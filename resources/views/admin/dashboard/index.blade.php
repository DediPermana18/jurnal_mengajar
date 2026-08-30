@extends('layouts.app')

@section('title', 'Dashboard - WebJournal Management System')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 900; font-size: 1.75rem;">Dashboard</h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">Ringkasan data master dan akun terbaru.</p>
        </div>
        <span class="text-muted small"><i class="bi bi-calendar3 me-1"></i>{{ now()->translatedFormat('l, d F Y') }}</span>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card-custom h-100">
                <div class="stat-card-title">Total Guru Terdaftar</div>
                <div class="stat-number-large text-primary">{{ number_format($totalGuru) }}</div>
                <div class="stat-card-label">Akun guru</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card-custom h-100">
                <div class="stat-card-title">Total Siswa Terdaftar</div>
                <div class="stat-number-large text-success">{{ number_format($totalSiswa) }}</div>
                <div class="stat-card-label">Data siswa</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card-custom h-100">
                <div class="stat-card-title">Total Kelas</div>
                <div class="stat-number-large text-info">{{ number_format($totalKelas) }}</div>
                <div class="stat-card-label">Rombongan belajar</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card-custom h-100">
                <div class="stat-card-title">Akun Tidak Aktif</div>
                <div class="stat-number-large text-secondary">{{ number_format($akunTidakAktif) }}</div>
                <div class="stat-card-label">Akun yang dinonaktifkan</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xl-8">
            <div class="table-card-custom h-100">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold text-dark mb-0">User / Guru Baru Ditambahkan</h5>
                    <a href="{{ route('admin.users.index') }}" class="small text-decoration-none">Lihat Kelola User <i class="bi bi-arrow-right ms-1"></i></a>
                </div>
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th>NAMA</th>
                                <th>USERNAME</th>
                                <th>ROLE</th>
                                <th>STATUS AKTIVASI</th>
                                <th>TANGGAL DIBUAT</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($userTerbaru as $user)
                                @php
                                    $statusAktivasi = $user->is_active ? 'Aktif' : 'Nonaktif';
                                    $statusClass = $user->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary';
                                @endphp
                                <tr>
                                    <td class="fw-semibold text-dark">{{ $user->nama }}</td>
                                    <td class="text-muted">{{ $user->username }}</td>
                                    <td><span class="badge bg-light text-dark border rounded-3">{{ $user->role_label }}</span></td>
                                    <td><span class="badge {{ $statusClass }} rounded-pill px-2 py-2">{{ $statusAktivasi }}</span></td>
                                    <td class="text-muted small">{{ $user->created_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center py-5 text-muted">Belum ada user yang ditambahkan.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                <h5 class="fw-bold text-dark mb-1">Akses Cepat</h5>
                <p class="text-muted small mb-4">Buka halaman pengelolaan data yang sering digunakan.</p>
                <div class="d-grid gap-3">
                    <a href="{{ route('admin.guru.create') }}" class="btn btn-primary rounded-3 py-2 fw-semibold text-start"><i class="bi bi-person-plus-fill me-2"></i> Tambah Guru</a>
                    <a href="{{ route('siswa.create') }}" class="btn btn-outline-success rounded-3 py-2 fw-semibold text-start"><i class="bi bi-person-plus me-2"></i> Tambah Siswa</a>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary rounded-3 py-2 fw-semibold text-start"><i class="bi bi-person-gear me-2"></i> Kelola User</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
