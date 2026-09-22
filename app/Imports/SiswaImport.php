<?php

namespace App\Imports;

use App\Imports\Exceptions\KelasNotFoundDuringImport;
use App\Models\Kelas;
use App\Models\Scopes\TestingDataScope;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Status testing di-tangkap SEKALI saat konstruksi: TRUE bila import sedang
     * dalam Mode QA/IT / Impersonation, sehingga setiap record baru siswa
     * tersimpan is_testing_data = 1 (bukan 0).
     */
    protected bool $isTesting = false;

    // ─── Konstruktor ──────────────────────────────────────────────────────────

    public function __construct(?int $fallbackIdKelas = null, ?bool $isTestingData = null)
    {
        $this->fallbackIdKelas = $fallbackIdKelas;

        // Konteks testing di-tangkap SEKALI DI KONSTRUKTOR (sebelum loop import
        // berjalan). Bila pemanggil mengirimkan parameter eksplisit $isTestingData,
        // nilai itu yang dipakai; jika tidak, diambil dari session/user login utama:
        //   session('is_testing_mode') || auth()->user()?->isTestingUser() || session()->has('impersonate_role')
        $this->isTesting = $isTestingData
            ?? (bool) (session('is_testing_mode')
                || auth()->user()?->isTestingUser()
                || session()->has('impersonate_role'));

        // TEMPORARY (diagnosa): verifikasi konteks di server produksi — hapus
        // setelah masalah is_testing_data=0 dipastikan beres.
        Log::info('SiswaImport context', [
            'user_id' => auth()->id(),
            'role' => auth()->user()?->role,
            'isTestingUser' => auth()->user()?->isTestingUser(),
            'is_testing_mode' => session('is_testing_mode'),
            'impersonate_role' => session('impersonate_role'),
            'active_role' => session('active_role'),
            'fallbackIdKelas' => $fallbackIdKelas,
            'isTesting' => $this->isTesting,
        ]);
    }

    // ─── Konteks Data (is_testing_data) ────────────────────────────────────────

    /**
     * Apakah import saat ini menarget partisi data TESTING (is_testing_data = 1).
     *
     * Selaras dengan isolasi data global aplikasi: Petugas IT / QA Tester
     * (User::isTestingUser() TRUE — termasuk saat Switch View As / impersonate)
     * SELALU menulis ke partisi testing. Session 'is_testing_mode' maupun session
     * 'impersonate_role' (layer impersonasi lain) juga memaksa partisi testing.
     * Hanya akun non-IT tanpa session testing yang menulis ke partisi real.
     */
    public static function isImportTestingContext(?User $user = null): bool
    {
        $user = $user ?? auth()->user();

        if ($user instanceof User && $user->isTestingUser()) {
            return true;
        }

        return (bool) session('is_testing_mode') || session()->has('impersonate_role');
    }

    /**
     * Partisi data yang menjadi target import saat ini (1 = testing, 0 = real).
     * Nilai didapat dari status yang ditangkap DI KONSTRUKTOR (bukan dievaluasi
     * per-baris), agar deterministik untuk seluruh baris dalam satu import.
     */
    public function targetIsTestingData(): bool
    {
        return $this->isTesting;
    }

    /**
     * Query Kelas untuk mencocokkan nama/header kelas & fallback id_kelas.
     * Global scope TestingDataScope DIMATIKAN (agar tidak memblokir pencarian
     * kelas saat Switch View), namun partisi target didahulukan — partisi lain
     * tetap diikutsertakan sebagai fallback. Dengan begitu Petugas IT / QA yang
     * mengimpor file berisi kelas REAL pun tetap ter-resolve; baris siswa tetap
     * ditulis ke partisi testing (konteks impor), kelas tujuannya boleh berasal
     * dari partisi mana pun.
     */
    protected function kelasQuery(): Builder
    {
        $target = $this->targetIsTestingData() ? 1 : 0;

        return Kelas::query()
            ->withoutGlobalScope(TestingDataScope::class)
            ->orderByRaw('CASE WHEN is_testing_data = ? THEN 0 ELSE 1 END', [$target])
            ->orderBy('id');
    }

    /** Cari kelas by ID pada partisi import aktif (tanpa global scope). */
    protected function findKelasById(int $id): ?Kelas
    {
        return $this->kelasQuery()->find($id);
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
                foreach ($this->kelasQuery()->select('id', 'tingkat', 'nama_kelas')->get() as $k) {
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

                // ── STRICT VALIDATION ──────────────────────────────────────────────
                // Header kelas yang tertulis pada file (mis. "KELAS: XI DKV 9",
                // "Kelas XI RPL 7", atau nama kelas polos "X TKJ 8") TIDAK ditemukan
                // di Data Master Kelas → batalkan SELURUH import. Transaksi DB di
                // controller di-rollback sehingga tidak ada data siswa parsial.
                if ($this->isPlausibleKelasHeader($flatText)) {
                    $this->abortKelasNotRegistered($this->extractKelasLabel($flatText));
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
                    $this->currentKelas = $this->findKelasById($this->fallbackIdKelas);
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
                $attributes = [
                    'nis' => $nisClean,
                    'nama' => $nama,
                    'id_kelas' => $this->currentKelas->id,
                    'id_jurusan' => $this->currentKelas->id_jurusan ?? null,
                    'jenis_kelamin' => $gender,
                    'status_siswa' => $status,
                ];

                // NISN unik GLOBAL — cari lintas partisi (real/testing) dan soft-delete
                // agar tidak menabrak unique index siswa.nisn / siswa.nis.
                // Kebijakan penanganan NISN (Conflict Skip):
                //   1) NISN TIDAK ditemukan        → create, is_testing_data = konteks impor.
                //   2) NISN ada di TESTING (1)     → update di tempat, tetap testing.
                //   3) NISN ada di REAL (0)        → SKIP baris (jangan di-update,
                //                                     jangan di-flip) + warning.
                $siswa = Siswa::withTrashed()
                    ->withoutGlobalScope(TestingDataScope::class)
                    ->where('nisn', $nisn)
                    ->first();

                if ($siswa === null) {
                    // nis juga kolom UNIK global. Bila nilainya sudah dipakai
                    // baris lain (NISN berbeda = siswa berbeda), kosongkan nis
                    // agar insert tidak menabrak unique constraint siswa.nis.
                    if ($nisClean !== null) {
                        $nisTakenByOther = Siswa::withTrashed()
                            ->withoutGlobalScope(TestingDataScope::class)
                            ->where('nis', $nisClean)
                            ->where('nisn', '!=', $nisn)
                            ->exists();

                        if ($nisTakenByOther) {
                            $attributes['nis'] = null;
                        }
                    }

                    $siswa = new Siswa;
                    $siswa->fill($attributes + ['nisn' => $nisn]);
                    // is_testing_data DI-SET LANGSUNG (bukan lewat fill) —
                    // dijamin ikut ter-insert: QA/IT/TESTING → 1, non-IT → 0.
                    $siswa->is_testing_data = $this->isTesting ? 1 : 0;
                    $siswa->save();
                } elseif ((int) $siswa->is_testing_data === 1) {
                    // NISN sudah ada di partisi TESTING → update di tempat,
                    // partisi DI-PERTAHANKAN testing (1), apapun konteks impor.
                    if ($siswa->trashed()) {
                        $siswa->restore();
                    }
                    $siswa->fill($attributes);
                    $siswa->is_testing_data = 1;
                    $siswa->save();
                } else {
                    // NISN sudah ada di partisi REAL → CONFLICT SKIP:
                    // jangan di-update & jangan di-flip statusnya (data produksi aman).
                    $this->skippedCount++;
                    $this->rowErrors[] = "NISN {$nisn} dilewati karena sudah terdaftar sebagai Data Real";

                    continue;
                }

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

    // ─── Strict Validation: Kelas Wajib Terdaftar ─────────────────────────────

    /**
     * TRUE bila baris non-data memiliki bentuk header kelas yang JELAS:
     *   1) baris menyebut kata kunci "KELAS" (mis. "KELAS: XI AK 1") —
     *      kecuali "Wali Kelas" yang bukan deklarasi kelas; atau
     *   2) pola tingkat + nama kelas polos (format CSV), mis. "X TKJ 1".
     *
     * Baris yang tidak memenuhi pola ini tetap mengalir ke penanganan baris
     * data biasa (di-skip / dianggap siswa) — TIDAK memicu pembatalan import.
     */
    protected function isPlausibleKelasHeader(string $flatText): bool
    {
        if ($flatText === '') {
            return false;
        }

        // "WALI KELAS : BUDI SANTOSO" or "KOP SURAT TANPA KELAS" bukan deklarasi kelas — jangan batalkan.
        if (preg_match('/(WALI\s*KELAS|KOP\s*SURAT|TANPA\s*KELAS|DAFTAR\s*HADIR|REKAP\s*PRESENSI)/i', $flatText)) {
            return false;
        }

        // 1) Baris mengandung kata kunci "KELAS" DAN masih ada konten lain
        //    setelahnya (bukan kolom label "KELAS" yang berdiri sendiri).
        if (preg_match('/\bKELAS\b/i', $flatText)) {
            $rest = (string) preg_replace('/\bKELAS\b/i', '', $flatText);

            if (trim($rest) !== '') {
                return true;
            }
        }

        // 2) Pola tingkat + jurusan/nama kelas polos (format CSV/teks),
        //    mis. "X TKJ 1", "XI DKV 2", "XII AKL 3".
        if (preg_match('/^(X|XI|XII)\s+[A-Z][A-Z0-9 .\/-]+$/i', trim($flatText))) {
            return true;
        }

        return false;
    }

    /**
     * Ambil label kelas dari teks rata baris untuk pesan error: bagian setelah
     * kata kunci "KELAS" bila ada, selain itu teks polosnya (mis. "X TKJ 9").
     */
    protected function extractKelasLabel(string $flatText): string
    {
        if (preg_match('/\bKELAS\b\s*[.:]?\s*(.+)$/i', $flatText, $m)) {
            return strtoupper(trim((string) preg_replace('/\s+/', ' ', $m[1])));
        }

        return strtoupper(trim((string) preg_replace('/\s+/', ' ', $flatText)));
    }

    /**
     * Deteksi tingkat ("X" / "XI" / "XII") pada teks label kelas.
     */
    protected function extractTingkat(string $text): string
    {
        foreach (self::TINGKAT_LIST as $t) {
            if (preg_match('/\b'.preg_quote($t, '/').'\b/i', $text)) {
                return $t;
            }
        }

        return '';
    }

    /**
     * Nama kelas tanpa tingkat dari label kelas (mis. "XI DKV 9" → "DKV 9").
     */
    protected function extractKelasName(string $text): string
    {
        $text = strtoupper(trim($text));

        foreach (self::TINGKAT_LIST as $t) {
            $text = (string) preg_replace('/\b'.preg_quote($t, '/').'\b/i', '', $text, 1);
        }

        return trim((string) preg_replace('/\s+/', ' ', str_replace([':', '.', ';'], ' ', $text)));
    }

    /**
     * Lempar exception pembatalan import dengan pesan informatif berformat:
     * "Import Gagal! Kelas 'X' (Tingkat XI) belum terdaftar di Data Master Kelas. …"
     */
    protected function abortKelasNotRegistered(string $rawClassText): void
    {
        $nama    = $this->extractKelasName($rawClassText);
        $tingkat = $this->extractTingkat($rawClassText);

        $label   = $nama !== '' ? $nama : $rawClassText;
        $tingkat = $tingkat !== '' ? $tingkat : 'tidak terdeteksi';

        throw new KelasNotFoundDuringImport(
            "Import Gagal! Kelas '{$label}' (Tingkat {$tingkat}) belum terdaftar di Data Master Kelas. ".
            'Silakan tambahkan kelas tersebut terlebih dahulu di menu Data Master -> Data Kelas sebelum mengunggah file ini.'
        );
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
                return $this->findKelasById($class['id']);
            }
        }

        foreach ($this->classList as $class) {
            if ($class['name'] !== '' && $class['name'] === $classText) {
                return $this->findKelasById($class['id']);
            }
        }

        foreach ($this->classList as $class) {
            if ($class['full'] !== '' && str_contains($classText, $class['full'])) {
                return $this->findKelasById($class['id']);
            }
        }

        foreach ($this->classList as $class) {
            if ($class['name'] !== '' && str_contains($classText, $class['name'])) {
                return $this->findKelasById($class['id']);
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
                        return $this->findKelasById($class['id']);
                    }
                    if ($full === $rest || $name === $rest) {
                        return $this->findKelasById($class['id']);
                    }
                }
            }
        }

        foreach ($this->classList as $class) {
            if ($class['full'] !== '' && str_contains($rowText, $class['full'])) {
                return $this->findKelasById($class['id']);
            }
        }

        foreach ($this->classList as $class) {
            if (in_array($class['name'], self::TINGKAT_LIST, true)) {
                continue;
            }
            if ($class['name'] !== '' && str_contains($rowText, $class['name'])) {
                return $this->findKelasById($class['id']);
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
