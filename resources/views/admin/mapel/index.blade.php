@extends('admin.layouts.app')

@section('title', 'Data Master Mata Pelajaran - WebJournal Management System')

@section('content')
<div class="container-fluid px-0">

    <!-- Header Judul & Action Button -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 800; font-size: 1.85rem;">Data Master Mata Pelajaran</h2>
            <p class="text-muted mb-0" style="font-size: 0.925rem; font-weight: 500;">Kelola pemetaan mata pelajaran, jam mengajar, dan status kehadiran guru di kelas.</p>
        </div>
        <a href="{{ route('mapel.create') }}" class="btn btn-primary rounded-3 px-3 py-2 fw-semibold d-flex align-items-center gap-2" style="background-color: #1565c0; border: none;">
            <i class="bi bi-plus"></i>
            <span>Tambah Mapel</span>
        </a>
    </div>

    <!-- Alert Notifikasi Flash -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-check-circle-fill fs-5 me-2"></i>
                <div>{{ session('success') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Card Container Data Mapel -->
    <div class="table-card-custom">
        
        <!-- Filter Controls Bar (Top of Table) -->
        <div class="row g-3 align-items-center mb-4">
            <!-- Search Input -->
            <div class="col-12 col-md-4">
                <div class="position-relative">
                    <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                    <input type="text" class="form-control rounded-3 ps-5 bg-light border-0 py-2 text-sm" placeholder="Cari mapel atau guru...">
                </div>
            </div>

            <!-- Dropdown Filter 1: Pilih Kelas -->
            <div class="col-6 col-md-2">
                <select class="form-select rounded-3 bg-light border-0 py-2 small text-secondary fw-medium">
                    <option value="">Pilih Kelas</option>
                    @foreach($dataKelas as $kelas)
                        <option value="{{ $kelas->id_kelas }}">{{ $kelas->nama_kelas }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Dropdown Filter 2: Jam Pelajaran -->
            <div class="col-6 col-md-3">
                <select class="form-select rounded-3 bg-light border-0 py-2 small text-secondary fw-medium">
                    <option value="">Jam Pelajaran</option>
                    <option value="Jam 1 - 4">Jam 1 - 4</option>
                    <option value="Jam 5 - 8">Jam 5 - 8</option>
                    <option value="Jam 9 - 10">Jam 9 - 10</option>
                </select>
            </div>

            <!-- Dropdown Filter 3: Status Guru -->
            <div class="col-6 col-md-3">
                <select class="form-select rounded-3 bg-light border-0 py-2 small text-secondary fw-medium">
                    <option value="">Status Guru</option>
                    <option value="Masuk Kelas">Masuk Kelas</option>
                    <option value="Tidak Hadir">Tidak Hadir</option>
                    <option value="Tugas">Tugas</option>
                </select>
            </div>
        </div>

        <!-- Table Data Mapel -->
        <div class="table-responsive">
            <table class="table table-custom align-middle">
                <thead>
                    <tr>
                        <th style="width: 12%;">KELAS</th>
                        <th style="width: 25%;">MATA PELAJARAN</th>
                        <th style="width: 15%;">JAM KE-</th>
                        <th style="width: 25%;">GURU PENGAJAR</th>
                        <th style="width: 13%;">STATUS GURU</th>
                        <th style="width: 10%; text-align: right;">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dataMapel as $mapel)
                        <tr>
                            <td>
                                <span class="badge bg-light text-dark border px-2.5 py-1.5 font-monospace fw-semibold" style="border-color: #cbd5e1 !important; border-radius: 6px;">
                                    {{ $mapel->kelas->nama_kelas ?? ($mapel->kode_mapel ?? 'XI RPL 1') }}
                                </span>
                            </td>
                            <td>
                                <span class="fw-bold text-dark fs-6">{{ $mapel->nama_mapel }}</span>
                            </td>
                            <td>
                                <span class="text-secondary fw-medium">{{ $mapel->jam_ke ?? 'Jam 1 - 4' }}</span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    @php
                                        $namaGuru = $mapel->guru->nama_guru ?? 'Guru Pengajar';
                                        $initials = '';
                                        $words = explode(' ', $namaGuru);
                                        foreach(array_slice($words, 0, 2) as $w) {
                                            $initials .= strtoupper(substr($w, 0, 1));
                                        }
                                    @endphp
                                    <div class="rounded-circle text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px; font-size: 0.75rem; background-color: #3b82f6;">
                                        {{ $initials }}
                                    </div>
                                    <span class="fw-semibold text-dark">{{ $namaGuru }}</span>
                                </div>
                            </td>
                            <td>
                                @if(($mapel->status_guru ?? 'Masuk Kelas') == 'Masuk Kelas' || ($mapel->status_guru ?? '') == 'Hadir')
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1 fw-semibold d-inline-flex align-items-center gap-1">
                                        <i class="bi bi-circle-fill me-1" style="font-size: 0.45rem;"></i> Masuk Kelas
                                    </span>
                                @elseif(($mapel->status_guru ?? '') == 'Tidak Hadir')
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3 py-1 fw-semibold d-inline-flex align-items-center gap-1">
                                        <i class="bi bi-circle-fill me-1" style="font-size: 0.45rem;"></i> Tidak Hadir
                                    </span>
                                @else
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-3 py-1 fw-semibold d-inline-flex align-items-center gap-1">
                                        <i class="bi bi-circle-fill me-1" style="font-size: 0.45rem;"></i> {{ $mapel->status_guru ?? 'Tugas' }}
                                    </span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('mapel.show', $mapel->id_kelas ?? $mapel->id_mapel) }}" class="text-secondary me-2 fs-6" title="Lihat Matriks Jadwal"><i class="bi bi-eye"></i></a>
                                <a href="{{ route('mapel.edit', $mapel->id_mapel) }}" class="text-secondary me-2 fs-6" title="Edit"><i class="bi bi-pencil"></i></a>
                                <form action="{{ route('mapel.destroy', $mapel->id_mapel) }}" method="POST" class="d-inline" onsubmit="return confirm('Yakin hapus data ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-link p-0 text-secondary border-0 fs-6" title="Hapus"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <!-- Sample Demonstration Rows Tepat Sesuai Foto -->
                        <!-- Row 1 -->
                        <tr>
                            <td>
                                <span class="badge bg-light text-dark border px-2.5 py-1.5 font-monospace fw-semibold" style="border-color: #cbd5e1 !important; border-radius: 6px;">
                                    XI RPL 1
                                </span>
                            </td>
                            <td>
                                <span class="fw-bold text-dark fs-6">Konsentrasi RPL</span>
                            </td>
                            <td>
                                <span class="text-secondary fw-medium">Jam 1 - 4</span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px; font-size: 0.75rem; background-color: #3b82f6;">
                                        BS
                                    </div>
                                    <span class="fw-semibold text-dark">Budi Santoso</span>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1 fw-semibold d-inline-flex align-items-center gap-1">
                                    <i class="bi bi-circle-fill me-1" style="font-size: 0.45rem;"></i> Masuk Kelas
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="#" class="text-secondary me-2 fs-6" title="Detail"><i class="bi bi-eye"></i></a>
                                <a href="#" class="text-secondary me-2 fs-6" title="Edit"><i class="bi bi-pencil"></i></a>
                                <a href="#" class="text-secondary fs-6" title="Hapus"><i class="bi bi-trash"></i></a>
                            </td>
                        </tr>

                        <!-- Row 2 -->
                        <tr>
                            <td>
                                <span class="badge bg-light text-dark border px-2.5 py-1.5 font-monospace fw-semibold" style="border-color: #cbd5e1 !important; border-radius: 6px;">
                                    X TKJ 2
                                </span>
                            </td>
                            <td>
                                <span class="fw-bold text-dark fs-6">Dasar Program Keahlian</span>
                            </td>
                            <td>
                                <span class="text-secondary fw-medium">Jam 5 - 8</span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px; font-size: 0.75rem; background-color: #475569;">
                                        SR
                                    </div>
                                    <span class="fw-semibold text-dark">Siti Rahmawati</span>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3 py-1 fw-semibold d-inline-flex align-items-center gap-1">
                                    <i class="bi bi-circle-fill me-1" style="font-size: 0.45rem;"></i> Tidak Hadir
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="#" class="text-secondary me-2 fs-6" title="Detail"><i class="bi bi-eye"></i></a>
                                <a href="#" class="text-secondary me-2 fs-6" title="Edit"><i class="bi bi-pencil"></i></a>
                                <a href="#" class="text-secondary fs-6" title="Hapus"><i class="bi bi-trash"></i></a>
                            </td>
                        </tr>

                        <!-- Row 3 -->
                        <tr>
                            <td>
                                <span class="badge bg-light text-dark border px-2.5 py-1.5 font-monospace fw-semibold" style="border-color: #cbd5e1 !important; border-radius: 6px;">
                                    XI RPL 1
                                </span>
                            </td>
                            <td>
                                <span class="fw-bold text-dark fs-6">Bahasa Inggris</span>
                            </td>
                            <td>
                                <span class="text-secondary fw-medium">Jam 9 - 10</span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px; font-size: 0.75rem; background-color: #64748b;">
                                        AW
                                    </div>
                                    <span class="fw-semibold text-dark">Ahmad Wijaya</span>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-3 py-1 fw-semibold d-inline-flex align-items-center gap-1">
                                    <i class="bi bi-circle-fill me-1" style="font-size: 0.45rem;"></i> Tugas
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="#" class="text-secondary me-2 fs-6" title="Detail"><i class="bi bi-eye"></i></a>
                                <a href="#" class="text-secondary me-2 fs-6" title="Edit"><i class="bi bi-pencil"></i></a>
                                <a href="#" class="text-secondary fs-6" title="Hapus"><i class="bi bi-trash"></i></a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Table Footer / Pagination -->
        <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
            <div class="text-muted small">
                Menampilkan 1-3 dari 45 Mata Pelajaran
            </div>
            <div class="d-flex align-items-center gap-1">
                <button class="btn btn-sm btn-light border px-2 py-1 text-muted" style="border-radius: 6px;" disabled><i class="bi bi-chevron-left small"></i></button>
                <button class="btn btn-sm btn-light border px-2 py-1 text-muted" style="border-radius: 6px;" disabled><i class="bi bi-chevron-right small"></i></button>
            </div>
        </div>

    </div>

</div>
@endsection
