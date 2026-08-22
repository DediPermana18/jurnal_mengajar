@extends('layouts.app')

@section('title', 'Plotting Jadwal Kelas - Kurikulum')

@push('styles')
<!-- Choices.js CSS for Searchable Select -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
<style>
    /* Styling agar Choices.js menyatu mulus dengan Bootstrap 5 & Design System */
    .choices {
        margin-bottom: 0;
    }
    .choices__inner {
        min-height: 42px;
        background-color: #ffffff;
        border: 1px solid #dee2e6;
        border-radius: 0.5rem;
        padding: 5px 12px;
        font-size: 0.9rem;
        box-shadow: none;
    }
    .is-focused .choices__inner {
        border-color: #86b7fe;
        box-shadow: 0 0 0 0.25rem rgba(22, 119, 255, 0.15);
    }
    .choices__list--dropdown {
        border-radius: 0.5rem;
        border: 1px solid #dee2e6;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
        z-index: 1050;
    }
    .choices__list--dropdown .choices__item--selectable {
        padding: 8px 14px;
        font-size: 0.88rem;
    }
    .choices__list--dropdown .choices__item--selectable.is-highlighted {
        background-color: #f0f7ff;
        color: #1677ff;
    }
    .choices[data-type*="select-one"] .choices__input {
        background-color: #f8fafc;
        border-radius: 0.375rem;
        border: 1px solid #e2e8f0;
        padding: 6px 10px;
        margin-bottom: 6px;
        font-size: 0.85rem;
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">

    {{-- Page Header --}}
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="font-weight: 900; font-size: 1.75rem; letter-spacing: -0.02em;">
                Plotting Jadwal Kelas
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                Kelola pemetaan Mata Pelajaran dan Guru Pengajar per kelas berdasarkan slot Master Jam Pelajaran.
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap align-items-center">
            @if($tahunAktif)
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-2 fw-semibold" style="font-size: 0.82rem;">
                    <i class="bi bi-calendar-check me-1"></i> T.A. {{ $tahunAktif->tahun_ajaran }} (Semester {{ $tahunAktif->semester }})
                </span>
            @endif
            <a href="{{ route('kurikulum.jam-pelajaran.index') }}" class="btn btn-outline-secondary rounded-3 fw-semibold px-3 d-flex align-items-center gap-2" style="font-size: 0.875rem;">
                <i class="bi bi-clock-history"></i> Master Jam
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

    {{-- Filter Card (Pilih Kelas & Pilih Hari) --}}
    <div class="card border-0 rounded-4 shadow-sm mb-4 bg-white">
        <div class="card-body p-4">
            <form method="GET" action="{{ route('kurikulum.jadwal.index') }}" id="filterForm">
                <div class="row g-4">
                    {{-- Baris Atas: Pilih Kelas (Full Width) --}}
                    <div class="col-12">
                        <label class="form-label fw-bold text-dark mb-2" style="font-size: 0.9rem;">
                            <i class="bi bi-door-open-fill text-primary me-1"></i> Pilih Kelas
                        </label>
                        <select name="id_kelas" id="selectKelas" class="form-select rounded-3">
                            <option value="">-- Pilih Kelas --</option>
                            @foreach($kelasList as $kelas)
                                <option value="{{ $kelas->id }}" {{ $selectedKelas && $selectedKelas->id == $kelas->id ? 'selected' : '' }}>
                                    {{ $kelas->nama_kelas }} ({{ $kelas->jurusan->nama_jurusan ?? 'Umum' }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Baris Bawah: Pilih Hari (Horizontal Row) --}}
                    <div class="col-12">
                        <label class="form-label fw-bold text-dark mb-2" style="font-size: 0.9rem;">
                            <i class="bi bi-calendar-week-fill text-primary me-1"></i> Pilih Hari
                        </label>
                        <div class="d-flex gap-2 flex-nowrap overflow-x-auto pb-2 pb-md-0" style="scrollbar-width: thin;">
                            @foreach($hariList as $hari)
                                @php
                                    $isActive = ($selectedHari === $hari);
                                @endphp
                                <button type="submit" name="hari" value="{{ $hari }}"
                                        class="btn flex-fill rounded-3 fw-semibold px-3 py-2 d-flex align-items-center justify-content-center gap-2 {{ $isActive ? 'btn-primary shadow-sm text-white' : 'btn-light border text-dark' }}"
                                        style="font-size: 0.875rem; min-width: fit-content; white-space: nowrap;">
                                    <i class="bi {{ $hari === 'Jumat' ? 'bi-calendar2-day' : 'bi-calendar-day' }}"></i>
                                    <span>{{ $hari }}</span>
                                    @if($hari === 'Jumat')
                                        <span class="badge {{ $isActive ? 'bg-white text-primary' : 'bg-warning-subtle text-warning-emphasis' }} rounded-pill ms-1" style="font-size: 0.68rem;">30m</span>
                                    @else
                                        <span class="badge {{ $isActive ? 'bg-white text-primary' : 'bg-secondary-subtle text-secondary' }} rounded-pill ms-1" style="font-size: 0.68rem;">40m</span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if(!$selectedKelas)
        {{-- ===================== EMPTY STATE (BELUM PILIH KELAS) ===================== --}}
        <div class="card border-0 rounded-4 shadow-sm bg-white text-center p-5 my-3">
            <div class="py-4">
                <div class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle shadow-sm mb-4" style="width: 90px; height: 90px;">
                    <i class="bi bi-calendar2-range-fill" style="font-size: 2.75rem; color: #1677ff;"></i>
                </div>
                <h4 class="fw-bold text-dark mb-2">Pilih Kelas untuk Menampilkan Jadwal</h4>
                <p class="text-muted mx-auto mb-4" style="max-width: 480px; font-size: 0.95rem;">
                    Silakan pilih salah satu kelas melalui dropdown pencarian di atas untuk melihat, menambah, atau mengelola matriks plotting jadwal pelajaran.
                </p>
                <div class="d-inline-flex align-items-center gap-2 px-3 py-2 rounded-pill bg-light border text-secondary" style="font-size: 0.82rem;">
                    <i class="bi bi-search text-primary"></i> Gunakan kotak pencarian dropdown di atas untuk memilih kelas
                </div>
            </div>
        </div>
    @else
        {{-- ===================== MATRIKS JADWAL (KELAS TERPILIH) ===================== --}}
        {{-- Status / Overview Card --}}
        <div class="card border-0 rounded-4 shadow-sm mb-4 bg-white">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-3 d-flex align-items-center justify-content-center text-white"
                             style="width: 48px; height: 48px; background: linear-gradient(135deg, #1677ff, #0958d9);">
                            <i class="bi bi-mortarboard-fill" style="font-size: 1.5rem;"></i>
                        </div>
                        <div>
                            <h5 class="fw-black mb-0 text-dark" style="font-weight: 800;">
                                {{ $selectedKelas->nama_kelas }} &mdash; Jadwal Hari {{ $selectedHari }}
                            </h5>
                            <span class="text-muted" style="font-size: 0.85rem;">
                                Wali Kelas: <strong>{{ $selectedKelas->waliKelas->nama ?? '-' }}</strong> &bull; Jurusan: <strong>{{ $selectedKelas->jurusan->nama_jurusan ?? '-' }}</strong>
                            </span>
                        </div>
                    </div>

                    {{-- Progress Bar --}}
                    <div class="d-flex align-items-center gap-3" style="min-width: 240px;">
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between mb-1 text-muted" style="font-size: 0.78rem;">
                                <span>Progress Plotting</span>
                                <strong class="text-dark">{{ $totalTerisi }}/{{ $totalKbm }} KBM ({{ $persentase }}%)</strong>
                            </div>
                            <div class="progress rounded-pill" style="height: 8px; background-color: #f1f5f9;">
                                <div class="progress-bar rounded-pill {{ $persentase === 100 ? 'bg-success' : 'bg-primary' }}"
                                     role="progressbar" style="width: {{ $persentase }}%;" aria-valuenow="{{ $persentase }}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                        @if($jamPelajaranList->where('jenis', '!=', 'istirahat')->count() > $totalTerisi)
                            <button type="button" class="btn btn-sm btn-primary rounded-3 px-3 py-2 fw-semibold d-flex align-items-center gap-1 shadow-sm"
                                    data-bs-toggle="modal" data-bs-target="#modalPlottingJadwal"
                                    data-jam-ke="1"
                                    onclick="preparePlotModal(1)">
                                <i class="bi bi-plus-lg"></i> Plot Mapel
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Tabel Matriks Jadwal Kelas --}}
        <div class="card border-0 rounded-4 shadow-sm bg-white overflow-hidden">
            <div class="card-header bg-white border-0 pt-4 pb-2 px-4">
                <div class="d-flex align-items-center justify-content-between">
                    <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.95rem;">
                        <i class="bi bi-grid-3x3-gap-fill text-primary me-2"></i>Matriks Slot Jam & Plotting Mata Pelajaran
                    </h6>
                    <span class="badge bg-light text-secondary border rounded-pill px-3 py-1" style="font-size: 0.75rem;">
                        Total {{ $totalSlot }} Slot ({{ $selectedHari === 'Jumat' ? 'Preset Jumat' : 'Preset Senin–Kamis' }})
                    </span>
                </div>
            </div>

            <div class="card-body p-0">
                @if($jamPelajaranList->isEmpty())
                    <div class="text-center py-5">
                        <i class="bi bi-clock text-muted" style="font-size: 2.5rem;"></i>
                        <p class="text-muted mt-3 mb-1 fw-semibold">Master Jam Pelajaran untuk {{ $selectedHari }} belum di-setting</p>
                        <p class="text-muted mb-3" style="font-size: 0.85rem;">
                            Silakan buka modul Master Jam Pelajaran lalu klik <strong>Generate Preset</strong>.
                        </p>
                        <a href="{{ route('kurikulum.jam-pelajaran.index', ['tab' => ($selectedHari === 'Jumat' ? 'Jumat' : 'Senin-Kamis')]) }}" class="btn btn-primary rounded-3 px-4 py-2 fw-semibold">
                            <i class="bi bi-lightning-charge-fill me-1"></i> Buka Master Jam Pelajaran
                        </a>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
                            <thead style="background: #f8fafc;">
                                <tr>
                                    <th class="ps-4 py-3" style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #64748b; width: 110px;">Jam Ke-</th>
                                    <th class="py-3" style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #64748b; width: 170px;">Rentang Waktu</th>
                                    <th class="py-3" style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #64748b; width: 130px;">Jenis Slot</th>
                                    <th class="py-3" style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #64748b;">Mata Pelajaran</th>
                                    <th class="py-3" style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #64748b;">Guru Pengajar</th>
                                    <th class="py-3 pe-4 text-end" style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #64748b; width: 140px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $istirahatCount = 0; @endphp
                                @foreach($jamPelajaranList as $jam)
                                    @php
                                        $mulai   = \Carbon\Carbon::createFromFormat('H:i:s', $jam->jam_mulai);
                                        $selesai = \Carbon\Carbon::createFromFormat('H:i:s', $jam->jam_selesai);
                                        $durasi  = $mulai->diffInMinutes($selesai);

                                        $isIstirahat = ($jam->jenis === 'istirahat');
                                        if ($isIstirahat) {
                                            $istirahatCount++;
                                            $slotName = "Istirahat " . $istirahatCount;
                                        } else {
                                            $slotName = "Jam " . ($jam->jam_ke ?? '-');
                                        }

                                        $waktuFormatted = substr(str_replace(':', '.', $jam->jam_mulai), 0, 5) . ' – ' . substr(str_replace(':', '.', $jam->jam_selesai), 0, 5);

                                        // Cek apakah ada jadwal di slot ini
                                        $jadwal = $jadwalList->get($jam->id);
                                    @endphp

                                    <tr class="{{ $isIstirahat ? 'bg-light-subtle' : '' }}" style="{{ $isIstirahat ? 'background-color: #fafafa;' : '' }}">
                                        {{-- 1. Jam Ke- --}}
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center gap-2">
                                                @if(!$isIstirahat && $jam->jam_ke)
                                                    <div class="rounded-circle d-flex align-items-center justify-content-center fw-black text-white"
                                                         style="width: 30px; height: 30px; font-size: 0.78rem; background: {{ $jam->jenis === 'kbm' ? '#1677ff' : ($jam->jenis === 'upacara' ? '#2563eb' : '#7c3aed') }};">
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

                                        {{-- 2. Rentang Waktu --}}
                                        <td>
                                            <div class="d-flex flex-column">
                                                <span class="fw-semibold text-dark" style="font-variant-numeric: tabular-nums; font-family: 'Courier New', monospace; font-size: 0.9rem;">
                                                    {{ $waktuFormatted }}
                                                </span>
                                                <span class="text-muted" style="font-size: 0.75rem;">{{ $durasi }} menit</span>
                                            </div>
                                        </td>

                                        {{-- 3. Jenis Slot --}}
                                        <td>
                                            @if($jam->jenis === 'kbm')
                                                <span class="badge d-inline-flex align-items-center gap-1 px-2 py-1 rounded-pill fw-semibold"
                                                      style="font-size: 0.75rem; background-color: #ecfdf5; color: #059669; border: 1px solid #a7f3d0;">
                                                    <i class="bi bi-book-fill" style="font-size: 0.7rem;"></i> KBM
                                                </span>
                                            @elseif($jam->jenis === 'istirahat')
                                                <span class="badge d-inline-flex align-items-center gap-1 px-2 py-1 rounded-pill fw-semibold"
                                                      style="font-size: 0.75rem; background-color: #fff7ed; color: #ea580c; border: 1px solid #fed7aa;">
                                                    <i class="bi bi-cup-hot-fill" style="font-size: 0.7rem;"></i> {{ $slotName }}
                                                </span>
                                            @elseif($jam->jenis === 'upacara')
                                                <span class="badge d-inline-flex align-items-center gap-1 px-2 py-1 rounded-pill fw-semibold"
                                                      style="font-size: 0.75rem; background-color: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe;">
                                                    <i class="bi bi-flag-fill" style="font-size: 0.7rem;"></i> Upacara
                                                </span>
                                            @elseif($jam->jenis === 'pembiasaan')
                                                <span class="badge d-inline-flex align-items-center gap-1 px-2 py-1 rounded-pill fw-semibold"
                                                      style="font-size: 0.75rem; background-color: #f5f3ff; color: #7c3aed; border: 1px solid #ddd6fe;">
                                                    <i class="bi bi-heart-pulse-fill" style="font-size: 0.7rem;"></i> Pembiasaan
                                                </span>
                                            @endif
                                        </td>

                                        {{-- 4. Mata Pelajaran --}}
                                        <td>
                                            @if($isIstirahat)
                                                <div class="d-inline-flex align-items-center gap-1 text-muted px-2 py-1 bg-light rounded-2 border border-dashed" style="font-size: 0.82rem;">
                                                    <i class="bi bi-lock-fill text-muted"></i>
                                                    <span>Istirahat (Non-KBM)</span>
                                                </div>
                                            @elseif($jadwal)
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="rounded-2 d-flex align-items-center justify-content-center bg-primary-subtle text-primary fw-bold" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                                        <i class="bi bi-journal-text"></i>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold text-dark" style="font-size: 0.92rem;">
                                                            {{ $jadwal->mataPelajaran->nama_mapel ?? 'Mapel Terhapus' }}
                                                        </div>
                                                        <div class="text-muted" style="font-size: 0.75rem;">
                                                            Kode: <span class="badge bg-light text-dark border">{{ $jadwal->mataPelajaran->kode_mapel ?? '-' }}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            @else
                                                <span class="badge bg-secondary-subtle text-secondary border border-dashed px-3 py-2 rounded-2 fw-medium" style="font-size: 0.8rem;">
                                                    <i class="bi bi-dash-circle me-1"></i> Belum di-plot
                                                </span>
                                            @endif
                                        </td>

                                        {{-- 5. Guru Pengajar --}}
                                        <td>
                                            @if($isIstirahat)
                                                <span class="text-muted" style="font-size: 0.85rem;">-</span>
                                            @elseif($jadwal)
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold"
                                                         style="width: 28px; height: 28px; font-size: 0.75rem; background: #64748b;">
                                                        {{ strtoupper(substr($jadwal->guru->nama ?? 'G', 0, 1)) }}
                                                    </div>
                                                    <div>
                                                        <div class="fw-semibold text-dark" style="font-size: 0.88rem;">
                                                            {{ $jadwal->guru->nama ?? 'Guru Tidak Ditemukan' }}
                                                        </div>
                                                        @if(!empty($jadwal->guru->nip))
                                                            <div class="text-muted" style="font-size: 0.72rem;">NIP: {{ $jadwal->guru->nip }}</div>
                                                        @endif
                                                    </div>
                                                </div>
                                            @else
                                                <span class="text-muted" style="font-size: 0.85rem;">-</span>
                                            @endif
                                        </td>

                                        {{-- 6. Aksi --}}
                                        <td class="pe-4 text-end">
                                            @if($isIstirahat)
                                                <span class="badge bg-light text-muted border px-2 py-1" style="font-size: 0.75rem;">
                                                    <i class="bi bi-lock-fill me-1"></i>Terkunci
                                                </span>
                                            @elseif($jadwal)
                                                <div class="d-flex gap-1 justify-content-end">
                                                    <button type="button" class="btn btn-sm btn-light border rounded-3 px-2 py-1"
                                                            style="font-size: 0.78rem;" title="Edit Plotting"
                                                            onclick="openEditModal(
                                                                {{ $jadwal->id }},
                                                                {{ $jadwal->id_mapel }},
                                                                {{ $jadwal->id_guru }},
                                                                {{ $jadwal->id_kelas }},
                                                                '{{ $jadwal->hari }}',
                                                                {{ $jadwal->id_jam }},
                                                                '{{ $slotName }} ({{ $waktuFormatted }})'
                                                            )">
                                                        <i class="bi bi-pencil-fill text-primary me-1"></i> Edit
                                                    </button>
                                                    <form method="POST" action="{{ route('kurikulum.jadwal.destroy', $jadwal->id) }}"
                                                          onsubmit="return confirm('Hapus plotting jadwal {{ $jadwal->mataPelajaran->nama_mapel ?? '' }} pada {{ $slotName }}?')"
                                                          class="d-inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-light border rounded-3 px-2 py-1" style="font-size: 0.78rem;" title="Hapus Plotting">
                                                            <i class="bi bi-trash3-fill text-danger me-1"></i> Hapus
                                                        </button>
                                                    </form>
                                                </div>
                                            @else
                                                <button type="button" class="btn btn-sm btn-outline-primary rounded-3 px-2 py-1 fw-semibold d-inline-flex align-items-center gap-1"
                                                        style="font-size: 0.78rem;"
                                                        data-bs-toggle="modal" data-bs-target="#modalPlottingJadwal"
                                                        data-jam-ke="{{ $jam->jam_ke ?? 1 }}"
                                                        data-jam-id="{{ $jam->id }}"
                                                        onclick="preparePlotModal({{ $jam->jam_ke ?? 1 }})">
                                                    <i class="bi bi-plus-lg"></i> Plot Mapel
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endif

