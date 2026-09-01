@extends('layouts.app')

@section('title', 'Dashboard Guru - WebJournal')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3 md:mb-4 gap-2 md:gap-3">
        <div class="min-w-0">
            <h2 class="fw-black text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 800; font-size: 1.5rem;">
                Dashboard Guru
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.85rem;">
                Selamat datang kembali, <strong>{{ auth()->user()->nama ?? 'Guru Pengajar' }}</strong>! Siap untuk mengajar hari ini?
            </p>
        </div>
        <div class="flex-shrink-0 mt-2 mt-md-0">
            <a href="{{ route('guru.jurnal') }}" class="btn btn-primary rounded-3 px-3 py-2 fw-semibold shadow-sm text-xs md:text-sm">
                <i class="bi bi-pencil-square me-1"></i> Isi Jurnal Hari Ini
            </a>
        </div>
    </div>

    <!-- Stat Cards Summary -->
    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 md:gap-4 mb-3 md:mb-4">
        <div>
            <div class="stat-card-custom p-3 md:p-4 h-100">
                <div class="stat-card-title text-uppercase text-xs md:text-sm truncate mb-1 md:mb-2" title="Jadwal Mengajar Hari Ini">Jadwal Mengajar Hari Ini</div>
                <div class="stat-number-large text-2xl md:text-4xl text-primary mb-1">{{ $jadwalHariIni->count() }}</div>
                <div class="stat-card-label text-xs md:text-sm truncate" title="Jam Pelajaran">Jam Pelajaran</div>
                <p class="stat-card-subtext text-xs truncate mb-0 mt-1" title="{{ $jadwalHariIni->pluck('kelas.nama_kelas')->filter()->unique()->values()->implode(' & ') ?: 'Tidak ada jadwal' }}">
                    {{ $jadwalHariIni->pluck('kelas.nama_kelas')->filter()->unique()->values()->implode(' & ') ?: 'Tidak ada jadwal' }}
                </p>
            </div>
        </div>
        <div>
            <div class="stat-card-custom p-3 md:p-4 h-100">
                <div class="stat-card-title text-uppercase text-xs md:text-sm truncate mb-1 md:mb-2" title="Status Jurnal">Status Jurnal</div>
                <div class="stat-number-large text-2xl md:text-4xl text-success mb-1">{{ count($jurnalFilledIds) }}/{{ $jadwalHariIni->count() }}</div>
                <div class="stat-card-label text-xs md:text-sm truncate" title="Jurnal Terisi">Jurnal Terisi</div>
                <p class="stat-card-subtext text-xs truncate mb-0 mt-1">{{ $jadwalHariIni->count() - count($jurnalFilledIds) }} sesi menanti pengisian</p>
            </div>
        </div>
        <div class="col-span-2 md:col-span-1">
            <div class="stat-card-custom p-3 md:p-4 h-100">
                <div class="stat-card-title text-uppercase text-xs md:text-sm truncate mb-1 md:mb-2" title="Siswa Dispen Hari Ini">Siswa Dispen Hari Ini</div>
                <div class="stat-number-large text-2xl md:text-4xl text-info mb-1">{{ $dispensasiHariIni->count() }}</div>
                <div class="stat-card-label text-xs md:text-sm truncate" title="{{ $jumlahDispenDisetujui }} Disetujui">{{ $jumlahDispenDisetujui }} Disetujui</div>
                <p class="stat-card-subtext text-xs truncate mb-0 mt-1">Terkait mapel / jam mengajar Anda</p>
            </div>
        </div>
    </div>

    <!-- Widget: Daftar Siswa Dispen Hari Ini (Guru Mapel) -->
    <div class="table-card-custom mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="fw-bold text-dark mb-0">
                <i class="bi bi-person-dash-fill text-info me-2"></i> Daftar Siswa Dispen Hari Ini
            </h5>
            <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle rounded-pill px-3 py-1">
                {{ \Carbon\Carbon::parse($today)->locale('id')->translatedFormat('d F Y') }}
            </span>
        </div>

        @if($dispensasiHariIni->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="bi bi-emoji-smile fs-1 d-block mb-2"></i>
                Tidak ada siswa di-dispensasi pada jam pelajaran Anda hari ini.
            </div>
        @else
            <div class="overflow-x-auto w-full rounded-lg">
                <table class="table table-custom align-middle mb-0 min-w-full">
                    <thead>
                        <tr>
                            <th class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">Nama Siswa</th>
                            <th class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">Kelas</th>
                            <th class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">Jam Ke-</th>
                            <th class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">Mapel / Guru</th>
                            <th class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">Alasan</th>
                            <th class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($dispensasiHariIni as $dispen)
                            <tr>
                                <td class="fw-semibold whitespace-nowrap px-3 py-2 text-xs md:text-sm">{{ $dispen->siswa?->nama ?? '-' }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-xs md:text-sm"><span class="badge bg-light text-dark border text-xs md:text-sm">{{ $dispen->siswa?->kelas?->nama ?? '-' }}</span></td>
                                <td class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">{{ $dispen->jam_ke_label }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">
                                    @if($dispen->jadwal)
                                        <div>{{ $dispen->jadwal->mapel?->nama_mapel ?? '-' }}</div>
                                        <small class="text-muted">{{ $dispen->jadwal->guru?->nama ?? '-' }}</small>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-muted whitespace-nowrap px-3 py-2 text-xs md:text-sm">{{ $dispen->alasan }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-xs md:text-sm"><span class="badge {{ $dispen->status_badge }} rounded-pill px-2.5 py-1.5 text-xs md:text-sm">{{ $dispen->status_label }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <!-- Quick Info Card: Jadwal Mengajar Hari Ini -->
    <div class="table-card-custom">
        <h5 class="fw-bold text-dark mb-3">Jadwal Mengajar Hari Ini</h5>
        <div class="overflow-x-auto w-full rounded-lg">
            <table class="table table-custom align-middle mb-0 min-w-full">
                <thead>
                    <tr>
                        <th class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">Jam ke-</th>
                        <th class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">Waktu</th>
                        <th class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">Kelas</th>
                        <th class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">Mata Pelajaran</th>
                        <th class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">Status Jurnal</th>
                        <th class="whitespace-nowrap px-3 py-2 text-xs md:text-sm text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($jadwalHariIni as $item)
                        @php
                            $sudahTerisi = in_array($item->id, $jurnalFilledIds, true);
                            $jurnalSesi  = $jurnalHariIniMap->get($item->id);
                            $jamP        = $item->jamPelajaran;
                            $waktuStr    = ($jamP && $jamP->jam_mulai && $jamP->jam_selesai)
                                ? \Carbon\Carbon::parse($jamP->jam_mulai)->format('H:i') . ' - ' . \Carbon\Carbon::parse($jamP->jam_selesai)->format('H:i')
                                : '-';
                        @endphp
                        <tr>
                            <td class="whitespace-nowrap px-3 py-2 text-xs md:text-sm"><strong>{{ $jamP?->jam_ke ?? '-' }}</strong></td>
                            <td class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">{{ $waktuStr }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-xs md:text-sm"><span class="badge bg-light text-dark border text-xs md:text-sm">{{ $item->kelas?->nama_kelas ?? '-' }}</span></td>
                            <td class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">{{ $item->mapel?->nama_mapel ?? '-' }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">
                                @if($sudahTerisi)
                                    <span class="status-badge-terisi text-xs md:text-sm"><i class="bi bi-check-circle-fill"></i> Sudah Terisi</span>
                                @else
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-2.5 py-1.5 text-xs md:text-sm">
                                        <i class="bi bi-clock-history me-1"></i> Belum Terisi
                                    </span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 text-xs md:text-sm text-end">
                                <div class="flex items-center justify-center gap-2 whitespace-nowrap">
                                @if($sudahTerisi && $jurnalSesi)
                                    <a href="{{ route('guru.jurnal.show', $jurnalSesi->id) }}" class="btn btn-sm btn-outline-secondary rounded-2 text-xs md:text-sm">Lihat</a>
                                @else
                                    <a href="{{ route('guru.jurnal.form', $item->id) }}" class="btn btn-sm btn-primary rounded-2 text-xs md:text-sm">Isi Jurnal</a>
                                @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4 whitespace-nowrap px-3 py-2 text-xs md:text-sm">
                                Tidak ada jadwal mengajar hari ini ({{ $hari }}).
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection