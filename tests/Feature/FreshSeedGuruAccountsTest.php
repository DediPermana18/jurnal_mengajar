<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FreshSeedGuruAccountsTest extends TestCase
{
    public function test_migrate_fresh_seed_creates_dummy_guru_accounts()
    {
        Artisan::call('migrate:fresh --seed');

        for ($i = 2; $i <= 10; $i++) {
            $username = 'test' . $i;
            $guru = User::where('username', $username)->first();

            $this->assertNotNull($guru, "{$username} tidak ditemukan");
            $this->assertEquals($username, $guru->nama);
            $this->assertEquals($username . '@school.id', $guru->email);
            $this->assertNull($guru->nip);
            $this->assertEquals('guru', $guru->role);
            $this->assertEquals('guru_mapel', $guru->sub_role);
            $this->assertTrue(Hash::check('password123', $guru->password));
        }

        $this->assertNull(User::where('username', 'test1')->first());
        $this->assertNull(User::where('username', 'test11')->first());
    }

    public function test_admin_tu_still_created()
    {
        Artisan::call('migrate:fresh --seed');
        $this->assertNotNull(User::where('email', 'admin@school.id')->first());
    }
}
