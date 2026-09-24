<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'WebJournal Management System')</title>

    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Alpine.js CDN -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Unpoly — SPA ringan untuk navigasi sidebar tanpa full page reload.
         Hanya fragmen <main id="page-content"> yang di-swap; sidebar & header
         tetap utuh (posisi scroll sidebar & dropdown yang terbuka tidak ter-reset).
         Dimuat sebelum bundle aplikasi (app.js dari Vite) agar global window.up tersedia. -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/unpoly@3.14.3/unpoly.css">
    <script src="https://cdn.jsdelivr.net/npm/unpoly@3.14.3/unpoly.js" defer></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        [x-cloak] {
            display: none !important;
        }

        :root {
            --sidebar-w: 260px;
            --sidebar-bg: #dbe6f1;
            --sidebar-border: #cad9e8;
            --topbar-bg: #dbe6f1;
            --topbar-border: #cad9e8;
            --main-bg: #f4f7fa;
            --primary-blue: #1677ff;
            --primary-blue-hover: #0958d9;
            --text-dark: #0f172a;
            --text-muted: #5a6e85;
            --sidebar-text: #475569;
            --sidebar-hover-bg: rgba(255, 255, 255, 0.45);
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--main-bg);
            color: var(--text-dark);
            min-height: 100vh;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        .app-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* ================= SIDEBAR ================= */
        .sidebar {
            width: var(--sidebar-w);
            background-color: var(--sidebar-bg);
            border-right: 1px solid var(--sidebar-border);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 1040; /* drawer mobile ~= Tailwind z-50 (di atas backdrop 1035) */
            transition: transform 0.3s ease;
        }

        /* Brand Logo Area */
        .sidebar-brand {
            padding: 1.5rem 1.25rem 1.25rem 1.35rem;
            display: flex;
            align-items: center;
            gap: 0.85rem;
        }

        .brand-logo-icon {
            width: 42px;
            height: 42px;
            background-color: #1677ff;
            color: #ffffff;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            box-shadow: 0 4px 12px rgba(22, 119, 255, 0.25);
            flex-shrink: 0;
        }

        .brand-title {
            font-weight: 800;
            font-size: 1.25rem;
            color: #0d2847;
            line-height: 1.1;
            letter-spacing: -0.02em;
        }

        .brand-sub {
            font-size: 0.625rem;
            font-weight: 800;
            color: #5a738e;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            display: block;
            margin-top: 2px;
        }

        /* Sidebar Navigation Items */
        .sidebar-nav {
            padding: 0.75rem 1rem;
            flex: 1;
            overflow-y: auto;
        }

        .nav-item-container {
            margin-bottom: 0.35rem;
        }

        .nav-btn {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            padding: 0.7rem 1rem;
            color: var(--sidebar-text);
            font-weight: 600;
            font-size: 0.925rem;
            border-radius: 12px;
            text-decoration: none;
            transition: all 0.2s ease;
            background: transparent;
            border: none;
        }

        .nav-btn:hover {
            background-color: var(--sidebar-hover-bg);
            color: var(--text-dark);
        }

        .nav-btn.active {
            background-color: var(--primary-blue);
            color: #ffffff;
            box-shadow: 0 4px 14px rgba(22, 119, 255, 0.3);
        }

        .nav-btn .btn-left {
            display: flex;
            align-items: center;
            gap: 0.85rem;
        }

        .nav-btn i {
            font-size: 1.15rem;
        }

        .nav-btn .chevron-icon {
            font-size: 0.75rem;
            transition: transform 0.2s ease;
        }

        .nav-btn .chevron-icon.rotate-180 {
            transform: rotate(180deg);
        }

        /* Badge live (antrean belum diproses) — efek ping, mirip animate-pulse */
        .nav-reset-badge-live {
            animation: navBadgePing 1.8s cubic-bezier(0, 0, 0.2, 1) infinite;
        }
        @keyframes navBadgePing {
            0% {
                box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.5);
            }
            70% {
                box-shadow: 0 0 0 7px rgba(220, 53, 69, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
            }
        }

        /* Submenu Styling */
        .submenu-list {
            position: relative;
            padding-left: 2.25rem;
            margin-top: 0.25rem;
            margin-bottom: 0.5rem;
            list-style: none;
        }

        /* Garis vertikal tipis penunjuk hierarki submenu */
        .submenu-list::before {
            content: '';
            position: absolute;
            left: 25px;
            top: 6px;
            bottom: 6px;
            width: 2px;
            border-radius: 2px;
            background-color: #c6d5e4;
        }

        .submenu-item-link {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            padding: 0.5rem 0.85rem;
            color: #475569;
            font-size: 0.865rem;
            font-weight: 600;
            border-radius: 10px;
            text-decoration: none;
            transition: all 0.2s ease;
            position: relative;
        }

        .submenu-item-link:hover {
            background-color: rgba(255, 255, 255, 0.6);
            color: var(--primary-blue);
        }

        .submenu-item-link.active {
            color: var(--primary-blue);
            font-weight: 700;
            background-color: rgba(255, 255, 255, 0.85);
        }

        .submenu-item-link.active::before {
            content: '';
            position: absolute;
            left: -10px;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 16px;
            background-color: var(--primary-blue);
            border-radius: 4px;
        }

        /* Sidebar Bottom */
        .sidebar-bottom {
            padding: 1rem 1rem 1.25rem;
            border-top: 1px solid var(--sidebar-border);
        }

        /* ================= MAIN CONTENT & TOPBAR ================= */
        .main-wrapper {
            margin-left: var(--sidebar-w);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            background-color: var(--main-bg);
            min-height: 100vh;
        }

        /* Topbar Header */
        .topbar-header {
            height: 65px;
            background-color: var(--topbar-bg);
            border-bottom: 1px solid var(--topbar-border);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding: 0 2rem;
            position: sticky;
            top: 0;
            z-index: 1030; /* di bawah backdrop (1035) & drawer sidebar (1040) */
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }

        .notif-bell-btn {
            background: transparent;
            border: none;
            color: #334155;
            font-size: 1.25rem;
            position: relative;
            cursor: pointer;
            padding: 0.4rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s ease;
        }

        .notif-bell-btn:hover {
            color: #0f172a;
        }

        .notif-badge {
            position: absolute;
            top: -2px;
            right: -4px;
            min-width: 17px;
            height: 17px;
            padding: 0 4px;
            background-color: #ef4444;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            line-height: 17px;
            text-align: center;
            border-radius: 999px;
            border: 1.5px solid var(--topbar-bg);
        }

        .topbar-divider {
            width: 1px;
            height: 24px;
            background-color: #cbd5e1;
        }

        .user-dropdown-btn {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: transparent;
            border: none;
            padding: 0.25rem 0.5rem;
            border-radius: 10px;
            cursor: pointer;
            transition: background 0.2s ease;
            text-decoration: none;
        }

        .user-dropdown-btn:hover {
            background-color: rgba(255, 255, 255, 0.4);
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
        }

        .user-meta {
            text-align: left;
            line-height: 1.2;
        }

        .user-name {
            font-size: 0.875rem;
            font-weight: 800;
            color: #0f172a;
        }

        .user-role {
            font-size: 0.75rem;
            color: #64748b;
            font-weight: 500;
        }

        .user-chevron {
            font-size: 0.75rem;
            color: #64748b;
        }

        /* Page Content Area — padding diserahkan ke utility Tailwind px-3 sm:px-6 py-4
           pada elemen <main class="page-content"> (lebih tipis di layar mobile). */
        .page-content {
            flex: 1;
        }

        /* ================= SIDEBAR BACKDROP (mobile drawer) ================= */
        /* Urutan z-index layar mobile (iOS fix: tombol hamburger harus selalu bisa ditap):
             topbar 1030  <  backdrop 1035 (~z-40)  <  drawer sidebar 1040 (~z-50)  <  modal 1055  */
        .sidebar-backdrop {
            position: fixed;
            inset: 0;
            z-index: 1035;
            background: rgba(15, 23, 42, 0.55);
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
            -webkit-tap-highlight-color: transparent;
        }

        .sidebar-backdrop.show {
            opacity: 1;
            visibility: visible;
        }

        /* ================= FIX Z-INDEX MODAL (agar dialog selalu DI ATAS backdrop) =================
           Backdrop hitam transparan berada di bawah dialog modal sehingga tombol Tutup / X
           dan area konten modal tetap dapat diklik. Nilai ini menimpa default Bootstrap. */
        .modal-backdrop {
            z-index: 1040;
        }

        .modal {
            z-index: 1055;
        }

        .modal .modal-dialog {
            z-index: 1060;
        }

        /* Sembunyikan sidebar & tampilkan sebagai drawer overlay hanya di layar mobile (< 768px) */
        @media (max-width: 767.98px) {
            .sidebar {
                width: min(280px, 75vw);
                transform: translateX(-100%);
                box-shadow: 0 0 24px rgba(15, 23, 42, 0.35);
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-wrapper {
                margin-left: 0;
            }
            .topbar-header {
                justify-content: space-between;
                padding: 0 1rem;
            }
            /* Padding .page-content kini dari utility px-3 sm:px-6 py-4 (padanan:
               mobile 0.75rem samping / 1rem atas-bawah) — padding card tabel juga
               diringkas di mobile agar kolom tidak terdesak. */
            .table-card-custom {
                padding: 1rem 0.75rem;
            }

            /* Judul halaman: ukuran pas di layar HP agar tidak "zoom" memenuhi layar */
            .page-content h1 {
                font-size: 1.5rem !important;
            }
            .page-content h2 {
                font-size: 1.4rem !important;
            }
            .page-content h3 {
                font-size: 1.25rem !important;
            }
            .page-content h4,
            .page-content h5 {
                font-size: 1.1rem !important;
            }
        }

        /* ================= STAT CARDS (Dashboard) ================= */
        .stat-card-custom {
            background: #ffffff;
            border: 1px solid #e8eef5;
            border-radius: 16px;
            padding: 1.5rem 1.75rem 1.6rem;
            box-shadow: 0 2px 12px rgba(15, 23, 42, 0.05);
            height: 100%;
            transition: box-shadow 0.2s ease, transform 0.2s ease;
        }

        .stat-card-custom:hover {
            box-shadow: 0 6px 24px rgba(15, 23, 42, 0.09);
            transform: translateY(-2px);
        }

        .stat-card-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 1rem;
            letter-spacing: 0.01em;
        }

        .stat-number-large {
            font-size: 2.8rem;
            font-weight: 900;
            line-height: 1;
            margin-bottom: 0.5rem;
            letter-spacing: -0.03em;
        }

        .stat-card-label {
            font-size: 0.9rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.25rem;
        }

        .stat-card-subtext {
            font-size: 0.78rem;
            color: #94a3b8;
            font-weight: 500;
            margin-top: 0.5rem;
            margin-bottom: 0;
        }

        @media (max-width: 767.98px) {
            .stat-card-custom {
                padding: 0.875rem 0.9rem !important;
                border-radius: 12px;
            }
            .stat-card-title {
                font-size: 0.75rem !important;
                margin-bottom: 0.35rem !important;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .stat-number-large {
                font-size: 1.5rem !important;
                margin-bottom: 0.25rem !important;
            }
            .stat-card-label {
                font-size: 0.75rem !important;
                margin-bottom: 0.15rem !important;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .stat-card-subtext {
                font-size: 0.7rem !important;
                margin-top: 0.25rem !important;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
        }

        /* ================= TABLE CARD ================= */
        .table-card-custom {
            background: #ffffff;
            border: 1px solid #e8eef5;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(15, 23, 42, 0.05);
            padding: 1.75rem 2rem;
        }

        .table-custom {
            margin-bottom: 0;
        }

        .table-custom th {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: #64748b;
            padding: 0.9rem 0.75rem;
            border-bottom: 1.5px solid #f1f5f9;
            white-space: nowrap;
        }

        .table-custom td {
            padding: 1rem 0.75rem;
            font-size: 0.9rem;
            vertical-align: middle;
            color: #1e293b;
            border-bottom: 1px solid #f8fafc;
        }

        .table-custom tbody tr:last-child td {
            border-bottom: none;
        }

        .table-custom tbody tr:hover td {
            background-color: #fafbfd;
        }

        .status-badge-terisi {
            background-color: #ecfdf5;
            color: #059669;
            border: 1px solid #a7f3d0;
            border-radius: 50px;
            padding: 0.3rem 0.8rem;
            font-size: 0.78rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            white-space: nowrap;
        }

    </style>
</head>
<body>

<div class="app-wrapper">

    <!-- SIDEBAR BACKDROP (mobile overlay) -->
    <div class="sidebar-backdrop" id="sidebarBackdrop" hidden></div>

    <!-- SIDEBAR NAVIGATION -->
    <aside class="sidebar" id="appSidebar">
        <!-- Brand Logo -->
        <div class="sidebar-brand">
            <div class="brand-logo-icon">
                <i class="bi bi-journal-bookmark-fill"></i>
            </div>
            <div>
                <span class="brand-title">WebJournal</span>
                <span class="brand-sub">MANAGEMENT SYSTEM</span>
            </div>
        </div>

        <!-- Navigation Menu -->
        <div class="sidebar-nav">
            @php
                $user = auth()->user();
                $userRole = $user ? $user->role : null;
                $userSubRole = $user ? $user->sub_role : null;

                // ============ ACTIVE ROLE (Petugas IT / QA - Switch View As) ============
                $previewRole = $user && $user->hasActiveRole() ? $user->activeRole() : null;

                if ($previewRole && $previewRole !== 'siswa') {
                    $previewRoleMap = \App\Models\User::PREVIEW_ROLE_MAP;
                    if (isset($previewRoleMap[$previewRole])) {
                        $userRole    = $previewRoleMap[$previewRole]['role'];
                        $userSubRole = $previewRoleMap[$previewRole]['sub_role'];
                    }
                }
                // =====================================================================

                // 1. Role Waka Kurikulum (role=admin & sub_role=waka_kurikulum)
                $isKurikulumRole = ($userRole === 'admin' && $userSubRole === 'waka_kurikulum') 
                                || in_array($userRole, ['admin_kurikulum', 'waka_kurikulum', 'kurikulum']);

                // 1b. Role Waka SDM (role=admin & sub_role=waka_sdm)
                $isWakaSdmRole = ($userRole === 'admin' && $userSubRole === 'waka_sdm') 
                              || in_array($userRole, ['waka_sdm', 'admin_sdm', 'sdm']);

                // 1b2. Role Waka Kesiswaan (role=admin & sub_role=waka_kesiswaan)
                $isWakaKesiswaanRole = ($userRole === 'admin' && $userSubRole === 'waka_kesiswaan')
                                     || in_array($userRole, ['waka_kesiswaan', 'admin_kesiswaan', 'kesiswaan'])
                                     // hanya panggil method asli jika tidak sedang preview
                                     || (!$previewRole && $user && $user->isWakaKesiswaan());

                // 1b3. Role Waka Piket (role=admin & sub_role=waka_piket).
                //      $userRole/$userSubRole sudah di-resolve dari previewRole,
                //      sehingga impersonasi 'waka_piket' otomatis terdeteksi.
                $isWakaPiketRole = ($userRole === 'admin' && $userSubRole === 'waka_piket')
                                || in_array($userRole, ['waka_piket', 'admin_piket', 'piket'])
                                || (!$previewRole && $user && $user->isWakaPiket());

                // 1c. Role Kepala Sekolah (role=kepsek / kepala_sekolah / admin & sub_role=kepsek)
                $isKepsekRole = ($userRole === 'admin' && in_array($userSubRole, ['kepsek', 'kepala_sekolah'])) 
                             || in_array($userRole, ['kepsek', 'kepala_sekolah'])
                             // hanya panggil method asli jika tidak sedang preview
                             || (!$previewRole && $user && $user->isKepsek());

                // 2. Role Satpam — gunakan $userRole/$userSubRole (sudah di-resolve dari previewRole)
                //    JANGAN pakai $user->isSatpam() karena itu membaca role asli dari DB.
                $isSatpamRole = ($userRole === 'admin' && $userSubRole === 'satpam')
                             || $userRole === 'piket_satpam';

                // 3. Petugas Piket ditentukan dari jadwal_piket pada hari berjalan (Senin–Jumat).
                //    Saat preview 'guru_piket', dipaksa aktif agar menu terlihat.
                $isGuruPiketRole = ($previewRole === 'guru_piket') 
                                || ($user && !$previewRole && $user->isPetugasPiketHariIni());

                // 3b. Koordinator Piket — tugas DINAMIS dari jadwal (bukan role tetap):
                //     menu tampil bila user tercatat sebagai koordinator_pagi/siang
                //     pada jadwal hari berjalan. Berbasis jadwal, jadi berlaku juga
                //     untuk akun Petugas IT yang sedang preview/guru berjadwal.
                $isKoordinatorPiketRole = $user && $user->koordinatorShiftHariIni() !== [];

                // 4. Role Wali Kelas (role=guru & sub_role=wali_kelas, atau terikat sebagai wali kelas).
                //    Deteksi lewat model (sub_role wali_kelas / terikat kelas), bukan hanya role string.
                $isWaliKelasUser = ($user && !$previewRole) ? $user->isWaliKelas() : false;
                $isWaliKelasRole = ($userRole === 'guru' && $userSubRole === 'wali_kelas')
                                || ($userRole === 'wali_kelas')
                                || $isWaliKelasUser;

                // 5. Guru Context (Guru Mapel & Wali Kelas).
                $isGuruRole = ($userRole === 'guru');
                $isGuruContext = $isGuruRole;

                // 6. Petugas TU & Super Admin
                $isSuperAdmin = ($userRole === 'admin' && $userSubRole === null);
                $isPetugasTU = ($userRole === 'admin' && $userSubRole === 'petugas_tu') || ($userRole === 'admin_tu');

                // Petugas IT / QA Tester (mode asli, tanpa preview)
                $isPetugasItRole = (!$previewRole && $user && $user->isPetugasIt());

                // Preview role sebagai Siswa (belum ada portal khusus)
                $isPreviewSiswa = ($previewRole === 'siswa');

                // Jumlah pengajuan reset kredensial yang MENUNGGU VERIFIKASI
                // (badge merah live di menu "Pengajuan Reset"). Mengikuti
                // TestingDataScope: Petugas TU melihat data real, IT/QA melihat
                // data testing — konsisten dengan isi panel.
                $pendingResetCount = \App\Models\ResetRequest::where('status', \App\Models\ResetRequest::STATUS_PENDING)->count();
            @endphp

            @if($isKurikulumRole)
                {{-- ================= NAVIGASI WAKA KURIKULUM ================= --}}
                <x-sidebar-kurikulum :pendingIzinCount="\App\Models\IzinGuru::whereIn('status', [\App\Models\IzinGuru::STATUS_PENDING_PIKET, \App\Models\IzinGuru::STATUS_PENDING_WAKA, \App\Models\IzinGuru::STATUS_PENDING_KEPSEK])->count()" />

            @elseif($isWakaSdmRole)
                {{-- ================= NAVIGASI WAKA SDM ================= --}}
                <x-sidebar-waka-sdm :pendingIzinCount="\App\Models\IzinGuru::whereIn('status', [\App\Models\IzinGuru::STATUS_PENDING_PIKET, \App\Models\IzinGuru::STATUS_PENDING_WAKA, \App\Models\IzinGuru::STATUS_PENDING_KEPSEK])->count()" />

            @elseif($isWakaKesiswaanRole)
                {{-- ================= NAVIGASI WAKA KESISWAAN ================= --}}
                <x-sidebar-waka-kesiswaan :pendingTtdCount="\App\Models\DispensasiSiswa::whereNull('ttd_waka')->where('tipe_dispen', '!=', \App\Models\DispensasiSiswa::TIPE_MASUK)->whereIn('status', [\App\Models\DispensasiSiswa::STATUS_PENDING, \App\Models\DispensasiSiswa::STATUS_PENDING_WAKA, \App\Models\DispensasiSiswa::STATUS_DISETUJUI])->count()" />

            @elseif($isWakaPiketRole)
                {{-- ================= NAVIGASI WAKA PIKET ================= --}}
                <x-sidebar-waka-piket />

            @elseif($isKepsekRole)
                {{-- ================= NAVIGASI KEPALA SEKOLAH ================= --}}
                <x-sidebar-kepsek :pendingIzinCount="\App\Models\IzinGuru::where('status', \App\Models\IzinGuru::STATUS_PENDING_KEPSEK)->count()" />

            @elseif($isSatpamRole)
                {{-- ================= NAVIGASI SATPAM / KEAMANAN (portal independen) ================= --}}
                <div class="nav-item-container mt-2">
                    <div class="px-2 mb-2 text-uppercase fw-bold text-muted" style="font-size: 0.68rem; letter-spacing: 0.08em;">
                        SATPAM / KEAMANAN
                    </div>
                </div>

                <!-- Dashboard Satpam -->
                <div class="nav-item-container">
                    <a href="{{ route('satpam.dashboard') }}" class="nav-btn {{ request()->routeIs('satpam.dashboard') ? 'active' : '' }}">
                        <span class="btn-left">
                            <i class="bi bi-shield-lock"></i>
                            <span>Dashboard Satpam</span>
                        </span>
                    </a>
                </div>

                <!-- Verifikasi Izin & Dispensasi -->
                <div class="nav-item-container">
                    <a href="{{ route('satpam.verifikasi') }}" class="nav-btn {{ request()->routeIs('satpam.verifikasi') || request()->routeIs('satpam.dispensasi.index') ? 'active' : '' }}">
                        <span class="btn-left">
                            <i class="bi bi-qr-code-scan"></i>
                            <span>Verifikasi Izin &amp; Dispensasi</span>
                        </span>
                    </a>
                </div>

            @elseif($isGuruPiketRole && !$isGuruRole)
                {{-- ================= NAVIGASI GURU PIKET ================= --}}

                <!-- Dashboard Piket -->
                <div class="nav-item-container mt-2">
                    <a href="{{ route('piket.dashboard') }}" class="nav-btn {{ request()->routeIs('piket.dashboard') ? 'active' : '' }}">
                        <span class="btn-left">
                            <i class="bi bi-speedometer2"></i>
                            <span>Dashboard</span>
                        </span>
                    </a>
                </div>

                <!-- Jurnal Mengajar Saya -->
                <div class="nav-item-container">
                    <a href="{{ route('guru.jurnal') }}" class="nav-btn {{ request()->routeIs('guru.jurnal*') ? 'active' : '' }}">
                        <span class="btn-left">
                            <i class="bi bi-journal-bookmark"></i>
                            <span>Jurnal Mengajar Saya</span>
                        </span>
                    </a>
                </div>

                <div class="nav-item-container mt-2">
                    <div class="px-2 mb-2 text-uppercase fw-bold text-muted" style="font-size: 0.68rem; letter-spacing: 0.08em;">
                        GURU PIKET
                    </div>
                </div>

                {{-- Presensi Guru (disabled/placeholder) --}}
                {{-- <div class="nav-item-container">
                    <a href="{{ route('piket.presensi-guru') }}" class="nav-btn {{ request()->routeIs('piket.presensi-guru') ? 'active' : '' }}">
                        <span class="btn-left">
                            <i class="bi bi-person-check-fill"></i>
                            <span>Presensi Guru</span>
                        </span>
                    </a>
                </div> --}}

                <!-- Presensi Siswa -->
                <div class="nav-item-container">
                    <a href="{{ route('piket.presensi-siswa') }}" class="nav-btn {{ request()->routeIs('piket.presensi-siswa') ? 'active' : '' }}">
                        <span class="btn-left">
                            <i class="bi bi-people-fill"></i>
                            <span>Presensi Siswa</span>
                        </span>
                    </a>
                </div>

                <!-- Jurnal KBM Harian -->
                <div class="nav-item-container">
                    <a href="{{ route('piket.jurnal') }}" class="nav-btn {{ request()->routeIs('piket.jurnal') ? 'active' : '' }}">
                        <span class="btn-left">
                            <i class="bi bi-journal-text"></i>
                            <span>Jurnal KBM Harian</span>
                        </span>
                    </a>
                </div>

                <!-- Dispensasi Siswa -->
                <div class="nav-item-container">
                    <a href="{{ route('piket.dispensasi.index') }}" class="nav-btn {{ request()->routeIs('piket.dispensasi.index') || request()->routeIs('piket.dispensasi.create') ? 'active' : '' }}">
                        <span class="btn-left">
                            <i class="bi bi-clipboard2-check"></i>
                            <span>Dispensasi Siswa</span>
                        </span>
                    </a>
                </div>

                <!-- Approval Izin Guru (Piket) -->
                <div class="nav-item-container">
                    <a href="{{ route('piket.izin.index') }}" class="nav-btn {{ request()->routeIs('piket.izin*') ? 'active' : '' }}">
                        <span class="btn-left">
                            <i class="bi bi-person-check-fill"></i>
                            <span>Approval Izin Guru</span>
                        </span>
                    </a>
                </div>

                <!-- Status Kehadiran Guru -->
                <div class="nav-item-container">
                    <a href="{{ route('piket.status-guru') }}" class="nav-btn {{ request()->routeIs('piket.status-guru*') ? 'active' : '' }}">
                        <span class="btn-left">
                            <i class="bi bi-people-fill"></i>
                            <span>Status Kehadiran Guru</span>
                        </span>
                    </a>
                </div>

            @elseif($isGuruContext)
                {{-- ================= NAVIGASI GURU (GURU MAPEL & WALI KELAS) ================= --}}
                <div class="nav-item-container mt-2">
                    <div class="px-2 mb-2 text-uppercase fw-bold text-muted" style="font-size: 0.68rem; letter-spacing: 0.08em;">
                        PORTAL GURU
                    </div>
                </div>

                <!-- Dashboard (mengikuti role aktif: Guru Mapel / Guru Piket / Wali Kelas) -->
                @php
                    $isGuruPiketNav = ($previewRole === 'guru_piket') || request()->is('piket/*');
                    $isWaliKelasNav = $isWaliKelasRole && (($previewRole === 'wali_kelas') || request()->is('walikelas/*'));
                    $activeDashboardRoute = $isGuruPiketNav ? 'piket.dashboard'
                        : ($isWaliKelasNav ? 'walikelas.dashboard' : 'guru.dashboard');
                @endphp
                <div class="nav-item-container">
                    <a href="{{ route($activeDashboardRoute) }}" class="nav-btn {{ request()->routeIs($activeDashboardRoute) ? 'active' : '' }}">
                        <span class="btn-left">
                            <i class="bi bi-speedometer2"></i>
                            <span>Dashboard</span>
                        </span>
                    </a>
                </div>

                <!-- Jurnal Mengajar Guru -->
                <div class="nav-item-container">
                    <a href="{{ route('guru.jurnal') }}" class="nav-btn {{ request()->routeIs('guru.jurnal*') ? 'active' : '' }}">
                        <span class="btn-left">
                            <i class="bi bi-journal-text"></i>
                            <span>Jurnal Mengajar</span>
                        </span>
                    </a>
                </div>

                <!-- Izin Guru -->
                <div class="nav-item-container">
                    <a href="{{ route('guru.izin.index') }}" class="nav-btn {{ request()->routeIs('guru.izin*') ? 'active' : '' }}">
                        <span class="btn-left">
                            <i class="bi bi-person-dash"></i>
                            <span>Izin Guru</span>
                        </span>
                    </a>
                </div>

                <!-- Verifikasi Surat Masuk / Telat (Guru Mapel) -->
                <div class="nav-item-container">
                    <a href="{{ route('guru.dispensasi.verifikasi') }}" class="nav-btn {{ request()->routeIs('guru.dispensasi.*') ? 'active' : '' }}">
                        <span class="btn-left">
                            <i class="bi bi-qr-code-scan"></i>
                            <span>Verifikasi Surat Masuk</span>
                        </span>
                    </a>
                </div>

                <!-- SECTION KELAS SAYA: selalu tampil bagi wali kelas (bahkan saat membuka modul Piket) -->
                @if($isWaliKelasRole)
                    @php
                        $isKelasSayaActive = request()->is('*walikelas*') || request()->routeIs('walikelas.*');
                    @endphp
                    <div class="nav-item-container mt-3" x-data="{ open: {{ $isKelasSayaActive ? 'true' : 'false' }} }">
                        <div class="px-2 mb-2 text-uppercase fw-bold text-muted" style="font-size: 0.68rem; letter-spacing: 0.08em;">
                            KELAS SAYA
                        </div>
                        <button class="nav-btn {{ $isKelasSayaActive ? 'active' : '' }}" 
                                type="button"
                                :aria-expanded="open"
                                @click.prevent="open = !open">
                            <span class="btn-left">
                                <i class="bi bi-mortarboard-fill"></i>
                                <span>Kelas Saya</span>
                            </span>
                            <i class="bi bi-chevron-down chevron-icon transition-transform duration-200" :class="open ? 'rotate-180' : ''"></i>
                        </button>
                        
                        <div x-show="open" 
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 transform -translate-y-2"
                             x-transition:enter-end="opacity-100 transform translate-y-0"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 transform translate-y-0"
                             x-transition:leave-end="opacity-0 transform -translate-y-2"
                             id="dropdownKelasSaya">
                            <ul class="submenu-list">
                                <li>
                                    <a href="{{ route('walikelas.dashboard') }}" class="submenu-item-link {{ request()->routeIs('walikelas.dashboard') ? 'active' : '' }}">
                                        <i class="bi bi-speedometer2"></i>
                                        <span>Dashboard Dispen</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('walikelas.rekap-absen') }}" class="submenu-item-link {{ request()->routeIs('walikelas.rekap-absen') ? 'active' : '' }}">
                                        <i class="bi bi-clipboard-data"></i>
                                        <span>Rekap Absen Siswa</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('walikelas.riwayat-jurnal') }}" class="submenu-item-link {{ request()->routeIs('walikelas.riwayat-jurnal') ? 'active' : '' }}">
                                        <i class="bi bi-journal-bookmark"></i>
                                        <span>Riwayat Jurnal</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('walikelas.siswa-bermasalah') }}" class="submenu-item-link {{ request()->routeIs('walikelas.siswa-bermasalah') ? 'active' : '' }}">
                                        <i class="bi bi-exclamation-triangle-fill text-warning"></i>
                                        <span>Catatan Siswa Bermasalah</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                @endif

                <!-- SECTION GURU PIKET: tampil bila guru berjadwal piket pada hari berjalan -->
                @if($isGuruPiketRole)
                    <div class="nav-item-container mt-3">
                        <div class="px-2 mb-2 text-uppercase fw-bold text-muted" style="font-size: 0.68rem; letter-spacing: 0.08em;">
                            GURU PIKET
                        </div>
                    </div>

                    <!-- Presensi Siswa -->
                    <div class="nav-item-container">
                        <a href="{{ route('piket.presensi-siswa') }}" class="nav-btn {{ request()->routeIs('piket.presensi-siswa') ? 'active' : '' }}">
                            <span class="btn-left">
                                <i class="bi bi-people-fill"></i>
                                <span>Presensi Siswa</span>
                            </span>
                        </a>
                    </div>

                    <!-- Jurnal KBM Harian -->
                    <div class="nav-item-container">
                        <a href="{{ route('piket.jurnal') }}" class="nav-btn {{ request()->routeIs('piket.jurnal') ? 'active' : '' }}">
                            <span class="btn-left">
                                <i class="bi bi-journal-text"></i>
                                <span>Jurnal KBM Harian</span>
                            </span>
                        </a>
                    </div>

                    <!-- Dispensasi Siswa -->
                    <div class="nav-item-container">
                        <a href="{{ route('piket.dispensasi.index') }}" class="nav-btn {{ request()->routeIs('piket.dispensasi.index') || request()->routeIs('piket.dispensasi.create') ? 'active' : '' }}">
                            <span class="btn-left">
                                <i class="bi bi-clipboard2-check"></i>
                                <span>Dispensasi Siswa</span>
                            </span>
                        </a>
                    </div>

                    <!-- Approval Izin Guru (Piket) -->
                    <div class="nav-item-container">
                        <a href="{{ route('piket.izin.index') }}" class="nav-btn {{ request()->routeIs('piket.izin*') ? 'active' : '' }}">
                            <span class="btn-left">
                                <i class="bi bi-person-check-fill"></i>
                                <span>Approval Izin Guru</span>
                            </span>
                        </a>
                    </div>

                    <!-- Status Kehadiran Guru -->
                    <div class="nav-item-container">
                        <a href="{{ route('piket.status-guru') }}" class="nav-btn {{ request()->routeIs('piket.status-guru*') ? 'active' : '' }}">
                            <span class="btn-left">
                                <i class="bi bi-people-fill"></i>
                                <span>Status Kehadiran Guru</span>
                            </span>
                        </a>
                    </div>
                @endif

                {{-- SECTION TUGAS TAMBAHAN: tampil bila guru bertugas sebagai Koordinator Piket hari ini --}}
                @if($isKoordinatorPiketRole)
                    <div class="nav-item-container mt-3">
                        <div class="px-2 mb-2 text-uppercase fw-bold text-muted" style="font-size: 0.68rem; letter-spacing: 0.08em;">
                            TUGAS TAMBAHAN
                        </div>
                    </div>

                    <!-- Kelola Piket Shift (Koordinator) -->
                    <div class="nav-item-container">
                        <a href="{{ route('koordinator.piket') }}" class="nav-btn {{ request()->routeIs('koordinator.piket*') ? 'active' : '' }}">
                            <span class="btn-left">
                                <i class="bi bi-clipboard-check"></i>
                                <span>Kelola Piket Shift (Koordinator)</span>
                            </span>
                        </a>
                    </div>
                @endif

            @elseif($isPetugasItRole)
                {{-- ================= NAVIGASI PETUGAS IT / QA TESTER ================= --}}
                <div class="nav-item-container mt-2">
                    <div class="px-2 mb-2 text-uppercase fw-bold text-muted" style="font-size: 0.68rem; letter-spacing: 0.08em;">
                        QA / PETUGAS IT
                    </div>
                </div>

                <!-- Dashboard -->
                <div class="nav-item-container">
                    <a href="{{ route('it.dashboard') }}" class="nav-btn {{ request()->routeIs('home', 'it.dashboard') ? 'active' : '' }}">
                        <span class="btn-left">
                            <i class="bi bi-grid-fill"></i>
                            <span>Dashboard</span>
                        </span>
                    </a>
                </div>

                <div class="nav-item-container">
                    <div class="px-2 mb-2 mt-2 text-uppercase fw-bold text-muted" style="font-size: 0.68rem; letter-spacing: 0.08em;">
                        PENGUJIAN
                    </div>
                </div>

                <!-- Switch View As -->
                <div class="nav-item-container">
                    <a href="#switchViewAs" class="nav-btn {{ session('active_role') ? 'active' : '' }}">
                        <span class="btn-left">
                            <i class="bi bi-arrows-fullscreen"></i>
                            <span>Switch View As</span>
                        </span>
                    </a>
                </div>

                <div class="nav-item-container">
                    <div class="px-2 mb-2 mt-2 text-uppercase fw-bold text-muted" style="font-size: 0.68rem; letter-spacing: 0.06em;">
                        PERAWATAN
                    </div>
                </div>

                @php $isMaintenanceActive = \App\Models\PengaturanJadwal::isMaintenanceModeActive(); @endphp
                <div class="nav-item-container">
                    <div class="px-2">
                        <div class="card border-0 rounded-4 shadow-sm p-3" style="background:#ffffff;">
                            <div class="d-flex align-items-center gap-2">
                                <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0
                                            {{ $isMaintenanceActive ? 'text-white' : 'text-muted' }}"
                                     style="width:38px; height:38px; {{ $isMaintenanceActive ? 'background:linear-gradient(135deg,#f59e0b,#d97706);' : 'background:#eef2f7;' }}">
                                    <i class="bi bi-wrench-adjustable fs-5"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark small">Mode Maintenance</div>
                                    @if($isMaintenanceActive)
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1 mt-1" style="font-size:0.66rem;">
                                            <i class="bi bi-circle-fill me-1" style="font-size:0.4rem;"></i>AKTIF — PERBAIKAN
                                        </span>
                                    @else
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 mt-1" style="font-size:0.66rem;">
                                            <i class="bi bi-circle-fill me-1" style="font-size:0.4rem;"></i>NORMAL
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="text-muted mt-2 mb-1" style="font-size:0.72rem; line-height:1.4;">
                                @if($isMaintenanceActive)
                                    Seluruh pengguna selain IT/QA kini melihat halaman pemeliharaan.
                                @else
                                    Aktifkan untuk membatasi akses sistem selama perbaikan.
                                @endif
                            </div>
                            <form method="POST" action="{{ route('it.maintenance-mode') }}">
                                @csrf
                                <input type="hidden" name="maintenance_mode" value="{{ $isMaintenanceActive ? '0' : '1' }}">
                                <button type="submit" class="btn btn-sm {{ $isMaintenanceActive ? 'btn-success' : 'btn-danger' }} w-100 rounded-3 fw-semibold">
                                    <i class="bi {{ $isMaintenanceActive ? 'bi-power' : 'bi-shield-exclamation' }} me-1"></i>
                                    {{ $isMaintenanceActive ? 'Nonaktifkan Mode' : 'Aktifkan Mode' }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Pengaturan WhatsApp Gateway (Fonnte) -->
                <div class="nav-item-container">
                    <a href="{{ route('it.settings.wa') }}" class="nav-btn {{ request()->routeIs('it.settings.wa*') ? 'active' : '' }}">
                        <span class="btn-left">
                            <i class="bi bi-whatsapp"></i>
                            <span>Pengaturan WA</span>
                        </span>
                    </a>
                </div>

            @elseif($isPreviewSiswa)
                {{-- ================= NAVIGASI SISWA (Preview) ================= --}}
                <div class="nav-item-container mt-2">
                    <div class="px-2 mb-2 text-uppercase fw-bold text-muted" style="font-size: 0.68rem; letter-spacing: 0.08em;">
                        PORTAL SISWA
                    </div>
                </div>

                <!-- Dashboard Siswa -->
                <div class="nav-item-container">
                    <a href="{{ route('home') }}" class="nav-btn {{ request()->routeIs('home') ? 'active' : '' }}">
                        <span class="btn-left">
                            <i class="bi bi-grid-fill"></i>
                            <span>Dashboard</span>
                        </span>
                    </a>
                </div>

                <div class="nav-item-container">
                    <div class="px-2 mb-2 mt-3 text-muted" style="font-size: 0.72rem;">
                        <i class="bi bi-eye me-1"></i> Mode preview (Siswa). Gunakan tombol "Kembali ke Mode IT" untuk keluar.
                    </div>
                </div>

@else
                {{-- ================= NAVIGASI ADMIN / PETUGAS TU / SUPER ADMIN ================= --}}

                @php
                    // State aktif untuk accordion dropdown sidebar: hanya SATU dropdown
                    // yang boleh terbuka dalam satu waktu (Behavior Accordion).
                    // Grup "Data Akademik" (Siswa, Kelas, Jurusan, Tahun Ajaran).
                    // Khusus routeIs(*.*) agar predikat tiap menu UNIK dan tidak ada dua
                    // tombol aktif bersamaan (pola is('*kelas*',..) dapat menabrak menu lain).
                    $isDataAkademikActive = request()->routeIs('siswa.*')
                                         || request()->routeIs('kelas.*')
                                         || request()->routeIs('jurusan.*')
                                         || request()->routeIs('tahun-ajaran.*');

                    // Dropdown "Jadwal Pelajaran" hanya aktif pada submenu pelajaran.
                    // TIDAK memakai pola '*jadwal*' (terlalu luas & menabrak
                    // "Jadwal Piket Guru" di /kurikulum/jadwal-piket*).
                    // Plotting Jadwal Kelas berada di URL /admin/jadwal*.
                    $isJadwalAdminActive = request()->is('admin/jam-pelajaran*')
                                        || request()->is('admin/jadwal*')
                                        || request()->is('kurikulum/jam-pelajaran*')
                                        || request()->routeIs('admin.jam-pelajaran.*')
                                        || request()->routeIs('admin.jam-pulang.*')
                                        || request()->routeIs('admin.agenda-rutin.*')
                                        || request()->routeIs('admin.jadwal.*');

                    // Dropdown yang terbuka saat halaman pertama dimuat (accordion).
                    $defaultOpenMenu = $isDataAkademikActive
                        ? 'dataAkademik'
                        : ($isJadwalAdminActive ? 'jadwalPelajaran' : '');
                @endphp

                {{-- Wrapper accordion: seluruh dropdown berbagi state "openMenu" --}}
                <div x-data="{ openMenu: '{{ $defaultOpenMenu }}' }">
                    {{-- Penanda area kerja (Tata Usaha / Administrator) --}}
                    <div class="nav-item-container mt-2">
                        <div class="px-2 mb-2 text-uppercase fw-bold text-muted" style="font-size: 0.68rem; letter-spacing: 0.08em;">
                            {{ ($userSubRole === 'petugas_tu' || $userRole === 'admin_tu') ? 'TATA USAHA (TU)' : 'ADMINISTRATOR' }}
                        </div>
                    </div>

                    {{-- ================= SECTION UTAMA ================= --}}
                    <div class="nav-item-container mt-3">
                        <div class="px-2 mb-2 text-uppercase fw-bold text-muted" style="font-size: 0.68rem; letter-spacing: 0.08em;">
                            UTAMA
                        </div>
                    </div>

                    <!-- Dashboard -->
                    <div class="nav-item-container">
                        <a href="{{ route('home') }}" class="nav-btn {{ request()->routeIs('home') || request()->routeIs('dashboard') ? 'active' : '' }}" title="Beranda / dashboard utama">
                            <span class="btn-left">
                                <i class="bi bi-grid-fill"></i>
                                <span>Dashboard</span>
                            </span>
                        </a>
                    </div>

                    {{-- ================= SECTION KELOLA AKUN ================= --}}
                    @if($isPetugasTU || $isSuperAdmin)
                        <div class="nav-item-container mt-3">
                            <div class="px-2 mb-2 text-uppercase fw-bold text-muted" style="font-size: 0.68rem; letter-spacing: 0.08em;">
                                KELOLA AKUN
                            </div>
                        </div>

                        <!-- Akun Admin -->
                        <div class="nav-item-container">
                            <a href="{{ route('admin.users.index') }}" class="nav-btn {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" title="Manajemen akun, sub-role, & hak akses admin/staf TU">
                                <span class="btn-left">
                                    <i class="bi bi-person-gear"></i>
                                    <span>Akun Admin</span>
                                </span>
                            </a>
                        </div>

                        <!-- Pengajuan Reset Password -->
                        <div class="nav-item-container">
                            <a href="{{ route('admin.reset-requests.index') }}" class="nav-btn {{ request()->routeIs('admin.reset-requests.*') ? 'active' : '' }}" title="Pengajuan reset kredensial dari guru / karyawan">
                                <span class="btn-left">
                                    <i class="bi bi-key"></i>
                                    <span>Pengajuan Reset Password</span>
                                </span>
                                <span x-data="resetBadge({{ $pendingResetCount }})"
                                      x-show="count > 0"
                                      x-cloak
                                      class="badge bg-danger rounded-pill nav-reset-badge-live"
                                      style="font-size: 0.69rem; padding: 0.4em 0.65em;"
                                      title="Jumlah pengajuan menunggu verifikasi">
                                    <span x-text="count > 99 ? '99+' : count">{{ $pendingResetCount > 99 ? '99+' : $pendingResetCount }}</span>
                                </span>
                            </a>
                        </div>
                    @endif

                    {{-- ================= SECTION DATA MASTER (AKADEMIK) ================= --}}
                    <div class="nav-item-container mt-3">
                        <div class="px-2 mb-2 text-uppercase fw-bold text-muted" style="font-size: 0.68rem; letter-spacing: 0.08em;">
                            DATA MASTER (AKADEMIK)
                        </div>
                    </div>

                    <!-- Data Guru -->
                    <div class="nav-item-container">
                        <a href="{{ route('guru.index') }}" class="nav-btn {{ request()->routeIs('guru.*') || request()->routeIs('admin.guru.*') ? 'active' : '' }}" title="Master data profil guru, NIP, mapel, dsb.">
                            <span class="btn-left">
                                <i class="bi bi-person-vcard"></i>
                                <span>Data Guru</span>
                            </span>
                        </a>
                    </div>

                    <!-- Data Akademik (dropdown accordion) -->
                    <div class="nav-item-container">
                        <button class="nav-btn {{ $isDataAkademikActive ? 'active' : '' }}" 
                                type="button"
                                :aria-expanded="openMenu === 'dataAkademik'"
                                @click.prevent="openMenu = openMenu === 'dataAkademik' ? null : 'dataAkademik'">
                            <span class="btn-left">
                                <i class="bi bi-journal-bookmark-fill"></i>
                                <span>Data Akademik</span>
                            </span>
                            <i class="bi bi-chevron-down chevron-icon transition-transform duration-200" :class="openMenu === 'dataAkademik' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="openMenu === 'dataAkademik'" 
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 transform -translate-y-2"
                             x-transition:enter-end="opacity-100 transform translate-y-0"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 transform translate-y-0"
                             x-transition:leave-end="opacity-0 transform -translate-y-2"
                             id="dropdownDataAkademik">
                            <ul class="submenu-list">
                                <li>
                                    <a href="{{ route('siswa.index') }}" class="submenu-item-link {{ request()->routeIs('siswa.*') ? 'active' : '' }}">
                                        <i class="bi bi-people"></i>
                                        <span>Data Siswa</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('kelas.index') }}" class="submenu-item-link {{ request()->routeIs('kelas.*') ? 'active' : '' }}">
                                        <i class="bi bi-door-open"></i>
                                        <span>Data Kelas</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('jurusan.index') }}" class="submenu-item-link {{ request()->routeIs('jurusan.*') ? 'active' : '' }}">
                                        <i class="bi bi-diagram-3"></i>
                                        <span>Data Jurusan</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('tahun-ajaran.index') }}" class="submenu-item-link {{ request()->routeIs('tahun-ajaran.*') ? 'active' : '' }}">
                                        <i class="bi bi-calendar3"></i>
                                        <span>Tahun Ajaran</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Data Ruangan -->
                    <div class="nav-item-container">
                        <a href="{{ route('ruangan.index') }}" class="nav-btn {{ request()->routeIs('ruangan.*') ? 'active' : '' }}" title="Master data ruangan & kapasitas">
                            <span class="btn-left">
                                <i class="bi bi-building"></i>
                                <span>Data Ruangan</span>
                            </span>
                        </a>
                    </div>

                    <!-- Import Data -->
                    <div class="nav-item-container">
                        <a href="{{ route('import.index') }}" class="nav-btn {{ request()->routeIs('import.*') ? 'active' : '' }}" title="Impor data siswa & jadwal dari berkas Excel">
                            <span class="btn-left">
                                <i class="bi bi-file-earmark-arrow-up"></i>
                                <span>Import Data</span>
                            </span>
                        </a>
                    </div>

                    @if($isSuperAdmin)
                        <!-- Data Mata Pelajaran (khusus Super Admin) -->
                        <div class="nav-item-container">
                            <a href="{{ route('mapel.index') }}" class="nav-btn {{ request()->routeIs('mapel.*') ? 'active' : '' }}" title="Master data mata pelajaran">
                                <span class="btn-left">
                                    <i class="bi bi-book"></i>
                                    <span>Data Mata Pelajaran</span>
                                </span>
                            </a>
                        </div>
                    @endif

                    {{-- ================= SECTION JADWAL & PIKET ================= --}}
                    <div class="nav-item-container mt-3">
                        <div class="px-2 mb-2 text-uppercase fw-bold text-muted" style="font-size: 0.68rem; letter-spacing: 0.08em;">
                            JADWAL & PIKET
                        </div>
                    </div>

                    @if($isPetugasTU || $isSuperAdmin)
                        <!-- Jadwal Pelajaran (dropdown accordion) -->
                        <div class="nav-item-container">
                            <button class="nav-btn {{ $isJadwalAdminActive ? 'active' : '' }}"
                                    type="button"
                                    :aria-expanded="openMenu === 'jadwalPelajaran'"
                                    @click.prevent="openMenu = openMenu === 'jadwalPelajaran' ? null : 'jadwalPelajaran'">
                                <span class="btn-left">
                                    <i class="bi bi-calendar3"></i>
                                    <span>Jadwal Pelajaran</span>
                                </span>
                                <i class="bi bi-chevron-down chevron-icon transition-transform duration-200" :class="openMenu === 'jadwalPelajaran' ? 'rotate-180' : ''"></i>
                            </button>
                            <div x-show="openMenu === 'jadwalPelajaran'" 
                                 x-transition:enter="transition ease-out duration-200"
                                 x-transition:enter-start="opacity-0 transform -translate-y-2"
                                 x-transition:enter-end="opacity-100 transform translate-y-0"
                                 x-transition:leave="transition ease-in duration-150"
                                 x-transition:leave-start="opacity-100 transform translate-y-0"
                                 x-transition:leave-end="opacity-0 transform -translate-y-2"
                                 id="dropdownJadwalAdmin">
                                <ul class="submenu-list">
                                    <li><a href="{{ route('admin.jam-pelajaran.index') }}" class="submenu-item-link {{ request()->routeIs('admin.jam-pelajaran.*') ? 'active' : '' }}"><i class="bi bi-clock-history"></i><span>Master Jam Pelajaran</span></a></li>
                                    <li><a href="{{ route('admin.jadwal.index') }}" class="submenu-item-link {{ request()->routeIs('admin.jadwal.*') ? 'active' : '' }}"><i class="bi bi-calendar-range"></i><span>Plotting Jadwal Kelas</span></a></li>
                                </ul>
                            </div>
                        </div>
                    @endif

                    @if($isSuperAdmin || $isPetugasTU)
                        <!-- Jadwal Piket Guru -->
                        <div class="nav-item-container">
                            <a href="{{ route('kurikulum.jadwal-piket.index') }}" class="nav-btn {{ (request()->is('*jadwal-piket*') || request()->routeIs('kurikulum.jadwal-piket.*')) ? 'active' : '' }}" title="Jadwal piket harian guru">
                                <span class="btn-left">
                                    <i class="bi bi-shield-check"></i>
                                    <span>Jadwal Piket Guru</span>
                                </span>
                            </a>
                        </div>
                    @endif

                    {{-- Menu tambahan khusus Super Admin (tetap dipertahankan) --}}
                    @if($isSuperAdmin)
                        <div class="nav-item-container mt-3">
                            <div class="px-2 mb-2 text-uppercase fw-bold text-muted" style="font-size: 0.68rem; letter-spacing: 0.08em;">
                                KURIKULUM & LAPORAN
                            </div>
                        </div>
                        <div class="nav-item-container">
                            <a href="{{ route('laporan.index') }}" class="nav-btn {{ request()->routeIs('laporan.*', 'kurikulum.laporan.*') ? 'active' : '' }}">
                                <span class="btn-left">
                                    <i class="bi bi-file-earmark-text"></i>
                                    <span>Laporan KBM</span>
                                </span>
                            </a>
                        </div>
                        <div class="nav-item-container">
                            <a href="{{ route('waka-sdm.dashboard') }}" class="nav-btn {{ request()->routeIs('waka-sdm.*') ? 'active' : '' }}">
                                <span class="btn-left">
                                    <i class="bi bi-person-workspace"></i>
                                    <span>Portal Waka SDM</span>
                                </span>
                            </a>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <!-- Sidebar Bottom Footer -->
        <div class="sidebar-bottom">
            @php
                // Label "SISTEM" hanya tampil pada sidebar area admin/TU (sesuai struktur
                // menu yang dirombak); sidebar role lain tetap tanpa label agar tidak berubah.
                $isAdminSidebarNav = !($isKurikulumRole || $isWakaSdmRole || $isWakaKesiswaanRole || $isWakaPiketRole
                    || $isKepsekRole || $isSatpamRole || ($isGuruPiketRole && !$isGuruRole)
                    || $isGuruContext || $isPetugasItRole || $isPreviewSiswa);
            @endphp
            @if($isAdminSidebarNav)
                <div class="nav-item-container mb-2">
                    <div class="px-2 mb-2 text-uppercase fw-bold text-muted" style="font-size: 0.68rem; letter-spacing: 0.08em;">
                        SISTEM
                    </div>
                </div>
            @endif
            <div class="nav-item-container">
                <a href="{{ route('pengaturan.index') }}" class="nav-btn px-2 {{ request()->routeIs('pengaturan.*') ? 'active' : '' }}">
                    <span class="btn-left">
                        <i class="bi bi-gear"></i>
                        <span>Pengaturan</span>
                    </span>
                </a>
            </div>
            <div class="nav-item-container">
                <a href="{{ route('bantuan.index') }}" class="nav-btn px-2 {{ request()->routeIs('bantuan.*') ? 'active' : '' }}">
                    <span class="btn-left">
                        <i class="bi bi-question-circle"></i>
                        <span>Bantuan</span>
                    </span>
                </a>
            </div>
        </div>
    </aside>

    <!-- MAIN CONTENT AREA -->
    <div class="main-wrapper">
        <!-- TOPBAR HEADER -->
        <header class="topbar-header">
            <!-- Mobile Toggle -->
            {{-- iOS/Safari fix: button + cursor-pointer + relative z-50 + touch-action: manipulation.
                 - cursor-pointer  : dikenali iOS sebagai elemen interaktif.
                 - touch-action    : menghilangkan penundaan double-tap-zoom Safari.
                 - min 44px        : tap target ergonomis untuk jari (Apple HIG). --}}
            <button class="btn btn-sm btn-light border d-md-none cursor-pointer relative z-50"
                    type="button"
                    id="sidebarToggle"
                    aria-label="Buka menu navigasi"
                    aria-controls="appSidebar"
                    aria-expanded="false"
                    style="touch-action: manipulation; -webkit-tap-highlight-color: transparent; min-width: 44px; min-height: 44px;">
                <i class="bi bi-list fs-5"></i>
            </button>

            <!-- Actions Right -->
            <div class="topbar-actions">
                @if(auth()->user() && auth()->user()->isPetugasIt())
                    @php
                        $itPreviewRole = $previewRole ?? (auth()->user()->hasActiveRole() ? auth()->user()->activeRole() : null);
                        $itPreviewLabel = $itPreviewRole ? (\App\Models\User::PREVIEW_ROLES[$itPreviewRole] ?? ucfirst($itPreviewRole)) : null;
                        $itMaintenanceOn = \App\Models\PengaturanJadwal::isMaintenanceModeActive();
                    @endphp

                    @if($itMaintenanceOn)
                        <!-- Quick disable Maintenance Mode -->
                        <form action="{{ route('it.maintenance-mode') }}" method="POST" class="d-inline">
                            @csrf
                            <input type="hidden" name="maintenance_mode" value="0">
                            <button type="submit" class="btn btn-sm btn-danger rounded-3 d-flex align-items-center gap-2 me-2" title="Mode Maintenance AKTIF — klik untuk mematikan">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <span class="d-none d-lg-inline">Maintenance ON — Matikan</span>
                            </button>
                        </form>
                    @endif

                    @if($itPreviewRole)
                        <!-- Kembali ke Mode IT -->
                        <form action="{{ route('it.reset-view') }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-warning rounded-3 d-flex align-items-center gap-2 me-2">
                                <i class="bi bi-arrow-counterclockwise"></i>
                                <span class="d-none d-sm-inline">Kembali ke Mode IT</span>
                            </button>
                        </form>
                    @endif

                    <!-- Switch View As -->
                    <div class="dropdown me-2">
                        <button class="btn btn-sm btn-dark rounded-3 d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-arrows-fullscreen"></i>
                            <span class="d-none d-md-inline">
                                {{ $itPreviewRole ? 'View: ' . $itPreviewLabel : 'Switch View As' }}
                            </span>
                            <i class="bi bi-chevron-down"></i>
                        </button>

                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-4 mt-2" style="max-height: 420px; overflow-y: auto;">
                            <li class="px-3 py-2">
                                <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.68rem; letter-spacing: 0.06em;">Pilih Role Portal</span>
                            </li>
                            @foreach(\App\Models\User::PREVIEW_ROLES as $previewKey => $previewName)
                                <li>
                                    <form action="{{ route('it.switch-view') }}" method="POST" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="role" value="{{ $previewKey }}">
                                        <button type="submit" class="dropdown-item py-2 {{ $itPreviewRole === $previewKey ? 'active' : '' }}">
                                            <i class="bi bi-person-circle me-2 text-muted"></i>
                                            {{ $previewName }}
                                        </button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <!-- Select Impersonate Target Context (Mode QA IT - Portal Guru / Wali Kelas) -->
                    @if(! empty($itPreviewRole) && in_array($itPreviewRole, ['guru_mapel', 'guru_piket', 'wali_kelas'], true))
                        @php
                            $itIsWaliKelas = $itPreviewRole === 'wali_kelas';
                            $itTargetId = session('impersonate_target_id');
                            $itTargetGuru = $itTargetId ? \App\Models\User::find($itTargetId) : null;
                            $itTargetList = \App\Models\User::where('role', \App\Models\User::ROLE_GURU)
                                ->where('is_active', true)
                                ->when($itIsWaliKelas, fn ($q) => $q->where(function ($q2) {
                                    $q2->where('sub_role', 'wali_kelas')
                                        ->orWhereHas('kelasWali');
                                }))
                                ->orderBy('nama')
                                ->get();
                        @endphp
                        <div class="dropdown me-2">
                            <button class="btn btn-sm btn-outline-secondary rounded-3 d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-badge"></i>
                                <span class="d-none d-md-inline">
                                    @if($itTargetGuru)
                                        {{ $itIsWaliKelas ? 'Wali Kelas: ' : 'Guru: ' }}{{ $itTargetGuru->nama }}
                                    @else
                                        {{ $itIsWaliKelas ? 'Pilih Wali Kelas Target' : 'Pilih Context Target Guru' }}
                                    @endif
                                </span>
                                <i class="bi bi-chevron-down"></i>
                            </button>

                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-4 mt-2" style="max-height: 420px; overflow-y: auto;">
                                <li class="px-3 py-2">
                                    <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.68rem; letter-spacing: 0.06em;">Pilih {{ $itIsWaliKelas ? 'Wali Kelas' : 'Guru' }} Target</span>
                                </li>
                                <li>
                                    <form action="{{ route('it.impersonate-target') }}" method="POST" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="impersonate_target_id" value="">
                                        <button type="submit" class="dropdown-item py-2 {{ $itTargetId ? '' : 'active' }}">
                                            <i class="bi bi-person me-2 text-muted"></i>
                                            Akun Saya (Tanpa Target)
                                        </button>
                                    </form>
                                </li>
                                @forelse($itTargetList as $guruOpt)
                                    <li>
                                        <form action="{{ route('it.impersonate-target') }}" method="POST" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="impersonate_target_id" value="{{ $guruOpt->id }}">
                                            <button type="submit" class="dropdown-item py-2 {{ (int) $itTargetId === (int) $guruOpt->id ? 'active' : '' }}">
                                                <i class="bi bi-person-check me-2 text-muted"></i>
                                                {{ $guruOpt->nama }}
                                                <span class="small text-muted">({{ $guruOpt->username }})</span>
                                            </button>
                                        </form>
                                    </li>
                                @empty
                                    <li class="px-3 py-2 text-muted small">Belum ada akun Guru testing.</li>
                                @endforelse
                            </ul>
                        </div>
                    @endif
                @endif

                <!-- Notifications -->
                @php
                    $navUnreadNotifs = auth()->user()?->unreadNotifications()->latest()->limit(5)->get() ?? collect();
                    $navUnreadCount  = $navUnreadNotifs->count();
                @endphp
                <div class="dropdown">
                    <button class="notif-bell-btn position-relative" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" style="position:relative;">
                        <i class="bi bi-bell fs-5"></i>
                        @if($navUnreadCount > 0)
                            <span class="notif-badge">{{ $navUnreadCount > 99 ? '99+' : $navUnreadCount }}</span>
                        @endif
                    </button>

                    <div class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-4 mt-2 notif-menu" style="width: 360px; max-height: 480px; overflow: hidden;">
                        <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
                            <span class="fw-bold text-dark">Notifikasi</span>
                            <div class="d-flex align-items-center gap-2">
                                <span class="small text-muted">{{ $navUnreadCount }} belum dibaca</span>
                                @if($navUnreadCount > 0)
                                    <form action="{{ route('notifications.read-all') }}" method="POST" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-link text-primary p-0 text-decoration-none" type="submit">
                                            <i class="bi bi-check2-all me-1"></i> Tandai semua dibaca
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                        <div style="max-height: 380px; overflow-y: auto;">
                            @forelse($navUnreadNotifs as $n)
                                @php
                                    $nData = $n->data;
                                    $nUrl  = data_get($nData, 'url', '#');
                                @endphp
                                <div class="dropdown-item px-3 py-2 bg-primary-subtle" style="white-space: normal; border-bottom: 1px solid #eef1f6;">
                                    <a href="{{ $nUrl }}" class="text-decoration-none text-reset d-block"
                                       data-notif-read="{{ route('notifications.read', $n->id) }}">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <strong class="small">{{ data_get($nData, 'title', 'Notifikasi') }}</strong>
                                            <small class="text-muted ms-2 text-nowrap">{{ $n->created_at?->diffForHumans() }}</small>
                                        </div>
                                        <div class="text-muted small mt-1">{{ data_get($nData, 'message', '') }}</div>
                                    </a>
                                </div>
                            @empty
                                <div class="text-center text-muted py-5">
                                    <i class="bi bi-bell-slash fs-2 d-block mb-2"></i>
                                    Tidak ada notifikasi baru.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- Divider -->
                <div class="topbar-divider"></div>

                <!-- Profile Dropdown -->
                <div class="dropdown">
                    <div class="user-dropdown-btn" data-bs-toggle="dropdown" aria-expanded="false">
                        @php
                            $navUser          = auth()->user();
                            $defaultNavAvatar = 'https://ui-avatars.com/api/?name=' . urlencode($navUser?->nama ?? 'User') . '&background=1677ff&color=fff&size=128&bold=true';
                            $navAvatar        = ($navUser && $navUser->foto_profil && \Illuminate\Support\Facades\Storage::disk('public')->exists($navUser->foto_profil))
                                ? asset('storage/' . $navUser->foto_profil)
                                : $defaultNavAvatar;
                        @endphp
                        <img src="{{ $navAvatar }}" alt="Avatar" class="user-avatar"
                             onerror="this.onerror=null;this.src='{{ $defaultNavAvatar }}';">
                        <div class="user-meta">
                            <div class="user-name">{{ $navUser?->nama ?? 'Admin Utama' }}</div>
                            <div class="user-role">{{ $navUser?->role_label ?? 'Administrator' }}</div>
                        </div>
                        <i class="bi bi-chevron-down user-chevron"></i>
                    </div>

                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-4 mt-2">
                        <li><a class="dropdown-item py-2" href="{{ route('profil.index') }}"><i class="bi bi-person me-2 text-primary"></i> Profil & Akun</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form action="{{ route('logout') }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="dropdown-item py-2 text-danger">
                                    <i class="bi bi-box-arrow-right me-2"></i> Keluar / Logout
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Bootstrap 5.3 JS Bundle: dimuat SEBELUM konten, karena beragam halaman
             memakai bootstrap.Modal/Toast di dalam script push-an halaman yang kini
             dirender di dalam <main> (ikut terbawa navigasi SPA). -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

        <!-- PAGE CONTENT BODY
             Padding responsive pakai utility Tailwind (px-3 sm:px-6 py-4):
             mobile = padding samping tipis (0.75rem), >= sm = 1.5rem, vertical 1rem.
             #page-content = target swap SPA (Unpoly): sidebar & header tidak ikut
             di-reload. Style & script per-halaman sengaja dirender DI DALAM <main>
             supaya aset per-halaman ikut terbawa & dieksekusi saat fragmen di-swap. -->
        <main id="page-content" class="page-content px-3 sm:px-6 py-4">
            @yield('content')
            @stack('styles')
            @stack('scripts')
        </main>
    </div>

