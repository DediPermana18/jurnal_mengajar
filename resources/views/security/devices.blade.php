@extends('layouts.app')

@section('title', 'Perangkat & Keamanan')

@push('styles')
<style>
    .sec-card {
        border: 1.5px solid #e2e8f0;
        border-radius: 16px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.05);
        background: #fff;
        overflow: hidden;
    }
    .sec-card .card-header-custom {
        padding: 1.25rem 1.5rem 1rem;
        border-bottom: 1.5px solid #f1f5f9;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    .sec-card .card-header-custom .icon-badge {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        flex-shrink: 0;
    }
    .sec-table thead th {
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #64748b;
        background: #f8fafc;
        border-bottom: 1.5px solid #e2e8f0;
        white-space: nowrap;
    }
    .sec-table tbody td {
        font-size: 0.85rem;
        vertical-align: middle;
        border-color: #f1f5f9;
    }
    .ua-cell {
        font-size: 0.7rem;
        color: #94a3b8;
        word-break: break-all;
        max-width: 360px;
    }
    .fp-badge {
        font-size: 0.68rem;
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    }
    .name-device-btn {
        font-size: 0.72rem;
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-4 py-4" style="max-width: 1100px;">

    {{-- ===== PAGE HEADER ===== --}}
    <div class="d-flex align-items-center gap-3 mb-4">
        <div class="rounded-3 d-flex align-items-center justify-content-center text-white shadow-sm"
             style="width: 44px; height: 44px; background: linear-gradient(135deg,#1677ff,#0050b3); flex-shrink: 0;">
            <i class="bi bi-shield-lock fs-5"></i>
        </div>
        <div>
            <h4 class="fw-bold mb-0 text-dark" style="font-size: 1.2rem;">Perangkat &amp; Keamanan</h4>
            <p class="text-muted mb-0" style="font-size: 0.82rem;">
                Jejak digital seluruh perangkat yang pernah / sedang login ke akun Anda.
            </p>
        </div>
    </div>

    {{-- ===== SESSION FLASH ===== --}}
    @if(session('success_password'))
        <div class="alert alert-success border-0 rounded-3 shadow-sm d-flex align-items-center gap-2 mb-4" style="font-size:0.88rem;">
            <i class="bi bi-shield-check-fill text-success fs-5"></i>
            <span>{{ session('success_password') }}</span>
            <button class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('success_naming'))
        <div class="alert alert-success border-0 rounded-3 shadow-sm d-flex align-items-center gap-2 mb-4" style="font-size:0.88rem;">
            <i class="bi bi-check-circle-fill text-success fs-5"></i>
            <span>{{ session('success_naming') }}</span>
            <button class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->has('device'))
        <div class="alert alert-danger border-0 rounded-3 shadow-sm d-flex align-items-center gap-2 mb-4" style="font-size:0.88rem;">
            <i class="bi bi-x-octagon-fill text-danger fs-5"></i>
            <span>{{ $errors->first('device') }}</span>
            <button class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- ===== INFO KONSEP ===== --}}
    <div class="alert alert-info border-0 rounded-3 shadow-sm mb-4 p-3.5 sm:p-4" style="font-size:0.84rem;">
        <div class="d-flex align-items-start gap-2.5">
            <i class="bi bi-info-circle-fill mt-0.5 fs-5 text-info flex-shrink-0"></i>
            <div class="w-100">
                <div class="fw-bold text-info-emphasis mb-1">Informasi Jejak Keamanan Akun</div>
                <div class="d-none d-md-block text-slate-700">
                    Catatan keamanan ini bersifat <strong>permanen (immutable)</strong> — setiap login berhasil
                    menambah satu catatan baru yang tidak dapat diubah maupun dihapus. Setiap perangkat memiliki
                    <strong>sidik jari unik</strong> (tag <span class="font-monospace">FP #…</span>) dari model perangkat,
                    layar, &amp; zona waktu. Login yang datang dari sidik jari yang <strong>belum pernah terlihat</strong>
                    ditandai <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill" style="font-size:0.68rem;">⚠️ Perangkat Baru / Tak Dikenal</span>.
                    Jika menemukan perangkat asing, gunakan <strong>[Putuskan Sesi Ini]</strong> lalu segera perbarui
                    password. Pemilik akun juga mendapat notifikasi bot chat tiap ada login baru.
                </div>
                <div class="d-block d-md-none text-slate-700">
                    <p class="mb-1.5">
                        Catatan login bersifat <strong>permanen &amp; memiliki sidik jari unik</strong> untuk mendeteksi perangkat baru/asing.
                    </p>
                    <details class="text-xs">
                        <summary class="cursor-pointer text-primary fw-semibold user-select-none">Pelajari Selengkapnya</summary>
                        <div class="mt-2 pt-2 border-top border-info-subtle small text-slate-600">
                            Setiap login berhasil menambah catatan permanen. Login dari sidik jari baru ditandai <span class="badge bg-danger-subtle text-danger rounded-pill" style="font-size:0.65rem;">⚠️ Perangkat Baru</span>. Jika ada sesi mencurigakan, segera <strong>[Putuskan Sesi]</strong> dan perbarui password Anda. Notifikasi otomatis dikirim ke WA/Telegram tiap ada aktivitas login.
                        </div>
                    </details>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== DAFTAR DEVICE ===== --}}
    <div class="sec-card">
        <div class="card-header-custom">
            <div class="icon-badge" style="background:#eef2ff;">
                <i class="bi bi-laptop text-primary"></i>
            </div>
            <div>
                <div class="fw-bold text-dark" style="font-size:0.95rem;">Riwayat Login Perangkat</div>
                <div class="text-muted" style="font-size:0.75rem;">Seluruh perangkat yang pernah berhasil login, lengkap dengan IP, browser, sidik jari, dan waktu.</div>
            </div>
        </div>

        @if($logs->isEmpty())
            <div class="text-center text-muted py-5">
                <i class="bi bi-shield-check fs-1 d-block mb-2"></i>
                Belum ada riwayat login tercatat untuk akun ini.
            </div>
        @else
            {{-- ===== MOBILE CARD LAYOUT (block md:hidden) ===== --}}
            <div class="d-block d-md-none p-3">
                @foreach($logs as $log)
                    <div class="bg-white p-3.5 sm:p-4 rounded-3 border border-slate-200 shadow-sm mb-3 space-y-2.5 {{ $log->is_unknown_device ? 'border-warning-subtle bg-warning-subtle/10' : '' }}">
                        {{-- Header Card --}}
                        <div class="d-flex align-items-start justify-content-between gap-2">
                            <div class="min-w-0">
                                <div class="d-flex align-items-center flex-wrap gap-1">
                                    @if($log->has_custom_name)
                                        <span class="fw-bold text-dark text-sm">
                                            <i class="bi bi-pencil-square text-primary me-1" style="font-size:0.8rem;"></i>{{ $log->custom_name }}
                                        </span>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill" style="font-size:0.62rem;">Nama Anda</span>
                                    @else
                                        <span class="fw-bold text-dark text-sm">{{ $log->device_name ?: 'Perangkat Tidak Dikenal' }}</span>
                                    @endif
                                </div>
                                @if($log->has_custom_name && $log->device_name)
                                    <div class="text-muted text-xs mt-0.5">{{ $log->device_name }}</div>
                                @endif
                            </div>
                            <div class="text-end flex-shrink-0">
                                @if($log->is_current)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-0.5" style="font-size:0.68rem;">
                                        <i class="bi bi-circle-fill me-1" style="font-size:0.45rem;"></i>Perangkat Ini
                                    </span>
                                @elseif($log->is_active)
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-2 py-0.5" style="font-size:0.68rem;">
                                        <i class="bi bi-circle-fill me-1" style="font-size:0.45rem;"></i>Aktif
                                    </span>
                                @else
                                    <span class="badge bg-light text-secondary border rounded-pill px-2 py-0.5" style="font-size:0.68rem;">Historis</span>
                                @endif
                                <div class="text-muted text-[11px] mt-1">
                                    {{ $log->login_at ? \Carbon\Carbon::parse($log->login_at)->timezone(config('app.timezone'))->format('d M Y H:i') : '-' }}
                                </div>
                            </div>
                        </div>

                        {{-- Perangkat Baru / Alert --}}
                        @if($log->is_unknown_device)
                            <div>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1" style="font-size:0.68rem;">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i>Perangkat Baru / Tak Dikenal
                                </span>
                            </div>
                        @endif

                        @if($log->description)
                            <div class="fw-semibold text-danger small"><i class="bi bi-shield-exclamation me-1"></i>{{ $log->description }}</div>
                        @endif

                        {{-- Body Card: Badges IP & Fingerprint --}}
                        <div class="d-flex flex-wrap align-items-center gap-1.5 pt-1">
                            <span class="font-monospace text-[11px] bg-slate-100 text-slate-600 px-2 py-0.5 rounded border border-slate-200">
                                <i class="bi bi-hdd-network me-1 text-slate-400"></i>{{ $log->ip_address ?: '-' }}
                            </span>
                            @if($log->fingerprint_short !== '')
                                <span class="badge bg-dark-subtle text-dark border rounded-pill fp-badge" title="Sidik jari unik">
                                    <i class="bi bi-fingerprint me-1"></i>FP #{{ $log->fingerprint_short }}
                                </span>
                                @if($log->is_generic)
                                    <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill fp-badge">
                                        <i class="bi bi-geo-alt me-1"></i>{{ $log->ip_short }} • {{ $log->login_time_short }}
                                    </span>
                                @endif
                            @endif
                        </div>

                        {{-- User-Agent Collapsible --}}
                        <details class="text-xs text-slate-500 pt-1">
                            <summary class="cursor-pointer text-primary fw-medium user-select-none small">
                                <i class="bi bi-info-circle me-1"></i>Lihat Detail Browser
                            </summary>
                            <div class="ua-cell mt-1.5 p-2 bg-slate-50 border border-slate-100 rounded text-break font-monospace" style="font-size:0.7rem; max-width: 100%;">
                                {{ $log->user_agent ?: 'User-Agent tidak tercatat' }}
                            </div>
                        </details>

                        {{-- Footer Card: Action Buttons --}}
                        <div class="pt-2 border-top border-slate-100 d-flex flex-column gap-2">
                            @if($log->fingerprint_short !== '')
                                <button type="button"
                                        class="btn btn-outline-primary btn-sm w-100 py-2 rounded-3 name-device-btn btn-name-device font-medium"
                                        data-bs-toggle="modal"
                                        data-bs-target="#deviceNameModal"
                                        data-fingerprint="{{ $log->device_fingerprint }}"
                                        data-nama="{{ $log->custom_name ?? '' }}"
                                        data-has-custom="{{ $log->has_custom_name ? 1 : 0 }}">
                                    <i class="bi bi-pencil me-1"></i>{{ $log->has_custom_name ? 'Ganti Nama Perangkat' : 'Beri Nama Perangkat Ini' }}
                                </button>
                            @endif
                            @if($log->is_active && ! $log->is_current)
                                <form method="POST" action="{{ route('security.devices.revoke', $log->id) }}"
                                      class="w-100 form-revoke"
                                      data-device="{{ $log->display_name ?: 'perangkat ini' }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-danger btn-sm w-100 py-2 rounded-3 font-medium" style="font-size:0.75rem;">
                                        <i class="bi bi-plug me-1"></i>Putuskan Sesi Ini
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- ===== DESKTOP TABLE LAYOUT (hidden md:table / d-none d-md-block) ===== --}}
            <div class="d-none d-md-block table-responsive">
                <table class="table table-hover align-middle mb-0 sec-table">
                    <thead>
                        <tr>
                            <th>Perangkat / Browser</th>
                            <th>Alamat IP</th>
                            <th>Waktu Login</th>
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($logs as $log)
                            <tr class="{{ $log->is_unknown_device ? 'table-warning-subtle' : '' }}">
                                <td>
                                    <div class="d-flex align-items-center flex-wrap gap-1">
                                        @if($log->has_custom_name)
                                            <span class="fw-bold text-dark">
                                                <i class="bi bi-pencil-square text-primary me-1" style="font-size:0.8rem;"></i>{{ $log->custom_name }}
                                            </span>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill" style="font-size:0.62rem;">Nama Anda</span>
                                            <span class="text-muted" style="font-size:0.72rem;">{{ $log->device_name }}</span>
                                        @else
                                            <span class="fw-semibold text-dark">{{ $log->device_name ?: 'Perangkat Tidak Dikenal' }}</span>
                                        @endif
                                    </div>

                                    @if($log->is_unknown_device)
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill mt-1" style="font-size:0.7rem;">
                                            <i class="bi bi-exclamation-triangle-fill me-1"></i>Perangkat Baru / Tak Dikenal
                                        </span>
                                    @endif

                                    @if($log->description)
                                        <div class="fw-semibold text-danger" style="font-size:0.8rem;"><i class="bi bi-shield-exclamation me-1"></i>{{ $log->description }}</div>
                                    @endif

                                    @if($log->fingerprint_short !== '')
                                        <div class="d-flex align-items-center flex-wrap gap-1 mt-1">
                                            <span class="badge bg-dark-subtle text-dark border rounded-pill fp-badge" title="Sidik jari unik perangkat ini — sama untuk seluruh login dari perangkat yang sama.">
                                                <i class="bi bi-fingerprint me-1"></i>FP #{{ $log->fingerprint_short }}
                                            </span>
                                            @if($log->is_generic)
                                                <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill fp-badge"
                                                      title="Nama generik (tanpa model): penanda unik dari IP &amp; waktu login terakhir untuk membedakannya dari perangkat lain.">
                                                    <i class="bi bi-geo-alt me-1"></i>{{ $log->ip_short }} • {{ $log->login_time_short }}
                                                </span>
                                            @endif
                                        </div>
                                    @endif

                                    <div class="ua-cell mt-1">{{ $log->user_agent ?: 'User-Agent tidak tercatat' }}</div>

                                    @if($log->fingerprint_short !== '')
                                        <div class="mt-1">
                                            <button type="button"
                                                    class="btn btn-outline-primary btn-sm rounded-3 name-device-btn btn-name-device"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#deviceNameModal"
                                                    data-fingerprint="{{ $log->device_fingerprint }}"
                                                    data-nama="{{ $log->custom_name ?? '' }}"
                                                    data-has-custom="{{ $log->has_custom_name ? 1 : 0 }}">
                                                <i class="bi bi-pencil me-1"></i>{{ $log->has_custom_name ? 'Ganti Nama Perangkat' : 'Beri Nama Perangkat Ini' }}
                                            </button>
                                        </div>
                                    @endif
                                </td>
                                <td class="font-monospace text-secondary">{{ $log->ip_address ?: '-' }}</td>
                                <td class="text-secondary">
                                    {{ $log->login_at ? \Carbon\Carbon::parse($log->login_at)->timezone(config('app.timezone'))->format('d M Y H:i') : '-' }}
                                </td>
                                <td>
                                    @if($log->is_current)
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1" style="font-size:0.72rem;">
                                            <i class="bi bi-circle-fill me-1" style="font-size:0.5rem;"></i>Perangkat ini
                                        </span>
                                    @elseif($log->is_active)
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-2 py-1" style="font-size:0.72rem;">
                                            <i class="bi bi-circle-fill me-1" style="font-size:0.5rem;"></i>Aktif
                                        </span>
                                    @else
                                        <span class="badge bg-light text-secondary border rounded-pill px-2 py-1" style="font-size:0.72rem;">Historis</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($log->is_active && ! $log->is_current)
                                        <form method="POST" action="{{ route('security.devices.revoke', $log->id) }}"
                                              class="d-inline form-revoke"
                                              data-device="{{ $log->display_name ?: 'perangkat ini' }}">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-danger btn-sm rounded-3" style="font-size:0.75rem;">
                                                <i class="bi bi-plug me-1"></i>Putuskan Sesi Ini
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-muted" style="font-size:0.75rem;">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

