<?php

namespace App\Support;

use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\Scopes\ActiveTahunAjaranScope;
use App\Models\ShiftPelajaran;
use App\Models\TahunAjaran;
use Illuminate\Support\Collection;

/**
 * Resolver slot jam `id_jam` — sumber kebenaran tunggal untuk pemetaan
 * `jadwal_pelajaran.id_jam` ke master `jam_pelajaran`.
 *
 * MASALAH YANG DISELESAIKAN
 * -------------------------
 * `jadwal_pelajaran.id_jam` adalah FK auto-increment ke `jam_pelajaran.id`.
 * Ketika master jam dihapus & dibuat ulang, ID baru menjadi 91, 93, 97, 100, ...
 * sehingga seluruh baris jadwal lama menunjuk slot yang sudah tidak ada
 * (soft-deleted). Akibatnya:
 *   - Matriks Plotting kelas: slot tampil "Belum di-plot" (jadwal hilang diam-diam).
 *   - Dashboard Guru: jadwal seri ke bawah karena `jam_ke` NULL.
 *
 * ATURAN
 * ------
 * 1. `id_jam` TIDAK PERNAH diambil dari request/frontend. Selalu di-resolve
 *    dari kombinasi (hari, jam_ke, shift efektif kelas, tahun ajaran).
 * 2. Petakan slot diurutkan & dicocokkan lewat `jam_pelajaran.jam_ke`,
 *    bukan urutan `id`.
 * 3. Jadwal "gantung" (id_jam tak tertaut master) TIDAK dibuang — tetap
 *    ditampilkan supaya dashboard tidak rusak, dan ditandai agar bisa
 *    di-heal saat halaman_plotting_ dibuka.
 */
class JamSlotResolver
{
    /**
     * Resolve shift efektif sebuah kelas (sinkron dengan JadwalPelajaranController).
     */
    public static function shiftUntukKelas(?Kelas $kelas, ?TahunAjaran $tahunAktif = null): ?int
    {
        if (! $kelas) {
            return null;
        }

        if ($kelas->shift_id) {
            return (int) $kelas->shift_id;
        }

        // Bila kelas belum di-bind shift, cari shift aktif yang melayani
        // tingkatan kelas (mode Multi-Shift).
        if (! $kelas->tingkat) {
            return null;
        }

        $tingkat = match (strtoupper(trim($kelas->tingkat))) {
            'X' => '10', 'XI' => '11', 'XII' => '12', default => (string) $kelas->tingkat
        };

        $shift = ShiftPelajaran::forCurrentContext()
            ->where('is_active', true)
            ->get()
            ->first(fn ($s) => ($s->grade_levels ?? []) && $s->servesGrade($tingkat));

        return $shift?->id;
    }

    /**
     * Petakan sekumpulan baris jadwal ke slot jam AKTIF yang benar.
     *
     * Untuk setiap baris:
     *  1. Kalau `id_jam` masih menunjuk slot aktif yang cocok (hari + shift) → dipakai.
     *  2. Kalau tidak → ambil `jam_ke` dari master BAWAH (bisa soft-deleted),
     *     lalu resolve ulang ke slot aktif via resolveSlot().
     *  3. Kalau `jam_ke` juga tidak bisa dibaca (master hilang total) → baris
     *     ditandai sebagai "yatim" dan tetap dikembalikan agar tidak hilang.
     *
     * @param  Collection<int, JadwalPelajaran>  $jadwals
     * @return array{mapped: Collection<int, JadwalPelajaran>, orphans: Collection<int, JadwalPelajaran>}
     */
    public static function petakanJadwal(
        Collection $jadwals,
        string $hari,
        ?int $shiftId,
        ?int $tahunAjaranId,
        bool $includeLegacyTa = false
    ): array {
        if ($jadwals->isEmpty()) {
            return ['mapped' => collect(), 'orphans' => collect()];
        }

        // 1. Master slot AKTIF yang terlihat di halaman plotting.
        $slotAktif = JamPelajaran::withoutGlobalScope(ActiveTahunAjaranScope::class)
            ->where('hari', $hari)
            ->ofShift($shiftId)
            ->ofTahunAjaran($tahunAjaranId, $includeLegacyTa)
            ->get();

        $byId = $slotAktif->keyBy('id');
        $byJamKe = $slotAktif
            ->whereNotNull('jam_ke')
            ->where('jenis', '!=', 'istirahat')
            ->sortByDesc('id')
            ->keyBy(fn ($s) => (int) $s->jam_ke);

        // 2. Master LENGKAP (termasuk soft-deleted) — sumber fallback jam_ke
        //    untuk jadwal yang id_jam-nya sudah "dari zaman dulu".
        $staleIds = $jadwals->pluck('id_jam')->filter()->unique()->diff($byId->keys());
        $jamKeDariMasterLama = collect();
        if ($staleIds->isNotEmpty()) {
            $lama = JamPelajaran::withoutGlobalScope(ActiveTahunAjaranScope::class)
                ->withTrashed()
                ->whereIn('id', $staleIds->all())
                ->get()
                ->keyBy('id');

            foreach ($jadwals as $j) {
                $slot = $lama->get($j->id_jam);
                if ($slot && $slot->jam_ke !== null) {
                    $jamKeDariMasterLama[$j->id] = (int) $slot->jam_ke;
                }
            }
        }

        $mapped = collect();
        $orphans = collect();
        $perluDisimpan = [];

        foreach ($jadwals as $j) {
            // (1) Sudah menunjuk slot aktif yang cocok → langsung pakai.
            if ($byId->has($j->id_jam)) {
                $slot = $byId->get($j->id_jam);
                if ($slot->hari === $hari && $slot->jenis !== 'istirahat') {
                    $mapped->put($slot->id, $j);

                    continue;
                }
            }

            // (2) Cari via jam_ke: lama dulu, sekarang.
            $jamKe = null;
            $slotLama = null;
            if ($jamKeDariMasterLama->has($j->id)) {
                $jamKe = $jamKeDariMasterLama->get($j->id);
            } else {
                $raw = JamPelajaran::withoutGlobalScope(ActiveTahunAjaranScope::class)
                    ->withTrashed()
                    ->find($j->id_jam);
                $slotLama = $raw;
                $jamKe = $raw?->jam_ke !== null ? (int) $raw->jam_ke : null;
            }

            if ($jamKe !== null && $byJamKe->has($jamKe)) {
                $target = $byJamKe->get($jamKe);
                if ((int) $j->id_jam !== (int) $target->id) {
                    $perluDisimpan[$j->id] = $target->id;
                    $j->id_jam = $target->id;
                }
                $mapped->put($target->id, $j);

                continue;
            }

            // (3) Yatim: tidak ada slot untuk jam_ke ini di hari ini.
            //     Tetap kembalikan agar dashboard/matriks tidak kosong/error.
            $orphans->put($j->id_jam ?? ('yatim-'.$j->id), $j);
            $mapped->put($j->id_jam ?? ('yatim-'.$j->id), $j);
        }

        // Auto-heal: persist hanya bila benar-benar berubah.
        foreach ($perluDisimpan as $jadwalId => $slotIdBaru) {
            JadwalPelajaran::withoutGlobalScope(ActiveTahunAjaranScope::class)
                ->whereKey($jadwalId)
                ->update(['id_jam' => $slotIdBaru]);
        }

        return ['mapped' => $mapped, 'orphans' => $orphans];
    }
}
