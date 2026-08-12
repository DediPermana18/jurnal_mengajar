<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AbsensiJurnal extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'absensi_jurnal';

    protected $fillable = [
        'id_jurnal',
        'id_siswa',
        'status',
        'keterangan',
    ];

    /**
     * Relasi ke Jurnal
     */
    public function jurnal(): BelongsTo
    {
        return $this->belongsTo(Jurnal::class, 'id_jurnal', 'id');
    }

    /**
     * Relasi ke Siswa
     */
    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'id_siswa', 'id');
    }
}
