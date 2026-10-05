<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Smart Idle-Aware Single Device Session.
 *
 * Inti logika single-device: setiap user hanya boleh memiliki SATU sesi yang
 * dianggap valid (current_session_id). Identitas "perangkat" memakai LOCK TOKEN
 * yang disimpan DI DALAM data sesi (key: single_device_lock), bukan
 * session()->getId() — karena lock token ikut bertahan saat sesi di-migrate
 * (regenerate) dan saat driver sesi array/test-cookie tidak disebar antar
 * request. Sesi lain yang mencoba login akan:
 *  - Ditolak (HTTP 409 Conflict) bila perangkat pertama SANGAT AKTIF
 *    (is_idle == false DAN last_active_at masih dalam jendela aktif 3 menit).
 *  - Mengambil alih (take-over) bila perangkat pertama AFK / stale,
 *    lalu perangkat pertama otomatis di-kick pada request berikutnya.
 *
 * Catatan penting: status "aktif" ditentukan oleh data di tabel users
 * (is_idle + last_active_at yang diperbarui heartbeat real-time), BUKAN dengan
 * mengecek keberadaan row di tabel sessions — karena sesi memakai lock token,
 * bukan ID row sessions.
 */
class SingleDeviceSessionService
{
    /** Key di dalam data sesi yang menampung lock token perangkat. */
    public const LOCK_KEY = 'single_device_lock';

    /**
     * Key di dalam data sesi: AFK auto-logout DINONAKTIFKAN untuk sesi berjalan.
     *
     * Di-set saat Device A memilih aksi [Tolak & Amankan Akun] (atau selesai
     * mengganti password): selama flag ini true, heartbeat/API tidak boleh
     * menandai sesi sebagai idle, sehingga sesi tidak bisa di-take-over oleh
     * perangkat lain karena AFK — hingga user Logout & Login kembali.
     */
    public const AFK_DISABLED_KEY = 'disable_afk_protection';

    /** Ambang AFK client-side (menit) — sinkron dengan idleTracker.js. */
    public const IDLE_THRESHOLD_MINUTES = 3;

    /**
     * Jendela "sangat aktif" (menit): jika last_active_at lebih baru dari X
     * menit lalu AND is_idle == false → perangkat dianggap aktif → login
     * perangkat lain DIBLOKIR.
     */
    public const ACTIVE_WINDOW_MINUTES = 3;

    public function __construct()
    {
    }

    /**
     * Resolve konflik login dari perangkat baru.
     *
     * Urutan pemeriksaan (logic gate):
     *  1. Tidak ada ikatan sesi (current_session_id kosong)  → 'none'.
     *  2. Lock token sama (re-login perangkat yang sama)     → 'none'.
     *  3. Perangkat lama AKTIF (is_idle == false DAN last_active_at
     *     dalam 3 menit terakhir)                            → 'active' (BLOKIR).
     *  4. Selain itu (AFK / sudah lama tak aktif)            → 'idle' (take-over).
     *
     * @param string $deviceLock lock token milik perangkat yang BARU login
     *                           (dari data sesi request; kosong bila sesi baru).
     * @return string 'none' | 'idle' | 'active'
     */
    public function resolveLoginConflict(User $user, string $deviceLock): string
    {
        $boundSessionId = $user->current_session_id;

        // 1. Belum pernah terikat sesi → perangkat bebas login.
        if (empty($boundSessionId)) {
            return 'none';
        }

        // 2. Re-login dari perangkat/sesi yang sama (lock token sama) → biarkan.
        if ($deviceLock !== '' && $boundSessionId === $deviceLock) {
            return 'none';
        }

        // 3. Perangkat lama terdeteksi SANGAT AKTIF → tolak login perangkat baru.
        $recentlyActive = $user->last_active_at !== null
            && $user->last_active_at->gte(Carbon::now()->subMinutes(self::ACTIVE_WINDOW_MINUTES));

        if (! $user->is_idle && $recentlyActive) {
            return 'active';
        }

        // 4. Perangkat lama AFK (is_idle == true) atau sudah lama tak mengirim
        //    heartbeat (diluar jendela aktif) → boleh take-over.
        return 'idle';
    }

    /**
     * Ikat sesi yang barusan berhasil login sebagai sesi aktif saat ini.
     * Default state perangkat: aktif (is_idle = false) + last_active_at = now().
     */
    public function bindSession(User $user, string $deviceLock): void
    {
        $user->forceFill([
            'current_session_id' => $deviceLock,
            'last_active_at' => Carbon::now(),
            'is_idle' => false,
        ])->save();
    }

    /**
     * Heartbeat client-side (idleTracker.js).
     *
     * Memperbarui last_active_at/is_idle di database secara real-time,
     * sekaligus mendeteksi apakah perangkat ini sudah di-revoke oleh
     * perangkat lain (kicked).
     *
     * @return array{kicked: bool, message: string, security_alert_at: string|null}
     */
    public function heartbeat(User $user, bool $isIdle, string $deviceLock): array
    {
        // Akun sedang di-suspend sementara (suspended_until di masa depan) →
        // sesi dikeluarkan; frontend menampilkan batas waktu suspend.
        if ($user->isCurrentlySuspended()) {
            if (Auth::id() === (int) $user->id) {
                $user->markOffline();
                Auth::logout();
            }

            return [
                'kicked' => true,
                'message' => 'Akun Anda sedang disuspend sementara sampai '.$user->suspended_until->format('d M Y H:i').'.',
                'security_alert_at' => $user->last_security_alert_at?->toDateTimeString(),
            ];
        }

        // Akun dinonaktifkan / di-suspend darurat → sesi ini langsung
        // dikeluarkan, apa pun driver session (file/cookie tidak ikut terhapus
        // oleh penghapusan baris tabel `sessions`).
        if (! $user->is_active) {
            if (Auth::id() === (int) $user->id) {
                $user->markOffline();
                Auth::logout();
            }

            return [
                'kicked' => true,
                'message' => 'Akun Anda sedang nonaktif / di-suspend. Sesi telah dihentikan. Silakan hubungi Petugas TU / Admin.',
                'security_alert_at' => $user->last_security_alert_at?->toDateTimeString(),
            ];
        }

        $boundSessionId = $user->current_session_id;
        $kicked = ! empty($boundSessionId) && $deviceLock !== $boundSessionId;

        if ($kicked) {
            // Sesi ini bukan lagi owner → keluarkan user dari sesi server.
            if (Auth::id() === (int) $user->id) {
                $user->markOffline();
                Auth::logout();
            }

            return [
                'kicked' => true,
                'message' => 'Sesi Anda telah dihentikan otomatis karena Anda sedang AFK dan akun digunakan di perangkat lain.',
                'security_alert_at' => $user->last_security_alert_at?->toDateTimeString(),
            ];
        }

        $user->forceFill([
            'last_active_at' => Carbon::now(),
            'is_idle' => $isIdle,
        ])->save();

        return [
            'kicked' => false,
            'message' => 'ok',
            'security_alert_at' => $user->last_security_alert_at?->toDateTimeString(),
        ];
    }

    /**
     * Lepas ikatan sesi saat logout eksplisit. Hanya jika perangkat yang logout
     * memang masih menjadi owner (agar tidak membekapkan owner baru).
     */
    public function clearBindingIfOwned(?User $user, string $deviceLock): void
    {
        if ($user !== null && ! empty($user->current_session_id)) {
            if ((string) $user->current_session_id === $deviceLock) {
                $user->forceFill([
                    'current_session_id' => null,
                    'is_idle' => false,
                ])->save();
            }
        }
    }
}