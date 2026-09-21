<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\IzinGuru;
use App\Models\JadwalPiket;
use App\Models\PengaturanJadwal;
use App\Models\User;
use App\Services\FonnteService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Otomasi Notifikasi WA Tahap "Menunggu Piket":
 *   - Broadcast WA ke seluruh Guru Piket bertugas pada tanggal pengajuan,
 *     lengkap dengan link quick-approve unik (token_piket).
 *   - Quick approve publik: Guru Piket pertama menyetujui -> status maju
 *     (level 3 -> Menunggu Waka SDM) + Waka SDM otomatis diberi tahu.
 *   - Anti double-approval: piket lain yang membuka link sama mendapat
 *     pemberitahuan "sudah diproses oleh piket lain".
 */
class IzinPiketBroadcastQuickApproveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Kunci "hari ini" ke Senin agar jadwal piket (Senin–Jumat) bersifat
        // deterministik terlepas dari kapan suite dijalankan.
        Carbon::setTestNow(Carbon::create(2026, 8, 31)); // Senin, 31 Agustus 2026
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
            'is_active' => true,
            'no_hp' => $noHp,
        ]);
    }

    protected function buatJadwalPiket(User $guru, string $hari = 'Senin'): void
    {
        JadwalPiket::create(['hari' => $hari, 'user_id' => $guru->id]);
    }

    protected function buatIzinPiket(User $guru, string $tanggal = '2026-08-31'): IzinGuru
    {
        return IzinGuru::create([
            'user_id' => $guru->id,
            'tanggal' => $tanggal,
            'alasan' => 'Sakit',
            'kategori_izin' => 'sakit',
            'status' => IzinGuru::STATUS_PENDING_PIKET,
            'is_testing_data' => false,
        ]);
    }

    /**
     * Nyalakan lingkungan non-testing + fake HTTP sehingga sendNotification
     * benar-benar "mengirim" (hermetik testing memotong semua request).
     */
    protected function aktifkanPengirimanWa(): void
    {
        AppSetting::set('fonnte_token', 'test-token');
        Http::fake();
        app()->detectEnvironment(fn () => 'production');
    }

    public function test_pengajuan_izin_menghasilkan_token_piket_dan_url_quick_approve(): void
    {
        $guru = $this->makeUser('guru', 'guru_mapel', '081300000001');
        $piket = $this->makeUser('guru', null, '081234567801');
        $this->buatJadwalPiket($piket);

        $this->actingAs($guru)
            ->post(route('guru.izin.store'), [
                'tanggal' => '2026-08-31',
                'kategori_izin' => 'sakit',
                'alasan' => 'Sakit',
                'lampiran' => null,
                'tugas_siswa' => null,
                'ttd_guru' => null,
            ])
            ->assertRedirect(route('guru.izin.index'));

        $izin = IzinGuru::where('user_id', $guru->id)->firstOrFail();

        $this->assertSame(IzinGuru::STATUS_PENDING_PIKET, $izin->status);
        $this->assertNotNull($izin->token_piket);
        $this->assertNotSame('', (string) $izin->token_piket);
        $this->assertSame(url('/approve-piket/'.$izin->token_piket), $izin->piket_approval_url);
    }

    public function test_token_piket_tidak_valid_menampilkan_tautan_tidak_valid(): void
    {
        $this->get(route('piket.quick-approve.show', 'token-tidak-ada'))
            ->assertOk()
            ->assertSee('Tautan Tidak Valid');
    }

    public function test_daftar_guru_piket_diambil_dari_jadwal_hari_tanggal_izin(): void
    {
        $piketSenin = $this->makeUser('guru', null, '081234567801');
        $piketRabu = $this->makeUser('guru', null, '081234567802');
        $bukanPiket = $this->makeUser('guru');

        $this->buatJadwalPiket($piketSenin, 'Senin');
        $this->buatJadwalPiket($piketRabu, 'Rabu');

        $daftar = FonnteService::guruPiketBertugasTanggal(Carbon::create(2026, 8, 31)); // Senin

        $this->assertTrue($daftar->contains('id', $piketSenin->id));
        $this->assertFalse($daftar->contains('id', $piketRabu->id));
        $this->assertFalse($daftar->contains('id', $bukanPiket->id));

        // Akhir pekan -> tidak ada petugas piket.
        $this->assertTrue(FonnteService::guruPiketBertugasTanggal(Carbon::create(2026, 8, 30))->isEmpty()); // Minggu
    }

    public function test_broadcast_wa_mengirim_ke_seluruh_guru_piket_bertugas_dengan_link_quick_approve(): void
    {
        $pemohon = $this->makeUser('guru', 'guru_mapel', '081300000001');
        $piketA = $this->makeUser('guru', null, '081234567801');
        $piketB = $this->makeUser('guru', null, '081234567802');

        $this->buatJadwalPiket($piketA);
        $this->buatJadwalPiket($piketB);
        // Pemohon kebetulan bertugas piket hari itu -> harus dilewati (anti self-notif).
        $this->buatJadwalPiket($pemohon);

        $izin = $this->buatIzinPiket($pemohon);

        $this->aktifkanPengirimanWa();

        $terkirim = FonnteService::notifyGuruPiketIzinBaru($izin);

        $this->assertSame(2, $terkirim);

        // Setiap piket menerima link quick-approve unik + format pesan sesuai spec.
        Http::assertSent(fn (Request $req) => str_starts_with((string) $req['message'], 'Notifikasi Guru Piket:'));
        Http::assertSent(fn (Request $req) => str_contains((string) $req['message'], $izin->piket_approval_url));
        Http::assertSent(fn (Request $req) => $req['target'] === '6281234567801');
        Http::assertSent(fn (Request $req) => $req['target'] === '6281234567802');
        // Pemohon (yang kebetulan piket) TIDAK menerima notifikasi.
        Http::assertNotSent(fn (Request $req) => $req['target'] === '6281300000001');
    }

    public function test_broadcast_membatalkan_kirim_saat_notifikasi_wa_dinonaktifkan_admin(): void
    {
        $pemohon = $this->makeUser('guru', 'guru_mapel', '081300000001');
        $piketA = $this->makeUser('guru', null, '081234567801');
        $this->buatJadwalPiket($piketA);

        $izin = $this->buatIzinPiket($pemohon);

        $this->aktifkanPengirimanWa();
        FonnteService::setNotificationsEnabled(false);

        $terkirim = FonnteService::notifyGuruPiketIzinBaru($izin);

        $this->assertSame(0, $terkirim);
        Http::assertSentCount(0);
    }

    public function test_quick_approve_memajukan_status_ke_menunggu_waka_dan_mengirim_wa_waka(): void
    {
        PengaturanJadwal::getSetting()->update(['no_wa_waka' => '081234567899']);

        $pemohon = $this->makeUser('guru', 'guru_mapel');
        $piketA = $this->makeUser('guru', null, '081234567801');
        $piketB = $this->makeUser('guru', null, '081234567802');
        $this->buatJadwalPiket($piketA);
        $this->buatJadwalPiket($piketB);

        $izin = $this->buatIzinPiket($pemohon);

        $this->aktifkanPengirimanWa();

        // Halaman siap menyetujui. GET memulai session sehingga csrf_token()
        // valid untuk POST (lingkungan non-testing mewajibkan CSRF).
        $this->get(route('piket.quick-approve.show', $izin->token_piket))
            ->assertOk()
            ->assertSee('Setujui Pengajuan');

        // Guru Piket A menyetujui.
        $this->post(route('piket.quick-approve.submit', $izin->token_piket), [
            'approved_by_piket_id' => $piketA->id,
            '_token' => csrf_token(),
        ])->assertRedirect(route('piket.quick-approve.show', $izin->token_piket));;

        $izin->refresh();

        $this->assertSame(IzinGuru::STATUS_PENDING_WAKA, $izin->status);
        $this->assertSame($piketA->id, (int) $izin->approved_by_piket);

        // Waka SDM otomatis diberi tahu (Tahap 1b via IzinGuru::updated hook).
        Http::assertSent(fn (Request $req) => $req['target'] === '6281234567899'
            && str_contains((string) $req['message'], 'telah diverifikasi oleh Guru Piket'));
    }

    public function test_double_approval_diblokir_untuk_piket_kedua(): void
    {
        $pemohon = $this->makeUser('guru', 'guru_mapel');
        $piketA = $this->makeUser('guru');
        $piketB = $this->makeUser('guru');
        $this->buatJadwalPiket($piketA);
        $this->buatJadwalPiket($piketB);

        $izin = $this->buatIzinPiket($pemohon);

        // Piket A sudah menyetujui (status maju ke Menunggu Waka SDM).
        $izin->update([
            'approved_by_piket' => $piketA->id,
            'status' => IzinGuru::STATUS_PENDING_WAKA,
        ]);

        // Piket B membuka link yang sama -> pemberitahuan sudah diproses.
        $this->get(route('piket.quick-approve.show', $izin->token_piket))
            ->assertOk()
            ->assertSee('sudah diproses oleh Guru Piket lain');

        // Piket B memaksa POST -> ditolak, status tetap tidak berubah.
        $this->post(route('piket.quick-approve.submit', $izin->token_piket), [
            'approved_by_piket_id' => $piketB->id,
        ])->assertSessionHas('error');

        $izin->refresh();

        $this->assertSame(IzinGuru::STATUS_PENDING_WAKA, $izin->status);
        $this->assertSame($piketA->id, (int) $izin->approved_by_piket);
    }

    public function test_self_approval_pemohon_yang_juga_piket_ditolak(): void
    {
        $pemohon = $this->makeUser('guru', 'guru_mapel');
        $this->buatJadwalPiket($pemohon); // pemohon juga piket hari itu

        $izin = $this->buatIzinPiket($pemohon);

        // Halaman menampilkan peringatan self-approval.
        $this->actingAs($pemohon)
            ->get(route('piket.quick-approve.show', $izin->token_piket))
            ->assertOk()
            ->assertSee('pemohon izin ini');

        // POST sebagai pemohon ditolak.
        $this->actingAs($pemohon)
            ->post(route('piket.quick-approve.submit', $izin->token_piket), [
                'approved_by_piket_id' => $pemohon->id,
            ])->assertSessionHas('error');

        $izin->refresh();

        $this->assertSame(IzinGuru::STATUS_PENDING_PIKET, $izin->status);
        $this->assertNull($izin->approved_by_piket);
    }

    public function test_quick_approve_mengikuti_level_approval_yang_dikonfigurasi(): void
    {
        $setting = PengaturanJadwal::getSetting();
        $pemohon = $this->makeUser('guru', 'guru_mapel');
        $piketA = $this->makeUser('guru');
        $this->buatJadwalPiket($piketA);

        // Level 2 -> Menunggu Kepsek.
        $setting->update(['izin_approval_level' => 2]);
        $izin2 = $this->buatIzinPiket($pemohon);
        $this->post(route('piket.quick-approve.submit', $izin2->token_piket), [
            'approved_by_piket_id' => $piketA->id,
        ]);
        $izin2->refresh();
        $this->assertSame(IzinGuru::STATUS_PENDING_KEPSEK, $izin2->status);
        $this->assertSame($piketA->id, (int) $izin2->approved_by_piket);

        // Level 1 -> langsung Disetujui.
        $setting->update(['izin_approval_level' => 1]);
        $izin1 = $this->buatIzinPiket($pemohon);
        $this->post(route('piket.quick-approve.submit', $izin1->token_piket), [
            'approved_by_piket_id' => $piketA->id,
        ]);
        $izin1->refresh();
        $this->assertSame(IzinGuru::STATUS_DISETUJUI, $izin1->status);
        $this->assertNotNull($izin1->approved_at);
    }
}