<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FreshSeedAdminTuTest extends TestCase
{
    public function test_migrate_fresh_seed_creates_admin_tu_account()
    {
        Artisan::call('migrate:fresh --seed');

        $admin = User::where('email', 'admin@school.id')->first();

        $this->assertNotNull($admin);
        $this->assertEquals('Administrator TU', $admin->nama);
        $this->assertEquals('admin', $admin->role);
        $this->assertEquals('petugas_tu', $admin->sub_role);
        $this->assertTrue(Hash::check('password', $admin->password));
    }
}
