<?php

namespace App\Http\Controllers;

use App\Models\Siswa;
use App\Models\Kelas;
use Illuminate\Http\Request;

class SiswaController extends Controller
{
    /**
     * Menampilkan daftar semua siswa dengan filter & pagination
     */
    public function index(Request $request)
    {
        $query = Siswa::with('kelas');

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

        // Filter jenis kelamin
        if ($request->filled('jenis_kelamin')) {
            $query->where('jenis_kelamin', $request->jenis_kelamin);
        }

        $dataSiswa  = $query->orderBy('nama')->paginate(15)->withQueryString();
        $dataKelas  = Kelas::orderBy('nama_kelas')->get();
        $totalSiswa = Siswa::count();

        return view('admin.siswa.index', compact('dataSiswa', 'dataKelas', 'totalSiswa'));
    }

    /**
     * Menampilkan form tambah siswa
     */
    public function create()
    {
        $dataKelas = Kelas::orderBy('nama_kelas')->get();
        return view('admin.siswa.create', compact('dataKelas'));
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
            'jenis_kelamin' => 'required|in:L,P',
            'status_siswa'  => 'nullable|string|max:20',
        ]);

        Siswa::create([
            'nisn'          => $request->nisn,
            'nis'           => $request->nis,
            'nama'          => $request->nama,
            'id_kelas'      => $request->id_kelas,
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
        $dataKelas = Kelas::orderBy('nama_kelas')->get();
        return view('admin.siswa.edit', compact('siswa', 'dataKelas'));
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
            'jenis_kelamin' => 'required|in:L,P',
            'status_siswa'  => 'nullable|string|max:20',
        ]);

        $siswa = Siswa::findOrFail($id);
        $siswa->update([
            'nisn'          => $request->nisn,
            'nis'           => $request->nis,
            'nama'          => $request->nama,
            'id_kelas'      => $request->id_kelas,
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
