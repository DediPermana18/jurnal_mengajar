@extends('admin.layouts.app')

@section('title', 'Import Data - WebJournal Management System')

@push('styles')
<style>
    .import-page-title {
        font-size: 1.65rem;
        font-weight: 800;
        color: #0f172a;
        letter-spacing: -0.02em;
    }

    .import-subtitle {
        font-size: 0.9rem;
        color: #64748b;
        font-weight: 500;
    }

    /* ── Tab Navigation ─────────────────────────────── */
    .import-tabs {
        display: flex;
        gap: 0.5rem;
        border-bottom: 1px solid #e8eef5;
        padding-bottom: 0;
        overflow-x: auto;
    }

    .import-tab {
        display: inline-flex;
        align-items: center;
        gap: 0.6rem;
        padding: 0.8rem 1.25rem;
        font-size: 0.875rem;
        font-weight: 700;
        color: #64748b;
        background: transparent;
        border: none;
        border-bottom: 3px solid transparent;
        transition: all 0.18s ease;
        cursor: pointer;
        white-space: nowrap;
    }

    .import-tab:hover {
        color: #334155;
        background: #f8fafc;
    }

    .import-tab.active {
        color: var(--primary-blue, #1677ff);
        border-bottom-color: var(--primary-blue, #1677ff);
    }

    .import-tab.disabled {
        opacity: 0.45;
        cursor: not-allowed;
    }

    /* ── Cards ──────────────────────────────────────── */
    .card-import {
        background: #ffffff;
        border: 1px solid #e8eef5;
        border-radius: 16px;
        box-shadow: 0 2px 12px rgba(15, 23, 42, 0.05);
    }

    .card-import-header {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid #f1f5f9;
    }

    .card-import-title {
        font-size: 1.05rem;
        font-weight: 800;
        color: #0f172a;
    }

    .card-import-body {
        padding: 1.5rem;
    }

    .tab-pane-item {
        display: none;
    }

    .tab-pane-item.active {
        display: block;
    }

    /* ── Dropzone─style upload area ─────────────────── */
    .dropzone-import {
        border: 2px dashed #cbd5e1;
        border-radius: 14px;
        background: #f8fafc;
        padding: 2.5rem 1.5rem;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .dropzone-import:hover,
    .dropzone-import.dragover {
        border-color: var(--primary-blue, #1677ff);
        background: #eff6ff;
    }

    .dropzone-import .dz-icon {
        font-size: 2.5rem;
        color: #94a3b8;
        display: block;
        margin-bottom: 0.75rem;
    }

    .dropzone-import:hover .dz-icon,
    .dropzone-import.dragover .dz-icon {
        color: var(--primary-blue, #1677ff);
    }

    .dropzone-import .dz-title {
        font-size: 0.95rem;
        font-weight: 700;
        color: #334155;
        margin-bottom: 0.25rem;
    }

    .dropzone-import .dz-sub {
        font-size: 0.8rem;
        color: #94a3b8;
    }

    .file-selected {
        background: #ecfdf5;
        border: 1px solid #a7f3d0;
        border-radius: 10px;
        padding: 0.7rem 1rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .file-selected i {
        color: #059669;
        font-size: 1.25rem;
    }

    .file-selected-name {
        font-weight: 700;
        color: #065f46;
        font-size: 0.875rem;
        word-break: break-all;
    }

    .file-selected-size {
        font-size: 0.78rem;
        color: #059669;
    }

    /* ── Panduan / instructions ─────────────────────── */
    .guide-item {
        display: flex;
        gap: 0.85rem;
        padding: 0.6rem 0;
        border-bottom: 1px dashed #e8eef5;
    }

    .guide-item:last-child {
        border-bottom: none;
    }

    .guide-num {
        width: 26px;
        height: 26px;
        border-radius: 8px;
        background: #eff6ff;
        color: #2563eb;
        font-weight: 800;
        font-size: 0.8rem;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .guide-text {
        font-size: 0.85rem;
        color: #475569;
        line-height: 1.5;
    }

    .guide-text strong {
        color: #0f172a;
    }

    /* ── Placeholder untuk import mendatang ─────────── */
    .placeholder-import {
        border: 2px dashed #e2e8f0;
        border-radius: 14px;
        background: #f8fafc;
        padding: 3rem 2rem;
        text-align: center;
    }

    .placeholder-import .ph-icon {
        font-size: 2.5rem;
        color: #cbd5e1;
        display: block;
        margin-bottom: 0.75rem;
    }

    .placeholder-import .ph-title {
        font-size: 1rem;
        font-weight: 800;
        color: #475569;
        margin-bottom: 0.35rem;
    }

    .placeholder-import .ph-sub {
        font-size: 0.8rem;
        color: #94a3b8;
    }

    /* ── Warning list ──────────────────────────────── */
    .warning-list {
        max-height: 280px;
        overflow-y: auto;
        font-size: 0.8rem;
    }

    .warning-list li {
        padding: 0.35rem 0;
        border-bottom: 1px dashed #f1f5f9;
    }

    .warning-list li:last-child {
        border-bottom: none;
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">

    {{-- ====================================================== --}}
    {{-- HEADER                                                  --}}
    {{-- ====================================================== --}}
    <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-3 mb-3">
        <div>
            <h2 class="import-page-title mb-1">Import Data</h2>
            <p class="import-subtitle mb-0">
                Pusat import data dari file Excel ke sistem.
            </p>
        </div>
    </div>

    {{-- ====================================================== --}}
    {{-- FLASH / HASIL IMPORT                                    --}}
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
        <div class="card-import mb-4 overflow-hidden" style="border-color:#fde68a;">
            <div class="d-flex align-items-center gap-2 px-4 py-3" style="background:#fffbeb;border-bottom:1px solid #fde68a;">
                <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
                <strong style="color:#92400e; font-size:0.9rem;">Beberapa baris dilewati selama import:</strong>
            </div>
            <div class="px-4 py-3">
                <ul class="warning-list mb-0 ps-0 list-unstyled">
                    @foreach(session('import_warnings') as $warn)
                        <li style="color:#92400e;">
                            <i class="bi bi-dot text-warning"></i>{{ $warn }}
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show border-0 rounded-3 mb-4 d-flex align-items-center gap-2" role="alert" style="background:#fef2f2; color:#991b1b;">
            <i class="bi bi-x-circle-fill fs-5"></i>
            <div>
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    {{-- ====================================================== --}}
    {{-- TAB NAVIGASI                                           --}}
    {{-- ====================================================== --}}
    <div class="import-tabs mb-4" role="tablist">
        <button type="button"
                class="import-tab active"
                id="tab-btn-siswa"
                data-tab-target="tab-siswa"
                role="tab"
                aria-selected="true">
            <i class="bi bi-people-fill"></i>
            Import Data Siswa
        </button>
        <button type="button"
                class="import-tab"
                id="tab-btn-guru"
                data-tab-target="tab-guru"
                role="tab"
                aria-selected="false">
            <i class="bi bi-person-vcard-fill"></i>
            Import Data Guru
        </button>
        <button type="button"
                class="import-tab"
                id="tab-btn-kelas"
                data-tab-target="tab-kelas-jurusan"
                role="tab"
                aria-selected="false">
            <i class="bi bi-diagram-3-fill"></i>
            Import Kelas / Jurusan
        </button>
        <button type="button"
                class="import-tab"
                id="tab-btn-ruangan"
                data-tab-target="tab-ruangan"
                role="tab"
                aria-selected="false">
            <i class="bi bi-building-fill"></i>
            Import Data Ruangan
        </button>
        <button type="button"
                class="import-tab"
                id="tab-btn-jadwal"
                data-tab-target="tab-jadwal"
                role="tab"
                aria-selected="false">
            <i class="bi bi-calendar-week-fill"></i>
            Import Jadwal Pelajaran
        </button>
    </div>

    {{-- ====================================================== --}}
    {{-- PANEL: IMPORT DATA SISWA (DEFAULT AKTIF)               --}}
    {{-- ====================================================== --}}
    <div class="tab-pane-item active" id="tab-siswa" role="tabpanel">
    <div class="row g-4">
        {{-- Kolom Kiri: Form Upload --}}
        <div class="col-lg-7">

            <div class="card-import">
                <div class="card-import-header d-flex align-items-center gap-3">
                    <div style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#22c55e,#16a34a);display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-file-earmark-spreadsheet text-white" style="font-size:1.1rem;"></i>
                    </div>
                    <div>
                        <h5 class="card-import-title mb-0">Upload File (Excel / CSV)</h5>
                        <p class="mb-0" style="font-size:0.78rem;color:#64748b;">Format: DAFTAR PRESENSI PESERTA DIDIK (.xlsx / .xls / .csv)</p>
                    </div>
                </div>

                <div class="card-import-body">
                    <form action="{{ route('import.siswa') }}" method="POST" enctype="multipart/form-data" id="formImportSiswa">
                        @csrf

                        {{-- Dropzone Upload --}}
                        <div class="mb-4">
                            <label for="fileExcelImport" class="form-label fw-semibold" style="font-size:0.875rem;color:#374151;">Upload File (Excel / CSV) <span class="text-danger">*</span></label>
                            <div class="dropzone-import" id="dropzoneArea">
                                <i class="bi bi-cloud-arrow-up dz-icon"></i>
                                <div class="dz-title">Upload File (Excel / CSV)</div>
                                <div class="dz-sub">Format file yang didukung: .xlsx, .xls, .csv (maks. 10 MB)</div>
                                <input type="file"
                                       class="d-none @error('file_excel') is-invalid @enderror"
                                       id="fileExcelImport"
                                       name="file_excel"
                                       accept=".xlsx,.xls,.csv"
                                       required>
                            </div>

                                {{-- Info file terpilih --}}
                                <div class="file-selected d-none mt-3" id="fileSelectedInfo">
                                    <i class="bi bi-file-earmark-check-fill"></i>
                                    <div>
                                        <div class="file-selected-name" id="fileSelectedName"></div>
                                        <div class="file-selected-size" id="fileSelectedSize"></div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-light border ms-auto" id="btnRemoveFile" title="Ganti file">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>

                                @error('file_excel')
                                    <div class="text-danger mt-2" style="font-size:0.8rem;">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Pilih Kelas (Fallback) --}}
                            <div class="mb-4">
                                <label for="importIdKelas" class="form-label fw-semibold" style="font-size:0.875rem;color:#374151;">
                                    Kelas Tujuan
                                    <span class="text-muted fw-normal">(opsional — fallback jika kelas tidak terdeteksi otomatis)</span>
                                </label>
                                <select class="form-select @error('id_kelas') is-invalid @enderror"
                                        id="importIdKelas"
                                        name="id_kelas"
                                        style="border-radius:10px;border:1px solid #e2e8f0;font-size:0.875rem;">
                                    <option value="">— Deteksi otomatis dari file Excel —</option>
                                    @foreach($dataKelas as $kelas)
                                        <option value="{{ $kelas->id }}"
                                            {{ old('id_kelas') == $kelas->id ? 'selected' : '' }}>
                                            {{ $kelas->nama_lengkap }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('id_kelas')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Aksi --}}
                            <div class="d-flex justify-content-end gap-2">
                                <button type="submit" id="btnSubmitImport"
                                        class="btn btn-success rounded-3 px-4 py-2 fw-semibold d-flex align-items-center gap-2"
                                        style="font-size:0.875rem;">
                                    <i class="bi bi-upload"></i>
                                    <span>Mulai Import</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Info jumlah siswa saat ini --}}
                <div class="card-import mt-4">
                    <div class="card-import-body d-flex align-items-center gap-3">
                        <div style="width:44px;height:44px;border-radius:12px;background:#eff6ff;display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-people-fill text-primary" style="font-size:1.2rem;"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-dark" style="font-size:1rem;">{{ number_format($totalSiswa) }} siswa terdaftar</div>
                            <div style="font-size:0.8rem;color:#64748b;">Import dengan NISN yang sama akan me-update data yang sudah ada (bukan duplikat).</div>
                        </div>
                    </div>
                </div>

                {{-- Zona Berbahaya: Reset Data Siswa --}}
                <div class="card-import mt-4" style="border-color:#fecaca;background:#fff7f7;">
                    <div class="card-import-header d-flex align-items-center gap-3" style="border-color:#fee2e2;">
                        <div style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#ef4444,#b91c1c);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="bi bi-trash3-fill text-white"></i>
                        </div>
                        <div>
                            <h5 class="card-import-title mb-0">Zona Berbahaya — Reset Data</h5>
                            <p class="mb-0" style="font-size:0.78rem;color:#b91c1c;">Hapus MASAL &amp; permanen. Tidak dapat dikembalikan.</p>
                        </div>
                    </div>
                    <div class="card-import-body">
                        <p class="mb-3" style="font-size:0.85rem;color:#7f1d1d;">
                            Menghapus <strong>seluruh data siswa</strong> pada konteks data ini, termasuk <strong>presensi siswa,
                            dispensasi, catatan terlambat/bermasalah, dan absensi jurnal</strong> yang terkait.
                            Gunakan hanya saat akan import ulang data yang benar.
                        </p>
                        <button type="button"
                                class="btn btn-danger rounded-3 px-4 py-2 fw-semibold d-inline-flex align-items-center gap-2"
                                style="font-size:0.875rem;"
                                data-reset-route="{{ route('import.reset-siswa') }}"
                                data-reset-label="seluruh data Siswa"
                                data-reset-phrase="HAPUS DATA SISWA"
                                data-bs-toggle="modal"
                                data-bs-target="#modalResetData">
                            <i class="bi bi-trash3"></i>
                            <span>Reset / Hapus Semua Data Siswa</span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Kolom Kanan: Panduan Format --}}
            <div class="col-lg-5">
                <div class="card-import">
                        <div class="card-import-header d-flex align-items-center gap-3">
                        <div style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#1677ff,#0958d9);display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-info-circle-fill text-white" style="font-size:1.1rem;"></i>
                        </div>
                        <div>
                            <h5 class="card-import-title mb-0">Unduh Contoh &amp; Panduan</h5>
                            <p class="mb-0" style="font-size:0.78rem;color:#64748b;">Unduh contoh format presensi lalu ikuti panduan berikut.</p>
                        </div>
                    </div>

                    <div class="card-import-body">
                        <div class="d-grid gap-2 mb-3">
                            <a href="{{ route('import.template-siswa') }}" class="btn btn-outline-success rounded-3 fw-semibold text-start" style="font-size:0.875rem;">
                                <i class="bi bi-file-earmark-excel me-2"></i> Unduh Contoh Format Presensi (.xlsx)
                            </a>
                        </div>
                        <div>
                            <div class="guide-item">
                            <div class="guide-num">1</div>
                            <div class="guide-text">
                                Gunakan file <strong>DAFTAR PRESENSI PESERTA DIDIK</strong>. Untuk <strong>.xlsx / .xls</strong> bisa memakai
                                beberapa sheet per tingkat (contoh: <strong>KELAS X</strong>, <strong>KELAS XI</strong>, <strong>KELAS XII</strong>).
                            </div>
                        </div>
                        <div class="guide-item">
                            <div class="guide-num">2</div>
                            <div class="guide-text">
                                Tiap tabel kelas ditandai baris header <strong>"KELAS : X TKJ 1"</strong> / <strong>"Kelas: X DKV 2"</strong>.
                                Siswa di bawah header otomatis masuk ke kelas itu.
                            </div>
                        </div>
                        <div class="guide-item">
                            <div class="guide-num">3</div>
                            <div class="guide-text">
                                Untuk file <strong>CSV</strong>, simpan dengan encoding <strong>UTF-8</strong> dan tulis baris header kelas
                                (mis. <strong>X TKJ 1</strong>) di atas kelompok siswanya. Pemisah boleh <strong>koma</strong> atau <strong>titik-koma</strong>.
                            </div>
                        </div>
                        <div class="guide-item">
                            <div class="guide-num">4</div>
                            <div class="guide-text">
                                Kolom wajib per baris: <strong>NO</strong> • <strong>NISN</strong> (10 digit angka) •
                                <strong>NAMA</strong> • <strong>NIS/NISS</strong> • <strong>L/P</strong>.
                            </div>
                        </div>
                        <div class="guide-item">
                            <div class="guide-num">5</div>
                            <div class="guide-text">
                                Kelas hanya dikenali jika valid di sistem. Bila baris header kelas tidak terdeteksi,
                                gunakan dropdown <strong>Kelas Tujuan</strong> sebagai fallback.
                                Jika ada kelas pada file yang <strong class="text-danger">belum terdaftar</strong> di
                                Data Master Kelas, <strong>seluruh import dibatalkan</strong> — tidak ada data yang tersimpan sebagian.
                            </div>
                        </div>
                        <div class="guide-item">
                            <div class="guide-num">6</div>
                            <div class="guide-text">
                                Duplikat NISN akan <strong>di-update</strong>, bukan digandakan.
                                Baris yang tidak valid (NISN salah, tanpa kelas aktif) akan dilewati dan dilaporkan.
                            </div>
                        </div>
                    </div>
                </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ====================================================== --}}
    {{-- PANEL: IMPORT DATA GURU                                --}}
    {{-- ====================================================== --}}
    <div class="tab-pane-item" id="tab-guru" role="tabpanel">
        <div class="row g-4">
            {{-- Kolom Kiri: Form Upload --}}
            <div class="col-lg-7">
                <div class="card-import">
                    <div class="card-import-header d-flex align-items-center gap-3">
                        <div style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#f59e0b,#d97706);display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-person-vcard-fill text-white" style="font-size:1.1rem;"></i>
                        </div>
                        <div>
                            <h5 class="card-import-title mb-0">Import Master Guru</h5>
                            <p class="mb-0" style="font-size:0.78rem;color:#64748b;">Format: NIP • NAMA GURU • STATUS (.xlsx / .xls / .csv)</p>
                        </div>
                    </div>

                    <div class="card-import-body">
                        <form action="{{ route('import.guru') }}" method="POST" enctype="multipart/form-data" id="formImportGuru">
                            @csrf

                            <div class="mb-4">
                                <label for="fileGuruImport" class="form-label fw-semibold" style="font-size:0.875rem;color:#374151;">File Guru <span class="text-danger">*</span></label>
                                <div class="dropzone-import" id="dropzoneGuru">
                                    <i class="bi bi-cloud-arrow-up dz-icon"></i>
                                    <div class="dz-title">Klik untuk memilih file, atau seret ke sini</div>
                                    <div class="dz-sub">Format: .xlsx, .xls, .csv (maks. 10 MB)</div>
                                    <input type="file"
                                           class="d-none @error('file_guru') is-invalid @enderror"
                                           id="fileGuruImport"
                                           name="file_guru"
                                           accept=".xlsx,.xls,.csv,.txt"
                                           required>
                                </div>

                                <div class="file-selected d-none mt-3" id="fileGuruSelectedInfo">
                                    <i class="bi bi-file-earmark-check-fill"></i>
                                    <div>
                                        <div class="file-selected-name" id="fileGuruSelectedName"></div>
                                        <div class="file-selected-size" id="fileGuruSelectedSize"></div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-light border ms-auto" id="btnRemoveGuruFile" title="Ganti file">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>

                                @error('file_guru')
                                    <div class="text-danger mt-2" style="font-size:0.8rem;">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="d-flex justify-content-end gap-2">
