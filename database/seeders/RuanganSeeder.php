<?php

namespace Database\Seeders;

use App\Models\Ruangan;
use Illuminate\Database\Seeder;

class RuanganSeeder extends Seeder
{
    public function run(): void
    {
        $ruangan = [
            ['kode_ruangan' => 'R-101', 'nama_ruangan' => 'Kelas 101', 'lokasi' => 'Gedung A Lantai 1'],
            ['kode_ruangan' => 'R-102', 'nama_ruangan' => 'Kelas 102', 'lokasi' => 'Gedung A Lantai 1'],
            ['kode_ruangan' => 'R-103', 'nama_ruangan' => 'Kelas 103', 'lokasi' => 'Gedung A Lantai 1'],
            ['kode_ruangan' => 'R-201', 'nama_ruangan' => 'Kelas 201', 'lokasi' => 'Gedung A Lantai 2'],
            ['kode_ruangan' => 'R-202', 'nama_ruangan' => 'Kelas 202', 'lokasi' => 'Gedung A Lantai 2'],
            ['kode_ruangan' => 'R-LAB-KOM', 'nama_ruangan' => 'Lab Komputer', 'lokasi' => 'Gedung B Lantai 1'],
            ['kode_ruangan' => 'R-LAB-IPA', 'nama_ruangan' => 'Lab IPA', 'lokasi' => 'Gedung B Lantai 2'],
            ['kode_ruangan' => 'R-GURU', 'nama_ruangan' => 'Ruang Guru', 'lokasi' => 'Gedung A Lantai 3'],
            ['kode_ruangan' => 'R-TU', 'nama_ruangan' => 'Ruang Tata Usaha', 'lokasi' => 'Gedung A Lantai 1'],
        ];

        foreach ($ruangan as $item) {
            Ruangan::updateOrCreate(
                ['kode_ruangan' => $item['kode_ruangan']],
                $item
            );
        }
    }
}
