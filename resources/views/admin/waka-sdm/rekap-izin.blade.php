@extends('layouts.app')

@section('title', 'Rekap Perizinan & Cuti Guru - Waka SDM')

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
                <span class="text-xs fw-semibold text-primary">Rekap Perizinan & Cuti</span>
            </div>
            <h2 class="fw-black text-dark mb-1" style="font-weight: 900; font-size: 1.5rem; letter-spacing: -0.02em;">
                Rekapitulasi Izin & Cuti Guru
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.875rem;">
                Daftar pengajuan izin sakit, dinas luar, cuti tahunan, dan permohonan dispensasi kedinasan guru.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('waka-sdm.rekap-presensi-guru') }}" class="btn btn-light border rounded-3 text-xs fw-semibold">
                <i class="bi bi-file-earmark-bar-graph me-1"></i> Rekap Performa KBM
            </a>
        </div>
    </div>

    {{-- Summary Mini Cards --}}
    <div class="row g-3 mb-4">
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
        <div class="col-6 col-md-3">
            <div class="stat-badge-card d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted text-2xs text-uppercase fw-bold">Disetujui</div>
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
                    <div class="text-muted text-2xs text-uppercase fw-bold">Menunggu Approval</div>
                    <div class="fs-4 fw-bold text-warning-emphasis">{{ $totalPending }}</div>
                </div>
                <div class="rounded-3 bg-warning-subtle text-warning p-2 fs-5">
                    <i class="bi bi-hourglass-split"></i>
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
                    <i class="bi bi-x-circle-fill"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter Panel --}}
    <div class="card border rounded-3 shadow-2xs mb-4">
        <div class="card-body p-3.5">
            <form method="GET" action="{{ route('waka-sdm.rekap-izin') }}">
                <div class="row g-2.5 align-items-center">
                    {{-- Live Search Input --}}
                    <div class="col-12 col-md-3">
                        <div class="position-relative">
                            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted" style="font-size: 0.85rem;"></i>
                            <input type="text" name="search" class="form-control form-control-sm rounded-3 ps-5 bg-light" placeholder="Cari nama, NIP, alasan..." value="{{ request('search') }}">
                        </div>
                    </div>

                    {{-- Filter Guru --}}
                    <div class="col-12 col-md-3">
                        <select name="id_guru" class="form-select form-select-sm rounded-3 bg-light" onchange="this.form.submit()">
                            <option value="">Semua Guru</option>
                            @foreach($guruList as $guru)
                                <option value="{{ $guru->id }}" {{ request('id_guru') == $guru->id ? 'selected' : '' }}>
                                    {{ $guru->nama }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Filter Kategori Izin --}}
                    <div class="col-6 col-md-2">
                        <select name="kategori_izin" class="form-select form-select-sm rounded-3 bg-light" onchange="this.form.submit()">
                            <option value="semua">Semua Jenis</option>
                            <option value="sakit" {{ request('kategori_izin') == 'sakit' ? 'selected' : '' }}>Sakit</option>
                            <option value="dinas_luar" {{ request('kategori_izin') == 'dinas_luar' ? 'selected' : '' }}>Tugas Luar / Dinas</option>
                            <option value="cuti" {{ request('kategori_izin') == 'cuti' ? 'selected' : '' }}>Cuti</option>
                            <option value="perdin" {{ request('kategori_izin') == 'perdin' ? 'selected' : '' }}>Perjalanan Dinas</option>
                            <option value="urusan_keluarga" {{ request('kategori_izin') == 'urusan_keluarga' ? 'selected' : '' }}>Urusan Keluarga</option>
                            <option value="lainnya" {{ request('kategori_izin') == 'lainnya' ? 'selected' : '' }}>Lainnya</option>
                        </select>
                    </div>

                    {{-- Filter Tanggal Mulai --}}
                    <div class="col-6 col-md-2">
                        <input type="date" name="tanggal_mulai" class="form-control form-control-sm rounded-3 bg-light" value="{{ request('tanggal_mulai') }}" onchange="this.form.submit()" title="Tanggal Mulai">
                    </div>

                    {{-- Filter Status Approval --}}
                    <div class="col-6 col-md-2">
                        <select name="status" class="form-select form-select-sm rounded-3 bg-light" onchange="this.form.submit()">
                            <option value="semua">Semua Status</option>
                            <option value="{{ \App\Models\IzinGuru::STATUS_DISETUJUI }}" {{ request('status') === \App\Models\IzinGuru::STATUS_DISETUJUI ? 'selected' : '' }}>Disetujui</option>
                            <option value="{{ \App\Models\IzinGuru::STATUS_PENDING_PIKET }}" {{ request('status') === \App\Models\IzinGuru::STATUS_PENDING_PIKET ? 'selected' : '' }}>Pending Piket</option>
                            <option value="{{ \App\Models\IzinGuru::STATUS_PENDING_WAKA }}" {{ request('status') === \App\Models\IzinGuru::STATUS_PENDING_WAKA ? 'selected' : '' }}>Pending Waka</option>
                            <option value="{{ \App\Models\IzinGuru::STATUS_PENDING_KEPSEK }}" {{ request('status') === \App\Models\IzinGuru::STATUS_PENDING_KEPSEK ? 'selected' : '' }}>Pending Kepsek</option>
                            <option value="{{ \App\Models\IzinGuru::STATUS_DITOLAK }}" {{ request('status') === \App\Models\IzinGuru::STATUS_DITOLAK ? 'selected' : '' }}>Ditolak</option>
                        </select>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Main Table Card --}}
    <div class="card border rounded-3 shadow-2xs mb-4">
        <div class="card-header bg-white border-bottom py-3 px-3.5 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h5 class="fw-bold text-dark mb-0" style="font-size: 1rem;">
                    Daftar Rekapitulasi Izin Guru
                </h5>
                <p class="text-muted mb-0 text-xs">
                    Menampilkan total <strong>{{ $daftarIzin->total() }}</strong> data izin berdasarkan filter aktif.
                </p>
            </div>
            <div class="text-xs text-muted">
                Halaman {{ $daftarIzin->currentPage() }} dari {{ $daftarIzin->lastPage() ?: 1 }}
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-rekap-izin table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 50px;" class="text-center">No</th>
                        <th>Nama Guru</th>
                        <th>Tanggal Izin</th>
                        <th>Jenis Izin</th>
                        <th>Alasan & Tugas Siswa</th>
                        <th>Bukti Surat</th>
                        <th>Status Pengganti</th>
                        <th>Status Approval</th>
                        <th style="min-width: 130px;" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($daftarIzin as $idx => $izin)
                        <tr>
                            <td class="text-center text-muted fw-semibold">
                                {{ ($daftarIzin->currentPage() - 1) * $daftarIzin->perPage() + $idx + 1 }}
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold" style="width: 34px; height: 34px; font-size: 0.8rem;">
                                        {{ strtoupper(substr($izin->user?->nama ?? 'G', 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark">{{ $izin->user?->nama ?? 'Guru Tidak Ditemukan' }}</div>
                                        <div class="text-muted text-2xs">{{ $izin->user?->nip ? 'NIP: ' . $izin->user->nip : 'Non-NIP' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="fw-semibold text-dark">
                                    {{ $izin->tanggal ? $izin->tanggal->translatedFormat('d F Y') : '-' }}
                                </span>
                                <div class="text-muted text-2xs">
                                    {{ $izin->tanggal ? $izin->tanggal->translatedFormat('l') : '' }}
                                </div>
                            </td>
                            <td>
                                @php
                                    $kategori = $izin->kategori_izin;
                                    $katBadge = 'bg-light text-dark border';
                                    if ($kategori === 'sakit') $katBadge = 'bg-danger-subtle text-danger border border-danger-subtle';
                                    elseif (in_array($kategori, ['dinas_luar', 'tugas_luar', 'perdin'])) $katBadge = 'bg-primary-subtle text-primary border border-primary-subtle';
                                    elseif ($kategori === 'cuti') $katBadge = 'bg-info-subtle text-info border border-info-subtle';
                                @endphp
                                <span class="badge {{ $katBadge }} px-2.5 py-1 rounded-pill text-xs fw-semibold">
                                    {{ $izin->kategori_izin_label ?? ucfirst(str_replace('_', ' ', $kategori ?? 'Izin')) }}
                                </span>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark text-xs mb-0.5" style="max-width: 260px;">
                                    {{ $izin->alasan ?? $izin->keterangan ?? '-' }}
                                </div>
                                @if($izin->tugas_siswa)
                                    <div class="text-muted text-2xs d-flex align-items-center gap-1" style="max-width: 260px;">
                                        <i class="bi bi-pencil-square text-primary"></i>
                                        <span class="text-truncate" title="{{ $izin->tugas_siswa }}">{{ $izin->tugas_siswa }}</span>
                                    </div>
                                @endif
                                @if($izin->catatan_penolakan)
                                    <div class="text-danger text-2xs mt-1">
                                        <i class="bi bi-x-circle me-1"></i><strong>Alasan Ditolak:</strong> {{ $izin->catatan_penolakan }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if($izin->lampiran)
                                    @php
                                        $ext = strtolower(pathinfo($izin->lampiran, PATHINFO_EXTENSION));
                                        $isPdf = $ext === 'pdf';
                                        $lampiranUrl = route('waka-sdm.izin.lampiran', $izin->id);
                                    @endphp
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-secondary rounded-3 text-xs d-flex align-items-center gap-1 py-1 px-2"
                                            onclick="openLampiranModal('{{ $lampiranUrl }}', '{{ $isPdf ? 'pdf' : 'image' }}', '{{ addslashes($izin->user?->nama ?? 'Guru') }}')">
                                        <i class="bi {{ $isPdf ? 'bi-file-earmark-pdf-fill text-danger' : 'bi-image-fill text-primary' }}"></i>
                                        <span>Lihat Bukti</span>
                                    </button>
                                @else
                                    <span class="text-muted text-xs italic">Tidak ada lampiran</span>
                                @endif
                            </td>
                            <td>
                                @if($izin->guru_pengganti_cover)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle text-xs">
                                        <i class="bi bi-shield-check me-1"></i> Cover: {{ $izin->guru_pengganti_cover }}
                                    </span>
                                @elseif($izin->approverPiket)
                                    <span class="badge bg-info-subtle text-info border border-info-subtle text-xs">
                                        <i class="bi bi-check2-all me-1"></i> Piket: {{ $izin->approverPiket->nama }}
                                    </span>
                                @else
                                    <span class="badge bg-light text-muted border text-xs">
                                        Belum Ada Pengganti
                                    </span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $izin->status_badge }} px-2.5 py-1 rounded-pill text-xs">
                                    {{ $izin->status_label }}
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="d-flex align-items-center justify-content-center gap-1">
                                    <button type="button"
                                            class="btn btn-sm btn-outline-primary rounded-3 text-xs py-1 px-2 fw-semibold"
                                            onclick="openDetailModal({{ $izin->id }})"
                                            title="Lihat Detail Pengajuan">
                                        <i class="bi bi-eye me-0.5"></i> Detail
                                    </button>
                                    <button type="button"
                                            class="btn btn-sm btn-outline-secondary rounded-3 text-xs py-1 px-2 fw-semibold"
                                            onclick="openDokumenModal({{ $izin->id }})"
                                            title="Lihat Dokumen / Tanda Tangan">
                                        <i class="bi bi-file-earmark-pdf me-0.5"></i> Dokumen
                                    </button>
                                    @if($izin->isPending() && $izin->ttd_waka === null)
                                        <button type="button"
                                                class="btn btn-sm btn-success rounded-3 text-xs py-1 px-2 fw-semibold"
                                                onclick="openTtdWakaModal({{ $izin->id }}, '{{ addslashes($izin->user?->nama ?? 'Guru') }}')"
                                                title="Tandatangani sebagai Waka SDM">
                                            <i class="bi bi-signature me-0.5"></i> Tanda Tangan
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                                Tidak ada data pengajuan izin guru yang cocok dengan filter yang dipilih.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($daftarIzin->hasPages())
            <div class="card-footer bg-white border-top py-3 px-3.5 d-flex align-items-center justify-content-between">
                {{ $daftarIzin->links() }}
            </div>
        @endif
    </div>

</div>

{{-- MODAL DETAIL PENGAJUAN IZIN --}}
<div class="modal fade" id="modalDetailIzin" tabindex="-1" aria-labelledby="modalDetailIzinLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom py-3 px-4">
                <h5 class="modal-title fw-bold text-dark" id="modalDetailIzinLabel">
                    <i class="bi bi-file-earmark-text-fill me-1 text-primary"></i> Detail Pengajuan Izin
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="detailIzinContainer">
                {{-- Dynamic content inserted by JavaScript --}}
            </div>
            <div class="modal-footer border-top py-2.5 px-4 d-flex justify-content-end">
                <button type="button" class="btn btn-sm btn-secondary rounded-3 text-xs" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

{{-- MODAL TANDA TANGAN WAKA SDM (DUAL MODE) --}}
<div class="modal fade" id="modalTtdWaka" tabindex="-1" aria-labelledby="modalTtdWakaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0 shadow">
            <form id="formTtdWaka" method="POST" action="">
                @csrf
                <div class="modal-header border-bottom py-3 px-4">
                    <h5 class="modal-title fw-bold text-dark" id="modalTtdWakaLabel">
                        <i class="bi bi-signature me-1 text-primary"></i> Tanda Tangan Approval Waka SDM
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    {{-- Info pengajuan --}}
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 p-3 rounded-3 bg-light border">
                        <div>
                            <div class="text-muted text-2xs text-uppercase fw-bold">Pengajuan Izin</div>
                            <div class="fw-bold text-dark text-sm">Guru: <span id="ttdNamaGuru"></span></div>
                        </div>
                        <input type="hidden" id="ttdIzinId" value="">
                    </div>

                    @if($isWakaSdmAuth)
                        {{-- MODE AUTHENTICATED: dropdown disembunyikan, info terkunci --}}
                        <div class="alert alert-info border-0 rounded-3 d-flex align-items-center gap-2 py-2.5 px-3 mb-3">
                            <i class="bi bi-person-lock-fill fs-5"></i>
                            <div>
                                <div class="text-2xs text-uppercase fw-bold">Penandatangan Terkunci</div>
                                <div class="fw-semibold text-dark text-sm">
                                    <i class="bi bi-person-check me-1 text-success"></i>
                                    {{ auth()->user()->nama }}
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle text-2xs ms-1">Mode Login Langsung</span>
                                </div>
                                <div class="text-muted text-2xs">Menggunakan akun Waka SDM yang sedang login.</div>
                            </div>
                        </div>
                        <input type="hidden" name="waka_sdm_id" value="{{ auth()->id() }}">
                    @else
                        {{-- MODE STANDALONE / SHARED LINK: dropdown pilihan pejabat --}}
                        <div class="mb-3">
                            <label for="selectWakaSdm" class="form-label fw-bold text-xs text-muted text-uppercase mb-1">
                                Pilih Pejabat Waka SDM / Kepegawaian <span class="text-danger">*</span>
                            </label>
                            <select name="waka_sdm_id" id="selectWakaSdm" class="form-select form-select-sm rounded-3" required>
                                <option value="">— Pilih Pejabat Waka SDM —</option>
                                @forelse($daftarWakaSdm as $waka)
                                    <option value="{{ $waka->id }}">{{ $waka->nama }} ({{ $waka->nip ?? 'Non-NIP' }})</option>
                                @empty
                                    <option value="" disabled>Tidak ada pejabat Waka SDM terdaftar</option>
                                @endforelse
                            </select>
                            <div class="form-text text-2xs">
                                <i class="bi bi-share me-1"></i> Mode tautan publik / petugas: pilih pejabat Waka SDM yang berwenang menandatangani.
                            </div>
                        </div>
                    @endif

                    {{-- Canvas tanda tangan --}}
                    <div class="mb-2">
                        <label for="canvasTtdWaka" class="form-label fw-bold text-xs text-muted text-uppercase mb-1">
                            Goreskan Tanda Tangan Digital <span class="text-danger">*</span>
                        </label>
                        <canvas id="canvasTtdWaka" width="600" height="220" class="form-control rounded-3"
                                style="height: auto; touch-action: none; cursor: crosshair;">Browser Anda tidak mendukung Canvas.</canvas>
                        <div class="d-flex justify-content-end mt-2">
                            <button type="button" id="btnClearTtdWaka" class="btn btn-sm btn-outline-danger rounded-3 text-xs">
                                <i class="bi bi-eraser me-1"></i> Hapus Tanda Tangan
                            </button>
                        </div>
                        <input type="hidden" name="ttd_waka" id="ttdWakaInput" value="">
                    </div>

                    <p class="text-muted text-2xs mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        Dengan menandatangani, Anda menyetujui pengajuan izin guru ini. Status akan diteruskan ke Kepala Sekolah untuk persetujuan akhir.
                    </p>
                </div>
                <div class="modal-footer border-top py-2.5 px-4 d-flex justify-content-between">
                    <button type="button" class="btn btn-sm btn-light border rounded-3 text-xs" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-success rounded-3 text-xs fw-semibold px-3" id="btnSubmitTtdWaka">
                        <i class="bi bi-check-lg me-1"></i> Setujui &amp; Tandatangani
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL DOKUMEN / SURAT BERTANDA TANGAN --}}
<div class="modal fade" id="modalDokumen" tabindex="-1" aria-labelledby="modalDokumenLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom py-3 px-4 d-flex align-items-center justify-content-between">
                <h5 class="modal-title fw-bold text-dark" id="modalDokumenLabel">
                    <i class="bi bi-file-earmark-text-fill me-1 text-primary"></i> Dokumen Izin Guru
                </h5>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-light border rounded-3 text-xs fw-semibold" id="btnPrintDokumen" onclick="printDokumen()">
                        <i class="bi bi-printer me-1"></i> Cetak
                    </button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body p-3" id="dokumenContainer" style="background: #64748b;">
                <div class="bg-white p-4 rounded-3" id="dokumenSurat" style="max-width: 800px; margin: 0 auto; font-size: 0.875rem; color: #111827;">
                    {{-- Dynamic signed document inserted by JavaScript --}}
                </div>
            </div>
            <div class="modal-footer border-top py-2.5 px-4 d-flex justify-content-between">
                <span class="text-muted text-xs" id="dokumenNamaGuru"></span>
                <a href="#" id="btnDownloadDokumen" target="_blank" class="btn btn-sm btn-outline-primary rounded-3 text-xs fw-semibold d-none">
                    <i class="bi bi-download me-1"></i> Unduh Lampiran
                </a>
            </div>
        </div>
    </div>
</div>

{{-- MODAL PREVIEW BUKTI SURAT / LAMPIRAN --}}
<div class="modal fade" id="modalLampiran" tabindex="-1" aria-labelledby="modalLampiranLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom py-3 px-4">
                <h5 class="modal-title fw-bold text-dark" id="modalLampiranLabel">
                    <i class="bi bi-paperclip me-1 text-primary"></i> Bukti Surat Izin Guru
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center" id="lampiranContainer" style="min-height: 350px;">
                {{-- Dynamic preview inserted by JavaScript --}}
            </div>
            <div class="modal-footer border-top py-2.5 px-4 d-flex justify-content-between">
                <span class="text-muted text-xs" id="lampiranNamaGuru"></span>
                <div class="d-flex gap-2">
                    <a href="#" id="btnDownloadLampiran" target="_blank" class="btn btn-sm btn-outline-primary rounded-3 text-xs fw-semibold">
                        <i class="bi bi-download me-1"></i> Unduh File
                    </a>
                    <button type="button" class="btn btn-sm btn-secondary rounded-3 text-xs" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@php
    $detailData = $daftarIzin->map(function ($i) {
        $lampiran = $i->lampiran;
        $lampiranType = null;
        if ($lampiran) {
            $ext = strtolower(pathinfo($lampiran, PATHINFO_EXTENSION));
            $lampiranType = $ext === 'pdf' ? 'pdf' : 'image';
        }
        return [
            'id' => $i->id,
            'nama' => $i->user?->nama ?? 'Guru Tidak Ditemukan',
            'nip' => $i->user?->nip ?? null,
            'tanggal' => $i->tanggal ? $i->tanggal->translatedFormat('d F Y') : '-',
            'hari' => $i->tanggal ? $i->tanggal->translatedFormat('l') : '',
            'kategori' => $i->kategori_izin_label ?? ucfirst(str_replace('_', ' ', $i->kategori_izin ?? 'Izin')),
            'alasan' => $i->alasan ?? $i->keterangan ?? '-',
            'tugas_siswa' => $i->tugas_siswa,
            'status' => $i->status_label,
            'status_badge' => $i->status_badge,
            'catatan_penolakan' => $i->catatan_penolakan,
            'lampiran_url' => $lampiran ? route('waka-sdm.izin.lampiran', $i->id) : null,
            'lampiran_type' => $lampiranType,
            'has_lampiran' => (bool) $lampiran,
            'guru_pengganti' => $i->guru_pengganti_cover,
            'pengganti_piket' => $i->approverPiket?->nama,
            'ttd_guru' => $i->ttd_guru_url,
            'ttd_waka' => $i->ttd_waka_url,
            'ttd_kepsek' => $i->ttd_kepsek_url,
            'nama_waka' => $i->approverWaka?->nama,
            'nama_kepsek' => $i->approverKepsek?->nama,
            'approved_at' => $i->approved_at ? $i->approved_at->translatedFormat('d F Y H:i') : null,
        ];
    })->values();
@endphp
<script>
const izinDetailData = @json($detailData);

function openDetailModal(izinId) {
    const data = izinDetailData.find(d => d.id === izinId);
    if (!data) return;

    const ttdGuru = data.ttd_guru
        ? `<img src="${data.ttd_guru}" class="rounded border" style="height: 56px; object-fit: contain;" alt="TTD Guru">`
        : `<span class="text-muted text-xs">Belum ada tanda tangan</span>`;
    const ttdWaka = data.ttd_waka
        ? `<img src="${data.ttd_waka}" class="rounded border" style="height: 56px; object-fit: contain;" alt="TTD Waka">`
        : `<span class="text-muted text-xs">Belum ada tanda tangan</span>`;
    const ttdKepsek = data.ttd_kepsek
        ? `<img src="${data.ttd_kepsek}" class="rounded border" style="height: 56px; object-fit: contain;" alt="TTD Kepsek">`
        : `<span class="text-muted text-xs">Belum ada tanda tangan</span>`;

    let lampiranHtml = '';
    if (data.has_lampiran) {
        let preview;
        if (data.lampiran_type === 'pdf') {
            preview = `<iframe src="${data.lampiran_url}" class="w-100 border rounded-3 mb-2" style="height: 320px;" frameborder="0"></iframe>`;
        } else {
            preview = `<img src="${data.lampiran_url}" class="img-fluid rounded-3 shadow-sm mb-2" style="max-height: 320px; object-fit: contain;" alt="Bukti Surat Izin">`;
        }
        lampiranHtml = `
            <div class="text-center mb-2">${preview}</div>
            <div class="text-center">
                <a href="${data.lampiran_url}" target="_blank" class="btn btn-sm btn-outline-primary rounded-3 text-xs fw-semibold">
                    <i class="bi bi-download me-1"></i> Unduh Lampiran
                </a>
            </div>`;
    } else {
        lampiranHtml = `<span class="text-muted text-xs">Tidak ada lampiran / bukti surat.</span>`;
    }

    let penggantiHtml;
    if (data.guru_pengganti) {
        penggantiHtml = `<span class="text-success fw-semibold text-xs"><i class="bi bi-shield-check me-1"></i>${data.guru_pengganti}</span>`;
    } else if (data.pengganti_piket) {
        penggantiHtml = `<span class="text-info fw-semibold text-xs"><i class="bi bi-check2-all me-1"></i>Piket: ${data.pengganti_piket}</span>`;
    } else {
        penggantiHtml = `<span class="text-muted text-xs">Belum ada guru pengganti / piket.</span>`;
    }

    const penolakanHtml = data.catatan_penolakan
        ? `<div class="alert alert-danger py-2 px-3 text-xs mb-0">
             <i class="bi bi-x-circle me-1"></i><strong>Alasan Ditolak:</strong> ${data.catatan_penolakan}
           </div>`
        : '';

    document.getElementById('detailIzinContainer').innerHTML = `
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <div class="d-flex align-items-center gap-2">
                <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold" style="width: 44px; height: 44px;">
                    ${data.nama.charAt(0).toUpperCase()}
                </div>
                <div>
                    <div class="fw-bold text-dark">${data.nama}</div>
                    <div class="text-muted text-2xs">${data.nip ? 'NIP: ' + data.nip : 'Non-NIP'}</div>
                </div>
            </div>
            <span class="badge ${data.status_badge} px-2.5 py-1 rounded-pill text-xs">${data.status}</span>
        </div>

        <div class="row g-3">
            <div class="col-md-4">
                <div class="small fw-semibold text-muted text-uppercase mb-1">Tanggal Izin</div>
                <div class="fw-semibold text-dark text-sm">${data.tanggal}</div>
                <div class="text-muted text-2xs">${data.hari}</div>
            </div>
            <div class="col-md-4">
                <div class="small fw-semibold text-muted text-uppercase mb-1">Jenis Izin</div>
                <span class="badge bg-light text-dark border px-2.5 py-1 rounded-pill text-xs fw-semibold">${data.kategori}</span>
            </div>
            <div class="col-md-4">
                <div class="small fw-semibold text-muted text-uppercase mb-1">Status Pengganti</div>
                ${penggantiHtml}
            </div>
        </div>

        <hr class="my-3">

        <div class="small fw-semibold text-muted text-uppercase mb-1">Alasan / Keterangan</div>
        <p class="text-dark text-sm mb-0">${data.alasan}</p>
        ${data.tugas_siswa ? `
            <div class="mt-2 text-xs">
                <i class="bi bi-pencil-square text-primary me-1"></i><strong>Penugasan kepada Siswa:</strong>
                <span class="text-muted">${data.tugas_siswa}</span>
            </div>` : ''}
        ${data.catatan_penolakan ? penolakanHtml : ''}

        <hr class="my-3">

        <div class="small fw-semibold text-muted text-uppercase mb-2">Bukti Surat / Lampiran</div>
        ${lampiranHtml}

        <hr class="my-3">

        <div class="small fw-semibold text-muted text-uppercase mb-2">Tanda Tangan</div>
        <div class="row text-center g-3">
            <div class="col-4">
                <div class="text-xs fw-semibold text-dark mb-1">Guru</div>
                <div class="d-flex justify-content-center">${ttdGuru}</div>
            </div>
            <div class="col-4">
                <div class="text-xs fw-semibold text-dark mb-1">Waka (${data.nama_waka || '-'})</div>
                <div class="d-flex justify-content-center">${ttdWaka}</div>
            </div>
            <div class="col-4">
                <div class="text-xs fw-semibold text-dark mb-1">Kepala Sekolah (${data.nama_kepsek || '-'})</div>
                <div class="d-flex justify-content-center">${ttdKepsek}</div>
            </div>
        </div>
        <div class="text-center text-muted text-2xs mt-2">
            ${data.approved_at ? 'Disetujui pada: ' + data.approved_at : ''}
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
        : `<span class="text-muted fst-italic">(belum ada)</span>`;
    const ttdWaka = data.ttd_waka
        ? `<img src="${data.ttd_waka}" style="height: 64px; object-fit: contain;" alt="TTD Waka">`
        : `<span class="text-muted fst-italic">(belum ada)</span>`;
    const ttdKepsek = data.ttd_kepsek
        ? `<img src="${data.ttd_kepsek}" style="height: 64px; object-fit: contain;" alt="TTD Kepsek">`
        : `<span class="text-muted fst-italic">(belum ada)</span>`;

    let lampiranBlock = '';
    if (data.has_lampiran) {
        if (data.lampiran_type === 'pdf') {
            lampiranBlock = `<div class="text-center mb-4"><iframe src="${data.lampiran_url}" style="width:100%; height:500px; border:1px solid #e5e7eb; border-radius:6px;" frameborder="0"></iframe></div>`;
        } else {
            lampiranBlock = `<div class="text-center mb-4"><img src="${data.lampiran_url}" class="img-fluid border rounded-3" style="max-height: 480px; object-fit: contain;" alt="Bukti Surat Izin"></div>`;
        }
    } else {
        lampiranBlock = `<div class="text-center text-muted fst-italic mb-4">Tidak ada bukti surat / lampiran yang diunggah.</div>`;
    }

    let statusLine;
    if (data.catatan_penolakan) {
        statusLine = `<div class="text-danger mb-1">Status: DITOLAK &mdash; ${data.catatan_penolakan}</div>`;
    } else if (data.status === 'Disetujui' && data.approved_at) {
        statusLine = `<div class="text-success mb-1">Status: DISETUJUI &mdash; ${data.approved_at}</div>`;
    } else {
        statusLine = `<div class="text-secondary mb-1">Status: ${data.status}</div>`;
    }

    document.getElementById('dokumenSurat').innerHTML = `
        <div style="text-align:center; border-bottom: 2px solid #111827; padding-bottom: 12px; margin-bottom: 16px;">
            <div style="font-size: 0.95rem; font-weight: 800; text-transform: uppercase;">Surat Pengajuan Izin Kegiatan Guru</div>
            <div class="text-muted" style="font-size: 0.8rem;">Formulir Rekap Perizinan &amp; Cuti Guru</div>
        </div>

        <div class="mb-3" style="font-size: 0.85rem;">
            <div class="mb-1">Nomor : ....................</div>
            <div class="mb-1">Kepada Yth. Pimpinan Sekolah / Waka SDM<br>di<br>Tempat</div>
        </div>

        <div class="mb-2" style="font-size: 0.85rem;">Dengan hormat,</div>
        <p style="font-size: 0.85rem; text-align: justify; margin-bottom: 8px;">
            Saya yang bertanda tangan di bawah ini:
        </p>
        <table style="font-size: 0.85rem; margin-bottom: 12px;">
            <tr><td style="padding-right: 12px; vertical-align: top;">Nama Guru</td><td>: <strong>${data.nama}</strong></td></tr>
            <tr><td style="padding-right: 12px; vertical-align: top;">NIP</td><td>: ${data.nip || '-'}</td></tr>
            <tr><td style="padding-right: 12px; vertical-align: top;">Tanggal Izin</td><td>: ${data.tanggal} (${data.hari})</td></tr>
            <tr><td style="padding-right: 12px; vertical-align: top;">Jenis Izin</td><td>: ${data.kategori}</td></tr>
        </table>
        <p style="font-size: 0.85rem; text-align: justify; margin-bottom: 8px;">mengajukan permohonan izin dengan alasan sebagai berikut:</p>
        <p style="font-size: 0.85rem; text-align: justify; padding-left: 16px; margin-bottom: 12px;"><em>"${data.alasan}"</em></p>
        ${data.tugas_siswa ? `<p style="font-size: 0.85rem; margin-bottom: 12px;"><strong>Penugasan kepada Siswa:</strong> ${data.tugas_siswa}</p>` : ''}
        <p style="font-size: 0.85rem; text-align: justify;">Demikian permohonan ini saya buat. Atas perhatian dan persetujuan Bapak/Ibu, saya ucapkan terima kasih.</p>
        <p style="font-size: 0.85rem; margin-top: 4px;">${statusLine}</p>
    `;

    document.getElementById('dokumenSurat').insertAdjacentHTML('beforeend', `
        <div style="display:flex; justify-content:space-between; gap:24px; margin-top:28px; align-items:flex-end;">
            <div style="text-align:center; width:45%;">
                <div class="mb-1" style="font-size:0.8rem;">Mengetahui,<br>Guru Piket</div>
                <div style="height:70px;"></div>
                <div class="border-top d-inline-block pt-1" style="font-size:0.8rem; min-width:140px;">${data.pengganti_piket || '...........................'}</div>
            </div>
            <div style="text-align:center; width:45%;">
                <div class="mb-1" style="font-size:0.8rem;">Pemohon</div>
                <div style="height:70px;">${ttdGuru}</div>
                <div class="border-top d-inline-block pt-1" style="font-size:0.8rem; min-width:140px;">${data.nama}</div>
            </div>
        </div>
        <div style="display:flex; justify-content:space-between; gap:24px; margin-top:36px; align-items:flex-end;">
            <div style="text-align:center; width:45%;">
                <div class="mb-1" style="font-size:0.8rem;">Mengetahui,<br>Waka SDM${data.nama_waka ? ' / '+data.nama_waka : ''}</div>
                <div style="height:70px;">${ttdWaka}</div>
                <div class="border-top d-inline-block pt-1" style="font-size:0.8rem; min-width:140px;">${data.nama_waka || '...........................'}</div>
            </div>
            <div style="text-align:center; width:45%;">
                <div class="mb-1" style="font-size:0.8rem;">Menyetujui,<br>Kepala Sekolah${data.nama_kepsek ? ' / '+data.nama_kepsek : ''}</div>
                <div style="height:70px;">${ttdKepsek}</div>
                <div class="border-top d-inline-block pt-1" style="font-size:0.8rem; min-width:140px;">${data.nama_kepsek || '...........................'}</div>
            </div>
        </div>
    `);

    let lampiranArea = document.createElement('div');
    lampiranArea.innerHTML = `<div style="margin-top:32px; border-top:1px solid #e5e7eb; padding-top:16px;">
        <div class="mb-2" style="font-size:0.8rem; font-weight:700;">LAMPIRAN / BUKTI SURAT</div>
        ${lampiranBlock}
    </div>`;
    document.getElementById('dokumenSurat').appendChild(lampiranArea.firstChild);

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
            body { font-family: 'Times New Roman', serif; color: #111827; padding: 40px; font-size: 14px; }
            .text-muted { color: #6b7280; }
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

function openLampiranModal(url, type, namaGuru) {
    const container = document.getElementById('lampiranContainer');
    const namaSpan = document.getElementById('lampiranNamaGuru');
    const btnDownload = document.getElementById('btnDownloadLampiran');

    namaSpan.textContent = 'Pemohon: ' + namaGuru;
    btnDownload.href = url;

    if (type === 'pdf') {
        container.innerHTML = `
            <iframe src="${url}" class="w-100 border rounded-3" style="height: 520px;" frameborder="0"></iframe>
        `;
    } else {
        container.innerHTML = `
            <img src="${url}" class="img-fluid rounded-3 shadow-sm" style="max-height: 520px; object-fit: contain;" alt="Bukti Surat Izin">
        `;
    }

    const modal = new bootstrap.Modal(document.getElementById('modalLampiran'));
    modal.show();
}

const isWakaSdmAuth = @json($isWakaSdmAuth);
let ttdWakaCanvasReady = false;

function openTtdWakaModal(izinId, namaGuru) {
    const form = document.getElementById('formTtdWaka');
    form.action = `/admin/waka-sdm/izin/${izinId}/approve-signature`;
    document.getElementById('ttdIzinId').value = izinId;
    document.getElementById('ttdNamaGuru').textContent = namaGuru;
    document.getElementById('ttdWakaInput').value = '';
    if (!isWakaSdmAuth) {
        const sel = document.getElementById('selectWakaSdm');
        if (sel) sel.value = '';
    }
    initCanvasTtdWaka();
    clearCanvasTtdWaka();
    const modal = new bootstrap.Modal(document.getElementById('modalTtdWaka'));
    modal.show();
}

function clearCanvasTtdWaka() {
    const canvas = document.getElementById('canvasTtdWaka');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    document.getElementById('ttdWakaInput').value = '';
}

function initCanvasTtdWaka() {
    const canvas = document.getElementById('canvasTtdWaka');
    const ttdHidden = document.getElementById('ttdWakaInput');
    if (!canvas || ttdWakaCanvasReady) return;

    const ctx = canvas.getContext('2d');
    ttdWakaCanvasReady = true;
    let drawing = false;
    let inked = false;
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
        inked = true;
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
        ttdHidden.value = inked ? canvas.toDataURL('image/png') : '';
    }

    canvas.addEventListener('pointerdown', start);
    canvas.addEventListener('pointermove', move);
    canvas.addEventListener('pointerup', end);
    canvas.addEventListener('pointercancel', end);
    canvas.addEventListener('touchstart', function (e) { e.preventDefault(); }, { passive: false });

    const btnClear = document.getElementById('btnClearTtdWaka');
    if (btnClear) {
        btnClear.onclick = clearCanvasTtdWaka;
    }

    const form = document.getElementById('formTtdWaka');
    if (form) {
        form.onsubmit = function (e) {
            const ttd = document.getElementById('ttdWakaInput').value;
            if (!ttd) {
                e.preventDefault();
                alert('Silakan goreskan tanda tangan terlebih dahulu pada area canvas.');
                return false;
            }
            if (!isWakaSdmAuth) {
                const sel = document.getElementById('selectWakaSdm');
                if (sel && !sel.value) {
                    e.preventDefault();
                    alert('Silakan pilih pejabat Waka SDM / Kepegawaian terlebih dahulu.');
                    return false;
                }
            }
            return true;
        };
    }
}
</script>
@endpush
