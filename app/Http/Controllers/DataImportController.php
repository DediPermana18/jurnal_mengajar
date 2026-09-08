<?php

namespace App\Http\Controllers;

use App\Imports\GuruImport;
use App\Imports\KelasImport;
use App\Imports\RuanganImport;
use App\Imports\SiswaImport;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Siswa;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

class DataImportController extends Controller
{
    /**
     * Halaman pusat Import Data (tabbed: Siswa / Guru / Kelas-Jurusan).
     */
    public function index()
    {
        $dataKelas = Kelas::with('jurusan')->orderBy('tingkat')->orderBy('nama_kelas')->get();
        $totalSiswa = Siswa::count();
        $totalGuru = Guru::count();

        return view('admin.import.index', compact('dataKelas', 'totalSiswa', 'totalGuru'));
    }

    /**
     * Menangani upload & import file Excel DAFTAR PRESENSI PESERTA DIDIK.
     */
    public function importSiswa(Request $request)
    {
        $request->validate([
            'file_excel' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
            'id_kelas' => 'nullable|exists:kelas,id',
        ], [
            'file_excel.required' => 'File Excel / CSV wajib dipilih.',
            'file_excel.mimes' => 'Format file harus .xlsx, .xls, atau .csv.',
            'file_excel.max' => 'Ukuran file maksimal 10 MB.',
        ]);

        $idKelas = $request->filled('id_kelas') ? (int) $request->id_kelas : null;

        try {
            $importer = new SiswaImport($idKelas);

            // File .csv / .txt selalu dikunci sebagai CSV reader (deteksi
            // ekstensi Maatwebsite tidak memetakan "txt"), sedangkan .xlsx/.xls
            // dibiarkan terdeteksi otomatis. Baris diproses identik di semua
            // format — termasuk dynamic gender scan.
            $extension = strtolower((string) $request->file('file_excel')->getClientOriginalExtension());
            $readerType = in_array($extension, ['csv', 'txt'], true) ? ExcelFormat::CSV : null;

            Excel::import($importer, $request->file('file_excel'), null, $readerType);

            $imported = $importer->importedCount;
            $skipped = $importer->skippedCount;
            $newKelas = $importer->newKelasCount;
            $errors = $importer->rowErrors;

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
                ->with('error', 'Import gagal: '.$e->getMessage());
        }
    }

    /**
     * Menangani upload & import file data GURU (xlsx / csv).
     * Format mengikuti template ekspor: NO, NIP, NAMA GURU, STATUS.
     * Duplikat NIP di-update (updateOrCreate berbasis nip).
     */
    public function importGuru(Request $request)
    {
        $request->validate([
            'file_guru' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
        ], [
            'file_guru.required' => 'File Excel / CSV wajib dipilih.',
            'file_guru.mimes' => 'Format file harus .xlsx, .xls, atau .csv.',
            'file_guru.max' => 'Ukuran file maksimal 10 MB.',
        ]);

        try {
            $importer = new GuruImport;

            $extension = strtolower((string) $request->file('file_guru')->getClientOriginalExtension());
            $readerType = in_array($extension, ['csv', 'txt'], true) ? ExcelFormat::CSV : null;

            Excel::import($importer, $request->file('file_guru'), null, $readerType);

            $successMsg = "Import guru berhasil! {$importer->importedCount} guru baru dibuat";
            if ($importer->updatedCount > 0) {
                $successMsg .= ", {$importer->updatedCount} guru diperbarui";
            }
            if ($importer->skippedCount > 0) {
                $successMsg .= ", {$importer->skippedCount} baris dilewati";
            }
            $successMsg .= '.';

            $session = redirect()->route('import.index')
                ->with('success', $successMsg);

            if (! empty($importer->rowErrors)) {
                $session = $session->with('import_warnings', $importer->rowErrors);
            }

            return $session;

        } catch (\Throwable $e) {
            return redirect()->route('import.index')
                ->with('error', 'Import guru gagal: '.$e->getMessage());
        }
    }

    /**
     * Menangani upload & import file data KELAS (xlsx / csv).
     * Format mengikuti template ekspor: NO, NAMA KELAS, TINGKAT, JURUSAN.
     */
    public function importKelas(Request $request)
    {
        $request->validate([
            'file_kelas' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
        ], [
            'file_kelas.required' => 'File Excel / CSV wajib dipilih.',
            'file_kelas.mimes' => 'Format file harus .xlsx, .xls, atau .csv.',
            'file_kelas.max' => 'Ukuran file maksimal 10 MB.',
        ]);

        try {
            $importer = new KelasImport;

            $extension = strtolower((string) $request->file('file_kelas')->getClientOriginalExtension());
            $readerType = in_array($extension, ['csv', 'txt'], true) ? ExcelFormat::CSV : null;

            Excel::import($importer, $request->file('file_kelas'), null, $readerType);

            $successMsg = "Import kelas berhasil! {$importer->importedCount} kelas baru dibuat";
            if ($importer->skippedCount > 0) {
                $successMsg .= ", {$importer->skippedCount} baris dilewati";
            }
            $successMsg .= '.';

            $session = redirect()->route('import.index')
                ->with('success', $successMsg);

            if (! empty($importer->rowErrors)) {
                $session = $session->with('import_warnings', $importer->rowErrors);
            }

            return $session;

        } catch (\Throwable $e) {
            return redirect()->route('import.index')
                ->with('error', 'Import kelas gagal: '.$e->getMessage());
        }
    }

    /**
     * Menangani upload & import file data RUANGAN (xlsx / csv).
     * Format mengikuti template: NO, KODE RUANGAN, NAMA RUANGAN, LOKASI / GEDUNG.
     */
    public function importRuangan(Request $request)
    {
        $request->validate([
            'file_ruangan' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
        ], [
            'file_ruangan.required' => 'File Excel / CSV wajib dipilih.',
            'file_ruangan.mimes' => 'Format file harus .xlsx, .xls, atau .csv.',
            'file_ruangan.max' => 'Ukuran file maksimal 10 MB.',
        ]);

        try {
            $importer = new RuanganImport;

            $extension = strtolower((string) $request->file('file_ruangan')->getClientOriginalExtension());
            $readerType = in_array($extension, ['csv', 'txt'], true) ? ExcelFormat::CSV : null;

            Excel::import($importer, $request->file('file_ruangan'), null, $readerType);

            $successMsg = "Import ruangan berhasil! {$importer->importedCount} ruangan baru dibuat";
            if ($importer->updatedCount > 0) {
                $successMsg .= ", {$importer->updatedCount} ruangan diperbarui";
            }
            if ($importer->skippedCount > 0) {
                $successMsg .= ", {$importer->skippedCount} baris dilewati";
            }
            $successMsg .= '.';

            $session = redirect()->route('import.index')
                ->with('success', $successMsg);

            if (! empty($importer->rowErrors)) {
                $session = $session->with('import_warnings', $importer->rowErrors);
            }

            return $session;

        } catch (\Throwable $e) {
            return redirect()->route('import.index')
                ->with('error', 'Import ruangan gagal: '.$e->getMessage());
        }
    }
}
