<?php

namespace App\Http\Controllers;

use App\Models\Ruangan;
use App\Models\User;
use Illuminate\Http\Request;

class RuanganController extends Controller
{
    protected function authorizePetugasTU(): void
    {
        $role = auth()->check() ? auth()->user()->role : null;

        abort_if(
            !in_array($role, ['admin_tu', 'admin', 'super_admin'], true),
            403,
            'Akses ditolak. Hanya Petugas TU yang dapat mengelola data ruangan.'
        );
    }

    public function index()
    {
        $this->authorizePetugasTU();

        $dataRuangan = Ruangan::with(['pengurus', 'jadwalPelajaran.kelas'])
            ->orderBy('kode_ruangan')
            ->get();

        $guruList = User::whereIn('role', ['admin', 'guru'])
            ->orderBy('nama')
            ->get();

        return view('admin.ruangan.index', compact('dataRuangan', 'guruList'));
    }

    public function store(Request $request)
    {
        $this->authorizePetugasTU();

        $validated = $request->validate([
            'kode_ruangan' => 'required|string|max:20|unique:ruangans,kode_ruangan',
            'nama_ruangan' => 'required|string|max:100',
            'lokasi'       => 'nullable|string|max:150',
            'pengurus'     => 'nullable|array',
            'pengurus.*'   => 'exists:users,id',
        ], [
            'kode_ruangan.required' => 'Kode ruangan wajib diisi.',
            'kode_ruangan.unique'   => 'Kode ruangan sudah terdaftar.',
            'nama_ruangan.required' => 'Nama ruangan wajib diisi.',
            'pengurus.*.exists'     => 'User yang dipilih tidak valid.',
        ]);

        $pengurusIds = $validated['pengurus'] ?? [];
        unset($validated['pengurus']);

        $ruangan = Ruangan::create($validated);
        $ruangan->pengurus()->sync($pengurusIds);

        return redirect()->route('ruangan.index')->with('success', 'Data Ruangan berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $this->authorizePetugasTU();

        $ruangan = Ruangan::findOrFail($id);

        $validated = $request->validate([
            'kode_ruangan' => 'required|string|max:20|unique:ruangans,kode_ruangan,' . $ruangan->id,
            'nama_ruangan' => 'required|string|max:100',
            'lokasi'       => 'nullable|string|max:150',
            'pengurus'     => 'nullable|array',
            'pengurus.*'   => 'exists:users,id',
        ], [
            'kode_ruangan.required' => 'Kode ruangan wajib diisi.',
            'kode_ruangan.unique'   => 'Kode ruangan sudah terdaftar.',
            'nama_ruangan.required' => 'Nama ruangan wajib diisi.',
            'pengurus.*.exists'     => 'User yang dipilih tidak valid.',
        ]);

        $pengurusIds = $validated['pengurus'] ?? [];
        unset($validated['pengurus']);

        $ruangan->update($validated);
        $ruangan->pengurus()->sync($pengurusIds);

        return redirect()->route('ruangan.index')->with('success', 'Data Ruangan berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $this->authorizePetugasTU();

        $ruangan = Ruangan::withCount('jadwalPelajaran')->findOrFail($id);

        if ($ruangan->jadwal_pelajaran_count > 0) {
            return back()->withErrors([
                'error' => 'Ruangan "' . $ruangan->nama_ruangan . '" tidak dapat dihapus karena masih dipakai di ' . $ruangan->jadwal_pelajaran_count . ' slot jadwal pelajaran.',
            ]);
        }

        $ruangan->pengurus()->detach();
        $ruangan->delete();

        return redirect()->route('ruangan.index')->with('success', 'Data Ruangan berhasil dihapus.');
    }
}
