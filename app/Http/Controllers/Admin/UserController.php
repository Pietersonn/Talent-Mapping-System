<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;

class UserController extends Controller
{
    /**
     * Menampilkan daftar user, menangani pencarian (search), filter, dan response AJAX untuk tabel.
     */
    public function index(Request $request)
    {
        // Query dasar dengan menghitung relasi untuk statistik sederhana jika diperlukan
        $query = User::withCount(['testSessions', 'picEvents']);

        // Logika Pencarian (Mencakup Nama, Email, Role, dan No HP)
        if ($request->has('search') && !empty($request->search)) {
            $term = $request->search;
            $query->where(function($q) use ($term) {
                $q->where('name', 'like', '%'.$term.'%')
                  ->orWhere('email', 'like', '%'.$term.'%')
                  ->orWhere('role', 'like', '%'.$term.'%')
                  ->orWhere('phone_number', 'like', '%'.$term.'%');
            });
        }

        // Filter berdasarkan Role (Opsional)
        if ($request->has('role') && !empty($request->role)) {
            $query->where('role', $request->role);
        }

        // Ambil data terbaru dengan pagination
        $users = $query->latest()->paginate(10);

        // Jika request berasal dari AJAX (Pencarian Realtime), kembalikan JSON
        if ($request->ajax()) {
            $users->getCollection()->transform(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone_number' => $user->phone_number ?? '-',
                    'role' => ucfirst($user->role),
                    'role_raw' => $user->role, // Digunakan untuk class warna badge di CSS
                    'is_active' => $user->is_active,
                    'avatar_letter' => substr($user->name, 0, 1),
                    'edit_url' => route('admin.users.edit', $user->id),
                    'delete_url' => route('admin.users.destroy', $user->id),
                    'show_url' => route('admin.users.show', $user->id),
                ];
            });

            return response()->json([
                'users' => $users,
                'current_user_id' => Auth::id(),
                'is_admin' => Auth::user()->role === 'admin'
            ]);
        }

        // Jika bukan AJAX, tampilkan view utama
        return view('admin.users.index', compact('users'));
    }

    /**
     * Menampilkan form untuk membuat user baru.
     */
    public function create()
    {
        return view('admin.users.create');
    }

    /**
     * Menyimpan data user baru ke database setelah validasi.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => ['required', 'string', 'in:admin,staff,pic,user'],
            'phone_number' => ['nullable', 'string', 'max:20'],
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'phone_number' => $request->phone_number,
            'is_active' => $request->has('is_active'),
        ]);

        return redirect()->route('admin.users.index')->with('success', 'User created successfully.');
    }

    /**
     * Menampilkan detail profil user beserta statistik aktivitasnya.
     */
    public function show(User $user)
    {
        // Eager load relasi untuk ditampilkan di halaman detail
        $user->load(['testSessions.event', 'picEvents', 'resendRequests.approvedBy']);

        // Menghitung statistik untuk dashboard mini di profil user
        $stats = [
            'total_test_sessions' => $user->testSessions()->count(),
            'completed_tests' => $user->testSessions()->where('is_completed', true)->count(),
            'events_as_pic' => $user->picEvents()->count(),
            'account_age' => $user->created_at->diffForHumans(null, true),
        ];

        return view('admin.users.show', compact('user', 'stats'));
    }

    /**
     * Menampilkan form untuk mengedit data user.
     */
    public function edit(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    /**
     * Memperbarui data user yang sudah ada di database.
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'role' => ['required', 'string', 'in:admin,staff,pic,user'],
            'phone_number' => ['nullable', 'string', 'max:20'],
        ]);

        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
            'phone_number' => $request->phone_number,
            'is_active' => $request->has('is_active'),
        ];

        // Update password hanya jika diisi
        if ($request->filled('password')) {
            $request->validate([
                'password' => ['confirmed', Rules\Password::defaults()],
            ]);
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return redirect()->route('admin.users.index')->with('success', 'User updated successfully.');
    }

    /**
     * Menghapus user dari database (dengan proteksi hapus diri sendiri).
     */
    public function destroy(User $user)
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', 'Cannot delete your own account.');
        }
        $user->delete();
        return redirect()->route('admin.users.index')->with('success', 'User deleted successfully.');
    }

    /**
     * Mengubah status aktif/non-aktif user secara cepat (Quick Action).
     */
    public function toggleStatus(User $user)
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', 'Cannot deactivate your own account.');
        }
        $user->update(['is_active' => !$user->is_active]);
        return back()->with('success', 'User status updated.');
    }

    /**
     * Mereset password user ke string acak secara manual (Quick Action).
     */
    public function resetPassword(User $user)
    {
        // Generate password acak 10 karakter
        $tempPassword = Str::random(10);
        $user->update(['password' => Hash::make($tempPassword)]);

        // Mengembalikan password sementara ke flash session agar bisa dicopy admin
        return back()->with('success', 'Password reset to: ' . $tempPassword . ' (Please copy this)');
    }
    public function exportPdf(Request $request)
    {
        $query = User::query();

        if ($request->filled('search')) {
            $term = trim($request->search);
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('email', 'like', "%{$term}%")
                  ->orWhere('role', 'like', "%{$term}%")
                  ->orWhere('phone_number', 'like', "%{$term}%");
            });
        }

        $users = $query->orderBy('name', 'asc')->get();

        $pdf = Pdf::loadView('admin.users.pdf.userReport', [
            'reportTitle' => 'Laporan Data Pengguna',
            'generatedBy' => Auth::user()->name,
            'generatedAt' => now()->format('d/m/Y H:i') . ' WITA',
            'rows'        => $users,
        ])->setPaper('a4', 'potrait');

        return $pdf->stream('Laporan_User.pdf');
    }

}
