<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JamPelajaran extends Model
{
    use HasFactory;

    protected $table = 'jam_pelajaran';

    protected $fillable = [
        'kategori_hari',
        'jam_ke',
        'jam_mulai',
        'jam_selesai',
    ];

    /**
     * Relasi ke Jadwal Pelajaran
     */
    public function jadwalPelajaran(): HasMany
    {
        return $this->hasMany(JadwalPelajaran::class, 'id_jam', 'id');
    }
}
