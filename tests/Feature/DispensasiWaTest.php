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

class DispensasiWaTest extends TestCase
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
            'nama'      => 'User ' . Str::random(5),
            'username'  => 'user_' . Str::random(8),
            'password'  => bcrypt('password'),
            'role'      => $role,
            'sub_role'  => 'guru',
            'is_active' => true,
        ], $extra));
    }

    public function test_dispensasi_index_renders_surat(): void
    {
        $piket = $this->makeUser('guru');
        JadwalPiket::create(['hari' => 'Senin', 'user_id' => $piket->id]);

        $kelas = Kelas::create(['nama_kelas' => 'XII IPA 1', 'tingkat' => 'XII']);

        $siswa = Siswa::create([
            'nama'          => 'Budi Santoso',
            'nisn'          => '1234567890',
            'nis'           => '12345',
            'jenis_kelamin' => 'L',
            'id_kelas'      => $kelas->id,
            'status_siswa'  => 'Aktif',
        ]);

        $dispen = DispensasiSiswa::create([
            'id_siswa'      => $siswa->id,
            'id_guru_piket' => $piket->id,
            'tanggal'       => now()->toDateString(),
            'jam_ke'        => '3,4',
            'alasan'        => 'Rapat organisasi siswa',
            'status'        => DispensasiSiswa::STATUS_PENDING,
        ]);

        // Tidak ada lagi tombol Kirim WA / approval Waka di halaman index.
        $this->actingAs($piket)
            ->get(route('piket.dispensasi.index'))
            ->assertOk()
            ->assertDontSee('Kirim WA ke Waka')
            ->assertDontSee('QR Approval')
            ->assertSee(route('piket.dispensasi.surat', $dispen->id));
    }

    public function test_waka_can_approve_pending_dispensasi_with_signature(): void
    {
        $piket = $this->makeUser('guru');
        $waka = $this->makeUser('admin', ['sub_role' => 'waka_kurikulum']);

        $kelas = Kelas::create(['nama_kelas' => 'XI IPA 2', 'tingkat' => 'XI']);
        $siswa = Siswa::create([
            'nama'          => 'Dewi Lestari',
            'nisn'          => '2234567890',
            'nis'           => '22345',
            'jenis_kelamin' => 'P',
            'id_kelas'      => $kelas->id,
            'status_siswa'  => 'Aktif',
        ]);

        $dispen = DispensasiSiswa::create([
            'id_siswa'      => $siswa->id,
            'id_guru_piket' => $piket->id,
            'tanggal'       => now()->toDateString(),
            'jam_ke'        => '5',
            'alasan'        => 'Kunjungan keluarga',
            'status'        => DispensasiSiswa::STATUS_PENDING_WAKA,
            'ttd_guru'      => 'data:image/png;base64,AAAA',
        ]);

        $this->actingAs($waka)
            ->get(route('kurikulum.dispensasi.approval.index'))
            ->assertOk();

        $this->actingAs($waka)
            ->post(route('kurikulum.dispensasi.approval.store', $dispen->id), [
                'ttd_waka' => 'data:image/png;base64,BBBB',
            ])
            ->assertRedirect(route('kurikulum.dispensasi.approval.index'))
            ->assertSessionHas('success');

        $dispen->refresh();
        $this->assertEquals(DispensasiSiswa::STATUS_FINAL, $dispen->status);
        $this->assertNotNull($dispen->ttd_waka);
        $this->assertNotNull($dispen->approved_at);
    }

    public function test_public_token_approval_page_allows_waka_signature_without_login(): void
    {
        $piket = $this->makeUser('guru');
        $kelas = Kelas::create(['nama_kelas' => 'X IPA 3', 'tingkat' => 'X']);
        $siswa = Siswa::create([
            'nama'          => 'Fajar Nugraha',
            'nisn'          => '3344556677',
            'nis'           => '33445',
            'jenis_kelamin' => 'L',
            'id_kelas'      => $kelas->id,
            'status_siswa'  => 'Aktif',
        ]);

        $dispen = DispensasiSiswa::create([
            'id_siswa'      => $siswa->id,
            'id_guru_piket' => $piket->id,
            'tanggal'       => now()->toDateString(),
            'jam_ke'        => '2,3',
            'alasan'        => 'Acara keluarga',
            'status'        => DispensasiSiswa::STATUS_DISETUJUI,
            'ttd_guru'      => 'data:image/png;base64,CCCC',
            'approval_token' => 'token-public-123',
        ]);

        $this->get(route('dispen.approval.show', $dispen->approval_token))
            ->assertOk()
            ->assertSee('Persetujuan Dispensasi')
            ->assertSee('Fajar Nugraha');

        $this->post(route('dispen.approval.store', $dispen->approval_token), [
            'ttd_waka' => 'data:image/png;base64,DDDD',
        ])->assertRedirect(route('dispen.approval.show', $dispen->approval_token))
            ->assertSessionHas('success');

        $dispen->refresh();
        $this->assertEquals(DispensasiSiswa::STATUS_APPROVED, $dispen->status);
        $this->assertNotNull($dispen->ttd_waka);
    }
}