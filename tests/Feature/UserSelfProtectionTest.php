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
 *    ditampilkan kembali untuk TU, [Suspend Darurat] hanya tersedia untuk
 *    akun lain.
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

    public function test_user_cannot_suspend_own_account_through_either_suspend_endpoint(): void
    {
        $admin = $this->makeTu();

        $this->actingAs($admin)
            ->post(route('admin.users.toggle-suspend', $admin->id))
            ->assertForbidden();

        $this->postJson(route('users.emergency-suspend', $admin->id))
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'is_active' => true,
            'suspended_until' => null,
        ]);
    }

    public function test_status_nonaktif_diaktifkan_dengan_flash_aktivasi_biasa(): void
    {
        $it = $this->makeIt();
        $target = $this->makeUser('waka_kurikulum', false, true);

        $html = $this->actingAs($it)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(route('admin.users.toggle-status', $target->id), $html);
        $this->assertStringContainsString('bi-check-circle', $html);
        $this->assertStringNotContainsString(
            'data-suspend-url="'.route('admin.users.toggle-suspend', $target->id).'"',
            $html
        );
        $this->assertStringNotContainsString(
            'action="'.route('admin.users.toggle-suspend', $target->id).'"',
            $html
        );

        $this->post(route('admin.users.toggle-status', $target->id))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success', 'Akun berhasil diaktifkan kembali.');

        $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => true]);

        $this->post(route('admin.users.toggle-status', $target->id))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success', 'Akun berhasil dinonaktifkan.');

        $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => false]);
    }

    public function test_status_suspend_tidak_bisa_diubah_dengan_toggle_status(): void
    {
        $it = $this->makeIt();
        $target = $this->makeUser('waka_kurikulum', true, true);
        $target->update(['suspended_until' => now()->addHour()]);

        $this->actingAs($it)
            ->post(route('admin.users.toggle-status', $target->id))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => true]);
    }

    public function test_status_suspend_menampilkan_unsuspend_tanpa_toggle_status(): void
    {
        $it = $this->makeIt();
        $target = $this->makeUser('waka_kurikulum', true, true);
        $target->update(['suspended_until' => now()->addHour()]);

        $html = $this->actingAs($it)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Di-Suspend', $html);
        $this->assertStringContainsString(
            'action="'.route('admin.users.toggle-suspend', $target->id).'"',
            $html
        );
        $this->assertStringNotContainsString(
            'action="'.route('admin.users.toggle-status', $target->id).'"',
            $html
        );
    }

    public function test_user_can_open_own_edit_page_and_update_profile(): void
    {
        $admin = $this->makeTu();

        // User dapat membuka form edit untuk profilnya sendiri
        $this->actingAs($admin)
            ->get(route('admin.users.edit', $admin->id))
            ->assertOk()
            ->assertDontSee('Akun Aktif')
            ->assertSee('Edit User');

        // User dapat memperbarui nama / no_hp sendiri
        $this->actingAs($admin)
            ->put(route('admin.users.update', $admin->id), [
                'name' => 'Nama Baru Petugas',
                'username' => $admin->username,
                'sub_role' => 'petugas_tu',
                'no_hp' => '08123456789',
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'nama' => 'Nama Baru Petugas',
            'no_hp' => '08123456789',
            'is_active' => true,
        ]);
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

    public function test_index_menandai_akun_sendiri_dengan_badge_akun_online(): void
    {
        $tu = $this->makeTu();
        $other = $this->makeUser('waka_kurikulum');

        $html = $this->actingAs($tu)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->getContent();

        // (1) Baris akun sendiri ditandai badge "Akun Anda (Online)" pada kolom AKSI
        //     (indikator online) + badge "Akun Anda Saat Ini" pada kolom NAMA.
        $this->assertStringContainsString('Akun Anda (Online)', $html);
        $this->assertStringContainsString('bg-primary', $html);
        $this->assertStringContainsString('bi-person-check', $html);
        $this->assertStringContainsString('Akun Anda Saat Ini', $html);

        // (2) Aksi kelola untuk akun sendiri DISEMBUNYIKAN: Edit (mata + pensil),
        //     Nonaktifkan, dan Hapus tidak boleh muncul untuk id sendiri.
        $this->assertStringNotContainsString(route('admin.users.edit', $tu->id), $html);
        $this->assertStringNotContainsString(route('admin.users.toggle-status', $tu->id), $html);
        $this->assertStringNotContainsString(route('admin.users.destroy', $tu->id), $html);
        $this->assertStringNotContainsString('Edit Profil', $html);

        // (3) Suspend Darurat juga sepenuhnya disembunyikan dari baris sendiri.
        $this->assertStringNotContainsString(route('admin.users.toggle-suspend', $tu->id), $html);
        $this->assertDoesNotMatchRegularExpression(
            '/<button[^>]*disabled[^>]*>.*?Suspend Darurat/s',
            $html
        );

        // (4) Untuk TU, akun admin lain tersedia tombol ikon [Lihat Detail]
        //     (mata) yang membuka mode lihat saja; tombol Edit/Toggle/Hapus
        //     disembunyikan. Badge "Dikelola Petugas IT" dihapus. Tombol
        //     [Suspend Darurat] tetap aktif (jalur respons keamanan).
        $this->assertStringContainsString(route('admin.users.edit', $other->id), $html);
        $this->assertStringContainsString('bi-eye', $html);
        $this->assertStringNotContainsString('Dikelola Petugas IT', $html);
        $this->assertStringNotContainsString('bi-trash', $html);
        $this->assertStringNotContainsString('bi-slash-circle', $html);
        $this->assertStringContainsString(route('admin.users.toggle-suspend', $other->id), $html);
    }

    public function test_index_menampilkan_badge_online_dan_menyembunyikan_hapus_untuk_user_aktif(): void
    {
        // Privilege manager (role 'admin' + sub_role null): boleh mengedit user
        // lain dan melihat seluruh partisi (bukan Petugas IT yang ter-scope testing).
        $viewer = User::create([
            'nama' => 'Super Admin',
            'username' => 'sa_'.Str::random(6),
            'password' => 'password123',
            'role' => 'admin',
            'sub_role' => null,
            'is_active' => true,
        ]);

        // Target "sedang online": ada jejak aktivitas < 5 menit yang lalu.
        $online = User::create([
            'nama' => 'Waka Online',
            'username' => 'waka_online_'.Str::random(4),
            'password' => 'password123',
            'role' => 'admin',
            'sub_role' => 'waka_kurikulum',
            'is_active' => true,
            'last_active_at' => now(),
        ]);

        $html = $this->actingAs($viewer)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->getContent();

        $this->assertTrue($online->fresh()->isOnline());

        // (1) Badge "Online" (titik hijau) tampil untuk baris user aktif.
        $this->assertStringContainsString('bi-circle-fill', $html);
        $this->assertStringContainsString('Aktif dalam '.User::ONLINE_WINDOW_MINUTES.' menit terakhir', $html);

        // (2) Edit & Suspend Darurat TETAP tersedia untuk user online.
        $this->assertStringContainsString(route('admin.users.edit', $online->id), $html);
        $this->assertStringContainsString(route('admin.users.toggle-suspend', $online->id), $html);

        // (3) Hapus disembunyikan selama akun online. Form tetap dirender agar
        //     polling AJAX bisa menampilkannya lagi secara dinamis; statusnya
        //     ditunjukkan lewat kelas `d-none` (bukan ketiadaan form).
        $this->assertStringContainsString('action="'.route('admin.users.destroy', $online->id).'"', $html);
        $this->assertStringContainsString('js-delete-action d-none', $html);
    }

    public function test_index_menampilkan_tombol_hapus_untuk_user_offline(): void
    {
        $viewer = User::create([
            'nama' => 'Super Admin',
            'username' => 'sa_'.Str::random(6),
            'password' => 'password123',
            'role' => 'admin',
            'sub_role' => null,
            'is_active' => true,
        ]);

        $offline = User::create([
            'nama' => 'Waka Offline',
            'username' => 'waka_offline_'.Str::random(4),
            'password' => 'password123',
            'role' => 'admin',
            'sub_role' => 'waka_kurikulum',
            'is_active' => true,
            'last_active_at' => now()->subMinutes(User::ONLINE_WINDOW_MINUTES + 5),
        ]);

        $html = $this->actingAs($viewer)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->getContent();

        $this->assertFalse($offline->fresh()->isOnline());

        // Tanpa badge Online, tombol aksi lengkap — termasuk Hapus (tanpa `d-none`).
        // Cek marker spesifik badge offline (diluar `bi-circle-fill` yang muncul
        // di bagian lain halaman). Kita pastikan badge kelas js-online-badge,
        // data-online="0", serta label Offline hadir.
        $this->assertStringContainsString('js-online-badge', $html);
        $this->assertStringContainsString('data-online="0"', $html);
        $this->assertStringContainsString('js-online-label">Offline', $html);
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

        // Akun legacy lain pun hanya tampil tombol [Lihat Detail] + [Suspend Darurat].
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

    public function test_super_admin_bisa_membuka_edit_dan_mengubah_user_lain(): void
    {
        $superAdmin = User::create([
            'nama' => 'Super Admin',
            'username' => 'superadmin_'.Str::random(5),
            'password' => 'password123',
            'role' => 'admin',
            'sub_role' => 'super_admin',
            'is_active' => true,
        ]);
        $target = $this->makeUser('waka_kurikulum');

        // Super Admin dapat membuka form edit user lain (bukan readonly)
        $this->actingAs($superAdmin)
            ->get(route('admin.users.edit', $target->id))
            ->assertOk()
            ->assertDontSee('Mode Lihat Saja')
            ->assertSee('Simpan Perubahan');

        // Super Admin dapat memperbarui nama, no_hp, nip, username
        $this->actingAs($superAdmin)
            ->put(route('admin.users.update', $target->id), [
                'name' => 'Nama Diedit Super Admin',
                'username' => $target->username,
                'sub_role' => 'waka_kurikulum',
                'no_hp' => '089988776655',
                'nip' => '1987654321',
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'nama' => 'Nama Diedit Super Admin',
            'no_hp' => '089988776655',
            'nip' => '1987654321',
        ]);
    }
}