@extends('layouts.app')

@section('title', 'Verifikasi Izin Keluar - Satpam')

@section('content')
<div class="container-fluid px-0">

    {{-- Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="font-weight: 900; font-size: 1.75rem; letter-spacing: -0.02em;">
                Verifikasi Izin Keluar Gerbang
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                Periksa surat izin digital siswa sebelum diizinkan keluar — input <strong>Kode Unik</strong> atau cari NIS / NISN / nama.
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

    {{-- Form Pencarian --}}
    <div class="table-card-custom mb-4">
        <form method="GET" action="{{ route('satpam.verifikasi') }}" class="row g-3 align-items-end">
            <div class="col-12 col-md-8 col-xl-6">
                <label class="form-label fw-bold text-secondary text-uppercase small mb-1">Kode Unik Surat / NIS / NISN / Nama</label>
                <input type="text" name="q" value="{{ $q }}" autofocus
                       class="form-control rounded-3 py-2" placeholder="Contoh: 5f3a... atau 23101 atau Budi">
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
                <span class="small">Siswa menunjukkan surat izin digital (berisi kode unik). Masukkan kode uniknya, atau ketik NIS/NISN/nama siswa untuk memeriksa dispensasi yang disetujui.</span>
            </div>
        </div>
    @elseif($dispen)
        @php
            $hariIni    = $dispen->tanggal->toDateString() === now()->toDateString();
            $bolehKeluar = $dispen->isApproved() && $hariIni && !$dispen->isKeluarGerbang();
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
                        Kelas <strong>{{ $dispen->siswa?->kelas?->nama_lengkap ?? '-' }}</strong>
                    </div>
                </div>
                <div class="text-end">
                    <span class="badge {{ $dispen->status_badge }} rounded-pill px-3 py-2">{{ $dispen->status_label }}</span>
                    @if($dispen->isKeluarGerbang())
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-2 d-block mt-1">
                            <i class="bi bi-check-circle-fill me-1"></i> Sudah Diizinkan Keluar
                        </span>
                    @endif
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-6 col-md-3">
                    <div class="text-muted small text-uppercase fw-bold">Tanggal</div>
                    <div>{{ $dispen->tanggal->translatedFormat('d M Y') }} @if($hariIni)<span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill ms-1">HARI INI</span>@endif</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted small text-uppercase fw-bold">Jam Ke</div>
                    <div>{{ $dispen->jam_ke ?? '-' }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted small text-uppercase fw-bold">Diterbitkan</div>
                    <div>{{ $dispen->guruPiket?->nama ?? '-' }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted small text-uppercase fw-bold">Kode Surat</div>
                    <div><code>{{ $dispen->approval_token ?? '-' }}</code></div>
                </div>
                <div class="col-12">
                    <div class="text-muted small text-uppercase fw-bold">Alasan</div>
                    <div>{{ $dispen->alasan }}</div>
                </div>
            </div>

            @if($bolehKeluar)
                <div class="alert border-0 rounded-4 mb-3 py-3 px-4 d-flex align-items-center gap-3 bg-success-subtle text-success" role="alert">
                    <i class="bi bi-patch-check-fill fs-5"></i>
                    <div>
                        <strong>Surat izin valid untuk keluar hari ini.</strong><br>
                        <span class="small">Izinkan siswa keluar gerbang.</span>
                    </div>
                </div>
                <form method="POST" action="{{ route('satpam.dispen.keluar', $dispen) }}" onsubmit="return confirm('Izinkan \'{{ $dispen->siswa?->nama }}\' keluar gerbang sekarang?')">
                    @csrf
                    <button type="submit" class="btn btn-success rounded-3 px-4 py-2 fw-semibold shadow-sm">
                        <i class="bi bi-door-open-fill me-1"></i> Izinkan Keluar Gerbang
                    </button>
                </form>
            @else
                <div class="alert border-0 rounded-4 mb-0 py-3 px-4 d-flex align-items-center gap-3 {{ $dispen->isKeluarGerbang() ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning-emphasis' }}" role="alert">
                    <i class="bi bi-info-circle-fill fs-5"></i>
                    <div>
                        @if($dispen->isKeluarGerbang())
                            <strong>Siswa sudah diizinkan keluar.</strong><br>
                            <span class="small">{{ $dispen->keluar_gerbang_at->translatedFormat('l, d M Y H:i') }} oleh {{ $dispen->verifier?->nama ?? '-' }}</span>
                        @elseif(!$dispen->isApproved())
                            <strong>Surat izin belum disetujui.</strong><br>
                            <span class="small">Siswa belum dapat keluar gerbang tanpa persetujuan Guru Piket / Waka Kesiswaan.</span>
                        @else
                            <strong>Surat izin bukan untuk hari ini.</strong><br>
                            <span class="small">Dispensasi berlaku tanggal {{ $dispen->tanggal->translatedFormat('d M Y') }}.</span>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        @if($daftarDispen->count() > 1)
            {{-- Riwayat lain siswa --}}
            <div class="table-card-custom">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <h5 class="fw-bold text-dark mb-0">Riwayat Dispensasi Lainnya</h5>
                    <span class="text-muted small">{{ $daftarDispen->count() - 1 }} record</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th>TANGGAL</th>
                                <th>JAM KE</th>
                                <th>ALASAN</th>
                                <th class="text-end">STATUS</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($daftarDispen as $d)
                                @if($d->id === $dispen->id) @continue @endif
                                <tr>
                                    <td>{{ $d->tanggal->translatedFormat('d M Y') }} @if($d->tanggal->toDateString() === now()->toDateString())<span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill ms-1">HARI INI</span>@endif</td>
                                    <td>{{ $d->jam_ke ?? '-' }}</td>
                                    <td>{{ \Illuminate\Support\Str::limit($d->alasan, 50) }}</td>
                                    <td class="text-end">
                                        @if($d->isKeluarGerbang())
                                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">Sudah Keluar</span>
                                        @else
                                            <span class="badge {{ $d->status_badge }} rounded-pill">{{ $d->status_label }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @elseif($siswa)
        <div class="alert border-0 rounded-4 mb-4 py-3 px-4 d-flex align-items-center gap-3 bg-warning-subtle text-warning-emphasis" role="alert">
            <i class="bi bi-info-circle-fill fs-5"></i>
            <div>
                <strong>Siswa {{ $siswa->nama }} ditemukan, namun tidak ada dispensasi disetujui.</strong><br>
                <span class="small">Siswa belum memiliki surat izin keluar yang sah.</span>
            </div>
        </div>
    @else
        <div class="alert border-0 rounded-4 mb-4 py-3 px-4 d-flex align-items-center gap-3 bg-danger-subtle text-danger" role="alert">
            <i class="bi bi-x-circle-fill fs-5"></i>
            <div>
                <strong>Tidak ditemukan.</strong><br>
                <span class="small">Kode unik, NIS, NISN, atau nama tidak cocok dengan surat izin mana pun.</span>
            </div>
        </div>
    @endif
</div>
@endsection