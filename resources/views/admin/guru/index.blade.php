@extends('admin.layouts.app')

@section('title', 'Data Guru - WebJournal Management System')

@section('content')
<div class="container-fluid px-0">

    <!-- Header Judul & Action -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-extrabold text-uppercase text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 800;">DATA GURU</h2>
            <p class="text-muted mb-0">Kelola informasi data guru pengajar dan pengampu mata pelajaran.</p>
        </div>
        <a href="{{ route('guru.create') }}" class="btn btn-primary rounded-3 px-3 py-2 fw-semibold d-flex align-items-center gap-2">
            <i class="bi bi-plus-circle-fill"></i>
            <span>Tambah Data Guru</span>
        </a>
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

    <!-- Card Data Tabel Guru -->
    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-nowrap">
                    <thead style="background-color: #fafafa; border-bottom: 2px solid #f1f5f9;">
                        <tr>
                            <th class="ps-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem; letter-spacing: 0.05em; width: 5%;">No</th>
                            <th class="text-uppercase text-muted fw-bold" style="font-size: 0.75rem; letter-spacing: 0.05em;">NIP</th>
                            <th class="text-uppercase text-muted fw-bold" style="font-size: 0.75rem; letter-spacing: 0.05em;">Nama Guru</th>
                            <th class="text-uppercase text-muted fw-bold" style="font-size: 0.75rem; letter-spacing: 0.05em;">No HP / Telepon</th>
                            <th class="pe-4 text-end text-uppercase text-muted fw-bold" style="font-size: 0.75rem; letter-spacing: 0.05em; width: 15%;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($dataGuru as $key => $guru)
                            <tr>
                                <td class="ps-4 fw-medium text-dark">{{ $key + 1 }}</td>
                                <td>
                                    <span class="badge bg-light text-dark border px-2 py-1 font-monospace">{{ $guru->nip ?? '-' }}</span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle bg-primary-subtle text-primary fw-bold d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; font-size: 0.85rem;">
                                            {{ strtoupper(substr($guru->nama_guru, 0, 1)) }}
                                        </div>
                                        <span class="fw-semibold text-dark">{{ $guru->nama_guru }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-secondary"><i class="bi bi-telephone me-1"></i> {{ $guru->no_hp ?? '-' }}</span>
                                </td>
                                <td class="pe-4 text-end">
                                    <a href="{{ route('guru.edit', $guru->id_guru) }}" class="btn btn-sm btn-light border rounded-3 me-1" title="Edit Data">
                                        <i class="bi bi-pencil-square text-warning"></i>
                                    </a>
                                    <form action="{{ route('guru.destroy', $guru->id_guru) }}" method="POST" class="d-inline" onsubmit="return confirm('Yakin hapus data guru ini?')">
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
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="bi bi-person-badge fs-1 d-block mb-2 text-secondary"></i>
                                    Data guru belum tersedia. Silakan tambahkan data baru.
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
