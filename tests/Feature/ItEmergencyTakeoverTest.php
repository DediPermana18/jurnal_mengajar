<?php

namespace Tests\Feature;

use App\Models\Scopes\TestingDataScope;
use App\Models\SecurityLog;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Emergency Super Admin Takeover ("Kartu As") untuk Petugas IT / QA Tester.
 *
 * Saat akun Super Admin utama dibobol / terkunci, Petugas IT dapat mempromosikan
 * dirinya sendiri menjadi 'super_admin' PERMANEN:
 *  - Endpoint: POST /admin/it-emergency/promote-self.
 *  - HANYA akun ber-role ATAU sub_role 'petugas_it' / 'qa_tester'.
 *  - Alasan darurat wajib diisi (jejak audit); promosi tercatat append-only di
 *    security_logs (IP, UA, timestamp, alasan).
 *  - Opsional (dengan konfirmasi): nonaktifkan paksa akun Super Admin lain yang
 *    dicurigai dibobol (is_active=false + sesi dikeluarkan + audit per akun).
 */
class ItEmergencyTakeoverTest extends TestCase
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

    private function petugasTu(): User
    {
        return User::withoutGlobalScope(TestingDataScope::class)
            ->where('email', 'admin@school.id')->firstOrFail();
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

    public function test_petugas_it_dapat_mempromosikan_dirinya_menjadi_super_admin(): void
    {
        $it = $this->itUser();

        $this->actingAs($it)
            ->post(route('it-emergency.promote-self'), ['reason' => $this->reason()])
            ->assertRedirect(route('home'))
            ->assertSessionHas('success');

        // Role & sub_role berubah PERMANEN menjadi Super Admin (skema baru DB:
        // role 'admin' + sub_role 'super_admin' — kolom role MySQL ber-ENUM
        //('admin','guru'), sehingga literal super_admin TIDAK ditulis ke role).
        $this->assertSame(User::ROLE_ADMIN, $it->role);
        $this->assertSame(User::ROLE_SUPER_ADMIN, $it->sub_role);
        $this->assertTrue($it->isSuperAdmin());
        $this->assertDatabaseHas('users', [
            'id' => $it->id,
            'role' => User::ROLE_ADMIN,
            'sub_role' => User::ROLE_SUPER_ADMIN,
        ]);

        // Penanda permanent "Emergency Takeover" diset — pembuka gembok suspend
        // akun Utama 'admin' (isEmergencyPrimaryAdminOverride) di UserController.
        $this->assertTrue($it->isEmergencyTakeover());
        $this->assertDatabaseHas('users', [
            'id' => $it->id,
            'is_emergency_takeover' => true,
        ]);

        // Snapshot identitas IT asli disimpan (untuk demoteSelf / "Kembali ke Mode IT").
        $this->assertSame(User::ROLE_PETUGAS_IT, $it->emergency_origin_role);
        $this->assertDatabaseHas('users', [
            'id' => $it->id,
            'emergency_origin_role' => User::ROLE_PETUGAS_IT,
        ]);

        // Audit append-only: baris "Emergency Takeover" dengan deskripsi berisi alasan.
        $log = SecurityLog::where('user_id', $it->id)
            ->where('device_name', 'Emergency Takeover')
            ->latest('login_at')
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('dipromosikan DARURAT menjadi Super Admin', (string) $log->description);
        $this->assertStringContainsString('dibobol', (string) $log->description);
        $this->assertNotNull($log->ip_address);
        $this->assertFalse($log->is_current_session);
    }

    public function test_qa_tester_juga_dapat_takeover(): void
    {
        $qa = $this->qaUser();

        $this->actingAs($qa)
            ->post(route('it-emergency.promote-self'), ['reason' => $this->reason()])
            ->assertRedirect(route('home'));

        $this->assertSame(User::ROLE_ADMIN, $qa->role);
        $this->assertSame(User::ROLE_SUPER_ADMIN, $qa->sub_role);
        $this->assertTrue($qa->isSuperAdmin());
    }

    public function test_akun_dengan_sub_role_petugas_it_juga_boleh_takeover(): void
    {
        $subIt = User::create([
            'username' => 'sub.it',
            'nama' => 'Sub Role IT',
            'email' => 'sub.it@school.id',
            'password' => 'secret',
            'role' => User::ROLE_ADMIN,
            'sub_role' => User::ROLE_PETUGAS_IT,
            'is_active' => true,
        ]);

        $this->actingAs($subIt)
            ->post(route('it-emergency.promote-self'), ['reason' => $this->reason()])
            ->assertRedirect(route('home'));

        $this->assertSame(User::ROLE_ADMIN, $subIt->role);
        $this->assertSame(User::ROLE_SUPER_ADMIN, $subIt->sub_role);
    }

    public function test_akun_bukan_it_ditolak_dan_data_utuh(): void
    {
        // Petugas TU biasa → 403.
        $this->actingAs($this->petugasTu())
            ->post(route('it-emergency.promote-self'), ['reason' => $this->reason()])
            ->assertForbidden();

        // Super Admin (bukan dari kasta IT/QA) → 403.
        $sa = $this->mkSuperAdmin('sa.lama');
        $this->actingAs($sa)
            ->post(route('it-emergency.promote-self'), ['reason' => $this->reason()])
            ->assertForbidden();

        // Tidak ada satu pun akun yang berubah jadi super_admin.
        $this->assertDatabaseHas('users', ['id' => $this->petugasTu()->id, 'role' => User::ROLE_ADMIN]);
        $this->assertDatabaseHas('users', ['id' => $sa->id, 'role' => User::ROLE_SUPER_ADMIN]);
        $this->assertTrue($sa->is_active);
    }

    public function test_alasan_wajib_dan_minimal_10_karakter(): void
    {
        $it = $this->itUser();

        // Kosong → 422 (validasi).
        $this->actingAs($it)
            ->post(route('it-emergency.promote-self'))
            ->assertSessionHasErrors('reason');

        // Terlalu pendek → 422 (validasi).
        $this->actingAs($it)
            ->from(route('admin.users.index'))
            ->post(route('it-emergency.promote-self'), ['reason' => 'pendek'])
            ->assertSessionHasErrors('reason')
            ->assertRedirect(route('admin.users.index'));

        // Akun tetap IT — tidak ada promosi.
        $this->assertSame(User::ROLE_PETUGAS_IT, $it->refresh()->role);
        $this->assertFalse($it->isSuperAdmin());
    }

    public function test_opsi_nonaktifkan_super_admin_lama_yang_dicurigai_dibobol(): void
    {
        $kompromi = $this->mkSuperAdmin('sa.korban');
        $it = $this->itUser();

        $this->actingAs($it)
            ->post(route('it-emergency.promote-self'), [
                'reason' => $this->reason(),
                'disable_compromised' => '1',
            ])
            ->assertRedirect(route('home'));

        // Akun Super Admin lama dinonaktifkan paksa (is_active=false).
        $this->assertFalse($kompromi->refresh()->is_active);
        $this->assertDatabaseHas('users', ['id' => $kompromi->id, 'is_active' => false]);

        // Audit per akun target juga tercatat (append-only).
        $targetLog = SecurityLog::where('user_id', $kompromi->id)
            ->latest('login_at')
            ->first();

        $this->assertNotNull($targetLog);
        $this->assertStringContainsString('dinonaktifkan darurat oleh', (string) $targetLog->description);
    }

    public function test_setelah_takeover_sesi_diperlakukan_sebagai_super_admin(): void
    {
        $it = $this->itUser();

        $this->actingAs($it)
            ->post(route('it-emergency.promote-self'), ['reason' => $this->reason()]);

        // Halaman home berikutnya menampilkan sidebar Super Admin penuh.
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Portal Waka SDM');
    }

    public function test_takeover_menghapus_state_impersonasi(): void
    {
        $it = $this->itUser();

        $this->withSession(['active_role' => 'admin_tu', 'impersonate_target_id' => 999])
            ->actingAs($it)
            ->post(route('it-emergency.promote-self'), ['reason' => $this->reason()]);

        $this->assertNull(session('active_role'));
        $this->assertNull(session('impersonate_target_id'));
    }

    public function test_ikon_dan_modal_takeover_hanya_tampil_untuk_it_di_topbar(): void
    {
        // Petugas IT: ikon darurat + modal takeover tampil di topbar (lintas halaman).
        $this->actingAs($this->itUser())
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Emergency Super Admin Takeover')   // tooltip ikon topbar
            ->assertSee('itEmergencyTakeoverModal')         // modal konfirmasi darurat
            ->assertSee('Promosikan ke Super Admin Permanen (Darurat)'); // judul modal

        // Petugas TU: ikon & modal TIDAK tampil.
        $this->actingAs($this->petugasTu())
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee('Emergency Super Admin Takeover')
            ->assertDontSee('itEmergencyTakeoverModal');
    }
}