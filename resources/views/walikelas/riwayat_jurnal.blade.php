@extends('layouts.app')

@section('title', 'Riwayat Jurnal Kelas - Wali Kelas')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-black text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 800; font-size: 1.75rem;">
                📖 Riwayat Jurnal Kelas
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                Pantau seluruh catatan jurnal pengajaran guru yang masuk di kelas Anda.
            </p>
        </div>
    </div>

    <div class="table-card-custom mb-4">
        <div class="table-responsive">
            <table class="table table-custom align-middle">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Mata Pelajaran</th>
                        <th>Guru Pengajar</th>
                        <th>Materi Pelajaran</th>
                        <th>Kehadiran</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>15 Agu 2026</td>
                        <td><strong>Pemrograman Web</strong></td>
                        <td>Budi Santoso, S.Kom.</td>
                        <td>Membuat Layout Dashboard Laravel dengan Blade</td>
                        <td><span class="badge bg-success">32/34 Siswa</span></td>
                    </tr>
                    <tr>
                        <td>14 Agu 2026</td>
                        <td><strong>Matematika</strong></td>
                        <td>Siti Aminah, M.Pd.</td>
                        <td>Matriks dan Transformasi Geometri</td>
                        <td><span class="badge bg-success">34/34 Siswa</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
