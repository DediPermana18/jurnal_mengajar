<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JamPulang extends Model
{
    use HasFactory;

    protected $table = 'jam_pulang';

    protected $fillable = [
        'kategori_hari',
        'tingkat',
        'max_jam_ke',
    ];

    protected $casts = [
        'max_jam_ke' => 'integer',
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
    public static function getAllAsLookup(): \Illuminate\Support\Collection
    {
        return static::all()->keyBy(fn ($r) => "{$r->kategori_hari}|{$r->tingkat}");
    }
}
