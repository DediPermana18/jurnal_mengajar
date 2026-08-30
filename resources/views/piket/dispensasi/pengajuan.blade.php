@extends('layouts.app')

@section('title', 'Approval Dispen - Guru Piket')

@section('content')
<div class="container-fluid px-0">

    {{-- Page Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="font-weight: 900; font-size: 1.75rem; letter-spacing: -0.02em;">
                Approval Dispen Siswa
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                Kirim <strong>Link Approval</strong> ke Waka Kesiswaan via WhatsApp. Setelah ditandatangani & disetujui lewat link, absensi kelas otomatis ditandai <strong>Dispen</strong>.
            </p>
        </div>
        <span class="text-muted small"><i class="bi bi-calendar3 me-1"></i>{{ now()->translatedFormat('l, d F Y') }}</span>
    </div>

    {{-- Alert Messages --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4 d-flex align-items-center gap-2" role="alert"
             style="background: #ecfdf5; color: #065f46; font-size: 0.9rem;">
            <i class="bi bi-check-circle-fill text-success fs-5"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4 d-flex align-items-center gap-2" role="alert"
             style="background: #fef2f2; color: #991b1b; font-size: 0.9rem;">
            <i class="bi bi-exclamation-triangle-fill text-danger fs-5"></i>
            <div>{{ session('error') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Stat Cards --}}
    <div class="row g-4 mb-4">
        <div class="col-12 col-sm-6 col-xl-4">
            <div class="stat-card-custom h-100">
                <div class="stat-card-title">Total Pending</div>
                <div class="stat-number-large text-warning">{{ number_format($totalPending) }}</div>
                <div class="stat-card-label">Menunggu persetujuan</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-4">
            <div class="stat-card-custom h-100">
                <div class="stat-card-title">Total Disetujui</div>
                <div class="stat-number-large text-success">{{ number_format($totalDisetujui) }}</div>
                <div class="stat-card-label">Dispen disetujui Guru Piket</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-4">
            <div class="stat-card-custom h-100">
                <div class="stat-card-title">Total Ditolak</div>
                <div class="stat-number-large text-danger">{{ number_format($totalDitolak) }}</div>
                <div class="stat-card-label">Dispen ditolak dengan catatan</div>
            </div>
        </div>
    </div>

    {{-- Filter Tab Status --}}
    <div class="mb-4">
        <ul class="nav nav-pills gap-2 flex-nowrap overflow-auto pb-1">
            <li class="nav-item">
                <a href="{{ route('piket.dispensasi.pengajuan', ['filter' => 'pending']) }}"
                   class="nav-link rounded-pill px-3 fw-semibold {{ $filter === 'pending' ? 'active' : '' }}">
                    Pending
                    <span class="badge rounded-pill {{ $filter === 'pending' ? 'bg-white text-warning' : 'bg-light text-muted border' }} ms-1">{{ $totalPending }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('piket.dispensasi.pengajuan', ['filter' => 'disetujui']) }}"
                   class="nav-link rounded-pill px-3 fw-semibold {{ $filter === 'disetujui' ? 'active' : '' }}">
                    Disetujui
                    <span class="badge rounded-pill {{ $filter === 'disetujui' ? 'bg-white text-success' : 'bg-light text-muted border' }} ms-1">{{ $totalDisetujui }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('piket.dispensasi.pengajuan', ['filter' => 'ditolak']) }}"
                   class="nav-link rounded-pill px-3 fw-semibold {{ $filter === 'ditolak' ? 'active' : '' }}">
                    Ditolak
                    <span class="badge rounded-pill {{ $filter === 'ditolak' ? 'bg-white text-danger' : 'bg-light text-muted border' }} ms-1">{{ $totalDitolak }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('piket.dispensasi.pengajuan', ['filter' => 'semua']) }}"
                   class="nav-link rounded-pill px-3 fw-semibold {{ $filter === 'semua' ? 'active' : '' }}">
                    Semua
                    <span class="badge rounded-pill {{ $filter === 'semua' ? 'bg-white text-primary' : 'bg-light text-muted border' }} ms-1">{{ $totalPending + $totalDisetujui + $totalDitolak }}</span>
                </a>
            </li>
        </ul>
    </div>

    {{-- Tabel Daftar Pengajuan --}}
    <div class="table-card-custom mb-4">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h5 class="fw-bold text-dark mb-0">Daftar Pengajuan Dispensasi</h5>
            <span class="text-muted small">Menampilkan {{ number_format($dataDispensasi->total()) }} pengajuan</span>
        </div>
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th>TANGGAL</th>
                        <th>SISWA</th>
                        <th>KELAS</th>
                        <th>JAM KE</th>
                        <th>ALASAN</th>
                        <th>STATUS</th>
                        <th class="text-end" style="min-width: 320px;">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dataDispensasi as $dispen)
                        <tr>
                            <td class="fw-semibold text-dark text-nowrap">
                                {{ $dispen->tanggal->translatedFormat('d/m/Y') }}
                                @if($dispen->isApproved())
                                    <div class="text-muted small fw-normal">Setujui: {{ $dispen->approver?->nama ?? '-' }}</div>
                                @elseif($dispen->status === \App\Models\DispensasiSiswa::STATUS_DITOLAK)
                                    <div class="text-muted small fw-normal">Diproses: {{ $dispen->approver?->nama ?? '-' }}</div>
                                @endif
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">{{ $dispen->siswa->nama ?? '-' }}</div>
                                <div class="text-muted small">NISN: {{ $dispen->siswa->nisn ?: '-' }}</div>
                            </td>
                            <td>{{ $dispen->siswa?->kelas?->nama ?? '-' }}</td>
                            <td>
                                <span class="badge bg-light text-dark border rounded-3 px-2 py-1">{{ $dispen->jam_ke_label }}</span>
                            </td>
                            <td style="max-width: 260px;">
                                <div class="text-wrap">{{ $dispen->alasan }}</div>
                                @if($dispen->status === \App\Models\DispensasiSiswa::STATUS_DITOLAK && $dispen->catatan_penolakan)
                                    <div class="small text-danger mt-1 border-top pt-1">
                                        <i class="bi bi-x-circle-fill me-1"></i>Catatan tolak: {{ $dispen->catatan_penolakan }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $dispen->status_badge }} rounded-pill px-2 py-2">{{ $dispen->status_label }}</span>
                            </td>
                            <td class="text-end text-nowrap">
                                <div class="d-inline-flex flex-wrap justify-content-end align-items-center gap-1">
                                    @if($dispen->isApproved())
                                        <a href="{{ route('piket.dispensasi.surat', $dispen->id) }}" target="_blank"
                                           class="btn btn-sm btn-outline-primary rounded-3" title="Lihat & cetak Surat Dispen Resmi">
                                            <i class="bi bi-file-earmark-text me-1"></i>Lihat Surat
                                        </a>
                                    @elseif($dispen->status === \App\Models\DispensasiSiswa::STATUS_PENDING)
                                        @if($dispen->approval_url)
                                            <a href="#" class="btn btn-sm btn-wa rounded-3"
                                               title="Kirim Link Approval langsung ke WhatsApp Waka Kesiswaan"
                                               data-wa-approval="{{ $dispen->approval_url }}"
                                               data-nama="{{ $dispen->siswa->nama ?? 'Siswa' }}"
                                               data-alasan="{{ $dispen->alasan }}"
                                               data-jam="{{ $dispen->jam_ke_label }}">
                                                <i class="bi bi-whatsapp me-1"></i>Kirim WA Approval
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-success rounded-3"
                                                    title="Salin Link Approval ke WhatsApp untuk diteruskan ke Waka Kesiswaan"
                                                    data-salin-link="{{ $dispen->approval_url }}">
                                                <i class="bi bi-clipboard me-1"></i>Salin Link WA
                                            </button>
                                            @php $qrSvgApproval = \App\Support\QrCodeHelper::svg($dispen->approval_url, 6); @endphp
                                            <button type="button" class="btn btn-sm btn-outline-dark rounded-3"
                                                    title="Tampilkan QR Approval untuk dipindai Waka Kesiswaan"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modalApprovalQr"
                                                    data-qr-target="qr-approval-{{ $dispen->id }}"
                                                    data-link="{{ $dispen->approval_url }}">
                                                <i class="bi bi-qr-code me-1"></i>QR Approval
                                            </button>
                                            <div class="d-none" id="qr-approval-{{ $dispen->id }}">{!! $qrSvgApproval !!}</div>
                                        @endif
                                        <button type="button" class="btn btn-sm btn-danger rounded-3"
                                                data-bs-toggle="modal" data-bs-target="#modalTolak{{ $dispen->id }}"
                                                title="Tolak dispensasi">
                                            <i class="bi bi-x-lg me-1"></i>Tolak
                                        </button>
                                    @else
                                        <span class="text-muted small">-</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                                Belum ada pengajuan dispensasi pada filter ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($dataDispensasi->hasPages())
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-4 pt-3 border-top">
                <div class="text-muted small mb-3 mb-md-0">
                    Menampilkan <strong>{{ $dataDispensasi->firstItem() ?? 0 }}</strong>-<strong>{{ $dataDispensasi->lastItem() ?? 0 }}</strong> dari <strong>{{ $dataDispensasi->total() }}</strong> pengajuan
                </div>
                {{ $dataDispensasi->links() }}
            </div>
        @endif
    </div>
