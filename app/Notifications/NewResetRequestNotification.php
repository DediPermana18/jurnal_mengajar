<?php

namespace App\Notifications;

use App\Models\ResetRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi database (tabel notifications) untuk Admin TU / Petugas TU saat
 * ada pengajuan reset kredensial baru dari formulir publik /lupa-sandi.
 *
 * Payload JSON (data):
 *  - title   : judul notifikasi
 *  - message : ringkasan (nama pemohon + jenis pengajuan)
 *  - url     : tujuan saat item lonceng diklik (panel pengajuan reset)
 *  - type    : jenis_pengajuan ('lupa_sandi' | 'lupa_kode_aktivasi')
 */
class NewResetRequestNotification extends Notification
{
    use Queueable;

    public function __construct(public ResetRequest $reset)
    {
    }

    /**
     * Hanya channel database — tersimpan di tabel notifications.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Payload JSON yang disimpan di kolom `data`.
     */
    public function toArray(object $notifiable): array
    {
        $user = $this->reset->user;

        return [
            'title' => 'Pengajuan Reset Kredensial',
            'message' => 'User '.($user->nama ?? 'Tanpa Nama')
                .' mengajukan reset '.$this->reset->jenis_label,
            'url' => route('admin.reset-requests.index'),
            'type' => $this->reset->jenis_pengajuan,
            'category' => 'reset',
        ];
    }
}