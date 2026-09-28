<?php

namespace App\Services;

use App\Models\DispensasiKolektif;
use App\Models\DispensasiSiswa;
use App\Models\PengaturanJadwal;
use App\Models\User;
use App\Support\DispensasiWaResult;
use App\Support\WaSendResult;
use Illuminate\Support\Facades\Log;

/**
 * Notifikasi WhatsApp (Fonnte) pengajuan dispensasi siswa ke Waka Kesiswaan.
 *
 * Dipanggil OTOMATIS begitu surat dispensasi dibuat:
 *  - surat individu   -> DispensasiSiswaObserver (event `created` model).
 *  - surat kolektif   -> DispensasiController::storeKolektif() (satu pesan untuk
 *    satu rombongan; baris anak sengaja dilewati observer agar tidak spam).
 *
 * Tombol "WA ke Waka" pada halaman Guru Piket memakai method yang sama
 * (`notifyWakaKesiswaan`) sebagai fungsi KIRIM ULANG (re-send) manual.
 *
 * Berisi tiga hal utama:
 *  1. Resolusi nomor HP Waka Kesiswaan dari database (user sub_role
 *     'waka_kesiswaan', fallback pengaturan `no_wa_waka`) + sanitasi ke format
 *     country code Indonesia (62...) beserta validasi.
 *  2. Pembentukan direct link approval (route publik `dispen.approval.show`)
 *     yang berbasis domain aplikasi `config('app.url')`.
 *  3. Penyusunan pesan WhatsApp (rincian siswa + link approval / TTD digital).
 *
 * Semua kegagalan bersifat fail-safe: tidak pernah melempar exception, hanya
 * mencatat info/warning ke log sehingga alur pembuatan surat tetap normal.
 */
class DispensasiWaService
{
    /**
     * Jumlah nama siswa yang ditampilkan pada pesan untuk pengajuan kolektif
     * (sisanya diringkas sebagai "+N lainnya").
     */
    protected const MAX_NAMA_KOLEKTIF = 3;

    /**
     * Jumlah kelas yang ditampilkan pada pesan pengajuan kolektif.
     */
    protected const MAX_KELAS_KOLEKTIF = 3;

    /**
     * Disanitasi nomor HP ke format internasional Indonesia (62xxxxxxxxx).
     *
     * Menangani input "0812..." (nol di depan), "+62 812-3456-7890" (spasi /
     * tanda hubung), maupun "8123456789" (tanpa country code).
     *
     * Delegasi ke {@see FonnteService::normalizeTarget()} sebagai sumber
     * kebenaran tunggal normalisasi nomor WA aplikasi (perubahan aturan di satu
     * tempat berlaku untuk seluruh fitur notifikasi).
     */
    public static function normalizeNoWa($no): string
    {
        return FonnteService::normalizeTarget((string) $no);
    }

    /**
     * Nomor dianggap valid bila: hanya digit, berawalan country code 62, dan
     * panjang nomor lokal 8-13 digit (rentang nomor seluler Indonesia).
     *
     * Delegasi ke {@see FonnteService::isValidTarget()} — validasi yang sama
     * dipakai sebelum request dikirim ke Fonnte, sehingga tidak ada nomor yang
     * lolos di sini tapi ditolak API sebagai "target invalid".
     */
    public static function isValidNoWa(?string $no): bool
    {
        return FonnteService::isValidTarget((string) $no);
    }

