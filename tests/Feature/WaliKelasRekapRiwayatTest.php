<?php

namespace Tests\Feature;

use App\Models\AbsensiJurnal;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WaliKelasRekapRiwayatTest extends TestCase
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
            'nama'      => 'Wali Kelas Test',
            'username'  => 'wkrekap_' . Str::random(6),
            'password'  => bcrypt('password'),
            'role'      => 'guru',
            'sub_role'  => 'wali_kelas',
            'is_active' => true,
        ]);
    }

    protected function makeJadwal(User $wali, User $guru, Kelas $kelas, $label = 'Matematika'): array
    {
        $tahun = TahunAjaran::create([
            'tahun_ajaran' => '2025/2026',
            'semester'     => 'Ganjil',
            'status_aktif' => true,
        ]);

        $suffix = Str::random(3);
        $mapel = MataPelajaran::create(['nama_mapel' => $label, 'kode_mapel' => strtoupper(Str::substr($label, 0, 3)) . $suffix]);
        $jam   = JamPelajaran::create([
            'kategori_hari' => 'Senin-Kamis',
            'jam_ke'        => 2,
            'jam_mulai'     => '07:40',
            'jam_selesai'   => '08:20',
            'jenis'         => 'kbm',
        ]);

        $jadwal = JadwalPelajaran::create([
            'group_id'        => Str::uuid(),
            'hari'            => 'Senin',
            'id_jam'          => $jam->id,
            'id_kelas'        => $kelas->id,
            'id_mapel'        => $mapel->id,
            'id_guru'         => $guru->id,
            'id_tahun_ajaran' => $tahun->id,
        ]);

        return [$jadwal, $mapel];
    }

    public function test_rekap_absen_menampilkan_akumulasi_presensi_siswa(): void
    {
        $wali = $this->makeWaliKelas();

        $kelas = Kelas::create([
            'nama_kelas'    => 'X IPA 1',
            'tingkat'       => 'X',
            'id_wali_kelas' => $wali->id,
        ]);

        $siswa = Siswa::create([
            'nisn'          => '0000000101',
            'nis'           => '23102',
            'nama'          => 'Andi Pratama',
            'jenis_kelamin' => 'L',
            'id_kelas'      => $kelas->id,
        ]);

        $guru = User::create([
            'nama'      => 'Guru Mapel',
            'username'  => 'gurumapel' . Str::random(4),
            'password'  => bcrypt('password'),
            'role'      => 'guru',
            'is_active' => true,
        ]);

        [$jadwal] = $this->makeJadwal($wali, $guru, $kelas);

        $jurnal = Jurnal::create([
            'id_jadwal'    => $jadwal->id,
            'tanggal'      => '2026-08-10',
            'materi'       => 'Matriks',
            'id_guru'      => $guru->id,
            'status_kehadiran' => 'Hadir',
        ]);

        AbsensiJurnal::create(['id_jurnal' => $jurnal->id, 'id_siswa' => $siswa->id, 'status' => 'Hadir']);
        AbsensiJurnal::create(['id_jurnal' => $jurnal->id, 'id_siswa' => $this->siswaLain($kelas), 'status' => 'Sakit']);

        $this->actingAs($wali)
            ->get(route('walikelas.rekap-absen'))
            ->assertOk()
            ->assertSee('Andi Pratama')
            ->assertSee('X IPA 1');
    }

    protected function siswaLain(Kelas $kelas): int
    {
        $s = Siswa::create([
            'nisn'          => '0000000202',
            'nis'           => '23103',
            'nama'          => 'Budi Santoso',
            'jenis_kelamin' => 'L',
            'id_kelas'      => $kelas->id,
        ]);
        return $s->id;
    }

    public function test_riwayat_jurnal_menampilkan_jurnal_kelas_bimbingan_saja(): void
    {
        $wali = $this->makeWaliKelas();
        $guru = User::create([
            'nama'      => 'Guru Mapel',
            'username'  => 'gm' . Str::random(4),
            'password'  => bcrypt('password'),
            'role'      => 'guru',
            'is_active' => true,
        ]);

        $kelasSaya = Kelas::create([
            'nama_kelas'    => 'X IPA 1',
            'tingkat'       => 'X',
            'id_wali_kelas' => $wali->id,
        ]);

        $kelasLain = Kelas::create([
            'nama_kelas'    => 'XI IPA 2',
            'tingkat'       => 'XI',
            'id_wali_kelas' => $this->makeWaliKelas()->id,
        ]);

        $saya = Siswa::create([
            'nisn'          => '0000000303',
            'nis'           => '23104',
            'nama'          => 'Cita',
            'jenis_kelamin' => 'P',
            'id_kelas'      => $kelasSaya->id,
        ]);

        [$jadwalSaya] = $this->makeJadwal($wali, $guru, $kelasSaya);
        [$jadwalLain] = $this->makeJadwal($wali, $guru, $kelasLain);

        $jurnalSaya = Jurnal::create([
            'id_jadwal' => $jadwalSaya->id,
            'tanggal'   => '2026-08-10',
            'materi'    => 'Materi Kelas Saya',
            'id_guru'   => $guru->id,
        ]);

        Jurnal::create([
            'id_jadwal' => $jadwalLain->id,
            'tanggal'   => '2026-08-10',
            'materi'    => 'Materi Kelas Lain',
            'id_guru'   => $guru->id,
        ]);

        AbsensiJurnal::create(['id_jurnal' => $jurnalSaya->id, 'id_siswa' => $saya->id, 'status' => 'Hadir']);
        AbsensiJurnal::create(['id_jurnal' => $jurnalSaya->id, 'id_siswa' => $this->siswaLain($kelasSaya), 'status' => 'Hadir']);

        $this->actingAs($wali)
            ->get(route('walikelas.riwayat-jurnal'))
            ->assertOk()
            ->assertSee('Matematika')
            ->assertSee('Guru Mapel')
            ->assertSee('Materi Kelas Saya')
            ->assertSee('2/2 Siswa')
            ->assertDontSee('Materi Kelas Lain');
    }
}