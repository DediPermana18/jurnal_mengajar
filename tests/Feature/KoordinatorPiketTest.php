<?php

namespace Tests\Feature;

use App\Models\JadwalPiket;
use App\Models\RekapPiketHarian;
use App\Models\StatusKehadiranGuru;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Portal Koordinator Piket — menu dinamis & panel shift (tugas dari jadwal,
 * bukan role tetap di database).
 *
 *  - Helper/middleware: guru yang hari ini tercatat sebagai koordinator_pagi /
 *    koordinator_siang pada jadwal_piket mendapat akses; selain itu 403
 *    (Petugas IT tetap diizinkan sebagai peninjau).
 *  - Sidebar Guru/Wali Kelas: seksi kondisional "TUGAS TAMBAHAN" berisi menu
 *    "Kelola Piket Shift (Koordinator)" hanya bila bertugas koordinator hari ini.
 *  - Panel /koordinator/piket: daftar anggota shift, monitoring kehadiran guru
 *    anggota shift, dan penyusunan Rekap Piket Shift (catatan_pagi/catatan_siang
 *    pada rekap_piket_harian) yang dikirim ke Waka Piket untuk divalidasi.
 */
class KoordinatorPiketTest extends TestCase
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

    /**
     * Jadwal piket Senin: koordinator pagi/siang + satu anggota per shift.
     * Format SK (legacy): baris koordinator berisi koordinator_*_user_id,
     * baris anggota berisi petugas_*_user_id.
     *
     * @return array{koordinatorPagi: User, koordinatorSiang: User, anggotaPagi: User, anggotaSiang: User}
     */
    protected function buatJadwalSenin(): array
    {
        $koordinatorPagi = $this->makeUser('guru', null, '081234567811');
        $koordinatorSiang = $this->makeUser('guru', null, '081234567812');
        $anggotaPagi = $this->makeUser('guru', null, '081234567813');
        $anggotaSiang = $this->makeUser('guru', null, '081234567814');

        JadwalPiket::create([
            'hari' => 'Senin',
            'user_id' => $koordinatorPagi->id,
            'koordinator_pagi_user_id' => $koordinatorPagi->id,
        ]);
        JadwalPiket::create([
            'hari' => 'Senin',
            'user_id' => $koordinatorSiang->id,
            'koordinator_siang_user_id' => $koordinatorSiang->id,
        ]);
        JadwalPiket::create([
            'hari' => 'Senin',
            'user_id' => $anggotaPagi->id,
            'petugas_pagi_user_id' => $anggotaPagi->id,
        ]);
        JadwalPiket::create([
            'hari' => 'Senin',
            'user_id' => $anggotaSiang->id,
            'petugas_siang_user_id' => $anggotaSiang->id,
        ]);

        return compact('koordinatorPagi', 'koordinatorSiang', 'anggotaPagi', 'anggotaSiang');
    }

    public function test_panel_terbuka_untuk_koordinator_pagi_dengan_menu_tugas_tambahan(): void
    {
        ['koordinatorPagi' => $koordinatorPagi, 'anggotaPagi' => $anggotaPagi, 'anggotaSiang' => $anggotaSiang] = $this->buatJadwalSenin();

        $res = $this->actingAs($koordinatorPagi)->get(route('koordinator.piket'));

        $res->assertOk();
        $res->assertSee('Kelola Piket Shift (Koordinator)');
        $res->assertSee('Shift Pagi');
        // Anggota shift pagi tampil; anggota shift siang tidak ikut.
        $res->assertSee($anggotaPagi->nama);
        $res->assertDontSee($anggotaSiang->nama);
        // Secundary check: sidebar seksi TUGAS TAMBAHAN muncul di halaman mana pun.
        $res->assertSee('TUGAS TAMBAHAN');
        $res->assertSee('Rekap Piket Harian 2026-08-31');
    }

    public function test_panel_terbuka_untuk_koordinator_siang(): void
    {
        ['koordinatorSiang' => $koordinatorSiang, 'anggotaPagi' => $anggotaPagi, 'anggotaSiang' => $anggotaSiang] = $this->buatJadwalSenin();

        $res = $this->actingAs($koordinatorSiang)->get(route('koordinator.piket'));

        $res->assertOk();
        $res->assertSee('Shift Siang');
        $res->assertSee($anggotaSiang->nama);
        $res->assertDontSee($anggotaPagi->nama);
    }

    public function test_sidebar_tugas_tambahan_tampil_hanya_untuk_koordinator(): void
    {
        ['koordinatorPagi' => $koordinatorPagi] = $this->buatJadwalSenin();

        // Koordinator: seksi TUGAS TAMBAHAN + menu Kelola Piket Shift tampil.
        $this->actingAs($koordinatorPagi)
            ->get(route('guru.dashboard'))
            ->assertOk()
            ->assertSee('TUGAS TAMBAHAN')
            ->assertSee('Kelola Piket Shift (Koordinator)');

        // Guru biasa tanpa jadwal koordinator: menu tidak tampil.
        $guruBiasa = $this->makeUser('guru', 'guru_mapel');

        $this->actingAs($guruBiasa)
            ->get(route('guru.dashboard'))
            ->assertOk()
            ->assertDontSee('TUGAS TAMBAHAN')
            ->assertDontSee('Kelola Piket Shift (Koordinator)');
    }

    public function test_panel_ditolak_untuk_guru_non_koordinator(): void
    {
        ['anggotaPagi' => $anggotaPagi] = $this->buatJadwalSenin();

        // Guru tanpa jadwal sama sekali.
        $guruBiasa = $this->makeUser('guru', 'guru_mapel');
        $this->actingAs($guruBiasa)
            ->get(route('koordinator.piket'))
            ->assertStatus(403);

        // Petugas piket biasa (anggota shift) — bukan koordinator.
        $this->actingAs($anggotaPagi)
            ->get(route('koordinator.piket'))
            ->assertStatus(403);
    }

    public function test_panel_dapat_dibuka_petugas_it(): void
    {
        $this->buatJadwalSenin();
        $itUser = $this->makeUser('petugas_it');

        // Petugas IT (peninjau) diizinkan; tanpa jadwal -> empty state informatif.
        $this->actingAs($itUser)
            ->get(route('koordinator.piket'))
            ->assertOk()
            ->assertSee('Tidak ada shift yang dipimpin pada hari ini');
    }

    public function test_monitoring_kehadiran_anggota_shift_tersimpan(): void
    {
        ['koordinatorPagi' => $koordinatorPagi, 'anggotaPagi' => $anggotaPagi] = $this->buatJadwalSenin();

        $this->actingAs($koordinatorPagi)
            ->post(route('koordinator.piket.kehadiran'), [
                'tanggal' => '2026-08-31',
                'shift' => 'pagi',
                'status' => [
                    $anggotaPagi->id => StatusKehadiranGuru::STATUS_SAKIT,
                ],
                'keterangan' => [
                    $anggotaPagi->id => 'Demam, sudah melapor Waka.',
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $record = StatusKehadiranGuru::where('user_id', $anggotaPagi->id)
            ->whereDate('tanggal', '2026-08-31')
            ->firstOrFail();

        $this->assertSame(StatusKehadiranGuru::STATUS_SAKIT, $record->status);
        $this->assertSame('Demam, sudah melapor Waka.', $record->keterangan);
        $this->assertSame($koordinatorPagi->id, (int) $record->updated_by);
        $this->assertFalse((bool) $record->is_testing_data);
    }

    public function test_monitoring_kehadiran_ditolak_untuk_non_koordinator(): void
    {
        ['anggotaPagi' => $anggotaPagi, 'anggotaSiang' => $anggotaSiang] = $this->buatJadwalSenin();

        // Anggota shift (bukan koordinator) tidak boleh mengubah kehadiran.
        $this->actingAs($anggotaPagi)
            ->post(route('koordinator.piket.kehadiran'), [
                'tanggal' => '2026-08-31',
                'shift' => 'pagi',
                'status' => [$anggotaPagi->id => StatusKehadiranGuru::STATUS_SAKIT],
            ])
            ->assertStatus(403);

        $this->assertSame(
            0,
            StatusKehadiranGuru::where('user_id', $anggotaPagi->id)->count()
        );
    }

    public function test_monitoring_kehadiran_menolak_guru_di_luar_anggota_shift(): void
    {
        ['koordinatorPagi' => $koordinatorPagi, 'anggotaSiang' => $anggotaSiang] = $this->buatJadwalSenin();

        // Koordinator pagi mencoba mengubah anggota shift siang -> 422.
        $this->actingAs($koordinatorPagi)
            ->post(route('koordinator.piket.kehadiran'), [
                'tanggal' => '2026-08-31',
                'shift' => 'pagi',
                'status' => [
                    $anggotaSiang->id => StatusKehadiranGuru::STATUS_IZIN,
                ],
            ])
            ->assertStatus(422);

        $this->assertSame(
            0,
            StatusKehadiranGuru::where('user_id', $anggotaSiang->id)->count()
        );
    }

    public function test_rekap_shift_dikirim_ke_waka_piket_satu_baris_per_tanggal(): void
    {
        ['koordinatorPagi' => $koordinatorPagi, 'koordinatorSiang' => $koordinatorSiang] = $this->buatJadwalSenin();

        // Koordinator pagi mengirim catatan shift pagi.
        $this->actingAs($koordinatorPagi)
            ->post(route('koordinator.piket.rekap'), [
                'tanggal' => '2026-08-31',
                'shift' => 'pagi',
                'catatan' => 'Shift pagi lancar; satu kelas kosong jam 1.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        // Koordinator siang mengirim catatan shift siang pada tanggal yang sama.
        $this->actingAs($koordinatorSiang)
            ->post(route('koordinator.piket.rekap'), [
                'tanggal' => '2026-08-31',
                'shift' => 'siang',
                'catatan' => 'Shift siang: pemadaman listrik 13.00.',
            ])
            ->assertSessionHas('success');

        // Satu baris rekap untuk tanggal tersebut (unique), kedua shift terisi.
        $this->assertSame(
            1,
            RekapPiketHarian::whereDate('tanggal', '2026-08-31')->count()
        );

        $rekap = RekapPiketHarian::whereDate('tanggal', '2026-08-31')->firstOrFail();
        $this->assertSame(RekapPiketHarian::STATUS_DRAFT, $rekap->status);
        $this->assertSame('Shift pagi lancar; satu kelas kosong jam 1.', $rekap->catatan_pagi);
        $this->assertSame('Shift siang: pemadaman listrik 13.00.', $rekap->catatan_siang);
        $this->assertSame($koordinatorPagi->id, (int) $rekap->koordinator_pagi_user_id);
        $this->assertSame($koordinatorSiang->id, (int) $rekap->koordinator_siang_user_id);
        $this->assertFalse((bool) $rekap->is_testing_data);
    }

    public function test_rekap_shift_ditolak_bagi_shift_yang_tidak_dipimpin(): void
    {
        ['koordinatorPagi' => $koordinatorPagi] = $this->buatJadwalSenin();

        // Koordinator pagi tidak berhak mengisi catatan shift siang.
        $this->actingAs($koordinatorPagi)
            ->post(route('koordinator.piket.rekap'), [
                'tanggal' => '2026-08-31',
                'shift' => 'siang',
                'catatan' => 'Catatan milik shift siang.',
            ])
            ->assertStatus(403);

        $this->assertSame(0, RekapPiketHarian::count());
    }

    public function test_rekap_terkunci_setelah_divalidasi_waka_piket(): void
    {
        ['koordinatorPagi' => $koordinatorPagi] = $this->buatJadwalSenin();
        $waka = $this->makeUser('admin', 'waka_piket');

        // Waka Piket memvalidasi rekap hari ini.
        $this->actingAs($waka)
            ->post(route('waka-piket.validasi'), ['tanggal' => '2026-08-31'])
            ->assertRedirect(route('waka-piket.rekap-harian', ['tanggal' => '2026-08-31']));

        // Koordinator tidak dapat lagi mengirim rekap (dokumen terkunci).
        $this->actingAs($koordinatorPagi)
            ->post(route('koordinator.piket.rekap'), [
                'tanggal' => '2026-08-31',
                'shift' => 'pagi',
                'catatan' => 'Revisi setelah validasi.',
            ])
            ->assertStatus(422);
    }

    public function test_panel_menampilkan_status_validasi_rekap(): void
    {
        ['koordinatorPagi' => $koordinatorPagi] = $this->buatJadwalSenin();

        // Sebelum Waka memvalidasi: panel menampilkan status Draft.
        $this->actingAs($koordinatorPagi)
            ->get(route('koordinator.piket'))
            ->assertOk()
            ->assertSee('Belum Ada');

        $waka = $this->makeUser('admin', 'waka_piket');
        $this->actingAs($waka)
            ->post(route('waka-piket.validasi'), ['tanggal' => '2026-08-31'])
            ->assertSessionHas('success');

        // Setelah validasi: panel menampilkan status Tervalidasi + pesan terkunci.
        $this->actingAs($koordinatorPagi)
            ->get(route('koordinator.piket'))
            ->assertOk()
            ->assertSee('Tervalidasi')
            ->assertSee('sudah divalidasi Waka Piket dan tidak dapat diubah lagi');
    }
}
