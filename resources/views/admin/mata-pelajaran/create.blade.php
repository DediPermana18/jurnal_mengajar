@extends('layouts.app')

@section('title', 'Tambah Mata Pelajaran - WebJournal Management System')

@section('content')
<div class="container-fluid px-0">

    {{-- Header dengan Tombol Kembali --}}
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('mapel.index') }}"
               class="btn btn-light border rounded-3 px-3 py-2 fw-semibold d-flex align-items-center gap-2"
               style="font-size: 0.875rem;">
                <i class="bi bi-arrow-left"></i>
                <span>Kembali</span>
            </a>
            <div>
                <h2 class="fw-black text-dark mb-0" style="font-weight: 900; font-size: 1.75rem; letter-spacing: -0.02em;">
                    Tambah Mata Pelajaran
                </h2>
                <p class="text-muted mb-0" style="font-size: 0.875rem;">
                    Masukkan data mata pelajaran baru ke dalam sistem.
                </p>
            </div>
        </div>
    </div>

    {{-- Alert Error Validation --}}
    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4" role="alert" style="font-size: 0.9rem;">
            <i class="bi bi-exclamation-circle-fill me-2"></i><strong>Terjadi kesalahan input:</strong>
            <ul class="mb-0 mt-1 ps-3">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Dedicated Form Card --}}
    <div class="card border-0 rounded-4 shadow-sm bg-white overflow-hidden" style="max-width: 800px;">
        <div class="card-header bg-white border-0 pt-4 pb-3 px-4">
            <div class="d-flex align-items-center gap-2">
                <div class="rounded-2 d-flex align-items-center justify-content-center bg-primary-subtle text-primary"
                     style="width: 36px; height: 36px;">
                    <i class="bi bi-plus-circle-fill fs-5"></i>
                </div>
                <h5 class="fw-bold mb-0 text-dark">Form Tambah Mata Pelajaran</h5>
            </div>
        </div>

        <form method="POST" action="{{ route('mapel.store') }}" id="formTambahMapel">
            @csrf

            <div class="card-body p-4 pt-2">
                <div class="row g-4">
                    {{-- Kode Mapel --}}
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-bold text-dark" style="font-size: 0.875rem;">
                            Kode Mapel <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="kode_mapel" class="form-control rounded-3 py-2"
                               placeholder="Contoh: MTK, IND, IPA, RPL-01"
                               value="{{ old('kode_mapel') }}" required autocomplete="off">
                        <div class="form-text text-muted" style="font-size: 0.78rem;">
                            Kode singkatan mata pelajaran yang bersifat unik.
                        </div>
                    </div>

                    {{-- Jenis Mapel --}}
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-bold text-dark" style="font-size: 0.875rem;">
                            Jenis Mapel <span class="text-danger">*</span>
                        </label>
                        <select name="kelompok" class="form-select rounded-3 py-2" required>
                            @foreach($jenisOptions as $opt)
                                <option value="{{ $opt }}" {{ old('kelompok') === $opt ? 'selected' : '' }}>
                                    {{ $opt }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Nama Mapel --}}
                    <div class="col-12">
                        <label class="form-label fw-bold text-dark" style="font-size: 0.875rem;">
                            Nama Mata Pelajaran <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="nama_mapel" class="form-control rounded-3 py-2"
                               placeholder="Contoh: Pemrograman Web dan Perangkat Bergerak"
                               value="{{ old('nama_mapel') }}" required autocomplete="off">
                    </div>
                </div>
            </div>

            {{-- Card Footer --}}
            <div class="card-footer bg-white border-0 p-4 pt-2 d-flex align-items-center justify-content-between flex-wrap gap-3">
                <a href="{{ route('mapel.index') }}" class="btn btn-light rounded-3 px-4">
                    Batal
                </a>
                <button type="submit" class="btn btn-primary rounded-3 px-4 fw-semibold shadow-sm" style="font-size: 0.9rem;">
                    <i class="bi bi-check-lg me-1"></i> Simpan Mapel
                </button>
            </div>
        </form>
    </div>

</div>
@endsection
