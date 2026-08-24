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
                    Pilih hari dan centang guru pengajar yang ditugaskan sebagai Petugas Piket.
                </p>
            </div>
        </div>
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
    <div class="card border-0 rounded-4 shadow-sm bg-white overflow-hidden">
        <div class="card-header bg-white border-0 pt-4 pb-3 px-4">
            <div class="d-flex align-items-center gap-2">
                <div class="rounded-2 d-flex align-items-center justify-content-center bg-primary-subtle text-primary"
                     style="width: 36px; height: 36px;">
                    <i class="bi bi-person-plus-fill fs-5"></i>
                </div>
                <h5 class="fw-bold mb-0 text-dark">Form Penugasan Petugas Piket</h5>
            </div>
        </div>

        <form action="{{ route('kurikulum.jadwal-piket.store') }}" method="POST" id="formPiket">
            @csrf

            <div class="card-body p-4 pt-2">
                {{-- 1. Pilihan Hari Piket --}}
                <div class="mb-4">
                    <label class="form-label fw-bold text-dark mb-2" style="font-size: 0.9rem;">
                        <i class="bi bi-calendar-event text-primary me-1"></i> Pilih Hari Piket <span class="text-danger">*</span>
                    </label>
                    <div class="d-flex gap-2 flex-wrap">
                        @foreach($hariList as $h)
                            <input type="radio" class="btn-check" name="hari" id="hari_{{ $h }}"
                                   value="{{ $h }}" {{ old('hari', $selectedHari) === $h ? 'checked' : '' }}
                                   onchange="window.location.href = '{{ route('kurikulum.jadwal-piket.create') }}?hari=' + this.value">
                            <label class="btn btn-outline-primary rounded-3 px-3 py-2 fw-semibold" for="hari_{{ $h }}" style="font-size: 0.875rem;">
                                {{ $h }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <hr class="my-4" style="border-color: #f1f5f9;">

                {{-- 2. Pilihan Guru (Grid with Search & Checkbox List) --}}
                <div class="mb-3">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                        <div>
                            <label class="form-label fw-bold text-dark mb-0" style="font-size: 0.9rem;">
                                <i class="bi bi-people-fill text-primary me-1"></i> Centang Guru Piket (Hari {{ $selectedHari }}) <span class="text-danger">*</span>
                            </label>
                            <div class="text-muted" style="font-size: 0.78rem;">
                                Klik atau centang kotak guru yang akan bertugas piket pada hari {{ $selectedHari }}.
                            </div>
                        </div>

                        {{-- Control Top: Counter & Select All --}}
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-2 fw-semibold" style="font-size: 0.8rem;" id="badgeCounter">
                                0 Guru Dipilih
                            </span>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-3" onclick="toggleSelectAll(true)">
                                <i class="bi bi-check-all me-1"></i>Pilih Semua
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-3" onclick="toggleSelectAll(false)">
                                <i class="bi bi-x me-1"></i>Batal Semua
                            </button>
                        </div>
                    </div>

                    {{-- Search Input Guru --}}
                    <div class="mb-3">
                        <div class="position-relative">
                            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted" style="font-size: 0.9rem;"></i>
                            <input type="text" id="searchGuruInput" class="form-control rounded-3 ps-5"
                                   placeholder="Ketik nama atau NIP guru untuk menyaring..."
                                   onkeyup="filterGuruList()" style="font-size: 0.875rem;">
                        </div>
                    </div>

                    {{-- Grid List Guru --}}
                    <div class="row g-3" id="guruGridList" style="max-height: 480px; overflow-y: auto; scrollbar-width: thin;">
                        @foreach($guruList as $guru)
                            @php
                                $isChecked = in_array($guru->id, old('guru_ids', $assignedGuruIds ?? []));
                            @endphp
                            <div class="col-12 col-md-6 col-lg-4 guru-item-col"
                                 data-name="{{ strtolower($guru->nama) }}"
                                 data-nip="{{ strtolower($guru->nip ?? '') }}">
                                <div class="guru-checkbox-card d-flex align-items-center gap-3 {{ $isChecked ? 'selected' : '' }}"
                                     onclick="toggleCardCheck(this, event)">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input guru-checkbox" type="checkbox"
                                               name="guru_ids[]" value="{{ $guru->id }}"
                                               id="guru_cb_{{ $guru->id }}"
                                               {{ $isChecked ? 'checked' : '' }}
                                               onchange="updateCounter()">
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

            {{-- Card Footer Actions --}}
            <div class="card-footer bg-white border-0 p-4 pt-2 d-flex align-items-center justify-content-between flex-wrap gap-3">
                <a href="{{ route('kurikulum.jadwal-piket.index') }}" class="btn btn-light rounded-3 px-4">
                    Batal
                </a>
                <button type="submit" class="btn btn-primary rounded-3 px-4 fw-semibold shadow-sm" style="font-size: 0.9rem;">
                    <i class="bi bi-check-lg me-1"></i> Simpan Penugasan Piket
                </button>
            </div>
        </form>
    </div>

</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        updateCounter();
    });

    function toggleCardCheck(card, event) {
        // Avoid double trigger if direct click on input
        if (event.target.tagName.toLowerCase() === 'input') {
            const cb = event.target;
            if (cb.checked) {
                card.classList.add('selected');
            } else {
                card.classList.remove('selected');
            }
            updateCounter();
            return;
        }

        const cb = card.querySelector('.guru-checkbox');
        if (cb) {
            cb.checked = !cb.checked;
            if (cb.checked) {
                card.classList.add('selected');
            } else {
                card.classList.remove('selected');
            }
            updateCounter();
        }
    }

    function updateCounter() {
        const checkboxes = document.querySelectorAll('.guru-checkbox:checked');
        const badge = document.getElementById('badgeCounter');
        if (badge) {
            badge.textContent = checkboxes.length + ' Guru Dipilih';
        }

        // Sync card classes
        document.querySelectorAll('.guru-checkbox').forEach(cb => {
            const card = cb.closest('.guru-checkbox-card');
            if (card) {
                if (cb.checked) {
                    card.classList.add('selected');
                } else {
                    card.classList.remove('selected');
                }
            }
        });
    }

    function filterGuruList() {
        const input = document.getElementById('searchGuruInput').value.toLowerCase().trim();
        const items = document.querySelectorAll('.guru-item-col');

        items.forEach(item => {
            const name = item.getAttribute('data-name') || '';
            const nip  = item.getAttribute('data-nip') || '';
            if (name.includes(input) || nip.includes(input)) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });
    }

    function toggleSelectAll(select) {
        const checkboxes = document.querySelectorAll('.guru-checkbox');
        checkboxes.forEach(cb => {
            // Only select visible items if search filter is active
            const col = cb.closest('.guru-item-col');
            if (col && col.style.display !== 'none') {
                cb.checked = select;
            }
        });
        updateCounter();
    }
</script>
@endpush
