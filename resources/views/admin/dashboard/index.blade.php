@extends('layouts.app')

@section('title', 'Dashboard - WebJournal Management System')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3 md:mb-4 gap-1 md:gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 900; font-size: 1.5rem;">Dashboard</h2>
            <p class="text-muted mb-0" style="font-size: 0.85rem;">Ringkasan data master dan akun terbaru.</p>
        </div>
        <span class="text-muted small mt-1 mt-md-0"><i class="bi bi-calendar3 me-1"></i>{{ now()->translatedFormat('l, d F Y') }}</span>
    </div>

    {{-- WIDGET SAKELAR: Mode Khusus Senin (Upacara Ditiadakan / KBM Dimajukan) --}}
    {{-- Hanya tampil pada hari Senin (server-side: ISO weekday 1 = Senin). --}}
    @if(now()->isoFormat('d') == 1 && (auth()->user()->role === 'admin' || in_array(auth()->user()->role, ['waka_kurikulum', 'admin_kurikulum', 'kurikulum'])))
        <div class="card border-0 rounded-4 shadow-sm mb-4 bg-white overflow-hidden">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-3 d-flex align-items-center justify-content-center text-white flex-shrink-0"
                             style="width: 44px; height: 44px; background: linear-gradient(135deg, #e11d48, #be123c);">
                            <i class="bi bi-lightning-charge-fill fs-4"></i>
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <h6 class="fw-bold mb-0 text-dark" style="font-size: 1rem;">
                                    Sakelar Mode Khusus Hari Senin: Upacara Ditiadakan (KBM Dimajukan)
                                </h6>
                                @if($pengaturanJadwal->senin_tanpa_upacara && $pengaturanJadwal->tanggal_eksekusi)
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3 py-1" style="font-size: 0.72rem;">
                                        <i class="bi bi-circle-fill me-1" style="font-size: 0.45rem;"></i>MODE TANPA UPACARA AKTIF ({{ \Carbon\Carbon::parse($pengaturanJadwal->tanggal_eksekusi)->translatedFormat('d M Y') }})
                                    </span>
                                @else
                                    <span class="badge bg-light text-muted border rounded-pill px-3 py-1" style="font-size: 0.72rem;">
                                        Normal (Ada Upacara)
                                    </span>
                                @endif
                            </div>
                            <div class="text-muted" style="font-size: 0.8rem;">
                                Aktifkan sakelar ini jika upacara ditiadakan pada hari Senin. Seluruh jam KBM bergeser maju 1 JP & siswa/guru pulang lebih awal.
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('admin.toggle-senin-tanpa-upacara') }}" id="formToggleSeninShift">
                        @csrf
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" role="switch" id="toggleSeninShift" name="senin_tanpa_upacara" value="1"
                                   {{ $pengaturanJadwal->senin_tanpa_upacara ? 'checked' : '' }}
                                   onchange="this.form.submit()" style="cursor: pointer; width: 3em; height: 1.5em;">
                            <label class="form-check-label fw-bold text-dark ms-2" for="toggleSeninShift" style="font-size: 0.85rem; cursor: pointer;">
                                {{ $pengaturanJadwal->senin_tanpa_upacara ? 'KBM Dimajukan (Tanpa Upacara)' : 'Senin Normal (Ada Upacara)' }}
                            </label>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-4 mb-3 md:mb-4">
        <div>
            <div class="stat-card-custom p-3 md:p-4 h-100">
                <div class="stat-card-title text-xs md:text-sm truncate mb-1 md:mb-2" title="Total Guru Terdaftar">Total Guru Terdaftar</div>
                <div class="stat-number-large text-2xl md:text-4xl text-primary mb-1">{{ number_format($totalGuru) }}</div>
                <div class="stat-card-label text-xs md:text-sm truncate" title="Akun guru">Akun guru</div>
            </div>
        </div>
        <div>
            <div class="stat-card-custom p-3 md:p-4 h-100">
                <div class="stat-card-title text-xs md:text-sm truncate mb-1 md:mb-2" title="Total Siswa Terdaftar">Total Siswa Terdaftar</div>
                <div class="stat-number-large text-2xl md:text-4xl text-success mb-1">{{ number_format($totalSiswa) }}</div>
                <div class="stat-card-label text-xs md:text-sm truncate" title="Data siswa">Data siswa</div>
            </div>
        </div>
        <div>
            <div class="stat-card-custom p-3 md:p-4 h-100">
                <div class="stat-card-title text-xs md:text-sm truncate mb-1 md:mb-2" title="Total Kelas">Total Kelas</div>
                <div class="stat-number-large text-2xl md:text-4xl text-info mb-1">{{ number_format($totalKelas) }}</div>
                <div class="stat-card-label text-xs md:text-sm truncate" title="Rombongan belajar">Rombongan belajar</div>
            </div>
        </div>
        <div>
            <div class="stat-card-custom p-3 md:p-4 h-100">
                <div class="stat-card-title text-xs md:text-sm truncate mb-1 md:mb-2" title="Akun Tidak Aktif">Akun Tidak Aktif</div>
                <div class="stat-number-large text-2xl md:text-4xl text-secondary mb-1">{{ number_format($akunTidakAktif) }}</div>
                <div class="stat-card-label text-xs md:text-sm truncate" title="Akun yang dinonaktifkan">Akun yang dinonaktifkan</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xl-8">
            <div class="table-card-custom h-100">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold text-dark mb-0">User / Guru Baru Ditambahkan</h5>
                    <a href="{{ route('admin.users.index') }}" class="small text-decoration-none">Lihat Kelola User <i class="bi bi-arrow-right ms-1"></i></a>
                </div>
                <div class="overflow-x-auto w-full rounded-lg">
                    <table class="table table-custom align-middle mb-0 min-w-full">
                        <thead>
                            <tr>
                                <th class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">NAMA</th>
                                <th class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">USERNAME</th>
                                <th class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">ROLE</th>
                                <th class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">STATUS AKTIVASI</th>
                                <th class="whitespace-nowrap px-3 py-2 text-xs md:text-sm">TANGGAL DIBUAT</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($userTerbaru as $user)
                                @php
                                    $statusAktivasi = $user->is_active ? 'Aktif' : 'Nonaktif';
                                    $statusClass = $user->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary';
                                @endphp
                                <tr>
                                    <td class="fw-semibold text-dark whitespace-nowrap px-3 py-2 text-xs md:text-sm">{{ $user->nama }}</td>
                                    <td class="text-muted whitespace-nowrap px-3 py-2 text-xs md:text-sm">{{ $user->username }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 text-xs md:text-sm"><span class="badge bg-light text-dark border rounded-3 text-xs md:text-sm">{{ $user->role_label }}</span></td>
                                    <td class="whitespace-nowrap px-3 py-2 text-xs md:text-sm"><span class="badge {{ $statusClass }} rounded-pill px-2.5 py-1.5 text-xs md:text-sm">{{ $statusAktivasi }}</span></td>
                                    <td class="text-muted whitespace-nowrap px-3 py-2 text-xs md:text-sm">{{ $user->created_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center py-5 text-muted whitespace-nowrap px-3 py-2 text-xs md:text-sm">Belum ada user yang ditambahkan.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                <h5 class="fw-bold text-dark mb-1">Akses Cepat</h5>
                <p class="text-muted small mb-4">Buka halaman pengelolaan data yang sering digunakan.</p>
                <div class="d-grid gap-3">
                    <a href="{{ route('admin.guru.create') }}" class="btn btn-primary rounded-3 py-2 fw-semibold text-start"><i class="bi bi-person-plus-fill me-2"></i> Tambah Guru</a>
                    <a href="{{ route('siswa.create') }}" class="btn btn-outline-success rounded-3 py-2 fw-semibold text-start"><i class="bi bi-person-plus me-2"></i> Tambah Siswa</a>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary rounded-3 py-2 fw-semibold text-start"><i class="bi bi-person-gear me-2"></i> Kelola User</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
