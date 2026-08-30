@extends('layouts.app')

@push('styles')
<style>
    .table-responsive {
        min-height: 280px;
        padding-bottom: 2rem;
    }
    .dropdown-menu {
        z-index: 1060 !important;
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">

    <!-- HEADER HALAMAN -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 800; font-size: 1.75rem;">
                Data Master Kelas
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                Kelola rombongan belajar, tingkat, jurusan, dan penugasan wali kelas.
            </p>
        </div>

        <!-- Tombol Tambah Kelas (Role Admin_TU, Admin, & Super Admin) -->
        @if(in_array(auth()->user()->role ?? '', ['admin_tu', 'admin', 'super_admin']))
            <div>
                <button type="button" class="btn btn-primary rounded-3 px-3 py-2 fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambahKelas">
                    <i class="bi bi-plus-lg me-1"></i> Tambah Kelas
                </button>
            </div>
        @endif
    </div>

    <!-- ALERT NOTIFIKASI SUCCESS / ERROR -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                <div>{{ session('success') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <div>
                    <strong class="d-block mb-1">Terjadi kesalahan:</strong>
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- FILTER BAR (CARD PUTIH) -->
    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
        <form action="{{ route('kelas.index') }}" method="GET" class="row g-3">
            
            <!-- Cari Kelas -->
            <div class="col-md-5">
                <label class="form-label fw-bold text-secondary text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.05em;">Cari Kelas / Wali</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 rounded-start-3 text-muted">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" 
                           name="search" 
                           value="{{ request('search') }}" 
                           class="form-control bg-light border-start-0 rounded-end-3" 
                           placeholder="Cari nama kelas atau wali kelas..."
                           onchange="this.form.submit()">
                </div>
            </div>

            <!-- Dropdown Filter Tingkat -->
            <div class="col-md-3">
                <label class="form-label fw-bold text-secondary text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.05em;">Tingkat</label>
                <select name="tingkat" class="form-select bg-light rounded-3" onchange="this.form.submit()">
                    <option value="Semua Tingkat" {{ request('tingkat') == 'Semua Tingkat' ? 'selected' : '' }}>Semua Tingkat</option>
                    <option value="X" {{ request('tingkat') == 'X' ? 'selected' : '' }}>Kelas X</option>
                    <option value="XI" {{ request('tingkat') == 'XI' ? 'selected' : '' }}>Kelas XI</option>
                    <option value="XII" {{ request('tingkat') == 'XII' ? 'selected' : '' }}>Kelas XII</option>
                </select>
            </div>

            <!-- Dropdown Filter Jurusan -->
            <div class="col-md-4">
                <label class="form-label fw-bold text-secondary text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.05em;">Jurusan</label>
                <select name="jurusan" class="form-select bg-light rounded-3" onchange="this.form.submit()">
                    <option value="Semua Jurusan" {{ request('jurusan') == 'Semua Jurusan' ? 'selected' : '' }}>Semua Jurusan</option>
                    @foreach($daftarJurusan as $jur)
                        <option value="{{ $jur->id }}" {{ request('jurusan') == $jur->id ? 'selected' : '' }}>
                            {{ $jur->kode_jurusan }} - {{ $jur->nama_jurusan }}
                        </option>
                    @endforeach
                </select>
            </div>

        </form>
    </div>

    <!-- TABEL DATA MASTER KELAS -->
    <div class="table-card-custom mb-4" style="overflow: visible;">
        <div class="table-responsive" style="min-height: 280px; padding-bottom: 2rem;">
            <table class="table table-custom align-middle">
                <thead>
                    <tr>
                        <th style="width: 22%;">NAMA KELAS</th>
                        <th style="width: 14%;">TINGKAT</th>
                        <th style="width: 24%;">JURUSAN</th>
                        <th style="width: 18%;">RUANGAN</th>
                        <th style="width: 24%;">WALI KELAS</th>
                        <th style="width: 16%;">TOTAL SISWA</th>
                        @if(in_array(auth()->user()->role ?? '', ['admin_tu', 'admin', 'super_admin']))
                            <th class="text-end" style="width: 10%;">AKSI</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($dataKelas as $kelas)
                        @php
                            // Warna badge tingkat
                            $tingkatColor = match($kelas->tingkat) {
                                'X'   => ['bg' => '#dcfce7', 'color' => '#166534', 'border' => '#bbf7d0'],
                                'XI'  => ['bg' => '#fef9c3', 'color' => '#854d0e', 'border' => '#fef08a'],
                                'XII' => ['bg' => '#dbeafe', 'color' => '#1d4ed8', 'border' => '#bfdbfe'],
                                default => ['bg' => '#f1f5f9', 'color' => '#475569', 'border' => '#e2e8f0']
                            };

                            // Inisial Wali Kelas jika ada
                            $waliInitial = '-';
                            if ($kelas->waliKelas) {
                                $words = explode(' ', trim($kelas->waliKelas->nama));
                                $waliInitial = strtoupper(substr($words[0], 0, 1));
                                if (count($words) > 1) {
                                    $waliInitial .= strtoupper(substr(end($words), 0, 1));
                                }
                            }
                        @endphp
                        <tr>
                            <!-- Kolom NAMA KELAS -->
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge fw-bold px-3 py-2 rounded-3 shadow-none" 
                                          style="background-color: {{ $tingkatColor['bg'] }}; color: {{ $tingkatColor['color'] }}; border: 1px solid {{ $tingkatColor['border'] }}; font-size: 0.9rem; letter-spacing: 0.01em;">
                                        {{ $kelas->nama_kelas }}
                                    </span>
                                </div>
                            </td>

                            <!-- Kolom TINGKAT -->
                            <td>
                                <span class="badge bg-light text-dark border px-2 py-1 rounded-2 font-monospace" style="font-size: 0.8rem;">
                                    {{ $kelas->tingkat }}
                                </span>
                            </td>

                            <!-- Kolom JURUSAN -->
                            <td>
                                @if($kelas->jurusan)
                                    <div>
                                        <span class="badge bg-light text-secondary border px-2 py-1 font-monospace mb-1" style="font-size: 0.75rem;">
                                            {{ $kelas->jurusan->kode_jurusan }}
                                        </span>
                                        <div class="fw-semibold text-dark" style="font-size: 0.875rem;">
                                            {{ $kelas->jurusan->nama_jurusan }}
                                        </div>
                                    </div>
                                @else
                                    <span class="text-muted" style="font-size: 0.85rem;">-</span>
                                @endif
                            </td>

                            <!-- Kolom RUANGAN -->
                            <td>
                                @if($kelas->ruangan)
                                    <span class="badge bg-light text-dark border px-3 py-2 rounded-3" style="font-size: 0.8rem;">
                                        <i class="bi bi-building me-1"></i>{{ $kelas->ruangan->kode_ruangan }} — {{ $kelas->ruangan->nama_ruangan }}
                                    </span>
                                @else
                                    <span class="badge bg-light text-muted border px-2 py-1 rounded-pill" style="font-size: 0.78rem;">
                                        <i class="bi bi-dash-circle me-1"></i>Belum ditentukan
                                    </span>
                                @endif
                            </td>

                            <!-- Kolom WALI KELAS -->
                            <td>
                                @if($kelas->waliKelas)
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle bg-secondary-subtle text-secondary fw-bold d-flex align-items-center justify-content-center shrink-0" 
                                             style="width: 36px; height: 36px; font-size: 0.8rem;">
                                            {{ $waliInitial }}
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark" style="font-size: 0.9rem;">
                                                {{ $kelas->waliKelas->nama }}
                                            </div>
                                            <div class="text-muted" style="font-size: 0.78rem;">
                                                NIP: {{ $kelas->waliKelas->nip ?? '-' }}
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <span class="badge bg-light text-muted border px-2 py-1 rounded-pill" style="font-size: 0.78rem;">
                                        <i class="bi bi-person-x me-1"></i> Belum ada wali
                                    </span>
                                @endif
                            </td>

                            <!-- Kolom TOTAL SISWA -->
                            <td>
                                <a href="{{ route('kelas.show', $kelas->id) }}" class="text-decoration-none">
                                    <span class="badge bg-light text-primary border border-primary-subtle px-3 py-2 rounded-3 fw-bold" style="font-size: 0.85rem;">
                                        {{ $kelas->siswa_count ?? 0 }}
                                    </span>
                                </a>
                            </td>

                            <!-- Kolom AKSI -->
                            @if(in_array(auth()->user()->role ?? '', ['admin_tu', 'admin', 'super_admin']))
                                <td class="text-end">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light border rounded-3 dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="window" aria-expanded="false">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3 z-50" style="z-index: 1060;">
                                            <!-- Detail Kelas & Siswa -->
                                            <li>
                                                <a href="{{ route('kelas.show', $kelas->id) }}" class="dropdown-item py-2">
                                                    <i class="bi bi-eye me-2 text-info"></i> Detail Kelas & Siswa
                                                </a>
                                            </li>

                                            <!-- Edit Kelas -->
                                            <li>
                                                <button type="button" class="dropdown-item py-2" data-bs-toggle="modal" data-bs-target="#modalEditKelas{{ $kelas->id }}">
                                                    <i class="bi bi-pencil-square me-2 text-warning"></i> Edit Kelas
                                                </button>
                                            </li>

                                            <li><hr class="dropdown-divider"></li>

                                            <!-- Hapus Kelas -->
                                            <li>
                                                <form action="{{ route('kelas.destroy', $kelas->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data kelas {{ $kelas->nama_kelas }}?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dropdown-item py-2 text-danger">
                                                        <i class="bi bi-trash me-2"></i> Hapus Kelas
                                                    </button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ in_array(auth()->user()->role ?? '', ['admin_tu', 'admin', 'super_admin']) ? 7 : 6 }}" class="text-center py-5 text-muted">
                                <i class="bi bi-door-closed fs-1 d-block mb-2 text-secondary"></i>
                                Belum ada data kelas yang sesuai dengan kriteria pencarian/filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- FOOTER TABEL & PAGINATION -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-4 pt-3 border-top">
            <div class="text-muted small mb-3 mb-md-0">
                Menampilkan <strong>{{ $dataKelas->firstItem() ?? 0 }}</strong>-<strong>{{ $dataKelas->lastItem() ?? 0 }}</strong> dari <strong>{{ $dataKelas->total() }}</strong> Kelas
            </div>
            <div>
                {{ $dataKelas->links() }}
            </div>
        </div>
    </div>

</div>

<!-- ================= MODALS KHUSUS ROLE ADMIN ================= -->
@if(in_array(auth()->user()->role ?? '', ['admin_tu', 'admin', 'super_admin']))

<!-- MODAL TAMBAH KELAS -->
<div class="modal fade" id="modalTambahKelas" tabindex="-1" aria-labelledby="modalTambahKelasLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark" id="modalTambahKelasLabel">Tambah Data Kelas Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('kelas.store') }}" method="POST">
                @csrf
                <div class="modal-body py-4">
                    <!-- NAMA KELAS -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">NAMA KELAS <span class="text-danger">*</span></label>
                        <input type="text" name="nama_kelas" value="{{ old('nama_kelas') }}" required class="form-control rounded-3" placeholder="misal: RPL 1, TKJ 2">
                        <div class="form-text text-muted small">Isi nama rombel/jurusan tanpa tingkat, misalnya RPL 1.</div>
                    </div>

                    <!-- TINGKAT -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">TINGKAT KELAS <span class="text-danger">*</span></label>
                        <select name="tingkat" class="form-select rounded-3" required>
                            <option value="">-- Pilih Tingkat --</option>
                            <option value="X" {{ old('tingkat') == 'X' ? 'selected' : '' }}>Kelas X (Sepuluh)</option>
                            <option value="XI" {{ old('tingkat') == 'XI' ? 'selected' : '' }}>Kelas XI (Sebelas)</option>
                            <option value="XII" {{ old('tingkat') == 'XII' ? 'selected' : '' }}>Kelas XII (Dua Belas)</option>
                        </select>
                    </div>

                    <!-- JURUSAN -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">KOMPETENSI KEAHLIAN / JURUSAN <span class="text-danger">*</span></label>
                        <select name="id_jurusan" class="form-select rounded-3" required>
                            <option value="">-- Pilih Jurusan --</option>
                            @foreach($daftarJurusan as $jurusan)
                                <option value="{{ $jurusan->id }}" {{ old('id_jurusan') == $jurusan->id ? 'selected' : '' }}>
                                    {{ $jurusan->kode_jurusan }} - {{ $jurusan->nama_jurusan }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- RUANGAN HOMEBASE -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">RUANGAN HOMEBASE (OPSIONAL)</label>
                        <select name="ruangan_id" class="form-select rounded-3">
                            <option value="">-- Belum Ditentukan --</option>
                            @foreach($daftarRuangan as $ruangan)
                                <option value="{{ $ruangan->id }}" {{ old('ruangan_id') == $ruangan->id ? 'selected' : '' }}>
                                    {{ $ruangan->kode_ruangan }} — {{ $ruangan->nama_ruangan }} @if($ruangan->lokasi) ({{ $ruangan->lokasi }}) @endif
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text text-muted small">Ruangan fisik tempat kelas ini berada/homespace.</div>
                    </div>

                    <!-- WALI KELAS -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">WALI KELAS (OPSIONAL)</label>
                        <select name="id_wali_kelas" class="form-select rounded-3">
                            <option value="">-- Belum Ditentukan / Pilih Nanti --</option>
                            @foreach($daftarWaliKelas as $wali)
                                @php
                                    $hasKelas = $wali->kelasWali->isNotEmpty();
                                    $namaKelasDipegang = $hasKelas ? $wali->kelasWali->pluck('nama_kelas')->join(', ') : '';
                                @endphp
                                <option value="{{ $wali->id }}" {{ old('id_wali_kelas') == $wali->id ? 'selected' : '' }} {{ $hasKelas ? 'disabled' : '' }}>
                                    {{ $wali->nama }} @if($wali->nip) (NIP: {{ $wali->nip }}) @endif
                                    @if($hasKelas)
                                        - [Sudah menjadi wali di {{ $namaKelasDipegang }}]
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text text-muted small">1 Guru hanya dapat ditugaskan menjadi Wali Kelas untuk 1 kelas.</div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4 fw-semibold">Simpan Kelas</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODALS EDIT KELAS -->
@foreach($dataKelas as $kelas)
<div class="modal fade" id="modalEditKelas{{ $kelas->id }}" tabindex="-1" aria-labelledby="modalEditKelasLabel{{ $kelas->id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark" id="modalEditKelasLabel{{ $kelas->id }}">Edit Data Kelas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('kelas.update', $kelas->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body py-4">
                    <!-- NAMA KELAS -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">NAMA KELAS <span class="text-danger">*</span></label>
                        <input type="text" name="nama_kelas" value="{{ old('nama_kelas', $kelas->nama_kelas) }}" required class="form-control rounded-3" placeholder="misal: RPL 1, TKJ 2">
                    </div>

                    <!-- TINGKAT -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">TINGKAT KELAS <span class="text-danger">*</span></label>
                        <select name="tingkat" class="form-select rounded-3" required>
                            <option value="X" {{ old('tingkat', $kelas->tingkat) == 'X' ? 'selected' : '' }}>Kelas X (Sepuluh)</option>
                            <option value="XI" {{ old('tingkat', $kelas->tingkat) == 'XI' ? 'selected' : '' }}>Kelas XI (Sebelas)</option>
                            <option value="XII" {{ old('tingkat', $kelas->tingkat) == 'XII' ? 'selected' : '' }}>Kelas XII (Dua Belas)</option>
                        </select>
                    </div>

                    <!-- JURUSAN -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">KOMPETENSI KEAHLIAN / JURUSAN <span class="text-danger">*</span></label>
                        <select name="id_jurusan" class="form-select rounded-3" required>
                            <option value="">-- Pilih Jurusan --</option>
                            @foreach($daftarJurusan as $jurusan)
                                <option value="{{ $jurusan->id }}" {{ old('id_jurusan', $kelas->id_jurusan) == $jurusan->id ? 'selected' : '' }}>
                                    {{ $jurusan->kode_jurusan }} - {{ $jurusan->nama_jurusan }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- RUANGAN HOMEBASE -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">RUANGAN HOMEBASE (OPSIONAL)</label>
                        <select name="ruangan_id" class="form-select rounded-3">
                            <option value="">-- Belum Ditentukan --</option>
                            @foreach($daftarRuangan as $ruangan)
                                <option value="{{ $ruangan->id }}" {{ old('ruangan_id', $kelas->ruangan_id) == $ruangan->id ? 'selected' : '' }}>
                                    {{ $ruangan->kode_ruangan }} — {{ $ruangan->nama_ruangan }} @if($ruangan->lokasi) ({{ $ruangan->lokasi }}) @endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- WALI KELAS -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">WALI KELAS (OPSIONAL)</label>
                        <select name="id_wali_kelas" class="form-select rounded-3">
                            <option value="">-- Belum Ditentukan / Kosongkan --</option>
                            @foreach($daftarWaliKelas as $wali)
                                @php
                                    $isCurrentWali = ($kelas->id_wali_kelas == $wali->id);
                                    $hasOtherKelas = $wali->kelasWali->isNotEmpty() && !$isCurrentWali;
                                    $namaKelasDipegang = $hasOtherKelas ? $wali->kelasWali->pluck('nama_kelas')->join(', ') : '';
                                @endphp
                                <option value="{{ $wali->id }}" 
                                        {{ old('id_wali_kelas', $kelas->id_wali_kelas) == $wali->id ? 'selected' : '' }} 
                                        {{ $hasOtherKelas ? 'disabled' : '' }}>
                                    {{ $wali->nama }} @if($wali->nip) (NIP: {{ $wali->nip }}) @endif
                                    @if($isCurrentWali)
                                        - [Wali Saat Ini]
                                    @elseif($hasOtherKelas)
                                        - [Sudah menjadi wali di {{ $namaKelasDipegang }}]
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text text-muted small">1 Guru hanya dapat ditugaskan menjadi Wali Kelas untuk 1 kelas.</div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4 fw-semibold">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach

@endif
@endsection
