<?php

namespace App\Imports;

use App\Models\Kelas;
use App\Models\Siswa;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeSheet;

class SiswaImport implements ToCollection, WithEvents
{
    // ─── State ────────────────────────────────────────────────────────────────
    protected const TINGKAT_LIST = ['X', 'XI', 'XII'];

    /** ID kelas fallback dari dropdown UI (opsional). */
    protected ?int $fallbackIdKelas;

    /** Nama sheet (tab Excel) yang sedang diproses — untuk logging saja. */
    protected ?string $sheetName = null;

    /** Kelas AKTIF dari blok header terakhir (mis. "X TKJ 1"). */
    protected ?Kelas $currentKelas = null;

    /** Kelas yang baru saja di-resolve untuk blok header terakhir. */
    protected ?Kelas $resolvedKelas = null;

    /** Nama kelas (raw) dari blok header terakhir. */
    protected ?string $parsedNamaKelasRaw = null;

    public int $importedCount = 0;

    public int $skippedCount = 0;

    public int $newKelasCount = 0;   // SELALU 0: tidak pernah auto-create kelas

    public array $rowErrors = [];

    /**
     * Daftar lengkap representasi kelas VALID dari DB, dipakai untuk mencocokkan
     * header. Setiap entry: ['id', 'full' => "X TKJ 1", 'name' => "TKJ 1"].
     */
    protected array $classList = [];

    // ─── Konstruktor ──────────────────────────────────────────────────────────

    public function __construct(?int $fallbackIdKelas = null)
    {
        $this->fallbackIdKelas = $fallbackIdKelas;
    }

    /**
     * Reset state per-sheet. With ToCollection satu instance dipakai untuk
     * SEMUA sheet, jadi tanpa reset, kelas aktif dari sheet sebelumnya
     * ikut menempel pada baris pertama sheet berikutnya.
     */
    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                $this->sheetName = $event->getSheet()->getDelegate()->getTitle();
                $this->currentKelas = null;
                $this->resolvedKelas = null;
                $this->parsedNamaKelasRaw = null;

