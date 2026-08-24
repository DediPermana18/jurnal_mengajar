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
        'jenis',
    ];

    protected $casts = [
        'jam_ke' => 'integer',
    ];

    /**
     * Label jenis KBM yang ramah tampilan
     */
    public function getJenisLabelAttribute(): string
    {
        return match ($this->jenis) {
            'kbm'        => 'KBM',
            'istirahat'  => 'Istirahat',
            'upacara'    => 'Upacara',
            'pembiasaan' => 'Pembiasaan',
            default      => ucfirst($this->jenis ?? '-'),
        };
    }

    /**
     * Format jam_mulai & jam_selesai sebagai "HH.MM – HH.MM"
     */
    public function getRentangWaktuAttribute(): string
    {
        $mulai   = substr($this->jam_mulai, 0, 5);
        $selesai = substr($this->jam_selesai, 0, 5);
        return str_replace(':', '.', $mulai) . ' – ' . str_replace(':', '.', $selesai);
    }

    /**
     * Relasi ke Jadwal Pelajaran
     */
    public function jadwalPelajaran(): HasMany
    {
        return $this->hasMany(JadwalPelajaran::class, 'id_jam', 'id');
    }
}
