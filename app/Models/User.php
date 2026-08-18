<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $table = 'users';

    protected $fillable = [
        'nama',
        'nip',
        'username',
        'password',
        'kode_aktivasi',
        'role',
        'mapel_ids',
        'kelas_id',
    ];

    protected $casts = [
        'mapel_ids' => 'array',
        'password' => 'hashed',
    ];

    protected $hidden = [
        'password',
        'kode_aktivasi',
        'remember_token',
    ];

    /**
     * Relasi ke Kelas jika user di-assign kelas_id langsung
     */
    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id', 'id');
    }

    /**
     * Relasi ke Kelas sebagai Wali Kelas (via id_wali_kelas di tabel kelas)
     */
    public function kelasWali(): HasMany
    {
        return $this->hasMany(Kelas::class, 'id_wali_kelas', 'id');
    }

    /**
     * Relasi ke Jadwal Pelajaran sebagai Guru Pengajar
     */
    public function jadwalPelajaran(): HasMany
    {
        return $this->hasMany(JadwalPelajaran::class, 'id_guru', 'id');
    }

    /**
     * Helper: apakah user ini adalah Wali Kelas?
     * True jika role 'wali_kelas' ATAU terdaftar sebagai id_wali_kelas di tabel kelas ATAU memiliki kelas_id.
     */
    public function isWaliKelas(): bool
    {
        if ($this->role === 'wali_kelas') {
            return true;
        }
        if (!empty($this->kelas_id)) {
            return true;
        }
        return $this->kelasWali()->exists();
    }
}
