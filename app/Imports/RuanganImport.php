<?php

namespace App\Imports;

use App\Imports\Concerns\TargetsImportPartition;
use App\Models\Ruangan;
use App\Models\Scopes\TestingDataScope;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class RuanganImport implements ToCollection, WithHeadingRow, WithCustomCsvSettings
{
    use TargetsImportPartition;

    public int $importedCount = 0;

    public int $updatedCount = 0;

    public int $skippedCount = 0;

    public array $rowErrors = [];

    protected string $delimiter = ',';

    public function __construct(string $delimiter = ',')
    {
        $this->delimiter = $delimiter;
    }

    public function getCsvSettings(): array
    {
        return [
            'delimiter' => $this->delimiter,
        ];
    }

    /**
     * Factory method untuk membuat instance RuanganImport dengan auto-deteksi delimiter CSV (, / ;).
     */
    public static function createWithAutoDelimiter(?string $filePath = null): self
    {
        $delimiter = ',';
        if ($filePath && file_exists($filePath) && is_readable($filePath)) {
            $handle = fopen($filePath, 'r');
            if ($handle) {
                $firstLine = fgets($handle);
                fclose($handle);
                if ($firstLine !== false) {
                    $semicolonCount = substr_count($firstLine, ';');
                    $commaCount = substr_count($firstLine, ',');
                    if ($semicolonCount > $commaCount) {
                        $delimiter = ';';
                    }
                }
            }
        }

        return new self($delimiter);
    }

    /**
     * Helper function untuk mengubah Nama Ruangan menjadi Kode Ruangan:
     * - Ubah ke UPPERCASE.
     * - Ganti spasi, titik, dan karakter khusus menjadi strip (-).
     * - Bersihkan double dash '--' dan strip di awal/akhir.
     *
     * Contoh:
     * - "Lab. KI 1"       => "LAB-KI-1"
     * - "R 1"             => "R-1"
     * - "Lab. TKJ 2 (FO)" => "LAB-TKJ-2-FO"
     * - "LAP"             => "LAP"
     */
    public static function generateKodeRuangan(string $nama): string
    {
        $str = strtoupper(trim($nama));
        $str = preg_replace('/[^A-Z0-9]+/i', '-', $str);
        $str = trim((string) preg_replace('/-+/', '-', $str), '-');

        return $str;
    }

    /**
     * Memproses kumpulan baris dari file Excel / CSV.
     * Dapat menerima mentahan file "Data Jadwal.csv" (kolom 'Ruang')
     * maupun file master ruangan (kolom 'NAMA RUANGAN' / 'KODE RUANGAN').
     */
    public function collection(Collection $rows): void
    {
        $ruanganMap = [];

        foreach ($rows as $row) {
            $rowArray = is_array($row) ? $row : $row->toArray();

            // 1. Ambil nilai dari kolom 'Ruang' atau 'NAMA RUANGAN'
            $hasKnownHeading = array_key_exists('ruang', $rowArray)
                || array_key_exists('nama_ruangan', $rowArray)
                || array_key_exists('nama', $rowArray)
                || array_key_exists('ruangan', $rowArray)
                || array_key_exists('kode_ruangan', $rowArray);

            if ($hasKnownHeading) {
                $nama = trim((string) (
                    $rowArray['ruang']
                    ?? $rowArray['nama_ruangan']
                    ?? $rowArray['nama']
                    ?? $rowArray['ruangan']
                    ?? $rowArray['kode_ruangan']
                    ?? ''
                ));
            } else {
                $nama = $this->cellAt($rowArray, 2) ?: ($this->cellAt($rowArray, 1) ?: $this->cellAt($rowArray, 0));
            }

            // 2. Filter nilai kosong, null, atau tanda strip
            if ($nama === '' || $nama === '-' || strtolower($nama) === 'null') {
                $this->skippedCount++;
                continue;
            }

            // Lokasi gedung (default: "Gedung Sekolah")
            $lokasi = trim((string) (
                $rowArray['lokasi_gedung']
                ?? $rowArray['lokasi']
                ?? $rowArray['gedung']
                ?? ''
            ));
            if ($lokasi === '' || $lokasi === '-') {
                $lokasi = 'Gedung Sekolah';
            }

            // 3. Auto-generate Kode Ruangan
            $kodeRuangan = self::generateKodeRuangan($nama);
            if ($kodeRuangan === '') {
                $this->skippedCount++;
                continue;
            }

            // Simpan unik berdasarkan nama asli & kode_ruangan (array_unique logic)
            if (! isset($ruanganMap[$kodeRuangan])) {
                $ruanganMap[$kodeRuangan] = [
                    'nama_ruangan' => $nama,
                    'lokasi' => $lokasi,
                ];
            }
        }

        if (empty($ruanganMap)) {
            $this->rowErrors[] = 'Tidak ada data nama ruangan yang valid ditemukan dalam file.';
            return;
        }

        // 4. Database Action: updateOrCreate berdasarkan matching key 'kode_ruangan'
        foreach ($ruanganMap as $kode => $data) {
            $existing = Ruangan::withoutGlobalScope(TestingDataScope::class)
                ->where('kode_ruangan', $kode)
                ->first();

            if ($existing) {
                $existing->forceFill([
                    'nama_ruangan' => $data['nama_ruangan'],
                    'lokasi' => $data['lokasi'] ?: ($existing->lokasi ?: 'Gedung Sekolah'),
                    'is_testing_data' => $this->targetIsTestingData() ? 1 : 0,
                ])->save();

                $this->updatedCount++;
            } else {
                Ruangan::create([
                    'kode_ruangan' => $kode,
                    'nama_ruangan' => $data['nama_ruangan'],
                    'lokasi' => $data['lokasi'] ?: 'Gedung Sekolah',
                    'is_testing_data' => $this->targetIsTestingData() ? 1 : 0,
                ]);

                $this->importedCount++;
            }
        }
    }
}
