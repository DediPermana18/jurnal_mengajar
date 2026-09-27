<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Kebijakan "Hidden Super Admin" di Manajemen User.
 *
 *  - Opsi sub-role istimewa ('super_admin' / 'admin') disembunyikan dari
 *    Petugas TU pada form Tambah/Edit.
 *  - Backend store/update melempar AuthorizationException (403) bila aktor
 *    non-privilege-manager mengirim payload sub_role 'super_admin' / 'admin'.
 *  - Akun Super Admin / Admin tidak memiliki tombol Edit/Delete/Suspend saat
 *    dilihat Petugas TU biasa (hanya badge "Dilindungi").
 *  - Super Admin (legacy admin+null / literal super_admin) & Petugas IT tetap
 *    dapat membuat dan mengelola akun istimewa.
 */
class ProtectedSuperAdminPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role = 'admin', ?string $subRole = 'petugas_tu'): User
    {
        return User::create([
            'nama' => 'User '.Str::random(4),
            'username' => 'user_'.Str::random(6),
            'password' => 'password123',
            'role' => $role,
            'sub_role' => $subRole,
            'is_active' => true,
        ]);
    }

    private function makeProtectedAccount(): User
    {
        return User::create([
            'nama' => 'Super Admin Rahasia',
            'username' => 'sa_'.Str::random(6),
            'password' => 'password123',
            'role' => 'admin',
            'sub_role' => 'super_admin',
            'is_active' => true,
        ]);
    }

    // ============ 1. FORM: opsi istimewa disembunyikan dari Petugas TU ============

    public function test_form_tambah_petugas_tu_tidak_menampilkan_opsi_super_admin_dan_admin(): void
    {
        $tu = $this->makeUser('admin', 'petugas_tu');

        $html = $this->actingAs($tu)
            ->get(route('admin.users.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('value="petugas_tu"', $html);
        $this->assertStringNotContainsString('value="super_admin"', $html);
        $this->assertStringNotContainsString('value="admin"', $html);
    }

    public function test_filter_sub_role_index_petugas_tu_tidak_menampilkan_opsi_istimewa(): void
    {
        $tu = $this->makeUser('admin', 'petugas_tu');
        $this->makeUser('admin', 'waka_kurikulum');

        $html = $this->actingAs($tu)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('value="petugas_tu"', $html);
        $this->assertStringNotContainsString('value="super_admin"', $html);
        $this->assertStringNotContainsString('value="admin"', $html);
    }

    public function test_form_tambah_super_admin_melihat_opsi_istimewa(): void
    {
        // Legacy Super Admin: role admin + sub_role null.
        $superAdmin = $this->makeUser('admin', null);
        $html = $this->actingAs($superAdmin)
            ->get(route('admin.users.create'))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('value="super_admin"', $html);
        $this->assertStringContainsString('value="admin"', $html);

        // Super Admin literal (role 'super_admin').
        $literal = $this->makeUser('super_admin', null);
        $html = $this->actingAs($literal)
            ->get(route('admin.users.create'))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('value="super_admin"', $html);
    }

    // ============ 2. BACKEND: store/update menolak sub-role istimewa ============

    public function test_store_petugas_tu_dengan_sub_role_super_admin_ditolak_403(): void
    {
        $tu = $this->makeUser('admin', 'petugas_tu');

        $this->actingAs($tu)
            ->post(route('admin.users.store'), [
                'name' => 'Anonim Super',
                'username' => 'anon_super_'.Str::random(6),
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'sub_role' => 'super_admin',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['nama' => 'Anonim Super']);
    }

    public function test_store_petugas_tu_dengan_sub_role_admin_ditolak_403(): void
    {
        $tu = $this->makeUser('admin', 'petugas_tu');

        $this->actingAs($tu)
            ->post(route('admin.users.store'), [
                'name' => 'Anonim Admin',
                'username' => 'anon_admin_'.Str::random(6),
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'sub_role' => 'admin',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['nama' => 'Anonim Admin']);
    }

    public function test_store_super_admin_membuat_akun_super_admin_berhasil(): void
    {
        $superAdmin = $this->makeUser('admin', null);

        $this->actingAs($superAdmin)
            ->post(route('admin.users.store'), [
                'name' => 'Super Baru',
                'username' => 'super_baru_'.Str::random(6),
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'sub_role' => 'super_admin',
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', ['nama' => 'Super Baru', 'sub_role' => 'super_admin']);
    }

    public function test_store_petugas_it_membuat_akun_super_admin_berhasil(): void
    {
        // IT/QA hidup di partisi testing; akun baru juga dibuat di partisi itu.
        $it = $this->makeUser('petugas_it', null);

        $this->actingAs($it)
            ->post(route('admin.users.store'), [
                'name' => 'Super IT',
                'username' => 'super_it_'.Str::random(6),
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'sub_role' => 'super_admin',
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', ['nama' => 'Super IT', 'sub_role' => 'super_admin']);
    }

    // ============ 3. TABEL: akun istimewa disembunyikan dari Petugas TU ============

    public function test_index_tu_tidak_menampilkan_akun_super_admin(): void
    {
        $tu = $this->makeUser('admin', 'petugas_tu');
        $protected = $this->makeProtectedAccount();
        $normal = $this->makeUser('admin', 'waka_kurikulum');

        $response = $this->actingAs($tu)
            ->get(route('admin.users.index'))
            ->assertOk();

        // Akun istimewa (sub_role 'super_admin') TIDAK TERLIHAT sama sekali di
        // daftar Kelola User — bukan sekadar tanpa tombol aksi.
        $response->assertDontSee($protected->username);
        $response->assertDontSee('Super Admin / Dilindungi');

        // Akun biasa tetap tampil beserta tombol aksinya.
        $response->assertSee($normal->nama);
        $response->assertSee(route('admin.users.edit', $normal->id));
        $response->assertSee(route('admin.users.toggle-suspend', $normal->id), false);
    }

    public function test_index_tu_juga_menyembunyikan_akun_role_super_admin(): void
    {
        $tu = $this->makeUser('admin', 'petugas_tu');
        // Akun literal role='super_admin' (sub_role null).
        $literal = User::create([
            'nama' => 'Super Literal',
            'username' => 'super_lit_'.Str::random(6),
            'password' => 'password123',
            'role' => 'super_admin',
            'sub_role' => null,
            'is_active' => true,
        ]);

        $this->actingAs($tu)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertDontSee($literal->username);
    }

    public function test_index_super_admin_melihat_akun_super_admin(): void
    {
        $superAdmin = $this->makeUser('admin', null);
        $protected = $this->makeProtectedAccount();

        $response = $this->actingAs($superAdmin)
            ->get(route('admin.users.index'))
            ->assertOk();

        // Super Admin melihat akun istimewa BESERTA tombol aksinya.
        $response->assertSee($protected->username);
        $response->assertSee(route('admin.users.edit', $protected->id));
        $response->assertSee(route('admin.users.toggle-suspend', $protected->id), false);
        $response->assertDontSee('Super Admin / Dilindungi');
    }

    public function test_detail_akun_super_admin_tersembunyi_dari_tu_404(): void
    {
        $tu = $this->makeUser('admin', 'petugas_tu');
        $protected = $this->makeProtectedAccount();

        // Halaman detail akun istimewa tidak terjangkau oleh Petugas TU (404),
        // konsisten dengan akun internal IT.
        $this->actingAs($tu)
            ->get(route('admin.users.edit', $protected->id))
            ->assertNotFound();
    }

    // ============ 3b. BACKEND MUTASI: target istimewa ditolak ============

    public function test_tu_tidak_bisa_suspend_akun_super_admin(): void
    {
        $tu = $this->makeUser('admin', 'petugas_tu');
        $protected = $this->makeProtectedAccount();

        // toggle-suspend (time-based) → target tidak terlihat → 404.
        $this->actingAs($tu)
            ->post(route('admin.users.toggle-suspend', $protected->id), ['duration' => '1h'])
            ->assertNotFound();

        // emergency-suspend (is_active=false + kick session) → 404.
        $this->actingAs($tu)
            ->post(route('users.emergency-suspend', $protected->id))
            ->assertNotFound();

        $this->assertNull($protected->fresh()->suspended_until);
        $this->assertTrue($protected->fresh()->is_active);
    }

    public function test_super_admin_bisa_suspend_akun_super_admin(): void
    {
        $superAdmin = $this->makeUser('admin', null);
        $protected = $this->makeProtectedAccount();

        $this->actingAs($superAdmin)
            ->post(route('admin.users.toggle-suspend', $protected->id), ['duration' => '1d'])
            ->assertRedirect();
    }

    public function test_update_petugas_tu_ke_sub_role_istimewa_ditolak_403(): void
    {
        $tu = $this->makeUser('admin', 'petugas_tu');
        $target = $this->makeUser('admin', 'waka_kurikulum');

        // Petugas TU tidak bisa meng-upgrade akun lain menjadi Super Admin.
        $this->actingAs($tu)
            ->put(route('admin.users.update', $target->id), [
                'name' => $target->nama,
                'username' => $target->username,
                'sub_role' => 'super_admin',
                'is_active' => 1,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'sub_role' => 'waka_kurikulum',
        ]);
    }
}