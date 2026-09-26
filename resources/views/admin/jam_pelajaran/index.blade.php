@extends('layouts.app')

@section('title', 'Master Jam Pelajaran Sekolah - WebJournal Management System')

@section('content')
@php
    // Param 'mode' hanya disertakan pada link ketika Mode Shift aktif (URL bersih di Mode Global).
    $modeParam = $mode === 'shift' ? ['mode' => 'shift'] : [];
    // Param 'ta' (Tahun Ajaran & Semester) ikut dipertahankan pada tautan internal halaman.
    $taParam = $selectedTahunAjaran ? ['ta' => $selectedTahunAjaran->id] : [];
    // Param dasar untuk tautan internal (tab + shift + mode), tanpa TA.
    $baseLink = ['tab' => $tab] + ($selectedShiftId ? ['shift' => $selectedShiftId] : []) + $modeParam;
@endphp
<div class="container-fluid px-0">

    {{-- Page Header --}}
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="font-weight: 900; font-size: 1.75rem; letter-spacing: -0.02em;">
                Master Jam Pelajaran Sekolah
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                @if($mode === 'shift')
                    Mode Shift — kelola slot jam KBM &amp; istirahat (Senin – Jumat) per shift.
                    Slot pada tiap sub-tab shift <strong>terisolasi</strong> dari shift lain; penomoran Jam Ke- dihitung terpisah.
                @elseif($systemMode === 'shift')
                    <strong>Tipe Penjadwalan Sekolah: Multi-Shift</strong> — slot di bawah adalah jam Global (dasar) yang berlaku
                    untuk kelas tanpa shift. Kelola slot per shift melalui <strong>Mode Shift</strong>.
                @else
                    Kelola struktur jam pelajaran KBM &amp; istirahat (Senin – Jumat) untuk seluruh kelas
                    (sekolah tanpa sistem shift). Bila sekolah beroperasi multi-sesi, aktifkan
                    <strong>Multi-Shift</strong> pada Tipe Penjadwalan Sekolah.
                @endif
            </p>
        </div>
        </div>

    {{-- Baris Aksi Atas: kiri = filter Tahun Ajaran & Semester, kanan = tombol aksi utama --}}
    <div class="flex justify-between items-center gap-4 flex-wrap mb-6">
        {{-- Kiri: Filter 'Tahun Ajaran & Semester' saja --}}
        @if($tahunAjaranList->isNotEmpty())
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="text-muted d-none d-xl-inline text-nowrap" style="font-size: 0.78rem;">
                    <i class="bi bi-calendar-event me-1 text-primary"></i>Tahun Ajaran &amp; Semester
                </span>
                <select id="selectTahunAjaran"
                        class="form-select rounded-3 fw-semibold px-3 py-2 text-nowrap"
                        style="font-size: 0.875rem; width: auto; min-width: 210px; cursor: pointer; border-color: #cbd5e1;"
                        onchange="window.location.href = this.value;">
                    @foreach($tahunAjaranList as $ta)
                        <option value="{{ route('admin.jam-pelajaran.index', $baseLink + ['ta' => $ta->id]) }}"
                                @if($selectedTahunAjaran && $selectedTahunAjaran->id === $ta->id) selected @endif>
                            {{ $ta->tahun_ajaran }} – {{ $ta->semester }}@if($ta->is_active) (Aktif) @endif
                        </option>
                    @endforeach
                </select>
            </div>
        @else
            <span class="text-muted d-inline-flex align-items-center gap-1 fw-semibold" style="font-size: 0.8rem;">
                <i class="bi bi-calendar-event"></i> Tahun Ajaran belum tersedia
            </span>
        @endif

        {{-- Kanan: tombol aksi utama (+ Tambah Jam = Primary paling mencolok) --}}
        <div class="d-flex align-items-center gap-2 flex-wrap justify-content-sm-end">
            {{-- Toggle Tipe Penjadwalan Sekolah (sistem global): Global [ ] Multi-Shift --}}
            <form method="POST"
                  action="{{ route('admin.jam-pelajaran.schedule-mode', $taParam) }}"
                  class="d-inline-flex align-items-center gap-2 me-1">
                @csrf
                <span class="text-muted d-none d-xxl-inline text-nowrap" style="font-size: 0.78rem;">
                    <i class="bi bi-diagram-3 me-1 text-primary"></i>Tipe Penjadwalan
                </span>
                <div class="btn-group btn-group-sm" role="group" aria-label="Tipe Penjadwalan Sekolah">
                    <button type="submit" name="schedule_mode" value="{{ \App\Models\AppSetting::SCHEDULE_GLOBAL }}"
                            class="btn rounded-start-3 fw-semibold px-3 {{ $systemMode === 'global' ? 'btn-primary text-white shadow-sm' : 'btn-outline-primary' }}"
                            style="font-size: 0.8rem;"
                            title="Sekolah tanpa sistem shift — semua kelas memakai slot jam Global/regular"
                            aria-label="Tipe Penjadwalan Sekolah: Global">
                        <i class="bi bi-globe2 me-1"></i>Global
                    </button>
                    <button type="submit" name="schedule_mode" value="{{ \App\Models\AppSetting::SCHEDULE_SHIFT }}"
                            class="btn rounded-end-3 fw-semibold px-3 {{ $systemMode === 'shift' ? 'btn-primary text-white shadow-sm' : 'btn-outline-primary' }}"
                            style="font-size: 0.8rem;"
                            title="Sekolah multi-sesi — setiap kelas terikat shift & slot jam diisolasi per shift"
                            aria-label="Tipe Penjadwalan Sekolah: Multi-Shift">
                        <i class="bi bi-arrow-left-right me-1"></i>Multi-Shift
                    </button>
                </div>
            </form>

            @if(!$shiftModeEmpty)
                {{-- Generate Preset: aksi ringkas (ikon + label pendek) --}}
                <button type="button"
                        class="btn btn-outline-warning rounded-3 px-3 py-2 d-inline-flex align-items-center justify-content-center gap-1 text-nowrap"
                        style="font-size: 0.875rem;" data-bs-toggle="modal" data-bs-target="#modalGeneratePreset"
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
                            style="font-size: 0.875rem;"
                            title="Salin struktur slot jam dari {{ $previousTahunAjaran->tahun_ajaran }} – {{ $previousTahunAjaran->semester }}">
                        <i class="bi bi-copy"></i>
                        <span class="d-none d-md-inline">Salin dari Semester Lalu</span>
                        <span class="d-md-none">Salin</span>
                    </button>
                </form>
            @endif

            {{-- Mode Shift: toggle Global ⇄ Shift — hanya untuk sekolah ber-tipe Multi-Shift --}}
            @if($systemMode === 'shift')
                @if($mode === 'shift')
                    <a href="{{ route('admin.jam-pelajaran.index', ['tab' => $tab] + $taParam) }}"
                       class="btn btn-outline-secondary rounded-3 fw-semibold px-3 py-2 d-inline-flex align-items-center justify-content-center gap-2 text-nowrap"
                       style="font-size: 0.875rem;">
                        <i class="bi bi-globe2"></i>
                        Kembali ke Mode <span class="d-none d-sm-inline">Global</span>
                    </a>
                @else
                    <a href="{{ route('admin.jam-pelajaran.index', ['tab' => $tab, 'mode' => 'shift'] + $taParam) }}"
                       class="btn btn-outline-primary rounded-3 fw-semibold px-3 py-2 d-inline-flex align-items-center justify-content-center gap-2 text-nowrap"
                       style="font-size: 0.875rem;">
                        <i class="bi bi-arrow-left-right"></i>
                        Mode Shift
                    </a>
                @endif
            @endif

            @if(!$shiftModeEmpty)
                {{-- Tambah Jam Pelajaran: Primary (paling mencolok di sebelah kanan) --}}
                <button type="button" id="btnTambahJam"
                        class="btn btn-primary rounded-3 fw-semibold px-3 py-2 d-inline-flex align-items-center justify-content-center gap-2 text-nowrap shadow-sm"
                        style="font-size: 0.875rem;" data-bs-toggle="modal" data-bs-target="#modalTambahJam"
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

    @if($mode === 'shift')
        {{-- ====== TAMPILAN KHUSUS MODE SHIFT ====== --}}
        <div class="mb-3">
            {{-- Baris Pilihan Shift: kiri = pills shift + ikon (+) tambah, kanan = kelola daftar shift --}}
            <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mb-2">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="text-muted fw-semibold d-inline-flex align-items-center gap-1 me-1" style="font-size: 0.82rem;">
                        <i class="bi bi-layers"></i> Pilih Shift:
                    </span>
                    @forelse($shifts as $shift)
                        <a href="{{ route('admin.jam-pelajaran.index', ['tab' => $tab, 'shift' => $shift->id] + $modeParam + $taParam) }}"
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
                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-3 py-2" style="font-size: 0.78rem;">
                            Belum ada shift — klik ikon (+) di samping untuk membuat Shift 1, Shift 2, dst.
                        </span>
                    @endforelse

                    {{-- Tombol kecil ikon (+) Tambah Shift --}}
                    <button type="button"
                            class="btn btn-sm btn-warning rounded-3 d-inline-flex align-items-center justify-content-center px-2 py-1 fw-semibold"
                            style="font-size: 0.8rem;"
                            data-bs-toggle="modal" data-bs-target="#modalShift"
                            title="+ Tambah Shift" aria-label="+ Tambah Shift">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-3 fw-semibold px-3"
                            style="font-size: 0.8rem;" data-bs-toggle="modal" data-bs-target="#modalShift">
                        <i class="bi bi-gear me-1"></i>Kelola Daftar Shift
                    </button>
                </div>
            </div>

            {{-- Callout ringkas shift aktif (1 baris tipis) --}}
            @if($selectedShift)
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-3 border border-warning-subtle"
                     style="background-color: #fff8e1; font-size: 0.75rem; max-width: 880px;">
                    <i class="bi bi-clock-history text-warning-emphasis"></i>
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
        <div class="d-inline-flex align-items-start gap-2 mb-3 px-3 py-2 rounded-3 border border-info-subtle"
             style="background-color: #eff6ff; font-size: 0.78rem; max-width: 840px;">
            <i class="bi bi-globe2 text-primary mt-1"></i>
            <span class="text-dark">
                <strong>Mode Global</strong> — Slot jam di bawah berlaku untuk semua kelas (sekolah tanpa sistem shift).
                @if($systemMode === 'shift')
                    Sekolah multi-sesi dapat beralih ke
                    <a href="{{ route('admin.jam-pelajaran.index', ['tab' => $tab, 'mode' => 'shift'] + $taParam) }}" class="fw-semibold text-primary">Mode Shift</a>.
                @endif
            </span>
        </div>
    @endif

    {{-- Tab Kelompok Hari (Senin–Kamis vs Jumat) --}}
    <div class="mb-4">
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.jam-pelajaran.index', ['tab' => 'Senin-Kamis', 'shift' => $selectedShiftId] + $modeParam + $taParam) }}"
               class="btn rounded-3 fw-semibold px-4 py-2 {{ $tab === 'Senin-Kamis' ? 'btn-primary shadow-sm text-white' : 'btn-light border text-dark' }}"
               style="font-size: 0.875rem;">
                <i class="bi bi-calendar-week me-1"></i>
                Senin – Kamis
            </a>
            <a href="{{ route('admin.jam-pelajaran.index', ['tab' => 'Jumat', 'shift' => $selectedShiftId] + $modeParam + $taParam) }}"
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
                    <button type="button" class="btn btn-warning rounded-3 px-3 py-2 fw-semibold text-dark"
                            style="font-size: 0.85rem;" data-bs-toggle="modal" data-bs-target="#modalShift">
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
                <span class="badge bg-orange-subtle text-warning border border-warning-subtle rounded-pill px-3 py-1"
                      style="font-size: 0.72rem; background-color: #fff7ed; color: #c05500 !important; border-color: #fed7aa !important;">
                    Berlaku per Shift Tingkat
                </span>
            </div>
        </div>

        <div class="card-body px-4 pb-4 pt-2">
            <form method="POST" action="{{ route('admin.jam-pulang.upsert') }}" id="formJamPulang">
                @csrf
                <input type="hidden" name="redirect_tab" value="{{ $tab }}">
                <input type="hidden" name="redirect_shift" value="{{ $selectedShiftId ?? '' }}">
                <input type="hidden" name="redirect_mode" value="{{ $mode }}">
                <input type="hidden" name="redirect_ta" value="{{ $selectedTahunAjaran?->id ?? '' }}">

                @php
                    $tingkatList = ['X', 'XI', 'XII'];
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

                {{-- Indikator: pengaturan jam pulang mengikuti tab shift aktif --}}
                <div class="d-inline-flex align-items-center gap-2 flex-wrap px-3 py-2 mb-3 rounded-3 border {{ $jpCtxIsShift ? 'bg-warning-subtle border-warning-subtle' : 'bg-info-subtle border-info-subtle' }}"
                     style="font-size: 0.8rem; max-width: 100%;">
                    <i class="bi {{ $jpCtxIsShift ? 'bi-clock text-warning-emphasis' : 'bi-globe2 text-primary' }}"></i>
                    <span class="fw-semibold text-dark">
                        Berlaku untuk: <strong>{{ $jpCtxNama }}</strong>
                        <span class="text-muted fw-normal">({{ $jpCtxDesc }})</span>
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
                    </div>
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <div id="warningJamPulang" class="small p-2 rounded-3 text-warning-emphasis d-none"
                             style="font-size: 0.78rem; background-color: #fff8e1; border: 1px solid #ffe082;">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i> ⚠️ Ada Perubahan Belum Disimpan
                        </div>
                        <button type="submit" id="btnSimpanJamPulang" class="btn fw-bold px-4 rounded-3 d-flex align-items-center gap-2"
                                style="font-size: 0.875rem; background: #f97316; border-color: #f97316; color: white;">
                            <i class="bi bi-floppy-fill"></i> Simpan Pengaturan Jam Pulang
                        </button>
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
                <input type="hidden" name="redirect_mode" value="{{ $mode }}">
                <input type="hidden" name="redirect_ta" value="{{ $selectedTahunAjaran?->id ?? '' }}">

                {{-- Grid Layout: 2 Kolom Berdampingan --}}
                <div class="row g-4">
                    {{-- Kolom Kiri: Pengaturan Upacara Bendera (Hari Senin) --}}
                    <div class="col-12 col-lg-6">
                        {{-- Fieldset Guard Untuk Setiap Kolom --}}
                        <fieldset class="border-0 p-0 m-0">
                            <form method="POST" action="{{ route('admin.agenda-rutin.upsert') }}" id="formAgendaSenin">
                                @csrf
                                <input type="hidden" name="hari" value="Senin">
                                <input type="hidden" name="redirect_tab" value="Senin-Kamis">
                                <input type="hidden" name="redirect_shift" value="{{ $selectedShiftId ?? '' }}">
                                <input type="hidden" name="redirect_mode" value="{{ $mode }}">
                                <input type="hidden" name="redirect_ta" value="{{ $selectedTahunAjaran?->id ?? '' }}">

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
                                        <select name="jam_ke" id="jamKeSenin" class="form-select rounded-3" required style="font-size: 0.875rem;" {{ $agendaSeninLocked ? 'disabled' : '' }}>
                                            @forelse($jamOptionsSenin as $jam)
                                                <option value="{{ $jam->jam_ke }}" {{ old('jam_ke', $agendaSenin->jam_ke ?? $jamOptionsSenin->first()->jam_ke) == $jam->jam_ke ? 'selected' : '' }}>
                                                    Jam Ke-{{ $jam->jam_ke }} ({{ substr($jam->jam_mulai, 0, 5) }} - {{ substr($jam->jam_selesai, 0, 5) }})
                                                </option>
                                            @empty
                                                <option value="">— Belum ada slot jam KBM —</option>
                                            @endforelse
                                        </select>
                                    </div>

                                    {{-- Toggle Switch --}}
                                    <div class="col-12 col-sm-7 pt-sm-4">
                                        <div class="form-check form-switch mb-0">
                                            <input class="form-check-input" type="checkbox" role="switch" id="switchAgendaSenin" name="is_active" value="1"
                                                   {{ old('is_active', $agendaSenin->is_active ?? true) ? 'checked' : '' }} style="cursor: pointer; width: 2.5em; height: 1.25em;"
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

                                <div class="d-flex justify-content-end pt-2 border-top">
                                    <button type="submit" id="btnSimpanSenin" class="btn btn-primary fw-bold px-4 py-2 rounded-3 d-flex align-items-center justify-content-center gap-2 w-full sm:w-auto mt-3 {{ $agendaSeninLocked ? 'opacity-50' : '' }}" style="font-size: 0.85rem;"
                                            title="{{ $agendaSeninLocked ? 'Data ini adalah data pengujian IT dan tidak dapat diubah.' : 'Simpan pengaturan upacara bendera' }}"
                                            {{ $agendaSeninLocked ? 'disabled' : '' }}>
                                        <i class="bi bi-floppy-fill"></i> Simpan Upacara Senin
                                    </button>
                                </div>
                            </form>
                        </fieldset>
                    </div>

                    {{-- Kolom Kanan: Pengaturan Pembiasaan (Hari Jumat) --}}
                    <div class="col-12 col-lg-6">
                        {{-- Fieldset Guard Untuk Setiap Kolom --}}
                        <fieldset class="border-0 p-0 m-0">
                            <form method="POST" action="{{ route('admin.agenda-rutin.upsert') }}" id="formAgendaJumat">
                                @csrf
                                <input type="hidden" name="hari" value="Jumat">
                                <input type="hidden" name="redirect_tab" value="Jumat">
                                <input type="hidden" name="redirect_shift" value="{{ $selectedShiftId ?? '' }}">
                                <input type="hidden" name="redirect_mode" value="{{ $mode }}">
                                <input type="hidden" name="redirect_ta" value="{{ $selectedTahunAjaran?->id ?? '' }}">

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
                                        <select name="jam_ke" id="jamKeJumat" class="form-select rounded-3" required style="font-size: 0.875rem;" {{ $agendaJumatLocked ? 'disabled' : '' }}>
                                            @forelse($jamOptionsJumat as $jam)
                                                <option value="{{ $jam->jam_ke }}" {{ old('jam_ke', $agendaJumat->jam_ke ?? $jamOptionsJumat->first()->jam_ke) == $jam->jam_ke ? 'selected' : '' }}>
                                                    Jam Ke-{{ $jam->jam_ke }} ({{ substr($jam->jam_mulai, 0, 5) }} - {{ substr($jam->jam_selesai, 0, 5) }})
                                                </option>
                                            @empty
                                                <option value="">— Belum ada slot jam KBM —</option>
                                            @endforelse
                                        </select>
                                    </div>

                                    {{-- Toggle Switch --}}
                                    <div class="col-12 col-sm-7 pt-sm-4">
                                        <div class="form-check form-switch mb-0">
                                            <input class="form-check-input" type="checkbox" role="switch" id="switchAgendaJumat" name="is_active" value="1"
                                                   {{ old('is_active', $agendaJumat->is_active ?? true) ? 'checked' : '' }} style="cursor: pointer; width: 2.5em; height: 1.25em;"
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

                                <div class="d-flex justify-content-end pt-2 border-top">
                                    <button type="submit" id="btnSimpanJumat" class="btn btn-info text-white fw-bold px-4 py-2 rounded-3 d-flex align-items-center justify-content-center gap-2 w-full sm:w-auto mt-3 {{ $agendaJumatLocked ? 'opacity-50' : '' }}" style="font-size: 0.85rem;"
                                            title="{{ $agendaJumatLocked ? 'Data ini adalah data pengujian IT dan tidak dapat diubah.' : 'Simpan pengaturan pembiasaan' }}"
                                            {{ $agendaJumatLocked ? 'disabled' : '' }}>
                                        <i class="bi bi-floppy-fill"></i> Simpan Pembiasaan Jumat
                                    </button>
                                </div>
                            </form>
                        </fieldset>
                    </div>
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
                    @if($mode === 'shift' && !$shiftModeEmpty)
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
                            <input type="time" name="jam_mulai" class="form-control rounded-3" step="60" value="07:00" autocomplete="off">
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
                    @if($mode === 'shift' && !$shiftModeEmpty)
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
{{-- ===================== MODAL PENGATURAN SHIFT ===================== --}}
<div class="modal fade" id="modalShift" tabindex="-1" aria-labelledby="modalShiftTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow rounded-4" style="max-height: 85vh; overflow: hidden;">
            <div class="modal-header border-0 pb-0" style="flex-shrink: 0;">
                <h5 class="modal-title fw-bold" id="modalShiftTitle">
                    <i class="bi bi-arrow-left-right text-primary me-2"></i>Pengaturan Shift Pelajaran
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-3" style="overflow-y: auto;">
                {{-- Form Tambah/Edit Shift --}}
                <form method="POST" action="{{ route('admin.shift-pelajaran.store') }}" id="formShift">
                    @csrf
                    <div id="shiftMethodPlaceholder"></div>
                    <div class="card border-0 shadow-none bg-light-subtle rounded-3">
                        <div class="card-body p-3">
                            <h6 class="fw-bold text-dark mb-1" style="font-size: 0.9rem;">
                                <i class="bi bi-plus-lg me-1"></i><span id="shiftFormTitle">Tambah Shift Baru</span>
                            </h6>
                            <p class="text-muted mb-3" style="font-size: 0.76rem;">
                                Rentang Jam Utama menunjukkan rentang waktu KBM utama shift (informasi/estimasi,
                                bukan pengunci jam). Status Aktif menandakan shift sedang digunakan.
                            </p>
                            <div class="row g-3 align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold text-dark mb-1" style="font-size: 0.85rem;">Nama Shift *</label>
                                    <input type="text" name="nama_shift" id="shiftNama" class="form-control rounded-3"
                                           maxlength="120" placeholder="cth: Shift 1 (Pagi)" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold text-dark mb-1" style="font-size: 0.85rem;">Jam Mulai Utama</label>
                                    <input type="time" name="jam_mulai" id="shiftMulai" class="form-control rounded-3" step="60" value="07:00">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold text-dark mb-1" style="font-size: 0.85rem;">Jam Selesai Utama</label>
                                    <input type="time" name="jam_selesai" id="shiftSelesai" class="form-control rounded-3" step="60" value="15:00">
                                </div>
                                <div class="col-md-2">
                                    <div class="form-check form-switch ps-5">
                                        <input class="form-check-input" type="checkbox" name="is_active" id="shiftAktif" value="1" checked>
                                        <label class="form-check-label fw-semibold text-dark" for="shiftAktif" style="font-size: 0.82rem;">Aktif</label>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex gap-2 mt-3">
                                <button type="submit" class="btn btn-primary rounded-3 px-4 fw-semibold">
                                    <i class="bi bi-check-lg me-1"></i> <span id="shiftSubmitText">Simpan Shift</span>
                                </button>
                                <button type="button" id="btnResetShiftForm" class="btn btn-light border rounded-3 px-3 fw-semibold d-none">
                                    Batal Edit
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                {{-- Daftar Shift --}}
                <div class="mt-4">
                    <h6 class="fw-bold text-dark mb-3" style="font-size: 0.9rem;">Daftar Shift</h6>
                    @forelse($shifts as $shift)
                        <div class="d-flex align-items-center gap-2 p-2 rounded-3 bg-white border mb-2 flex-wrap">
                            <div class="flex-grow-1" style="min-width: 220px;">
                                <div class="fw-semibold text-dark d-flex align-items-center gap-2 flex-wrap" style="font-size: 0.85rem;">
                                    <i class="bi bi-clock text-primary"></i>
                                    {{ $shift->nama_shift }}
                                    @if($shift->is_active)
                                        <span class="badge bg-success-subtle text-success rounded-pill" style="font-size: 0.65rem;">Aktif</span>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning rounded-pill" style="font-size: 0.65rem;">Non-Aktif</span>
                                    @endif
                                </div>
                                <div class="text-muted" style="font-size: 0.75rem;">
                                    🕐 {{ substr($shift->jam_mulai ?? '00:00:00', 0, 5) }} – {{ substr($shift->jam_selesai ?? '00:00:00', 0, 5) }}
                                    @if(!empty($shift->keterangan)) · {{ $shift->keterangan }} @endif
                                    @if($shift->jam_pelajaran_count > 0) · {{ $shift->jam_pelajaran_count }} slot jam @endif
                                    @if($shift->kelas_count > 0) · {{ $shift->kelas_count }} kelas @endif
                                </div>
                            </div>
                            <button type="button"
                                    class="btn btn-sm btn-outline-primary rounded-3 fw-semibold btn-edit-shift flex-shrink-0"
                                    data-shift-id="{{ $shift->id }}"
                                    data-nama="{{ $shift->nama_shift }}"
                                    data-mulai="{{ substr($shift->jam_mulai ?? '00:00:00', 0, 5) }}"
                                    data-selesai="{{ substr($shift->jam_selesai ?? '00:00:00', 0, 5) }}"
                                    data-aktif="{{ $shift->is_active ? 1 : 0 }}">
                                <i class="bi bi-pencil-square me-1"></i>Edit
                            </button>
                            <button type="button"
                                    class="btn btn-sm btn-outline-danger rounded-3 fw-semibold btn-hapus-shift flex-shrink-0"
                                    data-shift-id="{{ $shift->id }}"
                                    data-nama="{{ $shift->nama_shift }}"
                                    data-slots="{{ $shift->jam_pelajaran_count }}"
                                    data-kelas="{{ $shift->kelas_count }}"
                                    data-pulang="{{ $jamPulangCountByShift[$shift->id] ?? 0 }}">
                                <i class="bi bi-trash3 me-1"></i>Hapus
                            </button>
                        </div>
                    @empty
                        <div class="alert alert-light border text-muted rounded-3 mb-0" style="font-size: 0.82rem;">
                            Belum ada shift. Tambahkan shift pertama (mis. <em>"Shift 1 (Pagi)"</em>) untuk sekolah
                            dengan multi-sesi pembelajaran.
                        </div>
                    @endforelse
                </div>
            </div>
            <div class="modal-footer border-0 pt-0" style="flex-shrink: 0;">
                <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

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
                    <div class="rounded-3 border p-3 mb-3" style="background-color: #fff8e1; border-color: #fed7aa !important;">
                        <div class="fw-semibold text-dark mb-1" style="font-size: 0.85rem;">
                            <i class="bi bi-info-circle me-1"></i>Dampak Penghapusan:
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
                if (durasiInput) durasiInput.dataset.touched = '';
                if (jumlahInput) jumlahInput.dataset.touched = '';
                applyPresetDefaults();
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
        // Config per kartu: badge, toggle, select jam_ke, tombol simpan, elemen peringatan
        const agendaConfigs = {
            Senin: {
                badge:   document.getElementById('badgeStatusSenin'),
                toggle:  document.getElementById('switchAgendaSenin'),
                select:  document.getElementById('jamKeSenin'),
                button:  document.getElementById('btnSimpanSenin'),
                warning: document.getElementById('warningSenin'),
                form:    document.getElementById('formAgendaSenin'),
            },
            Jumat: {
                badge:   document.getElementById('badgeStatusJumat'),
                toggle:  document.getElementById('switchAgendaJumat'),
                select:  document.getElementById('jamKeJumat'),
                button:  document.getElementById('btnSimpanJumat'),
                warning: document.getElementById('warningJumat'),
                form:    document.getElementById('formAgendaJumat'),
            }
        };

        const BADGE_ACTIVE  = 'badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1';
        const BADGE_INACTIVE= 'badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-3 py-1';
        const BADGE_DIRTY   = 'badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-3 py-1';

        function badgeText(cfg) {
            return cfg.toggle.checked
                ? '● Aktif (Terkunci Jam Ke-' + cfg.select.value + ')'
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

            // Peringatan + highlight tombol
            if (cfg.warning) cfg.warning.classList.toggle('d-none', !cfg.dirty);
            if (cfg.button) {
                // Simpan class warna asli tombol sekali saja
                if (!cfg.originalButtonClass) {
                    cfg.originalButtonClass = Array.from(cfg.button.classList)
                        .find(function (c) { return c.indexOf('btn-') === 0 && c !== 'btn-warning'; });
                }
                if (cfg.dirty) {
                    cfg.button.classList.add('btn-warning');
                    if (cfg.originalButtonClass) cfg.button.classList.remove(cfg.originalButtonClass);
                    cfg.button.style.animation = 'btnPulse 1.2s ease-in-out infinite';
                } else {
                    cfg.button.classList.remove('btn-warning');
                    if (cfg.originalButtonClass) cfg.button.classList.add(cfg.originalButtonClass);
                    cfg.button.style.animation = '';
                }
            }
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

            // Reset state saat submit form (normal POST/reload) sebelum dikirim
            if (cfg.form) {
                cfg.form.addEventListener('submit', function () {
                    cfg.initialToggle = cfg.toggle.checked;
                    cfg.initialSelect = cfg.select.value;
                    cfg.dirty = false;
                    renderBadge(key);
                });
            }
        }

        // Inisialisasi: simpan nilai awal untuk kedua kartu
        Object.keys(agendaConfigs).forEach(function (key) {
            captureInitial(key);
            bindAgendaCard(key);
        });

        // ===== Pengaturan Jam Pulang: Badge Sync + Dirty State =====
        const jpForm    = document.getElementById('formJamPulang');
        const jpButton  = document.getElementById('btnSimpanJamPulang');
        const jpWarning = document.getElementById('warningJamPulang');
        const jpOriginalButtonStyle = {};

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

        function updateJamPulangDirty() {
            let dirty = false;
            document.querySelectorAll('.jam-pulang-select').forEach(function (select) {
                if (select.value !== select.dataset.initial) dirty = true;
            });

            if (jpWarning) jpWarning.classList.toggle('d-none', !dirty);
            if (jpButton) {
                if (dirty) {
                    if (jpOriginalButtonStyle.bg === undefined) {
                        jpOriginalButtonStyle.bg = jpButton.style.background;
                        jpOriginalButtonStyle.border = jpButton.style.borderColor;
                    }
                    jpButton.style.background = '#f59e0b';
                    jpButton.style.borderColor = '#d97706';
                    jpButton.style.animation = 'btnPulse 1.2s ease-in-out infinite';
                } else {
                    jpButton.style.background = jpOriginalButtonStyle.bg === undefined ? '#f97316' : jpOriginalButtonStyle.bg;
                    jpButton.style.borderColor = jpOriginalButtonStyle.border === undefined ? '#f97316' : jpOriginalButtonStyle.border;
                    jpButton.style.animation = '';
                }
            }
        }

        function resetJamPulangDirty() {
            document.querySelectorAll('.jam-pulang-select').forEach(function (select) {
                select.dataset.initial = select.value;
            });
            updateJamPulangDirty();
        }

        if (jpForm) {
            document.querySelectorAll('.jam-pulang-select').forEach(function (select) {
                select.addEventListener('change', function () {
                    renderJamPulangBadges();
                    updateJamPulangDirty();
                });
                // Simpan nilai awal (initial state) dari DB
                select.dataset.initial = select.value;
            });

            // Reset indikator saat submit (form reload/POST)
            jpForm.addEventListener('submit', function () {
                resetJamPulangDirty();
            });

            renderJamPulangBadges();
        }

        // ===== Modal Pengaturan Shift: isi form & switch store/update =====
        const shiftForm      = document.getElementById('formShift');
        const shiftNama      = document.getElementById('shiftNama');
        const shiftMulai     = document.getElementById('shiftMulai');
        const shiftSelesai   = document.getElementById('shiftSelesai');
        const shiftAktif     = document.getElementById('shiftAktif');
        const shiftFormTitle = document.getElementById('shiftFormTitle');
        const shiftSubmitText= document.getElementById('shiftSubmitText');
        const btnResetShift  = document.getElementById('btnResetShiftForm');
        const shiftMethodPh  = document.getElementById('shiftMethodPlaceholder');

        const isGlobalShift = {{ $selectedShiftId === null ? 'true' : 'false' }};

        function resetShiftFormToStore() {
            if (shiftForm) shiftForm.action = "{{ route('admin.shift-pelajaran.store') }}";
            if (shiftMethodPh) shiftMethodPh.innerHTML = '';
            if (shiftNama) shiftNama.value = '';
            if (shiftMulai) shiftMulai.value = '07:00';
            if (shiftSelesai) shiftSelesai.value = '15:00';
            if (shiftAktif) shiftAktif.checked = true;
            if (shiftFormTitle) shiftFormTitle.textContent = 'Tambah Shift Baru';
            if (shiftSubmitText) shiftSubmitText.textContent = 'Simpan Shift';
            if (btnResetShift) btnResetShift.classList.add('d-none');
        }

        if (shiftForm) {
            document.querySelectorAll('.btn-edit-shift').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const id      = btn.dataset.shiftId;
                    const nama    = btn.dataset.nama;
                    const mulai   = btn.dataset.mulai;
                    const selesai = btn.dataset.selesai;
                    const aktif   = btn.dataset.aktif === '1';

                    shiftForm.action = "{{ url('admin/shift-pelajaran') }}/" + id;
                    if (shiftMethodPh) {
                        shiftMethodPh.innerHTML = '<input type="hidden" name="_method" value="PUT">';
                    }
                    if (shiftNama) shiftNama.value = nama;
                    if (shiftMulai) shiftMulai.value = mulai;
                    if (shiftSelesai) shiftSelesai.value = selesai;
                    if (shiftAktif) shiftAktif.checked = aktif;
                    if (shiftFormTitle) shiftFormTitle.textContent = 'Edit Shift';
                    if (shiftSubmitText) shiftSubmitText.textContent = 'Perbarui Shift';
                    if (btnResetShift) btnResetShift.classList.remove('d-none');
                });
            });

            if (btnResetShift) {
                btnResetShift.addEventListener('click', resetShiftFormToStore);
            }

            document.getElementById('modalShift').addEventListener('hidden.bs.modal', resetShiftFormToStore);
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
                const modalEl = document.getElementById('modalShift');
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
