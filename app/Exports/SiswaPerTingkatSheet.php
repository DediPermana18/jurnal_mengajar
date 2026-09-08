<?php

namespace App\Exports;

use App\Models\Kelas;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

class SiswaPerTingkatSheet implements FromCollection, WithEvents, WithTitle
{
    protected string $tingkat;

    protected string $title;

    /** Baris (1-based) yang harus dicetak tebal: header seksi & judul kolom tiap kelas. */
    protected array $boldRows = [];

    public function __construct(string $tingkat, string $title)
    {
        $this->tingkat = $tingkat;
        $this->title = $title;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function collection(): Enumerable
    {
        $data = collect();

        $kelases = Kelas::where('tingkat', $this->tingkat)
            ->has('siswa')
            ->with(['siswa' => fn ($q) => $q->orderBy('nama')->orderBy('id')])
            ->orderBy('nama_kelas')
            ->get();

        foreach ($kelases as $kelas) {
            // 1. Header seksi nama kelas (tebal)
            $this->boldRows[] = $data->count() + 1;
            $data->push(['KELAS: '.$kelas->nama_lengkap]);

            // 2. Judul kolom untuk kelas ini (tebal)
            $this->boldRows[] = $data->count() + 1;
            $data->push(['NO', 'NISN', 'NIS', 'NAMA SISWA', 'JENIS KELAMIN', 'STATUS']);

            // 3. Siswa kelas ini — NO dimulai ulang dari 1 per kelas
            $no = 1;
            foreach ($kelas->siswa as $siswa) {
                $data->push([
                    $no++,
                    $siswa->nisn,
                    $siswa->nis,
                    $siswa->nama,
                    $siswa->jenis_kelamin === 'P' ? 'Perempuan' : 'Laki-laki',
                    $siswa->status_siswa ?? 'Aktif',
                ]);
            }

            // 4. Dua baris kosong sebagai gap menuju kelompok kelas berikutnya.
            //    Pakai [''] (bukan []) agar PhpSpreadsheet benar-benar menulis baris kosong.
            $data->push(['']);
            $data->push(['']);
        }

        return $data;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                foreach ($this->boldRows as $row) {
                    $sheet->getStyle('A'.$row.':F'.$row)->getFont()->setBold(true);
                }
            },
        ];
    }
}
