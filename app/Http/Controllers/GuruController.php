<?php

namespace App\Http\Controllers;

use App\Models\Guru;
use Illuminate\Http\Request;

class GuruController extends Controller
{
    // 1. Menampilkan daftar semua guru
    public function index()
    {
        $dataGuru = Guru::all();
        return view('admin.guru.index', compact('dataGuru'));
    }

    // 2. Menampilkan form tambah guru
    public function create()
    {
        return view('admin.guru.create');
    }

    // 3. Menyimpan data guru baru ke database
    public function store(Request $request)
    {
        $request->validate([
            'nip'       => 'nullable|unique:guru,nip',
            'nama_guru' => 'required',
            'no_hp'     => 'nullable',
        ]);

        Guru::create([
            'nip'       => $request->nip,
            'nama_guru' => $request->nama_guru,
            'no_hp'     => $request->no_hp,
        ]);

        return redirect()->route('guru.index')->with('success', 'Data guru berhasil ditambahkan!');
    }

    // 4. Menampilkan form edit guru
    public function edit($id)
    {
        $guru = Guru::findOrFail($id);
        return view('admin.guru.edit', compact('guru'));
    }

    // 5. Mengupdate data guru di database
    public function update(Request $request, $id)
    {
        $request->validate([
            'nip'       => 'nullable|unique:guru,nip,'.$id.',id_guru',
            'nama_guru' => 'required',
            'no_hp'     => 'nullable',
        ]);

        $guru = Guru::findOrFail($id);
        $guru->update([
            'nip'       => $request->nip,
            'nama_guru' => $request->nama_guru,
            'no_hp'     => $request->no_hp,
        ]);

        return redirect()->route('guru.index')->with('success', 'Data guru berhasil diperbarui!');
    }

    // 6. Menghapus data guru (Soft Delete)
    public function destroy($id)
    {
        $guru = Guru::findOrFail($id);
        $guru->delete();

        return redirect()->route('guru.index')->with('success', 'Data guru berhasil dihapus!');
    }
}