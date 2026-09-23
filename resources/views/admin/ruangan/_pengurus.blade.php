{{-- ============================================================ --}}
{{-- PARTIAL: Picker Pengurus Ruangan (search + badge + grid card)  --}}
{{-- Dipakai di halaman dedicated admin.ruangan.create & .edit.     --}}
{{-- Variabel yang dibutuhkan:                                     --}}
{{--   $guruList         : koleksi User (admin/guru)                --}}
{{--   $pengurusSelected : array id pengurus yang sudah terpilih    --}}
{{-- ============================================================ --}}

@push('styles')
<style>
    /* Kartu pilihan Pengurus Ruangan: touch-friendly, tanpa Ctrl/Cmd. */
    .pengurus-card {
        cursor: pointer;
        user-select: none;
        transition: all 0.15s ease;
        background-color: #ffffff;
    }

    .pengurus-card:hover {
        border-color: #93c5fd;
        background-color: #f8fafc;
    }

    .pengurus-card.selected {
        border-color: #2563eb;
        background-color: #eff6ff;
        box-shadow: 0 0 0 1px #2563eb;
    }

    .pengurus-card .form-check-input {
        cursor: pointer;
    }

    /* Hasil filter pencarian: kartu non-cocok disembunyikan.
       Penting: kartu memakai .d-flex Bootstrap (display: flex !important),
       jadi inline style biasa / atribut hidden tidak bisa menimpanya.
       Spesifisitas ganda (.pengurus-card.is-hidden) + !important memastikan
       aturan ini yang menang, dan saat class dilepas .d-flex aktif kembali. */
    .pengurus-card.is-hidden {
        display: none !important;
    }

    /* Sembunyikan row badge saat belum ada pilihan */
    .pengurus-badges:empty {
        margin: 0;
    }
</style>
@endpush

<div class="pengurus-picker">
    <input type="search" class="form-control form-control-sm rounded-3 bg-white pengurus-search mb-2"
           placeholder="Cari nama atau NIP pengurus..." autocomplete="off"
           onkeydown="if (event.key === 'Enter') event.preventDefault();">
    <div class="d-flex flex-wrap gap-1 my-2 pengurus-badges" aria-live="polite"></div>

    <div class="pengurus-grid grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
        @foreach($guruList as $guru)
            <label class="pengurus-card d-flex align-items-center gap-2 rounded-3 border p-2 pe-3 {{ in_array($guru->id, $pengurusSelected) ? 'selected' : '' }}"
                   data-badge="{{ $guru->nama }}"
                   data-search="{{ strtolower(trim($guru->nama . ' ' . ($guru->nip ?? '') . ' ' . $guru->role_label)) }}">
                <input class="form-check-input pengurus-checkbox flex-shrink-0 m-0" type="checkbox"
                       name="pengurus[]" value="{{ $guru->id }}"
                       {{ in_array($guru->id, $pengurusSelected) ? 'checked' : '' }}>
                <span class="rounded-circle bg-primary-subtle text-primary fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                      style="width: 34px; height: 34px; font-size: 0.75rem;">{{ strtoupper(substr($guru->nama, 0, 2)) }}</span>
                <span class="overflow-hidden">
                    <span class="d-block fw-semibold text-dark text-truncate" style="font-size: 0.85rem;" title="{{ $guru->nama }}">{{ $guru->nama }}</span>
                    <span class="d-block text-muted text-truncate" style="font-size: 0.72rem;">{{ $guru->role_label }}</span>
                </span>
            </label>
        @endforeach
    </div>
    <div class="form-text text-success"><i class="bi bi-check2-circle me-1"></i> Klik kartu untuk memilih — pengurus terpilih tampil sebagai badge dan bisa dihapus dengan tombol &times;.</div>
    @error('pengurus') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
</div>

@push('scripts')
<script>
    // ===== Picker Pengurus Ruangan (search + badge) — vanilla JS =====
    // Kartu bisa disentuh langsung di HP (tanpa Ctrl/Cmd); badge terpilih
    // bisa dihapus sekali klik via tombol ×.
    function refreshPengurusBadges(picker) {
        const badges = picker.querySelector('.pengurus-badges');
        if (!badges) return;
        badges.innerHTML = '';

        picker.querySelectorAll('.pengurus-checkbox:checked').forEach(function (cb) {
            const card = cb.closest('.pengurus-card');
            const nama = (card && card.dataset.badge) ? card.dataset.badge : ('#' + cb.value);

            const badge = document.createElement('span');
            badge.className = 'badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill d-inline-flex align-items-center gap-2 px-2 py-1';
            badge.style.fontSize = '0.78rem';

            const txt = document.createElement('span');
            txt.textContent = nama;
            badge.appendChild(txt);

            const x = document.createElement('button');
            x.type = 'button';
            x.className = 'rounded-circle border-0 d-inline-flex align-items-center justify-content-center bg-primary text-white flex-shrink-0';
            x.style.cssText = 'width: 18px; height: 18px; font-size: 0.85rem; line-height: 1; padding: 0;';
            x.setAttribute('aria-label', 'Hapus ' + nama);
            x.textContent = '\u00d7';
            x.addEventListener('click', function () {
                cb.checked = false;
                cb.dispatchEvent(new Event('change', { bubbles: true }));
            });
            badge.appendChild(x);
            badges.appendChild(badge);
        });
    }

    function initPengurusPicker(picker) {
        const grid = picker.querySelector('.pengurus-grid');
        const search = picker.querySelector('.pengurus-search');
        const badges = picker.querySelector('.pengurus-badges');
        if (!grid || !search || !badges) return;

        grid.addEventListener('change', function (e) {
            const cb = e.target.closest('.pengurus-checkbox');
            if (!cb) return;
            const card = cb.closest('.pengurus-card');
            if (card) card.classList.toggle('selected', cb.checked);
            refreshPengurusBadges(picker);
        });

        // Real-time filter (on input/keyup): tampilkan hanya card guru yang
        // nama/NIP-nya mengandung kata kunci (case-insensitive). Mengetik tidak
        // perlu Enter/reload; mengosongkan search menampilkan seluruh daftar.
        function applyFilter() {
            const q = search.value.trim().toLowerCase();
            grid.querySelectorAll('.pengurus-card').forEach(function (card) {
                const hay = (card.dataset.search || '').toLowerCase();
                const matched = !q || hay.indexOf(q) !== -1;
                card.classList.toggle('is-hidden', !matched);
            });
        }

        search.addEventListener('input', applyFilter);

        // Enter di search bar HANYA menjalankan filter nama guru,
        // TIDAK boleh memicu submit form utama (Simpan Perubahan / Simpan Ruangan).
        search.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                applyFilter();
            }
        });

        refreshPengurusBadges(picker);
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.pengurus-picker').forEach(initPengurusPicker);
    });
</script>
@endpush