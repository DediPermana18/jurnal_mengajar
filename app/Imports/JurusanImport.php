<?php

namespace App\Imports;

use App\Imports\Concerns\TargetsImportPartition;
use App\Models\Jurusan;
use App\Models\Scopes\TestingDataScope;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class JurusanImport implements ToModel, WithHeadingRow
{
    use TargetsImportPartition;

    public int $importedCount = 0;

    public int $updatedCount = 0;

    public int $skippedCount = 0;

    public array $rowErrors = [];

    public function model(array $row): Model|array|null
    {
        // Kolom B → kode jurusan, Kolom C → nama jurusan. Nama kunci utamanya
        // kode_jurusan / nama_jurusan (slug dari header "KODE JURUSAN" &
        // "NAMA JURUSAN"), dengan fallback posisi untuk header lain.
        $kode = strtoupper(trim((string) ($row['kode_jurusan'] ?? '')));
        if ($kode === '') {
            $kode = strtoupper($this->cellAt($row, 1));
        }

        $nama = trim((string) ($row['nama_jurusan'] ?? ''));
        if ($nama === '') {
            $nama = $this->cellAt($row, 2);
        }

        if ($kode === '' || $nama === '') {
            $this->skippedCount++;
            $this->rowErrors[] = "Baris dengan kode '{$kode}' dilewati — kode / nama jurusan kosong.";

            return null;
        }

        // updateOrCreate penyetara: kode_jurusan unik GLOBAL (bukan per
        // partisi), dan baris lama bisa saja masih mengisi unique index walau
        // di-soft-delete. Karenanya telusuri lintas partisi + soft-delete agar
        // tidak menabrak unique constraint, lalu selaraskan partisi import-nya.
        $existing = Jurusan::withTrashed()
            ->withoutGlobalScope(TestingDataScope::class)
            ->where('kode_jurusan', $kode)
            ->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            $existing->forceFill([
                'nama_jurusan' => $nama,
                'is_testing_data' => $this->targetIsTestingData() ? 1 : 0,
            ])->save();
            $this->updatedCount++;

            return null;
        }

        $jurusan = new Jurusan;
        $jurusan->kode_jurusan = $kode;
        $jurusan->nama_jurusan = $nama;
        $jurusan->is_testing_data = $this->targetIsTestingData() ? 1 : 0;
        $jurusan->save();

        $this->importedCount++;

        return $jurusan;
    }
}
