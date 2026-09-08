@extends('layouts.app')

@section('title', 'Data Master Tahun Ajaran - WebJournal Management System')

@section('content')
<div class="container-fluid px-0">

    {{-- Page Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 800; font-size: 1.75rem;">Data Master Tahun Ajaran</h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">Kelola Tahun Ajaran & Semester, set status aktif, dan integrasi dengan plotting jadwal.</p>
        </div>
        @if(in_array(auth()->user()->role ?? '', ['admin_tu', 'admin', 'super_admin']))
            <button type="button" class="btn btn-primary rounded-3 px-3 py-2 fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambahTahunAjaran">
                <i class="bi bi-plus-lg me-1"></i> Tambah Tahun Ajaran
            </button>
        @endif
    </div>

    {{-- Alert Messages --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
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
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Search Bar --}}
    <div class="card border-0 shadow-sm rounded-4 p-3.5 bg-white mb-4">
        <div class="position-relative" style="max-width: 450px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted" style="font-size: 0.9rem;"></i>
            <input type="text"
                   name="search"
                   class="form-control bg-light rounded-3 ps-5"
                   placeholder="Cari tahun ajaran atau semester...">
        </div>
    </div>

    {{-- Table --}}
    <div class="table-card-custom mb-4">
        <div class="table-responsive w-full overflow-x-auto">
            <table class="table table-custom align-middle min-w-full">
                <thead>
                    <tr>
                        <th class="whitespace-nowrap" style="width: 6%;">NO</th>
                        <th class="whitespace-nowrap" style="width: 26%;">TAHUN AJARAN</th>
                        <th class="whitespace-nowrap" style="width: 16%;">SEMESTER</th>
                        <th class="text-center whitespace-nowrap" style="width: 16%;">STATUS</th>
                        <th class="text-center whitespace-nowrap" style="width: 18%;">JUMLAH JADWAL</th>
                        <th class="text-center whitespace-nowrap" style="width: 18%;">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tahunAjaranList as $tahun)
                        <tr class="{{ $tahun->is_active ? '' : 'table-light' }}">
                            <td class="whitespace-nowrap">{{ $loop->iteration }}</td>
                            <td class="fw-semibold text-dark">
                                <span class="badge text-dark border rounded-3 px-3 py-2 font-monospace" style="background-color: #eef2ff;">
                                    {{ $tahun->tahun_ajaran }}
                                </span>
                            </td>
                            <td>
                                <span class="badge {{ $tahun->semester === 'Ganjil' ? 'bg-primary-subtle text-primary' : 'bg-info-subtle text-info-emphasis' }} border rounded-pill px-3 py-1">
                                    {{ $tahun->semester }}
                                </span>
                            </td>
                            <td class="text-center">
                                @if($tahun->is_active)
                                    <span class="badge bg-success rounded-pill px-3 py-1">Aktif</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary border rounded-pill px-3 py-1">Nonaktif</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border rounded-pill px-2 py-1">
                                    {{ $tahun->jadwal_pelajaran_count }} slot
                                </span>
                            </td>
                            <td class="text-center whitespace-nowrap">
                                @if(in_array(auth()->user()->role ?? '', ['admin_tu', 'admin', 'super_admin']))
                                    <div class="flex items-center justify-center gap-2 whitespace-nowrap">
                                        @if(!$tahun->is_active)
                                            <form action="{{ route('tahun-ajaran.set-aktif', $tahun->id) }}" method="POST" class="d-inline"
                                                  onsubmit="return confirm('Set Tahun Ajaran {{ $tahun->tahun_ajaran }} Semester {{ $tahun->semester }} menjadi AKTIF? Seluruh record lain akan dinonaktifkan otomatis.')">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-success rounded-3 px-2 py-1" title="Set Aktif">
                                                    <i class="bi bi-check2-circle me-1"></i>Set Aktif
                                                </button>
                                            </form>
                                        @endif
                                        <button type="button" class="btn btn-sm btn-warning text-white rounded-3 px-2 py-1" title="Edit tahun ajaran"
                                                data-bs-toggle="modal" data-bs-target="#modalEditTahunAjaran"
                                                onclick="openEditModal({{ $tahun->id }}, '{{ addslashes($tahun->tahun_ajaran) }}', '{{ $tahun->semester }}')">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <form action="{{ route('tahun-ajaran.destroy', $tahun->id) }}" method="POST" class="d-inline"
                                              onsubmit="return confirm('Hapus data Tahun Ajaran {{ $tahun->tahun_ajaran }} Semester {{ $tahun->semester }}? Tahun Ajaran yang sedang Aktif atau sudah memiliki jadwal pelajaran tidak dapat dihapus.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-3 px-2 py-1" title="Hapus tahun ajaran">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
                                Belum ada data Tahun Ajaran.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ==================== MODAL TAMBAH TAHUN AJARAN ==================== --}}
