<?php

namespace Tests\Feature;

use App\Models\JamPelajaran;
use App\Models\JadwalPelajaran;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Ruangan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class TeacherScheduleNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $guruValid;
    protected User $guruNoHpKosong;
    protected User $guruNoHpPendek;
    protected Kelas $kelas;
    protected MataPelajaran $mapel;
    protected Ruangan $ruangan;
    protected int $tahunAjaranId;

    protected function setUp(): void
    {
        parent::setUp();
        cache()->flush();

        $ta = \App\Models\TahunAjaran::create([
            'tahun_ajaran' => '2026/2027',
            'semester' => 'Ganjil',
            'is_active' => true,
            'is_testing_data' => false,
        ]);
        $this->tahunAjaranId = $ta->id;

        // 1. Setup User
        $this->guruValid = User::create([
            'username' => 'budi_santoso',
            'password' => bcrypt('password'),
            'role' => 'guru',
            'nama' => 'Budi Santoso',
            'no_hp' => '081234567890',
            'is_testing_data' => false,
        ]);

        $this->guruNoHpKosong = User::create([
            'username' => 'guru_kosong',
            'password' => bcrypt('password'),
            'role' => 'guru',
            'nama' => 'Guru Tanpa No HP',
            'no_hp' => null,
            'is_testing_data' => false,
        ]);

        $this->guruNoHpPendek = User::create([
            'username' => 'guru_pendek',
            'password' => bcrypt('password'),
            'role' => 'guru',
            'nama' => 'Guru No HP Pendek',
            'no_hp' => '0812',
            'is_testing_data' => false,
        ]);

        // 2. Setup Master Data
        $this->kelas = Kelas::create(['nama_kelas' => 'X-RPL-1', 'tingkat' => '10', 'is_testing_data' => false]);
        $this->mapel = MataPelajaran::create(['nama_mapel' => 'Matematika', 'kode_mapel' => 'MTK', 'is_testing_data' => false]);
        $this->ruangan = Ruangan::create(['nama_ruangan' => 'R-101', 'kode_ruangan' => 'R101', 'is_testing_data' => false]);
    }

    public function test_command_runs_successfully_and_skips_invalid_phone_numbers()
    {
        Log::spy();

        // Preset Carbon to Monday 06:30
        Carbon::setTestNow(Carbon::parse('2026-09-28 06:30:00'));

        $kelas2 = Kelas::create(['nama_kelas' => 'X-RPL-2', 'tingkat' => '10', 'is_testing_data' => false]);

        $jam = JamPelajaran::create([
            'hari' => 'Senin',
            'kategori_hari' => 'Senin-Kamis',
            'jam_ke' => 1,
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '07:45:00',
            'jenis' => 'kbm',
            'tahun_ajaran_id' => $this->tahunAjaranId,
            'is_testing_data' => false,
        ]);

        JadwalPelajaran::create([
            'group_id' => (string) \Illuminate\Support\Str::uuid(),
            'id_tahun_ajaran' => $this->tahunAjaranId,
            'hari' => 'Senin',
            'id_guru' => $this->guruNoHpKosong->id,
            'id_mapel' => $this->mapel->id,
            'id_kelas' => $this->kelas->id,
            'id_ruangan' => $this->ruangan->id,
            'id_jam' => $jam->id,
            'is_testing_data' => false,
        ]);

        JadwalPelajaran::create([
            'group_id' => (string) \Illuminate\Support\Str::uuid(),
            'id_tahun_ajaran' => $this->tahunAjaranId,
            'hari' => 'Senin',
            'id_guru' => $this->guruNoHpPendek->id,
            'id_mapel' => $this->mapel->id,
            'id_kelas' => $kelas2->id,
            'id_ruangan' => $this->ruangan->id,
            'id_jam' => $jam->id,
            'is_testing_data' => false,
        ]);

        $this->artisan('guru:notif-jadwal', ['--type' => 'pagi'])
            ->assertExitCode(0);

        Log::shouldHaveReceived('warning')
            ->with("Nomor HP tidak valid untuk guru: {$this->guruNoHpKosong->name}");

        Log::shouldHaveReceived('warning')
            ->with("Nomor HP tidak valid untuk guru: {$this->guruNoHpPendek->name}");
    }

    public function test_agenda_pagi_combines_consecutive_hours_into_block_schedule()
    {
        Carbon::setTestNow(Carbon::parse('2026-09-28 06:30:00')); // Monday

        // Jam 1 (07:00-07:45) & Jam 2 (07:45-08:30)
        $jam1 = JamPelajaran::create([
            'hari' => 'Senin',
            'kategori_hari' => 'Senin-Kamis',
            'jam_ke' => 1,
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '07:45:00',
            'jenis' => 'kbm',
            'tahun_ajaran_id' => $this->tahunAjaranId,
            'is_testing_data' => false,
        ]);

        $jam2 = JamPelajaran::create([
            'hari' => 'Senin',
            'kategori_hari' => 'Senin-Kamis',
            'jam_ke' => 2,
            'jam_mulai' => '07:45:00',
            'jam_selesai' => '08:30:00',
            'jenis' => 'kbm',
            'tahun_ajaran_id' => $this->tahunAjaranId,
            'is_testing_data' => false,
        ]);

        $groupId = (string) \Illuminate\Support\Str::uuid();

        JadwalPelajaran::create([
            'group_id' => $groupId,
            'id_tahun_ajaran' => $this->tahunAjaranId,
            'hari' => 'Senin',
            'id_guru' => $this->guruValid->id,
            'id_mapel' => $this->mapel->id,
            'id_kelas' => $this->kelas->id,
            'id_ruangan' => $this->ruangan->id,
            'id_jam' => $jam1->id,
            'is_testing_data' => false,
        ]);

        JadwalPelajaran::create([
            'group_id' => $groupId,
            'id_tahun_ajaran' => $this->tahunAjaranId,
            'hari' => 'Senin',
            'id_guru' => $this->guruValid->id,
            'id_mapel' => $this->mapel->id,
            'id_kelas' => $this->kelas->id,
            'id_ruangan' => $this->ruangan->id,
            'id_jam' => $jam2->id,
            'is_testing_data' => false,
        ]);

        $this->artisan('guru:notif-jadwal', ['--type' => 'pagi', '--dry-run' => true])
            ->expectsOutputToContain('Agenda Mengajar Hari Ini')
            ->expectsOutputToContain('Matematika')
            ->expectsOutputToContain('Jam Ke-1–2 (07.00–08.30 WIB)')
            ->assertExitCode(0);
    }

    public function test_pengingat_mulai_kbm_triggers_h_minus_5_minutes()
    {
        // 5 minutes before 07:00 is 06:55
        Carbon::setTestNow(Carbon::parse('2026-09-28 06:55:00')); // Monday

        $jam = JamPelajaran::create([
            'hari' => 'Senin',
            'kategori_hari' => 'Senin-Kamis',
            'jam_ke' => 1,
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '07:45:00',
            'jenis' => 'kbm',
            'tahun_ajaran_id' => $this->tahunAjaranId,
            'is_testing_data' => false,
        ]);

        JadwalPelajaran::create([
            'group_id' => (string) \Illuminate\Support\Str::uuid(),
            'id_tahun_ajaran' => $this->tahunAjaranId,
            'hari' => 'Senin',
            'id_guru' => $this->guruValid->id,
            'id_mapel' => $this->mapel->id,
            'id_kelas' => $this->kelas->id,
            'id_ruangan' => $this->ruangan->id,
            'id_jam' => $jam->id,
            'is_testing_data' => false,
        ]);

        $this->artisan('guru:notif-jadwal', ['--type' => 'mulai', '--dry-run' => true])
            ->expectsOutputToContain('Pengingat KBM — 5 Menit Lagi!')
            ->expectsOutputToContain('Matematika')
            ->assertExitCode(0);
    }

    public function test_pengingat_jurnal_sent_if_journal_not_yet_filled()
    {
        // 5 minutes before 07:45 is 07:40
        Carbon::setTestNow(Carbon::parse('2026-09-28 07:40:00')); // Monday

        $jam = JamPelajaran::create([
            'hari' => 'Senin',
            'kategori_hari' => 'Senin-Kamis',
            'jam_ke' => 1,
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '07:45:00',
            'jenis' => 'kbm',
            'tahun_ajaran_id' => $this->tahunAjaranId,
            'is_testing_data' => false,
        ]);

        $jadwal = JadwalPelajaran::create([
            'group_id' => (string) \Illuminate\Support\Str::uuid(),
            'id_tahun_ajaran' => $this->tahunAjaranId,
            'hari' => 'Senin',
            'id_guru' => $this->guruValid->id,
            'id_mapel' => $this->mapel->id,
            'id_kelas' => $this->kelas->id,
            'id_ruangan' => $this->ruangan->id,
            'id_jam' => $jam->id,
            'is_testing_data' => false,
        ]);

        // Jurnal belum diisi
        $this->artisan('guru:notif-jadwal', ['--type' => 'selesai', '--dry-run' => true])
            ->expectsOutputToContain('Pengingat Isi Jurnal KBM')
            ->expectsOutputToContain('Jurnal KBM belum terisi!')
            ->assertExitCode(0);
    }

    public function test_pengingat_jurnal_skipped_if_journal_already_filled()
    {
        Carbon::setTestNow(Carbon::parse('2026-09-28 07:40:00')); // Monday

        $jam = JamPelajaran::create([
            'hari' => 'Senin',
            'kategori_hari' => 'Senin-Kamis',
            'jam_ke' => 1,
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '07:45:00',
            'jenis' => 'kbm',
            'tahun_ajaran_id' => $this->tahunAjaranId,
            'is_testing_data' => false,
        ]);

        $jadwal = JadwalPelajaran::create([
            'group_id' => (string) \Illuminate\Support\Str::uuid(),
            'id_tahun_ajaran' => $this->tahunAjaranId,
            'hari' => 'Senin',
            'id_guru' => $this->guruValid->id,
            'id_mapel' => $this->mapel->id,
            'id_kelas' => $this->kelas->id,
            'id_ruangan' => $this->ruangan->id,
            'id_jam' => $jam->id,
            'is_testing_data' => false,
        ]);

        // Jurnal SUDAH diisi hari ini
        Jurnal::create([
            'id_jadwal' => $jadwal->id,
            'id_guru' => $this->guruValid->id,
            'tanggal' => '2026-09-28',
            'materi' => 'Matematika Dasar',
            'status_kehadiran' => 'Hadir',
            'is_testing_data' => false,
        ]);

        $this->artisan('guru:notif-jadwal', ['--type' => 'selesai', '--dry-run' => true])
            ->expectsOutputToContain('jurnal sudah diisi, skip')
            ->assertExitCode(0);
    }

    public function test_dynamic_lesson_time_shift_triggers_notifications_at_updated_db_times()
    {
        // Admin updates JamPelajaran in DB: Jam 1 shifted forward (06:30 - 07:15)
        $jamShifted = JamPelajaran::create([
            'hari' => 'Senin',
            'kategori_hari' => 'Senin-Kamis',
            'jam_ke' => 1,
            'jam_mulai' => '06:30:00',
            'jam_selesai' => '07:15:00',
            'jenis' => 'kbm',
            'tahun_ajaran_id' => $this->tahunAjaranId,
            'is_testing_data' => false,
        ]);

        JadwalPelajaran::create([
            'group_id' => (string) \Illuminate\Support\Str::uuid(),
            'id_tahun_ajaran' => $this->tahunAjaranId,
            'hari' => 'Senin',
            'id_guru' => $this->guruValid->id,
            'id_mapel' => $this->mapel->id,
            'id_kelas' => $this->kelas->id,
            'id_ruangan' => $this->ruangan->id,
            'id_jam' => $jamShifted->id,
            'is_testing_data' => false,
        ]);

        // 1. At 06:25 (H-5 minutes before NEW start time 06:30)
        Carbon::setTestNow(Carbon::parse('2026-09-28 06:25:00'));

        $this->artisan('guru:notif-jadwal', ['--type' => 'mulai', '--dry-run' => true])
            ->expectsOutputToContain('Pengingat KBM — 5 Menit Lagi!')
            ->expectsOutputToContain('06.30–07.15 WIB')
            ->assertExitCode(0);

        // 2. At 07:10 (H-5 minutes before NEW end time 07:15)
        Carbon::setTestNow(Carbon::parse('2026-09-28 07:10:00'));

        $this->artisan('guru:notif-jadwal', ['--type' => 'selesai', '--dry-run' => true])
            ->expectsOutputToContain('Pengingat Isi Jurnal KBM')
            ->expectsOutputToContain('06.30–07.15 WIB')
            ->assertExitCode(0);
    }
}
