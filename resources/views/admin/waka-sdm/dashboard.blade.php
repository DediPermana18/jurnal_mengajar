@extends('layouts.app')

@section('title', 'Dashboard Waka SDM - WebJournal')

@section('content')
<div class="container-fluid min-h-full bg-slate-50/70 px-0">

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
        <div class="inline-flex flex-wrap items-center gap-1 rounded-xl bg-slate-200/60 p-1">
            <a href="{{ route('waka-sdm.rekap-izin') }}"
               class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-slate-600 transition-all hover:bg-white/70 hover:text-blue-600">
                <i class="bi bi-calendar2-check"></i> Rekap Izin & Cuti
            </a>
            <a href="{{ route('waka-sdm.rekap-presensi-guru') }}"
               class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-semibold text-blue-600 shadow-sm transition-all hover:shadow-md">
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
        <div class="bg-white border border-slate-200/80 rounded-xl shadow-sm hover:shadow-md transition-all p-4 sm:p-5 flex flex-col justify-between">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Guru Hadir Hari Ini</p>
                    <div class="flex items-baseline gap-1.5 mt-1">
                        <span class="text-2xl sm:text-3xl font-bold text-slate-800">{{ $totalGuruHadirHariIni }}</span>
                        <span class="text-xs sm:text-sm text-slate-400">/ {{ $totalGuruTerjadwalHariIni }} Terjadwal</span>
                    </div>
                </div>
                <div class="flex items-center justify-center w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-emerald-100 text-emerald-600 text-lg sm:text-xl shrink-0">
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
        <div class="bg-white border border-slate-200/80 rounded-xl shadow-sm hover:shadow-md transition-all p-4 sm:p-5 flex flex-col justify-between">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Guru Izin / Sakit / Cuti</p>
                    <div class="flex items-baseline gap-1.5 mt-1">
                        <span class="text-2xl sm:text-3xl font-bold text-slate-800">{{ $totalGuruIzinHariIni }}</span>
                        <span class="text-xs sm:text-sm text-slate-400">Guru</span>
                    </div>
                </div>
                <div class="flex items-center justify-center w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-amber-100 text-amber-600 text-lg sm:text-xl shrink-0">
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
        <div class="bg-white border border-slate-200/80 rounded-xl shadow-sm hover:shadow-md transition-all p-4 sm:p-5 flex flex-col justify-between">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Kelas Kosong / Belum Diisi</p>
                    <div class="flex items-baseline gap-1.5 mt-1">
                        <span class="text-2xl sm:text-3xl font-bold text-slate-800">{{ $totalKelasKosong }}</span>
                        <span class="text-xs sm:text-sm text-slate-400">Kelas</span>
                    </div>
                </div>
                <div class="flex items-center justify-center w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-rose-100 text-rose-600 text-lg sm:text-xl shrink-0">
                    <i class="bi bi-door-closed-fill"></i>
                </div>
            </div>
            <div class="flex items-center justify-between text-xs text-slate-500 pt-2">
                <span>{{ $sesiKosongHariIni }} Sesi Belum Terisi</span>
                @if($totalKelasKosong > 0)
                    <span class="inline-flex items-center rounded-full bg-rose-50 text-rose-600 border border-rose-200 px-2 py-0.5 text-xs font-semibold">
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
        <div class="bg-white border border-slate-200/80 rounded-xl shadow-sm hover:shadow-md transition-all p-4 sm:p-5 flex flex-col justify-between">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Kehadiran Bulan {{ \Carbon\Carbon::now()->translatedFormat('F') }}</p>
                    <div class="flex items-baseline gap-1.5 mt-1">
                        <span class="text-2xl sm:text-3xl font-bold text-slate-800">{{ $persentaseKehadiranBulanIni }}%</span>
                    </div>
                </div>
                <div class="flex items-center justify-center w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-blue-100 text-blue-600 text-lg sm:text-xl shrink-0">
                    <i class="bi bi-award-fill"></i>
                </div>
            </div>
            <div class="pt-2">
                <div class="w-full h-1.5 bg-slate-100 rounded-full overflow-hidden mb-2">
                    <div class="h-full bg-blue-500 rounded-full" style="width: {{ $persentaseKehadiranBulanIni }}%"></div>
                </div>
                <div class="flex items-center justify-between text-xs text-slate-500">
                    <span>Target: 95.0%</span>
                    <span class="font-semibold text-blue-600">Kinerja Baik</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ============================================================== --}}
    {{-- 4. MAIN CONTENT GRID (WIDGETS MONITORING)                     --}}
    {{-- ============================================================== --}}
    {{-- Stacked full-width (1 kolom): "Guru Tidak Hadir" di atas, "Pantau
         Kelas Kosong" di bawahnya. Leaktrasi tinggi otomatis per card karena
         tiap baris grid hanya berisi 1 card. --}}
    <div class="grid grid-cols-1 w-full gap-6 mb-6">

        {{-- Widget Atas: Guru Tidak Hadir / Izin Hari Ini (full-width) --}}
        <div class="w-full bg-white border border-slate-200/80 rounded-xl shadow-sm hover:shadow-md transition-all overflow-hidden flex flex-col h-auto">
            <div class="shrink-0 flex items-center justify-between flex-wrap gap-2 px-5 py-4 border-b border-slate-100">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-amber-50 text-amber-600">
                            <i class="bi bi-person-x text-base"></i>
                        </span>
                        <h5 class="text-base font-bold text-slate-800">Guru Tidak Hadir / Izin Hari Ini</h5>
                    </div>
                    <p class="text-xs text-slate-500 mt-1">Daftar guru yang berhalangan hadir dan status penugasan guru pengganti.</p>
                </div>
                <span class="inline-flex items-center rounded-full px-3 py-1.5 text-xs font-semibold {{ $guruIzinHariIniList->isEmpty() ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-600 border border-amber-200' }}">
                    {{ $guruIzinHariIniList->count() }} Guru
                </span>
            </div>

            @if($guruIzinHariIniList->isEmpty())
                <div class="flex-1 flex flex-col items-center justify-center text-center py-10 px-6">
                    <div class="flex items-center justify-center w-14 h-14 rounded-2xl bg-emerald-100 text-emerald-600 mb-4">
                        <i class="bi bi-calendar2-check text-2xl"></i>
                    </div>
                    <h6 class="text-sm font-bold text-slate-800 mb-1">Semua guru terjadwal hadir hari ini.</h6>
                    <p class="text-xs text-slate-500 mb-0">Tidak ada pengajuan izin, sakit, atau dinas luar yang aktif untuk hari ini.</p>
                </div>
            @else
                {{-- Daftar izin bisa panjang. min-h-0 + overflow-auto TIDAK membuat
                     card ikut memendek karena tinggi baris grid ditentukan oleh isi;
                     ini hanya jaring pengaman bila list-grow sangat panjang. --}}
                <div class="flex-1 min-h-0 overflow-auto custom-scrollbar">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 z-10">
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

            <div class="shrink-0 flex items-center justify-between text-xs border-t border-slate-100 pt-3 mt-auto px-5 pb-4">
                <span class="text-slate-500 text-[11px] sm:text-xs truncate pr-2">Diperbarui real-time dari database izin</span>
                <a href="{{ route('waka-sdm.rekap-izin') }}" class="font-semibold text-sky-600 transition-colors hover:text-sky-700 shrink-0 whitespace-nowrap">
                    Kelola Rekap Izin &rarr;
                </a>
            </div>
        </div>

        {{-- Widget Bawah: Pantauan Kelas Kosong (Jam Ini) — full-width.
             Paginasi 5 data/halaman lewat mini arrow di header (query param
             `page`) supaya card tidak memanjang terlalu jauh ke bawah. --}}
        <div class="w-full bg-white border border-slate-200/80 rounded-xl shadow-sm hover:shadow-md transition-all overflow-hidden flex flex-col h-auto">
            <div class="shrink-0 flex items-center justify-between flex-wrap gap-2 px-5 py-4 border-b border-slate-100">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-red-50 text-red-600">
                            <i class="bi bi-door-closed text-base"></i>
                        </span>
                        <h5 class="text-base font-bold text-slate-800">Pantauan Kelas Kosong (Jam Ini)</h5>

                        {{-- ============ PENCARIAN (query param `q`) ============
                             Form GET ke route yang sama, sengaja TIDAK membawa
                             `page` supaya tiap pencarian baru kembali ke halaman 1. --}}
                        <form method="GET" action="{{ route('waka-sdm.dashboard') }}" role="search" class="relative shrink-0">
                            <i class="bi bi-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>

                            <input type="search"
                                   name="q"
                                   value="{{ $kelasKosongSearch }}"
                                   placeholder="Cari guru / kelas..."
                                   aria-label="Cari sesi kelas kosong berdasarkan nama guru, kelas, atau mata pelajaran"
                                   class="search-clean w-44 sm:w-60 pl-7 pr-7 py-1.5 text-xs rounded-lg border border-slate-200 bg-white text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-400 transition-colors">
                            <noscript>
                                <button type="submit" class="sr-only">Cari</button>
                            </noscript>

                            @if($kelasKosongSearch !== '')
                                <a href="{{ route('waka-sdm.dashboard') }}"
                                   class="absolute right-2 top-1/2 -translate-y-1/2 inline-flex items-center justify-center w-4 h-4 rounded-full text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
                                   title="Hapus pencarian" aria-label="Hapus pencarian">
                                    <i class="bi bi-x-lg text-[10px]"></i>
                                </a>
                            @endif
                        </form>

                        {{-- Mini navigasi halaman: query param `page`, tanpa full reload
                             karena hanya mengganti angka pada URL. --}}
                        @if($kelasKosongHariIniList->hasPages())
                            <span class="inline-flex items-center gap-1">
                                @if($kelasKosongHariIniList->onFirstPage())
                                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-md border border-slate-200 text-slate-300 text-[11px] cursor-not-allowed"
                                          title="Sudah di halaman pertama" aria-disabled="true">
                                        <i class="bi bi-chevron-left"></i>
                                    </span>
                                @else
                                    <a href="{{ $kelasKosongHariIniList->previousPageUrl() }}"
                                       class="inline-flex items-center justify-center w-6 h-6 rounded-md border border-slate-200 text-slate-500 hover:bg-slate-50 hover:text-sky-600 transition-colors"
                                       title="Halaman sebelumnya" aria-label="Halaman sebelumnya">
                                        <i class="bi bi-chevron-left"></i>
                                    </a>
                                @endif

                                <span class="text-[11px] font-semibold text-slate-500 tabular-nums px-1 whitespace-nowrap">
                                    {{ $kelasKosongHariIniList->currentPage() }}/{{ $kelasKosongHariIniList->lastPage() }}
                                </span>

                                @if($kelasKosongHariIniList->hasMorePages())
                                    <a href="{{ $kelasKosongHariIniList->nextPageUrl() }}"
                                       class="inline-flex items-center justify-center w-6 h-6 rounded-md border border-slate-200 text-slate-500 hover:bg-slate-50 hover:text-sky-600 transition-colors"
                                       title="Halaman berikutnya" aria-label="Halaman berikutnya">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                @else
                                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-md border border-slate-200 text-slate-300 text-[11px] cursor-not-allowed"
                                          title="Sudah di halaman terakhir" aria-disabled="true">
                                        <i class="bi bi-chevron-right"></i>
                                    </span>
                                @endif
                            </span>
                        @endif
                    </div>
                    <p class="text-xs text-slate-500 mt-1">
                        Sesi KBM yang sedang berlangsung/terjadwal tapi Jurnal KBM-nya belum diisi guru.
                        @if($kelasKosongJumlahBaris < $kelasKosongSesiTersaring)
                            <span class="text-slate-400">&middot; {{ $kelasKosongSesiTersaring }} sesi diringkas jadi {{ $kelasKosongJumlahBaris }} baris.</span>
                        @endif
                    </p>
                </div>
                {{-- Badge menghitung SESI (JP), bukan baris: grouping hanya
                     meringkas tampilan, angka total tidak boleh ikut menyusut. --}}
                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold border {{ $kelasKosongSearch !== '' ? 'bg-sky-50 text-sky-700 border-sky-200' : ($kelasKosongTotal > 0 ? 'bg-red-50 text-red-600 border-red-200' : 'bg-emerald-50 text-emerald-600 border-emerald-200') }}">
                    @if($kelasKosongSearch !== '')
                        {{ $kelasKosongSesiTersaring }} dari {{ $kelasKosongTotal }} Sesi Belum Diisi
                    @else
                        {{ $kelasKosongTotal }} Sesi Belum Diisi
                    @endif
                </span>
            </div>

            @if($kelasKosongHariIniList->total() === 0)
                <div class="flex-1 flex flex-col items-center justify-center text-center py-6 px-4">
                    @if($kelasKosongSearch !== '')
                        {{-- Hasil filter kosong. WAJIB dibedakan dari "tidak ada kelas
                             kosong sama sekali" — kalau tidak, user dikira diberi tahu
                             semua sesi terisi padahal masih ada yang kosong. --}}
                        <div class="flex items-center justify-center w-12 h-12 rounded-full bg-sky-50 text-sky-600 mb-3">
                            <i class="bi bi-search text-xl"></i>
                        </div>
                        <h6 class="text-sm font-bold text-slate-800 mb-1">Tidak ada sesi yang cocok dengan &ldquo;{{ $kelasKosongSearch }}&rdquo;.</h6>
                        <p class="text-xs text-slate-500 mb-0">
                            Dari {{ $kelasKosongTotal }} sesi belum diisi hari ini, tidak ada yang cocok.
                            <a href="{{ route('waka-sdm.dashboard', $kelasKosongLinkParams) }}" class="text-sky-600 font-semibold hover:underline">Hapus pencarian</a>
                        </p>
                    @else
                        <div class="flex items-center justify-center w-12 h-12 rounded-full bg-emerald-50 text-emerald-600 mb-3">
                            <i class="bi bi-check2-all text-xl"></i>
                        </div>
                        <h6 class="text-sm font-bold text-slate-800 mb-1">Semua sesi KBM hari ini sudah terisi dengan baik.</h6>
                        <p class="text-xs text-slate-500 mb-0">Tidak ada kelas kosong atau jurnal mengajar yang terlewatkan hari ini.</p>
                    @endif
                </div>
            @else
                {{-- Full-width: plenty ruang per kolom, jadi sel bisa lega dan
                     teks tetap satu baris (whitespace-nowrap) tanpa dipotong.
                     Batas panjang card tetap dijaga paginasi 5 data/halaman. --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-slate-50 text-left">
                                <th class="px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-slate-500 whitespace-nowrap">Jam / Sesi</th>
                                <th class="px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-slate-500">Kelas &amp; Mapel</th>
                                <th class="px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-slate-500">Guru Pengajar</th>
                                <th class="px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-slate-500 text-right whitespace-nowrap">Aksi Cepat</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($kelasKosongHariIniList as $item)
                                <tr class="transition-colors hover:bg-slate-50/60">
                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                        <span class="inline-flex items-center rounded-lg bg-slate-100 text-slate-700 border border-slate-200 px-2 py-0.5 text-xs font-bold">
                                            {{ $item->jam_ke_label }}
                                        </span>
                                        <div class="text-xs text-slate-400 tabular-nums mt-0.5">
                                            {{ $item->waktu_label }}
                                        </div>
                                        @if($item->jumlah_sesi > 1)
                                            <div class="text-[10px] text-slate-400 mt-0.5">
                                                {{ $item->jumlah_sesi }} sesi digabung
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <div class="font-bold text-sm text-slate-800 whitespace-nowrap">{{ $item->kelas?->nama_kelas_lengkap ?? $item->kelas?->nama_kelas ?? '-' }}</div>
                                        <div class="text-xs text-slate-500 whitespace-nowrap" title="{{ $item->mapel?->nama_mapel }}">
                                            {{ $item->mapel?->nama_mapel ?? '-' }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <div class="text-sm font-semibold text-slate-700 whitespace-nowrap" title="{{ $item->guru?->nama ?? 'Guru Tidak Ditemukan' }}">
                                            {{ $item->guru?->nama ?? 'Guru Tidak Ditemukan' }}
                                        </div>
                                        @if($item->izin)
                                            <span class="inline-flex items-center rounded-full bg-amber-50 text-amber-600 border border-amber-200 mt-0.5 px-2 py-0.5 text-xs font-semibold whitespace-nowrap">
                                                <i class="bi bi-exclamation-triangle mr-0.5"></i> Izin
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-red-50 text-red-600 border border-red-200 mt-0.5 px-2 py-0.5 text-xs font-semibold whitespace-nowrap">
                                                Belum Hadir
                                            </span>
                                        @endif
                                    </td>
                                    {{-- Aksi: tombol ringkas w-7 h-7 dengan tooltip title,
                                         tetap hemat tempat meski kolom ini lega. --}}
                                    <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                        @if($item->waUrl)
                                            <a href="{{ $item->waUrl }}" target="_blank" rel="noopener"
                                               class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-emerald-500 text-white shadow-sm transition-colors hover:bg-emerald-600"
                                               title="Ingatkan {{ $item->guru?->nama ?? 'guru' }} via WhatsApp"
                                               aria-label="Ingatkan {{ $item->guru?->nama ?? 'guru' }} via WhatsApp">
                                                <i class="bi bi-whatsapp text-sm"></i>
                                            </a>
                                        @else
                                            <a href="{{ route('waka-sdm.rekap-izin') }}"
                                               class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-white border border-slate-200 text-amber-500 transition-colors hover:bg-amber-50"
                                               title="Hubungi Piket (Belum ada nomor WA)"
                                               aria-label="Hubungi Piket">
                                                <i class="bi bi-telephone-fill text-sm"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="shrink-0 flex items-center justify-between text-xs border-t border-slate-100 pt-3 mt-auto px-5 pb-4">
                <span class="text-slate-500 text-[11px] sm:text-xs truncate pr-2">Kirim pengingat atau koordinasikan dengan Tim Piket</span>
                <a href="{{ route('kurikulum.laporan.index') }}" class="font-semibold text-sky-600 transition-colors hover:text-sky-700 shrink-0 whitespace-nowrap">
                    Lihat Semua Sesi &rarr;
                </a>
            </div>
        </div>
    </div>

    {{-- ============================================================== --}}
    {{-- 5. TABLE SECTION: PENGAJUAN IZIN & CUTI GURU TERBARU         --}}
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