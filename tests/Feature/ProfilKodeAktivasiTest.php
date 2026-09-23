<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Custom Kode Aktivasi di halaman Profil.
 *
 *  - Akun non-Guru yang memiliki Kode Aktivasi dapat mengubah kodenya
 *    secara manual melalui input form ("Kode Aktivasi Baru") dengan
 *    tombol "Simpan Kode Aktivasi" serta "Generate Random" (client-side).
 *  - Tab / menu "Kode Aktivasi" disembunyikan untuk akun dengan role Guru
 *    dan untuk akun non-Guru yang belum memiliki kode (null).
 *  - Validasi: 6-8 karakter, alfanumerik, tanpa spasi; unik antar akun
 *    pada lingkungan data yang sama; disimpan dalam huruf kapital.
 */
class ProfilKodeAktivasiTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUser(string $role, ?string $subRole = null, array $extra = []): User
    {
        return User::create(array_merge([
            'nama' => 'User '.Str::random(5),
            'username' => 'user_'.Str::random(8),
            'password' => bcrypt('password'),
            'role' => $role,
            'sub_role' => $subRole,
            'no_hp' => '085123456789',
            'is_active' => true,
        ], $extra));
    }

    // ================= Visibilitas Tab =================

    public function test_tab_kode_aktivasi_tampil_untuk_akun_non_guru_dengan_kode(): void
    {
        $user = $this->makeUser('admin', 'petugas_tu', ['kode_aktivasi' => 'KODE123']);

        $this->actingAs($user)->get(route('profil.index'))
            ->assertOk()
            ->assertSee('<a href="#section-kode"', false)
            ->assertSee('id="section-kode"', false)
            ->assertSee('Kode Aktivasi Baru', false)
            ->assertSee('Simpan Kode Aktivasi', false)
            ->assertSee('Generate Random', false)
            ->assertSee('generateRandomKode()', false)
            ->assertSee(route('profil.update-kode-aktivasi'), false);
    }

    public function test_tab_kode_aktivasi_disembunyikan_untuk_role_guru(): void
    {
        // Guru tetap disembunyikan meskipun kolom kode_aktivasi terisi.
        $user = $this->makeUser('guru', 'guru_mapel', ['kode_aktivasi' => 'KODE123']);

        $this->actingAs($user)->get(route('profil.index'))
            ->assertOk()
            ->assertDontSee('<a href="#section-kode"', false)
            ->assertDontSee('id="section-kode"', false)
            ->assertDontSee('Kode Aktivasi Baru', false)
            ->assertDontSee('Simpan Kode Aktivasi', false);
    }

    public function test_tab_kode_aktivasi_disembunyikan_untuk_akun_non_guru_tanpa_kode(): void
    {
        $user = $this->makeUser('admin', 'petugas_tu');

        $this->actingAs($user)->get(route('profil.index'))
            ->assertOk()
            ->assertDontSee('<a href="#section-kode"', false)
            ->assertDontSee('id="section-kode"', false)
            ->assertDontSee('Simpan Kode Aktivasi', false);
    }

    // ================= Perbarui Kode Aktivasi =================

    public function test_update_kode_aktivasi_berhasil_dengan_flash(): void
    {
        $user = $this->makeUser('admin', 'petugas_tu', ['kode_aktivasi' => 'KODE123']);

        $this->actingAs($user)
            ->post(route('profil.update-kode-aktivasi'), ['kode_aktivasi' => 'BARU456'])
            ->assertRedirect(route('profil.index'))
            ->assertSessionHas('success_kode', 'Kode aktivasi berhasil diperbarui.')
            ->assertSessionHas('tab_aktif', 'kode');

        $this->assertSame('BARU456', $user->fresh()->kode_aktivasi);
    }

    public function test_kode_aktivasi_disimpan_dalam_huruf_kapital(): void
    {
        $user = $this->makeUser('admin', 'petugas_tu', ['kode_aktivasi' => 'KODE123']);

        $this->actingAs($user)
            ->post(route('profil.update-kode-aktivasi'), ['kode_aktivasi' => '  baru456  '])
            ->assertRedirect(route('profil.index'));

        $this->assertSame('BARU456', $user->fresh()->kode_aktivasi);
    }

    public function test_update_kode_aktivasi_tidak_mengubah_kode_saat_gagal_validasi(): void
    {
        $user = $this->makeUser('admin', 'petugas_tu', ['kode_aktivasi' => 'KODE123']);

        foreach (['', 'ABC12', 'ABCDEFGHI', 'ABC 12', 'ABC-12', 'ABC_12'] as $kode) {
            $this->actingAs($user)
                ->post(route('profil.update-kode-aktivasi'), ['kode_aktivasi' => $kode])
                ->assertSessionHasErrors('kode_aktivasi');

            $this->assertSame('KODE123', $user->fresh()->kode_aktivasi);
        }
    }

    public function test_kode_aktivasi_harus_unik_antar_akun(): void
    {
        $this->makeUser('admin', 'petugas_tu', ['kode_aktivasi' => 'DIPAKAI']);
        $user = $this->makeUser('admin', 'petugas_tu', ['kode_aktivasi' => 'KODE123']);

        $this->actingAs($user)
            ->post(route('profil.update-kode-aktivasi'), ['kode_aktivasi' => 'DIPAKAI'])
            ->assertSessionHasErrors('kode_aktivasi');

        $this->assertSame('KODE123', $user->fresh()->kode_aktivasi);
    }

    public function test_kode_aktivasi_unik_bersifat_case_insensitive(): void
    {
        $this->makeUser('admin', 'petugas_tu', ['kode_aktivasi' => 'DIPAKAI']);
        $user = $this->makeUser('admin', 'petugas_tu', ['kode_aktivasi' => 'KODE123']);

        // Dikirim huruf kecil, dinormalisasi ke kapital di controller -> bentrok dengan DIPAKAI.
        $this->actingAs($user)
            ->post(route('profil.update-kode-aktivasi'), ['kode_aktivasi' => 'dipakai'])
            ->assertSessionHasErrors('kode_aktivasi');

        $this->assertSame('KODE123', $user->fresh()->kode_aktivasi);
    }

    public function test_akun_tanpa_kode_masih_boleh_mengatur_kode_baru(): void
    {
        $user = $this->makeUser('admin', 'petugas_tu');

        $this->actingAs($user)
            ->post(route('profil.update-kode-aktivasi'), ['kode_aktivasi' => 'BARU456'])
            ->assertRedirect(route('profil.index'));

        $this->assertSame('BARU456', $user->fresh()->kode_aktivasi);
    }

    public function test_guru_tidak_boleh_update_kode_aktivasi(): void
    {
        $user = $this->makeUser('guru', 'guru_mapel', ['kode_aktivasi' => 'KODE123']);

        $this->actingAs($user)
            ->post(route('profil.update-kode-aktivasi'), ['kode_aktivasi' => 'BARU456'])
            ->assertForbidden();

        $this->assertSame('KODE123', $user->fresh()->kode_aktivasi);
    }
}