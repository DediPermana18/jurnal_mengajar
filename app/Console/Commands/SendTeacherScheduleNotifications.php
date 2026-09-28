<?php

namespace App\Console\Commands;

use App\Models\JadwalPelajaran;
use App\Models\Jurnal;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Services\FonnteService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Kirim notifikasi WhatsApp otomatis ke Guru via Fonnte berdasarkan jadwal mengajar harian.
 *
 * Empat jenis notifikasi:
 *  A. Agenda Pagi (06:30 WIB)     — ringkasan seluruh jadwal hari ini.
 *  B. H-10 menit sebelum mulai    — pengingat dini masuk kelas.
 *  C. H-0 (saat jam masuk)        — notifikasi tepat saat KBM dimulai.
 *  D. H-5 menit sebelum selesai   — pengingat isi jurnal (bila belum diisi).
 *
 * Command dijadwalkan berjalan setiap menit via Laravel Scheduler.
 *
 * Window toleransi (scheduler kadang terlambat beberapa detik):
 *  - H-10 : jam_mulai antara now+9 menit s.d. now+11 menit
 *  - H-0  : jam_mulai antara now-10 detik s.d. now+2 menit
 *  - H-5 selesai : jam_selesai +/-1 menit dari (now + 5 menit)
 *
 * Deduplikasi via cache key per blok-per-hari (TTL 60 menit).
 * Timezone: Asia/Jakarta secara eksplisit di seluruh operasi Carbon.
 * Pesan: tanpa URL/link agar lolos Fonnte Free Package.
 */
class SendTeacherScheduleNotifications extends Command
{
    protected $signature = 'guru:notif-jadwal
                            {--type= : Paksa jenis notifikasi (pagi|h10|h0|selesai|kbm). Default: auto-detect.}
                            {--dry-run : Tampilkan pesan tanpa benar-benar mengirim via Fonnte.}
                            {--force : Bypass pengecekan jendela waktu; kirim notifikasi uji nyata ke nomor guru target.}
                            {--test-guru= : ID atau NIP guru tujuan saat --force aktif. Jika kosong, ambil guru pertama yang ada jadwal hari ini.}';

    protected $description = 'Kirim notifikasi WA ke guru: agenda pagi, H-10 menit, H-0 (jam masuk), dan pengingat jurnal sebelum KBM selesai.';

    // -------------------------------------------------------------------------
    // KONSTANTA
    // -------------------------------------------------------------------------

    /** Timezone operasional. */
    protected const TIMEZONE = 'Asia/Jakarta';

    /** Jendela H-10: jam_mulai harus berada antara now + MIN s.d. now + MAX menit. */
    protected const H10_WINDOW_MIN = 9;
    protected const H10_WINDOW_MAX = 11;

    /** Jendela H-0: jam_mulai antara now - 10 detik s.d. now + MAX menit. */
    protected const H0_WINDOW_MAX  = 2;

    /** Pengingat selesai: menit sebelum jam_selesai, toleransi +/-1 menit. */
    protected const SELESAI_NOTIF_BEFORE = 5;
    protected const SELESAI_WINDOW       = 1;

    /** TTL cache flag notifikasi (menit). */
    protected const CACHE_TTL = 60;

    // =========================================================================

    public function handle(): int
    {
        $now        = Carbon::now(self::TIMEZONE);
        $type       = $this->option('type') ?: $this->detectType($now);
        $isDry      = (bool) $this->option('dry-run');
        $isForce    = (bool) $this->option('force');
        $testGuruId = $this->option('test-guru');

        if ($isDry) {
            $this->warn('[DRY-RUN] Mode aktif — pesan tidak akan terkirim ke Fonnte.');
        }

        // --force: bypass jendela waktu, kirim 1 pesan uji nyata ke guru target
        if ($isForce) {
            if (! $type || $type === 'kbm') {
                $type = 'h10'; // default jenis saat --force tanpa --type
            }
            return $this->handleForceTest($now, $type, $testGuruId, $isDry);
        }

        match ($type) {
            'pagi'    => $this->handleAgendaPagi($now, $isDry),
            'h10'     => $this->handlePengingatH10($now, $isDry),
            'h0'      => $this->handlePengingatH0($now, $isDry),
            'selesai' => $this->handlePengingatSelesai($now, $isDry),
            'kbm'     => $this->handleSemuaKbm($now, $isDry),
            default   => null,
        };

        return self::SUCCESS;
    }

