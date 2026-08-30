<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_bergerak_berdasarkan_identifier_unik_dan_password_user_tsb(): void
    {
        // Guru dengan password 'password123'
        $guru = User::create([
            'nama'      => 'Guru A',
            'username'  => 'guru_a',
            'email'     => 'guru_a@school.id',
            'password'  => Hash::make('password123'),
            'role'      => 'guru',
            'sub_role'  => 'guru_mapel',
            'is_active' => true,
        ]);

        // Admin TU dengan password 'password'
        User::create([
            'nama'      => 'Admin TU',
            'username'  => 'admin_tu',
            'email'     => 'admin_tu@school.id',
            'password'  => Hash::make('password'),
            'role'      => 'admin',
            'sub_role'  => 'petugas_tu',
            'is_active' => true,
        ]);

        // Login guru dengan password yang BENAR untuk user tsb -> harus Guru, bukan Admin.
        $this->post(route('login.post'), [
            'login_id' => 'guru_a',
            'password' => 'password123',
            'mode'     => 'guru',
        ])->assertRedirect(route('guru.dashboard'));

        $this->assertAuthenticatedAs($guru);
        $this->assertEquals('guru', auth()->user()->role);
    }

    public function test_password_salah_untuk_user_yang_diresolve_mengembalikan_error(): void
    {
        User::create([
            'nama'      => 'Guru A',
            'username'  => 'guru_a',
            'email'     => 'guru_a@school.id',
            'password'  => Hash::make('password123'),
            'role'      => 'guru',
            'sub_role'  => 'guru_mapel',
            'is_active' => true,
        ]);

        // Password 'password' (milik user lain) TIDAK valid untuk guru_a
        $this->post(route('login.post'), [
            'login_id' => 'guru_a',
            'password' => 'password',
            'mode'     => 'guru',
        ])->assertSessionHasErrors('password');

        $this->assertGuest();
    }
}