<button type="submit" id="btnSubmitImportGuru"
                                        class="btn btn-success rounded-3 px-4 py-2 fw-semibold d-flex align-items-center gap-2"
                                        style="font-size:0.875rem;">
                                    <i class="bi bi-upload"></i>
                                    <span>Unggah & Import</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Info jumlah guru saat ini --}}
                <div class="card-import mt-4">
                    <div class="card-import-body d-flex align-items-center gap-3">
                        <div style="width:44px;height:44px;border-radius:12px;background:#fffbeb;display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-person-vcard-fill text-warning" style="font-size:1.2rem;"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-dark" style="font-size:1rem;">{{ number_format($totalGuru ?? 0) }} guru terdaftar</div>
                            <div style="font-size:0.8rem;color:#64748b;">Import dengan NIP yang sama akan me-update data yang sudah ada (bukan duplikat).</div>
                        </div>
                    </div>
                </div>

                {{-- Zona Berbahaya: Reset Data Guru --}}
                <div class="card-import mt-4" style="border-color:#fecaca;background:#fff7f7;">
                    <div class="card-import-header d-flex align-items-center gap-3" style="border-color:#fee2e2;">
                        <div style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#ef4444,#b91c1c);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="bi bi-trash3-fill text-white"></i>
                        </div>
                        <div>
                            <h5 class="card-import-title mb-0">Zona Berbahaya — Reset Data</h5>
                            <p class="mb-0" style="font-size:0.78rem;color:#b91c1c;">Hapus MASAL &amp; permanen. Tidak dapat dikembalikan.</p>
                        </div>
                    </div>
                    <div class="card-import-body">
                        <p class="mb-3" style="font-size:0.85rem;color:#7f1d1d;">
                            Menghapus <strong>seluruh akun guru</strong> pada konteks data ini, termasuk <strong>jadwal pelajaran,
                            jadwal piket, jurnal mengajar, izin guru, presensi piket, dan status kehadiran</strong> yang menyandang guru tersebut.
                        </p>
                        <button type="button"
                                class="btn btn-danger rounded-3 px-4 py-2 fw-semibold d-inline-flex align-items-center gap-2"
                                style="font-size:0.875rem;"
                                data-reset-route="{{ route('import.reset-guru') }}"
                                data-reset-label="seluruh data Guru"
                                data-reset-phrase="HAPUS DATA GURU"
                                data-bs-toggle="modal"
                                data-bs-target="#modalResetData">
                            <i class="bi bi-trash3"></i>
                            <span>Reset / Hapus Semua Data Guru</span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Kolom Kanan: Panduan & Template --}}
            <div class="col-lg-5">
                <div class="card-import">
                    <div class="card-import-header d-flex align-items-center gap-3">
                        <div style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#22c55e,#16a34a);display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-file-earmark-spreadsheet text-white" style="font-size:1.1rem;"></i>
                        </div>
                        <div>
                            <h5 class="card-import-title mb-0">Unduh Template Master Guru</h5>
                            <p class="mb-0" style="font-size:0.78rem;color:#64748b;">Gunakan format ekspor Data Guru yang sudah bersih.</p>
                        </div>
                    </div>

                    <div class="card-import-body">
                        <div class="d-grid gap-2 mb-2">
                            <a href="{{ route('guru.export', ['format' => 'xlsx']) }}" class="btn btn-outline-success rounded-3 fw-semibold text-start" style="font-size:0.875rem;">
                                <i class="bi bi-file-earmark-excel me-2"></i> Unduh Template Excel (.xlsx)
                            </a>
                            <a href="{{ route('guru.export', ['format' => 'csv']) }}" class="btn btn-outline-info rounded-3 fw-semibold text-start" style="font-size:0.875rem;">
                                <i class="bi bi-filetype-csv me-2"></i> Unduh Template CSV (.csv)
                            </a>
                        </div>
                        <div class="guide-item">
                            <div class="guide-num">1</div>
                            <div class="guide-text">
                                File berkolom: <strong>NO</strong> • <strong>NIP</strong> • <strong>NAMA GURU</strong> • <strong>STATUS</strong>.
                                Opsional: <strong>PERAN</strong> • <strong>WALI KELAS</strong> • <strong>NO HP</strong>. Kolom <strong>NO</strong> diabaikan.
                            </div>
                        </div>
                        <div class="guide-item">
                            <div class="guide-num">2</div>
                            <div class="guide-text">
                                <strong>NIP</strong> adalah kunci utama: NIP yang <strong>sudah ada</strong> akan di-<strong>update</strong>,
                                NIP baru dibuat sebagai akun guru. Username akun = <strong>NIP tanpa spasi</strong>,
                                password default = <strong>USERNAME123</strong> (hanya untuk akun baru).
                            </div>
                        </div>
                        <div class="guide-item">
                            <div class="guide-num">3</div>
                            <div class="guide-text">
                                Kolom <strong>PERAN</strong> opsional: <strong>Guru</strong> / <strong>Wali Kelas</strong> → akun role guru;
                                <strong>Kepsek</strong> → baris <strong>dilewati</strong> (tidak diproses).
                                Kolom <strong>STATUS</strong>: <strong>Aktif</strong> / <strong>Nonaktif</strong> (default <strong>Aktif</strong>).
                            </div>
                        </div>
                        <div class="guide-item">
                            <div class="guide-num">4</div>
                            <div class="guide-text">
                                Wali Kelas: isi <strong>PERAN = "Wali Kelas"</strong> + kolom <strong>WALI KELAS</strong> berisi nama kelas
                                (mis. <strong>X TKJ 1</strong>) → relasi wali kelas otomatis dipetakan ke Data Kelas.
                                Mapel diampu tetap dibaca dinamis dari Jadwal Pelajaran.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ====================================================== --}}
    {{-- PANEL: IMPORT KELAS / JURUSAN                          --}}
    {{-- ====================================================== --}}
    <div class="tab-pane-item" id="tab-kelas-jurusan" role="tabpanel">
        <div class="row g-4">
            {{-- Kolom Kiri: Upah dua form upload terpisah --}}
            <div class="col-lg-7">
                {{-- Form Import Jurusan --}}
                <div class="card-import mb-4">
                    <div class="card-import-header d-flex align-items-center gap-3">
                        <div style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#8b5cf6,#6d28d9);display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-diagram-3-fill text-white" style="font-size:1.1rem;"></i>
                        </div>
                        <div>
                            <h5 class="card-import-title mb-0">Import Master Jurusan</h5>
                            <p class="mb-0" style="font-size:0.78rem;color:#64748b;">Format: KODE JURUSAN • NAMA JURUSAN (.xlsx / .xls / .csv)</p>
                        </div>
                    </div>

                    <div class="card-import-body">
                        <form action="{{ route('jurusan.import') }}" method="POST" enctype="multipart/form-data" id="formImportJurusan">
                            @csrf

                            <div class="mb-4">
                                <label for="fileJurusanImport" class="form-label fw-semibold" style="font-size:0.875rem;color:#374151;">File Jurusan <span class="text-danger">*</span></label>
                                <div class="dropzone-import" id="dropzoneJurusan">
                                    <i class="bi bi-cloud-arrow-up dz-icon"></i>
                                    <div class="dz-title">Klik untuk memilih file, atau seret ke sini</div>
                                    <div class="dz-sub">Format: .xlsx, .xls, .csv (maks. 10 MB)</div>
                                    <input type="file"
                                           class="d-none @error('file_jurusan') is-invalid @enderror"
                                           id="fileJurusanImport"
                                           name="file_jurusan"
                                           accept=".xlsx,.xls,.csv,.txt"
                                           required>
                                </div>

                                <div class="file-selected d-none mt-3" id="fileJurusanSelectedInfo">
                                    <i class="bi bi-file-earmark-check-fill"></i>
                                    <div>
                                        <div class="file-selected-name" id="fileJurusanSelectedName"></div>
                                        <div class="file-selected-size" id="fileJurusanSelectedSize"></div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-light border ms-auto" id="btnRemoveJurusanFile" title="Ganti file">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>

                                @error('file_jurusan')
                                    <div class="text-danger mt-2" style="font-size:0.8rem;">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <button type="submit" id="btnSubmitImportJurusan"
                                        class="btn btn-success rounded-3 px-4 py-2 fw-semibold d-flex align-items-center gap-2"
                                        style="font-size:0.875rem;">
                                    <i class="bi bi-upload"></i>
                                    <span>Mulai Import</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Form Import Kelas --}}
                <div class="card-import mb-4">
                    <div class="card-import-header d-flex align-items-center gap-3">
                        <div style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-collection-fill text-white" style="font-size:1.1rem;"></i>
                        </div>
                        <div>
                            <h5 class="card-import-title mb-0">Import Master Kelas</h5>
                            <p class="mb-0" style="font-size:0.78rem;color:#64748b;">Format: NAMA KELAS • TINGKAT • JURUSAN (.xlsx / .xls / .csv)</p>
                        </div>
                    </div>

                    <div class="card-import-body">
                        <form action="{{ route('import.kelas') }}" method="POST" enctype="multipart/form-data" id="formImportKelas">
                            @csrf

                            <div class="mb-4">
                                <label for="fileKelasImport" class="form-label fw-semibold" style="font-size:0.875rem;color:#374151;">File Kelas <span class="text-danger">*</span></label>
                                <div class="dropzone-import" id="dropzoneKelas">
                                    <i class="bi bi-cloud-arrow-up dz-icon"></i>
                                    <div class="dz-title">Klik untuk memilih file, atau seret ke sini</div>
                                    <div class="dz-sub">Format: .xlsx, .xls, .csv (maks. 10 MB)</div>
                                    <input type="file"
                                           class="d-none @error('file_kelas') is-invalid @enderror"
                                           id="fileKelasImport"
                                           name="file_kelas"
                                           accept=".xlsx,.xls,.csv,.txt"
                                           required>
                                </div>

                                <div class="file-selected d-none mt-3" id="fileKelasSelectedInfo">
                                    <i class="bi bi-file-earmark-check-fill"></i>
                                    <div>
                                        <div class="file-selected-name" id="fileKelasSelectedName"></div>
                                        <div class="file-selected-size" id="fileKelasSelectedSize"></div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-light border ms-auto" id="btnRemoveKelasFile" title="Ganti file">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>

                                @error('file_kelas')
                                    <div class="text-danger mt-2" style="font-size:0.8rem;">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <button type="submit" id="btnSubmitImportKelas"
                                        class="btn btn-success rounded-3 px-4 py-2 fw-semibold d-flex align-items-center gap-2"
                                        style="font-size:0.875rem;">
                                    <i class="bi bi-upload"></i>
                                    <span>Mulai Import</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Zona Berbahaya: Reset Data Kelas / Jurusan --}}
                <div class="card-import mb-4" style="border-color:#fecaca;background:#fff7f7;">
                    <div class="card-import-header d-flex align-items-center gap-3" style="border-color:#fee2e2;">
                        <div style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#ef4444,#b91c1c);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="bi bi-trash3-fill text-white"></i>
                        </div>
                        <div>
                            <h5 class="card-import-title mb-0">Zona Berbahaya — Reset Data</h5>
                            <p class="mb-0" style="font-size:0.78rem;color:#b91c1c;">Hapus MASAL &amp; permanen. Tidak dapat dikembalikan.</p>
                        </div>
                    </div>
                    <div class="card-import-body">
                        <p class="mb-3" style="font-size:0.85rem;color:#7f1d1d;">
                            Menghapus <strong>seluruh kelas &amp; jurusan</strong> pada konteks data ini. Karena siswa &amp; jadwal
                            pelajaran terikat pada kelas, <strong>siswa, jadwal pelajaran, jurnal, dan presensi</strong> pada konteks
                            tersebut <strong>ikut terhapus</strong>.
                        </p>
                        <button type="button"
                                class="btn btn-danger rounded-3 px-4 py-2 fw-semibold d-inline-flex align-items-center gap-2"
                                style="font-size:0.875rem;"
                                data-reset-route="{{ route('import.reset-kelas-jurusan') }}"
                                data-reset-label="seluruh data Kelas &amp; Jurusan"
                                data-reset-phrase="HAPUS DATA KELAS JURUSAN"
                                data-bs-toggle="modal"
                                data-bs-target="#modalResetData">
                            <i class="bi bi-trash3"></i>
                            <span>Reset / Hapus Semua Data Kelas &amp; Jurusan</span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Kolom Kanan: Panduan & Template --}}
            <div class="col-lg-5">
                <div class="card-import mb-4">
                    <div class="card-import-header d-flex align-items-center gap-3">
                        <div style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#22c55e,#16a34a);display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-file-earmark-spreadsheet text-white" style="font-size:1.1rem;"></i>
                        </div>
                        <div>
                            <h5 class="card-import-title mb-0">Unduh Template Master Jurusan</h5>
                            <p class="mb-0" style="font-size:0.78rem;color:#64748b;">Gunakan format ekspor Data Jurusan yang sudah bersih.</p>
                        </div>
                    </div>

                    <div class="card-import-body">
                        <div class="d-grid gap-2 mb-2">
                            <a href="{{ route('jurusan.export', ['format' => 'xlsx']) }}" class="btn btn-outline-success rounded-3 fw-semibold text-start" style="font-size:0.875rem;">
                                <i class="bi bi-file-earmark-excel me-2"></i> Unduh Template Excel (.xlsx)
                            </a>
                            <a href="{{ route('jurusan.export', ['format' => 'csv']) }}" class="btn btn-outline-info rounded-3 fw-semibold text-start" style="font-size:0.875rem;">
                                <i class="bi bi-filetype-csv me-2"></i> Unduh Template CSV (.csv)
                            </a>
                        </div>
                        <div class="guide-item">
                            <div class="guide-num">1</div>
                            <div class="guide-text">
                                File berkolom: <strong>NO</strong> • <strong>KODE JURUSAN</strong> • <strong>NAMA JURUSAN</strong>.
                                Kolom <strong>NO</strong> diabaikan.
                            </div>
                        </div>
                        <div class="guide-item">
                            <div class="guide-num">2</div>
                            <div class="guide-text">
                                Kode jurusan yang <strong>sudah ada</strong> akan di-<strong>update</strong> nama jurusannya; yang baru akan dibuat.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-import">
                    <div class="card-import-header d-flex align-items-center gap-3">
                        <div style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#1677ff,#0958d9);display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-download text-white" style="font-size:1.1rem;"></i>
                        </div>
                        <div>
                            <h5 class="card-import-title mb-0">Unduh Template &amp; Panduan Kelas</h5>
                            <p class="mb-0" style="font-size:0.78rem;color:#64748b;">Format ekspor Data Kelas yang sudah bersih.</p>
                        </div>
                    </div>

                    <div class="card-import-body">
                        <div class="d-grid gap-2 mb-2">
                            <a href="{{ route('kelas.export', ['format' => 'xlsx']) }}" class="btn btn-outline-success rounded-3 fw-semibold text-start" style="font-size:0.875rem;">
                                <i class="bi bi-file-earmark-excel me-2"></i> Unduh Template Excel (.xlsx)
                            </a>
                            <a href="{{ route('kelas.export', ['format' => 'csv']) }}" class="btn btn-outline-info rounded-3 fw-semibold text-start" style="font-size:0.875rem;">
                                <i class="bi bi-filetype-csv me-2"></i> Unduh Template CSV (.csv)
                            </a>
                        </div>
                        <div class="guide-item">
                            <div class="guide-num">1</div>
                            <div class="guide-text">
                                File berkolom: <strong>NO</strong> • <strong>NAMA KELAS</strong> • <strong>TINGKAT</strong> • <strong>JURUSAN</strong>.
                            </div>
                        </div>
                        <div class="guide-item">
                            <div class="guide-num">2</div>
                            <div class="guide-text">
                                Isi <strong>NAMA KELAS</strong> (mis. <em>RPL 1</em>), <strong>TINGKAT</strong> (X / XI / XII), dan
                                <strong>JURUSAN</strong> (nama atau kode jurusan).
                            </div>
                        </div>
                        <div class="guide-item">
                            <div class="guide-num">3</div>
                            <div class="guide-text">
                                Jika jurusan di file <strong>belum terdaftar</strong>, sistem <strong>membuat jurusan baru</strong> secara otomatis sebelum membuat kelas.
                            </div>
                        </div>
                        <div class="guide-item">
                            <div class="guide-num">4</div>
                            <div class="guide-text">
                                Kelas dengan <strong>nama yang sama</strong> akan dilewati — tidak dibuat duplikat.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ====================================================== --}}
    {{-- PANEL: IMPORT DATA RUANGAN                             --}}
    {{-- ====================================================== --}}
    <div class="tab-pane-item" id="tab-ruangan" role="tabpanel">
        <div class="row g-4">
            {{-- Kolom Kiri: Form Upload --}}
            <div class="col-lg-7">
                <div class="card-import">
                    <div class="card-import-header d-flex align-items-center gap-3">
                        <div style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#0ea5e9,#0284c7);display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-building-fill text-white" style="font-size:1.1rem;"></i>
                        </div>
                        <div>
                            <h5 class="card-import-title mb-0">Import Master Ruangan</h5>
                            <p class="mb-0" style="font-size:0.78rem;color:#64748b;">Format: KODE RUANGAN • NAMA RUANGAN • LOKASI / GEDUNG (.xlsx / .xls / .csv)</p>
                        </div>
                    </div>

                    <div class="card-import-body">
                        <form action="{{ route('import.ruangan') }}" method="POST" enctype="multipart/form-data" id="formImportRuangan">
                            @csrf

                            <div class="mb-4">
                                <label for="fileRuanganImport" class="form-label fw-semibold" style="font-size:0.875rem;color:#374151;">File Ruangan <span class="text-danger">*</span></label>
                                <div class="dropzone-import" id="dropzoneRuangan">
                                    <i class="bi bi-cloud-arrow-up dz-icon"></i>
                                    <div class="dz-title">Klik untuk memilih file, atau seret ke sini</div>
                                    <div class="dz-sub">Format: .xlsx, .xls, .csv (maks. 10 MB)</div>
                                    <input type="file"
                                           class="d-none @error('file_ruangan') is-invalid @enderror"
                                           id="fileRuanganImport"
                                           name="file_ruangan"
                                           accept=".xlsx,.xls,.csv,.txt"
                                           required>
                                </div>

                                <div class="file-selected d-none mt-3" id="fileRuanganSelectedInfo">
                                    <i class="bi bi-file-earmark-check-fill"></i>
                                    <div>
                                        <div class="file-selected-name" id="fileRuanganSelectedName"></div>
                                        <div class="file-selected-size" id="fileRuanganSelectedSize"></div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-light border ms-auto" id="btnRemoveRuanganFile" title="Ganti file">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>

                                @error('file_ruangan')
                                    <div class="text-danger mt-2" style="font-size:0.8rem;">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="d-flex justify-content-end gap-2">
