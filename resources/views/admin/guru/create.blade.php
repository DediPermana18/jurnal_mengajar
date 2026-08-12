@extends('admin.layouts.app')

@section('title', 'Tambah Guru Baru - WebJournal')

@section('content')
<div class="container-fluid px-0" style="max-width: 760px;">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-1">Tambah Guru Baru</h3>
            <p class="text-muted small mb-0">Isi formulir di bawah ini untuk menambahkan data guru baru.</p>
        </div>
        <a href="{{ route('guru.index') }}" class="btn btn-light border rounded-3 px-3 py-2 fw-semibold d-flex align-items-center gap-2">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>

    <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
        <form action="{{ route('guru.store') }}" method="POST">
            @csrf
            
            <div class="mb-3">
                <label class="form-label fw-semibold text-dark">NIP (Nomor Induk Pegawai)</label>
                <input type="text" name="nip" class="form-control rounded-3 py-2" placeholder="Masukkan NIP (Opsional)">
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold text-dark">Nama Guru <span class="text-danger">*</span></label>
                <input type="text" name="nama_guru" class="form-control rounded-3 py-2" required placeholder="Masukkan Nama Lengkap Beserta Gelar">
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold text-dark">No HP / WhatsApp</label>
                <input type="text" name="no_hp" class="form-control rounded-3 py-2" placeholder="Contoh: 081234567890">
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('guru.index') }}" class="btn btn-light border rounded-3 px-4 py-2">Batal</a>
                <button type="submit" class="btn btn-primary rounded-3 px-4 py-2 fw-semibold">Simpan Data</button>
            </div>
        </form>
    </div>

</div>
@endsection
