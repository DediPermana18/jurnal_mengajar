@extends('layouts.app')

@section('title', 'Data Master Jurusan - WebJournal Management System')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 800; font-size: 1.75rem;">Data Master Jurusan</h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">Kelola kode dan nama jurusan yang digunakan pada data kelas.</p>
        </div>
        @if(in_array(auth()->user()->role ?? '', ['admin_tu', 'admin', 'super_admin']) || (auth()->user() && auth()->user()->isTestingUser()))
            <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto flex-shrink-0">
                <!-- Tombol Export Jurusan -->
                <div class="dropdown w-full sm:w-auto">
                    <button class="btn btn-outline-primary rounded-3 px-3 py-2 fw-semibold shadow-sm dropdown-toggle w-full sm:w-auto" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-download me-1"></i> Export
                    </button>
                    <ul class="dropdown-menu shadow-sm border-0 rounded-3" style="z-index: 1050;">
                        <li>
                            <a href="{{ route('jurusan.export', ['format' => 'xlsx']) }}" class="dropdown-item py-2">
                                <i class="bi bi-file-earmark-excel me-2 text-success"></i> Export Excel (.xlsx)
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('jurusan.export', ['format' => 'csv']) }}" class="dropdown-item py-2">
                                <i class="bi bi-filetype-csv me-2 text-info"></i> Export CSV (.csv)
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Tombol Tambah Jurusan -->
                <a href="{{ route('jurusan.create') }}" class="btn btn-primary rounded-3 px-3 py-2 fw-semibold shadow-sm w-full sm:w-auto text-center">
                    <i class="bi bi-plus-lg me-1"></i> Tambah Jurusan
                </a>
            </div>
        @endif
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
            <strong class="d-block mb-1">Terjadi kesalahan:</strong>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card border-0 shadow-sm rounded-4 p-3.5 bg-white mb-4">
        {{-- Filter bekerja live via AJAX (debounce pada search) --}}
        <form id="filterJurusanForm" action="{{ route('jurusan.index') }}" method="GET">
        <div class="position-relative" style="max-width: 450px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted" style="font-size: 0.9rem;"></i>
                <input type="text"
                       id="searchJurusan"
                       name="search"
                       value="{{ request('search') }}"
                       class="form-control bg-light rounded-3 ps-5"
                       placeholder="Cari kode atau nama jurusan..."
                       autocomplete="off">
            </div>
        </form>
    </div>

    {{-- ====================================================== --}}
    {{-- HASIL FILTER (DI-UPDATE VIA AJAX)                       --}}
    {{-- ====================================================== --}}
    <div id="jurusanResultsWrapper" style="position: relative;">
        <div id="jurusanResults">
            @include('admin.jurusan._results')
        </div>

        {{-- Loading indicator (spinner tipis saat fetch berlangsung) --}}
        <div id="jurusanLoading" class="master-list-loading" style="display: none; position: absolute; inset: 0; z-index: 20; align-items: center; justify-content: center; background: rgba(255,255,255,0.65); border-radius: 16px;">
            <div class="d-flex align-items-center gap-2 px-3 py-2 bg-white rounded-3 shadow-sm">
                <div class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></div>
                <span class="small fw-semibold text-muted">Memuat data...</span>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // ── Live AJAX Filter Data Master Jurusan ───────────────────────────
    (function () {
        const wrapperEl = document.getElementById('jurusanResultsWrapper');
        const resultsEl = document.getElementById('jurusanResults');
        const loadingEl = document.getElementById('jurusanLoading');
        const form      = document.getElementById('filterJurusanForm');
        if (!wrapperEl || !resultsEl || !form) return;

        const searchInput = document.getElementById('searchJurusan');
        const BASE_URL    = '{{ route("jurusan.index") }}';
        let requestSeq    = 0;

        function debounce(fn, ms) {
            let timer;
            return function (...args) {
                clearTimeout(timer);
                timer = setTimeout(() => fn.apply(this, args), ms);
            };
        }

        function refresh() {
            const seq = ++requestSeq;
            loadingEl.style.display = 'flex';

            const params = new URLSearchParams();
            const search = searchInput ? searchInput.value.trim() : '';
            if (search) params.set('search', search);
            const qs = params.toString();
            const targetUrl = BASE_URL + (qs ? '?' + qs : '');

            window.history.replaceState(null, '', targetUrl);

            fetch(targetUrl, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            })
            .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
            .then(data => {
                if (seq !== requestSeq) return; // respon basi diabaikan
                resultsEl.innerHTML = data.html;
            })
            .catch(() => {
                if (seq !== requestSeq) return;
                window.location.href = targetUrl;
            })
            .finally(() => {
                if (seq === requestSeq) loadingEl.style.display = 'none';
            });
        }

        // 1) Search input → debounce ±300ms
        if (searchInput) {
            searchInput.addEventListener('input', debounce(refresh, 300));
        }

        // 2) Cegah submit GET biasa (Enter di input search)
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            refresh();
        });
    })();
</script>
@endpush
@endsection