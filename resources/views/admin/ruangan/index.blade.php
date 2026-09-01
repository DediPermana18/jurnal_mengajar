@extends('layouts.app')

@section('title', 'Data Master Ruangan - WebJournal Management System')

@section('content')
<div class="container-fluid px-0">

    {{-- Page Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 800; font-size: 1.75rem;">Data Master Ruangan</h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">Kelola data ruangan sekolah beserta pengurus dan penugasan kelas.</p>
        </div>
        @if(in_array(auth()->user()->role ?? '', ['admin_tu', 'admin', 'super_admin']))
            <button type="button" class="btn btn-primary rounded-3 px-3 py-2 fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambahRuangan">
                <i class="bi bi-plus-lg me-1"></i> Tambah Ruangan
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

    {{-- Table --}}
    <div class="table-card-custom mb-4">
        <div class="table-responsive w-full overflow-x-auto">
            <table class="table table-custom align-middle min-w-full">
                <thead>
                    <tr>
                        <th class="whitespace-nowrap" style="width: 5%;">NO</th>
                        <th class="whitespace-nowrap" style="width: 12%;">KODE</th>
                        <th class="whitespace-nowrap" style="width: 20%;">NAMA RUANGAN</th>
                        <th style="width: 20%;">LOKASI / GEDUNG</th>
                        <th style="width: 18%;">KELAS / JADWAL</th>
                        <th style="width: 18%;">PENGURUS</th>
                        <th class="text-center whitespace-nowrap" style="width: 10%;">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dataRuangan as $ruangan)
                        <tr>
                            <td class="whitespace-nowrap">{{ $loop->iteration }}</td>
                            <td class="whitespace-nowrap">
                                <span class="badge bg-light text-dark border px-3 py-2 rounded-3 font-monospace">{{ $ruangan->kode_ruangan }}</span>
                            </td>
                            <td class="fw-semibold text-dark">{{ $ruangan->nama_ruangan }}</td>
                            <td class="text-muted">{{ $ruangan->lokasi ?? '-' }}</td>
                            <td>
                                @php
                                    $kelasDipakai = $ruangan->jadwalPelajaran
                                        ->map(fn($jp) => $jp->kelas)
                                        ->filter()
                                        ->unique('id');
                                @endphp
                                @if($kelasDipakai->isEmpty())
                                    <span class="text-muted small">-</span>
                                @else
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach($kelasDipakai as $kelas)
                                            <span class="badge bg-light text-dark border rounded-pill px-2 py-1" style="font-size: 0.75rem;">
                                                {{ $kelas->nama_kelas }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if($ruangan->pengurus->isEmpty())
                                    <span class="text-muted small">-</span>
                                @else
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach($ruangan->pengurus as $pengurus)
                                            <span class="badge bg-primary-subtle text-primary border rounded-pill px-2 py-1" style="font-size: 0.75rem;">
                                                {{ $pengurus->nama }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="whitespace-nowrap">
                                @if(in_array(auth()->user()->role ?? '', ['admin_tu', 'admin', 'super_admin']))
                                    <div class="flex items-center justify-center gap-2 whitespace-nowrap">
                                        <button type="button" class="btn btn-sm btn-warning text-white rounded-3 px-2 py-1" title="Edit ruangan"
                                                data-bs-toggle="modal" data-bs-target="#modalEditRuangan"
                                                onclick="openEditModal({{ $ruangan->id }}, '{{ addslashes($ruangan->kode_ruangan) }}', '{{ addslashes($ruangan->nama_ruangan) }}', '{{ addslashes($ruangan->lokasi) }}', {{ $ruangan->pengurus->pluck('id') }})">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <form action="{{ route('ruangan.destroy', $ruangan->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus data ruangan ini? Data yang sudah dihapus tidak dapat dipulihkan.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-3 px-2 py-1" title="Hapus ruangan">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-building fs-1 d-block mb-2"></i>
                                Belum ada data ruangan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ==================== MODAL TAMBAH RUANGAN ==================== --}}
@if(in_array(auth()->user()->role ?? '', ['admin_tu', 'admin', 'super_admin']))
<div class="modal fade" id="modalTambahRuangan" tabindex="-1" aria-labelledby="modalTambahRuanganLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <form action="{{ route('ruangan.store') }}" method="POST">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="modalTambahRuanganLabel">
                        <i class="bi bi-building-add text-primary me-2"></i>Tambah Ruangan Baru
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="kode_ruangan" class="form-label fw-semibold">Kode Ruangan <span class="text-danger">*</span></label>
                            <input type="text" name="kode_ruangan" id="kode_ruangan"
                                   class="form-control rounded-3 @error('kode_ruangan') is-invalid @enderror"
                                   value="{{ old('kode_ruangan') }}" placeholder="contoh: R-101" required>
                            @error('kode_ruangan') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-8">
                            <label for="nama_ruangan_tambah" class="form-label fw-semibold">Nama Ruangan <span class="text-danger">*</span></label>
                            <input type="text" name="nama_ruangan" id="nama_ruangan_tambah"
                                   class="form-control rounded-3 @error('nama_ruangan') is-invalid @enderror"
                                   value="{{ old('nama_ruangan') }}" placeholder="contoh: Kelas 101" required>
                            @error('nama_ruangan') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label for="lokasi_tambah" class="form-label fw-semibold">Lokasi / Gedung</label>
                            <input type="text" name="lokasi" id="lokasi_tambah"
                                   class="form-control rounded-3 @error('lokasi') is-invalid @enderror"
                                   value="{{ old('lokasi') }}" placeholder="contoh: Gedung A Lantai 1">
                            @error('lokasi') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label for="pengurus_tambah" class="form-label fw-semibold">Pengurus Ruangan</label>
                            <select name="pengurus[]" id="pengurus_tambah"
                                    class="form-select rounded-3 @error('pengurus') is-invalid @enderror"
                                    multiple size="5">
                                @foreach($guruList as $guru)
                                    <option value="{{ $guru->id }}" {{ in_array($guru->id, old('pengurus', [])) ? 'selected' : '' }}>
                                        {{ $guru->nama }} — {{ $guru->role_label }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">Tahan <kbd>Ctrl</kbd> (Windows) / <kbd>Cmd</kbd> (Mac) untuk memilih lebih dari satu.</div>
                            @error('pengurus') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
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

{{-- ==================== MODAL EDIT RUANGAN ==================== --}}
<div class="modal fade" id="modalEditRuangan" tabindex="-1" aria-labelledby="modalEditRuanganLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <form action="" method="POST" id="formEditRuangan">
                @csrf
                @method('PUT')
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="modalEditRuanganLabel">
                        <i class="bi bi-pencil-square text-warning me-2"></i>Edit Ruangan
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="kode_ruangan_edit" class="form-label fw-semibold">Kode Ruangan <span class="text-danger">*</span></label>
                            <input type="text" name="kode_ruangan" id="kode_ruangan_edit"
                                   class="form-control rounded-3 @error('kode_ruangan') is-invalid @enderror"
                                   required>
                            @error('kode_ruangan') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-8">
                            <label for="nama_ruangan_edit" class="form-label fw-semibold">Nama Ruangan <span class="text-danger">*</span></label>
                            <input type="text" name="nama_ruangan" id="nama_ruangan_edit"
                                   class="form-control rounded-3 @error('nama_ruangan') is-invalid @enderror"
                                   required>
                            @error('nama_ruangan') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label for="lokasi_edit" class="form-label fw-semibold">Lokasi / Gedung</label>
                            <input type="text" name="lokasi" id="lokasi_edit"
                                   class="form-control rounded-3 @error('lokasi') is-invalid @enderror">
                            @error('lokasi') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label for="pengurus_edit" class="form-label fw-semibold">Pengurus Ruangan</label>
                            <select name="pengurus[]" id="pengurus_edit"
                                    class="form-select rounded-3 @error('pengurus') is-invalid @enderror"
                                    multiple size="5">
                                @foreach($guruList as $guru)
                                    <option value="{{ $guru->id }}">
                                        {{ $guru->nama }} — {{ $guru->role_label }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">Tahan <kbd>Ctrl</kbd> (Windows) / <kbd>Cmd</kbd> (Mac) untuk memilih lebih dari satu.</div>
                            @error('pengurus') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
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
    function openEditModal(id, kode, nama, lokasi, pengurusIds) {
        const form = document.getElementById('formEditRuangan');
        form.action = '/admin/ruangan/' + id;

        document.getElementById('kode_ruangan_edit').value = kode;
        document.getElementById('nama_ruangan_edit').value = nama;
        document.getElementById('lokasi_edit').value = lokasi;

        const select = document.getElementById('pengurus_edit');
        for (let i = 0; i < select.options.length; i++) {
            select.options[i].selected = pengurusIds.includes(select.options[i].value);
        }
    }
</script>
@endpush
