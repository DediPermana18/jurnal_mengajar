@php
    $isDashboardActive   = request()->routeIs('waka-piket.dashboard*');
    $isRekapHarianActive = request()->routeIs('waka-piket.rekap-harian*') || request()->routeIs('waka-piket.validasi') || request()->routeIs('waka-piket.klb');
@endphp

{{-- Komponen ini HANYA dirender ketika @if($isWakaPiketRole) di layouts/app.blade.php terpenuhi --}}

<!-- ================= NAVIGASI WAKA PIKET ================= -->
<div class="nav-item-container mt-2">
    <div class="px-2 mb-2 text-uppercase fw-bold text-muted" style="font-size: 0.68rem; letter-spacing: 0.08em;">
        WAKA PIKET
    </div>
</div>

<!-- 1. Dashboard Waka Piket -->
<div class="nav-item-container">
    <a href="{{ Route::has('waka-piket.dashboard') ? route('waka-piket.dashboard') : '#' }}"
       class="nav-btn {{ $isDashboardActive ? 'active' : '' }}">
        <span class="btn-left">
            <i class="bi bi-layout-sidebar-inset"></i>
            <span>Dashboard</span>
        </span>
    </a>
</div>

<!-- 2. Rekap Harian Piket -->
<div class="nav-item-container">
    <a href="{{ Route::has('waka-piket.rekap-harian') ? route('waka-piket.rekap-harian') : '#' }}"
       class="nav-btn {{ $isRekapHarianActive ? 'active' : '' }}">
        <span class="btn-left">
            <i class="bi bi-clipboard-check"></i>
            <span>Rekap Harian Piket</span>
        </span>
    </a>
</div>