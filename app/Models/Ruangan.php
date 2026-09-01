<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ruangan extends Model
{
    use HasFactory;

    protected $table = 'ruangans';

    protected $fillable = [
        'kode_ruangan',
        'nama_ruangan',
        'lokasi',
    ];

    /**
     * Guru/Pengurus yang mengelola ruangan ini
     */
    public function pengurus(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'pengurus_ruangan', 'ruangan_id', 'user_id')
            ->withTimestamps();
    }

    /**
     * Slot jadwal pelajaran yang memakai ruangan ini (ruangan bersifat dinamis per slot).
     */
    public function jadwalPelajaran(): HasMany
    {
        return $this->hasMany(JadwalPelajaran::class, 'id_ruangan');
    }
}
