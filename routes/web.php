<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\JurnalMengajarController;
use App\Http\Controllers\SiswaController;
use App\Http\Controllers\Guru\GuruPortalController;
use App\Http\Controllers\Guru\JurnalController as GuruJurnalController;
use App\Http\Controllers\WaliKelasController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Kurikulum\JamPelajaranController;
use App\Http\Controllers\Kurikulum\JadwalPelajaranController;
use App\Http\Controllers\Kurikulum\JamPulangController;
use App\Http\Controllers\Kurikulum\AgendaRutinController;
use App\Http\Controllers\Kurikulum\PengaturanJadwalController;
use App\Http\Controllers\Kurikulum\KurikulumDashboardController;
use App\Http\Controllers\MataPelajaranController;
use App\Http\Controllers\DashboardController;

// Halaman Login & Autentikasi
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Halaman utama mengarah ke Dashboard Admin
Route::get('/', [DashboardController::class, 'index'])->name('home');

// Resource Route untuk Jurnal Mengajar
Route::get('/jurnal/foto/{filename}', [JurnalMengajarController::class, 'showFoto'])->name('jurnal.foto');
Route::resource('admin/jurnal', JurnalMengajarController::class);
Route::put('/admin/jurnal/{id}/update-piket', [JurnalMengajarController::class, 'updateByPiket'])->name('jurnal.updateByPiket');

use App\Http\Controllers\GuruController;

// Route Data Master Guru
Route::get('/admin/guru', [GuruController::class, 'index'])->name('guru.index');
Route::get('/admin/guru/create', [GuruController::class, 'create'])->name('admin.guru.create');
Route::post('/admin/guru', [GuruController::class, 'store'])->name('guru.store');
Route::get('/admin/guru/{id}/edit', [GuruController::class, 'edit'])->name('admin.guru.edit');
Route::put('/admin/guru/{id}', [GuruController::class, 'update'])->name('guru.update');
Route::delete('/admin/guru/{id}', [GuruController::class, 'destroy'])->name('guru.destroy');
Route::post('/admin/guru/{id}/reset-password', [GuruController::class, 'resetPassword'])->name('guru.reset-password');
Route::post('/admin/guru/{id}/update-password', [GuruController::class, 'updatePassword'])->name('guru.update-password');
Route::post('/admin/guru/{id}/toggle-status', [GuruController::class, 'toggleStatus'])->name('guru.toggle-status');
Route::post('/admin/guru/{id}/approve', [GuruController::class, 'approve'])->name('guru.approve');
Route::post('/admin/guru/{id}/update-status', [GuruController::class, 'updateStatus'])->name('guru.update-status');

use App\Http\Controllers\KelasController;
use App\Http\Controllers\JurusanController;
use App\Http\Controllers\UserController;

// Resource Routes untuk Data Master
Route::resource('admin/siswa', SiswaController::class);
Route::resource('admin/kelas', KelasController::class);

Route::resource('admin/users', UserController::class)
    ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
    ->names('admin.users');
Route::post('/admin/users/{id}/reset-password', [UserController::class, 'resetPassword'])
    ->name('admin.users.reset-password');
Route::post('/admin/users/{id}/toggle-status', [UserController::class, 'toggleStatus'])
    ->name('admin.users.toggle-status');

Route::resource('admin/jurusan', JurusanController::class);

Route::resource('admin/mata-pelajaran', MataPelajaranController::class)->names('mapel');

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
    Route::get('/jurnal/{jurnal}', [GuruJurnalController::class, 'show'])->name('guru.jurnal.show');
    Route::get('/jurnal/{jurnal}/edit', [GuruJurnalController::class, 'edit'])->name('guru.jurnal.edit');
    Route::put('/jurnal/{jurnal}', [GuruJurnalController::class, 'update'])->name('guru.jurnal.update');
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
    Route::put('/jurnal/{id}/update-piket', [JurnalMengajarController::class, 'updateByPiket'])->name('piket.jurnal.updateByPiket');
});

use App\Http\Controllers\Kurikulum\JadwalPiketController;

// ================= ROUTE PORTAL WAKA KURIKULUM =================
Route::prefix('kurikulum')->group(function () {
    Route::get('/dashboard', [KurikulumDashboardController::class, 'index'])->name('kurikulum.dashboard');

    // Master Jam Pelajaran (resourceful + generate preset)
    Route::get('/jam-pelajaran',          [JamPelajaranController::class, 'index'])->name('kurikulum.jam-pelajaran.index');
    Route::post('/jam-pelajaran',         [JamPelajaranController::class, 'store'])->name('kurikulum.jam-pelajaran.store');
    Route::put('/jam-pelajaran/{jamPelajaran}', [JamPelajaranController::class, 'update'])->name('kurikulum.jam-pelajaran.update');
    Route::delete('/jam-pelajaran/{jamPelajaran}', [JamPelajaranController::class, 'destroy'])->name('kurikulum.jam-pelajaran.destroy');
    Route::post('/jam-pelajaran/generate-preset', [JamPelajaranController::class, 'generatePreset'])->name('kurikulum.jam-pelajaran.generate');

    // Pengaturan Jam Pulang per Tingkat Kelas
    Route::post('/jam-pulang/upsert', [JamPulangController::class, 'upsert'])->name('kurikulum.jam-pulang.upsert');

    // Pengaturan Agenda Rutin / Upacara Sekolah
    Route::post('/agenda-rutin/upsert', [AgendaRutinController::class, 'upsert'])->name('kurikulum.agenda-rutin.upsert');

    // Sakelar Mode Senin Tanpa Upacara (Pergeseran KBM)
    Route::post('/toggle-senin-tanpa-upacara', [PengaturanJadwalController::class, 'toggleSeninTanpaUpacara'])->name('kurikulum.toggle-senin-tanpa-upacara');

    // Plotting Jadwal Kelas
    Route::get('/jadwal',                      [JadwalPelajaranController::class, 'index'])->name('kurikulum.jadwal.index');
    Route::post('/jadwal',                     [JadwalPelajaranController::class, 'store'])->name('kurikulum.jadwal.store');
    Route::put('/jadwal/{jadwalPelajaran}',    [JadwalPelajaranController::class, 'update'])->name('kurikulum.jadwal.update');
    Route::delete('/jadwal/{jadwalPelajaran}', [JadwalPelajaranController::class, 'destroy'])->name('kurikulum.jadwal.destroy');
    Route::get('/jadwal-pelajaran',            fn() => redirect()->route('kurikulum.jadwal.index'));

    // Jadwal Piket Guru
    Route::get('/jadwal-piket',               [JadwalPiketController::class, 'index'])->name('kurikulum.jadwal-piket.index');
    Route::get('/jadwal-piket/create',        [JadwalPiketController::class, 'create'])->name('kurikulum.jadwal-piket.create');
    Route::get('/jadwal-piket/{hari}/edit',   [JadwalPiketController::class, 'edit'])->name('kurikulum.jadwal-piket.edit');
    Route::post('/jadwal-piket',              [JadwalPiketController::class, 'store'])->name('kurikulum.jadwal-piket.store');
    Route::delete('/jadwal-piket/{id}',       [JadwalPiketController::class, 'destroy'])->name('kurikulum.jadwal-piket.destroy');

    Route::get('/izin', function () {
        return view('admin.placeholder', ['title' => 'Approval Izin Guru']);
    })->name('kurikulum.izin.index');

    Route::get('/laporan', function () {
        return view('admin.placeholder', ['title' => 'Laporan KBM']);
    })->name('kurikulum.laporan.index');
});
