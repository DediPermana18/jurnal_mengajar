@extends('layouts.app')

@section('title', 'Rekap Presensi & Jurnal KBM Guru - Waka SDM')

@push('styles')
<style>
    .stat-card-kbm {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 0.875rem;
        padding: 1.15rem 1.25rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    }
    .table-rekap-kbm th {
        background: #f8fafc;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        font-weight: 700;
        padding: 0.85rem 0.9rem;
        border-bottom: 1px solid #e2e8f0;
    }
    .table-rekap-kbm td {
        padding: 0.85rem 0.9rem;
        vertical-align: middle;
        font-size: 0.86rem;
        border-bottom: 1px solid #f1f5f9;
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">

    {{-- Page Header --}}
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="{{ route('waka-sdm.dashboard') }}" class="text-decoration-none text-muted text-xs d-flex align-items-center gap-1">
                    <i class="bi bi-arrow-left"></i> Dashboard Waka SDM
                </a>
                <span class="text-muted text-xs">/</span>
                <span class="text-xs fw-semibold text-primary">Rekap Presensi & KBM</span>
            </div>
            <h2 class="fw-black text-dark mb-1" style="font-weight: 900; font-size: 1.5rem; letter-spacing: -0.02em;">
                Rekapitulasi Presensi & KBM Guru
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.875rem;">
                Evaluasi performa Jam Pelajaran (JP Wajib vs JP Terealisasi) dan kedisiplinan pengisian jurnal mengajar bulanan.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            {{-- Tombol Export Excel --}}
            <a href="{{ route('waka-sdm.export-excel', request()->query()) }}" class="btn btn-success btn-sm rounded-3 fw-semibold text-xs d-flex align-items-center gap-1.5 shadow-2xs">
                <i class="bi bi-file-earmark-excel-fill"></i> Export Excel (.xls)
            </a>
            {{-- Tombol Print PDF --}}
            <a href="{{ route('waka-sdm.print-presensi', request()->query()) }}" target="_blank" class="btn btn-primary btn-sm rounded-3 fw-semibold text-xs d-flex align-items-center gap-1.5 shadow-2xs">
                <i class="bi bi-printer-fill"></i> Print PDF / Cetak Laporan
            </a>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="card border rounded-3 shadow-2xs mb-4">
        <div class="card-body p-3.5">
            <form method="GET" action="{{ route('waka-sdm.rekap-presensi-guru') }}">
                <div class="row g-2.5 align-items-center">
                    {{-- Search Input --}}
                    <div class="col-12 col-md-5">
                        <div class="position-relative">
                            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted" style="font-size: 0.85rem;"></i>
                            <input type="text" name="search" class="form-control form-control-sm rounded-3 ps-5 bg-light" placeholder="Cari nama guru atau NIP...">
                        </div>
                    </div>

                    {{-- Filter Bulan --}}
                    <div class="col-6 col-md-2">
                        <select name="bulan" class="form-select form-select-sm rounded-3 bg-light" onchange="this.form.submit()">
                            @foreach(range(1, 12) as $m)
                                <option value="{{ $m }}" {{ $bulan == $m ? 'selected' : '' }}>
                                    {{ \Carbon\Carbon::createFromDate(null, $m, 1)->translatedFormat('F') }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Filter Tahun --}}
                    <div class="col-6 col-md-2">
                        <select name="tahun" class="form-select form-select-sm rounded-3 bg-light" onchange="this.form.submit()">
                            @foreach(range(now()->year - 2, now()->year + 1) as $y)
                                <option value="{{ $y }}" {{ $tahun == $y ? 'selected' : '' }}>
                                    {{ $y }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Filter Guru --}}
                    <div class="col-12 col-md-3">
                        <select name="id_guru" class="form-select form-select-sm rounded-3 bg-light" onchange="this.form.submit()">
                            <option value="">Semua Guru</option>
                            @foreach($guruList as $g)
                                <option value="{{ $g->id }}" {{ $selectedGuru == $g->id ? 'selected' : '' }}>
                                    {{ $g->nama }} ({{ $g->nip ? 'NIP: ' . $g->nip : 'Non-NIP' }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="row g-3 mb-4">
        {{-- Total JP Wajib --}}
        <div class="col-6 col-md-3">
            <div class="stat-card-kbm">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-uppercase fw-bold text-muted" style="font-size: 0.7rem; letter-spacing: 0.05em;">Total JP Wajib</span>
                    <span class="badge bg-light text-dark border"><i class="bi bi-clock"></i></span>
                </div>
                <h3 class="fw-bold text-dark mb-0 fs-3">{{ $grandTotalJpWajib }} <span class="text-muted fw-normal text-xs">JP</span></h3>
                <div class="text-muted text-2xs mt-1">Beban mengajar terjadwal</div>
            </div>
        </div>

        {{-- Total JP Terealisasi --}}
        <div class="col-6 col-md-3">
            <div class="stat-card-kbm">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-uppercase fw-bold text-muted" style="font-size: 0.7rem; letter-spacing: 0.05em;">JP Terealisasi</span>
                    <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check-lg"></i></span>
                </div>
                <h3 class="fw-bold text-success mb-0 fs-3">{{ $grandTotalJpTerealisasi }} <span class="text-muted fw-normal text-xs">JP</span></h3>
                <div class="text-muted text-2xs mt-1">Terisi jurnal dengan status Hadir</div>
            </div>
        </div>

        {{-- Total JP Dicover / Izin --}}
        <div class="col-6 col-md-3">
            <div class="stat-card-kbm">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-uppercase fw-bold text-muted" style="font-size: 0.7rem; letter-spacing: 0.05em;">Dicover / Izin</span>
                    <span class="badge bg-info-subtle text-info border border-info-subtle"><i class="bi bi-shield-check"></i></span>
                </div>
                <h3 class="fw-bold text-dark mb-0 fs-3">
                    {{ $grandTotalJpCover }} <span class="text-muted fw-normal text-xs">Cover</span>
                    <span class="text-muted fw-normal text-xs">/ {{ $grandTotalJpIzin }} Izin</span>
                </h3>
                <div class="text-muted text-2xs mt-1">Ditangani guru piket / izin resmi</div>
            </div>
        </div>

        {{-- Rata-rata Kedisiplinan Sekolah --}}
        <div class="col-6 col-md-3">
            <div class="stat-card-kbm">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-uppercase fw-bold text-muted" style="font-size: 0.7rem; letter-spacing: 0.05em;">Tingkat Kedisiplinan</span>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="bi bi-award"></i></span>
                </div>
                <h3 class="fw-bold text-primary mb-0 fs-3">{{ $rataRataKedisiplinan }}%</h3>
                <div class="text-muted text-2xs mt-1">Rata-rata pemenuhan jam KBM</div>
            </div>
        </div>
    </div>

    {{-- Main Performance Table --}}
    <div class="card border rounded-3 shadow-2xs mb-4">
        <div class="card-header bg-white border-bottom py-3 px-3.5 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h5 class="fw-bold text-dark mb-0" style="font-size: 1rem;">
                    Rekapitulasi KBM & Kedisiplinan Guru — Bulan {{ $namaBulan }} {{ $tahun }}
                </h5>
                <p class="text-muted mb-0 text-xs">
                    Tahun Ajaran: <strong>{{ $tahunAktif ? $tahunAktif->tahun . ' (' . $tahunAktif->semester . ')' : 'Aktif' }}</strong>
                </p>
            </div>
            <div class="d-flex align-items-center gap-2 text-xs">
                <span class="badge bg-light text-dark border">
                    Total: {{ count($dataRekap) }} Guru Terdaftar
                </span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-rekap-kbm table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 45px;" class="text-center">No</th>
                        <th>Nama Guru & NIP</th>
                        <th>Mata Pelajaran</th>
                        <th class="text-center">JP Wajib</th>
                        <th class="text-center">JP Realisasi</th>
                        <th class="text-center">JP Cover</th>
                        <th class="text-center">JP Izin</th>
                        <th class="text-center">JP Kosong</th>
                        <th style="min-width: 150px;">Kedisiplinan KBM</th>
                        <th class="text-center">Kategori Kinerja</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dataRekap as $index => $item)
                        <tr>
                            <td class="text-center text-muted fw-semibold">{{ $index + 1 }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; font-size: 0.75rem;">
                                        {{ strtoupper(substr($item->guru->nama, 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark">{{ $item->guru->nama }}</div>
                                        <div class="text-muted text-2xs">{{ $item->guru->nip ? 'NIP: ' . $item->guru->nip : 'Non-NIP' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="fw-semibold text-dark text-xs">{{ $item->mapel }}</span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border fw-bold px-2 py-1">
                                    {{ $item->jpWajib }} JP
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-success-subtle text-success border border-success-subtle fw-bold px-2 py-1">
                                    {{ $item->jpTerealisasi }} JP
                                </span>
                            </td>
                            <td class="text-center">
                                @if($item->jpCover > 0)
                                    <span class="badge bg-info-subtle text-info border border-info-subtle fw-bold px-2 py-1">
                                        {{ $item->jpCover }} JP
                                    </span>
                                @else
                                    <span class="text-muted text-xs">0</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($item->jpIzin > 0)
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle fw-bold px-2 py-1">
                                        {{ $item->jpIzin }} JP
                                    </span>
                                @else
                                    <span class="text-muted text-xs">0</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($item->jpAlpha > 0)
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle fw-bold px-2 py-1">
                                        {{ $item->jpAlpha }} JP
                                    </span>
                                @else
                                    <span class="text-success text-xs fw-semibold"><i class="bi bi-check2"></i> 0</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex align-items-center justify-content-between mb-1 text-xs">
                                    <span class="fw-bold text-dark">{{ $item->persentase }}%</span>
                                    <span class="text-muted text-2xs">{{ $item->jpTerealisasi + $item->jpCover }} / {{ $item->jpWajib }} JP</span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    @php
                                        $barColor = 'bg-success';
                                        if ($item->persentase < 60) $barColor = 'bg-danger';
                                        elseif ($item->persentase < 75) $barColor = 'bg-warning';
                                        elseif ($item->persentase < 90) $barColor = 'bg-primary';
                                    @endphp
                                    <div class="progress-bar {{ $barColor }}" role="progressbar" style="width: {{ $item->persentase }}%" aria-valuenow="{{ $item->persentase }}" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="badge {{ $item->badgeClass }} px-2.5 py-1 rounded-pill text-xs">
                                    {{ $item->kategoriKinerja }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                                Tidak ada data guru yang aktif untuk periode yang dipilih.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if(count($dataRekap) > 0)
                    <tfoot class="bg-light fw-bold">
                        <tr>
                            <td colspan="3" class="text-end text-dark">TOTAL KESELURUHAN SEKOLAH:</td>
                            <td class="text-center text-dark">{{ $grandTotalJpWajib }} JP</td>
                            <td class="text-center text-success">{{ $grandTotalJpTerealisasi }} JP</td>
                            <td class="text-center text-info">{{ $grandTotalJpCover }} JP</td>
                            <td class="text-center text-warning-emphasis">{{ $grandTotalJpIzin }} JP</td>
                            <td class="text-center text-danger">{{ $grandTotalJpAlpha }} JP</td>
                            <td>
                                <div class="fw-bold text-primary">{{ $rataRataKedisiplinan }}% Rata-rata</div>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-primary text-white px-2.5 py-1 rounded-pill text-xs">
                                    Evaluasi Bulanan
                                </span>
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

</div>
@endsection
