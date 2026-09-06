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
            {{-- Tombol Generate Preset --}}
            <button type="button" class="btn btn-outline-warning rounded-3 fw-semibold px-3 d-flex align-items-center gap-2"
                    style="font-size: 0.875rem;" data-bs-toggle="modal" data-bs-target="#modalGeneratePreset">
                <i class="bi bi-lightning-charge-fill"></i>
                ⚡ Generate Preset Jam
            </button>

            {{-- Tombol Tambah Jam --}}
            <button type="button" id="btnTambahJam"
                    class="btn btn-primary rounded-3 fw-semibold px-3 d-flex align-items-center gap-2"
                    style="font-size: 0.875rem;" data-bs-toggle="modal" data-bs-target="#modalTambahJam"
                    data-mulai-senin="{{ $autoMulai['Senin-Kamis'] }}"
                    data-mulai-jumat="{{ $autoMulai['Jumat'] }}">
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
                Senin – Kamis
            </a>
            <a href="{{ route('admin.jam-pelajaran.index', ['tab' => 'Jumat']) }}"
               class="btn rounded-3 fw-semibold px-4 py-2 {{ $tab === 'Jumat' ? 'btn-primary shadow-sm text-white' : 'btn-light border text-dark' }}"
               style="font-size: 0.875rem;">
                <i class="bi bi-calendar2-day me-1"></i>
                Jumat
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
                    <button type="button" class="btn btn-primary rounded-3 px-3 py-2 fw-semibold"
                            style="font-size: 0.85rem;" data-bs-toggle="modal" data-bs-target="#modalGeneratePreset">
                        <i class="bi bi-lightning-charge-fill me-1"></i> Generate Preset {{ $tab }}
                    </button>
                </div>
            @else
                <div class="d-flex align-items-center justify-content-end px-4 pt-3 pb-2">
                    <button type="button"
                            class="btn btn-outline-danger rounded-3 fw-semibold px-3 d-flex align-items-center gap-2"
                            style="font-size: 0.8rem;"
                            data-bs-toggle="modal" data-bs-target="#modalHapusSemuaJP">
                        <i class="bi bi-trash3-fill"></i> Hapus Semua JP
                    </button>
                </div>
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
                                            'kbm'   => 'KBM',
                                            default => ucfirst($jam->jenis),
                                        };
                                    }

                                    $jenisBadge = match($jam->jenis) {
                                        'kbm'       => ['bg' => '#ecfdf5', 'color' => '#059669', 'border' => '#a7f3d0', 'icon' => 'bi-book-fill'],
                                        'istirahat' => ['bg' => '#fff7ed', 'color' => '#ea580c', 'border' => '#fed7aa', 'icon' => 'bi-cup-hot-fill'],
                                        default     => ['bg' => '#f8fafc', 'color' => '#64748b', 'border' => '#e2e8f0', 'icon' => 'bi-circle-fill'],
                                    };
                                @endphp
                                <tr>
                                    <td class="ps-4 whitespace-nowrap">
                                        <div class="d-flex align-items-center gap-2">
                                            @if($jam->jenis !== 'istirahat' && $jam->jam_ke)
                                                <div class="rounded-circle d-flex align-items-center justify-content-center fw-black text-white"
                                                     style="width: 30px; height: 30px; font-size: 0.78rem; background: #1677ff;">
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
                                                        '{{ $jam->jenis }}',
                                                        {{ $mulai->diffInMinutes($selesai) }}
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
                                                        id="jp-{{ \Illuminate\Support\Str::slug($kHari) }}-{{ $tingkat }}"
                                                        class="form-select form-select-sm rounded-3 jam-pulang-select"
                                                        data-kategori="{{ $kHari }}"
                                                        data-tingkat="{{ $tingkat }}"
                                                        data-initial="{{ $savedMax ?: '' }}"
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
                                            <span class="badge jam-pulang-badge rounded-pill px-2 py-1 flex-shrink-0"
                                                  data-kategori="{{ $kHari }}"
                                                  data-tingkat="{{ $tingkat }}"
                                                  style="font-size: 0.72rem;">
                                                @if($savedMax)
                                                    <span class="badge text-bg-danger rounded-pill">Batas: Jam {{ $savedMax }}</span>
                                                @else
                                                    <span class="badge text-bg-info rounded-pill">Semua Slot</span>
                                                @endif
                                            </span>
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
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <div id="warningJamPulang" class="small p-2 rounded-3 text-warning-emphasis d-none"
                             style="font-size: 0.78rem; background-color: #fff8e1; border: 1px solid #ffe082;">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i> ⚠️ Ada Perubahan Belum Disimpan
                        </div>
                        <button type="submit" id="btnSimpanJamPulang" class="btn fw-bold px-4 rounded-3 d-flex align-items-center gap-2"
                                style="font-size: 0.875rem; background: #f97316; border-color: #f97316; color: white;">
                            <i class="bi bi-floppy-fill"></i> Simpan Pengaturan Jam Pulang
                        </button>
                    </div>
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
                        <span id="badgeStatusSenin"
                              class="badge {{ (isset($agendaSenin) && $agendaSenin->is_active) ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle' }} rounded-pill px-3 py-1"
                              style="font-size: 0.72rem;">
                            @if(isset($agendaSenin) && $agendaSenin->is_active)
                                ● Aktif (Terkunci Jam Ke-{{ $agendaSenin->jam_ke }})
                            @else
                                ○ Non-Aktif
                            @endif
                        </span>
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
                                <select name="jam_ke" id="jamKeSenin" class="form-select rounded-3" required style="font-size: 0.875rem;">
                                    @forelse($jamOptionsSenin as $jam)
                                        <option value="{{ $jam->jam_ke }}" {{ old('jam_ke', $agendaSenin->jam_ke ?? $jamOptionsSenin->first()->jam_ke) == $jam->jam_ke ? 'selected' : '' }}>
                                            Jam Ke-{{ $jam->jam_ke }} ({{ substr($jam->jam_mulai, 0, 5) }} - {{ substr($jam->jam_selesai, 0, 5) }})
                                        </option>
                                    @empty
                                        <option value="">— Belum ada slot jam KBM —</option>
                                    @endforelse
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

                        <div id="warningSenin" class="small mb-3 p-2 rounded-3 text-warning-emphasis d-none" style="font-size: 0.78rem; background-color: #fff8e1; border: 1px solid #ffe082;">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i> ⚠️ Ada perubahan yang belum disimpan
                        </div>

                        <div class="d-flex justify-content-end pt-2 border-top">
                            <button type="submit" id="btnSimpanSenin" class="btn btn-primary fw-bold px-4 rounded-3 d-flex align-items-center gap-2" style="font-size: 0.85rem;">
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
                        <span id="badgeStatusJumat"
                              class="badge {{ (isset($agendaJumat) && $agendaJumat->is_active) ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle' }} rounded-pill px-3 py-1"
                              style="font-size: 0.72rem;">
                            @if(isset($agendaJumat) && $agendaJumat->is_active)
                                ● Aktif (Terkunci Jam Ke-{{ $agendaJumat->jam_ke }})
                            @else
                                ○ Non-Aktif
                            @endif
                        </span>
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
                                <select name="jam_ke" id="jamKeJumat" class="form-select rounded-3" required style="font-size: 0.875rem;">
                                    @forelse($jamOptionsJumat as $jam)
                                        <option value="{{ $jam->jam_ke }}" {{ old('jam_ke', $agendaJumat->jam_ke ?? $jamOptionsJumat->first()->jam_ke) == $jam->jam_ke ? 'selected' : '' }}>
                                            Jam Ke-{{ $jam->jam_ke }} ({{ substr($jam->jam_mulai, 0, 5) }} - {{ substr($jam->jam_selesai, 0, 5) }})
                                        </option>
                                    @empty
                                        <option value="">— Belum ada slot jam KBM —</option>
                                    @endforelse
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

                        <div id="warningJumat" class="small mb-3 p-2 rounded-3 text-warning-emphasis d-none" style="font-size: 0.78rem; background-color: #fff8e1; border: 1px solid #ffe082;">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i> ⚠️ Ada perubahan yang belum disimpan
                        </div>

                        <div class="d-flex justify-content-end pt-2 border-top">
                            <button type="submit" id="btnSimpanJumat" class="btn btn-info text-white fw-bold px-4 rounded-3 d-flex align-items-center gap-2" style="font-size: 0.85rem;">
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

