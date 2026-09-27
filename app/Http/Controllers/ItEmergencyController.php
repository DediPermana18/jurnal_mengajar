<?php

namespace App\Http\Controllers;

use App\Models\Scopes\TestingDataScope;
use App\Models\User;
use App\Services\SecurityAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Emergency Super Admin Takeover ("Kartu As") untuk Petugas IT / QA Tester.
 *
 * Situasi darurat (mis. akun Super Admin utama dibobol atau terkunci) dapat
 * memaksa penguncian sistem. Endpoint ini mengizinkan akun Petugas IT /
 * QA Tester mempromosikan DIRINYA SENDIRI menjadi 'super_admin' PERMANEN agar
 * sistem tetap bisa dikendalikan.
 *
 * Keamanan:
 *  - HANYA akun dengan role ATAU sub_role 'petugas_it' / 'qa_tester' yang boleh
 *    memanggil — Petugas TU, Waka*, Guru, maupun Super Admin lain ditolak 403.
 *  - Alasan darurat WAJIB diisi (min. 10 karakter) untuk jejak audit.
 *  - Promosi dicatat append-only di security_logs (IP, UA, timestamp, alasan).
 *  - Opsional (dengan konfirmasi): nonaktifkan SEMUA akun Super Admin lama yang
 *    dicurigai dibobol — is_active=false + seluruh sesi dikeluarkan + audit per
 *    akun lewat SecurityAuditService::emergencySuspend.
 */
