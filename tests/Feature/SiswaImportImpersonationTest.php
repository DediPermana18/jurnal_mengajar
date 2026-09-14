<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Scopes\TestingDataScope;
use App\Models\Siswa;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SiswaImportImpersonationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UserSeeder::class);
    }

    private function makeAdminTu(): User
    {
        return User::withoutGlobalScope(TestingDataScope::class)
            ->where('email', 'admin@school.id')
            ->firstOrFail();
    }

    private function makeQaTester(): User
    {
        return User::withoutGlobalScope(TestingDataScope::class)
            ->where('email', 'qa@school.id')
            ->firstOrFail();
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

    /** Baris CSV: header kelas + N baris siswa (nisn 10 digit unik). */
    private function siswaRows(int $count, int $offset = 0): array
    {
        $rows = [['KELAS: X TKJ 1']];

        for ($i = 1; $i <= $count; $i++) {
            $n = $i + $offset;
            $rows[] = [$i, '1000'.str_pad((string) $n, 6, '0', STR_PAD_LEFT), 'Siswa Uji '.$i, (string) (100 + $i), 'L', 'Aktif'];
        }

        return $rows;
    }

    private function real_siswa_count(): int
    {
        return Siswa::query()
            ->withoutGlobalScope(TestingDataScope::class)
            ->where('is_testing_data', 0)
            ->count();
    }

    private function testing_siswa_count(): int
    {
        return Siswa::query()
            ->withoutGlobalScope(TestingDataScope::class)
            ->where('is_testing_data', 1)
            ->count();
    }

    public function test_import_sebagai_admin_tu_asli_memproses_semua_baris_ke_partisi_real(): void
    {
        $admin = $this->makeAdminTu();
        $kelas = Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'TKJ 1']);

        $path = $this->writeCsv($this->siswaRows(5));
        $file = new UploadedFile($path, 'siswa_admin_tu.csv', 'text/csv', null, true);

        $this->actingAs($admin)->post(route('import.siswa'), [
            'file_excel' => $file,
            'id_kelas' => $kelas->id,
        ])->assertRedirect(route('import.index'))
            ->assertSessionHas('success', 'Import berhasil! 5 siswa diproses.')
            ->assertSessionMissing('import_warnings');

        // Seluruh siswa masuk partisi REAL, menunjuk ke kelas real.
        $this->assertSame(5, $this->real_siswa_count());
        $this->assertSame(
            $kelas->id,
            Siswa::query()
                ->withoutGlobalScope(TestingDataScope::class)
                ->where('is_testing_data', 0)
                ->where('nisn', '1000000001')
                ->firstOrFail()
                ->id_kelas
        );

        @unlink($path);
    }

    public function test_import_via_switch_view_qa_it_admin_tu_menulis_partisi_testing(): void
    {
        $admin = $this->makeAdminTu();
        $kelas = Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'TKJ 1']);

        // Import 1 — akun Admin TU asli (partisi real).
        $path = $this->writeCsv($this->siswaRows(5));
        $file = new UploadedFile($path, 'siswa_real.csv', 'text/csv', null, true);
        $this->actingAs($admin)->post(route('import.siswa'), [
            'file_excel' => $file,
            'id_kelas' => $kelas->id,
        ])->assertRedirect(route('import.index'));
        @unlink($path);
        $this->assertSame(5, $this->real_siswa_count());

        // Import 2 — Petugas IT / QA dengan Switch View As Admin TU (active_role).
        // Sebelum perbaikan, import menarget partisi real sehingga data tersimpan
        // is_testing_data = 0 dan tak muncul di Web UI mode testing.
        $qa = $this->makeQaTester();
        $this->actingAs($qa);
        session(['active_role' => 'admin_tu']);
        $this->assertTrue($qa->hasActiveRole(), 'QA dalam mode Switch View As Admin TU');

        // NISN berbeda dari import admin (kolom nisn unik global lintas partisi),
        // agar tidak membuat duplikat artifisial — parity diukur dari jumlah baris.
        $path2 = $this->writeCsv($this->siswaRows(5, 500));
        $file2 = new UploadedFile($path2, 'siswa_impersonasi.csv', 'text/csv', null, true);
        $response = $this->post(route('import.siswa'), [
            'file_excel' => $file2,
            'id_kelas' => $kelas->id,
        ]);

        @unlink($path2);

        // Jumlah baris diproses SAMA PERSIS dengan akun TU asli (count parity).
        $response->assertRedirect(route('import.index'))
            ->assertSessionHas('success', 'Import berhasil! 5 siswa diproses.')
            ->assertSessionMissing('import_warnings');

        // Data QA/IT disimpan ke partisi TESTING (is_testing_data = 1).
        $this->assertSame(5, $this->testing_siswa_count(), 'data QA/IT tersimpan di partisi testing');
        $this->assertSame(5, $this->real_siswa_count(), 'data import QA tidak bocor ke partisi real');

        // Baris pertama QA di partisi testing merujuk kelas yang sama (resolusi
        // kelas lintas partisi tetap valid).
        $this->assertSame($kelas->id, Siswa::query()
            ->withoutGlobalScope(TestingDataScope::class)
            ->where('is_testing_data', 1)
            ->where('nisn', '1000000501')
            ->firstOrFail()
            ->id_kelas);
    }

    public function test_import_menghormati_session_impersonate_role_menulis_testing(): void
    {
        $kelas = Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'TKJ 1']);

        $qa = $this->makeQaTester();
        $this->actingAs($qa);
        session(['impersonate_role' => 'admin_tu']);

        $path = $this->writeCsv($this->siswaRows(3));
        $file = new UploadedFile($path, 'siswa_impersonate_role.csv', 'text/csv', null, true);

        $this->post(route('import.siswa'), [
            'file_excel' => $file,
            'id_kelas' => $kelas->id,
        ])->assertRedirect(route('import.index'))
            ->assertSessionHas('success', 'Import berhasil! 3 siswa diproses.')
            ->assertSessionMissing('import_warnings');

        $this->assertSame(3, $this->testing_siswa_count());
        $this->assertSame(0, $this->real_siswa_count());

        @unlink($path);
    }

    public function test_import_petugas_it_murni_tanpa_switch_view_menulis_partisi_testing(): void
    {
        // Kelas dibuat QA → partisi testing (is_testing_data = 1).
        $qa = $this->makeQaTester();
        $this->actingAs($qa);
        $kelas = Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'TKJ 1']);
        $this->assertTrue((bool) $kelas->is_testing_data);

        $path = $this->writeCsv($this->siswaRows(2));
        $file = new UploadedFile($path, 'siswa_qa.csv', 'text/csv', null, true);

        $this->actingAs($qa)->post(route('import.siswa'), [
            'file_excel' => $file,
            'id_kelas' => $kelas->id,
        ])->assertRedirect(route('import.index'))
            ->assertSessionHas('success', 'Import berhasil! 2 siswa diproses.')
            ->assertSessionMissing('import_warnings');

        // Sandbox QA: semua siswa masuk partisi testing, partisi real tetap kosong.
        $this->assertSame(2, Siswa::query()
            ->withoutGlobalScope(TestingDataScope::class)
            ->where('is_testing_data', 1)
            ->count());
        $this->assertSame(0, $this->real_siswa_count());

        @unlink($path);
    }

    public function test_import_index_saat_switch_view_admin_tu_menampilkan_kelas_testing(): void
    {
        $admin = $this->makeAdminTu();
        Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'ZALPHA']);

        $qa = $this->makeQaTester();
        $this->actingAs($qa);
        $testing = Kelas::create(['tingkat' => 'XI', 'nama_kelas' => 'ZBETA']);
        $this->assertTrue((bool) $testing->is_testing_data);

        session(['active_role' => 'admin_tu']);

        $response = $this->get(route('import.index'));
        $response->assertOk();
        $response->assertSee('ZBETA');
        $response->assertDontSee('ZALPHA');
    }

    public function test_import_siswa_qa_switch_view_menyimpan_is_testing_data_satu(): void
    {
        $kelas = Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'TKJ 1']);

        $qa = $this->makeQaTester();
        $this->actingAs($qa);
        session(['active_role' => 'admin_tu']);

        $path = $this->writeCsv($this->siswaRows(2));
        $file = new UploadedFile($path, 'siswa_testing_flag.csv', 'text/csv', null, true);

        $this->post(route('import.siswa'), [
            'file_excel' => $file,
            'id_kelas' => $kelas->id,
        ])->assertRedirect(route('import.index'))
            ->assertSessionHas('success', 'Import berhasil! 2 siswa diproses.');

        // BUG FIX: import siswa dari mode QA/IT (via Switch View) wajib tersimpan
        // is_testing_data = 1 agar muncul di Web UI mode testing.
        $rows = Siswa::query()
            ->withoutGlobalScope(TestingDataScope::class)
            ->where('is_testing_data', 1)
            ->get();
        $this->assertCount(2, $rows);
        foreach ($rows as $siswa) {
            $this->assertTrue((bool) $siswa->is_testing_data);
        }

        @unlink($path);
    }

    public function test_import_siswa_qa_skip_nisn_yang_sudah_ada_di_data_real(): void
    {
        $kelas = Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'TKJ 1']);

        // Import 1 — Admin TU asli (partisi real).
        $admin = $this->makeAdminTu();
        $path = $this->writeCsv($this->siswaRows(2));
        $file = new UploadedFile($path, 'siswa_real.csv', 'text/csv', null, true);
        $this->actingAs($admin)->post(route('import.siswa'), [
            'file_excel' => $file,
            'id_kelas' => $kelas->id,
        ])->assertRedirect(route('import.index'));
        @unlink($path);
        $this->assertSame(2, $this->real_siswa_count());
        $this->assertSame(0, $this->testing_siswa_count());

        // Import 2 — QA / IT (Switch View) dengan file ber-NISN SAMA.
        // NISN sudah ada di data REAL → Conflict Skip + warning, data produksi aman.
        $qa = $this->makeQaTester();
        $this->actingAs($qa);
        session(['active_role' => 'admin_tu']);

        $path2 = $this->writeCsv($this->siswaRows(2));
        $file2 = new UploadedFile($path2, 'siswa_double.csv', 'text/csv', null, true);
        $this->post(route('import.siswa'), [
            'file_excel' => $file2,
            'id_kelas' => $kelas->id,
        ])->assertRedirect(route('import.index'))
            ->assertSessionHas('success', 'Import berhasil! 0 siswa diproses, 2 baris dilewati.')
            ->assertSessionHas('import_warnings');
        @unlink($path2);

        $warnings = session()->get('import_warnings');
        $this->assertContains(
            'NISN 1000000001 dilewati karena sudah terdaftar sebagai Data Real',
            $warnings,
        );
        $this->assertContains(
            'NISN 1000000002 dilewati karena sudah terdaftar sebagai Data Real',
            $warnings,
        );

        // Tetap 2 siswa real, TIDAK ada duplikat testing, TIDAK ada flip.
        $this->assertSame(2, $this->real_siswa_count());
        $this->assertSame(0, $this->testing_siswa_count());
        $this->assertSame(0, Siswa::query()
            ->withoutGlobalScope(TestingDataScope::class)
            ->where('nisn', '1000000001')
            ->firstOrFail()
            ->is_testing_data);
    }

    public function test_import_admin_tu_juga_skip_nisn_yang_sudah_ada_di_data_real(): void
    {
        $kelas = Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'TKJ 1']);

        // Import 1 — Admin TU asli: baris masuk partisi real.
        $admin = $this->makeAdminTu();
        $path = $this->writeCsv($this->siswaRows(2));
        $file = new UploadedFile($path, 'siswa_real.csv', 'text/csv', null, true);
        $this->actingAs($admin)->post(route('import.siswa'), [
            'file_excel' => $file,
            'id_kelas' => $kelas->id,
        ])->assertRedirect(route('import.index'));
        @unlink($path);
        $this->assertSame(2, $this->real_siswa_count());

        // Import 2 — Admin TU re-import file ber-NISN SAMA.
        // Conflict Skip juga berlaku untuk user non-IT: baris dilabeli warning,
        // bukan di-update.
        $path2 = $this->writeCsv($this->siswaRows(2));
        $file2 = new UploadedFile($path2, 'siswa_real_double.csv', 'text/csv', null, true);
        $this->actingAs($admin)->post(route('import.siswa'), [
            'file_excel' => $file2,
            'id_kelas' => $kelas->id,
        ])->assertRedirect(route('import.index'))
            ->assertSessionHas('success', 'Import berhasil! 0 siswa diproses, 2 baris dilewati.')
            ->assertSessionHas('import_warnings');
        @unlink($path2);

        $this->assertSame(2, $this->real_siswa_count());
        $this->assertSame(0, $this->testing_siswa_count());
    }

    public function test_import_admin_tu_update_nisn_testing_tanpa_memindahkan_partisi(): void
    {
        $kelas = Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'TKJ 1']);

        // Import 1 — QA / IT (Switch View): data masuk partisi testing.
        $qa = $this->makeQaTester();
        $this->actingAs($qa);
        session(['active_role' => 'admin_tu']);
        $path = $this->writeCsv($this->siswaRows(2));
        $file = new UploadedFile($path, 'siswa_testing.csv', 'text/csv', null, true);
        $this->post(route('import.siswa'), [
            'file_excel' => $file,
            'id_kelas' => $kelas->id,
        ])->assertRedirect(route('import.index'));
        @unlink($path);
        $this->assertSame(2, $this->testing_siswa_count());

        // Import 2 — Admin TU asli re-import file ber-NISN SAMA: NISN ada di
        // partisi testing → update di tempat, partisi testing DI-PERTAHANKAN
        // (tidak dipindah ke real).
        $admin = $this->makeAdminTu();
        $path2 = $this->writeCsv($this->siswaRows(2));
        $file2 = new UploadedFile($path2, 'siswa_admin.csv', 'text/csv', null, true);
        $this->actingAs($admin)->post(route('import.siswa'), [
            'file_excel' => $file2,
            'id_kelas' => $kelas->id,
        ])->assertRedirect(route('import.index'))
            ->assertSessionHas('success', 'Import berhasil! 2 siswa diproses.')
            ->assertSessionMissing('import_warnings');
        @unlink($path2);

        $this->assertSame(0, $this->real_siswa_count());
        $this->assertSame(2, $this->testing_siswa_count());
        $this->assertSame(1, Siswa::query()
            ->withoutGlobalScope(TestingDataScope::class)
            ->where('nisn', '1000000001')
            ->firstOrFail()
            ->is_testing_data);
    }

    public function test_session_is_testing_mode_memaksa_partisi_testing_bagi_admin(): void
    {
        $kelas = Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'TKJ 1']);

        $admin = $this->makeAdminTu();
        $this->actingAs($admin);
        session(['is_testing_mode' => true]);

        $path = $this->writeCsv($this->siswaRows(2));
        $file = new UploadedFile($path, 'siswa_testing_mode.csv', 'text/csv', null, true);

        $this->post(route('import.siswa'), [
            'file_excel' => $file,
            'id_kelas' => $kelas->id,
        ])->assertRedirect(route('import.index'))
            ->assertSessionHas('success', 'Import berhasil! 2 siswa diproses.');

        $this->assertSame(2, $this->testing_siswa_count());
        $this->assertSame(0, $this->real_siswa_count());

        @unlink($path);
    }
}
