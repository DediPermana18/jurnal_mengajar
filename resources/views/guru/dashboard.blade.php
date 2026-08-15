@extends('layouts.app')

@section('title', 'Dashboard Guru - WebJournal')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-black text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 800; font-size: 1.75rem;">
                Dashboard Guru
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                Selamat datang kembali, <strong>{{ auth()->user()->nama ?? 'Guru Pengajar' }}</strong>! Siap untuk mengajar hari ini?
            </p>
        </div>
        <div>
            <a href="{{ route('guru.jurnal') }}" class="btn btn-primary rounded-3 px-3 py-2 fw-semibold shadow-sm">
                <i class="bi bi-pencil-square me-1"></i> Isi Jurnal Hari Ini
            </a>
        </div>
    </div>

    <!-- Stat Cards Summary -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card-custom">
                <div class="stat-card-title text-uppercase">Jadwal Mengajar Hari Ini</div>
                <div class="stat-number-large text-primary">4</div>
                <div class="stat-card-label">Jam Pelajaran</div>
                <p class="stat-card-subtext">Kelas X RPL 1 & XI TKJ 2</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card-custom">
                <div class="stat-card-title text-uppercase">Status Jurnal</div>
                <div class="stat-number-large text-success">2/4</div>
                <div class="stat-card-label">Jurnal Terisi</div>
                <p class="stat-card-subtext">2 sesi menanti pengisian</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card-custom">
                <div class="stat-card-title text-uppercase">Total Jam Minggu Ini</div>
                <div class="stat-number-large text-dark">24</div>
                <div class="stat-card-label">Jam Tatap Muka</div>
                <p class="stat-card-subtext">Sesuai beban kerja guru</p>
            </div>
        </div>
    </div>

    <!-- Quick Info Card -->
    <div class="table-card-custom">
        <h5 class="fw-bold text-dark mb-3">Jadwal Mengajar Hari Ini</h5>
        <div class="table-responsive">
            <table class="table table-custom align-middle">
                <thead>
                    <tr>
                        <th>Jam ke-</th>
                        <th>Waktu</th>
                        <th>Kelas</th>
                        <th>Mata Pelajaran</th>
                        <th>Status Jurnal</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>1 - 2</strong></td>
                        <td>07:30 - 09:00</td>
                        <td><span class="badge bg-light text-dark border">XII RPL 1</span></td>
                        <td>Pemrograman Web</td>
                        <td><span class="status-badge-terisi"><i class="bi bi-check-circle-fill"></i> Sudah Terisi</span></td>
                        <td class="text-end"><a href="{{ route('guru.jurnal') }}" class="btn btn-sm btn-outline-secondary rounded-2">Edit Jurnal</a></td>
                    </tr>
                    <tr>
                        <td><strong>3 - 4</strong></td>
                        <td>09:15 - 10:45</td>
                        <td><span class="badge bg-light text-dark border">XI TKJ 2</span></td>
                        <td>Administrasi Server</td>
                        <td><span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-3 py-1"><i class="bi bi-clock-history me-1"></i> Belum Terisi</span></td>
                        <td class="text-end"><a href="{{ route('guru.jurnal') }}" class="btn btn-sm btn-primary rounded-2">Isi Jurnal</a></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
