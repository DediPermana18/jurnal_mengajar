<?php

namespace App\Http\Controllers\Kurikulum;

use App\Http\Controllers\Controller;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\JamPulang;
use App\Models\AgendaRutin;
use App\Models\PengaturanJadwal;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Ruangan;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

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
        $tahunAjaranList = TahunAjaran::orderByDesc('id')->get();
        $semesterList    = ['Ganjil', 'Genap'];
        $tahunOptions    = $tahunAjaranList
            ->pluck('tahun_ajaran')
            ->unique()
            ->sortDesc()
            ->values();

        $tahunAktif = $this->resolveTahunAjaranContext($request);

        // 2. Filter yang aktif
        $hariList = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];
        $selectedHari = $request->get('hari', 'Senin');
        if (!in_array($selectedHari, $hariList)) {
            $selectedHari = 'Senin';
        }

        $idKelas = $request->get('id_kelas');
        $selectedKelas = $idKelas ? $kelasList->firstWhere('id', $idKelas) : null;

        // 3. Tentukan kategori slot jam (Senin–Kamis vs Jumat) & tingkat kelas
        $kategoriHari = ($selectedHari === 'Jumat') ? 'Jumat' : 'Senin-Kamis';
        $tingkatKelas = $selectedKelas ? match(strtoupper(trim($selectedKelas->tingkat))) { 'X' => '10', 'XI' => '11', 'XII' => '12', default => $selectedKelas->tingkat } : '10';

        // 4. Ambil master jam pelajaran global sekolah
        $jamPelajaranList = JamPelajaran::where('kategori_hari', $kategoriHari)
            ->orderBy('jam_mulai')
            ->get();

        // 5. Ambil data jadwal pelajaran yang sudah di-plot
        $jadwalList = collect();
        if ($selectedKelas) {
            $jadwalList = JadwalPelajaran::with(['mataPelajaran', 'guru', 'jamPelajaran', 'ruangan'])
                ->where('id_kelas', $selectedKelas->id)
                ->where('hari', $selectedHari)
                ->when($tahunAktif, fn($q) => $q->where('id_tahun_ajaran', $tahunAktif->id))
                ->get()
                ->keyBy('id_jam');
        }

        // 6. Ambil status sakelar Senin Tanpa Upacara (dipakai widget Sakelar Mode Khusus di view)
        $pengaturanJadwal = PengaturanJadwal::getSetting();

        // Total slot jam (kategori hari terpilih) untuk badge ringkasan di header matriks.
        $totalSlot = $jamPelajaranList->count();

        // 7. Ambil batas jam pulang untuk kelas & hari yang dipilih
        $maxJamKe = null;
        if ($selectedKelas) {
            $maxJamKe = JamPulang::getMaxJamKe($kategoriHari, strtoupper(trim($selectedKelas->tingkat)));
        }

        // 8. Ambil agenda rutin / upacara aktif untuk hari terpilih
        $agendaRutinAktif = AgendaRutin::where('hari', $selectedHari)
            ->where('is_active', true)
            ->get()
            ->keyBy('jam_ke');

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
            'jamPelajaranList',
            'jadwalList',
            'totalSlot',
            'maxJamKe',
            'agendaRutinAktif',
            'pengaturanJadwal'
        ));
    }

    /**
     * Monitoring Slot Jadwal Kosong: cari kelas & slot KBM yang belum di-plot.
     * Menampilkan halaman penuh berisi ringkasan per Kelas -> per Hari -> daftar Jam Ke- kosong.
     */
    public function monitoringSlotKosong(Request $request)
    {
        $tahunAktif = $this->resolveTahunAjaranContext($request);
        $hariList = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];

        $kelasList = Kelas::with('jurusan')
            ->orderBy('tingkat')
            ->orderBy('nama_kelas')
            ->get();

        // Semua jadwal ter-plot pada tahun ajaran aktif.
        $jadwalTerplot = JadwalPelajaran::with('jamPelajaran')
            ->when($tahunAktif, fn ($q) => $q->where('id_tahun_ajaran', $tahunAktif->id))
            ->get();

        // Bucket jam_ke yang sudah ter-plot per (kelas, hari).
        $plotted = [];
        foreach ($jadwalTerplot as $j) {
            $jamKe = $j->jamPelajaran->jam_ke ?? null;
            if ($jamKe !== null) {
                $plotted[$j->id_kelas][$j->hari][$jamKe] = true;
            }
        }

        // Master slot KBM (bukan Istirahat/Upacara) per kategori hari.
        $slotsSeninKamis = JamPelajaran::where('kategori_hari', 'Senin-Kamis')
            ->whereNotIn('jenis', ['istirahat', 'upacara'])
            ->whereNotNull('jam_ke')
            ->get();
        $slotsJumat = JamPelajaran::where('kategori_hari', 'Jumat')
            ->whereNotIn('jenis', ['istirahat', 'upacara'])
            ->whereNotNull('jam_ke')
            ->get();

        // Agenda rutin aktif dikunci per hari -> jam_ke.
        $agendaAktif = AgendaRutin::where('is_active', true)
            ->get()
            ->groupBy('hari')
            ->mapWithKeys(fn ($items, $hari) => [$hari => $items->keyBy('jam_ke')]);

        $rows = [];
        $totalSlotKosong = 0;
        $jumlahKelasLengkap = 0;

        foreach ($kelasList as $kelas) {
            $tingkatSlug = match (strtoupper(trim($kelas->tingkat))) {
                'X'    => '10',
                'XI'   => '11',
                'XII'  => '12',
                default => $kelas->tingkat,
            };

            $punyaKosong = false;

            foreach ($hariList as $hari) {
                $kategori = ($hari === 'Jumat') ? 'Jumat' : 'Senin-Kamis';
                $slots = ($hari === 'Jumat') ? $slotsJumat : $slotsSeninKamis;
                $agendaHari = $agendaAktif->get($hari, collect());
                $maxJamKe = JamPulang::getMaxJamKe($kategori, $tingkatSlug);

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
                    if (!isset($plotted[$kelas->id][$hari][$slot->jam_ke])) {
                        $kosong[] = $slot->jam_ke;
                    }
                }

                if (!empty($kosong)) {
                    $punyaKosong = true;
                    $totalSlotKosong += count($kosong);
                    $rows[] = [
                        'kelas_id'   => $kelas->id,
                        'kelas_nama' => $kelas->nama_kelas,
                        'tingkat'    => $kelas->tingkat,
                        'jurusan'    => $kelas->jurusan->nama_jurusan ?? 'Umum',
                        'hari'       => $hari,
                        'jam_kosong' => array_values($kosong),
                        'jumlah'     => count($kosong),
                    ];
                }
            }

            if (!$punyaKosong) {
                $jumlahKelasLengkap++;
            }
        }

        $totalKelas = $kelasList->count();

        return view('admin.jadwal.monitoring', compact(
            'rows',
            'totalKelas',
            'jumlahKelasLengkap',
            'totalSlotKosong',
            'hariList'
        ));
    }

    /**
     * Simpan plotting jadwal baru (mendukung multi-slot jam sekaligus).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_kelas'       => 'required|exists:kelas,id',
            'hari'           => 'required|in:Senin,Selasa,Rabu,Kamis,Jumat',
            'jam_ke_mulai'   => 'required|integer|min:1|max:20',
            'jam_ke_selesai' => 'required|integer|min:1|max:20|gte:jam_ke_mulai',
            'id_mapel'       => 'required|exists:mata_pelajaran,id',
            'id_guru'        => ['required', Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', User::ROLE_GURU))],
            'id_ruangan'     => 'nullable|exists:ruangans,id',
            'group_id'       => 'nullable|string|max:40',
        ]);

        $tahunAktif = $this->resolveTahunAjaranContext($request);
        $kategoriHari = ($validated['hari'] === 'Jumat') ? 'Jumat' : 'Senin-Kamis';
        $kelas = Kelas::find($validated['id_kelas']);
        $tingkatKelas = $kelas ? match(strtoupper(trim($kelas->tingkat))) { 'X' => '10', 'XI' => '11', 'XII' => '12', default => $kelas->tingkat } : '10';

        // 1. Ambil semua slot KBM dalam rentang jam_ke_mulai s/d jam_ke_selesai (abaikan jenis istirahat)
        $targetSlots = JamPelajaran::where('kategori_hari', $kategoriHari)
            ->whereNotNull('jam_ke')
            ->where('jenis', '!=', 'istirahat')
            ->whereBetween('jam_ke', [$validated['jam_ke_mulai'], $validated['jam_ke_selesai']])
            ->orderBy('jam_mulai')
            ->get();

        if ($targetSlots->isEmpty()) {
            return $this->guardFailure(
                $request,
                'Tidak ditemukan slot KBM pada rentang Jam ke-' . $validated['jam_ke_mulai'] . ' s/d Jam ke-' . $validated['jam_ke_selesai'] . '.',
                ['id_kelas' => $validated['id_kelas'], 'hari' => $validated['hari']]
            );
        }

        $targetJamIds = $targetSlots->pluck('id')->toArray();

        // Mode Edit: saat group_id dikirim, seluruh slot pada grup yang sama diperbarui
        // (rentang baru boleh mengecil/membesar), dan group_id lama dipakai ulang.
        $isEditMode = filled($validated['group_id'] ?? null);
        $groupId    = $isEditMode ? (string) $validated['group_id'] : (string) Str::uuid();

        // 1b. HARD GUARD: slot terkunci multi-sumber dalam rentang (Non-KBM, Agenda Rutin,
        //     Batas Pulang Sekolah, Istirahat di tengah rentang, & slot sudah terisi mapel lain).
        $blockedSlots = $this->detectBlockedSlotsInRange(
            $kategoriHari,
            $targetSlots,
            $validated['hari'],
            (int) $validated['id_kelas'],
            $tahunAktif,
            $tingkatKelas,
            $isEditMode ? $groupId : null
        );

        if (!empty($blockedSlots)) {
            $detail = implode(', ', array_map(function ($b) {
                return 'Jam Ke-' . ($b['jam_ke'] ?? '-') . " ({$b['label']})";
            }, $blockedSlots));

            return $this->guardFailure(
                $request,
                'Gagal menyimpan! Terdapat slot terkunci atau bentrok dalam rentang jam yang dipilih: ' . $detail . '.',
                ['id_kelas' => $validated['id_kelas'], 'hari' => $validated['hari']]
            );
        }

        // 2. Pengecekan bentrok guru di kelas lain pada jam & hari yang sama
        $bentroks = JadwalPelajaran::where('hari', $validated['hari'])
            ->whereIn('id_jam', $targetJamIds)
            ->where('id_guru', $validated['id_guru'])
            ->where('id_kelas', '!=', $validated['id_kelas'])
            ->when($tahunAktif, fn($q) => $q->where('id_tahun_ajaran', $tahunAktif->id))
            ->with(['kelas', 'guru', 'jamPelajaran'])
            ->get();

        if ($bentroks->isNotEmpty()) {
            $namaGuru = $bentroks->first()->guru->nama ?? 'Guru terpilih';
            $bentrokList = $bentroks->map(function ($b) {
                $namaKelas = $b->kelas->nama_kelas ?? 'Kelas Lain';
                $jamKe     = $b->jamPelajaran->jam_ke ?? '-';
                return "Jam ke-{$jamKe} ({$namaKelas})";
            })->implode(', ');

            return $this->guardFailure(
                $request,
                "Bentrok Jadwal! {$namaGuru} sudah memiliki jadwal mengajar pada: {$bentrokList}.",
                ['id_kelas' => $validated['id_kelas'], 'hari' => $validated['hari']]
            );
        }

        // 2b. Pengecekan slot yang sudah terisi jadwal lain pada kelas & hari yang sama.
        //     Saat edit, slot milik grup yang sama dikecualikan (boleh dipilih ulang).
        $slotTerisiLain = JadwalPelajaran::where('id_kelas', $validated['id_kelas'])
            ->where('hari', $validated['hari'])
            ->whereIn('id_jam', $targetJamIds)
            ->when($tahunAktif, fn($q) => $q->where('id_tahun_ajaran', $tahunAktif->id))
            ->when($isEditMode, fn($q) => $q->where(function ($sub) use ($groupId) {
                $sub->where('group_id', '!=', $groupId)
                    ->orWhereNull('group_id');
            }))
            ->with(['mataPelajaran', 'jamPelajaran'])
            ->get();

        if ($slotTerisiLain->isNotEmpty()) {
            $slotBentrok = $slotTerisiLain->first();
            $namaMapel = $slotBentrok->mataPelajaran->nama_mapel ?? 'jadwal lain';
            $jamKe     = $slotBentrok->jamPelajaran->jam_ke ?? '-';

            return $this->guardFailure(
                $request,
                "Slot Jam ke-{$jamKe} pada hari {$validated['hari']} sudah terisi {$namaMapel}. Pilih rentang jam yang tidak menabrak slot yang sudah terisi.",
                ['id_kelas' => $validated['id_kelas'], 'hari' => $validated['hari']]
            );
        }

        // 3. Gunakan 1 group_id (UUID lama saat edit / UUID baru saat tambah) untuk seluruh sesi multi-jam ini
        foreach ($targetSlots as $slot) {
            JadwalPelajaran::withTrashed()->updateOrCreate(
                [
                    'id_kelas'        => $validated['id_kelas'],
                    'hari'            => $validated['hari'],
                    'id_jam'          => $slot->id,
                    'id_tahun_ajaran' => $tahunAktif?->id,
                ],
                [
                    'group_id'   => $groupId,
                    'id_mapel'   => $validated['id_mapel'],
                    'id_guru'    => $validated['id_guru'],
                    'id_ruangan' => $validated['id_ruangan'] ?? null,
                    'deleted_at' => null,
                ]
            );
        }

        $totalJp = $targetSlots->count();
        $pesanRentang = ($validated['jam_ke_mulai'] == $validated['jam_ke_selesai'])
            ? "Jam ke-{$validated['jam_ke_mulai']}"
            : "Jam ke-{$validated['jam_ke_mulai']} s/d Jam ke-{$validated['jam_ke_selesai']}";

        // Mode Edit: bersihkan slot lama pada grup yang berada di luar rentang baru
        // agar tidak tersisa slot yang seharusnya sudah dilepas saat rentang diciutkan.
        if ($isEditMode) {
            JadwalPelajaran::withTrashed()
                ->where('group_id', $groupId)
                ->where('id_kelas', $validated['id_kelas'])
                ->where('hari', $validated['hari'])
                ->where('id_tahun_ajaran', $tahunAktif?->id)
                ->whereNotIn('id_jam', $targetJamIds)
                ->forceDelete();
        }

        $pesanSukses = $isEditMode
            ? "Plotting jadwal berhasil diperbarui untuk {$totalJp} JP ({$pesanRentang})."
            : "Plotting jadwal berhasil disimpan untuk {$totalJp} JP ({$pesanRentang}).";

        return redirect()
            ->route('admin.jadwal.index', ['id_kelas' => $validated['id_kelas'], 'hari' => $validated['hari']])
            ->with('success', $pesanSukses);
    }

    /**
     * Update data plotting jadwal yang sudah ada.
     */
    public function update(Request $request, JadwalPelajaran $jadwalPelajaran)
    {
        $validated = $request->validate([
            'id_kelas'   => 'required|exists:kelas,id',
            'hari'       => 'required|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu',
            'id_jam'     => 'required|exists:jam_pelajaran,id',
            'id_mapel'   => 'required|exists:mata_pelajaran,id',
            'id_guru'    => ['required', Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', User::ROLE_GURU))],
            'id_ruangan' => 'nullable|exists:ruangans,id',
        ]);

        // HARD GUARD: tolak jika slot target terkunci (Non-KBM / Agenda Rutin / melewati batas pulang).
        $slot = JamPelajaran::find($validated['id_jam']);
        $kategoriHari = ($validated['hari'] === 'Jumat') ? 'Jumat' : 'Senin-Kamis';
        $kelasUpdate = Kelas::find($validated['id_kelas']);
        $tingkatUpdate = $kelasUpdate ? match (strtoupper(trim($kelasUpdate->tingkat))) { 'X' => '10', 'XI' => '11', 'XII' => '12', default => $kelasUpdate->tingkat } : '10';

        if ($slot) {
            $agendaUpdate = AgendaRutin::where('hari', $validated['hari'])->where('jam_ke', $slot->jam_ke)->where('is_active', true)->first();
            $maxJamKeUpdate = JamPulang::getMaxJamKe($kategoriHari, $tingkatUpdate);
            $terkunci = ($slot->jenis !== 'kbm')
                || ($agendaUpdate !== null)
                || ($maxJamKeUpdate !== null && $slot->jam_ke !== null && $slot->jam_ke > $maxJamKeUpdate);

            if ($terkunci) {
                return $this->guardFailure(
                    $request,
                    'Gagal menyimpan! Slot tersebut terkunci (non-KBM, agenda rutin, atau melewati batas jam pulang).',
                    ['id_kelas' => $validated['id_kelas'], 'hari' => $validated['hari']]
                );
            }

            // Tolak jika slot sudah terisi jadwal lain (bukan milik grup/dirinya sendiri).
            $terisiLain = JadwalPelajaran::where('id_kelas', $validated['id_kelas'])
                ->where('hari', $validated['hari'])
                ->where('id_jam', $validated['id_jam'])
                ->where('id', '!=', $jadwalPelajaran->id)
                ->when($jadwalPelajaran->id_tahun_ajaran, fn($q) => $q->where('id_tahun_ajaran', $jadwalPelajaran->id_tahun_ajaran))
                ->when($jadwalPelajaran->group_id, fn($q) => $q->where(function ($sub) use ($jadwalPelajaran) {
                    $sub->where('group_id', '!=', $jadwalPelajaran->group_id)->orWhereNull('group_id');
                }))
                ->exists();

            if ($terisiLain) {
                return $this->guardFailure(
                    $request,
                    'Gagal menyimpan! Slot tersebut sudah terisi jadwal lain pada kelas & hari yang sama.',
                    ['id_kelas' => $validated['id_kelas'], 'hari' => $validated['hari']]
                );
            }
        }

        // Pengecekan bentrok guru di kelas lain
        $bentrok = JadwalPelajaran::where('hari', $validated['hari'])
            ->where('id_jam', $validated['id_jam'])
            ->where('id_guru', $validated['id_guru'])
            ->where('id_kelas', '!=', $validated['id_kelas'])
            ->where('id', '!=', $jadwalPelajaran->id)
            ->when($jadwalPelajaran->id_tahun_ajaran, fn($q) => $q->where('id_tahun_ajaran', $jadwalPelajaran->id_tahun_ajaran))
            ->with(['kelas', 'guru', 'jamPelajaran'])
            ->first();

        if ($bentrok) {
            $namaGuru  = $bentrok->guru->nama ?? 'Guru terpilih';
            $namaKelas = $bentrok->kelas->nama_kelas ?? 'kelas lain';
            $jamKe     = $bentrok->jamPelajaran->jam_ke ?? '';
            $infoJam   = $jamKe ? "Jam ke-{$jamKe}" : 'slot jam tersebut';

            return $this->guardFailure(
                $request,
                "Bentrok Jadwal! {$namaGuru} sudah memiliki jadwal mengajar di {$namaKelas} pada {$infoJam}.",
                ['id_kelas' => $jadwalPelajaran->id_kelas, 'hari' => $jadwalPelajaran->hari]
            );
        }

        $jadwalPelajaran->update([
            'id_kelas'   => $validated['id_kelas'],
            'hari'       => $validated['hari'],
            'id_jam'     => $validated['id_jam'],
            'id_mapel'   => $validated['id_mapel'],
            'id_guru'    => $validated['id_guru'],
            'id_ruangan' => $validated['id_ruangan'] ?? null,
        ]);

        return redirect()
            ->route('admin.jadwal.index', ['id_kelas' => $jadwalPelajaran->id_kelas, 'hari' => $jadwalPelajaran->hari])
            ->with('success', 'Jadwal pelajaran berhasil diperbarui.');
    }

    /**
     * Hapus / unplot jadwal pelajaran pada slot tertentu.
     */
    public function destroy(JadwalPelajaran $jadwalPelajaran)
    {
        $idKelas = $jadwalPelajaran->id_kelas;
        $hari    = $jadwalPelajaran->hari;

        $jadwalPelajaran->delete();

        return redirect()
            ->route('admin.jadwal.index', ['id_kelas' => $idKelas, 'hari' => $hari])
            ->with('success', 'Plotting jadwal pada slot tersebut berhasil dikosongkan.');
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
        ?string $exemptGroupId = null
    ): array {
        if ($targetSlots->isEmpty()) {
            return [];
        }

        $agendaAktif = AgendaRutin::where('hari', $hari)
            ->where('is_active', true)
            ->get()
            ->keyBy('jam_ke');

        $maxJamKe = JamPulang::getMaxJamKe($kategoriHari, $tingkatKelas);

        $blocked = [];

        foreach ($targetSlots as $slot) {
            // a) Non-KBM pada master jam
            if ($slot->jenis !== 'kbm') {
                $blocked[] = [
                    'jam_ke' => $slot->jam_ke,
                    'reason' => 'non_kbm',
                    'label'  => strtoupper($slot->jenis ?? 'NON-KBM'),
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
                    'label'  => $label,
                    'detail' => $agenda->nama_agenda ?? 'Agenda Rutin',
                ];
                continue;
            }

            // c) Batas "Pulang Setelah" per tingkat kelas
            if ($maxJamKe !== null && $slot->jam_ke !== null && $slot->jam_ke > $maxJamKe) {
                $blocked[] = [
                    'jam_ke' => $slot->jam_ke,
                    'reason' => 'pulang',
                    'label'  => 'PULANG SEKOLAH',
                    'detail' => "Selesai KBM setelah Jam ke-{$maxJamKe}",
                ];
                continue;
            }

            // d) Sudah terisi mapel lain (kelas + hari + semester), kecuali grup yang sedang di-edit
            $occupiedQuery = JadwalPelajaran::where('id_kelas', $idKelas)
                ->where('hari', $hari)
                ->where('id_jam', $slot->id)
                ->when($tahunAktif, fn($q) => $q->where('id_tahun_ajaran', $tahunAktif->id));

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
                    'label'  => 'SUDAH TERISI',
                    'detail' => $occupied->mataPelajaran->nama_mapel ?? 'Mapel',
                ];
            }
        }

        // e) Istirahat di tengah rentang waktu
        $rangeStart = \Carbon\Carbon::parse($targetSlots->first()->jam_mulai);
        $rangeEnd   = \Carbon\Carbon::parse($targetSlots->last()->jam_selesai);

        $istirahatSpan = JamPelajaran::where('kategori_hari', $kategoriHari)
            ->where('jenis', 'istirahat')
            ->where('jam_mulai', '<', $rangeEnd->format('H:i:s'))
            ->where('jam_selesai', '>', $rangeStart->format('H:i:s'))
            ->first();

        if ($istirahatSpan) {
            $blocked[] = [
                'jam_ke' => null,
                'reason' => 'istirahat',
                'label'  => 'ISTIRAHAT',
                'detail' => $istirahatSpan->rentang_waktu,
            ];
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
    ): \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse {
        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Gagal menyimpan! Terdapat slot terkunci atau bentrok dalam rentang jam yang dipilih.',
            ], 422);
        }

        return redirect()
            ->route('admin.jadwal.index', $redirectParams)
            ->withInput()
            ->with('error', $flashMessage);
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
        if ($request->filled('tahun_ajaran_id')) {
            $tahun = TahunAjaran::find($request->input('tahun_ajaran_id'));
            if ($tahun) {
                session([$sessionKey => $tahun->id]);
                return $tahun;
            }
        }

        // 2. Kombinasi tahun_ajaran + semester dari request
        if ($request->filled('tahun_ajaran') && $request->filled('semester')) {
            $tahun = TahunAjaran::where('tahun_ajaran', $request->input('tahun_ajaran'))
                ->where('semester', $request->input('semester'))
                ->first();
            if ($tahun) {
                session([$sessionKey => $tahun->id]);
                return $tahun;
            }
        }

        // 3. Konteks dari session
        if ($sessionId = session($sessionKey)) {
            $tahun = TahunAjaran::find($sessionId);
            if ($tahun) {
                return $tahun;
            }
        }

        // 4. Default: tahun aktif / baris pertama
        return TahunAjaran::where('is_active', true)->first() ?? TahunAjaran::first();
    }
}
