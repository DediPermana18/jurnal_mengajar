@extends('layouts.app')

@section('title', 'Master Jam Pelajaran Sekolah - WebJournal Management System')

@section('content')
@php
    // Param 'ta' (Tahun Ajaran & Semester) ikut dipertahankan pada tautan internal halaman.
    // Mode tampilan (Global / Multi-Shift) sepenuhnya ditentukan oleh mode_jadwal Tahun Ajaran
    // terpilih — tidak ada parameter 'mode' manual yang disertakan di tautan.
    $taParam = $selectedTahunAjaran ? ['ta' => $selectedTahunAjaran->id] : [];
    // Param dasar untuk tautan internal (tab + shift), tanpa TA.
    $baseLink = ['tab' => $tab] + ($selectedShiftId ? ['shift' => $selectedShiftId] : []);
@endphp
<div class="container-fluid px-0">

    {{-- Toast feedback (Auto-Save Jam Pulang & aksi simpan lainnya) --}}
    <div id="toastJamPelajaranContainer" aria-live="polite" aria-atomic="true"
         style="position: fixed; top: 18px; right: 18px; z-index: 1085; display: flex; flex-direction: column; gap: 8px;"></div>

    {{-- Page Header --}}
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="font-weight: 900; font-size: 1.75rem; letter-spacing: -0.02em;">
                Master Jam Pelajaran Sekolah
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                @if($systemMode === 'shift')
                    Tipe Penjadwalan Tahun Ajaran terpilih: <strong>Multi-Shift</strong> — kelola slot jam KBM &amp; istirahat (Senin – Jumat) per shift.
                    Slot pada tiap sub-tab shift <strong>terisolasi</strong> dari shift lain; penomoran Jam Ke- dihitung terpisah.
                    Mode ini dikunci dari Tahun Ajaran terpilih dan hanya dapat diubah lewat <strong>Data Master Tahun Ajaran</strong>.
                @else
                    Kelola struktur jam pelajaran KBM &amp; istirahat (Senin – Jumat) untuk seluruh kelas.
                    Mode penjadwalan dikunci dari Tahun Ajaran terpilih — perubahan mode hanya lewat
                    <strong>Data Master Tahun Ajaran</strong>.
                @endif
            </p>
        </div>
        </div>

    {{-- Baris Aksi Atas: kiri = filter Tahun Ajaran & Semester, kanan = tombol aksi utama --}}
    <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mb-6">
        {{-- Kiri: Filter 'Tahun Ajaran & Semester' — soft pill dropdown, status Aktif sebagai chip hijau --}}
        @if($tahunAjaranList->isNotEmpty())
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="text-muted d-none d-xl-inline text-nowrap" style="font-size: 0.78rem;">
                    <i class="bi bi-calendar-event me-1 text-primary"></i>Tahun Ajaran &amp; Semester
                </span>
                <div class="dropdown" id="selectTahunAjaran">
                    <button type="button" class="btn d-inline-flex align-items-center gap-2 fw-semibold text-nowrap"
                            style="font-size: 0.875rem; height: 40px; min-width: 264px; padding: 0 0.9rem;
                                   background-color: #ffffff; color: #1e293b; border: 1px solid #cbd5e1;
                                   border-radius: 0.5rem; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);"
                            data-bs-toggle="dropdown" aria-expanded="false"
                            title="{{ $selectedTahunAjaran ? $selectedTahunAjaran->tahun_ajaran . ' – ' . $selectedTahunAjaran->semester : 'Pilih Tahun Ajaran & Semester' }}"
                            aria-label="Pilih Tahun Ajaran & Semester — konteks tampilan">
                        <i class="bi bi-calendar3 text-primary"></i>
                        <span class="fw-semibold text-nowrap" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                            {{ $selectedTahunAjaran?->tahun_ajaran }} – {{ $selectedTahunAjaran?->semester }}
                        </span>
                        @if($selectedTahunAjaran?->is_active)
                            <span class="badge rounded-pill px-2 py-1 d-inline-flex align-items-center gap-1"
                                  style="font-size: 0.64rem; line-height: 1.3; background-color: #d1fae5; color: #047857; border: 1px solid #a7f3d0;">
                                <i class="bi bi-check-circle-fill" style="font-size: 0.55rem;"></i>Aktif
                            </span>
                        @endif
                        <i class="bi bi-chevron-down text-muted ms-1" style="font-size: 0.68rem;"></i>
                    </button>
                    <ul class="dropdown-menu shadow-sm border-0"
                        style="min-width: 300px; max-height: 320px; overflow-y: auto; font-size: 0.85rem; border-radius: 0.6rem;">
                        @foreach($tahunAjaranList as $ta)
                            @php
                                $isSelected = $selectedTahunAjaran && $selectedTahunAjaran->id === $ta->id;
                            @endphp
                            <li>
                                <a class="dropdown-item d-flex align-items-center gap-2 rounded-3 {{ $isSelected ? 'active' : '' }}"
                                   href="{{ route('admin.jam-pelajaran.index', $baseLink + ['ta' => $ta->id]) }}"
                                   @if($isSelected) aria-current="true" style="background-color: #eff6ff; color: #1d4ed8;" @else style="color: #334155;" @endif>
                                    <i class="bi {{ $isSelected ? 'bi-calendar-check text-primary' : 'bi-calendar-event text-muted' }}"></i>
                                    <span class="flex-grow-1 text-truncate">{{ $ta->tahun_ajaran }} – {{ $ta->semester }}</span>
                                    @if($ta->is_active)
                                        <span class="badge rounded-pill px-2 py-1" style="font-size: 0.62rem; background-color: #d1fae5; color: #047857; border: 1px solid #a7f3d0;">Aktif</span>
                                    @endif
                                    @if($isSelected)
                                        <i class="bi bi-check2 text-primary"></i>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @else
            <span class="text-muted d-inline-flex align-items-center gap-1 fw-semibold" style="font-size: 0.8rem;">
                <i class="bi bi-calendar-event"></i> Tahun Ajaran belum tersedia
            </span>
        @endif

        {{-- Kanan: tombol aksi utama (+ Tambah Jam = Primary paling mencolok) --}}
        <div class="d-flex align-items-center gap-2 flex-wrap justify-content-sm-end">
            @if(!$shiftModeEmpty)
                {{-- Generate Preset: aksi ringkas (ikon + label pendek) --}}
                <button type="button"
                        class="btn btn-outline-secondary rounded-3 fw-semibold px-3 py-2 d-inline-flex align-items-center justify-content-center gap-1 text-nowrap"
                        style="font-size: 0.875rem; height: 40px; background-color: #f1f5f9; border-color: #cbd5e1; color: #334155;"
                        data-bs-toggle="modal" data-bs-target="#modalGeneratePreset"
                        title="Generate Preset Jam ({{ $tab }})" aria-label="Generate Preset Jam">
                    <i class="bi bi-lightning-charge-fill"></i>
                    <span class="d-none d-lg-inline">Preset</span>
                </button>
            @endif

            {{-- Tombol "Salin dari Semester Lalu": TA terpilih masih kosong & ada sumber --}}
            @if($selectedTahunAjaran && $totalSlotsForTa === 0 && $hasCopySource && $previousTahunAjaran)
                <form method="POST"
                      action="{{ route('admin.jam-pelajaran.copy-from', $taParam) }}"
                      class="d-inline-flex"
                      onsubmit="return confirm('Salin seluruh struktur jam pelajaran dari {{ $previousTahunAjaran->tahun_ajaran }} – {{ $previousTahunAjaran->semester }} ke Tahun Ajaran {{ $selectedTahunAjaran->tahun_ajaran }} – {{ $selectedTahunAjaran->semester }}? Data lama tidak akan diubah / ditimpa.')">
                    @csrf
                    <button type="submit"
                            class="btn btn-outline-info rounded-3 fw-semibold px-3 py-2 d-inline-flex align-items-center justify-content-center gap-2 text-nowrap"
                            style="font-size: 0.875rem; height: 40px;"
                            title="Salin struktur slot jam dari {{ $previousTahunAjaran->tahun_ajaran }} – {{ $previousTahunAjaran->semester }}">
                        <i class="bi bi-copy"></i>
                        <span class="d-none d-md-inline">Salin dari Semester Lalu</span>
                        <span class="d-md-none">Salin</span>
                    </button>
                </form>
            @endif

            @if(!$shiftModeEmpty)
                {{-- Tambah Jam Pelajaran: Primary (paling mencolok di sebelah kanan) --}}
                <button type="button" id="btnTambahJam"
                        class="btn btn-primary rounded-3 fw-semibold px-3 py-2 d-inline-flex align-items-center justify-content-center gap-2 text-nowrap shadow-sm"
                        style="font-size: 0.875rem; height: 40px;" data-bs-toggle="modal" data-bs-target="#modalTambahJam"
                        data-mulai-senin="{{ $autoMulai['Senin-Kamis'] }}"
                        data-mulai-jumat="{{ $autoMulai['Jumat'] }}">
                    <i class="bi bi-plus-lg"></i>
                    Tambah <span class="d-none d-sm-inline">Jam Pelajaran</span>
                </button>
            @endif
        </div>
    </div>

    {{-- Alert Messages --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4" role="alert"
             style="font-size: 0.9rem;">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4" role="alert"
             style="font-size: 0.9rem;">
            <i class="bi bi-exclamation-circle-fill me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(request('bulk_deleted'))
        <div class="alert alert-success alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4" role="alert"
             style="font-size: 0.9rem;">
            <i class="bi bi-check-circle-fill me-2"></i>{{ request('bulk_deleted') }} slot jam pelajaran berhasil dihapus.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($systemMode === 'shift')
        {{-- ====== TAMPILAN KHUSUS MODE SHIFT ====== --}}
        <div class="mb-4">
            {{-- Baris Pilihan Shift (1 baris flex): kiri = label + pills tab shift + "+ Shift Baru", kanan = Kelola Shift --}}
            <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mb-2">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="text-muted fw-semibold d-inline-flex align-items-center gap-1 me-1" style="font-size: 0.82rem;">
                        <i class="bi bi-layers"></i> Pilih Shift:
                    </span>
                    @forelse($shifts as $shift)
                        <a href="{{ route('admin.jam-pelajaran.index', ['tab' => $tab, 'shift' => $shift->id] + $taParam) }}"
                           class="btn btn-sm rounded-3 fw-semibold px-3 py-1 d-inline-flex align-items-center gap-1 {{ $selectedShiftId == $shift->id ? 'btn-dark text-white shadow-sm' : 'btn-light border text-dark' }}"
                           style="font-size: 0.8rem;">
                            <i class="bi bi-clock"></i> {{ $shift->nama_shift }}
                            @if(!$shift->is_active)
                                <span class="badge bg-warning-subtle text-warning rounded-pill" style="font-size: 0.62rem;">Non-Aktif</span>
                            @endif
                        </a>
                        @if($selectedShiftId == $shift->id)
                            <button type="button"
                                    class="btn btn-sm btn-light border rounded-3 px-2 py-1 d-inline-flex align-items-center btn-edit-shift-strip"
                                    data-shift-id="{{ $shift->id }}"
                                    title="Edit {{ $shift->nama_shift }}"
                                    style="font-size: 0.8rem;">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                        @endif
                    @empty
                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-3 py-2" style="font-size: 0.78rem;">
                            Belum ada shift — pilih "+ Shift Baru" untuk membuat Shift 1, Shift 2, dst.
                        </span>
                    @endforelse

                    {{-- + Shift Baru: tombol plus kecil menyatu di ujung kanan baris tab shift --}}
                    <button type="button"
                            class="btn btn-sm rounded-3 d-inline-flex align-items-center gap-1 px-3 py-1 fw-semibold"
                            style="font-size: 0.8rem; background-color: #f1f5f9; border: 1px solid #cbd5e1; color: #334155;"
                            data-bs-toggle="modal" data-bs-target="#modalShiftTambah"
                            title="+ Tambah Shift" aria-label="+ Tambah Shift">
                        <i class="bi bi-plus-lg"></i>
                        <span class="d-none d-sm-inline">Shift Baru</span>
                    </button>
                </div>

                {{-- Kanan: Kelola Daftar Shift (outline kecil) — sebaris dengan tab shift --}}
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-3 fw-semibold px-3 py-1 d-inline-flex align-items-center gap-1"
                            style="font-size: 0.8rem;" data-bs-toggle="modal" data-bs-target="#modalShiftKelola">
                        <i class="bi bi-gear me-1"></i>Kelola Daftar Shift
                    </button>
                </div>
            </div>

            {{-- Callout ringkas shift aktif (1 baris tipis) — soft blue selaras tema --}}
            @if($selectedShift)
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-3 border border-info-subtle"
                     style="background-color: #eff6ff; font-size: 0.75rem; max-width: 880px;">
                    <i class="bi bi-clock-history text-primary"></i>
                    <span class="text-dark">
                        <strong>Mode Shift: {{ $selectedShift->nama_shift }}</strong> — slot KBM/Istirahat khusus shift ini
                        terisolasi dari shift lain (penomoran Jam Ke- terpisah); kelas yang terikat memakai slot
                        <strong>Global + {{ $selectedShift->nama_shift }}</strong>.
                    </span>
                </div>
            @endif
        </div>
    @else
        {{-- ====== TAMPILAN MODE GLOBAL (default, tanpa UI shift) ====== --}}
        <div class="d-inline-flex align-items-start gap-2 mb-4 px-3 py-2 rounded-3 border border-info-subtle"
             style="background-color: #eff6ff; font-size: 0.78rem; max-width: 840px;">
            <i class="bi bi-globe2 text-primary mt-1"></i>
            <span class="text-dark">
                <strong>Mode Global</strong> — Slot jam di bawah berlaku untuk seluruh kelas.
                Mode penjadwalan dikunci dari Tahun Ajaran terpilih (Global); perubahan mode hanya
                lewat Form Edit Tahun Ajaran di Data Master Tahun Ajaran.
            </span>
        </div>
    @endif

    {{-- Tab Kelompok Hari (Senin–Kamis vs Jumat) --}}
    <div class="mb-4">
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.jam-pelajaran.index', ['tab' => 'Senin-Kamis', 'shift' => $selectedShiftId] + $taParam) }}"
               class="btn rounded-3 fw-semibold px-4 py-2 {{ $tab === 'Senin-Kamis' ? 'btn-primary shadow-sm text-white' : 'btn-light border text-dark' }}"
               style="font-size: 0.875rem;">
                <i class="bi bi-calendar-week me-1"></i>
                Senin – Kamis
            </a>
            <a href="{{ route('admin.jam-pelajaran.index', ['tab' => 'Jumat', 'shift' => $selectedShiftId] + $taParam) }}"
               class="btn rounded-3 fw-semibold px-4 py-2 {{ $tab === 'Jumat' ? 'btn-primary shadow-sm text-white' : 'btn-light border text-dark' }}"
               style="font-size: 0.875rem;">
                <i class="bi bi-calendar2-day me-1"></i>
                Jumat
            </a>
        </div>
    </div>

    {{-- Main Data Card --}}
    <div class="card border-0 rounded-4 shadow-sm">
        @php $rows = $tab === 'Senin-Kamis' ? $seninKamis : $jumat; @endphp

        <div class="card-header bg-white border-0 pt-4 pb-0 px-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-2 d-flex align-items-center justify-content-center"
                         style="width: 34px; height: 34px; background: {{ $tab === 'Senin-Kamis' ? 'linear-gradient(135deg,#1677ff,#0958d9)' : 'linear-gradient(135deg,#f97316,#ea580c)' }};">
                        <i class="bi {{ $tab === 'Senin-Kamis' ? 'bi-calendar-week' : 'bi-calendar2-day' }} text-white" style="font-size: 0.95rem;"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.95rem;">
                            Master Jam Sekolah &mdash; {{ $tab === 'Senin-Kamis' ? 'Senin – Kamis' : 'Jumat' }}
                            @if($selectedShift)
                                <span class="badge bg-dark-subtle text-dark rounded-pill px-2 py-1 align-middle ms-1" style="font-size: 0.68rem;">
                                    <i class="bi bi-clock"></i> {{ $selectedShift->nama_shift }}
                                </span>
                            @else
                                <span class="badge bg-info-subtle text-primary rounded-pill px-2 py-1 align-middle ms-1" style="font-size: 0.68rem;">
                                    <i class="bi bi-globe2"></i> Global
                                </span>
                            @endif
                            @if($selectedTahunAjaran)
                                <span class="badge bg-primary-subtle text-primary rounded-pill px-2 py-1 align-middle ms-1" style="font-size: 0.68rem;">
                                    <i class="bi bi-calendar-event"></i> {{ $selectedTahunAjaran->tahun_ajaran }} – {{ $selectedTahunAjaran->semester }}{{ $selectedTahunAjaran->is_active ? ' (Aktif)' : '' }}
                                </span>
                            @endif
                        </h6>
                        <div class="text-muted" style="font-size: 0.75rem;">
                            {{ $rows->count() }} slot terdaftar — Mode: {{ $selectedShift?->nama_shift ?? 'Global' }}
                        </div>
                    </div>
                </div>
                @if($rows->isNotEmpty())
                    <button type="button"
                            class="btn btn-outline-danger rounded-3 fw-semibold px-3 d-flex align-items-center gap-2"
                            style="font-size: 0.8rem;"
                            data-bs-toggle="modal" data-bs-target="#modalHapusSemuaJP">
                        <i class="bi bi-trash3-fill"></i> Hapus Semua JP
                    </button>
                @endif
            </div>
        </div>

        <div class="card-body p-0">
            @if($shiftModeEmpty)
                <div class="text-center py-5">
                    <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle mb-3" style="width: 70px; height: 70px;">
                        <i class="bi bi-layers text-muted" style="font-size: 2.2rem;"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-1">Belum Ada Shift Pelajaran</h6>
                    <p class="text-muted mx-auto mb-3" style="max-width: 440px; font-size: 0.85rem;">
                        Buat jenis shift terlebih dahulu (Shift 1, Shift 2, dst.) melalui tombol
                        <strong>+ Tambah Shift</strong> di bagian atas, lalu kelola slot jam per shift di sini.
                    </p>
                    <button type="button" class="btn btn-primary rounded-3 px-3 py-2 fw-semibold"
                            style="font-size: 0.85rem;" data-bs-toggle="modal" data-bs-target="#modalShiftTambah">
                        <i class="bi bi-plus-lg me-1"></i> + Tambah Shift
                    </button>
                </div>
            @elseif($rows->isEmpty())
                <div class="text-center py-5">
                    <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle mb-3" style="width: 70px; height: 70px;">
                        <i class="bi bi-clock text-muted" style="font-size: 2.2rem;"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-1">Belum Ada Data Jam Pelajaran ({{ $tab }})</h6>
                    <p class="text-muted mx-auto mb-3" style="max-width: 420px; font-size: 0.85rem;">
                        Klik tombol <strong>Generate Preset</strong> di atas atau tambah slot jam secara manual.
                    </p>
                    <button type="button" class="btn btn-primary rounded-3 px-3 py-2 fw-semibold"
                            style="font-size: 0.85rem;" data-bs-toggle="modal" data-bs-target="#modalGeneratePreset">
                        <i class="bi bi-lightning-charge-fill me-1"></i> Generate Preset {{ $tab }}
                    </button>
                    @if($selectedTahunAjaran && $totalSlotsForTa === 0 && $hasCopySource && $previousTahunAjaran)
                        <div class="mt-3 text-muted mx-auto" style="max-width: 480px; font-size: 0.78rem;">
                            Atau salin struktur slot jam dari semester lalu via tombol <strong>Salin dari Semester Lalu</strong> di bagian atas
                            (sumber: {{ $previousTahunAjaran->tahun_ajaran }} – {{ $previousTahunAjaran->semester }}).
                        </div>
                    @endif
                </div>
            @else
                {{-- Bulk Action Bar: muncul saat ada checkbox dicentang --}}
                <div class="px-4">
                    <div id="bulkActionBar"
                         class="d-none align-items-center justify-content-between gap-3 px-3 py-2 mb-3 rounded-3 border"
                         style="background-color: #f1f5f9; border-color: #e2e8f0 !important;">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="badge bg-primary rounded-pill px-3 py-1 fw-semibold" style="font-size: 0.75rem;">
                                <span id="bulkCount">0</span> jam dipilih
                            </span>
                            <span class="text-muted d-none d-md-inline" style="font-size: 0.78rem;">
                                Centang baris untuk aksi massal (Edit / Hapus)
                            </span>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <button type="button" id="btnBulkBatal"
                                    class="btn btn-sm btn-light border rounded-3 fw-semibold"
                                    style="font-size: 0.78rem;">
                                <i class="bi bi-x-lg me-1"></i> Batal
                            </button>
                            <button type="button" id="btnBulkHapus"
                                    class="btn btn-sm btn-outline-danger rounded-3 fw-semibold"
                                    style="font-size: 0.78rem;">
                                <i class="bi bi-trash3-fill me-1"></i> Hapus Terpilih
                            </button>
                            <button type="button" id="btnBulkEdit"
                                    class="btn btn-sm btn-primary rounded-3 fw-semibold"
                                    style="font-size: 0.78rem;">
                                <i class="bi bi-pencil-fill me-1"></i> Edit Terpilih
                            </button>
                        </div>
                    </div>
                </div>

                <div class="table-responsive overflow-x-auto w-full px-2">
                    <table class="table table-hover align-middle mb-0 min-w-full" style="font-size: 0.9rem; min-width: 720px;">
                        <thead style="background: #f8fafc;">
                            <tr>
                                <th class="ps-4 pe-2 py-3 align-middle" style="width: 56px;">
                                    <input type="checkbox" id="select-all" class="form-check-input" title="Pilih semua jam pelajaran" style="cursor: pointer;">
                                </th>
                                <th class="ps-4 py-3" style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #ffffff; white-space: nowrap; width: 130px;">Jam Ke-</th>
                                <th class="py-3" style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #64748b; width: 180px;">Rentang Waktu</th>
                                <th class="py-3" style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #64748b; width: 120px;">Durasi</th>
                                <th class="py-3" style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #64748b;">Jenis / Keterangan</th>
                                <th class="py-3 pe-4 text-end whitespace-nowrap" style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #64748b; width: 140px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $istirahatCount = 0; @endphp
                            @foreach($rows as $jam)
                                @php
                                    $mulai   = \Carbon\Carbon::parse($jam->jam_mulai);
                                    $selesai = \Carbon\Carbon::parse($jam->jam_selesai);
                                    $durasi  = $mulai->diffInMinutes($selesai);

                                    if ($jam->jenis === 'istirahat') {
                                        $istirahatCount++;
                                        $jenisLabel = "Istirahat " . $istirahatCount;
                                    } else {
                                        $jenisLabel = match($jam->jenis) {
                                            'kbm'   => 'KBM',
                                            default => ucfirst($jam->jenis),
                                        };
                                    }

                                    // Label ringkas per baris (dipakai JS untuk grouping bulk edit)
                                    $rowLabel = $jam->jenis === 'istirahat'
                                        ? $jenisLabel
                                        : (($jam->jam_ke ? "Jam {$jam->jam_ke} (KBM)" : 'KBM'));

                                    $jenisBadge = match($jam->jenis) {
                                        'kbm'       => ['bg' => '#ecfdf5', 'color' => '#059669', 'border' => '#a7f3d0', 'icon' => 'bi-book-fill'],
                                        'istirahat' => ['bg' => '#fff7ed', 'color' => '#ea580c', 'border' => '#fed7aa', 'icon' => 'bi-cup-hot-fill'],
                                        default     => ['bg' => '#f8fafc', 'color' => '#64748b', 'border' => '#e2e8f0', 'icon' => 'bi-circle-fill'],
                                    };
                                @endphp
                                <tr>
                                    <td class="ps-4 pe-2 align-middle">
                                        <input type="checkbox" class="form-check-input jp-checkbox" value="{{ $jam->id }}"
                                                       data-jenis="{{ $jam->jenis }}"
                                                       data-jam-ke="{{ $jam->jam_ke ?? '' }}"
                                                       data-label="{{ $rowLabel }}"
                                                       data-durasi="{{ $durasi }}"
                                                       style="cursor: pointer;">
                                    </td>
                                    <td class="ps-4 whitespace-nowrap align-middle">
                                        <div class="d-flex align-items-center gap-2">
                                            @if($jam->jenis !== 'istirahat' && $jam->jam_ke)
                                                <div class="rounded-circle d-flex align-items-center justify-content-center fw-black text-white"
                                                     style="width: 30px; height: 30px; font-size: 0.78rem; background: #1677ff;">
                                                    {{ $jam->jam_ke }}
                                                </div>
                                                <span class="fw-bold text-dark">Jam {{ $jam->jam_ke }}</span>
                                            @else
                                                <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-muted bg-light border"
                                                     style="width: 30px; height: 30px; font-size: 0.78rem;">
                                                    -
                                                </div>
                                                <span class="text-muted fw-semibold">-</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="align-middle">
                                        <span class="fw-semibold text-dark whitespace-nowrap" style="font-variant-numeric: tabular-nums; font-family: 'Courier New', monospace; font-size: 0.92rem;">
                                            {{ substr(str_replace(':', '.', $jam->jam_mulai), 0, 5) }} – {{ substr(str_replace(':', '.', $jam->jam_selesai), 0, 5) }}
                                        </span>
                                    </td>
                                    <td class="align-middle">
                                        <span class="text-muted fw-semibold whitespace-nowrap" style="font-size: 0.85rem;">{{ $durasi }} menit</span>
                                    </td>
                                    <td class="align-middle">
                                        <span class="badge d-inline-flex align-items-center gap-1 px-3 py-2 rounded-pill fw-semibold"
                                              style="font-size: 0.78rem; background-color: {{ $jenisBadge['bg'] }}; color: {{ $jenisBadge['color'] }}; border: 1px solid {{ $jenisBadge['border'] }};">
                                            <i class="bi {{ $jenisBadge['icon'] }}" style="font-size: 0.72rem;"></i>
                                            {{ $jenisLabel }}
                                        </span>
                                    </td>
                                    <td class="pe-4 text-end whitespace-nowrap">
                                        <div class="flex items-center justify-center gap-2 whitespace-nowrap">
                                            <button type="button"
                                                    class="btn btn-sm btn-light border rounded-3 px-2 py-1"
                                                    style="font-size: 0.78rem;"
                                                    title="Edit"
                                                    onclick="openEditModal(
                                                        {{ $jam->id }},
                                                        '{{ $jam->kategori_hari }}',
                                                        '{{ substr($jam->jam_mulai, 0, 5) }}',
                                                        '{{ substr($jam->jam_selesai, 0, 5) }}',
                                                        '{{ $jam->jenis }}',
                                                        {{ $mulai->diffInMinutes($selesai) }},
                                                        {{ $jam->shift_id ?? 0 }}
                                                    )">
                                                <i class="bi bi-pencil-fill text-primary me-1"></i> Edit
                                            </button>
                                            <form method="POST"
                                                  action="{{ route('admin.jam-pelajaran.destroy', $jam->id) }}"
                                                  onsubmit="return confirm('Hapus slot {{ $jenisLabel }} ({{ \Carbon\Carbon::parse($jam->jam_mulai)->format('H.i') }})?')"
                                                  class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-light border rounded-3 px-2 py-1"
                                                        style="font-size: 0.78rem;" title="Hapus">
                                                    <i class="bi bi-trash3-fill text-danger me-1"></i> Hapus
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

</div>

{{-- ===================== CARD PENGATURAN JAM PULANG PER TINGKAT ===================== --}}
<div class="container-fluid px-0 mt-4">
    <div class="card border-0 rounded-4 shadow-sm mb-4">
        <div class="card-header bg-white border-0 pt-4 pb-3 px-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-2 d-flex align-items-center justify-content-center"
                         style="width: 34px; height: 34px; background: linear-gradient(135deg,#f97316,#ea580c);">
                        <i class="bi bi-door-closed-fill text-white" style="font-size: 0.95rem;"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.95rem;">
                            ⚙️ Pengaturan Jam Pulang per Tingkat Kelas
                        </h6>
                        <div class="text-muted" style="font-size: 0.75rem;">
                            Tentukan batas slot KBM terakhir per tingkat. Slot setelahnya otomatis dikunci sebagai "🛑 Pulang Sekolah".
                        </div>
                    </div>
                </div>
                <span class="badge rounded-pill px-3 py-1"
                      style="font-size: 0.72rem; background-color: #eff6ff; color: #1d4ed8 !important; border: 1px solid #bfdbfe;">
                    Berlaku per Shift Tingkat
                </span>
            </div>
        </div>

        <div class="card-body px-4 pb-4 pt-2">
            <form method="POST" action="{{ route('admin.jam-pulang.upsert') }}" id="formJamPulang">
                @csrf
                <input type="hidden" name="redirect_tab" value="{{ $tab }}">
                <input type="hidden" name="redirect_shift" value="{{ $selectedShiftId ?? '' }}">
                <input type="hidden" name="redirect_ta" value="{{ $selectedTahunAjaran?->id ?? '' }}">

                @php
                    // Hanya tampilkan tingkatan yang dilayani shift aktif pada panel Jam Pulang;
                    // grade_levels kosong (null/[]) = berlaku semua tingkatan (X, XI, XII).
                    $tingkatList = $selectedShift && ! empty($selectedShift->grade_levels)
                        ? array_values(array_intersect(\App\Models\ShiftPelajaran::GRADE_LEVELS, $selectedShift->grade_levels))
                        : \App\Models\ShiftPelajaran::GRADE_LEVELS;
                    $kategoriList = [
                        'Senin-Kamis' => ['label' => 'Senin – Kamis', 'icon' => 'bi-calendar-week'],
                        'Jumat'       => ['label' => 'Jumat',         'icon' => 'bi-calendar2-day'],
                    ];

                    // Pengaturan jam pulang menyesuaikan tab shift yang sedang aktif (single context).
                    $jpCtxId      = (string) ($selectedShiftId ?? 0);
                    $jpCtxNama    = $selectedShift?->nama_shift ?? 'Global';
                    $jpCtxDesc    = $selectedShift ? ($selectedShift->rentang_utama ?: 'Kelas terikat shift ini') : 'Semua kelas tanpa shift';
                    $jpCtxIsShift = (bool) $selectedShift;

                    // Panel hanya memuat konteks aktif (Global / shift terpilih).
                    $jamPulangContexts = collect([
                        ['id' => $jpCtxId, 'nama' => $jpCtxNama, 'desc' => $jpCtxDesc, 'is_active' => true],
                    ]);
                @endphp

                {{-- Indikator: pengaturan jam pulang mengikuti tab shift aktif (soft blue) --}}
                <div class="d-inline-flex align-items-center gap-2 flex-wrap px-3 py-2 mb-3 rounded-3 border border-info-subtle"
                     style="font-size: 0.8rem; max-width: 100%; background-color: #eff6ff;">
                    <i class="bi {{ $jpCtxIsShift ? 'bi-clock' : 'bi-globe2' }} text-primary"></i>
                    <span class="fw-semibold text-dark d-inline-flex align-items-center gap-2 flex-wrap">
                        Berlaku untuk: <strong>{{ $jpCtxNama }}</strong>
                        <span class="text-muted fw-normal">({{ $jpCtxDesc }})</span>
                        @if($selectedShift)
                            <span class="badge rounded-pill px-2 py-1"
                                  style="font-size: 0.64rem; background-color: #eef2ff; color: #4338ca; border: 1px solid #c7d2fe;">
                                <i class="bi bi-mortarboard me-1"></i>Tingkatan: {{ $selectedShift->grade_levels_label }}
                            </span>
                        @endif
                    </span>
                    <span class="badge bg-white text-dark border rounded-pill px-2 py-1 ms-auto" style="font-size: 0.68rem;">
                        Menyesuaikan tab shift aktif
                    </span>
                </div>

                @foreach($jamPulangContexts as $ctx)
                    <div class="shift-jp-panel" data-shift-panel="{{ $ctx['id'] }}">
                        <div class="row g-4">
                            @foreach($kategoriList as $kHari => $kMeta)
                                @php
                                    $maxAvailable = $maxByShift[$ctx['id']][$kHari] ?? 0;
                                @endphp
                                <div class="col-md-6">
                                    <div class="p-3 rounded-3 border bg-light-subtle" style="background-color: #fafafa;">
                                        <div class="d-flex align-items-center gap-2 mb-3">
                                            <i class="bi {{ $kMeta['icon'] }} text-primary"></i>
                                            <span class="fw-bold text-dark" style="font-size: 0.9rem;">{{ $kMeta['label'] }}</span>
                                            <span class="badge bg-secondary-subtle text-secondary rounded-pill ms-auto px-2 py-1" style="font-size: 0.72rem;">
                                                Max Jam KBM Tersedia: {{ $maxAvailable }}
                                            </span>
                                        </div>
                                        @if($maxAvailable === 0)
                                            <div class="text-muted d-flex align-items-start gap-1 mb-3" style="font-size: 0.74rem;">
                                                <i class="bi bi-info-circle mt-1"></i>
                                                <span>
                                                    Belum ada slot jam untuk <strong>{{ $ctx['nama'] }}</strong> pada
                                                    {{ $kMeta['label'] }} — buat slotnya terlebih dahulu pada tabel di atas,
                                                    lalu atur batas jam pulang di sini.
                                                </span>
                                            </div>
                                        @endif
                                        <div class="d-flex flex-column gap-2">
                                            @foreach($tingkatList as $tingkat)
                                                @php
                                                    $key       = "{$ctx['id']}|{$kHari}|{$tingkat}";
                                                    $savedMax  = $jamPulangSettings->get($key)?->max_jam_ke;
                                                @endphp
                                                <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center gap-2 gap-sm-3 p-2 rounded-3 bg-white border">
                                                    <div class="d-flex align-items-center gap-2 w-full">
                                                        <div class="d-flex align-items-center justify-content-center rounded-2 fw-black text-white flex-shrink-0"
                                                             style="width: 36px; height: 36px; font-size: 0.8rem; background: {{ $tingkat === 'X' ? '#1677ff' : ($tingkat === 'XI' ? '#7c3aed' : '#059669') }};">
                                                            {{ $tingkat }}
                                                        </div>
                                                        <div class="fw-semibold text-dark" style="font-size: 0.82rem;">
                                                            Kelas {{ $tingkat }} — Pulang Setelah:
                                                        </div>
                                                        <span class="badge jam-pulang-badge rounded-pill px-2 py-1 ms-auto flex-shrink-0"
                                                              data-shift="{{ $ctx['id'] }}"
                                                              data-kategori="{{ $kHari }}"
                                                              data-tingkat="{{ $tingkat }}"
                                                              style="font-size: 0.72rem;">
                                                            @if($savedMax)
                                                                <span class="badge text-bg-danger rounded-pill">Batas: Jam {{ $savedMax }}</span>
                                                            @else
                                                                <span class="badge text-bg-info rounded-pill">Semua Slot</span>
                                                            @endif
                                                        </span>
                                                    </div>
                                                    <div class="w-full flex-sm-grow-1">
                                                        <select name="jam_pulang[{{ $ctx['id'] }}][{{ $kHari }}][{{ $tingkat }}]"
                                                                id="jp-{{ $ctx['id'] }}-{{ \Illuminate\Support\Str::slug($kHari) }}-{{ $tingkat }}"
                                                                class="form-select form-select-sm rounded-3 jam-pulang-select w-full"
                                                                data-shift="{{ $ctx['id'] }}"
                                                                data-kategori="{{ $kHari }}"
                                                                data-tingkat="{{ $tingkat }}"
                                                                data-initial="{{ $savedMax ?: '' }}"
                                                                style="font-size: 0.82rem;">
                                                            <option value="">— Tidak Dibatasi (semua slot aktif) —</option>
                                                            @for($j = 1; $j <= $maxAvailable; $j++)
                                                                <option value="{{ $j }}" {{ $savedMax == $j ? 'selected' : '' }}>
                                                                    Jam Ke-{{ $j }}
                                                                    @if($j == $maxAvailable) (Jam Terakhir) @endif
                                                                </option>
                                                            @endfor
                                                        </select>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <div class="d-flex align-items-center justify-content-between mt-4 pt-3 border-top flex-wrap gap-3">
                    <div class="text-muted d-flex align-items-center gap-2" style="font-size: 0.8rem;">
                        <i class="bi bi-info-circle text-primary"></i>
                        Pilih "Tidak Dibatasi" agar semua slot KBM dapat di-plot tanpa batas jam pulang.
                        Setiap perubahan dropdown tersimpan <strong>otomatis</strong>.
                    </div>
                    <div id="jpSaveIndicator" class="d-none small fw-semibold align-items-center gap-2 px-3 py-1 rounded-pill border"
                         style="font-size: 0.78rem; background-color: #f0fdf4; color: #15803d; border-color: #bbf7d0;">
                        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true" style="width: 0.9em; height: 0.9em;"></span>
                        Menyimpan…
                    </div>
                </div>
            </form>
        </div>
</div>

    <div class="card border-0 rounded-4 shadow-sm">
        <div class="card-body px-4 pb-4 pt-2">
            <form method="POST" action="{{ route('admin.agenda-rutin.upsert') }}" id="formAgendaRutin">
                @csrf
                <input type="hidden" name="redirect_tab" value="{{ $tab }}">
                <input type="hidden" name="redirect_shift" value="{{ $selectedShiftId ?? '' }}">
                <input type="hidden" name="redirect_ta" value="{{ $selectedTahunAjaran?->id ?? '' }}">

                {{-- Grid Layout: 2 Kolom Berdampingan --}}
                <div class="row g-4">
                    {{-- Kolom Kiri: Pengaturan Upacara Bendera (Hari Senin) --}}
                    <div class="col-12 col-lg-6">
                        {{-- Fieldset Guard Untuk Setiap Kolom --}}
                        <fieldset class="border-0 p-0 m-0">
                            {{-- Konten Card Kiri: Upacara Bendera --}}
                                <div class="card-header bg-white border-0 pt-4 pb-2 px-4">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="rounded-2 d-flex align-items-center justify-content-center text-white"
                                                 style="width: 36px; height: 36px; background: linear-gradient(135deg,#3b82f6,#1d4ed8);">
                                                <span style="font-size: 1.1rem;">🇮🇩</span>
                                            </div>
                                            <div>
                                                <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.95rem;">
                                                    Pengaturan Upacara Bendera (Khusus Hari Senin)
                                                </h6>
                                                <div class="text-muted" style="font-size: 0.75rem;">
                                                    Hari Senin (Locked Global)
                                                </div>
                                                @if($selectedShift)
                                                    <span class="badge rounded-pill px-2 py-1 mt-1 d-inline-flex align-items-center gap-1"
                                                          style="font-size: 0.65rem; background-color: #f0f9ff; color: #0369a1; border: 1px solid #bae6fd;">
                                                        <i class="bi bi-arrow-repeat"></i> Pengaturan khusus untuk {{ $selectedShift->nama_shift }}
                                                    </span>
                                                @else
                                                    <span class="badge rounded-pill px-2 py-1 mt-1 d-inline-flex align-items-center gap-1"
                                                          style="font-size: 0.65rem; background-color: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0;">
                                                        <i class="bi bi-globe2"></i> Berlaku global untuk semua kelas
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            @include('partials.testing-badge', ['record' => isset($agendaSenin) ? $agendaSenin : null])
                                            <span id="badgeStatusSenin"
                                              class="badge {{ (isset($agendaSenin) && $agendaSenin->is_active) ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle' }} rounded-pill px-3 py-1"
                                              style="font-size: 0.72rem;">
                                            @if(isset($agendaSenin) && $agendaSenin->is_active)
                                                ● Aktif (Terkunci Jam Ke-{{ $agendaSenin->jam_ke }})
                                            @else
                                                ○ Non-Aktif
                                            @endif
                                        </span>
                                        </div>
                                    </div>
                                </div>

                                @php
                                    $agendaSeninLocked = (isset($agendaSenin) && $agendaSenin->is_testing_data && !auth()->user()?->isPetugasIt());
                                @endphp

                                {{-- Konten Form: Dropdown + Toggle + Info + Tombol --}}
                                {{-- Dropdown Jam Ke- --}}
                                <div class="row g-3 align-items-center mb-3">
                                    {{-- Dropdown Jam Ke- --}}
                                    <div class="col-12 col-sm-5">
                                        <label class="form-label fw-semibold text-dark mb-1" style="font-size: 0.85rem;">
                                            <i class="bi bi-clock-history text-primary me-1"></i> Jam Ke- <span class="text-danger">*</span>
                                        </label>
                                        <select name="agenda[Senin][jam_ke]" id="jamKeSenin" class="form-select rounded-3" style="font-size: 0.875rem;" {{ $agendaSeninLocked ? 'disabled' : '' }}>
                                            @php
                                                $jamKeSeninSelected = old('agenda.Senin.jam_ke', $agendaSenin?->jam_ke);
                                            @endphp
                                            @if($agendaSenin === null)
                                                <option value="" {{ $jamKeSeninSelected === null ? 'selected' : '' }}>— Pilih Jam Ke- untuk shift ini —</option>
                                            @endif
                                            @forelse($jamOptionsSenin as $jam)
                                                <option value="{{ $jam->jam_ke }}" {{ $jamKeSeninSelected == $jam->jam_ke ? 'selected' : '' }}>
                                                    Jam Ke-{{ $jam->jam_ke }} ({{ $jam->jam_mulai_label }} - {{ $jam->jam_selesai_label }})
                                                </option>
                                            @empty
                                                <option value="">— Belum ada slot jam KBM —</option>
                                            @endforelse
                                        </select>
                                    </div>

                                    {{-- Toggle Switch --}}
                                    <div class="col-12 col-sm-7 pt-sm-4">
                                        <div class="form-check form-switch mb-0">
                                            <input class="form-check-input" type="checkbox" role="switch" id="switchAgendaSenin" name="agenda[Senin][is_active]" value="1"
                                                   {{ old('agenda.Senin.is_active', $agendaSenin?->is_active ?? false) ? 'checked' : '' }} style="cursor: pointer; width: 2.5em; height: 1.25em;"
                                                   {{ $agendaSeninLocked ? 'disabled' : '' }}>
                                            <label class="form-check-label fw-semibold text-dark ms-2" for="switchAgendaSenin" style="font-size: 0.85rem; cursor: pointer;">
                                                Kunci Slot Upacara Bendera
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="text-muted small mb-3 p-2.5 rounded-3 bg-light border" style="font-size: 0.78rem;">
                                    <i class="bi bi-info-circle text-primary me-1"></i>
                                    Mengunci slot jam ini secara otomatis di seluruh kelas untuk Upacara Bendera pada hari Senin.
                                </div>

                                <div id="warningSenin" class="small mb-3 p-2 rounded-3 text-warning-emphasis d-none" style="font-size: 0.78rem; background-color: #fff8e1; border: 1px solid #ffe082;">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i> ⚠️ Ada perubahan yang belum disimpan
                                </div>
                            </fieldset>
                    </div>

                    {{-- Kolom Kanan: Pengaturan Pembiasaan (Hari Jumat) --}}
                    <div class="col-12 col-lg-6">
                        {{-- Fieldset Guard Untuk Setiap Kolom --}}
                        <fieldset class="border-0 p-0 m-0">
                            {{-- Konten Card Kanan: Pembiasaan --}}
                                @php
                                    $agendaJumatLocked = (isset($agendaJumat) && $agendaJumat->is_testing_data && !auth()->user()?->isPetugasIt());
                                @endphp
                                <div class="card-header bg-white border-0 pt-4 pb-2 px-4">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="rounded-2 d-flex align-items-center justify-content-center text-white"
                                                 style="width: 36px; height: 36px; background: linear-gradient(135deg,#0284c7,#0369a1);">
                                                <span style="font-size: 1.1rem;">🤲</span>
                                            </div>
                                            <div>
                                                <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.95rem;">
                                                    Pengaturan Pembiasaan (Khusus Hari Jumat)
                                                </h6>
                                                <div class="text-muted" style="font-size: 0.75rem;">
                                                    Hari Jumat (Locked Global)
                                                </div>
                                                @if($selectedShift)
                                                    <span class="badge rounded-pill px-2 py-1 mt-1 d-inline-flex align-items-center gap-1"
                                                          style="font-size: 0.65rem; background-color: #f0f9ff; color: #0369a1; border: 1px solid #bae6fd;">
                                                        <i class="bi bi-arrow-repeat"></i> Pengaturan khusus untuk {{ $selectedShift->nama_shift }}
                                                    </span>
                                                @else
                                                    <span class="badge rounded-pill px-2 py-1 mt-1 d-inline-flex align-items-center gap-1"
                                                          style="font-size: 0.65rem; background-color: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0;">
                                                        <i class="bi bi-globe2"></i> Berlaku global untuk semua kelas
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            @include('partials.testing-badge', ['record' => isset($agendaJumat) ? $agendaJumat : null])
                                            <span id="badgeStatusJumat"
                                              class="badge {{ (isset($agendaJumat) && $agendaJumat->is_active) ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle' }} rounded-pill px-3 py-1"
                                              style="font-size: 0.72rem;">
                                            @if(isset($agendaJumat) && $agendaJumat->is_active)
                                                ● Aktif (Terkunci Jam Ke-{{ $agendaJumat->jam_ke }})
                                            @else
                                                ○ Non-Aktif
                                            @endif
                                        </span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Konten Form: Dropdown + Toggle + Info + Tombol --}}
                                {{-- Dropdown Jam Ke- --}}
                                <div class="row g-3 align-items-center mb-3">
                                    {{-- Dropdown Jam Ke- --}}
                                    <div class="col-12 col-sm-5">
                                        <label class="form-label fw-semibold text-dark mb-1" style="font-size: 0.85rem;">
                                            <i class="bi bi-clock-history text-info me-1"></i> Jam Ke- <span class="text-danger">*</span>
                                        </label>
                                        <select name="agenda[Jumat][jam_ke]" id="jamKeJumat" class="form-select rounded-3" style="font-size: 0.875rem;" {{ $agendaJumatLocked ? 'disabled' : '' }}>
                                            @php
                                                $jamKeJumatSelected = old('agenda.Jumat.jam_ke', $agendaJumat?->jam_ke);
                                            @endphp
                                            @if($agendaJumat === null)
                                                <option value="" {{ $jamKeJumatSelected === null ? 'selected' : '' }}>— Pilih Jam Ke- untuk shift ini —</option>
                                            @endif
                                            @forelse($jamOptionsJumat as $jam)
                                                <option value="{{ $jam->jam_ke }}" {{ $jamKeJumatSelected == $jam->jam_ke ? 'selected' : '' }}>
                                                    Jam Ke-{{ $jam->jam_ke }} ({{ $jam->jam_mulai_label }} - {{ $jam->jam_selesai_label }})
                                                </option>
                                            @empty
                                                <option value="">— Belum ada slot jam KBM —</option>
                                            @endforelse
                                        </select>
                                    </div>

                                    {{-- Toggle Switch --}}
                                    <div class="col-12 col-sm-7 pt-sm-4">
                                        <div class="form-check form-switch mb-0">
                                            <input class="form-check-input" type="checkbox" role="switch" id="switchAgendaJumat" name="agenda[Jumat][is_active]" value="1"
                                                   {{ old('agenda.Jumat.is_active', $agendaJumat?->is_active ?? false) ? 'checked' : '' }} style="cursor: pointer; width: 2.5em; height: 1.25em;"
                                                   {{ $agendaJumatLocked ? 'disabled' : '' }}>
                                            <label class="form-check-label fw-semibold text-dark ms-2" for="switchAgendaJumat" style="font-size: 0.85rem; cursor: pointer;">
                                                Kunci Slot Pembiasaan Jumat
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="text-muted small mb-3 p-2.5 rounded-3 bg-light border" style="font-size: 0.78rem;">
                                    <i class="bi bi-info-circle text-info me-1"></i>
                                    Mengunci slot jam ini secara otomatis di seluruh kelas untuk Pembiasaan (Yasinan/Senam/Jumat Bersih) pada hari Jumat.
                                </div>

                                <div id="warningJumat" class="small mb-3 p-2 rounded-3 text-warning-emphasis d-none" style="font-size: 0.78rem; background-color: #fff8e1; border: 1px solid #ffe082;">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i> ⚠️ Ada perubahan yang belum disimpan
                                </div>
                            </fieldset>
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-end pt-3 border-top mt-4 flex-wrap gap-3">
                    <div id="warningAgendaGlobal" class="small p-2 rounded-3 text-warning-emphasis d-none" style="font-size: 0.78rem; background-color: #fff8e1; border: 1px solid #ffe082;">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> ⚠️ Ada perubahan yang belum disimpan
                    </div>
                    <button type="submit" id="btnSimpanAgenda" class="btn btn-primary fw-bold px-4 py-2 rounded-3 d-inline-flex align-items-center justify-content-center gap-2 mt-3 {{ ($agendaSeninLocked && $agendaJumatLocked) ? 'opacity-50' : '' }}"
                            style="font-size: 0.85rem;"
                            title="{{ ($agendaSeninLocked && $agendaJumatLocked) ? 'Data ini adalah data pengujian IT dan tidak dapat diubah.' : 'Simpan pengaturan upacara bendera & pembiasaan dalam satu kali simpan' }}"
                            {{ ($agendaSeninLocked && $agendaJumatLocked) ? 'disabled' : '' }}>
                        <i class="bi bi-floppy-fill"></i> Simpan Upacara &amp; Pembiasaan
                    </button>
                </div>
            </form>
        </div>
    </div>
    </div>
