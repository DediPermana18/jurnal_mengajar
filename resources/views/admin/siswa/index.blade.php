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

    @media (max-width: 575.98px) {
        .filter-bar {
            padding: 0.9rem 0.9rem;
        }
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
        <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto flex-shrink-0">
            {{-- Tombol Hapus Semua --}}
            @if(auth()->user()?->isAdmin())
            <button type="button"
                    id="btnHapusSemua"
                    class="btn btn-outline-danger rounded-3 px-3 py-2 fw-semibold d-flex align-items-center justify-content-center gap-2 w-full sm:w-auto"
                    style="font-size: 0.9rem;"
                    data-bs-toggle="modal"
                    data-bs-target="#modalHapusSemua">
                <i class="bi bi-trash3"></i>
                <span>Hapus Semua</span>
            </button>
            @endif
            {{-- Tombol Export --}}
            <button type="button"
                    id="btnExport"
                    class="btn btn-outline-success rounded-3 px-3 py-2 fw-semibold d-flex align-items-center justify-content-center gap-2 w-full sm:w-auto"
                    style="font-size: 0.9rem;"
                    data-bs-toggle="modal"
                    data-bs-target="#modalExport">
                <i class="bi bi-download"></i>
                <span>Export</span>
            </button>
            {{-- Tombol Tambah Siswa --}}
            <a href="{{ route('siswa.create') }}"
               class="btn btn-primary rounded-3 px-3 py-2 fw-semibold d-flex align-items-center justify-content-center gap-2 w-full sm:w-auto"
               style="background-color: var(--primary-blue, #1677ff); border-color: var(--primary-blue, #1677ff); font-size: 0.9rem;">
                <i class="bi bi-plus-lg"></i>
                <span>Tambah Siswa</span>
            </a>
        </div>
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

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 rounded-3 mb-4 d-flex align-items-center gap-2" role="alert" style="background:#fef2f2; color:#991b1b;">
            <i class="bi bi-x-circle-fill fs-5"></i>
            <div>{{ session('error') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('import_warnings'))
        <div class="alert alert-warning alert-dismissible fade show border-0 rounded-3 mb-4" role="alert" style="background:#fffbeb; color:#92400e;">
            <div class="d-flex align-items-center gap-2 mb-2">
                <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                <strong>Beberapa baris dilewati:</strong>
            </div>
            <ul class="mb-0 ps-3" style="font-size:0.85rem;">
                @foreach(session('import_warnings') as $warn)
                    <li>{{ $warn }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- ====================================================== --}}
    {{-- FILTER BAR                                              --}}
    {{-- ====================================================== --}}
    @php
        // Data untuk dropdown dependen Kelas ↔ Jurusan (AlpineJS)
        $kelasFilterOpts = $dataKelas->map(fn ($k) => [
            'id'         => (string) $k->id,
            'jurusan_id' => (string) ($k->id_jurusan ?? ''),
            'label'      => $k->tingkat . ' • ' . $k->nama_kelas . ($k->jurusan ? ' (' . $k->jurusan->nama_jurusan . ')' : ''),
        ])->values()->all();

        $jurusanFilterOpts = $jurusans->map(fn ($j) => [
            'id'   => (string) $j->id,
            'kode' => $j->kode_jurusan,
        ])->values()->all();

        $filterJsonFlags = JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP;
    @endphp
    <div class="filter-bar">
        <form id="filterSiswaForm" action="{{ route('siswa.index') }}" method="GET"
              x-data='siswaFilter(
                  {!! json_encode($kelasFilterOpts, $filterJsonFlags) !!},
                  {!! json_encode($jurusanFilterOpts, $filterJsonFlags) !!},
                  {!! json_encode(request('id_kelas')) !!},
                  {!! json_encode(request('id_jurusan')) !!}
              )'>
            <div class="flex flex-col sm:flex-row gap-2">
                {{-- Search Input --}}
                <div class="w-full sm:flex-[4]">
                    <div class="search-wrapper">
                        <i class="bi bi-search"></i>
                        <input type="text"
                               id="searchSiswa"
                               name="search"
                               class="form-control"
                               placeholder="Cari nama siswa atau NISN..."
                               value="{{ request('search') }}"
                               autocomplete="off">
                    </div>
                </div>

                {{-- Dropdown Pilih Kelas --}}
                <div class="w-full sm:flex-[3]">
                    <select name="id_kelas" id="filterKelas" class="form-select"
                            x-model="kelasId"
                            @change="onKelasChange()">
                        <option value="">Pilih Kelas</option>
                        @foreach($dataKelas as $k)
                            <option value="{{ $k->id }}"
                                    data-jurusan="{{ $k->id_jurusan ?? '' }}"
                                    :hidden="jurusanId && jurusanId != '{{ $k->id_jurusan ?? '' }}'"
                                    :disabled="jurusanId && jurusanId != '{{ $k->id_jurusan ?? '' }}'"
                                    {{ (string)request('id_kelas') === (string)$k->id ? 'selected' : '' }}>
                                {{ $k->nama_lengkap }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Dropdown Pilih Jurusan --}}
                <div class="w-full sm:flex-[3]">
                    <select name="id_jurusan" id="filterJurusan" class="form-select"
                            x-model="jurusanId"
                            @change="onJurusanChange()">
                        <option value="">Semua Jurusan</option>
                        @foreach($jurusans as $j)
                            <option value="{{ $j->id }}"
                                    {{ (string)request('id_jurusan') === (string)$j->id ? 'selected' : '' }}>
                                {{ $j->kode_jurusan }} - {{ $j->nama_jurusan }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Dropdown Jenis Kelamin --}}
                <div class="w-full sm:flex-[2]">
                    <select name="jenis_kelamin" id="filterJenisKelamin" class="form-select">
                        <option value="">Jenis Kelamin</option>
                        <option value="L" {{ request('jenis_kelamin') == 'L' ? 'selected' : '' }}>Laki-laki</option>
                        <option value="P" {{ request('jenis_kelamin') == 'P' ? 'selected' : '' }}>Perempuan</option>
                    </select>
                </div>
            </div>
        </form>
    </div>

    {{-- ====================================================== --}}
    {{-- HASIL FILTER (DI-UPDATE VIA AJAX)                       --}}
    {{-- Wrapper stabil utk delegasi event + overlay loading.    --}}
    {{-- ====================================================== --}}
    <div id="siswaResultsWrapper" style="position: relative;">
        <div id="siswaResults">
            @include('admin.siswa._results')
        </div>

        {{-- Loading indicator (spinner tipis saat fetch berlangsung) --}}
        <div id="siswaLoading" class="master-list-loading" style="display: none; position: absolute; inset: 0; z-index: 20; align-items: center; justify-content: center; background: rgba(255,255,255,0.65); border-radius: 16px;">
            <div class="d-flex align-items-center gap-2 px-3 py-2 bg-white rounded-3 shadow-sm">
                <div class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></div>
                <span class="small fw-semibold text-muted">Memuat data...</span>
            </div>
        </div>
    </div>
