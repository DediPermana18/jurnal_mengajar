@extends('layouts.app')

@section('title', 'Verifikasi Surat Masuk / Telat - WebJournal')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3 gap-2">
        <div class="min-w-0">
            <h2 class="fw-black text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 800; font-size: 1.5rem;">
                Verifikasi Surat Masuk / Telat
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.85rem;">
                Scan / cari surat dispensasi telat siswa, lalu izinkan siswa masuk kelas.
            </p>
        </div>
        <a href="{{ route('guru.dashboard') }}" class="btn btn-outline-secondary btn-sm rounded-3 fw-semibold flex-shrink-0">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Dashboard
        </a>
    </div>

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm" role="alert">
            <i class="bi bi-x-octagon-fill me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    @endif

    {{-- Card: Form Pencarian / Scan QR --}}
    <div class="table-card-custom mb-4">
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <h5 class="fw-bold text-dark mb-0">
                <i class="bi bi-qr-code-scan text-primary me-2"></i> Cari / Scan Surat Dispensasi Telat
            </h5>
            <button type="button" class="btn btn-outline-primary btn-sm rounded-3 fw-semibold" data-bs-toggle="modal" data-bs-target="#modalScanQr">
                <i class="bi bi-camera me-1"></i> Scan QR Kamera
            </button>
        </div>
        <p class="text-muted small mb-3">
            Masukkan <strong>Kode Unik</strong> (hasil scan QR), <strong>Nomor Surat</strong>
            (contoh: <code>SIM-0005/2026</code> untuk Izin Masuk / Telat atau <code>DIS-0006/2026</code> untuk Keluar / Kegiatan),
            atau <strong>link hasil scan QR</strong>.
        </p>
        <form method="GET" action="{{ route('guru.dispensasi.verifikasi') }}" id="formCariSurat" class="d-flex flex-column flex-sm-row gap-2">
            <input type="text"
                   name="q"
                   id="inputKodeSurat"
                   class="form-control form-control-lg rounded-3"
                   placeholder="Scan QR / Ketik Kode Unik / Nomor Surat (SIM-0001/2026 atau DIS-0001/2026)..."
                   value="{{ old('q', $q ?? '') }}"
                   autofocus
                   autocomplete="off">
            <button type="submit" class="btn btn-primary btn-lg rounded-3 px-4 fw-semibold shadow-sm flex-shrink-0">
                <i class="bi bi-search me-1"></i> Cari Surat
            </button>
        </form>
    </div>

    {{-- Hasil pencarian / verifikasi --}}
    @if($warning)
        <div class="alert alert-warning alert-dismissible fade show rounded-3 shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ $warning }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    @endif

    @if($dispen)
        @php
            $ev = $eval ?? [];
            $siswa = $dispen->siswa;
            $catatan = $dispen->catatanTerlambat;
            $jamKedatangan = $catatan?->jam_masuk ? \Carbon\Carbon::parse($catatan->jam_masuk)->format('H:i') : null;
            $accPiket = $catatan ? (bool) $catatan->is_approved_piket : true;
            $jadwal = $ev['jadwal'] ?? null;
            $presensiStatus = ($ev['presensi'] ?? null)?->status;
            $semuaValid = ($ev['is_tipe_masuk'] ?? false)
                && ($ev['is_hari_ini'] ?? false)
                && ($ev['is_approved'] ?? false)
                && ! ($ev['is_selesai'] ?? false)
                && ! ($ev['is_ditolak'] ?? false)
                && ($ev['berhak'] ?? false);
        @endphp

        {{-- Kartu preview surat --}}
        <div class="table-card-custom mb-4" id="kartuHasil">
            <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
                <div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <h5 class="fw-bold text-dark mb-0">{{ $dispen->nomor_surat }}</h5>
                        <span class="badge {{ $dispen->status_badge }} rounded-pill px-3 py-1">
                            <i class="bi bi-info-circle me-1"></i>{{ $dispen->status_label }}
                        </span>
                        <span class="badge {{ $dispen->tipe_dispen === \App\Models\DispensasiSiswa::TIPE_MASUK ? 'bg-primary-subtle text-primary-emphasis border border-primary-subtle' : 'bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle' }} rounded-pill px-3 py-1">
                            <i class="bi {{ $dispen->tipe_dispen === \App\Models\DispensasiSiswa::TIPE_MASUK ? 'bi-box-arrow-in-right' : 'bi-box-arrow-right' }} me-1"></i>
                            {{ $dispen->tipe_dispen_label }}
                        </span>
                    </div>
                    <p class="text-muted small mb-0 mt-1">
                        Diterbitkan {{ $dispen->tanggal ? $dispen->tanggal->locale('id')->translatedFormat('d F Y') : '-' }}
                        oleh {{ $dispen->guruPiket?->nama ?? 'Guru Piket' }}
                    </p>
                </div>
                @if(($ev['is_selesai'] ?? false))
                    <div class="text-end">
                        <span class="badge bg-success text-white rounded-pill px-3 py-2 fw-semibold">
                            <i class="bi bi-person-check-fill me-1"></i> SUDAH MASUK KELAS
                        </span>
                        @if($dispen->masuk_kelas_at)
                            <div class="small text-muted mt-1">
                                {{ $dispen->masuk_kelas_at->locale('id')->translatedFormat('d M Y H:i') }}
                                oleh {{ $dispen->masukKelasVerifier?->nama ?? '-' }}
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            <div class="row g-3 mb-3">
                {{-- Identitas siswa --}}
                <div class="col-md-6">
                    <div class="d-flex align-items-start gap-3 p-3 rounded-3 border bg-light-subtle h-100">
                        <div class="avatar-circle-custom bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px; border-radius: 12px;">
                            <i class="bi bi-person-fill fs-5"></i>
                        </div>
                        <div class="min-w-0">
                            <div class="fw-bold text-dark">{{ $siswa?->nama ?? '-' }}</div>
                            <div class="small text-muted">
                                NIS: {{ $siswa?->nis ?? '-' }} &nbsp;•&nbsp; NISN: {{ $siswa?->nisn ?? '-' }}
                            </div>
                            <div class="small text-muted mt-1">
                                <span class="badge bg-light text-dark border">{{ $siswa?->kelas?->nama ?? '-' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                {{-- Detail surat --}}
                <div class="col-md-6">
                    <div class="p-3 rounded-3 border h-100">
                        <dl class="row mb-0 small">
                            <dt class="col-sm-5 text-muted fw-normal">Jam Kedatangan</dt>
                            <dd class="col-sm-7 fw-semibold mb-1">
                                {{ $jamKedatangan ? $jamKedatangan.' WIB' : '-' }}
                                @if($catatan?->id_satpam)
                                    <span class="text-muted fw-normal">(Satpam: {{ $catatan->satpam?->nama ?? '-' }})</span>
                                @endif
                            </dd>
                            <dt class="col-sm-5 text-muted fw-normal">Diizinkan Masuk JP</dt>
                            <dd class="col-sm-7 fw-semibold mb-1">Jam Ke-{{ $dispen->jam_masuk_jp ?? '-' }}</dd>
                            <dt class="col-sm-5 text-muted fw-normal">Status ACC Guru Piket</dt>
                            <dd class="col-sm-7 mb-1">
                                @if($accPiket)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1">
                                        <i class="bi bi-check-circle-fill me-1"></i> ACC
                                    </span>
                                @else
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-2 py-1">
                                        <i class="bi bi-hourglass-split me-1"></i> Menunggu ACC
                                    </span>
                                @endif
                                @if($dispen->approved_at)
                                    <div class="text-muted">{{ $dispen->approved_at->locale('id')->translatedFormat('d M Y H:i') }}</div>
                                @endif
                            </dd>
                            <dt class="col-sm-5 text-muted fw-normal">Alasan Keterlambatan</dt>
                            <dd class="col-sm-7 mb-0">{{ $dispen->alasan ?: '-' }}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            {{-- Checklist kelayakan verifikasi --}}
            <h6 class="fw-bold text-uppercase small text-secondary mb-2 mt-4"><i class="bi bi-clipboard-check me-1"></i> Pemeriksaan Kelayakan Verifikasi</h6>
            <div class="row g-2 mb-4">
                @php
                    $checks = [
                        ['label' => 'Surat berjenis "Masuk Kelas" (dispensasi telat)', 'ok' => $ev['is_tipe_masuk'] ?? false],
                        ['label' => 'Surat berlaku untuk hari ini', 'ok' => $ev['is_hari_ini'] ?? false],
                        ['label' => 'Status surat disetujui (ACC Guru Piket)', 'ok' => ($ev['is_approved'] ?? false) && ! ($ev['is_ditolak'] ?? false)],
                        ['label' => 'Belum pernah diverifikasi masuk kelas', 'ok' => ! ($ev['is_selesai'] ?? false)],
                        ['label' => 'Anda mengajar pada JP masuk kelas ('.$jadwal?->mapel?->nama_mapel.', Jam Ke-'.$jadwal?->jamPelajaran?->jam_ke.')', 'ok' => $ev['berhak'] ?? false],
                    ];
                @endphp
                @foreach($checks as $cek)
                    <div class="col-md-6">
                        <div class="d-flex align-items-center gap-2 p-2 rounded-3 {{ $cek['ok'] ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                            <i class="bi {{ $cek['ok'] ? 'bi-check-circle-fill' : 'bi-x-circle-fill' }} flex-shrink-0"></i>
                            <span class="small fw-semibold">{{ $cek['label'] }}</span>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Status presensi jurnal JP masuk kelas --}}
            @if(($ev['berhak'] ?? false) && $jadwal)
                <div class="alert {{ $ev['jurnal'] ? 'alert-info' : 'alert-secondary' }} rounded-3 p-3 mb-4 d-flex align-items-start gap-2">
                    <i class="bi {{ $ev['jurnal'] ? 'bi-journal-check' : 'bi-journal-text' }} fs-5 mt-1"></i>
                    <div class="small">
                        <strong>Jurnal Mengajar JP ini:</strong>
                        @if($ev['jurnal'])
                            sudah diisi —
                            status presensi <strong>{{ $siswa?->nama }}</strong> saat ini:
                            <span class="badge {{ $presensiStatus === 'Terlambat' ? 'bg-warning-subtle text-warning-emphasis border border-warning-subtle' : ($presensiStatus ? 'bg-light text-dark border' : 'bg-light text-dark border') }} rounded-pill px-2 py-1">
                                {{ $presensiStatus ?: 'Belum tercatat' }}
                            </span>
                            @if(! in_array($presensiStatus, ['Terlambat', 'Sakit', 'Izin', 'Dispen'], true))
                                <span class="d-block mt-1 text-muted">Setelah verifikasi, status otomatis menjadi <strong>Terlambat (T)</strong>.</span>
                            @endif
                        @else
                            jurnal JP ini <strong>belum diisi</strong> — presensi <strong>{{ $siswa?->nama }}</strong> akan otomatis
                            tercatat <strong>Terlambat (T)</strong> saat jurnal diisi (atau segera diterapkan bila langsung diisi).
                        @endif
                    </div>
                </div>
            @endif

            {{-- Aksi --}}
            @if(($ev['is_selesai'] ?? false))
                <div class="alert alert-success rounded-3 mb-0">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    Siswa ini <strong>sudah diizinkan masuk kelas</strong>. Tidak ada tindakan lebih lanjut.
                </div>
            @elseif($semuaValid)
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 p-3 rounded-3 border border-success-subtle bg-success-subtle">
                    <div class="small text-success-emphasis fw-semibold">
                        <i class="bi bi-patch-check-fill me-1"></i>
                        Semua persyaratan terpenuhi — Anda berhak memverifikasi surat ini.
                    </div>
                    <button type="button"
                            class="btn btn-success btn-lg rounded-3 px-4 fw-bold shadow-sm flex-shrink-0"
                            data-bs-toggle="modal"
                            data-bs-target="#modalIzinkanMasuk">
                        <i class="bi bi-box-arrow-in-right me-2"></i> Izinkan Masuk Kelas
                    </button>
                </div>
            @elseif(($ev['is_tipe_masuk'] ?? false) && ($ev['is_hari_ini'] ?? false) && ($ev['is_approved'] ?? false) && ! ($ev['berhak'] ?? false))
                <div class="alert alert-danger rounded-3 mb-0">
                    <i class="bi bi-shield-x me-2"></i>
                    <strong>Akses ditolak:</strong> Anda <u>tidak mengajar</u> pada Jam Pelajaran masuk kelas
                    (Jam Ke-{{ $dispen->jam_masuk_jp ?? '-' }}) untuk kelas {{ $siswa?->kelas?->nama ?? '-' }} hari ini.
                    Hanya Guru Mapel pada JP tersebut yang berhak memverifikasi surat ini.
                </div>
            @else
                <div class="alert alert-warning rounded-3 mb-0">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Surat ini belum dapat diverifikasi masuk kelas. Periksa hasil pemeriksaan di atas.
                </div>
            @endif
        </div>
    @endif
