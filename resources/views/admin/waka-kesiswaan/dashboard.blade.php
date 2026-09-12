@extends('layouts.app')

@section('title', 'Dashboard Waka Kesiswaan - WebJournal')

@push('styles')
<style>
    .stat-card-wk {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        padding: 1.25rem;
        transition: all 0.25s ease;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        position: relative;
        overflow: hidden;
    }
    .stat-card-wk:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08);
        border-color: #cbd5e1;
    }
    .table-custom-wk th {
        background: #f8fafc;
        font-size: 0.76rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        font-weight: 700;
        padding: 0.85rem 1rem;
        border-bottom: 1px solid #e2e8f0;
    }
    .table-custom-wk td {
        padding: 0.85rem 1rem;
        vertical-align: middle;
        font-size: 0.86rem;
        border-bottom: 1px solid #f1f5f9;
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">

    {{-- Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1 rounded-pill fw-semibold text-xs">
                    <i class="bi bi-shield-check me-1"></i> Kesiswaan
                </span>
            </div>
            <h2 class="fw-black text-dark mb-1" style="font-weight: 900; font-size: 1.55rem; letter-spacing: -0.02em;">
                Dashboard Waka Kesiswaan
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.875rem;">
                Peninjauan dispensasi siswa, verifikasi kelayakan, dan penandatanganan digital surat dispensasi.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="{{ route('waka-kesiswaan.dispensasi.approval.index') }}" class="btn btn-primary rounded-3 fw-semibold text-sm d-flex align-items-center gap-1.5 shadow-sm">
                <i class="bi bi-clipboard-check"></i> Approval Dispensasi
            </a>
            <span class="badge bg-white text-dark border shadow-2xs rounded-pill px-3 py-2 fw-semibold text-sm">
                <i class="bi bi-calendar3 me-1 text-primary"></i>
                {{ now()->translatedFormat('l, d F Y') }}
            </span>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4 d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Stat Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card-wk h-100">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div>
                        <div class="text-uppercase fw-bold text-muted" style="font-size: 0.72rem; letter-spacing: 0.06em;">
                            Dispensasi Hari Ini
                        </div>
                        <h3 class="fw-bold text-dark mt-1 mb-0" style="font-size: 1.85rem; letter-spacing: -0.02em;">{{ $totalHariIni }}</h3>
                    </div>
                    <div class="stat-icon-wrapper bg-primary-subtle text-primary" style="width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.35rem;">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card-wk h-100">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div>
                        <div class="text-uppercase fw-bold text-muted" style="font-size: 0.72rem; letter-spacing: 0.06em;">
                            Menunggu TTD
                        </div>
                        <h3 class="fw-bold text-warning mt-1 mb-0" style="font-size: 1.85rem; letter-spacing: -0.02em;">{{ $pendingTtd }}</h3>
                    </div>
                    <div class="stat-icon-wrapper bg-warning-subtle text-warning" style="width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.35rem;">
                        <i class="bi bi-pen"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card-wk h-100">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div>
                        <div class="text-uppercase fw-bold text-muted" style="font-size: 0.72rem; letter-spacing: 0.06em;">
                            Sudah Di-TTD
                        </div>
                        <h3 class="fw-bold text-success mt-1 mb-0" style="font-size: 1.85rem; letter-spacing: -0.02em;">{{ $sudahTtd }}</h3>
                    </div>
                    <div class="stat-icon-wrapper bg-success-subtle text-success" style="width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.35rem;">
                        <i class="bi bi-patch-check-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card-wk h-100">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div>
                        <div class="text-uppercase fw-bold text-muted" style="font-size: 0.72rem; letter-spacing: 0.06em;">
                            Total Pengajuan
                        </div>
                        <h3 class="fw-bold text-dark mt-1 mb-0" style="font-size: 1.85rem; letter-spacing: -0.02em;">{{ $totalSemua }}</h3>
                    </div>
                    <div class="stat-icon-wrapper bg-secondary-subtle text-secondary" style="width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.35rem;">
                        <i class="bi bi-list-check"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Riwayat terbaru --}}
    <div class="card border-0 rounded-4 shadow-sm">
        <div class="card-header bg-white border-0 rounded-4 pt-4 pb-2 d-flex justify-content-between align-items-center px-4">
            <h5 class="fw-bold text-dark mb-0"><i class="bi bi-clock-history me-2"></i>Riwayat Dispensasi Terbaru</h5>
            <a href="{{ route('waka-kesiswaan.dispensasi.approval.index') }}" class="btn btn-sm btn-light border rounded-3 fw-semibold">Lihat Semua</a>
        </div>
        <div class="card-body px-3 pb-3">
            <div class="table-responsive">
                <table class="table table-custom-wk align-middle mb-0">
                    <thead>
                        <tr>
                            <th>TANGGAL</th>
                            <th>SISWA</th>
                            <th>JAM</th>
                            <th>STATUS</th>
                            <th>TTD</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($riwayatTerbaru as $dispen)
                            <tr>
                                <td class="fw-semibold text-dark text-nowrap">{{ $dispen->tanggal?->translatedFormat('d/m/Y') ?? '-' }}</td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $dispen->siswa?->nama ?? '-' }}</div>
                                    <div class="text-muted small">{{ $dispen->siswa?->kelas?->nama_kelas ?? '-' }}</div>
                                </td>
                                <td class="fw-semibold text-dark">{{ $dispen->jam_ke_label ?? '-' }}</td>
                                <td><span class="badge {{ $dispen->status_badge }} rounded-pill px-2 py-1">{{ $dispen->status_label }}</span></td>
                                <td>
                                    @if($dispen->has_ttd_waka)
                                        <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle rounded-pill px-2 py-1">
                                            {{ $dispen->wakaKesiswaan?->nama ?? 'Sudah di-TTD' }}
                                        </span>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-2 py-1">Belum</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox-fill me-2"></i>Belum ada pengajuan dispensasi.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection