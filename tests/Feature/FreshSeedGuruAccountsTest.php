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

        $usernames = [
            'budi.santoso',
            'agus.setiawan',
            'siti.rahmawati',
            'ahmad.fauzi',
            'eko.prasetyo',
        ];

        foreach ($usernames as $username) {
            $guru = User::where('username', $username)->first();

            $this->assertNotNull($guru, "{$username} tidak ditemukan");
            $this->assertEquals('guru', $guru->role);
            $this->assertTrue(Hash::check('password', $guru->password));
        }

        $this->assertNull(User::where('username', 'test11')->first());
    }

    public function test_admin_tu_still_created()
    {
        Artisan::call('migrate:fresh --seed');
        $this->assertNotNull(User::where('email', 'admin@school.id')->first());
    }
}