{{-- ===================== MODAL GENERATE PRESET JAM ===================== --}}
<div class="modal fade" id="modalGeneratePreset" tabindex="-1" aria-labelledby="modalGeneratePresetTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow rounded-4"
             style="max-height: 85vh; display: flex; flex-direction: column; overflow: hidden;">
            <form method="POST" action="{{ route('admin.jam-pelajaran.generate') }}" id="formGeneratePreset"
                  style="display: flex; flex-direction: column; min-height: 0;">
                @csrf
                <div class="modal-header border-0 pb-0" style="flex-shrink: 0;">
                    <h5 class="modal-title fw-bold" id="modalGeneratePresetTitle">
                        <i class="bi bi-lightning-charge-fill text-warning me-2"></i>Generate Preset Jam Pelajaran
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body pt-3" style="overflow-y: auto; min-height: 0;">
                    {{-- Peringatan overwrite --}}
                    <div class="alert alert-warning d-flex align-items-start gap-2 rounded-3"
                         style="font-size: 0.8rem;">
                        <i class="bi bi-exclamation-triangle-fill mt-1"></i>
                        <div>
                            <strong>Perhatian!</strong> Slot KBM akan <strong>diperbarui sesuai urutan Jam Ke-</strong>
                            (id slot dipertahankan sehingga jadwal pelajaran yang sudah di-plot di semester berjalan
                            <strong>tetap utuh</strong>). Jika jumlah JP dikurangi, jadwal pada jam yang dihilangkan akan
                            ikut terhapus (akan ada konfirmasi terlebih dahulu).
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark" style="font-size: 0.85rem;">Kategori Hari</label>
                            <select name="kategori_hari" id="presetKategoriHari" class="form-select rounded-3" required>
                                <option value="Senin-Kamis" {{ $tab === 'Senin-Kamis' ? 'selected' : '' }}>Senin – Kamis</option>
                                <option value="Jumat" {{ $tab === 'Jumat' ? 'selected' : '' }}>Jumat</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold text-dark" style="font-size: 0.85rem;">Durasi per JP (menit)</label>
                            <input type="number" name="durasi_jp" class="form-control rounded-3" min="1" max="120"
                                   value="{{ $tab === 'Jumat' ? 30 : 40 }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold text-dark" style="font-size: 0.85rem;">Jumlah JP (KBM)</label>
                            <input type="number" name="jumlah_jp" class="form-control rounded-3" min="1" max="20"
                                   value="{{ $tab === 'Jumat' ? 9 : 13 }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark" style="font-size: 0.85rem;">Jam Mulai</label>
                            <input type="time" name="jam_mulai" class="form-control rounded-3" step="60" value="07:00" autocomplete="off">
                        </div>
                    </div>

                    <hr class="my-4">

                    {{-- Pengaturan Istirahat (Repeater) --}}
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="fw-bold text-dark mb-0" style="font-size: 0.95rem;">
                            <i class="bi bi-cup-hot-fill text-warning me-1"></i>Pengaturan Istirahat
                        </h6>
                        <button type="button" id="btnTambahIstirahat" class="btn btn-sm btn-outline-warning rounded-3 fw-semibold">
                            <i class="bi bi-plus-lg me-1"></i>Tambah Jam Istirahat
                        </button>
                    </div>
                    <p class="text-muted mb-3" style="font-size: 0.78rem;">
                        Tambahkan istirahat setelah jam KBM tertentu, dengan durasi bebas (mis. 15 atau 30 menit).
                        Bisa 0, 1, 2, atau lebih.
                    </p>

                    <div id="istirahatRows" class="d-flex flex-column gap-2">
                        {{-- Baris istirahat dinamis diisi via JS --}}
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0"
                     style="position: sticky; bottom: 0; background-color: #fff; z-index: 10; border-top: 1px solid #e5e7eb; flex-shrink: 0; margin-top: 1rem;">
                    <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning rounded-3 px-4 fw-semibold text-white">
                        <i class="bi bi-lightning-charge-fill me-1"></i> Generate
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
                        <div class="col-4">
                            <label class="form-label fw-semibold text-dark" style="font-size: 0.875rem;">Jam Mulai</label>
                            <input type="time" name="jam_mulai" id="editJamMulai" class="form-control rounded-3" step="60" autocomplete="off" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label fw-semibold text-dark" style="font-size: 0.875rem;">Jam Selesai</label>
                            <input type="time" name="jam_selesai" id="editJamSelesai" class="form-control rounded-3" step="60" autocomplete="off" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label fw-semibold text-dark" style="font-size: 0.875rem;">Durasi (Menit)</label>
                            <input type="number" name="durasi" id="editDurasi" class="form-control rounded-3" min="1" max="600" step="1" value="40" autocomplete="off" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark" style="font-size: 0.875rem;">Jenis Slot</label>
                        <select name="jenis" id="editJenis" class="form-select rounded-3" required>
                            <option value="kbm">KBM (Kegiatan Belajar Mengajar)</option>
                            <option value="istirahat">Istirahat</option>
                        </select>
                    </div>
                    <div class="form-check border rounded-3 p-3" style="background-color: #fafafa;">
                        <input class="form-check-input" type="checkbox" name="auto_shift" id="autoShift" value="1" checked>
                        <label class="form-check-label fw-semibold text-dark" for="autoShift" style="font-size: 0.85rem; cursor: pointer;">
                            Geser/Sesuaikan jam slot berikutnya secara otomatis
                        </label>
                        <div class="form-text text-muted" style="font-size: 0.76rem;">
                            Jika dicentang, perubahan waktu pada slot ini akan otomatis menggeser jam mulai dan jam
                            selesai slot-slot setelahnya.
                        </div>
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

