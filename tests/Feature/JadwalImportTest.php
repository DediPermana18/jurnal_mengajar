<?php

namespace Tests\Feature;

use App\Imports\JadwalImport;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Ruangan;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Tests\TestCase;

class JadwalImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        TahunAjaran::create([
            'tahun_ajaran' => '2025/2026',
            'semester' => 'Ganjil',
            'is_active' => true,
        ]);
    }

    public function test_imports_jadwal_and_links_to_active_master_jam_by_hari_and_jam_ke(): void
    {
        $kelas = Kelas::create(['nama_kelas' => 'RPL 1', 'tingkat' => 'X']);
        $mapel = MataPelajaran::create(['nama_mapel' => 'Matematika', 'kode_mapel' => 'MTK']);
        $guru  = User::create([
            'nama' => 'Pak Budi',
            'username' => 'budi_'.Str::random(4),
            'password' => bcrypt('password'),
            'role' => User::ROLE_GURU,
            'is_active' => true,
        ]);

        $jamSenin1 = JamPelajaran::create([
            'hari' => 'Senin',
            'kategori_hari' => 'Senin-Kamis',
            'jam_ke' => 1,
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '07:45:00',
            'jenis' => 'kbm',
        ]);

        $jamSelasa1 = JamPelajaran::create([
            'hari' => 'Selasa',
            'kategori_hari' => 'Senin-Kamis',
            'jam_ke' => 1,
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '07:45:00',
            'jenis' => 'kbm',
        ]);

        $rows = new Collection([
            [
                'kelas' => 'X RPL 1',
                'hari' => 'Senin',
                'jam' => '1',
                'mata_pelajaran' => 'Matematika',
                'guru' => 'Pak Budi',
            ],
            [
                'kelas' => 'X RPL 1',
                'hari' => 'Selasa',
                'jam' => '1',
                'mata_pelajaran' => 'Matematika',
                'guru' => 'Pak Budi',
            ],
        ]);

        $import = new JadwalImport();
        $import->collection($rows);

        $this->assertEquals(2, $import->importedCount);
        $this->assertEmpty($import->rowErrors);

        // Verify id_jam on Senin belongs to jamSenin1
        $jadwalSenin = JadwalPelajaran::where('hari', 'Senin')->first();
        $this->assertNotNull($jadwalSenin);
        $this->assertEquals($jamSenin1->id, $jadwalSenin->id_jam);

        // Verify id_jam on Selasa belongs to jamSelasa1 (NOT jamSenin1)
        $jadwalSelasa = JadwalPelajaran::where('hari', 'Selasa')->first();
        $this->assertNotNull($jadwalSelasa);
        $this->assertEquals($jamSelasa1->id, $jadwalSelasa->id_jam);
    }

    public function test_re_importing_jadwal_updates_existing_records_with_correct_master_jam_fk(): void
    {
        $kelas = Kelas::create(['nama_kelas' => 'RPL 2', 'tingkat' => 'XI']);
        $mapel = MataPelajaran::create(['nama_mapel' => 'Bahasa Indonesia', 'kode_mapel' => 'BIN']);
        $guru  = User::create([
            'nama' => 'Bu Ani',
            'username' => 'ani_'.Str::random(4),
            'password' => bcrypt('password'),
            'role' => User::ROLE_GURU,
            'is_active' => true,
        ]);

        $jamSenin2 = JamPelajaran::create([
            'hari' => 'Senin',
            'kategori_hari' => 'Senin-Kamis',
            'jam_ke' => 2,
            'jam_mulai' => '07:45:00',
            'jam_selesai' => '08:30:00',
            'jenis' => 'kbm',
        ]);

        $rows = new Collection([
            [
                'kelas' => 'XI RPL 2',
                'hari' => 'Senin',
                'jam' => '2',
                'mata_pelajaran' => 'Bahasa Indonesia',
                'guru' => 'Bu Ani',
            ],
        ]);

        // First import
        $import1 = new JadwalImport();
        $import1->collection($rows);
        $this->assertEquals(1, $import1->importedCount);

        // Second import (re-import)
        $import2 = new JadwalImport();
        $import2->collection($rows);

        $this->assertEquals(0, $import2->importedCount);
        $this->assertEquals(1, $import2->updatedCount);

        $jadwal = JadwalPelajaran::where('id_kelas', $kelas->id)->where('hari', 'Senin')->first();
        $this->assertNotNull($jadwal);
        $this->assertEquals($jamSenin2->id, $jadwal->id_jam);
    }

    public function test_skips_non_kbm_slots_like_istirahat_and_agenda_rutin(): void
    {
        $kelas = Kelas::create(['nama_kelas' => 'RPL 1', 'tingkat' => 'X']);
        $mapel = MataPelajaran::create(['nama_mapel' => 'Fisika', 'kode_mapel' => 'FIS']);
        $guru  = User::create([
            'nama' => 'Pak Joko',
            'username' => 'joko_'.Str::random(4),
            'password' => bcrypt('password'),
            'role' => User::ROLE_GURU,
            'is_active' => true,
        ]);

        JamPelajaran::create([
            'hari' => 'Senin',
            'kategori_hari' => 'Senin-Kamis',
            'jam_ke' => 5,
            'jam_mulai' => '10:00:00',
            'jam_selesai' => '10:15:00',
            'jenis' => 'istirahat',
        ]);

        JamPelajaran::create([
            'hari' => 'Senin',
            'kategori_hari' => 'Senin-Kamis',
            'jam_ke' => 6,
            'jam_mulai' => '10:15:00',
            'jam_selesai' => '11:00:00',
            'jenis' => 'agenda_rutin',
        ]);

        $rows = new Collection([
            [
                'kelas' => 'X RPL 1',
                'hari' => 'Senin',
                'jam' => '5',
                'mata_pelajaran' => 'Fisika',
                'guru' => 'Pak Joko',
            ],
            [
                'kelas' => 'X RPL 1',
                'hari' => 'Senin',
                'jam' => '6',
                'mata_pelajaran' => 'Fisika',
                'guru' => 'Pak Joko',
            ],
        ]);

        $import = new JadwalImport();
        $import->collection($rows);

        $this->assertEquals(0, $import->importedCount);
        $this->assertEquals(2, $import->skippedCount);
        $this->assertEquals(0, JadwalPelajaran::count());
    }

    public function test_logs_error_when_master_jam_is_not_found_for_given_hari_and_jam_ke(): void
    {
        $kelas = Kelas::create(['nama_kelas' => 'RPL 1', 'tingkat' => 'X']);
        $mapel = MataPelajaran::create(['nama_mapel' => 'Biologi', 'kode_mapel' => 'BIO']);
        $guru  = User::create([
            'nama' => 'Bu Sita',
            'username' => 'sita_'.Str::random(4),
            'password' => bcrypt('password'),
            'role' => User::ROLE_GURU,
            'is_active' => true,
        ]);

        JamPelajaran::create([
            'hari' => 'Senin',
            'kategori_hari' => 'Senin-Kamis',
            'jam_ke' => 1,
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '07:45:00',
            'jenis' => 'kbm',
        ]);

        $rows = new Collection([
            [
                'kelas' => 'X RPL 1',
                'hari' => 'Senin',
                'jam' => '99',
                'mata_pelajaran' => 'Biologi',
                'guru' => 'Bu Sita',
            ],
        ]);

        $import = new JadwalImport();
        $import->collection($rows);

        $this->assertEquals(0, $import->importedCount);
        $this->assertEquals(1, $import->skippedCount);
        $this->assertCount(1, $import->rowErrors);
        $this->assertStringContainsString('Master jam', $import->rowErrors[0]);
        $this->assertStringContainsString('Senin', $import->rowErrors[0]);
    }

    public function test_resolve_guru_flexible_title_and_punctuation_matching(): void
    {
        $kelas = Kelas::create(['nama_kelas' => 'RPL 1', 'tingkat' => 'X']);
        $mapel = MataPelajaran::create(['nama_mapel' => 'Pemrograman Web', 'kode_mapel' => 'WEB']);
        $guru  = User::create([
            'nama' => 'Ahmad Fauzi',
            'username' => 'fauzi_'.Str::random(4),
            'password' => bcrypt('password'),
            'role' => User::ROLE_GURU,
            'is_active' => true,
        ]);

        JamPelajaran::create([
            'hari' => 'Senin',
            'kategori_hari' => 'Senin-Kamis',
            'jam_ke' => 1,
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '07:45:00',
            'jenis' => 'kbm',
        ]);

        // CSV name includes titles and dots/commas
        $rows = new Collection([
            [
                'kelas' => 'X RPL 1',
                'hari' => 'Senin',
                'jam' => '1',
                'mata_pelajaran' => 'Pemrograman Web',
                'guru' => 'Drs. H. Ahmad Fauzi, S.Pd., M.M.',
            ],
        ]);

        $import = new JadwalImport();
        $import->collection($rows);

        $this->assertEquals(1, $import->importedCount);
        $this->assertEmpty($import->rowErrors);

        $jadwal = JadwalPelajaran::first();
        $this->assertEquals($guru->id, $jadwal->id_guru);
    }

    public function test_auto_creates_missing_ruangan_during_jadwal_import(): void
    {
        $kelas = Kelas::create(['nama_kelas' => 'RPL 1', 'tingkat' => 'X']);
        $mapel = MataPelajaran::create(['nama_mapel' => 'Olahraga', 'kode_mapel' => 'PJOK']);
        $guru  = User::create([
            'nama' => 'Bambang',
            'username' => 'bambang_'.Str::random(4),
            'password' => bcrypt('password'),
            'role' => User::ROLE_GURU,
            'is_active' => true,
        ]);

        JamPelajaran::create([
            'hari' => 'Senin',
            'kategori_hari' => 'Senin-Kamis',
            'jam_ke' => 1,
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '07:45:00',
            'jenis' => 'kbm',
        ]);

        // Room 'LAP' does not exist in master Ruangan before import
        $this->assertDatabaseMissing('ruangans', ['kode_ruangan' => 'LAP']);

        $rows = new Collection([
            [
                'kelas' => 'X RPL 1',
                'hari' => 'Senin',
                'jam' => '1',
                'mata_pelajaran' => 'Olahraga',
                'guru' => 'Bambang',
                'ruang' => 'LAP',
            ],
        ]);

        $import = new JadwalImport();
        $import->collection($rows);

        $this->assertEquals(1, $import->importedCount);
        $this->assertEmpty($import->rowErrors);

        // Verify room 'LAP' was auto-created and linked
        $ruangan = Ruangan::where('kode_ruangan', 'LAP')->first();
        $this->assertNotNull($ruangan);

        $jadwal = JadwalPelajaran::first();
        $this->assertEquals($ruangan->id, $jadwal->id_ruangan);
    }

    public function test_resolve_mapel_tolerant_whitespace_and_punctuation_matching(): void
    {
        $kelas = Kelas::create(['nama_kelas' => 'RPL 1', 'tingkat' => 'X']);
        $mapel = MataPelajaran::create(['nama_mapel' => 'Pendidikan Jasmani', 'kode_mapel' => 'PJOK']);
        $guru  = User::create([
            'nama' => 'Denny',
            'username' => 'denny_'.Str::random(4),
            'password' => bcrypt('password'),
            'role' => User::ROLE_GURU,
            'is_active' => true,
        ]);

        JamPelajaran::create([
            'hari' => 'Senin',
            'kategori_hari' => 'Senin-Kamis',
            'jam_ke' => 1,
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '07:45:00',
            'jenis' => 'kbm',
        ]);

        // CSV uses "P.J.O.K" with dots or extra spaces
        $rows = new Collection([
            [
                'kelas' => 'X RPL 1',
                'hari' => 'Senin',
                'jam' => '1',
                'mata_pelajaran' => 'P.J.O.K',
                'guru' => 'Denny',
            ],
        ]);

        $import = new JadwalImport();
        $import->collection($rows);

        $this->assertEquals(1, $import->importedCount);
        $this->assertEmpty($import->rowErrors);

        $jadwal = JadwalPelajaran::first();
        $this->assertEquals($mapel->id, $jadwal->id_mapel);
    }

    public function test_multi_jam_slot_parsing_creates_multiple_jadwal_records(): void
    {
        $kelas = Kelas::create(['nama_kelas' => 'TKI 1', 'tingkat' => 'X']);
        $mapel = MataPelajaran::create(['nama_mapel' => 'Bahasa Indonesia', 'kode_mapel' => 'BIN']);
        $guru  = User::create([
            'nama' => 'Yani, S.Pd.',
            'username' => 'yani_'.Str::random(4),
            'password' => bcrypt('password'),
            'role' => User::ROLE_GURU,
            'is_active' => true,
        ]);

        $jam4 = JamPelajaran::create(['hari' => 'Senin', 'kategori_hari' => 'Senin-Kamis', 'jam_ke' => 4, 'jam_mulai' => '09:15:00', 'jam_selesai' => '10:00:00', 'jenis' => 'kbm']);
        $jam5 = JamPelajaran::create(['hari' => 'Senin', 'kategori_hari' => 'Senin-Kamis', 'jam_ke' => 5, 'jam_mulai' => '10:00:00', 'jam_selesai' => '10:45:00', 'jenis' => 'kbm']);
        $jam6 = JamPelajaran::create(['hari' => 'Senin', 'kategori_hari' => 'Senin-Kamis', 'jam_ke' => 6, 'jam_mulai' => '11:00:00', 'jam_selesai' => '11:45:00', 'jenis' => 'kbm']);

        $rows = new Collection([
            [
                'kelas' => 'X TKI 1',
                'hari' => 'Senin',
                'jam' => '4.5.6',
                'mata_pelajaran' => 'Bahasa Indonesia',
                'guru' => 'Yani, S.Pd.',
                'ruang' => 'Lab. KI 1',
            ],
        ]);

        $import = new JadwalImport();
        $import->collection($rows);

        $this->assertEquals(3, $import->importedCount);
        $this->assertEmpty($import->rowErrors);

        $jadwals = JadwalPelajaran::orderBy('id_jam')->get();
        $this->assertCount(3, $jadwals);
        $this->assertEquals($jam4->id, $jadwals[0]->id_jam);
        $this->assertEquals($jam5->id, $jadwals[1]->id_jam);
        $this->assertEquals($jam6->id, $jadwals[2]->id_jam);

        // Verify shared group_id
        $this->assertEquals($jadwals[0]->group_id, $jadwals[1]->group_id);
        $this->assertEquals($jadwals[1]->group_id, $jadwals[2]->group_id);
    }

    public function test_range_jam_slot_parsing_creates_multiple_jadwal_records(): void
    {
        $kelas = Kelas::create(['nama_kelas' => 'TKI 1', 'tingkat' => 'X']);
        $mapel = MataPelajaran::create(['nama_mapel' => 'Matematika', 'kode_mapel' => 'MTK']);
        $guru  = User::create([
            'nama' => 'Pak Budi',
            'username' => 'budi_'.Str::random(4),
            'password' => bcrypt('password'),
            'role' => User::ROLE_GURU,
            'is_active' => true,
        ]);

        $jam2 = JamPelajaran::create(['hari' => 'Senin', 'kategori_hari' => 'Senin-Kamis', 'jam_ke' => 2, 'jam_mulai' => '07:45:00', 'jam_selesai' => '08:30:00', 'jenis' => 'kbm']);
        $jam3 = JamPelajaran::create(['hari' => 'Senin', 'kategori_hari' => 'Senin-Kamis', 'jam_ke' => 3, 'jam_mulai' => '08:30:00', 'jam_selesai' => '09:15:00', 'jenis' => 'kbm']);

        $rows = new Collection([
            [
                'kelas' => 'X TKI 1',
                'hari' => 'Senin',
                'jam' => '2-3',
                'mata_pelajaran' => 'Matematika',
                'guru' => 'Pak Budi',
            ],
        ]);

        $import = new JadwalImport();
        $import->collection($rows);

        $this->assertEquals(2, $import->importedCount);
        $this->assertEmpty($import->rowErrors);

        $jadwals = JadwalPelajaran::orderBy('id_jam')->get();
        $this->assertCount(2, $jadwals);
        $this->assertEquals($jam2->id, $jadwals[0]->id_jam);
        $this->assertEquals($jam3->id, $jadwals[1]->id_jam);
    }
}