</div>

{{-- ====================================================== --}}
{{-- MODAL: Hapus Semua Data Siswa (Danger Confirmation)    --}}
{{-- ====================================================== --}}
<div class="modal fade" id="modalHapusSemua" tabindex="-1" aria-labelledby="modalHapusSemuaLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border: none; box-shadow: 0 20px 60px rgba(127,0,0,0.18);">

            {{-- Header --}}
            <div class="modal-header" style="border-bottom: 1px solid #fee2e2; padding: 1.25rem 1.5rem; background: #fff5f5; border-radius: 16px 16px 0 0;">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,#ef4444,#b91c1c);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="bi bi-exclamation-triangle-fill text-white" style="font-size:1.2rem;"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="modalHapusSemuaLabel" style="font-size:1rem;color:#7f1d1d;">Hapus Semua Data Siswa</h5>
                        <p class="mb-0" style="font-size:0.78rem;color:#b91c1c;">Tindakan ini bersifat permanen dan tidak dapat dibatalkan</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            {{-- Body --}}
            <form action="{{ route('siswa.delete-all') }}" method="POST" id="formHapusSemua">
                @csrf
                @method('DELETE')
                <div class="modal-body" style="padding: 1.5rem;">

                    {{-- Peringatan keras --}}
                    <div class="rounded-3 p-3 mb-4" style="background:#fef2f2;border:1px solid #fecaca;">
                        <p class="fw-bold mb-2" style="font-size:0.88rem;color:#991b1b;"><i class="bi bi-shield-exclamation me-1"></i> Peringatan!</p>
                        <p class="mb-0" style="font-size:0.82rem;color:#b91c1c;line-height:1.6;">
                            Apakah Anda yakin ingin menghapus <strong>SELURUH data siswa</strong>?
                            Semua data identitas, kelas, dan riwayat yang terhubung akan ikut terpengaruh.
                            <br><strong>Tindakan ini tidak dapat dibatalkan!</strong>
                        </p>
                    </div>

                    {{-- Stat jumlah siswa --}}
                    <div class="rounded-3 p-3 mb-4 d-flex align-items-center gap-3" style="background:#f8fafc;border:1px solid #e2e8f0;">
                        <i class="bi bi-people-fill" style="font-size:1.5rem;color:#64748b;"></i>
                        <div>
                            <div style="font-size:0.78rem;color:#64748b;font-weight:500;">Jumlah siswa yang akan dihapus</div>
                            <div style="font-size:1.25rem;font-weight:800;color:#0f172a;">{{ number_format($totalSiswa) }} siswa</div>
                        </div>
                    </div>

                    {{-- Input konfirmasi --}}
                    <div class="mb-1">
                        <label for="inputKonfirmasiHapus" class="form-label fw-semibold" style="font-size:0.875rem;color:#374151;">
                            Ketik <code style="background:#fee2e2;color:#b91c1c;border-radius:4px;padding:0.1rem 0.4rem;font-weight:700;">HAPUS</code> untuk mengonfirmasi
                        </label>
                        <input type="text"
                               class="form-control"
                               id="inputKonfirmasiHapus"
                               name="konfirmasi"
                               placeholder="Ketik: HAPUS"
                               autocomplete="off"
                               style="border-radius:10px;border:2px solid #e2e8f0;font-size:0.875rem;font-weight:600;letter-spacing:0.05em;transition:border-color 0.2s;">
                        <div class="mt-1" id="konfirmasiHint" style="font-size:0.78rem;color:#94a3b8;">Ketik tepat: HAPUS (huruf kapital semua)</div>
                    </div>

                </div>

                {{-- Footer --}}
                <div class="modal-footer" style="border-top:1px solid #fee2e2;padding:1rem 1.5rem;gap:0.75rem;">
                    <button type="button" class="btn btn-light border rounded-3 px-4 py-2 fw-semibold" data-bs-dismiss="modal" style="font-size:0.875rem;">Batal</button>
                    <button type="submit"
                            id="btnSubmitHapusSemua"
                            class="btn btn-danger rounded-3 px-4 py-2 fw-semibold d-flex align-items-center gap-2"
                            style="font-size:0.875rem;"
                            disabled>
                        <i class="bi bi-trash3-fill"></i>
                        <span>Ya, Hapus Semua</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ====================================================== --}}
{{-- MODAL: Export Data Siswa (Pilih Format xlsx / csv)    --}}
{{-- ====================================================== --}}
<div class="modal fade" id="modalExport" tabindex="-1" aria-labelledby="modalExportLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border: none; box-shadow: 0 20px 60px rgba(15,23,42,0.15);">

            {{-- Header --}}
            <div class="modal-header" style="border-bottom: 1px solid #e8eef5; padding: 1.25rem 1.5rem; background: #f8fafc; border-radius: 16px 16px 0 0;">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,#16a34a,#15803d);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="bi bi-file-earmark-arrow-down-fill text-white" style="font-size:1.2rem;"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="modalExportLabel" style="font-size:1rem;color:#0f172a;">Export Data Siswa</h5>
                        <p class="mb-0" style="font-size:0.78rem;color:#64748b;">Pilih format file yang ingin diunduh.</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            {{-- Body --}}
            <form action="{{ route('siswa.export') }}" method="GET" id="formExport">
                <div class="modal-body" style="padding: 1.5rem;">
                    <label class="form-label fw-semibold" style="font-size:0.85rem;color:#374151;">Format Export</label>

                    <div class="form-check mb-3 p-3 rounded-3 border" style="border-color:#bbf7d0 !important;background:#f0fdf4;cursor:pointer;"
                         onclick="document.getElementById('formatXlsx').checked = true;">
                        <input class="form-check-input" type="radio" name="format" value="xlsx" id="formatXlsx" checked>
                        <label class="form-check-label fw-semibold d-flex align-items-center gap-2" for="formatXlsx" style="font-size:0.875rem;color:#166534;">
                            <span>🟢</span> Excel (.xlsx)
                            <span class="text-muted fw-normal">— Format standar, terbuka di Excel/Spreadsheet</span>
                        </label>
                    </div>

                    <div class="form-check mb-1 p-3 rounded-3 border" style="border-color:#fde68a !important;background:#fffbeb;cursor:pointer;"
                         onclick="document.getElementById('formatCsv').checked = true;">
                        <input class="form-check-input" type="radio" name="format" value="csv" id="formatCsv">
                        <label class="form-check-label fw-semibold d-flex align-items-center gap-2" for="formatCsv" style="font-size:0.875rem;color:#92400e;">
                            <span>⚡</span> CSV (.csv)
                            <span class="text-muted fw-normal">— Export teks cepat, ringan & kompatibel</span>
                        </label>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="modal-footer" style="border-top:1px solid #e8eef5;padding:1rem 1.5rem;gap:0.75rem;">
                    <button type="button" class="btn btn-light border rounded-3 px-4 py-2 fw-semibold" data-bs-dismiss="modal" style="font-size:0.875rem;">Batal</button>
                    <button type="submit" class="btn btn-success rounded-3 px-4 py-2 fw-semibold d-flex align-items-center gap-2" style="font-size:0.875rem;">
                        <i class="bi bi-download"></i>
                        <span>Download</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    // ── Dropdown dependen: Kelas ↔ Jurusan ──────────────────────────────────
    // - Memilih kelas → jurusan otomatis mengikuti kelas tersebut.
    // - Memilih jurusan → daftar kelas difilter khusus jurusan itu.
    // - "Semua Jurusan" → semua kelas tampil lagi.
    function siswaFilter(classes, jurusans, initialKelas, initialJurusan) {
        return {
            classes: classes,
            jurusans: jurusans,

            kelasId: initialKelas ? String(initialKelas) : '',
            jurusanId: initialJurusan ? String(initialJurusan) : '',

            init() {
                // Sinkronkan kombinasi di awal (mis. URL yang diisi manual).
                if (this.kelasId) {
                    const k = this.classes.find(c => String(c.id) === String(this.kelasId));
                    if (k && k.jurusan_id) {
                        this.jurusanId = String(k.jurusan_id);
                    }
                }
            },

            get filteredClasses() {
                if (!this.jurusanId) {
                    return this.classes;
                }
                return this.classes.filter(c => String(c.jurusan_id) === String(this.jurusanId));
            },

            onKelasChange() {
                // Kelas dipilih → jurusan otomatis diselaraskan dengan kelas.
                if (this.kelasId) {
                    const k = this.classes.find(c => String(c.id) === String(this.kelasId));
                    if (k && k.jurusan_id) {
                        this.jurusanId = String(k.jurusan_id);
                    }
                }
                this._submit();
            },

            onJurusanChange() {
                // Jurusan dipilih → jika kelas yang dipilih sebelumnya bukan milik jurusan baru, reset kelas.
                if (this.jurusanId && this.kelasId) {
                    const k = this.classes.find(c => String(c.id) === String(this.kelasId));
                    if (!k || String(k.jurusan_id) !== String(this.jurusanId)) {
                        this.kelasId = '';
                    }
                }
                this._submit();
            },

            _submit() {
                this.$nextTick(() => {
                    // Live AJAX filter: panggil refresh tabel tanpa me-refresh halaman.
                    if (window.siswaTable && typeof window.siswaTable.refresh === 'function') {
                        window.siswaTable.refresh();
                    }
                });
            },
        };
    }

    // ── Live AJAX Filter Data Master Siswa ───────────────────────────────
    // Search & dropdown memicu fetch partial hasil tanpa me-refresh halaman.
    // Debounce 300ms pada input search. Partial DOM update + spinner loading.
    window.siswaTable = (function () {
        const wrapperEl = document.getElementById('siswaResultsWrapper');
        const resultsEl = document.getElementById('siswaResults');
        const loadingEl = document.getElementById('siswaLoading');
        const form      = document.getElementById('filterSiswaForm');
        if (!wrapperEl || !resultsEl || !form) return { refresh: function () {} };

        const searchInput   = document.getElementById('searchSiswa');
        const kelasSelect   = document.getElementById('filterKelas');
        const jurusanSelect = document.getElementById('filterJurusan');
        const genderSelect  = document.getElementById('filterJenisKelamin');
        const BASE_URL      = '{{ route("siswa.index") }}';

        let currentPage = {{ (int) $dataSiswa->currentPage() }};
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
            if (kelasSelect && kelasSelect.value) params.set('id_kelas', kelasSelect.value);
            if (jurusanSelect && jurusanSelect.value) params.set('id_jurusan', jurusanSelect.value);
            if (genderSelect && genderSelect.value) params.set('jenis_kelamin', genderSelect.value);
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

        // 2) Dropdown berubah → filter langsung (Kelas/Jurusan ditangani Alpine onKelasChange/onJurusanChange)
        if (genderSelect) {
            genderSelect.addEventListener('change', () => { currentPage = 1; refresh(); });
        }

        // 3) Cegah submit GET biasa (Enter di input search)
        if (form) {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                currentPage = 1;
                refresh();
            });
        }

        // 4) Pagination + Reset Filter tanpa reload (event delegation pada wrapper)
        wrapperEl.addEventListener('click', (e) => {
            const resetLink = e.target.closest('[data-reset-filter]');
            if (resetLink) {
                e.preventDefault();
                if (searchInput)   searchInput.value = '';
                if (kelasSelect)   kelasSelect.value = '';
                if (jurusanSelect) jurusanSelect.value = '';
                if (genderSelect)  genderSelect.value = '';
                currentPage = 1;
                refresh();
                return;
            }

            const pageLink = e.target.closest('a.pagination-btn');
            if (!pageLink) return;
            e.preventDefault();
            const u  = new URL(pageLink.href);
            const p  = parseInt(u.searchParams.get('page') || '1', 10);
            if (!Number.isNaN(p) && p >= 1) {
                currentPage = p;
                refresh();
            }
        });

        return { refresh: refresh };
    })();

    // ── Konfirmasi "HAPUS" untuk modal hapus semua ────────────────────────
    (function () {
        const input  = document.getElementById('inputKonfirmasiHapus');
        const btn    = document.getElementById('btnSubmitHapusSemua');
        const hint   = document.getElementById('konfirmasiHint');
        const modal  = document.getElementById('modalHapusSemua');

        if (!input || !btn) return;

        function checkValue() {
            const isValid = input.value === 'HAPUS';
            btn.disabled  = !isValid;

            if (input.value.length === 0) {
                input.style.borderColor = '#e2e8f0';
                hint.style.color = '#94a3b8';
            } else if (isValid) {
                input.style.borderColor = '#22c55e';
                hint.textContent  = '✓ Konfirmasi diterima';
                hint.style.color  = '#16a34a';
            } else {
                input.style.borderColor = '#ef4444';
                hint.textContent  = 'Ketik tepat: HAPUS (huruf kapital semua)';
                hint.style.color  = '#dc2626';
            }
        }

        input.addEventListener('input', checkValue);

        // Reset saat modal ditutup
        if (modal) {
            modal.addEventListener('hidden.bs.modal', function () {
                input.value           = '';
                btn.disabled          = true;
                input.style.borderColor = '#e2e8f0';
                hint.textContent      = 'Ketik tepat: HAPUS (huruf kapital semua)';
                hint.style.color      = '#94a3b8';
            });
        }
    })();
</script>
@endpush
