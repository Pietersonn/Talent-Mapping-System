<?php

namespace App\Http\Controllers\PIC;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Event;

class ScoreController extends Controller
{
    /**
     * Helper: Ambil daftar ID Event milik PIC yang sedang login.
     */
    private function myEventIds()
    {
        return Event::where('pic_id', Auth::id())->pluck('id')->toArray();
    }

    /**
     * Helper: Ambil Data Event untuk Dropdown Filter
     */
    private function commonData()
    {
        $picEventIds = $this->myEventIds();

        $events = Event::whereIn('id', $picEventIds)
            ->orderBy('start_date', 'desc')
            ->get(['id', 'name', 'event_code']);

        return compact('events');
    }

    /**
     * Query Dasar Peserta (Diadaptasi dari Admin, ditambah filter PIC)
     */
    private function baseParticipantsQuery(array $filters, bool $onlyWithResults = true)
    {
        // Ambil ID event milik PIC
        $allowedEventIds = $this->myEventIds();

        // Query Builder
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
                'tr.sjt_results'
                // Top Competency dihapus jika tidak dipakai di tabel, agar query lebih ringan
            )
            ->whereIn('ts.event_id', $allowedEventIds); // WAJIB: Batasi data hanya event milik PIC

        // Filter Event Spesifik (jika dipilih di dropdown)
        if (!empty($filters['event_id'])) {
            // Pastikan event yang dipilih benar-benar milik PIC (security check)
            if (in_array($filters['event_id'], $allowedEventIds)) {
                $q->where('ts.event_id', $filters['event_id']);
            }
        }

        // Filter Pencarian (Nama, Email, Instansi)
        if (!empty($filters['q'])) {
            $term = $filters['q'];
            $q->where(function ($w) use ($term) {
                $w->where('u.name', 'like', "%{$term}%")
                  ->orWhere('u.email', 'like', "%{$term}%")
                  ->orWhere('ts.participant_background', 'like', "%{$term}%");
            });
        }

        // Hanya ambil yang sudah ada hasil tesnya
        if ($onlyWithResults) {
            $q->whereNotNull('tr.sjt_results');
        }

