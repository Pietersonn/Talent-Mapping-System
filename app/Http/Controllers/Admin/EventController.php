<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use App\Models\TestSession; // Tambahkan ini
use App\Models\TestResult;  // Tambahkan ini
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Barryvdh\DomPDF\Facade\Pdf;

class EventController extends Controller
{
    /**
     * Menampilkan daftar event dengan fitur pencarian realtime (AJAX) dan filter.
     */
    public function index(Request $request)
    {
        $query = Event::with(['pic'])->withCount('participants');

        // --- SEARCH & FILTER LOGIC ---
        if ($request->filled('search')) {
            $term = trim($request->search);
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('event_code', 'like', "%{$term}%")
                    ->orWhere('company', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%")
                    ->orWhereHas('pic', function ($p) use ($term) {
                        $p->where('name', 'like', "%{$term}%");
                    });
            });
        }

        $events = $query->latest()->paginate(10)->appends($request->query());

        if ($request->ajax()) {
            $events->getCollection()->transform(function ($event) {
                return [
                    'id' => $event->id,
                    'name' => $event->name,
                    'company' => $event->company ?? '-',
                    'event_code' => $event->event_code,
                    'pic_name' => $event->pic->name ?? 'Belum ada PIC',
                    'participants_count' => $event->participants_count,
                    'max_participants' => $event->max_participants,
                    'is_active' => $event->is_active,
                    'date_range' => $event->start_date->format('d M') . ' - ' . $event->end_date->format('d M Y'),
                    'show_url' => route('admin.events.show', $event->id),
                    'edit_url' => route('admin.events.edit', $event->id),
                    'delete_url' => route('admin.events.destroy', $event->id),
                    'toggle_url' => route('admin.events.toggle-status', $event->id),
                ];
            });

            return response()->json([
                'events' => $events,
                'is_admin' => Auth::user()->role === 'admin'
            ]);
        }

        return view('admin.events.index', compact('events'));
    }

    public function create()
    {
        $pics = User::where('role', 'pic')->where('is_active', true)->orderBy('name')->get();
        return view('admin.events.create', compact('pics'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'event_code' => ['required', 'string', 'max:15', 'unique:events,event_code'],
            'company' => ['nullable', 'string', 'max:100'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'pic_id' => ['nullable', 'exists:users,id'],
            'max_participants' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($request) {
            $last = DB::table('events')
                ->lockForUpdate()
                ->select(DB::raw("CAST(SUBSTRING(id, 4) AS UNSIGNED) as num"))
                ->orderByDesc('num')
                ->first();

            $nextNumber = $last ? $last->num + 1 : 1;
            $eventId = 'EVT' . $nextNumber;

            Event::create([
                'id' => $eventId,
                'name' => $request->name,
                'event_code' => strtoupper($request->event_code),
                'company' => $request->company,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'pic_id' => $request->pic_id,
                'max_participants' => $request->max_participants,
                'description' => $request->description,
                'is_active' => $request->has('is_active'),
            ]);
        });

        return redirect()->route('admin.events.index')->with('success', 'Event berhasil dibuat.');
    }

    /**
     * Menampilkan detail event.
     * PERBAIKAN: Menggunakan data dari TestSession (Realtime) bukan Pivot.
     */
    public function show(Event $event)
    {
        // 1. Load participants BESERTA data sesi tes mereka di event ini
        $event->load(['pic', 'participants.testSessions' => function($q) use ($event) {
            $q->where('event_id', $event->id);
        }]);

        // 2. Hitung Statistik Realtime dari tabel TestSession & TestResult
        $totalParticipants = $event->participants->count();

        $completedTests = TestSession::where('event_id', $event->id)
            ->where('is_completed', true)
            ->count();

        $resultsSent = TestResult::whereHas('testSession', function($q) use ($event) {
                $q->where('event_id', $event->id);
            })
            ->whereNotNull('email_sent_at')
            ->count();

        $stats = [
            'total_participants' => $totalParticipants,
            'completed_tests'    => $completedTests,
            'pending_tests'      => $totalParticipants - $completedTests,
            'results_sent'       => $resultsSent,
        ];

        return view('admin.events.show', compact('event', 'stats'));
    }

    public function edit(Event $event)
    {
        $pics = User::where('role', 'pic')->where('is_active', true)->orderBy('name')->get();
        return view('admin.events.edit', compact('event', 'pics'));
    }

    public function update(Request $request, Event $event)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'event_code' => ['required', 'string', 'max:15', Rule::unique('events', 'event_code')->ignore($event->id)],
            'company' => ['nullable', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'pic_id' => ['nullable', 'exists:users,id'],
            'max_participants' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $event->update([
            'name' => $request->name,
            'event_code' => strtoupper($request->event_code),
            'company' => $request->company,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'pic_id' => $request->pic_id,
            'max_participants' => $request->max_participants,
            'description' => $request->description,
            'is_active' => $request->has('is_active'),
        ]);

        return redirect()->route('admin.events.index')->with('success', 'Event berhasil diperbarui.');
    }

    public function destroy(Event $event)
    {
        if ($event->participants()->count() > 0) {
            return back()->with('error', 'Gagal menghapus: Event memiliki peserta terdaftar.');
        }

        $event->delete();
        return redirect()->route('admin.events.index')->with('success', 'Event berhasil dihapus.');
    }

    public function toggleStatus(Event $event)
    {
        $event->update(['is_active' => !$event->is_active]);
        return back()->with('success', 'Status event diperbarui.');
    }

    public function exportPdf(Request $request)
    {
        $query = Event::with(['pic'])->withCount('participants');

        if ($request->filled('search')) {
            $term = trim($request->search);
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('event_code', 'like', "%{$term}%")
                    ->orWhere('company', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%")
                    ->orWhereHas('pic', function ($p) use ($term) {
                        $p->where('name', 'like', "%{$term}%");
                    });
            });
        }

        $events = $query->orderBy('start_date', 'asc')->get();

        $pdf = Pdf::loadView('admin.events.pdf.eventReport', [
            'reportTitle' => 'Laporan Data Event',
            'generatedBy' => Auth::user()->name,
            'generatedAt' => now()->format('d M Y H:i'),
            'rows' => $events,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('Laporan_Event.pdf');
    }
}
