<?php

namespace Tests\Feature;

use App\Exports\SiswaExport;
use App\Exports\SiswaFlatExport;
use App\Imports\SiswaImport;
use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Tests\TestCase;

class SiswaDualModeTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::create([
            'nama' => 'Admin TU',
            'username' => 'admin_'.Str::random(8),
            'password' => bcrypt('password'),
            'role' => 'admin',
            'sub_role' => 'admin_tu',
            'is_active' => true,
        ]);
    }

    private function writeCsv(array $rows, string $delimiter = ','): string
    {
        $path = tempnam(sys_get_temp_dir(), 'import_').'.csv';
        $fh = fopen($path, 'w');

        foreach ($rows as $row) {
            fputcsv($fh, $row, $delimiter);
        }

        fclose($fh);

        return $path;
    }

    // ─── IMPORT CSV ───────────────────────────────────────────────────────────

    public function test_csv_import_propagates_gender_via_dynamic_scan(): void
    {
        Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'TKJ 1']);

        // Gender ada di kolom tersembunyi/ekstra (kolom F), bukan di kolom E,
        // plus kombinasi 'L' / 'P' / 'Perempuan' — harus tetap terdeteksi di CSV.
        $file = $this->writeCsv([
            ['X TKJ 1'],
            [1, '1000000001', 'Budi Santoso',   '101', '66', 'L'],
            [2, '1000000002', 'Siti Aminah',    '102', '66', 'P'],
            [3, '1000000003', 'Dewi Lestari',   '103', '66', 'Perempuan'],
            [4, '1000000004', 'Rizky',          '104', '66', 'LAKI-LAKI'],
            [5, '1000000005', 'Ani',            '105', '66', 'p'],
        ]);

        $importer = new SiswaImport;
        Excel::import($importer, $file);

        $this->assertEquals(5, $importer->importedCount, 'semua baris CSV harus terimport');
        $this->assertEquals(0, $importer->skippedCount);
        $this->assertEquals('L', Siswa::where('nisn', '1000000001')->first()->jenis_kelamin);
        $this->assertEquals('P', Siswa::where('nisn', '1000000002')->first()->jenis_kelamin);
        $this->assertEquals('P', Siswa::where('nisn', '1000000003')->first()->jenis_kelamin, 'Perempuan → P');
        $this->assertEquals('L', Siswa::where('nisn', '1000000004')->first()->jenis_kelamin, 'LAKI-LAKI → L');
        $this->assertEquals('P', Siswa::where('nisn', '1000000005')->first()->jenis_kelamin, 'huruf kecil p → P');

        @unlink($file);
    }

    public function test_semicolon_csv_with_explicit_no_urut_is_parsed(): void
    {
        Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'RPL 1']);

        // Excel Indonesia sering mengekspor CSV dengan delimiter titik-koma.
        $file = $this->writeCsv([
            ['X RPL 1'],
            [1, '1000000001', 'Agus Wijaya', '201', 'L'],
            [2, '1000000002', 'Putri Ayu',   '202', 'P'],
        ], ';');

        $importer = new SiswaImport;
        Excel::import($importer, $file);

        $this->assertEquals(2, $importer->importedCount);
        $this->assertEquals(0, $importer->skippedCount);
        $this->assertEquals('L', Siswa::where('nisn', '1000000001')->first()->jenis_kelamin);
        $this->assertEquals('P', Siswa::where('nisn', '1000000002')->first()->jenis_kelamin);

        @unlink($file);
    }

    public function test_controller_accepts_csv_upload_and_imports(): void
    {
        $admin = $this->makeAdmin();
        $kelas = Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'TKJ 1']);

        $path = $this->writeCsv([
            ['X TKJ 1'],
            [1, '1000000001', 'Budi Santoso', '101', 'L'],
            [2, '1000000002', 'Siti Aminah',  '102', 'P'],
        ]);

        $file = new UploadedFile($path, 'daftar_siswa.csv', 'text/csv', null, true);

        $response = $this->actingAs($admin)->post(route('import.siswa'), [
            'file_excel' => $file,
            'id_kelas' => $kelas->id,
        ]);

        $response->assertRedirect(route('import.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('siswa', ['nisn' => '1000000001', 'jenis_kelamin' => 'L']);
        $this->assertDatabaseHas('siswa', ['nisn' => '1000000002', 'jenis_kelamin' => 'P']);

        @unlink($path);
    }

    public function test_import_grouped_export_format_skips_headers_and_gaps(): void
    {
        $kelasX = Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'RPL 1']);
        $kelasXII = Kelas::create(['tingkat' => 'XII', 'nama_kelas' => 'TKJ 2']);

        // CSV persis seperti struktur ekspor grup: header kelas, judul kolom,
        // siswa, lalu 2 baris kosong gap antar kelas.
        $file = $this->writeCsv([
            ['KELAS: X RPL 1'],
            ['NO', 'NISN', 'NIS', 'NAMA SISWA', 'JENIS KELAMIN', 'STATUS'],
            [1, '1000000001', '', 'Budi Santoso', 'Laki-laki', 'Aktif'],
            [2, '1000000002', '', 'Siti Aminah', 'Perempuan', 'Aktif'],
            ['', ''],
            ['', ''],
            ['KELAS: XII TKJ 2'],
            ['NO', 'NISN', 'NIS', 'NAMA SISWA', 'JENIS KELAMIN', 'STATUS'],
            [1, '1000000003', '24830', 'Ahmad Yani', 'Laki-laki', 'Aktif'],
            ['', ''],
            ['', ''],
        ]);

        $importer = new SiswaImport;
        Excel::import($importer, $file);

        // 3 siswa valid; setidaknya judul kolom (2) + baris gap dilewati.
        // Catatan: CsvReader kadang menghilangkan baris kosong paling akhir.
        $this->assertEquals(3, $importer->importedCount);
        $this->assertGreaterThanOrEqual(4, $importer->skippedCount);

        $budi = Siswa::where('nisn', '1000000001')->first();
        $this->assertNotNull($budi);
        $this->assertSame('Budi Santoso', $budi->nama, 'nama tidak boleh tertukar dgn kolom NIS');
        $this->assertNull($budi->nis, 'NIS kosong pada file → null');
        $this->assertSame($kelasX->id, $budi->id_kelas);
        $this->assertSame('L', $budi->jenis_kelamin);

        $siti = Siswa::where('nisn', '1000000002')->first();
        $this->assertSame('Siti Aminah', $siti->nama);
        $this->assertSame($kelasX->id, $siti->id_kelas);
        $this->assertSame('P', $siti->jenis_kelamin);

        $ahmad = Siswa::where('nisn', '1000000003')->first();
        $this->assertSame('Ahmad Yani', $ahmad->nama);
        $this->assertSame('24830', $ahmad->nis);
        $this->assertSame($kelasXII->id, $ahmad->id_kelas);
        $this->assertSame('L', $ahmad->jenis_kelamin);

        $this->assertSame(0, $importer->newKelasCount, 'tidak boleh auto-create kelas');
        $this->assertSame([], $importer->rowErrors);

        @unlink($file);
    }

    public function test_roundtrip_exported_grouped_xlsx_reimports_correctly(): void
    {
        $kelasX = Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'RPL 1']);
        $kelasXII = Kelas::create(['tingkat' => 'XII', 'nama_kelas' => 'TKJ 2']);

        Siswa::create(['nisn' => '1000000001', 'nis' => '101', 'nama' => 'Budi Santoso', 'id_kelas' => $kelasX->id, 'jenis_kelamin' => 'L']);
        Siswa::create(['nisn' => '1000000002', 'nis' => '24830', 'nama' => 'Ahmad Yani', 'id_kelas' => $kelasXII->id, 'jenis_kelamin' => 'L']);

        $path = 'test_roundtrip_'.Str::random(6).'.xlsx';
        Excel::store(new SiswaExport, $path);

        // Kosongkan tabel supaya import benar-benar berasal dari file ekspor.
        Siswa::withTrashed()->get()->each->forceDelete();

        $importer = new SiswaImport;
        Excel::import($importer, Storage::disk('local')->path($path));

        $this->assertEquals(2, $importer->importedCount);
        $this->assertSame([], $importer->rowErrors, 'tidak boleh ada baris error/corrupt');

        $budi = Siswa::where('nisn', '1000000001')->first();
        $this->assertNotNull($budi);
        $this->assertSame('Budi Santoso', $budi->nama);
        $this->assertSame('101', $budi->nis);
        $this->assertSame($kelasX->id, $budi->id_kelas);

        $ahmad = Siswa::where('nisn', '1000000002')->first();
        $this->assertNotNull($ahmad);
        $this->assertSame('Ahmad Yani', $ahmad->nama);
        $this->assertSame('24830', $ahmad->nis);
        $this->assertSame($kelasXII->id, $ahmad->id_kelas);

        $this->assertSame(2, Siswa::count(), 'tidak ada entri kosong/corrupt');

        Storage::disk('local')->delete($path);
    }

    public function test_roundtrip_exported_grouped_csv_reimports_correctly(): void
    {
        $kelasX = Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'AK 1']);
        $kelasXII = Kelas::create(['tingkat' => 'XII', 'nama_kelas' => 'TKJ 2']);

        Siswa::create(['nisn' => '1000000001', 'nis' => '101', 'nama' => 'Budi Santoso', 'id_kelas' => $kelasX->id, 'jenis_kelamin' => 'L']);
        Siswa::create(['nisn' => '1000000002', 'nis' => '24830', 'nama' => 'Ahmad Yani', 'id_kelas' => $kelasXII->id, 'jenis_kelamin' => 'L']);

        $path = 'test_roundtrip_csv_'.Str::random(6).'.csv';
        Excel::store(new SiswaFlatExport, $path);

        // Kosongkan tabel supaya import benar-benar berasal dari file ekspor.
        Siswa::withTrashed()->get()->each->forceDelete();

        $importer = new SiswaImport;
        Excel::import($importer, Storage::disk('local')->path($path));

        $this->assertEquals(2, $importer->importedCount);
        $this->assertSame([], $importer->rowErrors, 'header & baris gap harus dilewati tanpa error');

        $this->assertSame('Budi Santoso', Siswa::where('nisn', '1000000001')->first()->nama);
        $this->assertSame('Ahmad Yani', Siswa::where('nisn', '1000000002')->first()->nama);
        $this->assertSame(2, Siswa::count());

        Storage::disk('local')->delete($path);
    }

    // ─── EXPORT XLSX & CSV ───────────────────────────────────────────────────

    public function test_export_downloads_xlsx_and_csv_with_expected_names(): void
    {
        Excel::fake();

        $admin = $this->makeAdmin();
        $kelas = Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'TKJ 1']);

        Siswa::create(['nisn' => '1000000001', 'nis' => '101', 'nama' => 'Budi Santoso', 'id_kelas' => $kelas->id, 'jenis_kelamin' => 'L']);
        Siswa::create(['nisn' => '1000000002', 'nis' => '102', 'nama' => 'Siti Aminah',  'id_kelas' => $kelas->id, 'jenis_kelamin' => 'P']);

        $this->actingAs($admin)->get(route('siswa.export', ['format' => 'xlsx']))->assertOk();
        $this->actingAs($admin)->get(route('siswa.export', ['format' => 'csv']))->assertOk();

        $prefix = 'data_siswa_'.date('Y-m-d');

        Excel::assertDownloaded($prefix.'.xlsx', function (SiswaExport $export) {
            $sheets = $export->sheets();
            $this->assertCount(3, $sheets);
            $this->assertSame(['KELAS X', 'KELAS XI', 'KELAS XII'], array_map(fn ($s) => $s->title(), $sheets));

            // Satu kelas (X TKJ 1, 2 siswa): header + judul kolom + 2 baris siswa + 2 baris gap.
            $rows = $sheets[0]->collection()->all();
            $this->assertCount(6, $rows);
            $this->assertSame(['KELAS: X TKJ 1'], $rows[0]);
            $this->assertSame(['NO', 'NISN', 'NIS', 'NAMA SISWA', 'JENIS KELAMIN', 'STATUS'], $rows[1]);
            $this->assertSame([1, '1000000001', '101', 'Budi Santoso', 'Laki-laki', 'Aktif'], $rows[2]);
            $this->assertSame([2, '1000000002', '102', 'Siti Aminah', 'Perempuan', 'Aktif'], $rows[3]);
            $this->assertSame([''], $rows[4]);
            $this->assertSame([''], $rows[5]);

            $this->assertCount(0, $sheets[1]->collection());
            $this->assertCount(0, $sheets[2]->collection());

            return true;
        });

        Excel::assertDownloaded($prefix.'.csv', function (SiswaFlatExport $export) {
            $rows = $export->collection()->all();

            // Satu kelas (X TKJ 1, 2 siswa): header + judul + 2 siswa + 2 gap.
            $this->assertCount(6, $rows);
            $this->assertSame(['KELAS: X TKJ 1'], $rows[0]);
            $this->assertSame(['NO', 'NISN', 'NIS', 'NAMA SISWA', 'JENIS KELAMIN', 'STATUS'], $rows[1]);
            $this->assertSame([1, '1000000001', '101', 'Budi Santoso', 'Laki-laki', 'Aktif'], $rows[2]);
            $this->assertSame([2, '1000000002', '102', 'Siti Aminah', 'Perempuan', 'Aktif'], $rows[3]);
            $this->assertSame([''], $rows[4]);
            $this->assertSame([''], $rows[5]);

            return true;
        });
    }

    public function test_xlsx_export_groups_students_by_grade_levels(): void
    {
        Excel::fake();

        $admin = $this->makeAdmin();
        $kelasXAkl = Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'AKL 1']);
        $kelasXRpl = Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'RPL 2']);
        Kelas::create(['tingkat' => 'XI', 'nama_kelas' => 'MP 1']);
        $kelasXII = Kelas::create(['tingkat' => 'XII', 'nama_kelas' => 'DKV 2']);

        Siswa::create(['nisn' => '1000000001', 'nis' => '101', 'nama' => 'Andi',   'id_kelas' => $kelasXAkl->id, 'jenis_kelamin' => 'L']);
        Siswa::create(['nisn' => '1000000002', 'nis' => '102', 'nama' => 'Budi',   'id_kelas' => $kelasXAkl->id, 'jenis_kelamin' => 'L']);
        Siswa::create(['nisn' => '1000000003', 'nis' => '103', 'nama' => 'Citra',  'id_kelas' => $kelasXRpl->id, 'jenis_kelamin' => 'P']);
        Siswa::create(['nisn' => '1000000004', 'nis' => '104', 'nama' => 'Dewi',   'id_kelas' => $kelasXII->id, 'jenis_kelamin' => 'P']);

        $this->actingAs($admin)->get(route('siswa.export', ['format' => 'xlsx']))->assertOk();

        Excel::assertDownloaded('data_siswa_'.date('Y-m-d').'.xlsx', function (SiswaExport $export) {
            $sheets = $export->sheets();

            $this->assertCount(3, $sheets);
            $this->assertSame('KELAS X', $sheets[0]->title());
            $this->assertSame('KELAS XI', $sheets[1]->title());
            $this->assertSame('KELAS XII', $sheets[2]->title());

            // KELAS X: 2 grup kelas → header+judul+siswa tiap grup (grup AKL 1: 2 siswa, RPL 2: 1 siswa) + gap.
            $rowsX = $sheets[0]->collection()->all();
            $this->assertCount(11, $rowsX);
            $this->assertSame(['KELAS: X AKL 1'], $rowsX[0]);
            $this->assertSame([1, '1000000001', '101', 'Andi', 'Laki-laki', 'Aktif'], $rowsX[2]);
            $this->assertSame([2, '1000000002', '102', 'Budi', 'Laki-laki', 'Aktif'], $rowsX[3]);
            $this->assertSame(['KELAS: X RPL 2'], $rowsX[6]);
            $this->assertSame([1, '1000000003', '103', 'Citra', 'Perempuan', 'Aktif'], $rowsX[8], 'NO direset ke 1 untuk tiap kelas');

            $this->assertCount(0, $sheets[1]->collection());

            $rowsXii = $sheets[2]->collection()->all();
            $this->assertCount(5, $rowsXii);
            $this->assertSame(['KELAS: XII DKV 2'], $rowsXii[0]);
            $this->assertSame([1, '1000000004', '104', 'Dewi', 'Perempuan', 'Aktif'], $rowsXii[2]);

            return true;
        });
    }

    public function test_empty_class_contributes_no_rows_to_its_grade_sheet(): void
    {
        Excel::fake();

        $admin = $this->makeAdmin();
        $withStudents = Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'RPL 1']);
        Kelas::create(['tingkat' => 'XI', 'nama_kelas' => 'AKL 1']); // tidak memiliki siswa

        Siswa::create(['nisn' => '1000000001', 'nis' => '101', 'nama' => 'Andi', 'id_kelas' => $withStudents->id, 'jenis_kelamin' => 'L']);

        $this->actingAs($admin)->get(route('siswa.export', ['format' => 'xlsx']))->assertOk();

        Excel::assertDownloaded('data_siswa_'.date('Y-m-d').'.xlsx', function (SiswaExport $export) {
            $sheets = $export->sheets();

            $this->assertCount(3, $sheets);

            $rowsX = $sheets[0]->collection()->all();
            $this->assertCount(5, $rowsX);
            $this->assertSame('KELAS: X RPL 1', $rowsX[0][0]);

            $rowsXi = $sheets[1]->collection()->all();
            $this->assertCount(0, $rowsXi, 'kelas tanpa siswa tidak menghasilkan grup apa pun');

            $this->assertCount(0, $sheets[2]->collection());

            return true;
        });
    }

    public function test_csv_export_groups_students_by_class_with_headers_and_gaps(): void
    {
        Excel::fake();

        $admin = $this->makeAdmin();
        $kelasX = Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'AKL 1']);
        $kelasXII = Kelas::create(['tingkat' => 'XII', 'nama_kelas' => 'DKV 2']);
        Kelas::create(['tingkat' => 'XI', 'nama_kelas' => 'MP 1']); // tanpa siswa → tidak ikut

        Siswa::create(['nisn' => '1000000001', 'nis' => '101', 'nama' => 'Andi',  'id_kelas' => $kelasX->id, 'jenis_kelamin' => 'L']);
        Siswa::create(['nisn' => '1000000002', 'nis' => '102', 'nama' => 'Budi',  'id_kelas' => $kelasX->id, 'jenis_kelamin' => 'L']);
        Siswa::create(['nisn' => '1000000003', 'nis' => '103', 'nama' => 'Citra', 'id_kelas' => $kelasXII->id, 'jenis_kelamin' => 'P']);

        $this->actingAs($admin)->get(route('siswa.export', ['format' => 'csv']))->assertOk();

        Excel::assertDownloaded('data_siswa_'.date('Y-m-d').'.csv', function (SiswaFlatExport $export) {
            $rows = $export->collection()->all();

            // Grup kelas X (2 siswa) ditempatkan sebelum kelas XII (urutan tingkat).
            $this->assertCount(11, $rows);
            $this->assertSame(['KELAS: X AKL 1'], $rows[0]);
            $this->assertSame(['NO', 'NISN', 'NIS', 'NAMA SISWA', 'JENIS KELAMIN', 'STATUS'], $rows[1]);
            $this->assertSame([1, '1000000001', '101', 'Andi', 'Laki-laki', 'Aktif'], $rows[2]);
            $this->assertSame([2, '1000000002', '102', 'Budi', 'Laki-laki', 'Aktif'], $rows[3]);
            $this->assertSame([''], $rows[4]);
            $this->assertSame([''], $rows[5], 'gap 2 baris kosong antar kelas');

            $this->assertSame(['KELAS: XII DKV 2'], $rows[6]);
            $this->assertSame([1, '1000000003', '103', 'Citra', 'Perempuan', 'Aktif'], $rows[8], 'NO direset ke 1 untuk tiap kelas');
            $this->assertSame([''], $rows[9]);
            $this->assertSame([''], $rows[10]);

            return true;
        });
    }

    public function test_generated_csv_file_has_class_grouping_with_blank_gaps(): void
    {
        $kelasX = Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'AK 1']);
        $kelasXII = Kelas::create(['tingkat' => 'XII', 'nama_kelas' => 'TKJ 2']);

        Siswa::create(['nisn' => '1000000001', 'nis' => '101', 'nama' => 'Andi', 'id_kelas' => $kelasX->id, 'jenis_kelamin' => 'L']);
        Siswa::create(['nisn' => '1000000002', 'nis' => '24830', 'nama' => 'Ahmad Yani', 'id_kelas' => $kelasXII->id, 'jenis_kelamin' => 'L']);

        $path = 'test_csv_'.Str::random(6).'.csv';
        Excel::store(new SiswaFlatExport, $path);
        $full = Storage::disk('local')->path($path);

        $lines = file($full);

        // Baris gap antar kelas ditulis sebagai 6 kolom kosong yang dikutip.
        $blank = '"","","","","",""'.PHP_EOL;
        $this->assertContains($blank, $lines, 'grup kelas dipisahkan oleh baris kosong');

        // Semua isi dikutip penuh; header kelas & judul kolom muncul per grup.
        $this->assertContains('"KELAS: X AK 1","","","","",""'.PHP_EOL, $lines);
        $this->assertContains('"KELAS: XII TKJ 2","","","","",""'.PHP_EOL, $lines);
        $this->assertContains('"NO","NISN","NIS","NAMA SISWA","JENIS KELAMIN","STATUS"'.PHP_EOL, $lines);

        // Nomor urut dimulai dari 1 untuk setiap grup kelas.
        $this->assertContains('"1","1000000001","101","Andi","Laki-laki","Aktif"'.PHP_EOL, $lines);
        $this->assertContains('"1","1000000002","24830","Ahmad Yani","Laki-laki","Aktif"'.PHP_EOL, $lines);

        // Letakkan dua grup tersebut dengan tepat satu grup per sesi: 2 header kelas.
        $this->assertSame(2, count(array_filter($lines, fn ($l) => str_starts_with($l, '"KELAS: '))));

        Storage::disk('local')->delete($path);
    }

    public function test_export_defaults_to_xlsx_without_format_param(): void
    {
        Excel::fake();

        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get(route('siswa.export'))->assertOk();

        Excel::assertDownloaded('data_siswa_'.date('Y-m-d').'.xlsx');
    }

    public function test_generated_xlsx_file_has_three_grade_sheet_tabs(): void
    {
        $kelasX = Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'RPL 1']);
        Kelas::create(['tingkat' => 'XI', 'nama_kelas' => 'TKJ 1']); // tanpa siswa → sheet kosong
        $kelasXII = Kelas::create(['tingkat' => 'XII', 'nama_kelas' => 'TKJ 2']);

        Siswa::create(['nisn' => '1000000001', 'nis' => '101', 'nama' => 'Andi',  'id_kelas' => $kelasX->id, 'jenis_kelamin' => 'L']);
        Siswa::create(['nisn' => '1000000002', 'nis' => '102', 'nama' => 'Budi',  'id_kelas' => $kelasXII->id, 'jenis_kelamin' => 'L']);

        $path = 'test_siswa_'.Str::random(6).'.xlsx';
        Excel::store(new SiswaExport, $path);

        $spreadsheet = IOFactory::load(Storage::disk('local')->path($path));

        $this->assertSame(['KELAS X', 'KELAS XI', 'KELAS XII'], $spreadsheet->getSheetNames());

        $sheetX = $spreadsheet->getSheetByName('KELAS X');
        $this->assertSame('KELAS: X RPL 1', $sheetX->getCell('A1')->getValue());
        $this->assertSame('NO', $sheetX->getCell('A2')->getValue());
        $this->assertSame('NISN', $sheetX->getCell('B2')->getValue());
        $this->assertSame('NAMA SISWA', $sheetX->getCell('D2')->getValue());
        $this->assertSame('JENIS KELAMIN', $sheetX->getCell('E2')->getValue());
        $this->assertSame('STATUS', $sheetX->getCell('F2')->getValue());
        $this->assertNull($sheetX->getCell('G2')->getValue(), 'judul kolom grup hanya 6 kolom');
        $this->assertSame('Andi', $sheetX->getCell('D3')->getValue());
        $this->assertEquals(1, $sheetX->getCell('A3')->getValue());
        $this->assertNull($sheetX->getCell('D4')->getValue(), 'baris gap pertama setelah siswa');

        $sheetXi = $spreadsheet->getSheetByName('KELAS XI');
        $this->assertNull($sheetXi->getCell('A1')->getValue(), 'sheet tanpa siswa tidak berisi data apa pun');

        $sheetXii = $spreadsheet->getSheetByName('KELAS XII');
        $this->assertSame('KELAS: XII TKJ 2', $sheetXii->getCell('A1')->getValue());
        $this->assertSame('Budi', $sheetXii->getCell('D3')->getValue());
        $this->assertEquals(1, $sheetXii->getCell('A3')->getValue(), 'nomor urut NO dimulai ulang per kelas');

        $spreadsheet->disconnectWorksheets();
        Storage::disk('local')->delete($path);
    }

    // ─── FORMAT: NISN/NIS teks, perataan, & auto-width ──────────────────

    public function test_generated_xlsx_forces_nisn_nis_as_text_with_centered_alignment_and_autofit(): void
    {
        $kelasX = Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'RPL 1']);
        Kelas::create(['tingkat' => 'XI', 'nama_kelas' => 'TKJ 1']); // tanpa siswa → kosong
        $kelasXII = Kelas::create(['tingkat' => 'XII', 'nama_kelas' => 'TKJ 2']);

        Siswa::create(['nisn' => '1000000001', 'nis' => '101', 'nama' => 'Andi',  'id_kelas' => $kelasX->id, 'jenis_kelamin' => 'L']);
        Siswa::create(['nisn' => '3000000002', 'nis' => '202', 'nama' => 'Sari Wulan', 'id_kelas' => $kelasXII->id, 'jenis_kelamin' => 'P']);

        $path = 'test_siswa_'.Str::random(6).'.xlsx';
        Excel::store(new SiswaExport, $path);

        $spreadsheet = IOFactory::load(Storage::disk('local')->path($path));
        $sheetX = $spreadsheet->getSheetByName('KELAS X');

        // NISN (B) & NIS (C) harus bertipe string/text — bukan angka → bukan scientific.
        $this->assertSame('1000000001', $sheetX->getCell('B3')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheetX->getCell('B3')->getDataType(), 'NISN harus string');
        $this->assertSame('101', $sheetX->getCell('C3')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheetX->getCell('C3')->getDataType(), 'NIS harus string');
        $sheetXII = $spreadsheet->getSheetByName('KELAS XII');
        $this->assertSame('3000000002', $sheetXII->getCell('B3')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheetXII->getCell('B3')->getDataType(), 'NISN 3000000002 juga string');

        // Perataan: NO, NISN, NIS, JENIS KELAMIN, STATUS di tengah; NAMA SISWA rata kiri.
        foreach (['A', 'B', 'C', 'E', 'F'] as $centerCol) {
            $this->assertSame(
                Alignment::HORIZONTAL_CENTER,
                $sheetX->getStyle($centerCol.'3')->getAlignment()->getHorizontal(),
                "kolom {$centerCol} harus di tengah"
            );
        }
        $this->assertSame(Alignment::HORIZONTAL_LEFT, $sheetX->getStyle('D3')->getAlignment()->getHorizontal(), 'NAMA SISWA rata kiri');

        // Auto-size lebar kolom: setelah disimpan, lebar kolom harus terhitung (> 0).
        foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $col) {
            $this->assertGreaterThan(0, $sheetX->getColumnDimension($col)->getWidth(), "lebar kolom {$col} terautofit");
        }

        $spreadsheet->disconnectWorksheets();
        Storage::disk('local')->delete($path);
    }

    public function test_exported_xlsx_can_be_reimported_without_errors(): void
    {
        // State awal: kelas + siswa sama persis dengan isi file export.
        $kelasX = Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'AK 1']);
        $kelasXII = Kelas::create(['tingkat' => 'XII', 'nama_kelas' => 'TKJ 1']);

        Siswa::create(['nisn' => '1000000001', 'nis' => '101', 'nama' => 'Andi',  'id_kelas' => $kelasX->id,   'jenis_kelamin' => 'L']);
        Siswa::create(['nisn' => '1000000002', 'nis' => '102', 'nama' => 'Budi',  'id_kelas' => $kelasX->id,   'jenis_kelamin' => 'L']);
        Siswa::create(['nisn' => '2000000001', 'nis' => '201', 'nama' => 'Citra', 'id_kelas' => $kelasXII->id, 'jenis_kelamin' => 'P']);

        // 1) Export → file xlsx.
        $path = 'roundtrip_'.Str::random(6).'.xlsx';
        Excel::store(new SiswaExport, $path);
        $abs = Storage::disk('local')->path($path);

        // 2) Import ulang file export yang sama (tanpa ubah data).
        $importer = new SiswaImport;
        Excel::import($importer, $abs);

        $this->assertEquals(3, $importer->importedCount, 'seluruh baris siswa di file export harus terimport');
        $this->assertEquals(0, count(array_filter(
            $importer->rowErrors,
            fn ($e) => str_contains($e, 'dilewati') && ! str_contains($e, 'tanpa kelas aktif')
        )), 'tidak boleh ada baris data siswa yang error saat re-import');

        // 3) Data tetap utuh: kelas & atribut sesuai input awal.
        $andi = Siswa::where('nisn', '1000000001')->first();
        $this->assertEquals('Andi', $andi->nama);
        $this->assertEquals('101', $andi->nis);
        $this->assertEquals('AK 1', $andi->kelas->nama_kelas);
        $this->assertEquals('X', $andi->kelas->tingkat);
        $this->assertEquals('L', $andi->jenis_kelamin, 'Laki-laki → L');
        $this->assertEquals('Aktif', $andi->status_siswa);

        $citra = Siswa::where('nisn', '2000000001')->first();
        $this->assertEquals('Citra', $citra->nama);
        $this->assertEquals('201', $citra->nis);
        $this->assertEquals('TKJ 1', $citra->kelas->nama_kelas);
        $this->assertEquals('XII', $citra->kelas->tingkat);
        $this->assertEquals('P', $citra->jenis_kelamin, 'Perempuan → P');

        Storage::disk('local')->delete($path);
    }

    public function test_export_writes_nis_to_column_c_as_text_with_leading_zero_preserved(): void
    {
        $kelasX = Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'AK 1']);

        Siswa::create(['nisn' => '1000000001', 'nis' => '0203101', 'nama' => 'Ahmad Fauzi', 'id_kelas' => $kelasX->id, 'jenis_kelamin' => 'L']);

        $path = 'test_nis_'.Str::random(6).'.xlsx';
        Excel::store(new SiswaExport, $path);

        $spreadsheet = IOFactory::load(Storage::disk('local')->path($path));
        $sheetX = $spreadsheet->getSheetByName('KELAS X');

        // Kolom B & C = NISN & NIS dipetakan dari DB.
        $this->assertSame('NISN', $sheetX->getCell('B2')->getValue());
        $this->assertSame('NIS', $sheetX->getCell('C2')->getValue());

        $this->assertSame('1000000001', $sheetX->getCell('B3')->getValue());
        $this->assertSame('0203101', $sheetX->getCell('C3')->getValue(), 'NIS dengan 0 di depan tetap utuh');
        $this->assertSame(DataType::TYPE_STRING, $sheetX->getCell('C3')->getDataType(), 'NIS harus bertipe teks/string');

        $spreadsheet->disconnectWorksheets();
        Storage::disk('local')->delete($path);
    }

    // ─── UI: UNIFIED UPLOAD (mode switcher Excel/CSV dihapus) ─────────────────

    public function test_import_index_renders_unified_upload_form(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('import.index'));

        $response->assertOk();
        $response->assertSee('Import Data Siswa');
        $response->assertSee('Upload File (Excel / CSV)');
        $response->assertSee('id="dropzoneArea"', false);
        $response->assertSee('id="fileExcelImport"', false);
        $response->assertSee('Unduh Contoh Format Presensi (.xlsx)');
        $response->assertDontSee('Mode Excel');
        $response->assertDontSee('Mode CSV');
    }

    // ─── UI: DEPENDENT DROPDOWN (KELAS ↔ JURUSAN) ────────────────────────────

    public function test_index_renders_dependent_kelas_jurusan_filter(): void
    {
        $admin = $this->makeAdmin();

        $pspt = Jurusan::create(['kode_jurusan' => 'PSPT', 'nama_jurusan' => 'Pspt']);
        $ak = Jurusan::create(['kode_jurusan' => 'AK', 'nama_jurusan' => 'Akutansi']);
        Kelas::create(['tingkat' => 'XI', 'nama_kelas' => 'PSPT 1', 'id_jurusan' => $pspt->id]);
        Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'AK 1', 'id_jurusan' => $ak->id]);
        Kelas::create(['tingkat' => 'XII', 'nama_kelas' => 'Reguler', 'id_jurusan' => null]);

        $response = $this->actingAs($admin)->get(route('siswa.index'));

        $response->assertOk();
        // Logika dependen tersedia di halaman.
        $response->assertSee('siswaFilter', false);
        // Kelas berisi id_jurusan agar kombinasi mustahil (mis. PSPT + jurusan AK)
        // bisa dicegah lewat logika dependen. Label memuat jenjang & nama kelas.
        $response->assertSee('jurusan_id', false);
        $response->assertSee('"jurusan_id":""', false);

        // Filter tetap ter-submit lewat request yang sudah ada.
        $response->assertSee('name="id_kelas"', false);
        $response->assertSee('name="id_jurusan"', false);
    }
}
