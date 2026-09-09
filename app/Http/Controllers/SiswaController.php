<?php

namespace App\Http\Controllers;

use App\Exports\SiswaExport;
use App\Exports\SiswaFlatExport;
use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

class SiswaController extends Controller
{
    /**
     * Export data siswa — format xlsx (default) atau csv.
     */
    public function export(Request $request)
    {
        $format = $request->input('format', 'xlsx');

        if ($format === 'csv') {
            // CSV tidak bisa memuat banyak sheet → semua kelas ditulis berurutan
            // dalam satu sheet, dipisah header kelas & baris kosong.
            return Excel::download(new SiswaFlatExport, 'data_siswa_'.date('Y-m-d').'.csv', ExcelFormat::CSV, [
                'Content-Type' => 'text/csv',
            ]);
        }

        return Excel::download(new SiswaExport, 'data_siswa_'.date('Y-m-d').'.xlsx', ExcelFormat::XLSX);
    }

    /**
     * Cek dukungan SOUNDEX/SUBSTRING_INDEX pada driver DB aktif
     * (MySQL/MariaDB mendukung; SQLite tidak).
     */
    protected static function supportsSoundex(): bool
    {
        return in_array(
            \DB::connection()->getDriverName(),
            ['mysql', 'mariadb'],
            true
        );
    }

    /**
     * Menampilkan daftar semua siswa dengan filter & pagination
     */
    public function index(Request $request)
    {
        $query = Siswa::with(['kelas', 'jurusan']);

        // Filter pencarian nama / NISN / NIS
        if ($request->filled('search')) {
            $search = trim($request->input('search'));

            $query->where(function ($q) use ($search) {
                // Tier 1: Standard text matching across Nama, NISN, dan NIS.
                $q->where('nama', 'LIKE', "%{$search}%")
                    ->orWhere('nisn', 'LIKE', "%{$search}%")
                    ->orWhere('nis', 'LIKE', "%{$search}%");

                // Tier 2: Untuk kata pendek tunggal (mis. "aril"), izinkan
                // SOUNDEX exact equality hanya — BUKAN LIKE. Ini menangkap
                // variasi fonetik ("aril" ≈ "ariel") tanpa kebocoran ke nama
                // tak berhubungan. Nama depan dibandingkan juga karena MariaDB
                // menggabungkan soundex kata-kata ("ARIEL PRATAMA" → 'A641635').
                if (strpos($search, ' ') === false && strlen($search) <= 6
                    && SiswaController::supportsSoundex()) {
                    $q->orWhereRaw('SOUNDEX(nama) = SOUNDEX(?)', [$search])
                        ->orWhereRaw("SOUNDEX(SUBSTRING_INDEX(nama, ' ', 1)) = SOUNDEX(?)", [$search]);
                }
            });
        }

        // Filter kelas
        if ($request->filled('id_kelas')) {
            $query->where('id_kelas', $request->id_kelas);
        }

        // Filter jurusan (mencakup id_jurusan di siswa atau di kelas siswa)
        if ($request->filled('id_jurusan')) {
            $idJurusan = $request->id_jurusan;
            $query->where(function ($q) use ($idJurusan) {
                $q->where('id_jurusan', $idJurusan)
                    ->orWhereHas('kelas', function ($kQ) use ($idJurusan) {
                        $kQ->where('id_jurusan', $idJurusan);
                    });
            });
        }

        // Filter jenis kelamin
        if ($request->filled('jenis_kelamin')) {
            $query->where('jenis_kelamin', $request->jenis_kelamin);
        }

        $dataSiswa = $query->orderBy('nama')->paginate(10)->withQueryString();
        $dataKelas = Kelas::with('jurusan')->orderBy('tingkat')->orderBy('nama_kelas')->get();
        $jurusans = Jurusan::orderBy('nama_jurusan')->get();
        $totalSiswa = Siswa::count();

        return view('admin.siswa.index', compact('dataSiswa', 'dataKelas', 'jurusans', 'totalSiswa'));
    }

    /**
     * Menampilkan form tambah siswa
     */
    public function create()
    {
        $dataKelas = Kelas::with('jurusan')->orderBy('tingkat')->orderBy('nama_kelas')->get();

        return view('admin.siswa.create', compact('dataKelas'));
    }

