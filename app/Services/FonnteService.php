<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\IzinGuru;
use App\Models\JadwalPiket;
use App\Models\PengaturanJadwal;
use App\Models\User;
use App\Support\WaSendResult;
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
 *
 * ================== KONTRAK RESPONS FONNTE (penting) ==================
 * Endpoint POST /send membalas **HTTP 200** untuk KEAGALAN BISNIS, sehingga
 * `response->successful()` TIDAK cukup untuk menyatakan "terkirim". Contoh
 * nyata hasil uji langsung ke api.fonnte.com:
 *
 *     HTTP 200  {"reason":"invalid token","status":false}
 *
 * Bentuk success: {"detail":"success! message in queue","id":[...],
 * "process":"pending","requestid":...,"status":true,"target":[...]}
 *
 * Bentuk failure (selalu `status:false`, `reason` berisi penyebab):
 * "token invalid", "devices must belong to an account", "input invalid",
 * "target invalid", "insufficient quota", dan sebagainya.
 *
 * Karena itu `send()` mengembalikan {@see WaSendResult} yang membaca field
 * `status` tersebut, sedangkan `sendNotification()` (bool) tetap dipertahankan
 * untuk pemanggil lama.
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
     * Sanitasi nomor tujuan WA ke format internasional Indonesia (62xxxxxxxxx).
     *
     * Sumber kebenaran tunggal normalisasi nomor untuk SELURUH pengiriman WA
     * (dispensasi, izin guru, jadwal guru, security bot). Menangani:
     *   - "+62 812-3456-7890" / "0812 3456 7890" -> "6281234567890"
     *   - "8123456789" (tanpa country code)       -> "628123456789"
     *   - "0812..." (nol di depan)                -> "62812..."
     *
     * Semua karakter non-digit (spasi, tanda hubung, kurung, titik) dibuang.
     * Mengembalikan string kosong bila tidak ada digit sama sekali.
     */
    public static function normalizeTarget(?string $no): string
    {
        $no = preg_replace('/[^0-9]/', '', (string) $no);

        if ($no === '') {
            return '';
        }

        // 0812… / 021… -> 62812… / 6221…
        if (str_starts_with($no, '0')) {
            return '62'.substr($no, 1);
        }

        // 812… (nomor lokal tanpa awalan 0 & tanpa country code) -> 62812…
        if (str_starts_with($no, '8') && strlen($no) >= 9) {
            return '62'.$no;
        }

        return $no;
    }

    /**
     * Nomor dianggap valid bila: hanya digit, berawalan country code 62, dan
     * panjang nomor lokal 8-13 digit. Mencegah request sia-sia ke Fonnte yang
     * akan ditolak dengan "target invalid".
     */
    public static function isValidTarget(?string $no): bool
    {
        $no = (string) $no;

        if ($no === '' || ! ctype_digit($no) || ! str_starts_with($no, '62')) {
            return false;
        }

        $lokal = substr($no, 2);

        return strlen($lokal) >= 8
            && strlen($lokal) <= 13
            && ! preg_match('/^0+$/', $lokal);
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
        return self::send((string) $target, (string) $message)->ok;
    }

    /**
     * Kirim pesan WA ke satu nomor dan kembalikan hasil BERLENGKAP (alasan
     * kegagalan ikut dibawa), sehingga UI bisa menampilkan error yang jujur.
     *
     * PENTING: Fonnte membalas HTTP 200 walau gagal secara bisnis
     * ({"status":false,"reason":"..."}), jadi `ok` hanya true bila body JSON
     * mengonfirmasi `status` true. Lihat {@see self::interpretResponse()}.
     *
     * Tidak pernah melempar exception.
     */
    public static function send($target, $message): WaSendResult
    {
        // Konversi literal '\n' menjadi newline murni (\n) bila ada
        $message = str_replace('\n', "\n", $message);

        // Paket gratis Fonnte menolak URL / karakter spesial / teks panjang.
        // Sanitasi dilakukan di layer shared service agar seluruh fitur (dispensasi,
        // izin guru, dsb.) konsisten dan tidak mengalami "invalid message request".
        $messageClean = self::bersihkanPesan($message);
        if ($messageClean !== $message) {
            Log::info('Fonnte WA: pesan disanitasi untuk paket gratis', [
                'target' => $target,
                'original_length' => strlen($message),
                'cleaned_length' => strlen($messageClean),
            ]);
        }

        // Switch global: admin (Petugas IT) dapat mematikan seluruh pengiriman
        // WA tanpa melempar error — cukup dicatat ke log lalu dibatalkan.
        if (! self::notificationsEnabled()) {
            $alasan = 'layanan notifikasi WA sedang dinonaktifkan oleh admin (Status Layanan Notifikasi pada Dashboard IT).';

            Log::info('Fonnte WA dilewati: notifikasi WA dinonaktifkan oleh admin (wa_notification_enabled=0).');

            return WaSendResult::gagal($target, $alasan);
        }

        // Hermetik di lingkungan testing: jangan pernah memanggil jaringan asli.
        if (app()->environment('testing')) {
            return WaSendResult::gagal($target, 'environment testing — pengiriman nyata diblokir.');
        }

        $token = self::token();

        if (! $token) {
            Log::warning('Fonnte WA dilewati: services.fonnte.token belum dikonfigurasi.');

            return WaSendResult::gagal($target, 'token Fonnte belum dikonfigurasi (Pengaturan WA / .env FONNTE_TOKEN).');
        }

        // Sanitasi + validasi nomor tujuan sebelum membuang request ke Fonnte.
        $target = self::normalizeTarget($target);

        if ($target === '') {
            Log::warning('Fonnte WA dilewati: nomor target kosong.');

            return WaSendResult::gagal($target, 'nomor tujuan kosong.');
        }

        if (! self::isValidTarget($target)) {
            Log::warning('Fonnte WA dilewati: nomor target tidak valid (harus format 62xxxxxxxxx).', [
                'target' => $target,
            ]);

            return WaSendResult::gagal($target, 'nomor tujuan tidak valid untuk Indonesia (harus 62xxxxxxxxx).');
        }

        if (trim($messageClean) === '') {
            Log::warning('Fonnte WA dilewati: isi pesan kosong setelah sanitasi.');

            return WaSendResult::gagal($target, 'isi pesan kosong.');
        }

        // Log payload yang akan dikirim ke API Fonnte (untuk debugging & audit).
        Log::info('Payload Fonnte:', [
            'target' => $target,
            'message' => $messageClean,
        ]);

        try {
            $response = Http::withHeaders([
                'Authorization' => $token,
            ])->timeout(15)->post(self::API_URL, [
                'target' => $target,
                'message' => $messageClean,
            ]);
        } catch (\Exception $e) {
            Log::error('Fonnte WA Error: '.$e->getMessage());

            return WaSendResult::gagal($target, 'gagal menghubungi server Fonnte: '.$e->getMessage());
        }

        $result = self::interpretResponse($target, $response);

        // Log response mentah dari Fonnte (termasuk body JSON) untuk debugging kegagalan.
        if (! $result->ok) {
            Log::error('Response Fonnte Gagal:', $result->response ?? ['reason' => $result->reason, 'ok' => false]);
        }

        return $result;
    }

    /**
     * Terjemahkan respons Fonnte menjadi WaSendResult.
     *
     * Fonnte membalas HTTP 200 untuk KEGAGALAN BISNIS; field `status` pada body
     * JSON adalah penentu sebenarnya:
     *   sukses : {"detail":"success! message in queue","status":true,...}
     *   gagal  : {"reason":"invalid token","status":false}
     *
     * Body kosong / JSON rusak pada HTTP 200 dianggap GAGAL (bukan sukses),
     * agar tidak ada alert "berhasil" padahal pesan tidak terkirim.
     */
    protected static function interpretResponse(string $target, $response): WaSendResult
    {
        $http = $response->status();
        $raw = (string) $response->body();
        $body = $response->json();
        $body = is_array($body) ? $body : null;

        // Log respons mentah — satu-satunya cara membedakan sukses vs gagal
        // karena Fonnte selalu membalas HTTP 200.
        Log::info('Fonnte WA respons API', [
            'target' => $target,
            'http' => $http,
            'body' => $body ?? $raw,
        ]);

        if (! $response->successful()) {
            $alasan = 'Fonnte menolak permintaan (HTTP '.$http.'): '.self::reasonFrom($body, $raw);

            Log::warning('Fonnte WA gagal: '.$alasan, [
                'target' => $target,
                'http' => $http,
            ]);

            return WaSendResult::gagal($target, $alasan, $http, $body, $raw);
        }

        // HTTP 200 belum tentu terkirim: Fonnte menjawab status=false (dengan
        // `reason`) untuk kegagalan bisnis, dan body kosong tidak membuktikan
        // apa pun. Keduanya dihitung GAGAL.
        if (! self::apiStatusTrue($body)) {
            $alasan = self::reasonFrom($body, $raw);

            Log::warning('Fonnte WA GAGAL meski HTTP '.$http.': '.$alasan, [
                'target' => $target,
                'http' => $http,
                'body' => $body,
            ]);

            return WaSendResult::gagal($target, $alasan, $http, $body, $raw);
        }

        Log::info('Fonnte WA terkirim ke '.$target, [
            'http' => $http,
            'requestid' => $body['requestid'] ?? null,
        ]);

        return WaSendResult::sukses($target, $http, $body, $raw);
    }

    /**
     * True HANYA bila body Fonnte secara eksplisit mengonfirmasi status=true.
     *
     * Body kosong / JSON rusak / tanpa field status diperlakukan sebagai
     * "belum terkonfirmasi" (bukan sukses) demi keamanan notifikasi.
     */
    protected static function apiStatusTrue(?array $body): bool
    {
        if (! $body) {
            return false;
        }

        // Fonnte tidak konsisten kapitalisasi kunci ("Status" vs "status").
        $status = $body['status'] ?? $body['Status'] ?? null;

        return $status === true
            || $status === 1
            || $status === '1'
            || $status === 'true';
    }

    /**
     * Ambil alasan kegagalan dari body Fonnte, dengan fallback ke body mentah.
     */
    protected static function reasonFrom(?array $body, string $raw): string
    {
        if ($body) {
            foreach (['reason', 'message', 'detail', 'error'] as $key) {
                $value = $body[$key] ?? null;

                if (is_string($value) && trim($value) !== '') {
                    return trim($value);
                }
            }
        }

        $raw = trim($raw);

        if ($raw === '') {
            return 'Fonnte membalas tanpa body — hasil pengiriman tidak dapat dipastikan.';
        }

        return mb_strlen($raw) > 300 ? mb_substr($raw, 0, 300).'…' : $raw;
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

            // Status perangkat: `device_status` (mis. "connect") adalah penentu
            // sebenarnya; key `status` di root bernilai numerik (1) dan hanya
            // penanda umum, jadi dibaca setelah `device_status`.
            $statusRaw = strtolower((string) (
                data_get($body, 'device_status')
                ?? data_get($device, 'device_status')
                ?? data_get($body, 'status')
                ?? data_get($device, 'status')
                ?? ''
            ));

            // Nilai yang menandakan perangkat terkoneksi ke server Fonnte
            // (termasuk 'connect' / 'connected' sesuai dokumentasi API).
            $connected = in_array($statusRaw, [
                'connect', 'connected', 'ongoing', 'active', 'online', 'running', 'on', 'true', '1', '',
            ], true);

            $details = [];
            foreach (['device_status', 'name', 'phone', 'status', 'package', 'expired', 'last_update', 'battery', 'platform'] as $key) {
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

            $pesan = $connected
                ? 'Gateway Fonnte terhubung.'
                : 'Koneksi ke API diterima, namun status perangkat WhatsApp tidak aktif (device_status: '.$statusRaw.').';

            if ($quota !== null) {
                $pesan .= ' Sisa kuota: '.$quota.' pesan.';
            }

            // Token yang sudah kedaluwarsa ditolak Fonnte dengan
            // {"status":false,"reason":"token invalid"} pada endpoint /send.
            if (isset($details['expired'])) {
                $pesan .= ' Masa berlaku token: '.$details['expired'].'.';
            }

            return [
                'connected' => $connected,
                'details' => $details,
                'quota' => $quota,
                'message' => $pesan,
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

    /**
     * Bersihkan isi pesan dari URL, link, domain, dan karakter khusus
     * yang diblokir Fonnte pada paket free.
     *
     * Melakukan pengurangan:
     * - Menghapus http:// dan https:// beserta seluruh URL yang mengandungnya
     * - Menghapus tautan domain (mis. .com, .id, .co)
     * - Menghapus tanda kurawal ganda atau berlebih
     * - Menghapus karakter khusus yang bisa memicu error "invalid message request"
     * pada free package Fonnte.
     *
     * @param  string  $pesan  Isi pesan mentah dari pengguna
     * @return string          Pesan yang sudah dibersihkan
     */
    public static function bersihkanPesan(string $pesan): string
    {
        // 0. Konversi string literal '\n' menjadi newline murni terlebih dahulu
        $hasil = str_replace('\n', "\n", $pesan);

        // 1. Hapus http:// dan https:// dan seluruh URL yang mengandungnya
        $hasil = preg_replace('/https?:\/\/\S+/', '', $hasil);

        // 2. Hapus domain saja (mis. .com, .id, .co, .net) jika berdiri sendiri
        //    (hindari menghapus domain yang bagian dari teks bukan URL)
        $hasil = preg_replace('/\b([a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)(com|id|co|net|org|gov|edu)(?:\/\S*)?\b/i', '', $hasil);

        // 3. Hapus spasi/tab berlebih pada baris yang sama (TIDAK menghapus \n ganti baris)
        $hasil = preg_replace('/[ \t]+/', ' ', $hasil);

        // 4. Batasi newline beruntun maksimal 2 (\n\n) agar tidak terlalu renggang
        $hasil = preg_replace('/\n{3,}/', "\n\n", $hasil);

        // 5. Hapus tanda kurawal ganda dan plus ganda
        $hasil = preg_replace('/\{+/', '{', $hasil);
        $hasil = preg_replace('/\++/', '+', $hasil);

        // 6. Potong string jika terlalu panjang (Fonnte punya limit)
        if (strlen($hasil) > 1600) {
            $hasil = mb_substr($hasil, 0, 1600) . '…';
        }

        return trim($hasil);
    }
}