</div>

{{-- Modal Catatan Penolakan --}}
@foreach($dataDispensasi as $dispen)
    @if($dispen->status === \App\Models\DispensasiSiswa::STATUS_PENDING)
        <div class="modal fade" id="modalTolak{{ $dispen->id }}" tabindex="-1" aria-labelledby="modalTolakLabel{{ $dispen->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4">
                    <form action="{{ route('piket.dispensasi.reject', $dispen->id) }}" method="POST">
                        @csrf
                        <div class="modal-header border-0 pb-0">
                            <h5 class="modal-title fw-bold text-dark" id="modalTolakLabel{{ $dispen->id }}">
                                <i class="bi bi-x-circle-fill text-danger me-1"></i>Tolak Dispen Siswa
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body px-4 py-3">
                            <div class="mb-3">
                                <div class="d-flex flex-column gap-1">
                                    <span class="fw-bold text-dark">{{ $dispen->siswa->nama ?? '-' }}</span>
                                    <span class="text-muted small">
                                        {{ $dispen->tanggal->translatedFormat('d/m/Y') }} &bull; {{ $dispen->jam_ke_label }} &bull; {{ $dispen->alasan }}
                                    </span>
                                </div>
                            </div>
                            <label class="form-label fw-bold text-secondary text-uppercase small mb-2">
                                Catatan Penolakan <span class="text-muted fw-normal">(opsional)</span>
                            </label>
                            <textarea name="catatan_penolakan" class="form-control rounded-3" rows="4" maxlength="500"
                                      placeholder="Tuliskan alasan penolakan pengajuan ini..."></textarea>
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-danger rounded-3 fw-semibold">
                                <i class="bi bi-x-lg me-1"></i>Tolak Dispen
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endforeach
{{-- Modal QR Approval --}}
<div class="modal fade" id="modalApprovalQr" tabindex="-1" aria-labelledby="modalApprovalQrLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark" id="modalApprovalQrLabel">
                    <i class="bi bi-qr-code text-success me-1"></i> QR Approval Dispen
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-3 text-center">
                <p class="text-muted small mb-3">
                    Guru Piket dapat memindai, atau mengirimkan tautan di bawah ini ke
                    Waka Kesiswaan / Penyetuju via WhatsApp.
                </p>
                <div class="qr-approval-preview d-inline-block border rounded-3 p-3 bg-white shadow-sm"></div>
                <div id="modalApprovalQrLink" class="text-break text-muted small mt-3 mb-0"></div>
            </div>
            <div class="modal-footer border-0 justify-content-center">
                <a href="#" id="btnWaApproval" target="_blank" class="btn btn-success rounded-3 fw-semibold">
                    <i class="bi bi-whatsapp me-1"></i> Kirim via WhatsApp
                </a>
                <button type="button" id="btnSalinQrLink" class="btn btn-outline-success rounded-3">
                    <i class="bi bi-clipboard me-1"></i> Salin Link
                </button>
                <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<style>
    .qr-approval-preview svg {
        width: 220px;
        height: 220px;
        display: block;
    }

    /* Tombol hijau khas WhatsApp */
    .btn-wa {
        background-color: #25D366 !important;
        border-color: #25D366 !important;
        color: #fff !important;
    }
    .btn-wa:hover,
    .btn-wa:focus,
    .btn-wa:active {
        background-color: #1EBE5D !important;
        border-color: #1EBE5D !important;
        color: #fff !important;
    }
