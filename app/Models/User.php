<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_GURU = 'guru';

    public const ROLE_PETUGAS_IT = 'petugas_it';

    public const ROLE_QA_TESTER = 'qa_tester';

    public const ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_GURU,
        self::ROLE_PETUGAS_IT,
        self::ROLE_QA_TESTER,
    ];

    /**
     * Kode role yang dapat dipilih oleh Petugas IT / QA Tester pada fitur
     * "Switch View As" (disimpan di session sebagai active_role).
     */
    public const PREVIEW_ROLES = [
        'admin_tu' => 'Admin TU',
        'satpam' => 'Satpam',
        'waka_kesiswaan' => 'Waka Kesiswaan',
        'waka_kurikulum' => 'Waka Kurikulum',
        'waka_sdm' => 'Waka SDM',
        'kepsek' => 'Kepala Sekolah',
        'guru_piket' => 'Guru Piket',
        'guru_mapel' => 'Guru Mapel',
        'siswa' => 'Siswa',
    ];

    /**
     * Pemetaan preview role -> (role, sub_role) efektif. Dipakai untuk
     * authorization (Middleware/Gate/Policy) dan navigasi saat aktif mode
     * impersonation tanpa perlu login ulang.
     */
    public const PREVIEW_ROLE_MAP = [
        'admin_tu' => ['role' => 'admin',     'sub_role' => 'petugas_tu'],
        'satpam' => ['role' => 'admin',     'sub_role' => 'satpam'],
        'waka_kesiswaan' => ['role' => 'admin',     'sub_role' => 'waka_kesiswaan'],
        'waka_kurikulum' => ['role' => 'admin',     'sub_role' => 'waka_kurikulum'],
        'waka_sdm' => ['role' => 'admin',     'sub_role' => 'waka_sdm'],
        'kepsek' => ['role' => 'admin',     'sub_role' => 'kepsek'],
        'guru_piket' => ['role' => 'guru',      'sub_role' => 'guru'],
        'guru_mapel' => ['role' => 'guru',      'sub_role' => 'guru_mapel'],
        'siswa' => ['role' => 'siswa',     'sub_role' => null],
    ];

    public const ADMIN_SUB_ROLES = [
        'waka_kesiswaan',
        'waka_kurikulum',
        'waka_sdm',
        'kepsek',
        'kepala_sekolah',
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
     * Daftar Mata Pelajaran unik yang diampu guru berdasarkan Plotting Jadwal.
     * Menggunakan hasManyThrough: User → JadwalPelajaran → MataPelajaran.
     * Catatan: distinct() tidak didukung langsung pada hasManyThrough;
     * gunakan ->mapelDiampu()->distinct()->get() untuk hasil unik.
     */
    public function mapelDiampu(): HasManyThrough
    {
        return $this->hasManyThrough(
            MataPelajaran::class,
            JadwalPelajaran::class,
            'id_guru',   // FK di jadwal_pelajaran → users.id
            'id',        // FK di mata_pelajaran → jadwal_pelajaran.id_mapel
            'id',        // PK di users
            'id_mapel'   // FK di jadwal_pelajaran → mata_pelajaran.id
        );
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
            $no = '62'.substr($no, 1);
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
            $no = '62'.substr($no, 1);
        }

        return $no;
    }

    /**
     * User yang bertindak sebagai Waka Kesiswaan / penanggung jawab persetujuan
     * dispensasi siswa. Diidentifikasi PERSIS dari user role 'admin' dengan
     * sub_role 'waka_kesiswaan' (bukan heuristik nomor HP / user acak).
     * Mengembalikan null jika belum ada user yang ditunjuk sebagai Waka Kesiswaan.
     */
    public static function wakaKesiswaan(): ?User
    {
        return static::query()
            ->where('role', static::ROLE_ADMIN)
            ->where('sub_role', 'waka_kesiswaan')
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
    }

    /**
     * Apakah user ini adalah Waka Kesiswaan (role admin + sub_role waka_kesiswaan)?
     */
    public function isWakaKesiswaan(): bool
    {
        return $this->role === static::ROLE_ADMIN && $this->sub_role === 'waka_kesiswaan';
    }

    /**
     * Daftar semua user yang berjabatan Waka Kesiswaan (aktif), urut id.
     * Dipakai untuk dropdown "Pilih Waka Kesiswaan" pada halaman approval.
     *
     * @return Collection<int, User>
     */
    public static function wakaKesiswaanList(): Collection
    {
        return static::query()
            ->where('role', static::ROLE_ADMIN)
            ->where('sub_role', 'waka_kesiswaan')
            ->where('is_active', true)
            ->orderBy('id')
            ->get();
    }

    /**
     * Apakah user ini adalah admin (role = 'admin')?
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Apakah user ini adalah Petugas IT / QA Tester (boleh menguji sistem)?
     */
    public function isPetugasIt(): bool
    {
        return in_array($this->role, [self::ROLE_PETUGAS_IT, self::ROLE_QA_TESTER], true);
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
     * Apakah user ini adalah Waka SDM / Kepegawaian?
     */
    public function isWakaSdm(): bool
    {
        return ($this->role === 'admin' && $this->sub_role === 'waka_sdm')
            || $this->role === 'waka_sdm';
    }

    /**
     * Apakah user ini adalah Kepala Sekolah?
     */
    public function isKepsek(): bool
    {
        return in_array($this->role, ['kepsek', 'kepala_sekolah'], true)
            || ($this->role === 'admin' && in_array($this->sub_role, ['kepsek', 'kepala_sekolah', 'kepala_sekolah2'], true));
    }

    /**
     * Apakah user sedang dalam mode impersonation "Switch View As"
     * (active_role diset di session)? Hanya berlaku untuk Petugas IT / QA Tester.
     */
    public function hasActiveRole(): bool
    {
        return $this->isPetugasIt() && ! empty(session('active_role'));
    }

    /**
     * Role impersonasi aktif (mis. 'admin_tu', 'guru_mapel', ...) atau null.
     */
    public function activeRole(): ?string
    {
        if (! $this->hasActiveRole()) {
            return null;
        }

        return session('active_role');
    }

    /**
     * Nama role efektif dari active_role yang sedang dipilih saat impersonation,
     * atau role asli user bila tidak sedang impersonasi.
     */
    public function effectiveRole(): string
    {
        return $this->activeRoleMap()['role'] ?? (string) $this->role;
    }

    /**
     * Sub-role efektif dari active_role yang sedang dipilih, atau sub_role asli.
     */
    public function effectiveSubRole(): ?string
    {
        return $this->activeRoleMap()['sub_role'] ?? $this->sub_role;
    }

    /**
     * Pemetaan [role, sub_role] dari active_role aktif, atau null.
     */
    public function activeRoleMap(): ?array
    {
        $role = $this->activeRole();
        if (! $role || $role === 'siswa') {
            return null;
        }

        return self::PREVIEW_ROLE_MAP[$role] ?? null;
    }

    /**
     * Apakah user Petugas IT sedang menguji (sandbox): membuat data testing?
     * True untuk semua kegiatan Petugas IT / QA Tester, baik mode IT langsung
     * maupun saat impersonasi — hasil inputan akan di-flag is_testing = true.
     */
    public function isTestingUser(): bool
    {
        return $this->isPetugasIt() || $this->hasActiveRole();
    }

    /**
     * Mode pandang data testing pada scope global:
     *  - Petugas IT: 'all' (default, lihat semua), 'real', atau 'testing'.
     *  - Non-IT: selalu hanya melihat data real (is_testing = false).
     */
    public static function testingViewMode(): string
    {
        $mode = session('testing_view', 'all');

        return in_array($mode, ['all', 'real', 'testing'], true) ? $mode : 'all';
    }

    /**
     * @deprecated Gunakan hasActiveRole() — nama lama dipertahankan agar
     *             kode yang masih memakai preview_role tetap berfungsi.
     */
    public function hasPreviewRole(): bool
    {
        return $this->hasActiveRole();
    }

    /**
     * @deprecated Gunakan activeRole() — alias lama untuk "Switch View As".
     */
    public function previewRole(): ?string
    {
        return $this->activeRole();
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
        if (! empty($this->kelas_id)) {
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
                '' => 'Admin',
                'waka_kurikulum' => 'Waka Kurikulum',
                'waka_sdm' => 'Waka SDM',
                'petugas_tu' => 'Petugas TU',
                'satpam' => 'Satpam',
            ],
            'guru' => [
                '' => 'Guru',
                'guru_mapel' => 'Guru Mapel',
                'wali_kelas' => 'Wali Kelas',
                'guru' => 'Guru Mapel',
            ],
            'petugas_it' => [
                '' => 'Petugas IT / QA Tester',
            ],
            'qa_tester' => [
                '' => 'QA Tester',
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
            Carbon::MONDAY => 'Senin',
            Carbon::TUESDAY => 'Selasa',
            Carbon::WEDNESDAY => 'Rabu',
            Carbon::THURSDAY => 'Kamis',
            Carbon::FRIDAY => 'Jumat',
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
