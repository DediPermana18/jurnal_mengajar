<?php

namespace App\Console\Commands;

use App\Models\DispensasiSiswa;
use Illuminate\Console\Command;

class AutoExpireDispensasi extends Command
{
    protected $signature = 'dispensasi:auto-expire';

    protected $description = 'Ubah surat diterima yang melewati batas (Jam Berangkat + 1 JP) menjadi Kadaluarsa';

    public function handle(): int
    {
        $count = DispensasiSiswa::refreshAutoExpired();

        $this->info("[dispensasi:auto-expire] {$count} surat dispensasi menjadi Kadaluarsa.");

        return self::SUCCESS;
    }
}
