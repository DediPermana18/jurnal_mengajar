<?php

namespace App\Models;

use App\Models\Concerns\HasTestingData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Status kehadiran guru harian.
 *
 * - Otomatis dibuat/diupdate saat pengajuan izin guru mencapai status final
 *   'disetujui' (Sakit / Izin / Dinas Luar sesuai kategori izin).
 * - Guru yang tidak memiliki record pada suatu tanggal dianggap berstatus
 *   'Hadir' (fallback state, tidak perlu disimpan ke DB).
 * - Bisa di-override manual oleh Guru Piket pada Portal Guru Piket.
 */
class StatusKehadiranGuru extends Model
{
    use HasFactory, HasTestingData;

    public const STATUS_HADIR = 'Hadir';

    public const STATUS_IZIN = 'Izin';

    public const STATUS_SAKIT = 'Sakit';

    public const STATUS_DINAS_LUAR = 'Dinas Luar';

    public const STATUSES = [
        self::STATUS_HADIR,
        self::STATUS_IZIN,
        self::STATUS_SAKIT,
        self::STATUS_DINAS_LUAR,
    ];

    public const STATUS_BADGES = [
        self::STATUS_HADIR => 'bg-success-subtle text-success border border-success-subtle',
        self::STATUS_SAKIT => 'bg-danger-subtle text-danger border border-danger-subtle',
        self::STATUS_IZIN => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
        self::STATUS_DINAS_LUAR => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
    ];

    public const STATUS_ICONS = [
        self::STATUS_HADIR => 'bi-check-circle-fill',
        self::STATUS_SAKIT => 'bi-thermometer-half',
        self::STATUS_IZIN => 'bi-calendar-x',
        self::STATUS_DINAS_LUAR => 'bi-briefcase-fill',
    ];

    protected $table = 'status_kehadiran_guru';

    protected $fillable = [
        'user_id',
        'tanggal',
        'status',
        'keterangan',
        'updated_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'is_testing_data' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getStatusBadgeAttribute(): string
    {
        return self::STATUS_BADGES[$this->status] ?? 'bg-secondary-subtle text-secondary border border-secondary-subtle';
    }

    public function getStatusIconAttribute(): string
    {
        return self::STATUS_ICONS[$this->status] ?? 'bi-question-circle';
    }
}