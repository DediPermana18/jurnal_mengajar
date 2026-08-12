@extends('admin.layouts.app')

@section('title', 'Data Kelas - WebJournal Management System')

@section('content')
<div class="container-fluid px-0">

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-extrabold text-uppercase text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 800;">DATA KELAS</h2>
            <p class="text-muted mb-0">Kelola daftar kelas, jurusan, serta pembagian wali kelas.</p>
        </div>
        <a href="{{ route('kelas.create') }}" class="btn btn-primary rounded-3 px-3 py-2 fw-semibold d-flex align-items-center gap-2">
            <i class="bi bi-plus-circle-fill"></i>
            <span>Tambah Data Kelas</span>
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-check-circle-fill fs-5 me-2"></i>
                <div>{{ session('success') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-nowrap">
                    <thead style="background-color: #fafafa; border-bottom: 2px solid #f1f5f9;">
                        <tr>
                            <th class="ps-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem; letter-spacing: 0.05em; width: 5%;">No</th>
                            <th class="text-uppercase text-muted fw-bold" style="font-size: 0.75rem; letter-spacing: 0.05em;">Nama Kelas</th>
                            <th class="text-uppercase text-muted fw-bold" style="font-size: 0.75rem; letter-spacing: 0.05em;">Jurusan</th>
                            <th class="text-uppercase text-muted fw-bold" style="font-size: 0.75rem; letter-spacing: 0.05em;">Wali Kelas</th>
                            <th class="text-uppercase text-muted fw-bold" style="font-size: 0.75rem; letter-spacing: 0.05em;">Jumlah Siswa</th>
                            <th class="pe-4 text-end text-uppercase text-muted fw-bold" style="font-size: 0.75rem; letter-spacing: 0.05em; width: 15%;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($dataKelas as $key => $kelas)
                            <tr>
                                <td class="ps-4 fw-medium text-dark">{{ $key + 1 }}</td>
                                <td>
                                    <span class="fw-bold text-dark fs-6">{{ $kelas->nama_kelas }}</span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-1 rounded-pill fw-semibold">
                                        {{ $kelas->jurusan->nama_jurusan ?? '-' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-medium text-dark">
                                        <i class="bi bi-person-badge text-primary me-1"></i>
                                        {{ $kelas->waliKelas->nama_guru ?? '-' }}
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-info-subtle text-info border border-info-subtle px-3 py-1 rounded-3 fw-semibold">
                                        <i class="bi bi-people-fill me-1"></i> {{ $kelas->jumlah_siswa ?? 0 }} Siswa
                                    </span>
                                </td>
                                <td class="pe-4 text-end">
                                    <a href="{{ route('kelas.edit', $kelas->id_kelas) }}" class="btn btn-sm btn-light border rounded-3 me-1" title="Edit Data">
                                        <i class="bi bi-pencil-square text-warning"></i>
                                    </a>
                                    <form action="{{ route('kelas.destroy', $kelas->id_kelas) }}" method="POST" class="d-inline" onsubmit="return confirm('Yakin hapus data kelas ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-light border rounded-3" title="Hapus Data">
                                            <i class="bi bi-trash text-danger"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-door-open fs-1 d-block mb-2 text-secondary"></i>
                                    Belum ada data kelas. Silakan tambahkan data baru.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection
