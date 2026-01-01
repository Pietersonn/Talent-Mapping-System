<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Event;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;

class ScoreController extends Controller
{
    private function filters(Request $r): array
    {
        return [
            'event_id' => $r->query('event_id'),
            // Instansi dihapus dari filter terpisah, digabung ke 'q'
            'q'        => trim((string) $r->query('q', '')),
        ];
    }

    private function commonData()
    {
        $events = Event::query()->orderBy('start_date', 'desc')->get(['id', 'name', 'event_code']);
        return compact('events');
    }

    private function baseParticipantsQuery(array $filters, bool $onlyWithResults = true)
    {
        $topCompExpr = "JSON_UNQUOTE(JSON_EXTRACT(tr.sjt_results, '$.top3[0].name'))";

        $q = DB::table('test_sessions as ts')
            ->join('users as u', 'u.id', '=', 'ts.user_id')
            ->leftJoin('events as e', 'e.id', '=', 'ts.event_id')
            ->leftJoin('test_results as tr', 'tr.session_id', '=', 'ts.id')
            ->select(
                'ts.id as session_id',
                'u.name',
                'u.email',
                'u.phone_number',
                'ts.participant_background as instansi',
                'ts.position',
                'e.name as event_name',
                'e.event_code',
                'tr.sjt_results',
                DB::raw("{$topCompExpr} as top_competency")
            );

        if (!empty($filters['event_id'])) {
            $q->where('ts.event_id', $filters['event_id']);
        }

        // LOGIKA PENCARIAN BARU: Mencakup Nama, Email, dan Instansi
        if (($filters['q'] ?? '') !== '') {
            $term = $filters['q'];
            $q->where(function ($w) use ($term) {
                $w->where('u.name', 'like', "%{$term}%")
                    ->orWhere('u.email', 'like', "%{$term}%")
                    ->orWhere('ts.participant_background', 'like', "%{$term}%"); // Tambahan pencarian instansi
            });
        }

        if ($onlyWithResults) {
            $q->whereNotNull('tr.sjt_results');
        }

        return $q;
    }

    // ... method participants() sama seperti sebelumnya ...
    public function participants(Request $req)
    {
        // Validasi
        $validated = $req->validate([
            'mode'     => 'nullable|in:all,top,bottom',
            'n'        => 'nullable|integer|min:1|max:5000',
            'event_id' => 'nullable|string|exists:events,id',
            'q'        => 'nullable|string|max:255',
        ]);

        $mode = $validated['mode'] ?? 'all';
        $n    = (int)($validated['n'] ?? 10);

        $filters = [
            'event_id' => $validated['event_id'] ?? null,
            'q'        => $validated['q'] ?? null,
        ];

        $common = $this->commonData();
        $events = $common['events'];

        $q = $this->baseParticipantsQuery($filters, true);
        $results = $q->orderBy('u.name')->orderBy('ts.id')->get();

        // Process Scoring (Sama seperti sebelumnya)
        $processedRows = $results->map(function ($row) {
            $totalScore = 0;
            $sjtData = null;
            if (!empty($row->sjt_results)) {
                $sjtData = json_decode($row->sjt_results, true);
                if (isset($sjtData['all']) && is_array($sjtData['all'])) {
                    foreach ($sjtData['all'] as $competency) {
                        if (isset($competency['score']) && is_numeric($competency['score'])) {
                            $totalScore += (float) $competency['score'];
                        }
                    }
                }
            }
            $row->total_score = $totalScore;

            $codes = ['SM', 'CIA', 'TS', 'WWO', 'CA', 'L', 'SE', 'PS', 'PE', 'GH'];
            $competencies = collect([]);
            if (isset($sjtData['all']) && is_array($sjtData['all'])) {
                foreach ($sjtData['all'] as $c) {
                    if (isset($c['code'], $c['score'])) {
                        $competencies->put($c['code'], $c['score']);
                    }
                }
            }
            foreach ($codes as $code) {
                $row->{$code} = round($competencies->get($code, 0), 1);
            }
            return $row;
        });

        // Sorting Logic
        if ($mode === 'top') {
            $sortedRows = $processedRows->sortByDesc('total_score')->take($n);
        } elseif ($mode === 'bottom') {
            $sortedRows = $processedRows->sortBy('total_score')->take($n);
        } else {
            $sortedRows = $processedRows->sortByDesc('total_score');
        }

        // Pagination Manual
        $rows = null;
        $pagination = null;
        if ($mode === 'all') {
            $perPage = 25;
            $currentPage = $req->integer('page', 1);
            $paginatedItems = $sortedRows->slice(($currentPage - 1) * $perPage, $perPage);

            $pagination = new LengthAwarePaginator(
                $paginatedItems->values(),
                $sortedRows->count(),
                $perPage,
                $currentPage,
                ['path' => $req->url(), 'query' => $req->query()]
            );
            $rows = $paginatedItems->values();
        } else {
            $rows = $sortedRows->values();
        }

        return view('admin.score.index', [
            'events'     => $events,
            'mode'       => $mode,
            'n'          => $n,
            'rows'       => $rows,
            'pagination' => $pagination,
            'filters'    => $filters,
        ]);
    }

