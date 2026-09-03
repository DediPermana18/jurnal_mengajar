@extends('layouts.app')

@section('title', 'Dashboard Guru Piket - WebJournal')

@section('content')
<div class="container-fluid px-0">

    {{-- Page Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3 gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1" style="font-size: 1.4rem; letter-spacing: -0.02em;">
                Dashboard Guru Piket
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.85rem;">
                Selamat datang, {{ auth()->user()->nama ?? auth()->user()->username }}. Kelola presensi harian sekolah dari sini.
            </p>
        </div>
        <span class="text-muted small"><i class="bi bi-calendar3 me-1"></i>{{ now()->translatedFormat('l, d F Y') }}</span>
    </div>

    {{-- ============================================================== --}}
    {{-- 1. STATS CARDS (4 sejajar, col-md-3)                          --}}
    {{-- ============================================================== --}}
    <div class="row g-3 mb-3">
        {{-- Card 1: Siswa Tidak Hadir --}}
        <div class="col-6 col-md-3">
            <div class="stat-card-custom position-relative">
                <div class="rounded-3 d-flex align-items-center justify-content-center bg-danger-subtle text-danger position-absolute"
                     style="top: 1rem; right: 1rem; width: 34px; height: 34px;">
                    <i class="bi bi-calendar-x-fill"></i>
                </div>
                <div class="stat-card-title mb-2 pe-5">Siswa Tidak Hadir</div>
                <div class="text-dark fw-bolder mb-1" style="font-size: 1.6rem; line-height: 1.1;">{{ number_format($siswaTidakHadir) }}</div>
                <div class="text-muted small">Sakit / Izin / Alpha &bull; Hari ini</div>
            </div>
        </div>

        {{-- Card 2: Guru Tidak Hadir / Izin --}}
        <div class="col-6 col-md-3">
            <div class="stat-card-custom position-relative">
                <div class="rounded-3 d-flex align-items-center justify-content-center bg-warning-subtle text-warning-emphasis position-absolute"
                     style="top: 1rem; right: 1rem; width: 34px; height: 34px;">
                    <i class="bi bi-person-x-fill"></i>
                </div>
                <div class="stat-card-title mb-2 pe-5">Guru Tidak Hadir / Izin</div>
                <div class="text-dark fw-bolder mb-1" style="font-size: 1.6rem; line-height: 1.1;">{{ number_format($guruIzinHariIni) }}</div>
                <div class="text-muted small">Mengajukan Izin &bull; Hari ini</div>
            </div>
        </div>

        {{-- Card 3: Total Dispensasi --}}
        <div class="col-6 col-md-3">
            <div class="stat-card-custom position-relative">
                <div class="rounded-3 d-flex align-items-center justify-content-center bg-primary-subtle text-primary position-absolute"
                     style="top: 1rem; right: 1rem; width: 34px; height: 34px;">
                    <i class="bi bi-door-open-fill"></i>
                </div>
                <div class="stat-card-title mb-2 pe-5">Total Dispensasi</div>
                <div class="text-dark fw-bolder mb-1" style="font-size: 1.6rem; line-height: 1.1;">{{ number_format($dispensasiHariIni) }}</div>
                <div class="text-muted small">Keluar / Masuk Kelas &bull; Hari ini</div>
            </div>
        </div>

        {{-- Card 4: Kelas KBM Belum Terisi --}}
        <div class="col-6 col-md-3">
            <div class="stat-card-custom position-relative">
                <div class="rounded-3 d-flex align-items-center justify-content-center bg-info-subtle text-info position-absolute"
                     style="top: 1rem; right: 1rem; width: 34px; height: 34px;">
                    <i class="bi bi-journal-x"></i>
                </div>
                <div class="stat-card-title mb-2 pe-5">Kelas KBM Belum Terisi</div>
                <div class="text-dark fw-bolder mb-1" style="font-size: 1.6rem; line-height: 1.1;">{{ number_format($kelasKbmBelumTerisi) }}</div>
                <div class="text-muted small">Jurnal belum diisi &bull; Hari ini</div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">

            {{-- ============================================================== --}}
            {{-- 2. QUICK ACTION BUTTONS (1 baris horizontal)                 --}}
            {{-- ============================================================== --}}
            <div class="mb-3">
                <h6 class="fw-bold text-muted mb-2 text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.05em;">
                    <i class="bi bi-lightning-fill text-warning me-1"></i> Aksi Cepat
                </h6>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('piket.dispensasi.create') }}" class="btn btn-primary fw-semibold d-inline-flex align-items-center gap-2">
                        <i class="bi bi-plus-circle-fill"></i> Buat Dispensasi
                    </a>
                    <a href="{{ route('piket.presensi-siswa') }}" class="btn btn-light border fw-semibold d-inline-flex align-items-center gap-2">
                        <i class="bi bi-clipboard-check-fill text-success"></i> Presensi Siswa Harian
                    </a>
                    <a href="{{ route('piket.izin.index') }}" class="btn btn-light border fw-semibold d-inline-flex align-items-center gap-2 position-relative">
                        <i class="bi bi-inbox-fill text-warning"></i> Approval Izin Guru
                        @if($izinPendingPiket > 0)
                            <span class="badge bg-danger text-white rounded-pill fw-bold ms-1">{{ $izinPendingPiket }}</span>
                        @endif
                    </a>
                </div>
            </div>

            {{-- ============================================================== --}}
            {{-- 3. RECENT ACTIVITY: Dispensasi Hari Ini                       --}}
            {{-- ============================================================== --}}
            <div class="table-card-custom">
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="bi bi-door-open me-1 text-primary"></i> Pengajuan Dispensasi Hari Ini
                    </h6>
                    <a href="{{ route('piket.dispensasi.index') }}" class="btn btn-sm btn-outline-primary rounded-3 fw-semibold">
                        Lihat Semua <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>

                @if($dispensasiTerbaru->isNotEmpty())
                    <div class="table-responsive w-full overflow-x-auto rounded-lg">
                        <table class="table table-custom align-middle mb-0 min-w-full">
                            <thead>
                                <tr>
                                    <th class="whitespace-nowrap">SISWA</th>
                                    <th class="whitespace-nowrap">KELAS</th>
                                    <th class="whitespace-nowrap">TIPE</th>
                                    <th class="whitespace-nowrap text-center">STATUS</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($dispensasiTerbaru as $dispen)
                                    <tr>
                                        <td class="whitespace-nowrap fw-semibold text-dark">{{ $dispen->siswa?->nama ?? '-' }}</td>
                                        <td class="whitespace-nowrap text-muted">{{ $dispen->siswa?->kelas?->nama_kelas ?? '-' }}</td>
                                        <td class="whitespace-nowrap">
                                            <span class="badge {{ $dispen->isTipeMasuk() ? 'bg-info-subtle text-info' : 'bg-secondary-subtle text-secondary' }} border rounded-pill px-2 py-1">
                                                {{ $dispen->tipe_dispen_label }}
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap text-center">
                                            <span class="badge {{ $dispen->status_badge }} rounded-pill px-2 py-2">{{ $dispen->status_label }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-door-closed fs-1 d-block mb-2 opacity-50"></i>
                        Tidak ada pengajuan dispensasi hari ini.
                    </div>
                @endif
            </div>
        </div>

        {{-- ============================================================== --}}
        {{-- SIDE: Daftar Guru Izin Hari Ini                               --}}
        {{-- ============================================================== --}}
        <div class="col-lg-4">
            <div class="table-card-custom h-100">
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="bi bi-person-x me-1 text-warning"></i> Daftar Guru Izin Hari Ini
                    </h6>
                    <a href="{{ route('piket.izin.index') }}" class="btn btn-sm btn-outline-warning rounded-3 fw-semibold">
                        Lihat Semua <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>

                @if($izinGuruHariIni->isNotEmpty())
                    <ul class="list-unstyled mb-0 d-flex flex-column gap-2">
                        @foreach($izinGuruHariIni as $izin)
                            <li class="d-flex align-items-center gap-2 py-2 border-bottom">
                                <div class="rounded-3 d-flex align-items-center justify-content-center bg-warning-subtle text-warning-emphasis flex-shrink-0" style="width: 34px; height: 34px;">
                                    <i class="bi bi-person-fill-dash"></i>
                                </div>
                                <div class="min-w-0 flex-grow-1">
                                    <div class="fw-semibold text-dark text-truncate" style="font-size: 0.9rem;">{{ $izin->user?->nama ?? '-' }}</div>
                                    <div class="text-muted small text-truncate">{{ $izin->kategori_izin_label }}</div>
                                </div>
                                <span class="badge {{ $izin->status_badge }} rounded-pill px-2 py-1 flex-shrink-0">{{ $izin->status_label }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-person-check fs-1 d-block mb-2 opacity-50"></i>
                        Semua guru hadir / tidak ada izin hari ini.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
