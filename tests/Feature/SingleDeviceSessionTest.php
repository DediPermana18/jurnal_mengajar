<?php

namespace Tests\Feature;

use App\Events\LoginApprovalRequested;
use App\Models\User;
use App\Services\SingleDeviceSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SingleDeviceSessionTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'nama' => 'Petugas Aktif',
            'username' => 'petugas_aktif',
            'email' => 'petugas_aktif@school.id',
            'password' => Hash::make('password123'),
            'role' => 'guru',
            'sub_role' => 'guru_mapel',
            'is_active' => true,
        ], $overrides));
    }

    /**
     * Simulasikan sesi yang tercatat hidup di database (Device A masih login).
     */
    private function seedLiveSession(User $user, string $sessionId): void
    {
        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $user->id,
            'ip_address' => '10.0.0.1',
            'user_agent' => 'Device-A',
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->getTimestamp(),
        ]);
    }

    private function loginPayload(): array
    {
        return [
            'login_id' => 'petugas_aktif',
            'password' => 'password123',
            'mode' => 'guru',
        ];
    }

    private function sessionLock(): string
    {
        return (string) session()->get(SingleDeviceSessionService::LOCK_KEY, '');
    }

    public function test_login_pertama_mengikat_current_session_id(): void
    {
        $this->createUser();

        $this->post(route('login.post'), $this->loginPayload())
            ->assertRedirect();

        $user = User::where('username', 'petugas_aktif')->firstOrFail();

        $this->assertNotEmpty($user->current_session_id);
        $this->assertSame($user->current_session_id, $this->sessionLock());
        $this->assertFalse($user->is_idle);
        $this->assertNotNull($user->last_active_at);
    }

    public function test_relogin_dari_perangkat_yang_sama_tidak_dianggap_konflik(): void
    {
        $this->createUser();

        // Login pertama → mengikat sesi + lock token tersimpan di sesi.
        $this->post(route('login.post'), $this->loginPayload())->assertRedirect();
        $this->assertNotEmpty($this->sessionLock());

        // Login ulang dengan perangkat/sesi yang sama (lock masih ada) → TETAP sukses.
        $this->post(route('login.post'), $this->loginPayload())
            ->assertRedirect();

        $this->assertAuthenticated();
    }

    public function test_login_perangkat_kedua_meminta_approval_saat_perangkat_pertama_sangat_aktif(): void
    {
        Event::fake([LoginApprovalRequested::class]);

        $user = $this->createUser();

        // Device A: sesi hidup + SANGAT AKTIF.
        $this->seedLiveSession($user, 'device-A');
        $user->forceFill([
            'current_session_id' => 'device-A',
            'is_idle' => false,
            'last_active_at' => now(),
        ])->save();

        // Device B (sesi baru, tanpa lock token) tahukah login → TIDAK dituntaskan.
        // Masuk mode pending approval (limit 60 detik) menunggu keputusan Device A.
        $this->postJson(route('login.post'), $this->loginPayload())
            ->assertStatus(202)
            ->assertJson([
                'approval_required' => true,
                'message' => 'Akun sedang aktif digunakan di perangkat lain. Menunggu persetujuan perangkat aktif.',
            ])
            ->assertJsonPath('request_id', fn ($value) => is_string($value) && $value !== '');

        $this->assertGuest();

        // current_session_id TETAP milik Device A — request pending belum menimpa apa pun.
        $this->assertSame('device-A', $user->fresh()->current_session_id);

        // Event LoginApprovalRequested dikirim real-time ke perangkat aktif (Device A)
        // membawa request_id + device_info (IP/User-Agent Device B).
        Event::assertDispatched(LoginApprovalRequested::class, function (LoginApprovalRequested $event) use ($user) {
            return $event->user->is($user)
                && is_array($event->device_info)
                && filled($event->device_info['ip'] ?? null);
        });
    }

    public function test_login_perangkat_kedua_masuk_mode_pending_untuk_request_browser(): void
    {
        $user = $this->createUser();
        $this->seedLiveSession($user, 'device-A');
        $user->forceFill([
            'current_session_id' => 'device-A',
            'is_idle' => false,
            'last_active_at' => now(),
        ])->save();

        // Request browser biasa (non-JSON) → redirect back + flash login_approval
        // agar halaman login menampilkan panel "Menunggu Konfirmasi Perangkat Aktif".
        $this->post(route('login.post'), $this->loginPayload())
            ->assertSessionHas('login_approval');

        $this->assertNotEmpty(session('login_approval.request_id'));

        $this->assertGuest();
    }

    public function test_login_perangkat_kedua_diizinkan_take_over_saat_perangkat_pertama_afk(): void
    {
        $user = $this->createUser();
        $this->seedLiveSession($user, 'device-A');
        $user->forceFill([
            'current_session_id' => 'device-A',
            'is_idle' => true,
            'last_active_at' => now()->subMinutes(SingleDeviceSessionService::IDLE_THRESHOLD_MINUTES + 1),
        ])->save();

        // Device B login → sukses (take-over) karena Device A AFK/idle.
        $this->post(route('login.post'), $this->loginPayload())
            ->assertRedirect();

        $this->assertAuthenticated();
        $user = $user->fresh();

        // current_session_id beralih ke lock token Device B + lock tersimpan di sesi.
        $this->assertNotSame('device-A', $user->current_session_id);
        $this->assertSame($user->current_session_id, $this->sessionLock());
        $this->assertFalse($user->is_idle);

        // Request berikutnya dari Device B tetap valid (tidak ter-kick).
        $this->get(route('guru.dashboard'))->assertOk();
    }

    public function test_login_perangkat_kedua_tidak_menimpa_perangkat_pertama_saat_aktif_tanpa_row_sessions(): void
    {
        $user = $this->createUser();
        // REGRESI: dulu bug — tanpa row di tabel sessions, gate selalu lolos
        // (sessionIsLive selalu false) sehingga Device B bebas menimpa Device A.
        // Sekarang gate HANYA bergantung pada is_idle + last_active_at.
        $user->forceFill([
            'current_session_id' => 'device-A',
            'is_idle' => false,
            'last_active_at' => now(),
        ])->save();
        // (Perhatikan: TIDAK ada row 'device-A' di tabel sessions.)

        // Device B login → masuk mode pending approval (202), BUKAN menimpa sesi Device A.
        $this->postJson(route('login.post'), $this->loginPayload())
            ->assertStatus(202)
            ->assertJson(['approval_required' => true]);

        $this->assertGuest();
        // current_session_id tetap milik Device A (tidak tertimpa).
        $this->assertSame('device-A', $user->fresh()->current_session_id);
    }

    public function test_login_perangkat_kedua_diizinkan_saat_perangkat_pertama_sudah_lama_tidak_aktif(): void
    {
        $user = $this->createUser();
        // current_session_id menunjuk sesi lama yang last_active_at-nya sudah
        // berlalu > 3 menit (misal sesi mati / user sudah lama tak aktif) →
        // tidak dianggap konflik, boleh take-over.
        $user->forceFill([
            'current_session_id' => 'device-A-hilang',
            'is_idle' => false,
            'last_active_at' => now()->subMinutes(10),
        ])->save();

        $this->post(route('login.post'), $this->loginPayload())
            ->assertRedirect();

        $this->assertAuthenticated();
    }

    public function test_heartbeat_memperbarui_status_idle_dan_last_active_at(): void
    {
        $user = $this->createUser();
        $lock = 'lock-heartbeat';
        $user->forceFill(['current_session_id' => $lock])->save();
        session()->put(SingleDeviceSessionService::LOCK_KEY, $lock);

        // Sesi menjadi idle (AFK).
        $this->actingAs($user)
            ->postJson('/api/user/heartbeat', [
                'is_idle' => true,
                'session_id' => $lock,
            ])
            ->assertOk()
            ->assertJson(['kicked' => false]);

        $fresh = $user->fresh();
        $this->assertTrue($fresh->is_idle);
        $this->assertNotNull($fresh->last_active_at);

        // User kembali berinteraksi → aktif kembali.
        $this->actingAs($user)
            ->postJson('/api/user/heartbeat', [
                'is_idle' => false,
                'session_id' => $lock,
            ])
            ->assertOk();

        $this->assertFalse($user->fresh()->is_idle);
    }

    public function test_heartbeat_mendeteksi_sesi_yang_ter_kick(): void
    {
        $user = $this->createUser();
        // Sesi ini bukan lagi pemilik — current_session_id berpindah ke perangkat lain.
        $user->forceFill(['current_session_id' => 'device-Bar', 'is_idle' => false])->save();
        session()->put(SingleDeviceSessionService::LOCK_KEY, 'lock-sesi-ini');

        $this->actingAs($user)
            ->postJson('/api/user/heartbeat', [
                'is_idle' => false,
                'session_id' => 'lock-sesi-ini',
            ])
            ->assertOk()
            ->assertJson([
                'kicked' => true,
                'message' => 'Sesi Anda telah dihentikan otomatis karena Anda sedang AFK dan akun digunakan di perangkat lain.',
            ]);
    }

    public function test_heartbeat_tanpa_autentikasi_ditolak_401(): void
    {
        $this->postJson('/api/user/heartbeat', ['is_idle' => false])
            ->assertStatus(401);
    }

    public function test_middleware_mengeluarkan_sesi_yang_tidak_cocok_dan_meredirect_ke_login(): void
    {
        $user = $this->createUser();
        $user->forceFill(['current_session_id' => 'sesi-perangkat-lain', 'is_idle' => false])->save();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame(
            'Sesi Anda telah dihentikan otomatis karena Anda sedang AFK dan akun digunakan di perangkat lain.',
            session('error')
        );
    }

    public function test_middleware_tidak_mengganggu_pengguna_dengan_current_session_null(): void
    {
        // Legacy / mode testing: current_session_id null → fitur non-aktif → tetap lewat.
        $user = $this->createUser();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();

        $this->assertAuthenticatedAs($user);
    }

    public function test_middleware_membiarkan_sesi_owner_yang_valid(): void
    {
        $this->createUser();
        $this->post(route('login.post'), $this->loginPayload())->assertRedirect();

        // Request berikutnya dengan lock yang sama → TIDAK ter-kick.
        $this->get(route('guru.dashboard'))->assertOk();
        $this->assertAuthenticated();
    }

    public function test_logout_menghapus_ikatan_current_session_id(): void
    {
        $this->createUser();
        $this->post(route('login.post'), $this->loginPayload())->assertRedirect();

        $user = User::where('username', 'petugas_aktif')->firstOrFail();
        $this->assertNotEmpty($user->current_session_id);

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->assertNull($user->fresh()->current_session_id);
        $this->assertGuest();
    }

    public function test_login_attempt_mencatat_log_audit_trail_allowed(): void
    {
        Log::spy();

        $this->createUser();
        $this->post(route('login.post'), $this->loginPayload())->assertRedirect();

        // Log gate login: berisi user_id, status idle, last_active_at & keputusan.
        Log::shouldHaveReceived('info')
            ->with(
                'single-device-session:login-gate',
                \Mockery::on(function (array $ctx) {
                    return ($ctx['user_id'] ?? null) !== null
                        && array_key_exists('is_idle', $ctx)
                        && array_key_exists('last_active_at', $ctx)
                        && $ctx['decision'] === 'allowed';
                })
            )
            ->once();
    }

    public function test_login_attempt_mencatat_log_audit_trail_pending_approval(): void
    {
        Log::spy();

        $user = $this->createUser();
        $user->forceFill([
            'current_session_id' => 'device-A',
            'is_idle' => false,
            'last_active_at' => now(),
        ])->save();

        $this->postJson(route('login.post'), $this->loginPayload())
            ->assertStatus(202);

        Log::shouldHaveReceived('info')
            ->with(
                'single-device-session:login-gate',
                \Mockery::on(function (array $ctx) {
                    return ($ctx['decision'] ?? null) === 'pending-approval'
                        && ($ctx['user_id'] ?? null) !== null;
                })
            )
            ->once();
    }
}