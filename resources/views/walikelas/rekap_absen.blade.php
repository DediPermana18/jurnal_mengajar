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
            <h5 class="fw-bold text-dark mb-0">Rekapitulasi Bulanan Kelas XII RPL 1</h5>
            <button class="btn btn-sm btn-outline-primary rounded-3"><i class="bi bi-download me-1"></i> Export Excel/PDF</button>
        </div>
        <div class="table-responsive">
            <table class="table table-custom align-middle">
                <thead>
                    <tr>
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
                    <tr>
                        <td>10928371</td>
                        <td><strong>Ahmad Dahlan</strong></td>
                        <td class="text-center text-success fw-bold">22</td>
                        <td class="text-center">1</td>
                        <td class="text-center">0</td>
                        <td class="text-center text-danger fw-bold">0</td>
                        <td class="text-center"><span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">95.6%</span></td>
                    </tr>
                    <tr>
                        <td>10928372</td>
                        <td><strong>Budi Setiawan</strong></td>
                        <td class="text-center text-success fw-bold">18</td>
                        <td class="text-center">2</td>
                        <td class="text-center">1</td>
                        <td class="text-center text-danger fw-bold">2</td>
                        <td class="text-center"><span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill">78.2%</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
