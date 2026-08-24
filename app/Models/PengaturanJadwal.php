<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PengaturanJadwal extends Model
{
    use HasFactory;

    protected $table = 'pengaturan_jadwal';

    protected $fillable = [
        'senin_tanpa_upacara',
        'tanggal_eksekusi',
    ];

    protected $casts = [
        'senin_tanpa_upacara' => 'boolean',
        'tanggal_eksekusi'    => 'date',
    ];

    /**
     * Ambil / buat record tunggal pengaturan jadwal.
     * Otomatis menjalankan auto-reset jika tanggal eksekusi sudah lewat.
     */
    public static function getSetting(): static
    {
        $setting = static::firstOrCreate([], [
            'senin_tanpa_upacara' => false,
            'tanggal_eksekusi'    => null,
        ]);

        $setting->checkAutoReset();

        return $setting;
    }

    /**
     * Logika Auto-Reset:
     * Jika hari ini bukan lagi hari Senin atau tanggal hari ini > tanggal_eksekusi,
     * sistem otomatis mereset senin_tanpa_upacara menjadi FALSE.
     */
    public function checkAutoReset(): void
    {
        if (!$this->senin_tanpa_upacara || !$this->tanggal_eksekusi) {
            return;
        }

        $today = Carbon::today();
        $tglEksekusi = Carbon::parse($this->tanggal_eksekusi);

        // Jika tanggal hari ini sudah melepasi tanggal_eksekusi
        if ($today->gt($tglEksekusi)) {
            $this->update([
                'senin_tanpa_upacara' => false,
                'tanggal_eksekusi'    => null,
            ]);
        }
    }

    /**
     * Cek apakah Mode Senin Tanpa Upacara aktif HARI INI.
     */
    public static function isSeninTanpaUpacaraHariIni(): bool
    {
        $setting = static::getSetting();

        if (!$setting->senin_tanpa_upacara || !$setting->tanggal_eksekusi) {
            return false;
        }

        $todayStr = Carbon::today()->toDateString();
        $eksekusiStr = Carbon::parse($setting->tanggal_eksekusi)->toDateString();

        return $todayStr === $eksekusiStr;
    }

    /**
     * Cek apakah Mode Senin Tanpa Upacara aktif untuk tanggal tertentu.
     */
    public static function isSeninTanpaUpacaraAktifForDate(?string $dateString = null): bool
    {
        $setting = static::getSetting();

        if (!$setting->senin_tanpa_upacara || !$setting->tanggal_eksekusi) {
            return false;
        }

        $targetDate = $dateString ? Carbon::parse($dateString)->toDateString() : Carbon::today()->toDateString();
        $eksekusiDate = Carbon::parse($setting->tanggal_eksekusi)->toDateString();

        return $targetDate === $eksekusiDate;
    }
}
