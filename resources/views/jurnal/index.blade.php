<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jurnal Mengajar Digital - Halaman Utama</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">

<!-- Navbar Utama -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="{{ route('home') }}">
            <i class="bi bi-journal-check"></i> Jurnal Mengajar Digital
        </a>
    </div>
</nav>

<div class="container mb-5">
    
    <!-- Bagian Tombol Navigasi Menu Cepat (Quick Links) -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted fw-bold mb-3"><i class="bi bi-grid-fill"></i> NAVIGASI DATA MASTER</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('guru.index') }}" class="btn btn-outline-primary">
                            <i class="bi bi-person-badge"></i> Data Guru
                        </a>
                        <a href="{{ route('mapel.index') }}" class="btn btn-outline-success">
                            <i class="bi bi-book"></i> Data Mapel
                        </a>
                        <a href="{{ route('kelas.index') }}" class="btn btn-outline-warning text-dark">
                            <i class="bi bi-door-open"></i> Data Kelas
                        </a>
                        <a href="{{ route('siswa.index') }}" class="btn btn-outline-info text-dark">
                            <i class="bi bi-people"></i> Data Siswa
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Notifikasi -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Tabel Data Jurnal Mengajar -->
    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <h5 class="mb-0 fw-bold text-primary"><i class="bi bi-journal-text"></i> Daftar Jurnal Mengajar</h5>
            <a href="{{ route('jurnal.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Tambah Jurnal
            </a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th width="5%">No</th>
                            <th>Tanggal</th>
                            <th>Nama Guru</th>
                            <th>Mata Pelajaran</th>
                            <th>Kelas</th>
                            <th>Materi Pembelajaran</th>
                            <th>Keterangan</th>
                            <th width="15%" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($dataJurnal as $index => $jurnal)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ \Carbon\Carbon::parse($jurnal->tanggal)->format('d/m/Y') }}</td>
                                <td>{{ $jurnal->guru->nama_guru ?? '-' }}</td>
                                <td>{{ $jurnal->mapel->nama_mapel ?? '-' }}</td>
                                <td><span class="badge bg-secondary">{{ $jurnal->kelas->nama_kelas ?? '-' }}</span></td>
                                <td>{{ $jurnal->materi }}</td>
                                <td>{{ $jurnal->keterangan ?? '-' }}</td>
                                <td class="text-center">
                                    <a href="{{ route('jurnal.edit', $jurnal->id_jurnal ?? $jurnal->id) }}" class="btn btn-sm btn-warning">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <form action="{{ route('jurnal.destroy', $jurnal->id_jurnal ?? $jurnal->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Yakin hapus jurnal ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">Belum ada data jurnal mengajar. Silakan tambah data baru.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>