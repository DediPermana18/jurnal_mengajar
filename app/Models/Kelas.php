<?php

namespace App\Models;

use App\Models\Concerns\HasTestingData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Kelas extends Model
{
    use HasFactory, HasTestingData, SoftDeletes;

    protected $table = 'kelas';

    protected $fillable = [
        'nama_kelas',
        'tingkat',
        'id_jurusan',
        'id_wali_kelas',
        'is_testing_data',
    ];

    protected $casts = [
        'is_testing_data' => 'boolean',
    ];

    public function getNamaLengkapAttribute(): string
    {
        $label = trim((string) $this->nama_kelas);
        $tingkat = trim((string) $this->tingkat);

        // Tambahkan tingkat (X/XI/XII) bila nama_kelas belum memuatnya — mis. "PSPT 1" → "XII PSPT 1".
        if ($tingkat !== '' && ! str_starts_with($label, $tingkat.' ')) {
            $label = $tingkat.' '.$label;
        }

        // Lampirkan nama panjang jurusan — mis. "XII PSPT 1 (Produksi Siaran dan Program Televisi)".
        if ($namaJurusan = $this->jurusan?->nama_jurusan) {
            $label .= ' ('.$namaJurusan.')';
        }

        return trim($label);
    }

    /**
     * Relasi ke Jurusan
     */
    public function jurusan(): BelongsTo
    {
        return $this->belongsTo(Jurusan::class, 'id_jurusan', 'id');
    }

    /**
     * Relasi ke User sebagai Wali Kelas
     */
    public function waliKelas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_wali_kelas', 'id');
    }

    /**
     * Relasi ke Siswa dalam kelas ini
     */
    public function siswa(): HasMany
    {
        return $this->hasMany(Siswa::class, 'id_kelas', 'id');
    }

    /**
     * Relasi ke Jadwal Pelajaran
     */
    public function jadwalPelajaran(): HasMany
    {
        return $this->hasMany(JadwalPelajaran::class, 'id_kelas', 'id');
    }
}
