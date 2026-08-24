@csrf
@if($isEdit)
    @method('PUT')
@endif

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label fw-semibold text-secondary small">NAMA GURU LENGKAP <span class="text-danger">*</span></label>
        <input type="text" name="nama" value="{{ old('nama', $isEdit ? $guru->nama : '') }}" class="form-control rounded-3" required maxlength="255" placeholder="Masukkan nama lengkap guru">
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold text-secondary small">NIP</label>
        <input type="text" name="nip" value="{{ old('nip', $isEdit ? $guru->nip : '') }}" class="form-control rounded-3" maxlength="50" placeholder="Masukkan NIP (opsional)">
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold text-secondary small">USERNAME <span class="text-danger">*</span></label>
        <input type="text" name="username" value="{{ old('username', $isEdit ? $guru->username : '') }}" class="form-control rounded-3" required maxlength="100" placeholder="Masukkan username login">
    </div>
    @if(!$isEdit)
        <div class="col-md-6">
            <label class="form-label fw-semibold text-secondary small">PASSWORD AKUN</label>
            <input type="password" name="password" class="form-control rounded-3" minlength="6" placeholder="Kosongkan untuk password default (password123)">
        </div>
        <div class="col-md-12">
            <label class="form-label fw-semibold text-secondary small">ROLE GURU <span class="text-danger">*</span></label>
            <select name="role" id="guru_role" class="form-select rounded-3" required onchange="toggleGuruFields()">
                @php($selectedGuruRole = old('role', 'guru_mapel'))
                <option value="guru_mapel" {{ $selectedGuruRole === 'guru_mapel' ? 'selected' : '' }}>Guru Mapel</option>
                <option value="wali_kelas" {{ $selectedGuruRole === 'wali_kelas' ? 'selected' : '' }}>Wali Kelas</option>
                <option value="guru" {{ $selectedGuruRole === 'guru' ? 'selected' : '' }}>Guru Umum</option>
            </select>
        </div>
    @else
        <div class="col-md-6">
            <label class="form-label fw-semibold text-secondary small">ROLE GURU <span class="text-danger">*</span></label>
            <select name="role" id="guru_role" class="form-select rounded-3" required onchange="toggleGuruFields()">
                @php($selectedGuruRole = old('role', $guru->sub_role ?: ($guru->role === 'guru' ? 'guru' : $guru->role)))
                <option value="guru_mapel" {{ $selectedGuruRole === 'guru_mapel' ? 'selected' : '' }}>Guru Mapel</option>
                <option value="wali_kelas" {{ $selectedGuruRole === 'wali_kelas' ? 'selected' : '' }}>Wali Kelas</option>
                <option value="guru" {{ $selectedGuruRole === 'guru' ? 'selected' : '' }}>Guru Umum</option>
            </select>
        </div>
    @endif
    <div class="col-12" id="guru_kelas_field">
        <label class="form-label fw-semibold text-secondary small">KELAS YANG DIWALIIN <span class="text-danger">*</span></label>
        <select name="kelas_id" class="form-select rounded-3">
            <option value="">-- Pilih Kelas --</option>
            @foreach($daftarKelas as $kelas)
                @php($isOwnKelas = $currentKelasId == $kelas->id || ($isEdit && $guru->kelasWali->contains('id', $kelas->id)))
                @php($hasOtherWali = $kelas->id_wali_kelas && !$isOwnKelas)
                <option
                    value="{{ $kelas->id }}"
                    @selected($isOwnKelas)
                    @disabled($hasOtherWali)
                >
                    {{ $kelas->nama_kelas }}{{ $kelas->jurusan ? ' - ' . $kelas->jurusan->nama_jurusan : '' }}{{ $hasOtherWali ? ' (dipegang guru lain)' : '' }}
                </option>
            @endforeach
        </select>
        <div class="form-text">Wajib diisi untuk role Wali Kelas.</div>
    </div>
    @if($isEdit)
        <div class="col-12">
            <div class="form-check border rounded-3 p-3 bg-light">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" class="form-check-input ms-0 me-2" id="is_active_guru" {{ old('is_active', $guru->is_active) ? 'checked' : '' }}>
                <label class="form-check-label fw-semibold text-dark" for="is_active_guru">Status Akun Aktif (Centang agar guru dapat login ke sistem)</label>
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
    function toggleGuruFields() {
        const role = document.getElementById('guru_role')?.value;
        const kelasField = document.getElementById('guru_kelas_field');
        if (kelasField) kelasField.style.display = role === 'wali_kelas' ? 'block' : 'none';
    }
    document.addEventListener('DOMContentLoaded', toggleGuruFields);
</script>
@endpush
