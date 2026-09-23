@extends('layouts.app')

@push('styles')
<style>
    .table-responsive {
        min-height: 280px;
        padding-bottom: 2rem;
    }
    .dropdown-menu {
        z-index: 1050 !important;
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">

    <!-- HEADER HALAMAN -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 800; font-size: 1.75rem;">
                Data Master Kelas
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                Kelola rombongan belajar, tingkat, jurusan, dan penugasan wali kelas.
            </p>
        </div>

        <!-- Tombol Tambah Kelas (Role Admin_TU, Admin, & Super Admin) -->
        @if(in_array(auth()->user()->role ?? '', ['admin_tu', 'admin', 'super_admin']) || (auth()->user() && auth()->user()->isTestingUser()))
            <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto flex-shrink-0">
                <!-- Tombol Export Kelas -->
                <div class="dropdown w-full sm:w-auto">
                    <button class="btn btn-outline-primary rounded-3 px-3 py-2 fw-semibold shadow-sm dropdown-toggle w-full sm:w-auto" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-download me-1"></i> Export
                    </button>
                    <ul class="dropdown-menu shadow-sm border-0 rounded-3" style="z-index: 1050;">
                        <li>
                            <a href="{{ route('kelas.export', ['format' => 'xlsx']) }}" class="dropdown-item py-2">
                                <i class="bi bi-file-earmark-excel me-2 text-success"></i> Export Excel (.xlsx)
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('kelas.export', ['format' => 'csv']) }}" class="dropdown-item py-2">
                                <i class="bi bi-filetype-csv me-2 text-info"></i> Export CSV (.csv)
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Tombol Tambah Kelas -->
                <button type="button" class="btn btn-primary rounded-3 px-3 py-2 fw-semibold shadow-sm w-full sm:w-auto text-center" data-bs-toggle="modal" data-bs-target="#modalTambahKelas">
                    <i class="bi bi-plus-lg me-1"></i> Tambah Kelas
                </button>
            </div>
        @endif
    </div>

    <!-- ALERT NOTIFIKASI SUCCESS / ERROR -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                <div>{{ session('success') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <div>
                    <strong class="d-block mb-1">Terjadi kesalahan:</strong>
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- FILTER BAR (CARD PUTIH) -->
    <div class="card border-0 shadow-sm rounded-4 p-3.5 bg-white mb-4">
        {{-- Filter bekerja live via AJAX (debounce pada search, fetch partial hasil) --}}
        <form id="filterKelasForm" action="{{ route('kelas.index') }}" method="GET" class="d-flex flex-wrap align-items-center gap-3">

            <!-- Cari Kelas -->
            <div class="flex-grow-1 position-relative w-full sm:w-auto" style="min-width: 240px; max-width: 450px;">
                <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted" style="font-size: 0.9rem;"></i>
                <input type="text"
                       id="searchKelas"
                       name="search"
                       value="{{ request('search') }}"
                       class="form-control bg-light rounded-lg ps-5"
                       placeholder="Cari nama kelas atau wali kelas..."
                       autocomplete="off">
            </div>

            <!-- Dropdown Filter Tingkat (full-width di mobile, 180px di ≥sm) -->
            <div class="w-full sm:w-[180px]">
                <select name="tingkat" id="tingkatKelas" class="form-select bg-light rounded-lg w-full">
                    <option value="Semua Tingkat" {{ request('tingkat') == 'Semua Tingkat' ? 'selected' : '' }}>Semua Tingkat</option>
                    <option value="X" {{ request('tingkat') == 'X' ? 'selected' : '' }}>Kelas X</option>
                    <option value="XI" {{ request('tingkat') == 'XI' ? 'selected' : '' }}>Kelas XI</option>
                    <option value="XII" {{ request('tingkat') == 'XII' ? 'selected' : '' }}>Kelas XII</option>
                </select>
            </div>

            <!-- Dropdown Filter Jurusan (full-width di mobile, 220px di ≥sm) -->
            <div class="w-full sm:w-[220px]">
                <select name="jurusan" id="jurusanKelas" class="form-select bg-light rounded-lg w-full">
                    <option value="Semua Jurusan" {{ request('jurusan') == 'Semua Jurusan' ? 'selected' : '' }}>Semua Jurusan</option>
                    @foreach($daftarJurusan as $jur)
                        <option value="{{ $jur->id }}" {{ request('jurusan') == $jur->id ? 'selected' : '' }}>
                            {{ $jur->kode_jurusan }} - {{ $jur->nama_jurusan }}
                        </option>
                    @endforeach
                </select>
            </div>

        </form>
    </div>

    <!-- ====================================================== -->
    <!-- HASIL FILTER (DI-UPDATE VIA AJAX)                      -->
    <!-- Wrapper stabil utk delegasi event + overlay loading.   -->
    <!-- ====================================================== -->
    <div id="kelasResultsWrapper" style="position: relative;">
        <div id="kelasResults">
    @include('admin.kelas._results')

        </div>

        {{-- Loading indicator (spinner tipis saat fetch berlangsung) --}}
        <div id="kelasLoading" class="master-list-loading" style="display: none; position: absolute; inset: 0; z-index: 20; align-items: center; justify-content: center; background: rgba(255,255,255,0.65); border-radius: 16px;">
            <div class="d-flex align-items-center gap-2 px-3 py-2 bg-white rounded-3 shadow-sm">
                <div class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></div>
                <span class="small fw-semibold text-muted">Memuat data...</span>
            </div>
        </div>
    </div>
</div>

<!-- ================= MODALS KHUSUS ROLE ADMIN ================= -->
@if(in_array(auth()->user()->role ?? '', ['admin_tu', 'admin', 'super_admin']) || (auth()->user() && auth()->user()->isTestingUser()))

<!-- MODAL TAMBAH KELAS -->
<div class="modal fade" id="modalTambahKelas" tabindex="-1" aria-labelledby="modalTambahKelasLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark" id="modalTambahKelasLabel">Tambah Data Kelas Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('kelas.store') }}" method="POST">
                @csrf
                <div class="modal-body py-4">
                    <!-- TINGKAT -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">TINGKAT KELAS <span class="text-danger">*</span></label>
                        <select name="tingkat" id="kelas-tingkat" class="form-select rounded-3" required>
                            <option value="">-- Pilih Tingkat --</option>
                            <option value="X" {{ old('tingkat') == 'X' ? 'selected' : '' }}>Kelas X (Sepuluh)</option>
                            <option value="XI" {{ old('tingkat') == 'XI' ? 'selected' : '' }}>Kelas XI (Sebelas)</option>
                            <option value="XII" {{ old('tingkat') == 'XII' ? 'selected' : '' }}>Kelas XII (Dua Belas)</option>
                        </select>
                    </div>

                    <!-- JURUSAN -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">KOMPETENSI KEAHLIAN / JURUSAN <span class="text-danger">*</span></label>
                        <select name="id_jurusan" id="kelas-jurusan" class="form-select rounded-3" required>
                            <option value="">-- Pilih Jurusan --</option>
                            @foreach($daftarJurusan as $jurusan)
                                <option value="{{ $jurusan->id }}" data-kode="{{ $jurusan->kode_jurusan }}" {{ old('id_jurusan') == $jurusan->id ? 'selected' : '' }}>
                                    {{ $jurusan->kode_jurusan }} - {{ $jurusan->nama_jurusan }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- NOMOR ROMBEL (Auto-detected) -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">NOMOR ROMBEL (Otomatis) <span class="text-danger">*</span></label>
                        <input type="number" name="nomor_rombel" id="kelas-nomor-rombel" value="{{ old('nomor_rombel') }}" min="1" readonly required class="form-control rounded-3 bg-light" placeholder="Otomatis">
                        <div class="form-text text-muted small">Nomor rombel dihitung otomatis berdasarkan kombinasi tingkat &amp; jurusan.</div>
                    </div>

                    <!-- NAMA KELAS (Preview / Auto-generated) -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">NAMA KELAS (Otomatis) <span class="text-danger">*</span></label>
                        <input type="text" id="kelas-nama-preview" name="nama_kelas" value="{{ old('nama_kelas') }}" readonly required class="form-control rounded-3 bg-light fw-bold" placeholder="X RPL 1">
                        <div class="form-text text-muted small">Nama kelas dihasilkan otomatis: Tingkat + Kode Jurusan + Nomor Rombel.</div>
                    </div>

                    <!-- WALI KELAS -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">WALI KELAS (OPSIONAL)</label>
                        <select name="id_wali_kelas" class="form-select rounded-3">
                            <option value="">-- Belum Ditentukan / Pilih Nanti --</option>
                            @foreach($daftarWaliKelas as $wali)
                                @php
                                    $hasKelas = $wali->kelasWali->isNotEmpty();
                                    $namaKelasDipegang = $hasKelas ? $wali->kelasWali->pluck('nama_kelas')->join(', ') : '';
                                @endphp
                                <option value="{{ $wali->id }}" {{ old('id_wali_kelas') == $wali->id ? 'selected' : '' }} {{ $hasKelas ? 'disabled' : '' }}>
                                    {{ $wali->nama }} @if($wali->nip) (NIP: {{ $wali->nip }}) @endif
                                    @if($hasKelas)
                                        - [Sudah menjadi wali di {{ $namaKelasDipegang }}]
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text text-muted small">1 Guru hanya dapat ditugaskan menjadi Wali Kelas untuk 1 kelas.</div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4 fw-semibold">Simpan Kelas</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODALS EDIT KELAS per baris dipindah ke admin.kelas._results (partial AJAX) -->
<!-- agar selalu sinkron dengan baris data hasil filter terbaru.              -->

@endif

@push('scripts')
<script>
    // ── Live AJAX Filter Data Master Kelas ──────────────────────────────
    // Search & dropdown memicu fetch partial hasil tanpa me-refresh halaman.
    // Debounce 300ms pada input search. Partial DOM update + spinner loading.
    (function () {
        const wrapperEl = document.getElementById('kelasResultsWrapper');
        const resultsEl = document.getElementById('kelasResults');
        const loadingEl = document.getElementById('kelasLoading');
        const form      = document.getElementById('filterKelasForm');
        if (!wrapperEl || !resultsEl || !form) return;

        const searchInput    = document.getElementById('searchKelas');
        const tingkatSelect  = document.getElementById('tingkatKelas');
        const jurusanSelect  = document.getElementById('jurusanKelas');
        const BASE_URL       = '{{ route("kelas.index") }}';

        let currentPage = {{ (int) $dataKelas->currentPage() }};
        let requestSeq  = 0;

        function debounce(fn, ms) {
            let timer;
            return function (...args) {
                clearTimeout(timer);
                timer = setTimeout(() => fn.apply(this, args), ms);
            };
        }

        function buildQuery() {
            const params = new URLSearchParams();
            const search = searchInput ? searchInput.value.trim() : '';
            if (search) params.set('search', search);
            if (tingkatSelect && tingkatSelect.value && tingkatSelect.value !== 'Semua Tingkat') {
                params.set('tingkat', tingkatSelect.value);
            }
            if (jurusanSelect && jurusanSelect.value && jurusanSelect.value !== 'Semua Jurusan') {
                params.set('jurusan', jurusanSelect.value);
            }
            if (currentPage > 1) params.set('page', currentPage);
            return params;
        }

        function refresh() {
            const seq = ++requestSeq;
            loadingEl.style.display = 'flex';

            const params = buildQuery();
            const qs    = params.toString();
            const targetUrl = BASE_URL + (qs ? '?' + qs : '');

            // Sinkronkan URL tanpa me-refresh halaman (bisa di-share / di-bookmark)
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
                // Fallback: muat ulang halaman agar data tetap tampil
                window.location.href = targetUrl;
            })
            .finally(() => {
                if (seq === requestSeq) loadingEl.style.display = 'none';
            });
        }

        // 1) Search input → debounce ±300ms
        if (searchInput) {
            searchInput.addEventListener('input', debounce(() => { currentPage = 1; refresh(); }, 300));
        }

        // 2) Dropdown berubah → filter langsung
        [tingkatSelect, jurusanSelect].forEach(sel => {
            if (sel) sel.addEventListener('change', () => { currentPage = 1; refresh(); });
        });

        // 3) Cegah submit GET biasa (Enter di input search)
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            currentPage = 1;
            refresh();
        });

        // 4) Pagination tanpa reload (delegasi pada wrapper; Bootstrap page-link)
        wrapperEl.addEventListener('click', (e) => {
            const pageLink = e.target.closest('a.page-link, a.pagination-btn');
            if (!pageLink) return;
            const u = new URL(pageLink.href);
            if (!u.searchParams.has('page')) return;
            e.preventDefault();
            const p = parseInt(u.searchParams.get('page') || '1', 10);
            if (!Number.isNaN(p) && p >= 1) {
                currentPage = p;
                refresh();
            }
        });
    })();

    (function () {
        const counts = @json($countsByKombinasi ?? []);

        function kunci(tingkat, idJurusan) {
            return tingkat + '|' + idJurusan;
        }

        function hitungRombel(tingkat, idJurusan) {
            if (!tingkat || !idJurusan) return '';
            const jumlah = counts[kunci(tingkat, idJurusan)] || 0;
            return jumlah + 1;
        }

        function perbaruiPreview() {
            const tingkat = document.getElementById('kelas-tingkat')?.value || '';
            const jurusanSelect = document.getElementById('kelas-jurusan');
            const idJurusan = jurusanSelect?.value || '';
            const kodeJurusan = jurusanSelect?.selectedOptions?.[0]?.dataset?.kode || '';
            const nomorRombel = hitungRombel(tingkat, idJurusan);

            const nomorInput = document.getElementById('kelas-nomor-rombel');
            const previewInput = document.getElementById('kelas-nama-preview');

            if (nomorInput) {
                nomorInput.value = nomorRombel === '' ? '' : nomorRombel;
            }
            if (previewInput) {
                previewInput.value = (tingkat && kodeJurusan && nomorRombel !== '')
                    ? tingkat + ' ' + kodeJurusan + ' ' + nomorRombel
                    : '';
            }
        }

        const tingkatEl = document.getElementById('kelas-tingkat');
        const jurusanEl = document.getElementById('kelas-jurusan');
        if (tingkatEl) tingkatEl.addEventListener('change', perbaruiPreview);
        if (jurusanEl) jurusanEl.addEventListener('change', perbaruiPreview);

        document.addEventListener('DOMContentLoaded', perbaruiPreview);
    })();
</script>
@endpush
@endsection
