<?php

namespace App\Http\Controllers;

use App\Services\SingleDeviceSessionService;
use Illuminate\Http\Request;

/**
 * Endpoint heartbeat AFK/idle tracker (client-side).
 *
 * Penerima ping `{ "is_idle": true|false }` sekaligus menjadi jalur polling
 * notifikasi keamanan (last_security_alert_at) dan deteksi sesi "kicked".
 */
class SingleDeviceSessionController extends Controller
{
    public function __construct(private SingleDeviceSessionService $sessionService)
    {
    }

    public function heartbeat(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Saat AFK auto-logout dinonaktifkan (aksi "Tolak & Amankan Akun"),
        // is_idle=true dari client diabaikan — sesi selalu dianggap aktif
        // sehingga tidak bisa di-take-over oleh perangkat lain karena AFK.
        $afkDisabled = (bool) $request->session()->get(SingleDeviceSessionService::AFK_DISABLED_KEY, false);
        $isIdle = $request->boolean('is_idle', false) && ! $afkDisabled;
        $deviceLock = (string) $request->input(
            'session_id',
            $request->session()->get(SingleDeviceSessionService::LOCK_KEY, '')
        );

        $payload = $this->sessionService->heartbeat($user, $isIdle, $deviceLock);
        $payload['afk_protection_disabled'] = $afkDisabled;

        return response()->json($payload);
    }
}