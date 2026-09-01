<?php

namespace Database\Seeders;

use App\Models\Ruangan;
use App\Models\User;
use Illuminate\Database\Seeder;

class RuanganSeeder extends Seeder
{
    /**
     * Seed 10 Data Ruangan realistis untuk SMK.
     */
    public function run(): void
    {
        $ruanganData = [
            [
                'kode_ruangan' => 'R-01',
                'nama_ruangan' => 'Ruang Praktik RPL',
                'lokasi'       => 'Gedung Praktek Lantai 1',
            ],
            [
                'kode_ruangan' => 'R-02',
                'nama_ruangan' => 'Lab Komputer Network',
                'lokasi'       => 'Gedung B Lantai 2',
            ],
            [
                'kode_ruangan' => 'R-03',
                'nama_ruangan' => 'Lab Multimedia & Desain',
                'lokasi'       => 'Gedung B Lantai 1',
            ],
            [
                'kode_ruangan' => 'R-04',
                'nama_ruangan' => 'Lab Kimia Industri',
                'lokasi'       => 'Gedung B Lantai 1',
            ],
            [
                'kode_ruangan' => 'R-05',
                'nama_ruangan' => 'Ruang Kelas X RPL 1',
                'lokasi'       => 'Gedung A Lantai 1',
            ],
            [
                'kode_ruangan' => 'R-06',
                'nama_ruangan' => 'Ruang Kelas XI AKL 1',
                'lokasi'       => 'Gedung A Lantai 1',
            ],
            [
                'kode_ruangan' => 'R-07',
                'nama_ruangan' => 'Ruang Teori BD (Bisnis Digital)',
                'lokasi'       => 'Gedung A Lantai 2',
            ],
            [
                'kode_ruangan' => 'R-08',
                'nama_ruangan' => 'Perpustakaan Utama',
                'lokasi'       => 'Gedung A Lantai 1',
            ],
            [
                'kode_ruangan' => 'R-09',
                'nama_ruangan' => 'Ruang Guru',
                'lokasi'       => 'Gedung A Lantai 2',
            ],
            [
                'kode_ruangan' => 'R-10',
                'nama_ruangan' => 'Ruang Tata Usaha',
                'lokasi'       => 'Gedung A Lantai 1',
            ],
        ];

        // Ambil data guru untuk pengurus dummy (opsional)
        $gurus = User::where('role', 'guru')->get();

        foreach ($ruanganData as $index => $item) {
            $ruangan = Ruangan::updateOrCreate(
                ['kode_ruangan' => $item['kode_ruangan']],
                $item
            );

            // Lampirkan pengurus dummy jika data guru tersedia
            if ($gurus->isNotEmpty()) {
                $pengurusId = $gurus->get($index % $gurus->count())?->id;
                if ($pengurusId) {
                    $ruangan->pengurus()->syncWithoutDetaching([$pengurusId]);
                }
            }
        }
    }
}