{{-- ===== MODAL: BERI NAMA PERANGKAT ===== --}}
<div class="modal fade" id="deviceNameModal" tabindex="-1" aria-labelledby="deviceNameModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <form method="POST" action="{{ route('security.devices.name') }}">
                @csrf
                <input type="hidden" name="fingerprint" id="deviceNameFingerprint" value="">

                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title fw-bold text-dark" id="deviceNameModalLabel">Beri Nama Perangkat Indikator</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body pt-2">
                    <p class="text-muted mb-3" style="font-size:0.8rem;">
                        Nama ini hanya tampil untuk Anda dan berlaku untuk seluruh login dari perangkat yang sama
                        (diidentifikasi lewat sidik jari <span class="font-monospace" id="deviceNameFpPreview">FP #…</span>).
                        Kosongkan untuk menghapus nama.
                    </p>
                    <div class="mb-2">
                        <label for="deviceNameInput" class="form-label fw-semibold text-dark" style="font-size:0.82rem;">Nama Perangkat</label>
                        <input type="text" maxlength="80" class="form-control rounded-3" id="deviceNameInput"
                               name="name" placeholder="mis. HP Utama, Tablet Sekolah" autocomplete="off">
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm rounded-3 px-3">
                        <i class="bi bi-check-lg me-1"></i>Simpan Nama
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('.form-revoke').forEach(form => {
        form.addEventListener('submit', function (e) {
            const nama = this.dataset.device || 'perangkat ini';
            if (!confirm('Yakin ingin memutuskan sesi "' + nama + '" dari akun Anda?\n\nAnda akan diarahkan ke form Ganti Password untuk mengamankan akun.')) {
                e.preventDefault();
            }
        });
    });

    // --- Prefill modal "Beri Nama Perangkat Ini" sesuai tombol yang diklik ---
    document.querySelectorAll('.btn-name-device').forEach(btn => {
        btn.addEventListener('click', function () {
            const fingerprint = this.dataset.fingerprint || '';
            const nama = this.dataset.nama || '';
            const hasCustom = this.dataset.hasCustom === '1';

            document.getElementById('deviceNameFingerprint').value = fingerprint;
            document.getElementById('deviceNameInput').value = nama;
            document.getElementById('deviceNameFpPreview').textContent = fingerprint ? ('FP #' + fingerprint.slice(0, 8)) : 'FP #…';
            document.getElementById('deviceNameModalLabel').textContent = hasCustom
                ? 'Ganti Nama Perangkat'
                : 'Beri Nama Perangkat Ini';
        });
    });
</script>
@endpush