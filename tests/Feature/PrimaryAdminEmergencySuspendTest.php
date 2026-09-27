<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Gembok Suspend Akun Utama 'admin' — dibuka khusus dalam Mode Darurat.
 *
 * Kebijakan: akun utama (username 'admin', Administrator TU) TIDAK dapat
 * di-suspend dalam kondisi normal (403). Gembok dibuka HANYA ketika:
 *   1. Mode Darurat aktif (session emergency_mode — hanya dapat diaktifkan
 *      Petugas IT / QA Tester), ATAU
 *   2. Aktor adalah akun hasil Emergency Super Admin Takeover "Kartu As"
 *      (is_emergency_takeover => true, di-set ItEmergencyController).
 *
 * Defense-in-depth: session 'emergency_mode' yang dipalsukan oleh pengguna
 * non-privileged (Petugas TU biasa) TIDAK memberi efek — tetap 403.
 *
 * Saat akun utama di-suspend dalam Mode Darurat, SELURUH sesi aktifnya
 * dikeluarkan seketika (tabel sessions + ikatan single-device), sehingga
 * penyadap langsung ter-logout.
 */
class PrimaryAdminEmergencySuspendTest extends TestCase
{
    use RefreshDatabase;

    private function mkAdmin(bool $testing = false): User
    {
        return User::create([
            'nama' => 'Administrator TU',
            'username' => 'admin',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'sub_role' => 'petugas_tu',
            'is_active' => true,
            'is_testing_data' => $testing,
            'kode_aktivasi' => 'ADM-SECURE-88',
        ]);
    }

    private function mkUser(string $subRole = 'petugas_tu', string $role = 'admin', bool $testing = false): User
    {
        return User::create([
            'nama' => 'User '.Str::random(4),
            'username' => 'user_'.Str::random(6),
            'password' => Hash::make('password123'),
            'role' => $role,
            'sub_role' => $subRole,
            'is_active' => true,
            'is_testing_data' => $testing,
            'kode_aktivasi' => 'AKT-'.Str::upper(Str::random(8)),
        ]);
    }

