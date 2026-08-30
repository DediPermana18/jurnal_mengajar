<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatatanTerlambat extends Model
{
    use HasFactory;

    protected $table = 'catatan_terlambat';

    protected $fillable = [
        'id_siswa',
        'tanggal',
        'jam_masuk',
        'keterangan',
        'id_satpam',
    ];

    /**
     * Penerima yang "dikirimi" record keterlambatan:
     * semua Guru Piket bertugas hari itu + Wali Kelas siswa.
     */
    public function penerima(): HasMany
    {
        return $this->hasMany(PenerimaTerlambat::class, 'catatan_terlambat_id', 'id');
    }

    /**
     * Jumlah Guru Piket yang menerima notifikasi keterlambatan ini.
     */
    public function getJumlahGuruPiketAttribute(): int
    {
        return $this->penerima->where('peran', PenerimaTerlambat::PERAN_GURU_PIKET)->count();
    }

    /**
     * User Wali Kelas penerima (jika ada).
     */
    public function getWaliKelasPenerimaAttribute(): ?User
    {
        return $this->penerima->firstWhere('peran', PenerimaTerlambat::PERAN_WALI_KELAS)?->user;
    }

    protected $casts = [
        'tanggal'   => 'date',
        'jam_masuk' => 'datetime',
    ];

    /**
     * Relasi ke data Siswa yang terlambat.
     */
    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'id_siswa', 'id');
    }

    /**
     * Relasi ke akun Satpam yang mencatat keterlambatan.
     */
    public function satpam(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_satpam', 'id');
    }
}