<button type="submit" id="btnSubmitImportRuangan"
                                        class="btn btn-success rounded-3 px-4 py-2 fw-semibold d-flex align-items-center gap-2"
                                        style="font-size:0.875rem;">
                                    <i class="bi bi-upload"></i>
                                    <span>Unggah & Import</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Zona Berbahaya: Reset Data Ruangan --}}
                <div class="card-import mt-4" style="border-color:#fecaca;background:#fff7f7;">
                    <div class="card-import-header d-flex align-items-center gap-3" style="border-color:#fee2e2;">
                        <div style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#ef4444,#b91c1c);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="bi bi-trash3-fill text-white"></i>
                        </div>
                        <div>
                            <h5 class="card-import-title mb-0">Zona Berbahaya — Reset Data</h5>
                            <p class="mb-0" style="font-size:0.78rem;color:#b91c1c;">Hapus MASAL &amp; permanen. Tidak dapat dikembalikan.</p>
                        </div>
                    </div>
                    <div class="card-import-body">
                        <p class="mb-3" style="font-size:0.85rem;color:#7f1d1d;">
                            Menghapus <strong>seluruh data ruangan</strong> pada konteks data ini beserta <strong>pengurus ruangan</strong>.
                            Referensi ruangan pada <strong>jadwal pelajaran</strong> dikosongkan (jadwal tetap tersimpan).
                        </p>
                        <button type="button"
                                class="btn btn-danger rounded-3 px-4 py-2 fw-semibold d-inline-flex align-items-center gap-2"
                                style="font-size:0.875rem;"
                                data-reset-route="{{ route('import.reset-ruangan') }}"
                                data-reset-label="seluruh data Ruangan"
                                data-reset-phrase="HAPUS DATA RUANGAN"
                                data-bs-toggle="modal"
                                data-bs-target="#modalResetData">
                            <i class="bi bi-trash3"></i>
                            <span>Reset / Hapus Semua Data Ruangan</span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Kolom Kanan: Panduan & Template --}}
            <div class="col-lg-5">
                <div class="card-import">
                    <div class="card-import-header d-flex align-items-center gap-3">
                        <div style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#22c55e,#16a34a);display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-file-earmark-spreadsheet text-white" style="font-size:1.1rem;"></i>
                        </div>
                        <div>
                            <h5 class="card-import-title mb-0">Unduh Template Master Ruangan</h5>
                            <p class="mb-0" style="font-size:0.78rem;color:#64748b;">Gunakan format ekspor Data Ruangan yang sudah bersih.</p>
                        </div>
                    </div>

                    <div class="card-import-body">
                        <div class="d-grid gap-2 mb-2">
                            <a href="{{ route('ruangan.export', ['format' => 'xlsx']) }}" class="btn btn-outline-success rounded-3 fw-semibold text-start" style="font-size:0.875rem;">
                                <i class="bi bi-file-earmark-excel me-2"></i> Unduh Template Excel (.xlsx)
                            </a>
                            <a href="{{ route('ruangan.export', ['format' => 'csv']) }}" class="btn btn-outline-info rounded-3 fw-semibold text-start" style="font-size:0.875rem;">
                                <i class="bi bi-filetype-csv me-2"></i> Unduh Template CSV (.csv)
                            </a>
                        </div>
                        <div class="guide-item">
                            <div class="guide-num">1</div>
                            <div class="guide-text">
                                File berkolom: <strong>NO</strong> • <strong>KODE RUANGAN</strong> • <strong>NAMA RUANGAN</strong> • <strong>LOKASI / GEDUNG</strong>.
                                Kolom <strong>NO</strong> diabaikan.
                            </div>
                        </div>
                        <div class="guide-item">
                            <div class="guide-num">2</div>
                            <div class="guide-text">
                                Isi <strong>KODE RUANGAN</strong> (mis. <em>R-101</em>), <strong>NAMA RUANGAN</strong> (mis. <em>Ruang Kelas 101</em>),
                                dan <strong>LOKASI / GEDUNG</strong> (opsional, mis. <em>Gedung A Lantai 1</em>).
                            </div>
                        </div>
                        <div class="guide-item">
                            <div class="guide-num">3</div>
                            <div class="guide-text">
                                Baris dengan <strong>KODE RUANGAN kosong</strong> akan <strong>diabaikan</strong>.
                            </div>
                        </div>
                        <div class="guide-item">
                            <div class="guide-num">4</div>
                            <div class="guide-text">
                                Kode ruangan yang <strong>sudah ada</strong> akan di-<strong>update</strong>; yang baru akan dibuat.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ====================================================== --}}
    {{-- PANEL: IMPORT JADWAL PELAJARAN                        --}}
    {{-- ====================================================== --}}
    <div class="tab-pane-item" id="tab-jadwal" role="tabpanel">
        <div class="row g-4">
            {{-- Kolom Kiri: Form Upload --}}
            <div class="col-lg-7">
                <div class="card-import">
                    <div class="card-import-header d-flex align-items-center gap-3">
                        <div style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#06b6d4,#0891b2);display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-calendar-week-fill text-white" style="font-size:1.1rem;"></i>
                        </div>
                        <div>
                            <h5 class="card-import-title mb-0">Import Jadwal Pelajaran</h5>
                            <p class="mb-0" style="font-size:0.78rem;color:#64748b;">Format: Kelas • Hari • Jam • MataPelajaran • Guru • Ruang (.xlsx / .csv)</p>
                        </div>
                    </div>

                    <div class="card-import-body">
                        <form action="{{ route('import.jadwal') }}" method="POST" enctype="multipart/form-data" id="formImportJadwal">
                            @csrf

                            <div class="mb-4">
                                <label for="fileJadwalImport" class="form-label fw-semibold" style="font-size:0.875rem;color:#374151;">File Jadwal <span class="text-danger">*</span></label>
                                <div class="dropzone-import" id="dropzoneJadwal">
                                    <i class="bi bi-cloud-arrow-up dz-icon"></i>
                                    <div class="dz-title">Klik untuk memilih file, atau seret ke sini</div>
                                    <div class="dz-sub">Format: .xlsx, .xls, .csv (maks. 10 MB)</div>
                                    <input type="file"
                                           class="d-none @error('file_jadwal') is-invalid @enderror"
                                           id="fileJadwalImport"
                                           name="file_jadwal"
                                           accept=".xlsx,.xls,.csv,.txt"
                                           required>
                                </div>

                                <div class="file-selected d-none mt-3" id="fileJadwalSelectedInfo">
                                    <i class="bi bi-file-earmark-check-fill"></i>
                                    <div>
                                        <div class="file-selected-name" id="fileJadwalSelectedName"></div>
                                        <div class="file-selected-size" id="fileJadwalSelectedSize"></div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-light border ms-auto" id="btnRemoveJadwalFile" title="Ganti file">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>

                                @error('file_jadwal')
                                    <div class="text-danger mt-2" style="font-size:0.8rem;">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <button type="submit" id="btnSubmitImportJadwal"
                                        class="btn rounded-3 px-4 py-2 fw-semibold d-flex align-items-center gap-2"
                                        style="font-size:0.875rem;background:linear-gradient(135deg,#06b6d4,#0891b2);color:#fff;"
                                        disabled>
                                    <i class="bi bi-upload"></i>
                                    <span>Unggah &amp; Import</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Info jumlah jadwal saat ini --}}
                <div class="card-import mt-4">
                    <div class="card-import-body d-flex align-items-center gap-3">
                        <div style="width:44px;height:44px;border-radius:12px;background:#ecfeff;display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-calendar-week-fill" style="font-size:1.2rem;color:#06b6d4;"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-dark" style="font-size:1rem;">{{ number_format($totalJadwal ?? 0) }} slot jadwal terdaftar</div>
                            <div style="font-size:0.8rem;color:#64748b;">Slot yang sama (Kelas + Hari + Jam) akan di-update, bukan digandakan.</div>
                        </div>
                    </div>
                </div>

                {{-- Zona Berbahaya: Reset Data Jadwal --}}
                <div class="card-import mt-4" style="border-color:#fecaca;background:#fff7f7;">
                    <div class="card-import-header d-flex align-items-center gap-3" style="border-color:#fee2e2;">
                        <div style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#ef4444,#b91c1c);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="bi bi-trash3-fill text-white"></i>
                        </div>
                        <div>
                            <h5 class="card-import-title mb-0">Zona Berbahaya — Reset Data</h5>
                            <p class="mb-0" style="font-size:0.78rem;color:#b91c1c;">Hapus MASAL &amp; permanen. Tidak dapat dikembalikan.</p>
                        </div>
                    </div>
                    <div class="card-import-body">
                        <p class="mb-3" style="font-size:0.85rem;color:#7f1d1d;">
                            Menghapus <strong>seluruh slot jadwal pelajaran</strong> pada konteks data ini.
                            <strong>Jurnal mengajar yang sudah dibuat tidak ikut terhapus</strong>, namun kehilangan referensi jadwalnya.
                            Gunakan hanya saat akan import ulang plotting jadwal dari awal.
                        </p>
                        <button type="button"
                                class="btn btn-danger rounded-3 px-4 py-2 fw-semibold d-inline-flex align-items-center gap-2"
                                style="font-size:0.875rem;"
                                data-reset-route="{{ route('import.reset-jadwal') }}"
                                data-reset-label="seluruh Jadwal Pelajaran"
                                data-reset-phrase="HAPUS DATA JADWAL"
                                data-bs-toggle="modal"
                                data-bs-target="#modalResetData">
                            <i class="bi bi-trash3"></i>
                            <span>Reset / Hapus Semua Jadwal Pelajaran</span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Kolom Kanan: Panduan Format --}}
            <div class="col-lg-5">
                <div class="card-import">
                    <div class="card-import-header d-flex align-items-center gap-3">
                        <div style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#1677ff,#0958d9);display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-info-circle-fill text-white" style="font-size:1.1rem;"></i>
                        </div>
                        <div>
                            <h5 class="card-import-title mb-0">Panduan Format Jadwal</h5>
                            <p class="mb-0" style="font-size:0.78rem;color:#64748b;">Ikuti format kolom berikut agar import berjalan sempurna.</p>
                        </div>
                    </div>

                    <div class="card-import-body">
                        <div class="d-grid gap-2 mb-3">
                            <a href="{{ route('import.template-jadwal') }}" class="btn btn-outline-info rounded-3 fw-semibold text-start" style="font-size:0.875rem;">
                                <i class="bi bi-filetype-csv me-2"></i> Unduh Template CSV (.csv)
                            </a>
                            <div class="dropdown">
                                <button class="btn btn-outline-success rounded-3 fw-semibold text-start w-100 dropdown-toggle" type="button" data-bs-toggle="dropdown" style="font-size:0.875rem;">
                                    <i class="bi bi-download me-2"></i> Export Jadwal Saat Ini
                                </button>
                                <ul class="dropdown-menu w-100 shadow-sm">
                                    <li><a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('admin.jadwal.export', ['format' => 'xlsx']) }}"><i class="bi bi-file-earmark-excel text-success"></i> Export Excel (.xlsx)</a></li>
                                    <li><a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('admin.jadwal.export', ['format' => 'csv']) }}"><i class="bi bi-filetype-csv text-info"></i> Export CSV (.csv)</a></li>
                                </ul>
                            </div>
                        </div>

                        {{-- Tabel contoh --}}
                        <div class="mb-4 rounded-3 overflow-hidden" style="border:1px solid #e2e8f0;">
                            <table class="table table-sm mb-0" style="font-size:0.78rem;">
                                <thead style="background:#f8fafc;">
                                    <tr>
                                        <th class="fw-bold text-muted px-2 py-2">Kolom</th>
                                        <th class="fw-bold text-muted px-2 py-2">Contoh Nilai</th>
                                        <th class="fw-bold text-muted px-2 py-2">Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td class="px-2"><code>Kelas</code></td><td class="px-2">X TKI 1</td><td class="px-2 text-danger fw-semibold">Wajib</td></tr>
                                    <tr><td class="px-2"><code>Hari</code></td><td class="px-2">Senin</td><td class="px-2 text-danger fw-semibold">Wajib</td></tr>
                                    <tr><td class="px-2"><code>Jam</code></td><td class="px-2">4.5.6 (atau 2-3)</td><td class="px-2 text-danger fw-semibold">Wajib</td></tr>
                                    <tr><td class="px-2"><code>MataPelajaran</code></td><td class="px-2">Bahasa Indonesia</td><td class="px-2 text-danger fw-semibold">Wajib</td></tr>
                                    <tr><td class="px-2"><code>Guru</code></td><td class="px-2">Yani, S.Pd.</td><td class="px-2 text-danger fw-semibold">Wajib</td></tr>
                                    <tr><td class="px-2"><code>Ruang</code></td><td class="px-2">Lab. KI 1</td><td class="px-2 text-muted">Opsional</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="guide-item">
                            <div class="guide-num">1</div>
                            <div class="guide-text">
                                Kolom <strong>Kelas</strong> wajib cocok dengan nama kelas di Data Master
                                (mis. <strong>X TKI 1</strong> atau <strong>TKI 1</strong>). Jika tidak ditemukan,
                                <strong class="text-danger">seluruh import dibatalkan</strong> — tidak ada data parsial yang tersimpan.
                            </div>
                        </div>
                        <div class="guide-item">
                            <div class="guide-num">2</div>
                            <div class="guide-text">
                                Kolom <strong>Jam</strong> dapat diisi tunggal (<code>1</code>), urutan bermata titik (<code>4.5.6</code>),
                                atau rentang strip (<code>2-3</code>). Setiap nomor jam akan di-mapping ke Master Jam Pelajaran secara otomatis.
                            </div>
                        </div>
                        <div class="guide-item">
                            <div class="guide-num">3</div>
                            <div class="guide-text">
                                Kolom <strong>Guru</strong> harus cocok persis dengan nama guru di Data Master.
                                Jika tidak ditemukan, baris tersebut <strong>dilewati + dicatat</strong> (baris lain tetap diproses).
                            </div>
                        </div>
                        <div class="guide-item">
                            <div class="guide-num">4</div>
                            <div class="guide-text">
                                <strong>Mata Pelajaran</strong> dan <strong>Ruangan</strong> yang belum ada di master akan
                                <strong>dibuat otomatis</strong> (auto-create).
                            </div>
                        </div>
                        <div class="guide-item">
                            <div class="guide-num">5</div>
                            <div class="guide-text">
                                Slot yang <strong>sudah ada</strong> (Kelas + Hari + Jam sama) akan <strong>di-update</strong>,
                                bukan digandakan. Import ulang aman dilakukan kapan saja.
                            </div>
                        </div>
                        <div class="guide-item">
                            <div class="guide-num">6</div>
                            <div class="guide-text">
                                Simpan file dengan encoding <strong>UTF-8</strong>. Pemisah kolom bisa
                                <strong>koma</strong> (<code>,</code>) atau <strong>titik koma</strong> (<code>;</code>) — terdeteksi otomatis.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

