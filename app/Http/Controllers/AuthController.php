<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class AuthController extends Controller
{
    /**
     * Tampilkan Halaman Login
     */
    public function showLoginForm()
    {
        if (Auth::check()) {
            return $this->redirectBasedOnRole(Auth::user());
        }

        return view('auth.login');
    }

    /**
     * Proses Autentikasi Login
     */
    public function login(Request $request)
    {
        $mode = $request->input('mode', 'guru');

        // Rule Validasi Input Dasar
        $rules = [
            'login_id' => 'required|string',
            'password' => 'required|string',
            'mode'     => 'required|in:guru,admin',
        ];

        // Jika mode Admin, wajibkan field kode_aktivasi
        if ($mode === 'admin') {
            $rules['kode_aktivasi'] = 'required|string';
        }

        $request->validate($rules, [
            'login_id.required'      => 'Username atau NIP wajib diisi.',
            'password.required'      => 'Password wajib diisi.',
            'kode_aktivasi.required' => 'Kode Aktivasi khusus Admin wajib diisi.',
        ]);

        $loginId = trim($request->input('login_id'));
        $password = $request->input('password');

        // Cari user berdasarkan Username, NIP, atau Email (termasuk yang dinonaktifkan)
        $user = User::withTrashed()
                    ->where(function($q) use ($loginId) {
                        $q->where('username', $loginId)
                          ->orWhere('nip', $loginId)
                          ->orWhere('email', $loginId);
                    })
                    ->first();

        if (!$user) {
            return back()->withErrors(['login_id' => 'Username atau NIP tidak terdaftar dalam sistem.'])->withInput();
        }

        // Cek jika akun sedang non-aktif (dinonaktifkan admin)
        if (!$user->is_active) {
            return back()->withErrors(['login_id' => 'Akun Anda telah dinonaktifkan, silakan hubungi admin.'])->withInput();
        }

        // Cek jika akun guru/admin sudah di-soft delete
        if ($user->trashed()) {
            return back()->withErrors(['login_id' => 'Akun Anda sudah tidak berlaku. Silakan hubungi Admin TU.'])->withInput();
        }

        // ================= VALIDASI KODE AKTIVASI ADMIN =================
        if ($mode === 'admin') {
            // Bersihkan input spasi dan ubah ke huruf kecil untuk perbandingan tidak case-sensitive
            $inputKode = strtolower(trim($request->input('kode_aktivasi', '')));

            // Daftar Kode Aktivasi yang Diizinkan (Dev/Testing & DB)
            $allowedCodes = [
                'admin123',
                'webjournal2026',
                'adm123',
            ];

            // Jika di database user mempunyai kode_aktivasi khusus, masukkan ke daftar valid
            if (!empty($user->kode_aktivasi)) {
                $allowedCodes[] = strtolower(trim($user->kode_aktivasi));
            }

            Log::info('Proses Validasi Login Admin:', [
                'user'        => $user->username,
                'input_kode'  => $inputKode,
                'valid_codes' => $allowedCodes
            ]);

            // Cek apakah kode yang diinput sesuai
            if (!in_array($inputKode, $allowedCodes)) {
                return back()->withErrors([
                    'kode_aktivasi' => 'Kode Aktivasi Admin tidak valid. (Gunakan: ADMIN123 atau WEBJOURNAL2026)'
                ])->withInput();
            }
        }

        // ================= PROSES AUTENTIKASI PASSWORD =================
        // Verifikasi password LANGSUNG terhadap user yang sudah di-resolve di atas
        // (berdasarkan identifier unik: username/nip/email). Tidak menggunakan
        // Auth::attempt() ulang agar tidak terjadi 'crossover' role bila ada
        // username/nip/email yang kembar antar user.
        if (!Hash::check($password, $user->password)) {
            return back()->withErrors(['password' => 'Password yang Anda masukkan salah.'])->withInput();
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
                ->with('success', 'Selamat datang kembali, ' . $user->nama . '!');
        }

        // 2. Admin dengan sub_role waka_kurikulum → portal kurikulum
        if ($user->role === 'admin' && $user->sub_role === 'waka_kurikulum') {
            return redirect()->route('kurikulum.dashboard')
                ->with('success', 'Selamat datang kembali, Waka Kurikulum ' . $user->nama . '!');
        }

        // 3. Admin lainnya (super_admin, TU, warden, dll.) → halaman utama admin
        if (in_array($user->role, ['admin', 'super_admin', 'epic_admin', 'absolute_admin', 'warden'])) {
            return redirect()->route('home')
                ->with('success', 'Selamat datang kembali, Admin ' . $user->nama . '!');
        }

        // 4. Guru yang mendapat jadwal piket HARI INI → portal piket
        if ($user->isPiketHariIni()) {
            return redirect()->route('piket.dashboard')
                ->with('success', 'Selamat datang kembali, Guru Piket ' . $user->nama . '!');
        }

        // 5. Guru biasa / wali kelas / guru mapel → portal guru
        if (in_array($user->role, ['guru', 'guru_mapel', 'wali_kelas'])) {
            return redirect()->route('guru.dashboard')
                ->with('success', 'Selamat datang kembali, ' . $user->nama . '!');
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
