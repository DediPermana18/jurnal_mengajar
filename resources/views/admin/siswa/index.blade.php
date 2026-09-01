@extends('layouts.app')

@section('title', 'Data Master Siswa - WebJournal Management System')

@push('styles')
<style>
    /* === Avatar Inisial Siswa === */
    .siswa-avatar {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.82rem;
        font-weight: 700;
        color: #ffffff;
        flex-shrink: 0;
    }

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

    /* === Status Badge === */
    .badge-aktif {
        background-color: #ecfdf5;
        color: #059669;
        border: 1px solid #a7f3d0;
        border-radius: 50px;
        padding: 0.25rem 0.75rem;
        font-size: 0.76rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
    }

    .badge-tidak-aktif {
        background-color: #fef2f2;
        color: #dc2626;
        border: 1px solid #fecaca;
        border-radius: 50px;
        padding: 0.25rem 0.75rem;
        font-size: 0.76rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
    }

    .badge-laki {
        background-color: #eff6ff;
        color: #2563eb;
        border: 1px solid #bfdbfe;
    }

    .badge-perempuan {
        background-color: #fdf2f8;
        color: #db2777;
        border: 1px solid #fbcfe8;
    }

    .badge-gender {
        display: inline-flex !important;
        align-items: center !important;
        gap: 0.375rem !important;
        border-radius: 9999px !important;
        padding: 0.25rem 0.75rem !important;
        font-size: 0.75rem !important;
        font-weight: 600 !important;
        white-space: nowrap !important;
        line-height: 1.2 !important;
    }

    /* === Badge Kelas === */
    .badge-kelas {
        background: #eff6ff;
        color: #1d4ed8;
        border: 1px solid #bfdbfe;
        border-radius: 8px;
        padding: 0.2rem 0.65rem;
        font-size: 0.78rem;
        font-weight: 700;
        white-space: nowrap;
    }

    /* === NISN Code === */
    .nisn-code {
        background: #f1f5f9;
        color: #475569;
        border-radius: 6px;
        padding: 0.15rem 0.55rem;
        font-size: 0.78rem;
        font-family: monospace;
        font-weight: 600;
        letter-spacing: 0.03em;
        display: inline-block;
    }

    /* === Aksi Icon Buttons === */
    .btn-aksi {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
        border: 1px solid #e2e8f0;
        background: #ffffff;
        color: #64748b;
        transition: all 0.18s ease;
        cursor: pointer;
        text-decoration: none;
    }

    .btn-aksi:hover {
        border-color: #cbd5e1;
        background: #f8fafc;
        color: #334155;
    }

    .btn-aksi.btn-aksi-danger:hover {
        border-color: #fecaca;
        background: #fef2f2;
        color: #dc2626;
    }

    .btn-aksi.btn-aksi-primary:hover {
        border-color: #bfdbfe;
        background: #eff6ff;
        color: #2563eb;
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
<div>
    {{-- ====================================================== --}}
    {{-- HEADER                                                  --}}
    {{-- ====================================================== --}}
    <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-3 mb-4">
        <div>
            <h2 class="mb-1" style="font-size: 1.65rem; font-weight: 800; color: #0f172a; letter-spacing: -0.02em;">
                Data Master Siswa
            </h2>
            <p class="mb-0" style="font-size: 0.9rem; color: #64748b; font-weight: 500;">
                Kelola data identitas siswa, NISN, dan rombel kelas.
            </p>
        </div>
        <a href="{{ route('siswa.create') }}"
           class="btn btn-primary rounded-3 px-3 py-2 fw-semibold d-flex align-items-center gap-2 flex-shrink-0"
           style="background-color: var(--primary-blue, #1677ff); border-color: var(--primary-blue, #1677ff); font-size: 0.9rem;">
            <i class="bi bi-plus-lg"></i>
            <span>Tambah Siswa</span>
        </a>
    </div>

    {{-- ====================================================== --}}
    {{-- FLASH MESSAGE                                           --}}
    {{-- ====================================================== --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 rounded-3 mb-4 d-flex align-items-center gap-2" role="alert" style="background:#ecfdf5; color:#065f46;">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- ====================================================== --}}
    {{-- FILTER BAR                                              --}}
    {{-- ====================================================== --}}
    <div class="filter-bar">
        <form action="{{ route('siswa.index') }}" method="GET">
            <div class="row g-3 align-items-center">
                {{-- Search Input --}}
                <div class="col-12 col-md-5">
                    <div class="search-wrapper">
                        <i class="bi bi-search"></i>
                        <input type="text"
                               name="search"
                               class="form-control"
                               placeholder="Cari nama siswa atau NISN..."
                               value="{{ request('search') }}">
                    </div>
                </div>

                {{-- Dropdown Pilih Kelas --}}
                <div class="col-6 col-md-2">
                    <select name="id_kelas" class="form-select" onchange="this.form.submit()">
                        <option value="">Pilih Kelas</option>
                        @foreach($dataKelas as $kelas)
                            <option value="{{ $kelas->id }}" {{ request('id_kelas') == $kelas->id ? 'selected' : '' }}>
                                {{ $kelas->tingkat }} &bull; {{ $kelas->nama_kelas }}{{ $kelas->jurusan ? ' (' . $kelas->jurusan->nama_jurusan . ')' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Dropdown Pilih Jurusan --}}
                <div class="col-6 col-md-2">
                    <select name="id_jurusan" class="form-select" onchange="this.form.submit()">
                        <option value="">Semua Jurusan</option>
                        @foreach($jurusans as $jurusan)
                            <option value="{{ $jurusan->id }}" {{ request('id_jurusan') == $jurusan->id ? 'selected' : '' }}>
                                {{ $jurusan->kode_jurusan }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Dropdown Jenis Kelamin --}}
                <div class="col-6 col-md-2">
                    <select name="jenis_kelamin" class="form-select" onchange="this.form.submit()">
                        <option value="">Jenis Kelamin</option>
                        <option value="L" {{ request('jenis_kelamin') == 'L' ? 'selected' : '' }}>Laki-laki</option>
                        <option value="P" {{ request('jenis_kelamin') == 'P' ? 'selected' : '' }}>Perempuan</option>
                    </select>
                </div>

                {{-- Tombol Filter & Reset --}}
                <div class="col-12 col-md-1 d-flex gap-2">
                    <button type="submit" class="btn btn-primary rounded-3 px-3 py-2 fw-semibold flex-grow-1"
                            style="background-color: var(--primary-blue, #1677ff); border-color: var(--primary-blue, #1677ff); font-size: 0.85rem;">
                        <i class="bi bi-funnel me-1"></i> Filter
                    </button>
                    @if(request()->hasAny(['search','id_kelas','id_jurusan','jenis_kelamin']))
                        <a href="{{ route('siswa.index') }}" class="btn btn-light border rounded-3 px-2 py-2" title="Reset Filter">
                            <i class="bi bi-x-lg text-muted"></i>
                        </a>
                    @endif
                </div>
            </div>
        </form>
    </div>

    {{-- ====================================================== --}}
    {{-- TABLE CARD                                              --}}
    {{-- ====================================================== --}}
    <div class="table-card-custom">

        {{-- Table --}}
        <div class="table-responsive w-full overflow-x-auto">
            <table class="table table-custom align-middle min-w-full">
                <thead>
                    <tr>
                        <th style="width: 30%;">NISN & NAMA SISWA</th>
                        <th class="whitespace-nowrap" style="width: 12%;">NIS</th>
                        <th style="width: 20%;">KELAS & JURUSAN</th>
                        <th class="whitespace-nowrap" style="width: 13%;">JENIS KELAMIN</th>
                        <th class="whitespace-nowrap" style="width: 12%;">STATUS</th>
                        <th class="whitespace-nowrap" style="width: 13%; text-align: right;">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dataSiswa as $idx => $siswa)
                        @php
                            // Generate inisial dari nama
                            $words   = explode(' ', $siswa->nama ?? '');
                            $inisial = strtoupper(substr($words[0] ?? 'S', 0, 1) . substr($words[1] ?? '', 0, 1));

                            // Warna avatar stabil berdasarkan ID siswa
                            $palette = ['#3b82f6','#8b5cf6','#ec4899','#f97316','#10b981','#06b6d4','#f59e0b','#6366f1'];
                            $bgColor = $palette[$siswa->id % count($palette)];

                            // Status siswa
                            $status = $siswa->status_siswa ?? 'Aktif';
                        @endphp
                        <tr>
                            {{-- Kolom 1: NISN & Nama --}}
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="siswa-avatar" style="background-color: {{ $bgColor }};">
                                        {{ $inisial }}
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark" style="font-size: 0.9rem; line-height: 1.3;">
                                            {{ $siswa->nama ?? '-' }}
                                        </div>
                                        <span class="text-muted small d-block mt-1">
                                            {{ $siswa->nisn ?? 'NISN belum diisi' }}
                                        </span>
                                    </div>
                                </div>
                            </td>

                            {{-- Kolom 2: NIS --}}
                            <td class="whitespace-nowrap">
                                @if($siswa->nis)
                                    <span class="nisn-code">{{ $siswa->nis }}</span>
                                @else
                                    <span class="text-muted" style="font-size: 0.82rem;">-</span>
                                @endif
                            </td>

                            {{-- Kolom 3: Kelas & Jurusan --}}
                            <td>
                                @if($siswa->kelas)
                                    <span class="badge-kelas">{{ $siswa->kelas->tingkat }} &bull; {{ $siswa->kelas->nama_kelas }}</span>
                                @else
                                    <span class="text-muted" style="font-size: 0.82rem;">Belum ditentukan</span>
                                @endif
                            </td>

                            {{-- Kolom 4: Jenis Kelamin --}}
                            <td>
                                @if($siswa->jenis_kelamin == 'L')
                                    <span class="badge-gender badge-laki inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium">
                                        <i class="bi bi-gender-male" style="font-size: 0.8rem;"></i>
                                        <span>Laki-laki</span>
                                    </span>
                                @elseif($siswa->jenis_kelamin == 'P')
                                    <span class="badge-gender badge-perempuan inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium">
                                        <i class="bi bi-gender-female" style="font-size: 0.8rem;"></i>
                                        <span>Perempuan</span>
                                    </span>
                                @else
                                    <span class="text-muted" style="font-size: 0.82rem;">-</span>
                                @endif
                            </td>

                            {{-- Kolom 5: Status Siswa --}}
                            <td>
                                @if(strtolower($status) == 'aktif')
                                    <span class="badge-aktif">
                                        <i class="bi bi-circle-fill" style="font-size: 0.42rem;"></i> Aktif
                                    </span>
                                @else
                                    <span class="badge-tidak-aktif">
                                        <i class="bi bi-circle-fill" style="font-size: 0.42rem;"></i> {{ $status }}
                                    </span>
                                @endif
                            </td>

                            {{-- Kolom 6: Aksi --}}
                            <td class="whitespace-nowrap">
                                <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                                    {{-- Edit --}}
                                    <a href="{{ route('siswa.edit', $siswa->id) }}"
                                       class="btn-aksi"
                                       title="Edit Data">
                                        <i class="bi bi-pencil"></i>
                                    </a>

                                    {{-- Hapus --}}
                                    <form action="{{ route('siswa.destroy', $siswa->id) }}" method="POST" class="d-inline-flex"
                                          onsubmit="return confirm('Yakin ingin menghapus data siswa {{ addslashes($siswa->nama) }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-aksi btn-aksi-danger" title="Hapus">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <div style="color: #cbd5e1;">
                                    <i class="bi bi-people" style="font-size: 2.5rem; display: block; margin-bottom: 0.75rem;"></i>
                                </div>
                                <div class="fw-semibold text-dark mb-1">Tidak ada data siswa</div>
                                <div class="text-muted" style="font-size: 0.85rem;">
                                    @if(request()->hasAny(['search','id_kelas','id_jurusan','jenis_kelamin']))
                                        Tidak ada siswa yang sesuai dengan filter. <a href="{{ route('siswa.index') }}">Reset filter</a>
                                    @else
                                        Belum ada siswa yang terdaftar. <a href="{{ route('siswa.create') }}">Tambah siswa baru</a>.
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

{{-- ====================================================== --}}
{{-- FOOTER: Info & Pagination                               --}}
{{-- ====================================================== --}}
@if($dataSiswa->total() > 0)
    <div class="custom-pagination-wrapper">
        {{-- Info jumlah di sebelah KIRI --}}
        <div class="pagination-info-text">
            Menampilkan
            <strong>{{ $dataSiswa->firstItem() }} - {{ $dataSiswa->lastItem() }}</strong>
            dari
            <strong>{{ number_format($dataSiswa->total()) }}</strong>
            siswa
            @if(request()->hasAny(['search','id_kelas','id_jurusan','jenis_kelamin']))
                <span class="text-muted ms-1">(difilter dari total <strong>{{ number_format($totalSiswa) }}</strong> siswa)</span>
            @endif
        </div>

        {{-- Tombol Navigasi Pagination di sebelah KANAN --}}
        <div class="pagination-controls">
            {{-- Tombol Prev --}}
            @if ($dataSiswa->onFirstPage())
                <span class="pagination-btn disabled" aria-disabled="true">
                    <svg class="pagination-svg-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                    <span>Prev</span>
                </span>
            @else
                <a href="{{ $dataSiswa->appends(request()->query())->previousPageUrl() }}" class="pagination-btn">
                    <svg class="pagination-svg-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                    <span>Prev</span>
                </a>
            @endif

            {{-- Counter Halaman (Badge) --}}
            <span class="pagination-badge">
                {{ $dataSiswa->currentPage() }} / {{ $dataSiswa->lastPage() }}
            </span>

            {{-- Tombol Next --}}
            @if ($dataSiswa->hasMorePages())
                <a href="{{ $dataSiswa->appends(request()->query())->nextPageUrl() }}" class="pagination-btn">
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

</div>
@endsection

@push('scripts')
<script>
    // Auto submit filter saat dropdown berubah (opsional UX improvement)
    document.querySelectorAll('.filter-bar select[name="id_kelas"], .filter-bar select[name="id_jurusan"], .filter-bar select[name="jenis_kelamin"]').forEach(function(el) {
        el.addEventListener('change', function() {
            this.closest('form').submit();
        });
    });
</script>
@endpush
