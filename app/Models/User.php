<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    public const ROLE_ADMIN       = 'admin';
    public const ROLE_GURU        = 'guru';
    public const ROLE_PETUGAS_IT  = 'petugas_it';

    public const ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_GURU,
        self::ROLE_PETUGAS_IT,
    ];

    /**
     * Kode role yang dapat dipilih oleh Petugas IT pada fitur "Switch View As".
     */
    public const PREVIEW_ROLES = [
        'admin_tu'      => 'Admin TU',
        'waka_kurikulum' => 'Waka Kurikulum',
        'guru_piket'    => 'Guru Piket',
        'guru_mapel'    => 'Guru Mapel',
        'siswa'         => 'Siswa',
    ];

    public const ADMIN_SUB_ROLES = [
        'waka_kurikulum',
        'waka_sdm',
        'petugas_tu',
        'satpam',
    ];

    public const GURU_SUB_ROLES = [
        'guru',
    ];

    protected $table = 'users';

    protected $fillable = [
        'nama',
        'nip',
        'username',
        'email',
        'no_hp',
        'foto_profil',
        'password',
        'kode_aktivasi',
        'is_active',
        'role',
        'sub_role',
        'kelas_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'password' => 'hashed',
    ];

    protected $hidden = [
        'password',
        'kode_aktivasi',
        'remember_token',
    ];

    /**
     * Relasi ke Kelas jika user di-assign kelas_id langsung
     */
    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id', 'id');
    }

    /**
     * Relasi ke Kelas sebagai Wali Kelas (via id_wali_kelas di tabel kelas)
     */
    public function kelasWali(): HasMany
    {
        return $this->hasMany(Kelas::class, 'id_wali_kelas', 'id');
    }

    /**
     * Relasi ke Jadwal Pelajaran sebagai Guru Pengajar
     */
    public function jadwalPelajaran(): HasMany
    {
        return $this->hasMany(JadwalPelajaran::class, 'id_guru', 'id');
    }

    /**
     * Relasi ke Jadwal Piket Guru
     */
    public function jadwalPiket(): HasMany
    {
        return $this->hasMany(JadwalPiket::class, 'user_id');
    }

    /**
     * Ruangan yang dikelola oleh user ini
     */
    public function ruanganDikelola(): BelongsToMany
    {
        return $this->belongsToMany(Ruangan::class, 'pengurus_ruangan', 'user_id', 'ruangan_id')
            ->withTimestamps();
    }

    // ===== Helper Methods =====

    /**
     * Nomor HP dalam format internasional tanpa awalan 0 (mis. 628123456789),
     * siap digunakan untuk tautan wa.me. Kosong jika user tidak punya nomor.
     */
    public function noHpInternasional(): string
    {
        $no = preg_replace('/[^0-9]/', '', trim((string) ($this->no_hp ?? '')));
        if ($no === '') {
            return '';
        }
        if (str_starts_with($no, '0')) {
            $no = '62' . substr($no, 1);
        }
        return $no;
    }

    /**
     * Normalisasi nomor HP untuk disimpan: buang karakter non-digit dan
     * ubah awalan 0 menjadi 62 (format internasional negara Indonesia).
     */
    public function normalizeNoHp(?string $noHp = ''): ?string
    {
        $no = preg_replace('/[^0-9]/', '', trim((string) $noHp));
        if ($no === '') {
            return null;
        }
        if (str_starts_with($no, '0')) {
            $no = '62' . substr($no, 1);
        }
        return $no;
    }

    /**
     * User yang bertindak sebagai Waka Kesiswaan / penanggung jawab persetujuan
     * dispensasi. Diidentifikasi dari nomor HP yang terisi di database:
     * prioritas user dengan sub_role 'waka_kesiswaan', lalu admin (role 'admin').
     */
    public static function wakaKesiswaan(): ?User
    {
        return static::query()
            ->whereNotNull('no_hp')
            ->where('no_hp', '!=', '')
            ->orderByRaw("CASE WHEN sub_role = 'waka_kesiswaan' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->first();
    }

    /**
     * Apakah user ini adalah admin (role = 'admin')?
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Apakah user ini adalah Petugas IT / QA Tester?
     */
    public function isPetugasIt(): bool
    {
        return $this->role === self::ROLE_PETUGAS_IT;
    }

    /**
     * Apakah user ini adalah Satpam / Petugas Keamanan?
     * Diidentifikasi dari role 'admin' + sub_role 'satpam' (skema baru)
     * atau role lama 'piket_satpam'.
     */
    public function isSatpam(): bool
    {
        return ($this->role === 'admin' && $this->sub_role === 'satpam')
            || $this->role === 'piket_satpam';
    }

    /**
     * Apakah user sedang dalam mode preview "Switch View As"?
     * Hanya berlaku untuk pengguna Petugas IT yang memilih role preview.
     */
    public function hasPreviewRole(): bool
    {
        return $this->isPetugasIt() && !empty(session('preview_role'));
    }

    /**
     * Role preview aktif (mis. 'admin_tu', 'guru_mapel', ...) atau null jika tidak preview.
     */
    public function previewRole(): ?string
    {
        if (!$this->hasPreviewRole()) {
            return null;
        }
        return session('preview_role');
    }

    /**
     * Apakah user ini adalah guru (role = 'guru')?
     */
    public function isGuru(): bool
    {
        return $this->role === 'guru';
    }

    /**
     * Apakah user ini adalah Wali Kelas?
     */
    public function isWaliKelas(): bool
    {
        if ($this->role === 'guru' && $this->sub_role === 'wali_kelas') {
            return true;
        }
        if (!empty($this->kelas_id)) {
            return true;
        }
        return $this->kelasWali()->exists();
    }

    /**
     * Display-friendly role label
     */
    public function getRoleLabelAttribute(): string
    {
        $labels = [
            'admin' => [
                ''                 => 'Admin',
                'waka_kurikulum'   => 'Waka Kurikulum',
                'waka_sdm'         => 'Waka SDM',
                'petugas_tu'       => 'Petugas TU',
                'satpam'           => 'Satpam',
            ],
            'guru' => [
                ''                 => 'Guru',
                'guru_mapel'       => 'Guru Mapel',
                'wali_kelas'       => 'Wali Kelas',
                'guru'             => 'Guru Mapel',
            ],
            'petugas_it' => [
                '' => 'Petugas IT / QA Tester',
            ],
        ];

        $subRoleKey = $this->sub_role ?? '';
        return $labels[$this->role][$subRoleKey] ?? ucfirst(str_replace('_', ' ', $this->role ?? ''));
    }

    /**
     * Cek apakah guru terdaftar sebagai petugas piket (jadwal hari apa pun).
     */
    public function isTerdaftarPiket(): bool
    {
        return $this->jadwalPiket()->exists();
    }

    /**
     * Nama hari (Indonesia) untuk hari piket aktif (Senin s.d. Jumat).
     * Mengembalikan null di luar hari aktif sekolah (Sabtu/Minggu).
     */
    protected function hariPiketHariIni(): ?string
    {
        $hariMap = [
            Carbon::MONDAY    => 'Senin',
            Carbon::TUESDAY   => 'Selasa',
            Carbon::WEDNESDAY => 'Rabu',
            Carbon::THURSDAY  => 'Kamis',
            Carbon::FRIDAY    => 'Jumat',
        ];

        return $hariMap[now()->dayOfWeek] ?? null;
    }

    /**
     * Cek apakah guru mendapat penugasan piket pada hari ini.
     * Hanya berlaku pada hari aktif sekolah (Senin s.d. Jumat) dan hanya jika
     * namanya terdaftar pada jadwal_piket untuk hari tersebut.
     */
    public function isPiketHariIni(): bool
    {
        if ($this->role !== self::ROLE_GURU) {
            return false;
        }

        $hari = $this->hariPiketHariIni();

        if ($hari === null) {
            return false;
        }

        return $this->jadwalPiket()->where('hari', $hari)->exists();
    }

    /**
     * Cek apakah user adalah Petugas Piket hari ini (tanpa cek role di DB).
     * Hanya berlaku pada hari aktif sekolah (Senin s.d. Jumat) dan hanya jika
     * namanya terdaftar pada jadwal_piket untuk hari tersebut.
     */
    public function isPetugasPiketHariIni(): bool
    {
        $hari = $this->hariPiketHariIni();

        if ($hari === null) {
            return false;
        }

        return $this->jadwalPiket()->where('hari', $hari)->exists();
    }
}
