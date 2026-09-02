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
        <form action="{{ route('piket.dispensasi.store') }}" method="POST" id="formDispen" novalidate>
            @csrf

            {{-- ============================================================ --}}
            {{-- TIPE DISPENSASI: KELUAR GERBANG vs MASUK KELAS               --}}
            {{-- ============================================================ --}}
            <div class="d-flex flex-wrap align-items-center gap-2 mb-4 p-1 bg-light-subtle border rounded-3 w-100" style="max-width: 420px;">
                <button type="button" id="tabDispenKeluar" class="btn btn-sm rounded-3 px-3 py-2 fw-semibold flex-grow-1" style="font-size: 0.85rem;">
                    <i class="bi bi-door-closed me-1"></i> Dispen Keluar Gerbang
                </button>
                <button type="button" id="tabDispenMasuk" class="btn btn-sm rounded-3 px-3 py-2 fw-semibold flex-grow-1" style="font-size: 0.85rem;">
                    <i class="bi bi-door-open me-1"></i> Dispen Masuk Kelas
                </button>
            </div>
            <input type="hidden" name="tipe_dispen" id="tipeDispen" value="{{ old('tipe_dispen', \App\Models\DispensasiSiswa::TIPE_KELUAR) }}">
            <div class="form-text mb-4" id="tipeDispenHint">Keluarkan siswa lebih awal / izin keluar sekolah dari jam tertentu.</div>

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

                {{-- ============================================================ --}}
                {{-- PART 1: JAM PELAJARAN (AUTOMATIC SELECTION)                 --}}
                {{-- ============================================================ --}}
                <div class="col-12" id="keluarPart1">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge rounded-pill bg-primary-subtle text-primary-emphasis border border-primary-subtle">Part 1</span>
                        <h6 class="fw-bold text-dark mb-0">Jam Pelajaran yang Ditinggalkan <span class="text-danger">*</span></h6>
                    </div>
                    <p class="text-muted small mb-2">Tentukan JP / Mapel yang ditinggalkan. Jam yang sedang berlangsung ter-centang otomatis.</p>

                    {{-- Quick-select helper --}}
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="text-muted small align-self-center">Pilih cepat:</span>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-3" id="btnJamSekarang">
                            <i class="bi bi-clock me-1"></i>Jam Sekarang
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-warning rounded-3" id="btnSisaJam">
                            <i class="bi bi-arrow-down-circle me-1"></i>Sisa Jam Hari Ini
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-3" id="btnPilihManual">
                            <i class="bi bi-pencil-square me-1"></i>Pilih Manual
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger rounded-3" id="btnBersihkanJam">
                            <i class="bi bi-x-circle me-1"></i>Bersihkan
                        </button>
                    </div>

                   <div class="row g-2">
                        @foreach($jamOptions as $jam)
                            @php
                                $jp = $jamPelajaran->firstWhere('jam_ke', $jam);
                                $mulai   = $jp ? substr((string) $jp->jam_mulai, 0, 5) : '';
                                $selesai = $jp ? substr((string) $jp->jam_selesai, 0, 5) : '';
                                $waktu   = ($mulai && $selesai) ? $mulai . ' - ' . $selesai : '';
                                $checked = in_array((string) $jam, (array) old('jam_ke', []));
                            @endphp
                            <!-- Menggunakan col-4 col-sm-3 col-md-2 col-xl-1 (biar muat banyak & rapat) -->
                            <div class="col-4 col-sm-3 col-md-2 col-xl-1">
                                <label class="d-flex flex-column align-items-center justify-content-center text-center w-100 h-100 px-1 py-1.5 rounded-3 border mb-0 {{ $checked ? 'border-primary bg-primary-subtle' : '' }}"
                                    style="cursor: pointer; min-height: 48px;" data-jam-label
                                    title="{{ $waktu ? 'Rentang ' . $waktu : 'Jam Pelajaran' }}">
                                    <input class="form-check-input m-0 mb-1" type="checkbox" name="jam_ke[]" value="{{ $jam }}"
                                        style="cursor: pointer; transform: scale(0.9);" data-jam-ke="{{ $jam }}"
                                        data-mulai="{{ $jp->jam_mulai ?? '' }}" data-selesai="{{ $jp->jam_selesai ?? '' }}"
                                        {{ $checked ? 'checked' : '' }}>
                                    <span class="fw-semibold lh-1" style="font-size: 0.8rem;">JP {{ $jam }}</span>
                                    @if($waktu)
                                        <span class="text-muted mt-1 lh-1" style="font-size: 0.6rem; letter-spacing: -0.3px;">{{ $waktu }}</span>
                                    @endif
                                </label>
                            </div>
                        @endforeach
                    </div>
                    <div class="form-text">
                        Jam yang sedang berlangsung otomatis ter-centang. Anda tetap dapat mengubah (check/uncheck) secara manual
                        jika jam keluar siswa berbeda dari jam pembuatan.
                    </div>
                    <div id="jamKeError" class="text-danger small mt-2 d-none">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>Pilih minimal satu jam pelajaran yang ditinggalkan (<i>Jam Pelajaran</i>).
                    </div>
                </div>

                {{-- ============================================================ --}}
                {{-- PART 2: JP BERANGKAT / KELUAR SEKOLAH (BERBASIS MASTER JP)  --}}
                {{-- ============================================================ --}}
                <div class="col-12" id="keluarPart2">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle">Part 2</span>
                        <h6 class="fw-bold text-dark mb-0">Waktu Berangkat / Keluar Gerbang <span class="text-muted fw-normal">(berbasis Jam Pelajaran)</span></h6>
                    </div>
                    <p class="text-muted small mb-2">
                        Pilih Jam Pelajaran saat siswa benar-benar keluar sekolah. Terisi otomatis berdasarkan JP ter-awal
                        yang dipilih di Part 1, namun dapat diubah manual oleh Guru Piket jika keluar di JP berikutnya.
                    </p>
                    <div class="col-md-6 col-lg-4 ps-0">
                        <label for="jam_keluar_jp" class="form-label fw-semibold text-secondary small">Jam Keluar Gerbang (JP)</label>
                        <select name="jam_keluar_jp" id="jam_keluar_jp" class="form-select rounded-3">
                            <option value="">-- Pilih Jam Pelajaran Keluar --</option>
                            @foreach($jamOptions as $jam)
                                @php
                                    $jp = $jamPelajaran->firstWhere('jam_ke', $jam);
                                @endphp
                                <option value="{{ $jam }}" data-mulai="{{ $jp->jam_mulai ?? '' }}"
                                        {{ (string) old('jam_keluar_jp') === (string) $jam ? 'selected' : '' }}>
                                    JP Ke-{{ $jam }}{{ $jp && $jp->jam_mulai ? ' (' . $jp->jam_mulai . ')' : '' }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">
                            Otomatis terisi JP ter-awal dari Part 1. Ubah manual bila siswa keluar di JP yang berbeda.
                        </div>
                    </div>
                </div>

                {{-- Mata Pelajaran / Guru Mapel yang Ditinggalkan (auto-detect) --}}
                <div class="col-12" id="keluarJadwal">
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

                {{-- Alasan (KELUAR) --}}
                <div class="col-12" id="keluarAlasan">
                    <label class="form-label fw-bold text-secondary text-uppercase small">Alasan Kegiatan <span class="text-danger">*</span></label>
                    <textarea name="alasan" rows="3" class="form-control rounded-3" maxlength="500"
                              placeholder="Contoh: Mengikuti lomba Paskibraka tingkat kabupaten..." required>{{ old('alasan') }}</textarea>
                </div>

                {{-- ============================================================ --}}
                {{-- AREA MASUK KELAS (Izin Telat / Kembali KBM)                  --}}
                {{-- ============================================================ --}}
                <div class="col-12 d-none" id="areaMasuk">
                    <div class="d-flex align-items-center gap-3 mb-1">
                        <span class="badge rounded-pill bg-success-subtle text-success-emphasis border border-success-subtle">Masuk Kelas</span>
                        <h6 class="fw-bold text-dark mb-0">Izin Telat / Masuk KBM <span class="text-danger">*</span></h6>
                    </div>
                    <p class="text-muted small mb-3">
                        Siswa terlambat/belum masuk dan diperbolehkan mengikuti KBM mulai dari Jam Pelajaran tertentu.
                        Terisi otomatis dengan JP yang sedang berjalan, namun dapat diubah manual.
                    </p>
                    <div class="row g-3">
                        <div class="col-md-6 col-lg-4">
                            <label for="jam_masuk_jp" class="form-label fw-semibold text-secondary small">Boleh Masuk Mulai JP Ke-</label>
                            <select name="jam_masuk_jp" id="jam_masuk_jp" class="form-select rounded-3" required>
                                <option value="">-- Pilih JP --</option>
                                @foreach($jamOptions as $jam)
                                    @php
                                        $jp = $jamPelajaran->firstWhere('jam_ke', $jam);
                                    @endphp
                                    <option value="{{ $jam }}" data-mulai="{{ $jp->jam_mulai ?? '' }}"
                                            {{ (string) old('jam_masuk_jp') === (string) $jam ? 'selected' : '' }}>
                                        JP Ke-{{ $jam }}{{ $jp && $jp->jam_mulai ? ' (' . substr((string) $jp->jam_mulai, 0, 5) . ')' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">Default: JP yang sedang berlangsung saat ini.</div>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <label for="alasan_kategori" class="form-label fw-semibold text-secondary small">Kategori Alasan</label>
                            <select name="alasan_kategori" id="alasan_kategori" class="form-select rounded-3" required>
                                <option value="">-- Pilih Kategori --</option>
                                @foreach(['Terlambat Sekolah', 'Urusan Organisasi/BK', 'Dari UKS', 'Lainnya'] as $kat)
                                    <option value="{{ $kat }}" {{ old('alasan_kategori') === $kat ? 'selected' : '' }}>{{ $kat }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">Pilih alasan keterlambatan / ketidakhadiran di awal.</div>
                        </div>
                        <div class="col-md-6 col-lg-4 d-none" id="alasanDetailWrap">
                            <label for="alasan_detail" class="form-label fw-semibold text-secondary small">Detail Alasan <span class="text-muted fw-normal">(opsional)</span></label>
                            <input type="text" name="alasan_detail" id="alasan_detail" class="form-control rounded-3" maxlength="250"
                                   placeholder="Tuliskan detail bila kategori 'Lainnya'..." value="{{ old('alasan_detail') }}">
                        </div>
                    </div>
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

        // =====================================================================
        // Jam Pelajaran: auto-select berdasarkan waktu sekarang + quick-select
        // =====================================================================
        @php
            $masterJamJson = json_encode(
                $jamPelajaran->map(fn ($jp) => [
                    'jam_ke'   => (int) $jp->jam_ke,
                    'mulai'    => $jp->jam_mulai,
                    'selesai'  => $jp->jam_selesai,
                    'jenis'    => $jp->jenis,
                ])->values()->all()
            );
        @endphp
        // Master jam pelajaran lengkap (dari server) untuk mencocokkan waktu sekarang
        const MASTER_JAM = {!! $masterJamJson !!};

        function waktuToHari(waktu) {
            const [h, m] = String(waktu || '').split(':').map(Number);
            return (h || 0) * 60 + (m || 0);
        }

        // Tentukan jam_ke yang sedang berlangsung berdasarkan master jam pelajaran.
        // Mengembalikan null jika tidak ada JP yang berlangsung.
        function jamKeSekarang() {
            const d = new Date();
            let now = d.getHours() * 60 + d.getMinutes();

            // Cocokkan dengan slot yang sedang berlangsung (now >= mulai && now < selesai)
            for (const jp of MASTER_JAM) {
                const mulai = waktuToHari(jp.mulai);
                const selesai = waktuToHari(jp.selesai);
                if (mulai === 0 && selesai === 0) continue;
                if (now >= mulai && now < selesai) return jp.jam_ke;
            }
            return null;
        }

        function autoSelectJamSekarang() {
            const jamKe = jamKeSekarang();
            if (jamKe === null) return false;
            let found = false;
            jamCheckboxes.forEach(function (c) {
                c.checked = (parseInt(c.value, 10) === jamKe);
                if (c.checked) found = true;
            });
            return found;
        }

        // Marker untuk melacak perubahan manual user (agar auto-select tidak menimpa)
        let userTouchedJam = false;
        jamCheckboxes.forEach(function (cb) {
            cb.addEventListener('change', function () { userTouchedJam = true; });
        });

        // Auto-select hanya saat halaman pertama dimuat & belum ada nilai old()
        var adaOldJam = `{{ old('jam_ke') ? '1' : '0' }}` === '1';
        if (!adaOldJam) {
            autoSelectJamSekarang();
        }

        // ---- Quick-select buttons ----
        const btnJamSekarang = document.getElementById('btnJamSekarang');
        const btnSisaJam     = document.getElementById('btnSisaJam');
        const btnPilihManual = document.getElementById('btnPilihManual');
        const btnBersihkan   = document.getElementById('btnBersihkanJam');

        if (btnJamSekarang) {
            btnJamSekarang.addEventListener('click', function () {
                autoSelectJamSekarang();
                filterJadwalOptions();
            });
        }

        if (btnSisaJam) {
            btnSisaJam.addEventListener('click', function () {
                const jamKe = jamKeSekarang();
                let startIndex = 0;
                if (jamKe !== null) {
                    const cb = jamCheckboxes.find(function (c) {
                        return parseInt(c.value, 10) === jamKe;
                    });
                    if (cb) startIndex = jamCheckboxes.indexOf(cb);
                }
                jamCheckboxes.forEach(function (c, i) {
                    c.checked = (i >= startIndex && c.dataset.mulai !== '');
                });
                filterJadwalOptions();
            });
        }

        if (btnPilihManual) {
            btnPilihManual.addEventListener('click', function () {
                // Tidak mengubah apa-apa; hanya memastikan checkbox tetap editable
                // dan mengarahkan pandangan guru ke area jam.
                const label = document.querySelector('[data-jam-label]');
                if (label) label.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        }

        if (btnBersihkan) {
            btnBersihkan.addEventListener('click', function () {
                jamCheckboxes.forEach(function (c) { c.checked = false; });
                filterJadwalOptions();
            });
        }

        // ---- Part 2: JP Keluar Gerbang (sync dari Part 1) ----
        const jamKeluarJpSelect = document.getElementById('jam_keluar_jp');

        // Sync otomatis: saat checkbox JP di Part 1 berubah, set dropdown keluar
        // ke JP TER-AWAL yang dipilih. User tetap bisa mengubah manual setelahnya.
        let jamKeluarManual = false;

        function syncJamKeluarJp() {
            if (jamKeluarManual || !jamKeluarJpSelect) return;
            const jams = jamTerpilih();
            if (jams.length > 0) {
                const terAwal = Math.min.apply(null, jams);
                jamKeluarJpSelect.value = String(terAwal);
            }
        }

        if (jamKeluarJpSelect) {
            jamKeluarJpSelect.addEventListener('change', function () {
                jamKeluarManual = true;
            });
        }

        // jalankan sync tiap kali checkbox Part 1 berubah (sebelum filter jadwal)
        jamCheckboxes.forEach(function (cb) {
            cb.addEventListener('change', function () {
                syncJamKeluarJp();
            });
        });

        // Saat halaman pertama dimuat (belum ada old()), isi dropdown JP keluar
        // dari JP yang tercentang otomatis (JP sekarang).
        var adaOldJam = `{{ old('jam_ke') ? '1' : '0' }}` === '1';
        if (!adaOldJam && jamKeluarJpSelect && jamKeluarJpSelect.value === '') {
            syncJamKeluarJp();
        }
        // ====================================================================

        // =====================================================================
        // TIPE DISPENSASI: Toggle Keluar Gerbang vs Masuk Kelas
        // =====================================================================
        const tipeDispenInput  = document.getElementById('tipeDispen');
        const tabDispenKeluar  = document.getElementById('tabDispenKeluar');
        const tabDispenMasuk   = document.getElementById('tabDispenMasuk');
        const tipeDispenHint   = document.getElementById('tipeDispenHint');
        const areaMasuk        = document.getElementById('areaMasuk');
        const jamMasukJpSelect = document.getElementById('jam_masuk_jp');
        const alasanKategori   = document.getElementById('alasan_kategori');
        const alasanDetailWrap = document.getElementById('alasanDetailWrap');

        const keluarSections = [
            document.getElementById('keluarPart1'),
            document.getElementById('keluarPart2'),
            document.getElementById('keluarJadwal'),
            document.getElementById('keluarAlasan'),
        ];

        function setTipeDispen(tipe) {
            const isMasuk = tipe === 'masuk';

            tabDispenKeluar.classList.toggle('btn-primary', !isMasuk);
            tabDispenKeluar.classList.toggle('btn-light', isMasuk);
            tabDispenMasuk.classList.toggle('btn-primary', isMasuk);
            tabDispenMasuk.classList.toggle('btn-light', !isMasuk);

            keluarSections.forEach(function (el) { if (el) el.classList.toggle('d-none', isMasuk); });
            if (areaMasuk) areaMasuk.classList.toggle('d-none', !isMasuk);

            if (tipeDispenHint) {
                tipeDispenHint.innerHTML = isMasuk
                    ? 'Mengizinkan siswa terlambat/belum masuk untuk mengikuti KBM mulai dari JP tertentu.'
                    : 'Keluarkan siswa lebih awal / izin keluar sekolah dari jam tertentu.';
            }

            if (isMasuk) {
                jamCheckboxes.forEach(function (c) { c.checked = false; });
                if (selectJadwal) selectJadwal.value = '';
                if (jamKeluarJpSelect) jamKeluarJpSelect.value = '';
                if (jamMasukJpSelect && jamMasukJpSelect.value === '') {
                    const jk = jamKeSekarang();
                    if (jk !== null) jamMasukJpSelect.value = String(jk);
                }
            } else {
                if (jamCheckboxes.every(function (c) { return !c.checked; })) {
                    autoSelectJamSekarang();
                }
                syncJamKeluarJp();
                filterJadwalOptions();
            }
        }

        if (tabDispenKeluar) tabDispenKeluar.addEventListener('click', function () {
            tipeDispenInput.value = 'keluar';
            setTipeDispen('keluar');
        });
        if (tabDispenMasuk) tabDispenMasuk.addEventListener('click', function () {
            tipeDispenInput.value = 'masuk';
            setTipeDispen('masuk');
        });

        // Kategori "Lainnya" => tampilkan detail
        if (alasanKategori && alasanDetailWrap) {
            alasanKategori.addEventListener('change', function () {
                alasanDetailWrap.classList.toggle('d-none', this.value !== 'Lainnya');
            });
        }

        // Inisialisasi sesuai nilai tersimpan / old()
        setTipeDispen(tipeDispenInput ? tipeDispenInput.value : 'keluar');
        if (alasanKategori && alasanDetailWrap) {
            alasanDetailWrap.classList.toggle('d-none', (alasanKategori.value || '') !== 'Lainnya');
        }
        // ====================================================================


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

            // Bagian Part 1 (Jam Pelajaran) untuk memberi tanda visual bila belum ada JP dipilih.
            const keluarPart1 = document.getElementById('keluarPart1');
            const jamKeError  = document.getElementById('jamKeError');

            function clearJamKeError() {
                if (jamKeError) jamKeError.classList.add('d-none');
                if (keluarPart1) {
                    keluarPart1.classList.remove('border-danger', 'border', 'rounded-3');
                }
            }

            jamCheckboxes.forEach(function (cb) {
                cb.addEventListener('change', clearJamKeError);
            });

            // Field wajib yang sedang TERLIHAT sesuai mode aktif. Field di bagian yang
            // disembunyikan (d-none) dilewati agar tidak memblokir submit.
            function isTampil(el) {
                return !!(el && el.offsetParent !== null);
            }

            formDispen.addEventListener('submit', function (e) {
                // 1) Konversi canvas Tanda Tangan ke Data URL base64 dan isi input hidden
                //    (langkah ini selalu dijalankan SEBELUM form benar-benar dikirim).
                refreshState();

                // 2) Kumpulkan field wajib yang tampak namun belum terisi.
                let invalidVisible = null;
                formDispen.querySelectorAll('input[required], select[required], textarea[required]').forEach(function (el) {
                    if (invalidVisible) return;
                    if (!isTampil(el)) return;
                    if (!el.checkValidity()) invalidVisible = el;
                });

                // 3) Tanda tangan Guru Piket wajib digambar.
                if (!filled) {
                    e.preventDefault();
                    ttdError.classList.remove('d-none');
                    ttdCanvas.classList.add('border-danger');
                    ttdCanvas.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    return;
                }

                // 4) Mode KELUAR: minimal satu jam pelajaran harus dipilih (server wajibkan jam_ke).
                const isMasukMode = !!(tipeDispenInput && tipeDispenInput.value === 'masuk');
                const adaJamTerpilih = jamCheckboxes.some(function (c) { return c.checked; });
                if (!isMasukMode && !adaJamTerpilih && !invalidVisible) {
                    e.preventDefault();
                    if (jamKeError) jamKeError.classList.remove('d-none');
                    if (keluarPart1) {
                        keluarPart1.classList.add('border', 'border-danger', 'rounded-3');
                        keluarPart1.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    return;
                }
                clearJamKeError();

                // 5) Field wajib yang tampak belum diisi -> tampilkan bubble validasi browser
                //    pada field tersebut, tanpa terhalang field tersembunyi (novalidate).
                if (invalidVisible) {
                    e.preventDefault();
                    invalidVisible.reportValidity();
                    invalidVisible.focus();
                    return;
                }
                // Semua valid -> form.submit() berjalan (default submit, tidak di-prevent).
            });
        }
    });
</script>
@endpush