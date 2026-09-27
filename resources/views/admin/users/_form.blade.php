@php
    // Mode Lihat Saja: Petugas TU / Admin (non-IT) hanya membuka detail akun
    // user lain dalam mode readonly — seluruh field dikunci (disabled).
    $isEdit = $isEdit ?? false;
    $readonly = $readonly ?? false;
    $fieldDisabled = $readonly ? 'disabled' : '';

    // State suspend terpadu (khusus halaman edit): akun disuspend bila ada
    // batas waktu aktif (suspended_until > now) ATAU dinonaktifkan permanen
    // (is_active = false via emergency suspend). Selama suspend, checkbox
    // "Akun aktif" dikunci dalam state UNCHECKED + disabled — reaktivasi
    // hanya lewat tombol "Unsuspend / Aktifkan Kembali" pada halaman Edit.
    $isSuspended = $isEdit && ($user->isCurrentlySuspended() || ! $user->is_active);
    $checkboxDisabled = ($readonly || $isSuspended) ? 'disabled' : '';
    $isAccountActiveChecked = $isEdit && $user->is_active && ! $user->isCurrentlySuspended();
@endphp
@csrf
@if($isEdit)
    @method('PUT')
@endif

<div class="row g-3">
    <div class="col-12">
        <label class="form-label fw-semibold text-secondary small">NAMA LENGKAP <span class="text-danger">*</span></label>
        <input type="text" name="name" value="{{ old('name', $isEdit ? $user->nama : '') }}" class="form-control rounded-3" required maxlength="255" {{ $fieldDisabled }}>
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold text-secondary small">USERNAME <span class="text-danger">*</span></label>
        <input type="text" name="username" value="{{ old('username', $isEdit ? $user->username : '') }}" class="form-control rounded-3" required maxlength="100" {{ $fieldDisabled }}>
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold text-secondary small">NIP / NIK (OPSIONAL)</label>
        <input type="text" name="nip" value="{{ old('nip', $isEdit ? $user->nip : '') }}" class="form-control rounded-3" maxlength="50" {{ $fieldDisabled }}>
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold text-secondary small">NOMOR WHATSAPP / HP</label>
        <input type="text" name="no_hp" value="{{ old('no_hp', $isEdit ? $user->no_hp : '') }}" class="form-control rounded-3" maxlength="20" placeholder="Contoh: 081234567890" {{ $fieldDisabled }}>
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold text-secondary small">SUB-ROLE <span class="text-danger">*</span></label>
        <select name="sub_role" class="form-select rounded-3" required {{ $fieldDisabled }}>
            <option value="">-- Pilih Sub-Role --</option>
            @foreach($subRoles as $value => $label)
                @php
                    $selectedRole = old('sub_role', $isEdit ? $user->sub_role : '');
                @endphp
                <option value="{{ $value }}" {{ $selectedRole === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    @if($isEdit)
        @php
            // Kode utuh hanya pernah dirender ke DOM untuk Petugas IT (peek/eye).
            // Untuk non-IT, `full` disamakan dengan nilai masked → plaintext tidak bocor.
            $fullCode = (auth()->check() && auth()->user()->isPetugasIt())
                ? $user->kode_aktivasi
                : $user->kode_aktivasi_masked;
        @endphp
        <div class="col-md-6" x-data="{
            reveal: false,
            masked: {{ json_encode($user->kode_aktivasi_masked, JSON_UNESCAPED_UNICODE) }},
            full: {{ json_encode($fullCode, JSON_UNESCAPED_UNICODE) }}
        }">
            <label class="form-label fw-semibold text-secondary small">KODE AKTIVASI</label>
            <div class="input-group">
                <input type="password" name="kode_aktivasi"
                       :type="reveal ? 'text' : 'password'"
                       :value="reveal ? full : masked"
                       readonly disabled
                       class="form-control rounded-3 font-monospace"
                       maxlength="100"
                       autocomplete="off">
                @if(auth()->check() && auth()->user()->isPetugasIt() && !$readonly)
                    <button type="button" class="btn btn-outline-secondary rounded-3 ms-2" @click="reveal = !reveal"
                            aria-label="Lihat / sembunyikan kode aktivasi"
                            title="Lihat kode aktivasi (khusus Petugas IT)">
                        <i class="bi bi-eye" x-show="!reveal"></i>
                        <i class="bi bi-eye-slash" x-show="reveal"></i>
                    </button>
                @endif
            </div>
            <div class="form-text">
                Kode aktivasi bersifat rahasia dan ditampilkan tersensor. Perubahan kode hanya lewat alur pengajuan resmi.
            </div>
        </div>
    @else
        <div class="col-md-6">
            <label class="form-label fw-semibold text-secondary small">KODE AKTIVASI <span class="text-danger">*</span></label>
            <input type="text" name="kode_aktivasi" value="{{ old('kode_aktivasi', '') }}" class="form-control rounded-3" maxlength="100" placeholder="Kosongkan untuk dibuat otomatis" {{ $fieldDisabled }}>
        </div>
    @endif
    @if(!$isEdit)
        <div class="col-md-6">
            <label class="form-label fw-semibold text-secondary small">PASSWORD <span class="text-danger">*</span></label>
            <div class="input-group" x-data="{ show: false }">
                <input type="password" name="password" :type="show ? 'text' : 'password'"
                       class="form-control rounded-3" required minlength="8" maxlength="255"
                       autocomplete="new-password" placeholder="Minimal 8 karakter" {{ $fieldDisabled }}>
                <button type="button" class="btn btn-outline-secondary rounded-3 ms-2" @click="show = !show"
                        aria-label="Tampilkan / sembunyikan password" {{ $fieldDisabled ? 'disabled' : '' }}>
                    <i class="bi bi-eye" x-show="!show"></i>
                    <i class="bi bi-eye-slash" x-show="show"></i>
                </button>
            </div>
            <div class="form-text">Minimal 8 karakter. Simpan baik-baik — password tidak dapat dilihat lagi setelah disimpan.</div>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold text-secondary small">KONFIRMASI PASSWORD <span class="text-danger">*</span></label>
            <div class="input-group" x-data="{ show: false }">
                <input type="password" name="password_confirmation" :type="show ? 'text' : 'password'"
                       class="form-control rounded-3" required minlength="8" maxlength="255"
                       autocomplete="new-password" placeholder="Ulangi password yang sama" {{ $fieldDisabled }}>
                <button type="button" class="btn btn-outline-secondary rounded-3 ms-2" @click="show = !show"
                        aria-label="Tampilkan / sembunyikan konfirmasi password" {{ $fieldDisabled ? 'disabled' : '' }}>
                    <i class="bi bi-eye" x-show="!show"></i>
                    <i class="bi bi-eye-slash" x-show="show"></i>
                </button>
            </div>
            <div class="form-text">Harus sama dengan kolom Password. Login ke akun ini memerlukan password + Kode Aktivasi di atas.</div>
        </div>
    @endif
    @if($isEdit)
        <div class="col-12">
            <div class="form-check border rounded-3 p-3">
                <input type="checkbox" name="is_active" value="1" class="form-check-input ms-0 me-2" id="is_active" {{ old('is_active', $isAccountActiveChecked) ? 'checked' : '' }} {{ $checkboxDisabled }}>
                <label class="form-check-label fw-semibold" for="is_active">Akun aktif</label>
                @if($readonly)
                    <div class="form-text text-muted mt-1">Status akun (Suspend Darurat / Unsuspend) dikelola dari tombol aksi di halaman ini atau halaman Kelola User.</div>
                @elseif($isSuspended)
                    <div class="form-text text-warning mt-1">
                        <i class="bi bi-exclamation-triangle me-1"></i>Akun sedang disuspend — gunakan tombol “Unsuspend / Aktifkan Kembali” untuk mengaktifkannya kembali.
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>