<?php

namespace App\Http\Controllers\PIC;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\TestSession;
use App\Models\EventParticipant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $pic = Auth::user();
        abort_unless($pic !== null, 401);

        // 1. Ambil Event milik PIC
        $myEvents = Event::query()
            ->where('pic_id', $pic->id)
            ->where('is_active', true)
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'event_code', 'start_date', 'end_date', 'is_active', 'max_participants']);

        $eventIds = $myEvents->pluck('id');

        // 2. Hitung Statistik Global
        // Total Peserta: Diambil dari tabel event_participants (Orang yang didaftarkan/diundang)
        $totalParticipants = 0;
        if ($eventIds->isNotEmpty()) {
            $totalParticipants = EventParticipant::whereIn('event_id', $eventIds)->count();
        }

        // Completed Tests: Diambil dari tabel test_sessions (Orang yang benar-benar mengerjakan & selesai)
        $completedTests = 0;
        if ($eventIds->isNotEmpty()) {
            $completedTests = TestSession::whereIn('event_id', $eventIds)
                ->where('is_completed', true)
                ->count();
        }

        // Pending Tests: Peserta terdaftar dikurangi yang sudah selesai
        // (Max 0 untuk mencegah angka negatif jika ada anomali data)
        $pendingTests = max(0, $totalParticipants - $completedTests);
        $totalEvents  = $myEvents->count();

        // 3. Ambil Sesi Terbaru (Recent Activity)
        $recentSessions = $eventIds->isNotEmpty()
            ? TestSession::query()
            ->whereIn('event_id', $eventIds)
            ->with(['user:id,name,email', 'event:id,name,event_code'])
            ->latest('updated_at')
            ->limit(5)
            ->get()
            : collect();

        // 4. Siapkan Data Per Event (Untuk Tabel & Chart)
        // Map Peserta Terdaftar (Registered)
        $registeredMap = [];
        if ($eventIds->isNotEmpty()) {
            $registeredMap = EventParticipant::select('event_id', DB::raw('count(*) as total'))
                ->whereIn('event_id', $eventIds)
                ->groupBy('event_id')
                ->pluck('total', 'event_id')
                ->toArray();
        }

        // Map Peserta Selesai (Completed) - Optional jika ingin dipakai di view
        $completedMap = [];
        if ($eventIds->isNotEmpty()) {
            $completedMap = TestSession::select('event_id', DB::raw('count(*) as total'))
                ->whereIn('event_id', $eventIds)
                ->where('is_completed', true)
                ->groupBy('event_id')
                ->pluck('total', 'event_id')
                ->toArray();
        }

        $eventsData = [];
        foreach ($myEvents as $ev) {
            $registered = $registeredMap[$ev->id] ?? 0;
            $completed  = $completedMap[$ev->id] ?? 0;
            $quota      = $ev->max_participants ?? 0;

            $eventsData[] = [
                'event'      => $ev,
                'registered' => $registered,
                'completed'  => $completed,
                'quota'      => $quota,
                // Hitung persentase untuk progress bar (opsional)
                'completion_rate' => $registered > 0 ? round(($completed / $registered) * 100) : 0,
            ];
        }

        return view('pic.dashboard', [
            'totalEvents'       => $totalEvents,
            'totalParticipants' => $totalParticipants,
            'completedTests'    => $completedTests,
            'pendingTests'      => $pendingTests,
            'recentSessions'    => $recentSessions,
            'eventsData'        => $eventsData, // Variabel utama untuk tabel

            // Variabel kompatibilitas (jika view lama masih pakai ini)
            'eventProgress'     => $eventsData,
        ]);
    }
}
