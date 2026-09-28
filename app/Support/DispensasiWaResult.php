<?php

namespace App\Support;

/**
 * Ringkasan hasil pengiriman notifikasi WA pengajuan dispensasi ke Waka
 * Kesiswaan (satu kiriman untuk satu surat, baik individu maupun kolektif).
 *
 * Dipakai controller "WA ke Waka (Kirim Ulang)" untuk decides alert: sukses
 * HANYA bila Fonnte benar-benar mengonfirmasi kiriman; bila gagal, alasannya
 * diteruskan ke UI supaya operator tahu apa yang harus diperbaiki (token,
 * nomor tujuan, atau perangkat WhatsApp).
 */
class DispensasiWaResult
{
    /**
     * @param  int  $terkirim  Jumlah nomor yang terkonfirmasi terkirim.
     * @param  array<int, string>  $targets  Nomor tujuan yang dicoba.
     * @param  array<int, WaSendResult>  $hasil  Hasil per nomor.
     * @param  string|null  $alasanLewati  Alasan bila notifikasi tidak
     *                                     pernah dicoba sama sekali
     *                                     (mis. data testing, surat
     *                                     masuk kelas, token kosong).
     */
    public function __construct(
        public int $terkirim = 0,
        public array $targets = [],
        public array $hasil = [],
        public ?string $alasanLewati = null,
    ) {}

    /**
     * Hasil untuk kondisi "tidak ada yang dikirim karena alasan internal".
     */
    public static function dilewati(string $alasan): self
    {
        return new self(0, [], [], $alasan);
    }

    /**
     * True bila minimal satu nomor benar-benar terkonfirmasi terkirim.
     */
    public function sukses(): bool
    {
        return $this->terkirim > 0;
    }

    /**
     * Alasan gagal yang layak ditampilkan ke operator, digabung bila beberapa
     * nomor gagal dengan sebab berbeda.
     */
    public function alasanGagal(): string
    {
        if ($this->alasanLewati !== null) {
            return $this->alasanLewati;
        }

        $alasan = [];

        foreach ($this->hasil as $hasil) {
            if ($hasil instanceof WaSendResult && ! $hasil->ok && $hasil->reason !== null) {
                $alasan[$hasil->reason] = $hasil->pesan();
            }
        }

        if ($alasan === []) {
            return 'Tidak ada nomor Waka Kesiswaan yang valid untuk dikirimi (format 62xxxxxxxxx).';
        }

        return implode(' | ', array_values($alasan));
    }
}
