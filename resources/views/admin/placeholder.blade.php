@extends('layouts.app')

@section('title', ($title ?? 'Fitur Belum Ditambahkan') . ' - WebJournal')

@section('content')
<div class="container-fluid px-0">

    @if(isset($title))
    <div class="mb-4">
        <h2 class="fw-black text-uppercase text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 900; font-size: 2rem;">
            {{ strtoupper($title) }}
        </h2>
        <p class="text-muted mb-0" style="font-size: 0.95rem; font-weight: 500;">
            Status modul menu {{ strtolower($title) }}.
        </p>
    </div>
    @endif

    <div class="d-flex justify-content-center align-items-center" style="min-height: 55vh;">
        <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white" style="max-width: 540px; width: 100%;">
            <div class="py-3">
                <div class="mb-4">
                    <div class="d-inline-flex align-items-center justify-content-center bg-light text-primary rounded-circle shadow-sm" style="width: 85px; height: 85px;">
                        <i class="bi bi-exclamation-circle-fill" style="font-size: 2.75rem; color: #1677ff;"></i>
                    </div>
                </div>
                <h3 class="fw-bold text-dark mb-2">Halaman ini belum ditambahkan</h3>
                <p class="text-muted mb-4" style="font-size: 0.95rem;">
                    Fitur {{ isset($title) ? strtolower($title) : 'ini' }} sedang dalam tahap pengembangan dan akan segera tersedia.
                </p>
                <a href="{{ route('home') }}" class="btn btn-primary rounded-3 px-4 py-2 fw-semibold shadow-sm">
                    <i class="bi bi-house-door-fill me-1"></i> Kembali ke Dashboard
                </a>
            </div>
        </div>
    </div>

</div>
@endsection