{{-- ====================================================== --}}
{{-- MODAL KONFIRMASI RESET / HAPUS MASAL                   --}}
{{-- ====================================================== --}}
<div class="modal fade" id="modalResetData" tabindex="-1" aria-labelledby="modalResetDataLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="POST" id="formResetData" action="" class="modal-content">
            @csrf
            <div class="modal-header" style="background:#fef2f2;border-bottom:1px solid #fee2e2;">
                <h5 class="modal-title fw-bold text-danger" id="modalResetDataLabel">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Konfirmasi Hapus Masal Data
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger border-0 rounded-3 d-flex align-items-start gap-2" style="background:#fef2f2;color:#7f1d1d;">
                    <i class="bi bi-exclamation-octagon-fill fs-5 mt-1"></i>
                    <div>
                        Anda akan menghapus <strong id="resetLabelText">seluruh data</strong> beserta seluruh data terkait secara
                        <strong>permanen</strong>. Tindakan ini <strong class="text-danger">tidak dapat dibatalkan</strong>.
                    </div>
                </div>
                <label for="resetConfirmInput" class="form-label fw-semibold" style="font-size:0.875rem;color:#374151;">
                    Ketik frasa konfirmasi <code id="resetPhraseText" class="text-danger fw-bold px-2 py-1" style="background:#fee2e2;border-radius:6px;">HAPUS DATA SISWA</code>
                    pada kolom di bawah untuk mengaktifkan tombol hapus:
                </label>
                <input type="text"
                       class="form-control form-control-lg @error('reset_confirm') is-invalid @enderror"
                       id="resetConfirmInput"
                       name="reset_confirm"
                       placeholder="Ketik frasa konfirmasi di sini…"
                       autocomplete="off"
                       autofocus
                       style="border-radius:10px;border:1px solid #fecaca;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;"
                       required>
                @error('reset_confirm')
                    <div class="text-danger mt-2" style="font-size:0.8rem;">{{ $message }}</div>
                @enderror
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border rounded-3 px-4 fw-semibold" data-bs-dismiss="modal">Batal</button>
                <button type="submit" id="btnConfirmReset" class="btn btn-danger rounded-3 px-4 fw-semibold d-inline-flex align-items-center gap-2" disabled>
                    <i class="bi bi-trash3-fill"></i> Ya, Hapus Semua Data
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // ── Tab switching ─────────────────────────────────────────────
    (function () {
        const tabs = document.querySelectorAll('.import-tab');

        function activateTab(targetId) {
            const target = document.getElementById(targetId);
            if (!target) return;

            document.querySelectorAll('.import-tab').forEach(function (t) {
                t.classList.remove('active');
                t.setAttribute('aria-selected', 'false');
            });
            document.querySelectorAll('.tab-pane-item').forEach(function (p) {
                p.classList.remove('active');
            });

            const btn = document.querySelector('[data-tab-target="' + targetId + '"]');
            if (btn) {
                btn.classList.add('active');
                btn.setAttribute('aria-selected', 'true');
            }
            target.classList.add('active');
        }

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                activateTab(this.dataset.tabTarget);
            });
        });

        // Restore tab dari session (setelah redirect import/reset)
        @if(session('active_tab'))
        activateTab('tab-{{ session('active_tab') }}');
        @endif
    })();

    // ── Dropzone-style file selection ─────────────────────────────
    (function () {
        const dropzone  = document.getElementById('dropzoneArea');
        const input     = document.getElementById('fileExcelImport');
        const btnSubmit = document.getElementById('btnSubmitImport');
        const infoBox   = document.getElementById('fileSelectedInfo');
        const nameEl    = document.getElementById('fileSelectedName');
        const sizeEl    = document.getElementById('fileSelectedSize');
        const btnRemove = document.getElementById('btnRemoveFile');

        if (!dropzone || !input) return;

        function openPicker() {
            input.click();
        }

        function formatSize(bytes) {
            if (!bytes) return '';
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
        }

        function showFile(file) {
            nameEl.textContent = file.name;
            sizeEl.textContent = 'Ukuran: ' + formatSize(file.size) + ' • Ekstensi: .' + (file.name.split('.').pop() || 'xlsx');
            infoBox.classList.remove('d-none');
            btnSubmit.disabled = false;
        }

        function clearFile() {
            input.value = '';
            infoBox.classList.add('d-none');
            btnSubmit.disabled = true;
        }

        dropzone.addEventListener('click', openPicker);
        input.addEventListener('change', function () {
            if (this.files && this.files[0]) showFile(this.files[0]);
        });

        ['dragenter', 'dragover'].forEach(evt => {
            dropzone.addEventListener(evt, e => {
                e.preventDefault();
                dropzone.classList.add('dragover');
            });
        });
        ['dragleave', 'drop'].forEach(evt => {
            dropzone.addEventListener(evt, e => {
                e.preventDefault();
                dropzone.classList.remove('dragover');
            });
        });
        dropzone.addEventListener('drop', e => {
            const files = e.dataTransfer.files;
            if (files && files[0]) {
                input.files = files;
                showFile(files[0]);
            }
        });

        btnRemove.addEventListener('click', clearFile);

        // Reset form juga bersihkan tampilan
        document.getElementById('formImportSiswa').addEventListener('reset', clearFile);
    })();

    // ── Dropzone untuk Import Kelas ────────────────────────────────
    (function () {
        const dropzone  = document.getElementById('dropzoneKelas');
        const input     = document.getElementById('fileKelasImport');
        const btnSubmit = document.getElementById('btnSubmitImportKelas');
        const infoBox   = document.getElementById('fileKelasSelectedInfo');
        const nameEl    = document.getElementById('fileKelasSelectedName');
        const sizeEl    = document.getElementById('fileKelasSelectedSize');
        const btnRemove = document.getElementById('btnRemoveKelasFile');

        if (!dropzone || !input) return;

        function formatSize(bytes) {
            if (!bytes) return '';
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
        }

        function showFile(file) {
            nameEl.textContent = file.name;
            sizeEl.textContent = 'Ukuran: ' + formatSize(file.size) + ' • Ekstensi: .' + (file.name.split('.').pop() || 'xlsx');
            infoBox.classList.remove('d-none');
            btnSubmit.disabled = false;
        }

        function clearFile() {
            input.value = '';
            infoBox.classList.add('d-none');
            btnSubmit.disabled = true;
        }

        dropzone.addEventListener('click', function () { input.click(); });
        input.addEventListener('change', function () {
            if (this.files && this.files[0]) showFile(this.files[0]);
        });

        ['dragenter', 'dragover'].forEach(evt => {
            dropzone.addEventListener(evt, e => {
                e.preventDefault();
                dropzone.classList.add('dragover');
            });
        });
        ['dragleave', 'drop'].forEach(evt => {
            dropzone.addEventListener(evt, e => {
                e.preventDefault();
                dropzone.classList.remove('dragover');
            });
        });
        dropzone.addEventListener('drop', e => {
            const files = e.dataTransfer.files;
            if (files && files[0]) {
                input.files = files;
                showFile(files[0]);
            }
        });

        btnRemove.addEventListener('click', clearFile);
        document.getElementById('formImportKelas').addEventListener('reset', clearFile);
    })();

    // ── Dropzone untuk Import Jurusan ───────────────────────────────
    (function () {
        const dropzone  = document.getElementById('dropzoneJurusan');
        const input     = document.getElementById('fileJurusanImport');
        const btnSubmit = document.getElementById('btnSubmitImportJurusan');
        const infoBox   = document.getElementById('fileJurusanSelectedInfo');
        const nameEl    = document.getElementById('fileJurusanSelectedName');
        const sizeEl    = document.getElementById('fileJurusanSelectedSize');
        const btnRemove = document.getElementById('btnRemoveJurusanFile');

        if (!dropzone || !input) return;

        function formatSize(bytes) {
            if (!bytes) return '';
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
        }

        function showFile(file) {
            nameEl.textContent = file.name;
            sizeEl.textContent = 'Ukuran: ' + formatSize(file.size) + ' • Ekstensi: .' + (file.name.split('.').pop() || 'xlsx');
            infoBox.classList.remove('d-none');
            btnSubmit.disabled = false;
        }

        function clearFile() {
            input.value = '';
            infoBox.classList.add('d-none');
            btnSubmit.disabled = true;
        }

        dropzone.addEventListener('click', function () { input.click(); });
        input.addEventListener('change', function () {
            if (this.files && this.files[0]) showFile(this.files[0]);
        });

        ['dragenter', 'dragover'].forEach(evt => {
            dropzone.addEventListener(evt, e => {
                e.preventDefault();
                dropzone.classList.add('dragover');
            });
        });
        ['dragleave', 'drop'].forEach(evt => {
            dropzone.addEventListener(evt, e => {
                e.preventDefault();
                dropzone.classList.remove('dragover');
            });
        });
        dropzone.addEventListener('drop', e => {
            const files = e.dataTransfer.files;
            if (files && files[0]) {
                input.files = files;
                showFile(files[0]);
            }
        });

        btnRemove.addEventListener('click', clearFile);
        document.getElementById('formImportJurusan').addEventListener('reset', clearFile);
    })();

    // ── Dropzone untuk Import Ruangan ───────────────────────────────
    (function () {
        const dropzone  = document.getElementById('dropzoneRuangan');
        const input     = document.getElementById('fileRuanganImport');
        const btnSubmit = document.getElementById('btnSubmitImportRuangan');
        const infoBox   = document.getElementById('fileRuanganSelectedInfo');
        const nameEl    = document.getElementById('fileRuanganSelectedName');
        const sizeEl    = document.getElementById('fileRuanganSelectedSize');
        const btnRemove = document.getElementById('btnRemoveRuanganFile');

        if (!dropzone || !input) return;

        function formatSize(bytes) {
            if (!bytes) return '';
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
        }

        function showFile(file) {
            nameEl.textContent = file.name;
            sizeEl.textContent = 'Ukuran: ' + formatSize(file.size) + ' • Ekstensi: .' + (file.name.split('.').pop() || 'xlsx');
            infoBox.classList.remove('d-none');
            btnSubmit.disabled = false;
        }

        function clearFile() {
            input.value = '';
            infoBox.classList.add('d-none');
            btnSubmit.disabled = true;
        }

        dropzone.addEventListener('click', function () { input.click(); });
        input.addEventListener('change', function () {
            if (this.files && this.files[0]) showFile(this.files[0]);
        });

        ['dragenter', 'dragover'].forEach(evt => {
            dropzone.addEventListener(evt, e => {
                e.preventDefault();
                dropzone.classList.add('dragover');
            });
        });
        ['dragleave', 'drop'].forEach(evt => {
            dropzone.addEventListener(evt, e => {
                e.preventDefault();
                dropzone.classList.remove('dragover');
            });
        });
        dropzone.addEventListener('drop', e => {
            const files = e.dataTransfer.files;
            if (files && files[0]) {
                input.files = files;
                showFile(files[0]);
            }
        });

        btnRemove.addEventListener('click', clearFile);
        document.getElementById('formImportRuangan').addEventListener('reset', clearFile);
    })();

    // ── Dropzone untuk Import Guru ─────────────────────────────────
    (function () {
        const dropzone  = document.getElementById('dropzoneGuru');
        const input     = document.getElementById('fileGuruImport');
        const btnSubmit = document.getElementById('btnSubmitImportGuru');
        const infoBox   = document.getElementById('fileGuruSelectedInfo');
        const nameEl    = document.getElementById('fileGuruSelectedName');
        const sizeEl    = document.getElementById('fileGuruSelectedSize');
        const btnRemove = document.getElementById('btnRemoveGuruFile');

        if (!dropzone || !input) return;

        function formatSize(bytes) {
            if (!bytes) return '';
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
        }

        function showFile(file) {
            nameEl.textContent = file.name;
            sizeEl.textContent = 'Ukuran: ' + formatSize(file.size) + ' • Ekstensi: .' + (file.name.split('.').pop() || 'xlsx');
            infoBox.classList.remove('d-none');
            btnSubmit.disabled = false;
        }

        function clearFile() {
            input.value = '';
            infoBox.classList.add('d-none');
            btnSubmit.disabled = true;
        }

        dropzone.addEventListener('click', function () { input.click(); });
        input.addEventListener('change', function () {
            if (this.files && this.files[0]) showFile(this.files[0]);
        });

        ['dragenter', 'dragover'].forEach(evt => {
            dropzone.addEventListener(evt, e => {
                e.preventDefault();
                dropzone.classList.add('dragover');
            });
        });
        ['dragleave', 'drop'].forEach(evt => {
            dropzone.addEventListener(evt, e => {
                e.preventDefault();
                dropzone.classList.remove('dragover');
            });
        });
        dropzone.addEventListener('drop', e => {
            const files = e.dataTransfer.files;
            if (files && files[0]) {
                input.files = files;
                showFile(files[0]);
            }
        });

        btnRemove.addEventListener('click', clearFile);
        document.getElementById('formImportGuru').addEventListener('reset', clearFile);
    })();

    // ── Dropzone: Import Jadwal Pelajaran ─────────────────────────
    (function () {
        const dropzone  = document.getElementById('dropzoneJadwal');
        const input     = document.getElementById('fileJadwalImport');
        const btnSubmit = document.getElementById('btnSubmitImportJadwal');
        const infoBox   = document.getElementById('fileJadwalSelectedInfo');
        const nameEl    = document.getElementById('fileJadwalSelectedName');
        const sizeEl    = document.getElementById('fileJadwalSelectedSize');
        const btnRemove = document.getElementById('btnRemoveJadwalFile');

        if (!dropzone || !input) return;

        function formatSize(bytes) {
            if (!bytes) return '';
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
        }

        function showFile(file) {
            nameEl.textContent = file.name;
            sizeEl.textContent = 'Ukuran: ' + formatSize(file.size) + ' • Ekstensi: .' + (file.name.split('.').pop() || 'csv');
            infoBox.classList.remove('d-none');
            btnSubmit.disabled = false;
        }

        function clearFile() {
            input.value = '';
            infoBox.classList.add('d-none');
            btnSubmit.disabled = true;
        }

        dropzone.addEventListener('click', function () { input.click(); });
        input.addEventListener('change', function () {
            if (this.files && this.files[0]) showFile(this.files[0]);
        });

        ['dragenter', 'dragover'].forEach(evt => {
            dropzone.addEventListener(evt, e => {
                e.preventDefault();
                dropzone.classList.add('dragover');
            });
        });
        ['dragleave', 'drop'].forEach(evt => {
            dropzone.addEventListener(evt, e => {
                e.preventDefault();
                dropzone.classList.remove('dragover');
            });
        });
        dropzone.addEventListener('drop', e => {
            const files = e.dataTransfer.files;
            if (files && files[0]) {
                input.files = files;
                showFile(files[0]);
            }
        });

        btnRemove.addEventListener('click', clearFile);
        document.getElementById('formImportJadwal').addEventListener('reset', clearFile);
    })();

    // ── Modal Konfirmasi Reset / Hapus Masal Data ────────────────────
    (function () {
        const modalEl  = document.getElementById('modalResetData');
        const form     = document.getElementById('formResetData');
        const input    = document.getElementById('resetConfirmInput');
        const btnReset = document.getElementById('btnConfirmReset');
        const lblText  = document.getElementById('resetLabelText');
        const phText   = document.getElementById('resetPhraseText');

        if (!modalEl || !form || !input || !btnReset) return;

        let expectedPhrase = '';

        function lockReset() {
            input.value = '';
            btnReset.disabled = true;
        }

        // Tombol "Reset / Hapus Semua Data ..." (di setiap tab) → isi modal.
        document.querySelectorAll('[data-reset-route]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                expectedPhrase = (btn.dataset.resetPhrase || '').toUpperCase();
                form.setAttribute('action', btn.dataset.resetRoute);
                lblText.textContent = btn.dataset.resetLabel || 'seluruh data';
                phText.textContent  = expectedPhrase;
                lockReset();
            });
        });

        // Tombol eksekusi hanya aktif saat frasa diketik persis (case-insensitive).
        input.addEventListener('input', function () {
            btnReset.disabled = this.value.trim().toUpperCase() !== expectedPhrase;
        });

        modalEl.addEventListener('hidden.bs.modal', lockReset);
    })();
</script>
@endpush