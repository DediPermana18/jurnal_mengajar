@extends('layouts.app')

@section('title', 'Edit Ruangan - Data Master Ruangan')

@section('content')
<div class="container-fluid px-0">

    {{-- Page Header --}}
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('ruangan.index') }}"
               class="btn btn-light border rounded-3 px-3 py-2 fw-semibold d-flex align-items-center gap-2"
               style="font-size: 0.875rem;">
                <i class="bi bi-arrow-left"></i>
                <span>Kembali</span>
            </a>
            <div>
                <h2 class="fw-black text-dark mb-0" style="font-weight: 800; font-size: 1.75rem; letter-spacing: -0.02em;">
                    Edit Ruangan
                </h2>
                <p class="text-muted mb-0" style="font-size: 0.9rem;">
                    Perbarui data ruangan <strong>{{ $ruangan->nama_ruangan }}</strong> beserta pengurusnya.
                </p>
            </div>
        </div>
    </div>

    {{-- Validation Errors --}}
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Terjadi kesalahan input:</strong>
            <ul class="mb-0 mt-1 ps-3 small">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form action="{{ route('ruangan.update', $ruangan->id) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="space-y-6">

            {{-- Section 1: Informasi Ruangan --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="bi bi-building text-primary fs-5"></i>
                    <h5 class="fw-bold text-dark mb-0">Informasi Ruangan</h5>
                </div>
                <p class="text-muted small mb-4">Lengkapi identitas ruangan pada form di bawah ini.</p>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="kode_ruangan" class="form-label fw-semibold">Kode Ruangan <span class="text-danger">*</span></label>
                        <input type="text" name="kode_ruangan" id="kode_ruangan"
                               class="form-control rounded-3 @error('kode_ruangan') is-invalid @enderror"
                               value="{{ old('kode_ruangan', $ruangan->kode_ruangan) }}" placeholder="contoh: R-101" required>
                        @error('kode_ruangan') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-8">
                        <label for="nama_ruangan" class="form-label fw-semibold">Nama Ruangan <span class="text-danger">*</span></label>
                        <input type="text" name="nama_ruangan" id="nama_ruangan"
                               class="form-control rounded-3 @error('nama_ruangan') is-invalid @enderror"
                               value="{{ old('nama_ruangan', $ruangan->nama_ruangan) }}" placeholder="contoh: Kelas 101" required>
                        @error('nama_ruangan') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12">
                        <label for="lokasi" class="form-label fw-semibold">Lokasi / Gedung</label>
                        <input type="text" name="lokasi" id="lokasi"
                               class="form-control rounded-3 @error('lokasi') is-invalid @enderror"
                               value="{{ old('lokasi', $ruangan->lokasi) }}" placeholder="contoh: Gedung A Lantai 1">
                        @error('lokasi') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>

            {{-- Section 2: Pengurus Ruangan --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="bi bi-people text-primary fs-5"></i>
                    <h5 class="fw-bold text-dark mb-0">Pengurus Ruangan</h5>
                </div>
                <p class="text-muted small mb-4">Pilih guru yang bertanggung jawab mengelola ruangan ini.</p>

                @include('admin.ruangan._pengurus')
            </div>
        </div>

        {{-- Footer Actions --}}
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mt-6 pb-2">
            <a href="{{ route('ruangan.index') }}" class="btn btn-light rounded-3 px-4 fw-semibold">
                <i class="bi bi-x-lg me-1"></i> Batal
            </a>
            <button type="submit" class="btn btn-warning text-white rounded-3 px-4 fw-semibold shadow-sm" style="font-size: 0.9rem;">
                <i class="bi bi-check-lg me-1"></i> Simpan Perubahan
            </button>
        </div>
    </form>

</div>
@endsection