<?php

namespace App\Console\Commands;

use App\Models\DispensasiSiswa;
use Illuminate\Console\Command;

class AutoMangkirDispensasi extends Command
{
    protected $signature = 'dispensasi:auto-mangkir';

    protected $description = 'Ubah surat yang siswa-nya belum kembali melewati batas (Jam Kembali + 1 JP) menjadi Mangkir/Bolos dan absensinya menjadi Alfa';

    public function handle(): int
    {
        $count = DispensasiSiswa::refreshAutoMangkir();

        $this->info("[dispensasi:auto-mangkir] {$count} dispensasi ditandai Mangkir (presensi diubah menjadi Alfa).");

        return self::SUCCESS;
    }
}
