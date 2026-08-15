@extends('layouts.app')

@section('title', 'Catatan Siswa Bermasalah - Wali Kelas')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-black text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 800; font-size: 1.75rem;">
                ⚠️ Catatan Siswa Bermasalah
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                Daftar siswa yang memerlukan tindakan / tindak lanjut karena indikasi absen/perilaku.
            </p>
        </div>
        <button class="btn btn-warning rounded-3 text-dark fw-bold shadow-sm" onclick="alert('Tambah Catatan Kendala Siswa')">
            <i class="bi bi-plus-circle me-1"></i> Tambah Catatan Siswa
        </button>
    </div>

    <div class="table-card-custom mb-4">
        <div class="table-responsive">
            <table class="table table-custom align-middle">
                <thead>
                    <tr>
                        <th>Siswa</th>
                        <th>Kategori</th>
                        <th>Jumlah Kejadian</th>
                        <th>Keterangan / Catatan Wali Kelas</th>
                        <th>Status Tindak Lanjut</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <strong class="d-block text-dark">Budi Setiawan</strong>
                            <small class="text-muted">NIS: 10928372</small>
                        </td>
                        <td><span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill">Alpha > 3x</span></td>
                        <td>3 Kali Sesi</td>
                        <td>Sering tidak masuk tanpa keterangan pada jam pertama.</td>
                        <td><span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i> Perlu Panggilan Orang Tua</span></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-primary rounded-2">Panggil Ortu</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
