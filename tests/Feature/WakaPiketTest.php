<?php

namespace Tests\Feature;

use App\Models\IzinGuru;
use App\Models\JadwalPelajaran;
use App\Models\JadwalPiket;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\RekapPiketHarian;
use App\Models\StatusKehadiranGuru;
use App\Models\TahunAjaran;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Portal Waka Piket — dashboard monitoring kehadiran/piket & rekap harian.
 *
 *  - Akses: admin sub_role waka_piket (utama) + waka_kurikulum (pengawas),
 *    403 untuk non-waka.
 *  - Dashboard: stat kehadiran guru (Hadir/Izin/Sakit/Dinas Luar/Alpa),
 *    petugas & koordinator piket, pantauan kelas kosong pagi/siang.
 *  - Rekap Harian: satu baris per tanggal, validasi draft -> verified,
 *    dan catatan Kejadian Luar Biasa (KLB).
 *  - Isolasi data testing: hanya Petugas IT/QA yang dapat memvalidasi data
 *    testing; user non-IT menulis data real (is_testing_data=false).
 */
class WakaPiketTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Kunci "hari ini" ke Senin, 31 Agustus 2026 (hari aktif sekolah).
        Carbon::setTestNow(Carbon::create(2026, 8, 31));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function makeUser(string $role, ?string $subRole = null, string $noHp = ''): User
    {
        return User::create([
            'nama' => 'User '.Str::random(5),
            'username' => 'user_'.Str::random(8),
            'password' => bcrypt('password'),
            'role' => $role,
            'sub_role' => $subRole,
            'no_hp' => $noHp,
            'is_active' => true,
        ]);
    }

    protected function buatJadwalSesi(
        int $idGuru,
        int $idKelas,
        int $idMapel,
        string $jamMulai,
        string $jamSelesai,
        int $jamKe
    ): JadwalPelajaran {
        $tahunAjaran = TahunAjaran::where('is_active', true)->first()
            ?? TahunAjaran::create([
                'tahun_ajaran' => '2026/2027',
                'semester' => 'Ganjil',
                'is_active' => true,
            ]);

        $jam = JamPelajaran::create([
            'hari' => 'Senin',
            'kategori_hari' => 'Senin-Kamis',
            'jam_ke' => $jamKe,
            'jam_mulai' => $jamMulai,
            'jam_selesai' => $jamSelesai,
            'jenis' => 'kbm',
        ]);

        return JadwalPelajaran::create([
            'hari' => 'Senin',
            'id_jam' => $jam->id,
            'id_kelas' => $idKelas,
            'id_mapel' => $idMapel,
            'id_guru' => $idGuru,
            'id_tahun_ajaran' => $tahunAjaran->id,
            'group_id' => (string) Str::uuid(),
        ]);
    }

    public function test_dashboard_terbuka_untuk_waka_piket(): void
    {
        $waka = $this->makeUser('admin', 'waka_piket');

        $res = $this->actingAs($waka)->get(route('waka-piket.dashboard'));

        $res->assertOk();
        $res->assertSee('Dashboard Waka Piket');
    }

    public function test_dashboard_terbuka_untuk_waka_kurikulum(): void
    {
        $wakaKurikulum = $this->makeUser('admin', 'waka_kurikulum');

        $this->actingAs($wakaKurikulum)
            ->get(route('waka-piket.dashboard'))
            ->assertOk();

        $this->actingAs($wakaKurikulum)
            ->get(route('waka-piket.rekap-harian'))
            ->assertOk();
    }

    public function test_dashboard_ditolak_untuk_user_non_waka(): void
    {
        $guru = $this->makeUser('guru');

        $this->actingAs($guru)
            ->get(route('waka-piket.dashboard'))
            ->assertStatus(403);

        $wakaSdm = $this->makeUser('admin', 'waka_sdm');

        $this->actingAs($wakaSdm)
            ->get(route('waka-piket.dashboard'))
            ->assertStatus(403);
    }

    public function test_dashboard_menampilkan_stat_dan_kelas_kosong(): void
    {
        $waka = $this->makeUser('admin', 'waka_piket');

        $guruHadir = $this->makeUser('guru', 'guru_mapel', '081234567801');
        $guruSakit = $this->makeUser('guru', 'guru_mapel', '081234567802');

        $kelas = Kelas::create(['nama_kelas' => 'X IPA 1', 'tingkat' => 'X']);
        $mapel = MataPelajaran::create(['nama_mapel' => 'Matematika', 'kode_mapel' => 'MTK']);

        // Sesi pagi (jam 1) & siang (jam 6) untuk dua guru berbeda.
        $this->buatJadwalSesi($guruHadir->id, $kelas->id, $mapel->id, '07:00', '08:40', 1);
        $this->buatJadwalSesi($guruSakit->id, $kelas->id, $mapel->id, '13:00', '14:30', 6);

        StatusKehadiranGuru::create([
            'user_id' => $guruHadir->id,
            'tanggal' => '2026-08-31',
            'status' => StatusKehadiranGuru::STATUS_HADIR,
        ]);
        StatusKehadiranGuru::create([
            'user_id' => $guruSakit->id,
            'tanggal' => '2026-08-31',
            'status' => StatusKehadiranGuru::STATUS_SAKIT,
        ]);

        // Petugas piket + koordinator pagi/siang hari Senin.
        $petugas = $this->makeUser('guru', null, '081234567803');
        $koordinator = $this->makeUser('guru', null, '081234567804');
        JadwalPiket::create(['hari' => 'Senin', 'user_id' => $petugas->id]);
        JadwalPiket::create([
            'hari' => 'Senin',
            'user_id' => $guruHadir->id,
            'koordinator_pagi_user_id' => $koordinator->id,
            'koordinator_siang_user_id' => $koordinator->id,
        ]);

        $res = $this->actingAs($waka)->get(route('waka-piket.dashboard'));

        $res->assertOk();
        $res->assertSee('Dashboard Waka Piket');
        $res->assertSee('Guru Hadir Hari Ini');
        $res->assertSee('Rincian Kehadiran Guru Hari Ini');
        $res->assertSee('Kelas Kosong Shift Pagi');
        $res->assertSee('Kelas Kosong Shift Siang');
        $res->assertSee('Matematika');
        $res->assertSee('X IPA 1');
        $res->assertSee($petugas->nama);
        $res->assertSee($koordinator->nama);
    }

    public function test_guru_dengan_izin_disetujui_tanpa_record_dihitung_berizin(): void
    {
        $waka = $this->makeUser('admin', 'waka_piket');

        $guru = $this->makeUser('guru', 'guru_mapel');
        $kelas = Kelas::create(['nama_kelas' => 'X IPA 2', 'tingkat' => 'X']);
        $mapel = MataPelajaran::create(['nama_mapel' => 'Fisika', 'kode_mapel' => 'FIS']);
        $this->buatJadwalSesi($guru->id, $kelas->id, $mapel->id, '07:00', '08:40', 1);

        // Izin disetujui (hook membuat record status otomatis). Hapus record
        // hasil sinkronisasi untuk menguji jalur fallback "approved izin".
        IzinGuru::create([
            'user_id' => $guru->id,
            'tanggal' => '2026-08-31',
            'alasan' => 'Dinas luar sekolah',
            'kategori_izin' => 'dinas_luar',
            'status' => IzinGuru::STATUS_DISETUJUI,
        ]);
        StatusKehadiranGuru::where('user_id', $guru->id)
            ->whereDate('tanggal', '2026-08-31')
            ->delete();

        $res = $this->actingAs($waka)->get(route('waka-piket.dashboard'));

        $res->assertOk();
        // Card "Izin / Sakit / Dinas Luar" — Dinas Luar=1, bukan Alpa.
        $res->assertSee('Dinas Luar');
        $res->assertSee('1 Dinas');
    }

    public function test_rekap_harian_dan_validasi_mengunci_status(): void
    {
        $waka = $this->makeUser('admin', 'waka_piket');

        // Halaman rekap terbuka, belum ada data.
        $this->actingAs($waka)
            ->get(route('waka-piket.rekap-harian'))
            ->assertOk()
            ->assertSee('Rekap Harian Piket')
            ->assertSee('Belum ada rekap harian yang dibuat.');

        // Validasi tanggal hari ini -> rekap dibuat (draft->validated).
        $this->actingAs($waka)
            ->post(route('waka-piket.validasi'), ['tanggal' => '2026-08-31'])
            ->assertRedirect(route('waka-piket.rekap-harian', ['tanggal' => '2026-08-31']))
            ->assertSessionHas('success');

        $rekap = RekapPiketHarian::whereDate('tanggal', '2026-08-31')->firstOrFail();
        $this->assertSame(RekapPiketHarian::STATUS_VALIDATED, $rekap->status);
        $this->assertSame($waka->id, (int) $rekap->validated_by);
        $this->assertNotNull($rekap->validated_at);
        // Non-IT menulis data real.
        $this->assertFalse((bool) $rekap->is_testing_data);

        // Validasi kedua ditolak — dokumen sudah terkunci.
        $this->actingAs($waka)
            ->post(route('waka-piket.validasi'), ['tanggal' => '2026-08-31'])
            ->assertStatus(422);
    }

    public function test_catatan_klb_tersimpan_dan_terkunci_setelah_validasi(): void
    {
        $waka = $this->makeUser('admin', 'waka_piket');

        // Simpan KLB -> rekap baru dibuat sebagai draft + catatan tersimpan.
        $this->actingAs($waka)
            ->post(route('waka-piket.klb'), [
                'tanggal' => '2026-08-31',
                'catatan_klb' => 'Pemadaman listrik jam 09.00 - 10.00.',
            ])
            ->assertRedirect(route('waka-piket.rekap-harian', ['tanggal' => '2026-08-31']))
            ->assertSessionHas('success');

        $rekap = RekapPiketHarian::whereDate('tanggal', '2026-08-31')->firstOrFail();
        $this->assertSame(RekapPiketHarian::STATUS_DRAFT, $rekap->status);
        $this->assertSame('Pemadaman listrik jam 09.00 - 10.00.', $rekap->catatan_klb);

        // Perbarui KLB (masih draft).
        $this->actingAs($waka)
            ->post(route('waka-piket.klb'), [
                'tanggal' => '2026-08-31',
                'catatan_klb' => 'Pemadaman listrik jam 09.00 - 10.00; KBM mandiri.',
            ])
            ->assertSessionHas('success');

        $this->assertSame('Pemadaman listrik jam 09.00 - 10.00; KBM mandiri.', $rekap->fresh()->catatan_klb);

        // Validasi rekap.
        $this->actingAs($waka)
            ->post(route('waka-piket.validasi'), ['tanggal' => '2026-08-31'])
            ->assertRedirect(route('waka-piket.rekap-harian', ['tanggal' => '2026-08-31']));

        // Setelah validasi, catatan KLB terkunci (422).
        $this->actingAs($waka)
            ->post(route('waka-piket.klb'), [
                'tanggal' => '2026-08-31',
                'catatan_klb' => 'Revisi setelah validasi.',
            ])
            ->assertStatus(422);
    }

    public function test_validasi_rekap_data_testing_hanya_oleh_petugas_it(): void
    {
        $itUser = $this->makeUser('petugas_it');

        RekapPiketHarian::create([
            'tanggal' => '2026-08-31',
            'status' => RekapPiketHarian::STATUS_DRAFT,
            'is_testing_data' => true,
        ]);

        // Petugas IT (isi data testing) memvalidasi rekap sandbox miliknya.
        $this->actingAs($itUser)
            ->post(route('waka-piket.validasi'), ['tanggal' => '2026-08-31'])
            ->assertRedirect(route('waka-piket.rekap-harian', ['tanggal' => '2026-08-31']));

        $rekap = RekapPiketHarian::whereDate('tanggal', '2026-08-31')->firstOrFail();
        $this->assertSame(RekapPiketHarian::STATUS_VALIDATED, $rekap->status);
        $this->assertSame($itUser->id, (int) $rekap->validated_by);
        $this->assertTrue((bool) $rekap->is_testing_data);
    }
}