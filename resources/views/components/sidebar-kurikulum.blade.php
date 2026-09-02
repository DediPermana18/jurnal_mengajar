@props(['pendingIzinCount' => 0])

@php
    // State penanda active route (berdasarkan route name, bukan URL)
    $isDashboardActive      = request()->routeIs('kurikulum.dashboard*');
    $isJadwalPiketActive    = request()->routeIs('kurikulum.jadwal-piket.*');
    $isMapelActive          = request()->routeIs('mapel.*');
    $isIzinActive           = request()->routeIs('kurikulum.izin.*');
    $isLaporanActive        = request()->routeIs('kurikulum.laporan.*');
@endphp

{{-- Komponen ini HANYA dirender ketika @if($isKurikulumRole) di layouts/app.blade.php terpenuhi --}}

<!-- ================= NAVIGASI WAKA KURIKULUM ================= -->
<div class="nav-item-container mt-2">
    <div class="px-2 mb-2 text-uppercase fw-bold text-muted" style="font-size: 0.68rem; letter-spacing: 0.08em;">
        WAKA KURIKULUM
    </div>
</div>

<!-- 1. Dashboard -->
<div class="nav-item-container">
    <a href="{{ Route::has('kurikulum.dashboard') ? route('kurikulum.dashboard') : '#' }}"
       class="nav-btn {{ $isDashboardActive ? 'active' : '' }}">
        <span class="btn-left">
            <i class="bi bi-layout-sidebar-inset"></i>
            <span>Dashboard</span>
        </span>
    </a>
</div>

<!-- 2. Jadwal Piket Guru -->
<div class="nav-item-container">
    <a href="{{ Route::has('kurikulum.jadwal-piket.index') ? route('kurikulum.jadwal-piket.index') : '#' }}"
       class="nav-btn {{ $isJadwalPiketActive ? 'active' : '' }}">
        <span class="btn-left">
            <i class="bi bi-shield-check"></i>
            <span>Jadwal Piket Guru</span>
        </span>
    </a>
</div>

<!-- 3. Data Mata Pelajaran -->
<div class="nav-item-container">
    <a href="{{ Route::has('mapel.index') ? route('mapel.index') : '#' }}"
       class="nav-btn {{ $isMapelActive ? 'active' : '' }}">
        <span class="btn-left">
            <i class="bi bi-book"></i>
            <span>Data Mata Pelajaran</span>
        </span>
    </a>
</div>

<!-- 4. Approval Izin Guru -->
<div class="nav-item-container">
    <a href="{{ Route::has('kurikulum.izin.index') ? route('kurikulum.izin.index') : '#' }}"
       class="nav-btn d-flex align-items-center justify-content-between {{ $isIzinActive ? 'active' : '' }}">
        <span class="btn-left">
            <i class="bi bi-person-check-fill"></i>
            <span>Approval Izin Guru</span>
        </span>
        @if(($pendingIzinCount ?? 0) > 0)
            <span class="badge bg-danger rounded-pill px-2 py-1" style="font-size: 0.72rem;">
                {{ $pendingIzinCount }}
            </span>
        @else
            <span class="badge bg-secondary-subtle text-secondary border rounded-pill px-2 py-1" style="font-size: 0.72rem;">
                0
            </span>
        @endif
    </a>
</div>

<!-- 4b. Pengaturan Alur Approval Izin -->
<div class="nav-item-container">
    <a href="{{ Route::has('kurikulum.izin.setting') ? route('kurikulum.izin.setting') : '#' }}"
       class="nav-btn {{ request()->routeIs('kurikulum.izin.setting*') ? 'active' : '' }}">
        <span class="btn-left">
            <i class="bi bi-diagram-3"></i>
            <span>Pengaturan Alur Izin</span>
        </span>
    </a>
</div>

<!-- 4c. Approval Dispensasi -->
<div class="nav-item-container">
    <a href="{{ Route::has('kurikulum.dispensasi.approval.index') ? route('kurikulum.dispensasi.approval.index') : '#' }}"
       class="nav-btn {{ request()->routeIs('kurikulum.dispensasi.approval.*') ? 'active' : '' }}">
        <span class="btn-left">
            <i class="bi bi-clipboard-check"></i>
            <span>Approval Dispensasi</span>
        </span>
    </a>
</div>

<!-- 5. Laporan KBM -->
<div class="nav-item-container">
    <a href="{{ Route::has('kurikulum.laporan.index') ? route('kurikulum.laporan.index') : '#' }}"
       class="nav-btn {{ $isLaporanActive ? 'active' : '' }}">
        <span class="btn-left">
            <i class="bi bi-file-earmark-text-fill"></i>
            <span>Laporan KBM</span>
        </span>
    </a>
</div>
