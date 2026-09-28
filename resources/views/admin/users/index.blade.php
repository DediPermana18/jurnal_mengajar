@extends('layouts.app')

@section('title', 'Kelola User - WebJournal Management System')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 800; font-size: 1.75rem;">Kelola User</h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">Kelola akun dan akses pengguna aplikasi.</p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <a href="{{ route('admin.users.create') }}" class="btn btn-primary rounded-3 px-3 py-2 fw-semibold shadow-sm">
                <i class="bi bi-plus-lg me-1"></i> Tambah User
            </a>
        </div>
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
                        <th class="whitespace-nowrap ps-3">NO</th>
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
                            $isCurrentUser = $user->id === auth()->id();
                            $viewerIsIt = (bool) (auth()->user()?->isPetugasIt() ?? false);
                            $viewerIsPrivileged = (bool) (auth()->user()?->isPrivilegedUserManager() ?? false);
                            // Super Admin dan Petugas IT dapat mengedit user lain.
                            $viewerCanEdit = $viewerIsIt || $viewerIsPrivileged;
                            // Kebijakan Hidden Super Admin: akun istimewa (Super Admin /
                            // Admin) tidak menampilkan tombol Edit/Delete/Suspend bagi
                            // Petugas TU biasa — hanya privilege manager (IT / Super Admin).
                            $isProtectedAccount = $user->isProtectedAccount();
                            $isProtectedHiddenActions = $isProtectedAccount && ! $viewerIsPrivileged;
                            // State suspend terpadu untuk tombol aksi: suspend sementara
                            // (suspended_until > now) ATAU nonaktif permanen (is_active=false).
                            $isRowSuspended = $user->isCurrentlySuspended() || ! $user->is_active;
                            $roleValue = $user->sub_role ?: 'petugas_tu';
                            $roleLabel = $subRoleLabels[$roleValue] ?? $user->role_label;
                            $isPrimaryAdmin = strtolower((string) $user->username) === 'admin';
                            // Gembok tombol suspend Akun Utama dibuka saat Mode Darurat aktif
                            // (session emergency_mode) ATAU aktor adalah akun hasil Emergency
                            // Takeover "Kartu As" (is_emergency_takeover). Sumber kebenaran
                            // otorisasi tetap di controller (abortIfPrimaryAdmin +
                            // isEmergencyPrimaryAdminOverride); flag ini hanya untuk UI.
                            $emergencyUnlock = session('emergency_mode')
                                || (bool) (auth()->user()?->is_emergency_takeover ?? false);
                            $roleClass = [
                                'petugas_tu' => 'bg-primary-subtle text-primary',
                                'waka_kurikulum' => 'bg-success-subtle text-success',
                                'waka_sdm' => 'bg-info-subtle text-info',
                                'satpam' => 'bg-danger-subtle text-danger',
                                'super_admin' => 'bg-dark-subtle text-dark',
                                'admin' => 'bg-dark-subtle text-dark',
                            ][$roleValue] ?? 'bg-secondary-subtle text-secondary';
                        @endphp
                        <tr class="{{ $isCurrentUser ? 'table-info' : '' }}">
                            <td class="whitespace-nowrap ps-3">{{ $dataUsers->firstItem() + $loop->index }}</td>
                            <td class="fw-semibold text-dark">
                                {{ $user->nama }}
                                @if($isCurrentUser)
                                    <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill ms-2 align-middle" style="font-size: 0.62rem;"
                                          title="Ini adalah akun yang sedang Anda gunakan">
                                        <i class="bi bi-person-check-fill me-1"></i>Akun Anda Saat Ini
                                    </span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap">
                                <div class="fw-semibold text-dark">{{ $user->username }}</div>
                                @if($user->nip)
                                    <div class="text-muted small">NIP/NIK: {{ $user->nip }}</div>
                                @endif
                            </td>
                            <td class="whitespace-nowrap"><span class="badge {{ $roleClass }} px-2 py-2 rounded-3">{{ $roleLabel }}</span></td>
                            <td class="whitespace-nowrap"><code class="font-monospace" title="Tersensor — hanya Petugas IT yang dapat melihat kode utuh">{{ $user->kode_aktivasi_masked }}</code></td>
                            <td class="whitespace-nowrap">
                                @if($user->isCurrentlySuspended())
                                    <span class="badge bg-danger-subtle text-danger px-2 py-2 rounded-3"
                                          title="Suspend sementara aktif sampai {{ $user->suspended_until?->format('d M Y H:i') }}">
                                        <i class="bi bi-shield-x me-1"></i> Suspended (s/d {{ $user->suspended_until?->format('H:i') }})
                                    </span>
                                @else
                                    <span class="badge {{ $user->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} px-2 py-2 rounded-3">{{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                @endif
                            </td>
                            <td class="text-end whitespace-nowrap">
                                <div class="flex items-center justify-center gap-2 whitespace-nowrap">
                                @if($isCurrentUser)
                                    {{-- Akun Anda saat ini: tombol Edit/Detail profil diizinkan, tombol Suspend/Hapus disembunyikan. --}}
                                    <div class="d-inline-flex align-items-center gap-2">
                                        <a href="{{ route('admin.users.edit', $user->id) }}" class="btn btn-sm btn-outline-warning rounded-3" title="Edit Profil / Data Saya">
                                            <i class="bi bi-pencil-square me-1"></i> Edit Profil
                                        </a>
                                    </div>
                                @elseif($isProtectedHiddenActions)
                                    {{-- Akun Super Admin / Admin dilindungi: tidak ada tombol
                                         Edit/Delete/Suspend untuk Petugas TU biasa. --}}
                                    <span class="badge bg-dark-subtle text-dark rounded-pill px-3 py-2"
                                          style="font-size: 0.68rem;"
                                          title="Akun Super Admin / Admin dilindungi — hanya Super Admin yang dapat mengelola akun ini">
                                        <i class="bi bi-shield-lock me-1"></i>Super Admin / Dilindungi
                                    </span>
                                @else
                                <div class="d-inline-flex align-items-center gap-2">
                                    <a href="{{ route('admin.users.edit', $user->id) }}" class="btn btn-sm btn-outline-info rounded-3" title="Lihat detail user">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    @if($viewerCanEdit)
                                        <a href="{{ route('admin.users.edit', $user->id) }}" class="btn btn-sm btn-outline-warning rounded-3" title="Edit user">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <form action="{{ route('admin.users.toggle-status', $user->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Ubah status aktif user ini?')">
                                            @csrf
                                            <button type="submit" class="btn btn-sm {{ $user->is_active ? 'btn-outline-secondary' : 'btn-outline-success' }} rounded-3" title="{{ $user->is_active ? 'Nonaktifkan user' : 'Aktifkan user' }}">
                                                <i class="bi {{ $user->is_active ? 'bi-slash-circle' : 'bi-check-circle' }}"></i>
                                            </button>
                                        </form>
                                        <form action="{{ route('admin.users.destroy', $user->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus user ini? Data yang di-soft delete akan disembunyikan dari sistem.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-3" title="Hapus user"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @endif
                                    @if($isPrimaryAdmin && ! $emergencyUnlock)
                                        <span class="badge bg-secondary-subtle text-secondary rounded-pill" style="font-size: 0.68rem;"
                                              title="Akun utama (Administrator TU) tidak dapat di-suspend">
                                            <i class="bi bi-shield-lock me-1"></i>Akun Utama (Tidak dapat di-suspend)
                                        </span>
                                    @elseif($isRowSuspended)
                                        {{-- User sedang disuspend (sementara/permanen): tombol hijau
                                             "Unsuspend" — tanpa dropdown durasi, langsung reaktivasi. --}}
                                        <form action="{{ route('admin.users.toggle-suspend', $user->id) }}" method="POST"
                                              class="d-inline" onsubmit="return confirm('Aktifkan kembali akun ini? Batas suspend akan dihapus dan status akun dikembalikan aktif.')">
                                            @csrf
                                            <input type="hidden" name="action" value="unsuspend">
                                            <button type="submit"
                                                    class="btn btn-sm btn-outline-success fw-semibold rounded-3"
                                                    title="Unsuspend — aktifkan kembali akun ini">
                                                <i class="bi bi-shield-check me-1"></i> Unsuspend
                                            </button>
                                        </form>
                                    @else
                                        <button type="button"
                                                class="btn btn-sm btn-outline-danger fw-semibold rounded-3"
                                                title="Suspend darurat — ubah status aktif akun"
                                                data-bs-toggle="modal"
                                                data-bs-target="#emergencySuspendModal"
                                                data-suspend-nama="{{ $user->nama }}"
                                                data-suspend-primary="{{ $isPrimaryAdmin ? '1' : '0' }}"
                                                data-suspend-url="{{ route('admin.users.toggle-suspend', $user->id) }}">
                                            <i class="bi bi-shield-x me-1"></i> Suspend Darurat
                                        </button>
                                    @endif
                                </div>
                                @endif
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

{{-- ===== MODAL KONFIRMASI SUSPEND DARURAT ===== --}}
<div class="modal fade" id="emergencySuspendModal" tabindex="-1" aria-labelledby="emergencySuspendModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-0 bg-danger-subtle text-danger">
                <h5 class="modal-title fw-bold" id="emergencySuspendModalLabel">
                    <i class="bi bi-shield-x me-2"></i>Suspend Darurat Akun
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <form id="formEmergencySuspend" method="POST" action="#">
                @csrf
                <div class="modal-body pt-3">
                    <p class="mb-0">
                        Apakah Anda yakin ingin menyuspend sementara akun
                        <strong class="text-danger" id="suspendTargetNama">ini</strong>?
                    </p>
                    <div id="emergencySuspendPrimaryWarning" class="alert alert-danger border-0 rounded-3 small mt-3 mb-0 d-none" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        <strong>PERINGATAN DARURAT:</strong> Karena akun utama (Administrator TU) di-suspend dalam
                        Mode Darurat, <strong>seluruh sesi aktif akun ini akan langsung dimatikan</strong> (penyadap
                        langsung ter-logout). Akses kendali akun utama hanya dapat dipulihkan oleh pengendali
                        darurat (Super Admin hasil Takeover / Mode Darurat).
                    </div>
                    <label class="form-label fw-semibold text-secondary small mt-3 mb-1">DURASI SUSPEND</label>
                    <select name="duration" class="form-select rounded-3">
                        <option value="1h" selected>1 Jam</option>
                        <option value="1d">1 Hari (24 Jam)</option>
                    </select>
                    <p class="mb-0 mt-2 text-muted" style="font-size: 0.85rem;">
                        Akun akan otomatis kembali aktif setelah durasi berlalu.
                    </p>
                </div>
                <div class="modal-footer border-0 pb-4">
                    <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger rounded-3 px-4 fw-semibold">
                        <i class="bi bi-shield-x me-1"></i> Ya, Suspend Sementara
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('emergencySuspendModal');
        const form = document.getElementById('formEmergencySuspend');
        const primaryWarning = document.getElementById('emergencySuspendPrimaryWarning');
        if (!modal || !form) return;

        modal.addEventListener('show.bs.modal', function (event) {
            const trigger = event.relatedTarget;
            if (!trigger) return;

            const nama = trigger.getAttribute('data-suspend-nama') || 'ini';
            const url = trigger.getAttribute('data-suspend-url') || '#';
            const isPrimary = trigger.getAttribute('data-suspend-primary') === '1';

            document.getElementById('suspendTargetNama').textContent = nama;
            form.setAttribute('action', url);

            if (primaryWarning) {
                // Tampilkan peringatan "semua sesi aktif dimatikan" hanya untuk akun utama.
                primaryWarning.classList.toggle('d-none', !isPrimary);
            }
        });
    });
</script>
@endpush

