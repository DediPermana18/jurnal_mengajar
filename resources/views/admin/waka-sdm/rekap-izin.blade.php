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
            <a href="{{ route('waka-sdm.rekap-presensi-guru') }}" class="btn btn-outline-primary rounded-3 text-xs fw-semibold">
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
                <div class="row g-2.5 align-items-end">
                    {{-- Filter Guru --}}
                    <div class="col-12 col-md-3">
                        <label class="form-label text-xs fw-bold text-muted text-uppercase mb-1">Nama Guru</label>
                        <select name="id_guru" class="form-select form-select-sm rounded-3">
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
                        <label class="form-label text-xs fw-bold text-muted text-uppercase mb-1">Jenis Izin</label>
                        <select name="kategori_izin" class="form-select form-select-sm rounded-3">
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
                        <label class="form-label text-xs fw-bold text-muted text-uppercase mb-1">Tanggal Mulai</label>
                        <input type="date" name="tanggal_mulai" class="form-control form-control-sm rounded-3" value="{{ request('tanggal_mulai') }}">
                    </div>

                    {{-- Filter Tanggal Selesai --}}
                    <div class="col-6 col-md-2">
                        <label class="form-label text-xs fw-bold text-muted text-uppercase mb-1">Tanggal Selesai</label>
                        <input type="date" name="tanggal_selesai" class="form-control form-control-sm rounded-3" value="{{ request('tanggal_selesai') }}">
                    </div>

                    {{-- Filter Status Approval --}}
                    <div class="col-6 col-md-2">
                        <label class="form-label text-xs fw-bold text-muted text-uppercase mb-1">Status</label>
                        <select name="status" class="form-select form-select-sm rounded-3">
                            <option value="semua">Semua Status</option>
                            <option value="{{ \App\Models\IzinGuru::STATUS_DISETUJUI }}" {{ request('status') === \App\Models\IzinGuru::STATUS_DISETUJUI ? 'selected' : '' }}>Disetujui</option>
                            <option value="{{ \App\Models\IzinGuru::STATUS_PENDING_PIKET }}" {{ request('status') === \App\Models\IzinGuru::STATUS_PENDING_PIKET ? 'selected' : '' }}>Pending Piket</option>
                            <option value="{{ \App\Models\IzinGuru::STATUS_PENDING_WAKA }}" {{ request('status') === \App\Models\IzinGuru::STATUS_PENDING_WAKA ? 'selected' : '' }}>Pending Waka</option>
                            <option value="{{ \App\Models\IzinGuru::STATUS_PENDING_KEPSEK }}" {{ request('status') === \App\Models\IzinGuru::STATUS_PENDING_KEPSEK ? 'selected' : '' }}>Pending Kepsek</option>
                            <option value="{{ \App\Models\IzinGuru::STATUS_DITOLAK }}" {{ request('status') === \App\Models\IzinGuru::STATUS_DITOLAK ? 'selected' : '' }}>Ditolak</option>
                        </select>
                    </div>

                    {{-- Tombol Aksi Filter --}}
                    <div class="col-12 col-md-1 d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm rounded-3 w-100 fw-semibold" title="Terapkan Filter">
                            <i class="bi bi-funnel-fill"></i>
                        </button>
                        <a href="{{ route('waka-sdm.rekap-izin') }}" class="btn btn-light border btn-sm rounded-3" title="Reset Filter">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
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
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
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
<script>
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
</script>
@endpush
