@extends('layouts.app')

@section('title', 'Pengaturan Approval Izin Guru')

@section('content')
<div class="container-fluid px-0" style="max-width: 760px;">

    {{-- Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="font-weight: 900; font-size: 1.75rem; letter-spacing: -0.02em;">
                Pengaturan Approval Izin Guru
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                Atur jumlah level persetujuan izin dan nomor WhatsApp Waka/Kepala Sekolah.
            </p>
        </div>
        <a href="{{ route('kurikulum.izin.index') }}" class="btn btn-outline-secondary rounded-3 px-3 py-2 fw-semibold">
            <i class="bi bi-arrow-left me-1"></i> Kembali
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4 d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-check-circle-fill text-success fs-5"></i><div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4" role="alert">
            <ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form method="POST" action="{{ route('kurikulum.izin.setting.update') }}">
        @csrf

        {{-- Level Approval --}}
        <div class="table-card-custom mb-4">
            <h5 class="fw-bold text-dark mb-1"><i class="bi bi-diagram-3 me-2 text-primary"></i> Jumlah Level Persetujuan</h5>
            <p class="text-muted small mb-4">Pilih berapa tahap persetujuan yang diperlukan. Level yang tidak terpakai otomatis dilewati.</p>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-check d-flex gap-3 align-items-start p-3 border rounded-4 {{ $level === 1 ? 'border-primary' : 'border' }}" style="cursor:pointer;">
                        <input class="form-check-input mt-1" type="radio" name="izin_approval_level" value="1" {{ $level === 1 ? 'checked' : '' }}>
                        <span>
                            <span class="fw-bold d-block">1 Level</span>
                            <span class="text-muted small">Verifikasi Piket langsung Disetujui. Tanpa Waka/Kepsek.</span>
                        </span>
                    </label>
                </div>
                <div class="col-md-4">
                    <label class="form-check d-flex gap-3 align-items-start p-3 border rounded-4 {{ $level === 2 ? 'border-primary' : 'border' }}" style="cursor:pointer;">
                        <input class="form-check-input mt-1" type="radio" name="izin_approval_level" value="2" {{ $level === 2 ? 'checked' : '' }}>
                        <span>
                            <span class="fw-bold d-block">2 Level</span>
                            <span class="text-muted small">Piket <i class="bi bi-arrow-right"></i> Kepala Sekolah (tanpa Waka).</span>
                        </span>
                    </label>
                </div>
                <div class="col-md-4">
                    <label class="form-check d-flex gap-3 align-items-start p-3 border rounded-4 {{ $level === 3 ? 'border-primary' : 'border' }}" style="cursor:pointer;">
                        <input class="form-check-input mt-1" type="radio" name="izin_approval_level" value="3" {{ $level === 3 ? 'checked' : '' }}>
                        <span>
                            <span class="fw-bold d-block">3 Level</span>
                            <span class="text-muted small">Piket <i class="bi bi-arrow-right"></i> Waka <i class="bi bi-arrow-right"></i> Kepala Sekolah. (Penuh)</span>
                        </span>
                    </label>
                </div>
            </div>
        </div>

        {{-- Nomor WA --}}
        <div class="table-card-custom mb-4">
            <h5 class="fw-bold text-dark mb-1"><i class="bi bi-whatsapp me-2 text-success"></i> Nomor WhatsApp Approver</h5>
            <p class="text-muted small mb-4">Untuk mengirim tautan persetujuan otomatis (format: 08xx atau 62xx).</p>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-dark">Nomor WA Waka</label>
                    <input type="text" name="no_wa_waka" class="form-control rounded-3" maxlength="20"
                           value="{{ $setting->no_wa_waka }}" placeholder="contoh: 081234567890">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-dark">Nomor WA Kepala Sekolah</label>
                    <input type="text" name="no_wa_kepsek" class="form-control rounded-3" maxlength="20"
                           value="{{ $setting->no_wa_kepsek }}" placeholder="contoh: 081298765432">
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('kurikulum.izin.index') }}" class="btn btn-light rounded-3 px-4 py-2 fw-semibold">Batal</a>
            <button type="submit" class="btn btn-primary rounded-3 px-4 py-2 fw-semibold shadow-sm">
                <i class="bi bi-check-lg me-1"></i> Simpan Pengaturan
            </button>
        </div>
    </form>
</div>
@endsection