</div>

{{-- Modal: Preview & Konfirmasi "Izinkan Masuk Kelas" --}}
@if($dispen && isset($eval) && ($eval['berhak'] ?? false) && ! ($eval['is_selesai'] ?? false) && ($eval['is_tipe_masuk'] ?? false) && ($eval['is_hari_ini'] ?? false) && ($eval['is_approved'] ?? false))
<div class="modal fade" id="modalIzinkanMasuk" tabindex="-1" aria-labelledby="modalIzinkanMasukLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="modalIzinkanMasukLabel">
                    <i class="bi bi-box-arrow-in-right text-success me-2"></i> Izinkan Masuk Kelas
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body pt-2">
                <div class="alert alert-info rounded-3 small mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    Setelah disetujui, status surat menjadi <strong>Siswa Masuk Kelas</strong> dan presensi siswa pada
                    Jurnal Mengajar Jam Ke-{{ $dispen->jam_masuk_jp ?? '-' }} otomatis menjadi <strong>Terlambat (T)</strong>
                    (jika sebelumnya Alpa / belum diisi).
                </div>
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="p-3 rounded-3 border bg-light-subtle h-100">
                            <div class="small text-muted text-uppercase">Nama Siswa</div>
                            <div class="fw-bold">{{ $dispen->siswa?->nama }}</div>
                            <div class="small text-muted">{{ $dispen->siswa?->kelas?->nama }} • NIS {{ $dispen->siswa?->nis }}</div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-3 rounded-3 border bg-light-subtle h-100">
                            <div class="small text-muted text-uppercase">Jam Kedatangan</div>
                            <div class="fw-bold">{{ $jamKedatangan ? $jamKedatangan.' WIB' : '-' }}</div>
                            <div class="small text-muted">Diizinkan masuk pada Jam Ke-{{ $dispen->jam_masuk_jp ?? '-' }}</div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-3 rounded-3 border bg-light-subtle h-100">
                            <div class="small text-muted text-uppercase">Alasan Keterlambatan</div>
                            <div class="fw-bold">{{ $dispen->alasan ?: '-' }}</div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-3 rounded-3 border bg-light-subtle h-100">
                            <div class="small text-muted text-uppercase">Status ACC Guru Piket</div>
                            <div class="fw-bold">
                                @if($accPiket)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1">
                                        <i class="bi bi-check-circle-fill me-1"></i> ACC Guru Piket
                                    </span>
                                @else
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-2 py-1">
                                        <i class="bi bi-hourglass-split me-1"></i> Belum ACC
                                    </span>
                                @endif
                            </div>
                            <div class="small text-muted">{{ $dispen->guruPiket?->nama ?? '-' }}</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary rounded-3 px-4" data-bs-dismiss="modal">Batal</button>
                <form method="POST" action="{{ route('guru.dispensasi.izinkan-masuk', $dispen) }}"
                      onsubmit="return confirm('Izinkan {{ $dispen->siswa?->nama }} masuk kelas pada Jam Ke-{{ $dispen->jam_masuk_jp ?? '-' }}? Presensi jurnal akan otomatis menjadi Terlambat (T).');">
                    @csrf
                    <button type="submit" class="btn btn-success rounded-3 px-4 py-2 fw-bold shadow-sm">
                        <i class="bi bi-check-lg me-1"></i> Izinkan Masuk Kelas
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Modal: Scan QR Kamera --}}
<div class="modal fade" id="modalScanQr" tabindex="-1" aria-labelledby="modalScanQrLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="modalScanQrLabel"><i class="bi bi-qr-code-scan text-primary me-2"></i> Scan QR Surat</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <video id="qrVideo" class="w-100 rounded-3 bg-dark" style="min-height: 220px;" playsinline muted></video>
                <p id="qrStatus" class="small text-muted mt-2 mb-0">
                    Tekan <strong>Mulai Scan</strong> dan arahkan kamera ke QR surat dispensasi.
                    Bila kamera tidak tersedia/terblokir, ketik kode surat secara manual pada kolom pencarian.
                </p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" id="btnQrStart" class="btn btn-primary rounded-3 px-4 fw-semibold">
                    <i class="bi bi-camera-video me-1"></i> Mulai Scan
                </button>
                <button type="button" id="btnQrStop" class="btn btn-outline-secondary rounded-3 px-4">Stop</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalEl = document.getElementById('modalScanQr');
    if (! modalEl) { return; }

    var video = document.getElementById('qrVideo');
    var statusEl = document.getElementById('qrStatus');
    var btnStart = document.getElementById('btnQrStart');
    var btnStop = document.getElementById('btnQrStop');
    var inputKode = document.getElementById('inputKodeSurat');
    var formCari = document.getElementById('formCariSurat');

    var stream = null;
    var rafId = null;
    var scanning = false;
    var supportsBarcode = 'BarcodeDetector' in window;

    function stopScan() {
        scanning = false;
        if (rafId) { cancelAnimationFrame(rafId); rafId = null; }
        if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); }
        stream = null;
        if (video) { video.srcObject = null; }
        if (btnStart) { btnStart.disabled = false; }
        if (statusEl) { statusEl.textContent = 'Scan dihentikan. Tekan Mulai Scan untuk mencoba lagi atau masukkan kode secara manual.'; }
    }

    function tick() {
        if (! scanning || ! stream) { return; }
        var detector = new BarcodeDetector({ formats: ['qr_code'] });
        detector.detect(video).then(function (codes) {
            if (codes && codes.length > 0) {
                var raw = (codes[0].rawValue || '').trim();
                if (raw) {
                    stopScan();
                    if (inputKode) { inputKode.value = raw; }
                    if (formCari && formCari.requestSubmit) { formCari.requestSubmit(); }
                    return;
                }
            }
            rafId = requestAnimationFrame(tick);
        }).catch(function () {
            rafId = requestAnimationFrame(tick);
        });
    }

    function startScan() {
        if (stream) { return; }
        scanning = true;
        if (btnStart) { btnStart.disabled = true; }

        if (! supportsBarcode) {
            scanning = false;
            if (btnStart) { btnStart.disabled = false; }
            if (statusEl) {
                statusEl.textContent = 'Browser ini tidak mendukung deteksi QR otomatis (BarcodeDetector). Silakan pindai QR dengan aplikasi lain, lalu ketik kode-nya pada kolom pencarian.';
            }
            return;
        }

        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } }).then(function (s) {
            stream = s;
            video.srcObject = stream;
            return video.play();
        }).then(function () {
            if (statusEl) { statusEl.textContent = 'Mengarahkan kamera ke QR surat...'; }
            rafId = requestAnimationFrame(tick);
        }).catch(function () {
            scanning = false;
            if (btnStart) { btnStart.disabled = false; }
            if (statusEl) {
                statusEl.textContent = 'Tidak dapat mengakses kamera (izin ditolak / tidak tersedia). Gunakan input manual: ketik kode unik atau nomor surat pada kolom pencarian.';
            }
        });
    }

    if (modalEl.addEventListener) {
        modalEl.addEventListener('hidden.bs.modal', stopScan);
    }
    if (btnStart) { btnStart.addEventListener('click', startScan); }
    if (btnStop) { btnStop.addEventListener('click', stopScan); }
});
</script>
@endpush