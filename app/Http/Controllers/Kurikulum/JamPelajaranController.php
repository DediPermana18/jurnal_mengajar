<?php

namespace App\Http\Controllers\Kurikulum;

use App\Http\Controllers\Controller;
use App\Models\AgendaRutin;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\JamPulang;
use App\Models\ShiftPelajaran;
use App\Models\TahunAjaran;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JamPelajaranController extends Controller
{
    /**
     * Tampilkan daftar Master Jam Pelajaran Sekolah per Kelompok Hari & Shift.
     */
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'Senin-Kamis');
        if (! in_array($tab, ['Senin-Kamis', 'Jumat'])) {
            $tab = 'Senin-Kamis';
        }

        // Mode tampilan: 'global' (default — semua kelas memakai slot universal,
        // seluruh UI shift disembunyikan) atau 'shift' (kelola slot per shift).
        $mode = $request->input('mode') === 'shift' ? 'shift' : 'global';

        // Seluruh master shift (untuk sub-tab shift & CRUD modal "Kelola Shift")
        $shifts = ShiftPelajaran::orderBy('jam_mulai')->orderBy('id')->get();

        if ($mode === 'shift') {
            // Shift terpilih pada sub-tab shift: default ke shift pertama bila tidak ditentukan.
            $selectedShiftId = $this->resolveShiftFilter($request) ?? $shifts->first()?->id;
        } else {
            // Mode Global: konsep shift tidak ditampilkan — selalu slot global (tanpa shift).
            $selectedShiftId = null;
        }

        $selectedShift = $selectedShiftId ? $shifts->firstWhere('id', $selectedShiftId) : null;

        // Mode shift ketika belum ada shift terdaftar → tampilkan state kosong,
        // jangan bocorkan slot global ke tampilan shift.
        $shiftModeEmpty = $mode === 'shift' && $shifts->isEmpty();

        // Pastikan penomoran jam_ke terurut konsisten untuk semua hari (per shift)
        foreach (['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'] as $h) {
            $this->syncJamKe($h);
        }

        // Tampilan perwakilan tab "Senin-Kamis" menggunakan hari 'Senin'
        $seninKamis = $shiftModeEmpty
            ? collect()
            : JamPelajaran::where('hari', 'Senin')
                ->ofShift($selectedShiftId)
                ->orderBy('jam_mulai')
                ->get();

        $jumat = $shiftModeEmpty
            ? collect()
            : JamPelajaran::where('hari', 'Jumat')
                ->ofShift($selectedShiftId)
                ->orderBy('jam_mulai')
                ->get();

        // Flag apakah sudah ada data jam pelajaran per kategori (untuk kontrol tombol preset)
        $hasSeninKamis = $seninKamis->isNotEmpty();
        $hasJumat = $jumat->isNotEmpty();

        // Pengaturan jam pulang: lookup['shift_id|kategori_hari|tingkat'] => JamPulang
        $jamPulangSettings = JamPulang::getAllAsLookup();

        // Peta max jam_ke KBM per shift per kategori (untuk dropdown batas jam pulang)
        $maxByShift = JamPulang::defaultMaxByShift();

        $selectedShiftKey = (string) ($selectedShiftId ?? 0);
        $maxJamKeSeninKamis = $maxByShift[$selectedShiftKey]['Senin-Kamis'] ?? 0;
        $maxJamKeJumat = $maxByShift[$selectedShiftKey]['Jumat'] ?? 0;

        // Normalisasi: reset pengaturan jam pulang yang melebihi slot KBM yang ada (per shift)
        JamPulang::normalizeAgainstMaster($maxByShift);

        // Ambil ulang setting setelah normalisasi
        $jamPulangSettings = JamPulang::getAllAsLookup();

        // Pengaturan Agenda Rutin / Upacara Sekolah (Senin & Jumat)
        $agendaSenin = AgendaRutin::where('hari', 'Senin')->first();
        $agendaJumat = AgendaRutin::where('hari', 'Jumat')->first();
        $agendaRutin = $agendaSenin ?? AgendaRutin::first();

        // Opsi dropdown jam untuk Pengaturan Upacara (Senin-Kamis) & Pembiasaan (Jumat).
        $jamOptionsSenin = JamPelajaran::where('hari', 'Senin')
            ->where('jenis', 'kbm')
            ->whereNotNull('jam_ke')
            ->orderBy('jam_ke')
            ->orderBy('jam_mulai')
            ->get();

        $jamOptionsJumat = JamPelajaran::where('hari', 'Jumat')
            ->where('jenis', 'kbm')
            ->whereNotNull('jam_ke')
            ->orderBy('jam_ke')
            ->orderBy('jam_mulai')
            ->get();

        // Auto-suggest jam mulai pada modal tambah: jam_selesai dari slot terakhir per kategori
        $lastSeninKamis = $seninKamis->sortBy('jam_mulai')->last();
        $lastJumat = $jumat->sortBy('jam_mulai')->last();
        $autoMulai = [
            'Senin-Kamis' => $lastSeninKamis ? substr($lastSeninKamis->jam_selesai, 0, 5) : '07:00',
            'Jumat' => $lastJumat ? substr($lastJumat->jam_selesai, 0, 5) : '07:00',
        ];

        return view('admin.jam_pelajaran.index', compact(
            'seninKamis', 'jumat', 'tab', 'mode', 'shiftModeEmpty',
            'shifts', 'selectedShift', 'selectedShiftId',
            'jamPulangSettings', 'maxJamKeSeninKamis', 'maxJamKeJumat', 'maxByShift',
            'agendaRutin', 'agendaSenin', 'agendaJumat',
            'jamOptionsSenin', 'jamOptionsJumat', 'autoMulai',
            'hasSeninKamis', 'hasJumat'
        ));
    }

    /**
     * Resolve parameter shift dari request (null = Global).
     */
    private function resolveShiftFilter(Request $request): ?int
    {
        $raw = $request->input('shift');

        if ($raw === null || $raw === '' || in_array((string) $raw, ['global', '0'], true)) {
            return null;
        }

        if (! is_numeric($raw) || (int) $raw <= 0) {
            return null;
        }

        $id = (int) $raw;

        return ShiftPelajaran::whereKey($id)->exists() ? $id : null;
    }

    /**
     * Pertahankan mode tampilan "shift" pada redirect kembali ke index,
     * sehingga admin tidak terlempar ke Mode Global setelah menyimpan data.
     */
    private function applyRedirectMode(Request $request, array $redirect): array
    {
        if ($request->input('mode') === 'shift') {
            $redirect['mode'] = 'shift';
        }

        return $redirect;
    }

    /**
     * Simpan data jam pelajaran baru (dapat diikat ke sebuah shift).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'kategori_hari' => 'required|in:Senin-Kamis,Jumat',
            'shift_id' => 'nullable|integer|exists:shift_pelajaran,id',
            'jam_mulai' => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i|after:jam_mulai',
            'jenis' => 'required|in:kbm,istirahat',
        ]);

        $shiftId = ! empty($validated['shift_id']) ? (int) $validated['shift_id'] : null;

        $daysToCreate = ($validated['kategori_hari'] === 'Senin-Kamis')
            ? ['Senin', 'Selasa', 'Rabu', 'Kamis']
            : ['Jumat'];

        foreach ($daysToCreate as $d) {
            JamPelajaran::create([
                'hari' => $d,
                'kategori_hari' => $validated['kategori_hari'],
                'shift_id' => $shiftId,
                'jam_ke' => $validated['jenis'] === 'istirahat' ? null : 1,
                'jam_mulai' => $validated['jam_mulai'],
                'jam_selesai' => $validated['jam_selesai'],
                'jenis' => $validated['jenis'],
            ]);
            $this->syncJamKe($d);
        }

        $redirect = ['tab' => $validated['kategori_hari']];
        if ($shiftId) {
            $redirect['shift'] = $shiftId;
        }
        $redirect = $this->applyRedirectMode($request, $redirect);

        return redirect()
            ->route('admin.jam-pelajaran.index', $redirect)
            ->with('success', "Jam Pelajaran ({$validated['kategori_hari']}) berhasil ditambahkan.");
    }

    /**
     * Update data jam pelajaran.
     */
    public function update(Request $request, JamPelajaran $jamPelajaran)
    {
        $validated = $request->validate([
            'kategori_hari' => 'required|in:Senin-Kamis,Jumat',
            'shift_id' => 'nullable|integer|exists:shift_pelajaran,id',
            'jam_mulai' => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i|after:jam_mulai',
            'jenis' => 'required|in:kbm,istirahat',
        ]);

        // Guard: hanya IT/QA yang dapat mengubah slot jam data testing.
        $this->authorizeTestingMutation($jamPelajaran);

        $oldSelesai = $jamPelajaran->jam_selesai;
        $lookupShiftId = $jamPelajaran->shift_id; // shift lama untuk pencarian target
        $newShiftId = ! empty($validated['shift_id']) ? (int) $validated['shift_id'] : null;

        $targetDays = ($validated['kategori_hari'] === 'Senin-Kamis')
            ? ['Senin', 'Selasa', 'Rabu', 'Kamis']
            : ['Jumat'];

        foreach ($targetDays as $d) {
            $targetSlot = JamPelajaran::where('hari', $d)
                ->ofShift($lookupShiftId)
                ->when($jamPelajaran->jenis === 'kbm' && $jamPelajaran->jam_ke !== null, fn ($q) => $q->where('jam_ke', $jamPelajaran->jam_ke))
                ->when($jamPelajaran->jenis === 'istirahat', fn ($q) => $q->where('jam_mulai', $jamPelajaran->jam_mulai)->where('jenis', 'istirahat'))
                ->first();

            if (! $targetSlot && $d === $jamPelajaran->hari) {
                $targetSlot = $jamPelajaran;
            }

            if ($targetSlot) {
                $targetSlot->update([
                    'hari' => $d,
                    'kategori_hari' => $validated['kategori_hari'],
                    'shift_id' => $newShiftId,
                    'jam_ke' => $validated['jenis'] === 'istirahat' ? null : $targetSlot->jam_ke,
                    'jam_mulai' => $validated['jam_mulai'],
                    'jam_selesai' => $validated['jam_selesai'],
                    'jenis' => $validated['jenis'],
                ]);

                if ($request->boolean('auto_shift')) {
                    $this->shiftFollowingSlots($targetSlot, $d, $oldSelesai);
                }

                $this->syncJamKe($d);
            }
        }

        $this->normalizeJamPulang();

        $redirect = ['tab' => $validated['kategori_hari']];
        if ($newShiftId) {
            $redirect['shift'] = $newShiftId;
        }
        $redirect = $this->applyRedirectMode($request, $redirect);

        return redirect()
            ->route('admin.jam-pelajaran.index', $redirect)
            ->with('success', "Jam Pelajaran ({$validated['kategori_hari']}) berhasil diperbarui.");
    }

    /**
     * Geser slot-slot berikutnya secara berantai (strict sequential) pada kategori hari yang sama
     * — hanya menyentuh slot milik shift yang sama dengan slot yang diedit.
     */
    private function shiftFollowingSlots(JamPelajaran $edited, string $hariOrGroup, string $oldSelesai): void
    {
        $days = ($hariOrGroup === 'Senin-Kamis')
            ? ['Senin', 'Selasa', 'Rabu', 'Kamis']
            : [$hariOrGroup];

        foreach ($days as $h) {
            $nextSlots = JamPelajaran::where('hari', $h)
                ->ofShift($edited->shift_id)
                ->where('id', '!=', $edited->id)
                ->where('jam_mulai', '>=', $oldSelesai)
                ->orderBy('jam_mulai')
                ->orderBy('id')
                ->get();

            if ($nextSlots->isEmpty()) {
                continue;
            }

            $anchor = Carbon::createFromFormat('H:i', substr($edited->jam_selesai, 0, 5));

            foreach ($nextSlots as $slot) {
                $origMulai = Carbon::createFromFormat('H:i:s', $slot->jam_mulai);
                $origSelesai = Carbon::createFromFormat('H:i:s', $slot->jam_selesai);
                $durasi = max(1, $origMulai->diffInMinutes($origSelesai));

                $newMulai = $anchor;
                $newSelesai = $newMulai->copy()->addMinutes($durasi);

                $slot->update([
                    'jam_mulai' => $newMulai->format('H:i:s'),
                    'jam_selesai' => $newSelesai->format('H:i:s'),
                ]);

                $anchor = $newSelesai;
            }
        }
    }

    /**
     * Hapus data jam pelajaran (per shift slot miliknya).
     */
    public function destroy(JamPelajaran $jamPelajaran)
    {
        // Guard: hanya IT/QA yang dapat menghapus slot jam data testing.
        $this->authorizeTestingMutation($jamPelajaran);

        $targetDays = in_array($jamPelajaran->hari, ['Senin', 'Selasa', 'Rabu', 'Kamis'])
            ? ['Senin', 'Selasa', 'Rabu', 'Kamis']
            : [$jamPelajaran->hari];

        $tab = in_array($jamPelajaran->hari, ['Senin', 'Selasa', 'Rabu', 'Kamis']) ? 'Senin-Kamis' : 'Jumat';
        $shiftId = $jamPelajaran->shift_id;

        foreach ($targetDays as $d) {
            $slotsToDelete = JamPelajaran::where('hari', $d)
                ->ofShift($shiftId)
                ->when($jamPelajaran->jenis === 'kbm' && $jamPelajaran->jam_ke !== null, fn ($q) => $q->where('jam_ke', $jamPelajaran->jam_ke))
                ->when($jamPelajaran->jenis === 'istirahat', fn ($q) => $q->where('jam_mulai', $jamPelajaran->jam_mulai)->where('jenis', 'istirahat'))
                ->get();

            foreach ($slotsToDelete as $s) {
                $s->delete();
            }
            $this->syncJamKe($d);
        }

        $this->normalizeJamPulang();

        $redirect = ['tab' => $tab];
        if ($shiftId) {
            $redirect['shift'] = $shiftId;
        }
        $redirect = $this->applyRedirectMode($request, $redirect);

        return redirect()
            ->route('admin.jam-pelajaran.index', $redirect)
            ->with('success', "Jam Pelajaran ({$tab}) berhasil dihapus.");
    }

    /**
     * Hapus seluruh slot jam pelajaran untuk satu kategori hari & shift terpilih.
     */
    public function destroyAll(Request $request, string $kategori_hari)
    {
        if (! in_array($kategori_hari, ['Senin-Kamis', 'Jumat'])) {
            $redirect = ['tab' => 'Senin-Kamis'];
            $redirect = $this->applyRedirectMode($request, $redirect);

            return redirect()
                ->route('admin.jam-pelajaran.index', $redirect)
                ->with('error', 'Kategori hari tidak valid.');
        }

        $shiftId = $this->resolveShiftFilter($request);

        $targetDays = ($kategori_hari === 'Senin-Kamis')
            ? ['Senin', 'Selasa', 'Rabu', 'Kamis']
            : ['Jumat'];

        // Guard: hapus massal tidak boleh menyentuh data testing (kecuali IT/QA).
        $baseQuery = JamPelajaran::whereIn('hari', $targetDays)->ofShift($shiftId);
        $this->authorizeTestingBatch((clone $baseQuery)->where('is_testing_data', true));

        $baseQuery->delete();
        $this->normalizeJamPulang();

        $redirect = ['tab' => $kategori_hari];
        if ($shiftId) {
            $redirect['shift'] = $shiftId;
        }
        $redirect = $this->applyRedirectMode($request, $redirect);

        return redirect()
            ->route('admin.jam-pelajaran.index', $redirect)
            ->with('success', "Semua slot jam pelajaran ({$kategori_hari}) berhasil dihapus.");
    }

    /**
     * Bulk update durasi jam pelajaran (Edit Masal).
     */
    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'updates' => 'required|array|min:1',
            'updates.*.id' => 'required|integer|exists:jam_pelajaran,id',
            'updates.*.durasi' => 'required|integer|min:1|max:600',
        ], [
            'updates.required' => 'Tidak ada slot jam pelajaran yang dipilih.',
            'updates.*.id.exists' => 'Ada slot jam pelajaran yang tidak valid.',
            'updates.*.durasi.min' => 'Durasi minimal 1 menit.',
            'updates.*.durasi.max' => 'Durasi maksimal 600 menit.',
        ]);

        try {
            $this->authorizeTestingBatch(
                JamPelajaran::whereIn('id', collect($validated['updates'])->pluck('id'))
                    ->where('is_testing_data', true)
            );

            DB::transaction(function () use ($validated) {
                foreach ($validated['updates'] as $item) {
                    $jam = JamPelajaran::find($item['id']);
                    if (! $jam) {
                        continue;
                    }

                    $targetDays = in_array($jam->hari, ['Senin', 'Selasa', 'Rabu', 'Kamis'])
                        ? ['Senin', 'Selasa', 'Rabu', 'Kamis']
                        : [$jam->hari];

                    foreach ($targetDays as $d) {
                        $targetSlot = JamPelajaran::where('hari', $d)
                            ->ofShift($jam->shift_id)
                            ->when($jam->jenis === 'kbm' && $jam->jam_ke !== null, fn ($q) => $q->where('jam_ke', $jam->jam_ke))
                            ->when($jam->jenis === 'istirahat', fn ($q) => $q->where('jam_mulai', $jam->jam_mulai)->where('jenis', 'istirahat'))
                            ->first();

                        if ($targetSlot) {
                            $mulai = Carbon::createFromFormat('H:i:s', $targetSlot->jam_mulai);
                            $selesai = $mulai->copy()->addMinutes((int) $item['durasi']);
                            $targetSlot->update([
                                'jam_selesai' => $selesai->format('H:i:s'),
                            ]);
                        }
                    }
                }
            });
        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->with('error', 'Gagal memperbarui durasi: '.$e->getMessage());
        }

        $firstRecord = JamPelajaran::find($validated['updates'][0]['id']);
        $hariGroup = $firstRecord ? (in_array($firstRecord->hari, ['Senin', 'Selasa', 'Rabu', 'Kamis']) ? 'Senin-Kamis' : 'Jumat') : 'Senin-Kamis';

        $this->recalculateSchedule($hariGroup);
        $this->syncJamKe($hariGroup);
        $this->normalizeJamPulang();

        $count = count($validated['updates']);

        return redirect()
            ->back()
            ->with('success', "Durasi {$count} slot jam pelajaran berhasil diperbarui.");
    }

    /**
     * Cek dampak generate preset.
     */
    public function checkGeneratePreset(Request $request)
    {
        $kategori = $request->input('kategori_hari', 'Senin-Kamis');
        if (! in_array($kategori, ['Senin-Kamis', 'Jumat'])) {
            return response()->json(['error' => 'Kategori hari tidak valid.'], 422);
        }

        $shiftId = $this->resolveShiftFilter($request);

        $targetDays = ($kategori === 'Senin-Kamis') ? ['Senin', 'Selasa', 'Rabu', 'Kamis'] : ['Jumat'];
        $jumlahJp = max(1, min(20, (int) $request->input('jumlah_jp', 0)));
        $tahunAktif = TahunAjaran::where('is_active', true)->first();

        $affectedKbm = JamPelajaran::whereIn('hari', $targetDays)
            ->ofShift($shiftId)
            ->where('jenis', 'kbm')
            ->where('jam_ke', '>', $jumlahJp)
            ->orderBy('jam_ke')
            ->get()
            ->map(fn ($slot) => [
                'jam_ke' => $slot->jam_ke,
                'rentang' => $slot->rentang_waktu,
            ]);

        $affectedJamKe = $affectedKbm->pluck('jam_ke')->unique()->values()->all();

        $plottedCount = 0;
        if ($tahunAktif && count($affectedJamKe) > 0) {
            $plottedCount = JadwalPelajaran::where('id_tahun_ajaran', $tahunAktif->id)
                ->whereHas('jamPelajaran', function ($q) use ($targetDays, $affectedJamKe, $shiftId) {
                    $q->ofShift($shiftId)
                        ->whereIn('hari', $targetDays)
                        ->whereIn('jam_ke', $affectedJamKe);
                })
                ->count();
        }

        return response()->json([
            'affected_jam_ke' => $affectedJamKe,
            'slots' => $affectedKbm->unique('jam_ke')->values(),
            'plotted_count' => $plottedCount,
            'semester' => $tahunAktif ? "{$tahunAktif->tahun_ajaran} - {$tahunAktif->semester}" : null,
        ]);
    }

    /**
     * Generate preset jam pelajaran secara dinamis (per shift terpilih).
     */
    public function generatePreset(Request $request)
    {
        $kategori = $request->input('kategori_hari', 'Senin-Kamis');
        if (! in_array($kategori, ['Senin-Kamis', 'Jumat'])) {
            $kategori = 'Senin-Kamis';
        }

        $shiftId = $this->resolveShiftFilter($request);

        $targetDays = ($kategori === 'Senin-Kamis') ? ['Senin', 'Selasa', 'Rabu', 'Kamis'] : ['Jumat'];

        $existingCount = JamPelajaran::whereIn('hari', $targetDays)
            ->ofShift($shiftId)
            ->count();

        if ($existingCount > 0) {
            $wantsJson = $request->expectsJson() || $request->ajax();
            if ($wantsJson) {
                return response()->json([
                    'error' => true,
                    'message' => "Preset tidak dapat dibuat karena jam pelajaran ({$kategori}) sudah terdaftar. Hapus semua data terlebih dahulu jika ingin reset.",
                    'existing_count' => $existingCount,
                ], 422);
            }

            return redirect()
                ->back()
                ->with('error', 'Preset tidak dapat dibuat karena jam pelajaran sudah terdaftar. Hapus semua data terlebih dahulu jika ingin reset.');
        }

        $durasiJp = max(1, (int) $request->input('durasi_jp', $kategori === 'Jumat' ? 30 : 40));
        $jumlahJp = max(1, min(20, (int) $request->input('jumlah_jp', $kategori === 'Jumat' ? 9 : 13)));
        $jamMulai = $request->input('jam_mulai', '07:00');

        $rawBreaks = is_array($request->input('breaks')) ? $request->input('breaks') : [];
        $breaks = collect($rawBreaks)
            ->map(fn ($b) => [
                'after_jam' => (int) ($b['after_jam'] ?? 0),
                'duration' => max(1, (int) ($b['duration'] ?? 15)),
            ])
            ->filter(fn ($b) => $b['after_jam'] > 0)
            ->sortBy('after_jam')
            ->values()
            ->all();

        foreach ($targetDays as $d) {
            JamPelajaran::where('hari', $d)
                ->ofShift($shiftId)
                ->where('jenis', 'istirahat')
                ->delete();

            $existingKbm = JamPelajaran::where('hari', $d)
                ->ofShift($shiftId)
                ->where('jenis', 'kbm')
                ->get()
                ->keyBy('jam_ke');

            $start = Carbon::createFromFormat('H:i', $jamMulai);

            for ($j = 1; $j <= $jumlahJp; $j++) {
                $end = $start->copy()->addMinutes($durasiJp);

                if ($existingKbm->has($j)) {
                    $existingKbm[$j]->update([
                        'hari' => $d,
                        'kategori_hari' => $kategori,
                        'shift_id' => $shiftId,
                        'jam_ke' => $j,
                        'jam_mulai' => $start->format('H:i:s'),
                        'jam_selesai' => $end->format('H:i:s'),
                        'jenis' => 'kbm',
                    ]);
                } else {
                    JamPelajaran::create([
                        'hari' => $d,
                        'kategori_hari' => $kategori,
                        'shift_id' => $shiftId,
                        'jam_ke' => $j,
                        'jam_mulai' => $start->format('H:i:s'),
                        'jam_selesai' => $end->format('H:i:s'),
                        'jenis' => 'kbm',
                    ]);
                }
                $start = $end;

                $break = collect($breaks)->firstWhere('after_jam', $j);
                if ($break) {
                    $bEnd = $start->copy()->addMinutes($break['duration']);
                    JamPelajaran::create([
                        'hari' => $d,
                        'kategori_hari' => $kategori,
                        'shift_id' => $shiftId,
                        'jam_ke' => null,
                        'jam_mulai' => $start->format('H:i:s'),
                        'jam_selesai' => $bEnd->format('H:i:s'),
                        'jenis' => 'istirahat',
                    ]);
                    $start = $bEnd;
                }
            }

            JamPelajaran::where('hari', $d)
                ->ofShift($shiftId)
                ->where('jenis', 'kbm')
                ->where('jam_ke', '>', $jumlahJp)
                ->delete();

            $this->syncJamKe($d);
        }

        $this->normalizeJamPulang();

        $redirect = ['tab' => $kategori];
        if ($shiftId) {
            $redirect['shift'] = $shiftId;
        }
        $redirect = $this->applyRedirectMode($request, $redirect);

        return redirect()
            ->route('admin.jam-pelajaran.index', $redirect)
            ->with('success', "Preset jam pelajaran {$kategori} berhasil digenerate.");
    }

    /**
     * Reset pengaturan jam pulang yang tidak lagi valid terhadap master jam pelajaran (per shift).
     */
    private function normalizeJamPulang(): void
    {
        JamPulang::normalizeAgainstMaster();
    }

    /**
     * Sinkronisasi penomoran jam_ke secara otomatis berurutan berdasarkan jam_mulai,
     * dihitung terpisah per shift (Global + setiap shift) pada hari yang sama.
     */
    private function syncJamKe(string $hariOrGroup): void
    {
        $days = ($hariOrGroup === 'Senin-Kamis')
            ? ['Senin', 'Selasa', 'Rabu', 'Kamis']
            : [$hariOrGroup];

        foreach ($days as $h) {
            $groups = JamPelajaran::where('hari', $h)
                ->orderBy('jam_mulai')
                ->orderBy('id')
                ->get()
                ->groupBy(fn ($item) => (string) ($item->shift_id ?? 0));

            foreach ($groups as $group) {
                $jamKeCounter = 1;
                foreach ($group as $item) {
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
    }

    /**
     * Hitung ulang timeline (jam_mulai & jam_selesai) secara sekuensial,
     * dihitung terpisah per shift (Global + setiap shift) pada hari yang sama.
     */
    private function recalculateSchedule(string $hariOrGroup): void
    {
        $days = ($hariOrGroup === 'Senin-Kamis')
            ? ['Senin', 'Selasa', 'Rabu', 'Kamis']
            : [$hariOrGroup];

        foreach ($days as $h) {
            $groups = JamPelajaran::where('hari', $h)
                ->orderBy('jam_mulai')
                ->orderBy('id')
                ->get()
                ->groupBy(fn ($slot) => (string) ($slot->shift_id ?? 0));

            foreach ($groups as $group) {
                if ($group->isEmpty()) {
                    continue;
                }

                $anchor = Carbon::createFromFormat('H:i:s', $group->first()->jam_mulai);

                foreach ($group as $slot) {
                    $currentMulai = Carbon::createFromFormat('H:i:s', $slot->jam_mulai);
                    $currentSelesai = Carbon::createFromFormat('H:i:s', $slot->jam_selesai);
                    $durasi = max(1, $currentMulai->diffInMinutes($currentSelesai));

                    $newMulai = $anchor;
                    $newSelesai = $anchor->copy()->addMinutes($durasi);

                    $mulaiDb = $slot->jam_mulai;
                    $selesaiDb = $slot->jam_selesai;
                    $newMulaiFmt = $newMulai->format('H:i:s');
                    $newSelesaiFmt = $newSelesai->format('H:i:s');

                    if ($mulaiDb !== $newMulaiFmt || $selesaiDb !== $newSelesaiFmt) {
                        $slot->update([
                            'jam_mulai' => $newMulaiFmt,
                            'jam_selesai' => $newSelesaiFmt,
                        ]);
                    }

                    $anchor = $newSelesai->copy();
                }
            }
        }
    }
}