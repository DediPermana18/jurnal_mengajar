<?php

namespace Database\Seeders;

use App\Models\Ruangan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PengurusRuanganSeeder extends Seeder
{
    /**
     * Seed Penanggung Jawab Ruangan (Lab Komputer & Studio DKV)
     */
    public function run(): void
    {
        $labKomputer = Ruangan::where('kode_ruangan', 'LAB-01')->first();
        $studioDkv = Ruangan::where('kode_ruangan', 'STD-01')->first();

        $guruTkj = User::where('username', 'ahmad.fauzi')->first();
        $guruDkv = User::where('username', 'eko.prasetyo')->first();

        $assignments = [];

        if ($labKomputer && $guruTkj) {
            $assignments[] = ['ruangan_id' => $labKomputer->id, 'user_id' => $guruTkj->id];
        }

        if ($studioDkv && $guruDkv) {
            $assignments[] = ['ruangan_id' => $studioDkv->id, 'user_id' => $guruDkv->id];
        }

        $dibuat = 0;
        foreach ($assignments as $a) {
            DB::table('pengurus_ruangan')->updateOrInsert(
                ['ruangan_id' => $a['ruangan_id'], 'user_id' => $a['user_id']],
                ['created_at' => now(), 'updated_at' => now()]
            );
            $dibuat++;
        }

        $this->command->info(" [PengurusRuanganSeeder] {$dibuat} Penanggung Jawab Ruangan (Lab & Studio) berhasil dibuat/diperbarui.");
    }
}
