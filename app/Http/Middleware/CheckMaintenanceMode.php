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
 *    penolakan login dilakukan di AuthController (non-IT/QA ditolak).
 *  - Petugas IT / QA Tester (isTestingUser(), termasuk saat impersonasi
 *    "Switch View As") TETAP boleh mengakses sistem untuk perbaikan & verifikasi.
 *  - Role lain (Admin TU, Guru, Wali Kelas, Kesiswaan, Siswa) dan guest
 *    diblokir dan diarahkan ke tampilan errors.maintenance (HTTP 503).
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

        $user = $request->user();

        if ($user instanceof User && $user->isTestingUser()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Sistem sedang dalam pemeliharaan. Silakan coba lagi nanti.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return response()
            ->view('errors.maintenance')
            ->setStatusCode(Response::HTTP_SERVICE_UNAVAILABLE)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }
}
