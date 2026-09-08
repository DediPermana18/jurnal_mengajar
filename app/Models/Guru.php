<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Guru extends User
{
    /**
     * Model "Guru" memetakan ke tabel users dengan scope role = guru,
     * sehingga relasi (wali kelas & mapel diampu) dibaca dinamis dari
     * tabel kelas dan jadwal_pelajaran — tidak pernah di-hardcode.
     */
    protected $table = 'users';

    protected static function booted(): void
    {
        static::addGlobalScope('role_guru', function (Builder $builder) {
            $builder->where('role', static::ROLE_GURU);
        });
    }

    /**
     * Kelas di mana ID guru dipasang sebagai wali kelas (kelas.id_wali_kelas).
     */
    public function waliKelas(): HasMany
    {
        return $this->hasMany(Kelas::class, 'id_wali_kelas', 'id');
    }

    /**
     * Daftar mata pelajaran yang diampu guru berdasarkan ploting di
     * jadwal_pelajaran (id_guru → id_mapel).
     * Catatan: distinct() tidak didukung pada hasManyThrough;
     * gunakan ->mataPelajaran()->get()->unique('id') untuk hasil unik.
     */
    public function mataPelajaran(): HasManyThrough
    {
        return $this->hasManyThrough(
            MataPelajaran::class,
            JadwalPelajaran::class,
            'id_guru',  // FK di jadwal_pelajaran → users.id
            'id',       // FK di mata_pelajaran → jadwal_pelajaran.id_mapel
            'id',       // PK di users
            'id_mapel'  // FK di jadwal_pelajaran → mata_pelajaran.id
        );
    }
}