<?php

namespace App\Http\Controllers\Kurikulum;

use App\Http\Controllers\Controller;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\MataPelajaran;
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

        // 4. Ambil master jam pelajaran sesuai tingkat kelas
        $jamPelajaranList = JamPelajaran::where('kategori_hari', $kategoriHari)
            ->where(function ($q) use ($tingkatKelas) {
                $q->where('tingkat', $tingkatKelas)
                  ->orWhereNull('tingkat');
            })
            ->orderBy('jam_mulai')
            ->get();

        // 5. Ambil data jadwal pelajaran yang sudah di-plot
        $jadwalList = collect();
        if ($selectedKelas) {
            $jadwalList = JadwalPelajaran::with(['mataPelajaran', 'guru', 'jamPelajaran'])
                ->where('id_kelas', $selectedKelas->id)
                ->where('hari', $selectedHari)
                ->when($tahunAktif, fn($q) => $q->where('id_tahun_ajaran', $tahunAktif->id))
                ->get()
                ->keyBy('id_jam');
        }

        // 6. Hitung statistik plotting
        $totalSlot = $jamPelajaranList->count();
        $totalKbm = $jamPelajaranList->where('jenis', '!=', 'istirahat')->count();
        $totalTerisi = $jadwalList->count();
        $persentase = $totalKbm > 0 ? round(($totalTerisi / $totalKbm) * 100) : 0;

        return view('kurikulum.jadwal.index', compact(
            'kelasList',
            'mapelList',
            'guruList',
            'tahunAktif',
            'hariList',
            'selectedHari',
            'selectedKelas',
            'jamPelajaranList',
            'jadwalList',
            'totalSlot',
            'totalKbm',
            'totalTerisi',
            'persentase'
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
        ]);

        $tahunAktif = TahunAjaran::where('status_aktif', true)->first() ?? TahunAjaran::first();
        $kategoriHari = ($validated['hari'] === 'Jumat') ? 'Jumat' : 'Senin-Kamis';
        $kelas = Kelas::find($validated['id_kelas']);
        $tingkatKelas = $kelas ? match(strtoupper(trim($kelas->tingkat))) { 'X' => '10', 'XI' => '11', 'XII' => '12', default => $kelas->tingkat } : '10';

        // 1. Ambil semua slot KBM dalam rentang jam_ke_mulai s/d jam_ke_selesai (abaikan jenis istirahat)
        $targetSlots = JamPelajaran::where('kategori_hari', $kategoriHari)
            ->where(function ($q) use ($tingkatKelas) {
                $q->where('tingkat', $tingkatKelas)
                  ->orWhereNull('tingkat');
            })
            ->whereNotNull('jam_ke')
            ->where('jenis', '!=', 'istirahat')
            ->whereBetween('jam_ke', [$validated['jam_ke_mulai'], $validated['jam_ke_selesai']])
            ->orderBy('jam_mulai')
            ->get();

        if ($targetSlots->isEmpty()) {
            return redirect()
                ->route('kurikulum.jadwal.index', ['id_kelas' => $validated['id_kelas'], 'hari' => $validated['hari']])
                ->withInput()
                ->with('error', 'Tidak ditemukan slot KBM pada rentang Jam ke-' . $validated['jam_ke_mulai'] . ' s/d Jam ke-' . $validated['jam_ke_selesai'] . '.');
        }

        $targetJamIds = $targetSlots->pluck('id')->toArray();

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
                ->route('kurikulum.jadwal.index', ['id_kelas' => $validated['id_kelas'], 'hari' => $validated['hari']])
                ->withInput()
                ->with('error', "Bentrok Jadwal! {$namaGuru} sudah memiliki jadwal mengajar pada: {$bentrokList}.");
        }

        // 3. Gunakan 1 group_id UUID yang sama untuk seluruh sesi multi-jam ini
        $groupId = (string) Str::uuid();

        foreach ($targetSlots as $slot) {
            JadwalPelajaran::updateOrCreate(
                [
                    'id_kelas'        => $validated['id_kelas'],
                    'hari'            => $validated['hari'],
                    'id_jam'          => $slot->id,
                    'id_tahun_ajaran' => $tahunAktif?->id,
                ],
                [
                    'group_id' => $groupId,
                    'id_mapel' => $validated['id_mapel'],
                    'id_guru'  => $validated['id_guru'],
                ]
            );
        }

        $totalJp = $targetSlots->count();
        $pesanRentang = ($validated['jam_ke_mulai'] == $validated['jam_ke_selesai'])
            ? "Jam ke-{$validated['jam_ke_mulai']}"
            : "Jam ke-{$validated['jam_ke_mulai']} s/d Jam ke-{$validated['jam_ke_selesai']}";

        return redirect()
            ->route('kurikulum.jadwal.index', ['id_kelas' => $validated['id_kelas'], 'hari' => $validated['hari']])
            ->with('success', "Plotting jadwal berhasil disimpan untuk {$totalJp} JP ({$pesanRentang}).");
    }

    /**
     * Update data plotting jadwal yang sudah ada.
     */
    public function update(Request $request, JadwalPelajaran $jadwalPelajaran)
    {
        $validated = $request->validate([
            'id_kelas' => 'required|exists:kelas,id',
            'hari'     => 'required|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu',
            'id_jam'   => 'required|exists:jam_pelajaran,id',
            'id_mapel' => 'required|exists:mata_pelajaran,id',
            'id_guru'  => ['required', Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', User::ROLE_GURU))],
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
                ->route('kurikulum.jadwal.index', ['id_kelas' => $jadwalPelajaran->id_kelas, 'hari' => $jadwalPelajaran->hari])
                ->with('error', "Bentrok Jadwal! {$namaGuru} sudah memiliki jadwal mengajar di {$namaKelas} pada {$infoJam}.");
        }

        $jadwalPelajaran->update([
            'id_kelas' => $validated['id_kelas'],
            'hari'     => $validated['hari'],
            'id_jam'   => $validated['id_jam'],
            'id_mapel' => $validated['id_mapel'],
            'id_guru'  => $validated['id_guru'],
        ]);

        return redirect()
            ->route('kurikulum.jadwal.index', ['id_kelas' => $jadwalPelajaran->id_kelas, 'hari' => $jadwalPelajaran->hari])
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
            ->route('kurikulum.jadwal.index', ['id_kelas' => $idKelas, 'hari' => $hari])
            ->with('success', 'Plotting jadwal pada slot tersebut berhasil dikosongkan.');
    }
}
