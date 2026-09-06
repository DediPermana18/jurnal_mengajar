<?php

namespace Database\Seeders;

use App\Models\JamPulang;
use App\Models\PengaturanJadwal;
use Illuminate\Database\Seeder;

class PengaturanJadwalSeeder extends Seeder
{
    /**
     * Seed Data Default Jam Pulang & Pengaturan Jadwal Sekolah
     */
    public function run(): void
    {
        // 1. Seed Jam Pulang Default (Tingkat X, XI, XII untuk Senin-Kamis & Jumat)
        $dataJamPulang = [
            ['kategori_hari' => 'Senin-Kamis', 'tingkat' => 'X',   'max_jam_ke' => 10],
            ['kategori_hari' => 'Senin-Kamis', 'tingkat' => 'XI',  'max_jam_ke' => 10],
            ['kategori_hari' => 'Senin-Kamis', 'tingkat' => 'XII', 'max_jam_ke' => 10],
            ['kategori_hari' => 'Jumat',       'tingkat' => 'X',   'max_jam_ke' => 8],
            ['kategori_hari' => 'Jumat',       'tingkat' => 'XI',  'max_jam_ke' => 8],
            ['kategori_hari' => 'Jumat',       'tingkat' => 'XII', 'max_jam_ke' => 8],
        ];

        $jpCount = 0;
        foreach ($dataJamPulang as $item) {
            JamPulang::updateOrCreate(
                ['kategori_hari' => $item['kategori_hari'], 'tingkat' => $item['tingkat']],
                ['max_jam_ke' => $item['max_jam_ke']]
            );
            $jpCount++;
        }

        // 2. Seed Pengaturan Jadwal Default
        PengaturanJadwal::updateOrCreate(
            ['id' => 1],
            [
                'senin_tanpa_upacara'    => false,
                'tanggal_eksekusi'       => null,
                'jumat_tanpa_pembiasaan' => false,
                'tanggal_eksekusi_jumat' => null,
                'no_wa_waka'             => '6281234567890',
                'izin_approval_level'    => 3,
                'no_wa_kepsek'           => '6281234567891',
            ]
        );

        $this->command->info(" [PengaturanJadwalSeeder] Jam Pulang ({$jpCount} entry) & Pengaturan Jadwal berhasil dibuat/diperbarui.");
    }
}
