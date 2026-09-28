<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPetugasPiket
{
    /**
     * Pastikan user login dan memiliki jadwal piket pada hari ini (Senin–Jumat).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isPetugasPiketHariIni()) {
            $redirectRoute = $user ? $user->dashboardRouteName() : 'login';

            if ($redirectRoute === 'piket.dashboard') {
                $redirectRoute = 'home';
            }

            return redirect()->route($redirectRoute)->with(
                'error',
                'Akses ditolak: Shift piket Anda sudah berakhir atau belum dimulai.'
            );
        }

        return $next($request);
    }
}
