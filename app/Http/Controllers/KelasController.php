<?php

namespace App\Http\Controllers;

use App\Exports\KelasExport;
use App\Models\Kelas;
use App\Models\Jurusan;
use App\Models\User;
use App\Models\Siswa;
use App\Models\JadwalPelajaran;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

class KelasController extends Controller
{
    /**
     * Proteksi server-side: Hanya user dengan role admin yang dapat melihat data kelas
     */
    protected function authorizeAdmin()
    {
        $role = auth()->check() ? auth()->user()->role : null;
        abort_if($role !== 'admin' && !in_array($role, ['admin_tu', 'admin', 'super_admin']), 403, 'Akses ditolak. Anda tidak memiliki izin untuk fitur manajemen kelas.');
    }

    /**
     * Proteksi untuk operasi create, store, update, destroy: Semua user dengan role admin diizinkan
     */
    protected function authorizePetugasTU()
    {
        $role = auth()->check() ? auth()->user()->role : null;
        abort_if($role !== 'admin' && !in_array($role, ['admin_tu', 'admin', 'super_admin']), 403, 'Akses ditolak. Hanya Admin yang dapat menambah/mengubah data kelas.');
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
        $daftarWaliKelas = User::where('role', 'guru')
            ->orWhereIn('role', ['wali_kelas', 'guru_mapel'])
            ->with('kelasWali')
            ->orderBy('nama')
            ->get();

        // Jumlah rombel per kombinasi tingkat + jurusan untuk auto-increment nomor rombel
        $countsByKombinasi = Kelas::selectRaw('tingkat, id_jurusan, count(*) as total')
            ->groupBy('tingkat', 'id_jurusan')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->tingkat . '|' . $row->id_jurusan => (int) $row->total])
            ->all();

        return view('admin.kelas.index', compact('dataKelas', 'daftarJurusan', 'daftarWaliKelas', 'countsByKombinasi'));
    }

    /**
     * Menampilkan detail kelas: daftar siswa & jadwal pelajaran
     */
    public function show(Request $request, $id)
    {
        $this->authorizeAdmin();

        $kelas = Kelas::with(['jurusan', 'waliKelas'])->withCount('siswa')->findOrFail($id);
        $siswaQuery = Siswa::where('id_kelas', $id);
        if ($request->filled('search')) {
            $search = trim($request->string('search'));
            $siswaQuery->where(function ($siswaFilter) use ($search) {
                $siswaFilter->where('nama', 'like', "%{$search}%")
                    ->orWhere('nis', 'like', "%{$search}%")
                    ->orWhere('nisn', 'like', "%{$search}%");
            });
        }
        $siswa = $siswaQuery->orderBy('nama')->get();
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
            'tingkat'       => 'required|in:X,XI,XII',
            'id_jurusan'    => 'required|exists:jurusan,id',
            'id_wali_kelas' => 'nullable|exists:users,id',
        ], [
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

        // Auto-generate Nomor Rombel & Nama Kelas untuk kombinasi tingkat + jurusan
        $jurusan = Jurusan::findOrFail($idJurusan);
        $latestNumber = Kelas::where('id_jurusan', $jurusan->id)
            ->where('tingkat', $request->tingkat)
            ->count() + 1;

        // Auto-construct full class name: "X RPL 1"
        $namaKelas = trim($request->tingkat . ' ' . $jurusan->kode_jurusan . ' ' . $latestNumber);

        // Cegah duplikat nama kelas (kombinasi tingkat + jurusan + rombel)
        if (Kelas::where('nama_kelas', $namaKelas)->withTrashed()->exists()) {
            return back()->withErrors(['error' => "Nama kelas '{$namaKelas}' sudah terdaftar dalam sistem."])->withInput();
        }

        $kelas = Kelas::create([
            'nama_kelas'    => $namaKelas,
            'tingkat'       => $request->tingkat,
            'id_jurusan'    => $idJurusan,
            'id_wali_kelas' => $idWaliKelas ?: null,
        ]);

        // Sinkronisasi kelas_id pada tabel users
        if (!empty($idWaliKelas)) {
            User::where('id', $idWaliKelas)->update([
                'role'     => 'guru',
                'sub_role' => 'wali_kelas',
                'kelas_id' => $kelas->id,
            ]);
        }

        return redirect()->route('kelas.index')->with('success', 'Data Kelas baru berhasil ditambahkan!');
    }

    /**
     * Export data kelas — format xlsx (default) atau csv.
     */
    public function export(Request $request)
    {
        $this->authorizeAdmin();

        $format = $request->input('format', 'xlsx');
        $filename = 'data_kelas_' . date('Y-m-d_His');

        if ($format === 'csv') {
            return Excel::download(new KelasExport, $filename . '.csv', ExcelFormat::CSV, [
                'Content-Type' => 'text/csv',
            ]);
        }

        return Excel::download(new KelasExport, $filename . '.xlsx', ExcelFormat::XLSX);
    }

    /**
     * Memperbarui data kelas
     */
    public function update(Request $request, $id)
    {
        $this->authorizePetugasTU();

        $kelas = Kelas::findOrFail($id);

        $request->validate([
            'nama_kelas'    => 'required|string|max:50',
            'tingkat'       => 'required|in:X,XI,XII',
            'id_jurusan'    => 'required|exists:jurusan,id',
            'id_wali_kelas' => 'nullable|exists:users,id',
        ], [
            'nama_kelas.required' => 'Nama kelas wajib diisi.',
            'tingkat.required'    => 'Tingkat kelas wajib dipilih.',
            'tingkat.in'          => 'Tingkat kelas harus X, XI, atau XII.',
            'id_jurusan.required' => 'Jurusan wajib dipilih.',
            'id_jurusan.exists'   => 'Jurusan yang dipilih tidak valid.',
            'id_wali_kelas.exists'=> 'Wali kelas yang dipilih tidak valid.',
        ]);

        $idJurusan = $request->id_jurusan;
        $idWaliKelas = $request->id_wali_kelas;
        $oldWaliKelasId = $kelas->id_wali_kelas;

        // Validasi: Cegah guru yang sudah menjadi wali kelas lain dipilih lagi
        if (!empty($idWaliKelas) && $idWaliKelas != $oldWaliKelasId) {
            $isAlreadyWali = Kelas::where('id_wali_kelas', $idWaliKelas)
                ->where('id', '!=', $id)
                ->exists();

            if ($isAlreadyWali) {
                return back()->withErrors(['id_wali_kelas' => 'Guru tersebut sudah menjadi Wali Kelas di kelas lain.'])->withInput();
            }
        }

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
                'role'     => 'guru',
                'sub_role' => 'wali_kelas',
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
