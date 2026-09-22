<?php

namespace App\Imports;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Scopes\TestingDataScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Import Master Guru (xlsx / csv) dengan role mapping, sanitasi username & password default.
 *
 * Alur per baris (kolom: NIP, NAMA GURU, STATUS, opsional PERAN, WALI KELAS, NO HP):
 *   1. NIP Opsional (Nullable): Jika NIP kosong/spasi/dash, disimpan sebagai NULL.
 *   2. Pembersihan Gelar: Menghapus gelar akademik (S.Pd, M.Pd, Drs., Dr., Hj., dll) saat membuat username.
 *   3. Username Otomatis: Dibuat dari nama guru tersanitasi (lowercase, hapus karakter khusus, spasi -> titik).
 *   4. Password Default: Hash::make(USERNAME . '123') untuk akun baru.
 *   5. Peran "Kepsek" → baris DILEWATI (bukan bagian master guru).
 *   6. Peran "Wali Kelas" + kolom WALI KELAS terisi → dipetakan otomatis ke kelas.id_wali_kelas.
 */
class GuruImport implements ToModel, WithHeadingRow
{
    public int $importedCount = 0;

    public int $updatedCount = 0;

    public int $skippedCount = 0;

    public int $waliMappedCount = 0;

    public array $rowErrors = [];

    /** Peran yang TIDAK diproses pada import master guru. */
    protected const SKIP_PERAN = ['kepsek', 'kepala sekolah', 'kepala_sekolah'];

    /** Peran yang artinya akun guru + pemetaan wali kelas. */
    protected const WALI_PERAN = ['wali kelas', 'walikelas', 'wali_kelas', 'wali'];

    public function model(array $row): Model|array|null
    {
        $rawNip = trim((string) ($row['nip'] ?? ''));
        $nama = trim((string) ($row['nama_guru'] ?? $row['nama'] ?? ''));
        $peran = strtolower(trim((string) ($row['peran'] ?? $row['role'] ?? '')));
        $waliKelasText = trim((string) ($row['wali_kelas'] ?? $row['kelas'] ?? ''));
        $noHp = trim((string) ($row['no_hp'] ?? '')) ?: null;
        $status = trim((string) ($row['status'] ?? ''));

        // Normalisasi NIP: bila kosong, spasi, dash, atau 'null' → simpan ke DB sebagai NULL
        $nip = ($rawNip === '' || $rawNip === '-' || strtolower($rawNip) === 'null') ? null : $rawNip;

        // Baris kosong total (gap antar sheet / baris kosong).
        if ($nip === null && $nama === '') {
            $this->skippedCount++;

            return null;
        }

        // ── Skip peran "Kepsek" (tidak diproses pada import guru ini) ─────────
        if (in_array($peran, self::SKIP_PERAN, true)) {
            $this->skippedCount++;
            $this->rowErrors[] = 'Baris peran \'Kepsek\' dilewati (tidak diproses dalam import guru). Nama: '.($nama !== '' ? $nama : '-');

            return null;
        }

        if ($nama === '') {
            $this->skippedCount++;
            $this->rowErrors[] = 'Baris dilewati — nama guru kosong.';

            return null;
        }

        $isActive = $this->parseStatus($status);
        $isWaliKelas = in_array($peran, self::WALI_PERAN, true);

        // Cari record eksisting by NIP (jika NIP terisi) atau by Nama (jika NIP null)
        $existing = null;
        if ($nip !== null) {
            $existing = Guru::withoutGlobalScope(TestingDataScope::class)->withTrashed()->where('nip', $nip)->first();
        } else {
            $existing = Guru::withoutGlobalScope(TestingDataScope::class)->withTrashed()->whereNull('nip')->where('nama', $nama)->first();
        }

        // ── Username: Nama tersanitasi (lowercase, no special char, spasi -> titik) ──
        $username = $existing?->username ?? $this->makeUniqueUsername($nip, $nama, $existing?->id);

        // ── Password default "USERNAME123" — hanya untuk akun BARU
        $password = $existing?->password ?? Hash::make($username.'123');

        $subRole = $isWaliKelas ? 'wali_kelas' : 'guru_mapel';

        // Update / Create by ID / NIP / Username
        $searchKey = $existing ? ['id' => $existing->id] : ($nip !== null ? ['nip' => $nip] : ['username' => $username]);

        // 1) Guru::updateOrCreate
        $guru = Guru::withoutGlobalScope(TestingDataScope::class)->withTrashed()->updateOrCreate(
            $searchKey,
            [
                'nama' => $nama,
                'nip' => $nip,
                'is_active' => $isActive,
                'username' => $username,
                'sub_role' => $subRole,
                'role' => Guru::ROLE_GURU,
                'password' => $password,
                'no_hp' => $noHp,
            ]
        );

        // 2) User::updateOrCreate by username / id
        User::withoutGlobalScope(TestingDataScope::class)->withTrashed()->updateOrCreate(
            ['username' => $username],
            [
                'nama' => $nama,
                'nip' => $nip,
                'password' => $password,
                'role' => Guru::ROLE_GURU,
                'sub_role' => $subRole,
                'is_active' => $isActive,
                'no_hp' => $noHp,
            ]
        );

        if ($guru->wasRecentlyCreated) {
            $this->importedCount++;
        } else {
            $this->updatedCount++;
        }

        // 3) Pemetaan Wali Kelas: peran "Wali Kelas" + kolom WALI KELAS terisi.
        if ($isWaliKelas && $waliKelasText !== '') {
            if ($this->mapWaliKelas($guru, $waliKelasText)) {
                $this->waliMappedCount++;
            } else {
                $identifier = $nip ?? $nama;
                $this->rowErrors[] = "Wali kelas '{$waliKelasText}' tidak ditemukan di Data Master Kelas ({$identifier}) — relasi kelas tidak dipetakan.";
            }
        }

        return null;
    }

