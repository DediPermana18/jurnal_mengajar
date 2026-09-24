<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Struktur sidebar navigasi area Admin/TU yang dirombak:
 *
 *  - Section Header: UTAMA, KELOLA AKUN, DATA MASTER (AKADEMIK),
 *    JADWAL & PIKET, SISTEM (footer), KURIKULUM & LAPORAN (SuperAdmin).
 *  - "Akun Guru" & "Data Guru" menuju halaman yang sama (admin/guru).
 *  - Accordion dropdown: hanya 1 terbuka (x-data openMenu).
 *  - Gate role dipertahankan persis seperti sebelumnya.
 */
class SidebarNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUser(string $role, ?string $subRole = null): User
    {
        return User::create([
            'nama' => 'User '.Str::random(5),
            'username' => 'user_'.Str::random(8),
            'password' => bcrypt('password'),
            'role' => $role,
            'sub_role' => $subRole,
            'no_hp' => '085123456789',
            'is_active' => true,
        ]);
    }

    public function test_sidebar_petugas_tu_menampilkan_grup_baru_dan_menghilangkan_label_lama(): void
    {
        $user = $this->makeUser('admin', 'petugas_tu');

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            // Section header baru
            ->assertSee('UTAMA')
            ->assertSee('KELOLA AKUN')
            ->assertSee('DATA MASTER (AKADEMIK)')
            ->assertSee('JADWAL & PIKET', false)
            ->assertSee('SISTEM')
            // Menu KELOLA AKUN ("Akun Guru" dihapus agar tidak dobel dengan Data Guru)
            ->assertSee('Akun Admin')
            ->assertSee('Pengajuan Reset Password')
            // Menu DATA MASTER (AKADEMIK)
            ->assertSee('Data Guru')
            ->assertSee('Data Akademik')
            ->assertSee('Data Ruangan')
            ->assertSee('Import Data')
            // Menu JADWAL & PIKET
            ->assertSee('Jadwal Pelajaran')
            ->assertSee('Jadwal Piket Guru')
            // "Akun Guru" dihapus (redundan) — cukup "Data Guru" saja
            ->assertDontSee('<span>Akun Guru</span>', false)
            // Label lama sudah diganti (klaim elemen sidebar, bukan konten kartu dashboard)
            ->assertDontSee('<span>Kelola User</span>', false)
            ->assertDontSee('<span>Data Pengguna / Guru</span>', false)
            // Khusus SuperAdmin: tidak muncul untuk TU
            ->assertDontSee('KURIKULUM & LAPORAN')
            ->assertDontSee('Portal Waka SDM');
    }

    public function test_halaman_guru_hanya_menyala_satu_menu_sidebar(): void
    {
        $user = $this->makeUser('admin', 'petugas_tu');

        $html = $this->actingAs($user)
            ->get(route('guru.index'))
            ->assertOk()
            ->getContent();

        // "Akun Guru" sudah tidak ada di sidebar mana pun.
        $this->assertStringNotContainsString('<span>Akun Guru</span>', $html);

        // Di halaman admin/guru hanya "Data Guru" yang aktif (nav-btn active = 1).
        $this->assertSame(1, substr_count($html, 'nav-btn active'));
        $this->assertStringContainsString('<span>Data Guru</span>', $html);
    }

    public function test_dashboard_hanya_menyala_pada_url_dashboard(): void
    {
        $user = $this->makeUser('admin', 'petugas_tu');

        // URL '/' (route 'home') => Dashboard aktif, tepat satu menu menyala.
        $htmlHome = $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->getContent();
        $this->assertSame(1, substr_count($htmlHome, 'nav-btn active'));

        // URL '/dashboard' (route 'dashboard') => Dashboard tetap aktif.
        $htmlDb = $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();
        $this->assertSame(1, substr_count($htmlDb, 'nav-btn active'));

        // URL '/admin/jurnal' BUKAN halaman dashboard => Dashboard TIDAK menyala
        // (sidebar Admin/TU memang tidak memiliki item menu Jurnal).
        $htmlJurnal = $this->actingAs($user)
            ->get(route('jurnal.index'))
            ->assertOk()
            ->getContent();
        $this->assertSame(0, substr_count($htmlJurnal, 'nav-btn active'));
    }

    public function test_sidebar_super_admin_menampilkan_menu_kurikulum_dan_laporan(): void
    {
        // role=admin & sub_role=null => Super Admin
        $user = $this->makeUser('admin', null);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Data Mata Pelajaran')
            ->assertSee('KURIKULUM & LAPORAN', false)
            ->assertSee('Laporan KBM')
            ->assertSee('Portal Waka SDM');
    }

    public function test_sidebar_accordion_memakai_state_open_menu_bersama(): void
    {
        $user = $this->makeUser('admin', 'petugas_tu');

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('x-data="{ openMenu:', false)
            // Halaman beranda + siswa tidak aktif => dropdown tertutup
            ->assertSee("openMenu: ''", false);
    }

    public function test_dropdown_data_akademik_terbuka_pada_halaman_siswa(): void
    {
        $user = $this->makeUser('admin', 'petugas_tu');

        $this->actingAs($user)
            ->get(route('siswa.index'))
            ->assertOk()
            ->assertSee("openMenu: 'dataAkademik'", false)
            // Submenu Data Akademik hadir saat dropdown aktif
            ->assertSee('dropdownDataAkademik');
    }

    public function test_dropdown_jadwal_pelajaran_terbuka_pada_halaman_plotting_jadwal(): void
    {
        $user = $this->makeUser('admin', 'petugas_tu');

        $this->actingAs($user)
            ->get(route('admin.jadwal.index'))
            ->assertOk()
            ->assertSee("openMenu: 'jadwalPelajaran'", false);
    }

    public function test_sidebar_guru_dan_role_lain_tidak_mendapat_grup_admin(): void
    {
        $guru = $this->makeUser('guru');
        $this->actingAs($guru)
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee('KELOLA AKUN')
            ->assertDontSee('DATA MASTER (AKADEMIK)')
            ->assertDontSee('Akun Guru')
            ->assertDontSee('SISTEM');

        $wakaKurikulum = $this->makeUser('admin', 'waka_kurikulum');
        $this->actingAs($wakaKurikulum)
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee('KELOLA AKUN')
            ->assertDontSee('Akun Guru')
            // Footer SISTEM hanya untuk area admin/TU
            ->assertDontSee('SISTEM');
    }
}