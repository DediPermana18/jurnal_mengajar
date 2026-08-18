<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\Jurusan;
use App\Models\User;
use App\Models\Siswa;
use App\Models\JadwalPelajaran;
use Illuminate\Http\Request;

class KelasController extends Controller
{
    /**
     * Proteksi server-side: Hanya admin_tu, admin, dan super_admin yang dapat melihat data kelas
     */
    protected function authorizeAdmin()
    {
        $role = auth()->check() ? auth()->user()->role : null;
        abort_if(!in_array($role, ['admin_tu', 'admin', 'super_admin']), 403, 'Akses ditolak. Anda tidak memiliki izin untuk fitur manajemen kelas.');
    }

    /**
     * Proteksi khusus Petugas TU (admin_tu) untuk operasi create, store, update, destroy
     */
    protected function authorizePetugasTU()
    {
        $role = auth()->check() ? auth()->user()->role : null;
        abort_if(!in_array($role, ['admin_tu', 'admin', 'super_admin']), 403, 'Akses ditolak. Hanya Petugas TU / Admin yang dapat menambah/mengubah data kelas.');
    }

    /**
     * Menampilkan daftar semua kelas beserta relasi Jurusan dan Wali Kelas
     */
    public function index(Request $request)
    {
        $this->authorizeAdmin();

        $query = Kelas::with(['jurusan', 'waliKelas'])->withCount('siswa');

        // Pencarian Nama Kelas, Tingkat, Jurusan, atau Wali Kelas
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('nama_kelas', 'like', "%{$search}%")
                  ->orWhere('tingkat', 'like', "%{$search}%")
                  ->orWhereHas('jurusan', function ($jQ) use ($search) {
                      $jQ->where('kode_jurusan', 'like', "%{$search}%")
                         ->orWhere('nama_jurusan', 'like', "%{$search}%");
                  })
                  ->orWhereHas('waliKelas', function ($wQ) use ($search) {
                      $wQ->where('nama', 'like', "%{$search}%")
                         ->orWhere('nip', 'like', "%{$search}%");
                  });
            });
        }

        // Filter berdasarkan Tingkat
        if ($request->filled('tingkat') && $request->tingkat !== 'Semua Tingkat') {
            $query->where('tingkat', $request->tingkat);
        }

        // Filter berdasarkan Jurusan
        if ($request->filled('jurusan') && $request->jurusan !== 'Semua Jurusan') {
            $jurusanFilter = $request->jurusan;
            $query->where(function ($q) use ($jurusanFilter) {
                $q->where('id_jurusan', $jurusanFilter)
                  ->orWhereHas('jurusan', function ($jQ) use ($jurusanFilter) {
                      $jQ->where('kode_jurusan', $jurusanFilter);
                  });
            });
        }

        $dataKelas = $query->orderBy('tingkat', 'asc')
            ->orderBy('nama_kelas', 'asc')
            ->paginate(10)
            ->withQueryString();

        $daftarJurusan = Jurusan::orderBy('nama_jurusan')->get();
        $daftarWaliKelas = User::whereIn('role', ['wali_kelas', 'guru', 'guru_mapel'])
            ->with('kelasWali')
            ->orderBy('nama')
            ->get();

        return view('admin.kelas.index', compact('dataKelas', 'daftarJurusan', 'daftarWaliKelas'));
    }

    /**
     * Menampilkan detail kelas: daftar siswa & jadwal pelajaran
     */
    public function show($id)
    {
        $this->authorizeAdmin();

        $kelas = Kelas::with(['jurusan', 'waliKelas'])->withCount('siswa')->findOrFail($id);
        $siswa = Siswa::where('id_kelas', $id)->orderBy('nama')->get();
        $jadwals = JadwalPelajaran::with(['guru', 'mataPelajaran', 'jamPelajaran'])
            ->where('id_kelas', $id)
            ->get();

        return view('admin.kelas.show', compact('kelas', 'siswa', 'jadwals'));
    }

    /**
     * Menyimpan data kelas baru
     */
    public function store(Request $request)
    {
        $this->authorizePetugasTU();

        $idJurusan = $request->id_jurusan ?? $request->jurusan_id;
        $idWaliKelas = $request->id_wali_kelas ?? $request->wali_kelas_id;

        $request->merge([
            'id_jurusan'    => $idJurusan,
            'id_wali_kelas' => $idWaliKelas ?: null,
        ]);

        $request->validate([
            'nama_kelas'    => 'required|string|max:50|unique:kelas,nama_kelas',
            'tingkat'       => 'required|in:X,XI,XII',
            'id_jurusan'    => 'required|exists:jurusan,id',
            'id_wali_kelas' => 'nullable|exists:users,id',
        ], [
            'nama_kelas.required'    => 'Nama kelas wajib diisi.',
            'nama_kelas.unique'      => 'Nama kelas sudah terdaftar dalam sistem.',
            'tingkat.required'       => 'Tingkat kelas wajib dipilih.',
            'tingkat.in'             => 'Pilihan tingkat tidak valid (harus X, XI, atau XII).',
            'id_jurusan.required'    => 'Jurusan wajib dipilih.',
            'id_jurusan.exists'      => 'Jurusan yang dipilih tidak ditemukan.',
            'id_wali_kelas.exists'   => 'Wali kelas yang dipilih tidak ditemukan.',
        ]);

        // Validasi: 1 Guru hanya boleh menjadi Wali Kelas pada 1 kelas
        if (!empty($idWaliKelas)) {
            $isAssigned = Kelas::where('id_wali_kelas', $idWaliKelas)->exists();
            if ($isAssigned) {
                return back()->withErrors(['id_wali_kelas' => 'Guru yang dipilih sudah menjadi Wali Kelas di kelas lain.'])->withInput();
            }
        }

        $kelas = Kelas::create([
            'nama_kelas'    => $request->nama_kelas,
            'tingkat'       => $request->tingkat,
            'id_jurusan'    => $idJurusan,
            'id_wali_kelas' => $idWaliKelas ?: null,
        ]);

        // Sinkronisasi kelas_id pada tabel users
        if (!empty($idWaliKelas)) {
            User::where('id', $idWaliKelas)->update([
                'role'     => 'wali_kelas',
                'kelas_id' => $kelas->id,
            ]);
        }

        return redirect()->route('kelas.index')->with('success', 'Data Kelas baru berhasil ditambahkan!');
    }

    /**
     * Memperbarui data kelas
     */
    public function update(Request $request, $id)
    {
        $this->authorizePetugasTU();

        $kelas = Kelas::findOrFail($id);

        $idJurusan = $request->id_jurusan ?? $request->jurusan_id;
        $idWaliKelas = $request->id_wali_kelas ?? $request->wali_kelas_id;

        $request->merge([
            'id_jurusan'    => $idJurusan,
            'id_wali_kelas' => $idWaliKelas ?: null,
        ]);

        $request->validate([
            'nama_kelas'    => 'required|string|max:50|unique:kelas,nama_kelas,' . $kelas->id,
            'tingkat'       => 'required|in:X,XI,XII',
            'id_jurusan'    => 'required|exists:jurusan,id',
            'id_wali_kelas' => 'nullable|exists:users,id',
        ], [
            'nama_kelas.required'    => 'Nama kelas wajib diisi.',
            'nama_kelas.unique'      => 'Nama kelas sudah terdaftar dalam sistem.',
            'tingkat.required'       => 'Tingkat kelas wajib dipilih.',
            'tingkat.in'             => 'Pilihan tingkat tidak valid (harus X, XI, atau XII).',
            'id_jurusan.required'    => 'Jurusan wajib dipilih.',
            'id_jurusan.exists'      => 'Jurusan yang dipilih tidak ditemukan.',
            'id_wali_kelas.exists'   => 'Wali kelas yang dipilih tidak ditemukan.',
        ]);

        // Validasi: 1 Guru hanya boleh menjadi Wali Kelas pada 1 kelas (kecuali kelas ini sendiri)
        if (!empty($idWaliKelas)) {
            $isAssigned = Kelas::where('id_wali_kelas', $idWaliKelas)
                ->where('id', '!=', $kelas->id)
                ->exists();
            if ($isAssigned) {
                return back()->withErrors(['id_wali_kelas' => 'Guru yang dipilih sudah menjadi Wali Kelas di kelas lain.'])->withInput();
            }
        }

        $oldWaliKelasId = $kelas->id_wali_kelas;

        $kelas->update([
            'nama_kelas'    => $request->nama_kelas,
            'tingkat'       => $request->tingkat,
            'id_jurusan'    => $idJurusan,
            'id_wali_kelas' => $idWaliKelas ?: null,
        ]);

        // Sinkronisasi kelas_id pada tabel users
        if (!empty($oldWaliKelasId) && $oldWaliKelasId != $idWaliKelas) {
            User::where('id', $oldWaliKelasId)->where('kelas_id', $kelas->id)->update(['kelas_id' => null]);
        }

        if (!empty($idWaliKelas)) {
            User::where('id', $idWaliKelas)->update([
                'role'     => 'wali_kelas',
                'kelas_id' => $kelas->id,
            ]);
        }

        return redirect()->route('kelas.index')->with('success', 'Data Kelas berhasil diperbarui!');
    }

    /**
     * Menghapus data kelas dengan validasi relasi siswa
     */
    public function destroy($id)
    {
        $this->authorizePetugasTU();

        $kelas = Kelas::withCount(['siswa', 'jadwalPelajaran'])->findOrFail($id);

        // Cek constraint: cegah penghapusan jika masih ada siswa di kelas
        if ($kelas->siswa_count > 0) {
            return back()->withErrors([
                'error' => 'Kelas "' . $kelas->nama_kelas . '" tidak dapat dihapus karena masih memiliki ' . $kelas->siswa_count . ' siswa terdaftar. Silakan pindahkan data siswa terlebih dahulu.'
            ]);
        }

        // Lepas relasi wali kelas pada model User
        if ($kelas->id_wali_kelas) {
            User::where('id', $kelas->id_wali_kelas)->where('kelas_id', $kelas->id)->update(['kelas_id' => null]);
        }

        $kelas->delete();

        return redirect()->route('kelas.index')->with('success', 'Data Kelas "' . $kelas->nama_kelas . '" berhasil dihapus!');
    }
}
