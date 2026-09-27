<?php

namespace App\Http\Middleware;

use App\Models\PengaturanJadwal;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Maintenance Mode (Mode Perbaikan Sistem).
 *
 * Bila aktif:
 *  - Halaman login (GET & POST /login) SELALU terbuka untuk siapa saja —
 *    penolakan login dilakukan di AuthController (non-bypass ditolak).
 *  - Akun yang berhak bypass (User::canBypassMaintenance): Petugas IT / QA
 *    Tester (termasuk impersonasi "Switch View As"), Super Admin (termasuk
 *    akun hasil Emergency Takeover "Kartu As": role 'admin' + sub_role
 *    'super_admin'), role 'admin' (TU, waka, satpam, kepsek, dsb.), dan akun
 *    IT khusus username 'petugas.it' — TETAP boleh mengakses sistem untuk
 *    perbaikan & pengendalian darurat.
 *  - Role lain (Guru, Wali Kelas, Kesiswaan, Siswa) dan guest diblokir dan
 *    diarahkan ke tampilan errors.maintenance (HTTP 503).
 *  - Endpoint demote-self (it-emergency.demote-self) SELALU terbuka saat
 *    maintenance: jalur pemulihan darurat agar akun hasil takeover yang
 *    terblokir dapat mengembalikan diri ke Mode IT biasa (keamanan tetap
 *    dijaga gate di ItEmergencyController: hanya akun is_emergency_takeover).
 * Bila nonaktif: semua berjalan normal.
 */
class CheckMaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        $maintenanceActive = PengaturanJadwal::isMaintenanceModeActive();

        $isLoginRequest = $request->is('login') || $request->routeIs('login', 'login.post');

        // Saat maintenance aktif, halaman login tetap terbuka (GET & POST).
        if ($maintenanceActive && $isLoginRequest) {
            return $next($request);
        }

        if (! $maintenanceActive) {
            return $next($request);
        }

        // Jalur pemulihan darurat: demote-self tetap diizinkan saat maintenance
        // aktif (akun hasil Emergency Takeover dapat kembali ke Mode IT).
        if ($request->routeIs('it-emergency.demote-self')) {
            return $next($request);
        }

        $user = $request->user();

        if ($user instanceof User && $user->canBypassMaintenance()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Sistem sedang dalam pemeliharaan. Silakan coba lagi nanti.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        // User terblokir yang ternyata beridentitas IT / pengendali darurat
        // di-pass ke halaman maintenance agar tombol "Login / Restore Mode IT"
        // dapat dirender (mis. akun hasil takeover pada konfigurasi lama).
        $maintenanceBlockedUser = ($user instanceof User && $user->isItOriginatedAccount())
            ? $user
            : null;

        return response()
            ->view('errors.maintenance', compact('maintenanceBlockedUser'))
            ->setStatusCode(Response::HTTP_SERVICE_UNAVAILABLE)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }
}
