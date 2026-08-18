<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'WebJournal Management System')</title>

    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
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
            z-index: 1040;
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
            transition: transform 0.25s ease;
        }

        .nav-btn[aria-expanded="true"] .chevron-icon {
            transform: rotate(180deg);
        }

        /* Submenu Styling */
        .submenu-list {
            padding-left: 2.25rem;
            margin-top: 0.25rem;
            margin-bottom: 0.5rem;
            list-style: none;
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
            z-index: 1030;
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

        .notif-dot {
            position: absolute;
            top: 4px;
            right: 4px;
            width: 8px;
            height: 8px;
            background-color: #ef4444;
            border-radius: 50%;
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

        /* Page Content Area */
        .page-content {
            padding: 2rem 2.25rem;
            flex: 1;
        }

        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-wrapper {
                margin-left: 0;
            }
            .topbar-header {
                justify-content: space-between;
                padding: 0 1.25rem;
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
    @stack('styles')
</head>
<body>

<div class="app-wrapper">

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
                $userRole = auth()->check() ? auth()->user()->role : null;

                // Role Kurikulum — role DB: 'admin_kurikulum', aliases: 'waka_kurikulum', 'kurikulum'
                $isKurikulumRole = in_array($userRole, ['admin_kurikulum', 'waka_kurikulum', 'kurikulum']);

                // Wali Kelas: hanya jika role PERSIS 'wali_kelas'
                $isWaliKelasRole = ($userRole === 'wali_kelas');

                // Guru context: role guru_mapel, guru umum, atau wali_kelas
                $isGuruRole = in_array($userRole, ['guru_mapel', 'guru', 'wali_kelas']);

                // Aktifkan Guru Piket
                $isGuruPiketRole = ($userRole === 'guru_piket');

                // Aktifkan blok navigasi Guru — HANYA dari role, bukan dari URL
                $isGuruContext = $isGuruRole || (request()->is('guru*') && !$isGuruPiketRole);
            @endphp

            @if($isKurikulumRole)
                {{-- ================= NAVIGASI WAKA KURIKULUM ================= --}}
                <x-sidebar-kurikulum />
            @elseif($isGuruPiketRole || request()->is('piket*'))
                <!-- ================= NAVIGASI GURU PIKET ================= -->
                <!-- Dashboard Piket -->
                <div class="nav-item-container">
                    <a href="{{ route('piket.dashboard') }}" class="nav-btn {{ request()->routeIs('piket.dashboard') ? 'active' : '' }}">
                        <span class="btn-left">
                            <i class="bi bi-speedometer2"></i>
                            <span>Dashboard</span>
                        </span>
                    </a>
                </div>

                <!-- Presensi Guru -->
                <div class="nav-item-container">
                    <a href="{{ route('piket.presensi-guru') }}" class="nav-btn {{ request()->routeIs('piket.presensi-guru') ? 'active' : '' }}">
                        <span class="btn-left">
                            <i class="bi bi-person-check-fill"></i>
                            <span>Presensi Guru</span>
                        </span>
                    </a>
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

            @elseif($isGuruContext)

                <!-- ================= NAVIGASI GURU (GURU MAPEL & WALI KELAS) ================= -->
                <!-- Dashboard Guru -->
                <div class="nav-item-container">
                    <a href="{{ route('guru.dashboard') }}" class="nav-btn {{ request()->routeIs('guru.dashboard') ? 'active' : '' }}">
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

                <!-- SECTION KELAS SAYA: HANYA untuk role 'wali_kelas' secara KETAT -->
                @if($isWaliKelasRole)
                    @php
                        $isKelasSayaActive = request()->routeIs('walikelas.*');
                    @endphp
                    <div class="nav-item-container mt-3">
                        <div class="px-2 mb-2 text-uppercase fw-bold text-muted" style="font-size: 0.68rem; letter-spacing: 0.08em;">
                            KELAS SAYA
                        </div>
                        <button class="nav-btn {{ $isKelasSayaActive ? 'active' : '' }}" 
                                type="button"
                                data-bs-toggle="collapse" 
                                data-bs-target="#dropdownKelasSaya" 
                                aria-expanded="{{ $isKelasSayaActive ? 'true' : 'false' }}" 
                                aria-controls="dropdownKelasSaya">
                            <span class="btn-left">
                                <i class="bi bi-mortarboard-fill"></i>
                                <span>Kelas Saya</span>
                            </span>
                            <i class="bi bi-chevron-down chevron-icon"></i>
                        </button>
                        
                        <div class="collapse {{ $isKelasSayaActive ? 'show' : '' }}" id="dropdownKelasSaya">
                            <ul class="submenu-list">
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
            @else
                <!-- ================= NAVIGASI ADMIN ================= -->
                <!-- Dashboard Link -->
                <div class="nav-item-container">
                    <a href="{{ route('home') }}" class="nav-btn {{ request()->routeIs('home') || request()->routeIs('jurnal.index') ? 'active' : '' }}">
                        <span class="btn-left">
                            <i class="bi bi-grid-fill"></i>
                            <span>Dashboard</span>
                        </span>
                    </a>
                </div>

                <!-- Dropdown Data Master -->
                @php
                    $isDataMasterActive = request()->routeIs('guru.index') || request()->routeIs('siswa.*') || request()->routeIs('kelas.*') || request()->routeIs('jurusan.*') || request()->routeIs('mapel.*');
                @endphp
                <div class="nav-item-container">
                    <button class="nav-btn {{ $isDataMasterActive ? 'active' : '' }}" 
                            type="button"
                            data-bs-toggle="collapse" 
                            data-bs-target="#dropdownDataMaster" 
                            aria-expanded="{{ $isDataMasterActive ? 'true' : 'false' }}" 
                            aria-controls="dropdownDataMaster">
                        <span class="btn-left">
                            <i class="bi bi-database-fill"></i>
                            <span>Data Master</span>
                        </span>
                        <i class="bi bi-chevron-down chevron-icon"></i>
                    </button>
                    
                    <div class="collapse {{ $isDataMasterActive ? 'show' : '' }}" id="dropdownDataMaster">
                        <ul class="submenu-list">
                            <li>
                                <a href="{{ route('guru.index') }}" class="submenu-item-link {{ request()->routeIs('guru.index') ? 'active' : '' }}">
                                    <i class="bi bi-person-badge"></i>
                                    <span>Data Guru</span>
                                </a>
                            </li>
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
                                <a href="{{ route('mapel.index') }}" class="submenu-item-link {{ request()->routeIs('mapel.*') ? 'active' : '' }}">
                                    <i class="bi bi-book"></i>
                                    <span>Data Mata Pelajaran</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                @php
                    $isPetugasTU = in_array(auth()->user()->role ?? 'admin_tu', ['admin_tu', 'admin']);
                @endphp

                @if(!$isPetugasTU)
                    <!-- Standalone Menu: Jadwal Pelajaran (Bukan Ranah TU) -->
                    <div class="nav-item-container">
                        <a href="{{ route('jadwal.index') }}" class="nav-btn {{ request()->routeIs('jadwal.*') ? 'active' : '' }}">
                            <span class="btn-left">
                                <i class="bi bi-calendar3"></i>
                                <span>Jadwal Pelajaran</span>
                            </span>
                        </a>
                    </div>

                    <!-- Standalone Menu: Laporan (Bukan Ranah TU) -->
                    <div class="nav-item-container">
                        <a href="{{ route('laporan.index') }}" class="nav-btn {{ request()->routeIs('laporan.*') ? 'active' : '' }}">
                            <span class="btn-left">
                                <i class="bi bi-file-earmark-text"></i>
                                <span>Laporan</span>
                            </span>
                        </a>
                    </div>
                @endif
            @endif
        </div>

        <!-- Sidebar Bottom Footer -->
        <div class="sidebar-bottom">
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
            <button class="btn btn-sm btn-light border d-lg-none" type="button" id="sidebarToggle">
                <i class="bi bi-list fs-5"></i>
            </button>

            <!-- Actions Right -->
            <div class="topbar-actions">
                <!-- Notification Bell -->
                <button type="button" class="notif-bell-btn" title="Notifikasi">
                    <i class="bi bi-bell"></i>
                    <span class="notif-dot"></span>
                </button>

                <!-- Divider -->
                <div class="topbar-divider"></div>

                <!-- Profile Dropdown -->
                <div class="dropdown">
                    <div class="user-dropdown-btn" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=100&auto=format&fit=crop&q=80" alt="Avatar" class="user-avatar">
                        <div class="user-meta">
                            <div class="user-name">{{ auth()->user()->nama ?? 'Admin Utama' }}</div>
                            <div class="user-role">{{ ucfirst(str_replace('_', ' ', auth()->user()->role ?? 'Administrator')) }}</div>
                        </div>
                        <i class="bi bi-chevron-down user-chevron"></i>
                    </div>

                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-4 mt-2">
                        <li><a class="dropdown-item py-2" href="#"><i class="bi bi-person me-2 text-primary"></i> Profil Saya</a></li>
                        <li><a class="dropdown-item py-2" href="{{ route('pengaturan.index') }}"><i class="bi bi-sliders me-2 text-warning"></i> Pengaturan Akun</a></li>
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

        <!-- PAGE CONTENT BODY -->
        <main class="page-content">
            @yield('content')
        </main>
    </div>

</div>

<!-- Bootstrap 5.3 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const sidebarToggle = document.getElementById('sidebarToggle');
        const appSidebar = document.getElementById('appSidebar');

        if (sidebarToggle && appSidebar) {
            sidebarToggle.addEventListener('click', function () {
                appSidebar.classList.toggle('show');
            });
        }
    });
</script>
@stack('scripts')
</body>
</html>
