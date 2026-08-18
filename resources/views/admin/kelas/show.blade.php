@extends('layouts.app')

@section('title', 'Detail Kelas ' . $kelas->nama_kelas . ' - WebJournal Management System')

@push('styles')
<style>
    /* === Siswa Table Card === */
    .siswa-no-badge {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: #f1f5f9;
        color: #475569;
        font-size: 0.78rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .siswa-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        font-size: 0.82rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        flex-shrink: 0;
    }

    /* === Info Cards Kelas === */
    .info-kelas-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 1.25rem 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04);
    }

    .info-kelas-icon {
        width: 46px;
        height: 46px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        flex-shrink: 0;
    }

    .info-kelas-label {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #94a3b8;
        margin-bottom: 0.15rem;
    }

    .info-kelas-value {
        font-size: 0.97rem;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.25;
    }

    /* === Matriks Button Banner === */
    .matriks-banner {
        background: linear-gradient(135deg, #1565c0 0%, #1e88e5 60%, #29b6f6 100%);
        border-radius: 16px;
        padding: 1.5rem 2rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1.5rem;
        color: white;
        margin-bottom: 1.5rem;
        box-shadow: 0 6px 24px rgba(21, 101, 192, 0.25);
    }

    .matriks-banner h5 {
        font-weight: 800;
        margin-bottom: 0.3rem;
        font-size: 1.1rem;
    }

    .matriks-banner p {
        margin-bottom: 0;
        opacity: 0.85;
        font-size: 0.875rem;
    }

    .btn-lihat-matriks {
        background: rgba(255, 255, 255, 0.2);
        border: 2px solid rgba(255, 255, 255, 0.5);
        color: #ffffff;
        border-radius: 12px;
        padding: 0.6rem 1.4rem;
        font-weight: 700;
        font-size: 0.9rem;
        white-space: nowrap;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
        backdrop-filter: blur(10px);
        flex-shrink: 0;
    }

    .btn-lihat-matriks:hover {
        background: rgba(255, 255, 255, 0.35);
        border-color: rgba(255, 255, 255, 0.8);
        color: #ffffff;
        transform: translateY(-1px);
    }

    /* Gender badge */
    .badge-laki { background: #dbeafe; color: #1d4ed8; }
    .badge-perempuan { background: #fce7f3; color: #be185d; }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">

    {{-- Breadcrumb --}}
    <div class="mb-3">
        <a href="{{ route('kelas.index') }}" class="text-decoration-none text-muted d-inline-flex align-items-center gap-1 small fw-semibold" style="font-size: 0.85rem;">
            <i class="bi bi-arrow-left"></i> Kembali ke Data Master Kelas
        </a>
    </div>

    {{-- Page Title --}}
    <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between mb-4 gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="font-weight: 800; font-size: 1.85rem; letter-spacing: -0.02em;">
                Detail Kelas — {{ $kelas->nama_kelas }}
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem; font-weight: 500;">
                {{ $kelas->jurusan->nama_jurusan ?? '-' }} · Tingkat: <strong>Kelas {{ $kelas->tingkat }}</strong> · Wali Kelas: <strong>{{ $kelas->waliKelas->nama ?? 'Belum Ditentukan' }}</strong>
            </p>
        </div>
        <a href="{{ route('kelas.index') }}" class="btn btn-light border rounded-3 px-3 py-2 fw-semibold d-flex align-items-center gap-2">
            <i class="bi bi-arrow-left"></i> Kembali ke Daftar Kelas
        </a>
    </div>

    {{-- Info Cards Row --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="info-kelas-card">
                <div class="info-kelas-icon" style="background: #dbeafe;">
                    <i class="bi bi-people-fill" style="color: #1d4ed8;"></i>
                </div>
                <div>
                    <div class="info-kelas-label">Total Siswa</div>
                    <div class="info-kelas-value">{{ $siswa->count() }} Siswa</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="info-kelas-card">
                <div class="info-kelas-icon" style="background: #dcfce7;">
                    <i class="bi bi-calendar3" style="color: #166534;"></i>
                </div>
                <div>
                    <div class="info-kelas-label">Jadwal Pelajaran</div>
                    <div class="info-kelas-value">{{ $jadwals->count() }} Mapel</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="info-kelas-card">
                <div class="info-kelas-icon" style="background: #fef9c3;">
                    <i class="bi bi-person-badge-fill" style="color: #854d0e;"></i>
                </div>
                <div>
                    <div class="info-kelas-label">Wali Kelas</div>
                    <div class="info-kelas-value">{{ $kelas->waliKelas->nama ?? '-' }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="info-kelas-card">
                <div class="info-kelas-icon" style="background: #fce7f3;">
                    <i class="bi bi-diagram-3-fill" style="color: #be185d;"></i>
                </div>
                <div>
                    <div class="info-kelas-label">Jurusan</div>
                    <div class="info-kelas-value">{{ $kelas->jurusan->kode_jurusan ?? '-' }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- ========================================================== --}}
    {{-- TABLE: Daftar Lengkap Siswa                                 --}}
    {{-- ========================================================== --}}
    <div class="table-card-custom">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="fw-bold text-dark mb-0" style="font-size: 1.1rem;">
                <i class="bi bi-person-lines-fill text-primary me-2"></i>
                Daftar Siswa Kelas {{ $kelas->nama_kelas }}
            </h5>
            {{-- Search Siswa --}}
            <div class="position-relative" style="width: 260px;">
                <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted" style="font-size: 0.85rem;"></i>
                <input type="text" id="searchSiswa" class="form-control rounded-3 ps-5 bg-light border-0 py-2" placeholder="Cari nama siswa...">
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-custom align-middle" id="tableSiswa">
                <thead>
                    <tr>
                        <th style="width: 5%;">NO</th>
                        <th style="width: 35%;">NAMA SISWA</th>
                        <th style="width: 20%;">NIS</th>
                        <th style="width: 20%;">NISN</th>
                        <th style="width: 20%;">JENIS KELAMIN</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($siswa as $idx => $s)
                        @php
                            $inisial = '';
                            $parts = explode(' ', trim($s->nama));
                            $inisial = strtoupper(substr($parts[0], 0, 1));
                            if (count($parts) > 1) {
                                $inisial .= strtoupper(substr(end($parts), 0, 1));
                            }
                            $colors = ['#3b82f6','#8b5cf6','#ec4899','#f97316','#10b981','#06b6d4'];
                            $color = $colors[$idx % count($colors)];
                        @endphp
                        <tr>
                            <td>
                                <div class="siswa-no-badge">{{ $idx + 1 }}</div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="siswa-avatar" style="background-color: {{ $color }};">{{ $inisial }}</div>
                                    <div>
                                        <div class="fw-bold text-dark" style="font-size: 0.95rem;">{{ $s->nama }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <code class="text-dark fw-semibold" style="background: #f1f5f9; padding: 0.2rem 0.55rem; border-radius: 6px; font-size: 0.85rem;">
                                    {{ $s->nis ?? '-' }}
                                </code>
                            </td>
                            <td>
                                <code class="text-dark fw-semibold" style="background: #f1f5f9; padding: 0.2rem 0.55rem; border-radius: 6px; font-size: 0.85rem;">
                                    {{ $s->nisn ?? '-' }}
                                </code>
                            </td>
                            <td>
                                @if(($s->jenis_kelamin ?? '') == 'L' || strtolower($s->jenis_kelamin ?? '') == 'laki-laki')
                                    <span class="badge badge-laki px-3 py-1 rounded-pill fw-semibold small">
                                        <i class="bi bi-gender-male me-1"></i> Laki-laki
                                    </span>
                                @elseif(($s->jenis_kelamin ?? '') == 'P' || strtolower($s->jenis_kelamin ?? '') == 'perempuan')
                                    <span class="badge badge-perempuan px-3 py-1 rounded-pill fw-semibold small">
                                        <i class="bi bi-gender-female me-1"></i> Perempuan
                                    </span>
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="bi bi-people fs-1 d-block mb-2 text-secondary"></i>
                                Belum ada siswa yang terdaftar di kelas ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Footer -->
        <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
            <div class="text-muted small">
                Menampilkan {{ $siswa->count() }} siswa di kelas ini
            </div>
            @if(in_array(auth()->user()->role ?? '', ['admin_tu', 'admin', 'super_admin']))
                <a href="{{ route('siswa.create') }}" class="btn btn-sm btn-outline-primary rounded-3 px-3 fw-semibold" style="font-size: 0.85rem;">
                    <i class="bi bi-plus me-1"></i> Tambah Siswa Baru
                </a>
            @endif
        </div>

    </div>

</div>
@endsection

@push('scripts')
<script>
    // Live Search Siswa
    document.getElementById('searchSiswa')?.addEventListener('input', function () {
        const query = this.value.toLowerCase();
        document.querySelectorAll('#tableSiswa tbody tr').forEach(row => {
            const nama = row.querySelector('td:nth-child(2)')?.textContent.toLowerCase() ?? '';
            const nis  = row.querySelector('td:nth-child(3)')?.textContent.toLowerCase() ?? '';
            row.style.display = (nama.includes(query) || nis.includes(query)) ? '' : 'none';
        });
    });
</script>
@endpush
