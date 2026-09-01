@extends('layouts.app')

@section('title', 'Dashboard Guru Piket - WebJournal')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3 md:mb-4 gap-1 md:gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 800; font-size: 1.5rem;">
                Dashboard Guru Piket
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.85rem;">
                Selamat datang, {{ auth()->user()->nama ?? auth()->user()->username }}. Kelola presensi harian sekolah dari sini.
            </p>
        </div>
        <div class="text-muted small mt-1 mt-md-0">
            <i class="bi bi-calendar3 me-1"></i>
            {{ now()->translatedFormat('l, d F Y') }}
        </div>
    </div>

    <div class="grid grid-cols-2 gap-3 md:gap-4 mb-3 md:mb-4">
        <div>
            <a href="{{ route('piket.presensi-guru') }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm rounded-4 p-3 md:p-4 h-100" style="transition: transform 0.18s, box-shadow 0.18s;" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 12px 30px rgba(0,0,0,0.1)'" onmouseout="this.style.transform=''; this.style.boxShadow=''">
                    <div class="d-flex align-items-center gap-2 md:gap-3 mb-2 md:mb-3">
                        <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 42px; height: 42px; background: linear-gradient(135deg, #dbeafe, #bfdbfe);">
                            <i class="bi bi-person-check-fill text-primary fs-5 fs-md-4"></i>
                        </div>
                        <div class="overflow-hidden">
                            <div class="fw-bold text-dark text-xs md:text-base truncate">Presensi Guru</div>
                            <div class="text-muted text-xs truncate">Catat kehadiran guru</div>
                        </div>
                    </div>
                    <span class="text-primary fw-semibold text-xs md:text-sm">Buka Presensi Guru <i class="bi bi-arrow-right ms-1"></i></span>
                </div>
            </a>
        </div>
        <div>
            <a href="{{ route('piket.presensi-siswa') }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm rounded-4 p-3 md:p-4 h-100" style="transition: transform 0.18s, box-shadow 0.18s;" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 12px 30px rgba(0,0,0,0.1)'" onmouseout="this.style.transform=''; this.style.boxShadow=''">
                    <div class="d-flex align-items-center gap-2 md:gap-3 mb-2 md:mb-3">
                        <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 42px; height: 42px; background: linear-gradient(135deg, #dcfce7, #bbf7d0);">
                            <i class="bi bi-people-fill text-success fs-5 fs-md-4"></i>
                        </div>
                        <div class="overflow-hidden">
                            <div class="fw-bold text-dark text-xs md:text-base truncate">Presensi Siswa</div>
                            <div class="text-muted text-xs truncate">Catat kehadiran siswa</div>
                        </div>
                    </div>
                    <span class="text-success fw-semibold text-xs md:text-sm">Buka Presensi Siswa <i class="bi bi-arrow-right ms-1"></i></span>
                </div>
            </a>
        </div>
    </div>
</div>
@endsection
