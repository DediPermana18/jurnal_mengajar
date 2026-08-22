<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $totalGuru = User::where('role', User::ROLE_GURU)->count();
        $totalSiswa = Siswa::count();
        $totalKelas = Kelas::count();
        $akunBelumAktivasi = User::whereNotNull('kode_aktivasi')->count();

        $userTerbaru = User::latest()->take(5)->get();

        return view('admin.dashboard.index', compact(
            'totalGuru',
            'totalSiswa',
            'totalKelas',
            'akunBelumAktivasi',
            'userTerbaru'
        ));
    }
}