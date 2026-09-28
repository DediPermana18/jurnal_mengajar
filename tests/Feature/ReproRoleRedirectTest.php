<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReproRoleRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUser(string $role, ?string $subRole, ?string $kode = null): User
    {
        return User::create([
            'nama' => 'User '.Str::random(5),
            'username' => 'user_'.Str::random(8),
            'password' => bcrypt('password123'),
            'kode_aktivasi' => $kode,
            'role' => $role,
            'sub_role' => $subRole,
            'is_active' => true,
        ]);
    }

    /** Guru Mapel harus DIARAHKAN ke portal guru, bukan dashboard admin. */
    public function test_guru_mapel_login_diarahkan_ke_portal_guru(): void
    {
        $guru = $this->makeUser('guru', 'guru_mapel');

        $this->post('/login', [
            'login_id' => $guru->username,
            'password' => 'password123',
            'mode' => 'guru',
        ])->assertRedirect(route('guru.dashboard'));
    }

    /** Guru Mapel yang login lalu buka "/" TIDAK BOLEH melihat dashboard admin. */
    public function test_guru_mapel_tidak_bisa_akses_dashboard_admin(): void
    {
        $guru = $this->makeUser('guru', 'guru_mapel');

        $this->actingAs($guru)
            ->get('/')
            ->assertDontSee('Total Guru');
    }

    /** Guru Mapel tidak boleh bisa buka /dashboard. */
    public function test_guru_mapel_tidak_bisa_akses_route_dashboard(): void
    {
        $guru = $this->makeUser('guru', 'guru_mapel');

        $this->actingAs($guru)
            ->get('/dashboard')
            ->assertDontSee('Total Guru');
    }

    /** Wali kelas juga harus dialihkan, bukan melihat dashboard admin. */
    public function test_wali_kelas_tidak_bisa_akses_dashboard_admin(): void
    {
        $wali = $this->makeUser('guru', 'wali_kelas');

        $this->actingAs($wali)
            ->get('/')
            ->assertDontSee('Total Guru');
    }

    /** Admin TU tetap harus bisa melihat dashboard admin. */
    public function test_admin_tu_masih_lihat_dashboard_admin(): void
    {
        $tu = $this->makeUser('admin', 'petugas_tu');

        $this->actingAs($tu)
            ->get('/')
            ->assertOk();
    }
}
