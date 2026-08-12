<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Jurnal extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'jurnal';

    protected $fillable = [
        'id_jadwal',
        'tanggal',
        'materi',
        'catatan_kejadian',
        'foto_kegiatan',
        'waktu_isi',
    ];

    protected function casts(): array
    {
        return [
            'tanggal'   => 'date',
            'waktu_isi' => 'datetime',
        ];
    }

    /**
     * Relasi ke Jadwal Pelajaran
     */
    public function jadwalPelajaran(): BelongsTo
    {
        return $this->belongsTo(JadwalPelajaran::class, 'id_jadwal', 'id');
    }

    /**
     * Alias relasi ke Jadwal Pelajaran
     */
    public function jadwal(): BelongsTo
    {
        return $this->jadwalPelajaran();
    }

    /**
     * Relasi ke Absensi Jurnal
     */
    public function absensiJurnal(): HasMany
    {
        return $this->hasMany(AbsensiJurnal::class, 'id_jurnal', 'id');
    }

    /**
     * Alias relasi ke Absensi Jurnal
     */
    public function absensi(): HasMany
    {
        return $this->absensiJurnal();
    }
}
