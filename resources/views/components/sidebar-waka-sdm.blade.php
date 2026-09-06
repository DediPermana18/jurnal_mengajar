@props(['pendingIzinCount' => 0])

@php
    $isDashboardActive = request()->routeIs('waka-sdm.dashboard*');
    $isDataGuruActive  = request()->routeIs('guru.*') || request()->routeIs('admin.guru.*');
    $isRekapIzinActive = request()->routeIs('waka-sdm.rekap-izin*');
    $isPresensiActive  = request()->routeIs('waka-sdm.rekap-presensi-guru*') || request()->routeIs('waka-sdm.export-excel') || request()->routeIs('waka-sdm.print-presensi');
    $isLaporanActive   = request()->routeIs('kurikulum.laporan.*') || request()->routeIs('laporan.*');
@endphp

<!-- ================= NAVIGASI WAKA SDM ================= -->
<div class="nav-item-container mt-2">
    <div class="px-2 mb-2 text-uppercase fw-bold text-muted" style="font-size: 0.68rem; letter-spacing: 0.08em;">
        WAKA SDM / KEPEGAWAIAN
    </div>
</div>

<!-- 1. Dashboard Waka SDM -->
<div class="nav-item-container">
    <a href="{{ route('waka-sdm.dashboard') }}"
       class="nav-btn {{ $isDashboardActive ? 'active' : '' }}">
        <span class="btn-left">
            <i class="bi bi-layout-sidebar-inset"></i>
            <span>Dashboard SDM</span>
        </span>
    </a>
</div>

<!-- 2. Data Guru / Pengguna -->
<div class="nav-item-container">
    <a href="{{ route('guru.index') }}"
       class="nav-btn {{ $isDataGuruActive ? 'active' : '' }}">
        <span class="btn-left">
            <i class="bi bi-people-fill"></i>
            <span>Data Guru</span>
        </span>
    </a>
</div>

<!-- 3. Rekap Izin & Cuti Guru -->
<div class="nav-item-container">
    <a href="{{ route('waka-sdm.rekap-izin') }}"
       class="nav-btn d-flex align-items-center justify-content-between {{ $isRekapIzinActive ? 'active' : '' }}">
        <span class="btn-left">
            <i class="bi bi-calendar2-check-fill"></i>
            <span>Rekap Izin & Cuti</span>
        </span>
        @if(($pendingIzinCount ?? 0) > 0)
            <span class="badge bg-warning-subtle text-warning-emphasis border rounded-pill px-2 py-0.5" style="font-size: 0.7rem;">
                {{ $pendingIzinCount }}
            </span>
        @endif
    </a>
</div>

<!-- 4. Rekap Presensi & KBM Guru -->
<div class="nav-item-container">
    <a href="{{ route('waka-sdm.rekap-presensi-guru') }}"
       class="nav-btn {{ $isPresensiActive ? 'active' : '' }}">
        <span class="btn-left">
            <i class="bi bi-person-check-fill"></i>
            <span>Presensi & JP Guru</span>
        </span>
    </a>
</div>

<!-- 5. Laporan Jurnal Mengajar -->
<div class="nav-item-container">
    <a href="{{ route('kurikulum.laporan.index') }}"
       class="nav-btn {{ $isLaporanActive ? 'active' : '' }}">
        <span class="btn-left">
            <i class="bi bi-file-earmark-text-fill"></i>
            <span>Laporan Jurnal KBM</span>
        </span>
    </a>
</div>
