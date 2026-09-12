<?php

namespace Tests\Feature;

use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class JurnalGroupConsecutiveSchedulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_consecutive_schedules_are_grouped_and_stored_together(): void
    {
        Carbon::setTestNow('2026-09-07 08:00:00');
        $hari = 'Senin';

        $tahun = TahunAjaran::create([
            'tahun_ajaran' => '2025/2026',
            'semester' => 'Ganjil',
            'is_active' => true,
        ]);

        $guru = User::create([
            'nama' => 'Guru Pengajar',
            'nip' => '198501012010011001',
            'username' => 'gurupengajar',
            'password' => bcrypt('password'),
            'role' => 'guru',
            'is_active' => true,
        ]);

        $kelas = Kelas::create([
            'nama_kelas' => 'X-IPA-1',
            'tingkat' => 'X',
        ]);

        $mapel = MataPelajaran::create([
            'nama_mapel' => 'Matematika',
        ]);

        $jam8 = JamPelajaran::create([
            'jam_ke' => 8,
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '07:45:00',
            'kategori_hari' => 'Senin-Kamis',
        ]);

        $jam9 = JamPelajaran::create([
            'jam_ke' => 9,
            'jam_mulai' => '07:45:00',
            'jam_selesai' => '08:30:00',
            'kategori_hari' => 'Senin-Kamis',
        ]);

        $jam10 = JamPelajaran::create([
            'jam_ke' => 10,
            'jam_mulai' => '08:30:00',
            'jam_selesai' => '09:15:00',
            'kategori_hari' => 'Senin-Kamis',
        ]);

        $groupId = (string) Str::uuid();

        $jadwal8 = JadwalPelajaran::create([
            'group_id' => $groupId,
            'hari' => $hari,
            'id_jam' => $jam8->id,
            'id_kelas' => $kelas->id,
            'id_mapel' => $mapel->id,
            'id_guru' => $guru->id,
            'id_tahun_ajaran' => $tahun->id,
        ]);

        $jadwal9 = JadwalPelajaran::create([
            'group_id' => $groupId,
            'hari' => $hari,
            'id_jam' => $jam9->id,
            'id_kelas' => $kelas->id,
            'id_mapel' => $mapel->id,
            'id_guru' => $guru->id,
            'id_tahun_ajaran' => $tahun->id,
        ]);

        $jadwal10 = JadwalPelajaran::create([
            'group_id' => $groupId,
            'hari' => $hari,
            'id_jam' => $jam10->id,
            'id_kelas' => $kelas->id,
            'id_mapel' => $mapel->id,
            'id_guru' => $guru->id,
            'id_tahun_ajaran' => $tahun->id,
        ]);

        $siswa = Siswa::create([
            'id_kelas' => $kelas->id,
            'nama' => 'Siswa Test',
            'nis' => '12345',
            'jenis_kelamin' => 'L',
            'status_siswa' => 'Aktif',
        ]);

        // 1. Check index page grouping: 3 consecutive JPs should be displayed as 1 group "Jam 8 - 10"
        $response = $this->actingAs($guru)->get(route('guru.jurnal'));
        $response->assertStatus(200);
        $response->assertSee('Jam 8 - 10');
        $response->assertSee('07.00 - 09.15');

        // 2. Submit form for primary schedule (jadwal8)
        $postData = [
            'id_jadwal' => $jadwal8->id,
            'materi' => 'Pembahasan Persamaan Kuadrat',
            'catatan_kejadian' => 'Siswa sangat antusias',
        ];

        $storeResponse = $this->actingAs($guru)->post(route('guru.jurnal.store'), $postData);
        $storeResponse->assertRedirect(route('guru.jurnal'));
        $storeResponse->assertSessionHas('success');

        // 3. Verify database: 3 journals created (one for each schedule in the block)
        $this->assertDatabaseHas('jurnal', [
            'id_jadwal' => $jadwal8->id,
            'materi' => 'Pembahasan Persamaan Kuadrat',
        ]);
        $this->assertDatabaseHas('jurnal', [
            'id_jadwal' => $jadwal9->id,
            'materi' => 'Pembahasan Persamaan Kuadrat',
        ]);
        $this->assertDatabaseHas('jurnal', [
            'id_jadwal' => $jadwal10->id,
            'materi' => 'Pembahasan Persamaan Kuadrat',
        ]);

        $this->assertEquals(3, Jurnal::whereIn('id_jadwal', [$jadwal8->id, $jadwal9->id, $jadwal10->id])->count());
    }
}