</style>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const noWaWaka = @json($noWaWaka);

        // ===== Kirim WA Approval (wa.me) =====
        document.querySelectorAll('[data-wa-approval]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const link   = btn.dataset.waApproval || '';
                const nama   = btn.dataset.nama || 'Siswa';
                const alasan = btn.dataset.alasan || '';
                const jam    = btn.dataset.jam || '';

                const teks = 'Halo Bapak/Ibu Waka Kesiswaan, berikut adalah pengajuan Dispensasi Siswa:\n'
                    + '- Nama: ' + nama + '\n'
                    + '- Alasan: ' + alasan + '\n'
                    + '- Jam Ke: ' + jam + '\n'
                    + '\n'
                    + 'Mohon untuk menyetujui dan menandatangani surat dispensasi melalui link berikut:\n'
                    + link + '\n'
                    + '\n'
                    + 'Terima kasih.';

                const base = noWaWaka ? ('https://wa.me/' + noWaWaka) : 'https://wa.me/';
                const url  = base + '?text=' + encodeURIComponent(teks);
                window.open(url, '_blank');
            });
        });

        // ===== Salin Link Approval WA =====
        function flashButton(btn, msg) {
            const old = btn.innerHTML;
            btn.innerHTML = msg;
            setTimeout(function () { btn.innerHTML = old; }, 1600);
        }

        document.querySelectorAll('[data-salin-link]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const link = btn.dataset.salinLink;
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(link).then(function () {
                        flashButton(btn, '<i class="bi bi-check-lg me-1"></i>Link Disalin!');
                    }, function () {
                        window.prompt('Salin link approval ini:', link);
                    });
                } else {
                    window.prompt('Salin link approval ini:', link);
                }
            });
        });

        // ===== QR Approval Modal =====
        const qrPreview   = document.querySelector('#modalApprovalQr .qr-approval-preview');
        const qrLinkText  = document.getElementById('modalApprovalQrLink');
        const btnWaApproval = document.getElementById('btnWaApproval');
        const btnSalinQrLink = document.getElementById('btnSalinQrLink');

        document.querySelectorAll('[data-qr-target]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const qrEl  = document.getElementById(btn.dataset.qrTarget);
                const link  = btn.dataset.link;
                const waText = 'Persetujuan Surat Dispen Digital — mohon diproses: ' + link;

                qrPreview.innerHTML = qrEl ? qrEl.innerHTML : '';
                qrLinkText.textContent = link;
                btnWaApproval.href = 'https://wa.me/?text=' + encodeURIComponent(waText);
                btnSalinQrLink.dataset.link = link;
            });
        });

        if (btnSalinQrLink) {
            btnSalinQrLink.addEventListener('click', function () {
                const link = this.dataset.link || '';
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(link).then(function () {
                        flashButton(btnSalinQrLink, '<i class="bi bi-check-lg me-1"></i>Disalin!');
                    }, function () {
                        window.prompt('Salin link approval ini:', link);
                    });
                } else {
                    window.prompt('Salin link approval ini:', link);
                }
            });
        }
    });
</script>
@endpush