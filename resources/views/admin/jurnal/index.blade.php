@extends('admin.layouts.app')

@section('title', 'Dashboard - WebJournal Management System')

@section('content')
<div class="container-fluid px-0">

    <!-- Header Judul Dashboard -->
    <div class="mb-4">
        <h2 class="fw-black text-uppercase text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 900; font-size: 2rem;">DASHBOARD</h2>
        <p class="text-muted mb-0" style="font-size: 0.95rem; font-weight: 500;">Ringkasan aktivitas dan pengisian jurnal hari ini.</p>
    </div>

    <!-- Alert Notifikasi Flash -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-check-circle-fill fs-5 me-2"></i>
                <div>{{ session('success') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- 3 Stat Cards Grid (Tepat Sesuai Foto) -->
    <div class="row g-4 mb-4">
        <!-- Card 1: Total Jurnal Terisi -->
        <div class="col-12 col-md-4">
            <div class="stat-card-custom">
                <div class="stat-card-title">Total jurnal terisi</div>
                <div class="stat-number-large text-success">
                    {{ count($dataJurnal) > 0 ? count($dataJurnal) : '20' }}
                </div>
                <div class="stat-card-label">Kelas</div>
                <p class="stat-card-subtext">5 dari kelas 10, 15 dari kelas 11</p>
            </div>
        </div>

        <!-- Card 2: Siswa Tidak Hadir -->
        <div class="col-12 col-md-4">
            <div class="stat-card-custom">
                <div class="stat-card-title">Siswa Tidak Hadir</div>
                <div class="stat-number-large" style="color: #0284c7;">15</div>
                <div class="stat-card-label">Siswa Tidak Hadir</div>
                <p class="stat-card-subtext">8 Sakit, 5 Izin, 2 Alpha</p>
            </div>
        </div>

        <!-- Card 3: Guru Tidak Hadir -->
        <div class="col-12 col-md-4">
            <div class="stat-card-custom">
                <div class="stat-card-title">Guru Tidak Hadir</div>
                <div class="stat-number-large text-danger">2</div>
                <div class="stat-card-label">Guru Tidak Hadir</div>
                <p class="stat-card-subtext">1 Sakit, 1 Dinas</p>
            </div>
        </div>
    </div>

    <!-- Riwayat Jurnal Terbaru Section Card (Tepat Sesuai Foto) -->
    <div class="table-card-custom">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="fw-bold text-dark mb-0" style="font-size: 1.15rem; font-weight: 800;">Riwayat Jurnal Terbaru</h5>
            <div class="dropdown">
                <button class="btn btn-sm btn-light border px-2 py-1" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="border-color: #cbd5e1 !important; border-radius: 8px;">
                    <i class="bi bi-chevron-down text-muted" style="font-size: 0.75rem;"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3">
                    <li><a class="dropdown-item small" href="#">Filter Semua</a></li>
                    <li><a class="dropdown-item small" href="#">Hari Ini</a></li>
                    <li><a class="dropdown-item small" href="#">Minggu Ini</a></li>
                </ul>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-custom align-middle">
                <thead>
                    <tr>
                        <th style="width: 15%;">WAKTU</th>
                        <th style="width: 15%;">KELAS</th>
                        <th style="width: 20%;">GURU PENGAJAR</th>
                        <th style="width: 20%;">MATA PELAJARAN</th>
                        <th style="width: 20%;">MATERI PEMBELAJARAN</th>
                        <th style="width: 10%; text-align: right;">STATUS</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dataJurnal as $jurnal)
                        <tr>
                            <td class="fw-medium text-dark">
                                {{ \Carbon\Carbon::parse($jurnal->tanggal)->format('H:i') != '00:00' ? \Carbon\Carbon::parse($jurnal->tanggal)->format('H:i') . ' WIB' : \Carbon\Carbon::parse($jurnal->tanggal)->format('d/m/Y') }}
                            </td>
                            <td>
                                <span class="fw-bold text-dark">{{ $jurnal->kelas->nama_kelas ?? '-' }}</span>
                            </td>
                            <td>
                                <div class="fw-medium text-dark">{{ $jurnal->guru->nama_guru ?? '-' }}</div>
                            </td>
                            <td>
                                <span class="text-secondary">{{ $jurnal->mapel->nama_mapel ?? '-' }}</span>
                            </td>
                            <td>
                                <div class="text-dark fw-medium">{{ $jurnal->materi }}</div>
                            </td>
                            <td class="text-end">
                                <span class="status-badge-terisi">
                                    <i class="bi bi-check-circle-fill" style="font-size: 0.75rem;"></i> Terisi
                                </span>
                            </td>
                        </tr>
                    @empty
                        <!-- Row 1 -->
                        <tr>
                            <td class="fw-medium text-dark">10:00 WIB</td>
                            <td><span class="fw-bold text-dark">XII RPL 1</span></td>
                            <td><div class="fw-medium text-dark">Budi Santoso</div></td>
                            <td><span class="text-secondary">Pemrograman Web</span></td>
                            <td><div class="text-dark fw-medium">Routing Laravel</div></td>
                            <td class="text-end">
                                <span class="status-badge-terisi">
                                    <i class="bi bi-check-circle-fill" style="font-size: 0.75rem;"></i> Terisi
                                </span>
                            </td>
                        </tr>
                        <!-- Row 2 -->
                        <tr>
                            <td class="fw-medium text-dark">09:30 WIB</td>
                            <td><span class="fw-bold text-dark">XI TKJ 2</span></td>
                            <td><div class="fw-medium text-dark">Ahmad Subarjo</div></td>
                            <td><span class="text-secondary">Jaringan Dasar</span></td>
                            <td><div class="text-dark fw-medium">Konfigurasi Router Mikrotik</div></td>
                            <td class="text-end">
                                <span class="status-badge-terisi">
                                    <i class="bi bi-check-circle-fill" style="font-size: 0.75rem;"></i> Terisi
                                </span>
                            </td>
                        </tr>
                        <!-- Row 3 -->
                        <tr>
                            <td class="fw-medium text-dark">09:15 WIB</td>
                            <td><span class="fw-bold text-dark">XI AKL 1</span></td>
                            <td><div class="fw-medium text-dark">Siti Aminah</div></td>
                            <td><span class="text-secondary">Akuntansi</span></td>
                            <td><div class="text-dark fw-medium">Laporan Keuangan Perusahaan</div></td>
                            <td class="text-end">
                                <span class="status-badge-terisi">
                                    <i class="bi bi-check-circle-fill" style="font-size: 0.75rem;"></i> Terisi
                                </span>
                            </td>
                        </tr>
                        <!-- Row 4 -->
                        <tr>
                            <td class="fw-medium text-dark">08:45 WIB</td>
                            <td><span class="fw-bold text-dark">X TKR 2</span></td>
                            <td><div class="fw-medium text-dark">Bambang Wijaya</div></td>
                            <td><span class="text-secondary">Pemeliharaan Mesin</span></td>
                            <td><div class="text-dark fw-medium">Perbaikan Sistem Injeksi</div></td>
                            <td class="text-end">
                                <span class="status-badge-terisi">
                                    <i class="bi bi-check-circle-fill" style="font-size: 0.75rem;"></i> Terisi
                                </span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
