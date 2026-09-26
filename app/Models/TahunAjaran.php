<?php

namespace App\Models;

use App\Models\Concerns\HasTestingData;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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
