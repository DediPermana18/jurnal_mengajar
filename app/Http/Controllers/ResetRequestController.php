<?php

namespace App\Http\Controllers;

use App\Models\ResetRequest;
use App\Models\User;
use App\Notifications\NewResetRequestNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

/**
 * Pengajuan reset kredensial (lupa sandi / lupa kode aktivasi).
 *
 * Alur lengkap:
 *  1. Publik (tanpa login) — GET/POST /lupa-sandi: pemohon mengisi
 *     username/NIP + jenis pengajuan + tanda tangan digital canvas.
 *     Record tersimpan berstatus 'pending'.
 *  2. Admin TU — GET /admin/pengajuan-reset: panel verifikasi tanda tangan.
 *     Setujui -> status 'approved' + reset_token unik (berlaku 24 jam);
 *     Tolak   -> status 'rejected' + admin_note.
 *  3. Publik — GET/POST /reset-credentials/{token}: pemohon membuka tautan
 *     unik (tanpa login) lalu mengisi kredensial baru sesuai
 *     jenis_pengajuan. Token langsung di-invalidate setelah pemakaian.
 *
 * Akses panel dibatasi admin area (Petugas TU) via AdminScheduleAccess pada
 * route. Mutasi data testing hanya boleh dilakukan Petugas IT / QA
 * (authorizeTestingMutation).
 */
class ResetRequestController extends Controller
{
    /**
     * Batasi panel pengajuan reset ke area Admin / Petugas TU (pola
     * UserController::authorizePetugasTU — reusing isAuthorizedAdminArea).
     */
    protected function authorizePetugasTU(): void
    {
        abort_unless(
            $this->isAuthorizedAdminArea(),
            403,
            'Akses ditolak. Hanya Petugas TU atau Admin yang dapat mengelola pengajuan reset.'
        );
    }

    /**
     * TRUE bila string adalah data URI PNG valid (hasil canvas signature pad).
     */
    protected function isValidSignature(string $value): bool
    {
        return (bool) preg_match('/^data:image\/png;base64,[a-z0-9+\/=]+$/i', trim($value));
    }

    /**
     * 1. FORMULIR PUBLIK — lupa sandi / lupa kode aktivasi.
     */
    public function create()
    {
        return view('reset-request.create');
    }

    /**
     * 1b. CEK AKUN (JSON, publik) — dipakai form /lupa-sandi (Alpine.js)
     * setelah user selesai mengetik Username/NIP. Menghormati
     * TestingDataScope: guest hanya dapat menemukan akun data real.
     */
    public function checkAccount(Request $request)
    {
        $validated = $request->validate([
            'login_id' => 'required|string|max:100',
        ]);

        $user = $this->resolveUserByLoginId(trim($validated['login_id']));

        return response()->json([
            'found' => $user !== null,
            'has_kode_aktivasi' => $user !== null && ! blank($user->kode_aktivasi),
        ]);
    }

    /**
     * Temukan akun dari username / NIP. Menghormati TestingDataScope:
     * guest hanya dapat menemukan akun data real.
     */
    protected function resolveUserByLoginId(string $loginId): ?User
    {
        return User::query()
            ->where(fn ($q) => $q->where('username', $loginId)->orWhere('nip', $loginId))
            ->first();
    }