    /**
     * Menyimpan data siswa baru
     */
    public function store(Request $request)
    {
        $request->validate([
            'nisn' => 'nullable|string|max:20|unique:siswa,nisn',
            'nis' => 'required|string|max:20|unique:siswa,nis',
            'nama' => 'required|string|max:100',
            'id_kelas' => 'nullable|exists:kelas,id',
            'jenis_kelamin' => 'required|in:L,P',
            'status_siswa' => 'nullable|string|max:20',
        ], [
            'nis.required' => 'NIS (Nomor Induk Sekolah) wajib diisi.',
            'nis.max' => 'NIS maksimal :max karakter.',
            'nis.unique' => 'NIS sudah digunakan siswa lain.',
            'nisn.max' => 'NISN maksimal :max karakter.',
            'nisn.unique' => 'NISN sudah digunakan siswa lain.',
        ]);

        $kelas = $request->filled('id_kelas') ? Kelas::find($request->id_kelas) : null;
        // Jurusan mengikuti jurusan kelas yang dipilih secara otomatis
        $idJurusan = $kelas?->id_jurusan;

        Siswa::create([
            'nisn' => $request->nisn,
            'nis' => $request->nis,
            'nama' => $request->nama,
            'id_kelas' => $request->id_kelas,
            'id_jurusan' => $idJurusan,
            'jenis_kelamin' => $request->jenis_kelamin,
            'status_siswa' => $request->status_siswa ?? 'Aktif',
        ]);

        return redirect()->route('siswa.index')->with('success', 'Data siswa berhasil ditambahkan!');
    }

    /**
     * Menampilkan detail siswa
     */
    public function show($id)
    {
        $siswa = Siswa::with('kelas')->findOrFail($id);

        return view('admin.siswa.show', compact('siswa'));
    }

    /**
     * Menampilkan form edit siswa
     */
    public function edit($id)
    {
        $siswa = Siswa::findOrFail($id);
        $dataKelas = Kelas::with('jurusan')->orderBy('tingkat')->orderBy('nama_kelas')->get();

        return view('admin.siswa.edit', compact('siswa', 'dataKelas'));
    }

    /**
     * Mengupdate data siswa
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'nisn' => "nullable|string|max:20|unique:siswa,nisn,{$id}",
            'nis' => "required|string|max:20|unique:siswa,nis,{$id}",
            'nama' => 'required|string|max:100',
            'id_kelas' => 'nullable|exists:kelas,id',
            'jenis_kelamin' => 'required|in:L,P',
            'status_siswa' => 'nullable|string|max:20',
        ], [
            'nis.required' => 'NIS (Nomor Induk Sekolah) wajib diisi.',
            'nis.max' => 'NIS maksimal :max karakter.',
            'nis.unique' => 'NIS sudah digunakan siswa lain.',
            'nisn.max' => 'NISN maksimal :max karakter.',
            'nisn.unique' => 'NISN sudah digunakan siswa lain.',
        ]);

        $siswa = Siswa::findOrFail($id);
        $kelas = $request->filled('id_kelas') ? Kelas::find($request->id_kelas) : null;
        // Jurusan mengikuti jurusan kelas yang dipilih secara otomatis
        $idJurusan = $kelas?->id_jurusan;

        $siswa->update([
            'nisn' => $request->nisn,
            'nis' => $request->nis,
            'nama' => $request->nama,
            'id_kelas' => $request->id_kelas,
            'id_jurusan' => $idJurusan,
            'jenis_kelamin' => $request->jenis_kelamin,
            'status_siswa' => $request->status_siswa ?? 'Aktif',
        ]);

        return redirect()->route('siswa.index')->with('success', 'Data siswa berhasil diperbarui!');
    }

    /**
     * Menghapus data siswa (Soft Delete)
     */
    public function destroy($id)
    {
        $siswa = Siswa::findOrFail($id);
        $siswa->delete();

        return redirect()->route('siswa.index')->with('success', 'Data siswa berhasil dihapus!');
    }

    /**
     * Menghapus SELURUH data siswa secara permanen (hard delete).
     * Hanya dapat diakses oleh Admin / TU.
     */
    public function deleteAll(Request $request)
    {
        // ── Otorisasi: hanya role admin ──────────────────────────────
        /** @var User $user */
        $user = auth()->user();
        if (! $user || ! $user->isAdmin()) {
            abort(403, 'Akses ditolak. Hanya Admin / TU yang dapat menghapus semua data siswa.');
        }

        // ── Validasi kata konfirmasi ─────────────────────────────────
        $request->validate([
            'konfirmasi' => ['required', 'in:HAPUS'],
        ], [
            'konfirmasi.required' => 'Kata konfirmasi wajib diisi.',
            'konfirmasi.in' => 'Konfirmasi tidak valid. Ketik tepat: HAPUS',
        ]);

        // ── Hapus permanen semua siswa (hard delete) ────────────────
        // forceDelete() menghapus baris dari tabel (termasuk yang punya
        // deleted_at), bukan sekadar mengisi timestamp soft-delete.
        Siswa::withTrashed()->forceDelete();

        return redirect()->route('siswa.index')
            ->with('success', 'Semua data siswa berhasil dihapus permanen.');
    }
}
