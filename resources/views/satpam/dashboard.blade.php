@extends('layouts.app')

@section('title', 'Dashboard Satpam')

@section('content')
<div class="container-fluid px-0">

    {{-- Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3 md:mb-4 gap-2 md:gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="font-weight: 900; font-size: 1.5rem; letter-spacing: -0.02em;">
                Dashboard Satpam
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.85rem;">
                Kedisiplinan siswa di gerbang — {{ \Carbon\Carbon::parse($today)->translatedFormat('l, d F Y') }}.
            </p>
        </div>
        <a href="{{ route('satpam.verifikasi') }}" class="btn btn-primary rounded-3 px-3 py-2 fw-semibold shadow-sm text-xs md:text-sm mt-1 mt-md-0">
            <i class="bi bi-door-open-fill me-1"></i> Verifikasi Izin Keluar
        </a>
    </div>

    {{-- Alert --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4 d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-check-circle-fill text-success fs-5"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('info'))
        <div class="alert alert-info alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4 d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-info-circle-fill text-info fs-5"></i>
            <div>{{ session('info') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4" role="alert">
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Metrics Card --}}
    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 md:gap-4 mb-3 md:mb-4">
        <div>
            <div class="stat-card-custom p-3 md:p-4 h-100">
                <div class="stat-card-title text-xs md:text-sm truncate mb-1 md:mb-2" title="Siswa Terlambat Hari Ini">Siswa Terlambat Hari Ini</div>
                <div class="stat-number-large text-2xl md:text-4xl text-warning mb-1">{{ number_format($totalTerlambat) }}</div>
                <div class="stat-card-label text-xs md:text-sm truncate" title="tercatat datang melewati batas waktu">Terlambat gerbang</div>
            </div>
        </div>
        <div>
            <div class="stat-card-custom p-3 md:p-4 h-100">
                <div class="stat-card-title text-xs md:text-sm truncate mb-1 md:mb-2" title="Izin Keluar Gerbang Hari Ini">Izin Keluar Gerbang Hari Ini</div>
                <div class="stat-number-large text-2xl md:text-4xl text-primary mb-1">{{ number_format($totalIzinKeluar) }}</div>
                <div class="stat-card-label text-xs md:text-sm truncate" title="dispensasi yang diverifikasi Satpam">Diverifikasi Satpam</div>
            </div>
        </div>
        <div class="col-span-2 md:col-span-1">
            <div class="stat-card-custom p-3 md:p-4 h-100">
                <div class="stat-card-title text-xs md:text-sm truncate mb-1 md:mb-2" title="Dispen Disetujui Hari Ini">Dispen Disetujui Hari Ini</div>
                <div class="stat-number-large text-2xl md:text-4xl text-info mb-1">{{ number_format($totalDispenDisetujui) }}</div>
                <div class="stat-card-label text-xs md:text-sm truncate" title="surat izin valid untuk keluar">Surat izin valid</div>
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <ul class="nav nav-pills mb-4 gap-2" id="satpamTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link rounded-3 px-4 py-2 fw-semibold {{ $tab === 'terlambat' ? 'active' : '' }}"
                    id="tab-terlambat-btn" data-bs-toggle="pill" data-bs-target="#tab-terlambat" type="button" role="tab">
                <i class="bi bi-clock-history me-1"></i> Input Siswa Terlambat
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link rounded-3 px-4 py-2 fw-semibold {{ $tab === 'dispensasi' ? 'active' : '' }}"
                    id="tab-dispensasi-btn" data-bs-toggle="pill" data-bs-target="#tab-dispensasi" type="button" role="tab">
                <i class="bi bi-door-open-fill me-1"></i> Input / Cek Dispensasi
            </button>
        </li>
    </ul>

    <div class="tab-content">
        {{-- ================= TAB 1: INPUT SISWA TERLAMBAT ================= --}}
        <div class="tab-pane fade {{ $tab === 'terlambat' ? 'show active' : '' }}" id="tab-terlambat" role="tabpanel">
            <div class="row g-4">
                {{-- Form Catat Terlambat --}}
                <div class="col-xl-4">
                    <div class="table-card-custom h-100">
                        <h5 class="fw-bold text-dark mb-1"><i class="bi bi-clock-history me-2 text-warning"></i>Catat Siswa Terlambat</h5>
                        <p class="text-muted small mb-4">Record otomatis diteruskan ke <strong>semua Guru Piket</strong> bertugas hari ini & <strong>Wali Kelas</strong> siswa.</p>

                        <form method="POST" action="{{ route('satpam.terlambat.store') }}">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label fw-bold text-secondary text-uppercase small mb-1">Filter Kelas</label>
                                <select id="filterKelasTerlambat" class="form-select rounded-3 py-2">
                                    <option value="">Semua Kelas</option>
                                    @foreach($kelasList as $kelas)
                                        <option value="{{ $kelas->id }}">{{ $kelas->nama_lengkap }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold text-secondary text-uppercase small mb-1">Siswa <span class="text-danger">*</span></label>
                                <select name="id_siswa" id="selectSiswaTerlambat" class="form-select rounded-3 py-2" required>
                                    <option value="">-- Pilih Siswa --</option>
                                    @foreach($siswaList as $siswa)
                                        <option value="{{ $siswa->id }}" data-kelas="{{ $siswa->id_kelas }}">
                                            @if($siswa->kelas) {{ $siswa->kelas->nama_lengkap }} - @endif{{ $siswa->nama }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('id_siswa') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <label class="form-label fw-bold text-secondary text-uppercase small mb-1">Tanggal <span class="text-danger">*</span></label>
                                    <input type="date" name="tanggal" value="{{ old('tanggal', $today) }}" max="{{ $today }}" required class="form-control rounded-3 py-2">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-bold text-secondary text-uppercase small mb-1">Jam Masuk <span class="text-danger">*</span></label>
                                    <input type="time" name="jam_masuk" value="{{ old('jam_masuk', now()->format('H:i')) }}" required class="form-control rounded-3 py-2">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold text-secondary text-uppercase small mb-1">Alasan Keterlambatan</label>
                                <input type="text" name="keterangan" value="{{ old('keterangan') }}" maxlength="191"
                                       class="form-control rounded-3 py-2" placeholder="Misal: bangun kesiangan, kehabisan transport">
                                @error('keterangan') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                            <button type="submit" class="btn btn-warning text-dark rounded-3 px-4 py-2 fw-semibold w-100 shadow-sm">
                                <i class="bi bi-person-plus-fill me-1"></i> Catat Terlambat
                            </button>
                        </form>

                        <hr class="my-4">
                        <div class="text-muted small">
                            <div class="fw-bold text-dark mb-1"><i class="bi bi-info-circle me-1"></i> Penerima Hari Ini</div>
                            @forelse($guruPiketHariIni as $guru)
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <i class="bi bi-person-fill text-secondary"></i>
                                    <span>{{ $guru->nama }}</span>
                                </div>
                            @empty
                                <div>Tidak ada Guru Piket terjadwal hari ini.</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- Rekap Terlambat --}}
                <div class="col-xl-8">
                    <div class="table-card-custom">
                        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                            <h5 class="fw-bold text-dark mb-0">Siswa Terlambat Hari Ini</h5>
                            <span class="text-muted small">{{ $daftarTerlambat->count() }} siswa</span>
                        </div>
                        <div class="overflow-x-auto w-full rounded-lg">
                            <table class="table table-custom align-middle mb-0 min-w-full">
                                <thead>
                                    <tr>
                                        <th class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">NO</th>
                                        <th class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">SISWA</th>
                                        <th class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">KELAS</th>
                                        <th class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">JAM MASUK</th>
                                        <th class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">ALASAN</th>
                                        <th class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">DITERUSKAN KE</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($daftarTerlambat as $i => $t)
                                        <tr>
                                            <td class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">{{ $i + 1 }}</td>
                                            <td class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">
                                                <strong>{{ $t->siswa?->nama ?? '-' }}</strong>
                                                <div class="text-muted small">{{ $t->siswa?->nis ?? '-' }}</div>
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">{{ $t->siswa?->kelas?->nama_lengkap ?? '-' }}</td>
                                            <td class="whitespace-nowrap px-3 py-2 text-xs md:text-sm"><span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill text-xs md:text-sm">{{ $t->jam_masuk?->format('H:i') }}</span></td>
                                            <td class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">{{ $t->keterangan ?? '-' }}</td>
                                            <td class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">
                                                <div class="d-flex flex-column gap-1">
                                                    @if($t->jumlah_guru_piket > 0)
                                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill text-start text-xs md:text-sm">
                                                            <i class="bi bi-people-fill me-1"></i>{{ $t->jumlah_guru_piket }} Guru Piket
                                                        </span>
                                                    @endif
                                                    @if($t->wali_kelas_penerima)
                                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill text-start text-xs md:text-sm">
                                                            <i class="bi bi-person-check-fill me-1"></i>Wali Kelas
                                                        </span>
                                                    @endif
                                                    @if($t->jumlah_guru_piket === 0 && !$t->wali_kelas_penerima)
                                                        <span class="text-muted small">-</span>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4 whitespace-nowrap px-3 py-2 text-xs md:text-sm">
                                                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                                Belum ada siswa terlambat tercatat hari ini.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ================= TAB 2: INPUT / CEK DISPENSASI ================= --}}
        <div class="tab-pane fade {{ $tab === 'dispensasi' ? 'show active' : '' }}" id="tab-dispensasi" role="tabpanel">
            <div class="row g-4">
                {{-- Form Input Dispensasi --}}
                <div class="col-xl-5">
                    <div class="table-card-custom h-100">
                        <h5 class="fw-bold text-dark mb-1"><i class="bi bi-door-open-fill me-2 text-primary"></i>Input Dispensasi Siswa</h5>
                        <p class="text-muted small mb-1">Siswa izin keluar / tidak ikut KBM. Guru Mapel terdeteksi otomatis dari jam pelajaran yang sedang berlangsung.</p>
                        <div class="alert border-0 rounded-3 py-2 px-3 mb-4 bg-primary-subtle text-primary d-flex align-items-center gap-2" style="font-size:0.8rem;">
                            <i class="bi bi-lightning-charge-fill"></i>
                            @if($jamKeSekarang)
                                Jam saat ini: <strong>ke-{{ $jamKeSekarang }}</strong>
                            @else
                                Jam KBM sedang libur / belum terjadwal.
                            @endif
                        </div>

                        <form method="POST" action="{{ route('satpam.dispensasi.store') }}" id="formDispensasi">
                            @csrf
                            <input type="hidden" name="tanggal" value="{{ old('tanggal', $today) }}">

                            <div class="mb-3">
                                <label class="form-label fw-bold text-secondary text-uppercase small mb-1">Filter Kelas</label>
                                <select id="filterKelasDispen" class="form-select rounded-3 py-2">
                                    <option value="">Semua Kelas</option>
                                    @foreach($kelasList as $kelas)
                                        <option value="{{ $kelas->id }}">{{ $kelas->nama_lengkap }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold text-secondary text-uppercase small mb-1">Siswa <span class="text-danger">*</span></label>
                                <select name="id_siswa" id="selectSiswaDispen" class="form-select rounded-3 py-2" required>
                                    <option value="">-- Pilih Siswa --</option>
                                    @foreach($siswaList as $siswa)
                                        <option value="{{ $siswa->id }}" data-kelas="{{ $siswa->id_kelas }}">
                                            @if($siswa->kelas) {{ $siswa->kelas->nama_lengkap }} - @endif{{ $siswa->nama }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('id_siswa') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold text-secondary text-uppercase small mb-1">Jenis Dispensasi <span class="text-danger">*</span></label>
                                <select name="jenis" class="form-select rounded-3 py-2" required>
                                    @foreach($jenisOptions as $jenisKey => $jenisLabel)
                                        <option value="{{ $jenisKey }}" @selected(old('jenis') === $jenisKey)>{{ $jenisLabel }}</option>
                                    @endforeach
                                </select>
                                @error('jenis') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold text-secondary text-uppercase small mb-1">
                                    Mata Pelajaran / Guru Pengajar <span class="text-muted fw-normal">(auto-detect jam sekarang)</span>
                                </label>
                                <select name="id_jadwal" id="selectJadwalDispen" class="form-select rounded-3 py-2">
                                    <option value="">-- Pilih Jam / Mapel / Guru --</option>
                                </select>
                                <div id="deteksiJadwalInfo" class="mt-2 small text-muted" style="display:none;"></div>
                                @error('id_jadwal') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold text-secondary text-uppercase small mb-1">Alasan <span class="text-danger">*</span></label>
                                <textarea name="alasan" rows="2" class="form-control rounded-3 py-2" required maxlength="500"
                                          placeholder="Contoh: izin dokter, keperluan keluarga...">{{ old('alasan') }}</textarea>
                                @error('alasan') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <button type="submit" class="btn btn-primary rounded-3 px-4 py-2 fw-semibold w-100 shadow-sm">
                                <i class="bi bi-check2-circle me-1"></i> Catat & Setujui Dispensasi
                            </button>
                        </form>
                    </div>
                </div>

                {{-- Cek / Rekap Dispensasi --}}
                <div class="col-xl-7">
                    <div class="table-card-custom mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                            <h5 class="fw-bold text-dark mb-0"><i class="bi bi-qr-code-scan me-2 text-primary"></i>Cek Surat Izin (Kode Unik)</h5>
                        </div>
                        <form method="GET" action="{{ route('satpam.verifikasi') }}" class="row g-3 align-items-end">
                            <div class="col-12 col-md-8">
                                <input type="text" name="q" class="form-control rounded-3 py-2"
                                       placeholder="Kode unik surat / NIS / NISN / nama siswa">
                            </div>
                            <div class="col-6 col-md">
                                <button type="submit" class="btn btn-outline-primary rounded-3 px-3 py-2 fw-semibold w-100">
                                    <i class="bi bi-search me-1"></i> Periksa
                                </button>
                            </div>
                            <div class="col-6 col-md">
                                <a href="{{ route('satpam.verifikasi') }}" class="btn btn-outline-secondary rounded-3 px-3 py-2 fw-semibold w-100">
                                    <i class="bi bi-door-open-fill me-1"></i> Hal. Verifikasi
                                </a>
                            </div>
                        </form>
                    </div>

                    <div class="table-card-custom">
                        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                            <h5 class="fw-bold text-dark mb-0">Dispensasi Hari Ini</h5>
                            <span class="text-muted small">{{ $daftarIzinKeluar->count() }} surat</span>
                        </div>
                        <div class="overflow-x-auto w-full rounded-lg">
                            <table class="table table-custom align-middle mb-0 min-w-full">
                                <thead>
                                    <tr>
                                        <th class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">SISWA</th>
                                        <th class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">KELAS</th>
                                        <th class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">JENIS</th>
                                        <th class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">JAM KE</th>
                                        <th class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">GURU MAPEL</th>
                                        <th class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">STATUS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($daftarIzinKeluar as $dispen)
                                        <tr>
                                            <td class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">
                                                <strong>{{ $dispen->siswa?->nama ?? '-' }}</strong>
                                                <div class="text-muted small">{{ $dispen->siswa?->nis ?? '-' }}</div>
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">{{ $dispen->siswa?->kelas?->nama_lengkap ?? '-' }}</td>
                                            <td class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">
                                                <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill text-xs md:text-sm">{{ $dispen->jenis_label }}</span>
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">{{ $dispen->jam_ke ?? '-' }}</td>
                                            <td class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">
                                                @if($dispen->guru)
                                                    <span>{{ $dispen->guru->nama }}</span>
                                                    <div class="text-muted small">{{ $dispen->jadwal?->mapel?->nama_mapel ?? '-' }}</div>
                                                @else
                                                    <span class="text-muted small">-</span>
                                                @endif
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">
                                                @if($dispen->isKeluarGerbang())
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill text-xs md:text-sm">Sudah Keluar</span>
                                                @else
                                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill text-xs md:text-sm">Disetujui</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4 whitespace-nowrap px-3 py-2 text-xs md:text-sm">
                                                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                                Tidak ada dispensasi disetujui hari ini.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const mapJadwalKelas = {!! json_encode($mapJadwalKelas, JSON_UNESCAPED_UNICODE) !!};

        function setupSiswaFilter(filterId, selectId) {
            const filter = document.getElementById(filterId);
            const select = document.getElementById(selectId);
            if (!filter || !select) return;
            const options = Array.from(select.options);

            function applyFilter() {
                const kelasId = filter.value;
                select.innerHTML = '';
                select.appendChild(new Option('-- Pilih Siswa --', '', true, true));
                options.forEach(function (opt) {
                    if (opt.value && (kelasId === '' || opt.dataset.kelas === kelasId)) {
                        select.appendChild(new Option(opt.text, opt.value));
                    }
                });
                select.dispatchEvent(new Event('change'));
            }

            filter.addEventListener('change', applyFilter);
        }

        setupSiswaFilter('filterKelasTerlambat', 'selectSiswaTerlambat');
        setupSiswaFilter('filterKelasDispen', 'selectSiswaDispen');

        // Auto-detect Mata Pelajaran / Guru Pengajar berdasarkan jam sekarang
        const selectSiswaDispen = document.getElementById('selectSiswaDispen');
        const selectJadwalDispen = document.getElementById('selectJadwalDispen');
        const deteksiInfo = document.getElementById('deteksiJadwalInfo');

        if (selectSiswaDispen && selectJadwalDispen) {
            selectSiswaDispen.addEventListener('change', function () {
                const option = selectSiswaDispen.options[selectSiswaDispen.selectedIndex];
                const kelasId = option ? option.dataset.kelas : '';
                const slots = mapJadwalKelas[kelasId] || [];

                selectJadwalDispen.innerHTML = '';
                selectJadwalDispen.appendChild(new Option('-- Pilih Jam / Mapel / Guru --', ''));

                if (slots.length === 0) {
                    deteksiInfo.style.display = 'block';
                    deteksiInfo.textContent = 'Tidak ada jadwal KBM hari ini untuk kelas ini. Dispensasi tetap dapat dicatat tanpa jam pelajaran.';
                    return;
                }

                deteksiInfo.style.display = 'block';
                deteksiInfo.textContent = '';

                slots.forEach(function (slot) {
                    const label = 'Jam ' + slot.jam_ke + ' (' + slot.waktu + ') | ' + slot.mapel + ' | ' + slot.guru;
                    const opt = new Option(label, slot.id_jadwal);
                    selectJadwalDispen.appendChild(opt);
                    if (slot.aktif) {
                        opt.selected = true;
                        deteksiInfo.innerHTML = '<i class="bi bi-magic me-1 text-primary"></i> Terdeteksi otomatis: <strong>Jam ke-' + slot.jam_ke + ' — ' + slot.mapel + ' (' + slot.guru + ')</strong>';
                    }
                });

                if (deteksiInfo.textContent === '' && slots.length > 0) {
                    deteksiInfo.textContent = 'Pilih jam / mapel / guru yang ditinggalkan siswa.';
                }
            });

            // Auto-select jika siswa sudah dipilih dari old() setelah validasi gagal.
            if (selectSiswaDispen.value) {
                selectSiswaDispen.dispatchEvent(new Event('change'));
            }
        }
    });
</script>
@endpush