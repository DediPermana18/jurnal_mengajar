<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Siswa extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'siswa';

    protected $fillable = [
        'nisn',
        'nis',
        'nama',
        'jenis_kelamin',
        'id_kelas',
        'status_siswa',
    ];

    /**
     * Relasi ke Kelas
     */
    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'id_kelas', 'id');
    }

    /**
     * Relasi ke Absensi Jurnal
     */
    public function absensiJurnal(): HasMany
    {
        return $this->hasMany(AbsensiJurnal::class, 'id_siswa', 'id');
    }
}