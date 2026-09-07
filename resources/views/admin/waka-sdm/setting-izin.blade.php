@extends('layouts.app')

@section('title', 'Pengaturan Alur Izin Guru - Waka SDM')

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
                <a href="{{ route('waka-sdm.rekap-izin') }}" class="text-decoration-none text-muted text-xs">
                    Rekap Izin
                </a>
                <span class="text-muted text-xs">/</span>
                <span class="text-xs fw-semibold text-primary">Pengaturan Alur Izin</span>
            </div>
            <h2 class="fw-black text-dark mb-1" style="font-weight: 900; font-size: 1.5rem; letter-spacing: -0.02em;">
                Pengaturan Alur Approval Izin Guru
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.875rem;">
                Konfigurasi tingkatan persetujuan berjenjang dan nomor kontak WhatsApp Waka SDM & Kepala Sekolah.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('waka-sdm.rekap-izin') }}" class="btn btn-outline-secondary rounded-3 text-xs fw-semibold">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Rekap Izin
            </a>
        </div>
    </div>

    {{-- Flash Alert Messages --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4 d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4 d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <div>{{ $errors->first() }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-4">
        {{-- Form Setting --}}
        <div class="col-12 col-lg-7">
            <div class="card border rounded-3 shadow-2xs">
                <div class="card-header bg-white border-bottom py-3 px-4">
                    <h5 class="fw-bold text-dark mb-0 fs-6">
                        <i class="bi bi-sliders me-1.5 text-primary"></i> Konfigurasi Level Persetujuan
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('waka-sdm.izin.setting.update') }}" method="POST">
                        @csrf

                        {{-- Level Approval --}}
                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark text-sm mb-2">
                                Tingkat Alur Persetujuan (Approval Level)
                            </label>
                            
                            <div class="d-flex flex-column gap-2.5">
                                {{-- Option 1 Level --}}
                                <label class="p-3 border rounded-3 d-flex align-items-start gap-3 cursor-pointer bg-light-subtle hover-bg-light transition">
                                    <input type="radio" name="izin_approval_level" value="1" class="form-check-input mt-1" {{ old('izin_approval_level', $level) == 1 ? 'checked' : '' }}>
                                    <div>
                                        <div class="fw-bold text-dark text-sm">1 Tingkat: Verifikasi Guru Piket Saja</div>
                                        <div class="text-muted text-xs mt-0.5">
                                            Pengajuan izin langsung disetujui penuh begitu diverifikasi oleh Guru Piket di pos piket.
                                        </div>
                                    </div>
                                </label>

                                {{-- Option 2 Level --}}
                                <label class="p-3 border rounded-3 d-flex align-items-start gap-3 cursor-pointer bg-light-subtle hover-bg-light transition">
                                    <input type="radio" name="izin_approval_level" value="2" class="form-check-input mt-1" {{ old('izin_approval_level', $level) == 2 ? 'checked' : '' }}>
                                    <div>
                                        <div class="fw-bold text-dark text-sm">2 Tingkat: Guru Piket &rarr; Waka SDM</div>
                                        <div class="text-muted text-xs mt-0.5">
                                            Guru Piket memverifikasi surat/tugas &rarr; Waka SDM menyetujui final (tanpa Kepsek).
                                        </div>
                                    </div>
                                </label>

                                {{-- Option 3 Level (Standard) --}}
                                <label class="p-3 border rounded-3 d-flex align-items-start gap-3 cursor-pointer bg-light-subtle hover-bg-light transition">
                                    <input type="radio" name="izin_approval_level" value="3" class="form-check-input mt-1" {{ old('izin_approval_level', $level) == 3 ? 'checked' : '' }}>
                                    <div>
                                        <div class="fw-bold text-dark text-sm">
                                            3 Tingkat: Guru Piket &rarr; Waka SDM &rarr; Kepala Sekolah
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1 text-2xs">Rekomendasi</span>
                                        </div>
                                        <div class="text-muted text-xs mt-0.5">
                                            Guru Piket memverifikasi &rarr; Waka SDM menyetujui & TTD &rarr; Kepala Sekolah menyetujui final via WhatsApp/Web.
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <hr class="my-4 text-muted opacity-25">

                        {{-- Nomor WhatsApp Waka SDM --}}
                        <div class="mb-3">
                            <label for="no_wa_waka" class="form-label fw-bold text-dark text-sm mb-1">
                                <i class="bi bi-whatsapp text-success me-1"></i> Nomor WhatsApp Waka SDM / Kepegawaian
                            </label>
                            <input type="text" 
                                   name="no_wa_waka" 
                                   id="no_wa_waka" 
                                   class="form-control rounded-3 py-2 text-sm @error('no_wa_waka') is-invalid @enderror" 
                                   placeholder="Contoh: 081234567890 atau 6281234567890"
                                   value="{{ old('no_wa_waka', $noWaWaka) }}">
                            <div class="text-muted text-2xs mt-1">
                                Digunakan untuk menerima tautan persetujuan cepat izin guru via WhatsApp.
                            </div>
                            @error('no_wa_waka')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Nomor WhatsApp Kepala Sekolah --}}
                        <div class="mb-4">
                            <label for="no_wa_kepsek" class="form-label fw-bold text-dark text-sm mb-1">
                                <i class="bi bi-whatsapp text-success me-1"></i> Nomor WhatsApp Kepala Sekolah
                            </label>
                            <input type="text" 
                                   name="no_wa_kepsek" 
                                   id="no_wa_kepsek" 
                                   class="form-control rounded-3 py-2 text-sm @error('no_wa_kepsek') is-invalid @enderror" 
                                   placeholder="Contoh: 081234567890 atau 6281234567890"
                                   value="{{ old('no_wa_kepsek', $noWaKepsek) }}">
                            <div class="text-muted text-2xs mt-1">
                                Digunakan untuk tautan approval tahap final Kepala Sekolah pada alur 3 tingkat.
                            </div>
                            @error('no_wa_kepsek')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <button type="submit" class="btn btn-primary rounded-3 px-4 py-2 fw-semibold text-sm">
                                <i class="bi bi-check2-circle me-1"></i> Simpan Pengaturan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Info Box / Flow Diagram --}}
        <div class="col-12 col-lg-5">
            <div class="card border rounded-3 shadow-2xs mb-4">
                <div class="card-header bg-white border-bottom py-3 px-4">
                    <h5 class="fw-bold text-dark mb-0 fs-6">
                        <i class="bi bi-diagram-3 me-1.5 text-info"></i> Skema Alur Saat Ini
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div class="p-3 bg-light rounded-3 mb-3 border">
                        <div class="fw-bold text-dark text-xs text-uppercase mb-2 text-muted">Status Aktif: Tingkat {{ $level }}</div>
                        @if($level == 1)
                            <div class="d-flex align-items-center gap-2 text-xs fw-semibold text-dark">
                                <span class="badge bg-warning-subtle text-warning-emphasis p-2">Guru Piket</span>
                                <i class="bi bi-arrow-right text-muted"></i>
                                <span class="badge bg-success-subtle text-success p-2">Disetujui Penuh</span>
                            </div>
                        @elseif($level == 2)
                            <div class="d-flex align-items-center gap-2 text-xs fw-semibold text-dark flex-wrap">
                                <span class="badge bg-warning-subtle text-warning-emphasis p-2">Guru Piket</span>
                                <i class="bi bi-arrow-right text-muted"></i>
                                <span class="badge bg-primary-subtle text-primary p-2">Waka SDM</span>
                                <i class="bi bi-arrow-right text-muted"></i>
                                <span class="badge bg-success-subtle text-success p-2">Disetujui Penuh</span>
                            </div>
                        @else
                            <div class="d-flex align-items-center gap-2 text-xs fw-semibold text-dark flex-wrap">
                                <span class="badge bg-warning-subtle text-warning-emphasis p-2">Guru Piket</span>
                                <i class="bi bi-arrow-right text-muted"></i>
                                <span class="badge bg-primary-subtle text-primary p-2">Waka SDM</span>
                                <i class="bi bi-arrow-right text-muted"></i>
                                <span class="badge bg-info-subtle text-info p-2">Kepala Sekolah</span>
                                <i class="bi bi-arrow-right text-muted"></i>
                                <span class="badge bg-success-subtle text-success p-2">Disetujui Penuh</span>
                            </div>
                        @endif
                    </div>

                    <h6 class="fw-bold text-dark text-xs text-uppercase mb-2">Penjelasan Alur:</h6>
                    <ul class="text-xs text-muted ps-3 mb-0" style="line-height: 1.7;">
                        <li><strong>Guru Mengajukan:</strong> Guru mengisi form izin dan melampirkan surat/tugas siswa.</li>
                        <li><strong>Guru Piket:</strong> Memverifikasi surat fisik dan menugaskan guru pengganti / cover di pos piket.</li>
                        <li><strong>Waka SDM:</strong> Menerima notifikasi WhatsApp dan menandatangani persetujuan secara digital.</li>
                        <li><strong>Kepala Sekolah:</strong> Menerima link persetujuan akhir (pada mode 3-tingkat).</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
