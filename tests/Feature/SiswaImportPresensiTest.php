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

class SiswaImportPresensiTest extends TestCase
{
    use RefreshDatabase;

    private function makeWorkbook(array $sheets): string
    {
        $spreadsheet = new Spreadsheet;

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

        $path = tempnam(sys_get_temp_dir(), 'import_').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    public function test_presensi_format_with_kelas_and_niss_headers_imports(): void
    {
        $kelasDkv = Kelas::create(['tingkat' => 'XI', 'nama_kelas' => 'DKV 1']);
        $kelasTkj = Kelas::create(['tingkat' => 'XII', 'nama_kelas' => 'TKJ 2']);

        $file = $this->makeWorkbook([
            [
                'title' => 'DAFTAR HADIR',
                'rows' => [
                    ['DAFTAR HADIR PESERTA DIDIK'],
                    ['Kelas : XI DKV 1'],
                    ['Wali Kelas : Budi Santoso'],
                    ['NO', 'NISS', 'NISN', 'NAMA SISWA', 'L/P', 'STATUS'],
                    [1, '25010 / 0742', '1000000001', 'Ahmad Yani',   'L', 'Aktif'],
                    [2, '25011 / 0991', '1000000002', 'Siti Aminah',  'P', 'Aktif'],
                    ['', ''],
                    ['DAFTAR HADIR PESERTA DIDIK'],
                    ['Kelas : XII TKJ 2'],
                    ['NO', 'NISS', 'NISN', 'NAMA SISWA', 'L/P', 'STATUS'],
                    [1, '24830 / 1001', '1000000003', 'Rizky Ramadhan', 'L', 'Nonaktif'],
                    ['Mengetahui,'],
                    ['Kepala Sekolah'],
                ],
            ],
        ]);

        $importer = new SiswaImport;
        Excel::import($importer, $file);

        $this->assertEquals(3, $importer->importedCount, 'ketiga siswa harus terimport');
        $this->assertEquals(0, $importer->newKelasCount);

        $ahmad = Siswa::where('nisn', '1000000001')->first();
        $this->assertNotNull($ahmad);
        $this->assertSame('Ahmad Yani', $ahmad->nama);
        $this->assertSame('25010 / 0742', $ahmad->nis, 'NISS utuh tersimpan ke field nis');
        $this->assertSame($kelasDkv->id, $ahmad->id_kelas, 'kelas di-resolve dari baris "Kelas : XI DKV 1"');
        $this->assertSame('L', $ahmad->jenis_kelamin);

        $siti = Siswa::where('nisn', '1000000002')->first();
        $this->assertSame('P', $siti->jenis_kelamin);

        $rizky = Siswa::where('nisn', '1000000003')->first();
        $this->assertSame($kelasTkj->id, $rizky->id_kelas, 'kelas kedua di-resolve dari baris kelas berikutnya');
        $this->assertSame('Nonaktif', $rizky->status_siswa);

        @unlink($file);
    }

    public function test_nis_variant_headers_are_still_detected_on_any_row(): void
    {
        Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'RPL 1']);

        $file = $this->makeWorkbook([
            [
                'title' => 'PRESENSI',
                'rows' => [
                    ['Kop surat tanpa kelas'],
                    ['Nama Guru : Dewi'],
                    ['Kelas X RPL 1'],
                    ['NO', 'NIS', 'NISN', 'NAMA SISWA', 'JENIS KELAMIN'],
                    [1, '00987', '1234567890', 'Andi Kumar', 'Laki-laki'],
                    [2, '',      '1234567891', 'Bunga',      'Perempuan'],
                ],
            ],
        ]);

        $importer = new SiswaImport;
        Excel::import($importer, $file);

        $this->assertEquals(2, $importer->importedCount);
        $this->assertEquals('00987', Siswa::where('nisn', '1234567890')->first()->nis, 'NIS teks nol-depan utuh dari kolom NIS');
        $this->assertNull(Siswa::where('nisn', '1234567891')->first()->nis, 'NIS kosong → null');

        @unlink($file);
    }
}
