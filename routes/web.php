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
use App\Http\Controllers\Kurikulum\KurikulumIzinController;
use App\Http\Controllers\Kurikulum\IzinSettingController;
use App\Http\Controllers\Kurikulum\KurikulumLaporanController;
use App\Http\Controllers\MataPelajaranController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\HelpController;
use App\Http\Middleware\AdminScheduleAccess;

// Halaman Login & Autentikasi
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Notifikasi navbar
use App\Http\Controllers\NotificationController;
Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');

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

use App\Http\Controllers\RuanganController;
Route::resource('admin/ruangan', RuanganController::class)->only(['index', 'store', 'update', 'destroy']);

Route::resource('admin/mata-pelajaran', MataPelajaranController::class)->names('mapel');

Route::redirect('/admin/laporan', '/kurikulum/laporan')->name('laporan.index');

// ================= PROFIL & PENGATURAN AKUN =================
Route::get('/profil', [ProfilController::class, 'index'])->name('profil.index');
Route::post('/profil/update-profil', [ProfilController::class, 'updateProfil'])->name('profil.update-profil');
Route::post('/profil/update-password', [ProfilController::class, 'updatePassword'])->name('profil.update-password');
Route::post('/profil/generate-kode-aktivasi', [ProfilController::class, 'generateKodeAktivasi'])->name('profil.generate-kode-aktivasi');
// Legacy redirect
Route::get('/admin/pengaturan', fn() => redirect()->route('profil.index'))->name('pengaturan.index');

Route::get('/bantuan', [HelpController::class, 'index'])->name('bantuan.index');
Route::get('/admin/bantuan', [HelpController::class, 'index']);

// ================= ROUTE PORTAL GURU (GURU MAPEL) =================
use App\Http\Controllers\Guru\IzinController as GuruIzinController;
Route::prefix('guru')->group(function () {
    Route::get('/dashboard', [GuruPortalController::class, 'dashboard'])->name('guru.dashboard');
    Route::get('/jurnal', [GuruJurnalController::class, 'index'])->name('guru.jurnal');
    Route::get('/jurnal/{jadwal}/form', [GuruJurnalController::class, 'create'])->name('guru.jurnal.form');
    Route::post('/jurnal', [GuruJurnalController::class, 'store'])->name('guru.jurnal.store');
    Route::get('/jurnal/{jurnal}', [GuruJurnalController::class, 'show'])->name('guru.jurnal.show');
    Route::get('/jurnal/{jurnal}/edit', [GuruJurnalController::class, 'edit'])->name('guru.jurnal.edit');
    Route::put('/jurnal/{jurnal}', [GuruJurnalController::class, 'update'])->name('guru.jurnal.update');

    // Izin Guru (pengajuan oleh guru + pelacakan status)
    Route::get('/izin',                    [GuruIzinController::class, 'index'])->name('guru.izin.index');
    Route::get('/izin/create',             [GuruIzinController::class, 'create'])->name('guru.izin.create');
    Route::post('/izin',                   [GuruIzinController::class, 'store'])->name('guru.izin.store');
    Route::get('/izin/{id}',               [GuruIzinController::class, 'show'])->name('guru.izin.show');
    Route::get('/izin/{id}/lampiran',      [GuruIzinController::class, 'showLampiran'])->name('guru.izin.lampiran');
});

// ================= ROUTE PORTAL WALI KELAS =================
Route::prefix('walikelas')->group(function () {
    Route::get('/dashboard', [WaliKelasController::class, 'dashboard'])->name('walikelas.dashboard');
    Route::get('/rekap-absen', [WaliKelasController::class, 'rekapAbsen'])->name('walikelas.rekap-absen');
    Route::get('/riwayat-jurnal', [WaliKelasController::class, 'riwayatJurnal'])->name('walikelas.riwayat-jurnal');
    Route::get('/siswa-bermasalah', [WaliKelasController::class, 'siswaBermasalah'])->name('walikelas.siswa-bermasalah');
    Route::post('/siswa-bermasalah/tindak-lanjut', [WaliKelasController::class, 'siswaBermasalahStore'])->name('walikelas.siswa-bermasalah.store');
});

// ================= PORTAL GURU PIKET =================
use App\Http\Controllers\GuruPiketController;
use App\Http\Controllers\DispensasiController;

