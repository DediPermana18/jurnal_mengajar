<?php

namespace Tests\Feature;

use App\Models\AbsensiJurnal;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Scopes\TestingDataScope;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WaliKelasRiwayatJurnalDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 8, 10));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function makeWaliKelas(): User
    {
        return User::create([
            'nama' => 'Wali Kelas Detail',
            'username' => 'wkdetail_'.Str::random(6),
            'password' => bcrypt('password'),
            'role' => 'guru',
            'sub_role' => 'wali_kelas',
            'is_active' => true,
        ]);
    }

    protected function makeGuruPengajar(): User
    {
        return User::create([
            'nama' => 'Guru Mapel',
            'username' => 'gmdetail_'.Str::random(6),
            'password' => bcrypt('password'),
            'role' => 'guru',
            'is_active' => true,
        ]);
    }

    protected function makeKelas(User $wali, string $nama = 'X IPA 1'): Kelas
    {
        return Kelas::create([
            'nama_kelas' => $nama,
            'tingkat' => 'X',
            'id_wali_kelas' => $wali->id,
        ]);
    }

    protected function makeSiswa(Kelas $kelas, string $nama, string $nisn, string $nis = '23100'): Siswa
    {
        return Siswa::create([
            'nisn' => $nisn,
            'nis' => $nis,
            'nama' => $nama,
            'jenis_kelamin' => 'L',
            'id_kelas' => $kelas->id,
        ]);
    }

    /**
     * @return array{0: JadwalPelajaran, 1: MataPelajaran}
     */
    protected function makeJadwal(User $guru, Kelas $kelas, int $jamKe = 2): array
    {
        $tahun = TahunAjaran::create([
            'tahun_ajaran' => '2025/2026',
            'semester' => 'Ganjil',
            'is_active' => true,
        ]);

        $suffix = Str::random(3);
        $mapel = MataPelajaran::create(['nama_mapel' => 'Matematika', 'kode_mapel' => 'MTK'.$suffix]);
        $jam = JamPelajaran::create([
            'kategori_hari' => 'Senin-Kamis',
            'jam_ke' => $jamKe,
            'jam_mulai' => sprintf('%02d:%02d', 6 + $jamKe, 40),
            'jam_selesai' => sprintf('%02d:%02d', 7 + $jamKe, 20),
            'jenis' => 'kbm',
        ]);

        $jadwal = JadwalPelajaran::create([
            'group_id' => Str::uuid(),
            'hari' => 'Senin',
            'id_jam' => $jam->id,
            'id_kelas' => $kelas->id,
            'id_mapel' => $mapel->id,
            'id_guru' => $guru->id,
            'id_tahun_ajaran' => $tahun->id,
        ]);

        return [$jadwal, $mapel];
    }

    /**
     * Buat jurnal beserta absensi per siswa.
     *
     * @param  array<int, array{0: string, 1?: string|null}>  $presensi
     */
    protected function makeJurnal(JadwalPelajaran $jadwal, User $guru, string $materi, array $siswas, array $presensi): Jurnal
    {
        $jurnal = Jurnal::create([
            'id_jadwal' => $jadwal->id,
            'id_guru' => $guru->id,
            'status_kehadiran' => 'Hadir',
            'tanggal' => '2026-08-10',
            'materi' => $materi,
            'catatan_kejadian' => 'Siswa mengikuti KBM dengan kondusif dan aktif.',
            'waktu_isi' => Carbon::parse('2026-08-10 08:30:00'),
        ]);

        foreach ($siswas as $i => $siswa) {
            [$status, $keterangan] = $presensi[$i] ?? ['Hadir', null];
            AbsensiJurnal::create([
                'id_jurnal' => $jurnal->id,
                'id_siswa' => $siswa->id,
                'status' => $status,
                'keterangan' => $keterangan,
            ]);
        }

        return $jurnal;
    }

    public function test_detail_jurnal_menampilkan_rincian_lengkap(): void
    {
        $wali = $this->makeWaliKelas();
        $guru = $this->makeGuruPengajar();
        $kelas = $this->makeKelas($wali);

        $siswaHadir = $this->makeSiswa($kelas, 'Andi Pratama', '0000000101', '23101');
        $siswaSakit = $this->makeSiswa($kelas, 'Budi Santoso', '0000000202', '23102');
        $siswaIzin = $this->makeSiswa($kelas, 'Cita Kirana', '0000000303', '23103');
        $siswaAlpa = $this->makeSiswa($kelas, 'Dina Marlina', '0000000404', '23104');

        [$jadwal] = $this->makeJadwal($guru, $kelas, 2);
        $jurnal = $this->makeJurnal(
            $jadwal,
            $guru,
            'Matriks dan Transformasi',
            [$siswaHadir, $siswaSakit, $siswaIzin, $siswaAlpa],
            [
                ['Hadir', null],
                ['Sakit', 'Demam berdarah'],
                ['Izin', 'Acara keluarga'],
                ['Alpa', null],
            ]
        );
        $jurnal->update(['foto_kegiatan' => 'foto_jurnal/foto-sesi-1.jpg']);

        $this->actingAs($wali)
            ->get(route('walikelas.riwayat-jurnal.show', $jurnal->id))
            ->assertOk()
            // Badge Read-Only + ringkasan baris
            ->assertSee('Read-Only')
            ->assertSee('10 August 2026')
            ->assertSee('Jam 2')
            // Informasi jurnal utama
            ->assertSee('X IPA 1')
            ->assertSee('Matematika')
            ->assertSee('Guru Mapel')
            ->assertSee('- Tidak Ada Guru Pengganti -')
            // Materi & catatan kejadian
            ->assertSee('Matriks dan Transformasi')
            ->assertSee('Siswa mengikuti KBM dengan kondusif dan aktif.')
            // Rekap presensi siswa
            ->assertSee('Budi Santoso')
            ->assertSee('Sakit (S)')
            ->assertSee('Demam berdarah')
            ->assertSee('Cita Kirana')
            ->assertSee('Izin (I)')
            ->assertSee('Acara keluarga')
            ->assertSee('Dina Marlina')
            ->assertSee('Alpa (A)')
            ->assertSee('Andi Pratama')
            // Foto kegiatan KBM
            ->assertSee('jurnal/foto/foto-sesi-1.jpg', false)
            ->assertSee('Lihat Foto Full')
            // Tombol kembali ke halaman riwayat wali kelas
            ->assertSee('Kembali ke Riwayat Jurnal');
    }

    public function test_detail_jurnal_dari_kelas_lain_ditolak(): void
    {
        $wali = $this->makeWaliKelas();
        $waliLain = $this->makeWaliKelas();
        $guru = $this->makeGuruPengajar();

        $kelasSaya = $this->makeKelas($wali, 'X IPA 1');
        $kelasLain = $this->makeKelas($waliLain, 'XI IPS 3');
        $siswaLain = $this->makeSiswa($kelasLain, 'Eko Wibowo', '0000000505', '23105');

        [$jadwalLain] = $this->makeJadwal($guru, $kelasLain, 3);
        $jurnalLain = $this->makeJurnal($jadwalLain, $guru, 'Materi Kelas Lain', [$siswaLain], [['Sakit', 'Ijin sakit']]);

        // Wali pertama TIDAK berhak melihat jurnal kelas bimbingan wali lain.
        // (kelasSaya dibuat agar terbukti wali punya kelas bimbingan sendiri.)
        $this->actingAs($wali)
            ->get(route('walikelas.riwayat-jurnal.show', $jurnalLain->id))
            ->assertStatus(403);
    }

    public function test_detail_jurnal_user_bukan_wali_kelas_ditolak(): void
    {
        $guruBiasa = User::create([
            'nama' => 'Guru Mapel Biasa',
            'username' => 'gmbiasa_'.Str::random(6),
            'password' => bcrypt('password'),
            'role' => 'guru',
            'is_active' => true,
        ]);

        $wali = $this->makeWaliKelas();
        $guru = $this->makeGuruPengajar();
        $kelas = $this->makeKelas($wali);
        $siswa = $this->makeSiswa($kelas, 'Fajar', '0000000606', '23106');

        [$jadwal] = $this->makeJadwal($guru, $kelas, 1);
        $jurnal = $this->makeJurnal($jadwal, $guru, 'Materi Rahasia', [$siswa], [['Hadir', null]]);

        $this->actingAs($guruBiasa)
            ->get(route('walikelas.riwayat-jurnal.show', $jurnal->id))
            ->assertStatus(403);
    }

    public function test_detail_jurnal_wali_kelas_tanpa_kelas_bimbingan_ditolak(): void
    {
        $waliTanpaKelas = $this->makeWaliKelas();
        $waliPemilik = $this->makeWaliKelas();
        $guru = $this->makeGuruPengajar();

        $kelas = $this->makeKelas($waliPemilik, 'X AK 1');
        $siswa = $this->makeSiswa($kelas, 'Gita', '0000000707', '23107');

        [$jadwal] = $this->makeJadwal($guru, $kelas, 1);
        $jurnal = $this->makeJurnal($jadwal, $guru, 'Materi Kelas Lain', [$siswa], [['Hadir', null]]);

        // Wali ini tidak mengampu kelas mana pun → tidak boleh membuka detail jurnal apa pun.
        $this->actingAs($waliTanpaKelas)
            ->get(route('walikelas.riwayat-jurnal.show', $jurnal->id))
            ->assertStatus(403);
    }

    public function test_detail_multi_jam_menggabungkan_absensi_unik_per_siswa(): void
    {
        $wali = $this->makeWaliKelas();
        $guru = $this->makeGuruPengajar();
        $kelas = $this->makeKelas($wali);

        $siswaA = $this->makeSiswa($kelas, 'Andi Pratama', '0000000808', '23108');
        $siswaB = $this->makeSiswa($kelas, 'Budi Santoso', '0000000909', '23109');

        $tahun = TahunAjaran::create([
            'tahun_ajaran' => '2025/2026',
            'semester' => 'Ganjil',
            'is_active' => true,
        ]);
        $mapel = MataPelajaran::create(['nama_mapel' => 'Matematika', 'kode_mapel' => 'MTK'.Str::random(3)]);
        $groupId = (string) Str::uuid();

        $jadwals = [];
        foreach ([1, 2] as $jamKe) {
            $jam = JamPelajaran::create([
                'kategori_hari' => 'Senin-Kamis',
                'jam_ke' => $jamKe,
                'jam_mulai' => sprintf('%02d:%02d', 6 + $jamKe, 40),
                'jam_selesai' => sprintf('%02d:%02d', 7 + $jamKe, 20),
                'jenis' => 'kbm',
            ]);

            $jadwals[] = JadwalPelajaran::create([
                'group_id' => $groupId,
                'hari' => 'Senin',
                'id_jam' => $jam->id,
                'id_kelas' => $kelas->id,
                'id_mapel' => $mapel->id,
                'id_guru' => $guru->id,
                'id_tahun_ajaran' => $tahun->id,
            ]);
        }

        // Satu sesi 2 jam → 2 record jurnal.
        // Jam 1: Andi & Budi hadir. Jam 2: Andi sakit, Budi hadir.
        $rujukan = null;
        foreach ($jadwals as $index => $jadwal) {
            $jurnal = $this->makeJurnal(
                $jadwal,
                $guru,
                'Matriks',
                [$siswaA, $siswaB],
                $index === 0
                    ? [['Hadir', null], ['Hadir', null]]
                    : [['Sakit', 'Sakit perut'], ['Hadir', null]]
            );
            $rujukan = $jurnal;
        }

        // "Semua JP": absensi antar-jam digabung per siswa unik (prefer non-Hadir).
        $content = $this->actingAs($wali)
            ->get(route('walikelas.riwayat-jurnal.show', $rujukan->id))
            ->assertOk()
            // Tab multi-jam terbentuk dari grouping sesi
            ->assertSee('Semua JP')
            ->assertSee('Jam ke-1')
            ->assertSee('Jam ke-2')
            ->assertSee('Sakit perut')
            ->assertSee('Budi Santoso')
            ->getContent();

        // Absensi digabung per siswa → hanya Andi yang sakit (1 badge Sakit),
        // Budi hadir; tidak ada duplikasi walau 2 record jurnal di DB.
        $this->assertSame(1, substr_count($content, 'Sakit (S)'), 'Badge Sakit (S) harus muncul tepat 1x pada mode Semua JP (absensi digabung).');

        // Tab Jam ke-2 → hanya jurnal jam 2 (Andi sakit).
        $this->get(route('walikelas.riwayat-jurnal.show', $rujukan->id).'?jp=2')
            ->assertOk()
            ->assertSee('Sakit (S)')
            ->assertSee('Sakit perut');

        // Tab Jam ke-1 → hanya jurnal jam 1 (semua hadir).
        $this->get(route('walikelas.riwayat-jurnal.show', $rujukan->id).'?jp=1')
            ->assertOk()
            ->assertDontSee('Sakit (S)')
            ->assertDontSee('Sakit perut');
    }

    // ─── Mode QA / Impersonasi Petugas IT ─────────────────────────────────────

    public function test_petugas_it_tanpa_target_detail_jurnal_ditolak(): void
    {
        $this->seed(DatabaseSeeder::class);

        $it = User::where('email', 'it@school.id')->firstOrFail();
        $this->actingAs($it);

        $this->post(route('it.switch-view'), ['role' => 'wali_kelas'])
            ->assertRedirect(route('walikelas.dashboard'));

        // Reset target → tanpa target, detail jurnal wajib ditolak (403).
        $this->withHeaders(['Referer' => route('walikelas.riwayat-jurnal')])
            ->post(route('it.impersonate-target'), ['impersonate_target_id' => '']);

        // Jurnal sandbox (is_testing_data=true) agar tetap terlihat oleh scope IT.
        $jadwal = JadwalPelajaran::withoutGlobalScope(TestingDataScope::class)->first();
        $this->assertNotNull($jadwal);

        $jurnal = Jurnal::create([
            'id_jadwal' => $jadwal->id,
            'id_guru' => $jadwal->id_guru,
            'status_kehadiran' => 'Hadir',
            'tanggal' => '2026-08-10',
            'materi' => 'Jurnal QA Tanpa Target',
            'is_testing_data' => true,
        ]);

        $this->get(route('walikelas.riwayat-jurnal.show', $jurnal->id))
            ->assertStatus(403);
    }

    public function test_petugas_it_dengan_target_melihat_detail_jurnal_kelas_bimbingan(): void
    {
        $this->seed(DatabaseSeeder::class);

        $it = User::where('email', 'it@school.id')->firstOrFail();
        $this->actingAs($it);

        $this->post(route('it.switch-view'), ['role' => 'wali_kelas'])
            ->assertRedirect(route('walikelas.dashboard'));

        $targetId = (int) session('impersonate_target_id');
        $this->assertGreaterThan(0, $targetId, 'Target wali kelas default harus terisi.');

        // Bangun data TESTING milik kelas bimbingan target impersonasi IT.
        $kelas = Kelas::create([
            'nama_kelas' => 'X RPL QA',
            'tingkat' => 'X',
            'id_wali_kelas' => $targetId,
            'is_testing_data' => true,
        ]);
        $guru = User::create([
            'nama' => 'Guru Pengajar QA',
            'username' => 'gpqa_'.Str::random(6),
            'password' => bcrypt('password'),
            'role' => 'guru',
            'is_active' => true,
            'is_testing_data' => true,
        ]);
        $siswa = $this->makeSiswa($kelas, 'Siswa QA', '0000001010', '23110');
        $siswa->update(['is_testing_data' => true]);

        [$jadwal] = $this->makeJadwal($guru, $kelas, 3);
        $jadwal->update(['is_testing_data' => true]);

        $jurnal = $this->makeJurnal($jadwal, $guru, 'Materi QA IT', [$siswa], [['Sakit', 'Tes skenario QA']]);
        $jurnal->update(['is_testing_data' => true]);

        $this->get(route('walikelas.riwayat-jurnal.show', $jurnal->id))
            ->assertOk()
            ->assertSee('X RPL QA')
            ->assertSee('Materi QA IT')
            ->assertSee('Guru Pengajar QA')
            ->assertSee('Siswa QA')
            ->assertSee('Sakit (S)')
            ->assertSee('Kembali ke Riwayat Jurnal');
    }
}