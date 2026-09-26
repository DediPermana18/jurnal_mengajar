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

    /** Tingkatan kelas yang didukung master kelas (urutan kanonis). */
    public const GRADE_LEVELS = ['X', 'XI', 'XII'];

    /** Label pendek per tingkatan (untuk badge/list): "Kelas 10" dst. */
    public const GRADE_LEVEL_SHORT_LABELS = [
        'X' => 'Kelas 10',
        'XI' => 'Kelas 11',
        'XII' => 'Kelas 12',
    ];

    /** Angka kelas per tingkatan (untuk label gabungan badge): "Kelas 10, 11 & 12". */
    public const GRADE_LEVEL_NUMBERS = [
        'X' => '10',
        'XI' => '11',
        'XII' => '12',
    ];

    /** Label lengkap per tingkatan (untuk form checkbox): "Kelas 10 / X" dst. */
    public const GRADE_LEVEL_FULL_LABELS = [
        'X' => 'Kelas 10 / X',
        'XI' => 'Kelas 11 / XI',
        'XII' => 'Kelas 12 / XII',
    ];

    protected $table = 'shift_pelajaran';

    protected $fillable = [
        'nama_shift',
        'keterangan',
        'jam_mulai',
        'jam_selesai',
        'is_active',
        'grade_levels',
        'is_testing_data',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_testing_data' => 'boolean',
        'grade_levels' => 'array',
    ];

    /**
     * Jam selesai dinamis: jam selesai slot jam pelajaran paling akhir milik
     * shift ini pada Tahun Ajaran aktif (scope global JamPelajaran).
     * null bila shift belum memiliki slot jam pelajaran.
     */
    public function getJamSelesaiDinamisAttribute(): ?string
    {
        if ($this->jamSelesaiDinamisResolved) {
            return $this->jamSelesaiDinamisCache;
        }

        $last = $this->jamPelajaran()
            ->whereNotNull('jam_selesai')
            ->orderByDesc('jam_selesai')
            ->limit(1)
            ->value('jam_selesai');

        $this->jamSelesaiDinamisCache = $last ?: null;
        $this->jamSelesaiDinamisResolved = true;

        return $this->jamSelesaiDinamisCache;
    }

    /**
     * Label rentang jam utama: "07.00 – 12.00", atau "Mulai 07.00" bila belum
     * ada slot, atau fallback "—". Jam selesai kini bersifat dinamis
     * (diturunkan dari slot JP terakhir), lihat getJamSelesaiDinamisAttribute.
     */
    public function getRentangUtamaAttribute(): string
    {
        if (empty($this->jam_mulai)) {
            return '—';
        }

        $mulai = str_replace(':', '.', substr($this->jam_mulai, 0, 5));
        $selesai = $this->jam_selesai_dinamis ?: $this->jam_selesai;

        if (empty($selesai)) {
            return 'Mulai '.$mulai;
        }

        return $mulai.' – '.str_replace(':', '.', substr($selesai, 0, 5));
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

    /**
     * Normalisasi daftar tingkatan: uppercase, buang nilai tak dikenal,
     * dan susun ulang mengikuti urutan kanonis X -> XI -> XII.
     *
     * @param  array|null  $raw  nilai mentah dari form (checkbox grade_levels[])
     */
    public static function normalizeGradeLevels(?array $raw): array
    {
        if (empty($raw)) {
            return [];
        }

        $normalized = array_values(array_unique(array_filter(
            array_map(fn ($v) => strtoupper(trim((string) $v)), $raw),
            fn ($v) => in_array($v, self::GRADE_LEVELS, true)
        )));

        usort(
            $normalized,
            fn ($a, $b) => array_search($a, self::GRADE_LEVELS, true) <=> array_search($b, self::GRADE_LEVELS, true)
        );

        return $normalized;
    }

    /**
     * Label pendek satu tingkatan ("Kelas 12").
     */
    public static function gradeShortLabel(string $grade): string
    {
        return self::GRADE_LEVEL_SHORT_LABELS[$grade] ?? $grade;
    }

    /**
     * Label gabungan tingkatan untuk badge ("Kelas 12", "Kelas 10, 11", "Kelas 10, 11, 12").
     */
    public static function gradeLevelsLabel(?array $grades): string
    {
        $grades = self::normalizeGradeLevels($grades);

        if (empty($grades)) {
            return 'Semua Tingkatan';
        }

        $numbers = array_map(fn ($g) => self::GRADE_LEVEL_NUMBERS[$g] ?? $g, $grades);

        return 'Kelas '.implode(', ', $numbers);
    }

    /**
     * Apakah shift ini melayani tingkatan kelas tertentu.
     * Kosong (null/[]) = berlaku untuk semua tingkatan (legacy).
     */
    public function servesGrade(?string $tingkat): bool
    {
        $levels = $this->grade_levels ?? [];

        if (empty($levels)) {
            return true;
        }

        return in_array(strtoupper(trim((string) $tingkat)), $levels, true);
    }

    /**
     * Label gabungan tingkatan ("Kelas 12" / "Kelas 10, 11") untuk badge list.
     */
    public function getGradeLevelsLabelAttribute(): string
    {
        return self::gradeLevelsLabel($this->grade_levels);
    }

    /** Cache kalkulasi jam_selesai_dinamis (hindari query berulang per request tampilan). */
    protected ?string $jamSelesaiDinamisCache = null;

    /** Penanda cache jam_selesai_dinamis sudah pernah dihitung. */
    protected bool $jamSelesaiDinamisResolved = false;
}