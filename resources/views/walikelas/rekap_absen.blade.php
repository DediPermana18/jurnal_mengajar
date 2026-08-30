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
        <div class="table-responsive">
            <table class="table table-custom align-middle">
                <thead>
                    <tr>
                        <th class="text-center" style="width: 60px;">No</th>
                        <th>NIS/NISN</th>
                        <th>Nama Siswa</th>
                        <th class="text-center">Hadir</th>
                        <th class="text-center">Izin</th>
                        <th class="text-center">Sakit</th>
                        <th class="text-center text-danger">Alpha</th>
                        <th class="text-center">Persentase</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rekapAbsen as $r)
                        <tr>
                            <td class="text-center text-muted">{{ $loop->iteration }}</td>
                            <td>{{ $r['siswa']->nisn ?? $r['siswa']->nis ?? '-' }}</td>
                            <td><strong>{{ $r['siswa']->nama }}</strong></td>
                            <td class="text-center text-success fw-bold">{{ $r['hadir'] }}</td>
                            <td class="text-center">{{ $r['izin'] }}</td>
                            <td class="text-center">{{ $r['sakit'] }}</td>
                            <td class="text-center text-danger fw-bold">{{ $r['alpha'] }}</td>
                            <td class="text-center">
                                <span class="badge {{ $r['persentase'] >= 90 ? 'bg-success-subtle text-success' : ($r['persentase'] >= 75 ? 'bg-warning-subtle text-warning' : 'bg-danger-subtle text-danger') }} border rounded-pill">
                                    {{ number_format($r['persentase'], 1) }}%
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
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
