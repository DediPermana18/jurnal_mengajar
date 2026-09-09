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

    <div class="card border-0 shadow-sm rounded-4 p-3.5 bg-white mb-4">
        <form action="{{ route('admin.users.index') }}" method="GET" class="d-flex flex-column gap-3">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <div class="flex-grow-1 position-relative" style="min-width: 240px; max-width: 450px;">
                    <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted" style="font-size: 0.9rem;"></i>
                    <input type="text"
                           name="search"
                           id="searchUserInput"
                           value="{{ request('search') }}"
                           class="form-control bg-light rounded-3 ps-5"
                           placeholder="Cari nama, username, atau NIP user...">
                </div>
                <div style="width: 200px;">
                    <select name="sub_role" id="subRoleSelect" class="form-select bg-light rounded-3" onchange="this.form.submit()">
                        <option value="">Semua Sub-Role</option>
                        @foreach($subRoles as $value => $label)
                            <option value="{{ $value }}" {{ request('sub_role') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="width: 170px;">
                    <select name="status" id="statusSelect" class="form-select bg-light rounded-3" onchange="this.form.submit()">
                        <option value="Semua Status">Semua Status</option>
                        <option value="Aktif" {{ request('status') === 'Aktif' ? 'selected' : '' }}>Aktif</option>
                        <option value="Nonaktif" {{ request('status') === 'Nonaktif' || request('status') === 'Tidak Aktif' ? 'selected' : '' }}>Nonaktif</option>
                    </select>
                </div>
            </div>

            {{-- Indikator Filter Aktif & Reset --}}
            @if(request()->hasAny(['search', 'sub_role', 'status']) && (request('search') || request('sub_role') || (request('status') && request('status') !== 'Semua Status')))
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 pt-2 border-top" style="border-color: #f1f5f9 !important;">
                    <div class="d-flex flex-wrap align-items-center gap-1.5" style="font-size: 0.8rem; color: #64748b;">
                        <span class="fw-semibold text-dark"><i class="bi bi-funnel-fill text-primary me-1"></i>Filter Aktif:</span>
                        @if(request('search'))
                            <span class="badge bg-light text-dark border px-2 py-1">Pencarian: "{{ request('search') }}"</span>
                        @endif
                        @if(request('sub_role'))
                            <span class="badge bg-light text-primary border border-primary-subtle px-2 py-1">Sub-Role: {{ $subRoles[request('sub_role')] ?? request('sub_role') }}</span>
                        @endif
                        @if(request('status') && request('status') !== 'Semua Status')
                            <span class="badge bg-light text-success border border-success-subtle px-2 py-1">Status: {{ request('status') }}</span>
                        @endif
                    </div>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-sm btn-outline-secondary rounded-2 px-2 py-1 text-decoration-none d-inline-flex align-items-center gap-1" style="font-size: 0.78rem;">
                        <i class="bi bi-arrow-counterclockwise"></i>
                        <span>Reset Filter</span>
                    </a>
                </div>
            @endif
        </form>
    </div>

    <div class="table-card-custom mb-4">
        <div class="table-responsive w-full overflow-x-auto">
            <table class="table table-custom align-middle min-w-full" id="tableManageUsers">
                <thead>
                    <tr>
                        <th class="whitespace-nowrap">NO</th>
                        <th>NAMA LENGKAP</th>
                        <th class="whitespace-nowrap">USERNAME</th>
                        <th class="whitespace-nowrap">SUB-ROLE</th>
                        <th class="whitespace-nowrap">KODE AKTIVASI</th>
                        <th class="whitespace-nowrap">STATUS</th>
                        <th class="text-end whitespace-nowrap">AKSI</th>
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
                            <td class="whitespace-nowrap">{{ $dataUsers->firstItem() + $loop->index }}</td>
                            <td class="fw-semibold text-dark">{{ $user->nama }}</td>
                            <td class="whitespace-nowrap">
                                <div class="fw-semibold text-dark">{{ $user->username }}</div>
                                @if($user->nip)
                                    <div class="text-muted small">NIP/NIK: {{ $user->nip }}</div>
                                @endif
                            </td>
                            <td class="whitespace-nowrap"><span class="badge {{ $roleClass }} px-2 py-2 rounded-3">{{ $roleLabel }}</span></td>
                            <td class="whitespace-nowrap"><code>{{ $user->kode_aktivasi ?: '-' }}</code></td>
                            <td class="whitespace-nowrap"><span class="badge {{ $user->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} px-2 py-2 rounded-3">{{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                            <td class="text-end whitespace-nowrap">
                                <div class="flex items-center justify-center gap-2 whitespace-nowrap">
                                <a href="{{ route('admin.users.edit', $user->id) }}" class="btn btn-sm btn-outline-warning rounded-3" title="Edit user"><i class="bi bi-pencil-square"></i></a>
                                <form action="{{ route('admin.users.toggle-status', $user->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Ubah status aktif user ini?')">
                                    @csrf
                                    <button type="submit" class="btn btn-sm {{ $user->is_active ? 'btn-outline-secondary' : 'btn-outline-success' }} rounded-3" title="{{ $user->is_active ? 'Nonaktifkan user' : 'Aktifkan user' }}">
                                        <i class="bi {{ $user->is_active ? 'bi-slash-circle' : 'bi-check-circle' }}"></i>
                                    </button>
                                </form>
                                <form action="{{ route('admin.users.reset-password', $user->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Reset password user ini ke username?')">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-info rounded-3" title="Reset password"><i class="bi bi-key"></i></button>
                                </form>
                                <form action="{{ route('admin.users.destroy', $user->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus user ini? Data yang di-soft delete akan disembunyikan dari sistem.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-3" title="Hapus user"><i class="bi bi-trash"></i></button>
                                </form>
                                </div>
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

