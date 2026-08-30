@extends('layouts.app')

@section('title', 'Detail Izin Guru')

@section('content')
<div class="container-fluid px-0" style="max-width: 900px;">

    {{-- Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="font-weight: 900; font-size: 1.75rem; letter-spacing: -0.02em;">
                Detail Izin
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                {{ $izin->tanggal->translatedFormat('l, d F Y') }} &bull; {{ auth()->user()->nama }}
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('guru.izin.index') }}" class="btn btn-outline-secondary rounded-3 px-3 py-2 fw-semibold">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
        </div>
    </div>

    {{-- Status --}}
    <div class="d-flex flex-wrap align-items-center gap-2 mb-4">
        <span class="badge {{ $izin->status_badge }} rounded-pill px-3 py-2 fs-6">{{ $izin->status_label }}</span>
        @if($izin->isRejected() && $izin->catatan_penolakan)
            <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3 py-2">
                <i class="bi bi-chat-left-text me-1"></i>{{ $izin->catatan_penolakan }}
            </span>
        @endif
    </div>

    <div class="row g-4">
        {{-- Detail --}}
        <div class="col-lg-7">
            <div class="table-card-custom mb-4">
                <h5 class="fw-bold text-dark mb-3">Informasi Izin</h5>
                <div class="row g-3">
                    <div class="col-6">
                        <div class="text-muted small">Nama Guru</div>
                        <div class="fw-semibold">{{ $izin->user?->nama ?? '-' }}</div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted small">NIP</div>
                        <div class="fw-semibold">{{ $izin->user?->nip ?: '-' }}</div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted small">Hari / Tanggal</div>
                        <div class="fw-semibold">{{ $izin->tanggal->translatedFormat('l, d/m/Y') }}</div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted small">Diajukan Pada</div>
                        <div class="fw-semibold">{{ $izin->created_at?->translatedFormat('d/m/Y H:i') }}</div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted small">Alasan</div>
                        <div class="fw-semibold">{{ $izin->alasan }}</div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted small">Tugas untuk Siswa</div>
                        <div class="fw-semibold">{{ $izin->tugas_siswa ?: '-' }}</div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted small">Lampiran/Bukti</div>
                        @if($izin->lampiran)
                            <a href="{{ route('guru.izin.lampiran', $izin->id) }}" target="_blank" class="btn btn-sm btn-outline-primary rounded-3">
                                <i class="bi bi-paperclip me-1"></i>Lihat Lampiran
                            </a>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- TTD --}}
            <div class="table-card-custom">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-signature me-2 text-primary"></i> Tanda Tangan</h5>
                <div class="row g-4">
                    <div class="col-sm-4 text-center">
                        <div class="text-muted small mb-2">Guru (Pemohon)</div>
                        @if($izin->has_ttd_guru)
                            <img src="{{ $izin->ttd_guru_url }}" class="border rounded-3 p-2 bg-white" style="max-height:80px; max-width:100%;" alt="TTD Guru">
                        @else
                            <span class="text-muted small fst-italic">Belum ada</span>
                        @endif
                    </div>
                    <div class="col-sm-4 text-center">
                        <div class="text-muted small mb-2">Waka</div>
                        @if($izin->has_ttd_waka)
                            <img src="{{ $izin->ttd_waka_url }}" class="border rounded-3 p-2 bg-white" style="max-height:80px; max-width:100%;" alt="TTD Waka">
                            <div class="text-muted small mt-1">{{ $izin->approverWaka?->nama }}</div>
                        @else
                            <span class="text-muted small fst-italic">Belum ada</span>
                        @endif
                    </div>
                    <div class="col-sm-4 text-center">
                        <div class="text-muted small mb-2">Kepala Sekolah</div>
                        @if($izin->has_ttd_kepsek)
                            <img src="{{ $izin->ttd_kepsek_url }}" class="border rounded-3 p-2 bg-white" style="max-height:80px; max-width:100%;" alt="TTD Kepsek">
                            <div class="text-muted small mt-1">{{ $izin->approverKepsek?->nama }}</div>
                        @else
                            <span class="text-muted small fst-italic">Belum ada</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Timeline / Alur --}}
        <div class="col-lg-5">
            <div class="table-card-custom">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-diagram-3 me-2 text-primary"></i> Alur Persetujuan</h5>
                @php
                    $level = \App\Models\PengaturanJadwal::izinApprovalLevel();
                    $steps = [];
                    $steps[] = ['Proses' => 'Pengajuan oleh Guru', 'done' => true, 'sub' => optional($izin->created_at)->translatedFormat('d/m/Y H:i')];
                    if ($level >= 1) $steps[] = ['Proses' => 'Verifikasi Guru Piket', 'done' => (bool) $izin->approved_by_piket || $izin->isApproved() || $izin->status === \App\Models\IzinGuru::STATUS_PENDING_WAKA || $izin->status === \App\Models\IzinGuru::STATUS_PENDING_KEPSEK, 'sub' => $izin->approverPiket?->nama];
                    if ($level >= 3) $steps[] = ['Proses' => 'Persetujuan Waka', 'done' => (bool) $izin->approved_by_waka || $izin->isApproved(), 'sub' => $izin->approverWaka?->nama];
                    if ($level >= 2) $steps[] = ['Proses' => 'Persetujuan Kepala Sekolah', 'done' => $izin->isApproved(), 'sub' => $izin->approverKepsek?->nama];
                @endphp
                <ul class="list-unstyled mb-0">
                    @foreach($steps as $i => $s)
                        <li class="d-flex gap-3 pb-3 {{ $loop->last ? '' : 'border-bottom' }}">
                            <div class="flex-shrink-0 text-center" style="width: 34px;">
                                @if($s['done'])
                                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success text-white" style="width:28px;height:28px;"><i class="bi bi-check-lg"></i></span>
                                @else
                                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-light text-muted border" style="width:28px;height:28px;">{{ $i + 1 }}</span>
                                @endif
                            </div>
                            <div>
                                <div class="fw-semibold text-dark {{ $s['done'] ? '' : 'text-muted' }}">{{ $s['Proses'] }}</div>
                                <div class="text-muted small">{{ $s['sub'] ?? '-' }}</div>
                            </div>
                        </li>
                    @endforeach
                </ul>
                @if($izin->isApproved() && $izin->approved_at)
                    <div class="alert alert-success rounded-3 mb-0 mt-1 text-center">
                        <i class="bi bi-check2-circle me-1"></i> Disetujui pada {{ $izin->approved_at->translatedFormat('d F Y, H:i') }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
