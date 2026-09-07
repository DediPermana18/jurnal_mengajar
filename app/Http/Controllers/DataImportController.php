<?php

namespace App\Http\Controllers;

use App\Imports\SiswaImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class DataImportController extends Controller
{
    /**
     * Halaman pusat Import Data (tabbed: Siswa / Guru / Kelas-Jurusan).
     */
    public function index()
    {
        $dataKelas  = \App\Models\Kelas::with('jurusan')->orderBy('tingkat')->orderBy('nama_kelas')->get();
        $totalSiswa = \App\Models\Siswa::count();

        return view('admin.import.index', compact('dataKelas', 'totalSiswa'));
    }

    /**
     * Menangani upload & import file Excel DAFTAR PRESENSI PESERTA DIDIK.
     */
    public function importSiswa(Request $request)
    {
        $request->validate([
            'file_excel'  => 'required|file|mimes:xlsx,xls|max:5120',
            'id_kelas'    => 'nullable|exists:kelas,id',
        ], [
            'file_excel.required' => 'File Excel wajib dipilih.',
            'file_excel.mimes'    => 'Format file harus .xlsx atau .xls.',
            'file_excel.max'      => 'Ukuran file maksimal 5 MB.',
        ]);

        $idKelas = $request->filled('id_kelas') ? (int) $request->id_kelas : null;

        try {
            $importer = new SiswaImport($idKelas);
            Excel::import($importer, $request->file('file_excel'));

            $imported   = $importer->importedCount;
            $skipped    = $importer->skippedCount;
            $newKelas   = $importer->newKelasCount;
            $errors     = $importer->rowErrors;

            // Pesan sukses utama
            $successMsg = "Import berhasil! {$imported} siswa diproses";
            if ($skipped > 0) {
                $successMsg .= ", {$skipped} baris dilewati";
            }
            if ($newKelas > 0) {
                $successMsg .= ", dan {$newKelas} kelas baru dibuat";
            }
            $successMsg .= '.';

            $session = redirect()->route('import.index')
                ->with('success', $successMsg);

            // Pesan warning per-baris jika ada
            if (! empty($errors)) {
                $session = $session->with('import_warnings', $errors);
            }

            return $session;

        } catch (\Throwable $e) {
            return redirect()->route('import.index')
                ->with('error', 'Import gagal: ' . $e->getMessage());
        }
    }
}