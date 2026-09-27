<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * State Suspend & Aksi pada Halaman Edit User.
 *
 *  - State "disuspend" terpadu = suspend sementara (suspended_until > now)
 *    ATAU suspend permanen (is_active = false via emergency suspend).
 *  - Checkbox "Akun aktif" pada form edit dikunci UNCHECKED + disabled selama
 *    akun disuspend; hanya tombol "Unsuspend / Aktifkan Kembali" yang
 *    mereaktivasi.
 *  - Akun aktif → tombol "Suspend Darurat" (merah) + dropdown durasi.
 *  - Akun disuspend → tombol "Unsuspend / Aktifkan Kembali" (hijau), dropdown
 *    durasi disembunyikan.
 *  - Controller toggleSuspend(action=unsuspend) menghapus suspended_until dan
 *    mengembalikan is_active = true.
 */
class SuspendStateEditPageTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $subRole = 'waka_kurikulum', bool $active = true): User
    {
        return User::create([
            'nama' => 'User '.Str::random(4),
            'username' => 'user_'.Str::random(6),
            'password' => 'password123',
            'role' => 'admin',
            'sub_role' => $subRole,
            'is_active' => $active,
        ]);
    }

    private function makeTu(): User
    {
        return $this->makeUser('petugas_tu');
    }

    private function makeIt(): User
    {
        return User::create([
            'nama' => 'Petugas IT',
            'username' => 'petugas_it_'.Str::random(6),
            'password' => 'password123',
            'role' => 'petugas_it',
            'sub_role' => null,
            'is_active' => true,
            'is_testing_data' => true,
        ]);
    }

    // ================= 1 & 2. STATE UI PADA HALAMAN EDIT =================

    public function test_edit_page_akun_disuspend_sementara_menampilkan_unsuspend_tanpa_dropdown(): void
    {
        $tu = $this->makeTu();
        $target = $this->makeUser('waka_kurikulum');
        $target->update(['suspended_until' => now()->addHour()]);

        $html = $this->actingAs($tu)
            ->get(route('admin.users.edit', $target->id))
            ->assertOk()
            ->getContent();

        // Badge state + tombol unsuspend hijau; dropdown durasi disembunyikan.
        $this->assertStringContainsString('Sedang disuspend sementara', $html);
        $this->assertStringContainsString('Unsuspend / Aktifkan Kembali', $html);
        $this->assertStringContainsString('btn-outline-success', $html);
        // Tombol suspend (btn-outline-danger) + dropdown durasinya TIDAK dirender.
        $this->assertStringNotContainsString('btn-outline-danger', $html);
        $this->assertStringNotContainsString('name="duration"', $html);

        // Checkbox "Akun aktif": UNCHECKED + disabled.
        $this->assertMatchesRegularExpression('/name="is_active"[^>]*disabled/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="is_active"[^>]*checked/', $html);
    }

    public function test_edit_page_akun_nonaktif_permanen_menampilkan_unsuspend(): void
    {
        $tu = $this->makeTu();
        $target = $this->makeUser('waka_kurikulum', false);

        $html = $this->actingAs($tu)
            ->get(route('admin.users.edit', $target->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Akun Dinonaktifkan (Suspend Permanen)', $html);
        $this->assertStringContainsString('Unsuspend / Aktifkan Kembali', $html);
        $this->assertStringContainsString('btn-outline-success', $html);
        $this->assertStringNotContainsString('btn-outline-danger', $html);
        $this->assertStringNotContainsString('name="duration"', $html);

        $this->assertMatchesRegularExpression('/name="is_active"[^>]*disabled/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="is_active"[^>]*checked/', $html);
    }

    public function test_edit_page_akun_aktif_menampilkan_suspend_darurat_dengan_dropdown(): void
    {
        // Petugas IT (bukan readonly) agar nilai disabled checkbox hanya berasal
        // dari state suspend — bukan dari Mode Lihat Saja.
        $it = $this->makeIt();
        $target = User::create([
            'nama' => 'User Skor Aktif',
            'username' => 'aktif_'.Str::random(6),
            'password' => 'password123',
            'role' => 'admin',
            'sub_role' => 'waka_kurikulum',
            'is_active' => true,
            'is_testing_data' => true,
        ]);

        $html = $this->actingAs($it)
            ->get(route('admin.users.edit', $target->id))
            ->assertOk()
            ->getContent();

        // Akun aktif: tombol suspend merah + dropdown durasi; tanpa tombol unsuspend.
        $this->assertStringContainsString('Suspend Darurat', $html);
        $this->assertStringContainsString('name="duration"', $html);
        $this->assertStringContainsString('1 Jam', $html);
        $this->assertStringContainsString('1 Hari', $html);
        $this->assertStringNotContainsString('Unsuspend / Aktifkan Kembali', $html);

        // Checkbox "Akun aktif": CHECKED + tidak disabled (tidak disuspend).
        $this->assertMatchesRegularExpression('/name="is_active"[^>]*checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="is_active"[^>]*disabled/', $html);
    }

    // ================= 3. CONTROLLER: ACTION UNSUSPEND =================

    public function test_unsuspend_menghapus_suspended_until_dan_mengembalikan_aktif(): void
    {
        $tu = $this->makeTu();
        $target = $this->makeUser('waka_kurikulum');
        $target->update(['suspended_until' => now()->addHour()]);

        $this->actingAs($tu)
            ->post(route('admin.users.toggle-suspend', $target->id), ['action' => 'unsuspend'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $fresh = $target->fresh();
        $this->assertNull($fresh->suspended_until);
        $this->assertTrue($fresh->is_active);
        $this->assertFalse($fresh->isCurrentlySuspended());
    }

    public function test_unsuspend_mengaktifkan_akun_nonaktif_permanen(): void
    {
        $tu = $this->makeTu();
        $target = $this->makeUser('waka_kurikulum', false);

        $this->actingAs($tu)
            ->post(route('admin.users.toggle-suspend', $target->id), ['action' => 'unsuspend'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $fresh = $target->fresh();
        $this->assertTrue($fresh->is_active);
        $this->assertNull($fresh->suspended_until);
        $this->assertFalse($fresh->isCurrentlySuspended());
    }

    public function test_unsuspend_tidak_bisa_diri_sendiri_atau_akun_utama(): void
    {
        $tu = $this->makeTu();
        $admin = User::create([
            'nama' => 'Akun Utama',
            'username' => 'admin',
            'password' => 'password123',
            'role' => 'admin',
            'sub_role' => 'petugas_tu',
            'is_active' => true,
        ]);

        // Tidak boleh unsuspend akun sendiri.
        $this->actingAs($tu)
            ->post(route('admin.users.toggle-suspend', $tu->id), ['action' => 'unsuspend'])
            ->assertForbidden();

        // Akun utama 'admin' tetap diproteksi dari segala aksi suspend.
        $this->actingAs($tu)
            ->post(route('admin.users.toggle-suspend', $admin->id), ['action' => 'unsuspend'])
            ->assertForbidden();
    }

    // ================= GARDIAN UPDATE FORM =================

    public function test_update_form_saat_akun_nonaktif_tidak_mengaktifkan_ulang(): void
    {
        $it = $this->makeIt();
        $target = User::create([
            'nama' => 'User Testing',
            'username' => 'user_t_'.Str::random(6),
            'password' => 'password123',
            'role' => 'admin',
            'sub_role' => 'waka_sdm',
            'is_active' => false, // suspend permanen
            'is_testing_data' => true,
        ]);

        // Checkbox "Akun aktif" dikunci (disabled) saat disuspend sehingga tidak
        // ikut terkirim — menyimpan form identitas TIDAK boleh mengaktifkan ulang.
        $this->actingAs($it)
            ->put(route('admin.users.update', $target->id), [
                'name' => 'User Testing Baru',
                'username' => $target->username,
                'sub_role' => 'waka_sdm',
            ])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success');

        $fresh = $target->fresh();
        $this->assertFalse($fresh->is_active);
        $this->assertNull($fresh->suspended_until);
        $this->assertEquals('User Testing Baru', $fresh->nama);
    }

    public function test_update_form_akun_disuspend_tidak_menghapus_batas_waktu(): void
    {
        $it = $this->makeIt();
        $target = User::create([
            'nama' => 'User Testing',
            'username' => 'user_t_'.Str::random(6),
            'password' => 'password123',
            'role' => 'admin',
            'sub_role' => 'waka_sdm',
            'is_active' => true,
            'is_testing_data' => true,
        ]);
        $target->update(['suspended_until' => now()->addHour()]);

        $this->actingAs($it)
            ->put(route('admin.users.update', $target->id), [
                'name' => 'User Testing Baru',
                'username' => $target->username,
                'sub_role' => 'waka_sdm',
            ])
            ->assertRedirect(route('admin.users.index'));

        $fresh = $target->fresh();
        // Batas suspend sementara TIDAK terhapus oleh simpan form identitas.
        $this->assertTrue($fresh->isCurrentlySuspended());
        $this->assertTrue($fresh->suspended_until->greaterThan(now()->addMinutes(30)));
    }

    // ================= INDEX: TOMBOL AKSI DI TABEL =================

    public function test_index_akun_disuspend_menampilkan_tombol_unsuspend_hijau(): void
    {
        $tu = $this->makeTu();
        $suspended = $this->makeUser('waka_kurikulum');
        $suspended->update(['suspended_until' => now()->addHour()]);

        $html = $this->actingAs($tu)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->getContent();

        $suspendUrl = route('admin.users.toggle-suspend', $suspended->id);

        // Tombol UNSUSPEND hijau (form aksi langsung) menggantikan tombol modal.
        $this->assertStringContainsString('Unsuspend', $html);
        $this->assertStringContainsString('btn-outline-success', $html);
        $this->assertStringContainsString('name="action" value="unsuspend"', $html);
        $this->assertStringContainsString('action="'.$suspendUrl.'"', $html);
        // Modal-trigger suspend (data-suspend-url) TIDAK dirender untuk akun ini.
        $this->assertStringNotContainsString('data-suspend-url="'.$suspendUrl.'"', $html);
    }

    public function test_index_akun_nonaktif_permanen_menampilkan_tombol_unsuspend(): void
    {
        $tu = $this->makeTu();
        $off = $this->makeUser('waka_kurikulum', false);

        $html = $this->actingAs($tu)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->getContent();

        $suspendUrl = route('admin.users.toggle-suspend', $off->id);

        $this->assertStringContainsString('name="action" value="unsuspend"', $html);
        $this->assertStringContainsString('action="'.$suspendUrl.'"', $html);
        $this->assertStringNotContainsString('data-suspend-url="'.$suspendUrl.'"', $html);
    }

    public function test_index_akun_aktif_menampilkan_tombol_suspend_darurat_modal(): void
    {
        $tu = $this->makeTu();
        $active = $this->makeUser('waka_kurikulum');

        $html = $this->actingAs($tu)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->getContent();

        $suspendUrl = route('admin.users.toggle-suspend', $active->id);

        // Akun aktif → tombol modal "Suspend Darurat" (merah) dengan data-suspend-url.
        $this->assertStringContainsString('data-suspend-url="'.$suspendUrl.'"', $html);
        $this->assertStringContainsString('btn-outline-danger', $html);
        $this->assertStringNotContainsString('name="action" value="unsuspend"', $html);
    }

    public function test_index_kolom_nomor_memiliki_padding_kiri(): void
    {
        $tu = $this->makeTu();
        $this->makeUser('waka_kurikulum');

        $html = $this->actingAs($tu)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->getContent();

        // Padding-left kolom pertama (NO) untuk nomor/teks tidak menempel ke tepi.
        $this->assertMatchesRegularExpression('/<th class="whitespace-nowrap ps-3">NO<\/th>/', $html);
        $this->assertMatchesRegularExpression('/<td class="whitespace-nowrap ps-3">\d+<\/td>/', $html);
    }
}