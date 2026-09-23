@extends('layouts.app')

@section('title', 'Data Master Ruangan - WebJournal Management System')

@section('content')
<div class="container-fluid px-0">

    {{-- Page Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 800; font-size: 1.75rem;">Data Master Ruangan</h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">Kelola data ruangan sekolah beserta pengurus dan penugasan kelas.</p>
        </div>
        @if(in_array(auth()->user()->role ?? '', ['admin_tu', 'admin', 'super_admin']) || (auth()->user() && auth()->user()->isTestingUser()))
            <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto flex-shrink-0">
                <!-- Tombol Export Ruangan -->
                <div class="dropdown w-full sm:w-auto">
                    <button class="btn btn-outline-primary rounded-3 px-3 py-2 fw-semibold shadow-sm dropdown-toggle w-full sm:w-auto" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-download me-1"></i> Export
                    </button>
                    <ul class="dropdown-menu shadow-sm border-0 rounded-3" style="z-index: 1050;">
                        <li>
                            <a href="{{ route('ruangan.export', ['format' => 'xlsx']) }}" class="dropdown-item py-2">
                                <i class="bi bi-file-earmark-excel me-2 text-success"></i> Export Excel (.xlsx)
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('ruangan.export', ['format' => 'csv']) }}" class="dropdown-item py-2">
                                <i class="bi bi-filetype-csv me-2 text-info"></i> Export CSV (.csv)
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Tombol Tambah Ruangan (halaman dedicated) -->
                <a href="{{ route('ruangan.create') }}" class="btn btn-primary rounded-3 px-3 py-2 fw-semibold shadow-sm w-full sm:w-auto text-center">
                    <i class="bi bi-plus-lg me-1"></i> Tambah Ruangan
                </a>
            </div>
        @endif
    </div>

    {{-- Alert Messages --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('import_warnings') && count(session('import_warnings')) > 0)
        <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Peringatan Import:</strong>
            <ul class="mb-0 mt-1 ps-3">
                @foreach (session('import_warnings') as $warn)
                    <li>{{ $warn }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
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
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Search Bar --}}
    <div class="card border-0 shadow-sm rounded-4 p-3.5 bg-white mb-4">
        {{-- Filter bekerja live via AJAX (debounce pada search) --}}
        <form id="filterRuanganForm" action="{{ route('ruangan.index') }}" method="GET">
        <div class="position-relative" style="max-width: 450px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted" style="font-size: 0.9rem;"></i>
                <input type="text"
                       id="searchRuangan"
                       name="search"
                       value="{{ request('search') }}"
                       class="form-control bg-light rounded-3 ps-5"
                       placeholder="Cari kode, nama ruangan, lokasi, atau pengurus..."
                       autocomplete="off">
            </div>
        </form>
    </div>

    {{-- ====================================================== --}}
    {{-- HASIL FILTER (DI-UPDATE VIA AJAX)                       --}}
    {{-- ====================================================== --}}
    <div id="ruanganResultsWrapper" style="position: relative;">
        <div id="ruanganResults">
            @include('admin.ruangan._results')
        </div>

        {{-- Loading indicator (spinner tipis saat fetch berlangsung) --}}
        <div id="ruanganLoading" class="master-list-loading" style="display: none; position: absolute; inset: 0; z-index: 20; align-items: center; justify-content: center; background: rgba(255,255,255,0.65); border-radius: 16px;">
            <div class="d-flex align-items-center gap-2 px-3 py-2 bg-white rounded-3 shadow-sm">
                <div class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></div>
                <span class="small fw-semibold text-muted">Memuat data...</span>
            </div>
        </div>
    </div>
</div>

{{-- ==================== MODAL IMPORT RUANGAN ==================== --}}
@if(in_array(auth()->user()->role ?? '', ['admin_tu', 'admin', 'super_admin']) || (auth()->user() && auth()->user()->isTestingUser()))
<div class="modal fade" id="modalImportRuangan" tabindex="-1" aria-labelledby="modalImportRuanganLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-2 d-flex align-items-center justify-content-center bg-primary-subtle text-primary" style="width: 36px; height: 36px;">
                        <i class="bi bi-file-earmark-arrow-up-fill fs-5"></i>
                    </div>
                    <h5 class="modal-title fw-bold text-dark" id="modalImportRuanganLabel">Import Data Ruangan</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('ruangan.import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body p-4">
                    <div class="alert alert-info border-0 rounded-3 small mb-3">
                        <div class="d-flex align-items-start gap-2">
                            <i class="bi bi-info-circle-fill fs-6 mt-0.5"></i>
                            <div>
                                Anda dapat mengunggah file <strong>Template Ruangan</strong> ataupun mentahan file <strong>Data Jadwal.csv</strong> (kolom <em>Ruang</em>). Kode ruangan akan dibuatkan secara otomatis.
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-dark">Pilih File Excel / CSV <span class="text-danger">*</span></label>
                        <input type="file" name="file_ruangan" class="form-control rounded-3" accept=".xlsx,.xls,.csv" required>
                        <div class="form-text text-muted small">Format didukung: .xlsx, .xls, .csv (Maks. 10 MB)</div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded-3 border">
                        <div class="small text-muted">
                            <i class="bi bi-file-earmark-arrow-down text-primary me-1"></i> Format Template Master
                        </div>
                        <a href="{{ route('ruangan.export', ['format' => 'xlsx']) }}" class="btn btn-sm btn-outline-primary rounded-2 fw-semibold">
                            Unduh Contoh (.xlsx)
                        </a>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0 d-flex justify-content-between">
                    <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4 fw-semibold shadow-sm">
                        <i class="bi bi-upload me-1"></i> Unggah & Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@endsection

@push('scripts')
<script>
    // ── Live AJAX Filter Data Master Ruangan ───────────────────────────
    (function () {
        const wrapperEl = document.getElementById('ruanganResultsWrapper');
        const resultsEl = document.getElementById('ruanganResults');
        const loadingEl = document.getElementById('ruanganLoading');
        const form      = document.getElementById('filterRuanganForm');
        if (!wrapperEl || !resultsEl || !form) return;

        const searchInput = document.getElementById('searchRuangan');
        const BASE_URL    = '{{ route("ruangan.index") }}';
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
