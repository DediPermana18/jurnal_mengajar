<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Str;

class ProfilController extends Controller
{
    /**
     * Tampilkan halaman Pengaturan Profil & Akun Saya.
     */
    public function index()
    {
        return view('profil.index', [
            'user' => Auth::user(),
        ]);
    }

    /**
     * Update data profil: nama, nip, username, email, foto.
     */
    public function updateProfil(Request $request)
    {
        $user = Auth::user();

        $rules = [
            'nama'       => 'required|string|max:150',
            'nip'        => "nullable|string|max:50|unique:users,nip,{$user->id}",
            'username'   => "required|string|max:100|unique:users,username,{$user->id}",
            'email'      => "nullable|email|max:150|unique:users,email,{$user->id}",
            'no_hp'      => 'nullable|string|max:20',
            'foto_profil' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ];

        $validated = $request->validate($rules, [
            'nip.unique'      => 'NIP sudah digunakan oleh akun lain.',
            'username.unique' => 'Username sudah digunakan oleh akun lain.',
            'email.unique'    => 'Email sudah digunakan oleh akun lain.',
            'foto_profil.max' => 'Ukuran foto tidak boleh melebihi 2 MB.',
        ]);

        // Handle upload foto profil
        if ($request->hasFile('foto_profil')) {
            // Hapus foto lama jika ada
            if ($user->foto_profil && Storage::disk('public')->exists($user->foto_profil)) {
                Storage::disk('public')->delete($user->foto_profil);
            }
            $path = $request->file('foto_profil')->store('foto_profil', 'public');
            $user->foto_profil = $path;
        }

        $user->nama     = $validated['nama'];
        $user->nip      = $validated['nip'] ?? null;
        $user->username = $validated['username'];
        $user->email    = $validated['email'] ?? null;
        $user->no_hp    = $user->normalizeNoHp($validated['no_hp'] ?? '');
        $user->save();

        return redirect()->route('profil.index')
            ->with('success_profil', 'Profil berhasil diperbarui.');
    }

    /**
     * Ganti password akun.
     */
    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'current_password'          => 'required|string',
            'password'                  => ['required', 'string', 'confirmed', Password::min(8)],
            'password_confirmation'     => 'required|string',
        ], [
            'current_password.required' => 'Password saat ini wajib diisi.',
            'password.confirmed'        => 'Konfirmasi password baru tidak cocok.',
            'password.min'              => 'Password baru minimal 8 karakter.',
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return back()
                ->withErrors(['current_password' => 'Password saat ini tidak sesuai.'])
                ->with('tab_aktif', 'password');
        }

        $user->password = Hash::make($request->password);
        $user->save();

        return redirect()->route('profil.index')
            ->with('success_password', 'Password berhasil diperbarui. Silakan login ulang.');
    }

    /**
     * Generate kode aktivasi baru (PIN Token) - Hanya untuk Admin.
     */
    public function generateKodeAktivasi()
    {
        $user = Auth::user();

        // Hanya Admin yang boleh generate kode aktivasi
        abort_unless($user->role === 'admin', 403, 'Akses ditolak. Hanya Administrator yang dapat menggenerate kode aktivasi.');

        // Generate kode unik 8 karakter alphanumeric uppercase
        do {
            $kode = strtoupper(Str::random(8));
        } while (\App\Models\User::where('kode_aktivasi', $kode)->whereNot('id', $user->id)->exists());

        $user->kode_aktivasi = $kode;
        $user->save();

        return redirect()->route('profil.index')
            ->with('success_kode', "Kode Aktivasi baru berhasil digenerate: {$kode}");
    }
}
