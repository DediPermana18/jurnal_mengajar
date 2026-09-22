<?php

namespace Database\Seeders;

use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Ruangan;
use App\Models\Scopes\TestingDataScope;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seed "kandidat akun" untuk menu Switch View As → Guru Mapel.
 *
 * Membuat sandbox data testing (is_testing_data = 1) yang lengkap dan
 * self-contained: akun guru.tester berjadwal, kelas, mapel, jam pelajaran,
 * ruangan, jurusan, tahun ajaran & siswa. Ketika Petugas IT / QA Tester
 * berpindah ke view Guru Mapel, sesi auth akan di-swap ke akun ini sehingga
 * halaman /guru/jurnal & /guru/dashboard menampilkan jadwal dummy secara
 * realtime — mirip "akun produksi" tanpa mencemari data real.
 *
 * Idempotent: seluruh operasi memakai updateOrCreate/firstOrNew berbasis
 * withoutGlobalScope(TestingDataScope), sehingga aman dijalankan ulang.
 *
 * Catatan: slot jam pelajaran sandbox memakai jam_ke 101+ (Senin-Kamis) dan
 * 201+ (Jumat) agar TIDAK menimpa master jam pelajaran real (jam_ke 1-10).
 */
class TestingGuruSeeder extends Seeder
{
    private const USERNAME_GURU = 'guru.tester';

    private const USERNAME_WALI_KELAS = 'wali.tester';

    public function run(): void
    {
        $this->command->info(' [TestingGuruSeeder] Membuat sandbox Guru Mapel testing (is_testing_data=1) ...');

        $tahunAjaran = TahunAjaran::withoutGlobalScope(TestingDataScope::class)->updateOrCreate(
            ['tahun_ajaran' => '2027/2028', 'semester' => 'Ganjil'],
            ['is_active' => true, 'is_testing_data' => true]
        );

        $jurusan = Jurusan::withoutGlobalScope(TestingDataScope::class)->updateOrCreate(
            ['kode_jurusan' => 'RPL'],
            ['nama_jurusan' => 'Rekayasa Perangkat Lunak', 'is_testing_data' => true]
        );

        $ruangan = Ruangan::withoutGlobalScope(TestingDataScope::class)->updateOrCreate(
            ['kode_ruangan' => 'R-TST'],
            [
                'nama_ruangan' => 'Lab Komputer Testing',
                'lokasi' => 'Gedung QA Lantai 3',
                'is_testing_data' => true,
            ]
        );

        $guru = User::withoutGlobalScope(TestingDataScope::class)->updateOrCreate(
            ['username' => self::USERNAME_GURU],
            [
                'nama' => 'Restu Purwanti, S.Kom.',
                'nip' => '199501012025011007',
                'email' => 'guru.tester@school.id',
                'no_hp' => '6281234569001',
                'password' => Hash::make('password'),
                'kode_aktivasi' => null,
                'role' => User::ROLE_GURU,
                'sub_role' => 'guru_mapel',
                'kelas_id' => null,
                'is_active' => true,
                'is_testing_data' => true,
            ]
        );

        $kelas = Kelas::withoutGlobalScope(TestingDataScope::class)->updateOrCreate(
            ['tingkat' => 'X', 'nama_kelas' => 'RPL 1'],
            ['id_jurusan' => $jurusan->id, 'id_wali_kelas' => null, 'is_testing_data' => true]
        );

        $waliKelas = User::withoutGlobalScope(TestingDataScope::class)->updateOrCreate(
            ['username' => self::USERNAME_WALI_KELAS],
            [
                'nama' => 'Sari Waluyo, S.Pd.',
                'nip' => '199701012025011008',
                'email' => 'wali.tester@school.id',
                'no_hp' => '6281234569002',
                'password' => Hash::make('password'),
                'kode_aktivasi' => null,
                'role' => User::ROLE_GURU,
                'sub_role' => 'wali_kelas',
                'kelas_id' => null,
                'is_active' => true,
                'is_testing_data' => true,
            ]
        );

        // Kelas sandbox dibimbing oleh wali.tester (target view Wali Kelas).
        $kelas->update(['id_wali_kelas' => $waliKelas->id]);

        $mapelDasar = MataPelajaran::withoutGlobalScope(TestingDataScope::class)->updateOrCreate(
            ['kode_mapel' => 'MPL-TST-01'],
            [
                'nama_mapel' => 'Pemrograman Dasar',
                'kelompok' => 'Kejuruan',
                'jurusan_id' => $jurusan->id,
                'is_testing_data' => true,
            ]
        );

        $mapelDatabase = MataPelajaran::withoutGlobalScope(TestingDataScope::class)->updateOrCreate(
            ['kode_mapel' => 'MPL-TST-02'],
            [
                'nama_mapel' => 'Basis Data',
                'kelompok' => 'Kejuruan',
                'jurusan_id' => $jurusan->id,
                'is_testing_data' => true,
            ]
        );

        $siswaList = [
            ['nis' => '9001', 'nisn' => '9000000001', 'nama' => 'Aria Pratama', 'jenis_kelamin' => 'L'],
            ['nis' => '9002', 'nisn' => '9000000002', 'nama' => 'Bella Safitri', 'jenis_kelamin' => 'P'],
            ['nis' => '9003', 'nisn' => '9000000003', 'nama' => 'Candra Wijaya', 'jenis_kelamin' => 'L'],
            ['nis' => '9004', 'nisn' => '9000000004', 'nama' => 'Devi Anggraini', 'jenis_kelamin' => 'P'],
            ['nis' => '9005', 'nisn' => '9000000005', 'nama' => 'Erik Gunawan', 'jenis_kelamin' => 'L'],
        ];

        foreach ($siswaList as $siswa) {
            Siswa::withoutGlobalScope(TestingDataScope::class)->updateOrCreate(
                ['nisn' => $siswa['nisn']],
                [
                    'nis' => $siswa['nis'],
                    'nama' => $siswa['nama'],
                    'jenis_kelamin' => $siswa['jenis_kelamin'],
                    'id_kelas' => $kelas->id,
                    'id_jurusan' => $jurusan->id,
                    'status_siswa' => 'Aktif',
                    'is_testing_data' => true,
                ]
            );
        }

        $jamSlots = $this->jamPelajaranSandbox();

        foreach ($jamSlots as $kategori => $rows) {
            $days = ($kategori === 'Senin-Kamis') ? ['Senin', 'Selasa', 'Rabu', 'Kamis'] : ['Jumat'];
            foreach ($days as $day) {
                foreach ($rows as $row) {
                    $jam = JamPelajaran::withoutGlobalScope(TestingDataScope::class)
                        ->firstOrNew(['hari' => $day, 'jam_ke' => $row['jam_ke']]);
                    $jam->hari = $day;
                    $jam->kategori_hari = $kategori;
                    $jam->jam_mulai = $row['jam_mulai'];
                    $jam->jam_selesai = $row['jam_selesai'];
                    $jam->jenis = 'kbm';
                    $jam->is_testing_data = true;
                    $jam->save();
                }
            }
        }

        $this->jadwalPelajaranSandbox($guru, $kelas, $ruangan, $tahunAjaran, $mapelDasar, $mapelDatabase);

        $this->command->info(' [TestingGuruSeeder] Selesai. Akun impersonasi: '.self::USERNAME_GURU.' / password (email: guru.tester@school.id)');
    }

