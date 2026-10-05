<?php

namespace App\Http\Middleware;

use App\Services\SingleDeviceSessionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Validasi sesi per-request (Single Device Session).
 *
 * Setiap request terautentikasi diperiksa: apakah "lock token" perangkat pada
 * sesi request masih sesuai dengan current_session_id di database. Jika tidak,
 * berarti perangkat ini sudah di-revoke (di-kick) oleh login perangkat lain
 * saat AFK → sesi lokal dihancurkan dan user diarahkan kembali ke halaman
 * login dengan pesan jelas.
 *
 * Endpoint heartbeat dilewati (skip) karena mengelola pengecekan "kicked"
 * sendiri dengan respon JSON agar frontend dapat menampilkan toast lalu redirect.
 */
class SingleDeviceSession
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Endpoint JSON heartbeat dikelola sendiri oleh controller-nya.
        if ($request->is('api/user/heartbeat')) {
            return $next($request);
        }

        // Suspend sementara berbasis waktu (suspended_until masih di masa
        // depan): sesi berjalan langsung dikeluarkan dengan pesan eksplisit
        // sampai kapan akun diblokir. Berlaku untuk semua driver session.
        if ($user->isCurrentlySuspended()) {
            $user->markOffline();
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with(
                'error',
                'Akun Anda sedang disuspend sementara sampai '.$user->suspended_until->format('d M Y H:i').'.'
            );
        }

        // Akun dinonaktifkan / di-suspend darurat (Peer Emergency Suspend):
        // sesi berjalan langsung dikeluarkan apa pun driver session yang dipakai
        // (file/cookie tidak dihapus lewat tabel `sessions`). Menutup celah
        // ketika current_session_id sudah dilepas menjadi null.
        if (! $user->is_active) {
            $user->markOffline();
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with(
                'error',
                'Akun Anda sedang nonaktif / di-suspend. Sesi telah dihentikan. Silakan hubungi Petugas TU / Admin.'
            );
        }

        $boundSessionId = $user->current_session_id;

        // Fitur belum aktif (mis. legacy user / mode testing) → biarkan lewat.
        if (empty($boundSessionId)) {
            return $next($request);
        }

        $deviceLock = (string) $request->session()->get(SingleDeviceSessionService::LOCK_KEY, '');

        if ($deviceLock !== $boundSessionId) {
            // Sesi ini sudah di-revoke oleh perangkat lain. Keluarkan paksa.
            $user->markOffline();
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with(
                'error',
                'Sesi Anda telah dihentikan otomatis karena Anda sedang AFK dan akun digunakan di perangkat lain.'
            );
        }

        return $next($request);
    }
}