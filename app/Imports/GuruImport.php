<?php

namespace App\Imports;

use App\Models\Guru;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class GuruImport implements ToModel, WithHeadingRow
{
    public int $importedCount = 0;

    public int $updatedCount = 0;

    public int $skippedCount = 0;

    public array $rowErrors = [];

    /**
     * Petakan kolom file: NIP, NAMA GURU, STATUS.
     * updateOrCreate berbasis 'nip' mencegah duplikasi.
     */
    public function model(array $row): Model|array|null
    {
        $nip = trim((string) ($row['nip'] ?? ''));
        $nama = trim((string) ($row['nama_guru'] ?? $row['nama'] ?? ''));
        $status = trim((string) ($row['status'] ?? ''));

        // NIP adalah kunci deduplikasi → baris tanpa NIP tidak bisa diproses.
        if ($nip === '') {
            $this->skippedCount++;
            $this->rowErrors[] = 'Baris dilewati — kolom NIP kosong.';

            return null;
        }

        if ($nama === '') {
            $this->skippedCount++;
            $this->rowErrors[] = "Baris dilewati — nama guru kosong untuk NIP {$nip}.";

            return null;
        }

        $isActive = $this->parseStatus($status);

        // Pertahankan username & sub_role yang sudah ada bila NIP sudah terdaftar.
        $existing = Guru::withTrashed()->where('nip', $nip)->first();
        $username = $existing?->username ?? $this->makeUniqueUsername($nip, $nama);
        $subRole = $existing?->sub_role ?: 'guru_mapel';

        // Kolom password NOT NULL: pertahankan hash lama agar import-ulang
        // tidak mereset password; hanya data baru yang mendapat hash default.
        $password = $existing?->password ?? Hash::make('password123');

        $guru = Guru::withTrashed()->updateOrCreate(
            ['nip' => $nip],
            [
                'nama'      => $nama,
                'is_active' => $isActive,
                'username'  => $username,
                'sub_role'  => $subRole,
                'role'      => Guru::ROLE_GURU,
                'password'  => $password,
            ]
        );

        if ($guru->wasRecentlyCreated) {
            $this->importedCount++;
        } else {
            $this->updatedCount++;
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
     * Username unik bertambah angka bila terjadi konflik dengan data lama.
     */
    protected function makeUniqueUsername(string $nip, string $nama): string
    {
        $base = Str::slug($nama, '.');
        if (trim($base, '.') === '') {
            $base = 'guru.' . $nip;
        }

        $username = $base;
        $suffix = 2;

        while (Guru::withTrashed()->where('username', $username)->exists()) {
            $username = $base . '.' . $suffix++;
        }

        return $username;
    }
}