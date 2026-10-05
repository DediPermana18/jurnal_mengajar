<?php

namespace App\Http\Controllers;

use App\Models\PengaturanJadwal;
use App\Models\User;
use App\Events\LoginApprovalRequested;
use App\Services\LoginApprovalService;
use App\Services\SingleDeviceSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Pesan error generik untuk SEMUA kegagalan autentikasi.
     *
     * Disamarkan agar calon penyerang tidak dapat membedakan apakah
     * username/NIP terdaftar, password salah, kode aktivasi salah, atau akun
     * sedang non-aktif (anti user-enumeration & credential oracle).
     */
    public const GENERIC_LOGIN_FAILED_MESSAGE = 'Kredensial yang Anda masukkan salah.';

    /**
     * Tampilkan Halaman Login
     *
     * Selama Maintenance Mode aktif, halaman login tetap accessible untuk siapa
     * saja. User yang TIDAK berhak bypass (User::canBypassMaintenance) dan masih
     * terautentikasi dikeluarkan (logout) agar tidak terlempar dalam siklus 503
     * saat mencoba kembali ke login.
     */
    public function showLoginForm()
    {
        $maintenanceActive = PengaturanJadwal::isMaintenanceModeActive();

        if (Auth::check()) {
            $user = Auth::user();

            if ($maintenanceActive && ! $user->canBypassMaintenance()) {
                $user->markOffline();
                Auth::logout();
                request()->session()->invalidate();
                request()->session()->regenerateToken();

                return view('auth.login', compact('maintenanceActive'));
            }

            return $this->redirectBasedOnRole($user);
        }

        return view('auth.login', compact('maintenanceActive'));
    }

    /**
     * Proses Autentikasi Login
     */
    public function login(Request $request)
    {
        // Rule Validasi Input Dasar. Validasi manual agar nilai field TIDAK
        // di-flash ulang ke sesi saat login gagal. Satu-satunya input yang
        // ikut di-flash adalah state tab 'mode' (guru/admin) supaya tab yang
        // dipilih tetap persist setelah redirect back — kredensial sensitif
        // (username, password, kode_aktivasi) tetap kosong.
        $validator = Validator::make($request->all(), [
            'login_id' => 'required|string',
            'password' => 'required|string',
        ], [
            'login_id.required' => 'Username atau NIP wajib diisi.',
            'password.required' => 'Password wajib diisi.',
        ]);

        if ($validator->fails()) {
            return back()
                ->withInput($request->only('mode'))
                ->withErrors($validator);
        }

        $loginId = trim($request->input('login_id'));
        $password = $request->input('password');

        // Cari user berdasarkan Username, NIP, atau Email (termasuk yang dinonaktifkan)
        $user = User::withTrashed()
            ->where(function ($q) use ($loginId) {
                $q->where('username', $loginId)
                    ->orWhere('nip', $loginId)
                    ->orWhere('email', $loginId);
            })
            ->first();

        // Semua kegagalan autentikasi memakai SATU pesan generik yang sama agar
        // tidak membocorkan: apakah akun terdaftar, status aktif, keberlakuan
        // kode aktivasi, maupun kebenaran password. Hanya state tab 'mode' yang
        // di-flash — input sensitif tidak ikut tersimpan di sesi.
        if (! $user) {
            return back()
                ->withInput($request->only('mode'))
                ->withErrors(['login_id' => self::GENERIC_LOGIN_FAILED_MESSAGE]);
        }

        // Suspend sementara berbasis waktu: tolak login selama suspended_until
        // masih di masa depan (akun otomatis normal lagi setelah lewat waktu).
        if ($user->isCurrentlySuspended()) {
            return back()
                ->withInput($request->only('mode'))
                ->withErrors([
                    'login_id' => 'Akun Anda sedang disuspend sementara sampai '.$user->suspended_until->format('d M Y H:i').'.',
                ]);
        }

        // Cek jika akun sedang non-aktif (dinonaktifkan admin)
        if (! $user->is_active) {
            return back()
                ->withInput($request->only('mode'))
                ->withErrors(['login_id' => self::GENERIC_LOGIN_FAILED_MESSAGE]);
        }

        // Cek jika akun guru/admin sudah di-soft delete
        if ($user->trashed()) {
            return back()
                ->withInput($request->only('mode'))
                ->withErrors(['login_id' => self::GENERIC_LOGIN_FAILED_MESSAGE]);
        }

        // ================= MAINTENANCE MODE =================
        // Saat Maintenance aktif, hanya akun yang berhak bypass
        // (User::canBypassMaintenance: Petugas IT / QA / Super Admin / role
        // admin / username petugas.it) yang boleh login. User lain ditolak
        // dengan pesan generik — tidak membocorkan validitas password maupun
        // keberadaan akun.
        if (PengaturanJadwal::isMaintenanceModeActive() && ! $user->canBypassMaintenance()) {
            return back()
                ->withInput($request->only('mode'))
                ->withErrors(['login_id' => self::GENERIC_LOGIN_FAILED_MESSAGE]);
        }

        // ================= VALIDASI KODE AKTIVASI UNTUK AKUN NON-GURU =================
        // Jika user ber-role Non-Guru (Admin, Kepsek, Waka SDM, Waka Kurikulum, Piket, TU, Satpam, IT, dll):
        // Wajib cocokkan input kode_aktivasi LANGSUNG dengan nilai $user->kode_aktivasi yang ada di database.
        $isGuruRole = $user->role === 'guru' || $user->role === User::ROLE_GURU;

        if (! $isGuruRole) {
            $inputKode = strtolower(trim((string) $request->input('kode_aktivasi', '')));
            $dbKode = strtolower(trim((string) $user->kode_aktivasi));

            if ($inputKode === '' || $dbKode === '' || $inputKode !== $dbKode) {
                return back()
                    ->withInput($request->only('mode'))
                    ->withErrors([
                        'kode_aktivasi' => self::GENERIC_LOGIN_FAILED_MESSAGE,
                    ]);
            }
        }

        // ================= PROSES AUTENTIKASI PASSWORD =================
        // Verifikasi password LANGSUNG terhadap user yang sudah di-resolve di atas
        // (berdasarkan identifier unik: username/nip/email). Tidak menggunakan
        // Auth::attempt() ulang agar tidak terjadi 'crossover' role bila ada
        // username/nip/email yang kembar antar user.
        if (! Hash::check($password, $user->password)) {
            return back()
                ->withInput($request->only('mode'))
                ->withErrors(['password' => self::GENERIC_LOGIN_FAILED_MESSAGE]);
        }

        // ================= SINGLE DEVICE SESSION (SMART IDLE-AWARE + APPROVAL) =================
        // Logic GATE login perangkat baru — dievaluasi SEBELUM session/token baru
        // dibuat untuk Device B:
        //  - Perangkat pertama SANGAT AKTIF (is_idle==false && last_active_at
        //    < 3 menit)  → login TIDAK dituntaskan. Dibuat status pending (TTL
        //    60 detik) + event LoginApprovalRequested real-time ke Device A;
        //    Device A memutuskan [Izinkan] / [Tolak] lewat modal interaktif.
        //  - Perangkat pertama AFK / stale   → izinkan take-over (kick Device A).
        //  - Bukan perangkat lain (relogin)  → biarkan normal.
        $singleDeviceService = app(SingleDeviceSessionService::class);
        $requestDeviceLock = (string) $request->session()->get(SingleDeviceSessionService::LOCK_KEY, '');
        $conflict = $singleDeviceService->resolveLoginConflict($user, $requestDeviceLock);

        $hasBoundSession = ! empty($user->current_session_id);
        $decisionLabel = match (true) {
            $conflict === 'active' => 'pending-approval',
            $hasBoundSession && $conflict === 'none' => 'allowed (relogin perangkat sama)',
            $hasBoundSession => 'allowed (take-over, perangkat lama idle)',
            default => 'allowed',
        };

        // Audit trail login attempt: status idle/AFK + keputusan gate.
        Log::info('single-device-session:login-gate', [
            'user_id' => $user->id,
            'username' => $user->username,
            'is_idle' => (bool) $user->is_idle,
            'last_active_at' => optional($user->last_active_at)?->toDateTimeString(),
            'bound_session_id' => $user->current_session_id,
            'decision' => $decisionLabel,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        if ($conflict === 'active') {
            // ===== LOGIN DEVICE B MENJADI PENDING (push-prompt approval) =====
            // Jangan tuntaskan session/token Device B. Simpan status sementara
            // login_request_pending (request_id) di Cache dengan TTL 60 detik dan
            // kirim event LoginApprovalRequested real-time ke perangkat aktif.
            $approvalService = app(LoginApprovalService::class);
            $pendingRequest = $approvalService->create($user, $request);

            event(new LoginApprovalRequested(
                $user,
                $pendingRequest['request_id'],
                $pendingRequest['device_info'],
                'Ada perangkat lain mencoba login ke akun Anda.'
            ));

            Log::warning('single-device-session:login-pending-approval', [
                'user_id' => $user->id,
                'username' => $user->username,
                'request_id' => $pendingRequest['request_id'],
                'reason' => 'Perangkat pertama terdeteksi SANGAT AKTIF (is_idle=false, last_active_at dalam 3 menit) → butuh persetujuan.',
                'ip' => $request->ip(),
            ]);

            if ($request->expectsJson()) {
                // Client Device B akan masuk mode polling status approval.
                return response()->json([
                    'approval_required' => true,
                    'message' => 'Akun sedang aktif digunakan di perangkat lain. Menunggu persetujuan perangkat aktif.',
                    'request_id' => $pendingRequest['request_id'],
                    'status_url' => route('login-approval.status', ['requestId' => $pendingRequest['request_id']]),
                    'expires_in' => LoginApprovalService::TTL_SECONDS,
                ], 202);
            }

            // Request browser → halaman login menampilkan panel "menunggu
            // konfirmasi" dan mem-poll status approval sampai diputuskan.
            return back()
                ->withInput($request->only('mode'))
                ->with('login_approval', [
                    'request_id' => $pendingRequest['request_id'],
                    'message' => 'Akun sedang aktif digunakan di perangkat lain. Tunggu konfirmasi dari perangkat aktif.',
                ]);
        }

        // Keluarkan lock token perangkat baru SEBELUM Auth::login() agar
        // listener audit (RecordSecurityLogin, event Illuminate\Auth\Events\Login)
        // menangkap lock token & session id FINAL saat event dipancarkan.
        $deviceLock = (string) \Illuminate\Support\Str::uuid();
        $request->session()->put(SingleDeviceSessionService::LOCK_KEY, $deviceLock);

        // Regenerasi sesi SEBELUM login (anti session fixation) — id sesi yang
        // tercatat di tabel security_logs adalah id sesi final sesi autentikasi.
        $request->session()->regenerate();

        // Sesi baru → AFK protection kembali aktif. Flag disable_afk_protection
        // HANYA berlaku untuk sesi berjalan; jangan sampai terbawa ke login baru.
        $request->session()->forget(SingleDeviceSessionService::AFK_DISABLED_KEY);

        Auth::login($user);

        // Ikat sebagai sesi valid (default state aktif: is_idle=false,
        // last_active_at=now()) — lock token sudah tersimpan di sesi di atas.
        $singleDeviceService->bindSession($user, $deviceLock);
        Log::info('single-device-session:login-bound', [
            'user_id' => $user->id,
            'device_lock' => $deviceLock,
            'is_idle' => false,
            'last_active_at' => now()->toDateTimeString(),
        ]);

        return $this->redirectBasedOnRole(Auth::user());
    }

    /**
     * Finalisasi Login Device B Setelah Disetujui (Interactive Approval).
     *
     * Dipanggil Device B saat polling status menemukan status 'approved' dan
     * menerima one-time login_token dari Device A. Request approval dikonsumsi
     * (one-time use), Device B di-authenticate, lock token baru dibuat dan
     * diikat sebagai current_session_id — efek sampingnya sesi Device A yang
     * lama otomatis di-kick oleh middleware SingleDeviceSession.
     */
    public function completeApprovalLogin(Request $request)
    {
        $requestId = trim((string) $request->input('request_id', ''));
        $loginToken = trim((string) $request->input('login_token', ''));

        if ($requestId === '' || $loginToken === '') {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Permintaan login tidak lengkap.'], 422);
            }

            return back()->withErrors(['login_id' => 'Permintaan login tidak lengkap.']);
        }

        $approvalService = app(LoginApprovalService::class);
        $user = $approvalService->consumeApproved($requestId, $loginToken);

        // Rejected / expired / token salah → login Device B gagal.
        if (! $user) {
            Log::info('single-device-session:login-approval-denied', [
                'request_id' => $requestId,
                'reason' => 'Request ditolak / sudah kadaluarsa / token tidak valid.',
                'ip' => $request->ip(),
            ]);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Permintaan login ditolak oleh perangkat aktif.'], 422);
            }

            return back()->withErrors(['login_id' => 'Permintaan login ditolak oleh perangkat aktif.']);
        }

        // Login Device B dituntaskan (identik dengan login biasa). Lock token
        // dibuat & regenerasi sesi dilakukan SEBELUM Auth::login() agar listener
        // audit (RecordSecurityLogin) menangkap lock & session id FINAL.
        $deviceLock = (string) \Illuminate\Support\Str::uuid();
        $request->session()->put(SingleDeviceSessionService::LOCK_KEY, $deviceLock);
        $request->session()->regenerate();

        // Sesi baru → AFK protection kembali aktif (flag sesi lama tidak
        // diwariskan ke sesi hasil re-login).
        $request->session()->forget(SingleDeviceSessionService::AFK_DISABLED_KEY);

        Auth::login($user);

        $singleDevice = app(SingleDeviceSessionService::class);
        $singleDevice->bindSession($user, $deviceLock);

        Log::info('single-device-session:login-bound-via-approval', [
            'user_id' => $user->id,
            'request_id' => $requestId,
            'device_lock' => $deviceLock,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'redirect_url' => $this->redirectBasedOnRole($user)->getTargetUrl(),
            ]);
        }

        return $this->redirectBasedOnRole(Auth::user());
    }

    /**
     * Redirect User Berdasarkan Role
     *
     * Seluruh keputusan diambil dari User::dashboardRouteName() — sumber
     * kebenaran TUNGGAL yang juga dipakai DashboardController sebagai penjaga
     * akses '/'. Karena keduanya memakai fungsi yang sama, redirect setelah
     * login dan pembatasan akses dashboard admin TIDAK PERNAH berbeda pendapat.
     *
     * Pemetaan role → portal:
     *  - admin + sub_role 'satpam'          → satpam.dashboard
     *  - admin + sub_role 'waka_kurikulum'  → kurikulum.dashboard
     *  - admin + sub_role 'waka_sdm'        → waka-sdm.dashboard
     *  - admin + sub_role 'waka_kesiswaan'  → waka-kesiswaan.dashboard
     *  - admin + sub_role 'waka_piket'      → waka-piket.dashboard
     *  - admin + sub_role 'kepsek'          → kepsek.dashboard
     *  - admin + sub_role 'petugas_tu'/null → home (dashboard admin)
     *  - petugas_it / qa_tester             → it.dashboard
     *  - super_admin                        → home (dashboard admin)
     *  - guru + jadwal piket HARI INI       → piket.dashboard
     *  - guru / guru_mapel / wali_kelas     → guru.dashboard
     */
    protected function redirectBasedOnRole($user)
    {
        $routeName = $user->dashboardRouteName();

        // Fail-safe: role/sub_role tidak dikenal (atau helper mengembalikan
        // null). JANGAN fallback ke dashboard admin — area itu memuat statistik
        // sekolah. Arahkan ke profil & beri petunjuk, dan catat di log.
        if (! $routeName) {
            Log::warning('auth:role-tidak-dikenal-tidak-ada-dashboard', [
                'user_id' => $user->id,
                'username' => $user->username,
                'role' => $user->role,
                'sub_role' => $user->sub_role,
            ]);

            return redirect()->route('profil.index')
                ->with('warning', 'Peran akun Anda belum diatur. Hubungi administrator untuk akses dashboard.');
        }

        $message = 'Selamat datang kembali, '.$this->sapaanRole($user, $routeName).$user->nama.'!';

        return redirect()->route($routeName)->with('success', $message);
    }

    /**
     * Sapaan singkat sesuai peran, dipakai pada pesan "Selamat datang kembali".
     * Sengaja memakai routeName (hasil resolusi) supaya tidak ada daftar
     * role lagi yang harus dicocokkan terpisah dari pemetaan dashboard.
     */
    private function sapaanRole($user, string $routeName): string
    {
        return match ($routeName) {
            'satpam.dashboard' => 'Satpam ',
            'kurikulum.dashboard' => 'Waka Kurikulum ',
            'waka-sdm.dashboard' => 'Waka SDM ',
            'waka-kesiswaan.dashboard' => 'Waka Kesiswaan ',
            'waka-piket.dashboard' => 'Waka Piket ',
            'kepsek.dashboard' => 'Kepala Sekolah ',
            'it.dashboard' => 'Petugas IT ',
            'piket.dashboard' => 'Guru Piket ',
            // Guru biasa / wali kelas / guru mapel: tanpa awalan jabatan.
            'guru.dashboard', 'walikelas.dashboard' => '',
            // Admin TU / Super Admin / Admin Utama.
            default => 'Admin ',
        };
    }

    /**
     * Proses Logout User
     */
    public function logout(Request $request)
    {
        // Lepaskan ikatan sesi ini dari current_session_id (single device)
        // agar akun bisa dipakai di perangkat lain tanpa konflik sesi basi.
        $singleDevice = app(SingleDeviceSessionService::class);
        $singleDevice->clearBindingIfOwned(
            $request->user(),
            (string) $request->session()->get(SingleDeviceSessionService::LOCK_KEY, '')
        );

        $request->user()?->markOffline();
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Anda telah berhasil keluar.');
    }
}
