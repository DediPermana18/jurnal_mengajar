<?php

namespace Database\Seeders;

use App\Models\JamPelajaran;
use Illuminate\Database\Seeder;

class JamPelajaranSeeder extends Seeder
{
    /**
     * Seed Master slot Jam Pelajaran 1 s/d 10 (Senin-Kamis & Jumat).
     */
    public function run(): void
    {
        // JP 1 s/d 10 (Senin-Kamis), durasi 45 menit
        $jamSeninKamis = [
            ['jam_ke' => 1,  'jam_mulai' => '07:00:00', 'jam_selesai' => '07:45:00', 'jenis' => 'kbm'],
            ['jam_ke' => 2,  'jam_mulai' => '07:45:00', 'jam_selesai' => '08:30:00', 'jenis' => 'kbm'],
            ['jam_ke' => 3,  'jam_mulai' => '08:30:00', 'jam_selesai' => '09:15:00', 'jenis' => 'kbm'],
            ['jam_ke' => 4,  'jam_mulai' => '09:15:00', 'jam_selesai' => '10:00:00', 'jenis' => 'kbm'],
            ['jam_ke' => 5,  'jam_mulai' => '10:00:00', 'jam_selesai' => '10:45:00', 'jenis' => 'kbm'],
            ['jam_ke' => null, 'jam_mulai' => '10:45:00', 'jam_selesai' => '11:00:00', 'jenis' => 'istirahat'],
            ['jam_ke' => 6,  'jam_mulai' => '11:00:00', 'jam_selesai' => '11:45:00', 'jenis' => 'kbm'],
            ['jam_ke' => 7,  'jam_mulai' => '11:45:00', 'jam_selesai' => '12:30:00', 'jenis' => 'kbm'],
            ['jam_ke' => 8,  'jam_mulai' => '13:00:00', 'jam_selesai' => '13:45:00', 'jenis' => 'kbm'],
            ['jam_ke' => 9,  'jam_mulai' => '13:45:00', 'jam_selesai' => '14:30:00', 'jenis' => 'kbm'],
            ['jam_ke' => 10, 'jam_mulai' => '14:30:00', 'jam_selesai' => '15:15:00', 'jenis' => 'kbm'],
        ];

        // JP 1 s/d 10 (Jumat), durasi 40 menit
        $jamJumat = [
            ['jam_ke' => 1,  'jam_mulai' => '07:00:00', 'jam_selesai' => '07:40:00', 'jenis' => 'kbm'],
            ['jam_ke' => 2,  'jam_mulai' => '07:40:00', 'jam_selesai' => '08:20:00', 'jenis' => 'kbm'],
            ['jam_ke' => 3,  'jam_mulai' => '08:20:00', 'jam_selesai' => '09:00:00', 'jenis' => 'kbm'],
            ['jam_ke' => 4,  'jam_mulai' => '09:15:00', 'jam_selesai' => '09:55:00', 'jenis' => 'kbm'],
            ['jam_ke' => 5,  'jam_mulai' => '09:55:00', 'jam_selesai' => '10:35:00', 'jenis' => 'kbm'],
            ['jam_ke' => 6,  'jam_mulai' => '10:35:00', 'jam_selesai' => '11:15:00', 'jenis' => 'kbm'],
            ['jam_ke' => 7,  'jam_mulai' => '11:15:00', 'jam_selesai' => '11:55:00', 'jenis' => 'kbm'],
            ['jam_ke' => 8,  'jam_mulai' => '13:00:00', 'jam_selesai' => '13:40:00', 'jenis' => 'kbm'],
            ['jam_ke' => 9,  'jam_mulai' => '13:40:00', 'jam_selesai' => '14:20:00', 'jenis' => 'kbm'],
            ['jam_ke' => 10, 'jam_mulai' => '14:20:00', 'jam_selesai' => '15:00:00', 'jenis' => 'kbm'],
        ];

        $dibuat = 0;
        foreach ([['Senin-Kamis', $jamSeninKamis], ['Jumat', $jamJumat]] as [$kategori, $slots]) {
            foreach ($slots as $slot) {
                JamPelajaran::updateOrCreate(
                    ['kategori_hari' => $kategori, 'jam_ke' => $slot['jam_ke']],
                    [
                        'jam_mulai' => $slot['jam_mulai'],
                        'jam_selesai' => $slot['jam_selesai'],
                        'jenis' => $slot['jenis'],
                    ]
                );
                $dibuat++;
            }
        }

        $this->command->info(" [JamPelajaranSeeder] {$dibuat} Slot Jam Pelajaran (JP 1-10) berhasil dibuat/diperbarui.");
    }
}
