<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PetugasItAdminAreaAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UserSeeder::class);
    }

    private function makeUser(string $role, string $subRole = 'guru_mapel'): User
    {
        return User::create([
            'nama' => 'Test User',
            'username' => 'user_'.Str::random(8),
            'password' => bcrypt('password'),
            'role' => $role,
            'sub_role' => $subRole,
            'is_active' => true,
        ]);
    }

    public function test_petugas_it_can_access_admin_guru_index(): void
    {
        $it = User::where('email', 'it@school.id')->firstOrFail();

        $this->actingAs($it)->get(route('guru.index'))->assertOk();
    }

    public function test_qa_tester_can_access_admin_guru_index(): void
    {
        $qa = User::where('email', 'qa@school.id')->firstOrFail();

        $this->actingAs($qa)->get(route('guru.index'))->assertOk();
    }

    public function test_petugas_it_impersonating_admin_tu_can_access_admin_guru_index(): void
    {
        $it = User::where('email', 'it@school.id')->firstOrFail();
        session(['active_role' => 'admin_tu']);

        $this->actingAs($it)->get(route('guru.index'))->assertOk();
    }

    public function test_non_it_user_is_forbidden_from_admin_guru(): void
    {
        $guru = $this->makeUser('guru');

        $this->actingAs($guru)->get(route('guru.index'))->assertForbidden();
    }

    public function test_petugas_it_can_store_guru(): void
    {
        $it = User::where('email', 'it@school.id')->firstOrFail();
        $username = 'guru_uji_'.Str::random(6);

        $this->actingAs($it)->post(route('guru.store'), [
            'nama' => 'Guru Baru Uji',
            'username' => $username,
            'password' => 'password123',
        ])->assertRedirect(route('guru.index'));

        $this->assertDatabaseHas('users', ['username' => $username]);
    }

    public function test_petugas_it_can_access_all_data_master_pages(): void
    {
        $it = User::where('email', 'it@school.id')->firstOrFail();

        $this->actingAs($it)->get(route('admin.users.index'))->assertOk();
        $this->actingAs($it)->get(route('kelas.index'))->assertOk();
        $this->actingAs($it)->get(route('jurusan.index'))->assertOk();
        $this->actingAs($it)->get(route('ruangan.index'))->assertOk();
        $this->actingAs($it)->get(route('tahun-ajaran.index'))->assertOk();
    }
}
