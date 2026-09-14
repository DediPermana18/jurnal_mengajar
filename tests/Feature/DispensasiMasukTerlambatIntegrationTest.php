<?php

namespace Tests\Feature;

use App\Models\CatatanTerlambat;
use App\Models\DispensasiKolektif;
use App\Models\DispensasiSiswa;
use App\Models\JadwalPiket;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DispensasiMasukTerlambatIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 9, 14));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function makeUser(string $role, ?string $subRole = null): User
    {
        return User::create([
            'nama' => 'User '.Str::random(5),
            'username' => 'user_'.Str::random(8),
            'password' => bcrypt('password'),
            'role' => $role,
            'sub_role' => $subRole,
            'is_active' => true,
        ]);
    }

    protected function makePiket(): User
    {
        $piket = $this->makeUser('guru');
        JadwalPiket::create(['hari' => 'Senin', 'user_id' => $piket->id]);

        return $piket;
    }

    protected function makeSatpam(): User
    {
        return $this->makeUser('admin', 'satpam');
    }

    protected function makeKelasDanSiswa(?User $waliKelas = null): array
    {
        $kelas = Kelas::create([
            'nama_kelas' => 'XI IPA 1',
            'tingkat' => 'XI',
            'id_wali_kelas' => $waliKelas?->id,
        ]);

        $siswa = Siswa::create([
            'nisn' => '0000001234',
            'nis' => '1234',
            'nama' => 'Ani Wati',
            'jenis_kelamin' => 'P',
            'id_kelas' => $kelas->id,
            'status_siswa' => 'Aktif',
        ]);

        return [$kelas, $siswa];
    }

    protected function buatCatatanTerlambat(Siswa $siswa, array $extra = []): CatatanTerlambat
    {
        return CatatanTerlambat::create(array_merge([
            'id_siswa' => $siswa->id,
            'tanggal' => '2026-09-14',
            'jam_masuk' => '07:15',
            'keterangan' => 'Terlambat',
            'id_satpam' => $this->makeSatpam()->id,
        ], $extra));
    }

    // ------------------------------------------------------------------
    // TEST: API terlambat-hari-ini
    // ------------------------------------------------------------------

    public function test_api_terlambat_hari_ini_hanya_mengembalikan_catatan_belum_dikaitkan(): void
    {
        $piket = $this->makePiket();
        [$kelas, $siswa] = $this->makeKelasDanSiswa();

        $catatan = $this->buatCatatanTerlambat($siswa);

        $this->actingAs($piket)
            ->getJson(route('piket.dispensasi.terlambat-hari-ini'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $catatan->id)
            ->assertJsonPath('data.0.nama', 'Ani Wati')
            ->assertJsonPath('data.0.jam_masuk', '07:15');
    }

    public function test_api_terlambat_hari_ini_tidak_mengembalikan_catatan_sudah_dikaitkan(): void
    {
        $piket = $this->makePiket();
        [$kelas, $siswa] = $this->makeKelasDanSiswa();

        $dispen = DispensasiSiswa::create([
            'id_siswa' => $siswa->id,
            'id_guru_piket' => $piket->id,
            'tanggal' => '2026-09-14',
            'tipe_dispen' => DispensasiSiswa::TIPE_MASUK,
            'jam_masuk_jp' => 2,
            'alasan' => 'Terlambat Sekolah',
            'status' => DispensasiSiswa::STATUS_DISETUJUI,
            'approved_at' => now(),
            'approved_by' => $piket->id,
            'ttd_guru' => 'data:image/png;base64,TDD',
            'approval_token' => Str::random(16),
        ]);

        $catatan = $this->buatCatatanTerlambat($siswa, [
            'dispensasi_id' => $dispen->id,
            'is_approved_piket' => true,
        ]);

        $this->actingAs($piket)
            ->getJson(route('piket.dispensasi.terlambat-hari-ini'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_api_terlambat_hari_ini_mengembalikan_saran_jp(): void
    {
        $piket = $this->makePiket();
        [$kelas, $siswa] = $this->makeKelasDanSiswa();

        JamPelajaran::create([
            'kategori_hari' => 'Senin-Kamis',
            'jam_ke' => 1,
            'jam_mulai' => '07:00',
            'jam_selesai' => '07:45',
            'jenis' => 'kbm',
        ]);
        JamPelajaran::create([
            'kategori_hari' => 'Senin-Kamis',
            'jam_ke' => 2,
            'jam_mulai' => '07:45',
            'jam_selesai' => '08:30',
            'jenis' => 'kbm',
        ]);

        $catatan = $this->buatCatatanTerlambat($siswa, ['jam_masuk' => '07:20']);

        $this->actingAs($piket)
            ->getJson(route('piket.dispensasi.terlambat-hari-ini'))
            ->assertOk()
            ->assertJsonPath('data.0.saran_jp', 2);
    }

    // ------------------------------------------------------------------
    // TEST: Store single mengkaitkan catatan
    // ------------------------------------------------------------------

    public function test_store_single_masuk_mengkaitkan_catatan_terlambat(): void
    {
        $piket = $this->makePiket();
        [$kelas, $siswa] = $this->makeKelasDanSiswa();
        $catatan = $this->buatCatatanTerlambat($siswa);

        $this->actingAs($piket)
            ->post(route('piket.dispensasi.store'), [
                'tipe_dispen' => DispensasiSiswa::TIPE_MASUK,
                'tanggal' => '2026-09-14',
                'id_siswa' => [$siswa->id],
                'jam_masuk_jp' => 2,
                'alasan_kategori' => 'Terlambat Sekolah',
                'ttd_guru' => 'data:image/png;base64,TDD_GURU',
                'catatan_terlambat_id' => [$catatan->id],
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame(1, DispensasiSiswa::count());
        $this->assertTrue($catatan->fresh()->is_approved_piket);
        $this->assertSame(1, (int) $catatan->fresh()->dispensasi_id);
    }

    public function test_store_single_masuk_tanpa_catatan_id_tidak_error(): void
    {
        $piket = $this->makePiket();
        [$kelas, $siswa] = $this->makeKelasDanSiswa();

        $this->actingAs($piket)
            ->post(route('piket.dispensasi.store'), [
                'tipe_dispen' => DispensasiSiswa::TIPE_MASUK,
                'tanggal' => '2026-09-14',
                'id_siswa' => [$siswa->id],
                'jam_masuk_jp' => 3,
                'alasan_kategori' => 'Lainnya',
                'alasan_detail' => 'Urusan keluarga',
                'ttd_guru' => 'data:image/png;base64,TDD_GURU',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame(1, DispensasiSiswa::count());
    }

    // ------------------------------------------------------------------
    // TEST: Store kolektif mengkaitkan catatan per siswa
    // ------------------------------------------------------------------

    public function test_store_kolektif_masuk_mengkaitkan_catatan_per_siswa(): void
    {
        $piket = $this->makePiket();
        $wali = $this->makeUser('guru', 'wali_kelas');
        $kelas = Kelas::create([
            'nama_kelas' => 'XI IPA 1',
            'tingkat' => 'XI',
            'id_wali_kelas' => $wali->id,
        ]);

        $siswa1 = Siswa::create([
            'nisn' => '0000000111', 'nis' => '111',
            'nama' => 'Siswa Satu', 'jenis_kelamin' => 'L',
            'id_kelas' => $kelas->id, 'status_siswa' => 'Aktif',
        ]);
        $siswa2 = Siswa::create([
            'nisn' => '0000000222', 'nis' => '222',
            'nama' => 'Siswa Dua', 'jenis_kelamin' => 'P',
            'id_kelas' => $kelas->id, 'status_siswa' => 'Aktif',
        ]);

        $catatan1 = $this->buatCatatanTerlambat($siswa1, ['jam_masuk' => '07:10']);
        $catatan2 = $this->buatCatatanTerlambat($siswa2, ['jam_masuk' => '07:25']);

        $this->actingAs($piket)
            ->post(route('piket.dispensasi.store'), [
                'tipe_dispen' => DispensasiSiswa::TIPE_MASUK,
                'tanggal' => '2026-09-14',
                'id_siswa' => [$siswa1->id, $siswa2->id],
                'jam_masuk_jp' => 2,
                'alasan_kategori' => 'Terlambat Sekolah',
                'ttd_guru' => 'data:image/png;base64,TDD_GURU',
                'ttd_siswa' => ['data:image/png;base64,TTD_S1', 'data:image/png;base64,TTD_S2'],
                'catatan_terlambat_id' => [$catatan1->id, $catatan2->id],
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame(1, DispensasiKolektif::count());
        $this->assertSame(2, DispensasiSiswa::count());

        $this->assertTrue($catatan1->fresh()->is_approved_piket);
        $this->assertTrue($catatan2->fresh()->is_approved_piket);
        $this->assertSame(DispensasiSiswa::orderBy('id')->first()->id, (int) $catatan1->fresh()->dispensasi_id);
        $this->assertSame(DispensasiSiswa::orderBy('id')->get()->last()->id, (int) $catatan2->fresh()->dispensasi_id);
    }

    // ------------------------------------------------------------------
    // TEST: Surat Masuk menampilkan jam kedatangan di gerbang bila terkait
    // ------------------------------------------------------------------

    public function test_surat_masuk_menampilkan_jam_kedatangan_gerbang(): void
    {
        $piket = $this->makePiket();
        [$kelas, $siswa] = $this->makeKelasDanSiswa();
        $catatan = $this->buatCatatanTerlambat($siswa, ['jam_masuk' => '07:15']);

        $dispen = DispensasiSiswa::create([
            'id_siswa' => $siswa->id,
            'id_guru_piket' => $piket->id,
            'tanggal' => '2026-09-14',
            'tipe_dispen' => DispensasiSiswa::TIPE_MASUK,
            'jam_masuk_jp' => 2,
            'alasan' => 'Terlambat Sekolah',
            'status' => DispensasiSiswa::STATUS_DISETUJUI,
            'approved_at' => now(),
            'approved_by' => $piket->id,
            'ttd_guru' => 'data:image/png;base64,TDD',
            'approval_token' => Str::random(16),
        ]);

        $catatan->update(['is_approved_piket' => true, 'dispensasi_id' => $dispen->id]);

        $this->actingAs($piket)
            ->get(route('piket.dispensasi.surat', $dispen->id))
            ->assertOk()
            ->assertSee('Jam Kedatangan di Gerbang')
            ->assertSee('07:15');
    }

    public function test_surat_masuk_tanpa_catatan_tetap_tampil(): void
    {
        $piket = $this->makePiket();
        [$kelas, $siswa] = $this->makeKelasDanSiswa();

        $dispen = DispensasiSiswa::create([
            'id_siswa' => $siswa->id,
            'id_guru_piket' => $piket->id,
            'tanggal' => '2026-09-14',
            'tipe_dispen' => DispensasiSiswa::TIPE_MASUK,
            'jam_masuk_jp' => 2,
            'alasan' => 'Terlambat Sekolah',
            'status' => DispensasiSiswa::STATUS_DISETUJUI,
            'approved_at' => now(),
            'approved_by' => $piket->id,
            'ttd_guru' => 'data:image/png;base64,TDD',
            'approval_token' => Str::random(16),
        ]);

        $this->actingAs($piket)
            ->get(route('piket.dispensasi.surat', $dispen->id))
            ->assertOk()
            ->assertSee('Jam Kedatangan di Gerbang')
            ->assertSee('tidak tercatat di gerbang');
    }
}
