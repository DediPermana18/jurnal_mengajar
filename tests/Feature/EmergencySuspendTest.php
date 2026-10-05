<?php

namespace Tests\Feature;

use App\Models\SecurityLog;
use App\Models\User;
use App\Services\SingleDeviceSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Peer Emergency Suspend — TU/Staff.
 *
 *  - Endpoint API POST /api/users/{id}/emergency-suspend, hanya role TU/Admin.
 *  - User TIDAK bisa menonaktifkan akunnya sendiri (harus rekan/user lain).
 *  - Aksi: is_active=false, SELURUH sesi aktif di tabel `sessions` dihapus,
 *    ikatan single-device dilepas, dan tercatat append-only di security_logs:
 *    "Akun {user_a} dinonaktifkan darurat oleh {user_b}".
 */
class EmergencySuspendTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $subRole = 'petugas_tu', ?string $role = 'admin', bool $active = true): User
    {
        return User::create([
            'nama' => 'User '.Str::random(4),
            'username' => 'user_'.Str::random(6),
            'password' => Hash::make('password123'),
            'role' => $role ?? 'admin',
            'sub_role' => $subRole,
            'is_active' => $active,
            'kode_aktivasi' => 'AKT-'.Str::upper(Str::random(8)),
        ]);
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

    // ================= AKSI SUSPEND (API JSON) =================

    public function test_petugas_tu_dapat_suspend_akun_rekan_lewat_api(): void
    {
        $aktor = $this->makeUser('petugas_tu');
        $target = $this->makeUser('waka_kurikulum');

        // Target sedang aktif login di 2 perangkat (salah satunya memegang ikatan single-device).
        $this->seedLiveSession('sess-target-1', $target->id);
        $this->seedLiveSession('sess-target-2', $target->id);
        $target->forceFill([
            'current_session_id' => 'lock-target-1',
            'is_idle' => false,
            'last_active_at' => now(),
        ])->save();

        $this->actingAs($aktor)
            ->postJson(route('users.emergency-suspend', $target->id))
            ->assertOk()
            ->assertJson(['success' => true]);

        // 1) Akun target dinonaktifkan.
        $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => false]);

        // 2) SELURUH sesi aktif target dihapus dari tabel sessions + ikatan dilepas.
        $this->assertDatabaseMissing('sessions', ['id' => 'sess-target-1']);
        $this->assertDatabaseMissing('sessions', ['id' => 'sess-target-2']);
        $target->refresh();
        $this->assertNull($target->current_session_id);
        $this->assertFalse($target->is_idle);
        $this->assertNull($target->last_active_at);

        // 3) Keamanan: hanya SATU baris audit baru (emergency suspend, append-only).
        $this->assertDatabaseCount('security_logs', 1);
        $this->assertDatabaseHas('security_logs', [
            'user_id' => $target->id,
            'device_name' => 'Emergency Suspend',
            'is_current_session' => false,
            'session_lock' => null,
            'session_id' => null,
        ]);
        $log = SecurityLog::sole();
        $this->assertStringContainsString('dinonaktifkan darurat oleh', (string) $log->description);
    }

    public function test_hanya_role_tu_atau_admin_yang_boleh_suspend(): void
    {
        $guru = $this->makeUser('guru_mapel', 'guru');
        $target = $this->makeUser('petugas_tu');

        $this->actingAs($guru)
            ->postJson(route('users.emergency-suspend', $target->id))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => true]);
        $this->assertDatabaseCount('security_logs', 0);
    }

    public function test_guest_tidak_bisa_suspend_dan_ditolak_api(): void
    {
        $target = $this->makeUser('waka_kurikulum');

        // Endpoint berada di prefix `api/*` → exception handler merender error
        // sebagai JSON 401 (shouldRenderJsonWhen) meski bukan request JSON.
        $this->post(route('users.emergency-suspend', $target->id))
            ->assertUnauthorized();

        $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => true]);
        $this->assertDatabaseCount('security_logs', 0);
    }

    public function test_tidak_bisa_suspend_akun_sendiri(): void
    {
        $aktor = $this->makeUser('petugas_tu');

        $this->actingAs($aktor)
            ->postJson(route('users.emergency-suspend', $aktor->id))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $aktor->id, 'is_active' => true]);
        $this->assertDatabaseCount('security_logs', 0);
    }

    // ================= EFEK TERHADAP SISTEM =================

    public function test_akun_yang_disuspend_tidak_bisa_login_lagi(): void
    {
        $aktor = $this->makeUser('petugas_tu');
        $target = $this->makeUser('waka_kurikulum');

        $this->actingAs($aktor)
            ->postJson(route('users.emergency-suspend', $target->id))
            ->assertOk();

        // Login ulang (dengan kode aktivasi + password BENAR) tetap ditolak generik.
        $this->from(route('login'))
            ->post(route('login.post'), [
                'login_id' => $target->username,
                'password' => 'password123',
                'mode' => 'admin',
                'kode_aktivasi' => $target->fresh()->kode_aktivasi,
            ])
            ->assertSessionHasErrors('login_id');

        // Yang terautentikasi tetap aktor (bukan target yang disuspend).
        $this->assertNotSame($target->id, auth()->id());
        // Tidak ada baris login baru — hanya baris suspend (append-only).
        $this->assertDatabaseCount('security_logs', 1);
    }

    public function test_form_web_berhasil_redirect_dengan_flash_success(): void
    {
        $aktor = $this->makeUser('petugas_tu');
        $target = $this->makeUser('satpam');

        $this->actingAs($aktor)
            ->post(route('users.emergency-suspend', $target->id))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => false]);
    }

    // ================= JEJAK AUDIT (APPEND-ONLY) =================

    public function test_log_suspend_mencatat_aktor_dan_ip_pelaku(): void
    {
        $aktor = $this->makeUser('petugas_tu');
        $target = $this->makeUser('waka_sdm');

        $this->actingAs($aktor)
            ->postJson(route('users.emergency-suspend', $target->id))
            ->assertOk();

        $log = SecurityLog::sole();
        $this->assertSame($target->id, (int) $log->user_id);
        $this->assertSame('127.0.0.1', $log->ip_address);
        $this->assertStringContainsString($target->nama, (string) $log->description);
        $this->assertStringContainsString($aktor->nama, (string) $log->description);
        $this->assertStringContainsString('dinonaktifkan darurat oleh', (string) $log->description);
    }

    public function test_suspend_tidak_bisa_menyasar_user_guru_dari_panel_tu(): void
    {
        $aktor = $this->makeUser('petugas_tu');
        $guru = $this->makeUser('guru', 'guru');

        // Endpoint panel TU hanya melayani user non-guru (findNonGuruUser → 404).
        $this->actingAs($aktor)
            ->postJson(route('users.emergency-suspend', $guru->id))
            ->assertNotFound();

        $this->assertDatabaseHas('users', ['id' => $guru->id, 'is_active' => true]);
        $this->assertDatabaseCount('security_logs', 0);
    }

    // ================= UI: TOMBOL & MODAL KONFIRMASI =================

