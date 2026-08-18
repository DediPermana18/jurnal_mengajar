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
     * Proteksi server-side: Hanya admin_tu, admin, dan super_admin yang dapat melihat data
     */
    protected function authorizeAdmin()
    {
        $role = auth()->check() ? auth()->user()->role : null;
        abort_if(!in_array($role, ['admin_tu', 'admin', 'super_admin']), 403, 'Akses ditolak. Anda tidak memiliki izin untuk fitur manajemen akun.');
    }

    /**
     * Proteksi khusus Petugas TU (admin_tu) untuk operasi store & update
     */
    protected function authorizePetugasTU()
    {
        $role = auth()->check() ? auth()->user()->role : null;
        abort_if($role !== 'admin_tu', 403, 'Akses ditolak. Hanya Petugas TU yang dapat menambah/mengubah data guru.');
    }

    /**
     * Menampilkan daftar Data Master Guru dengan fungsi pencarian & filter
     */
    public function index(Request $request)
    {
        $this->authorizeAdmin();

        $query = User::withTrashed()
            ->whereIn('role', ['guru', 'guru_mapel', 'wali_kelas', 'guru_piket', 'piket_satpam']);

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
                $query->whereNull('deleted_at');
            } elseif ($request->status === 'Tidak Aktif') {
                $query->onlyTrashed();
            }
        }

        if ($request->filled('wali_kelas') && $request->wali_kelas !== 'Semua') {
            if ($request->wali_kelas === 'Ya') {
                $query->where(function($q) {
                    $q->has('kelasWali')->orWhereNotNull('kelas_id');
                });
            } elseif ($request->wali_kelas === 'Tidak') {
                $query->doesntHave('kelasWali')->whereNull('kelas_id');
            }
        }

        if ($request->filled('kejuruan') && $request->kejuruan !== 'Semua Kejuruan') {
            $kejuruanFilter = $request->kejuruan;
            $query->where(function ($q) use ($kejuruanFilter) {
                $q->whereHas('kelasWali.jurusan', function ($jQ) use ($kejuruanFilter) {
                    $jQ->where('kode_jurusan', $kejuruanFilter)
                       ->orWhere('nama_jurusan', 'like', "%{$kejuruanFilter}%");
                })->orWhereHas('kelas.jurusan', function ($jQ) use ($kejuruanFilter) {
                    $jQ->where('kode_jurusan', $kejuruanFilter)
                       ->orWhere('nama_jurusan', 'like', "%{$kejuruanFilter}%");
                })->orWhereHas('jadwalPelajaran.kelas.jurusan', function ($jQ) use ($kejuruanFilter) {
                    $jQ->where('kode_jurusan', $kejuruanFilter)
                       ->orWhere('nama_jurusan', 'like', "%{$kejuruanFilter}%");
                });
            });
        }

        $dataGuru = $query->with(['kelas.jurusan', 'kelasWali.jurusan', 'jadwalPelajaran.mataPelajaran', 'jadwalPelajaran.kelas.jurusan'])
            ->orderBy('id', 'asc')
            ->paginate(10)
            ->withQueryString();

        $daftarKejuruan = Jurusan::all();
        $daftarMapel = MataPelajaran::orderBy('nama_mapel')->get();
        $daftarKelas = Kelas::with('jurusan')->orderBy('nama_kelas')->get();

        return view('admin.guru.index', compact('dataGuru', 'daftarKejuruan', 'daftarMapel', 'daftarKelas'));
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
            'role'      => 'required|in:guru_mapel,wali_kelas,guru_piket,guru',
            'mapel_ids' => 'nullable|array',
            'mapel_ids.*' => 'exists:mata_pelajaran,id',
            'kelas_id'  => 'nullable|exists:kelas,id',
        ], [
            'nama.required'   => 'Nama guru wajib diisi.',
            'nip.unique'      => 'NIP sudah terdaftar dalam sistem.',
            'username.required' => 'Username wajib diisi.',
            'username.unique' => 'Username sudah terdaftar dalam sistem.',
            'role.required'   => 'Role guru wajib dipilih.',
            'role.in'         => 'Role guru tidak valid.',
            'mapel_ids.array' => 'Format mata pelajaran tidak valid.',
            'mapel_ids.*.exists' => 'Mata pelajaran yang dipilih tidak ditemukan.',
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

        // Olah mapel_ids menjadi array integer bersih atau null
        $mapelIds = null;
        if ($request->filled('mapel_ids') || is_array($request->mapel_ids)) {
            $mapelIds = array_values(array_filter(array_map('intval', (array)$request->mapel_ids)));
            if (empty($mapelIds)) {
                $mapelIds = null;
            }
        }

        $kelasId = ($role === 'wali_kelas' && $request->filled('kelas_id')) ? (int)$request->kelas_id : null;

        // Buat user baru
        $user = User::create([
            'nama'          => $request->nama,
            'nip'           => $request->nip,
            'username'      => $request->username,
            'password'      => Hash::make($request->password ?? 'password123'),
            'role'          => $role,
            'kode_aktivasi' => null,
            'mapel_ids'     => $mapelIds,
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

        $user = User::withTrashed()->findOrFail($id);

        $request->validate([
            'nama'      => 'required|string|max:255',
            'nip'       => 'nullable|string|max:50|unique:users,nip,' . $user->id,
            'username'  => 'required|string|max:100|unique:users,username,' . $user->id,
            'role'      => 'required|in:guru_mapel,wali_kelas,guru_piket,guru',
            'mapel_ids' => 'nullable|array',
            'mapel_ids.*' => 'exists:mata_pelajaran,id',
            'kelas_id'  => 'nullable|exists:kelas,id',
        ], [
            'nama.required'   => 'Nama guru wajib diisi.',
            'nip.unique'      => 'NIP sudah terdaftar dalam sistem.',
            'username.required' => 'Username wajib diisi.',
            'username.unique' => 'Username sudah terdaftar dalam sistem.',
            'role.required'   => 'Role guru wajib dipilih.',
            'role.in'         => 'Role guru tidak valid.',
            'mapel_ids.array' => 'Format mata pelajaran tidak valid.',
            'mapel_ids.*.exists' => 'Mata pelajaran yang dipilih tidak ditemukan.',
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
        $oldRole = $user->role;

        // Olah mapel_ids menjadi array integer bersih atau null
        $mapelIds = null;
        if ($request->has('mapel_ids') && is_array($request->mapel_ids)) {
            $mapelIds = array_values(array_filter(array_map('intval', (array)$request->mapel_ids)));
            if (empty($mapelIds)) {
                $mapelIds = null;
            }
        }

        $kelasId = ($role === 'wali_kelas' && $request->filled('kelas_id')) ? (int)$request->kelas_id : null;

        // Update data user
        $user->update([
            'nama'      => $request->nama,
            'nip'       => $request->nip,
            'username'  => $request->username,
            'role'      => $role,
            'mapel_ids' => $mapelIds,
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
