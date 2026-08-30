<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Menghasilkan 1 akun Admin Tata Usaha (TU) default beserta informasi login.
     */
    public function run(): void
    {
        $nama     = 'Administrator TU';
        $email    = 'admin@school.id';
        $username = 'admin';
        $password = 'password';

        User::updateOrCreate(
            ['email' => $email],
            [
                'nama'          => $nama,
                'username'      => $username,
                'password'      => Hash::make($password),
                'kode_aktivasi' => null,
                'role'          => 'admin',
                'sub_role'      => 'petugas_tu',
                'is_active'     => true,
            ]
        );

        $this->command->info('');
        $this->command->info('================================================');
        $this->command->info(' Akun Admin Tata Usaha berhasil dibuat.');
        $this->command->info('------------------------------------------------');
        $this->command->info(" Nama     : {$nama}");
        $this->command->info(" Email    : {$email}");
        $this->command->info(" Password : {$password}");
        $this->command->info(' Role     : Admin Tata Usaha (TU)');
        $this->command->info('================================================');
        $this->command->info('');

        // ============ AKUN GURU MAPEL DUMMY (test2 s.d. test10) ============
        $this->createDummyGuruAccounts();
    }

    /**
     * Membuat 9 akun Guru Mapel dummy (test2 s.d. test10).
     *
     * Data guru disimpan langsung pada tabel users (role='guru').
     * Tidak ada tabel terpisah 'gurus', sehingga tidak ada relasi profil
     * tambahan yang perlu dibuat.
     */
    protected function createDummyGuruAccounts(): void
    {
        for ($i = 2; $i <= 10; $i++) {
            $username = 'test' . $i;
            $email    = $username . '@school.id';

            User::updateOrCreate(
                ['username' => $username],
                [
                    'nama'          => $username,
                    'email'         => $email,
                    'nip'           => null,
                    'password'      => Hash::make('password123'),
                    'kode_aktivasi' => null,
                    'role'          => 'guru',
                    'sub_role'      => 'guru_mapel',
                    'is_active'     => true,
                ]
            );
        }

        $this->command->info('');
        $this->command->info('================================================');
        $this->command->info(' 9 Akun Guru Mapel dummy berhasil dibuat.');
        $this->command->info('------------------------------------------------');
        $this->command->info(' Username  : test2 s.d. test10');
        $this->command->info(' Email     : test2@school.id s.d. test10@school.id');
        $this->command->info(' Password  : password123');
        $this->command->info(' Role      : Guru Mapel (guru)');
        $this->command->info(' NIP       : NULL');
        $this->command->info('================================================');
        $this->command->info('');
    }
}
