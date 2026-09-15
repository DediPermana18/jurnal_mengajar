@extends('layouts.app')

@section('title', 'Verifikasi Izin & Dispensasi - Satpam')

@section('content')
<div class="container-fluid px-0">

    {{-- Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="font-weight: 900; font-size: 1.75rem; letter-spacing: -0.02em;">
                Verifikasi Izin &amp; Dispensasi
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                Cek surat izin / dispensasi digital siswa di gerbang — <strong>Scan QR</strong>, masukkan <strong>Kode Unik</strong>, <strong>Nomor Surat</strong>, atau cari <strong>NIS / NISN / Nama</strong>.
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

    {{-- Form Pencarian Universal --}}
    <div class="table-card-custom mb-4">
        <form method="GET" action="{{ route('satpam.verifikasi') }}" class="row g-3 align-items-end">
            <div class="col-12 col-md-8 col-xl-6">
                <label class="form-label fw-bold text-secondary text-uppercase small mb-1">Scan QR / Nomor Surat / Kode Unik / NISN / Nama Siswa</label>
                <input type="text" name="q" value="{{ $q }}" autofocus
                       class="form-control rounded-3 py-2" placeholder="Scan QR / Ketik Nomor Surat / Kode Unik / NISN / Nama Siswa...">
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
                <span class="small">Siswa menunjukkan surat izin/dispensasi digital (berisi QR / kode unik / nomor surat). Scan QR lalu tempel link-nya, masukkan nomor surat seperti <code>DIS-1/2026</code>, ketik kode unik, atau cari berdasarkan NISN / nama siswa.</span>
            </div>
        </div>
    @elseif($kolektif)
        @php
            $items = $kolektif->siswaItems;
            $bisaKeluarIds = $items->filter(
                fn ($d) => $d->tanggal?->toDateString() === now()->toDateString()
                    && $d->isApproved() && ! $d->isKeluarGerbang() && ! $d->isExpired() && $d->isTtdLengkap()
            )->pluck('id')->all();
            $menungguKembaliIds = $items->filter(fn ($d) => $d->isMenungguKembali())->pluck('id')->all();
            $bisaKeluarCount     = count($bisaKeluarIds);
            $menungguKembaliCount = count($menungguKembaliIds);
            $sudahKeluarCount    = $items->filter(fn ($d) => $d->isKeluarGerbang())->count();
            $sudahKembaliCount   = $items->filter(fn ($d) => $d->isKembali())->count();
            $defaultMode         = $bisaKeluarCount === 0 ? 'kembali' : 'keluar';
            $kolektifHariIni     = $kolektif->tanggal?->toDateString() === now()->toDateString();
        @endphp

        {{-- Kartu Hasil Rombongan --}}
        <div class="table-card-custom mb-4">
            <div class="d-flex align-items-center gap-3 flex-wrap mb-3">
                <div class="rounded-4 p-3 bg-primary-subtle text-primary d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                    <i class="bi bi-people-fill fs-3"></i>
                </div>
                <div class="flex-grow-1">
                    <h5 class="fw-bold text-dark mb-0">
                        Dispensasi Rombongan / Kolektif
                    </h5>
                    <div class="text-muted small">
                        {{ $items->count() }} siswa &middot;
                        {{ $kolektif->tipe_dispen_label }} @if($kolektif->tidak_kembali_hari_ini) &middot; Tidak kembali hari ini @endif
                    </div>
                </div>
                <div class="text-end d-flex flex-column align-items-end gap-2">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-2">KOLEKTIF / ROMBONGAN</span>
                    <span class="badge {{ $kolektif->status_badge }} rounded-pill px-3 py-2">{{ $kolektif->status_label }}</span>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-6 col-md-3">
                    <div class="text-muted small text-uppercase fw-bold">Nomor Surat</div>
                    <div><code>{{ $kolektif->nomor_surat }}</code></div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted small text-uppercase fw-bold">Tanggal</div>
                    <div>{{ $kolektif->tanggal?->translatedFormat('d M Y') ?? '-' }} @if($kolektifHariIni)<span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill ms-1">HARI INI</span>@endif</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted small text-uppercase fw-bold">Jam Ditinggalkan</div>
                    <div>{{ $kolektif->jam_ke_label }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted small text-uppercase fw-bold">Diterbitkan</div>
                    <div>{{ $kolektif->guruPiket?->nama ?? '-' }}</div>
                </div>
                <div class="col-12">
                    <div class="text-muted small text-uppercase fw-bold">Alasan</div>
                    <div>{{ $kolektif->alasan }}</div>
                </div>
            </div>

            {{-- Ringkasan status siswa --}}
            <div class="alert border-0 rounded-4 mb-3 py-3 px-4 d-flex align-items-center gap-3 {{
                ($bisaKeluarCount > 0 || $menungguKembaliCount > 0) ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning-emphasis'
            }}" role="alert">
                <i class="bi bi-ui-checks fs-5"></i>
                <div>
                    @if($bisaKeluarCount > 0)
                        <strong>{{ $bisaKeluarCount }} siswa siap diizinkan keluar gerbang.</strong><br>
                        <span class="small">Gunakan tombol di bawah, lalu centang siswa yang benar-benar keluar saat ini.</span>
                    @elseif($menungguKembaliCount > 0)
                        <strong>{{ $menungguKembaliCount }} siswa menunggu konfirmasi kembali ke sekolah.</strong><br>
                        <span class="small">Gunakan tombol di bawah, lalu centang siswa yang benar-benar sudah kembali.</span>
                    @else
                        <strong>Tidak ada siswa yang dapat diproses pada rombongan ini.</strong><br>
                        <span class="small">{{ $sudahKeluarCount }} sudah keluar, {{ $sudahKembaliCount }} sudah kembali.</span>
                    @endif
                </div>
            </div>

            {{-- Tombol aksi membuka modal checklist --}}
            <div class="d-flex flex-wrap gap-2">
                @if($bisaKeluarCount > 0)
                    <button type="button" class="btn btn-success rounded-3 px-4 py-2 fw-semibold shadow-sm"
                            data-bs-toggle="modal" data-bs-target="#modalVerifikasiRombongan" data-mode="keluar">
                        <i class="bi bi-door-open-fill me-1"></i> Konfirmasi Keluar Gerbang (Rombongan)
                    </button>
                @endif
                @if($menungguKembaliCount > 0)
                    <button type="button" class="btn btn-info rounded-3 px-4 py-2 fw-semibold shadow-sm text-white"
                            data-bs-toggle="modal" data-bs-target="#modalVerifikasiRombongan" data-mode="kembali">
                        <i class="bi bi-check-circle-fill me-1"></i> Konfirmasi Kembali ke Sekolah (Rombongan)
                    </button>
                @endif
            </div>
        </div>

        @if($bisaKeluarCount > 0 || $menungguKembaliCount > 0)
            {{-- Modal Checklist Verifikasi Rombongan --}}
            <div class="modal fade" id="modalVerifikasiRombongan" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content rounded-4 border-0 shadow-lg">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold text-dark">
                                <i class="bi bi-ui-checks me-2 text-primary"></i>Verifikasi Rombongan <code>{{ $kolektif->nomor_surat }}</code>
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                        </div>
                        <div class="modal-body">
                            @if($bisaKeluarCount > 0 && $menungguKembaliCount > 0)
                                <div class="btn-group d-flex mb-3" role="group" aria-label="Mode verifikasi rombongan">
                                    <button type="button" class="btn btn-outline-success js-switch-mode" data-mode="keluar">
                                        <i class="bi bi-door-open-fill me-1"></i> Konfirmasi Keluar
                                    </button>
                                    <button type="button" class="btn btn-outline-info js-switch-mode" data-mode="kembali">
                                        <i class="bi bi-check-circle-fill me-1"></i> Konfirmasi Kembali
                                    </button>
                                </div>
                            @endif

                            {{-- Form Keluar Gerbang --}}
                            <form id="formRombonganKeluar" method="POST" action="{{ route('satpam.kolektif.keluar', $kolektif) }}"
                                  class="{{ $defaultMode === 'keluar' ? '' : 'd-none' }}">
                                @csrf
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input js-select-all" type="checkbox" data-target="#formRombonganKeluar" id="selectAllKeluar" checked>
                                        <label class="form-check-label fw-semibold text-success" for="selectAllKeluar">Pilih Semua</label>
                                    </div>
                                    <span class="text-muted small">Centang siswa yang benar-benar keluar gerbang saat ini.</span>
                                </div>
                                <div class="table-responsive" style="max-height: 380px; overflow-y: auto;">
                                    <table class="table table-sm align-middle mb-0">
                                        <tbody>
                                        @foreach($items as $item)
                                            @if(in_array($item->id, $bisaKeluarIds, true))
                                                <tr>
                                                    <td class="ps-1" style="width: 40px;">
                                                        <div class="form-check">
                                                            <input class="form-check-input js-check-item" type="checkbox" name="dispen_ids[]" value="{{ $item->id }}" id="keluar-{{ $item->id }}" checked>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <label class="form-check-label fw-semibold" for="keluar-{{ $item->id }}">{{ $item->siswa?->nama ?? '-' }}</label>
                                                        <div class="text-muted small">{{ $item->siswa?->nisn ?? '-' }} &middot; {{ $item->siswa?->kelas?->nama_lengkap ?? '-' }}</div>
                                                    </td>
                                                    <td class="text-end">
                                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">Belum keluar</span>
                                                    </td>
                                                </tr>
                                            @else
                                                @php
                                                    $keluarNote = match (true) {
                                                        $item->isKeluarGerbang() => 'Sudah keluar',
                                                        $item->isExpired() => 'Kadaluarsa',
                                                        $item->tanggal?->toDateString() !== now()->toDateString() => 'Bukan hari ini',
                                                        ! $item->isApproved() => $item->status_label,
                                                        default => 'Disetujui',
                                                    };
                                                @endphp
                                                <tr class="opacity-50">
                                                    <td class="ps-1" style="width: 40px;">
                                                        <span class="text-muted"><i class="bi bi-dash-circle"></i></span>
                                                    </td>
                                                    <td>
                                                        {{ $item->siswa?->nama ?? '-' }}
                                                        <div class="text-muted small">{{ $item->siswa?->nisn ?? '-' }} &middot; {{ $item->siswa?->kelas?->nama_lengkap ?? '-' }}</div>
                                                    </td>
                                                    <td class="text-end">
                                                        <span class="badge {{ $item->status_badge }} rounded-pill">{{ $keluarNote }}</span>
                                                    </td>
                                                </tr>
                                            @endif
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <div class="d-flex justify-content-end align-items-center gap-3 mt-3">
                                    <span class="text-muted small"><span class="js-count-selected fw-bold">{{ $bisaKeluarCount }}</span> siswa dipilih</span>
                                    <button type="submit" class="btn btn-success rounded-3 px-4 py-2 fw-semibold shadow-sm">
                                        <i class="bi bi-door-open-fill me-1"></i> Konfirmasi Keluar Gerbang
                                    </button>
                                </div>
                            </form>

                            {{-- Form Konfirmasi Kembali --}}
                            <form id="formRombonganKembali" method="POST" action="{{ route('satpam.kolektif.kembali', $kolektif) }}"
                                  class="{{ $defaultMode === 'kembali' ? '' : 'd-none' }}">
                                @csrf
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input js-select-all" type="checkbox" data-target="#formRombonganKembali" id="selectAllKembali" checked>
                                        <label class="form-check-label fw-semibold text-info" for="selectAllKembali">Pilih Semua</label>
                                    </div>
                                    <span class="text-muted small">Centang siswa yang benar-benar sudah kembali ke sekolah.</span>
                                </div>
                                <div class="table-responsive" style="max-height: 380px; overflow-y: auto;">
                                    <table class="table table-sm align-middle mb-0">
                                        <tbody>
                                        @foreach($items as $item)
                                            @if(in_array($item->id, $menungguKembaliIds, true))
                                                <tr>
                                                    <td class="ps-1" style="width: 40px;">
                                                        <div class="form-check">
                                                            <input class="form-check-input js-check-item" type="checkbox" name="dispen_ids[]" value="{{ $item->id }}" id="kembali-{{ $item->id }}" checked>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <label class="form-check-label fw-semibold" for="kembali-{{ $item->id }}">{{ $item->siswa?->nama ?? '-' }}</label>
                                                        <div class="text-muted small">{{ $item->siswa?->nisn ?? '-' }} &middot; {{ $item->siswa?->kelas?->nama_lengkap ?? '-' }}</div>
                                                    </td>
                                                    <td class="text-end">
                                                        <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle rounded-pill">Menunggu kembali</span>
                                                    </td>
                                                </tr>
                                            @else
                                                @php
                                                    $kembaliNote = match (true) {
                                                        $item->isKembali() => 'Sudah kembali',
                                                        $item->isMangkir() => 'Mangkir / Bolos',
                                                        $item->isTidakKembaliHariIni() => 'Tidak kembali hari ini',
                                                        ! $item->isKeluarGerbang() => 'Belum keluar',
                                                        default => $item->status_label,
                                                    };
                                                @endphp
                                                <tr class="opacity-50">
                                                    <td class="ps-1" style="width: 40px;">
                                                        <span class="text-muted"><i class="bi bi-dash-circle"></i></span>
                                                    </td>
                                                    <td>
                                                        {{ $item->siswa?->nama ?? '-' }}
                                                        <div class="text-muted small">{{ $item->siswa?->nisn ?? '-' }} &middot; {{ $item->siswa?->kelas?->nama_lengkap ?? '-' }}</div>
                                                    </td>
                                                    <td class="text-end">
                                                        <span class="badge {{ $item->status_badge }} rounded-pill">{{ $kembaliNote }}</span>
                                                    </td>
                                                </tr>
                                            @endif
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <div class="d-flex justify-content-end align-items-center gap-3 mt-3">
                                    <span class="text-muted small"><span class="js-count-selected fw-bold">{{ $menungguKembaliCount }}</span> siswa dipilih</span>
                                    <button type="submit" class="btn btn-info rounded-3 px-4 py-2 fw-semibold shadow-sm text-white">
                                        <i class="bi bi-check-circle-fill me-1"></i> Konfirmasi Kembali ke Sekolah
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @elseif($dispen)
        @php
            $hariIni      = $dispen->tanggal->toDateString() === now()->toDateString();
            $isTtdLengkap = $dispen->isTtdLengkap();
            $bolehKeluar  = $dispen->isApproved() && $hariIni && !$dispen->isKeluarGerbang() && !$dispen->isExpired() && $isTtdLengkap;
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
                    <div class="text-muted small text-uppercase fw-bold">Jam Ke</div>
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
                <div class="col-12 mt-3 pt-2 border-top">
                    <div class="text-muted small text-uppercase fw-bold mb-2">Status Tanda Tangan Digital (Wajib 3 TTD)</div>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge {{ $dispen->hasTtdSiswa() ? 'bg-success-subtle text-success border-success-subtle' : 'bg-danger-subtle text-danger border-danger-subtle' }} border rounded-pill px-3 py-2">
                            <i class="bi {{ $dispen->hasTtdSiswa() ? 'bi-check-circle-fill' : 'bi-x-circle-fill' }} me-1"></i>
                            TTD Siswa: {{ $dispen->hasTtdSiswa() ? 'Ada' : 'Belum TTD' }}
                        </span>
                        <span class="badge {{ $dispen->hasTtdGuruPiket() ? 'bg-success-subtle text-success border-success-subtle' : 'bg-danger-subtle text-danger border-danger-subtle' }} border rounded-pill px-3 py-2">
                            <i class="bi {{ $dispen->hasTtdGuruPiket() ? 'bi-check-circle-fill' : 'bi-x-circle-fill' }} me-1"></i>
                            TTD Guru Piket: {{ $dispen->hasTtdGuruPiket() ? 'Ada' : 'Belum TTD' }}
                        </span>
                        <span class="badge {{ $dispen->hasTtdWakaKesiswaan() ? 'bg-success-subtle text-success border-success-subtle' : 'bg-danger-subtle text-danger border-danger-subtle' }} border rounded-pill px-3 py-2">
                            <i class="bi {{ $dispen->hasTtdWakaKesiswaan() ? 'bi-check-circle-fill' : 'bi-x-circle-fill' }} me-1"></i>
                            TTD Waka Kesiswaan: {{ $dispen->hasTtdWakaKesiswaan() ? 'Ada' : 'Belum TTD' }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Action Buttons Dynamic --}}
            <div class="mt-4 pt-3 border-top">
                @if(!$dispen->isKeluarGerbang())
                    {{-- 1. Belum Keluar Gerbang (keluar_gerbang_at / jam_keluar IS NULL) --}}
                    @if($bolehKeluar)
                        <div class="alert border-0 rounded-4 mb-3 py-3 px-4 d-flex align-items-center gap-3 bg-success-subtle text-success" role="alert">
                            <i class="bi bi-patch-check-fill fs-5"></i>
                            <div>
                                <strong>Surat izin valid untuk keluar hari ini.</strong><br>
                                <span class="small">Izinkan siswa keluar gerbang (status menjadi "Siswa Out").</span>
                            </div>
                        </div>
                        <form method="POST" action="{{ route('satpam.dispen.keluar', $dispen) }}" onsubmit="return confirm('Konfirmasi izinkan \'{{ $dispen->siswa?->nama }}\' keluar gerbang sekarang?')">
                            @csrf
                            <button type="submit" class="btn btn-primary rounded-3 px-4 py-2 fw-semibold shadow-sm">
                                🚪 Verifikasi / Konfirmasi Keluar Gerbang
                            </button>
                        </form>
                    @else
                        <div class="alert border-0 rounded-4 mb-0 py-3 px-4 d-flex align-items-center gap-3 {{
                            $dispen->isExpired() || !$isTtdLengkap ? 'bg-danger-subtle text-danger' : 'bg-warning-subtle text-warning-emphasis'
                        }}" role="alert">
                            <i class="bi bi-info-circle-fill fs-5"></i>
                            <div>
                                @if(!$isTtdLengkap)
                                    <strong>Verifikasi Keluar Gagal! Surat Dispensasi belum ditandatangani lengkap oleh Siswa, Guru Piket, atau Waka Kesiswaan.</strong><br>
                                    <span class="small">Tanda tangan yang belum diisi: <strong>{{ implode(', ', $dispen->missingSignatures()) }}</strong>.</span>
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
                @elseif($dispen->isKeluarGerbang() && !$dispen->isKembali() && $dispen->isMenungguKembali())
                    {{-- 2. keluar_gerbang_at NOT NULL & kembali_at IS NULL (Status: Siswa Out & Menunggu Kembali) --}}
                    <div class="alert border-0 rounded-4 mb-3 py-3 px-4 d-flex align-items-center gap-3 bg-info-subtle text-info-emphasis" role="alert">
                        <i class="bi bi-arrow-left-circle-fill fs-5"></i>
                        <div>
                            <strong>Status: Siswa Out.</strong> Siswa keluar gerbang pada {{ optional($dispen->keluar_gerbang_at)->format('H:i') }}.<br>
                            <span class="small">Klik tombol di bawah untuk memproses verifikasi masuk / kembali ke sekolah.</span>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('satpam.dispen.kembali', $dispen) }}" onsubmit="return confirm('Konfirmasi \'{{ $dispen->siswa?->nama }}\' sudah kembali ke sekolah?')">
                        @csrf
                        <button type="submit" class="btn btn-success rounded-3 px-4 py-2 fw-semibold shadow-sm">
                            📥 Verifikasi Masuk / Konfirmasi Kembali ke Sekolah
                        </button>
                    </form>
                @elseif($dispen->isMangkir())
                    {{-- Mangkir / Bolos --}}
                    <div class="alert border-0 rounded-4 mb-0 py-3 px-4 d-flex align-items-center gap-3 bg-danger-subtle text-danger" role="alert">
                        <i class="bi bi-x-octagon-fill fs-5"></i>
                        <div>
                            <strong>MANGKIR / BOLOS.</strong><br>
                            <span class="small">Siswa belum kembali melewati Rencana Jam Kembali + 1 JP. Presensi JP terkait otomatis dicatat Alfa.</span>
                        </div>
                    </div>
                @elseif($dispen->isKeluarGerbang() && !$dispen->isKembali())
                    {{-- Siswa Out tapi tidak kembali hari ini (izin hingga pulang) --}}
                    <div class="alert border-0 rounded-4 mb-0 py-3 px-4 d-flex align-items-center gap-3 bg-info-subtle text-info-emphasis" role="alert">
                        <i class="bi bi-info-circle-fill fs-5"></i>
                        <div>
                            <strong>Siswa Out (Tidak Kembali Hari Ini).</strong><br>
                            <span class="small">Siswa diizinkan tidak kembali ke sekolah hingga jam pulang (keluar pada {{ optional($dispen->keluar_gerbang_at)->format('H:i') }}).</span>
                        </div>
                    </div>
                @else
                    {{-- 3. Keduanya NOT NULL (keluar_gerbang_at NOT NULL & kembali_at NOT NULL) --}}
                    <div class="alert border-0 rounded-4 mb-3 py-3 px-4 d-flex align-items-center gap-3 bg-secondary-subtle text-secondary" role="alert">
                        <i class="bi bi-check-circle-fill fs-5 text-success"></i>
                        <div>
                            <strong>Dispensasi Selesai.</strong> Siswa sudah kembali ke sekolah pada {{ optional($dispen->kembali_at)->format('H:i') }}.
                        </div>
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="badge bg-secondary p-2">
                            ✅ Dispensasi Selesai (Siswa Sudah Kembali)
                        </span>
                    </div>
                @endif
            </div>
        </div>

        @if($daftarDispen->count() > 1)
            {{-- Riwayat dispensasi lain siswa --}}
            <div class="table-card-custom">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <h5 class="fw-bold text-dark mb-0">Riwayat Dispensasi Lainnya</h5>
                    <span class="text-muted small">{{ $daftarDispen->count() - 1 }} record</span>
                </div>
                <div class="table-responsive w-full overflow-x-auto">
                    <table class="table table-custom align-middle mb-0 min-w-full">
                        <thead>
                            <tr>
                                <th class="whitespace-nowrap">TANGGAL</th>
                                <th class="whitespace-nowrap">JAM KE</th>
                                <th>ALASAN</th>
                                <th class="text-end whitespace-nowrap">STATUS</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($daftarDispen as $d)
                                @if($d->id === $dispen->id) @continue @endif
                                <tr>
                                    <td class="whitespace-nowrap">{{ $d->tanggal->translatedFormat('d M Y') }} @if($d->tanggal->toDateString() === now()->toDateString())<span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill ms-1">HARI INI</span>@endif</td>
                                    <td class="whitespace-nowrap">{{ $d->jam_ke ?? '-' }}</td>
                                    <td>{{ \Illuminate\Support\Str::limit($d->alasan, 50) }}</td>
                                    <td class="text-end whitespace-nowrap">
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
                <span class="small">Siswa belum memiliki surat izin/dispensasi yang sah hari ini.</span>
            </div>
        </div>
    @else
        <div class="alert border-0 rounded-4 mb-4 py-3 px-4 d-flex align-items-center gap-3 bg-danger-subtle text-danger" role="alert">
            <i class="bi bi-x-circle-fill fs-5"></i>
            <div>
                <strong>Tidak ditemukan.</strong><br>
                <span class="small">Nomor surat, kode unik, NIS, NISN, atau nama tidak cocok dengan surat izin/dispensasi mana pun.</span>
            </div>
        </div>
    @endif
