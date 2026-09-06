<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Orchestrator utama pemanggilan seluruh database seeder modular.
     */
    public function run(): void
    {
        $this->command->info('');
        $this->command->info('===================================================================');
        $this->command->info('  MEMULAI PROSES DATABASE SEEDING SYSTEM (jurnal_guru_digital)');
        $this->command->info('===================================================================');

        // Matikan Foreign Key Constraints agar truncate/seed berjalan mulus tanpa foreign key error
        Schema::disableForeignKeyConstraints();

        // 1. MASTER DATA & DEPENDENSI AWAL
        $this->call(TahunAjaranSeeder::class);
        $this->call(JurusanSeeder::class);
        $this->call(RuanganSeeder::class);
        $this->call(JamPelajaranSeeder::class);
        $this->call(PengaturanJadwalSeeder::class);

        // 2. USERS & AKADEMIK
        $this->call(UserSeeder::class);
        $this->call(KelasSeeder::class);
        $this->call(SiswaSeeder::class);
        $this->call(MapelSeeder::class);

        // 3. PLOTTING & JADWAL
        $this->call(JadwalPelajaranSeeder::class);
        $this->call(JadwalPiketSeeder::class);
        $this->call(PengurusRuanganSeeder::class);

        // 4. TRANSAKSI DUMMY & LOG
        $this->call(TransaksiSeeder::class);

        // Aktifkan kembali Foreign Key Constraints
        Schema::enableForeignKeyConstraints();

        $this->command->info('');
        $this->command->info('===================================================================');
        $this->command->info('  DATABASE SEEDING BERHASIL DISELESAIKAN SECARA KESELURUHAN!');
        $this->command->info('===================================================================');
        $this->command->info(' Credentials Default Login:');
        $this->command->info('  - Admin TU          : admin / password');
        $this->command->info('  - Waka Kurikulum    : waka.kurikulum / password');
        $this->command->info('  - Waka SDM          : waka.sdm / password');
        $this->command->info('  - Satpam            : satpam / password');
        $this->command->info('  - Petugas IT        : petugas.it / password');
        $this->command->info('  - Akun Guru         : [username_guru] / password (mis. budi.santoso, ahmad.fauzi, dll)');
        $this->command->info('===================================================================');
        $this->command->info('');
    }
}