// ================= APPROVAL IZIN GURU PUBLIK (tanpa login, via link/QR unik) =================
// Link dikirim ke Waka & Kepala Sekolah melalui WhatsApp. Satu token menangani
// seluruh langkah publik (Waka -> Kepsek) sesuai level approval yang dikonfigurasi.
use App\Http\Controllers\IzinApprovalController;
use App\Http\Controllers\IzinPiketController;
Route::get('/approve-izin/{token}', [IzinApprovalController::class, 'show'])->name('izin.approval.show');
Route::post('/approve-izin/{token}', [IzinApprovalController::class, 'submit'])->name('izin.approval.submit');

Route::get('/dispen/approve/{token}', [DispensasiController::class, 'publicApproveView'])->name('dispen.approval.show');
Route::post('/dispen/approve/{token}', [DispensasiController::class, 'publicApproveStore'])->name('dispen.approval.store');

Route::prefix('piket')->group(function () {
    Route::get('/dashboard', [GuruPiketController::class, 'dashboard'])->name('piket.dashboard');
    Route::get('/presensi-guru', [GuruPiketController::class, 'presensiGuru'])->name('piket.presensi-guru');
    Route::get('/presensi-siswa', [GuruPiketController::class, 'presensiSiswa'])->name('piket.presensi-siswa');
    Route::post('/presensi-siswa', [GuruPiketController::class, 'storePresensiSiswa'])->name('piket.presensi-siswa.store');
    Route::get('/jurnal', [GuruPiketController::class, 'jurnalKBM'])->name('piket.jurnal');
    Route::put('/jurnal/{id}/update-piket', [JurnalMengajarController::class, 'updateByPiket'])->name('piket.jurnal.updateByPiket');

    // Dispensasi Siswa oleh Guru Piket
    Route::get('/dispensasi',                    [DispensasiController::class, 'index'])->name('piket.dispensasi.index');
    Route::get('/dispensasi/create',             [DispensasiController::class, 'create'])->name('piket.dispensasi.create');
    Route::post('/dispensasi',                   [DispensasiController::class, 'store'])->name('piket.dispensasi.store');
    Route::get('/dispensasi/{id}/surat',         [DispensasiController::class, 'showSurat'])->name('piket.dispensasi.surat');
    Route::get('/dispensasi/{id}/ttd',           [DispensasiController::class, 'showTtd'])->name('piket.dispensasi.ttd');
    Route::post('/dispensasi/{id}/ttd',          [DispensasiController::class, 'saveTtd'])->name('piket.dispensasi.ttd-save');

    // Izin Guru oleh Guru Piket (verifikasi Step 1)
    Route::get('/izin',                          [IzinPiketController::class, 'index'])->name('piket.izin.index');
    Route::post('/izin/{id}/approve',            [IzinPiketController::class, 'approve'])->name('piket.izin.approve');
    Route::post('/izin/{id}/reject',             [IzinPiketController::class, 'reject'])->name('piket.izin.reject');
});

// ================= PORTAL SATPAM / KEAMANAN (independen, tanpa cek jadwal piket) =================
use App\Http\Controllers\SatpamController;
Route::prefix('satpam')->group(function () {
    Route::get('/',                          fn () => redirect()->route('satpam.dashboard'));
    Route::get('/dashboard',                 [SatpamController::class, 'dashboard'])->name('satpam.dashboard');
    Route::post('/terlambat',                [SatpamController::class, 'terlambatStore'])->name('satpam.terlambat.store');
    Route::post('/dispensasi',               [SatpamController::class, 'dispensasiStore'])->name('satpam.dispensasi.store');
    Route::get('/verifikasi',                [SatpamController::class, 'verifikasi'])->name('satpam.verifikasi');
    Route::post('/dispensasi/{dispen}/keluar', [SatpamController::class, 'dispenKeluar'])->name('satpam.dispen.keluar');
});

use App\Http\Controllers\PetugasItController;

// ================= ROUTE PETUGAS IT / QA TESTER (Switch View As) =================
Route::prefix('it')->middleware(['auth'])->group(function () {
    Route::post('/switch-view', [PetugasItController::class, 'switchView'])->name('it.switch-view');
    Route::post('/reset-view',  [PetugasItController::class, 'resetView'])->name('it.reset-view');
});

use App\Http\Controllers\Kurikulum\JadwalPiketController;

