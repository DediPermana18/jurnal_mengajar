<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
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
     * Relasi ke Kelas sebagai Wali Kelas
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
     * True jika role 'wali_kelas' ATAU terdaftar sebagai id_wali_kelas di tabel kelas.
     */
    public function isWaliKelas(): bool
    {
        if ($this->role === 'wali_kelas') {
            return true;
        }
        return $this->kelasWali()->exists();
    }
}
