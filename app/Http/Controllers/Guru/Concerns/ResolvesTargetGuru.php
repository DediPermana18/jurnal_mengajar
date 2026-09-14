<?php

namespace App\Http\Controllers\Guru\Concerns;

/**
 * Resolusi "context target guru" untuk impersonasi Switch View As (Mode QA IT).
 *
 * Saat Petugas IT / QA berpindah ke view Guru Mapel, auth() tetap milik IT,
 * namun data jadwal/jurnal/dispen mengikuti ID guru yang dipilih pada dropdown
 * "Pilih Context Target Guru" (session `impersonate_target_id`).
 */
trait ResolvesTargetGuru
{
    /**
     * ID guru target impersonasi yang dipilih via dropdown, atau null.
     */
    protected function impersonateTargetGuruId(): ?int
    {
        $id = session('impersonate_target_id');

        return $id !== null && $id !== '' ? (int) $id : null;
    }

    /**
     * ID guru efektif untuk pencarian data mengajar:
     * `session('impersonate_target_id') ?? auth()->user()->id`.
     */
    protected function effectiveGuruId(): int
    {
        return $this->impersonateTargetGuruId() ?? (int) auth()->id();
    }

    /**
     * Apakah user saat ini sedang dalam Mode Preview (Switch View As)?
     * Berlaku hanya jika ada `active_role` di session (sudah memilih role
     * pada dropdown "Pilih Role Portal").
     */
    protected function isInPreviewMode(): bool
    {
        return filled(session('active_role'));
    }

    /**
     * Apakah user QA/IT sedang dalam Mode Preview tapi TANPA target terpilih?
     * Bila TRUE, portal Wali Kelas harus menampilkan konten kosong —
     * DILARANG melakukan fallback query ke data akun sendiri (`auth()->id()`).
     */
    protected function isEmptyTargetContext(): bool
    {
        return $this->isInPreviewMode() && $this->impersonateTargetGuruId() === null;
    }
}
