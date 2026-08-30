@extends('layouts.app')

@section('title', 'Approval Izin Guru - Guru Piket')

@section('content')
<div class="container-fluid px-0">

    {{-- Page Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="font-weight: 900; font-size: 1.75rem; letter-spacing: -0.02em;">
                Approval Izin Guru
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                Verifikasi pengajuan izin (langkah pertama), lalu teruskan link Waka/Kepsek via WhatsApp.
                Alur aktif: <strong>{{ $level }} level</strong>.
            </p>
        </div>
        <span class="text-muted small"><i class="bi bi-calendar3 me-1"></i>{{ now()->translatedFormat('l, d F Y') }}</span>
    </div>

    {{-- Alert --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4 d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-check-circle-fill text-success fs-5"></i><div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4 d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-exclamation-triangle-fill text-danger fs-5"></i><div>{{ session('error') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4" role="alert">
            <ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Stat --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card-custom"><div class="stat-card-title">Pending Piket</div><div class="stat-number-large text-warning">{{ $totalPendingPiket }}</div><div class="stat-card-label">Perlu verifikasi</div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card-custom"><div class="stat-card-title">Pending Waka</div><div class="stat-number-large text-info">{{ $totalPendingWaka }}</div><div class="stat-card-label">Menunggu Waka</div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card-custom"><div class="stat-card-title">Pending Kepsek</div><div class="stat-number-large text-primary">{{ $totalPendingKepsek }}</div><div class="stat-card-label">Menunggu Kepsek</div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card-custom"><div class="stat-card-title">Disetujui / Ditolak</div><div class="stat-number-large text-success">{{ $totalDisetujui }}<span class="fs-4 text-danger"> / {{ $totalDitolak }}</span></div><div class="stat-card-label">Final</div></div>
        </div>
    </div>

    {{-- Filter --}}
    <div class="mb-4">
        <ul class="nav nav-pills gap-2 flex-nowrap overflow-auto pb-1">
            <li class="nav-item"><a href="{{ route('piket.izin.index', ['filter' => 'pending_piket']) }}" class="nav-link rounded-pill px-3 fw-semibold {{ $filter === 'pending_piket' ? 'active' : '' }}">Pending Piket <span class="badge rounded-pill {{ $filter==='pending_piket' ? 'bg-white text-warning' : 'bg-light text-muted border' }} ms-1">{{ $totalPendingPiket }}</span></a></li>
            <li class="nav-item"><a href="{{ route('piket.izin.index', ['filter' => 'pending_waka']) }}" class="nav-link rounded-pill px-3 fw-semibold {{ $filter === 'pending_waka' ? 'active' : '' }}">Pending Waka <span class="badge rounded-pill {{ $filter==='pending_waka' ? 'bg-white text-info' : 'bg-light text-muted border' }} ms-1">{{ $totalPendingWaka }}</span></a></li>
            <li class="nav-item"><a href="{{ route('piket.izin.index', ['filter' => 'pending_kepsek']) }}" class="nav-link rounded-pill px-3 fw-semibold {{ $filter === 'pending_kepsek' ? 'active' : '' }}">Pending Kepsek <span class="badge rounded-pill {{ $filter==='pending_kepsek' ? 'bg-white text-primary' : 'bg-light text-muted border' }} ms-1">{{ $totalPendingKepsek }}</span></a></li>
            <li class="nav-item"><a href="{{ route('piket.izin.index', ['filter' => 'disetujui']) }}" class="nav-link rounded-pill px-3 fw-semibold {{ $filter === 'disetujui' ? 'active' : '' }}">Disetujui <span class="badge rounded-pill {{ $filter==='disetujui' ? 'bg-white text-success' : 'bg-light text-muted border' }} ms-1">{{ $totalDisetujui }}</span></a></li>
            <li class="nav-item"><a href="{{ route('piket.izin.index', ['filter' => 'ditolak']) }}" class="nav-link rounded-pill px-3 fw-semibold {{ $filter === 'ditolak' ? 'active' : '' }}">Ditolak <span class="badge rounded-pill {{ $filter==='ditolak' ? 'bg-white text-danger' : 'bg-light text-muted border' }} ms-1">{{ $totalDitolak }}</span></a></li>
            <li class="nav-item"><a href="{{ route('piket.izin.index', ['filter' => 'semua']) }}" class="nav-link rounded-pill px-3 fw-semibold {{ $filter === 'semua' ? 'active' : '' }}">Semua</a></li>
        </ul>
    </div>

    {{-- Tabel --}}
    <div class="table-card-custom mb-4">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h5 class="fw-bold text-dark mb-0">Daftar Pengajuan Izin Guru</h5>
            <span class="text-muted small">Menampilkan {{ number_format($daftarIzin->total()) }} pengajuan</span>
        </div>
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th>TANGGAL</th>
                        <th>NAMA GURU</th>
                        <th>ALASAN</th>
                        <th>STATUS</th>
                        <th class="text-end" style="min-width: 320px;">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($daftarIzin as $izin)
                        <tr>
                            <td class="fw-semibold text-dark text-nowrap">{{ $izin->tanggal->translatedFormat('d/m/Y') }}</td>
                            <td>
                                <div class="fw-semibold text-dark">{{ $izin->user?->nama ?? '-' }}</div>
                                <div class="text-muted small">NIP: {{ $izin->user?->nip ?: '-' }}</div>
                            </td>
                            <td style="max-width: 260px;">
                                <div class="text-wrap">{{ $izin->alasan }}</div>
                                @if($izin->isRejected() && $izin->catatan_penolakan)
                                    <div class="small text-danger mt-1 border-top pt-1"><i class="bi bi-x-circle-fill me-1"></i>Catatan tolak: {{ $izin->catatan_penolakan }}</div>
                                @endif
                            </td>
                            <td><span class="badge {{ $izin->status_badge }} rounded-pill px-2 py-2">{{ $izin->status_label }}</span></td>
                            <td class="text-end text-nowrap">
                                <div class="d-inline-flex flex-wrap justify-content-end align-items-center gap-1">
                                    @if($izin->status === \App\Models\IzinGuru::STATUS_PENDING_PIKET)
                                        <form action="{{ route('piket.izin.approve', $izin->id) }}" method="POST" class="d-inline"
                                              onsubmit="return confirm('Verifikasi izin {{ addslashes($izin->user?->nama ?? 'guru') }} dari Piket?')">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success rounded-3" title="Verifikasi Step 1 (Piket)">
                                                <i class="bi bi-check-lg me-1"></i>Verifikasi Piket
                                            </button>
                                        </form>
                                        <button type="button" class="btn btn-sm btn-danger rounded-3"
                                                data-bs-toggle="modal" data-bs-target="#modalTolak{{ $izin->id }}">
                                            <i class="bi bi-x-lg me-1"></i>Tolak
                                        </button>
                                    @elseif($izin->status === \App\Models\IzinGuru::STATUS_PENDING_WAKA || $izin->status === \App\Models\IzinGuru::STATUS_PENDING_KEPSEK)
                                        @if($izin->approval_url)
                                            @php
                                                $tujuan = $izin->status === \App\Models\IzinGuru::STATUS_PENDING_WAKA ? 'Waka' : 'Kepala Sekolah';
                                                $noTujuan = $izin->status === \App\Models\IzinGuru::STATUS_PENDING_WAKA ? $noWaWaka : $noWaKepsek;
                                            @endphp
                                            <a href="#" class="btn btn-sm btn-wa rounded-3"
                                               title="Kirim Link Approval ke {{ $tujuan }}"
                                               data-wa-approval="{{ $izin->approval_url }}"
                                               data-nama="{{ addslashes($izin->user?->nama ?? 'Guru') }}"
                                               data-tanggal="{{ $izin->tanggal->translatedFormat('d/m/Y') }}"
                                               data-alasan="{{ addslashes($izin->alasan) }}"
                                               data-tujuan="{{ $tujuan }}"
                                               data-no="{{ $noTujuan }}">
                                                <i class="bi bi-whatsapp me-1"></i>Kirim KA {{ $tujuan }}
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-success rounded-3"
                                                    data-salin-link="{{ $izin->approval_url }}" title="Salin Link Approval">
                                                <i class="bi bi-clipboard me-1"></i>Salin
                                            </button>
                                            @php $qrSvg = \App\Support\QrCodeHelper::svg($izin->approval_url, 6); @endphp
                                            <button type="button" class="btn btn-sm btn-outline-dark rounded-3"
                                                    data-bs-toggle="modal" data-bs-target="#modalApprovalQr"
                                                    data-qr-target="qr-izin-{{ $izin->id }}"
                                                    data-link="{{ $izin->approval_url }}">
                                                <i class="bi bi-qr-code me-1"></i>QR
                                            </button>
                                            <div class="d-none" id="qr-izin-{{ $izin->id }}">{!! $qrSvg !!}</div>
                                        @endif
                                    @else
                                        <span class="text-muted small">-</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>Tidak ada pengajuan izin pada filter ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($daftarIzin->hasPages())
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-4 pt-3 border-top">
                <div class="text-muted small mb-3 mb-md-0">Menampilkan <strong>{{ $daftarIzin->firstItem() ?? 0 }}</strong>-<strong>{{ $daftarIzin->lastItem() ?? 0 }}</strong> dari <strong>{{ $daftarIzin->total() }}</strong> pengajuan</div>
                {{ $daftarIzin->links() }}
            </div>
        @endif
    </div>

    {{-- Info alur --}}
    <div class="alert bg-light-subtle border rounded-4 small text-muted mb-0">
        <strong><i class="bi bi-diagram-3 me-1"></i> Alur aktif ({{ $level }} level):</strong>
        @if($level === 3) Pengajuan <i class="bi bi-arrow-right"></i> Verifikasi Piket <i class="bi bi-arrow-right"></i> Waka <i class="bi bi-arrow-right"></i> Kepsek <i class="bi bi-arrow-right"></i> Disetujui.
        @elseif($level === 2) Pengajuan <i class="bi bi-arrow-right"></i> Verifikasi Piket <i class="bi bi-arrow-right"></i> Kepsek <i class="bi bi-arrow-right"></i> Disetujui.
        @else Pengajuan <i class="bi bi-arrow-right"></i> Verifikasi Piket <i class="bi bi-arrow-right"></i> Disetujui. (tanpa Waka/Kepsek)
        @endif
    </div>
</div>

{{-- Modal Tolak --}}
@foreach($daftarIzin as $izin)
    @if($izin->isPending())
        <div class="modal fade" id="modalTolak{{ $izin->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4">
                    <form action="{{ route('piket.izin.reject', $izin->id) }}" method="POST">
                        @csrf
                        <div class="modal-header border-0 pb-0">
                            <h5 class="modal-title fw-bold text-dark"><i class="bi bi-x-circle-fill text-danger me-1"></i>Tolak Izin Guru</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body px-4 py-3">
                            <div class="mb-3">
                                <span class="fw-bold text-dark">{{ $izin->user?->nama ?? '-' }}</span>
                                <span class="text-muted small d-block">{{ $izin->tanggal->translatedFormat('d/m/Y') }} &bull; {{ $izin->alasan }}</span>
                            </div>
                            <label class="form-label fw-bold text-secondary text-uppercase small mb-2">Catatan Penolakan <span class="text-danger">*</span></label>
                            <textarea name="catatan_penolakan" class="form-control rounded-3" rows="4" maxlength="1000" required placeholder="Alasan penolakan izin ini..."></textarea>
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-danger rounded-3 fw-semibold"><i class="bi bi-x-lg me-1"></i>Tolak Izin</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endforeach

{{-- Modal QR --}}
<div class="modal fade" id="modalApprovalQr" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-qr-code text-success me-1"></i> QR Approval Izin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-3 text-center">
                <p class="text-muted small mb-3">Kirim tautan di bawah ke Waka / Kepala Sekolah via WhatsApp.</p>
                <div class="qr-approval-preview d-inline-block border rounded-3 p-3 bg-white shadow-sm"></div>
                <div id="modalApprovalQrLink" class="text-break text-muted small mt-3 mb-0"></div>
            </div>
            <div class="modal-footer border-0 justify-content-center">
                <a href="#" id="btnWaApproval" target="_blank" class="btn btn-success rounded-3 fw-semibold"><i class="bi bi-whatsapp me-1"></i> Kirim via WhatsApp</a>
                <button type="button" id="btnSalinQrLink" class="btn btn-outline-success rounded-3"><i class="bi bi-clipboard me-1"></i> Salin Link</button>
                <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<style>
    .qr-approval-preview svg { width: 220px; height: 220px; display: block; }
    .btn-wa { background-color: #25D366 !important; border-color: #25D366 !important; color: #fff !important; }
    .btn-wa:hover, .btn-wa:focus, .btn-wa:active { background-color: #1EBE5D !important; border-color: #1EBE5D !important; color: #fff !important; }
</style>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const noWaWaka = @json($noWaWaka);
        const noWaKepsek = @json($noWaKepsek);

        // Kirim WA approval
        document.querySelectorAll('[data-wa-approval]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const link = btn.dataset.waApproval || '';
                const nama = btn.dataset.nama || 'Guru';
                const tanggal = btn.dataset.tanggal || '';
                const alasan = btn.dataset.alasan || '';
                const tujuan = btn.dataset.tujuan || 'Waka';
                const no = (tujuan === 'Kepala Sekolah' ? noWaKepsek : noWaWaka);

                const teks = 'Halo Bapak/Ibu ' + tujuan + ',\n'
                    + 'berikut pengajuan Izin Guru:\n'
                    + '- Nama: ' + nama + '\n'
                    + '- Tanggal: ' + tanggal + '\n'
                    + '- Alasan: ' + alasan + '\n\n'
                    + 'Mohon untuk menyetujui dan menandatangani izin melalui link berikut:\n'
                    + link + '\n\nTerima kasih.';

                const base = no ? ('https://wa.me/' + no) : 'https://wa.me/';
                window.open(base + '?text=' + encodeURIComponent(teks), '_blank');
            });
        });

        function flashButton(btn, msg) { const old = btn.innerHTML; btn.innerHTML = msg; setTimeout(function () { btn.innerHTML = old; }, 1600); }

        document.querySelectorAll('[data-salin-link]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const link = btn.dataset.salinLink;
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(link).then(function () { flashButton(btn, '<i class="bi bi-check-lg me-1"></i>Disalin!'); }, function () { window.prompt('Salin link approval:', link); });
                } else { window.prompt('Salin link approval:', link); }
            });
        });

        const qrPreview = document.querySelector('#modalApprovalQr .qr-approval-preview');
        const qrLinkText = document.getElementById('modalApprovalQrLink');
        const btnWaApproval = document.getElementById('btnWaApproval');
        const btnSalinQrLink = document.getElementById('btnSalinQrLink');

        document.querySelectorAll('[data-qr-target]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const qrEl = document.getElementById(btn.dataset.qrTarget);
                const link = btn.dataset.link;
                qrPreview.innerHTML = qrEl ? qrEl.innerHTML : '';
                qrLinkText.textContent = link;
                btnWaApproval.href = 'https://wa.me/?text=' + encodeURIComponent('Persetujuan surat izin guru digital — mohon diproses: ' + link);
                btnSalinQrLink.dataset.link = link;
            });
        });
    });
</script>
@endpush
