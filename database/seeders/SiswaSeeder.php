<?php

namespace Database\Seeders;

use App\Models\Kelas;
use App\Models\Siswa;
use Illuminate\Database\Seeder;

class SiswaSeeder extends Seeder
{
    /**
     * Seluruh siswa XI dikelompokkan per kelas (masing-masing 5 siswa).
     *
     * @return array<int, array{kelas: string, siswa: array<int, array{nama: string, jk: string}>}>
     */
    private function dataPerKelas(): array
    {
        return [
            ['kelas' => 'AKL 1', 'siswa' => [
                ['nama' => 'Adinda Rahmawati',      'jk' => 'P'],
                ['nama' => 'Bayu Prasetyo',         'jk' => 'L'],
                ['nama' => 'Citra Ayu Lestari',     'jk' => 'P'],
                ['nama' => 'Dimas Arya Saputra',    'jk' => 'L'],
                ['nama' => 'Eka Nurhaliza',         'jk' => 'P'],
            ]],
            ['kelas' => 'AN 1', 'siswa' => [
                ['nama' => 'Fajar Ramadhan',        'jk' => 'L'],
                ['nama' => 'Gita Permata Sari',     'jk' => 'P'],
                ['nama' => 'Hendra Gunawan',        'jk' => 'L'],
                ['nama' => 'Intan Permatasari',     'jk' => 'P'],
                ['nama' => 'Jaka Maulana',          'jk' => 'L'],
            ]],
            ['kelas' => 'BD 1', 'siswa' => [
                ['nama' => 'Kartika Dewi',          'jk' => 'P'],
                ['nama' => 'Lukman Hakim',          'jk' => 'L'],
                ['nama' => 'Maya Anggraini',        'jk' => 'P'],
                ['nama' => 'Naufal Firdaus',        'jk' => 'L'],
                ['nama' => 'Oktaviani',             'jk' => 'P'],
            ]],
            ['kelas' => 'DKV 1', 'siswa' => [
                ['nama' => 'Putra Mahardika',       'jk' => 'L'],
                ['nama' => 'Qori Indah Sari',       'jk' => 'P'],
                ['nama' => 'Rizky Ananda',          'jk' => 'L'],
                ['nama' => 'Salsabila Zahra',       'jk' => 'P'],
                ['nama' => 'Teguh Wicaksono',       'jk' => 'L'],
            ]],
            ['kelas' => 'MP 1', 'siswa' => [
                ['nama' => 'Umar Faruq',            'jk' => 'L'],
                ['nama' => 'Vina Melati',           'jk' => 'P'],
                ['nama' => 'Wahyu Nugroho',         'jk' => 'L'],
                ['nama' => 'Yuni Kartika',          'jk' => 'P'],
                ['nama' => 'Zainal Arifin',         'jk' => 'L'],
            ]],
            ['kelas' => 'PSPT 1', 'siswa' => [
                ['nama' => 'Alya Maharani',         'jk' => 'P'],
                ['nama' => 'Bima Sakti',            'jk' => 'L'],
                ['nama' => 'Cahaya Ningsih',        'jk' => 'P'],
                ['nama' => 'Doni Kurniawan',        'jk' => 'L'],
                ['nama' => 'Erik Santoso',          'jk' => 'L'],
            ]],
            ['kelas' => 'RPL 1', 'siswa' => [
                ['nama' => 'Fina Rahmayanti',       'jk' => 'P'],
                ['nama' => 'Galih Purnama',         'jk' => 'L'],
                ['nama' => 'Hesti Puspita',         'jk' => 'P'],
                ['nama' => 'Ilham Nugraha',         'jk' => 'L'],
                ['nama' => 'Jasmine Alika',         'jk' => 'P'],
            ]],
            ['kelas' => 'TKI 1', 'siswa' => [
                ['nama' => 'Krisna Wijaya',         'jk' => 'L'],
                ['nama' => 'Laila Fitriani',        'jk' => 'P'],
                ['nama' => 'M. Rifqi Pratama',      'jk' => 'L'],
                ['nama' => 'Nabila Aulia',          'jk' => 'P'],
                ['nama' => 'Oscar Permana',         'jk' => 'L'],
            ]],
            ['kelas' => 'TKJ 1', 'siswa' => [
                ['nama' => 'Putri Amelia',          'jk' => 'P'],
                ['nama' => 'Rendi Fadhilah',        'jk' => 'L'],
                ['nama' => 'Silvia Wulandari',      'jk' => 'P'],
                ['nama' => 'Topan Saputra',         'jk' => 'L'],
                ['nama' => 'Ulya Nurjanah',         'jk' => 'P'],
            ]],
            ['kelas' => 'ULW 1', 'siswa' => [
                ['nama' => 'Vega Mahesa',           'jk' => 'L'],
                ['nama' => 'Winda Kusuma',          'jk' => 'P'],
                ['nama' => 'Yoga Pradana',          'jk' => 'L'],
                ['nama' => 'Zahra Aini',            'jk' => 'P'],
                ['nama' => 'Ahmad Fauzi',           'jk' => 'L'],
            ]],
        ];
    }

    public function run(): void
    {
        // Bersihkan data siswa dummy lama (termasuk yang soft-deleted).
        Siswa::query()->forceDelete();

        $nis  = 1001;
        $nisn = 3000000001;

        $total  = 0;
        $dibuat = [];

        foreach ($this->dataPerKelas() as $kelompok) {
            $kelas = Kelas::where('tingkat', 'XI')
                ->where('nama_kelas', $kelompok['kelas'])
                ->first();

            if (! $kelas) {
                $this->command->warn(" Kelas XI {$kelompok['kelas']} tidak ditemukan, dilewati.");
                continue;
            }

            $jumlahKelas = 0;
            foreach ($kelompok['siswa'] as $calon) {
                Siswa::updateOrCreate(
                    ['nisn' => (string) $nisn, 'nis' => (string) $nis],
                    [
                        'nama'         => $calon['nama'],
                        'jenis_kelamin'=> $calon['jk'],
                        'id_kelas'     => $kelas->id,
                        'id_jurusan'   => $kelas->id_jurusan,
                        'status_siswa' => 'Aktif',
                    ]
                );
                $total++;
                $jumlahKelas++;
                $nis++;
                $nisn++;
            }

            $dibuat["XI {$kelompok['kelas']}"] = $jumlahKelas;
        }

        $this->command->info('');
        $this->command->info('================================================');
        $this->command->info(" {$total} Siswa kelas XI berhasil dibuat.");
        $this->command->info('------------------------------------------------');
        foreach ($dibuat as $kelas => $jumlah) {
            $this->command->info(" {$kelas}: {$jumlah} siswa");
        }
        $this->command->info('------------------------------------------------');
        $this->command->info(' NIS  : 4-5 digit (unik)');
        $this->command->info(' NISN : 10 digit (unik)');
        $this->command->info(' Akun login User: TIDAK dibuat (sesuai permintaan).');
        $this->command->info('================================================');
        $this->command->info('');
    }
}
