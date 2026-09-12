<?php

namespace App\Models;

use App\Models\Concerns\HasTestingData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class DispensasiSiswa extends Model
{
    use HasFactory, HasTestingData;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PENDING_WAKA = 'pending_waka';

    public const STATUS_DISETUJUI = 'disetujui';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_FINAL = 'final';

    public const STATUS_DITOLAK = 'ditolak';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_KELUAR = 'keluar';

    public const STATUS_DIBATALKAN = 'dibatalkan';

    public const STATUS_MANGKIR = 'mangkir';

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_PENDING_WAKA => 'Pending Waka',
        self::STATUS_DISETUJUI => 'Disetujui',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_FINAL => 'Final',
        self::STATUS_DITOLAK => 'Ditolak',
        self::STATUS_EXPIRED => 'Kadaluarsa',
        self::STATUS_KELUAR => 'Siswa Out',
        self::STATUS_DIBATALKAN => 'Dibatalkan',
        self::STATUS_MANGKIR => 'Mangkir / Bolos',
    ];

    public const STATUS_BADGES = [
        self::STATUS_PENDING => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
        self::STATUS_PENDING_WAKA => 'bg-info-subtle text-info-emphasis border border-info-subtle',
        self::STATUS_DISETUJUI => 'bg-success-subtle text-success border border-success-subtle',
        self::STATUS_APPROVED => 'bg-primary-subtle text-primary-emphasis border border-primary-subtle',
        self::STATUS_FINAL => 'bg-success-subtle text-success border border-success-subtle',
        self::STATUS_DITOLAK => 'bg-danger-subtle text-danger border border-danger-subtle',
        self::STATUS_EXPIRED => 'bg-danger-subtle text-danger-emphasis border border-danger-subtle',
        self::STATUS_KELUAR => 'bg-success-subtle text-success-emphasis border border-success-subtle',
        self::STATUS_DIBATALKAN => 'bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle',
        self::STATUS_MANGKIR => 'bg-danger-subtle text-danger border border-danger-subtle',
    ];

    public const JENIS_KELUAR = 'keluar_gerbang';

    public const JENIS_SAKIT = 'sakit';

    public const JENIS_KEPERLUAN = 'keperluan';

    public const JENIS_ACARA = 'acara_sekolah';

    public const JENIS_LABELS = [
        self::JENIS_KELUAR => 'Keluar Gerbang Sekolah',
        self::JENIS_SAKIT => 'Sakit / Pulang',
        self::JENIS_KEPERLUAN => 'Keperluan Pribadi / Keluarga',
        self::JENIS_ACARA => 'Tugas / Acara Sekolah',
    ];

    // Tipe dispensasi: keluar gerbang (default) vs masuk kelas (izin telat / kembali KBM)
    public const TIPE_KELUAR = 'keluar';

    public const TIPE_MASUK = 'masuk';

    public const TIPE_LABELS = [
        self::TIPE_KELUAR => 'Keluar Gerbang',
        self::TIPE_MASUK => 'Masuk Kelas',
    ];

    protected $table = 'dispensasi_siswa';

    protected $fillable = [
        'id_siswa',
        'id_guru_piket',
        'id_jadwal',
        'id_guru',
        'tanggal',
        'jenis',
        'tipe_dispen',
        'jam_ke',
        'jam_keluar_jp',
        'jam_masuk_jp',
        'jam_kembali_jp',
        'tidak_kembali_hari_ini',
        'alasan',
        'status',
        'approved_at',
        'approved_by',
        'catatan_penolakan',
        'ttd_siswa',
        'ttd_guru',
        'ttd_waka',
        'ttd_pembatalan',
        'waka_kesiswaan_id',
        'approval_token',
        'keluar_gerbang_at',
        'keluar_gerbang_by',
        'expired_at',
        'dibatalkan_at',
        'dibatalkan_by',
        'kembali_at',
        'kembali_by',
        'mangkir_at',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'approved_at' => 'datetime',
        'keluar_gerbang_at' => 'datetime',
        'expired_at' => 'datetime',
        'dibatalkan_at' => 'datetime',
        'tidak_kembali_hari_ini' => 'boolean',
        'kembali_at' => 'datetime',
        'mangkir_at' => 'datetime',
        'is_testing' => 'boolean',
    ];

    /**
     * Relasi ke data Siswa yang di-dispensasi.
     */
    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'id_siswa', 'id');
    }

    /**
     * Relasi ke Guru Piket yang menerbitkan dispensasi.
     */
    public function guruPiket(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_guru_piket', 'id');
    }

    /**
     * Relasi ke slot Jadwal Pelajaran (mapel/guru) yang ditinggalkan.
     */
    public function jadwal(): BelongsTo
    {
        return $this->belongsTo(JadwalPelajaran::class, 'id_jadwal', 'id');
    }

    /**
     * Relasi ke Guru Mapel yang mengajar pada jadwal yang ditinggalkan.
     */
    public function guru(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_guru', 'id');
    }

    /**
     * Relasi ke User (Guru Piket / Admin) yang menyetujui pengajuan.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by', 'id');
    }

    /**
     * Relasi ke user Waka Kesiswaan yang menandatangani surat (TTD) dispensasi.
     */
    public function wakaKesiswaan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waka_kesiswaan_id', 'id');
    }

    /**
     * Relasi ke akun Satpam yang mengizinkan siswa keluar gerbang.
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'keluar_gerbang_by', 'id');
    }

    /**
     * Relasi ke akun Satpam yang mengonfirmasi siswa kembali ke gerbang.
     */
    public function kembaliVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kembali_by', 'id');
    }

    /**
     * Apakah pengajuan sudah disetujui?
     */
    public function isApproved(): bool
    {
        return in_array($this->status, [
            self::STATUS_DISETUJUI,
            self::STATUS_APPROVED,
            self::STATUS_FINAL,
        ], true);
    }

    /**
     * Apakah siswa sudah diizinkan keluar gerbang oleh Satpam?
     */
    public function isKeluarGerbang(): bool
    {
        return $this->keluar_gerbang_at !== null;
    }

    /**
     * Apakah surat berstatus Kadaluarsa (auto-expired)?
     */
    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    /**
     * Apakah surat berstatus Dibatalkan (pembatalan ber-TTD siswa)?
     */
    public function isDibatalkan(): bool
    {
        return $this->status === self::STATUS_DIBATALKAN;
    }

    /**
     * Surat boleh dibatalkan bila belum keluar gerbang dan statusnya bukan
     * status terminal (ditolak / dibatalkan / kadaluarsa / sudah keluar).
     */
    public function isBisaDibatalkan(): bool
    {
        return ! $this->isKeluarGerbang()
            && ! in_array($this->status, [
                self::STATUS_DITOLAK,
                self::STATUS_DIBATALKAN,
                self::STATUS_EXPIRED,
                self::STATUS_KELUAR,
                self::STATUS_MANGKIR,
            ], true);
    }

    /**
     * Status aktif yang masih menunggu verifikasi keluar (berpotensi kadaluarsa).
     */
    protected function isStatusAktifMenungguKeluar(): bool
    {
        return in_array($this->status, [
            self::STATUS_DISETUJUI,
            self::STATUS_APPROVED,
            self::STATUS_FINAL,
        ], true);
    }

    /**
     * Jam berangkat surat: JP keluar (jam_keluar_jp) atau JP pertama jam_ke.
     * Digunakan sebagai dasar perhitungan batas kadaluarsa.
     */
    protected function jamBerangkat(): ?int
    {
        if ($this->tipe_dispen === self::TIPE_MASUK) {
            return null;
        }

        $jamKeluar = (int) ($this->jam_keluar_jp ?? 0);
        if ($jamKeluar > 0) {
            return $jamKeluar;
        }

        return $this->jam_ke_list[0] ?? null;
    }

    /**
     * Batas kadaluarsa surat = Jam Berangkat + 1 JP (durasi satu jam pelajaran).
     * Mengembalikan null bila data jam tidak tersedia / surat bukan tipe keluar.
     */
    public function batasKadaluarsa(): ?Carbon
    {
        return $this->batasDariJp($this->jamBerangkat());
    }

    /**
     * Batas mangkir surat = Rencana Jam Kembali (jam_kembali_jp) + 1 JP.
     * Bila siswa belum dikonfirmasi kembali melewati batas ini -> Mangkir / Bolos.
     */
    public function batasMangkir(): ?Carbon
    {
        return $this->batasDariJp($this->jamKembali());
    }

    /**
     * Waktu akhir JP ke-N pada tanggal dispensasi (mulai JP + durasi satu
     * jam pelajaran). Dipakai sebagai dasar batas kadaluarsa & batas mangkir.
     */
    protected function batasDariJp(?int $jam): ?Carbon
    {
        if (! $this->tanggal || ! $jam) {
            return null;
        }

        $kategori = $this->tanggal->isFriday() ? 'Jumat' : 'Senin-Kamis';

        $jamMulai = JamPelajaran::where('jam_ke', $jam)
            ->where('kategori_hari', $kategori)
            ->whereNotNull('jam_mulai')
            ->orderBy('jam_mulai')
            ->get()
            ->first(fn (JamPelajaran $j) => $j->jenis === 'kbm');

        if (! $jamMulai) {
            $jamMulai = JamPelajaran::where('jam_ke', $jam)
                ->where('kategori_hari', $kategori)
                ->whereNotNull('jam_mulai')
                ->orderBy('jam_mulai')
                ->first();
        }

        if (! $jamMulai) {
            return null;
        }

        // Durasi satu JP: default 45 menit bila jam_selesai tidak tersedia.
        $durasi = 45;
        if ($jamMulai->jam_mulai && $jamMulai->jam_selesai) {
            $durasi = (int) Carbon::parse($jamMulai->jam_selesai)
                ->diffInMinutes(Carbon::parse($jamMulai->jam_mulai));
            if ($durasi <= 0) {
                $durasi = 45;
            }
        }

        return $this->tanggal->copy()
            ->setTimeFromTimeString(substr((string) $jamMulai->jam_mulai, 0, 5).':00')
            ->addMinutes($durasi);
    }

    /**
     * Periksa & ubah status otomatis bila surat aktif sudah melewati batas
     * (Jam Berangkat + 1 JP) menjadi 'Kadaluarsa'. Mengembalikan true bila berubah.
     */
    public function refreshStatusOtomatis(): bool
    {
        if (! $this->isStatusAktifMenungguKeluar()) {
            return false;
        }

        $batas = $this->batasKadaluarsa();
        if (! $batas || now()->lessThan($batas)) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_EXPIRED,
            'expired_at' => now(),
        ]);

        return true;
    }

    /**
     * Jalankan pengecekan auto-expired massal untuk semua surat aktif yang
     * tanggalnya sudah lewat / hari ini dan belum dikonfirmasi keluar.
     * Dipanggil scheduler (artisan dispensasi:auto-expire) dan pada halaman
     * portal Satpam / Guru Piket agar status selalu segar tanpa menunggu cron.
     */
    public static function refreshAutoExpired(): int
    {
        $count = 0;

        $aktif = static::query()
            ->whereDate('tanggal', '<=', now()->toDateString())
            ->whereNull('keluar_gerbang_at')
            ->where('tipe_dispen', '!=', self::TIPE_MASUK)
            ->whereIn('status', [
                self::STATUS_DISETUJUI,
                self::STATUS_APPROVED,
                self::STATUS_FINAL,
            ])
            ->get();

        foreach ($aktif as $dispen) {
            if ($dispen->refreshStatusOtomatis()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Rencana Jam Kembali (jam_kembali_jp) surat tipe keluar, atau null.
     */
    protected function jamKembali(): ?int
    {
        $jam = (int) ($this->jam_kembali_jp ?? 0);

        return $jam > 0 ? $jam : null;
    }

    /**
     * Apakah siswa dicatat "Tidak Kembali Hari Ini" (izin hingga pulang).
     */
    public function isTidakKembaliHariIni(): bool
    {
        return (bool) $this->tidak_kembali_hari_ini;
    }

    /**
     * Apakah siswa sudah dikonfirmasi kembali oleh Satpam.
     */
    public function isKembali(): bool
    {
        return $this->kembali_at !== null;
    }

    /**
     * Apakah surat berstatus Mangkir / Bolos (auto-alfa).
     */
    public function isMangkir(): bool
    {
        return $this->status === self::STATUS_MANGKIR;
    }

    /**
     * Surat tipe keluar yang sudah keluar gerbang, belum kembali, memiliki
     * rencana jam kembali, dan statusnya masih aktif (bisa dijadikan mangkir).
     */
    public function isMenungguKembali(): bool
    {
        return ! $this->isTipeMasuk()
            && ! $this->isTidakKembaliHariIni()
            && ! $this->isKembali()
            && $this->jamKembali() !== null
            && $this->isStatusAktifMenungguPulang();
    }

    /**
     * Status aktif yang sudah keluar gerbang: berpotensi dicatat kembali.
     */
    protected function isStatusAktifMenungguPulang(): bool
    {
        return $this->isKeluarGerbang()
            && in_array($this->status, [
                self::STATUS_DISETUJUI,
                self::STATUS_APPROVED,
                self::STATUS_FINAL,
                self::STATUS_KELUAR,
            ], true);
    }

    /**
     * Periksa & ubah status otomatis menjadi 'Mangkir / Bolos' bila siswa yang
     * sudah keluar belum kembali melewati batas (Jam Kembali + 1 JP). Absensi
     * jurnal JP terkait juga diubah menjadi 'Alpa' (Alfa). true bila berubah.
     */
    public function refreshStatusMangkir(): bool
    {
        if (! $this->isMenungguKembali()) {
            return false;
        }

        $batas = $this->batasMangkir();
        if (! $batas || now()->lessThan($batas)) {
            return false;
        }

        $this->markAlfa();

        return $this->update([
            'status' => self::STATUS_MANGKIR,
            'mangkir_at' => now(),
        ]);
    }

    /**
     * Jalankan pengecekan auto-alfa massal untuk semua surat yang sudah keluar
     * dan melewati batas kembali (Jam Kembali + 1 JP). Dipanggil scheduler
     * (artisan dispensasi:auto-mangkir) dan pada halaman portal Satpam /
     * Guru Piket agar status selalu segar tanpa menunggu cron.
     */
    public static function refreshAutoMangkir(): int
    {
        $count = 0;

        $terpilih = static::query()
            ->whereDate('tanggal', '<=', now()->toDateString())
            ->whereNull('kembali_at')
            ->where('tidak_kembali_hari_ini', false)
            ->whereNotNull('jam_kembali_jp')
            ->whereNotNull('keluar_gerbang_at')
            ->whereIn('status', [
                self::STATUS_DISETUJUI,
                self::STATUS_APPROVED,
                self::STATUS_FINAL,
                self::STATUS_KELUAR,
            ])
            ->get();

        foreach ($terpilih as $dispen) {
            if ($dispen->refreshStatusMangkir()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Jurnal pada tanggal dispensasi yang jam pelajarannya sama dengan
     * Rencana Jam Kembali (di luar jam ke yang dibayar dispensasi).
     */
    public function jurnalKembaliTerkait()
    {
        $jamKembali = $this->jamKembali();
        if (! $jamKembali || ! $this->siswa?->id_kelas || $this->tanggal?->toDateString() === null) {
            return collect();
        }

        // Jam kembali sudah termasuk dalam daftar jam_ke dispensasi -> ditangani jurnalTerkait().
        if (in_array($jamKembali, $this->jam_ke_list, true)) {
            return collect();
        }

        $jadwalIds = JadwalPelajaran::with('jamPelajaran')
            ->where('id_kelas', $this->siswa->id_kelas)
            ->get()
            ->filter(fn (JadwalPelajaran $j) => (int) $j->jamPelajaran?->jam_ke === $jamKembali)
            ->pluck('id');

        return $jadwalIds->isEmpty()
            ? collect()
            : Jurnal::whereIn('id_jadwal', $jadwalIds)->whereDate('tanggal', $this->tanggal)->get();
    }

    /**
     * Ubah presensi otomatis ("Dispen") menjadi 'Alpa' (Alfa) sebagai konfirmasi
     * Mangkir / Bolos pada JP dispensasi, plus JP Rencana Kembali bila berbeda.
     * Mengembalikan jumlah baris absensi yang diubah/dibuat.
     */
    public function markAlfa(): int
    {
        $idSiswa = (int) $this->id_siswa;
        $alasan = trim((string) $this->alasan);
        $keterangan = 'Absen Alfa (Mangkir/Bolos): '.$alasan;
        $count = 0;

        foreach ($this->jurnalTerkait() as $jurnal) {
            $row = AbsensiJurnal::where('id_jurnal', $jurnal->id)
                ->where('id_siswa', $idSiswa)
                ->where('status', 'Dispen')
                ->where('keterangan', 'like', 'Dispensasi:%')
                ->first();

            if ($row) {
                $row->update(['status' => 'Alpa', 'keterangan' => $keterangan]);
                $count++;
            }
        }

        foreach ($this->jurnalKembaliTerkait() as $jurnal) {
            $row = AbsensiJurnal::where('id_jurnal', $jurnal->id)
                ->where('id_siswa', $idSiswa)
                ->first();

            if (! $row) {
                AbsensiJurnal::create([
                    'id_jurnal' => $jurnal->id,
                    'id_siswa' => $idSiswa,
                    'status' => 'Alpa',
                    'keterangan' => $keterangan,
                ]);
                $count++;
            } elseif ($row->status === 'Dispen' && str_starts_with((string) $row->keterangan, 'Dispensasi:')) {
                $row->update(['status' => 'Alpa', 'keterangan' => $keterangan]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Nomor surat resmi surat dispensasi, mis. DIS-0001/2026.
     */
    public function getNomorSuratAttribute(): string
    {
        return 'DIS-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT)
            .'/'.($this->tanggal?->format('Y') ?? now()->year);
    }

    /**
     * Label status dalam Bahasa Indonesia.
     */
    public function getStatusLabelAttribute(): string
    {
        if ($this->status === self::STATUS_FINAL) {
            return 'Final';
        }

        return self::STATUS_LABELS[$this->status] ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }

    /**
     * Badge bootstrap untuk status.
     */
    public function getStatusBadgeAttribute(): string
    {
        return self::STATUS_BADGES[$this->status] ?? 'bg-secondary-subtle text-secondary border border-secondary-subtle';
    }

    /**
     * Status tampilan khusus portal Waka Kesiswaan:
     * Menunggu TTD (kuning) / Disetujui (hijau) / Ditolak (merah).
     */
    public function getKesiswaanStatusLabelAttribute(): string
    {
        return match (true) {
            $this->status === self::STATUS_DITOLAK => 'Ditolak',
            $this->status === self::STATUS_DIBATALKAN => 'Dibatalkan',
            $this->status === self::STATUS_EXPIRED => 'Kadaluarsa',
            $this->status === self::STATUS_KELUAR => 'Siswa Out',
            $this->status === self::STATUS_MANGKIR => 'Mangkir / Bolos',
            default => $this->has_ttd_waka ? 'Disetujui' : 'Menunggu TTD',
        };
    }

    /**
     * Badge Bootstrap untuk status portal Waka Kesiswaan.
     */
    public function getKesiswaanStatusBadgeAttribute(): string
    {
        return match (true) {
            $this->status === self::STATUS_DITOLAK => 'bg-danger-subtle text-danger-emphasis border border-danger-subtle',
            $this->status === self::STATUS_EXPIRED => 'bg-danger-subtle text-danger-emphasis border border-danger-subtle',
            $this->status === self::STATUS_DIBATALKAN => 'bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle',
            $this->status === self::STATUS_KELUAR => 'bg-success-subtle text-success-emphasis border border-success-subtle',
            $this->status === self::STATUS_MANGKIR => 'bg-danger-subtle text-danger-emphasis border border-danger-subtle',
            default => $this->has_ttd_waka
                ? 'bg-success-subtle text-success-emphasis border border-success-subtle'
                : 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
        };
    }

    /**
     * Apakah surat masih menunggu tanda tangan Waka Kesiswaan (bisa di-TTD)?
     */
    public function isMenungguTtdWaka(): bool
    {
        return ! $this->has_ttd_waka
            && $this->status !== self::STATUS_DITOLAK
            && in_array($this->status, [
                self::STATUS_PENDING,
                self::STATUS_PENDING_WAKA,
                self::STATUS_DISETUJUI,
            ], true);
    }

    /**
     * Label jenis dispensasi dalam Bahasa Indonesia.
     */
    public function getJenisLabelAttribute(): string
    {
        return self::JENIS_LABELS[$this->jenis] ?? ucfirst(str_replace('_', ' ', (string) $this->jenis));
    }

    /**
     * Apakah dispensasi bertipe "Masuk Kelas" (izin telat / kembali KBM)?
     */
    public function isTipeMasuk(): bool
    {
        return $this->tipe_dispen === self::TIPE_MASUK;
    }

    /**
     * Label tipe dispensasi, mis. "Masuk Kelas" / "Keluar Gerbang".
     */
    public function getTipeDispenLabelAttribute(): string
    {
        return self::TIPE_LABELS[$this->tipe_dispen ?? self::TIPE_KELUAR] ?? ucfirst((string) $this->tipe_dispen);
    }

    /**
     * Daftar jam ke dalam bentuk array, mis. "3,4,5" -> [3, 4, 5].
     */
    public function getJamKeListAttribute(): array
    {
        return collect(explode(',', (string) $this->jam_ke))
            ->map(fn ($j) => (int) trim($j))
            ->filter(fn ($j) => $j > 0)
            ->values()
            ->all();
    }

    /**
     * Format ringkas jam ke, merangkum rentang berurutan.
     * Mis. [1,2,3,4,11] -> "Jam 1 - 4, 11"; [3,5,6] -> "Jam 3, 5 - 6".
     */
    public static function formatJamKeList(array $jamKeList): string
    {
        $list = array_values(array_unique(array_filter(array_map('intval', $jamKeList), fn ($j) => $j > 0)));
        sort($list);

        if (empty($list)) {
            return '-';
        }

        $segments = [];
        $start = $prev = $list[0];

        foreach (array_slice($list, 1) as $j) {
            if ($j === $prev + 1) {
                $prev = $j;

                continue;
            }
            $segments[] = ($start === $prev) ? (string) $start : "{$start} - {$prev}";
            $start = $prev = $j;
        }
        $segments[] = ($start === $prev) ? (string) $start : "{$start} - {$prev}";

        return implode(', ', $segments);
    }

    /**
     * Label ringkas jam ke, mis. [1,2,3,4,11] -> "Jam 1 - 4, 11".
     */
    public function getJamKeLabelAttribute(): string
    {
        return 'Jam '.self::formatJamKeList($this->jam_ke_list);
    }

    /**
     * URL tampilan tanda tangan siswa (canvas / data URL base64) atau null.
     */
    public function getTtdUrlAttribute(): ?string
    {
        $ttd = $this->ttd_siswa ? trim((string) $this->ttd_siswa) : null;

        if (! $ttd) {
            return null;
        }

        return preg_match('/^data:/i', $ttd)
            ? $ttd
            : Storage::disk('public')->url($ttd);
    }

    /**
     * Apakah siswa sudah menandatangani (canvas TTD) pada pengajuan ini?
     */
    public function getHasTtdAttribute(): bool
    {
        return (bool) $this->ttd_url;
    }

    /**
     * URL tampilan tanda tangan Guru Piket (penyetuju) atau null.
     */
    public function getTtdGuruUrlAttribute(): ?string
    {
        $ttd = $this->ttd_guru ? trim((string) $this->ttd_guru) : null;

        if (! $ttd) {
            return null;
        }

        return preg_match('/^data:/i', $ttd)
            ? $ttd
            : Storage::disk('public')->url($ttd);
    }

    /**
     * Apakah Guru Piket sudah menandatangani (canvas TTD) saat membuat (ACC) pengajuan?
     */
    public function getHasTtdGuruAttribute(): bool
    {
        return (bool) $this->ttd_guru_url;
    }

    /**
     * Alias backward-compatible untuk TTD Guru Piket.
     */
    public function getTtdPiketAttribute(): ?string
    {
        return $this->ttd_guru;
    }

    /**
     * Alias backward-compatible untuk relasi Guru Piket.
     */
    public function getPiketAttribute(): ?User
    {
        return $this->guruPiket;
    }

    /**
     * URL tampilan tanda tangan Waka Kurikulum atau null.
     */
    public function getTtdWakaUrlAttribute(): ?string
    {
        $ttd = $this->ttd_waka ? trim((string) $this->ttd_waka) : null;

        if (! $ttd) {
            return null;
        }

        return preg_match('/^data:/i', $ttd)
            ? $ttd
            : Storage::disk('public')->url($ttd);
    }

    /**
     * Apakah Waka Kurikulum sudah menandatangani pengajuan ini?
     */
    public function getHasTtdWakaAttribute(): bool
    {
        return (bool) $this->ttd_waka_url;
    }

    /**
     * URL TTD pembatalan (data URI hasil canvas atau file tersimpan).
     */
    public function getTtdPembatalanUrlAttribute(): ?string
    {
        $ttd = $this->ttd_pembatalan ? trim((string) $this->ttd_pembatalan) : null;

        if (! $ttd) {
            return null;
        }

        return preg_match('/^data:/i', $ttd)
            ? $ttd
            : Storage::disk('public')->url($ttd);
    }

    /**
     * Apakah pembatalan sudah dibubuhi tanda tangan siswa?
     */
    public function getHasTtdPembatalanAttribute(): bool
    {
        return (bool) $this->ttd_pembatalan_url;
    }

    /**
     * Jurnal pada tanggal dispensa yang jam pelajarannya cocok dengan jam dispen.
     */
    public function jurnalTerkait()
    {
        $tanggal = $this->tanggal?->toDateString();
        $jadwalIds = $this->jadwalIdsTerkait();

        return $jadwalIds->isEmpty() || ! $tanggal
            ? collect()
            : Jurnal::whereIn('id_jadwal', $jadwalIds)->whereDate('tanggal', $tanggal)->get();
    }

    /**
     * ID jadwal pelajaran milik kelas siswa yang jam ke-nya termasuk dalam jam dispen.
     */
    public function jadwalIdsTerkait()
    {
        if (! $this->siswa?->id_kelas) {
            return collect();
        }

        $jamKeList = $this->jam_ke_list;

        return JadwalPelajaran::with('jamPelajaran')
            ->where('id_kelas', $this->siswa->id_kelas)
            ->get()
            ->filter(fn (JadwalPelajaran $j) => in_array((int) $j->jamPelajaran?->jam_ke, $jamKeList, true))
            ->pluck('id');
    }

    /**
     * Terapkan status "Dispen" otomatis ke absensi_jurnal pada jurnal yang sudah dibuat.
     * Mengembalikan jumlah baris absensi yang disinkronkan.
     */
    public function terapkanKeAbsensi(): int
    {
        $idSiswa = (int) $this->id_siswa;
        $alasan = trim((string) $this->alasan);
        $count = 0;

        foreach ($this->jurnalTerkait() as $jurnal) {
            AbsensiJurnal::updateOrCreate(
                ['id_jurnal' => $jurnal->id, 'id_siswa' => $idSiswa],
                [
                    'status' => 'Dispen',
                    'keterangan' => 'Dispensasi: '.$alasan,
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * Cabut status "Dispen" hasil integrasi (hanya baris yang dibuat otomatis).
     * Digunakan saat pengajuan ditolak / dicabut.
     */
    public function cabutDariAbsensi(): int
    {
        $idSiswa = (int) $this->id_siswa;
        $count = 0;

        foreach ($this->jurnalTerkait() as $jurnal) {
            $row = AbsensiJurnal::where('id_jurnal', $jurnal->id)
                ->where('id_siswa', $idSiswa)
                ->where('status', 'Dispen')
                ->where('keterangan', 'like', 'Dispensasi:%')
                ->first();

            if ($row) {
                $row->update(['status' => 'Hadir', 'keterangan' => null, 'foto_surat' => null]);
                $count++;
            }
        }

        return $count;
    }
}
