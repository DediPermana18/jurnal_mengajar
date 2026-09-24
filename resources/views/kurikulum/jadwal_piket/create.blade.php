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

    /* Guru yang sudah dipilih di shift lain (Pagi <-> Siang) dikunci */
    .guru-checkbox-card.is-locked {
        opacity: 0.5;
        cursor: not-allowed;
        pointer-events: none;
        border-color: #e2e8f0;
        background-color: #f8fafc;
        box-shadow: none;
    }

    /* ==== Card shift minimalis: bg-white rounded-xl shadow-sm border border-gray-200 p-6 ==== */
    .shift-panel {
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        padding: 1.5rem;
        height: 100%;
        background: #ffffff;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    }

    /* Styling untuk header shift */
    .shift-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
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

    /* ===== Tombol Pilih Hari Piket: grid 5 kolom proporsional ===== */
    /* Lebar terbagi rata (20% per tombol) di semua layar; padding & font
       diperkecil di mobile agar nama hari ("Selasa", "Jumat", dst.) tidak
       terpotong ke kanan atau patah dua baris. CSS halaman menimpa .btn
       Bootstrap (loaded lebih awal & unlayered), jadi nilai ini dipakai. */
    .day-pill {
        width: 100%;
        padding: 0.5rem 0.25rem;
        font-size: 0.73rem;
        white-space: nowrap;
    }

    @media (min-width: 576px) {
        .day-pill {
            padding: 0.625rem 0.5rem;
            font-size: 0.875rem;
        }
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
        <a href="{{ route('kurikulum.jadwal-piket.shifts') }}"
           class="border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 no-underline font-medium px-4 py-2 rounded-lg shadow-sm transition flex items-center gap-2 whitespace-nowrap"
           title="Atur shift, jam bertugas, dan kuota petugas piket">
            <i class="bi bi-gear"></i>
            <span>Pengaturan Shift & Kuota</span>
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

    <form action="{{ route('kurikulum.jadwal-piket.store') }}" method="POST">
        @csrf

        <input type="hidden" name="form_submitted" value="1">
        <input type="hidden" name="minggu_ke" value="{{ $mingguKe }}">

        {{-- ===== Pengaturan Utama: Pilih Hari & Waka Piket (langsung di background halaman) ===== --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

            {{-- 1. Pilihan Hari Piket --}}
            <div>
                <label class="form-label fw-bold text-dark mb-2" style="font-size: 0.9rem;">
                    <i class="bi bi-calendar-event text-primary me-1"></i> Pilih Hari Piket <span class="text-danger">*</span>
                </label>
                <div class="grid grid-cols-5 gap-1.5 sm:gap-2">
                    @foreach($hariList as $h)
                        <input type="radio" class="btn-check" name="hari" id="hari_{{ $h }}"
                               value="{{ $h }}" {{ old('hari', $selectedHari) === $h ? 'checked' : '' }}
                               onchange="window.location.href = '{{ route('kurikulum.jadwal-piket.create') }}?hari=' + this.value + '&minggu_ke={{ $mingguKe }}'">
                        <label class="btn btn-outline-primary rounded-3 day-pill w-full text-center px-1 py-2 text-xs sm:text-sm font-medium text-truncate"
                               for="hari_{{ $h }}">
                            {{ $h }}
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- 2. Waka Piket: Single dropdown --}}
            <div>
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
                <small class="text-muted d-block mt-2">Guru dengan jabatan Waka/Kakurikulum/Waka Piket</small>
            </div>

        </div>

        {{-- ===== Penugasan Shift: Card PAGI & SIANG terpisah ===== --}}
        <div class="space-y-6 shift-panels-stack">
        @if($shiftList->isEmpty())
            {{-- Card 1: SHIFT PAGI --}}
            <div class="shift-panel w-100 bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-4">
                <div class="shift-header">
                    <span class="shift-title">SESI PAGI (07.00 - 11.00)</span>
                </div>

                {{-- Koordinator Pagi: Single dropdown --}}
                <div>
                    <label class="form-label fw-bold text-dark small">Koordinator Piket Pagi</label>
                    <select name="koordinator_pagi_user_id" class="form-select rounded-3" style="font-size: 0.875rem;">
                        <option value="">-- Pilih Koordinator Piket Pagi --</option>
                        @foreach($guruList as $guru)
                            <option value="{{ $guru->id }}"
                               {{ old('koordinator_pagi_user_id', $assignedKoordinatorPagiIds[0] ?? null) == $guru->id ? 'selected' : '' }}>{{ $guru->nama }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Petugas Piket Pagi: Multiple select (3-4 orang) --}}
                <div>
                    <label class="form-label fw-bold text-dark small">Petugas Piket Pagi (3-4 orang)</label>
                    <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center gap-2 mb-2">
                        <input type="search" class="form-control form-control-sm rounded-3 guru-search-input" data-panel="#guruGridListPagi"
                               placeholder="Cari Nama / NIP Guru..." style="min-width: 0;" autocomplete="off">
                        <div class="d-flex align-items-center gap-2 ms-sm-auto flex-shrink-0">
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-2 fw-semibold" style="font-size: 0.8rem;" id="badgeCounterPagi">
                                0 Guru Dipilih
                            </span>
                            <a href="#" class="small fw-semibold text-primary text-decoration-none guru-select-all" data-panel="#guruGridListPagi">Pilih Semua</a>
                            <span class="text-muted small">·</span>
                            <a href="#" class="small fw-semibold text-muted text-decoration-none guru-clear" data-panel="#guruGridListPagi">Bersihkan</a>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" id="guruGridListPagi" style="max-height: 480px; overflow-y: auto; scrollbar-width: thin;">
                        @foreach($guruList as $guru)
                            @php
                                $isChecked = in_array($guru->id, old('petugas_pagi_user_id', $assignedPetugasPagiIds ?? []));
                            @endphp
                            <div class="guru-item-col"
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

            {{-- Card 2: SHIFT SIANG --}}
            <div class="shift-panel w-100 bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-4">
                <div class="shift-header">
                    <span class="shift-title">SESI SIANG (11.00 - 15.00)</span>
                </div>

                {{-- Koordinator Siang: Single dropdown --}}
                <div>
                    <label class="form-label fw-bold text-dark small">Koordinator Piket Siang</label>
                    <select name="koordinator_siang_user_id" class="form-select rounded-3" style="font-size: 0.875rem;">
                        <option value="">-- Pilih Koordinator Piket Siang --</option>
                        @foreach($guruList as $guru)
                            <option value="{{ $guru->id }}"
                               {{ old('koordinator_siang_user_id', $assignedKoordinatorSiangIds[0] ?? null) == $guru->id ? 'selected' : '' }}>{{ $guru->nama }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Petugas Piket Siang: Multiple select (3-4 orang) --}}
                <div>
                    <label class="form-label fw-bold text-dark small">Petugas Piket Siang (3-4 orang)</label>
                    <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center gap-2 mb-2">
                        <input type="search" class="form-control form-control-sm rounded-3 guru-search-input" data-panel="#guruGridListSiang"
                               placeholder="Cari Nama / NIP Guru..." style="min-width: 0;" autocomplete="off">
                        <div class="d-flex align-items-center gap-2 ms-sm-auto flex-shrink-0">
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-2 fw-semibold" style="font-size: 0.8rem;" id="badgeCounterSiang">
                                0 Guru Dipilih
                            </span>
                            <a href="#" class="small fw-semibold text-primary text-decoration-none guru-select-all" data-panel="#guruGridListSiang">Pilih Semua</a>
                            <span class="text-muted small">·</span>
                            <a href="#" class="small fw-semibold text-muted text-decoration-none guru-clear" data-panel="#guruGridListSiang">Bersihkan</a>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" id="guruGridListSiang" style="max-height: 480px; overflow-y: auto; scrollbar-width: thin;">
                        @foreach($guruList as $guru)
                            @php
                                $isChecked = in_array($guru->id, old('petugas_siang_user_id', $assignedPetugasSiangIds ?? []));
                            @endphp
                            <div class="guru-item-col"
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

        @else
            @foreach($shiftList as $shift)
                @php
                    $sesiNama = strtolower($shift->nama);
                    $sesi = str_starts_with($sesiNama, 'pagi') ? 'pagi' : (str_starts_with($sesiNama, 'siang') ? 'siang' : null);
                    $kolomKoordinator = $sesi === 'pagi' ? 'koordinator_pagi_user_id' : ($sesi === 'siang' ? 'koordinator_siang_user_id' : null);
                    $assignedKoordinator = $kolomKoordinator === 'koordinator_pagi_user_id'
                        ? ($assignedKoordinatorPagiIds[0] ?? null)
                        : ($kolomKoordinator === 'koordinator_siang_user_id' ? ($assignedKoordinatorSiangIds[0] ?? null) : null);
                    $selectedUsers = old('shift_users.' . $shift->id, $assignedByShift[$shift->id] ?? []);
                @endphp
                <div class="shift-panel w-100 bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-4">
                    <div class="shift-header">
                        <span class="shift-title">{{ strtoupper($shift->nama) }} ({{ $shift->jam_label }})</span>
                        <span class="shift-count" data-quota="{{ $shift->maksimal_petugas }}">Maks. {{ $shift->maksimal_petugas }} petugas</span>
                    </div>
                    @if($kolomKoordinator)
                    <div>
                        <label class="form-label fw-bold text-dark small">Koordinator Piket {{ ucfirst($shift->nama) }}</label>
                        <select name="{{ $kolomKoordinator }}" class="form-select rounded-3" style="font-size: 0.875rem;">
                            <option value="">-- Pilih Koordinator Piket {{ ucfirst($shift->nama) }} --</option>
                            @foreach($guruList as $guru)
                                <option value="{{ $guru->id }}" {{ old($kolomKoordinator, $assignedKoordinator) == $guru->id ? 'selected' : '' }}>{{ $guru->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    <div>
                        <label class="form-label fw-bold text-dark small">Petugas {{ $shift->nama }}</label>
                        <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center gap-2 mb-2">
                            <input type="search" class="form-control form-control-sm rounded-3 guru-search-input" data-panel="#guruGridListShift{{ $shift->id }}"
                                   placeholder="Cari Nama / NIP Guru..." style="min-width: 0;" autocomplete="off">
                            <div class="d-flex align-items-center gap-2 ms-sm-auto flex-shrink-0">
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-2" id="badgeCounterShift{{ $shift->id }}">{{ count($selectedUsers) }} dipilih</span>
                                <a href="#" class="small fw-semibold text-primary text-decoration-none guru-select-all" data-panel="#guruGridListShift{{ $shift->id }}">Pilih Semua</a>
                                <span class="text-muted small">·</span>
                                <a href="#" class="small fw-semibold text-muted text-decoration-none guru-clear" data-panel="#guruGridListShift{{ $shift->id }}">Bersihkan</a>
                            </div>
                        </div>
                        <div class="text-muted small mb-2">Tambahkan lebih dari kuota jika diperlukan.</div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" id="guruGridListShift{{ $shift->id }}" style="max-height: 480px; overflow-y: auto; scrollbar-width: thin;">
                            @foreach($guruList as $guru)
                                @php
                                    $isChecked = in_array($guru->id, $selectedUsers);
                                @endphp
                                <div class="guru-item-col" data-name="{{ strtolower($guru->nama) }}" data-nip="{{ strtolower($guru->nip ?? '') }}">
                                    <label class="guru-checkbox-card d-flex align-items-center gap-3 {{ $isChecked ? 'selected' : '' }}">
                                        <input class="form-check-input shift-checkbox" type="checkbox" name="shift_users[{{ $shift->id }}][]" value="{{ $guru->id }}" data-shift-id="{{ $shift->id }}" data-quota="{{ $shift->maksimal_petugas }}" data-sesi="{{ $sesi }}" {{ $isChecked ? 'checked' : '' }}>
                                        <span class="rounded-circle bg-primary-subtle text-primary fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; font-size: 0.85rem;">{{ strtoupper(substr($guru->nama, 0, 2)) }}</span>
                                        <span class="overflow-hidden"><span class="d-block fw-bold text-dark text-truncate" style="font-size: 0.88rem;">{{ $guru->nama }}</span><span class="d-block text-muted text-truncate" style="font-size: 0.75rem;">NIP: {{ $guru->nip ?? '-' }}</span></span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        @endif
        </div>

        {{-- Footer Actions --}}
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mt-6 pb-2">
            <a href="{{ route('kurikulum.jadwal-piket.index') }}" class="btn btn-light rounded-3 px-4">
                Batal
            </a>
            <button type="submit" class="btn btn-primary rounded-3 px-4 fw-shadow-sm" style="font-size: 0.9rem;">
                <i class="bi bi-check-lg me-1"></i> Simpan Penugasan Piket
            </button>
        </div>
    </form>

</div>
@endsection

@push('scripts')
<script>
    // ===== Mutual Exclusive Shift Pagi & Siang (format SK / legacy) =====
    // 1. Koordinator Piket Pagi/Siang tidak boleh merangkap Petugas Piket:
    //    jika Guru X dipilih sebagai Koordinator, checkbox X di Petugas Pagi
    //    DAN Siang dikunci (disabled + styling) dan otomatis di-uncheck.
    //    Mengganti/mengosongkan Koordinator membuka kembali pilihan X.
    // 2. Guru yang dicentang di "Petugas Pagi" otomatis dikunci di "Petugas
    //    Siang", dan sebaliknya. Membatalkan centang akan membuka kembali.
    (function () {
        var pagiGrid = document.getElementById('guruGridListPagi');
        var siangGrid = document.getElementById('guruGridListSiang');
        if (!pagiGrid || !siangGrid) return;

        // ID guru yang sedang terpilih sebagai Koordinator Piket Pagi/Siang.
        function koordinatorTerpilih() {
            var ids = [];
            document.querySelectorAll('select[name="koordinator_pagi_user_id"], select[name="koordinator_siang_user_id"]').forEach(function (sel) {
                if (sel.value) ids.push(sel.value);
            });
            return new Set(ids);
        }

        // Guru dikunci bila: (a) menjadi Koordinator Piket Pagi/Siang, atau
        // (b) sudah dicentang sebagai petugas di shift sebelah.
        // Mengganti/mengosongkan pilihan Koordinator otomatis membuka kembali
        // status checkbox guru tersebut di daftar Petugas.
        function syncExclusive(grid, otherGrid) {
            var koordSet = koordinatorTerpilih();
            var otherSelected = new Set();
            otherGrid.querySelectorAll('.guru-checkbox:checked').forEach(function (cb) {
                if (!cb.disabled) otherSelected.add(cb.value);
            });

            grid.querySelectorAll('.guru-checkbox').forEach(function (cb) {
                var isLocked = koordSet.has(cb.value) || otherSelected.has(cb.value);
                cb.disabled = isLocked;
                var card = cb.closest('.guru-checkbox-card');
                if (card) card.classList.toggle('is-locked', isLocked);
                // Uncheck otomatis bila guru jadi koordinator padahal dicentang.
                if (isLocked && cb.checked) {
                    cb.checked = false;
                    if (card) card.classList.remove('selected');
                }
            });
        }

        function syncAll() {
            syncExclusive(pagiGrid, siangGrid);
            syncExclusive(siangGrid, pagiGrid);
            if (typeof updateCounterPagi === 'function') updateCounterPagi();
            if (typeof updateCounterSiang === 'function') updateCounterSiang();
        }

        pagiGrid.addEventListener('change', syncAll);
        siangGrid.addEventListener('change', syncAll);
        document.querySelectorAll('select[name="koordinator_pagi_user_id"], select[name="koordinator_siang_user_id"]').forEach(function (sel) {
            sel.addEventListener('change', syncAll);
        });
        syncAll(); // sinkronisasi awal (mis. re-render saat ada error validasi)
    })();

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

    // ===== Mutual Exclusive antar sesi Pagi & Siang (format shift dinamis) =====
    // Satu guru tidak boleh dipilih di panel Pagi DAN panel Siang sekaligus.
    // Checkbox guru yang sama di shift sebelah dikunci (disabled + styling).
    (function () {
        var pagi = [], siang = [];
        document.querySelectorAll('.shift-checkbox[data-sesi]').forEach(function (cb) {
            if (cb.dataset.sesi === 'pagi') pagi.push(cb);
            else if (cb.dataset.sesi === 'siang') siang.push(cb);
        });
        if (!pagi.length && !siang.length) return;

        var koordSelects = Array.prototype.slice.call(document.querySelectorAll(
            'select[name="koordinator_pagi_user_id"], select[name="koordinator_siang_user_id"]'
        ));

        // Perbarui badge counter shift (tanpa memicu confirm kuota).
        function perbaruiBadge(cb) {
            var shiftId = cb.dataset.shiftId;
            var badge = document.getElementById('badgeCounterShift' + shiftId);
            if (!badge) return;
            var selected = document.querySelectorAll('.shift-checkbox[data-shift-id="' + shiftId + '"]:checked').length;
            badge.textContent = selected + ' dipilih';
        }

        function kunciCheckbox(cb, isLocked) {
            cb.disabled = isLocked;
            var card = cb.closest('.guru-checkbox-card');
            if (card) card.classList.toggle('is-locked', isLocked);
            // Uncheck otomatis bila guru jadi koordinator padahal dicentang.
            if (isLocked && cb.checked) {
                cb.checked = false;
                if (card) card.classList.remove('selected');
                perbaruiBadge(cb);
            }
        }

        function syncPanel() {
            var koordSet = new Set();
            koordSelects.forEach(function (sel) { if (sel.value) koordSet.add(sel.value); });

            // Kumpulkan pilihan petugas (sebelum kunci dibuka kembali).
            var pagiSel = new Set();
            pagi.forEach(function (cb) { if (cb.checked && !cb.disabled) pagiSel.add(cb.value); });
            var siangSel = new Set();
            siang.forEach(function (cb) { if (cb.checked && !cb.disabled) siangSel.add(cb.value); });

            // Buka semua kunci, lalu kunci ulang sesuai aturan.
            var all = pagi.concat(siang);
            all.forEach(function (cb) { kunciCheckbox(cb, false); });

            // a) Koordinator Piket Pagi/Siang tidak boleh jadi petugas biasa.
            all.forEach(function (cb) { if (koordSet.has(cb.value)) kunciCheckbox(cb, true); });
            // b) Petugas Pagi <-> Petugas Siang saling mengunci.
            pagi.forEach(function (cb) { if (siangSel.has(cb.value)) kunciCheckbox(cb, true); });
            siang.forEach(function (cb) { if (pagiSel.has(cb.value)) kunciCheckbox(cb, true); });
        }

        pagi.concat(siang).forEach(function (cb) {
            cb.addEventListener('change', syncPanel);
        });
        koordSelects.forEach(function (sel) {
            sel.addEventListener('change', syncPanel);
        });
        syncPanel(); // sinkronisasi awal (re-render setelah error validasi)
    })();

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

    // ===== Search & Quick Selection Guru (berlaku untuk semua panel: Pagi/Siang legacy & shift dinamis) =====
    // Cari kartu guru berdasarkan Nama atau NIP secara real-time (menyembunyikan kartu
    // yang tidak cocok), plus tombol "Pilih Semua" / "Bersihkan Pilihan" di samping badge.
    // Hanya kartu yang terlihat (hasil filter) yang ikut "Pilih Semua"; kartu terkunci
    // (koordinator / sudah dipilih di shift lain) selalu dilewati.
    function setPanelChecks(panel, check) {
        panel.querySelectorAll('.guru-item-col').forEach(function (col) {
            // "Pilih Semua" hanya menyentuh kartu yang terlihat oleh filter aktif;
            // "Bersihkan Pilihan" selalu membersihkan seluruh panel sekalipun ada filter.
            if (check && col.style.display === 'none') return;
            var cb = col.querySelector('input[type="checkbox"]');
            if (!cb || cb.disabled) return;
            if (cb.checked !== check) {
                cb.checked = check;
                cb.dispatchEvent(new Event('change', { bubbles: true }));
            }
            var card = cb.closest('.guru-checkbox-card');
            if (card) card.classList.toggle('selected', check);
        });
    }

    function bindGuruPanelSearch(input) {
        var panel = document.querySelector(input.dataset.panel);
        if (!panel) return;
        input.addEventListener('input', function () {
            var q = input.value.trim().toLowerCase();
            panel.querySelectorAll('.guru-item-col').forEach(function (col) {
                var name = (col.dataset.name || '').toLowerCase();
                var nip = (col.dataset.nip || '').toLowerCase();
                col.style.display = (!q || name.indexOf(q) !== -1 || nip.indexOf(q) !== -1) ? '' : 'none';
            });
        });
    }

    document.querySelectorAll('.guru-search-input').forEach(bindGuruPanelSearch);

    document.querySelectorAll('.guru-select-all').forEach(function (link) {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            var panel = document.querySelector(link.dataset.panel);
            if (panel) setPanelChecks(panel, true);
        });
    });

    document.querySelectorAll('.guru-clear').forEach(function (link) {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            var panel = document.querySelector(link.dataset.panel);
            if (panel) setPanelChecks(panel, false);
        });
    });

    // Inisialisasi counter saat load
    document.addEventListener('DOMContentLoaded', function() {
        updateCounterPagi();
        updateCounterSiang();
    });
</script>
@endpush