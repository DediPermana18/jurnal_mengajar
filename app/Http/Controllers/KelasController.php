<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\Jurusan;
use App\Models\Guru;
use Illuminate\Http\Request;

class KelasController extends Controller
{
    // 1. Menampilkan daftar semua kelas
    public function index()
    {
        $dataKelas = Kelas::with(['jurusan', 'waliKelas'])->get();
        return view('admin.kelas.index', compact('dataKelas'));
    }

    // 2. Menampilkan form tambah kelas (simpel tanpa desain)
    public function create()
    {
        $dataJurusan = Jurusan::all();
        $dataGuru = Guru::all();
        return view('admin.kelas.create', compact('dataJurusan', 'dataGuru'));
    }

    // 3. Menyimpan data kelas baru
    public function store(Request $request)
    {
        $request->validate([
            'nama_kelas'   => 'required|string|max:50',
            'id_jurusan'   => 'nullable|exists:jurusan,id_jurusan',
            'id_guru_wali' => 'nullable|exists:guru,id_guru',
            'jumlah_siswa' => 'nullable|integer|min:0',
        ]);

        Kelas::create([
            'nama_kelas'   => $request->nama_kelas,
            'id_jurusan'   => $request->id_jurusan,
            'id_guru_wali' => $request->id_guru_wali,
            'jumlah_siswa' => $request->jumlah_siswa ?? 0,
        ]);

        return redirect()->route('kelas.index')->with('success', 'Data kelas berhasil ditambahkan!');
    }

    // 4. Menampilkan form edit kelas
    public function edit($id)
    {
        $kelas = Kelas::findOrFail($id);
        $dataJurusan = Jurusan::all();
        $dataGuru = Guru::all();
        return view('admin.kelas.edit', compact('kelas', 'dataJurusan', 'dataGuru'));
    }

    // 5. Mengupdate data kelas
    public function update(Request $request, $id)
    {
        $request->validate([
            'nama_kelas'   => 'required|string|max:50',
            'id_jurusan'   => 'nullable|exists:jurusan,id_jurusan',
            'id_guru_wali' => 'nullable|exists:guru,id_guru',
            'jumlah_siswa' => 'nullable|integer|min:0',
        ]);

        $kelas = Kelas::findOrFail($id);
        $kelas->update([
            'nama_kelas'   => $request->nama_kelas,
            'id_jurusan'   => $request->id_jurusan,
            'id_guru_wali' => $request->id_guru_wali,
            'jumlah_siswa' => $request->jumlah_siswa ?? 0,
        ]);

        return redirect()->route('kelas.index')->with('success', 'Data kelas berhasil diperbarui!');
    }

    // 6. Menghapus data kelas
    public function destroy($id)
    {
        $kelas = Kelas::findOrFail($id);
        $kelas->delete();

        return redirect()->route('kelas.index')->with('success', 'Data kelas berhasil dihapus!');
    }
}
