<?php

namespace Tests\Feature;

use App\Events\LoginApprovalDecision;
use App\Events\LoginApprovalRequested;
use App\Models\User;
use App\Services\LoginApprovalService;
use App\Services\SingleDeviceSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Interactive Real-Time Login Approval (Push Prompt).
 *
 * Skenario: Device B mencoba login ke akun yang AKTIF di Device A.
 *  - Login Device B TIDAK dituntaskan → status pending (Cache TTL 60 detik).
 *  - Event LoginApprovalRequested dikirim ke Device A (modal [Izinkan]/[Tolak]).
 *  - Device A approve → status approved + one-time login_token; sesi A di-invalidate.
 *  - Device B complete (dengan token) → login sukses; Device A ter-kick.
 *  - Device A reject (atau timeout) → Device B gagal login.
 */
class LoginApprovalFlowTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'nama' => 'Guru Uji',
            'username' => 'guru_uji',
            'email' => 'guru_uji@school.id',
            'password' => Hash::make('password123'),
            'role' => 'guru',
            'sub_role' => 'guru_mapel',
            'is_active' => true,
        ], $overrides));
    }

    private function loginPayload(): array
    {
        return [
            'login_id' => 'guru_uji',
            'password' => 'password123',
            'mode' => 'guru',
        ];
    }

    /** Device A SANGAT AKTIF, pemegang lock 'device-A'. */
    private function seedActiveDeviceA(User $user): void
    {
        $user->forceFill([
            'current_session_id' => 'device-A',
            'is_idle' => false,
            'last_active_at' => now(),
        ])->save();
    }

    /** Simulasikan Device A (user + lock sesi 'device-A') pada request berikutnya. */
    private function actingAsDeviceA(User $user)
    {
        return $this->withSession([SingleDeviceSessionService::LOCK_KEY => 'device-A'])
            ->actingAs($user);
    }

    public function test_alur_lengkap_izinkan_login_otomatis_masuk_dan_device_a_terkick(): void
    {
        Event::fake([LoginApprovalRequested::class, LoginApprovalDecision::class]);

        $user = $this->createUser();
        $this->seedActiveDeviceA($user);

        // 1) Device B login → 202 + request_id pending (sesi B tidak dituntaskan).
        $requestId = $this->postJson(route('login.post'), $this->loginPayload())
            ->assertStatus(202)
            ->assertJson(['approval_required' => true])
            ->assertJsonPath('expires_in', LoginApprovalService::TTL_SECONDS)
            ->json('request_id');

        $this->assertNotEmpty($requestId);
        $this->assertGuest();

        Event::assertDispatched(LoginApprovalRequested::class, function (LoginApprovalRequested $event) use ($user, $requestId) {
            return $event->user->is($user) && $event->request_id === $requestId;
        });

        // 2) Device A mem-poll endpoint pending → melihat permintaan tersebut.
        $this->actingAsDeviceA($user)
            ->getJson(route('login-approvals.pending'))
            ->assertOk()
            ->assertJsonPath('requests.0.request_id', $requestId)
            ->assertJsonPath('requests.0.status', 'pending');

        // 3) Device A menekan [Izinkan Login].
        $this->actingAsDeviceA($user)
            ->postJson(route('login-approval.approve'), ['request_id' => $requestId])
            ->assertOk()
            ->assertJson(['status' => 'approved']);

        Event::assertDispatched(LoginApprovalDecision::class, function (LoginApprovalDecision $event) use ($requestId) {
            return $event->status === 'approved' && $event->request_id === $requestId;
        });

        // Sesi Device A langsung di-invalidate (logout) setelah menyetujui.
        $this->assertGuest();

        // 4) Device B mem-poll status → approved + one-time login_token.
        $loginToken = $this->getJson(route('login-approval.status', ['requestId' => $requestId]))
            ->assertOk()
            ->assertJson(['status' => 'approved'])
            ->json('login_token');

        $this->assertNotEmpty($loginToken);

        // 5) Device B menuntaskan login memakai token (one-time use).
        $this->postJson(route('login-approval.complete'), [
            'request_id' => $requestId,
            'login_token' => $loginToken,
        ])
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('redirect_url', fn ($v) => filled($v) && is_string($v));

        $this->assertAuthenticatedAs($user);

        // Lock token Device B terikat sebagai sesi aktif baru.
        $fresh = $user->fresh();
        $this->assertSame($fresh->current_session_id, $this->sessionLock());

        // 6) Request berikutnya dari Device B (lock yang sama) tetap valid.
        $this->get(route('guru.dashboard'))->assertOk();

        // 7) Token one-time: pemakaian kedua gagal.
        $this->postJson(route('login-approval.complete'), [
            'request_id' => $requestId,
            'login_token' => $loginToken,
        ])->assertStatus(422);

        // 8) Device A lama (lock 'device-A') otomatis di-kick oleh middleware.
        $this->actingAsDeviceA($fresh)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_alur_tolak_login_device_b_gagal_sementara_device_a_tetap_aktif(): void
    {
        Event::fake([LoginApprovalRequested::class, LoginApprovalDecision::class]);

        $user = $this->createUser();
        $this->seedActiveDeviceA($user);

        // 1) Device B login → pending.
        $requestId = $this->postJson(route('login.post'), $this->loginPayload())
            ->assertStatus(202)
            ->json('request_id');

        // 2) Device A menekan [Tolak].
        $this->actingAsDeviceA($user)
            ->postJson(route('login-approval.reject'), ['request_id' => $requestId])
            ->assertOk()
            ->assertJson(['status' => 'rejected']);

        Event::assertDispatched(LoginApprovalDecision::class, function (LoginApprovalDecision $event) use ($requestId) {
            return $event->status === 'rejected' && $event->request_id === $requestId;
        });

        // Menolak TIDAK me-logout Device A.
        $this->actingAsDeviceA($user)->get(route('dashboard'))->assertOk();
        $this->assertSame('device-A', $user->fresh()->current_session_id);

        // 3) Device B mem-poll status → rejected (tanpa login_token).
        $this->getJson(route('login-approval.status', ['requestId' => $requestId]))
            ->assertOk()
            ->assertJson(['status' => 'rejected']);

        // Reset guard + session uji — Device B benar-benar berstatus guest.
        $this->app['auth']->forgetGuards();
        session()->flush();

        // 4) Device B mencoba menuntaskan login → gagal, tetap guest.
        $this->postJson(route('login-approval.complete'), [
            'request_id' => $requestId,
            'login_token' => 'token-palsu',
        ])
            ->assertStatus(422)
            ->assertJson(['message' => 'Permintaan login ditolak oleh perangkat aktif.']);

        $this->assertGuest();
        $this->assertSame('device-A', $user->fresh()->current_session_id);
    }

    public function test_permintaan_yang_kedaluwarsa_dianggap_expired(): void
    {
        $user = $this->createUser();
        $this->seedActiveDeviceA($user);

        $requestId = $this->postJson(route('login.post'), $this->loginPayload())
            ->assertStatus(202)
            ->json('request_id');

        // Simulasikan TTL 60 detik habis (key cache dihapus oleh store).
        Cache::forget('login_approval:request:'.$requestId);

        $this->getJson(route('login-approval.status', ['requestId' => $requestId]))
            ->assertOk()
            ->assertJson(['status' => 'expired']);

        // Timeout diperlakukan seperti ditolak oleh perangkat aktif.
        $this->postJson(route('login-approval.complete'), [
            'request_id' => $requestId,
            'login_token' => 'token-apapun',
        ])
            ->assertStatus(422)
            ->assertJson(['message' => 'Permintaan login ditolak oleh perangkat aktif.']);

        $this->assertGuest();
    }

    public function test_endpoint_pending_hanya_mengembalikan_request_pemilik_akun(): void
    {
        $user = $this->createUser();
        $this->seedActiveDeviceA($user);
        $other = $this->createUser([
            'nama' => 'Guru Lain',
            'username' => 'guru_lain',
            'email' => 'guru_lain@school.id',
        ]);

        $requestId = $this->postJson(route('login.post'), $this->loginPayload())
            ->assertStatus(202)
            ->json('request_id');

        // Pemilik akun (Device A) melihat permintaannya.
        $this->actingAsDeviceA($user)
            ->getJson(route('login-approvals.pending'))
            ->assertOk()
            ->assertJsonPath('requests.0.request_id', $requestId);

        // User lain tidak melihat apa pun.
        $this->actingAs($other)
            ->getJson(route('login-approvals.pending'))
            ->assertOk()
            ->assertJsonCount(0, 'requests');
    }

    public function test_approve_reject_hanya_bisa_dilakukan_pemilik_akun(): void
    {
        $user = $this->createUser();
        $this->seedActiveDeviceA($user);
        $intruder = $this->createUser([
            'nama' => 'Penyusup',
            'username' => 'penyusup',
            'email' => 'penyusup@school.id',
        ]);

        $requestId = $this->postJson(route('login.post'), $this->loginPayload())
            ->assertStatus(202)
            ->json('request_id');

        // User lain mencoba approve → 404 (identitas request disembunyikan).
        $this->actingAs($intruder)
            ->postJson(route('login-approval.approve'), ['request_id' => $requestId])
            ->assertStatus(404);

        // User lain mencoba reject → 404.
        $this->actingAs($intruder)
            ->postJson(route('login-approval.reject'), ['request_id' => $requestId])
            ->assertStatus(404);

        // Status tetap pending — tidak ada yang berubah.
        $this->getJson(route('login-approval.status', ['requestId' => $requestId]))
            ->assertOk()
            ->assertJson(['status' => 'pending']);
    }

    public function test_complete_tanpa_token_atau_request_id_ditolak(): void
    {
        $user = $this->createUser();
        $this->seedActiveDeviceA($user);

        $requestId = $this->postJson(route('login.post'), $this->loginPayload())
            ->assertStatus(202)
            ->json('request_id');

        // Token tidak diisi → 422.
        $this->postJson(route('login-approval.complete'), ['request_id' => $requestId])
            ->assertStatus(422)
            ->assertJson(['message' => 'Permintaan login tidak lengkap.']);

        // request_id tidak diisi → 422.
        $this->postJson(route('login-approval.complete'), ['login_token' => 'x'])
            ->assertStatus(422);

        $this->assertGuest();
    }

    public function test_tolak_dan_amankan_reject_dengan_pesan_akses_ditolak_pemilik(): void
    {
        $user = $this->createUser();
        $this->seedActiveDeviceA($user);

        // Device B login → pending.
        $requestId = $this->postJson(route('login.post'), $this->loginPayload())
            ->assertStatus(202)
            ->json('request_id');

        // Device A menekan [Tolak & Amankan Akun] → reject dengan pesan khusus.
        $this->actingAsDeviceA($user)
            ->postJson(route('login-approval.reject'), [
                'request_id' => $requestId,
                'message' => 'Akses ditolak oleh pemilik akun',
            ])
            ->assertOk()
            ->assertJson(['status' => 'rejected']);

        // Device B mem-poll status → melihat pesan spesifik dari pemilik.
        $this->getJson(route('login-approval.status', ['requestId' => $requestId]))
            ->assertOk()
            ->assertJson([
                'status' => 'rejected',
                'reject_message' => 'Akses ditolak oleh pemilik akun',
            ]);

        // Device B gagal login (tetap guest).
        $this->app['auth']->forgetGuards();
        session()->flush();
        $this->postJson(route('login-approval.complete'), [
            'request_id' => $requestId,
            'login_token' => 'token-palsu',
        ])->assertStatus(422);

        $this->assertGuest();
        $this->assertSame('device-A', $user->fresh()->current_session_id);
    }

    public function test_tolak_dan_amankan_ganti_password_mengamankan_akun(): void
    {
        $user = $this->createUser();
        $this->seedActiveDeviceA($user);

        // Device B login → pending request menggantung.
        $requestId = $this->postJson(route('login.post'), $this->loginPayload())
            ->assertStatus(202)
            ->json('request_id');

        // Row sessions "perangkat lain yang masih hidup" (untuk di-prune).
        DB::table('sessions')->insert([
            'id' => 'perangkat-lama-row',
            'user_id' => $user->id,
            'ip_address' => '10.9.9.9',
            'user_agent' => 'Device-Zombie',
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->getTimestamp(),
        ]);

        // [Tolak & Amankan]: tolak request tsb dengan pesan pemilik.
        $this->actingAsDeviceA($user)
            ->postJson(route('login-approval.reject'), [
                'request_id' => $requestId,
                'message' => 'Akses ditolak oleh pemilik akun',
            ])->assertOk();

        // Ganti password quick-reset langsung dari modal keamanan.
        $this->actingAsDeviceA($user)
            ->postJson(route('login-approval.secure-password'), [
                'current_password' => 'password123',
                'password' => 'passwordBaru123',
                'password_confirmation' => 'passwordBaru123',
            ])
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('message', 'Password berhasil diperbarui. Akun Anda kini aman.');

        // Password berubah & ter-hash baru.
        $fresh = $user->fresh();
        $this->assertTrue(Hash::check('passwordBaru123', $fresh->password));
        $this->assertFalse(Hash::check('password123', $fresh->password));

        // Lock token dirotasi — perangkat ini tetap owner dengan lock baru.
        $newLock = $this->sessionLock();
        $this->assertNotEmpty($newLock);
        $this->assertNotSame('device-A', $newLock);
        $this->assertSame($newLock, $fresh->current_session_id);
        $this->assertFalse($fresh->is_idle);

        // Semua sesi lain (di tabel sessions) di-invalidate — kecuali sesi berjalan.
        $this->assertFalse(DB::table('sessions')->where('id', 'perangkat-lama-row')->exists());

        // Request approval yang menggantung dibersihkan (indeks pending kosong).
        $this->withSession([SingleDeviceSessionService::LOCK_KEY => $newLock])
            ->actingAs($user)
            ->getJson(route('login-approvals.pending'))
            ->assertOk()
            ->assertJsonCount(0, 'requests');

        // Perangkat lama pemegang lock 'device-A' otomatis di-kick.
        $this->withSession([SingleDeviceSessionService::LOCK_KEY => 'device-A'])
            ->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_tolak_dan_amankan_password_lama_salah_ditolak(): void
    {
        $user = $this->createUser();
        $this->seedActiveDeviceA($user);

        // Password lama salah → 422 + pesan jelas, password TIDAK berubah.
        $this->actingAsDeviceA($user)
            ->postJson(route('login-approval.secure-password'), [
                'current_password' => 'passwordSALAH',
                'password' => 'passwordBaru123',
                'password_confirmation' => 'passwordBaru123',
            ])
            ->assertStatus(422)
            ->assertJson(['message' => 'Password saat ini tidak sesuai.']);

        $this->assertTrue(Hash::check('password123', $user->fresh()->password));
        $this->assertSame('device-A', $user->fresh()->current_session_id);
    }

    public function test_tolak_dan_amankan_validasi_password_baru(): void
    {
        $user = $this->createUser();
        $this->seedActiveDeviceA($user);

        // Konfirmasi password baru tidak cocok → error validasi.
        $this->actingAsDeviceA($user)
            ->postJson(route('login-approval.secure-password'), [
                'current_password' => 'password123',
                'password' => 'passwordBaru123',
                'password_confirmation' => 'berbedalagi',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        // Password baru terlalu pendek (< 8 karakter).
        $this->actingAsDeviceA($user)
            ->postJson(route('login-approval.secure-password'), [
                'current_password' => 'password123',
                'password' => 'pendek',
                'password_confirmation' => 'pendek',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        // Tidak ada perubahan apa pun.
        $this->assertTrue(Hash::check('password123', $user->fresh()->password));
        $this->assertSame('device-A', $user->fresh()->current_session_id);
    }

    public function test_amankan_akun_menonaktifkan_afk_protection_pada_sesi_berjalan(): void
    {
        $user = $this->createUser();
        $this->seedActiveDeviceA($user);

        // Device B login → pending.
        $requestId = $this->postJson(route('login.post'), $this->loginPayload())
            ->assertStatus(202)
            ->json('request_id');

        // Device A memilih [Tolak & Amankan Akun] → flag AFK protection off.
        $this->actingAsDeviceA($user)
            ->postJson(route('login-approval.reject'), [
                'request_id' => $requestId,
                'message' => 'Akses ditolak oleh pemilik akun',
                'amankan_akun' => true,
            ])->assertOk();

        // Flag tersimpan di sesi berjalan.
        $this->assertTrue(session()->get(SingleDeviceSessionService::AFK_DISABLED_KEY, false));

        // Heartbeat is_idle=true DIIABAIKAN — sesi tetap dianggap aktif.
        $this->actingAsDeviceA($user)
            ->postJson(route('user.heartbeat'), [
                'is_idle' => true,
                'session_id' => 'device-A',
            ])
            ->assertOk()
            ->assertJson([
                'kicked' => false,
                'afk_protection_disabled' => true,
            ]);

        $this->assertFalse($user->fresh()->is_idle);
        $this->assertSame('device-A', $user->fresh()->current_session_id);

        // Karena sesi tidak pernah idle, login perangkat lain tetap masuk
        // mode persetujuan (pending-approval), bukan take-over.
        $this->app['auth']->forgetGuards();
        session()->flush();
        $this->postJson(route('login.post'), $this->loginPayload())
            ->assertStatus(202)
            ->assertJsonPath('approval_required', true);
        $this->assertSame('device-A', $user->fresh()->current_session_id);
    }

    public function test_secure_password_menonaktifkan_afk_protection_untuk_sesi_berjalan(): void
    {
        $user = $this->createUser();
        $this->seedActiveDeviceA($user);

        $this->actingAsDeviceA($user)
            ->postJson(route('login-approval.secure-password'), [
                'current_password' => 'password123',
                'password' => 'passwordBaru123',
                'password_confirmation' => 'passwordBaru123',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        // Setelah akun diamankan, AFK auto-logout tetap nonaktif untuk sisa
        // sesi ini (sampai user Logout & Login kembali).
        $this->assertTrue(session()->get(SingleDeviceSessionService::AFK_DISABLED_KEY, false));

        // Lock tetap dirotasi & perangkat ini tetap owner.
        $fresh = $user->fresh();
        $this->assertSame($this->sessionLock(), $fresh->current_session_id);
        $this->assertNotSame('device-A', $fresh->current_session_id);
    }

    public function test_relogin_mengaktifkan_kembali_afk_protection(): void
    {
        $user = $this->createUser();

        // Simulasikan sesi "lama" yang sempat menonaktifkan AFK protection.
        $this->withSession([SingleDeviceSessionService::AFK_DISABLED_KEY => true]);
        $this->assertTrue(session()->get(SingleDeviceSessionService::AFK_DISABLED_KEY, false));

        // Logout eksplisit → sesi dihancurkan.
        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();

        // Login baru → flag TIDAK boleh ikut terbawa (AFK protection aktif lagi).
        $this->post(route('login.post'), $this->loginPayload())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
        $this->assertFalse(session()->get(SingleDeviceSessionService::AFK_DISABLED_KEY, false));
    }

    private function sessionLock(): string
    {
        return (string) session()->get(SingleDeviceSessionService::LOCK_KEY, '');
    }
}