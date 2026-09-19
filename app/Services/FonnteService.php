<?php

namespace App\Services;

use App\Models\IzinGuru;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Gateway notifikasi WhatsApp via Fonnte (https://fonnte.com).
 *
 * Dipakai untuk mengirim pesan WA otomatis, misalnya notifikasi ke guru saat
 * pengajuan izin guru mencapai status final 'Disetujui' (approved).
 *
 * Token dikonfigurasi pada `.env` -> `FONNTE_TOKEN` lalu dibaca lewat
 * `config('services.fonnte.token')`.
 */
class FonnteService
{
    /**
     * URL endpoint API pengiriman pesan Fonnte.
     */
    protected const API_URL = 'https://api.fonnte.com/send';

    /**
     * Kirim notifikasi WhatsApp generik ke satu nomor.
     *
     * @param  string  $target   Nomor tujuan WA (idealnya format internasional,
     *                           mis. 628123456789).
     * @param  string  $message  Teks pesan yang dikirim.
     * @return bool  true bila berhasil terkirim / false bila token kosong,
     *               target kosong, atau terjadi galat.
     */
    public static function sendNotification($target, $message): bool
    {
        $token = config('services.fonnte.token');

        if (! $token || empty($target) || empty($message)) {
            return false;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => $token,
            ])->post(self::API_URL, [
                'target' => $target,
                'message' => $message,
            ]);

            if (! $response->successful()) {
                Log::warning('Fonnte WA gagal (HTTP '.$response->status().'): '.$response->body());

                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Fonnte WA Error: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Kirim notifikasi WA ke guru saat pengajuan izinnya final DISETUJUI.
     *
     * Dipanggil otomatis dari model event IzinGuru (created/updated) sehingga
     * mencakup seluruh jalur approval (Piket 1-level, Waka SDM, Kurikulum,
     * link publik Waka->Kepsek, dan Kepsek).
     */
    public static function notifyIzinDisetujui(IzinGuru $izin): bool
    {
        $guru = $izin->user;

        if (! $guru) {
            return false;
        }

        $noHp = $guru->noHpInternasional();
        if ($noHp === '') {
            return false;
        }

        $nama = $guru->nama ?: $guru->name ?: 'Guru';
        $tanggal = $izin->tanggal?->translatedFormat('d F Y') ?? (string) $izin->tanggal;

        $pesan = "Halo {$nama},\n\n"
            ."Pengajuan izin Anda untuk tanggal *{$tanggal}* dengan alasan: \"{$izin->alasan}\" "
            ."telah *DISETUJUI* oleh Waka SDM.\n\n"
            ."Status presensi harian Anda telah diperbarui secara otomatis.\n\n"
            .'_WebJournal Management System_';

        return self::sendNotification($noHp, $pesan);
    }
}