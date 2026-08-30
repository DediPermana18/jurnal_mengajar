<?php

namespace App\Http\Controllers;

use App\Models\AbsensiJurnal;
use App\Models\CatatanSiswaBermasalah;
use App\Models\CatatanTerlambat;
use App\Models\DispensasiSiswa;
use App\Models\Jurnal;
use App\Models\PenerimaTerlambat;
use App\Models\Siswa;
use Carbon\Carbon;
use Illuminate\Http\Request;

class WaliKelasController extends Controller
{
    /**
     * Dashboard Wali Kelas: ringkasan dispensasi siswa hari ini.
     */
    public function dashboard()
    {
        $user = auth()->user();
        abort_unless($user && $user->isWaliKelas(), 403, 'Akses ditolak. Halaman ini khusus untuk Wali Kelas.');

        $kelasSaya = $user->kelasWali()->get();
        $kelasIds  = $kelasSaya->pluck('id');
        $today     = Carbon::today()->toDateString();

        $dispensasiHariIni = DispensasiSiswa::with(['siswa.kelas', 'jadwal.mapel', 'guruPiket'])
            ->whereDate('tanggal', $today)
            ->when($kelasIds->isNotEmpty(), fn ($q) => $q->whereHas('siswa', fn ($sq) => $sq->whereIn('id_kelas', $kelasIds)))
            ->orderBy('jam_ke')
            ->orderBy('id', 'desc')
            ->get()
            ->groupBy(fn ($d) => $d->siswa?->kelas?->nama ?? 'Tanpa Kelas');

        $jumlahDispen = $dispensasiHariIni->flatten(1)->count();
        $jumlahDisetujui = $dispensasiHariIni->flatten(1)
            ->where('status', DispensasiSiswa::STATUS_DISETUJUI)
            ->count();

        $totalSiswa = $kelasIds->isEmpty()
            ? 0
            : Siswa::whereIn('id_kelas', $kelasIds)->count();

        $namaKelasSaya = $kelasSaya->pluck('nama_lengkap')->implode(', ');
        $namaKelasSingkat = $kelasSaya->pluck('nama')->implode(', ');

        $terlambatHariIni = $kelasIds->isEmpty()
            ? 0
            : CatatanTerlambat::whereDate('tanggal', $today)
                ->whereHas('siswa', fn ($sq) => $sq->whereIn('id_kelas', $kelasIds))
                ->count();

        $perluPerhatian = $kelasIds->isEmpty()
            ? 0
            : Siswa::whereIn('id_kelas', $kelasIds)
                ->withCount(['catatanTerlambat', 'catatanBermasalah'])
                ->get()
                ->filter(fn ($s) => $s->catatan_terlambat_count > 3 || $s->catatan_bermasalah_count > 0)
                ->count();

        return view('walikelas.dashboard', compact(
            'kelasSaya',
            'dispensasiHariIni',
            'jumlahDispen',
            'jumlahDisetujui',
            'today',
            'totalSiswa',
            'namaKelasSaya',
            'namaKelasSingkat',
            'terlambatHariIni',
            'perluPerhatian'
        ));
    }

