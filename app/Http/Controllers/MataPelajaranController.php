<?php

namespace App\Http\Controllers;

use App\Models\MataPelajaran;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MataPelajaranController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = MataPelajaran::query();

        // Search Filter (Cari Nama Mapel / Kode Mapel)
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_mapel', 'like', "%{$search}%")
                  ->orWhere('kode_mapel', 'like', "%{$search}%");
            });
        }

        // Filter Jenis Mapel
        if ($kelompok = $request->get('kelompok')) {
            $query->where('kelompok', $kelompok);
        }

        $totalMapel = MataPelajaran::count();

        $dataMapel = $query->orderBy('nama_mapel', 'asc')
            ->paginate(10)
            ->appends($request->query());

        $jenisOptions = [
            'Umum',
            'Kejuruan',
            'Muatan Lokal / Ekstra',
        ];

        return view('admin.mata-pelajaran.index', compact('dataMapel', 'totalMapel', 'jenisOptions'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $jenisOptions = [
            'Umum',
            'Kejuruan',
            'Muatan Lokal / Ekstra',
        ];

        return view('admin.mata-pelajaran.create', compact('jenisOptions'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $mapel = MataPelajaran::findOrFail($id);

        $jenisOptions = [
            'Umum',
            'Kejuruan',
            'Muatan Lokal / Ekstra',
        ];

        return view('admin.mata-pelajaran.edit', compact('mapel', 'jenisOptions'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'kode_mapel' => [
                'required',
                'string',
                'max:20',
                Rule::unique('mata_pelajaran', 'kode_mapel')->whereNull('deleted_at'),
            ],
            'nama_mapel' => 'required|string|max:100',
            'kelompok'   => 'required|string|max:100',
            'kkm'        => 'nullable|integer|min:0|max:100',
            'beban_jam'  => 'nullable|integer|min:1|max:40',
        ], [
            'kode_mapel.unique'   => 'Kode Mata Pelajaran sudah digunakan.',
            'kode_mapel.required' => 'Kode Mata Pelajaran wajib diisi.',
            'nama_mapel.required' => 'Nama Mata Pelajaran wajib diisi.',
            'kelompok.required'   => 'Jenis Mapel wajib dipilih.',
        ]);

        MataPelajaran::create([
            'kode_mapel' => strtoupper(trim($validated['kode_mapel'])),
            'nama_mapel' => trim($validated['nama_mapel']),
            'kelompok'   => $validated['kelompok'],
            'kkm'        => $validated['kkm'] ?? 75,
            'beban_jam'  => $validated['beban_jam'] ?? 2,
        ]);

        return redirect()
            ->route('mapel.index')
            ->with('success', 'Mata Pelajaran berhasil ditambahkan.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $mapel = MataPelajaran::findOrFail($id);

        $validated = $request->validate([
            'kode_mapel' => [
                'required',
                'string',
                'max:20',
                Rule::unique('mata_pelajaran', 'kode_mapel')->ignore($mapel->id)->whereNull('deleted_at'),
            ],
            'nama_mapel' => 'required|string|max:100',
            'kelompok'   => 'required|string|max:100',
            'kkm'        => 'nullable|integer|min:0|max:100',
            'beban_jam'  => 'nullable|integer|min:1|max:40',
        ], [
            'kode_mapel.unique'   => 'Kode Mata Pelajaran sudah digunakan.',
            'kode_mapel.required' => 'Kode Mata Pelajaran wajib diisi.',
            'nama_mapel.required' => 'Nama Mata Pelajaran wajib diisi.',
            'kelompok.required'   => 'Jenis Mapel wajib dipilih.',
        ]);

        $mapel->update([
            'kode_mapel' => strtoupper(trim($validated['kode_mapel'])),
            'nama_mapel' => trim($validated['nama_mapel']),
            'kelompok'   => $validated['kelompok'],
            'kkm'        => $validated['kkm'] ?? 75,
            'beban_jam'  => $validated['beban_jam'] ?? 2,
        ]);

        return redirect()
            ->route('mapel.index')
            ->with('success', 'Data Mata Pelajaran berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $mapel = MataPelajaran::findOrFail($id);
        $mapel->delete();

        return redirect()
            ->route('mapel.index')
            ->with('success', 'Mata Pelajaran berhasil dihapus.');
    }
}
