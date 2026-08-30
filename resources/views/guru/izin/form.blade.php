@extends('layouts.app')

@section('title', 'Ajukan Izin - Portal Guru')

@section('content')
<div class="container-fluid px-0" style="max-width: 820px;">

    {{-- Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="font-weight: 900; font-size: 1.75rem; letter-spacing: -0.02em;">
                Ajukan Izin
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                Isi alasan izin tidak masuk mengajar beserta tanda tangan digital.
            </p>
        </div>
        <a href="{{ route('guru.izin.index') }}" class="btn btn-outline-secondary rounded-3 px-3 py-2 fw-semibold">
            <i class="bi bi-arrow-left me-1"></i> Kembali
        </a>
    </div>

    {{-- Error --}}
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form method="POST" action="{{ route('guru.izin.store') }}" id="formIzin">
        @csrf

        <div class="table-card-custom mb-4">
            <h5 class="fw-bold text-dark mb-4">
                <i class="bi bi-file-earmark-text me-2 text-primary"></i> Data Pengajuan Izin
            </h5>

            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label fw-semibold text-dark">Tanggal Izin <span class="text-danger">*</span></label>
                    <input type="date" name="tanggal" value="{{ old('tanggal', now()->toDateString()) }}"
                           class="form-control rounded-3" required>
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold text-dark">Alasan Izin <span class="text-danger">*</span></label>
                    <textarea name="alasan" rows="4" maxlength="1000" class="form-control rounded-3" required
                              placeholder="Contoh: Izin menghadiri kegiatan keluarga, sakit, atau urusan dinas...">{{ old('alasan') }}</textarea>
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold text-dark">Tugas untuk Siswa <span class="text-muted fw-normal">(opsional)</span></label>
                    <textarea name="tugas_siswa" rows="3" maxlength="1000" class="form-control rounded-3"
                              placeholder="Materi / tugas pengganti yang diberikan kepada siswa...">{{ old('tugas_siswa') }}</textarea>
                </div>
            </div>
        </div>

        <div class="table-card-custom mb-4">
            <h5 class="fw-bold text-dark mb-2">
                <i class="bi bi-paperclip me-2 text-primary"></i> Lampiran / Bukti Surat
                <span class="text-muted fw-normal small">(opsional)</span>
            </h5>
            <p class="text-muted small mb-3">Unggah foto surat/bukti pendukung (PNG/JPG/PDF).</p>
            <div class="d-flex gap-3 align-items-start flex-wrap">
                <button type="button" class="btn btn-outline-primary rounded-3 fw-semibold" id="btnUnggah">
                    <i class="bi bi-camera me-1"></i> Pilih / Foto Lampiran
                </button>
                <input type="file" id="fileLampiran" accept="image/png,image/jpeg,image/jpg,application/pdf" style="display:none;">
                <input type="hidden" name="lampiran" id="lampiran" value="">
                <div id="previewLampiran" class="d-none border rounded-3 p-2 bg-light-subtle text-center">
                    <span class="text-muted small" id="namaFileLampiran"></span>
                    <button type="button" class="btn btn-sm btn-outline-danger rounded-2 ms-2" id="btnHapusLampiran">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
                <img id="imgPreviewLampiran" class="d-none border rounded-3" style="max-height: 120px;" alt="Pratinjau">
            </div>
        </div>

        <div class="table-card-custom mb-4">
            <h6 class="fw-bold mb-1">
                <i class="bi bi-signature me-1"></i>Tanda Tangan Guru <span class="text-danger">*</span>
            </h6>
            <p class="text-muted small mb-3">
                Goreskan tanda tangan Anda pada area di bawah (mouse / layar sentuh).
            </p>
            <canvas id="canvasTtdGuru" width="600" height="220" class="form-control rounded-3"
                    style="height: auto; touch-action: none; cursor: crosshair;">Browser Anda tidak mendukung Canvas.</canvas>
            <div class="d-flex justify-content-end mt-2">
                <button type="button" id="btnClearTtdGuru" class="btn btn-sm btn-outline-danger rounded-3">
                    <i class="bi bi-eraser me-1"></i> Hapus
                </button>
            </div>
            <input type="hidden" name="ttd_guru" id="ttdGuru" value="">
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('guru.izin.index') }}" class="btn btn-light rounded-3 px-4 py-2 fw-semibold">Batal</a>
            <button type="submit" class="btn btn-primary rounded-3 px-4 py-2 fw-semibold shadow-sm" id="btnSubmit">
                <i class="bi bi-send me-1"></i> Kirim Pengajuan
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const canvas = document.getElementById('canvasTtdGuru');
        const ttdHidden = document.getElementById('ttdGuru');
        const btnClear = document.getElementById('btnClearTtdGuru');
        const formIzin = document.getElementById('formIzin');

        if (canvas) {
            const ctx = canvas.getContext('2d');
            let drawing = false, inked = false, lastX = 0, lastY = 0;

            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.strokeStyle = '#0f172a';
            ctx.lineWidth = 2.5;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';

            function getPos(e) {
                const rect = canvas.getBoundingClientRect();
                return {
                    x: (e.clientX - rect.left) * (canvas.width / rect.width),
                    y: (e.clientY - rect.top) * (canvas.height / rect.height),
                };
            }

            function start(e) { e.preventDefault(); drawing = true; inked = true; const p = getPos(e); lastX = p.x; lastY = p.y; ctx.beginPath(); ctx.moveTo(lastX, lastY); }
            function move(e) { if (!drawing) return; e.preventDefault(); const p = getPos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); lastX = p.x; lastY = p.y; }
            function end(e) { if (!drawing) return; drawing = false; ctx.closePath(); ttdHidden.value = inked ? canvas.toDataURL('image/png') : ''; }

            canvas.addEventListener('pointerdown', start);
            canvas.addEventListener('pointermove', move);
            canvas.addEventListener('pointerup', end);
            canvas.addEventListener('pointercancel', end);
            canvas.addEventListener('touchstart', function (e) { e.preventDefault(); }, { passive: false });

            btnClear.addEventListener('click', function () {
                ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, canvas.width, canvas.height);
                inked = false; ttdHidden.value = '';
            });

            formIzin.addEventListener('submit', function (e) {
                if (!ttdHidden.value) {
                    e.preventDefault();
                    alert('Silakan goreskan tanda tangan Anda terlebih dahulu.');
                }
            });
        }

        // ===== Lampiran =====
        const fileInput = document.getElementById('fileLampiran');
        const btnUnggah = document.getElementById('btnUnggah');
        const previewLampiran = document.getElementById('previewLampiran');
        const namaFileLampiran = document.getElementById('namaFileLampiran');
        const btnHapusLampiran = document.getElementById('btnHapusLampiran');
        const imgPreviewLampiran = document.getElementById('imgPreviewLampiran');

        btnUnggah.addEventListener('click', function () { fileInput.click(); });

        fileInput.addEventListener('change', function () {
            const file = fileInput.files[0];
            if (!file) return;

            if (file.size > 5 * 1024 * 1024) { alert('Ukuran file maksimal 5 MB.'); fileInput.value = ''; return; }

            const reader = new FileReader();
            reader.onload = function (ev) {
                const dataUrl = ev.target.result;
                document.getElementById('lampiran').value = dataUrl;
                namaFileLampiran.textContent = file.name;
                previewLampiran.classList.remove('d-none');
                if (file.type.startsWith('image/')) {
                    imgPreviewLampiran.src = dataUrl;
                    imgPreviewLampiran.classList.remove('d-none');
                } else {
                    imgPreviewLampiran.classList.add('d-none');
                    imgPreviewLampiran.removeAttribute('src');
                }
            };
            reader.readAsDataURL(file);
        });

        btnHapusLampiran.addEventListener('click', function () {
            fileInput.value = '';
            document.getElementById('lampiran').value = '';
            previewLampiran.classList.add('d-none');
            imgPreviewLampiran.classList.add('d-none');
            imgPreviewLampiran.removeAttribute('src');
        });
    });
</script>
@endpush
