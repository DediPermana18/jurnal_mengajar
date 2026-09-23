@extends('layouts.app')

@section('title', 'Kelola Piket Shift (Koordinator) - WebJournal')

@push('styles')
<style>
    .table-custom-kp th {
        background: #f8fafc;
        font-size: 0.76rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        font-weight: 700;
        padding: 0.85rem 1rem;
        border-bottom: 1px solid #e2e8f0;
    }
    .table-custom-kp td {
        padding: 0.85rem 1rem;
        vertical-align: middle;
        font-size: 0.86rem;
        border-bottom: 1px solid #f1f5f9;
    }
    .shift-card-header-pagi {
        background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%);
        border-bottom: 1px solid #fed7aa;
    }
    .shift-card-header-siang {
        background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
        border-bottom: 1px solid #bfdbfe;
    }
    .catatan-kotak {
        background: #f8fafc;
        border: 1px dashed #cbd5e1;
        border-radius: 0.75rem;
        padding: 0.9rem 1rem;
        font-size: 0.87rem;
        color: #334155;
        min-height: 70px;
        white-space: pre-wrap;
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">

    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2.5 py-1 rounded-pill fw-semibold text-xs">
                    <i class="bi bi-star-fill me-1"></i> Tugas Tambahan
                </span>
                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2.5 py-1 rounded-pill fw-semibold text-xs">
                    <i class="bi bi-people-fill me-1"></i> Koordinator Piket
                </span>
            </div>
            <h2 class="fw-black text-dark mb-1" style="font-weight: 900; font-size: 1.55rem; letter-spacing: -0.02em;">
                Kelola Piket Shift (Koordinator)
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.875rem;">
                Ringkasan shift yang Anda pimpin hari ini — daftar petugas, monitoring kehadiran guru,
                dan penyusunan Rekap Piket Shift untuk dikirim ke Waka Piket.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge bg-light border rounded-3 px-3 py-2 fw-semibold text-dark text-xs">
                <i class="bi bi-calendar3 text-primary me-1"></i> {{ $hariIniStr }}, {{ $now->format('d/m/Y') }}
            </span>
            <a href="{{ route('home') }}" class="btn btn-sm btn-light border rounded-3 fw-semibold text-xs">
                <i class="bi bi-speedometer2 text-primary"></i> Dashboard
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4 d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4 d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <div>{{ session('error') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger rounded-3 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center gap-2 mb-1">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <strong class="fw-bold">Data belum dapat disimpan.</strong>
            </div>
            <ul class="mb-0 ps-3" style="font-size: 0.85rem;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Status rekap hari ini (dikirim ke Waka Piket untuk validasi) --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body py-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-clipboard-data text-primary fs-5"></i>
                <div>
                    <div class="fw-bold text-dark" style="font-size: 0.9rem;">Rekap Piket Harian {{ $todayStr }}</div>
                    <div class="text-muted" style="font-size: 0.78rem;">
                        @if($rekap->exists)
                            Catatan shift Anda tersimpan dan menunggu validasi Waka Piket.
                        @else
                            Belum ada rekap untuk tanggal ini — buat lewat formulir di bawah.
                        @endif
                    </div>
                </div>
            </div>
            <span class="badge px-3 py-1.5 rounded-pill fw-semibold text-xs {{ $rekap->exists ? $rekap->status_badge : 'bg-secondary-subtle text-secondary border border-secondary-subtle' }}">
                {{ $rekap->exists ? $rekap->status_label : 'Belum Ada' }}
            </span>
        </div>
    </div>

    @if(empty($shiftsSaya))
        {{-- Peninjau (Petugas IT) tanpa jadwal koordinator hari ini --}}
        <div class="alert alert-info border rounded-3 shadow-sm mb-0 d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-info-circle-fill fs-5"></i>
            <div>Tidak ada shift yang dipimpin pada hari ini. Menu koordinator muncul otomatis berdasarkan jadwal piket yang sedang berjalan.</div>
        </div>
    @else
        @foreach($shiftsSaya as $shift)
            @php
                $anggota = $tim[$shift];
                $info = $infoShift[$shift];
                $records = $kehadiran[$shift];
                $kolomCatatan = $shift === 'pagi' ? $rekap->catatan_pagi : $rekap->catatan_siang;
            @endphp

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                {{-- Header shift --}}
                <div class="card-body py-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-2 {{ $shift === 'pagi' ? 'shift-card-header-pagi' : 'shift-card-header-siang' }}">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-white border shadow-sm" style="width: 42px; height: 42px;">
                            <i class="bi {{ $info['ikon'] }} text-warning fs-5"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-dark mb-0" style="font-size: 1rem;">
                                {{ $info['label'] }}
                                @if($info['jam'])
                                    <span class="text-muted fw-normal" style="font-size: 0.8rem;">· {{ $info['jam'] }}</span>
                                @endif
                            </div>
                            <div class="text-muted" style="font-size: 0.78rem;">
                                {{ $anggota->count() }} petugas piket terjadwal · {{ $hariIniStr }}
                            </div>
                        </div>
                    </div>
                    <span class="badge bg-white border rounded-pill fw-semibold text-dark text-xs px-3 py-1.5">
                        <i class="bi bi-person-check me-1 text-success"></i> Shift Anda
                    </span>
                </div>

                {{-- Monitoring kehadiran anggota shift --}}
                <form method="POST" action="{{ route('koordinator.piket.kehadiran') }}">
                    @csrf
                    <input type="hidden" name="tanggal" value="{{ $todayStr }}">
                    <input type="hidden" name="shift" value="{{ $shift }}">

                    <div class="table-responsive">
                        <table class="table table-custom-kp mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 42px;">#</th>
                                    <th>Nama Guru</th>
                                    <th>NIP</th>
                                    <th style="width: 170px;">Status Kehadiran</th>
                                    <th style="width: 30%;">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($anggota as $index => $guru)
                                    @php
                                        $rec = $records->firstWhere('user_id', $guru->id);
                                        $statusAwal = old('status.'.$guru->id, $rec ? $rec->status : \App\Models\StatusKehadiranGuru::STATUS_HADIR);
                                        $ketAwal = old('keterangan.'.$guru->id, $rec ? $rec->keterangan : null);
                                    @endphp
                                    <tr>
                                        <td class="text-muted">{{ $index + 1 }}</td>
                                        <td>
                                            <span class="fw-semibold text-dark">{{ $guru->nama }}</span>
                                        </td>
                                        <td class="text-muted">{{ $guru->nip ?: '-' }}</td>
                                        <td>
                                            <select name="status[{{ $guru->id }}]"
                                                    class="form-select form-select-sm rounded-3 border fw-semibold"
                                                    style="font-size: 0.8rem;">
                                                @foreach(\App\Models\StatusKehadiranGuru::STATUSES as $st)
                                                    <option value="{{ $st }}" {{ $statusAwal === $st ? 'selected' : '' }}>
                                                        {{ $st }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text"
                                                   name="keterangan[{{ $guru->id }}]"
                                                   value="{{ $ketAwal }}"
                                                   maxlength="1000"
                                                   placeholder="Catatan singkat (opsional)"
                                                   class="form-control form-control-sm rounded-3 border"
                                                   style="font-size: 0.8rem;">
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4" style="font-size: 0.85rem;">
                                            Belum ada petugas piket terjadwal untuk shift ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($anggota->isNotEmpty())
                        <div class="d-flex justify-content-end px-4 py-3 border-top bg-light">
                            <button type="submit" class="btn btn-primary btn-sm rounded-3 fw-semibold text-xs px-3">
                                <i class="bi bi-save me-1"></i> Simpan Monitoring Kehadiran
                            </button>
                        </div>
                    @endif
                </form>

                {{-- Penyusunan Rekap Piket Shift -> Waka Piket --}}
                <div class="card-body border-top bg-light">
                    <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                        <h6 class="fw-bold text-dark mb-0" style="font-size: 0.9rem;">
                            <i class="bi bi-send-check me-1 text-primary"></i> Rekap Piket Shift {{ $info['label'] }}
                        </h6>
                        @if($rekap->exists)
                            <span class="badge px-3 py-1 rounded-pill fw-semibold text-xs {{ $rekap->status_badge }}">
                                {{ $rekap->status_label }}
                            </span>
                        @endif
                    </div>

                    @if($rekap->exists && $rekap->isValidated())
                        {{-- Rekap sudah divalidasi Waka Piket: dokumen terkunci --}}
                        <div class="alert alert-success border-0 rounded-3 text-xs d-flex align-items-center gap-2 mb-3">
                            <i class="bi bi-shield-lock-fill"></i>
                            <div>Rekap pada tanggal ini sudah divalidasi Waka Piket dan tidak dapat diubah lagi.</div>
                        </div>
                        <div class="catatan-kotak">{{ $kolomCatatan ?: '(tidak ada catatan)' }}</div>
                    @else
                        <form method="POST" action="{{ route('koordinator.piket.rekap') }}">
                            @csrf
                            <input type="hidden" name="tanggal" value="{{ $todayStr }}">
                            <input type="hidden" name="shift" value="{{ $shift }}">
                            <textarea name="catatan"
                                      rows="3"
                                      maxlength="2000"
                                      required
                                      class="form-control rounded-3 border mb-3"
                                      style="font-size: 0.86rem;"
                                      placeholder="Tulis ringkasan kejadian, kendala, atau aktivitas shift {{ $info['label'] }} hari ini...">{{ old('catatan', $kolomCatatan) }}</textarea>
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <span class="text-muted" style="font-size: 0.75rem;">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Rekap berstatus draft hingga divalidasi Waka Piket.
                                </span>
                                <button type="submit" class="btn btn-primary btn-sm rounded-3 fw-semibold text-xs px-3">
                                    <i class="bi bi-send me-1"></i> Kirim Rekap Shift ke Waka Piket
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach
    @endif

</div>
@endsection
