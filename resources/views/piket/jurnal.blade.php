@extends('layouts.app')

@section('title', 'Jurnal KBM Harian - WebJournal')

@section('content')
<div class="container-fluid px-0">

    <!-- HEADER -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 800; font-size: 1.75rem;">
                Jurnal KBM Harian (Guru Piket)
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                Pantau jurnal KBM dan kelola Guru Pengganti / Piket per tanggal.
            </p>
        </div>

        <!-- Filter Tanggal -->
        <form method="GET" action="{{ route('piket.jurnal') }}" class="d-flex align-items-center gap-2">
            <label class="text-muted fw-semibold small mb-0"><i class="bi bi-calendar3 me-1"></i>Tanggal:</label>
            <input type="date"
                   name="tanggal"
                   value="{{ $tanggal }}"
                   max="{{ $today }}"
                   class="form-control form-control-sm rounded-3"
                   style="width: auto;"
                   onchange="this.form.submit()">
        </form>
    </div>

    <!-- INFO BAR: TANGGAL YANG DILIHAT -->
    <div class="alert border-0 rounded-4 mb-4 py-3 px-4 d-flex align-items-center gap-3
        {{ $tanggal === $today ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}"
         role="alert">
        <i class="bi {{ $tanggal === $today ? 'bi-unlock-fill' : 'bi-lock-fill' }} fs-5"></i>
        <div>
            @if($tanggal === $today)
                <strong>Hari Ini – {{ \Carbon\Carbon::parse($today)->translatedFormat('l, d F Y') }}</strong><br>
                <span class="small">Jurnal hari ini <strong>dapat diperbarui</strong> oleh Guru Piket / TU.</span>
            @else
                <strong>{{ \Carbon\Carbon::parse($tanggal)->translatedFormat('l, d F Y') }}</strong><br>
                <span class="small">Jurnal tanggal ini <strong>sudah terkunci</strong> (Read-Only).</span>
            @endif
        </div>
    </div>

    <!-- ALERT SUCCESS / ERROR -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- TABEL JURNAL -->
    <div class="table-card-custom mb-4">
        <div class="table-responsive w-full overflow-x-auto">
            <table class="table table-custom align-middle min-w-full">
                <thead>
                    <tr>
                        <th style="width: 11%;">WAKTU</th>
                        <th style="width: 22%;">GURU ASLI & STATUS</th>
                        <th style="width: 11%;">KELAS</th>
                        <th style="width: 15%;">MATA PELAJARAN</th>
                        <th style="width: 28%;">MATERI / CATATAN</th>
                        <th style="width: 11%; text-align: center;" class="whitespace-nowrap">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dataJurnal as $jurnal)
                        @php
                            $statusClass = match($jurnal->status_kehadiran) {
                                'Izin' => 'bg-warning-subtle text-warning-emphasis border-warning-subtle',
                                'Sakit' => 'bg-danger-subtle text-danger border-danger-subtle',
                                'Disposisi' => 'bg-info-subtle text-info-emphasis border-info-subtle',
                                default => 'bg-success-subtle text-success border-success-subtle',
                            };
                        @endphp
                        <tr class="{{ !$jurnal->is_editable ? 'opacity-75' : '' }}">

                            <!-- Waktu Jam Pelajaran -->
                            <td>
                                <div class="fw-semibold text-dark" style="font-size: 0.88rem;">
                                    @if($jurnal->jadwal && $jurnal->jadwal->jamPelajaran)
                                        {{ \Carbon\Carbon::parse($jurnal->jadwal->jamPelajaran->jam_mulai)->format('H:i') }}
                                        –
                                        {{ \Carbon\Carbon::parse($jurnal->jadwal->jamPelajaran->jam_selesai)->format('H:i') }}
                                    @else
                                        -
                                    @endif
                                </div>
                                <div class="text-muted" style="font-size: 0.75rem;">
                                    {{ \Carbon\Carbon::parse($jurnal->waktu_isi)->format('H:i') }} WIB
                                </div>
                            </td>

                            <!-- Guru Asli & Status -->
                            <td>
                                <div class="fw-semibold text-dark" style="font-size: 0.88rem;">
                                    {{ $jurnal->guru->nama ?? $jurnal->jadwal->guru->nama ?? '-' }}
                                </div>
                                <div class="mt-1">
                                    <span class="badge border rounded-pill px-2 py-1 small whitespace-nowrap {{ $statusClass }}">
                                        <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i> {{ $jurnal->status_kehadiran ?? 'Hadir' }}
                                    </span>
                                    @if($jurnal->guruPengganti)
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-2 py-1 small ms-1" title="Digantikan oleh Guru Piket">
                                            <i class="bi bi-person-fill-gear me-1"></i> Pengganti: {{ $jurnal->guruPengganti->nama }}
                                        </span>
                                    @endif
                                </div>
                            </td>

                            <!-- Kelas -->
                            <td>
                                <span class="fw-semibold text-dark" style="font-size: 0.88rem;">
                                    {{ $jurnal->jadwal->kelas->nama_kelas ?? '-' }}
                                </span>
                            </td>

                            <!-- Mata Pelajaran -->
                            <td>
                                <span class="text-dark" style="font-size: 0.88rem;">
                                    {{ $jurnal->jadwal->mapel->nama_mapel ?? '-' }}
                                </span>
                            </td>

                            <!-- Materi & Catatan Kejadian -->
                            <td>
                                <div class="text-dark" style="font-size: 0.85rem; max-width: 400px;">
                                    {{ \Illuminate\Support\Str::limit($jurnal->materi, 80) }}
                                </div>
                                @if($jurnal->catatan_kejadian)
                                    <div class="text-warning small mt-1">
                                        <i class="bi bi-exclamation-triangle-fill me-1"></i>{{ \Illuminate\Support\Str::limit($jurnal->catatan_kejadian, 50) }}
                                    </div>
                                @endif
                                @if($jurnal->foto_kegiatan)
                                    <div class="mt-1">
                                        <a href="{{ route('jurnal.foto', basename($jurnal->foto_kegiatan)) }}"
                                           data-image-preview="{{ route('jurnal.foto', basename($jurnal->foto_kegiatan)) }}"
                                           data-image-title="Foto Kegiatan KBM" class="badge bg-info-subtle text-info-emphasis border text-decoration-none"
                                           style="font-size: 0.72rem; cursor: pointer;">
                                            <i class="bi bi-image me-1"></i> Foto KBM
                                        </a>
                                    </div>
                                @endif
                            </td>

                            <!-- Kolom Aksi / Status Edit Piket -->
                            <td class="text-center whitespace-nowrap">
                                <div class="flex items-center justify-center gap-2 whitespace-nowrap">
                                @if($jurnal->is_editable)
                                    <button type="button"
                                            class="btn btn-sm btn-primary rounded-3 px-2 py-1 shadow-sm fw-semibold"
                                            style="font-size: 0.78rem;"
                                            onclick="openModalEditPiket({{ $jurnal->id }}, '{{ addslashes($jurnal->guru->nama ?? $jurnal->jadwal->guru->nama ?? '') }}', '{{ $jurnal->status_kehadiran ?? 'Hadir' }}', '{{ $jurnal->id_guru_pengganti ?? '' }}', '{{ addslashes($jurnal->catatan_kejadian ?? '') }}')">
                                        <i class="bi bi-pencil-square"></i> Edit Piket
                                    </button>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2 py-1 small">
                                        <i class="bi bi-lock-fill"></i> Terkunci
                                    </span>
                                @endif
                                </div>
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-journal-x fs-1 d-block mb-2 text-secondary"></i>
                                Tidak ada jurnal yang ditemukan untuk tanggal <strong>{{ \Carbon\Carbon::parse($tanggal)->translatedFormat('d F Y') }}</strong>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- FOOTER COUNT -->
        <div class="pt-3 border-top text-muted small d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                Menampilkan <strong>{{ $dataJurnal->count() }}</strong> jurnal untuk tanggal
                <strong>{{ \Carbon\Carbon::parse($tanggal)->translatedFormat('d F Y') }}</strong>.
            </div>
        </div>
    </div>

