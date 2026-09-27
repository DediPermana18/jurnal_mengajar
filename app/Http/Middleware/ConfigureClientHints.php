<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menginstruksikan Chromium (dan browser pendukung) untuk mengirim Client
 * Hints HIGH-ENTROPY pada request berikutnya: model perangkat
 * (Sec-CH-UA-Model) & versi platform (Sec-CH-UA-Platform-Version).
 *
 * Tanpa header Accept-CH ini, hanya hint low-entropy (Sec-CH-UA,
 * Sec-CH-UA-Mobile, Sec-CH-UA-Platform) yang dikirim otomatis — padahal
 * model perangkat adalah kunci membedakan dua HP Android yang sama-sama
 * tampil "Chrome • Android" (lihat DeviceSignatureService).
 *
 * Header dipasang di SEMUA respons web (idempotent, tidak memicu cache
 * negatif) sehingga halaman login pun mengundang hint pada request POST-nya.
 */
class ConfigureClientHints
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof Response) {
            $response->headers->set(
                'Accept-CH',
                'Sec-CH-UA, Sec-CH-UA-Mobile, Sec-CH-UA-Platform, Sec-CH-UA-Platform-Version, Sec-CH-UA-Model, Sec-CH-UA-Full-Version-List'
            );
        }

        return $response;
    }
}