@extends('layouts.app')

@section('title', 'Data Master Guru - WebJournal Management System')

@push('styles')
<style>
    /* === Filter Bar === */
    .filter-bar {
        background: #ffffff;
        border: 1px solid #e8eef5;
        border-radius: 14px;
        padding: 1.1rem 1.5rem;
        margin-bottom: 1.25rem;
        box-shadow: 0 1px 6px rgba(15, 23, 42, 0.04);
    }

    .filter-bar .form-control,
    .filter-bar .form-select {
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        font-size: 0.875rem;
        padding: 0.55rem 0.85rem;
        color: #334155;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .filter-bar .form-control:focus,
    .filter-bar .form-select:focus {
        border-color: var(--primary-blue, #1677ff);
        box-shadow: 0 0 0 3px rgba(22, 119, 255, 0.12);
        background-color: #ffffff;
    }

    .filter-bar .search-wrapper {
        position: relative;
    }

    .filter-bar .search-wrapper i {
        position: absolute;
        left: 0.85rem;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 0.9rem;
        pointer-events: none;
    }

    .filter-bar .search-wrapper .form-control {
        padding-left: 2.4rem;
    }

    /* === Custom Modern Pagination === */
    .custom-pagination-wrapper {
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        align-items: center;
        gap: 0.85rem;
        margin-top: 1.25rem;
        padding-top: 1rem;
        border-top: 1px solid #f1f5f9;
    }

    @media (min-width: 640px) {
        .custom-pagination-wrapper {
            flex-direction: row;
        }
    }

    .pagination-info-text {
        font-size: 0.875rem;
        font-weight: 500;
        color: #64748b;
    }

    .pagination-info-text strong {
        color: #0f172a;
        font-weight: 700;
    }

    .pagination-controls {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .pagination-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.4rem 0.8rem;
        font-size: 0.8125rem;
        font-weight: 600;
        color: #475569;
        background-color: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
        text-decoration: none;
        transition: all 0.15s ease;
        line-height: 1.25;
        user-select: none;
    }

    .pagination-btn:hover {
        color: #1677ff;
        background-color: #f8fafc;
        border-color: #bfdbfe;
        box-shadow: 0 2px 5px rgba(22, 119, 255, 0.1);
    }

    .pagination-btn.disabled,
    .pagination-btn:disabled {
        color: #cbd5e1;
        background-color: #f8fafc;
        border-color: #e2e8f0;
        cursor: not-allowed;
        box-shadow: none;
        pointer-events: none;
    }

    .pagination-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.35rem 0.7rem;
        font-size: 0.775rem;
        font-weight: 700;
        color: #475569;
        background-color: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        letter-spacing: 0.02em;
    }

    .pagination-svg-icon {
        width: 14px;
        height: 14px;
        flex-shrink: 0;
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 800; font-size: 1.75rem;">Data Master Guru</h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">Kelola data guru pengajar dan wali kelas.</p>
        </div>
        @if(in_array(auth()->user()->role ?? '', ['admin_tu', 'admin', 'super_admin']))
            <a href="{{ route('admin.guru.create') }}" class="btn btn-primary rounded-3 px-3 py-2 fw-semibold shadow-sm"><i class="bi bi-plus-lg me-1"></i> Tambah Guru</a>
        @endif
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert"><i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert"><strong class="d-block mb-1">Gagal memproses data:</strong><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
    @endif

    {{-- ====================================================== --}}
    {{-- FILTER BAR                                              --}}
    {{-- ====================================================== --}}
    <div class="filter-bar">
        <form action="{{ route('guru.index') }}" method="GET">
            <div class="row g-3 align-items-center">
                {{-- Input Cari Nama/NIP dengan Icon Kaca Pembesar --}}
                <div class="col-12 col-md-4">
                    <div class="search-wrapper">
                        <i class="bi bi-search"></i>
                        <input type="text"
                               name="search"
                               class="form-control"
                               placeholder="Cari nama atau NIP..."
                               value="{{ request('search') }}">
                    </div>
                </div>

                {{-- Dropdown Status --}}
                <div class="col-6 col-md-3">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="Semua Status" {{ request('status') === 'Semua Status' || !request()->filled('status') ? 'selected' : '' }}>Semua Status</option>
                        <option value="Aktif" {{ request('status') === 'Aktif' ? 'selected' : '' }}>Aktif</option>
                        <option value="Tidak Aktif" {{ request('status') === 'Tidak Aktif' ? 'selected' : '' }}>Tidak Aktif</option>
                    </select>
                </div>

                {{-- Dropdown Penugasan Wali Kelas & Option Kelas --}}
                <div class="col-6 col-md-3">
                    <select name="wali_kelas" class="form-select" onchange="this.form.submit()">
                        <option value="Semua" {{ request('wali_kelas') === 'Semua' || !request()->filled('wali_kelas') ? 'selected' : '' }}>Semua Penugasan</option>
                        <option value="Ya" {{ request('wali_kelas') === 'Ya' ? 'selected' : '' }}>Wali Kelas</option>
                        <option value="Tidak" {{ request('wali_kelas') === 'Tidak' ? 'selected' : '' }}>Bukan Wali Kelas</option>
                        @if(isset($daftarKelas) && $daftarKelas->count() > 0)
                            <optgroup label="Filter Per Kelas Wali">
                                @foreach($daftarKelas as $kelas)
                                    <option value="kelas_{{ $kelas->id }}" {{ request('wali_kelas') === 'kelas_' . $kelas->id ? 'selected' : '' }}>
                                        {{ $kelas->tingkat }} &bull; {{ $kelas->nama_kelas }}{{ $kelas->jurusan ? ' (' . $kelas->jurusan->nama_jurusan . ')' : '' }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endif
                    </select>
                </div>

                {{-- Tombol Filter & Reset --}}
                <div class="col-12 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary rounded-3 px-3 py-2 fw-semibold flex-grow-1 d-inline-flex align-items-center justify-content-center gap-1"
                            style="background-color: var(--primary-blue, #1677ff); border-color: var(--primary-blue, #1677ff); font-size: 0.875rem;">
                        <i class="bi bi-funnel"></i>
                        <span>Filter</span>
                    </button>
                    @if(request()->hasAny(['search','status','wali_kelas']))
                        <a href="{{ route('guru.index') }}" class="btn btn-light border rounded-3 px-3 py-2 d-inline-flex align-items-center justify-content-center" title="Reset Filter">
                            <i class="bi bi-x-lg text-muted"></i>
                        </a>
                    @endif
                </div>
            </div>
        </form>
    </div>

    <div class="table-card-custom mb-4">
        <div class="table-responsive w-full overflow-x-auto">
            <table class="table table-custom align-middle min-w-full">
                <thead><tr><th class="whitespace-nowrap" style="width: 28%;">GURU</th><th style="width: 28%;">MATA PELAJARAN</th><th style="width: 18%;">WALI KELAS</th><th class="whitespace-nowrap" style="width: 10%;">STATUS</th><th class="text-end whitespace-nowrap" style="width: 16%;">AKSI</th></tr></thead>
                <tbody>
                    @forelse($dataGuru as $guru)
                        @php
                            $words = explode(' ', trim($guru->nama));
                            $initials = strtoupper(substr($words[0], 0, 1));
                            $initials .= count($words) > 1 ? strtoupper(substr(end($words), 0, 1)) : strtoupper(substr($words[0], 1, 1));
                            $mapelNames = collect();
                            if ($guru->jadwalPelajaran && $guru->jadwalPelajaran->isNotEmpty()) {
                                $mapelNames = $mapelNames->concat($guru->jadwalPelajaran->map(fn($jp) => $jp->mataPelajaran?->nama_mapel)->filter());
                            }
                            $namaKelasWali = $guru->kelasWali?->pluck('nama_kelas')->join(', ') ?: $guru->kelas?->nama_kelas;
                        @endphp
                        <tr>
                            {{-- 1. Guru Info (Nama & NIP) --}}
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="rounded-circle bg-secondary-subtle text-secondary fw-bold d-flex align-items-center justify-content-center shrink-0" style="width: 44px; height: 44px;">
                                        {{ $initials }}
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark">{{ $guru->nama }}</div>
                                        <div class="text-muted small">NIP: {{ $guru->nip ?: '-' }}</div>
                                    </div>
                                </div>
                            </td>

                            {{-- 2. Mata Pelajaran Pengampu --}}
                            <td>
                                @if($mapelNames->unique()->isNotEmpty())
                                    @foreach($mapelNames->unique() as $namaMapel)
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 rounded-2 me-1 mb-1" style="font-size: 0.78rem; font-weight: 600;">
                                            <i class="bi bi-journal-bookmark me-1"></i>{{ $namaMapel }}
                                        </span>
                                    @endforeach
                                @else
                                    <span class="badge bg-light text-muted border px-2 py-1 rounded-pill" style="font-size: 0.78rem;">
                                        <i class="bi bi-dash-circle me-1"></i>Belum set
                                    </span>
                                @endif
                            </td>
                            <td>{{ $namaKelasWali ? 'Wali Kelas ' . $namaKelasWali : '-' }}</td>
                            <td class="whitespace-nowrap"><span class="badge {{ $guru->is_active ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning-emphasis' }} rounded-pill px-3 py-2">{{ $guru->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                            <td class="text-end whitespace-nowrap">
                                @if(in_array(auth()->user()->role ?? '', ['admin_tu', 'admin', 'super_admin']))
                                    <div class="flex items-center justify-center gap-2 whitespace-nowrap">
                                    <a href="{{ route('admin.guru.edit', $guru->id) }}" class="btn btn-sm btn-outline-warning rounded-3" title="Edit guru"><i class="bi bi-pencil-square"></i></a>
                                    <form action="{{ route('guru.reset-password', $guru->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Reset password guru ini ke password default?')">@csrf<button type="submit" class="btn btn-sm btn-outline-info rounded-3" title="Reset password"><i class="bi bi-key"></i></button></form>
                                    @if(!$guru->is_active)
                                        <form action="{{ route('guru.approve', $guru->id) }}" method="POST" class="d-inline">@csrf<button type="submit" class="btn btn-sm btn-outline-success rounded-3" title="Aktifkan guru"><i class="bi bi-check-circle"></i></button></form>
                                    @else
                                        <form action="{{ route('guru.toggle-status', $guru->id) }}" method="POST" class="d-inline">@csrf<button type="submit" class="btn btn-sm btn-outline-secondary rounded-3" title="Nonaktifkan guru"><i class="bi bi-slash-circle"></i></button></form>
                                    @endif
                                    <form action="{{ route('guru.destroy', $guru->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus data guru ini?')">@csrf @method('DELETE')<button type="submit" class="btn btn-sm btn-outline-danger rounded-3" title="Hapus guru"><i class="bi bi-trash"></i></button></form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-person-badge fs-1 d-block mb-2"></i>Tidak ada data guru yang sesuai.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ====================================================== --}}
{{-- FOOTER: Info & Pagination                               --}}
{{-- ====================================================== --}}
@if($dataGuru->total() > 0)
    <div class="custom-pagination-wrapper">
        {{-- Info jumlah di sebelah KIRI --}}
        <div class="pagination-info-text">
            Menampilkan
            <strong>{{ $dataGuru->firstItem() }} - {{ $dataGuru->lastItem() }}</strong>
            dari
            <strong>{{ number_format($dataGuru->total()) }}</strong>
            Guru
            @if(request()->filled('search') || request()->filled('status') || request()->filled('wali_kelas'))
                <span class="text-muted ms-1">(difilter)</span>
            @endif
        </div>

        {{-- Tombol Navigasi Pagination di sebelah KANAN --}}
        <div class="pagination-controls">
            {{-- Tombol Prev --}}
            @if ($dataGuru->onFirstPage())
                <span class="pagination-btn disabled" aria-disabled="true">
                    <svg class="pagination-svg-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                    <span>Prev</span>
                </span>
            @else
                <a href="{{ $dataGuru->appends(request()->query())->previousPageUrl() }}" class="pagination-btn">
                    <svg class="pagination-svg-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                    <span>Prev</span>
                </a>
            @endif

            {{-- Counter Halaman (Badge) --}}
            <span class="pagination-badge">
                {{ $dataGuru->currentPage() }} / {{ $dataGuru->lastPage() }}
            </span>

            {{-- Tombol Next --}}
            @if ($dataGuru->hasMorePages())
                <a href="{{ $dataGuru->appends(request()->query())->nextPageUrl() }}" class="pagination-btn">
                    <span>Next</span>
                    <svg class="pagination-svg-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </a>
            @else
                <span class="pagination-btn disabled" aria-disabled="true">
                    <span>Next</span>
                    <svg class="pagination-svg-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </span>
            @endif
        </div>
    </div>
@endif

@endsection