    /**
     * 2. PROSES PENGAJUAN PUBLIK — simpan record berstatus 'pending'.
     *
     * Pemohon diidentifikasi dari username / NIP (dan kode aktivasi lama,
     * bila pemohon hanya ingat kode-nya). Menghormati TestingDataScope:
     * guest hanya dapat menemukan akun data real.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'login_id' => 'required|string|max:100',
            'jenis_pengajuan' => ['required', Rule::in(array_keys(ResetRequest::JENIS_OPTIONS))],
            'tanda_tangan' => 'required|string|max:3000000',
        ], [
            'login_id.required' => 'Username / NIP wajib diisi.',
            'jenis_pengajuan.required' => 'Pilih jenis pengajuan terlebih dahulu.',
            'jenis_pengajuan.in' => 'Jenis pengajuan tidak valid.',
            'tanda_tangan.required' => 'Tanda tangan digital wajib diisi.',
        ]);

        if (! $this->isValidSignature($validated['tanda_tangan'])) {
            return back()
                ->withErrors(['tanda_tangan' => 'Tanda tangan yang dikirim tidak valid. Silakan tanda tangan ulang.'])
                ->withInput();
        }

        $user = $this->resolveUserByLoginId(trim($validated['login_id']));

        if (! $user) {
            return back()
                ->withErrors(['login_id' => 'Akun dengan username / NIP tersebut tidak ditemukan. Hubungi Admin TU bila kesulitan.'])
                ->withInput();
        }

        // Akun yang tidak memakai fitur Kode Aktivasi tidak boleh mengajukan
        // lupa_kode_aktivasi — hanya lupa sandi yang relevan untuknya.
        if ($validated['jenis_pengajuan'] === ResetRequest::JENIS_LUPA_KODE_AKTIVASI
            && blank($user->kode_aktivasi)) {
            return back()
                ->withErrors(['jenis_pengajuan' => 'Akun ini tidak menggunakan fitur Kode Aktivasi. Silakan pilih Lupa Sandi.'])
                ->withInput();
        }

        $reset = ResetRequest::create([
            'user_id' => $user->id,
            'jenis_pengajuan' => $validated['jenis_pengajuan'],
            'tanda_tangan' => trim($validated['tanda_tangan']),
            'status' => ResetRequest::STATUS_PENDING,
        ]);

        // Beri tahu Admin TU / Petugas TU lewat notifikasi database (lonceng).
        $this->notifyPetugasTU($reset);

        return redirect()->route('reset-request.create')
            ->with('success', 'Pengajuan diterima. Admin TU akan memverifikasi tanda tangan Anda dan mengirimkan tautan reset melalui WhatsApp.');
    }

    /**
     * Kirim notifikasi database ke semua Admin TU / Petugas TU yang berhak
     * membuka panel pengajuan reset (pola AdminScheduleAccess). Menghormati
     * TestingDataScope: guest hanya mengirim ke akun admin data real.
     */
    protected function notifyPetugasTU(ResetRequest $reset): void
    {
        $adminUsers = User::query()
            ->where(fn ($q) => $q
                ->where('role', 'admin')
                ->where(fn ($q2) => $q2
                    ->whereNull('sub_role')
                    ->orWhereIn('sub_role', ['petugas_tu', 'admin_tu']))
                ->orWhere('role', 'admin_tu'))
            ->where('is_active', true)
            ->get();

        if ($adminUsers->isNotEmpty()) {
            Notification::send($adminUsers, new NewResetRequestNotification($reset));
        }
    }

    /**
     * 3. PANEL ADMIN TU — daftar pengajuan (pending / approved / rejected).
     */
    public function index(Request $request)
    {
        $this->authorizePetugasTU();

        $status = $request->input('status');
        if ($status !== null && ! in_array($status, ResetRequest::STATUSES, true)) {
            $status = null;
        }

        $daftar = ResetRequest::with('user')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.reset-requests.index', compact('daftar', 'status'));
    }

    /**
     * 3b. COUNTER PENDING (JSON) — dikonsumsi badge live di sidebar via
     * polling Alpine tanpa perlu hard-refresh halaman. Jumlah mengikuti
     * TestingDataScope (Petugas TU melihat data real, IT melihat data testing).
     */
    public function pendingCount()
    {
        $this->authorizePetugasTU();

        return response()->json([
            'count' => ResetRequest::where('status', ResetRequest::STATUS_PENDING)->count(),
        ]);
    }

    /**
     * 4. SETUJUI — buat tautan reset unik (token, berlaku 24 jam).
     */
    public function approve($id)
    {
        $this->authorizePetugasTU();

        $reset = ResetRequest::findOrFail($id);

        // Guard: pengajuan data testing hanya dapat disetujui oleh IT / QA.
        $this->authorizeTestingMutation($reset);

        abort_if(! $reset->isPending(), 422, 'Pengajuan ini sudah diproses dan tidak dapat diubah lagi.');

        $reset->update([
            'status' => ResetRequest::STATUS_APPROVED,
            'reset_token' => ResetRequest::generateToken(),
            'token_expires_at' => now()->addHours(ResetRequest::TOKEN_TTL_HOURS),
            'admin_note' => null,
        ]);

        return back()->with('success', 'Pengajuan disetujui. Tautan reset unik berhasil dibuat (berlaku 24 jam) — salin atau kirim ke WhatsApp pemohon.');
    }

