<?php

namespace Tests\Feature;

use App\Models\DispensasiSiswa;
use App\Models\JadwalPiket;
use App\Models\Kelas;
use App\Models\PengaturanJadwal;
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
            'nama'      => 'User ' . Str::random(5),
            'username'  => 'user_' . Str::random(8),
            'password'  => bcrypt('password'),
            'role'      => $role,
            'sub_role'  => 'guru',
            'is_active' => true,
        ], $extra));
    }

    public function test_dispensasi_index_renders_kirim_wa_button(): void
    {
        PengaturanJadwal::getSetting()->update(['no_wa_waka' => '081234567890']);

        $piket = $this->makeUser('guru');
        JadwalPiket::create(['hari' => 'Senin', 'user_id' => $piket->id]);

        $kelas = Kelas::create(['nama_kelas' => 'XII IPA 1', 'tingkat' => 'XII']);

        $siswa = Siswa::create([
            'nama'          => 'Budi Santoso',
            'nisn'          => '1234567890',
            'nis'           => '12345',
            'jenis_kelamin' => 'L',
            'id_kelas'      => $kelas->id,
            'status_siswa'  => 'Aktif',
        ]);

        DispensasiSiswa::create([
            'id_siswa'        => $siswa->id,
            'id_guru_piket'   => $piket->id,
            'tanggal'         => now()->toDateString(),
            'jam_ke'          => '3,4',
            'alasan'          => 'Rapat organisasi siswa',
            'status'          => DispensasiSiswa::STATUS_PENDING,
            'approval_token'  => (string) Str::uuid(),
        ]);

        $this->actingAs($piket)
            ->get(route('piket.dispensasi.index'))
            ->assertOk()
            ->assertSee('Kirim WA ke Waka')
            ->assertSee('6281234567890');
    }
}