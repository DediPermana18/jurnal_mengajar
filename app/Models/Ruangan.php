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
     * Kelas yang menggunakan ruangan ini
     */
    public function kelas(): HasMany
    {
        return $this->hasMany(Kelas::class, 'ruangan_id');
    }
}
