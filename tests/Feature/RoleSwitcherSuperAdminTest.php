<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Opsi 'Super Admin' pada dropdown "Switch View As" (Petugas IT / QA Tester).
 *
 * Petugas IT / QA Tester dapat memilih role 'super_admin' pada View Mode
 * Switcher di topbar sehingga sesi diperlakukan sebagai Super Admin PENUH:
 * sidebar super admin, seluruh portal waka, Zona Berbahaya (reset massal),
 * dan bypass Gate global — tanpa perlu login ulang.
 *
 * Catatan keamanan: reset massal saat preview super admin tetap dieksekusi
 * di partisi TESTING (Petugas IT adalah testing user), sehingga data real
 * tidak pernah tersentuh.
 */
class RoleSwitcherSuperAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UserSeeder::class);
    }

    private function petugasIt(): User
    {
        return User::where('email', 'it@school.id')->firstOrFail();
    }

    private function qaTester(): User
    {
        return User::where('email', 'qa@school.id')->firstOrFail();
    }

    private function petugasTu(): User
    {
        return User::where('email', 'admin@school.id')->firstOrFail();
    }

    public function test_switch_view_ke_super_admin_berhasil(): void
    {
        $this->actingAs($this->petugasIt())
            ->post(route('it.switch-view'), ['role' => 'super_admin'])
            ->assertRedirect(route('home'))
            ->assertSessionHas('success', 'View: Super Admin');

        $this->assertEquals('super_admin', session('active_role'));
    }

    public function test_qa_tester_juga_dapat_switch_ke_super_admin(): void
    {
        $this->actingAs($this->qaTester())
            ->post(route('it.switch-view'), ['role' => 'super_admin'])
            ->assertRedirect(route('home'));

        $this->assertEquals('super_admin', session('active_role'));
    }

    public function test_user_bukan_it_tidak_bisa_switch_ke_super_admin(): void
    {
        $this->actingAs($this->petugasTu())
            ->post(route('it.switch-view'), ['role' => 'super_admin'])
            ->assertForbidden();
    }

    public function test_role_tidak_valid_tetap_ditolak(): void
    {
        $this->actingAs($this->petugasIt())
            ->post(route('it.switch-view'), ['role' => 'super_adminx'])
            ->assertStatus(422);
    }

    public function test_dropdown_menampilkan_opsi_super_admin(): void
    {
        $this->actingAs($this->petugasIt())
            ->get(route('home'))
            ->assertOk()
            // Widget impersonasi/testing terkonsolidasi dalam banner 'Mode Dev & Testing'
            // dengan dropdown 'Switch Role' (status view ditampilkan di bahasa banner).
            ->assertSee('Mode Dev &amp; Testing', false)
            ->assertSee('Switch Role')
            ->assertSee('Super Admin')
            // Tanpa preview, IT tidak melihat menu Super Admin (sidebar IT).
            ->assertDontSee('Portal Waka SDM');
    }

    public function test_preview_super_admin_melihat_kartu_zona_berbahaya(): void
    {
        $this->withSession(['active_role' => 'super_admin'])
            ->actingAs($this->petugasIt())
            ->get(route('import.index'))
            ->assertOk()
            ->assertSee('Zona Berbahaya')
            ->assertSee('Reset / Hapus Semua Data Siswa');
    }

    public function test_preview_super_admin_dapat_reset_massal_hanya_partisi_testing(): void
    {
        // Data real (partisi 0) — dibuat tanpa autentikasi.
        $kelasReal = Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'RPL 1']);
        Siswa::create([
            'nisn' => '1000000001',
            'nis' => '5001',
            'nama' => 'Siswa Real',
            'jenis_kelamin' => 'L',
            'id_kelas' => $kelasReal->id,
            'status_siswa' => 'Aktif',
        ]);

        // Data testing (partisi 1) — dibuat sebagai Petugas IT.
        $this->actingAs($this->petugasIt());
        $kelasTesting = Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'TKJ 1']);
        for ($i = 1; $i <= 2; $i++) {
            Siswa::create([
                'nisn' => (string) (2000000000 + $i),
                'nis' => (string) (6000 + $i),
                'nama' => "Siswa Testing {$i}",
                'jenis_kelamin' => 'L',
                'id_kelas' => $kelasTesting->id,
                'status_siswa' => 'Aktif',
            ]);
        }

        $this->withSession(['active_role' => 'super_admin'])
            ->actingAs($this->petugasIt())
            ->post(route('import.reset-siswa'), ['reset_confirm' => 'HAPUS DATA SISWA'])
            ->assertRedirect(route('import.index'))
            ->assertSessionHas('success');

        // Hanya partisi testing yang terhapus — data real utuh.
        $this->assertSame(0, DB::table('siswa')->where('is_testing_data', 1)->count());
        $this->assertSame(1, DB::table('siswa')->where('is_testing_data', 0)->count());
        $this->assertDatabaseCount('siswa', 1);
    }

    public function test_preview_super_admin_melihat_sidebar_super_admin(): void
    {
        $this->withSession(['active_role' => 'super_admin'])
            ->actingAs($this->petugasIt())
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Portal Waka SDM')
            ->assertSee('Laporan KBM');
    }

    public function test_reset_view_mengembalikan_mode_asli(): void
    {
        $it = $this->petugasIt();

        $this->actingAs($it)
            ->post(route('it.switch-view'), ['role' => 'super_admin']);

        $this->assertEquals('super_admin', $it->activeRole());

        $this->actingAs($it)
            ->post(route('it.reset-view'))
            ->assertRedirect(route('it.dashboard'))
            ->assertSessionMissing('active_role');

        // Setelah kembali, IT tidak lagi diperlakukan sebagai Super Admin:
        // kartu Zona Berbahaya menghilang dari halaman Import.
        $this->actingAs($it)
            ->get(route('import.index'))
            ->assertOk()
            ->assertDontSee('Zona Berbahaya');
    }
}