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
        <form action="{{ route('jurnal.update', $jurnal->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label class="form-label fw-semibold text-dark">Pilih Jadwal Mengajar <span class="text-danger">*</span></label>
                <select name="id_jadwal" class="form-select rounded-3 py-2 @error('id_jadwal') is-invalid @enderror" required>
                    <option value="">-- Pilih Jadwal --</option>
                    @foreach ($jadwals as $j)
                        <option value="{{ $j->id }}" {{ old('id_jadwal', $jurnal->id_jadwal) == $j->id ? 'selected' : '' }}>
                            {{ $j->hari }} (Jam Ke-{{ $j->jamPelajaran->jam_ke ?? '-' }}: {{ $j->jamPelajaran->jam_mulai ?? '' }} - {{ $j->jamPelajaran->jam_selesai ?? '' }}) | Kelas: {{ $j->kelas->nama_kelas ?? '-' }} | Mapel: {{ $j->mapel->nama_mapel ?? '-' }} | Guru: {{ $j->guru->nama ?? $j->guru->nama_guru ?? '-' }}
                        </option>
                    @endforeach
                </select>
                @error('id_jadwal')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-dark">Tanggal Mengajar <span class="text-danger">*</span></label>
                    <input type="date" name="tanggal" class="form-control rounded-3 py-2 @error('tanggal') is-invalid @enderror" value="{{ old('tanggal', is_string($jurnal->tanggal) ? $jurnal->tanggal : $jurnal->tanggal?->format('Y-m-d')) }}" required>
                    @error('tanggal')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-dark">Status Kehadiran Guru <span class="text-danger">*</span></label>
                    <select name="status_kehadiran" class="form-select rounded-3 py-2 @error('status_kehadiran') is-invalid @enderror" required>
                        <option value="Hadir" {{ old('status_kehadiran', $jurnal->status_kehadiran ?? 'Hadir') == 'Hadir' ? 'selected' : '' }}>Hadir</option>
                        <option value="Izin" {{ old('status_kehadiran', $jurnal->status_kehadiran) == 'Izin' ? 'selected' : '' }}>Izin</option>
                        <option value="Sakit" {{ old('status_kehadiran', $jurnal->status_kehadiran) == 'Sakit' ? 'selected' : '' }}>Sakit</option>
                        <option value="Disposisi" {{ old('status_kehadiran', $jurnal->status_kehadiran) == 'Disposisi' ? 'selected' : '' }}>Disposisi / Dinas</option>
                    </select>
                    @error('status_kehadiran')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold text-dark">Guru Pengganti / Piket (Opsional)</label>
                <select name="id_guru_pengganti" class="form-select rounded-3 py-2 @error('id_guru_pengganti') is-invalid @enderror">
                    <option value="">-- Tidak Ada / Pilih Guru Piket --</option>
                    @if(isset($gurus))
                        @foreach ($gurus as $guru)
                            <option value="{{ $guru->id }}" {{ old('id_guru_pengganti', $jurnal->id_guru_pengganti) == $guru->id ? 'selected' : '' }}>
                                {{ $guru->nama }} ({{ $guru->role }})
                            </option>
                        @endforeach
                    @endif
                </select>
                <small class="text-muted">Pilih jika guru asli berhalangan (Izin/Sakit/Disposisi) dan digantikan oleh guru piket.</small>
                @error('id_guru_pengganti')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold text-dark">Materi Pembelajaran <span class="text-danger">*</span></label>
                <textarea name="materi" class="form-control rounded-3 @error('materi') is-invalid @enderror" rows="3" placeholder="Masukkan materi pembelajaran" required>{{ old('materi', $jurnal->materi) }}</textarea>
                @error('materi')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold text-dark">Catatan Kejadian</label>
                <textarea name="catatan_kejadian" class="form-control rounded-3 @error('catatan_kejadian') is-invalid @enderror" rows="3" placeholder="Deskripsi kejadian atau catatan khusus">{{ old('catatan_kejadian', $jurnal->catatan_kejadian) }}</textarea>
                @error('catatan_kejadian')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold text-dark">Foto Kegiatan (Opsional)</label>
                @if($jurnal->foto_kegiatan)
                    <div class="mb-2">
                        <img src="{{ route('jurnal.foto', basename($jurnal->foto_kegiatan)) }}" alt="Foto Kegiatan" class="img-thumbnail rounded-3" style="max-height: 150px;">
                    </div>
                @endif
                <input type="file" name="foto_kegiatan" class="form-control rounded-3 @error('foto_kegiatan') is-invalid @enderror" accept="image/*">
                <small class="text-muted">Biarkan kosong jika tidak ingin mengubah foto kegiatan</small>
                @error('foto_kegiatan')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('jurnal.index') }}" class="btn btn-light border rounded-3 px-4 py-2">Batal</a>
                <button type="submit" class="btn btn-primary rounded-3 px-4 py-2 fw-semibold">Simpan Perubahan</button>
            </div>
        </form>
    </div>

</div>
@endsection
