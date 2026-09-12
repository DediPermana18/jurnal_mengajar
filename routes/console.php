<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-Expired: ubah surat dispen aktif yang melewati Jam Berangkat + 1 JP
// menjadi "Kadaluarsa" (backup jika scheduler cron tidak berjalan: cek ulang
// juga dilakukan saat portal Satpam / Guru Piket dibuka).
Schedule::command('dispensasi:auto-expire')
    ->everyMinute()
    ->withoutOverlapping();

// Auto-Mangkir: surat keluar yang siswa-nya belum kembali melewati Jam Kembali
// + 1 JP ditandai "Mangkir/Bolos" dan presensi JP terkait diubah menjadi 'A'
// (backup jika scheduler tidak berjalan: cek ulang saat portal dibuka).
Schedule::command('dispensasi:auto-mangkir')
    ->everyMinute()
    ->withoutOverlapping();
