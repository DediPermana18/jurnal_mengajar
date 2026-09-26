<?php

namespace App\Models\Scopes;

use App\Models\TahunAjaran;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global Scope Tahun Ajaran Aktif untuk Master Jam Pelajaran.
 *
 * Menyaring konsumen runtime (dispensasi, piket, jurnal, plotting jadwal, jam pulang)
 * agar hanya melihat slot jam milik Tahun Ajaran yang sedang AKTIF, plus slot
 * "legacy" (tahun_ajaran_id NULL) yang merupakan data sebelum fitur ini diperkenalkan.
 *
 * - Belum ada Tahun Ajaran aktif -> scope no-op (semua slot terlihat; mode legacy).
 * - Admin Master Jam Pelajaran memakai tanpaGlobalScope() + scope ofTahunAjaran()
 *   agar bisa menjelajah/mengelola Tahun Ajaran lama (arsip) secara eksplisit.
 */
class ActiveTahunAjaranScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $activeId = $this->activeTahunAjaranId();

        if ($activeId === null) {
            return; // Fitur belum dipakai (tidak ada TA aktif) — biarkan semua terlihat.
        }

        // Konteks runtime = slot TA aktif + slot legacy (era sebelum fitur TA).
        $builder->where(function ($q) use ($activeId) {
            $q->where('tahun_ajaran_id', $activeId)
                ->orWhereNull('tahun_ajaran_id');
        });
    }

    protected function activeTahunAjaranId(): ?int
    {
        return TahunAjaran::query()
            ->where('is_active', true)
            ->value('id');
    }
}