<?php

namespace App\Exports;

use App\Models\MataPelajaran;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class MataPelajaranExport implements FromCollection, WithHeadings, WithMapping
{
    protected bool $isTemplate;

    public function __construct(bool $isTemplate = false)
    {
        $this->isTemplate = $isTemplate;
    }

    public function collection(): Enumerable
    {
        if ($this->isTemplate) {
            // Data baris contoh untuk panduan pengisian template import
            return new Collection([
                [
                    'export_no' => 1,
                    'kode_mapel' => 'IND-01',
                    'nama_mapel' => 'Bahasa Indonesia',
                    'kelompok' => 'Muatan Umum',
                    'jurusan' => '-',
                ],
                [
                    'export_no' => 2,
                    'kode_mapel' => 'RPL-01',
                    'nama_mapel' => 'Pemrograman Web dan Perangkat Bergerak',
                    'kelompok' => 'Kejuruan',
                    'jurusan' => 'RPL',
                ],
                [
                    'export_no' => 3,
                    'kode_mapel' => 'BDD-01',
                    'nama_mapel' => 'Bahasa Daerah Sunda',
                    'kelompok' => 'Muatan Lokal',
                    'jurusan' => '-',
                ],
            ]);
        }

        $mapels = MataPelajaran::with('jurusan')
            ->orderBy('nama_mapel')
            ->get();

        $no = 1;
        foreach ($mapels as $m) {
            $m->setAttribute('export_no', $no++);
        }

        return new Collection($mapels);
    }

    public function headings(): array
    {
        return [
            'NO',
            'KODE MAPEL',
            'NAMA MATA PELAJARAN',
            'KELOMPOK',
            'JURUSAN',
        ];
    }

    public function map($mapel): array
    {
        if ($this->isTemplate) {
            return [
                $mapel['export_no'],
                $mapel['kode_mapel'],
                $mapel['nama_mapel'],
                $mapel['kelompok'],
                $mapel['jurusan'],
            ];
        }

        return [
            $mapel->export_no,
            $mapel->kode_mapel,
            $mapel->nama_mapel,
            $mapel->kelompok,
            $mapel->jurusan?->nama_jurusan ?? ($mapel->jurusan?->kode_jurusan ?? '-'),
        ];
    }
}
