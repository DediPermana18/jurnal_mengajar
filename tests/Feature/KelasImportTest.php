<?php

namespace Tests\Feature;

use App\Imports\KelasImport;
use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\Scopes\TestingDataScope;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class KelasImportTest extends TestCase
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

    private function upload(string $path): UploadedFile
    {
        $mime = str_ends_with($path, '.xlsx')
            ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            : 'text/csv';

        return new UploadedFile($path, basename($path), $mime, null, true);
    }

    /** Struktur file: NO, NAMA KELAS, TINGKAT, JURUSAN, WALI KELAS, TOTAL SISWA. */
    private function templateRows(array $data): array
    {
        $rows = [['NO', 'NAMA KELAS', 'TINGKAT', 'JURUSAN', 'WALI KELAS', 'TOTAL SISWA']];
        $no = 1;
        foreach ($data as $row) {
            $rows[] = [$no++, $row[0], $row[1], $row[2] ?? '-', $row[3] ?? '-', $row[4] ?? 0];
        }

        return $rows;
    }

    /** 71 kelas unik lintas tingkat: termasuk 'AK 1' untuk X, XI, dan XII. */
    private function kelasRows71(): array
    {
        $data = [['AK 1', 'X'], ['AK 1', 'XI'], ['AK 1', 'XII']];

        $suffix = 1;
        while (count($data) < 71) {
            $tingkat = ['X', 'XI', 'XII'][$suffix % 3];
            $data[] = ['Rombel '.str_pad((string) $suffix, 2, '0', STR_PAD_LEFT), $tingkat];
            $suffix++;
        }

        return $this->templateRows($data);
    }

    private function visibleKelasCount(): int
    {
        return Kelas::query()
            ->withoutGlobalScope(TestingDataScope::class)
            ->where('is_testing_data', 0)
            ->count();
    }

    public function test_71_kelas_semua_tingkat_tidak_ada_yang_dilewati(): void
    {
        $admin = $this->makeAdminTu();
        $path = $this->writeCsv($this->kelasRows71());
        $file = $this->upload($path);

        $response = $this->actingAs($admin)->post(route('import.kelas'), [
            'file_kelas' => $file,
        ]);

        $response->assertRedirect(route('import.index'));
        $response->assertSessionHas('success', 'Import kelas berhasil! 71 kelas baru dibuat.');
        $response->assertSessionMissing('import_warnings');

        // Seluruh 71 baris masuk — termasuk AK 1 di X, XI, dan XII sekaligus.
        $this->assertSame(71, $this->visibleKelasCount());
        foreach (['X', 'XI', 'XII'] as $tingkat) {
            $this->assertDatabaseHas('kelas', [
                'nama_kelas' => 'AK 1',
                'tingkat' => $tingkat,
                'is_testing_data' => 0,
            ]);
        }

        @unlink($path);
    }

    public function test_duplicate_hanya_dilewati_saat_tingkat_dan_nama_kelas_sama_persis(): void
    {
        $admin = $this->makeAdminTu();
        $path = $this->writeCsv($this->templateRows([
            ['AK 1', 'X'],
            ['AK 1', 'X'],   // duplikat persis (tingkat+nama) → dilewati
            ['AK 1', 'XI'],  // kombinasi berbeda → tetap diproses
        ]));
        $file = $this->upload($path);

        $this->actingAs($admin)->post(route('import.kelas'), [
            'file_kelas' => $file,
        ])->assertRedirect(route('import.index'))
            ->assertSessionHas('success', 'Import kelas berhasil! 2 kelas baru dibuat, 1 baris dilewati.')
            ->assertSessionHas('import_warnings');

        $this->assertSame(2, $this->visibleKelasCount());
        $this->assertSame(1, Kelas::query()
            ->withoutGlobalScope(TestingDataScope::class)
            ->where('nama_kelas', 'AK 1')
            ->where('tingkat', 'X')
            ->count(), 'duplikat persis tingkat + nama_kelas dilewati, hanya 1 baris X AK 1 tersimpan');

        @unlink($path);
    }

    public function test_reimport_kelas_duplikat_dilewati_tanpa_menduplikasi(): void
    {
        $admin = $this->makeAdminTu();
        $path = $this->writeCsv($this->templateRows([
            ['TKJ 1', 'X'],
            ['TKJ 1', 'XI'],
        ]));
        $file = $this->upload($path);

        $this->actingAs($admin)->post(route('import.kelas'), ['file_kelas' => $file])
            ->assertSessionHas('success', 'Import kelas berhasil! 2 kelas baru dibuat.');

        $file2 = $this->upload($path);
        $this->actingAs($admin)->post(route('import.kelas'), ['file_kelas' => $file2])
            ->assertSessionHas('success', 'Import kelas berhasil! 0 kelas baru dibuat, 2 baris dilewati.')
            ->assertSessionHas('import_warnings');

        $this->assertSame(2, $this->visibleKelasCount());

        @unlink($path);
    }

    public function test_kolom_wali_kelas_dan_total_siswa_ditangani(): void
    {
        $admin = $this->makeAdminTu();
        $wali = User::create([
            'nama' => 'Budi Santoso',
            'username' => 'wali.budi',
            'password' => bcrypt('password'),
            'role' => 'guru',
            'sub_role' => 'wali_kelas',
            'is_active' => true,
        ]);

        // TOTAL SISWA diisi angka besar di kolom F — harus diabaikan,
        // hanya NAMA KELAS/TINGKAT/JURUSAN/WALI KELAS yang dipetakan.
        $path = $this->writeCsv($this->templateRows([
            ['DKV 1', 'X', 'DKV', $wali->nama, 99999],
        ]));
        $file = $this->upload($path);

        $this->actingAs($admin)->post(route('import.kelas'), ['file_kelas' => $file])
            ->assertRedirect(route('import.index'))
            ->assertSessionHas('success', 'Import kelas berhasil! 1 kelas baru dibuat.')
            ->assertSessionMissing('import_warnings');

        $kelas = Kelas::query()
            ->withoutGlobalScope(TestingDataScope::class)
            ->where('nama_kelas', 'DKV 1')
            ->firstOrFail();
        $this->assertSame($wali->id, $kelas->id_wali_kelas, 'wali kelas dipetakan dari kolom E');

        @unlink($path);
    }

    public function test_jurusan_belum_terdaftar_dibuat_secara_otomatis(): void
    {
        $admin = $this->makeAdminTu();
        $this->actingAs($admin);
        $this->assertSame(0, Jurusan::withoutGlobalScope(TestingDataScope::class)->count());

        $path = $this->writeCsv($this->templateRows([
            ['TOKR 1', 'X', 'Otomotif'],
        ]));
        $file = $this->upload($path);

        $this->actingAs($admin)->post(route('import.kelas'), ['file_kelas' => $file])
            ->assertSessionHas('success', 'Import kelas berhasil! 1 kelas baru dibuat.');

        $jurusan = Jurusan::withoutGlobalScope(TestingDataScope::class)
            ->where('nama_jurusan', 'Otomotif')
            ->firstOrFail();
        $this->assertSame('OTOMOTIF', $jurusan->kode_jurusan);
        $this->assertSame(
            $jurusan->id,
            Kelas::query()->withoutGlobalScope(TestingDataScope::class)->where('nama_kelas', 'TOKR 1')->firstOrFail()->id_jurusan
        );

        @unlink($path);
    }

    public function test_import_restores_soft_deleted_kelas_tanpa_duplikat(): void
    {
        $admin = $this->makeAdminTu();
        $this->actingAs($admin);
        $kelas = Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'TKJ 1']);
        $kelas->delete();
        $this->assertTrue($kelas->trashed());

        $path = $this->writeCsv($this->templateRows([
            ['TKJ 1', 'X'],
        ]));
        $file = $this->upload($path);

        $this->actingAs($admin)->post(route('import.kelas'), ['file_kelas' => $file])
            ->assertRedirect(route('import.index'))
            ->assertSessionHas('success', 'Import kelas berhasil! 0 kelas baru dibuat, 1 kelas diperbarui.')
            ->assertSessionMissing('error');

        $restored = Kelas::query()
            ->withoutGlobalScope(TestingDataScope::class)
            ->where('tingkat', 'X')
            ->where('nama_kelas', 'TKJ 1')
            ->firstOrFail();
        $this->assertFalse($restored->trashed());
        $this->assertSame(1, $this->visibleKelasCount());

        @unlink($path);
    }

    public function test_import_xlsx_dengan_header_standar(): void
    {
        $admin = $this->makeAdminTu();

        $spreadsheet = new Spreadsheet;
        $ws = $spreadsheet->getActiveSheet();
        $rows = $this->templateRows([
            ['AK 1', 'X'],
            ['AK 1', 'XI'],
            ['AK 1', 'XII'],
        ]);
        foreach ($rows as $r => $cells) {
            foreach ($cells as $c => $value) {
                $ws->setCellValue([$c + 1, $r + 1], $value);
            }
        }
        $path = tempnam(sys_get_temp_dir(), 'import_').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $file = $this->upload($path);

        $this->actingAs($admin)->post(route('import.kelas'), ['file_kelas' => $file])
            ->assertRedirect(route('import.index'))
            ->assertSessionHas('success', 'Import kelas berhasil! 3 kelas baru dibuat.')
            ->assertSessionMissing('import_warnings');

        $this->assertSame(3, $this->visibleKelasCount());

        $spreadsheet->disconnectWorksheets();
        @unlink($path);
    }

    public function test_importer_pure_qa_menulis_partisi_testing(): void
    {
        $qa = $this->makeQaTester();
        $this->actingAs($qa);

        $path = $this->writeCsv($this->templateRows([
            ['TKJ 1', 'X'],
        ]));
        $file = $this->upload($path);

        $importer = new KelasImport;
        Excel::import($importer, $path);
        @unlink($path);

        $this->assertSame(1, $importer->importedCount);
        $this->assertSame(0, $this->visibleKelasCount(), 'sandbox QA tidak menulis partisi real');
        $this->assertSame(1, Kelas::query()
            ->withoutGlobalScope(TestingDataScope::class)
            ->where('is_testing_data', 1)
            ->count());
    }

    public function test_import_via_switch_view_qa_it_admin_tu_menulis_partisi_real(): void
    {
        $qa = $this->makeQaTester();
        $this->actingAs($qa);
        session(['active_role' => 'admin_tu']);

        $path = $this->writeCsv($this->templateRows([
            ['TKJ 1', 'X'],
        ]));
        $file = $this->upload($path);

        $this->post(route('import.kelas'), ['file_kelas' => $file])
            ->assertRedirect(route('import.index'))
            ->assertSessionHas('success', 'Import kelas berhasil! 1 kelas baru dibuat.');

        $this->assertSame(1, $this->visibleKelasCount(), 'QA + Switch View As Admin TU menulis partisi real');

        @unlink($path);
    }
}
