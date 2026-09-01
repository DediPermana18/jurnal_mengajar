<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PresensiSiswa extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'presensi_siswa';

    protected $fillable = [
        'id_siswa',
        'id_kelas',
        'tanggal',
        'status',
        'keterangan',
        'id_guru_piket',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    /**
     * Relasi ke Siswa
     */
    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'id_siswa', 'id');
    }

    /**
     * Relasi ke Kelas
     */
    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'id_kelas', 'id');
    }

    /**
     * Relasi ke Guru Piket
     */
    public function guruPiket(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_guru_piket', 'id');
    }

    /**
     * Scope untuk filter tanggal hari ini
     */
    public function scopeHariIni($query)
    {
        return $query->whereDate('tanggal', now()->toDateString());
    }

    /**
     * Scope untuk filter per kelas
     */
    public function scopeKelas($query, $idKelas)
    {
        return $query->where('id_kelas', $idKelas);
    }
}