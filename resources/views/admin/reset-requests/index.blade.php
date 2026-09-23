@extends('layouts.app')

@section('title', 'Pengajuan Reset Kredensial - WebJournal')

@push('styles')
<style>
    .table-reset th {
        background: #f8fafc;
        font-size: 0.74rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        font-weight: 700;
        padding: 0.8rem 1rem;
        border-bottom: 1px solid #e2e8f0;
        white-space: nowrap;
    }
    .table-reset td {
        padding: 0.85rem 1rem;
        vertical-align: middle;
        font-size: 0.86rem;
        border-bottom: 1px solid #f1f5f9;
    }
    .sig-thumb {
        width: 96px;
        height: 44px;
        object-fit: contain;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 0.5rem;
        padding: 4px;
        cursor: zoom-in;
    }
    .reset-link-box {
        background: #f1f5f9;
        border: 1px dashed #cbd5e1;
        border-radius: 0.6rem;
        padding: 0.55rem 0.75rem;
        font-size: 0.72rem;
        word-break: break-all;
        color: #334155;
        max-width: 260px;
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">

    {{-- ================= HEADER ================= --}}
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-brand-subtle text-brand-emphasis border px-2.5 py-1 rounded-pill fw-semibold text-xs" style="background:#e0f2fe;color:#0369a1;">
                    <i class="bi bi-key me-1"></i> Pengajuan Reset Kredensial
                </span>
            </div>
            <h2 class="fw-black text-dark mb-1" style="font-weight: 900; font-size: 1.55rem; letter-spacing: -0.02em;">
                Pengajuan Reset
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.875rem;">
                Verifikasi tanda tangan digital pemohon, lalu setujui (buat tautan reset unik) atau tolak.
            </p>
        </div>
        <a href="{{ route('admin.users.index') }}" class="btn btn-light border rounded-3 fw-semibold text-sm">
            <i class="bi bi-person-gear text-primary me-1"></i> Kelola User
        </a>
    </div>

    {{-- ================= ALERTS ================= --}}
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
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4" role="alert">
            <ul class="mb-0">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- ================= FILTER STATUS ================= --}}
    <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
        <span class="text-muted small fw-semibold me-1"><i class="bi bi-funnel me-1"></i>Filter:</span>
        <a href="{{ route('admin.reset-requests.index') }}" class="btn btn-sm {{ is_null($status) ? 'btn-dark' : 'btn-light border' }} rounded-3 fw-semibold">
            Semua
        </a>
        @foreach(\App\Models\ResetRequest::STATUSES as $s)
            <a href="{{ route('admin.reset-requests.index', ['status' => $s]) }}"
               class="btn btn-sm {{ $status === $s ? 'btn-dark' : 'btn-light border' }} rounded-3 fw-semibold">
                {{ \App\Models\ResetRequest::STATUS_LABELS[$s] }}
            </a>
        @endforeach
        @php
            $pendingCount = \App\Models\ResetRequest::where('status', \App\Models\ResetRequest::STATUS_PENDING)->count();
        @endphp
        @if($pendingCount > 0)
            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-2.5 py-1 text-xs ms-auto">
                <i class="bi bi-hourglass-split me-1"></i>{{ $pendingCount }} menunggu verifikasi
            </span>
        @endif
    </div>

    {{-- ================= TABEL ================= --}}
    <div class="card border rounded-3 shadow-sm">
        <div class="table-responsive">
            <table class="table table-reset align-middle mb-0">
                <thead>
                    <tr>
                        <th>Pemohon</th>
                        <th>Jenis Pengajuan</th>
                        <th>Tanda Tangan</th>
                        <th>Tanggal Pengajuan</th>
                        <th>Status</th>
                        <th style="min-width: 230px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($daftar as $r)
                        <tr>
                            <td>
                                <div class="fw-bold text-dark">{{ $r->user?->nama ?? '(akun dihapus)' }}</div>
                                <div class="text-muted small">@username: {{ $r->user?->username ?? '-' }}</div>
                                <div class="text-muted small">NIP: {{ $r->user?->nip ?? '-' }}</div>
                            </td>
                            <td>
                                <span class="badge {{ $r->jenis_pengajuan === \App\Models\ResetRequest::JENIS_LUPA_SANDI ? 'bg-primary-subtle text-primary border border-primary-subtle' : 'bg-info-subtle text-info border border-info-subtle' }} px-2.5 py-1 rounded-pill text-xs">
                                    <i class="bi {{ $r->jenis_pengajuan === \App\Models\ResetRequest::JENIS_LUPA_SANDI ? 'bi-shield-lock' : 'bi-hash' }} me-1"></i>
                                    {{ $r->jenis_label }}
                                </span>
                            </td>
                            <td>
                                @if($r->tanda_tangan)
                                    <img src="{{ $r->tanda_tangan }}" alt="Tanda tangan" class="sig-thumb"
                                         data-bs-toggle="modal" data-bs-target="#sigModal{{ $r->id }}" title="Klik untuk memperbesar">
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            </td>
                            <td>
                                <span class="text-dark fw-semibold d-block">{{ $r->created_at->translatedFormat('d M Y') }}</span>
                                <span class="text-muted small">{{ $r->created_at->translatedFormat('H:i') }} WIB</span>
                            </td>
                            <td>
                                <span class="badge {{ $r->status_badge }} px-2.5 py-1 rounded-pill text-xs">
                                    <i class="bi {{ $r->isPending() ? 'bi-hourglass-split' : ($r->isApproved() ? 'bi-check-circle' : 'bi-x-circle') }} me-1"></i>
                                    {{ $r->status_label }}
                                </span>
                                @if($r->isRejected() && $r->admin_note)
                                    <div class="text-danger small mt-1" style="font-size: 0.72rem;">
                                        <i class="bi bi-chat-left-text me-1"></i>{{ $r->admin_note }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if($r->isPending())
                                    {{-- Setujui + Tolak --}}
                                    <div class="d-flex flex-wrap gap-2">
                                        <form action="{{ route('admin.reset-requests.approve', $r->id) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success rounded-3 fw-semibold"
                                                    onclick="return confirm('Setujui pengajuan ini dan buat tautan reset unik?')">
                                                <i class="bi bi-check-lg me-1"></i>Setujui
                                            </button>
                                        </form>
                                        <button type="button" class="btn btn-sm btn-outline-danger rounded-3 fw-semibold"
                                                data-bs-toggle="modal" data-bs-target="#modalTolak{{ $r->id }}">
                                            <i class="bi bi-x-lg me-1"></i>Tolak
                                        </button>
                                    </div>
                                @elseif($r->isApproved())
                                    {{-- Salin link / Kirim WA --}}
                                    <div class="d-flex flex-wrap gap-2 align-items-center">
                                        <div class="reset-link-box">
                                            {{ $r->resetUrl() ?? 'Tautan sudah tidak tersedia' }}
                                        </div>
                                        @if($r->resetUrl())
                                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-3 fw-semibold"
                                                    data-url="{{ $r->resetUrl() }}"
                                                    onclick="copyResetLink(this)">
                                                <i class="bi bi-clipboard me-1"></i>Salin Link
                                            </button>
                                            @if($r->waResetUrl())
                                                <a href="{{ $r->waResetUrl() }}" target="_blank" rel="noopener"
                                                   class="btn btn-sm btn-success rounded-3 fw-semibold">
                                                    <i class="bi bi-whatsapp me-1"></i>Kirim WA
                                                </a>
                                            @else
                                                <span class="text-muted small" style="font-size:0.72rem;" title="Nomor WhatsApp pemohon tidak tersedia">
                                                    <i class="bi bi-info-circle"></i> No. WA kosong
                                                </span>
                                            @endif
                                        @endif
                                    </div>
                                @else
                                    <span class="text-muted small"><i class="bi bi-check2-all me-1"></i>Telah diproses</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="bi bi-inbox display-6 d-block mb-2"></i>
                                    <span class="fw-semibold">Belum ada pengajuan reset.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($daftar->hasPages())
            <div class="card-footer bg-white border-top py-3">
                {{ $daftar->links() }}
            </div>
        @endif
    </div>
</div>

{{-- ================= MODAL TOLAK ================= --}}
@foreach($daftar as $r)
    @if($r->isPending())
        <div class="modal fade" id="modalTolak{{ $r->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4">
                    <form action="{{ route('admin.reset-requests.reject', $r->id) }}" method="POST">
                        @csrf
                        <div class="modal-header border-0 pb-0">
                            <h5 class="modal-title fw-bold text-dark"><i class="bi bi-x-circle-fill text-danger me-1"></i>Tolak Pengajuan</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body px-4 py-3">
                            <div class="mb-3">
                                <span class="fw-bold text-dark">{{ $r->user?->nama ?? '-' }}</span>
                                <span class="text-muted small d-block">{{ $r->jenis_label }} • diajukan {{ $r->created_at->translatedFormat('d/m/Y H:i') }}</span>
                            </div>
                            <label class="form-label fw-bold text-secondary text-uppercase small mb-2">Alasan Penolakan <span class="text-danger">*</span></label>
                            <textarea name="admin_note" class="form-control rounded-3" rows="4" maxlength="500" required placeholder="Contoh: tanda tangan tidak sesuai dengan arsip identitas..."></textarea>
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-danger rounded-3 fw-semibold"><i class="bi bi-x-lg me-1"></i>Tolak Pengajuan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Modal perbesar tanda tangan --}}
        <div class="modal fade" id="sigModal{{ $r->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content border-0 shadow-lg rounded-4">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold text-dark small text-uppercase text-muted">Tanda Tangan — {{ $r->user?->nama ?? '-' }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body px-4 py-3 text-center">
                        <img src="{{ $r->tanda_tangan }}" alt="Tanda tangan" class="img-fluid border rounded-3 bg-light">
                    </div>
                    <div class="modal-footer border-0 justify-content-center pt-0">
                        <span class="text-muted small">Diajukan {{ $r->created_at->translatedFormat('d F Y, H:i') }}</span>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endforeach

@push('scripts')
<script>
    function copyResetLink(btn) {
        const url = btn.getAttribute('data-url');
        if (!url) return;
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(url).then(function () {
                flutterFeedback(btn, 'Link disalin!');
            });
        } else {
            // Fallback untuk http (ngrok/tunnel): seleksi text di elemen tersembunyi
            const ta = document.createElement('textarea');
            ta.value = url;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            flutterFeedback(btn, 'Link disalin!');
        }
    }
    function flutterFeedback(btn, message) {
        const original = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>' + message;
        btn.classList.add('btn-outline-success', 'text-success');
        setTimeout(function () {
            btn.innerHTML = original;
            btn.classList.remove('btn-outline-success', 'text-success');
        }, 1800);
    }
</script>
@endpush
@endsection