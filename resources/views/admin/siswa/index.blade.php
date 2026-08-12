@extends('admin.layouts.app')

@section('title', 'Data Siswa - WebJournal Management System')

@section('content')
<div class="container-fluid px-0">

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-extrabold text-uppercase text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 800;">DATA SISWA</h2>
            <p class="text-muted mb-0">Kelola data seluruh siswa terpilih dan kelas terdaftar.</p>
        </div>
        <a href="{{ route('siswa.create') }}" class="btn btn-primary rounded-3 px-3 py-2 fw-semibold d-flex align-items-center gap-2">
            <i class="bi bi-plus-circle-fill"></i>
            <span>Tambah Data Siswa</span>
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
                            <th class="text-uppercase text-muted fw-bold" style="font-size: 0.75rem; letter-spacing: 0.05em;">NIS</th>
                            <th class="text-uppercase text-muted fw-bold" style="font-size: 0.75rem; letter-spacing: 0.05em;">Nama Siswa</th>
                            <th class="text-uppercase text-muted fw-bold" style="font-size: 0.75rem; letter-spacing: 0.05em;">Kelas</th>
                            <th class="text-uppercase text-muted fw-bold" style="font-size: 0.75rem; letter-spacing: 0.05em;">Jenis Kelamin</th>
                            <th class="pe-4 text-end text-uppercase text-muted fw-bold" style="font-size: 0.75rem; letter-spacing: 0.05em; width: 15%;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($dataSiswa as $key => $siswa)
                            <tr>
                                <td class="ps-4 fw-medium text-dark">{{ $key + 1 }}</td>
                                <td>
                                    <span class="badge bg-light text-dark border px-2 py-1 font-monospace">{{ $siswa->nis ?? '-' }}</span>
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark">{{ $siswa->nama_siswa }}</span>
                                </td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 rounded-pill fw-semibold">
                                        {{ $siswa->kelas->nama_kelas ?? '-' }}
                                    </span>
                                </td>
                                <td>
                                    @if($siswa->jenis_kelamin == 'L')
                                        <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1 rounded-2 fw-semibold">Laki-laki</span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 rounded-2 fw-semibold">Perempuan</span>
                                    @endif
                                </td>
                                <td class="pe-4 text-end">
                                    <a href="{{ route('siswa.edit', $siswa->id_siswa) }}" class="btn btn-sm btn-light border rounded-3 me-1" title="Edit Data">
                                        <i class="bi bi-pencil-square text-warning"></i>
                                    </a>
                                    <form action="{{ route('siswa.destroy', $siswa->id_siswa) }}" method="POST" class="d-inline" onsubmit="return confirm('Yakin hapus data siswa ini?')">
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
                                    <i class="bi bi-people fs-1 d-block mb-2 text-secondary"></i>
                                    Belum ada data siswa. Silakan tambahkan data baru.
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
