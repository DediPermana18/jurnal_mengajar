<?php

namespace Database\Seeders;

use App\Models\Jurusan;
use Illuminate\Database\Seeder;

class JurusanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $dataJurusan = [
            [
                'kode_jurusan' => 'RPL',
                'nama_jurusan' => 'Rekayasa Perangkat Lunak',
            ],
            [
                'kode_jurusan' => 'TKJ',
                'nama_jurusan' => 'Teknik Komputer dan Jaringan',
            ],
            [
                'kode_jurusan' => 'AKL',
                'nama_jurusan' => 'Akuntansi dan Keuangan Lembaga',
            ],
            [
                'kode_jurusan' => 'TKR',
                'nama_jurusan' => 'Teknik Kendaraan Ringan',
            ],
        ];

        foreach ($dataJurusan as $item) {
            Jurusan::updateOrCreate(
                ['kode_jurusan' => $item['kode_jurusan']],
                ['nama_jurusan' => $item['nama_jurusan']]
            );
        }
    }
}
