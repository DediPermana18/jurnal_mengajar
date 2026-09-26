<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Guru\GuruPortalController;
use App\Http\Controllers\Guru\JurnalController as GuruJurnalController;
use App\Http\Controllers\Guru\DispensasiVerifikasiController as GuruDispensasiVerifikasiController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\JurnalMengajarController;
use App\Http\Controllers\KepsekController;
use App\Http\Controllers\Kurikulum\AgendaRutinController;
use App\Http\Controllers\Kurikulum\JadwalPelajaranController;
use App\Http\Controllers\Kurikulum\JamPelajaranController;
use App\Http\Controllers\Kurikulum\JamPulangController;
use App\Http\Controllers\Kurikulum\ShiftPelajaranController;
use App\Http\Controllers\Kurikulum\KurikulumDashboardController;
use App\Http\Controllers\Kurikulum\KurikulumLaporanController;
use App\Http\Controllers\Kurikulum\PengaturanJadwalController;
use App\Http\Controllers\MataPelajaranController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\RuanganController;
use App\Http\Controllers\SiswaController;
use App\Http\Controllers\TahunAjaranController;
use App\Http\Controllers\WakaSdmController;
use App\Http\Controllers\WaliKelasController;
use App\Http\Middleware\AdminScheduleAccess;
use Illuminate\Support\Facades\Route;

// Halaman Login & Autentikasi
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// ================= LUPA SANDI / KODE AKTIVASI (publik, tanpa login) =================
// Pengajuan via formulir publik + tanda tangan digital. Admin TU memverifikasi
// dan membuat tautan reset unik ber-token (lihat ResetRequestController).
use App\Http\Controllers\ResetRequestController;

Route::get('/lupa-sandi/cek-akun', [ResetRequestController::class, 'checkAccount'])->name('reset-request.check-account');
Route::get('/lupa-sandi', [ResetRequestController::class, 'create'])->name('reset-request.create');
Route::post('/lupa-sandi', [ResetRequestController::class, 'store'])->name('reset-request.store');

// Halaman reset kredensial via tautan unik ber-token (tanpa login/pilih akun).
// Token otomatis mendeteksi user_id & jenis_pengajuan dari record yang valid.
Route::get('/reset-credentials/{token}', [ResetRequestController::class, 'showResetForm'])->name('reset-credentials.show');
Route::post('/reset-credentials/{token}', [ResetRequestController::class, 'submitReset'])->name('reset-credentials.submit');

// Notifikasi navbar
use App\Http\Controllers\NotificationController;

Route::middleware(['auth'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
});

