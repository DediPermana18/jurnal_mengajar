<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Otomatisasi Notifikasi Keamanan (Bot Chat)
    |--------------------------------------------------------------------------
    |
    | Saat sebuah login baru berhasil dilakukan, pemilik akun diberi tahu
    | melalui bot chat sehingga penyusupan dapat segera ditindak lanjuti.
    |
    |   WhatsApp (Fonnte) : dipakai otomatis bila pemilik akun memiliki nomor
    |                       WA (users.no_hp) — memakai gateway FonnteService
    |                       yang sudah ada (token di .env -> FONNTE_TOKEN, atau
    |                       via halaman Pengaturan WA di Dashboard IT).
    |
    |   Telegram (opsional): isi token bot + chat id di bawah (atau .env)
    |                       untuk mengirim duplikat pesan via Telegram API.
    |
    */

    'notifications' => [
        'telegram_bot_token' => env('SECURITY_TELEGRAM_BOT_TOKEN', ''),
        'telegram_chat_id' => env('SECURITY_TELEGRAM_CHAT_ID', ''),
    ],

];