<?php

namespace App\Http\Controllers;

use App\Events\LoginApprovalDecision;
use App\Services\LoginApprovalService;
use App\Services\SingleDeviceSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Interactive Real-Time Login Approval — endpoint keputusan perangkat aktif (Device A).
 *
 *  - GET  /api/login-approval/status/{requestId}   (publik)  polling Device B.
 *  - GET  /api/user/login-approvals/pending        (auth)    polling Device A
 *                                                            (fallback modal tanpa websocket).
 *  - POST /api/login-approval/approve              (auth)    [Izinkan Login].
 *  - POST /api/login-approval/reject               (auth)    [Tolak].
 *
 * Penyelesaian login Device B setelah disetujui ditangani di
 * AuthController::completeApprovalLogin (analog login biasa + role redirect).
 */
class LoginApprovalController extends Controller
{
    public function __construct(private LoginApprovalService $approvalService)
    {
    }

    /**
     * Status permintaan persetujuan (polling Device B).
     * Publik — request_id acak (UUID) menjadi rahasia akses.
     */
    public function status(Request $request, string $requestId)
    {
        return response()->json($this->approvalService->status($requestId));
    }

    /**
     * Daftar permintaan persetujuan yang masih pending milik user (polling Device A).
     */
    public function pending(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'requests' => $this->approvalService->pendingForUser($user),
        ]);
    }

    /**
     * [Izinkan Login] — Device A menyetujui masuknya Device B.
     *
     * Setelah disetujui, sesi Device A langsung di-invalidate (logout), status
     * request berubah approved + diberikan one-time login_token untuk Device B.
     */
    public function approve(Request $request)
    {
        $data = $request->validate([
            'request_id' => 'required|string',
        ]);

        $user = $request->user();
        $requestId = $data['request_id'];

        $result = $this->approvalService->approve($user, $requestId);

        if ($result['status'] === 'approved') {
            // Kunci: setelah Device A menyetujui, sesinya langsung di-invalidate.
            event(new LoginApprovalDecision($user, $requestId, 'approved'));

            Log::info('single-device-session:login-approval-approved', [
                'user_id' => $user->id,
                'request_id' => $requestId,
            ]);

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json($result);
    }

    /**
     * [Tolak] — Device A menolak masuknya Device B (atau Device B timeout).
     * Aksi "Tolak & Amankan Akun" mengirim `message` khusus (mis. "Akses
     * ditolak oleh pemilik akun") yang akan dilihat Device B via polling status,
     * plus `amankan_akun` → menonaktifkan AFK auto-logout untuk sesi berjalan
     * ini (flag disable_afk_protection) hingga user Logout & Login kembali.
     */
    public function reject(Request $request)
    {
        $data = $request->validate([
            'request_id' => 'required|string',
            'message' => 'nullable|string|max:255',
            'amankan_akun' => 'nullable|boolean',
        ]);

        $user = $request->user();
        $requestId = $data['request_id'];

        $result = $this->approvalService->reject($user, $requestId, $data['message'] ?? null);

        event(new LoginApprovalDecision($user, $requestId, 'rejected'));

        if ($request->boolean('amankan_akun')) {
            // Sesi ini tidak lagi dilindungi AFK auto-logout (sesi lain tidak
            // boleh take-over meski user sedang tidak menyentuh perangkat).
            $request->session()->put(SingleDeviceSessionService::AFK_DISABLED_KEY, true);

            Log::info('single-device-session:afk-protection-disabled', [
                'user_id' => $user->id,
                'request_id' => $requestId,
                'reason' => 'Aksi "Tolak & Amankan Akun" dipilih — AFK auto-logout dinonaktifkan untuk sesi berjalan.',
            ]);
        }

        Log::info('single-device-session:login-approval-rejected', [
            'user_id' => $user->id,
            'request_id' => $requestId,
            'message' => $result['reject_message'] ?? null,
        ]);

        return response()->json($result);
    }

    /**
     * Quick-Reset Password ("Tolak & Amankan Akun").
     *
     * Dipanggil dari modal keamanan Device A tanpa perlu navigasi ke menu
     * profil: verifikasi password lama → perbarui password → invalidate SEMUA
     * sesi aktif lain (prune tabel sessions + rotasi lock token) → bersihkan
     * request approval yang masih menggantung. Perangkat ini tetap ter-login.
     */
    public function securePassword(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
            'password_confirmation' => 'required|string',
        ], [
            'current_password.required' => 'Password saat ini wajib diisi.',
            'password.confirmed' => 'Konfirmasi password baru tidak cocok.',
            'password.min' => 'Password baru minimal 8 karakter.',
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            return response()->json(['message' => 'Password saat ini tidak sesuai.'], 422);
        }

        // 1) Perbarui password.
        $user->password = Hash::make($data['password']);
        $user->save();

        // 2) Invalidate semua sesi aktif lain: hapus row sessions milik user
        //    selain sesi berjalan ini (perangkat lain otomatis di-logout pada
        //    request berikutnya).
        DB::table('sessions')->where('user_id', $user->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        // 3) Bersihkan request approval yang masih menggantung di akun ini.
        $this->approvalService->clearPendingForUser($user);

        // 4) Rotasi lock token: perangkat ini tetap owner, sementara perangkat
        //    lain yang memegang lock lama langsung di-kick oleh middleware.
        $deviceLock = (string) Str::uuid();
        $request->session()->put(SingleDeviceSessionService::LOCK_KEY, $deviceLock);
        app(SingleDeviceSessionService::class)->bindSession($user, $deviceLock);

        // 5) Nonaktifkan AFK auto-logout untuk SISA sesi ini (hingga user
        //    Logout & Login kembali). Password memang sudah diganti, namun user
        //    yang sedang "mode aman" tidak boleh terlempar gara-gara idle.
        $request->session()->put(SingleDeviceSessionService::AFK_DISABLED_KEY, true);

        Log::warning('single-device-session:password-reset-security', [
            'user_id' => $user->id,
            'reason' => 'Aksi "Tolak & Amankan Akun" — password diganti, seluruh sesi lain di-invalidate, dan AFK auto-logout dinonaktifkan.',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diperbarui. Akun Anda kini aman.',
        ]);
    }
}