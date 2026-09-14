<?php

namespace App\Models;

use App\Models\Concerns\HasTestingData;
use App\Models\Scopes\TestingDataScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PengaturanJadwal extends Model
{
    use HasFactory, HasTestingData;

    protected $table = 'pengaturan_jadwal';

    protected $fillable = [
        'senin_tanpa_upacara',
        'tanggal_eksekusi',
        'jumat_tanpa_pembiasaan',
        'tanggal_eksekusi_jumat',
        'no_wa_waka',
        'izin_approval_level',
        'no_wa_kepsek',
        'nama_waka_kesiswaan',
        'nip_waka_kesiswaan',
        'maintenance_mode',
    ];

    protected $casts = [
        'senin_tanpa_upacara' => 'boolean',
        'tanggal_eksekusi' => 'date',
        'jumat_tanpa_pembiasaan' => 'boolean',
        'tanggal_eksekusi_jumat' => 'date',
        'maintenance_mode' => 'boolean',
        'is_testing_data' => 'boolean',
    ];

    /**
     * Level alur approval Izin Guru yang diizinkan.
     * 3 = Piket -> Waka -> Kepsek; 2 = Piket -> Kepsek; 1 = Piket -> (final).
     */
    public const IZIN_LEVELS = [1, 2, 3];

    /**
     * Ambil / buat record tunggal pengaturan jadwal.
     * Otomatis menjalankan auto-reset jika tanggal eksekusi sudah lewat.
     */
    public static function getSetting(): static
    {
        // Utamakan baris data real (is_testing_data = false) bila ada baris testing.
        $setting = static::orderBy('is_testing_data')->firstOrCreate([], [
            'senin_tanpa_upacara' => false,
            'tanggal_eksekusi' => null,
            'jumat_tanpa_pembiasaan' => false,
            'tanggal_eksekusi_jumat' => null,
            'no_wa_waka' => null,
            'izin_approval_level' => 3,
            'no_wa_kepsek' => null,
            'nama_waka_kesiswaan' => null,
            'nip_waka_kesiswaan' => null,
        ]);

        $setting->checkAutoReset();

        return $setting;
    }

    /**
     * Key cache untuk status Mode Maintenance (bersifat global).
     */
    protected const MAINTENANCE_CACHE_KEY = 'app.maintenance_mode_active';

    /**
     * Ambil / buat baris setting yang menampung Mode Maintenance.
     *
     * Mode Maintenance bersifat GLOBAL (seluruh sistem), jadi sengaja di-bypass
     * dari TestingDataScope — tidak boleh terpecah antara bucket real vs testing.
     * Diprioritaskan ke baris data real (is_testing_data = false) bila ada.
     */
    public static function maintenanceSetting(): static
    {
        return static::withoutGlobalScope(TestingDataScope::class)
            ->orderBy('is_testing_data')
            ->firstOrCreate([], [
                'maintenance_mode' => false,
                'is_testing_data' => false,
            ]);
    }

    /**
     * Cek apakah Mode Maintenance aktif (global).
     *
     * Prioritas: (1) config 'app.maintenance_mode' bila diset (override env),
     * lalu (2) nilai dari database yang di-cache sebentar agar toggle terasa
     * instan; bila database bermasalah, fallback ke config / false.
     */
    public static function isMaintenanceModeActive(): bool
    {
        $configValue = config('app.maintenance_mode');

        if ($configValue !== null) {
            return (bool) $configValue;
        }

        try {
            return (bool) Cache::remember(
                self::MAINTENANCE_CACHE_KEY,
                60,
                static fn (): bool => (bool) static::maintenanceSetting()->maintenance_mode
            );
        } catch (\Throwable $e) {
            return (bool) config('app.maintenance_mode', false);
        }
    }

    /**
     * Nyalakan / matikan Mode Maintenance (global) + segarkan cache.
     */
    public static function setMaintenanceMode(bool $active): void
    {
        Cache::forget(self::MAINTENANCE_CACHE_KEY);

        static::maintenanceSetting()->update(['maintenance_mode' => $active]);

        Cache::put(self::MAINTENANCE_CACHE_KEY, $active, 60);
    }

    /**
     * Level alur approval Izin Guru aktif (1, 2, atau 3).
     */
    public static function izinApprovalLevel(): int
    {
        $level = (int) (static::getSetting()->izin_approval_level ?? 3);

        return in_array($level, self::IZIN_LEVELS, true) ? $level : 3;
    }

    /**
     * Nomor WA Waka (normalisasi internasional 62xxx) untuk link izin.
     * Prioritas: setting no_wa_waka, lalu fallback nomor user Waka terkait.
     */
    public static function noWaWakaIzin(): string
    {
        $no = trim((string) (static::getSetting()->no_wa_waka ?? ''));
        if ($no === '') {
            $no = trim((string) (User::wakaKesiswaan()?->noHpInternasional() ?? ''));
        }

        return static::normalizeWaNumber($no);
    }

    /**
     * Nomor WA Kepsek (normalisasi internasional 62xxx) untuk link izin.
     * Prioritas: setting no_wa_kepsek, lalu fallback nomor user role admin pertama.
     */
    public static function noWaKepsek(): string
    {
        $no = trim((string) (static::getSetting()->no_wa_kepsek ?? ''));
        if ($no === '') {
            $admin = User::where('role', User::ROLE_ADMIN)
                ->whereNotNull('no_hp')
                ->where('no_hp', '!=', '')
                ->orderBy('id')
                ->first();
            $no = trim((string) ($admin?->noHpInternasional() ?? ''));
        }

        return static::normalizeWaNumber($no);
    }

    /**
     * Normalisasi nomor WA ke format internasional tanpa awalan 0.
     */
    protected static function normalizeWaNumber(?string $no): string
    {
        $no = preg_replace('/[^0-9]/', '', trim((string) $no));
        if ($no !== '' && str_starts_with($no, '0')) {
            $no = '62'.substr($no, 1);
        }

        return $no;
    }

    /**
     * Logika Auto-Reset:
     * Jika tanggal hari ini > tanggal_eksekusi (Senin/Jumat),
     * sistem otomatis mereset status mode khusus menjadi FALSE.
     */
    public function checkAutoReset(): void
    {
        $today = Carbon::today();
        $updates = [];

        // Auto-reset Senin
        if ($this->senin_tanpa_upacara && $this->tanggal_eksekusi) {
            $tglSenin = Carbon::parse($this->tanggal_eksekusi);
            if ($today->gt($tglSenin)) {
                $updates['senin_tanpa_upacara'] = false;
                $updates['tanggal_eksekusi'] = null;
            }
        }

        // Auto-reset Jumat
        if ($this->jumat_tanpa_pembiasaan && $this->tanggal_eksekusi_jumat) {
            $tglJumat = Carbon::parse($this->tanggal_eksekusi_jumat);
            if ($today->gt($tglJumat)) {
                $updates['jumat_tanpa_pembiasaan'] = false;
                $updates['tanggal_eksekusi_jumat'] = null;
            }
        }

        if (! empty($updates)) {
            $this->update($updates);
        }
    }

    /**
     * Cek apakah Mode Senin Tanpa Upacara aktif HARI INI.
     */
    public static function isSeninTanpaUpacaraHariIni(): bool
    {
        $setting = static::getSetting();

        if (! $setting->senin_tanpa_upacara || ! $setting->tanggal_eksekusi) {
            return false;
        }

        $todayStr = Carbon::today()->toDateString();
        $eksekusiStr = Carbon::parse($setting->tanggal_eksekusi)->toDateString();

        return $todayStr === $eksekusiStr;
    }

    /**
     * Cek apakah Mode Jumat Tanpa Pembiasaan aktif HARI INI.
     */
    public static function isJumatTanpaPembiasaanHariIni(): bool
    {
        $setting = static::getSetting();

        if (! $setting->jumat_tanpa_pembiasaan || ! $setting->tanggal_eksekusi_jumat) {
            return false;
        }

        $todayStr = Carbon::today()->toDateString();
        $eksekusiStr = Carbon::parse($setting->tanggal_eksekusi_jumat)->toDateString();

        return $todayStr === $eksekusiStr;
    }

    /**
     * Cek apakah Mode Khusus (Senin atau Jumat) aktif HARI INI.
     */
    public static function isModeKhususHariIni(): bool
    {
        return static::isSeninTanpaUpacaraHariIni() || static::isJumatTanpaPembiasaanHariIni();
    }

    /**
     * Cek apakah Mode Senin Tanpa Upacara aktif untuk tanggal tertentu.
     */
    public static function isSeninTanpaUpacaraAktifForDate(?string $dateString = null): bool
    {
        $setting = static::getSetting();

        if (! $setting->senin_tanpa_upacara || ! $setting->tanggal_eksekusi) {
            return false;
        }

        $targetDate = $dateString ? Carbon::parse($dateString)->toDateString() : Carbon::today()->toDateString();
        $eksekusiDate = Carbon::parse($setting->tanggal_eksekusi)->toDateString();

        return $targetDate === $eksekusiDate;
    }

    /**
     * Cek apakah Mode Jumat Tanpa Pembiasaan aktif untuk tanggal tertentu.
     */
    public static function isJumatTanpaPembiasaanAktifForDate(?string $dateString = null): bool
    {
        $setting = static::getSetting();

        if (! $setting->jumat_tanpa_pembiasaan || ! $setting->tanggal_eksekusi_jumat) {
            return false;
        }

        $targetDate = $dateString ? Carbon::parse($dateString)->toDateString() : Carbon::today()->toDateString();
        $eksekusiDate = Carbon::parse($setting->tanggal_eksekusi_jumat)->toDateString();

        return $targetDate === $eksekusiDate;
    }
}
