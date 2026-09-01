@extends('layouts.app')

@section('title', 'Dashboard Wali Kelas - WebJournal')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3 md:mb-4 gap-2 md:gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 800; font-size: 1.5rem;">
                Dashboard Wali Kelas
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.85rem;">
                Ringkasan kegiatan & dispensasi siswa kelas bimbingan Anda.
            </p>
        </div>
        <div class="flex-shrink-0 mt-2 mt-md-0">
            <a href="{{ route('walikelas.rekap-absen') }}" class="btn btn-outline-secondary rounded-3 px-3 py-2 fw-semibold text-xs md:text-sm">
                <i class="bi bi-clipboard-data me-1"></i> Rekap Absen
            </a>
        </div>
    </div>

    @if($kelasSaya->isEmpty())
        <div class="table-card-custom text-center py-5">
            <i class="bi bi-inbox fs-1 text-muted d-block mb-3"></i>
            <h5 class="fw-bold text-dark mb-1">Belum Ada Kelas Bimbingan</h5>
            <p class="text-muted mb-0 small">
                Anda belum terdaftar sebagai Wali Kelas. Hubungi Admin untuk mengatur kelas bimbingan Anda.
            </p>
        </div>
    @else
        <!-- Stat Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-4 mb-3 md:mb-4">
            <div>
                <div class="stat-card-custom p-3 md:p-4 h-100">
                    <div class="stat-card-title text-uppercase text-xs md:text-sm truncate mb-1 md:mb-2" title="Total Siswa Bimbingan">Total Siswa Bimbingan</div>
                    <div class="stat-number-large text-2xl md:text-4xl text-primary mb-1">{{ $totalSiswa }}</div>
                    <div class="stat-card-label text-xs md:text-sm truncate" title="Siswa">Siswa</div>
                    <p class="stat-card-subtext text-xs truncate mb-0 mt-1" title="{{ $namaKelasSaya }}">{{ $namaKelasSaya }}</p>
                </div>
            </div>
            <div>
                <div class="stat-card-custom p-3 md:p-4 h-100">
                    <div class="stat-card-title text-uppercase text-xs md:text-sm truncate mb-1 md:mb-2" title="Siswa Terlambat">Siswa Terlambat</div>
                    <div class="stat-number-large text-2xl md:text-4xl text-danger mb-1">{{ $terlambatHariIni }}</div>
                    <div class="stat-card-label text-xs md:text-sm truncate" title="Hari Ini">Hari Ini</div>
                    <p class="stat-card-subtext text-xs truncate mb-0 mt-1">Dari catatan Satpam di gerbang</p>
                </div>
            </div>
            <div>
                <div class="stat-card-custom p-3 md:p-4 h-100">
                    <div class="stat-card-title text-uppercase text-xs md:text-sm truncate mb-1 md:mb-2" title="Siswa Perlu Perhatian">Siswa Perlu Perhatian</div>
                    <div class="stat-number-large text-2xl md:text-4xl text-warning mb-1">{{ $perluPerhatian }}</div>
                    <div class="stat-card-label text-xs md:text-sm truncate" title="Terlambat >3x / Berkasus">Terlambat >3x / Berkasus</div>
                    <p class="stat-card-subtext text-xs truncate mb-0 mt-1" title="{{ $namaKelasSaya }}">{{ $namaKelasSingkat }}</p>
                </div>
            </div>
            <div>
                <div class="stat-card-custom p-3 md:p-4 h-100">
                    <div class="stat-card-title text-uppercase text-xs md:text-sm truncate mb-1 md:mb-2" title="Siswa Dispen">Siswa Dispen</div>
                    <div class="stat-number-large text-2xl md:text-4xl text-info mb-1">{{ $jumlahDispen }}</div>
                    <div class="stat-card-label text-xs md:text-sm truncate" title="Hari Ini">Hari Ini</div>
                    <p class="stat-card-subtext text-xs truncate mb-0 mt-1">{{ $jumlahDisetujui }} disetujui</p>
                </div>
            </div>
        </div>

        <!-- Widget: Dispensasi Siswa Hari Ini -->
        <div class="table-card-custom mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h5 class="fw-bold text-dark mb-0">
                    <i class="bi bi-person-dash-fill text-info me-2"></i> Dispensasi Siswa Hari Ini
                </h5>
                <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle rounded-pill px-3 py-1">
                    {{ \Carbon\Carbon::parse($today)->locale('id')->translatedFormat('d F Y') }}
                </span>
            </div>

            @if($jumlahDispen === 0)
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-emoji-smile fs-1 d-block mb-2"></i>
                    Tidak ada siswa kelas bimbingan Anda yang di-dispensasi hari ini.
                </div>
            @else
                @foreach($dispensasiHariIni as $namaKelas => $items)
                    <div class="mb-4 {{ !$loop->last ? 'border-bottom pb-3' : '' }}">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="fw-bold text-dark">{{ $namaKelas }}</span>
                            <span class="badge bg-light text-dark border rounded-pill px-2">{{ $items->count() }} siswa</span>
                        </div>
                        <div class="overflow-x-auto w-full rounded-lg">
                            <table class="table table-custom align-middle mb-0 min-w-full">
                                <thead>
                                    <tr>
                                        <th class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">Nama Siswa</th>
                                        <th class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">Jam Ke-</th>
                                        <th class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">Alasan</th>
                                        <th class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">Guru Piket</th>
                                        <th class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($items as $dispen)
                                        <tr>
                                            <td class="fw-semibold whitespace-nowrap px-3 py-2 text-xs md:text-sm">{{ $dispen->siswa?->nama ?? '-' }}</td>
                                            <td class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">{{ $dispen->jam_ke_label }}</td>
                                            <td class="text-muted whitespace-nowrap px-3 py-2 text-xs md:text-sm">{{ $dispen->alasan }}</td>
                                            <td class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">{{ $dispen->guruPiket?->nama ?? '-' }}</td>
                                            <td class="whitespace-nowrap px-3 py-2 text-xs md:text-sm"><span class="badge {{ $dispen->status_badge }} rounded-pill px-2.5 py-1.5 text-xs md:text-sm">{{ $dispen->status_label }}</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    @endif
</div>
@endsection