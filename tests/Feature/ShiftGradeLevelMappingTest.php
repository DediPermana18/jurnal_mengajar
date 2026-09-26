<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\JamPelajaran;
use App\Models\JamPulang;
use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\ShiftPelajaran;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fitur Grade Level Mapping pada Shift Pelajaran:
 *  - Petakan shift ke tingkatan kelas (X / XI / XII) lewat form Tambah/Edit Shift.
 *  - Tampilkan badge tingkatan pada Daftar Shift & konteks Kelas.
 *  - Validasi plotting jadwal: kelas hanya boleh di-plot bila tingkat kelasnya
 *    termasuk daftar tingkatan yang dilayani shift kelas tersebut.
 */
class ShiftGradeLevelMappingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected TahunAjaran $tahun;

    protected Jurusan $jurusan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'nama' => 'Admin Grade',
            'username' => 'admingrade',
            'password' => bcrypt('password'),
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $this->tahun = TahunAjaran::create([
            'tahun_ajaran' => '2025/2026',
            'semester' => 'Ganjil',
            'is_active' => true,
        ]);

        $this->jurusan = Jurusan::create([
            'kode_jurusan' => 'RPL',
            'nama_jurusan' => 'Rekayasa Perangkat Lunak',
        ]);
    }

    private function makeShift(string $nama, array $gradeLevels = [], string $mulai = '07:00', string $selesai = '14:00'): ShiftPelajaran
    {
        return ShiftPelajaran::create([
            'nama_shift' => $nama,
            'keterangan' => 'Keterangan '.$nama,
            'jam_mulai' => $mulai,
            'jam_selesai' => $selesai,
            'is_active' => true,
            'grade_levels' => $gradeLevels ?: null,
        ]);
    }

    private function makeSlot(?int $shiftId, string $mulai, string $selesai, int $jamKe = 1): JamPelajaran
    {
        return JamPelajaran::create([
            'kategori_hari' => 'Senin-Kamis',
            'hari' => 'Senin',
            'shift_id' => $shiftId,
            'jam_ke' => $jamKe,
            'jam_mulai' => $mulai.':00',
            'jam_selesai' => $selesai.':00',
            'jenis' => 'kbm',
        ]);
    }

    private function makeGuru(): User
    {
        return User::create([
            'nama' => 'Drs. Supriyanto',
            'nip' => '197001011995011001',
            'username' => 'gurugrade',
            'password' => bcrypt('password'),
            'role' => User::ROLE_GURU,
            'is_active' => true,
        ]);
    }

    public function test_shift_store_persists_and_normalizes_grade_levels(): void
    {
        // Input tidak urut & campur case — distandarkan ke urutan kanonis X -> XI -> XII.
        $response = $this->actingAs($this->admin)
            ->post(route('admin.shift-pelajaran.store'), [
                'nama_shift' => 'Shift 1 (Pagi)',
                'grade_levels' => ['XII', 'x', 'XI'],
                'is_active' => 1,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('shift_pelajaran', [
            'nama_shift' => 'Shift 1 (Pagi)',
            'grade_levels' => '["X","XI","XII"]',
        ]);
    }

    public function test_shift_update_persists_grade_levels(): void
    {
        $shift = $this->makeShift('Shift 1 (Pagi)', ['XII']);

        $response = $this->actingAs($this->admin)
            ->put(route('admin.shift-pelajaran.update', $shift->id), [
                'nama_shift' => 'Shift 1 (Pagi) Revisi',
                'grade_levels' => ['X', 'XI'],
                'is_active' => 1,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('shift_pelajaran', [
            'id' => $shift->id,
            'grade_levels' => '["X","XI"]',
        ]);
    }

    public function test_shift_rejects_invalid_grade_level_value(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.shift-pelajaran.store'), [
                'nama_shift' => 'Shift Invalid',
                'grade_levels' => ['IX'],
                'is_active' => 1,
            ]);

        $response->assertSessionHasErrors('grade_levels.0');
        $this->assertDatabaseMissing('shift_pelajaran', ['nama_shift' => 'Shift Invalid']);
    }

    public function test_shift_without_grade_levels_remains_unconstrained(): void
    {
        // Kosong (null/[]) = shift berlaku untuk semua tingkatan (legacy) — tidak berubah.
        $shift = $this->makeShift('Shift 1 (Pagi)');
        $this->assertEmpty($shift->grade_levels ?? []);
        $this->assertTrue($shift->servesGrade('X'));
        $this->assertTrue($shift->servesGrade('XII'));
        $this->assertSame('Semua Tingkatan', $shift->grade_levels_label);
    }

    public function test_modal_form_renders_grade_level_checkboxes_and_daftar_shift_badges(): void
    {
        AppSetting::setScheduleMode(AppSetting::SCHEDULE_SHIFT);

        $this->makeShift('Shift 1 (Pagi)', ['XII'], '07:00', '12:00');
        $this->makeShift('Shift 2 (Siang)', ['X', 'XI'], '12:00', '18:00');

        $response = $this->actingAs($this->admin)
            ->get(route('admin.jam-pelajaran.index', ['tab' => 'Senin-Kamis']));

        $response->assertOk()
            ->assertSee('Berlaku untuk Tingkatan Kelas')
            // Checkbox group multi-select: Kelas 10/X, Kelas 11/XI, Kelas 12/XII.
            ->assertSee('Kelas 10 / X', false)
            ->assertSee('Kelas 11 / XI', false)
            ->assertSee('Kelas 12 / XII', false)
            ->assertSee('name="grade_levels[]"', false)
            ->assertSee('value="X"', false)
            ->assertSee('value="XII"', false)
            // Badge tingkatan pada Daftar Shift.
            ->assertSee('Kelas 12', false)
            ->assertSee('Kelas 10, 11', false)
            // Tombol Edit membawa data tingkatan untuk mengisi ulang checkbox.
            ->assertSee('data-grade="X,XI"', false)
            ->assertSee('data-grade="XII"', false);
    }

    public function test_plotting_rejects_class_whose_grade_is_not_served_by_shift(): void
    {
        AppSetting::setScheduleMode(AppSetting::SCHEDULE_SHIFT);

        // Shift 1 hanya melayani Kelas 12.
        $shift = $this->makeShift('Shift 1 (Pagi)', ['XII'], '07:00', '14:00');
        $this->makeSlot($shift->id, '07:00', '07:45');

        // Kelas tingkat X ternyata dialokasikan ke shift khusus kelas XII.
        $kelas = Kelas::create([
            'nama_kelas' => 'X RPL 1',
            'tingkat' => 'X',
            'id_jurusan' => $this->jurusan->id,
            'shift_id' => $shift->id,
        ]);

        $mapel = MataPelajaran::create(['nama_mapel' => 'Matematika']);
        $guru = $this->makeGuru();

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.jadwal.store'), [
                'id_kelas' => $kelas->id,
                'hari' => 'Senin',
                'jam_ke_mulai' => 1,
                'jam_ke_selesai' => 1,
                'id_mapel' => $mapel->id,
                'id_guru' => $guru->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Gagal! Shift "Shift 1 (Pagi)" hanya berlaku untuk tingkatan Kelas 12 — Kelas "X RPL 1" ber-tingkat X. Sesuaikan alokasi shift pada data kelas sebelum plotting jadwal.');

        $this->assertDatabaseCount('jadwal_pelajaran', 0);
    }

    public function test_plotting_allows_class_whose_grade_is_served_by_shift(): void
    {
        AppSetting::setScheduleMode(AppSetting::SCHEDULE_SHIFT);

        // Shift 1 melayani Kelas 10 & 11 — kelas X cocok.
        $shift = $this->makeShift('Shift 1 (Pagi)', ['X', 'XI'], '07:00', '14:00');
        $this->makeSlot($shift->id, '07:00', '07:45');

        $kelas = Kelas::create([
            'nama_kelas' => 'X RPL 1',
            'tingkat' => 'X',
            'id_jurusan' => $this->jurusan->id,
            'shift_id' => $shift->id,
        ]);

        $mapel = MataPelajaran::create(['nama_mapel' => 'Matematika']);
        $guru = $this->makeGuru();

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.jadwal.store'), [
                'id_kelas' => $kelas->id,
                'hari' => 'Senin',
                'jam_ke_mulai' => 1,
                'jam_ke_selesai' => 1,
                'id_mapel' => $mapel->id,
                'id_guru' => $guru->id,
            ]);

        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('jadwal_pelajaran', [
            'hari' => 'Senin',
            'id_kelas' => $kelas->id,
            'id_guru' => $guru->id,
        ]);
    }

    public function test_kelas_store_rejects_shift_that_does_not_serve_its_grade(): void
    {
        $shift = $this->makeShift('Shift 1 (Pagi)', ['XII'], '07:00', '14:00');

        $response = $this->actingAs($this->admin)
            ->post(route('kelas.store'), [
                'tingkat' => 'X',
                'id_jurusan' => $this->jurusan->id,
                'shift_id' => $shift->id,
            ]);

        $response->assertSessionHasErrors('shift_id');
        $this->assertDatabaseMissing('kelas', ['nama_kelas' => 'X RPL 1']);
    }

    public function test_kelas_store_allows_shift_that_serves_its_grade(): void
    {
        $shift = $this->makeShift('Shift 1 (Pagi)', ['X', 'XI'], '07:00', '14:00');

        $response = $this->actingAs($this->admin)
            ->post(route('kelas.store'), [
                'tingkat' => 'XI',
                'id_jurusan' => $this->jurusan->id,
                'shift_id' => $shift->id,
            ]);

        $response->assertRedirect(route('kelas.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('kelas', [
            'nama_kelas' => 'XI RPL 1',
            'shift_id' => $shift->id,
        ]);
    }

    public function test_plotting_index_warns_when_class_grades_do_not_match_shift(): void
    {
        AppSetting::setScheduleMode(AppSetting::SCHEDULE_SHIFT);

        $shift = $this->makeShift('Shift 1 (Pagi)', ['XII'], '07:00', '14:00');

        $kelas = Kelas::create([
            'nama_kelas' => 'X RPL 1',
            'tingkat' => 'X',
            'id_jurusan' => $this->jurusan->id,
            'shift_id' => $shift->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.jadwal.index', ['id_kelas' => $kelas->id, 'hari' => 'Senin']))
            ->assertOk()
            ->assertSee('Alokasi shift tidak selaras dengan tingkat kelas')
            ->assertSee('Kelas 12', false)
            ->assertSee('ber-tingkat', false);
    }

    public function test_jam_pulang_section_only_shows_grade_levels_served_by_active_shift(): void
    {
        AppSetting::setScheduleMode(AppSetting::SCHEDULE_SHIFT);

        // Shift 1 khusus Kelas 12: panel Jam Pulang hanya menampilkan kartu XII.
        $shift = $this->makeShift('Shift 1 (Pagi)', ['XII'], '07:00', '12:00');
        $this->makeSlot($shift->id, '07:00', '08:00');

        $this->actingAs($this->admin)
            ->get(route('admin.jam-pelajaran.index', ['tab' => 'Senin-Kamis', 'shift' => $shift->id]))
            ->assertOk()
            ->assertSee('jam_pulang['.$shift->id.'][Senin-Kamis][XII]', false)
            ->assertDontSee('jam_pulang['.$shift->id.'][Senin-Kamis][X]', false)
            ->assertDontSee('jam_pulang['.$shift->id.'][Senin-Kamis][XI]', false);

        // Shift tanpa pembatasan tingkatan: seluruh kartu tingkatan tampil.
        $shiftUmum = $this->makeShift('Shift 2 (Umum)', [], '12:00', '18:00');

        $this->actingAs($this->admin)
            ->get(route('admin.jam-pelajaran.index', ['tab' => 'Senin-Kamis', 'shift' => $shiftUmum->id]))
            ->assertOk()
            ->assertSee('jam_pulang['.$shiftUmum->id.'][Senin-Kamis][X]', false)
            ->assertSee('jam_pulang['.$shiftUmum->id.'][Senin-Kamis][XI]', false)
            ->assertSee('jam_pulang['.$shiftUmum->id.'][Senin-Kamis][XII]', false);
    }

    public function test_jam_pulang_upsert_ignores_and_cleans_grade_levels_not_served_by_shift(): void
    {
        AppSetting::setScheduleMode(AppSetting::SCHEDULE_SHIFT);

        // Shift 1 khusus Kelas 12.
        $shift = $this->makeShift('Shift 1 (Pagi)', ['XII'], '07:00', '12:00');

        // Data lama untuk tingkatan yang tidak lagi dilayani shift (XI) ikut dibersihkan.
        JamPulang::create([
            'shift_id' => $shift->id,
            'kategori_hari' => 'Senin-Kamis',
            'tingkat' => 'XI',
            'max_jam_ke' => 4,
            'is_testing_data' => 0,
        ]);

        // Payload menyertakan X (tidak relevan) & XII (relevan).
        $this->actingAs($this->admin)
            ->post(route('admin.jam-pulang.upsert'), [
                'redirect_tab' => 'Senin-Kamis',
                'redirect_shift' => $shift->id,
                'jam_pulang' => [
                    $shift->id => [
                        'Senin-Kamis' => ['X' => 7, 'XII' => 5],
                        'Jumat' => ['X' => 3, 'XII' => 3],
                    ],
                ],
            ])
            ->assertRedirect();

        // Hanya tingkatan yang dilayani shift yang tersimpan.
        $this->assertDatabaseHas('jam_pulang', [
            'shift_id' => $shift->id,
            'kategori_hari' => 'Senin-Kamis',
            'tingkat' => 'XII',
            'max_jam_ke' => 5,
        ]);
        $this->assertDatabaseMissing('jam_pulang', ['shift_id' => $shift->id, 'tingkat' => 'X']);
        $this->assertDatabaseMissing('jam_pulang', ['shift_id' => $shift->id, 'tingkat' => 'XI']);

        // Shift tanpa grade_levels: semua tingkatan tetap diproses.
        $shiftUmum = $this->makeShift('Shift 2 (Umum)', [], '12:00', '18:00');

        $this->actingAs($this->admin)
            ->post(route('admin.jam-pulang.upsert'), [
                'redirect_tab' => 'Senin-Kamis',
                'redirect_shift' => $shiftUmum->id,
                'jam_pulang' => [
                    $shiftUmum->id => [
                        'Senin-Kamis' => ['X' => 6, 'XI' => 5, 'XII' => 4],
                    ],
                ],
            ])
            ->assertRedirect();

        foreach (['X', 'XI', 'XII'] as $tingkat) {
            $this->assertDatabaseHas('jam_pulang', [
                'shift_id' => $shiftUmum->id,
                'kategori_hari' => 'Senin-Kamis',
                'tingkat' => $tingkat,
            ]);
        }
    }

    public function test_plotting_index_detects_shift_by_grade_for_unbound_class(): void
    {
        AppSetting::setScheduleMode(AppSetting::SCHEDULE_SHIFT);

        // Shift 2 khusus tingkatan XI — Kelas XI yang TIDAK terikat shift apa pun
        // otomatis dinaungi Shift 2 via deteksi grade_levels (kelas.shift_id NULL).
        $shift2 = $this->makeShift('Shift 2 (Siang)', ['XI'], '12:00', '18:00');
        $this->makeSlot($shift2->id, '12:00', '12:45');

        $kelas = Kelas::create([
            'nama_kelas' => 'XI RPL 1',
            'tingkat' => 'XI',
            'id_jurusan' => $this->jurusan->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.jadwal.index', ['id_kelas' => $kelas->id, 'hari' => 'Senin']))
            ->assertOk();

        // Badge header mengganti 'Global' menjadi nama shift hasil deteksi.
        $response->assertSee('Shift 2 (Siang)', false)
            ->assertDontSee('🌐 Global')
            // Matriks slot termuat dari slot milik Shift 2 (bukan hanya slot Global).
            ->assertSee('12.00 – 12.45');
    }

    public function test_plotting_index_falls_back_to_global_when_no_shift_serves_grade(): void
    {
        AppSetting::setScheduleMode(AppSetting::SCHEDULE_SHIFT);

        // Shift 1 hanya melayani Kelas 12 — Kelas XI tidak dinaungi shift mana pun
        // yang sesuai sehingga memakai slot Global (fallback, tetap Multi-Shift).
        $shift1 = $this->makeShift('Shift 1 (Pagi)', ['XII'], '07:00', '12:00');
        $this->makeSlot($shift1->id, '07:00', '07:45');

        $kelas = Kelas::create([
            'nama_kelas' => 'XI RPL 2',
            'tingkat' => 'XI',
            'id_jurusan' => $this->jurusan->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.jadwal.index', ['id_kelas' => $kelas->id, 'hari' => 'Senin']))
            ->assertOk();

        $response->assertSee('🌐 Global')
            ->assertDontSee('Shift 1 (Pagi)')
            // Slot milik shift yang tidak berlaku untuk XI TIDAK tampil di matriks.
            ->assertDontSee('07.00 – 07.45');
    }

    public function test_plotting_store_allows_unbound_class_when_shift_serves_its_grade(): void
    {
        AppSetting::setScheduleMode(AppSetting::SCHEDULE_SHIFT);

        $shift = $this->makeShift('Shift 2 (Siang)', ['XI'], '12:00', '18:00');
        $slot = $this->makeSlot($shift->id, '12:00', '12:45');

        // Kelas tingkat XI TANPA ikatan shift — tetap boleh di-plot karena
        // shift efektifnya terdeteksi dari grade_levels (Shift 2 melayani XI).
        $kelas = Kelas::create([
            'nama_kelas' => 'XI RPL 1',
            'tingkat' => 'XI',
            'id_jurusan' => $this->jurusan->id,
        ]);

        $mapel = MataPelajaran::create(['nama_mapel' => 'Matematika']);
        $guru = $this->makeGuru();

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.jadwal.store'), [
                'id_kelas' => $kelas->id,
                'hari' => 'Senin',
                'jam_ke_mulai' => 1,
                'jam_ke_selesai' => 1,
                'id_mapel' => $mapel->id,
                'id_guru' => $guru->id,
            ]);

        $response->assertJsonPath('success', true);

        // Record plotting menunjuk ke slot milik Shift 2 (id_jam slot shift tsb).
        $this->assertDatabaseHas('jadwal_pelajaran', [
            'hari' => 'Senin',
            'id_kelas' => $kelas->id,
            'id_jam' => $slot->id,
            'id_guru' => $guru->id,
        ]);
    }
}