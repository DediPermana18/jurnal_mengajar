<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JadwalPiket extends Model
{
    use HasFactory;

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
        'hari',
    ];

    /**
     * Relasi ke User (Guru / Petugas Piket)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
