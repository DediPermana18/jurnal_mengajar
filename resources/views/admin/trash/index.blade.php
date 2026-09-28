@extends('layouts.app')

@section('title', 'Data Terhapus (Recycle Bin) - WebJournal Management System')

@push('styles')
<style>
    /* ==== Recycle Bin ==== */

    /* Ikon header (rose-soft) */
    .trash-header-icon {
        width: 3rem;
        height: 3rem;
        border-radius: 0.85rem;
        background: #fff1f2;
        border: 1px solid #fecdd3;
        color: #e11d48;
        flex-shrink: 0;
    }

    /* Badge pill total record terhapus (di samping judul) */
    .trash-total-badge {
        background: #fff1f2;
        border: 1px solid #fecdd3;
        color: #be123c;
        font-size: 0.78rem;
        font-weight: 700;
        padding: 0.45em 0.9em;
        vertical-align: middle;
    }

    /* Sub-Nav Bar kategori (pill container slate-100) */
    .trash-tabs {
        position: relative;
        z-index: 10;
        background: #f1f5f9;
        border: 1px solid #eef2f6;
        border-radius: 14px;
        padding: 0.5rem;
        gap: 0.25rem;
        flex-wrap: nowrap;
        overflow-x: auto;
        overflow-y: hidden;
        scrollbar-width: none;
        margin-bottom: 1.5rem;
    }
    .trash-tabs::-webkit-scrollbar {
        display: none;
    }
    .trash-tabs .nav-link {
        border: none;
        border-radius: 10px;
        color: #64748b;
        font-weight: 600;
        font-size: 0.85rem;
        padding: 0.5rem 0.85rem;
        white-space: nowrap;
        flex: 0 0 auto;
        background: transparent;
        transition: color 0.15s ease, background-color 0.15s ease, box-shadow 0.15s ease;
    }
    .trash-tabs .nav-link:hover {
        color: #1e293b;
        background: #e2e8f0;
    }
    .trash-tabs .nav-link.active {
        color: #1d4ed8;
        background: #ffffff;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
    }
    .trash-tabs .badge {
        font-size: 0.68rem;
        font-weight: 600;
        padding: 0.35em 0.6em;
        vertical-align: 1px;
    }
    /* Counter 0 → muted; ada data → rose solid */
    .trash-tabs .badge.trash-badge-has {
        background-color: #f43f5e;
        color: #ffffff;
        font-weight: 700;
    }
    .trash-tabs .badge.trash-badge-empty {
        background-color: #e2e8f0;
        color: #94a3b8;
    }

    /* Pane Tab sebagai kolom flex — jarak antar elemen konsisten via gap,
       mencegah "Pilih Semua" & card melayang karena tumpukan margin. */
    .tab-content .tab-pane.trash-tab-pane.active {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .trash-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.9rem;
        background: #ffffff;
        border: 1px solid #e8eef5;
        border-radius: 12px;
        padding: 0.7rem 1rem;
        box-shadow: 0 1px 4px rgba(15, 23, 42, 0.04);
        transition: box-shadow 0.15s ease, border-color 0.15s ease;
    }
    .trash-row:hover {
        border-color: #cbd5e1;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.07);
    }
    .trash-row-info {
        min-width: 0;
    }
    .trash-row-actions {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-shrink: 0;
    }
    @media (max-width: 575.98px) {
        .trash-row {
            flex-direction: column;
            align-items: stretch;
        }
        .trash-row-actions {
            justify-content: flex-end;
        }
    }

    .empty-state {
        text-align: center;
        padding: 4rem 1rem;
        color: #94a3b8;
    }
    .empty-state i {
        font-size: 1.6rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 4.5rem;
        height: 4.5rem;
        border-radius: 1.25rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        color: #cbd5e1;
        margin-bottom: 1rem;
    }

    /* Panel tip data terhapus untuk konfirmasi */
    .trash-record-box {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 0.85rem 1rem;
    }

    /* ==== Tabel kategori User / Pengguna ==== */
    .trash-user-table th {
        background: #f8fafc;
        color: #475569;
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        border-bottom: 1px solid #e8eef5;
        padding-top: 0.8rem;
        padding-bottom: 0.8rem;
        white-space: nowrap;
    }
    .trash-user-table td {
        border-color: #eef2f6;
        padding-top: 0.7rem;
        padding-bottom: 0.7rem;
        vertical-align: middle;
    }
    .trash-user-table tbody tr:hover {
        background: #f8fafc;
    }
    .user-avatar {
        width: 2.25rem;
        height: 2.25rem;
        border-radius: 0.65rem;
        background: #eff6ff;
        border: 1px solid #dbeafe;
        color: #1d4ed8;
        font-size: 0.8rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .user-role-badge {
        background: #eef2ff;
        border: 1px solid #e0e7ff;
        color: #4338ca;
        font-size: 0.72rem;
        font-weight: 600;
        padding: 0.4em 0.75em;
        white-space: nowrap;
    }

    /* ==== Bulk Selection ==== */
    .trash-select-bar {
        background: #f8fafc;
        border: 1px solid #eef2f6;
        border-radius: 12px;
        padding: 0.45rem 0.8rem;
    }
    .select-all-label {
        cursor: pointer;
        user-select: none;
    }

    /* Checkbox accent rose (selaras dengan tema Recycle Bin) */
    .trash-select-bar .form-check-input:checked,
    .trash-check .form-check-input:checked,
    .trash-user-table .form-check-input:checked {
        background-color: #f43f5e;
        border-color: #f43f5e;
    }

    /* Row card terpilih */
    .trash-check {
        display: flex;
        align-items: center;
        flex-shrink: 0;
    }
    .trash-row-selected,
    .trash-row-selected:hover {
        border-color: #34d399;
        background: #f0fdf4;
    }
    .trash-user-table tr.trash-table-selected td {
        background: #ecfdf5;
    }

    /* Floating Action Bar bulk */
    .trash-float-bar {
        position: fixed;
        z-index: 1050;
        left: 50%;
        transform: translateX(-50%);
        bottom: 1.25rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        flex-wrap: wrap;
        width: fit-content;
        max-width: min(720px, calc(100vw - 1.5rem));
        background: #ffffff;
        border: 1px solid #e8eef5;
        border-radius: 16px;
        box-shadow: 0 12px 32px rgba(15, 23, 42, 0.16);
        padding: 0.6rem 0.9rem 0.6rem 1.1rem;
    }
    @media (max-width: 575.98px) {
        .trash-float-bar {
            left: 0.75rem;
            right: 0.75rem;
            transform: none;
            width: auto;
            flex-direction: column;
            align-items: stretch;
        }
        .trash-float-bar .btn {
            width: 100%;
        }
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
            <i class="bi bi-x-circle-fill me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
            <strong class="d-block mb-1">Terjadi kesalahan:</strong>
            <ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Page Header --}}
    <div class="d-flex align-items-center justify-content-between gap-3 mb-4 flex-wrap bg-white border border-secondary-subtle shadow-sm rounded-4 p-4">
        <div class="d-flex align-items-center gap-3">
            <div class="trash-header-icon d-flex align-items-center justify-content-center" aria-hidden="true">
                <i class="bi bi-trash3 fs-4"></i>
            </div>
            <div>
                <h2 class="fw-black text-dark mb-1" style="font-weight: 900; font-size: 1.5rem; letter-spacing: -0.02em;">
                    Data Terhapus (Recycle Bin)
                    <span class="badge rounded-pill trash-total-badge ms-2 text-nowrap" title="Total record terhapus dari seluruh kategori">
                        <i class="bi bi-inbox me-1"></i>{{ $totalTrash }} record terhapus
                    </span>
                </h2>
                <p class="text-muted mb-0" style="font-size: 0.9rem;">
                    Kelola data yang telah dihapus. <strong>Restore</strong> untuk memulihkan data,
                    atau <strong>Hapus Permanen</strong> untuk menghapusnya selamanya dari database
                    (tidak dapat dikembalikan).
                </p>
            </div>
        </div>
    </div>

    {{-- Nav Tabs per kategori (Sub-Nav Bar) --}}
    <ul class="nav trash-tabs" id="trashTabs" role="tablist">
        @foreach($categories as $cat)
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $loop->first ? 'active' : '' }}"
                        id="tab-{{ $cat['key'] }}-tab"
                        data-bs-toggle="tab"
                        data-bs-target="#tab-{{ $cat['key'] }}"
                        type="button" role="tab"
                        aria-controls="tab-{{ $cat['key'] }}"
                        aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                    <i class="bi {{ $cat['icon'] }} me-1"></i>{{ $cat['label'] }}
                    <span class="badge rounded-pill ms-1 {{ $cat['total'] > 0 ? 'trash-badge-has' : 'trash-badge-empty' }}">{{ $cat['total'] }}</span>
                </button>
            </li>
        @endforeach
    </ul>

    {{-- Tab Content --}}
    <div class="tab-content">
        @foreach($categories as $cat)
            <div class="tab-pane fade trash-tab-pane {{ $loop->first ? 'show active' : '' }}"
                 id="tab-{{ $cat['key'] }}" role="tabpanel"
                 aria-labelledby="tab-{{ $cat['key'] }}-tab"
                 x-data="trashBulk(
                     @js($cat['key']),
                     @js($cat['ids']),
                     @js(route('admin.trash.restore-bulk', [$cat['key']])),
                     @js(route('admin.trash.force-delete-bulk', [$cat['key']]))
                 )">

                {{-- Bar Pilih Semua (Select All) --}}
                @if($cat['total'] > 0)
                    <div class="trash-select-bar d-flex align-items-center justify-content-between gap-2">
                        <label class="d-flex align-items-center gap-2 mb-0 select-all-label"
                               for="selectAll-{{ $cat['key'] }}">
                            <input type="checkbox"
                                   class="form-check-input m-0"
                                   id="selectAll-{{ $cat['key'] }}"
                                   :checked="allSelected"
                                   @change="toggleSelectAll">
                            <span class="small fw-semibold text-secondary">
                                Pilih Semua <span class="text-muted fw-normal">({{ $cat['total'] }})</span>
                            </span>
                        </label>
                        <button type="button"
                                class="btn btn-sm btn-link text-secondary text-decoration-none p-0"
                                x-show="selected.length > 0" x-cloak
                                @click="clearSelection">
                            <i class="bi bi-x-circle me-1"></i>Bersihkan Pilihan
                        </button>
                    </div>
                @endif

                @if($cat['key'] === 'user')
                    {{-- Kategori User / Pengguna: tampilkan sebagai tabel --}}
                    @if($cat['total'] > 0)
                        <div class="table-responsive rounded-4 border shadow-sm bg-white overflow-hidden">
                            <table class="table align-middle mb-0 trash-user-table">
                                <thead>
                                    <tr>
                                        <th class="ps-3" style="width: 3rem;">
                                            <input type="checkbox"
                                                   class="form-check-input m-0"
                                                   :checked="allSelected"
                                                   @change="toggleSelectAll"
                                                   aria-label="Pilih semua user">
                                        </th>
                                        <th>Nama User</th>
                                        <th>Email</th>
                                        <th>Role / Akses</th>
                                        <th>Tanggal Dihapus</th>
                                        <th class="text-end pe-3">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($cat['rows'] as $row)
                                        <tr :class="{ 'trash-table-selected': isSelected(@js($row['id'])) }">
                                            <td class="ps-3">
                                                <input type="checkbox"
                                                       class="form-check-input m-0"
                                                       value="{{ $row['id'] }}"
                                                       x-model="selected"
                                                       aria-label="Pilih {{ $row['title'] }}">
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="user-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($row['title'], 0, 1)) }}</span>
                                                    <div>
                                                        <div class="fw-semibold text-dark">{{ $row['title'] }}</div>
                                                        <div class="text-muted small">{{ $row['subtitle'] }}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-secondary small">{{ $row['email'] ?? '-' }}</td>
                                            <td>
                                                <span class="badge rounded-pill user-role-badge">{{ $row['role_label'] }}</span>
                                            </td>
                                            <td class="text-secondary small text-nowrap" title="{{ $row['deleted_at']->diffForHumans() }}">
                                                <i class="bi bi-clock-history me-1"></i>{{ $row['deleted_at']->format('d M Y, H:i') }}
                                            </td>
                                            <td class="text-end pe-3">
                                                <button type="button" class="btn btn-sm btn-success"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#restoreModal-{{ $cat['key'] }}-{{ $row['id'] }}"
                                                        title="Pulihkan akun ini">
                                                    <i class="bi bi-arrow-counterclockwise me-1"></i>Restore
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#forceModal-{{ $cat['key'] }}-{{ $row['id'] }}"
                                                        title="Hapus permanen akun ini dari database (tidak dapat dikembalikan)">
                                                    <i class="bi bi-trash3 me-1"></i>Hapus Permanen
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="empty-state">
                            <i class="bi bi-inbox"></i>
                            <div class="fw-semibold">Belum ada akun user terhapus</div>
                            <div class="small">Akun yang dihapus (soft delete) akan muncul di sini.</div>
                        </div>
                    @endif
                @else
                    @forelse($cat['rows'] as $row)
                        <div class="trash-row" :class="{ 'trash-row-selected': isSelected(@js($row['id'])) }">
                            <div class="trash-check">
                                <input type="checkbox"
                                       class="form-check-input m-0"
                                       value="{{ $row['id'] }}"
                                       x-model="selected"
                                       aria-label="Pilih {{ $row['title'] }}">
                            </div>
                            <div class="trash-row-info">
                                <div class="fw-semibold text-dark" style="font-size: 0.95rem;">
                                    {{ $row['title'] }}
                                </div>
                                @if($row['subtitle'])
                                    <div class="text-muted small">{{ $row['subtitle'] }}</div>
                                @endif
                                <div class="text-muted small mt-1">
                                    <i class="bi bi-clock-history me-1"></i>
                                    Dihapus {{ $row['deleted_at']->diffForHumans() }}
                                    <span class="text-secondary">· {{ $row['deleted_at']->format('d M Y, H:i') }}</span>
                                </div>
                            </div>
                            <div class="trash-row-actions">
                                <button type="button" class="btn btn-sm btn-success"
                                        data-bs-toggle="modal"
                                        data-bs-target="#restoreModal-{{ $cat['key'] }}-{{ $row['id'] }}"
                                        title="Pulihkan data ini">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i>Restore
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger"
                                        data-bs-toggle="modal"
                                        data-bs-target="#forceModal-{{ $cat['key'] }}-{{ $row['id'] }}"
                                        title="Hapus permanen dari database (tidak dapat dikembalikan)">
                                    <i class="bi bi-trash3 me-1"></i>Hapus Permanen
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="empty-state">
                            <i class="bi bi-inbox"></i>
                            <div class="fw-semibold">Belum ada data terhapus pada kategori {{ $cat['label'] }}</div>
                            <div class="small">Data yang dihapus (soft delete) akan muncul di sini.</div>
                        </div>
                    @endforelse
                @endif

                {{-- Floating Action Bar (muncul otomatis saat minimal 1 item dipilih) --}}
                <div class="trash-float-bar"
                     x-show="selected.length > 0"
                     x-transition.opacity.duration.200ms
                     x-cloak>
                    <div class="d-flex align-items-center gap-2 flex-shrink-0">
                        <i class="bi bi-check2-circle fs-5 text-success"></i>
                        <span class="fw-semibold text-dark small" x-text="selected.length + ' item dipilih'"></span>
                        <span class="text-muted small d-none d-sm-inline">· {{ $cat['label'] }}</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <form method="POST" :action="restoreUrl" class="d-inline">
                            @csrf
                            <template x-for="id in selected" :key="'bulk-r-'+id">
                                <input type="hidden" name="ids[]" :value="id">
                            </template>
                            <button type="submit" class="btn btn-success btn-sm" title="Pulihkan semua item terpilih">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Restore Terpilih
                            </button>
                        </form>
                        <button type="button" class="btn btn-danger btn-sm"
                                data-bs-toggle="modal"
                                data-bs-target="#bulkForceModal-{{ $cat['key'] }}"
                                title="Hapus permanen semua item terpilih (tidak dapat dikembalikan)">
                            <i class="bi bi-trash3 me-1"></i>Hapus Permanen Terpilih
                        </button>
                    </div>
                </div>

                {{-- Modal Konfirmasi Hapus Permanen Massal --}}
                <div class="modal fade" id="bulkForceModal-{{ $cat['key'] }}"
                     tabindex="-1" aria-labelledby="bulkForceTitle-{{ $cat['key'] }}" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content rounded-4 border-0 shadow-sm" x-data="{ confirmText: '' }">
                            <div class="modal-header border-0 pb-0">
                                <h5 class="modal-title fw-bold text-danger" id="bulkForceTitle-{{ $cat['key'] }}">
                                    <i class="bi bi-trash3 me-2"></i>Hapus Permanen Massal
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                            </div>
                            <div class="modal-body pt-2">
                                <div class="alert alert-danger border-0 rounded-3 d-flex gap-2 mb-3" role="alert">
                                    <i class="bi bi-exclamation-octagon-fill fs-5 flex-shrink-0"></i>
                                    <div class="small lh-sm">
                                        <span class="fw-semibold" x-text="selected.length + ' item'"></span>
                                        data <strong>{{ $cat['label'] }}</strong> akan dihapus
                                        <strong>PERMANEN dari database</strong> dan
                                        <strong>TIDAK dapat dikembalikan lagi</strong>.<br>
                                        Seluruh baris terkait yang bergantung pada data ini juga ikut terhapus
                                        (aturan cascade database).
                                    </div>
                                </div>

                                <div class="trash-record-box mb-3">
                                    <div class="fw-semibold text-dark">
                                        {{ $cat['label'] }} terpilih: <span x-text="selected.length"></span> item
                                    </div>
                                    <div class="text-muted small">
                                        Tindakan ini tidak dapat dibatalkan.
                                    </div>
                                </div>

                                <label class="form-label small fw-semibold text-danger"
                                       for="bulkConfirm-{{ $cat['key'] }}">
                                    <i class="bi bi-keyboard me-1"></i>Ketik <code>HAPUS</code> untuk mengonfirmasi:
                                </label>
                                <input type="text"
                                       id="bulkConfirm-{{ $cat['key'] }}"
                                       class="form-control"
                                       x-model="confirmText"
                                       placeholder="HAPUS"
                                       autocomplete="off"
                                       spellcheck="false">
                            </div>
                            <div class="modal-footer border-0 pt-0">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                <form method="POST" :action="forceUrl" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <template x-for="id in selected" :key="'bulk-f-'+id">
                                        <input type="hidden" name="ids[]" :value="id">
                                    </template>
                                    <button type="submit"
                                            class="btn btn-danger"
                                            :disabled="confirmText.trim() !== 'HAPUS'">
                                        <i class="bi bi-trash3 me-1"></i>Ya, Hapus Permanen
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

