<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Form Tambah User Baru — field PASSWORD & KONFIRMASI PASSWORD.
 *
 *  - Form create menampilkan password + konfirmasi (dengan toggle mata Alpine);
 *    form edit TIDAK menampilkan keduanya (scope hanya create).
 *  - store() mewajibkan password (min 8, confirmed) dan menyimpan hasil
 *    Hash::make — user hasil create bisa langsung login (password + kode aktivasi).
 */
class UserCreatePasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function adminTu(): User
    {
        return User::create([
            'nama' => 'Admin TU',
            'username' => 'admin_tu_'.Str::random(6),
            'password' => 'password123',
            'role' => 'admin',
            'sub_role' => 'petugas_tu',
            'is_active' => true,
        ]);
    }

    public function test_halaman_create_menampilkan_field_password_dan_konfirmasi(): void
    {
        $admin = $this->adminTu();

        $this->actingAs($admin)
            ->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee('PASSWORD')
            ->assertSee('KONFIRMASI PASSWORD')
            ->assertSee('Minimal 8 karakter');
    }

    public function test_halaman_edit_tidak_menampilkan_field_password(): void
    {
        $admin = $this->adminTu();
        $user = User::create([
            'nama' => 'Waka Edit',
            'username' => 'waka_edit_'.Str::random(6),
            'password' => 'password123',
            'role' => 'admin',
            'sub_role' => 'waka_kurikulum',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.edit', $user->id))
            ->assertOk()
            ->assertDontSee('KONFIRMASI PASSWORD')
            ->assertDontSee('name="password"');
    }

    public function test_store_menyimpan_password_terhash(): void
    {
        $admin = $this->adminTu();
        $password = 'Rahasia!123';

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'User Password',
                'username' => 'user_pw_baru',
                'nip' => '111111',
                'no_hp' => '081111111111',
                'sub_role' => 'waka_sdm',
                'password' => $password,
                'password_confirmation' => $password,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.users.index'));

        $user = User::where('username', 'user_pw_baru')->firstOrFail();

        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertNotSame($password, $user->password);
        // Bukan lagi fallback lama "password = username".
        $this->assertFalse(Hash::check('user_pw_baru', $user->password));
    }

    public function test_store_menolak_password_kurang_dari_8_karakter(): void
    {
        $admin = $this->adminTu();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'User Pendek',
                'username' => 'user_pw_pendek',
                'nip' => null,
                'no_hp' => null,
                'sub_role' => 'waka_sdm',
                'password' => 'abc1234',
                'password_confirmation' => 'abc1234',
            ])
            ->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['username' => 'user_pw_pendek']);
    }

    public function test_store_menolak_konfirmasi_password_tidak_sama(): void
    {
        $admin = $this->adminTu();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'User Konfirmasi',
                'username' => 'user_pw_conf',
                'nip' => null,
                'no_hp' => null,
                'sub_role' => 'waka_sdm',
                'password' => 'rahasia123',
                'password_confirmation' => 'rahasia124',
            ])
            ->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['username' => 'user_pw_conf']);
    }

    public function test_store_menolak_tanpa_password(): void
    {
        $admin = $this->adminTu();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'User Tanpa PW',
                'username' => 'user_tanpa_pw',
                'nip' => null,
                'no_hp' => null,
                'sub_role' => 'waka_sdm',
            ])
            ->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['username' => 'user_tanpa_pw']);
    }

    public function test_user_baru_dapat_login_dengan_password_dan_kode_aktivasi(): void
    {
        $admin = $this->adminTu();
        $password = 'Rahasia!123';

        // Buat user lewat form (kode aktivasi ditentukan oleh admin).
        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'User Login',
                'username' => 'user_login_baru',
                'nip' => null,
                'no_hp' => null,
                'sub_role' => 'waka_sdm',
                'kode_aktivasi' => 'AKT-TEST01',
                'password' => $password,
                'password_confirmation' => $password,
            ])
            ->assertSessionHasNoErrors();

        $user = User::where('username', 'user_login_baru')->firstOrFail();

        // Login penuh: username + password + kode aktivasi (akun non-guru).
        Auth::logout();
        $this->post(route('login.post'), [
            'login_id' => $user->username,
            'password' => $password,
            'kode_aktivasi' => 'AKT-TEST01',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }
}