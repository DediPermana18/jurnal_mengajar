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
            'nama' => 'User '.Str::random(5),
            'username' => 'user_'.Str::random(8),
            'password' => bcrypt('password'),
            'role' => $role,
            'sub_role' => 'guru',
            'is_active' => true,
        ], $extra));
    }

    public function test_dispensasi_index_renders_surat(): void
    {
        $piket = $this->makeUser('guru');
        JadwalPiket::create(['hari' => 'Senin', 'user_id' => $piket->id]);

        $kelas = Kelas::create(['nama_kelas' => 'XII IPA 1', 'tingkat' => 'XII']);

        $siswa = Siswa::create([
            'nama' => 'Budi Santoso',
            'nisn' => '1234567890',
            'nis' => '12345',
            'jenis_kelamin' => 'L',
            'id_kelas' => $kelas->id,
            'status_siswa' => 'Aktif',
        ]);

        $dispen = DispensasiSiswa::create([
            'id_siswa' => $siswa->id,
            'id_guru_piket' => $piket->id,
            'tanggal' => now()->toDateString(),
            'jam_ke' => '3,4',
            'alasan' => 'Rapat organisasi siswa',
            'status' => DispensasiSiswa::STATUS_PENDING,
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
            'nama' => 'Dewi Lestari',
            'nisn' => '2234567890',
            'nis' => '22345',
            'jenis_kelamin' => 'P',
            'id_kelas' => $kelas->id,
            'status_siswa' => 'Aktif',
        ]);

        $dispen = DispensasiSiswa::create([
            'id_siswa' => $siswa->id,
            'id_guru_piket' => $piket->id,
            'tanggal' => now()->toDateString(),
            'jam_ke' => '5',
            'alasan' => 'Kunjungan keluarga',
            'status' => DispensasiSiswa::STATUS_PENDING_WAKA,
            'ttd_guru' => 'data:image/png;base64,AAAA',
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
        $waka = $this->makeUser('admin', ['sub_role' => 'waka_kesiswaan']);
        $kelas = Kelas::create(['nama_kelas' => 'X IPA 3', 'tingkat' => 'X']);
        $siswa = Siswa::create([
            'nama' => 'Fajar Nugraha',
            'nisn' => '3344556677',
            'nis' => '33445',
            'jenis_kelamin' => 'L',
            'id_kelas' => $kelas->id,
            'status_siswa' => 'Aktif',
        ]);

        $dispen = DispensasiSiswa::create([
            'id_siswa' => $siswa->id,
            'id_guru_piket' => $piket->id,
            'tanggal' => now()->toDateString(),
            'jam_ke' => '2,3',
            'alasan' => 'Acara keluarga',
            'status' => DispensasiSiswa::STATUS_DISETUJUI,
            'ttd_guru' => 'data:image/png;base64,CCCC',
            'approval_token' => 'token-public-123',
        ]);

        $this->get(route('dispen.approval.show', $dispen->approval_token))
            ->assertOk()
            ->assertSee('Persetujuan Dispensasi')
            ->assertSee('Fajar Nugraha')
            ->assertSee('Pilih Waka Kesiswaan');

        // Tanpa memilih Waka Kesiswaan -> validasi menolak.
        $this->post(route('dispen.approval.store', $dispen->approval_token), [
            'ttd_waka' => 'data:image/png;base64,DDDD',
            'waka_kesiswaan_id' => '',
        ])->assertSessionHasErrors('waka_kesiswaan_id');

        $this->post(route('dispen.approval.store', $dispen->approval_token), [
            'ttd_waka' => 'data:image/png;base64,DDDD',
            'waka_kesiswaan_id' => $waka->id,
        ])->assertRedirect(route('dispen.approval.show', $dispen->approval_token))
            ->assertSessionHas('success');

        $dispen->refresh();
        $this->assertEquals(DispensasiSiswa::STATUS_APPROVED, $dispen->status);
        $this->assertNotNull($dispen->ttd_waka);
        $this->assertEquals($waka->id, $dispen->waka_kesiswaan_id);
        $this->assertEquals($waka->id, $dispen->approved_by);
    }

    public function test_public_approval_auto_detects_logged_in_waka_kesiswaan(): void
    {
        $waka = $this->makeUser('admin', ['sub_role' => 'waka_kesiswaan']);
        $piket = $this->makeUser('guru');
        $kelas = Kelas::create(['nama_kelas' => 'X IPA 4', 'tingkat' => 'X']);
        $siswa = Siswa::create([
            'nama' => 'Gita Puspita',
            'nisn' => '5566778899',
            'nis' => '55667',
            'jenis_kelamin' => 'P',
            'id_kelas' => $kelas->id,
            'status_siswa' => 'Aktif',
        ]);

        $dispen = DispensasiSiswa::create([
            'id_siswa' => $siswa->id,
            'id_guru_piket' => $piket->id,
            'tanggal' => now()->toDateString(),
            'jam_ke' => '4',
            'alasan' => 'Persiapan lomba',
            'status' => DispensasiSiswa::STATUS_DISETUJUI,
            'ttd_guru' => 'data:image/png;base64,EEEE',
            'approval_token' => 'token-public-456',
        ]);

        // Login sebagai Waka Kesiswaan: dropdown terkunci + input tersembunyi.
        $this->actingAs($waka)
            ->get(route('dispen.approval.show', $dispen->approval_token))
            ->assertOk()
            ->assertSee('wakaHidden')
            ->assertSee('wakaSelect');

        // Server memaksa memakai identitas user yang login meski id lain (valid)
        // dikirim lewat request — bukti TTD tidak bisa atas nama waka lain saat login.
        $another = $this->makeUser('admin', ['sub_role' => 'petugas_tu']);
        $this->actingAs($waka)
            ->post(route('dispen.approval.store', $dispen->approval_token), [
                'ttd_waka' => 'data:image/png;base64,FFFF',
                'waka_kesiswaan_id' => $another->id,
            ])
            ->assertRedirect(route('dispen.approval.show', $dispen->approval_token))
            ->assertSessionHas('success');

        $dispen->refresh();
        $this->assertEquals($waka->id, $dispen->waka_kesiswaan_id);
        $this->assertEquals($waka->id, $dispen->approved_by);
    }

    public function test_waka_kesiswaan_approval_tabs_filter_history_and_actions(): void
    {
        $waka = $this->makeUser('admin', ['sub_role' => 'waka_kesiswaan']);
        $piket = $this->makeUser('guru');
        $kelas = Kelas::create(['nama_kelas' => 'XI IPA 3', 'tingkat' => 'XI']);
        $siswa = function (string $nama) use ($kelas) {
            return Siswa::create([
                'nama' => $nama,
                'nisn' => Str::random(10),
                'nis' => Str::random(6),
                'jenis_kelamin' => 'L',
                'id_kelas' => $kelas->id,
                'status_siswa' => 'Aktif',
            ]);
        };

        $siswaMenunggu = $siswa('Ani Menunggu');
        $siswaDisetujui = $siswa('Budi Disetujui');
        $siswaDitolak = $siswa('Cici Ditolak');

        $menunggu = DispensasiSiswa::create([
            'id_siswa' => $siswaMenunggu->id,
            'id_guru_piket' => $piket->id,
            'tanggal' => now()->toDateString(),
            'jam_ke' => '2,3',
            'alasan' => 'Menunggu tanda tangan',
            'status' => DispensasiSiswa::STATUS_DISETUJUI,
            'ttd_guru' => 'data:image/png;base64,GGGG',
        ]);

        $disetujui = DispensasiSiswa::create([
            'id_siswa' => $siswaDisetujui->id,
            'id_guru_piket' => $piket->id,
            'tanggal' => now()->toDateString(),
            'jam_ke' => '4',
            'alasan' => 'Sudah ditandatangani',
            'status' => DispensasiSiswa::STATUS_APPROVED,
            'ttd_guru' => 'data:image/png;base64,HHHH',
            'ttd_waka' => 'data:image/png;base64,IIII',
            'waka_kesiswaan_id' => $waka->id,
            'approved_at' => now(),
            'approved_by' => $waka->id,
        ]);

        $ditolak = DispensasiSiswa::create([
            'id_siswa' => $siswaDitolak->id,
            'id_guru_piket' => $piket->id,
            'tanggal' => now()->toDateString(),
            'jam_ke' => '5',
            'alasan' => 'Surat ditolak',
            'status' => DispensasiSiswa::STATUS_DITOLAK,
            'ttd_guru' => 'data:image/png;base64,KKKK',
        ]);

        $this->actingAs($waka)
            ->get(route('waka-kesiswaan.dispensasi.approval.index'))
            ->assertOk()
            ->assertSee('Menunggu TTD')
            ->assertSee('Disetujui')
            ->assertSee('Ditolak')
            ->assertSee('Semua')
            ->assertSee('Ani Menunggu')
            ->assertSee('Tanda Tangan')
            ->assertSee('text-warning-emphasis')
            ->assertDontSee('Detail TTD')
            ->assertDontSee('Budi Disetujui');

        $this->actingAs($waka)
            ->get(route('waka-kesiswaan.dispensasi.approval.index', ['filter' => 'disetujui']))
            ->assertOk()
            ->assertSee('Budi Disetujui')
            ->assertSee('Lihat Surat')
            ->assertSee('Cetak PDF')
            ->assertSee('Detail TTD')
            ->assertSee('text-success-emphasis')
            ->assertDontSee('Ani Menunggu');

        $this->actingAs($waka)
            ->get(route('waka-kesiswaan.dispensasi.approval.index', ['filter' => 'ditolak']))
            ->assertOk()
            ->assertSee('Cici Ditolak')
            ->assertSee('text-danger-emphasis')
            ->assertSee('Lihat Surat')
            ->assertDontSee('Ani Menunggu')
            ->assertDontSee('Budi Disetujui');

        $this->actingAs($waka)
            ->get(route('waka-kesiswaan.dispensasi.approval.index', ['filter' => 'semua']))
            ->assertOk()
            ->assertSee('Ani Menunggu')
            ->assertSee('Budi Disetujui')
            ->assertSee('Cici Ditolak');

        // Filter tidak dikenal -> kembali ke default "menunggu".
        $this->actingAs($waka)
            ->get(route('waka-kesiswaan.dispensasi.approval.index', ['filter' => 'bogus']))
            ->assertOk()
            ->assertSee('Ani Menunggu')
            ->assertDontSee('Budi Disetujui');
    }

    public function test_waka_kesiswaan_portal_dashboard_accessible(): void
    {
        $waka = $this->makeUser('admin', ['sub_role' => 'waka_kesiswaan']);

        $this->actingAs($waka)
            ->get(route('waka-kesiswaan.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard Waka Kesiswaan');

        $this->actingAs($waka)
            ->get(route('waka-kesiswaan.dispensasi.approval.index'))
            ->assertOk()
            ->assertSee('Approval Dispensasi');

        // Admin lain bukan Waka Kesiswaan -> 403.
        $this->actingAs($this->makeUser('admin', ['sub_role' => 'petugas_tu']))
            ->get(route('waka-kesiswaan.dashboard'))
            ->assertForbidden();

        // Guru biasa tidak boleh masuk portal.
        $this->actingAs($this->makeUser('guru'))
            ->get(route('waka-kesiswaan.dashboard'))
            ->assertForbidden();
    }
}
