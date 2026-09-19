@extends('layouts.app')

@section('title', 'Tambah Petugas Piket - Kurikulum')

@push('styles')
<style>
    /* Custom Styling Checkbox Card Guru */
    .guru-checkbox-card {
        border: 1px solid #e2e8f0;
        border-radius: 0.75rem;
        background-color: #ffffff;
        padding: 0.85rem 1rem;
        transition: all 0.15s ease;
        cursor: pointer;
        user-select: none;
        position: relative;
    }

    .guru-checkbox-card:hover {
        border-color: #93c5fd;
        background-color: #f8fafc;
        box-shadow: 0 4px 12px rgba(22, 119, 255, 0.06);
    }

    .guru-checkbox-card.selected {
        border-color: #2563eb;
        background-color: #eff6ff;
        box-shadow: 0 0 0 1px #2563eb;
    }

    .guru-checkbox-card input[type="checkbox"] {
        width: 1.2rem;
        height: 1.2rem;
        cursor: pointer;
    }

    /* Styling untuk field select Waka */
    .select-waka-card {
        border: 1px solid #e2e8f0;
        border-radius: 0.75rem;
        background-color: #ffffff;
        padding: 0.85rem 1rem;
        margin-bottom: 1rem;
        cursor: pointer;
    }

    .select-waka-card:hover {
        border-color: #93c5fd;
        background-color: #f8fafc;
    }

    .select-waka-card.selected {
        border-color: #2563eb;
        background-color: #eff6ff;
    }

    .piket-form-card {
        padding: 0;
    }

    .form-section {
        margin-bottom: 1.5rem;
    }

    .shift-panel {
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        padding: 1rem 1rem 1.1rem;
        height: 100%;
        background: #ffffff;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.02);
    }

    /* Styling untuk header shift */
    .shift-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.75rem;
    }

    .shift-title {
        font-size: 0.8rem;
        font-weight: 600;
        color: #374151;
    }

    .shift-count {
        font-size: 0.7rem;
        color: #6b7280;
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">

    {{-- Header dengan Tombol Kembali --}}
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('kurikulum.jadwal-piket.index') }}"
               class="btn btn-light border rounded-3 px-3 py-2 fw-semibold d-flex align-items-center gap-2"
               style="font-size: 0.875rem;">
                <i class="bi bi-arrow-left"></i>
                <span>Kembali</span>
            </a>
            <div>
                <h2 class="fw-black text-dark mb-0" style="font-weight: 900; font-size: 1.75rem; letter-spacing: -0.02em;">
                    Tambah Petugas Piket Guru
                </h2>
                <p class="text-muted mb-0" style="font-size: 0.875rem;">
                    Pilih hari dan atur penugasan piket guru sesuai format SK (Pagi & Siang).
                </p>
            </div>
        </div>
        <a href="{{ route('kurikulum.jadwal-piket.shifts') }}" class="btn btn-outline-secondary rounded-3">
            <i class="bi bi-clock me-1"></i> Atur Shift & Kuota
        </a>
    </div>

    {{-- Alert Error --}}
    @if(isset($errors) && $errors->any())
        <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Terjadi Kesalahan Input:</strong>
            <ul class="mb-0 mt-1 ps-3 small">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Card Utama Form Dedicated Page --}}
    <div class="card border-0 rounded-4 shadow-sm bg-white overflow-hidden piket-form-card">
        <form action="{{ route('kurikulum.jadwal-piket.store') }}" method="POST">
            @csrf
        <div class="card-header bg-white border-0 pt-4 pb-3 px-4 px-lg-5">
            <div class="d-flex align-items-center gap-2">
                <div class="rounded-2 d-flex align-items-center justify-content-center bg-primary-subtle text-primary"
                     style="width: 36px; height: 36px;">
                    <i class="bi bi-person-plus-fill fs-5"></i>
                </div>
                <h5 class="fw-bold mb-0 text-dark">Form Penugasan Petugas Piket</h5>
            </div>
        </div>

        {{-- Hidden form method check -->
        @method('POST')

        <input type="hidden" name="form_submitted" value="1">
        <input type="hidden" name="minggu_ke" value="{{ $mingguKe }}">

        <div class="card-body p-4 p-md-5">
            {{-- 1. Pilihan Hari Piket --}}
            <div class="form-section mb-4">
                <label class="form-label fw-bold text-dark mb-2" style="font-size: 0.9rem;">
                    <i class="bi bi-calendar-event text-primary me-1"></i> Pilih Hari Piket <span class="text-danger">*</span>
                </label>
                <div class="d-flex gap-2 flex-wrap">
                    @foreach($hariList as $h)
                        <input type="radio" class="btn-check" name="hari" id="hari_{{ $h }}"
                               value="{{ $h }}" {{ old('hari', $selectedHari) === $h ? 'checked' : '' }}
                               onchange="window.location.href = '{{ route('kurikulum.jadwal-piket.create') }}?hari=' + this.value + '&minggu_ke={{ $mingguKe }}'">
                        <label class="btn btn-outline-primary rounded-3 px-3 py-2 fw-semibold" for="hari_{{ $h }}" style="font-size: 0.875rem;">
                            {{ $h }}
                        </label>
                    @endforeach
                </div>
            </div>

            <hr class="my-4" style="border-color: #f1f5f9;">

            {{-- 2. Waka Piket: Single dropdown --}}
            <div class="form-section mb-4">
                <label class="form-label fw-bold text-dark mb-2" style="font-size: 0.9rem;">
                    <i class="bi bi-person-badge text-primary me-1"></i> Waka Piket <span class="text-danger">*</span>
                </label>
                <select name="waka_user_id" class="form-select rounded-3" style="font-size: 0.875rem;" required>
                    <option value="">-- Pilih Waka Piket --</option>
                    @foreach($wakaList as $waka)
                        <option value="{{ $waka->id }}"
                           {{ old('waka_user_id', $assignedWakaId ?? null) == $waka->id ? 'selected' : '' }}>{{ $waka->nama }}</option>
                    @endforeach
                </select>
                <small class="text-muted">Guru dengan jabatan Waka/kakurikulum</small>
            </div>

            <div class="row g-4">
            @if($shiftList->isEmpty())
            {{-- 3. Sesi Pagi (07.00 - 11.00) --}}
            <div class="col-12 col-xl-6 mb-4">
                <div class="border rounded-4 p-4 h-100">
                <div class="shift-header">
                    <span class="shift-title">SESI PAGI (07.00 - 11.00)</span>
                </div>

                {{-- Koordinator Pagi: Single dropdown --}}
                <div class="mb-2">
                    <label class="form-label fw-bold text-dark small">Koordinator Pagi</label>
                    <select name="koordinator_pagi_user_id" class="form-select rounded-3" style="font-size: 0.875rem;">
                        <option value="">-- Pilih Koordinator Pagi --</option>
                        @foreach($guruList as $guru)
                            <option value="{{ $guru->id }}"
                               {{ old('koordinator_pagi_user_id') == $guru->id ? 'selected' : '' }}>{{ $guru->nama }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Petugas Piket Pagi: Multiple select (3-4 orang) --}}
                <div>
                    <label class="form-label fw-bold text-dark small">Petugas Piket Pagi (3-4 orang)</label>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-2 fw-semibold" style="font-size: 0.8rem;" id="badgeCounterPagi">
                            0 Guru Dipilih
                        </span>
                    </div>
                    <div class="row g-3" id="guruGridListPagi" style="max-height: 480px; overflow-y: auto; scrollbar-width: thin;">
                        @foreach($guruList as $guru)
                            @php
                                $isChecked = in_array($guru->id, old('petugas_pagi_user_id', $assignedPetugasPagiIds ?? []));
                            @endphp
                            <div class="col-12 col-md-6 col-lg-4 guru-item-col"
                                 data-name="{{ strtolower($guru->nama) }}"
                                 data-nip="{{ strtolower($guru->nip ?? '') }}">
                                <div class="guru-checkbox-card d-flex align-items-center gap-3 {{ $isChecked ? 'selected' : '' }}"
                                     onclick="toggleCardCheck(this, event)">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input guru-checkbox" type="checkbox"
                                               name="petugas_pagi_user_id[]"
                                               value="{{ $guru->id }}"
                                               id="guru_cb_pagi_{{ $guru->id }}"
                                               {{ $isChecked ? 'checked' : '' }}
                                               onchange="updateCounterPagi()">
                                    </div>
                                    <div class="rounded-circle bg-primary-subtle text-primary fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                                         style="width: 38px; height: 38px; font-size: 0.85rem;">
                                        {{ strtoupper(substr($guru->nama, 0, 2)) }}
                                    </div>
                                    <div class="overflow-hidden flex-grow-1">
                                        <div class="fw-bold text-dark text-truncate" style="font-size: 0.88rem;" title="{{ $guru->nama }}">
                                            {{ $guru->nama }}
                                        </div>
                                        <div class="text-muted text-truncate" style="font-size: 0.75rem;">
                                            NIP: {{ $guru->nip ?? '-' }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                </div>
            </div>

            {{-- 4. Sesi Siang (11.00 - 15.00) --}}
            <div class="col-12 col-xl-6 mb-4">
                <div class="shift-panel">
                <div class="shift-header">
                    <span class="shift-title">SESI SIANG (11.00 - 15.00)</span>
                </div>

                {{-- Koordinator Siang: Single dropdown --}}
                <div class="mb-2">
                    <label class="form-label fw-bold text-dark small">Koordinator Siang</label>
                    <select name="koordinator_siang_user_id" class="form-select rounded-3" style="font-size: 0.875rem;">
                        <option value="">-- Pilih Koordinator Siang --</option>
                        @foreach($guruList as $guru)
                            <option value="{{ $guru->id }}"
                               {{ old('koordinator_siang_user_id') == $guru->id ? 'selected' : '' }}>{{ $guru->nama }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Petugas Piket Siang: Multiple select (3-4 orang) --}}
                <div>
                    <label class="form-label fw-bold text-dark small">Petugas Piket Siang (3-4 orang)</label>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-2 fw-semibold" style="font-size: 0.8rem;" id="badgeCounterSiang">
                            0 Guru Dipilih
                        </span>
                    </div>
                    <div class="row g-3" id="guruGridListSiang" style="max-height: 480px; overflow-y: auto; scrollbar-width: thin;">
                        @foreach($guruList as $guru)
                            @php
                                $isChecked = in_array($guru->id, old('petugas_siang_user_id', $assignedPetugasSiangIds ?? []));
                            @endphp
                            <div class="col-12 col-md-6 col-lg-4 guru-item-col"
                                 data-name="{{ strtolower($guru->nama) }}"
                                 data-nip="{{ strtolower($guru->nip ?? '') }}">
                                <div class="guru-checkbox-card d-flex align-items-center gap-3 {{ $isChecked ? 'selected' : '' }}"
                                     onclick="toggleCardCheck(this, event)">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input guru-checkbox" type="checkbox"
                                               name="petugas_siang_user_id[]"
                                               value="{{ $guru->id }}"
                                               id="guru_cb_siang_{{ $guru->id }}"
                                               {{ $isChecked ? 'checked' : '' }}
                                               onchange="updateCounterSiang()">
                                    </div>
                                    <div class="rounded-circle bg-primary-subtle text-primary fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                                         style="width: 38px; height: 38px; font-size: 0.85rem;">
                                        {{ strtoupper(substr($guru->nama, 0, 2)) }}
                                    </div>
                                    <div class="overflow-hidden flex-grow-1">
                                        <div class="fw-bold text-dark text-truncate" style="font-size: 0.88rem;" title="{{ $guru->nama }}">
                                            {{ $guru->nama }}
                                        </div>
                                        <div class="text-muted text-truncate" style="font-size: 0.75rem;">
                                            NIP: {{ $guru->nip ?? '-' }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                </div>
            </div>
            </div>

            @else
                @foreach($shiftList as $shift)
                    @php
                        $selectedUsers = old('shift_users.' . $shift->id, $assignedByShift[$shift->id] ?? []);
                    @endphp
                    <div class="col-12 col-xl-6 mb-4">
                        <div class="shift-panel">
                            <div class="shift-header">
                                <span class="shift-title">{{ strtoupper($shift->nama) }} ({{ $shift->jam_label }})</span>
                                <span class="shift-count" data-quota="{{ $shift->maksimal_petugas }}">Maks. {{ $shift->maksimal_petugas }} petugas</span>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold text-dark small">Petugas {{ $shift->nama }}</label>
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-2" id="badgeCounterShift{{ $shift->id }}">{{ count($selectedUsers) }} dipilih</span>
                                    <span class="text-muted small">Tambahkan lebih dari kuota jika diperlukan.</span>
                                </div>
                                <div class="row g-3" style="max-height: 480px; overflow-y: auto; scrollbar-width: thin;">
                                    @foreach($guruList as $guru)
                                        @php $isChecked = in_array($guru->id, $selectedUsers); @endphp
                                        <div class="col-12 col-md-6 col-xxl-4">
                                            <label class="guru-checkbox-card d-flex align-items-center gap-3 {{ $isChecked ? 'selected' : '' }}">
                                                <input class="form-check-input shift-checkbox" type="checkbox" name="shift_users[{{ $shift->id }}][]" value="{{ $guru->id }}" data-shift-id="{{ $shift->id }}" data-quota="{{ $shift->maksimal_petugas }}" {{ $isChecked ? 'checked' : '' }}>
                                                <span class="rounded-circle bg-primary-subtle text-primary fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; font-size: 0.85rem;">{{ strtoupper(substr($guru->nama, 0, 2)) }}</span>
                                                <span class="overflow-hidden"><span class="d-block fw-bold text-dark text-truncate" style="font-size: 0.88rem;">{{ $guru->nama }}</span><span class="d-block text-muted text-truncate" style="font-size: 0.75rem;">NIP: {{ $guru->nip ?? '-' }}</span></span>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            @endif

            {{-- Card Footer Actions --}}
            <div class="card-footer bg-white border-0 p-4 p-lg-5 pt-3 d-flex align-items-center justify-content-between flex-wrap gap-3">
                <a href="{{ route('kurikulum.jadwal-piket.index') }}" class="btn btn-light rounded-3 px-4">
                    Batal
                </a>
                <button type="submit" class="btn btn-primary rounded-3 px-4 fw-shadow-sm" style="font-size: 0.9rem;">
                    <i class="bi bi-check-lg me-1"></i> Simpan Penugasan Piket
                </button>
            </div>
        </div>
        </form>
    </div>

</div>
@endsection

@push('scripts')
<script>
    function toggleCardCheck(card, event) {
        if (event.target.type !== 'checkbox') {
            var checkbox = card.querySelector('input[type="checkbox"]');
            checkbox.checked = !checkbox.checked;
            checkbox.dispatchEvent(new Event('change', { bubbles: true }));
        }
        card.classList.toggle('selected', card.querySelector('input[type="checkbox"]').checked);
    }

    document.querySelectorAll('.shift-checkbox').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            var shiftId = this.dataset.shiftId;
            var selected = document.querySelectorAll('.shift-checkbox[data-shift-id="' + shiftId + '"]:checked').length;
            var quota = Number(this.dataset.quota);
            var badge = document.getElementById('badgeCounterShift' + shiftId);
            badge.textContent = selected + ' dipilih';
            if (selected > quota && !window.confirm('Jumlah petugas melebihi kuota normal (' + quota + '). Lanjutkan?')) {
                this.checked = false;
                this.closest('label').classList.remove('selected');
                badge.textContent = (selected - 1) + ' dipilih';
                return;
            }
            this.closest('label').classList.toggle('selected', this.checked);
        });
    });

    // Counter untuk Petugas Piket Pagi
    function updateCounterPagi() {
        var checked = document.querySelectorAll('#guruGridListPagi .guru-checkbox:checked').length;
        var badge = document.getElementById('badgeCounterPagi');
        if (badge) badge.textContent = checked + ' Guru Dipilih';
    }

    // Counter untuk Petugas Piket Siang
    function updateCounterSiang() {
        var checked = document.querySelectorAll('#guruGridListSiang .guru-checkbox:checked').length;
        var badge = document.getElementById('badgeCounterSiang');
        if (badge) badge.textContent = checked + ' Guru Dipilih';
    }

    // Fungsi toggle check all
    function toggleSelectAllPagi(selectAll) {
        if (selectAll) {
            document.querySelectorAll('#guruGridListPagi .guru-checkbox').forEach(function (checkbox) {
                checkbox.checked = true;
            });
        } else {
            document.querySelectorAll('#guruGridListPagi .guru-checkbox').forEach(function (checkbox) {
                checkbox.checked = false;
            });
        }
        updateCounterPagi();
    }

    function toggleSelectAllSiang(selectAll) {
        if (selectAll) {
            document.querySelectorAll('#guruGridListSiang .guru-checkbox').forEach(function (checkbox) {
                checkbox.checked = true;
            });
        } else {
            document.querySelectorAll('#guruGridListSiang .guru-checkbox').forEach(function (checkbox) {
                checkbox.checked = false;
            });
        }
        updateCounterSiang();
    }

    // Inisialisasi counter saat load
    document.addEventListener('DOMContentLoaded', function() {
        updateCounterPagi();
        updateCounterSiang();
    });
</script>
@endpush