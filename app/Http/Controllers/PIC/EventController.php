<?php

namespace App\Http\Controllers\PIC;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\TestSession;
use App\Models\TestResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;

class EventController extends Controller
{
    /**
     * Menampilkan daftar event milik PIC dengan pencarian realtime.
     */
    public function index(Request $request)
    {
        // Query dasar: Hanya event milik PIC yang sedang login
        $query = Event::where('pic_id', Auth::id())
            ->withCount('participants');

        // --- SEARCH LOGIC ---
        if ($request->filled('search')) {
            $term = trim($request->search);
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('event_code', 'like', "%{$term}%")
                    ->orWhere('company', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
            });
        }

        $events = $query->latest('start_date')->paginate(10)->appends($request->query());

        // --- AJAX RESPONSE (Untuk Realtime Search) ---
        if ($request->ajax()) {
            $events->getCollection()->transform(function ($event) {
                return [
                    'id' => $event->id,
                    'name' => $event->name,
                    'company' => $event->company ?? '-',
                    'event_code' => $event->event_code,
                    'participants_count' => $event->participants_count,
                    'max_participants' => $event->max_participants,
                    'is_active' => $event->is_active,
                    'date_range' => $event->start_date->format('d M') . ' - ' . $event->end_date->format('d M Y'),
                    'show_url' => route('pic.events.show', $event->id),
                ];
            });

            return response()->json([
                'events' => $events
            ]);
        }

        return view('pic.event.index', compact('events'));
    }

    /**
     * Menampilkan detail event (Sama persis logika Admin).
     */
    public function show(Event $event)
    {
        // Validasi kepemilikan event
        abort_unless($event->pic_id === Auth::id(), 403);

        // 1. Load participants BESERTA data sesi tes mereka di event ini
        $event->load(['participants.testSessions' => function($q) use ($event) {
            $q->where('event_id', $event->id);
        }]);

        // 2. Hitung Statistik Realtime
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
            'pending_tests'      => max(0, $totalParticipants - $completedTests),
            'results_sent'       => $resultsSent,
        ];

        return view('pic.event.show', compact('event', 'stats'));
    }

    /**
     * Export PDF Laporan Event PIC
     */

    public function exportPdf(Request $request)
    {
        // Query hanya mengambil event milik PIC yang sedang login
        $query = Event::where('pic_id', Auth::id())
            ->with(['pic']) // Eager load PIC info
            ->withCount('participants');

        if ($request->filled('search')) {
            $term = trim($request->search);
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('event_code', 'like', "%{$term}%")
                    ->orWhere('company', 'like', "%{$term}%");
            });
        }

        $events = $query->orderBy('start_date', 'asc')->get();

        // Load View yang baru dibuat
        $pdf = Pdf::loadView('pic.event.pdf.eventReport', [
            'reportTitle' => 'Laporan Event Saya',
            'generatedBy' => Auth::user()->name,
            'generatedAt' => now()->format('d M Y H:i'),
            'rows' => $events,
        ])->setPaper('a4', 'potrait');

        return $pdf->stream('Laporan_Event_PIC.pdf');
    }
}
