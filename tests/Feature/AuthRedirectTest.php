<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUser(string $role, ?string $subRole, ?string $kodeAktivasi = null): User
    {
        return User::create([
            'nama'           => 'User ' . Str::random(5),
            'username'       => 'user_' . Str::random(8),
            'password'       => bcrypt('password123'),
            'kode_aktivasi'  => $kodeAktivasi,
            'role'           => $role,
            'sub_role'       => $subRole,
            'is_active'      => true,
        ]);
    }

    public function test_satpam_di_redirect_ke_dashboard_satpam(): void
    {
        $satpam = $this->makeUser('admin', 'satpam', 'satpam123');

        $this->post('/login', [
            'login_id'      => $satpam->username,
            'password'      => 'password123',
            'mode'          => 'admin',
            'kode_aktivasi' => 'satpam123',
        ])->assertRedirect(route('satpam.dashboard'));
    }

    public function test_role_lama_piket_satpam_di_redirect_ke_dashboard_satpam(): void
    {
        $satpam = $this->makeUser('piket_satpam', null);

        $this->post('/login', [
            'login_id' => $satpam->username,
            'password' => 'password123',
            'mode'     => 'guru',
        ])->assertRedirect(route('satpam.dashboard'));
    }

    public function test_admin_tu_di_redirect_ke_dashboard_admin(): void
    {
        $tu = $this->makeUser('admin', 'petugas_tu', 'admin123');

        $this->post('/login', [
            'login_id'      => $tu->username,
            'password'      => 'password123',
            'mode'          => 'admin',
            'kode_aktivasi' => 'admin123',
        ])->assertRedirect(route('home'));
    }

    public function test_guru_di_redirect_ke_dashboard_guru(): void
    {
        $guru = $this->makeUser('guru', 'guru');

        $this->post('/login', [
            'login_id' => $guru->username,
            'password' => 'password123',
            'mode'     => 'guru',
        ])->assertRedirect(route('guru.dashboard'));
    }
}