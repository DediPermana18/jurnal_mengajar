<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Kelas extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'kelas';

    protected $fillable = [
        'nama_kelas',
        'tingkat',
        'id_jurusan',
        'id_wali_kelas',
        'ruangan_id',
    ];

    public function getNamaLengkapAttribute(): string
    {
        return trim($this->tingkat . ' ' . $this->nama_kelas);
    }

    /**
     * Relasi ke Jurusan
     */
    public function jurusan(): BelongsTo
    {
        return $this->belongsTo(Jurusan::class, 'id_jurusan', 'id');
    }

    /**
     * Relasi ke User sebagai Wali Kelas
     */
    public function waliKelas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_wali_kelas', 'id');
    }

    /**
     * Relasi ke Siswa dalam kelas ini
     */
    public function siswa(): HasMany
    {
        return $this->hasMany(Siswa::class, 'id_kelas', 'id');
    }

    /**
     * Relasi ke Jadwal Pelajaran
     */
    public function jadwalPelajaran(): HasMany
    {
        return $this->hasMany(JadwalPelajaran::class, 'id_kelas', 'id');
    }

    /**
     * Relasi ke Ruangan
     */
    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class, 'ruangan_id');
    }
}