<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MapelAccessTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, ?string $subRole = null): User
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

    public function test_waka_kurikulum_can_access_mata_pelajaran_index(): void
    {
        $user = $this->makeUser('admin', 'waka_kurikulum');

        $response = $this->actingAs($user)->get(route('mapel.index'));

        $response->assertOk();
    }

    public function test_petugas_tu_can_access_mata_pelajaran_index(): void
    {
        $user = $this->makeUser('admin', 'petugas_tu');

        $response = $this->actingAs($user)->get(route('mapel.index'));

        $response->assertOk();
    }

    public function test_non_authorized_role_is_forbidden(): void
    {
        $user = $this->makeUser('guru', 'guru_mapel');

        $response = $this->actingAs($user)->get(route('mapel.index'));

        $response->assertForbidden();
    }

    public function test_waka_kurikulum_can_store_mata_pelajaran(): void
    {
        $user = $this->makeUser('admin', 'waka_kurikulum');

        $response = $this->actingAs($user)->post(route('mapel.store'), [
            'kode_mapel' => 'KK-001',
            'nama_mapel' => 'Matematika',
            'kelompok' => 'Muatan Umum',
        ]);

        $response->assertRedirect(route('mapel.index'));
        $this->assertDatabaseHas('mata_pelajaran', ['nama_mapel' => 'Matematika']);
    }
}
