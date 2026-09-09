<?php

namespace App\Exports;

use App\Models\Kelas;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

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

            // 3. Siswa kelas ini — NO dimulai ulang dari 1 per kelas.
            //    NISN/NIS dipaksa string agar angka 10+ digit tidak berubah
            //    menjadi format scientific (mis. 3.11E+09) saat dibuka di Excel.
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

                // 1) Judul seksi kelas & judul kolom dicetak tebal.
                foreach ($this->boldRows as $row) {
                    $sheet->getStyle('A'.$row.':F'.$row)->getFont()->setBold(true);
                }

                $lastRow = max(1, $sheet->getHighestRow());

                // 2) Perataan teks: NO, NISN, NIS, JENIS KELAMIN, STATUS di tengah;
                //    NAMA SISWA (kolom D) rata kiri. Vertikal selalu tengah agar rapi.
                foreach (['A', 'B', 'C', 'E', 'F'] as $col) {
                    $sheet->getStyle($col.'1:'.$col.$lastRow)
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                }
                $sheet->getStyle('D1:D'.$lastRow)
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                // 3) NISN (kolom B) & NIS (kolom C) dipaksa type string/text eksplisit
                //    sehingga Excel tidak menampilkan angka panjang sebagai scientific.
                foreach ([2, 3] as $colIndex) {
                    $colLetter = Coordinate::stringFromColumnIndex($colIndex);
                    for ($row = 1; $row <= $lastRow; $row++) {
                        $coordinate = $colLetter.$row;
                        if ($sheet->cellExists($coordinate) && $sheet->getCell($coordinate)->getValue() !== null) {
                            $sheet->getCell($coordinate)->setDataType(DataType::TYPE_STRING);
                        }
                    }
                }

                // 4) Auto-fit lebar kolom A–F agar teks tidak terpotong.
                foreach (range(1, 6) as $colIndex) {
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($colIndex))
                        ->setAutoSize(true);
                }
            },
        ];
    }
}
