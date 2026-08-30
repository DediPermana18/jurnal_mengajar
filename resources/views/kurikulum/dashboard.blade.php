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
            @if(!($isHariSenin ?? false))
                <a href="{{ route('kurikulum.dashboard', ['dev_mode_senin' => 1]) }}"
                   class="btn btn-outline-warning btn-sm rounded-3 fw-semibold d-flex align-items-center gap-1"
                   title="Simulasi Tampilan Sakelar Senin">
                    <i class="bi bi-bug-fill"></i> Simulasi Senin (Dev)
                </a>
            @endif
            @if(!($isHariJumat ?? false))
                <a href="{{ route('kurikulum.dashboard', ['dev_mode_jumat' => 1]) }}"
                   class="btn btn-outline-info btn-sm rounded-3 fw-semibold d-flex align-items-center gap-1"
                   title="Simulasi Tampilan Sakelar Jumat">
                    <i class="bi bi-bug-fill"></i> Simulasi Jumat (Dev)
                </a>
            @endif
            @if(request()->has('dev_mode_senin') || request()->has('dev_mode_jumat'))
                <a href="{{ route('kurikulum.dashboard') }}"
                   class="btn btn-light btn-sm border rounded-3 text-muted">
                    <i class="bi bi-x-circle me-1"></i>Tutup Simulasi
                </a>
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
    {{-- 1. QUICK CONTROL / PENGATURAN KBM HARI INI                     --}}
    {{--    Tampil HANYA jika $hariAktif == 'Senin' atau 'Jumat'        --}}
    {{-- ============================================================== --}}
    @if((auth()->user()->role === 'admin' || in_array(auth()->user()->role, ['waka_kurikulum', 'admin_kurikulum', 'kurikulum'])) && in_array($hariAktif, ['Senin', 'Jumat']))
        @php
            $isSeninTanpaUpacara = $pengaturanJadwal->senin_tanpa_upacara && $pengaturanJadwal->tanggal_eksekusi;
            $isJumatTanpaPembiasaan = $pengaturanJadwal->jumat_tanpa_pembiasaan && $pengaturanJadwal->tanggal_eksekusi_jumat;
        @endphp

        <div class="mb-4">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <h5 class="fw-bold text-dark mb-0" style="font-size: 1.05rem;">
                    <i class="bi bi-sliders text-danger me-1"></i> Quick Control / Pengaturan KBM Hari Ini
                </h5>
                <span class="badge bg-light text-muted border rounded-pill px-3 py-1" style="font-size: 0.75rem;">
                    <i class="bi bi-clock-history me-1"></i>Hari Aktif: {{ $hariAktif }} @if($isSimulasiSenin || $isSimulasiJumat) (Mode Simulasi) @endif
                </span>
            </div>

            @if($hariAktif === 'Senin')
                {{-- CARD SAKELAR HARI SENIN --}}
                <div class="card border-0 rounded-4 shadow-sm overflow-hidden"
                     style="background: {{ $isSeninTanpaUpacara ? 'linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%)' : 'linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%)' }}; border: 1.5px solid {{ $isSeninTanpaUpacara ? '#fed7aa' : '#bbf7d0' }} !important;">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-3 d-flex align-items-center justify-content-center text-white flex-shrink-0"
                                     style="width: 48px; height: 48px; background: {{ $isSeninTanpaUpacara ? 'linear-gradient(135deg, #ea580c, #c2410c)' : 'linear-gradient(135deg, #16a34a, #15803d)' }};">
                                    <i class="bi {{ $isSeninTanpaUpacara ? 'bi-lightning-charge-fill' : 'bi-flag-fill' }} fs-4"></i>
                                </div>
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                        <h5 class="fw-bold mb-0 text-dark" style="font-size: 1.05rem;">
                                            Sakelar Khusus Hari Senin: Upacara Ditiadakan
                                        </h5>
                                        @if($isSeninTanpaUpacara)
                                            <span class="badge bg-warning text-dark border border-warning-subtle rounded-pill px-3 py-1 fw-bold" style="font-size: 0.78rem;">
                                                <i class="bi bi-clock-history me-1"></i>Mode Maju 1 JP
                                            </span>
                                        @else
                                            <span class="badge bg-success text-white border border-success-subtle rounded-pill px-3 py-1 fw-bold" style="font-size: 0.78rem;">
                                                <i class="bi bi-check-circle-fill me-1"></i>Senin Normal (Ada Upacara)
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-muted" style="font-size: 0.85rem;">
                                        Aktifkan jika Upacara Bendera ditiadakan. Seluruh jam KBM dimajukan 1 JP & siswa/guru pulang 1 JP lebih awal.
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

            @elseif($hariAktif === 'Jumat')
                {{-- CARD SAKELAR HARI JUMAT --}}
                <div class="card border-0 rounded-4 shadow-sm overflow-hidden"
                     style="background: {{ $isJumatTanpaPembiasaan ? 'linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%)' : 'linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%)' }}; border: 1.5px solid {{ $isJumatTanpaPembiasaan ? '#fed7aa' : '#bbf7d0' }} !important;">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-3 d-flex align-items-center justify-content-center text-white flex-shrink-0"
                                     style="width: 48px; height: 48px; background: {{ $isJumatTanpaPembiasaan ? 'linear-gradient(135deg, #ea580c, #c2410c)' : 'linear-gradient(135deg, #0284c7, #0369a1)' }};">
                                    <i class="bi {{ $isJumatTanpaPembiasaan ? 'bi-lightning-charge-fill' : 'bi-heart-pulse-fill' }} fs-4"></i>
                                </div>
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                        <h5 class="fw-bold mb-0 text-dark" style="font-size: 1.05rem;">
                                            Sakelar Khusus Hari Jumat: Pembiasaan Ditiadakan
                                        </h5>
                                        @if($isJumatTanpaPembiasaan)
                                            <span class="badge bg-warning text-dark border border-warning-subtle rounded-pill px-3 py-1 fw-bold" style="font-size: 0.78rem;">
                                                <i class="bi bi-clock-history me-1"></i>Mode Maju 1 JP
                                            </span>
                                        @else
                                            <span class="badge bg-success text-white border border-success-subtle rounded-pill px-3 py-1 fw-bold" style="font-size: 0.78rem;">
                                                <i class="bi bi-check-circle-fill me-1"></i>Jumat Normal (Ada Pembiasaan)
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-muted" style="font-size: 0.85rem;">
                                        Aktifkan jika Kegiatan Pembiasaan Jumat ditiadakan. Seluruh jam KBM dimajukan 1 JP & KBM dimulai lebih awal.
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            @endif
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
