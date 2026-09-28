<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SatpamDashboardLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 8, 10));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function makeSatpam(): User
    {
        return User::create([
            'nama' => 'Satpam '.Str::random(5),
            'username' => 'satpam_'.Str::random(8),
            'password' => bcrypt('password'),
            'role' => 'admin',
            'sub_role' => 'satpam',
            'is_active' => true,
        ]);
    }

    protected function makeSiswa(): array
    {
        $kelas = Kelas::create([
            'nama_kelas' => 'X IPA 1',
            'tingkat' => 'X',
        ]);

        $siswa = Siswa::create([
            'nisn' => '0000000001',
            'nis' => '23101',
            'nama' => 'Budi Santoso',
            'jenis_kelamin' => 'L',
            'id_kelas' => $kelas->id,
        ]);

        return [$kelas, $siswa];
    }

    protected function getDashboard(): \Illuminate\Testing\TestResponse
    {
        $satpam = $this->makeSatpam();
        $this->makeSiswa();

        return $this->actingAs($satpam)->get(route('satpam.dashboard'));
    }

    public function test_dashboard_wrapper_memiliki_padding_agar_konten_tidak_tertutup_topbar(): void
    {
        $this->getDashboard()
            ->assertOk()
            ->assertSee('container-fluid px-0 pt-3 md:pt-4 pb-4', false);
    }

    public function test_dashboard_tidak_lagi_menampilkan_tombol_verifikasi_di_pojok_header(): void
    {
        $response = $this->getDashboard()->assertOk();

        // Tombol redundan di header (pojok kanan atas) dihapus...
        $response->assertDontSee('Verifikasi Izin Keluar');

        // ...tetapi akses verifikasi tetap tersedia lewat menu sidebar & tombol di tab Cek.
        $response->assertSee('Verifikasi Izin &amp; Dispensasi', false)
            ->assertSee('Hal. Verifikasi');
    }

    public function test_dashboard_menggunakan_searchable_select_dengan_nis_dan_nisn(): void
    {
        $this->getDashboard()
            ->assertOk()
            // Choices.js dimuat (CSS + JS) untuk dropdown searchable.
            ->assertSee('choices.min.css')
            ->assertSee('Cari nama / NIS / NISN...')
            ->assertSee('<option value="" placeholder>-- Pilih Siswa --</option>', false)
            // Label opsi memuat NIS & NISN sehingga bisa dicari cepat di gerbang.
            ->assertSee('NIS 23101')
            ->assertSee('NISN 0000000001')
            ->assertSee('Budi Santoso');
    }

    public function test_dashboard_grid_form_dan_tabel_seimbang_1_3_dan_2_3(): void
    {
        $this->getDashboard()
            ->assertOk()
            ->assertSee('col-lg-4')
            ->assertSee('col-lg-8')
            ->assertSee('h-100 d-flex flex-column')
            ->assertSee('mt-auto')
            ->assertSee('flex-grow-1');
    }
}