</div>

<script>
    // Klik item notifikasi lonceng: tandai terbaca (async) lalu lanjut ke URL
    // tujuan (panel pengajuan reset). Data-notif-read memuat endpoint read-nya.
    document.addEventListener('click', function (e) {
        const el = e.target.closest('[data-notif-read]');
        if (!el) return;
        e.preventDefault();
        const readUrl = el.getAttribute('data-notif-read');
        if (readUrl) {
            fetch(readUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                keepalive: true, // tetap terkirim walau halaman berpindah
            }).catch(function () { /* navigasi tetap dilanjutkan */ });
        }
        window.location.href = el.getAttribute('href');
    });
</script>

@if($isPetugasTU || $isSuperAdmin)
    <script>
        // Badge live "Pengajuan Reset": jumlah pending diperbarui otomatis via
        // polling ringan (30 dtk, dijeda saat tab tidak aktif) — tanpa perlu
        // hard-refresh ketika Admin TU mengeklik Setujui/Tolak atau ada
        // pengajuan baru dari tab lain.
        function resetBadge(initialCount) {
            return {
                count: initialCount,
                timer: null,
                async poll() {
                    try {
                        const res = await fetch('{{ route('admin.reset-requests.count') }}', {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            },
                            cache: 'no-store',
                        });
                        if (!res.ok) return;
                        const data = await res.json();
                        if (typeof data.count === 'number' && data.count >= 0) {
                            this.count = data.count;
                        }
                    } catch (e) {
                        // Abaikan — polling berikutnya akan mencoba lagi.
                    }
                },
                init() {
                    this.timer = setInterval(() => {
                        if (document.hidden) return; // hemat resource saat tab tak aktif
                        this.poll();
                    }, 30000);
                },
                destroy() {
                    if (this.timer) {
                        clearInterval(this.timer);
                        this.timer = null;
                    }
                },
            };
        }
    </script>
@endif

</body>
</html>
