@extends('layouts.app')

@section('title', 'Data Master Guru - WebJournal Management System')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 800; font-size: 1.75rem;">Data Master Guru</h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">Kelola data guru pengajar dan wali kelas.</p>
        </div>
        @if(in_array(auth()->user()->role ?? '', ['admin_tu', 'admin', 'super_admin']))
            <a href="{{ route('admin.guru.create') }}" class="btn btn-primary rounded-3 px-3 py-2 fw-semibold shadow-sm"><i class="bi bi-plus-lg me-1"></i> Tambah Guru</a>
        @endif
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert"><i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert"><strong class="d-block mb-1">Gagal memproses data:</strong><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
    @endif

    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
        <form action="{{ route('guru.index') }}" method="GET" class="row g-3">
            <div class="col-md-6"><label class="form-label fw-bold text-secondary text-uppercase small">Pencarian</label><input type="text" name="search" value="{{ request('search') }}" class="form-control bg-light rounded-3" placeholder="Cari nama atau NIP"></div>
            <div class="col-md-3"><label class="form-label fw-bold text-secondary text-uppercase small">Status</label><select name="status" class="form-select bg-light rounded-3" onchange="this.form.submit()"><option value="Semua Status">Semua Status</option><option value="Aktif" {{ request('status') === 'Aktif' ? 'selected' : '' }}>Aktif</option><option value="Tidak Aktif" {{ request('status') === 'Tidak Aktif' ? 'selected' : '' }}>Tidak Aktif</option></select></div>
            <div class="col-md-3"><label class="form-label fw-bold text-secondary text-uppercase small">Wali Kelas</label><select name="wali_kelas" class="form-select bg-light rounded-3" onchange="this.form.submit()"><option value="Semua">Semua</option><option value="Ya" {{ request('wali_kelas') === 'Ya' ? 'selected' : '' }}>Ya</option><option value="Tidak" {{ request('wali_kelas') === 'Tidak' ? 'selected' : '' }}>Tidak</option></select></div>
        </form>
    </div>

    <div class="table-card-custom mb-4">
        <div class="table-responsive">
            <table class="table table-custom align-middle">
                <thead><tr><th style="width: 28%;">GURU</th><th style="width: 28%;">MATA PELAJARAN</th><th style="width: 18%;">WALI KELAS</th><th style="width: 10%;">STATUS</th><th class="text-end" style="width: 16%;">AKSI</th></tr></thead>
                <tbody>
                    @forelse($dataGuru as $guru)
                        @php
                            $words = explode(' ', trim($guru->nama));
                            $initials = strtoupper(substr($words[0], 0, 1));
                            $initials .= count($words) > 1 ? strtoupper(substr(end($words), 0, 1)) : strtoupper(substr($words[0], 1, 1));
                            $mapelNames = collect();
                            if ($guru->jadwalPelajaran && $guru->jadwalPelajaran->isNotEmpty()) {
                                $mapelNames = $mapelNames->concat($guru->jadwalPelajaran->map(fn($jp) => $jp->mataPelajaran?->nama_mapel)->filter());
                            }
                            $namaKelasWali = $guru->kelasWali?->pluck('nama_kelas')->join(', ') ?: $guru->kelas?->nama_kelas;
                        @endphp
                        <tr>
                            <td><div class="d-flex align-items-center gap-3"><div class="rounded-circle bg-secondary-subtle text-secondary fw-bold d-flex align-items-center justify-content-center shrink-0" style="width: 44px; height: 44px;">{{ $initials }}</div><div><div class="fw-bold text-dark">{{ $guru->nama }}</div><div class="text-muted small">NIP: {{ $guru->nip ?: '-' }}</div></div></div></td>
                            <td>{{ $mapelNames->unique()->join(', ') ?: '-' }}</td>
                            <td>{{ $namaKelasWali ? 'Wali Kelas ' . $namaKelasWali : '-' }}</td>
                            <td><span class="badge {{ $guru->is_active ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning-emphasis' }} rounded-pill px-3 py-2">{{ $guru->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                            <td class="text-end text-nowrap">
                                @if(in_array(auth()->user()->role ?? '', ['admin_tu', 'admin', 'super_admin']))
                                    <a href="{{ route('admin.guru.edit', $guru->id) }}" class="btn btn-sm btn-outline-warning rounded-3 me-1" title="Edit guru"><i class="bi bi-pencil-square"></i></a>
                                    <form action="{{ route('guru.reset-password', $guru->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Reset password guru ini ke password default?')">@csrf<button type="submit" class="btn btn-sm btn-outline-info rounded-3 me-1" title="Reset password"><i class="bi bi-key"></i></button></form>
                                    @if(!$guru->is_active)
                                        <form action="{{ route('guru.approve', $guru->id) }}" method="POST" class="d-inline">@csrf<button type="submit" class="btn btn-sm btn-outline-success rounded-3 me-1" title="Aktifkan guru"><i class="bi bi-check-circle"></i></button></form>
                                    @else
                                        <form action="{{ route('guru.toggle-status', $guru->id) }}" method="POST" class="d-inline">@csrf<button type="submit" class="btn btn-sm btn-outline-secondary rounded-3 me-1" title="Nonaktifkan guru"><i class="bi bi-slash-circle"></i></button></form>
                                    @endif
                                    <form action="{{ route('guru.destroy', $guru->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus data guru ini?')">@csrf @method('DELETE')<button type="submit" class="btn btn-sm btn-outline-danger rounded-3" title="Hapus guru"><i class="bi bi-trash"></i></button></form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-person-badge fs-1 d-block mb-2"></i>Tidak ada data guru yang sesuai.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-4 pt-3 border-top"><div class="text-muted small mb-3 mb-md-0">Menampilkan <strong>{{ $dataGuru->firstItem() ?? 0 }}</strong>-<strong>{{ $dataGuru->lastItem() ?? 0 }}</strong> dari <strong>{{ $dataGuru->total() }}</strong> Guru</div>{{ $dataGuru->links() }}</div>
    </div>
</div>
@endsection
