<?php

namespace App\Models;

use App\Models\Concerns\HasTestingData;
use App\Services\FonnteService;
use App\Services\StatusKehadiranService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class IzinGuru extends Model
{
    use HasFactory, HasTestingData;

    // Status-step alur approval bertingkat
    public const STATUS_PENDING_PIKET = 'pending_piket';

    public const STATUS_PENDING_WAKA = 'pending_waka';

    public const STATUS_PENDING_KEPSEK = 'pending_kepsek';

    public const STATUS_DISETUJUI = 'disetujui';

    public const STATUS_DITOLAK = 'ditolak';

    public const STATUSES = [
        self::STATUS_PENDING_PIKET,
        self::STATUS_PENDING_WAKA,
        self::STATUS_PENDING_KEPSEK,
        self::STATUS_DISETUJUI,
        self::STATUS_DITOLAK,
    ];

    public const STATUS_LABELS = [
        self::STATUS_PENDING_PIKET => 'Menunggu Piket',
        self::STATUS_PENDING_WAKA => 'Menunggu Waka SDM',
        self::STATUS_PENDING_KEPSEK => 'Menunggu Kepsek',
        self::STATUS_DISETUJUI => 'Disetujui',
        self::STATUS_DITOLAK => 'Ditolak',
    ];

    public const STATUS_BADGES = [
        self::STATUS_PENDING_PIKET => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
        self::STATUS_PENDING_WAKA => 'bg-info-subtle text-info-emphasis border border-info-subtle',
        self::STATUS_PENDING_KEPSEK => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
        self::STATUS_DISETUJUI => 'bg-success-subtle text-success border border-success-subtle',
        self::STATUS_DITOLAK => 'bg-danger-subtle text-danger border border-danger-subtle',
    ];

    public const KATEGORI_IZIN = [
        'sakit' => 'Sakit',
        'dinas_luar' => 'Dinas Luar / Tugas Sekolah',
        'urusan_keluarga' => 'Urusan Keluarga',
        'lainnya' => 'Lainnya',
    ];

    protected $table = 'izin_guru';

    protected $fillable = [
        'user_id',
        'tanggal',
        'kategori_izin',
        'keterangan',
        'alasan',
        'lampiran',
        'tugas_siswa',
        'status',
        'catatan_penolakan',
        'approved_by_piket',
        'approved_by_waka',
        'approved_by_kepsek',
        'approved_at',
        'ttd_guru',
        'ttd_waka',
        'ttd_kepsek',
        'approval_token',
        'token_waka',
        'token_kepsek',
        'token_piket',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'approved_at' => 'datetime',
        'is_testing_data' => 'boolean',
    ];

    /**
     * Auto-generasi token tahap:
     * - token_piket selalu tersedia untuk tiap pengajuan izin (dipakai link
     *   quick-approve yang disiarkan ke Guru Piket yang bertugas).
     * - token_waka   selalu tersedia untuk tiap pengajuan izin.
     * - token_kepsek baru dibuat saat status berubah menjadi Pending Kepsek,
     *   sehingga link Kepala Sekolah belum pernah ada sebelumnya.
     */
    protected static function booted(): void
    {
        static::saving(function (IzinGuru $izin) {
            if (! $izin->token_piket) {
                $izin->token_piket = (string) Str::uuid();
            }

            if (! $izin->token_waka) {
                $izin->token_waka = (string) Str::uuid();
            }

            if ($izin->status === self::STATUS_PENDING_KEPSEK && ! $izin->token_kepsek) {
                $izin->token_kepsek = (string) Str::uuid();
            }
        });

        // Sinkronisasi otomatis status kehadiran guru & alur notifikasi WA berantai:
        // - Izin diverifikasi Piket    => Tahap 1b: kirim WA ke nomor Waka SDM
        //   (status -> Pending Waka pada alur 3 level).
        // - Izin masuk tahap Pending Kepsek  => Tahap 2: kirim WA ke nomor Kepsek
        //   (Waka SDM setuju di alur 3 level / Piket lanjutkan di alur 2 level).
        // - Final approval (status -> 'disetujui') => catat Sakit/Izin/Dinas Luar
        //   pada tanggal terkait + Tahap 3: kirim WA final ke guru pengaju.
        // - Rollback / pembatalan dari 'disetujui'   => kembalikan ke 'Hadir'.
        static::created(function (IzinGuru $izin) {
            if ($izin->status === self::STATUS_DISETUJUI) {
                StatusKehadiranService::otomatiskanDariIzinDisetujui($izin);
                FonnteService::notifyIzinDisetujui($izin);
            }
        });

        static::updated(function (IzinGuru $izin) {
            $statusLama = $izin->getOriginal('status');
            $statusBaru = $izin->status;

            // Tahap 1b: izin diverifikasi Guru Piket -> masuk tahap menunggu Waka
            // SDM. Kirim WA otomatis ke nomor Waka (setelah quick-approve Piket
            // maupun approval Piket dari dashboard).
            if ($statusBaru === self::STATUS_PENDING_WAKA && $statusLama === self::STATUS_PENDING_PIKET) {
                FonnteService::notifyWakaMenungguApproval($izin);
            }

            // Tahap 2: izin masuk ke tahap menunggu persetujuan Kepala Sekolah.
            if ($statusBaru === self::STATUS_PENDING_KEPSEK && $statusLama !== self::STATUS_PENDING_KEPSEK) {
                $disetujuiOleh = $statusLama === self::STATUS_PENDING_PIKET ? 'Guru Piket' : 'Waka SDM';
                FonnteService::notifyKepsekMenungguApproval($izin, $disetujuiOleh);
            }

            if ($statusBaru === self::STATUS_DISETUJUI && $statusLama !== self::STATUS_DISETUJUI) {
                StatusKehadiranService::otomatiskanDariIzinDisetujui($izin);
                FonnteService::notifyIzinDisetujui($izin);

                return;
            }

            if ($statusLama === self::STATUS_DISETUJUI && $statusBaru !== self::STATUS_DISETUJUI) {
                StatusKehadiranService::kembalikanKeHadir($izin);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approverPiket(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_piket', 'id');
    }

    public function approverWaka(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_waka', 'id');
    }

    public function approverKepsek(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_kepsek', 'id');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }

    public function getStatusBadgeAttribute(): string
    {
        return self::STATUS_BADGES[$this->status] ?? 'bg-secondary-subtle text-secondary border border-secondary-subtle';
    }

    public static function kategoriLabel(?string $kategori): ?string
    {
        if ($kategori === null) {
            return null;
        }

        return self::KATEGORI_IZIN[$kategori] ?? $kategori;
    }

    public function getKategoriIzinLabelAttribute(): ?string
    {
        return self::kategoriLabel($this->kategori_izin);
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_DISETUJUI;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_DITOLAK;
    }

    public function isPending(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING_PIKET, self::STATUS_PENDING_WAKA, self::STATUS_PENDING_KEPSEK], true);
    }

    /**
     * Akses tanda tangan (data URL base64 / storage path) menjadi URL siap tampil.
     */
    protected function ttdUrlValue(?string $ttd): ?string
    {
        $ttd = $ttd ? trim((string) $ttd) : null;
        if (! $ttd) {
            return null;
        }

        return preg_match('/^data:/i', $ttd)
            ? $ttd
            : Storage::disk('public')->url($ttd);
    }

    public function getTtdGuruUrlAttribute(): ?string
    {
        return $this->ttdUrlValue($this->ttd_guru);
    }

    public function getTtdWakaUrlAttribute(): ?string
    {
        return $this->ttdUrlValue($this->ttd_waka);
    }

    public function getTtdKepsekUrlAttribute(): ?string
    {
        return $this->ttdUrlValue($this->ttd_kepsek);
    }

    public function getHasTtdGuruAttribute(): bool
    {
        return (bool) $this->ttd_guru_url;
    }

    public function getHasTtdWakaAttribute(): bool
    {
        return (bool) $this->ttd_waka_url;
    }

    public function getHasTtdKepsekAttribute(): bool
    {
        return (bool) $this->ttd_kepsek_url;
    }

    public function getWakaApprovalUrlAttribute(): ?string
    {
        return $this->token_waka ? url('/approve-izin/'.$this->token_waka) : null;
    }

    public function getPiketApprovalUrlAttribute(): ?string
    {
        return $this->token_piket ? url('/approve-piket/'.$this->token_piket) : null;
    }

    public function getKepsekApprovalUrlAttribute(): ?string
    {
        return $this->token_kepsek ? url('/approve-izin/'.$this->token_kepsek) : null;
    }

    public function getApprovalUrlAttribute(): ?string
    {
        // Link yang sedang berlaku sesuai tahap terakhir pengajuan.
        if ($this->status === self::STATUS_PENDING_KEPSEK && $this->token_kepsek) {
            return $this->kepsek_approval_url;
        }

        return $this->waka_approval_url;
    }
}