    private function mkIt(): User
    {
        return $this->mkUser('petugas_it', 'petugas_it');
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

    private function takeoverReason(): string
    {
        return 'Akun Super Admin utama dibobol dan terkunci, sistem butuh pengendali darurat.';
    }

    // ================= GEMBOK TETAP TERTUTUP TANPA MODE DARURAT =================

    public function test_suspend_akun_utama_tetap_ditolak_tanpa_mode_darurat(): void
    {
        $aktor = $this->mkUser('petugas_tu');
        $admin = $this->mkAdmin();

        $this->actingAs($aktor)
            ->post(route('admin.users.toggle-suspend', $admin->id), ['duration' => '1h'])
            ->assertForbidden();

        $this->actingAs($aktor)
            ->postJson(route('users.emergency-suspend', $admin->id))
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'is_active' => true,
            'suspended_until' => null,
        ]);
        $this->assertDatabaseCount('security_logs', 0);
    }

    // ================= TOGGLE MODE DARURAT (HANYA IT / QA) =================

    public function test_it_dapat_mengaktifkan_dan_menonaktifkan_mode_darurat(): void
    {
        $it = $this->mkIt();

        $this->actingAs($it)
            ->from(route('it.dashboard'))
            ->post(route('it.emergency-mode'), ['emergency_mode' => '1'])
            ->assertRedirect(route('it.dashboard'))
            ->assertSessionHas('success');

        $this->assertTrue($this->app['session']->get('emergency_mode'), 'Mode Darurat harus aktif setelah toggle ON.');

        $this->actingAs($it)
            ->from(route('it.dashboard'))
            ->post(route('it.emergency-mode'), ['emergency_mode' => '0'])
            ->assertRedirect(route('it.dashboard'));

        $this->assertFalse((bool) $this->app['session']->get('emergency_mode'), 'Mode Darurat harus mati setelah toggle OFF.');
    }

    public function test_mode_darurat_hanya_boleh_diaktifkan_oleh_petugas_it(): void
    {
        $tu = $this->mkUser('petugas_tu');
        $guru = $this->mkUser('guru_mapel', 'guru');

        $this->actingAs($tu)
            ->post(route('it.emergency-mode'), ['emergency_mode' => '1'])
            ->assertForbidden();

        $this->actingAs($guru)
            ->post(route('it.emergency-mode'), ['emergency_mode' => '1'])
            ->assertForbidden();

        $this->assertFalse((bool) $this->app['session']->get('emergency_mode'));
    }

    // ================= SUSPEND AKUN UTAMA SAAT MODE DARURAT =================

    public function test_it_diizinkan_suspend_akun_utama_saat_mode_darurat(): void
    {
        $it = $this->mkIt();
        // Akun utama pada partisi TESING (yang terlihat oleh Petugas IT).
        $admin = $this->mkAdmin(testing: true);
        $this->seedLiveSession('sess-admin-1', $admin->id);
        $admin->forceFill([
            'current_session_id' => 'lock-admin-1',
            'is_idle' => false,
            'last_active_at' => now(),
        ])->save();

        $this->actingAs($it)
            ->withSession(['emergency_mode' => true])
            ->from(route('admin.users.index'))
            ->post(route('admin.users.toggle-suspend', $admin->id), ['duration' => '1h'])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success');

        // (a) Status berubah: suspended_until terisi (1 jam), is_active tetap.
        $admin->refresh();
        $this->assertNotNull($admin->suspended_until);
        $this->assertTrue($admin->is_active);

        // (b) SELURUH sesi aktif akun utama dikeluarkan seketika.
        $this->assertDatabaseMissing('sessions', ['id' => 'sess-admin-1']);
        $this->assertNull($admin->current_session_id);
        $this->assertFalse($admin->is_idle);
        $this->assertNull($admin->last_active_at);

        // Tanpa audit baris (toggle-suspend berbasis waktu tidak menulis security_logs).
        $this->assertDatabaseCount('security_logs', 0);
    }

    public function test_it_diizinkan_emergency_suspend_akun_utama_saat_mode_darurat(): void
    {
        $it = $this->mkIt();
        $admin = $this->mkAdmin(testing: true);
        $this->seedLiveSession('sess-admin-1', $admin->id);

        $this->actingAs($it)
            ->withSession(['emergency_mode' => true])
            ->postJson(route('users.emergency-suspend', $admin->id))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'is_active' => false]);
        $this->assertDatabaseMissing('sessions', ['id' => 'sess-admin-1']);

        $admin->refresh();
        $this->assertNull($admin->current_session_id);

        $this->assertDatabaseHas('security_logs', [
            'user_id' => $admin->id,
            'device_name' => 'Emergency Suspend',
            'is_current_session' => false,
        ]);
    }

    // ================= DEFENSE-IN-DEPTH: SESSION DIPALSUKAN TIDAK CUKUP =================

    public function test_petugas_tu_tetap_ditolak_meski_session_mode_darurat_dipalsukan(): void
    {
        $tu = $this->mkUser('petugas_tu');
        $admin = $this->mkAdmin();

        $this->actingAs($tu)
            ->withSession(['emergency_mode' => true])
            ->post(route('admin.users.toggle-suspend', $admin->id), ['duration' => '1h'])
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'is_active' => true,
            'suspended_until' => null,
        ]);
        $this->assertDatabaseCount('security_logs', 0);
    }

    // ================= SETELAH EMERGENCY TAKEOVER ("KARTU AS") =================

    public function test_akun_setelah_takeover_diizinkan_suspend_akun_utama_produksi(): void
    {
        $it = $this->mkIt();
        // Akun utama pada partisi REAL (produksi) — yang dibobol / mustahil
        // dijangkau oleh Petugas IT sebelum takeover.
        $admin = $this->mkAdmin();
        $this->seedLiveSession('sess-prod-1', $admin->id);

        // 1) IT melakukan Emergency Takeover → menjadi Super Admin permanent.
        $this->actingAs($it)
            ->post(route('it-emergency.promote-self'), ['reason' => $this->takeoverReason()])
            ->assertRedirect(route('home'));

        $this->assertSame(User::ROLE_ADMIN, $it->role);
        $this->assertSame(User::ROLE_SUPER_ADMIN, $it->sub_role);
        $this->assertTrue($it->isEmergencyTakeover());

        // 2) Setelah takeover, akun utama PRODUKSI kini dapat di-emergency-suspend.
        $this->actingAs($it)
            ->postJson(route('users.emergency-suspend', $admin->id))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'is_active' => false]);
        $this->assertDatabaseMissing('sessions', ['id' => 'sess-prod-1']);

        $admin->refresh();
        $this->assertNull($admin->current_session_id);

        // Jejak audit: 1 baris promote + 1 baris emergency suspend.
        $this->assertSame(2, DB::table('security_logs')->count());
        $this->assertDatabaseHas('security_logs', [
            'user_id' => $admin->id,
            'device_name' => 'Emergency Suspend',
        ]);
    }

    public function test_akun_setelah_takeover_diizinkan_unsuspend_akun_utama(): void
    {
        $it = $this->mkIt();
        $admin = $this->mkAdmin();

        // 1) Takeover → pengendali darurat sistem.
        $this->actingAs($it)
            ->post(route('it-emergency.promote-self'), ['reason' => $this->takeoverReason()])
            ->assertRedirect(route('home'));

        // 2) Emergency-suspend akun utama produksi.
        $this->actingAs($it)
            ->postJson(route('users.emergency-suspend', $admin->id))
            ->assertOk();
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'is_active' => false]);

        // 3) Pengendali darurat mengembalikan akun utama (unsuspend penuh).
        $this->actingAs($it)
            ->from(route('admin.users.index'))
            ->post(route('admin.users.toggle-suspend', $admin->id), ['action' => 'unsuspend'])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success');

        $admin->refresh();
        $this->assertTrue($admin->is_active);
        $this->assertNull($admin->suspended_until);
    }

    // ================= UI / VIEW =================

    public function test_index_menampilkan_tombol_suspend_akun_utama_saat_mode_darurat(): void
    {
        $it = $this->mkIt();
        $admin = $this->mkAdmin(testing: true);

        // Tanpa Mode Darurat: badge "Akun Utama (Tidak dapat di-suspend)".
        $this->actingAs($it)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Akun Utama (Tidak dapat di-suspend)')
            ->assertDontSee(route('admin.users.toggle-suspend', $admin->id), false);

        // Mode Darurat aktif: tombol "Suspend Darurat" akun utama dibuka.
        $this->actingAs($it)
            ->withSession(['emergency_mode' => true])
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertDontSee('Akun Utama (Tidak dapat di-suspend)')
            ->assertSee('Suspend Darurat')
            ->assertSee(route('admin.users.toggle-suspend', $admin->id), false);
    }

    public function test_index_menampilkan_tombol_suspend_akun_utama_setelah_takeover(): void
    {
        $it = $this->mkIt();
        $admin = $this->mkAdmin();

        $this->actingAs($it)
            ->post(route('it-emergency.promote-self'), ['reason' => $this->takeoverReason()])
            ->assertRedirect(route('home'));

        $this->actingAs($it)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertDontSee('Akun Utama (Tidak dapat di-suspend)')
            ->assertSee('Suspend Darurat')
            ->assertSee(route('admin.users.toggle-suspend', $admin->id), false);
    }
}