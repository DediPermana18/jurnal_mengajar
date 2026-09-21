<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Persetujuan Cepat Guru Piket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: linear-gradient(180deg, #eef2f7 0%, #f8fafc 100%); color: #0f172a; font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; }
        .ttd-img { max-height: 84px; max-width: 100%; object-fit: contain; }
    </style>
</head>
<body class="py-4 py-md-5">
    @php
        $guru = $izin?->user;
    @endphp

    <div class="container" style="max-width: 760px;">
        {{-- Header --}}
        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center mb-2" style="width: 56px; height: 56px; border-radius: 16px; background: #0f172a; color: #fff;">
                <i class="bi bi-clipboard2-check fs-3"></i>
            </div>
            <h4 class="fw-bold mb-1" style="letter-spacing: -0.02em;">Persetujuan Cepat Guru Piket</h4>
            <p class="text-muted mb-0 small">Sistem Izin Digital — Tahap Verifikasi Piket · via Tautan Unik</p>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm d-flex align-items-center gap-2" role="alert"><i class="bi bi-check-circle-fill fs-5"></i><div>{{ session('success') }}</div><button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button></div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm d-flex align-items-center gap-2" role="alert"><i class="bi bi-exclamation-triangle-fill fs-5"></i><div>{{ session('error') }}</div><button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button></div>
        @endif

        {{-- ===== INVALID ===== --}}
        @if($state === 'invalid')
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body text-center py-5">
                    <i class="bi bi-link-45deg" style="font-size: 3rem; color: #64748b;"></i>
                    <h5 class="fw-bold mt-3">Tautan Tidak Valid</h5>
                    <p class="text-muted mb-0">Tautan persetujuan Guru Piket tidak ditemukan atau telah kedaluwarsa. Silakan minta ulang dari guru yang bersangkutan.</p>
                </div>
            </div>
        @endif

        @if($izin)
            {{-- ===== SUDAH DIPROSES (final: approved/rejected) ===== --}}
            @if(in_array($state, ['approved', 'rejected'], true))
                <div class="alert alert-info border-0 rounded-4 shadow-sm d-flex align-items-center gap-2 mb-4" role="alert">
                    <i class="bi bi-info-circle-fill fs-5"></i>
                    <div><strong>Pengajuan ini sudah diproses.</strong> Tautan ini tidak dapat digunakan lagi untuk mengubah status pengajuan izin.</div>
                </div>
            @endif

            {{-- ===== ANTI DOUBLE APPROVAL: sudah diproses piket lain ===== --}}
            @if($state === 'processed')
                <div class="alert alert-warning border-0 rounded-4 shadow-sm d-flex align-items-center gap-2 mb-4" role="alert">
                    <i class="bi bi-shield-check fs-5"></i>
                    <div>
                        <strong>Izin ini sudah diproses oleh Guru Piket lain.</strong>
                        Pengajuan izin atas nama <strong>{{ $guru->nama ?? '-' }}</strong> sudah diverifikasi dan tidak perlu ditindaklanjuti lagi melalui tautan ini.
                    </div>
                </div>
            @endif

            {{-- Ringkasan pengajuan --}}
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <div class="fw-bold fs-5">Pengajuan Izin Guru</div>
                        <span class="badge {{ $izin->status_badge }} rounded-pill px-3 py-2">{{ $izin->status_label }}</span>
                    </div>
                    <hr>
                    <div class="row g-3">
                        <div class="col-6"><div class="text-muted small">Nama Guru</div><div class="fw-semibold text-dark">{{ $guru->nama ?? '-' }}</div></div>
                        <div class="col-6"><div class="text-muted small">NIP</div><div class="fw-semibold text-dark">{{ $guru->nip ?? '-' }}</div></div>
                        <div class="col-6"><div class="text-muted small">Hari / Tanggal</div><div class="fw-semibold text-dark">{{ $izin->tanggal->translatedFormat('l, d F Y') }}</div></div>
                        <div class="col-12"><div class="text-muted small">Alasan</div><div class="fw-semibold text-dark">{{ $izin->alasan }}</div></div>
                        @if($izin->tugas_siswa)
                            <div class="col-12"><div class="text-muted small">Tugas untuk Siswa</div><div class="fw-semibold text-dark">{{ $izin->tugas_siswa }}</div></div>
                        @endif
                    </div>

                    @if($izin->has_ttd_guru)
                        <div class="mt-3 pt-3 border-top">
                            <div class="text-muted small mb-2"><i class="bi bi-pencil me-1"></i>Tanda Tangan Guru (Pemohon)</div>
                            <div class="border rounded-3 p-2 bg-light-subtle d-inline-block"><img src="{{ $izin->ttd_guru_url }}" class="ttd-img" alt="TTD Guru"></div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- ===== FORM READY (masih menunggu piket) ===== --}}
            @if($state === 'ready')
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4">
                        @if($isSelfOwner)
                            <div class="alert alert-danger border-0 rounded-3 d-flex align-items-center gap-2 py-2 px-3 mb-3">
                                <i class="bi bi-person-slash fs-5"></i>
                                <div>Anda adalah <strong>pemohon izin ini</strong>. Guru Piket lain yang bertugas wajib melakukan verifikasi — Anda tidak dapat menyetujui pengajuan sendiri.</div>
                            </div>
                        @endif

                        @if($authPiket)
                            <div class="alert alert-info border-0 rounded-3 d-flex align-items-center gap-2 py-2 px-3 mb-3">
                                <i class="bi bi-person-lock-fill fs-5"></i>
                                <div>
                                    <div class="text-uppercase fw-bold text-muted" style="font-size: 0.68rem; letter-spacing: 0.05em;">Petugas Piket / Waka Piket</div>
                                    <div class="fw-semibold text-dark"><i class="bi bi-person-check me-1 text-success"></i>{{ auth()->user()->nama }}</div>
                                </div>
                            </div>
                        @elseif($daftarPiket->count() === 1)
                            @php $singlePiket = $daftarPiket->first(); @endphp
                            <div class="alert alert-success border-0 rounded-3 d-flex align-items-center gap-2 py-2 px-3 mb-3">
                                <i class="bi bi-person-check fs-5"></i>
                                <div>Petugas verifikasi hari itu: <strong>{{ $singlePiket->nama }}</strong> — otomatis terpilih.</div>
                            </div>
                        @elseif($daftarPiket->isNotEmpty())
                            <div class="mb-3">
                                <label for="selectPiket" class="form-label small fw-semibold text-secondary">
                                    Pilih Guru Piket / Waka Piket yang bertugas (verifikator) <span class="text-danger">*</span>
                                </label>
                                <select name="approved_by_piket_id" id="selectPiket" class="form-select rounded-3" required>
                                    <option value="" disabled selected>-- Pilih Petugas Piket / Waka Piket --</option>
                                    @foreach($daftarPiket as $piket)
                                        <option value="{{ $piket->id }}">{{ $piket->nama }} ({{ $piket->nip ?? 'Non-NIP' }})</option>
                                    @endforeach
                                </select>
                            </div>
                        @else
                            <div class="alert alert-warning border-0 rounded-3 mb-3">
                                <i class="bi bi-exclamation-triangle me-1"></i>Tidak ada Guru Piket / Waka Piket terdaftar pada tanggal tersebut. Hubungi admin untuk verifikasi manual via dashboard.
                            </div>
                        @endif

                        @if(! $isSelfOwner && ($authPiket || $daftarPiket->isNotEmpty()))
                            <form method="POST" action="{{ route('piket.quick-approve.submit', $token) }}">
                                @csrf
                                @if($authPiket)
                                    <input type="hidden" name="approved_by_piket_id" value="{{ $authPiket->id }}">
                                @elseif($daftarPiket->count() === 1)
                                    <input type="hidden" name="approved_by_piket_id" value="{{ $singlePiket->id }}">
                                @endif
                                <button type="submit" class="btn btn-success rounded-3 w-100 py-2 fw-semibold">
                                    <i class="bi bi-check2-circle me-1"></i>Setujui Pengajuan
                                </button>
                            </form>
                            <p class="text-muted small text-center mt-2 mb-0"><i class="bi bi-info-circle me-1"></i>Setelah disetujui, pengajuan otomatis diteruskan ke {{ $level === 3 ? 'Waka SDM' : ($level === 2 ? 'Kepala Sekolah' : 'status DISETUJUI (alur 1 level)') }}.</p>
                        @endif
                    </div>
                </div>
            @endif
        @endif
    </div>
</body>
</html>