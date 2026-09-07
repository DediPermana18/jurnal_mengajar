@props(['pendingIzinCount' => 0])

@php
    $isDashboardActive = request()->routeIs('kepsek.dashboard*');
    $isRekapIzinActive = request()->routeIs('kepsek.rekap-izin*') || request()->routeIs('kepsek.izin.*');
@endphp

<!-- ================= NAVIGASI KEPALA SEKOLAH ================= -->
<div class="nav-item-container mt-2">
    <div class="px-2 mb-2 text-uppercase fw-bold text-muted" style="font-size: 0.68rem; letter-spacing: 0.08em;">
        KEPALA SEKOLAH
    </div>
</div>

<!-- 1. Dashboard Kepsek -->
<div class="nav-item-container">
    <a href="{{ route('kepsek.dashboard') }}"
       class="nav-btn {{ $isDashboardActive ? 'active' : '' }}">
        <span class="btn-left">
            <i class="bi bi-person-workspace"></i>
            <span>Dashboard Kepsek</span>
        </span>
    </a>
</div>

<!-- 2. Persetujuan Izin Guru -->
<div class="nav-item-container">
    <a href="{{ route('kepsek.rekap-izin') }}"
       class="nav-btn d-flex align-items-center justify-content-between {{ $isRekapIzinActive ? 'active' : '' }}">
        <span class="btn-left">
            <i class="bi bi-file-earmark-check-fill"></i>
            <span>Persetujuan Izin Guru</span>
        </span>
        @if(($pendingIzinCount ?? 0) > 0)
            <span class="badge bg-danger rounded-pill px-2 py-0.5" style="font-size: 0.7rem;">
                {{ $pendingIzinCount }}
            </span>
        @else
            <span class="badge bg-secondary-subtle text-secondary border rounded-pill px-1.5 py-0.5" style="font-size: 0.68rem;">
                0
            </span>
        @endif
    </a>
</div>
