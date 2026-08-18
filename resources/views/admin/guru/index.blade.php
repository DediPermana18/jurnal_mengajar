@extends('layouts.app')

@section('title', 'Data Master Guru - WebJournal Management System')

@section('content')
<div class="container-fluid px-0">

    <!-- HEADER HALAMAN -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 800; font-size: 1.75rem;">
                Data Master Guru
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                Kelola data guru pengajar dan wali kelas.
            </p>
        </div>

        <!-- Tombol Tambah Guru (Hanya Role Admin_TU, Admin, & Super Admin) -->
        @if(in_array(auth()->user()->role ?? '', ['admin_tu', 'admin', 'super_admin']))
            <div>
                <button type="button" class="btn btn-primary rounded-3 px-3 py-2 fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambahGuru">
                    <i class="bi bi-plus-lg me-1"></i> Tambah Guru
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
                    <strong class="d-block mb-1">Gagal menyimpan data:</strong>
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
        <form action="{{ route('guru.index') }}" method="GET" class="row g-3">
            
            <!-- Cari Guru -->
            <div class="col-md-4">
                <label class="form-label fw-bold text-secondary text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.05em;">Cari Guru</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 rounded-start-3 text-muted">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" 
                           name="search" 
                           value="{{ request('search') }}" 
                           class="form-control bg-light border-start-0 rounded-end-3" 
                           placeholder="Cari nama atau NIP..."
                           onchange="this.form.submit()">
                </div>
            </div>

            <!-- Dropdown Filter Status -->
            <div class="col-md-2">
                <label class="form-label fw-bold text-secondary text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.05em;">Status</label>
                <select name="status" class="form-select bg-light rounded-3" onchange="this.form.submit()">
                    <option value="Semua Status" {{ request('status') == 'Semua Status' ? 'selected' : '' }}>Semua Status</option>
                    <option value="Aktif" {{ request('status') == 'Aktif' ? 'selected' : '' }}>Aktif</option>
                    <option value="Tidak Aktif" {{ request('status') == 'Tidak Aktif' ? 'selected' : '' }}>Tidak Aktif</option>
                </select>
            </div>

            <!-- Dropdown Filter Kejuruan -->
            <div class="col-md-3">
                <label class="form-label fw-bold text-secondary text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.05em;">Kejuruan</label>
                <select name="kejuruan" class="form-select bg-light rounded-3" onchange="this.form.submit()">
                    <option value="Semua Kejuruan" {{ request('kejuruan') == 'Semua Kejuruan' ? 'selected' : '' }}>Semua Kejuruan</option>
                    <option value="RPL" {{ request('kejuruan') == 'RPL' ? 'selected' : '' }}>RPL</option>
                    <option value="TKJ" {{ request('kejuruan') == 'TKJ' ? 'selected' : '' }}>TKJ</option>
                    <option value="AKL" {{ request('kejuruan') == 'AKL' ? 'selected' : '' }}>AKL</option>
                    <option value="TKR" {{ request('kejuruan') == 'TKR' ? 'selected' : '' }}>TKR</option>
                    @foreach($daftarKejuruan as $jur)
                        @if(!in_array($jur->kode_jurusan, ['RPL', 'TKJ', 'AKL', 'TKR']))
                            <option value="{{ $jur->kode_jurusan }}" {{ request('kejuruan') == $jur->kode_jurusan ? 'selected' : '' }}>{{ $jur->kode_jurusan }}</option>
                        @endif
                    @endforeach
                </select>
            </div>

            <!-- Dropdown Filter Wali Kelas -->
            <div class="col-md-3">
                <label class="form-label fw-bold text-secondary text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.05em;">Wali Kelas</label>
                <select name="wali_kelas" class="form-select bg-light rounded-3" onchange="this.form.submit()">
                    <option value="Semua" {{ request('wali_kelas') == 'Semua' ? 'selected' : '' }}>Semua</option>
                    <option value="Ya" {{ request('wali_kelas') == 'Ya' ? 'selected' : '' }}>Ya</option>
                    <option value="Tidak" {{ request('wali_kelas') == 'Tidak' ? 'selected' : '' }}>Tidak</option>
                </select>
            </div>

        </form>
    </div>

    <!-- TABEL DATA MASTER GURU -->
    <div class="table-card-custom mb-4">
        <div class="table-responsive">
            <table class="table table-custom align-middle">
                <thead>
                    <tr>
                        <th style="width: 25%;">GURU</th>
                        <th style="width: 15%;">KEJURUAN</th>
                        <th style="width: 25%;">MATA PELAJARAN</th>
                        <th style="width: 18%;">WALI KELAS</th>
                        <th style="width: 12%;">STATUS</th>
                        @if(in_array(auth()->user()->role ?? '', ['admin_tu', 'admin', 'super_admin']))
                            <th class="text-end" style="width: 10%;">AKSI</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($dataGuru as $guru)
                        @php
                            // Inisial nama
                            $words = explode(' ', trim($guru->nama));
                            $initials = strtoupper(substr($words[0], 0, 1));
                            if (count($words) > 1) {
                                $initials .= strtoupper(substr(end($words), 0, 1));
                            } else {
                                $initials .= strtoupper(substr($words[0], 1, 1));
                            }

                            // Kejuruan unik
                            $kejuruanList = collect();
                            if ($guru->kelasWali && $guru->kelasWali->isNotEmpty()) {
                                foreach($guru->kelasWali as $kw) {
                                    if ($kw->jurusan) {
                                        $kejuruanList->push($kw->jurusan->kode_jurusan);
                                    }
                                }
                            }
                            if ($guru->kelas && $guru->kelas->jurusan) {
                                $kejuruanList->push($guru->kelas->jurusan->kode_jurusan);
                            } elseif (!empty($guru->kelas_id)) {
                                $k = $daftarKelas->firstWhere('id', $guru->kelas_id);
                                if ($k && $k->jurusan) {
                                    $kejuruanList->push($k->jurusan->kode_jurusan);
                                }
                            }
                            if ($guru->jadwalPelajaran && $guru->jadwalPelajaran->isNotEmpty()) {
                                foreach($guru->jadwalPelajaran as $jp) {
                                    if ($jp->kelas && $jp->kelas->jurusan) {
                                        $kejuruanList->push($jp->kelas->jurusan->kode_jurusan);
                                    }
                                }
                            }
                            $kejuruanList = $kejuruanList->unique();

                            // Mata pelajaran unik (ambil dari mapel_ids yang tersimpan di user + jadwalPelajaran)
                            $mapelNames = collect();
                            if (!empty($guru->mapel_ids)) {
                                $savedMapelIds = is_array($guru->mapel_ids) ? $guru->mapel_ids : (json_decode($guru->mapel_ids, true) ?? []);
                                $savedMapelIds = array_map('intval', (array)$savedMapelIds);
                                $mapelFromUser = $daftarMapel->whereIn('id', $savedMapelIds)->pluck('nama_mapel');
                                $mapelNames = $mapelNames->concat($mapelFromUser);
                            }
                            if ($guru->jadwalPelajaran && $guru->jadwalPelajaran->isNotEmpty()) {
                                $mapelFromJadwal = $guru->jadwalPelajaran
                                    ->map(fn($jp) => $jp->mataPelajaran ? $jp->mataPelajaran->nama_mapel : null)
                                    ->filter();
                                $mapelNames = $mapelNames->concat($mapelFromJadwal);
                            }
                            $mapelList = $mapelNames->unique();

                            // Nama kelas wali
                            $namaKelasWali = null;
                            if ($guru->kelasWali && $guru->kelasWali->isNotEmpty()) {
                                $namaKelasWali = $guru->kelasWali->pluck('nama_kelas')->join(', ');
                            } elseif ($guru->kelas) {
                                $namaKelasWali = $guru->kelas->nama_kelas;
                            } elseif (!empty($guru->kelas_id)) {
                                $k = $daftarKelas->firstWhere('id', $guru->kelas_id);
                                if ($k) {
                                    $namaKelasWali = $k->nama_kelas;
                                }
                            }
                        @endphp
                        <tr>
                            <!-- Kolom GURU -->
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="rounded-circle bg-secondary-subtle text-secondary fw-bold d-flex align-items-center justify-content-center shrink-0" 
                                         style="width: 44px; height: 44px; font-size: 0.9rem; letter-spacing: 0.02em;">
                                        {{ $initials }}
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark" style="font-size: 0.95rem;">{{ $guru->nama }}</div>
                                        <div class="text-muted" style="font-size: 0.8rem;">
                                            NIP: {{ $guru->nip ?? '-' }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Kolom KEJURUAN -->
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    @forelse($kejuruanList as $kej)
                                        <span class="badge bg-light text-secondary border px-2 py-1 font-monospace" style="font-size: 0.75rem;">
                                            {{ $kej }}
                                        </span>
                                    @empty
                                        <span class="text-muted" style="font-size: 0.85rem;">-</span>
                                    @endforelse
                                </div>
                            </td>

                            <!-- Kolom MATA PELAJARAN -->
                            <td>
                                <span class="text-dark" style="font-size: 0.875rem;">
                                    @if($mapelList->isNotEmpty())
                                        {{ $mapelList->join(', ') }}
                                    @else
                                        -
                                    @endif
                                </span>
                            </td>

                            <!-- Kolom WALI KELAS -->
                            <td>
                                <span class="text-dark" style="font-size: 0.875rem;">
                                    @if($namaKelasWali)
                                        Wali Kelas {{ $namaKelasWali }}
                                    @else
                                        -
                                    @endif
                                </span>
                            </td>

                            <!-- Kolom STATUS -->
                            <td>
                                @if($guru->trashed())
                                    <span class="status-badge-tidak-aktif text-danger bg-danger-subtle border border-danger-subtle rounded-pill px-3 py-1 fw-bold" style="font-size: 0.78rem;">
                                        <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i> Tidak Aktif
                                    </span>
                                @else
                                    <span class="status-badge-terisi text-success bg-success-subtle border border-success-subtle rounded-pill px-3 py-1 fw-bold" style="font-size: 0.78rem;">
                                        <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i> Aktif
                                    </span>
                                @endif
                            </td>

                            <!-- Kolom AKSI (Hanya Role Admin TU, Admin, & Super Admin) -->
                            @if(in_array(auth()->user()->role ?? '', ['admin_tu', 'admin', 'super_admin']))
                                <td class="text-end">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light border rounded-3 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3">
                                            <!-- Edit Guru -->
                                            <li>
                                                <button type="button" class="dropdown-item py-2" data-bs-toggle="modal" data-bs-target="#modalEditGuru{{ $guru->id }}">
                                                    <i class="bi bi-pencil-square me-2 text-warning"></i> Edit Data
                                                </button>
                                            </li>

                                            <!-- Reset / Ubah Password -->
                                            <li>
                                                <button type="button" class="dropdown-item py-2" data-bs-toggle="modal" data-bs-target="#modalPasswordGuru{{ $guru->id }}">
                                                    <i class="bi bi-key me-2 text-primary"></i> Reset / Ubah Password
                                                </button>
                                            </li>

                                            <!-- Toggle Status Aktif / Nonaktif -->
                                            <li>
                                                <form action="{{ route('guru.toggle-status', $guru->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item py-2">
                                                        @if($guru->trashed())
                                                            <i class="bi bi-check-circle me-2 text-success"></i> Aktifkan Akun
                                                        @else
                                                            <i class="bi bi-slash-circle me-2 text-secondary"></i> Nonaktifkan Akun
                                                        @endif
                                                    </button>
                                                </form>
                                            </li>

                                            <li><hr class="dropdown-divider"></li>

                                            <!-- Hapus Permanen -->
                                            <li>
                                                <form action="{{ route('guru.destroy', $guru->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus data guru ini secara permanen?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dropdown-item py-2 text-danger">
                                                        <i class="bi bi-trash me-2"></i> Hapus Permanen
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
                            <td colspan="{{ in_array(auth()->user()->role ?? '', ['admin_tu', 'admin', 'super_admin']) ? 6 : 5 }}" class="text-center py-5 text-muted">
                                <i class="bi bi-person-badge fs-1 d-block mb-2 text-secondary"></i>
                                Tidak ada data guru yang sesuai dengan kriteria pencarian/filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- FOOTER TABEL & PAGINATION -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-4 pt-3 border-top">
            <div class="text-muted small mb-3 mb-md-0">
                Menampilkan <strong>{{ $dataGuru->firstItem() ?? 0 }}</strong>-<strong>{{ $dataGuru->lastItem() ?? 0 }}</strong> dari <strong>{{ $dataGuru->total() }}</strong> Guru
            </div>
            <div>
                {{ $dataGuru->links() }}
            </div>
        </div>
    </div>

</div>

<!-- ================= MODALS KHUSUS ROLE ADMIN ================= -->
@if(in_array(auth()->user()->role ?? '', ['admin_tu', 'admin', 'super_admin']))

<!-- MODAL TAMBAH GURU -->
<div class="modal fade" id="modalTambahGuru" tabindex="-1" aria-labelledby="modalTambahGuruLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark" id="modalTambahGuruLabel">Tambah Data Guru Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('guru.store') }}" method="POST">
                @csrf
                <div class="modal-body py-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">NAMA GURU LENGKAP <span class="text-danger">*</span></label>
                        <input type="text" name="nama" required class="form-control rounded-3" placeholder="misal: Budi Santoso, S.Kom.">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">NIP (NOMOR INDUK PEGAWAI)</label>
                        <input type="text" name="nip" class="form-control rounded-3" placeholder="misal: 198005122005011003">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">USERNAME (UNTUK LOGIN) <span class="text-danger">*</span></label>
                        <input type="text" name="username" required class="form-control rounded-3" placeholder="misal: gurubudi">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">PASSWORD AKUN</label>
                        <input type="password" name="password" class="form-control rounded-3" placeholder="Kosongkan untuk default: password123">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">ROLE GURU <span class="text-danger">*</span></label>
                        <select name="role" id="tambah_role" class="form-select rounded-3" required onchange="toggleRoleFields('tambah')">
                            <option value="guru_mapel">Guru Mapel</option>
                            <option value="wali_kelas">Wali Kelas</option>
                            <option value="guru_piket">Guru Piket</option>
                            <option value="guru">Guru (Umum)</option>
                        </select>
                    </div>

                    <!-- FIELD DINAMIS: MATA PELAJARAN (muncul untuk Guru Mapel, Wali Kelas, & Guru) -->
                    <div class="mb-3" id="tambah_field_mapel">
                        <label class="form-label fw-semibold text-secondary small">
                            MATA PELAJARAN
                        </label>
                        <select name="mapel_ids[]" id="tambah_mapel_select" class="form-select rounded-3" multiple size="5">
                            @foreach($daftarMapel as $mapel)
                                <option value="{{ $mapel->id }}">
                                    {{ $mapel->nama_mapel }}
                                    @if($mapel->kode_mapel)
                                        ({{ $mapel->kode_mapel }})
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text text-muted small">
                            <i class="bi bi-info-circle me-1"></i>Tahan tombol <strong>Ctrl</strong> (Windows) atau <strong>Cmd</strong> (Mac) untuk memilih lebih dari 1 mata pelajaran.
                        </div>
                    </div>

                    <!-- FIELD DINAMIS: KELAS YANG DIWALIIN (muncul hanya untuk Wali Kelas) -->
                    <div class="mb-3" id="tambah_field_kelas" style="display: none;">
                        <label class="form-label fw-semibold text-secondary small">
                            KELAS YANG DIWALIIN <span class="text-danger">*</span>
                        </label>
                        <select name="kelas_id" id="tambah_kelas_select" class="form-select rounded-3">
                            <option value="">-- Pilih Kelas --</option>
                            @foreach($daftarKelas as $kelas)
                                <option value="{{ $kelas->id }}" {{ $kelas->id_wali_kelas ? 'disabled' : '' }}>
                                    {{ $kelas->nama_kelas }}
                                    @if($kelas->jurusan)
                                        - {{ $kelas->jurusan->nama_jurusan }}
                                    @endif
                                    @if($kelas->id_wali_kelas)
                                        (sudah ada wali)
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text text-muted small">1 Wali Kelas hanya boleh memegang 1 Kelas.</div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4 fw-semibold">Simpan Data Guru</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODALS EDIT GURU & UBAH PASSWORD -->
@foreach($dataGuru as $guru)

