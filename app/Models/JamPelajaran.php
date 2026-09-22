<?php

namespace App\Models;

use App\Models\Concerns\HasTestingData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JamPelajaran extends Model
{
    use HasFactory, HasTestingData;

    protected $table = 'jam_pelajaran';

    protected $fillable = [
        'hari',
        'kategori_hari',
        'jam_ke',
        'jam_mulai',
        'jam_selesai',
        'jenis',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::saving(function ($jam) {
            if (empty($jam->hari)) {
                $kat = $jam->kategori_hari ?? 'Senin-Kamis';
                $jam->hari = ($kat === 'Jumat') ? 'Jumat' : 'Senin';
            }
            if (empty($jam->kategori_hari)) {
                $jam->kategori_hari = in_array($jam->hari, ['Senin', 'Selasa', 'Rabu', 'Kamis'], true) ? 'Senin-Kamis' : 'Jumat';
            }
        });
    }

    /**
     * Backward compatibility accessor for kategori_hari
     */
    public function getKategoriHariAttribute(): string
    {
        $h = $this->attributes['hari'] ?? null;
        if ($h && in_array($h, ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'], true)) {
            return in_array($h, ['Senin', 'Selasa', 'Rabu', 'Kamis'], true) ? 'Senin-Kamis' : 'Jumat';
        }
        $kat = $this->attributes['kategori_hari'] ?? 'Senin-Kamis';
        return ($kat === 'Jumat') ? 'Jumat' : 'Senin-Kamis';
    }

    protected $casts = [
        'jam_ke' => 'integer',
        'is_testing_data' => 'boolean',
    ];

    /**
     * Label jenis KBM yang ramah tampilan
     */
    public function getJenisLabelAttribute(): string
    {
        return match ($this->jenis) {
            'kbm' => 'KBM',
            'istirahat' => 'Istirahat',
            default => ucfirst($this->jenis ?? '-'),
        };
    }

    /**
     * Format jam_mulai & jam_selesai sebagai "HH.MM – HH.MM"
     */
    public function getRentangWaktuAttribute(): string
    {
        $mulai = substr($this->jam_mulai, 0, 5);
        $selesai = substr($this->jam_selesai, 0, 5);

        return str_replace(':', '.', $mulai).' – '.str_replace(':', '.', $selesai);
    }

    /**
     * Relasi ke Jadwal Pelajaran
     */
    public function jadwalPelajaran(): HasMany
    {
        return $this->hasMany(JadwalPelajaran::class, 'id_jam', 'id');
    }
}
