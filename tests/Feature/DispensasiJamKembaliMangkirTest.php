<?php

namespace Tests\Feature;

use App\Models\AbsensiJurnal;
use App\Models\DispensasiSiswa;
use App\Models\JadwalPelajaran;
use App\Models\JadwalPiket;
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

class DispensasiJamKembaliMangkirTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 8, 31)); // Senin 00:00
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function makeUser(string $role, array $extra = []): User
    {
        return User::create(array_merge([
            'nama' => 'User '.Str::random(5),
            'username' => 'user_'.Str::random(8),
            'password' => bcrypt('password'),
            'role' => $role,
            'sub_role' => 'guru',
            'is_active' => true,
        ], $extra));
    }

    protected function buatKelasSiswa(): array
    {
        $kelas = Kelas::create(['nama_kelas' => 'XII IPA 2', 'tingkat' => 'XII']);

        $siswa = Siswa::create([
            'nama' => 'Rina Marlina',
            'nisn' => '7788990011',
            'nis' => '77889',
            'jenis_kelamin' => 'P',
            'id_kelas' => $kelas->id,
            'status_siswa' => 'Aktif',
        ]);

        return [$kelas, $siswa];
    }

    protected function buatJamPelajaran(): JamPelajaran
    {
        return JamPelajaran::create([
            'kategori_hari' => 'Senin-Kamis',
            'jam_ke' => 3,
            'jam_mulai' => '08:00:00',
            'jam_selesai' => '08:45:00',
            'jenis' => 'kbm',
        ]);
    }

    protected function buatJurnalJp3($kelas): array
    {
        $tahun = TahunAjaran::create([
            'tahun_ajaran' => '2025/2026',
            'semester' => 'Ganjil',
            'is_active' => true,
        ]);

        $mapel = MataPelajaran::create(['nama_mapel' => 'Matematika', 'kode_mapel' => 'MTK']);
        $guru = $this->makeUser('guru');

        $jadwal = JadwalPelajaran::create([
            'group_id' => Str::uuid(),
            'hari' => 'Senin',
            'id_jam' => $this->buatJamPelajaran()->id,
            'id_kelas' => $kelas->id,
            'id_mapel' => $mapel->id,
            'id_guru' => $guru->id,
            'id_tahun_ajaran' => $tahun->id,
        ]);

        $jurnal = Jurnal::create([
            'id_jadwal' => $jadwal->id,
            'tanggal' => '2026-08-31',
            'materi' => 'Operasi hitung',
        ]);

        return [$jadwal, $jurnal];
    }

    protected function buatDispenSiswa($piket, $siswa, array $extra = []): DispensasiSiswa
    {
        return DispensasiSiswa::create(array_merge([
            'id_siswa' => $siswa->id,
            'id_guru_piket' => $piket->id,
            'tanggal' => now()->toDateString(),
            'jam_ke' => '3',
            'jam_keluar_jp' => 3,
            'alasan' => 'Keperluan keluarga',
            'status' => DispensasiSiswa::STATUS_DISETUJUI,
            'ttd_guru' => 'data:image/png;base64,TEST',
            'approval_token' => 'token-'.Str::uuid(),
        ], $extra));
    }

    public function test_store_menyimpan_rencana_jam_kembali_part3(): void
    {
        $piket = $this->makeUser('guru');
        JadwalPiket::create(['hari' => 'Senin', 'user_id' => $piket->id]);
        [$kelas, $siswa] = $this->buatKelasSiswa();

        $this->actingAs($piket)
            ->post(route('piket.dispensasi.store'), [
                'tanggal' => '2026-08-31',
                'id_siswa' => $siswa->id,
                'jam_ke' => ['3', '4'],
                'jam_keluar_jp' => '3',
                'kembali_hari_ini' => '1',
                'jam_kembali_jp' => '6',
                'alasan' => 'Mengikuti lomba akademik',
                'ttd_guru' => 'data:image/png;base64,GURU',
            ])
            ->assertRedirect(route('piket.dispensasi.ttd', DispensasiSiswa::first()->id))
            ->assertSessionHas('success');

        $dispen = DispensasiSiswa::first();
        $this->assertEquals(6, $dispen->jam_kembali_jp);
        $this->assertFalse($dispen->tidak_kembali_hari_ini);

        // Belum keluar gerbang -> belum menunggu konfirmasi kembali.
        $this->assertFalse($dispen->isMenungguKembali());

        // Setelah keluar gerbang dengan rencana kembali -> menunggu konfirmasi kembali.
        $dispen->update(['keluar_gerbang_at' => now(), 'keluar_gerbang_by' => $piket->id]);
        $this->assertTrue($dispen->isMenungguKembali());

        $this->actingAs($piket)
            ->get(route('piket.dispensasi.index'))
            ->assertOk()
            ->assertSee('Rencana kembali JP-6');
    }

    public function test_store_default_tanpa_centang_berarti_tidak_kembali_hari_ini(): void
    {
        $piket = $this->makeUser('guru');
        JadwalPiket::create(['hari' => 'Senin', 'user_id' => $piket->id]);
        [$kelas, $siswa] = $this->buatKelasSiswa();

        $this->actingAs($piket)
            ->post(route('piket.dispensasi.store'), [
                'tanggal' => '2026-08-31',
                'id_siswa' => $siswa->id,
                'jam_ke' => ['3'],
                'jam_keluar_jp' => '3',
                'jam_kembali_jp' => '',
                'alasan' => 'Izin hingga pulang',
                'ttd_guru' => 'data:image/png;base64,GURU',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $dispen = DispensasiSiswa::first();
        $this->assertTrue($dispen->tidak_kembali_hari_ini);
        $this->assertNull($dispen->jam_kembali_jp);
        $this->assertFalse($dispen->isMenungguKembali());

        $this->actingAs($piket)
            ->get(route('piket.dispensasi.index'))
            ->assertOk()
            ->assertSee('Tidak kembali hari ini');
    }

    public function test_store_wajib_pilih_jp_kembali_saat_dicentang(): void
    {
        $piket = $this->makeUser('guru');
        JadwalPiket::create(['hari' => 'Senin', 'user_id' => $piket->id]);
        [$kelas, $siswa] = $this->buatKelasSiswa();

        $this->actingAs($piket)
            ->post(route('piket.dispensasi.store'), [
                'tanggal' => '2026-08-31',
                'id_siswa' => $siswa->id,
                'jam_ke' => ['3'],
                'jam_keluar_jp' => '3',
                'kembali_hari_ini' => '1',
                'jam_kembali_jp' => '',
                'alasan' => 'Kembali ke sekolah',
                'ttd_guru' => 'data:image/png;base64,GURU',
            ])
            ->assertSessionHasErrors('jam_kembali_jp');
    }

    public function test_satpam_konfirmasi_siswa_kembali_mencatat_kedatangan(): void
    {
        $this->buatJamPelajaran();

        $piket = $this->makeUser('guru');
        $satpam = $this->makeUser('admin', ['sub_role' => 'satpam']);
        [$kelas, $siswa] = $this->buatKelasSiswa();

        $dispen = $this->buatDispenSiswa($piket, $siswa, [
            'status' => DispensasiSiswa::STATUS_KELUAR,
            'jam_kembali_jp' => 6,
            'keluar_gerbang_at' => now(),
            'keluar_gerbang_by' => $satpam->id,
        ]);

        $this->assertTrue($dispen->isMenungguKembali());

        $this->actingAs($satpam)
            ->post(route('satpam.dispen.kembali', $dispen))
            ->assertRedirect(route('satpam.dispensasi.index', ['q' => $dispen->approval_token]))
            ->assertSessionHas('success');

        $dispen->refresh();
        $this->assertNotNull($dispen->kembali_at);
        $this->assertEquals($satpam->id, $dispen->kembali_by);
        $this->assertFalse($dispen->isMenungguKembali());

        $this->actingAs($satpam)
            ->get(route('satpam.dispensasi.index', ['q' => $dispen->approval_token]))
            ->assertOk()
            ->assertSee('Konfirmasi Kembali')
            ->assertDontSee('Konfirmasi Siswa Kembali');
    }

    public function test_auto_mangkir_mengubah_status_dan_presensi_menjadi_alfa(): void
    {
        $this->buatJamPelajaran();

        $piket = $this->makeUser('guru');
        $satpam = $this->makeUser('admin', ['sub_role' => 'satpam']);
        [$kelas, $siswa] = $this->buatKelasSiswa();

        [$jadwal, $jurnal] = $this->buatJurnalJp3($kelas);

        $dispen = $this->buatDispenSiswa($piket, $siswa, [
            'status' => DispensasiSiswa::STATUS_KELUAR,
            'jam_kembali_jp' => 3,
            'keluar_gerbang_at' => now(),
            'keluar_gerbang_by' => $satpam->id,
        ]);

        $dispen->terapkanKeAbsensi();
        $this->assertEquals('Dispen', AbsensiJurnal::where('id_siswa', $siswa->id)->first()->status);

        // Batas mangkir = Jam Kembali (08:00) + 1 JP (45 menit) = 08:45.
        $this->assertEquals('08:45', $dispen->batasMangkir()->format('H:i'));

        // Belum lewat batas -> belum Mangkir.
        $this->assertFalse($dispen->refreshStatusMangkir());

        // Lewat batas -> Mangkir / Bolos.
        Carbon::setTestNow(Carbon::create(2026, 8, 31, 9, 0));
        $jumlah = DispensasiSiswa::refreshAutoMangkir();
        $this->assertEquals(1, $jumlah);

        $dispen->refresh();
        $this->assertTrue($dispen->isMangkir());
        $this->assertEquals('Mangkir / Bolos', $dispen->status_label);
        $this->assertEquals('Mangkir / Bolos', $dispen->kesiswaan_status_label);
        $this->assertNotNull($dispen->mangkir_at);
        $this->assertFalse($dispen->isBisaDibatalkan());

        $absensi = AbsensiJurnal::where('id_siswa', $siswa->id)->first();
        $this->assertEquals('Alpa', $absensi->status);
        $this->assertStringStartsWith('Absen Alfa (Mangkir/Bolos):', $absensi->keterangan);

        // Command artisan (scheduler) juga bisa dijalankan tanpa error.
        $this->artisan('dispensasi:auto-mangkir')->assertExitCode(0);
    }

    public function test_satpam_tidak_bisa_konfirmasi_kembali_setelah_mangkir(): void
    {
        $this->buatJamPelajaran();

        $piket = $this->makeUser('guru');
        $satpam = $this->makeUser('admin', ['sub_role' => 'satpam']);
        [$kelas, $siswa] = $this->buatKelasSiswa();

        $dispen = $this->buatDispenSiswa($piket, $siswa, [
            'status' => DispensasiSiswa::STATUS_KELUAR,
            'jam_kembali_jp' => 3,
            'keluar_gerbang_at' => now(),
            'keluar_gerbang_by' => $satpam->id,
        ]);

        // Lewat batas -> terbuka portal satpam otomatis menandai Mangkir.
        Carbon::setTestNow(Carbon::create(2026, 8, 31, 10, 0));

        $this->actingAs($satpam)
            ->get(route('satpam.dispensasi.index', ['q' => $dispen->approval_token]))
            ->assertOk()
            ->assertSee('Mangkir / Bolos')
            ->assertSee('Belum kembali melewati Rencana Jam Kembali + 1 JP')
            ->assertDontSee('Konfirmasi Siswa Kembali');

        $dispen->refresh();
        $this->assertTrue($dispen->isMangkir());

        $this->actingAs($satpam)
            ->post(route('satpam.dispen.kembali', $dispen))
            ->assertRedirect()
            ->assertSessionHas('cancel');

        $this->assertNull($dispen->fresh()->kembali_at);
    }

    public function test_dispen_tidak_kembali_hari_ini_tidak_ditandai_mangkir(): void
    {
        $this->buatJamPelajaran();

        $piket = $this->makeUser('guru');
        $satpam = $this->makeUser('admin', ['sub_role' => 'satpam']);
        [$kelas, $siswa] = $this->buatKelasSiswa();

        $dispen = $this->buatDispenSiswa($piket, $siswa, [
            'status' => DispensasiSiswa::STATUS_KELUAR,
            'tidak_kembali_hari_ini' => true,
            'keluar_gerbang_at' => now(),
            'keluar_gerbang_by' => $satpam->id,
        ]);

        Carbon::setTestNow(Carbon::create(2026, 8, 31, 12, 0));

        $this->assertEquals(0, DispensasiSiswa::refreshAutoMangkir());
        $this->assertFalse($dispen->refresh()->isMangkir());
        $this->assertFalse($dispen->isMenungguKembali());

        $this->actingAs($satpam)
            ->get(route('satpam.dispensasi.index', ['q' => $dispen->approval_token]))
            ->assertOk()
            ->assertSee('Tidak kembali hari ini')
            ->assertDontSee('Konfirmasi Siswa Kembali');
    }

    public function test_waka_kesiswaan_tab_semua_menampilkan_status_mangkir(): void
    {
        $waka = $this->makeUser('admin', ['sub_role' => 'waka_kesiswaan']);
        $piket = $this->makeUser('guru');
        [$kelas, $siswa] = $this->buatKelasSiswa();

        $dispen = $this->buatDispenSiswa($piket, $siswa, [
            'status' => DispensasiSiswa::STATUS_MANGKIR,
            'mangkir_at' => now(),
            'jam_kembali_jp' => 3,
            'keluar_gerbang_at' => now(),
            'ttd_waka' => 'data:image/png;base64,WAKA',
        ]);
        $this->assertNotNull($dispen);

        $this->actingAs($waka)
            ->get(route('waka-kesiswaan.dispensasi.approval.index', ['filter' => 'semua']))
            ->assertOk()
            ->assertSee('Mangkir / Bolos')
            ->assertSee('Rina Marlina');
    }
}
