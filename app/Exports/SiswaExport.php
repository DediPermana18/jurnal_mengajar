<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SiswaExport implements Export, WithMultipleSheets
{
    /**
     * Satu sheet per tingkat: KELAS X, KELAS XI, dan KELAS XII.
     */
    public function sheets(): array
    {
        return [
            new SiswaPerTingkatSheet('X', 'KELAS X'),
            new SiswaPerTingkatSheet('XI', 'KELAS XI'),
            new SiswaPerTingkatSheet('XII', 'KELAS XII'),
        ];
    }
}
