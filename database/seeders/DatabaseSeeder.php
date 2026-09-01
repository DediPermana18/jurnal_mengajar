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

        // ============ DATA RUANGAN SMK (R-01 s.d. R-10) ============
        $this->call(RuanganSeeder::class);

        // ============ DATA KELAS XI SMK (10 Kelas) ============
        $this->call(KelasSeeder::class);

        // ============ DATA MATA PELAJARAN (Umum, Mulok, Kejuruan) ============
        $this->call(MapelSeeder::class);

        // ============ DATA GURU REALISTIS (12 Guru + Akun Login + Wali Kelas) ============
        $this->call(GuruSeeder::class);

        // ============ DATA SISWA KELAS XI (10 Kelas x 5 Siswa = 50 Siswa) ============
        $this->call(SiswaSeeder::class);
    }
}
