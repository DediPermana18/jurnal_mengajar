<?php

namespace App\Observers;

use App\Models\DispensasiSiswa;
use App\Services\DispensasiWaService;

/**
 * Observer DispensasiSiswa —_notifikasi WhatsApp otomatis ke Waka Kesiswaan_
 * begitu Guru Piket (atau Satpam) menerbitkan surat dispensasi baru.
 *
 * Catatan: method ini berjalan sinkron di dalam event `created`, sehingga
 * alur "simpan surat" tidak pernah gagal hanya karena gateway WA bermasalah —
 * DispensasiWaService hanya mencatat log lalu mengembalikan 0 bila dilewati.
 *
 * Baris anak dari pengajuan KOLEKTIF (rombongan) sengaja dilewati: satu
 * rombongan = satu surat = satu pesan, dikirim oleh
 * DispensasiController::storeKolektif() setelah transaksi selesai.
 */
class DispensasiSiswaObserver
{
    public function created(DispensasiSiswa $dispensasi): void
    {
        if ($dispensasi->dispensasi_kolektif_id !== null) {
            return;
        }

        DispensasiWaService::notifyWakaKesiswaan($dispensasi, 'dispensasi baru');
    }
}
