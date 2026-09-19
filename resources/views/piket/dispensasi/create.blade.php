@extends('layouts.app')

@section('title', 'Buat Dispensasi Siswa - Guru Piket')

@section('content')
<div class="container-fluid px-0">
    <style>
        .dispensasi-form {
            font-size: 0.9rem;
            line-height: 1.45;
        }

        .dispensasi-form .form-label,
        .dispensasi-form .form-text,
        .dispensasi-form p,
        .dispensasi-form .small,
        .dispensasi-form .btn,
        .dispensasi-form .badge,
        .dispensasi-form .text-muted,
        .dispensasi-form .text-secondary {
            font-size: 0.82rem;
        }

        .dispensasi-form h2 {
            font-size: 1.45rem;
        }

        .dispensasi-form h6 {
            font-size: 1rem;
        }
    </style>

    {{-- Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <a href="{{ route('piket.dispensasi.index') }}" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar Dispensasi</a>
            <h2 class="fw-black text-dark mt-2 mb-1" style="letter-spacing: -0.02em; font-weight: 800; font-size: 1.45rem;">
                Form Dispensasi Siswa
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.82rem;">
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

    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white dispensasi-form">
        <form action="{{ route('piket.dispensasi.store') }}" method="POST" id="formDispen" novalidate>
            @csrf

            {{-- ============================================================ --}}
            {{-- HEADER BARIS ATAS: MODE DISPEN (kiri) + TANGGAL DISPEN (kanan) --}}
            {{-- ============================================================ --}}
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                {{-- Kiri: Switch Mode Dispen --}}
                <div class="d-flex flex-wrap align-items-center gap-2 p-1 bg-light-subtle border rounded-3">
                    <button type="button" id="tabDispenKeluar" class="btn btn-sm rounded-3 px-3 py-2 fw-semibold flex-grow-1" style="font-size: 0.85rem;">
                        <i class="bi bi-door-closed me-1"></i> Dispen Keluar Gerbang
                    </button>
                    <button type="button" id="tabDispenMasuk" class="btn btn-sm rounded-3 px-3 py-2 fw-semibold flex-grow-1" style="font-size: 0.85rem;">
                        <i class="bi bi-door-open me-1"></i> Dispen Masuk Kelas
                    </button>
                </div>
                <input type="hidden" name="tipe_dispen" id="tipeDispen" value="{{ old('tipe_dispen', \App\Models\DispensasiSiswa::TIPE_KELUAR) }}">

                {{-- Kanan: Tanggal Dispen --}}
                <div class="w-100" style="min-width: 220px; max-width: 320px;">
                    <label for="tanggalDispen" class="form-label fw-bold text-secondary text-uppercase small mb-1">Tanggal Dispen <span class="text-danger">*</span></label>
                    <input type="date" name="tanggal" id="tanggalDispen" value="{{ old('tanggal', now()->toDateString()) }}" max="{{ now()->toDateString() }}" class="form-control rounded-3" required>
                </div>
            </div>
            <div class="form-text mb-4" id="tipeDispenHint">Keluarkan siswa lebih awal / izin keluar sekolah dari jam tertentu.</div>

            <div class="row g-4">
                {{-- ============================================================ --}}
                {{-- PILIH SISWA (FULL-WIDTH: Kelas | Cari NISN/Nama | Siswa)     --}}
                {{-- ============================================================ --}}
                <div class="col-12">
                    {{-- Multi-baris siswa: satu baris = satu siswa (rombongan) --}}
                    <label class="form-label fw-bold text-secondary text-uppercase small">Pilih Siswa <span class="text-danger">*</span></label>
                    <p class="text-muted small mb-2">
                        Pilih satu siswa untuk dispen tunggal, atau beberapa siswa untuk pengajuan kolektif (rombongan).
                        Bila lebih dari satu, tanda tangan digital digambar <strong>berurutan</strong> untuk tiap siswa.
                    </p>
                    <div id="terlambatHint" class="d-none small mb-2 px-2 py-1 bg-danger-subtle border border-danger-subtle rounded-3 text-danger-emphasis">
                        <i class="bi bi-info-circle me-1"></i>Siswa yang tercatat terlambat oleh Satpam hari ini muncul di bagian atas dropdown (<strong>⏰</strong>). Pilih langsung untuk mengisi jam masuk &amp; alasan otomatis.
                    </div>

                    <div id="siswaRowsContainer" class="d-flex flex-column gap-2">
                        @forelse($oldSiswaRows ?? collect() as $oldS)
                            <div class="siswa-row border rounded-3 p-2 bg-white">
                                <div class="d-flex gap-2 align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="row g-2">
                                            <div class="col-12 col-lg-4">
                                                <select class="form-select form-select-sm row-kelas" required>
                                                    <option value="">-- Pilih Kelas --</option>
                                                    @foreach($kelasList as $kelas)
                                                        <option value="{{ $kelas->id }}" {{ (int) ($oldS['id_kelas'] ?? null) === (int) $kelas->id ? 'selected' : '' }}>
                                                            {{ $kelas->nama_lengkap }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-12 col-lg-8">
                                                <select name="id_siswa[]" class="form-select form-select-sm row-siswa" required data-kelas="{{ $oldS['id_kelas'] ?? '' }}">
                                                    <option value="" data-nama="" data-nisn="" data-kelas="">-- Pilih Siswa --</option>
                                                    <option value="{{ $oldS['id'] ?? '' }}" selected
                                                            data-nama="{{ strtolower($oldS['nama'] ?? '') }}"
                                                            data-nisn="{{ strtolower($oldS['nisn'] ?? '') }}"
                                                            data-kelas="{{ $oldS['id_kelas'] ?? '' }}">
                                                        {{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}. {{ $oldS['nama'] ?? '' }} ({{ $oldS['nisn'] ?: 'Tanpa NISN' }})
                                                    </option>
                                                </select>
                                            </div>
                                        </div>
                                        <input type="hidden" name="catatan_terlambat_id[]" class="row-catatan" value="">
                                        <div class="row-status text-muted small d-none mt-1">
                                            <i class="bi bi-arrow-repeat spin"></i> Memuat data siswa...
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-danger row-remove" title="Hapus baris siswa">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                            </div>
                        @empty
                        @endforelse
                    </div>

                    <button type="button" id="btnTambahSiswa" class="btn btn-sm btn-outline-primary rounded-3 mt-2">
                        <i class="bi bi-person-plus me-1"></i> Tambah Siswa
                    </button>

                    <div id="siswaError" class="text-danger small mt-2 d-none">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>Pilih minimal satu siswa untuk dispensasi.
                    </div>
                    <div class="form-text">
                        Lebih dari satu siswa = surat dispensasi kolektif (rombongan) dengan TTD digital berurutan.
                    </div>

                    <template id="templateSiswaRow">
                        <div class="siswa-row border rounded-3 p-2 bg-white">
                            <div class="d-flex gap-2 align-items-start">
                                <div class="flex-grow-1">
                                    <div class="row g-2">
                                        <div class="col-12 col-lg-4">
                                            <select class="form-select form-select-sm row-kelas" required>
                                                <option value="">-- Pilih Kelas --</option>
                                                @foreach($kelasList as $kelas)
                                                    <option value="{{ $kelas->id }}">{{ $kelas->nama_lengkap }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-12 col-lg-8">
                                            <select name="id_siswa[]" class="form-select form-select-sm row-siswa" required>
                                                <option value="">-- Pilih Kelas Terlebih Dahulu --</option>
                                            </select>
                                        </div>
                                    </div>
                                    <input type="hidden" name="catatan_terlambat_id[]" class="row-catatan" value="">
                                    <div class="row-status text-muted small d-none mt-1">
                                        <i class="bi bi-arrow-repeat spin"></i> Memuat data siswa...
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-danger row-remove" title="Hapus baris siswa">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- ============================================================ --}}
                {{-- PART 1: JAM PELAJARAN (AUTOMATIC SELECTION)                 --}}
                {{-- ============================================================ --}}
                <div class="col-12" id="keluarPart1">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge rounded-pill bg-primary-subtle text-primary-emphasis border border-primary-subtle">Part 1</span>
                        <h6 class="fw-bold text-dark mb-0">Jam Pelajaran yang Ditinggalkan <span class="text-danger">*</span></h6>
                    </div>
                    <p class="text-muted small mb-2">Tentukan JP / Mapel yang ditinggalkan. Jam yang sedang berlangsung terpilih otomatis.</p>

                    {{-- Quick-select helper --}}
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="text-muted small align-self-center">Pilih cepat:</span>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-3" id="btnJamSekarang">
                            <i class="bi bi-clock me-1"></i>Jam Sekarang
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-warning rounded-3" id="btnSisaJam">
                            <i class="bi bi-arrow-down-circle me-1"></i>Sisa Jam Hari Ini
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger rounded-3" id="btnBersihkanJam">
                            <i class="bi bi-x-circle me-1"></i>Bersihkan
                        </button>
                    </div>

                   <div class="row g-3 mb-2">
                        {{-- Mulai JP — satu dropdown saja (tidak ada 'Sampai JP') --}}
                        <div class="col-md-6 col-lg-4">
                            <label for="dari_jp" class="form-label fw-semibold text-secondary small">Mulai Jam Pelajaran (JP) <span class="text-danger">*</span></label>
                            <select name="dari_jp" id="dari_jp" class="form-select rounded-3">
                                <option value="">-- Pilih JP Mulai --</option>
                                @foreach($jamPelajaranList as $jp)
                                    <option value="{{ $jp->id }}" data-jam-ke="{{ $jp->jam_ke }}"
                                            {{ (string) old('dari_jp') === (string) $jp->id
                                                || (old('dari_jp') === null && $currentJp && (int) $currentJp->id === (int) $jp->id)
                                                    ? 'selected' : '' }}>
                                        JP {{ $jp->jam_ke }} ({{ substr((string) $jp->jam_mulai, 0, 5) }} - {{ substr((string) $jp->jam_selesai, 0, 5) }})
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">Jam yang sedang berlangsung terpilih otomatis.</div>
                        </div>
                    </div>
                    {{-- Input tersembunyi: sampai_jp & jam_keluar_jp di-sync otomatis = dari_jp (diisi JS) --}}
                    <input type="hidden" name="sampai_jp" id="sampai_jp">
                    <input type="hidden" name="jam_keluar_jp" id="jam_keluar_jp">
                    {{-- Input tersembunyi jam_ke[] di-generate JS dari rentang terpilih --}}
                    <div id="jamKeHiddenContainer" class="d-none"></div>
                    <div id="jamKeError" class="text-danger small mt-2 d-none">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>Pilih Mulai Jam Pelajaran yang ditinggalkan.
                    </div>
                </div>



                {{-- ============================================================ --}}
                {{-- PART 3: RENCANA JAM KEMBALI KE SEKOLAH                      --}}
                {{-- ============================================================ --}}
                <div class="col-12" id="keluarPart3">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge rounded-pill bg-info-subtle text-info-emphasis border border-info-subtle">Part 2</span>
                        <h6 class="fw-bold text-dark mb-0">Kembali ke Sekolah Hari Ini</h6>
                    </div>
                    <p class="text-muted small mb-2">
                        Bila <strong>tidak</strong> dicentang, izin berlaku hingga jam pelajaran terakhir pada hari tersebut
                        dan tidak ada pemantauan kembali. Centang bila siswa akan kembali pada hari yang sama: Satpam akan
                        mengonfirmasi kedatangan kembali; bila siswa belum kembali hingga batas (Jam Kembali + 1 JP), surat
                        otomatis berstatus <strong>Mangkir / Bolos</strong> dan presensi JP terkait menjadi <strong>Alfa</strong>.
                    </p>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="kembali_hari_ini" value="1" id="kembali_hari_ini"
                               {{ old('kembali_hari_ini') !== null ? (old('kembali_hari_ini') ? 'checked' : '') : 'checked' }}>
                        <label class="form-check-label fw-semibold" for="kembali_hari_ini">
                            Siswa akan kembali ke sekolah hari ini
                        </label>
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
                                @foreach($jamPelajaranList as $jp)
                                    @php
                                        $jpTerpilih = (string) old('jam_masuk_jp') === (string) $jp->jam_ke
                                            || (old('jam_masuk_jp') === null
                                                && !empty($jamMasukDefault)
                                                && (string) $jamMasukDefault === (string) $jp->jam_ke);
                                    @endphp
                                    <option value="{{ $jp->jam_ke }}" data-jam-ke="{{ $jp->jam_ke }}"
                                            @selected($jpTerpilih)>
                                        JP {{ $jp->jam_ke }} ({{ substr((string) $jp->jam_mulai, 0, 5) }} - {{ substr((string) $jp->jam_selesai, 0, 5) }})
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
                        <div class="col-12" id="alasanDetailWrap">
                            <label for="alasan_detail" class="form-label fw-semibold text-secondary small">Detail / Catatan Alasan <span class="text-danger">*</span></label>
                            <textarea name="alasan_detail" id="alasan_detail" rows="2" class="form-control rounded-3" maxlength="500" required
                                      placeholder="Contoh: Ban motor bocor di perjalanan, mengurus surat di TU, dll.">{{ old('alasan_detail') }}</textarea>
                            <div class="form-text">Tuliskan rincian / catatan alasan keterlambatan atau ketidakhadiran siswa.</div>
                        </div>
                    </div>
                </div>



{{-- Tanda Tangan Guru Piket (wajib digambar) --}}
                <div class="col-12">
                    <label class="form-label fw-bold text-secondary text-uppercase small">
                        Tanda Tangan Guru Piket (Penyetuju) <span class="text-danger">*</span>
                    </label>
                    <div class="border rounded-3 p-3 bg-light-subtle">
                        <canvas id="canvasTtdGuru" width="600" height="220" class="form-control rounded-3 bg-white"
                                style="height: auto; touch-action: none; cursor: crosshair;">Browser Anda tidak mendukung Canvas.</canvas>
                        <div class="form-text mt-2">
                            Gambar tanda tangan Guru Piket pada kotak di atas menggunakan mouse, stylus, atau jari (layar sentuh).
                            Tanda tangan ini menjadi tanda ACC otomatis pada surat.
                        </div>
                        <div class="d-flex justify-content-end mt-2">
                            <button type="button" id="btnBersihTtd" class="btn btn-sm btn-outline-danger rounded-3">
                                <i class="bi bi-eraser me-1"></i>Bersihkan
                            </button>
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

            {{-- Tempat input tersembunyi TTD digital per siswa (disejajarkan dengan id_siswa[]) --}}
            <div id="ttdSiswaInputs" class="d-none"></div>
        </form>
    </div>

    {{-- ============================================================ --}}
    {{-- MODAL TTD DIGITAL BERURUTAN (SISWA DEMI SISWA, KOLEKTIF)   --}}
    {{-- ============================================================ --}}
    <div class="modal fade" id="ttdWizardModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-header border-0 pb-0">
                    <div>
                        <h5 class="fw-black mb-0"><i class="bi bi-pen me-1"></i> Tanda Tangan Digital Siswa</h5>
                        <p class="text-muted small mb-0">Gambar TTD di kotak di bawah. Klik <strong>Riset Canvas</strong> bila ingin mengulang.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
                        <span class="badge rounded-pill bg-primary-subtle text-primary-emphasis border border-primary-subtle px-3 py-2" style="font-size: 0.85rem;">
                            Tanda Tangan Siswa <span id="ttdStepCurrent">1</span> dari <span id="ttdStepTotal">1</span>
                        </span>
                        <span class="text-dark fw-semibold" id="ttdStepSiswa">—</span>
                    </div>
                    <canvas id="canvasTtdWizard" width="600" height="220" class="form-control rounded-3 bg-white"
                            style="height: auto; touch-action: none; cursor: crosshair;">Browser Anda tidak mendukung Canvas.</canvas>
                    <div id="ttdWizardError" class="text-danger small mt-2 d-none">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>Gambar tanda tangan siswa terlebih dahulu (canvas masih kosong).
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-danger rounded-3" id="btnRisetWizard">
                        <i class="bi bi-eraser me-1"></i> Riset Canvas
                    </button>
                    <button type="button" class="btn btn-success rounded-3 px-4 fw-semibold" id="btnTtdNext">
                        <i class="bi bi-arrow-right-circle me-1"></i> Lanjut ke Siswa Berikutnya
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<style>
    @keyframes biSpin {
        to { transform: rotate(360deg); }
    }
    .spin {
        display: inline-block;
        animation: biSpin 1s linear infinite;
    }
</style>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // ===== Auto-detect jadwal mapel/guru dari tanggal + siswa + jam ke- =====
        const tanggalInput   = document.querySelector('input[name="tanggal"]');
        const selectJadwal   = document.getElementById('id_jadwal');
        const HARI_INDONESIA = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        const dariJpSelect    = document.getElementById('dari_jp');
        const sampaiJpSelect  = document.getElementById('sampai_jp');
        const jamKembaliJpSelect = document.getElementById('jam_kembali_jp');
        const jamKeHiddenWrap = document.getElementById('jamKeHiddenContainer');
        const jpRangeInfo     = document.getElementById('jpRangeInfo');
        const badgeJpRange    = document.getElementById('badgeJpRange');
        let jamKembaliManual = false;

        // =====================================================================
        // Jam Pelajaran: auto-select berdasarkan waktu sekarang + quick-select
        // =====================================================================
        @php
            $masterJamJson = json_encode(
                $jamPelajaran->map(fn ($jp) => [
                    'id'       => (int) $jp->id,
                    'jam_ke'   => (int) $jp->jam_ke,
                    'mulai'    => $jp->jam_mulai,
                    'selesai'  => $jp->jam_selesai,
                    'jenis'    => $jp->jenis,
                    'kategori' => $jp->kategori_hari,
                ])->values()->all()
            );
        @endphp
        // Master jam pelajaran lengkap (dari server) untuk mencocokkan waktu sekarang
        const MASTER_JAM = {!! $masterJamJson !!};

        @php
            $masterJpPerHariJson = json_encode($jamPelajaranPerHari);
        @endphp
        // Master JP dikelompokkan per kategori hari (Senin-Kamis / Jumat) untuk
        // dropdown 'Dari JP'/'Sampai JP' dinamis mengikuti TANGGAL dispen terpilih.
        const MASTER_JP_PER_HARI = {!! $masterJpPerHariJson !!};
        // Endpoint untuk memuat ulang master JP per tanggal (refresh sinkron).
        const JAM_PELAJARAN_URL  = "{{ route('piket.dispensasi.jam-pelajaran') }}";

        function waktuToHari(waktu) {
            const [h, m] = String(waktu || '').split(':').map(Number);
            return (h || 0) * 60 + (m || 0);
        }

        // Daftar slot JP sesuai kategori hari sekarang (Jumat vs Senin-Kamis).
        function masterJamHariIni() {
            const d = new Date();
            const kategori = d.getDay() === 5 ? 'Jumat' : 'Senin-Kamis';
            return MASTER_JAM.filter(function (jp) {
                return jp.kategori === kategori && jp.jam_ke;
            });
        }

        // =====================================================================
        // Master JP DINAMIS MENGIKUTI TANGGAL DISPEN: saat tanggal diubah, seluruh
        // dropdown JP (Dari/Sampai, Keluar, Kembali, Masuk) di-render ulang sesuai
        // kategori hari tanggal tersebut (Jumat vs Senin-Kamis).
        // =====================================================================

        // Kategori hari ('Jumat' bila Jumat, selainnya 'Senin-Kamis') dari tanggal.
        function kategoriUntukTanggal(tanggalStr) {
            let d = tanggalStr ? new Date(tanggalStr + 'T00:00:00') : null;
            if (!d || isNaN(d.getTime())) d = new Date();
            return d.getDay() === 5 ? 'Jumat' : 'Senin-Kamis';
        }

        // Tanggal hari ini (local) dalam format YYYY-MM-DD — sama dengan nilai input[type=date].
        function tanggalHariIni() {
            const d = new Date();
            const mm = String(d.getMonth() + 1).padStart(2, '0');
            const dd = String(d.getDate()).padStart(2, '0');
            return d.getFullYear() + '-' + mm + '-' + dd;
        }

        // Master JP (dari MASTER_JP_PER_HARI) untuk sebuah tanggal dispen.
        function masterJpUntukTanggal(tanggalStr) {
            const kat = kategoriUntukTanggal(tanggalStr);
            return MASTER_JP_PER_HARI[kat] || MASTER_JP_PER_HARI['Senin-Kamis'] || [];
        }

        // Tanggal yang sedang aktif dipilih pada form (fallback: hari ini).
        function jpTanggalAktif() {
            return (tanggalInput && tanggalInput.value) ? tanggalInput.value : tanggalHariIni();
        }

        // Cek apakah sebuah nilai (value option) masih ada pada select.
        function optionAda(select, value) {
            if (!select) return false;
            for (const opt of select.options) {
                if (opt.value !== '' && opt.value === String(value || '')) return true;
            }
            return false;
        }

        // ID master JP yang urutannya (jam_ke) cocok di dalam daftar master.
        function idJpUntukUrutan(daftar, urutan) {
            for (const jp of (daftar || [])) {
                if (jp.jam_ke === urutan) return jp.id;
            }
            return null;
        }

        // Render option select dari daftar master JP. pakaiId = true bila value
        // option harus id master (Dari/Sampai JP); false bila value = nomor jam_ke
        // (JP Keluar / Kembali / Masuk — yang disimpan backend sebagai angka).
        function renderJpOptions(select, daftar, nilaiTerpilih, labelPertama, pakaiId) {
            if (!select) return;
            select.innerHTML = '';
            const optPertama = document.createElement('option');
            optPertama.value = '';
            optPertama.textContent = labelPertama || '-- Pilih --';
            select.appendChild(optPertama);

            (daftar || []).forEach(function (jp) {
                const value = pakaiId ? String(jp.id) : String(jp.jam_ke);
                const opt = document.createElement('option');
                opt.value = value;
                opt.dataset.jamKe = String(jp.jam_ke);
                opt.textContent = 'JP ' + jp.jam_ke + ' (' + String(jp.mulai || '').slice(0, 5) + ' - ' + String(jp.selesai || '').slice(0, 5) + ')';
                if (nilaiTerpilih !== null && nilaiTerpilih !== undefined && String(nilaiTerpilih) === value) {
                    opt.selected = true;
                }
                select.appendChild(opt);
            });
        }

        // Terapkan daftar master JP untuk satu tanggal ke semua dropdown JP:
        // pertahankan pilihan yang masih ada pada daftar baru, selainnya isi
        // default (jam aktif bila tanggal hari ini; JP-1 bila tanggal lain).
        function terapkanJpUntukTanggal(tanggalStr, daftar) {
            if (!tanggalStr) tanggalStr = jpTanggalAktif();
            const isToday = tanggalStr === tanggalHariIni();

            // ---- Part 1: Dari JP (nilai = id model) — sampai_jp hidden, auto-sync ----
            const dariSebelum = dariJpSelect ? dariJpSelect.value : '';
            const pertahankanDari = optionAda(dariJpSelect, dariSebelum) ? dariSebelum : null;

            renderJpOptions(dariJpSelect, daftar, pertahankanDari, '-- Pilih JP Mulai --', true);

            if (!dariJpSelect || !dariJpSelect.value) {
                let urutanDefault = isToday ? jamKeSekarang() : null;
                if (urutanDefault === null && daftar.length) urutanDefault = daftar[0].jam_ke;
                const idDefault = idJpUntukUrutan(daftar, urutanDefault);
                if (dariJpSelect && idDefault) dariJpSelect.value = String(idDefault);
            }

            // ---- Part 2: Jam Kembali (nilai = jam_ke) ----
            const kembaliSebelum = jamKembaliJpSelect ? jamKembaliJpSelect.value : '';
            if (jamKembaliManual && optionAda(jamKembaliJpSelect, kembaliSebelum)) {
                renderJpOptions(jamKembaliJpSelect, daftar, kembaliSebelum, '-- Pilih Jam Pelajaran Kembali --', false);
            } else {
                renderJpOptions(jamKembaliJpSelect, daftar, '', '-- Pilih Jam Pelajaran Kembali --', false);
                jamKembaliManual = false;
            }

            // ---- Boleh Masuk Mulai JP Ke- (nilai = jam_ke) ----
            const masukSebelum = jamMasukJpSelect ? jamMasukJpSelect.value : '';
            let masukBaru = optionAda(jamMasukJpSelect, masukSebelum) ? masukSebelum : null;
            if (masukBaru === null && isToday) masukBaru = saranJamMasuk();
            if (masukBaru === null && daftar.length) masukBaru = String(daftar[0].jam_ke);
            renderJpOptions(jamMasukJpSelect, daftar, masukBaru, '-- Pilih JP --', false);

            // Sinkronkan hidden inputs & jam_ke[] tersembunyi.
            onJamRangeChanged();
        }

        // Muat ulang semua dropdown JP berdasarkan tanggal yang dipilih: terapkan
        // master lokal secara instan, lalu segarkan dari server untuk memastikan
        // data terbaru (fallback: data lokal tetap dipakai bila request gagal).
        function updateJpDropdowns(tanggal) {
            const tgl = tanggal || jpTanggalAktif();
            terapkanJpUntukTanggal(tgl, masterJpUntukTanggal(tgl));

            fetch(JAM_PELAJARAN_URL + '?tanggal=' + encodeURIComponent(tgl), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (res) { return res.json(); })
            .then(function (json) {
                // Abaikan hasil bila tanggal sudah berubah sejak fetch dimulai
                // (hindari respon lama menimpa pilihan yang baru saja dirender).
                const aktif = (tanggalInput && tanggalInput.value) ? tanggalInput.value : '';
                if (json && json.error === false && Array.isArray(json.data) && aktif === tgl) {
                    terapkanJpUntukTanggal(tgl, json.data);
                }
            })
            .catch(function () { /* abaikan: pakai data master lokal */ });
        }

        // Tentukan jam_ke yang sedang berlangsung berdasarkan master jam pelajaran.
        // Mengembalikan null jika tidak ada JP yang berlangsung.
        function jamKeSekarang() {
            const d = new Date();
            let now = d.getHours() * 60 + d.getMinutes();

            // Cocokkan dengan slot yang sedang berlangsung (now >= mulai && now < selesai)
            for (const jp of masterJamHariIni()) {
                const mulai = waktuToHari(jp.mulai);
                const selesai = waktuToHari(jp.selesai);
                if (mulai === 0 && selesai === 0) continue;
                if (now >= mulai && now < selesai) return jp.jam_ke;
            }
            return null;
        }

        // Saran JP "Boleh Masuk": JP aktif -> JP berikutnya -> JP terakhir hari ini.
        function saranJamMasuk() {
            const aktif = jamKeSekarang();
            if (aktif !== null) return aktif;

            const d = new Date();
            const menit = d.getHours() * 60 + d.getMinutes();
            let next = null;
            let last = null;
            for (const jp of masterJamHariIni()) {
                const mulai = waktuToHari(jp.mulai);
                if (next === null && menit < mulai) next = jp.jam_ke;
                last = jp.jam_ke;
            }
            if (next !== null) return next;
            return last;
        }

        // Isi default dropdown "Boleh Masuk Mulai JP Ke-" (jangan timpa pilihan user).
        function autoSelectJamMasukJp() {
            if (!jamMasukJpSelect || jamMasukJpSelect.value !== '') return;
            const jk = saranJamMasuk();
            if (jk !== null) jamMasukJpSelect.value = String(jk);
        }

        function autoSelectJamSekarang() {
            const jamKe = jamKeSekarang();
            if (jamKe === null || !dariJpSelect) return false;
            let idOpt = null;
            for (const opt of dariJpSelect.options) {
                if (opt.value && parseInt(opt.dataset.jamKe || '0', 10) === jamKe) {
                    idOpt = opt;
                    break;
                }
            }
            if (!idOpt) return false;
            dariJpSelect.value = String(idOpt.value);
            syncSampaiJpHidden();
            return true;
        }

        // =====================================================================
        // RANGE 'Dari JP' s/d 'Sampai JP': baca rentang terpilih & kumpulkan
        // semua JP dalam range menjadi jam_ke[] untuk dikirim saat submit.
        // =====================================================================
        function getSelectedRange() {
            if (!dariJpSelect || !dariJpSelect.value) return null;
            const dariOpt = dariJpSelect.selectedOptions[0];
            const dariKe  = parseInt(dariOpt ? dariOpt.dataset.jamKe : '0', 10);
            if (isNaN(dariKe)) return null;
            return { min: dariKe, max: dariKe };
        }

        // Semua jam_ke dalam rentang [Dari JP .. Sampai JP] (berurutan naik).
        // Mengikuti master JP dari TANGGAL yang dipilih (bukan hanya hari browser
        // sekarang) agar jam_ke[] selalu selaras dengan jadwal tanggal dispen.
        function jamTerpilih() {
            const range = getSelectedRange();
            if (!range) return [];
            const result = [];
            masterJpUntukTanggal(jpTanggalAktif()).forEach(function (jp) {
                if (jp.jam_ke >= range.min
                    && jp.jam_ke <= range.max
                    && result.indexOf(jp.jam_ke) === -1) {
                    result.push(jp.jam_ke);
                }
            });
            return result.sort(function (a, b) { return a - b; });
        }

        function updateJpRangeUi(count, detail) {
            const label = count === 0 ? '0 JP' : (count + ' JP');
            if (badgeJpRange) badgeJpRange.textContent = label;
            if (jpRangeInfo) {
                jpRangeInfo.innerHTML = count === 0
                    ? 'Terpilih: <strong>0 JP</strong>'
                    : 'Terpilih: <strong>' + label + '</strong><span style="font-size:0.8rem;"> ' + (detail || '') + '</span>';
            }
        }

        // Bangun input tersembunyi jam_ke[] dari seluruh JP dalam rentang terpilih.
        function updateJamKeHidden() {
            if (!jamKeHiddenWrap) return;
            jamKeHiddenWrap.innerHTML = '';
            const jams = jamTerpilih();
            jams.forEach(function (j) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'jam_ke[]';
                input.value = String(j);
                jamKeHiddenWrap.appendChild(input);
            });
            updateJpRangeUi(jams.length, jams.length ? '(JP ' + jams.join(', ') + ')' : '');
        }

        // Sync hidden sampai_jp = dari_jp saat dari_jp berubah.
        function syncSampaiJpHidden() {
            const sampaiHidden = document.getElementById('sampai_jp');
            if (sampaiHidden && dariJpSelect) {
                sampaiHidden.value = dariJpSelect.value;
            }
        }

        function clearJamRange() {
            if (dariJpSelect) dariJpSelect.value = '';
            syncSampaiJpHidden();
            updateJamKeHidden();
        }

        // enforceJamRange: no longer needed (single dropdown) — kept as no-op for compatibility.
        function enforceJamRange() {}

        // Handler terpusat saat rentang JP berubah: validasi & sinkronkan Part 2/3,
        // jadwal & tampilan. clearJamKeError/sync/filterJadwalOptions adalah function
        // declaration (hoisted) yang didefinisikan di bagian bawah script.
        function onJamRangeChanged() {
            syncSampaiJpHidden();
            updateJamKeHidden();
            clearJamKeError();
            syncJamKeluarJp();
            syncJamKembaliJp();
            filterJadwalOptions();
        }

        // Hapus tanda error "belum ada JP dipilih" (dipanggil kapan saja saat init,
        // karena itu query DOM langsung tanpa bergantung pada binding ttdCanvas).
        function clearJamKeError() {
            const err = document.getElementById('jamKeError');
            if (err) err.classList.add('d-none');
            const part1 = document.getElementById('keluarPart1');
            if (part1) part1.classList.remove('border-danger', 'border', 'rounded-3');
        }

        if (dariJpSelect) dariJpSelect.addEventListener('change', onJamRangeChanged);
        // sampai_jp is now a hidden input synced from dari_jp; no change listener needed.

        // Auto-select saat halaman pertama dimuat hanya bila belum ada nilai old()
        var adaOldJam   = `{{ old('jam_ke') ? '1' : '0' }}` === '1';
        var adaOldRange = `{{ old('dari_jp') ? '1' : '0' }}` === '1';

        if (!adaOldJam && !adaOldRange && (!dariJpSelect || !dariJpSelect.value)) {
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
                onJamRangeChanged();
            });
        }

        if (btnSisaJam) {
            btnSisaJam.addEventListener('click', function () {
                if (!dariJpSelect) return;
                const ops = Array.from(dariJpSelect.options).filter(function (o) { return o.value !== ''; });
                if (ops.length === 0) return;
                const jamKe = jamKeSekarang();
                let fromOpt = ops[0];
                if (jamKe !== null) {
                    const found = ops.find(function (o) { return parseInt(o.dataset.jamKe || '0', 10) === jamKe; });
                    if (found) fromOpt = found;
                }
                dariJpSelect.value = fromOpt.value;
                syncSampaiJpHidden();
                onJamRangeChanged();
            });
        }

        if (btnPilihManual) {
            btnPilihManual.addEventListener('click', function () {
                // Tidak mengubah apa-apa; hanya mengarahkan pandangan guru ke rentang JP.
                const wrap = document.getElementById('keluarPart1');
                if (wrap) wrap.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        }

        if (btnBersihkan) {
            btnBersihkan.addEventListener('click', function () {
                clearJamRange();
                onJamRangeChanged();
            });
        }

        // ---- jam_keluar_jp: auto-sync as hidden input from dari_jp's jam_ke ----
        const jamKeluarJpHidden = document.getElementById('jam_keluar_jp');

        function syncJamKeluarJp() {
            if (!jamKeluarJpHidden || !dariJpSelect || !dariJpSelect.value) return;
            const dariOpt = dariJpSelect.selectedOptions[0];
            const dariKe  = parseInt(dariOpt ? dariOpt.dataset.jamKe : '0', 10);
            if (!isNaN(dariKe) && dariKe > 0) {
                jamKeluarJpHidden.value = String(dariKe);
            }
        }

        // Initial sync on load
        if (!adaOldJam) {
            syncJamKeluarJp();
        }

        // ---- Part 2: Kembali Hari Ini (checkbox boolean) ----
        const kembaliCheck = document.getElementById('kembali_hari_ini');
        function syncJamKembaliJp() {}
        function setJamKembaliState() {}
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

        const keluarSections = [
            document.getElementById('keluarPart1'),
            document.getElementById('keluarPart2'),
            document.getElementById('keluarPart3'),
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
                clearJamRange();
                if (selectJadwal) selectJadwal.value = '';
                if (jamKeluarJpSelect) jamKeluarJpSelect.value = '';
                autoSelectJamMasukJp();
            } else {
                if (jamTerpilih().length === 0) {
                    autoSelectJamSekarang();
                }
                syncJamKeluarJp();
                syncJamKembaliJp();
                setJamKembaliState();
                filterJadwalOptions();
            }
        }

        if (tabDispenKeluar) tabDispenKeluar.addEventListener('click', function () {
            tipeDispenInput.value = 'keluar';
            setTipeDispen('keluar');
            applyTerlambatGroups();
        });
        if (tabDispenMasuk) tabDispenMasuk.addEventListener('click', function () {
            tipeDispenInput.value = 'masuk';
            setTipeDispen('masuk');
            applyTerlambatGroups();
        });

        // Inisialisasi sesuai nilai tersimpan / old()
        setTipeDispen(tipeDispenInput ? tipeDispenInput.value : 'keluar');
        // Isi default "Boleh Masuk Mulai JP Ke-" sejak halaman dibuka (mode apa pun),
        // asalkan belum ada nilai old() — tetap bisa dioverride manual oleh Guru Piket.
        autoSelectJamMasukJp();
        // ====================================================================

        // =====================================================================
        // MULTI-SISWA ROWS: tiap baris = Pilih Kelas → Muat Siswa (AJAX ringan)
        // =====================================================================
        const SISWA_URL            = "{{ route('piket.dispensasi.siswa-by-kelas') }}";
        const siswaRowsContainer   = document.getElementById('siswaRowsContainer');
        const btnTambahSiswa       = document.getElementById('btnTambahSiswa');
        const siswaRowTemplate     = document.getElementById('templateSiswaRow');
        const siswaErrorBox        = document.getElementById('siswaError');

        // Data siswa terlambat hari ini (catatan Satpam) untuk quick-fill di dropdown.
        let terlambatData = @json($terlambatJson ?? []);
        // Flag mode aktif: 'masuk' menampilkan group "Siswa Terlambat" di dropdown siswa.
        let modeMasuk = false;

        function setSiswaError(show) {
            if (siswaErrorBox) siswaErrorBox.classList.toggle('d-none', !show);
        }

        function getFilledRows() {
            return Array.from(document.querySelectorAll('.siswa-row')).filter(function (row) {
                const s = row.querySelector('.row-siswa');
                return s && s.value !== '';
            });
        }

        // Kelas dari baris terisi pertama (acuan filter jadwal mapel/guru).
        function getPrimaryKelas() {
            const rows = getFilledRows();
            if (rows.length === 0) return '';
            const opt = rows[0].querySelector('.row-siswa').selectedOptions[0];
            return (opt && opt.dataset.kelas) || '';
        }

        // Kumpulkan id siswa yang sudah terpilih di seluruh baris aktif.
        function collectSelectedStudents() {
            const picked = {};
            getFilledRows().forEach(function (row) {
                const s = row.querySelector('.row-siswa');
                if (s && s.value) picked[String(s.value)] = true;
            });
            return picked;
        }

        // Sinkronkan opsi: siswa yang sudah dipilih di baris lain di-disable
        // di semua dropdown lain (nilai terpilih di baris itu sendiri tetap aktif).
        // Dicek setiap opsi-nya dibangun ulang / berubah (kelas, siswa, tambah/hapus baris).
        function updateSelectedStudentsState() {
            const picked = collectSelectedStudents();
            document.querySelectorAll('.siswa-row').forEach(function (row) {
                const s = row.querySelector('.row-siswa');
                if (!s) return;
                const sel = String(s.value);
                Array.from(s.options).forEach(function (opt) {
                    if (!opt.value) return;
                    opt.disabled = picked[String(opt.value)] === true && String(opt.value) !== sel;
                });
            });
        }

        // -------------------------------------------------------------------
        // INTEGRASI SISWA TERLAMBAT (catatan Satpam) ke dropdown siswa utama
        // -------------------------------------------------------------------
        // Bangun satu opsi siswa normal (tanpa group terlambat).
        // Label = "{no_absen}. {nama} ({nisn})" ; no_absen = urutan dalam kelas (di-derive).
        function regularOption(s, kelasId, noAbsen) {
            const opt = document.createElement('option');
            opt.value = String(s.id);
            opt.dataset.nama  = String(s.nama || '').toLowerCase();
            opt.dataset.nisn  = String(s.nisn || '').toLowerCase();
            opt.dataset.kelas = String(kelasId);
            const nomor = (noAbsen != null) ? String(noAbsen).padStart(2, '0') + '. ' : '';
            opt.textContent   = nomor + s.nama + ' (' + (s.nisn || 'Tanpa NISN') + ')';
            return opt;
        }

        // Bangun satu opsi siswa terlambat (bawa data catatan Satpam untuk auto-fill).
        function terlambatOption(d) {
            const opt = document.createElement('option');
            opt.value = String(d.id_siswa);
            opt.dataset.catatanId = String(d.id);
            opt.dataset.nama      = String(d.nama || '').toLowerCase();
            opt.dataset.nisn      = String(d.nisn || '').toLowerCase();
            opt.dataset.kelas     = String(d.kelas_id);
            opt.dataset.jamMasuk  = d.jam_masuk || '';
            opt.dataset.keterangan = d.keterangan || '';
            opt.dataset.saranJp   = d.saran_jp ? String(d.saran_jp) : '';
            opt.textContent = '\u23F0 ' + d.nama + ' (' + (d.nisn || 'Tanpa NISN')
                + (d.jam_masuk ? ', ' + String(d.jam_masuk).slice(0, 5) + ' WIB' : '') + ')';
            return opt;
        }

        // Bangun ulang pilihan baris: group "Terlambat" (selalu di atas, mode masuk)
        // + group "Semua Siswa" (hasil fetch kelas, siswa terlambat tidak diduplikasi).
        function renderRowOptions(row, kelasId, siswaList) {
            const siswa = row.querySelector('.row-siswa');
            if (!siswa) return;

            siswa.innerHTML = '<option value="" data-nama="" data-nisn="" selected>-- Pilih Siswa --</option>';

            const terlambatKelas = new Set(
                terlambatData.filter(function (d) {
                    return String(d.kelas_id) === String(kelasId);
                }).map(function (d) { return String(d.id_siswa); })
            );

            if (modeMasuk && terlambatData.length > 0) {
                const gTerlambat = document.createElement('optgroup');
                gTerlambat.label = 'Siswa Terlambat Hari Ini (Satpam)';
                terlambatData.forEach(function (d) { gTerlambat.appendChild(terlambatOption(d)); });
                siswa.appendChild(gTerlambat);
            }

            if (kelasId && siswaList && siswaList.length > 0) {
                // Urutan tetap (mis. by nama); no_absen = indeks urutan di kelas.
                const list = [].concat(siswaList).sort(function (a, b) {
                    return String(a.nama || '').localeCompare(String(b.nama || ''));
                });
                const gManual = document.createElement('optgroup');
                gManual.label = 'Semua Siswa (Pilihan Manual)';
                let nomor = 0;
                list.forEach(function (s) {
                    if (terlambatKelas.has(String(s.id))) return;
                    nomor += 1;
                    gManual.appendChild(regularOption(s, kelasId, nomor));
                });
                if (gManual.children.length > 0) siswa.appendChild(gManual);
            }

            // Sync state: disable siswa yang sudah dipilih baris lain.
            updateSelectedStudentsState();
        }

        // Auto-fill form saat user memilih siswa dari group terlambat.
        function autoFillFromTerlambat(row, opt) {
            if (!opt || !opt.dataset.catatanId) return;
            const hid = row.querySelector('.row-catatan');
            if (hid) hid.value = String(opt.dataset.catatanId);

            const kelasSel = row.querySelector('.row-kelas');
            const kelasOpt = opt.dataset.kelas || '';
            if (kelasSel && String(kelasSel.value || '') !== String(kelasOpt)) {
                kelasSel.value = String(kelasOpt);
                loadRowSiswa(row, String(kelasOpt), opt.value);
            }

            const jp = document.getElementById('jam_masuk_jp');
            if (jp) {
                if (opt.dataset.saranJp) {
                    jp.value = String(opt.dataset.saranJp);
                } else if (!jp.value) {
                    const jk = saranJamMasuk();
                    if (jk !== null) jp.value = String(jk);
                }
            }
            const kat = document.getElementById('alasan_kategori');
            if (kat) kat.value = 'Terlambat Sekolah';
            const detail = document.getElementById('alasan_detail');
            if (detail) detail.value = opt.dataset.keterangan || '';
        }

        // Snapshot pilihan baris agar dapat dikembalikan setelah re-render.
        function captureSelection(siswa) {
            const opt = siswa.selectedOptions[0];
            if (!opt || !opt.value) return null;
            return {
                value: opt.value,
                nama: opt.dataset.nama || '',
                nisn: opt.dataset.nisn || '',
                kelas: opt.dataset.kelas || '',
                catatanId: opt.dataset.catatanId || '',
                jamMasuk: opt.dataset.jamMasuk || '',
                keterangan: opt.dataset.keterangan || '',
                saranJp: opt.dataset.saranJp || '',
                text: opt.textContent
            };
        }

        // Pulihkan pilihan setelah re-render; bila tak ada di daftar baru,
        // opsi lama ditambahkan kembali (mis. toggle mode menghilangkan group terlambat).
        function restoreSelection(siswa, prev, kelasId) {
            if (!prev) return;
            const found = siswa.querySelector('option[value="' + String(prev.value) + '"]');
            if (found) { siswa.value = String(prev.value); return; }
            const opt = document.createElement('option');
            opt.value = String(prev.value);
            opt.dataset.nama = prev.nama;
            opt.dataset.nisn = prev.nisn;
            opt.dataset.kelas = prev.kelas || String(kelasId || '');
            if (prev.catatanId) {
                opt.dataset.catatanId = prev.catatanId;
                opt.dataset.jamMasuk = prev.jamMasuk;
                opt.dataset.keterangan = prev.keterangan;
                opt.dataset.saranJp = prev.saranJp;
            }
            opt.textContent = prev.text;
            const manual = siswa.querySelector('optgroup[label="Semua Siswa (Pilihan Manual)"]');
            if (manual) manual.insertBefore(opt, manual.firstChild);
            else siswa.appendChild(opt);
            siswa.value = String(prev.value);
        }

        // Terapkan mode aktif ke seluruh baris siswa (group terlambat on/off) + hint.
        function applyTerlambatGroups() {
            if (tipeDispenInput) modeMasuk = tipeDispenInput.value === 'masuk';
            document.querySelectorAll('.siswa-row').forEach(function (row) {
                const select = row.querySelector('.row-siswa');
                if (!select) return;
                // Jangan ganggu baris hasil restore old(): opsinya dirender server dan
                // belum dimuat penuh (row._siswaData kosong). Baris kosong/baru di-render ulang.
                if (select.value !== '' && !row._siswaData) return;
                const prev = captureSelection(select);
                const kelasId = row.querySelector('.row-kelas') ? row.querySelector('.row-kelas').value : '';
                renderRowOptions(row, kelasId, row._siswaData || null);
                restoreSelection(select, prev, kelasId);
            });
            const hint = document.getElementById('terlambatHint');
            if (hint) hint.classList.toggle('d-none', !(modeMasuk && terlambatData.length > 0));
            filterJadwalOptions();
        }

        function loadRowSiswa(row, kelasId, selectSiswaId) {
            const siswa  = row.querySelector('.row-siswa');
            const status = row.querySelector('.row-status');
            if (!siswa) return;

            if (!kelasId) {
                row._siswaData = null;
                renderRowOptions(row, '', null);
                siswa.value = '';
                siswa.dataset.kelas = '';
                filterJadwalOptions();
                return;
            }

            if (status) status.classList.remove('d-none');
            siswa.disabled = true;
            siswa.innerHTML = '<option value="">-- Memuat data siswa... --</option>';

            fetch(SISWA_URL + '?kelas_id=' + encodeURIComponent(kelasId), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (res) { return res.json(); })
            .then(function (json) {
                if (json.error || !Array.isArray(json.data)) {
                    throw new Error(json.message || 'Gagal memuat data siswa');
                }
                row._siswaData = json.data;
                renderRowOptions(row, kelasId, json.data);
                siswa.disabled = false;
                if (selectSiswaId) {
                    const opt = siswa.querySelector('option[value="' + String(selectSiswaId) + '"]');
                    if (opt) siswa.value = String(selectSiswaId);
                }
                updateSelectedStudentsState();
            })
            .catch(function () {
                siswa.innerHTML = '<option value="">-- Gagal memuat data siswa --</option>';
                siswa.disabled = false;
            })
            .finally(function () {
                if (status) status.classList.add('d-none');
                filterJadwalOptions();
            });
        }

        function wireRow(row) {
            const kelas  = row.querySelector('.row-kelas');
            const siswa  = row.querySelector('.row-siswa');
            const remove = row.querySelector('.row-remove');

            if (kelas) {
                kelas.addEventListener('change', function () {
                    const hid = row.querySelector('.row-catatan');
                    if (hid) hid.value = '';
                    loadRowSiswa(row, kelas.value);
                });
            }
            if (siswa) {
                siswa.addEventListener('change', function () {
                    const v = this.value;

                    // Cegah siswa yang sama terpilih dua kali di baris berbeda.
                    if (v !== '') {
                        const dupe = Array.from(document.querySelectorAll('.siswa-row')).some(function (r) {
                            const o = r.querySelector('.row-siswa');
                            return o && o !== this && String(o.value) === String(v);
                        }.bind(this));
                        if (dupe) {
                            const nm = this.selectedOptions[0];
                            const nama = nm ? nm.textContent.replace(/^\d+\.\s*/, '') : v;
                            alert('Siswa "' + nama + '" sudah ditambahkan ke daftar!');
                            this.value = '';
                            filterJadwalOptions();
                            setSiswaError(false);
                            updateSelectedStudentsState();
                            return;
                        }
                    }

                    const opt = this.selectedOptions[0];
                    if (opt && opt.dataset.catatanId) {
                        autoFillFromTerlambat(row, opt);
                    } else {
                        const hid = row.querySelector('.row-catatan');
                        if (hid) hid.value = '';
                    }
                    filterJadwalOptions();
                    setSiswaError(false);
                    updateSelectedStudentsState();
                });
            }
            if (remove) {
                remove.addEventListener('click', function () {
                    row.remove();
                    toggleRemoveButtons();
                    filterJadwalOptions();
                    updateSelectedStudentsState();
                });
            }
        }

        function toggleRemoveButtons() {
            const rows = document.querySelectorAll('.siswa-row');
            rows.forEach(function (r) {
                const btn = r.querySelector('.row-remove');
                if (btn) btn.classList.toggle('d-none', rows.length === 1);
            });
        }

        function createSiswaRow() {
            const existingRows = document.querySelectorAll('.siswa-row');
            let selectedKelasId = '';
            if (existingRows.length > 0) {
                // Ambil kelas_id dari baris terakhir/sebelumnya yang sudah ada nilainya, atau baris pertama
                for (let i = existingRows.length - 1; i >= 0; i--) {
                    const kSelect = existingRows[i].querySelector('.row-kelas');
                    if (kSelect && kSelect.value) {
                        selectedKelasId = kSelect.value;
                        break;
                    }
                }
            }

            const node = siswaRowTemplate.content.cloneNode(true);
            const row = node.querySelector('.siswa-row');
            siswaRowsContainer.appendChild(row);
            wireRow(row);

            if (selectedKelasId) {
                const kSelectNew = row.querySelector('.row-kelas');
                if (kSelectNew) {
                    kSelectNew.value = selectedKelasId;
                    kSelectNew.dispatchEvent(new Event('change'));
                }
            }

            applyTerlambatGroups();
            toggleRemoveButtons();
            setSiswaError(false);
            return row;
        }

        if (btnTambahSiswa) {
            btnTambahSiswa.addEventListener('click', createSiswaRow);
        }

        // Wire baris yang dirender server (restore old()) + isi otomatis bila kosong.
        document.querySelectorAll('.siswa-row').forEach(function (r) { wireRow(r); });
        updateSelectedStudentsState();
        toggleRemoveButtons();
        if (document.querySelectorAll('.siswa-row').length === 0) {
            createSiswaRow();
        }

        // =====================================================================
        // SISWA TERLAMBAT (catatan Satpam): refresh data saat tanggal berubah
        // =====================================================================
        const TERLAMBAT_URL = "{{ route('piket.dispensasi.terlambat-hari-ini') }}";

        function refreshTerlambatData() {
            const tgl = tanggalInput ? tanggalInput.value : '';
            return fetch(TERLAMBAT_URL + '?tanggal=' + encodeURIComponent(tgl || ''), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (res) { return res.json(); })
            .then(function (json) {
                terlambatData = (json.error || !Array.isArray(json.data)) ? [] : json.data;
                applyTerlambatGroups();
            })
            .catch(function () {
                terlambatData = [];
                applyTerlambatGroups();
            });
        }

        if (tanggalInput) {
            tanggalInput.addEventListener('change', refreshTerlambatData);
        }

        // Terapkan group terlambat sesuai mode/tanggal awal.
        applyTerlambatGroups();
        // ====================================================================


        function filterJadwalOptions() {
            let dayName = '';
            if (tanggalInput && tanggalInput.value) {
                const d = new Date(tanggalInput.value + 'T00:00:00');
                if (!isNaN(d.getTime())) dayName = HARI_INDONESIA[d.getDay()] || '';
            }

            const kelasId  = getPrimaryKelas();
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

        if (tanggalInput) {
            tanggalInput.addEventListener('change', function () {
                onFilterChanged();
                // Saat tanggal dispen diubah, re-render seluruh dropdown JP
                // (Dari/Sampai, Keluar, Kembali, Masuk) sesuai kategori hari tanggal tsb.
                updateJpDropdowns(tanggalInput.value);
            });
        }
        // Isi jam_ke[] tersembunyi, ubah dropdown JP sesuai tanggal (awal) & sinkronkan
        // Part 2/3 + filter jadwal. Dipanggil di akhir init agar seluruh konstanta
        // (jamKeluarJpSelect, jamKembaliJpSelect, jamMasukJpSelect, dst) sudah ada.
        updateJpDropdowns(jpTanggalAktif());
        filterJadwalOptions();

        // ===== Tanda Tangan Guru Piket (canvas, wajib digambar) =====
        const ttdCanvas     = document.getElementById('canvasTtdGuru');
        const ttdHidden     = document.getElementById('ttdGuruBase64');
        const ttdError      = document.getElementById('ttdGuruError');
        const btnBersihTtd  = document.getElementById('btnBersihTtd');
        const formDispen    = document.getElementById('formDispen');

        let filled = false;

        if (ttdCanvas) {
            const ctx = ttdCanvas.getContext('2d');
            let drawing = false, inked = false, lastX = 0, lastY = 0;

            function initCanvas() {
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, ttdCanvas.width, ttdCanvas.height);
                ctx.strokeStyle = '#0f172a';
                ctx.lineWidth = 2.5;
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';
            }
            initCanvas();

            function getPos(e) {
                const rect = ttdCanvas.getBoundingClientRect();
                return {
                    x: (e.clientX - rect.left) * (ttdCanvas.width / rect.width),
                    y: (e.clientY - rect.top) * (ttdCanvas.height / rect.height),
                };
            }

            function refreshState() {
                filled = inked;
                ttdHidden.value = inked ? ttdCanvas.toDataURL('image/png') : '';
                if (inked) {
                    if (ttdError) ttdError.classList.add('d-none');
                    ttdCanvas.classList.remove('border-danger');
                }
            }

            function start(e) {
                e.preventDefault();
                drawing = true;
                inked = true;
                const p = getPos(e);
                lastX = p.x;
                lastY = p.y;
                ctx.beginPath();
                ctx.moveTo(lastX, lastY);
            }
            function move(e) {
                if (!drawing) return;
                e.preventDefault();
                const p = getPos(e);
                ctx.lineTo(p.x, p.y);
                ctx.stroke();
                lastX = p.x;
                lastY = p.y;
            }
            function end(e) {
                if (!drawing) return;
                drawing = false;
                ctx.closePath();
                refreshState();
            }

            ttdCanvas.addEventListener('pointerdown', start);
            ttdCanvas.addEventListener('pointermove', move);
            ttdCanvas.addEventListener('pointerup', end);
            ttdCanvas.addEventListener('pointercancel', end);
            ttdCanvas.addEventListener('touchstart', function (e) { e.preventDefault(); }, { passive: false });

            if (btnBersihTtd) {
                btnBersihTtd.addEventListener('click', function () {
                    initCanvas();
                    inked = false;
                    refreshState();
                });
            }
        }

        // ===== Canvas TTD Wizard Modal (Kolektif) =====
        const wizardCanvas   = document.getElementById('canvasTtdWizard');
        const btnRisetWizard = document.getElementById('btnRisetWizard');
        const ttdWizardError = document.getElementById('ttdWizardError');
        let wizardInked = false;

        if (wizardCanvas) {
            const wctx = wizardCanvas.getContext('2d');
            let wdrawing = false, wlastX = 0, wlastY = 0;

            function initWizardCanvas() {
                wctx.fillStyle = '#ffffff';
                wctx.fillRect(0, 0, wizardCanvas.width, wizardCanvas.height);
                wctx.strokeStyle = '#0f172a';
                wctx.lineWidth = 2.5;
                wctx.lineCap = 'round';
                wctx.lineJoin = 'round';
            }
            initWizardCanvas();

            function wGetPos(e) {
                const rect = wizardCanvas.getBoundingClientRect();
                return {
                    x: (e.clientX - rect.left) * (wizardCanvas.width / rect.width),
                    y: (e.clientY - rect.top) * (wizardCanvas.height / rect.height),
                };
            }

            function wStart(e) {
                e.preventDefault();
                wdrawing = true;
                wizardInked = true;
                const p = wGetPos(e);
                wlastX = p.x;
                wlastY = p.y;
                wctx.beginPath();
                wctx.moveTo(wlastX, wlastY);
                if (ttdWizardError) ttdWizardError.classList.add('d-none');
            }
            function wMove(e) {
                if (!wdrawing) return;
                e.preventDefault();
                const p = wGetPos(e);
                wctx.lineTo(p.x, p.y);
                wctx.stroke();
                wlastX = p.x;
                wlastY = p.y;
            }
            function wEnd(e) {
                if (!wdrawing) return;
                wdrawing = false;
                wctx.closePath();
            }

            wizardCanvas.addEventListener('pointerdown', wStart);
            wizardCanvas.addEventListener('pointermove', wMove);
            wizardCanvas.addEventListener('pointerup', wEnd);
            wizardCanvas.addEventListener('pointercancel', wEnd);
            wizardCanvas.addEventListener('touchstart', function (e) { e.preventDefault(); }, { passive: false });

            if (btnRisetWizard) {
                btnRisetWizard.addEventListener('click', function () {
                    initWizardCanvas();
                    wizardInked = false;
                    if (ttdWizardError) ttdWizardError.classList.add('d-none');
                });
            }

            resetWizardCanvas = function resetWizardCanvas() {
                initWizardCanvas();
                wizardInked = false;
                if (ttdWizardError) ttdWizardError.classList.add('d-none');
            };

            wizardCanvasBlank = function wizardCanvasBlank() {
                return !wizardInked;
            };
        }

        // Bagian Part 1 (Jam Pelajaran) untuk memberi tanda visual bila belum ada JP dipilih.
        const keluarPart1 = document.getElementById('keluarPart1');
        const jamKeError  = document.getElementById('jamKeError');

        // Field wajib yang sedang TERLIHAT sesuai mode aktif. Field di bagian yang
        // disembunyikan (d-none) dilewati agar tidak memblokir submit.
        function isTampil(el) {
            return !!(el && el.offsetParent !== null);
        }

        formDispen.addEventListener('submit', function (e) {
            // 1) Konversi canvas Tanda Tangan ke Data URL base64 dan isi input hidden
            if (ttdCanvas && ttdHidden) {
                ttdHidden.value = filled ? ttdCanvas.toDataURL('image/png') : '';
            }

            // 1b) Bangun ulang jam_ke[] tersembunyi dari rentang 'Dari JP' s/d 'Sampai JP'
            updateJamKeHidden();

            // 2) Normalisasi baris siswa
            const filledRows = getFilledRows();
            document.querySelectorAll('.siswa-row').forEach(function (row) {
                const s = row.querySelector('.row-siswa');
                const hid = row.querySelector('.row-catatan');
                if (!s) return;
                if (!s.value) {
                    s.removeAttribute('required');
                    s.removeAttribute('name');
                    if (hid) hid.removeAttribute('name');
                } else {
                    s.required = true;
                    s.name = 'id_siswa[]';
                    if (hid) hid.name = 'catatan_terlambat_id[]';
                }
            });

            // 3) Wajib minimal satu siswa dipilih.
            setSiswaError(false);
            if (filledRows.length === 0) {
                e.preventDefault();
                setSiswaError(true);
                if (siswaRowsContainer) siswaRowsContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }

            // 4) Kumpulkan field wajib yang tampak namun belum terisi.
            let invalidVisible = null;
            formDispen.querySelectorAll('input[required], select[required], textarea[required]').forEach(function (el) {
                if (invalidVisible) return;
                if (!isTampil(el)) return;
                if (!el.checkValidity()) invalidVisible = el;
            });

            // 5) Tanda tangan Guru Piket wajib digambar.
            if (!filled) {
                e.preventDefault();
                if (ttdError) ttdError.classList.remove('d-none');
                if (ttdCanvas) {
                    ttdCanvas.classList.add('border-danger');
                    ttdCanvas.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                return;
            }

            // 6) Mode KELUAR: minimal satu jam pelajaran harus dipilih (server wajibkan jam_ke).
            const isMasukMode = !!(tipeDispenInput && tipeDispenInput.value === 'masuk');
            const adaJamTerpilih = jamTerpilih().length > 0;
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

            // 7) Field wajib yang tampak belum diisi
            if (invalidVisible) {
                e.preventDefault();
                invalidVisible.reportValidity();
                invalidVisible.focus();
                return;
            }

            // 8) 2+ siswa (kolektif/rombongan) -> buka wizard TTD digital berurutan.
            if (filledRows.length >= 2) {
                e.preventDefault();
                openTtdWizard(filledRows);
            }
        });

        // =====================================================================
        // WIZARD TTD DIGITAL BERURUTAN (KOLEKTIF): siswa demi siswa
        // =====================================================================
        const ttdWizardModal    = document.getElementById('ttdWizardModal');
        const ttdStepCurrent    = document.getElementById('ttdStepCurrent');
        const ttdStepTotal      = document.getElementById('ttdStepTotal');
        const ttdStepSiswa      = document.getElementById('ttdStepSiswa');
        const btnTtdNext        = document.getElementById('btnTtdNext');
        const ttdSiswaInputs    = document.getElementById('ttdSiswaInputs');

        let wizardRows = [];
        let wizardIdx  = 0;

        // Bersihkan input tersembunyi TTD dari pengisian sebelumnya.
        if (ttdSiswaInputs) ttdSiswaInputs.innerHTML = '';

        openTtdWizard = function openTtdWizard(filledRows) {
            wizardRows = filledRows.map(function (r) { return r; });
            wizardIdx  = 0;
            renderWizardStep(0);
            const modal = new bootstrap.Modal(ttdWizardModal);
            modal.show();
        };

        if (ttdWizardModal) {
            ttdWizardModal.addEventListener('shown.bs.modal', function () {
                if (typeof resetWizardCanvas === 'function') resetWizardCanvas();
            });
        }

        function renderWizardStep(i) {
            if (!ttdWizardModal || !wizardRows.length) return;
            if (i >= wizardRows.length) { return; }

            wizardIdx = i;
            const row = wizardRows[i];
            const opt = row.querySelector('.row-siswa').selectedOptions[0];
            const kelasOpt = row.querySelector('.row-kelas').selectedOptions[0];
            const nama = opt ? opt.textContent.trim() : '—';
            const kelas = kelasOpt ? kelasOpt.textContent.trim() : '';

            if (ttdStepCurrent) ttdStepCurrent.textContent = String(i + 1);
            if (ttdStepTotal)   ttdStepTotal.textContent   = String(wizardRows.length);
            if (ttdStepSiswa)   ttdStepSiswa.textContent   = (kelas ? nama + ' (' + kelas + ')' : nama);

            const isLast = (i === wizardRows.length - 1);
            if (btnTtdNext) {
                btnTtdNext.innerHTML = isLast
                    ? '<i class="bi bi-check2-circle me-1"></i> Selesaikan & Simpan'
                    : '<i class="bi bi-arrow-right-circle me-1"></i> Lanjut ke Siswa Berikutnya';
            }
            if (typeof resetWizardCanvas === 'function') resetWizardCanvas();
        }

        if (btnTtdNext && ttdWizardModal) {
            btnTtdNext.addEventListener('click', function () {
                if (typeof wizardCanvasBlank === 'function' && wizardCanvasBlank()) {
                    if (ttdWizardError) ttdWizardError.classList.remove('d-none');
                    return;
                }
                if (ttdWizardError) ttdWizardError.classList.add('d-none');
                const dataUrl = wizardCanvas.toDataURL('image/png');
                wizardRows[wizardIdx]._ttd = dataUrl;

                if (wizardIdx + 1 < wizardRows.length) {
                    renderWizardStep(wizardIdx + 1);
                    return;
                }

                // Selesaikan & Simpan: bangun ttd_siswa[] sejajar id_siswa[] lalu submit.
                if (ttdSiswaInputs) ttdSiswaInputs.innerHTML = '';
                wizardRows.forEach(function (row) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'ttd_siswa[]';
                    input.value = row._ttd || '';
                    if (ttdSiswaInputs) ttdSiswaInputs.appendChild(input);
                });

                const modal = bootstrap.Modal.getInstance(ttdWizardModal);
                if (modal) modal.hide();

                formDispen.submit();
            });
        }
    });
</script>
@endpush