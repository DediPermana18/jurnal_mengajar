@extends('layouts.app')

@section('title', 'Buat Dispensasi Siswa - Guru Piket')

@section('content')
<div class="container-fluid px-0">

    {{-- Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <a href="{{ route('piket.dispensasi.index') }}" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar Dispensasi</a>
            <h2 class="fw-black text-dark mt-2 mb-1" style="letter-spacing: -0.02em; font-weight: 800; font-size: 1.75rem;">
                Form Dispensasi Siswa
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                Ajukan dispensasi siswa. Pengajuan berstatus <strong>pending</strong> menunggu persetujuan Guru Piket / Kesiswaan sebelum surat resmi diterbitkan.
            </p>
        </div>
    </div>

    {{-- Alert Error --}}
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4 d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-exclamation-triangle-fill text-danger fs-5"></i>
            <div>
                <ul class="mb-0 ps-3">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
        <form action="{{ route('piket.dispensasi.store') }}" method="POST" id="formDispen">
            @csrf

            <div class="row g-4">
                {{-- Tanggal & Siswa --}}
                <div class="col-md-6">
                    <label class="form-label fw-bold text-secondary text-uppercase small">Tanggal Dispen <span class="text-danger">*</span></label>
                    <input type="date" name="tanggal" value="{{ old('tanggal', now()->toDateString()) }}" max="{{ now()->toDateString() }}" class="form-control rounded-3" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold text-secondary text-uppercase small">Cari Nama Siswa <span class="text-danger">*</span></label>
                    <input type="text" id="searchSiswa" class="form-control rounded-3 mb-2" placeholder="Ketik nama / NISN siswa untuk memfilter...">
                    <select name="id_siswa" id="id_siswa" class="form-select rounded-3" required>
                        <option value="">-- Pilih Siswa --</option>
                        @foreach($dataSiswa->groupBy(fn ($s) => $s->kelas?->nama ?? 'Tanpa Kelas') as $namaKelas => $siswaKelas)
                            <optgroup label="{{ $namaKelas }}">
                                @foreach($siswaKelas as $siswa)
                                    <option value="{{ $siswa->id }}" {{ old('id_siswa') == $siswa->id ? 'selected' : '' }}
                                            data-nama="{{ strtolower($siswa->nama) }}" data-nisn="{{ $siswa->nisn }}"
                                            data-kelas="{{ $siswa->id_kelas }}">
                                        {{ $siswa->nama }} ({{ $siswa->nisn ?: 'Tanpa NISN' }})
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <div class="form-text">Gunakan kotak pencarian untuk memfilter daftar siswa dengan cepat.</div>
                </div>

                {{-- Jam Ke --}}
                <div class="col-12">
                    <label class="form-label fw-bold text-secondary text-uppercase small">Jam Pelajaran <span class="text-danger">*</span></label>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($jamOptions as $jam)
                            <label class="form-check px-3 py-2 rounded-3 border mb-0 {{ in_array((string) $jam, (array) old('jam_ke', [])) ? 'border-primary bg-primary-subtle' : '' }}"
                                   style="cursor: pointer;" data-jam-label>
                                <input class="form-check-input me-1" type="checkbox" name="jam_ke[]" value="{{ $jam }}"
                                       style="cursor: pointer;"
                                       {{ in_array((string) $jam, (array) old('jam_ke', [])) ? 'checked' : '' }}>
                                <span class="fw-semibold small">Jam Ke-{{ $jam }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div class="form-text">Centang jam pelajaran berapa saja siswa tersebut di-dispensasi.</div>
                </div>

                {{-- Mata Pelajaran / Guru Mapel yang Ditinggalkan (auto-detect) --}}
                <div class="col-12">
                    <label class="form-label fw-bold text-secondary text-uppercase small">Mata Pelajaran / Guru Mapel yang Ditinggalkan <span class="text-muted fw-normal">(opsional)</span></label>
                    <select name="id_jadwal" id="id_jadwal" class="form-select rounded-3">
                        <option value="">-- Pilih jadwal KBM (otomatis terdeteksi) --</option>
                        @foreach($jadwalOptions as $j)
                            <option value="{{ $j['id'] }}" {{ (int) old('id_jadwal') === (int) $j['id'] ? 'selected' : '' }}
                                    data-hari="{{ $j['hari'] }}" data-kelas="{{ $j['id_kelas'] }}" data-jam-ke="{{ $j['jam_ke'] }}">
                                Jam {{ $j['jam_ke'] }} · {{ $j['hari'] }} · {{ $j['nama_kelas'] }} — {{ $j['mapel'] }} ({!! $j['guru'] !!})
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">
                        Otomatis tersaring dari tanggal, siswa, dan jam ke- yang dipilih. Biarkan kosong bila tidak perlu dikaitkan ke mapel/guru.
                    </div>
                </div>

                {{-- Alasan --}}
                <div class="col-12">
                    <label class="form-label fw-bold text-secondary text-uppercase small">Alasan Kegiatan <span class="text-danger">*</span></label>
                    <textarea name="alasan" rows="3" class="form-control rounded-3" maxlength="500"
                              placeholder="Contoh: Mengikuti lomba Paskibraka tingkat kabupaten..." required>{{ old('alasan') }}</textarea>
                </div>

                {{-- Foto Bukti Surat Dispen (kamera saja) --}}
                <div class="col-12">
                    <label class="form-label fw-bold text-secondary text-uppercase small">Foto / Bukti Surat Dispen <span class="text-muted fw-normal">(opsional)</span></label>
                    <div class="d-flex flex-column flex-md-row align-items-start gap-3">
                        <div>
                            <button type="button" id="btnBukaWebcam" class="btn btn-outline-dark rounded-3 px-4 py-2 fw-semibold"
                                    data-bs-toggle="modal" data-bs-target="#modalWebcam">
                                📷 Ambil Foto via Kamera / Webcam
                            </button>
                            <div class="form-text mt-2">
                                Di HP/Tablet otomatis membuka kamera; di PC/Laptop memakai webcam.
                                Foto dijepret langsung (tanpa pilih file galeri) dan menjadi lampiran bukti surat.
                            </div>
                        </div>
                        <div id="previewBukti" class="text-end flex-grow-1"></div>
                    </div>
                    <input type="hidden" name="bukti_surat" id="buktiSuratBase64" value="">
                </div>

                {{-- Tanda Tangan Digital Siswa --}}
                <div class="col-12">
                    <label class="form-label fw-bold text-secondary text-uppercase small">Tanda Tangan Siswa <span class="text-muted fw-normal">(opsional)</span></label>
                    <div class="border rounded-3 overflow-hidden" style="max-width: 520px;">
                        <canvas id="canvasTtd" width="600" height="220"
                                style="width: 100%; height: auto; display: block; background: #fff; touch-action: none; cursor: crosshair;">
                            Browser Anda tidak mendukung Canvas.
                        </canvas>
                        <div class="d-flex justify-content-between align-items-center bg-light-subtle px-3 py-2">
                            <span class="text-muted small"><i class="bi bi-pencil me-1"></i>Goreskan tanda tangan di area di atas (mouse / layar sentuh).</span>
                            <button type="button" id="btnClearTtd" class="btn btn-sm btn-outline-danger rounded-3">
                                <i class="bi bi-eraser me-1"></i> Hapus / Clear
                            </button>
                        </div>
                    </div>
                    <input type="hidden" name="ttd_siswa" id="ttdSiswa" value="">
                    <div class="form-text">
                        TTD berupa goresan tangan siswa/pemohon, otomatis tercantum pada surat dispen setelah pengajuan disetujui.
                    </div>
                </div>
            </div>

            {{-- Aksi --}}
            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                <a href="{{ route('piket.dispensasi.index') }}" class="btn btn-light rounded-3 px-4">Batal</a>
                <button type="submit" class="btn btn-success rounded-3 px-4 fw-semibold shadow-sm">
                    <i class="bi bi-send me-1"></i> Kirim Pengajuan Dispen
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Modal Ambil Foto via Kamera/Webcam --}}
<div class="modal fade" id="modalWebcam" tabindex="-1" aria-labelledby="modalWebcamLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark" id="modalWebcamLabel">
                    <i class="bi bi-camera-video me-1"></i> Ambil Foto Bukti via Kamera
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-3">
                <p class="text-muted small mb-3">
                    Arahkan kamera ke surat bukti fisik, lalu klik <strong>Ambil Foto</strong> untuk menjepret.
                </p>
                <div class="text-center bg-dark rounded-3 overflow-hidden"
                     style="min-height: 260px; display: flex; align-items: center; justify-content: center;">
                    <video id="webcamVideo" autoplay playsinline muted
                           style="width: 100%; max-height: 55vh; object-fit: contain;"></video>
                    <div id="webcamPlaceholder" class="text-light">
                        <i class="bi bi-camera-video-off fs-1 d-block mb-2"></i>
                        Memuat kamera... izinkan akses kamera bila browser meminta.
                    </div>
                    <canvas id="webcamCanvas" class="d-none" width="1280" height="720"></canvas>
                </div>
                <div id="webcamHasil" class="text-center mt-3 d-none">
                    <img id="webcamHasilImg" alt="Hasil foto kamera" class="rounded-3 border shadow-sm"
                         style="max-height: 320px; max-width: 100%; object-fit: contain;">
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Batal</button>
                <button type="button" id="btnAmbilFoto" class="btn btn-primary rounded-3 fw-semibold">
                    <i class="bi bi-camera me-1"></i>Ambil Foto
                </button>
                <button type="button" id="btnPakaiFoto" class="btn btn-success rounded-3 fw-semibold d-none"
                        data-bs-dismiss="modal">
                    <i class="bi bi-check-lg me-1"></i>Pakai Foto Ini
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // ===== Filter daftar siswa =====
        const searchInput = document.getElementById('searchSiswa');
        const selectSiswa = document.getElementById('id_siswa');

        searchInput.addEventListener('input', function () {
            const q = searchInput.value.trim().toLowerCase();
            for (const opt of selectSiswa.options) {
                const nama = (opt.dataset.nama || '').toLowerCase();
                const nisn = opt.dataset.nisn || '';
                opt.hidden = opt.value !== '' && q !== '' && !nama.includes(q) && !nisn.includes(q);
            }
        });

        // ===== Auto-detect jadwal mapel/guru dari tanggal + siswa + jam ke- =====
        const tanggalInput   = document.querySelector('input[name="tanggal"]');
        const selectJadwal   = document.getElementById('id_jadwal');
        const jamCheckboxes  = Array.from(document.querySelectorAll('input[name="jam_ke[]"]'));
        const HARI_INDONESIA = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

        function jamTerpilih() {
            return jamCheckboxes
                .filter(function (cb) { return cb.checked; })
                .map(function (cb) { return parseInt(cb.value, 10); })
                .filter(function (j) { return !isNaN(j); });
        }

        function filterJadwalOptions() {
            let dayName = '';
            if (tanggalInput && tanggalInput.value) {
                const d = new Date(tanggalInput.value + 'T00:00:00');
                if (!isNaN(d.getTime())) dayName = HARI_INDONESIA[d.getDay()] || '';
            }

            const kelasId  = selectSiswa.selectedOptions.length
                ? (selectSiswa.selectedOptions[0].dataset.kelas || '')
                : '';
            const jams = jamTerpilih();

            let visible = 0;
            for (const opt of selectJadwal.options) {
                if (!opt.value) continue;
                const cocokHari  = !dayName || opt.dataset.hari === dayName;
                const cocokKelas = !kelasId || opt.dataset.kelas === kelasId;
                const cocokJam   = !jams.length || jams.includes(parseInt(opt.dataset.jamKe || '0', 10));
                const show = cocokHari && cocokKelas && cocokJam;
                opt.hidden = !show;
                if (show) visible++;
            }

            // Jika hanya satu jadwal yang cocok dan belum ada pilihan -> pilih otomatis
            if (visible === 1 && selectJadwal.value === '') {
                for (const opt of selectJadwal.options) {
                    if (opt.value && !opt.hidden) {
                        selectJadwal.value = opt.value;
                        break;
                    }
                }
            }
        }

        function onFilterChanged() { filterJadwalOptions(); }

        if (tanggalInput) tanggalInput.addEventListener('change', onFilterChanged);
        if (selectSiswa)   selectSiswa.addEventListener('change', onFilterChanged);
        jamCheckboxes.forEach(function (cb) { cb.addEventListener('change', onFilterChanged); });
        filterJadwalOptions();

        // ===== Canvas Tanda Tangan Digital =====
        const canvasTtd = document.getElementById('canvasTtd');
        const ttdHidden = document.getElementById('ttdSiswa');
        const btnClearTtd = document.getElementById('btnClearTtd');

        if (canvasTtd) {
            const ctx = canvasTtd.getContext('2d');
            let drawing = false;
            let inked = false;
            let lastX = 0;
            let lastY = 0;

            // Latar putih agar hasil PNG bersih
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvasTtd.width, canvasTtd.height);
            ctx.strokeStyle = '#0f172a';
            ctx.lineWidth = 2.5;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';

            function getPos(e) {
                const rect = canvasTtd.getBoundingClientRect();
                return {
                    x: (e.clientX - rect.left) * (canvasTtd.width / rect.width),
                    y: (e.clientY - rect.top) * (canvasTtd.height / rect.height),
                };
            }

            function startStroke(e) {
                e.preventDefault();
                drawing = true;
                inked = true;
                const p = getPos(e);
                lastX = p.x;
                lastY = p.y;
                ctx.beginPath();
                ctx.moveTo(lastX, lastY);
                if (canvasTtd.setPointerCapture) {
                    canvasTtd.setPointerCapture(e.pointerId);
                }
            }

            function moveStroke(e) {
                if (!drawing) return;
                e.preventDefault();
                const p = getPos(e);
                ctx.lineTo(p.x, p.y);
                ctx.stroke();
                lastX = p.x;
                lastY = p.y;
            }

            function endStroke(e) {
                if (!drawing) return;
                drawing = false;
                ctx.closePath();
                ttdHidden.value = inked ? canvasTtd.toDataURL('image/png') : '';
            }

            canvasTtd.addEventListener('pointerdown', startStroke);
            canvasTtd.addEventListener('pointermove', moveStroke);
            canvasTtd.addEventListener('pointerup', endStroke);
            canvasTtd.addEventListener('pointercancel', endStroke);
            canvasTtd.addEventListener('touchstart', function (e) { e.preventDefault(); }, { passive: false });

            btnClearTtd.addEventListener('click', function () {
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, canvasTtd.width, canvasTtd.height);
                inked = false;
                ttdHidden.value = '';
            });
        }

        // ===== Kamera Foto Bukti Surat (camera only, bukan galeri) =====
        const modalWebcam  = document.getElementById('modalWebcam');
        const video        = document.getElementById('webcamVideo');
        const canvasWc     = document.getElementById('webcamCanvas');
        const placeholderc = document.getElementById('webcamPlaceholder');
        const hasilWrap    = document.getElementById('webcamHasil');
        const hasilImg     = document.getElementById('webcamHasilImg');
        const btnAmbilFoto = document.getElementById('btnAmbilFoto');
        const btnPakaiFoto = document.getElementById('btnPakaiFoto');
        const buktiHidden  = document.getElementById('buktiSuratBase64');
        const previewEl    = document.getElementById('previewBukti');

        let mediaStream = null;

        function drawPreview(dataUrl) {
            previewEl.innerHTML = '';
            if (!dataUrl) return;
            const box = document.createElement('div');
            box.className = 'd-inline-block text-start';
            box.innerHTML =
                '<div class="text-muted small mb-1"><i class="bi bi-check-circle-fill text-success me-1"></i>Foto tersimpan (lampiran)</div>' +
                '<img src="' + dataUrl + '" alt="Pratinjau foto bukti" class="rounded-3 border shadow-sm" style="height: 110px; max-width: 100%; object-fit: cover;">' +
                '<div class="mt-2"><button type="button" id="btnHapusFoto" class="btn btn-sm btn-outline-danger rounded-3">' +
                '<i class="bi bi-trash me-1"></i>Hapus Foto</button></div>';
            previewEl.appendChild(box);

            const btnHapus = document.getElementById('btnHapusFoto');
            if (btnHapus) {
                btnHapus.addEventListener('click', function () {
                    buktiHidden.value = '';
                    previewEl.innerHTML = '<span class="text-muted small">Belum ada foto.</span>';
                });
            }
        }

        async function startWebcam() {
            hasilWrap.classList.add('d-none');
            btnPakaiFoto.classList.add('d-none');
            btnAmbilFoto.classList.remove('d-none');
            placeholderc.style.display = '';
            placeholderc.innerHTML =
                '<i class="bi bi-camera-video-off fs-1 d-block mb-2"></i>' +
                'Memuat kamera... izinkan akses kamera bila browser meminta.';

            try {
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    throw new Error('MediaDevices tidak didukung');
                }
                mediaStream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } },
                    audio: false,
                });
                video.srcObject = mediaStream;
                await video.play();
                placeholderc.style.display = 'none';
            } catch (err) {
                placeholderc.style.display = '';
                placeholderc.innerHTML =
                    '<i class="bi bi-camera-video-off fs-1 d-block mb-2"></i>' +
                    'Tidak dapat mengakses kamera. Pastikan izin kamera diizinkan dan perangkat memiliki kamera/webcam.';
            }
        }

        function stopWebcam() {
            if (mediaStream) {
                mediaStream.getTracks().forEach(function (t) { t.stop(); });
                mediaStream = null;
            }
            video.srcObject = null;
        }

        if (modalWebcam) {
            modalWebcam.addEventListener('shown.bs.modal', startWebcam);
            modalWebcam.addEventListener('hidden.bs.modal', stopWebcam);

            btnAmbilFoto.addEventListener('click', function () {
                if (!video.srcObject) return;
                canvasWc.width  = video.videoWidth  || 1280;
                canvasWc.height = video.videoHeight || 720;
                canvasWc.getContext('2d').drawImage(video, 0, 0, canvasWc.width, canvasWc.height);
                hasilImg.src = canvasWc.toDataURL('image/png');
                hasilWrap.classList.remove('d-none');
                btnAmbilFoto.classList.add('d-none');
                btnPakaiFoto.classList.remove('d-none');
            });

            btnPakaiFoto.addEventListener('click', function () {
                const dataUrl = canvasWc.toDataURL('image/png');
                buktiHidden.value = dataUrl;
                drawPreview(dataUrl);
            });
        }
    });
</script>
@endpush