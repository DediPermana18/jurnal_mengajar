<?php

namespace Tests\Feature;

use App\Models\DispensasiSiswa;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SatpamVerifikasiTtdLengkapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 8, 31)); // Senin
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function makeSatpam(): User
    {
        return User::create([
            'nama' => 'Satpam Guard',
            'username' => 'satpam_'.Str::random(5),
            'password' => bcrypt('password'),
            'role' => 'admin',
            'sub_role' => 'satpam',
            'is_active' => true,
        ]);
    }

    protected function makeGuruPiket(): User
    {
        return User::create([
            'nama' => 'Guru Piket',
            'username' => 'piket_'.Str::random(5),
            'password' => bcrypt('password'),
            'role' => 'guru',
            'sub_role' => 'guru',
            'is_active' => true,
        ]);
    }

    protected function makeSiswa(): Siswa
    {
        $kelas = Kelas::create(['nama_kelas' => 'XII IPA 1', 'tingkat' => 'XII']);

        return Siswa::create([
            'nama' => 'Ahmad Fauzi',
            'nisn' => '1122334455',
            'nis' => '11223',
            'jenis_kelamin' => 'L',
            'id_kelas' => $kelas->id,
            'status_siswa' => 'Aktif',
        ]);
    }

    public function test_satpam_menolak_verifikasi_keluar_jika_ttd_waka_belum_ada(): void
    {
        $satpam = $this->makeSatpam();
        $piket = $this->makeGuruPiket();
        $siswa = $this->makeSiswa();

        // Dispen memiliki TTD Siswa & Guru Piket, tetapi BELUM di-ACC / TTD Waka Kesiswaan
        $dispen = DispensasiSiswa::create([
            'id_siswa' => $siswa->id,
            'id_guru_piket' => $piket->id,
            'tanggal' => now()->toDateString(),
            'jenis' => DispensasiSiswa::JENIS_KELUAR,
            'jam_ke' => '3,4',
            'jam_keluar_jp' => 3,
            'alasan' => 'Izin lomba',
            'status' => DispensasiSiswa::STATUS_DISETUJUI,
            'approval_token' => 'token-'.Str::uuid(),
            'ttd_siswa' => 'data:image/png;base64,TTD_SISWA',
            'ttd_guru' => 'data:image/png;base64,TTD_GURU',
            'ttd_waka' => null, // Waka belum TTD
        ]);

        // 1. Buka halaman verifikasi Satpam
        $this->actingAs($satpam)
            ->get(route('satpam.verifikasi', ['q' => $dispen->approval_token]))
            ->assertOk()
            ->assertSee('Verifikasi Keluar Gagal! Surat Dispensasi belum ditandatangani lengkap')
            ->assertSee('TTD Waka Kesiswaan: Belum TTD')
            ->assertDontSee('🚪 Verifikasi / Konfirmasi Keluar Gerbang');

        // 2. Coba post verifikasi keluar secara langsung -> WAJIB ditolak
        $this->actingAs($satpam)
            ->post(route('satpam.dispen.keluar', $dispen))
            ->assertRedirect(route('satpam.dispensasi.index', ['q' => $dispen->approval_token]))
            ->assertSessionHas('cancel', 'Verifikasi Keluar Gagal! Surat Dispensasi belum ditandatangani lengkap oleh Siswa, Guru Piket, atau Waka Kesiswaan.');

        // Status di database TIDAK berubah menjadi Siswa Out
        $this->assertNull($dispen->fresh()->keluar_gerbang_at);
        $this->assertNotEquals(DispensasiSiswa::STATUS_KELUAR, $dispen->fresh()->status);
    }

    public function test_satpam_meloloskan_verifikasi_keluar_setelah_ttd_waka_lengkap(): void
    {
        $satpam = $this->makeSatpam();
        $piket = $this->makeGuruPiket();
        $siswa = $this->makeSiswa();
        $waka = User::create([
            'nama' => 'Waka Kesiswaan',
            'username' => 'waka_'.Str::random(5),
            'password' => bcrypt('password'),
            'role' => 'admin',
            'sub_role' => 'waka_kesiswaan',
            'is_active' => true,
        ]);

        // Dispen sudah ditandatangani lengkap 3 TTD (Siswa, Piket, Waka)
        $dispen = DispensasiSiswa::create([
            'id_siswa' => $siswa->id,
            'id_guru_piket' => $piket->id,
            'tanggal' => now()->toDateString(),
            'jenis' => DispensasiSiswa::JENIS_KELUAR,
            'jam_ke' => '3,4',
            'jam_keluar_jp' => 3,
            'alasan' => 'Izin lomba',
            'status' => DispensasiSiswa::STATUS_APPROVED,
            'approval_token' => 'token-'.Str::uuid(),
            'ttd_siswa' => 'data:image/png;base64,TTD_SISWA',
            'ttd_guru' => 'data:image/png;base64,TTD_GURU',
            'ttd_waka' => 'data:image/png;base64,TTD_WAKA',
            'waka_kesiswaan_id' => $waka->id,
        ]);

        // 1. Buka verifikasi -> tampilkan tombol verifikasi keluar
        $this->actingAs($satpam)
            ->get(route('satpam.verifikasi', ['q' => $dispen->approval_token]))
            ->assertOk()
            ->assertSee('Surat izin valid untuk keluar hari ini')
            ->assertSee('TTD Siswa: Ada')
            ->assertSee('TTD Guru Piket: Ada')
            ->assertSee('TTD Waka Kesiswaan: Ada');

        // 2. Post verifikasi keluar -> BERHASIL
        $this->actingAs($satpam)
            ->post(route('satpam.dispen.keluar', $dispen))
            ->assertRedirect(route('satpam.dispensasi.index', ['q' => $dispen->approval_token]))
            ->assertSessionHas('success');

        $this->assertNotNull($dispen->fresh()->keluar_gerbang_at);
        $this->assertEquals(DispensasiSiswa::STATUS_KELUAR, $dispen->fresh()->status);
    }
}
