<?php

namespace App\Models;

use App\Models\Concerns\HasTestingData;
use App\Models\Scopes\TestingDataScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Master Data Tahun Ajaran — memakai isolasi data testing BERBASIS PERAN,
 * konsisten dengan master data lain (Kelas, Guru, Siswa, Jurusan, Mata
 * Pelajaran, Ruangan):
 *
 *  - User operasional biasa (bukan Petugas IT / QA / akun sandbox) hanya
 *    MELIHAT & MENULIS data PRODUKSI (is_testing_data = false) — meskipun
 *    saat ini sedang aktif Tahun Ajaran ber-label testing dari pihak IT.
 *  - Petugas IT / QA Tester / sandbox hanya melihat & menulis partisi testing.
 *
 * Global scope TestingDataScope (via HasTestingData) menegakkan filter baca;
 * model event 'creating' menegakkan flag tulis agar konsisten. Status konteks
 * lingkungan aktif (User::currentTestingStatus) membaca TA aktif TANPA scope,
 * sehingga tetap bekerja apa pun partisi yang sedang dilihat user.
 */
class TahunAjaran extends Model
{
    use HasFactory, HasTestingData, SoftDeletes;

    protected $table = 'tahun_ajaran';

    protected $fillable = [
        'tahun_ajaran',
        'semester',
        'mode_jadwal',
        'is_active',
        'is_testing_data',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_testing_data' => 'boolean',
        ];
    }

    /**
     * Query Tahun Ajaran pada partisi KONTEKS LINGKUNGAN AKTIF (bukan peran user).
     *
     * Halaman sub-sistem PENJADWALAN (Shift / Slot Jam / Plotting Jadwal) memakai
     * helper ini agar konsisten dengan model penjadwalan yang context-aware:
     * saat Tahun Ajaran testing aktif (User::currentTestingStatus() === true),
     * konteks penjadwalan mengikuti partisi testing — apa pun peran user yang
     * login, persis perilaku lama sebelum Tahun Ajaran beralih ke isolasi
     * berbasis peran untuk Data Master.
     */
    public static function forCurrentContext(): Builder
    {
        return static::withoutGlobalScope(TestingDataScope::class)
            ->where('is_testing_data', User::currentTestingStatus());
    }

    // =========================================================================
    // Helpers: Tahun Ajaran Aktif
    // =========================================================================

    /**
     * Query scope: filter hanya baris yang berstatus aktif (`is_active = true`).
     *
     * Penggunaan: TahunAjaran::aktif()->get()  atau  TahunAjaran::aktif()->first()
     */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Kembalikan instance Tahun Ajaran yang sedang aktif, atau null jika belum ada.
     *
     * Penggunaan tunggal di seluruh aplikasi agar perubahan kolom cukup di satu tempat.
     *
     * @return static|null
     */
    public static function aktif(): ?static
    {
        return static::where('is_active', true)->first();
    }

    /**
     * Kembalikan ID Tahun Ajaran aktif, atau null jika belum ada.
     * Berguna untuk menyisipkan ke klausa WHERE tanpa perlu mengambil seluruh model.
     */
    public static function aktifId(): ?int
    {
        $ta = static::aktif();

        return $ta ? (int) $ta->id : null;
    }

    /**
     * Mode penjadwalan eksplisit milik Tahun Ajaran.
     */
    public const MODE_GLOBAL = 'global';

    public const MODE_SHIFT = 'shift';

    /**
     * Aksesor: mode penjadwalan EFEKTIF milik T.A ini.
     *
     * Nilai eksplisit mode_jadwal ('global' | 'shift') menang; bila belum
     * ditentukan (null / TA legacy), mengikuti tipe penjadwalan sistem
     * (AppSetting::scheduleMode, default 'global').
     */
    protected function effectiveScheduleMode(): Attribute
    {
        return Attribute::get(function (): string {
            $mode = $this->mode_jadwal;

            if (in_array($mode, [self::MODE_GLOBAL, self::MODE_SHIFT], true)) {
                return $mode;
            }

            return AppSetting::scheduleMode();
        });
    }

    /**
     * Relasi ke Jadwal Pelajaran
     */
    public function jadwalPelajaran(): HasMany
    {
        return $this->hasMany(JadwalPelajaran::class, 'id_tahun_ajaran', 'id');
    }
}
