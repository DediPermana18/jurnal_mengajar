<?php

namespace App\Http\Controllers;

use App\Models\PengaturanJadwal;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
     * saja. User non-IT/QA yang masih terautentikasi dikeluarkan (logout) agar
     * tidak terlempar dalam siklus 503 saat mencoba kembali ke login.
     */
    public function showLoginForm()
    {
        $maintenanceActive = PengaturanJadwal::isMaintenanceModeActive();

        if (Auth::check()) {
            $user = Auth::user();

            if ($maintenanceActive && ! $user->isTestingUser()) {
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
        // Saat Maintenance aktif, hanya Petugas IT / QA Tester yang boleh login.
        // User biasa ditolak dengan pesan generik — tidak membocorkan validitas
        // password maupun keberadaan akun.
        if (PengaturanJadwal::isMaintenanceModeActive() && ! $user->isTestingUser()) {
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

        Auth::login($user);
        $request->session()->regenerate();

        return $this->redirectBasedOnRole(Auth::user());
    }

    /**
     * Redirect User Berdasarkan Role
     *
     * Urutan prioritas:
     *  1. Satpam          → satpam.dashboard
     *  2. Admin Waka Kurikulum (sub_role = waka_kurikulum) → kurikulum.dashboard
     *  3. Admin lainnya (TU, super_admin, dll.) → home (admin dashboard)
     *  4. Guru piket hari ini → piket.dashboard
     *  5. Guru biasa / wali kelas → guru.dashboard
     */
    protected function redirectBasedOnRole($user)
    {
        // 1. Satpam / Petugas Keamanan → portal satpam
        if ($user->isSatpam()) {
            return redirect()->route('satpam.dashboard')
                ->with('success', 'Selamat datang kembali, '.$user->nama.'!');
        }

        // 2. Admin dengan sub_role waka_kurikulum → portal kurikulum
        if ($user->role === 'admin' && $user->sub_role === 'waka_kurikulum') {
            return redirect()->route('kurikulum.dashboard')
                ->with('success', 'Selamat datang kembali, Waka Kurikulum '.$user->nama.'!');
        }

        // 3. Admin dengan sub_role waka_sdm → portal Waka SDM
        if (($user->role === 'admin' && $user->sub_role === 'waka_sdm') || $user->role === 'waka_sdm') {
            return redirect()->route('waka-sdm.dashboard')
                ->with('success', 'Selamat datang kembali, Waka SDM '.$user->nama.'!');
        }

        // 3b. Admin dengan sub_role waka_kesiswaan → portal Waka Kesiswaan
        if ($user->role === 'admin' && $user->sub_role === 'waka_kesiswaan') {
            return redirect()->route('waka-kesiswaan.dashboard')
                ->with('success', 'Selamat datang kembali, Waka Kesiswaan '.$user->nama.'!');
        }

        // 4. Petugas IT / QA Tester → dashboard IT
        if ($user->isPetugasIt()) {
            return redirect()->route('it.dashboard')
                ->with('success', 'Selamat datang kembali, Petugas IT '.$user->nama.'!');
        }

        // 5. Admin lainnya (super_admin, TU, warden, dll.) → halaman utama admin
        if (in_array($user->role, ['admin', 'super_admin', 'epic_admin', 'absolute_admin', 'warden'])) {
            return redirect()->route('home')
                ->with('success', 'Selamat datang kembali, Admin '.$user->nama.'!');
        }

        // 6. Guru yang mendapat jadwal piket HARI INI → portal piket
        if ($user->isPiketHariIni()) {
            return redirect()->route('piket.dashboard')
                ->with('success', 'Selamat datang kembali, Guru Piket '.$user->nama.'!');
        }

        // 5. Guru biasa / wali kelas / guru mapel → portal guru
        if (in_array($user->role, ['guru', 'guru_mapel', 'wali_kelas'])) {
            return redirect()->route('guru.dashboard')
                ->with('success', 'Selamat datang kembali, '.$user->nama.'!');
        }

        // Fallback — redirect ke home
        return redirect()->route('home');
    }

    /**
     * Proses Logout User
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Anda telah berhasil keluar.');
    }
}
