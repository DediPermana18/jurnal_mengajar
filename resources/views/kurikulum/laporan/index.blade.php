@extends('layouts.app')

@section('title', 'Laporan KBM - Kurikulum')

@section('content')
<div class="container-fluid px-0">

    {{-- Page Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="font-weight: 900; font-size: 1.75rem; letter-spacing: -0.02em;">
                Laporan KBM
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                Rekapitulasi keterlaksanaan Kegiatan Belajar Mengajar per tanggal, kelas, guru, dan mata pelajaran.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="{{ route('kurikulum.laporan.print', request()->query()) }}" class="btn btn-outline-danger rounded-3 px-3 py-2 fw-semibold">
                <i class="bi bi-file-earmark-pdf me-1"></i> Download PDF
            </a>
            <a href="{{ route('kurikulum.laporan.excel', request()->query()) }}" class="btn btn-outline-success rounded-3 px-3 py-2 fw-semibold">
                <i class="bi bi-file-earmark-excel me-1"></i> Export Excel
            </a>
        </div>
    </div>

    {{-- Filter Card --}}
    <div class="table-card-custom mb-4">
        <form method="GET" action="{{ route('kurikulum.laporan.index') }}" class="row g-3 align-items-end">
            <div class="col-12 col-md-3 col-xl-2">
                <label class="form-label fw-bold text-secondary text-uppercase small mb-1">Tanggal Mulai</label>
                <input type="date" name="tanggal_mulai" value="{{ old('tanggal_mulai', $mulai) }}"
                       class="form-control rounded-3 py-2">
            </div>
            <div class="col-12 col-md-3 col-xl-2">
                <label class="form-label fw-bold text-secondary text-uppercase small mb-1">Tanggal Selesai</label>
                <input type="date" name="tanggal_selesai" value="{{ old('tanggal_selesai', $selesai) }}"
                       class="form-control rounded-3 py-2">
            </div>
            <div class="col-12 col-md-3 col-xl-2">
                <label class="form-label fw-bold text-secondary text-uppercase small mb-1">Tingkat</label>
                <select name="tingkat" class="form-select rounded-3 py-2">
                    <option value="">Semua Tingkat</option>
                    @foreach($tingkatList as $tgl)
                        <option value="{{ $tgl }}" {{ $tingkatInput == $tgl ? 'selected' : '' }}>{{ $tgl }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-3 col-xl-2">
                <label class="form-label fw-bold text-secondary text-uppercase small mb-1">Kelas</label>
                <select name="id_kelas" class="form-select rounded-3 py-2">
                    <option value="">Semua Kelas</option>
                    @foreach($kelasList as $kelas)
                        <option value="{{ $kelas->id }}" {{ $idKelasInput == $kelas->id ? 'selected' : '' }}>
                            {{ $kelas->nama_lengkap }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-4 col-xl-2">
                <label class="form-label fw-bold text-secondary text-uppercase small mb-1">Guru</label>
                <select name="id_guru" class="form-select rounded-3 py-2">
                    <option value="">Semua Guru</option>
                    @foreach($guruList as $guru)
                        <option value="{{ $guru->id }}" {{ $idGuruInput == $guru->id ? 'selected' : '' }}>
                            {{ $guru->nama }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-4 col-xl-2">
                <label class="form-label fw-bold text-secondary text-uppercase small mb-1">Mata Pelajaran</label>
                <select name="id_mapel" class="form-select rounded-3 py-2">
                    <option value="">Semua Mapel</option>
                    @foreach($mapelList as $mapel)
                        <option value="{{ $mapel->id }}" {{ $idMapelInput == $mapel->id ? 'selected' : '' }}>
                            {{ $mapel->nama_mapel }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-xl-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary rounded-3 px-3 py-2 fw-semibold flex-fill">
                    <i class="bi bi-funnel me-1"></i> Terapkan
                </button>
                <a href="{{ route('kurikulum.laporan.index') }}" class="btn btn-outline-secondary rounded-3 px-3 py-2">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>

    {{-- Metric Cards --}}
    <div class="row g-4 mb-4">
        <div class="col-12 col-sm-6 col-xl-4">
            <div class="stat-card-custom h-100">
                <div class="stat-card-title">Total Jam KBM Terlaksana</div>
                <div class="stat-number-large text-success">{{ number_format($totalJamKBM) }}</div>
                <div class="stat-card-label">sesi KBM yang tercatat</div>
                <p class="stat-card-subtext">{{ $periodeMulai }} – {{ $periodeSelesai }}</p>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-4">
            <div class="stat-card-custom h-100">
                <div class="stat-card-title">Kehadiran Guru</div>
                <div class="stat-number-large text-primary">Hadir: {{ number_format($guruHadir) }}</div>
                <div class="stat-card-label">Izin/Sakit/Dinas: {{ number_format($guruTidakHadir) }}</div>
                <p class="stat-card-subtext">
                    Izin {{ number_format($guruIzin) }} &middot; Sakit {{ number_format($guruSakit) }} &middot; Dinas {{ number_format($guruDinas) }}
                </p>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-4">
            <div class="stat-card-custom h-100">
                <div class="stat-card-title">Jurnal Mengajar Terisi</div>
                <div class="stat-number-large text-warning">{{ number_format($totalJurnalTerisi) }}</div>
                <div class="stat-card-label">jurnal dengan materi terisi</div>
                <p class="stat-card-subtext">
                    {{ $totalJurnalTerisi > 0 ? number_format(($totalJurnalTerisi / max($totalJamKBM, 1)) * 100, 1) : 0 }}% dari total sesi
                </p>
            </div>
        </div>
    </div>

    {{-- Tabel Rekapitulasi --}}
    <div class="table-card-custom mb-4">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h5 class="fw-bold text-dark mb-0">Rekapitulasi KBM</h5>
            <span class="text-muted small">Menampilkan {{ $daftarJurnal->total() }} sesi jurnal</span>
        </div>
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th>TANGGAL</th>
                        <th>JAM KE-</th>
                        <th>KELAS</th>
                        <th>GURU</th>
                        <th>MATA PELAJARAN</th>
                        <th>MATERI / JURNAL</th>
                        <th class="text-center">STATUS KEHADIRAN</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($daftarJurnal as $jurnal)
                        @php
                            $jadwal = $jurnal->jadwalPelajaran;
                            $statusClass = match($jurnal->status_kehadiran) {
                                'Izin'       => 'bg-warning-subtle text-warning-emphasis border-warning-subtle',
                                'Sakit'      => 'bg-danger-subtle text-danger border-danger-subtle',
                                'Disposisi'  => 'bg-info-subtle text-info-emphasis border-info-subtle',
                                default      => 'bg-success-subtle text-success border-success-subtle',
                            };
                        @endphp
                        <tr>
                            <td class="fw-semibold text-dark text-nowrap">
                                <div>{{ $jurnal->tanggal->translatedFormat('d/m/Y') }}</div>
                                <small class="text-muted">{{ $jurnal->tanggal->translatedFormat('l') }}</small>
                            </td>
                            <td class="text-nowrap">
                                @if($jadwal?->jam)
                                    <span class="fw-semibold text-dark">Jam ke-{{ $jadwal->jam->jam_ke ?? '-' }}</span>
                                    <div><small class="text-muted">{{ $jadwal->jam->rentang_waktu }}</small></div>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                <span class="fw-semibold text-dark">{{ $jadwal?->kelas?->nama_kelas ?? '-' }}</span>
                                <div><small class="text-muted">{{ $jadwal?->kelas?->tingkat ?? '' }}</small></div>
                            </td>
                            <td>
                                <div class="fw-medium text-dark">
                                    {{ $jurnal->guru?->nama ?? $jadwal?->guru?->nama ?? '-' }}
                                </div>
                                @if($jurnal->guruPengganti)
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-1 small mt-1">
                                        <i class="bi bi-person-fill-gear me-1"></i>{{ $jurnal->guruPengganti->nama }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                <span class="text-secondary">{{ $jadwal?->mapel?->nama_mapel ?? '-' }}</span>
                            </td>
                            <td style="max-width: 260px;">
                                @if($jurnal->materi)
                                    <div class="text-dark fw-medium text-wrap">{{ $jurnal->materi }}</div>
                                    @if($jurnal->catatan_kejadian)
                                        <small class="text-muted d-block text-truncate" style="max-width: 240px;">Catatan: {{ $jurnal->catatan_kejadian }}</small>
                                    @endif
                                @else
                                    <span class="text-muted">Belum diisi materi</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge border rounded-pill px-2 py-1 small {{ $statusClass }}">
                                    {{ $jurnal->status_kehadiran ?? 'Hadir' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                                Belum ada data jurnal pada rentang filter yang dipilih.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($daftarJurnal->hasPages())
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-4 pt-3 border-top">
                <div class="text-muted small mb-3 mb-md-0">
                    Menampilkan <strong>{{ $daftarJurnal->firstItem() ?? 0 }}</strong>-<strong>{{ $daftarJurnal->lastItem() ?? 0 }}</strong> dari <strong>{{ $daftarJurnal->total() }}</strong> sesi
                </div>
                {{ $daftarJurnal->links() }}
            </div>
        @endif
    </div>

</div>
@endsection