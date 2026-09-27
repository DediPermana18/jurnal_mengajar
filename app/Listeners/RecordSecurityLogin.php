<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\DeviceSignatureService;
use App\Services\SecurityAuditService;
use App\Services\SecurityBotService;
use App\Services\SingleDeviceSessionService;
use Illuminate\Auth\Events\Login;

/**
 * Auth Event Listener — dicetuskan pada SETIAP proses login BERHASIL
 * (event Illuminate\Auth\Events\Login).
 *
 * Tugas:
 *  1. Menyimpan record baru ke tabel `security_logs` (append-only / immutable)
 *     berisi IP + User-Agent detail + device_name + sidik jari perangkat
 *     (Client Hints + data frontend) + session lock & id.
 *  2. Mengirim notifikasi Bot Chat (WhatsApp/Telegram) ke pemilik akun bahwa
 *     ada perangkat baru yang berhasil login.
 *
 * Catatan waktu eksekusi: pada alur login AuthController, lock token &
 * regenerasi sesi dilakukan SEBELUM Auth::login() — sehingga di titik ini
 * session()->get(LOCK_KEY) dan session()->getId() sudah bernilai FINAL.
 */
class RecordSecurityLogin
{
    /**
     * Key sesi penanda bahwa sesi dengan id tertentu sudah dicatat — mencegah
     * pencatatan ganda bila listener terdaftar lebih dari sekali.
     */
    private const RECORDED_MARKER = 'security_log.recorded_session_id';

    public function __construct(
        private SecurityAuditService $auditService,
        private SecurityBotService $botService,
        private DeviceSignatureService $deviceSignature,
    ) {
    }

    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        // ----- Pelindung duplikasi (idempotent per login) -----
        // Di lingkungan CLI/test, listener acap kali terdaftar lebih dari satu
        // kali pada dispatcher yang sama (provider di-boot berulang). Setiap
        // login BERHASIL selalu melakukan regenerasi session id SEBELUM
        // Auth::login() — karena itu penanda "session id terakhir yang sudah
        // dicatat" di sesi ini cukup untuk menjamin tiap login dicatat persis
        // SATU kali, tanpa menekan catatan pada re-login (id sesi berubah).
        $sessionId = (string) session()->getId();
        if ($sessionId !== '' && session()->get(self::RECORDED_MARKER) === $sessionId) {
            return;
        }
        session()->put(self::RECORDED_MARKER, $sessionId);

        $userAgent = (string) request()->userAgent();

        $signature = $this->deviceSignature->fromRequest(
            request(),
            DeviceSignatureService::metaFromRequest(request()),
        );

        $log = $this->auditService->recordLogin(
            $user,
            [
                'ip' => request()->ip(),
                'user_agent' => $userAgent,
                'device_name' => $signature['device_name'],
                'device_fingerprint' => $signature['device_fingerprint'],
                'device_meta' => $signature['device_meta'],
            ],
            (string) session()->get(SingleDeviceSessionService::LOCK_KEY, ''),
            (string) session()->getId(),
        );

        $this->botService->notifyNewDeviceLogin($user, $log);
    }
}