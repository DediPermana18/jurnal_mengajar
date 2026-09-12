<?php

namespace App\Exports;

use App\Models\Jurusan;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class JurusanExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection(): Enumerable
    {
        $jurusans = Jurusan::orderBy('kode_jurusan')->get();

        $no = 1;
        foreach ($jurusans as $j) {
            $j->setAttribute('export_no', $no++);
        }

        return new Collection($jurusans);
    }

    public function headings(): array
    {
        return [
            'NO',
            'KODE JURUSAN',
            'NAMA JURUSAN',
        ];
    }

    public function map($jurusan): array
    {
        return [
            $jurusan->export_no,
            $jurusan->kode_jurusan,
            $jurusan->nama_jurusan,
        ];
    }
}
