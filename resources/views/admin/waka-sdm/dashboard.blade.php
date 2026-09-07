@extends('layouts.app')

@section('title', 'Dashboard Waka SDM - WebJournal')

@push('styles')
<style>
    .stat-card-sdm {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        padding: 1.25rem;
        transition: all 0.25s ease;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        position: relative;
        overflow: hidden;
    }
    .stat-card-sdm:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08);
        border-color: #cbd5e1;
    }
    .stat-icon-wrapper {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
    }
    .actionable-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        transition: all 0.2s ease;
    }
    .table-custom-sdm th {
        background: #f8fafc;
        font-size: 0.76rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        font-weight: 700;
        padding: 0.85rem 1rem;
        border-bottom: 1px solid #e2e8f0;
    }
    .table-custom-sdm td {
        padding: 0.85rem 1rem;
        vertical-align: middle;
        font-size: 0.86rem;
        border-bottom: 1px solid #f1f5f9;
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
                    <i class="bi bi-people-fill me-1"></i> Kepegawaian & SDM
                </span>
            </div>
            <h2 class="fw-black text-dark mb-1" style="font-weight: 900; font-size: 1.55rem; letter-spacing: -0.02em;">
                Dashboard Waka SDM
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.875rem;">
                Monitoring kehadiran guru, pelacakan izin/cuti harian, dan kedisiplinan mengajar real-time.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="{{ route('waka-sdm.rekap-izin') }}" class="btn btn-light border rounded-3 fw-semibold text-sm d-flex align-items-center gap-1.5 shadow-2xs">
                <i class="bi bi-calendar2-check text-primary"></i> Rekap Izin & Cuti
            </a>
            <a href="{{ route('waka-sdm.rekap-presensi-guru') }}" class="btn btn-primary rounded-3 fw-semibold text-sm d-flex align-items-center gap-1.5 shadow-sm">
                <i class="bi bi-bar-chart-line"></i> Rekap Performa KBM
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

    {{-- ============================================================== --}}
    {{-- 1. STAT CARDS WIDGETS                                          --}}
    {{-- ============================================================== --}}
    <div class="row g-3 mb-4">
        {{-- Card 1: Guru Hadir Hari Ini --}}
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card-sdm h-100">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div>
                        <div class="text-uppercase fw-bold text-muted" style="font-size: 0.72rem; letter-spacing: 0.06em;">
                            Guru Hadir Hari Ini
                        </div>
                        <h3 class="fw-bold text-dark mt-1 mb-0" style="font-size: 1.85rem; letter-spacing: -0.02em;">
                            {{ $totalGuruHadirHariIni }}
                            <span class="text-muted fw-normal" style="font-size: 0.85rem;">/ {{ $totalGuruTerjadwalHariIni }} Terjadwal</span>
                        </h3>
                    </div>
                    <div class="stat-icon-wrapper bg-success-subtle text-success">
                        <i class="bi bi-person-check-fill"></i>
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between text-xs text-muted pt-2 border-top">
                    <span>Total Guru Aktif: <strong>{{ $totalGuruAktif }}</strong></span>
                    <span class="text-success fw-semibold">
                        @if($totalGuruTerjadwalHariIni > 0)
                            {{ round(($totalGuruHadirHariIni / $totalGuruTerjadwalHariIni) * 100) }}% Hadir
                        @else
                            100%
                        @endif
                    </span>
                </div>
            </div>
        </div>

        {{-- Card 2: Guru Izin / Sakit / Cuti Hari Ini --}}
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card-sdm h-100">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div>
                        <div class="text-uppercase fw-bold text-muted" style="font-size: 0.72rem; letter-spacing: 0.06em;">
                            Guru Izin / Sakit / Cuti
                        </div>
                        <h3 class="fw-bold text-dark mt-1 mb-0" style="font-size: 1.85rem; letter-spacing: -0.02em;">
                            {{ $totalGuruIzinHariIni }}
                            <span class="text-muted fw-normal" style="font-size: 0.85rem;">Guru</span>
                        </h3>
                    </div>
                    <div class="stat-icon-wrapper bg-warning-subtle text-warning-emphasis">
                        <i class="bi bi-person-dash-fill"></i>
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between text-xs text-muted pt-2 border-top">
                    <span>Pengajuan Aktif Hari Ini</span>
                    <a href="{{ route('waka-sdm.rekap-izin', ['tanggal' => $todayStr]) }}" class="text-decoration-none fw-semibold text-warning-emphasis">
                        Lihat Detail &rarr;
                    </a>
                </div>
            </div>
        </div>

        {{-- Card 3: Total Kelas Kosong --}}
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card-sdm h-100">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div>
                        <div class="text-uppercase fw-bold text-muted" style="font-size: 0.72rem; letter-spacing: 0.06em;">
                            Kelas Kosong / Belum Diisi
                        </div>
                        <h3 class="fw-bold text-dark mt-1 mb-0" style="font-size: 1.85rem; letter-spacing: -0.02em;">
                            {{ $totalKelasKosong }}
                            <span class="text-muted fw-normal" style="font-size: 0.85rem;">Kelas</span>
                        </h3>
                    </div>
                    <div class="stat-icon-wrapper {{ $totalKelasKosong > 0 ? 'bg-danger-subtle text-danger' : 'bg-secondary-subtle text-secondary' }}">
                        <i class="bi bi-door-closed-fill"></i>
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between text-xs text-muted pt-2 border-top">
                    <span>{{ $sesiKosongHariIni }} Sesi KBM Belum Terisi</span>
                    @if($totalKelasKosong > 0)
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Perlu Piket</span>
                    @else
                        <span class="badge bg-success-subtle text-success border border-success-subtle">Lengkap</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Card 4: Kehadiran Guru Bulan Ini --}}
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card-sdm h-100">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div>
                        <div class="text-uppercase fw-bold text-muted" style="font-size: 0.72rem; letter-spacing: 0.06em;">
                            Kehadiran Bulan {{ \Carbon\Carbon::now()->translatedFormat('F') }}
                        </div>
                        <h3 class="fw-bold text-dark mt-1 mb-0" style="font-size: 1.85rem; letter-spacing: -0.02em;">
                            {{ $persentaseKehadiranBulanIni }}%
                        </h3>
                    </div>
                    <div class="stat-icon-wrapper bg-primary-subtle text-primary">
                        <i class="bi bi-award-fill"></i>
                    </div>
                </div>
                <div class="progress mb-2" style="height: 6px;">
                    <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $persentaseKehadiranBulanIni }}%" aria-valuenow="{{ $persentaseKehadiranBulanIni }}" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <div class="d-flex align-items-center justify-content-between text-xs text-muted">
                    <span>Target: 95.0%</span>
                    <span class="fw-semibold text-primary">Kinerja Baik</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ============================================================== --}}
    {{-- 2. REAL-TIME ACTIONABLE MONITORING TABLES                      --}}
    {{-- ============================================================== --}}
    <div class="row g-3 mb-4">
        
        {{-- CARD 1: Guru Tidak Hadir / Izin Hari Ini --}}
        <div class="col-12 col-xl-6">
            <div class="actionable-card h-100 d-flex flex-column">
                <div class="card-header bg-white border-bottom py-3 px-3.5 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-circle p-1 d-inline-flex">
                                <i class="bi bi-person-x fs-6"></i>
                            </span>
                            <h5 class="fw-bold text-dark mb-0" style="font-size: 1rem;">
                                Guru Tidak Hadir / Izin Hari Ini
                            </h5>
                        </div>
                        <p class="text-muted mb-0 text-xs mt-1">
                            Daftar guru yang berhalangan hadir dan status penugasan guru pengganti.
                        </p>
                    </div>
                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill text-xs px-2.5 py-1">
                        {{ $guruIzinHariIniList->count() }} Guru
                    </span>
                </div>

                <div class="card-body p-0 flex-grow-1">
                    @if($guruIzinHariIniList->isEmpty())
                        <div class="text-center py-5 px-3">
                            <div class="rounded-circle bg-success-subtle text-success d-inline-flex align-items-center justify-content-center mb-2" style="width: 46px; height: 46px;">
                                <i class="bi bi-check2-circle fs-4"></i>
                            </div>
                            <h6 class="fw-bold text-dark mb-1">Semua guru terjadwal hadir hari ini.</h6>
                            <p class="text-muted text-xs mb-0">Tidak ada pengajuan izin, sakit, atau dinas luar yang aktif untuk hari ini.</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-custom-sdm table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Nama Guru</th>
                                        <th>Status</th>
                                        <th>Alasan</th>
                                        <th>Guru Pengganti</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($guruIzinHariIniList as $izin)
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="rounded-circle bg-warning-subtle text-warning-emphasis d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; font-size: 0.78rem;">
                                                        {{ strtoupper(substr($izin->user?->nama ?? 'G', 0, 1)) }}
                                                    </div>
                                                    <div>
                                                        <div class="fw-semibold text-dark">{{ $izin->user?->nama ?? 'Guru Tidak Ditemukan' }}</div>
                                                        <div class="text-muted text-2xs">{{ $izin->user?->nip ? 'NIP: ' . $izin->user->nip : 'Non-NIP' }}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark border border-secondary-subtle px-2 py-1 rounded-pill text-xs fw-semibold">
                                                    {{ $izin->kategori_izin_label ?? ucfirst(str_replace('_', ' ', $izin->kategori_izin ?? 'Izin')) }}
                                                </span>
                                            </td>
                                            <td>
                                                <div class="text-truncate text-dark text-xs" style="max-width: 170px;" title="{{ $izin->alasan ?? $izin->keterangan }}">
                                                    {{ $izin->alasan ?? $izin->keterangan ?? '-' }}
                                                </div>
                                            </td>
                                            <td>
                                                @if($izin->guru_pengganti)
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill text-xs" title="Guru Pengganti / Cover">
                                                        <i class="bi bi-person-check-fill me-1"></i> {{ $izin->guru_pengganti->nama }}
                                                    </span>
                                                @elseif($izin->approverPiket)
                                                    <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1 rounded-pill text-xs" title="Dicatat oleh Piket">
                                                        <i class="bi bi-shield-check me-1"></i> Piket: {{ $izin->approverPiket->nama }}
                                                    </span>
                                                @else
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 rounded-pill text-xs">
                                                        <i class="bi bi-exclamation-circle me-1"></i> Belum Cover
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <div class="card-footer bg-white border-top py-2.5 px-3.5 d-flex align-items-center justify-content-between text-xs">
                    <span class="text-muted">Diperbarui real-time dari database izin</span>
                    <a href="{{ route('waka-sdm.rekap-izin') }}" class="text-primary text-decoration-none fw-semibold">
                        Kelola Rekap Izin &rarr;
                    </a>
                </div>
            </div>
        </div>

        {{-- CARD 2: Pantauan Kelas Kosong (Jam Ini / Hari Ini) --}}
        <div class="col-12 col-xl-6">
            <div class="actionable-card h-100 d-flex flex-column">
                <div class="card-header bg-white border-bottom py-3 px-3.5 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-circle p-1 d-inline-flex">
                                <i class="bi bi-door-closed fs-6"></i>
                            </span>
                            <h5 class="fw-bold text-dark mb-0" style="font-size: 1rem;">
                                Pantauan Kelas Kosong (Jam Ini)
                            </h5>
                        </div>
                        <p class="text-muted mb-0 text-xs mt-1">
                            Sesi KBM yang sedang berlangsung/terjadwal tapi Jurnal KBM-nya belum diisi guru.
                        </p>
                    </div>
                    <span class="badge {{ $kelasKosongHariIniList->count() > 0 ? 'bg-danger-subtle text-danger border-danger-subtle' : 'bg-success-subtle text-success border-success-subtle' }} border rounded-pill text-xs px-2.5 py-1">
                        {{ $kelasKosongHariIniList->count() }} Sesi Belum Diisi
                    </span>
                </div>

                <div class="card-body p-0 flex-grow-1">
                    @if($kelasKosongHariIniList->isEmpty())
                        <div class="text-center py-5 px-3">
                            <div class="rounded-circle bg-success-subtle text-success d-inline-flex align-items-center justify-content-center mb-2" style="width: 46px; height: 46px;">
                                <i class="bi bi-check2-all fs-4"></i>
                            </div>
                            <h6 class="fw-bold text-dark mb-1">Semua sesi KBM hari ini sudah terisi dengan baik.</h6>
                            <p class="text-muted text-xs mb-0">Tidak ada kelas kosong atau jurnal mengajar yang terlewatkan hari ini.</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-custom-sdm table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Jam / Sesi</th>
                                        <th>Kelas & Mapel</th>
                                        <th>Guru Pengajar</th>
                                        <th class="text-end">Aksi Cepat</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($kelasKosongHariIniList as $item)
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
                                                    <a href="{{ route('waka-sdm.rekap-izin') }}" class="btn btn-sm btn-light border rounded-pill px-2.5 py-1 text-xs text-secondary fw-semibold" title="Buka manajemen izin / piket">
                                                        <i class="bi bi-shield-fill-exclamation text-warning me-1"></i> Hubungi Piket
                                                    </a>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <div class="card-footer bg-white border-top py-2.5 px-3.5 d-flex align-items-center justify-content-between text-xs">
                    <span class="text-muted">Kirim pengingat atau koordinasikan dengan Tim Piket</span>
                    <a href="#tabelMonitoringLengkap" class="text-primary text-decoration-none fw-semibold">
                        Lihat Semua Sesi &darr;
                    </a>
                </div>
            </div>
        </div>

    </div>

    {{-- ============================================================== --}}
    {{-- 3. TABEL MONITORING KBM & PRESENSI GURU HARI INI               --}}
    {{-- ============================================================== --}}
    <div id="tabelMonitoringLengkap" class="card border rounded-3 shadow-2xs mb-4">
        <div class="card-header bg-white border-bottom py-3 px-3.5 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h5 class="fw-bold text-dark mb-0" style="font-size: 1rem;">
                    <i class="bi bi-broadcast text-danger me-1.5"></i> Monitoring Status KBM & Guru Hari Ini
                </h5>
                <p class="text-muted mb-0 text-xs">
                    Pelacakan sesi jadwal KBM, keterisian jurnal mengajar, dan penugasan guru pengganti hari ini.
                </p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-light text-dark border text-xs">
                    Total: {{ $monitoringKbmHariIni->count() }} Sesi
                </span>
                <span class="badge bg-success-subtle text-success border border-success-subtle text-xs">
                    {{ $sesiTerisiHariIni }} Terisi
                </span>
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle text-xs">
                    {{ $sesiKosongHariIni }} Belum Terisi
                </span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-custom-sdm table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 50px;" class="text-center">No</th>
                        <th>Jam / Waktu</th>
                        <th>Kelas</th>
                        <th>Mata Pelajaran</th>
                        <th>Guru Pengajar</th>
                        <th>Status Guru</th>
                        <th>Status Jurnal</th>
                        <th>Guru Pengganti</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($monitoringKbmHariIni as $index => $item)
                        <tr>
                            <td class="text-center text-muted fw-semibold">{{ $index + 1 }}</td>
                            <td>
                                <span class="badge bg-light text-dark border fw-bold text-xs">
                                    Jam Ke-{{ $item->jam?->jam_ke ?? '-' }}
                                </span>
                                <div class="text-muted text-2xs mt-0.5">
                                    {{ $item->jam ? \Carbon\Carbon::parse($item->jam->jam_mulai)->format('H:i') . ' - ' . \Carbon\Carbon::parse($item->jam->jam_selesai)->format('H:i') : '' }}
                                </div>
                            </td>
                            <td>
                                <span class="fw-bold text-dark">{{ $item->kelas?->nama_kelas ?? '-' }}</span>
                            </td>
                            <td>
                                <span class="fw-semibold text-dark">{{ $item->mapel?->nama_mapel ?? '-' }}</span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold" style="width: 30px; height: 30px; font-size: 0.75rem;">
                                        {{ strtoupper(substr($item->guru?->nama ?? 'G', 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="fw-semibold text-dark">{{ $item->guru?->nama ?? 'Belum Ditentukan' }}</div>
                                        <div class="text-muted text-2xs">{{ $item->guru?->nip ? 'NIP: ' . $item->guru->nip : 'Non-NIP' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($item->izin)
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1 rounded-pill text-xs">
                                        <i class="bi bi-exclamation-triangle me-1"></i> Izin ({{ $item->izin->kategori_izin_label ?? 'Izin' }})
                                    </span>
                                @elseif($item->statusKehadiran === 'Hadir')
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill text-xs">
                                        <i class="bi bi-check-circle me-1"></i> Hadir
                                    </span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 rounded-pill text-xs">
                                        {{ $item->statusKehadiran }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $item->statusInfo['badge_class'] ?? 'bg-light text-dark' }} px-2 py-1 rounded-pill text-xs" style="{{ $item->statusInfo['style'] ?? '' }}">
                                    <i class="bi {{ $item->statusInfo['icon'] ?? 'bi-circle' }} me-1"></i>
                                    {{ $item->statusInfo['label'] ?? 'Belum Terisi' }}
                                </span>
                            </td>
                            <td>
                                @if($item->guruPengganti)
                                    <span class="badge bg-info-subtle text-info border border-info-subtle text-xs">
                                        <i class="bi bi-person-fill-gear me-1"></i> {{ $item->guruPengganti->nama }}
                                    </span>
                                @else
                                    <span class="text-muted text-xs">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                <i class="bi bi-calendar-x fs-2 d-block mb-1 text-secondary"></i>
                                Tidak ada jadwal KBM yang aktif untuk hari ini ({{ $hariIniStr }}).
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ============================================================== --}}
    {{-- 4. DAFTAR PERIZINAN & CUTI TERBARU                             --}}
    {{-- ============================================================== --}}
    <div class="card border rounded-3 shadow-2xs">
        <div class="card-header bg-white border-bottom py-3 px-3.5 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h5 class="fw-bold text-dark mb-0" style="font-size: 1rem;">
                    <i class="bi bi-clipboard2-check text-primary me-1.5"></i> Pengajuan Izin & Cuti Guru Terbaru
                </h5>
                <p class="text-muted mb-0 text-xs">
                    Daftar riwayat permohonan izin, sakit, dan dinas luar terbaru oleh dewan guru.
                </p>
            </div>
            <a href="{{ route('waka-sdm.rekap-izin') }}" class="btn btn-sm btn-outline-primary rounded-3 text-xs fw-semibold">
                Lihat Semua Rekap &rarr;
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-custom-sdm table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 50px;" class="text-center">No</th>
                        <th>Nama Guru</th>
                        <th>Tanggal Izin</th>
                        <th>Jenis Izin</th>
                        <th>Alasan / Keterangan</th>
                        <th>Tugas Siswa</th>
                        <th>Status Approval</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentIzin as $idx => $izin)
                        <tr>
                            <td class="text-center text-muted fw-semibold">{{ $idx + 1 }}</td>
                            <td>
                                <div class="fw-bold text-dark">{{ $izin->user?->nama ?? 'Guru Tidak Ditemukan' }}</div>
                                <div class="text-muted text-2xs">{{ $izin->user?->nip ? 'NIP: ' . $izin->user->nip : 'Non-NIP' }}</div>
                            </td>
                            <td>
                                <span class="fw-semibold text-dark">
                                    {{ $izin->tanggal ? $izin->tanggal->translatedFormat('d F Y') : '-' }}
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border px-2.5 py-1 rounded-pill text-xs fw-semibold">
                                    {{ $izin->kategori_izin_label ?? ucfirst(str_replace('_', ' ', $izin->kategori_izin ?? 'Izin')) }}
                                </span>
                            </td>
                            <td>
                                <div class="text-truncate text-dark text-xs" style="max-width: 250px;" title="{{ $izin->alasan ?? $izin->keterangan }}">
                                    {{ $izin->alasan ?? $izin->keterangan ?? '-' }}
                                </div>
                            </td>
                            <td>
                                <div class="text-truncate text-muted text-xs" style="max-width: 200px;" title="{{ $izin->tugas_siswa }}">
                                    {{ $izin->tugas_siswa ?? '-' }}
                                </div>
                            </td>
                            <td>
                                <span class="badge {{ $izin->status_badge }} px-2.5 py-1 rounded-pill text-xs">
                                    {{ $izin->status_label }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-1 text-secondary"></i>
                                Belum ada riwayat pengajuan izin guru.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
