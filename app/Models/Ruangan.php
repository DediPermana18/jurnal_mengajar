<?php

namespace App\Models;

use App\Models\Concerns\HasTestingData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ruangan extends Model
{
    use HasFactory, HasTestingData;

    protected $table = 'ruangans';

    protected $fillable = [
        'kode_ruangan',
        'nama_ruangan',
        'lokasi',
        'is_testing_data',
    ];

    protected $casts = [
        'is_testing_data' => 'boolean',
    ];

    /**
     * Guru/Pengurus yang mengelola ruangan ini
     */
    public function pengurus(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'pengurus_ruangan', 'ruangan_id', 'user_id')
            ->using(PengurusRuangan::class)
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