    /**
     * Rekap Absen Siswa Wali Kelas
     */
    public function rekapAbsen()
    {
        $user = auth()->user();
        abort_unless($user && $user->isWaliKelas(), 403, 'Akses ditolak. Halaman ini khusus untuk Wali Kelas.');

        $kelasSaya = $user->kelasWali()->get();
        $kelasIds  = $kelasSaya->pluck('id');
        $namaKelasSaya = $kelasSaya->pluck('nama_lengkap')->implode(', ');

        $daftarSiswa = Siswa::with('kelas')
            ->whereIn('id_kelas', $kelasIds)
            ->orderBy('nama')
            ->get();

        $rekapAbsen = [];

        foreach ($daftarSiswa as $siswa) {
            // Status presensi akumulatif dari seluruh jurnal guru mapel untuk siswa ini.
            $statusCounts = AbsensiJurnal::where('id_siswa', $siswa->id)
                ->selectRaw("status, COUNT(*) as jumlah")
                ->groupBy('status')
                ->pluck('jumlah', 'status');

            $hadir = (int) $statusCounts->get('Hadir', 0);
            $izin  = (int) $statusCounts->get('Izin', 0);
            $sakit = (int) $statusCounts->get('Sakit', 0);
            $alpha = (int) $statusCounts->get('Alpa', 0) + (int) $statusCounts->get('Alpha', 0);

            $total   = $hadir + $izin + $sakit + $alpha;
            $persen  = $total > 0 ? round(($hadir / $total) * 100, 1) : 100.0;

            $rekapAbsen[] = [
                'siswa'       => $siswa,
                'hadir'       => $hadir,
                'izin'        => $izin,
                'sakit'       => $sakit,
                'alpha'       => $alpha,
                'total'       => $total,
                'persentase'  => $persen,
                'nama_kelas'  => $siswa->kelas?->nama ?? '-',
            ];
        }

        $rekapAbsen = collect($rekapAbsen);

        return view('walikelas.rekap_absen', compact('rekapAbsen', 'namaKelasSaya'));
    }