</div>

{{-- ===================== MODAL TAMBAH JAM ===================== --}}
<div class="modal fade" id="modalTambahJam" tabindex="-1" aria-labelledby="modalTambahJamTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <form method="POST" action="{{ route('admin.jam-pelajaran.store') }}" id="formTambahJam">
                @csrf
                <input type="hidden" name="ta" value="{{ $selectedTahunAjaran?->id ?? '' }}">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="modalTambahJamTitle">
                        <i class="bi bi-plus-circle-fill text-primary me-2"></i>Tambah Jam Pelajaran
                        @if($selectedShift)
                            <span class="badge bg-dark-subtle text-dark rounded-pill px-2 py-1 align-middle ms-1" style="font-size: 0.68rem;">
                                <i class="bi bi-clock"></i> {{ $selectedShift->nama_shift }}
                            </span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2 py-1 align-middle ms-1" style="font-size: 0.68rem;">
                                🌐 Global
                            </span>
                        @endif
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark" style="font-size: 0.875rem;">Kategori Hari</label>
                        <select name="kategori_hari" id="tambahKategoriHari" class="form-select rounded-3" required>
                            <option value="Senin-Kamis" {{ $tab === 'Senin-Kamis' ? 'selected' : '' }}>Senin – Kamis</option>
                            <option value="Jumat" {{ $tab === 'Jumat' ? 'selected' : '' }}>Jumat</option>
                        </select>
                    </div>
                    @if($systemMode === 'shift' && !$shiftModeEmpty)
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark" style="font-size: 0.875rem;">
                            Shift
                            @if($selectedShift)
                                <span class="badge bg-dark-subtle text-dark rounded-pill px-2 py-1 align-middle ms-1" style="font-size: 0.62rem;">
                                    <i class="bi bi-clock"></i> default: {{ $selectedShift->nama_shift }} (sub-tab aktif)
                                </span>
                            @else
                                <span class="badge bg-info-subtle text-primary rounded-pill px-2 py-1 align-middle ms-1" style="font-size: 0.62rem;">
                                    <i class="bi bi-globe2"></i> default: Global
                                </span>
                            @endif
                        </label>
                        <select name="shift_id" id="tambahShiftId" class="form-select rounded-3">
                            <option value="">🌐 Global (berlaku semua kelas)</option>
                            @foreach($shifts as $shift)
                                <option value="{{ $shift->id }}" {{ $selectedShiftId == $shift->id ? 'selected' : '' }}>
                                    {{ $shift->nama_shift }} — {{ $shift->rentang_utama }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text text-muted" style="font-size: 0.76rem;">
                            Secara otomatis mengikuti sub-tab shift yang aktif. Slot dibuat untuk keempat hari
                            (Senin-Kamis) atau Jumat sesuai kategori, pada shift yang dipilih. Slot pada shift
                            terisolasi dari shift lain.
                        </div>
                    </div>
                    @endif
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold text-dark" style="font-size: 0.875rem;">Jam Mulai</label>
                            <input type="time" name="jam_mulai" id="tambahJamMulai" class="form-control rounded-3" step="60" autocomplete="off" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold text-dark" style="font-size: 0.875rem;">Jam Selesai</label>
                            <input type="time" name="jam_selesai" id="tambahJamSelesai" class="form-control rounded-3" step="60" autocomplete="off" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark" style="font-size: 0.875rem;">Jenis Slot</label>
                        <select name="jenis" id="tambahJenis" class="form-select rounded-3" required>
                            <option value="kbm">KBM (Kegiatan Belajar Mengajar)</option>
                            <option value="istirahat">Istirahat</option>
                        </select>
                    </div>
                    <div class="alert alert-light border d-flex align-items-start gap-2 rounded-3" style="font-size: 0.78rem;">
                        <i class="bi bi-info-circle-fill text-primary mt-1"></i>
                        <div>
                            Penomoran <strong>Jam Ke-</strong> dihitung otomatis berurutan per hari
                            <strong>dalam kelompok shift yang sama</strong> (Global &amp; setiap shift terpisah).
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4 fw-semibold">
                        <i class="bi bi-check-lg me-1"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ===================== MODAL GENERATE PRESET JAM ===================== --}}
