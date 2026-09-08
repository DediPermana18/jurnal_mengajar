<?php

namespace App\Http\Controllers;

use App\Exports\JurusanExport;
use App\Imports\JurusanImport;
use App\Models\Jurusan;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

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

    /**
     * Menampilkan form tambah jurusan baru
     */
    public function create()
    {
        $this->authorizePetugasTU();

        return view('admin.jurusan.create');
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

    /**
     * Menampilkan form edit jurusan
     */
    public function edit($id)
    {
        $this->authorizePetugasTU();

        $jurusan = Jurusan::findOrFail($id);

        return view('admin.jurusan.edit', compact('jurusan'));
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

    /**
     * Export data jurusan — format xlsx (default) atau csv.
     */
    public function export(Request $request)
    {
        $this->authorizePetugasTU();

        $format = $request->input('format', 'xlsx');
        $filename = 'data_jurusan_' . date('Y-m-d_His');

        if ($format === 'csv') {
            return Excel::download(new JurusanExport, $filename . '.csv', ExcelFormat::CSV, [
                'Content-Type' => 'text/csv',
            ]);
        }

        return Excel::download(new JurusanExport, $filename . '.xlsx', ExcelFormat::XLSX);
    }

    /**
     * Import data jurusan dari file Excel / CSV.
     * Format mengikuti template: NO, KODE JURUSAN, NAMA JURUSAN.
     */
    public function import(Request $request)
    {
        $this->authorizePetugasTU();

        $request->validate([
            'file_jurusan' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
        ], [
            'file_jurusan.required' => 'File Excel / CSV wajib dipilih.',
            'file_jurusan.mimes' => 'Format file harus .xlsx, .xls, atau .csv.',
            'file_jurusan.max' => 'Ukuran file maksimal 10 MB.',
        ]);

        try {
            $importer = new JurusanImport;

            $extension = strtolower((string) $request->file('file_jurusan')->getClientOriginalExtension());
            $readerType = in_array($extension, ['csv', 'txt'], true) ? ExcelFormat::CSV : null;

            Excel::import($importer, $request->file('file_jurusan'), null, $readerType);

            $successMsg = "Import jurusan berhasil! {$importer->importedCount} jurusan baru dibuat";
            if ($importer->updatedCount > 0) {
                $successMsg .= ", {$importer->updatedCount} jurusan diperbarui";
            }
            if ($importer->skippedCount > 0) {
                $successMsg .= ", {$importer->skippedCount} baris diproses (baru/update)";
            }
            $successMsg .= '.';

            $session = redirect()->route('jurusan.index')
                ->with('success', $successMsg);

            if (! empty($importer->rowErrors)) {
                $session = $session->with('import_warnings', $importer->rowErrors);
            }

            return $session;

        } catch (\Throwable $e) {
            return redirect()->route('jurusan.index')
                ->with('error', 'Import jurusan gagal: '.$e->getMessage());
        }
    }
}