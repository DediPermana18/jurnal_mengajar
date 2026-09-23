<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Batasi akses portal Waka Piket:
 * - user role 'admin' + sub_role 'waka_piket' (Penanggung Jawab Harian Piket);
 * - Waka Kurikulum (role admin + sub_role 'waka_kurikulum') sebagai pengawas
 *   bersama / pemilik jadwal piket;
 * - admin/super admin (sub_role null, eks. Petugas TU) sebagai peninjau;
 * - Petugas IT / QA Tester dalam mode impersonasi active_role 'waka_piket'
 *   maupun 'waka_kurikulum', atau mode IT langsung (peninjau/testing).
 */
class EnsureWakaPiket
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Silakan login terlebih dahulu.');
        }

        // Impersonasi Petugas IT sebagai Waka Piket / Waka Kurikulum.
        if ($user->hasActiveRole() && in_array($user->activeRole(), ['waka_piket', 'waka_kurikulum'], true)) {
            return $next($request);
        }

        // Petugas IT / QA Tester langsung: peninjau.
        if ($user->isPetugasIt()) {
            return $next($request);
        }

        $allowed = ($user->role === 'admin'
                && in_array($user->sub_role, ['waka_piket', 'waka_kurikulum', null], true))
            || $user->isWakaPiket();

        abort_unless($allowed, 403, 'Akses ditolak. Halaman ini khusus untuk Waka Piket / Kurikulum.');

        return $next($request);
    }
}