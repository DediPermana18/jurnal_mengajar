<?php

namespace App\Models;

use App\Models\Concerns\HasTestingData;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class JadwalPiket extends Model
{
    use HasFactory, HasTestingData;

    protected $table = 'jadwal_piket';

    public const HARI_LIST = [
        'Senin',
        'Selasa',
        'Rabu',
        'Kamis',
        'Jumat',
    ];

    protected $fillable = [
        'user_id',
        'shift_id',
        'minggu_ke',
        'bulan',
        'tahun',
        'waka_user_id',
        'koordinator_pagi_user_id',
        'petugas_pagi_user_id',
        'koordinator_siang_user_id',
        'petugas_siang_user_id',
        'hari',
    ];

    protected $casts = [
        'is_testing_data' => 'boolean',
        'minggu_ke' => 'integer',
        'bulan' => 'integer',
        'tahun' => 'integer',
    ];

    /**
     * Guru Piket (role sementara berbasis jadwal) yang bertugas pada tanggal
     * tertentu — default hari ini (`today()`).
     *
     * Pengambilan dari tabel `jadwal_piket` via kolom `hari` (Senin s.d. Jumat)
     * + `user_id`, konsisten dengan User::isPiketHariIni(). Menghormati
     * TestingDataScope (data testing/real terisolasi sesuai konteks aktif).
     *
     * @return Collection<int, User>
     */
    public static function getGuruPiketHariIni(?Carbon $tanggal = null): Collection
    {
        $tanggal = $tanggal ?? today();
        $hari = static::namaHariTanggal($tanggal);

        if ($hari === null) {
            return collect();
        }

        $ids = static::where('hari', $hari)
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->values()
            ->map(fn ($id) => (int) $id);

        if ($ids->isEmpty()) {
            return collect();
        }

        return User::whereIn('id', $ids)->orderBy('nama')->get();
    }

    /**
     * Koordinator Piket (Pagi & Siang) yang tercantum pada jadwal tanggal
     * tertentu — dari kolom `koordinator_pagi_user_id` & `koordinator_siang_user_id`.
     *
     * Melengkapi getGuruPiketHariIni(): memastikan nomor WA Koordinator Piket
     * ikut menerima notifikasi pengajuan izin (tahap approval) meskipun baris
     * jadwalnya tidak mengisi `user_id` atas nama koordinator tersebut.
     * Menghormati TestingDataScope (data testing/real terisolasi).
     *
     * @return Collection<int, User>
     */
    public static function koordinatorPiketBertugasTanggal(?Carbon $tanggal = null): Collection
    {
        $tanggal = $tanggal ?? today();
        $hari = static::namaHariTanggal($tanggal);

        if ($hari === null) {
            return collect();
        }

        $ids = static::where('hari', $hari)
            ->pluck('koordinator_pagi_user_id')
            ->merge(static::where('hari', $hari)->pluck('koordinator_siang_user_id'))
            ->filter()
            ->unique()
            ->values()
            ->map(fn ($id) => (int) $id);

        if ($ids->isEmpty()) {
            return collect();
        }

        return User::whereIn('id', $ids)->orderBy('nama')->get();
    }

    /**
     * Shift (Pagi/Siang) yang dipimpin seorang user pada tanggal tertentu,
     * berdasarkan kolom `koordinator_pagi_user_id` / `koordinator_siang_user_id`.
     *
     * Mengembalikan array berisi ['pagi' => bool, 'siang' => bool]. Di luar hari
     * aktif sekolah (Sabtu/Minggu) kedua flag selalu false. Menghormati
     * TestingDataScope (data testing/real terisolasi sesuai konteks aktif).
     *
     * @return array{pagi: bool, siang: bool}
     */
    public static function koordinatorShiftBertugas(Carbon|string|null $tanggal, int $userId): array
    {
        $hari = static::namaHariTanggal(static::parseTanggal($tanggal));

        if ($hari === null) {
            return ['pagi' => false, 'siang' => false];
        }

        return [
            'pagi' => static::where('hari', $hari)
                ->where('koordinator_pagi_user_id', $userId)
                ->exists(),
            'siang' => static::where('hari', $hari)
                ->where('koordinator_siang_user_id', $userId)
                ->exists(),
        ];
    }

    /**
     * Daftar Petugas Piket (anggota) sebuah shift pada tanggal tertentu.
     *
     * Menghimpun dari dua sumber agar kompatibel dengan kedua format jadwal:
     * 1. Format SK (legacy): kolom `petugas_pagi_user_id` / `petugas_siang_user_id`.
     * 2. Format shift dinamis: baris ber-`shift_id` yang namanya diawali Pagi/Siang.
     *
     * Koordinator itu sendiri dikecualikan (bukan anggota biasa). Menghormati
     * TestingDataScope.
     *
     * @return Collection<int, User>
     */
    public static function petugasShiftBertugas(Carbon|string|null $tanggal, string $shift): Collection
    {
        $hari = static::namaHariTanggal(static::parseTanggal($tanggal));

        if ($hari === null) {
            return collect();
        }

        $kolomPetugas = $shift === 'pagi' ? 'petugas_pagi_user_id' : 'petugas_siang_user_id';

        $ids = static::where('hari', $hari)
            ->pluck($kolomPetugas)
            ->filter()
            ->unique()
            ->values()
            ->map(fn ($id) => (int) $id);

        // Format shift dinamis: shift "Pagi"/"Siang" dari master ShiftPiket.
        $shiftId = ShiftPiket::where('is_active', true)
            ->whereRaw('LOWER(nama) LIKE ?', [$shift === 'pagi' ? 'pagi%' : 'siang%'])
            ->orderBy('urutan')
            ->value('id');

        if ($shiftId) {
            $ids = $ids->merge(
                static::where('hari', $hari)
                    ->where('shift_id', $shiftId)
                    ->pluck('user_id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
            );
        }

        $ids = $ids->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return User::whereIn('id', $ids)->orderBy('nama')->get();
    }

    /**
     * Normalisasi input tanggal ke instance Carbon.
     */
    protected static function parseTanggal(Carbon|string|null $tanggal): Carbon
    {
        return $tanggal instanceof Carbon
            ? $tanggal
            : Carbon::parse($tanggal ?: 'now');
    }

    protected static function namaHariTanggal(Carbon $tanggal): ?string
    {
        $hariMap = [
            Carbon::MONDAY => 'Senin',
            Carbon::TUESDAY => 'Selasa',
            Carbon::WEDNESDAY => 'Rabu',
            Carbon::THURSDAY => 'Kamis',
            Carbon::FRIDAY => 'Jumat',
        ];

        return $hariMap[$tanggal->dayOfWeek] ?? null;
    }

    /**
     * Relasi ke User (Guru / Petugas Piket)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(ShiftPiket::class, 'shift_id');
    }

    public function waka(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waka_user_id', 'id');
    }

    public function koordinatorPagi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'koordinator_pagi_user_id', 'id');
    }

    public function petugasPagi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'petugas_pagi_user_id', 'id');
    }

    public function koordinatorSiang(): BelongsTo
    {
        return $this->belongsTo(User::class, 'koordinator_siang_user_id', 'id');
    }

    public function petugasSiang(): BelongsTo
    {
        return $this->belongsTo(User::class, 'petugas_siang_user_id', 'id');
    }
}
