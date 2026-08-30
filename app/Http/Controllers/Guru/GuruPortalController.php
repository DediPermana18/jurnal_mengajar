<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\DispensasiSiswa;
use App\Models\JadwalPelajaran;
use App\Models\Jurnal;
use App\Models\TahunAjaran;
use Carbon\Carbon;
use Illuminate\Http\Request;

class GuruPortalController extends Controller
{
    /**
     * Nama hari dalam Bahasa Indonesia untuk Carbon.
     */
    protected function hariIndonesia(): string
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

        return $map[Carbon::now()->format('l')] ?? Carbon::now()->locale('id')->isoFormat('dddd');
    }

    /**
     * Halaman Dashboard Guru (Guru Mapel / Wali Kelas).
     */
    public function dashboard()
    {
        $user  = auth()->user();
        $today = Carbon::today()->toDateString();

        if (!$user) {
            abort(403, 'Silakan login terlebih dahulu.');
        }

        if (!in_array($user->role, ['guru', 'guru_mapel', 'wali_kelas'], true)) {
            abort(403, 'Akses ditolak. Halaman ini khusus untuk Guru.');
        }

        $hari       = $this->hariIndonesia();
        $tahunAktif = TahunAjaran::where('status_aktif', true)->first();

        // ===== Jadwal mengajar hari ini milik guru ini =====
        $jadwalQuery = JadwalPelajaran::with(['jamPelajaran', 'kelas', 'mapel'])
            ->where('id_guru', $user->id)
            ->where('hari', $hari);

        if ($tahunAktif) {
            $jadwalQuery->where('id_tahun_ajaran', $tahunAktif->id);
        }

        $jadwalHariIni = $jadwalQuery->get()->sortBy(fn ($j) => $j->jamPelajaran?->jam_ke ?? 999)->values();
        $jadwalHariIniIds = $jadwalHariIni->pluck('id');

        $jurnalHariIni = Jurnal::whereIn('id_jadwal', $jadwalHariIniIds)
            ->whereDate('tanggal', $today)
            ->get();
        $jurnalFilledIds = $jurnalHariIni->pluck('id_jadwal')->all();
        $jurnalHariIniMap = $jurnalHariIni->keyBy('id_jadwal');

        // ===== Dispensasi siswa yang terkait jam/mapel mengajar guru ini =====
        $dispensasiHariIni = DispensasiSiswa::with(['siswa.kelas', 'jadwal.mapel', 'jadwal.guru'])
            ->whereDate('tanggal', $today)
            ->where(function ($q) use ($user, $jadwalHariIniIds) {
                $q->where('id_guru', $user->id)
                    ->orWhereIn('id_jadwal', $jadwalHariIniIds);
            })
            ->orderBy('jam_ke')
            ->get();

        $jumlahDispenDisetujui = $dispensasiHariIni
            ->where('status', DispensasiSiswa::STATUS_DISETUJUI)
            ->count();

        return view('guru.dashboard', compact(
            'jadwalHariIni',
            'jadwalHariIniIds',
            'jurnalFilledIds',
            'jurnalHariIniMap',
            'dispensasiHariIni',
            'jumlahDispenDisetujui',
            'hari',
            'today'
        ));
    }
}