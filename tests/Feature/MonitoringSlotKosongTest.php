<?php

namespace Tests\Feature;

use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MonitoringSlotKosongTest extends TestCase
{
    use RefreshDatabase;

    private function adminTu(): User
    {
        return User::create([
            'nama'      => 'Petugas TU',
            'username'  => 'admin_tu_monitor',
            'password'  => bcrypt('password'),
            'role'      => 'admin',
            'sub_role'  => 'petugas_tu',
            'is_active' => true,
        ]);
    }

    private function guru(): User
    {
        return User::create([
            'nama'      => 'Guru A',
            'username'  => 'guru_monitor',
            'password'  => bcrypt('password'),
            'role'      => 'guru',
            'sub_role'  => 'guru_mapel',
            'is_active' => true,
        ]);
    }

    private function seedSeninKamis(int $jumlah = 2): array
    {
        return collect(range(1, $jumlah))->map(fn ($jamKe) => JamPelajaran::create([
            'kategori_hari' => 'Senin-Kamis',
            'jam_ke'        => $jamKe,
            'jam_mulai'     => sprintf('%02d:00:00', 7 + $jamKe),
            'jam_selesai'   => sprintf('%02d:00:00', 7 + $jamKe + 1),
            'jenis'         => 'kbm',
        ]))->all();
    }

    private function seedKelas(): array
    {
        $jurusan = Jurusan::create(['nama_jurusan' => 'MIPA', 'kode_jurusan' => 'MIPA']);
        $tahun = TahunAjaran::create([
            'tahun_ajaran' => '2026/2027',
            'semester'     => 'Ganjil',
            'status_aktif' => true,
        ]);
        $kelas = Kelas::create([
            'nama_kelas' => 'A',
            'tingkat'    => '10',
            'id_jurusan' => $jurusan->id,
        ]);
        $mapel = MataPelajaran::create(['nama_mapel' => 'Matematika', 'kode_mapel' => 'MTK']);

        return compact('tahun', 'kelas', 'mapel');
    }

    public function test_unplanned_kbm_slots_are_reported_as_empty(): void
    {
        $admin = $this->adminTu();
        $this->seedSeninKamis(2);
        ['kelas' => $kelas] = $this->seedKelas();

        $response = $this->actingAs($admin)->get(route('admin.jadwal.monitoring'));

        $response->assertOk()
            ->assertSee('Monitoring Slot Jadwal Kosong', false)
            ->assertSee('10 - A')          // kelas
            ->assertSee('Senin')           // hari
            ->assertSee('[1, 2]', false);  // jam kosong

        // Quick-access link menuju halaman plotting kelas & hari tertentu.
        $urlPlotting = route('admin.jadwal.index', ['id_kelas' => $kelas->id, 'hari' => 'Senin']);
        $urlPlottingHtml = str_replace('&', '&amp;', $urlPlotting); // & di-escape oleh HTML attribute
        $response->assertSee($urlPlottingHtml, false)
            ->assertSee('cursor: pointer;', false)
            ->assertSee('table-hover', false);
    }

    public function test_fully_plotted_class_is_excluded_and_counted_as_lengkap(): void
    {
        $admin = $this->adminTu();
        [$guru] = [$this->guru()];
        $slots = $this->seedSeninKamis(2);
        ['tahun' => $tahun, 'kelas' => $kelas, 'mapel' => $mapel] = $this->seedKelas();

        // Plot semua slot KBM untuk semua 4 hari Senin-Kamis.
        foreach (['Senin', 'Selasa', 'Rabu', 'Kamis'] as $hari) {
            foreach ($slots as $slot) {
                JadwalPelajaran::create([
                    'group_id'        => (string) Str::uuid(),
                    'hari'            => $hari,
                    'id_jam'          => $slot->id,
                    'id_kelas'        => $kelas->id,
                    'id_mapel'        => $mapel->id,
                    'id_guru'         => $guru->id,
                    'id_tahun_ajaran' => $tahun->id,
                ]);
            }
        }

        $response = $this->actingAs($admin)->get(route('admin.jadwal.monitoring'));

        $response->assertOk()
            ->assertSee('Semua Slot KBM Sudah Terisi Penuh', false)
            ->assertDontSee('10 - A');
    }

    public function test_non_tu_user_is_denied_access_to_monitoring(): void
    {
        $guru = $this->guru();

        $response = $this->actingAs($guru)->get(route('admin.jadwal.monitoring'));

        $response->assertForbidden();
    }
}
