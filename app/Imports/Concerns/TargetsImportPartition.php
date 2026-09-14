<?php

namespace App\Imports\Concerns;

use App\Models\User;

/**
 * Pembaca konteks partisi data is_testing_data untuk proses import.
 *
 * Saat Petugas IT / QA Tester melakukan Impersonate / Switch View As sebagai
 * role non-IT (mis. Admin TU), import diselaraskan dengan role yang dilihat
 * user: menarget partisi REAL (is_testing_data = 0). Partisi TESTING hanya
 * dipakai untuk Petugas IT / QA Tester MURNI (belum Switch View).
 */
trait TargetsImportPartition
{
    /**
     * Role yang sedang di-impersonate / Switch View As. Mengutamakan session
     * 'active_role' (mekanisme resmi) dengan toleransi 'impersonate_role'.
     */
    public static function impersonatedRole(?User $user = null): ?string
    {
        $user = $user ?? auth()->user();

        if (! $user instanceof User || ! $user->isPetugasIt()) {
            return null;
        }

        return session('active_role') ?: session('impersonate_role') ?: null;
    }

    /**
     * Apakah import saat ini menarget partisi data TESTING (is_testing_data = 1)?
     * Hanya TRUE untuk Petugas IT / QA Tester murni (belum Switch View).
     */
    public static function isImportTestingContext(?User $user = null): bool
    {
        $user = $user ?? auth()->user();

        if (! $user instanceof User || ! $user->isPetugasIt()) {
            return false;
        }

        return self::impersonatedRole($user) === null;
    }

    /** Nilai is_testing_data untuk partisi yang sedang di-import (1 / 0). */
    public function targetIsTestingData(): bool
    {
        return static::isImportTestingContext(auth()->user());
    }

    /**
     * Mengambil nilai cell pada posisi kolom (0-based untuk kolom B=1, C=2).
     * Dipakai sebagai fallback bila header tidak ter-slug ke kunci yang dikenal
     * (mis. "KODE" / "NAMA" tanpa slug "jurusan").
     */
    protected function cellAt(array $row, int $position): string
    {
        $values = array_values($row);

        return trim((string) ($values[$position] ?? ''));
    }
}
