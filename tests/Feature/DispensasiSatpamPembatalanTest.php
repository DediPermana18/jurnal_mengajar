<?php

namespace Tests\Feature;

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

class DispensasiSatpamPembatalanTest extends TestCase
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

    protected function buatDispenSiswa($piket, $siswa, array $extra = []): DispensasiSiswa
    {
        return DispensasiSiswa::create(array_merge([
            'id_siswa' => $siswa->id,
            'id_guru_piket' => $piket->id,
            'tanggal' => now()->toDateString(),
            'jam_keluar_jp' => 3,
            'alasan' => 'Keperluan keluarga',
            'status' => DispensasiSiswa::STATUS_DISETUJUI,
            'ttd_guru' => 'data:image/png;base64,TEST',
            'approval_token' => 'token-'.Str::uuid(),
        ], $extra));
    }

    public function test_auto_expire_massal_mengubah_surat_dispen_melewati_batas(): void
    {
        $this->buatJamPelajaran();

        $piket = $this->makeUser('guru');
        [$kelas, $siswa] = $this->buatKelasSiswa();
        $dispen = $this->buatDispenSiswa($piket, $siswa);

        // Batas kadaluarsa = Jam Berangkat (08:00) + 1 JP (45 menit) = 08:45.
        $this->assertEquals('08:45', $dispen->batasKadaluarsa()->format('H:i'));

        // Belum lewat batas -> masih aktif.
        $this->assertFalse($dispen->refreshStatusOtomatis());
        $this->assertFalse($dispen->isExpired());

        // Lewat batas (siang hari) -> auto-expired.
        Carbon::setTestNow(Carbon::create(2026, 8, 31, 10, 0));

        $jumlah = DispensasiSiswa::refreshAutoExpired();
        $this->assertEquals(1, $jumlah);

        $dispen->refresh();
        $this->assertTrue($dispen->isExpired());
        $this->assertEquals('Kadaluarsa', $dispen->status_label);
        $this->assertNotNull($dispen->expired_at);

        // Command artisan juga bisa dijalankan (scheduler) tanpa error.
        $this->artisan('dispensasi:auto-expire')->assertExitCode(0);
    }

    public function test_satpam_konfirmasi_keluar_gerbang_mengubah_status_siswa_out(): void
    {
        $piket = $this->makeUser('guru');
        [$kelas, $siswa] = $this->buatKelasSiswa();
        $dispen = $this->buatDispenSiswa($piket, $siswa);

        $satpam = $this->makeUser('admin', ['sub_role' => 'satpam']);

        // Portal Verifikasi Dispensasi: check via nomor surat & token.
        $this->actingAs($satpam)
            ->get(route('satpam.dispensasi.index', ['q' => $dispen->approval_token]))
            ->assertOk()
            ->assertSee('Rina Marlina')
            ->assertSee('Disetujui')
            ->assertSee('Konfirmasi Keluar Gerbang');

        $this->actingAs($satpam)
            ->get(route('satpam.dispensasi.index', ['q' => $dispen->nomor_surat]))
            ->assertOk()
            ->assertSee('Rina Marlina');

        $this->actingAs($satpam)
            ->post(route('satpam.dispen.keluar', $dispen))
            ->assertRedirect(route('satpam.dispensasi.index', ['q' => $dispen->approval_token]))
            ->assertSessionHas('success');

        $dispen->refresh();
        $this->assertEquals(DispensasiSiswa::STATUS_KELUAR, $dispen->status);
        $this->assertEquals('Siswa Out', $dispen->status_label);
        $this->assertNotNull($dispen->keluar_gerbang_at);
        $this->assertEquals($satpam->id, $dispen->keluar_gerbang_by);
    }

    public function test_satpam_tidak_bisa_mengizinkan_surat_kadaluarsa(): void
    {
        $this->buatJamPelajaran();

        $piket = $this->makeUser('guru');
        [$kelas, $siswa] = $this->buatKelasSiswa();
        $dispen = $this->buatDispenSiswa($piket, $siswa);

        // Lewat batas (10:00 > 08:45) -> Kadaluarsa.
        Carbon::setTestNow(Carbon::create(2026, 8, 31, 10, 0));
        $dispen->refreshStatusOtomatis();
        $this->assertTrue($dispen->refresh()->isExpired());

        $satpam = $this->makeUser('admin', ['sub_role' => 'satpam']);

        $this->actingAs($satpam)
            ->get(route('satpam.dispensasi.index', ['q' => $dispen->approval_token]))
            ->assertOk()
            ->assertSee('Kadaluarsa')
            ->assertSee('KADALUARSA')
            ->assertDontSee('Konfirmasi Keluar Gerbang');

        $this->actingAs($satpam)
            ->post(route('satpam.dispen.keluar', $dispen))
            ->assertRedirect()
            ->assertSessionHas('cancel');

        $dispen->refresh();
        $this->assertTrue($dispen->isExpired());
        $this->assertNull($dispen->keluar_gerbang_at);
    }

    public function test_guru_piket_membatalkan_dispensasi_dengan_ttd_siswa(): void
    {
        $piket = $this->makeUser('guru');
        JadwalPiket::create(['hari' => 'Senin', 'user_id' => $piket->id]);

        [$kelas, $siswa] = $this->buatKelasSiswa();
        $dispen = $this->buatDispenSiswa($piket, $siswa, [
            'status' => DispensasiSiswa::STATUS_APPROVED,
            'ttd_waka' => 'data:image/png;base64,WAKA',
        ]);

        $this->assertTrue($dispen->isBisaDibatalkan());

        // Tanpa TTD -> validasi menolak.
        $this->actingAs($piket)
            ->post(route('piket.dispensasi.batalkan', $dispen->id), ['ttd_pembatalan' => ''])
            ->assertSessionHasErrors('ttd_pembatalan');

        // Dengan TTD siswa -> surat dibatalkan.
        $this->actingAs($piket)
            ->post(route('piket.dispensasi.batalkan', $dispen->id), [
                'ttd_pembatalan' => 'data:image/png;base64,BATAL',
            ])
            ->assertRedirect(route('piket.dispensasi.index', ['tanggal' => now()->toDateString()]))
            ->assertSessionHas('success');

        $dispen->refresh();
        $this->assertEquals(DispensasiSiswa::STATUS_DIBATALKAN, $dispen->status);
        $this->assertEquals('Dibatalkan', $dispen->status_label);
        $this->assertNotNull($dispen->ttd_pembatalan);
        $this->assertEquals($piket->id, $dispen->dibatalkan_by);
        $this->assertNotNull($dispen->dibatalkan_at);

        // Setelah dibatalkan tidak boleh dibatalkan lagi.
        $this->assertFalse($dispen->isBisaDibatalkan());
        $this->actingAs($piket)
            ->post(route('piket.dispensasi.batalkan', $dispen->id), [
                'ttd_pembatalan' => 'data:image/png;base64,BATAL2',
            ])
            ->assertStatus(422);
    }

    public function test_waka_kesiswaan_tab_semua_menampilkan_status_kadaluarsa(): void
    {
        $waka = $this->makeUser('admin', ['sub_role' => 'waka_kesiswaan']);
        $piket = $this->makeUser('guru');
        [$kelas, $siswa] = $this->buatKelasSiswa();

        $dispen = $this->buatDispenSiswa($piket, $siswa, [
            'status' => DispensasiSiswa::STATUS_EXPIRED,
            'expired_at' => now(),
            'ttd_waka' => 'data:image/png;base64,WAKA',
        ]);

        $this->actingAs($waka)
            ->get(route('waka-kesiswaan.dispensasi.approval.index', ['filter' => 'semua']))
            ->assertOk()
            ->assertSee('Kadaluarsa')
            ->assertSee('Rina Marlina');

        // Surat kadaluarsa tidak lagi masuk daftar "Menunggu TTD".
        $this->actingAs($waka)
            ->get(route('waka-kesiswaan.dispensasi.approval.index', ['filter' => 'menunggu']))
            ->assertOk()
            ->assertDontSee('Rina Marlina');
    }
}
