<?php

namespace Tests\Feature;

use App\Models\AgendaRutin;
use App\Models\AppSetting;
use App\Models\JamPelajaran;
use App\Models\ShiftPelajaran;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ruang lingkup slot pada dropdown 'Jam Ke-' di Pengaturan Agenda Rutin:
 *  - Upacara Bendera (khusus hari Senin) & Pembiasaan (khusus hari Jumat)
 *    hanya menampilkan slot KBM milik shift yang sedang aktif — slot shift
 *    lain tidak boleh bocor ke dropdown.
 *  - Label rentang waktu selalu diformat konsisten HH:MM - HH:MM.
 *
 * Catatan: kedua kartu agenda (Senin & Jumat) dirender sekaligus, jadi
 * assertion dilakukan per-blok <select> agar presisi per dropdown.
 */
class AgendaRutinShiftScopeTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected TahunAjaran $tahun;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'nama' => 'Admin Agenda',
            'username' => 'adminagenda',
            'password' => bcrypt('password'),
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $this->tahun = TahunAjaran::create([
            'tahun_ajaran' => '2025/2026',
            'semester' => 'Ganjil',
            'is_active' => true,
        ]);
    }

    private function makeShift(string $nama, string $mulai = '07:00', string $selesai = '14:00'): ShiftPelajaran
    {
        return ShiftPelajaran::create([
            'nama_shift' => $nama,
            'keterangan' => 'Keterangan '.$nama,
            'jam_mulai' => $mulai,
            'jam_selesai' => $selesai,
            'is_active' => true,
            'grade_levels' => null,
        ]);
    }

    private function makeSlot(?int $shiftId, string $hari, string $mulai, string $selesai, int $jamKe = 1): JamPelajaran
    {
        $kategori = in_array($hari, ['Senin', 'Selasa', 'Rabu', 'Kamis'], true) ? 'Senin-Kamis' : 'Jumat';

        return JamPelajaran::create([
            'kategori_hari' => $kategori,
            'hari' => $hari,
            'shift_id' => $shiftId,
            'jam_ke' => $jamKe,
            'jam_mulai' => $mulai,
            'jam_selesai' => $selesai,
            'jenis' => 'kbm',
        ]);
    }

    /**
     * Ambil blok <select id="jamKeXxx"> ... </select> dari konten halaman.
     */
    private function selectBlock(string $html, string $id): string
    {
        preg_match('/<select[^>]*id="'.$id.'"[^>]*>.*?<\/select>/s', $html, $m);

        return $m[0] ?? '';
    }

    public function test_upacara_dropdown_only_lists_slots_of_active_shift(): void
    {
        AppSetting::setScheduleMode(AppSetting::SCHEDULE_SHIFT);

        $shift1 = $this->makeShift('Shift 1 (Pagi)', '07:00', '12:00');
        $shift2 = $this->makeShift('Shift 2 (Siang)', '11:00', '18:00');

        // Shift 1: slot Senin pagi + slot Jumat pagi (harus tetap di dropdown Pembiasaan).
        $this->makeSlot($shift1->id, 'Senin', '07:00:00', '08:00:00', 1);
        $this->makeSlot($shift1->id, 'Jumat', '07:10:00', '07:50:00', 1);
        // Shift 2: slot Senin siang (11:00-11:40) — harus TIDAK bocor ke Shift 1.
        $this->makeSlot($shift2->id, 'Senin', '11:00:00', '11:40:00', 1);

        // Melihat Shift 1: dropdown Upacara hanya slot Senin milik Shift 1.
        $html = $this->actingAs($this->admin)
            ->get(route('admin.jam-pelajaran.index', ['tab' => 'Senin-Kamis', 'shift' => $shift1->id]))
            ->assertOk()
            ->getContent();

        $seninBlock = $this->selectBlock($html, 'jamKeSenin');
        $this->assertStringContainsString('Jam Ke-1 (07:00 - 08:00)', $seninBlock);
        $this->assertStringNotContainsString('Jam Ke-1 (11:00 - 11:40)', $seninBlock);
        $this->assertStringNotContainsString('Jam Ke-1 (07:10 - 07:50)', $seninBlock);

        // Dropdown Pembiasaan tetap berisi slot Jumat milik shift yang sama, bukan shift lain.
        $jumatBlock = $this->selectBlock($html, 'jamKeJumat');
        $this->assertStringContainsString('Jam Ke-1 (07:10 - 07:50)', $jumatBlock);
        $this->assertStringNotContainsString('Jam Ke-1 (11:00 - 11:40)', $jumatBlock);

        // Melihat Shift 2: dropdown Upacara hanya slot Senin milik Shift 2.
        $html = $this->actingAs($this->admin)
            ->get(route('admin.jam-pelajaran.index', ['tab' => 'Senin-Kamis', 'shift' => $shift2->id]))
            ->assertOk()
            ->getContent();

        $seninBlock = $this->selectBlock($html, 'jamKeSenin');
        $this->assertStringContainsString('Jam Ke-1 (11:00 - 11:40)', $seninBlock);
        $this->assertStringNotContainsString('Jam Ke-1 (07:00 - 08:00)', $seninBlock);

        $jumatBlock = $this->selectBlock($html, 'jamKeJumat');
        $this->assertStringNotContainsString('Jam Ke-1 (07:10 - 07:50)', $jumatBlock);
    }

    public function test_pembiasaan_dropdown_only_lists_friday_slots_of_active_shift(): void
    {
        AppSetting::setScheduleMode(AppSetting::SCHEDULE_SHIFT);

        $shift1 = $this->makeShift('Shift 1 (Pagi)', '07:00', '12:00');
        $shift2 = $this->makeShift('Shift 2 (Siang)', '13:00', '18:00');

        // Shift 1: slot Jumat pagi.
        $this->makeSlot($shift1->id, 'Jumat', '07:00:00', '07:40:00', 1);
        // Shift 2: slot Jumat siang + slot Senin (harus TIDAK muncul di Pembiasaan).
        $this->makeSlot($shift2->id, 'Jumat', '13:00:00', '14:00:00', 1);
        $this->makeSlot($shift2->id, 'Senin', '09:00:00', '10:00:00', 1);

        // Melihat Shift 2: dropdown Pembiasaan hanya slot Jumat milik Shift 2.
        $html = $this->actingAs($this->admin)
            ->get(route('admin.jam-pelajaran.index', ['tab' => 'Jumat', 'shift' => $shift2->id]))
            ->assertOk()
            ->getContent();

        $jumatBlock = $this->selectBlock($html, 'jamKeJumat');
        $this->assertStringContainsString('Jam Ke-1 (13:00 - 14:00)', $jumatBlock);
        $this->assertStringNotContainsString('Jam Ke-1 (07:00 - 07:40)', $jumatBlock);
        $this->assertStringNotContainsString('Jam Ke-1 (09:00 - 10:00)', $jumatBlock);

        // Slot Senin milik Shift 2 tetap muncul di dropdown Upacara (hari Senin).
        $seninBlock = $this->selectBlock($html, 'jamKeSenin');
        $this->assertStringContainsString('Jam Ke-1 (09:00 - 10:00)', $seninBlock);
        $this->assertStringNotContainsString('Jam Ke-1 (13:00 - 14:00)', $seninBlock);
    }

    public function test_global_mode_agenda_dropdown_only_lists_global_slots(): void
    {
        AppSetting::setScheduleMode(AppSetting::SCHEDULE_GLOBAL);

        $shift = $this->makeShift('Shift 1 (Pagi)', '07:00', '12:00');

        // Slot global (tanpa shift) & slot milik shift — hanya global yang tampil.
        $this->makeSlot(null, 'Senin', '07:00:00', '07:40:00', 1);
        $this->makeSlot($shift->id, 'Senin', '11:00:00', '11:40:00', 1);

        $html = $this->actingAs($this->admin)
            ->get(route('admin.jam-pelajaran.index', ['tab' => 'Senin-Kamis']))
            ->assertOk()
            ->getContent();

        $seninBlock = $this->selectBlock($html, 'jamKeSenin');
        $this->assertStringContainsString('Jam Ke-1 (07:00 - 07:40)', $seninBlock);
        $this->assertStringNotContainsString('Jam Ke-1 (11:00 - 11:40)', $seninBlock);
    }

    public function test_agenda_dropdown_formats_time_consistently_hh_mm(): void
    {
        AppSetting::setScheduleMode(AppSetting::SCHEDULE_SHIFT);

        $shift = $this->makeShift('Shift 1 (Pagi)', '07:00', '12:00');

        // Beragam representasi penyimpanan waktu harus tampil konsisten HH:MM - HH:MM.
        $this->makeSlot($shift->id, 'Senin', '07:00:00', '07:45:00', 1);
        $this->makeSlot($shift->id, 'Senin', '08:00', '08:40', 2);
        $this->makeSlot($shift->id, 'Senin', '2026-09-26 09:00:00', '2026-09-26 09:40:00', 3);

        $html = $this->actingAs($this->admin)
            ->get(route('admin.jam-pelajaran.index', ['tab' => 'Senin-Kamis', 'shift' => $shift->id]))
            ->assertOk()
            ->getContent();

        $seninBlock = $this->selectBlock($html, 'jamKeSenin');
        $this->assertStringContainsString('Jam Ke-1 (07:00 - 07:45)', $seninBlock);
        $this->assertStringContainsString('Jam Ke-2 (08:00 - 08:40)', $seninBlock);
        $this->assertStringContainsString('Jam Ke-3 (09:00 - 09:40)', $seninBlock);
    }

    public function test_format_jam_hm_never_produces_truncated_garbage(): void
    {
        // Nilai normal dalam berbagai representasi → HH:MM konsisten.
        $this->assertSame('07:45', JamPelajaran::formatJamHm('07:45:00'));
        $this->assertSame('07:00', JamPelajaran::formatJamHm('07:00'));
        $this->assertSame('09:40', JamPelajaran::formatJamHm('2026-09-26 09:40:00'));
        $this->assertSame('13:05', JamPelajaran::formatJamHm('13:05:00.123456'));

        // Nilai korup / kosong → placeholder aman, bukan teks acak seperti '07:'.
        $this->assertSame('--:--', JamPelajaran::formatJamHm('07:'));
        $this->assertSame('--:--', JamPelajaran::formatJamHm(null));
        $this->assertSame('--:--', JamPelajaran::formatJamHm(''));
    }

    public function test_combined_agenda_form_saves_upacara_and_pembiasaan_in_one_request(): void
    {
        AppSetting::setScheduleMode(AppSetting::SCHEDULE_SHIFT);

        $shift = $this->makeShift('Shift 1 (Pagi)');

        $response = $this->actingAs($this->admin)
            ->post(route('admin.agenda-rutin.upsert'), [
                'redirect_tab' => 'Senin-Kamis',
                'redirect_shift' => $shift->id,
                'redirect_ta' => $this->tahun->id,
                'agenda' => [
                    'Senin' => ['jam_ke' => 1, 'is_active' => 1],
                    'Jumat' => ['jam_ke' => 2, 'is_active' => 0],
                ],
            ]);

        $response->assertRedirect(route('admin.jam-pelajaran.index', [
            'tab' => 'Senin-Kamis',
            'shift' => $shift->id,
            'ta' => $this->tahun->id,
        ]));
        $response->assertSessionHas('success', 'Pengaturan Kegiatan Khusus (Upacara Bendera & Pembiasaan Jumat) berhasil disimpan.');

        $this->assertDatabaseHas('agenda_rutin', [
            'hari' => 'Senin',
            'jam_ke' => 1,
            'nama_agenda' => 'Upacara Bendera',
            'is_active' => 1,
            'shift_id' => $shift->id,
        ]);
        $this->assertDatabaseHas('agenda_rutin', [
            'hari' => 'Jumat',
            'jam_ke' => 2,
            'nama_agenda' => 'Pembiasaan Jumat',
            'is_active' => 0,
            'shift_id' => $shift->id,
        ]);

        // Isolasi: tidak ada record Global (shift 0) yang ikut tercipta.
        $this->assertDatabaseMissing('agenda_rutin', ['hari' => 'Senin', 'jam_ke' => 1, 'shift_id' => 0]);
        $this->assertDatabaseMissing('agenda_rutin', ['hari' => 'Jumat', 'jam_ke' => 2, 'shift_id' => 0]);
    }

    public function test_combined_agenda_form_toggle_off_is_persisted_when_checkbox_absent(): void
    {
        AppSetting::setScheduleMode(AppSetting::SCHEDULE_SHIFT);

        // Toggle Upacara di-uncheck → field is_active tidak terkirim oleh browser.
        // Tanpa redirect_shift → tersimpan sebagai konfigurasi Global (shift_id = 0).
        $this->actingAs($this->admin)
            ->post(route('admin.agenda-rutin.upsert'), [
                'redirect_tab' => 'Jumat',
                'agenda' => [
                    'Senin' => ['jam_ke' => 3],
                    'Jumat' => ['jam_ke' => 1, 'is_active' => 1],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('agenda_rutin', [
            'hari' => 'Senin',
            'jam_ke' => 3,
            'is_active' => 0,
            'shift_id' => 0,
        ]);
        $this->assertDatabaseHas('agenda_rutin', [
            'hari' => 'Jumat',
            'jam_ke' => 1,
            'is_active' => 1,
            'shift_id' => 0,
        ]);
    }

    public function test_legacy_single_hari_agenda_upsert_still_works(): void
    {
        AppSetting::setScheduleMode(AppSetting::SCHEDULE_GLOBAL);

        $this->actingAs($this->admin)
            ->post(route('admin.agenda-rutin.upsert'), [
                'hari' => 'Senin',
                'jam_ke' => 1,
                'is_active' => 1,
                'redirect_tab' => 'Senin-Kamis',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('agenda_rutin', [
            'hari' => 'Senin',
            'jam_ke' => 1,
            'nama_agenda' => 'Upacara Bendera',
            'is_active' => 1,
            'shift_id' => 0,
        ]);
    }

    public function test_agenda_state_is_isolated_per_shift_in_view(): void
    {
        AppSetting::setScheduleMode(AppSetting::SCHEDULE_SHIFT);

        $shift1 = $this->makeShift('Shift 1 (Pagi)');
        $shift2 = $this->makeShift('Shift 2 (Siang)');

        // Setiap shift punya slot Senin sendiri agar dropdown tidak kosong.
        $this->makeSlot($shift1->id, 'Senin', '07:00:00', '07:40:00', 1);
        $this->makeSlot($shift2->id, 'Senin', '12:00:00', '12:40:00', 1);

        // Config khusus Shift 1: Upacara Aktif di Jam Ke-1 (shift 2 TIDAK punya config).
        AgendaRutin::create([
            'hari' => 'Senin',
            'jam_ke' => 1,
            'nama_agenda' => 'Upacara Bendera',
            'is_active' => true,
            'shift_id' => $shift1->id,
        ]);

        // ---- Tab Shift 1: status Aktif, Jam Ke-1 terpilih, badge hint shift ----
        $html1 = $this->actingAs($this->admin)
            ->get(route('admin.jam-pelajaran.index', ['tab' => 'Senin-Kamis', 'shift' => $shift1->id]))
            ->assertOk()
            ->getContent();

        $seninBlock1 = $this->selectBlock($html1, 'jamKeSenin');
        $this->assertStringNotContainsString('Pilih Jam Ke- untuk shift ini', $seninBlock1);
        $this->assertMatchesRegularExpression('/<option value="1"[^>]*selected[^>]*>/', $seninBlock1);

        preg_match('/<input[^>]*id="switchAgendaSenin"[^>]*>/s', $html1, $m1);
        $this->assertStringContainsString('checked', $m1[0] ?? '');
        $this->assertStringContainsString('Pengaturan khusus untuk Shift 1 (Pagi)', $html1);

        // ---- Tab Shift 2 (belum pernah diatur): default Non-Aktif & dropdown reset ----
        $html2 = $this->actingAs($this->admin)
            ->get(route('admin.jam-pelajaran.index', ['tab' => 'Senin-Kamis', 'shift' => $shift2->id]))
            ->assertOk()
            ->getContent();

        $seninBlock2 = $this->selectBlock($html2, 'jamKeSenin');
        $this->assertStringContainsString('— Pilih Jam Ke- untuk shift ini —', $seninBlock2);
        $this->assertDoesNotMatchRegularExpression('/<option value="1"[^>]*selected[^>]*>/', $seninBlock2);

        preg_match('/<input[^>]*id="switchAgendaSenin"[^>]*>/s', $html2, $m2);
        $this->assertStringNotContainsString('checked', $m2[0] ?? '');
        $this->assertStringContainsString('○ Non-Aktif', $html2);
        $this->assertStringContainsString('Pengaturan khusus untuk Shift 2 (Siang)', $html2);
    }

    public function test_saving_agenda_for_shift_does_not_create_global_or_other_shift_records(): void
    {
        AppSetting::setScheduleMode(AppSetting::SCHEDULE_SHIFT);

        $shift1 = $this->makeShift('Shift 1 (Pagi)');
        $shift2 = $this->makeShift('Shift 2 (Siang)');

        $this->actingAs($this->admin)
            ->post(route('admin.agenda-rutin.upsert'), [
                'redirect_tab' => 'Senin-Kamis',
                'redirect_shift' => $shift1->id,
                'agenda' => [
                    'Senin' => ['jam_ke' => 1, 'is_active' => 1],
                    'Jumat' => ['jam_ke' => 2, 'is_active' => 1],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('agenda_rutin', ['hari' => 'Senin', 'jam_ke' => 1, 'shift_id' => $shift1->id, 'is_active' => 1]);
        $this->assertDatabaseHas('agenda_rutin', ['hari' => 'Jumat', 'jam_ke' => 2, 'shift_id' => $shift1->id, 'is_active' => 1]);

        // Tidak ada konfigurasi Global (shift 0) maupun shift lain yang bocor.
        $this->assertDatabaseMissing('agenda_rutin', ['hari' => 'Senin', 'jam_ke' => 1, 'shift_id' => 0]);
        $this->assertDatabaseMissing('agenda_rutin', ['hari' => 'Jumat', 'jam_ke' => 2, 'shift_id' => 0]);
        $this->assertDatabaseMissing('agenda_rutin', ['hari' => 'Senin', 'jam_ke' => 1, 'shift_id' => $shift2->id]);
        $this->assertDatabaseMissing('agenda_rutin', ['hari' => 'Jumat', 'jam_ke' => 2, 'shift_id' => $shift2->id]);
    }
}