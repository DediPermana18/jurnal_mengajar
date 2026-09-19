@extends('layouts.app')

@section('title', 'Presensi Siswa Harian - Guru Piket')

@push('styles')
<style>
    .status-pill + .status-pill {
        margin-left: 6px;
    }
    .status-pill .btn-check:checked + .btn-outline-success {
        background-color: #198754;
        box-shadow: 0 1px 2px rgba(25,135,84,.35);
    }
    .status-pill label {
        padding-left: 14px;
        padding-right: 14px;
        font-weight: 600;
    }
    .status-pill label i {
        margin-right: 4px;
        font-size: 0.8rem;
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">

    {{-- Page Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 800; font-size: 1.75rem;">
                Presensi Siswa Harian
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                Input dan kelola kehadiran siswa per kelas
            </p>
        </div>
        <span class="text-muted small"><i class="bi bi-calendar3 me-1"></i>{{ \Carbon\Carbon::parse($tanggal)->translatedFormat('l, d F Y') }}</span>
    </div>

    {{-- Alert Messages --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4 d-flex align-items-center gap-2" role="alert"
             style="background: #ecfdf5; color: #065f46; font-size: 0.9rem;">
            <i class="bi bi-check-circle-fill text-success fs-5"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4 d-flex align-items-center gap-2" role="alert"
             style="background: #fef2f2; color: #991b1b; font-size: 0.9rem;">
            <i class="bi bi-exclamation-triangle-fill text-danger fs-5"></i>
            <div>{{ session('error') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4 d-flex align-items-center gap-2" role="alert"
             style="background: #fef2f2; color: #991b1b; font-size: 0.9rem;">
            <i class="bi bi-exclamation-triangle-fill text-danger fs-5"></i>
            <div>
                <strong>Periksa kembali presensi:</strong>
                <ul class="mb-0 ps-3 mt-1">
                    @foreach($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Filter Form (Compact Header) --}}
    <form method="GET" action="{{ route('piket.presensi-siswa') }}" class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body py-3">
            <div class="d-flex flex-wrap align-items-center gap-2 gap-md-3">
                <div class="d-flex align-items-center gap-2">
                    <label for="filterKelas" class="form-label fw-semibold text-dark mb-0 text-nowrap" style="font-size: 0.85rem;">
                        <i class="bi bi-journal-text me-1 text-muted"></i>Kelas <span class="text-danger">*</span>
                    </label>
                    <select name="id_kelas" id="filterKelas"
                            class="form-select form-select-sm rounded-3 @error('id_kelas') is-invalid @enderror"
                            style="width: 230px;" required>
                        <option value="">-- Pilih Kelas --</option>
                        @foreach($kelasList as $kelas)
                            <option value="{{ $kelas->id }}" {{ $idKelas == $kelas->id ? 'selected' : '' }}>
                                {{ $kelas->nama_kelas }} ({{ $kelas->jurusan?->nama_jurusan ?? '-' }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <label for="filterTanggal" class="form-label fw-semibold text-dark mb-0 text-nowrap" style="font-size: 0.85rem;">
                        <i class="bi bi-calendar3 me-1 text-muted"></i>Tanggal <span class="text-danger">*</span>
                    </label>
                    <input type="date"
                           name="tanggal"
                           id="filterTanggal"
                           value="{{ $tanggal }}"
                           max="{{ $today }}"
                           class="form-control form-control-sm rounded-3 @error('tanggal') is-invalid @enderror"
                           style="width: 165px;"
                           required>
                </div>

                <div class="d-flex align-items-center gap-2 ms-auto">
                    <button type="submit" class="btn btn-primary btn-sm fw-semibold rounded-3 px-3">
                        <i class="bi bi-search me-1"></i> Tampilkan Siswa
                    </button>
                    @if($idKelas)
                        <a href="{{ route('piket.presensi-siswa') }}" class="btn btn-outline-secondary btn-sm fw-semibold rounded-3 px-3">
                            <i class="bi bi-x-circle me-1"></i> Reset
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </form>

    {{-- Presensi Table --}}
    @if($idKelas)
        <form method="POST" action="{{ route('piket.presensi-siswa.store') }}" id="formPresensi" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="tanggal" value="{{ $tanggal }}">
            <input type="hidden" name="id_kelas" value="{{ $idKelas }}">

            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-bottom rounded-top-4 py-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <h5 class="fw-bold text-dark mb-0">
                            <i class="bi bi-people-fill text-primary me-2"></i>
                            Daftar Siswa - {{ $dataSiswa->first()?->kelas?->nama_kelas ?? 'Kelas' }}
                            <span class="badge bg-light text-dark border rounded-3 ms-1 px-2 py-1" id="countPresent">0/{{ $dataSiswa->count() }} Hadir</span>
                        </h5>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-success rounded-3" onclick="setAllStatus('Hadir')">
                                <i class="bi bi-check-circle me-1"></i> Set Semua Hadir
                            </button>
                            <button type="submit" class="btn btn-sm btn-primary fw-semibold rounded-3">
                                <i class="bi bi-save me-1"></i> Simpan Presensi
                            </button>
                        </div>
                    </div>
                    <div class="text-muted mt-2" style="font-size: 0.78rem;">
                        <i class="bi bi-info-circle me-1"></i>Semua siswa berstatus <strong>Hadir</strong> secara default. Klik pill untuk mengubah status siswa yang tidak hadir saja.
                    </div>
                </div>

                <div class="card-body p-0">
                    @if($dataSiswa->isNotEmpty())
                        <div class="table-responsive w-full overflow-x-auto">
                            <table class="table table-hover align-middle mb-0 min-w-full">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="text-center whitespace-nowrap" style="width: 50px;">NO</th>
                                        <th class="whitespace-nowrap" style="width: 100px;">NISN</th>
                                        <th>NAMA SISWA</th>
                                        <th class="whitespace-nowrap" style="min-width: 340px;">STATUS ABSENSI</th>
                                        <th style="min-width: 200px;">KETERANGAN</th>
                                        <th class="whitespace-nowrap" style="min-width: 140px;">FOTO SURAT</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($dataSiswa as $index => $siswa)
                                        @php
                                            $existing = $presensiExisting->get($siswa->id);
                                            $currentStatus = $existing ? $existing->status : 'Hadir';
                                            $currentKeterangan = $existing ? $existing->keterangan : '';
                                            $existingFoto = $existing ? $existing->foto_surat : null;
                                            $pillConfig = [
                                                'Hadir'  => ['cls' => 'btn-outline-success', 'icon' => 'bi-check-circle-fill'],
                                                'Sakit'  => ['cls' => 'btn-outline-danger',  'icon' => 'bi-heart-pulse-fill'],
                                                'Izin'   => ['cls' => 'btn-outline-primary', 'icon' => 'bi-file-earmark-text-fill'],
                                            ];
                                        @endphp
                                        <tr>
                                            <td class="text-center fw-semibold text-dark whitespace-nowrap">{{ $loop->iteration }}</td>
                                            <td class="text-muted small whitespace-nowrap">{{ $siswa->nisn ?? '-' }}</td>
                                            <td>
                                                <div class="fw-semibold text-dark">{{ $siswa->nama }}</div>
                                                <input type="hidden" name="presensi[{{ $siswa->id }}][id_siswa]" value="{{ $siswa->id }}">
                                            </td>
                                            <td class="status-pill">
                                                <div class="d-flex flex-wrap">
                                                    @foreach($pillConfig as $status => $cfg)
                                                        <span class="position-relative">
                                                            <input class="btn-check"
                                                                   type="radio"
                                                                   name="presensi[{{ $siswa->id }}][status]"
                                                                   id="status_{{ $siswa->id }}_{{ $status }}"
                                                                   value="{{ $status }}"
                                                                   {{ $currentStatus === $status ? 'checked' : '' }}
                                                                   onchange="updateCount(); toggleFotoCell({{ $siswa->id }})"
                                                                   required>
                                                            <label class="btn btn-sm rounded-pill {{ $cfg['cls'] }}"
                                                                   for="status_{{ $siswa->id }}_{{ $status }}">
                                                                <i class="bi {{ $cfg['icon'] }}"></i>{{ $status }}
                                                            </label>
                                                        </span>
                                                    @endforeach
                                                </div>
                                            </td>
                                            <td>
                                                <input type="text"
                                                       name="presensi[{{ $siswa->id }}][keterangan]"
                                                       class="form-control form-control-sm rounded-3"
                                                       value="{{ $currentKeterangan }}"
                                                       placeholder="Catatan (opsional)"
                                                       style="font-size: 0.82rem; max-width: 240px;">
                                            </td>
                                            <td>
                                                <div class="foto-surat-cell d-flex flex-column gap-1"
                                                     data-siswa="{{ $siswa->id }}"
                                                     style="{{ in_array($currentStatus, ['Sakit', 'Izin'], true) ? '' : 'display:none;' }}">
                                                    <input type="file"
                                                           accept="image/*"
                                                           name="presensi[{{ $siswa->id }}][foto_surat]"
                                                           class="form-control form-control-sm rounded-3 foto-input"
                                                           data-siswa="{{ $siswa->id }}"
                                                           style="font-size: 0.72rem; max-width: 200px;">
                                                    <img class="foto-thumb rounded-3 border mt-1 d-none"
                                                         data-siswa="{{ $siswa->id }}"
                                                         src=""
                                                         alt="Pratinjau surat"
                                                         style="max-height: 48px; max-width: 90px; object-fit: cover; cursor: zoom-in;">
                                                    @if($existingFoto)
                                                        <div class="d-flex align-items-center gap-1">
                                                            <img src="{{ asset('storage/' . $existingFoto) }}"
                                                                 class="rounded-3 border"
                                                                 alt="Surat tersimpan"
                                                                 style="max-height: 48px; max-width: 90px; object-fit: cover; cursor: zoom-in;"
                                                                 onclick="showImagePreview(this.src, 'Foto Surat - {{ addslashes($siswa->nama) }}')">
                                                            <span class="badge bg-success-subtle text-success border border-success-subtle text-xs" style="font-size: 0.68rem;">
                                                                <i class="bi bi-check-circle me-1"></i>Tersedia
                                                            </span>
                                                        </div>
                                                    @endif
                                                    <span class="text-muted" style="font-size: 0.68rem;">Opsional &mdash; bukti surat Sakit/Izin</span>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="bi bi-people fs-1 d-block mb-2 opacity-50"></i>
                            <p class="text-muted mb-0">Tidak ada siswa aktif di kelas ini.</p>
                        </div>
                    @endif
                </div>
            </div>
        </form>
    @else
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body text-center py-5">
                <i class="bi bi-funnel-fill text-muted mb-3" style="font-size: 3rem;"></i>
                <h5 class="fw-bold text-dark mb-2">Pilih Kelas Terlebih Dahulu</h5>
                <p class="text-muted mb-3">Silakan pilih kelas dan tanggal pada filter di atas untuk menampilkan daftar siswa.</p>
            </div>
        </div>
    @endif

</div>
@endsection

@push('scripts')
<script>
    function setAllStatus(status) {
        document.querySelectorAll('input.btn-check[type="radio"]').forEach(function (radio) {
            if (radio.value === status) {
                radio.checked = true;
            } else {
                radio.checked = false;
            }
        });
        document.querySelectorAll('.foto-surat-cell').forEach(function (cell) {
            toggleFotoCell(cell.dataset.siswa);
        });
        updateCount();
    }

    function updateCount() {
        var total = 0;
        document.querySelectorAll('input.btn-check[type="radio"]:checked').forEach(function (radio) {
            if (radio.value === 'Hadir') total++;
        });
        var badge = document.getElementById('countPresent');
        if (badge) badge.textContent = total + '/' + {{ $dataSiswa->count() }} + ' Hadir';
    }

    // Tampilkan kolom foto surat hanya saat status Sakit/Izin dipilih.
    function toggleFotoCell(siswaId) {
        var radio = document.querySelector('input[name="presensi[' + siswaId + '][status]"]:checked');
        var cell = document.querySelector('.foto-surat-cell[data-siswa="' + siswaId + '"]');
        if (!cell) return;
        var val = radio ? radio.value : 'Hadir';
        if (val === 'Sakit' || val === 'Izin') {
            cell.style.display = '';
        } else {
            cell.style.display = 'none';
        }
    }

    function showImagePreview(url, title) {
        if (!url) return;
        var modal = new bootstrap.Modal(document.getElementById('modalPreviewSurat'));
        document.getElementById('modalPreviewSuratTitle').innerText = title || 'Preview Gambar';
        document.getElementById('previewSuratSrc').src = url;
        modal.show();
    }

    document.addEventListener('DOMContentLoaded', function () {
        updateCount();

        // State awal: tampilkan/sembunyikan kolom foto surat per siswa
        document.querySelectorAll('.foto-surat-cell').forEach(function (cell) {
            toggleFotoCell(cell.dataset.siswa);
        });

        // Pratinjau thumbnail saat file dipilih
        document.querySelectorAll('.foto-input').forEach(function (input) {
            input.addEventListener('change', function () {
                var thumb = document.querySelector('.foto-thumb[data-siswa="' + this.dataset.siswa + '"]');
                if (this.files && this.files[0] && thumb) {
                    var reader = new FileReader();
                    reader.onload = function (e) {
                        thumb.src = e.target.result;
                        thumb.classList.remove('d-none');
                    };
                    reader.readAsDataURL(this.files[0]);
                }
            });
        });
    });
</script>

{{-- Modal Preview Gambar --}}
<div class="modal fade" id="modalPreviewSurat" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-dark text-white border-0 py-3">
                <h5 class="modal-title fw-bold fs-6" id="modalPreviewSuratTitle">Preview Gambar</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-3">
                <img id="previewSuratSrc" src="" alt="Preview" class="img-fluid rounded-3" style="max-height: 70vh; object-fit: contain;">
            </div>
        </div>
    </div>
</div>
@endpush