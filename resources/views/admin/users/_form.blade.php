@csrf
@if($isEdit)
    @method('PUT')
@endif

<div class="row g-3">
    <div class="col-12">
        <label class="form-label fw-semibold text-secondary small">NAMA LENGKAP <span class="text-danger">*</span></label>
        <input type="text" name="name" value="{{ old('name', $isEdit ? $user->nama : '') }}" class="form-control rounded-3" required maxlength="255">
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold text-secondary small">USERNAME / NIP / NIK <span class="text-danger">*</span></label>
        <input type="text" name="username" value="{{ old('username', $isEdit ? $user->username : '') }}" class="form-control rounded-3" required maxlength="100">
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold text-secondary small">NIP / NIK (OPSIONAL)</label>
        <input type="text" name="nip" value="{{ old('nip', $isEdit ? $user->nip : '') }}" class="form-control rounded-3" maxlength="50">
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold text-secondary small">SUB-ROLE <span class="text-danger">*</span></label>
        <select name="sub_role" class="form-select rounded-3" required>
            <option value="">-- Pilih Sub-Role --</option>
            @foreach($subRoles as $value => $label)
                @php
                    $selectedRole = old('sub_role', $isEdit ? $user->sub_role : '');
                @endphp
                <option value="{{ $value }}" {{ $selectedRole === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold text-secondary small">KODE AKTIVASI (OPSIONAL)</label>
        <input type="text" name="kode_aktivasi" value="{{ old('kode_aktivasi', $isEdit ? $user->kode_aktivasi : '') }}" class="form-control rounded-3" maxlength="100" placeholder="Kosongkan untuk dibuat otomatis">
    </div>
    @if($isEdit)
        <div class="col-12">
            <div class="form-check border rounded-3 p-3">
                <input type="checkbox" name="is_active" value="1" class="form-check-input ms-0 me-2" id="is_active" {{ old('is_active', $user->is_active) ? 'checked' : '' }}>
                <label class="form-check-label fw-semibold" for="is_active">Akun aktif</label>
            </div>
        </div>
    @endif
</div>
