@extends('layouts.app')

@section('title', 'Presensi Siswa Harian - Guru Piket')

@push('styles')
<style>
    .status-radio-group .form-check-input:checked {
        background-color: var(--bs-primary);
        border-color: var(--bs-primary);
    }
    .status-badge-hadir { background-color: #d1e7dd; color: #0f5132; }
    .status-badge-sakit { background-color: #f8d7da; color: #842029; }
    .status-badge-izin { background-color: #cff4fc; color: #055160; }
    .status-badge-alpha { background-color: #e2e3e5; color: #41464b; }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">

    {{-- Page Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 800; font-size: 1.75rem;">
                Presensi Siswa Harian
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                Input dan kelola kehadiran siswa per kelas
            </p>
        </div>
        <span class="text-muted small"><i class="bi bi-calendar3 me-1"></i>{{ \Carbon\Carbon::parse($tanggal)->translatedFormat('l, d F Y') }}</span>
    </div>

    {{-- Alert Messages --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4 d-flex align-items-center gap-2" role="alert"
             style="background: #ecfdf5; color: #065f46; font-size: 0.9rem;">
            <i class="bi bi-check-circle-fill text-success fs-5"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4 d-flex align-items-center gap-2" role="alert"
             style="background: #fef2f2; color: #991b1b; font-size: 0.9rem;">
            <i class="bi bi-exclamation-triangle-fill text-danger fs-5"></i>
            <div>{{ session('error') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Filter Form --}}
    <form method="GET" action="{{ route('piket.presensi-siswa') }}" class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold text-dark mb-1" style="font-size: 0.85rem;">
                        Kelas <span class="text-danger">*</span>
                    </label>
                    <select name="id_kelas" id="filterKelas" class="form-select rounded-3 @error('id_kelas') is-invalid @enderror"
                            style="font-size: 0.875rem;" required>
                        <option value="">-- Pilih Kelas --</option>
                        @foreach($kelasList as $kelas)
                            <option value="{{ $kelas->id }}" {{ $idKelas == $kelas->id ? 'selected' : '' }}>
                                {{ $kelas->nama_kelas }} ({{ $kelas->jurusan?->nama_jurusan ?? '-' }})
                            </option>
                        @endforeach
                    </select>
                    @error('id_kelas')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label fw-semibold text-dark mb-1" style="font-size: 0.85rem;">
                        Tanggal <span class="text-danger">*</span>
                    </label>
                    <input type="date"
                           name="tanggal"
                           id="filterTanggal"
                           value="{{ $tanggal }}"
                           max="{{ $today }}"
                           class="form-control rounded-3 @error('tanggal') is-invalid @enderror"
                           style="font-size: 0.875rem;"
                           required>
                    @error('tanggal')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-3">
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary fw-semibold rounded-3 py-2">
                            <i class="bi bi-search me-1"></i> Tampilkan Siswa
                        </button>
                    </div>
                </div>

                <div class="col-12 col-md-2">
                    @if($idKelas)
                        <a href="{{ route('piket.presensi-siswa') }}" class="btn btn-outline-secondary fw-semibold rounded-3 py-2 w-100">
                            <i class="bi bi-x-circle me-1"></i> Reset
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </form>

    {{-- Presensi Table --}}
    @if($idKelas)
        <form method="POST" action="{{ route('piket.presensi-siswa.store') }}" id="formPresensi">
            @csrf
            <input type="hidden" name="tanggal" value="{{ $tanggal }}">
            <input type="hidden" name="id_kelas" value="{{ $idKelas }}">

            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-bottom rounded-top-4 py-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <h5 class="fw-bold text-dark mb-0">
                            <i class="bi bi-people-fill text-primary me-2"></i>
                            Daftar Siswa - {{ $dataSiswa->first()?->kelas?->nama_kelas ?? 'Kelas' }}
                        </h5>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-success rounded-3" onclick="setAllStatus('Hadir')">
                                <i class="bi bi-check-circle me-1"></i> Set Semua Hadir
                            </button>
                            <button type="submit" class="btn btn-sm btn-primary fw-semibold rounded-3">
                                <i class="bi bi-save me-1"></i> Simpan Presensi
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-body p-0">
                    @if($dataSiswa->isNotEmpty())
                        <div class="table-responsive w-full overflow-x-auto">
                            <table class="table table-hover align-middle mb-0 min-w-full">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="text-center whitespace-nowrap" style="width: 50px;">NO</th>
                                        <th class="whitespace-nowrap" style="width: 100px;">NISN</th>
                                        <th>NAMA SISWA</th>
                                        <th class="text-center whitespace-nowrap" style="width: 300px;">STATUS ABSENSI</th>
                                        <th style="width: 200px;">KETERANGAN</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($dataSiswa as $index => $siswa)
                                        @php
                                            $existing = $presensiExisting->get($siswa->id);
                                            $currentStatus = $existing ? $existing->status : 'Hadir';
                                            $currentKeterangan = $existing ? $existing->keterangan : '';
                                        @endphp
                                        <tr>
                                            <td class="text-center fw-semibold text-dark whitespace-nowrap">{{ $loop->iteration }}</td>
                                            <td class="text-muted small whitespace-nowrap">{{ $siswa->nisn ?? '-' }}</td>
                                            <td>
                                                <div class="fw-semibold text-dark">{{ $siswa->nama }}</div>
                                            </td>
                                            <td>
                                                <div class="status-radio-group d-flex flex-wrap gap-3">
                                                    @foreach(['Hadir', 'Sakit', 'Izin', 'Alpha'] as $status)
                                                        @php
                                                            $badgeClass = match($status) {
                                                                'Hadir' => 'status-badge-hadir',
                                                                'Sakit' => 'status-badge-sakit',
                                                                'Izin' => 'status-badge-izin',
                                                                'Alpha' => 'status-badge-alpha',
                                                            };
                                                        @endphp
                                                        <div class="form-check">
                                                            <input class="form-check-input"
                                                                   type="radio"
                                                                   name="presensi[{{ $siswa->id }}][status]"
                                                                   id="status_{{ $siswa->id }}_{{ $status }}"
                                                                   value="{{ $status }}"
                                                                   {{ $currentStatus === $status ? 'checked' : '' }}
                                                                   required>
                                                            <label class="form-check-label fw-medium small px-2 py-1 rounded-pill border {{ $badgeClass }}"
                                                                   for="status_{{ $siswa->id }}_{{ $status }}"
                                                                   style="cursor: pointer; min-width: 70px; text-align: center;">
                                                                {{ $status }}
                                                            </label>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </td>
                                            <td>
                                                <input type="text"
                                                       name="presensi[{{ $siswa->id }}][keterangan]"
                                                       class="form-control form-control-sm rounded-3"
                                                       value="{{ $currentKeterangan }}"
                                                       placeholder="Keterangan (opsional)"
                                                       style="font-size: 0.82rem;">
                                                <input type="hidden" name="presensi[{{ $siswa->id }}][id_siswa]" value="{{ $siswa->id }}">
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="bi bi-people fs-1 d-block mb-2 opacity-50"></i>
                            <p class="text-muted mb-0">Tidak ada siswa aktif di kelas ini.</p>
                        </div>
                    @endif
                </div>
            </div>
        </form>
    @else
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body text-center py-5">
                <i class="bi bi-funnel-fill text-muted mb-3" style="font-size: 3rem;"></i>
                <h5 class="fw-bold text-dark mb-2">Pilih Kelas Terlebih Dahulu</h5>
                <p class="text-muted mb-3">Silakan pilih kelas dan tanggal pada filter di atas untuk menampilkan daftar siswa.</p>
            </div>
        </div>
    @endif

</div>
@endsection