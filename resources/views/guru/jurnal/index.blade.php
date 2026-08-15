@extends('layouts.app')

@section('title', 'Jurnal Mengajar - WebJournal')

@push('styles')
<style>
    .jadwal-card {
        background: #ffffff;
        border: 1px solid #e8eef5;
        border-radius: 16px;
        box-shadow: 0 2px 12px rgba(15, 23, 42, 0.05);
        transition: box-shadow 0.2s ease, transform 0.2s ease;
    }

    .jadwal-card:not(.locked):hover {
        box-shadow: 0 6px 24px rgba(15, 23, 42, 0.09);
        transform: translateY(-1px);
    }

    .jadwal-card.locked {
        opacity: 0.72;
        background: #f8fafc;
    }

    .status-badge-belum {
        background-color: #fffbeb;
        color: #d97706;
        border: 1px solid #fde68a;
        border-radius: 50px;
        padding: 0.3rem 0.8rem;
        font-size: 0.78rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        white-space: nowrap;
    }

    .jam-badge {
        background: #eff6ff;
        color: #1d4ed8;
        border: 1px solid #bfdbfe;
        border-radius: 10px;
        padding: 0.35rem 0.75rem;
        font-weight: 800;
        font-size: 0.85rem;
    }

    .empty-state-icon {
        width: 72px;
        height: 72px;
        border-radius: 50%;
        background: #eff6ff;
        color: #1677ff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        margin: 0 auto 1rem;
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 800; font-size: 1.75rem;">
                Jurnal Mengajar
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                Jadwal mengajar <strong>{{ $hari }}</strong>, {{ \Carbon\Carbon::parse($today)->translatedFormat('d F Y') }}
            </p>
        </div>
        <a href="{{ route('guru.dashboard') }}" class="btn btn-light border rounded-3 px-3 py-2 fw-semibold">
            <i class="bi bi-arrow-left me-1"></i> Dashboard
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success border-0 rounded-4 shadow-sm d-flex align-items-center gap-2">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger border-0 rounded-4 shadow-sm d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    @if($jadwals->isEmpty())
        <div class="table-card-custom text-center py-5">
            <div class="empty-state-icon">
                <i class="bi bi-calendar-x"></i>
            </div>
            <h5 class="fw-bold text-dark mb-2">Tidak Ada Jadwal Mengajar</h5>
            <p class="text-muted mb-0">Anda tidak memiliki jadwal mengajar pada hari {{ $hari }}.</p>
        </div>
    @else
        <div class="table-card-custom mb-4 d-none d-lg-block">
            <div class="table-responsive">
                <table class="table table-custom align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Jam Ke-</th>
                            <th>Waktu</th>
                            <th>Kelas</th>
                            <th>Mata Pelajaran</th>
                            <th>Status Jurnal</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($jadwals as $item)
                            <tr class="{{ !$item->can_fill ? 'text-muted' : '' }}">
                                <td><span class="jam-badge">Jam {{ $item->jam_ke }}</span></td>
                                <td>{{ $item->waktu }}</td>
                                <td><span class="badge bg-light text-dark border">{{ $item->kelas }}</span></td>
                                <td>{{ $item->mapel }}</td>
                                <td>
                                    @if($item->is_filled)
                                        <span class="status-badge-terisi">
                                            <i class="bi bi-check-circle-fill"></i> Sudah Terisi
                                        </span>
                                    @else
                                        <span class="status-badge-belum">
                                            <i class="bi bi-clock-history"></i> Belum Terisi
                                        </span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($item->can_fill)
                                        <a href="{{ route('guru.jurnal.form', $item->jadwal->id) }}"
                                           class="btn btn-primary btn-sm rounded-3 px-3 fw-semibold">
                                            <i class="bi bi-pencil-square me-1"></i> Isi Jurnal
                                        </a>
                                    @else
                                        <button type="button"
                                                class="btn btn-secondary btn-sm rounded-3 px-3 fw-semibold"
                                                disabled
                                                title="{{ $item->lock_reason }}">
                                            <i class="bi bi-lock-fill me-1"></i> Isi Jurnal
                                        </button>
                                        @if($item->lock_reason)
                                            <div class="small text-muted mt-1">{{ $item->lock_reason }}</div>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Mobile: Card View --}}
        <div class="d-lg-none">
            <div class="row g-3">
                @foreach($jadwals as $item)
                    <div class="col-12">
                        <div class="jadwal-card p-4 {{ !$item->can_fill ? 'locked' : '' }}">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <span class="jam-badge">Jam {{ $item->jam_ke }}</span>
                                @if($item->is_filled)
                                    <span class="status-badge-terisi">
                                        <i class="bi bi-check-circle-fill"></i> Sudah Terisi
                                    </span>
                                @else
                                    <span class="status-badge-belum">
                                        <i class="bi bi-clock-history"></i> Belum Terisi
                                    </span>
                                @endif
                            </div>

                            <div class="mb-2">
                                <small class="text-muted text-uppercase fw-bold" style="font-size: 0.68rem; letter-spacing: 0.06em;">Waktu</small>
                                <div class="fw-semibold">{{ $item->waktu }}</div>
                            </div>
                            <div class="mb-2">
                                <small class="text-muted text-uppercase fw-bold" style="font-size: 0.68rem; letter-spacing: 0.06em;">Kelas</small>
                                <div class="fw-semibold">{{ $item->kelas }}</div>
                            </div>
                            <div class="mb-3">
                                <small class="text-muted text-uppercase fw-bold" style="font-size: 0.68rem; letter-spacing: 0.06em;">Mata Pelajaran</small>
                                <div class="fw-semibold">{{ $item->mapel }}</div>
                            </div>

                            @if($item->can_fill)
                                <a href="{{ route('guru.jurnal.form', $item->jadwal->id) }}"
                                   class="btn btn-primary w-100 rounded-3 fw-semibold">
                                    <i class="bi bi-pencil-square me-1"></i> Isi Jurnal
                                </a>
                            @else
                                <button type="button" class="btn btn-secondary w-100 rounded-3 fw-semibold" disabled>
                                    <i class="bi bi-lock-fill me-1"></i> Isi Jurnal
                                </button>
                                @if($item->lock_reason)
                                    <p class="small text-muted text-center mb-0 mt-2">{{ $item->lock_reason }}</p>
                                @endif
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
