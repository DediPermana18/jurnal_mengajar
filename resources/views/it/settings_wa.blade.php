@extends('layouts.app')

@section('title', 'Pengaturan WA Gateway - WebJournal')

@section('content')
<style>
    .wa-settings {
        font-size: 0.78rem;
    }

    .wa-settings h2 {
        font-size: 1.25rem !important;
    }

    .wa-settings .card-title-sm {
        font-size: 0.85rem !important;
    }

    .wa-settings .label-sm {
        font-size: 0.68rem !important;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .wa-settings .stat-status-lg {
        font-size: 1.6rem !important;
        font-weight: 800;
        letter-spacing: -0.02em;
    }
</style>

<div class="container-fluid px-0 wa-settings">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3 md:mb-4 gap-1 md:gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 800; font-size: 1.5rem;">
                <i class="bi bi-whatsapp text-success me-1"></i>Pengaturan WA Gateway
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.85rem;">Kelola token Fonnte, pantau koneksi, dan uji kirim pesan WhatsApp.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('it.dashboard') }}" class="btn btn-sm btn-outline-secondary rounded-3 fw-semibold">
                <i class="bi bi-arrow-left me-1"></i>Dashboard IT
            </a>
            <span class="text-muted small"><i class="bi bi-calendar3 me-1"></i>{{ now()->translatedFormat('l, d F Y') }}</span>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-3 border-0 shadow-sm mb-3 d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-check-circle-fill text-success fs-5"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 shadow-sm mb-3 d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-x-circle-fill text-danger fs-5"></i>
            <div>{{ session('error') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-4">
        {{-- ============ CARD 0: STATUS LAYANAN NOTIFIKASI (TOGGLE GLOBAL) ============ --}}
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                    <div class="d-flex align-items-start align-items-lg-center gap-3">
                        <div class="rounded-4 d-flex align-items-center justify-content-center flex-shrink-0 {{ $waEnabled ? 'bg-success-subtle' : 'bg-danger-subtle' }}" style="width: 48px; height: 48px;">
                            <i class="bi {{ $waEnabled ? 'bi-bell-fill text-success' : 'bi-bell-slash-fill text-danger' }} fs-4"></i>
                        </div>
                        <div>
                            <h5 class="card-title-sm fw-bold text-dark mb-1">
                                <i class="bi bi-bell-fill text-primary me-2"></i>Status Layanan Notifikasi
                            </h5>
                            <p class="text-muted mb-0" style="font-size: 0.75rem; line-height: 1.5;">
                                Matikan untuk menghentikan <strong>seluruh</strong> pengiriman notifikasi WhatsApp (Fonnte) secara global
                                tanpa menghapus token — status tersimpan di database dan langsung berlaku.
                            </p>
                        </div>
                    </div>

                    <div class="d-flex flex-column flex-md-row align-items-md-center gap-3 flex-shrink-0">
                        @if($waEnabled)
                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-2" style="font-size:0.72rem;">
                                <i class="bi bi-bell-fill me-1"></i>Aktif (Notifikasi Terkirim)
                            </span>
                        @else
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3 py-2" style="font-size:0.72rem;">
                                <i class="bi bi-bell-slash-fill me-1"></i>Nonaktif (Notifikasi Dimatikan)
                            </span>
                        @endif

                        <form method="POST" action="{{ route('it.settings.wa.toggle') }}" class="m-0" id="waNotificationToggleForm">
                            @csrf
                            <input type="hidden" name="wa_notification_enabled" value="0">
                            <div class="form-check form-switch d-flex align-items-center gap-2 mb-0" style="--bs-form-switch-bg: #20c997;">
                                <input type="checkbox"
                                       class="form-check-input toggle-wa-notification"
                                       name="wa_notification_enabled"
                                       value="1"
                                       role="switch"
                                       id="waNotificationSwitch"
                                       {{ $waEnabled ? 'checked' : '' }}
                                       onchange="document.getElementById('waNotificationToggleForm').submit();">
                                <label class="form-check-label fw-semibold text-dark small" for="waNotificationSwitch">
                                    {{ $waEnabled ? 'Aktif' : 'Nonaktif' }}
                                </label>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- ================= CARD 1: STATUS KONEKSI GATEWAY ================= --}}
        <div class="col-12 col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h5 class="card-title-sm fw-bold text-dark mb-0">
                        <i class="bi bi-plug-fill text-primary me-2"></i>Status Koneksi Gateway
                    </h5>
                    <a href="{{ route('it.settings.wa') }}" class="btn btn-sm btn-outline-primary rounded-3" title="Segarkan status">
                        <i class="bi bi-arrow-repeat"></i> Segarkan
                    </a>
                </div>

                <div class="d-flex align-items-center gap-3 mb-3">
                    @if($status['connected'])
                        <span class="d-inline-block rounded-circle bg-success flex-shrink-0" style="width: 16px; height: 16px;"></span>
                        <div class="stat-status-lg text-success">Connected</div>
                    @else
                        <span class="d-inline-block rounded-circle bg-danger flex-shrink-0" style="width: 16px; height: 16px;"></span>
                        <div class="stat-status-lg text-danger">Disconnected</div>
                    @endif
                </div>

                <div class="mb-3">
                    @if($status['connected'])
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-2" style="font-size:0.72rem;">
                            <i class="bi bi-wifi me-1"></i>TERKONEKSI
                        </span>
                    @else
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3 py-2" style="font-size:0.72rem;">
                            <i class="bi bi-wifi-off me-1"></i>GAGAL KONEKSI
                        </span>
                    @endif
                    @if($status['quota'] !== null)
                        <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-3 py-2" style="font-size:0.72rem;">
                            <i class="bi bi-speedometer2 me-1"></i>Sisa Kuota: {{ $status['quota'] }}
                        </span>
                    @endif
                </div>

                <p class="text-muted small mb-3" style="line-height: 1.5;">{{ $status['message'] }}</p>

                @if(! empty($status['details']))
                    <ul class="list-unstyled mb-0">
                        @foreach($status['details'] as $key => $value)
                            <li class="d-flex justify-content-between align-items-center py-1 border-bottom" style="border-color: #eef2f7 !important;">
                                <span class="text-muted small text-capitalize">{{ str_replace('_', ' ', $key) }}</span>
                                <span class="fw-semibold text-dark small text-end ms-2" style="word-break: break-word;">{{ $value }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        {{-- ================= CARD 2: FORM API TOKEN FONNTE ================= --}}
        <div class="col-12 col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h5 class="card-title-sm fw-bold text-dark mb-0">
                        <i class="bi bi-key-fill text-success me-2"></i>API Token Fonnte
                    </h5>
                    <span class="badge bg-light text-dark border rounded-pill px-3 py-2" style="font-size:0.72rem;">
                        <i class="bi bi-shield-lock me-1"></i>Rahasia
                    </span>
                </div>

                <form method="POST" action="{{ route('it.settings.wa.update') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="fonnteToken" class="label-sm text-muted fw-bold mb-1 d-block">Token Fonnte (API)</label>
                        <div class="input-group">
                            <input type="password"
                                   class="form-control rounded-start-3 @error('fonnte_token') is-invalid @enderror"
                                   id="fonnteToken" name="fonnte_token"
                                   placeholder="Masukkan token baru, atau kosongkan untuk memakai .env"
                                   value="{{ old('fonnte_token', $dbToken) }}" autocomplete="off">
                            <button type="button" class="btn btn-outline-secondary rounded-end-3" id="toggleTokenBtn" onclick="toggleToken()" title="Tampilkan / sembunyikan token">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        @error('fonnte_token')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        <div class="form-text mt-2">
                            Token disimpan di tabel <code>app_settings</code> (database). Kosongkan lalu simpan untuk menghapus token DB dan kembali memakai <code>.env</code>.
                        </div>
                    </div>

                    <div class="card bg-light border-0 rounded-4 p-3 mb-3">
                        <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
                            <div>
                                <div class="label-sm text-muted fw-bold mb-1">Token Aktif</div>
                                <div class="fw-semibold text-dark small font-monospace">
                                    @if($activeToken !== '')
                                        <span class="text-muted">…{{ mb_substr($activeToken, -4) }}</span>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill ms-1" style="font-size:0.66rem;">
                                            @if($dbToken !== '') DB @else .ENV @endif
                                        </span>
                                    @else
                                        <span class="text-danger">Belum dikonfigurasi</span>
                                    @endif
                                </div>
                            </div>
                            <div class="text-md-end">
                                <div class="label-sm text-muted fw-bold mb-1">Sumber</div>
                                <div class="fw-semibold text-dark small">
                                    @if($dbToken !== '')
                                        Database (override)
                                    @elseif($envToken !== '')
                                        .env <span class="text-muted">(fallback)</span>
                                    @else
                                        <span class="text-danger">Tidak ada (.env kosong)</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success rounded-3 py-2 fw-semibold w-100">
                        <i class="bi bi-save me-1"></i>Simpan Token
                    </button>
                </form>
            </div>
        </div>

        {{-- ================= CARD 3: TES KIRIM PESAN WA ================= --}}
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h5 class="card-title-sm fw-bold text-dark mb-0">
                        <i class="bi bi-send-fill text-success me-2"></i>Tes Kirim Pesan WA
                    </h5>
                    <span class="badge bg-light text-dark border rounded-pill px-3 py-2" style="font-size:0.72rem;">
                        Verifikasi integrasi gateway
                    </span>
                </div>

                <form method="POST" action="{{ route('it.settings.wa.test') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label for="testNoHp" class="label-sm text-muted fw-bold mb-1 d-block">Nomor Tujuan</label>
                            <input type="text"
                                   class="form-control rounded-3 @error('no_hp') is-invalid @enderror"
                                   id="testNoHp" name="no_hp"
                                   placeholder="08xx / 62xx (mis. 628123456789)"
                                   value="{{ old('no_hp') }}" maxlength="20">
                            @error('no_hp')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Nomor Anda sendiri terlebih dahulu untuk uji coba.</div>
                            @if(! $waEnabled)
                                <div class="form-text text-danger fw-semibold">
                                    <i class="bi bi-bell-slash-fill me-1"></i>Layanan notifikasi sedang NONAKTIF — pesan tes tidak akan terkirim sampai switch diaktifkan.
                                </div>
                            @endif
                        </div>
                        <div class="col-12 col-md-8">
                            <label for="testPesan" class="label-sm text-muted fw-bold mb-1 d-block">Pesan Tes</label>
                            <textarea class="form-control rounded-3 @error('pesan') is-invalid @enderror"
                                      id="testPesan" name="pesan" rows="2"
                                      placeholder="Tulis pesan tes…">{{ old('pesan') }}</textarea>
                            @error('pesan')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="mt-3 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary rounded-3 px-4 py-2 fw-semibold">
                            <i class="bi bi-whatsapp me-1"></i>Kirim Tes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleToken() {
        const input = document.getElementById('fonnteToken');
        const icon = document.querySelector('#toggleTokenBtn i');
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
    }
</script>
@endsection