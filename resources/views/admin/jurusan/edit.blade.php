@extends('layouts.app')

@section('title', 'Edit Data Jurusan - WebJournal Management System')

@section('content')
<div class="container-fluid px-0" style="max-width: 760px;">
    {{-- Header --}}
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-1" style="font-size: 1.65rem; letter-spacing: -0.02em;">Edit Data Jurusan</h3>
            <p class="text-muted small mb-0">Perbarui informasi kode atau nama jurusan di bawah ini.</p>
        </div>
        <a href="{{ route('jurusan.index') }}" class="btn btn-light border rounded-3 px-3 py-2 fw-semibold d-flex align-items-center gap-2">
            <i class="bi bi-arrow-left"></i> <span>Kembali</span>
        </a>
    </div>

    {{-- Error Alert --}}
    @if ($errors->any())
        <div class="alert alert-danger border-0 rounded-4 shadow-sm mb-4" role="alert">
            <strong class="d-block mb-1">Gagal memperbarui data:</strong>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Form Card --}}
    <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
        <form action="{{ route('jurusan.update', $jurusan->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label class="form-label fw-semibold text-dark">Kode Jurusan <span class="text-danger">*</span></label>
                <input type="text"
                       name="kode_jurusan"
                       class="form-control rounded-3 py-2 @error('kode_jurusan') is-invalid @enderror"
                       value="{{ old('kode_jurusan', $jurusan->kode_jurusan) }}"
                       placeholder="Contoh: RPL, TKJ, DKV, AKL"
                       maxlength="20"
                       required>
                <div class="form-text text-muted">Gunakan singkatan atau kode unik jurusan (maksimal 20 karakter).</div>
                @error('kode_jurusan')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold text-dark">Nama Lengkap Jurusan <span class="text-danger">*</span></label>
                <input type="text"
                       name="nama_jurusan"
                       class="form-control rounded-3 py-2 @error('nama_jurusan') is-invalid @enderror"
                       value="{{ old('nama_jurusan', $jurusan->nama_jurusan) }}"
                       placeholder="Contoh: Rekayasa Perangkat Lunak"
                       maxlength="100"
                       required>
                @error('nama_jurusan')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                <a href="{{ route('jurusan.index') }}" class="btn btn-light border rounded-3 px-4 py-2">Batal</a>
                <button type="submit" class="btn btn-primary rounded-3 px-4 py-2 fw-semibold d-flex align-items-center gap-2"
                        style="background-color: var(--primary-blue, #1677ff); border-color: var(--primary-blue, #1677ff);">
                    <i class="bi bi-check-lg"></i>
                    <span>Simpan Perubahan</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
