<?php

namespace App\Imports;

use App\Imports\Concerns\TargetsImportPartition;
use App\Models\Jurusan;
use App\Models\MataPelajaran;
use App\Models\Scopes\TestingDataScope;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class MataPelajaranImport implements ToModel, WithHeadingRow
{
    use TargetsImportPartition;

    public int $importedCount = 0;

    public int $updatedCount = 0;

    public int $skippedCount = 0;

    public array $rowErrors = [];

    public function model(array $row): Model|array|null
    {
        // 1. Baca KODE MAPEL
        $kode = strtoupper(trim((string) ($row['kode_mapel'] ?? $row['kode'] ?? '')));
        if ($kode === '') {
            $kode = strtoupper($this->cellAt($row, 1));
        }

        // 2. Baca NAMA MATA PELAJARAN
        $nama = trim((string) ($row['nama_mata_pelajaran'] ?? $row['nama_mapel'] ?? $row['nama'] ?? ''));
        if ($nama === '') {
            $nama = $this->cellAt($row, 2);
        }

        // Jika baris kosong seluruhnya
        if ($kode === '' && $nama === '') {
            $this->skippedCount++;
            return null;
        }

        if ($kode === '' || $nama === '') {
            $this->skippedCount++;
            $this->rowErrors[] = "Baris mapel '{$nama}' ({$kode}) dilewati karena kode atau nama mapel kosong.";
            return null;
        }

        // 3. Baca KELOMPOK / JENIS MAPEL
        $kelompokRaw = trim((string) ($row['kelompok'] ?? $row['jenis_mapel'] ?? $row['jenis'] ?? ''));
        if ($kelompokRaw === '') {
            $kelompokRaw = $this->cellAt($row, 3);
        }

        $kelompok = $this->normalizeKelompok($kelompokRaw);

        // 4. Baca JURUSAN
        $jurusanRef = trim((string) ($row['jurusan'] ?? $row['kode_jurusan'] ?? $row['nama_jurusan'] ?? $row['jurusan_id'] ?? ''));
        if ($jurusanRef === '') {
            $jurusanRef = $this->cellAt($row, 4);
        }
        if (in_array($jurusanRef, ['', '-'], true)) {
            $jurusanRef = '';
        }

        $jurusanId = null;
        if ($kelompok === 'Kejuruan') {
            if ($jurusanRef === '') {
                throw new \InvalidArgumentException("Mapel Kejuruan '{$nama}' ({$kode}) wajib mengisi kolom JURUSAN.");
            }

            $jurusan = $this->resolveJurusan($jurusanRef);
            if (! $jurusan) {
                throw new \InvalidArgumentException("Jurusan '{$jurusanRef}' pada mapel '{$nama}' ({$kode}) tidak ditemukan di Data Master Jurusan.");
            }

            $jurusanId = $jurusan->id;
        }

        // 5. Update or Create berbasis kode_mapel (lintas partisi + soft-deleted)
        $existing = MataPelajaran::withTrashed()
            ->withoutGlobalScope(TestingDataScope::class)
            ->where('kode_mapel', $kode)
            ->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            $existing->forceFill([
                'nama_mapel' => $nama,
                'kelompok' => $kelompok,
                'jurusan_id' => $jurusanId,
                'is_testing_data' => $this->targetIsTestingData() ? 1 : 0,
            ])->save();

            $this->updatedCount++;
            return null;
        }

        $mapel = new MataPelajaran();
        $mapel->kode_mapel = $kode;
        $mapel->nama_mapel = $nama;
        $mapel->kelompok = $kelompok;
        $mapel->jurusan_id = $jurusanId;
        $mapel->is_testing_data = $this->targetIsTestingData() ? 1 : 0;
        $mapel->save();

        $this->importedCount++;

        return $mapel;
    }

    /**
     * Normalisasi nilai kelompok mapel.
     */
    protected function normalizeKelompok(string $raw): string
    {
        $lower = strtolower(trim($raw));

        if (in_array($lower, ['kejuruan', 'produktif', 'c1', 'c2', 'c3'], true)) {
            return 'Kejuruan';
        }

        if (in_array($lower, ['muatan lokal', 'mulok', 'lokal'], true)) {
            return 'Muatan Lokal';
        }

        // Default ke Muatan Umum (Umum / Muatan Umum / Wajib / dsb)
        return 'Muatan Umum';
    }

    /**
     * Mencari model Jurusan berdasarkan ID, Kode Jurusan, atau Nama Jurusan.
     */
    protected function resolveJurusan(string $ref): ?Jurusan
    {
        $jurusan = Jurusan::withTrashed()
            ->withoutGlobalScope(TestingDataScope::class)
            ->where(function ($q) use ($ref) {
                if (is_numeric($ref)) {
                    $q->where('id', (int) $ref)
                      ->orWhere('kode_jurusan', $ref)
                      ->orWhere('nama_jurusan', $ref);
                } else {
                    $q->where('kode_jurusan', $ref)
                      ->orWhere('nama_jurusan', $ref);
                }
            })
            ->first();

        if (! $jurusan) {
            return null;
        }

        if ($jurusan->trashed()) {
            $jurusan->restore();
        }

        return $jurusan;
    }
}
