<?php

namespace App\Models;

use App\Models\Concerns\HasTestingData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgendaRutin extends Model
{
    use HasFactory, HasTestingData;

    protected $table = 'agenda_rutin';

    protected $fillable = [
        'hari',
        'jam_ke',
        'nama_agenda',
        'is_active',
        'shift_id',
        'is_testing_data',
    ];

    protected $casts = [
        'jam_ke' => 'integer',
        'is_active' => 'boolean',
        'shift_id' => 'integer',
        'is_testing_data' => 'boolean',
    ];

    /**
     * Scope: konfigurasi agenda milik satu konteks shift.
     *
     * shift_id = 0 berarti Global (mode penjadwalan global / kelas tanpa shift).
     * null diperlakukan sama dengan 0 untuk nilai yang belum pernah di-set.
     */
    public function scopeOfShift($query, ?int $shiftId)
    {
        return $query->where('shift_id', (int) ($shiftId ?: 0));
    }
}