<?php

namespace App\Models;

use App\Models\Concerns\HasTestingData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class JamPulang extends Model
{
    use HasFactory, HasTestingData;

    protected $table = 'jam_pulang';

    protected $fillable = [
        'shift_id',
        'kategori_hari',
        'tingkat',
        'max_jam_ke',
        'is_testing_data',
    ];

    protected $casts = [
        'shift_id' => 'integer',
        'max_jam_ke' => 'integer',
        'is_testing_data' => 'boolean',
    ];

    /**
     * Ambil nilai max_jam_ke untuk kombinasi shift + kategori hari + tingkat kelas.
     * shift_id 0 (default) = pengaturan Global (kelas tanpa shift).
     * Mengembalikan null jika tidak ada batas (bebas).
     */
    public static function getMaxJamKe(string $kategoriHari, string $tingkat, int $shiftId = 0): ?int
    {
        $record = static::where('kategori_hari', $kategoriHari)
            ->where('tingkat', $tingkat)
            ->where('shift_id', $shiftId)
            ->first();

        return $record?->max_jam_ke;
    }

    /**
     * Ambil semua setting sebagai collection yang di-key oleh
     * "shift_id|kategori_hari|tingkat" untuk efisiensi lookup di view.
     */
    public static function getAllAsLookup(): Collection
    {
        return static::all()->keyBy(fn ($r) => "{$r->shift_id}|{$r->kategori_hari}|{$r->tingkat}");
    }

    /**
     * Normalisasi terhadap master jam pelajaran terkini.
     *
     * Jika nilai max_jam_ke pada jam_pulang melebihi slot KBM yang masih ada
     * untuk kombinasi shift + kategori hari (atau jam ke-nya sudah tidak ada),
     * reset otomatis ke null ("Tidak Dibatasi" / semua slot aktif).
     *
     * @param  array<string,array<int,int>>  $maxByShiftKategori  peta
     *         [shiftId => ['Senin-Kamis' => maxKBM, 'Jumat' => maxKBM], ...]
     */
    public static function normalizeAgainstMaster(array $maxByShiftKategori = []): int
    {
        $maxByShiftKategori = $maxByShiftKategori ?: static::defaultMaxByShift();

        $fixed = 0;
        static::all()->each(function ($r) use ($maxByShiftKategori, &$fixed) {
            $max = $maxByShiftKategori[$r->shift_id][$r->kategori_hari] ?? $maxByShiftKategori[0][$r->kategori_hari] ?? 0;

            // Reset jika batas melebihi slot KBM yang tersedia, atau jam ke sudah tidak ada di master
            if ($r->max_jam_ke === null) {
                return;
            }
            if ($r->max_jam_ke > $max || $max === 0) {
                $r->update(['max_jam_ke' => null]);
                $fixed++;
            }
        });

        return $fixed;
    }

    /**
     * Peta default [shiftId => ['Senin-Kamis' => max, 'Jumat' => max]] dari
     * seluruh slot KBM yang terdaftar (Global + semua shift yang ada).
     */
    public static function defaultMaxByShift(): array
    {
        $map = [];
        $grouped = JamPelajaran::where('jenis', 'kbm')
            ->whereNotNull('jam_ke')
            ->get()
            ->groupBy(fn ($j) => (string) ($j->shift_id ?? 0));

        foreach ($grouped as $shiftKey => $slots) {
            $maxSeninKamis = $slots->whereIn('hari', ['Senin', 'Selasa', 'Rabu', 'Kamis'])->max('jam_ke') ?? 0;
            $maxJumat = $slots->where('hari', 'Jumat')->max('jam_ke') ?? 0;
            $map[(int) $shiftKey] = [
                'Senin-Kamis' => (int) $maxSeninKamis,
                'Jumat' => (int) $maxJumat,
            ];
        }

        return $map ?: [0 => ['Senin-Kamis' => 0, 'Jumat' => 0]];
    }
}