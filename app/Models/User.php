<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_GURU  = 'guru';

    public const ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_GURU,
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

    // ===== Helper Methods =====

    /**
     * Apakah user ini adalah admin (role = 'admin')?
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
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
        ];

        $subRoleKey = $this->sub_role ?? '';
        return $labels[$this->role][$subRoleKey] ?? ucfirst(str_replace('_', ' ', $this->role ?? ''));
    }

    /**
    * Cek apakah guru mendapat penugasan piket pada hari ini.
     */
    public function isPiketHariIni(): bool
    {
        if ($this->role !== self::ROLE_GURU) {
            return false;
        }

        $map = [
            'Monday'    => 'Senin',
            'Tuesday'   => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday'  => 'Kamis',
            'Friday'    => 'Jumat',
            'Saturday'  => 'Sabtu',
            'Sunday'    => 'Minggu',
        ];

        $hari = $map[now()->dayName] ?? now()->locale('id')->translatedFormat('l');

        return $this->jadwalPiket()->where('hari', $hari)->exists();
    }
}
