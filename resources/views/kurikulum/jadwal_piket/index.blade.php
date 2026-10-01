@extends('layouts.app')

@section('title', 'Jadwal Piket Guru - Kurikulum')

@push('styles')
<style>
    /* Sub-Tab Hari Nav Pills Styling */
    .piket-day-pill {
        color: #475569;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        font-size: 0.875rem;
        transition: all 0.2s ease-in-out;
    }
    .piket-day-pill:hover {
        color: #0f172a;
        background-color: #f1f5f9;
        border-color: #cbd5e1;
    }
    .piket-day-pill.active {
        color: #ffffff !important;
        background-color: #2563eb !important;
        border-color: #2563eb !important;
        box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.25), 0 2px 4px -2px rgba(37, 99, 235, 0.15);
    }
    .piket-day-pill .badge-count {
        background-color: #e2e8f0;
        color: #475569;
    }
    .piket-day-pill.active .badge-count {
        background-color: rgba(255, 255, 255, 0.25);
        color: #ffffff;
    }

    /* Badge HARI INI: Kontras tinggi saat aktif (amber/slate) dan subtle saat tidak aktif (blue-100/700) */
    .piket-day-pill .badge-hari-ini {
        background-color: #dbeafe !important;
        color: #1d4ed8 !important;
        font-weight: 700;
        font-size: 0.65rem;
        padding: 0.2rem 0.45rem;
        border-radius: 0.375rem;
        letter-spacing: 0.02em;
        white-space: nowrap;
        line-height: 1;
    }
    .piket-day-pill.active .badge-hari-ini {
        background-color: #fbbf24 !important;
        color: #0f172a !important;
        font-weight: 800;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
    }

    /* Style dasar badge kategori / grup shift */
    .shift-badge {
        font-size: 0.725rem;
        padding: 0.35rem 0.75rem;
        border-radius: 999px;
        font-weight: 600;
        letter-spacing: 0.01em;
        white-space: nowrap;
    }
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
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <button type="button"
                    data-bs-toggle="modal"
                    data-bs-target="#shiftModal"
                    class="border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 font-medium px-4 py-2 rounded-lg shadow-sm transition flex items-center gap-2 whitespace-nowrap"
                    style="font-size: 0.875rem;"
                    title="Atur shift, jam bertugas, dan kuota petugas piket">
                <i class="bi bi-gear"></i>
                <span>Pengaturan Shift & Kuota</span>
            </button>
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

    @if(session('info'))
        <div class="alert alert-info alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4 d-flex align-items-center gap-2" role="alert"
             style="background: #eff6ff; color: #1e40af; font-size: 0.9rem;">
            <i class="bi bi-info-circle-fill text-info fs-5"></i>
            <div>{{ session('info') }}</div>
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
        ];
        $hariIni = $mapHariIni[\Carbon\Carbon::now()->format('l')] ?? '';
        $hariArray = is_array($hariList) ? $hariList : $hariList->toArray();
        $defaultActiveHari = in_array($hariIni, $hariArray) ? $hariIni : ($hariArray[0] ?? 'Senin');
    @endphp

    {{-- Sub-Tab Nav Pills Hari (Senin - Jumat) --}}
    <div class="card border-0 shadow-sm rounded-4 p-2 mb-4 bg-white">
        <ul class="nav nav-pills nav-fill flex-wrap gap-2" id="piketHariTab" role="tablist">
            @foreach($hariList as $hari)
                @php
                    $isActive = ($hari === $defaultActiveHari);
                    $isToday = ($hari === $hariIni);
                    $countPetugas = ($jadwalByHari[$hari] ?? collect())->count();
                    $slugHari = strtolower($hari);
                @endphp
                <li class="nav-item flex-grow-1" role="presentation">
                    <button class="nav-link w-100 rounded-3 py-2.5 px-3 font-semibold d-flex align-items-center justify-content-center gap-1.5 piket-day-pill {{ $isActive ? 'active' : '' }}"
                            id="tab-{{ $slugHari }}"
                            data-bs-toggle="pill"
                            data-bs-target="#content-{{ $slugHari }}"
                            type="button"
                            role="tab"
                            aria-controls="content-{{ $slugHari }}"
                            aria-selected="{{ $isActive ? 'true' : 'false' }}">
                        <span>{{ $hari }}</span>
                        @if($isToday)
                            <span class="badge badge-hari-ini">
                                HARI INI
                            </span>
                        @endif
                        <span class="badge badge-count rounded-pill" style="font-size: 0.72rem;">
                            {{ $countPetugas }}
                        </span>
                    </button>
                </li>
            @endforeach
        </ul>
    </div>

    {{-- Tab Content Hari --}}
    <div class="tab-content jadwal-piket-stack mb-4" id="piketHariTabContent">
        @foreach($hariList as $hari)
            @php
                $isActive = ($hari === $defaultActiveHari);
                $slugHari = strtolower($hari);
                $petugasHariIni = $jadwalByHari[$hari] ?? collect();
                $color = $dayColors[$hari] ?? ['bg' => '#f8fafc', 'border' => '#e2e8f0', 'icon' => 'bi-calendar-event'];
                $isToday = ($hari === $hariIni);

                // ===== Pengelompokan data per kategori (format SK + shift dinamis) =====
                $wakaHariIni       = $petugasHariIni->whereNotNull('waka_user_id');
                $koorPagiHariIni   = $petugasHariIni->whereNotNull('koordinator_pagi_user_id');
                $koorSiangHariIni  = $petugasHariIni->whereNotNull('koordinator_siang_user_id');

                // Kumpulkan ID koordinator pagi & siang agar bisa dikecualikan
                // dari daftar petugas — mencegah nama koordinator muncul dua kali.
                $koorPagiIds  = $koorPagiHariIni->pluck('koordinator_pagi_user_id')->filter()->unique()->values()->all();
                $koorSiangIds = $koorSiangHariIni->pluck('koordinator_siang_user_id')->filter()->unique()->values()->all();

                // Petugas SK (format kolom dedikasi), dikecualikan koordinator.
                $petugasPagiSk  = $petugasHariIni
                    ->whereNotNull('petugas_pagi_user_id')
                    ->filter(fn ($r) => ! in_array($r->petugas_pagi_user_id, $koorPagiIds, true));
                $petugasSiangSk = $petugasHariIni
                    ->whereNotNull('petugas_siang_user_id')
                    ->filter(fn ($r) => ! in_array($r->petugas_siang_user_id, $koorSiangIds, true));

                // Petugas berbasis shift (user_id + shift_id),
                // dikecualikan juga bila user adalah koordinator pagi/siang.
                $allKoorIds = array_unique(array_merge($koorPagiIds, $koorSiangIds));
                $shiftRows = $petugasHariIni->whereNotNull('shift_id');
                $petugasPagiShift  = $shiftRows
                    ->filter(fn ($r) => str_starts_with(strtolower((string) optional($r->shift)->nama), 'pagi')
                        && ! in_array($r->user_id, $allKoorIds, true));
                $petugasSiangShift = $shiftRows
                    ->filter(fn ($r) => str_starts_with(strtolower((string) optional($r->shift)->nama), 'siang')
                        && ! in_array($r->user_id, $allKoorIds, true));

                // Shift di luar Pagi/Siang (jika sekolah punya shift lain)
                $rowsShiftLain = $shiftRows
                    ->reject(fn ($r) => str_starts_with(strtolower((string) optional($r->shift)->nama), 'pagi')
                        || str_starts_with(strtolower((string) optional($r->shift)->nama), 'siang'))
                    ->groupBy(fn ($r) => optional($r->shift)->nama ?? 'Lainnya');

                // ===== Pengelompokan visual: Grup Pagi vs Grup Siang (Soft Badges & Clean Spacing) =====
                $groups = collect([
                    [
                        'title'    => 'SHIFT PAGI',
                        'icon'     => 'bi-sun-fill',
                        'header'   => 'bg-amber-100/80 text-amber-800 border border-amber-200/80',
                        'wrapper'  => 'bg-amber-50/20 border border-amber-200/60',
                        'sections' => collect([
                            [
                                'label'  => 'Koordinator Pagi',
                                'icon'   => 'bi-flag-fill',
                                'badge'  => 'bg-sky-50 text-sky-700 border border-sky-200/80',
                                'rows'   => $koorPagiHariIni,
                                'person' => fn ($row) => $row->koordinatorPagi,
                            ],
                            [
                                'label'  => 'Petugas Pagi',
                                'icon'   => 'bi-sun-fill',
                                'badge'  => 'bg-blue-50 text-blue-700 border border-blue-200/80',
                                'rows'   => $petugasPagiSk->merge($petugasPagiShift),
                                'person' => fn ($row) => $row->petugas_pagi_user_id ? $row->petugasPagi : $row->user,
                            ],
                        ]),
                    ],
                    [
                        'title'    => 'SHIFT SIANG',
                        'icon'     => 'bi-moon-stars-fill',
                        'header'   => 'bg-indigo-100/80 text-indigo-800 border border-indigo-200/80',
                        'wrapper'  => 'bg-indigo-50/20 border border-indigo-200/60',
                        'sections' => collect([
                            [
                                'label'  => 'Koordinator Siang',
                                'icon'   => 'bi-moon-stars-fill',
                                'badge'  => 'bg-indigo-50 text-indigo-700 border border-indigo-200/80',
                                'rows'   => $koorSiangHariIni,
                                'person' => fn ($row) => $row->koordinatorSiang,
                            ],
                            [
                                'label'  => 'Petugas Siang',
                                'icon'   => 'bi-moon-fill',
                                'badge'  => 'bg-slate-100 text-slate-700 border border-slate-200/80',
                                'rows'   => $petugasSiangSk->merge($petugasSiangShift),
                                'person' => fn ($row) => $row->petugas_siang_user_id ? $row->petugasSiang : $row->user,
                            ],
                        ]),
                    ],
                ]);

                // Shift tambahan di luar Pagi/Siang (jika ada) — grup netral terpisah
                if ($rowsShiftLain->isNotEmpty()) {
                    $groups->push([
                        'title'    => 'SHIFT LAINNYA',
                        'icon'     => 'bi-people-fill',
                        'header'   => 'bg-gray-100 text-gray-700 border border-gray-200',
                        'wrapper'  => 'bg-gray-50/40 border border-gray-200',
                        'sections' => collect($rowsShiftLain->map(fn ($groupRows, $namaShift) => [
                            'label'  => 'Petugas '.$namaShift,
                            'icon'   => 'bi-people-fill',
                            'badge'  => 'bg-gray-100 text-gray-700 border border-gray-200',
                            'rows'   => $groupRows,
                            'person' => fn ($row) => $row->user,
                        ])->values()),
                    ]);
                }
            @endphp

            <div class="tab-pane fade {{ $isActive ? 'show active' : '' }}"
                 id="content-{{ $slugHari }}"
                 role="tabpanel"
                 aria-labelledby="tab-{{ $slugHari }}">

                {{-- Card Hari --}}
                <div class="card w-100 border-0 shadow-sm rounded-4 overflow-hidden"
                     style="background: #ffffff; border: 1px solid #e2e8f0 !important;">

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
                        <div class="d-flex align-items-center gap-2">
                            <a href="{{ route('kurikulum.jadwal-piket.create', ['hari' => $hari, 'minggu_ke' => $mingguKe]) }}"
                               class="btn btn-sm btn-light border rounded-3 d-inline-flex align-items-center gap-1 px-3 shadow-none"
                               title="Kelola Guru Piket Hari {{ $hari }}">
                                <i class="bi bi-pencil-fill text-primary" style="font-size: 0.8rem;"></i>
                                <span class="small fw-semibold">Kelola</span>
                            </a>

                            {{-- Kosongkan seluruh penugasan piket hari ini (Minggu ke-{{ $mingguKe }}) --}}
                            @if($petugasHariIni->isNotEmpty())
                            <form action="{{ route('kurikulum.jadwal-piket.clear-day', ['hari' => $hari, 'minggu_ke' => $mingguKe]) }}"
                                  method="POST"
                                  class="d-inline"
                                  data-clear-day-form
                                  data-hari="{{ $hari }}"
                                  data-minggu="{{ $mingguKe }}"
                                  data-jumlah="{{ $petugasHariIni->count() }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="btn btn-sm btn-outline-danger border rounded-3 d-inline-flex align-items-center gap-1 px-3 shadow-none"
                                        title="Kosongkan seluruh jadwal piket hari {{ $hari }}">
                                    <i class="bi bi-trash3 text-danger" style="font-size: 0.8rem;"></i>
                                    <span class="small fw-semibold text-danger">Kosongkan</span>
                                </button>
                            </form>
                            @endif
                        </div>
                        @endif
                    </div>

                    {{-- Card Body: Kolom Informasi Petugas --}}
                    <div class="card-body px-4 py-4">
                        @if($petugasHariIni->isEmpty())
                            {{-- Empty State Hari (belum ada petugas sama sekali) --}}
                            <div class="d-flex align-items-center justify-content-center text-center py-5 px-3 rounded-4"
                                 style="border: 1.5px dashed #e2e8f0; background: #fafbfc;">
                                <div class="d-flex flex-column align-items-center gap-2 justify-content-center">
                                    <div class="rounded-circle bg-white border d-flex align-items-center justify-content-center flex-shrink-0 mb-1"
                                         style="width: 52px; height: 52px;">
                                        <i class="bi bi-calendar2-x text-secondary" style="font-size: 1.5rem;"></i>
                                    </div>
                                    <div class="text-center">
                                        <div class="fw-bold text-dark" style="font-size: 0.95rem;">
                                            Belum ada penugasan piket hari {{ $hari }}
                                        </div>
                                        @if($canManage)
                                        <p class="text-muted small mb-3">Tambahkan waka, koordinator, atau petugas piket untuk hari {{ $hari }}.</p>
                                        <a href="{{ route('kurikulum.jadwal-piket.create', ['hari' => $hari, 'minggu_ke' => $mingguKe]) }}"
                                           class="btn btn-sm btn-primary rounded-3 px-3 py-2">
                                            <i class="bi bi-plus-lg me-1"></i>Tambah Petugas Piket
                                        </a>
                                        @else
                                        <div class="text-muted small">Belum ada guru piket yang ditugaskan.</div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @else
                            {{-- Kolom info: Waka Piket (global/harian) + Grup Pagi & Siang --}}
                            <div class="d-flex flex-column gap-4">
                                {{-- Waka Piket: penanggung jawab harian (global) --}}
                                @php
                                    $wakaRows    = $wakaHariIni->values();
                                    $wakaPerson  = fn ($row) => $row->waka;
                                @endphp
                                <div class="rounded-4 border p-3.5 bg-amber-50/40 border-amber-200/80">
                                    <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
                                        <span class="shift-badge bg-amber-50 text-amber-700 border border-amber-200/80 d-inline-flex align-items-center gap-1.5">
                                            <i class="bi bi-person-badge-fill"></i>Waka Piket
                                        </span>
                                        <span class="text-muted" style="font-size: 0.75rem;">
                                            Penanggung jawab harian piket
                                        </span>
                                        @if($wakaRows->isNotEmpty())
                                            <span class="text-muted ms-auto" style="font-size: 0.75rem;">{{ $wakaRows->count() }} orang</span>
                                        @endif
                                    </div>

                                    @if($wakaRows->isNotEmpty())
                                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                                            @foreach($wakaRows as $row)
                                                @php
                                                    $u = $wakaPerson($row);
                                                @endphp
                                                <div class="d-flex align-items-center gap-2.5 bg-white border border-gray-200/80 rounded-3 p-2 pe-2.5 min-w-0 shadow-sm" style="min-width: 0;">
                                                    <span class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                                                          style="width: 34px; height: 34px; font-size: 0.72rem; font-weight: 700; color: #334155; background: #f1f5f9; border: 1px solid #e2e8f0;">
                                                        {{ $u ? strtoupper(substr($u->nama, 0, 2)) : '?' }}
                                                    </span>
                                                    <span class="overflow-hidden flex-grow-1 min-w-0" style="min-width: 0;">
                                                        <span class="d-block fw-semibold text-dark text-truncate" style="font-size: 0.8rem;"
                                                              title="{{ $u->nama ?? '-' }}">
                                                            {{ $u->nama ?? 'Data guru tidak ditemukan' }}
                                                        </span>
                                                        @if($u)
                                                            <span class="d-block text-muted text-truncate" style="font-size: 0.7rem;">
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
                                                                class="btn btn-sm btn-outline-danger border-0 rounded-circle p-0 d-flex align-items-center justify-content-center flex-shrink-0"
                                                                style="width: 26px; height: 26px;" title="Hapus Penugasan">
                                                            <i class="bi bi-x-lg" style="font-size: 0.7rem;"></i>
                                                        </button>
                                                    </form>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-muted small" style="padding: 0.4rem 0;">Belum diisi</span>
                                    @endif
                                </div>

                                {{-- Shift Groups (Pagi & Siang) --}}
                                @foreach($groups as $group)
                                    @php
                                        $groupSections = $group['sections']->values();
                                    @endphp
                                    <div class="rounded-4 border p-3.5 {{ $group['wrapper'] }}">
                                        <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
                                            <span class="shift-badge {{ $group['header'] }} d-inline-flex align-items-center gap-1.5 fw-bold">
                                                <i class="bi {{ $group['icon'] }}"></i>{{ $group['title'] }}
                                            </span>
                                            <span class="text-muted" style="font-size: 0.75rem;">
                                                {{ $group['sections']->sum(fn ($s) => $s['rows']->count()) }} guru bertugas
                                            </span>
                                        </div>

                                        <div class="d-flex flex-column gap-3">
                                            @foreach($groupSections as $sec)
                                                @php
                                                    $secRows = $sec['rows']->values();
                                                    $person = $sec['person'];
                                                @endphp
                                                <div>
                                                    <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                                                        <span class="shift-badge {{ $sec['badge'] }} d-inline-flex align-items-center gap-1">
                                                            <i class="bi {{ $sec['icon'] }}"></i>{{ $sec['label'] }}
                                                        </span>
                                                        @if($secRows->isNotEmpty())
                                                            <span class="text-muted" style="font-size: 0.75rem;">{{ $secRows->count() }} orang</span>
                                                        @endif
                                                    </div>

                                                    @if($secRows->isNotEmpty())
                                                        {{-- Responsive Grid Card Guru --}}
                                                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                                                            @foreach($secRows as $row)
                                                                @php($u = $person($row))
                                                                <div class="d-flex align-items-center gap-2.5 bg-white border border-gray-200/80 rounded-3 p-2 pe-2.5 min-w-0 shadow-sm" style="min-width: 0;">
                                                                    <span class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                                                                          style="width: 34px; height: 34px; font-size: 0.72rem; font-weight: 700; color: #334155; background: #f1f5f9; border: 1px solid #e2e8f0;">
                                                                        {{ $u ? strtoupper(substr($u->nama, 0, 2)) : '?' }}
                                                                    </span>
                                                                    <span class="overflow-hidden flex-grow-1 min-w-0" style="min-width: 0;">
                                                                        <span class="d-block fw-semibold text-dark text-truncate" style="font-size: 0.8rem;"
                                                                              title="{{ $u->nama ?? '-' }}">
                                                                            {{ $u->nama ?? 'Data guru tidak ditemukan' }}
                                                                        </span>
                                                                        @if($u)
                                                                            <span class="d-block text-muted text-truncate" style="font-size: 0.7rem;">
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
                                                                                class="btn btn-sm btn-outline-danger border-0 rounded-circle p-0 d-flex align-items-center justify-content-center flex-shrink-0"
                                                                                style="width: 26px; height: 26px;" title="Hapus Penugasan">
                                                                            <i class="bi bi-x-lg" style="font-size: 0.7rem;"></i>
                                                                        </button>
                                                                    </form>
                                                                    @endif
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @else
                                                        <span class="text-muted small" style="padding: 0.4rem 0;">Belum diisi</span>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
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

