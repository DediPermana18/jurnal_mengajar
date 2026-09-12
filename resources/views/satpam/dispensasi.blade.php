@extends('layouts.app')

@section('title', 'Verifikasi Dispensasi - Satpam')

@section('content')
<div class="container-fluid px-0">

    {{-- Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="font-weight: 900; font-size: 1.75rem; letter-spacing: -0.02em;">
                Verifikasi Dispensasi
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                Konfirmasikan <strong>keluar gerbang</strong> berdasarkan nomor surat dispensasi digital — ketik nomor surat atau scan QR (tempel link approval).
            </p>
        </div>
        <a href="{{ route('satpam.dashboard') }}" class="btn btn-outline-secondary rounded-3 px-3 py-2 fw-semibold">
            <i class="bi bi-arrow-left me-1"></i> Dashboard
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
    @if(session('cancel'))
        <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4 d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-x-octagon-fill text-danger fs-5"></i>
            <div>{{ session('cancel') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Form Pencarian --}}
    <div class="table-card-custom mb-4">
        <form method="GET" action="{{ route('satpam.dispensasi.index') }}" class="row g-3 align-items-end">
            <div class="col-12 col-md-8 col-xl-6">
                <label class="form-label fw-bold text-secondary text-uppercase small mb-1">Nomor Surat / Link Approval (QR)</label>
                <input type="text" name="q" value="{{ $q }}" autofocus
                       class="form-control rounded-3 py-2" placeholder="Contoh: DIS-1/2026 atau tempel link approval">
            </div>
            <div class="col-12 col-md-4 col-xl-2">
                <button type="submit" class="btn btn-primary rounded-3 px-4 py-2 fw-semibold shadow-sm w-100">
                    <i class="bi bi-search me-1"></i> Periksa
                </button>
            </div>
        </form>
    </div>

    @if($q === '')
        <div class="alert border-0 rounded-4 mb-4 py-3 px-4 d-flex align-items-center gap-3 bg-light text-secondary" role="alert">
            <i class="bi bi-qr-code-scan fs-5"></i>
            <div>
                <strong>Bagaimana cara verifikasi?</strong><br>
                <span class="small">Siswa menunjukkan surat dispensasi digital (berisi QR/nomor surat). Masukkan nomor surat seperti <code>DIS-1/2026</code>, atau scan QR lalu tempel link approval-nya.</span>
            </div>
        </div>
    @elseif($dispen)
        @php
            $hariIni     = $dispen->tanggal->toDateString() === now()->toDateString();
            $bolehKeluar = $dispen->isApproved() && $hariIni && !$dispen->isKeluarGerbang() && !$dispen->isExpired();
        @endphp

        {{-- Kartu Hasil --}}
        <div class="table-card-custom mb-4">
            <div class="d-flex align-items-center gap-3 flex-wrap mb-3">
                <div class="rounded-4 p-3 bg-primary-subtle text-primary d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                    <i class="bi bi-person-fill fs-3"></i>
                </div>
                <div class="flex-grow-1">
                    <h5 class="fw-bold text-dark mb-0">{{ $dispen->siswa?->nama ?? '-' }}</h5>
                    <div class="text-muted small">
                        NIS <strong>{{ $dispen->siswa?->nis ?? '-' }}</strong> &middot;
                        NISN <strong>{{ $dispen->siswa?->nisn ?? '-' }}</strong> &middot;
                        Kelas <strong>{{ $dispen->siswa?->kelas?->nama_lengkap ?? '-' }}</strong>
                    </div>
                </div>
                <div class="text-end">
                    <span class="badge {{ $dispen->status_badge }} rounded-pill px-3 py-2">{{ $dispen->status_label }}</span>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-6 col-md-3">
                    <div class="text-muted small text-uppercase fw-bold">Nomor Surat</div>
                    <div><code>{{ $dispen->nomor_surat }}</code></div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted small text-uppercase fw-bold">Tanggal</div>
                    <div>{{ $dispen->tanggal->translatedFormat('d M Y') }} @if($hariIni)<span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill ms-1">HARI INI</span>@endif</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted small text-uppercase fw-bold">Jam Berangkat</div>
                    <div>
                        @if(!empty($dispen->jam_keluar_jp))
                            JP-{{ $dispen->jam_keluar_jp }}
                        @else
                            {{ $dispen->jam_ke ?? '-' }}
                        @endif
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted small text-uppercase fw-bold">Diterbitkan</div>
                    <div>{{ $dispen->guruPiket?->nama ?? '-' }}</div>
                </div>
                <div class="col-12">
                    <div class="text-muted small text-uppercase fw-bold">Alasan</div>
                    <div>{{ $dispen->alasan }}</div>
                </div>
                @if($dispen->isExpired())
                    <div class="col-12">
                        <div class="text-muted small text-uppercase fw-bold">Kadaluarsa pada</div>
                        <div>{{ optional($dispen->expired_at)->translatedFormat('d M Y H:i') ?? '-' }}</div>
                    </div>
                @endif
                @if($dispen->isKeluarGerbang())
                    <div class="col-12">
                        <div class="text-muted small text-uppercase fw-bold">Keluar Gerbang</div>
                        <div>{{ optional($dispen->keluar_gerbang_at)->translatedFormat('d M Y H:i') ?? '-' }} oleh {{ $dispen->verifier?->nama ?? '-' }}</div>
                    </div>
                @endif
                @if((!empty($dispen->jam_kembali_jp) || $dispen->isTidakKembaliHariIni()) && !$dispen->isTipeMasuk())
                    <div class="col-12">
                        <div class="text-muted small text-uppercase fw-bold">Rencana Jam Kembali</div>
                        <div>
                            @if($dispen->isTidakKembaliHariIni())
                                Tidak kembali hari ini (izin hingga pulang)
                            @else
                                JP-{{ $dispen->jam_kembali_jp }} — siswa wajib kembali sebelum JP tersebut berakhir
                            @endif
                        </div>
                    </div>
                @endif
                @if($dispen->isKembali())
                    <div class="col-12">
                        <div class="text-muted small text-uppercase fw-bold">Konfirmasi Kembali</div>
                        <div class="text-success fw-semibold">
                            <i class="bi bi-check-circle-fill me-1"></i>
                            Kembali {{ $dispen->kembali_at->translatedFormat('l, d M Y H:i') }}
                            @if($dispen->kembaliVerifier) oleh {{ $dispen->kembaliVerifier->nama }}@endif
                        </div>
                    </div>
                @endif
                @if($dispen->isMangkir())
                    <div class="col-12">
                        <div class="text-muted small text-uppercase fw-bold">Mangkir / Bolos</div>
                        <div class="text-danger">
                            <i class="bi bi-x-octagon-fill me-1"></i>
                            Belum kembali melewati Rencana Jam Kembali + 1 JP — presensi JP terkait diubah menjadi Alfa pada {{ optional($dispen->mangkir_at)->translatedFormat('d M Y H:i') ?? '-' }}.
                        </div>
                    </div>
                @endif
            </div>

            @if($bolehKeluar)
                <div class="alert border-0 rounded-4 mb-3 py-3 px-4 d-flex align-items-center gap-3 bg-success-subtle text-success" role="alert">
                    <i class="bi bi-patch-check-fill fs-5"></i>
                    <div>
                        <strong>Surat dispensasi valid untuk keluar hari ini.</strong><br>
                        <span class="small">Izinkan siswa keluar gerbang (status menjadi "Siswa Out").</span>
                    </div>
                </div>
                <form method="POST" action="{{ route('satpam.dispen.keluar', $dispen) }}" onsubmit="return confirm('Izinkan \'{{ $dispen->siswa?->nama }}\' keluar gerbang sekarang?')">
                    @csrf
                    <button type="submit" class="btn btn-success rounded-3 px-4 py-2 fw-semibold shadow-sm">
                        <i class="bi bi-door-open-fill me-1"></i> Konfirmasi Keluar Gerbang
                    </button>
                </form>
            @else
                <div class="alert border-0 rounded-4 mb-0 py-3 px-4 d-flex align-items-center gap-3 {{
                    $dispen->isKeluarGerbang() || $dispen->isDibatalkan()
                        ? 'bg-success-subtle text-success'
                        : ($dispen->isExpired() ? 'bg-danger-subtle text-danger' : 'bg-warning-subtle text-warning-emphasis')
                }}" role="alert">
                    <i class="bi bi-info-circle-fill fs-5"></i>
                    <div>
                        @if($dispen->isKeluarGerbang())
                            <strong>Siswa sudah keluar gerbang (Siswa Out).</strong><br>
                            <span class="small">{{ $dispen->keluar_gerbang_at->translatedFormat('l, d M Y H:i') }} oleh {{ $dispen->verifier?->nama ?? '-' }}</span>
                        @elseif($dispen->isExpired())
                            <strong>Surat dispensasi KADALUARSA.</strong><br>
                            <span class="small">Melewati batas Jam Berangkat + 1 JP. Siswa tidak dapat diizinkan keluar dengan surat ini.</span>
                        @elseif($dispen->isDibatalkan())
                            <strong>Surat dispensasi telah DIBATALKAN.</strong><br>
                            <span class="small">Pembatalan dilakukan dengan tanda tangan siswa pada {{ optional($dispen->dibatalkan_at)->translatedFormat('d M Y H:i') ?? '-' }}.</span>
                        @elseif(!$dispen->isApproved())
                            <strong>Surat dispensasi belum disetujui.</strong><br>
                            <span class="small">Siswa belum dapat keluar gerbang tanpa persetujuan / tanda tangan yang sah.</span>
                        @else
                            <strong>Surat dispensasi bukan untuk hari ini.</strong><br>
                            <span class="small">Dispensasi berlaku tanggal {{ $dispen->tanggal->translatedFormat('d M Y') }}.</span>
                        @endif
                    </div>
                </div>
            @endif

            @if($dispen->isMenungguKembali())
                <div class="alert border-0 rounded-4 mb-3 py-3 px-4 d-flex align-items-center gap-3 bg-info-subtle text-info-emphasis" role="alert">
                    <i class="bi bi-arrow-left-circle-fill fs-5"></i>
                    <div>
                        <strong>Siswa sudah keluar dan belum kembali.</strong><br>
                        <span class="small">Rencana kembali JP-{{ $dispen->jam_kembali_jp }}. Konfirmasi saat siswa kembali ke sekolah agar tidak dicatat Mangkir / Bolos.</span>
                    </div>
                </div>
                <form method="POST" action="{{ route('satpam.dispen.kembali', $dispen) }}" onsubmit="return confirm('Konfirmasi \'{{ $dispen->siswa?->nama }}\' sudah kembali ke sekolah?')" class="mb-3">
                    @csrf
                    <button type="submit" class="btn btn-info rounded-3 px-4 py-2 fw-semibold shadow-sm text-white">
                        <i class="bi bi-check-circle-fill me-1"></i> Konfirmasi Siswa Kembali
                    </button>
                </form>
            @endif
        </div>
    @else
        <div class="alert border-0 rounded-4 mb-4 py-3 px-4 d-flex align-items-center gap-3 bg-danger-subtle text-danger" role="alert">
            <i class="bi bi-x-circle-fill fs-5"></i>
            <div>
                <strong>Tidak ditemukan.</strong><br>
                <span class="small">Nomor surat atau link approval tidak cocok dengan surat dispensasi mana pun.</span>
            </div>
        </div>
    @endif
</div>
@endsection