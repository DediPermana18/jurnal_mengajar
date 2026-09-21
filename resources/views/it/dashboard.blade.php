@extends('layouts.app')

@section('title', 'Dashboard IT & Helpdesk - WebJournal')

@section('content')
<style>
    .it-dashboard {
        font-size: 0.78rem;
    }

    .it-dashboard h2 {
        font-size: 1.25rem !important;
    }

    .it-dashboard .stat-card-custom,
    .it-dashboard .table-card-custom,
    .it-dashboard .badge,
    .it-dashboard .btn,
    .it-dashboard .text-muted,
    .it-dashboard .small,
    .it-dashboard p,
    .it-dashboard th,
    .it-dashboard td,
    .it-dashboard .stat-card-title,
    .it-dashboard .stat-card-label,
    .it-dashboard .stat-number-large {
        font-size: 0.72rem !important;
    }

    .it-dashboard .stat-number-large {
        font-size: 1.35rem !important;
    }

    .it-dashboard .table th,
    .it-dashboard .table td {
        padding-top: 0.55rem !important;
        padding-bottom: 0.55rem !important;
    }
</style>

<div class="container-fluid px-0 it-dashboard">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3 md:mb-4 gap-1 md:gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 800; font-size: 1.5rem;">
                <i class="bi bi-tools text-primary me-1"></i>Dashboard IT & Helpdesk
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.85rem;">Pantau laporan kendala pengguna dan status server.</p>
        </div>
        <span class="text-muted small mt-1 mt-md-0"><i class="bi bi-calendar3 me-1"></i>{{ now()->translatedFormat('l, d F Y') }}</span>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-3 border-0 shadow-sm mb-3 d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-check-circle-fill text-success fs-5"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('cancel'))
        <div class="alert alert-warning alert-dismissible fade show rounded-3 border-0 shadow-sm mb-3 d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
            <div>{{ session('cancel') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 shadow-sm mb-3 d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-x-circle-fill text-danger fs-5"></i>
            <div>{{ session('error') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- WIDGET STAT CARD --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 md:gap-4 mb-3 md:mb-4">
        <div>
            <div class="stat-card-custom p-3 md:p-4 h-100">
                <div class="stat-card-title text-xs md:text-sm truncate mb-1 md:mb-2" title="Tiket Kendala Menunggu Diproses">Tiket Kendala Pending</div>
                <div class="stat-number-large text-lg md:text-xl text-warning mb-1">{{ number_format($pendingCount) }}</div>
                <div class="stat-card-label text-xs md:text-sm truncate" title="Kendala yang belum diproses Tim IT">Menunggu Tindak Lanjut</div>
            </div>
        </div>
        <div>
            <div class="stat-card-custom p-3 md:p-4 h-100">
                <div class="stat-card-title text-xs md:text-sm truncate mb-1 md:mb-2" title="Laporan Kendola Masuk">Laporan Kendala Masuk</div>
                <div class="stat-number-large text-lg md:text-xl text-info mb-1">{{ number_format($activeKendalaCount) }}</div>
                <div class="stat-card-label text-xs md:text-sm truncate" title="Kendala dengan status Pending atau Proses">Pending / Proses</div>
            </div>
        </div>
        <div>
            <div class="stat-card-custom p-3 md:p-4 h-100">
                <div class="stat-card-title text-xs md:text-sm truncate mb-1 md:mb-2" title="Konektivitas Aplikasi & Database">Status Server</div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    @if($dbOnline)
                        <span class="d-inline-block rounded-circle bg-success flex-shrink-0" style="width: 11px; height: 11px;"></span>
                        <div class="stat-number-large text-lg md:text-xl text-success mb-0">Online</div>
                    @else
                        <span class="d-inline-block rounded-circle bg-danger flex-shrink-0" style="width: 11px; height: 11px;"></span>
                        <div class="stat-number-large text-lg md:text-xl text-danger mb-0">Offline</div>
                    @endif
                </div>
                <div class="stat-card-label text-xs md:text-sm truncate" title="Status Mode Maintenance">
                    @if($maintenanceActive)
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1" style="font-size:0.72rem;">
                            <i class="bi bi-circle-fill me-1" style="font-size:0.4rem;"></i>MAINTENANCE AKTIF
                        </span>
                    @else
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1" style="font-size:0.72rem;">
                            <i class="bi bi-circle-fill me-1" style="font-size:0.4rem;"></i>NORMAL
                        </span>
                    @endif
                </div>
            </div>
        </div>
        <div>
            <a href="{{ route('it.settings.wa') }}" class="stat-card-custom p-3 md:p-4 h-100 d-block text-decoration-none" title="Buka Pengaturan WA Gateway">
                <div class="stat-card-title text-xs md:text-sm truncate mb-1 md:mb-2" title="Status Bot WA (Fonnte)">Status Bot WA</div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    @if($fonnteConnected)
                        <span class="d-inline-block rounded-circle bg-success flex-shrink-0" style="width: 11px; height: 11px;"></span>
                        <div class="stat-number-large text-lg md:text-xl text-success mb-0">Connected</div>
                    @else
                        <span class="d-inline-block rounded-circle bg-danger flex-shrink-0" style="width: 11px; height: 11px;"></span>
                        <div class="stat-number-large text-lg md:text-xl text-danger mb-0">Disconnected</div>
                    @endif
                </div>
                <div class="stat-card-label text-xs md:text-sm truncate" title="Status Koneksi Fonnte API">
                    @if($fonnteConnected)
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1" style="font-size:0.72rem;">
                            <i class="bi bi-wifi me-1"></i>TERKONEKSI
                        </span>
                    @else
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1" style="font-size:0.72rem;">
                            <i class="bi bi-wifi-off me-1"></i>GAGAL KONEKSI
                        </span>
                    @endif
                </div>
                <div class="mt-2 small text-primary fw-semibold" style="font-size:0.68rem !important;">
                    <i class="bi bi-gear-wide-connected me-1"></i>Kelola & Tes Kirim →
                </div>
            </a>
        </div>
    </div>

    {{-- REKAP LAPORAN KEN DALA --}}
    <div class="row g-4">
        <div class="col-12">
            <div class="table-card-custom h-100">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h5 class="fw-bold text-dark mb-0">Rekap Laporan Kendala Sistem</h5>
                        <p class="text-muted small mb-0 mt-1">Total laporan yang belum diselesaikan.</p>
                    </div>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-2">
                        {{ number_format($activeKendalaCount) }} belum selesai
                    </span>
                </div>

                <div class="overflow-x-auto w-full rounded-lg">
                    <table class="table table-custom align-middle mb-0 min-w-full">
                        <thead>
                            <tr>
                                <th class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">PELAPOR</th>
                                <th class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">ROLE</th>
                                <th class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">JUDUL KENDALA</th>
                                <th class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">PRIORITAS</th>
                                <th class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">TANGGAL</th>
                                <th class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">STATUS</th>
                                <th class="whitespace-nowrap px-3 py-2 text-xs md:text-sm" style="text-align: right;">AKSI</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($kendala as $laporan)
                                <tr>
                                    <td class="fw-semibold text-dark whitespace-nowrap px-3 py-2 text-xs md:text-sm">
                                        {{ $laporan->pelapor?->nama ?? 'User #'.$laporan->user_id }}
                                    </td>
                                    <td class="text-muted whitespace-nowrap px-3 py-2 text-xs md:text-sm">
                                        <span class="badge bg-light text-dark border rounded-3 text-xs md:text-sm">{{ $laporan->pelapor?->role_label ?? '-' }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2 text-xs md:text-sm" title="{{ $laporan->judul }}">
                                        <span class="d-inline-block text-truncate fw-semibold text-dark" style="max-width: 210px;">{{ $laporan->judul }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">
                                        <span class="badge {{ $laporan->prioritas_badge }} rounded-pill" style="font-size:0.68rem;">{{ $laporan->prioritas_label }}</span>
                                    </td>
                                    <td class="text-muted whitespace-nowrap px-3 py-2 text-xs md:text-sm">{{ $laporan->created_at?->translatedFormat('d/m/Y H:i') ?? '-' }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">
                                        <span class="badge {{ $laporan->status_badge }} rounded-pill px-2 py-1" style="font-size:0.68rem;">{{ $laporan->status_label }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">
                                        <div class="d-flex align-items-center justify-content-end gap-1 flex-wrap">
                                            <button type="button" class="btn btn-sm btn-outline-primary rounded-3" data-bs-toggle="modal" data-bs-target="#detailKendala{{ $laporan->id }}">
                                                <i class="bi bi-eye me-1"></i>Detail
                                            </button>
                                            @if($laporan->status === 'selesai')
                                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1" style="font-size:0.68rem;">
                                                    <i class="bi bi-check2-circle me-1"></i>Selesai
                                                </span>
                                            @else
                                                <form method="POST" action="{{ route('it.kendala.status', $laporan->id) }}" class="d-inline">
                                                    @csrf
                                                    <select name="status" class="form-select form-select-sm rounded-3" style="width: auto;" onchange="this.form.submit()" title="Ubah status">
                                                        <option value="pending" {{ $laporan->status === 'pending' ? 'selected' : '' }}>Pending</option>
                                                        <option value="proses" {{ $laporan->status === 'proses' ? 'selected' : '' }}>Proses</option>
                                                        <option value="selesai" {{ $laporan->status === 'selesai' ? 'selected' : '' }}>Selesai</option>
                                                    </select>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted text-xs md:text-sm">
                                        <i class="bi bi-inbox d-block mb-2" style="font-size: 1.4rem;"></i>
                                        Belum ada laporan kendala.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

 {{-- ==================== MODAL DETAIL KENDALA PER ROW ==================== --}}
@foreach($kendala as $kendala)
<div class="modal fade" id="detailKendala{{ $kendala->id }}" tabindex="-1" aria-labelledby="detailKendalaLabel{{ $kendala->id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="detailKendalaLabel{{ $kendala->id }}">
                    <i class="bi bi-bug text-danger me-2"></i>Detail Kendala
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="text-muted text-uppercase small fw-bold mb-1" style="font-size: 0.68rem; letter-spacing: 0.06em;">Judul Kendala</div>
                        <div class="fw-bold text-dark">{{ $kendala->judul }}</div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted text-uppercase small fw-bold mb-1" style="font-size: 0.68rem; letter-spacing: 0.06em;">Deskripsi Error</div>
                        <div class="text-dark" style="white-space: pre-line; font-size: 0.9rem;">{{ $kendala->deskripsi }}</div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted text-uppercase small fw-bold mb-1" style="font-size: 0.68rem; letter-spacing: 0.06em;">Pelapor</div>
                        <div class="fw-semibold text-dark">{{ $kendala->pelapor?->nama ?? 'User #'.$kendala->user_id }}</div>
                        <div class="text-muted small">{{ $kendala->pelapor?->role_label ?? '-' }}</div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted text-uppercase small fw-bold mb-1" style="font-size: 0.68rem; letter-spacing: 0.06em;">Dilaporkan</div>
                        <div class="fw-semibold text-dark">{{ $kendala->created_at?->translatedFormat('d M Y, H:i') ?? '-' }}</div>
                        <div class="mt-1">
                            <span class="badge {{ $kendala->status_badge }} rounded-pill px-2 py-1 me-1">{{ $kendala->status_label }}</span>
                            <span class="badge {{ $kendala->prioritas_badge }} rounded-pill px-2 py-1">Prioritas {{ $kendala->prioritas_label }}</span>
                        </div>
                    </div>
                    @if($kendala->foto_bukti)
                        <div class="col-12">
                            <div class="text-muted text-uppercase small fw-bold mb-1" style="font-size: 0.68rem; letter-spacing: 0.06em;">Screenshot Bukti</div>
                            <a href="{{ asset('storage/'.$kendala->foto_bukti) }}" target="_blank" rel="noopener">
                                <img src="{{ asset('storage/'.$kendala->foto_bukti) }}" alt="Bukti kendala"
                                     class="img-fluid rounded-3 border" style="max-height: 320px;">
                            </a>
                        </div>
                    @endif
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light rounded-3 fw-semibold" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endforeach

@endsection