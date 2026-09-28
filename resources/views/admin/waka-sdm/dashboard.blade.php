@extends('layouts.app')

@section('title', 'Dashboard Waka SDM - WebJournal')

@section('content')
<div class="container-fluid px-0">

    {{-- Mapper badge warna (dari model/controller) Bootstrap -> Tailwind.
         Halaman ini 100% Tailwind; hanya lebar progress bar yang memakai
         inline style sebagai data binding dinamis (bukan CSS hack). --}}
    @php
        $twBadge = [
            'bg-danger-subtle text-danger border border-danger-subtle'    => 'bg-red-50 text-red-600 border border-red-200',
            'bg-warning-subtle text-warning-emphasis border border-warning-subtle' => 'bg-amber-50 text-amber-600 border border-amber-200',
            'bg-orange-subtle text-orange border border-orange-subtle'   => 'bg-orange-50 text-orange-600 border border-orange-200',
            'bg-success-subtle text-success border border-success-subtle' => 'bg-emerald-50 text-emerald-600 border border-emerald-200',
            'bg-info-subtle text-info-emphasis border border-info-subtle' => 'bg-sky-50 text-sky-600 border border-sky-200',
            'bg-secondary-subtle text-secondary border border-secondary-subtle' => 'bg-slate-100 text-slate-600 border border-slate-200',
        ];
        $twBadgeClass = fn (string $bs): string => $twBadge[$bs] ?? 'bg-slate-100 text-slate-600 border border-slate-200';
        $pctHadirHariIni = $totalGuruTerjadwalHariIni > 0
            ? round(($totalGuruHadirHariIni / $totalGuruTerjadwalHariIni) * 100)
            : 100;
    @endphp

    {{-- ============================================================== --}}
    {{-- 1. TOP SECTION: PAGE HEADER                                    --}}
    {{-- ============================================================== --}}
    <div class="mb-6">
        <span class="inline-flex items-center gap-1.5 rounded-full bg-sky-50 text-sky-700 border border-sky-100 px-2.5 py-1 text-xs font-semibold">
            <i class="bi bi-people-fill"></i> Kepegawaian & SDM
        </span>
        <h1 class="text-2xl font-bold text-slate-800 mt-2 mb-1">Dashboard Waka SDM</h1>
        <p class="text-sm text-slate-500">
            Monitoring kehadiran guru, pelacakan izin/cuti harian, dan kedisiplinan mengajar real-time.
        </p>
    </div>

    {{-- ============================================================== --}}
    {{-- 2. QUICK ACTION & DATE FILTER BAR                              --}}
    {{-- ============================================================== --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        {{-- Sisi Kiri: Group Navigasi / Aksi --}}
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('waka-sdm.rekap-izin') }}"
               class="inline-flex items-center gap-2 rounded-lg bg-white border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition-colors hover:bg-slate-50">
                <i class="bi bi-calendar2-check text-sky-600"></i> Rekap Izin & Cuti
            </a>
            <a href="{{ route('waka-sdm.rekap-presensi-guru') }}"
               class="inline-flex items-center gap-2 rounded-lg bg-sky-600 text-white px-4 py-2 text-sm font-semibold shadow-sm transition-colors hover:bg-sky-700">
                <i class="bi bi-bar-chart-line"></i> Rekap Performa KBM
            </a>
        </div>

        {{-- Sisi Kanan: Date Picker Pill --}}
        <span class="inline-flex items-center gap-2 rounded-lg bg-white border border-slate-200 px-3 py-2 text-sm text-slate-600 shadow-sm">
            <i class="bi bi-calendar3 text-slate-400"></i>
            {{ $hariIniStr }}, {{ \Carbon\Carbon::now()->translatedFormat('d F Y') }}
        </span>
    </div>

    {{-- ============================================================== --}}
    {{-- FLASH ALERT MESSAGES                                           --}}
    {{-- ============================================================== --}}
    @if(session('success'))
        <div class="flex items-start gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 mb-6">
            <i class="bi bi-check-circle-fill mt-0.5 text-emerald-500"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 mb-6">
            <i class="bi bi-x-circle-fill mt-0.5 text-red-500"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif
    @if($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 mb-6">
            <strong class="block mb-1">Terjadi kesalahan:</strong>
            <ul class="pl-4 list-disc mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ============================================================== --}}
    {{-- 3. GRID STAT CARDS (FORCE 4 COLUMNS COMPACT)                   --}}
    {{-- ============================================================== --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

        {{-- Card 1: Guru Hadir Hari Ini --}}
        <div class="bg-white border border-slate-200/80 rounded-xl shadow-sm p-4 sm:p-5 flex flex-col justify-between">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Guru Hadir Hari Ini</p>
                    <div class="flex items-baseline gap-1.5 mt-1">
                        <span class="text-2xl sm:text-3xl font-bold text-slate-800">{{ $totalGuruHadirHariIni }}</span>
                        <span class="text-xs sm:text-sm text-slate-400">/ {{ $totalGuruTerjadwalHariIni }} Terjadwal</span>
                    </div>
                </div>
                <div class="flex items-center justify-center w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-emerald-50 text-emerald-600 text-lg sm:text-xl shrink-0">
                    <i class="bi bi-person-check-fill"></i>
                </div>
            </div>
            <div class="pt-2">
                <div class="w-full h-1.5 bg-slate-100 rounded-full overflow-hidden mb-2">
                    <div class="h-full bg-emerald-500 rounded-full" style="width: {{ $pctHadirHariIni }}%"></div>
                </div>
                <div class="flex items-center justify-between text-xs text-slate-500">
                    <span>Total Aktif: <strong class="text-slate-700">{{ $totalGuruAktif }}</strong></span>
                    <span class="font-semibold text-emerald-600">{{ $pctHadirHariIni }}% Hadir</span>
                </div>
            </div>
        </div>

        {{-- Card 2: Guru Izin / Sakit / Cuti Hari Ini --}}
        <div class="bg-white border border-slate-200/80 rounded-xl shadow-sm p-4 sm:p-5 flex flex-col justify-between">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Guru Izin / Sakit / Cuti</p>
                    <div class="flex items-baseline gap-1.5 mt-1">
                        <span class="text-2xl sm:text-3xl font-bold text-slate-800">{{ $totalGuruIzinHariIni }}</span>
                        <span class="text-xs sm:text-sm text-slate-400">Guru</span>
                    </div>
                </div>
                <div class="flex items-center justify-center w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-amber-50 text-amber-600 text-lg sm:text-xl shrink-0">
                    <i class="bi bi-person-dash-fill"></i>
                </div>
            </div>
            <div class="flex items-center justify-between text-xs text-slate-500 pt-2">
                <span>Pengajuan Aktif Hari Ini</span>
                <a href="{{ route('waka-sdm.rekap-izin', ['tanggal' => $todayStr]) }}"
                   class="font-semibold text-amber-600 transition-colors hover:text-amber-700">
                    Lihat Detail &rarr;
                </a>
            </div>
        </div>

        {{-- Card 3: Total Kelas Kosong --}}
        <div class="bg-white border border-slate-200/80 rounded-xl shadow-sm p-4 sm:p-5 flex flex-col justify-between">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Kelas Kosong / Belum Diisi</p>
                    <div class="flex items-baseline gap-1.5 mt-1">
                        <span class="text-2xl sm:text-3xl font-bold text-slate-800">{{ $totalKelasKosong }}</span>
                        <span class="text-xs sm:text-sm text-slate-400">Kelas</span>
                    </div>
                </div>
                <div class="flex items-center justify-center w-10 h-10 sm:w-11 sm:h-11 rounded-xl text-lg sm:text-xl shrink-0 {{ $totalKelasKosong > 0 ? 'bg-red-50 text-red-600' : 'bg-slate-100 text-slate-500' }}">
                    <i class="bi bi-door-closed-fill"></i>
                </div>
            </div>
            <div class="flex items-center justify-between text-xs text-slate-500 pt-2">
                <span>{{ $sesiKosongHariIni }} Sesi Belum Terisi</span>
                @if($totalKelasKosong > 0)
                    <span class="inline-flex items-center rounded-full bg-red-50 text-red-600 border border-red-200 px-2 py-0.5 text-xs font-semibold">
                        Perlu Piket
                    </span>
                @else
                    <span class="inline-flex items-center rounded-full bg-emerald-50 text-emerald-600 border border-emerald-200 px-2 py-0.5 text-xs font-semibold">
                        Lengkap
                    </span>
                @endif
            </div>
        </div>

        {{-- Card 4: Kehadiran Guru Bulan Ini --}}
        <div class="bg-white border border-slate-200/80 rounded-xl shadow-sm p-4 sm:p-5 flex flex-col justify-between">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Kehadiran Bulan {{ \Carbon\Carbon::now()->translatedFormat('F') }}</p>
                    <div class="flex items-baseline gap-1.5 mt-1">
                        <span class="text-2xl sm:text-3xl font-bold text-slate-800">{{ $persentaseKehadiranBulanIni }}%</span>
                    </div>
                </div>
                <div class="flex items-center justify-center w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-sky-50 text-sky-600 text-lg sm:text-xl shrink-0">
                    <i class="bi bi-award-fill"></i>
                </div>
            </div>
            <div class="pt-2">
                <div class="w-full h-1.5 bg-slate-100 rounded-full overflow-hidden mb-2">
                    <div class="h-full bg-sky-500 rounded-full" style="width: {{ $persentaseKehadiranBulanIni }}%"></div>
                </div>
                <div class="flex items-center justify-between text-xs text-slate-500">
                    <span>Target: 95.0%</span>
                    <span class="font-semibold text-sky-600">Kinerja Baik</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ============================================================== --}}
    {{-- 4. MAIN CONTENT GRID (WIDGETS MONITORING)                     --}}
    {{-- ============================================================== --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

        {{-- Widget Kiri: Guru Tidak Hadir / Izin Hari Ini --}}
        <div class="bg-white border border-slate-200/80 rounded-xl shadow-sm overflow-hidden flex flex-col">
            <div class="flex items-center justify-between flex-wrap gap-2 px-5 py-4 border-b border-slate-100">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-amber-50 text-amber-600">
                            <i class="bi bi-person-x text-base"></i>
                        </span>
                        <h5 class="text-base font-bold text-slate-800">Guru Tidak Hadir / Izin Hari Ini</h5>
                    </div>
                    <p class="text-xs text-slate-500 mt-1">Daftar guru yang berhalangan hadir dan status penugasan guru pengganti.</p>
                </div>
                <span class="inline-flex items-center rounded-full bg-amber-50 text-amber-600 border border-amber-200 px-2.5 py-1 text-xs font-semibold">
                    {{ $guruIzinHariIniList->count() }} Guru
                </span>
            </div>

            @if($guruIzinHariIniList->isEmpty())
                <div class="flex flex-col items-center justify-center text-center py-6 sm:py-8 px-4">
                    <div class="flex items-center justify-center w-12 h-12 rounded-full bg-emerald-50 text-emerald-600 mb-3">
                        <i class="bi bi-check2-circle text-xl"></i>
                    </div>
                    <h6 class="text-sm font-bold text-slate-800 mb-1">Semua guru terjadwal hadir hari ini.</h6>
                    <p class="text-xs text-slate-500 mb-0">Tidak ada pengajuan izin, sakit, atau dinas luar yang aktif untuk hari ini.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-slate-50 text-left">
                                <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-500">Nama Guru</th>
                                <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-500">Status</th>
                                <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-500">Alasan</th>
                                <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-500">Guru Pengganti</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($guruIzinHariIniList as $izin)
                                <tr class="transition-colors hover:bg-slate-50/60">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <div class="flex items-center justify-center w-8 h-8 rounded-full bg-amber-50 text-amber-700 text-xs font-bold">
                                                {{ strtoupper(substr($izin->user?->nama ?? 'G', 0, 1)) }}
                                            </div>
                                            <div class="min-w-0">
                                                <div class="font-semibold text-slate-800 truncate max-w-[170px]" title="{{ $izin->user?->nama }}">
                                                    {{ $izin->user?->nama ?? 'Guru Tidak Ditemukan' }}
                                                </div>
                                                <div class="text-xs text-slate-400">{{ $izin->user?->nip ? 'NIP: ' . $izin->user->nip : 'Non-NIP' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center rounded-full bg-slate-100 text-slate-600 border border-slate-200 px-2 py-1 text-xs font-semibold">
                                            {{ $izin->kategori_izin_label ?? ucfirst(str_replace('_', ' ', $izin->kategori_izin ?? 'Izin')) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="text-xs text-slate-600 truncate max-w-[170px]" title="{{ $izin->alasan ?? $izin->keterangan }}">
                                            {{ $izin->alasan ?? $izin->keterangan ?? '-' }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($izin->guru_pengganti)
                                            <span class="inline-flex items-center rounded-full bg-emerald-50 text-emerald-600 border border-emerald-200 px-2 py-1 text-xs font-semibold"
                                                  title="Guru Pengganti / Cover">
                                                <i class="bi bi-person-check-fill mr-1"></i> {{ $izin->guru_pengganti->nama }}
                                            </span>
                                        @elseif($izin->approverPiket)
                                            <span class="inline-flex items-center rounded-full bg-sky-50 text-sky-600 border border-sky-200 px-2 py-1 text-xs font-semibold"
                                                  title="Dicatat oleh Piket">
                                                <i class="bi bi-shield-check mr-1"></i> Piket: {{ $izin->approverPiket->nama }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-red-50 text-red-600 border border-red-200 px-2 py-1 text-xs font-semibold">
                                                <i class="bi bi-exclamation-circle mr-1"></i> Belum Cover
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="flex items-center justify-between text-xs border-t border-slate-100 pt-3 mt-auto px-5 pb-4">
                <span class="text-slate-500 text-[11px] sm:text-xs truncate pr-2">Diperbarui real-time dari database izin</span>
                <a href="{{ route('waka-sdm.rekap-izin') }}" class="font-semibold text-sky-600 transition-colors hover:text-sky-700 shrink-0 whitespace-nowrap">
                    Kelola Rekap Izin &rarr;
                </a>
            </div>
        </div>

        {{-- Widget Kanan: Pantauan Kelas Kosong (Jam Ini) --}}
        <div class="bg-white border border-slate-200/80 rounded-xl shadow-sm overflow-hidden flex flex-col">
            <div class="flex items-center justify-between flex-wrap gap-2 px-5 py-4 border-b border-slate-100">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-red-50 text-red-600">
                            <i class="bi bi-door-closed text-base"></i>
                        </span>
                        <h5 class="text-base font-bold text-slate-800">Pantauan Kelas Kosong (Jam Ini)</h5>
                    </div>
                    <p class="text-xs text-slate-500 mt-1">Sesi KBM yang sedang berlangsung/terjadwal tapi Jurnal KBM-nya belum diisi guru.</p>
                </div>
                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold border {{ $kelasKosongHariIniList->count() > 0 ? 'bg-red-50 text-red-600 border-red-200' : 'bg-emerald-50 text-emerald-600 border-emerald-200' }}">
                    {{ $kelasKosongHariIniList->count() }} Sesi Belum Diisi
                </span>
            </div>

            @if($kelasKosongHariIniList->isEmpty())
                <div class="flex flex-col items-center justify-center text-center py-6 sm:py-8 px-4">
                    <div class="flex items-center justify-center w-12 h-12 rounded-full bg-emerald-50 text-emerald-600 mb-3">
                        <i class="bi bi-check2-all text-xl"></i>
                    </div>
                    <h6 class="text-sm font-bold text-slate-800 mb-1">Semua sesi KBM hari ini sudah terisi dengan baik.</h6>
                    <p class="text-xs text-slate-500 mb-0">Tidak ada kelas kosong atau jurnal mengajar yang terlewatkan hari ini.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-slate-50 text-left">
                                <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-500">Jam / Sesi</th>
                                <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-500">Kelas & Mapel</th>
                                <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-500">Guru Pengajar</th>
                                <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-500 text-right">Aksi Cepat</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($kelasKosongHariIniList as $item)
                                <tr class="transition-colors hover:bg-slate-50/60">
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center rounded-lg bg-slate-100 text-slate-700 border border-slate-200 px-2 py-0.5 text-xs font-bold">
                                            Jam Ke-{{ $item->jam?->jam_ke ?? '-' }}
                                        </span>
                                        <div class="text-xs text-slate-400 mt-0.5">
                                            {{ $item->jam ? \Carbon\Carbon::parse($item->jam->jam_mulai)->format('H:i') . ' - ' . \Carbon\Carbon::parse($item->jam->jam_selesai)->format('H:i') : '' }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-slate-800">{{ $item->kelas?->nama_kelas_lengkap ?? $item->kelas?->nama_kelas ?? '-' }}</div>
                                        <div class="text-xs text-slate-500 truncate max-w-[140px]" title="{{ $item->mapel?->nama_mapel }}">
                                            {{ $item->mapel?->nama_mapel ?? '-' }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="text-xs font-semibold text-slate-700">{{ $item->guru?->nama ?? 'Guru Tidak Ditemukan' }}</div>
                                        @if($item->izin)
                                            <span class="inline-flex items-center rounded-full bg-amber-50 text-amber-600 border border-amber-200 mt-0.5 px-1.5 py-0.5 text-[11px] font-semibold">
                                                <i class="bi bi-exclamation-triangle mr-0.5"></i> Izin
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-red-50 text-red-600 border border-red-200 mt-0.5 px-1.5 py-0.5 text-[11px] font-semibold">
                                                Belum Hadir
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        @if($item->waUrl)
                                            <a href="{{ $item->waUrl }}" target="_blank"
                                               class="inline-flex items-center gap-1 rounded-full bg-emerald-500 text-white px-2.5 py-1 text-xs font-semibold shadow-sm transition-colors hover:bg-emerald-600"
                                               title="Kirim pesan WhatsApp pengingat">
                                                <i class="bi bi-whatsapp"></i> Ingatkan
                                            </a>
                                        @else
                                            <a href="{{ route('waka-sdm.rekap-izin') }}"
                                               class="inline-flex items-center gap-1 rounded-full bg-white border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-500 transition-colors hover:bg-slate-50"
                                               title="Buka manajemen izin / piket">
                                                <i class="bi bi-shield-fill-exclamation text-amber-500"></i> Hubungi Piket
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="flex items-center justify-between text-xs border-t border-slate-100 pt-3 mt-auto px-5 pb-4">
                <span class="text-slate-500 text-[11px] sm:text-xs truncate pr-2">Kirim pengingat atau koordinasikan dengan Tim Piket</span>
                <a href="#tabelMonitoringLengkap" class="font-semibold text-sky-600 transition-colors hover:text-sky-700 shrink-0 whitespace-nowrap">
                    Lihat Semua Sesi &darr;
                </a>
            </div>
        </div>
    </div>

    {{-- ============================================================== --}}
    {{-- 5. TABLE SECTION: MONITORING STATUS KBM & GURU HARI INI       --}}
    {{-- ============================================================== --}}
    <div id="tabelMonitoringLengkap" class="w-full bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mb-6">
        <div class="flex items-center justify-between flex-wrap gap-2 px-5 py-3.5 border-b border-slate-100">
            <div>
                <h5 class="text-base font-bold text-slate-800">
                    <i class="bi bi-broadcast text-red-500 mr-1.5"></i> Monitoring Status KBM & Guru Hari Ini
                </h5>
                <p class="text-xs text-slate-500 mt-0.5">Pelacakan sesi jadwal KBM, keterisian jurnal mengajar, dan penugasan guru pengganti hari ini.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center rounded-lg bg-slate-100 text-slate-600 border border-slate-200 px-2.5 py-1 text-xs font-semibold">
                    Total: {{ $monitoringKbmHariIni->count() }} Sesi
                </span>
                <span class="inline-flex items-center rounded-lg bg-emerald-50 text-emerald-600 border border-emerald-200 px-2.5 py-1 text-xs font-semibold">
                    {{ $sesiTerisiHariIni }} Terisi
                </span>
                <span class="inline-flex items-center rounded-lg bg-red-50 text-red-600 border border-red-200 px-2.5 py-1 text-xs font-semibold">
                    {{ $sesiKosongHariIni }} Belum Terisi
                </span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50/80 border-y border-slate-200/80 text-left">
                        <th class="px-4 py-3 text-[11px] font-semibold tracking-wider text-slate-500 uppercase text-center w-12">No</th>
                        <th class="px-4 py-3 text-[11px] font-semibold tracking-wider text-slate-500 uppercase">Jam / Waktu</th>
                        <th class="px-4 py-3 text-[11px] font-semibold tracking-wider text-slate-500 uppercase">Kelas</th>
                        <th class="px-4 py-3 text-[11px] font-semibold tracking-wider text-slate-500 uppercase">Mata Pelajaran</th>
                        <th class="px-4 py-3 text-[11px] font-semibold tracking-wider text-slate-500 uppercase">Guru Pengajar</th>
                        <th class="px-4 py-3 text-[11px] font-semibold tracking-wider text-slate-500 uppercase">Status Guru</th>
                        <th class="px-4 py-3 text-[11px] font-semibold tracking-wider text-slate-500 uppercase">Status Jurnal</th>
                        <th class="px-4 py-3 text-[11px] font-semibold tracking-wider text-slate-500 uppercase">Guru Pengganti</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($monitoringKbmHariIni as $index => $item)
                        <tr class="transition-colors hover:bg-slate-50/60">
                            <td class="px-4 py-3 text-center text-slate-500 font-semibold">{{ $index + 1 }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-lg bg-slate-100 text-slate-700 border border-slate-200 px-2 py-0.5 text-xs font-bold">
                                    Jam Ke-{{ $item->jam?->jam_ke ?? '-' }}
                                </span>
                                <div class="text-xs text-slate-400 mt-0.5">
                                    {{ $item->jam ? \Carbon\Carbon::parse($item->jam->jam_mulai)->format('H:i') . ' - ' . \Carbon\Carbon::parse($item->jam->jam_selesai)->format('H:i') : '' }}
                                </div>
                            </td>
                            <td class="px-4 py-3 font-bold text-slate-800">{{ $item->kelas?->nama_kelas_lengkap ?? $item->kelas?->nama_kelas ?? '-' }}</td>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-slate-700 truncate max-w-[160px]" title="{{ $item->mapel?->nama_mapel }}">
                                    {{ $item->mapel?->nama_mapel ?? '-' }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="flex items-center justify-center w-8 h-8 rounded-full bg-sky-50 text-sky-600 text-xs font-bold">
                                        {{ strtoupper(substr($item->guru?->nama ?? 'G', 0, 1)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="font-semibold text-slate-800 truncate max-w-[150px]" title="{{ $item->guru?->nama }}">
                                            {{ $item->guru?->nama ?? 'Belum Ditentukan' }}
                                        </div>
                                        <div class="text-xs text-slate-400">{{ $item->guru?->nip ? 'NIP: ' . $item->guru->nip : 'Non-NIP' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                @if($item->izin)
                                    <span class="inline-flex items-center rounded-full bg-amber-50 text-amber-600 border border-amber-200 px-2 py-1 text-xs font-semibold">
                                        <i class="bi bi-exclamation-triangle mr-1"></i> Izin ({{ $item->izin->kategori_izin_label ?? 'Izin' }})
                                    </span>
                                @elseif($item->statusKehadiran === 'Hadir')
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 text-emerald-600 border border-emerald-200 px-2 py-1 text-xs font-semibold">
                                        <i class="bi bi-check-circle mr-1"></i> Hadir
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-slate-100 text-slate-600 border border-slate-200 px-2 py-1 text-xs font-semibold">
                                        {{ $item->statusKehadiran }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold border {{ $twBadgeClass($item->statusInfo['badge_class'] ?? '') }}">
                                    <i class="bi {{ $item->statusInfo['icon'] ?? 'bi-circle' }} mr-1"></i>
                                    {{ $item->statusInfo['label'] ?? 'Belum Terisi' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                @if($item->guruPengganti)
                                    <span class="inline-flex items-center rounded-lg bg-sky-50 text-sky-600 border border-sky-200 px-2 py-1 text-xs font-semibold">
                                        <i class="bi bi-person-fill-gear mr-1"></i> {{ $item->guruPengganti->nama }}
                                    </span>
                                @else
                                    <span class="text-xs text-slate-400">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-slate-500">
                                <div class="flex flex-col items-center justify-center">
                                    <i class="bi bi-calendar-x text-2xl text-slate-300 mb-1.5"></i>
                                    <p class="text-xs text-slate-500 font-medium mb-0.5">Tidak ada jadwal KBM yang aktif untuk hari ini.</p>
                                    <p class="text-[11px] text-slate-400 mb-0">Semua agenda KBM pada hari {{ $hariIniStr }} akan tampil di tabel ini.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ============================================================== --}}
    {{-- 6. TABLE SECTION: PENGAJUAN IZIN & CUTI GURU TERBARU         --}}
    {{-- ============================================================== --}}
    <div class="w-full bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="flex items-center justify-between flex-wrap gap-2 px-5 py-3.5 border-b border-slate-100">
            <div>
                <h5 class="text-base font-bold text-slate-800">
                    <i class="bi bi-clipboard2-check text-sky-600 mr-1.5"></i> Pengajuan Izin & Cuti Guru Terbaru
                </h5>
                <p class="text-xs text-slate-500 mt-0.5">Daftar riwayat permohonan izin, sakit, dan dinas luar terbaru oleh dewan guru.</p>
            </div>
            <a href="{{ route('waka-sdm.rekap-izin') }}"
               class="inline-flex items-center rounded-lg bg-white border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm transition-colors hover:bg-slate-50">
                Lihat Semua Rekap &rarr;
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50/80 border-y border-slate-200/80 text-left">
                        <th class="px-4 py-3 text-[11px] font-semibold tracking-wider text-slate-500 uppercase text-center w-12">No</th>
                        <th class="px-4 py-3 text-[11px] font-semibold tracking-wider text-slate-500 uppercase">Nama Guru</th>
                        <th class="px-4 py-3 text-[11px] font-semibold tracking-wider text-slate-500 uppercase">Tanggal Izin</th>
                        <th class="px-4 py-3 text-[11px] font-semibold tracking-wider text-slate-500 uppercase">Jenis Izin</th>
                        <th class="px-4 py-3 text-[11px] font-semibold tracking-wider text-slate-500 uppercase">Alasan / Keterangan</th>
                        <th class="px-4 py-3 text-[11px] font-semibold tracking-wider text-slate-500 uppercase">Tugas Siswa</th>
                        <th class="px-4 py-3 text-[11px] font-semibold tracking-wider text-slate-500 uppercase">Status Approval</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($recentIzin as $idx => $izin)
                        <tr class="transition-colors hover:bg-slate-50/60">
                            <td class="px-4 py-3 text-center text-slate-500 font-semibold">{{ $idx + 1 }}</td>
                            <td class="px-4 py-3">
                                <div class="font-bold text-slate-800 truncate max-w-[150px]" title="{{ $izin->user?->nama }}">
                                    {{ $izin->user?->nama ?? 'Guru Tidak Ditemukan' }}
                                </div>
                                <div class="text-xs text-slate-400">{{ $izin->user?->nip ? 'NIP: ' . $izin->user->nip : 'Non-NIP' }}</div>
                            </td>
                            <td class="px-4 py-3 font-semibold text-slate-700">
                                {{ $izin->tanggal ? $izin->tanggal->translatedFormat('d F Y') : '-' }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full bg-slate-100 text-slate-700 border border-slate-200 px-2.5 py-1 text-xs font-semibold">
                                    {{ $izin->kategori_izin_label ?? ucfirst(str_replace('_', ' ', $izin->kategori_izin ?? 'Izin')) }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-xs text-slate-600 truncate max-w-[220px]" title="{{ $izin->alasan ?? $izin->keterangan }}">
                                    {{ $izin->alasan ?? $izin->keterangan ?? '-' }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-xs text-slate-500 truncate max-w-[180px]" title="{{ $izin->tugas_siswa }}">
                                    {{ $izin->tugas_siswa ?? '-' }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold border {{ $twBadgeClass($izin->status_badge) }}">
                                    {{ $izin->status_label }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-slate-500">
                                <div class="flex flex-col items-center justify-center">
                                    <i class="bi bi-inbox text-2xl text-slate-300 mb-1.5"></i>
                                    <p class="text-xs text-slate-500 font-medium mb-0.5">Belum ada riwayat pengajuan izin guru.</p>
                                    <p class="text-[11px] text-slate-400 mb-0">Permohonan izin atau cuti yang diajukan akan tampil di sini.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection