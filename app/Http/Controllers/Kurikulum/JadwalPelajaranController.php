<?php

namespace App\Http\Controllers\Kurikulum;

use App\Exports\JadwalPelajaranExport;
use App\Http\Controllers\Controller;
use App\Imports\SiswaImport;
use App\Models\AgendaRutin;
use App\Models\AppSetting;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\JamPulang;
use App\Models\Kelas;
use App\Models\Jurusan;
use App\Models\MataPelajaran;
use App\Models\PengaturanJadwal;
use App\Models\Ruangan;
use App\Models\Scopes\ActiveTahunAjaranScope;
use App\Models\ShiftPelajaran;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Support\JamSlotResolver;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Excel as ExcelFormat;;
use Maatwebsite\Excel\Facades\Excel;

class JadwalPelajaranController extends Controller
{
    /**
     * Tampilkan halaman Plotting Jadwal Kelas.
     */
    public function index(Request $request)
    {
        // 1. Daftar data master untuk filter & dropdown form
        $kelasList = Kelas::with('jurusan')
            ->orderBy('tingkat')
            ->orderBy('nama_kelas')
            ->get();

        $mapelList = MataPelajaran::orderBy('nama_mapel')->get();

        $ruanganList = Ruangan::orderBy('kode_ruangan')->get();

        $guruList = User::where('role', User::ROLE_GURU)
            ->orderBy('nama')
            ->get();

        // 1b. Konteks Tahun Ajaran & Semester (dari URL/query, session, atau default aktif).
        //     Dipakai untuk mem-filter plotting jadwal yang ditampilkan & yang akan di-plot/di-record.
        $tahunAjaranList = TahunAjaran::forCurrentContext()->orderByDesc('id')->get();
        $semesterList = ['Ganjil', 'Genap'];
        $tahunOptions = $tahunAjaranList
            ->pluck('tahun_ajaran')
            ->unique()
            ->sortDesc()
            ->values();

        $tahunAktif = $this->resolveTahunAjaranContext($request);

        // 2. Filter yang aktif
        $hariList = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];
        $selectedHari = $request->query('hari', 'Senin');
        if (! in_array($selectedHari, $hariList)) {
            $selectedHari = 'Senin';
        }

        $idKelas = $request->get('id_kelas');
        $selectedKelas = $idKelas ? $kelasList->firstWhere('id', $idKelas) : null;

        // 3. Tentukan kategori slot jam (Senin–Kamis vs Jumat) & tingkat kelas
        $kategoriHari = ($selectedHari === 'Jumat') ? 'Jumat' : 'Senin-Kamis';
        $tingkatKelas = $selectedKelas ? match (strtoupper(trim($selectedKelas->tingkat))) {
            'X' => '10', 'XI' => '11', 'XII' => '12', default => $selectedKelas->tingkat
        } : '10';

        // 4. Deteksi shift EFEKTIF kelas yang sedang di-plot:
        //    - Ikatan langsung kelas (kelas.shift_id) menang.
        //    - Bila kelas tidak terikat shift, tetapi konteks Tahun Ajaran ber-mode
        //      Multi-Shift, cari shift AKTIF yang grade_levels-nya mencakup tingkatan
        //      kelas tsb (mis. tingkat XI -> Shift 2, meskipun kelas belum di-set shift).
        //    - Tidak ditemukan / mode Global => null (slot Global).
        $plotShift = $selectedKelas ? $this->shiftUntukKelas($selectedKelas, $tahunAktif) : null;
        $plotShiftId = $plotShift?->id;

        // Ambil master jam pelajaran sekolah: HANYA slot MURNI milik shift efektif
        // kelas terpilih (shift_id = shift kelas). Slots Global (shift_id NULL) atau
        // milik shift LAIN TIDAK disertakan — mencegah pencampuran shift berganda.
        // Kelas tanpa shift efektif => slot Global (shift_id NULL).
        //
        // Urutan kolom matriks TIDAK memakai `id` (yang bergeser tiap master jam
        // dibuat ulang) dan TIDAK memakai `jam_ke` lebih dulu (slot Istirahat
        // ber-`jam_ke` NULL sehingga akan terseret ke paling bawah), melainkan
        // KRONOLOGIS berdasarkan `jam_mulai` — lihat
        // JamPelajaran::scopeUrutkanWaktu().
        $jamPelajaranList = JamPelajaran::slotPerHari(
            $selectedHari,
            $plotShiftId,
            $tahunAktif?->id,
            (bool) ($tahunAktif?->is_active ?? false)
        );

        // 5. Ambil data jadwal pelajaran yang sudah di-plot.
        //    Auto-heal: `id_jam` yang menunjuk master jam lama/soft-deleted
        //    dipetakan ULANG ke slot aktif berdasarkan (hari, jam_ke, shift)
        //    sehingga jadwal tetap tampil di kolom yang benar.
        $jadwalList = collect();
        $jadwalGantung = collect();
        if ($selectedKelas) {
            // Lookup kolom slot jam (jam_ke / jam_mulai / jam_valid) lalu urut
            // kronologis berdasarkan jam_mulai — bukan id_jam, dan bukan
            // jam_ke lebih dulu (slot istirahat ber-jam_ke NULL).
            $rawJadwals = JadwalPelajaran::with(['mataPelajaran', 'guru', 'ruangan'])
                ->withSlot()
                ->where('id_kelas', $selectedKelas->id)
                ->where('hari', $selectedHari)
                ->when($tahunAktif, fn ($q) => $q->where('id_tahun_ajaran', $tahunAktif->id))
                ->urutkanSlot()
                ->get();

            $petakan = JamSlotResolver::petakanJadwal(
                $rawJadwals,
                $selectedHari,
                $plotShiftId,
                $tahunAktif?->id,
                (bool) ($tahunAktif?->is_active ?? false)
            );

            $jadwalList = $petakan['mapped'];
            $jadwalGantung = $petakan['orphans'];
        }

        // 6. Ambil status sakelar Senin Tanpa Upacara (dipakai widget Sakelar Mode Khusus di view)
        $pengaturanJadwal = PengaturanJadwal::getSetting();

        // Total slot jam (kategori hari terpilih) untuk badge ringkasan di header matriks.
        $totalSlot = $jamPelajaranList->count();

        // 7. Ambil batas jam pulang untuk kelas & hari yang dipilih (per shift efektif kelas)
        $maxJamKe = null;
        if ($selectedKelas) {
            $maxJamKe = JamPulang::getMaxJamKe(
                $kategoriHari,
                strtoupper(trim($selectedKelas->tingkat)),
                $plotShiftId ?? 0
            );
        }

