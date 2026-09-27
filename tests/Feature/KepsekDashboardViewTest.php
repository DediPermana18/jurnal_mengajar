<?php

namespace Tests\Feature;

use App\Models\IzinGuru;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Dashboard Kepala Sekolah — memastikan route kepsek.dashboard menampilkan
 * VIEW DASHBOARD (ringkasan/overview), BUKAN view rekap-izin (Modul
 * Persetujuan). Perbaikan: KepsekController::dashboard() tidak lagi
 * meneruskan ke rekapIzin().
 */
class KepsekDashboardViewTest extends TestCase
{
    use RefreshDatabase;

    protected function makeKepsek(): User
    {
        return User::create([
            'nama' => 'Dr. Kepsek M.Pd.',
            'username' => 'kepsek_'.Str::random(5),
            'password' => bcrypt('password'),
            'role' => 'admin',
            'sub_role' => 'kepsek',
            'kode_aktivasi' => 'KPS-SIGN-2026',
            'is_active' => true,
        ]);
    }

    protected function makeGuru(): User
    {
        return User::create([
            'nama' => 'Guru Dashboard '.Str::random(5),
            'username' => 'guru_'.Str::random(5),
            'password' => bcrypt('password'),
            'role' => 'guru',
            'is_active' => true,
        ]);
    }

    protected function makeIzin(User $guru, string $status, array $extra = []): IzinGuru
    {
        return IzinGuru::create(array_merge([
            'user_id' => $guru->id,
            'tanggal' => now()->toDateString(),
            'kategori_izin' => 'sakit',
            'alasan' => 'AlasanUnik_'.Str::random(6),
            'status' => $status,
            'token_kepsek' => $status === IzinGuru::STATUS_PENDING_KEPSEK ? (string) Str::uuid() : null,
        ], $extra));
    }

    public function test_kepsek_dashboard_menampilkan_view_dashboard_bukan_rekap_izin(): void
    {
        $kepsek = $this->makeKepsek();
        $guru = $this->makeGuru();
        $this->makeIzin($guru, IzinGuru::STATUS_PENDING_KEPSEK);

        $response = $this->actingAs($kepsek)->get(route('kepsek.dashboard'));

        $response->assertOk();

        // Konten KHAS Dashboard (bukan modul persetujuan):
        $response->assertSee('Dashboard Kepala Sekolah');
        $response->assertSee('Menunggu Persetujuan Anda');
        $response->assertSee('Antrean Persetujuan Terbaru');
        $response->assertSee('Aktivitas Izin Terbaru');

        // Konten KHAS rekap-izin HARUS tidak muncul di dashboard:
        $response->assertDontSee('Cari nama guru atau NIP...');
        $response->assertDontSee('Persetujuan & Rekap Izin Guru');
    }

    public function test_kepsek_dashboard_menampilkan_statistik_dan_antrean(): void
    {
        $kepsek = $this->makeKepsek();
        $guru = $this->makeGuru();

        $pending = $this->makeIzin($guru, IzinGuru::STATUS_PENDING_KEPSEK);
        $approved = $this->makeIzin($guru, IzinGuru::STATUS_DISETUJUI);
        $rejected = $this->makeIzin($guru, IzinGuru::STATUS_DITOLAK);

        $response = $this->actingAs($kepsek)->get(route('kepsek.dashboard'));

        $response->assertOk();

        // Antrean memuat pengajuan pending yang paling baru.
        $response->assertSee($guru->nama);
        $response->assertSee($pending->alasan);
        $response->assertSee('Menunggu Kepsek');

        // Data approved & rejected tidak hilang dari total.
        $response->assertSee(IzinGuru::STATUS_LABELS[IzinGuru::STATUS_DISETUJUI]);
        $response->assertSee(IzinGuru::STATUS_LABELS[IzinGuru::STATUS_DITOLAK]);
    }

    public function test_kepsek_dashboard_masuk_saat_impersonasi_petugas_it(): void
    {
        $it = User::create([
            'nama' => 'Petugas IT',
            'username' => 'it_'.Str::random(5),
            'password' => bcrypt('password'),
            'role' => 'petugas_it',
            'is_active' => true,
        ]);

        $this->actingAs($it);
        session(['active_role' => 'kepsek']);

        $this->get(route('kepsek.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard Kepala Sekolah');
    }
}