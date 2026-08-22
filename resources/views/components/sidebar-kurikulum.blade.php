@props(['pendingIzinCount' => 0])

@php
    // State penanda active route (berdasarkan route name, bukan URL)
    $isDashboardActive      = request()->routeIs('kurikulum.dashboard*');
    $isJadwalActive         = request()->routeIs('kurikulum.jam-pelajaran.*') || request()->routeIs('kurikulum.jadwal.*') || request()->routeIs('kurikulum.jadwal-piket.*');
    $isJamPelajaranActive   = request()->routeIs('kurikulum.jam-pelajaran.*');
    $isPlottingJadwalActive = request()->routeIs('kurikulum.jadwal.*');
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

<!-- 2. Jadwal Pelajaran (Dropdown / Submenu) -->
<div class="nav-item-container">
    <button class="nav-btn {{ $isJadwalActive ? 'active' : '' }}"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#dropdownJadwalKurikulum"
            aria-expanded="{{ $isJadwalActive ? 'true' : 'false' }}"
            aria-controls="dropdownJadwalKurikulum">
        <span class="btn-left">
            <i class="bi bi-calendar3"></i>
            <span>Jadwal Pelajaran</span>
        </span>
        <i class="bi bi-chevron-down chevron-icon"></i>
    </button>

    <div class="collapse {{ $isJadwalActive ? 'show' : '' }}" id="dropdownJadwalKurikulum">
        <ul class="submenu-list">
            <li>
                <a href="{{ Route::has('kurikulum.jam-pelajaran.index') ? route('kurikulum.jam-pelajaran.index') : '#' }}"
                   class="submenu-item-link {{ $isJamPelajaranActive ? 'active' : '' }}">
                    <i class="bi bi-clock-history"></i>
                    <span>Master Jam Pelajaran</span>
                </a>
            </li>
            <li>
                <a href="{{ Route::has('kurikulum.jadwal.index') ? route('kurikulum.jadwal.index') : '#' }}"
                   class="submenu-item-link {{ $isPlottingJadwalActive ? 'active' : '' }}">
                    <i class="bi bi-calendar-range"></i>
                    <span>Plotting Jadwal Kelas</span>
                </a>
            </li>
            <li>
                <a href="{{ Route::has('kurikulum.jadwal-piket.index') ? route('kurikulum.jadwal-piket.index') : '#' }}"
                   class="submenu-item-link {{ $isJadwalPiketActive ? 'active' : '' }}">
                    <i class="bi bi-shield-check"></i>
                    <span>Jadwal Piket Guru</span>
                </a>
            </li>
        </ul>
    </div>
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
