<?php

namespace App\Imports;

use App\Imports\Concerns\TargetsImportPartition;
use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\Scopes\TestingDataScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class KelasImport implements ToModel, WithHeadingRow
{
    use TargetsImportPartition;

    public int $importedCount = 0;

    public int $updatedCount = 0;

    public int $skippedCount = 0;

    public int $newKelasCount = 0;

    public array $rowErrors = [];

    public function model(array $row): Model|array|null
    {
        // Struktur file: A=NO, B=NAMA KELAS, C=TINGKAT, D=JURUSAN,
        // E=WALI KELAS. Kolom TOTAL SISWA TIDAK diproses — jumlah siswa
        // dihitung otomatis oleh sistem per kelas.
        $namaKelas = $this->readColumn($row, 'nama_kelas', 1);
        if ($namaKelas === '') {
            $this->skippedCount++;

            return null;
        }

        $tingkat = strtoupper($this->readColumn($row, 'tingkat', 2));
        if (! in_array($tingkat, ['X', 'XI', 'XII'], true)) {
            $this->skippedCount++;
            $this->rowErrors[] = "Baris '{$namaKelas}' dilewati — tingkat '".($row['tingkat'] ?? '')."' tidak valid (harus X, XI, atau XII).";

            return null;
        }

        // Cocokkan jurusan dari nama_jurusan ATAU kode_jurusan. Kolom '-' / kosong
        // dianggap tanpa jurusan. Jika belum terdaftar, buat otomatis (fallback).
        $jurusanRef = trim($this->readColumn($row, 'jurusan', 3));
        if (in_array($jurusanRef, ['', '-'], true)) {
            $jurusanRef = '';
        }

        $jurusan = null;
        if ($jurusanRef !== '') {
            // kode_jurusan unik GLOBAL + baris lama bisa masih mengisi unique
            // index walau soft-deleted → telusuri lintas partisi agar tidak
            // menabrak unique constraint, lalu selaraskan partisinya.
            $jurusan = $this->resolveJurusan($jurusanRef);

            if (! $jurusan) {
                $jurusan = Jurusan::withoutGlobalScope(TestingDataScope::class)->create([
                    'kode_jurusan' => substr(strtoupper(trim($jurusanRef)), 0, 20),
                    'nama_jurusan' => $jurusanRef,
                    'is_testing_data' => $this->targetIsTestingData() ? 1 : 0,
                ]);
            }
        }

        // Wali kelas (opsional): nama guru di kolom E → user dengan nama persis.
        $waliRef = $this->readColumn($row, 'wali_kelas', 4);
        if (in_array($waliRef, ['', '-'], true)) {
            $waliRef = '';
        }

        $wali = $waliRef !== '' ? User::where('nama', $waliRef)->first() : null;

        // Duplikat didefinisikan sebagai kombinasi TINGKAT + NAMA KELAS.
        // 'AK 1' untuk X, XI, dan XII adalah tiga kelas berbeda. Hanya dilewati
        // bila tingkat DAN nama kelasnya persis sama.
        $existing = Kelas::where('tingkat', $tingkat)
            ->where('nama_kelas', $namaKelas)
            ->first();

        if ($existing) {
            $this->skippedCount++;
            $this->rowErrors[] = "'{$namaKelas}' sudah ada sebagai {$tingkat} — dilewati (tidak dibuat duplikat).";

            return null;
        }

        // Kelas yang pernah dihapus (soft delete) dengan identitas (tingkat +
        // nama_kelas) yang sama: pulihkan agar tidak dibuat duplikat.
        $trashed = Kelas::onlyTrashed()
            ->where('tingkat', $tingkat)
            ->where('nama_kelas', $namaKelas)
            ->first();

        if ($trashed) {
            $trashed->restore();
            $trashed->update([
                'id_jurusan' => $jurusan?->id,
                'id_wali_kelas' => $wali?->id,
            ]);
            $this->updatedCount++;

            return null;
        }

        $kelas = Kelas::create([
            'nama_kelas' => $namaKelas,
            'tingkat' => $tingkat,
            'id_jurusan' => $jurusan?->id,
            'id_wali_kelas' => $wali?->id,
            'is_testing_data' => $this->targetIsTestingData() ? 1 : 0,
        ]);

        $this->importedCount++;
        $this->newKelasCount++;

        return $kelas;
    }

    /**
     * Baca kolom dengan kunci heading slug (nama_kelas / tingkat / jurusan /
     * wali_kelas), fallback ke posisi kolom (0-based: B=1, C=2, D=3, E=4)
     * bila header tidak ter-slug sesuai kunci tersebut.
     */
    protected function readColumn(array $row, string $key, int $position): string
    {
        $value = trim((string) ($row[$key] ?? ''));

        if ($value === '') {
            $value = $this->cellAt($row, $position);
        }

        return $value;
    }

    /**
     * Resolve jurusan dari nama ATAU kode, lintas partisi + soft-delete
     * (harmonis dengan fix duplikat unique-index pada JurusanImport).
     */
    protected function resolveJurusan(string $ref): ?Jurusan
    {
        $jurusan = Jurusan::withTrashed()
            ->withoutGlobalScope(TestingDataScope::class)
            ->where('nama_jurusan', $ref)
            ->orWhere('kode_jurusan', $ref)
            ->first();

        if (! $jurusan) {
            return null;
        }

        if ($jurusan->trashed()) {
            $jurusan->restore();
        }

        if ((bool) $jurusan->is_testing_data !== $this->targetIsTestingData()) {
            $jurusan->forceFill(['is_testing_data' => $this->targetIsTestingData() ? 1 : 0])->save();
        }

        return $jurusan;
    }
}
