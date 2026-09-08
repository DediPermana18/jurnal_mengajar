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
            <div class="d-flex gap-2">
                <!-- Tombol Export Jurusan -->
                <div class="dropdown">
                    <button class="btn btn-outline-primary rounded-3 px-3 py-2 fw-semibold shadow-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-download me-1"></i> Export
                    </button>
                    <ul class="dropdown-menu shadow-sm border-0 rounded-3" style="z-index: 1050;">
                        <li>
                            <a href="{{ route('jurusan.export', ['format' => 'xlsx']) }}" class="dropdown-item py-2">
                                <i class="bi bi-file-earmark-excel me-2 text-success"></i> Export Excel (.xlsx)
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('jurusan.export', ['format' => 'csv']) }}" class="dropdown-item py-2">
                                <i class="bi bi-filetype-csv me-2 text-info"></i> Export CSV (.csv)
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Tombol Tambah Jurusan -->
                <a href="{{ route('jurusan.create') }}" class="btn btn-primary rounded-3 px-3 py-2 fw-semibold shadow-sm">
                    <i class="bi bi-plus-lg me-1"></i> Tambah Jurusan
                </a>
            </div>
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

    <div class="card border-0 shadow-sm rounded-4 p-3.5 bg-white mb-4">
        <form action="{{ route('jurusan.index') }}" method="GET">
        <div class="position-relative" style="max-width: 450px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted" style="font-size: 0.9rem;"></i>
                <input type="text"
                       name="search"
                       value="{{ request('search') }}"
                       class="form-control bg-light rounded-3 ps-5"
                       placeholder="Cari kode atau nama jurusan...">
            </div>
        </form>
    </div>

    <div class="table-card-custom mb-4">
        <div class="table-responsive w-full overflow-x-auto">
            <table class="table table-custom align-middle min-w-full">
                <thead>
                    <tr>
                        <th class="whitespace-nowrap" style="width: 10%;">NO</th>
                        <th class="whitespace-nowrap" style="width: 25%;">KODE JURUSAN</th>
                        <th class="whitespace-nowrap">NAMA JURUSAN</th>
                        <th class="text-end whitespace-nowrap" style="width: 20%;">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dataJurusan as $jurusan)
                        <tr>
                            <td class="whitespace-nowrap">{{ $loop->iteration }}</td>
                            <td class="whitespace-nowrap"><span class="badge bg-light text-dark border px-3 py-2 rounded-3 font-monospace">{{ $jurusan->kode_jurusan }}</span></td>
                            <td class="fw-semibold text-dark">{{ $jurusan->nama_jurusan }}</td>
                            <td class="text-end whitespace-nowrap">
                                @if(in_array(auth()->user()->role ?? '', ['admin_tu', 'admin', 'super_admin']))
                                    <div class="flex items-center justify-center gap-2 whitespace-nowrap">
                                    <a href="{{ route('jurusan.edit', $jurusan->id) }}" class="btn btn-sm btn-outline-warning rounded-3" title="Edit jurusan">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <form action="{{ route('jurusan.destroy', $jurusan->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus data jurusan ini? Data yang sudah dihapus tidak dapat dipulihkan.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-3" title="Hapus jurusan">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    </div>
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