        return $q;
    }

    /**
     * Helper: Hitung Skor & Parsing JSON (Sama persis dengan Admin)
     */
    private function processScores($results)
    {
        return $results->map(function ($row) {
            $totalScore = 0;
            $sjtData = null;

            if (!empty($row->sjt_results)) {
                $sjtData = json_decode($row->sjt_results, true);

                // Fallback jika struktur JSON berbeda (langsung object vs key 'all')
                $scores = $sjtData['all'] ?? $sjtData ?? [];

                if (is_array($scores)) {
                    foreach ($scores as $competency) {
                        if (isset($competency['score']) && is_numeric($competency['score'])) {
                            $totalScore += (float) $competency['score'];
                        }
                    }
                }
            }
            $row->total_score = $totalScore;

            // Mapping Kode Kompetensi
            $codes = ['SM', 'CIA', 'TS', 'WWO', 'CA', 'L', 'SE', 'PS', 'PE', 'GH'];
            $competencies = collect([]);

            $scoresMap = $sjtData['all'] ?? $sjtData ?? []; // Gunakan variabel yang sudah di-check

            if (is_array($scoresMap)) {
                foreach ($scoresMap as $c) {
                    // Cek format data: bisa jadi key langsung nama kompetensi atau ada field 'code'
                    // Di sini kita asumsikan mapping berdasarkan nama kompetensi ke kode
                    // Atau jika di JSON sudah ada 'code', kita pakai itu.
                    // CODE ADAPTASI DARI ADMIN:
                    if (isset($c['code'], $c['score'])) {
                        $competencies->put($c['code'], $c['score']);
                    }
                    // Fallback: Mapping manual jika 'code' tidak ada di JSON tapi Key array adalah Nama Kompetensi
                    elseif (isset($c['score'])) {
                        // Logic mapping nama ke kode (opsional, sesuaikan dengan data real)
                    }
                }
            }

            // Jika JSON menggunakan Key sebagai Nama Kompetensi (Struktur umum TalentMapping)
            // Kita perlu mapping manual nama ke kode jika 'code' tidak tersedia di dalam item
            if ($competencies->isEmpty() && is_array($scoresMap)) {
                $mapNameCode = [
                    'Self Management' => 'SM',
                    'Creativity & Innovation' => 'CIA',
                    'Technical Skill' => 'TS',
                    'Working With Others' => 'WWO',
                    'Customer Awareness' => 'CA',
                    'Leadership' => 'L',
                    'Social Engagement' => 'SE',
                    'Problem Solving' => 'PS',
                    'Planning & Execution' => 'PE',
                    'Grit & Hardwork' => 'GH'
                ];
                foreach ($scoresMap as $name => $val) {
                    if (isset($mapNameCode[$name]) && isset($val['score'])) {
                        $competencies->put($mapNameCode[$name], $val['score']);
                    }
                }
            }

            foreach ($codes as $code) {
                $row->{$code} = round($competencies->get($code, 0), 1);
            }

            return $row;
        });
    }

    /**
     * Page Index (Tabel Skor)
     */
    public function index(Request $req)
    {
        // Validasi Input
        $mode = $req->query('mode', 'all');
        $n    = (int) $req->query('n', 10);

        $filters = [
            'event_id' => $req->query('event_id'),
            'q'        => trim((string) $req->query('q', '')),
        ];

        // Ambil Data dari DB
        $q = $this->baseParticipantsQuery($filters, true);
        $results = $q->orderBy('u.name')->orderBy('ts.id')->get();

        // Proses Skor
        $processedRows = $this->processScores($results);

        // Sorting Logic (Top/Bottom/All)
        if ($mode === 'top') {
            $sortedRows = $processedRows->sortByDesc('total_score')->take($n);
        } elseif ($mode === 'bottom') {
            $sortedRows = $processedRows->sortBy('total_score')->take($n);
        } else {
            // Default sort by Name
            $sortedRows = $processedRows->sortBy('name');
        }

        // Manual Pagination
        $rows = null;
        $pagination = null;

        if ($mode === 'all') {
            $perPage = $n; // Gunakan N sebagai per page jika mode All
            $currentPage = LengthAwarePaginator::resolveCurrentPage();
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

        // Data Pendukung View
        $common = $this->commonData();

        return view('pic.score.index', [
            'events'     => $common['events'],
            'rows'       => $rows,
            'pagination' => $pagination,
            'mode'       => $mode,
            'n'          => $n,
            'filters'    => $filters,
            'q'          => $filters['q'] // Pass 'q' explicitly for view
        ]);
    }

    /**
     * Export PDF
     */
    public function exportPdf(Request $req)
    {
        $mode     = $req->query('mode', 'all');
        $n        = (int) $req->query('n', 10);
        $filters = [
            'event_id' => $req->query('event_id'),
            'q'        => trim((string) $req->query('q', '')),
        ];

        // Ambil Data
        $q = $this->baseParticipantsQuery($filters, true);
        $results = $q->orderBy('u.name')->orderBy('ts.id')->get();

        // Proses Skor
        $processedRows = $this->processScores($results);

        // Sorting & Limiting untuk PDF
        if ($mode === 'top') {
            $rows = $processedRows->sortByDesc('total_score')->take($n);
        } elseif ($mode === 'bottom') {
            $rows = $processedRows->sortBy('total_score')->take($n);
        } else {
            $rows = $processedRows->sortByDesc('total_score'); // Default PDF sort by score
        }

        // Teks Judul Laporan
        $modeText = match ($mode) {
            'top' => "Top {$n} Peserta (Skor Tertinggi)",
            'bottom' => "Bottom {$n} Peserta (Skor Terendah)",
            default => "Semua Peserta"
        };

        // Info Filter di PDF
        $filterTextParts = [];
        if(!empty($filters['event_id'])) {
            $evtName = Event::find($filters['event_id'])->name ?? '-';
            $filterTextParts[] = "Event: $evtName";
        }
        if(!empty($filters['q'])) {
            $filterTextParts[] = "Pencarian: '{$filters['q']}'";
        }
        $filterInfo = !empty($filterTextParts) ? implode(', ', $filterTextParts) : '';

        $pdfData = [
            'rows'        => $rows,
            'reportTitle' => 'Laporan Kompetensi Peserta (PIC)',
            'modeText'    => $modeText . ($filterInfo ? " - " . $filterInfo : ""),
            'generatedBy' => Auth::user()->name ?? 'PIC',
            'generatedAt' => now()->format('d M Y H:i') . ' WITA',
        ];

        $pdf = Pdf::loadView('pic.score.pdf.scoreReport', $pdfData)
            ->setPaper('a4', 'landscape');

        return $pdf->stream('Laporan_Skor_Kompetensi.pdf');
    }
}
