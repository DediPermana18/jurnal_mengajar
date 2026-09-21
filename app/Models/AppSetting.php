<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}