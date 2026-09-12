<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed Data Master User:
     * - Admin TU, Waka Kesiswaan, Waka Kurikulum, Waka SDM, Satpam, Petugas IT
     * - Daftar Guru (Budi Santoso, Agus Setiawan, Siti Rahmawati, Ahmad Fauzi, Eko Prasetyo, Rina Wulandari, Hendra Wijaya, Retno Utami, Bambang Hermanto, Dewi Lestari)
     */
    public function run(): void
    {
        $defaultPassword = Hash::make('password');

        // 1. ADMIN & STRUKTURAL / STAFF
        $staffs = [
            [
                'username' => 'admin',
                'nama' => 'Administrator TU',
                'nip' => '198702022012011002',
                'email' => 'admin@school.id',
                'no_hp' => '081234567001',
                'role' => User::ROLE_ADMIN,
                'sub_role' => 'petugas_tu',
                'kode_aktivasi' => 'ADM-SECURE-88',
            ],
            [
                'username' => 'waka.kesiswaan',
                'nama' => 'Drs. H. Darmawan, M.Pd.',
                'nip' => '196812101997031001',
                'email' => 'waka.kesiswaan@sekolah.sch.id',
                'no_hp' => '081234567011',
                'role' => User::ROLE_ADMIN,
                'sub_role' => 'waka_kesiswaan',
                'kode_aktivasi' => 'KSW-PASS-11',
            ],
            [
                'username' => 'kepsek',
                'nama' => 'Dr. H. Ahmad Dahlan, M.Pd.',
                'nip' => '197001011995031001',
                'email' => 'kepsek@school.id',
                'no_hp' => '081234567000',
                'role' => User::ROLE_ADMIN,
                'sub_role' => 'kepsek',
                'kode_aktivasi' => 'KPS-SIGN-2026',
            ],
            [
                'username' => 'waka.kurikulum',
                'nama' => 'Dr. H. Subagyo, M.Pd.',
                'nip' => '197508122002121001',
                'email' => 'waka.kurikulum@school.id',
                'no_hp' => '081234567002',
                'role' => User::ROLE_ADMIN,
                'sub_role' => 'waka_kurikulum',
                'kode_aktivasi' => 'KUR-PASS-33',
            ],
            [
                'username' => 'waka.sdm',
                'nama' => 'Drs. Supriyanto, M.M.',
                'nip' => '197804152005011004',
                'email' => 'waka.sdm@school.id',
                'no_hp' => '081234567003',
                'role' => User::ROLE_ADMIN,
                'sub_role' => 'waka_sdm',
                'kode_aktivasi' => 'SDM-PASS-77',
            ],
            [
                'username' => 'satpam',
                'nama' => 'Sugianto',
                'nip' => '199001012018011099',
                'email' => 'satpam@school.id',
                'no_hp' => '081234567004',
                'role' => User::ROLE_ADMIN,
                'sub_role' => 'satpam',
                'kode_aktivasi' => 'STP-PASS-55',
            ],
            [
                'username' => 'petugas.it',
                'nama' => 'Rian Hidayat, S.Kom.',
                'nip' => '199305202020011005',
                'email' => 'it@school.id',
                'no_hp' => '081234567005',
                'role' => User::ROLE_PETUGAS_IT,
                'sub_role' => null,
                'kode_aktivasi' => 'IT-PASS-99',
            ],
            [
                'username' => 'qa.tester',
                'nama' => 'Dewi Puspita, S.Kom.',
                'nip' => '199407152021011006',
                'email' => 'qa@school.id',
                'no_hp' => '081234567012',
                'role' => User::ROLE_QA_TESTER,
                'sub_role' => null,
                'kode_aktivasi' => 'QA-PASS-77',
            ],
        ];

        foreach ($staffs as $s) {
            User::updateOrCreate(
                ['username' => $s['username']],
                [
                    'nama' => $s['nama'],
                    'nip' => $s['nip'],
                    'email' => $s['email'],
                    'no_hp' => $s['no_hp'],
                    'password' => $defaultPassword,
                    'kode_aktivasi' => $s['kode_aktivasi'],
                    'role' => $s['role'],
                    'sub_role' => $s['sub_role'],
                    'is_active' => true,
                ]
            );
        }

        // 2. DAFTAR GURU REALISTIS
        $guruList = [
            [
                'nama' => 'Budi Santoso, S.Kom.',
                'username' => 'budi.santoso',
                'nip' => '198503122010011001',
                'email' => 'budi.santoso@school.id',
                'no_hp' => '081234567801',
            ],
            [
                'nama' => 'Agus Setiawan, S.Pd.',
                'username' => 'agus.setiawan',
                'nip' => '199106202019031005',
                'email' => 'agus.setiawan@school.id',
                'no_hp' => '081234567802',
            ],
            [
                'nama' => 'Siti Rahmawati, S.Pd.',
                'username' => 'siti.rahmawati',
                'nip' => '198807242012022005',
                'email' => 'siti.rahmawati@school.id',
                'no_hp' => '081234567803',
            ],
            [
                'nama' => 'Ir. Ahmad Fauzi, M.T.',
                'username' => 'ahmad.fauzi',
                'nip' => '198111052008011003',
                'email' => 'ahmad.fauzi@school.id',
                'no_hp' => '081234567804',
            ],
            [
                'nama' => 'Eko Prasetyo, S.Sn., M.Ds.',
                'username' => 'eko.prasetyo',
                'nip' => '199002152015041001',
                'email' => 'eko.prasetyo@school.id',
                'no_hp' => '081234567805',
            ],
            [
                'nama' => 'Rina Wulandari, S.E., M.M.',
                'username' => 'rina.wulandari',
                'nip' => '198709102011012008',
                'email' => 'rina.wulandari@school.id',
                'no_hp' => '081234567806',
            ],
            [
                'nama' => 'Hendra Wijaya, S.T.',
                'username' => 'hendra.wijaya',
                'nip' => '199204082018011004',
                'email' => 'hendra.wijaya@school.id',
                'no_hp' => '081234567807',
            ],
            [
                'nama' => 'Dr. Retno Utami, M.Si.',
                'username' => 'retno.utami',
                'nip' => '197808142005012002',
                'email' => 'retno.utami@school.id',
                'no_hp' => '081234567808',
            ],
            [
                'nama' => 'Bambang Hermanto, S.Ag.',
                'username' => 'bambang.hermanto',
                'nip' => '198310122009021003',
                'email' => 'bambang.hermanto@school.id',
                'no_hp' => '081234567809',
            ],
            [
                'nama' => 'Dewi Lestari, S.Pd.',
                'username' => 'dewi.lestari',
                'nip' => '199403162020122007',
                'email' => 'dewi.lestari@school.id',
                'no_hp' => '081234567810',
            ],
        ];

        $guruCount = 0;
        foreach ($guruList as $g) {
            User::updateOrCreate(
                ['username' => $g['username']],
                [
                    'nama' => $g['nama'],
                    'nip' => $g['nip'],
                    'email' => $g['email'],
                    'no_hp' => $g['no_hp'],
                    'password' => $defaultPassword,
                    'kode_aktivasi' => null,
                    'role' => User::ROLE_GURU,
                    'sub_role' => 'guru_mapel',
                    'is_active' => true,
                ]
            );
            $guruCount++;
        }

        $totalUsers = count($staffs) + $guruCount;
        $this->command->info(" [UserSeeder] {$totalUsers} User (7 Staff/Admin + {$guruCount} Guru) berhasil dibuat/diperbarui.");
    }
}
