<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Akun Super Admin
        User::updateOrCreate(
            ['username' => 'superadmin'],
            [
                'nama'          => 'Super Admin',
                'nip'           => '198501012010011001',
                'password'      => Hash::make('password'),
                'kode_aktivasi' => null,
                'role'          => 'super_admin',
            ]
        );

        // 2. Akun Admin Sekolah
        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'nama'          => 'Admin Sekolah',
                'nip'           => '198702022012011002',
                'password'      => Hash::make('password'),
                'kode_aktivasi' => null,
                'role'          => 'admin',
            ]
        );

        // 3. Akun Guru Dummy 1 (Budi Santoso)
        User::updateOrCreate(
            ['username' => 'gurbudi'],
            [
                'nama'          => 'Budi Santoso, S.Pd.',
                'nip'           => '199003032015011003',
                'password'      => Hash::make('password'),
                'kode_aktivasi' => null,
                'role'          => 'guru',
            ]
        );

        // 4. Akun Guru Dummy 2 (Siti Rahma)
        User::updateOrCreate(
            ['username' => 'gursiti'],
            [
                'nama'          => 'Siti Rahma, M.Pd.',
                'nip'           => '199204042018012004',
                'password'      => Hash::make('password'),
                'kode_aktivasi' => null,
                'role'          => 'guru',
            ]
        );

        // 5. Akun Guru Dummy 3 (Ahmad Dahlan)
        User::updateOrCreate(
            ['username' => 'gurahmad'],
            [
                'nama'          => 'Ahmad Dahlan, S.Si.',
                'nip'           => '199405052020011005',
                'password'      => Hash::make('password'),
                'kode_aktivasi' => null,
                'role'          => 'guru',
            ]
        );
    }
}
