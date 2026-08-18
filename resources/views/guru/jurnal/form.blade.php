@extends('layouts.app')

@section('title', (isset($jurnal) ? 'Edit Jurnal Mengajar' : 'Isi Jurnal Mengajar') . ' - WebJournal')

@push('styles')
<style>
    .form-section-card {
        background: #ffffff;
        border: 1px solid #e8eef5;
        border-radius: 16px;
        box-shadow: 0 2px 12px rgba(15, 23, 42, 0.05);
        padding: 1.75rem 2rem;
    }

    .presensi-row.hadir-default {
        background-color: #ffffff;
    }

    .presensi-row.tidak-hadir {
        background-color: #fef2f2;
    }

    .readonly-field {
        background-color: #f1f5f9 !important;
        cursor: not-allowed;
    }

    .img-preview-thumbnail {
        width: 90px;
        height: 60px;
        object-fit: cover;
        border-radius: 8px;
        cursor: pointer;
        border: 2px solid #0284c7;
        transition: transform 0.2s ease;
    }

    .img-preview-thumbnail:hover {
        transform: scale(1.05);
    }

    /* FIX Z-INDEX MODAL KAMERA agar tidak terhalang backdrop hitam */
    .modal-camera-custom {
        z-index: 10055 !important;
    }
    .modal-camera-custom .modal-dialog {
        z-index: 10060 !important;
    }
    .modal-backdrop.show {
        z-index: 10050 !important;
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 800; font-size: 1.75rem;">
                {{ isset($jurnal) ? 'Edit Jurnal Mengajar' : 'Form Pengisian Jurnal' }}
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                Jam {{ $jadwal->jamPelajaran?->jam_ke ?? '-' }} &bull; {{ $waktu }} &bull; {{ \Carbon\Carbon::parse($today)->translatedFormat('d F Y') }}
            </p>
        </div>
        <a href="{{ route('guru.jurnal') }}" class="btn btn-light border rounded-3 px-3 py-2 fw-semibold">
            <i class="bi bi-arrow-left me-1"></i> Kembali
        </a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger border-0 rounded-4 shadow-sm mb-4">
            <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i>Terdapat kesalahan pada form:</div>
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ isset($jurnal) ? route('guru.jurnal.update', $jurnal->id) : route('guru.jurnal.store') }}" 
          method="POST" 
          id="formJurnal">
        @csrf
        @if(isset($jurnal))
            @method('PUT')
        @endif
        <input type="hidden" name="id_jadwal" value="{{ $jadwal->id }}">

        {{-- Header Jurnal --}}
        <div class="form-section-card mb-4">
            <h5 class="fw-bold text-dark mb-3">
                <i class="bi bi-journal-text text-primary me-2"></i> Informasi Jurnal Utama
            </h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-secondary small text-uppercase">Nama Kelas</label>
                    <input type="text"
                           class="form-control rounded-3 readonly-field"
                           value="{{ $jadwal->kelas?->nama_kelas ?? '-' }}"
                           readonly
                           disabled>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-secondary small text-uppercase">Mata Pelajaran</label>
                    <input type="text"
                           class="form-control rounded-3 readonly-field"
                           value="{{ $jadwal->mapel?->nama_mapel ?? '-' }}"
                           readonly
                           disabled>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold text-secondary small text-uppercase">
                        Materi Pelajaran / Bahasan <span class="text-danger">*</span>
                    </label>
                    <textarea name="materi"
                               class="form-control rounded-3 @error('materi') is-invalid @enderror"
                               rows="3"
                               placeholder="Tuliskan ringkasan materi pelajaran yang disampaikan..."
                               required>{{ old('materi', $jurnal->materi ?? '') }}</textarea>
                    @error('materi')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-secondary small text-uppercase">
                        Catatan Kejadian Penting (Opsional)
                    </label>
                    <textarea name="catatan_kejadian"
                               class="form-control rounded-3 @error('catatan_kejadian') is-invalid @enderror"
                               rows="3"
                               placeholder="Catat kejadian khusus selama KBM (misal: siswa terlambat, kendala fasilitas)...">{{ old('catatan_kejadian', $jurnal->catatan_kejadian ?? '') }}</textarea>
                    @error('catatan_kejadian')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-secondary small text-uppercase d-block mb-2">
                        Foto Kegiatan KBM (Wajib Kamera Live)
                    </label>
                    
                    <!-- HIDDEN INPUT BASE64 FOTO KEGIATAN -->
                    <input type="hidden" name="foto_kegiatan_camera" id="foto_kegiatan_camera_input">

                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <button type="button" 
                                class="btn btn-primary rounded-3 px-3 fw-semibold"
                                id="btnTriggerFotoKegiatan"
                                onclick="openCameraModal('foto_kegiatan_camera_input', 'preview_kegiatan_thumb', 'Foto Kegiatan KBM')">
                            <i class="bi bi-camera-fill me-1"></i> Ambil Foto via Kamera
                        </button>

                        <!-- CONTAINER THUMBNAIL HASIL JEPRET KAMERA (FORM UTAMA) -->
                        <div id="preview_kegiatan_container" class="{{ (isset($jurnal) && $jurnal->foto_kegiatan) ? '' : 'd-none' }}">
                            <div class="d-flex align-items-center gap-2 p-2 bg-light rounded-3 border">
                                <img id="preview_kegiatan_thumb" 
                                     src="{{ (isset($jurnal) && $jurnal->foto_kegiatan) ? route('jurnal.foto', basename($jurnal->foto_kegiatan)) : '' }}" 
                                     alt="Preview Foto KBM" 
                                     class="img-preview-thumbnail"
                                     onclick="showImagePreview(this.src, 'Foto Kegiatan KBM')">
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 small">
                                    <i class="bi bi-check-circle me-1"></i> Tersedia
                                </span>
                            </div>
                        </div>
                    </div>
                    <small class="text-muted d-block mt-2">Gunakan kamera bawaan HP / webcam perangkat untuk mengambil foto kegiatan secara langsung.</small>
                </div>
            </div>
        </div>

        {{-- Presensi Siswa --}}
        <div class="form-section-card mb-4">
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                <h5 class="fw-bold text-dark mb-0">
                    <i class="bi bi-people-fill text-primary me-2"></i> Presensi Siswa Kelas {{ $jadwal->kelas?->nama_kelas }}
                </h5>
                <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle rounded-pill px-3 py-2">
                    <i class="bi bi-check2-all me-1"></i> Default: Semua Hadir
                </span>
            </div>
            <p class="text-muted small mb-3">
                Centang hanya siswa yang <strong>tidak hadir</strong>. Siswa yang tidak dicentang otomatis tercatat sebagai <strong>Hadir</strong>.
            </p>

            <div class="table-responsive">
                <table class="table table-custom align-middle mb-0" id="tabelPresensi">
                    <thead>
                        <tr>
                            <th style="width: 50px;">No</th>
                            <th style="width: 120px;">NIS</th>
                            <th>Nama Siswa</th>
                            <th style="width: 140px;" class="text-center">Tidak Hadir?</th>
                            <th style="width: 140px;" class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($siswas as $index => $siswa)
                            @php
                                $existingAbsensi = isset($absensiMap) ? ($absensiMap[$siswa->id] ?? null) : null;
                                
                                $isTidakHadir = old("tidak_hadir") 
                                    ? in_array($siswa->id, old("tidak_hadir", []))
                                    : (old("presensi.{$siswa->id}.status") ? true : ($existingAbsensi ? $existingAbsensi->status !== 'Hadir' : false));

                                $currentStatus = old("presensi.{$siswa->id}.status", old("status.{$siswa->id}", $existingAbsensi ? $existingAbsensi->status : 'Sakit'));
                                if ($currentStatus === 'Hadir') { $currentStatus = 'Sakit'; }

                                $currentKeterangan = old("presensi.{$siswa->id}.keterangan", old("keterangan.{$siswa->id}", $existingAbsensi ? $existingAbsensi->keterangan : ''));
                            @endphp

                            <tr class="presensi-row {{ $isTidakHadir ? 'tidak-hadir' : 'hadir-default' }}" id="main_row_{{ $siswa->id }}">
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $siswa->nis }}</td>
                                <td class="fw-semibold">{{ $siswa->nama }}</td>
                                <td class="text-center">
                                    <div class="form-check d-flex justify-content-center">
                                        <input type="checkbox"
                                               class="form-check-input chk-tidak-hadir"
                                               name="tidak_hadir[]"
                                               value="{{ $siswa->id }}"
                                               id="tidak_hadir_{{ $siswa->id }}"
                                               onchange="togglePresensiDetail({{ $siswa->id }}, this.checked)"
                                               {{ $isTidakHadir ? 'checked' : '' }}>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1 fw-bold {{ $isTidakHadir ? 'd-none' : '' }}" id="badge_hadir_{{ $siswa->id }}">
                                        <i class="bi bi-check-circle-fill me-1"></i> Hadir
                                    </span>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3 py-1 fw-bold {{ $isTidakHadir ? '' : 'd-none' }}" id="badge_absen_{{ $siswa->id }}">
                                        <i class="bi bi-x-circle-fill me-1"></i> Tidak Hadir
                                    </span>
                                </td>
                            </tr>
                            <tr class="presensi-detail {{ $isTidakHadir ? '' : 'd-none' }}" id="detail_row_{{ $siswa->id }}">
                                <td colspan="5" class="bg-light rounded-3">
                                    <div class="p-3">
                                        <div class="row g-3 align-items-start">
                                            <!-- 1. Select Dropdown Status -->
                                            <div class="col-md-4">
                                                <label class="form-label fw-semibold small text-uppercase text-secondary">
                                                    Status Ketidakhadiran <span class="text-danger">*</span>
                                                </label>
                                                <select name="presensi[{{ $siswa->id }}][status]"
                                                        id="status_select_{{ $siswa->id }}"
                                                        class="form-select form-select-sm rounded-3 input-detail-{{ $siswa->id }}"
                                                        {{ $isTidakHadir ? '' : 'disabled' }}>
                                                    <option value="Sakit" {{ $currentStatus === 'Sakit' ? 'selected' : '' }}>Sakit (S)</option>
                                                    <option value="Izin" {{ $currentStatus === 'Izin' ? 'selected' : '' }}>Izin (I)</option>
                                                    <option value="Alpa" {{ $currentStatus === 'Alpa' ? 'selected' : '' }}>Alpa (A)</option>
                                                    <option value="Dispen" {{ $currentStatus === 'Dispen' ? 'selected' : '' }}>Dispen (D)</option>
                                                </select>
                                            </div>

                                            <!-- 2. Input Text Keterangan -->
                                            <div class="col-md-4">
                                                <label class="form-label fw-semibold small text-uppercase text-secondary">
                                                    Keterangan / Alasan
                                                </label>
                                                <input type="text"
                                                       name="presensi[{{ $siswa->id }}][keterangan]"
                                                       id="ket_input_{{ $siswa->id }}"
                                                       class="form-control form-control-sm rounded-3 input-detail-{{ $siswa->id }}"
                                                       placeholder="Opsional (misal: Demam, Acara Keluarga)"
                                                       value="{{ $currentKeterangan }}"
                                                       {{ $isTidakHadir ? '' : 'disabled' }}>
                                            </div>

                                            <!-- 3. Foto Surat Izin via Kamera Live -->
                                            <div class="col-md-4">
                                                <label class="form-label fw-semibold small text-uppercase text-secondary d-block mb-1">
                                                    Foto Surat Izin / Dokter
                                                </label>

                                                <!-- HIDDEN INPUT BASE64 FOTO SURAT SISWA -->
                                                <input type="hidden" 
                                                       name="presensi[{{ $siswa->id }}][foto_surat_camera]" 
                                                       id="foto_surat_camera_input_{{ $siswa->id }}"
                                                       class="input-detail-{{ $siswa->id }}"
                                                       {{ $isTidakHadir ? '' : 'disabled' }}>

                                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                                    <button type="button" 
                                                            class="btn btn-sm btn-outline-primary rounded-3 input-detail-{{ $siswa->id }}"
                                                            id="btn_trigger_surat_{{ $siswa->id }}"
                                                            onclick="openCameraModal('foto_surat_camera_input_{{ $siswa->id }}', 'preview_surat_thumb_{{ $siswa->id }}', 'Foto Surat - {{ addslashes($siswa->nama) }}')"
                                                            {{ $isTidakHadir ? '' : 'disabled' }}>
                                                        <i class="bi bi-camera-fill me-1"></i> Kamera Live
                                                    </button>

                                                    <!-- THUMBNAIL SURAT SISWA -->
                                                    <div id="preview_surat_container_{{ $siswa->id }}" 
                                                         class="{{ ($existingAbsensi && $existingAbsensi->foto_surat) ? '' : 'd-none' }}">
                                                        <img id="preview_surat_thumb_{{ $siswa->id }}" 
                                                             src="{{ ($existingAbsensi && $existingAbsensi->foto_surat) ? asset('storage/' . $existingAbsensi->foto_surat) : '' }}" 
                                                             alt="Surat {{ $siswa->nama }}" 
                                                             class="img-preview-thumbnail"
                                                             style="width: 60px; height: 45px;"
                                                             onclick="showImagePreview(this.src, 'Foto Surat Izin - {{ addslashes($siswa->nama) }}')">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    Tidak ada siswa aktif di kelas ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('guru.jurnal') }}" class="btn btn-light border rounded-3 px-4 py-2 fw-semibold">
                Batal
            </a>
            <button type="submit" class="btn btn-primary rounded-3 px-4 py-2 fw-semibold" {{ $siswas->isEmpty() ? 'disabled' : '' }}>
                <i class="bi bi-save me-1"></i> {{ isset($jurnal) ? 'Simpan Perubahan Jurnal' : 'Simpan Jurnal & Presensi' }}
            </button>
        </div>
    </form>
