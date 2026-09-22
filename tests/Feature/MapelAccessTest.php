<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MapelAccessTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, ?string $subRole = null): User
    {
        return User::create([
            'nama' => 'Test User',
            'username' => 'user_'.Str::random(8),
            'password' => bcrypt('password'),
            'role' => $role,
            'sub_role' => $subRole,
            'is_active' => true,
        ]);
    }

    public function test_waka_kurikulum_can_access_mata_pelajaran_index(): void
    {
        $user = $this->makeUser('admin', 'waka_kurikulum');

        $response = $this->actingAs($user)->get(route('mapel.index'));

        $response->assertOk();
    }

    public function test_petugas_tu_can_access_mata_pelajaran_index(): void
    {
        $user = $this->makeUser('admin', 'petugas_tu');

        $response = $this->actingAs($user)->get(route('mapel.index'));

        $response->assertOk();
    }

    public function test_non_authorized_role_is_forbidden(): void
    {
        $user = $this->makeUser('guru', 'guru_mapel');

        $response = $this->actingAs($user)->get(route('mapel.index'));

        $response->assertForbidden();
    }

    public function test_waka_kurikulum_can_store_mata_pelajaran(): void
    {
        $user = $this->makeUser('admin', 'waka_kurikulum');

        $response = $this->actingAs($user)->post(route('mapel.store'), [
            'kode_mapel' => 'KK-001',
            'nama_mapel' => 'Matematika',
            'kelompok' => 'Muatan Umum',
        ]);

        $response->assertRedirect(route('mapel.index'));
        $this->assertDatabaseHas('mata_pelajaran', ['nama_mapel' => 'Matematika', 'jurusan_id' => null]);
    }

    public function test_store_kejuruan_requires_jurusan_id(): void
    {
        $user = $this->makeUser('admin', 'waka_kurikulum');

        $response = $this->actingAs($user)->post(route('mapel.store'), [
            'kode_mapel' => 'TKJ-001',
            'nama_mapel' => 'Administrasi Server',
            'kelompok' => 'Kejuruan',
            'jurusan_id' => '',
        ]);

        $response->assertSessionHasErrors('jurusan_id');
        $this->assertDatabaseMissing('mata_pelajaran', ['kode_mapel' => 'TKJ-001']);
    }

    public function test_store_kejuruan_with_valid_jurusan_succeeds(): void
    {
        $user = $this->makeUser('admin', 'waka_kurikulum');
        $jurusan = \App\Models\Jurusan::create([
            'kode_jurusan' => 'TKJ',
            'nama_jurusan' => 'Teknik Komputer dan Jaringan',
        ]);

        $response = $this->actingAs($user)->post(route('mapel.store'), [
            'kode_mapel' => 'TKJ-001',
            'nama_mapel' => 'Administrasi Server',
            'kelompok' => 'Kejuruan',
            'jurusan_id' => $jurusan->id,
        ]);

        $response->assertRedirect(route('mapel.index'));
        $this->assertDatabaseHas('mata_pelajaran', [
            'kode_mapel' => 'TKJ-001',
            'kelompok' => 'Kejuruan',
            'jurusan_id' => $jurusan->id,
        ]);
    }

    public function test_update_kejuruan_to_umum_clears_jurusan_id(): void
    {
        $user = $this->makeUser('admin', 'waka_kurikulum');
        $jurusan = \App\Models\Jurusan::create([
            'kode_jurusan' => 'RPL',
            'nama_jurusan' => 'Rekayasa Perangkat Lunak',
        ]);
        $mapel = \App\Models\MataPelajaran::create([
            'kode_mapel' => 'RPL-001',
            'nama_mapel' => 'Basis Data',
            'kelompok' => 'Kejuruan',
            'jurusan_id' => $jurusan->id,
        ]);

        $response = $this->actingAs($user)->put(route('mapel.update', $mapel->id), [
            'kode_mapel' => 'RPL-001',
            'nama_mapel' => 'Basis Data',
            'kelompok' => 'Muatan Umum',
            'jurusan_id' => '',
        ]);

        $response->assertRedirect(route('mapel.index'));
        $this->assertDatabaseHas('mata_pelajaran', [
            'id' => $mapel->id,
            'kelompok' => 'Muatan Umum',
            'jurusan_id' => null,
        ]);
    }

    public function test_can_export_mata_pelajaran_excel_and_csv(): void
    {
        $user = $this->makeUser('admin', 'waka_kurikulum');
        \App\Models\MataPelajaran::create([
            'kode_mapel' => 'EXP-01',
            'nama_mapel' => 'Mapel Export Test',
            'kelompok' => 'Muatan Umum',
        ]);

        $resExcel = $this->actingAs($user)->get(route('mapel.export', ['format' => 'xlsx']));
        $resExcel->assertOk();
        $this->assertTrue(
            str_contains((string) $resExcel->headers->get('content-disposition'), '.xlsx')
        );

        $resCsv = $this->actingAs($user)->get(route('mapel.export', ['format' => 'csv']));
        $resCsv->assertOk();
        $this->assertTrue(
            str_contains((string) $resCsv->headers->get('content-disposition'), '.csv')
        );
    }

    public function test_can_download_mata_pelajaran_template(): void
    {
        $user = $this->makeUser('admin', 'petugas_tu');

        $response = $this->actingAs($user)->get(route('mapel.template'));
        $response->assertOk();
        $this->assertTrue(
            str_contains((string) $response->headers->get('content-disposition'), 'template_import_mata_pelajaran.xlsx')
        );
    }

    public function test_can_import_mata_pelajaran_from_csv(): void
    {
        $user = $this->makeUser('admin', 'waka_kurikulum');
        $jurusan = \App\Models\Jurusan::create([
            'kode_jurusan' => 'TKJ',
            'nama_jurusan' => 'Teknik Komputer dan Jaringan',
        ]);

        $csvContent = "NO,KODE MAPEL,NAMA MATA PELAJARAN,KELOMPOK,JURUSAN\n"
            ."1,MAT-01,Matematika Wajib,Muatan Umum,-\n"
            ."2,TKJ-02,Jaringan Nirkabel,Kejuruan,TKJ\n"
            ."3,PLH-01,Pendidikan Lingkungan Hidup,Muatan Lokal,-\n";

        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('mapel.csv', $csvContent);

        $response = $this->actingAs($user)->post(route('mapel.import'), [
            'file_mapel' => $file,
        ]);

        $response->assertRedirect(route('mapel.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('mata_pelajaran', [
            'kode_mapel' => 'MAT-01',
            'nama_mapel' => 'Matematika Wajib',
            'kelompok' => 'Muatan Umum',
            'jurusan_id' => null,
        ]);

        $this->assertDatabaseHas('mata_pelajaran', [
            'kode_mapel' => 'TKJ-02',
            'nama_mapel' => 'Jaringan Nirkabel',
            'kelompok' => 'Kejuruan',
            'jurusan_id' => $jurusan->id,
        ]);

        $this->assertDatabaseHas('mata_pelajaran', [
            'kode_mapel' => 'PLH-01',
            'nama_mapel' => 'Pendidikan Lingkungan Hidup',
            'kelompok' => 'Muatan Lokal',
            'jurusan_id' => null,
        ]);
    }

    public function test_import_updates_existing_mapel_without_duplicate(): void
    {
        $user = $this->makeUser('admin', 'waka_kurikulum');
        \App\Models\MataPelajaran::create([
            'kode_mapel' => 'MAT-01',
            'nama_mapel' => 'Matematika Lama',
            'kelompok' => 'Muatan Umum',
        ]);

        $csvContent = "NO,KODE MAPEL,NAMA MATA PELAJARAN,KELOMPOK,JURUSAN\n"
            ."1,MAT-01,Matematika Baru Terupdate,Muatan Umum,-\n";

        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('mapel.csv', $csvContent);

        $response = $this->actingAs($user)->post(route('mapel.import'), [
            'file_mapel' => $file,
        ]);

        $response->assertRedirect(route('mapel.index'));
        $this->assertEquals(1, \App\Models\MataPelajaran::where('kode_mapel', 'MAT-01')->count());
        $this->assertDatabaseHas('mata_pelajaran', [
            'kode_mapel' => 'MAT-01',
            'nama_mapel' => 'Matematika Baru Terupdate',
        ]);
    }

    public function test_import_kejuruan_without_jurusan_fails_and_rollbacks(): void
    {
        $user = $this->makeUser('admin', 'waka_kurikulum');

        $csvContent = "NO,KODE MAPEL,NAMA MATA PELAJARAN,KELOMPOK,JURUSAN\n"
            ."1,VALID-01,Mapel Valid Sebelum Error,Muatan Umum,-\n"
            ."2,INVALID-02,Mapel Kejuruan Tanpa Jurusan,Kejuruan,-\n";

        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('mapel.csv', $csvContent);

        $response = $this->actingAs($user)->post(route('mapel.import'), [
            'file_mapel' => $file,
        ]);

        $response->assertRedirect(route('mapel.index'));
        $response->assertSessionHas('error');

        // Verify that whole transaction rolled back
        $this->assertDatabaseMissing('mata_pelajaran', ['kode_mapel' => 'VALID-01']);
        $this->assertDatabaseMissing('mata_pelajaran', ['kode_mapel' => 'INVALID-02']);
    }

    public function test_import_restores_soft_deleted_mapel(): void
    {
        $user = $this->makeUser('admin', 'waka_kurikulum');
        $mapel = \App\Models\MataPelajaran::create([
            'kode_mapel' => 'DEL-01',
            'nama_mapel' => 'Mapel Terhapus',
            'kelompok' => 'Muatan Umum',
        ]);
        $mapel->delete();

        $this->assertSoftDeleted('mata_pelajaran', ['id' => $mapel->id]);

        $csvContent = "NO,KODE MAPEL,NAMA MATA PELAJARAN,KELOMPOK,JURUSAN\n"
            ."1,DEL-01,Mapel Dipulihkan,Muatan Umum,-\n";

        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('mapel.csv', $csvContent);

        $response = $this->actingAs($user)->post(route('mapel.import'), [
            'file_mapel' => $file,
        ]);

        $response->assertRedirect(route('mapel.index'));
        $this->assertDatabaseHas('mata_pelajaran', [
            'id' => $mapel->id,
            'kode_mapel' => 'DEL-01',
            'nama_mapel' => 'Mapel Dipulihkan',
            'deleted_at' => null,
        ]);
    }
}