@if(in_array(auth()->user()->role ?? '', ['admin_tu', 'admin', 'super_admin']))
<div class="modal fade" id="modalTambahTahunAjaran" tabindex="-1" aria-labelledby="modalTambahTahunAjaranLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <form action="{{ route('tahun-ajaran.store') }}" method="POST">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="modalTambahTahunAjaranLabel">
                        <i class="bi bi-calendar-plus text-primary me-2"></i>Tambah Tahun Ajaran Baru
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="tahun_ajaran_tambah" class="form-label fw-semibold">Tahun Ajaran <span class="text-danger">*</span></label>
                            <input type="text" name="tahun_ajaran" id="tahun_ajaran_tambah"
                                   class="form-control rounded-3 @error('tahun_ajaran') is-invalid @enderror"
                                   value="{{ old('tahun_ajaran') }}" placeholder="contoh: 2025/2026" maxlength="20" required>
                            @error('tahun_ajaran') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label for="semester_tambah" class="form-label fw-semibold">Semester <span class="text-danger">*</span></label>
                            <select name="semester" id="semester_tambah" class="form-select rounded-3 @error('semester') is-invalid @enderror" required>
                                <option value="">-- Pilih Semester --</option>
                                <option value="Ganjil" {{ old('semester') === 'Ganjil' ? 'selected' : '' }}>Ganjil</option>
                                <option value="Genap" {{ old('semester') === 'Genap' ? 'selected' : '' }}>Genap</option>
                            </select>
                            @error('semester') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <div class="alert alert-info border-0 rounded-3 py-2 px-3 mb-0 d-flex align-items-center gap-2" style="font-size: 0.8rem;">
                                <i class="bi bi-info-circle-fill text-info flex-shrink-0"></i>
                                <span>Tahun Ajaran yang baru ditambahkan berstatus <strong>Nonaktif</strong>. Gunakan tombol <strong>Set Aktif</strong> pada tabel untuk mengaktifkannya.</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-3 fw-semibold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-3 fw-semibold px-4">
                        <i class="bi bi-check-lg me-1"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ==================== MODAL EDIT TAHUN AJARAN ==================== --}}
<div class="modal fade" id="modalEditTahunAjaran" tabindex="-1" aria-labelledby="modalEditTahunAjaranLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <form action="" method="POST" id="formEditTahunAjaran">
                @csrf
                @method('PUT')
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="modalEditTahunAjaranLabel">
                        <i class="bi bi-pencil-square text-warning me-2"></i>Edit Tahun Ajaran
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="tahun_ajaran_edit" class="form-label fw-semibold">Tahun Ajaran <span class="text-danger">*</span></label>
                            <input type="text" name="tahun_ajaran" id="tahun_ajaran_edit"
                                   class="form-control rounded-3 @error('tahun_ajaran') is-invalid @enderror"
                                   placeholder="contoh: 2025/2026" maxlength="20" required>
                            @error('tahun_ajaran') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label for="semester_edit" class="form-label fw-semibold">Semester <span class="text-danger">*</span></label>
                            <select name="semester" id="semester_edit" class="form-select rounded-3 @error('semester') is-invalid @enderror" required>
                                <option value="Ganjil">Ganjil</option>
                                <option value="Genap">Genap</option>
                            </select>
                            @error('semester') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-3 fw-semibold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning rounded-3 fw-semibold px-4">
                        <i class="bi bi-check-lg me-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@endsection

@push('scripts')
<script>
    function openEditModal(id, tahunAjaran, semester) {
        const form = document.getElementById('formEditTahunAjaran');
        form.action = '/admin/tahun-ajaran/' + id;

        document.getElementById('tahun_ajaran_edit').value = tahunAjaran;
        document.getElementById('semester_edit').value = semester;
    }
</script>
@endpush