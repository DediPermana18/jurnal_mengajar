<?php

namespace Tests\Feature;

use App\Imports\JurusanImport;
use App\Models\Jurusan;
use App\Models\Scopes\TestingDataScope;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class JurusanImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UserSeeder::class);
    }

    private function makeAdminTu(): User
    {
        return User::where('email', 'admin@school.id')->firstOrFail();
    }

    private function makeQaTester(): User
    {
        return User::where('email', 'qa@school.id')->firstOrFail();
    }

    private function dataJurusan10(): array
    {
        return [
            ['AKL', 'Akuntansi & Keuangan Lembaga'],
            ['DKV', 'Desain Komunikasi Visual'],
            ['MPLB', 'Manajemen Perkantoran & Layanan Bisnis'],
            ['PPLG', 'Pengembangan Perangkat Lunak & Gim'],
            ['RPL', 'Rekayasa Perangkat Lunak'],
            ['TBSM', 'Teknik Bisnis & Sepeda Motor'],
            ['TEI', 'Teknik Elektronika Industri'],
            ['TITL', 'Teknik Instalasi Tenaga Listrik'],
            ['TKJ', 'Teknik Komputer & Jaringan'],
            ['TOKR', 'Teknik Otomotif Kendaraan Ringan'],
        ];
    }

    private function writeCsv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'import_').'.csv';
        $fh = fopen($path, 'w');

        foreach ($rows as $row) {
            fputcsv($fh, $row);
        }

        fclose($fh);

        return $path;
    }

    private function writeXlsx(Spreadsheet $spreadsheet): string
    {
        $path = tempnam(sys_get_temp_dir(), 'import_').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    /** Baris CSV template: NO, KODE JURUSAN, NAMA JURUSAN (+ data). */
    private function templateRows(array $data): array
    {
        $rows = [['NO', 'KODE JURUSAN', 'NAMA JURUSAN']];
        $no = 1;
        foreach ($data as [$kode, $nama]) {
            $rows[] = [$no++, $kode, $nama];
        }

        return $rows;
    }

    private function upload(string $path, string $fieldName = 'file_jurusan'): UploadedFile
    {
        $mime = str_ends_with($path, '.xlsx')
            ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            : 'text/csv';

        return new UploadedFile($path, basename($path), $mime, null, true);
    }

    private function visibleJurusanCount(): int
    {
        return Jurusan::query()
            ->withoutGlobalScope(TestingDataScope::class)
            ->where('is_testing_data', 0)
            ->count();
    }

    public function test_controller_imports_xlsx_data_jurusan(): void
    {
        $admin = $this->makeAdminTu();
        $data = $this->dataJurusan10();

        $spreadsheet = new Spreadsheet;
        $ws = $spreadsheet->getActiveSheet();
        $rows = $this->templateRows($data);
        foreach ($rows as $r => $cells) {
            foreach ($cells as $c => $value) {
                $ws->setCellValue([$c + 1, $r + 1], $value);
            }
        }
        $path = $this->writeXlsx($spreadsheet);
        $file = $this->upload($path);

        $response = $this->actingAs($admin)->post(route('jurusan.import'), [
            'file_jurusan' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', '10 Data Jurusan berhasil diimport!');
        $response->assertSessionMissing('import_warnings');

        $this->assertSame(10, $this->visibleJurusanCount());
        foreach ($data as [$kode, $nama]) {
            $this->assertDatabaseHas('jurusan', [
                'kode_jurusan' => $kode,
                'nama_jurusan' => $nama,
                'is_testing_data' => 0,
            ]);
        }

        $spreadsheet->disconnectWorksheets();
        @unlink($path);
    }

    public function test_controller_accepts_file_field_name(): void
    {
        $admin = $this->makeAdminTu();
        $path = $this->writeCsv($this->templateRows([['AKL', 'Akuntansi & Keuangan Lembaga']]));
        $file = $this->upload($path);

        $this->actingAs($admin)->post(route('jurusan.import'), ['file' => $file])
            ->assertRedirect()
            ->assertSessionHas('success', '1 Data Jurusan berhasil diimport!');

        $this->assertDatabaseHas('jurusan', ['kode_jurusan' => 'AKL', 'is_testing_data' => 0]);

        @unlink($path);
    }

    public function test_controller_accepts_excel_file_field_name(): void
    {
        $admin = $this->makeAdminTu();
        $path = $this->writeCsv($this->templateRows([['DKV', 'Desain Komunikasi Visual']]));
        $file = $this->upload($path);

        $this->actingAs($admin)->post(route('jurusan.import'), ['excel_file' => $file])
            ->assertRedirect()
            ->assertSessionHas('success', '1 Data Jurusan berhasil diimport!');

        $this->assertDatabaseHas('jurusan', ['kode_jurusan' => 'DKV', 'is_testing_data' => 0]);

        @unlink($path);
    }

    public function test_reimport_same_file_is_idempotent(): void
    {
        $admin = $this->makeAdminTu();
        $path = $this->writeCsv($this->templateRows($this->dataJurusan10()));
        $file = $this->upload($path);

        // Import pertama → semua dibuat.
        $this->actingAs($admin)->post(route('jurusan.import'), ['file_jurusan' => $file])
            ->assertSessionHas('success', '10 Data Jurusan berhasil diimport!');
        $this->assertSame(10, $this->visibleJurusanCount());

        // Import kedua → tidak ada duplikat, semua diperbarui.
        $file2 = $this->upload($path);
        $this->actingAs($admin)->post(route('jurusan.import'), ['file_jurusan' => $file2])
            ->assertSessionHas('success', 'Import jurusan berhasil! 0 jurusan baru dibuat, 10 jurusan diperbarui.');
        $this->assertSame(10, $this->visibleJurusanCount(), 'tidak boleh ada duplikat setelah re-import');
        $this->assertSame(10, Jurusan::query()
            ->withoutGlobalScope(TestingDataScope::class)
            ->count());

        @unlink($path);
    }

    public function test_import_restores_soft_deleted_jurusan_without_duplicate_error(): void
    {
        $admin = $this->makeAdminTu();
        $this->actingAs($admin);
        $jurusan = Jurusan::create([
            'kode_jurusan' => 'AKL',
            'nama_jurusan' => 'Akuntansi Lama',
        ]);
        $jurusan->delete();
        $this->assertTrue($jurusan->trashed());

        $path = $this->writeCsv($this->templateRows([['AKL', 'Akuntansi & Keuangan Lembaga']]));
        $file = $this->upload($path);

        // Sebelum perbaikan: firstOrCreate menabrak unique index soft-deleted →
        // UniqueConstraintViolationException "Duplicate entry AKL" → import gagal.
        $this->actingAs($admin)->post(route('jurusan.import'), ['file_jurusan' => $file])
            ->assertRedirect()
            ->assertSessionHas('success', 'Import jurusan berhasil! 0 jurusan baru dibuat, 1 jurusan diperbarui.')
            ->assertSessionMissing('error');

        $restored = Jurusan::query()
            ->withoutGlobalScope(TestingDataScope::class)
            ->where('kode_jurusan', 'AKL')
            ->firstOrFail();
        $this->assertFalse($restored->trashed(), 'jurusan soft-deleted harus dipulihkan');
        $this->assertSame('Akuntansi & Keuangan Lembaga', $restored->nama_jurusan);
        $this->assertFalse((bool) $restored->is_testing_data);

        @unlink($path);
    }

    public function test_import_uses_positional_fallback_for_non_standard_headers(): void
    {
        $admin = $this->makeAdminTu();

        // Header tidak menghasilkan slug kode_jurusan / nama_jurusan
        // ("Nomor,Kode,Nama") → importer memakai fallback posisi kolom B/C.
        $path = $this->writeCsv([
            ['Nomor', 'Kode', 'Nama'],
            [1, 'RPL', 'Rekayasa Perangkat Lunak'],
            [2, 'TKJ', 'Teknik Komputer & Jaringan'],
        ]);
        $file = $this->upload($path);

        $this->actingAs($admin)->post(route('jurusan.import'), ['file_jurusan' => $file])
            ->assertRedirect()
            ->assertSessionHas('success', '2 Data Jurusan berhasil diimport!')
            ->assertSessionMissing('import_warnings');

        $rpl = Jurusan::query()
            ->withoutGlobalScope(TestingDataScope::class)
            ->where('kode_jurusan', 'RPL')
            ->firstOrFail();
        $this->assertSame('Rekayasa Perangkat Lunak', $rpl->nama_jurusan);
        $tkj = Jurusan::query()
            ->withoutGlobalScope(TestingDataScope::class)
            ->where('kode_jurusan', 'TKJ')
            ->firstOrFail();
        $this->assertSame('Teknik Komputer & Jaringan', $tkj->nama_jurusan);

        @unlink($path);
    }

    public function test_importer_skips_empty_rows_with_rows_errors(): void
    {
        $path = $this->writeCsv([
            ['NO', 'KODE JURUSAN', 'NAMA JURUSAN'],
            [1, 'RPL', 'Rekayasa Perangkat Lunak'],
            [2, '', ''],
            [3, 'TKJ', 'Teknik Komputer & Jaringan'],
        ]);

        $importer = new JurusanImport;
        Excel::import($importer, $path);

        $this->assertSame(2, $importer->importedCount);
        $this->assertSame(0, $importer->updatedCount);
        $this->assertSame(1, $importer->skippedCount);
        $this->assertNotEmpty($importer->rowErrors);

        @unlink($path);
    }

    public function test_import_menghormati_konteks_impersonasi_switch_view(): void
    {
        // QA Tester dengan Switch View As Admin TU → data masuk partisi REAL
        // (diselaraskan dengan role yang sedang dilihat).
        $qa = $this->makeQaTester();
        $this->actingAs($qa);
        session(['active_role' => 'admin_tu']);

        $path = $this->writeCsv($this->templateRows([['RPL', 'Rekayasa Perangkat Lunak']]));
        $file = $this->upload($path);

        $this->post(route('jurusan.import'), ['file_jurusan' => $file])
            ->assertRedirect()
            ->assertSessionHas('success', '1 Data Jurusan berhasil diimport!');

        $this->assertSame(1, $this->visibleJurusanCount(), 'QA + Switch View As Admin TU menulis partisi real');
        $this->assertSame(0, Jurusan::query()
            ->withoutGlobalScope(TestingDataScope::class)
            ->where('is_testing_data', 1)
            ->count());

        @unlink($path);
    }

    public function test_import_petugas_it_murni_menulis_partisi_testing(): void
    {
        $qa = $this->makeQaTester();
        $this->actingAs($qa);

        $path = $this->writeCsv($this->templateRows([['RPL', 'Rekayasa Perangkat Lunak']]));
        $file = $this->upload($path);

        $this->post(route('jurusan.import'), ['file_jurusan' => $file])
            ->assertRedirect()
            ->assertSessionHas('success', '1 Data Jurusan berhasil diimport!');

        $this->assertSame(0, $this->visibleJurusanCount(), 'sandbox QA tidak menulis partisi real');
        $this->assertSame(1, Jurusan::query()
            ->withoutGlobalScope(TestingDataScope::class)
            ->where('is_testing_data', 1)
            ->count());

        @unlink($path);
    }
}
