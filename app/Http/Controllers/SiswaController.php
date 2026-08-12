<?php

namespace App\Http\Controllers;

use App\Models\Siswa;
use App\Models\Kelas;
use Illuminate\Http\Request;

class SiswaController extends Controller
{
    // 1. Menampilkan daftar semua siswa
    public function index()
    {
        $dataSiswa = Siswa::with('kelas')->get();
        return view('admin.siswa.index', compact('dataSiswa'));
    }

    // 2. Menampilkan form tambah siswa (simpel tanpa desain)
    public function create()
    {
        $dataKelas = Kelas::all();
        return view('admin.siswa.create', compact('dataKelas'));
    }

    // 3. Menyimpan data siswa baru
    public function store(Request $request)
    {
        $request->validate([
            'nis'           => 'nullable|string|max:20',
            'nama_siswa'    => 'required|string|max:100',
            'id_kelas'      => 'nullable|exists:kelas,id_kelas',
            'jenis_kelamin' => 'required|in:L,P',
        ]);

        Siswa::create([
            'nis'           => $request->nis,
            'nama_siswa'    => $request->nama_siswa,
            'id_kelas'      => $request->id_kelas,
            'jenis_kelamin' => $request->jenis_kelamin,
        ]);

        return redirect()->route('siswa.index')->with('success', 'Data siswa berhasil ditambahkan!');
    }

    // 4. Menampilkan form edit siswa
    public function edit($id)
    {
        $siswa = Siswa::findOrFail($id);
        $dataKelas = Kelas::all();
        return view('admin.siswa.edit', compact('siswa', 'dataKelas'));
    }

    // 5. Mengupdate data siswa
    public function update(Request $request, $id)
    {
        $request->validate([
            'nis'           => 'nullable|string|max:20',
            'nama_siswa'    => 'required|string|max:100',
            'id_kelas'      => 'nullable|exists:kelas,id_kelas',
            'jenis_kelamin' => 'required|in:L,P',
        ]);

        $siswa = Siswa::findOrFail($id);
        $siswa->update([
            'nis'           => $request->nis,
            'nama_siswa'    => $request->nama_siswa,
            'id_kelas'      => $request->id_kelas,
            'jenis_kelamin' => $request->jenis_kelamin,
        ]);

        return redirect()->route('siswa.index')->with('success', 'Data siswa berhasil diperbarui!');
    }

    // 6. Menghapus data siswa
    public function destroy($id)
    {
        $siswa = Siswa::findOrFail($id);
        $siswa->delete();

        return redirect()->route('siswa.index')->with('success', 'Data siswa berhasil dihapus!');
    }
}
