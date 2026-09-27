<?php

namespace Tests\Feature;

use App\Models\SecurityLog;
use App\Models\User;
use App\Services\DeviceSignatureService;
use App\Services\SingleDeviceSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Nama Perangkat Generik → Identitas Unik via Sidik Jari (Fingerprint).
 *
 *  - Nama perangkat dipecah presisi dari Client Hints (Sec-CH-UA-*) + UA,
 *    termasuk MODEL perangkat (Android biasanya termuat di UA; iOS/Chrome
 *    lewat header Sec-CH-UA-Model high-entropy).
 *  - Sidik jari terbentuk dari model/engine/browser + data frontend
 *    (screen, timezone, language, cores, touch) — STABIL per perangkat,
 *    TANPA IP (IP berubah tiap ganti jaringan seluler).
 *  - Login dari fingerprint yang belum pernah terlihat → is_unknown_device
 *    (append-only, diset sekali saat insert) → badge "Perangkat Baru /
 *    Tak Dikenal" di halaman Perangkat & Keamanan.
 *  - "Beri Nama Perangkat Ini" menyimpan nama kustom di `security_devices`
 *    (bukan audit — security_logs tetap immutable).
 */
class DeviceFingerprintTest extends TestCase
{
    use RefreshDatabase;

    private const UA_SAMSUNG = 'Mozilla/5.0 (Linux; Android 13; SM-S918B Build/TP1A.220624.014) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Mobile Safari/537.36';

    private const UA_ANDROID_GENERIC = 'Mozilla/5.0 (Android 14; Mobile) Gecko/123.0 Firefox/127.0';

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

    private function viewer(User $user, string $lock)
    {
        return $this->actingAs($user)
            ->withSession([SingleDeviceSessionService::LOCK_KEY => $lock]);
    }

    private function deviceMeta(array $overrides = []): string
    {
        return json_encode(array_merge([
            'screen' => [1080, 2340, 24],
            'timezone' => 'Asia/Jakarta',
            'language' => 'id-ID',
            'cores' => 8,
            'touch' => 5,
        ], $overrides), JSON_UNESCAPED_UNICODE);
    }

    // ================= PARSER UA & CLIENT HINTS =================

    public function test_model_android_diextract_dari_user_agent(): void
    {
        $service = app(DeviceSignatureService::class);
        $request = Request::create('/', 'POST', [], [], [], [
            'HTTP_USER_AGENT' => self::UA_SAMSUNG,
        ]);

        $signature = $service->fromRequest($request);

        $this->assertStringContainsString('Samsung SM-S918B', $signature['device_name']);
        $this->assertStringContainsString('Chrome', $signature['device_name']);
        $this->assertFalse($signature['is_generic']);
        $this->assertTrue($signature['device_meta']['model'] === 'SM-S918B');
        $this->assertNotEmpty($signature['device_fingerprint']);
    }

    public function test_ua_generik_tanpa_model_dianggap_generic(): void
    {
        $service = app(DeviceSignatureService::class);
        $request = Request::create('/', 'POST', [], [], [], [
            'HTTP_USER_AGENT' => self::UA_ANDROID_GENERIC,
        ]);

        $signature = $service->fromRequest($request);

        $this->assertStringContainsString('Firefox', $signature['device_name']);
        $this->assertStringContainsString('Android', $signature['device_name']);
        // Tanpa model & tanpa screen/timezone → benar-benar generik "Firefox • Android".
        $this->assertTrue($signature['is_generic']);
        $this->assertEmpty($signature['device_meta']['model']);
    }

    public function test_client_hints_model_diprioritaskan_atas_ua(): void
    {
        $service = app(DeviceSignatureService::class);
        // UA generik, tapi Client Hints memberi tahu model sebenarnya (iOS misalnya).
        $request = Request::create('/', 'POST', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1',
            'HTTP_SEC_CH_UA_MODEL' => 'iPhone17,3',
            'HTTP_SEC_CH_UA_PLATFORM' => '"iOS"',
            'HTTP_SEC_CH_UA_PLATFORM_VERSION' => '"17.4"',
            'HTTP_SEC_CH_UA' => '"Not)A;Brand";v="99"; "Safari";v="17.4"; "Mobile Safari";v="17.4"',
        ]);

