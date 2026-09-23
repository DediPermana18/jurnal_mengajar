{{-- ============================================================ --}}
{{-- PARTIAL: Hasil filter Data Master Guru (di-update via AJAX)   --}}
{{-- Dipakai oleh admin.guru.index (render awal) & GuruController  --}}
{{-- saat request AJAX (return JSON html).                         --}}
{{-- ============================================================ --}}

{{-- Indikator Filter Aktif & Tombol Reset --}}
@if(request()->hasAny(['search', 'status', 'wali_kelas']) && (request('search') || (request('status') && request('status') !== 'Semua Status') || (request('wali_kelas') && request('wali_kelas') !== 'Semua')))
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div class="d-flex flex-wrap align-items-center gap-1.5" style="font-size: 0.8rem; color: #64748b;">
            <span class="fw-semibold text-dark"><i class="bi bi-funnel-fill text-primary me-1"></i>Filter Aktif:</span>
            @if(request('search'))
                <span class="badge bg-light text-dark border px-2 py-1">Pencarian: "{{ request('search') }}"</span>
            @endif
            @if(request('status') && request('status') !== 'Semua Status')
                <span class="badge bg-light text-success border border-success-subtle px-2 py-1">Status: {{ request('status') }}</span>
            @endif
            @if(request('wali_kelas') && request('wali_kelas') !== 'Semua')
                @php
                    $waliLabel = request('wali_kelas');
                    if ($waliLabel === 'Ya') $waliLabel = 'Wali Kelas';
                    elseif ($waliLabel === 'Tidak') $waliLabel = 'Bukan Wali Kelas';
                    elseif (str_starts_with($waliLabel, 'kelas_')) {
                        $kId = (int) str_replace('kelas_', '', $waliLabel);
                        $kObj = $daftarKelas->firstWhere('id', $kId);
                        $waliLabel = 'Wali: ' . ($kObj ? $kObj->tingkat . ' ' . $kObj->nama_kelas : $waliLabel);
                    }
                @endphp
                <span class="badge bg-light text-primary border border-primary-subtle px-2 py-1">{{ $waliLabel }}</span>
            @endif
        </div>
        <a href="{{ route('guru.index') }}" data-reset-filter class="btn btn-sm btn-outline-secondary rounded-2 px-2 py-1 text-decoration-none d-inline-flex align-items-center gap-1" style="font-size: 0.78rem;">
            <i class="bi bi-arrow-counterclockwise"></i>
            <span>Reset Filter</span>
        </a>
    </div>
@endif