<div class="modal fade" id="modalGeneratePreset" tabindex="-1" aria-labelledby="modalGeneratePresetTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow rounded-4"
             style="max-height: 85vh; display: flex; flex-direction: column; overflow: hidden;">
            <form method="POST" action="{{ route('admin.jam-pelajaran.generate') }}" id="formGeneratePreset"
                  style="display: flex; flex-direction: column; min-height: 0;">
                @csrf
                <input type="hidden" name="ta" value="{{ $selectedTahunAjaran?->id ?? '' }}">
                <input type="hidden" name="shift" id="presetShift" value="{{ $selectedShiftId ?? '' }}">
                <div class="modal-header border-0 pb-0" style="flex-shrink: 0;">
                    <h5 class="modal-title fw-bold" id="modalGeneratePresetTitle">
                        <i class="bi bi-lightning-charge-fill text-warning me-2"></i>Generate Preset Jam Pelajaran
                        @if($selectedShift)
                            <span class="badge bg-dark-subtle text-dark rounded-pill px-2 py-1 align-middle ms-1" style="font-size: 0.68rem;">
                                <i class="bi bi-clock"></i> {{ $selectedShift->nama_shift }}
                            </span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2 py-1 align-middle ms-1" style="font-size: 0.68rem;">
                                🌐 Global
                            </span>
                        @endif
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body pt-3" style="overflow-y: auto; min-height: 0;">
                    {{-- Peringatan overwrite --}}
                    <div class="alert alert-warning d-flex align-items-start gap-2 rounded-3"
                         style="font-size: 0.8rem;">
                        <i class="bi bi-exclamation-triangle-fill mt-1"></i>
                        <div>
                            <strong>Perhatian!</strong> Slot KBM akan <strong>diperbarui sesuai urutan Jam Ke-</strong>
                            (id slot dipertahankan sehingga jadwal pelajaran yang sudah di-plot di semester berjalan
                            <strong>tetap utuh</strong>). Jika jumlah JP dikurangi, jadwal pada jam yang dihilangkan akan
                            ikut terhapus (akan ada konfirmasi terlebih dahulu).
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark" style="font-size: 0.85rem;">Kategori Hari</label>
                            <select name="kategori_hari" id="presetKategoriHari" class="form-select rounded-3" required>
                                <option value="Senin-Kamis" {{ $tab === 'Senin-Kamis' ? 'selected' : '' }}>Senin – Kamis</option>
                                <option value="Jumat" {{ $tab === 'Jumat' ? 'selected' : '' }}>Jumat</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold text-dark" style="font-size: 0.85rem;">Durasi per JP (menit)</label>
                            <input type="number" name="durasi_jp" class="form-control rounded-3" min="1" max="120"
                                   value="{{ $tab === 'Jumat' ? 30 : 40 }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold text-dark" style="font-size: 0.85rem;">Jumlah JP (KBM)</label>
                            <input type="number" name="jumlah_jp" class="form-control rounded-3" min="1" max="20"
                                   value="{{ $tab === 'Jumat' ? 9 : 13 }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark" style="font-size: 0.85rem;">Jam Mulai</label>
                            <input type="time" name="jam_mulai" id="presetJamMulai" class="form-control rounded-3" step="60"
                                   value="{{ $selectedShift?->jam_mulai ? substr($selectedShift->jam_mulai, 0, 5) : '07:00' }}" autocomplete="off">
                            <div class="form-text text-muted" style="font-size: 0.72rem;">
                                <i class="bi bi-magic me-1"></i>Otomatis diisi berdasarkan Jam Mulai Utama
                                @if($selectedShift)
                                    (<strong>{{ $selectedShift->nama_shift }}</strong>)
                                @else
                                    (<strong>Global</strong>)
                                @endif
                                — ubah manual bila diperlukan.
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    {{-- Pengaturan Istirahat (Repeater) --}}
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="fw-bold text-dark mb-0" style="font-size: 0.95rem;">
                            <i class="bi bi-cup-hot-fill text-warning me-1"></i>Pengaturan Istirahat
                        </h6>
                        <button type="button" id="btnTambahIstirahat" class="btn btn-sm btn-outline-warning rounded-3 fw-semibold">
                            <i class="bi bi-plus-lg me-1"></i>Tambah Jam Istirahat
                        </button>
                    </div>
                    <p class="text-muted mb-3" style="font-size: 0.78rem;">
                        Tambahkan istirahat setelah jam KBM tertentu, dengan durasi bebas (mis. 15 atau 30 menit).
                        Bisa 0, 1, 2, atau lebih.
                    </p>

                    <div id="istirahatRows" class="d-flex flex-column gap-2">
                        {{-- Baris istirahat dinamis diisi via JS --}}
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0"
                     style="position: sticky; bottom: 0; background-color: #fff; z-index: 10; border-top: 1px solid #e5e7eb; flex-shrink: 0; margin-top: 1rem;">
                    <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning rounded-3 px-4 fw-semibold text-white">
                        <i class="bi bi-lightning-charge-fill me-1"></i> Generate
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ===================== MODAL EDIT JAM ===================== --}}
<div class="modal fade" id="modalEditJam" tabindex="-1" aria-labelledby="modalEditJamTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <form method="POST" id="formEditJam" action="">
                @csrf
                @method('PUT')
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="modalEditJamTitle">
                        <i class="bi bi-pencil-square text-warning me-2"></i>Edit Jam Pelajaran
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark" style="font-size: 0.875rem;">Kategori Hari</label>
                        <select name="kategori_hari" id="editKategoriHari" class="form-select rounded-3" required>
                            <option value="Senin-Kamis">Senin – Kamis</option>
                            <option value="Jumat">Jumat</option>
                        </select>
                    </div>
                    @if($systemMode === 'shift' && !$shiftModeEmpty)
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark" style="font-size: 0.875rem;">Shift</label>
                        <select name="shift_id" id="editShiftId" class="form-select rounded-3">
                            <option value="">🌐 Global (berlaku semua kelas)</option>
                            @foreach($shifts as $shift)
                                <option value="{{ $shift->id }}">{{ $shift->nama_shift }} — {{ $shift->rentang_utama }}</option>
                            @endforeach
                        </select>
                        <div class="form-text text-muted" style="font-size: 0.76rem;">
                            Pilih shift yang memiliki slot jam ini. "Global" dipakai kelas yang tidak terikat shift.
                        </div>
                    </div>
                    @endif
                    <div class="row g-3 mb-3">
                        <div class="col-4">
                            <label class="form-label fw-semibold text-dark" style="font-size: 0.875rem;">Jam Mulai</label>
                            <input type="time" name="jam_mulai" id="editJamMulai" class="form-control rounded-3" step="60" autocomplete="off" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label fw-semibold text-dark" style="font-size: 0.875rem;">Jam Selesai</label>
                            <input type="time" name="jam_selesai" id="editJamSelesai" class="form-control rounded-3" step="60" autocomplete="off" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label fw-semibold text-dark" style="font-size: 0.875rem;">Durasi (Menit)</label>
                            <input type="number" name="durasi" id="editDurasi" class="form-control rounded-3" min="1" max="600" step="1" value="40" autocomplete="off" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark" style="font-size: 0.875rem;">Jenis Slot</label>
                        <select name="jenis" id="editJenis" class="form-select rounded-3" required>
                            <option value="kbm">KBM (Kegiatan Belajar Mengajar)</option>
                            <option value="istirahat">Istirahat</option>
                        </select>
                    </div>
                    <div class="form-check border rounded-3 p-3" style="background-color: #fafafa;">
                        <input class="form-check-input" type="checkbox" name="auto_shift" id="autoShift" value="1" checked>
                        <label class="form-check-label fw-semibold text-dark" for="autoShift" style="font-size: 0.85rem; cursor: pointer;">
                            Geser/Sesuaikan jam slot berikutnya secara otomatis
                        </label>
                        <div class="form-text text-muted" style="font-size: 0.76rem;">
                            Jika dicentang, perubahan waktu pada slot ini akan otomatis menggeser jam mulai dan jam
                            selesai slot-slot setelahnya.
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning text-white rounded-3 px-4 fw-semibold" id="btnSubmitEdit">
                        <i class="bi bi-check-lg me-1"></i> Perbarui
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ===================== MODAL EDIT MASAL (BULK) ===================== --}}
<div class="modal fade" id="modalBulkEdit" tabindex="-1" aria-labelledby="modalBulkEditTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <form method="POST" action="{{ route('admin.jam-pelajaran.bulk-update') }}" id="formBulkEdit">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="modalBulkEditTitle">
                        <i class="bi bi-pencil-square text-primary me-2"></i>Edit Durasi Terpilih
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <div class="alert alert-info d-flex align-items-start gap-2 rounded-3" style="font-size: 0.8rem;">
                        <i class="bi bi-info-circle-fill mt-1"></i>
                        <div>
                            <strong><span id="bulkEditCount">0</span> slot jam pelajaran</strong> akan diperbarui
                            durasinya sekaligus. Jam selesai setiap slot dihitung ulang dari jam mulainya, lalu
                            timeline digeser otomatis agar tetap rapat berurutan.
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark" style="font-size: 0.875rem;">
                            Durasi per Kelompok Slot (menit) <span class="text-danger">*</span>
                        </label>
                        <div id="bulkGroupList" class="d-flex flex-column gap-3"></div>
                        <div class="form-text text-muted mt-2" style="font-size: 0.76rem;">
                            Setiap kelompok slot memiliki input durasi sendiri. Timeline jam dihitung ulang otomatis
                            agar tetap rapat berurutan.
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4 fw-semibold">
                        <i class="bi bi-check-lg me-1"></i> Terapkan ke <span id="bulkEditCountBtn">0</span> Slot
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ===================== MODAL KONFIRMASI HAPUS SEMUA SLOT ===================== --}}
<div class="modal fade" id="modalHapusSemuaJP" tabindex="-1" aria-labelledby="modalHapusSemuaJPTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <form method="POST" action="{{ route('admin.jam-pelajaran.destroy-all', ['kategori_hari' => $tab]) }}" id="formHapusSemuaJP">
                @csrf
                @method('DELETE')
                <input type="hidden" name="ta" value="{{ $selectedTahunAjaran?->id ?? '' }}">
                <input type="hidden" name="shift" value="{{ $selectedShiftId ?? '' }}">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-danger" id="modalHapusSemuaJPTitle">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Hapus Semua Slot ({{ $tab }})
                        @if($selectedShift)
                            <span class="badge bg-dark-subtle text-dark rounded-pill px-2 py-1 align-middle ms-1" style="font-size: 0.68rem;">
                                <i class="bi bi-clock"></i> {{ $selectedShift->nama_shift }}
                            </span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2 py-1 align-middle ms-1" style="font-size: 0.68rem;">
                                🌐 Global
                            </span>
                        @endif
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <p class="mb-0 text-dark" style="font-size: 0.875rem;">
                        Apakah Anda yakin ingin menghapus semua slot jam pelajaran untuk hari ini
                        (<strong>{{ $tab }}</strong>) pada shift <strong>{{ $selectedShift?->nama_shift ?? 'Global' }}</strong>?
                        Tindakan ini tidak dapat dibatalkan.
                    </p>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger rounded-3 px-4 fw-semibold">
                        <i class="bi bi-trash3-fill me-1"></i> Ya, Hapus Semua
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
{{-- ===================== MODAL TAMBAH SHIFT BARU (form saja, tanpa daftar) ===================== --}}
@if($systemMode === 'shift')
<div class="modal fade" id="modalShiftTambah" tabindex="-1" aria-labelledby="modalShiftTambahTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow rounded-4" style="max-height: 85vh; overflow: hidden;">
            <div class="modal-header border-0 pb-0" style="flex-shrink: 0;">
                <h5 class="modal-title fw-bold" id="modalShiftTambahTitle">
                    <i class="bi bi-plus-lg text-primary me-2"></i>Tambah Shift Baru
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-3" style="overflow-y: auto;">
                <form method="POST" action="{{ route('admin.shift-pelajaran.store') }}" id="formShiftTambah">
                    @csrf
                    <div class="card border-0 shadow-none bg-light-subtle rounded-3">
                        <div class="card-body p-3">
                            <p class="text-muted mb-3" style="font-size: 0.76rem;">
                                Jam Mulai Utama menjadi patokan awal KBM/preset shift. Jam selesai/pulang dihitung
                                <strong>otomatis</strong> dari slot jam pelajaran terakhir yang terdaftar pada shift.
                                Status Aktif menandakan shift sedang digunakan.
                            </p>
                            <div class="row g-3 align-items-end">
                                <div class="col-md-5">
                                    <label class="form-label fw-semibold text-dark mb-1" style="font-size: 0.85rem;">Nama Shift *</label>
                                    <input type="text" name="nama_shift" id="shiftNama" class="form-control rounded-3"
                                           maxlength="120" placeholder="cth: Shift 1 (Pagi)" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold text-dark mb-1" style="font-size: 0.85rem;">Jam Mulai Utama</label>
                                    <input type="time" name="jam_mulai" id="shiftMulai" class="form-control rounded-3" step="60" value="07:00">
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check form-switch ps-5">
                                        <input class="form-check-input" type="checkbox" name="is_active" id="shiftAktif" value="1" checked>
                                        <label class="form-check-label fw-semibold text-dark" for="shiftAktif" style="font-size: 0.82rem;">Aktif</label>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3">
                                <label class="form-label fw-semibold text-dark mb-1 d-flex align-items-center gap-2" style="font-size: 0.85rem;">
                                    <i class="bi bi-mortarboard text-primary"></i> Berlaku untuk Tingkatan Kelas
                                    <span class="text-muted fw-normal" style="font-size: 0.72rem;">(opsional — kosongkan bila berlaku untuk semua tingkatan)</span>
                                </label>
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach(\App\Models\ShiftPelajaran::GRADE_LEVELS as $grade)
                                        <div class="form-check form-check-inline mb-0">
                                            <input class="form-check-input grade-level-check" type="checkbox"
                                                   name="grade_levels[]" value="{{ $grade }}" id="gradeLevel_{{ $grade }}">
                                            <label class="form-check-label text-dark" for="gradeLevel_{{ $grade }}" style="font-size: 0.82rem;">
                                                {{ \App\Models\ShiftPelajaran::GRADE_LEVEL_FULL_LABELS[$grade] }}
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="form-text text-muted" style="font-size: 0.72rem;">
                                    Contoh: Shift 1 khusus <strong>Kelas 12</strong>; Shift 2 untuk <strong>Kelas 10 &amp; 11</strong>.
                                    Plotting jadwal kelas hanya valid bila tingkat kelas masuk daftar shift yang dialokasikan.
                                </div>
                            </div>
                            <div class="d-flex gap-2 mt-3">
                                <button type="submit" class="btn btn-primary rounded-3 px-4 fw-semibold">
                                    <i class="bi bi-check-lg me-1"></i> Simpan Shift
                                </button>
                                <button type="button" class="btn btn-light border rounded-3 px-3 fw-semibold" data-bs-dismiss="modal">
                                    Batal
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- ===================== MODAL KELOLA DAFTAR SHIFT (tabel + form edit kondisional) ===================== --}}
<div class="modal fade" id="modalShiftKelola" tabindex="-1" aria-labelledby="modalShiftKelolaTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow rounded-4" style="max-height: 85vh; overflow: hidden;">
            <div class="modal-header border-0 pb-0" style="flex-shrink: 0;">
                <h5 class="modal-title fw-bold" id="modalShiftKelolaTitle">
                    <i class="bi bi-gear text-primary me-2"></i>Kelola Daftar Shift
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-3" style="overflow-y: auto;">
                {{-- Form Edit Shift (tersembunyi default; tampil saat tombol Edit baris diklik) --}}
                <div id="kelolaEditFormWrap" class="d-none">
                    <form method="POST" action="{{ route('admin.shift-pelajaran.store') }}" id="formShiftKelola">
                        @csrf
                        <div id="kelolaMethodPlaceholder"></div>
                        <div class="card border-0 shadow-none bg-light-subtle rounded-3">
                            <div class="card-body p-3">
                                <h6 class="fw-bold text-dark mb-1" style="font-size: 0.9rem;">
                                    <i class="bi bi-pencil-square text-warning me-1"></i>Edit Shift
                                </h6>
                                <p class="text-muted mb-3" style="font-size: 0.76rem;">
                                    Perbarui data shift ini. Jam selesai/pulang tetap dihitung otomatis dari
                                    slot jam pelajaran terakhir yang terdaftar pada shift.
                                </p>
                                <div class="row g-3 align-items-end">
                                    <div class="col-md-5">
                                        <label class="form-label fw-semibold text-dark mb-1" style="font-size: 0.85rem;">Nama Shift *</label>
                                        <input type="text" name="nama_shift" id="kelolaShiftNama" class="form-control rounded-3"
                                               maxlength="120" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold text-dark mb-1" style="font-size: 0.85rem;">Jam Mulai Utama</label>
                                        <input type="time" name="jam_mulai" id="kelolaShiftMulai" class="form-control rounded-3" step="60">
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check form-switch ps-5">
                                            <input class="form-check-input" type="checkbox" name="is_active" id="kelolaShiftAktif" value="1">
                                            <label class="form-check-label fw-semibold text-dark" for="kelolaShiftAktif" style="font-size: 0.82rem;">Aktif</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <label class="form-label fw-semibold text-dark mb-1 d-flex align-items-center gap-2" style="font-size: 0.85rem;">
                                        <i class="bi bi-mortarboard text-primary"></i> Berlaku untuk Tingkatan Kelas
                                        <span class="text-muted fw-normal" style="font-size: 0.72rem;">(kosongkan bila berlaku untuk semua tingkatan)</span>
                                    </label>
                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach(\App\Models\ShiftPelajaran::GRADE_LEVELS as $grade)
                                            <div class="form-check form-check-inline mb-0">
                                                <input class="form-check-input kelola-grade-level-check" type="checkbox"
                                                       name="grade_levels[]" value="{{ $grade }}" id="kelolaGradeLevel_{{ $grade }}">
                                                <label class="form-check-label text-dark" for="kelolaGradeLevel_{{ $grade }}" style="font-size: 0.82rem;">
                                                    {{ \App\Models\ShiftPelajaran::GRADE_LEVEL_FULL_LABELS[$grade] }}
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="d-flex gap-2 mt-3">
                                    <button type="submit" class="btn btn-primary rounded-3 px-4 fw-semibold">
                                        <i class="bi bi-check-lg me-1"></i> Perbarui Shift
                                    </button>
                                    <button type="button" id="btnBatalEditKelola" class="btn btn-light border rounded-3 px-3 fw-semibold">
                                        Batal
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                {{-- Daftar Shift (tabel) --}}
                <div id="kelolaDaftarWrap">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="fw-bold text-dark mb-0" style="font-size: 0.9rem;">
                            <i class="bi bi-list-check text-primary me-1"></i>Daftar Shift ({{ $shifts->count() }})
                        </h6>
                        <button type="button"
                                class="btn btn-sm btn-primary rounded-3 fw-semibold"
                                style="font-size: 0.78rem;"
                                data-bs-toggle="modal" data-bs-target="#modalShiftTambah" data-bs-dismiss="modal">
                            <i class="bi bi-plus-lg me-1"></i>Tambah Shift Baru
                        </button>
                    </div>
                    @if($shifts->isEmpty())
                        <div class="alert alert-light border text-muted rounded-3 mb-0" style="font-size: 0.82rem;">
                            Belum ada shift terdaftar. Gunakan tombol <strong>Tambah Shift Baru</strong> untuk membuat
                            shift pertama (mis. <em>"Shift 1 (Pagi)"</em>).
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr class="text-uppercase text-muted" style="font-size: 0.68rem;">
                                        <th class="fw-semibold">Shift</th>
                                        <th class="fw-semibold">Jam Mulai</th>
                                        <th class="fw-semibold">Tingkatan</th>
                                        <th class="fw-semibold">Status</th>
                                        <th class="fw-semibold text-end">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($shifts as $shift)
                                        <tr>
                                            <td>
                                                <div class="fw-semibold text-dark" style="font-size: 0.85rem;">{{ $shift->nama_shift }}</div>
                                                @if($shift->jam_pelajaran_count > 0 || $shift->kelas_count > 0)
                                                    <div class="text-muted" style="font-size: 0.7rem;">
                                                        {{ $shift->jam_pelajaran_count }} slot · {{ $shift->kelas_count }} kelas
                                                    </div>
                                                @endif
                                            </td>
                                            <td>
                                                <div style="font-size: 0.8rem;">Mulai: {{ substr($shift->jam_mulai ?? '00:00:00', 0, 5) }}</div>
                                                @if($shift->jam_selesai_dinamis)
                                                    <div class="text-muted" style="font-size: 0.7rem;">
                                                        Selesai: {{ substr($shift->jam_selesai_dinamis, 0, 5) }} (slot JP terakhir)
                                                    </div>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge rounded-pill px-2 py-1"
                                                      style="font-size: 0.62rem; background-color: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe;">
                                                    <i class="bi bi-mortarboard-fill me-1"></i>{{ $shift->grade_levels_label }}
                                                </span>
                                            </td>
                                            <td>
                                                @if($shift->is_active)
                                                    <span class="badge bg-success-subtle text-success rounded-pill" style="font-size: 0.65rem;">Aktif</span>
                                                @else
                                                    <span class="badge bg-warning-subtle text-warning rounded-pill" style="font-size: 0.65rem;">Non-Aktif</span>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                <div class="d-inline-flex gap-1">
                                                    <button type="button"
                                                            class="btn btn-sm btn-outline-primary rounded-3 fw-semibold btn-edit-shift"
                                                            data-shift-id="{{ $shift->id }}"
                                                            data-nama="{{ $shift->nama_shift }}"
                                                            data-mulai="{{ substr($shift->jam_mulai ?? '00:00:00', 0, 5) }}"
                                                            data-aktif="{{ $shift->is_active ? 1 : 0 }}"
                                                            data-grade="{{ implode(',', $shift->grade_levels ?? []) }}">
                                                        <i class="bi bi-pencil-square me-1"></i>Edit
                                                    </button>
                                                    <button type="button"
                                                            class="btn btn-sm btn-outline-danger rounded-3 fw-semibold btn-hapus-shift"
                                                            data-shift-id="{{ $shift->id }}"
                                                            data-nama="{{ $shift->nama_shift }}"
                                                            data-slots="{{ $shift->jam_pelajaran_count }}"
                                                            data-kelas="{{ $shift->kelas_count }}"
                                                            data-pulang="{{ $jamPulangCountByShift[$shift->id] ?? 0 }}">
                                                        <i class="bi bi-trash3 me-1"></i>Hapus
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
            <div class="modal-footer border-0 pt-0" style="flex-shrink: 0;">
                <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endif

