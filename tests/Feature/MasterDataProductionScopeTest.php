<?php

namespace Tests\Feature;

use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Ruangan;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Isolasi data testing pada area Data Master.
 *
 * User operasional biasa (non-IT / non-QA / bukan akun sandbox / bukan Super
 * Admin) HANYA boleh melihat data PRODUKSI (is_testing_data = 0) di seluruh
 * halaman Data Master: Kelas, Guru, Siswa, Jurusan, Mata Pelajaran, dan
 * Ruangan — termasuk saat IT sedang mengaktifkan Tahun Ajaran testing. Kasus
 * TA testing inilah yang sebelumnya "menumpangi" Kelas ke partisi testing,
 * karena Kelas sempat menerapkan isolasi berbasis konteks lingkungan aktif.
 *
 * Petugas IT / QA Tester / akun sandbox sebaliknya hanya melihat partisi
 * testing (is_testing_data = 1).
 */
class MasterDataProductionScopeTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UserSeeder::class);
    }

    private function adminUser(): User
    {
        return User::where('email', 'admin@school.id')->firstOrFail();
    }

    private function itUser(): User
    {
        return User::where('email', 'it@school.id')->firstOrFail();
    }

    private function qaUser(): User
    {
        return User::where('email', 'qa@school.id')->firstOrFail();
    }

    private function createJurusanAs(User $actor, string $nama, string $kode): Jurusan
    {
        $this->actingAs($actor);

        return Jurusan::create(['kode_jurusan' => $kode, 'nama_jurusan' => $nama]);
    }

    private function createKelasAs(User $actor, string $namaKelas, int $idJurusan): Kelas
    {
        $this->actingAs($actor);

        return Kelas::create([
            'tingkat' => 'X',
            'nama_kelas' => $namaKelas,
            'id_jurusan' => $idJurusan,
        ]);
    }

    private function createGuruAs(User $actor, string $nama): User
    {
        $this->actingAs($actor);

        return User::create([
            'nama' => $nama,
            'username' => strtolower(Str::slug($nama)).'_'.Str::random(4),
            'password' => bcrypt('password'),
            'role' => User::ROLE_GURU,
            'is_active' => true,
        ]);
    }

    private function createSiswaAs(User $actor, string $nama, int $idKelas, string $jk): Siswa
    {
        $this->actingAs($actor);

        return Siswa::create([
            'nisn' => $this->faker->unique()->numerify('#############'),
            'nis' => $this->faker->unique()->numerify('#####'),
            'nama' => $nama,
            'jenis_kelamin' => $jk,
            'id_kelas' => $idKelas,
            'status_siswa' => 'Aktif',
        ]);
    }

    private function createTahunAjaranAs(User $actor, string $tahun, string $semester = 'Ganjil', ?bool $testing = null): TahunAjaran
    {
        $this->actingAs($actor);

        $attrs = [
            'tahun_ajaran' => $tahun,
            'semester' => $semester,
            'is_active' => false,
        ];

        // Bila $testing null → biarkan model event 'creating' menentukan flag
        // sesuai peran aktor (opsi ini juga menguji sisi tulis hook).
        if ($testing !== null) {
            $attrs['is_testing_data'] = $testing;
        }

        return TahunAjaran::create($attrs);
    }

    /**
     * Kasus kunci: Tahun Ajaran testing AKTIF. User operasional biasa harus
     * tetap melihat KELAS PRODUKSI saja (bukan ditumpangi ke partisi testing),
     * sedangkan Petugas IT hanya melihat kelas testing.
     */
    public function test_kelas_index_user_operasional_hanya_melihat_data_real_meski_ta_testing_aktif(): void
    {
        $admin = $this->adminUser();
        $it = $this->itUser();

        // IT mengaktifkan Tahun Ajaran testing (simulasi persiapan TA baru).
        $this->actingAs($it);
        TahunAjaran::create([
            'tahun_ajaran' => '2026/2027-'.$this->faker->unique()->numerify('####'),
            'semester' => 'Ganjil',
            'is_active' => true,
            'is_testing_data' => true,
        ]);
        $this->assertTrue(User::currentTestingStatus(), 'konteks testing harus aktif');

        // Kelas PRODUKSI dibuat oleh admin (user operasional biasa).
        $jurusanProduksi = $this->createJurusanAs($admin, 'JURUSAN PRODUKSI KLS', 'JURS-PROD');
        $kelasProduksi = $this->createKelasAs($admin, 'X RPL UJI-PROD', $jurusanProduksi->id);
        $this->assertFalse((bool) $kelasProduksi->is_testing_data, 'kelas produksi harus ditulis ke partisi real meski TA testing aktif');

        // Kelas TESTING dibuat oleh Petugas IT.
        $jurusanTesting = $this->createJurusanAs($it, 'JURUSAN TESTING KLS', 'JURS-TEST');
        $kelasTesting = $this->createKelasAs($it, 'X RPL UJI-TESTING', $jurusanTesting->id);
        $this->assertTrue((bool) $kelasTesting->is_testing_data);

        // Halaman Kelas untuk admin: HANYA data produksi.
        $response = $this->actingAs($admin)->get(route('kelas.index'));
        $response->assertOk();
        $response->assertSee('X RPL UJI-PROD');
        $response->assertDontSee('X RPL UJI-TESTING');

        // Halaman Kelas untuk Petugas IT: HANYA data testing.
        $response = $this->actingAs($it)->get(route('kelas.index'));
        $response->assertOk();
        $response->assertSee('X RPL UJI-TESTING');
        $response->assertDontSee('X RPL UJI-PROD');
    }

    public function test_kelas_index_qa_tester_hanya_lihat_testing_dan_admin_hanya_real(): void
    {
        $admin = $this->adminUser();
        $qa = $this->qaUser();

        $jurusanProduksi = $this->createJurusanAs($admin, 'JURUSAN PRODUKSI QA', 'JURS-QA-P');
        $kelasProduksi = $this->createKelasAs($admin, 'X RPL QA-PROD', $jurusanProduksi->id);

        $jurusanTesting = $this->createJurusanAs($qa, 'JURUSAN TESTING QA', 'JURS-QA-T');
        $kelasTesting = $this->createKelasAs($qa, 'X RPL QA-TESTING', $jurusanTesting->id);

        $this->assertFalse((bool) $kelasProduksi->is_testing_data);
        $this->assertTrue((bool) $kelasTesting->is_testing_data);

        $this->actingAs($qa)->get(route('kelas.index'))
            ->assertOk()
            ->assertSee('X RPL QA-TESTING')
            ->assertDontSee('X RPL QA-PROD');

        $this->actingAs($admin)->get(route('kelas.index'))
            ->assertOk()
            ->assertSee('X RPL QA-PROD')
            ->assertDontSee('X RPL QA-TESTING');
    }

    public function test_guru_index_partisi_real_vs_testing(): void
    {
        $admin = $this->adminUser();
        $it = $this->itUser();

        $this->createGuruAs($admin, 'GURU UJI PRODUKSI');
        $this->createGuruAs($it, 'GURU UJI TESTING');

        // Celah paginasi: pakai pencarian agar hanya baris yang relevan dirender.
        $this->actingAs($admin)->get(route('guru.index', ['search' => 'GURU UJI']))
            ->assertOk()
            ->assertSee('GURU UJI PRODUKSI')
            ->assertDontSee('GURU UJI TESTING');

        $this->actingAs($it)->get(route('guru.index', ['search' => 'GURU UJI']))
            ->assertOk()
            ->assertSee('GURU UJI TESTING')
            ->assertDontSee('GURU UJI PRODUKSI');
    }

    public function test_siswa_index_partisi_real_vs_testing(): void
    {
        $admin = $this->adminUser();
        $it = $this->itUser();

        $jurusanProduksi = $this->createJurusanAs($admin, 'JURUSAN PRODUKSI SISWA', 'JURS-SW-P');
        $kelasProduksi = $this->createKelasAs($admin, 'X RPL SISWA-PROD', $jurusanProduksi->id);

        $jurusanTesting = $this->createJurusanAs($it, 'JURUSAN TESTING SISWA', 'JURS-SW-T');
        $kelasTesting = $this->createKelasAs($it, 'X RPL SISWA-TESTING', $jurusanTesting->id);

        $this->createSiswaAs($admin, 'SISWA UJI PRODUKSI', $kelasProduksi->id, 'L');
        $this->createSiswaAs($it, 'SISWA UJI TESTING', $kelasTesting->id, 'P');

        $this->actingAs($admin)->get(route('siswa.index'))
            ->assertOk()
            ->assertSee('SISWA UJI PRODUKSI')
            ->assertDontSee('SISWA UJI TESTING');

        $this->actingAs($it)->get(route('siswa.index'))
            ->assertOk()
            ->assertSee('SISWA UJI TESTING')
            ->assertDontSee('SISWA UJI PRODUKSI');
    }

    public function test_index_jurusan_mapel_ruangan_partisi_real_vs_testing(): void
    {
        $admin = $this->adminUser();
        $it = $this->itUser();

        // ---- JURUSAN ----
        $this->createJurusanAs($admin, 'JURUSAN UJI PRODUKSI', 'JUR-UJI-P');
        $this->createJurusanAs($it, 'JURUSAN UJI TESTING', 'JUR-UJI-T');

        $this->actingAs($admin)->get(route('jurusan.index'))
            ->assertOk()
            ->assertSee('JURUSAN UJI PRODUKSI')
            ->assertDontSee('JURUSAN UJI TESTING');

        $this->actingAs($it)->get(route('jurusan.index'))
            ->assertOk()
            ->assertSee('JURUSAN UJI TESTING')
            ->assertDontSee('JURUSAN UJI PRODUKSI');

        // ---- MATA PELAJARAN ----
        $this->actingAs($admin);
        MataPelajaran::create([
            'kode_mapel' => 'MPL-UJI-P',
            'nama_mapel' => 'MAPEL UJI PRODUKSI',
            'kelompok' => 'Muatan Umum',
        ]);

        $this->actingAs($it);
        MataPelajaran::create([
            'kode_mapel' => 'MPL-UJI-T',
            'nama_mapel' => 'MAPEL UJI TESTING',
            'kelompok' => 'Kejuruan',
        ]);

        $this->actingAs($admin)->get(route('mapel.index'))
            ->assertOk()
            ->assertSee('MAPEL UJI PRODUKSI')
            ->assertDontSee('MAPEL UJI TESTING');

        $this->actingAs($it)->get(route('mapel.index'))
            ->assertOk()
            ->assertSee('MAPEL UJI TESTING')
            ->assertDontSee('MAPEL UJI PRODUKSI');

        // ---- RUANGAN ----
        $this->actingAs($admin);
        Ruangan::create([
            'kode_ruangan' => 'RU-UJI-P',
            'nama_ruangan' => 'RUANG UJI PRODUKSI',
        ]);

        $this->actingAs($it);
        Ruangan::create([
            'kode_ruangan' => 'RU-UJI-T',
            'nama_ruangan' => 'RUANG UJI TESTING',
        ]);

        $this->actingAs($admin)->get(route('ruangan.index'))
            ->assertOk()
            ->assertSee('RUANG UJI PRODUKSI')
            ->assertDontSee('RUANG UJI TESTING');

        $this->actingAs($it)->get(route('ruangan.index'))
            ->assertOk()
            ->assertSee('RUANG UJI TESTING')
            ->assertDontSee('RUANG UJI PRODUKSI');
    }

    /**
     * Sisi TULIS Kelas via route store: kelas baru harus ditulis ke partisi
     * yang benar sesuai aktor (real untuk user operasional; testing untuk IT).
     */
    public function test_store_kelas_partisi_tulis_mengikuti_aktor(): void
    {
        $admin = $this->adminUser();
        $it = $this->itUser();

        $jurusanProduksi = $this->createJurusanAs($admin, 'JURUSAN STORE PROD', 'JURS-ST-P');
        $jurusanTesting = $this->createJurusanAs($it, 'JURUSAN STORE TEST', 'JURS-ST-T');

        // Admin menyimpan kelas → partisi real.
        $this->actingAs($admin)
            ->post(route('kelas.store'), ['tingkat' => 'X', 'id_jurusan' => $jurusanProduksi->id])
            ->assertRedirect(route('kelas.index'));

        $kelasProduksi = Kelas::where('id_jurusan', $jurusanProduksi->id)->firstOrFail();
        $this->assertFalse((bool) $kelasProduksi->is_testing_data);

        // Petugas IT menyimpan kelas → partisi testing.
        $this->actingAs($it)
            ->post(route('kelas.store'), ['tingkat' => 'XI', 'id_jurusan' => $jurusanTesting->id])
            ->assertRedirect(route('kelas.index'));

        $kelasTesting = Kelas::where('id_jurusan', $jurusanTesting->id)->firstOrFail();
        $this->assertTrue((bool) $kelasTesting->is_testing_data);
    }

    public function test_tahun_ajaran_index_partisi_real_vs_testing(): void
    {
        $admin = $this->adminUser();
        $it = $this->itUser();

        $taProduksi = $this->createTahunAjaranAs($admin, '2030/2031');
        $taTesting = $this->createTahunAjaranAs($it, '2033/2034', 'Ganjil', true);

        $this->assertFalse((bool) $taProduksi->is_testing_data);
        $this->assertTrue((bool) $taTesting->is_testing_data);

        // Admin (user operasional): hanya melihat Tahun Ajaran PRODUKSI.
        $this->actingAs($admin)->get(route('tahun-ajaran.index'))
            ->assertOk()
            ->assertSee('2030/2031')
            ->assertDontSee('2033/2034');

        // Petugas IT: hanya melihat Tahun Ajaran TESTING.
        $this->actingAs($it)->get(route('tahun-ajaran.index'))
            ->assertOk()
            ->assertSee('2033/2034')
            ->assertDontSee('2030/2031');
    }

    /**
     * Kasus kunci Tahun Ajaran: saat TA testing diaktifkan oleh IT, user
     * operasional biasa harus TETAP melihat hanya Tahun Ajaran PRODUKSI.
     */
    public function test_tahun_ajaran_index_user_operasional_tetap_lihat_real_meski_ta_testing_aktif(): void
    {
        $admin = $this->adminUser();
        $it = $this->itUser();

        $taProduksi = $this->createTahunAjaranAs($admin, '2030/2031');

        // IT membuat & mengaktifkan Tahun Ajaran testing.
        $taTesting = $this->createTahunAjaranAs($it, '2033/2034', 'Genap', true);
        $taTesting->update(['is_active' => true]);
        $this->assertTrue(User::currentTestingStatus(), 'konteks testing aktif');

        // Admin: daftar TA hanya berisi data real, TA testing tidak menumpangi.
        $response = $this->actingAs($admin)->get(route('tahun-ajaran.index'));
        $response->assertOk();
        $response->assertSee('2030/2031');
        $response->assertDontSee('2033/2034');

        // IT: hanya melihat partisi testing.
        $response = $this->actingAs($it)->get(route('tahun-ajaran.index'));
        $response->assertOk();
        $response->assertSee('2033/2034');
        $response->assertDontSee('2030/2031');
    }

    public function test_store_tahun_ajaran_partisi_tulis_mengikuti_aktor(): void
    {
        $admin = $this->adminUser();
        $it = $this->itUser();

        // Admin menyimpan TA → partisi real (hook creating tidak menandai testing).
        $this->actingAs($admin)
            ->post(route('tahun-ajaran.store'), ['tahun_ajaran' => '2031/2032', 'semester' => 'Ganjil'])
            ->assertRedirect(route('tahun-ajaran.index'));

        $taProduksi = TahunAjaran::where('tahun_ajaran', '2031/2032')->firstOrFail();
        $this->assertFalse((bool) $taProduksi->is_testing_data);

        // Petugas IT menyimpan TA → partisi testing.
        $this->actingAs($it)
            ->post(route('tahun-ajaran.store'), ['tahun_ajaran' => '2034/2035', 'semester' => 'Genap'])
            ->assertRedirect(route('tahun-ajaran.index'));

        $taTesting = TahunAjaran::where('tahun_ajaran', '2034/2035')->firstOrFail();
        $this->assertTrue((bool) $taTesting->is_testing_data);
    }

    /**
     * setAktif harus menonaktifkan T.A aktif di SEMUA partisi. Tanpa ini,
     * user operasional yang mengaktifkan T.A real akan menyisakan T.A testing
     * (milik IT) tetap aktif → dua T.A aktif & konteks ambigu.
     */
    public function test_set_aktif_tahun_ajaran_menonaktifkan_partisi_lain(): void
    {
        $admin = $this->adminUser();
        $it = $this->itUser();

        $taProduksi = $this->createTahunAjaranAs($admin, '2030/2031');
        $taTesting = $this->createTahunAjaranAs($it, '2033/2034', 'Ganjil', true);

        // IT mengaktifkan TA testing.
        $taTesting->update(['is_active' => true]);
        $this->assertTrue(User::currentTestingStatus());

        // Admin mengaktifkan TA real melalui route set-aktif.
        $this->actingAs($admin)
            ->post(route('tahun-ajaran.set-aktif', $taProduksi->id))
            ->assertRedirect(route('tahun-ajaran.index'));

        $taProduksi->refresh();
        $taTesting->refresh();

        $this->assertTrue((bool) $taProduksi->is_active);
        $this->assertFalse((bool) $taTesting->is_active, 'TA testing di partisi lain harus ikut nonaktif');
        $this->assertFalse(User::currentTestingStatus(), 'konteks kembali produksi');
        $this->assertSame(1, TahunAjaran::withoutGlobalScope(\App\Models\Scopes\TestingDataScope::class)
            ->where('is_active', true)
            ->count());
    }
}