{{-- ===== Modal: Pengaturan Shift & Kuota ===== --}}
@if($canManage)
<div class="modal fade" id="shiftModal" tabindex="-1" aria-labelledby="shiftModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 rounded-4 overflow-hidden">
            <div class="modal-header border-0 pt-4 px-4 pb-0">
                <div>
                    <h5 class="fw-bold text-dark mb-1" id="shiftModalLabel">
                        <i class="bi bi-gear me-1 text-secondary"></i>Pengaturan Shift & Kuota
                    </h5>
                    <p class="text-muted mb-0 small">Atur nama shift, jam bertugas, dan kuota petugas piket.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="modal-body px-4 py-3">
                {{-- Form Tambah Shift --}}
                <form method="POST" action="{{ route('kurikulum.jadwal-piket.shifts.store') }}" class="row g-3 align-items-end">
                    @csrf
                    <input type="hidden" name="from_shift_modal" value="1">
                    <div class="col-12 col-md-3">
                        <label class="form-label small fw-semibold mb-1">Nama Shift</label>
                        <input name="nama" class="form-control" required placeholder="Contoh: Jumat">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1">Mulai</label>
                        <input type="time" name="jam_mulai" class="form-control" required>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1">Selesai</label>
                        <input type="time" name="jam_selesai" class="form-control" required>
                    </div>
                    <div class="col-5 col-md-2">
                        <label class="form-label small fw-semibold mb-1">Maks. Petugas</label>
                        <input type="number" name="maksimal_petugas" class="form-control" min="1" max="100" value="4" required>
                    </div>
                    <div class="col-4 col-md-1">
                        <label class="form-label small fw-semibold mb-1">Urutan</label>
                        <input type="number" name="urutan" class="form-control" min="0" value="0">
                    </div>
                    <div class="col-3 col-md-2">
                        <button class="btn btn-primary w-100"><i class="bi bi-plus-lg me-1"></i> Tambah</button>
                    </div>
                </form>

                <hr class="my-4">

                {{-- Daftar Shift --}}
                <h6 class="fw-bold text-dark mb-2">
                    <i class="bi bi-list-check me-1 text-primary"></i>Daftar Shift
                </h6>
                <div class="table-responsive" style="max-height: 380px; overflow-y: auto;">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-1">Shift</th>
                                <th>Jam</th>
                                <th>Kuota</th>
                                <th>Status</th>
                                <th class="text-end pe-1">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($shifts as $shift)
                            <tr data-shift-row="{{ $shift->id }}">
                                <td class="ps-1">
                                    <form id="shift-form-{{ $shift->id }}" method="POST" action="{{ route('kurikulum.jadwal-piket.shifts.update', $shift) }}" class="d-none">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="from_shift_modal" value="1">
                                        <input type="hidden" name="is_active" value="{{ $shift->is_active ? 1 : 0 }}" class="shift-is-active-hidden">
                                    </form>
                                    <input form="shift-form-{{ $shift->id }}" name="nama" value="{{ $shift->nama }}" class="form-control" required>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <input form="shift-form-{{ $shift->id }}" type="time" name="jam_mulai" value="{{ substr($shift->jam_mulai, 0, 5) }}" class="form-control" required>
                                        <input form="shift-form-{{ $shift->id }}" type="time" name="jam_selesai" value="{{ substr($shift->jam_selesai, 0, 5) }}" class="form-control" required>
                                    </div>
                                </td>
                                <td>
                                    <input form="shift-form-{{ $shift->id }}" type="number" name="maksimal_petugas" value="{{ $shift->maksimal_petugas }}" class="form-control" min="1" max="100" required>
                                </td>
                                <td>
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input shift-toggle-active" data-shift-id="{{ $shift->id }}" data-url="{{ route('kurikulum.jadwal-piket.shifts.update', $shift) }}" {{ $shift->is_active ? 'checked' : '' }}>
                                        <label class="form-check-label small">Aktif</label>
                                    </div>
                                </td>
                                <td class="text-end pe-1">
                                    <input form="shift-form-{{ $shift->id }}" type="hidden" name="urutan" value="{{ $shift->urutan }}">
                                    <button form="shift-form-{{ $shift->id }}" type="submit" class="btn btn-sm btn-outline-primary btn-save-shift" title="Simpan"><i class="bi bi-save"></i></button>
                                    <form method="POST" action="{{ route('kurikulum.jadwal-piket.shifts.destroy', $shift) }}" class="d-inline ms-1" onsubmit="return confirm('Hapus shift ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="from_shift_modal" value="1">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">Belum ada shift. Tambahkan shift di atas.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-light border rounded-3 px-4 fw-semibold" data-bs-dismiss="modal">Selesai</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Auto-save toggle status active shift via instant AJAX & form submit handler
    document.addEventListener('DOMContentLoaded', function () {
        // 1. Direct event listener (change) pada checkbox status Aktif
        document.querySelectorAll('.shift-toggle-active').forEach(function (checkbox) {
            checkbox.addEventListener('change', function () {
                var shiftId = this.dataset.shiftId;
                var row = document.querySelector('tr[data-shift-row="' + shiftId + '"]') || this.closest('tr');
                var hiddenInput = row ? row.querySelector('input.shift-is-active-hidden') : null;
                var isActive = this.checked ? 1 : 0;

                if (hiddenInput) {
                    hiddenInput.value = isActive;
                }

                var url = this.dataset.url;
                var token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                    || document.querySelector('input[name="_token"]')?.value;

                fetch(url, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        is_active: isActive,
                        toggle_active_only: 1
                    })
                })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data && data.success) {
                        var currentStatus = Boolean(data.is_active);
                        checkbox.checked = currentStatus;
                        if (hiddenInput) {
                            hiddenInput.value = currentStatus ? '1' : '0';
                        }
                    } else {
                        alert('Gagal memperbarui status aktif shift.');
                    }
                })
                .catch(function (err) {
                    console.error(err);
                    alert('Terjadi kesalahan koneksi saat memperbarui status shift.');
                });
            });
        });

        // 2. Tombol Simpan per baris (Ikon Biru Save): presisi mengambil .checked dari row yang sama
        document.querySelectorAll('form[id^="shift-form-"]').forEach(function (form) {
            form.addEventListener('submit', function () {
                var formId = form.id;
                var shiftId = formId.replace('shift-form-', '').replace('shift-form-page-', '');
                var row = document.querySelector('tr[data-shift-row="' + shiftId + '"]') || form.closest('tr');
                var hiddenInput = form.querySelector('input.shift-is-active-hidden');
                var checkbox = document.querySelector('.shift-toggle-active[data-shift-id="' + shiftId + '"]');

                if (checkbox && hiddenInput) {
                    hiddenInput.value = checkbox.checked ? '1' : '0';
                }
            });
        });
    });

    // Buka kembali modal Pengaturan Shift & Kuota setelah simpan/hapus shift
    // dikirim dari dalam modal (flag from_shift_modal), termasuk saat validasi gagal.
    document.addEventListener('DOMContentLoaded', function () {
        var shiftModalEl = document.getElementById('shiftModal');
        var reopen = {{ session('open_shift_modal') ? 'true' : 'false' }}
            || '{{ old('from_shift_modal') ? '1' : '' }}' === '1';
        if (shiftModalEl && reopen) {
            bootstrap.Modal.getOrCreateInstance(shiftModalEl).show();
        }
    });

    // Konfirmasi "Kosongkan Jadwal" per hari (event delegation, sekali pasang).
    // Pesan menyebutkan nama hari & jumlah penugasan agar tidak salah hapus hari.
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form[data-clear-day-form]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                var hari    = form.dataset.hari || '';
                var minggu  = form.dataset.minggu || '';
                var jumlah  = form.dataset.jumlah || '0';

                var pesan = 'Apakah Anda yakin ingin mengosongkan seluruh jadwal piket untuk hari ' + hari + '?\n\n'
                    + jumlah + ' penugasan (Minggu ke-' + minggu + ') akan dihapus permanen. '
                    + 'Tindakan ini tidak dapat dibatalkan.';

                if (! window.confirm(pesan)) {
                    event.preventDefault();
                }
            });
        });
    });
</script>
@endpush
@endif
@endsection