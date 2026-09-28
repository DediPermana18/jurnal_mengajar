<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SecurityAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public const SUB_ROLES = [
        'super_admin',
        'admin',
        'petugas_tu',
        'waka_kurikulum',
        'waka_sdm',
        'waka_piket',
        'koordinator_piket',
        'satpam',
        'waka_kesiswaan',
        'kepsek',
    ];

    public const SUB_ROLE_LABELS = [
        'super_admin' => 'Super Admin',
        'admin' => 'Admin',
        'petugas_tu' => 'Petugas TU',
        'waka_kurikulum' => 'Waka Kurikulum',
        'waka_sdm' => 'Waka SDM',
        'waka_piket' => 'Waka Piket',
        'koordinator_piket' => 'Koordinator Piket',
        'satpam' => 'Petugas Keamanan / Satpam',
        'waka_kesiswaan' => 'Waka Kesiswaan',
        'kepsek' => 'Kepala Sekolah',
    ];

    protected function authorizePetugasTU(): void
    {
        abort_unless(
            $this->isAuthorizedAdminArea(),
            403,
            'Akses ditolak. Hanya Petugas TU atau Admin yang dapat mengelola user.'
        );
    }

    /**
     * Apakah aktor saat ini adalah "Privilege Manager User" (Petugas IT/QA,
     * Super Admin literal, atau Admin Utama legacy admin+null) yang berhak
     * melihat & mengelola opsi/akun Super Admin / Admin?
     */
    protected function isPrivilegedManager(): bool
    {
        $actor = Auth::user();

        return $actor instanceof User && $actor->isPrivilegedUserManager();
    }

    /**
     * Daftar sub-role yang boleh DIPILIH pada form Tambah/Edit.
     *
     * Opsi 'super_admin' / 'admin' (akun istimewa) disembunyikan dari aktor
     * non-privilege-manager (Petugas TU biasa) — hanya Super Admin / IT yang
     * menampilkan dan memilihnya.
     */
    protected function subRoleOptions(): array
    {
        $all = self::SUB_ROLE_LABELS;

        if ($this->isPrivilegedManager()) {
            return $all;
        }

        return array_diff_key($all, array_flip(User::PROTECTED_SUB_ROLES));
    }

    /**
     * Kebijakan "Hidden Super Admin": hanya privilege manager yang boleh
     * MEMBERIKAN hak akses 'super_admin' / 'admin'. Petugas TU yang mencoba
     * mengirim sub_role istimewa ditolak dengan AuthorizationException (403).
     */
    protected function authorizePrivilegedSubRole(string $subRole): void
    {
        if (! in_array($subRole, User::PROTECTED_SUB_ROLES, true)) {
            return;
        }

        abort_if(
            ! $this->isPrivilegedManager(),
            403,
            'Hak akses Super Admin / Admin hanya dapat diberikan oleh Super Admin.'
        );
    }

    /**
     * Akun istimewa (role/sub_role 'super_admin' atau sub_role 'admin') TIDAK
     * boleh diubah, dihapus, di-toggle, atau di-suspend oleh aktor non-
     * privilege-manager — meskipun akun tersebut terlihat di daftar user.
     */
    protected function abortIfProtectedAccount(User $user): void
    {
        if (! $user->isProtectedAccount()) {
            return;
        }

        abort_if(
            ! $this->isPrivilegedManager(),
            403,
            'Akun Super Admin / Admin dilindungi dan hanya dapat dikelola oleh Super Admin.'
        );
    }

    public function index(Request $request)
    {
        $this->authorizePetugasTU();

        $query = User::query()
            ->where('role', '!=', User::ROLE_GURU)
            ->orderBy('nama')
            ->orderBy('username');

        // Akun internal IT / System disembunyikan dari Petugas TU.
        $this->applyInternalAccountVisibility($query);

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($userQuery) use ($search) {
                $userQuery->where('nama', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%");
            });
        }

        if ($request->filled('sub_role') && in_array($request->input('sub_role'), self::SUB_ROLES, true)) {
            $query->where('sub_role', $request->input('sub_role'));
        }

        if ($request->filled('status') && $request->status !== 'Semua Status') {
            if ($request->status === 'Aktif' || $request->status === '1') {
                $query->where('is_active', true);
            } elseif ($request->status === 'Tidak Aktif' || $request->status === 'Nonaktif' || $request->status === '0') {
                $query->where('is_active', false);
            }
        }

        $dataUsers = $query->paginate(15)->withQueryString();

        // Daftar akun Super Admin LAIN (selain aktor) untuk modal "Emergency
        // Super Admin Takeover" di topbar — hanya dihitung saat Petugas IT /
        // QA Tester membuka halaman. Query tanpa global scope testing agar
        // mencakup akun super admin partisi real (produksi) yang mungkin
        // dicurigai dibobol.
        $actor = Auth::user();
        $emergencyOtherSuperAdmins = ($actor instanceof User && $actor->isPetugasIt())
            ? User::otherSuperAdminsExcluding($actor)
            : collect();

        return view('admin.users.index', [
            'dataUsers' => $dataUsers,
            // Filter dropdown: tanpa opsi istimewa untuk Petugas TU.
            'subRoles' => $this->subRoleOptions(),
            // Label lengkap untuk badge sub-role tiap baris (termasuk akun
            // super admin/admin yang tetap tampil walau opsi filter disembunyikan).
            'subRoleLabels' => self::SUB_ROLE_LABELS,
            // Daftar Super Admin lain utk modal takeover darurat (IT only).
            'emergencyOtherSuperAdmins' => $emergencyOtherSuperAdmins,
        ]);
    }

    public function create()
    {
        $this->authorizePetugasTU();

        return view('admin.users.create', [
            'subRoles' => $this->subRoleOptions(),
        ]);
    }

    public function edit($id)
    {
        $this->authorizePetugasTU();

        $user = $this->findNonGuruUser($id);
        $actor = Auth::user();
        $isSelf = ($actor instanceof User) && $actor->id === $user->id;

        $isSuperAdmin = ($actor instanceof User) && (
            $actor->isSuperAdmin()
            || $actor->isPetugasIt()
            || $actor->isPrivilegedUserManager()
            || in_array(strtolower((string) $actor->sub_role), ['super_admin', 'super admin'], true)
            || strtolower((string) $actor->role) === 'super_admin'
        );

        // Jika bukan Super Admin dan bukan akun sendiri, form terkunci readonly (mode lihat saja)
        $readonly = ! $isSelf && ! $isSuperAdmin;

        return view('admin.users.edit', [
            'user' => $user,
            'subRoles' => $this->subRoleOptions(),
            'readonly' => $readonly,
            'isProtectedAccount' => $user->isProtectedAccount(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizePetugasTU();

        $validated = $this->validateUser($request, null, true);
        // Kebijakan Hidden Super Admin: non-privilege-manager dilarang
        // membuat akun dengan sub_role 'super_admin' / 'admin'.
        $this->authorizePrivilegedSubRole($validated['sub_role']);
        $kodeAktivasi = ($validated['kode_aktivasi'] ?? null) ?: $this->generateActivationCode();

        // Bersihkan data soft delete yang bentrok (username, nip, kode_aktivasi)
        $this->cleanupTrashedConflicts($validated['username'], $validated['nip'] ?? null, $kodeAktivasi);

        User::create([
            'nama' => $validated['name'],
            'username' => $validated['username'],
            'nip' => $validated['nip'] ?? null,
            'no_hp' => $validated['no_hp'] ?? null,
            'sub_role' => $validated['sub_role'],
            'role' => $this->roleForSubRole($validated['sub_role']),
            'kode_aktivasi' => $kodeAktivasi,
            // Hash eksplisit (cast 'hashed' menjaga agar tidak ter-hash ganda).
            'password' => Hash::make($validated['password']),
            'is_active' => true,
        ]);

        return redirect()->route('admin.users.index')->with('success', 'User berhasil ditambahkan. Password awal sesuai yang diisikan.');
    }

    public function update(Request $request, $id)
    {
        $this->authorizePetugasTU();

        $user = $this->findNonGuruUser($id);
        $actor = Auth::user();
        $isSelf = ($actor instanceof User) && $actor->id === $user->id;

        // Otorisasi: HANYA Super Admin / Petugas IT atau user yang mengedit akun sendiri yang berhak mengubah data user
        $this->authorizeSuperAdminOrSelf($user);

        if (! $isSelf) {
            $this->abortIfProtectedAccount($user);
        }

        $validated = $this->validateUser($request, $user->id);
        // Kebijakan Hidden Super Admin: non-privilege-manager dilarang mengubah
        // sub_role menjadi 'super_admin' / 'admin'.
        $this->authorizePrivilegedSubRole($validated['sub_role']);

        $kodeAktivasi = $validated['kode_aktivasi'] ?? $user->kode_aktivasi;
        // Kode aktivasi tidak pernah bisa diubah lewat form (disabled/masked).
        // Non-IT yang mencoba menyisipkan nilai baru diabaikan → tetap kode lama.
        if (! $actor instanceof User || ! $actor->isPetugasIt()) {
            $kodeAktivasi = $user->kode_aktivasi;
        }

        // Bersihkan data soft delete yang bentrok sebelum update
        $this->cleanupTrashedConflicts($validated['username'], $validated['nip'] ?? null, $kodeAktivasi, $user->id);

        $user->update([
            'nama' => $validated['name'],
            'username' => $validated['username'],
            'nip' => $validated['nip'] ?? null,
            'no_hp' => $validated['no_hp'] ?? null,
            'sub_role' => $validated['sub_role'],
            'role' => $this->roleForSubRole($validated['sub_role']),
            'kode_aktivasi' => $kodeAktivasi,
            // Status aktif: jika mengedit akun sendiri, tetap aktif (tidak bisa menonaktifkan diri sendiri).
            // Saat akun disuspend checkbox dikunci (disabled).
            'is_active' => $isSelf ? true : ($request->has('is_active') ? $request->boolean('is_active') : $user->is_active),
        ]);

        return redirect()->route('admin.users.index')->with('success', 'Data user berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $this->authorizePetugasTU();

        $user = $this->findNonGuruUser($id);
        $this->abortIfCurrentUser($user, 'Anda tidak dapat melakukan tindakan manajemen pada akun Anda sendiri.');
        $this->abortIfNonItMutation();
        $this->abortIfProtectedAccount($user);

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'User berhasil dihapus.');
    }

    protected function validateUser(Request $request, ?int $ignoreId = null, bool $isCreate = false): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'username' => [
                'required',
                'string',
                'max:255',
                Rule::unique('users', 'username')->withoutTrashed()->ignore($ignoreId),
            ],
            'nip' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('users', 'nip')->withoutTrashed()->ignore($ignoreId),
            ],
            'no_hp' => ['nullable', 'string', 'max:20'],
            'sub_role' => ['required', 'in:'.implode(',', self::SUB_ROLES)],
            'kode_aktivasi' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('users', 'kode_aktivasi')->withoutTrashed()->ignore($ignoreId),
            ],
        ];

        // Password hanya dipersyaratkan saat membuat user baru (bukan edit).
        if ($isCreate) {
            $rules['password'] = ['required', 'string', 'min:8', 'confirmed', 'max:255'];
        }

        return $request->validate($rules, [
            'name.required' => 'Nama lengkap wajib diisi.',
            'username.required' => 'Username wajib diisi.',
            'username.unique' => 'Username sudah digunakan.',
            'nip.unique' => 'NIP sudah digunakan.',
            'sub_role.required' => 'Sub-role wajib dipilih.',
            'sub_role.in' => 'Sub-role tidak valid.',
            'kode_aktivasi.unique' => 'Kode aktivasi sudah digunakan.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal harus 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak sama.',
        ]);
    }

    protected function roleForSubRole(string $subRole): string
    {
        return 'admin';
    }

    protected function findNonGuruUser($id): User
    {
        $query = User::where('role', '!=', User::ROLE_GURU);
        // Akun internal IT / System juga tidak terlihat (404) bagi Petugas TU.
        $this->applyInternalAccountVisibility($query);

        return $query->findOrFail($id);
    }

    /**
     * Sembunyikan akun yang tidak boleh dilihat Petugas TU dari daftar/admin:
     *  - Akun internal IT / System (role 'petugas_it' / 'qa_tester',
     *    mis. 'petugas.it' / Rian Hidayat).
     *  - Akun istimewa ber-role/sub_role 'super_admin' atau sub_role 'admin'
     *    (Kebijakan Hidden Super Admin) — hanya Super Admin / IT yang melihatnya.
     *
     * Privilege manager (Petugas IT/QA, Super Admin literal, Admin Utama
     * legacy admin+null) TIDAK dikenai penyembunyian — mereka melihat semua.
     * Berlaku pada daftar user, halaman detail, dan seluruh aksi akun.
     */
    protected function applyInternalAccountVisibility($query): void
    {
        $actor = Auth::user();

        if ($actor instanceof User && $actor->isPrivilegedUserManager()) {
            return;
        }

        $query->where(function ($q) {
            $q->whereNotIn('role', [User::ROLE_PETUGAS_IT, User::ROLE_QA_TESTER, User::ROLE_SUPER_ADMIN])
                // sub_role NULL (akun legacy tanpa sub-role) TETAP tampil;
                // hanya nilai istimewa 'super_admin' / 'admin' yang disembunyikan.
                ->where(function ($subQ) {
                    $subQ->whereNull('sub_role')
                        ->orWhereNotIn('sub_role', User::PROTECTED_SUB_ROLES);
                });
        });
    }

    /**
     * Otorisasi ketat: HANYA Super Admin / Petugas IT yang boleh mengedit data user lain,
     * atau user yang sedang mengedit akunnya sendiri.
     */
    protected function authorizeSuperAdminOrSelf(User $targetUser): void
    {
        $actor = Auth::user();
        if (! $actor instanceof User) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengedit data user.');
        }

        if ($actor->id === $targetUser->id) {
            return;
        }

        $isSuperAdmin = $actor->isSuperAdmin()
            || $actor->isPetugasIt()
            || $actor->isPrivilegedUserManager()
            || in_array(strtolower((string) $actor->sub_role), ['super_admin', 'super admin'], true)
            || strtolower((string) $actor->role) === 'super_admin';

        abort_unless($isSuperAdmin, 403, 'Anda tidak memiliki hak akses untuk mengedit data user.');
    }

    /**
     * Tolak aksi yang menyasar akun user yang sedang login (menonaktifkan,
     * menghapus, atau mengubah role/status diri sendiri). Respon HTTP 403
     * dengan pesan warning yang jelas.
     */
    protected function abortIfCurrentUser(User $user, string $message): void
    {
        abort_if(Auth::id() === $user->id, 403, $message);
    }

    /**
     * Kunci SELURUH perubahan akun user lain (simpan/update, toggle status,
     * hapus) bagi aktor non-IT — kebijakan "Mode Lihat Saja".
     *
     * Petugas TU / Admin (non-IT) hanya boleh:
     *   - membuka halaman detail akun user lain dalam mode READONLY,
     *   - membuat akun baru ([Tambah User]),
     *   - menjalankan [Suspend Darurat] sebagai respons keamanan.
     *
     * Setiap perubahan data/kredensial (termasuk akun legacy ber-role selain
     * admin) hanya dikelola oleh Petugas IT / QA Tester. Berlaku untuk:
     * update, toggle status (nonaktifkan), dan hapus.
     */
    protected function abortIfNonItMutation(): void
    {
        $actor = Auth::user();

        if ($actor instanceof User && ($actor->isPetugasIt() || $actor->isPrivilegedUserManager() || $actor->isSuperAdmin())) {
            return;
        }

        abort(403, 'Mode lihat saja. Perubahan data akun hanya dikelola oleh Super Admin.');
    }

    /**
     * Pastikan akun sasaran suspend TERLIHAT oleh aktor saat ini — konsisten
     * dengan kebijakan visibilitas findNonGuruUser / applyInternalAccountVisibility:
     *  - User ber-role guru tidak dikelola dari panel TU → 404.
     *  - Akun internal IT / System tidak terlihat oleh Petugas TU → 404.
     *  - Akun istimewa (role/sub_role 'super_admin' atau sub_role 'admin')
     *    tidak terlihat oleh user non-superadmin → 404.
     */
    protected function assertSuspendTargetVisible(User $user): void
    {
        abort_if($user->role === User::ROLE_GURU, 404);

        $actor = Auth::user();

        // Privilege manager melihat semua akun (termasuk Super Admin / Admin).
        if ($actor instanceof User && $actor->isPrivilegedUserManager()) {
            return;
        }

        abort_if(
            in_array($user->role, [User::ROLE_PETUGAS_IT, User::ROLE_QA_TESTER, User::ROLE_SUPER_ADMIN], true)
                || in_array($user->sub_role, User::PROTECTED_SUB_ROLES, true),
            404
        );
    }

    /**
     * Akun utama 'admin' (Administrator TU) diproteksi — tidak boleh di-suspend
     * / dinonaktifkan lewat aksi API/form apa pun (403).
     *
     * Gembok ini HANYA dibuka (oleh controller yang sama) ketika situasi darurat
     * pengendalian sistem aktif:
     *   1. Mode Darurat dinyalakan (session emergency_mode — hanya dapat
     *      diaktifkan oleh Petugas IT / QA Tester), ATAU
     *   2. Aktor adalah akun hasil Emergency Super Admin Takeover "Kartu As"
     *      (is_emergency_takeover => true).
     * Dalam dua kondisi itu, akun utama tetap TIDAK bisa di-suspend oleh
     * aktor non-privileged (lihat isEmergencyPrimaryAdminOverride).
     */
    protected function abortIfPrimaryAdmin(User $user): void
    {
        if ($this->isEmergencyPrimaryAdminOverride()) {
            return;
        }

        abort_if(
            strtolower((string) $user->username) === 'admin',
            403,
            'Akun utama admin tidak dapat di-suspend.'
        );
    }

    /**
     * Apakah pembuka gembok suspend akun utama 'admin' aktif untuk aktor saat ini?
     *
     * Pemanggil legitimate = privilege manager (Petugas IT / QA Tester, Super
     * Admin literal, Admin Utama legacy) ATAU Petugas IT — dan salah satu dari:
     *  - session 'emergency_mode' bernilai true (Mode Darurat), ATAU
     *  - akunnya pernah melakukan Emergency Super Admin Takeover
     *    (is_emergency_takeover = true).
     *
     * Pertahanan berlapis: session 'emergency_mode' yang dipalsukan oleh
     * pengguna non-privileged TIDAK memberi efek apa pun — tetap 403.
     */
    protected function isEmergencyPrimaryAdminOverride(): bool
    {
        $actor = Auth::user();

        if (! $actor instanceof User || ! $actor->isPrivilegedUserManager()) {
            return false;
        }

        return (bool) session('emergency_mode', false) || $actor->isEmergencyTakeover();
    }

    public function toggleStatus($id)
    {
        $this->authorizePetugasTU();

        $user = $this->findNonGuruUser($id);
        $this->abortIfCurrentUser($user, 'Anda tidak dapat melakukan tindakan manajemen pada akun Anda sendiri.');
        $this->abortIfNonItMutation();
        $this->abortIfProtectedAccount($user);

        $user->update([
            'is_active' => ! $user->is_active,
        ]);

        $statusLabel = $user->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()->route('admin.users.index')->with('success', "Status akun {$user->nama} berhasil {$statusLabel}.");
    }

    /**
     * Suspend Darurat (sementara berbasis waktu) — endpoint tersendiri, TIDAK
     * lewat route update biasa (tidak mengirim field nama/username/sub-role/password).
     *
     *  - Route: POST /admin/users/{user}/toggle-suspend (route-model binding).
     *  - Mengisi kolom suspended_until: 1 jam (default) atau 1 hari (24 jam).
     *  - Akun otomatis kembali aktif setelah suspended_until tercapai.
     *  - Aksi UNSUSPEND (payload action=unsuspend): menghapus suspended_until
     *    DAN mengembalikan is_active = true (reaktivasi penuh). Dipakai tombol
     *    "Unsuspend / Aktifkan Kembali" pada halaman Edit User.
     *  - Respons: kembali ke halaman asal + flash batas waktu suspend.
     *
     * Batasan keamanan:
     *  - Hanya role TU / Admin (isAuthorizedAdminArea).
     *  - User tidak bisa menyuspend akunnya sendiri (harus rekan lain).
     *  - Akun utama 'admin' tidak bisa di-suspend (diproteksi).
     */
    public function toggleSuspend(Request $request, User $user)
    {
        $this->authorizePetugasTU();

        $this->assertSuspendTargetVisible($user);
        $this->abortIfCurrentUser(
            $user,
            'Anda tidak dapat menonaktifkan akun Anda sendiri. Silakan minta rekan Petugas TU / Admin lain untuk melakukan suspend darurat.'
        );
        $this->abortIfPrimaryAdmin($user);
        $this->abortIfProtectedAccount($user);

        // ==== UNSUSPEND: hapus batas waktu & kembalikan status aktif ====
        if ($request->input('action') === 'unsuspend' || $request->boolean('unsuspend')) {
            $user->update([
                'suspended_until' => null,
                'is_active' => true,
            ]);

            return back()->with(
                'success',
                "Akun {$user->nama} berhasil diaktifkan kembali (unsuspend). Seluruh batas suspend telah dihapus."
            );
        }

        // Durasi suspend: default 1 jam; opsi lain 1 hari (24 jam).
        $duration = (string) $request->input('duration', '1h');
        $suspendedUntil = in_array($duration, ['1d', 'day', '24h'], true)
            ? now()->addDay()
            : now()->addHour();

        $user->update(['suspended_until' => $suspendedUntil]);

        // Mode darurat + target akun utama 'admin': keluarkan SELURUH sesi aktif
        // akun tersebut seketika (termasuk sesi penyadap) + lepas ikatan
        // single-device, sesuai peringatan di UI "seluruh sesi aktif akun ini
        // akan dimatikan". Guard abortIfPrimaryAdmin di atas sudah memastikan
        // kode hanya bisa dicapai dengan override darurat yang sah (Mode Darurat
        // atau akun hasil Emergency Takeover).
        if (strtolower((string) $user->username) === 'admin') {
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->update([
                'current_session_id' => null,
                'is_idle' => false,
                'last_active_at' => null,
            ]);
        }

        return back()->with(
            'success',
            'Akun berhasil disuspend sementara hingga '.$user->suspended_until->format('H:i d/m/Y').'.'
        );
    }

    /**
     * Suspend Darurat (Peer Emergency Suspend) — TU / Admin.
     *
     * Menonaktifkan paksa akun rekan kerja (is_active = false), mengeluarkan
     * SELURUH sesi aktif akun target (tabel sessions + ikatan single-device),
     * lalu mencatat aktivitas ke security_logs (append-only/immutable):
     * "Akun {user_a} dinonaktifkan darurat oleh {user_b}".
     *
     * Batasan keamanan:
     *  - Hanya role TU / Admin (isAuthorizedAdminArea) yang boleh memanggil.
     *  - User TIDAK bisa menonaktifkan akunnya sendiri (harus rekan lain).
     *
     * Endpoint: POST /api/users/{id}/emergency-suspend
     * - Permintaan JSON  → respons JSON { success, message }.
     * - Permintaan web   → redirect kembali ke daftar user + flash success.
     */
    public function emergencySuspend(Request $request, $id)
    {
        $this->authorizePetugasTU();

        $target = $this->findNonGuruUser($id);
        $this->abortIfCurrentUser(
            $target,
            'Anda tidak dapat menonaktifkan akun Anda sendiri. Silakan minta rekan Petugas TU / Admin lain untuk melakukan suspend darurat.'
        );
        // Akun utama 'admin' diproteksi — tidak bisa di-suspend lewat API/form apa pun.
        $this->abortIfPrimaryAdmin($target);
        // Akun Super Admin / Admin juga dilindungi dari Petugas TU biasa.
        $this->abortIfProtectedAccount($target);

        $actor = Auth::user();
        $device = [
            'ip' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
        ];

        $log = app(SecurityAuditService::class)->emergencySuspend($target, $actor, $device);

        Log::info('security:emergency-suspend', [
            'target_user_id' => $target->id,
            'actor_user_id' => $actor->id,
            'security_log_id' => $log->id,
            'ip' => $device['ip'],
        ]);

        $message = "Akun {$target->nama} telah dinonaktifkan darurat. Seluruh sesi aktif akun tersebut (termasuk potensi penyadap) telah dikeluarkan.";

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        return redirect()->route('admin.users.index')->with('success', $message);
    }

    protected function cleanupTrashedConflicts(string $username, ?string $nip = null, ?string $kodeAktivasi = null, ?int $ignoreId = null): void
    {
        $query = User::onlyTrashed()->where(function ($q) use ($username, $nip, $kodeAktivasi) {
            $q->where('username', $username);
            if ($nip) {
                $q->orWhere('nip', $nip);
            }
            if ($kodeAktivasi) {
                $q->orWhere('kode_aktivasi', $kodeAktivasi);
            }
        });

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        $query->forceDelete();
    }

    protected function generateActivationCode(): string
    {
        do {
            $code = 'AKT-'.Str::upper(Str::random(8));
        } while (User::withTrashed()->where('kode_aktivasi', $code)->exists());

        return $code;
    }
}
