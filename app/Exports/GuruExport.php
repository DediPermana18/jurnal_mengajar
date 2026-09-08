<?php

namespace App\Exports;

use App\Models\Guru;
use Illuminate\Support\Enumerable;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class GuruExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection(): Enumerable
    {
        $gurus = Guru::orderBy('id')->get();

        // NO di-assign sekali (idempotent) sehingga aman ketika iterasi berulang.
        $no = 1;
        foreach ($gurus as $guru) {
            $guru->setAttribute('export_no', $no++);
        }

        return new Collection($gurus);
    }

    public function headings(): array
    {
        return [
            'NO',
            'NIP',
            'NAMA GURU',
            'STATUS',
        ];
    }

    public function map($guru): array
    {
        return [
            $guru->export_no,
            $guru->nip ?: '-',
            $guru->nama,
            $guru->is_active ? 'Aktif' : 'Nonaktif',
        ];
    }
}