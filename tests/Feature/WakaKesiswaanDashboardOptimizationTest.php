<?php

namespace Tests\Feature;

use App\Models\DispensasiSiswa;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Optimasi UX Dashboard Waka Kesiswaan:
 * - header memakai link action "Buka Modul Approval" (bukan tombol biru),
 * - tabel dashboard = quick-view khusus pengajuan MENUNGGU TTD (limit 5),
 * - halaman Approval punya search, filter tanggal, pagination, dan TTD bulk.
 */
class WakaKesiswaanDashboardOptimizationTest extends TestCase
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

    protected function makeWaka(): User
    {
        return $this->makeUser('admin', ['sub_role' => 'waka_kesiswaan']);
    }

    protected function buatSiswa(string $nama, string $nisn, string $nis): array
    {
        $kelas = Kelas::create(['nama_kelas' => 'X IPA 1', 'tingkat' => 'X']);

        $siswa = Siswa::create([
            'nama' => $nama,
            'nisn' => $nisn,
            'nis' => $nis,
            'jenis_kelamin' => 'L',
            'id_kelas' => $kelas->id,
            'status_siswa' => 'Aktif',
        ]);

        return [$kelas, $siswa];
    }

    protected function buatDispen(Siswa $siswa, User $piket, array $extra = []): DispensasiSiswa
    {
        return DispensasiSiswa::create(array_merge([
            'id_siswa' => $siswa->id,
            'id_guru_piket' => $piket->id,
            'tanggal' => now()->toDateString(),
            'tipe_dispen' => DispensasiSiswa::TIPE_KELUAR,
            'jam_ke' => '1,2',
            'alasan' => 'Keperluan keluarga',
        ], $extra));
    }

    // ================= Header & Link Action =================

    public function test_dashboard_header_pakai_link_buka_modul_approval_dan_badge_kesiswaan(): void
    {
        $waka = $this->makeWaka();

        $this->actingAs($waka)
            ->get(route('waka-kesiswaan.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard Waka Kesiswaan')
            ->assertSee('Buka Modul Approval')
            ->assertSee('bi-arrow-right')
            // Badge 'Kesiswaan' (ikon shield) redundan dengan judul halaman → sudah dihapus.
            ->assertDontSee('bi-shield-check', false);
    }

    // ================= Quick-view Dashboard (pending only, limit 5) =================

    public function test_dashboard_quickview_hanya_menampilkan_pengajuan_menunggu_ttd(): void
    {
        $waka = $this->makeWaka();
        $piket = $this->makeUser('guru');

        [$kelasA, $siswaMenunggu] = $this->buatSiswa('Budi Menunggu', '1111111111', '10011');
        [$kelasB, $siswaSelesai] = $this->buatSiswa('Cici Sudah TTD', '2222222222', '10022');

        $this->buatDispen($siswaMenunggu, $piket, ['status' => DispensasiSiswa::STATUS_PENDING]);
        $this->buatDispen($siswaSelesai, $piket, [
            'status' => DispensasiSiswa::STATUS_APPROVED,
            'ttd_waka' => 'data:image/png;base64,WAKA',
            'approved_by' => $waka->id,
        ]);

        $this->actingAs($waka)
            ->get(route('waka-kesiswaan.dashboard'))
            ->assertOk()
            ->assertSee('Menunggu Approval Waka')
            ->assertSee('Budi Menunggu')
            ->assertDontSee('Cici Sudah TTD');
    }

    public function test_dashboard_quickview_dibatasi_5_data(): void
    {
        $waka = $this->makeWaka();
        $piket = $this->makeUser('guru');

        $names = [];
        for ($i = 1; $i <= 6; $i++) {
            [, $siswa] = $this->buatSiswa("Siswa Antrian {$i}", str_pad((string) $i, 10, '0', STR_PAD_LEFT), '2000'.$i);
            $this->buatDispen($siswa, $piket, ['status' => DispensasiSiswa::STATUS_PENDING, 'alasan' => "Antrian ke-{$i}"]);
            $names[$i] = $siswa->nama;
        }

        // Urut tanggal DESC, id DESC → 5 terbaru (id 2..6) tampil; id 1 (tertua) tidak.
        $this->actingAs($waka)
            ->get(route('waka-kesiswaan.dashboard'))
            ->assertOk()
            ->assertSee($names[6])
            ->assertSee($names[2])
            ->assertDontSee($names[1]);
    }

    public function test_dashboard_quickview_empty_state_saat_tidak_ada_pending(): void
    {
        $waka = $this->makeWaka();

        $this->actingAs($waka)
            ->get(route('waka-kesiswaan.dashboard'))
            ->assertOk()
            ->assertSee('Tidak ada pengajuan yang menunggu tanda tangan Waka Kesiswaan.');
    }

    // ================= Approval: Search =================

    public function test_approval_search_by_nama_nis_dan_nomor_surat(): void
    {
        $waka = $this->makeWaka();
        $piket = $this->makeUser('guru');

        [$kelasA, $siswaA] = $this->buatSiswa('Andi Belajar', '3333333333', '30033');
        [$kelasB, $siswaB] = $this->buatSiswa('Rina Rutin', '4444444444', '40044');

        $dispenA = $this->buatDispen($siswaA, $piket, ['status' => DispensasiSiswa::STATUS_PENDING]);
        $dispenB = $this->buatDispen($siswaB, $piket, ['status' => DispensasiSiswa::STATUS_PENDING]);

        $this->actingAs($waka);

        // Pencarian nama
        $this->get(route('waka-kesiswaan.dispensasi.approval.index', ['filter' => 'semua', 'search' => 'Andi']))
            ->assertOk()
            ->assertSee('Andi Belajar')
            ->assertDontSee('Rina Rutin');

        // Pencarian NIS
        $this->get(route('waka-kesiswaan.dispensasi.approval.index', ['filter' => 'semua', 'search' => '40044']))
            ->assertOk()
            ->assertSee('Rina Rutin')
            ->assertDontSee('Andi Belajar');

        // Pencarian nomor surat DIS-####/TAHUN
        $this->get(route('waka-kesiswaan.dispensasi.approval.index', ['filter' => 'semua', 'search' => $dispenB->nomor_surat]))
            ->assertOk()
            ->assertSee('Rina Rutin')
            ->assertDontSee('Andi Belajar');
    }

    // ================= Approval: Filter Tanggal =================

    public function test_approval_filter_rentang_tanggal(): void
    {
        $waka = $this->makeWaka();
        $piket = $this->makeUser('guru');

        [$kelasA, $siswaA] = $this->buatSiswa('Agustus Awal', '5555555555', '50055');
        [$kelasB, $siswaB] = $this->buatSiswa('Agustus Akhir', '6666666666', '60066');

        $this->buatDispen($siswaA, $piket, ['tanggal' => '2026-08-10', 'status' => DispensasiSiswa::STATUS_PENDING]);
        $this->buatDispen($siswaB, $piket, ['tanggal' => '2026-08-20', 'status' => DispensasiSiswa::STATUS_PENDING]);

        $this->actingAs($waka);

        // Rentang 15-31 Agustus → hanya surat tanggal 20.
        $this->get(route('waka-kesiswaan.dispensasi.approval.index', [
            'filter' => 'semua',
            'tanggal_mulai' => '2026-08-15',
            'tanggal_selesai' => '2026-08-31',
        ]))
            ->assertOk()
            ->assertSee('Agustus Akhir')
            ->assertDontSee('Agustus Awal');

        // Rentang 05-12 Agustus → hanya surat tanggal 10.
        $this->get(route('waka-kesiswaan.dispensasi.approval.index', [
            'filter' => 'semua',
            'tanggal_mulai' => '2026-08-05',
            'tanggal_selesai' => '2026-08-12',
        ]))
            ->assertOk()
            ->assertSee('Agustus Awal')
            ->assertDontSee('Agustus Akhir');
    }

    // ================= Approval: Pagination =================

    public function test_approval_pagination_15_per_halaman(): void
    {
        $waka = $this->makeWaka();
        $piket = $this->makeUser('guru');

        for ($i = 1; $i <= 16; $i++) {
            [, $siswa] = $this->buatSiswa("Paginate {$i}", str_pad((string) $i, 10, '0', STR_PAD_LEFT), '7000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT));
            $this->buatDispen($siswa, $piket, ['status' => DispensasiSiswa::STATUS_PENDING, 'alasan' => "Pesan {$i}"]);
        }

        $this->actingAs($waka)
            ->get(route('waka-kesiswaan.dispensasi.approval.index', ['filter' => 'semua']))
            ->assertOk()
            ->assertSee('16 surat')
            ->assertSee('Halaman 1 / 2');

        $this->get(route('waka-kesiswaan.dispensasi.approval.index', ['filter' => 'semua', 'page' => 2]))
            ->assertOk()
            ->assertSee('Halaman 2 / 2');
    }

    // ================= Approval: TTD Massal (Bulk) =================

    public function test_bulk_ttd_menandatangani_beberapa_surat_sekaligus(): void
    {
        $waka = $this->makeWaka();
        $piket = $this->makeUser('guru');

        $ids = [];
        foreach (['Satu', 'Dua', 'Tiga'] as $i => $label) {
            [, $siswa] = $this->buatSiswa("Bulk {$label}", '888888888'.$i, '800'.($i + 1));
            $ids[] = $this->buatDispen($siswa, $piket, ['status' => DispensasiSiswa::STATUS_PENDING])->id;
        }

        // Surat yang SUDAH di-TTD → harus dilewati (tidak diproses ulang).
        [, $siswaSelesai] = $this->buatSiswa('Bulk Selesai', '9999999999', '9001');
        $selesai = $this->buatDispen($siswaSelesai, $piket, [
            'status' => DispensasiSiswa::STATUS_APPROVED,
            'ttd_waka' => 'data:image/png;base64,WAKA_LAMA',
            'approved_by' => $waka->id,
        ]);

        $response = $this->actingAs($waka)
            ->post(route('waka-kesiswaan.dispensasi.approval.bulk'), [
                'ids' => array_merge($ids, [$selesai->id]),
                'ttd_waka' => 'data:image/png;base64,TTD_MASSAL',
            ]);

        $response->assertSessionHas('success');

        foreach ($ids as $id) {
            $dispen = DispensasiSiswa::find($id);
            $this->assertNotNull($dispen->ttd_waka, "Surat {$id} harus punya TTD.");
            $this->assertSame(DispensasiSiswa::STATUS_APPROVED, $dispen->status);
            $this->assertNotNull($dispen->approved_at);
        }

        $selesai->refresh();
        $this->assertSame('data:image/png;base64,WAKA_LAMA', $selesai->ttd_waka, 'Surat yang sudah di-TTD tidak boleh ditimpa.');
    }

    public function test_bulk_ttd_wajib_menggambar_tanda_tangan(): void
    {
        $waka = $this->makeWaka();
        $piket = $this->makeUser('guru');

        [, $siswa] = $this->buatSiswa('Bulk Tanpa TTD', '1212121212', '1012');
        $id = $this->buatDispen($siswa, $piket, ['status' => DispensasiSiswa::STATUS_PENDING])->id;

        $this->actingAs($waka)
            ->post(route('waka-kesiswaan.dispensasi.approval.bulk'), [
                'ids' => [$id],
                'ttd_waka' => '',
            ])
            ->assertSessionHasErrors('ttd_waka');
    }

    public function test_bulk_ttd_tanpa_pilihan_surat_ditolak(): void
    {
        $waka = $this->makeWaka();

        $this->actingAs($waka)
            ->post(route('waka-kesiswaan.dispensasi.approval.bulk'), [
                'ids' => [],
                'ttd_waka' => 'data:image/png;base64,TTD',
            ])
            ->assertSessionHasErrors('ids');
    }

    public function test_bulk_ttd_ditolak_untuk_non_waka(): void
    {
        $guru = $this->makeUser('guru');
        $piket = $this->makeUser('guru');

        [, $siswa] = $this->buatSiswa('Bulk Non Waka', '1313131313', '1013');
        $id = $this->buatDispen($siswa, $piket, ['status' => DispensasiSiswa::STATUS_PENDING])->id;

        $this->actingAs($guru)
            ->post(route('waka-kesiswaan.dispensasi.approval.bulk'), [
                'ids' => [$id],
                'ttd_waka' => 'data:image/png;base64,TTD',
            ])
            ->assertForbidden();
    }

    // ================= Polish UI/UX: Counter Badges =================

    public function test_sidebar_badge_menunggu_ttd_disembunyikan_saat_nol(): void
    {
        $waka = $this->makeWaka();

        // Tanpa pengajuan menunggu → badge counter di sidebar TIDAK dirender
        // sama sekali (tidak ada badge "0").
        $this->actingAs($waka)
            ->get(route('waka-kesiswaan.dashboard'))
            ->assertOk()
            ->assertDontSee('bg-danger rounded-pill px-2 py-0.5', false)
            ->assertDontSee('bg-secondary-subtle text-secondary border rounded-pill px-1.5 py-0.5', false);
    }

    public function test_sidebar_badge_menunggu_ttd_tampil_saat_ada_pending(): void
    {
        $waka = $this->makeWaka();
        $piket = $this->makeUser('guru');

        [, $siswa] = $this->buatSiswa('Sidebar Pending', '1414141414', '1014');
        $this->buatDispen($siswa, $piket, ['status' => DispensasiSiswa::STATUS_PENDING]);

        // Ada antrian menunggu TTD → badge merah (danger) dengan counter tampil.
        $this->actingAs($waka)
            ->get(route('waka-kesiswaan.dashboard'))
            ->assertOk()
            ->assertSee('bg-danger rounded-pill px-2 py-0.5', false);
    }

    public function test_tab_counter_nol_menggunakan_styling_subtle(): void
    {
        $waka = $this->makeWaka();
        $piket = $this->makeUser('guru');

        // Hanya 1 menunggu → tab 'disetujui' dan 'ditolak' counter-nya 0.
        [, $siswa] = $this->buatSiswa('Tab Menunggu', '1515151515', '1015');
        $this->buatDispen($siswa, $piket, ['status' => DispensasiSiswa::STATUS_PENDING]);

        $this->actingAs($waka)
            ->get(route('waka-kesiswaan.dispensasi.approval.index', ['filter' => 'menunggu']))
            ->assertOk()
            // Tab inactive dengan counter 0 → subtle (transparan + opacity-50 + border-0).
            ->assertSee('bg-transparent text-muted opacity-50 border-0', false)
            // Tab active dengan counter > 0 → tetap kontras (badge putih di atas warna).
            ->assertSee('bg-white bg-opacity-25 text-white', false);
    }

    // ================= Polish UI/UX: Tombol Bulk & Live Search =================

    public function test_bulk_button_awalnya_disabled_dengan_styling_visual(): void
    {
        $waka = $this->makeWaka();
        $piket = $this->makeUser('guru');

        [, $siswa] = $this->buatSiswa('Bulk Awal', '1616161616', '1016');
        $this->buatDispen($siswa, $piket, ['status' => DispensasiSiswa::STATUS_PENDING]);

        // Tanpa checkbox dicentang → tombol disabled + opacity-50 + pointer-events-none.
        $this->actingAs($waka)
            ->get(route('waka-kesiswaan.dispensasi.approval.index', ['filter' => 'menunggu']))
            ->assertOk()
            ->assertSee('<button type="button" id="btnBulkTtd" class="btn btn-sm btn-primary rounded-3 fw-semibold opacity-50 pointer-events-none" disabled>', false);
    }

    public function test_fitur_instant_search_pada_form_filter(): void
    {
        $waka = $this->makeWaka();
        $piket = $this->makeUser('guru');

        [, $siswa] = $this->buatSiswa('Live Search', '1717171717', '1017');
        $this->buatDispen($siswa, $piket, ['status' => DispensasiSiswa::STATUS_PENDING]);

        $this->actingAs($waka)
            ->get(route('waka-kesiswaan.dispensasi.approval.index', ['filter' => 'menunggu']))
            ->assertOk()
            // Form punya identitas untuk submit programatik + input search live submit.
            ->assertSee('id="filterApprovalForm"', false)
            ->assertSee('data-live-submit', false);
    }
}