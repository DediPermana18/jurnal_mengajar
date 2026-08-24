@extends('layouts.app')

@section('title', 'Dashboard Waka Kurikulum - WebJournal')

@push('styles')
<style>
    .stat-card-modern {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        padding: 1.25rem;
        transition: all 0.2s ease;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    .stat-card-modern:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08);
        border-color: #cbd5e1;
    }
    .quick-link-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        padding: 1.25rem;
        transition: all 0.2s ease;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 1rem;
        color: #0f172a;
    }
    .quick-link-card:hover {
        background: #f8fafc;
        border-color: #3b82f6;
        color: #2563eb;
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(37, 99, 235, 0.08);
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">

    {{-- Page Header --}}
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="font-weight: 900; font-size: 1.75rem; letter-spacing: -0.02em;">
                Dashboard Kurikulum
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                Selamat datang kembali! Ringkasan sistem plotting jadwal, jam pelajaran, dan aktivitas KBM sekolah.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            @if(!$isHariSenin)
                <a href="{{ route('kurikulum.dashboard', ['dev_mode_senin' => 1]) }}"
                   class="btn btn-outline-warning btn-sm rounded-3 fw-semibold d-flex align-items-center gap-1"
                   title="Aktifkan Tampilan Simulasi Hari Senin">
                    <i class="bi bi-bug-fill"></i> Simulasi Hari Senin (Dev)
                </a>
            @else
                @if(request()->has('dev_mode_senin'))
                    <a href="{{ route('kurikulum.dashboard') }}"
                       class="btn btn-light btn-sm border rounded-3 text-muted">
                        <i class="bi bi-x-circle me-1"></i>Tutup Simulasi
                    </a>
                @endif
            @endif
            <span class="badge bg-white text-dark border shadow-2xs rounded-pill px-3 py-2 fw-semibold" style="font-size: 0.82rem;">
                <i class="bi bi-calendar-event me-1 text-primary"></i>
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

    {{-- ============================================================== --}}
    {{-- 1. WIDGET BANNER KHUSUS (MODE KHUSUS SENIN)                     --}}
    {{--    Tampil HANYA jika hari ini Senin (atau dev_mode_senin)       --}}
    {{-- ============================================================== --}}
    @if($isHariSenin)
        @php
            $isTanpaUpacara = $pengaturanJadwal->senin_tanpa_upacara && $pengaturanJadwal->tanggal_eksekusi;
        @endphp
        <div class="card border-0 rounded-4 shadow-sm mb-4 overflow-hidden"
             style="background: {{ $isTanpaUpacara ? 'linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%)' : 'linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%)' }}; border: 1.5px solid {{ $isTanpaUpacara ? '#fed7aa' : '#bbf7d0' }} !important;">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-3 d-flex align-items-center justify-content-center text-white flex-shrink-0"
                             style="width: 48px; height: 48px; background: {{ $isTanpaUpacara ? 'linear-gradient(135deg, #ea580c, #c2410c)' : 'linear-gradient(135deg, #16a34a, #15803d)' }};">
                            <i class="bi {{ $isTanpaUpacara ? 'bi-lightning-charge-fill' : 'bi-flag-fill' }} fs-4"></i>
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <h5 class="fw-bold mb-0 text-dark" style="font-size: 1.1rem;">
                                    Pengaturan Penyesuaian Upacara Senin
                                </h5>
                                @if($isTanpaUpacara)
                                    <span class="badge bg-warning text-dark border border-warning-subtle rounded-pill px-3 py-1 fw-bold" style="font-size: 0.78rem;">
                                        <i class="bi bi-clock-history me-1"></i>KBM Dimajukan 1 JP (Upacara Ditiadakan)
                                    </span>
                                @else
                                    <span class="badge bg-success text-white border border-success-subtle rounded-pill px-3 py-1 fw-bold" style="font-size: 0.78rem;">
                                        <i class="bi bi-check-circle-fill me-1"></i>Jadwal Normal (Ada Upacara Bendera)
                                    </span>
                                @endif
                            </div>
                            <div class="text-muted" style="font-size: 0.85rem;">
                                @if($isTanpaUpacara)
                                    Upacara ditiadakan hari ini. Slot Jam 1 ditiadakan, seluruh jam KBM bergeser maju 1 JP & siswa/guru pulang 1 JP lebih awal.
                                @else
                                    Kegiatan upacara bendera dilaksanakan seperti biasa di Jam ke-1. Pembelajaran KBM dimulai sesuai jadwal standar.
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Switch / Toggle Form --}}
                    @if(auth()->user()->role === 'admin' || in_array(auth()->user()->role, ['waka_kurikulum', 'admin_kurikulum', 'kurikulum']))
                        <form method="POST" action="{{ route('kurikulum.toggle-senin-tanpa-upacara') }}" id="formToggleDashboardSenin">
                            @csrf
                            <div class="d-flex align-items-center gap-2 p-2 rounded-3 bg-white border shadow-2xs">
                                <span class="fw-semibold text-muted small ms-1">Status Kegiatan:</span>
                                <div class="form-check form-switch mb-0 me-2">
                                    <input class="form-check-input" type="checkbox" role="switch" id="toggleDashboardSenin" name="senin_tanpa_upacara" value="1"
                                           {{ $isTanpaUpacara ? 'checked' : '' }}
                                           onchange="this.form.submit()" style="cursor: pointer; width: 3em; height: 1.5em;">
                                    <label class="form-check-label fw-bold text-dark ms-2" for="toggleDashboardSenin" style="font-size: 0.875rem; cursor: pointer;">
                                        {{ $isTanpaUpacara ? 'Upacara Ditiadakan' : 'Mode Normal (Upacara)' }}
                                    </label>
                                </div>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- ============================================================== --}}
    {{-- 2. STAT CARDS (RINGKASAN STATISTIK)                            --}}
    {{-- ============================================================== --}}
    <div class="row g-4 mb-4">
        {{-- Card 1: Total Kelas --}}
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card-modern d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted fw-semibold mb-1" style="font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.05em;">
                        Total Kelas
                    </div>
                    <h3 class="fw-black text-dark mb-0" style="font-weight: 900; font-size: 1.85rem;">
                        {{ number_format($totalKelas) }}
                    </h3>
                    <div class="text-muted mt-1" style="font-size: 0.78rem;">
                        <i class="bi bi-door-open text-primary me-1"></i>Rombongan Belajar
                    </div>
                </div>
                <div class="rounded-3 d-flex align-items-center justify-content-center bg-primary-subtle text-primary" style="width: 52px; height: 52px;">
                    <i class="bi bi-buildings-fill fs-3"></i>
                </div>
            </div>
        </div>

        {{-- Card 2: Total Mata Pelajaran --}}
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card-modern d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted fw-semibold mb-1" style="font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.05em;">
                        Mata Pelajaran
                    </div>
                    <h3 class="fw-black text-dark mb-0" style="font-weight: 900; font-size: 1.85rem;">
                        {{ number_format($totalMapel) }}
                    </h3>
                    <div class="text-muted mt-1" style="font-size: 0.78rem;">
                        <i class="bi bi-journal-check text-success me-1"></i>Mapel Terdaftar
                    </div>
                </div>
                <div class="rounded-3 d-flex align-items-center justify-content-center bg-success-subtle text-success" style="width: 52px; height: 52px;">
                    <i class="bi bi-book-half fs-3"></i>
                </div>
            </div>
        </div>

        {{-- Card 3: Progress Plotting Jadwal --}}
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card-modern">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <div class="text-muted fw-semibold" style="font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.05em;">
                        Plotting Jadwal
                    </div>
                    <div class="rounded-3 d-flex align-items-center justify-content-center bg-info-subtle text-info" style="width: 38px; height: 38px;">
                        <i class="bi bi-calendar-check-fill fs-5"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline gap-2 mb-2">
                    <h3 class="fw-black text-dark mb-0" style="font-weight: 900; font-size: 1.85rem;">
                        {{ $progressPlotting }}%
                    </h3>
                    <span class="text-muted small">Selesai</span>
                </div>
                <div class="progress rounded-pill" style="height: 6px; background-color: #e2e8f0;">
                    <div class="progress-bar bg-info rounded-pill" role="progressbar" style="width: {{ $progressPlotting }}%;" aria-valuenow="{{ $progressPlotting }}" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>
        </div>

        {{-- Card 4: Guru Mengajar Hari Ini --}}
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card-modern d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted fw-semibold mb-1" style="font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.05em;">
                        Guru Hari Ini
                    </div>
                    <h3 class="fw-black text-dark mb-0" style="font-weight: 900; font-size: 1.85rem;">
                        {{ $guruMengajarHariIni }} <span class="text-muted fw-normal fs-6">/ {{ $totalGuru }}</span>
                    </h3>
                    <div class="text-muted mt-1" style="font-size: 0.78rem;">
                        <i class="bi bi-person-check text-warning me-1"></i>Bertugas {{ $hariIniStr }}
                    </div>
                </div>
                <div class="rounded-3 d-flex align-items-center justify-content-center bg-warning-subtle text-warning-emphasis" style="width: 52px; height: 52px;">
                    <i class="bi bi-person-workspace fs-3"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- ============================================================== --}}
    {{-- 3. QUICK LINKS / AKSES CEPAT                                   --}}
    {{-- ============================================================== --}}
    <div class="mb-4">
        <h5 class="fw-bold text-dark mb-3" style="font-size: 1.05rem;">
            <i class="bi bi-lightning-fill text-warning me-1"></i> Akses Cepat Modul Kurikulum
        </h5>
        <div class="row g-3">
            {{-- Link 1: Master Jam Pelajaran --}}
            <div class="col-12 col-md-4">
                <a href="{{ route('kurikulum.jam-pelajaran.index') }}" class="quick-link-card shadow-2xs">
                    <div class="rounded-3 d-flex align-items-center justify-content-center bg-primary-subtle text-primary flex-shrink-0"
                         style="width: 46px; height: 46px;">
                        <i class="bi bi-clock-history fs-4"></i>
                    </div>
                    <div class="overflow-hidden flex-grow-1">
                        <h6 class="fw-bold mb-1 text-dark" style="font-size: 0.95rem;">Master Jam Pelajaran</h6>
                        <div class="text-muted text-truncate" style="font-size: 0.78rem;">
                            Preset slot KBM, Istirahat, Jam Pulang & Agenda Rutin.
                        </div>
                    </div>
                    <i class="bi bi-chevron-right text-muted"></i>
                </a>
            </div>

            {{-- Link 2: Plotting Jadwal Kelas --}}
            <div class="col-12 col-md-4">
                <a href="{{ route('kurikulum.jadwal.index') }}" class="quick-link-card shadow-2xs">
                    <div class="rounded-3 d-flex align-items-center justify-content-center bg-success-subtle text-success flex-shrink-0"
                         style="width: 46px; height: 46px;">
                        <i class="bi bi-calendar-grid fs-4"></i>
                    </div>
                    <div class="overflow-hidden flex-grow-1">
                        <h6 class="fw-bold mb-1 text-dark" style="font-size: 0.95rem;">Plotting Jadwal Kelas</h6>
                        <div class="text-muted text-truncate" style="font-size: 0.78rem;">
                            Atur penugasan Mapel & Guru Pengajar per kelas.
                        </div>
                    </div>
                    <i class="bi bi-chevron-right text-muted"></i>
                </a>
            </div>

            {{-- Link 3: Jadwal Piket Guru --}}
            <div class="col-12 col-md-4">
                <a href="{{ route('kurikulum.jadwal-piket.index') }}" class="quick-link-card shadow-2xs">
                    <div class="rounded-3 d-flex align-items-center justify-content-center bg-info-subtle text-info flex-shrink-0"
                         style="width: 46px; height: 46px;">
                        <i class="bi bi-person-badge-fill fs-4"></i>
                    </div>
                    <div class="overflow-hidden flex-grow-1">
                        <h6 class="fw-bold mb-1 text-dark" style="font-size: 0.95rem;">Jadwal Piket Guru</h6>
                        <div class="text-muted text-truncate" style="font-size: 0.78rem;">
                            Kelola penugasan piket harian guru pengawas KBM.
                        </div>
                    </div>
                    <i class="bi bi-chevron-right text-muted"></i>
                </a>
            </div>
        </div>
    </div>

</div>
@endsection
