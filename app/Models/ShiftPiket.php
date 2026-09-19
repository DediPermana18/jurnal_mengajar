<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class ShiftPiket extends Model
{
    use HasFactory;

    protected $table = 'shift_piket';

    protected $fillable = [
        'nama',
        'jam_mulai',
        'jam_selesai',
        'maksimal_petugas',
        'is_active',
        'urutan',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'maksimal_petugas' => 'integer',
        'urutan' => 'integer',
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
