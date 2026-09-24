<?php

namespace Tests\Feature;

use App\Models\PengaturanJadwal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Fitur Maintenance Mode (Mode Perbaikan Sistem):
 * - Aktif  -> hanya Petugas IT / QA Tester (isTestingUser) yang boleh masuk.
 * - Role lain & guest diblokir (HTTP 503) dan melihat errors.maintenance.
 * - Nonaktif -> semua role normal.
 */
class MaintenanceModeTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUser(string $role, array $extra = []): User
    {
        return User::create(array_merge([
            'nama' => 'User '.Str::random(5),
            'username' => 'user_'.Str::random(8),
            'password' => bcrypt('password'),
            'role' => $role,
            'sub_role' => 'guru',
            'is_active' => true,
        ], $extra));
    }

    public function test_guest_diblokir_saat_maintenance_aktif(): void
    {
        PengaturanJadwal::setMaintenanceMode(true);

        $this->get(route('home'))
            ->assertStatus(503)
            ->assertSee('Sistem Sedang Dalam Pemeliharaan');
    }

    public function test_role_non_it_diblokir_saat_maintenance_aktif(): void
    {
        PengaturanJadwal::setMaintenanceMode(true);

        // Admin TU
        $this->actingAs($this->makeUser('admin', ['sub_role' => 'petugas_tu']))
            ->get(route('home'))
            ->assertStatus(503)
            ->assertSee('Sistem Sedang Dalam Pemeliharaan');

        // Guru
        $this->actingAs($this->makeUser('guru'))
            ->get(route('home'))
            ->assertStatus(503);

        // Wali Kelas
        $this->actingAs($this->makeUser('guru', ['sub_role' => 'wali_kelas']))
            ->get(route('home'))
            ->assertStatus(503);
    }

    public function test_petugas_it_bypass_saat_maintenance_aktif(): void
    {
        PengaturanJadwal::setMaintenanceMode(true);

        $this->actingAs($this->makeUser('petugas_it'))
            ->get(route('home'))
            ->assertOk();
    }

    public function test_qa_tester_bypass_saat_maintenance_aktif(): void
    {
        PengaturanJadwal::setMaintenanceMode(true);

        $this->actingAs($this->makeUser('qa_tester'))
            ->get(route('home'))
            ->assertOk();
    }

    public function test_petugas_it_impersonasi_tetap_bypass_saat_maintenance_aktif(): void
    {
        PengaturanJadwal::setMaintenanceMode(true);

        $it = $this->makeUser('petugas_it');
        $this->actingAs($it);
        session(['active_role' => 'guru_mapel']);

        // Tidak kena 503 maintenance; arahkan ke portal guru sesuai active_role.
        $this->get(route('home'))
            ->assertRedirect(route('guru.dashboard'));

        $this->get(route('guru.dashboard'))
            ->assertOk()
            ->assertSee('Jadwal Mengajar Hari Ini');
    }

    public function test_request_json_mendapat_503_json(): void
    {
        PengaturanJadwal::setMaintenanceMode(true);

        $this->actingAs($this->makeUser('guru'))
            ->getJson(route('home'))
            ->assertStatus(503)
            ->assertJson(['success' => false]);
    }

    public function test_maintenance_nonaktif_semua_role_normal(): void
    {
        PengaturanJadwal::setMaintenanceMode(false);

        $this->actingAs($this->makeUser('guru'))
            ->get(route('home'))
            ->assertOk();
    }

    public function test_halaman_login_tetap_open_dengan_banner_saat_maintenance(): void
    {
        PengaturanJadwal::setMaintenanceMode(true);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Mode Maintenance Aktif')
            ->assertSee('Sistem sedang dalam pemeliharaan. Hanya Petugas IT yang dapat mengakses sistem saat ini.');
    }

    public function test_login_role_non_it_ditolak_saat_maintenance(): void
    {
        PengaturanJadwal::setMaintenanceMode(true);

        $this->makeUser('guru', ['username' => 'guru_tetap']);

        $this->from(route('login'))
            ->post(route('login.post'), [
                'login_id' => 'guru_tetap',
                'password' => 'password',
                'mode' => 'guru',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['login_id' => 'Kredensial yang Anda masukkan salah.']);

        $this->assertGuest();
    }

    public function test_login_petugas_it_lolos_saat_maintenance(): void
    {
        PengaturanJadwal::setMaintenanceMode(true);

        $this->makeUser('petugas_it', ['username' => 'petugas_it', 'kode_aktivasi' => 'it123']);

        $this->from(route('login'))
            ->post(route('login.post'), [
                'login_id' => 'petugas_it',
                'password' => 'password',
                'kode_aktivasi' => 'it123',
                'mode' => 'admin',
            ])
            ->assertRedirect(route('it.dashboard'))
            ->assertSessionHasNoErrors();

        $this->assertAuthenticated();
    }

    public function test_login_qa_tester_lolos_saat_maintenance(): void
    {
        PengaturanJadwal::setMaintenanceMode(true);

        $this->makeUser('qa_tester', ['username' => 'qa_tester', 'kode_aktivasi' => 'qa123']);

        $this->from(route('login'))
            ->post(route('login.post'), [
                'login_id' => 'qa_tester',
                'password' => 'password',
                'kode_aktivasi' => 'qa123',
                'mode' => 'admin',
            ])
            ->assertRedirect(route('it.dashboard'))
            ->assertSessionHasNoErrors();

        $this->assertAuthenticated();
    }

    public function test_user_terlanjur_login_sebelum_maintenance_dil_logout_dari_halaman_login(): void
    {
        $guru = $this->makeUser('guru');

        $this->actingAs($guru);
        $this->assertAuthenticated();

        PengaturanJadwal::setMaintenanceMode(true);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Mode Maintenance Aktif');

        $this->assertGuest();
    }

    public function test_toggle_hanya_petugas_it(): void
    {
        PengaturanJadwal::setMaintenanceMode(false);

        // Non-IT tidak boleh mengubah.
        $this->actingAs($this->makeUser('admin', ['sub_role' => 'petugas_tu']))
            ->post(route('it.maintenance-mode'), ['maintenance_mode' => 1])
            ->assertStatus(403);

        // IT menyalakan.
        $this->actingAs($this->makeUser('petugas_it'))
            ->from(route('home'))
            ->post(route('it.maintenance-mode'), ['maintenance_mode' => 1])
            ->assertRedirect(route('home'))
            ->assertSessionHas('success');

        $this->assertTrue(PengaturanJadwal::isMaintenanceModeActive());

        // IT mematikan kembali.
        $this->actingAs($this->makeUser('petugas_it'))
            ->from(route('home'))
            ->post(route('it.maintenance-mode'), ['maintenance_mode' => 0])
            ->assertRedirect(route('home'));

        $this->assertFalse(PengaturanJadwal::isMaintenanceModeActive());
    }
}
