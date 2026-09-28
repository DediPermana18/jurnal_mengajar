<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\DispensasiKolektif;
use App\Models\DispensasiSiswa;
use App\Models\JadwalPiket;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use App\Services\DispensasiWaService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Notifikasi WA otomatis ke Waka Kesiswaan saat Guru Piket membuat surat
 * dispensasi baru (DispensasiSiswaObserver) + tombol kirim ulang manual.
 */
class DispensasiWaNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 9, 14)); // Senin
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function makeUser(string $role, ?string $subRole = 'guru', ?string $noHp = null): User
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

    protected function buatKelasDanSiswa(int $jumlah = 2): array
    {
        $kelas = Kelas::create(['nama_kelas' => 'RPL 1', 'tingkat' => 'XI']);

        $siswa = collect();
        foreach (range(1, $jumlah) as $i) {
            $siswa->push(Siswa::create([
                'nama' => "Siswa Dispen {$i}",
                'nisn' => '77665500'.sprintf('%02d', $i),
                'nis' => '7766'.sprintf('%02d', $i),
                'jenis_kelamin' => 'L',
                'id_kelas' => $kelas->id,
                'status_siswa' => 'Aktif',
            ]));
        }

        return [$kelas, $siswa];
    }

    /**
     * Aktifkan pengiriman WA sungguhan (di-locally): token Fonnte disimulasikan,
     * HTTP di-fake dengan body JSON realistis, dan environment dipaksa
     * 'production' agar guard hermetik FonnteService::send() tidak memotong
     * request.
     *
     * Body JSON WAJIB berisi `status` karena Fonnte membalas HTTP 200 walau
     * gagal secara bisnis (token invalid, device terputus, target tidak
     * valid, kuota habis) — respons itulah yang menentukan hasil kirim.
     *
     * Konsekuensi environment 'production': middleware anti-CSRF (yang biasanya
     * dilewati otomatis pada unit test) ikut aktif, jadi dinonaktifkan eksplisit.
     */
    protected function aktifkanPengirimanWa(): void
    {
        AppSetting::set('fonnte_token', 'test-token');
        $this->fakeFonnte();
        app()->detectEnvironment(fn () => 'production');

        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_disponsasi_baru_otomatis_mengirim_wa_ke_waka_kesiswaan(): void
    {
        $piket = $this->makeUser('guru', 'guru', '081300000001');
        JadwalPiket::create(['hari' => 'Senin', 'user_id' => $piket->id]);
        [$kelas, $siswa] = $this->buatKelasDanSiswa(1);
        $this->makeUser('admin', 'waka_kesiswaan', '081234567890');

        $this->aktifkanPengirimanWa();

        $this->actingAs($piket)
            ->post(route('piket.dispensasi.store'), [
                'tanggal' => '2026-09-14',
                'id_siswa' => [$siswa[0]->id],
                'jam_ke' => ['3', '4'],
                'jam_keluar_jp' => '4',
                'alasan' => 'Mengikuti lomba Paskibraka',
                'ttd_guru' => 'data:image/png;base64,GURU_PIKET',
            ])
            ->assertSessionHasNoErrors();

        $dispen = DispensasiSiswa::firstOrFail();

        // Nomor WA Waka Kesiswaan dinormalisasi ke format country code 62.
        Http::assertSent(fn (Request $req) => $req['target'] === '6281234567890');

        // Pesan memuat rincian dispensasi - link approval DIHAPUS untuk Free package Fonnte.
        Http::assertSent(function (Request $req) use ($dispen, $siswa) {
            $pesan = (string) $req['message'];

            return str_contains($pesan, 'PENGAJUAN APPROVAL DISPENSASI SISWA')
                && str_contains($pesan, $siswa[0]->nama)
                && str_contains($pesan, 'XI RPL 1')
                && str_contains($pesan, 'Jam 3 - 4')
                && str_contains($pesan, '14 September 2026')
                && str_contains($pesan, 'Mengikuti lomba Paskibraka')
                // Link approval DIHAPUS dari payload pesan (Fonnte free package).
                && ! str_contains($pesan, $dispen->approval_url);
        });
    }

    public function test_direct_link_approval_menggunakan_base_url_aplikasi(): void
    {
        config(['app.url' => 'https://jurnal.sekolah.test']);

        $piket = $this->makeUser('guru');
        [$kelas, $siswa] = $this->buatKelasDanSiswa(1);

        $dispen = DispensasiSiswa::create([
            'id_siswa' => $siswa[0]->id,
            'id_guru_piket' => $piket->id,
            'tanggal' => now()->toDateString(),
            'tipe_dispen' => DispensasiSiswa::TIPE_KELUAR,
            'jam_ke' => '3',
            'alasan' => 'Keperluan keluarga',
            'status' => DispensasiSiswa::STATUS_DISETUJUI,
            'approval_token' => 'token-abc-123',
        ]);

        $this->assertSame(
            'https://jurnal.sekolah.test/dispen/approve/token-abc-123',
            $dispen->approval_url
        );
    }

    public function test_tombol_kirim_ulang_mengirim_wa_lagi_dan_memberi_flash_sukses(): void
    {
        $piket = $this->makeUser('guru', 'guru', '081300000001');
        JadwalPiket::create(['hari' => 'Senin', 'user_id' => $piket->id]);
        [$kelas, $siswa] = $this->buatKelasDanSiswa(1);
        $this->makeUser('admin', 'waka_kesiswaan', '0812 3456 7890');

        $dispen = DispensasiSiswa::create([
            'id_siswa' => $siswa[0]->id,
            'id_guru_piket' => $piket->id,
            'tanggal' => now()->toDateString(),
            'tipe_dispen' => DispensasiSiswa::TIPE_KELUAR,
            'jam_ke' => '3',
            'alasan' => 'Urusan sekolah',
            'status' => DispensasiSiswa::STATUS_DISETUJUI,
            'approval_token' => 'token-resend-1',
        ]);

        $this->aktifkanPengirimanWa();

        $this->actingAs($piket)
            ->from(route('piket.dispensasi.index'))
            ->post(route('piket.dispensasi.kirim-wa', $dispen->id))
            ->assertRedirect(route('piket.dispensasi.index'))
            ->assertSessionHas('success');

        Http::assertSent(fn (Request $req) => $req['target'] === '6281234567890'
            && str_contains((string) $req['message'], 'Silakan login ke WebJournal System untuk meninjau dan melakukan persetujuan secara langsung.')
            && ! str_contains((string) $req['message'], $dispen->approval_url));
    }

    public function test_kirim_ulang_ditolak_untuk_guru_biasa(): void
    {
        $piket = $this->makeUser('guru');
        [$kelas, $siswa] = $this->buatKelasDanSiswa(1);

        $dispen = DispensasiSiswa::create([
            'id_siswa' => $siswa[0]->id,
            'id_guru_piket' => $piket->id,
            'tanggal' => now()->toDateString(),
            'tipe_dispen' => DispensasiSiswa::TIPE_KELUAR,
            'jam_ke' => '3',
            'alasan' => 'Urusan sekolah',
            'status' => DispensasiSiswa::STATUS_DISETUJUI,
            'approval_token' => 'token-ditolak-1',
        ]);

        // Guru lain yang tidak bertugas piket & bukan pembuat surat -> 403.
        $this->actingAs($this->makeUser('guru'))
            ->post(route('piket.dispensasi.kirim-wa', $dispen->id))
            ->assertForbidden();
    }

    public function test_nomor_hp_waka_kesiswaan_kosong_tidak_mengirim_dan_mencatat_peringatan(): void
    {
        Log::spy();

        $piket = $this->makeUser('guru', 'guru', '081300000001');
        JadwalPiket::create(['hari' => 'Senin', 'user_id' => $piket->id]);
        [$kelas, $siswa] = $this->buatKelasDanSiswa(1);

        // Akun Waka Kesiswaan ada, tetapi nomor HP belum diisi.
        $this->makeUser('admin', 'waka_kesiswaan', null);

        $this->aktifkanPengirimanWa();

        $this->actingAs($piket)
            ->post(route('piket.dispensasi.store'), [
                'tanggal' => '2026-09-14',
                'id_siswa' => [$siswa[0]->id],
                'jam_ke' => ['3'],
                'alasan' => 'Keperluan keluarga',
                'ttd_guru' => 'data:image/png;base64,GURU_PIKET',
            ])
            ->assertSessionHasNoErrors();

        // Surat tetap tersimpan (notifikasi tidak boleh memblokir alur).
        $this->assertSame(1, DispensasiSiswa::count());
        Http::assertNothingSent();

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message) => str_contains($message, 'Waka Kesiswaan'));
    }

    public function test_nomor_hp_tidak_valid_disisi_dan_dilarang_dikirim(): void
    {
        $this->assertSame('628123456789', DispensasiWaService::normalizeNoWa('0812-3456-789'));
        $this->assertSame('628123456789', DispensasiWaService::normalizeNoWa('+62 812 3456 789'));
        $this->assertSame('628123456789', DispensasiWaService::normalizeNoWa('8123456789'));
        $this->assertSame('', DispensasiWaService::normalizeNoWa('  '));

        $this->assertTrue(DispensasiWaService::isValidNoWa('628123456789'));
        $this->assertFalse(DispensasiWaService::isValidNoWa('0812345678'));   // belum 62
        $this->assertFalse(DispensasiWaService::isValidNoWa('6212'));          // terlalu pendek
        $this->assertFalse(DispensasiWaService::isValidNoWa('62000000000'));  // semua nol
        $this->assertFalse(DispensasiWaService::isValidNoWa(''));
    }

    public function test_dispen_kolektif_mengirim_satu_pesan_untuk_sekalian_seluruh_siswa(): void
    {
        $piket = $this->makeUser('guru', 'guru', '081300000001');
        JadwalPiket::create(['hari' => 'Senin', 'user_id' => $piket->id]);
        [$kelas, $siswa] = $this->buatKelasDanSiswa(3);
        $this->makeUser('admin', 'waka_kesiswaan', '081234567890');

        $this->aktifkanPengirimanWa();

        $this->actingAs($piket)
            ->post(route('piket.dispensasi.store'), [
                'tanggal' => '2026-09-14',
                'id_siswa' => [$siswa[0]->id, $siswa[1]->id, $siswa[2]->id],
                'jam_ke' => ['5'],
                'jam_keluar_jp' => '5',
                'alasan' => 'Kegiatan Strength Building',
                'ttd_guru' => 'data:image/png;base64,GURU_PIKET',
                'ttd_siswa' => [
                    'data:image/png;base64,TTD1',
                    'data:image/png;base64,TTD2',
                    'data:image/png;base64,TTD3',
                ],
            ])
            ->assertSessionHasNoErrors();

        $kolektif = DispensasiKolektif::firstOrFail();

        // Tepat satu pesan (satu rombongan), bukan satu per siswa.
        Http::assertSentCount(1);
        Http::assertSent(function (Request $req) use ($kolektif, $siswa) {
            $pesan = (string) $req['message'];

            return $req['target'] === '6281234567890'
                && str_contains($pesan, 'Silakan login ke WebJournal System untuk meninjau dan melakukan persetujuan secara langsung.')
                && str_contains($pesan, $siswa[0]->nama)
                && str_contains($pesan, $siswa[2]->nama);
        });
    }

    public function test_dispen_masuk_kelas_tidak_mengirim_wa_ke_waka(): void
    {
        $piket = $this->makeUser('guru', 'guru', '081300000001');
        JadwalPiket::create(['hari' => 'Senin', 'user_id' => $piket->id]);
        [$kelas, $siswa] = $this->buatKelasDanSiswa(1);
        $this->makeUser('admin', 'waka_kesiswaan', '081234567890');

        $this->aktifkanPengirimanWa();

        $this->actingAs($piket)
            ->post(route('piket.dispensasi.store'), [
                'tanggal' => '2026-09-14',
                'id_siswa' => [$siswa[0]->id],
                'tipe_dispen' => DispensasiSiswa::TIPE_MASUK,
                'jam_masuk_jp' => '3',
                'alasan_kategori' => 'Kurang sehat',
                'alasan_detail' => 'Demam',
                'ttd_guru' => 'data:image/png;base64,GURU_PIKET',
            ])
            ->assertSessionHasNoErrors();

        // Surat izin masuk kelas tidak melalui alur TTD Waka Kesiswaan.
        Http::assertNothingSent();
    }

    public function test_halaman_daftar_menampilkan_tombol_kirim_ulang_wa(): void
    {
        $piket = $this->makeUser('guru', 'guru', '081300000001');
        JadwalPiket::create(['hari' => 'Senin', 'user_id' => $piket->id]);
        [$kelas, $siswa] = $this->buatKelasDanSiswa(1);

        $dispen = DispensasiSiswa::create([
            'id_siswa' => $siswa[0]->id,
            'id_guru_piket' => $piket->id,
            'tanggal' => now()->toDateString(),
            'tipe_dispen' => DispensasiSiswa::TIPE_KELUAR,
            'jam_ke' => '3',
            'alasan' => 'Urusan sekolah',
            'status' => DispensasiSiswa::STATUS_DISETUJUI,
            'approval_token' => 'token-tombol-1',
        ]);

        $this->actingAs($piket)
            ->get(route('piket.dispensasi.index'))
            ->assertOk()
            ->assertSee('WA ke Waka (Kirim Ulang)')
            ->assertSee(route('piket.dispensasi.kirim-wa', $dispen->id), false);
    }

    public function test_dropdown_aksi_tidak_terpotong_oleh_overflow_x_tabel(): void
    {
        $piket = $this->makeUser('guru', 'guru', '081300000001');
        JadwalPiket::create(['hari' => 'Senin', 'user_id' => $piket->id]);
        [$kelas, $siswa] = $this->buatKelasDanSiswa(1);

        DispensasiSiswa::create([
            'id_siswa' => $siswa[0]->id,
            'id_guru_piket' => $piket->id,
            'tanggal' => now()->toDateString(),
            'tipe_dispen' => DispensasiSiswa::TIPE_KELUAR,
            'jam_ke' => '3',
            'alasan' => 'Urusan sekolah',
            'status' => DispensasiSiswa::STATUS_DISETUJUI,
            'approval_token' => 'token-dropdown-1',
        ]);

        $response = $this->actingAs($piket)->get(route('piket.dispensasi.index'))->assertOk();

        // Wrapper tabel memang scroll horizontal (that's why menu ikut ter-clip).
        $response->assertSee('table-responsive w-full overflow-x-auto', false);

        // Dropdown dikonfigurasi keluar dari area scroll: Popper strategy "fixed"
        // (position: fixed, tidak ter-clip ancestor overflow) + boundary viewport.
        $response->assertSee('data-bs-config=', false);

        preg_match('/data-bs-config="([^"]*)"/', $response->getContent(), $m);

        $this->assertNotEmpty($m, 'Tombol dropdown aksi tidak memuat data-bs-config.');

        // Nilai atribut (setelah entity di-decode browser) harus jadi JSON valid.
        $config = json_decode(html_entity_decode($m[1], ENT_QUOTES), true);

        $this->assertIsArray($config, 'data-bs-config bukan JSON valid: '.$m[1]);
        $this->assertSame('fixed', $config['popperConfig']['strategy'] ?? null);
        $this->assertSame('viewport', $config['boundary'] ?? null);
    }
}
