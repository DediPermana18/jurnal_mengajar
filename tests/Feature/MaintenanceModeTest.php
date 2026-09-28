<?php

namespace Tests\Feature;

use App\Models\PengaturanJadwal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Fitur Maintenance Mode (Mode Perbaikan Sistem):
 * - Aktif -> hanya akun ber-izin bypass (User::canBypassMaintenance) yang
 *   boleh masuk: Petugas IT / QA Tester (role atau sub_role, termasuk
 *   impersonasi & akun sandbox), Super Admin (role literal atau sub_role —
 *   termasuk hasil Emergency Takeover "Kartu As"), role 'admin' (TU, waka,
 *   satpam, kepsek, dsb.), dan akun IT khusus username 'petugas.it'.
 * - Role lain (Guru, Wali Kelas, Kesiswaan, Siswa) & guest diblokir
 *   (HTTP 503) dan melihat errors.maintenance.
 * - Endpoint demote-self tetap terbuka saat maintenance sebagai jalur
 *   pemulihan darurat (akun hasil takeover kembali ke Mode IT).
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

    /**
     * Akun hasil Emergency Super Admin Takeover: role 'admin' + sub_role
     * 'super_admin' + is_emergency_takeover=true.
     */
    private function takeoverUser(): User
    {
        $u = $this->makeUser('petugas_it', ['username' => 'petugas.it']);

        $u->forceFill([
            'role' => User::ROLE_ADMIN,
            'sub_role' => User::ROLE_SUPER_ADMIN,
            'is_emergency_takeover' => true,
            'emergency_origin_role' => User::ROLE_PETUGAS_IT,
        ])->save();

        return $u->refresh();
    }

    // ================= BLOKIR / BYPASS MAINTENANCE =================

    public function test_guest_diblokir_saat_maintenance_aktif(): void
    {
        PengaturanJadwal::setMaintenanceMode(true);

        $this->get(route('home'))
            ->assertStatus(503)
            ->assertSee('Sistem Sedang Dalam Pemeliharaan');
    }

    public function test_guru_dan_wali_kelas_diblokir_saat_maintenance_aktif(): void
    {
        PengaturanJadwal::setMaintenanceMode(true);

        // Guru
        $this->actingAs($this->makeUser('guru'))
            ->get(route('home'))
            ->assertStatus(503);

        // Wali Kelas
        $this->actingAs($this->makeUser('guru', ['sub_role' => 'wali_kelas']))
            ->get(route('home'))
            ->assertStatus(503);
    }

    public function test_admin_tu_bypass_saat_maintenance_aktif(): void
    {
        PengaturanJadwal::setMaintenanceMode(true);

        // Role 'admin' (Petugas TU) masuk kebijakan bypass maintenance.
        $this->actingAs($this->makeUser('admin', ['sub_role' => 'petugas_tu']))
            ->get(route('home'))
            ->assertOk();
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

    public function test_super_admin_role_literal_bypass_saat_maintenance_aktif(): void
    {
        PengaturanJadwal::setMaintenanceMode(true);

        $this->actingAs($this->makeUser('super_admin', ['sub_role' => 'super_admin']))
            ->get(route('home'))
            ->assertOk();
    }

    public function test_super_admin_hasil_takeover_bypass_saat_maintenance_aktif(): void
    {
        PengaturanJadwal::setMaintenanceMode(true);

        // BUG FIX utama: akun hasil Emergency Takeover (role 'admin' +
        // sub_role 'super_admin') tidak boleh lagi tertendang ke halaman
        // maintenance (HTTP 503) — pengendali darurat tetap punya akses.
        $this->actingAs($this->takeoverUser())
            ->get(route('home'))
            ->assertOk();
    }

    public function test_akun_username_petugas_it_bypass_saat_maintenance_aktif(): void
    {
        PengaturanJadwal::setMaintenanceMode(true);

        $this->actingAs($this->makeUser('admin', ['username' => 'petugas.it', 'sub_role' => null]))
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

        // Guru tidak lagi melihat dashboard admin: '/' mengalihkannya ke portal
        // guru. Yang diuji di sini tetap "tidak diblokir maintenance".
        $guru = $this->makeUser('guru');

        $this->actingAs($guru)
            ->get(route('home'))
            ->assertRedirect(route('guru.dashboard'));

        $this->get(route('guru.dashboard'))->assertOk();
    }

    // ================= PEMULIHAN DARURAT SAAT MAINTENANCE =================

    public function test_demote_self_tetap_terbuka_saat_maintenance_aktif(): void
    {
        PengaturanJadwal::setMaintenanceMode(true);

        $u = $this->takeoverUser();

        // Akun hasil takeover dapat memanggil demote-self walau maintenance aktif.
        $this->actingAs($u)
            ->post(route('it-emergency.demote-self'))
            ->assertRedirect(route('it.dashboard'))
            ->assertSessionHas('success');

        $u->refresh();
        $this->assertSame(User::ROLE_ADMIN, $u->role);
        $this->assertSame(User::ROLE_PETUGAS_IT, $u->sub_role);
        $this->assertFalse($u->is_emergency_takeover);

        // Setelah demote (role admin + sub_role petugas_it) tetap bypass maintenance.
        $this->get(route('home'))->assertOk();
    }

    public function test_demote_self_tetap_ditolak_untuk_super_admin_biasa_saat_maintenance(): void
    {
        PengaturanJadwal::setMaintenanceMode(true);

        $sa = $this->makeUser('super_admin', ['sub_role' => 'super_admin']);

        // Bukan akun hasil takeover -> ditolak 403 oleh gate controller.
        $this->actingAs($sa)
            ->post(route('it-emergency.demote-self'))
            ->assertForbidden();
    }

    public function test_halaman_maintenance_menampilkan_restore_mode_it_untuk_akun_takeover(): void
    {
        PengaturanJadwal::setMaintenanceMode(true);

        $u = $this->takeoverUser();

        $html = view('errors.maintenance', ['maintenanceBlockedUser' => $u])->render();

        $this->assertStringContainsString('Login / Restore Mode IT', $html);
        $this->assertStringContainsString(route('it-emergency.demote-self'), $html);
        $this->assertStringContainsString('Restore Mode IT (Demote)', $html);
    }

    public function test_halaman_maintenance_tidak_menampilkan_restore_untuk_user_biasa(): void
    {
        $guru = $this->makeUser('guru');

        $html = view('errors.maintenance', ['maintenanceBlockedUser' => $guru])->render();

        $this->assertStringNotContainsString('Login / Restore Mode IT', $html);
        $this->assertStringNotContainsString(route('it-emergency.demote-self'), $html);
    }

    // ================= HALAMAN & GATE LOGIN SAAT MAINTENANCE =================

    public function test_halaman_login_tetap_open_dengan_banner_saat_maintenance(): void
    {
        PengaturanJadwal::setMaintenanceMode(true);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Mode Maintenance Aktif')
            ->assertSee('Sistem sedang dalam pemeliharaan. Hanya Petugas IT, Super Admin, dan Admin yang dapat mengakses sistem saat ini.');
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

    public function test_login_admin_lolos_saat_maintenance(): void
    {
        PengaturanJadwal::setMaintenanceMode(true);

        $this->makeUser('admin', ['username' => 'admin_tu', 'sub_role' => 'petugas_tu', 'kode_aktivasi' => 'adm123']);

        $this->from(route('login'))
            ->post(route('login.post'), [
                'login_id' => 'admin_tu',
                'password' => 'password',
                'kode_aktivasi' => 'adm123',
                'mode' => 'admin',
            ])
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

        // Non-IT tidak boleh mengubah (gate di controller, bukan middleware).
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