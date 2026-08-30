@extends('layouts.app')

@section('title', 'Dashboard Guru - WebJournal')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-black text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 800; font-size: 1.75rem;">
                Dashboard Guru
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                Selamat datang kembali, <strong>{{ auth()->user()->nama ?? 'Guru Pengajar' }}</strong>! Siap untuk mengajar hari ini?
            </p>
        </div>
        <div>
            <a href="{{ route('guru.jurnal') }}" class="btn btn-primary rounded-3 px-3 py-2 fw-semibold shadow-sm">
                <i class="bi bi-pencil-square me-1"></i> Isi Jurnal Hari Ini
            </a>
        </div>
    </div>

    <!-- Stat Cards Summary -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card-custom">
                <div class="stat-card-title text-uppercase">Jadwal Mengajar Hari Ini</div>
                <div class="stat-number-large text-primary">{{ $jadwalHariIni->count() }}</div>
                <div class="stat-card-label">Jam Pelajaran</div>
                <p class="stat-card-subtext">
                    {{ $jadwalHariIni->pluck('kelas.nama_kelas')->filter()->unique()->values()->implode(' & ') ?: 'Tidak ada jadwal' }}
                </p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card-custom">
                <div class="stat-card-title text-uppercase">Status Jurnal</div>
                <div class="stat-number-large text-success">{{ count($jurnalFilledIds) }}/{{ $jadwalHariIni->count() }}</div>
                <div class="stat-card-label">Jurnal Terisi</div>
                <p class="stat-card-subtext">{{ $jadwalHariIni->count() - count($jurnalFilledIds) }} sesi menanti pengisian</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card-custom">
                <div class="stat-card-title text-uppercase">Siswa Dispen Hari Ini</div>
                <div class="stat-number-large text-info">{{ $dispensasiHariIni->count() }}</div>
                <div class="stat-card-label">{{ $jumlahDispenDisetujui }} Disetujui</div>
                <p class="stat-card-subtext">Terkait mapel / jam mengajar Anda</p>
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
            <div class="table-responsive">
                <table class="table table-custom align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Nama Siswa</th>
                            <th>Kelas</th>
                            <th>Jam Ke-</th>
                            <th>Mapel / Guru</th>
                            <th>Alasan</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($dispensasiHariIni as $dispen)
                            <tr>
                                <td class="fw-semibold">{{ $dispen->siswa?->nama ?? '-' }}</td>
                                <td><span class="badge bg-light text-dark border">{{ $dispen->siswa?->kelas?->nama ?? '-' }}</span></td>
                                <td>{{ $dispen->jam_ke_label }}</td>
                                <td>
                                    @if($dispen->jadwal)
                                        <div>{{ $dispen->jadwal->mapel?->nama_mapel ?? '-' }}</div>
                                        <small class="text-muted">{{ $dispen->jadwal->guru?->nama ?? '-' }}</small>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-muted">{{ $dispen->alasan }}</td>
                                <td><span class="badge {{ $dispen->status_badge }} rounded-pill px-3 py-1">{{ $dispen->status_label }}</span></td>
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
        <div class="table-responsive">
            <table class="table table-custom align-middle">
                <thead>
                    <tr>
                        <th>Jam ke-</th>
                        <th>Waktu</th>
                        <th>Kelas</th>
                        <th>Mata Pelajaran</th>
                        <th>Status Jurnal</th>
                        <th class="text-end">Aksi</th>
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
                            <td><strong>{{ $jamP?->jam_ke ?? '-' }}</strong></td>
                            <td>{{ $waktuStr }}</td>
                            <td><span class="badge bg-light text-dark border">{{ $item->kelas?->nama_kelas ?? '-' }}</span></td>
                            <td>{{ $item->mapel?->nama_mapel ?? '-' }}</td>
                            <td>
                                @if($sudahTerisi)
                                    <span class="status-badge-terisi"><i class="bi bi-check-circle-fill"></i> Sudah Terisi</span>
                                @else
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-3 py-1">
                                        <i class="bi bi-clock-history me-1"></i> Belum Terisi
                                    </span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if($sudahTerisi && $jurnalSesi)
                                    <a href="{{ route('guru.jurnal.show', $jurnalSesi->id) }}" class="btn btn-sm btn-outline-secondary rounded-2">Lihat</a>
                                @else
                                    <a href="{{ route('guru.jurnal.form', $item->id) }}" class="btn btn-sm btn-primary rounded-2">Isi Jurnal</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
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