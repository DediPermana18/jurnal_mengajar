<?php

namespace App\Imports;

use App\Models\Jurusan;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class JurusanImport implements ToModel, WithHeadingRow
{
    public int $importedCount = 0;

    public int $updatedCount = 0;

    public int $skippedCount = 0;

    public array $rowErrors = [];

    public function model(array $row): Model|array|null
    {
        $kode = strtoupper(trim((string) ($row['kode_jurusan'] ?? '')));
        $nama = trim((string) ($row['nama_jurusan'] ?? ''));

        if ($kode === '' || $nama === '') {
            $this->skippedCount++;
            $this->rowErrors[] = "Baris dengan kode '{$kode}' dilewati — kode / nama jurusan kosong.";

            return null;
        }

        $existing = Jurusan::where('kode_jurusan', $kode)->first();

        if ($existing) {
            $existing->update(['nama_jurusan' => $nama]);
            $this->updatedCount++;
            $this->skippedCount++;

            return null;
        }

        $jurusan = Jurusan::firstOrCreate(
            ['kode_jurusan' => $kode],
            ['nama_jurusan' => $nama]
        );

        $this->importedCount++;

        return $jurusan;
    }
}