    // =========================================================================
    // A. AGENDA PAGI (06:30 WIB)
    // =========================================================================

    protected function handleAgendaPagi(Carbon $now, bool $isDry): void
    {
        $cacheKey = 'notif_pagi_' . $now->toDateString();
        if (cache()->has($cacheKey) && ! $isDry) {
            return;
        }

        $hariIni = $this->namaHariIndonesia($now);
        $jadwals = $this->getJadwalHariIni($hariIni);
        if ($jadwals->isEmpty()) {
            return;
        }

        $perGuru  = $jadwals->groupBy('id_guru');
        $terkirim = 0;

        foreach ($perGuru as $guruId => $jadwalGuru) {
            $guru = $jadwalGuru->first()->guru;
            if (! $guru) {
                continue;
            }

            $noHp = $this->sanitasiNoHp($guru);
            if ($noHp === null) {
                continue;
            }

            $bloks = $this->blockJadwal($jadwalGuru);
            $pesan = $this->pesanAgendaPagi($guru, $hariIni, $bloks, $now);

            if ($isDry) {
                $this->line("[DRY-RUN][PAGI] -> {$noHp}");
                foreach (explode("\n", $pesan) as $l) {
                    $this->line($l);
                }
                $this->line('---');
            } elseif (FonnteService::sendNotification($noHp, $pesan)) {
                $terkirim++;
            }
        }

        if (! $isDry) {
            cache()->put($cacheKey, true, $now->copy()->addHours(23));
            Log::info("[guru:notif-jadwal] Agenda Pagi terkirim ke {$terkirim} guru.");
        }
    }

    // =========================================================================
    // DISPATCHER KBM — jalankan B+C+D sekaligus
    // =========================================================================

    protected function handleSemuaKbm(Carbon $now, bool $isDry): void
    {
        $this->handlePengingatH10($now, $isDry);
        $this->handlePengingatH0($now, $isDry);
        $this->handlePengingatSelesai($now, $isDry);
    }

    // =========================================================================
    // FORCE TEST — bypass jendela waktu, kirim 1 pesan uji nyata
    // =========================================================================

