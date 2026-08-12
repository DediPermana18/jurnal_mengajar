@extends('admin.layouts.app')

@section('title', 'Edit Jurnal Mengajar - WebJournal')

@section('content')
<div class="container-fluid px-0" style="max-width: 860px;">

    <!-- Header Section -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-1">Edit Jurnal Mengajar</h3>
            <p class="text-muted small mb-0">Perbarui catatan data jurnal mengajar di bawah ini.</p>
        </div>
        <a href="{{ route('jurnal.index') }}" class="btn btn-light border rounded-3 px-3 py-2 fw-semibold d-flex align-items-center gap-2">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>

    <!-- Error Alerts -->
    @if ($errors->any())
        <div class="alert alert-danger border-0 rounded-4 shadow-sm mb-4">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Form Card -->
    <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
        <form action="{{ route('jurnal.update', $jurnal->id_jurnal ?? $jurnal->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label class="form-label fw-semibold text-dark">Pilih Jadwal Mengajar <span class="text-danger">*</span></label>
                <select name="id_jadwal" class="form-select rounded-3 py-2 @error('id_jadwal') is-invalid @enderror" required>
                    <option value="">-- Pilih Jadwal --</option>
                    @foreach ($jadwals as $j)
                        <option value="{{ $j->id_jadwal }}" {{ old('id_jadwal', $jurnal->id_jadwal) == $j->id_jadwal ? 'selected' : '' }}>
                            {{ $j->hari }} ({{ $j->jam_mulai }} - {{ $j->jam_selesai }}) | Kelas: {{ $j->kelas->nama_kelas ?? '-' }} | Mapel: {{ $j->mapel->nama_mapel ?? '-' }} | Guru: {{ $j->guru->nama_guru ?? '-' }}
                        </option>
                    @endforeach
                </select>
                @error('id_jadwal')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold text-dark">Tanggal Mengajar <span class="text-danger">*</span></label>
                    <input type="date" name="tanggal" class="form-control rounded-3 py-2 @error('tanggal') is-invalid @enderror" value="{{ old('tanggal', $jurnal->tanggal) }}" required>
                    @error('tanggal')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold text-dark">Status Kehadiran Guru <span class="text-danger">*</span></label>
                    <select name="status_guru" class="form-select rounded-3 py-2 @error('status_guru') is-invalid @enderror" required>
                        <option value="Hadir" {{ old('status_guru', $jurnal->status_guru) == 'Hadir' ? 'selected' : '' }}>Hadir</option>
                        <option value="Izin" {{ old('status_guru', $jurnal->status_guru) == 'Izin' ? 'selected' : '' }}>Izin</option>
                        <option value="Sakit" {{ old('status_guru', $jurnal->status_guru) == 'Sakit' ? 'selected' : '' }}>Sakit</option>
                        <option value="Tugas" {{ old('status_guru', $jurnal->status_guru) == 'Tugas' ? 'selected' : '' }}>Tugas</option>
                    </select>
                    @error('status_guru')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold text-dark">Materi Pembelajaran <span class="text-danger">*</span></label>
                <input type="text" name="materi" class="form-control rounded-3 py-2 @error('materi') is-invalid @enderror" value="{{ old('materi', $jurnal->materi) }}" placeholder="Masukkan materi pembelajaran" required>
                @error('materi')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold text-dark">Keterangan / Catatan Kegiatan</label>
                <textarea name="keterangan" class="form-control rounded-3 @error('keterangan') is-invalid @enderror" rows="3" placeholder="Deskripsi kegiatan atau catatan khusus">{{ old('keterangan', $jurnal->keterangan) }}</textarea>
                @error('keterangan')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold text-dark">Jumlah Siswa Hadir <span class="text-danger">*</span></label>
                    <input type="number" name="jumlah_siswa_hadir" class="form-control rounded-3 py-2 @error('jumlah_siswa_hadir') is-invalid @enderror" value="{{ old('jumlah_siswa_hadir', $jurnal->jumlah_siswa_hadir) }}" min="0" required>
                    @error('jumlah_siswa_hadir')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold text-dark">Semester <span class="text-danger">*</span></label>
                    <select name="semester" class="form-select rounded-3 py-2 @error('semester') is-invalid @enderror" required>
                        <option value="Ganjil" {{ old('semester', $jurnal->semester) == 'Ganjil' ? 'selected' : '' }}>Ganjil</option>
                        <option value="Genap" {{ old('semester', $jurnal->semester) == 'Genap' ? 'selected' : '' }}>Genap</option>
                    </select>
                    @error('semester')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4 mb-4">
                    <label class="form-label fw-semibold text-dark">Tahun Ajaran <span class="text-danger">*</span></label>
                    <input type="text" name="tahun_ajaran" class="form-control rounded-3 py-2 @error('tahun_ajaran') is-invalid @enderror" value="{{ old('tahun_ajaran', $jurnal->tahun_ajaran) }}" required>
                    @error('tahun_ajaran')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('jurnal.index') }}" class="btn btn-light border rounded-3 px-4 py-2">Batal</a>
                <button type="submit" class="btn btn-primary rounded-3 px-4 py-2 fw-semibold">Simpan Perubahan</button>
            </div>
        </form>
    </div>

</div>
@endsection