{{-- ====================================================== --}}
{{-- TABEL DATA GURU (hanya tampil di layar ≥ md / tablet landscape) --}}
{{-- Catatan: wrapper ini adalah mekanisme utama sembunyi/tampil (`hidden md:block`).
     Class `md:table` pada <table> menjaga display:table saat ≥md. `hidden` tidak
     ditempelkan ke <table> karena utility Tailwind `.table{display:table}` (bentrok
     dengan class Bootstrap `table`) akan menimpanya. --}}
{{-- ====================================================== --}}
<div class="table-card-custom mb-4 hidden md:block">
    <div class="table-responsive w-full overflow-x-auto">
        <table class="table table-custom align-middle min-w-full md:table">
            <thead><tr><th class="whitespace-nowrap" style="width: 28%;">GURU</th><th style="width: 28%;">MATA PELAJARAN</th><th style="width: 18%;">WALI KELAS</th><th class="whitespace-nowrap" style="width: 10%;">STATUS</th><th class="text-end whitespace-nowrap" style="width: 16%;">AKSI</th></tr></thead>
            <tbody>
                @forelse($dataGuru as $guru)
                    @php
                        $words = explode(' ', trim($guru->nama));
                        $initials = strtoupper(substr($words[0], 0, 1));
                        $initials .= count($words) > 1 ? strtoupper(substr(end($words), 0, 1)) : strtoupper(substr($words[0], 1, 1));
                        $mapelDiampu = $guru->mataPelajaran->unique('id');
                        $namaKelasWali = $guru->waliKelas->isEmpty() ? $guru->kelas?->nama_kelas : $guru->waliKelas->pluck('nama_kelas')->join(', ');
                    @endphp
                    <tr>
                        {{-- 1. Guru Info (Nama & NIP) --}}
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-secondary-subtle text-secondary fw-bold d-flex align-items-center justify-content-center shrink-0" style="width: 44px; height: 44px;">
                                    {{ $initials }}
                                </div>
                                <div>
                                    <div class="fw-bold text-dark">{{ $guru->nama }}</div>
                                    <div class="text-muted small">NIP: {{ $guru->nip ?: '-' }}</div>
                                    <div class="text-muted small">No. HP: {{ $guru->no_hp ?: '-' }}</div>
                                </div>
                            </div>
                        </td>

                        {{-- 2. Mata Pelajaran Pengampu (dari Plotting Jadwal) --}}
                        <td>
                            @if($mapelDiampu->isNotEmpty())
                                @foreach($mapelDiampu as $mapel)
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 rounded-2 me-1 mb-1" style="font-size: 0.78rem; font-weight: 600;">
                                        <i class="bi bi-journal-bookmark me-1"></i>{{ $mapel->nama_mapel }}
                                    </span>
                                @endforeach
                            @else
                                <span class="badge bg-light text-muted border px-2 py-1 rounded-pill" style="font-size: 0.78rem;">
                                    <i class="bi bi-dash-circle me-1"></i>Belum di-plot
                                </span>
                            @endif
                        </td>
                        <td>
                            @if($namaKelasWali)
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-2" style="font-size: 0.78rem; font-weight: 600;">
                                    <i class="bi bi-person-check me-1"></i>Wali Kelas {{ $namaKelasWali }}
                                </span>
                            @else
                                <span class="badge bg-light text-muted border px-2 py-1 rounded-pill" style="font-size: 0.78rem;">
                                    <i class="bi bi-dash-circle me-1"></i>-
                                </span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap"><span class="badge {{ $guru->is_active ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning-emphasis' }} rounded-pill px-3 py-2">{{ $guru->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                        <td class="text-end whitespace-nowrap">
                            @if(in_array(auth()->user()->role ?? '', ['admin_tu', 'admin', 'super_admin']) || (auth()->user() && auth()->user()->isTestingUser()))
                                <div class="flex items-center justify-center gap-2 whitespace-nowrap">
                                <a href="{{ route('admin.guru.edit', $guru->id) }}" class="btn btn-sm btn-outline-warning rounded-3" title="Edit guru"><i class="bi bi-pencil-square"></i></a>
                                <form action="{{ route('guru.reset-password', $guru->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Reset password guru ini ke password default?')">@csrf<button type="submit" class="btn btn-sm btn-outline-info rounded-3" title="Reset password"><i class="bi bi-key"></i></button></form>
                                @if(!$guru->is_active)
                                    <form action="{{ route('guru.approve', $guru->id) }}" method="POST" class="d-inline">@csrf<button type="submit" class="btn btn-sm btn-outline-success rounded-3" title="Aktifkan guru"><i class="bi bi-check-circle"></i></button></form>
                                @else
                                    <form action="{{ route('guru.toggle-status', $guru->id) }}" method="POST" class="d-inline">@csrf<button type="submit" class="btn btn-sm btn-outline-secondary rounded-3" title="Nonaktifkan guru"><i class="bi bi-slash-circle"></i></button></form>
                                @endif
                                <form action="{{ route('guru.destroy', $guru->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus data guru ini?')">@csrf @method('DELETE')<button type="submit" class="btn btn-sm btn-outline-danger rounded-3" title="Hapus guru"><i class="bi bi-trash"></i></button></form>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-person-badge fs-1 d-block mb-2"></i>Tidak ada data guru yang sesuai.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ====================================================== --}}
{{-- CARD STACK DATA GURU (hanya tampil di layar < md / HP) --}}
{{-- ====================================================== --}}
<div class="block md:hidden mb-4">
    @forelse($dataGuru as $guru)
        @php
            $words = explode(' ', trim($guru->nama));
            $initials = strtoupper(substr($words[0], 0, 1));
            $initials .= count($words) > 1 ? strtoupper(substr(end($words), 0, 1)) : strtoupper(substr($words[0], 1, 1));
            $mapelDiampu = $guru->mataPelajaran->unique('id');
            $namaKelasWali = $guru->waliKelas->isEmpty() ? $guru->kelas?->nama_kelas : $guru->waliKelas->pluck('nama_kelas')->join(', ');
        @endphp

        <div class="card border-0 rounded-4 shadow-sm mb-3 overflow-hidden">
            <div class="card-body p-3 p-sm-4">
                {{-- Header Card: Avatar, Nama, NIP (plus status) --}}
                <div class="d-flex align-items-start gap-3 pb-3 mb-3 border-bottom">
                    <div class="rounded-circle bg-secondary-subtle text-secondary fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                        {{ $initials }}
                    </div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-bold text-dark" style="font-size: 0.95rem; line-height: 1.35;">{{ $guru->nama }}</div>
                        <div class="text-muted small mt-0.5">NIP: {{ $guru->nip ?: '-' }}</div>
                    </div>
                    <span class="badge {{ $guru->is_active ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning-emphasis' }} rounded-pill px-2 py-1 flex-shrink-0" style="font-size: 0.72rem;">{{ $guru->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                </div>

                {{-- Body Card: Mata Pelajaran & No. HP secara vertikal --}}
                <div class="d-flex flex-column gap-3">
                    <div>
                        <div class="text-uppercase small fw-semibold text-muted" style="font-size: 0.7rem; letter-spacing: 0.06em;">Mata Pelajaran</div>
                        <div class="mt-1 d-flex flex-wrap align-items-center gap-1">
                            @if($mapelDiampu->isNotEmpty())
                                @foreach($mapelDiampu as $mapel)
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 rounded-2" style="font-size: 0.78rem; font-weight: 600;">
                                        <i class="bi bi-journal-bookmark me-1"></i>{{ $mapel->nama_mapel }}
                                    </span>
                                @endforeach
                            @else
                                <span class="badge bg-light text-muted border px-2 py-1 rounded-pill" style="font-size: 0.78rem;">
                                    <i class="bi bi-dash-circle me-1"></i>Belum di-plot
                                </span>
                            @endif
                        </div>
                    </div>

                    @if($namaKelasWali)
                        <div>
                            <div class="text-uppercase small fw-semibold text-muted" style="font-size: 0.7rem; letter-spacing: 0.06em;">Wali Kelas</div>
                            <div class="mt-1">
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-2" style="font-size: 0.78rem; font-weight: 600;">
                                    <i class="bi bi-person-check me-1"></i>{{ $namaKelasWali }}
                                </span>
                            </div>
                        </div>
                    @endif

                    <div>
                        <div class="text-uppercase small fw-semibold text-muted" style="font-size: 0.7rem; letter-spacing: 0.06em;">No. HP</div>
                        <div class="mt-1 d-flex align-items-center gap-2">
                            <i class="bi bi-telephone text-muted" style="font-size: 0.85rem;"></i>
                            <span class="text-dark fw-medium" style="font-size: 0.9rem;">{{ $guru->no_hp ?: '-' }}</span>
                        </div>
                    </div>
                </div>

                {{-- Footer Card / Aksi --}}
                @if(in_array(auth()->user()->role ?? '', ['admin_tu', 'admin', 'super_admin']) || (auth()->user() && auth()->user()->isTestingUser()))
                    <div class="d-flex flex-wrap gap-2 pt-3 mt-3 border-top">
                        <a href="{{ route('admin.guru.edit', $guru->id) }}" class="btn btn-sm btn-outline-warning rounded-3 flex-fill" title="Edit guru"><i class="bi bi-pencil-square me-1"></i> Edit</a>
                        <form action="{{ route('guru.reset-password', $guru->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Reset password guru ini ke password default?')">@csrf<button type="submit" class="btn btn-sm btn-outline-info rounded-3" title="Reset password"><i class="bi bi-key"></i></button></form>
                        @if(!$guru->is_active)
                            <form action="{{ route('guru.approve', $guru->id) }}" method="POST" class="d-inline">@csrf<button type="submit" class="btn btn-sm btn-outline-success rounded-3" title="Aktifkan guru"><i class="bi bi-check-circle"></i></button></form>
                        @else
                            <form action="{{ route('guru.toggle-status', $guru->id) }}" method="POST" class="d-inline">@csrf<button type="submit" class="btn btn-sm btn-outline-secondary rounded-3" title="Nonaktifkan guru"><i class="bi bi-slash-circle"></i></button></form>
                        @endif
                        <form action="{{ route('guru.destroy', $guru->id) }}" method="POST" class="d-inline flex-fill" onsubmit="return confirm('Hapus data guru ini?')">@csrf @method('DELETE')<button type="submit" class="btn btn-sm btn-outline-danger rounded-3 w-100" title="Hapus guru"><i class="bi bi-trash me-1"></i> Hapus</button></form>
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div class="table-card-custom text-center py-5">
            <i class="bi bi-person-badge fs-1 d-block mb-2 text-muted"></i>
            <div class="text-muted">Tidak ada data guru yang sesuai.</div>
        </div>
    @endforelse
</div>

{{-- ====================================================== --}}
{{-- FOOTER: Info & Pagination                               --}}
{{-- ====================================================== --}}
@if($dataGuru->total() > 0)
    <div class="custom-pagination-wrapper">
        {{-- Info jumlah di sebelah KIRI --}}
        <div class="pagination-info-text">
            Menampilkan
            <strong>{{ $dataGuru->firstItem() }} - {{ $dataGuru->lastItem() }}</strong>
            dari
            <strong>{{ number_format($dataGuru->total()) }}</strong>
            Guru
            @if(request()->filled('search') || request()->filled('status') || request()->filled('wali_kelas'))
                <span class="text-muted ms-1">(difilter)</span>
            @endif
        </div>

        {{-- Tombol Navigasi Pagination di sebelah KANAN --}}
        <div class="pagination-controls">
            {{-- Tombol Prev --}}
            @if ($dataGuru->onFirstPage())
                <span class="pagination-btn disabled" aria-disabled="true">
                    <svg class="pagination-svg-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                    <span>Prev</span>
                </span>
            @else
                <a href="{{ $dataGuru->appends(request()->query())->previousPageUrl() }}" class="pagination-btn">
                    <svg class="pagination-svg-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                    <span>Prev</span>
                </a>
            @endif

            {{-- Counter Halaman (Badge) --}}
            <span class="pagination-badge">
                {{ $dataGuru->currentPage() }} / {{ $dataGuru->lastPage() }}
            </span>

            {{-- Tombol Next --}}
            @if ($dataGuru->hasMorePages())
                <a href="{{ $dataGuru->appends(request()->query())->nextPageUrl() }}" class="pagination-btn">
                    <span>Next</span>
                    <svg class="pagination-svg-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </a>
            @else
                <span class="pagination-btn disabled" aria-disabled="true">
                    <span>Next</span>
                    <svg class="pagination-svg-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </span>
            @endif
        </div>
    </div>
@endif