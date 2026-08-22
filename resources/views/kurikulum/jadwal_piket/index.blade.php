@extends('layouts.app')

@section('title', 'Jadwal Piket Guru - Kurikulum')

@section('content')
<div class="container-fluid px-0">

    {{-- Page Header --}}
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="font-weight: 900; font-size: 1.75rem; letter-spacing: -0.02em;">
                Jadwal Piket Guru
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                Atur dan jadwalkan penugasan piket guru harian (Senin s.d. Sabtu) untuk pemantauan KBM & presensi.
            </p>
        </div>
        <div>
            <button type="button" class="btn btn-primary rounded-3 fw-semibold px-3 py-2 d-flex align-items-center gap-2 shadow-sm"
                    data-bs-toggle="modal" data-bs-target="#modalTambahPiket" style="font-size: 0.875rem;">
                <i class="bi bi-person-plus-fill"></i>
                <span>Tambah Petugas Piket</span>
            </button>
        </div>
    </div>

    {{-- Alert Messages --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4 d-flex align-items-center gap-2" role="alert"
             style="background: #ecfdf5; color: #065f46; font-size: 0.9rem;">
            <i class="bi bi-check-circle-fill text-success fs-5"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(isset($errors) && $errors->any())
        <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4 d-flex align-items-center gap-2" role="alert"
             style="background: #fef2f2; color: #991b1b; font-size: 0.9rem;">
            <i class="bi bi-exclamation-triangle-fill text-danger fs-5"></i>
            <div>
                <ul class="mb-0 ps-3">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Grid Jadwal Hari (Senin - Sabtu) --}}
    <div class="row g-4 mb-4">
        @php
            $dayColors = [
                'Senin'  => ['bg' => '#eff6ff', 'badge' => 'primary',   'border' => '#bfdbfe', 'icon' => 'bi-calendar-event'],
                'Selasa' => ['bg' => '#f0fdf4', 'badge' => 'success',   'border' => '#bbf7d0', 'icon' => 'bi-calendar-event'],
                'Rabu'   => ['bg' => '#fefce8', 'badge' => 'warning',   'border' => '#fef08a', 'icon' => 'bi-calendar-event'],
                'Kamis'  => ['bg' => '#faf5ff', 'badge' => 'secondary', 'border' => '#e9d5ff', 'icon' => 'bi-calendar-event'],
                'Jumat'  => ['bg' => '#ecfeff', 'badge' => 'info',      'border' => '#a5f3fc', 'icon' => 'bi-calendar-event'],
                'Sabtu'  => ['bg' => '#fff1f2', 'badge' => 'danger',    'border' => '#fecdd3', 'icon' => 'bi-calendar-event'],
            ];

            // Cek hari ini
            $mapHariIni = [
                'Monday'    => 'Senin',
                'Tuesday'   => 'Selasa',
                'Wednesday' => 'Rabu',
                'Thursday'  => 'Kamis',
                'Friday'    => 'Jumat',
                'Saturday'  => 'Sabtu',
                'Sunday'    => 'Minggu',
            ];
            $hariIni = $mapHariIni[\Carbon\Carbon::now()->format('l')] ?? '';
        @endphp

        @foreach($hariList as $hari)
            @php
                $petugasHariIni = $jadwalByHari[$hari] ?? collect();
                $color = $dayColors[$hari] ?? ['bg' => '#f8fafc', 'badge' => 'secondary', 'border' => '#e2e8f0', 'icon' => 'bi-calendar-event'];
                $isToday = ($hari === $hariIni);
            @endphp
            <div class="col-12 col-md-6 col-xl-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden position-relative {{ $isToday ? 'ring-active' : '' }}"
                     style="background: #ffffff; border: 1px solid {{ $isToday ? '#3b82f6' : '#e2e8f0' }} !important;">
                    
                    @if($isToday)
                        <div class="position-absolute top-0 end-0 m-3">
                            <span class="badge bg-primary rounded-pill px-2 py-1 shadow-sm" style="font-size: 0.7rem;">
                                <i class="bi bi-clock-history me-1"></i>HARI INI
                            </span>
                        </div>
                    @endif

                    {{-- Card Header --}}
                    <div class="card-header border-0 pb-0 pt-4 px-4 bg-transparent d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <div class="rounded-3 d-flex align-items-center justify-content-center"
                                 style="width: 38px; height: 38px; background: {{ $color['bg'] }}; color: #334155; border: 1px solid {{ $color['border'] }};">
                                <i class="bi {{ $color['icon'] }} fs-5"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-0 text-dark">{{ $hari }}</h5>
                                <span class="text-muted" style="font-size: 0.78rem;">
                                    {{ $petugasHariIni->count() }} Guru Bertugas
                                </span>
                            </div>
                        </div>

                        {{-- Quick Add Button for this day --}}
                        <button type="button" class="btn btn-sm btn-light rounded-circle border shadow-none text-secondary"
                                style="width: 32px; height: 32px; padding: 0;"
                                title="Tambah Guru ke hari {{ $hari }}"
                                onclick="openAddModalWithDay('{{ $hari }}')">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>

                    {{-- Card Body: Daftar Guru Piket --}}
                    <div class="card-body px-4 py-3">
                        @if($petugasHariIni->isEmpty())
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-person-x fs-2 d-block mb-1 text-secondary opacity-50"></i>
                                <span class="small">Belum ada guru piket</span>
                            </div>
                        @else
                            <div class="d-flex flex-column gap-2">
                                @foreach($petugasHariIni as $item)
                                    @php
                                        $user = $item->user;
                                    @endphp
                                    <div class="d-flex align-items-center justify-content-between p-2 rounded-3 border bg-light-subtle">
                                        <div class="d-flex align-items-center gap-2 overflow-hidden">
                                            <div class="rounded-circle bg-primary-subtle text-primary fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                                                 style="width: 34px; height: 34px; font-size: 0.8rem;">
                                                {{ $user ? strtoupper(substr($user->nama, 0, 2)) : 'GP' }}
                                            </div>
                                            <div class="overflow-hidden">
                                                <div class="fw-semibold text-dark text-truncate" style="font-size: 0.875rem;" title="{{ $user->nama ?? '-' }}">
                                                    {{ $user->nama ?? 'Guru Tidak Ditemukan' }}
                                                </div>
                                                <div class="text-muted small text-truncate" style="font-size: 0.75rem;">
                                                    NIP: {{ $user->nip ?? '-' }} &bull; {{ $user ? $user->role_label : '-' }}
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Delete Action --}}
                                        <form action="{{ route('kurikulum.jadwal-piket.destroy', $item->id) }}" method="POST"
                                              onsubmit="return confirm('Hapus penugasan piket {{ $user->nama ?? 'Guru ini' }} pada hari {{ $hari }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm rounded-circle border-0 p-1 ms-2"
                                                    title="Hapus Penugasan">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

