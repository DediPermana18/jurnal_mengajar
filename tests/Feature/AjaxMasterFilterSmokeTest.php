<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AjaxMasterFilterSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UserSeeder::class);
    }

    private function adminUser(): User
    {
        $admin = User::whereIn('role', ['admin_tu', 'admin'])->first();
        if ($admin) {
            return $admin;
        }

        return User::create([
            'nama'     => 'Test Admin',
            'username' => 'tester_'.Str::random(8),
            'password' => bcrypt('password'),
            'role'     => 'admin_tu',
            'is_active' => true,
        ]);
    }

    public function test_guru_index_returns_json_partial_on_ajax()
    {
        $user = $this->adminUser();
        $this->actingAs($user);

        $res = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get('/admin/guru?status=Aktif');
        $res->assertOk();
        $json = $res->json();
        $this->assertArrayHasKey('html', $json);
        $this->assertIsString($json['html']);
        $this->assertStringContainsString('table-card-custom', $json['html']);
    }

    public function test_siswa_index_returns_json_partial_on_ajax()
    {
        $user = $this->adminUser();
        $this->actingAs($user);

        $res = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get('/admin/siswa?search=budi');
        $res->assertOk();
        $json = $res->json();
        $this->assertArrayHasKey('html', $json);
        $this->assertStringContainsString('table-card-custom', $json['html']);
    }

    public function test_kelas_index_returns_json_partial_on_ajax()
    {
        $user = $this->adminUser();
        $this->actingAs($user);

        $res = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get('/admin/kelas?tingkat=X');
        $res->assertOk();
        $json = $res->json();
        $this->assertArrayHasKey('html', $json);
        $this->assertStringContainsString('table-card-custom', $json['html']);
    }

    public function test_jurusan_index_returns_json_partial_on_ajax()
    {
        $user = $this->adminUser();
        $this->actingAs($user);

        $res = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get('/admin/jurusan?search=rpl');
        $res->assertOk();
        $json = $res->json();
        $this->assertArrayHasKey('html', $json);
        $this->assertStringContainsString('table-card-custom', $json['html']);
    }

    public function test_ruangan_index_returns_json_partial_on_ajax()
    {
        $user = $this->adminUser();
        $this->actingAs($user);

        $res = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get('/admin/ruangan?search=labor');
        $res->assertOk();
        $json = $res->json();
        $this->assertArrayHasKey('html', $json);
        $this->assertStringContainsString('table-card-custom', $json['html']);
    }
}