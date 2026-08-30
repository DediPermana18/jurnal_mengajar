<?php

namespace App\Http\Controllers\Kurikulum;

use App\Http\Controllers\Controller;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
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

        // 3. Stat Card 3: Progress Plotting Jadwal
        $tahunAktif = TahunAjaran::where('status_aktif', true)->first() ?? TahunAjaran::first();

        $totalJadwalPlotted = JadwalPelajaran::when($tahunAktif, function ($q) use ($tahunAktif) {
            $q->where('id_tahun_ajaran', $tahunAktif->id);
        })->count();

        $slotKbmSeninKamis = JamPelajaran::where('kategori_hari', 'Senin-Kamis')->where('jenis', '!=', 'istirahat')->count();
        $slotKbmJumat      = JamPelajaran::where('kategori_hari', 'Jumat')->where('jenis', '!=', 'istirahat')->count();
        $totalSlotIdeal    = $totalKelas * (($slotKbmSeninKamis * 4) + $slotKbmJumat);

        $progressPlotting = ($totalSlotIdeal > 0)
            ? min(100, round(($totalJadwalPlotted / $totalSlotIdeal) * 100))
            : 85;

        // 4. Stat Card 4: Guru Mengajar Hari Ini
        $guruMengajarHariIni = JadwalPelajaran::where('hari', $hariIniStr)
            ->when($tahunAktif, fn($q) => $q->where('id_tahun_ajaran', $tahunAktif->id))
            ->distinct('id_guru')
            ->count('id_guru');

        $totalGuru = User::where('role', 'guru')->count();

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
            'progressPlotting',
            'totalJadwalPlotted',
            'totalSlotIdeal',
            'guruMengajarHariIni',
            'totalGuru',
            'tahunAktif'
        ));
    }
}
