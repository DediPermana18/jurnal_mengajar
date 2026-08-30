<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PetugasItPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\UserSeeder::class);
    }

    private function loginPetugasIt()
    {
        $it = User::where('email', 'it@school.id')->first();
        $this->actingAs($it);
        return $it;
    }

    public function test_switch_view_sets_preview_role()
    {
        $this->loginPetugasIt();

        $this->post(route('it.switch-view'), ['role' => 'waka_kurikulum'])
            ->assertRedirect(route('home'));

        $this->assertEquals('waka_kurikulum', session('preview_role'));
    }

    public function test_switch_view_rejects_invalid_role()
    {
        $this->loginPetugasIt();

        $this->post(route('it.switch-view'), ['role' => 'hacker'])
            ->assertStatus(422);

        $this->assertNull(session('preview_role'));
    }

    public function test_reset_view_clears_preview_role()
    {
        $it = $this->loginPetugasIt();
        session(['preview_role' => 'guru_piket']);

        $this->post(route('it.reset-view'))
            ->assertRedirect(route('home'));

        $this->assertNull(session('preview_role'));
    }

    public function test_non_it_user_cannot_switch()
    {
        $guru = User::where('username', 'guru')->first();
        $this->actingAs($guru);

        $this->post(route('it.switch-view'), ['role' => 'admin_tu'])
            ->assertStatus(403);
    }

    public function test_petugas_it_account_exists_with_password()
    {
        $it = User::where('email', 'it@school.id')->first();
        $this->assertNotNull($it);
        $this->assertEquals('petugas_it', $it->role);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('password', $it->password));
    }
}
