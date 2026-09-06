<?php

namespace Database\Seeders;

use App\Models\Ruangan;
use Illuminate\Database\Seeder;

class RuanganSeeder extends Seeder
{
    /**
     * Seed data Ruangan SMK: R-01 s/d R-06, Lab Komputer, Studio DKV.
     */
    public function run(): void
    {
        $dataRuangan = [
            ['kode_ruangan' => 'R-01', 'nama_ruangan' => 'Ruang Kelas R-01', 'lokasi' => 'Gedung A Lantai 1'],
            ['kode_ruangan' => 'R-02', 'nama_ruangan' => 'Ruang Kelas R-02', 'lokasi' => 'Gedung A Lantai 1'],
            ['kode_ruangan' => 'R-03', 'nama_ruangan' => 'Ruang Kelas R-03', 'lokasi' => 'Gedung A Lantai 2'],
            ['kode_ruangan' => 'R-04', 'nama_ruangan' => 'Ruang Kelas R-04', 'lokasi' => 'Gedung A Lantai 2'],
            ['kode_ruangan' => 'R-05', 'nama_ruangan' => 'Ruang Kelas R-05', 'lokasi' => 'Gedung B Lantai 1'],
            ['kode_ruangan' => 'R-06', 'nama_ruangan' => 'Ruang Kelas R-06', 'lokasi' => 'Gedung B Lantai 1'],
            ['kode_ruangan' => 'LAB-01', 'nama_ruangan' => 'Lab Komputer', 'lokasi' => 'Gedung B Lantai 2'],
            ['kode_ruangan' => 'STD-01', 'nama_ruangan' => 'Studio DKV', 'lokasi' => 'Gedung C Lantai 1'],
        ];

        $dibuat = 0;
        foreach ($dataRuangan as $item) {
            Ruangan::updateOrCreate(
                ['kode_ruangan' => $item['kode_ruangan']],
                ['nama_ruangan' => $item['nama_ruangan'], 'lokasi' => $item['lokasi']]
            );
            $dibuat++;
        }

        $this->command->info(" [RuanganSeeder] {$dibuat} Data Ruangan berhasil dibuat/diperbarui.");
    }
}
