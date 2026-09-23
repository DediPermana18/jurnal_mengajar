<?php

namespace App\Models;

use App\Models\Concerns\HasTestingData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rekap Piket Harian — dokumen validasi piket yang disahkan oleh Waka Piket.
 *
 * Satu baris per tanggal (unique), berisi catatan Koordinator Shift Pagi &
 * Siang, catatan Kejadian Luar Biasa (KLB), serta jejak validasi Waka Piket.
 * Mengikuti isolasi data testing via trait HasTestingData.
 */
class RekapPiketHarian extends Model
{
    use HasFactory, HasTestingData;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_VALIDATED = 'validated';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_VALIDATED,
    ];

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_VALIDATED => 'Tervalidasi',
    ];

    public const STATUS_BADGES = [
        self::STATUS_DRAFT => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
        self::STATUS_VALIDATED => 'bg-success-subtle text-success border border-success-subtle',
    ];

    protected $table = 'rekap_piket_harian';

    protected $fillable = [
        'tanggal',
        'status',
        'koordinator_pagi_user_id',
        'koordinator_siang_user_id',
        'catatan_pagi',
        'catatan_siang',
        'catatan_klb',
        'validated_by',
        'validated_at',
        'is_testing_data',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'validated_at' => 'datetime',
        'status' => 'string',
        'is_testing_data' => 'boolean',
    ];

    /**
     * Koordinator Shift Pagi yang mengisi rekap pada tanggal tersebut.
     */
    public function koordinatorPagi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'koordinator_pagi_user_id', 'id');
    }

    /**
     * Koordinator Shift Siang yang mengisi rekap pada tanggal tersebut.
     */
    public function koordinatorSiang(): BelongsTo
    {
        return $this->belongsTo(User::class, 'koordinator_siang_user_id', 'id');
    }

    /**
     * Waka Piket / Kurikulum yang mengesahkan rekap (validated_by).
     */
    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by', 'id');
    }

    /**
     * Label status yang ramah tampilan.
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }

    /**
     * Kelas badge Bootstrap sesuai status.
     */
    public function getStatusBadgeAttribute(): string
    {
        return self::STATUS_BADGES[$this->status] ?? 'bg-secondary-subtle text-secondary border border-secondary-subtle';
    }

    /**
     * Apakah rekap sudah disahkan Waka Piket?
     */
    public function isValidated(): bool
    {
        return $this->status === self::STATUS_VALIDATED;
    }

    /**
     * Ambil rekap untuk sebuah tanggal, atau siapkan instance baru (belum
     * tersimpan) bila belum ada. Dijamin satu baris per tanggal (unique).
     *
     * Catatan SQLite: kolom `date` tersimpan sebagai "YYYY-MM-DD 00:00:00",
     * sehingga pencocokan string "YYYY-MM-DD" dengan where('tanggal', ...)
     * gagal dan berpotensi melahirkan duplikat pada firstOrNew/create.
     * Karena itu pencarian memakai whereDate(). Menghormati TestingDataScope.
     */
    public static function untukTanggal(string $tanggal): self
    {
        return static::whereDate('tanggal', $tanggal)->first()
            ?? new static(['tanggal' => $tanggal]);
    }

    /**
     * Apakah rekap masih berupa draft?
     */
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }
}