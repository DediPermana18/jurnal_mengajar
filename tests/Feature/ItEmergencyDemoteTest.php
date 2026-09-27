<?php

namespace Tests\Feature;

use App\Models\Scopes\TestingDataScope;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Demote/Demote-self pasca Emergency Super Admin Takeover ("Kartu As").
 *
 * Setelah Petugas IT / QA Tester mempromosikan dirinya menjadi Super Admin
 * permanen (promote-self), UI Switcher/Mode IT hilang. Endpoint
 * POST /admin/it-emergency/demote-self mengembalikan akun ke identitas
 * IT/QA asli:
 *  - role      = 'admin'   (format valid di kolom role MySQL ENUM('admin','guru')),
 *  - sub_role  = 'petugas_it' / 'qa_tester' (snapshot asal saat promote),
 *  - is_emergency_takeover & emergency_origin_role dibersihkan,
 *  - redirect ke Dashboard IT (akun kembali diakui sebagai Petugas IT).
 *
 * Keamanan:
 *  - HANYA akun is_emergency_takeover=true (hasil takeover asli) yang boleh
 *    demote — Super Admin biasa / IT tanpa takeover ditolak 403.
 *  - Jejak audit append-only di security_logs (device_name 'Emergency Demote').
 */
class ItEmergencyDemoteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UserSeeder::class);
    }

    private function itUser(): User
    {
        return User::withoutGlobalScope(TestingDataScope::class)
            ->where('email', 'it@school.id')->firstOrFail();
    }

    private function qaUser(): User
    {
        return User::withoutGlobalScope(TestingDataScope::class)
            ->where('email', 'qa@school.id')->firstOrFail();
    }

    private function mkSuperAdmin(string $username): User
    {
        return User::create([
            'username' => $username,
            'nama' => 'Super Admin '.$username,
            'email' => $username.'@school.id',
            'password' => 'secret',
            'role' => User::ROLE_SUPER_ADMIN,
            'sub_role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);
    }

    private function reason(): string
    {
        return 'Akun Super Admin utama dibobol dan terkunci, sistem butuh pengendali darurat.';
    }

    private function takeOver(User $user): void
    {
        $this->actingAs($user)
            ->post(route('it-emergency.promote-self'), ['reason' => $this->reason()])
            ->assertRedirect(route('home'));
    }

    private function fresh(User $user): User
    {
        return User::withoutGlobalScope(TestingDataScope::class)->findOrFail($user->id);
    }

    // ================= GATE: HANYA AKUN HASIL TAKEOVER =================

    public function test_demote_ditolak_untuk_super_admin_biasa(): void
    {
        $sa = $this->mkSuperAdmin('sa.asing');

        $this->actingAs($sa)
            ->post(route('it-emergency.demote-self'))
            ->assertForbidden();

        $sa = $this->fresh($sa);
        $this->assertSame(User::ROLE_SUPER_ADMIN, $sa->sub_role);
        $this->assertFalse($sa->is_emergency_takeover);
        $this->assertDatabaseCount('security_logs', 0);
    }

    public function test_demote_ditolak_untuk_petugas_it_belum_takeover(): void
    {
        $it = $this->itUser();

        $this->actingAs($it)
            ->post(route('it-emergency.demote-self'))
            ->assertForbidden();

        $this->assertDatabaseCount('security_logs', 0);
    }

    // ================= DEMOTE MENGEMBALIKAN KE MODE IT =================

    public function test_petugas_it_setelah_takeover_dapat_kembali_ke_mode_it(): void
    {
        $it = $this->itUser();

        // 1) Takeover → Super Admin darurat.
        $this->takeOver($it);
        $this->assertTrue($it->is_emergency_takeover);
        $this->assertSame(User::ROLE_PETUGAS_IT, $it->emergency_origin_role);

        // 2) Demote (dengan state impersonasi & mode darurat yang sedang aktif —
        //    keduanya harus ikut dibersihkan).
        $this->actingAs($it)
            ->withSession([
                'active_role' => 'super_admin',
                'impersonate_target_id' => 999,
                'testing_view' => 'testing',
                'emergency_mode' => true,
            ])
            ->post(route('it-emergency.demote-self'))
            ->assertRedirect(route('it.dashboard'))
            ->assertSessionHas('success');

        // Identitas IT/QA pulih + status Super Admin darurat dilepas.
        $it = $this->fresh($it);
        $this->assertSame(User::ROLE_ADMIN, $it->role);
        $this->assertSame(User::ROLE_PETUGAS_IT, $it->sub_role);
        $this->assertTrue($it->isPetugasIt());
        $this->assertFalse($it->isSuperAdmin());
        $this->assertFalse($it->is_emergency_takeover);
        $this->assertNull($it->emergency_origin_role);

        $this->assertDatabaseHas('users', [
            'id' => $it->id,
            'role' => User::ROLE_ADMIN,
            'sub_role' => User::ROLE_PETUGAS_IT,
            'is_emergency_takeover' => false,
            'emergency_origin_role' => null,
        ]);

        // Sesi dibersihkan: tidak tersisa mode impersonasi / darurat.
        $this->assertNull($this->app['session']->get('active_role'));
        $this->assertNull($this->app['session']->get('impersonate_target_id'));
        $this->assertNull($this->app['session']->get('testing_view'));
        $this->assertNull($this->app['session']->get('emergency_mode'));

        // Jejak audit append-only.
        $this->assertDatabaseHas('security_logs', [
            'user_id' => $it->id,
            'device_name' => 'Emergency Demote',
            'is_current_session' => false,
        ]);

        // 3) Akun kini berfungsi penuh sebagai Petugas IT: Dashboard IT terbuka.
        $this->assertTrue($it->isPetugasIt());
        $this->actingAs($it)
            ->get(route('it.dashboard'))
            ->assertOk();
    }

    public function test_qa_tester_setelah_takeover_dikembalikan_ke_qa_tester(): void
    {
        $qa = $this->qaUser();

        $this->takeOver($qa);
        $this->assertSame(User::ROLE_QA_TESTER, $qa->emergency_origin_role);

        $this->actingAs($qa)
            ->post(route('it-emergency.demote-self'))
            ->assertRedirect(route('it.dashboard'))
            ->assertSessionHas('success');

        $qa = $this->fresh($qa);
        $this->assertSame(User::ROLE_ADMIN, $qa->role);
        $this->assertSame(User::ROLE_QA_TESTER, $qa->sub_role);
        $this->assertTrue($qa->isPetugasIt());
        $this->assertFalse($qa->is_emergency_takeover);
    }

    // ================= UI TOPBAR =================

    public function test_topbar_menampilkan_tombol_kembali_ke_mode_it_untuk_akun_takeover(): void
    {
        $it = $this->itUser();
        $this->takeOver($it);

        $this->actingAs($it)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Kembali ke Mode IT / QA')
            ->assertSee(route('it-emergency.demote-self'), false);
    }

    public function test_topbar_tidak_menampilkan_tombol_kembali_untuk_super_admin_biasa_dan_it(): void
    {
        // Super Admin biasa (bukan hasil takeover) — tombol tidak tampil.
        $sa = $this->mkSuperAdmin('sa.asing');
        $this->actingAs($sa)
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee('Kembali ke Mode IT / QA');

        // Petugas IT yang belum pernah takeover — tombol tidak tampil.
        $it = $this->itUser();
        $this->actingAs($it)
            ->get(route('it.dashboard'))
            ->assertOk()
            ->assertDontSee('Kembali ke Mode IT / QA');
    }
}