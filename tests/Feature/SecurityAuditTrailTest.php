<?php

namespace Tests\Feature;

use App\Models\SecurityLog;
use App\Models\User;
use App\Services\SecurityAuditService;
use App\Services\SingleDeviceSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Immutable Security Audit Log & Remote Session Invalidation.
 *
 *  - Setiap login BERHASIL menambah SATU baris di tabel security_logs
 *    (append-only — tidak ada UPDATE/DELETE untuk tabel ini di controller).
 *  - Login yang beluм berhasil (pending approval) TIDAK dicatat.
 *  - Halaman "Perangkat & Keamanan" menampilkan seluruh jejak login.
 *  - [Putuskan Sesi Ini] meng-invalidasi sesi HTTP target dari tabel
 *    `sessions` (dan melepas ikatan single-device bila perlu), lalu mengarahkan
 *    pemilik ke form Ganti Password (flash tab_aktif = password).
 *  - Notifikasi bot chat (WhatsApp/Telegram) dikirim tiap login baru.
 */
class SecurityAuditTrailTest extends TestCase
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

    private function seedLiveSession(string $sessionId, int $userId): void
    {
        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => null,
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->timestamp,
        ]);
    }

    private function viewer(User $user, string $lock)
    {
        return $this->actingAs($user)
            ->withSession([SingleDeviceSessionService::LOCK_KEY => $lock]);
    }

    // ================= PEMBUKUAN LOGIN (append-only) =================

    public function test_login_berhasil_mencatat_jejak_perangkat(): void
    {
        $this->createUser();

        $this->post(route('login.post'), $this->loginPayload())->assertRedirect();

        $userId = User::where('username', 'guru_uji')->value('id');
        $log = SecurityLog::where('user_id', $userId)->sole();

        $this->assertTrue($log->is_current_session);
        $this->assertNotNull($log->login_at);
        $this->assertNotEmpty($log->ip_address);
        $this->assertNotEmpty($log->user_agent);
        $this->assertNotEmpty($log->device_name);

        // Lock token & session id FINAL sesi autentikasi terrekam pada log.
        $this->assertNotSame('', (string) $log->session_lock);
        $this->assertSame(
            (string) session()->get(SingleDeviceSessionService::LOCK_KEY, ''),
            (string) $log->session_lock
        );
        $this->assertSame((string) session()->getId(), (string) $log->session_id);
    }

    public function test_login_berulang_append_baris_baru_tanpa_mengubah_log_lama(): void
    {
        $this->createUser();

        $this->post(route('login.post'), $this->loginPayload())->assertRedirect();
        $pertama = SecurityLog::first();
        $this->post(route('login.post'), $this->loginPayload())->assertRedirect();

        $this->assertDatabaseCount('security_logs', 2);

        // Baris lama tidak tersentuh (immutable).
        $this->assertSame(
            (string) $pertama->id,
            (string) SecurityLog::findOrFail($pertama->id)->id
        );
        $this->assertSame(
            (string) $pertama->login_at,
            (string) SecurityLog::findOrFail($pertama->id)->login_at
        );
    }

    public function test_login_password_salah_tidak_mencatat_log(): void
    {
        $this->createUser();

        $this->post(route('login.post'), array_merge($this->loginPayload(), [
            'password' => 'salah123',
        ]));

        $this->assertDatabaseCount('security_logs', 0);
    }

    public function test_login_pending_approval_tidak_mencatat_log(): void
    {
        // Device A SANGAT AKTIF → login Device B menunggu persetujuan dan
        // TIDAK dituntaskan (Auth::login tidak dipanggil) → belum dicatat.
        $user = $this->createUser();
        $user->forceFill([
            'current_session_id' => 'device-A',
            'is_idle' => false,
            'last_active_at' => now(),
        ])->save();

        $this->postJson(route('login.post'), $this->loginPayload())
            ->assertStatus(202)
            ->assertJson(['approval_required' => true]);

        $this->assertDatabaseCount('security_logs', 0);
    }

    // ================= HALAMAN PERANGKAT & KEAMANAN =================

    public function test_halaman_perangkat_memerlukan_autentikasi(): void
    {
        $this->get(route('security.devices'))->assertRedirect(route('login'));
    }

    public function test_halaman_menampilkan_riwayat_dan_tombol_hanya_untuk_perangkat_lain_yang_aktif(): void
    {
        $user = $this->createUser();
        $user->forceFill(['current_session_id' => 'lock-viewer'])->save();

        $perangkatSekarang = SecurityLog::create([
            'user_id' => $user->id,
            'ip_address' => '192.168.1.5',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/126.0.0.0',
            'device_name' => 'Chrome • Windows 10/11',
            'login_at' => now(),
            'session_lock' => 'lock-viewer',
            'session_id' => 'sess-viewer',
        ]);

        $perangkatAktifLain = SecurityLog::create([
            'user_id' => $user->id,
            'ip_address' => '203.0.113.99',
            'user_agent' => 'Mozilla/5.0 (Linux; Android 14) Mobile Firefox/127.0',
            'device_name' => 'Firefox • Android',
            'login_at' => now()->subMinutes(2),
            'session_lock' => 'lock-other',
            'session_id' => 'sess-other',
        ]);
        // Perangkat lain punya sesi HTTP yang masih hidup di tabel sessions.
        $this->seedLiveSession('sess-other', $user->id);

        SecurityLog::create([
            'user_id' => $user->id,
            'ip_address' => '198.51.100.7',
            'user_agent' => 'Mozilla/5.0 (Macintosh) Safari/605.1.15',
            'device_name' => 'Safari • macOS',
            'login_at' => now()->subDays(5),
            'session_lock' => 'lock-archived',
            'session_id' => 'sess-old',
        ]);

        $response = $this->viewer($user, 'lock-viewer')
            ->get(route('security.devices'))
            ->assertOk()
            ->assertSee('Perangkat & Keamanan')
            // Status badge
            ->assertSee('Perangkat ini')
            ->assertSee('Aktif')
            ->assertSee('Historis')
            // Detail IP & perangkat
            ->assertSee('192.168.1.5')
            ->assertSee('203.0.113.99')
            ->assertSee('198.51.100.7')
            ->assertSee('Firefox • Android');

        // Tombol [Putuskan Sesi Ini] hanya untuk perangkat lain yang aktif.
        $response->assertSee(route('security.devices.revoke', $perangkatAktifLain->id), false);
        $response->assertDontSee(route('security.devices.revoke', $perangkatSekarang->id), false);
    }

    // ================= PUTUSKAN SESI INI (remote invalidation) =================

    public function test_putuskan_sesi_menghapus_sesi_http_dan_mengarahkan_ke_ganti_password(): void
    {
        $user = $this->createUser();
        $user->forceFill(['current_session_id' => 'lock-viewer'])->save();

        $target = SecurityLog::create([
            'user_id' => $user->id,
            'ip_address' => '203.0.113.99',
            'user_agent' => 'Mozilla/5.0 Android',
            'device_name' => 'Firefox • Android',
            'login_at' => now()->subMinutes(1),
            'session_lock' => 'lock-other',
            'session_id' => 'sess-other',
        ]);
        $this->seedLiveSession('sess-other', $user->id);

        $this->viewer($user, 'lock-viewer')
            ->post(route('security.devices.revoke', $target->id))
            ->assertRedirect(route('profil.index'));

        // Sesi HTTP target di-invalidasi langsung dari database.
        $this->assertDatabaseMissing('sessions', ['id' => 'sess-other']);

        // User diarahkan langsung ke form Ganti Password (tab password profil).
        $this->assertSame('password', session('tab_aktif'));

        // Keamanan: baris security_logs TIDAK dihapus maupun diubah (immutable).
        $this->assertDatabaseHas('security_logs', ['id' => $target->id, 'session_id' => 'sess-other']);
    }

    public function test_putuskan_sesi_perangkat_yang_memegang_ikatan_aktif_memindahkan_ikatan_ke_pemilik(): void
    {
        // Situasi kebalikan: perangkat asing yang memegang current_session_id
        // sedang menampung ikatan aktif (pemilik memonitor lewat perangkat lain).
        $user = $this->createUser();
        $user->forceFill(['current_session_id' => 'lock-other'])->save();

        $target = SecurityLog::create([
            'user_id' => $user->id,
            'ip_address' => '203.0.113.99',
            'user_agent' => 'Mozilla/5.0 Android',
            'device_name' => 'Firefox • Android',
            'login_at' => now()->subMinutes(1),
            'session_lock' => 'lock-other',
            'session_id' => 'sess-other',
        ]);
        $this->seedLiveSession('sess-other', $user->id);

        $this->withoutMiddleware(\App\Http\Middleware\SingleDeviceSession::class)
            ->actingAs($user)
            ->withSession([SingleDeviceSessionService::LOCK_KEY => 'lock-viewer'])
            ->from(route('security.devices'))
            ->post(route('security.devices.revoke', $target->id));

        // Sesuatu berubah: baris sesi HTTP dihapus & ikatan pindah ke pemilik.
        $this->assertDatabaseMissing('sessions', ['id' => 'sess-other']);
        $user->refresh();
        $this->assertSame('lock-viewer', (string) $user->current_session_id);

        // security_logs tetap utuh (immutable).
        $this->assertDatabaseHas('security_logs', ['id' => $target->id, 'session_lock' => 'lock-other']);
    }

    public function test_tidak_bisa_memutuskan_sesi_yang_sedang_dipakai(): void
    {
        $user = $this->createUser();
        $user->forceFill(['current_session_id' => 'lock-viewer'])->save();

        $sesiSekarang = SecurityLog::create([
            'user_id' => $user->id,
            'ip_address' => '192.168.1.5',
            'user_agent' => 'Chrome',
            'device_name' => 'Chrome • Windows 10/11',
            'login_at' => now(),
            'session_lock' => 'lock-viewer',
            'session_id' => 'sess-viewer',
        ]);
        $this->seedLiveSession('sess-viewer', $user->id);

        $this->viewer($user, 'lock-viewer')
            ->from(route('security.devices'))
            ->post(route('security.devices.revoke', $sesiSekarang->id))
            ->assertRedirect(route('security.devices'))
            ->assertSessionHasErrors('device');

        // Batal: sesi sendiri tetap hidup, log tidak tersentuh.
        $this->assertDatabaseHas('sessions', ['id' => 'sess-viewer']);
        $this->assertDatabaseHas('security_logs', ['id' => $sesiSekarang->id]);
    }

    public function test_tidak_bisa_memutuskan_log_milik_user_lain(): void
    {
        $pemilik = $this->createUser();
        $orangLain = $this->createUser(['username' => 'user_lain', 'email' => 'user_lain@school.id']);

        $logPemilik = SecurityLog::create([
            'user_id' => $pemilik->id,
            'ip_address' => '203.0.113.99',
            'user_agent' => 'UA',
            'device_name' => 'Perangkat Pemilik',
            'login_at' => now(),
            'session_lock' => 'lock-other',
            'session_id' => 'sess-other',
        ]);

        $this->viewer($orangLain, 'lock-lain')
            ->post(route('security.devices.revoke', $logPemilik->id))
            ->assertNotFound();

        $this->assertDatabaseHas('security_logs', ['id' => $logPemilik->id]);
    }

    // ================= NOTIFIKASI BOT (WA / TELEGRAM) =================

    public function test_login_memicu_pesan_telegram_ke_pemilik_akun(): void
    {
        config()->set('security.notifications.telegram_bot_token', 'BOT:test-token');
        config()->set('security.notifications.telegram_chat_id', '987654321');
        Http::fake();

        $this->createUser();
        $this->post(route('login.post'), $this->loginPayload())->assertRedirect();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.telegram.org/botBOT:test-token/sendMessage')
                && $request['chat_id'] === '987654321'
                && str_contains($request['text'], 'Login Baru');
        });
    }

    public function test_konfigurasi_telegram_kosong_tidak_melakukan_request_eksternal(): void
    {
        config()->set('security.notifications.telegram_bot_token', '');
        config()->set('security.notifications.telegram_chat_id', '');
        Http::fake();

        $this->createUser();
        $this->post(route('login.post'), $this->loginPayload())->assertRedirect();

        Http::assertNothingSent();
    }

    // ================= UTILITAS PARSING DEVICE =================

    public function test_device_name_diparse_dari_user_agent(): void
    {
        $this->assertSame(
            'Chrome • Windows 10/11',
            SecurityAuditService::deviceNameFromUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/126.0.0.0')
        );
        $this->assertSame(
            'Firefox • Android',
            SecurityAuditService::deviceNameFromUserAgent('Mozilla/5.0 (Android 14; Mobile) Gecko/123.0 Firefox/127.0')
        );
        $this->assertSame(
            'Safari • macOS',
            SecurityAuditService::deviceNameFromUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 Safari/605.1.15')
        );
    }
}