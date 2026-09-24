{{-- ============================================================ --}}
{{-- PARTIAL: Hasil filter Data Master Kelas (di-update via AJAX) --}}
{{-- Dipakai oleh admin.kelas.index (render awal) & KelasController--}}
{{-- saat request AJAX (return JSON html).                        --}}
{{-- Berisi tabel + modal edit per baris agar tetap sinkron dengan --}}
{{-- hasil filter terbaru.                                        --}}
{{-- ============================================================ --}}

<!-- TABEL DATA MASTER KELAS -->
<div class="table-card-custom mb-4" style="overflow: visible;">
    <div class="hidden sm:block table-responsive w-full overflow-x-auto min-w-full" style="min-height: 280px; padding-bottom: 2rem;">
        <table class="table table-custom align-middle min-w-full" style="min-width: 900px;">
            <thead>
                <tr>
                    <th class="whitespace-nowrap" style="width: 25%;">NAMA KELAS</th>
                    <th class="whitespace-nowrap" style="width: 15%;">TINGKAT</th>
                    <th class="whitespace-nowrap" style="width: 25%;">JURUSAN</th>
                    <th style="width: 25%;">WALI KELAS</th>
                    <th class="whitespace-nowrap" style="width: 10%;">TOTAL SISWA</th>
                    @if(in_array(auth()->user()->role ?? '', ['admin_tu', 'admin', 'super_admin']) || (auth()->user() && auth()->user()->isTestingUser()))
                        <th class="text-end whitespace-nowrap" style="width: 10%;">AKSI</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($dataKelas as $kelas)
                    @php
                        // Warna badge tingkat
                        $tingkatColor = match($kelas->tingkat) {
                            'X'   => ['bg' => '#dcfce7', 'color' => '#166534', 'border' => '#bbf7d0'],
                            'XI'  => ['bg' => '#fef9c3', 'color' => '#854d0e', 'border' => '#fef08a'],
                            'XII' => ['bg' => '#dbeafe', 'color' => '#1d4ed8', 'border' => '#bfdbfe'],
                            default => ['bg' => '#f1f5f9', 'color' => '#475569', 'border' => '#e2e8f0']
                        };

                        // Inisial Wali Kelas jika ada
                        $waliInitial = '-';
                        if ($kelas->waliKelas) {
                            $words = explode(' ', trim($kelas->waliKelas->nama));
                            $waliInitial = strtoupper(substr($words[0], 0, 1));
                            if (count($words) > 1) {
                                $waliInitial .= strtoupper(substr(end($words), 0, 1));
                            }
                        }
                    @endphp
                    <tr>
                        <!-- Kolom NAMA KELAS -->
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge fw-bold px-3 py-2 rounded-3 shadow-none"
                                      style="background-color: {{ $tingkatColor['bg'] }}; color: {{ $tingkatColor['color'] }}; border: 1px solid {{ $tingkatColor['border'] }}; font-size: 0.9rem; letter-spacing: 0.01em;">
                                    {{ $kelas->nama_kelas }}
                                </span>
                            </div>
                            @if($kelas->shift)
                                <div class="mt-1">
                                    <span class="badge bg-dark-subtle text-dark border px-2 py-1 rounded-pill" style="font-size: 0.7rem;">
                                        <i class="bi bi-clock me-1"></i>{{ $kelas->shift->nama_shift }}
                                        @if(!$kelas->shift->is_active) <span class="text-warning">(Non-Aktif)</span> @endif
                                    </span>
                                </div>
                            @else
                                <div class="mt-1">
                                    <span class="badge bg-secondary-subtle text-secondary border px-2 py-1 rounded-pill" style="font-size: 0.7rem;">
                                        <i class="bi bi-globe2 me-1"></i>Global
                                    </span>
                                </div>
                            @endif
                        </td>

                        <!-- Kolom TINGKAT -->
                        <td>
                            <span class="badge bg-light text-dark border px-2 py-1 rounded-2 font-monospace" style="font-size: 0.8rem;">
                                {{ $kelas->tingkat }}
                            </span>
                        </td>

                        <!-- Kolom JURUSAN -->
                        <td>
                            @if($kelas->jurusan)
                                <div>
                                    <span class="badge bg-light text-secondary border px-2 py-1 font-monospace mb-1" style="font-size: 0.75rem;">
                                        {{ $kelas->jurusan->kode_jurusan }}
                                    </span>
                                    <div class="fw-semibold text-dark truncate max-w-[150px]" title="{{ $kelas->jurusan->nama_jurusan }}" style="font-size: 0.875rem;">
                                        {{ $kelas->jurusan->nama_jurusan }}
                                    </div>
                                </div>
                            @else
                                <span class="text-muted" style="font-size: 0.85rem;">-</span>
                            @endif
                        </td>

                        <!-- Kolom WALI KELAS (avatar & NIP diringkas agar rapi di layar kecil) -->
                        <td>
                            @if($kelas->waliKelas)
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle bg-secondary-subtle text-secondary fw-bold d-flex align-items-center justify-content-center"
                                         style="width: 32px; height: 32px; font-size: 0.75rem; flex-shrink: 0;">
                                        {{ $waliInitial }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="fw-bold text-dark truncate" title="{{ $kelas->waliKelas->nama }}" style="font-size: 0.9rem; max-width: 170px;">
                                            {{ $kelas->waliKelas->nama }}
                                        </div>
                                        <div class="text-muted truncate d-none d-md-block" title="NIP: {{ $kelas->waliKelas->nip ?? '-' }}" style="font-size: 0.78rem; max-width: 170px;">
                                            NIP: {{ $kelas->waliKelas->nip ?? '-' }}
                                        </div>
                                    </div>
                                </div>
                            @else
                                <span class="badge bg-light text-muted border px-2 py-1 rounded-pill" style="font-size: 0.78rem;">
                                    <i class="bi bi-person-x me-1"></i> Belum ada wali
                                </span>
                            @endif
                        </td>

                        <!-- Kolom TOTAL SISWA -->
                        <td>
                            <a href="{{ route('kelas.show', $kelas->id) }}" class="text-decoration-none">
                                <span class="badge bg-light text-primary border border-primary-subtle px-3 py-2 rounded-3 fw-bold" style="font-size: 0.85rem;">
                                    {{ $kelas->siswa_count ?? 0 }}
                                </span>
                            </a>
                        </td>

                        <!-- Kolom AKSI -->
                        @if(in_array(auth()->user()->role ?? '', ['admin_tu', 'admin', 'super_admin']) || (auth()->user() && auth()->user()->isTestingUser()))
                            <td class="text-end whitespace-nowrap">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light border rounded-3 dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="window" aria-expanded="false">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3 z-50" style="z-index: 1050;">
                                        <!-- Detail Kelas & Siswa -->
                                        <li>
                                            <a href="{{ route('kelas.show', $kelas->id) }}" class="dropdown-item py-2">
                                                <i class="bi bi-eye me-2 text-info"></i> Detail Kelas & Siswa
                                            </a>
                                        </li>

                                        <!-- Edit Kelas -->
                                        <li>
                                            <button type="button" class="dropdown-item py-2" data-bs-toggle="modal" data-bs-target="#modalEditKelas{{ $kelas->id }}">
                                                <i class="bi bi-pencil-square me-2 text-warning"></i> Edit Kelas
                                            </button>
                                        </li>

                                        <li><hr class="dropdown-divider"></li>

                                        <!-- Hapus Kelas -->
                                        <li>
                                            <form action="{{ route('kelas.destroy', $kelas->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data kelas {{ $kelas->nama_kelas }}?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item py-2 text-danger">
                                                    <i class="bi bi-trash me-2"></i> Hapus Kelas
                                                </button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ in_array(auth()->user()->role ?? '', ['admin_tu', 'admin', 'super_admin']) || (auth()->user() && auth()->user()->isTestingUser()) ? 6 : 5 }}" class="text-center py-5 text-muted">
                            <i class="bi bi-door-closed fs-1 d-block mb-2 text-secondary"></i>
                            Belum ada data kelas yang sesuai dengan kriteria pencarian/filter.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- ============ CARD VIEW (layar < sm, menggantikan tabel di mobile) ============ -->
    <div class="block sm:hidden">
        @forelse($dataKelas as $kelas)
            @php
                // Warna badge tingkat (sama dengan versi tabel)
                $tingkatColor = match($kelas->tingkat) {
                    'X'   => ['bg' => '#dcfce7', 'color' => '#166534', 'border' => '#bbf7d0'],
                    'XI'  => ['bg' => '#fef9c3', 'color' => '#854d0e', 'border' => '#fef08a'],
                    'XII' => ['bg' => '#dbeafe', 'color' => '#1d4ed8', 'border' => '#bfdbfe'],
                    default => ['bg' => '#f1f5f9', 'color' => '#475569', 'border' => '#e2e8f0']
                };

                // Inisial Wali Kelas jika ada
                $waliInitial = '-';
                if ($kelas->waliKelas) {
                    $words = explode(' ', trim($kelas->waliKelas->nama));
                    $waliInitial = strtoupper(substr($words[0], 0, 1));
                    if (count($words) > 1) {
                        $waliInitial .= strtoupper(substr(end($words), 0, 1));
                    }
                }
            @endphp
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-3">
                <!-- Header: Nama Kelas + Tingkat -->
                <div class="d-flex justify-content-between align-items-center gap-2 px-3 py-3"
                     style="background-color: {{ $tingkatColor['bg'] }}; border-bottom: 1px solid {{ $tingkatColor['border'] }};">
                    <span class="badge fw-bold px-3 py-2 rounded-3 shadow-none"
                          style="background-color: #fff; color: {{ $tingkatColor['color'] }}; border: 1px solid {{ $tingkatColor['border'] }}; font-size: 0.9rem; letter-spacing: 0.01em;">
                        {{ $kelas->nama_kelas }}
                    </span>
                    <span class="d-flex align-items-center gap-2 flex-shrink-0">
                        @if($kelas->shift)
                            <span class="badge bg-dark-subtle text-dark border px-2 py-1 rounded-pill" style="font-size: 0.7rem;">
                                <i class="bi bi-clock me-1"></i>{{ $kelas->shift->nama_shift }}
                            </span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary border px-2 py-1 rounded-pill" style="font-size: 0.7rem;">
                                <i class="bi bi-globe2 me-1"></i>Global
                            </span>
                        @endif
                        <span class="badge bg-white text-dark border px-2 py-1 rounded-2 font-monospace flex-shrink-0" style="font-size: 0.8rem;">
                            {{ $kelas->tingkat }}
                        </span>
                    </span>
                </div>

                <div class="p-3">
                    <!-- Baris 1: Jurusan + Total Siswa -->
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                        <div class="min-w-0 flex-grow-1">
                            <div class="text-muted small fw-semibold mb-1">JURUSAN</div>
                            @if($kelas->jurusan)
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <span class="badge bg-light text-secondary border px-2 py-1 font-monospace" style="font-size: 0.75rem;">
                                        {{ $kelas->jurusan->kode_jurusan }}
                                    </span>
                                    <div class="fw-semibold text-dark" style="font-size: 0.875rem;">
                                        {{ $kelas->jurusan->nama_jurusan }}
                                    </div>
                                </div>
                            @else
                                <span class="text-muted" style="font-size: 0.85rem;">-</span>
                            @endif
                        </div>
                        <div class="text-end flex-shrink-0">
                            <div class="text-muted small fw-semibold mb-1">SISWA</div>
                            <a href="{{ route('kelas.show', $kelas->id) }}" class="text-decoration-none">
                                <span class="badge bg-light text-primary border border-primary-subtle px-3 py-2 rounded-3 fw-bold" style="font-size: 0.85rem;">
                                    {{ $kelas->siswa_count ?? 0 }}
                                </span>
                            </a>
                        </div>
                    </div>

                    <!-- Baris 2: Wali Kelas + Aksi -->
                    <div class="d-flex justify-content-between align-items-center gap-2 pt-3 border-top">
                        <div class="d-flex align-items-center gap-2 min-w-0">
                            @if($kelas->waliKelas)
                                <div class="rounded-circle bg-secondary-subtle text-secondary fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                                     style="width: 36px; height: 36px; font-size: 0.8rem;">
                                    {{ $waliInitial }}
                                </div>
                                <div class="min-w-0">
                                    <div class="text-muted small fw-semibold">WALI KELAS</div>
                                    <div class="fw-bold text-dark text-truncate" title="{{ $kelas->waliKelas->nama }}" style="font-size: 0.9rem; max-width: 180px;">
                                        {{ $kelas->waliKelas->nama }}
                                    </div>
                                </div>
                            @else
                                <div class="min-w-0">
                                    <div class="text-muted small fw-semibold">WALI KELAS</div>
                                    <span class="badge bg-light text-muted border px-2 py-1 rounded-pill" style="font-size: 0.78rem;">
                                        <i class="bi bi-person-x me-1"></i> Belum ada wali
                                    </span>
                                </div>
                            @endif
                        </div>

                        @if(in_array(auth()->user()->role ?? '', ['admin_tu', 'admin', 'super_admin']) || (auth()->user() && auth()->user()->isTestingUser()))
                            <div class="flex-shrink-0">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light border rounded-3 dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="window" aria-expanded="false">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3 z-50" style="z-index: 1050;">
                                        <li>
                                            <a href="{{ route('kelas.show', $kelas->id) }}" class="dropdown-item py-2">
                                                <i class="bi bi-eye me-2 text-info"></i> Detail Kelas & Siswa
                                            </a>
                                        </li>
                                        <li>
                                            <button type="button" class="dropdown-item py-2" data-bs-toggle="modal" data-bs-target="#modalEditKelas{{ $kelas->id }}">
                                                <i class="bi bi-pencil-square me-2 text-warning"></i> Edit Kelas
                                            </button>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form action="{{ route('kelas.destroy', $kelas->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data kelas {{ $kelas->nama_kelas }}?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item py-2 text-danger">
                                                    <i class="bi bi-trash me-2"></i> Hapus Kelas
                                                </button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center py-5 text-muted">
                <i class="bi bi-door-closed fs-1 d-block mb-2 text-secondary"></i>
                Belum ada data kelas yang sesuai dengan kriteria pencarian/filter.
            </div>
        @endforelse
    </div>

    <!-- FOOTER TABEL & PAGINATION -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-4 pt-3 border-top">
        <div class="text-muted small mb-3 mb-md-0">
            Menampilkan <strong>{{ $dataKelas->firstItem() ?? 0 }}</strong>-<strong>{{ $dataKelas->lastItem() ?? 0 }}</strong> dari <strong>{{ $dataKelas->total() }}</strong> Kelas
        </div>
        <div>
            {{ $dataKelas->links() }}
        </div>
    </div>
