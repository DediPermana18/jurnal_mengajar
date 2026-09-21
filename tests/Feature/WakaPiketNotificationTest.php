<?php

namespace Tests\Feature;

use App\Http\Controllers\UserController;
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
 * Integrasi Notifikasi WA dengan Guru Piket Dinamis + Sub-Role Waka Piket:
 *   - Sub-role 'waka_piket' tersedia di dropdown Sub-Role user & valid saat store.
 *   - JadwalPiket::getGuruPiketHariIni() -> daftar guru piket dinamis per hari.
 *   - Broadcast WA tahap "Menunggu Piket" mencakup Guru Piket bertugas + Waka Piket.
 *   - Fallback fail-safe: tanpa jadwal piket -> teruskan langsung ke Waka SDM/Waka Piket.
 *   - Quick approve dapat dilakukan oleh Waka Piket.
 */
class WakaPiketNotificationTest extends TestCase
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
     * Nyalakan lingkungan non-testing + fake HTTP agar sendNotification benar-
     * benar "mengirim" (hermetik testing memotong semua request).
     */
    protected function aktifkanPengirimanWa(): void
    {
        AppSetting::set('fonnte_token', 'test-token');
        Http::fake();
        app()->detectEnvironment(fn () => 'production');
    }

    public function test_sub_role_waka_piket_tersedia_di_form_dan_valid_saat_store(): void
    {
        $admin = $this->makeUser('admin', 'petugas_tu');

        // Opsi "Waka Piket" hadir pada dropdown form Tambah User.
        $this->actingAs($admin)
            ->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee('Waka Piket');

        // Submit user baru dengan sub-role waka_piket diterima (validasi lulus).
        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Waka Piket Baru',
                'username' => 'waka_piket_'.Str::random(6),
                'nip' => null,
                'no_hp' => '081234567888',
                'sub_role' => 'waka_piket',
            ])->assertRedirect(route('admin.users.index'));

        $wakaPiket = User::where('sub_role', 'waka_piket')->firstOrFail();

        $this->assertSame('admin', $wakaPiket->role);
        $this->assertSame('081234567888', $wakaPiket->no_hp);
        $this->assertSame('Waka Piket', $wakaPiket->role_label);
        $this->assertTrue($wakaPiket->isWakaPiket());

        // Terdaftar sebagai konstanta daftar sub-role yang valid.
        $this->assertContains('waka_piket', UserController::SUB_ROLES);
    }

    public function test_get_guru_piket_hari_ini_mengambil_petugas_dari_jadwal(): void
    {
        $piketA = $this->makeUser('guru');
        $piketRabu = $this->makeUser('guru');
        $bukanPiket = $this->makeUser('guru');

        $this->buatJadwalPiket($piketA, 'Senin');
        $this->buatJadwalPiket($piketRabu, 'Rabu');

        // Hari ini (Senin ter-kunci setTestNow) -> hanya piket Senin.
        $daftar = JadwalPiket::getGuruPiketHariIni();

        $this->assertTrue($daftar->contains('id', $piketA->id));
        $this->assertFalse($daftar->contains('id', $piketRabu->id));
        $this->assertFalse($daftar->contains('id', $bukanPiket->id));

        // Parameter tanggal lain (Rabu) ikut dihormati.
        $daftarRabu = JadwalPiket::getGuruPiketHariIni(Carbon::create(2026, 9, 2)); // Rabu
        $this->assertTrue($daftarRabu->contains('id', $piketRabu->id));

        // Akhir pekan -> kosong.
        $this->assertTrue(JadwalPiket::getGuruPiketHariIni(Carbon::create(2026, 8, 30))->isEmpty()); // Minggu
    }

    public function test_broadcast_wa_mencakup_guru_piket_dan_waka_piket(): void
    {
        $pemohon = $this->makeUser('guru', 'guru_mapel', '081300000001');
        $piketA = $this->makeUser('guru', null, '081234567801');
        $wakaPiket = $this->makeUser('admin', 'waka_piket', '081234567888');

        $this->buatJadwalPiket($piketA);
        $izin = $this->buatIzinPiket($pemohon);

        $this->aktifkanPengirimanWa();

        $terkirim = FonnteService::notifyGuruPiketIzinBaru($izin);

        $this->assertSame(2, $terkirim);
        Http::assertSent(fn (Request $req) => str_contains((string) $req['message'], $izin->piket_approval_url));
        Http::assertSent(fn (Request $req) => $req['target'] === '6281234567801'); // Guru Piket
        Http::assertSent(fn (Request $req) => $req['target'] === '6281234567888'); // Waka Piket
    }

    public function test_fallback_wawa_tanpa_jadwal_piket_diteruskan_ke_waka_sdm_dan_waka_piket(): void
    {
        PengaturanJadwal::getSetting()->update(['no_wa_waka' => '081234567899']);

        $pemohon = $this->makeUser('guru', 'guru_mapel', '081300000001');
        $wakaPiket = $this->makeUser('admin', 'waka_piket', '081234567888');

        // TIDAK ada jadwal piket pada tanggal pengajuan (fallback fail-safe).
        $izin = $this->buatIzinPiket($pemohon);

        $this->aktifkanPengirimanWa();

        $terkirim = FonnteService::notifyGuruPiketIzinBaru($izin);

        $this->assertSame(2, $terkirim);
        Http::assertSent(fn (Request $req) => $req['target'] === '6281234567899'); // Waka SDM (fallback)
        Http::assertSent(fn (Request $req) => $req['target'] === '6281234567888'); // Waka Piket
        Http::assertSent(fn (Request $req) => str_contains((string) $req['message'], $izin->piket_approval_url));
    }

    public function test_fallback_tanpa_jadwal_dan_tanpa_wa_apa_pun_tidak_mengirim(): void
    {
        $pemohon = $this->makeUser('guru', 'guru_mapel', '081300000001');

        // Tanpa jadwal piket, tanpa waka piket, tanpa setting no_wa_waka.
        $izin = $this->buatIzinPiket($pemohon);

        $this->aktifkanPengirimanWa();

        $terkirim = FonnteService::notifyGuruPiketIzinBaru($izin);

        $this->assertSame(0, $terkirim);
        Http::assertSentCount(0);
    }

    public function test_quick_approve_oleh_waka_piket_memajukan_status(): void
    {
        $pemohon = $this->makeUser('guru', 'guru_mapel');
        $wakaPiket = $this->makeUser('admin', 'waka_piket');

        $izin = $this->buatIzinPiket($pemohon);

        $this->aktifkanPengirimanWa();

        // Waka Piket login -> terdeteksi sebagai Petugas Piket / Waka Piket.
        $this->actingAs($wakaPiket)
            ->get(route('piket.quick-approve.show', $izin->token_piket))
            ->assertOk()
            ->assertSee('Petugas Piket / Waka Piket')
            ->assertSee('Setujui Pengajuan');

        // Waka Piket menyetujui via tautan (layout login mengunci petugas aktif).
        $this->actingAs($wakaPiket)
            ->post(route('piket.quick-approve.submit', $izin->token_piket), [
                'approved_by_piket_id' => $wakaPiket->id,
                '_token' => csrf_token(),
            ])->assertRedirect(route('piket.quick-approve.show', $izin->token_piket));

        $izin->refresh();

        $this->assertSame(IzinGuru::STATUS_PENDING_WAKA, $izin->status);
        $this->assertSame($wakaPiket->id, (int) $izin->approved_by_piket);
    }

    public function test_quick_approve_waka_sdm_fallback_ketika_tidak_ada_jadwal_piket(): void
    {
        $pemohon = $this->makeUser('guru', 'guru_mapel');
        $wakaSdm = $this->makeUser('admin', 'waka_sdm');

        // Tidak ada jadwal piket hari itu -> Waka SDM menjadi verifikator pengganti.
        $izin = $this->buatIzinPiket($pemohon);

        $this->get(route('piket.quick-approve.show', $izin->token_piket))
            ->assertOk()
            ->assertSee('Setujui Pengajuan');

        $this->post(route('piket.quick-approve.submit', $izin->token_piket), [
            'approved_by_piket_id' => $wakaSdm->id,
        ])->assertRedirect(route('piket.quick-approve.show', $izin->token_piket));

        $izin->refresh();

        $this->assertSame(IzinGuru::STATUS_PENDING_WAKA, $izin->status);
        $this->assertSame($wakaSdm->id, (int) $izin->approved_by_piket);
    }

    public function test_broadcast_wa_mencakup_koordinator_piket_hari_tersebut(): void
    {
        $pemohon = $this->makeUser('guru', 'guru_mapel', '081300000001');
        $petugasA = $this->makeUser('guru', null, '081234567801');
        $koordinatorPagi = $this->makeUser('guru', null, '081234567825');

        // Baris jadwal hari itu: user_id = petugas, koordinator_pagi_user_id
        // menunjuk guru BEDA yang tidak punya baris user_id sendiri.
        JadwalPiket::create([
            'hari' => 'Senin',
            'user_id' => $petugasA->id,
            'koordinator_pagi_user_id' => $koordinatorPagi->id,
        ]);

        $izin = $this->buatIzinPiket($pemohon);

        $this->aktifkanPengirimanWa();

        $terkirim = FonnteService::notifyGuruPiketIzinBaru($izin);

        // Petugas piket + Koordinator Piket sama-sama menerima notifikasi.
        $this->assertSame(2, $terkirim);
        Http::assertSent(fn (Request $req) => $req['target'] === '6281234567801'); // Guru Piket
        Http::assertSent(fn (Request $req) => $req['target'] === '6281234567825'); // Koordinator Pagi
        Http::assertSent(fn (Request $req) => str_contains((string) $req['message'], $izin->piket_approval_url));
    }
}