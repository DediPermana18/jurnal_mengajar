<?php

namespace Database\Seeders;

use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;

class TahunAjaranSeeder extends Seeder
{
    /**
     * Seed Data Master Tahun Ajaran
     */
    public function run(): void
    {
        $dataTahun = [
            ['tahun_ajaran' => '2025/2026', 'semester' => 'Ganjil', 'is_active' => true],
            ['tahun_ajaran' => '2025/2026', 'semester' => 'Genap',  'is_active' => false],
            ['tahun_ajaran' => '2026/2027', 'semester' => 'Ganjil', 'is_active' => false],
        ];

        $dibuat = 0;
        foreach ($dataTahun as $item) {
            TahunAjaran::updateOrCreate(
                ['tahun_ajaran' => $item['tahun_ajaran'], 'semester' => $item['semester']],
                ['is_active' => $item['is_active']]
            );
            $dibuat++;
        }

        $this->command->info(" [TahunAjaranSeeder] {$dibuat} Data Tahun Ajaran berhasil dibuat/diperbarui.");
    }
}