</div>
@endsection

@if($kolektif)
@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modalEl = document.getElementById('modalVerifikasiRombongan');
        if (! modalEl) {
            return;
        }

        const defaultMode = '{{ $defaultMode ?? 'keluar' }}';
        const formKeluar = document.getElementById('formRombonganKeluar');
        const formKembali = document.getElementById('formRombonganKembali');

        function setMode(mode) {
            const elShow = mode === 'kembali' ? formKembali : formKeluar;
            const elHide = mode === 'kembali' ? formKeluar : formKembali;
            elShow.classList.remove('d-none');
            elHide.classList.add('d-none');

            document.querySelectorAll('.js-switch-mode').forEach(function (btn) {
                if (btn.dataset.mode === mode) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });
        }

        modalEl.addEventListener('shown.bs.modal', function (e) {
            const opener = e.relatedTarget;
            const mode = opener && opener.dataset.mode ? opener.dataset.mode : defaultMode;
            setMode(mode);
        });

        document.querySelectorAll('.js-switch-mode').forEach(function (btn) {
            btn.addEventListener('click', function () {
                setMode(btn.dataset.mode);
            });
        });

        document.querySelectorAll('.js-select-all').forEach(function (selAll) {
            const form = document.querySelector(selAll.dataset.target);
            if (! form) {
                return;
            }

            const boxes = function () {
                return Array.prototype.slice.call(form.querySelectorAll('.js-check-item'));
            };

            const update = function () {
                const list = boxes();
                selAll.checked = list.length > 0 && list.every(function (cb) { return cb.checked; });
                const counter = form.querySelector('.js-count-selected');
                if (counter) {
                    counter.textContent = list.filter(function (cb) { return cb.checked; }).length;
                }
            };

            selAll.addEventListener('change', function () {
                boxes().forEach(function (cb) { cb.checked = selAll.checked; });
                update();
            });

            boxes().forEach(function (cb) {
                cb.addEventListener('change', update);
            });

            update();
        });
    });
</script>
@endpush
@endif
