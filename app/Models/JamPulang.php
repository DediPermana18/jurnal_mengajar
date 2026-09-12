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
        'kategori_hari',
        'tingkat',
        'max_jam_ke',
    ];

    protected $casts = [
        'max_jam_ke' => 'integer',
        'is_testing' => 'boolean',
    ];

    /**
     * Ambil nilai max_jam_ke untuk kombinasi kategori hari + tingkat kelas.
     * Mengembalikan null jika tidak ada batas (bebas).
     */
    public static function getMaxJamKe(string $kategoriHari, string $tingkat): ?int
    {
        $record = static::where('kategori_hari', $kategoriHari)
            ->where('tingkat', $tingkat)
            ->first();

        return $record?->max_jam_ke;
    }

    /**
     * Ambil semua setting sebagai collection yang di-key oleh "kategori_hari|tingkat"
     * untuk efisiensi lookup di view.
     */
    public static function getAllAsLookup(): Collection
    {
        return static::all()->keyBy(fn ($r) => "{$r->kategori_hari}|{$r->tingkat}");
    }

    /**
     * Normalisasi terhadap master jam pelajaran terkini.
     *
     * Jika nilai max_jam_ke pada jam_pulang melebihi slot KBM yang masih ada
     * (atau jam ke-nya sudah tidak ada di master), reset otomatis ke null
     * ("Tidak Dibatasi" / semua slot aktif). Dipanggil saat render atau saat
     * master jam diubah/dihapus agar tidak terjadi error offset pada dropdown.
     *
     * @param  array<string,int>  $maxByKategori  peta ['Senin-Kamis' => maxKBM, 'Jumat' => maxKBM]
     */
    public static function normalizeAgainstMaster(array $maxByKategori = []): int
    {
        $maxByKategori = $maxByKategori ?: [
            'Senin-Kamis' => JamPelajaran::where('kategori_hari', 'Senin-Kamis')->where('jenis', 'kbm')->whereNotNull('jam_ke')->max('jam_ke') ?? 0,
            'Jumat' => JamPelajaran::where('kategori_hari', 'Jumat')->where('jenis', 'kbm')->whereNotNull('jam_ke')->max('jam_ke') ?? 0,
        ];

        $fixed = 0;
        static::all()->each(function ($r) use ($maxByKategori, &$fixed) {
            $max = $maxByKategori[$r->kategori_hari] ?? 0;

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
}
