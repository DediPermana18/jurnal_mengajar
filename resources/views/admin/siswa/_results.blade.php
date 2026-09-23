{{-- ============================================================ --}}
{{-- PARTIAL: Hasil filter Data Master Siswa (di-update via AJAX) --}}
{{-- Dipakai oleh admin.siswa.index (render awal) & SiswaController--}}
{{-- saat request AJAX (return JSON html).                        --}}
{{-- ============================================================ --}}

{{-- Indikator Filter Aktif & Tombol Reset --}}
@if(request()->hasAny(['search', 'id_kelas', 'id_jurusan', 'jenis_kelamin']) && (request('search') || request('id_kelas') || request('id_jurusan') || request('jenis_kelamin')))
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div class="d-flex flex-wrap align-items-center gap-1.5" style="font-size: 0.8rem; color: #64748b;">
            <span class="fw-semibold text-dark"><i class="bi bi-funnel-fill text-primary me-1"></i>Filter Aktif:</span>
            @if(request('search'))
                <span class="badge bg-light text-dark border px-2 py-1">Pencarian: "{{ request('search') }}"</span>
            @endif
            @if(request('id_kelas'))
                @php $selK = $dataKelas->firstWhere('id', request('id_kelas')); @endphp
                <span class="badge bg-light text-primary border border-primary-subtle px-2 py-1">Kelas: {{ $selK ? $selK->tingkat . ' ' . $selK->nama_kelas : request('id_kelas') }}</span>
            @endif
            @if(request('id_jurusan'))
                @php $selJ = $jurusans->firstWhere('id', request('id_jurusan')); @endphp
                <span class="badge bg-light text-success border border-success-subtle px-2 py-1">Jurusan: {{ $selJ ? $selJ->kode_jurusan : request('id_jurusan') }}</span>
            @endif
            @if(request('jenis_kelamin'))
                <span class="badge bg-light text-dark border px-2 py-1">Gender: {{ request('jenis_kelamin') == 'L' ? 'Laki-laki' : 'Perempuan' }}</span>
            @endif
        </div>
        <a href="{{ route('siswa.index') }}" data-reset-filter class="btn btn-sm btn-outline-secondary rounded-2 px-2 py-1 text-decoration-none d-inline-flex align-items-center gap-1" style="font-size: 0.78rem;">
            <i class="bi bi-arrow-counterclockwise"></i>
            <span>Reset Filter</span>
        </a>
    </div>
@endif

