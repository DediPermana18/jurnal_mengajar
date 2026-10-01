@extends('layouts.app')

@section('title', 'Rekap Absen Siswa - Wali Kelas')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-black text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 800; font-size: 1.75rem;">
                📊 Rekap Absen Siswa
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                Rekapitulasi ketidakhadiran dan kehadiran siswa kelas bimbingan Anda (Wali Kelas).
            </p>
        </div>
    </div>

    <div class="table-card-custom mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">Rekapitulasi Kehadiran {{ $namaKelasSaya ?? 'Kelas Bimbingan' }}</h5>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1">{{ $rekapAbsen->count() }} Siswa</span>
        </div>
        <div class="table-responsive w-full overflow-x-auto">
            <table class="table table-custom align-middle min-w-full">
                <thead>
                    <tr>
                        <th class="text-center whitespace-nowrap py-3 px-3" style="width: 60px;">No</th>
                        <th class="py-3 px-4 whitespace-nowrap" style="width: 140px;">NIS/NISN</th>
                        <th class="py-3 px-4">Nama Siswa</th>
                        <th class="text-center py-3 px-4" style="width: 100px;">Hadir</th>
                        <th class="text-center py-3 px-4" style="width: 100px;">Izin</th>
                        <th class="text-center py-3 px-4" style="width: 100px;">Sakit</th>
                        <th class="text-center text-danger py-3 px-4" style="width: 100px;">Alpha</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rekapAbsen as $r)
                        <tr>
                            <td class="text-center text-muted whitespace-nowrap py-3 px-3">{{ $loop->iteration }}</td>
                            <td class="py-3 px-4 whitespace-nowrap">{{ $r['siswa']->nisn ?? $r['siswa']->nis ?? '-' }}</td>
                            <td class="py-3 px-4"><strong>{{ $r['siswa']->nama }}</strong></td>
                            <td class="text-center text-success fw-bold py-3 px-4">
                                <div>{{ $r['hadir'] }}</div>
                                @if(($r['terlambat'] ?? 0) > 0)
                                    <div class="mt-1">
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-2 py-0.5 fw-semibold" style="font-size: 0.7rem;">
                                            <i class="bi bi-clock-history me-1"></i>{{ $r['terlambat'] }} Terlambat
                                        </span>
                                    </div>
                                @endif
                            </td>
                            <td class="text-center py-3 px-4">{{ $r['izin'] }}</td>
                            <td class="text-center py-3 px-4">{{ $r['sakit'] }}</td>
                            <td class="text-center text-danger fw-bold py-3 px-4">{{ $r['alpha'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                Belum ada data presensi untuk kelas bimbingan Anda.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
