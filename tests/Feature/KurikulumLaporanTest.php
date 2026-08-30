<?php

namespace Tests\Feature;

use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\TahunAjaran;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class KurikulumLaporanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Kunci jam sistem ke 10 Agustus 2026 agar rentang default
        // (awal bulan -> hari ini) deterministik untuk data yang dibuat.
        Carbon::setTestNow(Carbon::create(2026, 8, 10));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function makeUser(string $role, ?string $subRole = null): User
    {
        return User::create([
            'nama'      => 'User ' . Str::random(5),
            'username'  => 'user_' . Str::random(8),
            'password'  => bcrypt('password'),
            'role'      => $role,
            'sub_role'  => $subRole,
            'is_active' => true,
        ]);
    }

    protected function makeSetup(): array
    {
        $admin = $this->makeUser('admin', 'waka_kurikulum');
        $guru  = $this->makeUser('guru', 'guru_mapel');

        $tahunAjaran = TahunAjaran::create(['tahun_ajaran' => '2026/2027', 'semester' => 'Ganjil', 'status_aktif' => true]);
        $kelasX      = Kelas::create(['nama_kelas' => 'X IPA 1', 'tingkat' => 'X']);
        $kelasXI     = Kelas::create(['nama_kelas' => 'XI IPA 1', 'tingkat' => 'XI']);
        $mapel       = MataPelajaran::create(['nama_mapel' => 'Matematika', 'kode_mapel' => 'MTK']);
        $jam         = JamPelajaran::create(['kategori_hari' => 'Senin-Kamis', 'jam_ke' => 1, 'jam_mulai' => '07:30:00', 'jam_selesai' => '09:00:00', 'jenis' => 'kbm']);

        $jadwalX = JadwalPelajaran::create([
            'group_id'       => (string) Str::uuid(),
            'hari'           => 'Senin',
            'id_jam'         => $jam->id,
            'id_kelas'       => $kelasX->id,
            'id_mapel'       => $mapel->id,
            'id_guru'        => $guru->id,
            'id_tahun_ajaran' => $tahunAjaran->id,
        ]);

        $jadwalXI = JadwalPelajaran::create([
            'group_id'       => (string) Str::uuid(),
            'hari'           => 'Selasa',
            'id_jam'         => $jam->id,
            'id_kelas'       => $kelasXI->id,
            'id_mapel'       => $mapel->id,
            'id_guru'        => $guru->id,
            'id_tahun_ajaran' => $tahunAjaran->id,
        ]);

        return compact('admin', 'guru', 'tahunAjaran', 'kelasX', 'kelasXI', 'mapel', 'jam', 'jadwalX', 'jadwalXI');
    }

    protected function makeJurnal(int $idJadwal, int $guruId, string $tanggal, string $materi, string $status): Jurnal
    {
        return Jurnal::create([
            'id_jadwal'        => $idJadwal,
            'id_guru'          => $guruId,
            'status_kehadiran' => $status,
            'tanggal'          => $tanggal,
            'materi'           => $materi,
            'waktu_isi'        => now(),
        ]);
    }

    public function test_laporan_index_renders_metrics_and_table(): void
    {
        $s = $this->makeSetup();
        $guruId = $s['guru']->id;

        $this->makeJurnal($s['jadwalX']->id, $guruId, '2026-08-03', 'Logaritma dan Eksponen', 'Hadir');
        $this->makeJurnal($s['jadwalX']->id, $guruId, '2026-08-04', '', 'Izin');
        $this->makeJurnal($s['jadwalXI']->id, $guruId, '2026-08-05', 'Trigonometri Dasar', 'Sakit');

        $response = $this->actingAs($s['admin'])->get(route('kurikulum.laporan.index'));

        $response->assertOk()
            ->assertSee('Total Jam KBM Terlaksana')
            ->assertSee('Kehadiran Guru')
            ->assertSee('Jurnal Mengajar Terisi')
            ->assertSee('X IPA 1')
            ->assertSee('XI IPA 1')
            ->assertSee('Matematika')
            ->assertSee('Logaritma dan Eksponen')
            ->assertSee('Trigonometri Dasar')
            ->assertSee('Hadir')
            ->assertSee('Izin')
            ->assertSee('Sakit');
    }

    public function test_laporan_filters_by_kelas_tingkat_guru_and_mapel(): void
    {
        $s = $this->makeSetup();
        $guruId = $s['guru']->id;

        $this->makeJurnal($s['jadwalX']->id, $guruId, '2026-08-03', 'Materi Kelas Sepuluh', 'Hadir');
        $this->makeJurnal($s['jadwalXI']->id, $guruId, '2026-08-05', 'Materi Kelas Sebelas', 'Hadir');

        // Filter by kelas
        $this->actingAs($s['admin'])
            ->get(route('kurikulum.laporan.index', ['id_kelas' => $s['kelasX']->id]))
            ->assertOk()
            ->assertSee('Materi Kelas Sepuluh')
            ->assertDontSee('Materi Kelas Sebelas');

        // Filter by tingkat
        $this->actingAs($s['admin'])
            ->get(route('kurikulum.laporan.index', ['tingkat' => 'XI']))
            ->assertOk()
            ->assertSee('Materi Kelas Sebelas')
            ->assertDontSee('Materi Kelas Sepuluh');

        // Filter by guru (via id_guru jurnal / jadwal)
        $this->actingAs($s['admin'])
            ->get(route('kurikulum.laporan.index', ['id_guru' => $guruId]))
            ->assertOk()
            ->assertSee('Materi Kelas Sebelas');

        // Filter by mapel
        $this->actingAs($s['admin'])
            ->get(route('kurikulum.laporan.index', ['id_mapel' => $s['mapel']->id]))
            ->assertOk()
            ->assertSee('Materi Kelas Sepuluh');
    }

    public function test_laporan_date_range_filter(): void
    {
        $s = $this->makeSetup();
        $guruId = $s['guru']->id;

        $this->makeJurnal($s['jadwalX']->id, $guruId, '2026-08-03', 'Dalam Rentang', 'Hadir');
        $this->makeJurnal($s['jadwalXI']->id, $guruId, '2026-08-20', 'Di Luar Rentang', 'Hadir'); // di luar 01-10

        $this->actingAs($s['admin'])
            ->get(route('kurikulum.laporan.index', [
                'tanggal_mulai'   => '2026-08-01',
                'tanggal_selesai' => '2026-08-10',
            ]))
            ->assertOk()
            ->assertSee('Dalam Rentang')
            ->assertDontSee('Di Luar Rentang');
    }

    public function test_laporan_print_page_renders(): void
    {
        $s = $this->makeSetup();
        $guruId = $s['guru']->id;

        $this->makeJurnal($s['jadwalX']->id, $guruId, '2026-08-03', 'Materi Cetak', 'Hadir');

        $this->actingAs($s['admin'])
            ->get(route('kurikulum.laporan.print', ['tanggal_mulai' => '2026-08-01', 'tanggal_selesai' => '2026-08-10']))
            ->assertOk()
            ->assertSee('LAPORAN KEGIATAN BELAJAR MENGAJAR')
            ->assertSee('Cetak / Save PDF')
            ->assertSee('Materi Cetak');
    }

    public function test_laporan_excel_download(): void
    {
        $s = $this->makeSetup();
        $guruId = $s['guru']->id;

        $this->makeJurnal($s['jadwalX']->id, $guruId, '2026-08-03', 'Materi Export', 'Hadir');

        $this->actingAs($s['admin'])
            ->get(route('kurikulum.laporan.excel'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->assertSee('LAPORAN KEGIATAN BELAJAR MENGAJAR')
            ->assertSee('Materi Export');
    }

    public function test_admin_laporan_menu_redirects_to_kurikulum_laporan(): void
    {
        $this->actingAs($this->makeUser('admin'))
            ->get(route('laporan.index'))
            ->assertRedirect(route('kurikulum.laporan.index'));
    }
}