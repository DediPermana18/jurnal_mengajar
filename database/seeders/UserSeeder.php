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
        // 1. Super Admin
        User::updateOrCreate(
            ['username' => 'superadmin'],
            [
                'nama'          => 'Super Admin System',
                'nip'           => '198501012010011001',
                'password'      => Hash::make('password123'),
                'kode_aktivasi' => 'SUPER123',
                'role'          => 'super_admin',
            ]
        );

        // 2. Admin TU (Tata Usaha)
        User::updateOrCreate(
            ['username' => 'admintu'],
            [
                'nama'          => 'Admin Tata Usaha (TU)',
                'nip'           => '198702022012011002',
                'password'      => Hash::make('password123'),
                'kode_aktivasi' => 'ADMIN123',
                'role'          => 'admin_tu',
            ]
        );

        // Alias 'admin' untuk Admin TU (memastikan kompatibilitas test login)
        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'nama'          => 'Administrator Utama',
                'nip'           => '198702022012011000',
                'password'      => Hash::make('password123'),
                'kode_aktivasi' => 'ADMIN123',
                'role'          => 'admin_tu',
            ]
        );

        // 3. Waka Kurikulum
        User::updateOrCreate(
            ['username' => 'kurikulum'],
            [
                'nama'          => 'Waka Kurikulum',
                'nip'           => '198803032013011003',
                'password'      => Hash::make('password123'),
                'kode_aktivasi' => 'KURIKULUM123',
                'role'          => 'admin_kurikulum',
            ]
        );

        // 4. Waka Kesiswaan
        User::updateOrCreate(
            ['username' => 'kesiswaan'],
            [
                'nama'          => 'Waka Kesiswaan',
                'nip'           => '198904042014011004',
                'password'      => Hash::make('password123'),
                'kode_aktivasi' => 'KESISWAAN123',
                'role'          => 'admin_kesiswaan',
            ]
        );

        // 5. Guru Mapel
        User::updateOrCreate(
            ['username' => 'gurubudi'],
            [
                'nama'          => 'Budi Santoso, S.Pd. (Guru Mapel)',
                'nip'           => '199003032015011005',
                'password'      => Hash::make('password123'),
                'kode_aktivasi' => null,
                'role'          => 'guru_mapel',
            ]
        );

        // Alias 'guru' untuk Guru Testing
        User::updateOrCreate(
            ['username' => 'guru'],
            [
                'nama'          => 'Guru Pengajar Utama',
                'nip'           => '199003032015011000',
                'password'      => Hash::make('password123'),
                'kode_aktivasi' => null,
                'role'          => 'guru_mapel',
            ]
        );

        // 6. Wali Kelas
        User::updateOrCreate(
            ['username' => 'gurahmad'],
            [
                'nama'          => 'Ahmad Dahlan, S.Si. (Wali Kelas)',
                'nip'           => '199405052020011006',
                'password'      => Hash::make('password123'),
                'kode_aktivasi' => null,
                'role'          => 'wali_kelas',
            ]
        );

        // 7. Guru Piket
        User::updateOrCreate(
            ['username' => 'gurupiket'],
            [
                'nama'          => 'Siti Rahma, M.Pd. (Guru Piket)',
                'nip'           => '199206062017011007',
                'password'      => Hash::make('password123'),
                'kode_aktivasi' => 'gurupiket123',
                'role'          => 'guru_piket',
            ]
        );

        // 8. Satpam / Security
        User::updateOrCreate(
            ['username' => 'satpam'],
            [
                'nama'          => 'Joko Security (Satpam)',
                'nip'           => '199507072021011008',
                'password'      => Hash::make('password123'),
                'kode_aktivasi' => 'satpam123',
                'role'          => 'piket_satpam',
            ]
        );
    }
}