{{-- ===================== MODAL KONFIRMASI HAPUS SEMUA SLOT ===================== --}}
<div class="modal fade" id="modalHapusSemuaJP" tabindex="-1" aria-labelledby="modalHapusSemuaJPTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <form method="POST" action="{{ route('admin.jam-pelajaran.destroy-all', ['kategori_hari' => $tab]) }}" id="formHapusSemuaJP">
                @csrf
                @method('DELETE')
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-danger" id="modalHapusSemuaJPTitle">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Hapus Semua Slot ({{ $tab }})
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <p class="mb-0 text-dark" style="font-size: 0.875rem;">
                        Apakah Anda yakin ingin menghapus semua slot jam pelajaran untuk hari ini
                        (<strong>{{ $tab }}</strong>)? Tindakan ini tidak dapat dibatalkan.
                    </p>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger rounded-3 px-4 fw-semibold">
                        <i class="bi bi-trash3-fill me-1"></i> Ya, Hapus Semua
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<style>
    @keyframes btnPulse {
        0%   { transform: scale(1); }
        50%  { transform: scale(1.08); }
        100% { transform: scale(1); }
    }
</style>
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

        // ===== Auto-fill "Jam Mulai" pada Modal Tambah berdasar slot terakhir =====
        const btnTambahJam     = document.getElementById('btnTambahJam');
        const tambahKategori   = document.getElementById('tambahKategoriHari');
        const tambahJamMulai   = document.getElementById('tambahJamMulai');

        if (btnTambahJam && tambahKategori && tambahJamMulai) {
            const fallbackMulai = { 'Senin-Kamis': '07:00', 'Jumat': '07:00' };

            function applyAutoMulai() {
                const kategori = tambahKategori.value;
                const value = (kategori === 'Jumat')
                    ? (btnTambahJam.dataset.mulaiJumat || fallbackMulai.Jumat)
                    : (btnTambahJam.dataset.mulaiSenin || fallbackMulai['Senin-Kamis']);
                tambahJamMulai.value = value;
            }

            // Saat modal dibuka
            document.getElementById('modalTambahJam').addEventListener('shown.bs.modal', applyAutoMulai);

            // Saat kategori hari diubah di dalam modal
            tambahKategori.addEventListener('change', applyAutoMulai);
        }

        // ===== Modal Edit: Two-way real-time kalkulasi Durasi <-> Jam Selesai =====
        const editMulai   = document.getElementById('editJamMulai');
        const editSelesai = document.getElementById('editJamSelesai');
        const editDurasi  = document.getElementById('editDurasi');

        function minutesToTime(totalMin) {
            totalMin = ((totalMin % 1440) + 1440) % 1440; // jaga-jaga jika minus/lewat tengah malam
            const hh = String(Math.floor(totalMin / 60)).padStart(2, '0');
            const mm = String(totalMin % 60).padStart(2, '0');
            return hh + ':' + mm;
        }

        function timeToMinutes(val) {
            if (!val) return null;
            const p = val.split(':');
            return parseInt(p[0], 10) * 60 + parseInt(p[1], 10);
        }

        if (editMulai && editSelesai && editDurasi) {
            // Durasi berubah -> hitung ulang Jam Selesai = Jam Mulai + Durasi
            editDurasi.addEventListener('input', function () {
                const mulaiMin = timeToMinutes(editMulai.value);
                const durasi   = parseInt(editDurasi.value, 10);
                if (mulaiMin === null || isNaN(durasi) || durasi < 1) return;
                editSelesai.value = minutesToTime(mulaiMin + durasi);
            });

            // Jam Selesai berubah -> hitung ulang Durasi = selisih menit
            editSelesai.addEventListener('change', function () {
                const mulaiMin   = timeToMinutes(editMulai.value);
                const selesaiMin = timeToMinutes(editSelesai.value);
                if (mulaiMin === null || selesaiMin === null) return;
                const diff = ((selesaiMin - mulaiMin) % 1440 + 1440) % 1440;
                editDurasi.value = diff;
            });

            // Jam Mulai berubah -> ikut hitung ulang durasi dari Jam Selesai yang ada
            editMulai.addEventListener('change', function () {
                const mulaiMin   = timeToMinutes(editMulai.value);
                const selesaiMin = timeToMinutes(editSelesai.value);
                if (mulaiMin === null || selesaiMin === null) return;
                const diff = ((selesaiMin - mulaiMin) % 1440 + 1440) % 1440;
                editDurasi.value = diff;
            });
        }

        // ===== Generate Preset: Repeater Istirahat Dinamis =====
        const presetForm       = document.getElementById('formGeneratePreset');
        const presetKategori   = document.getElementById('presetKategoriHari');
        const btnTambahIstirahat = document.getElementById('btnTambahIstirahat');
        const istirahatRows    = document.getElementById('istirahatRows');
        let istirahatIndex     = 0;

        const presetDefaults = {
            'Senin-Kamis': { durasi: 40, jumlah: 13 },
            'Jumat':       { durasi: 30, jumlah: 9 },
        };

        function applyPresetDefaults() {
            if (!presetKategori) return;
            const d = presetDefaults[presetKategori.value] || presetDefaults['Senin-Kamis'];
            const durasiInput = presetForm.querySelector('[name="durasi_jp"]');
            const jumlahInput = presetForm.querySelector('[name="jumlah_jp"]');
            if (durasiInput && !durasiInput.dataset.touched) durasiInput.value = d.durasi;
            if (jumlahInput && !jumlahInput.dataset.touched) jumlahInput.value = d.jumlah;
        }

        function addIstirahatRow(afterJam, duration) {
            if (!istirahatRows) return;
            istirahatIndex++;

            const jumlahJp = parseInt((presetForm.querySelector('[name="jumlah_jp"]')?.value) || 13, 10);

            let options = '<option value="">— Pilih Jam —</option>';
            for (let j = 1; j <= jumlahJp; j++) {
                options += '<option value="' + j + '"' + (String(afterJam) === String(j) ? ' selected' : '') + '>Setelah Jam Ke-' + j + '</option>';
            }

            const row = document.createElement('div');
            row.className = 'istirahat-row d-flex align-items-end gap-2 p-2 rounded-3 border bg-light-subtle';
            row.dataset.index = istirahatIndex;
            row.innerHTML =
                '<div class="flex-grow-1">' +
                    '<label class="form-label fw-semibold mb-1" style="font-size:0.75rem;">Setelah Jam Ke-</label>' +
                    '<select name="breaks[' + istirahatIndex + '][after_jam]" class="form-select form-select-sm rounded-3">' + options + '</select>' +
                '</div>' +
                '<div style="width:130px;">' +
                    '<label class="form-label fw-semibold mb-1" style="font-size:0.75rem;">Durasi (menit)</label>' +
                    '<input type="number" name="breaks[' + istirahatIndex + '][duration]" class="form-control form-control-sm rounded-3" min="1" max="120" value="' + (duration || 15) + '">' +
                '</div>' +
                '<div>' +
                    '<button type="button" class="btn btn-sm btn-outline-danger rounded-3" title="Hapus Baris">' +
                        '<i class="bi bi-trash3"></i>' +
                    '</button>' +
                '</div>';

            // Hapus baris
            row.querySelector('button').addEventListener('click', function () {
                row.remove();
                // nilai awal selected tidak lagi terdaftar untuk btn tambah; biarkan blank -> kosongkan baris lain? tidak perlu
            });

            istirahatRows.appendChild(row);
        }

        if (presetForm && presetKategori && btnTambahIstirahat && istirahatRows) {
            presetKategori.addEventListener('change', applyPresetDefaults);

            btnTambahIstirahat.addEventListener('click', function () {
                addIstirahatRow('', 15);
            });

            // Saat jumlah JP berubah, perbarui opsi "Setelah Jam Ke-" pada baris yang ada
            const jumlahInput = presetForm.querySelector('[name="jumlah_jp"]');
            if (jumlahInput) {
                jumlahInput.addEventListener('change', function () {
                    jumlahInput.dataset.touched = '1';
                    const newJumlah = parseInt(jumlahInput.value, 10) || 1;
                    istirahatRows.querySelectorAll('.istirahat-row').forEach(function (row) {
                        const sel = row.querySelector('select[name$="[after_jam]"]');
                        const current = sel.value;
                        let opts = '<option value="">— Pilih Jam —</option>';
                        for (let j = 1; j <= newJumlah; j++) {
                            opts += '<option value="' + j + '"' + (String(current) === String(j) ? ' selected' : '') + '>Setelah Jam Ke-' + j + '</option>';
                        }
                        sel.innerHTML = opts;
                    });
                });
            }

            // Saat modal dibuka: atur default sesuai kategori terpilih & kosongkan istirahat
            document.getElementById('modalGeneratePreset').addEventListener('shown.bs.modal', function () {
                presetKategori.value = "{{ $tab }}";
                const durasiInput = presetForm.querySelector('[name="durasi_jp"]');
                const jumlahInput = presetForm.querySelector('[name="jumlah_jp"]');
                if (durasiInput) durasiInput.dataset.touched = '';
                if (jumlahInput) jumlahInput.dataset.touched = '';
                applyPresetDefaults();
                istirahatRows.innerHTML = '';
                istirahatIndex = 0;
            });

            // Cek dampak pengurangan slot sebelum submit: peringatkan jika ada jadwal ter-plot
            presetForm.addEventListener('submit', function (e) {
                e.preventDefault();
                if (!presetForm.reportValidity()) return;

                const kategori = presetKategori.value;
                const jumlahInput = presetForm.querySelector('[name="jumlah_jp"]');
                const jumlah = parseInt(jumlahInput?.value || '0', 10);

                fetch("{{ route('admin.jam-pelajaran.generate-check') }}" +
                    '?kategori_hari=' + encodeURIComponent(kategori) +
                    '&jumlah_jp=' + encodeURIComponent(jumlah))
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        if (data.affected_jam_ke && data.affected_jam_ke.length > 0 && data.plotted_count > 0) {
                            const jamList = data.affected_jam_ke.join(' & ');
                            const semester = data.semester ? ' (' + data.semester + ')' : '';
                            const pesan = 'Slot Jam Ke-' + jamList +
                                ' berisi jadwal pelajaran' + semester +
                                '.\n\nMengurangi slot akan menghapus jadwal di jam tersebut.\n\nLanjutkan Generate Preset?';
                            if (!window.confirm(pesan)) return;
                        }
                        presetForm.submit();
                    })
                    .catch(function () {
                        presetForm.submit();
                    });
            });
        }

        // ===== Badge Status Dinamis & Dirty State: Upacara (Senin) & Pembiasaan (Jumat) =====
        // Config per kartu: badge, toggle, select jam_ke, tombol simpan, elemen peringatan
        const agendaConfigs = {
            Senin: {
                badge:   document.getElementById('badgeStatusSenin'),
                toggle:  document.getElementById('switchAgendaSenin'),
                select:  document.getElementById('jamKeSenin'),
                button:  document.getElementById('btnSimpanSenin'),
                warning: document.getElementById('warningSenin'),
                form:    document.getElementById('formAgendaSenin'),
            },
            Jumat: {
                badge:   document.getElementById('badgeStatusJumat'),
                toggle:  document.getElementById('switchAgendaJumat'),
                select:  document.getElementById('jamKeJumat'),
                button:  document.getElementById('btnSimpanJumat'),
                warning: document.getElementById('warningJumat'),
                form:    document.getElementById('formAgendaJumat'),
            }
        };

        const BADGE_ACTIVE  = 'badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1';
        const BADGE_INACTIVE= 'badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-3 py-1';
        const BADGE_DIRTY   = 'badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-3 py-1';

        function badgeText(cfg) {
            return cfg.toggle.checked
                ? '● Aktif (Terkunci Jam Ke-' + cfg.select.value + ')'
                : '○ Non-Aktif';
        }

        // Tampilkan badge sesuai status + dirty state
        function renderBadge(key) {
            const cfg = agendaConfigs[key];
            if (!cfg || !cfg.badge) return;

            cfg.badge.style.fontSize = '0.72rem';

            if (cfg.dirty) {
                cfg.badge.className = BADGE_DIRTY;
                cfg.badge.textContent = '🟡 Belum Disimpan';
            } else {
                cfg.badge.className = cfg.toggle.checked ? BADGE_ACTIVE : BADGE_INACTIVE;
                cfg.badge.textContent = badgeText(cfg);
            }

            // Peringatan + highlight tombol
            if (cfg.warning) cfg.warning.classList.toggle('d-none', !cfg.dirty);
            if (cfg.button) {
                // Simpan class warna asli tombol sekali saja
                if (!cfg.originalButtonClass) {
                    cfg.originalButtonClass = Array.from(cfg.button.classList)
                        .find(function (c) { return c.indexOf('btn-') === 0 && c !== 'btn-warning'; });
                }
                if (cfg.dirty) {
                    cfg.button.classList.add('btn-warning');
                    if (cfg.originalButtonClass) cfg.button.classList.remove(cfg.originalButtonClass);
                    cfg.button.style.animation = 'btnPulse 1.2s ease-in-out infinite';
                } else {
                    cfg.button.classList.remove('btn-warning');
                    if (cfg.originalButtonClass) cfg.button.classList.add(cfg.originalButtonClass);
                    cfg.button.style.animation = '';
                }
            }
        }

        // Simpan nilai awal (initial state) dan render ulang
        function captureInitial(key) {
            const cfg = agendaConfigs[key];
            if (!cfg || !cfg.toggle || !cfg.select) return;
            cfg.initialToggle = cfg.toggle.checked;
            cfg.initialSelect = cfg.select.value;
            cfg.dirty = false;
            renderBadge(key);
        }

        function setDirty(key, dirty) {
            const cfg = agendaConfigs[key];
            if (!cfg) return;
            cfg.dirty = dirty;
            renderBadge(key);
        }

        // Deteksi perubahan (dirty state) pada dropdown jam & toggle
        function bindAgendaCard(key) {
            const cfg = agendaConfigs[key];
            if (!cfg || !cfg.toggle || !cfg.select) return;

            const checkDirty = function () {
                const isDirty = (cfg.toggle.checked !== cfg.initialToggle) || (cfg.select.value !== cfg.initialSelect);
                setDirty(key, isDirty);
            };

            cfg.toggle.addEventListener('change', checkDirty);
            cfg.select.addEventListener('change', checkDirty);

            // Reset state saat submit form (normal POST/reload) sebelum dikirim
            if (cfg.form) {
                cfg.form.addEventListener('submit', function () {
                    cfg.initialToggle = cfg.toggle.checked;
                    cfg.initialSelect = cfg.select.value;
                    cfg.dirty = false;
                    renderBadge(key);
                });
            }
        }

        // Inisialisasi: simpan nilai awal untuk kedua kartu
        Object.keys(agendaConfigs).forEach(function (key) {
            captureInitial(key);
            bindAgendaCard(key);
        });

        // ===== Pengaturan Jam Pulang: Badge Sync + Dirty State =====
        const jpForm    = document.getElementById('formJamPulang');
        const jpButton  = document.getElementById('btnSimpanJamPulang');
        const jpWarning = document.getElementById('warningJamPulang');
        const jpOriginalButtonStyle = {};

        function renderJamPulangBadges() {
            document.querySelectorAll('.jam-pulang-select').forEach(function (select) {
                const kategori = select.dataset.kategori;
                const tingkat  = select.dataset.tingkat;
                const badge = document.querySelector('.jam-pulang-badge[data-kategori="' + kategori + '"][data-tingkat="' + tingkat + '"]');
                if (!badge) return;

                const val = select.value;
                badge.innerHTML = val
                    ? '<span class="badge text-bg-danger rounded-pill">Batas: Jam ' + val + '</span>'
                    : '<span class="badge text-bg-info rounded-pill">Semua Slot</span>';
            });
        }

        function updateJamPulangDirty() {
            let dirty = false;
            document.querySelectorAll('.jam-pulang-select').forEach(function (select) {
                if (select.value !== select.dataset.initial) dirty = true;
            });

            if (jpWarning) jpWarning.classList.toggle('d-none', !dirty);
            if (jpButton) {
                if (dirty) {
                    if (jpOriginalButtonStyle.bg === undefined) {
                        jpOriginalButtonStyle.bg = jpButton.style.background;
                        jpOriginalButtonStyle.border = jpButton.style.borderColor;
                    }
                    jpButton.style.background = '#f59e0b';
                    jpButton.style.borderColor = '#d97706';
                    jpButton.style.animation = 'btnPulse 1.2s ease-in-out infinite';
                } else {
                    jpButton.style.background = jpOriginalButtonStyle.bg === undefined ? '#f97316' : jpOriginalButtonStyle.bg;
                    jpButton.style.borderColor = jpOriginalButtonStyle.border === undefined ? '#f97316' : jpOriginalButtonStyle.border;
                    jpButton.style.animation = '';
                }
            }
        }

        function resetJamPulangDirty() {
            document.querySelectorAll('.jam-pulang-select').forEach(function (select) {
                select.dataset.initial = select.value;
            });
            updateJamPulangDirty();
        }

        if (jpForm) {
            document.querySelectorAll('.jam-pulang-select').forEach(function (select) {
                select.addEventListener('change', function () {
                    renderJamPulangBadges();
                    updateJamPulangDirty();
                });
                // Simpan nilai awal (initial state) dari DB
                select.dataset.initial = select.value;
            });

            // Reset indikator saat submit (form reload/POST)
            jpForm.addEventListener('submit', function () {
                resetJamPulangDirty();
            });

            renderJamPulangBadges();
        }
    });

    function openEditModal(id, kategoriHari, jamMulai, jamSelesai, jenis, durasi) {
        const routeBase = "{{ url('admin/jam-pelajaran') }}";
        document.getElementById('formEditJam').action = routeBase + '/' + id;

        document.getElementById('editKategoriHari').value = kategoriHari;
        document.getElementById('editJamMulai').value     = jamMulai;
        document.getElementById('editJamSelesai').value   = jamSelesai;
        document.getElementById('editJenis').value        = jenis;
        if (document.getElementById('editDurasi')) {
            document.getElementById('editDurasi').value = durasi !== undefined && durasi !== '' ? durasi : '40';
        }

        const modal = new bootstrap.Modal(document.getElementById('modalEditJam'));
        modal.show();
    }

</script>
@endpush
