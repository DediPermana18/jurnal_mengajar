<?php

namespace Tests\Feature;

use App\Models\JadwalPiket;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PiketSidebarActiveStateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Rabu — hari aktif sekolah.
        Carbon::setTestNow('2026-09-16 08:00:00');
        $this->seed(DatabaseSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function buatGuruBerjadwalPiket(): User
    {
        $guru = User::create([
            'nama' => 'Guru Piket Sidebar',
            'username' => 'piket_sidebar_'.Str::random(6),
            'password' => bcrypt('secret'),
            'role' => User::ROLE_GURU,
            'sub_role' => 'guru',
            'is_active' => true,
        ]);
        JadwalPiket::create(['hari' => 'Rabu', 'user_id' => $guru->id]);

        return $guru;
    }

    public function test_guru_berjadwal_piket_dashboard_piket_aktif_bukan_status_kehadiran_guru(): void
    {
        $guru = $this->buatGuruBerjadwalPiket();

        // Item "Dashboard Piket" yang menyala (bg-blue), bukan "Status Kehadiran Guru".
        $this->actingAs($guru)
            ->get(route('piket.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard Piket')
            ->assertSee('href="'.route('piket.dashboard').'" class="nav-btn active"', false)
            ->assertDontSee('href="'.route('piket.status-guru').'" class="nav-btn active"', false);
    }

    public function test_impersonasi_guru_piket_dashboard_piket_aktif_bukan_status_kehadiran_guru(): void
    {
        $it = User::where('email', 'it@school.id')->firstOrFail();
        $this->actingAs($it);
        $this->post(route('it.switch-view'), ['role' => 'guru_piket'])
            ->assertRedirect(route('piket.dashboard'));

        $this->get(route('piket.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard Piket')
            ->assertSee('href="'.route('piket.dashboard').'" class="nav-btn active"', false)
            ->assertDontSee('href="'.route('piket.status-guru').'" class="nav-btn active"', false);
    }

    public function test_halaman_status_kehadiran_guru_tetap_menyoroti_status_kehadiran_guru(): void
    {
        $it = User::where('email', 'it@school.id')->firstOrFail();
        $this->actingAs($it);
        $this->post(route('it.switch-view'), ['role' => 'guru_piket']);

        $this->get(route('piket.status-guru'))
            ->assertOk()
            ->assertSee('href="'.route('piket.status-guru').'" class="nav-btn active"', false)
            ->assertDontSee('href="'.route('piket.dashboard').'" class="nav-btn active"', false);
    }

    public function test_sidebar_piket_khusus_dikelompokkan_guru_piket_dan_area_pribadi_guru(): void
    {
        // Akun petugas piket khusus (role literal 'guru_piket', non-guru) yang
        // bertugas hari ini → blok sidebar piket-only ter-render (grup
        // "GURU PIKET" + "AREA PRIBADI GURU").
        $piket = User::create([
            'nama' => 'Petugas Piket Khusus',
            'username' => 'piket_role_'.Str::random(6),
            'password' => bcrypt('secret'),
            'role' => 'guru_piket',
            'sub_role' => null,
            'is_active' => true,
        ]);
        JadwalPiket::create(['hari' => 'Rabu', 'user_id' => $piket->id]);

        $this->actingAs($piket)
            ->get(route('home'))
            ->assertOk()
            // Dua kelompok jelas.
            ->assertSee('GURU PIKET')
            ->assertSee('AREA PRIBADI GURU')
            // Grup 1: GURU PIKET — Dashboard, Presensi Siswa, Jurnal KBM Harian,
            // Dispensasi Siswa, Approval Izin Guru, Status Kehadiran Guru.
            ->assertSee('href="'.route('piket.dashboard').'" class="nav-btn', false)
            ->assertSee('href="'.route('piket.presensi-siswa').'" class="nav-btn', false)
            ->assertSee('href="'.route('piket.jurnal').'" class="nav-btn', false)
            ->assertSee('href="'.route('piket.dispensasi.index').'" class="nav-btn', false)
            ->assertSee('href="'.route('piket.izin.index').'" class="nav-btn', false)
            ->assertSee('href="'.route('piket.status-guru').'" class="nav-btn', false)
            // Grup 2: AREA PRIBADI GURU — Jurnal Mengajar Saya, Pengajuan Izin Saya.
            ->assertSee('href="'.route('guru.jurnal').'" class="nav-btn', false)
            ->assertSee('href="'.route('guru.izin.index').'" class="nav-btn', false)
            ->assertSee('Pengajuan Izin Saya')
            // Tidak ada item lain yang menyala pada halaman beranda.
            ->assertDontSee('href="'.route('piket.dashboard').'" class="nav-btn active"', false);
    }
}