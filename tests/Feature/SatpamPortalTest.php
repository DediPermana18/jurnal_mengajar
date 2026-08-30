<?php

namespace Tests\Feature;

use App\Models\AbsensiJurnal;
use App\Models\CatatanTerlambat;
use App\Models\DispensasiSiswa;
use App\Models\JadwalPelajaran;
use App\Models\JadwalPiket;
use App\Models\JamPelajaran;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\PenerimaTerlambat;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SatpamPortalTest extends TestCase
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

    protected function makeUser(string $role, ?string $subRole = null): User
    {
        return User::create([
            'nama'      => 'User ' . Str::random(5),
            'username'  => 'user_' . Str::random(8),
            'password'  => bcrypt('password'),
            'role'      => $role,
            'sub_role'  => $subRole,
            'is_active' => true,
        ]);
    }

    protected function makeSatpam(): User
    {
        return $this->makeUser('admin', 'satpam');
    }

    protected function makeSiswaBudi(?User $waliKelas = null): array
    {
        $kelas = Kelas::create([
            'nama_kelas'   => 'X IPA 1',
            'tingkat'      => 'X',
            'id_wali_kelas' => $waliKelas?->id,
        ]);

        $siswa = Siswa::create([
            'nisn'          => '0000000001',
            'nis'           => '23101',
            'nama'          => 'Budi Santoso',
            'jenis_kelamin' => 'L',
            'id_kelas'      => $kelas->id,
        ]);

        return [$kelas, $siswa];
    }

    protected function jadwalkanPiket(User $guru): void
    {
        JadwalPiket::create(['user_id' => $guru->id, 'hari' => 'Senin']);
    }

    protected function buatKbmHariIni(Kelas $kelas, User $guru): array
    {
        $tahun = TahunAjaran::create([
            'tahun_ajaran' => '2025/2026',
            'semester'     => 'Ganjil',
            'status_aktif' => true,
        ]);

        $mapel = MataPelajaran::create(['nama_mapel' => 'Matematika', 'kode_mapel' => 'MTK']);

        $jam = JamPelajaran::create([
            'kategori_hari' => 'Senin-Kamis',
            'jam_ke'        => 2,
            'jam_mulai'     => '07:40',
            'jam_selesai'   => '08:20',
            'jenis'         => 'kbm',
        ]);

        $jadwal = JadwalPelajaran::create([
            'group_id'       => Str::uuid(),
            'hari'           => 'Senin',
            'id_jam'         => $jam->id,
            'id_kelas'       => $kelas->id,
            'id_mapel'       => $mapel->id,
            'id_guru'        => $guru->id,
            'id_tahun_ajaran'=> $tahun->id,
        ]);

        Jurnal::create([
            'id_jadwal' => $jadwal->id,
            'tanggal'   => '2026-08-10',
            'materi'    => 'Operasi hitung',
        ]);

        return [$jadwal, $guru];
    }

    protected function makeDispen(User $satpam, Siswa $siswa, array $extra = []): DispensasiSiswa
    {
        return DispensasiSiswa::create(array_merge([
            'id_siswa'       => $siswa->id,
            'id_guru_piket'  => $satpam->id,
            'tanggal'        => now()->toDateString(),
            'jenis'          => DispensasiSiswa::JENIS_KELUAR,
            'jam_ke'         => '5,6',
            'alasan'         => 'Ada keperluan keluarga',
            'status'         => DispensasiSiswa::STATUS_DISETUJUI,
            'approval_token' => Str::random(16),
        ], $extra));
    }

    public function test_satpam_akses_dashboard_tanpa_jadwal_piket(): void
    {
        $satpam = $this->makeSatpam();

        $this->actingAs($satpam)
            ->get(route('satpam.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard Satpam')
            ->assertSee('Siswa Terlambat Hari Ini')
            ->assertSee('Izin Keluar Gerbang Hari Ini')
            ->assertSee('Input Siswa Terlambat')
            ->assertSee('Input / Cek Dispensasi');
    }

    public function test_guru_tanpa_jadwal_piket_ditolak_akses_portal_satpam(): void
    {
        $guru = $this->makeUser('guru', 'guru_mapel');

        $this->actingAs($guru)
            ->get(route('satpam.dashboard'))
            ->assertForbidden();
    }

    public function test_catat_siswa_terlambat_diteruskan_ke_guru_piket_dan_wali_kelas(): void
    {
        $satpam    = $this->makeSatpam();
        $wali      = $this->makeUser('guru', 'wali_kelas');
        $piketA    = $this->makeUser('guru', 'guru_mapel');
        $piketB    = $this->makeUser('guru', 'guru_mapel');
        $guruBukanPiket = $this->makeUser('guru', 'guru_mapel');
        $this->jadwalkanPiket($piketA);
        $this->jadwalkanPiket($piketB);

        [$kelas] = $this->makeSiswaBudi($wali);
        $kelas->refresh();

        $this->actingAs($satpam)
            ->get(route('satpam.dashboard'))
            ->assertOk();

        $this->actingAs($satpam)
            ->post(route('satpam.terlambat.store'), [
                'id_siswa'   => $kelas->siswa->first()->id,
                'tanggal'    => '2026-08-10',
                'jam_masuk'  => '07:45',
                'keterangan' => 'Bangun kesiangan',
            ])
            ->assertRedirect(route('satpam.dashboard'))
            ->assertSessionHas('success');

        $catatan = CatatanTerlambat::first();

        $this->assertEquals('07:45', $catatan->jam_masuk->format('H:i'));

        $penerimaGuru = PenerimaTerlambat::where('catatan_terlambat_id', $catatan->id)
            ->where('peran', PenerimaTerlambat::PERAN_GURU_PIKET)
            ->pluck('user_id')
            ->all();

        $this->assertCount(2, $penerimaGuru);
        $this->assertContains($piketA->id, $penerimaGuru);
        $this->assertContains($piketB->id, $penerimaGuru);
        $this->assertNotContains($guruBukanPiket->id, $penerimaGuru);

        $this->assertTrue(PenerimaTerlambat::where('catatan_terlambat_id', $catatan->id)
            ->where('peran', PenerimaTerlambat::PERAN_WALI_KELAS)
            ->where('user_id', $wali->id)
            ->exists());

        $this->actingAs($satpam)
            ->get(route('satpam.dashboard'))
            ->assertOk()
            ->assertSee('Budi Santoso')
            ->assertSee('07:45')
            ->assertSee('Bangun kesiangan')
            ->assertSee('2 Guru Piket')
            ->assertSee('Wali Kelas');
    }

    public function test_catat_dispensasi_menandai_absensi_dispen_di_jurnal_guru_mapel(): void
    {
        $satpam = $this->makeSatpam();
        [$kelas, $siswa] = $this->makeSiswaBudi();
        [$jadwal, $guru] = $this->buatKbmHariIni($kelas, $this->makeUser('guru', 'guru_mapel'));

        $this->actingAs($satpam)
            ->post(route('satpam.dispensasi.store'), [
                'tanggal'   => '2026-08-10',
                'id_siswa'  => $siswa->id,
                'jenis'     => DispensasiSiswa::JENIS_KELUAR,
                'id_jadwal' => $jadwal->id,
                'alasan'    => 'Dibawa orang tua ke dokter',
            ])
            ->assertRedirect(route('satpam.dashboard', ['tab' => 'dispensasi']))
            ->assertSessionHas('success');

        $dispen = DispensasiSiswa::first();

        $this->assertEquals($siswa->id, $dispen->id_siswa);
        $this->assertEquals(DispensasiSiswa::STATUS_DISETUJUI, $dispen->status);
        $this->assertEquals('keluar_gerbang', $dispen->jenis);
        $this->assertEquals('2', $dispen->jam_ke);
        $this->assertEquals($jadwal->id, $dispen->id_jadwal);
        $this->assertEquals($guru->id, $dispen->id_guru);

        $absensi = AbsensiJurnal::where('id_siswa', $siswa->id)->first();

        $this->assertNotNull($absensi);
        $this->assertEquals('Dispen', $absensi->status);
        $this->assertStringStartsWith('Dispensasi:', $absensi->keterangan);

        $this->actingAs($satpam)
            ->get(route('satpam.dashboard', ['tab' => 'dispensasi']))
            ->assertOk()
            ->assertSee('Matematika')
            ->assertSee($guru->nama)
            ->assertSee('Keluar Gerbang Sekolah');
    }

    public function test_dispensasi_tanpa_jadwal_tetap_bisa_dicatat(): void
    {
        $satpam = $this->makeSatpam();
        [, $siswa] = $this->makeSiswaBudi();

        $this->actingAs($satpam)
            ->post(route('satpam.dispensasi.store'), [
                'tanggal'  => '2026-08-10',
                'id_siswa' => $siswa->id,
                'jenis'    => DispensasiSiswa::JENIS_SAKIT,
                'alasan'   => 'Sakit, pulang lebih awal',
            ])
            ->assertRedirect(route('satpam.dashboard', ['tab' => 'dispensasi']))
            ->assertSessionHas('success');

        $dispen = DispensasiSiswa::first();

        $this->assertEquals(DispensasiSiswa::STATUS_DISETUJUI, $dispen->status);
        $this->assertNull($dispen->id_jadwal);
        $this->assertNull($dispen->jam_ke);
    }

    public function test_verifikasi_kode_unik_dan_izinkan_keluar_gerbang(): void
    {
        $satpam = $this->makeSatpam();
        [, $siswa] = $this->makeSiswaBudi();
        $dispen = $this->makeDispen($satpam, $siswa);

        $this->actingAs($satpam)
            ->get(route('satpam.verifikasi', ['q' => $dispen->approval_token]))
            ->assertOk()
            ->assertSee('Budi Santoso')
            ->assertSee('Surat izin valid untuk keluar hari ini')
            ->assertSee('Izinkan Keluar Gerbang');

        $this->actingAs($satpam)
            ->post(route('satpam.dispen.keluar', $dispen))
            ->assertRedirect(route('satpam.verifikasi', ['q' => $dispen->approval_token]))
            ->assertSessionHas('success');

        $this->assertNotNull($dispen->fresh()->keluar_gerbang_at);
        $this->assertEquals($satpam->id, $dispen->fresh()->keluar_gerbang_by);

        $this->actingAs($satpam)
            ->get(route('satpam.dashboard'))
            ->assertOk()
            ->assertSee('Sudah Keluar')
            ->assertSee('Izin Keluar Gerbang Hari Ini');
    }

    public function test_verifikasi_dua_kali_memberi_info_notif(): void
    {
        $satpam = $this->makeSatpam();
        [, $siswa] = $this->makeSiswaBudi();
        $dispen = $this->makeDispen($satpam, $siswa, [
            'keluar_gerbang_at' => now(),
            'keluar_gerbang_by' => $satpam->id,
        ]);

        $this->actingAs($satpam)
            ->post(route('satpam.dispen.keluar', $dispen))
            ->assertRedirect(route('satpam.verifikasi', ['q' => $dispen->approval_token]))
            ->assertSessionHas('info');
    }

    public function test_verifikasi_siswa_tanpa_dispen_dianggap_tidak_sah(): void
    {
        $satpam = $this->makeSatpam();
        [, $siswa] = $this->makeSiswaBudi();

        $this->actingAs($satpam)
            ->get(route('satpam.verifikasi', ['q' => '23101']))
            ->assertOk()
            ->assertSee('tidak ada dispensasi disetujui')
            ->assertDontSee('Izinkan Keluar Gerbang');
    }

    public function test_verifikasi_tidak_ditemukan(): void
    {
        $satpam = $this->makeSatpam();

        $this->actingAs($satpam)
            ->get(route('satpam.verifikasi', ['q' => 'tidak-ada']))
            ->assertOk()
            ->assertSee('Tidak ditemukan');
    }

    public function test_satpam_route_root_redirect_ke_dashboard(): void
    {
        $satpam = $this->makeSatpam();

        $this->actingAs($satpam)
            ->get('/satpam')
            ->assertRedirect(route('satpam.dashboard'));
    }
}