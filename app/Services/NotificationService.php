<?php

namespace App\Services;

use App\Models\IzinGuru;
use App\Models\User;
use App\Notifications\IzinBaruNotification;
use App\Notifications\SiswaDispenNotification;
use App\Notifications\SiswaTerlambatNotification;
use App\Notifications\StatusIzinNotification;
use Illuminate\Support\Collection;

/**
 * Pusat pembuatan notifikasi. Memusatkan logika "siapa menerima notifikasi apa"
 * sehingga controller yang memicu event cukup memanggil satu method statis.
 */
class NotificationService
{
    /**
     * Guru Piket yang bertugas hari ini (dari jadwal_piket). Fallback ke
     * seluruh guru yang terdaftar piket bila tidak ada yang bertugas hari ini.
     */
    public static function guruPiketRecipients(): Collection
    {
        $hari = now()->translatedFormat('l');

        $hariMap = [
            'Monday'    => 'Senin',
            'Tuesday'   => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday'  => 'Kamis',
            'Friday'    => 'Jumat',
        ];
        $hariId = $hariMap[$hari] ?? null;

        $users = User::whereHas('jadwalPiket')
            ->when($hariId, fn ($q) => $q->whereHas('jadwalPiket', fn ($j) => $j->where('hari', $hariId)))
            ->get();

        return $users->isNotEmpty() ? $users : User::whereHas('jadwalPiket')->get();
    }

    /**
     * Waka Kurikulum / SDM (role admin + sub_role waka*).
     */
    public static function wakaRecipients(): Collection
    {
        return User::where('role', 'admin')
            ->where('sub_role', 'like', 'waka%')
            ->get();
    }

    /**
     * Kepala Sekolah (role admin + sub_role kepala_sekolah / kepsek).
     */
    public static function kepsekRecipients(): Collection
    {
        return User::where('role', 'admin')
            ->whereIn('sub_role', ['kepala_sekolah', 'kepsek', 'kepala_sekolah2'])
            ->get();
    }

    /**
     * Notifikasi ke guru pemohon izin ketika status izinnya berubah.
     */
    public static function izinStatusChanged(IzinGuru $izin): void
    {
        $guru = $izin->user;
        if (!$guru) {
            return;
        }

        $guru->notify(new StatusIzinNotification([
            'category' => 'izin_status',
            'title'    => 'Perubahan Status Izin',
            'message'  => "Izin Anda tanggal {$izin->tanggal?->translatedFormat('d M Y')} kini berstatus: {$izin->status_label}.",
            'status'   => $izin->status,
            'url'      => route('guru.izin.index'),
        ]));
    }

    /**
     * Notifikasi "pengajuan izin baru" ke Guru Piket, Waka, dan Kepsek.
     */
    public static function izinBaruDiajukan(IzinGuru $izin): void
    {
        $nama  = $izin->user?->nama ?? 'Seorang guru';
        $tanggal = $izin->tanggal?->translatedFormat('d M Y') ?? '-';

        $payload = [
            'category' => 'izin_baru',
            'title'    => 'Pengajuan Izin Baru',
            'message'  => "{$nama} mengajukan izin pada {$tanggal} dan butuh persetujuan Anda.",
            'url'      => route('kurikulum.izin.index'),
        ];

        $recipients = collect()
            ->merge(static::guruPiketRecipients())
            ->merge(static::wakaRecipients())
            ->merge(static::kepsekRecipients())
            ->unique('id');

        foreach ($recipients as $recipient) {
            if ($recipient->id !== $izin->user_id) {
                $recipient->notify(new IzinBaruNotification($payload));
            }
        }
    }

    /**
     * Notifikasi laporan siswa terlambat dari Satpam ke Guru Piket bertugas
     * hari ini + Wali Kelas (diambil dari tabel penerima_catatan_terlambat).
     */
    public static function siswaTerlambat($penerima): void
    {
        $payload = [
            'category' => 'siswa_terlambat',
            'title'    => 'Siswa Terlambat',
            'message'  => "Siswa {$penerima->siswa?->nama} tercatat terlambat pukul {$penerima->jam_masuk} oleh Satpam.",
            'url'      => route('satpam.dashboard', ['tab' => 'terlambat']),
        ];

        foreach ($penerima->penerima as $p) {
            if ($p->user_id) {
                optional(User::find($p->user_id))->notify(new SiswaTerlambatNotification($payload));
            }
        }
    }

    /**
     * Notifikasi dispensasi siswa dari Satpam ke Guru Piket / Guru Mapel /
     * Wali Kelas.
     */
    public static function siswaDispen($dispen): void
    {
        $jenis = $dispen->jenis_label ?? 'Dispensasi';
        $payload = [
            'category' => 'siswa_dispen',
            'title'    => 'Siswa Dispensasi',
            'message'  => "Siswa {$dispen->siswa?->nama} mendapatkan dispensasi ({$jenis}) pada {$dispen->jam_ke_label} oleh Satpam.",
            'url'      => route('satpam.dashboard', ['tab' => 'dispensasi']),
        ];

        $recipients = collect();

        if ($dispen->id_guru) {
            $recipients->push($dispen->id_guru);
        }
        if ($dispen->id_guru_piket) {
            $recipients->push($dispen->id_guru_piket);
        }
        if ($waliKelasId = $dispen->siswa?->kelas?->id_wali_kelas) {
            $recipients->push($waliKelasId);
        }

        foreach ($recipients->unique()->filter() as $id) {
            optional(User::find($id))->notify(new SiswaDispenNotification($payload));
        }
    }
}
