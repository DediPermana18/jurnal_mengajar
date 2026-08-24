@extends('layouts.app')

@section('title', 'Data Master Jurusan - WebJournal Management System')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 800; font-size: 1.75rem;">Data Master Jurusan</h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">Kelola kode dan nama jurusan yang digunakan pada data kelas.</p>
        </div>
        @if(in_array(auth()->user()->role ?? '', ['admin_tu', 'admin', 'super_admin']))
            <a href="{{ route('jurusan.create') }}" class="btn btn-primary rounded-3 px-3 py-2 fw-semibold shadow-sm">
                <i class="bi bi-plus-lg me-1"></i> Tambah Jurusan
            </a>
        @endif
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
            <strong class="d-block mb-1">Terjadi kesalahan:</strong>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="table-card-custom mb-4">
        <div class="table-responsive">
            <table class="table table-custom align-middle">
                <thead>
                    <tr>
                        <th style="width: 10%;">NO</th>
                        <th style="width: 25%;">KODE JURUSAN</th>
                        <th>NAMA JURUSAN</th>
                        <th class="text-end" style="width: 20%;">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dataJurusan as $jurusan)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td><span class="badge bg-light text-dark border px-3 py-2 rounded-3 font-monospace">{{ $jurusan->kode_jurusan }}</span></td>
                            <td class="fw-semibold text-dark">{{ $jurusan->nama_jurusan }}</td>
                            <td class="text-end">
                                @if(in_array(auth()->user()->role ?? '', ['admin_tu', 'admin', 'super_admin']))
                                    <a href="{{ route('jurusan.edit', $jurusan->id) }}" class="btn btn-sm btn-outline-warning rounded-3 me-1" title="Edit jurusan">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <form action="{{ route('jurusan.destroy', $jurusan->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus data jurusan ini? Data yang sudah dihapus tidak dapat dipulihkan.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-3" title="Hapus jurusan">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center py-5 text-muted"><i class="bi bi-diagram-3 fs-1 d-block mb-2"></i>Belum ada data jurusan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection