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

        $tahunAktif = TahunAjaran::where('status_aktif', true)->first() ?? TahunAjaran::first();

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
        $tahunAktif = TahunAjaran::where('status_aktif', true)->first() ?? TahunAjaran::first();
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

        $tahunAktif = TahunAjaran::where('status_aktif', true)->first() ?? TahunAjaran::first();
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
            return redirect()
                ->route('admin.jadwal.index', ['id_kelas' => $validated['id_kelas'], 'hari' => $validated['hari']])
                ->withInput()
                ->with('error', 'Tidak ditemukan slot KBM pada rentang Jam ke-' . $validated['jam_ke_mulai'] . ' s/d Jam ke-' . $validated['jam_ke_selesai'] . '.');
        }

        $targetJamIds = $targetSlots->pluck('id')->toArray();

        // Mode Edit: saat group_id dikirim, seluruh slot pada grup yang sama diperbarui
        // (rentang baru boleh mengecil/membesar), dan group_id lama dipakai ulang.
        $isEditMode = filled($validated['group_id'] ?? null);
        $groupId    = $isEditMode ? (string) $validated['group_id'] : (string) Str::uuid();

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

            return redirect()
                ->route('admin.jadwal.index', ['id_kelas' => $validated['id_kelas'], 'hari' => $validated['hari']])
                ->withInput()
                ->with('error', "Bentrok Jadwal! {$namaGuru} sudah memiliki jadwal mengajar pada: {$bentrokList}.");
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

            return redirect()
                ->route('admin.jadwal.index', ['id_kelas' => $validated['id_kelas'], 'hari' => $validated['hari']])
                ->withInput()
                ->with('error', "Slot Jam ke-{$jamKe} pada hari {$validated['hari']} sudah terisi {$namaMapel}. Pilih rentang jam yang tidak menabrak slot yang sudah terisi.");
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

            return redirect()
                ->route('admin.jadwal.index', ['id_kelas' => $jadwalPelajaran->id_kelas, 'hari' => $jadwalPelajaran->hari])
                ->with('error', "Bentrok Jadwal! {$namaGuru} sudah memiliki jadwal mengajar di {$namaKelas} pada {$infoJam}.");
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
}
