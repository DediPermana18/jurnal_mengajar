<?php

namespace App\Models;

use App\Models\Concerns\HasTestingData;
use App\Models\Concerns\TestingDataContextAware;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShiftPiket extends Model implements TestingDataContextAware
{
    use HasFactory, HasTestingData;

    protected $table = 'shift_piket';

    protected $fillable = [
        'nama',
        'jam_mulai',
        'jam_selesai',
        'maksimal_petugas',
        'is_active',
        'urutan',
        'is_testing_data',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'maksimal_petugas' => 'integer',
        'urutan' => 'integer',
        'is_testing_data' => 'boolean',
    ];

    public function jadwalPiket(): HasMany
    {
        return $this->hasMany(JadwalPiket::class, 'shift_id');
    }

    public function getJamLabelAttribute(): string
    {
        return substr($this->jam_mulai, 0, 5).' - '.substr($this->jam_selesai, 0, 5);
    }
}
