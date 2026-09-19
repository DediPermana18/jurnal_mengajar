<?php

namespace Tests\Feature;

use App\Models\IzinGuru;
use App\Models\JadwalPelajaran;
use App\Models\Scopes\TestingDataScope;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TestingGuruImpersonationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Rabu — hari aktif sekolah; jadwal sandbox guru.tester tersedia.
        Carbon::setTestNow('2026-09-16 08:00:00');

        $this->seed(DatabaseSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function loginPetugasIt(): User
    {
        $it = User::where('email', 'it@school.id')->firstOrFail();
        $this->actingAs($it);

        return $it;
    }

    private function switchToGuruMapel()
    {
        $this->post(route('it.switch-view'), ['role' => 'guru_mapel'])
            ->assertRedirect(route('guru.dashboard'));
    }

    public function test_home_redirect_ke_dashboard_sesuai_active_role()
    {
        $this->loginPetugasIt();

        // Tanpa active_role, IT melihat dashboard Admin (home).
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Total Guru Terdaftar');

        // admin_tu → dashboard Admin TU tetap dirender di home (bukan redirect loop).
        $this->post(route('it.switch-view'), ['role' => 'admin_tu']);
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Total Guru Terdaftar');

        // satpam → redirect ke portal Satpam tanpa loop.
        $this->post(route('it.switch-view'), ['role' => 'satpam']);
        $this->get(route('home'))
            ->assertRedirect(route('satpam.dashboard'));
        $this->get(route('satpam.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard Satpam');

        // waka_sdm → redirect ke portal Waka SDM.
        $this->post(route('it.switch-view'), ['role' => 'waka_sdm']);
        $this->get(route('home'))
            ->assertRedirect(route('waka-sdm.dashboard'));

        // kepsek → redirect ke portal Kepsek.
        $this->post(route('it.switch-view'), ['role' => 'kepsek']);
        $this->get(route('home'))
            ->assertRedirect(route('kepsek.dashboard'));

        // waka_kurikulum → redirect ke portal Kurikulum.
        $this->post(route('it.switch-view'), ['role' => 'waka_kurikulum']);
        $this->get(route('home'))
            ->assertRedirect(route('kurikulum.dashboard'));

        // Mode Guru Mapel → portal guru.
        $this->switchToGuruMapel();
        $this->get(route('home'))
            ->assertRedirect(route('guru.dashboard'));

        $this->get(route('guru.dashboard'))
            ->assertOk()
            ->assertSee('Jadwal Mengajar Hari Ini')
            ->assertDontSee('Total Guru Terdaftar');

        // Mode Wali Kelas → / diarahkan ke portal wali kelas.
        $this->post(route('it.switch-view'), ['role' => 'wali_kelas']);
        $this->get(route('home'))
            ->assertRedirect(route('walikelas.dashboard'));
    }

    public function test_switch_view_manajemen_landing_tanpa_loop(): void
    {
        $this->loginPetugasIt();

        $roleRoutes = [
            'satpam' => route('satpam.dashboard'),
            'waka_kurikulum' => route('kurikulum.dashboard'),
            'waka_sdm' => route('waka-sdm.dashboard'),
            'waka_kesiswaan' => route('waka-kesiswaan.dashboard'),
            'kepsek' => route('kepsek.dashboard'),
            'guru_mapel' => route('guru.dashboard'),
            'wali_kelas' => route('walikelas.dashboard'),
            'admin_tu' => route('home'),
        ];

        foreach ($roleRoutes as $role => $expectedUrl) {
            $this->post(route('it.switch-view'), ['role' => $role]);

            $response = $this->get($expectedUrl);
            $response->assertStatus(200, "Redirect loop atau dashboard tidak bisa dirender utk role: {$role}");
        }
    }

    public function test_izin_guru_tanpa_target_tidak_bocor_data(): void
    {
        $this->loginPetugasIt();
        $this->switchToGuruMapel();

        // Bersihkan target — user memilih "Akun Saya (Tanpa Target)".
        $this->withHeaders(['Referer' => route('guru.izin.index')])
            ->post(route('it.impersonate-target'), ['impersonate_target_id' => '']);
        $this->assertNull(session('impersonate_target_id'));

        // Data izin milik akun IT sendiri — harus TIDAK bocor bila fallback tertutup.
        IzinGuru::create([
            'user_id' => auth()->id(),
            'tanggal' => now()->toDateString(),
            'alasan' => 'Izin Sandbox IT - rahasia jangan bocor',
            'status' => IzinGuru::STATUS_DISETUJUI,
        ]);

        $this->get(route('guru.izin.index'))
            ->assertOk()
            ->assertSee('Menunggu Persetujuan')
            ->assertSee('Disetujui')
            ->assertSee('Ditolak')
            ->assertDontSee('Izin Sandbox IT - rahasia jangan bocor')
            ->assertSee('Belum ada pengajuan izin');
    }

    public function test_izin_guru_dengan_target_hanya_menampilkan_data_target(): void
    {
        $this->loginPetugasIt();
        $this->switchToGuruMapel();

        $target = User::where('username', 'guru.tester')->firstOrFail();
        $this->assertSame((int) session('impersonate_target_id'), (int) $target->id);

        IzinGuru::create([
            'user_id' => $target->id,
            'tanggal' => now()->toDateString(),
            'alasan' => 'Sakit - tesdt milik target',
            'status' => IzinGuru::STATUS_DISETUJUI,
            'token_waka' => (string) Str::uuid(),
        ]);

        // Izin milik akun IT tidak boleh ikut tampil di riwayat guru target.
        IzinGuru::create([
            'user_id' => auth()->id(),
            'tanggal' => now()->toDateString(),
            'alasan' => 'Izin Milik IT - dilarang tampil',
            'status' => IzinGuru::STATUS_DISETUJUI,
            'token_waka' => (string) Str::uuid(),
        ]);

        $this->get(route('guru.izin.index'))
            ->assertOk()
            ->assertSee('Sakit - tesdt milik target')
            ->assertDontSee('Izin Milik IT - dilarang tampil');
    }

    public function test_seeder_membuat_akun_guru_testing_berjadwal()
    {
        $guru = User::withoutGlobalScope(TestingDataScope::class)
            ->where('username', 'guru.tester')
            ->firstOrFail();

        $this->assertTrue((bool) $guru->is_testing_data, 'guru.tester harus flag is_testing_data = true');
        $this->assertSame('guru', $guru->role);
        $this->assertSame('guru_mapel', $guru->sub_role);
        $this->assertTrue((bool) $guru->is_active);

        $jumlahJadwal = JadwalPelajaran::withoutGlobalScope(TestingDataScope::class)
            ->where('id_guru', $guru->id)
            ->count();

        $this->assertGreaterThan(0, $jumlahJadwal, 'guru.tester harus punya jadwal mengajar sandbox');
    }

    public function test_switch_view_guru_mapel_tetap_login_it_dengan_target_default()
    {
        $it = $this->loginPetugasIt();
        $guruTarget = User::where('username', 'guru.tester')->firstOrFail();

        $this->switchToGuruMapel();

        $this->assertSame('guru_mapel', session('active_role'));
        $this->assertSame($it->id, auth()->id(), 'Auth tetap milik Petugas IT (bukan pindah login).');
        $this->assertSame($guruTarget->id, session('impersonate_target_id'), 'Target default = guru testing pertama.');
        $this->assertTrue(auth()->user()->hasActiveRole());
    }

    public function test_switch_view_tanpa_guru_testing_tidak_set_target()
    {
        // Tanpai akun guru testing, target default dilewati (kompatibel Seed user lama).
        User::withoutGlobalScope(TestingDataScope::class)
            ->whereIn('username', ['guru.tester', 'wali.tester'])
            ->delete();

        $this->loginPetugasIt();
        $this->switchToGuruMapel();

        $this->assertNull(session('impersonate_target_id'));
        $this->assertSame('guru_mapel', session('active_role'));
    }

    public function test_switch_view_ke_role_non_guru_menghapus_target()
    {
        $this->loginPetugasIt();
        $this->switchToGuruMapel();

        $this->assertNotNull(session('impersonate_target_id'));

        $this->post(route('it.switch-view'), ['role' => 'waka_kurikulum'])
            ->assertRedirect(route('kurikulum.dashboard'));

        $this->assertNull(session('impersonate_target_id'));
        $this->assertSame('waka_kurikulum', session('active_role'));
    }

    public function test_dashboard_guru_menampilkan_jadwal_guru_target()
    {
        $this->loginPetugasIt();
        $this->switchToGuruMapel();

        $this->get(route('guru.dashboard'))
            ->assertOk()
            ->assertSee('Jadwal Mengajar Hari Ini')
            ->assertSee('Pemrograman Dasar');
    }

    public function test_jurnal_menampilkan_jadwal_guru_target()
    {
        $this->loginPetugasIt();
        $this->switchToGuruMapel();

        $this->get(route('guru.jurnal'))
            ->assertOk()
            ->assertSee('Pemrograman Dasar')
            ->assertSee('Basis Data')
            ->assertSee('RPL 1')
            ->assertDontSee('Tidak Ada Jadwal Mengajar');
    }

    public function test_select_impersonate_target_mengubah_context_guru()
    {
        $this->loginPetugasIt();
        $this->switchToGuruMapel();

        $guruLain = User::withoutGlobalScope(TestingDataScope::class)->create([
            'nama' => 'Guru Kedua Sandbox',
            'username' => 'guru.tester.2',
            'email' => 'guru.tester2@school.id',
            'password' => bcrypt('password'),
            'role' => 'guru',
            'sub_role' => 'guru_mapel',
            'is_active' => true,
            'is_testing_data' => true,
        ]);

        $this->withHeaders(['Referer' => route('guru.dashboard')])
            ->post(route('it.impersonate-target'), ['impersonate_target_id' => $guruLain->id])
            ->assertRedirect(route('guru.dashboard'));

        $this->assertSame((int) session('impersonate_target_id'), (int) $guruLain->id);

        // Context kini milik guru kedua (tanpa jadwal) → halaman jurnal kosong.
        $this->get(route('guru.jurnal'))
            ->assertOk()
            ->assertSee('Tidak Ada Jadwal Mengajar');
    }

    public function test_clear_impersonate_target_kembali_tanpa_target()
    {
        $this->loginPetugasIt();
        $this->switchToGuruMapel();

        $this->withHeaders(['Referer' => route('guru.dashboard')])
            ->post(route('it.impersonate-target'), ['impersonate_target_id' => ''])
            ->assertRedirect(route('guru.dashboard'));

        $this->assertNull(session('impersonate_target_id'));
    }

    public function test_select_target_harus_akun_guru_testing()
    {
        $this->loginPetugasIt();
        $this->switchToGuruMapel();

        // Guru real tidak terlihat oleh mode IT (isolasi is_testing_data).
        $guruReal = User::withoutGlobalScope(TestingDataScope::class)
            ->where('role', 'guru')
            ->where('is_testing_data', false)
            ->first();
        $this->assertNotNull($guruReal);

        $targetSebelum = session('impersonate_target_id');

        $this->withHeaders(['Referer' => route('guru.dashboard')])
            ->post(route('it.impersonate-target'), ['impersonate_target_id' => $guruReal->id])
            ->assertStatus(422);

        // Target default tidak berubah — session tetap milik guru.tester.
        $this->assertSame($targetSebelum, session('impersonate_target_id'));
    }

    public function test_reset_view_menghapus_active_role_dan_target()
    {
        $this->loginPetugasIt();
        $this->switchToGuruMapel();

        $this->post(route('it.reset-view'))
            ->assertRedirect(route('it.dashboard'));

        $this->assertNull(session('active_role'));
        $this->assertNull(session('impersonate_target_id'));
    }

    public function test_non_it_tidak_bisa_set_target()
    {
        $guruReal = User::where('role', 'guru')->where('is_testing_data', false)->firstOrFail();
        $this->actingAs($guruReal);

        $this->post(route('it.impersonate-target'), ['impersonate_target_id' => 1])
            ->assertStatus(403);
        $this->post(route('it.reset-view'))->assertStatus(403);
    }

    public function test_switch_view_wali_kelas_set_target_wali_kelas_dan_redirect_ke_portal_wali()
    {
        $waliTarget = User::withoutGlobalScope(TestingDataScope::class)
            ->where('username', 'wali.tester')
            ->firstOrFail();

        $this->loginPetugasIt();

        $this->post(route('it.switch-view'), ['role' => 'wali_kelas'])
            ->assertRedirect(route('walikelas.dashboard'));

        $this->assertSame('wali_kelas', session('active_role'));
        $this->assertSame((int) session('impersonate_target_id'), (int) $waliTarget->id, 'Target default = wali kelas testing pertama.');
        $this->assertSame((string) session('impersonate_target_id'), (string) $waliTarget->id);
    }

    public function test_wali_kelas_dashboard_menampilkan_kelas_target_wali_kelas()
    {
        $this->loginPetugasIt();
        $this->post(route('it.switch-view'), ['role' => 'wali_kelas']);

        $this->get(route('walikelas.dashboard'))
            ->assertOk()
            ->assertSee('X RPL 1');
    }

    public function test_select_target_saat_wali_kelas_harus_guru_wali_kelas()
    {
        $this->loginPetugasIt();
        $this->post(route('it.switch-view'), ['role' => 'wali_kelas']);

        $guruMapel = User::withoutGlobalScope(TestingDataScope::class)
            ->where('username', 'guru.tester')
            ->firstOrFail();
        $waliTarget = User::withoutGlobalScope(TestingDataScope::class)
            ->where('username', 'wali.tester')
            ->firstOrFail();

        // Guru Mapel biasa bukan Wali Kelas → ditolak.
        $this->withHeaders(['Referer' => route('walikelas.dashboard')])
            ->post(route('it.impersonate-target'), ['impersonate_target_id' => $guruMapel->id])
            ->assertStatus(422);

        // Wali Kelas testing → diterima.
        $this->withHeaders(['Referer' => route('walikelas.dashboard')])
            ->post(route('it.impersonate-target'), ['impersonate_target_id' => $waliTarget->id])
            ->assertRedirect(route('walikelas.dashboard'));

        $this->assertSame((int) session('impersonate_target_id'), (int) $waliTarget->id);
    }

    public function test_switch_view_guru_mapel_hapus_target_wali_kelas()
    {
        $this->loginPetugasIt();
        $this->post(route('it.switch-view'), ['role' => 'wali_kelas']);
        $this->assertNotNull(session('impersonate_target_id'));

        $this->post(route('it.switch-view'), ['role' => 'guru_mapel'])
            ->assertRedirect(route('guru.dashboard'));

        $this->assertSame('guru_mapel', session('active_role'));
        $this->assertNotNull(session('impersonate_target_id'), 'Sesi guru tetap dipertahankan antar view guru.');
    }

    // --- Empty Target Context: portal Wali Kelas wajib kosong saat target belum dipilih ---

    public function test_wali_kelas_tanpa_target_dashboard_menampilkan_layout_kosong(): void
    {
        $this->loginPetugasIt();
        $this->post(route('it.switch-view'), ['role' => 'wali_kelas']);

        // Bersihkan target — user memilih "Akun Saya (Tanpa Target)"
        $this->withHeaders(['Referer' => route('walikelas.dashboard')])
            ->post(route('it.impersonate-target'), ['impersonate_target_id' => '']);
        $this->assertNull(session('impersonate_target_id'));

        $this->get(route('walikelas.dashboard'))
            ->assertOk()
            ->assertSee('Total Siswa Bimbingan')
            ->assertSee('Siswa Terlambat')
            ->assertSee('Siswa Perlu Perhatian')
            ->assertSee('Siswa Dispen')
            ->assertSee('Tidak ada siswa kelas bimbingan Anda yang di-dispensasi hari ini.');
    }

    public function test_wali_kelas_tanpa_target_rekap_absen_kosong(): void
    {
        $this->loginPetugasIt();
        $this->post(route('it.switch-view'), ['role' => 'wali_kelas']);

        $this->withHeaders(['Referer' => route('walikelas.rekap-absen')])
            ->post(route('it.impersonate-target'), ['impersonate_target_id' => '']);

        $this->get(route('walikelas.rekap-absen'))
            ->assertOk()
            ->assertSee('Rekapitulasi Kehadiran')
            ->assertSee('Belum ada data presensi untuk kelas bimbingan Anda.');
    }

    public function test_wali_kelas_tanpa_target_riwayat_jurnal_kosong(): void
    {
        $this->loginPetugasIt();
        $this->post(route('it.switch-view'), ['role' => 'wali_kelas']);

        $this->withHeaders(['Referer' => route('walikelas.riwayat-jurnal')])
            ->post(route('it.impersonate-target'), ['impersonate_target_id' => '']);

        $this->get(route('walikelas.riwayat-jurnal'))
            ->assertOk()
            ->assertSee('Riwayat Jurnal Mengajar')
            ->assertSee('Belum ada jurnal mengajar untuk kelas bimbingan Anda.');
    }

    public function test_wali_kelas_tanpa_target_siswa_bermasalah_kosong(): void
    {
        $this->loginPetugasIt();
        $this->post(route('it.switch-view'), ['role' => 'wali_kelas']);

        $this->withHeaders(['Referer' => route('walikelas.siswa-bermasalah')])
            ->post(route('it.impersonate-target'), ['impersonate_target_id' => '']);

        $this->get(route('walikelas.siswa-bermasalah'))
            ->assertOk()
            ->assertSee('Total Siswa Kelas')
            ->assertSee('Siswa Perlu Perhatian')
            ->assertSee('Tidak ada siswa di kelas wali Anda.');
    }

    public function test_wali_kelas_tanpa_target_store_tindak_lanjut_ditolak(): void
    {
        $this->loginPetugasIt();
        $this->post(route('it.switch-view'), ['role' => 'wali_kelas']);

        $this->withHeaders(['Referer' => route('walikelas.siswa-bermasalah')])
            ->post(route('it.impersonate-target'), ['impersonate_target_id' => '']);

        $this->post(route('walikelas.siswa-bermasalah.store'), [
            'id_siswa' => 1,
            'jenis_tindakan' => 'panggil_ortu',
            'status' => 'belum',
        ])->assertStatus(403);
    }

    public function test_wali_kelas_dengan_target_tetap_menampilkan_data(): void
    {
        $this->loginPetugasIt();
        $this->post(route('it.switch-view'), ['role' => 'wali_kelas']);

        $this->assertNotNull(session('impersonate_target_id'), 'Target default harus terisi setelah switch.');

        $this->get(route('walikelas.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard Wali Kelas')
            ->assertSee('X RPL 1');
    }

    // --- Sidebar: Link "Dashboard" utama harus mengikuti role aktif saat impersonasi ---

    public function test_sidebar_dashboard_mode_guru_mapel_menuju_guru_dashboard(): void
    {
        $this->loginPetugasIt();
        $this->switchToGuruMapel();

        $this->get(route('guru.dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('guru.dashboard').'" class="nav-btn active"', false)
            ->assertDontSee('<a href="'.route('piket.dashboard').'" class="nav-btn', false);
    }

    public function test_sidebar_dashboard_mode_guru_piket_menuju_piket_dashboard(): void
    {
        $this->loginPetugasIt();
        $this->post(route('it.switch-view'), ['role' => 'guru_piket'])
            ->assertRedirect(route('piket.dashboard'));

        $this->get(route('piket.dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('piket.dashboard').'" class="nav-btn active"', false)
            ->assertDontSee('<a href="'.route('guru.dashboard').'" class="nav-btn', false);
    }

    public function test_sidebar_dashboard_mode_wali_kelas_menuju_walikelas_dashboard(): void
    {
        $this->loginPetugasIt();
        $this->post(route('it.switch-view'), ['role' => 'wali_kelas']);

        $this->get(route('walikelas.dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('walikelas.dashboard').'" class="nav-btn active"', false)
            ->assertDontSee('<a href="'.route('guru.dashboard').'" class="nav-btn', false);
    }
}
