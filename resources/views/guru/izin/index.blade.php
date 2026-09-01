@extends('layouts.app')

@section('title', 'Izin Guru Saya - Portal Guru')

@section('content')
<div class="container-fluid px-0">

    {{-- Page Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="font-weight: 900; font-size: 1.75rem; letter-spacing: -0.02em;">
                Izin Guru Saya
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                Ajukan izin tidak masuk mengajar dan pantau status persetujuan berjenjang.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('guru.izin.create') }}" class="btn btn-primary rounded-3 px-3 py-2 fw-semibold shadow-sm">
                <i class="bi bi-plus-lg me-1"></i> Ajukan Izin
            </a>
        </div>
    </div>

    {{-- Alert --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4 d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-check-circle-fill text-success fs-5"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Stat --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4">
            <div class="stat-card-custom">
                <div class="stat-card-title">Menunggu Persetujuan</div>
                <div class="stat-number-large text-warning">{{ $totalPending }}</div>
                <div class="stat-card-label">Belum final</div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="stat-card-custom">
                <div class="stat-card-title">Disetujui</div>
                <div class="stat-number-large text-success">{{ $totalDisetujui }}</div>
                <div class="stat-card-label">Izin final</div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="stat-card-custom">
                <div class="stat-card-title">Ditolak</div>
                <div class="stat-number-large text-danger">{{ $totalDitolak }}</div>
                <div class="stat-card-label">Dengan catatan</div>
            </div>
        </div>
    </div>

    {{-- Filter --}}
    <div class="mb-4">
        <ul class="nav nav-pills gap-2 flex-nowrap overflow-auto pb-1">
            <li class="nav-item">
                <a href="{{ route('guru.izin.index') }}" class="nav-link rounded-pill px-3 fw-semibold {{ $filter === 'Semua' ? 'active' : '' }}">Semua</a>
            </li>
            @foreach(\App\Models\IzinGuru::STATUS_LABELS as $value => $label)
                <li class="nav-item">
                    <a href="{{ route('guru.izin.index', ['status' => $value]) }}" class="nav-link rounded-pill px-3 fw-semibold {{ $filter === $value ? 'active' : '' }}">{{ $label }}</a>
                </li>
            @endforeach
        </ul>
    </div>

    {{-- Tabel --}}
    <div class="table-card-custom mb-4">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h5 class="fw-bold text-dark mb-0">Riwayat Izin</h5>
            <span class="text-muted small">Menampilkan {{ number_format($daftarIzin->total()) }} pengajuan</span>
        </div>
        <div class="table-responsive w-full overflow-x-auto">
            <table class="table table-custom align-middle mb-0 min-w-full">
                <thead>
                    <tr>
                        <th class="whitespace-nowrap">TANGGAL</th>
                        <th>ALASAN</th>
                        <th>TUGAS SISWA</th>
                        <th class="whitespace-nowrap">STATUS</th>
                        <th class="text-end whitespace-nowrap">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($daftarIzin as $izin)
                        <tr>
                            <td class="fw-semibold text-dark text-nowrap">{{ $izin->tanggal->translatedFormat('d/m/Y') }}</td>
                            <td style="max-width: 300px;">
                                <div class="text-wrap">{{ $izin->alasan }}</div>
                                @if($izin->isRejected() && $izin->catatan_penolakan)
                                    <div class="small text-danger mt-1 border-top pt-1">
                                        <i class="bi bi-x-circle-fill me-1"></i>Catatan tolak: {{ $izin->catatan_penolakan }}
                                    </div>
                                @endif
                            </td>
                            <td style="max-width: 220px;">
                                @if($izin->tugas_siswa)
                                    <span class="text-wrap" title="{{ $izin->tugas_siswa }}">{{ $izin->tugas_siswa }}</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $izin->status_badge }} rounded-pill px-2 py-2">{{ $izin->status_label }}</span>
                            </td>
                            <td class="text-end text-nowrap">
                                <div class="flex items-center justify-center gap-2 whitespace-nowrap">
                                <a href="{{ route('guru.izin.show', $izin->id) }}" class="btn btn-sm btn-outline-secondary rounded-3">
                                    <i class="bi bi-eye me-1"></i>Detail
                                </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                                Belum ada pengajuan izin. Klik <strong>Ajukan Izin</strong> untuk memulai.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($daftarIzin->hasPages())
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-4 pt-3 border-top">
                <div class="text-muted small mb-3 mb-md-0">
                    Menampilkan <strong>{{ $daftarIzin->firstItem() ?? 0 }}</strong>-<strong>{{ $daftarIzin->lastItem() ?? 0 }}</strong> dari <strong>{{ $daftarIzin->total() }}</strong> pengajuan
                </div>
                {{ $daftarIzin->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
