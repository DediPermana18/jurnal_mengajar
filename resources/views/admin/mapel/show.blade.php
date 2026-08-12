@extends('admin.layouts.app')

@section('title', 'Matriks Jadwal Harian - {{ $kelas->nama_kelas }} - WebJournal')

@push('styles')
<style>
    /* === Jam Card Grid === */
    .jam-cards-scroll {
        display: flex;
        gap: 1rem;
        overflow-x: auto;
        padding: 0.5rem 0 1.25rem 0;
        scrollbar-width: thin;
        scrollbar-color: #b8cde2 transparent;
    }

    .jam-cards-scroll::-webkit-scrollbar {
        height: 5px;
    }

    .jam-cards-scroll::-webkit-scrollbar-thumb {
        background-color: #b8cde2;
        border-radius: 10px;
    }

    /* === Jam Column Header === */
    .jam-column {
        flex: 0 0 220px;
        min-width: 220px;
    }

    .jam-header-label {
        font-size: 0.78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #64748b;
        margin-bottom: 0.65rem;
        padding: 0 0.25rem;
    }

    /* === Individual Jadwal Card === */
    .jadwal-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 1rem 1rem 0.85rem;
        cursor: pointer;
        transition: all 0.22s ease;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
        position: relative;
        overflow: hidden;
    }

    .jadwal-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.1);
        border-color: #bfdbfe;
    }

    .jadwal-card.has-jurnal {
        border-color: #d1fae5;
    }

    .jadwal-card.no-jurnal {
        border-style: dashed;
        border-color: #e2e8f0;
        background-color: #f9fbfd;
    }

    .jadwal-card.no-jurnal:hover {
        border-color: #93c5fd;
        background-color: #ffffff;
    }

    /* === Kelas Badge di Card === */
    .card-kelas-badge {
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.03em;
        padding: 0.2rem 0.55rem;
        border-radius: 6px;
        margin-bottom: 0.7rem;
        display: inline-block;
    }

    .badge-rpl  { background: #dbeafe; color: #1d4ed8; }
    .badge-tkj  { background: #fce7f3; color: #be185d; }
    .badge-akl  { background: #fef9c3; color: #854d0e; }
    .badge-tkr  { background: #dcfce7; color: #166534; }
    .badge-default { background: #f1f5f9; color: #475569; }

    /* === Card score / nomor urut === */
    .card-score {
        position: absolute;
        top: 0.8rem;
        right: 0.9rem;
        font-size: 0.7rem;
        font-weight: 700;
        color: #94a3b8;
    }

    /* === Mapel Name === */
    .card-mapel-name {
        font-size: 1rem;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 0.3rem;
        line-height: 1.3;
    }

    /* === Guru Name === */
    .card-guru-name {
        font-size: 0.8rem;
        color: #64748b;
        font-weight: 500;
        margin-bottom: 0.9rem;
    }

    /* === Guru Avatar Row === */
    .card-footer-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-top: 1px solid #f1f5f9;
        padding-top: 0.7rem;
        margin-top: 0.3rem;
    }

    .guru-avatar {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background-color: #3b82f6;
        color: #ffffff;
        font-size: 0.72rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    /* === Status Badge pada card === */
    .card-status-masuk {
        background: #dcfce7;
        color: #166534;
        border: 1px solid #bbf7d0;
        border-radius: 50px;
        padding: 0.2rem 0.6rem;
        font-size: 0.72rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
    }

    .card-status-tidak-hadir {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
        border-radius: 50px;
        padding: 0.2rem 0.6rem;
        font-size: 0.72rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
    }

    .card-status-tugas {
        background: #fef9c3;
        color: #854d0e;
        border: 1px solid #fde68a;
        border-radius: 50px;
        padding: 0.2rem 0.6rem;
        font-size: 0.72rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
    }

    .card-status-kosong {
        background: #f1f5f9;
        color: #94a3b8;
        border: 1px dashed #cbd5e1;
        border-radius: 50px;
        padding: 0.2rem 0.6rem;
        font-size: 0.72rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
    }

    /* === Detail Modal Jurnal === */
    .modal-jurnal .modal-content {
        border: none;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 20px 60px rgba(15, 23, 42, 0.15);
    }

    .modal-jurnal .modal-header {
        background: linear-gradient(135deg, #1565c0 0%, #1e88e5 100%);
        border: none;
        padding: 1.5rem 1.75rem;
    }

    .modal-jurnal .modal-title {
        color: #ffffff;
        font-weight: 800;
        font-size: 1.1rem;
    }

    .modal-jurnal .modal-header .btn-close {
        filter: brightness(10);
        opacity: 0.7;
    }

    .modal-jurnal .modal-body {
        padding: 1.75rem;
        background-color: #f8fafc;
    }

    .jurnal-info-row {
        display: flex;
        align-items: flex-start;
        margin-bottom: 1.1rem;
        gap: 0.85rem;
    }

    .jurnal-info-icon {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        background: #e0f2fe;
        color: #0369a1;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
    }

    .jurnal-info-label {
        font-size: 0.78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #94a3b8;
        margin-bottom: 0.15rem;
    }

    .jurnal-info-value {
        font-size: 0.95rem;
        font-weight: 700;
        color: #0f172a;
    }

    /* Materi box */
    .materi-box {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1rem 1.25rem;
        margin-top: 0.5rem;
        font-size: 0.925rem;
        color: #334155;
        line-height: 1.6;
    }

    /* Empty state */
    .empty-state-card {
        background: #ffffff;
        border: 2px dashed #e2e8f0;
        border-radius: 16px;
        padding: 3.5rem 2rem;
        text-align: center;
    }

    /* Filter bar */
    .filter-bar {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 1rem 1.25rem;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">

    {{-- Breadcrumb --}}
    <div class="mb-3">
        <a href="{{ route('mapel.index') }}" class="text-decoration-none text-muted d-inline-flex align-items-center gap-1 small fw-semibold" style="font-size: 0.85rem;">
            <i class="bi bi-arrow-left"></i> Kembali ke Data Master Mapel
        </a>
    </div>

    {{-- Page Title --}}
    <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between mb-4 gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="font-weight: 800; font-size: 1.85rem; letter-spacing: -0.02em;">
                Matriks Jadwal Harian - {{ $kelas->nama_kelas }}
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                Monitoring matriks jam mengajar dan bukti foto kehadiran guru hari ini.
            </p>
        </div>

        {{-- Tombol Tanggal & Pilih Kelas --}}
        <div class="d-flex align-items-center gap-2 flex-wrap">
            {{-- Tanggal Hari Ini --}}
            <div class="d-flex align-items-center gap-2 bg-white rounded-3 border px-3 py-2 shadow-sm" style="font-size: 0.85rem;">
                <i class="bi bi-calendar3 text-primary"></i>
                <span class="fw-semibold text-dark">
                    Hari Ini ({{ \Carbon\Carbon::parse($today)->isoFormat('dddd, D MMM YYYY') }})
                </span>
            </div>

            {{-- Dropdown Pilih Kelas --}}
            <div class="dropdown">
                <button class="btn btn-white rounded-3 border px-3 py-2 fw-semibold shadow-sm d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" style="font-size: 0.875rem;">
                    <span>Pilih Kelas: {{ $kelas->nama_kelas }}</span>
                    <i class="bi bi-chevron-down small"></i>
                </button>
                <ul class="dropdown-menu shadow-sm border-0 rounded-3">
                    @foreach($dataKelas as $k)
                        <li>
                            <a class="dropdown-item small fw-medium py-2 {{ $k->id_kelas == $kelas->id_kelas ? 'active' : '' }}" 
                               href="{{ route('mapel.show', $k->id_kelas) }}">
                                {{ $k->nama_kelas }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>

    {{-- Jadwal Cards Horizontal Scroll --}}
    @if($jadwals->isEmpty())
        {{-- Empty State --}}
        <div class="empty-state-card">
            <i class="bi bi-calendar-x" style="font-size: 3rem; color: #cbd5e1;"></i>
            <h5 class="mt-3 fw-bold text-dark">Belum Ada Jadwal</h5>
            <p class="text-muted small">Tidak ada jadwal pelajaran untuk kelas <strong>{{ $kelas->nama_kelas }}</strong> hari ini.</p>
            <a href="{{ route('mapel.index') }}" class="btn btn-primary mt-2 rounded-3 px-4" style="background-color: #1565c0; border: none;">
                Tambah Data Mapel
            </a>
        </div>
    @else
        {{-- Matriks Grid Horizontal --}}
        <div class="jam-cards-scroll" id="matriksScroll">

            {{-- ===== DEMO DATA: Ditampilkan jika jadwal DB kosong ===== --}}
            @php
                $demoJadwal = [
                    [
                        'jam_label'   => 'Jam Ke-1',
                        'mapel'       => 'Konsentrasi RPL',
                        'guru'        => 'Budi Santoso, S.Kom',
                        'inisial'     => 'BS',
                        'badge'       => 'badge-rpl',
                        'kelas_label' => 'Las. RPL 1',
                        'score'       => '8.57',
                        'status'      => 'masuk',
                        'jurnal'      => [
                            'materi'   => 'Pembuatan REST API dengan Laravel 11 menggunakan Eloquent ORM dan Resource Controller.',
                            'tanggal'  => now()->format('d F Y'),
                            'hadir'    => 30,
                            'keterangan' => 'Semua siswa aktif mengikuti pembelajaran.',
                        ]
                    ],
                    [
                        'jam_label'   => 'Jam Ke-2',
                        'mapel'       => 'Konsentrasi RPL',
                        'guru'        => 'Budi Santoso, S.Kom',
                        'inisial'     => 'BS',
                        'badge'       => 'badge-rpl',
                        'kelas_label' => 'Las. RPL 1',
                        'score'       => '8.57',
                        'status'      => 'masuk',
                        'jurnal'      => [
                            'materi'   => 'Implementasi middleware dan autentikasi API Token pada Laravel.',
                            'tanggal'  => now()->format('d F Y'),
                            'hadir'    => 28,
                            'keterangan' => '2 siswa izin sakit.',
                        ]
                    ],
                    [
                        'jam_label'   => 'Jam Ke-3',
                        'mapel'       => 'Matematika',
                        'guru'        => 'Dwi Wahyuni, S.E',
                        'inisial'     => 'DW',
                        'badge'       => 'badge-akl',
                        'kelas_label' => '8.57',
                        'score'       => '8.57',
                        'status'      => 'tidak_hadir',
                        'jurnal'      => null
                    ],
                    [
                        'jam_label'   => 'Jam Ke-4',
                        'mapel'       => 'B. Inggris',
                        'guru'        => 'Siti Aminah, S.Pd',
                        'inisial'     => 'SA',
                        'badge'       => 'badge-tkj',
                        'kelas_label' => '8.57',
                        'score'       => '8.57',
                        'status'      => 'masuk',
                        'jurnal'      => [
                            'materi'   => 'Reading comprehension: Descriptive Text & Simple Present Tense.',
                            'tanggal'  => now()->format('d F Y'),
                            'hadir'    => 32,
                            'keterangan' => '-',
                        ]
                    ],
                    [
                        'jam_label'   => 'Jam Ke-5',
                        'mapel'       => 'PKK',
                        'guru'        => 'Ahmad Subarjo, S.T',
                        'inisial'     => 'AS',
                        'badge'       => 'badge-tkr',
                        'kelas_label' => '8.57',
                        'score'       => '8.57',
                        'status'      => 'kosong',
                        'jurnal'      => null
                    ],
                ];
            @endphp

            @foreach($jadwals as $index => $jadwal)
                @php
                    $jurnal     = $jurnalHariIni[$jadwal->id_jadwal] ?? null;
                    $namaGuru   = $jadwal->guru->nama_guru ?? 'Belum ditentukan';
                    $namaMapel  = $jadwal->mapel->nama_mapel ?? '-';
                    $jamLabel   = 'Jam Ke-' . ($index + 1);

                    // Generate Inisial
                    $inisial = '';
                    foreach (array_slice(explode(' ', $namaGuru), 0, 2) as $w) {
                        $inisial .= strtoupper(substr($w, 0, 1));
                    }

                    // Status Guru
                    $statusGuru = $jurnal ? ($jurnal->status_guru ?? 'Hadir') : 'kosong';
                @endphp
                <div class="jam-column">
                    <div class="jam-header-label">{{ $jamLabel }}</div>
                    <div class="jadwal-card {{ $jurnal ? 'has-jurnal' : 'no-jurnal' }}"
                         onclick="showJurnalModal({{ json_encode([
                             'jam'          => $jamLabel,
                             'mapel'        => $namaMapel,
                             'guru'         => $namaGuru,
                             'kelas'        => $kelas->nama_kelas,
                             'jam_mulai'    => $jadwal->jam_mulai,
                             'jam_selesai'  => $jadwal->jam_selesai,
                             'materi'       => $jurnal ? $jurnal->materi : null,
                             'keterangan'   => $jurnal ? $jurnal->keterangan : null,
                             'hadir'        => $jurnal ? $jurnal->jumlah_siswa_hadir : null,
                             'tanggal'      => $jurnal ? \Carbon\Carbon::parse($jurnal->tanggal)->isoFormat('D MMMM YYYY') : null,
                             'status_guru'  => $statusGuru,
                             'has_jurnal'   => (bool)$jurnal,
                         ]) }})">

                        {{-- Badge Kelas & Score --}}
                        <div class="d-flex align-items-start justify-content-between mb-1">
                            <span class="card-kelas-badge badge-rpl">{{ $kelas->nama_kelas }}</span>
                            <span class="card-score">{{ number_format(8.57, 2) }}</span>
                        </div>

                        {{-- Nama Mapel --}}
                        <div class="card-mapel-name">{{ $namaMapel }}</div>
                        <div class="card-guru-name">{{ $namaGuru }}</div>

                        {{-- Footer: Guru Avatar + Status --}}
                        <div class="card-footer-row">
                            <div class="guru-avatar">{{ $inisial }}</div>

                            @if(!$jurnal)
                                <span class="card-status-kosong">
                                    <i class="bi bi-clock" style="font-size: 0.65rem;"></i> Belum Diisi
                                </span>
                            @elseif(in_array($statusGuru, ['Hadir', 'Masuk Kelas']))
                                <span class="card-status-masuk">
                                    <i class="bi bi-circle-fill" style="font-size: 0.45rem;"></i> Masuk
                                </span>
                            @elseif($statusGuru == 'Tidak Hadir' || $statusGuru == 'Izin' || $statusGuru == 'Sakit')
                                <span class="card-status-tidak-hadir">
                                    <i class="bi bi-circle-fill" style="font-size: 0.45rem;"></i> Tidak Hadir
                                </span>
                            @else
                                <span class="card-status-tugas">
                                    <i class="bi bi-circle-fill" style="font-size: 0.45rem;"></i> {{ $statusGuru }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach

        </div>
    @endif

    {{-- ============================================================ --}}
    {{-- DEMO MATRIKS: Ditampilkan jika data jadwal di DB masih kosong --}}
    {{-- ============================================================ --}}
    @if($jadwals->isEmpty())
        <div class="mt-3">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-3 py-1 rounded-pill fw-semibold small">
                    <i class="bi bi-info-circle me-1"></i> Preview / Demo Data
                </span>
                <span class="text-muted small">Isi jadwal terlebih dahulu untuk menampilkan data nyata.</span>
            </div>
            <div class="jam-cards-scroll">
                @foreach($demoJadwal as $dIdx => $demo)
                    <div class="jam-column">
                        <div class="jam-header-label">{{ $demo['jam_label'] }}</div>
                        <div class="jadwal-card {{ $demo['jurnal'] ? 'has-jurnal' : 'no-jurnal' }}"
                             onclick="showJurnalModal({{ json_encode([
                                 'jam'         => $demo['jam_label'],
                                 'mapel'       => $demo['mapel'],
                                 'guru'        => $demo['guru'],
                                 'kelas'       => $kelas->nama_kelas,
                                 'jam_mulai'   => null,
                                 'jam_selesai' => null,
                                 'materi'      => $demo['jurnal']['materi'] ?? null,
                                 'keterangan'  => $demo['jurnal']['keterangan'] ?? null,
                                 'hadir'       => $demo['jurnal']['hadir'] ?? null,
                                 'tanggal'     => $demo['jurnal']['tanggal'] ?? null,
                                 'status_guru' => $demo['status'],
                                 'has_jurnal'  => $demo['jurnal'] !== null,
                             ]) }})">
                            <div class="d-flex align-items-start justify-content-between mb-1">
                                <span class="card-kelas-badge {{ $demo['badge'] }}">{{ $demo['kelas_label'] }}</span>
                                <span class="card-score">{{ $demo['score'] }}</span>
                            </div>
                            <div class="card-mapel-name">{{ $demo['mapel'] }}</div>
                            <div class="card-guru-name">{{ $demo['guru'] }}</div>
                            <div class="card-footer-row">
                                <div class="guru-avatar">{{ $demo['inisial'] }}</div>
                                @if($demo['status'] == 'masuk')
                                    <span class="card-status-masuk"><i class="bi bi-circle-fill" style="font-size: 0.45rem;"></i> Masuk</span>
                                @elseif($demo['status'] == 'tidak_hadir')
                                    <span class="card-status-tidak-hadir"><i class="bi bi-circle-fill" style="font-size: 0.45rem;"></i> Tidak Hadir</span>
                                @else
                                    <span class="card-status-kosong"><i class="bi bi-clock" style="font-size: 0.65rem;"></i> Belum Diisi</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

