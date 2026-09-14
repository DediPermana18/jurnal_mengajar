<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\PengaturanJadwal;
use App\Models\Siswa;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $activeRole = session('active_role');

        // IT/QA sedang melakukan impersonasi — arahkan ke portal peran yang sesuai,
        // kecuali admin_tu yang memang menampilkan dashboard Admin TU di halaman ini.
        // JANGAN redirect kembali ke route('home') (avoid infinite loop).
        if ($activeRole) {
            $portalRoute = [
                'satpam' => 'satpam.dashboard',
                'waka_kurikulum' => 'kurikulum.dashboard',
                'waka_sdm' => 'waka-sdm.dashboard',
                'waka_kesiswaan' => 'waka-kesiswaan.dashboard',
                'kepsek' => 'kepsek.dashboard',
                'guru_piket' => 'piket.dashboard',
                'guru_mapel' => 'guru.dashboard',
                'wali_kelas' => 'walikelas.dashboard',
            ][$activeRole] ?? null;

            if ($portalRoute) {
                return redirect()->route($portalRoute);
            }
        }

        $totalGuru = User::where('role', User::ROLE_GURU)->count();
        $totalSiswa = Siswa::count();
        $totalKelas = Kelas::count();
        $akunTidakAktif = User::where('is_active', false)->count();

        $userTerbaru = User::latest()->take(5)->get();

        $pengaturanJadwal = PengaturanJadwal::getSetting();

        return view('admin.dashboard.index', compact(
            'totalGuru',
            'totalSiswa',
            'totalKelas',
            'akunTidakAktif',
            'userTerbaru',
            'pengaturanJadwal'
        ));
    }
}
