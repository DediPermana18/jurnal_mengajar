@extends('admin.layouts.app')

@section('title', 'Tambah Jurnal Mengajar - WebJournal')

@section('content')
<div class="container-fluid px-0" style="max-width: 860px;">

    <!-- Header Section -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-1">Tambah Jurnal Mengajar</h3>
            <p class="text-muted small mb-0">Isi formulir di bawah ini untuk menambahkan catatan jurnal mengajar harian.</p>
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
        <form action="{{ route('jurnal.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="mb-3">
                <label class="form-label fw-semibold text-dark">Pilih Jadwal Mengajar <span class="text-danger">*</span></label>
                <select name="id_jadwal" class="form-select rounded-3 py-2 @error('id_jadwal') is-invalid @enderror" required>
                    <option value="">-- Pilih Jadwal (Hari/Jam - Kelas - Mapel - Guru) --</option>
                    @foreach ($jadwals as $j)
                        <option value="{{ $j->id }}" {{ old('id_jadwal') == $j->id ? 'selected' : '' }}>
                            {{ $j->hari }} (Jam Ke-{{ $j->jamPelajaran->jam_ke ?? '-' }}: {{ $j->jamPelajaran->jam_mulai ?? '' }} - {{ $j->jamPelajaran->jam_selesai ?? '' }}) | Kelas: {{ $j->kelas->nama_kelas ?? '-' }} | Mapel: {{ $j->mapel->nama_mapel ?? '-' }} | Guru: {{ $j->guru->nama ?? $j->guru->nama_guru ?? '-' }}
                        </option>
                    @endforeach
                </select>
                @error('id_jadwal')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold text-dark">Tanggal Mengajar <span class="text-danger">*</span></label>
                <input type="date" name="tanggal" class="form-control rounded-3 py-2 @error('tanggal') is-invalid @enderror" value="{{ old('tanggal', date('Y-m-d')) }}" required>
                @error('tanggal')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold text-dark">Materi Pembelajaran <span class="text-danger">*</span></label>
                <textarea name="materi" class="form-control rounded-3 @error('materi') is-invalid @enderror" rows="3" placeholder="Contoh: Routing Laravel dasar & penanganan Controller" required>{{ old('materi') }}</textarea>
                @error('materi')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold text-dark">Catatan Kejadian</label>
                <textarea name="catatan_kejadian" class="form-control rounded-3 @error('catatan_kejadian') is-invalid @enderror" rows="3" placeholder="Deskripsi kejadian atau catatan khusus kelas (opsional)">{{ old('catatan_kejadian') }}</textarea>
                @error('catatan_kejadian')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold text-dark">Foto Kegiatan (Opsional)</label>
                <input type="file" name="foto_kegiatan" class="form-control rounded-3 @error('foto_kegiatan') is-invalid @enderror" accept="image/*">
                <small class="text-muted">Format yang didukung: JPG, JPEG, PNG, WEBP (Maks: 2MB)</small>
                @error('foto_kegiatan')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('jurnal.index') }}" class="btn btn-light border rounded-3 px-4 py-2">Batal</a>
                <button type="submit" class="btn btn-primary rounded-3 px-4 py-2 fw-semibold">Simpan Jurnal</button>
            </div>
        </form>
    </div>

</div>
@endsection
