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
                            <h5 class="card-import-title mb-0">Upload File Excel</h5>
                            <p class="mb-0" style="font-size:0.78rem;color:#64748b;">Format: DAFTAR PRESENSI PESERTA DIDIK (.xlsx / .xls)</p>
                        </div>
                    </div>

                    <div class="card-import-body">
                        <form action="{{ route('import.siswa') }}" method="POST" enctype="multipart/form-data" id="formImportSiswa">
                            @csrf

                            {{-- Dropzone Upload --}}
                            <div class="mb-4">
                                <label for="fileExcelImport" class="form-label fw-semibold" style="font-size:0.875rem;color:#374151;">File Excel <span class="text-danger">*</span></label>
                                <div class="dropzone-import" id="dropzoneArea">
                                    <i class="bi bi-cloud-arrow-up dz-icon"></i>
                                    <div class="dz-title">Klik untuk memilih file, atau seret ke sini</div>
                                    <div class="dz-sub">Format: .xlsx, .xls (maks. 5 MB)</div>
                                    <input type="file"
                                           class="d-none @error('file_excel') is-invalid @enderror"
                                           id="fileExcelImport"
                                           name="file_excel"
                                           accept=".xlsx,.xls"
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
                                            {{ $kelas->tingkat }} • {{ $kelas->nama_kelas }}
                                            {{ $kelas->jurusan ? '(' . $kelas->jurusan->nama_jurusan . ')' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('id_kelas')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Aksi --}}
                            <div class="d-flex justify-content-end gap-2">
                                <button type="reset" class="btn btn-light border rounded-3 px-4 py-2 fw-semibold" style="font-size:0.875rem;">Reset</button>
                                <button type="submit" id="btnSubmitImport"
                                        class="btn btn-success rounded-3 px-4 py-2 fw-semibold d-flex align-items-center gap-2"
                                        style="font-size:0.875rem;" disabled>
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
            </div>

            {{-- Kolom Kanan: Panduan Format --}}
            <div class="col-lg-5">
                <div class="card-import">
                    <div class="card-import-header d-flex align-items-center gap-3">
                        <div style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#1677ff,#0958d9);display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-info-circle-fill text-white" style="font-size:1.1rem;"></i>
                        </div>
                        <div>
                            <h5 class="card-import-title mb-0">Panduan Format Excel</h5>
                            <p class="mb-0" style="font-size:0.78rem;color:#64748b;">Agar import berjalan lancar, ikuti pedoman berikut.</p>
                        </div>
                    </div>

                    <div class="card-import-body">
                        <div class="guide-item">
                            <div class="guide-num">1</div>
                            <div class="guide-text">
                                Gunakan file <strong>DAFTAR PRESENSI PESERTA DIDIK</strong> dengan beberapa sheet per tingkat
                                (contoh: <strong>KELAS X</strong>, <strong>KELAS XI</strong>, <strong>KELAS XII</strong>).
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
                                Kolom wajib: <strong>NO</strong> (kolom A) • <strong>NISN</strong> (kolom B, 10 digit angka) •
                                <strong>NAMA</strong> (kolom C) • <strong>NIS/NISS</strong> (kolom D) • <strong>L/P</strong> (kolom E).
                            </div>
                        </div>
                        <div class="guide-item">
                            <div class="guide-num">4</div>
                            <div class="guide-text">
                                Kelas hanya dikenali jika valid di sistem.
                                Teks selain nama kelas (mis. nama guru/wali) tidak akan membuat kelas baru.
                            </div>
                        </div>
                        <div class="guide-item">
                            <div class="guide-num">5</div>
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

    {{-- ====================================================== --}}
    {{-- PANEL: IMPORT DATA GURU (PLACEHOLDER)                  --}}
    {{-- ====================================================== --}}
    <div class="tab-pane-item" id="tab-guru" role="tabpanel">
        <div class="card-import">
            <div class="card-import-header d-flex align-items-center gap-3">
                <div style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#f59e0b,#d97706);display:flex;align-items:center;justify-content:center;">
                    <i class="bi bi-person-vcard-fill text-white" style="font-size:1.1rem;"></i>
                </div>
                <div>
                    <h5 class="card-import-title mb-0">Import Data Guru</h5>
                    <p class="mb-0" style="font-size:0.78rem;color:#64748b;">Penambahan guru dari file Excel secara massal.</p>
                </div>
            </div>
            <div class="card-import-body">
                <div class="placeholder-import">
                    <i class="bi bi-rocket-takeoff ph-icon"></i>
                    <div class="ph-title">Menyusul Segera</div>
                    <div class="ph-sub">Halaman import guru sedang dikembangkan. Gunakan form manual di menu Data Pengguna / Guru.</div>
                </div>
            </div>
        </div>
    </div>

    {{-- ====================================================== --}}
    {{-- PANEL: IMPORT KELAS / JURUSAN (PLACEHOLDER)            --}}
    {{-- ====================================================== --}}
    <div class="tab-pane-item" id="tab-kelas-jurusan" role="tabpanel">
        <div class="card-import">
            <div class="card-import-header d-flex align-items-center gap-3">
                <div style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#8b5cf6,#6d28d9);display:flex;align-items:center;justify-content:center;">
                    <i class="bi bi-diagram-3-fill text-white" style="font-size:1.1rem;"></i>
                </div>
                <div>
                    <h5 class="card-import-title mb-0">Import Kelas / Jurusan</h5>
                    <p class="mb-0" style="font-size:0.78rem;color:#64748b;">Penambahan rombel kelas dan jurusan dari file Excel secara massal.</p>
                </div>
            </div>
            <div class="card-import-body">
                <div class="placeholder-import">
                    <i class="bi bi-rocket-takeoff ph-icon"></i>
                    <div class="ph-title">Menyusul Segera</div>
                    <div class="ph-sub">Halaman import kelas/jurusan sedang dikembangkan. Gunakan menu Data Kelas &amp; Data Jurusan.</div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
    // ── Tab switching ─────────────────────────────────────────────
    (function () {
        const tabs = document.querySelectorAll('.import-tab');

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                const targetId = this.dataset.tabTarget;
                const target   = document.getElementById(targetId);
                if (!target) return;

                // Non-aktifkan semua tab & panel
                document.querySelectorAll('.import-tab').forEach(function (t) {
                    t.classList.remove('active');
                    t.setAttribute('aria-selected', 'false');
                });
                document.querySelectorAll('.tab-pane-item').forEach(function (p) {
                    p.classList.remove('active');
                });

                // Aktifkan tab & panel yang diklik
                this.classList.add('active');
                this.setAttribute('aria-selected', 'true');
                target.classList.add('active');
            });
        });
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

        // Drag & drop
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
</script>
@endpush