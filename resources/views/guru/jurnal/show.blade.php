@extends('layouts.app')

@section('title', 'Detail Jurnal Mengajar - WebJournal')

@push('styles')
<style>
    .form-section-card {
        background: #ffffff;
        border: 1px solid #e8eef5;
        border-radius: 16px;
        box-shadow: 0 2px 12px rgba(15, 23, 42, 0.05);
        padding: 1.75rem 2rem;
    }

    .readonly-field {
        background-color: #f8fafc !important;
        color: #334155 !important;
    }

    .img-preview-thumbnail {
        width: 100px;
        height: 65px;
        object-fit: cover;
        border-radius: 8px;
        cursor: pointer;
        border: 2px solid #cbd5e1;
        transition: transform 0.2s ease;
    }

    .img-preview-thumbnail:hover {
        transform: scale(1.05);
        border-color: #0284c7;
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 800; font-size: 1.75rem;">
                Detail Jurnal Mengajar
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                Jam {{ $jadwal->jamPelajaran?->jam_ke ?? '-' }} &bull; {{ $waktu }} &bull; {{ \Carbon\Carbon::parse($jurnal->tanggal)->translatedFormat('d F Y') }}
                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill ms-2">Read-Only</span>
            </p>
        </div>
        <a href="{{ route('guru.jurnal') }}" class="btn btn-light border rounded-3 px-3 py-2 fw-semibold">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar Jurnal
        </a>
    </div>

    @if(session('error'))
        <div class="alert alert-warning border-0 rounded-4 shadow-sm mb-4">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
        </div>
    @endif

    {{-- Informasi Jurnal --}}
    <div class="form-section-card mb-4">
        <h5 class="fw-bold text-dark mb-3">
            <i class="bi bi-journal-text text-primary me-2"></i> Informasi Jurnal Utama
        </h5>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold text-secondary small text-uppercase">Nama Kelas</label>
                <input type="text"
                       class="form-control rounded-3 readonly-field"
                       value="{{ $jadwal->kelas?->nama_kelas ?? '-' }}"
                       readonly>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold text-secondary small text-uppercase">Mata Pelajaran</label>
                <input type="text"
                       class="form-control rounded-3 readonly-field"
                       value="{{ $jadwal->mapel?->nama_mapel ?? '-' }}"
                       readonly>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold text-secondary small text-uppercase">Status Kehadiran Guru Pengajar</label>
                <div class="p-2 bg-light rounded-3 border d-flex align-items-center gap-2">
                    @php
                        $statusClass = match($jurnal->status_kehadiran) {
                            'Izin' => 'bg-warning-subtle text-warning-emphasis border-warning-subtle',
                            'Sakit' => 'bg-danger-subtle text-danger border-danger-subtle',
                            'Disposisi' => 'bg-info-subtle text-info-emphasis border-info-subtle',
                            default => 'bg-success-subtle text-success border-success-subtle',
                        };
                    @endphp
                    <span class="badge border rounded-pill px-3 py-2 fw-bold {{ $statusClass }}">
                        {{ $jurnal->status_kehadiran ?? 'Hadir' }}
                    </span>
                    <span class="text-dark small fw-medium">Guru Asli: {{ $jurnal->guru->nama ?? $jadwal->guru->nama ?? '-' }}</span>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold text-secondary small text-uppercase">Guru Pengganti / Piket</label>
                <div class="p-2 bg-light rounded-3 border">
                    @if($jurnal->guruPengganti)
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-2 fw-bold me-2">
                            <i class="bi bi-person-fill-gear me-1"></i> {{ $jurnal->guruPengganti->nama }}
                        </span>
                        <span class="text-muted small">Guru Piket Pengganti</span>
                    @else
                        <span class="text-muted small p-1 d-block">- Tidak Ada Guru Pengganti -</span>
                    @endif
                </div>
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold text-secondary small text-uppercase">
                    Materi Pelajaran / Bahasan
                </label>
                <div class="p-3 bg-light rounded-3 border text-dark" style="min-height: 80px;">
                    {{ $jurnal->materi }}
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold text-secondary small text-uppercase">
                    Catatan Kejadian Penting
                </label>
                <div class="p-3 bg-light rounded-3 border text-dark" style="min-height: 70px;">
                    {{ $jurnal->catatan_kejadian ?: 'Tidak ada catatan kejadian.' }}
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold text-secondary small text-uppercase d-block mb-2">
                    Foto Kegiatan KBM
                </label>
                @if($jurnal->foto_kegiatan)
                    <div class="p-3 bg-light rounded-3 border d-flex align-items-center gap-3">
                        <img src="{{ route('jurnal.foto', basename($jurnal->foto_kegiatan)) }}" 
                             alt="Foto Kegiatan KBM" 
                             class="img-preview-thumbnail"
                             onclick="showImagePreview('{{ route('jurnal.foto', basename($jurnal->foto_kegiatan)) }}', 'Foto Kegiatan Belajar Mengajar')">
                        <div>
                            <span class="badge bg-success-subtle text-success border border-success-subtle mb-1">
                                <i class="bi bi-check-circle me-1"></i> Foto Ter-upload
                            </span>
                            <div>
                                <button type="button" 
                                        class="btn btn-sm btn-link p-0 text-decoration-none fw-semibold text-primary"
                                        onclick="showImagePreview('{{ route('jurnal.foto', basename($jurnal->foto_kegiatan)) }}', 'Foto Kegiatan Belajar Mengajar')">
                                    <i class="bi bi-eye me-1"></i> Lihat Foto Full
                                </button>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="p-3 bg-light rounded-3 border text-muted small">
                        Tidak ada foto kegiatan KBM yang di-upload.
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Presensi Siswa --}}
    <div class="form-section-card mb-4">
        <h5 class="fw-bold text-dark mb-3">
            <i class="bi bi-people-fill text-primary me-2"></i> Rekap Presensi Siswa Kelas {{ $jadwal->kelas?->nama_kelas }}
        </h5>

        <div class="table-responsive w-full overflow-x-auto">
            <table class="table table-custom align-middle mb-0 min-w-full">
                <thead>
                    <tr>
                        <th class="whitespace-nowrap" style="width: 50px;">No</th>
                        <th class="whitespace-nowrap" style="width: 120px;">NIS</th>
                        <th class="whitespace-nowrap">Nama Siswa</th>
                        <th style="width: 140px;" class="text-center whitespace-nowrap">Status</th>
                        <th class="whitespace-nowrap">Keterangan / Foto Surat</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($siswas as $index => $siswa)
                        @php
                            $abs = $absensiMap[$siswa->id] ?? null;
                            $status = $abs ? $abs->status : 'Hadir';
                        @endphp
                        <tr>
                            <td class="whitespace-nowrap">{{ $index + 1 }}</td>
                            <td class="whitespace-nowrap">{{ $siswa->nis }}</td>
                            <td class="fw-semibold">{{ $siswa->nama }}</td>
                            <td class="text-center whitespace-nowrap">
                                @if($status === 'Hadir')
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1 fw-bold">
                                        <i class="bi bi-check-circle-fill me-1"></i> Hadir
                                    </span>
                                @elseif($status === 'Sakit')
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-3 py-1 fw-bold">
                                        Sakit (S)
                                    </span>
                                @elseif($status === 'Izin')
                                    <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle rounded-pill px-3 py-1 fw-bold">
                                        Izin (I)
                                    </span>
                                @elseif($status === 'Dispen')
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1 fw-bold">
                                        Dispen (D)
                                    </span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3 py-1 fw-bold">
                                        Alpa (A)
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if($status !== 'Hadir')
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="text-dark small">
                                            {{ $abs?->keterangan ?: '-' }}
                                        </span>
                                        @if($abs?->foto_surat)
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-primary rounded-3 py-0 px-2"
                                                    style="font-size: 0.78rem;"
                                                    onclick="showImagePreview('{{ asset('storage/' . $abs->foto_surat) }}', 'Foto Surat Izin - {{ addslashes($siswa->nama) }}')">
                                                <i class="bi bi-file-earmark-image me-1"></i> Lihat Surat
                                            </button>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                Tidak ada siswa di kelas ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL PREVIEW GAMBAR POP-UP -->
