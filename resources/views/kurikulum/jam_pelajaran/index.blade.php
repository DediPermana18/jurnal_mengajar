@extends('layouts.app')

@section('title', 'Master Jam Pelajaran Sekolah - WebJournal Management System')

@section('content')
<div class="container-fluid px-0">

    {{-- Page Header --}}
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="font-weight: 900; font-size: 1.75rem; letter-spacing: -0.02em;">
                Master Jam Pelajaran Sekolah
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                Kelola struktur jam pelajaran KBM dan istirahat berlaku global (Senin – Jumat). Penomoran jam otomatis berurutan.
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap align-items-center">
            {{-- Tombol Generate Preset Senin-Kamis --}}
            <form method="POST" action="{{ route('admin.jam-pelajaran.generate') }}" class="d-inline"
                  onsubmit="return confirm('Generate preset akan mengosongkan dan membuat ulang slot jam Senin–Kamis. Lanjutkan?')">
                @csrf
                <input type="hidden" name="kategori_hari" value="Senin-Kamis">
                <button type="submit" class="btn btn-outline-warning rounded-3 fw-semibold px-3 d-flex align-items-center gap-2"
                        style="font-size: 0.875rem;">
                    <i class="bi bi-lightning-charge-fill"></i>
                    ⚡ Generate Preset Senin–Kamis
                </button>
            </form>

            {{-- Tombol Generate Preset Jumat --}}
            <form method="POST" action="{{ route('admin.jam-pelajaran.generate') }}" class="d-inline"
                  onsubmit="return confirm('Generate preset akan mengosongkan dan membuat ulang slot jam Jumat. Lanjutkan?')">
                @csrf
                <input type="hidden" name="kategori_hari" value="Jumat">
                <button type="submit" class="btn btn-outline-warning rounded-3 fw-semibold px-3 d-flex align-items-center gap-2"
                        style="font-size: 0.875rem;">
                    <i class="bi bi-lightning-charge-fill"></i>
                    ⚡ Generate Preset Jumat
                </button>
            </form>

            {{-- Tombol Tambah Jam --}}
            <button type="button" class="btn btn-primary rounded-3 fw-semibold px-3 d-flex align-items-center gap-2"
                    style="font-size: 0.875rem;" data-bs-toggle="modal" data-bs-target="#modalTambahJam">
                <i class="bi bi-plus-lg"></i>
                Tambah Jam Pelajaran
            </button>
        </div>
    </div>

    {{-- Alert Messages --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4" role="alert"
             style="font-size: 0.9rem;">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4" role="alert"
             style="font-size: 0.9rem;">
            <i class="bi bi-exclamation-circle-fill me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Tab Kelompok Hari (Senin–Kamis vs Jumat) --}}
    <div class="mb-4">
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.jam-pelajaran.index', ['tab' => 'Senin-Kamis']) }}"
               class="btn rounded-3 fw-semibold px-4 py-2 {{ $tab === 'Senin-Kamis' ? 'btn-primary shadow-sm text-white' : 'btn-light border text-dark' }}"
               style="font-size: 0.875rem;">
                <i class="bi bi-calendar-week me-1"></i>
                Senin – Kamis <span class="badge {{ $tab === 'Senin-Kamis' ? 'bg-white text-primary' : 'bg-secondary-subtle text-secondary' }} rounded-pill ms-1">40 menit</span>
            </a>
            <a href="{{ route('admin.jam-pelajaran.index', ['tab' => 'Jumat']) }}"
               class="btn rounded-3 fw-semibold px-4 py-2 {{ $tab === 'Jumat' ? 'btn-primary shadow-sm text-white' : 'btn-light border text-dark' }}"
               style="font-size: 0.875rem;">
                <i class="bi bi-calendar2-day me-1"></i>
                Jumat <span class="badge {{ $tab === 'Jumat' ? 'bg-white text-primary' : 'bg-secondary-subtle text-secondary' }} rounded-pill ms-1">30 menit</span>
            </a>
        </div>
    </div>

    {{-- Main Data Card --}}
    <div class="card border-0 rounded-4 shadow-sm">
        <div class="card-header bg-white border-0 pt-4 pb-0 px-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-2 d-flex align-items-center justify-content-center"
                         style="width: 34px; height: 34px; background: {{ $tab === 'Senin-Kamis' ? 'linear-gradient(135deg,#1677ff,#0958d9)' : 'linear-gradient(135deg,#f97316,#ea580c)' }};">
                        <i class="bi {{ $tab === 'Senin-Kamis' ? 'bi-calendar-week' : 'bi-calendar2-day' }} text-white" style="font-size: 0.95rem;"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.95rem;">
                            Master Jam Sekolah &mdash; {{ $tab === 'Senin-Kamis' ? 'Senin – Kamis' : 'Jumat' }}
                        </h6>
                        <div class="text-muted" style="font-size: 0.75rem;">
                            {{ ($tab === 'Senin-Kamis' ? $seninKamis : $jumat)->count() }} slot terdaftar (Berlaku Global)
                        </div>
                    </div>
                </div>
                <span class="badge bg-light text-secondary border rounded-pill px-3 py-1" style="font-size: 0.75rem;">
                    {{ $tab === 'Senin-Kamis' ? 'Durasi KBM: 40 Menit' : 'Durasi KBM: 30 Menit' }}
                </span>
            </div>
        </div>

        <div class="card-body p-0">
            @php $rows = $tab === 'Senin-Kamis' ? $seninKamis : $jumat; @endphp

            @if($rows->isEmpty())
                <div class="text-center py-5">
                    <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle mb-3" style="width: 70px; height: 70px;">
                        <i class="bi bi-clock text-muted" style="font-size: 2.2rem;"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-1">Belum Ada Data Jam Pelajaran ({{ $tab }})</h6>
                    <p class="text-muted mx-auto mb-3" style="max-width: 420px; font-size: 0.85rem;">
                        Klik tombol <strong>Generate Preset</strong> di atas atau tambah slot jam secara manual.
                    </p>
                    <form method="POST" action="{{ route('admin.jam-pelajaran.generate') }}" class="d-inline">
                        @csrf
                        <input type="hidden" name="kategori_hari" value="{{ $tab }}">
                        <button type="submit" class="btn btn-primary rounded-3 px-3 py-2 fw-semibold" style="font-size: 0.85rem;">
                            <i class="bi bi-lightning-charge-fill me-1"></i> Generate Preset {{ $tab }}
                        </button>
                    </form>
                </div>
            @else
                <div class="table-responsive w-full overflow-x-auto">
                    <table class="table table-hover align-middle mb-0 min-w-full" style="font-size: 0.9rem;">
                        <thead style="background: #f8fafc;">
                            <tr>
                                <th class="ps-4 py-3" style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #64748b; white-space: nowrap; width: 130px;">Jam Ke-</th>
                                <th class="py-3" style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #64748b; width: 180px;">Rentang Waktu</th>
                                <th class="py-3" style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #64748b; width: 120px;">Durasi</th>
                                <th class="py-3" style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #64748b;">Jenis / Keterangan</th>
                                <th class="py-3 pe-4 text-end whitespace-nowrap" style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #64748b; width: 140px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $istirahatCount = 0; @endphp
                            @foreach($rows as $jam)
                                @php
                                    $mulai   = \Carbon\Carbon::createFromFormat('H:i:s', $jam->jam_mulai);
                                    $selesai = \Carbon\Carbon::createFromFormat('H:i:s', $jam->jam_selesai);
                                    $durasi  = $mulai->diffInMinutes($selesai);

                                    if ($jam->jenis === 'istirahat') {
                                        $istirahatCount++;
                                        $jenisLabel = "Istirahat " . $istirahatCount;
                                    } else {
                                        $jenisLabel = match($jam->jenis) {
                                            'kbm'        => 'KBM',
                                            'upacara'    => 'Upacara',
                                            'pembiasaan' => 'Pembiasaan',
                                            default      => ucfirst($jam->jenis),
                                        };
                                    }

                                    $jenisBadge = match($jam->jenis) {
                                        'kbm'        => ['bg' => '#ecfdf5', 'color' => '#059669', 'border' => '#a7f3d0', 'icon' => 'bi-book-fill'],
                                        'istirahat'  => ['bg' => '#fff7ed', 'color' => '#ea580c', 'border' => '#fed7aa', 'icon' => 'bi-cup-hot-fill'],
                                        'upacara'    => ['bg' => '#eff6ff', 'color' => '#2563eb', 'border' => '#bfdbfe', 'icon' => 'bi-flag-fill'],
                                        'pembiasaan' => ['bg' => '#f5f3ff', 'color' => '#7c3aed', 'border' => '#ddd6fe', 'icon' => 'bi-heart-pulse-fill'],
                                        default      => ['bg' => '#f8fafc', 'color' => '#64748b', 'border' => '#e2e8f0', 'icon' => 'bi-circle-fill'],
                                    };
                                @endphp
                                <tr>
                                    <td class="ps-4 whitespace-nowrap">
                                        <div class="d-flex align-items-center gap-2">
                                            @if($jam->jenis !== 'istirahat' && $jam->jam_ke)
                                                <div class="rounded-circle d-flex align-items-center justify-content-center fw-black text-white"
                                                     style="width: 30px; height: 30px; font-size: 0.78rem; background: {{ $jam->jenis === 'kbm' ? '#1677ff' : ($jam->jenis === 'upacara' ? '#2563eb' : '#7c3aed') }};">
                                                    {{ $jam->jam_ke }}
                                                </div>
                                                <span class="fw-bold text-dark">Jam {{ $jam->jam_ke }}</span>
                                            @else
                                                <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-muted bg-light border"
                                                     style="width: 30px; height: 30px; font-size: 0.78rem;">
                                                    -
                                                </div>
                                                <span class="text-muted fw-semibold">-</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-dark" style="font-variant-numeric: tabular-nums; font-family: 'Courier New', monospace; font-size: 0.92rem;">
                                            {{ substr(str_replace(':', '.', $jam->jam_mulai), 0, 5) }} – {{ substr(str_replace(':', '.', $jam->jam_selesai), 0, 5) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-muted fw-semibold" style="font-size: 0.85rem;">{{ $durasi }} menit</span>
                                    </td>
                                    <td>
                                        <span class="badge d-inline-flex align-items-center gap-1 px-3 py-2 rounded-pill fw-semibold"
                                              style="font-size: 0.78rem; background-color: {{ $jenisBadge['bg'] }}; color: {{ $jenisBadge['color'] }}; border: 1px solid {{ $jenisBadge['border'] }};">
                                            <i class="bi {{ $jenisBadge['icon'] }}" style="font-size: 0.72rem;"></i>
                                            {{ $jenisLabel }}
                                        </span>
                                    </td>
                                    <td class="pe-4 text-end whitespace-nowrap">
                                        <div class="flex items-center justify-center gap-2 whitespace-nowrap">
                                            <button type="button"
                                                    class="btn btn-sm btn-light border rounded-3 px-2 py-1"
                                                    style="font-size: 0.78rem;"
                                                    title="Edit"
                                                    onclick="openEditModal(
                                                        {{ $jam->id }},
                                                        '{{ $jam->kategori_hari }}',
                                                        '{{ substr($jam->jam_mulai, 0, 5) }}',
                                                        '{{ substr($jam->jam_selesai, 0, 5) }}',
                                                        '{{ $jam->jenis }}'
                                                    )">
                                                <i class="bi bi-pencil-fill text-primary me-1"></i> Edit
                                            </button>
                                            <form method="POST"
                                                  action="{{ route('admin.jam-pelajaran.destroy', $jam->id) }}"
                                                  onsubmit="return confirm('Hapus slot {{ $jenisLabel }} ({{ \Carbon\Carbon::parse($jam->jam_mulai)->format('H.i') }})?')"
                                                  class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-light border rounded-3 px-2 py-1"
                                                        style="font-size: 0.78rem;" title="Hapus">
                                                    <i class="bi bi-trash3-fill text-danger me-1"></i> Hapus
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

</div>

{{-- ===================== CARD PENGATURAN JAM PULANG PER TINGKAT ===================== --}}
<div class="container-fluid px-0 mt-4">
    <div class="card border-0 rounded-4 shadow-sm">
        <div class="card-header bg-white border-0 pt-4 pb-3 px-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-2 d-flex align-items-center justify-content-center"
                         style="width: 34px; height: 34px; background: linear-gradient(135deg,#f97316,#ea580c);">
                        <i class="bi bi-door-closed-fill text-white" style="font-size: 0.95rem;"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.95rem;">
                            ⚙️ Pengaturan Jam Pulang per Tingkat Kelas
                        </h6>
                        <div class="text-muted" style="font-size: 0.75rem;">
                            Tentukan batas slot KBM terakhir per tingkat. Slot setelahnya otomatis dikunci sebagai "🛑 Pulang Sekolah".
                        </div>
                    </div>
                </div>
                <span class="badge bg-orange-subtle text-warning border border-warning-subtle rounded-pill px-3 py-1"
                      style="font-size: 0.72rem; background-color: #fff7ed; color: #c05500 !important; border-color: #fed7aa !important;">
                    Berlaku Global per Tingkat
                </span>
            </div>
        </div>

        <div class="card-body px-4 pb-4 pt-2">
            <form method="POST" action="{{ route('admin.jam-pulang.upsert') }}" id="formJamPulang">
                @csrf
                <input type="hidden" name="redirect_tab" value="{{ $tab }}">

                @php
                    $tingkatList = ['X', 'XI', 'XII'];
                    $kategoriList = [
                        'Senin-Kamis' => ['label' => 'Senin – Kamis', 'icon' => 'bi-calendar-week', 'max' => $maxJamKeSeninKamis],
                        'Jumat'       => ['label' => 'Jumat',         'icon' => 'bi-calendar2-day',  'max' => $maxJamKeJumat],
                    ];
                @endphp

                <div class="row g-4">
                    @foreach($kategoriList as $kHari => $kMeta)
                        <div class="col-md-6">
                            <div class="p-3 rounded-3 border bg-light-subtle" style="background-color: #fafafa;">
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <i class="bi {{ $kMeta['icon'] }} text-primary"></i>
                                    <span class="fw-bold text-dark" style="font-size: 0.9rem;">{{ $kMeta['label'] }}</span>
                                    <span class="badge bg-secondary-subtle text-secondary rounded-pill ms-auto px-2 py-1" style="font-size: 0.72rem;">
                                        Max Jam KBM Tersedia: {{ $kMeta['max'] }}
                                    </span>
                                </div>
                                <div class="d-flex flex-column gap-2">
                                    @foreach($tingkatList as $tingkat)
                                        @php
                                            $key       = "{$kHari}|{$tingkat}";
                                            $savedMax  = $jamPulangSettings->get($key)?->max_jam_ke;
                                        @endphp
                                        <div class="d-flex align-items-center gap-3 p-2 rounded-3 bg-white border">
                                            <div class="d-flex align-items-center justify-content-center rounded-2 fw-black text-white flex-shrink-0"
                                                 style="width: 36px; height: 36px; font-size: 0.8rem; background: {{ $tingkat === 'X' ? '#1677ff' : ($tingkat === 'XI' ? '#7c3aed' : '#059669') }};">
                                                {{ $tingkat }}
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="fw-semibold text-dark mb-1" style="font-size: 0.82rem;">
                                                    Kelas {{ $tingkat }} — Pulang Setelah:
                                                </div>
                                                <select name="jam_pulang[{{ $kHari }}][{{ $tingkat }}]"
                                                        class="form-select form-select-sm rounded-3"
                                                        style="font-size: 0.82rem;">
                                                    <option value="">— Tidak Dibatasi (semua slot aktif) —</option>
                                                    @for($j = 1; $j <= $kMeta['max']; $j++)
                                                        <option value="{{ $j }}" {{ $savedMax == $j ? 'selected' : '' }}>
                                                            Jam Ke-{{ $j }}
                                                            @if($j == $kMeta['max']) (Jam Terakhir) @endif
                                                        </option>
                                                    @endfor
                                                </select>
                                            </div>
                                            @if($savedMax)
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1 flex-shrink-0"
                                                      style="font-size: 0.72rem;">
                                                    Batas: Jam {{ $savedMax }}
                                                </span>
                                            @else
                                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 flex-shrink-0"
                                                      style="font-size: 0.72rem;">
                                                    Bebas
                                                </span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="d-flex align-items-center justify-content-between mt-4 pt-3 border-top flex-wrap gap-3">
                    <div class="text-muted d-flex align-items-center gap-2" style="font-size: 0.8rem;">
                        <i class="bi bi-info-circle text-primary"></i>
                        Pilih "Tidak Dibatasi" agar semua slot KBM dapat di-plot tanpa batas jam pulang.
                    </div>
                    <button type="submit" class="btn btn-warning fw-bold px-4 rounded-3 d-flex align-items-center gap-2"
                            style="font-size: 0.875rem; background: #f97316; border-color: #f97316; color: white;">
                        <i class="bi bi-floppy-fill"></i> Simpan Pengaturan Jam Pulang
                    </button>
                </div>
            </form>
        </div>
</div>

{{-- ===================== 2 CARD TERPISAH: PENGATURAN AGENDA RUTIN ===================== --}}
<div class="container-fluid px-0 mt-4">
    <div class="row g-4">
        {{-- CARD 1: Pengaturan Upacara Bendera (Khusus Hari Senin) --}}
        <div class="col-12 col-lg-6">
            <div class="card border-0 rounded-4 shadow-sm h-100">
                <div class="card-header bg-white border-0 pt-4 pb-2 px-4">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <div class="rounded-2 d-flex align-items-center justify-content-center text-white"
                                 style="width: 36px; height: 36px; background: linear-gradient(135deg,#3b82f6,#1d4ed8);">
                                <span style="font-size: 1.1rem;">🇮🇩</span>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.95rem;">
                                    Pengaturan Upacara Bendera (Khusus Hari Senin)
                                </h6>
                                <div class="text-muted" style="font-size: 0.75rem;">
                                    Hari Senin (Locked Global)
                                </div>
                            </div>
                        </div>
                        @if(isset($agendaSenin) && $agendaSenin->is_active)
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1" style="font-size: 0.72rem;">
                                <i class="bi bi-lock-fill me-1"></i>Slot Terkunci: Jam ke-{{ $agendaSenin->jam_ke }}
                            </span>
                        @else
                            <span class="badge bg-light text-muted border rounded-pill px-3 py-1" style="font-size: 0.72rem;">
                                Belum Diaktifkan
                            </span>
                        @endif
                    </div>
                </div>

                <div class="card-body px-4 pb-4 pt-2 d-flex flex-column justify-content-between">
                    <form method="POST" action="{{ route('admin.agenda-rutin.upsert') }}" id="formAgendaSenin">
                        @csrf
                        <input type="hidden" name="hari" value="Senin">
                        <input type="hidden" name="redirect_tab" value="Senin-Kamis">

                        <div class="row g-3 align-items-center mb-3">
                            {{-- Dropdown Jam Ke- --}}
                            <div class="col-12 col-sm-5">
                                <label class="form-label fw-semibold text-dark mb-1" style="font-size: 0.85rem;">
                                    <i class="bi bi-clock-history text-primary me-1"></i> Jam Ke- <span class="text-danger">*</span>
                                </label>
                                <select name="jam_ke" class="form-select rounded-3" required style="font-size: 0.875rem;">
                                    @for($j = 1; $j <= 15; $j++)
                                        <option value="{{ $j }}" {{ old('jam_ke', $agendaSenin->jam_ke ?? 1) == $j ? 'selected' : '' }}>
                                            Jam Ke-{{ $j }}
                                        </option>
                                    @endfor
                                </select>
                            </div>

                            {{-- Toggle Switch --}}
                            <div class="col-12 col-sm-7 pt-sm-4">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="switchAgendaSenin" name="is_active" value="1"
                                           {{ old('is_active', $agendaSenin->is_active ?? true) ? 'checked' : '' }} style="cursor: pointer; width: 2.5em; height: 1.25em;">
                                    <label class="form-check-label fw-semibold text-dark ms-2" for="switchAgendaSenin" style="font-size: 0.85rem; cursor: pointer;">
                                        Kunci Slot Upacara Bendera
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="text-muted small mb-3 p-2.5 rounded-3 bg-light border" style="font-size: 0.78rem;">
                            <i class="bi bi-info-circle text-primary me-1"></i>
                            Mengunci slot jam ini secara otomatis di seluruh kelas untuk Upacara Bendera pada hari Senin.
                        </div>

                        <div class="d-flex justify-content-end pt-2 border-top">
                            <button type="submit" class="btn btn-primary fw-bold px-4 rounded-3 d-flex align-items-center gap-2" style="font-size: 0.85rem;">
                                <i class="bi bi-floppy-fill"></i> Simpan Upacara Senin
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- CARD 2: Pengaturan Pembiasaan (Khusus Hari Jumat) --}}
        <div class="col-12 col-lg-6">
            <div class="card border-0 rounded-4 shadow-sm h-100">
                <div class="card-header bg-white border-0 pt-4 pb-2 px-4">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <div class="rounded-2 d-flex align-items-center justify-content-center text-white"
                                 style="width: 36px; height: 36px; background: linear-gradient(135deg,#0284c7,#0369a1);">
                                <span style="font-size: 1.1rem;">🤲</span>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.95rem;">
                                    Pengaturan Pembiasaan (Khusus Hari Jumat)
                                </h6>
                                <div class="text-muted" style="font-size: 0.75rem;">
                                    Hari Jumat (Locked Global)
                                </div>
                            </div>
                        </div>
                        @if(isset($agendaJumat) && $agendaJumat->is_active)
                            <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-3 py-1" style="font-size: 0.72rem;">
                                <i class="bi bi-lock-fill me-1"></i>Slot Terkunci: Jam ke-{{ $agendaJumat->jam_ke }}
                            </span>
                        @else
                            <span class="badge bg-light text-muted border rounded-pill px-3 py-1" style="font-size: 0.72rem;">
                                Belum Diaktifkan
                            </span>
                        @endif
                    </div>
                </div>

                <div class="card-body px-4 pb-4 pt-2 d-flex flex-column justify-content-between">
                    <form method="POST" action="{{ route('admin.agenda-rutin.upsert') }}" id="formAgendaJumat">
                        @csrf
                        <input type="hidden" name="hari" value="Jumat">
                        <input type="hidden" name="redirect_tab" value="Jumat">

                        <div class="row g-3 align-items-center mb-3">
                            {{-- Dropdown Jam Ke- --}}
                            <div class="col-12 col-sm-5">
                                <label class="form-label fw-semibold text-dark mb-1" style="font-size: 0.85rem;">
                                    <i class="bi bi-clock-history text-info me-1"></i> Jam Ke- <span class="text-danger">*</span>
                                </label>
                                <select name="jam_ke" class="form-select rounded-3" required style="font-size: 0.875rem;">
                                    @for($j = 1; $j <= 15; $j++)
                                        <option value="{{ $j }}" {{ old('jam_ke', $agendaJumat->jam_ke ?? 1) == $j ? 'selected' : '' }}>
                                            Jam Ke-{{ $j }}
                                        </option>
                                    @endfor
                                </select>
                            </div>

                            {{-- Toggle Switch --}}
                            <div class="col-12 col-sm-7 pt-sm-4">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="switchAgendaJumat" name="is_active" value="1"
                                           {{ old('is_active', $agendaJumat->is_active ?? true) ? 'checked' : '' }} style="cursor: pointer; width: 2.5em; height: 1.25em;">
                                    <label class="form-check-label fw-semibold text-dark ms-2" for="switchAgendaJumat" style="font-size: 0.85rem; cursor: pointer;">
                                        Kunci Slot Pembiasaan Jumat
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="text-muted small mb-3 p-2.5 rounded-3 bg-light border" style="font-size: 0.78rem;">
                            <i class="bi bi-info-circle text-info me-1"></i>
                            Mengunci slot jam ini secara otomatis di seluruh kelas untuk Pembiasaan (Yasinan/Senam/Jumat Bersih) pada hari Jumat.
                        </div>

                        <div class="d-flex justify-content-end pt-2 border-top">
                            <button type="submit" class="btn btn-info text-white fw-bold px-4 rounded-3 d-flex align-items-center gap-2" style="font-size: 0.85rem;">
                                <i class="bi bi-floppy-fill"></i> Simpan Pembiasaan Jumat
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalTambahJam" tabindex="-1" aria-labelledby="modalTambahJamTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <form method="POST" action="{{ route('admin.jam-pelajaran.store') }}" id="formTambahJam">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="modalTambahJamTitle">
                        <i class="bi bi-plus-circle-fill text-primary me-2"></i>Tambah Jam Pelajaran
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark" style="font-size: 0.875rem;">Kategori Hari</label>
                        <select name="kategori_hari" id="tambahKategoriHari" class="form-select rounded-3" required>
                            <option value="Senin-Kamis" {{ $tab === 'Senin-Kamis' ? 'selected' : '' }}>Senin – Kamis</option>
                            <option value="Jumat" {{ $tab === 'Jumat' ? 'selected' : '' }}>Jumat</option>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold text-dark" style="font-size: 0.875rem;">Jam Mulai</label>
                            <input type="time" name="jam_mulai" id="tambahJamMulai" class="form-control rounded-3" step="60" autocomplete="off" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold text-dark" style="font-size: 0.875rem;">Jam Selesai</label>
                            <input type="time" name="jam_selesai" id="tambahJamSelesai" class="form-control rounded-3" step="60" autocomplete="off" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark" style="font-size: 0.875rem;">Jenis Slot</label>
                        <select name="jenis" id="tambahJenis" class="form-select rounded-3" required>
                            <option value="kbm">KBM (Kegiatan Belajar Mengajar)</option>
                            <option value="upacara">Upacara</option>
                            <option value="pembiasaan">Pembiasaan</option>
                            <option value="istirahat">Istirahat</option>
                        </select>
                        <div class="form-text text-muted" style="font-size: 0.78rem;">
                            Penomoran Jam Ke- (Jam 1, Jam 2, dst.) dan penamaan Istirahat dihitung otomatis berurutan secara global.
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4 fw-semibold" id="btnSubmitTambah">
                        <i class="bi bi-check-lg me-1"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ===================== MODAL EDIT JAM ===================== --}}
