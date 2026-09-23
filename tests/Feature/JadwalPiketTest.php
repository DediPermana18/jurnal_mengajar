<?php

namespace Tests\Feature;

use App\Models\JadwalPiket;
use App\Models\ShiftPiket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JadwalPiketTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_jadwal_piket_updates_schedule_and_flashes_correct_message(): void
    {
        $admin = User::create([
            'nama' => 'Admin Kurikulum',
            'username' => 'admin_kurikulum',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $guru1 = User::create([
            'nama' => 'Guru A',
            'username' => 'gurua',
            'password' => bcrypt('password'),
            'role' => 'guru',
            'is_active' => true,
        ]);

        $guru2 = User::create([
            'nama' => 'Guru B',
            'username' => 'gurub',
            'password' => bcrypt('password'),
            'role' => 'guru',
            'is_active' => true,
        ]);

        // Existing schedule for Tuesday
        $selasaPiket = JadwalPiket::create([
            'hari' => 'Selasa',
            'user_id' => $guru1->id,
        ]);

        // Submit sync for Senin
        $response = $this->actingAs($admin)
            ->post(route('kurikulum.jadwal-piket.store'), [
                'hari' => 'Senin',
                'guru_ids' => [$guru1->id, $guru2->id],
            ]);

        $response->assertRedirect(route('kurikulum.jadwal-piket.index'));
        $response->assertSessionHas('success', 'Petugas piket hari Senin berhasil diperbarui.');

        // Assert database has both teachers on Senin
        $this->assertDatabaseHas('jadwal_piket', [
            'hari' => 'Senin',
            'user_id' => $guru1->id,
        ]);
        $this->assertDatabaseHas('jadwal_piket', [
            'hari' => 'Senin',
            'user_id' => $guru2->id,
        ]);

        // Assert Tuesday schedule is still intact
        $this->assertDatabaseHas('jadwal_piket', [
            'id' => $selasaPiket->id,
            'hari' => 'Selasa',
        ]);
    }

    public function test_sync_jadwal_piket_fails_validation_and_does_not_delete_if_no_guru_selected(): void
    {
        $admin = User::create([
            'nama' => 'Admin Kurikulum',
            'username' => 'admin_kurikulum',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $guru1 = User::create([
            'nama' => 'Guru A',
            'username' => 'gurua',
            'password' => bcrypt('password'),
            'role' => 'guru',
            'is_active' => true,
        ]);

        // Existing schedule for Senin
        $seninPiket = JadwalPiket::create([
            'hari' => 'Senin',
            'user_id' => $guru1->id,
        ]);

        // Submit sync without guru_ids
        $response = $this->actingAs($admin)
            ->post(route('kurikulum.jadwal-piket.store'), [
                'hari' => 'Senin',
                'guru_ids' => [],
            ]);

        $response->assertSessionHasErrors('guru_ids');

        // Existing schedule must NOT be deleted
        $this->assertDatabaseHas('jadwal_piket', [
            'id' => $seninPiket->id,
            'hari' => 'Senin',
            'user_id' => $guru1->id,
        ]);
    }

    public function test_create_menampilkan_user_waka_piket_pada_dropdown_waka(): void
    {
        $admin = User::create([
            'nama' => 'Admin Kurikulum',
            'username' => 'admin_kurikulum',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        // Skema baru: role 'admin' + sub_role 'waka_piket' (dari /admin/users).
        $wakaPiket = User::create([
            'nama' => 'Bpk Waka Piket Baru',
            'username' => 'waka_piket_baru',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'sub_role' => 'waka_piket',
            'is_active' => true,
        ]);

        // Role legacy: 'waka' / 'wakakurikulum' tetap harus muncul.
        $wakaLegacy = User::create([
            'nama' => 'Bpk Waka Lama',
            'username' => 'waka_lama',
            'password' => bcrypt('password'),
            'role' => 'waka',
            'is_active' => true,
        ]);

        $guruBiasa = User::create([
            'nama' => 'Guru Biasa',
            'username' => 'guru_biasa',
            'password' => bcrypt('password'),
            'role' => 'guru',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('kurikulum.jadwal-piket.create'))
            ->assertOk();

        $response->assertSee('Guru dengan jabatan Waka/Kakurikulum/Waka Piket');

        // Ekstrak blok <select name="waka_user_id"> untuk memastikan isi
        // dropdown benar-benar memuat user waka_piket & legacy, tanpa guru biasa.
        $html = $response->getContent();
        $start = strpos($html, 'name="waka_user_id"');
        $end = strpos($html, '</select>', $start);
        $wakaSelect = substr($html, $start, $end - $start);

        $this->assertStringContainsString('value="'.$wakaPiket->id.'"', $wakaSelect);
        $this->assertStringContainsString('value="'.$wakaLegacy->id.'"', $wakaSelect);
        $this->assertStringNotContainsString('value="'.$guruBiasa->id.'"', $wakaSelect);
    }

    public function test_store_menerima_waka_user_id_dengan_sub_role_waka_piket(): void
    {
        $admin = User::create([
            'nama' => 'Admin Kurikulum',
            'username' => 'admin_kurikulum',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $guru = User::create([
            'nama' => 'Guru Shift',
            'username' => 'guru_shift',
            'password' => bcrypt('password'),
            'role' => 'guru',
            'is_active' => true,
        ]);

        // Waka dari skema baru (sub_role 'waka_piket').
        $wakaPiket = User::create([
            'nama' => 'Waka Piket Baru',
            'username' => 'waka_piket_store',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'sub_role' => 'waka_piket',
            'is_active' => true,
        ]);

        $shift = ShiftPiket::create([
            'nama' => 'Pagi',
            'jam_mulai' => '07:00',
            'jam_selesai' => '11:00',
            'maksimal_petugas' => 4,
            'urutan' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('kurikulum.jadwal-piket.store'), [
                'hari' => 'Senin',
                'minggu_ke' => 1,
                'waka_user_id' => $wakaPiket->id,
                'shift_users' => [$shift->id => [$guru->id]],
            ]);

        $response->assertRedirect(route('kurikulum.jadwal-piket.index'));

        // Validasi store mengizinkan ID user sub-role waka_piket dan data
        // waka tercatat pada baris pertama shift.
        $this->assertDatabaseHas('jadwal_piket', [
            'hari' => 'Senin',
            'shift_id' => $shift->id,
            'user_id' => $guru->id,
            'waka_user_id' => $wakaPiket->id,
        ]);
    }

    public function test_store_menolak_guru_yang_sama_di_shift_pagi_dan_siang(): void
    {
        $admin = User::create([
            'nama' => 'Admin Kurikulum',
            'username' => 'admin_kurikulum',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $waka = User::create([
            'nama' => 'Waka Piket',
            'username' => 'waka_piket',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'sub_role' => 'waka_piket',
            'is_active' => true,
        ]);

        $guru1 = User::create([
            'nama' => 'Guru Satu',
            'username' => 'guru_satu',
            'password' => bcrypt('password'),
            'role' => 'guru',
            'is_active' => true,
        ]);

        $guru2 = User::create([
            'nama' => 'Guru Dua',
            'username' => 'guru_dua',
            'password' => bcrypt('password'),
            'role' => 'guru',
            'is_active' => true,
        ]);

        // Data lama hari tersebut — harus tetap utuh saat validasi ditolak.
        $existing = JadwalPiket::create([
            'hari' => 'Senin',
            'minggu_ke' => 1,
            'user_id' => $guru1->id,
        ]);

        // Guru2 dipilih di Pagi DAN Siang bersamaan -> ditolak.
        $response = $this->actingAs($admin)
            ->post(route('kurikulum.jadwal-piket.store'), [
                'hari' => 'Senin',
                'minggu_ke' => 1,
                'waka_user_id' => $waka->id,
                'petugas_pagi_user_id' => [$guru1->id, $guru2->id],
                'petugas_siang_user_id' => [$guru2->id],
            ]);

        $response->assertSessionHasErrors('petugas_pagi_user_id');

        $errors = session('errors');
        $this->assertStringContainsString(
            'Guru yang sama tidak dapat bertugas di shift Pagi dan Siang bersamaan.',
            (string) $errors->first('petugas_pagi_user_id')
        );

        $this->assertDatabaseHas('jadwal_piket', ['id' => $existing->id]);
    }

    public function test_store_legacy_sk_menyimpan_waka_koordinator_dan_petugas_pagi_siang(): void
    {
        $admin = User::create([
            'nama' => 'Admin Kurikulum',
            'username' => 'admin_kurikulum',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $waka = User::create([
            'nama' => 'Waka Piket',
            'username' => 'waka_piket_store',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'sub_role' => 'waka_piket',
            'is_active' => true,
        ]);

        $koordPagi = User::create(['nama' => 'Koord Pagi', 'username' => 'koord_pagi', 'password' => bcrypt('x'), 'role' => 'guru', 'is_active' => true]);
        $petugasPagi1 = User::create(['nama' => 'Petugas Pagi 1', 'username' => 'petugas_p1', 'password' => bcrypt('x'), 'role' => 'guru', 'is_active' => true]);
        $petugasPagi2 = User::create(['nama' => 'Petugas Pagi 2', 'username' => 'petugas_p2', 'password' => bcrypt('x'), 'role' => 'guru', 'is_active' => true]);
        $koordSiang = User::create(['nama' => 'Koord Siang', 'username' => 'koord_siang', 'password' => bcrypt('x'), 'role' => 'guru', 'is_active' => true]);
        $petugasSiang = User::create(['nama' => 'Petugas Siang', 'username' => 'petugas_s', 'password' => bcrypt('x'), 'role' => 'guru', 'is_active' => true]);

        $response = $this->actingAs($admin)
            ->post(route('kurikulum.jadwal-piket.store'), [
                'hari' => 'Senin',
                'minggu_ke' => 1,
                'waka_user_id' => $waka->id,
                'koordinator_pagi_user_id' => $koordPagi->id,
                'petugas_pagi_user_id' => [$petugasPagi1->id, $petugasPagi2->id],
                'koordinator_siang_user_id' => $koordSiang->id,
                'petugas_siang_user_id' => [$petugasSiang->id],
            ]);

        $response->assertRedirect(route('kurikulum.jadwal-piket.index'));

        // 1 waka + 1 koord pagi + 2 petugas pagi + 1 koord siang + 1 petugas siang.
        $this->assertDatabaseCount('jadwal_piket', 6);

        $this->assertDatabaseHas('jadwal_piket', ['hari' => 'Senin', 'minggu_ke' => 1, 'waka_user_id' => $waka->id]);
        $this->assertDatabaseHas('jadwal_piket', ['hari' => 'Senin', 'minggu_ke' => 1, 'koordinator_pagi_user_id' => $koordPagi->id]);
        $this->assertDatabaseHas('jadwal_piket', ['hari' => 'Senin', 'minggu_ke' => 1, 'petugas_pagi_user_id' => $petugasPagi1->id]);
        $this->assertDatabaseHas('jadwal_piket', ['hari' => 'Senin', 'minggu_ke' => 1, 'petugas_pagi_user_id' => $petugasPagi2->id]);
        $this->assertDatabaseHas('jadwal_piket', ['hari' => 'Senin', 'minggu_ke' => 1, 'koordinator_siang_user_id' => $koordSiang->id]);
        $this->assertDatabaseHas('jadwal_piket', ['hari' => 'Senin', 'minggu_ke' => 1, 'petugas_siang_user_id' => $petugasSiang->id]);
    }

    public function test_store_shift_menyimpan_koordinator_pagi_dan_siang(): void
    {
        $admin = User::create([
            'nama' => 'Admin Kurikulum',
            'username' => 'admin_kurikulum_koor',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $guruPagi = User::create(['nama' => 'Petugas Pagi', 'username' => 'petugas_pagi_x', 'password' => bcrypt('x'), 'role' => 'guru', 'is_active' => true]);
        $guruSiang = User::create(['nama' => 'Petugas Siang', 'username' => 'petugas_siang_x', 'password' => bcrypt('x'), 'role' => 'guru', 'is_active' => true]);
        $koordPagi = User::create(['nama' => 'Koor Pagi', 'username' => 'koor_pagi_x', 'password' => bcrypt('x'), 'role' => 'guru', 'is_active' => true]);
        $koordSiang = User::create(['nama' => 'Koor Siang', 'username' => 'koor_siang_x', 'password' => bcrypt('x'), 'role' => 'guru', 'is_active' => true]);

        $shiftPagi = ShiftPiket::where('nama', 'Pagi')->firstOrFail();
        $shiftSiang = ShiftPiket::where('nama', 'Siang')->firstOrFail();

        $response = $this->actingAs($admin)
            ->post(route('kurikulum.jadwal-piket.store'), [
                'hari' => 'Senin',
                'minggu_ke' => 1,
                'shift_users' => [
                    $shiftPagi->id => [$guruPagi->id],
                    $shiftSiang->id => [$guruSiang->id],
                ],
                'koordinator_pagi_user_id' => $koordPagi->id,
                'koordinator_siang_user_id' => $koordSiang->id,
            ]);

        $response->assertRedirect(route('kurikulum.jadwal-piket.index'));

        // 2 baris petugas shift + 2 baris koordinator (pagi & siang).
        $this->assertDatabaseCount('jadwal_piket', 4);
        $this->assertDatabaseHas('jadwal_piket', ['hari' => 'Senin', 'shift_id' => $shiftPagi->id, 'user_id' => $guruPagi->id]);
        $this->assertDatabaseHas('jadwal_piket', ['hari' => 'Senin', 'shift_id' => $shiftSiang->id, 'user_id' => $guruSiang->id]);
        $this->assertDatabaseHas('jadwal_piket', ['hari' => 'Senin', 'koordinator_pagi_user_id' => $koordPagi->id]);
        $this->assertDatabaseHas('jadwal_piket', ['hari' => 'Senin', 'koordinator_siang_user_id' => $koordSiang->id]);
    }

    public function test_store_menolak_guru_sama_di_panel_pagi_dan_siang(): void
    {
        $admin = User::create([
            'nama' => 'Admin Kurikulum',
            'username' => 'admin_kurikulum_dupe',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $guru = User::create(['nama' => 'Guru Dupe', 'username' => 'guru_dupe_panel', 'password' => bcrypt('x'), 'role' => 'guru', 'is_active' => true]);

        $shiftPagi = ShiftPiket::where('nama', 'Pagi')->firstOrFail();
        $shiftSiang = ShiftPiket::where('nama', 'Siang')->firstOrFail();

        // Data lama hari tersebut — harus tetap utuh saat validasi ditolak.
        JadwalPiket::create(['hari' => 'Senin', 'minggu_ke' => 1, 'user_id' => $guru->id]);

        // Guru yang sama dicentang di panel Pagi DAN Siang -> ditolak.
        $response = $this->actingAs($admin)
            ->post(route('kurikulum.jadwal-piket.store'), [
                'hari' => 'Senin',
                'minggu_ke' => 1,
                'shift_users' => [
                    $shiftPagi->id => [$guru->id],
                    $shiftSiang->id => [$guru->id],
                ],
            ]);

        $response->assertSessionHasErrors('shift_users');

        $errors = session('errors');
        $this->assertStringContainsString(
            'Guru yang sama tidak dapat bertugas di shift Pagi dan Siang bersamaan.',
            (string) $errors->first('shift_users')
        );

        $this->assertDatabaseCount('jadwal_piket', 1);
    }

    public function test_store_menolak_koordinator_yang_sekaligus_petugas_legacy(): void
    {
        $admin = User::create([
            'nama' => 'Admin Kurikulum',
            'username' => 'admin_kurikulum_koor_dupe',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $guru = User::create(['nama' => 'Guru Rangkap', 'username' => 'guru_rangkap', 'password' => bcrypt('x'), 'role' => 'guru', 'is_active' => true]);

        // Koordinator Pagi TIDAK boleh dicentang ulang sebagai petugas Pagi/Siang.
        $response = $this->actingAs($admin)
            ->post(route('kurikulum.jadwal-piket.store'), [
                'hari' => 'Senin',
                'minggu_ke' => 1,
                'koordinator_pagi_user_id' => $guru->id,
                'petugas_pagi_user_id' => [$guru->id],
            ]);

        $response->assertSessionHasErrors('koordinator_pagi_user_id');

        $errors = session('errors');
        $this->assertStringContainsString(
            'Guru yang menjadi Koordinator Piket tidak dapat dipilih sebagai Petugas Piket biasa.',
            (string) $errors->first('koordinator_pagi_user_id')
        );

        $this->assertDatabaseCount('jadwal_piket', 0);
    }

    public function test_store_menolak_koordinator_yang_sekaligus_petugas_panel_shift(): void
    {
        $admin = User::create([
            'nama' => 'Admin Kurikulum',
            'username' => 'admin_kurikulum_koor_dupe2',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $guru = User::create(['nama' => 'Guru Rangkap Shift', 'username' => 'guru_rangkap_shift', 'password' => bcrypt('x'), 'role' => 'guru', 'is_active' => true]);

        $shiftPagi = ShiftPiket::where('nama', 'Pagi')->firstOrFail();

        // Koordinator Siang TIDAK boleh ikut dicentang sebagai petugas shift Pagi.
        $response = $this->actingAs($admin)
            ->post(route('kurikulum.jadwal-piket.store'), [
                'hari' => 'Senin',
                'minggu_ke' => 1,
                'koordinator_siang_user_id' => $guru->id,
                'shift_users' => [
                    $shiftPagi->id => [$guru->id],
                ],
            ]);

        $response->assertSessionHasErrors('koordinator_pagi_user_id');

        $errors = session('errors');
        $this->assertStringContainsString(
            'Guru yang menjadi Koordinator Piket tidak dapat dipilih sebagai Petugas Piket biasa.',
            (string) $errors->first('koordinator_pagi_user_id')
        );

        $this->assertDatabaseCount('jadwal_piket', 0);
    }

    public function test_create_form_merender_elemen_mutual_exclusion_pagi_siang(): void
    {
        $admin = User::create([
            'nama' => 'Admin Kurikulum',
            'username' => 'admin_kurikulum_render',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        // Guru agar checkbox petugas (dengan data-sesi) ikut dirender.
        User::create([
            'nama' => 'Guru Render',
            'username' => 'guru_render',
            'password' => bcrypt('password'),
            'role' => 'guru',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('kurikulum.jadwal-piket.create'))
            ->assertOk()
            // Form aktif = panel shift (shift Pagi & Siang ter-seed di migrasi).
            ->assertSee('Koordinator Piket Pagi')
            ->assertSee('Koordinator Piket Siang')
            ->assertSee('data-sesi="pagi"', false)
            ->assertSee('data-sesi="siang"', false)
            // Layout vertikal: container shift 1 kolom (Pagi di atas, Siang di bawah).
            ->assertSee('shift-panels-stack')
            ->assertSee('shift-panel w-100', false)
            // Grid card guru responsif 1/2 kolom agar nama & NIP tidak terpotong pendek
            // (grid-cols-1 di mobile, sm:grid-cols-2 mulai layar sm).
            ->assertSee('sm:grid-cols-2', false)
            ->assertSee('guru-item-col', false)
            // Search box & quick selection guru dirender.
            ->assertSee('Cari Nama / NIP Guru', false)
            ->assertSee('guru-select-all', false)
            ->assertSee('guru-clear', false)
            // JS/CSS mutual exclusion juga dirender.
            ->assertSee('syncExclusive')
            ->assertSee('is-locked')
            ->assertSee('koordinator_pagi_user_id')
            ->assertSee('koordinator_siang_user_id');
    }

    public function test_edit_merender_form_yang_sama_tanpa_error(): void
    {
        $admin = User::create([
            'nama' => 'Admin Kurikulum',
            'username' => 'admin_kurikulum',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $guru = User::create([
            'nama' => 'Guru Edit',
            'username' => 'guru_edit',
            'password' => bcrypt('password'),
            'role' => 'guru',
            'is_active' => true,
        ]);

        JadwalPiket::create([
            'hari' => 'Senin',
            'minggu_ke' => 1,
            'user_id' => $guru->id,
        ]);

        // Edit memakai form yang sama (include create.blade.php) — harus
        // merender tanpa error dan tetap memuat grid mutual exclusion.
        $this->actingAs($admin)
            ->get(route('kurikulum.jadwal-piket.edit', 'Senin'))
            ->assertOk()
            ->assertSee('guruGridListPagi')
            ->assertSee('guruGridListSiang')
            ->assertSee('syncExclusive');
    }

    public function test_koordinator_piket_bertugas_tanggal_mengambil_koordinator_dari_jadwal(): void
    {
        $guruA = User::create(['nama' => 'Guru A', 'username' => 'guru_a_koor', 'password' => bcrypt('x'), 'role' => 'guru', 'is_active' => true]);
        $koordPagi = User::create(['nama' => 'Koord Pagi', 'username' => 'koord_pagi_helper', 'password' => bcrypt('x'), 'role' => 'guru', 'is_active' => true]);
        $koordSiang = User::create(['nama' => 'Koord Siang', 'username' => 'koord_siang_helper', 'password' => bcrypt('x'), 'role' => 'guru', 'is_active' => true]);

        // Senin: koordinator pagi terdaftar; Rabu: koordinator siang terdaftar.
        JadwalPiket::create(['hari' => 'Senin', 'user_id' => $guruA->id, 'koordinator_pagi_user_id' => $koordPagi->id]);
        JadwalPiket::create(['hari' => 'Rabu', 'user_id' => $guruA->id, 'koordinator_siang_user_id' => $koordSiang->id]);

        $senin = JadwalPiket::koordinatorPiketBertugasTanggal(Carbon::create(2026, 8, 31));
        $this->assertTrue($senin->contains('id', $koordPagi->id));
        $this->assertFalse($senin->contains('id', $koordSiang->id));
        $this->assertFalse($senin->contains('id', $guruA->id)); // guruA hanya user_id, bukan koordinator

        $rabu = JadwalPiket::koordinatorPiketBertugasTanggal(Carbon::create(2026, 9, 2));
        $this->assertTrue($rabu->contains('id', $koordSiang->id));
        $this->assertFalse($rabu->contains('id', $koordPagi->id));

        // Akhir pekan (Minggu) -> tidak ada jadwal.
        $this->assertTrue(JadwalPiket::koordinatorPiketBertugasTanggal(Carbon::create(2026, 8, 30))->isEmpty());
    }

    public function test_index_layout_vertikal_dan_kategori_per_hari(): void
    {
        $admin = User::create([
            'nama' => 'Admin Kurikulum',
            'username' => 'admin_kurikulum_index',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $waka = User::create(['nama' => 'Waka Piket Uji', 'username' => 'waka_uji_index', 'password' => bcrypt('x'), 'role' => 'admin', 'sub_role' => 'waka_piket', 'is_active' => true]);
        $koorPagi = User::create(['nama' => 'Koor Pagi Uji', 'username' => 'koorp_uji', 'password' => bcrypt('x'), 'role' => 'guru', 'nip' => '19800101', 'is_active' => true]);
        $koorSiang = User::create(['nama' => 'Koor Siang Uji', 'username' => 'koors_uji', 'password' => bcrypt('x'), 'role' => 'guru', 'nip' => '19800102', 'is_active' => true]);
        $pPagi1 = User::create(['nama' => 'Petugas Pagi Uji', 'username' => 'pp_uji1', 'password' => bcrypt('x'), 'role' => 'guru', 'nip' => '19800103', 'is_active' => true]);
        $pSiang1 = User::create(['nama' => 'Petugas Siang Uji', 'username' => 'ps_uji1', 'password' => bcrypt('x'), 'role' => 'guru', 'nip' => '19800104', 'is_active' => true]);
        $petugasShiftPagi = User::create(['nama' => 'Petugas Shift Pagi', 'username' => 'pshift_uji', 'password' => bcrypt('x'), 'role' => 'guru', 'nip' => '19800105', 'is_active' => true]);

        // Format SK (legacy): baris per kategori pada hari Senin.
        JadwalPiket::create(['hari' => 'Senin', 'minggu_ke' => 1, 'user_id' => $waka->id, 'waka_user_id' => $waka->id]);
        JadwalPiket::create(['hari' => 'Senin', 'minggu_ke' => 1, 'user_id' => $koorPagi->id, 'koordinator_pagi_user_id' => $koorPagi->id]);
        JadwalPiket::create(['hari' => 'Senin', 'minggu_ke' => 1, 'user_id' => $pPagi1->id, 'petugas_pagi_user_id' => $pPagi1->id]);
        JadwalPiket::create(['hari' => 'Senin', 'minggu_ke' => 1, 'user_id' => $koorSiang->id, 'koordinator_siang_user_id' => $koorSiang->id]);
        JadwalPiket::create(['hari' => 'Senin', 'minggu_ke' => 1, 'user_id' => $pSiang1->id, 'petugas_siang_user_id' => $pSiang1->id]);

        // Format shift dinamis: petugas shift "Pagi" pada hari Selasa.
        $shiftPagi = ShiftPiket::where('nama', 'Pagi')->firstOrFail();
        JadwalPiket::create(['hari' => 'Selasa', 'minggu_ke' => 1, 'shift_id' => $shiftPagi->id, 'user_id' => $petugasShiftPagi->id]);

        $response = $this->actingAs($admin)
            ->get(route('kurikulum.jadwal-piket.index', ['minggu_ke' => 1]))
            ->assertOk();

        // Layout vertikal 1 kolom + label kategori.
        $response->assertSee('jadwal-piket-stack')
            ->assertSee('Waka Piket')
            ->assertSee('Koordinator Pagi')
            ->assertSee('Petugas Pagi')
            ->assertSee('Koordinator Siang')
            ->assertSee('Petugas Siang');

        // Nama guru dari tiap kategori ikut dirender.
        $response->assertSee('Waka Piket Uji')
            ->assertSee('Koor Pagi Uji')
            ->assertSee('Petugas Pagi Uji')
            ->assertSee('Koor Siang Uji')
            ->assertSee('Petugas Siang Uji')
            ->assertSee('Petugas Shift Pagi');

        // Hari tersusun berurutan Senin s.d. Jumat (layout vertikal).
        $response->assertSeeInOrder(['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat']);

        // Hari tanpa data -> empty state rapi.
        $response->assertSee('Belum ada penugasan piket hari Rabu');
    }
}
