<?php

namespace Database\Seeders;

use App\Models\JadwalPiket;
use App\Models\User;
use Illuminate\Database\Seeder;

class JadwalPiketSeeder extends Seeder
{
    /**
     * Seed Data Assign Jadwal Piket Harian Guru (Senin - Jumat)
     */
    public function run(): void
    {
        $piketAssignment = [
            'Senin' => ['budi.santoso', 'siti.rahmawati'],
            'Selasa' => ['agus.setiawan', 'ahmad.fauzi'],
            'Rabu' => ['eko.prasetyo', 'rina.wulandari'],
            'Kamis' => ['hendra.wijaya', 'retno.utami'],
            'Jumat' => ['bambang.hermanto', 'dewi.lestari'],
        ];

        $total = 0;
        foreach ($piketAssignment as $hari => $usernames) {
            foreach ($usernames as $username) {
                $user = User::where('username', $username)->first();
                if ($user) {
                    JadwalPiket::updateOrCreate(
                        ['user_id' => $user->id, 'hari' => $hari]
                    );
                    $total++;
                }
            }
        }

        $this->command->info(" [JadwalPiketSeeder] {$total} Penugasan Piket Guru (Senin-Jumat) berhasil dibuat/diperbarui.");
    }
}
