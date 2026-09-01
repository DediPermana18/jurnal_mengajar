<?php

namespace Tests\Feature;

use App\Models\IzinGuru;
use App\Models\JadwalPiket;
use App\Models\PengaturanJadwal;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IzinGuruApprovalFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Kunci "hari ini" ke Senin agar pengecekan jadwal piket (Senin–Jumat)
        // bersifat deterministik terlepas dari kapan suite dijalankan.
        Carbon::setTestNow(Carbon::create(2026, 8, 31)); // Senin, 31 Agustus 2026
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function makeUser(string $role, ?string $subRole = null, array $extra = []): User
    {
        return User::create(array_merge([
            'nama' => 'User '.Str::random(5),
            'username' => 'user_'.Str::random(8),
            'password' => bcrypt('password'),
            'role' => $role,
            'sub_role' => $subRole,
            'is_active' => true,
        ], $extra));
    }

    protected function makePiketToday(User $guru): void
    {
        // "Senin" dipakai karena isPiketHariIni()/isPetugasPiketHariIni() hanya
        // menghitung jadwal pada hari aktif sekolah (Senin–Jumat), dan setTestNow
        // mengunci hari berjalan ke Senin.
        JadwalPiket::create(['hari' => 'Senin', 'user_id' => $guru->id]);
    }

    public function test_full_three_level_flow_uses_separate_tokens_reaches_approved(): void
    {
        PengaturanJadwal::getSetting()->update(['izin_approval_level' => 3]);

        $guru = $this->makeUser('guru', 'guru_mapel');
        $piket = $this->makeUser('guru', 'guru');
        $this->makePiketToday($piket);

        // Guru submit
        $this->actingAs($guru)
            ->post(route('guru.izin.store'), [
                'tanggal' => now()->toDateString(),
                'alasan' => 'Sakit demam',
                'tugas_siswa' => 'Mengerjakan latihan',
                'ttd_guru' => 'data:image/png;base64,AAAA',
            ])
            ->assertRedirect(route('guru.izin.index'))
            ->assertSessionHas('success');

        $izin = IzinGuru::first();
        $this->assertNotNull($izin);
        $this->assertEquals(IzinGuru::STATUS_PENDING_PIKET, $izin->status);
        $this->assertNotNull($izin->token_waka);
        $this->assertNull($izin->token_kepsek); // token Kepsek belum dirilis
        $this->assertNotNull($izin->ttd_guru);

        // Piket verify -> level 3 -> pending_waka
        $this->actingAs($piket)
            ->post(route('piket.izin.approve', $izin->id))
            ->assertRedirect(route('piket.izin.index'))
            ->assertSessionHas('success');

        $izin->refresh();
        $this->assertEquals(IzinGuru::STATUS_PENDING_WAKA, $izin->status);
        $this->assertNotNull($izin->approved_by_piket);

        // Waka approve via token WAKA
        $this->post(route('izin.approval.submit', $izin->token_waka), [
            'keputusan' => 'setujui',
            'ttd_waka' => 'data:image/png;base64,BBBB',
        ])->assertRedirect(route('izin.approval.show', $izin->token_waka));

        $izin->refresh();
        $this->assertEquals(IzinGuru::STATUS_PENDING_KEPSEK, $izin->status);
        $this->assertNotNull($izin->ttd_waka);
        $this->assertNotNull($izin->token_kepsek); // token Kepsek baru dirilis

        // Setelah Waka menandatangani: halaman token Waka = Sukses Verifikasi Waka,
        // TANPA form apa pun, dan ada tombol teruskan ke Kepala Sekolah.
        $this->get(route('izin.approval.show', $izin->token_waka))
            ->assertOk()
            ->assertSee('Sukses Verifikasi Waka Kurikulum')
            ->assertSee($izin->kepsek_approval_url)
            ->assertDontSee('id="formApproval"');

        // Kepsek approve final via token Khusus Kepala Sekolah
        $this->post(route('izin.approval.submit', $izin->token_kepsek), [
            'keputusan' => 'setujui',
            'ttd_kepsek' => 'data:image/png;base64,CCCC',
        ])->assertRedirect(route('izin.approval.show', $izin->token_kepsek));

        $izin->refresh();
        $this->assertEquals(IzinGuru::STATUS_DISETUJUI, $izin->status);
        $this->assertNotNull($izin->ttd_kepsek);
        $this->assertNotNull($izin->approved_at);
        $this->assertTrue($izin->isApproved());
    }

    public function test_waka_token_page_never_shows_kepsek_form(): void
    {
        PengaturanJadwal::getSetting()->update(['izin_approval_level' => 3]);

        $guru = $this->makeUser('guru', 'guru_mapel');

        $izin = IzinGuru::create([
            'user_id' => $guru->id,
            'tanggal' => now()->toDateString(),
            'alasan' => 'Izin sakit',
            'status' => IzinGuru::STATUS_PENDING_WAKA,
            'ttd_guru' => 'data:image/png;base64,AAAA',
            'token_waka' => (string) Str::uuid(),
        ]);

        // Halaman token Waka: HANYA form Waka (heading Kepala Sekolah tidak muncul).
        $this->get(route('izin.approval.show', $izin->token_waka))
            ->assertOk()
            ->assertSee('Tanda Tangan Waka Kurikulum')
            ->assertDontSee('Tanda Tangan & Persetujuan Kepala Sekolah');

        // Tidak ada token Kepsek sebelum tahap Waka selesai.
        $this->assertNull($izin->fresh()->token_kepsek);
    }

    public function test_kepsek_form_hidden_while_waka_step_incomplete_even_with_leaked_token_in_level_three(): void
    {
        PengaturanJadwal::getSetting()->update(['izin_approval_level' => 3]);

        $guru = $this->makeUser('guru', 'guru_mapel');

        $izin = IzinGuru::create([
            'user_id' => $guru->id,
            'tanggal' => now()->toDateString(),
            'alasan' => 'Izin sakit',
            'status' => IzinGuru::STATUS_PENDING_WAKA,
            'ttd_guru' => 'data:image/png;base64,AAAA',
            'token_waka' => (string) Str::uuid(),
        ]);

        // Simulasikan "bocor": token Kepsek dibuat prematur, tapi Waka belum menandatangani.
        $izin->update(['token_kepsek' => (string) Str::uuid()]);

        // Form Kepala Sekolah TIDAK boleh muncul selama status masih Pending Waka.
        $this->get(route('izin.approval.show', $izin->token_kepsek))
            ->assertOk()
            ->assertSee('Masih Menunggu Langkah Sebelumnya')
            ->assertDontSee('Tanda Tangan & Persetujuan Kepala Sekolah')
            ->assertDontSee('Tanda Tangan Waka Kurikulum');

        // Submit dari token Kepsek saat Pending Waka pun ditolak.
        $this->post(route('izin.approval.submit', $izin->token_kepsek), [
            'keputusan' => 'setujui',
            'ttd_kepsek' => 'data:image/png;base64,ABCD',
        ])->assertSessionHas('error');

        $izin->refresh();
        $this->assertEquals(IzinGuru::STATUS_PENDING_WAKA, $izin->status);
        $this->assertNull($izin->ttd_kepsek);

        // Setelah Waka menandatangani (status Pending Kepsek), form Kepsek aktif.
        $izin->update([
            'status' => IzinGuru::STATUS_PENDING_KEPSEK,
            'ttd_waka' => 'data:image/png;base64,BBBB',
        ]);

        $this->get(route('izin.approval.show', $izin->token_kepsek))
            ->assertOk()
            ->assertSee('Tanda Tangan & Persetujuan Kepala Sekolah', false)
            ->assertDontSee('Tanda Tangan Waka Kurikulum');
    }

    public function test_level_two_skips_waka(): void
    {
        PengaturanJadwal::getSetting()->update(['izin_approval_level' => 2]);

        $guru = $this->makeUser('guru', 'guru_mapel');
        $piket = $this->makeUser('guru', 'guru');
        $this->makePiketToday($piket);

        $izin = IzinGuru::create([
            'user_id' => $guru->id,
            'tanggal' => now()->toDateString(),
            'alasan' => 'Izin dinas',
            'status' => IzinGuru::STATUS_PENDING_PIKET,
            'token_waka' => (string) Str::uuid(),
        ]);

        $this->actingAs($piket)->post(route('piket.izin.approve', $izin->id));
        $izin->refresh();
        $this->assertEquals(IzinGuru::STATUS_PENDING_KEPSEK, $izin->status);
        $this->assertNotNull($izin->token_kepsek);

        $this->post(route('izin.approval.submit', $izin->token_kepsek), [
            'keputusan' => 'setujui',
            'ttd_kepsek' => 'data:image/png;base64,DDDD',
        ]);
        $izin->refresh();
        $this->assertEquals(IzinGuru::STATUS_DISETUJUI, $izin->status);
    }

    public function test_level_one_piket_finalizes_directly(): void
    {
        PengaturanJadwal::getSetting()->update(['izin_approval_level' => 1]);

        $guru = $this->makeUser('guru', 'guru_mapel');
        $piket = $this->makeUser('guru', 'guru');
        $this->makePiketToday($piket);

        $izin = IzinGuru::create([
            'user_id' => $guru->id,
            'tanggal' => now()->toDateString(),
            'alasan' => 'Izin keluarga',
            'status' => IzinGuru::STATUS_PENDING_PIKET,
            'token_waka' => (string) Str::uuid(),
        ]);

        $this->actingAs($piket)->post(route('piket.izin.approve', $izin->id));
        $izin->refresh();
        $this->assertEquals(IzinGuru::STATUS_DISETUJUI, $izin->status);
        $this->assertNotNull($izin->approved_at);
    }

    public function test_rejection_from_public_step(): void
    {
        PengaturanJadwal::getSetting()->update(['izin_approval_level' => 3]);

        $guru = $this->makeUser('guru', 'guru_mapel');
        $piket = $this->makeUser('guru', 'guru');
        $this->makePiketToday($piket);

        $izin = IzinGuru::create([
            'user_id' => $guru->id,
            'tanggal' => now()->toDateString(),
            'alasan' => 'Izin sakit',
            'status' => IzinGuru::STATUS_PENDING_WAKA,
            'token_waka' => (string) Str::uuid(),
        ]);

        $this->post(route('izin.approval.submit', $izin->token_waka), [
            'keputusan' => 'tolak',
            'catatan_penolakan' => 'Bukti kurang',
        ]);
        $izin->refresh();
        $this->assertEquals(IzinGuru::STATUS_DITOLAK, $izin->status);
        $this->assertSame('Bukti kurang', $izin->catatan_penolakan);
    }

    public function test_public_approval_requires_kepsek_signature(): void
    {
        PengaturanJadwal::getSetting()->update(['izin_approval_level' => 3]);

        $guru = $this->makeUser('guru', 'guru_mapel');

        $izin = IzinGuru::create([
            'user_id' => $guru->id,
            'tanggal' => now()->toDateString(),
            'alasan' => 'Izin',
            'status' => IzinGuru::STATUS_PENDING_KEPSEK,
            'ttd_waka' => 'data:image/png;base64,BBBB',
            'token_waka' => (string) Str::uuid(),
            'token_kepsek' => (string) Str::uuid(),
        ]);

        $this->post(route('izin.approval.submit', $izin->token_kepsek), [
            'keputusan' => 'setujui', // no ttd_kepsek
        ])->assertSessionHas('error');

        $izin->refresh();
        $this->assertEquals(IzinGuru::STATUS_PENDING_KEPSEK, $izin->status);
    }

    public function test_waka_approval_requires_waka_signature_and_does_not_advance(): void
    {
        PengaturanJadwal::getSetting()->update(['izin_approval_level' => 3]);

        $guru = $this->makeUser('guru', 'guru_mapel');

        $izin = IzinGuru::create([
            'user_id' => $guru->id,
            'tanggal' => now()->toDateString(),
            'alasan' => 'Izin sakit',
            'status' => IzinGuru::STATUS_PENDING_WAKA,
            'ttd_guru' => 'data:image/png;base64,AAAA',
            'token_waka' => (string) Str::uuid(),
        ]);

        // Submit tanpa tanda tangan Waka -> ditolak server-side, status tidak maju.
        $this->post(route('izin.approval.submit', $izin->token_waka), [
            'keputusan' => 'setujui',
        ])->assertSessionHas('error');

        $izin->refresh();
        $this->assertEquals(IzinGuru::STATUS_PENDING_WAKA, $izin->status);
        $this->assertNull($izin->ttd_waka);

        // Dengan ttd_waka valid -> maju ke Pending Kepsek.
        $this->post(route('izin.approval.submit', $izin->token_waka), [
            'keputusan' => 'setujui',
            'ttd_waka' => 'data:image/png;base64,BBBB',
        ])->assertRedirect(route('izin.approval.show', $izin->token_waka));

        $izin->refresh();
        $this->assertEquals(IzinGuru::STATUS_PENDING_KEPSEK, $izin->status);
        $this->assertNotNull($izin->ttd_waka);
        $this->assertNotNull($izin->token_kepsek);
    }

    public function test_waka_cannot_execute_on_pending_piket_and_only_sees_info(): void
    {
        PengaturanJadwal::getSetting()->update(['izin_approval_level' => 3]);

        $admin = $this->makeUser('admin', 'waka_kurikulum');
        $guru = $this->makeUser('guru', 'guru_mapel');

        // Pengajuan masih menunggu verifikasi Piket
        $izin = IzinGuru::create([
            'user_id' => $guru->id,
            'tanggal' => now()->toDateString(),
            'alasan' => 'Izin sakit',
            'status' => IzinGuru::STATUS_PENDING_PIKET,
            'token_waka' => (string) Str::uuid(),
        ]);

        // Halaman Waka: TIDAK memunculkan tombol Setujui/Tolak, tapi badge menunggu verifikasi piket
        $this->actingAs($admin)->get(route('kurikulum.izin.index'))
            ->assertOk()
            ->assertSee('Menunggu Verifikasi Piket')
            ->assertDontSee('Setujui');

        // Server-side: Waka tidak boleh approve / reject izin yang sedang Pending Piket
        $this->actingAs($admin)
            ->post(route('kurikulum.izin.approve', $izin->id))
            ->assertStatus(422);

        $izin->refresh();
        $this->assertEquals(IzinGuru::STATUS_PENDING_PIKET, $izin->status);

        $this->actingAs($admin)
            ->post(route('kurikulum.izin.reject', $izin->id), ['catatan_penolakan' => 'Tidak valid'])
            ->assertStatus(422);

        $izin->refresh();
        $this->assertEquals(IzinGuru::STATUS_PENDING_PIKET, $izin->status);
    }

    public function test_piket_approve_updates_status_to_pending_waka_for_waka(): void
    {
        PengaturanJadwal::getSetting()->update(['izin_approval_level' => 3]);

        $guru = $this->makeUser('guru', 'guru_mapel');
        $piket = $this->makeUser('guru', 'guru');
        $this->makePiketToday($piket);

        $izin = IzinGuru::create([
            'user_id' => $guru->id,
            'tanggal' => now()->toDateString(),
            'alasan' => 'Izin sakit',
            'status' => IzinGuru::STATUS_PENDING_PIKET,
            'token_waka' => (string) Str::uuid(),
        ]);

        // Guru Piket menyetujui -> status otomatis menjadi Pending Waka
        $this->actingAs($piket)->post(route('piket.izin.approve', $izin->id))->assertRedirect();

        $izin->refresh();
        $this->assertEquals(IzinGuru::STATUS_PENDING_WAKA, $izin->status);
    }

    public function test_all_pages_render_without_errors(): void
    {
        $setting = PengaturanJadwal::getSetting()
            ->update(['izin_approval_level' => 3, 'no_wa_waka' => '081234567890', 'no_wa_kepsek' => '081298765432']);

        $admin = $this->makeUser('admin', null, ['sub_role' => 'waka_kurikulum']);
        $guru = $this->makeUser('guru', 'guru_mapel');
        $piket = $this->makeUser('guru', 'guru');
        $this->makePiketToday($piket);

        $izin = IzinGuru::create([
            'user_id' => $guru->id,
            'tanggal' => now()->toDateString(),
            'alasan' => 'Izin kuliah',
            'tugas_siswa' => 'Kerjakan latihan',
            'status' => IzinGuru::STATUS_PENDING_WAKA,
            'ttd_guru' => 'data:image/png;base64,AAAA',
            'token_waka' => (string) Str::uuid(),
        ]);

        // Guru pages
        $this->actingAs($guru)->get(route('guru.izin.index'))->assertOk();
        $this->actingAs($guru)->get(route('guru.izin.create'))->assertOk();
        $this->actingAs($guru)->get(route('guru.izin.show', $izin->id))->assertOk();

        // Piket page (level 3, pending_waka -> tampilkan tombol Kirim WA)
        $this->actingAs($piket)->get(route('piket.izin.index'))->assertOk();

        // Kurikulum index & setting
        $this->actingAs($admin)->get(route('kurikulum.izin.index'))
            ->assertOk()
            ->assertSee('Kirim WA ke Waka')
            ->assertSee('6281234567890'); // no_wa_waka normalisasi
        $this->actingAs($admin)->get(route('kurikulum.izin.setting'))->assertOk();

        // Public approval: token Waka saat Pending Waka
        $this->get(route('izin.approval.show', $izin->token_waka))->assertOk();

        // Masuk tahap Kepsek: token Kepsek otomatis dirilis
        $izin->update(['status' => IzinGuru::STATUS_PENDING_KEPSEK]);
        $izin->refresh();
        $this->assertNotNull($izin->token_kepsek);
        $this->get(route('izin.approval.show', $izin->token_kepsek))->assertOk();
        $this->get(route('izin.approval.show', $izin->token_waka))->assertOk(); // waka_done

        // Final disetujui
        $izin->update(['status' => IzinGuru::STATUS_DISETUJUI, 'ttd_kepsek' => 'data:image/png;base64,ZZZZ', 'approved_at' => now()]);
        $this->get(route('izin.approval.show', $izin->token_kepsek))->assertOk();
        $this->get(route('izin.approval.show', $izin->token_waka))->assertOk();
    }
}
