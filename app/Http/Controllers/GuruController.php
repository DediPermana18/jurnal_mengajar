<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Kelas;
use App\Models\Jurusan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GuruController extends Controller
{
    /**
     * Proteksi server-side: Hanya user dengan role admin yang dapat melihat data
     */
    protected function authorizeAdmin()
    {
        $role = Auth::check() ? Auth::user()->role : null;
        abort_if($role !== 'admin' && !in_array($role, ['admin_tu', 'admin', 'super_admin']), 403, 'Akses ditolak. Anda tidak memiliki izin untuk fitur manajemen akun.');
    }

    /**
     * Proteksi untuk operasi store & update: Semua user dengan role admin diizinkan
     */
    protected function authorizePetugasTU()
    {
        $role = Auth::check() ? Auth::user()->role : null;
        abort_if($role !== 'admin' && !in_array($role, ['admin_tu', 'admin', 'super_admin']), 403, 'Akses ditolak. Hanya Admin yang dapat menambah/mengubah data guru.');
    }

    /**
     * Menampilkan daftar Data Master Guru dengan fungsi pencarian & filter
     */
    public function index(Request $request)
    {
        $this->authorizeAdmin();

        $query = User::query()
            ->where('role', User::ROLE_GURU);

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('nip', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'Semua Status') {
            if ($request->status === 'Aktif') {
                $query->where('is_active', true);
            } elseif ($request->status === 'Tidak Aktif' || $request->status === 'Nonaktif') {
                $query->where('is_active', false);
            }
        }

        if ($request->filled('wali_kelas') && $request->wali_kelas !== 'Semua') {
            if ($request->wali_kelas === 'Ya') {
                $query->where(function($q) {
                    $q->has('kelasWali')->orWhereNotNull('kelas_id');
                });
            } elseif ($request->wali_kelas === 'Tidak') {
                $query->doesntHave('kelasWali')->whereNull('kelas_id');
            } elseif (str_starts_with($request->wali_kelas, 'kelas_')) {
                $kelasId = (int) str_replace('kelas_', '', $request->wali_kelas);
                $query->where(function($q) use ($kelasId) {
                    $q->whereHas('kelasWali', fn($k) => $k->where('id', $kelasId))
                      ->orWhere('kelas_id', $kelasId);
                });
            }
        }

        $dataGuru = $query->with(['kelas.jurusan', 'kelasWali.jurusan', 'jadwalPelajaran.mataPelajaran', 'jadwalPelajaran.kelas.jurusan'])
            ->orderBy('id', 'asc')
            ->paginate(10)
            ->withQueryString();

        $daftarKelas = Kelas::with('jurusan')->orderBy('tingkat')->orderBy('nama_kelas')->get();

        return view('admin.guru.index', compact('dataGuru', 'daftarKelas'));
    }

    public function create()
    {
        $this->authorizePetugasTU();

        return view('admin.guru.create', [
            'daftarKelas' => Kelas::with('jurusan')->orderBy('nama_kelas')->get(),
            'currentKelasId' => null,
        ]);
    }

    public function edit($id)
    {
        $this->authorizePetugasTU();

        $guru = User::where('role', User::ROLE_GURU)
            ->with(['kelasWali', 'kelas'])
            ->findOrFail($id);
        return view('admin.guru.edit', [
            'guru' => $guru,
            'daftarKelas' => Kelas::with('jurusan')->orderBy('nama_kelas')->get(),
            'currentKelasId' => $guru->kelas_id ?: $guru->kelasWali->first()?->id,
        ]);
    }

    /**
     * Menyimpan data guru baru (Khusus Petugas TU)
     */
    public function store(Request $request)
    {
        $this->authorizePetugasTU();

        $request->validate([
            'nama'      => 'required|string|max:255',
            'nip'       => 'nullable|string|max:50|unique:users,nip',
            'username'  => 'required|string|max:100|unique:users,username',
            'password'  => 'nullable|string|min:6',
            'role'      => 'required|in:guru_mapel,wali_kelas,guru',
            'kelas_id'  => 'nullable|exists:kelas,id',
        ], [
            'nama.required'   => 'Nama guru wajib diisi.',
            'nip.unique'      => 'NIP sudah terdaftar dalam sistem.',
            'username.required' => 'Username wajib diisi.',
            'username.unique' => 'Username sudah terdaftar dalam sistem.',
            'role.required'   => 'Role guru wajib dipilih.',
            'role.in'         => 'Role guru tidak valid.',
            'kelas_id.exists' => 'Kelas yang dipilih tidak ditemukan.',
        ]);

        $role = $request->role;

        // Validasi: kelas_id wajib diisi jika role wali_kelas
        if ($role === 'wali_kelas' && empty($request->kelas_id)) {
            return back()->withErrors(['kelas_id' => 'Kelas wajib dipilih untuk role Wali Kelas.'])->withInput();
        }

        // Validasi: kelas yang dipilih belum ada wali kelasnya
        if ($role === 'wali_kelas' && !empty($request->kelas_id)) {
            $kelasExists = Kelas::where('id', $request->kelas_id)
                ->whereNotNull('id_wali_kelas')
                ->exists();
            if ($kelasExists) {
                return back()->withErrors(['kelas_id' => 'Kelas yang dipilih sudah memiliki Wali Kelas.'])->withInput();
            }
        }

        $kelasId = ($role === 'wali_kelas' && $request->filled('kelas_id')) ? (int)$request->kelas_id : null;

        // Buat user baru
        $user = User::create([
            'nama'          => $request->nama,
            'nip'           => $request->nip,
            'username'      => $request->username,
            'password'      => Hash::make($request->password ?? 'password123'),
            'role'          => User::ROLE_GURU,
            'sub_role'      => $role,
            'is_active'     => true,
            'kelas_id'      => $kelasId,
        ]);

        // Assign wali kelas: set id_wali_kelas pada tabel kelas
        if ($role === 'wali_kelas' && !empty($kelasId)) {
            Kelas::where('id', $kelasId)->update(['id_wali_kelas' => $user->id]);
        }

        return redirect()->route('guru.index')->with('success', 'Data Guru baru berhasil ditambahkan!');
    }

    /**
     * Memperbarui data guru (Khusus Petugas TU)
     */
    public function update(Request $request, $id)
    {
        $this->authorizePetugasTU();

        $user = User::withTrashed()->where('role', User::ROLE_GURU)->findOrFail($id);

        $request->validate([
            'nama'      => 'required|string|max:255',
            'nip'       => 'nullable|string|max:50|unique:users,nip,' . $user->id,
            'username'  => 'required|string|max:100|unique:users,username,' . $user->id,
            'role'      => 'required|in:guru_mapel,wali_kelas,guru',
            'kelas_id'  => 'nullable|exists:kelas,id',
        ], [
            'nama.required'   => 'Nama guru wajib diisi.',
            'nip.unique'      => 'NIP sudah terdaftar dalam sistem.',
            'username.required' => 'Username wajib diisi.',
            'username.unique' => 'Username sudah terdaftar dalam sistem.',
            'role.required'   => 'Role guru wajib dipilih.',
            'role.in'         => 'Role guru tidak valid.',
            'kelas_id.exists' => 'Kelas yang dipilih tidak ditemukan.',
        ]);

        $role = $request->role;

        // Validasi: kelas_id wajib diisi jika role wali_kelas
        if ($role === 'wali_kelas' && empty($request->kelas_id)) {
            return back()->withErrors(['kelas_id' => 'Kelas wajib dipilih untuk role Wali Kelas.'])->withInput();
        }

        // Validasi: kelas yang dipilih belum ada wali kelasnya (kecuali kelas yang sedang dipegang guru ini)
        if ($role === 'wali_kelas' && !empty($request->kelas_id)) {
            $kelasExists = Kelas::where('id', $request->kelas_id)
                ->where('id_wali_kelas', '!=', $user->id)
                ->whereNotNull('id_wali_kelas')
                ->exists();
            if ($kelasExists) {
                return back()->withErrors(['kelas_id' => 'Kelas yang dipilih sudah memiliki Wali Kelas lain.'])->withInput();
            }
        }

        // Simpan role lama untuk perbandingan
        $oldRole = $user->sub_role ?: $user->role;

        $kelasId = ($role === 'wali_kelas' && $request->filled('kelas_id')) ? (int)$request->kelas_id : null;

        // Update data user
        $user->update([
            'nama'      => $request->nama,
            'nip'       => $request->nip,
            'username'  => $request->username,
            'role'      => User::ROLE_GURU,
            'sub_role'  => $role,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $user->is_active,
            'kelas_id'  => $kelasId,
        ]);

        // ===== HANDLE PERUBAHAN ASSIGNMENT WALI KELAS =====
        if ($oldRole === 'wali_kelas' && $role !== 'wali_kelas') {
            // Role berubah DARI wali_kelas -> selain wali_kelas: lepas semua kelas yang diwaliin
            Kelas::where('id_wali_kelas', $user->id)->update(['id_wali_kelas' => null]);
        }

        if ($role === 'wali_kelas') {
            // Lepas kelas lama guru ini jika ada yang berbeda dari kelas baru
            Kelas::where('id_wali_kelas', $user->id)
                ->when(!empty($kelasId), function($q) use ($kelasId) {
                    $q->where('id', '!=', $kelasId);
                })
                ->update(['id_wali_kelas' => null]);

            // Set guru ini sebagai id_wali_kelas di tabel kelas
            if (!empty($kelasId)) {
                Kelas::where('id', $kelasId)->update(['id_wali_kelas' => $user->id]);
            }
        }

        return redirect()->route('guru.index')->with('success', 'Data Guru berhasil diperbarui!');
    }

    protected function generateActivationCode(): string
    {
        do {
            $code = 'AKT-' . Str::upper(Str::random(8));
        } while (User::withTrashed()->where('kode_aktivasi', $code)->exists());

        return $code;
    }

    /**
     * Soft delete data guru (Admin saja)
     */
    public function destroy($id)
    {
        $this->authorizeAdmin();

        $user = User::where('role', User::ROLE_GURU)->findOrFail($id);

        if ($user->trashed()) {
            return redirect()->route('guru.index')->with('error', 'Data guru sudah dalam status tidak aktif.');
        }

        // Validasi: Cek apakah guru masih menjabat sebagai Wali Kelas
        $kelasWali = Kelas::where('id_wali_kelas', $user->id)->first();
        if ($kelasWali) {
            $namaKelas = $kelasWali->nama_lengkap ?? $kelasWali->nama_kelas;
            return redirect()->route('guru.index')->with('error', "Gagal menghapus! Guru ini masih aktif sebagai Wali Kelas di {$namaKelas}. Silakan ganti wali kelas terlebih dahulu.");
        }

        $user->delete();

        return redirect()->route('guru.index')->with('success', 'Data Guru "' . $user->nama . '" berhasil dihapus (soft delete).');
    }

    /**
     * Reset Password Guru ke Default ('password123')
     */
    public function resetPassword($id)
    {
        $this->authorizeAdmin();

        $user = User::withTrashed()->where('role', User::ROLE_GURU)->findOrFail($id);
        $user->update([
            'password' => Hash::make('password123'),
        ]);

        return redirect()->route('guru.index')->with('success', 'Password guru ' . $user->nama . ' berhasil di-reset menjadi "password123"!');
    }

    /**
     * Reset / Ubah Password Guru Manual (Admin TU / Super Admin)
     */
    public function updatePassword(Request $request, $id)
    {
        $this->authorizeAdmin();

        $user = User::withTrashed()->findOrFail($id);

        $request->validate([
            'password' => 'required|string|min:6',
        ], [
            'password.required' => 'Password baru wajib diisi.',
            'password.min'      => 'Password minimal harus 6 karakter.',
        ]);

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return redirect()->route('guru.index')->with('success', 'Password akun guru ' . $user->nama . ' berhasil diperbarui!');
    }

    /**
     * Setujui / Approve akun guru dan aktifkan
     */
    public function approve($id)
    {
        $this->authorizeAdmin();

        $user = User::findOrFail($id);

        $user->update([
            'is_active' => true,
        ]);

        return redirect()->route('guru.index')->with('success', 'Data guru berhasil disetujui dan diaktifkan.');
    }

    /**
     * Update status aktif / nonaktif akun guru
     */
    public function updateStatus(Request $request, $id)
    {
        $this->authorizeAdmin();

        $user = User::findOrFail($id);
        $isActive = $request->has('is_active') 
            ? filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN)
            : ($request->input('status') === 'aktif' || $request->input('status') === '1' || $request->input('status') === true);

        $user->update([
            'is_active' => $isActive,
        ]);

        $msg = $user->is_active
            ? 'Data guru berhasil disetujui dan diaktifkan.'
            : 'Akun guru ' . $user->nama . ' berhasil dinonaktifkan.';

        return redirect()->route('guru.index')->with('success', $msg);
    }

    /**
     * Toggle Status Aktif / Tidak Aktif (is_active)
     */
    public function toggleStatus($id)
    {
        $this->authorizeAdmin();

        $user = User::findOrFail($id);

        $user->update(['is_active' => !$user->is_active]);

        $msg = $user->is_active
            ? 'Data guru berhasil disetujui dan diaktifkan.'
            : 'Akun guru ' . $user->nama . ' berhasil dinonaktifkan.';

        return redirect()->route('guru.index')->with('success', $msg);
    }
}
