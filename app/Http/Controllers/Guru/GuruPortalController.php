<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Guru\Concerns\ResolvesTargetGuru;
use App\Models\DispensasiSiswa;
use App\Models\JadwalPelajaran;
use App\Models\Jurnal;
use App\Models\TahunAjaran;
use Carbon\Carbon;

class GuruPortalController extends Controller
{
    use ResolvesTargetGuru;

    /**
     * Nama hari dalam Bahasa Indonesia untuk Carbon.
     */
    protected function hariIndonesia(): string
    {
        $map = [
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
            'Sunday' => 'Minggu',
        ];

        return $map[Carbon::now()->format('l')] ?? Carbon::now()->locale('id')->isoFormat('dddd');
    }

    /**
     * Halaman Dashboard Guru (Guru Mapel / Wali Kelas).
     */
    public function dashboard()
    {
        $user = auth()->user();
        $today = Carbon::today()->toDateString();

        if (! $user) {
            abort(403, 'Silakan login terlebih dahulu.');
        }

        if (! $user->isPetugasIt()
            && ! in_array($user->effectiveRole(), ['guru', 'guru_mapel', 'wali_kelas'], true)) {
            abort(403, 'Akses ditolak. Halaman ini khusus untuk Guru.');
        }

        // Context guru: target impersonasi bila sedang Switch View As (Mode QA IT),
        // selain itu akun login.
        $guruId = $this->effectiveGuruId();
        $hari = $this->hariIndonesia();
        $tahunAktif = TahunAjaran::aktif();

        // ===== Jadwal mengajar hari ini milik guru ini =====
        $jadwalQuery = JadwalPelajaran::withSlot()
            ->with(['jamPelajaran', 'kelas', 'mapel'])
            ->where('id_guru', $guruId)
            ->where('hari', $hari);

        if ($tahunAktif) {
            $jadwalQuery->where('id_tahun_ajaran', $tahunAktif->id);
        }

        $jadwalMentahHariIni = $jadwalQuery->get()->sortBy(fn ($j) => $j->jamPelajaran?->jam_ke ?? $j->jam_ke ?? 999)->values();
        $jadwalMentahHariIni->each(fn ($jadwal, $index) => $jadwal->dashboard_order = $index);
        $jadwalHariIniIds = $jadwalMentahHariIni->pluck('id');

        $jurnalHariIni = Jurnal::whereIn('id_jadwal', $jadwalHariIniIds)
            ->whereDate('tanggal', $today)
            ->get();
        $jurnalHariIniMap = $jurnalHariIni->keyBy('id_jadwal');

        $jadwalHariIni = $jadwalMentahHariIni
            ->groupBy(fn ($jadwal) => $jadwal->id_kelas.'-'.$jadwal->id_mapel)
            ->flatMap(function ($jadwals) use ($jurnalHariIniMap) {
                $blocks = collect();
                $block = collect();

                foreach ($jadwals as $jadwal) {
                    $previous = $block->last();
                    $previousJam = $previous?->jamPelajaran?->jam_ke ?? $previous?->jam_ke;
                    $currentJam = $jadwal->jamPelajaran?->jam_ke ?? $jadwal->jam_ke;
                    $isConsecutive = $previousJam !== null
                        && $currentJam !== null
                        && (int) $currentJam === (int) $previousJam + 1;

                    if ($block->isNotEmpty() && ! $isConsecutive) {
                        $blocks->push($block);
                        $block = collect();
                    }

                    $block->push($jadwal);
                }

                if ($block->isNotEmpty()) {
                    $blocks->push($block);
                }

                return $blocks->map(function ($schedules) use ($jurnalHariIniMap) {
                    $first = $schedules->first();
                    $last = $schedules->last();
                    $firstJam = $first->jamPelajaran;
                    $lastJam = $last->jamPelajaran;
                    $startJamKe = $firstJam?->jam_ke ?? $first->jam_ke ?? null;
                    $endJamKe = $lastJam?->jam_ke ?? $last->jam_ke ?? null;
                    $groupIds = $schedules->pluck('id');
                    $jurnalId = $groupIds->first(fn ($id) => $jurnalHariIniMap->has($id));
                    $display = clone $first;

                    $display->jam_ke_tampilan = $startJamKe === null
                        ? '-'
                        : ($startJamKe == $endJamKe ? $startJamKe : $startJamKe.' - '.$endJamKe);
                    $display->waktu_tampilan = $firstJam?->jam_mulai && $lastJam?->jam_selesai
                        ? \Carbon\Carbon::parse($firstJam->jam_mulai)->format('H:i').' - '.\Carbon\Carbon::parse($lastJam->jam_selesai)->format('H:i')
                        : '-';
                    $display->jurnal_sesi = $jurnalId ? $jurnalHariIniMap->get($jurnalId) : null;
                    $display->sudah_terisi = $jurnalId !== null;

                    return $display;
                });
            })
            ->sortBy('dashboard_order')
            ->values();
        $jurnalFilledIds = $jadwalHariIni->where('sudah_terisi')->pluck('id')->all();

        // ===== Dispensasi siswa yang terkait jam/mapel mengajar guru ini =====
        $dispensasiHariIni = DispensasiSiswa::with(['siswa.kelas', 'jadwal.mapel', 'jadwal.guru'])
            ->whereDate('tanggal', $today)
            ->where(function ($q) use ($guruId, $jadwalHariIniIds) {
                $q->where('id_guru', $guruId)
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
