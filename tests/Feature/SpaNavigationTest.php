<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Navigasi SPA (Unpoly) pada sidebar — tanpa full page reload:
 *
 *  - Unpoly dimuat sebagai aset CDN (CSS + JS) di <head> sebelum @vite.
 *  - <main id="page-content"> adalah target swap → sidebar & header tidak
 *    pernah di-reload (scroll sidebar & dropdown terbuka tetap bertahan).
 *  - @stack('styles') & @stack('scripts') dirender DI DALAM <main> supaya aset
 *    per-halaman ikut terbawa & dieksekusi saat fragmen di-swap.
 *  - Bootstrap bundle dimuat SEBELUM <main> agar @push('scripts') yang memakai
 *    bootstrap.Modal/Toast tetap berjalan pada load awal.
 */
class SpaNavigationTest extends TestCase
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

    public function test_layout_memuat_aset_unpoly_dan_target_swap_main(): void
    {
        $user = $this->makeUser('admin', 'petugas_tu');

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            // Unpoly (CSS + JS) dimuat dari CDN
            ->assertSee('https://cdn.jsdelivr.net/npm/unpoly@3.14.3/unpoly.js', false)
            ->assertSee('https://cdn.jsdelivr.net/npm/unpoly@3.14.3/unpoly.css', false)
            // Target swap SPA: elemen <main id="page-content" class="page-content …">
            ->assertSee('<main id="page-content" class="page-content', false);
    }

    public function test_bootstrap_bundle_dimuat_sebelum_main(): void
    {
        $user = $this->makeUser('admin', 'petugas_tu');

        $html = $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->getContent();

        $posBootstrap = strpos($html, 'bootstrap.bundle.min.js');
        // Hindari kecocokan dengan teks komentar di <head> yang menyebut id yang
        // sama — pakai penanda lengkap elemen <main>.
        $posMain = strpos($html, '<main id="page-content" class="page-content');

        $this->assertNotFalse($posBootstrap, 'Bootstrap bundle harus dirender.');
        $this->assertNotFalse($posMain, '<main id="page-content"> harus dirender.');
        $this->assertLessThan($posMain, $posBootstrap, 'Bootstrap bundle harus dimuat sebelum <main>.');
    }

    public function test_aset_per_halaman_dirender_di_dalam_main(): void
    {
        $user = $this->makeUser('admin', 'petugas_tu');

        // Halaman ini meng-@push('styles') (.table-reset) dan @push('scripts')
        // (function copyResetLink) — keduanya wajib berada DALAM fragmen <main>.
        $html = $this->actingAs($user)
            ->get(route('admin.reset-requests.index'))
            ->assertOk()
            ->getContent();

        $posMain = strpos($html, '<main id="page-content" class="page-content');
        $posClose = strpos($html, '</main>', $posMain);

        $this->assertNotFalse($posMain);
        $this->assertNotFalse($posClose);

        // Style spesifik halaman ikut dalam fragmen swap.
        $posStyle = strpos($html, '.table-reset th');
        $this->assertNotFalse($posStyle, 'Pushed style halaman harus dirender.');
        $this->assertTrue(
            $posStyle > $posMain && $posStyle < $posClose,
            'Pushed style harus berada di dalam <main> (agar ikut ter-swap).'
        );

        // Script spesifik halaman ikut dalam fragmen swap.
        $posScript = strpos($html, 'function copyResetLink');
        $this->assertNotFalse($posScript, 'Pushed script halaman harus dirender.');
        $this->assertTrue(
            $posScript > $posMain && $posScript < $posClose,
            'Pushed script harus berada di dalam <main> (agar ikut ter-swap).'
        );
    }

    public function test_tidak_ada_stack_scripts_di_luar_main(): void
    {
        $user = $this->makeUser('admin', 'petugas_tu');

        // Setelah <main> ditutup tidak boleh ada <script> hasil @stack halaman
        // (mis. definisi fungsi page) yang ter-render di luar fragmen swap.
        $html = $this->actingAs($user)
            ->get(route('admin.reset-requests.index'))
            ->assertOk()
            ->getContent();

        $posClose = strpos($html, '</main>');
        $posCopyReset = strpos($html, 'function copyResetLink');

        $this->assertNotFalse($posClose);
        $this->assertNotFalse($posCopyReset);
        $this->assertTrue($posCopyReset < $posClose, 'Pushed script tidak boleh berada di luar <main>.');
    }
}