</div>

{{-- ===================== MODAL PLOTTING JADWAL (MULTI-SLOT / BLOK JAM) ===================== --}}
@if($selectedKelas)
<div class="modal fade" id="modalPlottingJadwal" tabindex="-1" aria-labelledby="modalPlottingJadwalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <form method="POST" action="{{ route('kurikulum.jadwal.store') }}" id="formPlottingJadwal">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="modalPlottingJadwalTitle">
                        <i class="bi bi-calendar-plus-fill text-primary me-2"></i>Plotting Mata Pelajaran
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body pt-3">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark">Kelas</label>
                            <select name="id_kelas" class="form-select rounded-3" required>
                                @foreach($kelasList as $kelas)
                                    <option value="{{ $kelas->id }}" {{ $selectedKelas->id === $kelas->id ? 'selected' : '' }}>{{ $kelas->nama_kelas }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark">Hari</label>
                            <select name="hari" class="form-select rounded-3" required>
                                @foreach($hariList as $hari)
                                    <option value="{{ $hari }}" {{ $selectedHari === $hari ? 'selected' : '' }}>{{ $hari }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Info Banner --}}
                    <div class="p-3 mb-3 rounded-3 bg-light border">
                        <div class="row g-2" style="font-size: 0.85rem;">
                            <div class="col-6">
                                <span class="text-muted">Kelas:</span>
                                <div class="fw-bold text-dark">{{ $selectedKelas->nama_kelas }}</div>
                            </div>
                            <div class="col-6">
                                <span class="text-muted">Hari:</span>
                                <div class="fw-bold text-dark">{{ $selectedHari }}</div>
                            </div>
                        </div>
                    </div>

                    {{-- Pilihan Rentang Jam Pelajaran (Dari Jam Ke- s/d Sampai Jam Ke-) --}}
                    @php
                        $kbmSlots = $jamPelajaranList->where('jenis', '!=', 'istirahat')->whereNotNull('jam_ke')->sortBy('jam_ke');
                    @endphp
                    <div class="row g-3 mb-2">
                        <div class="col-6">
                            <label class="form-label fw-semibold text-dark mb-1" style="font-size: 0.875rem;">
                                <i class="bi bi-play-circle-fill text-primary me-1"></i> Dari Jam Ke-
                            </label>
                            <select name="jam_ke_mulai" id="plotJamKeMulai" class="form-select rounded-3" required onchange="onMulaiChange()">
                                @foreach($kbmSlots as $jam)
                                    @php
                                        $wkt = substr(str_replace(':', '.', $jam->jam_mulai), 0, 5) . ' – ' . substr(str_replace(':', '.', $jam->jam_selesai), 0, 5);
                                    @endphp
                                    <option value="{{ $jam->jam_ke }}">
                                        Jam {{ $jam->jam_ke }} ({{ $wkt }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold text-dark mb-1" style="font-size: 0.875rem;">
                                <i class="bi bi-stop-circle-fill text-primary me-1"></i> Sampai Jam Ke-
                            </label>
                            <select name="jam_ke_selesai" id="plotJamKeSelesai" class="form-select rounded-3" required onchange="onSelesaiChange()">
                                @foreach($kbmSlots as $jam)
                                    @php
                                        $wkt = substr(str_replace(':', '.', $jam->jam_mulai), 0, 5) . ' – ' . substr(str_replace(':', '.', $jam->jam_selesai), 0, 5);
                                    @endphp
                                    <option value="{{ $jam->jam_ke }}">
                                        Jam {{ $jam->jam_ke }} ({{ $wkt }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Live JP Counter & Info Banner --}}
                    <div class="p-2 mb-3 rounded-3 bg-primary-subtle border border-primary-subtle d-flex align-items-center justify-content-between flex-wrap gap-2" id="boxJpInfo">
                        <div class="d-flex align-items-center gap-2 text-primary" style="font-size: 0.82rem;">
                            <i class="bi bi-info-circle-fill fs-6 flex-shrink-0"></i>
                            <span id="labelJpInfo">Terpilih: <strong>1 JP</strong> (Jam 1)</span>
                        </div>
                        <span class="badge bg-primary rounded-pill px-2 py-1" id="badgeJpTotal" style="font-size: 0.78rem;">1 JP</span>
                    </div>

                    {{-- Pilih Mata Pelajaran --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark" style="font-size: 0.875rem;">Mata Pelajaran</label>
                        <select name="id_mapel" id="plotIdMapel" class="form-select rounded-3" required>
                            <option value="">-- Pilih Mata Pelajaran --</option>
                            @foreach($mapelList as $mapel)
                                <option value="{{ $mapel->id }}">
                                    {{ $mapel->nama_mapel }} ({{ $mapel->kode_mapel }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Pilih Guru Pengajar --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark" style="font-size: 0.875rem;">Guru Pengajar</label>
                        <select name="id_guru" id="plotIdGuru" class="form-select rounded-3" required>
                            <option value="">-- Pilih Guru Pengajar --</option>
                            @foreach($guruList as $guru)
                                <option value="{{ $guru->id }}">
                                    {{ $guru->nama }} {{ !empty($guru->nip) ? '— NIP: ' . $guru->nip : '' }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text text-muted" style="font-size: 0.78rem;">
                            <i class="bi bi-shield-check text-success me-1"></i>Sistem akan otomatis mengecek bentrok jadwal guru di kelas lain untuk seluruh jam yang dipilih.
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4 fw-semibold">
                        <i class="bi bi-check-lg me-1"></i> Simpan Plotting
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ===================== MODAL EDIT PLOTTING ===================== --}}
<div class="modal fade" id="modalEditJadwal" tabindex="-1" aria-labelledby="modalEditJadwalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <form method="POST" id="formEditJadwal" action="">
                @csrf
                @method('PUT')

                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="modalEditJadwalTitle">
                        <i class="bi bi-pencil-square text-warning me-2"></i>Edit Plotting Jadwal
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body pt-3">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-dark">Kelas</label>
                            <select name="id_kelas" id="editIdKelas" class="form-select rounded-3" required>
                                @foreach($kelasList as $kelas)
                                    <option value="{{ $kelas->id }}">{{ $kelas->nama_kelas }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-dark">Hari</label>
                            <select name="hari" id="editHari" class="form-select rounded-3" required>
                                @foreach($hariList as $hari)
                                    <option value="{{ $hari }}">{{ $hari }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-dark">Jam Pelajaran</label>
                            <select name="id_jam" id="editIdJam" class="form-select rounded-3" required>
                                @foreach($jamPelajaranList->where('jenis', '!=', 'istirahat') as $jam)
                                    <option value="{{ $jam->id }}">Jam {{ $jam->jam_ke }} ({{ substr($jam->jam_mulai, 0, 5) }} - {{ substr($jam->jam_selesai, 0, 5) }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Info Banner --}}
                    <div class="p-3 mb-3 rounded-3 bg-light border">
                        <div class="row g-2" style="font-size: 0.85rem;">
                            <div class="col-4">
                                <span class="text-muted">Kelas:</span>
                                <div class="fw-bold text-dark">{{ $selectedKelas->nama_kelas }}</div>
                            </div>
                            <div class="col-4">
                                <span class="text-muted">Hari:</span>
                                <div class="fw-bold text-dark">{{ $selectedHari }}</div>
                            </div>
                            <div class="col-4">
                                <span class="text-muted">Slot Jam:</span>
                                <div class="fw-bold text-dark" id="editSlotInfo">-</div>
                            </div>
                        </div>
                    </div>

                    {{-- Pilih Mata Pelajaran --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark" style="font-size: 0.875rem;">Mata Pelajaran</label>
                        <select name="id_mapel" id="editIdMapel" class="form-select rounded-3" required>
                            @foreach($mapelList as $mapel)
                                <option value="{{ $mapel->id }}">
                                    {{ $mapel->nama_mapel }} ({{ $mapel->kode_mapel }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Pilih Guru Pengajar --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark" style="font-size: 0.875rem;">Guru Pengajar</label>
                        <select name="id_guru" id="editIdGuru" class="form-select rounded-3" required>
                            @foreach($guruList as $guru)
                                <option value="{{ $guru->id }}">
                                    {{ $guru->nama }} {{ !empty($guru->nip) ? '— NIP: ' . $guru->nip : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning text-white rounded-3 px-4 fw-semibold">
                        <i class="bi bi-check-lg me-1"></i> Perbarui
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<!-- Choices.js JS for Searchable Select -->
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>

@php
    $formattedSlots = $jamPelajaranList->map(function($j) use ($jadwalList) {
        return [
            'id' => $j->id,
            'jam_ke' => $j->jam_ke,
            'jenis' => $j->jenis,
            'jam_mulai' => substr($j->jam_mulai, 0, 5),
            'jam_selesai' => substr($j->jam_selesai, 0, 5),
            'is_plotted' => $jadwalList ? $jadwalList->has($j->id) : false
        ];
    });
@endphp
<script>
    const allSlots = @json($formattedSlots);

    document.addEventListener('DOMContentLoaded', function () {
        // 1. Inisialisasi Choices.js pada Dropdown Pilih Kelas
        const selectKelasEl = document.getElementById('selectKelas');
        if (selectKelasEl) {
            const choices = new Choices(selectKelasEl, {
                searchEnabled: true,
                searchPlaceholderValue: 'Ketik nama kelas...',
                itemSelectText: '',
                shouldSort: false,
                placeholder: true,
                placeholderValue: '-- Pilih Kelas --',
                noResultsText: 'Kelas tidak ditemukan',
            });

            selectKelasEl.addEventListener('change', function () {
                if (this.value) {
                    document.getElementById('filterForm').submit();
                }
            });
        }

        // 2. Tangani event pembukaan modal plotting agar membaca data-jam-ke dari tombol pemicu
        const modalPlotting = document.getElementById('modalPlottingJadwal');
        if (modalPlotting) {
            modalPlotting.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                if (button) {
                    const jamKe = button.getAttribute('data-jam-ke');
                    if (jamKe) {
                        preparePlotModal(parseInt(jamKe));
                    }
                }
            });
        }

        // 3. Cegah auto-submit saat Enter ditekan di dalam input/select modal
        const forms = [
            document.getElementById('formPlottingJadwal'),
            document.getElementById('formEditJadwal')
        ];

        forms.forEach(function (form) {
            if (!form) return;
            const elements = form.querySelectorAll('input:not([type="hidden"]), select');
            elements.forEach(function (el, index) {
                el.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        if (index < elements.length - 1) {
                            elements[index + 1].focus();
                        }
                    }
                });
            });
        });

        updateJpInfo();
    });

    function onMulaiChange() {
        const mulaiEl = document.getElementById('plotJamKeMulai');
        const selesaiEl = document.getElementById('plotJamKeSelesai');
        if (!mulaiEl || !selesaiEl) return;

        const mulaiVal = parseInt(mulaiEl.value) || 1;
        const selesaiVal = parseInt(selesaiEl.value) || 1;

        if (selesaiVal < mulaiVal) {
            selesaiEl.value = mulaiVal;
        }
        updateJpInfo();
    }

    function onSelesaiChange() {
        const mulaiEl = document.getElementById('plotJamKeMulai');
        const selesaiEl = document.getElementById('plotJamKeSelesai');
        if (!mulaiEl || !selesaiEl) return;

        const mulaiVal = parseInt(mulaiEl.value) || 1;
        const selesaiVal = parseInt(selesaiEl.value) || 1;

        if (selesaiVal < mulaiVal) {
            mulaiEl.value = selesaiVal;
        }
        updateJpInfo();
    }

    function updateJpInfo() {
        const mulaiEl = document.getElementById('plotJamKeMulai');
        const selesaiEl = document.getElementById('plotJamKeSelesai');
        const labelEl = document.getElementById('labelJpInfo');
        const badgeEl = document.getElementById('badgeJpTotal');
        if (!mulaiEl || !selesaiEl || !labelEl || !badgeEl) return;

        const mulaiVal = parseInt(mulaiEl.value) || 1;
        const selesaiVal = parseInt(selesaiEl.value) || 1;

        // Filter slot KBM dalam rentang
        const selectedKbmSlots = allSlots.filter(s => s.jam_ke !== null && s.jenis !== 'istirahat' && s.jam_ke >= mulaiVal && s.jam_ke <= selesaiVal);
        const totalJp = selectedKbmSlots.length;

        // Cek apakah ada istirahat di dalam rentang waktu jam mulai s/d jam selesai
        let hasIstirahat = false;
        if (selectedKbmSlots.length > 0) {
            const firstSlot = selectedKbmSlots[0];
            const lastSlot = selectedKbmSlots[selectedKbmSlots.length - 1];
            hasIstirahat = allSlots.some(s => s.jenis === 'istirahat' && s.jam_mulai >= firstSlot.jam_mulai && s.jam_selesai <= lastSlot.jam_selesai);
        }

        let note = '';
        if (hasIstirahat) {
            note = ' &bull; <span class="text-warning-emphasis fw-semibold">(Melewati Jam Istirahat)</span>';
        }

        if (mulaiVal === selesaiVal) {
            labelEl.innerHTML = `Terpilih: <strong>${totalJp} JP</strong> (Jam ${mulaiVal})${note}`;
        } else {
            labelEl.innerHTML = `Terpilih: <strong>${totalJp} JP</strong> (Jam ${mulaiVal} s/d Jam ${selesaiVal})${note}`;
        }

        badgeEl.textContent = `${totalJp} JP`;
    }

    function preparePlotModal(jamKe) {
        const mulaiEl = document.getElementById('plotJamKeMulai');
        const selesaiEl = document.getElementById('plotJamKeSelesai');
        if (jamKe && mulaiEl && selesaiEl) {
            mulaiEl.value = String(jamKe);
            selesaiEl.value = String(jamKe);
        }
        updateJpInfo();
    }

    function openEditModal(idJadwal, idMapel, idGuru, idKelas, hari, idJam, slotInfo) {
        const routeBase = "{{ url('kurikulum/jadwal') }}";
        document.getElementById('formEditJadwal').action = routeBase + '/' + idJadwal;

        document.getElementById('editSlotInfo').textContent = slotInfo;
        document.getElementById('editIdMapel').value = idMapel;
        document.getElementById('editIdGuru').value  = idGuru;
        document.getElementById('editIdKelas').value = idKelas;
        document.getElementById('editHari').value = hari;
        document.getElementById('editIdJam').value = idJam;

        const modal = new bootstrap.Modal(document.getElementById('modalEditJadwal'));
        modal.show();
    }
</script>
@endpush
