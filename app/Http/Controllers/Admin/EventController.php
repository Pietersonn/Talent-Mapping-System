<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Barryvdh\DomPDF\Facade\Pdf;

class EventController extends Controller
{
    /**
     * Menampilkan daftar event dengan fitur pencarian realtime (AJAX) dan filter.
     */
    public function index(Request $request)
    {
        // Query dasar: ambil relasi PIC dan hitung jumlah peserta
        $query = Event::with(['pic'])->withCount('participants');

        // --- SEARCH & FILTER LOGIC ---

        // 1. Pencarian Global (AJAX & Standard)
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

        // Ambil data terbaru dengan pagination (10 item per halaman)
        $events = $query->latest()->paginate(10)->appends($request->query());

        // --- RESPONSE UNTUK SEARCH REALTIME (AJAX) ---
        if ($request->ajax()) {
            // Transform data agar mudah dibaca oleh JavaScript di frontend
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

                    // URL Actions
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

    /**
     * Menampilkan form untuk membuat event baru.
     */
    public function create()
    {
        // Ambil user yang berperan sebagai PIC dan Aktif untuk dropdown
        $pics = User::where('role', 'pic')->where('is_active', true)->orderBy('name')->get();
        return view('admin.events.create', compact('pics'));
    }

    /**
     * Menyimpan event baru ke database.
     */
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
            'is_active' => ['nullable', 'boolean'], // Checkbox returns 1 or null
        ]);

        // Generate ID Custom (Misal: EVT0012)
        $eventId = 'EVT' . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

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
            'is_active' => $request->has('is_active'), // Checkbox handling
        ]);

        return redirect()->route('admin.events.index')->with('success', 'Event berhasil dibuat.');
    }

    /**
     * Menampilkan detail event.
     */
    public function show(Event $event)
    {
        $event->load(['pic', 'participants']);

        // Statistik sederhana untuk halaman detail
        $stats = [
            'total_participants' => $event->participants()->count(),
            'completed_tests' => $event->participants()->where('test_completed', true)->count(),
            'pending_tests' => $event->participants()->where('test_completed', false)->count(),
            'results_sent' => $event->participants()->where('results_sent', true)->count(),
        ];

        return view('admin.events.show', compact('event', 'stats'));
    }

    /**
     * Menampilkan form edit event.
     */
    public function edit(Event $event)
    {
        $pics = User::where('role', 'pic')->where('is_active', true)->orderBy('name')->get();
        return view('admin.events.edit', compact('event', 'pics'));
    }

    /**
     * Memperbarui data event.
     */
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

    /**
     * Menghapus event.
     */
    public function destroy(Event $event)
    {
        // Cek jika ada peserta
        if ($event->participants()->count() > 0) {
            return back()->with('error', 'Gagal menghapus: Event memiliki peserta terdaftar.');
        }

        $event->delete();
        return redirect()->route('admin.events.index')->with('success', 'Event berhasil dihapus.');
    }

    /**
     * Quick Action: Ubah status aktif/non-aktif.
     */
    public function toggleStatus(Event $event)
    {
        $event->update(['is_active' => !$event->is_active]);
        return back()->with('success', 'Status event diperbarui.');
    }

    /**
     * Export PDF.
     */
    public function exportPdf(Request $request)
    {
        $query = Event::with(['pic'])->withCount('participants');

        // Terapkan filter pencarian yang sama agar hasil PDF sesuai dengan yang tampil di tabel
        if ($request->filled('search')) {
            $term = trim($request->search);
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('event_code', 'like', "%{$term}%")
                    ->orWhere('company', 'like', "%{$term}%")
                    ->orWhereHas('pic', function ($p) use ($term) {
                        $p->where('name', 'like', "%{$term}%");
                    });
            });
        }

        $events = $query->orderBy('start_date', 'asc')->get();

        // INI BAGIAN PENTINGNYA: Load view pdf yang Anda buat
        $pdf = Pdf::loadView('admin.events.pdf.eventReport', [
            'reportTitle' => 'Laporan Data Event',
            'generatedBy' => Auth::user()->name,
            'generatedAt' => now()->format('d M Y H:i'),
            'rows' => $events,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('Laporan_Event.pdf');
    }
}
