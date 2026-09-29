<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Jejak aktivitas real-time user terautentikasi.
 *
 * `users.last_active_at` punya DUA konsumen:
 *  1. Status "Online" di tabel Kelola User — User::isOnline() (jendela 5 menit).
 *  2. Gate keamanan Single Device Session — perangkat lama dianggap "sangat
 *     aktif" (sehingga menolak login perangkat baru) bila `last_active_at`
 *     masih segar DAN `is_idle == false`.
 *
 * Karena itu setiap request yang lolos autentikasi menyegarkan `last_active_at`.
 *
 * Dua keputusan penting:
 *
 *  - Middleware ini didaftarkan SETELAH SingleDeviceSession pada grup 'web'
 *    (lihat bootstrap/app.php). SingleDeviceSession mem-return redirect untuk
 *    user yang di-kick / di-suspend / dinonaktifkan, dan pada pipeline Laravel
 *    middleware sesudahnya tidak ikut jalan. Akibatnya user bermasalah TIDAK
 *    menyegarkan jejak aktivitasnya — mereka tetap terlihat OFFLINE, sesuai
 *    yang diharapkan.
 *
 *  - `updated_at` SENGAJA tidak disentuh (memakai withoutTimestamps) agar makna
 *    kolom `users.updated_at` sebagai jejak PERUBAHAN DATA tetap utuh, bukan
 *    berubah menjadi "waktu request terakhir".
 *
 * Endpoint heartbeat (/api/user/heartbeat) dan polling status online
 * (/admin/users/online-status) dilewati: keduanya request latar (bukan
 * interaksi nyata user) sehingga TIDAK boleh menyegarkan `last_active_at` —
 * kalau tidak, sekadar membuka halaman Kelola User akan membuat akun tampak
 * online selamanya. Controller heartbeat memperbarui `last_active_at`+`is_idle`
 * sendiri sesuai kebutuhan.
 */
class UpdateUserLastActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $isBackgroundPoll = $request->is('api/user/heartbeat')
            || $request->is('admin/users/online-status');

        if ($user instanceof User && ! $isBackgroundPoll) {
            User::withoutTimestamps(function () use ($user): void {
                $user->forceFill(['last_active_at' => now()])->save();
            });
        }

        return $next($request);
    }
}