{{-- ===================== MODAL KONFIRMASI HAPUS SHIFT (dengan dampak berantai) ===================== --}}
<div class="modal fade" id="modalHapusShift" tabindex="-1" aria-labelledby="modalHapusShiftTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <form method="POST" action="" id="formHapusShift">
                @csrf
                @method('DELETE')
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-danger" id="modalHapusShiftTitle">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Hapus Shift Pelajaran
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <p class="mb-2 text-dark" style="font-size: 0.875rem;">
                        Apakah Anda yakin ingin menghapus shift
                        <strong id="hapusShiftNama">-</strong>?
                    </p>

                    {{-- Dampak berantai (cascade safety): slot/kelas dialihkan ke Global, jam pulang dihapus --}}
                    <div class="rounded-3 border p-3 mb-3" style="background-color: #eff6ff; border-color: #bfdbfe !important;">
                        <div class="fw-semibold text-dark mb-1" style="font-size: 0.85rem;">
                            <i class="bi bi-info-circle text-primary me-1"></i>Dampak Penghapusan:
                        </div>
                        <ul class="mb-0 ps-3 text-dark" style="font-size: 0.82rem;">
                            <li id="dampakSlots"><!-- diisi JavaScript --></li>
                            <li id="dampakKelas"><!-- diisi JavaScript --></li>
                            <li id="dampakPulang"><!-- diisi JavaScript --></li>
                        </ul>
                    </div>

                    <p class="mb-0 text-muted" style="font-size: 0.78rem;">
                        Tindakan ini tidak dapat dibatalkan.
                    </p>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger rounded-3 px-4 fw-semibold">
                        <i class="bi bi-trash3-fill me-1"></i> Ya, Hapus Shift
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<style>
    @keyframes btnPulse {
        0%   { transform: scale(1); }
        50%  { transform: scale(1.08); }
        100% { transform: scale(1); }
    }
