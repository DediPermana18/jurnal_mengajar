@extends('layouts.app')

@section('title', ($readonly ?? false) ? 'Detail User - WebJournal Management System' : 'Edit User - WebJournal Management System')

@section('content')
<div class="container-fluid px-0">
    <div class="mb-4">
        <a href="{{ route('admin.users.index') }}" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left me-1"></i> Kembali ke Kelola User</a>
        <h2 class="fw-black text-dark mt-2 mb-1" style="letter-spacing: -0.02em; font-weight: 800; font-size: 1.75rem;">{{ $readonly ? 'Detail User' : 'Edit User' }}</h2>
        <p class="text-muted mb-0" style="font-size: 0.9rem;">
            {{ $readonly ? 'Lihat identitas dan hak akses user ini (mode lihat saja).' : 'Perbarui identitas dan hak akses user.' }}
        </p>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4" role="alert">
            <strong class="d-block mb-1">Terjadi kesalahan:</strong>
            <ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if($readonly)
        <div class="alert alert-info border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-eye"></i>
            <div>
                <strong>Mode Lihat Saja.</strong> Perubahan kredensial hanya dikelola oleh Super Admin.
            </div>
        </div>
    @endif

    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
        <form id="formEditUser" action="{{ route('admin.users.update', $user->id) }}" method="POST">
            @include('admin.users._form', ['isEdit' => true, 'readonly' => $readonly])
        </form>
        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top align-items-center">
            <a href="{{ route('admin.users.index') }}" class="btn btn-light rounded-3 px-4">Kembali</a>
            @if(strtolower((string) $user->username) === 'admin')
                <span class="badge bg-secondary-subtle text-secondary rounded-pill px-3 py-2 align-self-center"
                      title="Akun utama (Administrator TU) tidak dapat di-suspend">
                    <i class="bi bi-shield-lock me-1"></i> Akun Utama (Tidak dapat di-suspend)
                </span>
            @elseif(($isProtectedAccount ?? false) && ! (auth()->user()?->isPrivilegedUserManager() ?? false))
                {{-- Akun Super Admin / Admin dilindungi dari Petugas TU biasa:
                     tombol Suspend Darurat disembunyikan (hanya Super Admin). --}}
                <span class="badge bg-dark-subtle text-dark rounded-pill px-3 py-2 align-self-center"
                      title="Akun Super Admin / Admin dilindungi — hanya Super Admin yang dapat mengelola akun ini">
                    <i class="bi bi-shield-lock me-1"></i> Akun Super Admin / Admin (Dilindungi)
                </span>
            @else
                @php
                    // Akun sedang disuspend (sementara via suspended_until > now ATAU
                    // nonaktif permanen via is_active = false) → tampilkan aksi
                    // UNSUSPEND saja (tanpa dropdown durasi). Akun aktif → tampilkan
                    // tombol "Suspend Darurat" + dropdown durasi (1 Jam / 1 Hari).
                    $editIsSuspended = $user->isCurrentlySuspended() || ! $user->is_active;
                @endphp
                @if($editIsSuspended)
                    @if($user->isCurrentlySuspended())
                        <span class="badge bg-danger-subtle text-danger rounded-pill px-3 py-2 align-self-center"
                              title="Suspend sementara aktif sampai {{ $user->suspended_until?->format('d M Y H:i') }}">
                            <i class="bi bi-shield-x me-1"></i> Sedang disuspend sementara s/d {{ $user->suspended_until?->format('H:i d/m/Y') }}
                        </span>
                    @else
                        <span class="badge bg-danger-subtle text-danger rounded-pill px-3 py-2 align-self-center"
                              title="Akun dinonaktifkan (suspend permanen)">
                            <i class="bi bi-shield-x me-1"></i> Akun Dinonaktifkan (Suspend Permanen)
                        </span>
                    @endif
                    {{-- Akun disuspend: tombol UNSUSPEND (hijau) — dropdown durasi disembunyikan. --}}
                    <form action="{{ route('admin.users.toggle-suspend', $user->id) }}" method="POST"
                          class="d-inline-flex align-items-center gap-2"
                          onsubmit="return confirm('Aktifkan kembali akun ini? Batas suspend akan dihapus dan status akun dikembalikan aktif.')">
                        @csrf
                        <input type="hidden" name="action" value="unsuspend">
                        <button type="submit" class="btn btn-outline-success rounded-3 px-4 fw-semibold">
                            <i class="bi bi-shield-check me-1"></i> Unsuspend / Aktifkan Kembali
                        </button>
                    </form>
                @else
                    {{-- Akun aktif: tombol SUSPEND DARURAT (merah) + dropdown durasi. --}}
                    <form action="{{ route('admin.users.toggle-suspend', $user->id) }}" method="POST"
                          class="d-inline-flex align-items-center gap-2" onsubmit="return confirm('Suspend sementara akun ini? Status aktif akan diblokir sampai batas durasi yang dipilih.')">
                        @csrf
                        <select name="duration" class="form-select form-select-sm rounded-3 w-auto" title="Durasi suspend sementara">
                            <option value="1h" selected>1 Jam</option>
                            <option value="1d">1 Hari</option>
                        </select>
                        <button type="submit" class="btn btn-outline-danger rounded-3 px-4 fw-semibold">
                            <i class="bi bi-shield-x me-1"></i> Suspend Darurat
                        </button>
                    </form>
                @endif
            @endif
            @if($readonly)
                <button type="submit" form="formEditUser" class="btn btn-light rounded-3 px-4 fw-semibold" disabled title="Form terkunci — perubahan hanya dikelola oleh Super Admin">
                    <i class="bi bi-lock-fill me-1"></i> Simpan Perubahan (Terkunci)
                </button>
            @else
                <button type="submit" form="formEditUser" class="btn btn-primary rounded-3 px-4 fw-semibold"><i class="bi bi-check-lg me-1"></i> Simpan Perubahan</button>
            @endif
        </div>
    </div>
</div>
@endsection