    public function exportParticipantsPdf(Request $req)
    {
        $mode     = $req->query('mode', 'all');
        $n        = (int) $req->query('n', 10);
        $eventId  = $req->query('event_id');
        $search   = trim((string) $req->query('q', ''));

        $filters = [
            'event_id' => $eventId,
            'q'        => $search,
        ];

        $q = $this->baseParticipantsQuery($filters, true);
        $results = $q->orderBy('u.name')->orderBy('ts.id')->get();

        // Process Logic (Copy dari atas agar konsisten)
        $processedRows = $results->map(function ($row) {
            $totalScore = 0;
            $sjtData = null;
            if (!empty($row->sjt_results)) {
                $sjtData = json_decode($row->sjt_results, true);
                if (isset($sjtData['all']) && is_array($sjtData['all'])) {
                    foreach ($sjtData['all'] as $competency) {
                        if (isset($competency['score']) && is_numeric($competency['score'])) {
                            $totalScore += (float) $competency['score'];
                        }
                    }
                }
            }
            $row->total_score = $totalScore;
            $codes = ['SM', 'CIA', 'TS', 'WWO', 'CA', 'L', 'SE', 'PS', 'PE', 'GH'];
            $competencies = collect([]);
            if (isset($sjtData['all']) && is_array($sjtData['all'])) {
                foreach ($sjtData['all'] as $c) {
                    if (isset($c['code'], $c['score'])) {
                        $competencies->put($c['code'], $c['score']);
                    }
                }
            }
            foreach ($codes as $code) {
                $row->{$code} = round($competencies->get($code, 0), 1);
            }
            return $row;
        });

        if ($mode === 'top') {
            $rows = $processedRows->sortByDesc('total_score')->take($n);
        } elseif ($mode === 'bottom') {
            $rows = $processedRows->sortBy('total_score')->take($n);
        } else {
            $rows = $processedRows->sortByDesc('total_score');
        }

        // --- BAHASA INDONESIA UNTUK PDF ---
        $reportTitle = 'Laporan Kompetensi Peserta';
        $modeText = match ($mode) {
            'top' => "Top {$n} Peserta Berdasarkan Total Skor",
            'bottom' => "Bottom {$n} Peserta Berdasarkan Total Skor",
            default => "Semua Peserta — Diurutkan Berdasarkan Skor Tertinggi",
        };

        // Filter text untuk ditampilkan di PDF (Opsional, agar user tau filter apa yang dipakai)
        $filterTextParts = [];
        if($eventId) {
            $evtName = Event::find($eventId)->name ?? $eventId;
            $filterTextParts[] = "Event: $evtName";
        }
        if($search) {
            $filterTextParts[] = "Pencarian: '$search'";
        }
        $filterInfo = !empty($filterTextParts) ? implode(', ', $filterTextParts) : 'Tanpa Filter Tambahan';


        $data = [
            'rows'        => $rows,
            'reportTitle' => $reportTitle,
            'modeText'    => $modeText . " (" . $filterInfo . ")",
            'generatedBy' => Auth::user()->name ?? 'Admin',
            'generatedAt' => now('Asia/Makassar')->format('d M Y H:i') . ' WITA',
        ];

        return Pdf::loadView('pic.participants.pdf.report-participant', $data)
            ->setPaper('a4', 'landscape')
            ->setOptions(['isRemoteEnabled' => true])
            ->stream('laporan-skor-peserta.pdf');
    }
}
