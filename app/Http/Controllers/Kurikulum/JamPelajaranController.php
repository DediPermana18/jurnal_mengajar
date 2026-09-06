<?php

namespace App\Http\Controllers\Kurikulum;

use App\Http\Controllers\Controller;
use App\Models\JamPelajaran;
use App\Models\JamPulang;
use App\Models\JadwalPelajaran;
use App\Models\TahunAjaran;
use App\Models\AgendaRutin;
use Illuminate\Http\Request;

class JamPelajaranController extends Controller
{
    /**
     * Tampilkan daftar Master Jam Pelajaran Sekolah per Kelompok Hari.
     */
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'Senin-Kamis');
        if (!in_array($tab, ['Senin-Kamis', 'Jumat'])) {
            $tab = 'Senin-Kamis';
        }

        // Pastikan penomoran jam_ke terurut konsisten
        $this->syncJamKe('Senin-Kamis');
        $this->syncJamKe('Jumat');

        $seninKamis = JamPelajaran::where('kategori_hari', 'Senin-Kamis')
            ->orderBy('jam_mulai')
            ->get();

        $jumat = JamPelajaran::where('kategori_hari', 'Jumat')
            ->orderBy('jam_mulai')
            ->get();

        // Pengaturan jam pulang: lookup['kategori_hari|tingkat'] => JamPulang
        $jamPulangSettings = JamPulang::getAllAsLookup();

        // Pengaturan Agenda Rutin / Upacara Sekolah (Senin & Jumat)
        $agendaSenin = AgendaRutin::where('hari', 'Senin')->first();
        $agendaJumat = AgendaRutin::where('hari', 'Jumat')->first();
        $agendaRutin = $agendaSenin ?? AgendaRutin::first();

        // Opsi dropdown jam untuk Pengaturan Upacara (Senin-Kamis) & Pembiasaan (Jumat).
        // Hanya ambil slot KBM yang aktif/valid, urutkan berdasar urutan jam (jam_ke, lalu jam_mulai).
        $jamOptionsSenin = JamPelajaran::where('kategori_hari', 'Senin-Kamis')
            ->where('jenis', 'kbm')
            ->whereNotNull('jam_ke')
            ->orderBy('jam_ke')
            ->orderBy('jam_mulai')
            ->get();

        $jamOptionsJumat = JamPelajaran::where('kategori_hari', 'Jumat')
            ->where('jenis', 'kbm')
            ->whereNotNull('jam_ke')
            ->orderBy('jam_ke')
            ->orderBy('jam_mulai')
            ->get();

        // Hitung max jam_ke KBM tersedia per kategori (untuk dropdown batas jam pulang).
        // Berbasis total slot KBM aktif saat ini agar label "(Jam Terakhir)" selalu akurat.
        $maxJamKeSeninKamis = $jamOptionsSenin->max('jam_ke') ?? 0;
        $maxJamKeJumat      = $jamOptionsJumat->max('jam_ke') ?? 0;

        // Normalisasi: reset pengaturan jam pulang yang melebihi slot KBM yang ada saat ini
        JamPulang::normalizeAgainstMaster([
            'Senin-Kamis' => $maxJamKeSeninKamis,
            'Jumat'       => $maxJamKeJumat,
        ]);

        // Ambil ulang setting setelah normalisasi
        $jamPulangSettings = JamPulang::getAllAsLookup();

        // Auto-suggest jam mulai pada modal tambah: jam_selesai dari slot terakhir per kategori
        $lastSeninKamis = $seninKamis->sortBy('jam_mulai')->last();
        $lastJumat      = $jumat->sortBy('jam_mulai')->last();
        $autoMulai = [
            'Senin-Kamis' => $lastSeninKamis ? substr($lastSeninKamis->jam_selesai, 0, 5) : '07:00',
            'Jumat'       => $lastJumat ? substr($lastJumat->jam_selesai, 0, 5) : '07:00',
        ];

        return view('kurikulum.jam_pelajaran.index', compact(
            'seninKamis', 'jumat', 'tab',
            'jamPulangSettings', 'maxJamKeSeninKamis', 'maxJamKeJumat',
            'agendaRutin', 'agendaSenin', 'agendaJumat',
            'jamOptionsSenin', 'jamOptionsJumat', 'autoMulai'
        ));
    }

    /**
     * Simpan data jam pelajaran baru.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'kategori_hari' => 'required|in:Senin-Kamis,Jumat',
            'jam_mulai'     => 'required|date_format:H:i',
            'jam_selesai'   => 'required|date_format:H:i|after:jam_mulai',
            'jenis'         => 'required|in:kbm,istirahat',
        ]);

        JamPelajaran::create([
            'kategori_hari' => $validated['kategori_hari'],
            'jam_ke'        => $validated['jenis'] === 'istirahat' ? null : 1,
            'jam_mulai'     => $validated['jam_mulai'],
            'jam_selesai'   => $validated['jam_selesai'],
            'jenis'         => $validated['jenis'],
        ]);

        $this->syncJamKe($validated['kategori_hari']);

        return redirect()
            ->route('admin.jam-pelajaran.index', ['tab' => $validated['kategori_hari']])
            ->with('success', "Jam Pelajaran ({$validated['kategori_hari']}) berhasil ditambahkan.");
    }

    /**
     * Update data jam pelajaran.
     */
    public function update(Request $request, JamPelajaran $jamPelajaran)
    {
        $validated = $request->validate([
            'kategori_hari' => 'required|in:Senin-Kamis,Jumat',
            'jam_mulai'     => 'required|date_format:H:i',
            'jam_selesai'   => 'required|date_format:H:i|after:jam_mulai',
            'jenis'         => 'required|in:kbm,istirahat',
        ]);

        $oldHari    = $jamPelajaran->kategori_hari;
        $oldSelesai = $jamPelajaran->jam_selesai;

        $jamPelajaran->update([
            'kategori_hari' => $validated['kategori_hari'],
            'jam_ke'        => $validated['jenis'] === 'istirahat' ? null : $jamPelajaran->jam_ke,
            'jam_mulai'     => $validated['jam_mulai'],
            'jam_selesai'   => $validated['jam_selesai'],
            'jenis'         => $validated['jenis'],
        ]);

        // Auto-shift: geser slot-slot berikutnya bila dicentang
        if ($request->boolean('auto_shift')) {
            $this->shiftFollowingSlots($jamPelajaran, $validated['kategori_hari'], $oldSelesai);
        }

        $this->syncJamKe($validated['kategori_hari']);
        if ($oldHari !== $validated['kategori_hari']) {
            $this->syncJamKe($oldHari);
        }

        $this->normalizeJamPulang();

        return redirect()
            ->route('admin.jam-pelajaran.index', ['tab' => $validated['kategori_hari']])
            ->with('success', "Jam Pelajaran ({$validated['kategori_hari']}) berhasil diperbarui.");
    }

    /**
     * Geser slot-slot berikutnya secara berantai (strict sequential) pada kategori hari yang sama.
     *
     * Setiap slot berikutnya (slot X+1, X+2, dst.) dirapatkan langsung ke jam selesai
     * slot sebelumnya dengan tetap mempertahankan durasi asli masing-masing slot:
     *   - jam_mulai   = jam_selesai slot sebelumnya
     *   - jam_selesai = jam_mulai baru + durasi asli
     */
    private function shiftFollowingSlots(JamPelajaran $edited, string $kategoriHari, string $oldSelesai): void
    {
        // Ambil semua slot setelah slot yang di-edit pada kategori yang sama.
        // Slots berikutnya dimulai dari jam selesai (lama) slot yang di-edit, urutkan berdasarkan jam_mulai (lalu id).
        $nextSlots = JamPelajaran::where('kategori_hari', $kategoriHari)
            ->where('id', '!=', $edited->id)
            ->where('jam_mulai', '>=', $oldSelesai)
            ->orderBy('jam_mulai')
            ->orderBy('id')
            ->get();

        if ($nextSlots->isEmpty()) {
            return;
        }

        // Anchor awal: jam selesai baru dari slot yang di-edit
        $anchor = \Carbon\Carbon::createFromFormat('H:i', substr($edited->jam_selesai, 0, 5));

        foreach ($nextSlots as $slot) {
            // Durasi asli slot ini (tidak boleh berubah)
            $origMulai   = \Carbon\Carbon::createFromFormat('H:i:s', $slot->jam_mulai);
            $origSelesai = \Carbon\Carbon::createFromFormat('H:i:s', $slot->jam_selesai);
            $durasi      = max(1, $origMulai->diffInMinutes($origSelesai));

            // Raparkan: jam_mulai = jam_selesai slot sebelumnya, jam_selesai = mulai + durasi asli
            $newMulai   = $anchor;
            $newSelesai = $newMulai->copy()->addMinutes($durasi);

            $slot->update([
                'jam_mulai'   => $newMulai->format('H:i:s'),
                'jam_selesai' => $newSelesai->format('H:i:s'),
            ]);

            // Lanjutkan rantai
            $anchor = $newSelesai;
        }
    }

    /**
     * Hapus data jam pelajaran.
     */
    public function destroy(JamPelajaran $jamPelajaran)
    {
        $hari = $jamPelajaran->kategori_hari;
        $jamPelajaran->delete();

        $this->syncJamKe($hari);
        $this->normalizeJamPulang();

        return redirect()
            ->route('admin.jam-pelajaran.index', ['tab' => $hari])
            ->with('success', "Jam Pelajaran ({$hari}) berhasil dihapus.");
    }

    /**
     * Hapus seluruh slot jam pelajaran untuk satu kategori hari (Senin-Kamis atau Jumat).
     */
    public function destroyAll(Request $request, string $kategori_hari)
    {
        if (!in_array($kategori_hari, ['Senin-Kamis', 'Jumat'])) {
            return redirect()
                ->route('admin.jam-pelajaran.index', ['tab' => 'Senin-Kamis'])
                ->with('error', 'Kategori hari tidak valid.');
        }

        JamPelajaran::where('kategori_hari', $kategori_hari)->delete();
        $this->normalizeJamPulang();

        return redirect()
            ->route('admin.jam-pelajaran.index', ['tab' => $kategori_hari])
            ->with('success', "Semua slot jam pelajaran ({$kategori_hari}) berhasil dihapus.");
    }

    /**
     * Cek dampak generate preset: slot KBM mana saja yang akan berkurang & apakah
     * slot tersebut masih memuat jadwal pelajaran pada semester/tahun ajaran aktif.
     */
    public function checkGeneratePreset(Request $request)
    {
        $kategori = $request->input('kategori_hari', 'Senin-Kamis');
        if (!in_array($kategori, ['Senin-Kamis', 'Jumat'])) {
            return response()->json(['error' => 'Kategori hari tidak valid.'], 422);
        }

        $jumlahJp = max(1, min(20, (int) $request->input('jumlah_jp', 0)));
        $tahunAktif = TahunAjaran::where('is_active', true)->first();

        // Slot KBM yang akan hilang karena jam_ke-nya melebihi jumlah JP baru
        $affectedKbm = JamPelajaran::where('kategori_hari', $kategori)
            ->where('jenis', 'kbm')
            ->where('jam_ke', '>', $jumlahJp)
            ->orderBy('jam_ke')
            ->get()
            ->map(fn ($slot) => [
                'jam_ke'  => $slot->jam_ke,
                'rentang' => $slot->rentang_waktu,
            ]);

        $affectedJamKe = $affectedKbm->pluck('jam_ke')->all();

        // Jumlah jadwal ter-plot pada slot yang terdampak (semester aktif)
        $plottedCount = 0;
        if ($tahunAktif && count($affectedJamKe) > 0) {
            $plottedCount = JadwalPelajaran::where('id_tahun_ajaran', $tahunAktif->id)
                ->whereHas('jamPelajaran', function ($q) use ($kategori, $affectedJamKe) {
                    $q->where('kategori_hari', $kategori)
                      ->whereIn('jam_ke', $affectedJamKe);
                })
                ->count();
        }

        return response()->json([
            'affected_jam_ke' => $affectedJamKe,
            'slots'           => $affectedKbm->values(),
            'plotted_count'   => $plottedCount,
            'semester'        => $tahunAktif ? "{$tahunAktif->tahun_ajaran} - {$tahunAktif->semester}" : null,
        ]);
    }

    /**
     * Generate preset jam pelajaran secara dinamis (jumlah JP, durasi, dan istirahat bebas).
     *
     * Slot KBM diperbarui IN-PLACE berdasarkan urutan jam_ke (UPSERT), sehingga nilai
     * id pada tabel jam_pelajaran TETAP dan relasi id_jam pada jadwal_pelajaran (plotting
     * jadwal semester) tidak rusak. Hanya slot istirahat yang dihapus & dibuat ulang
     * (istirahat tidak pernah direferensikan oleh jadwal_pelajaran).
     */
    public function generatePreset(Request $request)
    {
        $kategori = $request->input('kategori_hari', 'Senin-Kamis');
        if (!in_array($kategori, ['Senin-Kamis', 'Jumat'])) {
            $kategori = 'Senin-Kamis';
        }

        $durasiJp = max(1, (int) $request->input('durasi_jp', $kategori === 'Jumat' ? 30 : 40));
        $jumlahJp = max(1, min(20, (int) $request->input('jumlah_jp', $kategori === 'Jumat' ? 9 : 13)));
        $jamMulai = $request->input('jam_mulai', '07:00');

        // Normalisasi data istirahat: [{ after_jam, duration }]
        $rawBreaks = is_array($request->input('breaks')) ? $request->input('breaks') : [];
        $breaks = collect($rawBreaks)
            ->map(fn ($b) => [
                'after_jam' => (int) ($b['after_jam'] ?? 0),
                'duration'  => max(1, (int) ($b['duration'] ?? 15)),
            ])
            ->filter(fn ($b) => $b['after_jam'] > 0)
            ->sortBy('after_jam')
            ->values()
            ->all();

        // Slot istirahat tidak pernah di-plot ke jadwal_pelajaran, aman dibuang & dibuat ulang.
        JamPelajaran::where('kategori_hari', $kategori)
            ->where('jenis', 'istirahat')
            ->delete();

        // Ambil slot KBM existing berindex jam_ke agar di-update in-place (ID tetap).
        $existingKbm = JamPelajaran::where('kategori_hari', $kategori)
            ->where('jenis', 'kbm')
            ->get()
            ->keyBy('jam_ke');

        $start = \Carbon\Carbon::createFromFormat('H:i', $jamMulai);
        $istirahatCount = 0;
        $totalSlots = 0;

        for ($j = 1; $j <= $jumlahJp; $j++) {
            // Slot KBM
            $end = $start->copy()->addMinutes($durasiJp);

            if ($existingKbm->has($j)) {
                // Update in-place: pertahankan id agar relasi jadwal_pelajaran.id_jam tetap utuh
                $existingKbm[$j]->update([
                    'jam_ke'      => $j,
                    'jam_mulai'   => $start->format('H:i:s'),
                    'jam_selesai' => $end->format('H:i:s'),
                    'jenis'       => 'kbm',
                ]);
            } else {
                JamPelajaran::create([
                    'kategori_hari' => $kategori,
                    'jam_ke'        => $j,
                    'jam_mulai'     => $start->format('H:i:s'),
                    'jam_selesai'   => $end->format('H:i:s'),
                    'jenis'         => 'kbm',
                ]);
            }
            $start = $end;
            $totalSlots++;

            // Sisipkan istirahat jika ada break setelah jam KBM ini
            $break = collect($breaks)->firstWhere('after_jam', $j);
            if ($break) {
                $istirahatCount++;
                $bEnd = $start->copy()->addMinutes($break['duration']);
                JamPelajaran::create([
                    'kategori_hari' => $kategori,
                    'jam_ke'        => null,
                    'jam_mulai'     => $start->format('H:i:s'),
                    'jam_selesai'   => $bEnd->format('H:i:s'),
                    'jenis'         => 'istirahat',
                ]);
                $start = $bEnd;
                $totalSlots++;
            }
        }

        // Hapus slot KBM sisa yang melebihi jumlah JP baru (sudah ada konfirmasi peringatan di UI)
        JamPelajaran::where('kategori_hari', $kategori)
            ->where('jenis', 'kbm')
            ->where('jam_ke', '>', $jumlahJp)
            ->delete();

        $this->syncJamKe($kategori);
        $this->normalizeJamPulang();

        return redirect()
            ->route('admin.jam-pelajaran.index', ['tab' => $kategori])
            ->with('success', "Preset jam pelajaran {$kategori} berhasil digenerate ({$jumlahJp} JP + {$istirahatCount} istirahat, total {$totalSlots} slot).");
    }

    /**
     * Reset pengaturan jam pulang yang tidak lagi valid terhadap master jam pelajaran.
     */
    private function normalizeJamPulang(): void
    {
        $maxSeninKamis = JamPelajaran::where('kategori_hari', 'Senin-Kamis')
            ->where('jenis', 'kbm')
            ->whereNotNull('jam_ke')
            ->max('jam_ke') ?? 0;

        $maxJumat = JamPelajaran::where('kategori_hari', 'Jumat')
            ->where('jenis', 'kbm')
            ->whereNotNull('jam_ke')
            ->max('jam_ke') ?? 0;

        JamPulang::normalizeAgainstMaster([
            'Senin-Kamis' => $maxSeninKamis,
            'Jumat'       => $maxJumat,
        ]);
    }

    /**
     * Sinkronisasi penomoran jam_ke secara otomatis berurutan berdasarkan jam_mulai.
     */
    private function syncJamKe(string $kategoriHari): void
    {
        $items = JamPelajaran::where('kategori_hari', $kategoriHari)
            ->orderBy('jam_mulai')
            ->get();

        $jamKeCounter = 1;
        foreach ($items as $item) {
            if ($item->jenis === 'istirahat') {
                if ($item->jam_ke !== null) {
                    $item->update(['jam_ke' => null]);
                }
            } else {
                if ($item->jam_ke !== $jamKeCounter) {
                    $item->update(['jam_ke' => $jamKeCounter]);
                }
                $jamKeCounter++;
            }
        }
    }
}