        // 8. Ambil agenda rutin / upacara aktif untuk hari terpilih, TERISOLASI
        // per shift efektif kelas yang sedang di-plot (Global: 0).
        $agendaRutinAktif = AgendaRutin::where('hari', $selectedHari)
            ->where('is_active', true)
            ->ofShift($plotShiftId ?? 0)
            ->get()
            ->keyBy('jam_ke');

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'selected_kelas_id' => $selectedKelas?->id,
                'selected_hari' => $selectedHari,
                'tahun_aktif' => $tahunAktif,
                'jadwal' => $jadwalList->map(fn ($j) => $this->ringkasJadwalJson($j))->values(),
                'jadwal_gantung' => $jadwalGantung->map(fn ($j) => $this->ringkasJadwalJson($j))->values(),
            ]);
        }

        return view('admin.jadwal.index', compact(
            'kelasList',
            'mapelList',
            'ruanganList',
            'guruList',
            'tahunAktif',
            'tahunAjaranList',
            'tahunOptions',
            'semesterList',
            'hariList',
            'selectedHari',
            'selectedKelas',
            'plotShift',
            'jamPelajaranList',
            'jadwalList',
            'jadwalGantung',
            'totalSlot',
            'maxJamKe',
            'agendaRutinAktif',
            'pengaturanJadwal'
        ));
    }

    /**
     * Bentuk JSON ringkas untuk satu baris jadwal (dipakai respons JSON matriks).
     * Menyertakan `jam_ke` hasil JOIN supaya frontend bisa memetakan slot
     * tanpa bergantung pada `id_jam`.
     */
    private function ringkasJadwalJson(JadwalPelajaran $j): array
    {
        return [
            'id' => $j->id,
            'id_kelas' => $j->id_kelas,
            'id_jam' => $j->id_jam,
            'jam_ke' => $j->jam_ke,
            'jam_mulai' => $j->jam_mulai,
            'jam_valid' => (int) ($j->jam_valid ?? 0) === 1,
            'hari' => $j->hari,
            'group_id' => $j->group_id,
            'id_mapel' => $j->id_mapel,
            'id_guru' => $j->id_guru,
            'id_ruangan' => $j->id_ruangan,
            'mata_pelajaran' => $j->mataPelajaran?->nama_mapel,
            'guru' => $j->guru?->nama,
            'ruangan' => $j->ruangan?->kode_ruangan,
            'is_testing_data' => (bool) $j->is_testing_data,
        ];
    }

    /**
     * Unduh seluruh data Jadwal Pelajaran aktif sebagai XLSX / CSV.
     * Format kolom: Kelas, Hari, Jam, MataPelajaran, Guru, Ruang.
     */
    public function export(Request $request)
    {
        $testing = SiswaImport::isImportTestingContext(request()->user()) ? 1 : 0;
        $tahunAktif = $this->resolveTahunAjaranContext($request);
        $format = strtolower((string) $request->input('format', 'xlsx'));
        $filename = 'jadwal_pelajaran_'.date('Y-m-d_His');

        $export = new JadwalPelajaranExport($tahunAktif?->id, $testing);

        if ($format === 'csv') {
            return Excel::download($export, $filename.'.csv', ExcelFormat::CSV, [
                'Content-Type' => 'text/csv',
            ]);
        }

        return Excel::download($export, $filename.'.xlsx', ExcelFormat::XLSX);
    }

    /**
     * Monitoring Slot Jadwal Kosong: cari kelas & slot KBM yang belum di-plot.
     * Menampilkan halaman penuh berisi ringkasan per Kelas -> per Hari -> daftar Jam Ke- kosong.
     */
    public function monitoringSlotKosong(Request $request)
    {
        $tahunAjaranList = TahunAjaran::forCurrentContext()
            ->orderByDesc('tahun_ajaran')
            ->orderBy('semester')
            ->get();
        $requestedTahunAjaranId = $request->query('tahun_ajaran_id');
        $tahunAktif = is_numeric($requestedTahunAjaranId)
            ? $tahunAjaranList->firstWhere('id', (int) $requestedTahunAjaranId)
            : null;
        $tahunAktif ??= $tahunAjaranList->firstWhere('is_active', true) ?? $tahunAjaranList->first();
        $hariList = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];

        // Filter pencarian nama kelas, tingkat, jurusan, dan hari (GET params).
        $keyword = trim((string) $request->input('search', ''));
        $selectedHari = (string) $request->input('hari', '');
        $tingkatOptions = [
            'X' => ['X', '10'],
            'XI' => ['XI', '11'],
            'XII' => ['XII', '12'],
        ];
        $selectedTingkat = (string) $request->input('tingkat', '');
        if (! array_key_exists($selectedTingkat, $tingkatOptions)) {
            $selectedTingkat = '';
        }
        $jurusanOptions = Jurusan::query()
            ->orderBy('nama_jurusan')
            ->get(['id', 'nama_jurusan', 'kode_jurusan']);
        $jurusanId = $request->input('jurusan_id');
        $selectedJurusanId = is_numeric($jurusanId)
            && $jurusanOptions->contains(fn (Jurusan $jurusan) => (int) $jurusan->id === (int) $jurusanId)
                ? (int) $jurusanId
                : '';

        $kelasList = Kelas::with('jurusan')
            ->when($selectedTingkat !== '', fn ($q) => $q->whereIn('tingkat', $tingkatOptions[$selectedTingkat]))
            ->when($selectedJurusanId !== '', fn ($q) => $q->where('id_jurusan', $selectedJurusanId))
            ->when($keyword !== '', function ($q) use ($keyword) {
                $q->where(function ($sub) use ($keyword) {
                    $sub->where('nama_kelas', 'like', "%{$keyword}%")
                        ->orWhere('tingkat', 'like', "%{$keyword}%")
                        ->orWhereRaw("CONCAT(tingkat, ' ', nama_kelas) LIKE ?", ["%{$keyword}%"])
                        ->orWhereRaw("CONCAT(tingkat, ' - ', nama_kelas) LIKE ?", ["%{$keyword}%"])
                        ->orWhereHas('jurusan', function ($jQ) use ($keyword) {
                            $jQ->where('nama_jurusan', 'like', "%{$keyword}%")
                                ->orWhere('kode_jurusan', 'like', "%{$keyword}%");
                        });
                });
            })
            ->orderBy('tingkat')
            ->orderBy('nama_kelas')
            ->get();

        // Semua jadwal ter-plot pada Tahun Ajaran & Semester yang dipilih.
        // Relasi slot dibaca tanpa scope aktif agar data tahun arsip juga terhitung.
        $jadwalTerplot = JadwalPelajaran::with([
            'jamPelajaran' => fn ($q) => $q
                ->withoutGlobalScope(ActiveTahunAjaranScope::class)
                ->ofTahunAjaran($tahunAktif?->id, (bool) ($tahunAktif?->is_active ?? false)),
        ])
            ->when(
                $tahunAktif,
                fn ($q) => $q->where('id_tahun_ajaran', $tahunAktif->id),
                fn ($q) => $q->whereNull('id_tahun_ajaran')
            )
            ->get();

        // Bucket jam_ke yang sudah ter-plot per (kelas, hari).
        $plotted = [];
        foreach ($jadwalTerplot as $j) {
            $jamKe = $j->jamPelajaran->jam_ke ?? null;
            if ($jamKe !== null) {
                $plotted[$j->id_kelas][$j->hari][$jamKe] = true;
            }
        }

        // Master slot KBM MURNI — hanya berjenis 'kbm' yang dihitung sebagai slot kosong.
        // Slot non-KBM (istirahat, upacara, pembiasaan, agenda rutin, pulang, dsb.)
        // TIDAK dimasukkan ke dalam kalkulasi slot_kosong. Batas per tingkat kelas
        // (JamPulang) tetap diaplikasikan terpisah pada loop di bawah.
        $slotsPerHari = JamPelajaran::withoutGlobalScope(ActiveTahunAjaranScope::class)
            ->where('jenis', 'kbm')
            ->whereNotNull('jam_ke')
            ->ofTahunAjaran($tahunAktif?->id, (bool) ($tahunAktif?->is_active ?? false))
            ->get()
            ->groupBy('hari');

        // Agenda rutin aktif dikunci per shift -> hari -> jam_ke.
        // Peta bersarang: [shift_id][hari][jam_ke] = AgendaRutin, agar slot
        // kosong dihitung terhadap agenda shift kelas masing-masing, bukan
        // satu agenda global yang bocor ke semua shift.
        $agendaAktif = AgendaRutin::where('is_active', true)
            ->get()
            ->groupBy('shift_id')
            ->mapWithKeys(function ($rows, $shiftId) {
                return [
                    (int) $shiftId => $rows->groupBy('hari')->mapWithKeys(
                        fn ($items, $hari) => [$hari => $items->keyBy('jam_ke')]
                    ),
                ];
            });

        $rows = [];
        $totalSlotKosong = 0;
        $jumlahKelasLengkap = 0;

        foreach ($kelasList as $kelas) {
            $punyaKosong = false;

            // Shift efektif kelas (ikon langsung + deteksi grade_levels) untuk
            // menghitung slot terlihat, agenda & batas jam pulang per kelas.
            $shiftEff = $this->shiftUntukKelas($kelas, $tahunAktif)?->id ?? 0;

            foreach ($hariList as $hari) {
                // Filter hari: skip hari yang tidak dipilih (bila dropdown terisi).
                if ($selectedHari !== '' && $hari !== $selectedHari) {
                    continue;
                }

                $kategori = ($hari === 'Jumat') ? 'Jumat' : 'Senin-Kamis';
                $slots = $slotsPerHari->get($hari) ?? collect();
                if ($slots->isEmpty() && in_array($hari, ['Senin', 'Selasa', 'Rabu', 'Kamis'], true)) {
                    // Fallback untuk hari Senin-Kamis jika slot disimpan dengan hari='Senin'
                    $slots = $slotsPerHari->get('Senin', collect());
                }

                // Hanya slot MURNI milik shift efektif kelas ini (tanpa campuran slot Global/shift lain).
                $slots = $slots
                    ->filter(fn ($slot) => (int) ($slot->shift_id ?? 0) === $shiftEff)
                    ->values();

                $agendaHari = $agendaAktif[$shiftEff][$hari] ?? collect();
                // Batas Jam Pulang per tingkat kelas (format tingkat sama dengan master:
                // huruf Romawi, mis. 'X', 'XI', 'XII' — lihat PengaturanJadwalSeeder).
                // Slot dengan jam_ke > max_jam_ke (mis. jam ke-13 saat max 12) tidak dihitung kosong.
                $maxJamKe = JamPulang::getMaxJamKe($kategori, strtoupper(trim($kelas->tingkat)), $shiftEff);

                $kosong = [];
                foreach ($slots as $slot) {
                    // Lewati slot yang dikunci Agenda Rutin (misal Upacara).
                    if ($agendaHari->has($slot->jam_ke)) {
                        continue;
                    }
                    // Lewati slot yang melewati batas jam pulang kelas.
                    if ($maxJamKe !== null && $slot->jam_ke > $maxJamKe) {
                        continue;
                    }
                    // Kosong bila belum ada mapel ter-plot.
                    if (! isset($plotted[$kelas->id][$hari][$slot->jam_ke])) {
                        $kosong[] = $slot->jam_ke;
                    }
                }

                if (! empty($kosong)) {
                    $punyaKosong = true;
                    $totalSlotKosong += count($kosong);
                    $rows[] = [
                        'kelas_id' => $kelas->id,
                        'kelas_nama' => $kelas->nama_kelas,
                        'tingkat' => $kelas->tingkat,
                        'jurusan' => $kelas->jurusan->nama_jurusan ?? 'Umum',
                        'hari' => $hari,
                        'jam_kosong' => array_values($kosong),
                        'jumlah' => count($kosong),
                    ];
                }
            }

            if (! $punyaKosong) {
                $jumlahKelasLengkap++;
            }
        }

        $totalKelas = $kelasList->count();

        return view('admin.jadwal.monitoring', compact(
            'rows',
            'totalKelas',
            'jumlahKelasLengkap',
            'totalSlotKosong',
            'hariList',
            'keyword',
            'selectedHari',
            'tahunAjaranList',
            'tahunAktif',
            'tingkatOptions',
            'selectedTingkat',
            'jurusanOptions',
            'selectedJurusanId'
        ));
    }

    /**
     * Simpan plotting jadwal baru (mendukung multi-slot jam sekaligus).
     */
    /**
     * Simpan plotting jadwal baru (mendukung multi-slot jam sekaligus).
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'id_kelas' => 'required|exists:kelas,id',
                'hari' => 'required|in:Senin,Selasa,Rabu,Kamis,Jumat',
                'jam_ke_mulai' => 'required|integer|min:1|max:20',
                'jam_ke_selesai' => 'required|integer|min:1|max:20|gte:jam_ke_mulai',
                'id_mapel' => 'required|exists:mata_pelajaran,id',
                'id_guru' => ['required', Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', User::ROLE_GURU))],
                'id_ruangan' => 'nullable|exists:ruangans,id',
                'group_id' => 'nullable|string|max:40',
            ]);

            $tahunAktif = $this->resolveTahunAjaranContext($request);
            $taStr = $tahunAktif ? " (T.A. {$tahunAktif->tahun_ajaran} {$tahunAktif->semester})" : '';
            $kategoriHari = ($validated['hari'] === 'Jumat') ? 'Jumat' : 'Senin-Kamis';
            $kelas = Kelas::find($validated['id_kelas']);

            // Validator SISTEM (Tipe Penjadwalan konteks T.A): pada tipe Multi-Shift,
            // kelas WAJIB memiliki shift efektif. Shift efektif = ikatan langsung kelas
            // (kelas.shift_id) ATAU deteksi otomatis dari grade_levels shift (mis. kelas
            // XI tanpa ikatan shift -> shift yang melayani tingkatan Kelas 11). Slot jam
            // yang diambil hanya milik shift efektif tsb (scope ofShift - MURNI shift,
            // tanpa bocoran slot Global/shift lain). Kelas tanpa shift efektif sama
            // sekali ditolak agar jadwal tidak menyalahi alokasi shift sekolah.
            // Mode efektif = mode_jadwal T.A aktif; bila belum ditentukan, ikut sistem.
            $tahunAktifMode = $tahunAktif?->effective_schedule_mode
                ?? AppSetting::scheduleMode();
            $plotShift = $kelas ? $this->shiftUntukKelas($kelas, $tahunAktif) : null;

            if ($tahunAktifMode === AppSetting::SCHEDULE_SHIFT && ! $plotShift) {
                throw new \Exception('Gagal! Sekolah menggunakan tipe penjadwalan Multi-Shift — Kelas "'.($kelas->nama_kelas ?? '?').'" belum dialokasikan ke shift tertentu. Tetapkan shift pada data kelas sebelum melakukan plotting jadwal.');
            }

            // Validator Grade Level Mapping: bila shift yang dialokasikan ke kelas
            // sekadar melayani tingkatan tertentu (grade_levels terisi), kelas yang
            // tingkatan-nya di luar daftar tersebut ditolak — slot jam shift hanya
            // relevan untuk tingkatan yang memang dilayani shift itu.
            if ($kelas?->shift_id) {
                $shiftKelas = $kelas->shift;
                $gradeLevels = $shiftKelas?->grade_levels ?? [];
                if (! empty($gradeLevels) && ! $shiftKelas->servesGrade($kelas->tingkat)) {
                    throw new \Exception(
                        'Gagal! Shift "'.$shiftKelas->nama_shift.'" hanya berlaku untuk tingkatan '
                        .ShiftPelajaran::gradeLevelsLabel($gradeLevels)
                        .' — Kelas "'.($kelas->nama_kelas ?? '?').'" ber-tingkat '.($kelas->tingkat ?? '?')
                        .'. Sesuaikan alokasi shift pada data kelas sebelum plotting jadwal.'
                    );
                }
            }

            $tingkatKelas = $kelas ? match (strtoupper(trim($kelas->tingkat))) {
                'X' => '10', 'XI' => '11', 'XII' => '12', default => $kelas->tingkat
            } : '10';

            // 1. Ambil semua slot KBM dalam rentang jam_ke_mulai s/d jam_ke_selesai (abaikan jenis istirahat)
            //    — HANYA slot murni milik shift efektif kelas pada TA terpilih.
            //    `id_jam` SELALU diambil dari master aktif lewat query ini (tidak pernah
            //    dari input pengguna), sehingga tetap benar walau ID jam bergeser.
            $targetSlots = JamPelajaran::withoutGlobalScope(ActiveTahunAjaranScope::class)
                ->where('hari', $validated['hari'])
                ->plotable($plotShift?->id, $tahunAktif?->id, (bool) ($tahunAktif?->is_active ?? false))
                ->whereBetween('jam_ke', [$validated['jam_ke_mulai'], $validated['jam_ke_selesai']])
                ->orderBy('jam_mulai')
                ->orderByDesc('id')
                ->get();

            if ($targetSlots->isEmpty()) {
                throw new \Exception("Tidak ditemukan slot KBM pada rentang Jam ke-{$validated['jam_ke_mulai']} s/d Jam ke-{$validated['jam_ke_selesai']}.");
            }

            $targetJamIds = $targetSlots->pluck('id')->toArray();

            // Guard: jika slot tujuan/kelas/guru ini menyentuh data testing, hanya IT/QA yang boleh.
            $this->authorizeTestingBatch(
                JadwalPelajaran::withTrashed()
                    ->where('hari', $validated['hari'])
                    ->whereIn('id_jam', $targetJamIds)
                    ->where(function ($q) use ($validated) {
                        $q->where('id_kelas', $validated['id_kelas'])
                            ->orWhere('id_guru', $validated['id_guru']);
                    })
                    ->when($tahunAktif, fn ($q) => $q->where('id_tahun_ajaran', $tahunAktif->id))
                    ->where('is_testing_data', true)
            );

            // Mode Edit: saat group_id dikirim, seluruh slot pada grup yang sama diperbarui
            $isEditMode = filled($validated['group_id'] ?? null);
            $groupId = $isEditMode ? (string) $validated['group_id'] : (string) Str::uuid();

            // 1b. HARD GUARD: slot terkunci multi-sumber dalam rentang
            $blockedSlots = $this->detectBlockedSlotsInRange(
                $kategoriHari,
                $targetSlots,
                $validated['hari'],
                (int) $validated['id_kelas'],
                $tahunAktif,
                $tingkatKelas,
                $isEditMode ? $groupId : null,
                $plotShift?->id ?? 0
            );

            if (! empty($blockedSlots)) {
                $details = [];
                foreach ($blockedSlots as $b) {
                    $label = match ($b['reason']) {
                        'istirahat' => 'Istirahat',
                        'agenda' => 'Agenda Rutin',
                        'pulang' => 'Terkunci (Selesai KBM)',
                        'non_kbm' => 'Terkunci',
                        'terisi' => 'Terkunci ('.($b['detail'] ?? 'Sudah Terisi').')',
                        default => 'Terkunci',
                    };

                    if ($b['jam_ke'] !== null) {
                        $details[] = "{$label} pada Jam Ke-{$b['jam_ke']}";
                    } else {
                        $details[] = "{$label}";
                    }
                }

                $detailStr = implode(', ', array_unique($details));
                throw new \Exception("Gagal! Rentang jam yang dipilih menabrak slot {$detailStr}.");
            }

            // 2. Pengecekan bentrok guru di kelas lain pada jam & hari yang sama
            $bentroks = JadwalPelajaran::where('hari', $validated['hari'])
                ->whereIn('id_jam', $targetJamIds)
                ->where('id_guru', $validated['id_guru'])
                ->where('id_kelas', '!=', $validated['id_kelas'])
                ->when($tahunAktif, fn ($q) => $q->where('id_tahun_ajaran', $tahunAktif->id))
                ->with(['kelas', 'guru', 'jamPelajaran', 'mataPelajaran'])
                ->get();

            if ($bentroks->isNotEmpty()) {
                $namaGuru = $bentroks->first()->guru->nama ?? 'Guru terpilih';
                $details = [];
                $grouped = $bentroks->groupBy(fn ($b) => ($b->mataPelajaran->nama_mapel ?? 'Mapel').'|||'.($b->kelas->nama_kelas ?? 'Kelas'));

                foreach ($grouped as $key => $items) {
                    [$namaMapel, $namaKelas] = explode('|||', $key);
                    $jamKes = $items->map(fn ($item) => 'Jam Ke-'.($item->jamPelajaran->jam_ke ?? '-'))->unique()->implode(' / ');
                    $details[] = "Guru {$namaGuru} sudah ada jadwal di Kelas {$namaKelas} pada {$jamKes}{$taStr}";
                }

                throw new \Exception('Gagal! '.implode('; ', $details).'.');
            }

            // 2b. Pengecekan bentrok ruangan sedang digunakan oleh kelas lain
            if (! empty($validated['id_ruangan'])) {
                $bentrokRuangan = JadwalPelajaran::where('hari', $validated['hari'])
                    ->whereIn('id_jam', $targetJamIds)
                    ->where('id_ruangan', $validated['id_ruangan'])
                    ->where('id_kelas', '!=', $validated['id_kelas'])
                    ->when($isEditMode, fn ($q) => $q->where(function ($sub) use ($groupId) {
                        $sub->where('group_id', '!=', $groupId)->orWhereNull('group_id');
                    }))
                    ->when($tahunAktif, fn ($q) => $q->where('id_tahun_ajaran', $tahunAktif->id))
                    ->with(['kelas', 'mataPelajaran', 'ruangan', 'jamPelajaran'])
                    ->get();

                if ($bentrokRuangan->isNotEmpty()) {
                    $details = [];
                    $grouped = $bentrokRuangan->groupBy(fn ($b) => ($b->ruangan->nama_ruangan ?? $b->ruangan->kode_ruangan ?? 'Ruangan').'|||'.($b->kelas->nama_kelas ?? 'Kelas').'|||'.($b->mataPelajaran->nama_mapel ?? 'Mapel'));

                    foreach ($grouped as $key => $items) {
                        [$namaRuangan, $namaKelas, $namaMapel] = explode('|||', $key);
                        $jamKes = $items->map(fn ($item) => 'Jam Ke-'.($item->jamPelajaran->jam_ke ?? '-'))->unique()->implode(' / ');
                        $details[] = "Ruangan {$namaRuangan} sudah terpakai oleh Kelas {$namaKelas} pada {$jamKes}{$taStr}";
                    }

                    throw new \Exception('Gagal! '.implode('; ', $details).'.');
                }
            }

            // 2c. Pengecekan slot yang sudah terisi jadwal lain pada kelas & hari yang sama
            $slotTerisiLain = JadwalPelajaran::where('id_kelas', $validated['id_kelas'])
                ->where('hari', $validated['hari'])
                ->whereIn('id_jam', $targetJamIds)
                ->when($tahunAktif, fn ($q) => $q->where('id_tahun_ajaran', $tahunAktif->id))
                ->when($isEditMode, fn ($q) => $q->where(function ($sub) use ($groupId) {
                    $sub->where('group_id', '!=', $groupId)->orWhereNull('group_id');
                }))
                ->with(['mataPelajaran', 'jamPelajaran'])
                ->get();

            if ($slotTerisiLain->isNotEmpty()) {
                $slotBentrok = $slotTerisiLain->first();
                $namaMapel = $slotBentrok->mataPelajaran->nama_mapel ?? 'jadwal lain';
                $jamKe = $slotBentrok->jamPelajaran->jam_ke ?? '-';

                throw new \Exception("Gagal! Rentang jam yang dipilih menabrak slot Terkunci ({$namaMapel}) pada Jam Ke-{$jamKe}.");
            }

            // 3. Simpan dalam transaksi DB
            DB::transaction(function () use ($targetSlots, $validated, $tahunAktif, $groupId, $isEditMode, $targetJamIds) {
                // Hapus permanen record soft-deleted pada slot target untuk guru atau kelas ini
                // agar tidak memicu bentrok DB Unique Constraint (unq_guru_hari_jam / unq_kelas_hari_jam)
                JadwalPelajaran::onlyTrashed()
                    ->where('hari', $validated['hari'])
                    ->whereIn('id_jam', $targetJamIds)
                    ->where(function ($q) use ($validated) {
                        $q->where('id_guru', $validated['id_guru'])
                            ->orWhere('id_kelas', $validated['id_kelas']);
                    })
                    ->when($tahunAktif, fn ($q) => $q->where('id_tahun_ajaran', $tahunAktif->id))
                    ->forceDelete();

                foreach ($targetSlots as $slot) {
                    if ($slot->jenis !== 'kbm') {
                        continue;
                    }
                    JadwalPelajaran::withTrashed()->updateOrCreate(
                        [
                            'id_kelas' => $validated['id_kelas'],
                            'hari' => $validated['hari'],
                            'id_jam' => $slot->id,
                            'id_tahun_ajaran' => $tahunAktif?->id,
                        ],
                        [
                            'group_id' => $groupId,
                            'id_mapel' => $validated['id_mapel'],
                            'id_guru' => $validated['id_guru'],
                            'id_ruangan' => $validated['id_ruangan'] ?? null,
                            'deleted_at' => null,
                        ]
                    );
                }

                if ($isEditMode) {
                    // Guard: cleanup grup (jam non-target) tidak boleh menghapus data testing milik non-IT.
                    $this->authorizeTestingBatch(
                        JadwalPelajaran::withTrashed()
                            ->where('group_id', $groupId)
                            ->where('id_kelas', $validated['id_kelas'])
                            ->where('hari', $validated['hari'])
                            ->where('id_tahun_ajaran', $tahunAktif?->id)
                            ->where('is_testing_data', true)
                    );

                    JadwalPelajaran::withTrashed()
                        ->where('group_id', $groupId)
                        ->where('id_kelas', $validated['id_kelas'])
                        ->where('hari', $validated['hari'])
                        ->where('id_tahun_ajaran', $tahunAktif?->id)
                        ->whereNotIn('id_jam', $targetJamIds)
                        ->forceDelete();
                }
            });

            $mapelObj = MataPelajaran::find($validated['id_mapel']);
            $guruObj = User::find($validated['id_guru']);
            $namaMapel = $mapelObj?->nama_mapel ?? 'Mapel';
            $namaGuru = $guruObj?->nama ?? 'Guru';

            $pesanJam = ($validated['jam_ke_mulai'] == $validated['jam_ke_selesai'])
                ? "Jam Ke-{$validated['jam_ke_mulai']}"
                : "Jam Ke-{$validated['jam_ke_mulai']} s/d {$validated['jam_ke_selesai']}";

            $actionVerb = $isEditMode ? 'memperbarui' : 'menambahkan';
            $taInfo = $tahunAktif ? " untuk Tahun Ajaran {$tahunAktif->tahun_ajaran} ({$tahunAktif->semester})" : '';
            $pesanSukses = "Berhasil {$actionVerb} jadwal {$namaMapel} ({$namaGuru}) pada {$pesanJam}{$taInfo}.";

            session()->flash('success', $pesanSukses);

            if ($request->wantsJson()) {
                $savedItems = JadwalPelajaran::with(['mataPelajaran', 'guru', 'jamPelajaran', 'ruangan'])
                    ->where('group_id', $groupId)
                    ->where('id_kelas', $validated['id_kelas'])
                    ->where('hari', $validated['hari'])
                    ->when($tahunAktif, fn ($q) => $q->where('id_tahun_ajaran', $tahunAktif->id))
                    ->get();

                return response()->json([
                    'success' => true,
                    'message' => $pesanSukses,
                    'id_kelas' => $validated['id_kelas'],
                    'hari' => $validated['hari'],
                    'data' => $savedItems,
                ]);
            }

            return redirect()
                ->route('admin.jadwal.index', ['id_kelas' => $validated['id_kelas'], 'hari' => $validated['hari']])
                ->with('success', $pesanSukses);
        } catch (\Throwable $e) {
            return $this->guardFailure(
                $request,
                $e->getMessage(),
                ['id_kelas' => $request->input('id_kelas'), 'hari' => $request->input('hari')]
            );
        }
    }

    /**
     * Update data plotting jadwal yang sudah ada.
     *
     * CATATAN PENTING — `id_jam` dari request TIDAK pernah dipakai mentah-mentah.
     * ID `jam_pelajaran` bersifat auto-increment dan berubah total ketika master
     * jam dihapus-dibuat ulang, sehingga form yang terbuka lama bisa mengirim ID
     * yang sudah tidak berlaku. Semua query di bawah memakai `$slotBaru`, hasil
     * resolve DINAMIS dari (hari, jam_ke, shift efektif kelas, tahun ajaran).
     */
    public function update(Request $request, JadwalPelajaran $jadwalPelajaran)
    {
        try {
            $validated = $request->validate([
                'id_kelas' => 'required|exists:kelas,id',
                'hari' => 'required|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu',
                'id_jam' => 'nullable|integer',
                'jam_ke' => 'nullable|integer|min:1',
                'id_mapel' => 'required|exists:mata_pelajaran,id',
                'id_guru' => ['required', Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', User::ROLE_GURU))],
                'id_ruangan' => 'nullable|exists:ruangans,id',
            ]);

            // Minimal salah satu dari (id_jam, jam_ke) harus ada agar slot bisa ditentukan.
            if (empty($validated['id_jam']) && empty($validated['jam_ke'])) {
                throw new \Exception('Gagal! Slot jam wajib ditentukan (kirim minimal jam_ke atau id_jam).');
            }

            $tahunAktif = $this->resolveTahunAjaranContext($request);
            $taStr = $tahunAktif ? " (T.A. {$tahunAktif->tahun_ajaran} {$tahunAktif->semester})" : '';
            $kategoriHari = ($validated['hari'] === 'Jumat') ? 'Jumat' : 'Senin-Kamis';
            $kelasUpdate = Kelas::find($validated['id_kelas']);
            $tingkatUpdate = $kelasUpdate ? match (strtoupper(trim($kelasUpdate->tingkat))) {
                'X' => '10', 'XI' => '11', 'XII' => '12', default => $kelasUpdate->tingkat
            } : '10';

            $plotShiftUpdate = $kelasUpdate ? $this->shiftUntukKelas($kelasUpdate, $tahunAktif) : null;
            $plotShiftUpdateId = $plotShiftUpdate?->id ?? 0;

            // ── RESOLVE SLOT DINAMIS (tidak hardcode / tidak percaya id_jam dari client) ──
            // Urutan sumber `jam_ke`:
            //   1. jam_ke eksplisit dari request (form plotting mengirim rentang jam ke-N)
            //   2. jam_ke dari record jadwal yang sedang diedit (aman walau form basi)
            //   3. jam_ke dari id_jam yang dikirim (dibaca dari master, termasuk soft-deleted)
            $jamKeTarget = $this-> tentukanJamKeTarget($request, $jadwalPelajaran, $validated);

            if ($jamKeTarget === null) {
                throw new \Exception('Gagal! Slot jam untuk jadwal ini tidak dapat ditentukan. Periksa kembali master jam pelajaran.');
            }

            $slotBaru = JamPelajaran::resolveSlot(
                $jamKeTarget,
                $validated['hari'],
                $plotShiftUpdate?->id,
                $tahunAktif?->id,
                (bool) ($tahunAktif?->is_active ?? false)
            );

            if (! $slotBaru) {
                throw new \Exception(
                    "Gagal! Slot Jam Ke-{$jamKeTarget} pada hari {$validated['hari']} tidak ditemukan pada master jam pelajaran"
                    .($plotShiftUpdate ? ' shift "'.$plotShiftUpdate->nama_shift.'"' : '')
                    .'. Tambahkan slot jam tersebut terlebih dahulu.'
                );
            }

            // ID hasil resolve inilah yang dipakai seluruh query di bawah.
            $idJamEfektif = (int) $slotBaru->id;

            {
                $agendaUpdate = AgendaRutin::where('hari', $validated['hari'])
                    ->where('jam_ke', $slotBaru->jam_ke)
                    ->where('is_active', true)
                    ->ofShift($plotShiftUpdateId)
                    ->first();
                $maxJamKeUpdate = JamPulang::getMaxJamKe($kategoriHari, $tingkatUpdate, $plotShiftUpdateId);
                $terkunci = ($slotBaru->jenis !== 'kbm')
                    || ($agendaUpdate !== null)
                    || ($maxJamKeUpdate !== null && $slotBaru->jam_ke !== null && $slotBaru->jam_ke > $maxJamKeUpdate);

                if ($terkunci) {
                    $reasonLabel = ($slotBaru->jenis !== 'kbm')
                        ? str_contains(strtolower($slotBaru->jenis ?? ''), 'istirahat') ? 'Istirahat' : 'Terkunci'
                        : (($agendaUpdate !== null)
                            ? 'Agenda Rutin'
                            : 'Terkunci (Selesai KBM)');
                    $jamKeStr = $slotBaru->jam_ke !== null ? "Jam Ke-{$slotBaru->jam_ke}" : 'slot tersebut';

                    throw new \Exception("Gagal! Rentang jam yang dipilih menabrak slot {$reasonLabel} pada {$jamKeStr}.");
                }

                // Tolak jika slot sudah terisi jadwal lain
                $terisiLain = JadwalPelajaran::where('id_kelas', $validated['id_kelas'])
                    ->where('hari', $validated['hari'])
                    ->where('id_jam', $idJamEfektif)
                    ->where('id', '!=', $jadwalPelajaran->id)
                    ->when($jadwalPelajaran->id_tahun_ajaran, fn ($q) => $q->where('id_tahun_ajaran', $jadwalPelajaran->id_tahun_ajaran))
                    ->when($jadwalPelajaran->group_id, fn ($q) => $q->where(function ($sub) use ($jadwalPelajaran) {
                        $sub->where('group_id', '!=', $jadwalPelajaran->group_id)->orWhereNull('group_id');
                    }))
                    ->with(['mataPelajaran'])
                    ->first();

                if ($terisiLain) {
                    $namaMapel = $terisiLain->mataPelajaran->nama_mapel ?? 'Jadwal Lain';
                    $jamKeStr = $slotBaru->jam_ke !== null ? "Jam Ke-{$slotBaru->jam_ke}" : 'slot tersebut';
                    throw new \Exception("Gagal! Rentang jam yang dipilih menabrak slot Terkunci ({$namaMapel}) pada {$jamKeStr}.");
                }
            }

            // Pengecekan bentrok guru di kelas lain
            $bentrok = JadwalPelajaran::where('hari', $validated['hari'])
                ->where('id_jam', $idJamEfektif)
                ->where('id_guru', $validated['id_guru'])
                ->where('id_kelas', '!=', $validated['id_kelas'])
                ->where('id', '!=', $jadwalPelajaran->id)
                ->when($jadwalPelajaran->id_tahun_ajaran, fn ($q) => $q->where('id_tahun_ajaran', $jadwalPelajaran->id_tahun_ajaran))
                ->with(['kelas', 'guru', 'jamPelajaran', 'mataPelajaran'])
                ->first();

            if ($bentrok) {
                $namaGuru = $bentrok->guru->nama ?? 'Guru terpilih';
                $namaMapel = $bentrok->mataPelajaran->nama_mapel ?? 'Mapel';
                $namaKelas = $bentrok->kelas->nama_kelas ?? 'Kelas lain';
                $jamKe = $bentrok->jamPelajaran->jam_ke ?? '';
                $infoJam = $jamKe ? "Jam Ke-{$jamKe}" : 'slot jam tersebut';

                throw new \Exception("Gagal! Guru {$namaGuru} sudah ada jadwal di Kelas {$namaKelas} pada {$infoJam}{$taStr}.");
            }

            // Pengecekan bentrok ruangan sedang digunakan oleh kelas lain
            if (! empty($validated['id_ruangan'])) {
                $bentrokRuangan = JadwalPelajaran::where('hari', $validated['hari'])
                    ->where('id_jam', $idJamEfektif)
                    ->where('id_ruangan', $validated['id_ruangan'])
                    ->where('id_kelas', '!=', $validated['id_kelas'])
                    ->where('id', '!=', $jadwalPelajaran->id)
                    ->when($jadwalPelajaran->id_tahun_ajaran, fn ($q) => $q->where('id_tahun_ajaran', $jadwalPelajaran->id_tahun_ajaran))
                    ->with(['kelas', 'mataPelajaran', 'ruangan', 'jamPelajaran'])
                    ->first();

                if ($bentrokRuangan) {
                    $namaRuangan = $bentrokRuangan->ruangan->nama_ruangan ?? $bentrokRuangan->ruangan->kode_ruangan ?? 'Ruangan';
                    $namaKelas = $bentrokRuangan->kelas->nama_kelas ?? 'Kelas lain';
                    $namaMapel = $bentrokRuangan->mataPelajaran->nama_mapel ?? 'Mapel';
                    $jamKe = $bentrokRuangan->jamPelajaran->jam_ke ?? '';
                    $infoJam = $jamKe ? "Jam Ke-{$jamKe}" : 'slot jam tersebut';

                    throw new \Exception("Gagal! Ruangan {$namaRuangan} sudah terpakai oleh Kelas {$namaKelas} pada {$infoJam}{$taStr}.");
                }
            }

            // Guard: record & baris soft-deleted terkait tidak boleh data testing (kecuali IT/QA).
            $this->authorizeTestingMutation($jadwalPelajaran);
            $this->authorizeTestingBatch(
                JadwalPelajaran::onlyTrashed()
                    ->where('hari', $validated['hari'])
                    ->where('id_jam', $idJamEfektif)
                    ->where(function ($q) use ($validated) {
                        $q->where('id_guru', $validated['id_guru'])
                            ->orWhere('id_kelas', $validated['id_kelas']);
                    })
                    ->when($tahunAktif, fn ($q) => $q->where('id_tahun_ajaran', $tahunAktif->id))
                    ->where('is_testing_data', true)
            );

            DB::transaction(function () use ($jadwalPelajaran, $validated, $tahunAktif, $idJamEfektif) {
                // Hapus permanen record soft-deleted pada slot target untuk guru atau kelas ini
                // agar tidak memicu bentrok DB Unique Constraint (unq_guru_hari_jam / unq_kelas_hari_jam)
                JadwalPelajaran::onlyTrashed()
                    ->where('hari', $validated['hari'])
                    ->where('id_jam', $idJamEfektif)
                    ->where(function ($q) use ($validated) {
                        $q->where('id_guru', $validated['id_guru'])
                            ->orWhere('id_kelas', $validated['id_kelas']);
                    })
                    ->when($tahunAktif, fn ($q) => $q->where('id_tahun_ajaran', $tahunAktif->id))
                    ->forceDelete();

                $jadwalPelajaran->update([
                    'id_kelas' => $validated['id_kelas'],
                    'hari' => $validated['hari'],
                    'id_jam' => $idJamEfektif,
                    'id_mapel' => $validated['id_mapel'],
                    'id_guru' => $validated['id_guru'],
                    'id_ruangan' => $validated['id_ruangan'] ?? null,
                    'id_tahun_ajaran' => $tahunAktif?->id ?? $jadwalPelajaran->id_tahun_ajaran,
                ]);
            });

            $mapelObj = MataPelajaran::find($validated['id_mapel']);
            $guruObj = User::find($validated['id_guru']);

            $namaMapel = $mapelObj?->nama_mapel ?? 'Mapel';
            $namaGuru = $guruObj?->nama ?? 'Guru';
            $jamKe = $slotBaru->jam_ke ?? '-';

            $taInfo = $tahunAktif ? " untuk Tahun Ajaran {$tahunAktif->tahun_ajaran} ({$tahunAktif->semester})" : '';
            $pesanSukses = "Berhasil memperbarui jadwal {$namaMapel} ({$namaGuru}) pada Jam Ke-{$jamKe}{$taInfo}.";
            session()->flash('success', $pesanSukses);

            if ($request->wantsJson()) {
                $jadwalPelajaran->load(['mataPelajaran', 'guru', 'jamPelajaran', 'ruangan']);
                $jadwalPelajaran->setAttribute('jam_ke', $slotBaru->jam_ke);
                $jadwalPelajaran->setAttribute('jam_valid', 1);

                return response()->json([
                    'success' => true,
                    'message' => $pesanSukses,
                    'id_kelas' => $validated['id_kelas'],
                    'hari' => $validated['hari'],
                    'data' => $jadwalPelajaran,
                ]);
            }

            return redirect()
                ->route('admin.jadwal.index', ['id_kelas' => $jadwalPelajaran->id_kelas, 'hari' => $jadwalPelajaran->hari])
                ->with('success', $pesanSukses);
        } catch (\Throwable $e) {
            return $this->guardFailure(
                $request,
                $e->getMessage(),
                ['id_kelas' => $jadwalPelajaran->id_kelas, 'hari' => $jadwalPelajaran->hari]
            );
        }
    }

    /**
     * Tentukan `jam_ke` target saat update jadwal, dari sumber yang trustworthy.
     *
     * `id_jam` dari client TIDAK dipercaya langsung karena bisa basi (master jam
     * dihapus-dibuat ulang → ID berubah). Urutan prioritas sumber:
     *   1. `jam_ke` eksplisit dari request (form plotting mengirim slot ke-N).
     *   2. `jam_ke` record jadwal yang sedang diedit.
     *   3. `jam_ke` dari master jam yang di-referensikan `id_jam` — dibaca
     *      TANPA global scope dan DENGAN `withTrashed()` supaya jadwal lama
     *      yang menunjuk slot soft-deleted tetap bisa ditemukan & di-heal.
     */
    private function tentukanJamKeTarget(
        Request $request,
        JadwalPelajaran $jadwalPelajaran,
        array $validated
    ): ?int {
        // 1. jam_ke eksplisit dari request.
        if (! empty($validated['jam_ke'])) {
            return (int) $validated['jam_ke'];
        }

        // 2. Dari record jadwal yang sedang diedit (id_jam saat ini).
        $slotSaatIni = JamPelajaran::withoutGlobalScope(ActiveTahunAjaranScope::class)
            ->withTrashed()
            ->find($jadwalPelajaran->id_jam);
        if ($slotSaatIni && $slotSaatIni->jam_ke !== null) {
            return (int) $slotSaatIni->jam_ke;
        }

        // 3. Dari id_jam yang dikirim request (bisa saja slot master lain).
        if (! empty($validated['id_jam'])) {
            $slotKiriman = JamPelajaran::withoutGlobalScope(ActiveTahunAjaranScope::class)
                ->withTrashed()
                ->find($validated['id_jam']);
            if ($slotKiriman && $slotKiriman->jam_ke !== null) {
                return (int) $slotKiriman->jam_ke;
            }
        }

        return null;
    }

    /**
     * Hapus / unplot jadwal pelajaran pada slot tertentu.
     */
    public function destroy(Request $request, JadwalPelajaran $jadwalPelajaran)
    {
        // Guard: hanya IT/QA yang dapat menghapus plot jadwal data testing.
        $this->authorizeTestingMutation($jadwalPelajaran);

        try {
            $idKelas = $jadwalPelajaran->id_kelas;
            $hari = $jadwalPelajaran->hari;

            DB::transaction(function () use ($jadwalPelajaran) {
                $jadwalPelajaran->delete();
            });

            $pesanSukses = 'Plotting jadwal pada slot tersebut berhasil dikosongkan.';
            session()->flash('success', $pesanSukses);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $pesanSukses,
                    'id_kelas' => $idKelas,
                    'hari' => $hari,
                ]);
            }

            return redirect()
                ->route('admin.jadwal.index', ['id_kelas' => $idKelas, 'hari' => $hari])
                ->with('success', $pesanSukses);
        } catch (\Throwable $e) {
            return $this->guardFailure($request, $e->getMessage(), []);
        }
    }

    /**
     * Deteksi slot yang "terkunci" dalam rentang jam (multi-sumber):
     *  - Uraikan Non-KBM pada master jam (jenis !== 'kbm').
     *  - Agenda Rutin aktif (Upacara / Pembiasaan) pada hari tersebut.
     *  - Melewati batas "Pulang Setelah" berdasarkan tingkat kelas (jam_ke > maxJamKe).
     *  - Slot yang sudah terisi mapel lain pada kelas + hari + semester aktif
     *    (dikecualikan bila milik grup yang sama saat mode edit).
     *  - Ada istirahat yang terentang di dalam rentang waktu.
     *
     * @return array<int, array{jam_ke:int|null, reason:string, label:string, detail:string}>
     */
    private function detectBlockedSlotsInRange(
        string $kategoriHari,
        $targetSlots,
        string $hari,
        int $idKelas,
        ?TahunAjaran $tahunAktif,
        string $tingkatKelas,
        ?string $exemptGroupId = null,
        int $shiftId = 0
    ): array {
        if ($targetSlots->isEmpty()) {
            return [];
        }

        $agendaAktif = AgendaRutin::where('hari', $hari)
            ->where('is_active', true)
            ->ofShift($shiftId)
            ->get()
            ->keyBy('jam_ke');

        $maxJamKe = JamPulang::getMaxJamKe($kategoriHari, $tingkatKelas, $shiftId);

        $blocked = [];

        foreach ($targetSlots as $slot) {
            // a) Non-KBM (Skip istirahat agar rentang jam dapat menyeberangi waktu istirahat)
            if ($slot->jenis !== 'kbm') {
                if ($slot->jenis === 'istirahat') {
                    continue;
                }
                $blocked[] = [
                    'jam_ke' => $slot->jam_ke,
                    'reason' => 'non_kbm',
                    'label' => strtoupper($slot->jenis ?? 'NON-KBM'),
                    'detail' => $slot->jenis_label,
                ];

                continue;
            }

            // b) Agenda Rutin aktif (Upacara/Pembiasaan)
            if ($agendaAktif->has($slot->jam_ke)) {
                $agenda = $agendaAktif->get($slot->jam_ke);
                $nama = strtolower($agenda->nama_agenda ?? 'Agenda');
                $label = str_contains($nama, 'upacara')
                    ? 'UPACARA'
                    : (str_contains($nama, 'pembiasaan') ? 'PEMBIASAAN' : 'AGENDA');
                $blocked[] = [
                    'jam_ke' => $slot->jam_ke,
                    'reason' => 'agenda',
                    'label' => $label,
                    'detail' => $agenda->nama_agenda ?? 'Agenda Rutin',
                ];

                continue;
            }

            // c) Batas "Pulang Setelah" per tingkat kelas
            if ($maxJamKe !== null && $slot->jam_ke !== null && $slot->jam_ke > $maxJamKe) {
                $blocked[] = [
                    'jam_ke' => $slot->jam_ke,
                    'reason' => 'pulang',
                    'label' => 'PULANG SEKOLAH',
                    'detail' => "Selesai KBM setelah Jam ke-{$maxJamKe}",
                ];

                continue;
            }

            // d) Sudah terisi mapel lain (kelas + hari + semester), kecuali grup yang sedang di-edit
            $occupiedQuery = JadwalPelajaran::where('id_kelas', $idKelas)
                ->where('hari', $hari)
                ->where('id_jam', $slot->id)
                ->when($tahunAktif, fn ($q) => $q->where('id_tahun_ajaran', $tahunAktif->id));

            if ($exemptGroupId) {
                $occupiedQuery->where(function ($sub) use ($exemptGroupId) {
                    $sub->where('group_id', '!=', $exemptGroupId)->orWhereNull('group_id');
                });
            }

            $occupied = $occupiedQuery->with('mataPelajaran')->first();
            if ($occupied) {
                $blocked[] = [
                    'jam_ke' => $slot->jam_ke,
                    'reason' => 'terisi',
                    'label' => 'SUDAH TERISI',
                    'detail' => $occupied->mataPelajaran->nama_mapel ?? 'Mapel',
                ];
            }
        }

        return $blocked;
    }

    /**
     * Respons gagal pada guard plotting: JSON 422 saat request menginginkan JSON
     * (fetch dari modal), atau redirect dengan flash error untuk form biasa.
     */
    private function guardFailure(
        Request $request,
        string $flashMessage,
        array $redirectParams = []
    ): JsonResponse|RedirectResponse {
        if ($request->wantsJson()) {
            return response()->json([
                'message' => $flashMessage,
            ], 422);
        }

        return redirect()
            ->route('admin.jadwal.index', $redirectParams)
            ->withInput()
            ->with('error', $flashMessage);
    }

    /**
     * Resolve shift EFEKTIF untuk sebuah kelas pada konteks plotting jadwal.
     *
     * Prioritas:
     *  1. Ikatan langsung kelas (kelas.shift_id) — menang apa pun kondisinya.
     *  2. Mode Multi-Shift: shift AKTIF yang grade_levels-nya PENTING memuat
     *     tingkatan kelas tsb (non-kosong). Inilah deteksi otomatis "kelas tanpa
     *     ikatan shift namun dinaungi shift berdasarkan tingkatan kelas". Bila
     *     ambigu (beberapa shift melayani tingkatan yang sama), shift ber-id
     *     terkecil dipilih secara deterministik.
     *     Shift legacy (grade_levels kosong = berlaku semua tingkatan) TIDAK
     *     dipakai sebagai hasil deteksi — kelas semacam itu dianggap "berlaku
     *     semua" sehingga memakai slot Global.
     *  3. Tidak ditemukan / mode Global => null (slot Global).
     */
    private function shiftUntukKelas(Kelas $kelas, ?TahunAjaran $tahunAktif): ?ShiftPelajaran
    {
        if ($kelas->shift_id) {
            return $kelas->shift;
        }

        $mode = $tahunAktif?->effective_schedule_mode ?? AppSetting::scheduleMode();
        if ($mode !== AppSetting::SCHEDULE_SHIFT) {
            return null;
        }

        $tingkat = strtoupper(trim((string) $kelas->tingkat));

        return ShiftPelajaran::where('is_active', true)
            ->orderBy('id')
            ->get()
            ->first(function ($shift) use ($tingkat) {
                $levels = $shift->grade_levels ?? [];

                return ! empty($levels) && in_array($tingkat, $levels, true);
            });
    }

    /**
     * Resolver konteks Tahun Ajaran & Semester aktif untuk halaman Plotting Jadwal.
     *
     * Prioritas:
     *   1. Param query/input `tahun_ajaran_id` (valid) -> disimpan ke session.
     *   2. Param query/input `tahun_ajaran` + `semester` -> di-resolve ke id, disimpan ke session.
     *   3. Konteks yang terakhir dipilih pada session.
     *   4. Tahun ajaran ber-status aktif; fallback baris pertama.
     */
    private function resolveTahunAjaranContext(Request $request): ?TahunAjaran
    {
        $sessionKey = 'jadwal_selected_tahun_ajaran_id';

        // 1. tahun_ajaran_id dari request (langsung ter-resolve)
        //    Konteks dropdown mengikuti LINGKUNGAN AKTIF (testing/produksi),
        //    bukan partisi peran — konsisten dengan sub-sistem penjadwalan.
        if ($request->filled('tahun_ajaran_id')) {
            $tahun = TahunAjaran::forCurrentContext()->find($request->input('tahun_ajaran_id'));
            if ($tahun) {
                session([$sessionKey => $tahun->id]);

                return $tahun;
            }
        }

        // 2. Kombinasi tahun_ajaran + semester dari request
        if ($request->filled('tahun_ajaran') && $request->filled('semester')) {
            $tahun = TahunAjaran::forCurrentContext()
                ->where('tahun_ajaran', $request->input('tahun_ajaran'))
                ->where('semester', $request->input('semester'))
                ->first();
            if ($tahun) {
                session([$sessionKey => $tahun->id]);

                return $tahun;
            }
        }

        // 3. Konteks dari session
        if ($sessionId = session($sessionKey)) {
            $tahun = TahunAjaran::forCurrentContext()->find($sessionId);
            if ($tahun) {
                return $tahun;
            }
        }

        // 4. Default: tahun aktif / baris pertama
        return TahunAjaran::forCurrentContext()->where('is_active', true)->first()
            ?? TahunAjaran::forCurrentContext()->first();
    }

    /**
     * Route temporary debugging untuk mengecek data raw jadwal_pelajaran per kelas.
     */
    public function debugJadwalKelas(Request $request, $id_kelas)
    {
        $kelas = Kelas::find($id_kelas);
        $tahunAktif = $this->resolveTahunAjaranContext($request);
        $jadwals = JadwalPelajaran::with(['mataPelajaran', 'guru', 'jamPelajaran', 'ruangan'])
            ->where('id_kelas', $id_kelas)
            ->get();

        return response()->json([
            'id_kelas' => (int) $id_kelas,
            'kelas' => $kelas ? "{$kelas->tingkat} {$kelas->nama_kelas}" : 'Tidak ditemukan',
            'tahun_aktif' => $tahunAktif ? "ID: {$tahunAktif->id} ({$tahunAktif->tahun_ajaran} {$tahunAktif->semester})" : null,
            'total_jadwal' => $jadwals->count(),
            'jadwals' => $jadwals->map(fn ($j) => [
                'id' => $j->id,
                'hari' => $j->hari,
                'id_jam' => $j->id_jam,
                'id_tahun_ajaran' => $j->id_tahun_ajaran,
                'mapel' => $j->mataPelajaran?->nama_mapel,
                'guru' => $j->guru?->nama,
                'jam_valid' => $j->jamPelajaran !== null,
                'jam_ke' => $j->jamPelajaran?->jam_ke,
            ]),
        ]);
    }
}
