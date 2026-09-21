<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\IzinGuru;
use App\Models\JadwalPiket;
use App\Models\PengaturanJadwal;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Gateway notifikasi WhatsApp via Fonnte (https://fonnte.com).
 *
 * Alur notifikasi berantai saat pengajuan izin guru:
 *   Tahap 1 : Guru submit izin     -> broadcast WA ke Guru Piket bertugas hari
 *                                     tsb (link quick-approve unik).
 *   Tahap 1b: Piket approve        -> notif ke nomor WA Waka SDM.
 *   Tahap 2 : Waka SDM approve     -> notif ke nomor WA Kepsek.
 *   Tahap 3 : Kepsek approve final -> notif ke nomor WA guru pengaju.
 *
 * Token dikonfigurasi pada `.env` -> `FONNTE_TOKEN` lalu dibaca lewat
 * `config('services.fonnte.token')`, dan dapat di-override dari UI (halaman
 * Pengaturan WA di Dashboard IT) lewat tabel `app_settings` (key
 * `fonnte_token`) — lihat `self::token()`.
 *
 * Seluruh pengiriman juga dapat dimatikan secara GLOBAL oleh Petugas IT lewat
 * switch "Status Layanan Notifikasi" (key `wa_notification_enabled` di tabel
 * `app_settings`) — saat nonaktif, `sendNotification()` membatalkan kiriman
 * tanpa request ke API dan mencatat info ke log (lihat `notificationsEnabled()`).
 *
 * Nomor tujuan Waka/Kepsek diambil dari
 * `PengaturanJadwal::noWaWakaIzin()/noWaKepsek()` (setting, fallback user).
 */
class FonnteService
{
    /**
     * URL endpoint API pengiriman pesan Fonnte.
     */
    protected const API_URL = 'https://api.fonnte.com/send';

    /**
     * Key di tabel `app_settings` untuk switch global "notifikasi WA aktif?".
     *
     * Nilai: '1' = notifikasi dikirim, '0' = seluruh pengiriman dibatalkan.
     * Dikelola Petugas IT dari halaman Pengaturan WA (route
     * POST /it/settings/wa/toggle).
     */
    public const NOTIFICATION_ENABLED_KEY = 'wa_notification_enabled';

    /**
     * Resolusi token Fonnte yang aktif:
     * 1. Token tersimpan di database (AppSetting key `fonnte_token`) — dikelola
     *    dari halaman Pengaturan WA (Dashboard IT).
     * 2. Fallback ke `.env` -> `FONNTE_TOKEN` (config/services.php).
     */
    public static function token(): string
    {
        $db = trim((string) (AppSetting::get('fonnte_token') ?? ''));

        if ($db !== '') {
            return $db;
        }

        return trim((string) (config('services.fonnte.token') ?? ''));
    }

    /**
     * Status switch global "notifikasi WA" (key `wa_notification_enabled`).
     *
     * Default AKTIF (true) bila belum pernah diatur — agar perilaku lama
     * (notifikasi selalu terkirim) tetap berlaku. Bila database bermasalah,
     * dianggap aktif (fail-safe ke perilaku normal).
     */
    public static function notificationsEnabled(): bool
    {
        try {
            return (bool) (AppSetting::get(self::NOTIFICATION_ENABLED_KEY, '1') ?? false);
        } catch (\Throwable $e) {
            return true;
        }
    }

    /**
     * Nyalakan / matikan switch global "notifikasi WA".
     *
     * Menyimpan '1'/'0' di tabel `app_settings` (global, untuk semua bucket
     * data — lihat AppSetting). Dipanggil dari controller toggle Petugas IT.
     */
    public static function setNotificationsEnabled(bool $enabled): void
    {
        AppSetting::set(self::NOTIFICATION_ENABLED_KEY, $enabled ? '1' : '0');
    }

    /**
     * Kirim notifikasi WhatsApp generik ke satu nomor.
     *
     * Graceful failover: bila token/target/pesan kosong, switch global
     * nonaktif, atau terjadi galat, method mencatat peringatan/info ke log dan
     * mengembalikan false — TANPA melempar exception (tidak pernah memicu
     * HTTP 500).
     *
     * @param  string  $target   Nomor tujuan WA (idealnya format internasional,
     *                           mis. 628123456789).
     * @param  string  $message  Teks pesan yang dikirim.
     * @return bool  true bila berhasil terkirim / false bila dilewati atau gagal.
     */
    public static function sendNotification($target, $message): bool
    {
        // Switch global: admin (Petugas IT) dapat mematikan seluruh pengiriman
        // WA tanpa melempar error — cukup dicatat ke log lalu dibatalkan.
        if (! self::notificationsEnabled()) {
            Log::info('Fonnte WA dilewati: notifikasi WA dinonaktifkan oleh admin (wa_notification_enabled=0).');

            return false;
        }

        // Hermetik di lingkungan testing: jangan pernah memanggil jaringan asli.
        if (app()->environment('testing')) {
            return false;
        }

        $token = self::token();

        if (! $token) {
            Log::warning('Fonnte WA dilewati: services.fonnte.token belum dikonfigurasi.');

            return false;
        }

        if (empty($target)) {
            Log::warning('Fonnte WA dilewati: nomor target kosong.');

            return false;
        }

        if (empty($message)) {
            Log::warning('Fonnte WA dilewati: isi pesan kosong.');

            return false;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => $token,
            ])->timeout(15)->post(self::API_URL, [
                'target' => $target,
                'message' => $message,
            ]);

            if (! $response->successful()) {
                Log::warning('Fonnte WA gagal (HTTP '.$response->status().'): '.$response->body());

                return false;
            }

            Log::info('Fonnte WA terkirim ke '.$target);

            return true;
        } catch (\Exception $e) {
            Log::error('Fonnte WA Error: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Guru Piket yang bertugas pada tanggal tertentu.
     *
     * Delegasi ke JadwalPiket::getGuruPiketHariIni() (sumber kebenaran tunggal
     * daftar piket dinamis per hari).
     */
    public static function guruPiketBertugasTanggal(Carbon $tanggal): Collection
    {
        return JadwalPiket::getGuruPiketHariIni($tanggal);
    }

    /**
     * Tahap 1 — broadcast notifikasi WA verifikasi tahap "Menunggu Piket".
     *
     * Penerima (semuanya mendapat link quick-approve unik):
     *   - SELURUH Guru Piket yang bertugas pada tanggal pengajuan (dari
     *     `jadwal_piket`/JadwalPiket::getGuruPiketHariIni()); plus
     *   - Koordinator Piket Pagi & Siang yang bertugas hari tersebut
     *     (dari kolom koordinator_pagi_user_id/koordinator_siang_user_id); plus
     *   - Waka Piket (sub-role 'waka_piket') — garda tambahan verifikasi.
     *
     * Fallback fail-safe: bila TIDAK ADA petugas piket/koordinator pada
     * tanggal tersebut, notifikasi langsung diteruskan ke Waka SDM (nomor
     * pengaturan/alur Waka) + Waka Piket, agar alur approval tidak terputus/
     * stuck menunggu piket.
     *
     * Data testing (QA/IT) tidak dikirimkan (konsisten dgn method WA lain).
     *
     * @return int  Jumlah pesan yang berhasil dikirim (0 saat dilewati/gagal).
     */
    public static function notifyGuruPiketIzinBaru(IzinGuru $izin): int
    {
        if ($izin->is_testing_data) {
            return 0;
        }

        $link = $izin->piket_approval_url;
        if (! $link) {
            return 0;
        }

        $nama = $izin->user?->nama ?? 'Guru';
        $tanggal = $izin->tanggal?->translatedFormat('d F Y') ?? (string) $izin->tanggal;

        $pesan = "Notifikasi Guru Piket: Ada pengajuan izin dari {$nama} ({$tanggal}).\n"
            ."Silakan tinjau dan setujui melalui link berikut:\n{$link}";

        $targetNumbers = collect();

        // Waka Piket selalu menjadi penerima (garda cadangan verifikasi piket).
        foreach (User::wakaPiketUsers() as $wakaPiket) {
            // Jangan kirim ke pemohon bila kebetulan dia juga Waka Piket.
            if ($wakaPiket->id === $izin->user_id) {
                continue;
            }

            $noHp = $wakaPiket->noHpInternasional();
            if ($noHp !== '') {
                $targetNumbers->push($noHp);
            }
        }

        // Guru Piket bertugas + Koordinator Piket (Pagi/Siang) hari tersebut.
        // Koordinator diambil dari kolom koordinator_* agar nomornya ikut
        // menerima notifikasi approval walau baris jadwalnya tanpa user_id.
        $petugasVerifikasi = JadwalPiket::getGuruPiketHariIni($izin->tanggal)
            ->merge(JadwalPiket::koordinatorPiketBertugasTanggal($izin->tanggal))
            ->unique('id')
            ->values();

        if ($petugasVerifikasi->isNotEmpty()) {
            foreach ($petugasVerifikasi as $petugas) {
                // Jangan kirim ke guru pengaju bila kebetulan dia juga piket hari itu.
                if ($petugas->id === $izin->user_id) {
                    continue;
                }

                $noHp = $petugas->noHpInternasional();
                if ($noHp !== '') {
                    $targetNumbers->push($noHp);
                }
            }
        } else {
            // Fallback fail-safe: tanpa petugas piket/koordinator, teruskan ke
            // Waka SDM agar izin tidak menunggu verifikasi yang tidak pernah ada.
            $noWaWaka = PengaturanJadwal::noWaWakaIzin();
            if ($noWaWaka !== '') {
                $targetNumbers->push($noWaWaka);
            }
        }

        $terkirim = 0;

        foreach ($targetNumbers->unique() as $target) {
            if (self::sendNotification($target, $pesan)) {
                $terkirim++;
            }
        }

        return $terkirim;
    }

    /**
     * Tahap 1b — notifikasi ke nomor WA Waka SDM saat Guru Piket menyetujui
     * izin (status berpindah dari Pending Piket -> Menunggu Waka SDM).
     *
     * Dipicu otomatis oleh model event IzinGuru::updated, baik dari quick-approve
     * maupun approval Piket via dashboard.
     */
    public static function notifyWakaMenungguApproval(IzinGuru $izin): bool
    {
        if ($izin->is_testing_data) {
            return false;
        }

        $nama = $izin->user?->nama ?? 'Guru';
        $tanggal = $izin->tanggal?->translatedFormat('d F Y') ?? (string) $izin->tanggal;

        $pesan = "Notifikasi Waka SDM: Pengajuan izin dari {$nama} pada {$tanggal} "
            .'telah diverifikasi oleh Guru Piket. Mohon berikan persetujuan di dashboard WebJournal.';

        return self::sendNotification(PengaturanJadwal::noWaWakaIzin(), $pesan);
    }

    /**
     * Tahap 2 — notifikasi ke nomor WA Kepsek saat izin masuk tahap menunggu
     * persetujuan akhir Kepala Sekolah (setelah Waka SDM / Piket menyetujui).
     *
     * @param  string  $disetujuiOleh  Pihak yang menyetujui sebelumnya
     *                                 ('Waka SDM' pada alur 3 level,
     *                                 'Guru Piket' pada alur 2 level).
     */
    public static function notifyKepsekMenungguApproval(IzinGuru $izin, string $disetujuiOleh = 'Waka SDM'): bool
    {
        if ($izin->is_testing_data) {
            return false;
        }

        $nama = $izin->user?->nama ?? 'Guru';

        $pesan = "Notifikasi Kepsek: Pengajuan izin dari {$nama} telah disetujui oleh {$disetujuiOleh}. "
            .'Membutuhkan persetujuan akhir Anda di dashboard WebJournal.';

        return self::sendNotification(PengaturanJadwal::noWaKepsek(), $pesan);
    }

    /**
     * Tahap 3 — notifikasi final ke guru saat pengajuan izinnya resmi DISETUJUI.
     *
     * Rantai approver dibangun dinamis dari kolom approved_by_waka /
     * approved_by_kepsek / approved_by_piket, mis. "Waka SDM & Kepsek"
     * pada alur 3 level.
     */
    public static function notifyIzinDisetujui(IzinGuru $izin): bool
    {
        if ($izin->is_testing_data) {
            return false;
        }

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

        $approvers = [];
        if ($izin->approved_by_waka) {
            $approvers[] = 'Waka SDM';
        }
        if ($izin->approved_by_kepsek) {
            $approvers[] = 'Kepsek';
        }
        if (empty($approvers) && $izin->approved_by_piket) {
            $approvers[] = 'Guru Piket';
        }
        $chain = implode(' & ', $approvers) ?: 'pihak terkait';

        $pesan = "Halo {$nama},\n\n"
            ."Pengajuan izin Anda untuk tanggal *{$tanggal}* dengan alasan: \"{$izin->alasan}\" "
            ."telah *SELESAI DISETUJUI* oleh {$chain}.\n\n"
            ."Status presensi harian Anda telah diperbarui secara otomatis.\n\n"
            .'_WebJournal Management System_';

        return self::sendNotification($noHp, $pesan);
    }

    /**
     * Cek status koneksi gateway Fonnte via endpoint /device.
     *
     * Tidak pernah melempar exception; bila token kosong / jaringan gagal,
     * dikembalikan status disconnected beserta pesan. Di lingkungan testing
     * status dianggap terhubung tanpa memanggil jaringan asli.
     *
     * @return array{connected: bool, details: array, quota: ?string, message: string}
     */
    public static function checkConnection(): array
    {
        $token = self::token();

        if ($token === '') {
            return [
                'connected' => false,
                'details' => [],
                'quota' => null,
                'message' => 'Token Fonnte belum dikonfigurasi (database / .env).',
            ];
        }

        if (app()->environment('testing')) {
            return [
                'connected' => true,
                'details' => ['mode' => 'testing'],
                'quota' => null,
                'message' => 'Mode testing aktif — status tidak dipertukarkan dengan server Fonnte.',
            ];
        }

        try {
            // Endpoint /device Fonnte wajib diakses via POST + header Authorization.
            $response = Http::withHeaders(['Authorization' => $token])
                ->timeout(10)
                ->post('https://api.fonnte.com/device');

            if (! $response->successful()) {
                return [
                    'connected' => false,
                    'details' => [],
                    'quota' => null,
                    'message' => 'HTTP '.$response->status().' — '.mb_substr(trim($response->body()), 0, 200),
                ];
            }

            $body = $response->json() ?? [];
            $device = is_array(data_get($body, 'device')) ? data_get($body, 'device') : $body;

            // Status perangkat: prioritas key root (device_status / status),
            // fallback key di dalam objek device.
            $statusRaw = strtolower((string) (
                data_get($body, 'device_status')
                ?? data_get($body, 'status')
                ?? data_get($device, 'device_status')
                ?? data_get($device, 'status')
                ?? ''
            ));

            // Nilai yang menandakan perangkat terkoneksi ke server Fonnte
            // (termasuk 'connect' / 'connected' sesuai dokumentasi API).
            $connected = in_array($statusRaw, [
                'connect', 'connected', 'ongoing', 'active', 'online', 'running', 'on', 'true', '1', '',
            ], true);

            $details = [];
            foreach (['device_status', 'name', 'phone', 'status', 'last_update', 'battery', 'platform'] as $key) {
                if (($val = (string) (data_get($body, $key) ?? data_get($device, $key) ?? '')) !== '') {
                    $details[$key] = $val;
                }
            }
            if ($details === []) {
                $details['api'] = 'device endpoint OK';
            }

            $quota = null;
            foreach (['sisa_kuota', 'daily_limit', 'quota', 'limit', 'saldo', 'credits', 'remaining'] as $key) {
                $val = data_get($body, $key, data_get($device, $key, null));
                if ($val !== null && $val !== '') {
                    $quota = (string) $val;
                    break;
                }
            }

            return [
                'connected' => $connected,
                'details' => $details,
                'quota' => $quota,
                'message' => $connected
                    ? 'Gateway Fonnte terhubung.'
                    : 'Koneksi ke API diterima, namun status perangkat WhatsApp tidak aktif (device_status: '.$statusRaw.').',
            ];
        } catch (\Exception $e) {
            return [
                'connected' => false,
                'details' => [],
                'quota' => null,
                'message' => 'Gagal menghubungi server Fonnte: '.$e->getMessage(),
            ];
        }
    }
}