// Halaman utama (Dashboard Admin) — WAJIB login.
// Guest yang membuka '/' akan dialihkan otomatis ke halaman login oleh
// middleware 'auth'. User yang sudah login tetap langsung melihat dashboard.
Route::middleware(['auth'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('home');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

// Resource Route untuk Jurnal Mengajar (wajib login)
Route::middleware(['auth'])->group(function () {
    Route::get('/jurnal/foto/{filename}', [JurnalMengajarController::class, 'showFoto'])->name('jurnal.foto');
    Route::resource('admin/jurnal', JurnalMengajarController::class);
    Route::put('/admin/jurnal/{id}/update-piket', [JurnalMengajarController::class, 'updateByPiket'])->name('jurnal.updateByPiket');
});

use App\Http\Controllers\DataImportController;
use App\Http\Controllers\GuruController;
use App\Http\Controllers\JurusanController;
use App\Http\Controllers\KelasController;
use App\Http\Controllers\UserController;

// Route Data Master (Admin / Petugas TU)
Route::middleware(['auth', AdminScheduleAccess::class])->group(function () {
    // Route Data Master Guru
    Route::get('/admin/guru', [GuruController::class, 'index'])->name('guru.index');
    Route::get('/admin/guru/export', [GuruController::class, 'export'])->name('guru.export');
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

    // Resource Routes untuk Data Master
    Route::get('admin/import', [DataImportController::class, 'index'])->name('import.index');
    Route::get('admin/import/template-siswa', [DataImportController::class, 'downloadTemplateSiswa'])->name('import.template-siswa');
    Route::get('admin/import/template-jadwal', [DataImportController::class, 'downloadTemplateJadwal'])->name('import.template-jadwal');
    Route::post('admin/import/siswa', [DataImportController::class, 'importSiswa'])->name('import.siswa');
    Route::post('admin/import/guru', [DataImportController::class, 'importGuru'])->name('import.guru');
    Route::post('admin/import/kelas', [DataImportController::class, 'importKelas'])->name('import.kelas');
    Route::post('admin/import/ruangan', [DataImportController::class, 'importRuangan'])->name('import.ruangan');
    Route::post('admin/import/reset-siswa', [DataImportController::class, 'resetSiswa'])->name('import.reset-siswa');
    Route::post('admin/import/reset-guru', [DataImportController::class, 'resetGuru'])->name('import.reset-guru');
    Route::post('admin/import/reset-kelas-jurusan', [DataImportController::class, 'resetKelasJurusan'])->name('import.reset-kelas-jurusan');
    Route::post('admin/import/reset-ruangan', [DataImportController::class, 'resetRuangan'])->name('import.reset-ruangan');
    Route::post('admin/import/jadwal', [DataImportController::class, 'importJadwal'])->name('import.jadwal');
    Route::post('admin/import/reset-jadwal', [DataImportController::class, 'resetJadwal'])->name('import.reset-jadwal');
    Route::get('admin/siswa/export', [SiswaController::class, 'export'])->name('siswa.export');
    Route::delete('admin/siswa/delete-all', [SiswaController::class, 'deleteAll'])->name('siswa.delete-all');
    Route::resource('admin/siswa', SiswaController::class);
    Route::get('admin/kelas/export', [KelasController::class, 'export'])->name('kelas.export');
    // Tambah/Edit kelas memakai modal (store/update), bukan halaman create/edit terpisah.
    Route::resource('admin/kelas', KelasController::class)->except(['create', 'edit']);

    Route::resource('admin/users', UserController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->names('admin.users');
    Route::post('/admin/users/{id}/reset-password', [UserController::class, 'resetPassword'])
        ->name('admin.users.reset-password');
    Route::post('/admin/users/{id}/toggle-status', [UserController::class, 'toggleStatus'])
        ->name('admin.users.toggle-status');

    // Panel Admin TU: verifikasi pengajuan reset (lupa sandi / kode aktivasi)
    Route::get('/admin/pengajuan-reset', [ResetRequestController::class, 'index'])
        ->name('admin.reset-requests.index');
    Route::get('/admin/pengajuan-reset/count', [ResetRequestController::class, 'pendingCount'])
        ->name('admin.reset-requests.count');
    Route::post('/admin/pengajuan-reset/{id}/approve', [ResetRequestController::class, 'approve'])
        ->name('admin.reset-requests.approve');
    Route::post('/admin/pengajuan-reset/{id}/reject', [ResetRequestController::class, 'reject'])
        ->name('admin.reset-requests.reject');

    Route::get('admin/jurusan/export', [JurusanController::class, 'export'])->name('jurusan.export');
    Route::post('admin/jurusan/import', [JurusanController::class, 'import'])->name('jurusan.import');
    Route::resource('admin/jurusan', JurusanController::class);

    Route::get('admin/ruangan/export', [RuanganController::class, 'export'])->name('ruangan.export');
    Route::post('admin/ruangan/import', [RuanganController::class, 'import'])->name('ruangan.import');
    Route::resource('admin/ruangan', RuanganController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);

    Route::resource('admin/tahun-ajaran', TahunAjaranController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->names('tahun-ajaran');
    Route::post('/admin/tahun-ajaran/{tahunAjaran}/set-aktif', [TahunAjaranController::class, 'setAktif'])
        ->name('tahun-ajaran.set-aktif');
});

// Route Data Master Mata Pelajaran — diakses Admin/Petugas TU DAN Waka Kurikulum.
// Authorization ditangani di dalam MataPelajaranController (bukan AdminScheduleAccess)
// agar Waka Kurikulum bisa membuka halaman ini tanpa 403.
Route::get('admin/mata-pelajaran/export', [MataPelajaranController::class, 'export'])->name('mapel.export')->middleware(['auth']);
Route::get('admin/mata-pelajaran/template', [MataPelajaranController::class, 'downloadTemplate'])->name('mapel.template')->middleware(['auth']);
Route::post('admin/mata-pelajaran/import', [MataPelajaranController::class, 'import'])->name('mapel.import')->middleware(['auth']);
Route::resource('admin/mata-pelajaran', MataPelajaranController::class)
    ->names('mapel')
    ->middleware(['auth']);

Route::redirect('/admin/laporan', '/kurikulum/laporan')->name('laporan.index');

// ================= PROFIL & PENGATURAN AKUN =================
// Wajib autentikasi: halaman ini mengakses Auth::user() untuk menampilkan/mengedit profil.
// Tanpa middleware 'auth', pengunjung yang belum login akan memicu
// "Attempt to read property on null" di resources/views/profil/index.blade.php.
Route::get('/profil', [ProfilController::class, 'index'])->name('profil.index')->middleware('auth');
Route::post('/profil/update-profil', [ProfilController::class, 'updateProfil'])->name('profil.update-profil')->middleware('auth');
Route::post('/profil/update-password', [ProfilController::class, 'updatePassword'])->name('profil.update-password')->middleware('auth');
Route::post('/profil/generate-kode-aktivasi', [ProfilController::class, 'generateKodeAktivasi'])->name('profil.generate-kode-aktivasi')->middleware('auth');
Route::post('/profil/update-kode-aktivasi', [ProfilController::class, 'updateKodeAktivasi'])->name('profil.update-kode-aktivasi')->middleware('auth');
// Legacy redirect
Route::get('/admin/pengaturan', fn () => redirect()->route('profil.index'))->name('pengaturan.index');

Route::get('/bantuan', [HelpController::class, 'index'])->name('bantuan.index');
Route::get('/admin/bantuan', [HelpController::class, 'index']);
Route::post('/bantuan/kendala', [HelpController::class, 'storeKendala'])->name('bantuan.kendala.store');

// ================= ROUTE PORTAL GURU (GURU MAPEL) =================
use App\Http\Controllers\Guru\IzinController as GuruIzinController;

Route::prefix('guru')->middleware(['auth'])->group(function () {
    Route::get('/dashboard', [GuruPortalController::class, 'dashboard'])->name('guru.dashboard');

    // Verifikasi Surat Dispensasi Telat (Masuk Kelas) oleh Guru Mapel
    Route::get('/dispensasi/verifikasi', [GuruDispensasiVerifikasiController::class, 'index'])->name('guru.dispensasi.verifikasi');
    Route::post('/dispensasi/{dispen}/izinkan-masuk', [GuruDispensasiVerifikasiController::class, 'izinkanMasuk'])->name('guru.dispensasi.izinkan-masuk');
    Route::get('/jurnal', [GuruJurnalController::class, 'index'])->name('guru.jurnal');
    Route::get('/jurnal/{jadwal}/form', [GuruJurnalController::class, 'create'])->name('guru.jurnal.form');
    Route::post('/jurnal', [GuruJurnalController::class, 'store'])->name('guru.jurnal.store');
    Route::get('/jurnal/{jurnal}', [GuruJurnalController::class, 'show'])->name('guru.jurnal.show');
    Route::get('/jurnal/{jurnal}/edit', [GuruJurnalController::class, 'edit'])->name('guru.jurnal.edit');
    Route::put('/jurnal/{jurnal}', [GuruJurnalController::class, 'update'])->name('guru.jurnal.update');

    // Izin Guru (pengajuan oleh guru + pelacakan status)
    Route::get('/izin', [GuruIzinController::class, 'index'])->name('guru.izin.index');
    Route::get('/izin/create', [GuruIzinController::class, 'create'])->name('guru.izin.create');
    Route::post('/izin', [GuruIzinController::class, 'store'])->name('guru.izin.store');
    Route::get('/izin/{id}', [GuruIzinController::class, 'show'])->name('guru.izin.show');
    Route::get('/izin/{id}/lampiran', [GuruIzinController::class, 'showLampiran'])->name('guru.izin.lampiran');
});

// ================= ROUTE PORTAL WALI KELAS =================
Route::prefix('walikelas')->middleware(['auth'])->group(function () {
    Route::get('/dashboard', [WaliKelasController::class, 'dashboard'])->name('walikelas.dashboard');
    Route::get('/rekap-absen', [WaliKelasController::class, 'rekapAbsen'])->name('walikelas.rekap-absen');
    Route::get('/riwayat-jurnal', [WaliKelasController::class, 'riwayatJurnal'])->name('walikelas.riwayat-jurnal');
    Route::get('/riwayat-jurnal/{jurnal}', [WaliKelasController::class, 'showJurnal'])->name('walikelas.riwayat-jurnal.show');
    Route::get('/siswa-bermasalah', [WaliKelasController::class, 'siswaBermasalah'])->name('walikelas.siswa-bermasalah');
    Route::post('/siswa-bermasalah/tindak-lanjut', [WaliKelasController::class, 'siswaBermasalahStore'])->name('walikelas.siswa-bermasalah.store');
});

// ================= PORTAL GURU PIKET =================
use App\Http\Controllers\DispensasiController;
use App\Http\Controllers\GuruPiketController;
// ================= APPROVAL IZIN GURU PUBLIK (tanpa login, via link/QR unik) =================
// Link dikirim ke Waka & Kepala Sekolah melalui WhatsApp. Satu token menangani
// seluruh langkah publik (Waka -> Kepsek) sesuai level approval yang dikonfigurasi.
use App\Http\Controllers\IzinApprovalController;
use App\Http\Controllers\IzinPiketController;
use App\Http\Controllers\IzinPiketQuickApproveController;
use App\Http\Controllers\StatusKehadiranGuruController;

Route::get('/approve-izin/{token}', [IzinApprovalController::class, 'show'])->name('izin.approval.show');
Route::post('/approve-izin/{token}', [IzinApprovalController::class, 'submit'])->name('izin.approval.submit');

// Quick Approve tahap "Menunggu Piket" — link disiarkan via WA ke Guru Piket
// bertugas. Guru Piket pertama menyetujui, piket lain melihat "sudah diproses".
Route::get('/approve-piket/{token}', [IzinPiketQuickApproveController::class, 'show'])->name('piket.quick-approve.show');
Route::post('/approve-piket/{token}', [IzinPiketQuickApproveController::class, 'approve'])->name('piket.quick-approve.submit');

Route::get('/dispen/approve/{token}', [DispensasiController::class, 'publicApproveView'])->name('dispen.approval.show');
Route::post('/dispen/approve/{token}', [DispensasiController::class, 'publicApproveStore'])->name('dispen.approval.store');

Route::prefix('piket')->middleware(['auth'])->group(function () {
    Route::get('/dashboard', [GuruPiketController::class, 'dashboard'])->name('piket.dashboard');
    Route::get('/presensi-guru', [GuruPiketController::class, 'presensiGuru'])->name('piket.presensi-guru');
    Route::get('/presensi-siswa', [GuruPiketController::class, 'presensiSiswa'])->name('piket.presensi-siswa');
    Route::post('/presensi-siswa', [GuruPiketController::class, 'storePresensiSiswa'])->name('piket.presensi-siswa.store');
    Route::get('/jurnal', [GuruPiketController::class, 'jurnalKBM'])->name('piket.jurnal');
    Route::put('/jurnal/{id}/update-piket', [JurnalMengajarController::class, 'updateByPiket'])->name('piket.jurnal.updateByPiket');

    // Dispensasi Siswa oleh Guru Piket
    Route::get('/dispensasi', [DispensasiController::class, 'index'])->name('piket.dispensasi.index');
    Route::get('/dispensasi/create', [DispensasiController::class, 'create'])->name('piket.dispensasi.create');
    Route::get('/dispensasi/siswa-by-kelas', [DispensasiController::class, 'siswaByKelas'])->name('piket.dispensasi.siswa-by-kelas');
    Route::get('/dispensasi/terlambat-hari-ini', [DispensasiController::class, 'terlambatHariIni'])->name('piket.dispensasi.terlambat-hari-ini');
    Route::get('/dispensasi/jam-pelajaran', [DispensasiController::class, 'apiJamPelajaran'])->name('piket.dispensasi.jam-pelajaran');
    Route::post('/dispensasi', [DispensasiController::class, 'store'])->name('piket.dispensasi.store');
    Route::get('/dispensasi/{id}/surat', [DispensasiController::class, 'showSurat'])->name('piket.dispensasi.surat');
    Route::get('/dispensasi/{id}/ttd', [DispensasiController::class, 'showTtd'])->name('piket.dispensasi.ttd');
    Route::post('/dispensasi/{id}/ttd', [DispensasiController::class, 'saveTtd'])->name('piket.dispensasi.ttd-save');
    Route::post('/dispensasi/{id}/batalkan', [DispensasiController::class, 'pembatalanStore'])->name('piket.dispensasi.batalkan');
    Route::get('/dispensasi/kolektif/{id}/surat', [DispensasiController::class, 'showSuratKolektif'])->name('piket.dispensasi.kolektif.surat');

    // Izin Guru oleh Guru Piket (verifikasi Step 1)
    Route::get('/izin', [IzinPiketController::class, 'index'])->name('piket.izin.index');
    Route::post('/izin/{id}/approve', [IzinPiketController::class, 'approve'])->name('piket.izin.approve');
    Route::post('/izin/{id}/reject', [IzinPiketController::class, 'reject'])->name('piket.izin.reject');

    // Status Kehadiran Guru oleh Guru Piket (pantau & override status harian)
    Route::get('/status-guru', [StatusKehadiranGuruController::class, 'index'])->name('piket.status-guru');
    Route::post('/status-guru/update', [StatusKehadiranGuruController::class, 'update'])->name('piket.status-guru.update');
});

// ================= PORTAL KOORDINATOR PIKET (tugas dinamis dari jadwal) =================
use App\Http\Controllers\KoordinatorPiketController;

Route::prefix('koordinator')->middleware(['auth', 'koordinator-piket'])->group(function () {
    // Panel ringkasan shift yang dipimpin: daftar anggota, monitoring kehadiran,
    // dan penyusunan Rekap Piket Shift (dikirim ke Waka Piket untuk validasi)
    Route::get('/piket', [KoordinatorPiketController::class, 'panel'])->name('koordinator.piket');
    Route::post('/piket/kehadiran', [KoordinatorPiketController::class, 'simpanKehadiran'])->name('koordinator.piket.kehadiran');
    Route::post('/piket/rekap', [KoordinatorPiketController::class, 'simpanRekap'])->name('koordinator.piket.rekap');
});

// ================= PORTAL SATPAM / KEAMANAN (independen, tanpa cek jadwal piket) =================
use App\Http\Controllers\SatpamController;

Route::prefix('satpam')->middleware(['auth'])->group(function () {
    Route::get('/', fn () => redirect()->route('satpam.dashboard'));
    Route::get('/dashboard', [SatpamController::class, 'dashboard'])->name('satpam.dashboard');
    Route::post('/terlambat', [SatpamController::class, 'terlambatStore'])->name('satpam.terlambat.store');
    Route::post('/dispensasi', [SatpamController::class, 'dispensasiStore'])->name('satpam.dispensasi.store');
    Route::get('/verifikasi', [SatpamController::class, 'verifikasi'])->name('satpam.verifikasi');
    Route::get('/dispensasi', [SatpamController::class, 'verifikasi'])->name('satpam.dispensasi.index');
    Route::post('/dispensasi/{dispen}/keluar', [SatpamController::class, 'dispenKeluar'])->name('satpam.dispen.keluar');
    Route::post('/dispensasi/{dispen}/kembali', [SatpamController::class, 'dispenKembali'])->name('satpam.dispen.kembali');
    Route::post('/dispensasi-kolektif/{kolektif}/keluar', [SatpamController::class, 'kolektifKeluar'])->name('satpam.kolektif.keluar');
    Route::post('/dispensasi-kolektif/{kolektif}/kembali', [SatpamController::class, 'kolektifKembali'])->name('satpam.kolektif.kembali');
});

use App\Http\Controllers\PetugasItController;
use App\Http\Controllers\IT\WaSettingController;

// ================= ROUTE PETUGAS IT / QA TESTER (Switch View As) =================
Route::prefix('it')->middleware(['auth'])->group(function () {
    Route::get('/dashboard', [PetugasItController::class, 'dashboard'])->name('it.dashboard');
    Route::post('/switch-view', [PetugasItController::class, 'switchView'])->name('it.switch-view');
    Route::post('/reset-view', [PetugasItController::class, 'resetView'])->name('it.reset-view');
    Route::post('/impersonate-target', [PetugasItController::class, 'selectImpersonateTarget'])->name('it.impersonate-target');
    Route::post('/testing-view', [PetugasItController::class, 'setTestingView'])->name('it.testing-view');
    Route::post('/maintenance-mode', [PetugasItController::class, 'toggleMaintenanceMode'])->name('it.maintenance-mode');
    Route::post('/kendala/{id}/status', [PetugasItController::class, 'updateKendalaStatus'])->name('it.kendala.status');

    // Pengaturan WhatsApp Gateway (Fonnte)
    Route::get('/settings/wa', [WaSettingController::class, 'index'])->name('it.settings.wa');
    Route::post('/settings/wa/update', [WaSettingController::class, 'update'])->name('it.settings.wa.update');
    Route::post('/settings/wa/test', [WaSettingController::class, 'test'])->name('it.settings.wa.test');
    Route::post('/settings/wa/toggle', [WaSettingController::class, 'toggle'])->name('it.settings.wa.toggle');
});

use App\Http\Controllers\Kurikulum\JadwalPiketController;

// ================= ROUTE PORTAL WAKA KURIKULUM =================
Route::prefix('kurikulum')->middleware(['auth'])->group(function () {
    Route::get('/dashboard', [KurikulumDashboardController::class, 'index'])->name('kurikulum.dashboard');

    // Jadwal Piket Guru
    Route::get('/jadwal-piket', [JadwalPiketController::class, 'index'])->name('kurikulum.jadwal-piket.index');
    Route::get('/jadwal-piket/shifts', [JadwalPiketController::class, 'shifts'])->name('kurikulum.jadwal-piket.shifts');
    Route::post('/jadwal-piket/shifts', [JadwalPiketController::class, 'storeShift'])->name('kurikulum.jadwal-piket.shifts.store');
    Route::put('/jadwal-piket/shifts/{shift}', [JadwalPiketController::class, 'updateShift'])->name('kurikulum.jadwal-piket.shifts.update');
    Route::delete('/jadwal-piket/shifts/{shift}', [JadwalPiketController::class, 'destroyShift'])->name('kurikulum.jadwal-piket.shifts.destroy');
    Route::get('/jadwal-piket/create', [JadwalPiketController::class, 'create'])->name('kurikulum.jadwal-piket.create');
    Route::get('/jadwal-piket/{hari}/edit', [JadwalPiketController::class, 'edit'])->name('kurikulum.jadwal-piket.edit');
    Route::post('/jadwal-piket', [JadwalPiketController::class, 'store'])->name('kurikulum.jadwal-piket.store');
    Route::delete('/jadwal-piket/{id}', [JadwalPiketController::class, 'destroy'])->name('kurikulum.jadwal-piket.destroy');

    Route::get('/approval-dispensasi', [DispensasiController::class, 'indexApproval'])->name('kurikulum.dispensasi.approval.index');
    Route::post('/approval-dispensasi/{id}', [DispensasiController::class, 'storeApproval'])->name('kurikulum.dispensasi.approval.store');

    Route::get('/laporan', [KurikulumLaporanController::class, 'index'])->name('kurikulum.laporan.index');
    Route::get('/laporan/export-excel', [KurikulumLaporanController::class, 'exportExcel'])->name('kurikulum.laporan.excel');
    Route::get('/laporan/print', [KurikulumLaporanController::class, 'printPdf'])->name('kurikulum.laporan.print');
});

// Legacy redirect dari Kurikulum Izin ke Waka SDM
Route::redirect('/kurikulum/izin', '/admin/waka-sdm/rekap-izin');
Route::redirect('/kurikulum/izin/pengaturan', '/admin/waka-sdm/izin/pengaturan');

// ================= ROUTE MASTER & PLOTTING JADWAL (ADMIN / TU) =================
Route::prefix('admin')->middleware(['auth', AdminScheduleAccess::class])->group(function () {
    Route::get('/jam-pelajaran', [JamPelajaranController::class, 'index'])->name('admin.jam-pelajaran.index');
    Route::post('/jam-pelajaran', [JamPelajaranController::class, 'store'])->name('admin.jam-pelajaran.store');
    Route::put('/jam-pelajaran/{jamPelajaran}', [JamPelajaranController::class, 'update'])->name('admin.jam-pelajaran.update');
    Route::delete('/jam-pelajaran/{jamPelajaran}', [JamPelajaranController::class, 'destroy'])->name('admin.jam-pelajaran.destroy');
    Route::delete('/jam-pelajaran/truncate/{kategori_hari}', [JamPelajaranController::class, 'destroyAll'])->name('admin.jam-pelajaran.destroy-all');
    Route::post('/jam-pelajaran/generate-preset', [JamPelajaranController::class, 'generatePreset'])->name('admin.jam-pelajaran.generate');
    Route::get('/jam-pelajaran/generate-check', [JamPelajaranController::class, 'checkGeneratePreset'])->name('admin.jam-pelajaran.generate-check');
    Route::post('/jam-pelajaran/copy-from', [JamPelajaranController::class, 'copyFromPrevious'])->name('admin.jam-pelajaran.copy-from');
    Route::post('/jam-pelajaran/bulk-update', [JamPelajaranController::class, 'bulkUpdate'])->name('admin.jam-pelajaran.bulk-update');
    Route::post('/jam-pelajaran/schedule-mode', [JamPelajaranController::class, 'updateScheduleMode'])->name('admin.jam-pelajaran.schedule-mode');
    Route::post('/jam-pulang/upsert', [JamPulangController::class, 'upsert'])->name('admin.jam-pulang.upsert');

    // CRUD Master Shift (Shift 1 Pagi, Shift 2 Siang, dst.) dari Master Jam Pelajaran
    Route::post('/shift-pelajaran', [ShiftPelajaranController::class, 'store'])->name('admin.shift-pelajaran.store');
    Route::put('/shift-pelajaran/{shiftPelajaran}', [ShiftPelajaranController::class, 'update'])->name('admin.shift-pelajaran.update');
    Route::delete('/shift-pelajaran/{shiftPelajaran}', [ShiftPelajaranController::class, 'destroy'])->name('admin.shift-pelajaran.destroy');
    Route::post('/agenda-rutin/upsert', [AgendaRutinController::class, 'upsert'])->name('admin.agenda-rutin.upsert');
    Route::post('/toggle-senin-tanpa-upacara', [PengaturanJadwalController::class, 'toggleSeninTanpaUpacara'])->name('admin.toggle-senin-tanpa-upacara');
    Route::post('/toggle-mode-khusus', [PengaturanJadwalController::class, 'toggleModeKhusus'])->name('admin.toggle-mode-khusus');

    Route::get('/jadwal', [JadwalPelajaranController::class, 'index'])->name('admin.jadwal.index');
    Route::get('/jadwal/export', [JadwalPelajaranController::class, 'export'])->name('admin.jadwal.export');
    Route::get('/jadwal/debug/{id_kelas}', [JadwalPelajaranController::class, 'debugJadwalKelas'])->name('admin.jadwal.debug');
    Route::get('/jadwal/slot-kosong', [JadwalPelajaranController::class, 'monitoringSlotKosong'])->name('admin.jadwal.monitoring');
    Route::post('/jadwal', [JadwalPelajaranController::class, 'store'])->name('admin.jadwal.store');
    Route::put('/jadwal/{jadwalPelajaran}', [JadwalPelajaranController::class, 'update'])->name('admin.jadwal.update');
    Route::delete('/jadwal/{jadwalPelajaran}', [JadwalPelajaranController::class, 'destroy'])->name('admin.jadwal.destroy');
    Route::get('/jadwal-pelajaran', fn () => redirect()->route('admin.jadwal.index'));
});

// ================= ROUTE PORTAL WAKA SDM (KEPEGAWAIAN) =================
Route::prefix('admin/waka-sdm')->middleware(['auth'])->group(function () {
    Route::get('/dashboard', [WakaSdmController::class, 'dashboard'])->name('waka-sdm.dashboard');
    Route::get('/rekap-izin', [WakaSdmController::class, 'rekapIzin'])->name('waka-sdm.rekap-izin');
    Route::get('/izin', [WakaSdmController::class, 'rekapIzin'])->name('waka-sdm.izin.index');
    Route::post('/izin/{id}/approve', [WakaSdmController::class, 'approveIzin'])->name('waka-sdm.izin.approve');
    Route::post('/izin/{id}/approve-signature', [WakaSdmController::class, 'approveIzinSignature'])->name('waka-sdm.izin.approve-signature');
    Route::post('/izin/{id}/reject', [WakaSdmController::class, 'rejectIzin'])->name('waka-sdm.izin.reject');
    Route::get('/izin/pengaturan', [WakaSdmController::class, 'settingIzin'])->name('waka-sdm.izin.setting');
    Route::post('/izin/pengaturan', [WakaSdmController::class, 'updateSettingIzin'])->name('waka-sdm.izin.setting.update');
    Route::get('/rekap-presensi-guru', [WakaSdmController::class, 'rekapPresensiGuru'])->name('waka-sdm.rekap-presensi-guru');
    Route::get('/rekap-presensi-guru/export-excel', [WakaSdmController::class, 'exportExcelPresensi'])->name('waka-sdm.export-excel');
    Route::get('/rekap-presensi-guru/print', [WakaSdmController::class, 'printPresensi'])->name('waka-sdm.print-presensi');
    Route::get('/izin/{id}/lampiran', [WakaSdmController::class, 'showLampiran'])->name('waka-sdm.izin.lampiran');
});
// ================= ROUTE PORTAL KEPALA SEKOLAH =================
Route::prefix('admin/kepsek')->middleware(['auth'])->group(function () {
    Route::get('/dashboard', [KepsekController::class, 'dashboard'])->name('kepsek.dashboard');
    Route::get('/rekap-izin', [KepsekController::class, 'rekapIzin'])->name('kepsek.rekap-izin');
    Route::get('/izin', [KepsekController::class, 'rekapIzin'])->name('kepsek.izin.index');
    Route::post('/izin/{id}/approve-signature', [KepsekController::class, 'approveIzinSignature'])->name('kepsek.izin.approve-signature');
    Route::post('/izin/{id}/reject', [KepsekController::class, 'rejectIzin'])->name('kepsek.izin.reject');
});

// ================= ROUTE PORTAL WAKA KESISWAAN =================
use App\Http\Controllers\WakaKesiswaanController;

Route::prefix('admin/waka-kesiswaan')->middleware(['auth', 'waka-kesiswaan'])->group(function () {
    Route::get('/dashboard', [WakaKesiswaanController::class, 'dashboard'])->name('waka-kesiswaan.dashboard');
    Route::get('/approval-dispensasi', [WakaKesiswaanController::class, 'approvalIndex'])->name('waka-kesiswaan.dispensasi.approval.index');
    Route::post('/approval-dispensasi/{id}', [WakaKesiswaanController::class, 'approvalStore'])->name('waka-kesiswaan.dispensasi.approval.store');
});

// ================= ROUTE PORTAL WAKA PIKET =================
use App\Http\Controllers\WakaPiket\WakaPiketController;

Route::prefix('admin/waka-piket')->middleware(['auth', 'waka-piket'])->group(function () {
    Route::get('/dashboard', [WakaPiketController::class, 'dashboard'])->name('waka-piket.dashboard');
    Route::get('/rekap-harian', [WakaPiketController::class, 'rekapHarian'])->name('waka-piket.rekap-harian');
    Route::post('/rekap-harian/validasi', [WakaPiketController::class, 'validasiRekap'])->name('waka-piket.validasi');
    Route::post('/rekap-harian/klb', [WakaPiketController::class, 'storeCatatanKlb'])->name('waka-piket.klb');
});