    /**
     * Nomor WA Waka Kesiswaan yang aktif & valid, siap dikirim.
     *
     * Sumber (sesuai urutan):
     *  1. User berjabatan Waka Kesiswaan (role 'admin' + sub_role
     *     'waka_kesiswaan', aktif) — sumber utama sesuai role/jabatan.
     *  2. Fallback: kolom `no_wa_waka` pada Pengaturan Jadwal (dikelola dari
     *     halaman Pengaturan Izin Guru / Profil), bila tidak ada akun Waka
     *     Kesiswaan yang memakai nomor valid.
     *
     * Mengembalikan array nomor unik; array kosong bila tidak ada nomor yang
     * bisa dipakai (pemanggil wajib memberi peringatan ke pengguna/log).
     *
     * @return array<int, string>
     */
    public static function wakaKesiswaanNumbers(): array
    {
        try {
            $numbers = [];

            foreach (User::wakaKesiswaanList() as $waka) {
                $no = static::normalizeNoWa($waka->no_hp);

                if (static::isValidNoWa($no)) {
                    $numbers[] =$no;
                }
            }

            if ($numbers === []) {$no = static::normalizeNoWa(PengaturanJadwal::getSetting()->no_wa_waka);

                if (static::isValidNoWa($no)) {
                    $numbers[] =$no;
                }
            }

            return array_values(array_unique($numbers));
        } catch (\Throwable $e) {
            Log::error('Gagal resolving nomor WA Waka Kesiswaan untuk notifikasi dispensasi: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Direct link approval (TTD digital Waka Kesiswaan) untuk satu surat
     * dispensasi — individu maupun kolektif.
     *
     * Link dibangun dari route publik `dispen.approval.show` yang di-prefix
     * base URL aplikasi (`config('app.url')`) agar tetap menghasilkan domain
     * produksi meskipun surat dibuat dari request lokal/CLI.
     */
    public static function approvalUrl(DispensasiSiswa|DispensasiKolektif $surat): ?string
    {
        $token = trim((string) ($surat->approval_token ?? ''));

        if ($token === '') {
            return null;
        }

        $base = rtrim((string) config('app.url'), '/');

        return $base.route('dispen.approval.show',$token, false);
    }

    /**
     * Susun pesan WhatsApp pengajuan approval dispensasi (Format teks polos tanpa emoji/Markdown untuk Fonnte Free Package).
     *
     * Mengembalikan null bila link approval tidak bisa dibuat (token kosong).
     */
    public static function buildMessage(DispensasiSiswa|DispensasiKolektif $surat): ?string
    {
        $link = static::approvalUrl($surat);

        if (! $link) {
            Log::warning('Notifikasi WA dispensasi dilewati: approval_token kosong, link approval tidak dapat dibuat.', [
                'dispensasi_id' => $surat->getKey(),
                'kolektif' => $surat instanceof DispensasiKolektif,
            ]);

            return null;
        }

        $detail = static::detailSurat($surat);

        $baris = [
            'PENGAJUAN APPROVAL DISPENSASI SISWA',
            '',
            'Yth. Waka Kesiswaan,',
            'Terdapat pengajuan surat dispensasi baru yang membutuhkan persetujuan/tanda tangan Anda:',
            '',
            '- Nama Siswa: '.$detail['nama'],
            '- Kelas: '.$detail['kelas'],
            '- Waktu KBM: '.$detail['jam'],
            '- Tanggal: '.$detail['tanggal'],
            '- Alasan: '.$detail['alasan'],
        ];

        if ($detail['catatan'] !== null) {
            $baris[] = '- No. Surat: '.$detail['catatan'];
        }

        $baris[] = '';$baris[] = 'Silakan login ke WebJournal System untuk meninjau dan melakukan persetujuan secara langsung.';
        $baris[] = '';$baris[] = 'Pesan otomatis dari WebJournal System';

        return implode("\n", $baris);
    }

    /**
     * Kirim notifikasi WA pengajuan dispensasi ke Waka Kesiswaan.
     *
     * Mengembalikan {@see DispensasiWaResult} berisi jumlah pesan yang
     * BENAR-BAREN terkonfirmasi terkirim oleh Fonnte beserta alasan
     * kegagalannya, sehingga pemanggil (observer maupun tombol kirim ulang)
     * dapat memberi umpan balik yang jujur ke pengguna.
     *
     * Sukses dihitung HANYA bila Fonnte menjawab `status: true` pada body JSON;
     * Fonnte membalas HTTP 200 walau gagal (mis. token invalid, perangkat
     * terputus, target tidak valid, kuota habis) sehingga hasil HTTP tidak
     * cukup — lihat {@see FonnteService::send()}.
     *
     * @param  string  $konteks  Keterangan asal pemanggilan (mis. 'pembuatan',
     *                           'kirim ulang') — hanya dipakai untuk log.
     */
    public static function notifyWakaKesiswaan(
        DispensasiSiswa|DispensasiKolektif $surat,
        string $konteks = 'pembuatan'
    ): DispensasiWaResult {
        $ref = [
            'konteks' => $konteks,
            'dispensasi_id' => $surat->getKey(),
            'kolektif' => $surat instanceof DispensasiKolektif,
        ];

        // Data testing (QA/IT) tidak pernah menghasilkan kiriman WA sungguhan.
        if ((bool) $surat->is_testing_data) {
            Log::info('Notifikasi WA dispensasi dilewati: record bertanda data testing (is_testing_data=1).', $ref);

            return DispensasiWaResult::dilewati('Surat ini adalah data testing (QA/IT) sehingga notifikasi WA tidak dikirim.');
        }

        // Surat "Masuk Kelas" (izin telat / kembali KBM) tidak melalui alur TTD
        // Waka Kesiswaan — kotak TTD Waka tidak ada pada surat masuk kelas.
        if ((string) $surat->tipe_dispen === DispensasiSiswa::TIPE_MASUK) {
            Log::info('Notifikasi WA dispensasi dilewati: surat Masuk Kelas tidak memerlukan TTD Waka Kesiswaan.', $ref);

            return DispensasiWaResult::dilewati('Surat Masuk Kelas tidak memerlukan persetujuan/tanda tangan Waka Kesiswaan.');
        }

        $pesan = static::buildMessage($surat);

        if ($pesan === null) {
            return DispensasiWaResult::dilewati('Link approval tidak dapat dibuat (approval_token kosong).');
        }

        $targets = static::wakaKesiswaanNumbers();

        if ($targets === []) {$alasan = 'Nomor HP Waka Kesiswaan belum diisi atau tidak valid (harus format 62xxxxxxxxx). '
                .'Isi pada Profil user Waka Kesiswaan atau kolom no_wa_waka (Pengaturan Izin Guru).';

            Log::warning('Notifikasi WA dispensasi ke Waka Kesiswaan dilewati: '.$alasan,$ref);

            return DispensasiWaResult::dilewati($alasan);
        }

        $terkirim = 0;
        $hasil = [];

        foreach ($targets as$target) {
            // `send()` (bukan `sendNotification()`) dipakai agar alasan kegagalan
            // dari Fonnte ikut tertangkap untuk ditampilkan ke operator.
            $hasil[$target] = FonnteService::send($target,$pesan);

            if ($hasil[$target]->ok) {$terkirim++;
            }
        }

        Log::info('Notifikasi WA dispensasi ke Waka Kesiswaan diproses: '.$terkirim.' dari '.count($targets).' nomor terkirim.',$ref + [
            'targets' => $targets,
            'gagal' => array_keys(array_filter($hasil, fn (WaSendResult $r) => !$r->ok)),
        ]);

        return new DispensasiWaResult($terkirim, $targets,$hasil);
    }

    /**
     * Rincian surat untuk isi pesan WA: nama siswa, kelas, jam KBM, tanggal,
     * alasan, dan nomor surat (baris tambahan).
     *
     * Untuk pengajuan kolektif, nama siswa & kelas diringkas (maks. 3 nama)
     * agar pesan tetap ringkas di layar WhatsApp.
     *
     * @return array{nama: string, kelas: string, jam: string, tanggal: string, alasan: string, catatan: ?string}
     */
    protected static function detailSurat(DispensasiSiswa|DispensasiKolektif $surat): array
    {
        $items =$surat instanceof DispensasiKolektif
            ? $surat->siswaItems()->with('siswa.kelas')->get()
            : collect([$surat->loadMissing('siswa.kelas')]);

        $nama =$items
            ->map(fn (DispensasiSiswa $item) => trim((string) ($item->siswa?->nama ?? '')))
            ->filter()
            ->values();

        $namaSiswa = $nama->count() > static::MAX_NAMA_KOLEKTIF &&$surat instanceof DispensasiKolektif
            ? $nama->take(static::MAX_NAMA_KOLEKTIF)->implode(', ')
                .' (+'.($nama->count() - static::MAX_NAMA_KOLEKTIF).' lainnya)'
            : ($nama->isEmpty() ? '-' :$nama->implode(', '));

        $kelas =$items
            ->map(fn (DispensasiSiswa $item) => trim((string) ($item->siswa?->kelas?->nama_lengkap ?? '')))
            ->filter()
            ->unique()
            ->values();

        $labelKelas =$kelas->count() > static::MAX_KELAS_KOLEKTIF
            ? $kelas->take(static::MAX_KELAS_KOLEKTIF)->implode(', ')
                .' (+'.($kelas->count() - static::MAX_KELAS_KOLEKTIF).' lainnya)'
            : ($kelas->isEmpty() ? '-' :$kelas->implode(', '));

        return [
            'nama' => $namaSiswa,
            'kelas' => $labelKelas,
            'jam' => static::jamKbmLabel($surat),
            'tanggal' => $surat->tanggal?->translatedFormat('d F Y') ?? '-',
            'alasan' => trim((string) $surat->alasan) !== '' ? trim((string) $surat->alasan) : '-',
            'catatan' => $surat->nomor_surat,
        ];
    }

    /**
     * Label waktu KBM pada pesan WA, mis. "Jam 3 - 4 (Keluar JP 4)".
     */
    protected static function jamKbmLabel(DispensasiSiswa|DispensasiKolektif $surat): string
    {
        if ((string) $surat->tipe_dispen === DispensasiSiswa::TIPE_MASUK) {
            return $surat->jam_masuk_jp ? 'Masuk mulai JP '.$surat->jam_masuk_jp : '-';
        }

        $jamList =$surat->jam_ke_list;

        if ($jamList === []) {
            return '-';
        }

        $label = 'Jam '.DispensasiSiswa::formatJamKeList($jamList);

        if ($surat->jam_keluar_jp) {
            $label .= ' (Keluar JP '.$surat->jam_keluar_jp.')';
        }

        return $label;
    }
}