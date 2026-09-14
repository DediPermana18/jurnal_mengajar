<?php

namespace Tests\Feature;

use App\Models\DispensasiSiswa;
use App\Models\JadwalPiket;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regresi bug "Lihat Surat" 404:
 * link surat (/piket/dispensasi/{id}/surat) yang tampil di portal Waka Kesiswaan
 * saat Petugas IT impersonasi 'waka_kesiswaan' mengarah ke ID record real yang
 * disembunyikan TestingDataScope, sehingga findOrFail() lama berakhir 404.
 */
class DispensasiSuratScopeTest extends TestCase
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

    protected function buatSiswa(string $nama, string $nisn): Siswa
    {
        $kelas = Kelas::create(['nama_kelas' => 'XII IPA 1', 'tingkat' => 'XII']);

        return Siswa::create([
            'nama' => $nama,
            'nisn' => $nisn,
            'nis' => '12345',
            'jenis_kelamin' => 'L',
            'id_kelas' => $kelas->id,
            'status_siswa' => 'Aktif',
        ]);
    }

    protected function buatDispensasi(Siswa $siswa, User $piket, array $extra = []): DispensasiSiswa
    {
        return DispensasiSiswa::create(array_merge([
            'id_siswa' => $siswa->id,
            'id_guru_piket' => $piket->id,
            'tanggal' => now()->toDateString(),
            'jam_ke' => '1,2',
            'alasan' => 'Alasan dispensasi',
            'status' => DispensasiSiswa::STATUS_APPROVED,
            'ttd_guru' => 'data:image/png;base64,PROD',
            'ttd_waka' => 'data:image/png;base64,WAKA',
            'approved_by' => $piket->id,
        ], $extra));
    }

    public function test_impersonasi_waka_portal_menampilkan_real_dan_lihat_surat_ok(): void
    {
        $piket = $this->makeUser('guru');
        JadwalPiket::create(['hari' => 'Senin', 'user_id' => $piket->id]);

        $waka = $this->makeUser('admin', ['sub_role' => 'waka_kesiswaan']);
        $siswa = $this->buatSiswa('Budi Santoso', '1234567890');

        // Data produksi (is_testing_data = false) disetujui Waka.
        $produksi = $this->buatDispensasi($siswa, $piket, ['approved_by' => $waka->id]);
        $this->assertFalse((bool) $produksi->is_testing_data);

        // IT impersonasi role waka_kesiswaan -> portal menampilkan real + testing.
        $this->actingAs($this->makeUser('petugas_it'));
        session(['active_role' => 'waka_kesiswaan']);

        $resp = $this->get(route('waka-kesiswaan.dispensasi.approval.index', ['filter' => 'semua']));
        $resp->assertOk();
        $resp->assertSee('Budi Santoso');
        $resp->assertSee(route('piket.dispensasi.surat', $produksi->id), false);

        // Klik "Lihat Surat" pada baris real -> harus 200 (dulu 404).
        $resp = $this->get(route('piket.dispensasi.surat', $produksi->id));
        $resp->assertOk();
        $resp->assertSee('Budi Santoso');
    }

    public function test_impersonasi_waka_lihat_surat_record_testing_ok(): void
    {
        $piket = $this->makeUser('guru');
        JadwalPiket::create(['hari' => 'Senin', 'user_id' => $piket->id]);

        $siswa = $this->buatSiswa('Siti Aminah', '2234567890');

        $this->actingAs($this->makeUser('petugas_it'));
        session(['active_role' => 'waka_kesiswaan']);

        // IT membuat record testing (is_testing_data = true via HasTestingData).
        $testing = $this->buatDispensasi($siswa, $piket);
        $this->assertTrue((bool) $testing->is_testing_data);

        $this->get(route('piket.dispensasi.surat', $testing->id))
            ->assertOk()
            ->assertSee('Siti Aminah');
    }

    public function test_it_biasa_tanpa_impersonasi_lihat_surat_real_adalah_404(): void
    {
        $piket = $this->makeUser('guru');
        JadwalPiket::create(['hari' => 'Senin', 'user_id' => $piket->id]);

        $waka = $this->makeUser('admin', ['sub_role' => 'waka_kesiswaan']);
        $siswa = $this->buatSiswa('Joko Widodo', '3344556677');
        $produksi = $this->buatDispensasi($siswa, $piket, ['approved_by' => $waka->id]);
        $this->assertFalse((bool) $produksi->is_testing_data);

        // IT biasa (belum impersonasi) tetap dilarang membuka surat real.
        $this->actingAs($this->makeUser('petugas_it'));

        $this->get(route('piket.dispensasi.surat', $produksi->id))
            ->assertNotFound();
    }

    public function test_non_it_tidak_bisa_lihat_surat_record_testing(): void
    {
        $piket = $this->makeUser('guru');
        JadwalPiket::create(['hari' => 'Senin', 'user_id' => $piket->id]);

        $siswa = $this->buatSiswa('Rina Melati', '4455667788');

        $this->actingAs($this->makeUser('petugas_it'));
        $testing = $this->buatDispensasi($siswa, $piket);
        $this->assertTrue((bool) $testing->is_testing_data);

        // Guru piket non-IT hanya melihat data real -> surat testing 404.
        $this->actingAs($piket);

        $this->get(route('piket.dispensasi.surat', $testing->id))
            ->assertNotFound();
    }

    public function test_guru_piket_pembuat_bisa_lihat_surat_own_record(): void
    {
        $piket = $this->makeUser('guru');
        JadwalPiket::create(['hari' => 'Senin', 'user_id' => $piket->id]);

        $siswa = $this->buatSiswa('Andi Wijaya', '5566778899');
        $dispen = $this->buatDispensasi($siswa, $piket);

        $this->actingAs($piket);

        // Wali/approver berbeda -> hanya bisa karena id_guru_piket == user.
        $this->get(route('piket.dispensasi.surat', $dispen->id))
            ->assertOk()
            ->assertSee('Andi Wijaya');
    }

    public function test_waka_asli_bisa_lihat_surat(): void
    {
        $piket = $this->makeUser('guru');
        JadwalPiket::create(['hari' => 'Senin', 'user_id' => $piket->id]);

        $waka = $this->makeUser('admin', ['sub_role' => 'waka_kesiswaan']);
        $siswa = $this->buatSiswa('Dewi Anggraini', '6677889900');
        $dispen = $this->buatDispensasi($siswa, $piket, ['approved_by' => $waka->id]);

        $this->actingAs($waka);

        $this->get(route('piket.dispensasi.surat', $dispen->id))
            ->assertOk()
            ->assertSee('Dewi Anggraini');
    }

    public function test_tombol_kembali_surat_tidak_terbaca_kembali_ke_piket_saat_waka_asli(): void
    {
        $piket = $this->makeUser('guru');
        JadwalPiket::create(['hari' => 'Senin', 'user_id' => $piket->id]);

        $waka = $this->makeUser('admin', ['sub_role' => 'waka_kesiswaan']);
        $siswa = $this->buatSiswa('Waka Utami', '7788990011');
        $dispen = $this->buatDispensasi($siswa, $piket, ['approved_by' => $waka->id]);

        $this->actingAs($waka);

        $this->get(route('piket.dispensasi.surat', $dispen->id))
            ->assertOk()
            ->assertSee(route('waka-kesiswaan.dispensasi.approval.index'), false)
            ->assertDontSee(route('piket.dispensasi.index'), false);
    }

    public function test_tombol_kembali_surat_menuju_waka_saat_impersonasi_waka(): void
    {
        $piket = $this->makeUser('guru');
        JadwalPiket::create(['hari' => 'Senin', 'user_id' => $piket->id]);

        $waka = $this->makeUser('admin', ['sub_role' => 'waka_kesiswaan']);
        $siswa = $this->buatSiswa('Impersonasi Waka', '8899001122');
        $produksi = $this->buatDispensasi($siswa, $piket, ['approved_by' => $waka->id]);

        $this->actingAs($this->makeUser('petugas_it'));
        session(['active_role' => 'waka_kesiswaan']);

        $this->get(route('piket.dispensasi.surat', $produksi->id))
            ->assertOk()
            ->assertSee(route('waka-kesiswaan.dispensasi.approval.index'), false)
            ->assertDontSee(route('piket.dispensasi.index'), false);
    }

    public function test_tombol_kembali_surat_menuju_piket_saat_guru_piket(): void
    {
        $piket = $this->makeUser('guru');
        JadwalPiket::create(['hari' => 'Senin', 'user_id' => $piket->id]);

        $siswa = $this->buatSiswa('Petugas Piket', '9900112233');
        $dispen = $this->buatDispensasi($siswa, $piket);

        $this->actingAs($piket);

        $this->get(route('piket.dispensasi.surat', $dispen->id))
            ->assertOk()
            ->assertSee(route('piket.dispensasi.index'), false)
            ->assertDontSee(route('waka-kesiswaan.dispensasi.approval.index'), false);
    }
}
