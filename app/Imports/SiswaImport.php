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

    /** Kelas AKTIF dari header kelas terakhir (mis. "XI DKV 1"). */
    protected ?Kelas $currentKelas = null;

    /** Kelas yang baru saja di-resolve untuk blok terakhir. */
    protected ?Kelas $resolvedKelas = null;

    /** Nama kelas (raw) dari blok terakhir. */
    protected ?string $parsedNamaKelasRaw = null;

    /** Indeks kolom yang terdeteksi untuk header dinamis. */
    protected ?int $nisCol = null;

    protected ?int $nisnCol = null;

    protected ?int $namaCol = null;

    protected ?int $jkCol = null;

    protected ?int $statusCol = null;

    public int $importedCount = 0;

    public int $skippedCount = 0;

    public int $newKelasCount = 0;

    public array $rowErrors = [];

    /**
     * Posisi kolom terdeteksi dari BARIS HEADER tabel.
     * Dipakai untuk kompatibilitas dengan helper lama.
     */
    protected ?array $headerColumns = null;

    /**
     * Daftar lengkap representasi kelas VALID dari DB, dipakai untuk mencocokkan
     * header kelas. Setiap entry: ['id', 'full' => "X TKJ 1", 'name' => "TKJ 1"].
     */
    protected array $classList = [];

    // ─── Konstruktor ──────────────────────────────────────────────────────────

    public function __construct(?int $fallbackIdKelas = null)
    {
        $this->fallbackIdKelas = $fallbackIdKelas;
    }

    /**
     * Reset state per-sheet. Dengan ToCollection satu instance dipakai untuk
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
                $this->nisCol = null;
                $this->nisnCol = null;
                $this->namaCol = null;
                $this->jkCol = null;
                $this->statusCol = null;
                $this->headerColumns = null;

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
     * Proses sheet menggunakan inspeksi baris dinamis.
     *
     * Alur:
     *   1. Deteksi baris judul kolom (mengandung NISN / NAMA) → petakan kolom.
     *   2. Deteksi header kelas "KELAS: ..." / cell bertuliskan "Kelas" →
     *      set $currentKelas untuk blok berikutnya.
     *   3. Baris data siswa: cari kelas aktif → baca kolom → simpan/update ke DB.
     */
    public function collection(Collection $rows): void
    {
        foreach ($rows as $rowIndex => $row) {
            $cells = array_values($row->toArray());
            $flatText = $this->normalizeCellText(implode(' ', array_filter(array_map(
                fn ($v) => trim((string) $v),
                $cells
            ))));

            // 0) Baris kosong total (gap antar tabel kelas).
            if ($flatText === '') {
                $this->skippedCount++;

                continue;
            }

            // 1) Baris judul kolom (mengandung "NISN" / "NAMA").
            if ($this->isHeaderRow($cells)) {
                $this->trackHeaderColumns($cells);
                $this->skippedCount++;

                continue;
            }

            // 2) Header kelas ("KELAS: X TKJ 1", "Kelas XI DKV 1", cell berisi "Kelas",
            //    atau teks kelas polos seperti "X TKJ 1"). Khusus baris yang BELUM
            //    jelas-jelas data siswa agar nama siswa berisi teks kelas (mis.
            //    "Andi TKJ 1 Pratama") tidak pernah salah dianggap sebagai header.
            if (! $this->isDefinitelyDataRow($cells)) {
                $matchedKelas = $this->resolveKelasFromRow($cells, $flatText);
                if ($matchedKelas !== null) {
                    $this->currentKelas = $matchedKelas;
                    $this->resolvedKelas = $matchedKelas;
                    $this->parsedNamaKelasRaw = $matchedKelas->nama_lengkap;

                    continue;
                }
            }

            // 3) Hanya baris data siswa yang diproses dari titik ini.
            if (! $this->isLikelyDataRow($cells)) {
                $this->skippedCount++;

                continue;
            }

            // 4) Butuh kelas aktif (header kelas atau fallback dropdown UI).
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

            // 5) Baca kolom siswa dengan preferensi header yang sudah terdeteksi.
            [$nisn, $nama, $nis] = $this->extractStudentColumns($cells);

            // Nama wajib ada & valid — NAMA kosong = alasan utama skip baris.
            if ($nama === '' || $this->isReservedToken($nama) || $this->isInvalidNama($nama)) {
                $this->skippedCount++;
                $this->rowErrors[] = "Baris #{$rowIndex}: '".($nama ?: $nisn)."' dilewati — nama tidak valid/kosong.";

                continue;
            }

            // NISN dijadikan kunci unik updateOrCreate — wajib punya digit.
            // NISS boleh kosong (kelas 10) → disimpan null, bukan alasan skip.
            $nisn = $this->cleanNisn($nisn);
            if ($nisn === null) {
                $this->skippedCount++;
                $this->rowErrors[] = "Baris #{$rowIndex}: '{$nama}' dilewati — NISN kosong/tidak bernomor.";

                continue;
            }

            // NIS/NISS diambil UTUH sebagai string ("24435 / 0001 . 0411"),
            // kosong → null.
            $nisClean = $nis !== null && trim((string) $nis) !== ''
                ? trim((string) $nis)
                : null;

            $gender = $this->detectGender($cells);
            $status = $this->detectStatus($cells);

            try {
                Siswa::updateOrCreate(
                    ['nisn' => $nisn],
                    [
                        'nis' => $nisClean,
                        'nama' => $nama,
                        'id_kelas' => $this->currentKelas->id,
                        'id_jurusan' => $this->currentKelas->id_jurusan ?? null,
                        'jenis_kelamin' => $gender,
                        'status_siswa' => $status,
                    ]
                );

                $this->importedCount++;
            } catch (\Throwable $e) {
                $this->skippedCount++;
                $this->rowErrors[] = "Baris #{$rowIndex} (NISN: {$nisn}): ".$e->getMessage();
            }
        }
    }

    // ─── Deteksi Header & Kolom Dinamis ─────────────────────────────────────

    /**
     * Apakah baris saat ini merupakan BARIS JUDUL KOLOM (header tabel).
     * Terdeteksi bila ada sel yang memuat kata "NISN" atau "NAMA"
     * (case-insensitive, spasi diabaikan sehingga "NISN" / "N I S N" ketangkap).
     */
    protected function isHeaderRow(array $cells): bool
    {
        // Baris data siswa (kolom A berisi nomor urut ATAU ada NISN 10 digit)
        // tidak mungkin judul kolom — mencegah nama seperti "NISN Aneh" / "Tanpa
        // NISN" terdeteksi sebagai baris header.
        if ($this->isDefinitelyDataRow($cells)) {
            return false;
        }

        foreach ($cells as $raw) {
            $normalized = $this->normalizeHeaderToken((string) $raw);
            if ($normalized === '') {
                continue;
            }

            if (str_contains($normalized, 'NISN') || str_contains($normalized, 'NAMA')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tangkap indeks kolom header untuk field utama secara dinamis:
     *   NISN, NAMA, NISS (atau "N I S S" / "NIS"), L/P (Gender), STATUS.
     */
    protected function trackHeaderColumns(array $cells): void
    {
        $this->nisCol = null;
        $this->nisnCol = null;
        $this->namaCol = null;
        $this->jkCol = null;
        $this->statusCol = null;

        foreach ($cells as $index => $raw) {
            $normalized = $this->normalizeHeaderToken((string) $raw);
            if ($normalized === '') {
                continue;
            }

            if ($this->nisnCol === null && str_contains($normalized, 'NISN')) {
                $this->nisnCol = $index;

                continue;
            }

            if ($this->nisCol === null && str_contains($normalized, 'NISS')) {
                $this->nisCol = $index;

                continue;
            }

            if ($this->nisCol === null && str_contains($normalized, 'NIS') && ! str_contains($normalized, 'NISN')) {
                $this->nisCol = $index;

                continue;
            }

            if ($this->namaCol === null && str_contains($normalized, 'NAMA')) {
                $this->namaCol = $index;

                continue;
            }

            if ($this->jkCol === null && (str_contains($normalized, 'LP') || str_contains($normalized, 'GENDER') || str_contains($normalized, 'JENIS'))) {
                $this->jkCol = $index;

                continue;
            }

            if ($this->statusCol === null && str_contains($normalized, 'STATUS')) {
                $this->statusCol = $index;
            }
        }

        $this->headerColumns = [
            'no' => null,
            'nisn' => $this->nisnCol,
            'nis' => $this->nisCol,
            'nama' => $this->namaCol,
            'jenis_kelamin' => $this->jkCol,
            'status' => $this->statusCol,
        ];
    }

    /**
     * Baca baris sebagai data siswa dengan preferensi header yang sudah terdeteksi.
     * NIS/NISS diambil UTUH (tidak di-strip), karena bisa berupa "24435 / 0001 . 0411".
     */
    protected function extractStudentColumns(array $cells): array
    {
        $nisn = $this->nisnCol !== null
            ? trim((string) ($cells[$this->nisnCol] ?? ''))
            : ($this->detectNisnFromCells($cells) ?? '');

        $nama = $this->namaCol !== null
            ? trim((string) ($cells[$this->namaCol] ?? ''))
            : $this->detectNamaFromCells($cells);

        $nis = $this->nisCol !== null
            ? $this->cleanNumericText($cells[$this->nisCol] ?? null)
            : $this->detectNisFromCells($cells, $nisn !== '' ? $nisn : null);

        return [$nisn, $nama, $nis];
    }

    // ─── Deteksi Baris (dinamis di seluruh sheet) ─────────────────────────────

    /**
     * TRUE bila baris PASTI data siswa sehingga TIDAK boleh dicoba sebagai
     * header kelas: kolom pertama nomor urut valid ATAU berisi NISN 10 digit.
     */
    protected function isDefinitelyDataRow(array $cells): bool
    {
        if ($this->isValidNoUrut($cells[0] ?? null)) {
            return true;
        }

        return $this->rowContainsNisn($cells);
    }

    /**
     * TRUE bila baris memiliki pola data siswa (nomor urut, NISN, atau nama).
     */
    protected function isLikelyDataRow(array $cells): bool
    {
        $firstCell = trim((string) ($cells[0] ?? ''));
        if ($firstCell !== '' && preg_match('/^KELAS\b/i', $firstCell)) {
            return false;
        }

        if ($this->isValidNoUrut($cells[0] ?? null)) {
            return true;
        }

        if ($this->rowContainsNisn($cells)) {
            return true;
        }

        return $this->rowContainsLikelyStudentName($cells);
    }

    /** TRUE bila salah satu sel berisi angka tepat 10 digit (kandidat NISN). */
    protected function rowContainsNisn(array $cells): bool
    {
        foreach ($cells as $raw) {
            if ($raw === null || $raw === '') {
                continue;
            }

            if (preg_match('/^\d{10}$/', trim((string) $raw))) {
                return true;
            }
        }

        return false;
    }

    /** TRUE bila baris berisi kandidat nama siswa, meski NIS/NISN tidak hadir. */
    protected function rowContainsLikelyStudentName(array $cells): bool
    {
        foreach ($cells as $raw) {
            $trimmed = trim((string) $raw);
            if ($trimmed === '' || str_starts_with($trimmed, '=')) {
                continue;
            }

            if (preg_match('/^KELAS\b/i', $trimmed)) {
                continue;
            }

            if ($this->isReservedToken($trimmed)) {
                continue;
            }

            if (is_numeric($trimmed)) {
                continue;
            }

            if (preg_match('/[A-Za-z]/', $trimmed) && preg_match('/\s/', $trimmed)) {
                return true;
            }
        }

        return false;
    }

    // ─── Resolve Kelas ────────────────────────────────────────────────────────

    /**
     * Resolve kelas dari baris non-data. Prioritaskan ekstraksi teks setelah
     * "KELAS:" / "Kelas :" / cell bertuliskan "Kelas" (mis. "Kelas : XI DKV 1"),
     * lalu fallback ke pencocokan teks baris terhadap daftar kelas DB.
     */
    protected function resolveKelasFromRow(array $cells, string $flatText): ?Kelas
    {
        $classText = $this->extractClassTextFromRow($cells);
        $matched = $classText !== '' ? $this->resolveKelasByName($classText) : null;

        if ($matched === null) {
            $matched = $this->matchKelasByRowText($flatText);
        }

        if ($matched === null && preg_match('/\bKELAS\b/i', $flatText)) {
            $matched = $this->resolveKelasByName($this->extractKelasNameFromFlatText($flatText));
        }

        return $matched;
    }

    // ─── Kelas Resolution (STRICT — DB-only, TIDAK auto-create) ────────────────

    /**
     * Ekstrak teks nama kelas dari baris header kelas. Mendukung:
     *   ["KELAS: XI AK 1"]            → "XI AK 1"
     *   ["Kelas : X AK 1", "", ...]   → "X AK 1"
     *   ["", "KELAS", "XI AKL 2"]     → "XI AKL 2"  (nilai di cell sebelah)
     *   ["Kelas X DKV 2"]             → "X DKV 2"   (gabungan teks satu cell)
     *   ["X TKJ 1"]                   → ""          (bukan baris KELAS)
     */
    protected function extractClassTextFromRow(array $cells): string
    {
        $rowText = implode(' ', array_map(
            fn ($v) => trim((string) $v),
            $cells
        ));

        if (preg_match('/KELAS\s*[.:]?\s*(.+)$/i', $rowText, $m)) {
            $classText = $this->normalizeCellText($m[1]);
            if ($classText !== '' && $classText !== 'KELAS') {
                return $classText;
            }
        }

        foreach ($cells as $index => $value) {
            $text = strtoupper(trim((string) $value));
            if ($text === '') {
                continue;
            }

            if (! preg_match('/\bKELAS\b/i', $text)) {
                continue;
            }

            // Ambil nilai kelas dari cell di SEBELAHNYA (pertama yang tidak kosong).
            for ($j = $index + 1; $j < count($cells); $j++) {
                $candidate = trim((string) ($cells[$j] ?? ''));
                if ($candidate === '') {
                    continue;
                }

                $candidate = $this->normalizeCellText($candidate);
                if ($candidate !== '' && $candidate !== 'KELAS') {
                    return $candidate;
                }
            }

            // Tidak ada cell lanjutan → ambil teks setelah "KELAS" dalam sel yang sama
            // (mis. ": XI RPL 1" → "XI RPL 1").
            if (preg_match('/KELAS\s*[.:]?\s*(.+)$/i', $text, $m2)) {
                $inner = $this->normalizeCellText($m2[1]);
                if ($inner !== '' && $inner !== 'KELAS') {
                    return $inner;
                }
            }
        }

        return '';
    }

    /**
     * Resolve nama kelas terhadap daftar kelas DB.
     */
    protected function resolveKelasByName(string $classText): ?Kelas
    {
        if ($classText === '') {
            return null;
        }

        foreach ($this->classList as $class) {
            if ($class['full'] !== '' && $class['full'] === $classText) {
                return Kelas::find($class['id']);
            }
        }

        foreach ($this->classList as $class) {
            if ($class['name'] !== '' && $class['name'] === $classText) {
                return Kelas::find($class['id']);
            }
        }

        foreach ($this->classList as $class) {
            if ($class['full'] !== '' && str_contains($classText, $class['full'])) {
                return Kelas::find($class['id']);
            }
        }

        foreach ($this->classList as $class) {
            if ($class['name'] !== '' && str_contains($classText, $class['name'])) {
                return Kelas::find($class['id']);
            }
        }

        return null;
    }

    /**
     * Cocokkan teks rata satu baris terhadap daftar kelas VALID dari DB.
     */
    protected function matchKelasByRowText(string $rowText): ?Kelas
    {
        if ($rowText === '') {
            return null;
        }

        if (str_starts_with($rowText, 'KELAS')) {
            $rest = trim(substr($rowText, strlen('KELAS')));
            if ($rest !== '') {
                foreach ($this->classList as $class) {
                    $full = strtoupper(trim($class['full']));
                    $name = strtoupper(trim($class['name']));
                    if ($full !== '' && str_contains($rest, $full)) {
                        return Kelas::find($class['id']);
                    }
                    if ($full === $rest || $name === $rest) {
                        return Kelas::find($class['id']);
                    }
                }
            }
        }

        foreach ($this->classList as $class) {
            if ($class['full'] !== '' && str_contains($rowText, $class['full'])) {
                return Kelas::find($class['id']);
            }
        }

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

    protected function normalizeHeaderToken(string $text): string
    {
        $text = strtoupper(trim((string) $text));
        $text = preg_replace('/\s+/', '', $text);

        return (string) str_replace(['/', '-'], '', $text);
    }

    protected function extractKelasNameFromFlatText(string $flatText): string
    {
        if (preg_match('/\bKELAS\b\s*:?(.*)$/i', $flatText, $m)) {
            return $this->normalizeCellText($m[1]);
        }

        if (preg_match('/\bKELAS\b\s+(.*)$/i', $flatText, $m)) {
            return $this->normalizeCellText($m[1]);
        }

        return '';
    }

    // ─── Normalisasi & Validasi ───────────────────────────────────────────────

    /**
     * Cek apakah Kolom A merupakan nomor urut valid (int antara 1-100).
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
     * Cek apakah nama berisi data sampah (formula Excel / baris judul / footer).
     */
    protected function isInvalidNama(string $nama): bool
    {
        if (str_starts_with($nama, '=')) {
            return true;
        }

        $blocked = [
            'Mata Pelajaran', 'Wali Kelas', 'Laki-laki', 'Pengajar', 'Keterangan',
            'Daftar Hadir', 'Peserta Didik', 'Mengetahui', 'Kepala Sekolah',
            'Jumlah', 'Total', 'Tanda Tangan', 'Guru Piket',
        ];
        foreach ($blocked as $term) {
            if (stripos($nama, $term) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Bersihkan NISN menjadi angka murni (maks 20 digit). Kosong → null.
     */
    protected function cleanNisn(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = preg_replace('/[^0-9]/', '', trim((string) $value));
        $clean = (string) substr($clean, 0, 20);

        return $clean === '' ? null : $clean;
    }

    // ─── Ekstraksi & Fallback Kolom Siswa ─────────────────────────────────────

    /**
     * Deteksi NISN dengan fallback dinamis bila kolom header tidak tersedia.
     */
    protected function detectNisnFromCells(array $cells): ?string
    {
        foreach ($cells as $raw) {
            $trimmed = trim((string) $raw);
            if ($trimmed === '') {
                continue;
            }

            if (preg_match('/^\d{10}$/', $trimmed)) {
                return $trimmed;
            }
        }

        $numericCandidates = [];
        foreach ($cells as $index => $raw) {
            $trimmed = trim((string) $raw);
            if ($trimmed === '' || $index === 0) {
                continue;
            }

            if (ctype_digit($trimmed)) {
                $numericCandidates[] = $trimmed;
            }
        }

        if ($numericCandidates === []) {
            return null;
        }

        usort($numericCandidates, fn ($a, $b) => strlen((string) $b) <=> strlen((string) $a));

        return (string) $numericCandidates[0];
    }

    /**
     * Deteksi nama siswa dengan fallback dinamis bila kolom header tidak tersedia.
     */
    protected function detectNamaFromCells(array $cells): string
    {
        $nameCells = [];

        foreach ($cells as $raw) {
            $trimmed = trim((string) $raw);
            if ($trimmed === '') {
                continue;
            }

            if ($this->looksLikeName($trimmed)) {
                $nameCells[] = $trimmed;
            }
        }

        $withSpace = array_filter($nameCells, fn ($n) => str_contains($n, ' '));
        $pool = $withSpace !== [] ? $withSpace : $nameCells;

        $nama = '';
        foreach ($pool as $candidate) {
            if (strlen($candidate) > strlen($nama)) {
                $nama = $candidate;
            }
        }

        return $nama;
    }

    /**
     * Deteksi NIS/NISS dengan fallback dinamis bila kolom header tidak tersedia.
     * Sel tempat NISN berada dikecualikan agar NIS tidak tertukar — nilai NIS/NISS
     * diambil UTUH sebagai string.
     */
    protected function detectNisFromCells(array $cells, ?string $nisn = null): ?string
    {
        $numericCandidates = [];

        foreach ($cells as $index => $raw) {
            $trimmed = trim((string) $raw);
            if ($trimmed === '') {
                continue;
            }

            if ($index === 0 && $this->isValidNoUrut($raw)) {
                continue;
            }

            if ($nisn !== null && $this->cleanNumericText($raw) === $nisn) {
                continue;
            }

            if (preg_match('/^\d{10}$/', $trimmed)) {
                continue;
            }

            if (ctype_digit($trimmed) || preg_match('/\d/', $trimmed)) {
                $numericCandidates[$index] = $trimmed;
            }
        }

        if ($numericCandidates === []) {
            return null;
        }

        ksort($numericCandidates);

        return $this->cleanNumericText(reset($numericCandidates));
    }

    /**
     * Normalisasi nilai NIS/NISS menjadi string bersih (trim spasi, kolaps
     * spasi berlebih). Nilai kosong => null.
     */
    protected function cleanNumericText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        return (string) preg_replace('/\s+/', ' ', $text);
    }

    /**
     * Deteksi jenis kelamin (L/P). Prioritas kolom baku "JENIS KELAMIN"/
     * "JENIS KELAS"/"L/P" bila baris judul terekam; tanpanya lakukan scan
     * dinamis di seluruh sel baris. Nilai tak dikenal → default 'L'.
     */
    protected function detectGender(array $cells): string
    {
        if ($this->headerColumns !== null && ($this->headerColumns['jenis_kelamin'] ?? null) !== null) {
            $idx = $this->headerColumns['jenis_kelamin'];
            $val = strtoupper(trim((string) ($cells[$idx] ?? '')));

            if ($val === 'P' || $val === 'PEREMPUAN' || $val === 'WANITA') {
                return 'P';
            }

            return 'L';
        }

        foreach ($cells as $cell) {
            $val = strtoupper(trim((string) $cell));
            if ($val === 'P' || $val === 'PEREMPUAN') {
                return 'P';
            }
        }

        return 'L';
    }

    /**
     * Deteksi status siswa. Baca kolom "STATUS" bila header terdeteksi;
     * bila kosong / kolom tidak ada → default 'Aktif'.
     */
    protected function detectStatus(array $cells): string
    {
        if ($this->headerColumns !== null && ($this->headerColumns['status'] ?? null) !== null) {
            $idx = $this->headerColumns['status'];
            $val = strtoupper(trim((string) ($cells[$idx] ?? '')));

            if (in_array($val, ['NONAKTIF', 'NON-AKTIF', 'TIDAK AKTIF', 'NON AKTIF'], true)) {
                return 'Nonaktif';
            }

            if ($val === 'AKTIF') {
                return 'Aktif';
            }
        }

        return 'Aktif';
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
            'NO', 'NISN', 'NIS', 'NISS', 'NAMA SISWA', 'NAMA', 'KELAS', 'JENIS KELAMIN',
            'JENIS KELAS', 'L/P', 'STATUS', 'STATUS SISWA',
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
