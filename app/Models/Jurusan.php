<?php

namespace App\Models;

use App\Models\Concerns\HasTestingData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Jurusan extends Model
{
    use HasFactory, SoftDeletes, HasTestingData;

    protected $table = 'jurusan';

    protected $guarded = ['id'];

    protected $casts = [
        'is_testing_data' => 'boolean',
    ];

    /**
     * Relasi ke Model Kelas (1 Jurusan Memiliki Banyak Kelas)
     */
    public function kelas(): HasMany
    {
        return $this->hasMany(Kelas::class, 'id_jurusan', 'id');
    }
}