    /**
     * 5. TOLAK — simpan alasan penolakan.
     */
    public function reject(Request $request, $id)
    {
        $this->authorizePetugasTU();

        $reset = ResetRequest::findOrFail($id);

        // Guard: pengajuan data testing hanya dapat ditolak oleh IT / QA.
        $this->authorizeTestingMutation($reset);

        abort_if(! $reset->isPending(), 422, 'Pengajuan ini sudah diproses dan tidak dapat diubah lagi.');

        $validated = $request->validate([
            'admin_note' => 'required|string|max:500',
        ], [
            'admin_note.required' => 'Alasan penolakan wajib diisi.',
            'admin_note.max' => 'Alasan penolakan maksimal :max karakter.',
        ]);

        $reset->update([
            'status' => ResetRequest::STATUS_REJECTED,
            'admin_note' => trim($validated['admin_note']),
        ]);

        return back()->with('success', 'Pengajuan ditolak dan alasan telah dicatat.');
    }

    /**
     * 6. HALAMAN RESET via token unik (tanpa login). Form adaptif:
     * lupa_sandi -> password baru; lupa_kode_aktivasi -> kode baru.
     */
    public function showResetForm($token)
    {
        $reset = ResetRequest::resolveToken($token);

        // Token tidak dikenal / sudah dipakai / kedaluwarsa -> tolak dan
        // kembalikan ke halaman Login publik dengan pesan error.
        if (! $reset || ! $reset->isValidToken()) {
            return redirect()->route('login')
                ->with('error', 'Tautan reset sudah tidak berlaku');
        }

        return view('reset-request.form', ['reset' => $reset]);
    }

    /**
     * 7. PROSES RESET — setelah berhasil, token di-invalidate agar tidak
     * dapat dipakai ulang, sesi login dihapus, dan pengguna diarahkan ke
     * halaman Login publik (status logged out).
     */
    public function submitReset(Request $request, $token)
    {
        $reset = ResetRequest::resolveToken($token);

        // Token tidak dikenal / sudah dipakai / kedaluwarsa -> tolak dan
        // kembalikan ke halaman Login publik dengan pesan error.
        if (! $reset || ! $reset->isValidToken()) {
            return redirect()->route('login')
                ->with('error', 'Tautan reset sudah tidak berlaku');
        }

        // Guard: reset data testing hanya dapat dilakukan oleh IT / QA.
        $this->authorizeTestingMutation($reset);

        $user = $reset->user;

        if ($reset->jenis_pengajuan === ResetRequest::JENIS_LUPA_SANDI) {
            $validated = $request->validate([
                'password' => 'required|string|min:8|confirmed',
            ], [
                'password.required' => 'Password baru wajib diisi.',
                'password.min' => 'Password minimal harus 8 karakter.',
                'password.confirmed' => 'Konfirmasi password tidak sama.',
            ]);

            $user->update([
                'password' => Hash::make($validated['password']),
            ]);
        } else {
            $validated = $request->validate([
                'kode_aktivasi' => [
                    'required',
                    'string',
                    'max:100',
                    Rule::unique('users', 'kode_aktivasi')->withoutTrashed(),
                ],
            ], [
                'kode_aktivasi.required' => 'Kode aktivasi baru wajib diisi.',
                'kode_aktivasi.unique' => 'Kode aktivasi sudah digunakan oleh akun lain.',
            ]);

            $user->update([
                'kode_aktivasi' => trim($validated['kode_aktivasi']),
            ]);
        }

        // Invalidate token — tautan tidak dapat dipakai ulang.
        $reset->update([
            'reset_token' => null,
            'token_expires_at' => null,
        ]);

        // Pastikan TIDAK ADA Auth::login() di alur ini. Pengguna yang membawa
        // sesi aktif (mis. Admin TU sedang mencoba link reset yang disalin)
        // dikeluarkan sesinya, sehingga redirect ke /login benar-benar sampai
        // ke formulir login publik — bukan dialihkan AuthController ke
        // dashboard admin/guru sesuai role.
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('success', 'Kredensial berhasil diperbarui. Silakan login kembali dengan password/kode aktivasi baru Anda.');
    }
}