@php
    $selectedMapelIds = [];
    if (!empty($guru->mapel_ids)) {
        $selectedMapelIds = is_array($guru->mapel_ids) ? $guru->mapel_ids : (json_decode($guru->mapel_ids, true) ?? []);
        $selectedMapelIds = array_map('intval', (array)$selectedMapelIds);
    }
    $currentKelasId = $guru->kelas_id ?? ($guru->kelasWali->first() ? $guru->kelasWali->first()->id : null);
@endphp

<!-- MODAL EDIT GURU -->
<div class="modal fade" id="modalEditGuru{{ $guru->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark">Edit Data Guru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('guru.update', $guru->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body py-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">NAMA GURU LENGKAP <span class="text-danger">*</span></label>
                        <input type="text" name="nama" value="{{ old('nama', $guru->nama) }}" required class="form-control rounded-3">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">NIP</label>
                        <input type="text" name="nip" value="{{ old('nip', $guru->nip) }}" class="form-control rounded-3">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">USERNAME <span class="text-danger">*</span></label>
                        <input type="text" name="username" value="{{ old('username', $guru->username) }}" required class="form-control rounded-3">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">ROLE GURU <span class="text-danger">*</span></label>
                        <select name="role" id="edit_{{ $guru->id }}_role" class="form-select rounded-3" required onchange="toggleRoleFields('edit_{{ $guru->id }}')">
                            <option value="guru_mapel" {{ $guru->role == 'guru_mapel' ? 'selected' : '' }}>Guru Mapel</option>
                            <option value="wali_kelas" {{ $guru->role == 'wali_kelas' ? 'selected' : '' }}>Wali Kelas</option>
                            <option value="guru_piket" {{ $guru->role == 'guru_piket' ? 'selected' : '' }}>Guru Piket</option>
                            <option value="guru" {{ $guru->role == 'guru' ? 'selected' : '' }}>Guru (Umum)</option>
                        </select>
                    </div>

                    <!-- FIELD DINAMIS: MATA PELAJARAN (muncul untuk Guru Mapel, Wali Kelas, & Guru) -->
                    <div class="mb-3" id="edit_{{ $guru->id }}_field_mapel" style="{{ in_array($guru->role, ['guru_mapel', 'wali_kelas', 'guru']) ? '' : 'display: none;' }}">
                        <label class="form-label fw-semibold text-secondary small">
                            MATA PELAJARAN
                        </label>
                        <select name="mapel_ids[]" id="edit_{{ $guru->id }}_mapel_select" class="form-select rounded-3" multiple size="5">
                            @foreach($daftarMapel as $mapel)
                                <option value="{{ $mapel->id }}" {{ in_array((int)$mapel->id, $selectedMapelIds, true) ? 'selected' : '' }}>
                                    {{ $mapel->nama_mapel }}
                                    @if($mapel->kode_mapel)
                                        ({{ $mapel->kode_mapel }})
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text text-muted small">
                            <i class="bi bi-info-circle me-1"></i>Tahan tombol <strong>Ctrl</strong> (Windows) atau <strong>Cmd</strong> (Mac) untuk memilih lebih dari 1 mata pelajaran.
                        </div>
                    </div>

                    <!-- FIELD DINAMIS: KELAS YANG DIWALIIN (muncul hanya untuk Wali Kelas) -->
                    <div class="mb-3" id="edit_{{ $guru->id }}_field_kelas" style="{{ $guru->role === 'wali_kelas' ? '' : 'display: none;' }}">
                        <label class="form-label fw-semibold text-secondary small">
                            KELAS YANG DIWALIIN <span class="text-danger">*</span>
                        </label>
                        <select name="kelas_id" id="edit_{{ $guru->id }}_kelas_select" class="form-select rounded-3">
                            <option value="">-- Pilih Kelas --</option>
                            @foreach($daftarKelas as $kelas)
                                @php
                                    $isOwnKelas = ($currentKelasId == $kelas->id) || ($guru->kelasWali && $guru->kelasWali->contains('id', $kelas->id));
                                    $hasOtherWali = $kelas->id_wali_kelas && !$isOwnKelas;
                                @endphp
                                <option value="{{ $kelas->id }}" {{ $isOwnKelas ? 'selected' : '' }} {{ $hasOtherWali ? 'disabled' : '' }}>
                                    {{ $kelas->nama_kelas }}
                                    @if($kelas->jurusan)
                                        - {{ $kelas->jurusan->nama_jurusan }}
                                    @endif
                                    @if($hasOtherWali)
                                        (dipegang guru lain)
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text text-muted small">1 Wali Kelas hanya boleh memegang 1 Kelas.</div>
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

<!-- MODAL RESET / UBAH PASSWORD GURU -->
<div class="modal fade" id="modalPasswordGuru{{ $guru->id }}" tabindex="-1" aria-labelledby="modalPasswordGuruLabel{{ $guru->id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark" id="modalPasswordGuruLabel{{ $guru->id }}">
                    <i class="bi bi-shield-lock text-primary me-2"></i> Reset / Ubah Password Guru
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('guru.update-password', $guru->id) }}" method="POST">
                @csrf
                <div class="modal-body py-4">
                    <p class="text-muted small mb-3">
                        Ubah password akun guru: <strong>{{ $guru->nama }}</strong> (Username: <code class="text-primary fw-bold">{{ $guru->username }}</code>)
                    </p>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">PASSWORD BARU <span class="text-danger">*</span></label>
                        <input type="text" 
                               name="password" 
                               id="inputPwd{{ $guru->id }}"
                               required 
                               minlength="6"
                               class="form-control rounded-3" 
                               placeholder="Ketik password baru (min 6 karakter)">
                    </div>

                    <!-- Checkbox Gunakan Password Default -->
                    <div class="form-check bg-light p-3 rounded-3 border">
                        <input class="form-check-input ms-0 me-2" 
                               type="checkbox" 
                               id="checkDefault{{ $guru->id }}"
                               onchange="handleDefaultPassword(this, 'inputPwd{{ $guru->id }}')">
                        <label class="form-check-label fw-semibold text-dark small" for="checkDefault{{ $guru->id }}">
                            Gunakan Password Default (<code class="text-primary fw-bold">password123</code>)
                        </label>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4 fw-semibold">
                        <i class="bi bi-key me-1"></i> Simpan Password Baru
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endforeach
@endif
@endsection

@push('scripts')
<script>
    function toggleRoleFields(prefix) {
        const roleSelect = document.getElementById(prefix + '_role');
        const mapelField = document.getElementById(prefix + '_field_mapel');
        const kelasField = document.getElementById(prefix + '_field_kelas');

        if (!roleSelect) return;
        const role = roleSelect.value;

        if (mapelField) {
            // Tampilkan pilihan Mapel untuk Guru Mapel, Wali Kelas, dan Guru Umum
            if (role === 'guru_mapel' || role === 'wali_kelas' || role === 'guru') {
                mapelField.style.display = 'block';
            } else {
                mapelField.style.display = 'none';
            }
        }

        if (kelasField) {
            // Tampilkan pilihan Kelas hanya untuk Wali Kelas
            if (role === 'wali_kelas') {
                kelasField.style.display = 'block';
            } else {
                kelasField.style.display = 'none';
            }
        }
    }

    function handleDefaultPassword(checkbox, inputId) {
        const input = document.getElementById(inputId);
        if (!input) return;
        if (checkbox.checked) {
            input.value = 'password123';
            input.readOnly = true;
        } else {
            input.value = '';
            input.readOnly = false;
            input.focus();
        }
    }
</script>
@endpush
