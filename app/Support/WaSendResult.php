<?php

namespace App\Support;

/**
 * Hasil satu percobaan kirim pesan WhatsApp via Fonnte.
 *
 * PENTING — kontrak Fonnte: endpoint /send membalas **HTTP 200** bahkan
 * ketika pengiriman gagal secara bisnis. Contoh nyata (diuji langsung ke
 * api.fonnte.com):
 *
 *     HTTP 200  {"reason":"invalid token","status":false}
 *
 * Jadi `response->successful()` saja TIDAK cukup untuk menyatakan kiriman
 * berhasil; field `status` pada body JSON adalah satu-satunya pembuktian.
 * Dokumentasi Fonnte: "Failed run response always return status : false",
 * dengan `reason` berupa "token invalid", "target invalid", "insufficient
 * quota", "devices must belong to an account", dll.
 *
 * Objek ini dipakai agar pemanggil (controller) bisa menampilkan alert error
 * yang JUJUR berisi alasan dari Fonnte, alih-alih flash "berhasil" palsu.
 */
class WaSendResult
{
    /**
     * @param  bool  $ok  true hanya bila Fonnte mengonfirmasi
     *                    status=true (pesan masuk antrean).
     * @param  string|null  $target  Nomor tujuan (sudah ternormalisasi).
     * @param  int|null  $httpStatus  Kode HTTP respons Fonnte.
     * @param  string|null  $reason  Alasan gagal (dari `reason` Fonnte atau
     *                               kondisi internal: token kosong, target
     *                               tidak valid, layanan dimatikan, dll).
     * @param  array|null  $response  Body JSON respons Fonnte ( mentah).
     * @param  string|null  $rawBody  Body respons apa adanya (fallback).
     */
    public function __construct(
        public bool $ok = false,
        public ?string $target = null,
        public ?int $httpStatus = null,
        public ?string $reason = null,
        public ?array $response = null,
        public ?string $rawBody = null,
    ) {}

    /**
     * Hasil sukses (Fonnte mengonfirmasi status = true).
     */
    public static function sukses(?string $target = null, ?int $httpStatus = null, ?array $response = null, ?string $rawBody = null): self
    {
        return new self(true, $target, $httpStatus, null, $response, $rawBody);
    }

    /**
     * Hasil gagal, lengkap dengan alasan yang bisa ditampilkan ke pengguna.
     */
    public static function gagal(?string $target, string $reason, ?int $httpStatus = null, ?array $response = null, ?string $rawBody = null): self
    {
        return new self(false, $target, $httpStatus, $reason, $response, $rawBody);
    }

    /**
     * Penjelasan singkat siap tampil di alert UI, memakai bahasa aksi operator.
     *
     * Alasan Fonnte yang umum dipetakan ke petunjuk perbaikan yang konkret.
     * Alasan mentah dari API tetap disertakan (dalam kurung) agar operator /
     * developer bisa menelusuri kegagalan yang tidak dikenali.
     */
    public function pesan(): string
    {
        $reason = trim((string) $this->reason);

        if ($reason === '') {
            return 'Fonnte menolak pengiriman tanpa menyertakan alasan.';
        }

        // Tidak pernah sampai ke Fonnte (diblokir pemeriksaan lokal: token /
        // nomor / pesan kosong, atau layanan dimatikan) — reason sudah
        // action-oriented, cukup ditampilkan apa adanya.
        if ($this->httpStatus === null) {
            return $reason;
        }

        $r = mb_strtolower($reason);

        $pesan = match (true) {
            str_contains($r, 'token invalid'), str_contains($r, 'invalid token') => 'Token Fonnte tidak valid. Perbarui token pada Dashboard IT → Pengaturan WA.',

            str_contains($r, 'insufficient quota') => 'Kuota Fonnte habis, pesan tidak dikirim. Top up di dashboard Fonnte.',

            str_contains($r, 'devices must belong to an account') => 'Token Fonnte/device tidak terdaftar pada akun Fonnte ini.',

            str_contains($r, 'target invalid') => 'Nomor tujuan ditolak Fonnte (format 62xxxxxxxxx tidak valid).',

            str_contains($r, 'disconnected'), str_contains($r, 'device') => 'Perangkat WhatsApp Fonnte sedang terputus, pesan tidak dapat dikirim.',

            str_contains($r, 'json format invalid'), str_contains($r, 'input invalid') => 'Parameter pengiriman ditolak Fonnte (input tidak valid).',

            default => 'Fonnte menolak: '.$reason,
        };

        // Alasan yang sudah persis ditampilkan tidak perlu diulang.
        return $pesan === $reason || str_contains($pesan, $reason)
            ? $pesan
            : $pesan.' (Fonnte: '.$reason.')';
    }
}