class ItEmergencyController extends Controller
{
    /**
     * Endpoint POST /admin/it-emergency/promote-self.
     */
    public function promoteSelf(Request $request)
    {
        $user = Auth::user();

        abort_unless(
            $user instanceof User && $this->isItAccount($user),
            403,
            'Takeover darurat hanya dapat dilakukan oleh akun Petugas IT / QA Tester.'
        );

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'reason.required' => 'Alasan darurat wajib diisi untuk jejak audit.',
            'reason.min' => 'Alasan darurat minimal 10 karakter.',
            'reason.max' => 'Alasan darurat maksimal 500 karakter.',
        ]);

        $device = [
            'ip' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
        ];

        // (Opsional, dengan konfirmasi) nonaktifkan akun Super Admin lama yang
        // dicurigai dibobol. Query TANPA scope testing agar mencakup super admin
        // partisi real (produksi) maupun testing.
        $disabled = [];
        if ($request->boolean('disable_compromised')) {
            $disabled = $this->disableCompromisedSuperAdmins($user, $device);
        }

        // Snapshot identitas IT/QA ASLI (sebelum role ditimpa) untuk restorasi
        // demoteSelf — baca dulu, baru timpa role di bawah.
        $originItRole = $this->resolveOriginalItRole($user);

        // Promosi PERMANEN ke Super Admin (skema baru DB):
        // Kolom 'role' di MySQL ber-ENUM('admin','guru') — literal
        // 'super_admin' pada kolom role ditolak (Data truncated).
        // Status Super Admin dibawa oleh sub_role = 'super_admin'
        // (User::isSuperAdmin() mengenali keduanya: role literal ATAU sub_role).
        $user->update([
            'role' => User::ROLE_ADMIN,
            'sub_role' => User::ROLE_SUPER_ADMIN,
        ]);

        // Penanda permanen "takeover darurat" membuka gembok pengelolaan akun
        // Utama 'admin' (UserController::isEmergencyPrimaryAdminOverride).
        // Keduanya di-set via forceFill — sengaja TIDAK ada di $fillable agar
        // tidak bisa di-set curang lewat mass-assignment dari request manapun.
        $user->forceFill([
            'is_emergency_takeover' => true,
            'emergency_origin_role' => $originItRole,
        ])->save();

        // Akun sudah bukan IT lagi — bersihkan state mode IT/impersonasi.
        $request->session()->forget(['active_role', 'impersonate_target_id', 'testing_view']);

        // Jejak audit append-only (immutable security_logs).
        $log = app(SecurityAuditService::class)
            ->emergencyPromote($user, $validated['reason'], $device, $disabled);

        Log::info('security:emergency-takeover', [
            'user_id' => $user->id,
            'security_log_id' => $log->id,
            'ip' => $device['ip'],
            'reason' => $validated['reason'],
            'disabled_count' => count($disabled),
        ]);

        $message = 'Takeover darurat berhasil. Akun Anda kini Super Admin permanen.';

        if (count($disabled) > 0) {
            $message .= ' '.count($disabled).' akun Super Admin lama telah dinonaktifkan (is_active=false) dan seluruh sesinya dikeluarkan.';
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        return redirect()->route('home')->with('success', $message);
    }

    /**
     * Apakah akun ber-role ATAU sub_role 'petugas_it' / 'qa_tester'?
     */
    private function isItAccount(User $user): bool
    {
        return $user->isPetugasIt()
            || in_array($user->sub_role, [User::ROLE_PETUGAS_IT, User::ROLE_QA_TESTER], true);
    }

    /**
     * Endpoint POST /admin/it-emergency/demote-self.
     *
     * Setelah Emergency Takeover ("Kartu As"), akun Petugas IT / QA Tester
     * berubah menjadi Super Admin permanen dan UI Switcher/Mode IT hilang.
     * Endpoint ini mengembalikan akun ke identitas IT/QA asli:
     *  - role  = 'admin' (satunya format yang valid di kolom role MySQL
     *    ENUM('admin','guru')),
     *  - sub_role = 'petugas_it' / 'qa_tester' (sesuai snapshot asal),
     *  - is_emergency_takeover & emergency_origin_role dibersihkan, lalu
     *    diarahkan kembali ke Dashboard IT.
     *
     * Keamanan:
     *  - HANYA akun dengan is_emergency_takeover=true (hasil takeover asli)
     *    yang boleh memanggil — Super Admin biasa / IT tanpa takeover ditolak 403.
     *  - Aksi dicatat append-only di security_logs (IP, UA, timestamp).
     */
    public function demoteSelf(Request $request)
    {
        $user = Auth::user();

        abort_unless(
            $user instanceof User && $user->isEmergencyTakeover() && $user->isSuperAdmin(),
            403,
            'Hanya akun hasil Emergency Super Admin Takeover yang dapat kembali ke Mode IT / QA.'
        );

        // Kembalikan identitas IT/QA asli (snapshot promosi) — fallback aman
        // 'petugas_it' bila snapshot tidak tersedia karena alasan apa pun.
        $itRole = in_array($user->emergency_origin_role, [User::ROLE_PETUGAS_IT, User::ROLE_QA_TESTER], true)
            ? $user->emergency_origin_role
            : User::ROLE_PETUGAS_IT;

        $device = [
            'ip' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
        ];

        // Restorasi + lepaskan status Super Admin darurat.
        // forceFill: kolom khusus ini sengaja tidak ada di $fillable.
        $user->forceFill([
            'role' => User::ROLE_ADMIN,
            'sub_role' => $itRole,
            'is_emergency_takeover' => false,
            'emergency_origin_role' => null,
        ])->save();

        // Bersihkan state Mode IT / impersonasi & Mode Darurat (bila aktif).
        $request->session()->forget(['active_role', 'impersonate_target_id', 'testing_view', 'emergency_mode']);

        // Jejak audit append-only (immutable security_logs).
        $log = app(SecurityAuditService::class)->emergencyDemote($user, $device);

        Log::info('security:emergency-demote', [
            'user_id' => $user->id,
            'security_log_id' => $log->id,
            'ip' => $device['ip'],
            'restored_sub_role' => $itRole,
        ]);

        $message = "Akun berhasil dikembalikan ke Mode IT / QA ({$itRole}). Status Super Admin darurat dilepaskan.";

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        return redirect()->route('it.dashboard')->with('success', $message);
    }

    /**
     * Identitas IT/QA ASLI akun sebelum ditimpa role oleh promoteSelf.
     *
     * Mengembalikan 'petugas_it' / 'qa_tester' sesuai bentuk akun saat masuk:
     * sub_role IT (skema production MySQL: role 'admin' + sub_role IT) atau
     * role literal IT (skema lama / sqlite test). Disimpan ke kolom
     * emergency_origin_role dan dipakai demoteSelf untuk restorasi.
     */
    private function resolveOriginalItRole(User $user): string
    {
        if (in_array($user->sub_role, [User::ROLE_PETUGAS_IT, User::ROLE_QA_TESTER], true)) {
            return $user->sub_role;
        }

        if (in_array($user->role, [User::ROLE_PETUGAS_IT, User::ROLE_QA_TESTER], true)) {
            return $user->role;
        }

        // Gate promoteSelf memastikan akun memang IT — fallback defensif.
        return User::ROLE_PETUGAS_IT;
    }

    /**
     * Nonaktifkan paksa SEMUA akun Super Admin lain (indikasi dibobol).
     *
     * Memakai SecurityAuditService::emergencySuspend per akun: is_active=false,
     * seluruh sesi aktif + ikatan single-device dikeluarkan, dan audit baris
     * append-only dicatat per akun — keamanan berlapis dan tetap immutable.
     *
     * @return User[] daftar akun Super Admin lain yang telah dinonaktifkan.
     */
    private function disableCompromisedSuperAdmins(User $actor, array $device): array
    {
        $targets = User::withoutGlobalScope(TestingDataScope::class)
            ->where('id', '!=', $actor->id)
            ->where(function ($query) {
                $query->where('role', User::ROLE_SUPER_ADMIN)
                    ->orWhere('sub_role', User::ROLE_SUPER_ADMIN);
            })
            ->orderBy('id')
            ->get();

        $service = app(SecurityAuditService::class);

        foreach ($targets as $target) {
            if ($target->is_active) {
                $service->emergencySuspend($target, $actor, $device);
            }
        }

        return $targets->all();
    }
}