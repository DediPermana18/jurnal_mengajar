<?php

namespace Tests\Feature;

use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\JamPulang;
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
            'nama' => 'Petugas TU',
            'username' => 'admin_tu_monitor',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'sub_role' => 'petugas_tu',
            'is_active' => true,
        ]);
    }

    private function guru(): User
    {
        return User::create([
            'nama' => 'Guru A',
            'username' => 'guru_monitor',
            'password' => bcrypt('password'),
            'role' => 'guru',
            'sub_role' => 'guru_mapel',
            'is_active' => true,
        ]);
    }

    private function seedSeninKamis(int $jumlah = 2): array
    {
        return collect(range(1, $jumlah))->map(fn ($jamKe) => JamPelajaran::create([
            'kategori_hari' => 'Senin-Kamis',
            'jam_ke' => $jamKe,
            'jam_mulai' => sprintf('%02d:00:00', 7 + $jamKe),
            'jam_selesai' => sprintf('%02d:00:00', 7 + $jamKe + 1),
            'jenis' => 'kbm',
        ]))->all();
    }

    private function seedKelas(): array
    {
        $jurusan = Jurusan::create(['nama_jurusan' => 'MIPA', 'kode_jurusan' => 'MIPA']);
        $tahun = TahunAjaran::create([
            'tahun_ajaran' => '2026/2027',
            'semester' => 'Ganjil',
            'is_active' => true,
        ]);
        $kelas = Kelas::create([
            'nama_kelas' => 'A',
            'tingkat' => '10',
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

    public function test_monitoring_filters_by_grade_and_major_and_keeps_filter_state(): void
    {
        $admin = $this->adminTu();
        $this->seedSeninKamis(1);
        $jurusanMipa = Jurusan::create(['nama_jurusan' => 'MIPA', 'kode_jurusan' => 'MIPA']);
        $jurusanRpl = Jurusan::create(['nama_jurusan' => 'Rekayasa Perangkat Lunak', 'kode_jurusan' => 'RPL']);

        foreach ([
            ['tingkat' => '10', 'nama_kelas' => 'Kelas MIPA', 'jurusan' => $jurusanMipa],
            ['tingkat' => 'XI', 'nama_kelas' => 'Kelas RPL', 'jurusan' => $jurusanRpl],
            ['tingkat' => '12', 'nama_kelas' => 'Kelas XII MIPA', 'jurusan' => $jurusanMipa],
        ] as $data) {
            Kelas::create([
                'nama_kelas' => $data['nama_kelas'],
                'tingkat' => $data['tingkat'],
                'id_jurusan' => $data['jurusan']->id,
            ]);
        }

        $response = $this->actingAs($admin)->get(route('admin.jadwal.monitoring', [
            'tingkat' => 'XI',
            'jurusan_id' => $jurusanRpl->id,
            'hari' => 'Senin',
            'search' => 'Kelas',
        ]));

        $response->assertOk()
            ->assertSee('XI - Kelas RPL', false)
            ->assertDontSee('10 - Kelas MIPA', false)
            ->assertDontSee('12 - Kelas XII MIPA', false)
            ->assertSee('name="tingkat" id="selectFilterTingkat"', false)
            ->assertSee('value="XI" selected', false)
            ->assertSee('name="jurusan_id" id="selectFilterJurusan"', false)
            ->assertSee('value="'.$jurusanRpl->id.'" selected', false)
            ->assertSee('value="Senin" selected', false)
            ->assertSee('value="Kelas"', false)
            ->assertSee('Tingkat: Kelas XI', false)
            ->assertSee('Jurusan: Rekayasa Perangkat Lunak', false);

        $this->assertSame(1, substr_count($response->getContent(), 'class="fw-bold text-dark">XI - Kelas RPL</div>'));
    }

    public function test_monitoring_uses_active_year_by_default_and_selected_archived_year_when_requested(): void
    {
        $admin = $this->adminTu();
        $activeYear = TahunAjaran::create([
            'tahun_ajaran' => '2026/2027',
            'semester' => 'Ganjil',
            'is_active' => true,
        ]);
        $archivedYear = TahunAjaran::create([
            'tahun_ajaran' => '2025/2026',
            'semester' => 'Genap',
            'is_active' => false,
        ]);
        $jurusan = Jurusan::create(['nama_jurusan' => 'MIPA', 'kode_jurusan' => 'MIPA']);
        $kelas = Kelas::create([
            'nama_kelas' => 'A',
            'tingkat' => 'X',
            'id_jurusan' => $jurusan->id,
        ]);
        $mapel = MataPelajaran::create(['nama_mapel' => 'Matematika', 'kode_mapel' => 'MTK']);
        $guru = $this->guru();

        $slots = [];
        foreach ([$activeYear, $archivedYear] as $year) {
            foreach ([1, 2] as $jamKe) {
                $slots[$year->id][$jamKe] = JamPelajaran::create([
                    'hari' => 'Senin',
                    'kategori_hari' => 'Senin-Kamis',
                    'jam_ke' => $jamKe,
                    'jam_mulai' => sprintf('%02d:00:00', 7 + $jamKe),
                    'jam_selesai' => sprintf('%02d:00:00', 8 + $jamKe),
                    'jenis' => 'kbm',
                    'tahun_ajaran_id' => $year->id,
                ]);
            }
        }

        foreach ([
            ['year' => $activeYear, 'slot' => $slots[$activeYear->id][1]],
            ['year' => $archivedYear, 'slot' => $slots[$archivedYear->id][2]],
        ] as $plotted) {
            JadwalPelajaran::create([
                'group_id' => (string) Str::uuid(),
                'hari' => 'Senin',
                'id_jam' => $plotted['slot']->id,
                'id_kelas' => $kelas->id,
                'id_mapel' => $mapel->id,
                'id_guru' => $guru->id,
                'id_tahun_ajaran' => $plotted['year']->id,
            ]);
        }

        $defaultResponse = $this->actingAs($admin)
            ->withSession(['jadwal_selected_tahun_ajaran_id' => $archivedYear->id])
            ->get(route('admin.jadwal.monitoring', ['hari' => 'Senin']))
            ->assertOk()
            ->assertViewHas('tahunAktif', fn ($year) => $year->is($activeYear));

        $defaultResponse->assertSee('2026/2027 — Ganjil (Aktif)', false)
            ->assertSee('[2]', false)
            ->assertDontSee('[1]', false);

        $selectedResponse = $this->actingAs($admin)
            ->get(route('admin.jadwal.monitoring', [
                'tahun_ajaran_id' => $archivedYear->id,
                'hari' => 'Senin',
            ]))
            ->assertOk()
            ->assertViewHas('tahunAktif', fn ($year) => $year->is($archivedYear));

        $selectedResponse->assertSee('2025/2026 — Genap', false)
            ->assertSee('[1]', false)
            ->assertDontSee('[2]', false);
    }

    public function test_non_kbm_slots_are_not_reported_as_kosong(): void
    {
        $admin = $this->adminTu();
        $this->seedSeninKamis(3);
        $this->seedKelas();

        // Simulasi data master yang memuat slot NON-KBM dengan jam_ke terisi
        // (mis. hasil import lama / preset yang tidak rapi): pembiasaan, upacara,
        // istirahat dengan jam_ke, dan 'pulang' (jam ke-13 "Pulang Sekolah").
        // Semuanya TIDAK boleh dihitung sebagai slot kosong.
        $nonKbm = [
            ['jam_ke' => 4, 'jenis' => 'pembiasaan', 'jam_mulai' => '11:00:00', 'jam_selesai' => '11:45:00'],
            ['jam_ke' => 5, 'jenis' => 'upacara',    'jam_mulai' => '07:00:00', 'jam_selesai' => '07:45:00'],
            ['jam_ke' => 6, 'jenis' => 'istirahat',  'jam_mulai' => '12:00:00', 'jam_selesai' => '12:30:00'],
            ['jam_ke' => 7, 'jenis' => 'pulang',     'jam_mulai' => '15:30:00', 'jam_selesai' => '16:15:00'],
        ];

        foreach ($nonKbm as $slot) {
            JamPelajaran::create([
                'hari' => 'Senin',
                'kategori_hari' => 'Senin-Kamis',
                'jam_ke' => $slot['jam_ke'],
                'jam_mulai' => $slot['jam_mulai'],
                'jam_selesai' => $slot['jam_selesai'],
                'jenis' => $slot['jenis'],
            ]);
        }

        $response = $this->actingAs($admin)->get(route('admin.jadwal.monitoring'));

        $response->assertOk()
            // Hanya KBM murni (jam 1-3) yang kosong — tanpa jam 4, 5, 6, atau 7.
            ->assertSee('[1, 2, 3]', false)
            ->assertDontSee('[1, 2, 3, 4', false)
            // Badge total slot kosong hanya menghitung slot KBM murni:
            // 3 slot x 4 hari Senin-Kamis = 12 (bukan 28 bila slot non-KBM ikut dihitung).
            ->assertSee('style="font-size: 1.35rem;">12</div>', false)
            ->assertDontSee('style="font-size: 1.35rem;">28</div>', false);
    }

    public function test_jam_pulang_boundary_excludes_slots_after_max_jam_ke(): void
    {
        $admin = $this->adminTu();

        // Master KBM hari Jumat: jam 1 s/d 13.
        foreach (range(1, 13) as $jamKe) {
            JamPelajaran::create([
                'hari' => 'Jumat',
                'kategori_hari' => 'Jumat',
                'jam_ke' => $jamKe,
                'jam_mulai' => sprintf('%02d:00:00', 7 + $jamKe),
                'jam_selesai' => sprintf('%02d:00:00', 7 + $jamKe + 1),
                'jenis' => 'kbm',
            ]);
        }

        // Kelas XI (tingkat disimpan sebagai huruf Romawi, sama dengan master jam_pulang).
        $jurusan = Jurusan::create(['nama_jurusan' => 'RPL', 'kode_jurusan' => 'RPL']);
        $kelas = Kelas::create([
            'nama_kelas' => 'RPL 1',
            'tingkat' => 'XI',
            'id_jurusan' => $jurusan->id,
        ]);
        TahunAjaran::create([
            'tahun_ajaran' => '2026/2027',
            'semester' => 'Ganjil',
            'is_active' => true,
        ]);

        // Setting Jam Pulang Kelas XI hari Jumat: pulang setelah Jam Ke-12.
        JamPulang::create([
            'kategori_hari' => 'Jumat',
            'tingkat' => 'XI',
            'max_jam_ke' => 12,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.jadwal.monitoring'));

        $response->assertOk()
            ->assertSee('XI - RPL 1', false)
            // Jam 13 (Pulang Sekolah) TIDAK boleh tampil sebagai slot kosong.
            ->assertDontSee('[13', false)
            // Badge total & daftar kosong hanya menghitung slot KBM aktif s/d jam 12.
            ->assertSee('style="font-size: 1.35rem;">12</div>', false);
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
                    'group_id' => (string) Str::uuid(),
                    'hari' => $hari,
                    'id_jam' => $slot->id,
                    'id_kelas' => $kelas->id,
                    'id_mapel' => $mapel->id,
                    'id_guru' => $guru->id,
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
