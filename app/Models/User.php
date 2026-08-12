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
    ];

    protected $hidden = [
        'password',
        'kode_aktivasi',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

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
}
