<?php

namespace Tests\Feature;

use App\Models\AbsensiJurnal;
use App\Models\CatatanSiswaBermasalah;
use App\Models\CatatanTerlambat;
use App\Models\DispensasiSiswa;
use App\Models\JadwalPelajaran;
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

class WaliKelasBermasalahTest extends TestCase
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
            'username'  => 'walikelas_' . Str::random(6),
            'password'  => bcrypt('password'),
            'role'      => 'guru',
            'sub_role'  => 'wali_kelas',
            'is_active' => true,
        ]);
    }

    protected function makeSiswa(User $wali): array
    {
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

        return [$kelas, $siswa];
    }

    public function test_wali_kelas_melihat_rekap_keterlambatan_siswa_real(): void
    {
        $wali  = $this->makeWaliKelas();
        [, $siswa] = $this->makeSiswa($wali);

        $catatan = CatatanTerlambat::create([
            'id_siswa'   => $siswa->id,
            'tanggal'    => '2026-08-10',
            'jam_masuk'  => '07:45',
            'keterangan' => 'Bangun kesiangan',
            'id_satpam'  => $wali->id,
        ]);

        PenerimaTerlambat::create([
            'catatan_terlambat_id' => $catatan->id,
            'user_id'              => $wali->id,
            'peran'                => PenerimaTerlambat::PERAN_WALI_KELAS,
        ]);

        $this->actingAs($wali)
            ->get(route('walikelas.siswa-bermasalah'))
            ->assertOk()
            ->assertSee('Andi Pratama')
            ->assertSee('Terlambat 1x')
            ->assertSee('07:45')
            ->assertSee('Bangun kesiangan')
            ->assertSee('Tindak Lanjut');
    }

    public function test_wali_kelas_melihat_badge_dispen_dan_alpha(): void
    {
        $wali  = $this->makeWaliKelas();
        [$kelas, $siswa] = $this->makeSiswa($wali);

        DispensasiSiswa::create([
            'id_siswa'       => $siswa->id,
            'id_guru_piket'  => $wali->id,
            'tanggal'        => '2026-08-10',
            'jenis'          => DispensasiSiswa::JENIS_KELUAR,
            'jam_ke'         => '3',
            'alasan'         => 'Ke dokter',
            'status'         => DispensasiSiswa::STATUS_DISETUJUI,
            'approval_token' => Str::random(16),
        ]);

        $tahun = TahunAjaran::create([
            'tahun_ajaran' => '2025/2026',
            'semester'     => 'Ganjil',
            'status_aktif' => true,
        ]);

        $mapel = MataPelajaran::create(['nama_mapel' => 'Matematika', 'kode_mapel' => 'MTK']);
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
            'id_guru'         => $wali->id,
            'id_tahun_ajaran' => $tahun->id,
        ]);

        $jurnal = Jurnal::create([
            'id_jadwal' => $jadwal->id,
            'tanggal'   => '2026-08-10',
            'materi'    => 'x',
        ]);

        AbsensiJurnal::create([
            'id_jurnal' => $jurnal->id,
            'id_siswa'  => $siswa->id,
            'status'    => 'Alpa',
        ]);

        $this->actingAs($wali)
            ->get(route('walikelas.siswa-bermasalah'))
            ->assertOk()
            ->assertSee('Andi Pratama')
            ->assertSee('Alpha 1x')
            ->assertSee('Dispen 1x');
    }

    public function test_wali_kelas_simpan_tindak_lanjut_panggil_ortu(): void
    {
        $wali  = $this->makeWaliKelas();
        [$kelas, $siswa] = $this->makeSiswa($wali);

        $this->actingAs($wali)
            ->post(route('walikelas.siswa-bermasalah.store'), [
                'id_siswa'       => $siswa->id,
                'jenis_tindakan' => 'panggil_ortu',
                'status'         => 'dipanggil',
                'catatan'        => 'Ortu sudah dihubungi via WA',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $catatan = CatatanSiswaBermasalah::where('id_siswa', $siswa->id)
            ->where('id_wali_kelas', $wali->id)
            ->first();

        $this->assertNotNull($catatan);
        $this->assertEquals('panggil_ortu', $catatan->jenis_tindakan);
        $this->assertEquals('dipanggil', $catatan->status);
        $this->assertEquals('Ortu sudah dihubungi via WA', $catatan->catatan);

        $this->actingAs($wali)
            ->get(route('walikelas.siswa-bermasalah'))
            ->assertOk()
            ->assertSee('Panggil Ortu')
            ->assertSee('Ortu sudah dihubungi via WA');
    }

    public function test_wali_kelas_tidak_bisa_tindak_lanjut_siswa_luar_kelasnya(): void
    {
        $wali   = $this->makeWaliKelas();
        $wali2  = $this->makeWaliKelas();
        $kelas  = Kelas::create(['nama_kelas' => 'XI IPA 2', 'tingkat' => 'XI', 'id_wali_kelas' => $wali2->id]);
        $siswa = Siswa::create([
            'nisn'          => '0000000202',
            'nis'           => '23203',
            'nama'          => 'Siswa Kelas Lain',
            'jenis_kelamin' => 'P',
            'id_kelas'      => $kelas->id,
        ]);

        $this->actingAs($wali)
            ->post(route('walikelas.siswa-bermasalah.store'), [
                'id_siswa'       => $siswa->id,
                'jenis_tindakan' => 'catatan',
                'status'         => 'belum',
            ])
            ->assertForbidden();
    }
}