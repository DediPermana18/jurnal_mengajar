<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pembatasan aksi reset data massal "Zona Berbahaya" pada halaman Import Data.
 *
 * Kartu "Zona Berbahaya — Reset Data" (Siswa, Guru, Kelas/Jurusan, Ruangan,
 * Jadwal) HANYA boleh dilihat & dieksekusi oleh Super Admin — dikenali dari
 * role ATAU sub_role 'super_admin' (User::isSuperAdmin()).
 *
 * - Petugas TU / akun non-Super Admin: kartu hilang total dari halaman dan
 *   seluruh endpoint reset menolak dengan HTTP 403 + data tetap utuh.
 * - Super Admin (role atau sub_role 'super_admin'): kartu tampil dan aksi
 *   reset berjalan normal.
 */
class BulkResetAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UserSeeder::class);
    }

    private function petugasTu(): User
    {
        return User::where('email', 'admin@school.id')->firstOrFail();
    }

    private function superAdmin(?string $role = null, ?string $subRole = null): User
    {
        return User::create([
            'username' => 'super.admin',
            'nama' => 'Super Admin',
            'email' => 'super.admin@school.id',
            'password' => 'secret',
            'role' => $role ?? User::ROLE_SUPER_ADMIN,
            'sub_role' => $subRole,
            'is_active' => true,
        ]);
    }

    private function seedSiswa(int $jumlah = 2): void
    {
        $kelas = Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'TKJ 1']);

        for ($i = 1; $i <= $jumlah; $i++) {
            Siswa::create([
                'nisn' => (string) (1000000000 + $i),
                'nis' => (string) (5000 + $i),
                'nama' => "Siswa Uji {$i}",
                'jenis_kelamin' => 'L',
                'id_kelas' => $kelas->id,
                'status_siswa' => 'Aktif',
            ]);
        }
    }

    public function test_petugas_tu_tidak_melihat_kartu_zona_berbahaya(): void
    {
        $this->actingAs($this->petugasTu())
            ->get(route('import.index'))
            ->assertOk()
            ->assertDontSee('Zona Berbahaya')
            ->assertDontSee('Reset / Hapus Semua Data Siswa')
            ->assertDontSee('Reset / Hapus Semua Data Guru')
            ->assertDontSee('Reset / Hapus Semua Data Kelas')
            ->assertDontSee('Reset / Hapus Semua Data Ruangan')
            ->assertDontSee('Reset / Hapus Semua Jadwal Pelajaran');
    }

    public function test_super_admin_melihat_kartu_zona_berbahaya(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('import.index'))
            ->assertOk()
            ->assertSee('Zona Berbahaya')
            ->assertSee('Reset / Hapus Semua Data Siswa')
            ->assertSee('Reset / Hapus Semua Data Guru')
            ->assertSee('Reset / Hapus Semua Data Kelas &amp; Jurusan', false)
            ->assertSee('Reset / Hapus Semua Data Ruangan')
            ->assertSee('Reset / Hapus Semua Jadwal Pelajaran');
    }

    public function test_super_admin_via_sub_role_melihat_kartu_zona_berbahaya(): void
    {
        $super = $this->superAdmin(User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN);

        $this->actingAs($super)
            ->get(route('import.index'))
            ->assertOk()
            ->assertSee('Zona Berbahaya')
            ->assertSee('Reset / Hapus Semua Data Siswa');
    }

    public function test_endpoint_reset_menolak_petugas_tu_dan_data_tetap_utuh(): void
    {
        $this->seedSiswa(2);

        $endpoints = [
            'import.reset-siswa',
            'import.reset-guru',
            'import.reset-kelas-jurusan',
            'import.reset-ruangan',
            'import.reset-jadwal',
        ];

        foreach ($endpoints as $name) {
            $this->actingAs($this->petugasTu())
                ->post(route($name))
                ->assertForbidden();
        }

        // Tidak satu pun percobaan reset yang menghapus data.
        $this->assertDatabaseCount('siswa', 2);
        $this->assertDatabaseCount('kelas', 1);
    }

    public function test_super_admin_dapat_reset_data_siswa(): void
    {
        $this->seedSiswa(3);

        $this->actingAs($this->superAdmin())
            ->post(route('import.reset-siswa'), ['reset_confirm' => 'HAPUS DATA SISWA'])
            ->assertRedirect(route('import.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('siswa', 0);
        // Struktur lain (kelas) tidak ikut terhapus oleh reset siswa.
        $this->assertDatabaseCount('kelas', 1);
    }
}