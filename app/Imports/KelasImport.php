<?php

namespace App\Imports;

use App\Models\Jurusan;
use App\Models\Kelas;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class KelasImport implements ToModel, WithHeadingRow
{
    public int $importedCount = 0;

    public int $skippedCount = 0;

    public int $newKelasCount = 0;

    public array $rowErrors = [];

    public function model(array $row): Model|array|null
    {
        $namaKelas = trim((string) ($row['nama_kelas'] ?? ''));
        if ($namaKelas === '') {
            $this->skippedCount++;

            return null;
        }

        $tingkat = strtoupper(trim((string) ($row['tingkat'] ?? '')));
        if (! in_array($tingkat, ['X', 'XI', 'XII'], true)) {
            $this->skippedCount++;
            $this->rowErrors[] = "Baris '{$namaKelas}' dilewati — tingkat '".($row['tingkat'] ?? '')."' tidak valid (harus X, XI, atau XII).";

            return null;
        }

        // Cocokkan jurusan dari nama_kelas ATAU kode_jurusan yang diberikan.
        // Jika belum terdaftar di DB, buat jurusan baru secara otomatis
        // (fallback) sebelum record kelasnya dibuat.
        $jurusanRef = trim((string) ($row['jurusan'] ?? ''));
        $jurusan = null;
        if ($jurusanRef !== '') {
            $jurusan = Jurusan::where('nama_jurusan', $jurusanRef)
                ->orWhere('kode_jurusan', $jurusanRef)
                ->first();

            if (! $jurusan) {
                $jurusan = Jurusan::firstOrCreate(
                    ['nama_jurusan' => $jurusanRef],
                    ['kode_jurusan' => strtoupper($jurusanRef)]
                );
            }
        }

        $exists = Kelas::where('nama_kelas', $namaKelas)->exists();

        $kelas = Kelas::firstOrCreate(
            ['nama_kelas' => $namaKelas],
            [
                'tingkat' => $tingkat,
                'id_jurusan' => $jurusan->id ?? null,
            ]
        );

        if ($exists) {
            $this->skippedCount++;
            $this->rowErrors[] = "'{$namaKelas}' sudah ada sebagai {$tingkat} — dilewati (tidak dibuat duplikat).";
        } else {
            $this->importedCount++;
            $this->newKelasCount++;
        }

        return $kelas;
    }
}