</div>

<!-- ================= MODALS EDIT KELAS (per baris) ================= -->
@if(in_array(auth()->user()->role ?? '', ['admin_tu', 'admin', 'super_admin']) || (auth()->user() && auth()->user()->isTestingUser()))
@foreach($dataKelas as $kelas)
<div class="modal fade" id="modalEditKelas{{ $kelas->id }}" tabindex="-1" aria-labelledby="modalEditKelasLabel{{ $kelas->id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark" id="modalEditKelasLabel{{ $kelas->id }}">Edit Data Kelas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('kelas.update', $kelas->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body py-4">
                    <!-- NAMA KELAS -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">NAMA KELAS <span class="text-danger">*</span></label>
                        <input type="text" name="nama_kelas" value="{{ old('nama_kelas', $kelas->nama_kelas) }}" required class="form-control rounded-3" placeholder="misal: RPL 1, TKJ 2">
                    </div>

                    <!-- TINGKAT -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">TINGKAT KELAS <span class="text-danger">*</span></label>
                        <select name="tingkat" class="form-select rounded-3" required>
                            <option value="X" {{ old('tingkat', $kelas->tingkat) == 'X' ? 'selected' : '' }}>Kelas X (Sepuluh)</option>
                            <option value="XI" {{ old('tingkat', $kelas->tingkat) == 'XI' ? 'selected' : '' }}>Kelas XI (Sebelas)</option>
                            <option value="XII" {{ old('tingkat', $kelas->tingkat) == 'XII' ? 'selected' : '' }}>Kelas XII (Dua Belas)</option>
                        </select>
                    </div>

                    <!-- JURUSAN -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">KOMPETENSI KEAHLIAN / JURUSAN <span class="text-danger">*</span></label>
                        <select name="id_jurusan" class="form-select rounded-3" required>
                            <option value="">-- Pilih Jurusan --</option>
                            @foreach($daftarJurusan as $jurusan)
                                <option value="{{ $jurusan->id }}" {{ old('id_jurusan', $kelas->id_jurusan) == $jurusan->id ? 'selected' : '' }}>
                                    {{ $jurusan->kode_jurusan }} - {{ $jurusan->nama_jurusan }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- SHIFT -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">SHIFT (OPSIONAL)</label>
                        <select name="shift_id" class="form-select rounded-3">
                            <option value="">-- Global (Tanpa Shift) --</option>
                            @foreach($daftarShift as $shift)
                                <option value="{{ $shift->id }}" {{ old('shift_id', $kelas->shift_id) == $shift->id ? 'selected' : '' }}>
                                    {{ $shift->nama_shift }} @if($shift->jam_mulai) ({{ substr($shift->jam_mulai, 0, 5) }} - {{ substr($shift->jam_selesai ?: '00:00', 0, 5) }}) @endif
                                    @if(!$shift->is_active) — Non-Aktif @endif
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text text-muted small">Menentukan jam pelajaran &amp; jam pulang yang berlaku untuk kelas ini.</div>
                    </div>

                    <!-- WALI KELAS -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">WALI KELAS (OPSIONAL)</label>
                        <select name="id_wali_kelas" class="form-select rounded-3">
                            <option value="">-- Belum Ditentukan / Kosongkan --</option>
                            @foreach($daftarWaliKelas as $wali)
                                @php
                                    $isCurrentWali = ($kelas->id_wali_kelas == $wali->id);
                                    $hasOtherKelas = $wali->kelasWali->isNotEmpty() && !$isCurrentWali;
                                    $namaKelasDipegang = $hasOtherKelas ? $wali->kelasWali->pluck('nama_kelas')->join(', ') : '';
                                @endphp
                                <option value="{{ $wali->id }}"
                                        {{ old('id_wali_kelas', $kelas->id_wali_kelas) == $wali->id ? 'selected' : '' }}
                                        {{ $hasOtherKelas ? 'disabled' : '' }}>
                                    {{ $wali->nama }} @if($wali->nip) (NIP: {{ $wali->nip }}) @endif
                                    @if($isCurrentWali)
                                        - [Wali Saat Ini]
                                    @elseif($hasOtherKelas)
                                        - [Sudah menjadi wali di {{ $namaKelasDipegang }}]
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text text-muted small">1 Guru hanya dapat ditugaskan menjadi Wali Kelas untuk 1 kelas.</div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4 fw-semibold">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach
@endif