</div>

<!-- ================= MODAL PREVIEW GAMBAR POP-UP ================= -->
<div class="modal fade" id="modalPreviewGambar" tabindex="-1" aria-labelledby="modalPreviewGambarTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="modal-title fw-bold text-dark" id="modalPreviewGambarTitle">Preview Gambar</h5>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-outline-primary rounded-3 px-2 py-1 fw-semibold d-flex align-items-center gap-1" onclick="toggleFullscreenFoto('previewGambarSrc')">
                        <i class="bi bi-arrows-angle-expand"></i> Fullscreen
                    </button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body text-center py-4">
                <img id="previewGambarSrc" src="" alt="Preview Gambar" class="img-fluid rounded-3 shadow-sm" style="max-height: 500px; object-fit: contain;">
            </div>
            <div class="modal-footer border-0 pt-0 justify-content-end">
                <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- ================= MODAL LIVE WEBCAM / KAMERA (KONDISI A & B) ================= -->
<div class="modal fade modal-camera-custom" id="modalWebcamKamera" tabindex="-1" aria-labelledby="modalWebcamKameraTitle" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white border-0 py-3">
                <h5 class="modal-title fw-bold fs-6" id="modalWebcamKameraTitle">
                    <i class="bi bi-camera-video text-primary me-2"></i> Ambil Foto Kamera
                </h5>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-outline-light rounded-3" id="btnSwitchCamera" onclick="switchCamera()" title="Tukar Kamera Depan / Belakang">
                        <i class="bi bi-arrow-repeat me-1"></i> 🔄 Switch Kamera
                    </button>
                    <button type="button" class="btn-close btn-close-white" onclick="closeCameraModal()" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body bg-black text-center p-3">
                <!-- VIEW CONTAINER: KONDISI A (LIVE VIDEO) & KONDISI B (PREVIEW CANVAS/IMAGE) -->
                <div class="position-relative bg-dark rounded-3 overflow-hidden d-inline-block w-100 shadow" style="min-height: 320px; max-height: 450px;">
                    <!-- KONDISI A: VIDEO LIVE STREAM -->
                    <video id="webcamVideo" autoplay playsinline class="w-100 h-100" style="max-height: 450px; object-fit: contain;"></video>
                    
                    <!-- KONDISI B: CANVAS & PREVIEW IMAGE HASIL JEPRET -->
                    <canvas id="webcamCanvas" class="d-none"></canvas>
                    <img id="webcamPreviewImg" src="" alt="Hasil Jepret Kamera" class="w-100 h-100 d-none" style="max-height: 450px; object-fit: contain;">
                </div>
                <div class="mt-2 text-white-50 small" id="cameraStatusText">
                    <i class="bi bi-info-circle me-1"></i> Posisikan kamera dengan jelas, lalu tekan <strong>Jepret Foto</strong>.
                </div>
            </div>
            <div class="modal-footer bg-dark border-0 py-3 justify-content-between">
                <button type="button" class="btn btn-outline-light rounded-3 px-4" onclick="closeCameraModal()">Batal</button>
                
                <!-- TOMBOL KONDISI A: JEPRET FOTO -->
                <button type="button" class="btn btn-primary rounded-3 px-4 fw-bold" id="btnJepretFoto" onclick="captureWebcamPhoto()">
                    <i class="bi bi-camera me-1"></i> 📸 Jepret Foto
                </button>

                <!-- TOMBOL KONDISI B: FOTO ULANG & GUNAKAN FOTO (SEMBUNYI SAAT KONDISI A) -->
                <div id="groupButtonsKondisiB" class="d-none gap-2">
                    <button type="button" class="btn btn-warning rounded-3 px-3 fw-semibold text-dark" onclick="resetToKondisiA()">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> 🔄 Foto Ulang
                    </button>
                    <button type="button" class="btn btn-success rounded-3 px-4 fw-bold" onclick="useCapturedPhoto()">
                        <i class="bi bi-check-circle me-1"></i> ✅ Gunakan Foto
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function togglePresensiDetail(siswaId, isChecked) {
        const mainRow = document.getElementById('main_row_' + siswaId);
        const detailRow = document.getElementById('detail_row_' + siswaId);
        const badgeHadir = document.getElementById('badge_hadir_' + siswaId);
        const badgeAbsen = document.getElementById('badge_absen_' + siswaId);
        const inputs = document.querySelectorAll('.input-detail-' + siswaId);

        if (isChecked) {
            if (mainRow) {
                mainRow.classList.remove('hadir-default');
                mainRow.classList.add('tidak-hadir');
            }
            if (detailRow) detailRow.classList.remove('d-none');
            if (badgeHadir) badgeHadir.classList.add('d-none');
            if (badgeAbsen) badgeAbsen.classList.remove('d-none');

            inputs.forEach(input => {
                input.disabled = false;
            });
        } else {
            if (mainRow) {
                mainRow.classList.add('hadir-default');
                mainRow.classList.remove('tidak-hadir');
            }
            if (detailRow) detailRow.classList.add('d-none');
            if (badgeHadir) badgeHadir.classList.remove('d-none');
            if (badgeAbsen) badgeAbsen.classList.add('d-none');

            inputs.forEach(input => {
                input.disabled = true;
                if (input.tagName === 'SELECT') {
                    input.value = 'Sakit';
                } else if (input.type === 'text' || input.type === 'hidden') {
                    input.value = '';
                }
            });
        }
    }

    function showImagePreview(url, title = 'Preview Gambar') {
        document.getElementById('modalPreviewGambarTitle').innerText = title;
        document.getElementById('previewGambarSrc').src = url;
        const modal = new bootstrap.Modal(document.getElementById('modalPreviewGambar'));
        modal.show();
    }

    function toggleFullscreenFoto(imgId) {
        const img = document.getElementById(imgId);
        if (!img) return;

        if (img.requestFullscreen) {
            img.requestFullscreen();
        } else if (img.webkitRequestFullscreen) {
            img.webkitRequestFullscreen();
        } else if (img.msRequestFullscreen) {
            img.msRequestFullscreen();
        }
    }

    // ================= LIVE WEBCAM KAMERA & SWITCH CAMERA =================
    let currentTargetInputId = null;
    let currentTargetThumbImgId = null;
    let currentWebcamStream = null;
    let currentCapturedBase64 = null;
    let currentFacingMode = 'user'; // Default: Kamera Depan

    function startCameraStream(facingMode = 'user') {
        if (currentWebcamStream) {
            currentWebcamStream.getTracks().forEach(track => track.stop());
            currentWebcamStream = null;
        }

        const video = document.getElementById('webcamVideo');

        const constraints = {
            video: {
                facingMode: { ideal: facingMode },
                width: { ideal: 1280 },
                height: { ideal: 720 }
            }
        };

        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            navigator.mediaDevices.getUserMedia(constraints)
                .then(function(stream) {
                    currentWebcamStream = stream;
                    video.srcObject = stream;
                    video.play();
                })
                .catch(function(err) {
                    console.warn('Gagal memuat mode kamera ' + facingMode + ', mencoba mode fallback:', err);
                    // Fallback jika facingMode spesifik gagal
                    navigator.mediaDevices.getUserMedia({ video: true })
                        .then(function(stream) {
                            currentWebcamStream = stream;
                            video.srcObject = stream;
                            video.play();
                        })
                        .catch(function(fallbackErr) {
                            alert('Tidak dapat mengakses kamera: ' + fallbackErr.message + '\nSilakan periksa izin kamera pada browser/HP Anda.');
                        });
                });
        } else {
            alert('Browser Anda tidak mendukung fitur kamera live HTML5.');
        }
    }

    function switchCamera() {
        currentFacingMode = (currentFacingMode === 'user') ? 'environment' : 'user';
        const modeLabel = (currentFacingMode === 'user') ? 'Kamera Depan' : 'Kamera Belakang';
        const statusText = document.getElementById('cameraStatusText');
        if (statusText) {
            statusText.innerHTML = '<i class="bi bi-camera-fill me-1"></i> Mengalihkan ke <strong>' + modeLabel + '</strong>...';
        }
        startCameraStream(currentFacingMode);
    }

    function openCameraModal(targetInputId, targetThumbImgId, title = 'Ambil Foto Kamera') {
        currentTargetInputId = targetInputId;
        currentTargetThumbImgId = targetThumbImgId;
        document.getElementById('modalWebcamKameraTitle').innerHTML = '<i class="bi bi-camera-video text-primary me-2"></i> ' + title;
        
        resetToKondisiA();
        startCameraStream(currentFacingMode);

        const modalEl = document.getElementById('modalWebcamKamera');
        let modal = bootstrap.Modal.getInstance(modalEl);
        if (!modal) {
            modal = new bootstrap.Modal(modalEl);
        }
        modal.show();
    }

    // KONDISI A: LIVE STREAM KAMERA ON
    function resetToKondisiA() {
        currentCapturedBase64 = null;
        const video = document.getElementById('webcamVideo');
        const previewImg = document.getElementById('webcamPreviewImg');
        const btnJepret = document.getElementById('btnJepretFoto');
        const btnGroupB = document.getElementById('groupButtonsKondisiB');
        const statusText = document.getElementById('cameraStatusText');

        video.classList.remove('d-none');
        previewImg.classList.add('d-none');
        previewImg.src = '';

        btnJepret.classList.remove('d-none');
        btnGroupB.classList.add('d-none');
        btnGroupB.classList.remove('d-flex');

        const modeLabel = (currentFacingMode === 'user') ? 'Kamera Depan' : 'Kamera Belakang';
        statusText.innerHTML = '<i class="bi bi-info-circle me-1"></i> Mode: <strong>' + modeLabel + '</strong>. Posisikan kamera dengan jelas, lalu tekan <strong>📸 Jepret Foto</strong>.';

        if (video.paused && currentWebcamStream) {
            video.play();
        }
    }

    // JEPRET FOTO -> PINDAH KE KONDISI B (FREEZE / PREVIEW)
    function captureWebcamPhoto() {
        const video = document.getElementById('webcamVideo');
        const canvas = document.getElementById('webcamCanvas');
        const previewImg = document.getElementById('webcamPreviewImg');
        const btnJepret = document.getElementById('btnJepretFoto');
        const btnGroupB = document.getElementById('groupButtonsKondisiB');
        const statusText = document.getElementById('cameraStatusText');

        if (!video.videoWidth) return;

        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

        currentCapturedBase64 = canvas.toDataURL('image/jpeg', 0.85);

        // Tampilkan preview gambar
        previewImg.src = currentCapturedBase64;
        previewImg.classList.remove('d-none');
        video.classList.add('d-none');

        // Ganti tombol ke KONDISI B
        btnJepret.classList.add('d-none');
        btnGroupB.classList.remove('d-none');
        btnGroupB.classList.add('d-flex');

        statusText.innerHTML = '<i class="bi bi-check-circle-fill text-success me-1"></i> Foto berhasil diambil! Pilih <strong>Gunakan Foto</strong> jika sudah sesuai atau <strong>Foto Ulang</strong>.';
    }

    // KONDISI B: USER TEKAN "GUNAKAN FOTO" -> SIMPAN BASE64 KE HIDDEN INPUT & TUTUP MODAL
    function useCapturedPhoto() {
        if (!currentCapturedBase64 || !currentTargetInputId) return;

        // Set Base64 DataURL ke hidden input
        const targetInput = document.getElementById(currentTargetInputId);
        if (targetInput) {
            targetInput.value = currentCapturedBase64;
        }

        // Set preview thumbnail di form utama
        if (currentTargetThumbImgId) {
            const thumbImg = document.getElementById(currentTargetThumbImgId);
            if (thumbImg) {
                thumbImg.src = currentCapturedBase64;
                const container = thumbImg.closest('.d-none');
                if (container) container.classList.remove('d-none');
            }
        }

        closeCameraModal();
    }

    // TUTUP MODAL & HENTIKAN STREAM KAMERA
    function closeCameraModal() {
        if (currentWebcamStream) {
            currentWebcamStream.getTracks().forEach(track => track.stop());
            currentWebcamStream = null;
        }
        const modalEl = document.getElementById('modalWebcamKamera');
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
    }
</script>
@endpush
