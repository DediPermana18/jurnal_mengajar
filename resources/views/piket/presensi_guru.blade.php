@extends('layouts.app')

@section('title', 'Presensi Guru - WebJournal')

@section('content')
<div class="container-fluid px-0">
    <div class="mb-4">
        <h2 class="fw-black text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 800; font-size: 1.75rem;">
            Presensi Guru Harian
        </h2>
        <p class="text-muted mb-0" style="font-size: 0.9rem;">
            Catat dan pantau kehadiran guru — {{ now()->translatedFormat('l, d F Y') }}
        </p>
    </div>

    <div class="card border-0 shadow-sm rounded-4 p-5 text-center">
        <i class="bi bi-person-check-fill text-primary mb-3" style="font-size: 3rem;"></i>
        <h5 class="fw-bold text-dark mb-2">Halaman Presensi Guru</h5>
        <p class="text-muted">Fitur input presensi guru akan segera tersedia di sini.</p>
        <a href="{{ route('piket.dashboard') }}" class="btn btn-light rounded-3 mt-2">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Dashboard
        </a>
    </div>
</div>
@endsection
