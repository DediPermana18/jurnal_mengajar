<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\JurnalMengajarController;
use App\Http\Controllers\SiswaController;
use App\Http\Controllers\Guru\GuruPortalController;
use App\Http\Controllers\Guru\JurnalController as GuruJurnalController;
use App\Http\Controllers\WaliKelasController;
use App\Http\Controllers\AuthController;

// Halaman Login & Autentikasi
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Halaman utama mengarah ke Dashboard Admin
Route::get('/', [JurnalMengajarController::class, 'index'])->name('home');

// Resource Route untuk Jurnal Mengajar
Route::resource('admin/jurnal', JurnalMengajarController::class);

use App\Http\Controllers\GuruController;

// Route Data Master Guru
Route::get('/admin/guru', [GuruController::class, 'index'])->name('guru.index');
Route::post('/admin/guru', [GuruController::class, 'store'])->name('guru.store');
Route::put('/admin/guru/{id}', [GuruController::class, 'update'])->name('guru.update');
Route::delete('/admin/guru/{id}', [GuruController::class, 'destroy'])->name('guru.destroy');
Route::post('/admin/guru/{id}/reset-password', [GuruController::class, 'resetPassword'])->name('guru.reset-password');
Route::post('/admin/guru/{id}/update-password', [GuruController::class, 'updatePassword'])->name('guru.update-password');
Route::post('/admin/guru/{id}/toggle-status', [GuruController::class, 'toggleStatus'])->name('guru.toggle-status');

// Resource Routes untuk Data Master
Route::resource('admin/siswa', SiswaController::class);

Route::get('/admin/kelas', function () {
    return view('admin.placeholder', ['title' => 'Data Kelas']);
})->name('kelas.index');

Route::get('/admin/jurusan', function () {
    return view('admin.placeholder', ['title' => 'Data Jurusan']);
})->name('jurusan.index');

Route::get('/admin/mata-pelajaran', function () {
    return view('admin.placeholder', ['title' => 'Data Mata Pelajaran']);
})->name('mapel.index');

Route::get('/admin/jadwal', function () {
    return view('admin.placeholder', ['title' => 'Jadwal Pelajaran']);
})->name('jadwal.index');

Route::get('/admin/laporan', function () {
    return view('admin.placeholder', ['title' => 'Laporan']);
})->name('laporan.index');

Route::get('/admin/pengaturan', function () {
    return view('admin.placeholder', ['title' => 'Pengaturan']);
})->name('pengaturan.index');

Route::get('/admin/bantuan', function () {
    return view('admin.placeholder', ['title' => 'Bantuan']);
})->name('bantuan.index');

// ================= ROUTE PORTAL GURU (GURU MAPEL) =================
Route::prefix('guru')->group(function () {
    Route::get('/dashboard', [GuruPortalController::class, 'dashboard'])->name('guru.dashboard');
    Route::get('/jurnal', [GuruJurnalController::class, 'index'])->name('guru.jurnal');
    Route::get('/jurnal/{jadwal}/form', [GuruJurnalController::class, 'create'])->name('guru.jurnal.form');
    Route::post('/jurnal', [GuruJurnalController::class, 'store'])->name('guru.jurnal.store');
});

// ================= ROUTE PORTAL WALI KELAS =================
Route::prefix('walikelas')->group(function () {
    Route::get('/rekap-absen', [WaliKelasController::class, 'rekapAbsen'])->name('walikelas.rekap-absen');
    Route::get('/riwayat-jurnal', [WaliKelasController::class, 'riwayatJurnal'])->name('walikelas.riwayat-jurnal');
    Route::get('/siswa-bermasalah', [WaliKelasController::class, 'siswaBermasalah'])->name('walikelas.siswa-bermasalah');
});

// ================= ROUTE PORTAL GURU PIKET =================
use App\Http\Controllers\GuruPiketController;

Route::prefix('piket')->group(function () {
    Route::get('/dashboard', [GuruPiketController::class, 'dashboard'])->name('piket.dashboard');
    Route::get('/presensi-guru', [GuruPiketController::class, 'presensiGuru'])->name('piket.presensi-guru');
    Route::get('/presensi-siswa', [GuruPiketController::class, 'presensiSiswa'])->name('piket.presensi-siswa');
    Route::get('/jurnal', [GuruPiketController::class, 'jurnalKBM'])->name('piket.jurnal');
});
