<?php

namespace App\Models;

use App\Models\Concerns\HasTestingData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Master jenis shift sekolah (Shift 1 Pagi, Shift 2 Siang, dst.).
 *
 * Shift hanya memengaruhi struktur jam pelajaran (jam_pelajaran.shift_id)
 * dan penentuan shift yang berlaku pada sebuah kelas (kelas.shift_id).
 * Slot/jam pelajaran dengan shift_id NULL adalah "Global" (legacy) dan
 * tetap berlaku untuk semua kelas.
 */
class ShiftPelajaran extends Model
{
    use HasFactory, HasTestingData;

    protected $table = 'shift_pelajaran';

    protected $fillable = [
        'nama_shift',
        'keterangan',
        'jam_mulai',
        'jam_selesai',
        'is_active',
        'is_testing_data',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_testing_data' => 'boolean',
    ];

    /**
     * Label rentang jam utama ("07.00 – 12.00") atau fallback "—".
     */
    public function getRentangUtamaAttribute(): string
    {
        if (empty($this->jam_mulai) || empty($this->jam_selesai)) {
            return '—';
        }

        $mulai = str_replace(':', '.', substr($this->jam_mulai, 0, 5));
        $selesai = str_replace(':', '.', substr($this->jam_selesai, 0, 5));

        return $mulai.' – '.$selesai;
    }

    /**
     * Slot jam pelajaran milik shift ini.
     */
    public function jamPelajaran(): HasMany
    {
        return $this->hasMany(JamPelajaran::class, 'shift_id', 'id');
    }

    /**
     * Kelas-kelas yang terikat pada shift ini.
     */
    public function kelas(): HasMany
    {
        return $this->hasMany(Kelas::class, 'shift_id', 'id');
    }
}