        $signature = $service->fromRequest($request);

        $this->assertStringContainsString('iPhone17,3', $signature['device_name']);
        $this->assertFalse($signature['is_generic']);
        $this->assertSame('iOS', $signature['device_meta']['os']);
        $this->assertSame('17.4', $signature['device_meta']['os_version']);
    }

    public function test_fingerprint_stabil_per_perangkat_dan_unik_per_lainnya(): void
    {
        $service = app(DeviceSignatureService::class);

        $make = function (string $ip, array $meta) use ($service): ?string {
            $request = Request::create('/', 'POST', [], [], [], [
                'REMOTE_ADDR' => $ip,
                'HTTP_USER_AGENT' => self::UA_SAMSUNG,
            ]);

            return $service->fromRequest($request, $meta)['device_fingerprint'];
        };

        $a1 = $make('36.84.1.1', ['screen' => [1080, 2340, 24], 'timezone' => 'Asia/Jakarta', 'cores' => 8, 'touch' => 5]);
        $a2 = $make('36.84.9.9', ['screen' => [1080, 2340, 24], 'timezone' => 'Asia/Jakarta', 'cores' => 8, 'touch' => 5]);

        // Perangkat SAMA walau IP berganti (ganti jaringan seluler) → hash sama.
        $this->assertNotNull($a1);
        $this->assertSame($a1, $a2);

        // Perangkat BEDA (iPhone dengan UA + screen + timezone beda) → hash beda.
        $b = $make('36.84.5.5', ['screen' => [1170, 2532, 24], 'timezone' => 'Asia/Makassar', 'cores' => 6, 'touch' => 5]);
        $this->assertNotSame($a1, $b);
    }

    public function test_fingerprint_tidak_memakai_ip(): void
    {
        $service = app(DeviceSignatureService::class);
        $meta = ['screen' => [1080, 2340, 24], 'timezone' => 'Asia/Jakarta'];

        $viaIpA = $service->fromRequest(
            Request::create('/', 'POST', [], [], [], ['REMOTE_ADDR' => '1.2.3.4', 'HTTP_USER_AGENT' => self::UA_SAMSUNG]),
            $meta
        )['device_fingerprint'];
        $viaIpB = $service->fromRequest(
            Request::create('/', 'POST', [], [], [], ['REMOTE_ADDR' => '8.8.8.8', 'HTTP_USER_AGENT' => self::UA_SAMSUNG]),
            $meta
        )['device_fingerprint'];

        $this->assertSame($viaIpA, $viaIpB);
    }

    // ================= PENCATATAN LOGIN (append-only) =================

    public function test_login_pertama_perangkat_baru_ditandai_unknown_lalu_kedua_dikenal(): void
    {
        $this->createUser();

        $payload = $this->loginPayload() + ['device_meta' => $this->deviceMeta()];

        // Login #1 (perangkat belum pernah terlihat) → is_unknown_device = true.
        $this->withHeaders(['User-Agent' => self::UA_SAMSUNG])
            ->post(route('login.post'), $payload)
            ->assertRedirect();
        $pertama = SecurityLog::sole();
        $this->assertNotEmpty($pertama->device_fingerprint);
        $this->assertTrue($pertama->is_unknown_device);
        $this->assertIsArray($pertama->device_meta);
        $this->assertSame('Asia/Jakarta', $pertama->device_meta['timezone']);

        // Login #2 perangkat yang sama → fingerprint sama, sudah DIKENAL.
        $this->withHeaders(['User-Agent' => self::UA_SAMSUNG])
            ->post(route('login.post'), $payload)
            ->assertRedirect();
        $kedua = SecurityLog::latest('id')->first();
        $this->assertSame($pertama->device_fingerprint, $kedua->device_fingerprint);
        $this->assertFalse($kedua->is_unknown_device);
    }

    public function test_login_ua_generik_tetap_punya_fingerprint_dan_nama_generik(): void
    {
        $this->createUser();

        $this->withHeaders(['User-Agent' => self::UA_ANDROID_GENERIC])
            ->post(route('login.post'), $this->loginPayload())
            ->assertRedirect();

        $log = SecurityLog::sole();
        $this->assertNotEmpty($log->device_fingerprint);
        $this->assertNotEmpty($log->device_name);
        $this->assertTrue($log->device_meta['is_generic'] ?? false);
        // Nama generik: hanya browser • OS, tanpa model dalam kurung.
        $this->assertStringNotContainsString('(', (string) $log->device_name);
    }

    // ================= HALAMAN PERANGKAT & KEAMANAN =================

    public function test_halaman_menampilkan_tag_fingerprint_dan_badge_perangkat_baru(): void
    {
        $user = $this->createUser();
        $user->forceFill(['current_session_id' => 'lock-viewer'])->save();

        SecurityLog::create([
            'user_id' => $user->id,
            'ip_address' => '203.0.113.99',
            'user_agent' => self::UA_SAMSUNG,
            'device_name' => 'Chrome 122 • Android 13 (Samsung SM-S918B)',
            'device_fingerprint' => 'aabbccddeeff0011',
            'device_meta' => ['model' => 'SM-S918B', 'os' => 'Android', 'timezone' => 'Asia/Jakarta', 'is_generic' => false],
            'is_unknown_device' => true,
            'login_at' => now(),
            'session_lock' => 'lock-other',
            'session_id' => 'sess-other',
        ]);

        $this->viewer($user, 'lock-viewer')
            ->get(route('security.devices'))
            ->assertOk()
            ->assertSee('Perangkat & Keamanan')
            ->assertSee('FP #aabbccdd')
            ->assertSee('Perangkat Baru / Tak Dikenal')
            ->assertSee('Beri Nama Perangkat Ini');
    }

    public function test_nama_generik_ditampilkan_penanda_ip_dan_waktu_login_terakhir(): void
    {
        $user = $this->createUser();
        $user->forceFill(['current_session_id' => 'lock-viewer'])->save();

        SecurityLog::create([
            'user_id' => $user->id,
            'ip_address' => '203.0.113.99',
            'user_agent' => self::UA_ANDROID_GENERIC,
            'device_name' => 'Firefox 127.0 • Android 14',
            'device_fingerprint' => '1122334455667788',
            'device_meta' => ['model' => null, 'os' => 'Android', 'is_generic' => true],
            'is_unknown_device' => false,
            'login_at' => now()->subMinutes(3),
            'session_lock' => 'lock-other',
            'session_id' => 'sess-other',
        ]);

        // Penanda disambiguasi: IP disamarkan + FP — bukan ISO hash penuh.
        $this->viewer($user, 'lock-viewer')
            ->get(route('security.devices'))
            ->assertOk()
            ->assertSee('FP #11223344')
            ->assertSee('203.0.***.99');
    }

    // ================= BERI NAMA PERANGKAT INI =================

    public function test_pemilik_bisa_menamai_perangkatnya_dan_namanya_terpakai_di_halaman(): void
    {
        $user = $this->createUser();
        $user->forceFill(['current_session_id' => 'lock-viewer'])->save();

        SecurityLog::create([
            'user_id' => $user->id,
            'ip_address' => '203.0.113.99',
            'user_agent' => self::UA_SAMSUNG,
            'device_name' => 'Chrome 122 • Android 13 (Samsung SM-S918B)',
            'device_fingerprint' => 'aabbccddeeff0011',
            'device_meta' => ['model' => 'SM-S918B', 'is_generic' => false],
            'login_at' => now(),
            'session_lock' => 'lock-other',
            'session_id' => 'sess-other',
        ]);

        $this->viewer($user, 'lock-viewer')
            ->post(route('security.devices.name'), [
                'fingerprint' => 'aabbccddeeff0011',
                'name' => 'HP Utama',
            ])
            ->assertRedirect()
            ->assertSessionHas('success_naming');

        $this->assertDatabaseHas('security_devices', [
            'user_id' => $user->id,
            'fingerprint' => 'aabbccddeeff0011',
            'name' => 'HP Utama',
        ]);

        // Nama kustom muncul sebagai identitas utama; log lama tidak tersentuh.
        $this->viewer($user, 'lock-viewer')
            ->get(route('security.devices'))
            ->assertOk()
            ->assertSee('HP Utama')
            ->assertSee('Nama Anda')
            ->assertSee('Ganti Nama Perangkat');

        $this->assertDatabaseHas('security_logs', ['device_name' => 'Chrome 122 • Android 13 (Samsung SM-S918B)']);
    }

    public function test_mengkosongkan_nama_menghapus_nama_kustom(): void
    {
        $user = $this->createUser();
        $user->forceFill(['current_session_id' => 'lock-viewer'])->save();

        SecurityLog::create([
            'user_id' => $user->id,
            'ip_address' => '203.0.113.99',
            'user_agent' => self::UA_SAMSUNG,
            'device_name' => 'Chrome 122 • Android 13 (Samsung SM-S918B)',
            'device_fingerprint' => 'aabbccddeeff0011',
            'device_meta' => ['model' => 'SM-S918B', 'is_generic' => false],
            'login_at' => now(),
            'session_lock' => 'lock-other',
            'session_id' => 'sess-other',
        ]);

        $this->viewer($user, 'lock-viewer')
            ->post(route('security.devices.name'), [
                'fingerprint' => 'aabbccddeeff0011',
                'name' => 'HP Utama',
            ])->assertRedirect();

        $this->viewer($user, 'lock-viewer')
            ->post(route('security.devices.name'), [
                'fingerprint' => 'aabbccddeeff0011',
                'name' => '',
            ])->assertRedirect();

        $this->assertDatabaseMissing('security_devices', [
            'user_id' => $user->id,
            'fingerprint' => 'aabbccddeeff0011',
        ]);
    }

    public function test_tidak_bisa_menamai_fingerprint_bukan_milik_akun(): void
    {
        $pemilik = $this->createUser();
        $orangLain = $this->createUser(['username' => 'user_lain', 'email' => 'user_lain@school.id']);

        SecurityLog::create([
            'user_id' => $pemilik->id,
            'ip_address' => '203.0.113.99',
            'user_agent' => self::UA_SAMSUNG,
            'device_name' => 'Chrome 122 • Android 13 (Samsung SM-S918B)',
            'device_fingerprint' => 'aabbccddeeff0011',
            'device_meta' => ['model' => 'SM-S918B', 'is_generic' => false],
            'login_at' => now(),
            'session_lock' => 'lock-other',
            'session_id' => 'sess-other',
        ]);

        $this->viewer($orangLain, 'lock-lain')
            ->post(route('security.devices.name'), [
                'fingerprint' => 'aabbccddeeff0011',
                'name' => 'Penyadap',
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('security_devices', 0);
    }

    // ================= NOTIFIKASI BOT =================

    public function test_notifikasi_telegram_mencantumkan_sidik_jari_dan_penanda_perangkat_baru(): void
    {
        config()->set('security.notifications.telegram_bot_token', 'BOT:test-token');
        config()->set('security.notifications.telegram_chat_id', '987654321');
        Http::fake();

        $this->createUser();
        $this->withHeaders(['User-Agent' => self::UA_SAMSUNG])
            ->post(route('login.post'), $this->loginPayload() + ['device_meta' => $this->deviceMeta()])
            ->assertRedirect();

        $fp = (string) SecurityLog::sole()->device_fingerprint;

        Http::assertSent(function ($request) use ($fp) {
            return str_contains($request->url(), 'api.telegram.org/botBOT:test-token/sendMessage')
                && str_contains($request['text'], 'Login Baru')
                && str_contains($request['text'], 'FP #'.substr($fp, 0, 8))
                && str_contains($request['text'], 'Perangkat Baru / Tak Dikenal');
        });
    }
}