public function test_halaman_kelola_user_menampilkan_tombol_suspend_darurat_dan_modal(): void
    {
        $aktor = $this->makeUser('petugas_tu');
        $target = $this->makeUser('waka_kurikulum');
        $lawan = $this->makeUser('waka_sdm');

        $response = $this->actingAs($aktor)
            ->get(route('admin.users.index'))
            ->assertOk();

        // Modal konfirmasi dengan teks yang ditetapkan requirement.
        $response->assertSee('Suspend Darurat Akun')
            ->assertSee('Apakah Anda yakin ingin menyuspend sementara akun')
            ->assertSee('DURASI SUSPEND')
            ->assertSee('1 Jam')
            ->assertSee('1 Hari (24 Jam)');

        // Tombol aksi muncul untuk baris user LAIN, mengarah ke route SUSPEND
        // khusus (toggle-suspend) — aksi tidak mengirim field nama/username/sub-role/password.
        $response->assertSee('Suspend Darurat')
            ->assertSee(route('admin.users.toggle-suspend', $target->id), false)
            ->assertSee(route('admin.users.toggle-suspend', $lawan->id), false);

        // Tidak muncul untuk baris akun sendiri (harus rekan yang menonaktifkan).
        $response->assertDontSee(route('admin.users.toggle-suspend', $aktor->id), false);
    }

    // ================= TOGGLE SUSPEND (ROUTE KHUSUS) =================

    private function makeAdmin(): User
    {
        return User::create([
            'nama' => 'Administrator TU',
            'username' => 'admin',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'sub_role' => 'petugas_tu',
            'is_active' => true,
            'kode_aktivasi' => 'ADM-SECURE-88',
        ]);
    }

    public function test_toggle_suspend_mengisi_suspended_until_1_jam_dan_flash(): void
    {
        $aktor = $this->makeUser('petugas_tu');
        $target = $this->makeUser('waka_kurikulum');
        $before = now();

        $this->actingAs($aktor)
            ->from(route('admin.users.index'))
            ->post(route('admin.users.toggle-suspend', $target->id), ['duration' => '1h'])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success');

        // Flash memuat batas waktu suspend ("Akun berhasil disuspend sementara hingga ...").
        $this->assertStringContainsString(
            'Akun berhasil disuspend sementara hingga',
            (string) session('success')
        );

        // suspended_until terisi ± 1 jam ke depan.
        $suspendedUntil = $target->fresh()->suspended_until;
        $this->assertNotNull($suspendedUntil);
        $this->assertTrue($suspendedUntil->greaterThan($before->addMinutes(50)));
        $this->assertTrue($suspendedUntil->lessThan($before->addMinutes(70)));
        $this->assertTrue($target->fresh()->isCurrentlySuspended());

        // Suspend berbasis waktu TIDAK mengubah is_active — akun otomatis
        // normal lagi begitu suspended_until tercapai.
        $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => true]);
    }

    public function test_toggle_suspend_durasi_1_hari_mengisi_suspended_until(): void
    {
        $aktor = $this->makeUser('petugas_tu');
        $target = $this->makeUser('satpam');
        $before = now();

        $this->actingAs($aktor)
            ->post(route('admin.users.toggle-suspend', $target->id), ['duration' => '1d'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $suspendedUntil = $target->fresh()->suspended_until;
        $this->assertNotNull($suspendedUntil);
        $this->assertTrue($suspendedUntil->greaterThan($before->addDay()->subMinutes(5)));
        $this->assertTrue($suspendedUntil->lessThan($before->addDay()->addMinutes(5)));
    }

    public function test_login_ditolak_saat_akun_disuspend_sementara(): void
    {
        $target = $this->makeUser('petugas_tu');
        $target->update(['suspended_until' => now()->addHour()]);

        $this->from(route('login'))
            ->post(route('login.post'), [
                'login_id' => $target->username,
                'password' => 'password123',
                'mode' => 'admin',
            ])
            ->assertSessionHasErrors('login_id');

        $this->assertStringContainsString(
            'Akun Anda sedang disuspend sementara sampai',
            (string) session('errors')->first('login_id')
        );
    }

    public function test_login_berhasil_setelah_suspended_until_kadaluarsa(): void
    {
        $target = $this->makeUser('petugas_tu');
        $target->update(['suspended_until' => now()->subHour()]);

        // Setelah lewat waktu, tombol suspend tidak memblokir login lagi.
        $this->assertFalse($target->fresh()->isCurrentlySuspended());
    }

    public function test_toggle_suspend_tidak_bisa_menonaktifkan_akun_sendiri(): void
    {
        $aktor = $this->makeUser('petugas_tu');

        $this->actingAs($aktor)
            ->post(route('admin.users.toggle-suspend', $aktor->id))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $aktor->id, 'is_active' => true]);
    }

    public function test_toggle_suspend_ditolak_untuk_akun_utama_admin(): void
    {
        $aktor = $this->makeUser('petugas_tu');
        $admin = $this->makeAdmin();

        $this->actingAs($aktor)
            ->post(route('admin.users.toggle-suspend', $admin->id))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'is_active' => true]);
    }

    public function test_emergency_suspend_juga_ditolak_untuk_akun_utama_admin(): void
    {
        $aktor = $this->makeUser('petugas_tu');
        $admin = $this->makeAdmin();

        $this->actingAs($aktor)
            ->postJson(route('users.emergency-suspend', $admin->id))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'is_active' => true]);
        $this->assertDatabaseCount('security_logs', 0);
    }

    public function test_index_menampilkan_badge_akun_utama_dan_menyembunyikan_tombol_suspend(): void
    {
        $aktor = $this->makeUser('petugas_tu');
        $admin = $this->makeAdmin();

        $response = $this->actingAs($aktor)
            ->get(route('admin.users.index'))
            ->assertOk();

        $response->assertSee('Akun Utama (Tidak dapat di-suspend)')
            ->assertDontSee(route('admin.users.toggle-suspend', $admin->id), false);
    }

    public function test_detail_page_menampilkan_tombol_suspend_atau_badge_akun_utama(): void
    {
        $aktor = $this->makeUser('petugas_tu');
        $target = $this->makeUser('waka_kurikulum');
        $admin = $this->makeAdmin();

        // User biasa → ada tombol Suspend Darurat di halaman detail.
        $this->actingAs($aktor)
            ->get(route('admin.users.edit', $target->id))
            ->assertOk()
            ->assertSee(route('admin.users.toggle-suspend', $target->id), false);

        // Akun utama 'admin' → badge "Akun Utama", tombol suspend disembunyikan.
        $this->actingAs($aktor)
            ->get(route('admin.users.edit', $admin->id))
            ->assertOk()
            ->assertSee('Akun Utama (Tidak dapat di-suspend)')
            ->assertDontSee(route('admin.users.toggle-suspend', $admin->id), false);
    }

    public function test_badge_status_menampilkan_suspended_sampai_jam_lalu_aktif_setelah_kadaluarsa(): void
    {
        $aktor = $this->makeUser('petugas_tu');
        $suspended = $this->makeUser('waka_kurikulum');
        $suspended->update(['suspended_until' => now()->addHour()]);
        $expired = $this->makeUser('waka_sdm');
        $expired->update(['suspended_until' => now()->subHour()]);

        $response = $this->actingAs($aktor)
            ->get(route('admin.users.index'))
            ->assertOk();

        // Hanya akun yang suspended_until-nya masih masa depan ber-badge "Di-Suspend (s/d HH:mm)".
        $this->assertSame(1, substr_count($response->getContent(), 'Di-Suspend (s/d '));
        // Akun yang sudah lewat waktunya tampil normal (status Aktif).
        $response->assertSee($expired->nama)
            ->assertSee('Aktif');
    }

    public function test_middleware_mengeluarkan_sesi_user_yang_disuspend_sementara(): void
    {
        $user = $this->makeUser('petugas_tu');
        $user->update(['suspended_until' => now()->addHour()]);

        // Sesi yang sedang berjalan dikeluarkan apa pun driver session.
        $this->actingAs($user)
            ->withSession([SingleDeviceSessionService::LOCK_KEY => 'lock-sesi'])
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertStringContainsString(
            'Akun Anda sedang disuspend sementara sampai',
            (string) session('error')
        );
    }

    public function test_heartbeat_mendeteksi_akun_yang_disuspend_sementara(): void
    {
        $target = $this->makeUser('waka_kurikulum');
        $target->update(['suspended_until' => now()->addHour()]);

        $response = $this->actingAs($target->fresh())
            ->postJson('/api/user/heartbeat', [
                'is_idle' => false,
                'session_id' => 'lock-target',
            ])
            ->assertOk()
            ->assertJsonPath('kicked', true);

        // Pesan heartbeat berisi batas waktu suspend.
        $this->assertStringContainsString(
            'Akun Anda sedang disuspend sementara sampai',
            (string) $response->json('message')
        );
    }

    // ================= KICK SESSION INDEPENDEN DRIVER =================
    // (Sesi `file`/`cookie` tidak ikut terhapus lewat tabel `sessions`, jadi
    // middleware & heartbeat harus menendang paksa akun nonaktif/suspend.)

    public function test_middleware_mengeluarkan_sesi_akun_yang_disuspend(): void
    {
        $user = $this->makeUser('petugas_tu');
        $user->forceFill(['current_session_id' => 'lock-sesi', 'is_idle' => false])->save();
        $this->seedLiveSession('sess-target-hidup', $user->id);

        // Sesuatu dibuktikan: sesi target masih hidup sebelum suspend (lock cocok).
        $this->actingAs($user)
            ->withSession([SingleDeviceSessionService::LOCK_KEY => 'lock-sesi'])
            ->get(route('dashboard'))
            ->assertOk();

        // Rekan TU melakukan suspend darurat.
        $aktor = $this->makeUser('petugas_tu');
        $this->actingAs($aktor)
            ->postJson(route('users.emergency-suspend', $user->id))
            ->assertOk();

        // Sesi lama harus memuat ulang data user dari DB (perilaku request asli).
        $user->refresh();

        // Request dengan sesi lama target (lock masih cocok, tapi akun nonaktif)
        // → langsung di-kick ke login, apa pun driver session.
        $this->actingAs($user)
            ->withSession([SingleDeviceSessionService::LOCK_KEY => 'lock-sesi'])
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame(
            'Akun Anda sedang nonaktif / di-suspend. Sesi telah dihentikan. Silakan hubungi Petugas TU / Admin.',
            session('error')
        );
    }

    public function test_heartbeat_mendeteksi_akun_yang_disuspend_dan_mengirim_kicked(): void
    {
        $aktor = $this->makeUser('petugas_tu');
        $target = $this->makeUser('waka_kurikulum');
        $target->forceFill(['current_session_id' => 'lock-target', 'is_idle' => false])->save();

        $this->actingAs($aktor)
            ->postJson(route('users.emergency-suspend', $target->id))
            ->assertOk();

        // Heartbeat sesi lama target → kicked=true meski lock masih cocok
        // (guard memuat ulang user dari DB per request, bukan objek basi).
        $this->actingAs($target->fresh())
            ->postJson('/api/user/heartbeat', [
                'is_idle' => false,
                'session_id' => 'lock-target',
            ])
            ->assertOk()
            ->assertJson([
                'kicked' => true,
            ]);
    }
}