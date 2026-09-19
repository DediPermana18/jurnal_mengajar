<?php

namespace App\Models;

use App\Models\Concerns\HasTestingData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JadwalPiket extends Model
{
    use HasFactory, HasTestingData;

    protected $table = 'jadwal_piket';

    public const HARI_LIST = [
        'Senin',
        'Selasa',
        'Rabu',
        'Kamis',
        'Jumat',
    ];

    protected $fillable = [
        'user_id',
        'shift_id',
        'minggu_ke',
        'bulan',
        'tahun',
        'waka_user_id',
        'koordinator_pagi_user_id',
        'petugas_pagi_user_id',
        'koordinator_siang_user_id',
        'petugas_siang_user_id',
        'hari',
    ];

    protected $casts = [
        'is_testing_data' => 'boolean',
        'minggu_ke' => 'integer',
        'bulan' => 'integer',
        'tahun' => 'integer',
    ];

    /**
     * Relasi ke User (Guru / Petugas Piket)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(ShiftPiket::class, 'shift_id');
    }

    public function waka(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waka_user_id', 'id');
    }

    public function koordinatorPagi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'koordinator_pagi_user_id', 'id');
    }

    public function petugasPagi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'petugas_pagi_user_id', 'id');
    }

    public function koordinatorSiang(): BelongsTo
    {
        return $this->belongsTo(User::class, 'koordinator_siang_user_id', 'id');
    }

    public function petugasSiang(): BelongsTo
    {
        return $this->belongsTo(User::class, 'petugas_siang_user_id', 'id');
    }
}
