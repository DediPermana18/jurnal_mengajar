@extends('admin.layouts.app')

@section('title', 'Edit Data Kelas - WebJournal')

@section('content')
<div class="container-fluid px-0" style="max-width: 760px;">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-1">Edit Data Kelas</h3>
            <p class="text-muted small mb-0">Perbarui informasi data kelas di bawah ini.</p>
        </div>
        <a href="{{ route('kelas.index') }}" class="btn btn-light border rounded-3 px-3 py-2 fw-semibold d-flex align-items-center gap-2">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger border-0 rounded-4 shadow-sm mb-4">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
        <form action="{{ route('kelas.update', $kelas->id_kelas) }}" method="POST">
            @csrf
            @method('PUT')
            
            <div class="mb-3">
                <label class="form-label fw-semibold text-dark">Nama Kelas <span class="text-danger">*</span></label>
                <input type="text" name="nama_kelas" class="form-control rounded-3 py-2" value="{{ old('nama_kelas', $kelas->nama_kelas) }}" required placeholder="Contoh: XII RPL 1">
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold text-dark">Jurusan</label>
                <select name="id_jurusan" class="form-select rounded-3 py-2">
                    <option value="">-- Pilih Jurusan --</option>
                    @foreach ($dataJurusan as $jurusan)
                        <option value="{{ $jurusan->id_jurusan }}" {{ old('id_jurusan', $kelas->id_jurusan) == $jurusan->id_jurusan ? 'selected' : '' }}>
                            {{ $jurusan->nama_jurusan }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold text-dark">Wali Kelas</label>
                <select name="id_guru_wali" class="form-select rounded-3 py-2">
                    <option value="">-- Pilih Wali Kelas --</option>
                    @foreach ($dataGuru as $guru)
                        <option value="{{ $guru->id_guru }}" {{ old('id_guru_wali', $kelas->id_guru_wali) == $guru->id_guru ? 'selected' : '' }}>
                            {{ $guru->nama_guru }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold text-dark">Jumlah Siswa</label>
                <input type="number" name="jumlah_siswa" class="form-control rounded-3 py-2" value="{{ old('jumlah_siswa', $kelas->jumlah_siswa) }}" min="0" placeholder="0">
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('kelas.index') }}" class="btn btn-light border rounded-3 px-4 py-2">Batal</a>
                <button type="submit" class="btn btn-primary rounded-3 px-4 py-2 fw-semibold">Simpan Perubahan</button>
            </div>
        </form>
    </div>

</div>
@endsection
