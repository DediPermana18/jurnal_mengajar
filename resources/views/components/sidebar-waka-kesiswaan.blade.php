@props(['pendingTtdCount' => 0])

@php
    $isDashboardActive = request()->routeIs('waka-kesiswaan.dashboard*');
    $isApprovalActive  = request()->routeIs('waka-kesiswaan.dispensasi.approval.*');
@endphp

{{-- Komponen ini HANYA dirender ketika @if($isWakaKesiswaanRole) di layouts/app.blade.php terpenuhi --}}

<!-- ================= NAVIGASI WAKA KESISWAAN ================= -->
<div class="nav-item-container mt-2">
    <div class="px-2 mb-2 text-uppercase fw-bold text-muted" style="font-size: 0.68rem; letter-spacing: 0.08em;">
        WAKA KESISWAAN
    </div>
</div>

<!-- 1. Dashboard -->
<div class="nav-item-container">
    <a href="{{ Route::has('waka-kesiswaan.dashboard') ? route('waka-kesiswaan.dashboard') : '#' }}"
       class="nav-btn {{ $isDashboardActive ? 'active' : '' }}">
        <span class="btn-left">
            <i class="bi bi-layout-sidebar-inset"></i>
            <span>Dashboard</span>
        </span>
    </a>
</div>

<!-- 2. Approval Dispensasi -->
<div class="nav-item-container">
    <a href="{{ Route::has('waka-kesiswaan.dispensasi.approval.index') ? route('waka-kesiswaan.dispensasi.approval.index') : '#' }}"
       class="nav-btn d-flex align-items-center justify-content-between {{ $isApprovalActive ? 'active' : '' }}">
        <span class="btn-left">
            <i class="bi bi-clipboard-check"></i>
            <span>Approval Dispensasi</span>
        </span>
        @if(($pendingTtdCount ?? 0) > 0)
            <span class="badge bg-danger rounded-pill px-2 py-0.5" style="font-size: 0.7rem;">
                {{ $pendingTtdCount }}
            </span>
        @else
            <span class="badge bg-secondary-subtle text-secondary border rounded-pill px-1.5 py-0.5" style="font-size: 0.68rem;">
                0
            </span>
        @endif
    </a>
</div>