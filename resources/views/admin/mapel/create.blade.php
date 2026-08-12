@extends('admin.layouts.app')

@section('title', 'Tambah Mapel Baru - WebJournal')

@section('content')
<div class="container-fluid px-0" style="max-width: 800px;">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-1">Tambah Mata Pelajaran Baru</h3>
            <p class="text-muted small mb-0">Isi formulir di bawah ini untuk menginput mata pelajaran baru beserta pemetaan kelas & guru.</p>
        </div>
        <a href="{{ route('mapel.index') }}" class="btn btn-light border rounded-3 px-3 py-2 fw-semibold d-flex align-items-center gap-2">
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
        <form action="{{ route('mapel.store') }}" method="POST">
            @csrf
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold text-dark">Nama Mata Pelajaran <span class="text-danger">*</span></label>
                    <input type="text" name="nama_mapel" class="form-control rounded-3 py-2" value="{{ old('nama_mapel') }}" required placeholder="Contoh: Konsentrasi RPL">
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold text-dark">Kode Mapel (Opsional)</label>
                    <input type="text" name="kode_mapel" class="form-control rounded-3 py-2" value="{{ old('kode_mapel') }}" placeholder="Contoh: MPL-01">
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold text-dark">Pilih Kelas</label>
                    <select name="id_kelas" class="form-select rounded-3 py-2">
                        <option value="">-- Pilih Kelas --</option>
                        @foreach ($dataKelas as $kelas)
                            <option value="{{ $kelas->id_kelas }}" {{ old('id_kelas') == $kelas->id_kelas ? 'selected' : '' }}>
                                {{ $kelas->nama_kelas }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold text-dark">Pilih Guru Pengajar</label>
                    <select name="id_guru" class="form-select rounded-3 py-2">
                        <option value="">-- Pilih Guru --</option>
                        @foreach ($dataGuru as $guru)
                            <option value="{{ $guru->id_guru }}" {{ old('id_guru') == $guru->id_guru ? 'selected' : '' }}>
                                {{ $guru->nama_guru }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold text-dark">Jam Ke-</label>
                    <input type="text" name="jam_ke" class="form-control rounded-3 py-2" value="{{ old('jam_ke', 'Jam 1 - 4') }}" placeholder="Contoh: Jam 1 - 4">
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold text-dark">Status Guru</label>
                    <select name="status_guru" class="form-select rounded-3 py-2">
                        <option value="Masuk Kelas" {{ old('status_guru') == 'Masuk Kelas' ? 'selected' : '' }}>Masuk Kelas</option>
                        <option value="Tidak Hadir" {{ old('status_guru') == 'Tidak Hadir' ? 'selected' : '' }}>Tidak Hadir</option>
                        <option value="Tugas" {{ old('status_guru') == 'Tugas' ? 'selected' : '' }}>Tugas</option>
                    </select>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('mapel.index') }}" class="btn btn-light border rounded-3 px-4 py-2">Batal</a>
                <button type="submit" class="btn btn-primary rounded-3 px-4 py-2 fw-semibold" style="background-color: #1565c0; border: none;">Simpan Data</button>
            </div>
        </form>
    </div>

</div>
@endsection