    /**
     * Interpretasi kolom STATUS → is_active (default Aktif).
     */
    protected function parseStatus(string $status): bool
    {
        $s = strtolower($status);

        if (in_array($s, ['nonaktif', 'tidak aktif', 'inactive', '0', 'no', 'false'], true)) {
            return false;
        }

        return true;
    }

    /**
     * Sanitasi nama guru: menghapus gelar akademik depan dan belakang beserta koma/titik.
     * Contoh: "Endang Safitri, S.Pd" -> "Endang Safitri"
     *         "Drs. H. Ahmad Fauzi, M.Pd." -> "Ahmad Fauzi"
     */
    public function sanitizeNamaGuru(string $nama): string
    {
        // 1. Hapus gelar depan (Drs., Dra., Dr., Ir., Prof., Pdt., H., Hj., K.H., dll)
        $clean = preg_replace('/^(dr[sa]?|dr|ir|prof|pdt|h|hj|k\.?h)\.?\s+/i', '', $nama);
        while (preg_match('/^(dr[sa]?|dr|ir|prof|pdt|h|hj|k\.?h)\.?\s+/i', $clean)) {
            $clean = preg_replace('/^(dr[sa]?|dr|ir|prof|pdt|h|hj|k\.?h)\.?\s+/i', '', $clean);
        }

        // 2. Hapus gelar belakang setelah koma
        $clean = preg_replace('/,.*$/', '', $clean);

        return trim($clean);
    }

    /**
     * Generasi username dasar dari nama guru yang sudah dibersihkan dari gelar:
     * lowercase, hapus karakter khusus selain huruf/angka/spasi, ubah spasi ke titik.
     * Contoh: "Endang Safitri, S.Pd" -> "endang.safitri"
     */
    public function generateBaseUsername(string $nama): string
    {
        $clean = $this->sanitizeNamaGuru($nama);
        $clean = strtolower($clean);
        // Hapus karakter khusus selain huruf, angka, dan spasi
        $clean = preg_replace('/[^a-z0-9\s]/', '', $clean);
        // Ganti spasi menjadi titik
        $clean = preg_replace('/\s+/', '.', trim($clean));

        return $clean;
    }

    /**
     * Generator username unik:
     * Ambil nama guru tersanitasi, ubah ke lowercase, ganti spasi dengan titik.
     * Jika username sudah terpakai oleh user lain, tambahkan angka suffix (2, 3, dst).
     */
    protected function makeUniqueUsername(?string $nip, string $nama, ?int $ignoreId = null): string
    {
        $base = $this->generateBaseUsername($nama);

        if (trim($base, '._-') === '') {
            $base = $nip ? preg_replace('/[^a-z0-9._\-]/', '', strtolower($nip)) : 'guru';
        }

        $username = $base;
        $suffix = 2;

        $query = User::withoutGlobalScope(TestingDataScope::class)->withTrashed()->where('username', $username);
        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        while ($query->exists()) {
            $username = $base.$suffix++;
            $query = User::withoutGlobalScope(TestingDataScope::class)->withTrashed()->where('username', $username);
            if ($ignoreId !== null) {
                $query->where('id', '!=', $ignoreId);
            }
        }

        return $username;
    }

    /**
     * Petakan kelas.id_wali_kelas ke guru sesuai string nama kelas pada kolom
     * "Wali Kelas" (mis. "X TKJ 1", "XI DKV 2").
     */
    protected function mapWaliKelas(Guru $guru, string $kelasText): bool
    {
        $kelasText = strtoupper(trim((string) preg_replace('/\s+/', ' ', $kelasText)));

        // Pisahkan tingkat (X/XI/XII — urut dari terpanjang) dari nama kelas.
        $tingkat = '';
        foreach (['XII', 'XI', 'X'] as $t) {
            if (preg_match('/^'.preg_quote($t, '/').'(\s|$)/', $kelasText)) {
                $tingkat = $t;
                $kelasText = trim(substr($kelasText, strlen($t)));

                break;
            }
        }

        if ($tingkat === '' || $kelasText === '') {
            return false;
        }

        $kelas = Kelas::query()
            ->withoutGlobalScope(TestingDataScope::class)
            ->whereRaw('UPPER(TRIM(tingkat)) = ? AND UPPER(TRIM(nama_kelas)) = ?', [$tingkat, $kelasText])
            ->orderByRaw('CASE WHEN is_testing_data = ? THEN 0 ELSE 1 END', [$guru->is_testing_data ? 1 : 0])
            ->orderBy('id')
            ->first();

        if ($kelas === null) {
            return false;
        }

        $kelas->id_wali_kelas = $guru->id;
        $kelas->save();

        return true;
    }
}