{{-- ============ MODALS ============ --}}
@foreach($categories as $cat)
    @foreach($cat['rows'] as $row)
        {{-- Modal Restore --}}
        <div class="modal fade" id="restoreModal-{{ $cat['key'] }}-{{ $row['id'] }}"
             tabindex="-1" aria-labelledby="restoreModalTitle-{{ $cat['key'] }}-{{ $row['id'] }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content rounded-4 border-0 shadow-sm">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold" id="restoreModalTitle-{{ $cat['key'] }}-{{ $row['id'] }}">
                            <i class="bi bi-arrow-counterclockwise text-success me-2"></i>Pulihkan Data
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body pt-2">
                        <p class="text-muted mb-2">Anda akan memulihkan data <strong>{{ $row['label'] }}</strong> berikut:</p>
                        <div class="trash-record-box">
                            <div class="fw-semibold text-dark">{{ $row['title'] }}</div>
                            @if($row['subtitle'])
                                <div class="text-muted small">{{ $row['subtitle'] }}</div>
                            @endif
                            <div class="text-muted small mt-1">
                                <i class="bi bi-clock-history me-1"></i>Dihapus {{ $row['deleted_at']->diffForHumans() }}
                            </div>
                        </div>
                        <p class="text-muted small mt-3 mb-0">
                            Data akan kembali aktif dan terlihat seperti sebelum dihapus di seluruh portal terkait.
                        </p>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <form method="POST" action="{{ $row['restoreUrl'] }}">
                            @csrf
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Ya, Pulihkan
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- Modal Hapus Permanen (konfirmasi ketat: ketik nama) --}}
        <div class="modal fade" id="forceModal-{{ $cat['key'] }}-{{ $row['id'] }}"
             tabindex="-1" aria-labelledby="forceModalTitle-{{ $cat['key'] }}-{{ $row['id'] }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content rounded-4 border-0 shadow-sm"
                     x-data="{ confirmText: '', expected: @js($row['title']) }">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold text-danger" id="forceModalTitle-{{ $cat['key'] }}-{{ $row['id'] }}">
                            <i class="bi bi-trash3 me-2"></i>Hapus Permanen
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body pt-2">
                        <div class="alert alert-danger border-0 rounded-3 d-flex gap-2 mb-3" role="alert">
                            <i class="bi bi-exclamation-octagon-fill fs-5 flex-shrink-0"></i>
                            <div class="small lh-sm">
                                Data ini akan dihapus <strong>PERMANEN dari database</strong> dan
                                <strong>TIDAK dapat dikembalikan lagi</strong>.<br>
                                Baris terkait yang bergantung pada data ini juga ikut terhapus (aturan cascade database).
                            </div>
                        </div>

                        <div class="trash-record-box mb-3">
                            <div class="fw-semibold text-dark">{{ $row['title'] }}</div>
                            @if($row['subtitle'])
                                <div class="text-muted small">{{ $row['subtitle'] }}</div>
                            @endif
                        </div>

                        <label class="form-label small fw-semibold text-danger" for="confirm-{{ $cat['key'] }}-{{ $row['id'] }}">
                            <i class="bi bi-keyboard me-1"></i>Ketik nama data di atas untuk mengonfirmasi:
                        </label>
                        <input type="text"
                               id="confirm-{{ $cat['key'] }}-{{ $row['id'] }}"
                               class="form-control"
                               x-model="confirmText"
                               placeholder="{{ $row['title'] }}"
                               autocomplete="off"
                               spellcheck="false">
                        <div class="form-text small" x-show="confirmText.trim() !== '' && confirmText.trim() !== expected" x-cloak>
                            Nama yang diketik belum sesuai — tombol hapus tetap terkunci.
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <form method="POST" action="{{ $row['forceUrl'] }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="btn btn-danger"
                                    :disabled="confirmText.trim() !== expected">
                                <i class="bi bi-trash3 me-1"></i>Ya, Hapus Permanen
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
@endforeach
@endsection

@push('scripts')
<script>
    /**
     * State bulk selection per kategori Recycle Bin — dipakai lewat x-data
     * pada setiap tab-pane halaman admin/trash/index.
     */
    function trashBulk(category, allIds, restoreUrl, forceUrl) {
        return {
            category: category,
            allIds: allIds,
            selected: [],
            restoreUrl: restoreUrl,
            forceUrl: forceUrl,

            isSelected(id) {
                return this.selected.includes(String(id));
            },

            get allSelected() {
                return this.allIds.length > 0
                    && this.allIds.every(id => this.selected.includes(id));
            },

            toggleSelectAll() {
                this.selected = this.allSelected ? [] : [...this.allIds];
            },

            clearSelection() {
                this.selected = [];
            },
        };
    }
</script>
@endpush