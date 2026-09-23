@extends('layouts.app')

@section('title', 'Rekap Harian Piket - WebJournal')

@push('styles')
<style>
    .table-custom-wp th {
        background: #f8fafc;
        font-size: 0.76rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        font-weight: 700;
        padding: 0.85rem 1rem;
        border-bottom: 1px solid #e2e8f0;
    }
    .table-custom-wp td {
        padding: 0.85rem 1rem;
        vertical-align: middle;
        font-size: 0.86rem;
        border-bottom: 1px solid #f1f5f9;
    }
    .catatan-kotak {
        background: #f8fafc;
        border: 1px dashed #cbd5e1;
        border-radius: 0.75rem;
        padding: 0.9rem 1rem;
        font-size: 0.87rem;
        color: #334155;
        min-height: 70px;
        white-space: pre-wrap;
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">

    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-info-subtle text-info border border-info-subtle px-2.5 py-1 rounded-pill fw-semibold text-xs">
                    <i class="bi bi-clipboard-check me-1"></i> Rekap Piket Harian
                </span>
            </div>
            <h2 class="fw-black text-dark mb-1" style="font-weight: 900; font-size: 1.55rem; letter-spacing: -0.02em;">
                Rekap Harian Piket
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.875rem;">
                Review catatan koordinator shift pagi/siang, validasi rekap, dan catatan Kejadian Luar Biasa (KLB).
            </p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <form method="GET" action="{{ route('waka-piket.rekap-harian') }}" class="d-flex align-items-center gap-2">
                <input type="date" name="tanggal" value="{{ $tanggal }}"
                       class="form-control form-control-sm rounded-3 border" style="width: auto;">
                <button type="submit" class="btn btn-sm btn-primary rounded-3 fw-semibold text-xs">
                    <i class="bi bi-funnel"></i> Tampilkan
                </button>
            </form>
            <a href="{{ route('waka-piket.dashboard') }}" class="btn btn-sm btn-light border rounded-3 fw-semibold text-xs">
                <i class="bi bi-speedometer2 text-primary"></i> Dashboard
            </a>
        </div>
    </div>

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

    {{-- ============================================================== --}}
    {{-- 1. DETAIL REKAP TANGGAL TERPILIH                                --}}
    {{-- ============================================================== --}}
    <div class="card border rounded-3 shadow-2xs mb-4">
        <div class="card-header bg-white border-bottom py-3 px-3.5 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h5 class="fw-bold text-dark mb-0" style="font-size: 1rem;">
                    <i class="bi bi-calendar3 text-primary me-1.5"></i>
                    Rekap: {{ \Carbon\Carbon::parse($tanggal)->translatedFormat('l, d F Y') }}
                </h5>
                <p class="text-muted mb-0 text-xs">
                    Satu baris rekap per tanggal.
                </p>
            </div>
            @if($rekap->exists)
                <span class="badge {{ $rekap->status_badge }} px-2.5 py-1 rounded-pill text-xs">
                    <i class="bi {{ $rekap->isValidated() ? 'bi-check-circle' : 'bi-hourglass-split' }} me-1"></i>
                    {{ $rekap->status_label }}
                </span>
            @else
                <span class="badge bg-secondary-subtle text-secondary border px-2.5 py-1 rounded-pill text-xs">
                    <i class="bi bi-file-earmark-plus me-1"></i> Belum Dibuat
                </span>
            @endif
        </div>

        <div class="card-body p-3.5">
            @if($rekap->exists && $rekap->isValidated())
                <div class="alert alert-success border-0 rounded-3 text-sm d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-patch-check-fill"></i>
                    <span>
                        Dokumen telah divalidasi oleh <strong>{{ $rekap->validator?->nama ?? '-' }}</strong>
                        pada {{ $rekap->validated_at?->translatedFormat('d F Y H:i') ?? '-' }}.
                        Catatan terkunci dan tidak dapat diubah lagi.
                    </span>
                </div>
            @endif

            <div class="row g-3 mb-3">
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold text-muted text-xs text-uppercase">Koordinator Shift Pagi</label>
                    <div class="catatan-kotak d-flex align-items-center gap-2">
                        @if($rekap->koordinatorPagi)
                            <i class="bi bi-sun text-warning"></i>
                            <span>{{ $rekap->koordinatorPagi->nama }}</span>
                        @else
                            <span class="text-muted">Belum ditentukan / tidak terjadwal</span>
                        @endif
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold text-muted text-xs text-uppercase">Koordinator Shift Siang</label>
                    <div class="catatan-kotak d-flex align-items-center gap-2">
                        @if($rekap->koordinatorSiang)
                            <i class="bi bi-moon-stars text-primary"></i>
                            <span>{{ $rekap->koordinatorSiang->nama }}</span>
                        @else
                            <span class="text-muted">Belum ditentukan / tidak terjadwal</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold text-muted text-xs text-uppercase">Catatan Koordinator Shift Pagi</label>
                    <div class="catatan-kotak">
                        {{ $rekap->catatan_pagi ?? 'Belum ada catatan dari koordinator shift pagi.' }}
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold text-muted text-xs text-uppercase">Catatan Koordinator Shift Siang</label>
                    <div class="catatan-kotak">
                        {{ $rekap->catatan_siang ?? 'Belum ada catatan dari koordinator shift siang.' }}
                    </div>
                </div>
            </div>

            {{-- Aksi validasi (hanya saat draft) --}}
            @if(! $rekap->exists || $rekap->isDraft())
                <div class="d-flex align-items-center gap-2 flex-wrap border-top pt-3">
                    <form method="POST" action="{{ route('waka-piket.validasi') }}" onsubmit="return confirm('Validasi rekap tanggal {{ \Carbon\Carbon::parse($tanggal)->translatedFormat('d F Y') }}? Setelah divalidasi dokumen terkunci.')">
                        @csrf
                        <input type="hidden" name="tanggal" value="{{ $tanggal }}">
                        <button type="submit" class="btn btn-success rounded-3 fw-semibold text-sm d-inline-flex align-items-center gap-1.5 shadow-sm">
                            <i class="bi bi-patch-check-fill"></i> Validasi Rekap
                        </button>
                    </form>
                    <span class="text-muted text-2xs">Validasi mengunci dokumen: status menjadi Tervalidasi dengan jejak Waka Piket.</span>
                </div>
            @endif
        </div>
    </div>

    {{-- ============================================================== --}}
    {{-- 2. FORM CATATAN KLB (KEJADIAN LUAR BIASA)                       --}}
    {{-- ============================================================== --}}
    <div class="card border rounded-3 shadow-2xs mb-4">
        <div class="card-header bg-white border-bottom py-3 px-3.5">
            <h5 class="fw-bold text-dark mb-0" style="font-size: 1rem;">
                <i class="bi bi-exclamation-triangle text-danger me-1.5"></i> Catatan Kejadian Luar Biasa (KLB)
            </h5>
            <p class="text-muted mb-0 text-xs">
                Catatan insiden / kejadian luar biasa pada tanggal {{ \Carbon\Carbon::parse($tanggal)->translatedFormat('d F Y') }}.
            </p>
        </div>
        <div class="card-body p-3.5">
            @if($rekap->exists && $rekap->isValidated())
                <div class="catatan-kotak mb-2">
                    {{ $rekap->catatan_klb ?? 'Tidak ada catatan KLB.' }}
                </div>
                <div class="alert alert-light border rounded-3 text-xs text-muted mb-0">
                    <i class="bi bi-lock-fill me-1"></i> Rekap sudah tervalidasi — catatan KLB terkunci. Hubungi Petugas IT untuk revisi dokumen.
                </div>
            @else
                <form method="POST" action="{{ route('waka-piket.klb') }}">
                    @csrf
                    <input type="hidden" name="tanggal" value="{{ $tanggal }}">
                    <textarea name="catatan_klb" rows="4" class="form-control rounded-3 border"
                              placeholder="Contoh: Terjadi pemadaman listrik pada jam 09.00-10.00, KBM dilaksanakan mandiri...">{{ old('catatan_klb', $rekap->catatan_klb) }}</textarea>
                    <div class="d-flex align-items-center justify-content-between mt-2 flex-wrap gap-2">
                        <span class="text-muted text-2xs">Maksimal 2000 karakter.</span>
                        <button type="submit" class="btn btn-danger rounded-3 fw-semibold text-sm d-inline-flex align-items-center gap-1.5">
                            <i class="bi bi-save"></i> Simpan Catatan KLB
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    {{-- ============================================================== --}}
    {{-- 3. RIWAYAT REKAP HARIAN                                         --}}
    {{-- ============================================================== --}}
    <div class="card border rounded-3 shadow-2xs mb-4">
        <div class="card-header bg-white border-bottom py-3 px-3.5 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h5 class="fw-bold text-dark mb-0" style="font-size: 1rem;">
                    <i class="bi bi-journal-text text-primary me-1.5"></i> Riwayat Rekap Harian
                </h5>
                <p class="text-muted mb-0 text-xs">
                    Seluruh rekap harian piket — satu baris per tanggal.
                </p>
            </div>
            <span class="badge bg-light text-dark border text-xs">{{ $daftarRekap->total() }} Rekap</span>
        </div>

        <div class="table-responsive">
            <table class="table table-custom-wp table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 50px;" class="text-center">No</th>
                        <th>Tanggal</th>
                        <th>Koordinator Pagi</th>
                        <th>Koordinator Siang</th>
                        <th>Status</th>
                        <th>Divalidasi Oleh</th>
                        <th>Catatan KLB</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($daftarRekap as $index => $row)
                        <tr>
                            <td class="text-center text-muted fw-semibold">{{ $daftarRekap->firstItem() + $index }}</td>
                            <td>
                                <div class="fw-bold text-dark">{{ $row->tanggal->translatedFormat('d F Y') }}</div>
                                <div class="text-muted text-2xs">{{ $row->tanggal->translatedFormat('l') }}</div>
                            </td>
                            <td>
                                @if($row->koordinatorPagi)
                                    <span class="text-sm">{{ $row->koordinatorPagi->nama }}</span>
                                @else
                                    <span class="text-muted text-2xs">-</span>
                                @endif
                            </td>
                            <td>
                                @if($row->koordinatorSiang)
                                    <span class="text-sm">{{ $row->koordinatorSiang->nama }}</span>
                                @else
                                    <span class="text-muted text-2xs">-</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $row->status_badge }} px-2.5 py-1 rounded-pill text-xs">
                                    <i class="bi {{ $row->isValidated() ? 'bi-check-circle' : 'bi-hourglass-split' }} me-1"></i>
                                    {{ $row->status_label }}
                                </span>
                            </td>
                            <td class="text-sm">
                                @if($row->isValidated())
                                    @if($row->validator)
                                        {{ $row->validator->nama }}
                                        <div class="text-muted text-2xs">{{ $row->validated_at?->translatedFormat('d F Y H:i') }}</div>
                                    @else
                                        <span class="text-muted text-2xs">-</span>
                                    @endif
                                @else
                                    <span class="text-muted text-2xs">Belum divalidasi</span>
                                @endif
                            </td>
                            <td class="text-sm">
                                @if($row->catatan_klb)
                                    <span class="text-truncate d-inline-block" style="max-width: 200px;" title="{{ $row->catatan_klb }}">{{ $row->catatan_klb }}</span>
                                @else
                                    <span class="text-muted text-2xs">-</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('waka-piket.rekap-harian', ['tanggal' => $row->tanggal->toDateString()]) }}" class="btn btn-sm btn-light border rounded-3 text-xs fw-semibold">
                                    <i class="bi bi-eye"></i> Buka
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                <i class="bi bi-journal-x fs-2 d-block mb-1 text-secondary"></i>
                                Belum ada rekap harian yang dibuat.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($daftarRekap->hasPages())
            <div class="card-footer bg-white border-top py-2.5">
                {{ $daftarRekap->links() }}
            </div>
        @endif
    </div>

</div>
@endsection