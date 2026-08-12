@extends('admin.layouts.app')

@section('title', 'Edit Data Guru - WebJournal')

@section('content')
<div class="container-fluid px-0" style="max-width: 760px;">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-1">Edit Data Guru</h3>
            <p class="text-muted small mb-0">Perbarui informasi data guru pengajar di bawah ini.</p>
        </div>
        <a href="{{ route('guru.index') }}" class="btn btn-light border rounded-3 px-3 py-2 fw-semibold d-flex align-items-center gap-2">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>

    <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
        <form action="{{ route('guru.update', $guru->id_guru) }}" method="POST">
            @csrf
            @method('PUT')
            
            <div class="mb-3">
                <label class="form-label fw-semibold text-dark">NIP (Nomor Induk Pegawai)</label>
                <input type="text" name="nip" class="form-control rounded-3 py-2 @error('nip') is-invalid @enderror" value="{{ old('nip', $guru->nip) }}" placeholder="Masukkan NIP (Opsional)">
                @error('nip')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold text-dark">Nama Guru <span class="text-danger">*</span></label>
                <input type="text" name="nama_guru" class="form-control rounded-3 py-2 @error('nama_guru') is-invalid @enderror" value="{{ old('nama_guru', $guru->nama_guru) }}" required placeholder="Masukkan Nama Lengkap Beserta Gelar">
                @error('nama_guru')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold text-dark">No. HP / WhatsApp</label>
                <input type="text" name="no_hp" class="form-control rounded-3 py-2 @error('no_hp') is-invalid @enderror" value="{{ old('no_hp', $guru->no_hp) }}" placeholder="Contoh: 081234567890">
                @error('no_hp')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('guru.index') }}" class="btn btn-light border rounded-3 px-4 py-2">Batal</a>
                <button type="submit" class="btn btn-primary rounded-3 px-4 py-2 fw-semibold">Simpan Perubahan</button>
            </div>
        </form>
    </div>

</div>
@endsection
