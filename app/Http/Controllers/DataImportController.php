<?php

namespace App\Http\Controllers;

use App\Imports\Exceptions\KelasNotFoundDuringImport;
use App\Imports\GuruImport;
use App\Imports\JadwalImport;
use App\Imports\KelasImport;
use App\Imports\RuanganImport;
use App\Imports\SiswaImport;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Scopes\TestingDataScope;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

class DataImportController extends Controller
{
    /**
     * Halaman pusat Import Data (tabbed: Siswa / Guru / Kelas-Jurusan).
     */
    public function index()
    {
        // Selaraskan tampilan & hitungan dengan partisi data yang dituju import
        // (real saat user asli Admin TU, atau saat Petugas IT Switch View As
        // Admin TU; testing hanya untuk Petugas IT / QA Tester murni).
        $testing = SiswaImport::isImportTestingContext(request()->user());

        $dataKelas = Kelas::query()
            ->withoutGlobalScope(TestingDataScope::class)
            ->where('is_testing_data', $testing ? 1 : 0)
            ->with('jurusan')
            ->orderBy('tingkat')
            ->orderBy('nama_kelas')
            ->get();

        $totalSiswa = Siswa::query()
            ->withoutGlobalScope(TestingDataScope::class)
            ->where('is_testing_data', $testing ? 1 : 0)
            ->count();

        $totalGuru = Guru::query()
            ->withoutGlobalScope(TestingDataScope::class)
            ->where('is_testing_data', $testing ? 1 : 0)
            ->count();

        $totalJadwal = JadwalPelajaran::query()
            ->withoutGlobalScope(TestingDataScope::class)
            ->where('is_testing_data', $testing ? 1 : 0)
            ->count();

        return view('admin.import.index', compact('dataKelas', 'totalSiswa', 'totalGuru', 'totalJadwal'));
    }

    /**
     * Perpanjang batas waktu eksekusi untuk proses import data besar.
     *
     * Batas default PHP (fpm/php.ini) umumnya 30 detik — jauh di bawah kebutuhan
     * import ribuan baris + hashing bcrypt password default (≈0,5 dtk per akun
     * baru pada bcrypt cost 12). Dipanggil di awal tiap method import.
     */
    private function extendExecutionTime(int $seconds = 300): void
    {
        // set_time_limit() sekaligus me-reset penghitung waktu berjalan (tidak
        // hanya menaikkan nilai max); aman dipanggil per-request.
        if (function_exists('set_time_limit')) {
            @set_time_limit($seconds);
        }

        // Fallback bila set_time_limit diblokir hosting (disable_functions).
        $current = (int) ini_get('max_execution_time');
        if ($current > 0 && $current < $seconds) {
            @ini_set('max_execution_time', (string) $seconds);
        }
    }

