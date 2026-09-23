<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Batasi akses panel Koordinator Piket.
 *
 * Koordinator Piket BUKAN role tetap di database, melainkan tugas dinamis yang
 * diturunkan dari jadwal piket (koordinator_pagi_user_id / koordinator_siang_user_id)
 * pada hari berjalan. Middleware ini menyaring:
 *  - Petugas IT / QA Tester: diizinkan (peninjau / penguji, pola guard lain).
 *  - Guru/Wali Kelas yang hari ini terdaftar sebagai koordinator (Pagi/Siang).
 *  - Selain itu ditolak 403.
 */
class EnsureKoordinatorPiket
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Silakan login terlebih dahulu.');
        }

        // Petugas IT / QA Tester: peninjau (isolasi data testing via scope).
        if ($user->isPetugasIt()) {
            return $next($request);
        }

        abort_unless(
            $user->koordinatorShiftHariIni() !== [],
            403,
            'Akses ditolak. Anda tidak bertugas sebagai Koordinator Piket hari ini.'
        );

        return $next($request);
    }
}