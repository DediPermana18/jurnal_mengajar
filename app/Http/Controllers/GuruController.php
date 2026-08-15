<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Jurusan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class GuruController extends Controller
{
    /**
     * Proteksi server-side: Hanya admin_tu, admin, dan super_admin yang dapat mengubah data
     */
    protected function authorizeAdmin()
    {
        $role = auth()->check() ? auth()->user()->role : 'admin';
        abort_if(!in_array($role, ['admin_tu', 'admin', 'super_admin']), 403, 'Akses ditolak. Anda tidak memiliki izin untuk fitur manajemen akun.');
    }

    /**
     * Menampilkan daftar Data Master Guru dengan fungsi pencarian & filter
     */
    public function index(Request $request)
    {
        // Query seluruh user dengan role terkait guru
        $query = User::withTrashed()
            ->whereIn('role', ['guru', 'guru_mapel', 'wali_kelas', 'guru_piket', 'piket_satpam']);

        // 1. Filter Pencarian Nama atau NIP
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('nip', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%");
            });
        }

        // 2. Filter Status (Aktif / Tidak Aktif)
        if ($request->filled('status') && $request->status !== 'Semua Status') {
            if ($request->status === 'Aktif') {
                $query->whereNull('deleted_at');
            } elseif ($request->status === 'Tidak Aktif') {
                $query->onlyTrashed();
            }
        }

        // 3. Filter Wali Kelas (Ya / Tidak)
        if ($request->filled('wali_kelas') && $request->wali_kelas !== 'Semua') {
            if ($request->wali_kelas === 'Ya') {
                $query->has('kelasWali');
            } elseif ($request->wali_kelas === 'Tidak') {
                $query->doesntHave('kelasWali');
            }
        }

        // 4. Filter Kejuruan (RPL, TKJ, AKL, TKR, dst)
        if ($request->filled('kejuruan') && $request->kejuruan !== 'Semua Kejuruan') {
            $kejuruanFilter = $request->kejuruan;
            $query->where(function ($q) use ($kejuruanFilter) {
                $q->whereHas('kelasWali.jurusan', function ($jQ) use ($kejuruanFilter) {
                    $jQ->where('kode_jurusan', $kejuruanFilter)
                       ->orWhere('nama_jurusan', 'like', "%{$kejuruanFilter}%");
                })->orWhereHas('jadwalPelajaran.kelas.jurusan', function ($jQ) use ($kejuruanFilter) {
                    $jQ->where('kode_jurusan', $kejuruanFilter)
                       ->orWhere('nama_jurusan', 'like', "%{$kejuruanFilter}%");
                });
            });
        }

        // Eager loading relasi
        $dataGuru = $query->with(['kelasWali.jurusan', 'jadwalPelajaran.mataPelajaran', 'jadwalPelajaran.kelas.jurusan'])
            ->orderBy('id', 'asc')
            ->paginate(10)
            ->withQueryString();

        $daftarKejuruan = Jurusan::all();

        return view('admin.guru.index', compact('dataGuru', 'daftarKejuruan'));
    }

    /**
     * Menyimpan data guru baru (Admin saja)
     */
    public function store(Request $request)
    {
        $this->authorizeAdmin();

        $request->validate([
            'nama'     => 'required|string|max:255',
            'nip'      => 'nullable|string|max:50|unique:users,nip',
            'username' => 'required|string|max:100|unique:users,username',
            'password' => 'nullable|string|min:6',
            'role'     => 'required|in:guru_mapel,wali_kelas,guru_piket,guru',
        ], [
            'nama.required'   => 'Nama guru wajib diisi.',
            'nip.unique'      => 'NIP sudah terdaftar dalam sistem.',
            'username.unique' => 'Username sudah terdaftar dalam sistem.',
        ]);

        User::create([
            'nama'          => $request->nama,
            'nip'           => $request->nip,
            'username'      => $request->username,
            'password'      => Hash::make($request->password ?? 'password123'),
            'role'          => $request->role,
            'kode_aktivasi' => null,
        ]);

        return redirect()->route('guru.index')->with('success', 'Data Guru baru berhasil ditambahkan!');
    }

    /**
     * Memperbarui data guru (Admin saja)
     */
    public function update(Request $request, $id)
    {
        $this->authorizeAdmin();

        $user = User::withTrashed()->findOrFail($id);

        $request->validate([
            'nama'     => 'required|string|max:255',
            'nip'      => 'nullable|string|max:50|unique:users,nip,' . $user->id,
            'username' => 'required|string|max:100|unique:users,username,' . $user->id,
            'role'     => 'required|in:guru_mapel,wali_kelas,guru_piket,guru',
        ]);

        $user->update([
            'nama'     => $request->nama,
            'nip'      => $request->nip,
            'username' => $request->username,
            'role'     => $request->role,
        ]);

        return redirect()->route('guru.index')->with('success', 'Data Guru berhasil diperbarui!');
    }

    /**
     * Menghapus data guru (Admin saja)
     */
    public function destroy($id)
    {
        $this->authorizeAdmin();

        $user = User::withTrashed()->findOrFail($id);
        $user->forceDelete();

        return redirect()->route('guru.index')->with('success', 'Data Guru berhasil dihapus secara permanen!');
    }

    /**
     * Reset Password Guru ke Default ('password123')
     */
    public function resetPassword($id)
    {
        $this->authorizeAdmin();

        $user = User::withTrashed()->findOrFail($id);
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
     * Toggle Status Aktif / Tidak Aktif (Soft Delete / Restore)
     */
    public function toggleStatus($id)
    {
        $this->authorizeAdmin();

        $user = User::withTrashed()->findOrFail($id);

        if ($user->trashed()) {
            $user->restore();
            $msg = 'Status akun guru ' . $user->nama . ' berhasil diubah menjadi AKTIF.';
        } else {
            $user->delete();
            $msg = 'Status akun guru ' . $user->nama . ' berhasil diubah menjadi TIDAK AKTIF.';
        }

        return redirect()->route('guru.index')->with('success', $msg);
    }
}