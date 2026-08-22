<?php

namespace App\Http\Controllers;

use App\Models\Jurusan;
use Illuminate\Http\Request;

class JurusanController extends Controller
{
    protected function authorizePetugasTU(): void
    {
        $role = auth()->check() ? auth()->user()->role : null;

        abort_if(
            !in_array($role, ['admin_tu', 'admin', 'super_admin'], true),
            403,
            'Akses ditolak. Hanya Petugas TU yang dapat mengelola data jurusan.'
        );
    }

    public function index()
    {
        $this->authorizePetugasTU();

        $dataJurusan = Jurusan::withCount('kelas')
            ->orderBy('kode_jurusan')
            ->get();

        return view('admin.jurusan.index', compact('dataJurusan'));
    }

    public function store(Request $request)
    {
        $this->authorizePetugasTU();

        $validated = $request->validate([
            'kode_jurusan' => 'required|string|max:20|unique:jurusan,kode_jurusan',
            'nama_jurusan' => 'required|string|max:100',
        ], [
            'kode_jurusan.required' => 'Kode jurusan wajib diisi.',
            'kode_jurusan.unique' => 'Kode jurusan sudah terdaftar.',
            'nama_jurusan.required' => 'Nama jurusan wajib diisi.',
        ]);

        Jurusan::create($validated);

        return redirect()->route('jurusan.index')->with('success', 'Data Jurusan berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $this->authorizePetugasTU();

        $jurusan = Jurusan::findOrFail($id);
        $validated = $request->validate([
            'kode_jurusan' => 'required|string|max:20|unique:jurusan,kode_jurusan,' . $jurusan->id,
            'nama_jurusan' => 'required|string|max:100',
        ], [
            'kode_jurusan.required' => 'Kode jurusan wajib diisi.',
            'kode_jurusan.unique' => 'Kode jurusan sudah terdaftar.',
            'nama_jurusan.required' => 'Nama jurusan wajib diisi.',
        ]);

        $jurusan->update($validated);

        return redirect()->route('jurusan.index')->with('success', 'Data Jurusan berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $this->authorizePetugasTU();

        $jurusan = Jurusan::withCount('kelas')->findOrFail($id);

        if ($jurusan->kelas_count > 0) {
            return back()->withErrors([
                'error' => 'Jurusan "' . $jurusan->nama_jurusan . '" tidak dapat dihapus karena masih digunakan oleh ' . $jurusan->kelas_count . ' kelas.',
            ]);
        }

        $jurusan->delete();

        return redirect()->route('jurusan.index')->with('success', 'Data Jurusan berhasil dihapus.');
    }
}