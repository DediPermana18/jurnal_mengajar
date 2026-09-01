@extends('layouts.app')

@section('title', 'Monitoring Slot Jadwal Kosong - Kurikulum')

@section('content')
<div class="container-fluid px-0">

    {{-- Header Halaman --}}
    <div class="mb-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <h2 class="fw-black text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 900; font-size: 1.75rem;">
                    <i class="bi bi-search text-primary me-2"></i>Monitoring Slot Jadwal Kosong
                </h2>
                <p class="text-muted mb-0" style="font-size: 0.95rem; font-weight: 500;">
                    Daftar kelas dengan slot KBM yang belum di-plot agar plotting lebih cepat.
                </p>
            </div>
            <a href="{{ route('admin.jadwal.index') }}" class="btn btn-light border rounded-3 px-4 py-2 fw-semibold shadow-sm">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Plotting Jadwal
            </a>
        </div>
    </div>

    {{-- Ringkasan Statistik --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 rounded-4 shadow-sm bg-white h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center text-white flex-shrink-0"
                         style="width: 48px; height: 48px; background: linear-gradient(135deg, #1677ff, #0958d9);">
                        <i class="bi bi-building"></i>
                    </div>
                    <div>
                        <div class="text-muted" style="font-size: 0.78rem;">Total Kelas</div>
                        <div class="fw-bold text-dark" style="font-size: 1.35rem;">{{ $totalKelas }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 rounded-4 shadow-sm bg-white h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center text-white flex-shrink-0"
                         style="width: 48px; height: 48px; background: linear-gradient(135deg, #f97316, #ea580c);">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <div>
                        <div class="text-muted" style="font-size: 0.78rem;">Total Slot KBM Kosong</div>
                        <div class="fw-bold text-dark" style="font-size: 1.35rem;">{{ $totalSlotKosong }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 rounded-4 shadow-sm bg-white h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center text-white flex-shrink-0"
                         style="width: 48px; height: 48px; background: linear-gradient(135deg, #22c55e, #16a34a);">
                        <i class="bi bi-check2-circle"></i>
                    </div>
                    <div>
                        <div class="text-muted" style="font-size: 0.78rem;">Kelas Lengkap</div>
                        <div class="fw-bold text-dark" style="font-size: 1.35rem;">{{ $jumlahKelasLengkap }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabel Daftar Slot Kosong --}}
    <div class="card border-0 rounded-4 shadow-sm bg-white overflow-hidden">
        @if(empty($rows))
            <div class="card-body text-center py-5">
                <i class="bi bi-check2-circle text-success" style="font-size: 3rem;"></i>
                <h5 class="fw-bold text-dark mt-3 mb-1">Semua Slot KBM Sudah Terisi Penuh</h5>
                <p class="text-muted mb-4" style="font-size: 0.9rem;">Tidak ada kelas yang memiliki slot jadwal kosong. Kerja bagus!</p>
                <a href="{{ route('admin.jadwal.index') }}" class="btn btn-primary rounded-3 px-4 py-2 fw-semibold shadow-sm">
                    <i class="bi bi-arrow-left me-1"></i> Kembali ke Plotting Jadwal
                </a>
            </div>
        @else
            <div class="card-header bg-white border-0 pt-4 pb-2 px-4">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.95rem;">
                        <i class="bi bi-grid-3x3-gap-fill text-primary me-2"></i>Daftar Kelas dengan Slot KBM Kosong
                    </h6>
                    <span class="badge bg-danger-subtle text-danger border rounded-pill px-3 py-2" style="font-size: 0.78rem;">
                        {{ count($rows) }} baris
                    </span>
                </div>
            </div>
            <div class="table-responsive w-full overflow-x-auto">
                <table class="table table-hover align-middle mb-0 min-w-full" id="tableMonitoringKosong">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4 py-3 whitespace-nowrap">Kelas</th>
                            <th class="py-3 whitespace-nowrap">Hari</th>
                            <th class="py-3 whitespace-nowrap">Jam Kosong</th>
                            <th class="text-center py-3 whitespace-nowrap">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            @php
                                $urlPlotting = route('admin.jadwal.index', [
                                    'id_kelas' => $row['kelas_id'],
                                    'hari'     => $row['hari'],
                                ]);
                            @endphp
                            <tr style="cursor: pointer;" data-url="{{ $urlPlotting }}">
                                <td class="ps-4 py-3">
                                    <a href="{{ $urlPlotting }}" class="text-decoration-none">
                                        <div class="fw-bold text-dark">{{ $row['tingkat'] }} - {{ $row['kelas_nama'] }}</div>
                                        <div class="text-muted" style="font-size: 0.75rem;">{{ $row['jurusan'] ?? 'Umum' }}</div>
                                    </a>
                                </td>
                                <td class="py-3">
                                    <a href="{{ $urlPlotting }}" class="text-decoration-none">
                                        <span class="badge bg-light text-dark border rounded-pill px-3 py-2">
                                            <i class="bi bi-calendar-week me-1"></i>{{ $row['hari'] }}
                                        </span>
                                    </a>
                                </td>
                                <td class="py-3 whitespace-nowrap">
                                    <span class="fw-semibold text-danger">[{{ implode(', ', $row['jam_kosong']) }}]</span>
                                </td>
                                <td class="text-center py-3 whitespace-nowrap">
                                    <a href="{{ $urlPlotting }}" class="text-decoration-none">
                                        <span class="badge bg-danger text-white rounded-pill px-2 py-1">{{ $row['jumlah'] }}</span>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('#tableMonitoringKosong tbody tr[data-url]').forEach(function (row) {
            row.addEventListener('click', function (e) {
                if (e.target.closest('a')) {
                    return; // biarkan link bawaan bekerja
                }
                window.location.href = row.getAttribute('data-url');
            });
        });
    });
</script>
@endpush
