<?php

namespace Tests\Feature;

use App\Models\IzinGuru;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class KurikulumDashboardTest extends TestCase
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

    protected function makeUser(string $role, ?string $subRole = null): User
    {
        return User::create([
            'nama'      => 'User ' . Str::random(5),
            'username'  => 'user_' . Str::random(8),
            'password'  => bcrypt('password'),
            'role'      => $role,
            'sub_role'  => $subRole,
            'is_active' => true,
        ]);
    }

    public function test_dashboard_renders_with_sections()
    {
        $waka = $this->makeUser('admin', 'waka_kurikulum');
        $guru = $this->makeUser('guru', 'guru_mapel');

        Kelas::create(['nama_kelas' => 'X IPA 1', 'tingkat' => 'X']);
        MataPelajaran::create(['nama_mapel' => 'Matematika', 'kode_mapel' => 'MTK']);

        IzinGuru::create([
            'user_id' => $guru->id,
            'tanggal' => '2026-08-10',
            'alasan'  => 'Sakit demam tinggi',
            'status'  => IzinGuru::STATUS_PENDING_WAKA,
        ]);

        $res = $this->actingAs($waka)->get(route('kurikulum.dashboard'));
        $res->assertOk();
        $res->assertSee('Akses Cepat Modul Kurikulum');
        $res->assertSee('Approval Izin Guru');
        $res->assertSee('Data Mata Pelajaran');
        $res->assertSee('Laporan KBM');
        $res->assertSee('Daftar Izin Guru Menunggu Approval');
        $res->assertSee('Ringkasan KBM Hari Ini');
        $res->assertSee('Sakit demam tinggi');
        $res->assertSee('Setujui');
    }

    public function test_dashboard_empty_state_when_no_pending()
    {
        $waka = $this->makeUser('admin', 'waka_kurikulum');

        $res = $this->actingAs($waka)->get(route('kurikulum.dashboard'));
        $res->assertOk();
        $res->assertSee('Tidak ada pengajuan izin yang menunggu persetujuan.');
    }
}