</style>
<script>
    document.addEventListener('DOMContentLoaded', function () {

        const forms = [
            document.getElementById('formTambahJam'),
            document.getElementById('formEditJam')
        ];

        forms.forEach(function (form) {
            if (!form) return;

            const inputs = form.querySelectorAll('input:not([type="hidden"]), select');
            inputs.forEach(function (input, index) {
                input.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        if (index < inputs.length - 1) {
                            inputs[index + 1].focus();
                        }
                    }
                });
            });
        });

        // ===== Auto-fill "Jam Mulai" pada Modal Tambah berdasar slot terakhir =====
        const btnTambahJam     = document.getElementById('btnTambahJam');
        const tambahKategori   = document.getElementById('tambahKategoriHari');
        const tambahJamMulai   = document.getElementById('tambahJamMulai');

        if (btnTambahJam && tambahKategori && tambahJamMulai) {
            const fallbackMulai = { 'Senin-Kamis': '07:00', 'Jumat': '07:00' };

            function applyAutoMulai() {
                const kategori = tambahKategori.value;
                const value = (kategori === 'Jumat')
                    ? (btnTambahJam.dataset.mulaiJumat || fallbackMulai.Jumat)
                    : (btnTambahJam.dataset.mulaiSenin || fallbackMulai['Senin-Kamis']);
                tambahJamMulai.value = value;
            }

            // Saat modal dibuka
            document.getElementById('modalTambahJam').addEventListener('shown.bs.modal', applyAutoMulai);

            // Saat kategori hari diubah di dalam modal
            tambahKategori.addEventListener('change', applyAutoMulai);
        }

        // ===== Modal Edit: Two-way real-time kalkulasi Durasi <-> Jam Selesai =====
        const editMulai   = document.getElementById('editJamMulai');
        const editSelesai = document.getElementById('editJamSelesai');
        const editDurasi  = document.getElementById('editDurasi');

        function minutesToTime(totalMin) {
            totalMin = ((totalMin % 1440) + 1440) % 1440; // jaga-jaga jika minus/lewat tengah malam
            const hh = String(Math.floor(totalMin / 60)).padStart(2, '0');
            const mm = String(totalMin % 60).padStart(2, '0');
            return hh + ':' + mm;
        }

        function timeToMinutes(val) {
            if (!val) return null;
            const p = val.split(':');
            return parseInt(p[0], 10) * 60 + parseInt(p[1], 10);
        }

        if (editMulai && editSelesai && editDurasi) {
            // Durasi berubah -> hitung ulang Jam Selesai = Jam Mulai + Durasi
            editDurasi.addEventListener('input', function () {
                const mulaiMin = timeToMinutes(editMulai.value);
                const durasi   = parseInt(editDurasi.value, 10);
                if (mulaiMin === null || isNaN(durasi) || durasi < 1) return;
                editSelesai.value = minutesToTime(mulaiMin + durasi);
            });

            // Jam Selesai berubah -> hitung ulang Durasi = selisih menit
            editSelesai.addEventListener('change', function () {
                const mulaiMin   = timeToMinutes(editMulai.value);
                const selesaiMin = timeToMinutes(editSelesai.value);
                if (mulaiMin === null || selesaiMin === null) return;
                const diff = ((selesaiMin - mulaiMin) % 1440 + 1440) % 1440;
                editDurasi.value = diff;
            });

            // Jam Mulai berubah -> ikut hitung ulang durasi dari Jam Selesai yang ada
            editMulai.addEventListener('change', function () {
                const mulaiMin   = timeToMinutes(editMulai.value);
                const selesaiMin = timeToMinutes(editSelesai.value);
                if (mulaiMin === null || selesaiMin === null) return;
                const diff = ((selesaiMin - mulaiMin) % 1440 + 1440) % 1440;
                editDurasi.value = diff;
            });
        }

        // ===== Generate Preset: Repeater Istirahat Dinamis =====
        const presetForm       = document.getElementById('formGeneratePreset');
        const presetKategori   = document.getElementById('presetKategoriHari');
        const btnTambahIstirahat = document.getElementById('btnTambahIstirahat');
        const istirahatRows    = document.getElementById('istirahatRows');
        let istirahatIndex     = 0;

        const presetDefaults = {
            'Senin-Kamis': { durasi: 40, jumlah: 13 },
            'Jumat':       { durasi: 30, jumlah: 9 },
        };

        function applyPresetDefaults() {
            if (!presetKategori) return;
            const d = presetDefaults[presetKategori.value] || presetDefaults['Senin-Kamis'];
            const durasiInput = presetForm.querySelector('[name="durasi_jp"]');
            const jumlahInput = presetForm.querySelector('[name="jumlah_jp"]');
            if (durasiInput && !durasiInput.dataset.touched) durasiInput.value = d.durasi;
            if (jumlahInput && !jumlahInput.dataset.touched) jumlahInput.value = d.jumlah;
        }

        function addIstirahatRow(afterJam, duration) {
            if (!istirahatRows) return;
            istirahatIndex++;

            const jumlahJp = parseInt((presetForm.querySelector('[name="jumlah_jp"]')?.value) || 13, 10);

            let options = '<option value="">— Pilih Jam —</option>';
            for (let j = 1; j <= jumlahJp; j++) {
                options += '<option value="' + j + '"' + (String(afterJam) === String(j) ? ' selected' : '') + '>Setelah Jam Ke-' + j + '</option>';
            }

            const row = document.createElement('div');
            row.className = 'istirahat-row d-flex align-items-end gap-2 p-2 rounded-3 border bg-light-subtle';
            row.dataset.index = istirahatIndex;
            row.innerHTML =
                '<div class="flex-grow-1">' +
                    '<label class="form-label fw-semibold mb-1" style="font-size:0.75rem;">Setelah Jam Ke-</label>' +
                    '<select name="breaks[' + istirahatIndex + '][after_jam]" class="form-select form-select-sm rounded-3">' + options + '</select>' +
                '</div>' +
                '<div style="width:130px;">' +
                    '<label class="form-label fw-semibold mb-1" style="font-size:0.75rem;">Durasi (menit)</label>' +
                    '<input type="number" name="breaks[' + istirahatIndex + '][duration]" class="form-control form-control-sm rounded-3" min="1" max="120" value="' + (duration || 15) + '">' +
                '</div>' +
                '<div>' +
                    '<button type="button" class="btn btn-sm btn-outline-danger rounded-3" title="Hapus Baris">' +
                        '<i class="bi bi-trash3"></i>' +
                    '</button>' +
                '</div>';

            // Hapus baris
            row.querySelector('button').addEventListener('click', function () {
                row.remove();
                // nilai awal selected tidak lagi terdaftar untuk btn tambah; biarkan blank -> kosongkan baris lain? tidak perlu
            });

            istirahatRows.appendChild(row);
        }

        if (presetForm && presetKategori && btnTambahIstirahat && istirahatRows) {
            presetKategori.addEventListener('change', applyPresetDefaults);

            btnTambahIstirahat.addEventListener('click', function () {
                addIstirahatRow('', 15);
            });

            // Saat jumlah JP berubah, perbarui opsi "Setelah Jam Ke-" pada baris yang ada
            const jumlahInput = presetForm.querySelector('[name="jumlah_jp"]');
            if (jumlahInput) {
                jumlahInput.addEventListener('change', function () {
                    jumlahInput.dataset.touched = '1';
                    const newJumlah = parseInt(jumlahInput.value, 10) || 1;
                    istirahatRows.querySelectorAll('.istirahat-row').forEach(function (row) {
                        const sel = row.querySelector('select[name$="[after_jam]"]');
                        const current = sel.value;
                        let opts = '<option value="">— Pilih Jam —</option>';
                        for (let j = 1; j <= newJumlah; j++) {
                            opts += '<option value="' + j + '"' + (String(current) === String(j) ? ' selected' : '') + '>Setelah Jam Ke-' + j + '</option>';
                        }
                        sel.innerHTML = opts;
                    });
                });
            }

            // Saat modal dibuka: atur default sesuai kategori terpilih & kosongkan istirahat
            document.getElementById('modalGeneratePreset').addEventListener('shown.bs.modal', function () {
                presetKategori.value = "{{ $tab }}";
                const durasiInput = presetForm.querySelector('[name="durasi_jp"]');
                const jumlahInput = presetForm.querySelector('[name="jumlah_jp"]');
                const jamMulaiInput = presetForm.querySelector('[name="jam_mulai"]');
                if (durasiInput) durasiInput.dataset.touched = '';
                if (jumlahInput) jumlahInput.dataset.touched = '';
                applyPresetDefaults();
                // Nilai awal Jam Mulai mengikuti Jam Mulai Utama shift aktif (07:00 di Mode Global).
                if (jamMulaiInput) {
                    jamMulaiInput.value = "{{ $selectedShift?->jam_mulai ? substr($selectedShift->jam_mulai, 0, 5) : '07:00' }}";
                }
                istirahatRows.innerHTML = '';
                istirahatIndex = 0;
            });

            // Cek dampak pengurangan slot sebelum submit: peringatkan jika ada jadwal ter-plot
            presetForm.addEventListener('submit', function (e) {
                e.preventDefault();
                if (!presetForm.reportValidity()) return;

                const kategori = presetKategori.value;
                const jumlahInput = presetForm.querySelector('[name="jumlah_jp"]');
                const jumlah = parseInt(jumlahInput?.value || '0', 10);

                fetch("{{ route('admin.jam-pelajaran.generate-check') }}" +
                    '?kategori_hari=' + encodeURIComponent(kategori) +
                    '&jumlah_jp=' + encodeURIComponent(jumlah) +
                    '&shift=' + encodeURIComponent(document.getElementById('presetShift')?.value || '') +
                    '&ta=' + encodeURIComponent("{{ $selectedTahunAjaran?->id ?? '' }}"))
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        if (data.affected_jam_ke && data.affected_jam_ke.length > 0 && data.plotted_count > 0) {
                            const jamList = data.affected_jam_ke.join(' & ');
                            const semester = data.semester ? ' (' + data.semester + ')' : '';
                            const pesan = 'Slot Jam Ke-' + jamList +
                                ' berisi jadwal pelajaran' + semester +
                                '.\n\nMengurangi slot akan menghapus jadwal di jam tersebut.\n\nLanjutkan Generate Preset?';
                            if (!window.confirm(pesan)) return;
                        }
                        presetForm.submit();
                    })
                    .catch(function () {
                        presetForm.submit();
                    });
            });
        }

        // ===== Badge Status Dinamis & Dirty State: Upacara (Senin) & Pembiasaan (Jumat) =====
        // Satu form gabungan menyimpan kedua pengaturan sekaligus (satu tombol: Simpan Upacara & Pembiasaan).
        const agendaForm = document.getElementById('formAgendaRutin');
        const agendaButton = document.getElementById('btnSimpanAgenda');
        const warningAgendaGlobal = document.getElementById('warningAgendaGlobal');

        const agendaConfigs = {
            Senin: {
                badge:   document.getElementById('badgeStatusSenin'),
                toggle:  document.getElementById('switchAgendaSenin'),
                select:  document.getElementById('jamKeSenin'),
                warning: document.getElementById('warningSenin'),
            },
            Jumat: {
                badge:   document.getElementById('badgeStatusJumat'),
                toggle:  document.getElementById('switchAgendaJumat'),
                select:  document.getElementById('jamKeJumat'),
                warning: document.getElementById('warningJumat'),
            }
        };

        const BADGE_ACTIVE  = 'badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1';
        const BADGE_INACTIVE= 'badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-3 py-1';
        const BADGE_DIRTY   = 'badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-3 py-1';

        function badgeText(cfg) {
            return cfg.toggle.checked
                ? (cfg.select.value
                    ? '● Aktif (Terkunci Jam Ke-' + cfg.select.value + ')'
                    : '● Aktif (harap pilih Jam Ke-)')
                : '○ Non-Aktif';
        }

        // Tampilkan badge sesuai status + dirty state
        function renderBadge(key) {
            const cfg = agendaConfigs[key];
            if (!cfg || !cfg.badge) return;

            cfg.badge.style.fontSize = '0.72rem';

            if (cfg.dirty) {
                cfg.badge.className = BADGE_DIRTY;
                cfg.badge.textContent = '🟡 Belum Disimpan';
            } else {
                cfg.badge.className = cfg.toggle.checked ? BADGE_ACTIVE : BADGE_INACTIVE;
                cfg.badge.textContent = badgeText(cfg);
            }

            // Peringatan dalam kartu
            if (cfg.warning) cfg.warning.classList.toggle('d-none', !cfg.dirty);
        }

        // Highlight tombol simpan bersama + peringatan global bila ada kartu yang berubah
        function renderAgendaButton() {
            if (!agendaButton) return;

            const anyDirty = Object.keys(agendaConfigs).some(function (key) {
                return agendaConfigs[key].dirty;
            });

            if (!agendaButton.dataset.originalClass) {
                agendaButton.dataset.originalClass = Array.from(agendaButton.classList)
                    .find(function (c) { return c.indexOf('btn-') === 0 && c !== 'btn-warning'; });
            }
            if (anyDirty) {
                agendaButton.classList.add('btn-warning');
                if (agendaButton.dataset.originalClass) agendaButton.classList.remove(agendaButton.dataset.originalClass);
                agendaButton.style.animation = 'btnPulse 1.2s ease-in-out infinite';
            } else {
                agendaButton.classList.remove('btn-warning');
                if (agendaButton.dataset.originalClass) agendaButton.classList.add(agendaButton.dataset.originalClass);
                agendaButton.style.animation = '';
            }
            if (warningAgendaGlobal) warningAgendaGlobal.classList.toggle('d-none', !anyDirty);
        }

        // Simpan nilai awal (initial state) dan render ulang
        function captureInitial(key) {
            const cfg = agendaConfigs[key];
            if (!cfg || !cfg.toggle || !cfg.select) return;
            cfg.initialToggle = cfg.toggle.checked;
            cfg.initialSelect = cfg.select.value;
            cfg.dirty = false;
            renderBadge(key);
        }

        function setDirty(key, dirty) {
            const cfg = agendaConfigs[key];
            if (!cfg) return;
            cfg.dirty = dirty;
            renderBadge(key);
            renderAgendaButton();
        }

        // Deteksi perubahan (dirty state) pada dropdown jam & toggle
        function bindAgendaCard(key) {
            const cfg = agendaConfigs[key];
            if (!cfg || !cfg.toggle || !cfg.select) return;

            const checkDirty = function () {
                const isDirty = (cfg.toggle.checked !== cfg.initialToggle) || (cfg.select.value !== cfg.initialSelect);
                setDirty(key, isDirty);
            };

            cfg.toggle.addEventListener('change', checkDirty);
            cfg.select.addEventListener('change', checkDirty);
        }

        // Reset state kedua kartu saat submit form gabungan (POST/reload) sebelum dikirim
        if (agendaForm) {
            agendaForm.addEventListener('submit', function () {
                Object.keys(agendaConfigs).forEach(function (key) {
                    const cfg = agendaConfigs[key];
                    if (!cfg) return;
                    cfg.initialToggle = cfg.toggle.checked;
                    cfg.initialSelect = cfg.select.value;
                    cfg.dirty = false;
                    renderBadge(key);
                });
                renderAgendaButton();
            });
        }

        // Inisialisasi: simpan nilai awal untuk kedua kartu
        Object.keys(agendaConfigs).forEach(function (key) {
            captureInitial(key);
            bindAgendaCard(key);
        });

        // ===== Pengaturan Jam Pulang: Badge Sync + Auto-Save =====
        const jpForm      = document.getElementById('formJamPulang');
        const jpIndicator = document.getElementById('jpSaveIndicator');
        const csrfToken   = (function () {
            const meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.getAttribute('content') : '';
        })();

        function renderJamPulangBadges() {
            document.querySelectorAll('.jam-pulang-select').forEach(function (select) {
                const shift    = select.dataset.shift;
                const kategori = select.dataset.kategori;
                const tingkat  = select.dataset.tingkat;
                const badge = document.querySelector('.jam-pulang-badge[data-shift="' + shift + '"][data-kategori="' + kategori + '"][data-tingkat="' + tingkat + '"]');
                if (!badge) return;

                const val = select.value;
                badge.innerHTML = val
                    ? '<span class="badge text-bg-danger rounded-pill">Batas: Jam ' + val + '</span>'
                    : '<span class="badge text-bg-info rounded-pill">Semua Slot</span>';
            });
        }

        function setJpIndicator(visible) {
            if (!jpIndicator) return;
            jpIndicator.classList.toggle('d-none', !visible);
            jpIndicator.classList.toggle('d-inline-flex', visible);
        }

        // Kirim seluruh form Jam Pulang (semua tingkat/kategori) dalam satu request AJAX.
        // Nilai yang baru dipilih ikut tersimpan; pengaturan lainnya tetap utuh di server.
        async function autoSaveJamPulang(select) {
            if (!jpForm) return;

            const tingkat    = select.dataset.tingkat || '';
            const kategori   = select.dataset.kategori || '';
            const valueLabel = select.value ? 'Jam Ke-' + select.value : 'tidak dibatasi';
            const hariLabel  = kategori === 'Jumat' ? ' (hari Jumat)' : '';

            setJpIndicator(true);

            try {
                const res = await fetch(jpForm.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: new FormData(jpForm),
                });

                if (!res.ok) {
                    let msg = 'Gagal menyimpan pengaturan jam pulang.';
                    try {
                        const j = await res.json();
                        if (j.message) msg = j.message;
                    } catch (e) { /* body non-JSON (mis. halaman error) */ }
                    select.value = select.dataset.initial; // kembalikan pilihan
                    renderJamPulangBadges();
                    showToast(msg, 'danger');
                    return;
                }

                select.dataset.initial = select.value;
                renderJamPulangBadges();
                showToast('Batas jam pulang Kelas ' + tingkat + hariLabel + ' berhasil diperbarui (' + valueLabel + ').', 'success');
            } catch (err) {
                select.value = select.dataset.initial;
                renderJamPulangBadges();
                showToast('Gagal terhubung ke server. Pengaturan tidak tersimpan.', 'danger');
            } finally {
                setJpIndicator(false);
            }
        }

        if (jpForm) {
            document.querySelectorAll('.jam-pulang-select').forEach(function (select) {
                // Simpan nilai awal (initial state) dari DB
                select.dataset.initial = select.value;

                select.addEventListener('change', function () {
                    renderJamPulangBadges();
                    autoSaveJamPulang(select);
                });
            });

            renderJamPulangBadges();
        }

        // ===== Toast feedback (Auto-Save Jam Pulang & aksi simpan) =====
        function showToast(message, type) {
            const container = document.getElementById('toastJamPelajaranContainer');
            if (!container || typeof bootstrap === 'undefined') {
                window.alert(message);
                return;
            }
            const color = (type === 'danger' || type === 'error') ? 'danger' : (type === 'success' ? 'success' : 'warning');
            const icon = color === 'danger' ? 'bi-exclamation-triangle-fill'
                : (color === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill');
            const el = document.createElement('div');
            el.className = 'toast align-items-center text-bg-' + color + ' border-0 shadow rounded-3';
            el.setAttribute('role', 'alert');
            el.setAttribute('aria-live', 'assertive');
            el.setAttribute('aria-atomic', 'true');
            el.innerHTML = '<div class="d-flex"><div class="toast-body fw-semibold" style="font-size:0.82rem;">' +
                '<i class="bi ' + icon + ' me-2"></i>' + message +
                '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Tutup"></button></div>';
            container.appendChild(el);
            const toast = new bootstrap.Toast(el, { delay: 6000 });
            el.addEventListener('hidden.bs.toast', function () { el.remove(); });
            toast.show();
        }

        // ===== Modal Tambah Shift Baru: reset form ke default saat dibuka =====
        const modalShiftTambah  = document.getElementById('modalShiftTambah');
        const tambahShiftNama   = document.getElementById('shiftNama');
        const tambahShiftMulai  = document.getElementById('shiftMulai');
        const tambahShiftAktif  = document.getElementById('shiftAktif');

        function resetTambahShiftForm() {
            if (tambahShiftNama) tambahShiftNama.value = '';
            if (tambahShiftMulai) tambahShiftMulai.value = '07:00';
            if (tambahShiftAktif) tambahShiftAktif.checked = true;
            document.querySelectorAll('#modalShiftTambah .grade-level-check').forEach(function (cb) {
                cb.checked = false;
            });
        }

        if (modalShiftTambah) {
            modalShiftTambah.addEventListener('shown.bs.modal', resetTambahShiftForm);
        }

        // ===== Modal Kelola Shift: tabel + form edit kondisional =====
        const modalShiftKelola     = document.getElementById('modalShiftKelola');
        const kelolaEditFormWrap   = document.getElementById('kelolaEditFormWrap');
        const kelolaDaftarWrap     = document.getElementById('kelolaDaftarWrap');
        const kelolaForm           = document.getElementById('formShiftKelola');
        const kelolaMethodPh       = document.getElementById('kelolaMethodPlaceholder');
        const kelolaShiftNama      = document.getElementById('kelolaShiftNama');
        const kelolaShiftMulai     = document.getElementById('kelolaShiftMulai');
        const kelolaShiftAktif     = document.getElementById('kelolaShiftAktif');

        function showKelolaMode(showEdit) {
            if (kelolaEditFormWrap) kelolaEditFormWrap.classList.toggle('d-none', !showEdit);
            if (kelolaDaftarWrap) kelolaDaftarWrap.classList.toggle('d-none', showEdit);
        }

        document.querySelectorAll('.btn-edit-shift').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const id    = btn.dataset.shiftId;
                const nama  = btn.dataset.nama;
                const mulai = btn.dataset.mulai;
                const aktif = btn.dataset.aktif === '1';

                if (kelolaForm) kelolaForm.action = "{{ url('admin/shift-pelajaran') }}/" + id;
                if (kelolaMethodPh) {
                    kelolaMethodPh.innerHTML = '<input type="hidden" name="_method" value="PUT">';
                }
                if (kelolaShiftNama) kelolaShiftNama.value = nama;
                if (kelolaShiftMulai) kelolaShiftMulai.value = mulai;
                if (kelolaShiftAktif) kelolaShiftAktif.checked = aktif;
                const selectedGrades = (btn.dataset.grade || '').split(',').filter(Boolean);
                document.querySelectorAll('#modalShiftKelola .kelola-grade-level-check').forEach(function (cb) {
                    cb.checked = selectedGrades.includes(cb.value);
                });

                showKelolaMode(true);
            });
        });

        const btnBatalEditKelola = document.getElementById('btnBatalEditKelola');
        if (btnBatalEditKelola) {
            btnBatalEditKelola.addEventListener('click', function () {
                showKelolaMode(false);
            });
        }

        if (modalShiftKelola) {
            modalShiftKelola.addEventListener('hidden.bs.modal', function () {
                showKelolaMode(false);
            });
        }

        // ===== Konfirmasi hapus shift: isi modal dampak berantai & arahkan form DELETE =====
        const modalHapusShift = document.getElementById('modalHapusShift');
        const formHapusShift  = document.getElementById('formHapusShift');
        const hapusShiftNama  = document.getElementById('hapusShiftNama');
        const dampakSlots     = document.getElementById('dampakSlots');
        const dampakKelas     = document.getElementById('dampakKelas');
        const dampakPulang    = document.getElementById('dampakPulang');

        document.querySelectorAll('.btn-hapus-shift').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const id   = btn.dataset.shiftId;
                const nama = btn.dataset.nama;
                const s    = parseInt(btn.dataset.slots || '0', 10);
                const k    = parseInt(btn.dataset.kelas || '0', 10);
                const p    = parseInt(btn.dataset.pulang || '0', 10);

                if (hapusShiftNama) hapusShiftNama.textContent = nama;
                if (formHapusShift) formHapusShift.action = "{{ url('admin/shift-pelajaran') }}/" + id;

                if (dampakSlots) {
                    dampakSlots.innerHTML = s > 0
                        ? '<strong>' + s + ' slot jam pelajaran</strong> yang terikat akan <strong>dialihkan ke Mode Global</strong> (tidak ikut terhapus).'
                        : 'Tidak ada slot jam pelajaran yang terikat shift ini.';
                }
                if (dampakKelas) {
                    dampakKelas.innerHTML = k > 0
                        ? '<strong>' + k + ' kelas</strong> yang terikat akan <strong>dialihkan ke Mode Global</strong> (tidak ikut terhapus).'
                        : 'Tidak ada kelas yang terikat shift ini.';
                }
                if (dampakPulang) {
                    dampakPulang.innerHTML = p > 0
                        ? '<strong>' + p + ' pengaturan jam pulang</strong> milik shift ini akan <strong>dihapus</strong>.'
                        : 'Tidak ada pengaturan jam pulang khusus shift ini.';
                }

                if (modalHapusShift) {
                    bootstrap.Modal.getOrCreateInstance(modalHapusShift).show();
                }
            });
        });

        // ===== Edit cepat shift dari sub-tab strip (ikon pensil pada pill aktif) =====
        document.querySelectorAll('.btn-edit-shift-strip').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const modalEl = document.getElementById('modalShiftKelola');
                if (!modalEl) return;

                bootstrap.Modal.getOrCreateInstance(modalEl).show();

                // Delegasi ke tombol Edit yang ada di dalam daftar modal (mengisi form PUT).
                const listBtn = document.querySelector('.btn-edit-shift[data-shift-id="' + btn.dataset.shiftId + '"]');
                if (listBtn) listBtn.click();
            });
        });

        // ===== Bulk Action: Select All & Mass Action (Edit/Hapus Terpilih) =====
        const selectAll       = document.getElementById('select-all');
        const bulkBar         = document.getElementById('bulkActionBar');
        const bulkCount       = document.getElementById('bulkCount');
        const btnBulkEdit     = document.getElementById('btnBulkEdit');
        const btnBulkHapus    = document.getElementById('btnBulkHapus');
        const btnBulkBatal    = document.getElementById('btnBulkBatal');
        const modalBulkEdit   = document.getElementById('modalBulkEdit');
        const formBulkEdit    = document.getElementById('formBulkEdit');
        const bulkGroupList   = document.getElementById('bulkGroupList');
        let bulkSelectedIds   = [];
        let bulkGroups        = []; // [{ jenis, slots: [{id, jenis, jamKe, label, durasi}] }]

        function getAllBulkBoxes() {
            return Array.prototype.slice.call(document.querySelectorAll('.jp-checkbox'));
        }

        function getSelectedBulkIds() {
            return getAllBulkBoxes()
                .filter(function (cb) { return cb.checked; })
                .map(function (cb) { return cb.value; });
        }

        function updateBulkUI() {
            const boxes  = getAllBulkBoxes();
            const ids    = getSelectedBulkIds();
            const count  = ids.length;

            // Sinkronkan "select-all" (checked penuh / indeterminate sebagian)
            if (selectAll) {
                selectAll.checked = boxes.length > 0 && count === boxes.length;
                selectAll.indeterminate = count > 0 && count < boxes.length;
            }

            // Tampilkan/sembunyikan bulk action bar
            if (bulkBar) {
                const show = count > 0;
                bulkBar.classList.toggle('d-none', !show);
                bulkBar.classList.toggle('d-flex', show);
                if (bulkCount) bulkCount.textContent = count;
            }
        }

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                getAllBulkBoxes().forEach(function (cb) { cb.checked = selectAll.checked; });
                updateBulkUI();
            });
        }

        getAllBulkBoxes().forEach(function (cb) {
            cb.addEventListener('change', updateBulkUI);
        });

        // Kelompokkan slot tercentang berdasarkan run urutan jenis (KBM/istirahat)
        function buildBulkGroups() {
            const groups = [];
            getAllBulkBoxes()
                .filter(function (cb) { return cb.checked; })
                .forEach(function (cb) {
                    const jenis = cb.getAttribute('data-jenis') || 'kbm';
                    const slot = {
                        id: cb.value,
                        jenis: jenis,
                        jamKe: parseInt(cb.getAttribute('data-jam-ke'), 10),
                        label: cb.getAttribute('data-label')
                            || (jenis === 'istirahat' ? 'Istirahat' : 'Jam ' + cb.getAttribute('data-jam-ke') + ' (KBM)'),
                        durasi: parseInt(cb.getAttribute('data-durasi'), 10),
                    };
                    const last = groups[groups.length - 1];
                    if (last && last.jenis === jenis) {
                        last.slots.push(slot);
                    } else {
                        groups.push({ jenis: jenis, slots: [slot] });
                    }
                });
            return groups;
        }

        function buildGroupLabel(group) {
            if (!group || group.slots.length === 0) return '';
            if (group.slots.length === 1) return group.slots[0].label;
            if (group.jenis === 'kbm') {
                const nums = group.slots
                    .map(function (s) { return s.jamKe; })
                    .filter(function (n) { return Number.isFinite(n); })
                    .sort(function (a, b) { return a - b; });
                if (nums.length > 0) {
                    return 'Jam ' + nums[0] + ' - Jam ' + nums[nums.length - 1] + ' (KBM)';
                }
            }
            return group.slots[0].label + ' - ' + group.slots[group.slots.length - 1].label;
        }

        // Render satu input durasi per kelompok slot di dalam modal
        function renderBulkGroups(groups) {
            if (!bulkGroupList) return;
            bulkGroupList.innerHTML = '';

            groups.forEach(function (group) {
                const firstDurasi = group.slots[0].durasi;
                const variatif = group.slots.some(function (s) { return s.durasi !== firstDurasi; });

                const item = document.createElement('div');
                item.className = 'bulk-group border rounded-3 p-3';
                item.style.backgroundColor = '#fcfcfd';

                const head = document.createElement('div');
                head.className = 'd-flex flex-wrap justify-content-between align-items-center gap-2 mb-2';
                const label = document.createElement('label');
                label.className = 'fw-semibold text-dark mb-0';
                label.style.fontSize = '0.82rem';
                label.textContent = buildGroupLabel(group);
                const badge = document.createElement('span');
                badge.className = 'text-muted small';
                badge.textContent = group.slots.length + ' slot';
                head.appendChild(label);
                head.appendChild(badge);

                const input = document.createElement('input');
                input.type = 'number';
                input.min = '1';
                input.max = '600';
                input.step = '1';
                input.autocomplete = 'off';
                input.required = true;
                input.className = 'form-control rounded-3 bulk-group-durasi';
                if (variatif) {
                    input.placeholder = 'Variatif (Isi durasi baru)';
                } else if (Number.isFinite(firstDurasi)) {
                    input.value = firstDurasi;
                }

                item.appendChild(head);
                item.appendChild(input);
                bulkGroupList.appendChild(item);
            });
        }

        // Tombol "Edit Terpilih": kunci ID terpilih, kelompokkan & buka modal bulk edit
        if (btnBulkEdit && modalBulkEdit) {
            btnBulkEdit.addEventListener('click', function () {
                bulkSelectedIds = getSelectedBulkIds();
                if (bulkSelectedIds.length === 0) return;

                const lblCount     = document.getElementById('bulkEditCount');
                const lblCountBtn  = document.getElementById('bulkEditCountBtn');
                if (lblCount)    lblCount.textContent    = bulkSelectedIds.length;
                if (lblCountBtn) lblCountBtn.textContent = bulkSelectedIds.length;

                // Kelompokkan slot tercentang (run urutan jenis) & render input per kelompok
                bulkGroups = buildBulkGroups();
                renderBulkGroups(bulkGroups);

                const modal = new bootstrap.Modal(modalBulkEdit);
                modal.show();
            });
        }

        // Submit form bulk edit: bangun payload updates[] dari durasi per kelompok slot
        if (formBulkEdit) {
            formBulkEdit.addEventListener('submit', function (e) {
                e.preventDefault();
                if (!formBulkEdit.reportValidity()) return;

                // Baca durasi dari tiap kelompok (urutan render = urutan kelompok)
                const inputs = Array.prototype.slice.call(
                    document.querySelectorAll('#bulkGroupList .bulk-group-durasi')
                );
                const durasis = inputs.map(function (inp) {
                    return parseInt(inp.value, 10);
                });

                // Validasi: semua kelompok wajib terisi durasi valid (1-600)
                if (durasis.length === 0) return;
                if (durasis.some(function (d) { return !Number.isFinite(d) || d < 1 || d > 600; })) return;

                // Hapus input tersembunyi lama (jika ada)
                formBulkEdit.querySelectorAll('input[type="hidden"][name^="updates"]').forEach(function (el) {
                    el.remove();
                });

                // Expose payload tambahan agar durable (untuk kompatibilitas XHR)
                let idx = 0;
                durasis.forEach(function (durasi, gi) {
                    const group = bulkGroups[gi];
                    if (!group) return;
                    group.slots.forEach(function (slot) {
                        const hid = document.createElement('input');
                        hid.type = 'hidden';
                        hid.name = 'updates[' + idx + '][id]';
                        hid.value = slot.id;
                        formBulkEdit.appendChild(hid);

                        const hd = document.createElement('input');
                        hd.type = 'hidden';
                        hd.name = 'updates[' + idx + '][durasi]';
                        hd.value = durasi;
                        formBulkEdit.appendChild(hd);

                        idx++;
                    });
                });

                formBulkEdit.submit();
            });
        }

        // Tombol "Hapus Terpilih": hapus massal lewat endpoint destroy per ID
        if (btnBulkHapus) {
            btnBulkHapus.addEventListener('click', function () {
                const ids = getSelectedBulkIds();
                if (ids.length === 0) return;

                const pesan = 'Hapus ' + ids.length + ' slot jam pelajaran yang dipilih?\n\n'
                    + 'Tindakan ini tidak dapat dibatalkan.';
                if (!window.confirm(pesan)) return;

                const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                const token    = csrfMeta ? csrfMeta.getAttribute('content') : '';

                const tasks = ids.map(function (id) {
                    return fetch("{{ url('admin/jam-pelajaran') }}/" + id, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': token,
                            'Accept': 'application/json',
                        },
                    });
                });

                Promise.all(tasks)
                    .then(function () {
                        const url = new URL(window.location.href);
                        url.searchParams.set('bulk_deleted', String(ids.length));
                        window.location.href = url.toString();
                    })
                    .catch(function () {
                        window.location.reload();
                    });
            });
        }

        // Tombol "Batal": kosongkan semua checkbox
        if (btnBulkBatal) {
            btnBulkBatal.addEventListener('click', function () {
                getAllBulkBoxes().forEach(function (cb) { cb.checked = false; });
                if (selectAll) selectAll.checked = false;
                updateBulkUI();
            });
        }

        // Inisialisasi state awal (bar disembunyikan, select-all kosong)
        updateBulkUI();
    });

    function openEditModal(id, kategoriHari, jamMulai, jamSelesai, jenis, durasi, shiftId) {
        const routeBase = "{{ url('admin/jam-pelajaran') }}";
        document.getElementById('formEditJam').action = routeBase + '/' + id;

        document.getElementById('editKategoriHari').value = kategoriHari;
        document.getElementById('editJamMulai').value     = jamMulai;
        document.getElementById('editJamSelesai').value   = jamSelesai;
        document.getElementById('editJenis').value        = jenis;
        const editShift = document.getElementById('editShiftId');
        if (editShift) {
            editShift.value = (shiftId !== undefined && shiftId !== null && shiftId !== 0) ? String(shiftId) : '';
        }
        if (document.getElementById('editDurasi')) {
            document.getElementById('editDurasi').value = durasi !== undefined && durasi !== '' ? durasi : '40';
        }

        const modal = new bootstrap.Modal(document.getElementById('modalEditJam'));
        modal.show();
    }

</script>
@endpush
