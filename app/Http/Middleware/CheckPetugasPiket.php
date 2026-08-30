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

        abort_unless(
            $user && $user->isPetugasPiketHariIni(),
            403,
            'Akses ditolak. Anda tidak memiliki jadwal piket hari ini.'
        );

        return $next($request);
    }
}
