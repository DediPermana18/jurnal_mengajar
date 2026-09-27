<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Interactive Real-Time Login Approval (Push Prompt).
 *
 * Saat Device B mencoba login ke akun yang sedang AKTIF di Device A, login
 * Device B TIDAK dituntaskan. Sebagai gantinya dibuat status sementara
 * "login_request_pending" yang disimpan di Cache dengan TTL 60 detik:
 *
 *  - request_id  : identitas unik permintaan (dua perangkat memakainya).
 *  - status      : pending -> approved | rejected (atau expired saat TTL habis).
 *  - login_token : one-time token yang baru dibuat saat Device A menyetujui,
 *                  dipakai Device B untuk menuntaskan login-nya.
 *  - device_info : IP + User-Agent Device B (ditampilkan di modal Device A).
 *
 * Device B menunggu keputusan lewat polling status; Device A memutuskan lewat
 * endpoint approve/reject (event real-time + polling fallback).
 */
class LoginApprovalService
{
    /** Masa berlaku permintaan persetujuan (detik). */
    public const TTL_SECONDS = 60;

    private const CACHE_PREFIX = 'login_approval:request:';

    private const CACHE_INDEX_PREFIX = 'login_approval:index:';

    public function __construct(
        private DeviceSignatureService $deviceSignature,
    ) {
    }

    /**
     * Buat permintaan persetujuan login baru (status: pending).
     */
    public function create(User $user, Request $request): array
    {
        $requestId = (string) Str::uuid();

        $signature = $this->deviceSignature->fromRequest(
            $request,
            DeviceSignatureService::metaFromRequest($request),
        );

        $payload = [
            'request_id' => $requestId,
            'user_id' => $user->id,
            'device_info' => [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'device_name' => $signature['device_name'],
                'device_fingerprint' => $signature['device_fingerprint'],
                'device_meta' => $signature['device_meta'],
            ],
            'status' => 'pending',
            'login_token' => null,
            'created_at' => now()->toDateTimeString(),
        ];

        Cache::put($this->key($requestId), $payload, self::TTL_SECONDS);

        // Indeks per-user agar Device A bisa mem-poll permintaan pending-nya.
        $index = Cache::get($this->indexKey($user->id), []);
        $index[$requestId] = true;
        Cache::put($this->indexKey($user->id), $index, self::TTL_SECONDS);

        return $payload;
    }

    /**
     * Ambil payload mentah permintaan (atau null bila sudah kadaluarsa).
     */
    public function get(string $requestId): ?array
    {
        $payload = Cache::get($this->key($requestId));

        return is_array($payload) ? $payload : null;
    }

    /**
     * Status publik untuk Device B (polling): pending | approved | rejected | expired.
     * Hanya mengembalikan login_token saat status sudah approved.
     */
    public function status(string $requestId): array
    {
        $payload = $this->get($requestId);

        if ($payload === null) {
            return [
                'request_id' => $requestId,
                'status' => 'expired',
            ];
        }

        $result = [
            'request_id' => $requestId,
            'status' => $payload['status'],
            'device_info' => $payload['device_info'],
        ];

        if ($payload['status'] === 'approved' && $payload['login_token'] !== null) {
            $result['login_token'] = $payload['login_token'];
        }

        if ($payload['status'] === 'rejected' && ! empty($payload['reject_message'])) {
            $result['reject_message'] = $payload['reject_message'];
        }

        return $result;
    }

    /**
     * Daftar permintaan yang masih PENDING milik user (untuk Device A).
     */
    public function pendingForUser(User $user): array
    {
        $index = Cache::get($this->indexKey($user->id), []);
        $pending = [];
        $alive = [];

        foreach (array_keys($index) as $requestId) {
            $payload = $this->get($requestId);

            if ($payload === null) {
                continue; // sudah expired / dikonsumsi — bersihkan dari indeks.
            }

            $alive[$requestId] = true;

            if ($payload['status'] === 'pending') {
                $pending[] = $payload;
            }
        }

        Cache::put($this->indexKey($user->id), $alive, self::TTL_SECONDS);

        return $pending;
    }

    /**
     * Device A menyetujui login Device B → status approved + one-time login_token.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 404 bila
     *         permintaan bukan milik user yang berhak menyetujui.
     */
    public function approve(User $user, string $requestId): array
    {
        $payload = $this->get($requestId);

        if ($payload === null || (int) $payload['user_id'] !== (int) $user->id) {
            abort(404, 'Permintaan login tidak ditemukan.');
        }

        if ($payload['status'] === 'pending') {
            $payload['status'] = 'approved';
            $payload['login_token'] = (string) Str::random(64);
            Cache::put($this->key($requestId), $payload, self::TTL_SECONDS);
            $this->forgetFromIndex($user, $requestId);
        }

        return [
            'request_id' => $requestId,
            'status' => $payload['status'],
        ];
    }

    /**
     * Device A menolak login Device B → status rejected.
     * `$message` (opsional) disimpan sebagai alasan yang akan dilihat Device B
     * (mis. "Akses ditolak oleh pemilik akun" pada aksi Tolak & Amankan Akun).
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 404 bila
     *         permintaan bukan milik user yang berhak menolak.
     */
    public function reject(User $user, string $requestId, ?string $message = null): array
    {
        $payload = $this->get($requestId);

        if ($payload === null || (int) $payload['user_id'] !== (int) $user->id) {
            abort(404, 'Permintaan login tidak ditemukan.');
        }

        if ($payload['status'] === 'pending') {
            $payload['status'] = 'rejected';
            $payload['reject_message'] = ($message !== null && trim($message) !== '')
                ? trim($message)
                : 'Permintaan login ditolak oleh perangkat aktif.';
            Cache::put($this->key($requestId), $payload, self::TTL_SECONDS);
        }

        return [
            'request_id' => $requestId,
            'status' => $payload['status'],
            'reject_message' => $payload['reject_message'] ?? 'Permintaan login ditolak oleh perangkat aktif.',
        ];
    }

    /**
     * Hapus seluruh permintaan persetujuan yang masih menggantung milik user
     * (dipanggil saat akun diamankan — request lama langsung tidak berlaku).
     */
    public function clearPendingForUser(User $user): void
    {
        Cache::forget($this->indexKey($user->id));
    }

    /**
     * Device B menuntaskan login setelah disetujui (one-time use via token).
     * Request dikonsumsi & dihapus → pemakaian kedua otomatis gagal.
     */
    public function consumeApproved(string $requestId, string $token): ?User
    {
        $payload = $this->get($requestId);

        if ($payload === null || $payload['status'] !== 'approved') {
            return null;
        }

        if (! hash_equals((string) $payload['login_token'], $token)) {
            return null;
        }

        $user = User::find($payload['user_id']);

        if (! $user) {
            return null;
        }

        Cache::forget($this->key($requestId));
        $this->forgetFromIndex($user, $requestId);

        return $user;
    }

    private function key(string $requestId): string
    {
        return self::CACHE_PREFIX.$requestId;
    }

    private function indexKey(int $userId): string
    {
        return self::CACHE_INDEX_PREFIX.$userId;
    }

    private function forgetFromIndex(User $user, string $requestId): void
    {
        $index = Cache::get($this->indexKey($user->id), []);

        if (isset($index[$requestId])) {
            unset($index[$requestId]);
            Cache::put($this->indexKey($user->id), $index, self::TTL_SECONDS);
        }
    }
}