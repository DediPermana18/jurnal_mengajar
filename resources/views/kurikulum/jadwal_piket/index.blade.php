@extends('layouts.app')

@section('title', 'Jadwal Piket Guru - Kurikulum')

@push('styles')
<style>
    /* Mengubah layout jadi 3 kolom di baris atas dan 2 kolom di baris bawah */
    .jadwal-piket-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1.25rem;
    }

    /* Membuat card Kamis & Jumat di baris kedua melebar rapi */
    .jadwal-piket-grid > div:nth-child(4),
    .jadwal-piket-grid > div:nth-child(5) {
        grid-column: span 1;
    }

    /* Tablet/Laptop sedang: 2 Kolom */
    @media (max-width: 991.98px) {
        .jadwal-piket-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    /* HP/Mobile: 1 Kolom Full */
    @media (max-width: 575.98px) {
        .jadwal-piket-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Style untuk badge shift */
    .shift-badge {
        font-size: 0.7rem;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-weight: 600;
    }
    .shift-pagi { background: #bfdbfe; color: #374151; }
    .shift-siang { background: #f3f4f6; color: #374151; }
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

    {{-- Grid Jadwal Hari (Senin - Jumat) --}}
    <div class="jadwal-piket-grid mb-4">
        @php
            $dayColors = [
                'Senin'  => ['bg' => '#eff6ff', 'badge' => 'primary',   'border' => '#bfdbfe', 'icon' => 'bi-calendar-event'],
                'Selasa' => ['bg' => '#f0fdf4', 'badge' => 'success',   'border' => '#bbf7d0', 'icon' => 'bi-calendar-event'],
                'Rabu'   => ['bg' => '#fefce8', 'badge' => 'warning',   'border' => '#fef08a', 'icon' => 'bi-calendar-event'],
                'Kamis'  => ['bg' => '#faf5ff', 'badge' => 'secondary', 'border' => '#e9d5ff', 'icon' => 'bi-calendar-event'],
                'Jumat'  => ['bg' => '#ecfeff', 'badge' => 'info',      'border' => '#a5f3fc', 'icon' => 'bi-calendar-event'],
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
                $color = $dayColors[$hari] ?? ['bg' => '#f8fafc', 'badge' => 'secondary', 'border' => '#e2e8f0', 'icon' => 'bi-calendar-event'];
                $isToday = ($hari === $hariIni);
                // Ambil data per shift dari jadwal hari ini
                $wakaHariIni = $petugasHariIni->whereNotNull('waka_user_id')->values();
                $koorPagiHariIni = $petugasHariIni->whereNotNull('koordinator_pagi_user_id')->values();
                $petugasPagiHariIni = $petugasHariIni->whereNotNull('petugas_pagi_user_id')->values();
                $koorSiangHariIni = $petugasHariIni->whereNotNull('koordinator_siang_user_id')->values();
                $petugasSiangHariIni = $petugasHariIni->whereNotNull('petugas_siang_user_id')->values();
            @endphp
            <div>
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

                        {{-- Dedicated Page Edit Button for this day --}}
                        @if($canManage)
                        <a href="{{ route('kurikulum.jadwal-piket.create', ['hari' => $hari, 'minggu_ke' => $mingguKe]) }}"
                           class="btn btn-sm btn-light rounded-circle border shadow-none text-secondary d-flex align-items-center justify-content-center"
                           style="width: 32px; height: 32px; padding: 0;"
                           title="Kelola Guru Piket Hari {{ $hari }}">
                            <i class="bi bi-pencil-fill text-primary" style="font-size: 0.8rem;"></i>
                        </a>
                        @endif
                    </div>

                    {{-- Card Body: Daftar Guru Piket dengan Shift Pagi & Siang --}}
                    <div class="card-body px-4 py-3">
                        @if($petugasHariIni->isEmpty())
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-person-x fs-2 d-block mb-1 text-secondary opacity-50"></i>
                                <span class="small">Belum ada guru piket</span>
                            </div>
                        @else
                            <div class="d-flex flex-column gap-2">
                                {{-- Show Shift Info --}}
                                @if($wakaHariIni->isNotEmpty())
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span class="shift-badge shift-pagi fw-semibold">WAKA PIKET</span>
                                        @foreach($wakaHariIni as $waka)
                                            <span class="badge bg-primary rounded-pill px-2 py-1 small"
                                                  style="font-size: 0.7rem;">
                                                {{ $waka->user ? strtoupper(substr($waka->user->nama, 0, 2)) : 'Waka' }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif

                                @if($koorPagiHariIni->isNotEmpty())
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span class="shift-badge shift-pagi fw-semibold">KOORDINATOR PAGI (07.00-11.00)</span>
                                        @foreach($koorPagiHariIni as $koor)
                                            <span class="badge bg-primary rounded-pill px-2 py-1 small"
                                                  style="font-size: 0.7rem;">
                                                Koor: {{ $koor->user ? strtoupper(substr($koor->user->nama, 0, 2)) : 'Koordinator' }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif

                                @if($petugasPagiHariIni->isNotEmpty())
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span class="shift-badge shift-pagi fw-semibold">PETUGAS PAGI (3-4 ORANG)</span>
                                        @foreach($petugasPagiHariIni as $petugasPagi)
                                            @php
                                                $nip = $petugasPagi->user ? ($petugasPagi->user->nip ?? '-') : '-';
                                                $nama = $petugasPagi->user ? $petugasPagi->user->nama : 'Petugas';
                                            @endphp
                                            <span class="badge bg-primary rounded-pill px-2 py-1 small"
                                                  style="font-size: 0.7rem;">
                                                {{ strtoupper(substr($nama, 0, 2)) }} ({{ $nip }})
                                            </span>
                                        @endforeach
                                        <span class="text-muted small">(3-4 orang)</span>
                                    </div>
                                @endif

                                {{-- Sesi Siang divider --}}
                                @if($koorSiangHariIni->isNotEmpty() || $petugasSiangHariIni->isNotEmpty())
                                    <hr class="my-3 border-primary-subtle">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span class="shift-badge shift-siang fw-semibold">KOORDINATOR SIANG (11.00-15.00)</span>
                                        @foreach($koorSiangHariIni as $koorSiang)
                                            <span class="badge bg-slate-500 rounded-pill px-2 py-1 small"
                                                  style="font-size: 0.7rem;">
                                                Koor: {{ $koorSiang->user ? strtoupper(substr($koorSiang->user->nama, 0, 2)) : 'Koordinator' }}
                                            </span>
                                        @endforeach
                                    </div>

                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span class="shift-badge shift-siang fw-semibold">PETUGAS SIANG (3-4 ORANG)</span>
                                        @foreach($petugasSiangHariIni as $petugasSiang)
                                            @php
                                                $nip = $petugasSiang->user ? ($petugasSiang->user->nip ?? '-') : '-';
                                                $nama = $petugasSiang->user ? $petugasSiang->user->nama : 'Petugas';
                                            @endphp
                                            <span class="badge bg-slate-500 rounded-pill px-2 py-1 small"
                                                  style="font-size: 0.7rem;">
                                                {{ strtoupper(substr($nama, 0, 2)) }} ({{ $nip }})
                                            </span>
                                        @endforeach
                                        <span class="text-muted small">(3-4 orang)</span>
                                    </div>
                                @endif

                                @foreach($petugasHariIni as $item)
                                    @php
                                        $user = $item->user;
                                        $waka = $item->waka;
                                        $koorPagi = $item->koordinatorPagi;
                                        $petugasPagi = $item->petugasPagi;
                                        $koorSiang = $item->koordinatorSiang;
                                        $petugasSiang = $item->petugasSiang;
                                    @endphp
                                    <div class="d-flex align-items-center justify-content-between p-2 rounded-3 border bg-light-subtle">
                                        <div class="d-flex align-items-center gap-2 overflow-hidden">
                                            <div class="rounded-circle bg-primary-subtle text-primary fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                                                 style="width: 34px; height: 34px; font-size: 0.8rem;">
                                                @if($user)
                                                    {{ strtoupper(substr($user->nama, 0, 2)) }}
                                                @elseif($waka)
                                                    {{ strtoupper(substr($waka->nama, 0, 2)) }}
                                                @elseif($koorPagi && $koorPagi->user)
                                                    {{ strtoupper(substr($koorPagi->user->nama, 0, 2)) }}
                                                @elseif($koorSiang && $koorSiang->user)
                                                    {{ strtoupper(substr($koorSiang->user->nama, 0, 2)) }}
                                                @else
                                                    -
                                                @endif
                                            </div>
                                            <div class="overflow-hidden">
                                                <div class="fw-semibold text-dark text-truncate" style="font-size: 0.875rem;" title="{{ $user->nama ?? $waka->nama ?? $koorPagi->user->nama ?? $koorSiang->user->nama ?? '-' }}">
                                                    @if($user) {{$user->nama}}
                                                    @elseif($waka) {{$waka->nama}}
                                                    @elseif($koorPagi && $koorPagi->user) {{$koorPagi->user->nama}}
                                                    @elseif($koorSiang && $koorSiang->user) {{$koorSiang->user->nama}}
                                                    @endif
                                                </div>
                                                @if($item->shift)
                                                    <div class="text-primary small text-truncate">{{ $item->shift->nama }} ({{ $item->shift->jam_label }}) - Maks. {{ $item->shift->maksimal_petugas }}</div>
                                                @endif
                                                <div class="text-muted small text-truncate" style="font-size: 0.75rem;">
                                                    @if($user) NIP: {{ $user->nip ?? '-' }} &bull; {{ $user->role_label }}
                                                    @elseif($waka) Waka: {{ $waka->role_label ?? '-' }}
                                                    @elseif($koorPagi && $koorPagi->user) KoorPagi: {{ $koorPagi->user->role_label ?? '-' }}
                                                    @elseif($koorSiang && $koorSiang->user) KoorSiang: {{ $koorSiang->user->role_label ?? '-' }}
                                                    @endif
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Delete Action --}}
                                        @if($canManage)
                                        <form action="{{ route('kurikulum.jadwal-piket.destroy', $item->id) }}" method="POST"
                                              onsubmit="return confirm('Hapus penugasan piket {{ $user->nama ?? $waka->nama ?? 'Guru ini' }} pada hari {{ $hari }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm rounded-circle border-0 p-1 ms-2"
                                                    title="Hapus Penugasan">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </form>
                                        @endif
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
@endsection