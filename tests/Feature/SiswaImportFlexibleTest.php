<?php

namespace Tests\Feature;

use App\Imports\SiswaImport;
use App\Models\Kelas;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SiswaImportFlexibleTest extends TestCase
{
    use RefreshDatabase;

    private function makeWorkbook(array $sheets): string
    {
        $spreadsheet = new Spreadsheet();

        foreach ($sheets as $i => $sheet) {
            if ($i === 0) {
                $ws = $spreadsheet->getActiveSheet();
                $ws->setTitle($sheet['title']);
            } else {
                $ws = $spreadsheet->createSheet();
                $ws->setTitle($sheet['title']);
            }

            foreach ($sheet['rows'] as $r => $cells) {
                foreach ($cells as $c => $value) {
                    $ws->setCellValue([$c + 1, $r + 1], $value);
                }
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'import_') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        return $path;
    }

    private function studentRows(int $baseNisn): array
    {
        return [
            [$baseNisn + 0, (string) (1000000000 + $baseNisn), 'Budi Santoso',   (string) (100 + $baseNisn), 'L'],
            [$baseNisn + 1, (string) (2000000000 + $baseNisn), 'Siti Aminah',    (string) (200 + $baseNisn), 'P'],
        ];
    }

    public function test_flexible_header_variants_are_detected(): void
    {
        Kelas::create(['tingkat' => 'X',  'nama_kelas' => 'TKJ 1']);
        Kelas::create(['tingkat' => 'XI', 'nama_kelas' => 'AKL 2']);
        Kelas::create(['tingkat' => 'XII', 'nama_kelas' => 'DKV 1']);

        $file = $this->makeWorkbook([
            [
                'title' => 'KELAS X',
                'rows'  => array_merge(
                    [['X TKJ 1']],
                    $this->studentRows(1)
                ),
            ],
            [
                'title' => 'KELAS XI',
                'rows'  => array_merge(
                    [['Kelas XI AKL 2']],
                    $this->studentRows(20)
                ),
            ],
            [
                'title' => 'KELAS XII',
                'rows'  => array_merge(
                    [['KELAS: XII DKV 1']],
                    $this->studentRows(40)
                ),
            ],
        ]);

        $importer = new SiswaImport;
        Excel::import($importer, $file);

        $this->assertEquals(6, $importer->importedCount, 'semua siswa harus terimport');
        $this->assertEquals(0, $importer->skippedCount, 'tidak ada baris yang dilewati');
        $this->assertEquals(0, $importer->newKelasCount, 'tidak boleh auto-create kelas');
        $this->assertEquals(3, Kelas::count(), 'jumlah kelas tidak berubah');

        $tkj = Siswa::where('nisn', '1000000001')->first();
        $this->assertEquals('TKJ 1', $tkj->kelas->nama_kelas);
        $this->assertEquals('X', $tkj->kelas->tingkat);

        $akl = Siswa::where('nisn', '1000000020')->first();
        $this->assertEquals('AKL 2', $akl->kelas->nama_kelas);
        $this->assertEquals('XI', $akl->kelas->tingkat);

        $dkv = Siswa::where('nisn', '1000000040')->first();
        $this->assertEquals('DKV 1', $dkv->kelas->nama_kelas);
        $this->assertEquals('XII', $dkv->kelas->tingkat);

        @unlink($file);
    }

    public function test_student_rows_are_never_mistaken_for_headers(): void
    {
        Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'TKJ 1']);

        $file = $this->makeWorkbook([
            [
                'title' => 'KELAS X',
                'rows'  => array_merge(
                    [['KELAS : X TKJ 1']],
                    [ // siswa baris kedua punya nama mengandung "TKJ 1" → TIDAK boleh jadi header
                        [1, '1000000001', 'Andi TKJ 1 Pratama', '101', 'L'],
                    ]
                ),
            ],
        ]);

        $importer = new SiswaImport;
        Excel::import($importer, $file);

        $this->assertEquals(1, $importer->importedCount);
        $this->assertEquals(0, $importer->skippedCount);
        $this->assertEquals(1, Siswa::count());
        $this->assertEquals('TKJ 1', Siswa::first()->kelas->nama_kelas);

        @unlink($file);
    }

    public function test_fallback_kelas_option_still_works(): void
    {
        Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'RPL 1']);

        $file = $this->makeWorkbook([
            [
                'title' => 'SHEET TANPA HEADER',
                'rows'  => $this->studentRows(1),
            ],
        ]);

        $importer = new SiswaImport(Kelas::where('nama_kelas', 'RPL 1')->first()->id);
        Excel::import($importer, $file);

        $this->assertEquals(2, $importer->importedCount);
        $this->assertNull($importer->getResolvedIdKelas(), 'fallback bukan header, resolved tetap null');
        $this->assertEquals('RPL 1', Siswa::where('nisn', '1000000001')->first()->kelas->nama_kelas);

        @unlink($file);
    }

    public function test_header_detected_when_class_name_is_in_non_first_cell(): void
    {
        Kelas::create(['tingkat' => 'XI', 'nama_kelas' => 'AKL 2']);

        $file = $this->makeWorkbook([
            [
                'title' => 'SHEET A',
                'rows'  => [
                    ['', 'KELAS', 'XI AKL 2'],
                    ...$this->studentRows(1),
                ],
            ],
        ]);

        $importer = new SiswaImport;
        Excel::import($importer, $file);

        $this->assertEquals(2, $importer->importedCount);
        $this->assertEquals('AKL 2', Siswa::where('nisn', '1000000001')->first()->kelas->nama_kelas);
        $this->assertEquals(0, $importer->newKelasCount);

        @unlink($file);
    }

    public function test_row_without_active_class_logs_row_text_and_is_skipped(): void
    {
        Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'TKJ 1']);
        $file = $this->makeWorkbook([
            [
                'title' => 'TANPA HEADER NO FALLBACK',
                'rows'  => $this->studentRows(1),
            ],
        ]);

        $importer = new SiswaImport;
        Excel::import($importer, $file);

        $this->assertEquals(0, $importer->importedCount);
        $this->assertEquals(2, $importer->skippedCount);
        $this->assertCount(2, $importer->rowErrors);
        $this->assertStringContainsString('rowText=', $importer->rowErrors[0]);
        $this->assertStringContainsString('1000000001', $importer->rowErrors[0], 'rowText harus memuat baris asli utk diagnosis');

        @unlink($file);
    }

    public function test_gender_strict_column_e_l_or_p_only(): void
    {
        Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'TKJ 1']);

        $rows = [
            ['X TKJ 1'],
            [1, '1000000001', 'Budi',         '101', 'L'],
            [2, '1000000002', 'Siti Aminah',  '102', 'P'],
            [3, '1000000003', 'Rina',         '103', 'WANITA'],
            [4, '1000000004', 'Faisal',       '104', 'LAKI'],
            [5, '1000000005', 'Clara',        '105', 'FEMALE'],
        ];

        $file = $this->makeWorkbook([
            ['title' => 'GENDER', 'rows' => $rows],
        ]);

        $importer = new SiswaImport;
        Excel::import($importer, $file);

        $this->assertEquals(5, $importer->importedCount);
        $this->assertEquals(0, $importer->skippedCount);

        // Hanya 'L'/'P' persis yang dipakai; yang lain default laki-laki.
        $expect = [
            '1000000001' => 'L',
            '1000000002' => 'P',
            '1000000003' => 'L',
            '1000000004' => 'L',
            '1000000005' => 'L',
        ];
        foreach ($expect as $nisn => $jk) {
            $this->assertEquals($jk, Siswa::where('nisn', $nisn)->first()->jenis_kelamin, "NISN {$nisn} harus {$jk}");
        }

        @unlink($file);
    }

    public function test_gender_is_detected_in_any_column_not_just_e(): void
    {
        Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'RPL 1']);

        $file = $this->makeWorkbook([
            [
                'title' => 'GENDER KOLOM F',
                'rows'  => [
                    ['X RPL 1'],
                    [1, '1000000001', 'Dewi', '101', '', 'Perempuan'],
                    [2, '1000000002', 'Agus', '102', '', 'LAKI-LAKI'],
                ],
            ],
        ]);

        $importer = new SiswaImport;
        Excel::import($importer, $file);

        $this->assertEquals(2, $importer->importedCount);
        $this->assertEquals(0, $importer->skippedCount);
        $this->assertEquals('P', Siswa::where('nisn', '1000000001')->first()->jenis_kelamin, 'gender ditemukan di kolom F');
        $this->assertEquals('L', Siswa::where('nisn', '1000000002')->first()->jenis_kelamin);

        @unlink($file);
    }

    public function test_gender_scan_ignores_extra_hidden_columns(): void
    {
        Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'TKJ 1']);

        // Kolom tak terduga (mis. '66') muncul SEBELUM gender; gender not found via
        // fixed index → dynamic scan tetap menemukan 'P'/'L' di sel mana pun.
        $file = $this->makeWorkbook([
            [
                'title' => 'EXTRA COL',
                'rows'  => [
                    ['X TKJ 1'],
                    [1, '1000000001', 'AISYAH',   '101', '66', 'P'],
                    [2, '1000000002', 'AATHIFAH', '102', '66', 'P'],
                    [3, '1000000003', 'Budi',     '103', '66', 'L'],
                ],
            ],
        ]);

        $importer = new SiswaImport;
        Excel::import($importer, $file);

        $this->assertEquals(3, $importer->importedCount);
        $this->assertEquals(0, $importer->skippedCount);
        $this->assertEquals('P', Siswa::where('nisn', '1000000001')->first()->jenis_kelamin, 'AISYAH → perempuan walau ada kolom menyembunyikan E');
        $this->assertEquals('P', Siswa::where('nisn', '1000000002')->first()->jenis_kelamin, 'AATHIFAH → perempuan');
        $this->assertEquals('L', Siswa::where('nisn', '1000000003')->first()->jenis_kelamin);

        @unlink($file);
    }

    public function test_unknown_gender_never_drops_row_and_defaults_to_male(): void
    {
        Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'TKJ 1']);

        $file = $this->makeWorkbook([
            [
                'title' => 'GENDER UNKNOWN',
                'rows'  => [
                    ['X TKJ 1'],
                    [1, '1000000001', 'SITI NUR AISYAH', '101', '???'],
                    [2, '1000000002', 'Agus',            '102', '???'],
                    [3, '1000000003', 'Cahyo',           '103', 'LAKI-LAKI'],
                ],
            ],
        ]);

        $importer = new SiswaImport;
        Excel::import($importer, $file);

        $this->assertEquals(3, $importer->importedCount, 'format gender aneh TIDAK boleh drop baris');
        $this->assertEquals(0, $importer->skippedCount);
        $this->assertEquals('L', Siswa::where('nisn', '1000000001')->first()->jenis_kelamin);
        $this->assertEquals('L', Siswa::where('nisn', '1000000002')->first()->jenis_kelamin);
        $this->assertEquals('L', Siswa::where('nisn', '1000000003')->first()->jenis_kelamin, 'LAKI-LAKI bukan L/P → L');

        @unlink($file);
    }

    public function test_perempuan_marked_via_column_e_p(): void
    {
        Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'TKJ 1']);

        $file = $this->makeWorkbook([
            [
                'title' => 'GENDER P',
                'rows'  => [
                    ['X TKJ 1'],
                    [1, '1000000001', 'DEWI LESTARI', '101', 'P'],
                    [2, '1000000002', 'SITI',         '102', 'p'],
                ],
            ],
        ]);

        $importer = new SiswaImport;
        Excel::import($importer, $file);

        $this->assertEquals(2, $importer->importedCount);
        $this->assertEquals(0, $importer->skippedCount);
        $this->assertEquals('P', Siswa::where('nisn', '1000000001')->first()->jenis_kelamin);
        $this->assertEquals('P', Siswa::where('nisn', '1000000002')->first()->jenis_kelamin, 'huruf kecil p → P');

        @unlink($file);
    }

    public function test_weird_source_codes_never_drop_and_do_not_map_name(): void
    {
        Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'TKJ 1']);

        $file = $this->makeWorkbook([
            [
                'title' => 'KODE ANEH',
                'rows'  => [
                    ['X TKJ 1'],
                    [1, '1000000001', 'AAN SYAIFUDIN',    '101', ' / /0001'],
                    [2, '1000000002', 'ABDULLOH MUSYAFA', '102', ' / /0002'],
                    [3, '1000000003', 'DEWI LESTARI',     '103', '/'],
                ],
            ],
        ]);

        $importer = new SiswaImport;
        Excel::import($importer, $file);

        // Tidak ada drop; tanpa fallback nama, semua non-'P' default L.
        $this->assertEquals(3, $importer->importedCount);
        $this->assertEquals(0, $importer->skippedCount);
        $this->assertEquals('L', Siswa::where('nisn', '1000000001')->first()->jenis_kelamin);
        $this->assertEquals('L', Siswa::where('nisn', '1000000002')->first()->jenis_kelamin);
        $this->assertEquals('L', Siswa::where('nisn', '1000000003')->first()->jenis_kelamin);

        @unlink($file);
    }

    public function test_empty_gender_defaults_to_male_only_when_cell_empty(): void
    {
        Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'TKJ 1']);

        $file = $this->makeWorkbook([
            [
                'title' => 'GENDER KOSONG',
                'rows'  => [
                    ['X TKJ 1'],
                    [1, '1000000001', 'Bayu', '101', ''],
                ],
            ],
        ]);

        $importer = new SiswaImport;
        Excel::import($importer, $file);

        $this->assertEquals(1, $importer->importedCount);
        $this->assertEquals('L', Siswa::where('nisn', '1000000001')->first()->jenis_kelamin);

        @unlink($file);
    }

    public function test_export_format_with_kelas_prefix_and_fixed_header_columns(): void
    {
        Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'AK 1']);
        Kelas::create(['tingkat' => 'XI', 'nama_kelas' => 'AK 1']);

        // Struktur PERSIS hasil export (SiswaPerTingkatSheet):
        // Baris 1: "KELAS: X AK 1"
        // Baris 2: NO | NISN | NIS | NAMA SISWA | JENIS KELAMIN | STATUS
        // Baris 3+: data siswa
        $file = $this->makeWorkbook([
            [
                'title' => 'KELAS X',
                'rows'  => [
                    ['KELAS: X AK 1'],
                    ['NO', 'NISN', 'NIS', 'NAMA SISWA', 'JENIS KELAMIN', 'STATUS'],
                    [1, '1234567890', '20231001', 'Ahmad Fauzi', 'Laki-laki', 'Aktif'],
                    [2, '1234567891', '20231002', 'Siti Nurhaliza', 'Perempuan', 'Aktif'],
                ],
            ],
            [
                'title' => 'KELAS XI',
                'rows'  => [
                    ['KELAS: XI AK 1'],
                    ['NO', 'NISN', 'NIS', 'NAMA SISWA', 'JENIS KELAMIN', 'STATUS'],
                    [1, '2234567890', '20241001', 'Rizky Ramadhan', 'Laki-laki', 'Aktif'],
                ],
            ],
        ]);

        $importer = new SiswaImport;
        Excel::import($importer, $file);

        $this->assertEquals(3, $importer->importedCount, 'semua siswa dari format export harus terimport');
        $this->assertEquals(0, count(array_filter(
            $importer->rowErrors,
            fn ($e) => str_contains($e, 'dilewati') && ! str_contains($e, 'tanpa kelas aktif')
        )), 'tidak boleh ada baris data siswa yang error');

        $fauzi = Siswa::where('nisn', '1234567890')->first();
        $this->assertNotNull($fauzi);
        $this->assertEquals('Ahmad Fauzi', $fauzi->nama);
        $this->assertEquals('AK 1', $fauzi->kelas->nama_kelas);
        $this->assertEquals('X', $fauzi->kelas->tingkat);
        $this->assertEquals('20231001', $fauzi->nis);
        $this->assertEquals('L', $fauzi->jenis_kelamin, 'Laki-laki → L');

        $siti = Siswa::where('nisn', '1234567891')->first();
        $this->assertEquals('Siti Nurhaliza', $siti->nama);
        $this->assertEquals('P', $siti->jenis_kelamin, 'Perempuan → P');

        $rizky = Siswa::where('nisn', '2234567890')->first();
        $this->assertEquals('AK 1', $rizky->kelas->nama_kelas);
        $this->assertEquals('XI', $rizky->kelas->tingkat);

        @unlink($file);
    }

    public function test_import_works_when_sheet_name_does_not_match_class(): void
    {
        // Kelas valid ada di DB; nama tab sheet TIDAK memuat nama kelas.
        // Import tetap jalan selama "KELAS:" di cell A1 (Baris 1) valid.
        Kelas::create(['tingkat' => 'XI', 'nama_kelas' => 'AK 1']);

        $file = $this->makeWorkbook([
            [
                'title' => 'DAFTAR SISWA', // arbitrary — bukan "KELAS XI"
                'rows'  => [
                    ['KELAS: XI AK 1'],
                    ['NO', 'NISN', 'NIS', 'NAMA SISWA', 'JENIS KELAMIN', 'STATUS'],
                    [1, '2334567890', '20251001', 'Rizky Ramadhan', 'Laki-laki', 'Aktif'],
                ],
            ],
        ]);

        $importer = new SiswaImport;
        Excel::import($importer, $file);

        $this->assertEquals(1, $importer->importedCount, 'sheet name tidak berpengaruh selama KELAS: di A1 valid');
        $this->assertCount(0, $importer->rowErrors);

        $rizky = Siswa::where('nisn', '2334567890')->first();
        $this->assertNotNull($rizky);
        $this->assertEquals('AK 1', $rizky->kelas->nama_kelas);
        $this->assertEquals('XI', $rizky->kelas->tingkat);

        @unlink($file);
    }

    public function test_tolerant_headers_map_columns_and_status_defaults_to_aktif(): void
    {
        Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'RPL 1']);

        // Header varian toleran: "NAMA" (tanpa SISWA), "JENIS KELAS", "L/P".
        $file = $this->makeWorkbook([
            [
                'title' => 'VARIAN',
                'rows'  => [
                    ['KELAS : X RPL 1'],
                    ['NO', 'NISN', 'NIS', 'NAMA', 'JENIS KELAS', 'L/P', 'STATUS'],
                    [1, '3334567890', '20261001', 'Dewi Lestari', 'Perempuan', '', 'Nonaktif'],
                    [2, '3334567891', '20261002', 'Agus Salim',   'Laki-laki', '', ''],
                ],
            ],
        ]);

        $importer = new SiswaImport;
        Excel::import($importer, $file);

        $this->assertEquals(2, $importer->importedCount, 'header varian harus tetap terimport');

        $dewi = Siswa::where('nisn', '3334567890')->first();
        $this->assertEquals('Dewi Lestari', $dewi->nama);
        $this->assertEquals('P', $dewi->jenis_kelamin, 'Perempuan → P');
        $this->assertEquals('Nonaktif', $dewi->status_siswa, 'status dari kolom STATUS dihormati');

        $agus = Siswa::where('nisn', '3334567891')->first();
        $this->assertEquals('Agus Salim', $agus->nama);
        $this->assertEquals('L', $agus->jenis_kelamin, 'Laki-laki → L');
        $this->assertEquals('Aktif', $agus->status_siswa, 'status kosong → default Aktif');

        @unlink($file);
    }
}