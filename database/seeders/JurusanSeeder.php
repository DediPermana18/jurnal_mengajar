<?php

namespace Database\Seeders;

use App\Models\Jurusan;
use Illuminate\Database\Seeder;

class JurusanSeeder extends Seeder
{
    /**
     * Seed data Jurusan SMK: DKV, TKJ, dan AKL.
     */
    public function run(): void
    {
        $dataJurusan = [
            ['kode_jurusan' => 'DKV', 'nama_jurusan' => 'Desain Komunikasi Visual'],
            ['kode_jurusan' => 'TKJ', 'nama_jurusan' => 'Teknik Komputer & Jaringan'],
            ['kode_jurusan' => 'AKL', 'nama_jurusan' => 'Akuntansi & Keuangan Lembaga'],
        ];

        $dibuat = 0;
        foreach ($dataJurusan as $item) {
            Jurusan::updateOrCreate(
                ['kode_jurusan' => $item['kode_jurusan']],
                ['nama_jurusan' => $item['nama_jurusan']]
            );
            $dibuat++;
        }

        $this->command->info(" [JurusanSeeder] {$dibuat} Data Jurusan berhasil dibuat/diperbarui.");
    }
}
