<?php

namespace App\Imports;

use App\Imports\Concerns\TargetsImportPartition;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Ruangan;
use App\Models\Scopes\TestingDataScope;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class JadwalImport implements ToCollection, WithHeadingRow, WithCustomCsvSettings
{
    use TargetsImportPartition;

    public int $importedCount = 0;

    public int $updatedCount = 0;

    public int $skippedCount = 0;

    public array $rowErrors = [];

    protected string $delimiter = ',';

    /**
     * Cache in-memory untuk menghindari query berulang per baris.
     * Key: string identifier, Value: model / null.
     */
    protected array $kelasCache   = [];
    protected array $jamCache     = [];
    protected array $mapelCache   = [];
    protected array $guruCache    = [];
    protected array $ruanganCache = [];

    /** Tahun ajaran aktif — diambil sekali saat pertama kali dibutuhkan. */
    protected ?TahunAjaran $tahunAjaran = null;

    /**
     * Nilai MataPelajaran yang dianggap "acara khusus" — bukan mata pelajaran
     * reguler. Baris ini dilewati secara diam-diam (tidak masuk rowErrors).
     */
    private const SKIP_MAPEL = [
        'upacara',
        'upacara/apel',
        'apel',
        'pembiasaan',
        'pembiasaan hari jumat',
        'pembiasaan jumat',
        'literasi',
    ];

    public function __construct(string $delimiter = ',')
    {
        $this->delimiter = $delimiter;
    }

    // ── WithCustomCsvSettings ─────────────────────────────────────

    public function getCsvSettings(): array
    {
        return [
            'delimiter' => $this->delimiter,
        ];
    }

    // ── Factory: auto-deteksi delimiter ──────────────────────────

    /**
     * Baca beberapa baris awal file (bukan hanya header) untuk menentukan
     * delimiter yang paling dominan — lebih andal dari cek satu baris karena
     * file dengan BOM atau komentar bisa menyebabkan false-positive.
     */
    public static function createWithAutoDelimiter(?string $filePath = null): self
    {
        $delimiter = ',';

        if ($filePath && file_exists($filePath) && is_readable($filePath)) {
            $handle = fopen($filePath, 'r');
            if ($handle) {
                $semicolons = 0;
                $commas     = 0;

                // Baca hingga 5 baris pertama
                for ($i = 0; $i < 5; $i++) {
                    $line = fgets($handle);
                    if ($line === false) {
                        break;
                    }
                    $semicolons += substr_count($line, ';');
                    $commas     += substr_count($line, ',');
                }
                fclose($handle);

                if ($semicolons > $commas) {
                    $delimiter = ';';
                }
            }
        }

        return new self($delimiter);
    }

    // ── Main collection handler ───────────────────────────────────

    public function collection(Collection $rows): void
    {
        $tahunAjaran = $this->resolveTahunAjaran();

        if (! $tahunAjaran) {
            $this->rowErrors[] = 'Tidak ada Tahun Ajaran aktif yang ditemukan. Import dibatalkan.';

            return;
        }

        foreach ($rows as $index => $row) {
            $rowNum  = $index + 2; // header di baris 1
            $rowData = is_array($row) ? $row : $row->toArray();

            // ── 1. Normalisasi semua key: lowercase + hapus spasi ─────
            // Maatwebsite sudah men-slug header (spasi → underscore, lowercase),
            // tapi beberapa edge-case (BOM, spasi ganda, camelCase) masih lolos.
            // Kita bangun ulang array dengan key yang bersih agar readCol aman.
            $rowData = $this->normalizeRowKeys($rowData);

            // ── 2. Baca kolom dasar ───────────────────────────────────
            $namaKelas    = $this->readCol($rowData, ['kelas']);
            $hari         = $this->readCol($rowData, ['hari']);
            $slotJam      = $this->readCol($rowData, ['jam', 'slot', 'jam_ke', 'no_jam']);   // mis. "1", "2-3"
            $waktuMulai   = $this->readCol($rowData, ['waktu_mulai', 'waktumulai', 'jam_mulai', 'mulai', 'start']);
            $waktuSelesai = $this->readCol($rowData, ['waktu_selesai', 'waktselesai', 'waktuselesai', 'jam_selesai', 'selesai', 'end']);
            $namaMapel    = $this->readCol($rowData, ['mata_pelajaran', 'matapelajaran', 'mapel', 'pelajaran', 'nama_mapel', 'subject']);
            $namaGuru     = $this->readCol($rowData, ['guru', 'nama_guru', 'pengajar', 'teacher']);
            $namaRuang    = $this->readCol($rowData, ['ruang', 'ruangan', 'nama_ruangan', 'kode_ruangan', 'room']);

            // ── 3. Skip baris kosong total ────────────────────────────
            if ($namaKelas === '' && $hari === '' && $namaMapel === '' && $namaGuru === '') {
                $this->skippedCount++;
                continue;
            }

            // ── 4. Skip acara khusus (Upacara / Pembiasaan / dll) ─────
            if ($this->isSpecialEvent($namaMapel)) {
                $this->skippedCount++;
                continue;
            }

            // ── 5. Validasi kolom wajib ───────────────────────────────
            $missing = [];
            if ($namaKelas === '') {
                $missing[] = 'Kelas';
            }
            if ($hari === '') {
                $missing[] = 'Hari';
            }
            if ($slotJam === '' && $waktuMulai === '') {
                $missing[] = 'Jam';
            }
            if ($namaMapel === '') {
                $missing[] = 'MataPelajaran';
            }
            if ($namaGuru === '') {
                $missing[] = 'Guru';
            }

            if (! empty($missing)) {
                $this->skippedCount++;
                $this->rowErrors[] = "Baris {$rowNum}: Kolom wajib kosong — ".implode(', ', $missing).".";
                continue;
            }

            // ── 6. Normalisasi Hari ───────────────────────────────────
            $hariNorm = $this->normalizeHari($hari);
            if (! $hariNorm) {
                $this->skippedCount++;
                $this->rowErrors[] = "Baris {$rowNum}: Nilai hari '{$hari}' tidak dikenali.";
                continue;
            }

            // ── 7. Resolve Kelas (wajib ada — abort jika tidak ditemukan) ──
            $kelas = $this->resolveKelas($namaKelas);
            if (! $kelas) {
                throw new \RuntimeException(
                    "Baris {$rowNum}: Kelas '{$namaKelas}' tidak ditemukan di Data Master Kelas. "
                    ."Pastikan semua kelas sudah di-import terlebih dahulu."
                );
            }

            // ── 8. Resolve MataPelajaran & Guru & Ruangan ──────────────
            $mapel = $this->resolveMapel($namaMapel);
            if (! $mapel) {
                $this->skippedCount++;
                $this->rowErrors[] = "Baris {$rowNum} (Kelas {$namaKelas}, {$hariNorm}): "
                    ."Mata Pelajaran '{$namaMapel}' belum terdaftar di Data Master.";
                continue;
            }

            $guru = $this->resolveGuru($namaGuru);
            if (! $guru) {
                $this->skippedCount++;
                $this->rowErrors[] = "Baris {$rowNum} (Kelas {$namaKelas}, {$hariNorm}, {$namaMapel}): "
                    ."Guru '{$namaGuru}' tidak ditemukan di Data Master Guru.";
                continue;
            }

            $ruangan = null;
            if ($namaRuang !== '') {
                $ruangan = $this->resolveRuangan($namaRuang);
            }

            // ── 9. Parse slot jam_ke (multi-slot support: "4.5.6", "2-3", "1") ──
            $jamKeList = $this->parseJamSlots($slotJam);

            // Fallback jika slotJam tidak menghasilkan angka tapi waktuMulai ada
            if (empty($jamKeList) && $waktuMulai !== '') {
                $fallbackJam = $this->resolveJamFlexible($waktuMulai, $waktuSelesai, '', $hariNorm);
                if ($fallbackJam && $fallbackJam->jam_ke) {
                    $jamKeList = [$fallbackJam->jam_ke];
                }
            }

            if (empty($jamKeList)) {
                $this->skippedCount++;
                $this->rowErrors[] = "Baris {$rowNum} (Kelas {$namaKelas}, Hari {$hariNorm}): Kolom 'Jam' ('{$slotJam}') tidak dapat diparsing ke nomor jam yang valid.";
                continue;
            }

            // Satu UUID group_id untuk seluruh slot jam pada baris ini
            $groupId = (string) Str::uuid();

            // ── 10. Loop insert/update ke jadwal_pelajaran per jam_ke ─
            foreach ($jamKeList as $jamKe) {
                $jamPelajaran = $this->queryMasterJamByHariAndJamKe($hariNorm, $jamKe);

                if (! $jamPelajaran) {
                    $this->skippedCount++;
                    $this->rowErrors[] = "Baris {$rowNum} (Kelas {$namaKelas}, Hari {$hariNorm}): Master jam ke-{$jamKe} tidak ditemukan.";
                    continue;
                }

                // Skip slot non-KBM (istirahat, agenda_rutin)
                if (in_array($jamPelajaran->jenis, ['istirahat', 'agenda_rutin'], true)) {
                    $this->skippedCount++;
                    continue;
                }

                $uniqueKey = [
                    'id_kelas'        => $kelas->id,
                    'hari'            => $hariNorm,
                    'id_jam'          => $jamPelajaran->id,
                    'id_tahun_ajaran' => $tahunAjaran->id,
                ];

                $fillValues = [
                    'group_id'        => $groupId,
                    'id_mapel'        => $mapel->id,
                    'id_guru'         => $guru->id,
                    'id_ruangan'      => $ruangan?->id,
                    'is_testing_data' => $this->targetIsTestingData() ? 1 : 0,
                ];

                $existing = JadwalPelajaran::withoutGlobalScope(TestingDataScope::class)
                    ->withTrashed()
                    ->where($uniqueKey)
                    ->first();

                if ($existing) {
                    if ($existing->trashed()) {
                        $existing->restore();
                    }

                    $existing->forceFill($fillValues)->save();
                    $this->updatedCount++;
                } else {
                    JadwalPelajaran::create(array_merge($uniqueKey, $fillValues));
                    $this->importedCount++;
                }
            }
        }
    }

    // ──────────────────────────────────────────────────────────────
    // HELPERS
    // ──────────────────────────────────────────────────────────────

    /**
     * Normalisasi key array baris CSV:
     *  - lowercase
     *  - hapus BOM dan karakter non-printable
     *  - collapse spasi / tab / garis bawah berlebih → single underscore
     *
     * Sehingga "Waktu Selesai", "waktu_selesai", "WAKTU SELESAI" semua
     * menghasilkan key "waktu_selesai" yang sama.
     */
    protected function normalizeRowKeys(array $row): array
    {
        $result = [];

        foreach ($row as $rawKey => $value) {
            // Hapus BOM UTF-8 dan karakter kontrol
            $key = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', (string) $rawKey);
            // Lowercase
            $key = strtolower(trim($key));
            // Spasi / strip / titik → underscore, lalu collapse underscore ganda
            $key = preg_replace('/[\s\-\.]+/', '_', $key);
            $key = preg_replace('/_+/', '_', $key);
            $key = trim($key, '_');

            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * Baca nilai kolom berdasarkan daftar alias key yang sudah di-normalisasi.
     */
    protected function readCol(array $row, array $aliases): string
    {
        foreach ($aliases as $alias) {
            if (array_key_exists($alias, $row)) {
                return trim((string) ($row[$alias] ?? ''));
            }
        }

        return '';
    }

    /**
     * Apakah nama mapel adalah acara/kegiatan khusus yang harus dilewati?
     * Pengecekan bersifat case-insensitive dan trim.
     */
    protected function isSpecialEvent(string $namaMapel): bool
    {
        if ($namaMapel === '') {
            return false;
        }

        $lower = strtolower(trim($namaMapel));

        // Exact match terhadap daftar tetap
        if (in_array($lower, self::SKIP_MAPEL, true)) {
            return true;
        }

        // Prefix match: "upacara *", "pembiasaan *"
        foreach (['upacara', 'pembiasaan', 'apel pagi'] as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                return true;
            }
        }

        return false;
    }

    // ── Normalisasi nilai Hari ────────────────────────────────────

    protected function normalizeHari(string $raw): ?string
    {
        $map = [
            'senin'   => 'Senin',
            'selasa'  => 'Selasa',
            'rabu'    => 'Rabu',
            'kamis'   => 'Kamis',
            'jumat'   => 'Jumat',
            "jum'at"  => 'Jumat',
            'jumuah'  => 'Jumat',
            'sabtu'   => 'Sabtu',
            // Bahasa Inggris (fallback)
            'monday'    => 'Senin',
            'tuesday'   => 'Selasa',
            'wednesday' => 'Rabu',
            'thursday'  => 'Kamis',
            'friday'    => 'Jumat',
            'saturday'  => 'Sabtu',
        ];

        return $map[strtolower(trim($raw))] ?? null;
    }

    // ── Parsing Slot Jam (multi-slot: "4.5.6", "2-3", "1") ───────

    /**
     * Memecah string kolom 'Jam' (misal: "4.5.6", "2-3", "1, 2, 3", "1")
     * menjadi array integer jam_ke.
     */
    protected function parseJamSlots(string $slotJam): array
    {
        $slotJam = trim($slotJam);
        if ($slotJam === '') {
            return [];
        }

        // Cek jika formatnya range angka dengan strip (misal "2-4" atau "1-3")
        if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $slotJam, $m)) {
            $start = (int) $m[1];
            $end   = (int) $m[2];

            if ($start > 0 && $end >= $start && ($end - $start) <= 12) {
                return range($start, $end);
            }
        }

        // Split berdasarkan titik, koma, strip, slash, atau spasi
        $parts = preg_split('/[\.\-,\/\s]+/', $slotJam);
        $result = [];

        foreach ($parts as $part) {
            if (is_numeric($part)) {
                $val = (int) $part;
                if ($val > 0) {
                    $result[] = $val;
                }
            }
        }

        return array_values(array_unique($result));
    }

    // ── Resolve JamPelajaran — strategi fleksibel ─────────────────

    /**
     * Resolve record Master Jam Pelajaran aktif berdasarkan kombinasi 'hari'
     * dan 'jam_ke' (atau waktu mulai/selesai).
     */
    protected function resolveJamFlexible(
        string $waktuMulai,
        string $waktuSelesai,
        string $slotJam,
        string $hari = ''
    ): ?JamPelajaran {
        if ($hari === '') {
            return null;
        }

        // Strategi 1: slot/nomor jam + hari (paling presisi)
        if ($slotJam !== '') {
            $jam = $this->resolveJamBySlot($slotJam, $hari);
            if ($jam) {
                return $jam;
            }
        }

        // Strategi 2: waktu eksplisit HH:MM + hari
        if ($waktuMulai !== '' && $waktuSelesai !== '') {
            $mulaiNorm   = $this->parseTimeString($waktuMulai);
            $selesaiNorm = $this->parseTimeString($waktuSelesai);

            if ($mulaiNorm && $selesaiNorm) {
                $jam = $this->queryJamByTime($mulaiNorm, $selesaiNorm, $hari);
                if ($jam) {
                    return $jam;
                }
            }
        }

        // Strategi 3: nilai waktu tunggal — cari berdasarkan mulai saja + hari
        if ($waktuMulai !== '') {
            $mulaiNorm = $this->parseTimeString($waktuMulai);
            if ($mulaiNorm) {
                $jam = $this->queryJamByMulaiOnly($mulaiNorm, $hari);
                if ($jam) {
                    return $jam;
                }
            }
        }

        return null;
    }

    /**
     * Query JamPelajaran berdasarkan jam_mulai + jam_selesai (HH:MM) dan hari.
     */
    protected function queryJamByTime(string $mulaiHHMM, string $selesaiHHMM, string $hari): ?JamPelajaran
    {
        if ($hari === '') {
            return null;
        }

        $key = $hari.'|'.$mulaiHHMM.'|'.$selesaiHHMM;

        if (array_key_exists($key, $this->jamCache)) {
            return $this->jamCache[$key];
        }

        $query = JamPelajaran::withoutGlobalScope(TestingDataScope::class)
            ->where('hari', $hari)
            ->whereRaw("TIME_FORMAT(jam_mulai, '%H:%i') = ?", [$mulaiHHMM])
            ->whereRaw("TIME_FORMAT(jam_selesai, '%H:%i') = ?", [$selesaiHHMM]);

        if (Schema::hasColumn('jam_pelajaran', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        $jam = $query->orderBy('id')->first();

        $this->jamCache[$key] = $jam;

        return $jam;
    }

    /**
     * Fallback: cari JamPelajaran hanya berdasarkan jam_mulai dan hari.
     */
    protected function queryJamByMulaiOnly(string $mulaiHHMM, string $hari): ?JamPelajaran
    {
        if ($hari === '') {
            return null;
        }

        $key = $hari.'|mulai_only:'.$mulaiHHMM;

        if (array_key_exists($key, $this->jamCache)) {
            return $this->jamCache[$key];
        }

        $query = JamPelajaran::withoutGlobalScope(TestingDataScope::class)
            ->where('hari', $hari)
            ->whereRaw("TIME_FORMAT(jam_mulai, '%H:%i') = ?", [$mulaiHHMM]);

        if (Schema::hasColumn('jam_pelajaran', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        $jam = $query->orderBy('jam_ke')->orderBy('id')->first();

        $this->jamCache[$key] = $jam;

        return $jam;
    }

    /**
     * Cari Master JamPelajaran aktif berdasarkan nomor slot / jam_ke dan hari.
     * Mengabaikan record soft delete (whereNull('deleted_at')).
     */
    protected function resolveJamBySlot(string $slotRaw, string $hari): ?JamPelajaran
    {
        if ($hari === '' || ! preg_match('/(\d+)/', $slotRaw, $m)) {
            return null;
        }

        return $this->queryMasterJamByHariAndJamKe($hari, (int) $m[1]);
    }

    /**
     * Query Master JamPelajaran aktif berdasarkan kombinasi 'hari' dan 'jam_ke'.
     * Mengabaikan record soft delete (whereNull('deleted_at')).
     */
    protected function queryMasterJamByHariAndJamKe(string $hari, int $jamKe): ?JamPelajaran
    {
        if ($hari === '' || $jamKe <= 0) {
            return null;
        }

        $key = $hari.'|jam_ke:'.$jamKe;

        if (array_key_exists($key, $this->jamCache)) {
            return $this->jamCache[$key];
        }

        $query = JamPelajaran::withoutGlobalScope(TestingDataScope::class)
            ->where('hari', $hari)
            ->where('jam_ke', $jamKe);

        if (Schema::hasColumn('jam_pelajaran', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        $jam = $query->orderBy('id')->first();

        $this->jamCache[$key] = $jam;

        return $jam;
    }

    /**
     * Parse string waktu ke format "HH:MM" yang siap dibandingkan dengan DB.
     *
     * Menangani:
     *  - "07:30", "7:30"             → "07:30"
     *  - "07.30", "7.30"             → "07:30"
     *  - "07:30:00"                   → "07:30"
     *  - Excel serial date (float)    → konversi ke waktu hari
     *  - String tanggal Excel yang salah parse, mis. "5/4/2006 7:40:00 AM"
     *    → ekstrak komponen waktu
     *
     * Mengembalikan null jika nilai tidak dapat diparsing menjadi waktu valid.
     */
    protected function parseTimeString(string $raw): ?string
    {
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        // ── Kasus 1: Angka desimal = Excel serial date (mis. 0.3125 = 07:30) ──
        if (is_numeric($raw) && str_contains($raw, '.')) {
            $fraction = (float) $raw;
            // Bila nilai ≥ 1, buang bagian tanggal (integer part)
            $fraction = $fraction - floor($fraction);
            $totalSeconds = (int) round($fraction * 86400);
            $h = intdiv($totalSeconds, 3600);
            $m = intdiv($totalSeconds % 3600, 60);

            if ($h >= 0 && $h < 24) {
                return sprintf('%02d:%02d', $h, $m);
            }
        }

        // ── Kasus 2: String tanggal Excel yang mengandung komponen waktu ──
        // Contoh: "5/4/2006 7:40:00 AM", "1899-12-30 07:30:00"
        if (preg_match('/(\d{1,2}):(\d{2})(?::\d{2})?\s*(AM|PM)?/i', $raw, $m)) {
            $h  = (int) $m[1];
            $mn = (int) $m[2];
            $meridiem = strtoupper($m[3] ?? '');

            if ($meridiem === 'PM' && $h < 12) {
                $h += 12;
            } elseif ($meridiem === 'AM' && $h === 12) {
                $h = 0;
            }

            if ($h >= 0 && $h < 24) {
                return sprintf('%02d:%02d', $h, $mn);
            }
        }

        // ── Kasus 3: Format "HH:MM" atau "HH.MM" biasa ───────────────────
        // Normalisasi titik → titik dua
        $normalized = str_replace('.', ':', $raw);

        // Hilangkan detik jika ada (HH:MM:SS → HH:MM)
        $normalized = preg_replace('/^(\d{1,2}:\d{2}):\d{2}$/', '$1', $normalized);

        if (preg_match('/^(\d{1,2}):(\d{2})$/', $normalized, $m)) {
            $h  = (int) $m[1];
            $mn = (int) $m[2];

            if ($h >= 0 && $h < 24 && $mn >= 0 && $mn < 60) {
                return sprintf('%02d:%02d', $h, $mn);
            }
        }

        // Tidak dapat diparsing
        return null;
    }

    // ── Resolve Kelas ─────────────────────────────────────────────

    protected function resolveKelas(string $namaInput): ?Kelas
    {
        $namaInputNorm = trim((string) preg_replace('/\s+/', ' ', $namaInput));
        $key = strtolower($namaInputNorm);

        if (array_key_exists($key, $this->kelasCache)) {
            return $this->kelasCache[$key];
        }

        // 1. Coba exact match nama_kelas (mis. "RPL 1"), atau dengan tingkat (mis. "XI RPL 1")
        $kelas = Kelas::withoutGlobalScope(TestingDataScope::class)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($namaInputNorm) {
                $q->whereRaw('LOWER(nama_kelas) = ?', [strtolower($namaInputNorm)])
                  ->orWhereRaw('LOWER(CONCAT(tingkat, " ", nama_kelas)) = ?', [strtolower($namaInputNorm)]);
            })
            ->first();

        // 2. Jika belum ketemu, coba parse prefix tingkat (X, XI, XII atau 10, 11, 12) + nama_kelas
        if (! $kelas) {
            $tingkatMap = ['XII' => 'XII', '12' => 'XII', 'XI' => 'XI', '11' => 'XI', 'X' => 'X', '10' => 'X'];
            foreach ($tingkatMap as $prefix => $targetTingkat) {
                if (preg_match('/^'.preg_quote($prefix, '/').'\s*[\-\.]?\s*(.+)$/i', $namaInputNorm, $m)) {
                    $strippedNama = trim($m[1]);
                    $kelas = Kelas::withoutGlobalScope(TestingDataScope::class)
                        ->whereNull('deleted_at')
                        ->where('tingkat', $targetTingkat)
                        ->whereRaw('LOWER(nama_kelas) = ?', [strtolower($strippedNama)])
                        ->first();

                    if ($kelas) {
                        break;
                    }
                }
            }
        }

        // 3. Fallback: jika tetap belum ketemu, coba strip prefix tingkat apapun
        if (! $kelas) {
            $stripped = trim((string) preg_replace('/^(XII|XI|X|12|11|10)\s*[\-\.]?\s*/i', '', $namaInputNorm));
            if ($stripped !== '' && $stripped !== $namaInputNorm) {
                $kelas = Kelas::withoutGlobalScope(TestingDataScope::class)
                    ->whereNull('deleted_at')
                    ->whereRaw('LOWER(nama_kelas) = ?', [strtolower($stripped)])
                    ->first();
            }
        }

        $this->kelasCache[$key] = $kelas;

        return $kelas;
    }

    // ── Resolve MataPelajaran (flexible matching) ─────────────────

    protected function resolveMapel(string $namaInput): ?MataPelajaran
    {
        $norm = trim((string) preg_replace('/\s+/', ' ', $namaInput));
        if ($norm === '') {
            return null;
        }

        $key = strtolower($norm);

        if (array_key_exists($key, $this->mapelCache)) {
            return $this->mapelCache[$key];
        }

        $kode = strtoupper($norm);

        // 1. Case-insensitive match via nama_mapel ATAU kode_mapel
        $mapel = MataPelajaran::withoutGlobalScope(TestingDataScope::class)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($key, $kode) {
                $q->whereRaw('LOWER(nama_mapel) = ?', [$key])
                  ->orWhere('kode_mapel', $kode);
            })
            ->first();

        // 2. Fallback: pencarian toleran tanda baca/spasi (contoh: "P.J.O.K" vs "PJOK")
        if (! $mapel) {
            $cleanedInput = strtolower((string) preg_replace('/[^\w]/u', '', $norm));
            if ($cleanedInput !== '') {
                $allMapel = MataPelajaran::withoutGlobalScope(TestingDataScope::class)
                    ->whereNull('deleted_at')
                    ->get();

                foreach ($allMapel as $m) {
                    $cleanedDBName = strtolower((string) preg_replace('/[^\w]/u', '', $m->nama_mapel));
                    $cleanedDBKode = strtolower((string) preg_replace('/[^\w]/u', '', $m->kode_mapel ?? ''));

                    if ($cleanedDBName === $cleanedInput || ($cleanedDBKode !== '' && $cleanedDBKode === $cleanedInput)) {
                        $mapel = $m;
                        break;
                    }
                }
            }
        }

        $this->mapelCache[$key] = $mapel;

        return $mapel;
    }

    // ── Helper Sanitasi Nama Guru ─────────────────────────────────

    /**
     * Membersihkan gelar akademik, gelar keagamaan/kehormatan, serta tanda baca
     * dari string nama guru agar pencarian pencocokan nama bersifat fleksibel.
     */
    protected function sanitizeNamaGuru(string $raw): string
    {
        $str = strtolower(trim($raw));

        // Hapus gelar umum (case-insensitive)
        $pattern = '/\b(drs|dra|ir|hj|h|dr|prof|kh|ustd|ust|s\.?pd\.?i?|s\.?t|s\.?kom|s\.?si|s\.?ag|s\.?se|s\.?e|s\.?sos|s\.?h|s\.?psi|m\.?pd\.?i?|m\.?t|m\.?kom|m\.?si|m\.?ag|m\.?m|m\.?se|m\.?e|m\.?sos|m\.?h|m\.?psi|a\.?md\.?(?:kom|t)?|gr)\b/i';
        $str = preg_replace($pattern, '', $str);

        // Hapus tanda baca & karakter khusus
        $str = preg_replace('/[^\w\s]/u', ' ', $str);

        // Normalize spasi berlebih
        return trim((string) preg_replace('/\s+/', ' ', $str));
    }

    // ── Resolve Guru (flexible matching with titles/punctuation) ──

    protected function resolveGuru(string $namaInput): ?User
    {
        $rawNorm = trim((string) preg_replace('/\s+/', ' ', $namaInput));
        if ($rawNorm === '') {
            return null;
        }

        $key = strtolower($rawNorm);

        if (array_key_exists($key, $this->guruCache)) {
            return $this->guruCache[$key];
        }

        // 1. Exact case-insensitive match
        $guru = User::withoutGlobalScope(TestingDataScope::class)
            ->where('role', User::ROLE_GURU)
            ->whereRaw('LOWER(nama) = ?', [$key])
            ->first();

        // 2. Flexible match: sanitasi gelar & tanda baca pada input dan data DB
        if (! $guru) {
            $sanitizedInput = $this->sanitizeNamaGuru($rawNorm);

            if ($sanitizedInput !== '') {
                $guruList = User::withoutGlobalScope(TestingDataScope::class)
                    ->where('role', User::ROLE_GURU)
                    ->get();

                // Match 2a: Sanitized exact match
                foreach ($guruList as $g) {
                    $sanitizedDB = $this->sanitizeNamaGuru($g->nama);
                    if ($sanitizedDB === $sanitizedInput) {
                        $guru = $g;
                        break;
                    }
                }

                // Match 2b: Substring / containment match (e.g. "Budi Santoso" vs "Budi")
                if (! $guru) {
                    foreach ($guruList as $g) {
                        $sanitizedDB = $this->sanitizeNamaGuru($g->nama);
                        if ($sanitizedDB !== '' && (str_contains($sanitizedDB, $sanitizedInput) || str_contains($sanitizedInput, $sanitizedDB))) {
                            $guru = $g;
                            break;
                        }
                    }
                }
            }
        }

        $this->guruCache[$key] = $guru;

        return $guru;
    }

    // ── Resolve Ruangan (with auto-create fallback) ─────────────

    protected function resolveRuangan(string $namaInput): ?Ruangan
    {
        $rawNorm = trim((string) preg_replace('/\s+/', ' ', $namaInput));
        if ($rawNorm === '') {
            return null;
        }

        $key = strtolower($rawNorm);

        if (array_key_exists($key, $this->ruanganCache)) {
            return $this->ruanganCache[$key];
        }

        $kode = RuanganImport::generateKodeRuangan($rawNorm);

        // 1. Match via kode_ruangan (auto-generated slug) ATAU nama_ruangan
        $ruangan = Ruangan::withoutGlobalScope(TestingDataScope::class)
            ->where(function ($q) use ($kode, $rawNorm) {
                $q->where('kode_ruangan', $kode)
                  ->orWhereRaw('LOWER(nama_ruangan) = ?', [strtolower($rawNorm)]);
            })
            ->first();

        // 2. Auto-create jika ruangan belum terdaftar di data master
        if (! $ruangan && $kode !== '') {
            $ruangan = Ruangan::create([
                'kode_ruangan'    => $kode,
                'nama_ruangan'    => $rawNorm,
                'lokasi'          => 'Gedung Utama',
                'is_testing_data' => $this->targetIsTestingData() ? 1 : 0,
            ]);
        }

        $this->ruanganCache[$key] = $ruangan;

        return $ruangan;
    }

    // ── Resolve Tahun Ajaran aktif ────────────────────────────────

    protected function resolveTahunAjaran(): ?TahunAjaran
    {
        if ($this->tahunAjaran !== null) {
            return $this->tahunAjaran;
        }

        $this->tahunAjaran = TahunAjaran::withoutGlobalScope(TestingDataScope::class)
            ->where('is_active', true)
            ->first();

        return $this->tahunAjaran;
    }
}
