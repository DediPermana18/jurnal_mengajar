@extends('layouts.app')

@section('title', 'Riwayat Jurnal Kelas - Wali Kelas')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-black text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 800; font-size: 1.75rem;">
                📖 Riwayat Jurnal Kelas
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                Pantau seluruh catatan jurnal pengajaran guru yang masuk di kelas Anda.
            </p>
        </div>
    </div>

    <div class="table-card-custom mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">Riwayat Jurnal Mengajar {{ $namaKelasSaya ?? 'Kelas Bimbingan' }}</h5>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1">{{ $daftarJurnal->count() }} Jurnal</span>
        </div>
        <div class="table-responsive w-full overflow-x-auto">
            <table class="table table-custom align-middle min-w-full">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Jam</th>
                        <th>Mata Pelajaran</th>
                        <th>Guru Pengajar</th>
                        <th>Materi Pelajaran</th>
                        <th>Kehadiran</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($daftarJurnal as $j)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($j['tanggal'])->translatedFormat('d M Y') }}</td>
                            <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1">{{ $j['jam_label'] }}</span></td>
                            <td><strong>{{ $j['mapel'] }}</strong></td>
                            <td>{{ $j['guru_pengajar'] }}</td>
                            <td class="text-muted">{{ $j['materi'] }}</td>
                            <td class="whitespace-nowrap">
                                @php
                                    $jumlahHadir = (int) $j['hadir'];
                                    $totalSiswa = (int) $j['total_siswa'];
                                    // Fallback bila presensi kosong: tampilkan "0/0 Siswa" (bukan badge kosong).
                                    $labelKehadiran = $totalSiswa > 0 ? "{$jumlahHadir}/{$totalSiswa} Siswa" : '0/0 Siswa';
                                @endphp
                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold whitespace-nowrap inline-block {{ $jumlahHadir === $totalSiswa && $totalSiswa > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                    {{ $labelKehadiran }}
                                </span>
                            </td>
                            <td class="text-end whitespace-nowrap">
                                <a href="{{ route('walikelas.riwayat-jurnal.show', $j['jurnal']->id) }}"
                                   class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-semibold"
                                   title="Lihat rincian lengkap jurnal ini">
                                    <i class="bi bi-eye me-1"></i> Detail
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-journal-x fs-1 d-block mb-2"></i>
                                Belum ada jurnal mengajar untuk kelas bimbingan Anda.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
