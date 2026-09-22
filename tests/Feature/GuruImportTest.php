<?php

namespace Tests\Feature;

use App\Imports\GuruImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GuruImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_guru_with_nullable_nip_sanitized_username_and_default_password()
    {
        $importer = new GuruImport;

        // Row 1: Teacher without NIP, has academic title
        $row1 = [
            'nip' => '',
            'nama_guru' => 'Endang Safitri, S.Pd',
            'status' => 'Aktif',
            'peran' => 'Guru',
        ];

        // Row 2: Teacher with NIP and front/back titles
        $row2 = [
            'nip' => '198001012005011002',
            'nama_guru' => 'Drs. H. Ahmad Fauzi, M.Pd.',
            'status' => 'Aktif',
            'peran' => 'Guru',
        ];

        // Row 3: Teacher without NIP with duplicate base name
        $row3 = [
            'nip' => '  ',
            'nama_guru' => 'Endang Safitri, S.ST',
            'status' => 'Aktif',
            'peran' => 'Guru',
        ];

        $importer->model($row1);
        $importer->model($row2);
        $importer->model($row3);

        // Verify User 1
        $user1 = User::where('username', 'endang.safitri')->first();
        $this->assertNotNull($user1);
        $this->assertNull($user1->nip);
        $this->assertEquals('Endang Safitri, S.Pd', $user1->nama);
        $this->assertTrue(Hash::check('endang.safitri123', $user1->password));

        // Verify User 2
        $user2 = User::where('nip', '198001012005011002')->first();
        $this->assertNotNull($user2);
        $this->assertEquals('ahmad.fauzi', $user2->username);
        $this->assertTrue(Hash::check('ahmad.fauzi123', $user2->password));

        // Verify User 3 (unique username suffix)
        $user3 = User::where('username', 'endang.safitri2')->first();
        $this->assertNotNull($user3);
        $this->assertNull($user3->nip);
        $this->assertEquals('Endang Safitri, S.ST', $user3->nama);
        $this->assertTrue(Hash::check('endang.safitri2123', $user3->password));
    }
}
