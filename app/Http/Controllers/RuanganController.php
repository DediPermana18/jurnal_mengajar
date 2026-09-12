<?php

namespace App\Http\Controllers;

use App\Exports\RuanganExport;
use App\Models\Ruangan;
use App\Models\User;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

class RuanganController extends Controller
{
    protected function authorizePetugasTU(): void
    {
        abort_unless(
            $this->isAuthorizedAdminArea(),
            403,
            'Akses ditolak. Hanya Petugas TU yang dapat mengelola data ruangan.'
        );
    }

    public function index(Request $request)
    {
        $this->authorizePetugasTU();

        $query = Ruangan::with(['pengurus', 'jadwalPelajaran.kelas']);

        if ($request->filled('search')) {
            $search = trim($request->string('search'));
            $query->where(function ($ruanganQuery) use ($search) {
                $ruanganQuery->where('kode_ruangan', 'like', "%{$search}%")
                    ->orWhere('nama_ruangan', 'like', "%{$search}%")
                    ->orWhere('lokasi', 'like', "%{$search}%")
                    ->orWhereHas('pengurus', fn ($guruQuery) => $guruQuery->where('nama', 'like', "%{$search}%"));
            });
        }

        $dataRuangan = $query
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
            'lokasi' => 'nullable|string|max:150',
            'pengurus' => 'nullable|array',
            'pengurus.*' => 'exists:users,id',
        ], [
            'kode_ruangan.required' => 'Kode ruangan wajib diisi.',
            'kode_ruangan.unique' => 'Kode ruangan sudah terdaftar.',
            'nama_ruangan.required' => 'Nama ruangan wajib diisi.',
            'pengurus.*.exists' => 'User yang dipilih tidak valid.',
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
            'kode_ruangan' => 'required|string|max:20|unique:ruangans,kode_ruangan,'.$ruangan->id,
            'nama_ruangan' => 'required|string|max:100',
            'lokasi' => 'nullable|string|max:150',
            'pengurus' => 'nullable|array',
            'pengurus.*' => 'exists:users,id',
        ], [
            'kode_ruangan.required' => 'Kode ruangan wajib diisi.',
            'kode_ruangan.unique' => 'Kode ruangan sudah terdaftar.',
            'nama_ruangan.required' => 'Nama ruangan wajib diisi.',
            'pengurus.*.exists' => 'User yang dipilih tidak valid.',
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
                'error' => 'Ruangan "'.$ruangan->nama_ruangan.'" tidak dapat dihapus karena masih dipakai di '.$ruangan->jadwal_pelajaran_count.' slot jadwal pelajaran.',
            ]);
        }

        $ruangan->pengurus()->detach();
        $ruangan->delete();

        return redirect()->route('ruangan.index')->with('success', 'Data Ruangan berhasil dihapus.');
    }

    /**
     * Export data ruangan — format xlsx (default) atau csv.
     */
    public function export(Request $request)
    {
        $this->authorizePetugasTU();

        $format = $request->input('format', 'xlsx');
        $filename = 'data_ruangan_'.date('Y-m-d_His');

        if ($format === 'csv') {
            return Excel::download(new RuanganExport, $filename.'.csv', ExcelFormat::CSV, [
                'Content-Type' => 'text/csv',
            ]);
        }

        return Excel::download(new RuanganExport, $filename.'.xlsx', ExcelFormat::XLSX);
    }
}
