<?php

namespace App\Models;

use App\Models\Concerns\HasTestingData;
use App\Models\Scopes\TestingDataScope;
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
    use HasFactory, HasTestingData, Notifiable, SoftDeletes;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_GURU = 'guru';

    public const ROLE_PETUGAS_IT = 'petugas_it';

    public const ROLE_QA_TESTER = 'qa_tester';

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_GURU,
        self::ROLE_PETUGAS_IT,
        self::ROLE_QA_TESTER,
    ];

    /**
     * Kode role yang dapat dipilih oleh Petugas IT / QA Tester pada fitur
     * "Switch View As" (disimpan di session sebagai active_role).
     *
     * 'super_admin' → preview tampilan & otorisasi Super Admin penuh.
     */
    public const PREVIEW_ROLES = [
        'super_admin' => 'Super Admin',
        'admin_tu' => 'Admin TU',
        'satpam' => 'Satpam',
        'waka_kesiswaan' => 'Waka Kesiswaan',
        'waka_kurikulum' => 'Waka Kurikulum',
        'waka_sdm' => 'Waka SDM',
        'waka_piket' => 'Waka Piket',
        'kepsek' => 'Kepala Sekolah',
        'guru_piket' => 'Guru Piket',
        'guru_mapel' => 'Guru Mapel',
        'wali_kelas' => 'Wali Kelas',
    ];

    /**
     * Pemetaan preview role -> (role, sub_role) efektif. Dipakai untuk
     * authorization (Middleware/Gate/Policy) dan navigasi saat aktif mode
     * impersonation tanpa perlu login ulang.
     */
    public const PREVIEW_ROLE_MAP = [
        'super_admin' => ['role' => 'super_admin', 'sub_role' => 'super_admin'],
        'admin_tu' => ['role' => 'admin',     'sub_role' => 'petugas_tu'],
        'satpam' => ['role' => 'admin',     'sub_role' => 'satpam'],
        'waka_kesiswaan' => ['role' => 'admin',     'sub_role' => 'waka_kesiswaan'],
        'waka_kurikulum' => ['role' => 'admin',     'sub_role' => 'waka_kurikulum'],
        'waka_sdm' => ['role' => 'admin',     'sub_role' => 'waka_sdm'],
        'waka_piket' => ['role' => 'admin',     'sub_role' => 'waka_piket'],
        'kepsek' => ['role' => 'admin',     'sub_role' => 'kepsek'],
        'guru_piket' => ['role' => 'guru',      'sub_role' => 'guru'],
        'guru_mapel' => ['role' => 'guru',      'sub_role' => 'guru_mapel'],
        'wali_kelas' => ['role' => 'guru',      'sub_role' => 'wali_kelas'],
    ];

    public const ADMIN_SUB_ROLES = [
        'waka_kesiswaan',
        'waka_kurikulum',
        'waka_sdm',
        'waka_piket',
        'kepsek',
        'kepala_sekolah',
        'petugas_tu',
        'satpam',
    ];

    public const GURU_SUB_ROLES = [
        'guru',
    ];

    /**
     * Sub-role yang menandai akun istimewa (Super Admin / Admin) yang DIHINDARI
     * dari pengelolaan Petugas TU biasa. Hanya privilege manager (Petugas IT /
     * Super Admin / Admin Utama) yang boleh melihat aksi & memanipulasi akun ini.
     */
    public const PROTECTED_SUB_ROLES = ['super_admin', 'admin'];

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
        'suspended_until',
        'role',
        'sub_role',
        'kelas_id',
        'is_testing_data',
        'last_active_at',
        'is_idle',
        'current_session_id',
        'last_security_alert_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'suspended_until' => 'datetime',
        'password' => 'hashed',
        'is_testing_data' => 'boolean',
        'is_idle' => 'boolean',
        'last_active_at' => 'datetime',
        'last_security_alert_at' => 'datetime',
        'is_emergency_takeover' => 'boolean',
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
            ->using(PengurusRuangan::class)
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
     * Daftar semua user yang berjabatan Waka Piket (sub-role 'waka_piket'),
     * urut nama. Waka Piket merupakan garda verifikasi tahap "Menunggu Piket"
     * bersama Guru Piket yang bertugas; ikut menerima broadcast WA quick-approve.
     */
    public static function wakaPiketUsers(): Collection
    {
        return static::query()
            ->where('role', static::ROLE_ADMIN)
            ->where('sub_role', 'waka_piket')
            ->where('is_active', true)
            ->orderBy('nama')
            ->get();
    }

    /**
     * Apakah user ini adalah Waka Piket (role admin + sub_role waka_piket)?
     */
    public function isWakaPiket(): bool
    {
        return $this->role === static::ROLE_ADMIN && $this->sub_role === 'waka_piket';
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
     * Apakah user ini adalah Super Admin?
     *
     * Super Admin dikenali dari role 'super_admin' ATAU sub_role 'super_admin'.
     * Akun ini memiliki akses penuh ke seluruh route admin (Data Master dsb.)
     * tanpa terkecuali — Middleware, Gate, maupun Policy mengizinkannya.
     */
    public function isSuperAdmin(): bool
    {
        if ($this->role === self::ROLE_SUPER_ADMIN || $this->sub_role === self::ROLE_SUPER_ADMIN) {
            return true;
        }

        // Preview "Switch View As" (Petugas IT / QA Tester memilih role
        // Super Admin): selama mode impersonasi ini aktif, sesi diperlakukan
        // sebagai Super Admin PENUH — seluruh otorisasi (termasuk Zona
        // Berbahaya / reset massal, portal Waka, dan bypass Gate) terbuka,
        // konsisten dengan Gate::before global di AppServiceProvider.
        return $this->hasActiveRole() && $this->activeRole() === self::ROLE_SUPER_ADMIN;
    }

    /**
     * Apakah akun ini merupakan akun istimewa (Super Admin / Admin) yang
     * dilindungi dari pengelolaan Petugas TU biasa?
     *
     * Akun dilindungi bila role-nya 'super_admin' ATAU sub_role-nya bernilai
     * 'super_admin' / 'admin' (hak akses setara administrator sistem).
     * Akun ini tidak dapat diubah, dihapus, atau di-suspend oleh user biasa.
     */
    public function isProtectedAccount(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN
            || in_array($this->sub_role, self::PROTECTED_SUB_ROLES, true);
    }

    /**
     * Apakah akun pengguna (actor) saat ini termasuk "Privilege Manager User"
     * yang berhak mengelola akun Super Admin / Admin?
     *
     * Privilege manager = Petugas IT / QA Tester (pengendali penuh sistem),
     * Super Admin literal (role/sub_role 'super_admin'), ataupun Admin Utama
     * legacy (role 'admin' + sub_role null — konsep Super Admin awal aplikasi).
     * Petugas TU / Waka* maupun admin terspesialisasi BUKAN privilege manager.
     */
    public function isPrivilegedUserManager(): bool
    {
        return $this->isPetugasIt()
            || $this->isSuperAdmin()
            || ($this->role === 'admin' && $this->sub_role === null);
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
     *
     * Petugas IT dikenali dari role 'petugas_it' / 'qa_tester' ATAU
     * sub_role 'petugas_it' / 'qa_tester'. Bentuk sub_role (role 'admin' +
     * sub_role IT) dipakai karena kolom role di MySQL production
     * ber-ENUM('admin','guru'), sehingga identitas IT dibawa oleh sub_role —
     * termasuk hasil restorasi demoteSelf (ItEmergencyController) yang
     * mengembalikan akun takeover ke role 'admin' + sub_role IT.
     */
    public function isPetugasIt(): bool
    {
        return in_array($this->role, [self::ROLE_PETUGAS_IT, self::ROLE_QA_TESTER], true)
            || in_array($this->sub_role, [self::ROLE_PETUGAS_IT, self::ROLE_QA_TESTER], true);
    }

    /**
     * Daftar akun Super Admin LAIN (selain $actor) untuk modal "Emergency
     * Super Admin Takeover" ("Kartu As") di topbar.
     *
     * Query TANPA global scope testing agar mencakup akun super admin partisi
     * real (produksi) yang mungkin dicurigai dibobol — aktor IT sendiri terlihat
     * hanya dari partisi testing, sehingga scope default tidak akan menemukan
     * akun produksi tersebut.
     */
    public static function otherSuperAdminsExcluding(User $actor, array $columns = ['id', 'nama', 'username']): Collection
    {
        return static::withoutGlobalScope(TestingDataScope::class)
            ->where('id', '!=', $actor->id)
            ->where(function ($query) {
                $query->where('role', static::ROLE_SUPER_ADMIN)
                    ->orWhere('sub_role', static::ROLE_SUPER_ADMIN);
            })
            ->orderBy('nama')
            ->get($columns);
    }

    /**
     * Apakah akun ini merupakan hasil Emergency Super Admin Takeover
     * ("Kartu As") — di-promosikan menjadi Super Admin permanen dari akun
     * Petugas IT / QA Tester saat akun Super Admin utama dibobol / terkunci?
     *
     * Penanda permanen ini membuka gembok pengelolaan akun Utama 'admin'
     * (UserController::isEmergencyPrimaryAdminOverride) sehingga penyadap
     * akun utama dapat dikeluarkan seketika dalam situasi darurat.
     */
    public function isEmergencyTakeover(): bool
    {
        return (bool) $this->is_emergency_takeover;
    }

    /**
     * Apakah akun boleh mengakses sistem saat Maintenance Mode aktif?
     *
     * Prioritas izin (sumber kebenaran tunggal untuk middleware
     * CheckMaintenanceMode, gate login AuthController, dan halaman maintenance):
     *  1. Petugas IT / QA Tester (role atau sub_role), termasuk saat
     *     impersonasi "Switch View As" dan akun sandbox (isTestingUser).
     *  2. Super Admin (role literal 'super_admin' ATAU sub_role 'super_admin') —
     *     termasuk akun hasil Emergency Takeover ("Kartu As") yang ber-role
     *     'admin' + sub_role 'super_admin' agar pengendali darurat tidak
     *     terkunci keluar dari sistem saat maintenance.
     *  3. Role 'admin' (admin-area: TU, waka, satpam, kepsek, dsb.).
     *  4. Akun IT khusus dengan username 'petugas.it'.
     */
    public function canBypassMaintenance(): bool
    {
        if ($this->isTestingUser()) {
            return true;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->role === self::ROLE_ADMIN || $this->username === 'petugas.it';
    }

    /**
     * Apakah akun termasuk "dunia IT / pengendali darurat" sehingga berhak
     * melihat tombol pemulihan "Login / Restore Mode IT" pada halaman
     * Maintenance (errors.maintenance)?
     */
    public function isItOriginatedAccount(): bool
    {
        return $this->isPetugasIt()
            || $this->isSuperAdmin()
            || $this->isEmergencyTakeover()
            || $this->username === 'petugas.it'
            || (bool) $this->is_testing_data;
    }

    /**
     * Apakah akun sedang diblokir sementara (Suspend Darurat berbasis waktu)?
     *
     * TRUE selama suspended_until masih di masa depan — login & sesi berjalan
     * ditolak sampai waktu tersebut tercapai (lalu otomatis kembali normal).
     */
    public function isCurrentlySuspended(): bool
    {
        return $this->suspended_until !== null
            && now()->lessThan($this->suspended_until);
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
     *
     * Mencakup skema role-sub-role (role 'admin' + sub_role 'waka_sdm'/'sdm')
     * maupun skema role literal ('waka_sdm', 'admin_sdm', 'sdm'). Definisi ini
     * selaras dengan gate navigasi sidebar ($isWakaSdmRole di layouts/app.blade.php)
     * dan authorizeWakaSdm() pada WakaSdmController — agar menu "Portal Waka SDM"
     * tidak pernah tampil untuk role yang justru ditolak oleh controller.
     */
    public function isWakaSdm(): bool
    {
        return ($this->role === 'admin' && in_array($this->sub_role, ['waka_sdm', 'sdm'], true))
            || in_array($this->role, ['waka_sdm', 'admin_sdm', 'sdm'], true);
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
     * Apakah user adalah Petugas IT / QA Tester yang sedang menguji (sandbox)?
     * True untuk semua kegiatan Petugas IT / QA Tester, baik mode IT langsung
     * maupun saat impersonasi ("Switch View As"). True pula untuk akun
     * is_testing_data=1 (mis. guru.tester) walau sedang tidak ditumpangi,
     * agar akun sandbox tetap terkunci ke data testing. Menjadi basis isolasi
     * global scope TestingDataScope:
     *  - TRUE  → hanya melihat data testing (is_testing_data = true).
     *  - FALSE → hanya melihat data real (is_testing_data = false).
     */
    public function isTestingUser(): bool
    {
        return $this->isPetugasIt() || $this->hasActiveRole() || (bool) $this->is_testing_data;
    }

    /**
     * Status konteks TESTING saat ini (lingkungan aktif) — sumber kebenaran tunggal
     * untuk isolasi data `is_testing_data`:
     *
     *  - Tahun Ajaran AKTIF ber-label data testing (is_testing_data = 1) => SELURUH
     *    sub-sistem (Shift, Slot Jam, Plotting, agenda, dsb.) mewarisi konteks
     *    testing, apa pun peran user yang sedang masuk;
     *  - ATAU user yang sedang login adalah Petugas IT / QA Tester / sedang
     *    impersonasi "Switch View As" / akun sandbox (is_testing_data = 1).
     *
     * TRUE  → baca & tulis hanya pada partisi testing (is_testing_data = true).
     * FALSE → baca & tulis hanya pada partisi real (is_testing_data = false).
     *
     * Dipakai oleh global scope TestingDataScope (filter baca) dan trait
     * HasTestingData (flag tulis saat create) agar tidak terjadi mismatch:
     * data yang baru dibuat dalam konteks testing langsung terlihat di UI.
     */
    public static function currentTestingStatus(): bool
    {
        // Preferensi (1): Tahun Ajaran aktif. Dipakai tanpa global scope agar tidak
        // memicu rekursi dan selalu bisa membaca flag is_testing_data TA itu sendiri.
        $activeTa = TahunAjaran::withoutGlobalScope(TestingDataScope::class)
            ->where('is_active', true)
            ->latest('id')
            ->first();

        if ($activeTa && (bool) $activeTa->is_testing_data) {
            return true;
        }

        // Preferensi (2): session user (Petugas IT / QA Tester / impersonasi / sandbox).
        $user = auth()->user();

        return $user instanceof self && $user->isTestingUser();
    }

    /**
     * Mode pandang data testing yang disimpan di sesi ('all' / 'real' / 'testing').
     *
     * Catatan: global scope TestingDataScope kini mengisolasi data secara ketat
     * (Petugas IT / QA hanya melihat data testing). Method ini dipertahankan
     * sebagai preferensi sesi / kompatibilitas API dan tidak lagi memengaruhi
     * query pada scope global.
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
                'waka_piket' => 'Waka Piket',
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
     * Kode Aktivasi dalam bentuk tersensor (masked) untuk tampilan UI:
     * mis. 'AKT-XXXXXXXX' → 'AKT-••••-XX'. Nilai asli tidak pernah bocor ke
     * layar daftar user; hanya Petugas IT yang boleh melihatnya utuh di form.
     */
    public function getKodeAktivasiMaskedAttribute(): string
    {
        $code = (string) ($this->kode_aktivasi ?? '');
        if ($code === '') {
            return '-';
        }
        if (strlen($code) <= 4) {
            return str_repeat('•', strlen($code));
        }

        return substr($code, 0, 3).'-••••-'.substr($code, -2);
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

    /**
     * Task dinamis Koordinator Piket: daftar shift ('pagi' / 'siang') yang
     * dipimpin user ini pada hari berjalan, berdasarkan jadwal piket (bukan
     * role tetap di database).
     *
     * Mengembalikan array kosong bila tidak bertugas sebagai koordinator hari
     * ini atau di luar hari aktif sekolah. Menghormati TestingDataScope.
     *
     * @return array<int, string> contoh: ['pagi'], ['siang'], ['pagi', 'siang']
     */
    public function koordinatorShiftHariIni(): array
    {
        $hari = $this->hariPiketHariIni();

        if ($hari === null) {
            return [];
        }

        $shifts = [];

        if (JadwalPiket::where('hari', $hari)->where('koordinator_pagi_user_id', $this->id)->exists()) {
            $shifts[] = 'pagi';
        }

        if (JadwalPiket::where('hari', $hari)->where('koordinator_siang_user_id', $this->id)->exists()) {
            $shifts[] = 'siang';
        }

        return $shifts;
    }
}
