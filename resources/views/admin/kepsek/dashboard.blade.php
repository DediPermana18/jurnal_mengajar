@extends('layouts.app')

@section('title', 'Dashboard Kepala Sekolah')

@push('styles')
<style>
    .stat-card-kepsek {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        padding: 1.25rem;
        transition: all 0.25s ease;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        position: relative;
        overflow: hidden;
    }
    .stat-card-kepsek:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08);
        border-color: #cbd5e1;
    }
    .stat-icon-wrapper-kepsek {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
    }
    .actionable-card-kepsek {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        transition: all 0.2s ease;
    }
    .table-dashboard-kepsek th {
        background: #f8fafc;
        font-size: 0.76rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        font-weight: 700;
        padding: 0.85rem 1rem;
        border-bottom: 1px solid #e2e8f0;
    }
    .table-dashboard-kepsek td {
        padding: 0.85rem 1rem;
        vertical-align: middle;
        font-size: 0.86rem;
        border-bottom: 1px solid #f1f5f9;
    }
    .pipeline-pill {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 0.875rem;
        padding: 0.7rem 1rem;
    }
</style>
@endpush

@section('content')
<div class="container-fluid p-4">

    {{-- Header Section --}}
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2.5 py-1 rounded-pill fw-semibold text-xs">
                    <i class="bi bi-award-fill me-1"></i> Persetujuan Sekolah
                </span>
            </div>
            <h2 class="fw-black text-dark mb-1" style="font-weight: 900; font-size: 1.55rem; letter-spacing: -0.02em;">
                Dashboard Kepala Sekolah
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.875rem;">
                Ringkasan pengajuan izin guru, antrean persetujuan, dan aktivitas terakhir di sekolah Anda.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="{{ route('kepsek.rekap-izin') }}" class="btn btn-light border rounded-3 fw-semibold text-sm d-flex align-items-center gap-1.5 shadow-2xs">
                <i class="bi bi-calendar2-check text-primary"></i> Rekap Izin Guru
            </a>
            <a href="{{ route('kepsek.rekap-izin', ['status' => \App\Models\IzinGuru::STATUS_PENDING_KEPSEK]) }}" class="btn btn-primary rounded-3 fw-semibold text-sm d-flex align-items-center gap-1.5 shadow-sm">
                <i class="bi bi-pencil-square"></i> Tinjau & Tanda Tangan
            </a>
            <span class="badge bg-white text-dark border shadow-2xs rounded-pill px-3 py-2 fw-semibold text-sm">
                <i class="bi bi-calendar3 me-1 text-primary"></i>
                {{ $hariIniStr }}, {{ \Carbon\Carbon::now()->translatedFormat('d F Y') }}
            </span>
        </div>
    </div>

    {{-- Flash Alert Messages --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4 d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4 d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-x-circle-fill fs-5"></i>
            <div>{{ session('error') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- ============================================================== --}}
    {{-- 1. STAT CARDS WIDGETS                                          --}}
    {{-- ============================================================== --}}
    <div class="row g-3 mb-4">
        {{-- Card 1: Menunggu Persetujuan Kepala Sekolah --}}
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card-kepsek h-100">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div>
                        <div class="text-uppercase fw-bold text-muted" style="font-size: 0.72rem; letter-spacing: 0.06em;">
                            Menunggu Persetujuan Anda
                        </div>
                        <h3 class="fw-bold text-dark mt-1 mb-0" style="font-size: 1.85rem; letter-spacing: -0.02em;">
                            {{ $totalPendingKepsek }}
                            <span class="text-muted fw-normal" style="font-size: 0.85rem;">Pengajuan</span>
                        </h3>
                    </div>
                    <div class="stat-icon-wrapper-kepsek bg-warning-subtle text-warning-emphasis">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between text-xs text-muted pt-2 border-top">
                    <span>Perlu Tinjauan & TTD Anda</span>
                    @if($totalPendingKepsek > 0)
                        <a href="{{ route('kepsek.rekap-izin', ['status' => \App\Models\IzinGuru::STATUS_PENDING_KEPSEK]) }}" class="text-decoration-none fw-semibold text-warning-emphasis">
                            Tinjau Sekarang &rarr;
                        </a>
                    @else
                        <span class="badge bg-success-subtle text-success border border-success-subtle">Aman</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Card 2: Disetujui --}}
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card-kepsek h-100">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div>
                        <div class="text-uppercase fw-bold text-muted" style="font-size: 0.72rem; letter-spacing: 0.06em;">
                            Disetujui
                        </div>
                        <h3 class="fw-bold text-dark mt-1 mb-0" style="font-size: 1.85rem; letter-spacing: -0.02em;">
                            {{ $totalDisetujui }}
                            <span class="text-muted fw-normal" style="font-size: 0.85rem;">Pengajuan</span>
                        </h3>
                    </div>
                    <div class="stat-icon-wrapper-kepsek bg-success-subtle text-success">
                        <i class="bi bi-check2-circle"></i>
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between text-xs text-muted pt-2 border-top">
                    <span>
                        <i class="bi bi-calendar-check me-1"></i>
                        {{ $disetujuiHariIni }} disetujui hari ini
                    </span>
                    <a href="{{ route('kepsek.rekap-izin', ['status' => \App\Models\IzinGuru::STATUS_DISETUJUI]) }}" class="text-decoration-none fw-semibold text-success">
                        Lihat &rarr;
                    </a>
                </div>
            </div>
        </div>

        {{-- Card 3: Ditolak --}}
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card-kepsek h-100">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div>
                        <div class="text-uppercase fw-bold text-muted" style="font-size: 0.72rem; letter-spacing: 0.06em;">
                            Ditolak
                        </div>
                        <h3 class="fw-bold text-dark mt-1 mb-0" style="font-size: 1.85rem; letter-spacing: -0.02em;">
                            {{ $totalDitolak }}
                            <span class="text-muted fw-normal" style="font-size: 0.85rem;">Pengajuan</span>
                        </h3>
                    </div>
                    <div class="stat-icon-wrapper-kepsek bg-danger-subtle text-danger">
                        <i class="bi bi-x-octagon"></i>
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between text-xs text-muted pt-2 border-top">
                    <span>Total ditolak di semua tahap</span>
                    <a href="{{ route('kepsek.rekap-izin', ['status' => \App\Models\IzinGuru::STATUS_DITOLAK]) }}" class="text-decoration-none fw-semibold text-danger">
                        Lihat &rarr;
                    </a>
                </div>
            </div>
        </div>

        {{-- Card 4: Total Pengajuan --}}
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card-kepsek h-100">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div>
                        <div class="text-uppercase fw-bold text-muted" style="font-size: 0.72rem; letter-spacing: 0.06em;">
                            Total Pengajuan
                        </div>
                        <h3 class="fw-bold text-dark mt-1 mb-0" style="font-size: 1.85rem; letter-spacing: -0.02em;">
                            {{ $totalPengajuan }}
                            <span class="text-muted fw-normal" style="font-size: 0.85rem;">Izin Guru</span>
                        </h3>
                    </div>
                    <div class="stat-icon-wrapper-kepsek bg-primary-subtle text-primary">
                        <i class="bi bi-clipboard2-data"></i>
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between text-xs text-muted pt-2 border-top">
                    <span>Semua status & tahap</span>
                    <a href="{{ route('kepsek.rekap-izin') }}" class="text-decoration-none fw-semibold text-primary">
                        Rekap &rarr;
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Pipeline status approval --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-xl-4">
            <div class="pipeline-pill d-flex align-items-center gap-2">
                <span class="badge bg-info-subtle text-info rounded-circle p-2 d-inline-flex"><i class="bi bi-shield-check fs-6"></i></span>
                <div>
                    <div class="text-uppercase fw-bold text-muted" style="font-size: 0.68rem; letter-spacing: 0.05em;">Antrean Menunggu Piket</div>
                    <div class="fw-bold text-dark" style="font-size: 1.05rem;">{{ $totalPendingPiket }} <span class="text-muted fw-normal text-xs">pengajuan divalidasi Guru Piket</span></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-4">
            <div class="pipeline-pill d-flex align-items-center gap-2">
                <span class="badge bg-info-subtle text-info rounded-circle p-2 d-inline-flex"><i class="bi bi-person-check fs-6"></i></span>
                <div>
                    <div class="text-uppercase fw-bold text-muted" style="font-size: 0.68rem; letter-spacing: 0.05em;">Antrean Menunggu Waka SDM</div>
                    <div class="fw-bold text-dark" style="font-size: 1.05rem;">{{ $totalPendingWaka }} <span class="text-muted fw-normal text-xs">pengajuan menuju Anda berikutnya</span></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-4">
            <div class="pipeline-pill d-flex align-items-center gap-2">
                <span class="badge bg-success-subtle text-success rounded-circle p-2 d-inline-flex"><i class="bi bi-patch-check fs-6"></i></span>
                <div>
                    <div class="text-uppercase fw-bold text-muted" style="font-size: 0.68rem; letter-spacing: 0.05em;">Persetujuan Final</div>
                    <div class="fw-bold text-dark" style="font-size: 1.05rem;">{{ $totalDisetujui }} <span class="text-muted fw-normal text-xs">izin disetujui ({{ $disetujuiHariIni }} hari ini)</span></div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============================================================== --}}
    {{-- 2. ANTREAN PERSETUJUAN & AKTIVITAS IZIN TERBARU                --}}
    {{-- ============================================================== --}}
    <div class="row g-3 mb-4 align-items-stretch">
        
        {{-- CARD 1: ANTREAN PERSETUJUAN --}}
        <div class="col-12 col-lg-6 d-flex">
            <div class="card border-0 rounded-4 shadow-sm w-100 p-3.5 d-flex flex-column" style="min-height: 250px; background: #ffffff;">
                {{-- Header Card --}}
                <div class="d-flex align-items-center justify-content-between pb-3 mb-3 border-bottom gap-2">
                    <div class="d-flex align-items-center gap-2.5">
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-3 p-2 d-inline-flex flex-shrink-0">
                            <i class="bi bi-hourglass-split fs-6"></i>
                        </span>
                        <div>
                            <div class="fw-bold text-dark" style="font-size: 0.95rem; line-height: 1.25;">
                                Antrean Persetujuan Terbaru
                            </div>
                            <div class="text-muted" style="font-size: 0.75rem;">
                                Pengajuan izin guru yang menunggu keputusan & tanda tangan Anda.
                            </div>
                        </div>
                    </div>
                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-2.5 py-1 text-xs flex-shrink-0">
                        {{ $antreanKepsek->count() }} Menunggu
                    </span>
                </div>

                {{-- Body Card --}}
                <div class="flex-grow-1 d-flex flex-column justify-content-center">
                    @if($antreanKepsek->isEmpty())
                        <div class="text-center py-4 px-3 my-auto">
                            <div class="rounded-circle bg-success-subtle text-success border border-success-subtle d-inline-flex align-items-center justify-content-center mb-2" style="width: 38px; height: 38px; font-size: 1.1rem;">
                                <i class="bi bi-check2"></i>
                            </div>
                            <div class="fw-semibold text-dark mb-0.5" style="font-size: 0.875rem;">Tidak ada antrean persetujuan.</div>
                            <div class="text-muted" style="font-size: 0.75rem;">Semua pengajuan izin guru sudah diproses.</div>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" style="font-size: 0.82rem;">
                                <thead class="bg-light text-muted fw-semibold text-uppercase" style="font-size: 0.7rem;">
                                    <tr>
                                        <th class="py-2 px-2.5">Nama Guru</th>
                                        <th class="py-2 px-2.5">Tanggal & Kategori</th>
                                        <th class="py-2 px-2.5">Alasan</th>
                                        <th class="py-2 px-2.5 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($antreanKepsek as $izin)
                                        <tr>
                                            <td class="py-2 px-2.5">
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="rounded-circle bg-warning-subtle text-warning-emphasis d-flex align-items-center justify-content-center fw-bold flex-shrink-0" style="width: 28px; height: 28px; font-size: 0.75rem;">
                                                        {{ strtoupper(substr($izin->user?->nama ?? 'G', 0, 1)) }}
                                                    </div>
                                                    <div>
                                                        <div class="fw-semibold text-dark" style="font-size: 0.83rem;">{{ $izin->user?->nama ?? 'Guru Tidak Ditemukan' }}</div>
                                                        <div class="text-muted text-2xs">{{ $izin->user?->nip ? 'NIP: ' . $izin->user->nip : 'Non-NIP' }}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-2 px-2.5">
                                                <span class="fw-semibold text-dark">{{ $izin->tanggal->translatedFormat('d F Y') }}</span>
                                                <div class="text-muted text-2xs">{{ $izin->kategori_izin_label }}</div>
                                            </td>
                                            <td class="py-2 px-2.5">
                                                <div class="text-truncate text-muted" style="max-width: 140px;" title="{{ $izin->alasan }}">
                                                    {{ $izin->alasan ?? '-' }}
                                                </div>
                                            </td>
                                            <td class="py-2 px-2.5 text-center">
                                                <span class="badge {{ $izin->status_badge }} px-2 py-0.5 rounded-pill text-2xs">
                                                    {{ $izin->status_label }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                {{-- Footer Card --}}
                <div class="mt-auto pt-3 border-top d-flex align-items-center justify-content-between flex-wrap gap-2" style="font-size: 0.75rem;">
                    <span class="text-muted">Klik untuk membuka modul persetujuan & tanda tangan</span>
                    <a href="{{ route('kepsek.rekap-izin', ['status' => \App\Models\IzinGuru::STATUS_PENDING_KEPSEK]) }}" class="btn btn-sm btn-primary rounded-3 text-xs fw-semibold px-3 py-1.5 shadow-sm">
                        <i class="bi bi-pencil-square me-1"></i> Tinjau & Tanda Tangan
                    </a>
                </div>
            </div>
        </div>

        {{-- CARD 2: AKTIVITAS IZIN TERBARU --}}
        <div class="col-12 col-lg-6 d-flex">
            <div class="card border-0 rounded-4 shadow-sm w-100 p-3.5 d-flex flex-column" style="min-height: 250px; background: #ffffff;">
                {{-- Header Card --}}
                <div class="d-flex align-items-center justify-content-between pb-3 mb-3 border-bottom gap-2">
                    <div class="d-flex align-items-center gap-2.5">
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-3 p-2 d-inline-flex flex-shrink-0">
                            <i class="bi bi-activity fs-6"></i>
                        </span>
                        <div>
                            <div class="fw-bold text-dark" style="font-size: 0.95rem; line-height: 1.25;">
                                Aktivitas Izin Terbaru
                            </div>
                            <div class="text-muted" style="font-size: 0.75rem;">
                                Pengajuan izin guru terbaru di semua tahap persetujuan.
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Body Card --}}
                <div class="flex-grow-1 d-flex flex-column justify-content-center">
                    @if($aktivitasTerbaru->isEmpty())
                        <div class="text-center py-4 px-3 my-auto">
                            <div class="rounded-circle bg-secondary-subtle text-secondary border border-secondary-subtle d-inline-flex align-items-center justify-content-center mb-2" style="width: 38px; height: 38px; font-size: 1.1rem;">
                                <i class="bi bi-inbox"></i>
                            </div>
                            <div class="fw-semibold text-dark mb-0.5" style="font-size: 0.875rem;">Belum ada aktivitas izin guru.</div>
                            <div class="text-muted" style="font-size: 0.75rem;">Riwayat pengajuan izin akan tampil di sini.</div>
                        </div>
                    @else
                        <div class="d-flex flex-column gap-2">
                            @foreach($aktivitasTerbaru as $izin)
                                <div class="p-2 rounded-3 bg-light d-flex align-items-center gap-2.5">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold flex-shrink-0 {{ in_array($izin->status, [\App\Models\IzinGuru::STATUS_DISETUJUI]) ? 'bg-success-subtle text-success' : (in_array($izin->status, [\App\Models\IzinGuru::STATUS_DITOLAK]) ? 'bg-danger-subtle text-danger' : 'bg-warning-subtle text-warning-emphasis') }}" style="width: 30px; height: 30px; font-size: 0.75rem;">
                                        {{ strtoupper(substr($izin->user?->nama ?? 'G', 0, 1)) }}
                                    </div>
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="d-flex align-items-center justify-content-between gap-2">
                                            <span class="fw-semibold text-dark text-truncate" style="font-size: 0.83rem;">{{ $izin->user?->nama ?? 'Guru Tidak Ditemukan' }}</span>
                                            <span class="badge {{ $izin->status_badge }} px-2 py-0.5 rounded-pill text-2xs flex-shrink-0">{{ $izin->status_label }}</span>
                                        </div>
                                        <div class="text-muted text-2xs">
                                            {{ $izin->tanggal->translatedFormat('d F Y') }} &bull; {{ $izin->kategori_izin_label }}
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Footer Card --}}
                <div class="mt-auto pt-3 border-top d-flex align-items-center justify-content-between flex-wrap gap-2" style="font-size: 0.75rem;">
                    <span class="text-muted">Lihat detail lengkap & filter di modul rekap</span>
                    <a href="{{ route('kepsek.rekap-izin') }}" class="text-primary text-decoration-none fw-semibold">
                        Buka Rekap Izin &rarr;
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection