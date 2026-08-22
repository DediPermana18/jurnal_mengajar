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
        // Bersihkan data user kesiswaan atau kepala sekolah jika masih ada
        User::withTrashed()->whereIn('username', ['kesiswaan', 'kepala_sekolah'])->forceDelete();

        // 1. Super Admin
        User::updateOrCreate(
            ['username' => 'superadmin'],
            [
                'nama'          => 'Super Admin System',
                'nip'           => '198501012010011001',
                'password'      => Hash::make('password123'),
                'kode_aktivasi' => 'SUPER123',
                'role'          => 'admin',
                'sub_role'      => null,
                'is_active'     => true,
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
                'role'          => 'admin',
                'sub_role'      => 'petugas_tu',
                'is_active'     => true,
            ]
        );

        // Alias 'admin' untuk Admin TU (memastikan kompatibilitas test login)
        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'nama'          => 'Administrator Utama',
                'nip'           => '198702022012011000',
                'password'      => Hash::make('password123'),
                'kode_aktivasi' => 'ADMIN1234',
                'role'          => 'admin',
                'sub_role'      => 'petugas_tu',
                'is_active'     => true,
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
                'role'          => 'admin',
                'sub_role'      => 'waka_kurikulum',
                'is_active'     => true,
            ]
        );

        // 4. Guru Mapel
        User::updateOrCreate(
            ['username' => 'gurubudi'],
            [
                'nama'          => 'Budi Santoso, S.Pd. (Guru Mapel)',
                'nip'           => '199003032015011005',
                'password'      => Hash::make('password123'),
                'kode_aktivasi' => null,
                'role'          => 'guru',
                'sub_role'      => 'guru_mapel',
                'is_active'     => true,
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
                'role'          => 'guru',
                'sub_role'      => 'guru_mapel',
                'is_active'     => true,
            ]
        );

        // 5. Wali Kelas
        User::updateOrCreate(
            ['username' => 'gurahmad'],
            [
                'nama'          => 'Ahmad Dahlan, S.Si. (Wali Kelas)',
                'nip'           => '199405052020011006',
                'password'      => Hash::make('password123'),
                'kode_aktivasi' => null,
                'role'          => 'guru',
                'sub_role'      => 'wali_kelas',
                'is_active'     => true,
            ]
        );

        // 6. Guru yang dapat ditugaskan piket melalui jadwal_piket
        User::updateOrCreate(
            ['username' => 'gurupiket'],
            [
                'nama'          => 'Siti Rahma, M.Pd. (Guru Mapel)',
                'nip'           => '199206062017011007',
                'password'      => Hash::make('password123'),
                'kode_aktivasi' => null,
                'role'          => 'guru',
                'sub_role'      => 'guru',
                'is_active'     => true,
            ]
        );

        // 7. Satpam / Security
        User::updateOrCreate(
            ['username' => 'satpam'],
            [
                'nama'          => 'Joko Security (Satpam)',
                'nip'           => '199507072021011008',
                'password'      => Hash::make('password123'),
                'kode_aktivasi' => 'satpam123',
                'role'          => 'admin',
                'sub_role'      => 'satpam',
                'is_active'     => true,
            ]
        );
    }
}
