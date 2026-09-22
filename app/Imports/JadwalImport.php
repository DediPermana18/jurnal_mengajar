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
            if ($namaMapel === '') {
                $missing[] = 'MataPelajaran';
            }
            if ($namaGuru === '') {
                $missing[] = 'Guru';
            }
            // WaktuMulai & WaktuSelesai wajib, KECUALI jika kolom Jam/slot tersedia
            $hasTimeBySlot = ($slotJam !== '');
            if ($waktuMulai === '' && ! $hasTimeBySlot) {
                $missing[] = 'WaktuMulai';
            }
            if ($waktuSelesai === '' && ! $hasTimeBySlot) {
                $missing[] = 'WaktuSelesai';
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

            // ── 8. Resolve JamPelajaran ───────────────────────────────
            // Strategi (berurutan, berhenti pada yang berhasil):
            //   a) Gunakan WaktuMulai + WaktuSelesai jika tersedia.
            //   b) Parse waktu dari kolom Jam/slot (nomor slot → cari di master).
            //   c) Fallback: parse nilai Excel serial date yang salah terbaca.
            $jamPelajaran = $this->resolveJamFlexible(
                $waktuMulai,
                $waktuSelesai,
                $slotJam,
                $hariNorm
            );

            if (! $jamPelajaran) {
                $this->skippedCount++;
                $this->rowErrors[] = "Baris {$rowNum} (Kelas {$namaKelas}, {$hariNorm}): "
                    ."Jam '{$waktuMulai}–{$waktuSelesai}' (slot '{$slotJam}') tidak ditemukan di master Jam Pelajaran.";
                continue;
            }

            // ── 9. Resolve MataPelajaran (strict — skip jika tidak ada) ─
            $mapel = $this->resolveMapel($namaMapel);
            if (! $mapel) {
                $this->skippedCount++;
                $this->rowErrors[] = "Baris {$rowNum} (Kelas {$namaKelas}, {$hariNorm}): "
                    ."Mata Pelajaran '{$namaMapel}' belum terdaftar di Data Master.";
                continue;
            }

            // ── 10. Resolve Guru (strict — skip jika tidak ada) ───────
            $guru = $this->resolveGuru($namaGuru);
            if (! $guru) {
                $this->skippedCount++;
                $this->rowErrors[] = "Baris {$rowNum} (Kelas {$namaKelas}, {$hariNorm}, {$namaMapel}): "
                    ."Guru '{$namaGuru}' tidak ditemukan di Data Master Guru.";
                continue;
            }

            // ── 11. Resolve Ruangan (strict — opsional, null jika tidak ada) ──
            $ruangan = null;
            if ($namaRuang !== '') {
                $ruangan = $this->resolveRuangan($namaRuang);
                if (! $ruangan) {
                    $this->skippedCount++;
                    $this->rowErrors[] = "Baris {$rowNum} (Kelas {$namaKelas}, {$hariNorm}, {$namaMapel}): "
                        ."Ruangan '{$namaRuang}' belum terdaftar di Data Master.";
                    continue;
                }
            }

            // ── 12. updateOrCreate ke jadwal_pelajaran ─────────────────
            $uniqueKey = [
                'id_kelas'        => $kelas->id,
                'hari'            => $hariNorm,
                'id_jam'          => $jamPelajaran->id,
                'id_tahun_ajaran' => $tahunAjaran->id,
            ];

            $fillValues = [
                'group_id'        => (string) Str::uuid(),
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

    // ── Resolve JamPelajaran — strategi fleksibel ─────────────────

    /**
     * Coba tiga strategi secara berurutan:
     *
     *  1. WaktuMulai + WaktuSelesai (format HH:MM atau HH.MM) — paling andal.
     *  2. Kolom Jam/slot (nomor "1", "2", "2-3", "4") → cari di master berdasarkan
     *     `jam_ke` (ambil jam pertama dari range).
     *  3. Nilai yang terbaca sebagai serial tanggal Excel atau string tanggal
     *     (mis. "5/4/2006 07:30:00") → ekstrak komponen waktu-nya.
     */
    protected function resolveJamFlexible(
        string $waktuMulai,
        string $waktuSelesai,
        string $slotJam,
        string $hari = ''
    ): ?JamPelajaran {
        // Strategi 1: slot/nomor jam + hari (paling presisi)
        if ($slotJam !== '' && $hari !== '') {
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

        // Strategi 3: slot/nomor jam tanpa filter hari
        if ($slotJam !== '') {
            $jam = $this->resolveJamBySlot($slotJam, '');
            if ($jam) {
                return $jam;
            }
        }

        // Strategi 4: nilai waktu tunggal — cari berdasarkan mulai saja + hari
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
    protected function queryJamByTime(string $mulaiHHMM, string $selesaiHHMM, string $hari = ''): ?JamPelajaran
    {
        $key = $hari.'|'.$mulaiHHMM.'|'.$selesaiHHMM;

        if (array_key_exists($key, $this->jamCache)) {
            return $this->jamCache[$key];
        }

        $jam = JamPelajaran::withoutGlobalScope(TestingDataScope::class)
            ->when($hari !== '', fn ($q) => $q->where('hari', $hari))
            ->whereRaw("TIME_FORMAT(jam_mulai, '%H:%i') = ?", [$mulaiHHMM])
            ->whereRaw("TIME_FORMAT(jam_selesai, '%H:%i') = ?", [$selesaiHHMM])
            ->first();

        $this->jamCache[$key] = $jam;

        return $jam;
    }

    /**
     * Fallback: cari JamPelajaran hanya berdasarkan jam_mulai dan hari.
     */
    protected function queryJamByMulaiOnly(string $mulaiHHMM, string $hari = ''): ?JamPelajaran
    {
        $key = $hari.'|mulai_only:'.$mulaiHHMM;

        if (array_key_exists($key, $this->jamCache)) {
            return $this->jamCache[$key];
        }

        $jam = JamPelajaran::withoutGlobalScope(TestingDataScope::class)
            ->when($hari !== '', fn ($q) => $q->where('hari', $hari))
            ->whereRaw("TIME_FORMAT(jam_mulai, '%H:%i') = ?", [$mulaiHHMM])
            ->orderBy('jam_ke')
            ->first();

        $this->jamCache[$key] = $jam;

        return $jam;
    }

    /**
     * Cari JamPelajaran berdasarkan nomor slot / jam_ke dan hari.
     */
    protected function resolveJamBySlot(string $slotRaw, string $hari = ''): ?JamPelajaran
    {
        if (! preg_match('/(\d+)/', $slotRaw, $m)) {
            return null;
        }

        $jamKe = (int) $m[1];
        $key   = $hari.'|slot:'.$jamKe;

        if (array_key_exists($key, $this->jamCache)) {
            return $this->jamCache[$key];
        }

        $jam = JamPelajaran::withoutGlobalScope(TestingDataScope::class)
            ->when($hari !== '', fn ($q) => $q->where('hari', $hari))
            ->where('jam_ke', $jamKe)
            ->orderBy('id')
            ->first();

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

    // ── Resolve MataPelajaran (strict match, no auto-create) ──────

    protected function resolveMapel(string $namaInput): ?MataPelajaran
    {
        $key = strtolower(trim($namaInput));

        if (array_key_exists($key, $this->mapelCache)) {
            return $this->mapelCache[$key];
        }

        // Match via nama_mapel (case-insensitive) ATAU kode_mapel (uppercase)
        $kode  = strtoupper(trim($namaInput));
        $mapel = MataPelajaran::withoutGlobalScope(TestingDataScope::class)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($key, $kode) {
                $q->whereRaw('LOWER(nama_mapel) = ?', [$key])
                  ->orWhere('kode_mapel', $kode);
            })
            ->first();

        $this->mapelCache[$key] = $mapel;

        return $mapel;
    }

    // ── Resolve Guru ──────────────────────────────────────────────

    protected function resolveGuru(string $namaInput): ?User
    {
        $key = strtolower(trim($namaInput));

        if (array_key_exists($key, $this->guruCache)) {
            return $this->guruCache[$key];
        }

        $guru = User::withoutGlobalScope(TestingDataScope::class)
            ->where('role', User::ROLE_GURU)
            ->whereRaw('LOWER(nama) = ?', [$key])
            ->first();

        $this->guruCache[$key] = $guru;

        return $guru;
    }

    // ── Resolve Ruangan (strict match, no auto-create) ──────────

    protected function resolveRuangan(string $namaInput): ?Ruangan
    {
        $key = strtolower(trim($namaInput));

        if (array_key_exists($key, $this->ruanganCache)) {
            return $this->ruanganCache[$key];
        }

        $kode = RuanganImport::generateKodeRuangan($namaInput);

        // Match via kode_ruangan (auto-generated slug) ATAU nama_ruangan
        $ruangan = Ruangan::withoutGlobalScope(TestingDataScope::class)
            ->where(function ($q) use ($kode, $namaInput) {
                $q->where('kode_ruangan', $kode)
                  ->orWhereRaw('LOWER(nama_ruangan) = ?', [strtolower($namaInput)]);
            })
            ->first();

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
