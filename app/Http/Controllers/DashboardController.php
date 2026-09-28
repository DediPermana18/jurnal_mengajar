<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\PengaturanJadwal;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    /**
     * Halaman utama / Dashboard Admin.
     *
     * Dua lapis proteksi:
     *
     *  1. Impersonasi "Switch View As" (Petugas IT / QA Tester). Saat
     *     active_role aktif, peran yang ditampilkan adalah peran hasil
     *     preview, BUKAN peran asli user.
     *  2. Otorisasi role(user). Bila user TIDAK berhak melihat statistik
     *     sekolah, dialihkan ke portalnya sendiri (guru / piket / waka /
     *     kepsek / satpam / IT) — bukan diberi dashboard admin.
     *
     * JANGAN redirect ke route('home') di sini: akan membuat infinite loop
     * karena halaman ini adalah route('home') itu sendiri.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Tanpa user (middleware 'auth' gagal) → biarkan ke penolakan default.
        if (! $user) {
            abort(401, 'Silakan login terlebih dahulu.');
        }

        // ── 1. Impersonasi "Switch View As" ──────────────────────────────
        // Dialihkan ke portal peran yang sedang di-preview. Admin TU &
        // Super Admin tetap dirender di halaman ini (bukan redirect loop).
        if ($user->activeRole()) {
            $portalRoute = $user->dashboardRouteName();

            if ($portalRoute && $portalRoute !== 'home') {
                return redirect()->route($portalRoute);
            }
        }

        // ── 2. Otorisasi role(user) ──────────────────────────────────────
        // Guru Mapel / Wali Kelas / Satpam / Waka / Kepsek TIDAK boleh melihat
        // dashboard admin; arahkan ke portal masing-masing.
        if (! $user->canViewAdminDashboard()) {
            $portalRoute = $user->dashboardRouteName();

            // Fail-safe: role tak dikenal (null) → jangan tampilkan statistik
            // admin. Kembalikan ke halaman profil agar tidak bocor.
            if (! $portalRoute || $portalRoute === 'home') {
                Log::warning('dashboard:akses-ditolak-role-tidak-dikenal', [
                    'user_id' => $user->id,
                    'username' => $user->username,
                    'role' => $user->role,
                    'sub_role' => $user->sub_role,
                    'ip' => $request->ip(),
                ]);

                return redirect()->route('profil.index')
                    ->with('warning', 'Akun Anda belum memiliki hak akses dashboard. Hubungi administrator.');
            }

            return redirect()->route($portalRoute);
        }

        $totalGuru = User::where('role', User::ROLE_GURU)->count();
        $totalSiswa = Siswa::count();
        $totalKelas = Kelas::count();
        $akunTidakAktif = User::where('is_active', false)->count();

        $userTerbaru = User::latest()->take(5)->get();

        $pengaturanJadwal = PengaturanJadwal::getSetting();

        return view('admin.dashboard.index', compact(
            'totalGuru',
            'totalSiswa',
            'totalKelas',
            'akunTidakAktif',
            'userTerbaru',
            'pengaturanJadwal'
        ));
    }
}
