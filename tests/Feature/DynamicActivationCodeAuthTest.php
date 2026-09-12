<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicActivationCodeAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guru_can_login_without_activation_code(): void
    {
        $guru = User::create([
            'nama' => 'Budi Santoso',
            'username' => 'budi.santoso',
            'password' => bcrypt('password123'),
            'role' => 'guru',
            'sub_role' => 'guru_mapel',
            'kode_aktivasi' => null,
            'is_active' => true,
        ]);

        $this->post('/login', [
            'login_id' => 'budi.santoso',
            'password' => 'password123',
        ])->assertRedirect(route('guru.dashboard'));

        $this->assertAuthenticatedAs($guru);
    }

    public function test_admin_fails_login_without_matching_db_activation_code(): void
    {
        $admin = User::create([
            'nama' => 'Admin TU',
            'username' => 'admin.tu',
            'password' => bcrypt('password123'),
            'role' => 'admin',
            'sub_role' => 'petugas_tu',
            'kode_aktivasi' => 'ADM-SECURE-88',
            'is_active' => true,
        ]);

        // Wrong code
        $response = $this->post('/login', [
            'login_id' => 'admin.tu',
            'password' => 'password123',
            'kode_aktivasi' => 'WRONG-CODE-123',
        ]);

        $response->assertSessionHasErrors(['kode_aktivasi' => 'Kode aktivasi tidak valid.']);
        $this->assertGuest();
    }

    public function test_admin_fails_login_with_legacy_hardcoded_code(): void
    {
        User::create([
            'nama' => 'Admin TU',
            'username' => 'admin.tu',
            'password' => bcrypt('password123'),
            'role' => 'admin',
            'sub_role' => 'petugas_tu',
            'kode_aktivasi' => 'ADM-SECURE-88',
            'is_active' => true,
        ]);

        // Legacy hardcoded string 'ADMIN123' or 'WEBJOURNAL2026' must fail
        $response = $this->post('/login', [
            'login_id' => 'admin.tu',
            'password' => 'password123',
            'kode_aktivasi' => 'ADMIN123',
        ]);

        $response->assertSessionHasErrors(['kode_aktivasi' => 'Kode aktivasi tidak valid.']);
        $this->assertGuest();
    }

    public function test_admin_succeeds_login_with_matching_db_activation_code(): void
    {
        $admin = User::create([
            'nama' => 'Admin TU',
            'username' => 'admin.tu',
            'password' => bcrypt('password123'),
            'role' => 'admin',
            'sub_role' => 'petugas_tu',
            'kode_aktivasi' => 'ADM-SECURE-88',
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'login_id' => 'admin.tu',
            'password' => 'password123',
            'kode_aktivasi' => 'ADM-SECURE-88',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($admin);
    }
}
