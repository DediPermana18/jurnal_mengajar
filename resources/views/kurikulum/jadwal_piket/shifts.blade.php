@extends('layouts.app')

@section('title', 'Pengaturan Shift Piket')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold text-dark mb-1">Pengaturan Shift Piket</h2>
            <p class="text-muted mb-0">Atur nama shift, jam bertugas, dan kuota petugas.</p>
        </div>
        <a href="{{ route('kurikulum.jadwal-piket.index') }}" class="btn btn-light border rounded-3">
            <i class="bi bi-arrow-left me-1"></i> Jadwal Piket
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4"><h5 class="mb-0 fw-bold">Tambah Shift</h5></div>
        <div class="card-body px-4">
            <form method="POST" action="{{ route('kurikulum.jadwal-piket.shifts.store') }}" class="row g-3 align-items-end">
                @csrf
                <div class="col-12 col-md-3"><label class="form-label">Nama Shift</label><input name="nama" class="form-control" required placeholder="Contoh: Jumat"></div>
                <div class="col-6 col-md-2"><label class="form-label">Mulai</label><input type="time" name="jam_mulai" class="form-control" required></div>
                <div class="col-6 col-md-2"><label class="form-label">Selesai</label><input type="time" name="jam_selesai" class="form-control" required></div>
                <div class="col-6 col-md-2"><label class="form-label">Maks. Petugas</label><input type="number" name="maksimal_petugas" class="form-control" min="1" max="100" value="4" required></div>
                <div class="col-6 col-md-1"><label class="form-label">Urutan</label><input type="number" name="urutan" class="form-control" min="0" value="0"></div>
                <div class="col-12 col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-plus-lg me-1"></i> Tambah</button></div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th class="ps-4">Shift</th><th>Jam</th><th>Kuota</th><th>Status</th><th class="text-end pe-4">Aksi</th></tr></thead>
                <tbody>
                @forelse($shifts as $shift)
                    <tr>
                        <form method="POST" action="{{ route('kurikulum.jadwal-piket.shifts.update', $shift) }}">
                            @csrf @method('PUT')
                            <td class="ps-4"><input name="nama" value="{{ $shift->nama }}" class="form-control" required></td>
                            <td><div class="d-flex gap-2"><input type="time" name="jam_mulai" value="{{ substr($shift->jam_mulai, 0, 5) }}" class="form-control" required><input type="time" name="jam_selesai" value="{{ substr($shift->jam_selesai, 0, 5) }}" class="form-control" required></div></td>
                            <td><input type="number" name="maksimal_petugas" value="{{ $shift->maksimal_petugas }}" class="form-control" min="1" max="100" required></td>
                            <td><div class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" {{ $shift->is_active ? 'checked' : '' }}> <label class="form-check-label">Aktif</label></div></td>
                            <td class="text-end pe-4"><input type="hidden" name="urutan" value="{{ $shift->urutan }}"><button class="btn btn-sm btn-outline-primary"><i class="bi bi-save"></i></button></form>
                                <form method="POST" action="{{ route('kurikulum.jadwal-piket.shifts.destroy', $shift) }}" class="d-inline ms-1" onsubmit="return confirm('Hapus shift ini?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                            </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">Belum ada shift.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
