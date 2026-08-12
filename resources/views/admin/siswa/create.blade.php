@extends('admin.layouts.app')

@section('title', 'Tambah Siswa Baru - WebJournal')

@section('content')
<div class="container-fluid px-0" style="max-width: 760px;">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-1">Tambah Siswa Baru</h3>
            <p class="text-muted small mb-0">Isi formulir di bawah ini untuk menginput data siswa baru.</p>
        </div>
        <a href="{{ route('siswa.index') }}" class="btn btn-light border rounded-3 px-3 py-2 fw-semibold d-flex align-items-center gap-2">
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
        <form action="{{ route('siswa.store') }}" method="POST">
            @csrf
            
            <div class="mb-3">
                <label class="form-label fw-semibold text-dark">NIS (Nomor Induk Siswa)</label>
                <input type="text" name="nis" class="form-control rounded-3 py-2" value="{{ old('nis') }}" placeholder="Masukkan NIS">
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold text-dark">Nama Siswa <span class="text-danger">*</span></label>
                <input type="text" name="nama_siswa" class="form-control rounded-3 py-2" value="{{ old('nama_siswa') }}" required placeholder="Masukkan Nama Lengkap Siswa">
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold text-dark">Kelas <span class="text-danger">*</span></label>
                <select name="id_kelas" class="form-select rounded-3 py-2" required>
                    <option value="">-- Pilih Kelas --</option>
                    @foreach ($dataKelas as $kelas)
                        <option value="{{ $kelas->id_kelas }}" {{ old('id_kelas') == $kelas->id_kelas ? 'selected' : '' }}>
                            {{ $kelas->nama_kelas }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold text-dark d-block">Jenis Kelamin <span class="text-danger">*</span></label>
                <div class="d-flex gap-4">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="jenis_kelamin" id="jk_l" value="L" {{ old('jenis_kelamin') == 'L' ? 'checked' : '' }} required>
                        <label class="form-check-label" for="jk_l">Laki-laki</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="jenis_kelamin" id="jk_p" value="P" {{ old('jenis_kelamin') == 'P' ? 'checked' : '' }} required>
                        <label class="form-check-label" for="jk_p">Perempuan</label>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('siswa.index') }}" class="btn btn-light border rounded-3 px-4 py-2">Batal</a>
                <button type="submit" class="btn btn-primary rounded-3 px-4 py-2 fw-semibold">Simpan Data</button>
            </div>
        </form>
    </div>

</div>
@endsection
