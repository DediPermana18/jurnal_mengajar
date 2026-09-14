<?php

namespace App\Models;

use App\Models\Concerns\HasTestingData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Laporan kendala / bug report dari pengguna aplikasi.
 *
 * Status: pending -> proses -> selesai.
 */
class LaporanKendala extends Model
{
    use HasFactory, HasTestingData;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROSES = 'proses';

    public const STATUS_SELESAI = 'selesai';

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_PROSES => 'Diproses',
        self::STATUS_SELESAI => 'Selesai',
    ];

    public const STATUS_BADGES = [
        self::STATUS_PENDING => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
        self::STATUS_PROSES => 'bg-info-subtle text-info-emphasis border border-info-subtle',
        self::STATUS_SELESAI => 'bg-success-subtle text-success border border-success-subtle',
    ];

    public const PRIORITAS_LOW = 'low';

    public const PRIORITAS_MEDIUM = 'medium';

    public const PRIORITAS_HIGH = 'high';

    public const PRIORITAS_LABELS = [
        self::PRIORITAS_LOW => 'Rendah',
        self::PRIORITAS_MEDIUM => 'Sedang',
        self::PRIORITAS_HIGH => 'Tinggi',
    ];

    public const PRIORITAS_BADGES = [
        self::PRIORITAS_LOW => 'bg-success-subtle text-success-emphasis border border-success-subtle',
        self::PRIORITAS_MEDIUM => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
        self::PRIORITAS_HIGH => 'bg-danger-subtle text-danger border border-danger-subtle',
    ];

    protected $table = 'laporan_kendala';

    protected $fillable = [
        'user_id',
        'judul',
        'deskripsi',
        'foto_bukti',
        'status',
        'prioritas',
        'is_testing_data',
    ];

    protected $casts = [
        'is_testing_data' => 'boolean',
    ];

    /**
     * Pengguna yang melaporkan kendala.
     */
    public function pelapor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Label status dalam Bahasa Indonesia.
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }

    /**
     * Badge Bootstrap untuk status.
     */
    public function getStatusBadgeAttribute(): string
    {
        return self::STATUS_BADGES[$this->status] ?? 'bg-secondary-subtle text-secondary border border-secondary-subtle';
    }

    /**
     * Label prioritas dalam Bahasa Indonesia.
     */
    public function getPrioritasLabelAttribute(): string
    {
        return self::PRIORITAS_LABELS[$this->prioritas] ?? ucfirst(str_replace('_', ' ', (string) $this->prioritas));
    }

    /**
     * Badge Bootstrap untuk prioritas.
     */
    public function getPrioritasBadgeAttribute(): string
    {
        return self::PRIORITAS_BADGES[$this->prioritas] ?? 'bg-secondary-subtle text-secondary border border-secondary-subtle';
    }
}
