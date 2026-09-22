<?php

namespace Tests\Feature;

use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Ruangan;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class JadwalExportTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdminTu(): User
    {
        return User::create([
            'nama' => 'Admin TU Test',
            'username' => 'admintu_'.Str::random(6),
            'password' => bcrypt('password'),
            'role' => 'admin',
            'sub_role' => 'admin_tu',
            'is_active' => true,
        ]);
    }

    public function test_download_template_jadwal_returns_valid_csv(): void
    {
        $admin = $this->makeAdminTu();

        $response = $this->actingAs($admin)->get(route('import.template-jadwal'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->getContent();
        $this->assertStringContainsString('Kelas,Hari,Jam,MataPelajaran,Guru,Ruang', $content);
        $this->assertStringContainsString('X TKI 1,Senin,1,Matematika', $content);
        $this->assertStringContainsString('4.5.6', $content);
    }

    public function test_export_jadwal_groups_consecutive_jam_slots_into_dot_separated_format(): void
    {
        $admin = $this->makeAdminTu();

        $tahun = TahunAjaran::create([
            'tahun_ajaran' => '2025/2026',
            'semester' => 'Ganjil',
            'is_active' => true,
        ]);

        $kelas = Kelas::create(['nama_kelas' => 'X TKI 1', 'tingkat' => 'X']);
        $mapel = MataPelajaran::create(['nama_mapel' => 'Bahasa Indonesia', 'kode_mapel' => 'BIN']);
        $guru  = User::create([
            'nama' => 'Yani, S.Pd.',
            'username' => 'yani_'.Str::random(4),
            'password' => bcrypt('password'),
            'role' => User::ROLE_GURU,
            'is_active' => true,
        ]);
        $ruang = Ruangan::create(['kode_ruangan' => 'LAB-KI-1', 'nama_ruangan' => 'Lab. KI 1']);

        $jam4 = JamPelajaran::create(['hari' => 'Senin', 'kategori_hari' => 'Senin-Kamis', 'jam_ke' => 4, 'jam_mulai' => '09:15:00', 'jam_selesai' => '10:00:00', 'jenis' => 'kbm']);
        $jam5 = JamPelajaran::create(['hari' => 'Senin', 'kategori_hari' => 'Senin-Kamis', 'jam_ke' => 5, 'jam_mulai' => '10:00:00', 'jam_selesai' => '10:45:00', 'jenis' => 'kbm']);

        $groupId = (string) Str::uuid();

        JadwalPelajaran::create([
            'id_kelas' => $kelas->id,
            'hari' => 'Senin',
            'id_jam' => $jam4->id,
            'id_tahun_ajaran' => $tahun->id,
            'group_id' => $groupId,
            'id_mapel' => $mapel->id,
            'id_guru' => $guru->id,
            'id_ruangan' => $ruang->id,
        ]);

        JadwalPelajaran::create([
            'id_kelas' => $kelas->id,
            'hari' => 'Senin',
            'id_jam' => $jam5->id,
            'id_tahun_ajaran' => $tahun->id,
            'group_id' => $groupId,
            'id_mapel' => $mapel->id,
            'id_guru' => $guru->id,
            'id_ruangan' => $ruang->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.jadwal.export', ['format' => 'csv']));

        $response->assertStatus(200);

        $binaryResponse = $response->baseResponse;
        $content = file_get_contents($binaryResponse->getFile()->getPathname());

        $this->assertStringContainsString('Kelas', $content);
        $this->assertStringContainsString('X TKI 1', $content);
        $this->assertStringNotContainsString('X X TKI 1', $content);
        $this->assertStringContainsString('4.5', $content);
        $this->assertStringContainsString('Bahasa Indonesia', $content);
        $this->assertStringContainsString('Yani, S.Pd.', $content);
    }

    public function test_export_jadwal_combines_tingkat_and_nama_kelas_without_duplication(): void
    {
        $admin = $this->makeAdminTu();

        $tahun = TahunAjaran::create([
            'tahun_ajaran' => '2025/2026',
            'semester' => 'Ganjil',
            'is_active' => true,
        ]);

        $kelasAk = Kelas::create(['nama_kelas' => 'AK 1', 'tingkat' => 'X']);
        $mapel   = MataPelajaran::create(['nama_mapel' => 'Akuntansi Dasar', 'kode_mapel' => 'AKD']);
        $guru    = User::create([
            'nama' => 'Budi, S.E.',
            'username' => 'budi_'.Str::random(4),
            'password' => bcrypt('password'),
            'role' => User::ROLE_GURU,
            'is_active' => true,
        ]);
        $jam1 = JamPelajaran::create(['hari' => 'Rabu', 'kategori_hari' => 'Senin-Kamis', 'jam_ke' => 1, 'jam_mulai' => '07:00:00', 'jam_selesai' => '07:45:00', 'jenis' => 'kbm']);

        JadwalPelajaran::create([
            'id_kelas' => $kelasAk->id,
            'hari' => 'Rabu',
            'id_jam' => $jam1->id,
            'id_tahun_ajaran' => $tahun->id,
            'group_id' => (string) Str::uuid(),
            'id_mapel' => $mapel->id,
            'id_guru' => $guru->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.jadwal.export', ['format' => 'csv']));
        $response->assertStatus(200);

        $binaryResponse = $response->baseResponse;
        $content = file_get_contents($binaryResponse->getFile()->getPathname());

        // "AK 1" dengan tingkat "X" harus digabung menjadi "X AK 1"
        $this->assertStringContainsString('X AK 1', $content);
        $this->assertStringNotContainsString('X X AK 1', $content);
    }

    public function test_export_jadwal_filters_out_records_without_kelas(): void
    {
        $admin = $this->makeAdminTu();

        $tahun = TahunAjaran::create([
            'tahun_ajaran' => '2025/2026',
            'semester' => 'Ganjil',
            'is_active' => true,
        ]);

        $kelas = Kelas::create(['nama_kelas' => 'X TKI 2', 'tingkat' => 'X']);
        $mapel = MataPelajaran::create(['nama_mapel' => 'Matematika Tanpa Kelas', 'kode_mapel' => 'MTK-TK']);
        $guru  = User::create([
            'nama' => 'Guru Test',
            'username' => 'guru_'.Str::random(4),
            'password' => bcrypt('password'),
            'role' => User::ROLE_GURU,
            'is_active' => true,
        ]);
        $jam1 = JamPelajaran::create(['hari' => 'Selasa', 'kategori_hari' => 'Senin-Kamis', 'jam_ke' => 1, 'jam_mulai' => '07:00:00', 'jam_selesai' => '07:45:00', 'jenis' => 'kbm']);

        JadwalPelajaran::create([
            'id_kelas' => $kelas->id,
            'hari' => 'Selasa',
            'id_jam' => $jam1->id,
            'id_tahun_ajaran' => $tahun->id,
            'group_id' => (string) Str::uuid(),
            'id_mapel' => $mapel->id,
            'id_guru' => $guru->id,
        ]);

        // Soft delete kelas agar relasi $j->kelas bernilai null pada query biasa (tanpa withTrashed)
        $kelas->delete();

        $response = $this->actingAs($admin)->get(route('admin.jadwal.export', ['format' => 'csv']));
        $response->assertStatus(200);

        $binaryResponse = $response->baseResponse;
        $content = file_get_contents($binaryResponse->getFile()->getPathname());

        $this->assertStringNotContainsString('Matematika Tanpa Kelas', $content);
    }

    public function test_admin_jadwal_index_renders_without_blade_parse_error(): void
    {
        $admin = $this->makeAdminTu();

        TahunAjaran::create([
            'tahun_ajaran' => '2025/2026',
            'semester' => 'Ganjil',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.jadwal.index'));
        $response->assertStatus(200);
        $response->assertSee('Plotting Jadwal Kelas');
    }
}
