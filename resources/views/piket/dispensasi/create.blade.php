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
                Guru Piket mengisi detail dispensasi dan langsung menyetujui (ACC). Setelah disetujui,
                Tanda Tangan Siswa (Pemohon) dilengkapi sebagai konfirmasi akhir.
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

                {{-- Tanda Tangan Guru Piket (wajib digambar) --}}
                <div class="col-12">
                    <label class="form-label fw-bold text-secondary text-uppercase small">
                        Tanda Tangan Guru Piket (Penyetuju) <span class="text-danger">*</span>
                    </label>
                    <div class="border rounded-3 p-3 bg-light-subtle">
                        <div class="d-flex flex-column flex-md-row align-items-start gap-3">
                            <div class="flex-grow-1">
                                <canvas id="canvasTtdGuru" width="520" height="180"
                                        class="border rounded-3 bg-white w-100"
                                        style="touch-action: none; cursor: crosshair; max-width: 100%; height: auto;"></canvas>
                                <div class="form-text mt-2">
                                    Gambar tanda tangan Guru Piket pada kotak di atas menggunakan mouse, stylus, atau jari (layar sentuh).
                                    Tanda tangan ini menjadi tanda ACC otomatis pada surat.
                                </div>
                            </div>
                            <div class="d-flex flex-column gap-2 text-center">
                                <button type="button" id="btnBersihTtd" class="btn btn-sm btn-outline-danger rounded-3">
                                    <i class="bi bi-eraser me-1"></i>Bersihkan
                                </button>
                                <div id="ttdGuruStatus" class="d-none text-success small fw-semibold">
                                    <i class="bi bi-check-circle-fill me-1"></i>Tanda tangan tersimpan
                                </div>
                            </div>
                        </div>
                        <div id="ttdGuruError" class="text-danger small mt-2 d-none">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>Tanda tangan Guru Piket wajib digambar sebelum menyimpan.
                        </div>
                    </div>
                    <input type="hidden" name="ttd_guru" id="ttdGuruBase64" value="">
                </div>
            </div>

            {{-- Aksi --}}
            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                <a href="{{ route('piket.dispensasi.index') }}" class="btn btn-light rounded-3 px-4">Batal</a>
                <button type="submit" class="btn btn-success rounded-3 px-4 fw-semibold shadow-sm">
                    <i class="bi bi-check2-circle me-1"></i> Buat & Setujui Dispen (ACC)
                </button>
            </div>
        </form>
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

        // ===== Tanda Tangan Guru Piket (canvas, wajib digambar) =====
        const ttdCanvas     = document.getElementById('canvasTtdGuru');
        const ttdHidden     = document.getElementById('ttdGuruBase64');
        const ttdStatus     = document.getElementById('ttdGuruStatus');
        const ttdError      = document.getElementById('ttdGuruError');
        const btnBersihTtd  = document.getElementById('btnBersihTtd');
        const formDispen    = document.getElementById('formDispen');

        if (ttdCanvas) {
            const ctx = ttdCanvas.getContext('2d');
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.lineWidth = 2.5;
            ctx.strokeStyle = '#0f172a';

            let drawing = false;
            let filled  = false;

            function getPos(e) {
                const rect = ttdCanvas.getBoundingClientRect();
                const scaleX = ttdCanvas.width / rect.width;
                const scaleY = ttdCanvas.height / rect.height;
                const clientX = (e.touches && e.touches[0]) ? e.touches[0].clientX : e.clientX;
                const clientY = (e.touches && e.touches[0]) ? e.touches[0].clientY : e.clientY;
                return {
                    x: (clientX - rect.left) * scaleX,
                    y: (clientY - rect.top) * scaleY,
                };
            }

            function isBlank() {
                const data = ctx.getImageData(0, 0, ttdCanvas.width, ttdCanvas.height).data;
                for (let i = 3; i < data.length; i += 4) {
                    if (data[i] !== 0) return false;
                }
                return true;
            }

            function refreshState() {
                filled = !isBlank();
                ttdHidden.value = filled ? ttdCanvas.toDataURL('image/png') : '';
                if (filled) {
                    ttdStatus.classList.remove('d-none');
                    ttdError.classList.add('d-none');
                } else {
                    ttdStatus.classList.add('d-none');
                }
            }

            function start(e) {
                e.preventDefault();
                drawing = true;
                const p = getPos(e);
                ctx.beginPath();
                ctx.moveTo(p.x, p.y);
            }

            function move(e) {
                if (!drawing) return;
                e.preventDefault();
                const p = getPos(e);
                ctx.lineTo(p.x, p.y);
                ctx.stroke();
            }

            function end() {
                if (!drawing) return;
                drawing = false;
                refreshState();
            }

            ttdCanvas.addEventListener('mousedown', start);
            ttdCanvas.addEventListener('mousemove', move);
            window.addEventListener('mouseup', end);
            ttdCanvas.addEventListener('touchstart', start, { passive: false });
            ttdCanvas.addEventListener('touchmove', move, { passive: false });
            ttdCanvas.addEventListener('touchend', end);

            btnBersihTtd.addEventListener('click', function () {
                ctx.clearRect(0, 0, ttdCanvas.width, ttdCanvas.height);
                refreshState();
            });

            formDispen.addEventListener('submit', function (e) {
                refreshState();
                if (!filled) {
                    e.preventDefault();
                    ttdError.classList.remove('d-none');
                    ttdCanvas.classList.add('border-danger');
                    ttdCanvas.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });
        }
    });
</script>
@endpush