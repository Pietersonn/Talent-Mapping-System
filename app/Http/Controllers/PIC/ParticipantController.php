<?php

namespace App\Http\Controllers\PIC;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response; // Penting untuk download file
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;   // Penting untuk cek kolom database
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class ParticipantController extends Controller
{
    /** Helper: Ambil ID Event milik PIC */
    private function myEventIds(int $userId): array
    {
        return Event::where('pic_id', $userId)->pluck('id')->toArray();
    }

    /** LIST Participants (Index) */
    public function index(Request $request)
    {
        $picEventIds = $this->myEventIds(Auth::id());

        // 1. Ambil Parameter
        $search   = $request->input('search') ?? $request->input('q');
        $eventId  = $request->input('event_id');
        $n        = $request->input('n', 10);

        // 2. Query Builder
        $query = DB::table('test_sessions as ts')
            ->join('users as u', 'u.id', '=', 'ts.user_id')
            ->leftJoin('events as e', 'e.id', '=', 'ts.event_id')
            ->leftJoin('test_results as tr', 'tr.session_id', '=', 'ts.id')
            ->select(
                'ts.id as session_id',
                'u.name',
                'u.email',
                'ts.participant_background as instansi',
                'e.name as event_name',
                'e.event_code',
                'tr.pdf_path',
                'tr.sjt_results'
            )
            ->whereIn('ts.event_id', $picEventIds);

        // Filter
        if ($eventId) $query->where('ts.event_id', $eventId);
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('u.name', 'like', "%{$search}%")
                  ->orWhere('u.email', 'like', "%{$search}%")
                  ->orWhere('ts.participant_background', 'like', "%{$search}%");
            });
        }

        $query->orderBy('ts.created_at', 'desc');
        $rows = $query->paginate($n)->withQueryString();

        // 3. Transform Data
        $rows->getCollection()->transform(function ($row) {
            // Hitung Score
            $row->total_score = null;
            if (!empty($row->sjt_results)) {
                $data = json_decode($row->sjt_results, true);
                if (isset($data['all'])) {
                    $row->total_score = round(collect($data['all'])->sum('score'));
                }
            }

            // Generate URL Result
            // Pastikan parameter yang dikirim sesuai dengan yang diharapkan resultPdf (ID session)
            $row->download_url = (!empty($row->pdf_path))
                ? route('pic.participants.result-pdf', $row->session_id)
                : null;

            $row->event_name_short = Str::limit($row->event_name, 25);

            return $row;
        });

        // 4. AJAX Response
        if ($request->ajax()) {
            return response()->json([
                'data'  => $rows->items(),
                'links' => (string) $rows->links(),
                'from'  => $rows->firstItem() ?? 0
            ]);
        }

        // 5. View Response
        $events = Event::whereIn('id', $picEventIds)
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'event_code']);

        return view('pic.participants.index', [
            'rows'    => $rows,
            'events'  => $events,
            'filters' => ['search' => $search, 'event_id' => $eventId]
        ]);
    }

    /** EXPORT PDF (Laporan Tabel) */
    public function exportPdf(Request $request)
    {
        $picEventIds = $this->myEventIds(Auth::id());
        $search      = $request->input('search') ?? $request->input('q');
        $eventId     = $request->input('event_id');

        $query = DB::table('test_sessions as ts')
            ->join('users as u', 'u.id', '=', 'ts.user_id')
            ->leftJoin('events as e', 'e.id', '=', 'ts.event_id')
            ->select(
                'ts.id as session_id',
                'u.name',
                'u.email',
                'u.phone_number',
                'ts.participant_background as instansi',
                'ts.position as jabatan',
                'e.name as event_name'
            )
            ->whereIn('ts.event_id', $picEventIds);

        if ($eventId) $query->where('ts.event_id', $eventId);
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('u.name', 'like', "%{$search}%")
                  ->orWhere('u.email', 'like', "%{$search}%");
            });
        }

        $results = $query->orderBy('ts.created_at', 'desc')->get();

        $pdf = Pdf::loadView('pic.participants.pdf.participantReport', [
            'reportTitle' => 'Laporan Peserta PIC',
            'generatedBy' => Auth::user()->name,
            'generatedAt' => now()->format('d/m/Y H:i'),
            'rows'        => $results,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('Laporan_Peserta.pdf');
    }

    /** * DOWNLOAD RESULT PDF (Logic Baru yang Lebih Kuat)
     */
    public function resultPdf(string $session)
    {
        // 1. Cek Hak Akses (Hanya event milik PIC)
        $allowed = $this->myEventIds(Auth::id());

        // 2. Query Data
        $row = DB::table('test_sessions as ts')
            ->leftJoin('test_results as tr', 'tr.session_id', '=', 'ts.id')
            ->whereIn('ts.event_id', $allowed) // Filter Wajib: Event milik PIC
            ->where(function ($q) use ($session) {
                // Support pencarian by ID
                if (ctype_digit($session)) {
                    $q->orWhere('ts.id', (int)$session);
                }
                // Support pencarian by Code (jika ada kolom code)
                if (Schema::hasColumn('test_sessions', 'code')) {
                    $q->orWhere('ts.code', $session);
                }
            })
            ->select(['ts.id as session_id', 'ts.event_id', 'tr.pdf_path'])
            ->first();

        // 3. Validasi Keberadaan Data
        if (!$row || empty($row->pdf_path)) {
            abort(404, 'File hasil tidak ditemukan atau Anda tidak memiliki akses.');
        }

        $path = $row->pdf_path;

        // 4. Logika Pengambilan File

        // A. Jika URL Eksternal (S3, Supabase, dll) -> Redirect
        if (preg_match('~^https?://~i', $path)) {
            return redirect()->away($path);
        }

        // B. Cek di Storage Laravel (local, public, s3)
        $fileName = 'result-' . $row->session_id . '.pdf';

        // Cek disk default dulu, lalu fallback ke local/public
        $disksToCheck = [config('filesystems.default'), 'local', 'public'];

        foreach ($disksToCheck as $disk) {
            if (!$disk) continue;
            try {
                if (Storage::disk($disk)->exists($path)) {
                    $fileContent = Storage::disk($disk)->get($path);
                    return Response::make($fileContent, 200, [
                        'Content-Type'        => 'application/pdf',
                        'Content-Disposition' => 'inline; filename="' . $fileName . '"',
                    ]);
                }
            } catch (\Throwable $e) {
                // Abaikan error disk, lanjut ke disk berikutnya
                continue;
            }
        }

        // C. Cek Path Absolute di Server (Fallback terakhir)
        // Kadang path disimpan lengkap dari root server atau folder public
        if (file_exists($path)) {
            return Response::make(file_get_contents($path), 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $fileName . '"',
            ]);
        }

        // Cek di folder public jika path relatif
        if (file_exists(public_path($path))) {
             return Response::make(file_get_contents(public_path($path)), 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $fileName . '"',
            ]);
        }

        // 5. Jika semua cara gagal
        abort(404, 'File fisik PDF tidak ditemukan di server.');
    }
}
