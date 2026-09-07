<?php

namespace Tests\Feature;

use App\Models\AgendaRutin;
use App\Models\JamPelajaran;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Ruangan;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JadwalConflictValidationMessagesTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected TahunAjaran $tahun;
    protected Kelas $kelasA;
    protected Kelas $kelasB;
    protected MataPelajaran $mapelMath;
    protected MataPelajaran $mapelPhys;
    protected User $guru1;
    protected Ruangan $ruangan1;
    protected JamPelajaran $jam1;
    protected JamPelajaran $jam2;
    protected JamPelajaran $jamBreak;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'nama' => 'Admin Test',
            'username' => 'admintest',
            'password' => bcrypt('password'),
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $this->tahun = TahunAjaran::create([
            'tahun_ajaran' => '2025/2026',
            'semester' => 'Ganjil',
            'is_active' => true,
        ]);

        $this->kelasA = Kelas::create(['nama_kelas' => 'X-IPA-1', 'tingkat' => 'X']);
        $this->kelasB = Kelas::create(['nama_kelas' => 'X-IPA-2', 'tingkat' => 'X']);

        $this->mapelMath = MataPelajaran::create(['nama_mapel' => 'Matematika']);
        $this->mapelPhys = MataPelajaran::create(['nama_mapel' => 'Fisika']);

        $this->guru1 = User::create([
            'nama' => 'Drs. Supriyanto, M.M.',
            'nip' => '197001011995011001',
            'username' => 'gurusupri',
            'password' => bcrypt('password'),
            'role' => User::ROLE_GURU,
            'is_active' => true,
        ]);

        $this->ruangan1 = Ruangan::create([
            'kode_ruangan' => 'R.101',
            'nama_ruangan' => 'Laboratorium Fisika',
        ]);

        $this->jam1 = JamPelajaran::create([
            'jam_ke' => 1,
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '07:45:00',
            'jenis' => 'kbm',
            'kategori_hari' => 'Senin-Kamis',
        ]);

        $this->jam2 = JamPelajaran::create([
            'jam_ke' => 2,
            'jam_mulai' => '07:45:00',
            'jam_selesai' => '08:30:00',
            'jenis' => 'kbm',
            'kategori_hari' => 'Senin-Kamis',
        ]);

        $this->jamBreak = JamPelajaran::create([
            'jam_ke' => 5,
            'jam_mulai' => '10:00:00',
            'jam_selesai' => '10:30:00',
            'jenis' => 'istirahat',
            'kategori_hari' => 'Senin-Kamis',
        ]);
    }

    public function test_guru_conflict_returns_detailed_error_message(): void
    {
        // Setup: Guru1 already assigned to Kelas A for Matematika at Jam 1
        JadwalPelajaran::create([
            'group_id' => (string) \Illuminate\Support\Str::uuid(),
            'hari' => 'Senin',
            'id_jam' => $this->jam1->id,
            'id_kelas' => $this->kelasA->id,
            'id_mapel' => $this->mapelMath->id,
            'id_guru' => $this->guru1->id,
            'id_tahun_ajaran' => $this->tahun->id,
        ]);

        // Attempt plotting Guru1 to Kelas B at Jam 1 (same time & day)
        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.jadwal.store'), [
                'id_kelas' => $this->kelasB->id,
                'hari' => 'Senin',
                'jam_ke_mulai' => 1,
                'jam_ke_selesai' => 1,
                'id_mapel' => $this->mapelPhys->id,
                'id_guru' => $this->guru1->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath(
            'message',
            'Gagal! Guru Drs. Supriyanto, M.M. sudah ada jadwal di Kelas X-IPA-1 pada Jam Ke-1 (T.A. 2025/2026 Ganjil).'
        );
    }

    public function test_ruangan_conflict_returns_detailed_error_message(): void
    {
        // Setup: Ruangan1 already used by Kelas A for Matematika at Jam 1
        JadwalPelajaran::create([
            'group_id' => (string) \Illuminate\Support\Str::uuid(),
            'hari' => 'Senin',
            'id_jam' => $this->jam1->id,
            'id_kelas' => $this->kelasA->id,
            'id_mapel' => $this->mapelMath->id,
            'id_guru' => $this->guru1->id,
            'id_ruangan' => $this->ruangan1->id,
            'id_tahun_ajaran' => $this->tahun->id,
        ]);

        // Create another teacher for Kelas B
        $guru2 = User::create([
            'nama' => 'Budi Santoso',
            'nip' => '197501011996011002',
            'username' => 'gurubudi',
            'password' => bcrypt('password'),
            'role' => User::ROLE_GURU,
            'is_active' => true,
        ]);

        // Attempt plotting Kelas B to use Ruangan1 at Jam 1
        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.jadwal.store'), [
                'id_kelas' => $this->kelasB->id,
                'hari' => 'Senin',
                'jam_ke_mulai' => 1,
                'jam_ke_selesai' => 1,
                'id_mapel' => $this->mapelPhys->id,
                'id_guru' => $guru2->id,
                'id_ruangan' => $this->ruangan1->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath(
            'message',
            'Gagal! Ruangan Laboratorium Fisika sudah terpakai oleh Kelas X-IPA-1 pada Jam Ke-1 (T.A. 2025/2026 Ganjil).'
        );
    }

    public function test_locked_break_slot_conflict_returns_detailed_error_message(): void
    {
        // Create an agenda rutin (e.g. Upacara) on Jam 1
        AgendaRutin::create([
            'hari' => 'Senin',
            'jam_ke' => 1,
            'nama_agenda' => 'Upacara Bendera',
            'is_active' => true,
        ]);

        // Attempt plotting on Jam 1 which is locked by Agenda Rutin
        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.jadwal.store'), [
                'id_kelas' => $this->kelasA->id,
                'hari' => 'Senin',
                'jam_ke_mulai' => 1,
                'jam_ke_selesai' => 1,
                'id_mapel' => $this->mapelMath->id,
                'id_guru' => $this->guru1->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath(
            'message',
            'Gagal! Rentang jam yang dipilih menabrak slot Agenda Rutin pada Jam Ke-1.'
        );
    }

    public function test_successful_plotting_persists_data_and_returns_clear_success_message(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.jadwal.store'), [
                'id_kelas' => $this->kelasA->id,
                'hari' => 'Senin',
                'jam_ke_mulai' => 1,
                'jam_ke_selesai' => 2,
                'id_mapel' => $this->mapelMath->id,
                'id_guru' => $this->guru1->id,
                'id_ruangan' => $this->ruangan1->id,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success'  => true,
            'message'  => 'Berhasil menambahkan jadwal Matematika (Drs. Supriyanto, M.M.) pada Jam Ke-1 s/d 2 untuk Tahun Ajaran 2025/2026 (Ganjil).',
            'id_kelas' => $this->kelasA->id,
            'hari'     => 'Senin',
        ]);
        $response->assertJsonStructure(['data' => [['id', 'id_kelas', 'hari', 'id_jam', 'mata_pelajaran', 'guru']]]);

        // Verify persistence in DB
        $this->assertDatabaseHas('jadwal_pelajaran', [
            'id_kelas' => $this->kelasA->id,
            'hari' => 'Senin',
            'id_jam' => $this->jam1->id,
            'id_mapel' => $this->mapelMath->id,
            'id_guru' => $this->guru1->id,
            'id_ruangan' => $this->ruangan1->id,
        ]);

        $this->assertDatabaseHas('jadwal_pelajaran', [
            'id_kelas' => $this->kelasA->id,
            'hari' => 'Senin',
            'id_jam' => $this->jam2->id,
            'id_mapel' => $this->mapelMath->id,
            'id_guru' => $this->guru1->id,
            'id_ruangan' => $this->ruangan1->id,
        ]);
    }

    public function test_index_returns_json_schedule_matrix_when_wants_json(): void
    {
        JadwalPelajaran::create([
            'group_id' => (string) \Illuminate\Support\Str::uuid(),
            'hari' => 'Senin',
            'id_jam' => $this->jam1->id,
            'id_kelas' => $this->kelasA->id,
            'id_mapel' => $this->mapelMath->id,
            'id_guru' => $this->guru1->id,
            'id_tahun_ajaran' => $this->tahun->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.jadwal.index', [
                'id_kelas' => $this->kelasA->id,
                'hari' => 'Senin',
            ]));

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('selected_kelas_id', $this->kelasA->id);
        $response->assertJsonPath('selected_hari', 'Senin');
        $response->assertJsonStructure(['jadwal' => [['id', 'id_kelas', 'hari']]]);
    }

    public function test_soft_deleted_schedule_ignored_in_conflict_validation_and_unique_constraint(): void
    {
        // 1. Create a schedule for Guru1 on Kelas A at Jam 1, then soft-delete it
        $jadwalLama = JadwalPelajaran::create([
            'group_id' => (string) \Illuminate\Support\Str::uuid(),
            'hari' => 'Senin',
            'id_jam' => $this->jam1->id,
            'id_kelas' => $this->kelasA->id,
            'id_mapel' => $this->mapelMath->id,
            'id_guru' => $this->guru1->id,
            'id_tahun_ajaran' => $this->tahun->id,
        ]);

        $jadwalLama->delete(); // Soft delete

        $this->assertSoftDeleted('jadwal_pelajaran', ['id' => $jadwalLama->id]);

        // 2. Re-assign Guru1 to Kelas B at Jam 1 (same time, day, & teacher as soft-deleted record)
        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.jadwal.store'), [
                'id_kelas' => $this->kelasB->id,
                'hari' => 'Senin',
                'jam_ke_mulai' => 1,
                'jam_ke_selesai' => 1,
                'id_mapel' => $this->mapelPhys->id,
                'id_guru' => $this->guru1->id,
            ]);

        // Must succeed without conflict error (422) or DB Unique Constraint error (500)
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        // Verify active persistence in DB for Kelas B
        $this->assertDatabaseHas('jadwal_pelajaran', [
            'id_kelas' => $this->kelasB->id,
            'hari' => 'Senin',
            'id_jam' => $this->jam1->id,
            'id_guru' => $this->guru1->id,
            'deleted_at' => null,
        ]);
    }
}