</div>

<!-- ================= MODAL EDIT QUICK-PIKET (SPLIT PIKET & GURU PENGGANTI) ================= -->
<div class="modal fade" id="modalEditPiket" tabindex="-1" aria-labelledby="modalEditPiketLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold fs-6" id="modalEditPiketLabel">
                    <i class="bi bi-person-fill-gear me-2"></i> Update Status & Guru Pengganti Piket
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formEditPiket" method="POST" action="">
                @csrf
                @method('PUT')
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small text-uppercase">Guru Asli Pengajar</label>
                        <input type="text" id="piket_nama_guru_asli" class="form-control rounded-3 bg-light" readonly disabled>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Status Kehadiran Guru <span class="text-danger">*</span></label>
                        <select name="status_kehadiran" id="piket_status_kehadiran" class="form-select rounded-3 py-2" required>
                            <option value="Hadir">Hadir</option>
                            <option value="Izin">Izin</option>
                            <option value="Sakit">Sakit</option>
                            <option value="Disposisi">Disposisi / Dinas Out</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Guru Pengganti (Piket)</label>
                        <select name="id_guru_pengganti" id="piket_id_guru_pengganti" class="form-select rounded-3 py-2">
                            <option value="">-- Pilih Guru Pengganti / Piket --</option>
                            @if(isset($gurus))
                                @foreach($gurus as $guru)
                                    <option value="{{ $guru->id }}">{{ $guru->nama }} ({{ $guru->role }})</option>
                                @endforeach
                            @endif
                        </select>
                        <small class="text-muted d-block mt-1">Jika status Guru Asli Izin/Sakit/Disposisi, tentukan Guru Piket yang menggantikan di kelas.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Catatan Kejadian Piket (Opsional)</label>
                        <textarea name="catatan_kejadian" id="piket_catatan_kejadian" class="form-control rounded-3" rows="3" placeholder="Tuliskan catatan piket atau alasan pergantian guru..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-3 justify-content-between">
                    <button type="button" class="btn btn-outline-secondary rounded-3 px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4 fw-semibold">
                        <i class="bi bi-save me-1"></i> Simpan Perubahan Piket
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================= MODAL PREVIEW GAMBAR (LIGHTBOX) ================= -->
<div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-labelledby="imagePreviewModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold text-dark fs-6" id="imagePreviewModalTitle">
                    <i class="bi bi-image me-2 text-primary"></i> Foto / Dokumentasi
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-2 bg-dark-subtle text-center">
                <img id="imagePreviewModalSrc" src="" alt="Preview Foto"
                     class="img-fluid rounded mx-auto d-block"
                     style="max-height: 80vh; width: auto; max-width: 100%; object-fit: contain;">
            </div>
            <div class="modal-footer border-0 justify-content-end">
                <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        // ===== Lightbox Preview Foto (Modal Pop-up) =====
        var titleEl = document.getElementById('imagePreviewModalTitle');
        var imgEl   = document.getElementById('imagePreviewModalSrc');
        var modalEl = document.getElementById('imagePreviewModal');

        document.querySelectorAll('[data-image-preview]').forEach(function (el) {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                if (imgEl) imgEl.src = el.getAttribute('data-image-preview');
                if (titleEl) titleEl.innerHTML = '<i class="bi bi-image me-2 text-primary"></i> ' +
                    (el.getAttribute('data-image-title') || 'Foto / Dokumentasi');
                if (modalEl && window.bootstrap && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                }
            });
        });
    })();

    function openModalEditPiket(jurnalId, namaGuruAsli, statusKehadiran, idGuruPengganti, catatanKejadian) {
        const form = document.getElementById('formEditPiket');
        form.action = "{{ url('/piket/jurnal') }}/" + jurnalId + "/update-piket";

        document.getElementById('piket_nama_guru_asli').value = namaGuruAsli;
        document.getElementById('piket_status_kehadiran').value = statusKehadiran || 'Hadir';
        document.getElementById('piket_id_guru_pengganti').value = idGuruPengganti || '';
        document.getElementById('piket_catatan_kejadian').value = catatanKejadian || '';

        const modal = new bootstrap.Modal(document.getElementById('modalEditPiket'));
        modal.show();
    }
</script>
@endpush
@endsection
