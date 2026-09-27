<?php

namespace App\Services;

use App\Models\SecurityLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Audit jejak digital login (immutable) + manajemen perangkat.
 *
 *  - recordLogin()   : SATU-SATUNYA jalur penulisan ke tabel security_logs
 *                      (append-only). Tidak ada jalur update/delete.
 *  - devicesFor()    : daftar seluruh perangkat pemilik akun (pernah/sedang
 *                      login) lengkap dengan penanda sesi aktif/current.
 *  - revokeDevice()  : menghentikan sesi perangkat asing — TANPA mengubah
 *                      baris security_logs (hanya tabel sessions + rebind
 *                      current_session_id).
 *  - emergencySuspend(): nonaktifkan paksa akun rekan (is_active=false),
 *                      mengeluarkan SELURUH sesi aktifnya, dan mencatat aksi
 *                      ke security_logs (append-only).
 */
class SecurityAuditService
{
    /**
     * Nama perangkat ramah-manusia dari User-Agent (browser + OS).
     */
    public static function deviceNameFromUserAgent(string $userAgent): string
    {
        $os = 'OS Tidak Dikenal';
        $osMap = [
            'Windows NT 10.0' => 'Windows 10/11',
            'Windows NT 6.3' => 'Windows 8.1',
            'Windows NT 6.2' => 'Windows 8',
            'Windows NT 6.1' => 'Windows 7',
            'Android' => 'Android',
            'iPhone' => 'iOS (iPhone)',
            'iPad' => 'iOS (iPad)',
            'Mac OS X' => 'macOS',
            'CrOS' => 'ChromeOS',
            'Linux' => 'Linux',
        ];

        foreach ($osMap as $needle => $label) {
            if (stripos($userAgent, $needle) !== false) {
                $os = $label;
                break;
            }
        }

        $browser = 'Browser Tidak Dikenal';
        $browserMap = [
            'Edg/' => 'Edge',
            'OPR/' => 'Opera',
            'Chrome/' => 'Chrome',
            'Firefox/' => 'Firefox',
            'Safari/' => 'Safari',
        ];

        foreach ($browserMap as $needle => $label) {
            if (stripos($userAgent, $needle) !== false) {
                $browser = $label;
                break;
            }
        }

        return "{$browser} • {$os}";
    }

    /**
     * Catat satu login berhasil (ditambah SATU baris — immutable).
     *
     * Selain data lama (IP + UA + device_name), baris menyimpan:
     *  - device_fingerprint : sidik jari perangkat (hash stabil) — dipakai
     *    membedakan perangkat dengan nama generik yang sama.
     *  - device_meta        : komponen raw (model, OS, screen, timezone, dll).
     *  - is_unknown_device  : diset SEKALI di titik insert — TRUE bila
     *    fingerprint ini BELUM PERNAH tercatat untuk user (perangkat baru /
     *    tak dikenal). Tidak pernah diubah sesudah insert.
     */
    public function recordLogin(User $user, array $device, string $sessionLock, string $sessionId): SecurityLog
    {
        $fingerprint = isset($device['device_fingerprint'])
            ? trim((string) $device['device_fingerprint'])
            : '';

        $isUnknownDevice = $fingerprint !== ''
            && ! SecurityLog::query()
                ->where('user_id', $user->id)
                ->where('device_fingerprint', $fingerprint)
                ->exists();

        return SecurityLog::create([
            'user_id' => $user->id,
            'ip_address' => $device['ip'] ?? null,
            'user_agent' => $device['user_agent'] ?? null,
            'device_name' => $device['device_name'] ?? null,
            'device_fingerprint' => $fingerprint !== '' ? $fingerprint : null,
            'device_meta' => $device['device_meta'] ?? null,
            'is_unknown_device' => $isUnknownDevice,
            'login_at' => Carbon::now(),
            'is_current_session' => true,
            'session_lock' => $sessionLock,
            'session_id' => $sessionId,
        ]);
    }

