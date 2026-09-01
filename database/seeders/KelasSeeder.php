<?php

namespace Database\Seeders;

use App\Models\Jurusan;
use App\Models\Kelas;
use Illuminate\Database\Seeder;

class KelasSeeder extends Seeder
{
    /**
     * Seed 10 Data Kelas XI berdasarkan 10 Jurusan SMK yang tersedia.
     */
    public function run(): void
    {
        // Peta kode jurusan → nama kelas (format: "XI [KODE] 1")
        $kelasConfig = [
            'AKL'  => 'AKL 1',
            'AN'   => 'AN 1',
            'BD'   => 'BD 1',
            'DKV'  => 'DKV 1',
            'MP'   => 'MP 1',
            'PSPT' => 'PSPT 1',
            'RPL'  => 'RPL 1',
            'TKI'  => 'TKI 1',
            'TKJ'  => 'TKJ 1',
            'ULW'  => 'ULW 1',
        ];

        $dibuat  = 0;
        $dilewat = [];

        foreach ($kelasConfig as $kodeJurusan => $namaKelas) {
            $jurusan = Jurusan::where('kode_jurusan', $kodeJurusan)->first();

            if (! $jurusan) {
                $dilewat[] = $kodeJurusan;
                continue;
            }

            Kelas::updateOrCreate(
                [
                    'nama_kelas' => $namaKelas,
                    'tingkat'    => 'XI',
                    'id_jurusan' => $jurusan->id,
                ],
                [
                    'id_wali_kelas' => null,
                ]
            );

            $dibuat++;
        }

        $this->command->info('');
        $this->command->info('================================================');
        $this->command->info(" {$dibuat} Kelas XI berhasil dibuat/diperbarui.");
        $this->command->info('------------------------------------------------');
        $this->command->info(' Kelas: XI AKL 1, XI AN 1, XI BD 1, XI DKV 1,');
        $this->command->info('        XI MP 1, XI PSPT 1, XI RPL 1, XI TKI 1,');
        $this->command->info('        XI TKJ 1, XI ULW 1');
        $this->command->info(' Tingkat   : XI');
        $this->command->info(' Wali Kelas: NULL (opsional)');

        if (! empty($dilewat)) {
            $this->command->warn(' Jurusan tidak ditemukan: ' . implode(', ', $dilewat));
        }

        $this->command->info('================================================');
        $this->command->info('');
    }
}
