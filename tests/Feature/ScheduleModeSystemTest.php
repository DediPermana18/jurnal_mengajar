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
 * Fitur Read-Only Schedule Mode (Global vs Multi-Shift) pada Master Jam Pelajaran:
 *  - Mode penjadwalan HANYA dikendalikan oleh mode_jadwal Tahun Ajaran terpilih
 *    (data master Tahun Ajaran) — tidak ada toggle manual di halaman Master.
 *  - Tipikal fallback 'schedule_mode' di app_settings hanya berlaku untuk TA legacy.
 *  - Mode Global: murni slot jam Global; seluruh UI shift disembunyikan.
 *  - Mode Multi-Shift: tab shift + slot jam per shift; tampilan slot Global disembunyikan.
 *  - Validator plotting menolak kelas tanpa alokasi shift saat T.A multi-shift.
 */
class ScheduleModeSystemTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected TahunAjaran $tahun;

    protected Jurusan $jurusan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'nama' => 'Admin Sistem',
            'username' => 'adminsys',
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

    private function makeShift(string $nama, string $mulai = '07:00', string $selesai = '14:00'): ShiftPelajaran
    {
        return ShiftPelajaran::create([
            'nama_shift' => $nama,
            'keterangan' => 'Keterangan '.$nama,
            'jam_mulai' => $mulai,
            'jam_selesai' => $selesai,
            'is_active' => true,
        ]);
    }

    private function makeSlot(?int $shiftId, string $mulai, string $selesai, int $jamKe = 1, string $hari = 'Senin'): JamPelajaran
    {
        return JamPelajaran::create([
            'kategori_hari' => in_array($hari, ['Senin', 'Selasa', 'Rabu', 'Kamis'], true) ? 'Senin-Kamis' : 'Jumat',
            'hari' => $hari,
            'shift_id' => $shiftId,
            'jam_ke' => $jamKe,
            'jam_mulai' => $mulai.':00',
            'jam_selesai' => $selesai.':00',
            'jenis' => 'kbm',
            'tahun_ajaran_id' => $this->tahun->id,
        ]);
    }

    private function plottingPayload(int $kelasId, int $mapelId, int $guruId, int $jamMulai = 1, int $jamSelesai = 1): array
    {
        return [
            'id_kelas' => $kelasId,
            'hari' => 'Senin',
            'jam_ke_mulai' => $jamMulai,
            'jam_ke_selesai' => $jamSelesai,
            'id_mapel' => $mapelId,
            'id_guru' => $guruId,
        ];
    }

    public function test_schedule_mode_is_read_only_from_tahun_ajaran_global_and_hides_shift_ui(): void
    {
        // Tanpa mode eksplisit, Tahun Ajaran ikut tipe penjadwalan sistem (default Global).
        $this->assertSame(AppSetting::SCHEDULE_GLOBAL, AppSetting::scheduleMode());

        $this->makeShift('Shift 1 (Pagi)');
        $this->makeSlot(null, '07:00', '07:45');

        // Tidak ada toggle manual di halaman — parameter ?mode=shift pun diabaikan:
        // mode sepenuhnya ditentukan oleh mode_jadwal Tahun Ajaran (read-only).
        $response = $this->actingAs($this->admin)
            ->get(route('admin.jam-pelajaran.index', ['tab' => 'Senin-Kamis', 'mode' => 'shift']));

        $response->assertOk()
            ->assertSee('Mode Global')
            ->assertSee('07.00 – 07.45')
            ->assertDontSee('Pilih Shift:')
            ->assertDontSee('+ Tambah Shift')
            ->assertDontSee('Kelola Daftar Shift')
            ->assertDontSee('mode=shift')
            ->assertDontSee('Kembali ke Mode Global')
            ->assertDontSee('Tipe Penjadwalan Sekolah')
            ->assertDontSee('Multi-Shift');
    }

    public function test_shift_schedule_mode_defaults_index_to_shift_management_ui_hiding_global_slots(): void
    {
        AppSetting::setScheduleMode(AppSetting::SCHEDULE_SHIFT);

        $s1 = $this->makeShift('Shift 1 (Pagi)');
        $this->makeSlot($s1->id, '13:00', '13:45');
        // Slot Global tidak boleh bocor ke tampilan shift.
        $this->makeSlot(null, '07:00', '07:45');

        // Tanpa parameter mode manual, halaman langsung membuka pengelolaan shift;
        // tidak ada toggle/tautan untuk kembali ke tampilan slot Global.
        $response = $this->actingAs($this->admin)
            ->get(route('admin.jam-pelajaran.index', ['tab' => 'Senin-Kamis']));

        $response->assertOk()
            ->assertSee('Mode Shift: Shift 1 (Pagi)', false)
            ->assertSee('Pilih Shift:')
            ->assertSee('+ Tambah Shift')
            ->assertSee('Kelola Daftar Shift')
            ->assertSee('13.00 – 13.45')
            ->assertDontSee('07.00 – 07.45')
            ->assertDontSee('Kembali ke Mode Global')
            ->assertDontSee('mode=global');
    }

    public function test_plotting_rejects_class_without_shift_when_school_is_multishift(): void
    {
        AppSetting::setScheduleMode(AppSetting::SCHEDULE_SHIFT);

        $s1 = $this->makeShift('Shift 1 (Pagi)');
        $this->makeSlot($s1->id, '13:00', '13:45');

        // Kelas TANPA shift (tidak teralokasi) — menabrak kebijakan multi-shift.
        $kelas = Kelas::create([
            'nama_kelas' => 'X RPL 1',
            'tingkat' => 'X',
            'id_jurusan' => $this->jurusan->id,
        ]);

        $mapel = MataPelajaran::create(['nama_mapel' => 'Matematika']);

        $guru = User::create([
            'nama' => 'Drs. Supriyanto',
            'nip' => '197001011995011001',
            'username' => 'gurucoba',
            'password' => bcrypt('password'),
            'role' => User::ROLE_GURU,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.jadwal.store'), $this->plottingPayload($kelas->id, $mapel->id, $guru->id));

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Gagal! Sekolah menggunakan tipe penjadwalan Multi-Shift — Kelas "X RPL 1" belum dialokasikan ke shift tertentu. Tetapkan shift pada data kelas sebelum melakukan plotting jadwal.');

        $this->assertDatabaseCount('jadwal_pelajaran', 0);
    }

    public function test_tahun_ajaran_store_persists_mode_jadwal(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('tahun-ajaran.store'), [
                'tahun_ajaran' => '2024/2025',
                'semester' => 'Ganjil',
                'mode_jadwal' => TahunAjaran::MODE_SHIFT,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('tahun_ajaran', [
            'tahun_ajaran' => '2024/2025',
            'semester' => 'Ganjil',
            'mode_jadwal' => 'shift',
        ]);
    }

    public function test_tahun_ajaran_update_persists_mode_jadwal(): void
    {
        $year = TahunAjaran::create([
            'tahun_ajaran' => '2024/2025',
            'semester' => 'Ganjil',
            'mode_jadwal' => 'shift',
        ]);

        $response = $this->actingAs($this->admin)
            ->put(route('tahun-ajaran.update', $year->id), [
                'tahun_ajaran' => '2024/2025',
                'semester' => 'Ganjil',
                'mode_jadwal' => TahunAjaran::MODE_GLOBAL,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('tahun_ajaran', [
            'id' => $year->id,
            'mode_jadwal' => 'global',
        ]);
    }

    public function test_master_jam_pelajaran_uses_tahun_ajaran_shift_mode_over_system_global(): void
    {
        // Sistem default 'global', tetapi Tahun Ajaran aktif berspesifikasi Multi-Shift.
        $this->tahun->update(['mode_jadwal' => TahunAjaran::MODE_SHIFT]);

        $s1 = $this->makeShift('Shift 1 (Pagi)');
        $this->makeSlot($s1->id, '13:00', '13:45');

        $this->actingAs($this->admin)
            ->get(route('admin.jam-pelajaran.index', ['tab' => 'Senin-Kamis']))
            ->assertOk()
            ->assertSee('Mode Shift: Shift 1 (Pagi)', false)
            ->assertSee('Pilih Shift:')
            ->assertSee('+ Tambah Shift')
            ->assertSee('13.00 – 13.45');
    }

    public function test_master_jam_pelajaran_uses_tahun_ajaran_global_mode_over_system_shift(): void
    {
        // Sistem Multi-Shift, tetapi Tahun Ajaran aktif ber-mode Global -> UI murni Global.
        AppSetting::setScheduleMode(AppSetting::SCHEDULE_SHIFT);
        $this->tahun->update(['mode_jadwal' => TahunAjaran::MODE_GLOBAL]);

        $this->makeShift('Shift 1 (Pagi)');

        $this->actingAs($this->admin)
            ->get(route('admin.jam-pelajaran.index', ['tab' => 'Senin-Kamis', 'mode' => 'shift']))
            ->assertOk()
            ->assertSee('Mode Global')
            ->assertDontSee('Pilih Shift:')
            ->assertDontSee('+ Tambah Shift')
            ->assertDontSee('Kelola Daftar Shift')
            ->assertDontSee('mode=shift');
    }

    public function test_plotting_validator_uses_tahun_ajaran_shift_mode(): void
    {
        // Sistem default 'global', tetapi T.A aktif ber-mode Multi-Shift -> validator
        // tetap menolak kelas tanpa alokasi shift.
        $this->tahun->update(['mode_jadwal' => TahunAjaran::MODE_SHIFT]);

        $s1 = $this->makeShift('Shift 1 (Pagi)');
        $this->makeSlot($s1->id, '13:00', '13:45');

        $kelas = Kelas::create([
            'nama_kelas' => 'X RPL 1',
            'tingkat' => 'X',
            'id_jurusan' => $this->jurusan->id,
        ]);

        $mapel = MataPelajaran::create(['nama_mapel' => 'Matematika']);

        $guru = User::create([
            'nama' => 'Drs. Supriyanto',
            'nip' => '197001011995011001',
            'username' => 'gurucoba',
            'password' => bcrypt('password'),
            'role' => User::ROLE_GURU,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.jadwal.store'), $this->plottingPayload($kelas->id, $mapel->id, $guru->id));

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Gagal! Sekolah menggunakan tipe penjadwalan Multi-Shift — Kelas "X RPL 1" belum dialokasikan ke shift tertentu. Tetapkan shift pada data kelas sebelum melakukan plotting jadwal.');

        $this->assertDatabaseCount('jadwal_pelajaran', 0);
    }

    public function test_plotting_allows_class_with_allocated_shift_when_school_is_multishift(): void
    {
        AppSetting::setScheduleMode(AppSetting::SCHEDULE_SHIFT);

        $s1 = $this->makeShift('Shift 1 (Pagi)');
        $this->makeSlot($s1->id, '13:00', '13:45');

        // Kelas teralokasi ke Shift 1 — plotting valid dan hanya memakai slot shift-nya.
        $kelas = Kelas::create([
            'nama_kelas' => 'X RPL 1',
            'tingkat' => 'X',
            'id_jurusan' => $this->jurusan->id,
            'shift_id' => $s1->id,
        ]);

        $mapel = MataPelajaran::create(['nama_mapel' => 'Matematika']);

        $guru = User::create([
            'nama' => 'Drs. Supriyanto',
            'nip' => '197001011995011001',
            'username' => 'gurucoba',
            'password' => bcrypt('password'),
            'role' => User::ROLE_GURU,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.jadwal.store'), $this->plottingPayload($kelas->id, $mapel->id, $guru->id));

        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('jadwal_pelajaran', [
            'hari' => 'Senin',
            'id_kelas' => $kelas->id,
            'id_guru' => $guru->id,
        ]);
    }

    /**
     * Bug: matriks kelas terikat Shift 2 dulu mencampur slot Shift 1 + Global
     * (total 19 slot, Jam 1 muncul 07.00 milik Shift 1, dan blok "Pulang Sekolah"
     * berulang di tengah jadwal). Query harus STRICT terhadap shift kelas:
     * hanya slot dengan shift_id = shift kelas yang diambil, tanpa shift lain/Global.
     */
    public function test_plotting_matrix_shows_only_own_shift_slots_and_pulang_lock_per_shift(): void
    {
        AppSetting::setScheduleMode(AppSetting::SCHEDULE_SHIFT);

        // Shift 1 (Pagi) + slot Global TIDAK boleh bocor ke matriks kelas Shift 2.
        $s1 = $this->makeShift('Shift 1 (Pagi)');
        $this->makeSlot($s1->id, '07:00', '07:45');
        $this->makeSlot(null, '09:00', '09:45');

        // Shift 2 (Siang): 7 slot KBM, Jam 1 mulai pukul 11.00 (bukan 07.00).
        $s2 = $this->makeShift('Shift 2 (Siang)', '11:00', '18:00');
        foreach (range(1, 7) as $jamKe) {
            $this->makeSlot(
                $s2->id,
                sprintf('%02d:00', 10 + $jamKe),
                sprintf('%02d:40', 10 + $jamKe),
                $jamKe
            );
        }

        // Kelas XI RPL 1 terikat langsung ke Shift 2.
        $kelas = Kelas::create([
            'nama_kelas' => 'XI RPL 1',
            'tingkat' => 'XI',
            'id_jurusan' => $this->jurusan->id,
            'shift_id' => $s2->id,
        ]);

        $content = $this->actingAs($this->admin)
            ->get(route('admin.jadwal.index', ['id_kelas' => $kelas->id, 'hari' => 'Senin']))
            ->assertOk()
            ->getContent();

        // Matriks HANYA memuat 7 slot Shift 2 (mulai 11.00), tanpa Shift 1/Global.
        $this->assertStringContainsString('Shift 2 (Siang)', $content);
        $this->assertStringContainsString('Total 7 Slot', $content);
        $this->assertStringContainsString('11.00 – 11.40', $content);
        $this->assertStringNotContainsString('07.00 – 07.45', $content);
        $this->assertStringNotContainsString('09.00 – 09.45', $content);

        // Plotting pada slot Shift 2 harus mendarat di slot MURNI milik Shift 2.
        $mapel = MataPelajaran::create(['nama_mapel' => 'Matematika']);
        $guru = User::create([
            'nama' => 'Drs. Supriyanto',
            'nip' => '197001011995011001',
            'username' => 'gurucoba',
            'password' => bcrypt('password'),
            'role' => User::ROLE_GURU,
            'is_active' => true,
        ]);
        $slotShift2Jam1 = JamPelajaran::where('shift_id', $s2->id)->where('jam_ke', 1)->firstOrFail();

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.jadwal.store'), $this->plottingPayload($kelas->id, $mapel->id, $guru->id));

        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('jadwal_pelajaran', [
            'id_kelas' => $kelas->id,
            'hari' => 'Senin',
            'id_jam' => $slotShift2Jam1->id,
        ]);

        // Jam Pulang Shift 2 (XI selesai setelah Jam ke-5): HANYA Jam 6 & Jam 7
        // yang diblokir "Pulang Sekolah" — tidak boleh ada blok berulang di tengah.
        JamPulang::create([
            'shift_id' => $s2->id,
            'kategori_hari' => 'Senin-Kamis',
            'tingkat' => 'XI',
            'max_jam_ke' => 5,
        ]);

        $content2 = $this->actingAs($this->admin)
            ->get(route('admin.jadwal.index', ['id_kelas' => $kelas->id, 'hari' => 'Senin']))
            ->assertOk()
            ->getContent();

        $this->assertSame(2, substr_count($content2, 'Kelas XI selesai KBM setelah Jam ke-5'));
    }
}