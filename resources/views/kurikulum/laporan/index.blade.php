@extends('layouts.app')

@section('title', 'Laporan KBM - Kurikulum')

@section('content')
<div class="container-fluid px-0">

    {{-- Page Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-800 mb-1 tracking-tight">
                Laporan KBM
            </h2>
            <p class="text-sm text-slate-500 mb-0">
                Rekapitulasi keterlaksanaan Kegiatan Belajar Mengajar per tanggal, kelas, guru, dan mata pelajaran.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('kurikulum.laporan.print', request()->query()) }}"
               class="h-9 px-3.5 text-sm flex items-center gap-2 font-medium rounded-lg border border-red-200 text-red-600 bg-red-50/60 hover:bg-red-100/80 transition-colors shadow-sm text-decoration-none">
                <i class="bi bi-file-earmark-pdf"></i> Download PDF
            </a>
            <a href="{{ route('kurikulum.laporan.excel', request()->query()) }}"
               class="h-9 px-3.5 text-sm flex items-center gap-2 font-medium rounded-lg border border-emerald-200 text-emerald-700 bg-emerald-50/60 hover:bg-emerald-100/80 transition-colors shadow-sm text-decoration-none">
                <i class="bi bi-file-earmark-excel"></i> Export Excel
            </a>
        </div>
    </div>

    {{-- Filter Card --}}
    <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-sm mb-6">
        <form method="GET" action="{{ route('kurikulum.laporan.index') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 items-end" id="formLaporanFilter">
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Tanggal Mulai</label>
                <input type="date" name="tanggal_mulai" value="{{ old('tanggal_mulai', $mulai) }}"
                       class="w-full min-w-[140px] rounded-lg border border-slate-200 bg-slate-50/40 pl-3 pr-2 py-1.5 text-sm text-slate-700 focus:bg-white focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
                       onchange="this.form.submit()">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Tanggal Selesai</label>
                <input type="date" name="tanggal_selesai" value="{{ old('tanggal_selesai', $selesai) }}"
                       class="w-full min-w-[140px] rounded-lg border border-slate-200 bg-slate-50/40 pl-3 pr-2 py-1.5 text-sm text-slate-700 focus:bg-white focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
                       onchange="this.form.submit()">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Tingkat</label>
                <select name="tingkat" class="w-full rounded-lg border border-slate-200 bg-slate-50/40 px-3 py-1.5 text-sm text-slate-700 focus:bg-white focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500" onchange="this.form.submit()">
                    <option value="">Semua Tingkat</option>
                    @foreach($tingkatList as $tgl)
                        <option value="{{ $tgl }}" {{ $tingkatInput == $tgl ? 'selected' : '' }}>{{ $tgl }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Kelas</label>
                <select name="id_kelas" class="w-full rounded-lg border border-slate-200 bg-slate-50/40 px-3 py-1.5 text-sm text-slate-700 focus:bg-white focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500" onchange="this.form.submit()">
                    <option value="">Semua Kelas</option>
                    @foreach($kelasList as $kelas)
                        <option value="{{ $kelas->id }}" {{ $idKelasInput == $kelas->id ? 'selected' : '' }}>
                            {{ $kelas->nama_lengkap }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Guru</label>
                <select name="id_guru" class="w-full rounded-lg border border-slate-200 bg-slate-50/40 px-3 py-1.5 text-sm text-slate-700 focus:bg-white focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500" onchange="this.form.submit()">
                    <option value="">Semua Guru</option>
                    @foreach($guruList as $guru)
                        <option value="{{ $guru->id }}" {{ $idGuruInput == $guru->id ? 'selected' : '' }}>
                            {{ $guru->nama }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Mata Pelajaran</label>
                <select name="id_mapel" class="w-full rounded-lg border border-slate-200 bg-slate-50/40 px-3 py-1.5 text-sm text-slate-700 focus:bg-white focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500" onchange="this.form.submit()">
                    <option value="">Semua Mapel</option>
                    @foreach($mapelList as $mapel)
                        <option value="{{ $mapel->id }}" {{ $idMapelInput == $mapel->id ? 'selected' : '' }}>
                            {{ $mapel->nama_mapel }}
                        </option>
                    @endforeach
                </select>
            </div>
            {{-- Reset Filter --}}
            @if(request()->hasAny(['tanggal_mulai','tanggal_selesai','tingkat','id_kelas','id_guru','id_mapel']))
            <div class="sm:col-span-2 md:col-span-3 lg:col-span-6 flex justify-end pt-1">
                <a href="{{ route('kurikulum.laporan.index') }}"
                   class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-700 bg-slate-100 hover:bg-slate-200/80 border border-slate-200 rounded-lg px-3 py-1.5 transition-colors text-decoration-none"
                   title="Reset semua filter">
                    <i class="bi bi-x-circle"></i>
                    <span>Reset Filter</span>
                </a>
            </div>
            @endif
        </form>
    </div>

    {{-- Metric Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
        {{-- Card 1: Total Jam --}}
        <div class="bg-white border border-slate-200/80 rounded-xl shadow-sm p-4 flex flex-col justify-between">
            <div>
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">Total Jam KBM Terlaksana</div>
                <div class="text-3xl font-bold text-slate-800">{{ number_format($totalJamKBM) }}</div>
                <div class="text-xs text-slate-500 mt-1">sesi KBM yang tercatat</div>
            </div>
            <p class="text-xs text-slate-400 mt-3 pt-2 border-t border-slate-100 mb-0">{{ $periodeMulai }} – {{ $periodeSelesai }}</p>
        </div>

        {{-- Card 2: Kehadiran Guru --}}
        <div class="bg-white border border-slate-200/80 rounded-xl shadow-sm p-4 flex flex-col justify-between">
            <div>
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">Kehadiran Guru</div>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-bold text-emerald-600">{{ number_format($guruHadir) }}</span>
                    <span class="text-xs font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-full px-2 py-0.5">Hadir</span>
                </div>
                <div class="text-xs text-slate-500 mt-1">Izin/Sakit/Dinas: <strong>{{ number_format($guruTidakHadir) }}</strong></div>
            </div>
            <p class="text-xs text-slate-500 mt-3 pt-2 border-t border-slate-100 mb-0">
                Izin: {{ number_format($guruIzin) }} &middot; Sakit: {{ number_format($guruSakit) }} &middot; Dinas: {{ number_format($guruDinas) }}
            </p>
        </div>

        {{-- Card 3: Jurnal Terisi --}}
        <div class="bg-white border border-slate-200/80 rounded-xl shadow-sm p-4 flex flex-col justify-between">
            <div>
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">Jurnal Mengajar Terisi</div>
                <div class="text-3xl font-bold text-amber-500">{{ number_format($totalJurnalTerisi) }}</div>
                <div class="text-xs text-slate-500 mt-1">jurnal dengan materi terisi</div>
            </div>
            <p class="text-xs text-slate-500 mt-3 pt-2 border-t border-slate-100 mb-0">
                {{ $totalJamKBM > 0 ? number_format(($totalJurnalTerisi / $totalJamKBM) * 100, 1) : 0 }}% dari total sesi
            </p>
        </div>
    </div>

    {{-- Tabel Rekapitulasi --}}
    <div class="table-card-custom mb-4">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h5 class="fw-bold text-dark mb-0">Rekapitulasi KBM</h5>
            <span class="text-muted small">Menampilkan {{ $daftarJurnal->total() }} baris rekapitulasi (Total {{ number_format($totalJamKBM) }} sesi jam KBM)</span>
        </div>
        <div class="table-responsive w-full overflow-x-auto">
            <table class="table table-custom align-middle mb-0 min-w-full">
                <thead>
                    <tr>
                        <th>TANGGAL</th>
                        <th class="whitespace-nowrap">JAM KE-</th>
                        <th>KELAS</th>
                        <th>GURU</th>
                        <th>MATA PELAJARAN</th>
                        <th>MATERI / JURNAL</th>
                        <th class="text-center whitespace-nowrap">STATUS KEHADIRAN</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($daftarJurnal as $item)
                        @php
                            $jurnal = $item->jurnal;
                            $jadwal = $jurnal->jadwalPelajaran;
                            $statusClass = match($jurnal->status_kehadiran) {
                                'Izin'       => 'bg-warning-subtle text-warning-emphasis border-warning-subtle',
                                'Sakit'      => 'bg-danger-subtle text-danger border-danger-subtle',
                                'Disposisi'  => 'bg-info-subtle text-info-emphasis border-info-subtle',
                                default      => 'bg-success-subtle text-success border-success-subtle',
                            };
                        @endphp
                        <tr>
                            <td class="fw-semibold text-dark text-nowrap">
                                <div>{{ $jurnal->tanggal->translatedFormat('d/m/Y') }}</div>
                                <small class="text-muted">{{ $jurnal->tanggal->translatedFormat('l') }}</small>
                            </td>
                            <td class="text-nowrap">
                                <div class="flex flex-col items-start gap-1">
                                    <span class="font-semibold text-slate-800 text-sm">{{ $item->label_jam_ke }}</span>
                                    @if($item->rentang_waktu)
                                        <span class="text-xs text-slate-500">{{ $item->rentang_waktu }}</span>
                                    @endif
                                    @if($item->total_jam > 1)
                                        <span class="inline-flex items-center bg-blue-50 text-blue-700 font-semibold border border-blue-200/80 px-2 py-0.5 rounded-full text-xs">
                                            {{ $item->total_jam }} Jam KBM
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <span class="fw-semibold text-dark">{{ $jadwal?->kelas?->nama_kelas_lengkap ?? $jadwal?->kelas?->nama_kelas ?? '-' }}</span>
                            </td>
                            <td>
                                <div class="fw-medium text-dark">
                                    {{ $jurnal->guru?->nama ?? $jadwal?->guru?->nama ?? '-' }}
                                </div>
                                @if($jurnal->guruPengganti)
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-1 small mt-1">
                                        <i class="bi bi-person-fill-gear me-1"></i>{{ $jurnal->guruPengganti->nama }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                <span class="text-secondary">{{ $jadwal?->mapel?->nama_mapel ?? '-' }}</span>
                            </td>
                            <td style="max-width: 260px;">
                                @if($jurnal->materi)
                                    <div class="text-dark fw-medium text-wrap">{{ $jurnal->materi }}</div>
                                    @if($jurnal->catatan_kejadian)
                                        <small class="text-muted d-block text-truncate" style="max-width: 240px;">Catatan: {{ $jurnal->catatan_kejadian }}</small>
                                    @endif
                                @else
                                    <span class="text-muted">Belum diisi materi</span>
                                @endif
                            </td>
                            <td class="text-center whitespace-nowrap">
                                <span class="badge border rounded-pill px-2 py-1 small {{ $statusClass }}">
                                    {{ $jurnal->status_kehadiran ?? 'Hadir' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                                Belum ada data jurnal pada rentang filter yang dipilih.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($daftarJurnal->hasPages())
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-4 pt-3 border-top">
                <div class="text-muted small mb-3 mb-md-0">
                    Menampilkan <strong>{{ $daftarJurnal->firstItem() ?? 0 }}</strong>-<strong>{{ $daftarJurnal->lastItem() ?? 0 }}</strong> dari <strong>{{ $daftarJurnal->total() }}</strong> baris rekapitulasi (Total {{ number_format($totalJamKBM) }} sesi jam KBM)
                </div>
                {{ $daftarJurnal->links() }}
            </div>
        @endif
    </div>

</div>
@endsection