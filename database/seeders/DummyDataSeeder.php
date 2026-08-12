<?php

namespace Database\Seeders;

use App\Models\AbsensiJurnal;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DummyDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Seed Tahun Ajaran
        $tahunAktif = TahunAjaran::updateOrCreate(
            ['tahun_ajaran' => '2024/2025', 'semester' => 'Ganjil'],
            ['status_aktif' => true]
        );

        TahunAjaran::updateOrCreate(
            ['tahun_ajaran' => '2024/2025', 'semester' => 'Genap'],
            ['status_aktif' => false]
        );

        // 2. Seed Mata Pelajaran
        $mtk = MataPelajaran::updateOrCreate(
            ['kode_mapel' => 'MTK-01'],
            ['nama_mapel' => 'Matematika']
        );

        $bin = MataPelajaran::updateOrCreate(
            ['kode_mapel' => 'BIN-01'],
            ['nama_mapel' => 'Bahasa Indonesia']
        );

        $big = MataPelajaran::updateOrCreate(
            ['kode_mapel' => 'BIG-01'],
            ['nama_mapel' => 'Bahasa Inggris']
        );

        $inf = MataPelajaran::updateOrCreate(
            ['kode_mapel' => 'INF-01'],
            ['nama_mapel' => 'Informatika']
        );

        $fis = MataPelajaran::updateOrCreate(
            ['kode_mapel' => 'FIS-01'],
            ['nama_mapel' => 'Fisika']
        );

        // 3. Seed Jam Pelajaran
        $jamSeninKamis = [
            ['jam_ke' => 1, 'jam_mulai' => '07:00:00', 'jam_selesai' => '07:45:00'],
            ['jam_ke' => 2, 'jam_mulai' => '07:45:00', 'jam_selesai' => '08:30:00'],
            ['jam_ke' => 3, 'jam_mulai' => '08:30:00', 'jam_selesai' => '09:15:00'],
            ['jam_ke' => 4, 'jam_mulai' => '09:30:00', 'jam_selesai' => '10:15:00'],
        ];

        foreach ($jamSeninKamis as $j) {
            JamPelajaran::updateOrCreate(
                ['kategori_hari' => 'Senin-Kamis', 'jam_ke' => $j['jam_ke']],
                ['jam_mulai' => $j['jam_mulai'], 'jam_selesai' => $j['jam_selesai']]
            );
        }

        $jamJumat = [
            ['jam_ke' => 1, 'jam_mulai' => '07:00:00', 'jam_selesai' => '07:40:00'],
            ['jam_ke' => 2, 'jam_mulai' => '07:40:00', 'jam_selesai' => '08:20:00'],
        ];

        foreach ($jamJumat as $j) {
            JamPelajaran::updateOrCreate(
                ['kategori_hari' => 'Jumat', 'jam_ke' => $j['jam_ke']],
                ['jam_mulai' => $j['jam_mulai'], 'jam_selesai' => $j['jam_selesai']]
            );
        }

        // Ambil ID Guru
        $guruBudi   = User::where('username', 'gurbudi')->first();
        $guruSiti   = User::where('username', 'gursiti')->first();
        $guruAhmad  = User::where('username', 'gurahmad')->first();

        // 4. Seed Kelas
        $kelas10 = Kelas::updateOrCreate(
            ['nama_kelas' => 'X IPA 1'],
            ['tingkat' => 'X', 'id_wali_kelas' => $guruBudi?->id]
        );

        $kelas11 = Kelas::updateOrCreate(
            ['nama_kelas' => 'XI IPA 1'],
            ['tingkat' => 'XI', 'id_wali_kelas' => $guruSiti?->id]
        );

        $kelas12 = Kelas::updateOrCreate(
            ['nama_kelas' => 'XII IPA 1'],
            ['tingkat' => 'XII', 'id_wali_kelas' => $guruAhmad?->id]
        );

        // 5. Seed Siswa
        $siswaData = [
            ['nisn' => '0051234001', 'nis' => '241001', 'nama' => 'Aditya Pratama', 'jenis_kelamin' => 'L', 'id_kelas' => $kelas10->id, 'status_siswa' => 'Aktif'],
            ['nisn' => '0051234002', 'nis' => '241002', 'nama' => 'Anisa Putri', 'jenis_kelamin' => 'P', 'id_kelas' => $kelas10->id, 'status_siswa' => 'Aktif'],
            ['nisn' => '0051234003', 'nis' => '241003', 'nama' => 'Bagus Setiawan', 'jenis_kelamin' => 'L', 'id_kelas' => $kelas10->id, 'status_siswa' => 'Aktif'],
            ['nisn' => '0051234004', 'nis' => '241004', 'nama' => 'Citra Dewi', 'jenis_kelamin' => 'P', 'id_kelas' => $kelas10->id, 'status_siswa' => 'Aktif'],
            ['nisn' => '0051234005', 'nis' => '241005', 'nama' => 'Dwi Cahyo', 'jenis_kelamin' => 'L', 'id_kelas' => $kelas10->id, 'status_siswa' => 'Aktif'],
            ['nisn' => '0041234001', 'nis' => '231001', 'nama' => 'Eka Wijaya', 'jenis_kelamin' => 'L', 'id_kelas' => $kelas11->id, 'status_siswa' => 'Aktif'],
            ['nisn' => '0041234002', 'nis' => '231002', 'nama' => 'Fadhilah Nur', 'jenis_kelamin' => 'P', 'id_kelas' => $kelas11->id, 'status_siswa' => 'Aktif'],
        ];

        foreach ($siswaData as $s) {
            Siswa::updateOrCreate(['nisn' => $s['nisn']], $s);
        }

        // 6. Seed Jadwal Pelajaran
        $jam1 = JamPelajaran::where('kategori_hari', 'Senin-Kamis')->where('jam_ke', 1)->first();
        $jam2 = JamPelajaran::where('kategori_hari', 'Senin-Kamis')->where('jam_ke', 2)->first();

        if ($jam1 && $guruBudi && $tahunAktif) {
            $jadwal1 = JadwalPelajaran::updateOrCreate(
                [
                    'id_kelas'        => $kelas10->id,
                    'hari'            => 'Senin',
                    'id_jam'          => $jam1->id,
                    'id_tahun_ajaran' => $tahunAktif->id,
                ],
                [
                    'group_id' => Str::uuid()->toString(),
                    'id_mapel' => $mtk->id,
                    'id_guru'  => $guruBudi->id,
                ]
            );

            // 7. Seed Jurnal Contoh
            $jurnal1 = Jurnal::updateOrCreate(
                ['id_jadwal' => $jadwal1->id, 'tanggal' => now()->format('Y-m-d')],
                [
                    'materi'           => 'Persamaan dan Pertidaksamaan Linear Satu Variabel',
                    'catatan_kejadian' => 'Siswa antusias dan aktif berdiskusi.',
                    'foto_kegiatan'    => null,
                    'waktu_isi'        => now(),
                ]
            );

            // 8. Seed Absensi Jurnal Contoh
            $siswasK10 = Siswa::where('id_kelas', $kelas10->id)->get();
            $statuses = ['Hadir', 'Hadir', 'Hadir', 'Izin', 'Hadir'];

            foreach ($siswasK10 as $idx => $sis) {
                AbsensiJurnal::updateOrCreate(
                    [
                        'id_jurnal' => $jurnal1->id,
                        'id_siswa'  => $sis->id,
                    ],
                    [
                        'status'     => $statuses[$idx % count($statuses)],
                        'keterangan' => $statuses[$idx % count($statuses)] === 'Izin' ? 'Surat izin dari orang tua' : null,
                    ]
                );
            }
        }

        if ($jam2 && $guruSiti && $tahunAktif) {
            JadwalPelajaran::updateOrCreate(
                [
                    'id_kelas'        => $kelas10->id,
                    'hari'            => 'Senin',
                    'id_jam'          => $jam2->id,
                    'id_tahun_ajaran' => $tahunAktif->id,
                ],
                [
                    'group_id' => Str::uuid()->toString(),
                    'id_mapel' => $bin->id,
                    'id_guru'  => $guruSiti->id,
                ]
            );
        }
    }
}
