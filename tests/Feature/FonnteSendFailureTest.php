<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\DispensasiSiswa;
use App\Models\JadwalPiket;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use App\Services\FonnteService;
use App\Support\WaSendResult;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regresi kegagalan notifikasi WhatsApp (Fonnte).
 *
 * BUG YANG DIPERBAIKI: `FonnteService::sendNotification()` dulu hanya
 * memeriksa kode HTTP. Fonnte membalas **HTTP 200** bahkan saat gagal secara
 * bisnis, contoh nyata hasil uji ke api.fonnte.com:
 *
 *     HTTP 200  {"reason":"invalid token","status":false}
 *
 * Akibatnya aplikasi menampilkan alert "Notifikasi WhatsApp berhasil
 * dikirim" padahal tidak ada pesan yang sampai ke Waka Kesiswaan.
 *
 * Test di bawah mengunci perilaku baru: sukses hanya bila body JSON
 * mengonfirmasi `status: true`, dan setiap kegagalan menyertakan alasan Fonnte
 * ke UI.
 */
class FonnteSendFailureTest extends TestCase
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

    protected function makeUser(string $role = 'guru', ?string $subRole = 'guru', ?string $noHp = null): User
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

    protected function buatSiswa(): Siswa
    {
        $kelas = Kelas::create(['nama_kelas' => 'RPL 1', 'tingkat' => 'XI']);

        return Siswa::create([
            'nama' => 'Siswa Uji Fonnte',
            'nisn' => '77665599',
            'nis' => '776699',
            'jenis_kelamin' => 'L',
            'id_kelas' => $kelas->id,
            'status_siswa' => 'Aktif',
        ]);
    }

    /**
     * Aktifkan pengiriman WA nyata: token disimulasikan, HTTP di-fake, dan
     * environment dipaksa 'production' agar guard hermetik terlewati.
     * Middleware anti-CSRF ikut dinonaktifkan karena environment production.
     */
    protected function aktifkanPengirimanWa(): void
    {
        AppSetting::set('fonnte_token', 'test-token');
        app()->detectEnvironment(fn () => 'production');

        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    // ================================================================
    // 1. Kontrak respons Fonnte: HTTP 200 BUKAN berarti sukses
    // ================================================================

    public function test_status_false_dengan_http_200_didalam_gagal_bukan_sukses(): void
    {
        $this->aktifkanPengirimanWa();
        $this->fakeFonnteGagal('invalid token');

        $hasil = FonnteService::send('6281234567890', 'Pesan uji');

        $this->assertFalse($hasil->ok, 'HTTP 200 + status:false harus dianggap GAGAL.');
        $this->assertSame(200, $hasil->httpStatus);
        $this->assertSame('invalid token', $hasil->reason);

        // API lama (bool) ikut mengembalikan false — sumber bug "alert sukses palsu".
        $this->assertFalse(FonnteService::sendNotification('6281234567890', 'Pesan uji'));
    }

    public function test_body_kosong_pada_http_200_tidak_diperlakukan_sebagai_sukses(): void
    {
        $this->aktifkanPengirimanWa();
        $this->fakeFonnte(''); // 200 tanpa body — tidak ada bukti terkirim

        $hasil = FonnteService::send('6281234567890', 'Pesan uji');

        $this->assertFalse($hasil->ok);
        $this->assertStringContainsString('tidak dapat dipastikan', (string) $hasil->reason);
    }

    public function test_kunci_status_kapital_besar_untuk_sukses_dibaca(): void
    {
        // Dokumentasi Fonnte tidak konsisten kapitalisasi kunci: contoh
        // kegagalan resminya menulis "Status" (kapital) sementara contoh
        // keberhasilan menulis "status". Keduanya harus dikenali.
        $this->aktifkanPengirimanWa();
        $this->fakeFonnte(['Status' => true, 'detail' => 'success! message in queue']);

        $this->assertTrue(FonnteService::send('6281234567890', 'Pesan')->ok);
    }

    public function test_kunci_status_kapital_besar_untuk_gagal_dibaca(): void
    {
        $this->aktifkanPengirimanWa();
        $this->fakeFonnte(['Status' => false, 'reason' => 'token invalid']);

        $this->assertFalse(FonnteService::send('6281234567890', 'Pesan')->ok);
    }

    public function test_respons_mentah_fonnte_tercatat_di_log(): void
    {
        Log::spy();

        $this->aktifkanPengirimanWa();
        $this->fakeFonnteGagal('insufficient quota');

        FonnteService::send('6281234567890', 'Pesan uji');

        Log::shouldHaveReceived('info')
            ->withArgs(fn (string $message, array $context = []) => str_contains($message, 'Fonnte WA respons API')
                && ($context['body']['reason'] ?? null) === 'insufficient quota')
            ->once();
    }

    public function test_gagal_berserta_alasan_dicatat_sebagai_warning(): void
    {
        Log::spy();

        $this->aktifkanPengirimanWa();
        $this->fakeFonnteGagal('device not connected');

        FonnteService::send('6281234567890', 'Pesan uji');

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message) => str_contains($message, 'device not connected'))
            ->once();
    }

    // ================================================================
    // 2. Sanitasi & validasi nomor tujuan
    // ================================================================

    public function test_nomor_tujuan_disanitasi_menjadi_format_62(): void
    {
        $this->assertSame('6281234567890', FonnteService::normalizeTarget('+62 812-3456-7890'));
        $this->assertSame('6281234567890', FonnteService::normalizeTarget('0812 3456 7890'));
        $this->assertSame('6281234567890', FonnteService::normalizeTarget('81234567890'));
        $this->assertSame('6281234567890', FonnteService::normalizeTarget('(0812) 3456-7890'));
        $this->assertSame('', FonnteService::normalizeTarget('  -- '));
        $this->assertSame('', FonnteService::normalizeTarget(null));

        $this->assertTrue(FonnteService::isValidTarget('6281234567890'));
        $this->assertFalse(FonnteService::isValidTarget('081234567890'));  // belum 62
        $this->assertFalse(FonnteService::isValidTarget('6212'));         // terlalu pendek
        $this->assertFalse(FonnteService::isValidTarget('62000000000')); // semua nol
        $this->assertFalse(FonnteService::isValidTarget('62abc'));
    }

    public function test_nomor_kotor_dikirim_ke_fonnte_sudah_ternormalisasi(): void
    {
        $this->aktifkanPengirimanWa();
        $this->fakeFonnte();

        FonnteService::send('+62 812-3456-7890', 'Pesan uji');

        Http::assertSent(fn (Request $req) => $req['target'] === '6281234567890');
    }

    public function test_nomor_tidak_valid_tidak_pernah_mengirim_request_ke_fonnte(): void
    {
        $this->aktifkanPengirimanWa();
        $this->fakeFonnte();

        $hasil = FonnteService::send('12345', 'Pesan uji');

        // Menolak di sisi aplikasi: hemat kuota & hindari "target invalid".
        Http::assertNothingSent();
        $this->assertFalse($hasil->ok);
        $this->assertStringContainsString('62xxxxxxxxx', (string) $hasil->reason);
    }

    public function test_pesan_kosong_tidak_mengirim(): void
    {
        $this->aktifkanPengirimanWa();
        $this->fakeFonnte();

        $hasil = FonnteService::send('6281234567890', '   ');

        Http::assertNothingSent();
        $this->assertFalse($hasil->ok);
    }

    public function test_pesan_dengan_url_dan_karakter_khusus_dibersihkan_sebelum_kirim_ke_fonnte(): void
    {
        $this->aktifkanPengirimanWa();
        $this->fakeFonnte();

        $pesanAsli = "PENGAJUAN APPROVAL DISPENSASI SISWA\n\nhttps://webjournal.test/dispen/approve/abc123\nSilakan cek {link} dan buka https://webjournal.test/approve\n";

        FonnteService::send('6281234567890', $pesanAsli);

        Http::assertSent(fn (Request $req) => $req['message'] === FonnteService::bersihkanPesan($pesanAsli));
    }

    public function test_token_kosong_ditolak_sebelum_request(): void
    {
        $this->aktifkanPengirimanWa();
        $this->fakeFonnte();

        // Kosongkan token di DB maupun .env agar `FonnteService::token()`
        // benar-benar kosong (AppSetting::set('') menghapus barisnya).
        AppSetting::set('fonnte_token', null);
        config(['services.fonnte.token' => '']);

        $hasil = FonnteService::send('6281234567890', 'Pesan uji');

        Http::assertNothingSent();
        $this->assertFalse($hasil->ok);
        $this->assertStringContainsString('token Fonnte belum dikonfigurasi', (string) $hasil->reason);
    }

    // ================================================================
    // 3. Alasan kegagalan ditampilkan ke operator (tidak ada alert sukses palsu)
    // ================================================================

    public function test_alasan_fonnte_dipetakan_ke_petunjuk_perbaikan(): void
    {
        $peta = [
            // reason dari API Fonnte            => potongan pesan yang harus tampil
            'token invalid' => 'Token Fonnte tidak valid',
            'invalid token' => 'Token Fonnte tidak valid',
            'insufficient quota' => 'Kuota Fonnte habis',
            'device not connected' => 'terputus',
            'target invalid' => 'Nomor tujuan ditolak Fonnte',
            'input invalid' => 'Parameter pengiriman ditolak Fonnte',
            'some brand new reason' => 'Fonnte menolak: some brand new reason',
        ];

        foreach ($peta as $reason => $harusTampil) {
            // httpStatus terisi -> alasan dianggap berasal dari respons Fonnte.
            $hasil = WaSendResult::gagal('6281234567890', $reason, 200, ['reason' => $reason]);

            $this->assertStringContainsString(
                $harusTampil,
                $hasil->pesan(),
                'Pesan untuk reason "'.$reason.'" tidak memuat petunjuk yang diharapkan.'
            );
        }
    }

    public function test_alasan_diblokir_sebelum_request_tidak_dituding_ke_fonnte(): void
    {
        // httpStatus null = tidak pernah ada respons Fonnte (pemeriksaan lokal),
        // jadi reason cukup ditampilkan apa adanya tanpa awalan "Fonnte menolak".
        $hasil = WaSendResult::gagal('6212', 'nomor tujuan tidak valid untuk Indonesia (harus 62xxxxxxxxx).');

        $this->assertSame(
            'nomor tujuan tidak valid untuk Indonesia (harus 62xxxxxxxxx).',
            $hasil->pesan()
        );
    }

    public function test_kirim_ulang_gagal_menampilkan_alasan_fonnte_bukan_alert_sukses(): void
    {
        $piket = $this->makeUser('guru', 'guru', '081300000001');
        JadwalPiket::create(['hari' => 'Senin', 'user_id' => $piket->id]);
        $siswa = $this->buatSiswa();
        $this->makeUser('admin', 'waka_kesiswaan', '081234567890');

        $dispen = DispensasiSiswa::create([
            'id_siswa' => $siswa->id,
            'id_guru_piket' => $piket->id,
            'tanggal' => now()->toDateString(),
            'tipe_dispen' => DispensasiSiswa::TIPE_KELUAR,
            'jam_ke' => '3',
            'alasan' => 'Urusan sekolah',
            'status' => DispensasiSiswa::STATUS_DISETUJUI,
            'approval_token' => 'token-fail-1',
        ]);

        $this->aktifkanPengirimanWa();
        $this->fakeFonnteGagal('insufficient quota');

        $this->actingAs($piket)
            ->from(route('piket.dispensasi.index'))
            ->post(route('piket.dispensasi.kirim-wa', $dispen->id))
            ->assertRedirect(route('piket.dispensasi.index'))
            ->assertSessionMissing('success')
            ->assertSessionHas('error', fn (string $pesan) => str_contains($pesan, 'GAGAL')
                && str_contains($pesan, 'insufficient quota'));
    }

    public function test_pembuatan_surat_tidak_tertunding_berkala_gagal_fonnte(): void
    {
        $piket = $this->makeUser('guru', 'guru', '081300000001');
        JadwalPiket::create(['hari' => 'Senin', 'user_id' => $piket->id]);
        $siswa = $this->buatSiswa();
        $this->makeUser('admin', 'waka_kesiswaan', '081234567890');

        $this->aktifkanPengirimanWa();
        $this->fakeFonnteGagal('token invalid');

        // Gagal WA tidak boleh membatalkan pembuatan surat.
        $this->actingAs($piket)
            ->post(route('piket.dispensasi.store'), [
                'tanggal' => '2026-09-14',
                'id_siswa' => [$siswa->id],
                'jam_ke' => ['3', '4'],
                'jam_keluar_jp' => '4',
                'alasan' => 'Mengikuti lomba',
                'ttd_guru' => 'data:image/png;base64,GURU_PIKET',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, DispensasiSiswa::count());
    }

    public function test_tes_kirim_halaman_it_menampilkan_alasan_fonnte(): void
    {
        $it = $this->makeUser('admin', 'petugas_it', '081300000009');

        $this->aktifkanPengirimanWa();
        $this->fakeFonnteGagal('token invalid');

        $this->actingAs($it)
            ->from(route('it.settings.wa'))
            ->post(route('it.settings.wa.test'), [
                'no_hp' => '081234567890',
                'pesan' => 'Tes integrasi Fonnte',
            ])
            ->assertRedirect(route('it.settings.wa'))
            ->assertSessionMissing('success')
            ->assertSessionHas('error', fn (string $pesan) => str_contains($pesan, 'Token Fonnte tidak valid'));
    }

    public function test_tes_kirim_halaman_it_berhasil_menampilkan_bukti_antrean(): void
    {
        $it = $this->makeUser('admin', 'petugas_it', '081300000009');

        $this->aktifkanPengirimanWa();
        $this->fakeFonnte();

        $this->actingAs($it)
            ->from(route('it.settings.wa'))
            ->post(route('it.settings.wa.test'), [
                'no_hp' => '081234567890',
                'pesan' => 'Tes integrasi Fonnte',
            ])
            ->assertRedirect(route('it.settings.wa'))
            ->assertSessionHas('success', fn (string $pesan) => str_contains($pesan, 'berhasil dikirim')
                && str_contains($pesan, '6281234567890')
                && str_contains($pesan, 'requestid'));
    }
}
