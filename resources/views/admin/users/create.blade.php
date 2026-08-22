@extends('layouts.app')

@section('title', 'Tambah User - WebJournal Management System')

@section('content')
<div class="container-fluid px-0">
    <div class="mb-4">
        <a href="{{ route('admin.users.index') }}" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left me-1"></i> Kembali ke Kelola User</a>
        <h2 class="fw-black text-dark mt-2 mb-1" style="letter-spacing: -0.02em; font-weight: 800; font-size: 1.75rem;">Tambah User Baru</h2>
        <p class="text-muted mb-0" style="font-size: 0.9rem;">Buat akun baru dengan hak akses sistem yang sesuai.</p>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4" role="alert">
            <strong class="d-block mb-1">Terjadi kesalahan:</strong>
            <ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
        <form action="{{ route('admin.users.store') }}" method="POST">
            @include('admin.users._form', ['isEdit' => false])
            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                <a href="{{ route('admin.users.index') }}" class="btn btn-light rounded-3 px-4">Batal</a>
                <button type="submit" class="btn btn-primary rounded-3 px-4 fw-semibold"><i class="bi bi-check-lg me-1"></i> Simpan User</button>
            </div>
        </form>
    </div>
</div>
@endsection
