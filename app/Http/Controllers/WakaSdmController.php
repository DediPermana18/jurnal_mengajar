<?php

namespace App\Http\Controllers;

use App\Models\IzinGuru;
use App\Models\JadwalPelajaran;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\TahunAjaran;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class WakaSdmController extends Controller
{
    /**
     * Authorize access for Waka SDM, Super Admin, and Petugas IT in preview mode.
     */
    protected function authorizeWakaSdm(): void
    {
        $user = Auth::user();

        if (!$user) {
            abort(401, 'Silakan login terlebih dahulu.');
        }

        // Preview role Petugas IT
        if ($user->hasPreviewRole() && $user->previewRole() === 'waka_sdm') {
            return;
        }

        // Admin, Waka SDM, or Super Admin
        $allowed = ($user->role === 'admin' && ($user->sub_role === 'waka_sdm' || $user->sub_role === null))
            || $user->role === 'waka_sdm'
            || in_array($user->role, ['super_admin', 'epic_admin', 'absolute_admin'], true);

        abort_unless($allowed, 403, 'Akses ditolak. Halaman ini khusus untuk Waka SDM / Kepegawaian.');
    }

    /**
     * Map English day name to Indonesian
     */
    protected function getHariIndonesia(Carbon $date): string
    {
        $map = [
            'Monday'    => 'Senin',
            'Tuesday'   => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday'  => 'Kamis',
            'Friday'    => 'Jumat',
            'Saturday'  => 'Sabtu',
            'Sunday'    => 'Minggu',
        ];

        return $map[$date->format('l')] ?? 'Senin';
    }

    /**
     * 1. WAKA SDM DASHBOARD
     */
    public function dashboard(Request $request)
    {
        $this->authorizeWakaSdm();

        $now = Carbon::now();
        $todayStr = $now->toDateString();
        $hariIniStr = $this->getHariIndonesia($now);

        $tahunAktif = TahunAjaran::where('is_active', true)->first() ?? TahunAjaran::first();

        // 1. Total Guru Aktif di database
        $totalGuruAktif = User::where('role', User::ROLE_GURU)
            ->where('is_active', true)
            ->count();

        // 2. Total Guru Terjadwal Hari Ini
        $jadwalHariIni = JadwalPelajaran::with(['guru', 'kelas', 'mapel', 'jamPelajaran'])
            ->where('hari', $hariIniStr)
            ->when($tahunAktif, fn($q) => $q->where('id_tahun_ajaran', $tahunAktif->id))
            ->get();

        $idGuruTerjadwal = $jadwalHariIni->pluck('id_guru')->filter()->unique();
        $totalGuruTerjadwalHariIni = $idGuruTerjadwal->count();

        // 3. Izin Guru Hari Ini
        $daftarIzinHariIni = IzinGuru::with('user')
            ->whereDate('tanggal', $todayStr)
            ->get();

        $guruIzinHariIniIds = $daftarIzinHariIni->where('status', '!=', IzinGuru::STATUS_DITOLAK)
            ->pluck('user_id')
            ->unique();
        $totalGuruIzinHariIni = $guruIzinHariIniIds->count();

        // 4. Jurnal yang sudah diisi hari ini
        $idJadwalHariIni = $jadwalHariIni->pluck('id');
        $jurnalHariIni = Jurnal::with(['guru', 'guruPengganti'])
            ->whereDate('tanggal', $todayStr)
            ->whereIn('id_jadwal', $idJadwalHariIni)
            ->get()
            ->keyBy('id_jadwal');

        // Guru Hadir Hari Ini: Guru terjadwal yang tidak izin atau yang sudah mengisi jurnal hadir
        $guruHadirIds = $idGuruTerjadwal->diff($guruIzinHariIniIds);
        $totalGuruHadirHariIni = $guruHadirIds->count();

        // 5. Total Sesi & Total Kelas Kosong (Belum Diisi Jurnal / Guru Belum Hadir)
        $totalSesiHariIni = $jadwalHariIni->count();
        $sesiTerisiHariIni = 0;
        $sesiKosongHariIni = 0;

        $kelasStatus = [];
        foreach ($jadwalHariIni as $jadwal) {
            $jurnal = $jurnalHariIni->get($jadwal->id);
            $isTerisi = $jurnal && !empty($jurnal->materi);

            if ($isTerisi) {
                $sesiTerisiHariIni++;
            } else {
                $sesiKosongHariIni++;
                $kelasStatus[$jadwal->id_kelas] = true;
            }
        }
        $totalKelasKosong = count($kelasStatus);

        // 6. Grafik Persentase Kehadiran Guru Bulan Ini (Timeline Day 1 to Today)
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();
        $period = CarbonPeriod::create($startOfMonth, $now->copy());

        $chartLabels = [];
        $chartHadirData = [];
        $chartIzinData = [];
        $chartRateData = [];

        $totalHadirBulanIni = 0;
        $totalIzinBulanIni = 0;
        $totalSakitBulanIni = 0;
        $totalDinasBulanIni = 0;
        $totalAlphaBulanIni = 0;

        foreach ($period as $date) {
            $dateStr = $date->toDateString();
            $dayOfWeek = $date->dayOfWeek;

            // Lewati hari Minggu (0) dan Sabtu (6) jika 5 hari kerja
            if ($dayOfWeek === Carbon::SUNDAY || $dayOfWeek === Carbon::SATURDAY) {
                continue;
            }

            $hariName = $this->getHariIndonesia($date);
            $scheduledTeachers = JadwalPelajaran::where('hari', $hariName)
                ->when($tahunAktif, fn($q) => $q->where('id_tahun_ajaran', $tahunAktif->id))
                ->distinct('id_guru')
                ->count('id_guru');

            if ($scheduledTeachers === 0) {
                $scheduledTeachers = max(1, $totalGuruAktif);
            }

            $leaveCount = IzinGuru::whereDate('tanggal', $dateStr)
                ->where('status', '!=', IzinGuru::STATUS_DITOLAK)
                ->distinct('user_id')
                ->count('user_id');

            $presentCount = max(0, $scheduledTeachers - $leaveCount);
            $rate = $scheduledTeachers > 0 ? round(($presentCount / $scheduledTeachers) * 100, 1) : 100;

            $chartLabels[] = $date->format('d M');
            $chartHadirData[] = $presentCount;
            $chartIzinData[] = $leaveCount;
            $chartRateData[] = $rate;

            $totalHadirBulanIni += $presentCount;
            $totalIzinBulanIni += $leaveCount;
        }

        // Breakdown izin bulan ini berdasarkan kategori
        $izinKategoriBulanIni = IzinGuru::whereBetween('tanggal', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->where('status', '!=', IzinGuru::STATUS_DITOLAK)
            ->get();

        foreach ($izinKategoriBulanIni as $iz) {
            if ($iz->kategori_izin === 'sakit') {
                $totalSakitBulanIni++;
            } elseif (in_array($iz->kategori_izin, ['dinas_luar', 'tugas_luar', 'perdin'])) {
                $totalDinasBulanIni++;
            } else {
                // cuti, urusan_keluarga, lainnya
            }
        }

        $persentaseKehadiranBulanIni = ($totalHadirBulanIni + $totalIzinBulanIni) > 0
            ? round(($totalHadirBulanIni / ($totalHadirBulanIni + $totalIzinBulanIni)) * 100, 1)
            : 100;

        // 7. Recent Izin Guru (10 Terakhir)
        $recentIzin = IzinGuru::with('user')
            ->orderBy('tanggal', 'desc')
            ->orderBy('id', 'desc')
            ->take(10)
            ->get();

        // 8. Live Status Monitoring KBM Hari Ini (Semua Sesi Jadwal)
        $monitoringKbmHariIni = $jadwalHariIni->map(function ($jadwal) use ($jurnalHariIni, $todayStr, $daftarIzinHariIni) {
            $jurnal = $jurnalHariIni->get($jadwal->id);
            $izin = $daftarIzinHariIni->firstWhere('user_id', $jadwal->id_guru);

            $statusInfo = Jurnal::hitungStatusPengisian(
                $jurnal,
                $todayStr,
                $jadwal->jamPelajaran?->jam_selesai
            );

            return (object) [
                'jadwal'          => $jadwal,
                'jurnal'          => $jurnal,
                'izin'            => $izin,
                'statusInfo'      => $statusInfo,
                'guru'            => $jadwal->guru,
                'kelas'           => $jadwal->kelas,
                'mapel'           => $jadwal->mapel,
                'jam'             => $jadwal->jamPelajaran,
                'guruPengganti'   => $jurnal?->guruPengganti,
                'statusKehadiran' => $jurnal?->status_kehadiran ?? ($izin ? 'Izin' : 'Belum Absen'),
            ];
        });

        return view('admin.waka-sdm.dashboard', compact(
            'todayStr',
            'hariIniStr',
            'totalGuruAktif',
            'totalGuruTerjadwalHariIni',
            'totalGuruHadirHariIni',
            'totalGuruIzinHariIni',
            'totalKelasKosong',
            'totalSesiHariIni',
            'sesiTerisiHariIni',
            'sesiKosongHariIni',
            'persentaseKehadiranBulanIni',
            'chartLabels',
            'chartHadirData',
            'chartIzinData',
            'chartRateData',
            'totalHadirBulanIni',
            'totalIzinBulanIni',
            'totalSakitBulanIni',
            'totalDinasBulanIni',
            'recentIzin',
            'monitoringKbmHariIni'
        ));
    }

    /**
     * 2. REKAP PERIZINAN & CUTI GURU PAGE
     */
    public function rekapIzin(Request $request)
    {
        $this->authorizeWakaSdm();

        $query = IzinGuru::with([
            'user',
            'approverPiket',
            'approverWaka',
            'approverKepsek',
        ]);

        // Filter Tanggal Spesifik
        if ($request->filled('tanggal')) {
            $query->whereDate('tanggal', $request->input('tanggal'));
        }

        // Filter Rentang Tanggal / Bulan
        $tanggalMulai = $request->input('tanggal_mulai');
        $tanggalSelesai = $request->input('tanggal_selesai');

        if ($request->filled('bulan') && $request->filled('tahun')) {
            $bulan = (int) $request->input('bulan');
            $tahun = (int) $request->input('tahun');
            $start = Carbon::createFromDate($tahun, $bulan, 1)->startOfMonth()->toDateString();
            $end = Carbon::createFromDate($tahun, $bulan, 1)->endOfMonth()->toDateString();
            $query->whereBetween('tanggal', [$start, $end]);
        } elseif ($tanggalMulai && $tanggalSelesai) {
            $query->whereBetween('tanggal', [$tanggalMulai, $tanggalSelesai]);
        } elseif ($tanggalMulai) {
            $query->whereDate('tanggal', '>=', $tanggalMulai);
        } elseif ($tanggalSelesai) {
            $query->whereDate('tanggal', '<=', $tanggalSelesai);
        }

        // Filter Nama Guru
        if ($request->filled('id_guru')) {
            $query->where('user_id', $request->input('id_guru'));
        }

        // Filter Kategori Izin
        if ($request->filled('kategori_izin') && $request->input('kategori_izin') !== 'semua') {
            $kategori = $request->input('kategori_izin');
            if ($kategori === 'perdin') {
                $query->whereIn('kategori_izin', ['dinas_luar', 'tugas_luar', 'perdin']);
            } else {
                $query->where('kategori_izin', $kategori);
            }
        }

        // Filter Status Approval
        if ($request->filled('status') && $request->input('status') !== 'semua') {
            $query->where('status', $request->input('status'));
        }

        // Search text
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($uq) use ($search) {
                    $uq->where('nama', 'like', "%{$search}%")
                        ->orWhere('nip', 'like', "%{$search}%");
                })
                ->orWhere('alasan', 'like', "%{$search}%")
                ->orWhere('keterangan', 'like', "%{$search}%");
            });
        }

        // Summary counts
        $totalPengajuan = (clone $query)->count();
        $totalDisetujui = (clone $query)->where('status', IzinGuru::STATUS_DISETUJUI)->count();
        $totalPending   = (clone $query)->whereIn('status', [
            IzinGuru::STATUS_PENDING_PIKET,
            IzinGuru::STATUS_PENDING_WAKA,
            IzinGuru::STATUS_PENDING_KEPSEK,
        ])->count();
        $totalDitolak   = (clone $query)->where('status', IzinGuru::STATUS_DITOLAK)->count();

        // Paginate results
        $daftarIzin = $query->orderBy('tanggal', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString();

        // Cari pengganti piket / guru pengganti untuk tiap izin di tanggal tsb
        $daftarIzin->getCollection()->transform(function ($izin) {
            $tanggal = $izin->tanggal ? $izin->tanggal->toDateString() : null;
            if ($tanggal) {
                $jurnalPengganti = Jurnal::with('guruPengganti')
                    ->whereDate('tanggal', $tanggal)
                    ->where('id_guru', $izin->user_id)
                    ->whereNotNull('id_guru_pengganti')
                    ->first();

                $izin->guru_pengganti_cover = $jurnalPengganti?->guruPengganti?->nama;
            } else {
                $izin->guru_pengganti_cover = null;
            }
            return $izin;
        });

        $guruList = User::where('role', User::ROLE_GURU)->orderBy('nama')->get();

        return view('admin.waka-sdm.rekap-izin', compact(
            'daftarIzin',
            'guruList',
            'totalPengajuan',
            'totalDisetujui',
            'totalPending',
            'totalDitolak'
        ));
    }

    /**
     * Helper to compute teacher performance statistics for a given month/year
     */
    protected function hitungPerformaGuruBulanan(int $bulan, int $tahun, ?int $idGuru = null): array
    {
        $startOfMonth = Carbon::createFromDate($tahun, $bulan, 1)->startOfMonth();
        $endOfMonth = Carbon::createFromDate($tahun, $bulan, 1)->endOfMonth();

        $tahunAktif = TahunAjaran::where('is_active', true)->first() ?? TahunAjaran::first();

        // Hitung frekuensi tiap hari sekolah (Senin - Jumat) dalam bulan tersebut
        $dayOccurrences = [
            'Senin'  => 0,
            'Selasa' => 0,
            'Rabu'   => 0,
            'Kamis'  => 0,
            'Jumat'  => 0,
            'Sabtu'  => 0,
        ];

        $period = CarbonPeriod::create($startOfMonth, $endOfMonth);
        foreach ($period as $date) {
            $hari = $this->getHariIndonesia($date);
            if (isset($dayOccurrences[$hari])) {
                $dayOccurrences[$hari]++;
            }
        }

        // Query Guru
        $guruQuery = User::where('role', User::ROLE_GURU)
            ->where('is_active', true)
            ->with(['mapelDiampu', 'jadwalPelajaran' => function ($q) use ($tahunAktif) {
                if ($tahunAktif) {
                    $q->where('id_tahun_ajaran', $tahunAktif->id);
                }
            }])
            ->orderBy('nama');

        if ($idGuru) {
            $guruQuery->where('id', $idGuru);
        }

        $gurus = $guruQuery->get();

        $dataRekap = [];
        $grandTotalJpWajib = 0;
        $grandTotalJpTerealisasi = 0;
        $grandTotalJpCover = 0;
        $grandTotalJpIzin = 0;
        $grandTotalJpAlpha = 0;

        foreach ($gurus as $guru) {
            // 1. Hitung JP Wajib berdasarkan plotting jadwal mingguan * frekuensi hari di bulan tsb
            $jadwals = $guru->jadwalPelajaran;
            $jpWajib = 0;
            $jadwalPerHari = [];

            foreach ($jadwals as $jdw) {
                $jpWajib += ($dayOccurrences[$jdw->hari] ?? 0);
                $jadwalPerHari[$jdw->hari] = ($jadwalPerHari[$jdw->hari] ?? 0) + 1;
            }

            // 2. Query Jurnal yang diisi oleh guru ini atau untuk jadwal guru ini pada bulan tersebut
            $jurnalList = Jurnal::with('guruPengganti')
                ->whereBetween('tanggal', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
                ->where(function ($q) use ($guru) {
                    $q->where('id_guru', $guru->id)
                      ->orWhereHas('jadwalPelajaran', fn($j) => $j->where('id_guru', $guru->id));
                })
                ->get();

            $jpTerealisasi = 0;
            $jpCover = 0;
            $jpIzinJurnal = 0;
            $jpTerlambat = 0;

            foreach ($jurnalList as $jurn) {
                $isOriginalGuru = ($jurn->id_guru == $guru->id);
                $hasCover = !empty($jurn->id_guru_pengganti) && $jurn->id_guru_pengganti != $guru->id;

                if ($jurn->status_kehadiran === 'Hadir' && !empty($jurn->materi)) {
                    if ($hasCover && !$isOriginalGuru) {
                        $jpCover++;
                    } else {
                        $jpTerealisasi++;
                    }
                } elseif (in_array($jurn->status_kehadiran, ['Izin', 'Sakit', 'Disposisi'])) {
                    $jpIzinJurnal++;
                    if ($hasCover) {
                        $jpCover++;
                    }
                }
            }

            // 3. Izin resmi dari tabel izin_guru pada bulan tersebut
            $izinList = IzinGuru::where('user_id', $guru->id)
                ->whereBetween('tanggal', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
                ->where('status', IzinGuru::STATUS_DISETUJUI)
                ->get();

            $jpIzinResmi = 0;
            foreach ($izinList as $iz) {
                $hariIz = $this->getHariIndonesia($iz->tanggal);
                $jpIzinResmi += ($jadwalPerHari[$hariIz] ?? 0);
            }

            $jpIzin = max($jpIzinJurnal, $jpIzinResmi);
            $jpTerpenuhi = $jpTerealisasi + $jpCover;
            $jpAlpha = max(0, $jpWajib - ($jpTerpenuhi + $jpIzin));

            // Persentase Kedisiplinan / Realisasi KBM
            $persentase = $jpWajib > 0
                ? min(100, round(($jpTerpenuhi / $jpWajib) * 100, 1))
                : 100;

            // Rating Kinerja
            if ($persentase >= 90) {
                $kategoriKinerja = 'Sangat Baik';
                $badgeClass = 'bg-success-subtle text-success border border-success-subtle';
            } elseif ($persentase >= 75) {
                $kategoriKinerja = 'Baik';
                $badgeClass = 'bg-primary-subtle text-primary border border-primary-subtle';
            } elseif ($persentase >= 60) {
                $kategoriKinerja = 'Cukup';
                $badgeClass = 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
            } else {
                $kategoriKinerja = 'Perlu Evaluasi';
                $badgeClass = 'bg-danger-subtle text-danger border border-danger-subtle';
            }

            $dataRekap[] = (object) [
                'guru'            => $guru,
                'mapel'           => $guru->mapelDiampu->unique('id')->pluck('nama_mapel')->implode(', ') ?: '-',
                'jpWajib'         => $jpWajib,
                'jpTerealisasi'   => $jpTerealisasi,
                'jpCover'         => $jpCover,
                'jpIzin'          => $jpIzin,
                'jpAlpha'         => $jpAlpha,
                'persentase'      => $persentase,
                'kategoriKinerja' => $kategoriKinerja,
                'badgeClass'      => $badgeClass,
            ];

            $grandTotalJpWajib += $jpWajib;
            $grandTotalJpTerealisasi += $jpTerealisasi;
            $grandTotalJpCover += $jpCover;
            $grandTotalJpIzin += $jpIzin;
            $grandTotalJpAlpha += $jpAlpha;
        }

        $rataRataKedisiplinan = count($dataRekap) > 0
            ? round(collect($dataRekap)->avg('persentase'), 1)
            : 0;

        return [
            'dataRekap'               => $dataRekap,
            'grandTotalJpWajib'       => $grandTotalJpWajib,
            'grandTotalJpTerealisasi' => $grandTotalJpTerealisasi,
            'grandTotalJpCover'       => $grandTotalJpCover,
            'grandTotalJpIzin'        => $grandTotalJpIzin,
            'grandTotalJpAlpha'       => $grandTotalJpAlpha,
            'rataRataKedisiplinan'    => $rataRataKedisiplinan,
            'bulan'                   => $bulan,
            'tahun'                   => $tahun,
            'namaBulan'               => Carbon::createFromDate($tahun, $bulan, 1)->translatedFormat('F'),
            'tahunAktif'              => $tahunAktif,
        ];
    }

    /**
     * 3. REKAP PRESENSI & JURNAL KBM GURU
     */
    public function rekapPresensiGuru(Request $request)
    {
        $this->authorizeWakaSdm();

        $bulan = (int) $request->input('bulan', Carbon::now()->month);
        $tahun = (int) $request->input('tahun', Carbon::now()->year);
        $idGuru = $request->filled('id_guru') ? (int) $request->input('id_guru') : null;

        $stats = $this->hitungPerformaGuruBulanan($bulan, $tahun, $idGuru);
        $guruList = User::where('role', User::ROLE_GURU)->orderBy('nama')->get();

        return view('admin.waka-sdm.rekap-presensi-guru', array_merge($stats, [
            'guruList'   => $guruList,
            'selectedGuru' => $idGuru,
        ]));
    }

    /**
     * Export Excel (.xls) Laporan Bulanan Kedisiplinan Guru
     */
    public function exportExcelPresensi(Request $request)
    {
        $this->authorizeWakaSdm();

        $bulan = (int) $request->input('bulan', Carbon::now()->month);
        $tahun = (int) $request->input('tahun', Carbon::now()->year);
        $idGuru = $request->filled('id_guru') ? (int) $request->input('id_guru') : null;

        $stats = $this->hitungPerformaGuruBulanan($bulan, $tahun, $idGuru);

        $html = "\xEF\xBB\xBF" . view('admin.waka-sdm.excel-presensi-guru', $stats)->render();

        $filename = 'laporan-kedisiplinan-guru-' . str_pad($bulan, 2, '0', STR_PAD_LEFT) . '-' . $tahun . '.xls';

        return response($html)
            ->header('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Cache-Control', 'max-age=0');
    }

    /**
     * Print PDF Laporan Bulanan Kedisiplinan Guru kepada Kepala Sekolah
     */
    public function printPresensi(Request $request)
    {
        $this->authorizeWakaSdm();

        $bulan = (int) $request->input('bulan', Carbon::now()->month);
        $tahun = (int) $request->input('tahun', Carbon::now()->year);
        $idGuru = $request->filled('id_guru') ? (int) $request->input('id_guru') : null;

        $stats = $this->hitungPerformaGuruBulanan($bulan, $tahun, $idGuru);

        $kepsek = User::where('role', 'kepala_sekolah')
            ->orWhere('sub_role', 'kepala_sekolah')
            ->first();

        $wakaSdm = Auth::user();

        return view('admin.waka-sdm.print-presensi-guru', array_merge($stats, [
            'kepsek'  => $kepsek,
            'wakaSdm' => $wakaSdm,
        ]));
    }

    /**
     * Show Lampiran / Bukti Surat Izin Guru
     */
    public function showLampiran($id)
    {
        $this->authorizeWakaSdm();

        $izin = IzinGuru::findOrFail($id);

        if (!$izin->lampiran) {
            abort(404, 'Lampiran surat tidak ditemukan.');
        }

        if (Storage::disk('public')->exists($izin->lampiran)) {
            return Storage::disk('public')->response($izin->lampiran);
        }

        if (Storage::disk('local')->exists($izin->lampiran)) {
            return Storage::disk('local')->response($izin->lampiran);
        }

        abort(404, 'File lampiran fisik tidak ditemukan di server.');
    }
}
