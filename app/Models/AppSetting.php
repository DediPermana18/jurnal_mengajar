<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Penyimpanan pengaturan aplikasi kunci-nilai (key-value) yang dikelola dari
 * UI — mis. API Token Fonnte (WhatsApp Gateway).
 *
 * Bersifat GLOBAL (tanpa partisi testing): pengaturan gateway harus sama untuk
 * seluruh bucket data (QA/IT maupun data real), sejalan dengan pendekatan
 * PengaturanJadwal::maintenanceSetting().
 *
 * Nilai kosong dihapus dari tabel sehingga sistem otomatis fallback ke
 * konfigurasi `.env` (config/services.php).
 */
class AppSetting extends Model
{
    protected $table = 'app_settings';

    protected $fillable = ['key', 'value'];

    /**
     * Tipe penjadwalan sekolah yang berlaku secara sistem.
     */
    public const SCHEDULE_GLOBAL = 'global';

    public const SCHEDULE_SHIFT = 'shift';

    /**
     * Key cache untuk tipe penjadwalan sekolah (bersifat global).
     */
    protected const SCHEDULE_MODE_CACHE_KEY = 'app.schedule_mode';

    /**
     * Ambil nilai sebuah key (atau default bila belum ada).
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $row = static::where('key', $key)->first();

        return $row?->value ?? $default;
    }

    /**
     * Simpan / perbarui nilai key. Nilai kosong menghapus record sehingga
     * sistem kembali menggunakan default (mis. token dari .env).
     */
    public static function set(string $key, ?string $value = null): void
    {
        $value = $value === null ? null : trim($value);

        if ($value === null || $value === '') {
            static::where('key', $key)->delete();

            return;
        }

        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * Tipe penjadwalan sekolah aktif: 'global' (semua kelas memakai slot
     * universal) atau 'shift' (multi-sesi, tiap kelas terikat shift).
     *
     * Bersifat GLOBAL (tidak terpartisi testing) & di-cache sebentar agar
     * pembacaan per-request ringan; default 'global'.
     */
    public static function scheduleMode(): string
    {
        try {
            return (string) Cache::remember(
                self::SCHEDULE_MODE_CACHE_KEY,
                60,
                static function (): string {
                    $mode = (string) (static::get('schedule_mode', self::SCHEDULE_GLOBAL) ?? self::SCHEDULE_GLOBAL);

                    return in_array($mode, [self::SCHEDULE_GLOBAL, self::SCHEDULE_SHIFT], true)
                        ? $mode
                        : self::SCHEDULE_GLOBAL;
                }
            );
        } catch (\Throwable) {
            return self::SCHEDULE_GLOBAL;
        }
    }

    /**
     * Ubah tipe penjadwalan sekolah secara global + segarkan cache.
     */
    public static function setScheduleMode(string $mode): void
    {
        $mode = in_array($mode, [self::SCHEDULE_GLOBAL, self::SCHEDULE_SHIFT], true)
            ? $mode
            : self::SCHEDULE_GLOBAL;

        Cache::forget(self::SCHEDULE_MODE_CACHE_KEY);

        static::set('schedule_mode', $mode);

        Cache::put(self::SCHEDULE_MODE_CACHE_KEY, $mode, 60);
    }
}