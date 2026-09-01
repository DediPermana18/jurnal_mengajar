<?php

namespace Database\Seeders;

use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GuruSeeder extends Seeder
{
    /**
     * Seed Data Master Guru realistis beserta akun login, relasi wali kelas, dan mapel pengampu.
     */
    public function run(): void
    {
        // 1. CLEANUP DATA DUMMY GURU LAMA ('test2', 'test3', 'testting1', dll)
        $oldDummyGuruIds = User::where('role', User::ROLE_GURU)
            ->where(function ($q) {
                $q->where('username', 'like', 'test%')
                  ->orWhere('nama', 'like', 'test%')
                  ->orWhere('email', 'like', 'test%');
            })
            ->pluck('id');

        if ($oldDummyGuruIds->isNotEmpty()) {
            JadwalPelajaran::whereIn('id_guru', $oldDummyGuruIds)->forceDelete();
            Kelas::whereIn('id_wali_kelas', $oldDummyGuruIds)->update(['id_wali_kelas' => null]);
            User::whereIn('id', $oldDummyGuruIds)->forceDelete();
        }

        $tahunAktif = TahunAjaran::where('status_aktif', true)->first() ?? TahunAjaran::first();

        // 2. DAFTAR 12 GURU REALISTIS BESERTA MAPPING MAPEL & WALI KELAS
        $guruList = [
            [
                'nama'        => 'Budi Santoso, S.Kom.',
                'username'    => 'budi.santoso',
                'email'       => 'budi.santoso@smk.sch.id',
                'nip'         => '198503122010011002',
                'no_hp'       => '081234567801',
                'wali_kelas'  => 'RPL 1', // XI RPL 1
                'mapels'      => [
                    ['kode' => 'MPL-KJ-01', 'kelas' => 'RPL 1', 'hari' => 'Senin', 'jam_mulai' => 1, 'jam_selesai' => 4],
                ],
            ],
            [
                'nama'        => 'Siti Rahmawati, S.Pd.',
                'username'    => 'siti.rahmawati',
                'email'       => 'siti.rahmawati@smk.sch.id',
                'nip'         => '198807242012022005',
                'no_hp'       => '081234567802',
                'wali_kelas'  => 'AKL 1', // XI AKL 1
                'mapels'      => [
                    ['kode' => 'MPL-UM-04', 'kelas' => 'AKL 1', 'hari' => 'Senin', 'jam_mulai' => 1, 'jam_selesai' => 3],
                ],
            ],
            [
                'nama'        => 'Ir. Ahmad Fauzi, M.T.',
                'username'    => 'ahmad.fauzi',
                'email'       => 'ahmad.fauzi@smk.sch.id',
                'nip'         => '198111052008011003',
                'no_hp'       => '081234567803',
                'wali_kelas'  => 'TKJ 1', // XI TKJ 1
                'mapels'      => [
                    ['kode' => 'MPL-KJ-09', 'kelas' => 'TKJ 1', 'hari' => 'Senin', 'jam_mulai' => 1, 'jam_selesai' => 4],
                ],
            ],
            [
                'nama'        => 'Dra. Hj. Endang Sri Wahyuni, M.Pd.',
                'username'    => 'endang.sri',
                'email'       => 'endang.sri@smk.sch.id',
                'nip'         => '197405182000032001',
                'no_hp'       => '081234567804',
                'wali_kelas'  => 'MP 1', // XI MP 1
                'mapels'      => [
                    ['kode' => 'MPL-KJ-06', 'kelas' => 'MP 1', 'hari' => 'Senin', 'jam_mulai' => 1, 'jam_selesai' => 3],
                    ['kode' => 'MPL-UM-03', 'kelas' => 'MP 1', 'hari' => 'Selasa', 'jam_mulai' => 1, 'jam_selesai' => 2],
                ],
            ],
            [
                'nama'        => 'Eko Prasetyo, S.Sn., M.Ds.',
                'username'    => 'eko.prasetyo',
                'email'       => 'eko.prasetyo@smk.sch.id',
                'nip'         => '199002152015041001',
                'no_hp'       => '081234567805',
                'wali_kelas'  => 'DKV 1', // XI DKV 1
                'mapels'      => [
                    ['kode' => 'MPL-KJ-05', 'kelas' => 'DKV 1', 'hari' => 'Senin', 'jam_mulai' => 1, 'jam_selesai' => 4],
                ],
            ],
            [
                'nama'        => 'Rina Wulandari, S.E., M.M.',
                'username'    => 'rina.wulandari',
                'email'       => 'rina.wulandari@smk.sch.id',
                'nip'         => '198709102011012008',
                'no_hp'       => '081234567806',
                'wali_kelas'  => 'BD 1', // XI BD 1
                'mapels'      => [
                    ['kode' => 'MPL-KJ-04', 'kelas' => 'BD 1', 'hari' => 'Senin', 'jam_mulai' => 1, 'jam_selesai' => 3],
                    ['kode' => 'MPL-KJ-03', 'kelas' => 'AKL 1', 'hari' => 'Selasa', 'jam_mulai' => 1, 'jam_selesai' => 3],
                ],
            ],
            [
                'nama'        => 'Hendra Wijaya, S.T.',
                'username'    => 'hendra.wijaya',
                'email'       => 'hendra.wijaya@smk.sch.id',
                'nip'         => '199204082018011004',
                'no_hp'       => '081234567807',
                'wali_kelas'  => 'AN 1', // XI AN 1
                'mapels'      => [
                    ['kode' => 'MPL-KJ-02', 'kelas' => 'AN 1', 'hari' => 'Senin', 'jam_mulai' => 1, 'jam_selesai' => 4],
                ],
            ],
            [
                'nama'        => 'Maya Kartika, S.Pd., M.Hum.',
                'username'    => 'maya.kartika',
                'email'       => 'maya.kartika@smk.sch.id',
                'nip'         => '198912032014022003',
                'no_hp'       => '081234567808',
                'wali_kelas'  => 'ULW 1', // XI ULW 1
                'mapels'      => [
                    ['kode' => 'MPL-UM-05', 'kelas' => 'ULW 1', 'hari' => 'Senin', 'jam_mulai' => 1, 'jam_selesai' => 2],
                    ['kode' => 'MPL-ML-02', 'kelas' => 'ULW 1', 'hari' => 'Selasa', 'jam_mulai' => 1, 'jam_selesai' => 2],
                ],
            ],
            [
                'nama'        => 'Agus Setiawan, S.Pd.',
                'username'    => 'agus.setiawan',
                'email'       => 'agus.setiawan@smk.sch.id',
                'nip'         => '199106202019031005',
                'no_hp'       => '081234567809',
                'wali_kelas'  => 'PSPT 1', // XI PSPT 1
                'mapels'      => [
                    ['kode' => 'MPL-UM-06', 'kelas' => 'PSPT 1', 'hari' => 'Senin', 'jam_mulai' => 1, 'jam_selesai' => 3],
                    ['kode' => 'MPL-KJ-07', 'kelas' => 'PSPT 1', 'hari' => 'Selasa', 'jam_mulai' => 1, 'jam_selesai' => 3],
                ],
            ],
            [
                'nama'        => 'Dr. Retno Utami, M.Si.',
                'username'    => 'retno.utami',
                'email'       => 'retno.utami@smk.sch.id',
                'nip'         => '197808142005012002',
                'no_hp'       => '081234567810',
                'wali_kelas'  => 'TKI 1', // XI TKI 1
                'mapels'      => [
                    ['kode' => 'MPL-KJ-08', 'kelas' => 'TKI 1', 'hari' => 'Senin', 'jam_mulai' => 1, 'jam_selesai' => 4],
                ],
            ],
            [
                'nama'        => 'Bambang Hermanto, S.Ag.',
                'username'    => 'bambang.hermanto',
                'email'       => 'bambang.hermanto@smk.sch.id',
                'nip'         => '198310122009021003',
                'no_hp'       => '081234567811',
                'wali_kelas'  => null,
                'mapels'      => [
                    ['kode' => 'MPL-UM-01', 'kelas' => 'RPL 1', 'hari' => 'Selasa', 'jam_mulai' => 1, 'jam_selesai' => 3],
                    ['kode' => 'MPL-UM-02', 'kelas' => 'TKJ 1', 'hari' => 'Rabu', 'jam_mulai' => 1, 'jam_selesai' => 2],
                ],
            ],
            [
                'nama'        => 'Dewi Lestari, S.Pd.',
                'username'    => 'dewi.lestari',
                'email'       => 'dewi.lestari@smk.sch.id',
                'nip'         => '199403162020122007',
                'no_hp'       => '081234567812',
                'wali_kelas'  => null,
                'mapels'      => [
                    ['kode' => 'MPL-ML-01', 'kelas' => 'DKV 1', 'hari' => 'Selasa', 'jam_mulai' => 1, 'jam_selesai' => 2],
                    ['kode' => 'MPL-UM-07', 'kelas' => 'AN 1', 'hari' => 'Rabu', 'jam_mulai' => 1, 'jam_selesai' => 2],
                ],
            ],
        ];

        $dibuatCount = 0;
        $tableData   = [];

        foreach ($guruList as $g) {
            // Cari kelas jika guru ditugaskan sebagai wali kelas
            $kelasWaliObj = null;
            if (! empty($g['wali_kelas'])) {
                $kelasWaliObj = Kelas::where('nama_kelas', $g['wali_kelas'])->first();
            }

            $user = User::updateOrCreate(
                ['username' => $g['username']],
                [
                    'nama'      => $g['nama'],
                    'email'     => $g['email'],
                    'nip'       => $g['nip'],
                    'no_hp'     => $g['no_hp'],
                    'password'  => Hash::make('password123'),
                    'role'      => User::ROLE_GURU,
                    'sub_role'  => $kelasWaliObj ? 'wali_kelas' : 'guru_mapel',
                    'kelas_id'  => $kelasWaliObj?->id,
                    'is_active' => true,
                ]
            );

            // Set id_wali_kelas pada tabel kelas
            if ($kelasWaliObj) {
                $kelasWaliObj->update(['id_wali_kelas' => $user->id]);
            }

            // Plotting Jadwal Pelajaran (Relasi Guru & Mapel)
            $mapelNamesArr = [];
            foreach ($g['mapels'] as $mConfig) {
                $mapelObj = MataPelajaran::where('kode_mapel', $mConfig['kode'])->first();
                $targetKelas = Kelas::where('nama_kelas', $mConfig['kelas'])->first();

                if ($mapelObj && $targetKelas) {
                    $mapelNamesArr[] = $mapelObj->nama_mapel;

                    $kategoriHari = ($mConfig['hari'] === 'Jumat') ? 'Jumat' : 'Senin-Kamis';
                    $targetSlots  = JamPelajaran::where('kategori_hari', $kategoriHari)
                        ->whereNotNull('jam_ke')
                        ->where('jenis', '!=', 'istirahat')
                        ->whereBetween('jam_ke', [$mConfig['jam_mulai'], $mConfig['jam_selesai']])
                        ->get();

                    $groupId = (string) Str::uuid();

                    foreach ($targetSlots as $slot) {
                        JadwalPelajaran::withTrashed()->updateOrCreate(
                            [
                                'id_kelas'        => $targetKelas->id,
                                'hari'            => $mConfig['hari'],
                                'id_jam'          => $slot->id,
                                'id_tahun_ajaran' => $tahunAktif?->id,
                            ],
                            [
                                'group_id'   => $groupId,
                                'id_mapel'   => $mapelObj->id,
                                'id_guru'    => $user->id,
                                'deleted_at' => null,
                            ]
                        );
                    }
                }
            }

            $dibuatCount++;

            $tableData[] = [
                'No'         => $dibuatCount,
                'Nama'       => $user->nama,
                'NIP'        => $user->nip,
                'Username'   => $user->username,
                'Role'       => $user->sub_role === 'wali_kelas' ? 'Wali Kelas (' . ($kelasWaliObj ? 'XI ' . $kelasWaliObj->nama_kelas : '') . ')' : 'Guru Mapel',
                'Mapel Utama'=> implode(', ', array_unique($mapelNamesArr)),
            ];
        }

        $this->command->info('');
        $this->command->info('================================================');
        $this->command->info(" {$dibuatCount} Data Guru & Relasi Mapel Pengampu Berhasil Disimpan.");
        $this->command->info('------------------------------------------------');
        $this->command->info(' Default Password : password123');
        $this->command->info(' Data Dummy Lama   : Berhasil dibersihkan.');
        $this->command->info('================================================');
        $this->command->table(['No', 'Nama Guru & Gelar', 'NIP', 'Username', 'Role / Wali Kelas', 'Mapel Utama'], $tableData);
        $this->command->info('');
    }
}
