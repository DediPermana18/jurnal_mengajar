@extends('layouts.app')

@section('title', 'Dashboard Wali Kelas - WebJournal')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-black text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 800; font-size: 1.75rem;">
                Dashboard Wali Kelas
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                Ringkasan kegiatan & dispensasi siswa kelas bimbingan Anda.
            </p>
        </div>
        <div>
            <a href="{{ route('walikelas.rekap-absen') }}" class="btn btn-outline-secondary rounded-3 px-3 py-2 fw-semibold">
                <i class="bi bi-clipboard-data me-1"></i> Rekap Absen
            </a>
        </div>
    </div>

    @if($kelasSaya->isEmpty())
        <div class="table-card-custom text-center py-5">
            <i class="bi bi-inbox fs-1 text-muted d-block mb-3"></i>
            <h5 class="fw-bold text-dark mb-1">Belum Ada Kelas Bimbingan</h5>
            <p class="text-muted mb-0 small">
                Anda belum terdaftar sebagai Wali Kelas. Hubungi Admin untuk mengatur kelas bimbingan Anda.
            </p>
        </div>
    @else
        <!-- Stat Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-card-custom h-100">
                    <div class="stat-card-title text-uppercase">Total Siswa Bimbingan</div>
                    <div class="stat-number-large text-primary">{{ $totalSiswa }}</div>
                    <div class="stat-card-label">Siswa</div>
                    <p class="stat-card-subtext text-truncate" title="{{ $namaKelasSaya }}">{{ $namaKelasSaya }}</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card-custom h-100">
                    <div class="stat-card-title text-uppercase">Siswa Terlambat</div>
                    <div class="stat-number-large text-danger">{{ $terlambatHariIni }}</div>
                    <div class="stat-card-label">Hari Ini</div>
                    <p class="stat-card-subtext">Dari catatan Satpam di gerbang</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card-custom h-100">
                    <div class="stat-card-title text-uppercase">Siswa Perlu Perhatian</div>
                    <div class="stat-number-large text-warning">{{ $perluPerhatian }}</div>
                    <div class="stat-card-label">Terlambat >3x / Berkasus</div>
                    <p class="stat-card-subtext text-truncate" title="{{ $namaKelasSaya }}">{{ $namaKelasSingkat }}</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card-custom h-100">
                    <div class="stat-card-title text-uppercase">Siswa Dispen</div>
                    <div class="stat-number-large text-info">{{ $jumlahDispen }}</div>
                    <div class="stat-card-label">Hari Ini</div>
                    <p class="stat-card-subtext">{{ $jumlahDisetujui }} disetujui</p>
                </div>
            </div>
        </div>

        <!-- Widget: Dispensasi Siswa Hari Ini -->
        <div class="table-card-custom mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h5 class="fw-bold text-dark mb-0">
                    <i class="bi bi-person-dash-fill text-info me-2"></i> Dispensasi Siswa Hari Ini
                </h5>
                <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle rounded-pill px-3 py-1">
                    {{ \Carbon\Carbon::parse($today)->locale('id')->translatedFormat('d F Y') }}
                </span>
            </div>

            @if($jumlahDispen === 0)
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-emoji-smile fs-1 d-block mb-2"></i>
                    Tidak ada siswa kelas bimbingan Anda yang di-dispensasi hari ini.
                </div>
            @else
                @foreach($dispensasiHariIni as $namaKelas => $items)
                    <div class="mb-4 {{ !$loop->last ? 'border-bottom pb-3' : '' }}">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="fw-bold text-dark">{{ $namaKelas }}</span>
                            <span class="badge bg-light text-dark border rounded-pill px-2">{{ $items->count() }} siswa</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-custom align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Nama Siswa</th>
                                        <th>Jam Ke-</th>
                                        <th>Alasan</th>
                                        <th>Guru Piket</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($items as $dispen)
                                        <tr>
                                            <td class="fw-semibold">{{ $dispen->siswa?->nama ?? '-' }}</td>
                                            <td>{{ $dispen->jam_ke_label }}</td>
                                            <td class="text-muted">{{ $dispen->alasan }}</td>
                                            <td>{{ $dispen->guruPiket?->nama ?? '-' }}</td>
                                            <td><span class="badge {{ $dispen->status_badge }} rounded-pill px-3 py-1">{{ $dispen->status_label }}</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    @endif
</div>
@endsection