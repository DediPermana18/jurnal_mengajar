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

// Notifikasi WA Guru — Agenda Pagi: ringkasan jadwal hari ini (setiap hari 06:30 WIB)
Schedule::command('guru:notif-jadwal --type=pagi')
    ->dailyAt('06:30')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();

// Notifikasi WA Guru — KBM: H-10 menit, H-0 jam masuk, H-5 pengingat jurnal
// Berjalan setiap menit selama jam operasional sekolah (05:00–19:00 WIB).
// Pengecekan jendela waktu dilakukan di dalam command itu sendiri.
Schedule::command('guru:notif-jadwal --type=kbm')
    ->everyMinute()
    ->timezone('Asia/Jakarta')
    ->between('05:00', '19:00')
    ->withoutOverlapping();


