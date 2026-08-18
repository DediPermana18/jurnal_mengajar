@extends('layouts.app')

@section('title', 'Master Jam Pelajaran - Kurikulum')

@section('content')
<div class="container-fluid px-0">

    {{-- Page Header --}}
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="font-weight: 900; font-size: 1.75rem; letter-spacing: -0.02em;">
                Master Jam Pelajaran
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                Kelola slot jam mengajar harian per tingkat kelas (Kelas 10, 11, 12). Penomoran jam otomatis berurutan.
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap align-items-center">
            {{-- Tombol Salin Preset antar Tingkat --}}
            <button type="button" class="btn btn-outline-primary rounded-3 fw-semibold px-3 d-flex align-items-center gap-2"
                    style="font-size: 0.875rem;" data-bs-toggle="modal" data-bs-target="#modalSalinTingkat">
                <i class="bi bi-copy"></i>
                Salin dari Tingkat Lain
            </button>

            {{-- Tombol Generate Preset --}}
            <form method="POST" action="{{ route('kurikulum.jam-pelajaran.generate') }}" class="d-inline"
                  onsubmit="return confirm('Generate preset akan mengatur ulang data jam Kelas {{ $tingkat }} untuk {{ $tab }}. Lanjutkan?')">
                @csrf
                <input type="hidden" name="tingkat" value="{{ $tingkat }}">
                <input type="hidden" name="kategori_hari" value="{{ $tab }}">
                <button type="submit" class="btn btn-outline-warning rounded-3 fw-semibold px-3 d-flex align-items-center gap-2"
                        style="font-size: 0.875rem;">
                    <i class="bi bi-lightning-charge-fill"></i>
                    Generate Preset {{ $tab }}
                </button>
            </form>

            {{-- Tombol Tambah Jam --}}
            <button type="button" class="btn btn-primary rounded-3 fw-semibold px-3 d-flex align-items-center gap-2"
                    style="font-size: 0.875rem;" data-bs-toggle="modal" data-bs-target="#modalTambahJam">
                <i class="bi bi-plus-lg"></i>
                Tambah Jam Pelajaran
            </button>
        </div>
    </div>

    {{-- Alert Messages --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4" role="alert"
             style="font-size: 0.9rem;">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4" role="alert"
             style="font-size: 0.9rem;">
            <i class="bi bi-exclamation-circle-fill me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- 1. Tab Filter Tingkat Kelas (Kelas 10, Kelas 11, Kelas 12) --}}
    <div class="card border-0 rounded-4 shadow-sm mb-3 bg-white p-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="text-muted fw-bold text-uppercase d-none d-md-inline" style="font-size: 0.78rem; letter-spacing: 0.05em;">
                    <i class="bi bi-mortarboard-fill text-primary me-1"></i> Tingkat Kelas:
                </span>
                <div class="btn-group p-1 bg-light rounded-3" role="group" aria-label="Filter Tingkat Kelas">
                    @foreach($tingkatList as $t)
                        <a href="{{ route('kurikulum.jam-pelajaran.index', ['tingkat' => $t, 'tab' => $tab]) }}"
                           class="btn rounded-3 fw-bold px-4 py-2 {{ $tingkat === $t ? 'btn-primary shadow-sm text-white' : 'btn-light text-dark' }}"
                           style="font-size: 0.875rem;">
                            Kelas {{ $t }}
                        </a>
                    @endforeach
                </div>
            </div>
            <div class="text-muted" style="font-size: 0.82rem;">
                Struktur Aktif: <strong class="text-dark">Kelas {{ $tingkat }}</strong> &bull; <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">{{ $tab === 'Senin-Kamis' ? 'Senin – Kamis' : 'Jumat' }}</span>
            </div>
        </div>
    </div>

    {{-- 2. Tab Filter Hari (Senin–Kamis vs Jumat) --}}
    <div class="mb-4">
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('kurikulum.jam-pelajaran.index', ['tingkat' => $tingkat, 'tab' => 'Senin-Kamis']) }}"
               class="btn rounded-3 fw-semibold px-4 py-2 {{ $tab === 'Senin-Kamis' ? 'btn-primary shadow-sm text-white' : 'btn-light border text-dark' }}"
               style="font-size: 0.875rem;">
                <i class="bi bi-calendar-week me-1"></i>
                Senin – Kamis <span class="badge {{ $tab === 'Senin-Kamis' ? 'bg-white text-primary' : 'bg-secondary-subtle text-secondary' }} rounded-pill ms-1">40 menit</span>
            </a>
            <a href="{{ route('kurikulum.jam-pelajaran.index', ['tingkat' => $tingkat, 'tab' => 'Jumat']) }}"
               class="btn rounded-3 fw-semibold px-4 py-2 {{ $tab === 'Jumat' ? 'btn-primary shadow-sm text-white' : 'btn-light border text-dark' }}"
               style="font-size: 0.875rem;">
                <i class="bi bi-calendar2-day me-1"></i>
                Jumat <span class="badge {{ $tab === 'Jumat' ? 'bg-white text-primary' : 'bg-secondary-subtle text-secondary' }} rounded-pill ms-1">30 menit</span>
            </a>
        </div>
    </div>

    {{-- 3. Main Data Card --}}
    <div class="card border-0 rounded-4 shadow-sm">
        <div class="card-header bg-white border-0 pt-4 pb-0 px-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-2 d-flex align-items-center justify-content-center"
                         style="width: 34px; height: 34px; background: {{ $tab === 'Senin-Kamis' ? 'linear-gradient(135deg,#1677ff,#0958d9)' : 'linear-gradient(135deg,#f97316,#ea580c)' }};">
                        <i class="bi {{ $tab === 'Senin-Kamis' ? 'bi-calendar-week' : 'bi-calendar2-day' }} text-white" style="font-size: 0.95rem;"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.95rem;">
                            Jadwal Kelas {{ $tingkat }} &mdash; {{ $tab === 'Senin-Kamis' ? 'Senin – Kamis' : 'Jumat' }}
                        </h6>
                        <div class="text-muted" style="font-size: 0.75rem;">
                            {{ ($tab === 'Senin-Kamis' ? $seninKamis : $jumat)->count() }} slot terdaftar
                        </div>
                    </div>
                </div>
                <span class="badge bg-light text-secondary border rounded-pill px-3 py-1" style="font-size: 0.75rem;">
                    {{ $tab === 'Senin-Kamis' ? 'Durasi per JP: 40 Menit' : 'Durasi per JP: 30 Menit' }}
                </span>
            </div>
        </div>

        <div class="card-body p-0">
            @php $rows = $tab === 'Senin-Kamis' ? $seninKamis : $jumat; @endphp

            @if($rows->isEmpty())
                <div class="text-center py-5">
                    <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle mb-3" style="width: 70px; height: 70px;">
                        <i class="bi bi-clock text-muted" style="font-size: 2.2rem;"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-1">Belum Ada Data Jam Pelajaran untuk Kelas {{ $tingkat }}</h6>
                    <p class="text-muted mx-auto mb-3" style="max-width: 420px; font-size: 0.85rem;">
                        Anda dapat menyalin struktur dari tingkat lain atau klik <strong>Generate Preset Otomatis</strong>.
                    </p>
                    <div class="d-flex justify-content-center gap-2">
                        <button type="button" class="btn btn-outline-primary rounded-3 px-3 py-2 fw-semibold" style="font-size: 0.85rem;"
                                data-bs-toggle="modal" data-bs-target="#modalSalinTingkat">
                            <i class="bi bi-copy me-1"></i> Salin dari Tingkat Lain
                        </button>
                        <form method="POST" action="{{ route('kurikulum.jam-pelajaran.generate') }}" class="d-inline">
                            @csrf
                            <input type="hidden" name="tingkat" value="{{ $tingkat }}">
                            <input type="hidden" name="kategori_hari" value="{{ $tab }}">
                            <button type="submit" class="btn btn-primary rounded-3 px-3 py-2 fw-semibold" style="font-size: 0.85rem;">
                                <i class="bi bi-lightning-charge-fill me-1"></i> Generate Preset
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
                        <thead style="background: #f8fafc;">
                            <tr>
                                <th class="ps-4 py-3" style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #64748b; white-space: nowrap; width: 130px;">Jam Ke-</th>
                                <th class="py-3" style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #64748b; width: 180px;">Rentang Waktu</th>
                                <th class="py-3" style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #64748b; width: 120px;">Durasi</th>
                                <th class="py-3" style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #64748b;">Jenis / Keterangan</th>
                                <th class="py-3 pe-4 text-end" style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #64748b; width: 140px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $istirahatCount = 0; @endphp
                            @foreach($rows as $jam)
                                @php
                                    $mulai   = \Carbon\Carbon::createFromFormat('H:i:s', $jam->jam_mulai);
                                    $selesai = \Carbon\Carbon::createFromFormat('H:i:s', $jam->jam_selesai);
                                    $durasi  = $mulai->diffInMinutes($selesai);

                                    if ($jam->jenis === 'istirahat') {
                                        $istirahatCount++;
                                        $jenisLabel = "Istirahat " . $istirahatCount;
                                    } else {
                                        $jenisLabel = match($jam->jenis) {
                                            'kbm'        => 'KBM',
                                            'upacara'    => 'Upacara',
                                            'pembiasaan' => 'Pembiasaan',
                                            default      => ucfirst($jam->jenis),
                                        };
                                    }

                                    $jenisBadge = match($jam->jenis) {
                                        'kbm'        => ['bg' => '#ecfdf5', 'color' => '#059669', 'border' => '#a7f3d0', 'icon' => 'bi-book-fill'],
                                        'istirahat'  => ['bg' => '#fff7ed', 'color' => '#ea580c', 'border' => '#fed7aa', 'icon' => 'bi-cup-hot-fill'],
                                        'upacara'    => ['bg' => '#eff6ff', 'color' => '#2563eb', 'border' => '#bfdbfe', 'icon' => 'bi-flag-fill'],
                                        'pembiasaan' => ['bg' => '#f5f3ff', 'color' => '#7c3aed', 'border' => '#ddd6fe', 'icon' => 'bi-heart-pulse-fill'],
                                        default      => ['bg' => '#f8fafc', 'color' => '#64748b', 'border' => '#e2e8f0', 'icon' => 'bi-circle-fill'],
                                    };
                                @endphp
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center gap-2">
                                            @if($jam->jenis !== 'istirahat' && $jam->jam_ke)
                                                <div class="rounded-circle d-flex align-items-center justify-content-center fw-black text-white"
                                                     style="width: 30px; height: 30px; font-size: 0.78rem; background: {{ $jam->jenis === 'kbm' ? '#1677ff' : ($jam->jenis === 'upacara' ? '#2563eb' : '#7c3aed') }};">
                                                    {{ $jam->jam_ke }}
                                                </div>
                                                <span class="fw-bold text-dark">Jam {{ $jam->jam_ke }}</span>
                                            @else
                                                <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-muted bg-light border"
                                                     style="width: 30px; height: 30px; font-size: 0.78rem;">
                                                    -
                                                </div>
                                                <span class="text-muted fw-semibold">-</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-dark" style="font-variant-numeric: tabular-nums; font-family: 'Courier New', monospace; font-size: 0.92rem;">
                                            {{ substr(str_replace(':', '.', $jam->jam_mulai), 0, 5) }} – {{ substr(str_replace(':', '.', $jam->jam_selesai), 0, 5) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-muted fw-semibold" style="font-size: 0.85rem;">{{ $durasi }} menit</span>
                                    </td>
                                    <td>
                                        <span class="badge d-inline-flex align-items-center gap-1 px-3 py-2 rounded-pill fw-semibold"
                                              style="font-size: 0.78rem; background-color: {{ $jenisBadge['bg'] }}; color: {{ $jenisBadge['color'] }}; border: 1px solid {{ $jenisBadge['border'] }};">
                                            <i class="bi {{ $jenisBadge['icon'] }}" style="font-size: 0.72rem;"></i>
                                            {{ $jenisLabel }}
                                        </span>
                                    </td>
                                    <td class="pe-4 text-end">
                                        <div class="d-flex gap-1 justify-content-end">
                                            <button type="button"
                                                    class="btn btn-sm btn-light border rounded-3 px-2 py-1"
                                                    style="font-size: 0.78rem;"
                                                    title="Edit"
                                                    onclick="openEditModal(
                                                        {{ $jam->id }},
                                                        '{{ $jam->tingkat ?? $tingkat }}',
                                                        '{{ $jam->kategori_hari }}',
                                                        '{{ substr($jam->jam_mulai, 0, 5) }}',
                                                        '{{ substr($jam->jam_selesai, 0, 5) }}',
                                                        '{{ $jam->jenis }}'
                                                    )">
                                                <i class="bi bi-pencil-fill text-primary me-1"></i> Edit
                                            </button>
                                            <form method="POST"
                                                  action="{{ route('kurikulum.jam-pelajaran.destroy', $jam->id) }}"
                                                  onsubmit="return confirm('Hapus slot {{ $jenisLabel }} ({{ \Carbon\Carbon::parse($jam->jam_mulai)->format('H.i') }}) untuk Kelas {{ $tingkat }}?')"
                                                  class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-light border rounded-3 px-2 py-1"
                                                        style="font-size: 0.78rem;" title="Hapus">
                                                    <i class="bi bi-trash3-fill text-danger me-1"></i> Hapus
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

</div>

{{-- ===================== MODAL SALIN DARI TINGKAT LAIN ===================== --}}
<div class="modal fade" id="modalSalinTingkat" tabindex="-1" aria-labelledby="modalSalinTingkatTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <form method="POST" action="{{ route('kurikulum.jam-pelajaran.copy') }}" id="formSalinTingkat">
                @csrf
                <input type="hidden" name="to_tingkat" value="{{ $tingkat }}">

                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="modalSalinTingkatTitle">
                        <i class="bi bi-copy text-primary me-2"></i>Salin Preset antar Tingkat
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body pt-3">
                    <div class="p-3 mb-3 rounded-3 bg-light border">
                        <div class="row g-2" style="font-size: 0.85rem;">
                            <div class="col-6">
                                <span class="text-muted">Tingkat Target:</span>
                                <div class="fw-bold text-dark">Kelas {{ $tingkat }}</div>
                            </div>
                            <div class="col-6">
                                <span class="text-muted">Hari Aktif:</span>
                                <div class="fw-bold text-dark">{{ $tab }}</div>
                            </div>
                        </div>
                    </div>

                    {{-- Tingkat Asal --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark" style="font-size: 0.875rem;">
                            Ambil struktur jam dari Tingkat:
                        </label>
                        <select name="from_tingkat" id="salinFromTingkat" class="form-select rounded-3" required>
                            @foreach($tingkatList as $t)
                                @if($t !== $tingkat)
                                    <option value="{{ $t }}">Kelas {{ $t }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>

                    {{-- Hari Acuan --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark" style="font-size: 0.875rem;">
                            Hari Acuan:
                        </label>
                        <select name="kategori_hari" id="salinKategoriHari" class="form-select rounded-3" required>
                            <option value="semua">Semua Hari (Senin–Kamis & Jumat)</option>
                            <option value="Senin-Kamis" {{ $tab === 'Senin-Kamis' ? 'selected' : '' }}>Hanya Senin – Kamis</option>
                            <option value="Jumat" {{ $tab === 'Jumat' ? 'selected' : '' }}>Hanya Jumat</option>
                        </select>
                    </div>

                    <div class="alert alert-warning border-0 rounded-3 mb-0" style="font-size: 0.8rem;">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        Seluruh slot jam yang ada pada <strong>Kelas {{ $tingkat }}</strong> untuk hari yang dipilih akan ditimpa dengan struktur dari tingkat acuan.
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4 fw-semibold">
                        <i class="bi bi-check-lg me-1"></i> Terapkan Salin
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ===================== MODAL TAMBAH JAM ===================== --}}
<div class="modal fade" id="modalTambahJam" tabindex="-1" aria-labelledby="modalTambahJamTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <form method="POST" action="{{ route('kurikulum.jam-pelajaran.store') }}" id="formTambahJam">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="modalTambahJamTitle">
                        <i class="bi bi-plus-circle-fill text-primary me-2"></i>Tambah Jam Pelajaran
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    {{-- Tingkat Kelas --}}
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold text-dark" style="font-size: 0.875rem;">Tingkat Kelas</label>
                            <select name="tingkat" id="tambahTingkat" class="form-select rounded-3" required>
                                @foreach($tingkatList as $t)
                                    <option value="{{ $t }}" {{ $tingkat === $t ? 'selected' : '' }}>Kelas {{ $t }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold text-dark" style="font-size: 0.875rem;">Kategori Hari</label>
                            <select name="kategori_hari" id="tambahKategoriHari" class="form-select rounded-3" required>
                                <option value="Senin-Kamis" {{ $tab === 'Senin-Kamis' ? 'selected' : '' }}>Senin – Kamis</option>
                                <option value="Jumat" {{ $tab === 'Jumat' ? 'selected' : '' }}>Jumat</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold text-dark" style="font-size: 0.875rem;">Jam Mulai</label>
                            <input type="time" name="jam_mulai" id="tambahJamMulai" class="form-control rounded-3" step="60" autocomplete="off" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold text-dark" style="font-size: 0.875rem;">Jam Selesai</label>
                            <input type="time" name="jam_selesai" id="tambahJamSelesai" class="form-control rounded-3" step="60" autocomplete="off" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark" style="font-size: 0.875rem;">Jenis Slot</label>
                        <select name="jenis" id="tambahJenis" class="form-select rounded-3" required>
                            <option value="kbm">KBM (Kegiatan Belajar Mengajar)</option>
                            <option value="upacara">Upacara</option>
                            <option value="pembiasaan">Pembiasaan</option>
                            <option value="istirahat">Istirahat</option>
                        </select>
                        <div class="form-text text-muted" style="font-size: 0.78rem;">
                            Penomoran Jam Ke- (Jam 1, Jam 2, dst.) dan penamaan Istirahat dihitung otomatis berurutan per tingkat kelas.
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4 fw-semibold" id="btnSubmitTambah">
                        <i class="bi bi-check-lg me-1"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ===================== MODAL EDIT JAM ===================== --}}
<div class="modal fade" id="modalEditJam" tabindex="-1" aria-labelledby="modalEditJamTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <form method="POST" id="formEditJam" action="">
                @csrf
                @method('PUT')
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="modalEditJamTitle">
                        <i class="bi bi-pencil-square text-warning me-2"></i>Edit Jam Pelajaran
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold text-dark" style="font-size: 0.875rem;">Tingkat Kelas</label>
                            <select name="tingkat" id="editTingkat" class="form-select rounded-3" required>
                                @foreach($tingkatList as $t)
                                    <option value="{{ $t }}">Kelas {{ $t }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold text-dark" style="font-size: 0.875rem;">Kategori Hari</label>
                            <select name="kategori_hari" id="editKategoriHari" class="form-select rounded-3" required>
                                <option value="Senin-Kamis">Senin – Kamis</option>
                                <option value="Jumat">Jumat</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold text-dark" style="font-size: 0.875rem;">Jam Mulai</label>
                            <input type="time" name="jam_mulai" id="editJamMulai" class="form-control rounded-3" step="60" autocomplete="off" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold text-dark" style="font-size: 0.875rem;">Jam Selesai</label>
                            <input type="time" name="jam_selesai" id="editJamSelesai" class="form-control rounded-3" step="60" autocomplete="off" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark" style="font-size: 0.875rem;">Jenis Slot</label>
                        <select name="jenis" id="editJenis" class="form-select rounded-3" required>
                            <option value="kbm">KBM (Kegiatan Belajar Mengajar)</option>
                            <option value="upacara">Upacara</option>
                            <option value="pembiasaan">Pembiasaan</option>
                            <option value="istirahat">Istirahat</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning text-white rounded-3 px-4 fw-semibold" id="btnSubmitEdit">
                        <i class="bi bi-check-lg me-1"></i> Perbarui
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Matikan submit form otomatis saat menekan tombol Enter di input field modal
        const forms = [
            document.getElementById('formTambahJam'),
            document.getElementById('formEditJam'),
            document.getElementById('formSalinTingkat')
        ];

        forms.forEach(function (form) {
            if (!form) return;

            const inputs = form.querySelectorAll('input:not([type="hidden"]), select');
            inputs.forEach(function (input, index) {
                input.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault(); // Jangan submit form secara otomatis

                        // Pindah fokus ke input berikutnya secara berurutan jika ada
                        if (index < inputs.length - 1) {
                            inputs[index + 1].focus();
                        }
                    }
                });
            });
        });
    });

    function openEditModal(id, tingkat, kategoriHari, jamMulai, jamSelesai, jenis) {
        const routeBase = "{{ url('kurikulum/jam-pelajaran') }}";
        document.getElementById('formEditJam').action = routeBase + '/' + id;

        document.getElementById('editTingkat').value      = tingkat;
        document.getElementById('editKategoriHari').value = kategoriHari;
        document.getElementById('editJamMulai').value     = jamMulai;
        document.getElementById('editJamSelesai').value   = jamSelesai;
        document.getElementById('editJenis').value        = jenis;

        const modal = new bootstrap.Modal(document.getElementById('modalEditJam'));
        modal.show();
    }
</script>
@endpush
