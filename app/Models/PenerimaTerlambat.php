<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penerima Catatan Terlambat: guru piket bertugas hari itu & wali kelas siswa.
 */
class PenerimaTerlambat extends Model
{
    use HasFactory;

    protected $table = 'penerima_catatan_terlambat';

    public const PERAN_GURU_PIKET = 'guru_piket';
    public const PERAN_WALI_KELAS = 'wali_kelas';

    protected $fillable = [
        'catatan_terlambat_id',
        'user_id',
        'peran',
    ];

    public function catatan(): BelongsTo
    {
        return $this->belongsTo(CatatanTerlambat::class, 'catatan_terlambat_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}