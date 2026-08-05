<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Jurnal Mengajar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">

<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-journal-plus"></i> Tambah Jurnal Mengajar Baru</h5>
                </div>
                <div class="card-body p-4">

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('jurnal.store') }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label fw-bold">Pilih Jadwal Mengajar <span class="text-danger">*</span></label>
                            <select name="id_jadwal" class="form-select @error('id_jadwal') is-invalid @enderror" required>
                                <option value="">-- Pilih Jadwal (Hari/Jam - Kelas - Mapel - Guru) --</option>
                                @foreach ($jadwals as $j)
                                    <option value="{{ $j->id_jadwal }}" {{ old('id_jadwal') == $j->id_jadwal ? 'selected' : '' }}>
                                        {{ $j->hari }} ({{ $j->jam_mulai }} - {{ $j->jam_selesai }}) | Kelas: {{ $j->kelas->nama_kelas ?? '-' }} | Mapel: {{ $j->mapel->nama_mapel ?? '-' }} | Guru: {{ $j->guru->nama_guru ?? '-' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Tanggal Mengajar <span class="text-danger">*</span></label>
                                <input type="date" name="tanggal" class="form-control @error('tanggal') is-invalid @enderror" value="{{ old('tanggal', date('Y-m-d')) }}" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Status Kehadiran Guru <span class="text-danger">*</span></label>
                                <select name="status_guru" class="form-select @error('status_guru') is-invalid @enderror" required>
                                    <option value="Hadir" {{ old('status_guru') == 'Hadir' ? 'selected' : '' }}>Hadir</option>
                                    <option value="Izin" {{ old('status_guru') == 'Izin' ? 'selected' : '' }}>Izin</option>
                                    <option value="Sakit" {{ old('status_guru') == 'Sakit' ? 'selected' : '' }}>Sakit</option>
                                    <option value="Tugas" {{ old('status_guru') == 'Tugas' ? 'selected' : '' }}>Tugas</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Materi Pembelajaran <span class="text-danger">*</span></label>
                            <input type="text" name="materi" class="form-control @error('materi') is-invalid @enderror" value="{{ old('materi') }}" placeholder="Masukkan materi pembelajaran" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Keterangan / Deskripsi Kegiatan</label>
                            <textarea name="keterangan" class="form-control @error('keterangan') is-invalid @enderror" rows="3" placeholder="Deskripsi kegiatan atau catatan khusus">{{ old('keterangan') }}</textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold">Jumlah Siswa Hadir <span class="text-danger">*</span></label>
                                <input type="number" name="jumlah_siswa_hadir" class="form-control @error('jumlah_siswa_hadir') is-invalid @enderror" value="{{ old('jumlah_siswa_hadir', 0) }}" min="0" required>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold">Semester <span class="text-danger">*</span></label>
                                <select name="semester" class="form-select @error('semester') is-invalid @enderror" required>
                                    <option value="Ganjil" {{ old('semester') == 'Ganjil' ? 'selected' : '' }}>Ganjil</option>
                                    <option value="Genap" {{ old('semester') == 'Genap' ? 'selected' : '' }}>Genap</option>
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold">Tahun Ajaran <span class="text-danger">*</span></label>
                                <input type="text" name="tahun_ajaran" class="form-control @error('tahun_ajaran') is-invalid @enderror" value="{{ old('tahun_ajaran', '2026/2027') }}" required>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between mt-4">
                            <a href="{{ route('jurnal.index') }}" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
                            <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> Simpan Data Jurnal</button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