<div class="modal fade" id="modalEditJam" tabindex="-1" aria-labelledby="modalEditJamTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <form method="POST" id="formEditJam" action="">
                @csrf
                @method('PUT')
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="modalEditJamTitle">
                        <i class="bi bi-pencil-square text-warning me-2"></i>Edit Jam Pelajaran
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark" style="font-size: 0.875rem;">Kategori Hari</label>
                        <select name="kategori_hari" id="editKategoriHari" class="form-select rounded-3" required>
                            <option value="Senin-Kamis">Senin – Kamis</option>
                            <option value="Jumat">Jumat</option>
                        </select>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold text-dark" style="font-size: 0.875rem;">Jam Mulai</label>
                            <input type="time" name="jam_mulai" id="editJamMulai" class="form-control rounded-3" step="60" autocomplete="off" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold text-dark" style="font-size: 0.875rem;">Jam Selesai</label>
                            <input type="time" name="jam_selesai" id="editJamSelesai" class="form-control rounded-3" step="60" autocomplete="off" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark" style="font-size: 0.875rem;">Jenis Slot</label>
                        <select name="jenis" id="editJenis" class="form-select rounded-3" required>
                            <option value="kbm">KBM (Kegiatan Belajar Mengajar)</option>
                            <option value="upacara">Upacara</option>
                            <option value="pembiasaan">Pembiasaan</option>
                            <option value="istirahat">Istirahat</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning text-white rounded-3 px-4 fw-semibold" id="btnSubmitEdit">
                        <i class="bi bi-check-lg me-1"></i> Perbarui
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {

        const forms = [
            document.getElementById('formTambahJam'),
            document.getElementById('formEditJam')
        ];

        forms.forEach(function (form) {
            if (!form) return;

            const inputs = form.querySelectorAll('input:not([type="hidden"]), select');
            inputs.forEach(function (input, index) {
                input.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        if (index < inputs.length - 1) {
                            inputs[index + 1].focus();
                        }
                    }
                });
            });
        });
    });

    function openEditModal(id, kategoriHari, jamMulai, jamSelesai, jenis) {
        const routeBase = "{{ url('admin/jam-pelajaran') }}";
        document.getElementById('formEditJam').action = routeBase + '/' + id;

        document.getElementById('editKategoriHari').value = kategoriHari;
        document.getElementById('editJamMulai').value     = jamMulai;
        document.getElementById('editJamSelesai').value   = jamSelesai;
        document.getElementById('editJenis').value        = jenis;

        const modal = new bootstrap.Modal(document.getElementById('modalEditJam'));
        modal.show();
    }

</script>
@endpush
