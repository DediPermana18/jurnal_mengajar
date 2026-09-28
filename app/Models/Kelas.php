<?php

namespace App\Models;

use App\Models\Concerns\HasTestingData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Master Data Kelas — memakai isolasi data testing BERBASIS PERAN (bukan
 * mengikuti konteks lingkungan aktif), persis seperti master data lain
 * (Guru, Siswa, Jurusan, Mata Pelajaran, Ruangan):
 *
 *  - User operasional biasa (bukan Petugas IT / QA / akun sandbox) hanya
 *    MELIHAT & MENULIS data PRODUKSI (is_testing_data = false) — meskipun
 *    Tahun Ajaran aktif sedang di-flag testing, data kelas produksi tidak
 *    pernah disembunyikan/ditumpangi.
 *  - Petugas IT / QA Tester / sandbox hanya melihat & menulis partisi testing.
 *
 * Global scope TestingDataScope (via HasTestingData) menegakkan filter baca;
 * model event 'creating' menegakkan flag tulis agar konsisten.
 */
class Kelas extends Model
{
    use HasFactory, HasTestingData, SoftDeletes;

    protected $table = 'kelas';

    protected $fillable = [
        'nama_kelas',
        'tingkat',
        'id_jurusan',
        'shift_id',
        'id_wali_kelas',
        'is_testing_data',
    ];

    protected $casts = [
        'is_testing_data' => 'boolean',
    ];

    /**
     * Nama kelas ringkas beserta tingkat (mis. "XI RPL 2", "X TJKT 1").
     */
    public function getNamaKelasLengkapAttribute(): string
    {
        return $this->nama_lengkap;
    }

    /**
     * Nama kelas lengkap dengan tingkat (mis. "XI RPL 2").
     */
    public function getNamaLengkapAttribute(): string
    {
        $label = trim((string) $this->nama_kelas);
        $tingkat = trim((string) $this->tingkat);

        if ($tingkat !== '' && ! str_starts_with(strtolower($label), strtolower($tingkat).' ')) {
            $label = "{$tingkat} {$label}";
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
     * Relasi ke Shift (NULL = kelas global / legacy).
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(ShiftPelajaran::class, 'shift_id', 'id');
    }

    /**
     * Shift efektif kelas: kembalikan 0 bila kelas tidak terikat shift (global).
     */
    public function getShiftEffectiveAttribute(): int
    {
        return $this->shift_id ? (int) $this->shift_id : 0;
    }

    /**
     * Relasi ke Jadwal Pelajaran
     */
    public function jadwalPelajaran(): HasMany
    {
        return $this->hasMany(JadwalPelajaran::class, 'id_kelas', 'id');
    }
}
