<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SiswaSearchTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'nama' => 'Admin TU',
            'username' => 'admin_'.Str::random(8),
            'password' => bcrypt('password'),
            'role' => 'admin',
            'sub_role' => 'admin_tu',
            'is_active' => true,
        ]);
    }

    private function makeSiswa(int $i, string $nama): void
    {
        $k = Kelas::first() ?? Kelas::create(['tingkat' => 'X', 'nama_kelas' => 'TKJ 1']);
        Siswa::create([
            'nisn' => sprintf('%010d', 1000000000 + $i),
            'nis' => (string) (10000 + $i),
            'nama' => $nama,
            'id_kelas' => $k->id,
            'jenis_kelamin' => 'L',
            'status_siswa' => 'Aktif',
        ]);
    }

    public function test_search_matches_partial_name_via_get(): void
    {
        $user = $this->makeUser();
        $this->makeSiswa(1, 'ARIEL PRATAMA');
        $this->makeSiswa(2, 'BUDI SANTOSO');

        $response = $this->actingAs($user)->get(route('siswa.index', ['search' => 'bud']));

        $response->assertOk();
        $response->assertSee('BUDI SANTOSO');
        $response->assertDontSee('ARIEL PRATAMA');
    }

    public function test_search_matches_phonetic_for_aril_if_soundex_available(): void
    {
        $user = $this->makeUser();
        $this->makeSiswa(1, 'ARIEL PRATAMA');

        $supports = false;
        try {
            DB::selectOne('SELECT SOUNDEX("aril") AS s');
            $supports = true;
        } catch (\Throwable $e) {
            $supports = false;
        }

        if (! $supports) {
            $this->markTestSkipped('DB tidak mendukung SOUNDEX (butuh MySQL/MariaDB).');
        }

        // Cari "aril" → harus muncul "ARIEL" secara fonetik.
        $response = $this->actingAs($user)->get(route('siswa.index', ['search' => 'aril']));

        $response->assertOk();
        $response->assertSee('ARIEL PRATAMA');
    }
}
