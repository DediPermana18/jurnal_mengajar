<?php

namespace App\Exports;

use App\Models\Ruangan;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class RuanganExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection(): Enumerable
    {
        $ruangans = Ruangan::orderBy('kode_ruangan')->get();

        $no = 1;
        foreach ($ruangans as $r) {
            $r->setAttribute('export_no', $no++);
        }

        return new Collection($ruangans);
    }

    public function headings(): array
    {
        return [
            'NO',
            'KODE RUANGAN',
            'NAMA RUANGAN',
            'LOKASI / GEDUNG',
        ];
    }

    public function map($ruangan): array
    {
        return [
            $ruangan->export_no,
            $ruangan->kode_ruangan,
            $ruangan->nama_ruangan,
            $ruangan->lokasi ?? '',
        ];
    }
}
