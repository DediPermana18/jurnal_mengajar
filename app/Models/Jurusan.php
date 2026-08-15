<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Jurusan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'jurusan';

    protected $guarded = ['id'];

    /**
     * Relasi ke Model Kelas (1 Jurusan Memiliki Banyak Kelas)
     */
    public function kelas(): HasMany
    {
        return $this->hasMany(Kelas::class, 'id_jurusan', 'id');
    }
}