    /**
     * Slot jam pelajaran sandbox (kategori standar, jam_ke di-offset agar
     * tidak menabrak master real).
     *
     * @return array<string, array<int, array{jam_ke:int, jam_mulai:string, jam_selesai:string}>>
     */
    private function jamPelajaranSandbox(): array
    {
        $seninKamis = [
            ['jam_ke' => 101, 'jam_mulai' => '07:00:00', 'jam_selesai' => '07:45:00'],
            ['jam_ke' => 102, 'jam_mulai' => '07:45:00', 'jam_selesai' => '08:30:00'],
            ['jam_ke' => 103, 'jam_mulai' => '08:30:00', 'jam_selesai' => '09:15:00'],
            ['jam_ke' => 104, 'jam_mulai' => '09:15:00', 'jam_selesai' => '10:00:00'],
            ['jam_ke' => 105, 'jam_mulai' => '10:00:00', 'jam_selesai' => '10:45:00'],
            ['jam_ke' => 106, 'jam_mulai' => '11:00:00', 'jam_selesai' => '11:45:00'],
            ['jam_ke' => 107, 'jam_mulai' => '11:45:00', 'jam_selesai' => '12:30:00'],
            ['jam_ke' => 108, 'jam_mulai' => '13:00:00', 'jam_selesai' => '13:45:00'],
        ];

        $jumat = [
            ['jam_ke' => 201, 'jam_mulai' => '07:00:00', 'jam_selesai' => '07:40:00'],
            ['jam_ke' => 202, 'jam_mulai' => '07:40:00', 'jam_selesai' => '08:20:00'],
            ['jam_ke' => 203, 'jam_mulai' => '08:20:00', 'jam_selesai' => '09:00:00'],
        ];

        return ['Senin-Kamis' => $seninKamis, 'Jumat' => $jumat];
    }

    /**
     * Plotting jadwal sandbox guru.tester untuk seluruh hari sekolah.
     */
    private function jadwalPelajaranSandbox(
        User $guru,
        Kelas $kelas,
        Ruangan $ruangan,
        TahunAjaran $tahunAjaran,
        MataPelajaran $mapelDasar,
        MataPelajaran $mapelDatabase
    ): void {
        $jamMap = JamPelajaran::withoutGlobalScope(TestingDataScope::class)
            ->where('is_testing_data', true)
            ->get()
            ->keyBy(fn (JamPelajaran $j) => $j->hari.'|'.$j->jam_ke);

        // Blok jadwal per hari: [hari, [mapel, [jam_ke...]]]
        $blok = [
            ['Senin', $mapelDasar,    [101, 102]],
            ['Senin', $mapelDatabase, [103, 104]],
            ['Selasa', $mapelDasar,   [105]],
            ['Selasa', $mapelDatabase, [106, 107]],
            ['Rabu', $mapelDasar,     [101, 102, 103]],
            ['Rabu', $mapelDatabase,  [106, 107]],
            ['Kamis', $mapelDasar,    [104, 105]],
            ['Kamis', $mapelDatabase, [108]],
            ['Jumat', $mapelDasar,    [201, 202]],
            ['Jumat', $mapelDatabase, [203]],
        ];

        foreach ($blok as [$hari, $mapel, $jamKeList]) {
            $groupId = (string) Str::uuid();

            foreach ($jamKeList as $jamKe) {
                $jam = $jamMap->get($hari.'|'.$jamKe);
                if (! $jam) {
                    continue;
                }

                JadwalPelajaran::withoutGlobalScope(TestingDataScope::class)->updateOrCreate(
                    [
                        'id_guru' => $guru->id,
                        'hari' => $hari,
                        'id_jam' => $jam->id,
                        'id_tahun_ajaran' => $tahunAjaran->id,
                    ],
                    [
                        'group_id' => $groupId,
                        'id_kelas' => $kelas->id,
                        'id_mapel' => $mapel->id,
                        'id_ruangan' => $ruangan->id,
                        'is_testing_data' => true,
                    ]
                );
            }
        }
    }
}
