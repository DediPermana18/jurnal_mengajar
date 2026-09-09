<?php

namespace App\Exports;

use App\Models\Kelas;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;

class SiswaFlatExport implements FromCollection
{
    public function collection(): Enumerable
    {
        $data = collect();

        $kelases = Kelas::has('siswa')
            ->with(['siswa' => fn ($q) => $q->orderBy('nama')->orderBy('id')])
            ->orderBy('tingkat')
            ->orderBy('nama_kelas')
            ->get();

        foreach ($kelases as $kelas) {
            // 1. Header seksi nama kelas
            $data->push(['KELAS: '.$kelas->nama_lengkap]);

            // 2. Judul kolom untuk kelas ini
            $data->push(['NO', 'NISN', 'NIS', 'NAMA SISWA', 'JENIS KELAMIN', 'STATUS']);

            // 3. Siswa kelas ini — NO dimulai ulang dari 1 per kelas.
            //    NISN/NIS ditulis sebagai string agar 10+ digit tidak berubah
            //    menjadi format scientific saat dibuka kembali di Excel.
            $no = 1;
            foreach ($kelas->siswa as $siswa) {
                $data->push([
                    $no++,
                    (string) $siswa->nisn,
                    $siswa->nis !== null ? (string) $siswa->nis : '',
                    $siswa->nama,
                    $siswa->jenis_kelamin === 'P' ? 'Perempuan' : 'Laki-laki',
                    $siswa->status_siswa ?? 'Aktif',
                ]);
            }

            // 4. Dua baris kosong sebagai gap menuju kelompok kelas berikutnya
            $data->push(['']);
            $data->push(['']);
        }

        return $data;
    }
}
