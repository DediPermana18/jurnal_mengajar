<?php

namespace App\Models;

use App\Models\Concerns\HasTestingData;
use App\Models\Scopes\ActiveTahunAjaranScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JamPelajaran extends Model
{
    use HasFactory, HasTestingData;

    protected $table = 'jam_pelajaran';

    protected $fillable = [
        'hari',
        'kategori_hari',
        'shift_id',
        'jam_ke',
        'jam_mulai',
        'jam_selesai',
        'jenis',
        'tahun_ajaran_id',
    ];

    protected static function booted(): void
    {
        parent::booted();

        // Konsumen runtime hanya melihat slot TA aktif (+ slot legacy).
        static::addGlobalScope(new ActiveTahunAjaranScope);

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
     * Scope: slot yang terlihat oleh kelas dengan shift $shiftId.
     * Kelas selalu melihat slot Global (shift_id NULL) + slot shift-nya sendiri.
     */
    public function scopeForShift($query, ?int $shiftId)
    {
        return $query->where(function ($q) use ($shiftId) {
            $q->whereNull('shift_id')
                ->orWhere('shift_id', $shiftId);
        });
    }

    /**
     * Scope: slot milik satu shift tertentu (termasuk Global = NULL).
     */
    public function scopeOfShift($query, ?int $shiftId)
    {
        if ($shiftId === null || $shiftId === 0) {
            return $query->whereNull('shift_id');
        }

        return $query->where('shift_id', $shiftId);
    }

    /**
     * Scope: batasi slot ke konteks Tahun Ajaran & Semester tertentu.
     *
     * @param  int|null  $tahunAjaranId  id tahun_ajaran; null = hanya slot legacy (tanpa TA).
     * @param  bool  $includeLegacy  sertakan slot legacy (tahun_ajaran_id NULL) — dipakai
     *                               khusus untuk Tahun Ajaran yang sedang AKTIF, karena
     *                               data lama dipandang sebagai kepunyaan TA berjalan.
     */
    public function scopeOfTahunAjaran($query, ?int $tahunAjaranId, bool $includeLegacy = false)
    {
        if ($tahunAjaranId === null) {
            return $query->whereNull('tahun_ajaran_id');
        }

        return $query->where(function ($q) use ($tahunAjaranId, $includeLegacy) {
            $q->where('tahun_ajaran_id', $tahunAjaranId);
            if ($includeLegacy) {
                $q->orWhereNull('tahun_ajaran_id');
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
     * Normalisasi nilai waktu penyimpanan menjadi format konsisten "HH:MM".
     * Sumber bisa berupa 'HH:MM:SS', 'HH:MM', datetime string ('YYYY-MM-DD HH:MM:SS'),
     * atau nilai korup dari versi lama. Nilai yang tidak dapat diparsing
     * ditampilkan sebagai '--:--' agar tidak pernah menghasilkan teks acak
     * seperti '10:00 - 07:'.
     */
    public static function formatJamHm(?string $value): string
    {
        $value = is_string($value) ? trim($value) : '';

        if ($value === '') {
            return '--:--';
        }

        if (preg_match('/(\d{1,2}):(\d{2})/', $value, $m)) {
            $hour = min(23, max(0, (int) $m[1]));
            $minute = min(59, max(0, (int) $m[2]));

            return sprintf('%02d:%02d', $hour, $minute);
        }

        return '--:--';
    }

    /**
     * Label jam mulai konsisten HH:MM (opsi dropdown Agenda / Pembiasaan).
     */
    public function getJamMulaiLabelAttribute(): string
    {
        return static::formatJamHm($this->jam_mulai);
    }

    /**
     * Label jam selesai konsisten HH:MM (opsi dropdown Agenda / Pembiasaan).
     */
    public function getJamSelesaiLabelAttribute(): string
    {
        return static::formatJamHm($this->jam_selesai);
    }

    /**
     * Relasi ke Jadwal Pelajaran
     */
    public function jadwalPelajaran(): HasMany
    {
        return $this->hasMany(JadwalPelajaran::class, 'id_jam', 'id');
    }

    /**
     * Relasi ke Shift (NULL = slot global / legacy).
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(ShiftPelajaran::class, 'shift_id', 'id');
    }

    /**
     * Relasi ke Tahun Ajaran & Semester (NULL = slot legacy / era sebelum fitur TA).
     */
    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class, 'tahun_ajaran_id', 'id');
    }
}
