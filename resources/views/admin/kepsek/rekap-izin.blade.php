@extends('layouts.app')

@section('title', 'Persetujuan & Rekap Izin Guru - Kepala Sekolah')

@push('styles')
<style>
    .stat-badge-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 0.875rem;
        padding: 1rem 1.25rem;
        box-shadow: 0 1px 2px rgba(0,0,0,0.03);
    }
    .table-rekap-izin th {
        background: #f8fafc;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        font-weight: 700;
        padding: 0.85rem 1rem;
        border-bottom: 1px solid #e2e8f0;
    }
    .table-rekap-izin td {
        padding: 0.85rem 1rem;
        vertical-align: middle;
        font-size: 0.85rem;
        border-bottom: 1px solid #f1f5f9;
    }
    #canvasTtdKepsek {
        width: 100%;
        height: 220px;
        background: #ffffff;
        border: 1px dashed #cbd5e1;
        border-radius: 0.5rem;
        touch-action: none;
        cursor: crosshair;
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">

    {{-- Page Header --}}
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="{{ route('kepsek.dashboard') }}" class="text-decoration-none text-muted text-xs d-flex align-items-center gap-1">
                    <i class="bi bi-person-workspace"></i> Dashboard Kepala Sekolah
                </a>
                <span class="text-muted text-xs">/</span>
                <span class="text-xs fw-semibold text-primary">Persetujuan Izin Guru</span>
            </div>
            <h2 class="fw-black text-dark mb-1" style="font-weight: 900; font-size: 1.5rem; letter-spacing: -0.02em;">
                Modul Persetujuan Kepala Sekolah
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.875rem;">
                Verifikasi akhir dan pengesahan tanda tangan digital permohonan izin guru.
            </p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm d-flex align-items-center gap-2 mb-4" role="alert">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm d-flex align-items-center gap-2 mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <div>{{ session('error') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Summary Mini Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-badge-card d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted text-2xs text-uppercase fw-bold">Menunggu Kepsek</div>
                    <div class="fs-4 fw-bold text-warning-emphasis">{{ $totalPendingKepsek }}</div>
                </div>
                <div class="rounded-3 bg-warning-subtle text-warning p-2 fs-5">
                    <i class="bi bi-hourglass-split"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-badge-card d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted text-2xs text-uppercase fw-bold">Disetujui Final</div>
                    <div class="fs-4 fw-bold text-success">{{ $totalDisetujui }}</div>
                </div>
                <div class="rounded-3 bg-success-subtle text-success p-2 fs-5">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-badge-card d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted text-2xs text-uppercase fw-bold">Ditolak</div>
                    <div class="fs-4 fw-bold text-danger">{{ $totalDitolak }}</div>
                </div>
                <div class="rounded-3 bg-danger-subtle text-danger p-2 fs-5">
                    <i class="bi bi-x-octagon-fill"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-badge-card d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted text-2xs text-uppercase fw-bold">Total Pengajuan</div>
                    <div class="fs-4 fw-bold text-dark">{{ $totalPengajuan }}</div>
                </div>
                <div class="rounded-3 bg-light text-secondary p-2 fs-5">
                    <i class="bi bi-folder2-open"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter & Search Card --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3 p-md-4">
            <form method="GET" action="{{ route('kepsek.rekap-izin') }}">
                <div class="row g-2 align-items-center">
                    <div class="col-12 col-md-4">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control bg-light border-start-0 text-xs" placeholder="Cari nama guru atau NIP..." value="{{ request('search') }}">
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <input type="date" name="tanggal" class="form-control text-xs" value="{{ request('tanggal') }}">
                    </div>
                    <div class="col-6 col-md-3">
                        <select name="status" class="form-select text-xs">
                            <option value="">-- Semua Status --</option>
                            <option value="{{ \App\Models\IzinGuru::STATUS_PENDING_KEPSEK }}" {{ (request('status') ?? \App\Models\IzinGuru::STATUS_PENDING_KEPSEK) === \App\Models\IzinGuru::STATUS_PENDING_KEPSEK ? 'selected' : '' }}>Menunggu Kepsek</option>
                            <option value="{{ \App\Models\IzinGuru::STATUS_DISETUJUI }}" {{ request('status') === \App\Models\IzinGuru::STATUS_DISETUJUI ? 'selected' : '' }}>Disetujui</option>
                            <option value="{{ \App\Models\IzinGuru::STATUS_DITOLAK }}" {{ request('status') === \App\Models\IzinGuru::STATUS_DITOLAK ? 'selected' : '' }}>Ditolak</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm rounded-3 w-100 text-xs fw-semibold"><i class="bi bi-filter me-1"></i> Filter</button>
                        <a href="{{ route('kepsek.rekap-izin') }}" class="btn btn-light border btn-sm rounded-3" title="Reset Filter"><i class="bi bi-arrow-counterclockwise"></i></a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Main Table Card --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="table-responsive">
            <table class="table table-hover table-rekap-izin mb-0">
                <thead>
                    <tr>
                        <th style="width: 50px;">No</th>
                        <th>Nama Guru</th>
                        <th>Tanggal & Kategori</th>
                        <th>Alasan & Tugas</th>
                        <th>Status Approval</th>
                        <th class="text-center">Status TTD</th>
                        <th class="text-end" style="min-width: 170px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($izinList as $index => $izin)
                        <tr>
                            <td class="text-muted small">{{ $izinList->firstItem() + $index }}</td>
                            <td>
                                <div class="fw-semibold text-dark">{{ $izin->user?->nama ?? '-' }}</div>
                                <div class="text-muted text-2xs">NIP: {{ $izin->user?->nip ?: '-' }}</div>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">{{ $izin->tanggal->translatedFormat('d F Y') }}</div>
                                <div class="text-muted text-2xs">{{ $izin->tanggal->translatedFormat('l') }} &bull; {{ $izin->kategori_izin_label }}</div>
                            </td>
                            <td>
                                <div class="text-dark fw-medium text-truncate" style="max-width: 220px;" title="{{ $izin->alasan }}">{{ $izin->alasan }}</div>
                                @if($izin->tugas_siswa)
                                    <div class="text-muted text-2xs text-truncate" style="max-width: 220px;" title="{{ $izin->tugas_siswa }}"><i class="bi bi-pencil-square me-1"></i>{{ $izin->tugas_siswa }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $izin->status_badge }} rounded-pill px-2.5 py-1 text-2xs">{{ $izin->status_label }}</span>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <span class="badge {{ $izin->has_ttd_guru ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} rounded-circle p-1" title="TTD Guru"><i class="bi bi-pencil"></i></span>
                                    <span class="badge {{ $izin->has_ttd_waka ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} rounded-circle p-1" title="TTD Waka SDM"><i class="bi bi-check2-circle"></i></span>
                                    <span class="badge {{ $izin->has_ttd_kepsek ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} rounded-circle p-1" title="TTD Kepsek"><i class="bi bi-award"></i></span>
                                </div>
                            </td>
                            <td class="text-end">
                                <div class="d-flex align-items-center justify-content-end gap-1">
                                    @if($izin->status === \App\Models\IzinGuru::STATUS_PENDING_KEPSEK)
                                        <button type="button" class="btn btn-sm btn-success rounded-3 text-xs fw-semibold shadow-sm d-inline-flex align-items-center gap-1"
                                                onclick="openTtdKepsekModal({{ $izin->id }}, '{{ addslashes($izin->user?->nama ?? 'Guru') }}')"
                                                title="Proses TTD Kepsek">
                                            <i class="bi bi-pencil-square"></i>
                                            <span>Tinjau & Tanda Tangan</span>
                                        </button>
                                        @if($izin->token_kepsek)
                                            <a href="{{ route('izin.approval.show', $izin->token_kepsek) }}" target="_blank"
                                               class="btn btn-sm btn-outline-primary rounded-3 text-xs"
                                               title="Buka Form Standalone TTD Kepsek">
                                                <i class="bi bi-box-arrow-up-right"></i>
                                            </a>
                                        @endif
                                        <button type="button" class="btn btn-sm btn-outline-danger rounded-3 text-xs"
                                                onclick="openRejectKepsekModal({{ $izin->id }}, '{{ addslashes($izin->user?->nama ?? 'Guru') }}')"
                                                title="Tolak Pengajuan">
                                            <i class="bi bi-x-circle"></i>
                                        </button>
                                    @endif

                                    <button type="button" class="btn btn-sm btn-light border rounded-3 text-xs"
                                            onclick="openDetailModal({{ $izin->id }})" title="Detail">
                                        <i class="bi bi-eye"></i>
                                    </button>

                                    <button type="button" class="btn btn-sm btn-light border rounded-3 text-xs"
                                            onclick="openDokumenModal({{ $izin->id }})" title="Cetak Surat">
                                        <i class="bi bi-printer"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                                Tidak ada pengajuan izin yang memerlukan persetujuan Kepala Sekolah saat ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($izinList->hasPages())
            <div class="card-footer bg-white border-top p-3">
                {{ $izinList->links() }}
            </div>
        @endif
    </div>

</div>

<!-- MODAL TTD KEPSEK -->
<div class="modal fade" id="modalTtdKepsek" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark fs-6">
                    <i class="bi bi-signature me-2 text-success"></i> Tanda Tangan Kepala Sekolah
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formTtdKepsek" method="POST" action="">
                @csrf
                <div class="modal-body p-4">
                    <p class="text-muted text-xs mb-3">
                        Goreskan tanda tangan Kepala Sekolah untuk mengesahkan permohonan izin atas nama:
                        <strong id="ttdNamaGuruKepsek" class="text-dark"></strong>.
                    </p>

                    @if($isKepsekAuth)
                        <div class="alert alert-info border-0 rounded-3 d-flex align-items-center gap-2 py-2 px-3 mb-3">
                            <i class="bi bi-person-lock-fill fs-5"></i>
                            <div>
                                <div class="text-uppercase fw-bold text-muted" style="font-size: 0.68rem; letter-spacing: 0.05em;">Penandatangan</div>
                                <div class="fw-semibold text-dark">
                                    <i class="bi bi-person-check me-1 text-success"></i>{{ Auth::user()->nama }}
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1" style="font-size: 0.7rem;">Terkunci · Mode Login</span>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="approved_by_kepsek_id" value="{{ Auth::id() }}">
                    @else
                        @php $kList = $daftarKepsek ?? collect(); @endphp
                        @if($kList->count() === 1)
                            @php $singleK = $kList->first(); @endphp
                            <div class="mb-3">
                                <label for="selectKepsek" class="form-label small fw-semibold text-secondary">
                                    Pejabat Kepala Sekolah
                                </label>
                                <select name="approved_by_kepsek_id" id="selectKepsek" class="form-select rounded-3" required>
                                    <option value="{{ $singleK->id }}" selected>{{ $singleK->nama }} ({{ $singleK->nip ?? 'Non-NIP' }})</option>
                                </select>
                                <input type="hidden" name="approved_by_kepsek_id" value="{{ $singleK->id }}">
                                <div class="form-text small"><i class="bi bi-person-check me-1"></i>Satu pejabat Kepala Sekolah terdaftar — otomatis terpilih.</div>
                            </div>
                        @elseif($kList->isNotEmpty())
                            <div class="mb-3">
                                <label for="selectKepsek" class="form-label small fw-semibold text-secondary">
                                    Pilih Pejabat Kepala Sekolah <span class="text-danger">*</span>
                                </label>
                                <select name="approved_by_kepsek_id" id="selectKepsek" class="form-select rounded-3" required>
                                    <option value="" disabled selected>-- Pilih Pejabat Kepala Sekolah --</option>
                                    @foreach($kList as $kp)
                                        <option value="{{ $kp->id }}">{{ $kp->nama }} ({{ $kp->nip ?? 'Non-NIP' }})</option>
                                    @endforeach
                                </select>
                            </div>
                        @else
                            <input type="hidden" name="approved_by_kepsek_id" value="">
                        @endif
                    @endif

                    <canvas id="canvasTtdKepsek" width="500" height="220">Browser tidak mendukung Canvas.</canvas>
                    <div class="d-flex justify-content-end my-2">
                        <button type="button" id="btnClearTtdKepsek" class="btn btn-sm btn-outline-danger rounded-3 text-xs">
                            <i class="bi bi-eraser me-1"></i> Hapus Canvas
                        </button>
                    </div>
                    <input type="hidden" name="ttd_kepsek" id="ttdKepsekInput" value="">
                </div>
                <div class="modal-footer border-0 pt-0 px-4 pb-4">
                    <button type="button" class="btn btn-light rounded-3 text-xs" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success rounded-3 text-xs fw-semibold px-4 shadow-sm">
                        <i class="bi bi-check2-circle me-1"></i> Setujui & Tanda Tangan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL REJECT KEPSEK -->
<div class="modal fade" id="modalRejectKepsek" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-danger fs-6"><i class="bi bi-x-circle me-2"></i> Penolakan Izin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formRejectKepsek" method="POST" action="">
                @csrf
                <div class="modal-body p-4">
                    <p class="text-muted text-xs mb-3">Tolak pengajuan izin atas nama <strong id="rejectNamaGuruKepsek" class="text-dark"></strong>.</p>
                    <div class="mb-3">
                        <label class="form-label text-xs fw-semibold text-secondary">Catatan Penolakan <span class="text-danger">*</span></label>
                        <textarea name="catatan_penolakan" rows="3" class="form-control text-xs" placeholder="Tuliskan alasan penolakan..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 px-4 pb-4">
                    <button type="button" class="btn btn-light rounded-3 text-xs" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger rounded-3 text-xs fw-semibold px-4">Tolak Izin</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL DETAIL IZIN -->
<div class="modal fade" id="modalDetailIzin" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark fs-6"><i class="bi bi-info-circle me-2 text-primary"></i> Detail Pengajuan Izin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="modalDetailBody"></div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light rounded-3 text-xs" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DOKUMEN CETAK SURAT -->
<div class="modal fade" id="modalDokumen" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark fs-6"><i class="bi bi-file-earmark-text me-2 text-primary"></i> Dokumen Surat Perizinan Guru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div id="dokumenSurat" class="border rounded-3 p-4 bg-white shadow-sm"></div>
            </div>
            <div class="modal-footer border-0 justify-content-between pt-0 px-4 pb-4">
                <span id="dokumenNamaGuru" class="text-muted text-xs"></span>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light border rounded-3 text-xs" data-bs-dismiss="modal">Tutup</button>
                    <a id="btnDownloadDokumen" href="#" target="_blank" class="btn btn-outline-primary rounded-3 text-xs fw-semibold d-none"><i class="bi bi-download me-1"></i> Unduh Lampiran</a>
                    <button type="button" onclick="printDokumen()" class="btn btn-primary rounded-3 text-xs fw-semibold"><i class="bi bi-printer me-1"></i> Cetak Surat</button>
                </div>
            </div>
        </div>
    </div>
</div>

@php
    $detailData = $izinList->map(function ($i) {
        $lampiran = $i->lampiran;
        $ext = strtolower(pathinfo($lampiran ?? '', PATHINFO_EXTENSION));
        return [
            'id' => $i->id,
            'nama' => $i->user?->nama ?? '-',
            'nip' => $i->user?->nip ?? '-',
            'tanggal' => $i->tanggal->translatedFormat('d F Y'),
            'hari' => $i->tanggal->translatedFormat('l'),
            'alasan' => $i->alasan,
            'tugas_siswa' => $i->tugas_siswa,
            'kategori' => $i->kategori_izin_label,
            'status' => $i->status_label,
            'catatan_penolakan' => $i->catatan_penolakan,
            'has_lampiran' => (bool) $lampiran,
            'lampiran_url' => $lampiran ? route('waka-sdm.izin.lampiran', $i->id) : null,
            'lampiran_type' => in_array($ext, ['pdf']) ? 'pdf' : 'image',
            'ttd_guru' => $i->ttd_guru_url,
            'ttd_waka' => $i->ttd_waka_url,
            'ttd_kepsek' => $i->ttd_kepsek_url,
            'nama_waka' => $i->approverWaka?->nama,
            'nama_kepsek' => $i->approverKepsek?->nama,
            'approved_at' => $i->approved_at?->translatedFormat('d F Y, H:i'),
        ];
    })->values();
@endphp

@push('scripts')
<script>
const izinDetailData = @json($detailData);
const isKepsekAuth = @json($isKepsekAuth);
let ttdKepsekCanvasReady = false;

function openDetailModal(izinId) {
    const data = izinDetailData.find(d => d.id === izinId);
    if (!data) return;

    const ttdGuru = data.ttd_guru
        ? `<img src="${data.ttd_guru}" class="rounded border" style="height: 56px; object-fit: contain;" alt="TTD Guru">`
        : `<span class="badge bg-secondary-subtle text-secondary border rounded-pill px-2 py-1 text-2xs">Belum ada TTD</span>`;
    const ttdWaka = data.ttd_waka
        ? `<img src="${data.ttd_waka}" class="rounded border" style="height: 56px; object-fit: contain;" alt="TTD Waka">`
        : `<span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-1 text-2xs">Menunggu TTD Waka SDM</span>`;
    const ttdKepsek = data.ttd_kepsek
        ? `<img src="${data.ttd_kepsek}" class="rounded border" style="height: 56px; object-fit: contain;" alt="TTD Kepsek">`
        : `<span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-1 text-2xs">Menunggu TTD Kepsek</span>`;

    document.getElementById('modalDetailBody').innerHTML = `
        <div class="row g-3 mb-3">
            <div class="col-6"><div class="text-muted text-xs">Nama Guru</div><div class="fw-semibold text-dark">${data.nama}</div></div>
            <div class="col-6"><div class="text-muted text-xs">NIP</div><div class="fw-semibold text-dark">${data.nip}</div></div>
            <div class="col-6"><div class="text-muted text-xs">Tanggal Izin</div><div class="fw-semibold text-dark">${data.hari}, ${data.tanggal}</div></div>
            <div class="col-6"><div class="text-muted text-xs">Kategori</div><div class="fw-semibold text-dark">${data.kategori}</div></div>
            <div class="col-12"><div class="text-muted text-xs">Alasan</div><div class="fw-semibold text-dark">${data.alasan}</div></div>
            ${data.tugas_siswa ? `<div class="col-12"><div class="text-muted text-xs">Tugas Siswa</div><div class="fw-semibold text-dark">${data.tugas_siswa}</div></div>` : ''}
        </div>
        <hr class="my-3">
        <div class="small fw-semibold text-muted text-uppercase mb-2">Tanda Tangan Digital (3 Berdampingan)</div>
        <div class="row text-center g-3">
            <div class="col-4">
                <div class="text-xs fw-semibold text-dark mb-1">Guru (${data.nama})</div>
                <div class="d-flex justify-content-center align-items-center" style="min-height:60px;">${ttdGuru}</div>
            </div>
            <div class="col-4">
                <div class="text-xs fw-semibold text-dark mb-1">Waka SDM (${data.nama_waka || '-'})</div>
                <div class="d-flex justify-content-center align-items-center" style="min-height:60px;">${ttdWaka}</div>
            </div>
            <div class="col-4">
                <div class="text-xs fw-semibold text-dark mb-1">Kepala Sekolah (${data.nama_kepsek || '-'})</div>
                <div class="d-flex justify-content-center align-items-center" style="min-height:60px;">${ttdKepsek}</div>
            </div>
        </div>
    `;

    const modal = new bootstrap.Modal(document.getElementById('modalDetailIzin'));
    modal.show();
}

function openDokumenModal(izinId) {
    const data = izinDetailData.find(d => d.id === izinId);
    if (!data) return;

    const ttdGuru = data.ttd_guru
        ? `<img src="${data.ttd_guru}" style="height: 64px; object-fit: contain;" alt="TTD Guru">`
        : `<span class="text-muted fst-italic text-xs">(belum ada TTD)</span>`;
    const ttdWaka = data.ttd_waka
        ? `<img src="${data.ttd_waka}" style="height: 64px; object-fit: contain;" alt="TTD Waka">`
        : `<span class="text-muted fst-italic text-xs">(Menunggu TTD Waka SDM)</span>`;
    const ttdKepsek = data.ttd_kepsek
        ? `<img src="${data.ttd_kepsek}" style="height: 64px; object-fit: contain;" alt="TTD Kepsek">`
        : `<span class="text-muted fst-italic text-xs">(Menunggu TTD Kepsek)</span>`;

    document.getElementById('dokumenSurat').innerHTML = `
        <div style="text-align:center; border-bottom: 2px solid #111827; padding-bottom: 12px; margin-bottom: 16px;">
            <div style="font-size: 0.95rem; font-weight: 800; text-transform: uppercase;">Surat Pengajuan Izin Kegiatan Guru</div>
            <div class="text-muted" style="font-size: 0.8rem;">Sistem Informasi Presensi & Perizinan Digital</div>
        </div>
        <div class="mb-3" style="font-size: 0.85rem;">
            <div class="mb-1">Kepada Yth. Pimpinan Sekolah / Kepala Sekolah<br>di Tempat</div>
        </div>
        <table style="font-size: 0.85rem; margin-bottom: 12px;">
            <tr><td style="padding-right: 12px; vertical-align: top;">Nama Guru</td><td>: <strong>${data.nama}</strong></td></tr>
            <tr><td style="padding-right: 12px; vertical-align: top;">NIP</td><td>: ${data.nip || '-'}</td></tr>
            <tr><td style="padding-right: 12px; vertical-align: top;">Tanggal Izin</td><td>: ${data.tanggal} (${data.hari})</td></tr>
            <tr><td style="padding-right: 12px; vertical-align: top;">Jenis Izin</td><td>: ${data.kategori}</td></tr>
        </table>
        <p style="font-size: 0.85rem; text-align: justify; margin-bottom: 8px;">Permohonan izin dengan alasan: <em>"${data.alasan}"</em></p>
        ${data.tugas_siswa ? `<p style="font-size: 0.85rem; margin-bottom: 12px;"><strong>Penugasan Siswa:</strong> ${data.tugas_siswa}</p>` : ''}
        
        <div style="display:flex; justify-content:space-between; gap:16px; margin-top:36px; align-items:flex-end;">
            <div style="text-align:center; width:32%;">
                <div class="mb-1" style="font-size:0.8rem;">Pemohon,<br>Guru</div>
                <div style="height:70px; display:flex; align-items:center; justify-content:center;">${ttdGuru}</div>
                <div class="border-top d-inline-block pt-1" style="font-size:0.8rem; min-width:130px;">${data.nama}</div>
            </div>
            <div style="text-align:center; width:32%;">
                <div class="mb-1" style="font-size:0.8rem;">Mengetahui,<br>Waka SDM</div>
                <div style="height:70px; display:flex; align-items:center; justify-content:center;">${ttdWaka}</div>
                <div class="border-top d-inline-block pt-1" style="font-size:0.8rem; min-width:130px;">${data.nama_waka || '...........................'}</div>
            </div>
            <div style="text-align:center; width:32%;">
                <div class="mb-1" style="font-size:0.8rem;">Menyetujui,<br>Kepala Sekolah</div>
                <div style="height:70px; display:flex; align-items:center; justify-content:center;">${ttdKepsek}</div>
                <div class="border-top d-inline-block pt-1" style="font-size:0.8rem; min-width:130px;">${data.nama_kepsek || '...........................'}</div>
            </div>
        </div>
    `;

    document.getElementById('dokumenNamaGuru').textContent = 'Pemohon: ' + data.nama;
    const btnDownload = document.getElementById('btnDownloadDokumen');
    btnDownload.href = data.lampiran_url || '#';
    btnDownload.classList.toggle('d-none', !data.has_lampiran);

    const modal = new bootstrap.Modal(document.getElementById('modalDokumen'));
    modal.show();
}

function printDokumen() {
    const surat = document.getElementById('dokumenSurat').innerHTML;
    const w = window.open('', '_blank', 'width=800,height=900');
    w.document.write(`
        <html><head><title>Dokumen Izin Guru</title>
        <style>
            body { font-family: system-ui, -apple-system, sans-serif; color: #111827; padding: 40px; font-size: 14px; }
            img { max-width: 100%; }
            table { border-collapse: collapse; }
            td { vertical-align: top; }
        </style></head><body>${surat}</body></html>
    `);
    w.document.close();
    w.focus();
    w.print();
    w.close();
}

function openTtdKepsekModal(izinId, namaGuru) {
    const form = document.getElementById('formTtdKepsek');
    form.action = `/admin/kepsek/izin/${izinId}/approve-signature`;
    document.getElementById('ttdNamaGuruKepsek').textContent = namaGuru;
    document.getElementById('ttdKepsekInput').value = '';
    initCanvasTtdKepsek();
    clearCanvasTtdKepsek();
    const modal = new bootstrap.Modal(document.getElementById('modalTtdKepsek'));
    modal.show();
}

function openRejectKepsekModal(izinId, namaGuru) {
    const form = document.getElementById('formRejectKepsek');
    form.action = `/admin/kepsek/izin/${izinId}/reject`;
    document.getElementById('rejectNamaGuruKepsek').textContent = namaGuru;
    const modal = new bootstrap.Modal(document.getElementById('modalRejectKepsek'));
    modal.show();
}

let kepsekCanvasInked = false;

function clearCanvasTtdKepsek() {
    const canvas = document.getElementById('canvasTtdKepsek');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    document.getElementById('ttdKepsekInput').value = '';
    kepsekCanvasInked = false;
}

function initCanvasTtdKepsek() {
    const canvas = document.getElementById('canvasTtdKepsek');
    const ttdHidden = document.getElementById('ttdKepsekInput');
    if (!canvas || ttdKepsekCanvasReady) return;

    const ctx = canvas.getContext('2d');
    ttdKepsekCanvasReady = true;
    let drawing = false;

    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.lineWidth = 2.5;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = '#0f172a';

    function getPos(e) {
        const rect = canvas.getBoundingClientRect();
        return {
            x: (e.clientX - rect.left) * (canvas.width / rect.width),
            y: (e.clientY - rect.top) * (canvas.height / rect.height),
        };
    }

    function start(e) {
        const pos = getPos(e);
        drawing = true;
        kepsekCanvasInked = true;
        ctx.beginPath();
        ctx.moveTo(pos.x, pos.y);
    }

    function move(e) {
        if (!drawing) return;
        const pos = getPos(e);
        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();
    }

    function end() {
        if (!drawing) return;
        drawing = false;
        ctx.closePath();
        if (kepsekCanvasInked) {
            ttdHidden.value = canvas.toDataURL('image/png');
        }
    }

    canvas.addEventListener('pointerdown', start);
    canvas.addEventListener('pointermove', move);
    canvas.addEventListener('pointerup', end);
    canvas.addEventListener('pointercancel', end);
    canvas.addEventListener('touchstart', function (e) { e.preventDefault(); }, { passive: false });

    const btnClear = document.getElementById('btnClearTtdKepsek');
    if (btnClear) {
        btnClear.onclick = clearCanvasTtdKepsek;
    }

    const form = document.getElementById('formTtdKepsek');
    if (form) {
        form.onsubmit = function (e) {
            // 1. Eksport canvas ke Base64 sebelum form submit
            if (kepsekCanvasInked) {
                const signatureData = canvas.toDataURL('image/png');
                ttdHidden.value = signatureData;
            }

            // 2. Cegah submit jika area TTD masih kosong
            const ttd = ttdHidden.value;
            if (!kepsekCanvasInked || !ttd || ttd.trim() === '') {
                e.preventDefault();
                alert('Harap bubuhkan tanda tangan terlebih dahulu.');
                return false;
            }

            // 3. Validasi pilihan pejabat Kepsek (mode non-auth)
            if (!isKepsekAuth) {
                const sel = document.getElementById('selectKepsek');
                if (sel && !sel.value) {
                    e.preventDefault();
                    alert('Silakan pilih pejabat Kepala Sekolah terlebih dahulu.');
                    return false;
                }
            }

            return true;
        };
    }
}
</script>
@endpush
@endsection
