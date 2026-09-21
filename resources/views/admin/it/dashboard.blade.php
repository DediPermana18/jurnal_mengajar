@extends('layouts.app')

@section('title', 'Dashboard IT & Helpdesk - WebJournal')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3 md:mb-4 gap-1 md:gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 900; font-size: 1.5rem;">
                <i class="bi bi-tools text-primary me-1"></i>Dashboard IT &amp; Helpdesk
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.85rem;">Pantau laporan kendala pengguna dan status server.</p>
        </div>
        <span class="text-muted small mt-1 mt-md-0"><i class="bi bi-calendar3 me-1"></i>{{ now()->translatedFormat('l, d F Y') }}</span>
    </div>

    {{-- WIDGET STAT CARD --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 md:gap-4 mb-3 md:mb-4">
        <div>
            <div class="stat-card-custom p-3 md:p-4 h-100">
                <div class="stat-card-title text-xs md:text-sm truncate mb-1 md:mb-2" title="Tiket Kendala Menunggu Diproses">Tiket Kendala Pending</div>
                <div class="stat-number-large text-2xl md:text-4xl text-warning mb-1">{{ number_format($pendingCount) }}</div>
                <div class="stat-card-label text-xs md:text-sm truncate" title="Kendala yang belum diproses Tim IT">Menunggu Tindak Lanjut</div>
            </div>
        </div>
        <div>
            <div class="stat-card-custom p-3 md:p-4 h-100">
                <div class="stat-card-title text-xs md:text-sm truncate mb-1 md:mb-2" title="Laporan Kendala Masuk">Laporan Kendala Masuk</div>
                <div class="stat-number-large text-2xl md:text-4xl text-info mb-1">{{ number_format($activeKendalaCount) }}</div>
                <div class="stat-card-label text-xs md:text-sm truncate" title="Kendala dengan status Pending atau Proses">Pending / Proses</div>
            </div>
        </div>
        <div>
            <div class="stat-card-custom p-3 md:p-4 h-100">
                <div class="stat-card-title text-xs md:text-sm truncate mb-1 md:mb-2" title="Konektivitas Aplikasi & Database">Status Server</div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    @if($dbOnline)
                        <span class="d-inline-block rounded-circle bg-success flex-shrink-0" style="width: 11px; height: 11px;"></span>
                        <div class="stat-number-large text-2xl md:text-3xl text-success mb-0">Online</div>
                    @else
                        <span class="d-inline-block rounded-circle bg-danger flex-shrink-0" style="width: 11px; height: 11px;"></span>
                        <div class="stat-number-large text-2xl md:text-3xl text-danger mb-0">Offline</div>
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
                        <div class="stat-number-large text-2xl md:text-3xl text-success mb-0">Connected</div>
                    @else
                        <span class="d-inline-block rounded-circle bg-danger flex-shrink-0" style="width: 11px; height: 11px;"></span>
                        <div class="stat-number-large text-2xl md:text-3xl text-danger mb-0">Disconnected</div>
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
                <div class="mt-2 small text-primary fw-semibold" style="font-size:0.72rem !important;">
                    <i class="bi bi-gear-wide-connected me-1"></i>Kelola & Tes Kirim →
                </div>
            </a>
        </div>
    </div>

    <div class="row g-4">
        {{-- TABEL LAPORAN KENDALA TERBARU --}}
        <div class="col-12 col-xl-8">
            <div class="table-card-custom h-100">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h5 class="fw-bold text-dark mb-0">Laporan Kendala Terbaru</h5>
                        <p class="text-muted small mb-0 mt-1">Seluruh laporan dari pengguna (real &amp; testing).</p>
                    </div>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-2">
                        {{ number_format($kendala->where('status', '!=', App\Models\LaporanKendala::STATUS_SELESAI)->count()) }} belum selesai
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
                                        @if($laporan->foto_bukti)
                                            <i class="bi bi-camera-fill text-muted ms-1" style="font-size:0.7rem;" title="Ada bukti foto"></i>
                                        @endif
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
                                            @if($laporan->status !== App\Models\LaporanKendala::STATUS_SELESAI)
                                                <form method="POST" action="{{ route('it.kendala.status', $laporan->id) }}" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="status" value="{{ App\Models\LaporanKendala::STATUS_SELESAI }}">
                                                    <button type="submit" class="btn btn-sm btn-success rounded-3">
                                                        <i class="bi bi-check2-circle me-1"></i>Tandai Selesai
                                                    </button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('it.kendala.status', $laporan->id) }}" class="d-inline">
                                                @csrf
                                                <select name="status" class="form-select form-select-sm rounded-3" style="width: auto;" onchange="this.form.submit()" title="Ubah status">
                                                    <option value="{{ App\Models\LaporanKendala::STATUS_PENDING }}" {{ $laporan->status === App\Models\LaporanKendala::STATUS_PENDING ? 'selected' : '' }}>Pending</option>
                                                    <option value="{{ App\Models\LaporanKendala::STATUS_PROSES }}" {{ $laporan->status === App\Models\LaporanKendala::STATUS_PROSES ? 'selected' : '' }}>Proses</option>
                                                    <option value="{{ App\Models\LaporanKendala::STATUS_SELESAI }}" {{ $laporan->status === App\Models\LaporanKendala::STATUS_SELESAI ? 'selected' : '' }}>Selesai</option>
                                                </select>
                                            </form>
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

        {{-- SIDE PANEL: INFORMASI SERVER --}}
        <div class="col-12 col-xl-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-hdd-stack-fill text-primary me-2"></i>Informasi Server</h5>
                    <span class="badge {{ $dbOnline ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }} border rounded-pill px-3 py-1">
                        {{ $dbOnline ? 'Terkoneksi' : 'Gangguan Koneksi' }}
                    </span>
                </div>
                <ul class="list-unstyled mb-4">
                    <li class="d-flex justify-content-between align-items-center py-2 border-bottom" style="border-color: #eef2f7 !important;">
                        <span class="text-muted small">Aplikasi</span>
                        <span class="fw-semibold text-dark small">{{ $appName }}</span>
                    </li>
                    <li class="d-flex justify-content-between align-items-center py-2 border-bottom" style="border-color: #eef2f7 !important;">
                        <span class="text-muted small">Environment</span>
                        <span class="fw-semibold text-dark small">{{ ucfirst($appEnv) }}</span>
                    </li>
                    <li class="d-flex justify-content-between align-items-center py-2 border-bottom" style="border-color: #eef2f7 !important;">
                        <span class="text-muted small">Database</span>
                        <span class="fw-semibold {{ $dbOnline ? 'text-success' : 'text-danger' }} small">
                            {{ $dbOnline ? 'Online' : 'Offline' }}
                        </span>
                    </li>
                    <li class="d-flex justify-content-between align-items-center py-2 border-bottom" style="border-color: #eef2f7 !important;">
                        <span class="text-muted small">Mode Maintenance</span>
                        <span class="fw-semibold {{ $maintenanceActive ? 'text-danger' : 'text-success' }} small">
                            {{ $maintenanceActive ? 'Aktif' : 'Normal' }}
                        </span>
                    </li>
                    <li class="d-flex justify-content-between align-items-center py-2 border-bottom" style="border-color: #eef2f7 !important;">
                        <span class="text-muted small">Framework (Laravel)</span>
                        <span class="fw-semibold text-dark small">v{{ $laravelVersion }}</span>
                    </li>
                    <li class="d-flex justify-content-between align-items-center py-2 border-bottom" style="border-color: #eef2f7 !important;">
                        <span class="text-muted small">PHP</span>
                        <span class="fw-semibold text-dark small">v{{ $phpVersion }}</span>
                    </li>
                    <li class="d-flex justify-content-between align-items-center py-2">
                        <span class="text-muted small">Waktu Server</span>
                        <span class="fw-semibold text-dark small">{{ $serverTime->translatedFormat('d M Y H:i:s') }}</span>
                    </li>
                </ul>

                <div class="d-grid gap-2 mt-auto">
                    <form method="POST" action="{{ route('it.maintenance-mode') }}" class="d-grid">
                        @csrf
                        <input type="hidden" name="maintenance_mode" value="{{ $maintenanceActive ? '0' : '1' }}">
                        <button type="submit" class="btn {{ $maintenanceActive ? 'btn-success' : 'btn-danger' }} rounded-3 py-2 fw-semibold">
                            <i class="bi {{ $maintenanceActive ? 'bi-power' : 'bi-shield-exclamation' }} me-1"></i>
                            {{ $maintenanceActive ? 'Nonaktifkan Mode Maintenance' : 'Aktifkan Mode Maintenance' }}
                        </button>
                    </form>
                    <a href="{{ route('bantuan.index') }}" class="btn btn-outline-primary rounded-3 py-2 fw-semibold">
                        <i class="bi bi-life-preserver me-1"></i> Buka Pusat Bantuan
                    </a>
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
                    @if($kendala->foto_bukti && Illuminate\Support\Facades\Storage::disk('public')->exists($kendala->foto_bukti))
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
                <form method="POST" action="{{ route('it.kendala.status', $kendala->id) }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="status" value="{{ App\Models\LaporanKendala::STATUS_SELESAI }}">
                    <button type="submit" class="btn btn-success rounded-3 fw-semibold px-3" {{ $kendala->status === App\Models\LaporanKendala::STATUS_SELESAI ? 'disabled' : '' }}>
                        <i class="bi bi-check2-circle me-1"></i> Tandai Selesai
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endforeach

@endsection