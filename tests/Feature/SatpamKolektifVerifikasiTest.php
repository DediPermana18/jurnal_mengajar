<?php

namespace Tests\Feature;

use App\Models\DispensasiKolektif;
use App\Models\DispensasiSiswa;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SatpamKolektifVerifikasiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 8, 10)); // Senin
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

    protected function makeSatpam(): User
    {
        return $this->makeUser('admin', 'satpam');
    }

    protected function buatSiswa(string $nama): Siswa
    {
        $kelas = Kelas::create(['nama_kelas' => 'X IPA 1', 'tingkat' => 'X']);

        return Siswa::create([
            'nama' => $nama,
            'nisn' => Str::random(10),
            'nis' => Str::random(5),
            'jenis_kelamin' => 'L',
            'id_kelas' => $kelas->id,
            'status_siswa' => 'Aktif',
        ]);
    }

    protected function buatRombongan(User $piket, array $siswaList, array $extra = []): array
    {
        $kolektif = DispensasiKolektif::create(array_merge([
            'id_guru_piket' => $piket->id,
            'tanggal' => now()->toDateString(),
            'tipe_dispen' => DispensasiSiswa::TIPE_KELUAR,
            'jam_ke' => '5,6',
            'jam_keluar_jp' => 5,
            'jam_kembali_jp' => 6,
            'tidak_kembali_hari_ini' => false,
            'alasan' => 'Mengikuti lomba akademik',
            'status' => DispensasiSiswa::STATUS_DISETUJUI,
            'approved_at' => now(),
            'approved_by' => $piket->id,
            'ttd_guru' => 'data:image/png;base64,TEST_GURU',
            'approval_token' => 'kolektif-'.Str::uuid(),
        ], $extra));

        $items = collect();

        foreach ($siswaList as $siswa) {
            $items->push(DispensasiSiswa::create([
                'dispensasi_kolektif_id' => $kolektif->id,
                'id_siswa' => $siswa->id,
                'id_guru_piket' => $piket->id,
                'tanggal' => now()->toDateString(),
                'tipe_dispen' => DispensasiSiswa::TIPE_KELUAR,
                'jam_ke' => '5,6',
                'jam_keluar_jp' => 5,
                'jam_kembali_jp' => 6,
                'tidak_kembali_hari_ini' => false,
                'alasan' => 'Mengikuti lomba akademik',
                'status' => DispensasiSiswa::STATUS_DISETUJUI,
                'approved_at' => now(),
                'approved_by' => $piket->id,
                'ttd_guru' => 'data:image/png;base64,TEST_GURU',
                'ttd_siswa' => 'data:image/png;base64,TEST_SISWA',
                'ttd_waka' => 'data:image/png;base64,TEST_WAKA',
                'approval_token' => 'item-'.Str::uuid(),
            ]));
        }

        return [$kolektif, $items];
    }

    public function test_verifikasi_scan_qr_kolektif_menampilkan_checklist_dan_pilih_semua(): void
    {
        $satpam = $this->makeSatpam();
        $piket = $this->makeUser('guru');

        $azizah = $this->buatSiswa('Azizah Rahma');
        $bimo = $this->buatSiswa('Bimo Saputra');
        $citra = $this->buatSiswa('Citra Lestari');

        [$kolektif] = $this->buatRombongan($piket, [$azizah, $bimo, $citra]);

        $linkQr = route('dispen.approval.show', $kolektif->approval_token);

        $this->actingAs($satpam)
            ->get(route('satpam.verifikasi', ['q' => $linkQr]))
            ->assertOk()
            ->assertSee('KOLEKTIF / ROMBONGAN')
            ->assertSee('Dispensasi Rombongan / Kolektif')
            ->assertSee('3 siswa siap diizinkan keluar gerbang')
            ->assertSee('Konfirmasi Keluar Gerbang (Rombongan)')
            ->assertSee('Pilih Semua')
            ->assertSee('Azizah Rahma')
            ->assertSee('Bimo Saputra')
            ->assertSee('Citra Lestari');
    }

    public function test_kolektif_keluar_memperbarui_hanya_siswa_yang_dicentang(): void
    {
        $satpam = $this->makeSatpam();
        $piket = $this->makeUser('guru');

        $siswa = [
            $this->buatSiswa('Azizah Rahma'),
            $this->buatSiswa('Bimo Saputra'),
            $this->buatSiswa('Citra Lestari'),
        ];

        [$kolektif, $items] = $this->buatRombongan($piket, $siswa);

        // Satpam hanya mencentang siswa 1 & 3 (Bimo tidak ikut keluar).
        $this->actingAs($satpam)
            ->post(route('satpam.kolektif.keluar', $kolektif), [
                'dispen_ids' => [$items[0]->id, $items[2]->id],
            ])
            ->assertRedirect(route('satpam.dispensasi.index', ['q' => $kolektif->approval_token]))
            ->assertSessionHas('success');

        $this->assertNotNull($items[0]->fresh()->keluar_gerbang_at);
        $this->assertEquals(DispensasiSiswa::STATUS_KELUAR, $items[0]->fresh()->status);
        $this->assertEquals($satpam->id, $items[0]->fresh()->keluar_gerbang_by);

        $this->assertNotNull($items[2]->fresh()->keluar_gerbang_at);

        $this->assertNull($items[1]->fresh()->keluar_gerbang_at);
        $this->assertEquals(DispensasiSiswa::STATUS_DISETUJUI, $items[1]->fresh()->status);
    }

    public function test_alur_gerbang_rombongan_keluar_lalu_kembali(): void
    {
        $satpam = $this->makeSatpam();
        $piket = $this->makeUser('guru');

        $siswa = [
            $this->buatSiswa('Azizah Rahma'),
            $this->buatSiswa('Bimo Saputra'),
        ];

        [$kolektif, $items] = $this->buatRombongan($piket, $siswa);

        // Tahap 1: Satpam izinkan keduanya keluar gerbang.
        $this->actingAs($satpam)
            ->post(route('satpam.kolektif.keluar', $kolektif), [
                'dispen_ids' => [$items[0]->id, $items[1]->id],
            ])
            ->assertSessionHas('success');

        // Tahap 2: Surat di-scan ulang -> kini menunggu konfirmasi kembali.
        $this->actingAs($satpam)
            ->get(route('satpam.verifikasi', ['q' => $kolektif->approval_token]))
            ->assertOk()
            ->assertSee('2 siswa menunggu konfirmasi kembali')
            ->assertSee('Konfirmasi Kembali ke Sekolah (Rombongan)')
            ->assertDontSee('Konfirmasi Keluar Gerbang (Rombongan)');

        // Tahap 3: Hanya Azizah yang sudah kembali; Bimo belum.
        $this->actingAs($satpam)
            ->post(route('satpam.kolektif.kembali', $kolektif), [
                'dispen_ids' => [$items[0]->id],
            ])
            ->assertRedirect(route('satpam.dispensasi.index', ['q' => $kolektif->approval_token]))
            ->assertSessionHas('success');

        $this->assertNotNull($items[0]->fresh()->kembali_at);
        $this->assertEquals($satpam->id, $items[0]->fresh()->kembali_by);
        $this->assertNull($items[1]->fresh()->kembali_at);

        // Tahap 4: Scan lagi -> Azizah sudah kembali, Bimo masih menunggu.
        $this->actingAs($satpam)
            ->get(route('satpam.verifikasi', ['q' => $kolektif->approval_token]))
            ->assertOk()
            ->assertSee('1 siswa menunggu konfirmasi kembali')
            ->assertSee('Konfirmasi Kembali ke Sekolah (Rombongan)')
            ->assertSee('Sudah kembali')
            ->assertSee('Azizah Rahma')
            ->assertSee('Bimo Saputra');
    }

    public function test_kolektif_keluar_tanpa_centang_ditolak_validasi(): void
    {
        $satpam = $this->makeSatpam();
        $piket = $this->makeUser('guru');

        [$kolektif, $items] = $this->buatRombongan($piket, [$this->buatSiswa('Azizah Rahma')]);

        $this->actingAs($satpam)
            ->post(route('satpam.kolektif.keluar', $kolektif), [
                'dispen_ids' => [],
            ])
            ->assertSessionHasErrors('dispen_ids');

        $this->assertNull($items[0]->fresh()->keluar_gerbang_at);
    }
}
