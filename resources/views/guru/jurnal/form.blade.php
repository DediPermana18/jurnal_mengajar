@extends('layouts.app')

@section('title', 'Isi Jurnal Mengajar - WebJournal')

@push('styles')
<style>
    .form-section-card {
        background: #ffffff;
        border: 1px solid #e8eef5;
        border-radius: 16px;
        box-shadow: 0 2px 12px rgba(15, 23, 42, 0.05);
        padding: 1.75rem 2rem;
    }

    .presensi-row.hadir-default {
        background-color: #f0fdf4;
    }

    .presensi-row.tidak-hadir {
        background-color: #fef2f2;
    }

    .presensi-detail {
        display: none;
    }

    .presensi-row.tidak-hadir .presensi-detail {
        display: table-row;
    }

    .status-radio-group .form-check-inline {
        margin-right: 0.75rem;
    }

    .readonly-field {
        background-color: #f1f5f9 !important;
        cursor: not-allowed;
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 800; font-size: 1.75rem;">
                Form Pengisian Jurnal
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                Jam {{ $jadwal->jamPelajaran?->jam_ke ?? '-' }} &bull; {{ $waktu }} &bull; {{ \Carbon\Carbon::parse($today)->translatedFormat('d F Y') }}
            </p>
        </div>
        <a href="{{ route('guru.jurnal') }}" class="btn btn-light border rounded-3 px-3 py-2 fw-semibold">
            <i class="bi bi-arrow-left me-1"></i> Kembali
        </a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger border-0 rounded-4 shadow-sm">
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('guru.jurnal.store') }}" method="POST" enctype="multipart/form-data" id="formJurnal">
        @csrf
        <input type="hidden" name="id_jadwal" value="{{ $jadwal->id }}">

        {{-- Header Jurnal --}}
        <div class="form-section-card mb-4">
            <h5 class="fw-bold text-dark mb-3">
                <i class="bi bi-journal-text text-primary me-2"></i> Informasi Jurnal
            </h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-secondary small text-uppercase">Nama Kelas</label>
                    <input type="text"
                           class="form-control rounded-3 readonly-field"
                           value="{{ $jadwal->kelas?->nama_kelas ?? '-' }}"
                           readonly
                           disabled>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-secondary small text-uppercase">Mata Pelajaran</label>
                    <input type="text"
                           class="form-control rounded-3 readonly-field"
                           value="{{ $jadwal->mapel?->nama_mapel ?? '-' }}"
                           readonly
                           disabled>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold text-secondary small text-uppercase">
                        Materi Pelajaran / Bahasan <span class="text-danger">*</span>
                    </label>
                    <textarea name="materi"
                              class="form-control rounded-3 @error('materi') is-invalid @enderror"
                              rows="4"
                              placeholder="Tuliskan ringkasan materi pelajaran yang disampaikan..."
                              required>{{ old('materi') }}</textarea>
                    @error('materi')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Presensi Siswa --}}
        <div class="form-section-card mb-4">
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                <h5 class="fw-bold text-dark mb-0">
                    <i class="bi bi-people-fill text-primary me-2"></i> Presensi Siswa
                </h5>
                <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle rounded-pill px-3 py-2">
                    <i class="bi bi-check2-all me-1"></i> Default: Semua Hadir
                </span>
            </div>
            <p class="text-muted small mb-3">
                Centang hanya siswa yang <strong>tidak hadir</strong>. Siswa yang tidak dicentang otomatis tercatat sebagai <strong>Hadir</strong>.
            </p>

            <div class="table-responsive">
                <table class="table table-custom align-middle mb-0" id="tabelPresensi">
                    <thead>
                        <tr>
                            <th style="width: 50px;">No</th>
                            <th>NIS</th>
                            <th>Nama Siswa</th>
                            <th style="width: 140px;" class="text-center">Tidak Hadir?</th>
                            <th style="width: 120px;" class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($siswas as $index => $siswa)
                            @php
                                $isTidakHadir = in_array($siswa->id, old('tidak_hadir', []));
                                $oldStatus = old("status.{$siswa->id}", 'Sakit');
                            @endphp
                            <tr class="presensi-row {{ $isTidakHadir ? 'tidak-hadir' : 'hadir-default' }}"
                                data-siswa-id="{{ $siswa->id }}">
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $siswa->nis }}</td>
                                <td class="fw-semibold">{{ $siswa->nama }}</td>
                                <td class="text-center">
                                    <div class="form-check d-flex justify-content-center">
                                        <input type="checkbox"
                                               class="form-check-input chk-tidak-hadir"
                                               name="tidak_hadir[]"
                                               value="{{ $siswa->id }}"
                                               id="tidak_hadir_{{ $siswa->id }}"
                                               {{ $isTidakHadir ? 'checked' : '' }}>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-success-subtle text-success-emphasis status-hadir-label {{ $isTidakHadir ? 'd-none' : '' }}">
                                        Hadir
                                    </span>
                                    <span class="badge bg-danger-subtle text-danger-emphasis status-absen-label {{ $isTidakHadir ? '' : 'd-none' }}">
                                        Tidak Hadir
                                    </span>
                                </td>
                            </tr>
                            <tr class="presensi-detail {{ $isTidakHadir ? '' : 'd-none' }}"
                                data-detail-for="{{ $siswa->id }}">
                                <td colspan="5" class="bg-light rounded-3">
                                    <div class="p-3">
                                        <div class="row g-3 align-items-start">
                                            <div class="col-md-5">
                                                <label class="form-label fw-semibold small text-uppercase text-secondary">
                                                    Jenis Ketidakhadiran <span class="text-danger">*</span>
                                                </label>
                                                <div class="status-radio-group">
                                                    @foreach(['Sakit' => 'S', 'Izin' => 'I', 'Alpa' => 'A', 'Dispen' => 'D'] as $val => $label)
                                                        <div class="form-check form-check-inline">
                                                            <input class="form-check-input"
                                                                   type="radio"
                                                                   name="status[{{ $siswa->id }}]"
                                                                   id="status_{{ $siswa->id }}_{{ $val }}"
                                                                   value="{{ $val }}"
                                                                   {{ $oldStatus === $val ? 'checked' : '' }}
                                                                   {{ $isTidakHadir ? '' : 'disabled' }}>
                                                            <label class="form-check-label" for="status_{{ $siswa->id }}_{{ $val }}">
                                                                {{ $val }} ({{ $label }})
                                                            </label>
                                                        </div>
                                                    @endforeach
                                                </div>
                                                @error("status.{$siswa->id}")
                                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label fw-semibold small text-uppercase text-secondary">
                                                    Keterangan / Alasan
                                                </label>
                                                <input type="text"
                                                       name="keterangan[{{ $siswa->id }}]"
                                                       class="form-control rounded-3 form-control-sm"
                                                       placeholder="Opsional"
                                                       value="{{ old("keterangan.{$siswa->id}") }}"
                                                       {{ $isTidakHadir ? '' : 'disabled' }}>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label fw-semibold small text-uppercase text-secondary">
                                                    Foto Surat Izin/Dokter
                                                </label>
                                                <input type="file"
                                                       name="foto_surat[{{ $siswa->id }}]"
                                                       class="form-control form-control-sm rounded-3"
                                                       accept="image/jpeg,image/png,image/webp"
                                                       {{ $isTidakHadir ? '' : 'disabled' }}>
                                                <small class="text-muted">Opsional, maks. 2MB</small>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    Tidak ada siswa aktif di kelas ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('guru.jurnal') }}" class="btn btn-light border rounded-3 px-4 py-2 fw-semibold">
                Batal
            </a>
            <button type="submit" class="btn btn-primary rounded-3 px-4 py-2 fw-semibold" {{ $siswas->isEmpty() ? 'disabled' : '' }}>
                <i class="bi bi-save me-1"></i> Simpan Jurnal & Presensi
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const checkboxes = document.querySelectorAll('.chk-tidak-hadir');

        function toggleSiswaRow(checkbox) {
            const siswaId = checkbox.value;
            const mainRow = checkbox.closest('.presensi-row');
            const detailRow = document.querySelector(`tr[data-detail-for="${siswaId}"]`);
            const hadirLabel = mainRow.querySelector('.status-hadir-label');
            const absenLabel = mainRow.querySelector('.status-absen-label');
            const detailInputs = detailRow ? detailRow.querySelectorAll('input') : [];

            if (checkbox.checked) {
                mainRow.classList.remove('hadir-default');
                mainRow.classList.add('tidak-hadir');
                if (detailRow) detailRow.classList.remove('d-none');
                hadirLabel?.classList.add('d-none');
                absenLabel?.classList.remove('d-none');
                detailInputs.forEach(input => {
                    if (input.type === 'radio') {
                        const group = detailRow.querySelectorAll(`input[name="${input.name}"]`);
                        const anyChecked = Array.from(group).some(r => r.checked);
                        if (!anyChecked && group.length) group[0].checked = true;
                    }
                    input.disabled = false;
                });
            } else {
                mainRow.classList.add('hadir-default');
                mainRow.classList.remove('tidak-hadir');
                if (detailRow) detailRow.classList.add('d-none');
                hadirLabel?.classList.remove('d-none');
                absenLabel?.classList.add('d-none');
                detailInputs.forEach(input => {
                    input.disabled = true;
                    if (input.type === 'checkbox' || input.type === 'radio') {
                        input.checked = false;
                    } else if (input.type === 'file' || input.type === 'text') {
                        input.value = '';
                    }
                });
            }
        }

        checkboxes.forEach(function (checkbox) {
            checkbox.addEventListener('change', function () {
                toggleSiswaRow(this);
            });
            toggleSiswaRow(checkbox);
        });
    });
</script>
@endpush