    /**
     * Riwayat Jurnal Kelas
     */
    public function riwayatJurnal()
    {
        $user = auth()->user();
        abort_unless($user && $user->isWaliKelas(), 403, 'Akses ditolak. Halaman ini khusus untuk Wali Kelas.');

        $kelasSaya = $user->kelasWali()->get();
        $kelasIds  = $kelasSaya->pluck('id');
        $namaKelasSaya = $kelasSaya->pluck('nama_lengkap')->implode(', ');

        // Jurnal mengajar yang dilaksanakan HANYA di kelas bimbingan wali kelas ini.
        $jurnal = Jurnal::with([
                'jadwalPelajaran.mapel',
                'jadwalPelajaran.kelas',
                'guru',
                'guruPengganti',
                'absensiJurnal',
            ])
            ->whereHas('jadwalPelajaran', fn ($q) => $q->whereIn('id_kelas', $kelasIds))
            ->orderBy('tanggal', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $daftarJurnal = $jurnal->map(function (Jurnal $j) {
            $absensi   = $j->absensiJurnal;
            $hadir     = $absensi->where('status', 'Hadir')->count();
            $totalKehadiran = $absensi->whereIn('status', ['Hadir', 'Izin', 'Sakit', 'Alpa', 'Alpha', 'Dispen'])->count();
            $totalSiswa = $absensi->count();

            $guruPengajar = $j->guruPengganti ?: $j->guru;

            return [
                'jurnal'        => $j,
                'tanggal'       => $j->tanggal,
                'mapel'         => $j->jadwalPelajaran?->mapel?->nama_mapel ?? '-',
                'guru_pengajar' => $guruPengajar?->nama ?? '-',
                'materi'        => $j->materi ?: '-',
                'hadir'         => $hadir,
                'total_siswa'   => $totalSiswa,
                'ratio_label'   => $totalKehadiran > 0 ? "{$totalKehadiran}/{$totalSiswa} Siswa" : '0/0 Siswa',
            ];
        });

        return view('walikelas.riwayat_jurnal', compact('daftarJurnal', 'namaKelasSaya'));
    }

    /**
     * Catatan Siswa Bermasalah — data REALTIME dari Database:
     * - Siswa pada kelas wali kelas ini (auth()->user()->kelasWali()).
     * - Rekap keterlambatan dari CatatanTerlambat (input Satpam) yang penerimanya
     *   mencakup Wali Kelas ini (via penerima_catatan_terlambat, peran guru_piket/wali_kelas).
     * - Rekap Alpha (Alpa) dari AbsensiJurnal guru mapel.
     * - Rekap Dispensasi dari DispensasiSiswa.
     * + Catatan tindak lanjut (panggil ortu) yang tersimpan di DB.
     */
    public function siswaBermasalah()
    {
        $user = auth()->user();
        abort_unless($user && $user->isWaliKelas(), 403, 'Akses ditolak. Halaman ini khusus untuk Wali Kelas.');

        $kelasWali = $user->kelasWali()->with('siswa')->get();
        $kelasIds  = $kelasWali->pluck('id');

        $daftarSiswa = Siswa::with('kelas')
            ->whereIn('id_kelas', $kelasIds)
            ->orderBy('nama')
            ->get();

        $statistikTindakLanjut = CatatanSiswaBermasalah::where('id_wali_kelas', $user->id)
            ->get(['id_siswa', 'jenis_tindakan', 'catatan', 'status', 'updated_at'])
            ->keyBy('id_siswa');

        // --- PETA DATA PER SISWA ---
        $rekap = [];

        foreach ($daftarSiswa as $siswa) {
            $riwayatTerlambat = CatatanTerlambat::with('satpam')
                ->where('id_siswa', $siswa->id)
                ->whereHas('penerima', fn ($q) => $q->where('user_id', $user->id))
                ->orderBy('tanggal', 'desc')
                ->orderBy('jam_masuk', 'desc')
                ->get();

            $totalTerlambat = $riwayatTerlambat->count();

            // Alpha: status 'Alpa'/'Alpha' dari presensi jurnal guru mapel.
            $totalAlpha = AbsensiJurnal::where('id_siswa', $siswa->id)
                ->whereIn('status', ['Alpa', 'Alpha'])
                ->count();

            $dispensasi = DispensasiSiswa::where('id_siswa', $siswa->id)
                ->where('status', DispensasiSiswa::STATUS_DISETUJUI)
                ->orderBy('tanggal', 'desc')
                ->get();

            $rekap[] = [
                'siswa'             => $siswa,
                'riwayat_terlambat' => $riwayatTerlambat,
                'total_terlambat'   => $totalTerlambat,
                'total_alpha'       => $totalAlpha,
                'total_dispen'      => $dispensasi->count(),
                'dispensasi'        => $dispensasi,
                'tindak_lanjut'     => $statistikTindakLanjut->get($siswa->id),
            ];
        }

        return view('walikelas.siswa_bermasalah', compact('kelasWali', 'rekap', 'statistikTindakLanjut'));
    }

    /**
     * Simpan tindak lanjut (Panggil Ortu / Tambah Catatan) untuk siswa.
     */
    public function siswaBermasalahStore(Request $request)
    {
        $user = auth()->user();
        abort_unless($user && $user->isWaliKelas(), 403, 'Akses ditolak. Halaman ini khusus untuk Wali Kelas.');

        $validated = $request->validate([
            'id_siswa'      => 'required|exists:siswa,id',
            'jenis_tindakan' => 'required|in:panggil_ortu,catatan',
            'catatan'       => 'nullable|string|max:1000',
            'status'        => 'required|in:belum,dipanggil,selesai',
        ], [
            'id_siswa.required'       => 'Siswa wajib dipilih.',
            'jenis_tindakan.required' => 'Jenis tindakan wajib dipilih.',
            'catatan.max'             => 'Catatan maksimal :max karakter.',
            'status.required'         => 'Status tindak lanjut wajib dipilih.',
        ]);

        $kelasIds = $user->kelasWali()->pluck('id');
        $siswa = Siswa::where('id', $validated['id_siswa'])->whereIn('id_kelas', $kelasIds)->first();

        abort_unless($siswa, 403, 'Siswa bukan bagian dari kelas wali kelas Anda.');

        CatatanSiswaBermasalah::updateOrCreate(
            ['id_siswa' => $siswa->id, 'id_wali_kelas' => $user->id],
            [
                'jenis_tindakan' => $validated['jenis_tindakan'],
                'catatan'        => $validated['catatan'] ?? null,
                'status'         => $validated['status'],
            ]
        );

        return back()->with('success', 'Tindak lanjut untuk "' . $siswa->nama . '" berhasil disimpan.');
    }
}