{{-- ====================================================== --}}
{{-- TABLE CARD                                              --}}
{{-- ====================================================== --}}
<div class="table-card-custom">

    {{-- Table --}}
    <div class="table-responsive w-full overflow-x-auto">
        <table class="table table-custom align-middle min-w-full" style="min-width: 760px;">
            <thead>
                <tr>
                    <th style="width: 30%;">NISN & NAMA SISWA</th>
                    <th class="whitespace-nowrap" style="width: 12%;">NIS</th>
                    <th style="width: 20%;">KELAS & JURUSAN</th>
                    <th class="whitespace-nowrap" style="width: 13%;">JENIS KELAMIN</th>
                    <th class="whitespace-nowrap" style="width: 12%;">STATUS</th>
                    <th class="whitespace-nowrap" style="width: 13%; text-align: right;">AKSI</th>
                </tr>
            </thead>
            <tbody>
                @forelse($dataSiswa as $idx => $siswa)
                    @php
                        // Generate inisial dari nama
                        $words   = explode(' ', $siswa->nama ?? '');
                        $inisial = strtoupper(substr($words[0] ?? 'S', 0, 1) . substr($words[1] ?? '', 0, 1));

                        // Warna avatar stabil berdasarkan ID siswa
                        $palette = ['#3b82f6','#8b5cf6','#ec4899','#f97316','#10b981','#06b6d4','#f59e0b','#6366f1'];
                        $bgColor = $palette[$siswa->id % count($palette)];

                        // Status siswa
                        $status = $siswa->status_siswa ?? 'Aktif';
                    @endphp
                    <tr>
                        {{-- Kolom 1: NISN & Nama --}}
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <div class="siswa-avatar" style="background-color: {{ $bgColor }};">
                                    {{ $inisial }}
                                </div>
                                <div>
                                    <div class="fw-bold text-dark" style="font-size: 0.9rem; line-height: 1.3;">
                                        {{ $siswa->nama ?? '-' }}
                                    </div>
                                    <span class="text-muted small d-block mt-1">
                                        {{ $siswa->nisn ?? 'NISN belum diisi' }}
                                    </span>
                                </div>
                            </div>
                        </td>

                        {{-- Kolom 2: NIS --}}
                        <td class="whitespace-nowrap">
                            @if($siswa->nis)
                                <span class="nisn-code">{{ $siswa->nis }}</span>
                            @else
                                <span class="text-muted" style="font-size: 0.82rem;">-</span>
                            @endif
                        </td>

                        {{-- Kolom 3: Kelas & Jurusan --}}
                        <td>
                            @if($siswa->kelas)
                                <span class="badge-kelas">{{ $siswa->kelas->tingkat }} &bull; {{ $siswa->kelas->nama_kelas }}</span>
                            @else
                                <span class="text-muted" style="font-size: 0.82rem;">Belum ditentukan</span>
                            @endif
                        </td>

                        {{-- Kolom 4: Jenis Kelamin --}}
                        <td>
                            @if($siswa->jenis_kelamin == 'L')
                                <span class="badge-gender badge-laki inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium">
                                    <i class="bi bi-gender-male" style="font-size: 0.8rem;"></i>
                                    <span>Laki-laki</span>
                                </span>
                            @elseif($siswa->jenis_kelamin == 'P')
                                <span class="badge-gender badge-perempuan inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium">
                                    <i class="bi bi-gender-female" style="font-size: 0.8rem;"></i>
                                    <span>Perempuan</span>
                                </span>
                            @else
                                <span class="text-muted" style="font-size: 0.82rem;">-</span>
                            @endif
                        </td>

                        {{-- Kolom 5: Status Siswa --}}
                        <td>
                            @if(strtolower($status) == 'aktif')
                                <span class="badge-aktif">
                                    <i class="bi bi-circle-fill" style="font-size: 0.42rem;"></i> Aktif
                                </span>
                            @else
                                <span class="badge-tidak-aktif">
                                    <i class="bi bi-circle-fill" style="font-size: 0.42rem;"></i> {{ $status }}
                                </span>
                            @endif
                        </td>

                        {{-- Kolom 6: Aksi --}}
                        <td class="whitespace-nowrap">
                            <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                                {{-- Edit --}}
                                <a href="{{ route('siswa.edit', $siswa->id) }}"
                                   class="btn-aksi"
                                   title="Edit Data">
                                    <i class="bi bi-pencil"></i>
                                </a>

                                {{-- Hapus --}}
                                <form action="{{ route('siswa.destroy', $siswa->id) }}" method="POST" class="d-inline-flex"
                                      onsubmit="return confirm('Yakin ingin menghapus data siswa {{ addslashes($siswa->nama) }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-aksi btn-aksi-danger" title="Hapus">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <div style="color: #cbd5e1;">
                                <i class="bi bi-people" style="font-size: 2.5rem; display: block; margin-bottom: 0.75rem;"></i>
                            </div>
                            <div class="fw-semibold text-dark mb-1">Tidak ada data siswa</div>
                            <div class="text-muted" style="font-size: 0.85rem;">
                                @if(request()->hasAny(['search','id_kelas','id_jurusan','jenis_kelamin']))
                                    Tidak ada siswa yang sesuai dengan filter. <a href="{{ route('siswa.index') }}" data-reset-filter>Reset filter</a>
                                @else
                                    Belum ada siswa yang terdaftar. <a href="{{ route('siswa.create') }}">Tambah siswa baru</a>.
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

{{-- ====================================================== --}}
{{-- FOOTER: Info & Pagination                               --}}
{{-- ====================================================== --}}
@if($dataSiswa->total() > 0)
    <div class="custom-pagination-wrapper">
        {{-- Info jumlah di sebelah KIRI --}}
        <div class="pagination-info-text">
            Menampilkan
            <strong>{{ $dataSiswa->firstItem() }} - {{ $dataSiswa->lastItem() }}</strong>
            dari
            <strong>{{ number_format($dataSiswa->total()) }}</strong>
            siswa
            @if(request()->hasAny(['search','id_kelas','id_jurusan','jenis_kelamin']))
                <span class="text-muted ms-1">(difilter dari total <strong>{{ number_format($totalSiswa) }}</strong> siswa)</span>
            @endif
        </div>

        {{-- Tombol Navigasi Pagination di sebelah KANAN --}}
        <div class="pagination-controls">
            {{-- Tombol Prev --}}
            @if ($dataSiswa->onFirstPage())
                <span class="pagination-btn disabled" aria-disabled="true">
                    <svg class="pagination-svg-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                    <span>Prev</span>
                </span>
            @else
                <a href="{{ $dataSiswa->appends(request()->query())->previousPageUrl() }}" class="pagination-btn">
                    <svg class="pagination-svg-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                    <span>Prev</span>
                </a>
            @endif

            {{-- Counter Halaman (Badge) --}}
            <span class="pagination-badge">
                {{ $dataSiswa->currentPage() }} / {{ $dataSiswa->lastPage() }}
            </span>

            {{-- Tombol Next --}}
            @if ($dataSiswa->hasMorePages())
                <a href="{{ $dataSiswa->appends(request()->query())->nextPageUrl() }}" class="pagination-btn">
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

</div>