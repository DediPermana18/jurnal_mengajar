<?php

namespace App\Exports;

use App\Models\Kelas;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class KelasExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection(): Enumerable
    {
        $kelases = Kelas::with('jurusan')
            ->orderBy('tingkat')
            ->orderBy('nama_kelas')
            ->get();

        // NO di-assign sekali (idempotent) sehingga aman ketika iterasi berulang.
        $no = 1;
        foreach ($kelases as $k) {
            $k->setAttribute('export_no', $no++);
        }

        return new Collection($kelases);
    }

    public function headings(): array
    {
        return [
            'NO',
            'NAMA KELAS',
            'TINGKAT',
            'JURUSAN',
        ];
    }

    public function map($kelas): array
    {
        return [
            $kelas->export_no,
            $kelas->nama_kelas,
            $kelas->tingkat,
            $kelas->jurusan?->nama_jurusan ?? '-',
        ];
    }
}
