<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Proteksi Self-Deactivation & "Mode Lihat Saja" di Manajemen User.
 *
 *  - Self-protection: user TIDAK boleh menonaktifkan / menghapus / mengubah
 *    role/status akunnya sendiri melalui halaman Kelola User (HTTP 403).
 *  - Mode Lihat Saja: Petugas TU (non-IT) BOLEH membuka detail akun user lain,
 *    tetapi SELURUH field form terkunci readonly — perubahan data/kredensial
 *    (edit, toggle status, hapus) hanya dikelola Petugas IT / QA Tester.
 *  - Badge "Dikelola Petugas IT" dihapus; tombol ikon [Lihat Detail] (mata)
 *    ditampilkan kembali untuk TU, [Suspend Darurat] tetap tersedia.
 *  - Quick Reset Password langsung (reset ke username) TIDAK ADA lagi:
 *    tombol & route dihapus; seluruh reset lewat menu Pengajuan Reset
 *    Password (approve oleh TU).
 */
class UserSelfProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUser(string $subRole = 'waka_kurikulum', bool $active = true, bool $testing = false): User
    {
        return User::create([
            'nama' => 'User '.Str::random(4),
            'username' => 'user_'.Str::random(6),
            'password' => 'password123',
            'role' => 'admin',
            'sub_role' => $subRole,
            'is_active' => $active,
            // Petugas IT/QA hanya melihat partisi testing (TestingDataScope).
            'is_testing_data' => $testing,
        ]);
    }

    protected function makeTu(): User
    {
        return User::create([
            'nama' => 'Petugas TU '.Str::random(4),
            'username' => 'tu_'.Str::random(6),
            'password' => 'password123',
            'role' => 'admin',
            'sub_role' => 'petugas_tu',
            'is_active' => true,
        ]);
    }

    protected function makeIt(): User
    {
        return User::create([
            'nama' => 'Petugas IT '.Str::random(4),
            'username' => 'it_'.Str::random(6),
            'password' => 'password123',
            'role' => 'petugas_it',
            'sub_role' => null,
            'is_active' => true,
        ]);
    }

    // ================= SELF PROTECTION (tetap berlaku) =================

    public function test_user_cannot_deactivate_own_account(): void
    {
        $admin = $this->makeTu();

        $this->actingAs($admin)
            ->post(route('admin.users.toggle-status', $admin->id))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'is_active' => true]);
    }

    public function test_user_cannot_delete_own_account(): void
    {
        $admin = $this->makeTu();

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $admin->id))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_user_cannot_change_own_role_or_status_via_global_management(): void
    {
        $admin = $this->makeTu();

        $this->actingAs($admin)
            ->put(route('admin.users.update', $admin->id), [
                'name' => 'Nama Baru',
                'username' => $admin->username,
                'sub_role' => 'kepsek',
                'is_active' => 0,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'nama' => $admin->nama,
            'sub_role' => 'petugas_tu',
            'is_active' => true,
        ]);
    }

    public function test_user_cannot_open_own_edit_page(): void
    {
        $admin = $this->makeTu();

        $this->actingAs($admin)
            ->get(route('admin.users.edit', $admin->id))
            ->assertForbidden();
    }

    // ================= MODE LIHAT SAJA (Peer detail readonly) =================

    public function test_petugas_tu_tidak_bisa_toggle_status_admin_lain(): void
    {
        $tu = $this->makeTu();
        $target = $this->makeUser('waka_kurikulum');

        $this->actingAs($tu)
            ->post(route('admin.users.toggle-status', $target->id))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => true]);
    }

    public function test_petugas_tu_tidak_bisa_toggle_status_petugas_tu_lain(): void
    {
        $tu = $this->makeTu();
        $rekan = $this->makeUser('petugas_tu');

        $this->actingAs($tu)
            ->post(route('admin.users.toggle-status', $rekan->id))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $rekan->id, 'is_active' => true]);
    }

    public function test_petugas_tu_membuka_detail_admin_lain_dalam_mode_lihat_saja(): void
    {
        $tu = $this->makeTu();
        $target = $this->makeUser('waka_sdm');

        // Halaman detail/edit BOLEH dibuka oleh Petugas TU, tetapi seluruh form
        // terkunci readonly + tombol simpan diganti penanda "Terkunci".
        $this->actingAs($tu)
            ->get(route('admin.users.edit', $target->id))
            ->assertOk()
            ->assertSee('Mode Lihat Saja')
            ->assertSee('Simpan Perubahan (Terkunci)');
    }

    public function test_petugas_tu_tidak_bisa_simpan_perubahan_user_admin_lain(): void
    {
        $tu = $this->makeTu();
        $target = $this->makeUser('waka_kesiswaan');

        $this->actingAs($tu)
            ->put(route('admin.users.update', $target->id), [
                'name' => 'Disusupi',
                'username' => $target->username,
                'sub_role' => 'petugas_tu',
                'is_active' => 0,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'nama' => $target->nama,
            'sub_role' => 'waka_kesiswaan',
            'is_active' => true,
        ]);
    }

    public function test_petugas_tu_tidak_bisa_hapus_admin_lain(): void
    {
        $tu = $this->makeTu();
        $target = $this->makeUser('waka_piket');

        $this->actingAs($tu)
            ->delete(route('admin.users.destroy', $target->id))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_petugas_tu_membuka_detail_akun_legacy_dalam_mode_lihat_saja(): void
    {
        // Akun legacy role 'piket_satpam' pun kini masuk kebijakan "Mode Lihat
        // Saja": TU boleh membuka detail (readonly) tetapi tidak bisa toggle/ubah.
        $tu = $this->makeTu();
        $legacy = User::create([
            'nama' => 'Legacy Piket',
            'username' => 'legacy_'.Str::random(6),
            'password' => 'password123',
            'role' => 'piket_satpam',
            'sub_role' => null,
            'is_active' => true,
        ]);

        // Halaman detail terbuka dalam mode lihat saja.
        $this->actingAs($tu)
            ->get(route('admin.users.edit', $legacy->id))
            ->assertOk()
            ->assertSee('Mode Lihat Saja')
            ->assertSee('Simpan Perubahan (Terkunci)');

        // Toggle status akun legacy pun dilarang (hanya Petugas IT).
        $this->actingAs($tu)
            ->post(route('admin.users.toggle-status', $legacy->id))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $legacy->id, 'is_active' => true]);
    }

    public function test_petugas_it_masih_bisa_mengelola_akun_admin(): void
    {
        $it = $this->makeIt();
        // IT/QA hidup di partisi testing → akun yang dikelolanya harus is_testing_data=true.
        $target = $this->makeUser('waka_kurikulum', true, true);

        // IT tetap bisa membuka halaman edit akun admin (bukan mode lihat saja).
        $this->actingAs($it)
            ->get(route('admin.users.edit', $target->id))
            ->assertOk()
            ->assertDontSee('Mode Lihat Saja')
            ->assertDontSee('Simpan Perubahan (Terkunci)');

        // IT tetap bisa menonaktifkan / menghapus akun admin.
        $this->actingAs($it)
            ->post(route('admin.users.toggle-status', $target->id))
            ->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => false]);

        $this->actingAs($it)
            ->delete(route('admin.users.destroy', $target->id))
            ->assertRedirect(route('admin.users.index'));
        $this->assertSoftDeleted('users', ['id' => $target->id]);
    }

    // ================= RESET PASSWORD TERPUSAT (quick reset dihapus) =================

    public function test_non_it_tidak_bisa_mengubah_kode_aktivasi_lewat_update(): void
    {
        $tu = $this->makeTu();
        $legacy = User::create([
            'nama' => 'Legacy Piket',
            'username' => 'legacy_'.Str::random(6),
            'password' => 'password123',
            'role' => 'piket_satpam',
            'sub_role' => null,
            'is_active' => true,
            'kode_aktivasi' => 'AKT-ASLI001',
        ]);

        // Kebijakan "Mode Lihat Saja": Petugas TU tidak bisa menjalankan update
        // SAMA SEKALI (seluruh akun user lain) → HTTP 403, kode aktivasi aman.
        $this->actingAs($tu)
            ->put(route('admin.users.update', $legacy->id), [
                'name' => 'Legacy Piket',
                'username' => $legacy->username,
                'sub_role' => 'satpam',
                // Upaya menyuntikkan kode aktivasi baru dari form — harus ditolak.
                'kode_aktivasi' => 'AKT-DISUNTIK',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $legacy->id,
            'kode_aktivasi' => 'AKT-ASLI001',
        ]);
    }

    public function test_jalur_reset_password_langsung_telah_dihapus(): void
    {
        $tu = $this->makeTu();
        $target = $this->makeUser('waka_sdm');

        // Endpoint lama POST /admin/users/{id}/reset-password sudah tidak ada → 404.
        $this->actingAs($tu)
            ->post('/admin/users/'.$target->id.'/reset-password')
            ->assertNotFound();

        // Password tetap utuh (tidak di-reset ke username).
        $this->assertTrue(Hash::check('password123', $target->fresh()->password));
        $this->assertFalse(Hash::check($target->username, $target->fresh()->password));
    }

    public function test_halaman_index_tidak_menampilkan_tombol_reset_cepat(): void
    {
        $tu = $this->makeTu();
        $this->makeUser('waka_kurikulum');

        $this->actingAs($tu)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertDontSee('Reset password user ini ke username')
            ->assertDontSee('/reset-password');
    }

    // ================= RENDERING INDEX & MASKING =================

    public function test_kode_aktivasi_ditampilkan_tersensor_di_tabel(): void
    {
        $tu = $this->makeTu();
        User::create([
            'nama' => 'Waka Rahasia',
            'username' => 'waka_secret',
            'password' => 'password123',
            'role' => 'admin',
            'sub_role' => 'waka_kurikulum',
            'is_active' => true,
            'kode_aktivasi' => 'AKT-SECRET99',
        ]);

        $this->actingAs($tu)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('AKT-••••-99')
            ->assertDontSee('AKT-SECRET99');
    }

    public function test_index_marks_current_user_row_and_hides_all_action_buttons(): void
    {
        $tu = $this->makeTu();
        $other = $this->makeUser('waka_kurikulum');

        $response = $this->actingAs($tu)
            ->get(route('admin.users.index'))
            ->assertOk();

        // (1) Baris akun sendiri ditandai badge "Akun Anda Saat Ini".
        $response->assertSee('Akun Anda Saat Ini')
            ->assertSee('person-check-fill');

        // (2) Kolom AKSI untuk diri sendiri hanya berisi placeholder "Akun Aktif".
        $response->assertSee('Akun Aktif')
            ->assertDontSee(route('admin.users.edit', $tu->id))
            ->assertDontSee(route('admin.users.toggle-status', $tu->id))
            ->assertDontSee(route('admin.users.destroy', $tu->id));

        // (3) Untuk TU, akun admin lain tersedia tombol ikon [Lihat Detail]
        //     (mata) yang membuka mode lihat saja; tombol Edit/Toggle/Hapus
        //     disembunyikan. Badge "Dikelola Petugas IT" dihapus. Tombol
        //     [Suspend Darurat] tetap tersedia (jalur respons keamanan).
        $response->assertSee(route('admin.users.edit', $other->id))
            ->assertSee('bi-eye')
            ->assertDontSee('Dikelola Petugas IT')
            ->assertDontSee('bi-trash')
            ->assertDontSee('bi-slash-circle')
            ->assertSee(route('admin.users.toggle-suspend', $other->id), false);
    }

    public function test_index_tu_hanya_melihat_detail_untuk_akun_legacy(): void
    {
        $tu = $this->makeTu();
        $legacy = User::create([
            'nama' => 'Legacy Piket',
            'username' => 'legacy_'.Str::random(6),
            'password' => 'password123',
            'role' => 'piket_satpam',
            'sub_role' => null,
            'is_active' => true,
        ]);

        // Akun legacy pun hanya tampil tombol [Lihat Detail] + [Suspend Darurat].
        $this->actingAs($tu)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee(route('admin.users.edit', $legacy->id))
            ->assertDontSee('bi-trash')
            ->assertDontSee('bi-slash-circle')
            ->assertSee(route('admin.users.toggle-suspend', $legacy->id), false);
    }

    public function test_petugas_tu_masih_bisa_membuat_user_baru(): void
    {
        $tu = $this->makeTu();

        $this->actingAs($tu)
            ->post(route('admin.users.store'), [
                'name' => 'User Baru',
                'username' => 'baru_'.Str::random(6),
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'sub_role' => 'satpam',
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', ['nama' => 'User Baru', 'is_active' => true]);
    }

    // ================= AKUN INTERNAL IT / SYSTEM (disembunyikan dari TU) =================

    protected function makeInternalIt(string $username, string $nama, string $role): User
    {
        return User::create([
            'nama' => $nama,
            'username' => $username,
            'password' => 'password123',
            'role' => $role,
            'sub_role' => null,
            'is_active' => true,
        ]);
    }

    public function test_index_tu_tidak_menampilkan_akun_internal_it(): void
    {
        $tu = $this->makeTu();
        $this->makeInternalIt('petugas.it', 'Rian Hidayat, S.Kom.', 'petugas_it');
        $this->makeInternalIt('qa.tester', 'Dewi Puspita, S.Kom.', 'qa_tester');
        $normal = $this->makeUser('waka_kurikulum');

        $this->actingAs($tu)
            ->get(route('admin.users.index'))
            ->assertOk()
            // User umum tetap tampil...
            ->assertSee($normal->nama)
            // ...sedangkan akun internal IT / System disembunyikan.
            ->assertDontSee('petugas.it')
            ->assertDontSee('Rian Hidayat')
            ->assertDontSee('Dewi Puspita');
    }

    public function test_tu_tidak_bisa_membuka_detail_akun_internal_it(): void
    {
        $tu = $this->makeTu();
        $itAccount = $this->makeInternalIt('petugas.it', 'Rian Hidayat, S.Kom.', 'petugas_it');

        // Akun internal tidak hanya tersembunyi di daftar — akses langsung pun 404.
        $this->actingAs($tu)
            ->get(route('admin.users.edit', $itAccount->id))
            ->assertNotFound();

        // Mutasi (toggle/hapus/suspend) pada akun internal juga tidak terjangkau.
        $this->actingAs($tu)
            ->post(route('admin.users.toggle-status', $itAccount->id))
            ->assertNotFound();
    }

    public function test_petugas_it_masih_melihat_akun_internal_di_partisi_testing(): void
    {
        $it = $this->makeIt();
        // IT/QA hidup di partisi testing → akun internal harus is_testing_data=true.
        $itAccount = User::create([
            'nama' => 'Rian Hidayat, S.Kom.',
            'username' => 'petugas.it',
            'password' => 'password123',
            'role' => 'petugas_it',
            'sub_role' => null,
            'is_active' => true,
            'is_testing_data' => true,
        ]);

        $this->actingAs($it)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Rian Hidayat');

        // IT tetap bisa membuka detail akun internal.
        $this->actingAs($it)
            ->get(route('admin.users.edit', $itAccount->id))
            ->assertOk();
    }
}