</div>

{{-- ============================================================ --}}
{{-- MODAL: Detail Hasil Pengisian Jurnal                          --}}
{{-- ============================================================ --}}
<div class="modal fade modal-jurnal" id="modalJurnal" tabindex="-1" aria-labelledby="modalJurnalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 520px;">
        <div class="modal-content">

            {{-- Modal Header --}}
            <div class="modal-header">
                <div>
                    <div class="text-white opacity-75 small fw-semibold mb-1" id="modalJamLabel">Jam Ke-1</div>
                    <h5 class="modal-title" id="modalJurnalLabel">Detail Pengisian Jurnal</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            {{-- Modal Body: Isi Jurnal --}}
            <div class="modal-body">

                {{-- Kondisi: Jurnal Sudah Diisi --}}
                <div id="modalHasJurnal">
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <div class="jurnal-info-row mb-0">
                                <div class="jurnal-info-icon" style="background:#e0f2fe; color:#0369a1;">
                                    <i class="bi bi-book-fill"></i>
                                </div>
                                <div>
                                    <div class="jurnal-info-label">Mata Pelajaran</div>
                                    <div class="jurnal-info-value" id="modalMapel">-</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="jurnal-info-row mb-0">
                                <div class="jurnal-info-icon" style="background:#dcfce7; color:#166534;">
                                    <i class="bi bi-door-open-fill"></i>
                                </div>
                                <div>
                                    <div class="jurnal-info-label">Kelas</div>
                                    <div class="jurnal-info-value" id="modalKelas">-</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="jurnal-info-row mb-0">
                                <div class="jurnal-info-icon" style="background:#fef9c3; color:#854d0e;">
                                    <i class="bi bi-person-badge-fill"></i>
                                </div>
                                <div>
                                    <div class="jurnal-info-label">Guru Pengajar</div>
                                    <div class="jurnal-info-value" id="modalGuru">-</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="jurnal-info-row mb-0">
                                <div class="jurnal-info-icon" style="background:#fce7f3; color:#be185d;">
                                    <i class="bi bi-people-fill"></i>
                                </div>
                                <div>
                                    <div class="jurnal-info-label">Siswa Hadir</div>
                                    <div class="jurnal-info-value" id="modalHadir">-</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="jurnal-info-row mb-0">
                                <div class="jurnal-info-icon" style="background:#ede9fe; color:#6d28d9;">
                                    <i class="bi bi-calendar-check-fill"></i>
                                </div>
                                <div>
                                    <div class="jurnal-info-label">Tanggal Pengisian</div>
                                    <div class="jurnal-info-value" id="modalTanggal">-</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Status Guru Badge --}}
                    <div class="mb-3 d-flex align-items-center gap-2">
                        <span class="jurnal-info-label mb-0">Status Guru:</span>
                        <span id="modalStatusBadge"></span>
                    </div>

                    {{-- Materi Pelajaran --}}
                    <div>
                        <div class="jurnal-info-label mb-1">📖 Materi Pembelajaran</div>
                        <div class="materi-box" id="modalMateri">-</div>
                    </div>

                    {{-- Keterangan (jika ada) --}}
                    <div class="mt-3" id="keteranganSection">
                        <div class="jurnal-info-label mb-1">📝 Keterangan Tambahan</div>
                        <div class="materi-box" id="modalKeterangan">-</div>
                    </div>
                </div>

                {{-- Kondisi: Jurnal Belum Diisi --}}
                <div id="modalNoJurnal" class="d-none text-center py-3">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">📋</div>
                    <h6 class="fw-bold text-dark mb-1">Jurnal Belum Diisi</h6>
                    <p class="text-muted small mb-0">Guru belum mengisi jurnal mengajar untuk sesi ini hari ini.</p>
                </div>

            </div>

            {{-- Modal Footer --}}
            <div class="modal-footer border-0 bg-white pt-0 pb-3 px-4">
                <button type="button" class="btn btn-light border rounded-3 px-4 fw-semibold" data-bs-dismiss="modal">Tutup</button>
            </div>

        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    function showJurnalModal(data) {
        const modal = new bootstrap.Modal(document.getElementById('modalJurnal'));

        // Isi header info
        document.getElementById('modalJamLabel').textContent = data.jam;
        document.getElementById('modalJurnalLabel').textContent = data.has_jurnal
            ? 'Detail Hasil Pengisian Jurnal'
            : 'Jurnal Belum Diisi';

        if (data.has_jurnal) {
            // Tampilkan konten jurnal
            document.getElementById('modalHasJurnal').classList.remove('d-none');
            document.getElementById('modalNoJurnal').classList.add('d-none');

            document.getElementById('modalMapel').textContent   = data.mapel   || '-';
            document.getElementById('modalKelas').textContent   = data.kelas   || '-';
            document.getElementById('modalGuru').textContent    = data.guru    || '-';
            document.getElementById('modalHadir').textContent   = data.hadir !== null ? data.hadir + ' Siswa' : '-';
            document.getElementById('modalTanggal').textContent = data.tanggal || '-';
            document.getElementById('modalMateri').textContent  = data.materi  || 'Tidak ada materi.';

            // Keterangan
            if (data.keterangan && data.keterangan !== '-') {
                document.getElementById('keteranganSection').classList.remove('d-none');
                document.getElementById('modalKeterangan').textContent = data.keterangan;
            } else {
                document.getElementById('keteranganSection').classList.add('d-none');
            }

            // Status Badge
            let badgeHTML = '';
            const s = data.status_guru;
            if (s === 'masuk' || s === 'Hadir' || s === 'Masuk Kelas') {
                badgeHTML = '<span class="card-status-masuk"><i class="bi bi-check-circle-fill"></i> Masuk Kelas</span>';
            } else if (s === 'tidak_hadir' || s === 'Tidak Hadir' || s === 'Izin' || s === 'Sakit') {
                badgeHTML = '<span class="card-status-tidak-hadir"><i class="bi bi-x-circle-fill"></i> Tidak Hadir</span>';
            } else {
                badgeHTML = '<span class="card-status-tugas"><i class="bi bi-circle-fill" style="font-size: 0.45rem;"></i> ' + s + '</span>';
            }
            document.getElementById('modalStatusBadge').innerHTML = badgeHTML;

        } else {
            // Jurnal belum diisi
            document.getElementById('modalHasJurnal').classList.add('d-none');
            document.getElementById('modalNoJurnal').classList.remove('d-none');
        }

        modal.show();
    }
</script>
@endpush
