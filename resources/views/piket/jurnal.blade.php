@extends('layouts.app')

@section('title', 'Jurnal KBM Harian - WebJournal')

@section('content')
<div class="container-fluid px-0">

    <!-- HEADER -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 800; font-size: 1.75rem;">
                Jurnal KBM Harian
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                Pantau jurnal kegiatan belajar mengajar per tanggal.
            </p>
        </div>

        <!-- Filter Tanggal -->
        <form method="GET" action="{{ route('piket.jurnal') }}" class="d-flex align-items-center gap-2">
            <label class="text-muted fw-semibold small mb-0"><i class="bi bi-calendar3 me-1"></i>Tanggal:</label>
            <input type="date"
                   name="tanggal"
                   value="{{ $tanggal }}"
                   max="{{ $today }}"
                   class="form-control form-control-sm rounded-3"
                   style="width: auto;"
                   onchange="this.form.submit()">
        </form>
    </div>

    <!-- INFO BAR: TANGGAL YANG DILIHAT -->
    <div class="alert border-0 rounded-4 mb-4 py-3 px-4 d-flex align-items-center gap-3
        {{ $tanggal === $today ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}"
         role="alert">
        <i class="bi {{ $tanggal === $today ? 'bi-unlock-fill' : 'bi-lock-fill' }} fs-5"></i>
        <div>
            @if($tanggal === $today)
                <strong>Hari Ini – {{ \Carbon\Carbon::parse($today)->translatedFormat('l, d F Y') }}</strong><br>
                <span class="small">Jurnal hari ini <strong>dapat diedit</strong> oleh guru terkait.</span>
            @else
                <strong>{{ \Carbon\Carbon::parse($tanggal)->translatedFormat('l, d F Y') }}</strong><br>
                <span class="small">Jurnal tanggal ini <strong>sudah terkunci</strong>. Tidak dapat diedit.</span>
            @endif
        </div>
    </div>

    <!-- ALERT SUCCESS / ERROR -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- TABEL JURNAL -->
    <div class="table-card-custom mb-4">
        <div class="table-responsive">
            <table class="table table-custom align-middle">
                <thead>
                    <tr>
                        <th style="width: 12%;">WAKTU</th>
                        <th style="width: 20%;">GURU</th>
                        <th style="width: 15%;">KELAS</th>
                        <th style="width: 20%;">MATA PELAJARAN</th>
                        <th style="width: 23%;">MATERI</th>
                        <th style="width: 10%; text-align: center;">STATUS</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dataJurnal as $jurnal)
                        <tr class="{{ !$jurnal->is_editable ? 'opacity-75' : '' }}">

                            <!-- Waktu Jam Pelajaran -->
                            <td>
                                <div class="fw-semibold text-dark" style="font-size: 0.88rem;">
                                    @if($jurnal->jadwal && $jurnal->jadwal->jamPelajaran)
                                        {{ \Carbon\Carbon::parse($jurnal->jadwal->jamPelajaran->jam_mulai)->format('H:i') }}
                                        –
                                        {{ \Carbon\Carbon::parse($jurnal->jadwal->jamPelajaran->jam_selesai)->format('H:i') }}
                                    @else
                                        -
                                    @endif
                                </div>
                                <div class="text-muted" style="font-size: 0.75rem;">
                                    {{ \Carbon\Carbon::parse($jurnal->waktu_isi)->format('H:i') }} WIB
                                </div>
                            </td>

                            <!-- Guru -->
                            <td>
                                <div class="fw-semibold text-dark" style="font-size: 0.88rem;">
                                    {{ $jurnal->jadwal->guru->nama ?? '-' }}
                                </div>
                                <div class="text-muted" style="font-size: 0.75rem;">
                                    NIP: {{ $jurnal->jadwal->guru->nip ?? '-' }}
                                </div>
                            </td>

                            <!-- Kelas -->
                            <td>
                                <span class="fw-semibold text-dark" style="font-size: 0.88rem;">
                                    {{ $jurnal->jadwal->kelas->nama_kelas ?? '-' }}
                                </span>
                            </td>

                            <!-- Mata Pelajaran -->
                            <td>
                                <span class="text-dark" style="font-size: 0.88rem;">
                                    {{ $jurnal->jadwal->mapel->nama_mapel ?? '-' }}
                                </span>
                            </td>

                            <!-- Materi -->
                            <td>
                                <div class="text-dark" style="font-size: 0.88rem; max-width: 260px;">
                                    {{ \Illuminate\Support\Str::limit($jurnal->materi, 80) }}
                                </div>
                                @if($jurnal->catatan_kejadian)
                                    <div class="text-warning small mt-1">
                                        <i class="bi bi-exclamation-triangle-fill me-1"></i>{{ \Illuminate\Support\Str::limit($jurnal->catatan_kejadian, 60) }}
                                    </div>
                                @endif
                            </td>

                            <!-- Kolom Status: Terkunci / Dapat Di-edit -->
                            <td class="text-center">
                                @if($jurnal->is_editable)
                                    <span class="rounded-pill px-3 py-1 fw-bold border border-success-subtle bg-success-subtle text-success"
                                          style="font-size: 0.75rem; white-space: nowrap;">
                                        <i class="bi bi-unlock-fill me-1"></i>Dapat Di-edit
                                    </span>
                                @else
                                    <span class="rounded-pill px-3 py-1 fw-bold border border-secondary-subtle bg-secondary-subtle text-secondary"
                                          style="font-size: 0.75rem; white-space: nowrap;">
                                        <i class="bi bi-lock-fill me-1"></i>Terkunci
                                    </span>
                                @endif
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-journal-x fs-1 d-block mb-2 text-secondary"></i>
                                Tidak ada jurnal yang ditemukan untuk tanggal <strong>{{ \Carbon\Carbon::parse($tanggal)->translatedFormat('d F Y') }}</strong>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- FOOTER COUNT -->
        <div class="pt-3 border-top text-muted small">
            Menampilkan <strong>{{ $dataJurnal->count() }}</strong> jurnal untuk tanggal
            <strong>{{ \Carbon\Carbon::parse($tanggal)->translatedFormat('d F Y') }}</strong>.
        </div>
    </div>

</div>
@endsection
