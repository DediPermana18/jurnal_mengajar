<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catatan tindak lanjut Wali Kelas atas siswa bermasalah
 * (panggil orang tua / catatan kendala).
 */
class CatatanSiswaBermasalah extends Model
{
    use HasFactory;

    protected $table = 'catatan_siswa_bermasalah';

    public const JENIS_PANGGIL_ORTU = 'panggil_ortu';
    public const JENIS_CATATAN      = 'catatan';

    public const STATUS_BELUM     = 'belum';
    public const STATUS_DIPANGGIL = 'dipanggil';
    public const STATUS_SELESAI   = 'selesai';

    protected $fillable = [
        'id_siswa',
        'id_wali_kelas',
        'jenis_tindakan',
        'catatan',
        'status',
    ];

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'id_siswa', 'id');
    }

    public function waliKelas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_wali_kelas', 'id');
    }
}