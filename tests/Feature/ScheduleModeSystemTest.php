<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\JamPelajaran;
use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\ShiftPelajaran;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fitur Global System Toggle untuk Schedule Mode (Global vs Multi-Shift):
 *  - Konfigurasi sistem 'schedule_mode' disimpan di app_settings (global).
 *  - Toggle "Tipe Penjadwalan Sekolah" di bagian atas Master Jam Pelajaran.
 *  - Mode Global menyembunyikan seluruh UI shift (??mode=shift diabaikan).
 *  - Mode Multi-Shift membuka pengelolaan shift; slot jam terisolasi per shift.
 *  - Validator plotting menolak kelas tanpa alokasi shift saat sekolah multi-shift.
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

    public function test_default_schedule_mode_is_global_and_hides_shift_ui_even_with_mode_param(): void
    {
        // Tanpa setting eksplisit, tipe penjadwalan sistem = Global.
        $this->assertSame(AppSetting::SCHEDULE_GLOBAL, AppSetting::scheduleMode());

        $this->makeShift('Shift 1 (Pagi)');
        $this->makeSlot(null, '07:00', '07:45');

        $response = $this->actingAs($this->admin)
            ->get(route('admin.jam-pelajaran.index', ['tab' => 'Senin-Kamis', 'mode' => 'shift']));

        $response->assertOk()
            ->assertSee('Mode Global')
            ->assertDontSee('Pilih Shift:')
            ->assertDontSee('+ Tambah Shift')
            ->assertDontSee('Kelola Daftar Shift')
            ->assertDontSee('mode=shift')
            // Toggle "Tipe Penjadwalan Sekolah" tersedia di bagian atas halaman.
            ->assertSee('Tipe Penjadwalan')
            ->assertSee('Multi-Shift');
    }

    public function test_shift_schedule_mode_defaults_index_to_shift_management_ui(): void
    {
        AppSetting::setScheduleMode(AppSetting::SCHEDULE_SHIFT);

        $s1 = $this->makeShift('Shift 1 (Pagi)');
        $this->makeSlot($s1->id, '13:00', '13:45');

        // Tanpa ?mode=..., halaman langsung membuka pengelolaan shift.
        $response = $this->actingAs($this->admin)
            ->get(route('admin.jam-pelajaran.index', ['tab' => 'Senin-Kamis']));

        $response->assertOk()
            ->assertSee('Mode Shift: Shift 1 (Pagi)', false)
            ->assertSee('Pilih Shift:')
            ->assertSee('+ Tambah Shift')
            ->assertSee('Kelola Daftar Shift')
            ->assertSee('13.00 – 13.45');
    }

    public function test_shift_schedule_mode_allows_explicit_global_view(): void
    {
        AppSetting::setScheduleMode(AppSetting::SCHEDULE_SHIFT);

        $s1 = $this->makeShift('Shift 1 (Pagi)');
        $this->makeSlot($s1->id, '13:00', '13:45');
        $this->makeSlot(null, '07:00', '07:45');

        // ?mode=global tetap tersedia untuk mengelola slot dasar/global.
        $response = $this->actingAs($this->admin)
            ->get(route('admin.jam-pelajaran.index', ['tab' => 'Senin-Kamis', 'mode' => 'global']));

        $response->assertOk()
            ->assertSee('Mode Global')
            ->assertDontSee('Pilih Shift:')
            ->assertDontSee('+ Tambah Shift')
            ->assertDontSee('Kelola Daftar Shift')
            ->assertSee('07.00 – 07.45');
    }

    public function test_update_schedule_mode_persists_setting_and_redirects(): void
    {
        $this->makeShift('Shift 1 (Pagi)');

        // Beralih ke Multi-Shift.
        $response = $this->actingAs($this->admin)
            ->post(route('admin.jam-pelajaran.schedule-mode'), ['schedule_mode' => AppSetting::SCHEDULE_SHIFT]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('app_settings', [
            'key' => 'schedule_mode',
            'value' => AppSetting::SCHEDULE_SHIFT,
        ]);
        $this->assertSame(AppSetting::SCHEDULE_SHIFT, AppSetting::scheduleMode());

        // Kembali ke Global.
        $response = $this->actingAs($this->admin)
            ->post(route('admin.jam-pelajaran.schedule-mode'), ['schedule_mode' => AppSetting::SCHEDULE_GLOBAL]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('app_settings', [
            'key' => 'schedule_mode',
            'value' => AppSetting::SCHEDULE_GLOBAL,
        ]);
        $this->assertSame(AppSetting::SCHEDULE_GLOBAL, AppSetting::scheduleMode());

        // Mode invalid ditolak oleh validasi.
        $this->actingAs($this->admin)
            ->post(route('admin.jam-pelajaran.schedule-mode'), ['schedule_mode' => 'campuran'])
            ->assertSessionHasErrors('schedule_mode');
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
}