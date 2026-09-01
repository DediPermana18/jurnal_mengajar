<?php

namespace App\Http\Controllers\Kurikulum;

use App\Http\Controllers\Controller;
use App\Models\IzinGuru;
use App\Models\JadwalPelajaran;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\PengaturanJadwal;
use App\Models\TahunAjaran;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class KurikulumDashboardController extends Controller
{
    /**
     * Tampilkan Dashboard Waka Kurikulum.
     */
    public function index(Request $request)
    {
        $now = Carbon::now();

        $mapHariIni = [
            'Monday'    => 'Senin',
            'Tuesday'   => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday'  => 'Kamis',
            'Friday'    => 'Jumat',
            'Saturday'  => 'Sabtu',
            'Sunday'    => 'Minggu',
        ];

        $hariIniStr = $mapHariIni[$now->format('l')] ?? 'Senin';

        // Developer / Testing Simulation Check
        $isSimulasiSenin = $request->has('dev_mode_senin') && $request->boolean('dev_mode_senin');
        $isSimulasiJumat = $request->has('dev_mode_jumat') && $request->boolean('dev_mode_jumat');

        if ($isSimulasiSenin) {
            $hariAktif = 'Senin';
        } elseif ($isSimulasiJumat) {
            $hariAktif = 'Jumat';
        } else {
            $hariAktif = $hariIniStr;
        }

        $isHariSenin = ($hariAktif === 'Senin');
        $isHariJumat = ($hariAktif === 'Jumat');

        // Pengaturan Sakelar Mode Khusus (Senin / Jumat)
        $pengaturanJadwal = PengaturanJadwal::getSetting();

        // 1. Stat Card 1: Total Kelas
        $totalKelas = Kelas::count();

        // 2. Stat Card 2: Total Mata Pelajaran
        $totalMapel = MataPelajaran::count();

        // 3. Stat Card 3: Izin Menunggu Approval Waka
        $izinMenungguApproval = IzinGuru::where('status', IzinGuru::STATUS_PENDING_WAKA)->count();

        // 4. Stat Card 4: Guru Mengajar Hari Ini
        $tahunAktif = TahunAjaran::where('status_aktif', true)->first() ?? TahunAjaran::first();

        $guruMengajarHariIni = JadwalPelajaran::where('hari', $hariIniStr)
            ->when($tahunAktif, fn($q) => $q->where('id_tahun_ajaran', $tahunAktif->id))
            ->distinct('id_guru')
            ->count('id_guru');

        $totalGuru = User::where('role', 'guru')->count();

        // 5. Daftar izin guru menunggu approval (Pending Waka)
        $daftarIzinPending = IzinGuru::with('user')
            ->where('status', IzinGuru::STATUS_PENDING_WAKA)
            ->latest()
            ->take(10)
            ->get();

        // 6. Ringkasan KBM Hari Ini (jurnal terisi vs total sesi jadwal)
        $totalSesiHariIni = JadwalPelajaran::where('hari', $hariIniStr)
            ->when($tahunAktif, fn($q) => $q->where('id_tahun_ajaran', $tahunAktif->id))
            ->count();

        $jurnalTerisiHariIni = Jurnal::whereDate('tanggal', $now->toDateString())
            ->whereNotNull('materi')
            ->where('materi', '!=', '')
            ->count();

        $persentaseKbmHariIni = $totalSesiHariIni > 0
            ? min(100, round(($jurnalTerisiHariIni / $totalSesiHariIni) * 100))
            : 0;

        return view('kurikulum.dashboard', compact(
            'hariAktif',
            'hariIniStr',
            'isHariSenin',
            'isHariJumat',
            'isSimulasiSenin',
            'isSimulasiJumat',
            'pengaturanJadwal',
            'totalKelas',
            'totalMapel',
            'izinMenungguApproval',
            'guruMengajarHariIni',
            'totalGuru',
            'tahunAktif',
            'daftarIzinPending',
            'totalSesiHariIni',
            'jurnalTerisiHariIni',
            'persentaseKbmHariIni'
        ));
    }
}
