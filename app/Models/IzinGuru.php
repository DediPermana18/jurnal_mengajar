<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class IzinGuru extends Model
{
    use HasFactory;

    // Status-step alur approval bertingkat
    public const STATUS_PENDING_PIKET  = 'pending_piket';
    public const STATUS_PENDING_WAKA   = 'pending_waka';
    public const STATUS_PENDING_KEPSEK = 'pending_kepsek';
    public const STATUS_DISETUJUI      = 'disetujui';
    public const STATUS_DITOLAK        = 'ditolak';

    public const STATUSES = [
        self::STATUS_PENDING_PIKET,
        self::STATUS_PENDING_WAKA,
        self::STATUS_PENDING_KEPSEK,
        self::STATUS_DISETUJUI,
        self::STATUS_DITOLAK,
    ];

    public const STATUS_LABELS = [
        self::STATUS_PENDING_PIKET  => 'Pending Piket',
        self::STATUS_PENDING_WAKA   => 'Pending Waka',
        self::STATUS_PENDING_KEPSEK => 'Pending Kepsek',
        self::STATUS_DISETUJUI      => 'Disetujui',
        self::STATUS_DITOLAK        => 'Ditolak',
    ];

    public const STATUS_BADGES = [
        self::STATUS_PENDING_PIKET  => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
        self::STATUS_PENDING_WAKA   => 'bg-info-subtle text-info-emphasis border border-info-subtle',
        self::STATUS_PENDING_KEPSEK => 'bg-primary-subtle text-primary border border-primary-subtle',
        self::STATUS_DISETUJUI      => 'bg-success-subtle text-success border border-success-subtle',
        self::STATUS_DITOLAK        => 'bg-danger-subtle text-danger border border-danger-subtle',
    ];

    protected $table = 'izin_guru';

    protected $fillable = [
        'user_id',
        'tanggal',
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
    ];

    protected $casts = [
        'tanggal'     => 'date',
        'approved_at' => 'datetime',
    ];

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
        if (!$ttd) {
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

    public function getApprovalUrlAttribute(): ?string
    {
        return $this->approval_token ? url('/approve-izin/' . $this->approval_token) : null;
    }
}