    /**
     * Seluruh jejak perangkat milik user (terbaru di atas), dengan atribut
     * dinamis per-baris:
     *  - is_current : log yang sedang DIPAKAI browser ini (lock/session id cocok).
     *  - is_active  : perangkat yang MASIH AKTIF — masih ada baris sesi HTTP-nya
     *                 di tabel `sessions`, atau masih memegang ikatan
     *                 single-device (current_session_id) → layak diberi tombol
     *                 [Putuskan Sesi Ini].
     */
    public function devicesFor(User $user, string $currentLock, string $currentSessionId): Collection
    {
        $boundLock = (string) $user->current_session_id;

        $logs = SecurityLog::query()
            ->where('user_id', $user->id)
            ->latest('login_at')
            ->get();

        // Kumpulkan ID sesi yang MASIH HIDUP di tabel `sessions` (driver database).
        $liveSessionIds = DB::table('sessions')
            ->whereIn('id', $logs->pluck('session_id')->filter()->all())
            ->pluck('id')
            ->flip();

        return $logs
            ->map(function (SecurityLog $log) use ($currentLock, $currentSessionId, $boundLock, $liveSessionIds) {
                $lock = (string) $log->session_lock;
                $sid = (string) $log->session_id;

                $log->is_current = ($lock !== '' && $lock === $currentLock)
                    || ($sid !== '' && $sid === $currentSessionId);

                $log->is_active = ($sid !== '' && isset($liveSessionIds[$sid]))
                    || ($lock !== '' && $lock === $boundLock);

                return $log;
            })
            ->values();
    }

    /**
     * Putuskan sesi perangkat lain (remote session invalidation).
     *
     * Kebijakan immutability: baris `security_logs` TIDAK di-update maupun
     * di-hapus. Yang dihentikan adalah:
     *  1. Baris sesi HTTP target di tabel `sessions` (di-hapus) — perangkat
     *     langsung ter-logout pada request berikutnya (driver database).
     *  2. Bila target masih menjadi perangkat aktif (memegang
     *     current_session_id), ikatan dipindahkan ke perangkat saat ini →
     *     middleware SingleDeviceSession menendangnya.
     *
     * @return array{ok: bool, message: string}
     */
    public function revokeDevice(User $user, SecurityLog $log, string $currentLock, string $currentSessionId): array
    {
        $targetLock = (string) $log->session_lock;
        $targetSessionId = (string) $log->session_id;

        // Tidak boleh memutuskan sesi yang sedang dipakai browser ini.
        if (($targetSessionId !== '' && $targetSessionId === $currentSessionId)
            || ($targetLock !== '' && $targetLock === $currentLock)) {
            return [
                'ok' => false,
                'message' => 'Sesi yang sedang Anda pakai tidak dapat diputus dari sini.',
            ];
        }

        // 1) Invalidate sesi HTTP target langsung dari database.
        if ($targetSessionId !== '') {
            DB::table('sessions')->where('id', $targetSessionId)->delete();
        }

        // 2) Bila target masih perangkat aktif → pindahkan ikatan ke perangkat
        //    saat ini (perangkat target otomatis di-kick oleh middleware).
        if ($targetLock !== '' && $targetLock === (string) $user->current_session_id) {
            $rebindLock = $currentLock !== ''
                ? $currentLock
                : $this->generateAndAttachNewLock();

            app(SingleDeviceSessionService::class)->bindSession($user, $rebindLock);
        }

        Log::info('security-devices:session-revoked', [
            'user_id' => $user->id,
            'security_log_id' => $log->id,
            'target_session_id' => $targetSessionId,
            'target_session_lock' => $targetLock,
        ]);

        return [
            'ok' => true,
            'message' => 'Sesi perangkat tersebut telah diputus. Untuk keamanan, silakan ganti password Anda.',
        ];
    }

