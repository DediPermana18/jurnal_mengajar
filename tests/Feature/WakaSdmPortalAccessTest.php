<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Portal Waka SDM (route /admin/waka-sdm/*) harus dapat diakses oleh SELURUH
 * role/sub-role Waka SDM / Kepegawaian, dan menu sidebar harus SELARAS dengan
 * otorisasi controller — tidak ada menu tanpa akses, tidak ada akses tanpa menu.
 *
 * Sebelum perbaikan: sidebar menampilkan navigasi SDM untuk role literal
 * 'sdm'/'admin_sdm' (layouts/app.blade.php $isWakaSdmRole), namun
 * authorizeWakaSdm() hanya mengizinkan admin+sub_role waka_sdm, role
 * 'waka_sdm', dan super admin → role 'sdm'/'admin_sdm' melihat menu
 * "Dashboard SDM / Portal Waka SDM" tetapi ditolak HTTP 403 saat diklik.
 */
class WakaSdmPortalAccessTest extends TestCase
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

    private function mkUser(string $username, string $role, ?string $subRole = null): User
    {
        return User::create([
            'username' => $username,
            'nama' => 'Uji '.$username,
            'email' => $username.'@school.id',
            'password' => 'secret',
            'role' => $role,
            'sub_role' => $subRole,
            'is_active' => true,
        ]);
    }

    public function test_seluruh_role_sdm_dapat_mengakses_dashboard(): void
    {
        $users = [
            'super_admin'         => $this->mkUser('sa', User::ROLE_SUPER_ADMIN),
            'super_admin_sub_role' => $this->mkUser('sas', User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN),
            'admin_utama'         => $this->mkUser('au', User::ROLE_ADMIN, null),
            'waka_sdm'            => $this->mkUser('ws', User::ROLE_ADMIN, 'waka_sdm'),
            'role_sdm'            => $this->mkUser('rs', 'sdm'),
            'role_admin_sdm'      => $this->mkUser('ras', 'admin_sdm'),
            'admin_sub_sdm'       => $this->mkUser('ass', User::ROLE_ADMIN, 'sdm'),
        ];

        foreach ($users as $label => $user) {
            $this->actingAs($user)
                ->get(route('waka-sdm.dashboard'))
                ->assertOk();
        }

        // Spot-check halaman internal portal lainnya untuk role sdm literal.
        $this->actingAs($this->mkUser('rs2', 'sdm'))
            ->get(route('waka-sdm.rekap-izin'))
            ->assertOk();

        $this->actingAs($this->mkUser('rs3', 'sdm'))
            ->get(route('waka-sdm.rekap-presensi-guru'))
            ->assertOk();
    }

    public function test_petugas_tu_tetap_ditolak(): void
    {
        $this->actingAs($this->petugasTu())
            ->get(route('waka-sdm.dashboard'))
            ->assertForbidden();

        $this->actingAs($this->petugasTu())
            ->get(route('waka-sdm.rekap-izin'))
            ->assertForbidden();
    }

    public function test_menu_sidebar_selaras_dengan_otorisasi(): void
    {
        // Role sdm literal: menu "Dashboard SDM" tampil DAN halaman terbuka.
        $this->actingAs($this->mkUser('rs', 'sdm'))
            ->get(route('waka-sdm.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard SDM')
            ->assertSee('WAKA SDM / KEPEGAWAIAN');

        // Petugas TU: menu "Portal Waka SDM" tidak muncul di sidebar admin.
        $this->actingAs($this->petugasTu())
            ->get(route('import.index'))
            ->assertOk()
            ->assertDontSee('Portal Waka SDM');

        // Super Admin: menu "Portal Waka SDM" tampil di sidebar admin.
        $this->actingAs($this->mkUser('sa', User::ROLE_SUPER_ADMIN))
            ->get(route('import.index'))
            ->assertOk()
            ->assertSee('Portal Waka SDM');
    }
}