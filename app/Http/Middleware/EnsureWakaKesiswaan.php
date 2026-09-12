<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Batasi akses portal Waka Kesiswaan:
 * - user role admin + sub_role waka_kesiswaan (jabatan Waka Kesiswaan);
 * - admin/super admin (sub_role null, eks. Petugas TU/TU) sebagai peninjau;
 * - Petugas IT / QA Tester dalam mode impersonasi active_role 'waka_kesiswaan'
 *   maupun mode IT langsung (sebagai peninjau/testing).
 */
class EnsureWakaKesiswaan
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Silakan login terlebih dahulu.');
        }

        if ($user->hasActiveRole() && $user->activeRole() === 'waka_kesiswaan') {
            return $next($request);
        }

        if ($user->isPetugasIt()) {
            return $next($request);
        }

        $allowed = ($user->role === 'admin'
                && in_array($user->sub_role, ['waka_kesiswaan', null], true))
            || $user->isWakaKesiswaan();

        abort_unless($allowed, 403, 'Akses ditolak. Halaman ini khusus untuk Waka Kesiswaan.');

        return $next($request);
    }
}
