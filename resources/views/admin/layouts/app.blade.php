<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'WebJournal Management System - Admin')</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        :root {
            --sidebar-width: 270px;
            --sidebar-active-bg: #1565c0;
            --sidebar-active-color: #ffffff;
            --main-bg: #e2ebf4;
            --card-radius: 16px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--main-bg);
            color: #1e293b;
            min-height: 100vh;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        .app-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styling */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #cce0f2 0%, #b8cde2 50%, #a4bccc 100%);
            border-right: 1px solid rgba(180, 200, 220, 0.6);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 1040;
            transition: transform 0.3s ease;
        }

        .sidebar-header {
            padding: 1.75rem 1.25rem 1.5rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.85rem;
        }

        .brand-icon {
            width: 44px;
            height: 44px;
            background: #1565c0;
            color: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            box-shadow: 0 4px 12px rgba(21, 101, 192, 0.3);
            flex-shrink: 0;
        }

        .brand-text {
            font-weight: 800;
            font-size: 1.2rem;
            color: #0f172a;
            line-height: 1.15;
            letter-spacing: -0.02em;
        }

        .brand-subtitle {
            font-size: 0.65rem;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            display: block;
            margin-top: 2px;
        }

        .sidebar-menu {
            padding: 0.5rem 1.25rem;
            flex: 1;
            overflow-y: auto;
        }

        .nav-item {
            margin-bottom: 0.4rem;
        }

        .nav-link-custom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 1.1rem;
            color: #334155;
            font-weight: 600;
            font-size: 0.95rem;
            border-radius: 14px;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .nav-link-custom:hover {
            background-color: rgba(255, 255, 255, 0.5);
            color: #0f172a;
        }

        .nav-link-custom.active {
            background-color: var(--sidebar-active-bg);
            color: var(--sidebar-active-color);
            box-shadow: 0 4px 14px rgba(21, 101, 192, 0.3);
        }

        .nav-link-custom .link-content {
            display: flex;
            align-items: center;
            gap: 0.85rem;
        }

        .nav-link-custom i {
            font-size: 1.2rem;
        }

        .dropdown-chevron {
            font-size: 0.8rem;
            transition: transform 0.25s ease;
        }

        .nav-link-custom[aria-expanded="true"] .dropdown-chevron {
            transform: rotate(180deg);
        }

        /* Submenu Styling */
        .submenu {
            padding-left: 2.4rem;
            margin-top: 0.3rem;
            margin-bottom: 0.6rem;
            list-style: none;
        }

        .submenu .submenu-link {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            padding: 0.55rem 0.85rem;
            color: #475569;
            font-size: 0.875rem;
            font-weight: 600;
            border-radius: 10px;
            text-decoration: none;
            transition: all 0.2s ease;
            position: relative;
        }

        .submenu .submenu-link:hover {
            background-color: rgba(255, 255, 255, 0.7);
            color: #1565c0;
        }

        .submenu .submenu-link.active {
            color: #1565c0;
            font-weight: 700;
            background-color: rgba(255, 255, 255, 0.9);
        }

        .submenu .submenu-link.active::before {
            content: '';
            position: absolute;
            left: -12px;
            top: 50%;
            transform: translateY(-50%);
            width: 5px;
            height: 18px;
            background-color: #1565c0;
            border-radius: 4px;
        }

        .sidebar-footer {
            padding: 1.25rem 1.25rem 1.5rem;
            border-top: 1px solid rgba(160, 185, 205, 0.5);
        }

        /* Main Workspace Styling */
        .main-content {
            margin-left: var(--sidebar-width);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            background-color: var(--main-bg);
            min-height: 100vh;
        }

        /* Top Header Navbar */
        .top-navbar {
            height: 70px;
            background: transparent;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2.5rem;
            position: sticky;
            top: 0;
            z-index: 1030;
        }

        .topbar-search {
            position: relative;
            width: 360px;
        }

        .topbar-search input {
            background-color: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(200, 215, 230, 0.8);
            border-radius: 12px;
            padding: 0.5rem 1rem 0.5rem 2.6rem;
            font-size: 0.875rem;
            color: #1e293b;
        }

        .topbar-search input:focus {
            background-color: #ffffff;
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
        }

        .topbar-search i {
            position: absolute;
            left: 0.95rem;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            font-size: 0.9rem;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }

        .notification-btn {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.6);
            border: 1px solid rgba(200, 215, 230, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #475569;
            position: relative;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .notification-btn:hover {
            background: #ffffff;
            color: #0f172a;
        }

        .notification-badge {
            position: absolute;
            top: 7px;
            right: 7px;
            width: 8px;
            height: 8px;
            background-color: #ef4444;
            border-radius: 50%;
            border: 1.5px solid #ffffff;
        }

        .profile-card {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.35rem 0.5rem;
            border-radius: 12px;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .profile-card:hover {
            background-color: rgba(255, 255, 255, 0.6);
        }

        .avatar-img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #ffffff;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }

        .profile-info {
            line-height: 1.25;
        }

        .profile-name {
            font-size: 0.9rem;
            font-weight: 800;
            color: #000000;
        }

        .profile-role {
            font-size: 0.78rem;
            color: #475569;
            font-weight: 500;
        }

        /* Page Content Body */
        .page-body {
            padding: 0.5rem 2.5rem 2.5rem;
            flex: 1;
        }

        /* Stat Card Modern Design */
        .stat-card-custom {
            background: #ffffff;
            border: 1px solid rgba(226, 232, 240, 0.9);
            border-radius: var(--card-radius);
            padding: 1.6rem 1.75rem;
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.03);
            height: 100%;
        }

        .stat-card-title {
            font-weight: 800;
            color: #000000;
            font-size: 1.05rem;
            margin-bottom: 1.25rem;
        }

        .stat-number-large {
            font-size: 2.75rem;
            font-weight: 900;
            line-height: 1;
            margin-bottom: 0.6rem;
            letter-spacing: -0.03em;
        }

        .stat-card-label {
            font-weight: 800;
            color: #000000;
            font-size: 0.95rem;
            margin-bottom: 0.2rem;
        }

        .stat-card-subtext {
            color: #64748b;
            font-size: 0.825rem;
            font-weight: 500;
            margin-bottom: 0;
        }

        /* Table Card Styling */
        .table-card-custom {
            background: #ffffff;
            border: 1px solid rgba(226, 232, 240, 0.9);
            border-radius: var(--card-radius);
            box-shadow: 0 4px 24px rgba(15, 23, 42, 0.04);
            padding: 1.75rem 2rem;
        }

        .table-custom {
            margin-bottom: 0;
        }

        .table-custom th {
            font-size: 0.725rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #475569;
            padding: 1rem 0.75rem;
            border-bottom: 1px solid #f1f5f9;
        }

        .table-custom td {
            padding: 1.1rem 0.75rem;
            font-size: 0.9rem;
            vertical-align: middle;
            color: #1e293b;
            border-bottom: 1px solid #f8fafc;
        }

        .status-badge-terisi {
            background-color: #e6f4ea;
            color: #1e7e34;
            border: 1px solid #ceebd5;
            border-radius: 50px;
            padding: 0.35rem 0.85rem;
            font-size: 0.8rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }

        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
    @stack('styles')
</head>
<body>

<div class="app-wrapper">
    <!-- Sidebar Navigation -->
    <aside class="sidebar" id="appSidebar">
        <!-- Sidebar Brand -->
        <div class="sidebar-header">
            <div class="brand-icon">
                <i class="bi bi-journal-bookmark-fill"></i>
            </div>
            <div>
                <span class="brand-text">WebJournal</span>
                <span class="brand-subtitle">MANAGEMENT SYSTEM</span>
            </div>
        </div>

        <!-- Sidebar Navigation Menu -->
        <div class="sidebar-menu">
            <!-- Dashboard Link -->
            <div class="nav-item">
                <a href="{{ route('home') }}" class="nav-link-custom {{ request()->routeIs('home') || request()->routeIs('jurnal.index') ? 'active' : '' }}">
                    <span class="link-content">
                        <i class="bi bi-grid-fill"></i>
                        <span>Dashboard</span>
                    </span>
                </a>
            </div>

            <!-- Dropdown Data Master -->
            @php
                $isDataMasterActive = request()->routeIs('guru.*') || request()->routeIs('siswa.*') || request()->routeIs('kelas.*') || request()->routeIs('mapel.*');
            @endphp
            <div class="nav-item">
                <a class="nav-link-custom {{ $isDataMasterActive ? 'active' : '' }}" 
                   data-bs-toggle="collapse" 
                   href="#dropdownDataMaster" 
                   role="button" 
                   aria-expanded="{{ $isDataMasterActive ? 'true' : 'false' }}" 
                   aria-controls="dropdownDataMaster">
                    <span class="link-content">
                        <i class="bi bi-database"></i>
                        <span>Data Master</span>
                    </span>
                    <i class="bi bi-chevron-down dropdown-chevron"></i>
                </a>
                
                <div class="collapse {{ $isDataMasterActive ? 'show' : '' }}" id="dropdownDataMaster">
                    <ul class="submenu">
                        <li>
                            <a href="{{ route('guru.index') }}" class="submenu-link {{ request()->routeIs('guru.*') ? 'active' : '' }}">
                                <i class="bi bi-person-badge"></i>
                                <span>Data Guru</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('siswa.index') }}" class="submenu-link {{ request()->routeIs('siswa.*') ? 'active' : '' }}">
                                <i class="bi bi-people"></i>
                                <span>Data Siswa</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('kelas.index') }}" class="submenu-link {{ request()->routeIs('kelas.*') ? 'active' : '' }}">
                                <i class="bi bi-door-open"></i>
                                <span>Data Kelas</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('mapel.index') }}" class="submenu-link {{ request()->routeIs('mapel.*') ? 'active' : '' }}">
                                <i class="bi bi-book"></i>
                                <span>Data Mata Pelajaran</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Jadwal Pelajaran -->
            <div class="nav-item">
                <a href="{{ route('jurnal.create') }}" class="nav-link-custom {{ request()->routeIs('jurnal.create') ? 'active' : '' }}">
                    <span class="link-content">
                        <i class="bi bi-calendar3"></i>
                        <span>Jadwal Pelajaran</span>
                    </span>
                </a>
            </div>

            <!-- Laporan -->
            <div class="nav-item">
                <a href="#" class="nav-link-custom">
                    <span class="link-content">
                        <i class="bi bi-file-earmark-text"></i>
                        <span>Laporan</span>
                    </span>
                </a>
            </div>
        </div>

        <!-- Sidebar Bottom Footer -->
        <div class="sidebar-footer">
            <div class="nav-item">
                <a href="#" class="nav-link-custom px-2">
                    <span class="link-content">
                        <i class="bi bi-gear"></i>
                        <span>Pengaturan</span>
                    </span>
                </a>
            </div>
            <div class="nav-item">
                <a href="#" class="nav-link-custom px-2">
                    <span class="link-content">
                        <i class="bi bi-question-circle"></i>
                        <span>Bantuan</span>
                    </span>
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content Container -->
    <div class="main-content">
        <!-- Top Navbar -->
        <header class="top-navbar">
            <div class="d-flex align-items-center gap-3 d-lg-none me-auto">
                <button class="btn btn-light" type="button" id="sidebarToggle">
                    <i class="bi bi-list fs-5"></i>
                </button>
            </div>

            <!-- Topbar Global Search Input -->
            <div class="topbar-search d-none d-md-block me-auto">
                <i class="bi bi-search"></i>
                <input type="text" class="form-control" placeholder="Cari jurnal, guru, atau kelas...">
            </div>

            <div class="user-profile">
                <div class="notification-btn" title="Notifikasi">
                    <i class="bi bi-bell"></i>
                    <span class="notification-badge"></span>
                </div>

                <div class="dropdown">
                    <div class="profile-card" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=100&auto=format&fit=crop&q=80" alt="Admin Avatar" class="avatar-img">
                        <div class="profile-info text-start me-1">
                            <div class="profile-name">Admin Utama</div>
                            <div class="profile-role">Administrator</div>
                        </div>
                        <i class="bi bi-chevron-down text-muted small ms-1"></i>
                    </div>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-4 mt-2">
                        <li><a class="dropdown-item py-2" href="#"><i class="bi bi-person me-2 text-primary"></i> Profil Saya</a></li>
                        <li><a class="dropdown-item py-2" href="#"><i class="bi bi-sliders me-2 text-warning"></i> Pengaturan Akun</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item py-2 text-danger" href="#"><i class="bi bi-box-arrow-right me-2"></i> Keluar / Logout</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Main Page Content Body -->
        <main class="page-body">
            @yield('content')
        </main>
    </div>
</div>

<!-- Bootstrap 5 Bundle JS -->
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
