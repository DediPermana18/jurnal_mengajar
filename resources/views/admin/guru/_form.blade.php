@csrf
@if($isEdit)
    @method('PUT')
@endif

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label fw-semibold text-secondary small">NAMA GURU LENGKAP <span class="text-danger">*</span></label>
        <input type="text" name="nama" value="{{ old('nama', $isEdit ? $guru->nama : '') }}" class="form-control rounded-3" required maxlength="255">
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold text-secondary small">NIP</label>
        <input type="text" name="nip" value="{{ old('nip', $isEdit ? $guru->nip : '') }}" class="form-control rounded-3" maxlength="50">
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold text-secondary small">USERNAME <span class="text-danger">*</span></label>
        <input type="text" name="username" value="{{ old('username', $isEdit ? $guru->username : '') }}" class="form-control rounded-3" required maxlength="100">
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold text-secondary small">KODE AKTIVASI (OPSIONAL)</label>
        <input type="text" name="kode_aktivasi" value="{{ old('kode_aktivasi', $isEdit ? $guru->kode_aktivasi : '') }}" class="form-control rounded-3" maxlength="100" placeholder="Kosongkan untuk dibuat otomatis">
    </div>
    @if(!$isEdit)
        <div class="col-md-6">
            <label class="form-label fw-semibold text-secondary small">PASSWORD AKUN</label>
            <input type="password" name="password" class="form-control rounded-3" minlength="6" placeholder="Kosongkan untuk password123">
        </div>
    @endif
    <div class="col-md-6">
        <label class="form-label fw-semibold text-secondary small">ROLE GURU <span class="text-danger">*</span></label>
        <select name="role" id="guru_role" class="form-select rounded-3" required onchange="toggleGuruFields()">
            @php($selectedGuruRole = old('role', $isEdit ? ($guru->sub_role ?: ($guru->role === 'guru' ? 'guru' : $guru->role)) : ''))
            <option value="guru_mapel" {{ $selectedGuruRole === 'guru_mapel' ? 'selected' : '' }}>Guru Mapel</option>
            <option value="wali_kelas" {{ $selectedGuruRole === 'wali_kelas' ? 'selected' : '' }}>Wali Kelas</option>
            <option value="guru" {{ $selectedGuruRole === 'guru' ? 'selected' : '' }}>Guru Umum</option>
        </select>
    </div>
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
