@extends('admin.layouts.app')

@section('title', 'Data Master Kelas - WebJournal Management System')

@section('content')
<div class="container-fluid px-0">

    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="font-weight: 800; font-size: 1.85rem; letter-spacing: -0.02em;">Data Master Kelas</h2>
            <p class="text-muted mb-0" style="font-size: 0.925rem; font-weight: 500;">Kelola rombongan belajar, wali kelas, dan ruangan.</p>
        </div>
        <a href="{{ route('kelas.create') }}" class="btn btn-primary rounded-3 px-3 py-2 fw-semibold d-flex align-items-center gap-2" style="background-color: #1565c0; border: none;">
            <i class="bi bi-plus"></i>
            <span>+ Tambah Kelas</span>
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

    <div class="table-card-custom">

        <!-- Filter Bar -->
        <div class="row g-3 align-items-center mb-4">
            <div class="col-12 col-md-5">
                <div class="position-relative">
                    <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                    <input type="text" class="form-control rounded-3 ps-5 bg-light border-0 py-2" placeholder="Cari nama kelas atau ruangan...">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select class="form-select rounded-3 bg-light border-0 py-2 small text-secondary fw-medium">
                    <option value="">Semua Tingkat</option>
                    <option value="X">Kelas X</option>
                    <option value="XI">Kelas XI</option>
                    <option value="XII">Kelas XII</option>
                </select>
            </div>
            <div class="col-6 col-md-4">
                <select class="form-select rounded-3 bg-light border-0 py-2 small text-secondary fw-medium">
                    <option value="">Semua Jurusan</option>
                    @foreach(App\Models\Jurusan::all() as $j)
                        <option value="{{ $j->id_jurusan }}">{{ $j->nama_jurusan }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <!-- Tabel -->
        <div class="table-responsive">
            <table class="table table-custom align-middle">
                <thead>
                    <tr>
                        <th style="width: 18%;">NAMA KELAS</th>
                        <th style="width: 27%;">JURUSAN</th>
                        <th style="width: 22%;">WALI KELAS</th>
                        <th style="width: 12%;">JML SISWA</th>
                        <th style="width: 12%;">RUANGAN</th>
                        <th style="width: 9%; text-align: right;">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dataKelas as $kelas)
                        <tr>
                            <td>
                                <span class="badge fw-semibold px-3 py-1 rounded-2" style="background:#dbeafe; color:#1d4ed8; font-size: 0.85rem; letter-spacing: 0.02em;">
                                    {{ $kelas->nama_kelas }}
                                </span>
                            </td>
                            <td class="fw-medium text-dark">{{ $kelas->jurusan->nama_jurusan ?? '-' }}</td>
                            <td class="fw-semibold text-dark">{{ $kelas->waliKelas->nama_guru ?? '-' }}</td>
                            <td>
                                <span class="fw-semibold text-dark">{{ $kelas->jumlah_siswa ?? 0 }}</span>
                                <span class="text-muted small"> Siswa</span>
                            </td>
                            <td>
                                @if($kelas->ruangan ?? null)
                                    <span class="badge bg-light border px-2 py-1 rounded-2 fw-medium text-dark">
                                        {{ $kelas->ruangan }}
                                    </span>
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            </td>
                            <td class="text-end">
                                {{-- 👁️ Ikon Mata → Detail Kelas (Daftar Siswa) --}}
                                <a href="{{ route('kelas.show', $kelas->id_kelas) }}" class="text-secondary me-2 fs-6" title="Lihat Detail Kelas & Siswa">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('kelas.edit', $kelas->id_kelas) }}" class="text-secondary me-2 fs-6" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="{{ route('kelas.destroy', $kelas->id_kelas) }}" method="POST" class="d-inline" onsubmit="return confirm('Yakin hapus data kelas ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-link p-0 text-secondary border-0 fs-6" title="Hapus"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <!-- Demo Rows sesuai foto -->
                        <tr>
                            <td><span class="badge fw-semibold px-3 py-1 rounded-2" style="background:#dbeafe; color:#1d4ed8;">XII RPL 1</span></td>
                            <td class="fw-medium text-dark">Rekayasa Perangkat Lunak</td>
                            <td class="fw-semibold text-dark">Budi Santoso, S.Kom.</td>
                            <td><span class="fw-semibold text-dark">36</span><span class="text-muted small"> Siswa</span></td>
                            <td><span class="badge bg-light border px-2 py-1 rounded-2 fw-medium text-dark">Lab RPL 1</span></td>
                            <td class="text-end">
                                <a href="#" class="text-secondary me-2 fs-6" title="Lihat Detail"><i class="bi bi-eye"></i></a>
                                <a href="#" class="text-secondary me-2 fs-6" title="Edit"><i class="bi bi-pencil"></i></a>
                                <a href="#" class="text-secondary fs-6" title="Hapus"><i class="bi bi-trash"></i></a>
                            </td>
                        </tr>
                        <tr>
                            <td><span class="badge fw-semibold px-3 py-1 rounded-2" style="background:#fce7f3; color:#be185d;">XI TKJ 2</span></td>
                            <td class="fw-medium text-dark">Teknik Komputer & Jaringan</td>
                            <td class="fw-semibold text-dark">Siti Aminah, M.Pd.</td>
                            <td><span class="fw-semibold text-dark">32</span><span class="text-muted small"> Siswa</span></td>
                            <td><span class="badge bg-light border px-2 py-1 rounded-2 fw-medium text-dark">Lab Komputer 2</span></td>
                            <td class="text-end">
                                <a href="#" class="text-secondary me-2 fs-6" title="Lihat Detail"><i class="bi bi-eye"></i></a>
                                <a href="#" class="text-secondary me-2 fs-6" title="Edit"><i class="bi bi-pencil"></i></a>
                                <a href="#" class="text-secondary fs-6" title="Hapus"><i class="bi bi-trash"></i></a>
                            </td>
                        </tr>
                        <tr>
                            <td><span class="badge fw-semibold px-3 py-1 rounded-2" style="background:#dcfce7; color:#166534;">X AKL 1</span></td>
                            <td class="fw-medium text-dark">Akuntansi Keuangan Lembaga</td>
                            <td class="fw-semibold text-dark">Dwi Wahyuni, S.E.</td>
                            <td><span class="fw-semibold text-dark">34</span><span class="text-muted small"> Siswa</span></td>
                            <td><span class="badge bg-light border px-2 py-1 rounded-2 fw-medium text-dark">R.105</span></td>
                            <td class="text-end">
                                <a href="#" class="text-secondary me-2 fs-6" title="Lihat Detail"><i class="bi bi-eye"></i></a>
                                <a href="#" class="text-secondary me-2 fs-6" title="Edit"><i class="bi bi-pencil"></i></a>
                                <a href="#" class="text-secondary fs-6" title="Hapus"><i class="bi bi-trash"></i></a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Footer Pagination -->
        <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
            <div class="text-muted small">Menampilkan 1-3 dari 24 kelas</div>
            <div class="d-flex align-items-center gap-1">
                <button class="btn btn-sm btn-light border px-2 py-1" style="border-radius: 6px;" disabled><i class="bi bi-chevron-left small"></i></button>
                <button class="btn btn-sm btn-primary px-2 py-1" style="border-radius: 6px; background:#1565c0; border:none;">1</button>
                <button class="btn btn-sm btn-light border px-2 py-1" style="border-radius: 6px;">2</button>
                <button class="btn btn-sm btn-light border px-2 py-1" style="border-radius: 6px;">3</button>
                <span class="text-muted small px-1">...</span>
                <button class="btn btn-sm btn-light border px-2 py-1" style="border-radius: 6px;"><i class="bi bi-chevron-right small"></i></button>
            </div>
        </div>

    </div>

</div>
@endsection
