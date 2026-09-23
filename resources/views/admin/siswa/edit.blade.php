@extends('admin.layouts.app')

@section('title', 'Edit Data Siswa - WebJournal')

@section('content')
<div class="container-fluid px-0" style="max-width: 760px;">
    @php
        // Sync filter Tingkatan dengan form Tambah: deteksi otomatis tingkatan
        // dari kelas siswa yang sedang diedit (memakai old() agar tetap konsisten
        // saat validasi gagal dan form di-render ulang).
        $selectedKelasId = old('id_kelas', $siswa->id_kelas);
        $selectedKelas   = $dataKelas->firstWhere('id', $selectedKelasId);
        $initialTingkat  = $selectedKelas ? (string) $selectedKelas->tingkat : '';
        $dataTingkatan   = $dataKelas->pluck('tingkat')->filter()->unique()->sort()->values();
    @endphp
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-1">Edit Data Siswa</h3>
            <p class="text-muted small mb-0">Perbarui informasi data siswa di bawah ini.</p>
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

    <fieldset>
        <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
        <form action="{{ route('siswa.update', $siswa->id) }}" method="POST">
            @csrf
            @method('PUT')
            
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-dark mb-1">NIS (Nomor Induk Sekolah) <span class="text-muted fw-normal">(Opsional)</span></label>
                    <input type="text" name="nis" class="form-control rounded-3 py-2" value="{{ old('nis', $siswa->nis) }}" maxlength="20" placeholder="Masukkan NIS lokal sekolah">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-dark mb-1">NISN (Nasional) <span class="text-danger">*</span></label>
                    <input type="text" name="nisn" class="form-control rounded-3 py-2" value="{{ old('nisn', $siswa->nisn) }}" required maxlength="10" placeholder="Masukkan 10 digit NISN">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold text-dark">Nama Siswa <span class="text-danger">*</span></label>
                <input type="text" name="nama" class="form-control rounded-3 py-2" value="{{ old('nama', $siswa->nama) }}" required placeholder="Masukkan Nama Lengkap Siswa">
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold text-dark" for="filter_tingkat">
                        Tingkatan Kelas <span class="text-muted fw-normal">(Opsional)</span>
                    </label>
                    <select id="filter_tingkat" class="form-select rounded-3 py-2" autocomplete="off">
                        <option value="">-- Semua Tingkatan --</option>
                        @foreach ($dataTingkatan as $tingkatan)
                            <option value="{{ $tingkatan }}" {{ $tingkatan === $initialTingkat ? 'selected' : '' }}>{{ $tingkatan }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label fw-semibold text-dark">Kelas <span class="text-danger">*</span></label>
                    <select name="id_kelas" id="id_kelas" class="form-select rounded-3 py-2" required>
                        <option value="">-- Pilih Kelas --</option>
                        @foreach ($dataKelas as $kelas)
                            <option value="{{ $kelas->id }}" data-tingkatan="{{ $kelas->tingkat }}"
                                    {{ $selectedKelasId == $kelas->id ? 'selected' : '' }}
                                    {{ $initialTingkat !== '' && (string) $kelas->tingkat !== $initialTingkat ? 'hidden' : '' }}>
                                {{ $kelas->nama_lengkap }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text text-muted">
                        <i class="bi bi-info-circle me-1"></i>Jurusan dan Tingkatan siswa otomatis menyesuaikan berdasarkan kelas yang dipilih.
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold text-dark d-block">Jenis Kelamin <span class="text-danger">*</span></label>
                <div class="d-flex gap-4">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="jenis_kelamin" id="jk_l" value="L" {{ old('jenis_kelamin', $siswa->jenis_kelamin) == 'L' ? 'checked' : '' }} required>
                        <label class="form-check-label" for="jk_l">Laki-laki</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="jenis_kelamin" id="jk_p" value="P" {{ old('jenis_kelamin', $siswa->jenis_kelamin) == 'P' ? 'checked' : '' }} required>
                        <label class="form-check-label" for="jk_p">Perempuan</label>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('siswa.index') }}" class="btn btn-light border rounded-3 px-4 py-2">Batal</a>
                <button type="submit" class="btn btn-primary rounded-3 px-4 py-2 fw-semibold">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</fieldset>

</div>
@push('scripts')
<script>
    // Sync 2 arah Tingkatan Kelas <-> Kelas (identik dengan form Tambah Siswa).
    document.addEventListener('DOMContentLoaded', function () {
        const filterTingkat = document.getElementById('filter_tingkat');
        const selectKelas   = document.getElementById('id_kelas');
        if (!filterTingkat || !selectKelas) return;

        /**
         * Terapkan visibilitas opsi kelas sesuai filter tingkatan.
         * @param {boolean} resetKelas - true = reset pilihan kelas (saat filter berubah).
         */
        function syncKelasOptions(resetKelas) {
            const chosen = filterTingkat.value; // '' = semua tingkatan
            if (resetKelas) selectKelas.value = '';

            Array.from(selectKelas.options).forEach(function (opt) {
                if (opt.value === '') return; // opsi placeholder "-- Pilih Kelas --"
                const tingkat = opt.getAttribute('data-tingkatan') || '';
                opt.hidden = (chosen !== '' && tingkat !== chosen);
            });
        }

        // Arah 1 (Filter): pilih Tingkatan -> filter opsi Kelas (+ reset pilihan Kelas).
        filterTingkat.addEventListener('change', function () {
            syncKelasOptions(true);
        });

        // Arah 2 (Auto-Select): pilih Kelas langsung -> sinkronkan nilai Tingkatan.
        selectKelas.addEventListener('change', function () {
            const selected = selectKelas.options[selectKelas.selectedIndex];
            if (!selected || selected.value === '') return;

            const tingkat = selected.getAttribute('data-tingkatan') || '';
            if (tingkat !== '') {
                filterTingkat.value = tingkat;   // ikuti tingkatan kelas yang dipilih
                syncKelasOptions(false);         // sembunyikan kelas di luar tingkatan tsb, tanpa reset pilihan
            }
        });

        // Muat awal: terapkan visibilitas opsi sesuai tingkatan terdeteksi,
        // sehingga hanya kelas-kelas setingkat dengan siswa yang tampil.
        syncKelasOptions(false);
    });
</script>
@endpush
@endsection