    /**
     * Kirim satu pesan uji coba (real/dry-run) tanpa memedulikan jendela waktu.
     *
     * Logika:
     *  1. Resolusi guru target: dari --test-guru (ID/NIP) atau guru pertama hari ini.
     *  2. Ambil blok jadwal terdekat dari jam sekarang (atau blok pertama bila tidak ada).
     *  3. Bangun pesan sesuai --type (h10|h0|selesai|pagi) lalu kirim.
     *
     * @param  string|null  $testGuruId  Nilai option --test-guru (ID atau NIP)
     */
    protected function handleForceTest(Carbon $now, string $type, ?string $testGuruId, bool $isDry): int
    {
        $hariIni = $this->namaHariIndonesia($now);
        $jadwals = $this->getJadwalHariIni($hariIni);

        // Fallback: jika tidak ada jadwal hari ini, coba hari lain (Senin)
        // agar --force selalu bisa menghasilkan contoh pesan.
        if ($jadwals->isEmpty()) {
            $this->warn('[FORCE] Tidak ada jadwal hari ini, mencoba hari Senin sebagai fallback...');
            $jadwals = $this->getJadwalHariIni('Senin');
        }

        if ($jadwals->isEmpty()) {
            $this->error('[FORCE] Tidak ada jadwal KBM sama sekali di database. Batalkan.');
            return self::FAILURE;
        }

        // --- Resolusi guru target ---
        $guru = $this->resolusiGuruTarget($jadwals, $testGuruId);
        if (! $guru) {
            $this->error('[FORCE] Guru target tidak ditemukan atau tidak memiliki nomor HP valid.');
            return self::FAILURE;
        }

        $noHp = $this->sanitasiNoHp($guru);
        if ($noHp === null) {
            $this->error("[FORCE] Nomor HP guru {$guru->name} tidak valid.");
            return self::FAILURE;
        }

        // --- Ambil blok jadwal guru target ---
        $jadwalGuru = $jadwals->where('id_guru', $guru->id)->values();
        if ($jadwalGuru->isEmpty()) {
            // Guru ditemukan tapi tidak ada jadwal hari ini — ambil jadwal pertama apapun
            $jadwalGuru = $jadwals->groupBy('id_guru')->first();
        }

        $bloks = $this->blockJadwal($jadwalGuru);
        if (empty($bloks)) {
            $this->error('[FORCE] Tidak ada blok KBM yang bisa dibangun.');
            return self::FAILURE;
        }

        // Pilih blok terdekat dari jam sekarang (yang belum/baru mulai),
        // atau blok pertama sebagai fallback.
        $blok = $this->blokTerdekat($bloks, $now);

        // --- Bangun pesan ---
        $pesan = match ($type) {
            'h10'     => $this->pesanPengingatH10($guru, $blok),
            'h0'      => $this->pesanPengingatH0($guru, $blok),
            'selesai' => $this->pesanPengingatJurnal($guru, $blok),
            'pagi'    => $this->pesanAgendaPagi($guru, $hariIni, $bloks, $now),
            default   => $this->pesanPengingatH10($guru, $blok),
        };

        $label = strtoupper($type);

        if ($isDry) {
            $this->warn("[FORCE][DRY-RUN][{$label}] Pesan yang AKAN dikirim ke {$noHp} ({$guru->name}):");
            $this->line(str_repeat('-', 50));
            foreach (explode("\n", $pesan) as $l) {
                $this->line($l);
            }
            $this->line(str_repeat('-', 50));
            $this->info('[FORCE][DRY-RUN] Pesan tidak dikirim (--dry-run aktif).');
        } else {
            $this->warn("[FORCE] Mengirim notifikasi uji [{$label}] ke {$noHp} ({$guru->name})...");
            $berhasil = FonnteService::sendNotification($noHp, $pesan);
            if ($berhasil) {
                $this->info("[FORCE] Berhasil terkirim ke {$noHp} ({$guru->name}).");
                Log::info("[guru:notif-jadwal][FORCE] Notifikasi uji [{$label}] terkirim ke {$guru->name} ({$noHp}).");
            } else {
                $this->error("[FORCE] Gagal mengirim ke {$noHp}. Periksa log Fonnte.");
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    /**
     * Resolusi guru target berdasarkan nilai --test-guru (ID atau NIP).
     * Jika kosong, kembalikan guru pertama yang ada di $jadwals.
     *
     * @param  Collection<JadwalPelajaran>  $jadwals
     */
    protected function resolusiGuruTarget(Collection $jadwals, ?string $testGuruId): ?User
    {
        if (! empty($testGuruId)) {
            // Cari berdasarkan id (numerik) atau nip (string)
            $guru = User::withoutGlobalScopes()
                ->where(function ($q) use ($testGuruId) {
                    $q->where('id', $testGuruId)
                      ->orWhere('nip', $testGuruId);
                })
                ->first();

            if (! $guru) {
                $this->warn("[FORCE] Guru dengan ID/NIP '{$testGuruId}' tidak ditemukan, jatuh ke guru pertama.");
            } else {
                return $guru;
            }
        }

        // Fallback: guru pertama yang ada jadwal hari ini
        foreach ($jadwals as $jadwal) {
            if ($jadwal->guru && ! empty($jadwal->guru->no_hp)) {
                return $jadwal->guru;
            }
        }

        return null;
    }

    /**
     * Pilih blok KBM yang jam_mulai-nya paling dekat dari sekarang (belum/baru mulai).
     * Jika semua blok sudah lewat, kembalikan blok pertama.
     *
     * @param  array[]  $bloks
     */
    protected function blokTerdekat(array $bloks, Carbon $now): array
    {
        $mendatang = array_filter($bloks, function ($b) use ($now) {
            $jamMulai = Carbon::parse($now->toDateString() . ' ' . $b['jam_mulai'], self::TIMEZONE);
            return $jamMulai->gte($now);
        });

        if (! empty($mendatang)) {
            // Blok paling awal di antara yang belum mulai
            usort($mendatang, fn ($a, $b) => strcmp($a['jam_mulai'], $b['jam_mulai']));
            return array_values($mendatang)[0];
        }

        // Semua sudah lewat — kembalikan blok terakhir (paling akhir hari ini)
        return end($bloks);
    }

    // =========================================================================
    // B. H-10 MENIT SEBELUM KBM MULAI
    // =========================================================================

    /**
     * Kirim pengingat 10 menit sebelum KBM dimulai.
     *
     * Jendela: jam_mulai antara (now + 9 menit) s.d. (now + 11 menit).
     * Cache:   kbm_notified_h10_{guruId}_{jam_mulai}_{tanggal}
     */
    protected function handlePengingatH10(Carbon $now, bool $isDry): void
    {
        $hariIni     = $this->namaHariIndonesia($now);
        $jadwals     = $this->getJadwalHariIni($hariIni);
        if ($jadwals->isEmpty()) {
            return;
        }

        $windowMulai = $now->copy()->addMinutes(self::H10_WINDOW_MIN);
        $windowAkhir = $now->copy()->addMinutes(self::H10_WINDOW_MAX);
        $terkirim    = 0;

        foreach ($jadwals->groupBy('id_guru') as $guruId => $jadwalGuru) {
            $guru = $jadwalGuru->first()->guru;
            if (! $guru) {
                continue;
            }

            $noHp = $this->sanitasiNoHp($guru);
            if ($noHp === null) {
                continue;
            }

            foreach ($this->blockJadwal($jadwalGuru) as $blok) {
                $jamMulai = Carbon::parse(
                    $now->toDateString() . ' ' . $blok['jam_mulai'],
                    self::TIMEZONE
                );

                if (! $this->dalamJendelaAntara($jamMulai, $windowMulai, $windowAkhir)) {
                    continue;
                }

                $cacheKey = 'kbm_notified_h10_' . $guruId . '_' . $blok['jam_mulai'] . '_' . $now->toDateString();
                if (cache()->has($cacheKey) && ! $isDry) {
                    continue;
                }

                $pesan = $this->pesanPengingatH10($guru, $blok);

                if ($isDry) {
                    $this->line("[DRY-RUN][H-10] -> {$noHp}");
                    foreach (explode("\n", $pesan) as $l) {
                        $this->line($l);
                    }
                    $this->line('---');
                } else {
                    if (FonnteService::sendNotification($noHp, $pesan)) {
                        cache()->put($cacheKey, true, $now->copy()->addMinutes(self::CACHE_TTL));
                        $terkirim++;
                    }
                }
            }
        }

        if (! $isDry && $terkirim > 0) {
            Log::info("[guru:notif-jadwal] H-10 KBM terkirim ke {$terkirim} guru.");
        }
    }

    // =========================================================================
    // C. H-0 — TEPAT SAAT JAM KBM DIMULAI
    // =========================================================================

    /**
     * Kirim notifikasi tepat saat jam pelajaran dimulai.
     *
     * Jendela: jam_mulai antara (now - 10 detik) s.d. (now + 2 menit).
     * Cache:   kbm_notified_h0_{guruId}_{jam_mulai}_{tanggal}
     */
    protected function handlePengingatH0(Carbon $now, bool $isDry): void
    {
        $hariIni     = $this->namaHariIndonesia($now);
        $jadwals     = $this->getJadwalHariIni($hariIni);
        if ($jadwals->isEmpty()) {
            return;
        }

        $windowMulai = $now->copy()->subSeconds(10);
        $windowAkhir = $now->copy()->addMinutes(self::H0_WINDOW_MAX);
        $terkirim    = 0;

        foreach ($jadwals->groupBy('id_guru') as $guruId => $jadwalGuru) {
            $guru = $jadwalGuru->first()->guru;
            if (! $guru) {
                continue;
            }

            $noHp = $this->sanitasiNoHp($guru);
            if ($noHp === null) {
                continue;
            }

            foreach ($this->blockJadwal($jadwalGuru) as $blok) {
                $jamMulai = Carbon::parse(
                    $now->toDateString() . ' ' . $blok['jam_mulai'],
                    self::TIMEZONE
                );

                if (! $this->dalamJendelaAntara($jamMulai, $windowMulai, $windowAkhir)) {
                    continue;
                }

                $cacheKey = 'kbm_notified_h0_' . $guruId . '_' . $blok['jam_mulai'] . '_' . $now->toDateString();
                if (cache()->has($cacheKey) && ! $isDry) {
                    continue;
                }

                $pesan = $this->pesanPengingatH0($guru, $blok);

                if ($isDry) {
                    $this->line("[DRY-RUN][H-0] -> {$noHp}");
                    foreach (explode("\n", $pesan) as $l) {
                        $this->line($l);
                    }
                    $this->line('---');
                } else {
                    if (FonnteService::sendNotification($noHp, $pesan)) {
                        cache()->put($cacheKey, true, $now->copy()->addMinutes(self::CACHE_TTL));
                        $terkirim++;
                    }
                }
            }
        }

        if (! $isDry && $terkirim > 0) {
            Log::info("[guru:notif-jadwal] H-0 KBM terkirim ke {$terkirim} guru.");
        }
    }

    // =========================================================================
    // D. H-5 MENIT SEBELUM KBM SELESAI (PENGINGAT JURNAL)
    // =========================================================================

    /**
     * Kirim pengingat isi jurnal 5 menit sebelum jam pelajaran berakhir.
     * Hanya dikirim jika jurnal BELUM diisi hari ini.
     *
     * Jendela: jam_selesai dalam +/-1 menit dari (now + 5 menit).
     * Cache:   kbm_notified_selesai_{guruId}_{jam_selesai}_{tanggal}
     */
    protected function handlePengingatSelesai(Carbon $now, bool $isDry): void
    {
        $hariIni    = $this->namaHariIndonesia($now);
        $jadwals    = $this->getJadwalHariIni($hariIni);
        if ($jadwals->isEmpty()) {
            return;
        }

        $targetWaktu = $now->copy()->addMinutes(self::SELESAI_NOTIF_BEFORE);
        $terkirim    = 0;

        foreach ($jadwals->groupBy('id_guru') as $guruId => $jadwalGuru) {
            $guru = $jadwalGuru->first()->guru;
            if (! $guru) {
                continue;
            }

            $noHp = $this->sanitasiNoHp($guru);
            if ($noHp === null) {
                continue;
            }

            foreach ($this->blockJadwal($jadwalGuru) as $blok) {
                $jamSelesai = Carbon::parse(
                    $now->toDateString() . ' ' . $blok['jam_selesai'],
                    self::TIMEZONE
                );

                if (! $this->dalamJendelaSymetris($targetWaktu, $jamSelesai, self::SELESAI_WINDOW)) {
                    continue;
                }

                $cacheKey = 'kbm_notified_selesai_' . $guruId . '_' . $blok['jam_selesai'] . '_' . $now->toDateString();
                if (cache()->has($cacheKey) && ! $isDry) {
                    continue;
                }

                if ($this->cekJurnalSudahDiisi($blok['jadwal_ids'], $now)) {
                    if ($isDry) {
                        $this->line("[DRY-RUN][SELESAI] {$guru->name} — jurnal sudah diisi, skip.");
                    }
                    if (! $isDry) {
                        cache()->put($cacheKey, true, $now->copy()->addMinutes(30));
                    }
                    continue;
                }

                $pesan = $this->pesanPengingatJurnal($guru, $blok);

                if ($isDry) {
                    $this->line("[DRY-RUN][SELESAI] -> {$noHp}");
                    foreach (explode("\n", $pesan) as $l) {
                        $this->line($l);
                    }
                    $this->line('---');
                } else {
                    if (FonnteService::sendNotification($noHp, $pesan)) {
                        cache()->put($cacheKey, true, $now->copy()->addMinutes(30));
                        $terkirim++;
                    }
                }
            }
        }

        if (! $isDry && $terkirim > 0) {
            Log::info("[guru:notif-jadwal] Pengingat Jurnal terkirim ke {$terkirim} guru.");
        }
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Deteksi jenis notifikasi dari jam sekarang (Asia/Jakarta).
     */
    protected function detectType(Carbon $now): ?string
    {
        $h = (int) $now->format('H');
        $m = (int) $now->format('i');

        if ($h === 6 && $m === 30) {
            return 'pagi';
        }

        if ($h >= 5 && $h <= 19) {
            return 'kbm';
        }

        return null;
    }

    /**
     * Ambil seluruh jadwal pelajaran hari ini (non-testing, TA aktif).
     *
     * @return Collection<JadwalPelajaran>
     */
    protected function getJadwalHariIni(string $hari): Collection
    {
        $tahunAktifId = TahunAjaran::withoutGlobalScopes()
            ->where('is_testing_data', false)
            ->where('is_active', true)
            ->value('id');

        if ($tahunAktifId === null) {
            Log::warning('[guru:notif-jadwal] Tidak ada Tahun Ajaran aktif — notifikasi dilewati.');
            return collect();
        }

        return JadwalPelajaran::withoutGlobalScopes()
            ->where('hari', $hari)
            ->where('id_tahun_ajaran', $tahunAktifId)
            ->where('is_testing_data', false)
            ->whereNull('deleted_at')
            ->with([
                'guru'          => fn ($q) => $q->withoutGlobalScopes(),
                'mataPelajaran' => fn ($q) => $q->withoutGlobalScopes(),
                'kelas'         => fn ($q) => $q->withoutGlobalScopes(),
                'ruangan'       => fn ($q) => $q->withoutGlobalScopes(),
                'jamPelajaran'  => fn ($q) => $q->withoutGlobalScopes(),
            ])
            ->get()
            ->filter(fn ($j) => $j->jamPelajaran && $j->jamPelajaran->jenis === 'kbm')
            ->filter(fn ($j) => $j->guru !== null)
            ->values();
    }

    /**
     * Kelompokkan jadwal guru menjadi "blok" KBM yang benar-benar berurutan.
     *
     * Dua JP digabung menjadi satu blok jika SEMUA kondisi berikut terpenuhi:
     *  1. id_mapel   sama  — mapel tidak berganti
     *  2. id_kelas   sama  — kelas tidak berganti
     *  3. id_ruangan sama  — ruangan/lokasi tidak berpindah
     *  4. jam_ke     berurutan (n+1)  — tidak ada slot kosong di antara keduanya
     *  5. jam_selesai JP sebelumnya === jam_mulai JP berikutnya  — waktu benar-benar
     *     tersambung langsung (tidak ada jeda istirahat, upacara, dll.)
     *
     * Jika kondisi (5) gagal — misalnya ada slot istirahat yang memisahkan dua slot
     * JP bernomor berurutan — keduanya dianggap blok BERBEDA sehingga notifikasi
     * H-10 dan H-0 tetap dikirim untuk JP lanjutan tersebut.
     *
     * @param  Collection<JadwalPelajaran>  $jadwalGuru
     * @return array[]  Array of blok: {jam_ke_mulai, jam_ke_selesai, jam_mulai,
     *                                   jam_selesai, mapel, kelas, ruangan, jadwal_ids}
     */
    protected function blockJadwal(Collection $jadwalGuru): array
    {
        $sorted = $jadwalGuru
            ->sortBy(fn ($j) => (int) ($j->jamPelajaran?->jam_ke ?? 0))
            ->values();

        $bloks  = [];
        $buffer = null;

        foreach ($sorted as $jadwal) {
            $jamKe        = (int) ($jadwal->jamPelajaran?->jam_ke ?? 0);
            $idMapel      = $jadwal->id_mapel;
            $idKelas      = $jadwal->id_kelas;
            $idRuangan    = $jadwal->id_ruangan;
            $jamMulaiJP   = $jadwal->jamPelajaran?->jam_mulai ?? '00:00';

            if ($buffer === null) {
                $buffer = $this->buatBuffer($jadwal, $jamKe);
                continue;
            }

            // --- Syarat 1-4: identitas dan urutan slot ---
            $samaIdentitas  = $buffer['id_mapel']   === $idMapel
                           && $buffer['id_kelas']   === $idKelas
                           && $buffer['id_ruangan'] === $idRuangan;
            $slotBerurutan  = ($jamKe === $buffer['jam_ke_selesai'] + 1);

            // --- Syarat 5: koneksi waktu nyata ---
            // jam_selesai JP terakhir dalam buffer harus sama persis dengan
            // jam_mulai JP baru. Jika ada jeda (istirahat, dll.) maka
            // string-nya akan berbeda dan blok baru dimulai.
            $waktuTersambung = ($buffer['jam_selesai'] === $jamMulaiJP);

            $bisaGabung = $samaIdentitas && $slotBerurutan && $waktuTersambung;

            if ($bisaGabung) {
                // Perpanjang blok yang sedang aktif
                $buffer['jam_ke_selesai'] = $jamKe;
                $buffer['jam_selesai']    = $jadwal->jamPelajaran?->jam_selesai ?? $buffer['jam_selesai'];
                $buffer['jadwal_ids'][]   = $jadwal->id;
            } else {
                // Tutup blok lama, mulai blok baru
                $bloks[]  = $buffer;
                $buffer   = $this->buatBuffer($jadwal, $jamKe);
            }
        }

        if ($buffer !== null) {
            $bloks[] = $buffer;
        }

        return $bloks;
    }


    /**
     * Buat entry buffer blok dari satu baris jadwal.
     */
    protected function buatBuffer(JadwalPelajaran $jadwal, int $jamKe): array
    {
        return [
            'id_mapel'       => $jadwal->id_mapel,
            'id_kelas'       => $jadwal->id_kelas,
            'id_ruangan'     => $jadwal->id_ruangan,
            'jam_ke_mulai'   => $jamKe,
            'jam_ke_selesai' => $jamKe,
            'jam_mulai'      => $jadwal->jamPelajaran?->jam_mulai ?? '00:00',
            'jam_selesai'    => $jadwal->jamPelajaran?->jam_selesai ?? '00:00',
            'mapel'          => $jadwal->mataPelajaran?->nama_mapel
                                    ?? $jadwal->mataPelajaran?->nama
                                    ?? 'Mapel',
            'kelas'          => $jadwal->kelas?->nama_lengkap ?? $jadwal->kelas?->nama_kelas ?? 'Kelas',
            'ruangan'        => $jadwal->ruangan?->nama_ruangan ?? $jadwal->ruangan?->kode ?? '-',
            'jadwal_ids'     => [$jadwal->id],
        ];
    }

    /**
     * Periksa apakah jurnal sudah diisi hari ini untuk salah satu jadwal dalam blok.
     */
    protected function cekJurnalSudahDiisi(array $jadwalIds, Carbon $now): bool
    {
        return Jurnal::whereIn('id_jadwal', $jadwalIds)
            ->whereDate('tanggal', $now->toDateString())
            ->exists();
    }

    /**
     * Sanitasi dan validasi nomor HP guru.
     * Mengembalikan format internasional (628xx) atau null jika tidak valid.
     */
    protected function sanitasiNoHp(User $guru): ?string
    {
        $rawNo = $guru->no_hp;

        if (empty($rawNo)) {
            Log::warning("Nomor HP tidak valid untuk guru: {$guru->name}");
            return null;
        }

        $cleanPhone = preg_replace('/[^0-9]/', '', (string) $rawNo);

        if (str_starts_with($cleanPhone, '0')) {
            $cleanPhone = '62' . substr($cleanPhone, 1);
        }

        if (strlen($cleanPhone) < 10) {
            Log::warning("Nomor HP tidak valid untuk guru: {$guru->name}");
            return null;
        }

        return $cleanPhone;
    }

    /**
     * Cek apakah $waktu berada dalam jendela [$dari, $sampai] (inklusif).
     * Digunakan untuk H-10 dan H-0 (jendela asimetris).
     */
    protected function dalamJendelaAntara(Carbon $waktu, Carbon $dari, Carbon $sampai): bool
    {
        return $waktu->between($dari, $sampai);
    }

    /**
     * Cek apakah $waktu berada dalam +/-$menit dari $target.
     * Digunakan untuk H-5 selesai (jendela simetris).
     */
    protected function dalamJendelaSymetris(Carbon $target, Carbon $waktu, int $menit): bool
    {
        return abs($target->diffInMinutes($waktu, false)) <= $menit;
    }

    /**
     * Nama hari Indonesia dari objek Carbon.
     */
    protected function namaHariIndonesia(Carbon $now): string
    {
        return match ($now->dayOfWeek) {
            Carbon::MONDAY    => 'Senin',
            Carbon::TUESDAY   => 'Selasa',
            Carbon::WEDNESDAY => 'Rabu',
            Carbon::THURSDAY  => 'Kamis',
            Carbon::FRIDAY    => 'Jumat',
            Carbon::SATURDAY  => 'Sabtu',
            Carbon::SUNDAY    => 'Minggu',
            default           => 'Senin',
        };
    }

    /**
     * Format jam "HH:MM:SS" atau "HH:MM" menjadi "HH.MM" (gaya Indonesia).
     */
    protected function fmtJam(string $jam): string
    {
        return substr(str_replace(':', '.', $jam), 0, 5);
    }

    /**
     * Label "Jam Ke-X" atau "Jam Ke-X-Y" untuk sebuah blok.
     */
    protected function labelJamKe(array $blok): string
    {
        if ($blok['jam_ke_mulai'] === $blok['jam_ke_selesai']) {
            return "Jam Ke-{$blok['jam_ke_mulai']}";
        }
        return "Jam Ke-{$blok['jam_ke_mulai']}-{$blok['jam_ke_selesai']}";
    }

    // =========================================================================
    // PESAN
    // =========================================================================

    /**
     * Susun teks pesan Agenda Pagi (06:30 WIB).
     * Tanpa URL/link agar lolos Fonnte Free Package.
     *
     * @param array[] $bloks
     */
    protected function pesanAgendaPagi(User $guru, string $hari, array $bloks, Carbon $now): string
    {
        $nama     = $guru->nama ?: $guru->name;
        $tanggal  = $now->translatedFormat('d F Y');
        $jumlahJP = array_sum(array_map(
            fn ($b) => $b['jam_ke_selesai'] - $b['jam_ke_mulai'] + 1,
            $bloks
        ));

        $baris   = [];
        $baris[] = "Agenda Mengajar Hari Ini\n";
        $baris[] = "Assalamu'alaikum, Bpk/Ibu *{$nama}* 🙏";
        $baris[] = "Berikut jadwal mengajar Anda hari *{$hari}, {$tanggal}*:\n";

        foreach ($bloks as $i => $blok) {
            $no      = $i + 1;
            $mulai   = $this->fmtJam($blok['jam_mulai']);
            $selesai = $this->fmtJam($blok['jam_selesai']);
            $jamKe   = $this->labelJamKe($blok);

            $baris[] = "*{$no}. {$blok['mapel']}*";
            $baris[] = "   Kelas  : *{$blok['kelas']}*";
            $baris[] = "   Ruang  : {$blok['ruangan']}";
            $baris[] = "   Waktu  : {$jamKe} ({$mulai}-{$selesai} WIB)\n";
        }

        $baris[] = "Total: *{$jumlahJP} JP* hari ini.";
        $baris[] = "\nMohon isi jurnal KBM sebelum jam pelajaran berakhir ya.";
        $baris[] = "\n_WebJournal Management System_";

        return implode("\n", $baris);
    }

    /**
     * Pesan pengingat H-10 menit sebelum KBM MULAI.
     * Tanpa URL/link agar lolos Fonnte Free Package.
     */
    protected function pesanPengingatH10(User $guru, array $blok): string
    {
        $nama    = $guru->nama ?: $guru->name;
        $mulai   = $this->fmtJam($blok['jam_mulai']);
        $selesai = $this->fmtJam($blok['jam_selesai']);
        $jamKe   = $this->labelJamKe($blok);

        return implode("\n", [
            "Pengingat KBM — 10 Menit Lagi!\n",
            "Bpk/Ibu *{$nama}*, KBM Anda akan segera dimulai dalam *10 menit*.\n",
            "Mapel  : *{$blok['mapel']}*",
            "Kelas  : *{$blok['kelas']}*",
            "Ruang  : {$blok['ruangan']}",
            "Waktu  : {$jamKe} ({$mulai}-{$selesai} WIB)\n",
            "Silakan bersiap menuju kelas. Selamat mengajar!",
            "\n_WebJournal Management System_",
        ]);
    }

    /**
     * Pesan notifikasi H-0 — tepat saat jam KBM dimulai.
     * Tanpa URL/link agar lolos Fonnte Free Package.
     */
    protected function pesanPengingatH0(User $guru, array $blok): string
    {
        $nama    = $guru->nama ?: $guru->name;
        $mulai   = $this->fmtJam($blok['jam_mulai']);
        $selesai = $this->fmtJam($blok['jam_selesai']);
        $jamKe   = $this->labelJamKe($blok);

        return implode("\n", [
            "Waktunya Masuk Kelas!\n",
            "Bpk/Ibu *{$nama}*, KBM Anda dimulai *sekarang*.\n",
            "Mapel  : *{$blok['mapel']}*",
            "Kelas  : *{$blok['kelas']}*",
            "Ruang  : {$blok['ruangan']}",
            "Waktu  : {$jamKe} ({$mulai}-{$selesai} WIB)\n",
            "Jangan lupa isi Jurnal KBM setelah selesai mengajar.",
            "\n_WebJournal Management System_",
        ]);
    }

    /**
     * Pesan pengingat isian Jurnal H-5 menit sebelum KBM selesai.
     * Tanpa URL/link agar lolos Fonnte Free Package.
     */
    protected function pesanPengingatJurnal(User $guru, array $blok): string
    {
        $nama    = $guru->nama ?: $guru->name;
        $mulai   = $this->fmtJam($blok['jam_mulai']);
        $selesai = $this->fmtJam($blok['jam_selesai']);
        $jamKe   = $this->labelJamKe($blok);

        return implode("\n", [
            "Pengingat Isi Jurnal KBM\n",
            "Bpk/Ibu *{$nama}*, KBM Anda akan segera berakhir.\n",
            "Mapel  : *{$blok['mapel']}*",
            "Kelas  : *{$blok['kelas']}*",
            "Ruang  : {$blok['ruangan']}",
            "Waktu  : {$jamKe} ({$mulai}-{$selesai} WIB)\n",
            "*Jurnal KBM belum terisi!*",
            "Mohon segera buka Portal Guru pada WebJournal untuk mengisi Jurnal KBM sebelum jam pelajaran berakhir.\n",
            "Terima kasih atas dedikasi Anda 🙏",
            "\n_WebJournal Management System_",
        ]);
    }
}
