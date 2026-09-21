@extends('layouts.app')

@section('title', 'Jadwal Piket Guru - Kurikulum')

@push('styles')
<style>
    /* Layout vertikal: 1 hari = 1 card full-width, berurutan Senin-Jumat */
    .jadwal-piket-stack {
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }

    /* Highlight card hari ini */
    .ring-active {
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.55);
    }

    /* Chip guru per kategori */
    .guru-chip {
        max-width: 100%;
    }

    /* Style untuk badge kategori (format SK) */
    .shift-badge {
        font-size: 0.7rem;
        padding: 0.3rem 0.65rem;
        border-radius: 999px;
        font-weight: 700;
        letter-spacing: 0.02em;
        white-space: nowrap;
    }
    .shift-pagi { background: #dbeafe; color: #1e40af; }
    .shift-koord-pagi { background: #ddd6fe; color: #5b21b6; }
    .shift-siang { background: #f3f4f6; color: #374151; }
    .shift-koord-siang { background: #e0e7ff; color: #3730a3; }
    .shift-waka { background: #fef3c7; color: #92400e; }
    .shift-lain { background: #f1f5f9; color: #475569; }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">
    {{-- Page Header --}}
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="font-weight: 900; font-size: 1.75rem; letter-spacing: -0.02em;">
                Jadwal Piket Guru
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                Atur dan jadwalkan penugasan piket guru harian (Senin s.d. Jumat) untuk pemantauan KBM & presensi.
            </p>
        </div>

        @if($canManage)
        <div>
            <a href="{{ route('kurikulum.jadwal-piket.create', ['minggu_ke' => $mingguKe]) }}"
               class="btn btn-primary rounded-3 fw-semibold px-3 py-2 d-flex align-items-center gap-2 shadow-sm"
               style="font-size: 0.875rem;">
                <i class="bi bi-person-plus-fill"></i>
                <span>Tambah Petugas Piket</span>
            </a>
        </div>
        @endif
    </div>

    <div class="d-flex gap-2 flex-wrap mb-4" role="tablist" aria-label="Pilih minggu">
        @for($pilihMinggu = 1; $pilihMinggu <= 4; $pilihMinggu++)
            <a href="{{ route('kurikulum.jadwal-piket.index', ['minggu_ke' => $pilihMinggu]) }}"
               class="btn {{ $mingguKe === $pilihMinggu ? 'btn-primary' : 'btn-outline-primary' }} rounded-3">Minggu ke-{{ $pilihMinggu }}</a>
        @endfor
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

    {{-- Daftar Jadwal Hari (Senin - Jumat) — Layout Vertikal 1 Kolom --}}
    <div class="jadwal-piket-stack mb-4">
        @php
            $dayColors = [
                'Senin'  => ['bg' => '#eff6ff', 'border' => '#bfdbfe', 'icon' => 'bi-calendar-event'],
                'Selasa' => ['bg' => '#f0fdf4', 'border' => '#bbf7d0', 'icon' => 'bi-calendar-event'],
                'Rabu'   => ['bg' => '#fefce8', 'border' => '#fef08a', 'icon' => 'bi-calendar-event'],
                'Kamis'  => ['bg' => '#faf5ff', 'border' => '#e9d5ff', 'icon' => 'bi-calendar-event'],
                'Jumat'  => ['bg' => '#ecfeff', 'border' => '#a5f3fc', 'icon' => 'bi-calendar-event'],
            ];

            // Cek hari ini
            $mapHariIni = [
                'Monday'    => 'Senin',
                'Tuesday'   => 'Selasa',
                'Wednesday' => 'Rabu',
                'Thursday'  => 'Kamis',
                'Friday'    => 'Jumat',
                'Sunday'    => 'Minggu',
            ];
            $hariIni = $mapHariIni[\Carbon\Carbon::now()->format('l')] ?? '';
        @endphp

        @foreach($hariList as $hari)
            @php
                $petugasHariIni = $jadwalByHari[$hari] ?? collect();
                $color = $dayColors[$hari] ?? ['bg' => '#f8fafc', 'border' => '#e2e8f0', 'icon' => 'bi-calendar-event'];
                $isToday = ($hari === $hariIni);

                // ===== Pengelompokan data per kategori (format SK + shift dinamis) =====
                $wakaHariIni       = $petugasHariIni->whereNotNull('waka_user_id');
                $koorPagiHariIni   = $petugasHariIni->whereNotNull('koordinator_pagi_user_id');
                $petugasPagiSk     = $petugasHariIni->whereNotNull('petugas_pagi_user_id');
                $koorSiangHariIni  = $petugasHariIni->whereNotNull('koordinator_siang_user_id');
                $petugasSiangSk    = $petugasHariIni->whereNotNull('petugas_siang_user_id');

                // Petugas berbasis shift (user_id + shift_id)
                $shiftRows = $petugasHariIni->whereNotNull('shift_id');
                $petugasPagiShift = $shiftRows->filter(fn ($r) => str_starts_with(strtolower((string) optional($r->shift)->nama), 'pagi'));
                $petugasSiangShift = $shiftRows->filter(fn ($r) => str_starts_with(strtolower((string) optional($r->shift)->nama), 'siang'));

                // Shift di luar Pagi/Siang (jika sekolah punya shift lain)
                $rowsShiftLain = $shiftRows
                    ->reject(fn ($r) => str_starts_with(strtolower((string) optional($r->shift)->nama), 'pagi')
                        || str_starts_with(strtolower((string) optional($r->shift)->nama), 'siang'))
                    ->groupBy(fn ($r) => optional($r->shift)->nama ?? 'Lainnya');

                $sections = collect([
                    [
                        'label'  => 'Waka Piket',
                        'icon'   => 'bi-person-badge-fill',
                        'class'  => 'shift-waka',
                        'rows'   => $wakaHariIni,
                        'person' => fn ($row) => $row->waka,
                    ],
                    [
                        'label'  => 'Koordinator Pagi',
                        'icon'   => 'bi-flag-fill',
                        'class'  => 'shift-koord-pagi',
                        'rows'   => $koorPagiHariIni,
                        'person' => fn ($row) => $row->koordinatorPagi,
                    ],
                    [
                        'label'  => 'Petugas Pagi',
                        'icon'   => 'bi-sun-fill',
                        'class'  => 'shift-pagi',
                        'rows'   => $petugasPagiSk->merge($petugasPagiShift),
                        'person' => fn ($row) => $row->petugas_pagi_user_id ? $row->petugasPagi : $row->user,
                    ],
                    [
                        'label'  => 'Koordinator Siang',
                        'icon'   => 'bi-moon-stars-fill',
                        'class'  => 'shift-koord-siang',
                        'rows'   => $koorSiangHariIni,
                        'person' => fn ($row) => $row->koordinatorSiang,
                    ],
                    [
                        'label'  => 'Petugas Siang',
                        'icon'   => 'bi-moon-fill',
                        'class'  => 'shift-siang',
                        'rows'   => $petugasSiangSk->merge($petugasSiangShift),
                        'person' => fn ($row) => $row->petugas_siang_user_id ? $row->petugasSiang : $row->user,
                    ],
                ]);

                // Shift tambahan di luar Pagi/Siang (jika ada)
                foreach ($rowsShiftLain as $namaShift => $groupRows) {
                    $sections->push([
                        'label'  => 'Petugas '.$namaShift,
                        'icon'   => 'bi-people-fill',
                        'class'  => 'shift-lain',
                        'rows'   => $groupRows,
                        'person' => fn ($row) => $row->user,
                    ]);
                }
            @endphp

            {{-- Card Hari — Full Width --}}
            <div class="card w-100 border-0 shadow-sm rounded-4 overflow-hidden {{ $isToday ? 'ring-active' : '' }}"
                 style="background: #ffffff; border: 1px solid {{ $isToday ? '#3b82f6' : '#e2e8f0' }} !important;">

                {{-- Card Header: Hari di kiri, Aksi Edit/Kelola di kanan --}}
                <div class="card-header border-0 pb-0 pt-4 px-4 bg-transparent d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-3 d-flex align-items-center justify-content-center"
                             style="width: 40px; height: 40px; background: {{ $color['bg'] }}; color: #334155; border: 1px solid {{ $color['border'] }};">
                            <i class="bi {{ $color['icon'] }} fs-5"></i>
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <h5 class="fw-bold mb-0 text-dark">{{ $hari }}</h5>
                                @if($isToday)
                                    <span class="badge bg-primary rounded-pill px-2 py-1" style="font-size: 0.68rem;">
                                        <i class="bi bi-clock-history me-1"></i>HARI INI
                                    </span>
                                @endif
                            </div>
                            <span class="text-muted" style="font-size: 0.78rem;">
                                {{ $petugasHariIni->count() }} Guru Bertugas &bull; Minggu ke-{{ $mingguKe }}
                            </span>
                        </div>
                    </div>

                    @if($canManage)
                    <a href="{{ route('kurikulum.jadwal-piket.create', ['hari' => $hari, 'minggu_ke' => $mingguKe]) }}"
                       class="btn btn-sm btn-light border rounded-3 d-inline-flex align-items-center gap-1 px-3 shadow-none"
                       title="Kelola Guru Piket Hari {{ $hari }}">
                        <i class="bi bi-pencil-fill text-primary" style="font-size: 0.8rem;"></i>
                        <span class="small fw-semibold">Kelola</span>
                    </a>
                    @endif
                </div>

                {{-- Card Body: Kolom Informasi Petugas --}}
                <div class="card-body px-4 py-3">
                    @if($petugasHariIni->isEmpty())
                        {{-- Empty State Hari (belum ada petugas sama sekali) --}}
                        <div class="d-flex align-items-center justify-content-center text-center py-4 px-3 rounded-3"
                             style="border: 1.5px dashed #e2e8f0; background: #fafbfc;">
                            <div class="d-flex align-items-center gap-3 flex-wrap justify-content-center">
                                <div class="rounded-circle bg-white border d-flex align-items-center justify-content-center flex-shrink-0"
                                     style="width: 48px; height: 48px;">
                                    <i class="bi bi-calendar2-x text-secondary" style="font-size: 1.4rem;"></i>
                                </div>
                                <div class="text-center text-sm-start">
                                    <div class="fw-semibold text-secondary" style="font-size: 0.9rem;">
                                        Belum ada penugasan piket hari {{ $hari }}
                                    </div>
                                    @if($canManage)
                                    <a href="{{ route('kurikulum.jadwal-piket.create', ['hari' => $hari, 'minggu_ke' => $mingguKe]) }}"
                                       class="btn btn-sm btn-primary rounded-3 mt-1">
                                        <i class="bi bi-plus-lg me-1"></i>Tambah Petugas Piket
                                    </a>
                                    @else
                                    <div class="text-muted small">Belum ada guru piket yang ditugaskan.</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @else
                        {{-- Kolom info: Waka / Koordinator / Petugas Pagi & Siang --}}
                        <div class="d-flex flex-column gap-3">
                            @foreach($sections as $sec)
                                @php
                                    $secRows = $sec['rows']->values();
                                    $person = $sec['person'];
                                @endphp
                                <div class="row g-2 align-items-start">
                                    <div class="col-12 col-md-3 col-xl-2">
                                        <span class="shift-badge {{ $sec['class'] }} d-inline-flex align-items-center gap-1">
                                            <i class="bi {{ $sec['icon'] }}"></i>{{ $sec['label'] }}
                                        </span>
                                    </div>
                                    <div class="col-12 col-md-9 col-xl-10 d-flex flex-wrap align-items-start gap-2">
                                        @forelse($secRows as $row)
                                            @php($u = $person($row))
                                            <div class="guru-chip d-inline-flex align-items-center gap-2 bg-light-subtle border rounded-3 py-1 ps-1 pe-2">
                                                <span class="rounded-circle bg-white border d-flex align-items-center justify-content-center flex-shrink-0"
                                                      style="width: 34px; height: 34px; font-size: 0.72rem; font-weight: 700; color: #334155;">
                                                    {{ $u ? strtoupper(substr($u->nama, 0, 2)) : '?' }}
                                                </span>
                                                <span class="overflow-hidden">
                                                    <span class="d-block fw-semibold text-dark text-truncate" style="font-size: 0.8rem; max-width: 240px;"
                                                          title="{{ $u->nama ?? '-' }}">
                                                        {{ $u->nama ?? 'Data guru tidak ditemukan' }}
                                                    </span>
                                                    @if($u)
                                                        <span class="d-block text-muted text-truncate" style="font-size: 0.7rem; max-width: 240px;">
                                                            NIP: {{ $u->nip ?? '-' }}
                                                        </span>
                                                    @endif
                                                </span>
                                                @if($canManage)
                                                <form action="{{ route('kurikulum.jadwal-piket.destroy', $row->id) }}" method="POST"
                                                      onsubmit="return confirm('Hapus penugasan {{ $u->nama ?? 'guru ini' }} pada hari {{ $hari }}?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            class="btn btn-sm btn-outline-danger border-0 rounded-circle p-0 d-flex align-items-center justify-content-center"
                                                            style="width: 24px; height: 24px;" title="Hapus Penugasan">
                                                        <i class="bi bi-x-lg" style="font-size: 0.7rem;"></i>
                                                    </button>
                                                </form>
                                                @endif
                                            </div>
                                        @empty
                                            <span class="text-muted small" style="padding: 0.4rem 0;">Belum diisi</span>
                                        @endforelse
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

</div>
@endsection