<?php

namespace Database\Seeders;

use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\User;
use Illuminate\Database\Seeder;

class KelasSeeder extends Seeder
{
    /**
     * Seed Data Kelas Rombongan Belajar:
     * - X DKV 1
     * - XI TKJ 1
     * - XII AKL 1
     * - X TKJ 1
     * - XI DKV 1
     * - XII TKJ 1
     */
    public function run(): void
    {
        $jurusanDkv = Jurusan::where('kode_jurusan', 'DKV')->first();
        $jurusanTkj = Jurusan::where('kode_jurusan', 'TKJ')->first();
        $jurusanAkl = Jurusan::where('kode_jurusan', 'AKL')->first();

        $kelasConfig = [
            ['tingkat' => 'X',   'nama_kelas' => 'DKV 1', 'id_jurusan' => $jurusanDkv?->id, 'wali' => 'eko.prasetyo'],
            ['tingkat' => 'XI',  'nama_kelas' => 'TKJ 1', 'id_jurusan' => $jurusanTkj?->id, 'wali' => 'ahmad.fauzi'],
            ['tingkat' => 'XII', 'nama_kelas' => 'AKL 1', 'id_jurusan' => $jurusanAkl?->id, 'wali' => 'rina.wulandari'],
            ['tingkat' => 'X',   'nama_kelas' => 'TKJ 1', 'id_jurusan' => $jurusanTkj?->id, 'wali' => 'budi.santoso'],
            ['tingkat' => 'XI',  'nama_kelas' => 'DKV 1', 'id_jurusan' => $jurusanDkv?->id, 'wali' => 'siti.rahmawati'],
            ['tingkat' => 'XII', 'nama_kelas' => 'TKJ 1', 'id_jurusan' => $jurusanTkj?->id, 'wali' => 'agus.setiawan'],
        ];

        $dibuat = 0;
        foreach ($kelasConfig as $kc) {
            $wali = $kc['wali'] ? User::where('username', $kc['wali'])->first() : null;

            $kelas = Kelas::updateOrCreate(
                ['nama_kelas' => $kc['nama_kelas'], 'tingkat' => $kc['tingkat']],
                [
                    'id_jurusan'    => $kc['id_jurusan'],
                    'id_wali_kelas' => $wali?->id,
                ]
            );

            // Update user status as Wali Kelas
            if ($wali) {
                $wali->update(['sub_role' => 'wali_kelas', 'kelas_id' => $kelas->id]);
            }

            $dibuat++;
        }

        $this->command->info(" [KelasSeeder] {$dibuat} Kelas Rombongan Belajar berhasil dibuat/diperbarui.");
    }
}
