<?php

namespace Database\Seeders;

use App\Models\MataPelajaran;
use Illuminate\Database\Seeder;

class MapelSeeder extends Seeder
{
    /**
     * Seed Data Mata Pelajaran (Muatan Umum, Muatan Lokal, Kejuruan DKV, TKJ, AKL)
     */
    public function run(): void
    {
        $mapels = [
            // A. MUATAN UMUM
            ['kode_mapel' => 'MPL-UM-01', 'nama_mapel' => 'Pendidikan Agama dan Budi Pekerti', 'kelompok' => 'Muatan Umum'],
            ['kode_mapel' => 'MPL-UM-02', 'nama_mapel' => 'Pendidikan Pancasila',              'kelompok' => 'Muatan Umum'],
            ['kode_mapel' => 'MPL-UM-03', 'nama_mapel' => 'Bahasa Indonesia',                  'kelompok' => 'Muatan Umum'],
            ['kode_mapel' => 'MPL-UM-04', 'nama_mapel' => 'Matematika',                         'kelompok' => 'Muatan Umum'],
            ['kode_mapel' => 'MPL-UM-05', 'nama_mapel' => 'Bahasa Inggris',                     'kelompok' => 'Muatan Umum'],
            ['kode_mapel' => 'MPL-UM-06', 'nama_mapel' => 'PJOK',                               'kelompok' => 'Muatan Umum'],
            ['kode_mapel' => 'MPL-UM-07', 'nama_mapel' => 'Sejarah',                            'kelompok' => 'Muatan Umum'],

            // B. MUATAN LOKAL
            ['kode_mapel' => 'MPL-ML-01', 'nama_mapel' => 'Bahasa Jawa',                        'kelompok' => 'Muatan Lokal'],
            ['kode_mapel' => 'MPL-ML-02', 'nama_mapel' => 'Bahasa Jepang',                      'kelompok' => 'Muatan Lokal'],

            // C. KEJURUAN DKV
            ['kode_mapel' => 'MPL-DKV-01', 'nama_mapel' => 'Desain Grafis & Vektor',            'kelompok' => 'Kejuruan'],
            ['kode_mapel' => 'MPL-DKV-02', 'nama_mapel' => 'Pemodelan 3D dan Animasi',          'kelompok' => 'Kejuruan'],
            ['kode_mapel' => 'MPL-DKV-03', 'nama_mapel' => 'Fotografi & Videografi Digital',   'kelompok' => 'Kejuruan'],

            // D. KEJURUAN TKJ
            ['kode_mapel' => 'MPL-TKJ-01', 'nama_mapel' => 'Administrasi Infrastruktur Jaringan', 'kelompok' => 'Kejuruan'],
            ['kode_mapel' => 'MPL-TKJ-02', 'nama_mapel' => 'Administrasi Server & Cloud',        'kelompok' => 'Kejuruan'],
            ['kode_mapel' => 'MPL-TKJ-03', 'nama_mapel' => 'Teknologi Layanan Jaringan',       'kelompok' => 'Kejuruan'],

            // E. KEJURUAN AKL
            ['kode_mapel' => 'MPL-AKL-01', 'nama_mapel' => 'Akuntansi Keuangan',                'kelompok' => 'Kejuruan'],
            ['kode_mapel' => 'MPL-AKL-02', 'nama_mapel' => 'Praktikum Akuntansi Perusahaan',   'kelompok' => 'Kejuruan'],
            ['kode_mapel' => 'MPL-AKL-03', 'nama_mapel' => 'Perpajakan & Digital Finance',      'kelompok' => 'Kejuruan'],
        ];

        $dibuat = 0;
        foreach ($mapels as $mapel) {
            MataPelajaran::updateOrCreate(
                ['kode_mapel' => $mapel['kode_mapel']],
                $mapel
            );
            $dibuat++;
        }

        $this->command->info(" [MapelSeeder] {$dibuat} Data Mata Pelajaran berhasil dibuat/diperbarui.");
    }
}
