<?php

namespace Database\Seeders;

use App\Models\Kelas;
use App\Models\Siswa;
use Illuminate\Database\Seeder;

class SiswaSeeder extends Seeder
{
    /**
     * Seed Data Siswa: 6 siswa per kelas (NIS 4-5 digit, NISN 10 digit, id_kelas, id_jurusan).
     */
    public function run(): void
    {
        $siswaMasterList = [
            'X DKV 1' => [
                ['nama' => 'Aditya Pratama',      'jk' => 'L'],
                ['nama' => 'Anisa Putri',         'jk' => 'P'],
                ['nama' => 'Bagus Setiawan',      'jk' => 'L'],
                ['nama' => 'Citra Dewi Lestari',  'jk' => 'P'],
                ['nama' => 'Dwi Cahyo',           'jk' => 'L'],
                ['nama' => 'Eka Nurhaliza',       'jk' => 'P'],
            ],
            'XI TKJ 1' => [
                ['nama' => 'Fajar Ramadhan',      'jk' => 'L'],
                ['nama' => 'Gita Permata Sari',   'jk' => 'P'],
                ['nama' => 'Hendra Gunawan',      'jk' => 'L'],
                ['nama' => 'Intan Permatasari',   'jk' => 'P'],
                ['nama' => 'Jaka Maulana',        'jk' => 'L'],
                ['nama' => 'Kartika Dewi',        'jk' => 'P'],
            ],
            'XII AKL 1' => [
                ['nama' => 'Lukman Hakim',        'jk' => 'L'],
                ['nama' => 'Maya Anggraini',      'jk' => 'P'],
                ['nama' => 'Naufal Firdaus',      'jk' => 'L'],
                ['nama' => 'Oktaviani Rahma',     'jk' => 'P'],
                ['nama' => 'Putra Mahardika',     'jk' => 'L'],
                ['nama' => 'Qori Indah Sari',     'jk' => 'P'],
            ],
            'X TKJ 1' => [
                ['nama' => 'Rizky Ananda',        'jk' => 'L'],
                ['nama' => 'Salsabila Zahra',     'jk' => 'P'],
                ['nama' => 'Teguh Wicaksono',     'jk' => 'L'],
                ['nama' => 'Umar Faruq',          'jk' => 'L'],
                ['nama' => 'Vina Melati',         'jk' => 'P'],
                ['nama' => 'Wahyu Nugroho',       'jk' => 'L'],
            ],
            'XI DKV 1' => [
                ['nama' => 'Yuni Kartika',        'jk' => 'P'],
                ['nama' => 'Zainal Arifin',       'jk' => 'L'],
                ['nama' => 'Alya Maharani',       'jk' => 'P'],
                ['nama' => 'Bima Sakti',          'jk' => 'L'],
                ['nama' => 'Cahaya Ningsih',      'jk' => 'P'],
                ['nama' => 'Doni Kurniawan',      'jk' => 'L'],
            ],
            'XII TKJ 1' => [
                ['nama' => 'Erik Santoso',        'jk' => 'L'],
                ['nama' => 'Fina Rahmayanti',     'jk' => 'P'],
                ['nama' => 'Galih Purnama',       'jk' => 'L'],
                ['nama' => 'Hesti Puspita',       'jk' => 'P'],
                ['nama' => 'Ilham Nugraha',       'jk' => 'L'],
                ['nama' => 'Jasmine Alika',       'jk' => 'P'],
            ],
        ];

        $nisCounter  = 1001;
        $nisnCounter = 3000000001;
        $totalSiswa  = 0;

        foreach ($siswaMasterList as $kelasKey => $daftarSiswa) {
            // Explode tingkat & nama_kelas
            [$tingkat, $namaKelas] = explode(' ', $kelasKey, 2);

            $kelasObj = Kelas::where('tingkat', $tingkat)
                ->where('nama_kelas', $namaKelas)
                ->first();

            if (!$kelasObj) {
                continue;
            }

            foreach ($daftarSiswa as $sData) {
                Siswa::updateOrCreate(
                    ['nis' => (string) $nisCounter],
                    [
                        'nisn'          => (string) $nisnCounter,
                        'nama'          => $sData['nama'],
                        'jenis_kelamin' => $sData['jk'],
                        'id_kelas'      => $kelasObj->id,
                        'id_jurusan'    => $kelasObj->id_jurusan,
                        'status_siswa'  => 'Aktif',
                    ]
                );
                $nisCounter++;
                $nisnCounter++;
                $totalSiswa++;
            }
        }

        $this->command->info(" [SiswaSeeder] {$totalSiswa} Data Siswa (6 per kelas) berhasil dibuat/diperbarui.");
    }
}
