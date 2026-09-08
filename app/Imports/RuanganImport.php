<?php

namespace App\Imports;

use App\Models\Ruangan;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class RuanganImport implements ToModel, WithHeadingRow
{
    public int $importedCount = 0;

    public int $updatedCount = 0;

    public int $skippedCount = 0;

    public array $rowErrors = [];

    public function model(array $row): Model|array|null
    {
        $kode = strtoupper(trim((string) ($row['kode_ruangan'] ?? '')));
        $nama = trim((string) ($row['nama_ruangan'] ?? ''));
        $lokasi = trim((string) ($row['lokasi_gedung'] ?? ''));

        // Abaikan baris jika kolom kode_ruangan kosong.
        if ($kode === '') {
            $this->skippedCount++;
            $this->rowErrors[] = "Baris dilewati — kode ruangan kosong.";

            return null;
        }

        $existing = Ruangan::where('kode_ruangan', $kode)->first();

        if ($existing) {
            $existing->update([
                'nama_ruangan' => $nama !== '' ? $nama : $existing->nama_ruangan,
                'lokasi' => $lokasi !== '' ? $lokasi : $existing->lokasi,
            ]);
            $this->updatedCount++;
            $this->skippedCount++;

            return null;
        }

        $ruangan = Ruangan::firstOrCreate(
            ['kode_ruangan' => $kode],
            [
                'nama_ruangan' => $nama !== '' ? $nama : $kode,
                'lokasi' => $lokasi !== '' ? $lokasi : null,
            ]
        );

        $this->importedCount++;

        return $ruangan;
    }
}
