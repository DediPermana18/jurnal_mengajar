@extends('layouts.app')

@section('title', 'Pengaturan Profil & Akun Saya')

@push('styles')
<style>
    /* ---- Avatar Upload ---- */
    .avatar-wrapper {
        position: relative;
        width: 100px;
        height: 100px;
        cursor: pointer;
        flex-shrink: 0;
    }
    .avatar-wrapper img {
        width: 100px;
        height: 100px;
        object-fit: cover;
        border-radius: 50%;
        border: 3px solid #e2e8f0;
        transition: filter 0.2s;
    }
    .avatar-wrapper:hover img {
        filter: brightness(0.75);
    }
    .avatar-overlay {
        position: absolute;
        inset: 0;
        border-radius: 50%;
        background: rgba(22,119,255,0.15);
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.2s;
        pointer-events: none;
    }
    .avatar-wrapper:hover .avatar-overlay { opacity: 1; }

    /* ---- Section Tab Pills ---- */
    .profil-nav .nav-link {
        border-radius: 10px;
        color: #475569;
        font-weight: 600;
        font-size: 0.875rem;
        padding: 0.55rem 1rem;
        transition: background 0.15s, color 0.15s;
    }
    .profil-nav .nav-link.active {
        background: #1677ff;
        color: #fff;
    }
    .profil-nav .nav-link:not(.active):hover {
        background: #f0f5ff;
        color: #1677ff;
    }

    /* ---- Section Cards ---- */
    .profil-card {
        border: 1.5px solid #e2e8f0;
        border-radius: 16px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.05);
        background: #fff;
        overflow: hidden;
    }
    .profil-card .card-header-custom {
        padding: 1.25rem 1.5rem 1rem;
        border-bottom: 1.5px solid #f1f5f9;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    .profil-card .card-header-custom .icon-badge {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        flex-shrink: 0;
    }

    /* ---- Password Strength ---- */
    #passwordStrengthBar {
        height: 5px;
        border-radius: 3px;
        transition: width 0.3s, background 0.3s;
    }

    /* ---- Kode Aktivasi Chip ---- */
    .kode-chip {
        font-family: 'Courier New', monospace;
        letter-spacing: 0.2em;
        font-size: 1.35rem;
        font-weight: 800;
        background: linear-gradient(135deg, #eff6ff, #dbeafe);
        border: 1.5px solid #bfdbfe;
        border-radius: 12px;
        padding: 0.65rem 1.25rem;
        color: #1e40af;
        display: inline-block;
        user-select: all;
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-4 py-4" style="max-width: 1000px;">

    {{-- ===== PAGE HEADER ===== --}}
    <div class="d-flex align-items-center gap-3 mb-4">
        <div class="rounded-3 d-flex align-items-center justify-content-center text-white shadow-sm"
             style="width: 44px; height: 44px; background: linear-gradient(135deg,#1677ff,#0050b3); flex-shrink: 0;">
            <i class="bi bi-person-gear fs-5"></i>
        </div>
        <div>
            <h4 class="fw-bold mb-0 text-dark" style="font-size: 1.2rem;">Pengaturan Profil & Akun Saya</h4>
            <p class="text-muted mb-0" style="font-size: 0.82rem;">
                Kelola informasi profil, keamanan, dan kredensial akun Anda.
            </p>
        </div>
    </div>

    {{-- ===== SESSION FLASH ALERTS ===== --}}
    @if(session('success_profil'))
        <div class="alert alert-success border-0 rounded-3 shadow-sm d-flex align-items-center gap-2 mb-4" style="font-size:0.88rem;">
            <i class="bi bi-check-circle-fill text-success fs-5"></i>
            <span>{{ session('success_profil') }}</span>
            <button class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('success_password'))
        <div class="alert alert-success border-0 rounded-3 shadow-sm d-flex align-items-center gap-2 mb-4" style="font-size:0.88rem;">
            <i class="bi bi-shield-check-fill text-success fs-5"></i>
            <span>{{ session('success_password') }}</span>
            <button class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('success_kode'))
        <div class="alert alert-info border-0 rounded-3 shadow-sm d-flex align-items-center gap-2 mb-4" style="font-size:0.88rem;">
            <i class="bi bi-key-fill text-info fs-5"></i>
            <span>{{ session('success_kode') }}</span>
            <button class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger border-0 rounded-3 shadow-sm mb-4" style="font-size:0.88rem;">
            <div class="d-flex align-items-center gap-2 mb-1">
                <i class="bi bi-exclamation-triangle-fill text-danger fs-5"></i>
                <strong>Terjadi kesalahan pada input:</strong>
            </div>
            <ul class="mb-0 ps-3 mt-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ===== LAYOUT: Sidebar Nav + Content ===== --}}
    <div class="row g-4">

        {{-- --- LEFT: Profile Card + Nav Tabs --- --}}
        <div class="col-12 col-lg-3">
            {{-- Profile Identity Card --}}
            <div class="profil-card p-4 text-center mb-3">
                @php
                    $avatarSrc = $user->foto_profil
                        ? asset('storage/' . $user->foto_profil)
                        : 'https://ui-avatars.com/api/?name=' . urlencode($user->nama) . '&background=1677ff&color=fff&size=128&bold=true';
                @endphp
                <img src="{{ $avatarSrc }}" alt="Avatar"
                     class="rounded-circle border mb-3"
                     style="width:80px;height:80px;object-fit:cover;border-width:3px!important;border-color:#e2e8f0!important;">
                <div class="fw-bold text-dark" style="font-size:1rem;">{{ $user->nama }}</div>
                <div class="text-muted" style="font-size:0.78rem;">{{ $user->role_label }}</div>
                @if($user->nip)
                    <div class="badge bg-light text-secondary border rounded-pill px-3 mt-1" style="font-size:0.72rem;">NIP: {{ $user->nip }}</div>
                @endif
                <div class="mt-2">
                    @if($user->is_active)
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1" style="font-size:0.72rem;">
                            <i class="bi bi-circle-fill me-1" style="font-size:0.5rem;"></i>Akun Aktif
                        </span>
                    @else
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1" style="font-size:0.72rem;">
                            <i class="bi bi-circle-fill me-1" style="font-size:0.5rem;"></i>Tidak Aktif
                        </span>
                    @endif
                </div>
            </div>

            {{-- Nav Pills --}}
            <div class="profil-nav d-flex flex-column gap-1 p-2 profil-card">
                <a href="#section-profil" class="nav-link active d-flex align-items-center gap-2"
                   onclick="switchTab(this,'section-profil')">
                    <i class="bi bi-person-circle"></i> Informasi Profil
                </a>
                <a href="#section-password" class="nav-link d-flex align-items-center gap-2"
                   onclick="switchTab(this,'section-password')">
                    <i class="bi bi-shield-lock"></i> Ganti Password
                </a>
                @if(auth()->user()->role === 'admin')
                <a href="#section-kode" class="nav-link d-flex align-items-center gap-2"
                   onclick="switchTab(this,'section-kode')">
                    <i class="bi bi-key-fill"></i> Kode Aktivasi
                </a>
                @endif
            </div>
        </div>

        {{-- --- RIGHT: Content Sections --- --}}
        <div class="col-12 col-lg-9">

            {{-- ========== SECTION 1: INFORMASI PROFIL ========== --}}
            <div id="section-profil" class="profil-section">
                <div class="profil-card">
                    <div class="card-header-custom">
                        <div class="icon-badge" style="background:#eff6ff;">
                            <i class="bi bi-person-circle text-primary"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-dark" style="font-size:0.95rem;">Informasi Profil</div>
                            <div class="text-muted" style="font-size:0.75rem;">Perbarui nama, NIP, username, email, nomor WA, dan foto profil Anda.</div>
                        </div>
                    </div>

                    <div class="p-4">
                        <form method="POST" action="{{ route('profil.update-profil') }}" enctype="multipart/form-data" id="formProfil">
                            @csrf

                            {{-- Avatar Upload Row --}}
                            <div class="d-flex align-items-center gap-4 mb-4 pb-3 border-bottom">
                                <label for="inputFoto" class="avatar-wrapper mb-0" title="Klik untuk ganti foto">
                                    <img id="previewFoto" src="{{ $avatarSrc }}" alt="Foto Profil">
                                    <div class="avatar-overlay">
                                        <i class="bi bi-camera-fill text-white fs-5"></i>
                                    </div>
                                </label>
                                <input type="file" name="foto_profil" id="inputFoto" class="d-none" accept="image/jpg,image/jpeg,image/png,image/webp">
                                <div>
                                    <div class="fw-semibold text-dark mb-1" style="font-size:0.875rem;">Foto Profil</div>
                                    <div class="text-muted mb-2" style="font-size:0.78rem;">JPG, PNG, WebP. Maks 2 MB.</div>
                                    <label for="inputFoto" class="btn btn-outline-primary btn-sm rounded-3" style="font-size:0.8rem;">
                                        <i class="bi bi-upload me-1"></i>Pilih Foto
                                    </label>
                                    @if($user->foto_profil)
                                        <span class="text-muted ms-2" style="font-size:0.78rem;">
                                            <i class="bi bi-check-circle text-success me-1"></i>Foto telah diupload
                                        </span>
                                    @endif
                                </div>
                            </div>

                            {{-- Form Fields --}}
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold text-dark mb-1" style="font-size:0.85rem;">
                                        Nama Lengkap <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" name="nama" class="form-control rounded-3 @error('nama') is-invalid @enderror"
                                           value="{{ old('nama', $user->nama) }}" required
                                           placeholder="Nama lengkap" style="font-size:0.875rem;">
                                    @error('nama')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold text-dark mb-1" style="font-size:0.85rem;">
                                        NIP <span class="text-muted fw-normal">(opsional)</span>
                                    </label>
                                    <input type="text" name="nip" class="form-control rounded-3 @error('nip') is-invalid @enderror"
                                           value="{{ old('nip', $user->nip) }}"
                                           placeholder="Nomor Induk Pegawai" style="font-size:0.875rem;">
                                    @error('nip')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold text-dark mb-1" style="font-size:0.85rem;">
                                        Username <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0 rounded-start-3" style="font-size:0.85rem;">@</span>
                                        <input type="text" name="username" class="form-control rounded-end-3 border-start-0 @error('username') is-invalid @enderror"
                                               value="{{ old('username', $user->username) }}" required
                                               placeholder="username_anda" style="font-size:0.875rem;">
                                        @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold text-dark mb-1" style="font-size:0.85rem;">
                                        Email <span class="text-muted fw-normal">(opsional)</span>
                                    </label>
                                    <input type="email" name="email" class="form-control rounded-3 @error('email') is-invalid @enderror"
                                           value="{{ old('email', $user->email) }}"
                                           placeholder="contoh@sekolah.sch.id" style="font-size:0.875rem;">
                                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold text-dark mb-1" style="font-size:0.85rem;">
                                        Nomor WhatsApp / HP <span class="text-muted fw-normal">(opsional)</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0 rounded-start-3" style="font-size:0.85rem;"><i class="bi bi-whatsapp"></i></span>
                                        <input type="text" name="no_hp" class="form-control rounded-end-3 @error('no_hp') is-invalid @enderror"
                                               value="{{ old('no_hp', $user->no_hp) }}"
                                               placeholder="081234567890" style="font-size:0.875rem;">
                                        @error('no_hp')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="text-muted mt-1" style="font-size:0.72rem;">
                                        <i class="bi bi-info-circle me-1"></i>Digunakan untuk tombol "Kirim WA Approval" Dispensasi. Awalan 0 akan otomatis diubah ke 62.
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end mt-4 pt-3 border-top">
                                <button type="submit" class="btn btn-primary fw-bold px-5 rounded-3 d-flex align-items-center gap-2" style="font-size:0.875rem;">
                                    <i class="bi bi-floppy-fill"></i> Simpan Perubahan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- ========== SECTION 2: GANTI PASSWORD ========== --}}
            <div id="section-password" class="profil-section d-none">
                <div class="profil-card">
                    <div class="card-header-custom">
                        <div class="icon-badge" style="background:#f0fdf4;">
                            <i class="bi bi-shield-lock-fill text-success"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-dark" style="font-size:0.95rem;">Keamanan — Ganti Password</div>
                            <div class="text-muted" style="font-size:0.75rem;">Pastikan akun Anda tetap aman dengan password yang kuat dan unik.</div>
                        </div>
                    </div>

                    <div class="p-4">
                        <form method="POST" action="{{ route('profil.update-password') }}" id="formPassword">
                            @csrf

                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label fw-semibold text-dark mb-1" style="font-size:0.85rem;">
                                        Password Saat Ini <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input type="password" name="current_password" id="currentPassword"
                                               class="form-control rounded-start-3 @error('current_password') is-invalid @enderror"
                                               placeholder="Masukkan password saat ini" required style="font-size:0.875rem;">
                                        <button type="button" class="btn btn-outline-secondary rounded-end-3"
                                                onclick="togglePwd('currentPassword',this)" style="font-size:0.85rem;">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        @error('current_password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold text-dark mb-1" style="font-size:0.85rem;">
                                        Password Baru <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input type="password" name="password" id="newPassword"
                                               class="form-control rounded-start-3 @error('password') is-invalid @enderror"
                                               placeholder="Min. 8 karakter" required
                                               style="font-size:0.875rem;" oninput="checkStrength(this.value)">
                                        <button type="button" class="btn btn-outline-secondary rounded-end-3"
                                                onclick="togglePwd('newPassword',this)" style="font-size:0.85rem;">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="mt-2">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span class="text-muted" style="font-size:0.75rem;">Kekuatan Password</span>
                                            <span id="strengthLabel" class="fw-semibold" style="font-size:0.75rem;"></span>
                                        </div>
                                        <div class="bg-light rounded" style="height:5px;">
                                            <div id="passwordStrengthBar" class="rounded bg-danger" style="width:0%;height:5px;transition:width .3s,background .3s;"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold text-dark mb-1" style="font-size:0.85rem;">
                                        Konfirmasi Password Baru <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input type="password" name="password_confirmation" id="confirmPassword"
                                               class="form-control rounded-start-3"
                                               placeholder="Ulangi password baru" required style="font-size:0.875rem;"
                                               oninput="checkMatch()">
                                        <button type="button" class="btn btn-outline-secondary rounded-end-3"
                                                onclick="togglePwd('confirmPassword',this)" style="font-size:0.85rem;">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    <div id="matchFeedback" class="mt-1" style="font-size:0.78rem;"></div>
                                </div>
                            </div>

                            <div class="alert alert-warning border-0 rounded-3 mt-3 mb-0 p-2 ps-3" style="font-size:0.8rem;">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                Setelah password berhasil diubah, sesi aktif akan tetap berjalan. Pastikan Anda mengingat password baru.
                            </div>

                            <div class="d-flex justify-content-end mt-4 pt-3 border-top">
                                <button type="submit" class="btn btn-success fw-bold px-5 rounded-3 d-flex align-items-center gap-2" style="font-size:0.875rem;">
                                    <i class="bi bi-shield-check-fill"></i> Perbarui Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- ========== SECTION 3: KODE AKTIVASI ========== --}}
            @if(auth()->user()->role === 'admin')
            <div id="section-kode" class="profil-section d-none">
                <div class="profil-card">
                    <div class="card-header-custom">
                        <div class="icon-badge" style="background:#fffbeb;">
                            <i class="bi bi-key-fill text-warning"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-dark" style="font-size:0.95rem;">Kode Aktivasi & Kredensial</div>
                            <div class="text-muted" style="font-size:0.75rem;">Kelola kode aktivasi dan token keamanan akun Anda.</div>
                        </div>
                    </div>

                    <div class="p-4">

                        {{-- Status & Kode saat ini --}}
                        <div class="row g-3 mb-4">
                            <div class="col-12 col-md-5">
                                <div class="p-3 rounded-3 border bg-light">
                                    <div class="text-muted mb-1" style="font-size:0.78rem;"><i class="bi bi-info-circle me-1"></i>Status Aktivasi Akun</div>
                                    @if($user->is_active)
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge bg-success rounded-pill px-2">Aktif</span>
                                            <span class="text-success fw-semibold" style="font-size:0.875rem;">Akun sudah diaktifkan</span>
                                        </div>
                                    @else
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge bg-danger rounded-pill px-2">Nonaktif</span>
                                            <span class="text-danger fw-semibold" style="font-size:0.875rem;">Akun belum aktif</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <div class="col-12 col-md-7">
                                <div class="p-3 rounded-3 border bg-light">
                                    <div class="text-muted mb-2" style="font-size:0.78rem;"><i class="bi bi-qr-code me-1"></i>Kode Aktivasi Saat Ini</div>
                                    @if($user->kode_aktivasi)
                                        <div class="d-flex align-items-center gap-3 flex-wrap">
                                            <span class="kode-chip" id="kodeAktivasiDisplay">{{ $user->kode_aktivasi }}</span>
                                            <button type="button" class="btn btn-outline-secondary btn-sm rounded-3"
                                                    onclick="copyKode()" title="Salin kode">
                                                <i class="bi bi-clipboard" id="copyIcon"></i> Salin
                                            </button>
                                        </div>
                                    @else
                                        <div class="text-muted fst-italic" style="font-size:0.85rem;">
                                            <i class="bi bi-dash-circle me-1"></i>Belum ada kode aktivasi. Generate sekarang.
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Penjelasan --}}
                        <div class="alert border-0 rounded-3 p-3 mb-4" style="background:#f0f9ff;border-left:3px solid #38bdf8!important;font-size:0.82rem;">
                            <div class="fw-semibold text-info-emphasis mb-1"><i class="bi bi-lightbulb me-1"></i>Cara Kerja Kode Aktivasi</div>
                            <ul class="mb-0 ps-3">
                                <li>Kode aktivasi digunakan untuk mengaktifkan akun saat pertama kali login.</li>
                                <li>Guru baru dapat menggunakan kode ini untuk mengaktifkan akun mereka.</li>
                                <li>Setiap kali di-generate, kode lama akan menjadi tidak valid.</li>
                                <li>Simpan kode baru dan bagikan hanya kepada yang berhak.</li>
                            </ul>
                        </div>

                        {{-- Generate Kode Baru --}}
                        <form method="POST" action="{{ route('profil.generate-kode-aktivasi') }}"
                              onsubmit="return confirm('Generate kode aktivasi baru? Kode lama akan langsung diganti.')">
                            @csrf
                            <div class="d-flex align-items-center gap-3 flex-wrap pt-3 border-top">
                                <div class="text-muted" style="font-size:0.82rem;">
                                    <i class="bi bi-info-circle text-primary me-1"></i>
                                    Generate kode baru jika kode lama sudah bocor atau perlu direset.
                                </div>
                                <button type="submit" class="btn btn-warning fw-bold px-4 rounded-3 d-flex align-items-center gap-2 ms-md-auto" style="font-size:0.875rem;">
                                    <i class="bi bi-arrow-repeat"></i> Generate Kode Aktivasi Baru
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            @endif

        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // ===== Tab Switcher =====
    function switchTab(el, sectionId) {
        event.preventDefault();
        document.querySelectorAll('.profil-nav .nav-link').forEach(l => l.classList.remove('active'));
        el.classList.add('active');
        document.querySelectorAll('.profil-section').forEach(s => s.classList.add('d-none'));
        document.getElementById(sectionId).classList.remove('d-none');
    }

    // ===== Avatar Live Preview =====
    document.getElementById('inputFoto').addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;
        if (file.size > 2 * 1024 * 1024) {
            alert('Ukuran file terlalu besar. Maksimal 2 MB.');
            this.value = '';
            return;
        }
        const reader = new FileReader();
        reader.onload = e => document.getElementById('previewFoto').src = e.target.result;
        reader.readAsDataURL(file);
    });

    // ===== Show/Hide Password =====
    function togglePwd(inputId, btn) {
        const input = document.getElementById(inputId);
        const icon  = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'bi bi-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'bi bi-eye';
        }
    }

    // ===== Password Strength =====
    function checkStrength(val) {
        const bar   = document.getElementById('passwordStrengthBar');
        const label = document.getElementById('strengthLabel');
        let score = 0;
        if (val.length >= 8)              score++;
        if (/[A-Z]/.test(val))            score++;
        if (/[0-9]/.test(val))            score++;
        if (/[^A-Za-z0-9]/.test(val))     score++;

        const configs = [
            { pct:'0%',   cls:'bg-danger',  txt:'',         color:'#ef4444' },
            { pct:'25%',  cls:'bg-danger',  txt:'Lemah',    color:'#ef4444' },
            { pct:'50%',  cls:'bg-warning', txt:'Cukup',    color:'#f59e0b' },
            { pct:'75%',  cls:'bg-info',    txt:'Kuat',     color:'#06b6d4' },
            { pct:'100%', cls:'bg-success', txt:'Sangat Kuat', color:'#22c55e' },
        ];
        const c = configs[score];
        bar.style.width = c.pct;
        bar.className   = 'rounded ' + c.cls;
        bar.style.height = '5px';
        label.innerText = c.txt;
        label.style.color = c.color;
    }

    // ===== Confirm Password Match =====
    function checkMatch() {
        const pw   = document.getElementById('newPassword').value;
        const conf = document.getElementById('confirmPassword').value;
        const fb   = document.getElementById('matchFeedback');
        if (!conf) { fb.innerHTML = ''; return; }
        if (pw === conf) {
            fb.innerHTML = '<span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>Password cocok</span>';
        } else {
            fb.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle-fill me-1"></i>Password tidak cocok</span>';
        }
    }

    // ===== Copy Kode Aktivasi =====
    function copyKode() {
        const kode = document.getElementById('kodeAktivasiDisplay');
        if (!kode) return;
        navigator.clipboard.writeText(kode.innerText.trim()).then(() => {
            const icon = document.getElementById('copyIcon');
            icon.className = 'bi bi-clipboard-check';
            setTimeout(() => icon.className = 'bi bi-clipboard', 2000);
        });
    }

    // ===== Auto-open tab jika ada error password =====
    document.addEventListener('DOMContentLoaded', function () {
        @if(session('tab_aktif') === 'password' || $errors->has('current_password') || $errors->has('password'))
            const pwTab = document.querySelector('[onclick*="section-password"]');
            if (pwTab) switchTab(pwTab, 'section-password');
        @endif
    });
</script>
@endpush