    /**
     * Suspend darurat akun rekan kerja (Peer Emergency Suspend).
     *
     * Dijalankan saat terdeteksi indikasi peretasan / kebocoran kredensial:
     *  1. Nonaktifkan akun target (is_active = false) — login langsung ditolak
     *     dengan pesan generik (lihat AuthController::login).
     *  2. Invalidate & hapus SELURUH sesi aktif milik target dari tabel
     *     `sessions` + lepaskan ikatan single-device (current_session_id,
     *     is_idle, last_active_at) agar tidak menyisakan "owner" yang basi.
     *  3. Catat aktivitas ke security_logs (append-only, immutable):
     *     "Akun {target} dinonaktifkan darurat oleh {aktor}" + IP/UA pelaku.
     *
     * Catatan immutability: SEMUA operasi di sini hanya menulis BARIS BARU ke
     * security_logs. Tidak ada satupun UPDATE/DELETE untuk tabel itu.
     *
     * @return SecurityLog baris audit yang baru dibuat (milik target).
     */
    public function emergencySuspend(User $target, User $actor, array $device): SecurityLog
    {
        // 1) Nonaktifkan akun target.
        $target->forceFill(['is_active' => false])->save();

        // 2) Hapus seluruh sesi HTTP aktif target + lepas ikatan single-device.
        DB::table('sessions')->where('user_id', $target->id)->delete();
        $target->forceFill([
            'current_session_id' => null,
            'is_idle' => false,
            'last_active_at' => null,
        ])->save();

        // 3) Append-only: jejak audit di security_logs (milik TARGET).
        return SecurityLog::create([
            'user_id' => $target->id,
            'ip_address' => $device['ip'] ?? null,
            'user_agent' => $device['user_agent'] ?? null,
            'device_name' => 'Emergency Suspend',
            'description' => "Akun {$target->nama} dinonaktifkan darurat oleh {$actor->nama}.",
            'login_at' => Carbon::now(),
            'is_current_session' => false,
        ]);
    }

    /**
     * Emergency Super Admin Takeover ("Kartu As") — promosi DARURAT akun
     * Petugas IT / QA Tester menjadi Super Admin permanen.
     *
     * Menulis SATU baris audit ke security_logs (append-only, immutable)
     * milik akun yang dipromosikan, mencakup IP/UA pelaku, timestamp, dan
     * alasan/action untuk kepentingan audit keamanan. Tidak ada satupun
     * operasi UPDATE/DELETE untuk tabel security_logs.
     *
     * @param  User[]  $disabledTargets  akun Super Admin lama yang turut
     *                                   dinonaktifkan bila ada indikasi bobol.
     */
    public function emergencyPromote(User $user, string $reason, array $device, array $disabledTargets = []): SecurityLog
    {
        $description = "Akun {$user->nama} dipromosikan DARURAT menjadi Super Admin oleh dirinya sendiri (Petugas IT). Alasan: {$reason}.";

        if (count($disabledTargets) > 0) {
            $names = collect($disabledTargets)
                ->map(fn (User $target) => $target->nama)
                ->join(', ');

            $description .= " Akun Super Admin lama yang telah dinonaktifkan (indikasi bobol): {$names}.";
        }

        return SecurityLog::create([
            'user_id' => $user->id,
            'ip_address' => $device['ip'] ?? null,
            'user_agent' => $device['user_agent'] ?? null,
            'device_name' => 'Emergency Takeover',
            'description' => $description,
            'login_at' => Carbon::now(),
            'is_current_session' => false,
        ]);
    }

    /**
     * Demote/Demote-self pasca Emergency Super Admin Takeover — mengembalikan
     * akun ke Mode IT / QA biasa.
     *
     * Menulis SATU baris audit append-only (immutable) ke security_logs milik
     * akun yang dikembalikan, mencakup IP/UA pelaku dan timestamp. Tidak ada
     * satupun operasi UPDATE/DELETE untuk tabel security_logs.
     */
    public function emergencyDemote(User $user, array $device): SecurityLog
    {
        $roleLabel = $user->sub_role === User::ROLE_QA_TESTER ? 'QA Tester' : 'Petugas IT';

        return SecurityLog::create([
            'user_id' => $user->id,
            'ip_address' => $device['ip'] ?? null,
            'user_agent' => $device['user_agent'] ?? null,
            'device_name' => 'Emergency Demote',
            'description' => "Akun {$user->nama} dikembalikan ke Mode IT / QA ({$roleLabel}) oleh dirinya sendiri — status Super Admin darurat (Emergency Takeover) dilepaskan.",
            'login_at' => Carbon::now(),
            'is_current_session' => false,
        ]);
    }

    private function generateAndAttachNewLock(): string
    {
        $lock = (string) Str::uuid();
        session()->put(SingleDeviceSessionService::LOCK_KEY, $lock);

        return $lock;
    }
}