// ================= ROUTE PORTAL WAKA KURIKULUM =================
Route::prefix('kurikulum')->group(function () {
    Route::get('/dashboard', [KurikulumDashboardController::class, 'index'])->name('kurikulum.dashboard');

    // Jadwal Piket Guru
    Route::get('/jadwal-piket',               [JadwalPiketController::class, 'index'])->name('kurikulum.jadwal-piket.index');
    Route::get('/jadwal-piket/create',        [JadwalPiketController::class, 'create'])->name('kurikulum.jadwal-piket.create');
    Route::get('/jadwal-piket/{hari}/edit',   [JadwalPiketController::class, 'edit'])->name('kurikulum.jadwal-piket.edit');
    Route::post('/jadwal-piket',              [JadwalPiketController::class, 'store'])->name('kurikulum.jadwal-piket.store');
    Route::delete('/jadwal-piket/{id}',       [JadwalPiketController::class, 'destroy'])->name('kurikulum.jadwal-piket.destroy');

    Route::get('/izin',                               [KurikulumIzinController::class, 'index'])->name('kurikulum.izin.index');
    Route::post('/izin/{id}/approve',                 [KurikulumIzinController::class, 'approve'])->name('kurikulum.izin.approve');
    Route::post('/izin/{id}/reject',                  [KurikulumIzinController::class, 'reject'])->name('kurikulum.izin.reject');
    Route::get('/izin/lampiran/{id}',                 [KurikulumIzinController::class, 'showLampiran'])->name('kurikulum.izin.lampiran');
    Route::get('/izin/pengaturan',                    [IzinSettingController::class, 'index'])->name('kurikulum.izin.setting');
    Route::post('/izin/pengaturan',                   [IzinSettingController::class, 'update'])->name('kurikulum.izin.setting.update');

    Route::get('/approval-dispensasi', [DispensasiController::class, 'indexApproval'])->name('kurikulum.dispensasi.approval.index');
    Route::post('/approval-dispensasi/{id}', [DispensasiController::class, 'storeApproval'])->name('kurikulum.dispensasi.approval.store');

    Route::get('/laporan', [KurikulumLaporanController::class, 'index'])->name('kurikulum.laporan.index');
    Route::get('/laporan/export-excel', [KurikulumLaporanController::class, 'exportExcel'])->name('kurikulum.laporan.excel');
    Route::get('/laporan/print', [KurikulumLaporanController::class, 'printPdf'])->name('kurikulum.laporan.print');
});

// ================= ROUTE MASTER & PLOTTING JADWAL (ADMIN / TU) =================
Route::prefix('admin')->middleware(['auth', AdminScheduleAccess::class])->group(function () {
    Route::get('/jam-pelajaran', [JamPelajaranController::class, 'index'])->name('admin.jam-pelajaran.index');
    Route::post('/jam-pelajaran', [JamPelajaranController::class, 'store'])->name('admin.jam-pelajaran.store');
    Route::put('/jam-pelajaran/{jamPelajaran}', [JamPelajaranController::class, 'update'])->name('admin.jam-pelajaran.update');
    Route::delete('/jam-pelajaran/{jamPelajaran}', [JamPelajaranController::class, 'destroy'])->name('admin.jam-pelajaran.destroy');
    Route::post('/jam-pelajaran/generate-preset', [JamPelajaranController::class, 'generatePreset'])->name('admin.jam-pelajaran.generate');
    Route::post('/jam-pulang/upsert', [JamPulangController::class, 'upsert'])->name('admin.jam-pulang.upsert');
    Route::post('/agenda-rutin/upsert', [AgendaRutinController::class, 'upsert'])->name('admin.agenda-rutin.upsert');
    Route::post('/toggle-senin-tanpa-upacara', [PengaturanJadwalController::class, 'toggleSeninTanpaUpacara'])->name('admin.toggle-senin-tanpa-upacara');
    Route::post('/toggle-mode-khusus', [PengaturanJadwalController::class, 'toggleModeKhusus'])->name('admin.toggle-mode-khusus');

    Route::get('/jadwal', [JadwalPelajaranController::class, 'index'])->name('admin.jadwal.index');
    Route::get('/jadwal/slot-kosong', [JadwalPelajaranController::class, 'monitoringSlotKosong'])->name('admin.jadwal.monitoring');
    Route::post('/jadwal', [JadwalPelajaranController::class, 'store'])->name('admin.jadwal.store');
    Route::put('/jadwal/{jadwalPelajaran}', [JadwalPelajaranController::class, 'update'])->name('admin.jadwal.update');
    Route::delete('/jadwal/{jadwalPelajaran}', [JadwalPelajaranController::class, 'destroy'])->name('admin.jadwal.destroy');
    Route::get('/jadwal-pelajaran', fn() => redirect()->route('admin.jadwal.index'));
});
