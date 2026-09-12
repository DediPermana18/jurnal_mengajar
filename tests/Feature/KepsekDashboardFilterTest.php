<?php

namespace Tests\Feature;

use App\Models\IzinGuru;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class KepsekDashboardFilterTest extends TestCase
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
            'nama' => 'Guru Test '.Str::random(5),
            'username' => 'guru_'.Str::random(5),
            'password' => bcrypt('password'),
            'role' => 'guru',
            'is_active' => true,
        ]);
    }

    public function test_kepsek_dashboard_defaults_to_pending_kepsek_filter(): void
    {
        $kepsek = $this->makeKepsek();
        $guru = $this->makeGuru();

        $pendingItem = IzinGuru::create([
            'user_id' => $guru->id,
            'tanggal' => now()->toDateString(),
            'kategori_izin' => 'sakit',
            'alasan' => 'AlasanIzinPendingKepsekUnik',
            'status' => IzinGuru::STATUS_PENDING_KEPSEK,
            'token_kepsek' => (string) Str::uuid(),
        ]);

        $approvedItem = IzinGuru::create([
            'user_id' => $guru->id,
            'tanggal' => now()->toDateString(),
            'kategori_izin' => 'sakit',
            'alasan' => 'AlasanIzinDisetujuiUnik',
            'status' => IzinGuru::STATUS_DISETUJUI,
        ]);

        $response = $this->actingAs($kepsek)->get(route('kepsek.rekap-izin'));

        $response->assertOk();
        $response->assertSee('Menunggu Kepsek');
        $response->assertSee('AlasanIzinPendingKepsekUnik');
        $response->assertDontSee('AlasanIzinDisetujuiUnik');

        $this->assertEquals('Menunggu Kepsek', $pendingItem->status_label);
        $this->assertStringContainsString('bg-warning-subtle', $pendingItem->status_badge);
    }

    public function test_kepsek_dashboard_filters_approved_when_selected(): void
    {
        $kepsek = $this->makeKepsek();
        $guru = $this->makeGuru();

        IzinGuru::create([
            'user_id' => $guru->id,
            'tanggal' => now()->toDateString(),
            'kategori_izin' => 'sakit',
            'alasan' => 'AlasanIzinPendingKepsekUnik',
            'status' => IzinGuru::STATUS_PENDING_KEPSEK,
        ]);

        IzinGuru::create([
            'user_id' => $guru->id,
            'tanggal' => now()->toDateString(),
            'kategori_izin' => 'sakit',
            'alasan' => 'AlasanIzinDisetujuiUnik',
            'status' => IzinGuru::STATUS_DISETUJUI,
        ]);

        $response = $this->actingAs($kepsek)->get(route('kepsek.rekap-izin', ['status' => 'disetujui']));

        $response->assertOk();
        $response->assertSee('AlasanIzinDisetujuiUnik');
        $response->assertDontSee('AlasanIzinPendingKepsekUnik');
    }
}