<div class="modal fade" id="modalPreviewGambar" tabindex="-1" aria-labelledby="modalPreviewGambarTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="modal-title fw-bold text-dark" id="modalPreviewGambarTitle">Preview Gambar</h5>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-outline-primary rounded-3 px-2 py-1 fw-semibold d-flex align-items-center gap-1" onclick="toggleFullscreenFoto('previewGambarSrc')">
                        <i class="bi bi-arrows-angle-expand"></i> Fullscreen
                    </button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body text-center py-4">
                <img id="previewGambarSrc" src="" alt="Preview Gambar" class="img-fluid rounded-3 shadow-sm" style="max-height: 500px; object-fit: contain;">
            </div>
            <div class="modal-footer border-0 pt-0 justify-content-end">
                <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function showImagePreview(url, title = 'Preview Gambar') {
        document.getElementById('modalPreviewGambarTitle').innerText = title;
        document.getElementById('previewGambarSrc').src = url;
        const modal = new bootstrap.Modal(document.getElementById('modalPreviewGambar'));
        modal.show();
    }

    function toggleFullscreenFoto(imgId) {
        const img = document.getElementById(imgId);
        if (!img) return;

        if (img.requestFullscreen) {
            img.requestFullscreen();
        } else if (img.webkitRequestFullscreen) {
            img.webkitRequestFullscreen();
        } else if (img.msRequestFullscreen) {
            img.msRequestFullscreen();
        }
    }
</script>
@endpush
