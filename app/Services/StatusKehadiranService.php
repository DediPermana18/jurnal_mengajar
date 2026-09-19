<?php

namespace App\Services;

use App\Models\IzinGuru;
use App\Models\StatusKehadiranGuru;
use Illuminate\Support\Facades\Auth;

/**
 * Sinkronisasi status kehadiran guru dari pengajuan izin guru (alur approval).
 *
 * Rute singkat:
 * - Final approval (status izin = 'disetujui') -> catat status kehadiran
 *   (Sakit / Izin / Dinas Luar, sesuai kategori izin) pada tanggal terkait.
 * - Rollback / pembatalan dari 'disetujui'  -> kembalikan ke 'Hadir'
 *   (hapus jejak otomatis yang dibuat dari izin tsb).
 * - Halaman Status Kehadiran Guru memanggil sinkronkanTanggal() agar data
 *   tetap realtime & idempotent (tidak menimpa override manual Guru Piket).
 */
class StatusKehadiranService
{
    /**
     * Buat/perbarui record status kehadiran guru saat izin DISETUJUI final.
     */
    public static function otomatiskanDariIzinDisetujui(IzinGuru $izin): ?StatusKehadiranGuru
    {
        if ($izin->status !== IzinGuru::STATUS_DISETUJUI || empty($izin->user_id) || empty($izin->tanggal)) {
            return null;
        }

        $status = match ($izin->kategori_izin) {
            'sakit' => StatusKehadiranGuru::STATUS_SAKIT,
            'dinas_luar' => StatusKehadiranGuru::STATUS_DINAS_LUAR,
            default => StatusKehadiranGuru::STATUS_IZIN,
        };

        return StatusKehadiranGuru::updateOrCreate(
            [
                'user_id' => $izin->user_id,
                'tanggal' => $izin->tanggal->toDateString(),
            ],
            [
                'status' => $status,
                'keterangan' => $izin->alasan,
                'updated_by' => Auth::id()
                    ?? $izin->approved_by_kepsek
                    ?? $izin->approved_by_waka
                    ?? $izin->approved_by_piket,
            ]
        );
    }

    /**
     * Kembalikan status kehadiran guru ke 'Hadir' saat izin di-rollback /
     * dibatalkan dari status 'disetujui'.
     *
     * Hanya menghapus jejak otomatis yang dibuat dari izin tsb
     * (keterangan = alasan izin), sehingga override manual Guru Piket
     * tidak ikut terhapus.
     */
    public static function kembalikanKeHadir(IzinGuru $izin): void
    {
        if (empty($izin->user_id) || empty($izin->tanggal)) {
            return;
        }

        StatusKehadiranGuru::where('user_id', $izin->user_id)
            ->whereDate('tanggal', $izin->tanggal->toDateString())
            ->where('keterangan', $izin->alasan)
            ->delete();
    }

    /**
     * Sinkronkan status kehadiran untuk satu tanggal dari seluruh izin yang
     * sudah disetujui. Idempotent & aman: tidak menimpa record yang sudah ada
     * (mis. override manual Guru Piket).
     */
    public static function sinkronkanTanggal(string $tanggal): void
    {
        IzinGuru::whereDate('tanggal', $tanggal)
            ->where('status', IzinGuru::STATUS_DISETUJUI)
            ->orderBy('id')
            ->each(function (IzinGuru $izin) {
                $sudahAda = StatusKehadiranGuru::where('user_id', $izin->user_id)
                    ->whereDate('tanggal', $izin->tanggal->toDateString())
                    ->exists();

                if (! $sudahAda) {
                    self::otomatiskanDariIzinDisetujui($izin);
                }
            });
    }
}