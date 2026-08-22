@extends('layouts.app')

@section('title', 'Kelola User - WebJournal Management System')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 800; font-size: 1.75rem;">Kelola User</h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">Kelola akun dan akses pengguna aplikasi.</p>
        </div>
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary rounded-3 px-3 py-2 fw-semibold shadow-sm">
            <i class="bi bi-plus-lg me-1"></i> Tambah User
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
            <strong class="d-block mb-1">Terjadi kesalahan:</strong>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
        <form action="{{ route('admin.users.index') }}" method="GET" class="row g-3 align-items-end">
            <div class="col-md-6">
                <label class="form-label fw-bold text-secondary text-uppercase small">Cari User</label>
                <input type="text" name="search" value="{{ request('search') }}" class="form-control bg-light rounded-3" placeholder="Nama, username, atau NIP">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold text-secondary text-uppercase small">Sub-Role</label>
                <select name="sub_role" class="form-select bg-light rounded-3">
                    <option value="">Semua Sub-Role</option>
                    @foreach($subRoles as $value => $label)
                        <option value="{{ $value }}" {{ request('sub_role') === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary rounded-3 w-100"><i class="bi bi-search me-1"></i> Cari</button>
            </div>
        </form>
    </div>

    <div class="table-card-custom mb-4">
        <div class="table-responsive">
            <table class="table table-custom align-middle">
                <thead>
                    <tr>
                        <th>NO</th>
                        <th>NAMA LENGKAP</th>
                        <th>USERNAME / NIP</th>
                        <th>SUB-ROLE</th>
                        <th>KODE AKTIVASI</th>
                        <th>STATUS</th>
                        <th class="text-end">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dataUsers as $user)
                        @php
                            $roleValue = $user->sub_role ?: 'petugas_tu';
                            $roleLabel = $subRoles[$roleValue] ?? $user->role_label;
                            $roleClass = [
                                'petugas_tu' => 'bg-primary-subtle text-primary',
                                'waka_kurikulum' => 'bg-success-subtle text-success',
                                'waka_sdm' => 'bg-info-subtle text-info',
                                'satpam' => 'bg-danger-subtle text-danger',
                            ][$roleValue] ?? 'bg-secondary-subtle text-secondary';
                        @endphp
                        <tr>
                            <td>{{ $dataUsers->firstItem() + $loop->index }}</td>
                            <td class="fw-semibold text-dark">{{ $user->nama }}</td>
                            <td>
                                <div class="fw-semibold text-dark">{{ $user->username }}</div>
                                <div class="text-muted small">NIP/NIK: {{ $user->nip ?: '-' }}</div>
                            </td>
                            <td><span class="badge {{ $roleClass }} px-2 py-2 rounded-3">{{ $roleLabel }}</span></td>
                            <td><code>{{ $user->kode_aktivasi ?: '-' }}</code></td>
                            <td><span class="badge {{ $user->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} px-2 py-2 rounded-3">{{ $user->is_active ? 'Aktif' : 'Tidak Aktif' }}</span></td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.users.edit', $user->id) }}" class="btn btn-sm btn-outline-warning rounded-3 me-1" title="Edit user"><i class="bi bi-pencil-square"></i></a>
                                <form action="{{ route('admin.users.reset-password', $user->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Reset password user ini ke username?')">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-info rounded-3 me-1" title="Reset password"><i class="bi bi-key"></i></button>
                                </form>
                                <form action="{{ route('admin.users.destroy', $user->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus user ini? Data yang sudah dihapus tidak dapat dipulihkan.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-3" title="Hapus user"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-people fs-1 d-block mb-2"></i>Belum ada data user.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-4 pt-3 border-top">
            <div class="text-muted small mb-3 mb-md-0">Menampilkan <strong>{{ $dataUsers->firstItem() ?? 0 }}</strong>-<strong>{{ $dataUsers->lastItem() ?? 0 }}</strong> dari <strong>{{ $dataUsers->total() }}</strong> user</div>
            {{ $dataUsers->links() }}
        </div>
    </div>
</div>
@endsection
