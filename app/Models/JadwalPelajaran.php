<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class JadwalPelajaran extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'jadwal_pelajaran';

    protected $fillable = [
        'group_id',
        'hari',
        'id_jam',
        'id_kelas',
        'id_mapel',
        'id_guru',
        'id_tahun_ajaran',
    ];

    /**
     * Relasi ke Jam Pelajaran
     */
    public function jamPelajaran(): BelongsTo
    {
        return $this->belongsTo(JamPelajaran::class, 'id_jam', 'id');
    }

    /**
     * Alias relasi ke Jam Pelajaran
     */
    public function jam(): BelongsTo
    {
        return $this->jamPelajaran();
    }

    /**
     * Relasi ke Kelas
     */
    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'id_kelas', 'id');
    }

    /**
     * Relasi ke Mata Pelajaran
     */
    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class, 'id_mapel', 'id');
    }

    /**
     * Alias relasi ke Mata Pelajaran
     */
    public function mapel(): BelongsTo
    {
        return $this->mataPelajaran();
    }

    /**
     * Relasi ke User (Guru Pengajar)
     */
    public function guru(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_guru', 'id');
    }

    /**
     * Relasi ke Tahun Ajaran
     */
    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class, 'id_tahun_ajaran', 'id');
    }

    /**
     * Relasi ke Jurnal
     */
    public function jurnal(): HasMany
    {
        return $this->hasMany(Jurnal::class, 'id_jadwal', 'id');
    }
}
