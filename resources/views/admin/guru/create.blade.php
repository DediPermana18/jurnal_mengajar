@extends('layouts.app')

@section('title', 'Tambah Guru - WebJournal Management System')

@section('content')
<div class="container-fluid px-0" style="max-width: 760px;">

    @if(auth()->user()?->isTestingUser())
        <div class="alert alert-warning alert-dismissible fade show rounded-4 shadow-sm mb-4" role="alert">
            <strong>Mode Preview Active:</strong> Data Master bersifat Read-Only untuk mencegah perubahan pada data produksi.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-1">Tambah Data Guru</h3>
            <p class="text-muted small mb-0">Isi formulir di bawah ini untuk menginput data guru baru.</p>
        </div>
        <a href="{{ route('guru.index') }}" class="btn btn-light border rounded-3 px-3 py-2 fw-semibold d-flex align-items-center gap-2">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger border-0 rounded-4 shadow-sm mb-4">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <fieldset @if(auth()->user()?->isTestingUser()) disabled @endif>
        <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
            <form action="{{ route('guru.store') }}" method="POST">
                @include('admin.guru._form', ['isEdit' => false])
                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top"><a href="{{ route('guru.index') }}" class="btn btn-light rounded-3 px-4">Batal</a><button type="submit" class="btn btn-primary rounded-3 px-4 fw-semibold"><i class="bi bi-check-lg me-1"></i> Simpan Guru</button></div>
            </form>
        </div>
    </fieldset>
</div>
@endsection
