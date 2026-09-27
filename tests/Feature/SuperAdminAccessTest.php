<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Akses penuh Super Admin di seluruh route admin.
 *
 *  - Middleware AdminScheduleAccess + Controller::isAuthorizedAdminArea harus
 *    mengizinkan role 'super_admin' ATAU sub_role 'super_admin' tanpa 403.
 *  - Gate::before memberikan bypass global (ability apa pun) bagi super_admin.
 *  - Route admin ber-guard sendiri (Mata Pelajaran) juga diizinkan.
 */
class SuperAdminAccessTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, ?string $subRole = null): User
    {
        return User::create([
            'nama' => 'User '.Str::random(4),
            'username' => 'user_'.Str::random(6),
            'password' => Hash::make('password123'),
            'role' => $role,
            'sub_role' => $subRole,
            'is_active' => true,
            'kode_aktivasi' => 'AKT-'.Str::upper(Str::random(8)),
        ]);
    }

    public function test_super_admin_role_dapat_mengakses_seluruh_halaman_admin(): void
    {
        $super = $this->makeUser('super_admin', null);

        // Melewati middleware AdminScheduleAccess + isAuthorizedAdminArea
        // pada seluruh area Data Master / admin (sebelumnya HTTP 403).
        $this->actingAs($super)
            ->get(route('admin.users.index'))
            ->assertOk();

        $this->actingAs($super)
            ->get(route('guru.index'))
            ->assertOk();

        $this->actingAs($super)
            ->get(route('kelas.index'))
            ->assertOk();
    }

    public function test_super_admin_lewat_sub_role_dapat_mengakses_halaman_admin(): void
    {
        // Aksara sub_role 'super_admin' (role tetap 'admin') juga diizinkan.
        $super = $this->makeUser('admin', 'super_admin');

        $this->actingAs($super)
            ->get(route('admin.users.index'))
            ->assertOk();

        $this->actingAs($super)
            ->get(route('guru.index'))
            ->assertOk();
    }

    public function test_super_admin_dapat_akses_route_mata_pelajaran(): void
    {
        // Route admin di luar group AdminScheduleAccess (guard di controller).
        $super = $this->makeUser('super_admin', null);

        $this->actingAs($super)
            ->get(route('mapel.index'))
            ->assertOk();
    }

    public function test_super_admin_dapat_mengakses_menu_jadwal_piket_guru(): void
    {
        // Menu sidebar "Jadwal Piket Guru" menuju /kurikulum/jadwal-piket
        // (guard di JadwalPiketController) — super_admin kedua varian diizinkan.
        $super = $this->makeUser('super_admin', null);

        $this->actingAs($super)
            ->get(route('kurikulum.jadwal-piket.index'))
            ->assertOk();

        $subSuper = $this->makeUser('admin', 'super_admin');

        $this->actingAs($subSuper)
            ->get(route('kurikulum.jadwal-piket.index'))
            ->assertOk();
    }

    public function test_non_super_admin_tetap_ditolak_403(): void
    {
        // Regresi: user non-admin (mis. guru) tetap 403.
        $guru = $this->makeUser('guru', null);

        $this->actingAs($guru)
            ->get(route('admin.users.index'))
            ->assertForbidden();

        $this->actingAs($guru)
            ->get(route('guru.index'))
            ->assertForbidden();
    }

    public function test_gate_memberikan_akses_penuh_untuk_super_admin(): void
    {
        $super = $this->makeUser('super_admin', null);
        $this->actingAs($super);
        $this->assertTrue(Gate::allows('manage-everything'));

        $subRoleSuper = $this->makeUser('admin', 'super_admin');
        $this->actingAs($subRoleSuper);
        $this->assertTrue(Gate::allows('manage-everything'));
    }

    public function test_gate_tetap_menolak_non_super_admin(): void
    {
        // Petugas TU biasa dilarang tapuh mengambil alih ability sesuka hati.
        $tu = $this->makeUser('admin', 'petugas_tu');
        $this->actingAs($tu);
        $this->assertFalse(Gate::allows('manage-everything'));
    }
}