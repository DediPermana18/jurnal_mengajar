@extends('layouts.app')

@section('title', 'Dashboard Waka Piket - WebJournal')

@push('styles')
<style>
    .stat-card-wp {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        padding: 1.25rem;
        transition: all 0.25s ease;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        position: relative;
        overflow: hidden;
    }
    .stat-card-wp:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08);
        border-color: #cbd5e1;
    }
    .stat-icon-wrapper-wp {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
    }
    .table-custom-wp th {
        background: #f8fafc;
        font-size: 0.76rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        font-weight: 700;
        padding: 0.85rem 1rem;
        border-bottom: 1px solid #e2e8f0;
    }
    .table-custom-wp td {
        padding: 0.85rem 1rem;
        vertical-align: middle;
        font-size: 0.86rem;
        border-bottom: 1px solid #f1f5f9;
    }
    .chip-status-kehadiran {
        min-width: 110px;
        border-radius: 0.9rem;
        padding: 0.55rem 0.9rem;
        text-align: center;
        border: 1px solid transparent;
    }
    .chip-status-kehadiran .num {
        font-size: 1.45rem;
        font-weight: 800;
        line-height: 1.1;
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">

    {{-- Header Section --}}
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-info-subtle text-info border border-info-subtle px-2.5 py-1 rounded-pill fw-semibold text-xs">
                    <i class="bi bi-shield-check me-1"></i> Piket & Ketertiban
                </span>
                @if($isSimulasiSenin || $isSimulasiJumat)
                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2.5 py-1 rounded-pill fw-semibold text-xs">
                        <i class="bi bi-flask me-1"></i> Simulasi: {{ $hariAktif }}
                    </span>
                @endif
            </div>
            <h2 class="fw-black text-dark mb-1" style="font-weight: 900; font-size: 1.55rem; letter-spacing: -0.02em;">
                Dashboard Waka Piket
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.875rem;">
                Monitoring kehadiran guru, petugas & koordinator piket aktif, dan pantauan kelas kosong pagi/siang.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="{{ route('waka-piket.rekap-harian') }}" class="btn btn-primary rounded-3 fw-semibold text-sm d-flex align-items-center gap-1.5 shadow-sm">
                <i class="bi bi-clipboard-check"></i> Rekap Harian Piket
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
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <div>{{ session('error') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- ============================================================== --}}
    {{-- 1. STAT KEHADIRAN GURU (Hadir / Izin / Sakit / Dinas Luar / Alpa) --}}
    {{-- ============================================================== --}}
    <div class="row g-3 mb-4">
        {{-- Card 1: Guru Hadir --}}
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card-wp h-100">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div>
                        <div class="text-uppercase fw-bold text-muted" style="font-size: 0.72rem; letter-spacing: 0.06em;">
                            Guru Hadir Hari Ini
                        </div>
                        <h3 class="fw-bold text-dark mt-1 mb-0" style="font-size: 1.85rem; letter-spacing: -0.02em;">
                            {{ $totalHadir }}
                            <span class="text-muted fw-normal" style="font-size: 0.85rem;">/ {{ $totalGuruTerjadwal }} Terjadwal</span>
                        </h3>
                    </div>
                    <div class="stat-icon-wrapper-wp bg-success-subtle text-success">
                        <i class="bi bi-person-check-fill"></i>
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between text-xs text-muted pt-2 border-top">
                    <span>Guru Terjadwal KBM</span>
                    <span class="text-success fw-semibold">
                        @if($totalGuruTerjadwal > 0)
                            {{ round(($totalHadir / $totalGuruTerjadwal) * 100) }}% Hadir
                        @else
                            100%
                        @endif
                    </span>
                </div>
            </div>
        </div>

        {{-- Card 2: Izin / Sakit / Dinas Luar --}}
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card-wp h-100">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div>
                        <div class="text-uppercase fw-bold text-muted" style="font-size: 0.72rem; letter-spacing: 0.06em;">
                            Izin / Sakit / Dinas Luar
                        </div>
                        <h3 class="fw-bold text-dark mt-1 mb-0" style="font-size: 1.85rem; letter-spacing: -0.02em;">
                            {{ $statKehadiran['Izin'] + $statKehadiran['Sakit'] + $statKehadiran['Dinas Luar'] }}
                            <span class="text-muted fw-normal" style="font-size: 0.85rem;">Guru</span>
                        </h3>
                    </div>
                    <div class="stat-icon-wrapper-wp bg-warning-subtle text-warning-emphasis">
                        <i class="bi bi-person-dash-fill"></i>
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between text-xs text-muted pt-2 border-top">
                    <span>Dengan keterangan resmi</span>
                    <span class="fw-semibold text-warning-emphasis">
                        {{ $statKehadiran['Sakit'] }} Sakit &middot; {{ $statKehadiran['Dinas Luar'] }} Dinas
                    </span>
                </div>
            </div>
        </div>

        {{-- Card 3: Alpa / Belum Absen --}}
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card-wp h-100">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div>
                        <div class="text-uppercase fw-bold text-muted" style="font-size: 0.72rem; letter-spacing: 0.06em;">
                            Alpa / Belum Absen
                        </div>
                        <h3 class="fw-bold text-dark mt-1 mb-0" style="font-size: 1.85rem; letter-spacing: -0.02em;">
                            {{ $statKehadiran['Alpa'] }}
                            <span class="text-muted fw-normal" style="font-size: 0.85rem;">Guru</span>
                        </h3>
                    </div>
                    <div class="stat-icon-wrapper-wp {{ $statKehadiran['Alpa'] > 0 ? 'bg-danger-subtle text-danger' : 'bg-secondary-subtle text-secondary' }}">
                        <i class="bi bi-person-x-fill"></i>
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between text-xs text-muted pt-2 border-top">
                    <span>Tanpa catatan hadir/izin</span>
                    @if($statKehadiran['Alpa'] > 0)
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Perlu Konfirmasi</span>
                    @else
                        <span class="badge bg-success-subtle text-success border border-success-subtle">Lengkap</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Card 4: Kelas Kosong --}}
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card-wp h-100">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div>
                        <div class="text-uppercase fw-bold text-muted" style="font-size: 0.72rem; letter-spacing: 0.06em;">
                            Kelas Kosong / Belum Diisi
                        </div>
                        <h3 class="fw-bold text-dark mt-1 mb-0" style="font-size: 1.85rem; letter-spacing: -0.02em;">
                            {{ $kelasKosongList->count() }}
                            <span class="text-muted fw-normal" style="font-size: 0.85rem;">Sesi</span>
                        </h3>
                    </div>
                    <div class="stat-icon-wrapper-wp {{ $kelasKosongList->count() > 0 ? 'bg-danger-subtle text-danger' : 'bg-secondary-subtle text-secondary' }}">
                        <i class="bi bi-door-closed-fill"></i>
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between text-xs text-muted pt-2 border-top">
                    <span>Pagi {{ $kelasKosongPagi->count() }} &middot; Siang {{ $kelasKosongSiang->count() }}</span>
                    @if($kelasKosongList->count() > 0)
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Perlu Piket</span>
                    @else
                        <span class="badge bg-success-subtle text-success border border-success-subtle">Lengkap</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Rincian status kehadiran (Hadir / Izin / Sakit / Dinas Luar / Alpa) --}}
    <div class="card border rounded-3 shadow-2xs mb-4">
        <div class="card-header bg-white border-bottom py-3 px-3.5 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h5 class="fw-bold text-dark mb-0" style="font-size: 1rem;">
                    <i class="bi bi-people-fill text-primary me-1.5"></i> Rincian Kehadiran Guru Hari Ini
                </h5>
                <p class="text-muted mb-0 text-xs">
                    Status guru terjadwal KBM hari {{ $hariAktif }} — dari status kehadiran terbaru & izin yang disetujui.
                </p>
            </div>
        </div>
        <div class="card-body p-3.5">
            <div class="row g-2">
                @php
                    $rincianStatus = [
                        ['status' => 'Hadir', 'icon' => 'bi-check-circle-fill'],
                        ['status' => 'Izin', 'icon' => 'bi-calendar-x'],
                        ['status' => 'Sakit', 'icon' => 'bi-thermometer-half'],
                        ['status' => 'Dinas Luar', 'icon' => 'bi-briefcase-fill'],
                        ['status' => 'Alpa', 'icon' => 'bi-person-x-fill'],
                    ];
                    $rincianBadges = [
                        'Hadir' => 'bg-success-subtle text-success border-success-subtle',
                        'Izin' => 'bg-warning-subtle text-warning-emphasis border-warning-subtle',
                        'Sakit' => 'bg-danger-subtle text-danger border-danger-subtle',
                        'Dinas Luar' => 'bg-info-subtle text-info-emphasis border-info-subtle',
                        'Alpa' => 'bg-danger-subtle text-danger border-danger-subtle',
                    ];
                @endphp
                @foreach($rincianStatus as $rs)
                    <div class="col-6 col-md">
                        <div class="chip-status-kehadiran {{ $rincianBadges[$rs['status']] ?? 'bg-light text-dark border' }}">
                            <div class="num">{{ $statKehadiran[$rs['status']] ?? 0 }}</div>
                            <div class="fw-semibold text-xs d-inline-flex align-items-center gap-1">
                                <i class="bi {{ $rs['icon'] }}"></i> {{ $rs['status'] }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ============================================================== --}}
    {{-- 2. PETUGAS & KOORDINATOR PIKET AKTIF HARI INI                    --}}
    {{-- ============================================================== --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="card border rounded-3 shadow-2xs h-100">
                <div class="card-header bg-white border-bottom py-3 px-3.5">
                    <h5 class="fw-bold text-dark mb-0" style="font-size: 0.95rem;">
                        <i class="bi bi-people text-primary me-1.5"></i> Petugas Piket Aktif
                        <span class="badge bg-light text-dark border ms-1">{{ $petugasPiketHariIni->count() }}</span>
                    </h5>
                </div>
                <div class="card-body p-3">
                    @forelse($petugasPiketHariIni as $petugas)
                        <div class="d-flex align-items-center justify-content-between py-2 border-bottom border-light-subtle">
                            <div class="d-flex align-items-center gap-2">
                                <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; font-size: 0.75rem;">
                                    {{ strtoupper(substr($petugas->nama ?? 'P', 0, 1)) }}
                                </div>
                                <div>
                                    <div class="fw-semibold text-dark text-sm">{{ $petugas->nama }}</div>
                                    <div class="text-muted text-2xs">{{ $petugas->nip ? 'NIP: '.$petugas->nip : 'Guru Piket' }}</div>
                                </div>
                            </div>
                            @php $noHpPetugas = $petugas->noHpInternasional(); @endphp
                            @if($noHpPetugas)
                                <a href="https://wa.me/{{ $noHpPetugas }}?text={{ urlencode('Halo '.$petugas->nama.', kami dari Waka Piket mengingatkan Anda bertugas piket hari ini. Terima kasih.') }}"
                                   target="_blank" class="btn btn-sm btn-success rounded-pill text-xs fw-semibold shadow-2xs px-2.5" title="Hubungi via WhatsApp">
                                    <i class="bi bi-whatsapp"></i>
                                </a>
                            @endif
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-calendar-x fs-3 d-block mb-1 text-secondary"></i>
                            <span class="text-xs">Belum ada petugas piket terjadwal untuk hari ini.</span>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="card border rounded-3 shadow-2xs h-100">
                <div class="card-header bg-white border-bottom py-3 px-3.5">
                    <h5 class="fw-bold text-dark mb-0" style="font-size: 0.95rem;">
                        <i class="bi bi-sun text-warning me-1.5"></i> Koordinator Shift Pagi
                        <span class="badge bg-light text-dark border ms-1">{{ $koordinatorPagi->count() }}</span>
                    </h5>
                </div>
                <div class="card-body p-3">
                    @forelse($koordinatorPagi as $koordinator)
                        <div class="d-flex align-items-center justify-content-between py-2 border-bottom border-light-subtle">
                            <div class="d-flex align-items-center gap-2">
                                <div class="rounded-circle bg-warning-subtle text-warning-emphasis d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; font-size: 0.75rem;">
                                    {{ strtoupper(substr($koordinator->nama ?? 'K', 0, 1)) }}
                                </div>
                                <div>
                                    <div class="fw-semibold text-dark text-sm">{{ $koordinator->nama }}</div>
                                    <div class="text-muted text-2xs">{{ $koordinator->nip ? 'NIP: '.$koordinator->nip : 'Koordinator Piket' }}</div>
                                </div>
                            </div>
                            @php $noHpKoord = $koordinator->noHpInternasional(); @endphp
                            @if($noHpKoord)
                                <a href="https://wa.me/{{ $noHpKoord }}?text={{ urlencode('Halo '.$koordinator->nama.', kami dari Waka Piket mengingatkan tugas koordinasi piket shift pagi hari ini. Terima kasih.') }}"
                                   target="_blank" class="btn btn-sm btn-success rounded-pill text-xs fw-semibold shadow-2xs px-2.5" title="Hubungi via WhatsApp">
                                    <i class="bi bi-whatsapp"></i>
                                </a>
                            @endif
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-calendar-x fs-3 d-block mb-1 text-secondary"></i>
                            <span class="text-xs">Belum ada koordinator pagi terjadwal.</span>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="card border rounded-3 shadow-2xs h-100">
                <div class="card-header bg-white border-bottom py-3 px-3.5">
                    <h5 class="fw-bold text-dark mb-0" style="font-size: 0.95rem;">
                        <i class="bi bi-moon-stars text-primary me-1.5"></i> Koordinator Shift Siang
                        <span class="badge bg-light text-dark border ms-1">{{ $koordinatorSiang->count() }}</span>
                    </h5>
                </div>
                <div class="card-body p-3">
                    @forelse($koordinatorSiang as $koordinator)
                        <div class="d-flex align-items-center justify-content-between py-2 border-bottom border-light-subtle">
                            <div class="d-flex align-items-center gap-2">
                                <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; font-size: 0.75rem;">
                                    {{ strtoupper(substr($koordinator->nama ?? 'K', 0, 1)) }}
                                </div>
                                <div>
                                    <div class="fw-semibold text-dark text-sm">{{ $koordinator->nama }}</div>
                                    <div class="text-muted text-2xs">{{ $koordinator->nip ? 'NIP: '.$koordinator->nip : 'Koordinator Piket' }}</div>
                                </div>
                            </div>
                            @php $noHpKoord = $koordinator->noHpInternasional(); @endphp
                            @if($noHpKoord)
                                <a href="https://wa.me/{{ $noHpKoord }}?text={{ urlencode('Halo '.$koordinator->nama.', kami dari Waka Piket mengingatkan tugas koordinasi piket shift siang hari ini. Terima kasih.') }}"
                                   target="_blank" class="btn btn-sm btn-success rounded-pill text-xs fw-semibold shadow-2xs px-2.5" title="Hubungi via WhatsApp">
                                    <i class="bi bi-whatsapp"></i>
                                </a>
                            @endif
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-calendar-x fs-3 d-block mb-1 text-secondary"></i>
                            <span class="text-xs">Belum ada koordinator siang terjadwal.</span>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- ============================================================== --}}
    {{-- 3. PANTAUAN KELAS KOSONG PAGI / SIANG                           --}}
    {{-- ============================================================== --}}
    @php
        $judulKelasKosong = [
            'pagi' => ['label' => 'Shift Pagi', 'icon' => 'bi-sun', 'data' => $kelasKosongPagi],
            'siang' => ['label' => 'Shift Siang', 'icon' => 'bi-moon-stars', 'data' => $kelasKosongSiang],
        ];
    @endphp

    @foreach($judulKelasKosong as $key => $blok)
        <div class="card border rounded-3 shadow-2xs mb-4">
            <div class="card-header bg-white border-bottom py-3 px-3.5 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h5 class="fw-bold text-dark mb-0" style="font-size: 1rem;">
                        <i class="bi {{ $blok['icon'] }} text-warning me-1.5"></i> Kelas Kosong {{ $blok['label'] }}
                    </h5>
                    <p class="text-muted mb-0 text-xs">
                        Sesi KBM yang terjadwal tapi Jurnal KBM-nya belum diisi guru — siap koordinasi dengan tim piket.
                    </p>
                </div>
                <span class="badge {{ $blok['data']->count() > 0 ? 'bg-danger-subtle text-danger border-danger-subtle' : 'bg-success-subtle text-success border-success-subtle' }} border rounded-pill text-xs px-2.5 py-1">
                    {{ $blok['data']->count() }} Sesi Belum Diisi
                </span>
            </div>

            <div class="card-body p-0">
                @if($blok['data']->isEmpty())
                    <div class="text-center py-5 px-3">
                        <div class="rounded-circle bg-success-subtle text-success d-inline-flex align-items-center justify-content-center mb-2" style="width: 46px; height: 46px;">
                            <i class="bi bi-check2-all fs-4"></i>
                        </div>
                        <h6 class="fw-bold text-dark mb-1">Semua sesi {{ strtolower($blok['label']) }} sudah terisi.</h6>
                        <p class="text-muted text-xs mb-0">Tidak ada kelas kosong pada shift ini.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-custom-wp table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Jam / Sesi</th>
                                    <th>Kelas & Mapel</th>
                                    <th>Guru Pengajar</th>
                                    <th class="text-end">Aksi Cepat</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($blok['data'] as $item)
                                    <tr>
                                        <td>
                                            <span class="badge bg-light text-dark border fw-bold text-xs">
                                                Jam Ke-{{ $item->jam?->jam_ke ?? '-' }}
                                            </span>
                                            <div class="text-muted text-2xs mt-0.5">
                                                {{ $item->jam ? \Carbon\Carbon::parse($item->jam->jam_mulai)->format('H:i') . ' - ' . \Carbon\Carbon::parse($item->jam->jam_selesai)->format('H:i') : '' }}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark">{{ $item->kelas?->nama_kelas ?? '-' }}</div>
                                            <div class="text-muted text-xs text-truncate" style="max-width: 140px;" title="{{ $item->mapel?->nama_mapel }}">
                                                {{ $item->mapel?->nama_mapel ?? '-' }}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark text-xs">{{ $item->guru?->nama ?? 'Guru Tidak Ditemukan' }}</div>
                                            @if($item->izin)
                                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle text-2xs px-1.5 py-0.5 rounded-pill">
                                                    <i class="bi bi-exclamation-triangle"></i> Izin
                                                </span>
                                            @else
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle text-2xs px-1.5 py-0.5 rounded-pill">
                                                    Belum Hadir
                                                </span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            @if($item->waUrl)
                                                <a href="{{ $item->waUrl }}" target="_blank" class="btn btn-sm btn-success rounded-pill px-2.5 py-1 text-xs d-inline-flex align-items-center gap-1 shadow-2xs fw-semibold" title="Kirim pesan WhatsApp pengingat">
                                                    <i class="bi bi-whatsapp"></i> Ingatkan
                                                </a>
                                            @else
                                                <span class="text-muted text-2xs">Tanpa No. WA</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endforeach

    {{-- ============================================================== --}}
    {{-- 4. REKAP HARIAN (QUICK LINK)                                    --}}
    {{-- ============================================================== --}}
    <div class="card border rounded-3 shadow-2xs">
        <div class="card-header bg-white border-bottom py-3 px-3.5 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h5 class="fw-bold text-dark mb-0" style="font-size: 1rem;">
                    <i class="bi bi-clipboard-check text-primary me-1.5"></i> Rekap Harian Piket
                </h5>
                <p class="text-muted mb-0 text-xs">
                    Review catatan koordinator shift pagi/siang dan validasi rekap harian.
                </p>
            </div>
            <div class="d-flex align-items-center gap-2">
                @if($rekapHariIni)
                    <span class="badge {{ $rekapHariIni->status_badge }} px-2.5 py-1 rounded-pill text-xs">
                        <i class="bi {{ $rekapHariIni->isValidated() ? 'bi-check-circle' : 'bi-hourglass-split' }} me-1"></i>
                        Hari ini: {{ $rekapHariIni->status_label }}
                    </span>
                @else
                    <span class="badge bg-secondary-subtle text-secondary border px-2.5 py-1 rounded-pill text-xs">
                        <i class="bi bi-file-earmark-plus me-1"></i> Belum ada rekap hari ini
                    </span>
                @endif
                @if($totalRekapBelumValidasi > 0)
                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2.5 py-1 rounded-pill text-xs">
                        {{ $totalRekapBelumValidasi }} rekap menunggu validasi
                    </span>
                @endif
                <a href="{{ route('waka-piket.rekap-harian') }}" class="btn btn-sm btn-outline-primary rounded-3 text-xs fw-semibold">
                    Buka Rekap &rarr;
                </a>
            </div>
        </div>
    </div>

</div>
@endsection