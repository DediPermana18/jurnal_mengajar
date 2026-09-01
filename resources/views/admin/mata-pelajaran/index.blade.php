@extends('layouts.app')

@section('title', 'Data Mata Pelajaran - WebJournal Management System')

@push('styles')
<style>
    /* Styling khusus UI Tabel & Badge Mapel */
    .badge-kode-mapel {
        font-family: 'Courier New', monospace;
        font-weight: 700;
        font-size: 0.82rem;
        letter-spacing: 0.05em;
        background-color: #f1f5f9;
        color: #0f172a;
        padding: 0.35rem 0.65rem;
        border-radius: 0.375rem;
        border: 1px solid #cbd5e1;
        display: inline-block;
    }

    .btn-aksi {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 0.5rem;
        border: 1px solid #e2e8f0;
        background: #ffffff;
        color: #64748b;
        transition: all 0.15s ease;
        text-decoration: none;
    }

    .btn-aksi:hover {
        background: #f8fafc;
        color: #1677ff;
        border-color: #bfdbfe;
    }

    .btn-aksi.btn-aksi-danger:hover {
        background: #fef2f2;
        color: #dc2626;
        border-color: #fca5a5;
    }

    /* Custom Modern Pagination */
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
        padding: 0.38rem 0.75rem;
        font-size: 0.8125rem;
        font-weight: 700;
        color: #1677ff;
        background-color: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 8px;
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

    {{-- Page Header --}}
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="font-weight: 900; font-size: 1.75rem; letter-spacing: -0.02em;">
                Data Mata Pelajaran
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                Kelola daftar mata pelajaran dan kode mapel sekolah.
            </p>
        </div>
        <div>
            <a href="{{ route('mapel.create') }}"
               class="btn btn-primary rounded-3 fw-semibold px-3 py-2 d-flex align-items-center gap-2"
               style="font-size: 0.875rem;">
                <i class="bi bi-plus-lg"></i>
                <span>Tambah Mapel</span>
            </a>
        </div>
    </div>

    {{-- Alert Messages --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4" role="alert" style="font-size: 0.9rem;">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4" role="alert" style="font-size: 0.9rem;">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
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

    {{-- Main Filter & Table Card --}}
    <div class="card border-0 rounded-4 shadow-sm bg-white overflow-hidden">
        {{-- Filter Bar --}}
        <div class="card-header bg-white border-0 pt-4 pb-3 px-4">
            <form method="GET" action="{{ route('mapel.index') }}" id="filterForm">
                <div class="row g-3 align-items-center">
                    {{-- Input Search --}}
                    <div class="col-12 col-md-6 col-lg-7">
                        <div class="position-relative">
                            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted" style="font-size: 0.9rem;"></i>
                            <input type="text" name="search" class="form-control rounded-3 ps-5"
                                   placeholder="Cari Nama Mapel atau Kode Mapel..."
                                   value="{{ request('search') }}" style="font-size: 0.875rem;">
                        </div>
                    </div>

                    {{-- Filter Jenis Mapel --}}
                    <div class="col-12 col-md-4 col-lg-3">
                        <select name="kelompok" onchange="this.form.submit()" class="form-select rounded-3" style="font-size: 0.875rem;">
                            <option value="">-- Semua Jenis Mapel --</option>
                            @foreach($jenisOptions as $opt)
                                <option value="{{ $opt }}" {{ request('kelompok') === $opt ? 'selected' : '' }}>
                                    {{ $opt }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Tombol Filter & Reset --}}
                    <div class="col-12 col-md-2 col-lg-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary rounded-3 flex-fill fw-semibold d-flex align-items-center justify-content-center gap-1" style="font-size: 0.85rem;">
                            <i class="bi bi-funnel-fill"></i> Filter
                        </button>
                        @if(request()->hasAny(['search', 'kelompok']))
                            <a href="{{ route('mapel.index') }}" class="btn btn-light border rounded-3 text-muted d-flex align-items-center justify-content-center px-2" title="Reset Filter" style="font-size: 0.85rem;">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>
                        @endif
                    </div>
                </div>
            </form>
        </div>

        {{-- Table Content --}}
        <div class="card-body p-0">
            <div class="table-responsive w-full overflow-x-auto">
                <table class="table table-hover align-middle mb-0 min-w-full" style="font-size: 0.9rem;">
                    <thead style="background: #f8fafc;">
                        <tr>
                            <th class="ps-4 py-3 whitespace-nowrap" style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #64748b; width: 60px;">No</th>
                            <th class="py-3 whitespace-nowrap" style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #64748b; width: 140px;">Kode Mapel</th>
                            <th class="py-3" style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #64748b;">Nama Mata Pelajaran</th>
                            <th class="py-3 whitespace-nowrap" style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #64748b; width: 160px;">Kelompok</th>
                            <th class="py-3 pe-4 text-end whitespace-nowrap" style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #64748b; width: 120px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($dataMapel as $mapel)
                            <tr>
                                {{-- 1. No --}}
                                <td class="ps-4 fw-semibold text-muted whitespace-nowrap" style="font-size: 0.85rem;">
                                    {{ $loop->iteration + $dataMapel->firstItem() - 1 }}
                                </td>

                                {{-- 2. Kode Mapel --}}
                                <td class="whitespace-nowrap">
                                    <span class="badge-kode-mapel">
                                        {{ $mapel->kode_mapel ?? '-' }}
                                    </span>
                                </td>

                                {{-- 3. Nama Mata Pelajaran --}}
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-2 d-flex align-items-center justify-content-center bg-primary-subtle text-primary fw-bold" style="width: 32px; height: 32px; font-size: 0.85rem;">
                                            <i class="bi bi-journal-bookmark-fill"></i>
                                        </div>
                                        <span class="fw-bold text-dark" style="font-size: 0.92rem;">
                                            {{ $mapel->nama_mapel }}
                                        </span>
                                    </div>
                                </td>

                                {{-- 4. Kelompok --}}
                                <td>
                                    @php
                                        $badgeStyle = match($mapel->kelompok) {
                                            'Muatan Umum' => 'background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd;',
                                            'Kejuruan'    => 'background-color: #dcfce7; color: #15803d; border: 1px solid #bbf7d0;',
                                            'Muatan Lokal'=> 'background-color: #fef9c3; color: #a16207; border: 1px solid #fef08a;',
                                            default       => 'background-color: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;'
                                        };
                                    @endphp
                                    <span class="badge px-2 py-1 rounded-2 fw-semibold" style="font-size: 0.78rem; {{ $badgeStyle }}">
                                        {{ $mapel->kelompok ?? '-' }}
                                    </span>
                                </td>

                                {{-- 5. Aksi --}}
                                <td class="pe-4 text-end whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                                        {{-- Dedicated Edit Page Button --}}
                                        <a href="{{ route('mapel.edit', $mapel->id) }}" class="btn-aksi" title="Edit Mapel">
                                            <i class="bi bi-pencil"></i>
                                        </a>

                                        {{-- Delete Form --}}
                                        <form action="{{ route('mapel.destroy', $mapel->id) }}" method="POST" class="d-inline-flex"
                                              onsubmit="return confirm('Yakin ingin menghapus mata pelajaran {{ addslashes($mapel->nama_mapel) }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-aksi btn-aksi-danger" title="Hapus Mapel">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <div style="color: #cbd5e1;">
                                        <i class="bi bi-journal-x" style="font-size: 2.75rem; display: block; margin-bottom: 0.75rem;"></i>
                                    </div>
                                    <div class="fw-semibold text-dark mb-1" style="font-size: 1rem;">Tidak ada data mata pelajaran</div>
                                    <div class="text-muted" style="font-size: 0.85rem;">
                                        @if(request()->hasAny(['search', 'kelompok']))
                                            Tidak ada mapel yang sesuai dengan filter pencarian. <a href="{{ route('mapel.index') }}">Reset filter</a>
                                        @else
                                            Belum ada mata pelajaran yang terdaftar. Klik tombol <strong>+ Tambah Mapel</strong> di atas.
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Footer & Modern Pagination --}}
        @if($dataMapel->total() > 0)
            <div class="px-4 pb-4">
                <div class="custom-pagination-wrapper">
                    {{-- Info Teks Sebelah Kanan --}}
                    <div class="pagination-info-text">
                        Menampilkan
                        <strong>{{ $dataMapel->firstItem() }} - {{ $dataMapel->lastItem() }}</strong>
                        dari
                        <strong>{{ number_format($dataMapel->total()) }}</strong>
                        Mapel
                        @if(request()->hasAny(['search', 'kelompok']))
                            <span class="text-muted ms-1">(difilter dari total <strong>{{ number_format($totalMapel) }}</strong> Mapel)</span>
                        @endif
                    </div>

                    {{-- Tombol Prev & Next --}}
                    <div class="pagination-controls">
                        @if ($dataMapel->onFirstPage())
                            <span class="pagination-btn disabled" aria-disabled="true">
                                <svg class="pagination-svg-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="15 18 9 12 15 6"></polyline>
                                </svg>
                                <span>Prev</span>
                            </span>
                        @else
                            <a href="{{ $dataMapel->appends(request()->query())->previousPageUrl() }}" class="pagination-btn">
                                <svg class="pagination-svg-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="15 18 9 12 15 6"></polyline>
                                </svg>
                                <span>Prev</span>
                            </a>
                        @endif

                        <span class="pagination-badge">
                            {{ $dataMapel->currentPage() }} / {{ $dataMapel->lastPage() }}
                        </span>

                        @if ($dataMapel->hasMorePages())
                            <a href="{{ $dataMapel->appends(request()->query())->nextPageUrl() }}" class="pagination-btn">
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
            </div>
        @endif
    </div>

</div>
@endsection
