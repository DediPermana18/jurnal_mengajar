@extends('layouts.app')

@section('title', 'Status Kehadiran Guru - Guru Piket')

@section('content')
<div class="container-fluid px-0">

    @php
        // Kumpulan ID "user aktif" (login asli + target simulasi/impersonasi).
        // Status kehadiran milik ID mana pun dari daftar ini TIDAK boleh diubah
        // (anti self-status-override).
        $activeUserIdList = array_values(array_unique(array_filter([
            (int) auth()->id(),
            (int) session('impersonate_target_id'),
            (int) session('simulated_user_id'),
        ], fn ($v) => $v > 0)));
        $isOwnRow = fn ($guru) => in_array((int) $guru->id, $activeUserIdList, true);
    @endphp

    {{-- Page Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="font-weight: 900; font-size: 1.75rem; letter-spacing: -0.02em;">
                Status Kehadiran Guru
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                Pantau ketersediaan guru hari ini. Status ter-sinkron otomatis dari izin yang disetujui
                &mdash; guru tanpa catatan dianggap <strong>Hadir</strong>.
            </p>
        </div>
        <span class="text-muted small"><i class="bi bi-calendar3 me-1"></i>{{ now()->translatedFormat('l, d F Y') }}</span>
    </div>

    {{-- Alert --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4 d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-check-circle-fill text-success fs-5"></i><div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4 d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-exclamation-triangle-fill text-danger fs-5"></i><div>{{ session('error') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4" role="alert">
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Stat Cards --}}
    @php $SK = \App\Models\StatusKehadiranGuru::class; @endphp
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body d-flex align-items-center gap-3 p-3">
                    <div class="rounded-3 p-3 bg-success-subtle text-success"><i class="bi bi-check-circle-fill fs-4"></i></div>
                    <div>
                        <div class="text-muted small">Hadir</div>
                        <div class="fw-bold fs-4 text-dark">{{ $stats[$SK::STATUS_HADIR] }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body d-flex align-items-center gap-3 p-3">
                    <div class="rounded-3 p-3 bg-warning-subtle text-warning-emphasis"><i class="bi bi-calendar-x fs-4"></i></div>
                    <div>
                        <div class="text-muted small">Izin</div>
                        <div class="fw-bold fs-4 text-dark">{{ $stats[$SK::STATUS_IZIN] }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body d-flex align-items-center gap-3 p-3">
                    <div class="rounded-3 p-3 bg-warning-subtle text-warning-emphasis"><i class="bi bi-briefcase-fill fs-4"></i></div>
                    <div>
                        <div class="text-muted small">Dinas Luar</div>
                        <div class="fw-bold fs-4 text-dark">{{ $stats[$SK::STATUS_DINAS_LUAR] }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body d-flex align-items-center gap-3 p-3">
                    <div class="rounded-3 p-3 bg-danger-subtle text-danger"><i class="bi bi-thermometer-half fs-4"></i></div>
                    <div>
                        <div class="text-muted small">Sakit</div>
                        <div class="fw-bold fs-4 text-dark">{{ $stats[$SK::STATUS_SAKIT] }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter --}}
    <form method="GET" action="{{ route('piket.status-guru') }}" class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label text-muted small text-uppercase fw-bold mb-1">Tanggal</label>
                    <input type="date" name="tanggal" class="form-control rounded-3" value="{{ $tanggal }}" max="{{ now()->toDateString() }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label text-muted small text-uppercase fw-bold mb-1">Cari Guru</label>
                    <input type="text" name="q" class="form-control rounded-3" placeholder="Nama / NIP guru..." value="{{ $cari }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted small text-uppercase fw-bold mb-1">Status</label>
                    <select name="status" class="form-select rounded-3">
                        <option value="">Semua Status</option>
                        @foreach(\App\Models\StatusKehadiranGuru::STATUSES as $s)
                            <option value="{{ $s }}" @selected($filterStatus === $s)>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary rounded-3 flex-grow-1"><i class="bi bi-funnel me-1"></i>Filter</button>
                    <a href="{{ route('piket.status-guru') }}" class="btn btn-light border rounded-3" title="Reset">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>
            </div>
        </div>
    </form>

    {{-- Tabel --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-0">
            <div class="d-flex justify-content-between align-items-center px-4 py-3 flex-wrap gap-2">
                <h5 class="fw-bold text-dark mb-0">Daftar Kehadiran Guru {{ \Illuminate\Support\Carbon::parse($tanggal)->translatedFormat('d F Y') }}</h5>
                <span class="text-muted small">Menampilkan {{ number_format($gurus->total()) }} guru</span>
            </div>
            <div class="table-responsive">
                <table class="table table-custom align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="whitespace-nowrap ps-4">#</th>
                            <th>NAMA GURU</th>
                            <th class="whitespace-nowrap">STATUS KEHADIRAN</th>
                            <th>KETERANGAN</th>
                            <th class="whitespace-nowrap">TERAKHIR DIUBAH</th>
                            <th class="text-end pe-4 whitespace-nowrap">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($gurus as $guru)
                            <tr>
                                <td class="text-muted ps-4">{{ $gurus->firstItem() + $loop->index }}</td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $guru->nama }}</div>
                                    <div class="text-muted small">NIP: {{ $guru->nip ?: '-' }}</div>
                                </td>
                                <td>
                                    <span class="badge rounded-pill px-3 py-2 {{ $guru->kehadiran->status_badge }}">
                                        <i class="bi {{ $guru->kehadiran->status_icon }} me-1"></i>{{ $guru->kehadiran->status }}
                                    </span>
                                </td>
                                <td>
                                    <div class="text-wrap small">{{ $guru->kehadiran->keterangan ?: ($guru->kehadiran->exists ? '—' : 'Tanpa catatan (default Hadir)') }}</div>
                                </td>
                                <td class="small text-muted text-nowrap">
                                    @if($guru->kehadiran->exists)
                                        {{ $guru->kehadiran->updated_at?->translatedFormat('d/m/Y H:i') }}
                                        <div class="text-muted">{{ $guru->kehadiran->updatedBy?->nama ?: 'Sistem' }}</div>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    @if($isOwnRow($guru))
                                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-3"
                                                disabled
                                                title="Tidak dapat mengubah status diri sendiri — ajukan melalui Pengajuan Izin.">
                                            <i class="bi bi-person-lock me-1"></i>Ubah Status
                                        </button>
                                    @else
                                        <button type="button" class="btn btn-sm btn-outline-primary rounded-3"
                                                data-bs-toggle="modal" data-bs-target="#modalUbahStatus"
                                                data-id="{{ $guru->id }}"
                                                data-nama="{{ $guru->nama }}"
                                                data-status="{{ $guru->kehadiran->status }}"
                                                data-keterangan="{{ $guru->kehadiran->keterangan }}"
                                                title="Ubah status kehadiran {{ $guru->nama }}">
                                            <i class="bi bi-pencil-square me-1"></i>Ubah Status
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>Tidak ada guru yang cocok dengan filter ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($gurus->hasPages())
                <div class="d-flex justify-content-between align-items-center px-4 py-3 border-top flex-wrap gap-2">
                    <div class="text-muted small">Menampilkan <strong>{{ $gurus->firstItem() ?? 0 }}</strong>-<strong>{{ $gurus->lastItem() ?? 0 }}</strong> dari <strong>{{ $gurus->total() }}</strong> guru</div>
                    {{ $gurus->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Modal Manual Override --}}
<div class="modal fade" id="modalUbahStatus" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form action="{{ route('piket.status-guru.update') }}" method="POST">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-pencil-square text-primary me-1"></i>Ubah Status Kehadiran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body px-4 py-3">
                    <input type="hidden" name="user_id" id="override_user_id" value="">
                    <input type="hidden" name="tanggal" value="{{ $tanggal }}">
                    <div class="mb-3">
                        <span class="fw-bold text-dark" id="override_nama">-</span>
                        <span class="text-muted small d-block">Perubahan langsung (manual override) oleh Guru Piket.</span>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary text-uppercase small mb-2">Status Kehadiran <span class="text-danger">*</span></label>
                        <select name="status" id="override_status" class="form-select rounded-3" required>
                            @foreach(\App\Models\StatusKehadiranGuru::STATUSES as $s)
                                <option value="{{ $s }}">{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-bold text-secondary text-uppercase small mb-2">Keterangan</label>
                        <textarea name="keterangan" id="override_keterangan" class="form-control rounded-3" rows="3" maxlength="1000" placeholder="Alasan / catatan perubahan status..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-3 fw-semibold"><i class="bi bi-check-lg me-1"></i>Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('[data-bs-target="#modalUbahStatus"]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-id');
            var nama = btn.getAttribute('data-nama');
            var status = btn.getAttribute('data-status');
            var keterangan = btn.getAttribute('data-keterangan');

            document.getElementById('override_user_id').value = id || '';
            document.getElementById('override_nama').textContent = nama || '-';
            document.getElementById('override_status').value = status || 'Hadir';
            document.getElementById('override_keterangan').value = keterangan || '';
        });
    });
</script>
@endpush