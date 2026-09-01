<?php

namespace Database\Seeders;

use App\Models\MataPelajaran;
use Illuminate\Database\Seeder;

class MapelSeeder extends Seeder
{
    /**
     * Seed Data Mata Pelajaran (Muatan Umum, Muatan Lokal, Kejuruan)
     */
    public function run(): void
    {
        $mapels = [
            // A. MUATAN UMUM (Wajib Semua Jurusan)
            [
                'kode_mapel' => 'MPL-UM-01',
                'nama_mapel' => 'Pendidikan Agama dan Budi Pekerti',
                'kelompok'   => 'Muatan Umum',
            ],
            [
                'kode_mapel' => 'MPL-UM-02',
                'nama_mapel' => 'Pendidikan Pancasila',
                'kelompok'   => 'Muatan Umum',
            ],
            [
                'kode_mapel' => 'MPL-UM-03',
                'nama_mapel' => 'Bahasa Indonesia',
                'kelompok'   => 'Muatan Umum',
            ],
            [
                'kode_mapel' => 'MPL-UM-04',
                'nama_mapel' => 'Matematika',
                'kelompok'   => 'Muatan Umum',
            ],
            [
                'kode_mapel' => 'MPL-UM-05',
                'nama_mapel' => 'Bahasa Inggris',
                'kelompok'   => 'Muatan Umum',
            ],
            [
                'kode_mapel' => 'MPL-UM-06',
                'nama_mapel' => 'Pendidikan Jasmani Olahraga dan Kesehatan (PJOK)',
                'kelompok'   => 'Muatan Umum',
            ],
            [
                'kode_mapel' => 'MPL-UM-07',
                'nama_mapel' => 'Sejarah',
                'kelompok'   => 'Muatan Umum',
            ],

            // B. MUATAN LOKAL (Mulok)
            [
                'kode_mapel' => 'MPL-ML-01',
                'nama_mapel' => 'Bahasa Jawa',
                'kelompok'   => 'Muatan Lokal',
            ],
            [
                'kode_mapel' => 'MPL-ML-02',
                'nama_mapel' => 'Bahasa Jepang',
                'kelompok'   => 'Muatan Lokal',
            ],

            // C. KEJURUAN / PRODUKTIF (Spesifik SMK)
            [
                'kode_mapel' => 'MPL-KJ-01',
                'nama_mapel' => 'Pemrograman Web dan Perangkat Bergerak',
                'kelompok'   => 'Kejuruan',
            ],
            [
                'kode_mapel' => 'MPL-KJ-02',
                'nama_mapel' => 'Pemodelan 3D dan Animasi',
                'kelompok'   => 'Kejuruan',
            ],
            [
                'kode_mapel' => 'MPL-KJ-03',
                'nama_mapel' => 'Akuntansi Keuangan',
                'kelompok'   => 'Kejuruan',
            ],
            [
                'kode_mapel' => 'MPL-KJ-04',
                'nama_mapel' => 'Digital Marketing & E-Commerce',
                'kelompok'   => 'Kejuruan',
            ],
            [
                'kode_mapel' => 'MPL-KJ-05',
                'nama_mapel' => 'Desain Grafis & Vektor',
                'kelompok'   => 'Kejuruan',
            ],
            [
                'kode_mapel' => 'MPL-KJ-06',
                'nama_mapel' => 'Otomatisasi Tata Kelola Perkantoran',
                'kelompok'   => 'Kejuruan',
            ],
            [
                'kode_mapel' => 'MPL-KJ-07',
                'nama_mapel' => 'Teknik Pengolahan Audio Video',
                'kelompok'   => 'Kejuruan',
            ],
            [
                'kode_mapel' => 'MPL-KJ-08',
                'nama_mapel' => 'Operasi Teknik Kimia',
                'kelompok'   => 'Kejuruan',
            ],
            [
                'kode_mapel' => 'MPL-KJ-09',
                'nama_mapel' => 'Administrasi Infrastruktur Jaringan',
                'kelompok'   => 'Kejuruan',
            ],
            [
                'kode_mapel' => 'MPL-KJ-10',
                'nama_mapel' => 'Perencanaan dan Layanan Perjalanan Wisata',
                'kelompok'   => 'Kejuruan',
            ],
        ];

        $dibuat = 0;
        foreach ($mapels as $mapel) {
            MataPelajaran::updateOrCreate(
                ['kode_mapel' => $mapel['kode_mapel']],
                $mapel
            );
            $dibuat++;
        }

        $this->command->info('');
        $this->command->info('================================================');
        $this->command->info(" {$dibuat} Data Mata Pelajaran berhasil dibuat/diperbarui.");
        $this->command->info('------------------------------------------------');
        $this->command->info(' - 7 Mapel Muatan Umum (MPL-UM-01 s/d MPL-UM-07)');
        $this->command->info(' - 2 Mapel Muatan Lokal (MPL-ML-01 s/d MPL-ML-02)');
        $this->command->info(' - 10 Mapel Kejuruan (MPL-KJ-01 s/d MPL-KJ-10)');
        $this->command->info('================================================');
        $this->command->info('');
    }
}
