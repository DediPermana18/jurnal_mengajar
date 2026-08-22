@extends('layouts.app')

@section('title', 'Edit Guru - WebJournal Management System')

@section('content')
<div class="container-fluid px-0">
    <div class="mb-4">
        <a href="{{ route('guru.index') }}" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left me-1"></i> Kembali ke Data Guru</a>
        <h2 class="fw-black text-dark mt-2 mb-1" style="letter-spacing: -0.02em; font-weight: 800; font-size: 1.75rem;">Edit Data Guru</h2>
        <p class="text-muted mb-0">Perbarui akun guru dan penugasan akademiknya.</p>
    </div>
    @if ($errors->any())
        <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4"><strong>Gagal menyimpan data:</strong><ul class="mb-0 ps-3">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
        <form action="{{ route('guru.update', $guru->id) }}" method="POST">
            @include('admin.guru._form', ['isEdit' => true])
            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top"><a href="{{ route('guru.index') }}" class="btn btn-light rounded-3 px-4">Batal</a><button type="submit" class="btn btn-primary rounded-3 px-4 fw-semibold"><i class="bi bi-check-lg me-1"></i> Simpan Perubahan</button></div>
        </form>
    </div>
</div>
@endsection