                // Muat DAFTAR KELAS VALID dari DB (tidak pernah auto-create).
                // Tidak ada hardcoded regex "KELAS :" — pencocokan murni berbasis
                // daftar kelas DB (tingkat + nama_kelas, dan nama_kelas saja).
                $this->classList = [];
                foreach (Kelas::select('id', 'tingkat', 'nama_kelas')->get() as $k) {
                    $this->classList[] = [
                        'id' => $k->id,
                        'full' => strtoupper(trim(($k->tingkat ?? '').' '.$k->nama_kelas)),
                        'name' => strtoupper(trim($k->nama_kelas)),
                    ];
                }
            },
        ];
    }

    // ─── Entry Point ──────────────────────────────────────────────────────────

    /**
     * Proses satu sheet. Setiap sheet berisi BEBERAPA tabel kelas yang
     * ditumpuk vertikal, dipisahkan oleh header blok seperti:
     *   "KELAS : X TKJ 1", "Kelas X DKV 2", "X AKL 1", dst.
     *
     * Pencocokan header TIDAK memakai regex/hardcoded "KELAS :" sama sekali —
     * murni membandingkan teks rata baris dengan daftar kelas VALID dari DB.
     * Jika teks baris mengandung "full" (tingkat+nama_kelas) ATAU "name"
     * (nama_kelas) milik kelas yang ada di DB, baris tsb = header → set
     * $currentKelas dan lompati (tidak diproses sebagai data siswa).
     *
     * Alur:
     *   1. Scan setap baris (non-data) untuk header kelas → set $currentKelas.
     *   2. Baris dengan No Urut valid (Kolom A) + $currentKelas ada →
     *      siswa ditetapkan PERSIS ke kelas tersebut. TIDAK pernah di-skip
     *      hanya karena kelas tidak terdeteksi di baris itu sendiri.
     *   3. Baris data tanpa kelas aktif → log/dump teks baris utk diagnosis.
     */
    public function collection(Collection $rows): void
    {
        foreach ($rows as $rowIndex => $row) {
            $cells = array_values($row->toArray());
            $flatText = $this->normalizeCellText(implode(' ', array_filter(array_map(
                fn ($v) => trim((string) $v),
                $cells
            ))));

            // ── 0) SKIP BARIS KOSONG TOTAL (gap antar tabel kelas) ──────────
            if ($flatText === '') {
                $this->skippedCount++;

                continue;
            }

            $noUrut = $cells[0] ?? null;
            $isDataRow = $this->isValidNoUrut($noUrut);

            // ── 1) HEADER DETECTION (murni DB-driven, TANPA regex) ───────────
            // GUARD: hanya baris yang BUKAN data siswa (Kolom A bukan nomor) yang
            // bisa jadi header — mencegah nama siswa berisi teks kelas ("Andi TKJ 1
            // Pratama") dianggap header.
            if (! $isDataRow) {
                $matchedKelas = $this->matchKelasByRowText($flatText);
                if ($matchedKelas !== null) {
                    $this->currentKelas = $matchedKelas;
                    $this->resolvedKelas = $matchedKelas;
                    $this->parsedNamaKelasRaw = $matchedKelas->nama_lengkap;

                    continue; // baris header kelas ("KELAS: X TKJ 1") — dipakai utk context
                }
            }

            // ── 2) SKIP METADATA / JUDUL KOLOM ───────────────────────────────
            // Baris seperti ["NO", "NISN", "NIS", "NAMA SISWA", ...] atau teks
            // berawalan "KELAS:" yang tidak cocok dgn kelas DB.
            if (! $isDataRow && $this->isHeaderOrHeadingRow($flatText)) {
                $this->skippedCount++;

                continue;
            }

            // ── 3) STRICT VALIDATION KOLOM A (No Urut) ───────────────────────
            if (! $isDataRow) {
                $this->skippedCount++;

                continue;
            }

            // ── 4) BUTUH KELAS AKTIF ─────────────────────────────────────────
            // Baris sebelum header kelas pertama tidak bisa ditetapkan → log
            // teks barisnya supaya terlihat seperti apa sebenarnya di log/tinker.
            if ($this->currentKelas === null) {
                if ($this->fallbackIdKelas !== null) {
                    $this->currentKelas = Kelas::find($this->fallbackIdKelas);
                }

                if ($this->currentKelas === null) {
                    Log::warning('SiswaImport: baris tanpa kelas aktif (header tidak tertangkap)', [
                        'sheet' => $this->sheetName,
                        'rowIndex' => $rowIndex,
                        'rowText' => $flatText,
                    ]);

                    $this->skippedCount++;
                    $this->rowErrors[] = "Baris #{$rowIndex}: siswa tanpa kelas aktif — rowText='{$flatText}' — dilewati.";

                    continue;
                }
            }

            // ── 5) DYNAMIC COLUMN MAPPING ────────────────────────────────────
            // Struktur file berbeda-beda (ekspor grup: B=NISN, C=NIS, D=NAMA;
            // template klasik: B=NISN, C=NAMA, D=NIS). Deteksi per-baris:
            // NISN = sel 10 digit, NIS = angka selain No-urut & NISN, Nama =
            // sel teks terpanjang (lebih suka yang mengandung spasi).
            [$nisn, $nama, $nis] = $this->detectStudentColumns($cells);

            // ── Skip: nama wajib ada & valid (jangan pernah simpan baris kosong) ──
            if ($nama === '' || $this->isReservedToken($nama) || $this->isInvalidNama($nama)) {
                $this->skippedCount++;
                $this->rowErrors[] = "Baris #{$rowIndex}: '".($nama ?: $nisn)."' dilewati — nama tidak valid/kosong.";

                continue;
            }

            // ── Skip: NISN wajib numerik 10 digit ───────────────────────────
            if ($nisn === '' || ! ctype_digit($nisn) || strlen($nisn) !== 10) {
                $this->skippedCount++;
                $this->rowErrors[] = "Baris #{$rowIndex}: '".($nama ?: $nisn)."' dilewati — NISN tidak valid (harus 10 digit angka).";

                continue;
            }

            // ── Mapping gender (enum NOT NULL, L/P) — scan dinamis per baris ─
            $gender = 'L'; // default Laki-laki
            foreach ($cells as $cell) {
                $val = strtoupper(trim((string) $cell));
                if ($val === 'P' || $val === 'PEREMPUAN') {
                    $gender = 'P'; // Perempuan
                    break;
                }
                if ($val === 'L' || $val === 'LAKI-LAKI') {
                    $gender = 'L'; // Laki-laki
                    break;
                }
            }

            // ── Simpan / Update via updateOrCreate (match by NISN) ───────────
            try {
                Siswa::updateOrCreate(
                    ['nisn' => $nisn],
                    [
                        'nis' => ! empty($nis) ? $nis : null,
                        'nama' => $nama,
                        'id_kelas' => $this->currentKelas->id,
                        'id_jurusan' => $this->currentKelas->id_jurusan ?? null,
                        'jenis_kelamin' => $gender,
                        'status_siswa' => 'Aktif',
                    ]
                );

                $this->importedCount++;

            } catch (\Throwable $e) {
                $this->skippedCount++;
                $this->rowErrors[] = "Baris #{$rowIndex} (NISN: {$nisn}): ".$e->getMessage();
            }
        }
    }

    // ─── Kelas Resolution (STRICT — DB-only, TIDAK auto-create) ────────────────

    /**
     * Cocokkan teks rata satu baris terhadap daftar kelas VALID dari DB
     * ($this->classList). Tidak ada regex "KELAS :" — murni pencocokan teks.
     *
     * Urutan prioritas:
     *   1. "full"  = "X TKJ 1" (tingkat + nama_kelas) → paling spesifik.
     *   2. "name"  = "TKJ 1"   (nama_kelas saja), hindari tingkat X/XI/XII
     *      sendirian agar tidak bentrok dengan nama siswa.
     *
     * Baris mana pun yang mengandung representasi kelas (di sel mana pun)
     * dianggap header. Jika tidak ada kecocokan → null (bukan header).
     */
    protected function matchKelasByRowText(string $rowText): ?Kelas
    {
        if ($rowText === '') {
            return null;
        }

        // 1) Prioritas: nama lengkap tingkat + nama_kelas → "X TKJ 1", "XI AKL 2".
        foreach ($this->classList as $class) {
            if ($class['full'] !== '' && str_contains($rowText, $class['full'])) {
                return Kelas::find($class['id']);
            }
        }

        // 2) Fallback: nama_kelas saja → "TKJ 1", "DKV 2" (hindari tingkat sendirian).
        foreach ($this->classList as $class) {
            if (in_array($class['name'], self::TINGKAT_LIST, true)) {
                continue;
            }
            if ($class['name'] !== '' && str_contains($rowText, $class['name'])) {
                return Kelas::find($class['id']);
            }
        }

        return null;
    }

    /**
     * Normalisasi teks rata baris: uppercase, kolaps spasi berlebih,
     * abaikan titik dua/titik/koma sebagai pemisah sel.
     */
    protected function normalizeCellText(string $text): string
    {
        $text = (string) preg_replace('/\s+/', ' ', $text);
        $text = str_replace([':', '.', ';'], ' ', $text);

        return strtoupper(trim((string) preg_replace('/\s+/', ' ', $text)));
    }

    // ─── Normalisasi ──────────────────────────────────────────────────────────

    /**
     * Cek apakah Kolom A merupakan nomor urut valid (int antara 1-100).
     * Menangani int, float (1.0), dan string numeric. Nilai lain (teks header,
     * rumus "= 15", footer) → false.
     */
    protected function isValidNoUrut(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        if (is_int($value)) {
            return $value >= 1 && $value <= 100;
        }

        if (is_float($value)) {
            return $value >= 1 && $value <= 100 && fmod($value, 1.0) === 0.0;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '' || ! ctype_digit($trimmed)) {
                return false;
            }
            $int = (int) $trimmed;

            return $int >= 1 && $int <= 100;
        }

        return false;
    }

    /**
     * Cek apakah nama berisi data sampah (Excel formula / footer teks).
     * Skip jika diawali '=' atau mengandung kata kunci footer/statistik.
     */
    protected function isInvalidNama(string $nama): bool
    {
        if (str_starts_with($nama, '=')) {
            return true;
        }

        $blocked = ['Mata Pelajaran', 'Wali Kelas', 'Laki-laki', 'Pengajar', 'Keterangan'];
        foreach ($blocked as $term) {
            if (stripos($nama, $term) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Deteksi baris metadata/judul kolom yang HARUS dilewati (bukan data siswa):
     *   - Baris dengan teks berawalan "KELAS" (mis. "KELAS : X TKJ 1") yang tidak
     *     cocok dengan kelas DB (aman dilewati).
     *   - Baris judul kolom tabel (mengandung token unik "NISN", "NAMA SISWA",
     *     "JENIS KELAMIN").
     */
    protected function isHeaderOrHeadingRow(string $flatText): bool
    {
        $upper = strtoupper($flatText);
        if (str_starts_with($upper, 'KELAS')) {
            return true;
        }

        foreach (['NISN', 'NAMA SISWA', 'JENIS KELAMIN'] as $token) {
            if (str_contains($upper, $token)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Deteksi kolom siswa secara DINAMIS per baris — tidak bergantung pada
     * urutan kolom file (ekspor grup: B=NISN C=NIS D=NAMA; template klasik:
     * B=NISN C=NAMA D=NIS):
     *   - NISN : satu-satunya sel dengan tepat 10 digit angka.
     *   - NIS  : sel angka yang bukan No-urut (kolom pertama) & bukan NISN.
     *   - Nama : sel teks terpanjang, lebih suka yang mengandung spasi
     *     (nama lengkap) dan bukan token khusus/header.
     *
     * @return array{0: string, 1: string, 2: ?string} [nisn, nama, nis]
     */
    protected function detectStudentColumns(array $cells): array
    {
        $nisn = null;
        $nisCandidates = [];
        $nameCells = [];

        foreach ($cells as $index => $raw) {
            $trimmed = trim((string) $raw);
            if ($trimmed === '') {
                continue;
            }

            if (preg_match('/^\d{10}$/', $trimmed) && $nisn === null) {
                $nisn = $trimmed;

                continue;
            }

            if (is_numeric($trimmed)) {
                $nisCandidates[$index] = $trimmed;

                continue;
            }

            if ($this->looksLikeName($trimmed)) {
                $nameCells[$index] = $trimmed;
            }
        }

        // Nama: utamakan sel teks yang mengandung spasi, lalu yang terpanjang.
        $withSpace = array_filter($nameCells, fn ($n) => str_contains($n, ' '));
        $pool = $withSpace !== [] ? $withSpace : $nameCells;
        $nama = '';
        foreach ($pool as $candidate) {
            if (strlen($candidate) > strlen($nama)) {
                $nama = $candidate;
            }
        }

        // NIS: angka selain No-urut (kolom pertama) dan selain NISN.
        unset($nisCandidates[0]);
        $nis = $nisCandidates === [] ? null : preg_replace('/[^0-9]/', '', (string) reset($nisCandidates));

        return [$nisn ?? '', $nama, $nis];
    }

    /** Kandidat nama = sel teks normal (bukan angka, bukan formula, bukan token khusus). */
    protected function looksLikeName(string $value): bool
    {
        return $value !== ''
            && ! str_starts_with($value, '=')
            && ! is_numeric($value)
            && ! $this->isReservedToken($value);
    }

    /** Token khusus/header yang tidak boleh dianggap sebagai nama siswa. */
    protected function isReservedToken(string $value): bool
    {
        $upper = strtoupper(trim($value));

        return in_array($upper, [
            'NO', 'NISN', 'NIS', 'NAMA SISWA', 'KELAS', 'JENIS KELAMIN', 'STATUS',
            'LAKI-LAKI', 'PEREMPUAN', 'L', 'P', 'AKTIF', 'NON-AKTIF', 'KET',
        ], true);
    }

    // ─── Getters ──────────────────────────────────────────────────────────────

    /** ID kelas yang aktif untuk baris terakhir. */
    public function getResolvedIdKelas(): ?int
    {
        return $this->resolvedKelas?->id;
    }

    /** Nama kelas yang dipakai untuk blok terakhir. */
    public function getParsedNamaKelasRaw(): ?string
    {
        return $this->parsedNamaKelasRaw;
    }
}