    /**
     * Mengunduh contoh file format presensi untuk Import Data Siswa.
     */
    public function downloadTemplateSiswa()
    {
        $path = storage_path('app/public/templates/contoh-format-presensi.xlsx');

        if (! file_exists($path)) {
            abort(404, 'Contoh format presensi tidak ditemukan.');
        }

        return response()->download($path, 'contoh-format-presensi.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Menangani upload & import file Excel DAFTAR PRESENSI PESERTA DIDIK.
     */
    public function importSiswa(Request $request)
    {
        $this->extendExecutionTime();

        $request->validate([
            'file_excel' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
            'id_kelas' => [
                'nullable',
                'integer',
                function ($attribute, $value, $fail) {
                    if ($value === null || $value === '') {
                        return;
                    }

                    // id_kelas harus ada pada tabel kelas (dicari lintas partisi —
                    // import me-resolve kelas lintas partisi dengan partisi target
                    // didahulukan, sehingga kelas real/testing mana pun valid).
                    $exists = Kelas::query()
                        ->withoutGlobalScope(TestingDataScope::class)
                        ->whereKey((int) $value)
                        ->exists();

                    if (! $exists) {
                        $fail('Kelas yang dipilih tidak ditemukan pada konteks data saat ini.');
                    }
                },
            ],
        ], [
            'file_excel.required' => 'File Excel / CSV wajib dipilih.',
            'file_excel.mimes' => 'Format file harus .xlsx, .xls, atau .csv.',
            'file_excel.max' => 'Ukuran file maksimal 10 MB.',
        ]);

        $idKelas = $request->filled('id_kelas') ? (int) $request->id_kelas : null;

        try {
            // Konteks partisi di-tentukan SEKALI di sini (saat request aktif) lalu
            // dikirim via parameter constructor — import tidak boleh bergantung
            // pada session/auth yang mungkin pudar saat pemrosesan berjalan.
            $importUntukTesting = SiswaImport::isImportTestingContext(request()->user());
            $importer = new SiswaImport($idKelas, $importUntukTesting);

            // File .csv / .txt selalu dikunci sebagai CSV reader (deteksi
            // ekstensi Maatwebsite tidak memetakan "txt"), sedangkan .xlsx/.xls
            // dibiarkan terdeteksi otomatis. Baris diproses identik di semua
            // format — termasuk dynamic gender scan.
            $extension = strtolower((string) $request->file('file_excel')->getClientOriginalExtension());
            $readerType = in_array($extension, ['csv', 'txt'], true) ? ExcelFormat::CSV : null;

            // ── STRICT VALIDATION ─────────────────────────────────────────────
            // Seluruh import dibungkus dalam SATU transaksi DB. Bila ada header
            // kelas pada file yang TIDAK terdaftar di Data Master Kelas, exception
            // KelasNotFoundDuringImport dilempar → transaksi di-rollback otomatis
            // → TIDAK ADA baris siswa yang tersimpan parsial (no partial import).
            DB::transaction(function () use ($importer, $request, $readerType) {
                Excel::import($importer, $request->file('file_excel'), null, $readerType);
            });

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

        } catch (KelasNotFoundDuringImport $e) {
            // Pembatalan penuh: ada kelas pada file yang belum terdaftar di Data
            // Master Kelas. Transaksi sudah di-rollback — tampilkan pesan merah
            // informatif di paling atas halaman Import Data.
            return redirect()->route('import.index')
                ->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return redirect()->route('import.index')
                ->with('error', 'Import gagal: '.$e->getMessage());
        }
    }

    /**
     * Menangani upload & import file data GURU (xlsx / csv).
     * Format mengikuti template ekspor: NO, NIP, NAMA GURU, STATUS + opsional
     * PERAN, WALI KELAS, NO HP. Duplikat NIP di-update (updateOrCreate berbasis
     * nip & username); password default akun baru: USERNAME123; peran "Kepsek"
     * dilewati; peran "Wali Kelas" otomatis memetakan kelas.id_wali_kelas.
     */
    public function importGuru(Request $request)
    {
        $this->extendExecutionTime();

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

            // ── TRANSACTION ──────────────────────────────────────────────────
            // Import ribuan baris (plus hashing bcrypt per akun baru) dibungkus
            // SATU transaksi: commit hanya sekali di akhir → overhead query turun
            // drastis; bila ada error di tengah → rollback penuh (no partial).
            DB::transaction(function () use ($importer, $request, $readerType) {
                Excel::import($importer, $request->file('file_guru'), null, $readerType);
            });

            $successMsg = "Import guru berhasil! {$importer->importedCount} guru baru dibuat";
            if ($importer->updatedCount > 0) {
                $successMsg .= ", {$importer->updatedCount} guru diperbarui";
            }
            if ($importer->waliMappedCount > 0) {
                $successMsg .= ", {$importer->waliMappedCount} guru dipetakan sebagai wali kelas";
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
        $this->extendExecutionTime();

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

            DB::transaction(function () use ($importer, $request, $readerType) {
                Excel::import($importer, $request->file('file_kelas'), null, $readerType);
            });

            $successMsg = "Import kelas berhasil! {$importer->importedCount} kelas baru dibuat";
            if ($importer->updatedCount > 0) {
                $successMsg .= ", {$importer->updatedCount} kelas diperbarui";
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
                ->with('error', 'Import kelas gagal: '.$e->getMessage());
        }
    }

    /**
     * Menangani upload & import file data RUANGAN (xlsx / csv).
     * Format mengikuti template: NO, KODE RUANGAN, NAMA RUANGAN, LOKASI / GEDUNG.
     */
    public function importRuangan(Request $request)
    {
        $this->extendExecutionTime();

        $request->validate([
            'file_ruangan' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
        ], [
            'file_ruangan.required' => 'File Excel / CSV wajib dipilih.',
            'file_ruangan.mimes' => 'Format file harus .xlsx, .xls, atau .csv.',
            'file_ruangan.max' => 'Ukuran file maksimal 10 MB.',
        ]);

        try {
            $importer = RuanganImport::createWithAutoDelimiter($request->file('file_ruangan')->getRealPath());

            $extension = strtolower((string) $request->file('file_ruangan')->getClientOriginalExtension());
            $readerType = in_array($extension, ['csv', 'txt'], true) ? ExcelFormat::CSV : null;

            DB::transaction(function () use ($importer, $request, $readerType) {
                Excel::import($importer, $request->file('file_ruangan'), null, $readerType);
            });

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

    /**
     * Menangani upload & import file JADWAL PELAJARAN (xlsx / csv).
     * Format: Kelas, Hari, WaktuMulai, WaktuSelesai, MataPelajaran, Guru, Ruang.
     * Kelas wajib ada di master; Mapel & Ruangan di-auto-create jika belum ada;
     * Guru wajib ada — baris dilewati bila tidak ditemukan.
     */
    public function importJadwal(Request $request)
    {
        $this->extendExecutionTime();

        $request->validate([
            'file_jadwal' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
        ], [
            'file_jadwal.required' => 'File Excel / CSV wajib dipilih.',
            'file_jadwal.mimes'   => 'Format file harus .xlsx, .xls, atau .csv.',
            'file_jadwal.max'     => 'Ukuran file maksimal 10 MB.',
        ]);

        try {
            $importer = JadwalImport::createWithAutoDelimiter(
                $request->file('file_jadwal')->getRealPath()
            );

            $extension = strtolower(
                (string) $request->file('file_jadwal')->getClientOriginalExtension()
            );
            $readerType = in_array($extension, ['csv', 'txt'], true)
                ? ExcelFormat::CSV
                : null;

            DB::transaction(function () use ($importer, $request, $readerType) {
                Excel::import($importer, $request->file('file_jadwal'), null, $readerType);
            });

            $successMsg = "Import jadwal berhasil! {$importer->importedCount} slot baru dibuat";
            if ($importer->updatedCount > 0) {
                $successMsg .= ", {$importer->updatedCount} slot diperbarui";
            }
            if ($importer->skippedCount > 0) {
                $successMsg .= ", {$importer->skippedCount} baris dilewati";
            }
            $successMsg .= '.';

            $session = redirect()->route('import.index')
                ->with('success', $successMsg)
                ->with('active_tab', 'jadwal');

            if (! empty($importer->rowErrors)) {
                $session = $session->with('import_warnings', $importer->rowErrors);
            }

            return $session;

        } catch (\RuntimeException $e) {
            // Kelas tidak ditemukan → rollback otomatis (dalam DB::transaction)
            return redirect()->route('import.index')
                ->with('error', $e->getMessage())
                ->with('active_tab', 'jadwal');
        } catch (\Throwable $e) {
            return redirect()->route('import.index')
                ->with('error', 'Import jadwal gagal: '.$e->getMessage())
                ->with('active_tab', 'jadwal');
        }
    }

    // ══════════════════════════════════════════════════════════════
    // RESET / HAPUS MASAL DATA MASTER (Zona Berbahaya)
    // ══════════════════════════════════════════════════════════════

    /**
     * Partisi data aktif (0 = real, 1 = testing), selaras dengan index()
     * dan SiswaImport — agar reset TIDAK PERNAH menyentuh partisi lain.
     */
    private function resetScope(Request $request): int
    {
        return SiswaImport::isImportTestingContext($request->user()) ? 1 : 0;
    }

    /**
     * Validasi frasa konfirmasi berbahaya — harus diketik utuh (huruf besar).
     */
    private function validateResetConfirmation(Request $request, string $expected): void
    {
        $request->validate([
            'reset_confirm' => [
                'required',
                'string',
                function ($attribute, $value, $fail) use ($expected) {
                    if (mb_strtoupper(trim($value)) !== $expected) {
                        $fail("Frasa konfirmasi tidak cocok. Tulis persis: {$expected}");
                    }
                },
            ],
        ], [
            'reset_confirm.required' => 'Ketik frasa konfirmasi untuk melanjutkan.',
        ]);
    }

    /**
     * Reset semua Data Siswa (beserta presensi/dispensasi/catatan/absensi terkait).
     */
    public function resetSiswa(Request $request)
    {
        $this->validateResetConfirmation($request, 'HAPUS DATA SISWA');
        $scope = $this->resetScope($request);

        $deleted = DB::transaction(function () use ($scope) {
            // Anak-anak siswa dihapus dulu (partisi sama) sebelum tabel induk.
            foreach (['absensi_jurnal', 'catatan_terlambat', 'catatan_siswa_bermasalah', 'dispensasi_siswa', 'presensi_siswa'] as $table) {
                DB::table($table)->where('is_testing_data', $scope)->delete();
            }

            return DB::table('siswa')->where('is_testing_data', $scope)->delete();
        });

        return redirect()->route('import.index')
            ->with('success', 'Seluruh Data Siswa Berhasil Dihapus ('.number_format($deleted).' siswa).');
    }

    /**
     * Reset semua akun Guru (role=guru) beserta riwayat mengajar/piket/izin
     * yang menyandang guru tersebut.
     */
    public function resetGuru(Request $request)
    {
        $this->validateResetConfirmation($request, 'HAPUS DATA GURU');
        $scope = $this->resetScope($request);

        $deleted = DB::transaction(function () use ($scope) {
            $ids = DB::table('users')
                ->where('role', User::ROLE_GURU)
                ->where('is_testing_data', $scope)
                ->pluck('id')
                ->all();

            if (empty($ids)) {
                return 0;
            }

            // Baris dependen yang memuat guru (FK → users: CASCADE / SET NULL)
            // dihapus eksplisit agar deterministik & sesuai partisi.
            DB::table('status_kehadiran_guru')->whereIn('user_id', $ids)->delete();
            DB::table('pengurus_ruangan')->whereIn('user_id', $ids)->delete();
            DB::table('laporan_kendala')->whereIn('user_id', $ids)->delete();
            DB::table('penerima_catatan_terlambat')->whereIn('user_id', $ids)->delete();
            DB::table('catatan_siswa_bermasalah')->whereIn('id_wali_kelas', $ids)->delete();
            DB::table('catatan_terlambat')->whereIn('id_satpam', $ids)->delete();

            DB::table('dispensasi_kolektif')->where(function ($q) use ($ids) {
                $q->whereIn('id_guru', $ids)->orWhereIn('id_guru_piket', $ids);
            })->delete();

            DB::table('dispensasi_siswa')->where(function ($q) use ($ids) {
                $q->whereIn('id_guru', $ids)->orWhereIn('id_guru_piket', $ids);
            })->delete();

            DB::table('izin_guru')->whereIn('user_id', $ids)->delete();
            DB::table('presensi_siswa')->whereIn('id_guru_piket', $ids)->delete();
            DB::table('jadwal_piket')->whereIn('user_id', $ids)->delete();
            DB::table('jadwal_pelajaran')->whereIn('id_guru', $ids)->delete();

            // Jurnal guru (absensi_jurnal ikut ter-cascade via id_jurnal).
            DB::table('jurnal')->whereIn('id_guru', $ids)->delete();

            return DB::table('users')->whereIn('id', $ids)->where('role', User::ROLE_GURU)->delete();
        });

        return redirect()->route('import.index')
            ->with('success', 'Seluruh Data Guru Berhasil Dihapus ('.number_format($deleted).' guru).');
    }

    /**
     * Reset semua Data Kelas & Jurusan (kelas menyeret jadwal pelajaran,
     * jurnal, presensi, dan siswa yang berada di kelas tsb sesuai relasi).
     */
    public function resetKelasJurusan(Request $request)
    {
        $this->validateResetConfirmation($request, 'HAPUS DATA KELAS JURUSAN');
        $scope = $this->resetScope($request);

        $deleted = DB::transaction(function () use ($scope) {
            // Tabel ber-FK ke kelas di partisi sama dibersihkan dulu.
            DB::table('jadwal_pelajaran')->where('is_testing_data', $scope)->delete();
            DB::table('presensi_siswa')->where('is_testing_data', $scope)->delete();
            DB::table('siswa')->where('is_testing_data', $scope)->delete();

            // Lepas referensi kelas pada users (kelas_id → SET NULL).
            DB::table('users')->where('is_testing_data', $scope)->update(['kelas_id' => null]);

            $kelas   = DB::table('kelas')->where('is_testing_data', $scope)->delete();
            $jurusan = DB::table('jurusan')->where('is_testing_data', $scope)->delete();

            return $kelas + $jurusan;
        });

        return redirect()->route('import.index')
            ->with('success', 'Seluruh Data Kelas & Jurusan Berhasil Dihapus ('.number_format($deleted).' baris).');
    }

    /**
     * Reset semua Data Ruangan (referensi pada jadwal pelajaran dinull-kan,
     * pengurus ruangan ikut dihapus).
     */
    public function resetRuangan(Request $request)
    {
        $this->validateResetConfirmation($request, 'HAPUS DATA RUANGAN');
        $scope = $this->resetScope($request);

        $deleted = DB::transaction(function () use ($scope) {
            DB::table('pengurus_ruangan')->where('is_testing_data', $scope)->delete();

            // jadwal_pelajaran.id_ruangan → SET NULL (jadwal tetap tersimpan).
            DB::table('jadwal_pelajaran')->where('is_testing_data', $scope)->update(['id_ruangan' => null]);

            return DB::table('ruangans')->where('is_testing_data', $scope)->delete();
        });

        return redirect()->route('import.index')
            ->with('success', 'Seluruh Data Ruangan Berhasil Dihapus ('.number_format($deleted).' ruangan).');
    }

    /**
     * Reset semua Jadwal Pelajaran sesuai partisi aktif.
     */
    public function resetJadwal(Request $request)
    {
        $this->validateResetConfirmation($request, 'HAPUS DATA JADWAL');
        $scope = $this->resetScope($request);

        $deleted = DB::transaction(function () use ($scope) {
            return DB::table('jadwal_pelajaran')
                ->where('is_testing_data', $scope)
                ->delete();
        });

        return redirect()->route('import.index')
            ->with('success', 'Seluruh Data Jadwal Pelajaran Berhasil Dihapus ('.number_format($deleted).' slot).')
            ->with('active_tab', 'jadwal');
    }
}
