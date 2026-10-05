<?php

namespace Tests\Feature;

use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\ShiftPelajaran;
use App\Models\Siswa;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Recycle Bin (Data Terhapus) — manajemen soft delete untuk Super Admin / Petugas IT.
 *
 * Cakupan:
 *  - Akses halaman trash (Super Admin & Petugas IT boleh, Petugas TU ditolak).
 *  - Restore mengembalikan record soft-deleted.
 *  - Force delete menghapus permanen.
 *  - Model/kunci yang tidak dikenal → 404; record non-trashed → 404.
 *  - Isolasi is_testing_data tetap dipertahankan (IT = partisi testing,
 *    Super Admin = partisi produksi).
 *  - JamPelajaran kini soft-deletable (trait SoftDeletes) dan terkelola di trash.
 */
class TrashManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UserSeeder::class);
    }

    // ------------------------------------------------------------------------
    // Helper
    // ------------------------------------------------------------------------

    private function superAdmin(): User
    {
        return User::create([
            'username' => 'rt.superadmin',
            'nama' => 'Super Admin Uji',
            'email' => 'rt.superadmin@school.id',
            'password' => 'secret',
            'role' => User::ROLE_SUPER_ADMIN,
            'sub_role' => null,
            'is_active' => true,
        ]);
    }

    private function petugasIt(): User
    {
        return User::where('email', 'it@school.id')->firstOrFail();
    }

    private function petugasTu(): User
    {
        return User::where('email', 'admin@school.id')->firstOrFail();
    }

    private function makeGuru(array $overrides = []): User
    {
        return User::create(array_merge([
            'username' => 'guru.'.uniqid(),
            'nama' => 'Guru Recycle Bin',
            'email' => 'guru.'.uniqid().'@school.id',
            'password' => 'secret',
            'role' => User::ROLE_GURU,
            'sub_role' => 'guru',
            'is_active' => true,
        ], $overrides));
    }

    private function makeAccount(array $overrides = []): User
    {
        return User::create(array_merge([
            'username' => 'akun.'.uniqid(),
            'nama' => 'Akun Recycle Bin',
            'email' => 'akun.'.uniqid().'@school.id',
            'password' => 'secret',
            'role' => User::ROLE_ADMIN,
            'sub_role' => 'petugas_tu',
            'is_active' => true,
        ], $overrides));
    }

    private function makeSiswa(array $overrides = []): Siswa
    {
        $kelas = Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'TKJ 1']);

        return Siswa::create(array_merge([
            'nisn' => (string) rand(1000000000, 1999999999),
            'nis' => (string) rand(10000, 99999),
            'nama' => 'Siswa Recycle Bin',
            'jenis_kelamin' => 'L',
            'id_kelas' => $kelas->id,
            'status_siswa' => 'Aktif',
        ], $overrides));
    }

    private function makeJamPelajaran(?int $shiftId = null): JamPelajaran
    {
        return JamPelajaran::create([
            'kategori_hari' => 'Senin-Kamis',
            'shift_id' => $shiftId,
            'jam_ke' => 1,
            'jam_mulai' => '08:00:00',
            'jam_selesai' => '08:45:00',
            'jenis' => 'kbm',
        ]);
    }

    // ------------------------------------------------------------------------
    // Akses
    // ------------------------------------------------------------------------

    public function test_super_admin_dapat_melihat_halaman_trash(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('admin.trash.index'))
            ->assertOk()
            ->assertSee('Data Terhapus')
            ->assertSee('Recycle Bin')
            ->assertSee('Guru', false)
            ->assertSee('Siswa', false)
            ->assertSee('Jam Pelajaran')
            ->assertSee('Jadwal Pelajaran');
    }

    public function test_petugas_it_dapat_melihat_halaman_trash(): void
    {
        $this->actingAs($this->petugasIt())
            ->get(route('admin.trash.index'))
            ->assertOk();
    }

    public function test_petugas_tu_tidak_bisa_mengakses_trash(): void
    {
        $this->actingAs($this->petugasTu())
            ->get(route('admin.trash.index'))
            ->assertForbidden();
    }

    public function test_guest_diredirect_ke_login(): void
    {
        $this->get(route('admin.trash.index'))
            ->assertRedirect(route('login'));
    }

    // ------------------------------------------------------------------------
    // Restore & Force Delete
    // ------------------------------------------------------------------------

    public function test_restore_mengembalikan_guru_terhapus(): void
    {
        $guru = $this->makeGuru(['nama' => 'Guru Restore Uji']);
        $guru->delete();
        $this->assertSoftDeleted('users', ['id' => $guru->id]);

        $this->actingAs($this->superAdmin())
            ->post(route('admin.trash.restore', ['guru', $guru->id]))
            ->assertRedirect()
            ->assertSessionHas('success', 'Data Guru "Guru Restore Uji" berhasil dipulihkan (restore).');

        $this->assertNotSoftDeleted('users', ['id' => $guru->id]);
        $this->assertDatabaseHas('users', ['id' => $guru->id, 'deleted_at' => null]);
    }

    public function test_force_delete_menghapus_siswa_permanen(): void
    {
        $siswa = $this->makeSiswa(['nama' => 'Siswa Force Uji']);
        $siswa->delete();
        $this->assertSoftDeleted('siswa', ['id' => $siswa->id]);

        $this->actingAs($this->superAdmin())
            ->delete(route('admin.trash.force-delete', ['siswa', $siswa->id]))
            ->assertRedirect()
            ->assertSessionHas('success', 'Data Siswa "Siswa Force Uji" telah dihapus PERMANEN dari database dan tidak dapat dikembalikan.');

        $this->assertDatabaseMissing('siswa', ['id' => $siswa->id]);
    }

    public function test_restore_menampilkan_error_bila_gagal(): void
    {
        // Restore record yang masih aktif (tidak trashed) → 404.
        $guru = $this->makeGuru();

        $this->actingAs($this->superAdmin())
            ->post(route('admin.trash.restore', ['guru', $guru->id]))
            ->assertNotFound();
    }

    public function test_model_tidak_dikenal_menghasilkan_404(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->post(route('admin.trash.restore', ['hacker-model', 1]))
            ->assertNotFound();

        $this->actingAs($admin)
            ->delete(route('admin.trash.force-delete', ['hacker-model', 1]))
            ->assertNotFound();
    }

    public function test_force_delete_record_yang_tidak_trashed_menghasilkan_404(): void
    {
        $siswa = $this->makeSiswa(['nama' => 'Siswa Aktif']);

        $this->actingAs($this->superAdmin())
            ->delete(route('admin.trash.force-delete', ['siswa', $siswa->id]))
            ->assertNotFound();
    }

    // ------------------------------------------------------------------------
    // Isolasi is_testing_data
    // ------------------------------------------------------------------------

    public function test_super_admin_hanya_melihat_data_real_di_trash(): void
    {
        $real = $this->makeGuru(['nama' => 'Guru Real Uji', 'is_testing_data' => false]);
        $testing = $this->makeGuru(['nama' => 'Guru Testing Uji', 'is_testing_data' => true]);
        $real->delete();
        $testing->delete();

        $this->actingAs($this->superAdmin())
            ->get(route('admin.trash.index'))
            ->assertOk()
            ->assertSee('Guru Real Uji')
            ->assertDontSee('Guru Testing Uji');
    }

    public function test_petugas_it_hanya_melihat_data_testing_di_trash(): void
    {
        $real = $this->makeGuru(['nama' => 'Guru Real Uji', 'is_testing_data' => false]);
        $testing = $this->makeGuru(['nama' => 'Guru Testing Uji', 'is_testing_data' => true]);
        $real->delete();
        $testing->delete();

        $this->actingAs($this->petugasIt())
            ->get(route('admin.trash.index'))
            ->assertOk()
            ->assertSee('Guru Testing Uji')
            ->assertDontSee('Guru Real Uji');
    }

    public function test_isolasi_testing_dijaga_saat_restore(): void
    {
        // Data real tidak boleh di-restore oleh Petugas IT (di luar partisinya).
        $real = $this->makeGuru(['nama' => 'Guru Real Rahasia', 'is_testing_data' => false]);
        $real->delete();

        $this->actingAs($this->petugasIt())
            ->post(route('admin.trash.restore', ['guru', $real->id]))
            ->assertNotFound();
    }

    // ------------------------------------------------------------------------
    // Jam Pelajaran (SoftDeletes baru)
    // ------------------------------------------------------------------------

    public function test_jam_pelajaran_kini_soft_deletable_dan_muncul_di_trash(): void
    {
        $shift = ShiftPelajaran::create([
            'nama_shift' => 'Shift 1 Pagi',
            'jam_mulai' => '07:00:00',
            'is_active' => true,
        ]);
        $jam = $this->makeJamPelajaran($shift->id);
        $jam->delete();

        $this->assertSoftDeleted('jam_pelajaran', ['id' => $jam->id]);

        $this->actingAs($this->superAdmin())
            ->get(route('admin.trash.index'))
            ->assertOk()
            ->assertSee('08:00–08:45')
            ->assertSee('Shift 1 Pagi');
    }

    public function test_jam_pelajaran_bisa_di_restore_dari_trash(): void
    {
        $jam = $this->makeJamPelajaran();
        $jam->delete();

        $this->actingAs($this->superAdmin())
            ->post(route('admin.trash.restore', ['jam-pelajaran', $jam->id]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNotSoftDeleted('jam_pelajaran', ['id' => $jam->id]);
    }

    // ------------------------------------------------------------------------
    // User / Pengguna (kategori akun)
    // ------------------------------------------------------------------------

    public function test_user_category_menampilkan_tabel_akun_terhapus(): void
    {
        $akun = $this->makeAccount(['nama' => 'Akun TU Terhapus']);
        $akun->delete();
        $this->assertSoftDeleted('users', ['id' => $akun->id]);

        $this->actingAs($this->superAdmin())
            ->get(route('admin.trash.index'))
            ->assertOk()
            ->assertSee('User / Pengguna', false)
            ->assertSee('Nama User')
            ->assertSee('Role / Akses')
            ->assertSee('Tanggal Dihapus')
            ->assertSee('Akun TU Terhapus')
            ->assertSee($akun->email)
            ->assertSee('Admin · Petugas TU');
    }

    public function test_user_restore_mengembalikan_akun(): void
    {
        $akun = $this->makeAccount(['nama' => 'Akun Restore Uji']);
        $akun->delete();
        $this->assertSoftDeleted('users', ['id' => $akun->id]);

        $this->actingAs($this->superAdmin())
            ->post(route('admin.trash.restore', ['user', $akun->id]))
            ->assertRedirect()
            ->assertSessionHas('success', 'Data User / Pengguna "Akun Restore Uji" berhasil dipulihkan (restore).');

        $this->assertNotSoftDeleted('users', ['id' => $akun->id]);
    }

    public function test_user_force_delete_menghapus_akun_permanen(): void
    {
        $akun = $this->makeAccount(['nama' => 'Akun Force Uji']);
        $akun->delete();
        $this->assertSoftDeleted('users', ['id' => $akun->id]);

        $this->actingAs($this->superAdmin())
            ->delete(route('admin.trash.force-delete', ['user', $akun->id]))
            ->assertRedirect()
            ->assertSessionHas('success', 'Data User / Pengguna "Akun Force Uji" telah dihapus PERMANEN dari database dan tidak dapat dikembalikan.');

        $this->assertDatabaseMissing('users', ['id' => $akun->id]);
    }

    public function test_user_kategori_mematuhi_isolasi_testing(): void
    {
        $real = $this->makeAccount(['nama' => 'Akun Real Uji', 'is_testing_data' => false]);
        $testing = $this->makeAccount(['nama' => 'Akun Testing Uji', 'is_testing_data' => true]);
        $real->delete();
        $testing->delete();

        $this->actingAs($this->superAdmin())
            ->get(route('admin.trash.index'))
            ->assertOk()
            ->assertSee('Akun Real Uji')
            ->assertDontSee('Akun Testing Uji');

        $this->actingAs($this->petugasIt())
            ->get(route('admin.trash.index'))
            ->assertOk()
            ->assertSee('Akun Testing Uji')
            ->assertDontSee('Akun Real Uji');
    }

    // ------------------------------------------------------------------------
    // Sidebar: menu 'Data Terhapus' per role
    // ------------------------------------------------------------------------

    public function test_super_admin_melihat_menu_data_terhapus_di_sidebar(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('admin.trash.index'))
            ->assertOk()
            ->assertSee('/admin/trash"', false);
    }

    public function test_petugas_it_melihat_menu_data_terhapus_di_sidebar(): void
    {
        $this->actingAs($this->petugasIt())
            ->get(route('admin.trash.index'))
            ->assertOk()
            ->assertSee('/admin/trash"', false);
    }

    public function test_menu_data_terhapus_tidak_muncul_untuk_petugas_tu(): void
    {
        $this->actingAs($this->petugasTu())
            ->get(route('guru.index'))
            ->assertOk()
            ->assertDontSee('/admin/trash');
    }

    // ------------------------------------------------------------------------
    // Bulk Operation (Restore / Force Delete massal)
    // ------------------------------------------------------------------------

    public function test_bulk_restore_memulihkan_beberapa_guru_terhapus(): void
    {
        $g1 = $this->makeGuru(['nama' => 'Guru Bulk Satu']);
        $g2 = $this->makeGuru(['nama' => 'Guru Bulk Dua']);
        $g1->delete();
        $g2->delete();
        $this->assertSoftDeleted('users', ['id' => $g1->id]);
        $this->assertSoftDeleted('users', ['id' => $g2->id]);

        $this->actingAs($this->superAdmin())
            ->post(route('admin.trash.restore-bulk', ['guru']), ['ids' => [$g1->id, $g2->id]])
            ->assertRedirect()
            ->assertSessionHas('success', '2 data Guru berhasil dipulihkan (restore).');

        $this->assertNotSoftDeleted('users', ['id' => $g1->id]);
        $this->assertNotSoftDeleted('users', ['id' => $g2->id]);
        $this->assertDatabaseHas('users', ['id' => $g1->id, 'deleted_at' => null]);
    }

    public function test_bulk_restore_mengabaikan_id_di_luar_kategori(): void
    {
        // Hanya guru tertentu yang dipilih; guru lain tetap terhapus.
        $g1 = $this->makeGuru(['nama' => 'Guru Bulk Pilih Satu']);
        $g2 = $this->makeGuru(['nama' => 'Guru Bulk Pilih Dua']);
        $g1->delete();
        $g2->delete();

        $this->actingAs($this->superAdmin())
            ->post(route('admin.trash.restore-bulk', ['guru']), ['ids' => [$g1->id]])
            ->assertRedirect()
            ->assertSessionHas('success', '1 data Guru berhasil dipulihkan (restore).');

        $this->assertNotSoftDeleted('users', ['id' => $g1->id]);
        $this->assertSoftDeleted('users', ['id' => $g2->id]);
    }

    public function test_bulk_force_delete_menghapus_beberapa_siswa_permanen(): void
    {
        $s1 = $this->makeSiswa(['nama' => 'Siswa Bulk Satu']);
        $s2 = $this->makeSiswa(['nama' => 'Siswa Bulk Dua']);
        $s1->delete();
        $s2->delete();
        $this->assertSoftDeleted('siswa', ['id' => $s1->id]);

        $this->actingAs($this->superAdmin())
            ->delete(route('admin.trash.force-delete-bulk', ['siswa']), ['ids' => [$s1->id, $s2->id]])
            ->assertRedirect()
            ->assertSessionHas('success', '2 data Siswa telah dihapus PERMANEN dari database dan tidak dapat dikembalikan.');

        $this->assertDatabaseMissing('siswa', ['id' => $s1->id]);
        $this->assertDatabaseMissing('siswa', ['id' => $s2->id]);
    }

    public function test_bulk_restore_tanpa_ids_memberi_pesan_error(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('admin.trash.restore-bulk', ['guru']), ['ids' => []])
            ->assertRedirect()
            ->assertSessionHas('error', 'Pilih minimal satu data Guru yang akan diproses.');
    }

    public function test_bulk_restore_menjaga_isolasi_testing(): void
    {
        $real = $this->makeGuru(['nama' => 'Guru Real Bulk', 'is_testing_data' => false]);
        $testing = $this->makeGuru(['nama' => 'Guru Testing Bulk', 'is_testing_data' => true]);
        $real->delete();
        $testing->delete();

        // Petugas IT memilih kedua id — hanya yang di partisi testing yang dipulihkan.
        $this->actingAs($this->petugasIt())
            ->post(route('admin.trash.restore-bulk', ['guru']), ['ids' => [$real->id, $testing->id]])
            ->assertRedirect()
            ->assertSessionHas('success', '1 data Guru berhasil dipulihkan (restore).');

        $this->assertNotSoftDeleted('users', ['id' => $testing->id]);
        $this->assertSoftDeleted('users', ['id' => $real->id]);
    }

    public function test_bulk_aksi_ditolak_untuk_petugas_tu(): void
    {
        $this->actingAs($this->petugasTu())
            ->post(route('admin.trash.restore-bulk', ['guru']), ['ids' => [1]])
            ->assertForbidden();
    }

    public function test_bulk_model_tidak_dikenal_menghasilkan_404(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('admin.trash.restore-bulk', ['model-aneh']), ['ids' => [1]])
            ->assertNotFound();
    }

    public function test_bulk_ui_menampilkan_checkbox_dan_floating_bar(): void
    {
        $g1 = $this->makeGuru(['nama' => 'Guru Bulk UI']);
        $g1->delete();

        $this->actingAs($this->superAdmin())
            ->get(route('admin.trash.index'))
            ->assertOk()
            ->assertSee('Pilih Semua')
            ->assertSee('Restore Terpilih')
            ->assertSee('Hapus Permanen Terpilih')
            ->assertSee('restore-bulk', false)
            ->assertSee('force-delete-bulk', false);
    }
}
