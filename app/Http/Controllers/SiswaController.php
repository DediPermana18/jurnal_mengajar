<?php

namespace App\Http\Controllers;

use App\Models\Siswa;
use App\Models\Kelas;
use App\Models\Jurusan;
use Illuminate\Http\Request;

class SiswaController extends Controller
{
    /**
     * Menampilkan daftar semua siswa dengan filter & pagination
     */
    public function index(Request $request)
    {
        $query = Siswa::with(['kelas', 'jurusan']);

        // Filter pencarian nama / NISN / NIS
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('nisn', 'like', "%{$search}%")
                  ->orWhere('nis', 'like', "%{$search}%");
            });
        }

        // Filter kelas
        if ($request->filled('id_kelas')) {
            $query->where('id_kelas', $request->id_kelas);
        }

        // Filter jurusan
        if ($request->filled('id_jurusan')) {
            $query->where('id_jurusan', $request->id_jurusan);
        }

        // Filter jenis kelamin
        if ($request->filled('jenis_kelamin')) {
            $query->where('jenis_kelamin', $request->jenis_kelamin);
        }

        $dataSiswa  = $query->orderBy('nama')->paginate(10)->withQueryString();
        $dataKelas  = Kelas::with('jurusan')->orderBy('tingkat')->orderBy('nama_kelas')->get();
        $jurusans   = Jurusan::orderBy('nama_jurusan')->get();
        $totalSiswa = Siswa::count();

        return view('admin.siswa.index', compact('dataSiswa', 'dataKelas', 'jurusans', 'totalSiswa'));
    }

    /**
     * Menampilkan form tambah siswa
     */
    public function create()
    {
        $dataKelas = Kelas::with('jurusan')->orderBy('tingkat')->orderBy('nama_kelas')->get();
        $jurusans  = Jurusan::orderBy('nama_jurusan')->get();
        return view('admin.siswa.create', compact('dataKelas', 'jurusans'));
    }

    /**
     * Menyimpan data siswa baru
     */
    public function store(Request $request)
    {
        $request->validate([
            'nisn'          => 'nullable|string|max:20|unique:siswa,nisn',
            'nis'           => 'nullable|string|max:20',
            'nama'          => 'required|string|max:100',
            'id_kelas'      => 'nullable|exists:kelas,id',
            'id_jurusan'    => 'nullable|exists:jurusan,id',
            'jenis_kelamin' => 'required|in:L,P',
            'status_siswa'  => 'nullable|string|max:20',
        ]);

        $kelas = $request->filled('id_kelas') ? Kelas::find($request->id_kelas) : null;
        $idJurusan = $request->filled('id_jurusan') ? $request->id_jurusan : $kelas?->id_jurusan;

        Siswa::create([
            'nisn'          => $request->nisn,
            'nis'           => $request->nis,
            'nama'          => $request->nama,
            'id_kelas'      => $request->id_kelas,
            'id_jurusan'    => $idJurusan,
            'jenis_kelamin' => $request->jenis_kelamin,
            'status_siswa'  => $request->status_siswa ?? 'Aktif',
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
        $siswa     = Siswa::findOrFail($id);
        $dataKelas = Kelas::with('jurusan')->orderBy('tingkat')->orderBy('nama_kelas')->get();
        $jurusans  = Jurusan::all();

        return view('admin.siswa.edit', compact('siswa', 'dataKelas', 'jurusans'));
    }

    /**
     * Mengupdate data siswa
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'nisn'          => "nullable|string|max:20|unique:siswa,nisn,{$id}",
            'nis'           => 'nullable|string|max:20',
            'nama'          => 'required|string|max:100',
            'id_kelas'      => 'nullable|exists:kelas,id',
            'id_jurusan'    => 'nullable|exists:jurusan,id',
            'jenis_kelamin' => 'required|in:L,P',
            'status_siswa'  => 'nullable|string|max:20',
        ]);

        $siswa = Siswa::findOrFail($id);
        $kelas = $request->filled('id_kelas') ? Kelas::find($request->id_kelas) : null;
        $idJurusan = $request->filled('id_jurusan') ? $request->id_jurusan : $kelas?->id_jurusan;

        $siswa->update([
            'nisn'          => $request->nisn,
            'nis'           => $request->nis,
            'nama'          => $request->nama,
            'id_kelas'      => $request->id_kelas,
            'id_jurusan'    => $idJurusan,
            'jenis_kelamin' => $request->jenis_kelamin,
            'status_siswa'  => $request->status_siswa ?? 'Aktif',
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
}
