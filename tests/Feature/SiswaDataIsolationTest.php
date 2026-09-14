<?php

namespace Tests\Feature;

use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class SiswaDataIsolationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UserSeeder::class);
    }

    private function createKelas(): Kelas
    {
        $jurusan = Jurusan::create(['kode_jurusan' => $this->faker->unique()->bothify('JUR-####'), 'nama_jurusan' => 'Rekayasa Perangkat Lunak']);

        return Kelas::create([
            'tingkat' => 'X',
            'nama_kelas' => 'RPL 1',
            'id_jurusan' => $jurusan->id,
        ]);
    }

    private function createSiswa(array $overrides = []): Siswa
    {
        return Siswa::create(array_merge([
            'nisn' => $this->faker->unique()->numerify('#############'),
            'nis' => $this->faker->unique()->numerify('#####'),
            'nama' => $this->faker->name(),
            'jenis_kelamin' => 'L',
            'id_kelas' => $this->createKelas()->id,
            'status_siswa' => 'Aktif',
        ], $overrides));
    }

    public function test_qa_tester_siswa_page_hanya_menampilkan_data_testing(): void
    {
        // Data produksi (is_testing_data = 0) dibuat oleh admin.
        $admin = User::where('email', 'admin@school.id')->firstOrFail();
        $produksi = $this->actingAs($admin)->createSiswa(['nama' => 'SISWA PRODUKSI TERKUNCI']);
        $this->assertFalse((bool) $produksi->is_testing_data);

        // Data testing (is_testing_data = 1) dibuat oleh QA Tester.
        $qa = User::where('email', 'qa@school.id')->firstOrFail();
        $testing = $this->actingAs($qa)->createSiswa(['nama' => 'SISWA TESTING QA']);
        $this->assertTrue((bool) $testing->is_testing_data);

        // QA Tester: halaman siswa hanya berisi data testing / kosong dari data produksi.
        $response = $this->actingAs($qa)->get(route('siswa.index'));
        $response->assertOk();
        $response->assertSee('SISWA TESTING QA');
        $response->assertDontSee('SISWA PRODUKSI TERKUNCI');
        $this->assertSame(1, Siswa::count());

        // Admin (non-IT): hanya melihat data real, data testing tidak tercampur.
        $response = $this->actingAs($admin)->get(route('siswa.index'));
        $response->assertOk();
        $response->assertSee('SISWA PRODUKSI TERKUNCI');
        $response->assertDontSee('SISWA TESTING QA');
        $this->assertSame(1, Siswa::count());
    }

    public function test_petugas_it_siswa_page_hanya_menampilkan_data_testing(): void
    {
        $admin = User::where('email', 'admin@school.id')->firstOrFail();
        $produksi = $this->actingAs($admin)->createSiswa(['nama' => 'SISWA PRODUKSI IT CHECK']);

        $it = User::where('email', 'it@school.id')->firstOrFail();
        $testing = $this->actingAs($it)->createSiswa(['nama' => 'SISWA TESTING IT']);
        $this->assertTrue((bool) $testing->is_testing_data);

        $response = $this->actingAs($it)->get(route('siswa.index'));
        $response->assertOk();
        $response->assertSee('SISWA TESTING IT');
        $response->assertDontSee('SISWA PRODUKSI IT CHECK');

        // Data produksi tetap aman untuk admin.
        $response = $this->actingAs($admin)->get(route('siswa.index'));
        $response->assertOk();
        $response->assertSee('SISWA PRODUKSI IT CHECK');
        $response->assertDontSee('SISWA TESTING IT');
    }

    public function test_petugas_it_impersonating_siswa_page_tetap_data_testing_saja(): void
    {
        $admin = User::where('email', 'admin@school.id')->firstOrFail();
        $produksi = $this->actingAs($admin)->createSiswa(['nama' => 'SISWA PRODUKSI IMPERSONASI']);

        $it = User::where('email', 'it@school.id')->firstOrFail();
        $this->actingAs($it);
        session(['active_role' => 'admin_tu']);

        // isTestingUser() tetap TRUE walau profil aktif berganti (Switch View As).
        $this->assertTrue($it->isTestingUser());

        $testing = $this->createSiswa(['nama' => 'SISWA TESTING IMPERSONASI']);
        $this->assertTrue((bool) $testing->is_testing_data);

        $response = $this->get(route('siswa.index'));
        $response->assertOk();
        $response->assertSee('SISWA TESTING IMPERSONASI');
        $response->assertDontSee('SISWA PRODUKSI IMPERSONASI');
    }
}