</div>

{{-- MODAL TAMBAH PETUGAS PIKET --}}
<div class="modal fade" id="modalTambahPiket" tabindex="-1" aria-labelledby="modalTambahPiketLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-dark" id="modalTambahPiketLabel">
                    <i class="bi bi-person-plus text-primary me-2"></i>Tambah Petugas Piket
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('kurikulum.jadwal-piket.store') }}" method="POST">
                @csrf
                <div class="modal-body py-3 px-4">
                    {{-- Pilihan Hari --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">HARI PIKET <span class="text-danger">*</span></label>
                        <select name="hari" id="modal_input_hari" class="form-select rounded-3 py-2" required>
                            <option value="">-- Pilih Hari --</option>
                            @foreach($hariList as $hariOption)
                                <option value="{{ $hariOption }}" {{ old('hari') === $hariOption ? 'selected' : '' }}>
                                    {{ $hariOption }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Pilihan Guru --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">PILIH GURU PIKET <span class="text-danger">*</span></label>
                        <select name="guru_ids[]" id="modal_input_user" class="form-select rounded-3 py-2" multiple size="6" required>
                            @foreach($guruList as $guru)
                                <option value="{{ $guru->id }}">
                                    {{ $guru->nama }} {{ $guru->nip ? '(' . $guru->nip . ')' : '' }} - [{{ $guru->role_label }}]
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text text-muted small mt-1">
                            <i class="bi bi-info-circle me-1"></i>Tahan tombol <strong>Ctrl</strong> (Windows) atau <strong>Cmd</strong> (Mac) untuk memilih lebih dari 1 guru. Guru yang dipilih akan memiliki hak akses piket pada hari tersebut.
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 pb-4 px-4">
                    <button type="button" class="btn btn-light rounded-3 px-3 fw-medium" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4 fw-semibold">
                        <i class="bi bi-save me-1"></i>Simpan Penugasan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    const selectedByHari = @json($selectedByHari);

    function syncGuruSelection(hari) {
        const selectUser = document.getElementById('modal_input_user');
        if (!selectUser) return;

        const assignedIds = (selectedByHari && selectedByHari[hari]) ? selectedByHari[hari].map(Number) : [];
        Array.from(selectUser.options).forEach(opt => {
            opt.selected = assignedIds.includes(Number(opt.value));
        });
    }

    document.getElementById('modal_input_hari')?.addEventListener('change', function() {
        syncGuruSelection(this.value);
    });

    function openAddModalWithDay(hari) {
        const selectHari = document.getElementById('modal_input_hari');
        if (selectHari) {
            selectHari.value = hari;
            syncGuruSelection(hari);
        }
        const modal = new bootstrap.Modal(document.getElementById('modalTambahPiket'));
        modal.show();
    }
</script>
@endpush
@endsection
