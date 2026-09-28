<?php

namespace App\Services;

use App\Models\SecurityDevice;
use App\Models\SecurityLog;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Notifikasi bot chat (WhatsApp / Telegram) saat ada login baru.
 *
 *  - WhatsApp: dikirimkan ke nomor WA pemilik akun (users.no_hp) lewat
 *    gateway Fonnte yang sudah dipakai aplikasi (FonnteService::sendNotification).
 *  - Telegram: dikirimkan bila token bot + chat id dikonfigurasi
 *    (config/security.php atau .env SECURITY_TELEGRAM_*).
 *
 * Seluruh jalur bersifat "best-effort": kegagalan jaringan/config hanya
 * dicatat ke log — tidak pernah memicu HTTP 500. Di lingkungan testing,
 * FonnteService membatalkan kiriman tanpa memanggil jaringan asli.
 */
class SecurityBotService
{
    public function notifyNewDeviceLogin(User $user, SecurityLog $log): void
    {
        $fingerprint = (string) $log->device_fingerprint;
        $fpLabel = $fingerprint !== '' ? 'FP #'.substr($fingerprint, 0, 8) : 'tidak tersedia';
        $unknownLabel = $log->is_unknown_device
            ? "\n⚠️ *Perangkat Baru / Tak Dikenal* — sidik jari ini belum pernah tercatat untuk akun Anda."
            : '';

        $savedDevice = $fingerprint !== ''
            ? SecurityDevice::where('user_id', $user->id)
                ->where('fingerprint', $fingerprint)
                ->first()
            : null;

        $browserInfo = $log->device_name ?: '-';
        $customName = trim((string) $savedDevice?->name);

        if ($customName !== '') {
            $deviceDisplay = $browserInfo !== '-' ? "{$customName} ({$browserInfo})" : $customName;
        } else {
            $deviceDisplay = $browserInfo;
        }

        $waktu = $log->login_at?->translatedFormat('d F Y H:i:s') ?? $log->login_at?->toDateTimeString() ?? '-';
        $username = $user->username ?: '-';
        $nama = $user->nama ?: $user->name;
        $ip = $log->ip_address ?: '-';
        $browser = $log->user_agent ?: '-';

        $baris = [
            "🔐 *Peringatan Keamanan — Login Baru*\n",
            "Akun : {$username}",
            "Nama : {$nama}",
            "Waktu : {$waktu}",
            "IP : {$ip}",
            "Perangkat : {$deviceDisplay}",
            "Sidik Jari : {$fpLabel}",
            "Browser : {$browser}",
        ];

        if ($log->is_unknown_device) {
            $baris[] = "\n⚠️ *Perangkat Baru / Tak Dikenal* — sidik jari ini belum pernah tercatat untuk akun Anda.";
        }

        $baris[] = "\nJika ini bukan Anda, segera buka menu *Perangkat & Keamanan* untuk memutuskan sesi asing dan ganti password.";

        $pesan = implode("\n", $baris);

        // ---------- 1) WhatsApp (Fonnte) ----------
        $noHp = $user->noHpInternasional();
        $waStatus = 'no-number';
        if ($noHp !== '') {
            $waStatus = FonnteService::sendNotification($noHp, $pesan) ? 'sent' : 'skipped-failed';
        }

        // ---------- 2) Telegram (opsional) ----------
        $telegramToken = trim((string) config('security.notifications.telegram_bot_token'));
        $telegramChat = trim((string) config('security.notifications.telegram_chat_id'));
        $telegramStatus = 'not-configured';
        if ($telegramToken !== '' && $telegramChat !== '') {
            try {
                Http::timeout(8)->post(
                    "https://api.telegram.org/bot{$telegramToken}/sendMessage",
                    [
                        'chat_id' => $telegramChat,
                        'text' => $pesan,
                        'parse_mode' => 'Markdown',
                    ]
                )->throw();

                $telegramStatus = 'sent';
            } catch (\Throwable $e) {
                $telegramStatus = 'failed';
                Log::warning('security-bot:telegram-gagal', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('security-bot:new-device-login', [
            'user_id' => $user->id,
            'security_log_id' => $log->id,
            'ip' => $log->ip_address,
            'device_name' => $log->device_name,
            'whatsapp' => $waStatus,
            'telegram' => $telegramStatus,
        ]);
    }
}