@extends('layouts.app')

@section('title', 'Approval Dispensasi - Waka Kesiswaan')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="font-weight: 900; font-size: 1.75rem; letter-spacing: -0.02em;">
                Approval Dispensasi
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                Riwayat surat dispensasi siswa — tandatangani, tinjau, dan kelola surat sebagai Waka Kesiswaan.
            </p>
        </div>
        <span class="text-muted small"><i class="bi bi-calendar3 me-1"></i>{{ now()->translatedFormat('l, d F Y') }}</span>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4 d-flex align-items-center gap-2" role="alert" style="background: #ecfdf5; color: #065f46; font-size: 0.9rem;">
            <i class="bi bi-check-circle-fill text-success fs-5"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4 d-flex align-items-center gap-2" role="alert" style="background: #fef2f2; color: #991b1b; font-size: 0.9rem;">
            <i class="bi bi-exclamation-triangle-fill text-danger fs-5"></i>
            <div>{{ $errors->first() }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @php
        $tabs = [
            'menunggu'  => ['label' => 'Menunggu TTD',  'icon' => 'bi-hourglass-split',          'color' => 'warning'],
            'disetujui' => ['label' => 'Disetujui',     'icon' => 'bi-check2-circle',            'color' => 'success'],
            'ditolak'   => ['label' => 'Ditolak',       'icon' => 'bi-x-circle',                 'color' => 'danger'],
            'semua'     => ['label' => 'Semua',         'icon' => 'bi-card-list',                'color' => 'primary'],
        ];
    @endphp

    {{-- Sistem Tab / Filter Status --}}
    <div class="card border-0 rounded-4 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <div class="d-flex flex-wrap gap-2">
                @foreach($tabs as $key => $tab)
                    @php
                        $isActive = ($filter === $key);
                        $countValue = $counts[$key] ?? 0;
                    @endphp
                    <a href="{{ route('waka-kesiswaan.dispensasi.approval.index', ['filter' => $key]) }}"
                       class="btn text-nowrap d-inline-flex align-items-center gap-2 rounded-3 fw-semibold {{ $isActive ? 'btn-' . $tab['color'] . ' text-white' : 'btn-light border text-dark' }}"
                       style="font-size: 0.85rem;">
                        <i class="bi {{ $tab['icon'] }}"></i>
                        <span>{{ $tab['label'] }}</span>
                        <span class="badge {{ $isActive ? 'bg-white bg-opacity-25 text-white' : 'bg-secondary-subtle text-secondary' }} rounded-pill px-2" style="font-size: 0.7rem;">
                            {{ $countValue }}
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    <div class="table-card-custom mb-4">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h5 class="fw-bold text-dark mb-0">
                {{ $tabs[$filter]['label'] ?? 'Surat Dispensasi' }}
            </h5>
            <span class="text-muted small">{{ $daftar->count() }} surat</span>
        </div>

        <div class="table-responsive w-full overflow-x-auto">
            <table class="table table-custom align-middle mb-0 min-w-full">
                <thead>
                    <tr>
                        <th>TANGGAL</th>
                        <th>SISWA</th>
                        <th>JAM</th>
                        <th>ALASAN</th>
                        <th>STATUS</th>
                        <th class="text-end">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($daftar as $dispen)
                        <tr>
                            <td class="fw-semibold text-dark text-nowrap">
                                {{ $dispen->tanggal?->translatedFormat('d/m/Y') ?? '-' }}
                                <div class="text-muted small fw-normal">{{ $dispen->nomor_surat }}</div>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">{{ $dispen->siswa?->nama ?? '-' }}</div>
                                <div class="text-muted small">{{ $dispen->siswa?->kelas?->nama_kelas ?? '-' }}</div>
                            </td>
                            <td class="fw-semibold text-dark">{{ $dispen->jam_ke_label ?? '-' }}</td>
                            <td style="max-width: 240px;">
                                <div class="text-wrap">{{ \Illuminate\Support\Str::limit($dispen->alasan ?? '-', 100) }}</div>
                            </td>
                            <td>
                                <span class="badge {{ $dispen->kesiswaan_status_badge }} rounded-pill px-2 py-1">
                                    {{ $dispen->kesiswaan_status_label }}
                                </span>
                            </td>
                            <td class="text-end">
                                @if($dispen->isMenungguTtdWaka())
                                    <button type="button" class="btn btn-sm btn-primary rounded-3" data-bs-toggle="modal" data-bs-target="#approvalModal{{ $dispen->id }}">
                                        <i class="bi bi-pen me-1"></i>Tanda Tangan
                                    </button>
                                @else
                                    <div class="d-flex justify-content-end flex-wrap gap-1">
                                        <a href="{{ route('piket.dispensasi.surat', $dispen->id) }}" target="_blank"
                                           class="btn btn-sm btn-outline-primary rounded-3">
                                            <i class="bi bi-eye me-1"></i>Lihat Surat
                                        </a>
                                        <a href="{{ route('piket.dispensasi.surat', $dispen->id) }}?print=1" target="_blank"
                                           class="btn btn-sm btn-outline-secondary rounded-3">
                                            <i class="bi bi-printer me-1"></i>Cetak PDF
                                        </a>
                                        @if($dispen->has_ttd_waka)
                                            <button type="button" class="btn btn-sm btn-outline-success rounded-3"
                                                    data-bs-toggle="modal" data-bs-target="#ttdDetailModal{{ $dispen->id }}">
                                                <i class="bi bi-pen-fill me-1"></i>Detail TTD
                                            </button>
                                        @endif
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                <i class="bi bi-inbox-fill me-2"></i>
                                @if($filter === 'menunggu')
                                    Tidak ada dispensasi yang menunggu tanda tangan Waka Kesiswaan.
                                @elseif($filter === 'disetujui')
                                    Belum ada surat dispensasi yang ditandatangani.
                                @elseif($filter === 'ditolak')
                                    Belum ada surat dispensasi yang ditolak.
                                @else
                                    Belum ada surat dispensasi.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ================= MODAL TANDA TANGAN (per surat yang masih menunggu) ================= --}}
@foreach($daftar as $dispen)
    @if($dispen->isMenungguTtdWaka())
        <div class="modal fade" id="approvalModal{{ $dispen->id }}" tabindex="-1" aria-labelledby="approvalModalLabel{{ $dispen->id }}" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered">
                <div class="modal-content rounded-4 border-0 shadow-lg">
                    <div class="modal-header border-0 pb-0">
                        <div>
                            <h5 class="modal-title fw-bold text-dark" id="approvalModalLabel{{ $dispen->id }}">Persetujuan Dispensasi</h5>
                            <div class="text-muted small">{{ $dispen->siswa?->nama ?? '-' }} • {{ $dispen->tanggal?->translatedFormat('d F Y') ?? '-' }}</div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="row g-4">
                            <div class="col-lg-6">
                                <div class="card border-0 bg-light-subtle rounded-4 h-100">
                                    <div class="card-body">
                                        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-card-list me-2"></i>Ringkasan Dispensasi</h6>
                                        <dl class="row mb-0 small">
                                            <dt class="col-sm-5 text-muted">Nomor Surat</dt>
                                            <dd class="col-sm-7 fw-semibold text-dark">{{ $dispen->nomor_surat }}</dd>

                                            <dt class="col-sm-5 text-muted">Nama Siswa</dt>
                                            <dd class="col-sm-7 fw-semibold text-dark">{{ $dispen->siswa?->nama ?? '-' }}</dd>

                                            <dt class="col-sm-5 text-muted">Kelas</dt>
                                            <dd class="col-sm-7 fw-semibold text-dark">{{ $dispen->siswa?->kelas?->nama_kelas ?? '-' }}</dd>

                                            <dt class="col-sm-5 text-muted">Jam</dt>
                                            <dd class="col-sm-7 fw-semibold text-dark">{{ $dispen->jam_ke_label ?? '-' }}</dd>

                                            <dt class="col-sm-5 text-muted">Alasan</dt>
                                            <dd class="col-sm-7 fw-semibold text-dark">{{ $dispen->alasan ?? '-' }}</dd>

                                            <dt class="col-sm-5 text-muted">TTD Piket</dt>
                                            <dd class="col-sm-7">
                                                @if($dispen->has_ttd_guru)
                                                    <img src="{{ $dispen->ttd_guru_url }}" alt="TTD Guru Piket" style="max-height: 70px; max-width: 180px; border: 1px solid #e5e7eb; border-radius: 12px; background: white; padding: 6px;">
                                                @else
                                                    <span class="text-muted">Belum tersedia</span>
                                                @endif
                                            </dd>

                                            <dt class="col-sm-5 text-muted">TTD Siswa</dt>
                                            <dd class="col-sm-7">
                                                @if($dispen->has_ttd)
                                                    <img src="{{ $dispen->ttd_url }}" alt="TTD Siswa" style="max-height: 70px; max-width: 180px; border: 1px solid #e5e7eb; border-radius: 12px; background: white; padding: 6px;">
                                                @else
                                                    <span class="text-muted">Belum tersedia</span>
                                                @endif
                                            </dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="card border-0 bg-white rounded-4 shadow-sm h-100">
                                    <div class="card-body">
                                        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-pen-fill me-2"></i>Tanda Tangan Waka Kesiswaan</h6>
                                        <p class="text-muted small mb-3">Gambar tanda tangan digital Anda (<strong>{{ auth()->user()->nama }}</strong>) di area canvas di bawah ini, lalu simpan. Anda bertindak sebagai Waka Kesiswaan sekarang.</p>

                                        <div class="border rounded-4 p-2 bg-light-subtle mb-3" style="min-height: 180px;">
                                            <canvas id="ttdWaka{{ $dispen->id }}" width="520" height="180" class="w-100" style="border: 1px solid #dfe5ef; border-radius: 12px; background: #fff; touch-action: none; cursor: crosshair;"></canvas>
                                        </div>

                                        <div class="d-flex flex-wrap gap-2 mb-3">
                                            <button type="button" class="btn btn-outline-secondary btn-sm rounded-3" data-clear-canvas="ttdWaka{{ $dispen->id }}">
                                                <i class="bi bi-eraser me-1"></i>Bersihkan Canvas
                                            </button>
                                        </div>

                                        <form action="{{ route('waka-kesiswaan.dispensasi.approval.store', $dispen->id) }}" method="POST" id="formApproval{{ $dispen->id }}">
                                            @csrf
                                            <input type="hidden" name="ttd_waka" id="ttdWakaInput{{ $dispen->id }}" value="">
                                            <button type="submit" class="btn btn-success w-100 rounded-3 fw-semibold" data-submit-approval>
                                                <i class="bi bi-check-circle me-1"></i>Setujui & Simpan TTD
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endforeach

{{-- ================= MODAL DETAIL TTD (untuk surat yang sudah di-TTD) ================= --}}
@foreach($daftar as $dispen)
    @if($dispen->has_ttd_waka)
        @php
            $signer = $dispen->wakaKesiswaan
                ?? (($dispen->approver && $dispen->approver->isWakaKesiswaan()) ? $dispen->approver : null);
        @endphp
        <div class="modal fade" id="ttdDetailModal{{ $dispen->id }}" tabindex="-1" aria-labelledby="ttdDetailLabel{{ $dispen->id }}" aria-hidden="true">
            <div class="modal-dialog modal-md modal-dialog-centered">
                <div class="modal-content rounded-4 border-0 shadow-lg">
                    <div class="modal-header border-0 pb-0">
                        <div>
                            <h5 class="modal-title fw-bold text-dark" id="ttdDetailLabel{{ $dispen->id }}">Detail Tanda Tangan</h5>
                            <div class="text-muted small">{{ $dispen->siswa?->nama ?? '-' }} • {{ $dispen->tanggal?->translatedFormat('d F Y') ?? '-' }}</div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4 text-center">
                        <div class="d-inline-block bg-white rounded-3 p-3 border mb-3">
                            <img src="{{ $dispen->ttd_waka_url }}" alt="TTD Waka Kesiswaan" style="max-height: 130px; max-width: 100%; object-fit: contain;">
                        </div>

                        <dl class="row text-start mb-0 small">
                            <dt class="col-sm-5 text-muted">Penandatangan</dt>
                            <dd class="col-sm-7 fw-semibold text-dark">{{ $signer?->nama ?? '-' }}</dd>

                            @if($signer && trim((string) $signer->nip))
                                <dt class="col-sm-5 text-muted">NIP</dt>
                                <dd class="col-sm-7 fw-semibold text-dark">{{ $signer->nip }}</dd>
                            @endif

                            <dt class="col-sm-5 text-muted">Jabatan</dt>
                            <dd class="col-sm-7 fw-semibold text-dark">Waka Kesiswaan</dd>

                            <dt class="col-sm-5 text-muted">Ditandatangani</dt>
                            <dd class="col-sm-7 fw-semibold text-dark">
                                {{ $dispen->approved_at?->translatedFormat('d/m/Y H:i') ?? '-' }}
                            </dd>

                            <dt class="col-sm-5 text-muted">Nomor Surat</dt>
                            <dd class="col-sm-7 fw-semibold text-dark">{{ $dispen->nomor_surat }}</dd>
                        </dl>

                        <div class="mt-4 d-grid gap-2">
                            <a href="{{ route('piket.dispensasi.surat', $dispen->id) }}" target="_blank" class="btn btn-primary rounded-3 fw-semibold">
                                <i class="bi bi-eye me-1"></i>Lihat Surat Lengkap
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endforeach

<script>
    function initSignatureCanvas(canvasId, inputId) {
        const canvas = document.getElementById(canvasId);
        const input = document.getElementById(inputId);
        if (!canvas || !input) return;

        const ctx = canvas.getContext('2d');
        let drawing = false;
        let lastX = 0;
        let lastY = 0;

        const resizeCanvas = () => {
            const ratio = window.devicePixelRatio || 1;
            const rect = canvas.getBoundingClientRect();
            canvas.width = rect.width * ratio;
            canvas.height = rect.height * ratio;
            ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
            ctx.lineWidth = 2.4;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.strokeStyle = '#111827';
        };

        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);

        const getPos = (event) => {
            const rect = canvas.getBoundingClientRect();
            const clientX = event.touches ? event.touches[0].clientX : event.clientX;
            const clientY = event.touches ? event.touches[0].clientY : event.clientY;
            return {
                x: clientX - rect.left,
                y: clientY - rect.top,
            };
        };

        const startDrawing = (event) => {
            drawing = true;
            const pos = getPos(event);
            lastX = pos.x;
            lastY = pos.y;
            ctx.beginPath();
            ctx.moveTo(lastX, lastY);
        };

        const draw = (event) => {
            if (!drawing) return;
            const pos = getPos(event);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();
            lastX = pos.x;
            lastY = pos.y;
        };

        const stopDrawing = () => {
            drawing = false;
            input.value = canvas.toDataURL('image/png');
        };

        canvas.addEventListener('pointerdown', startDrawing);
        canvas.addEventListener('pointermove', draw);
        canvas.addEventListener('pointerup', stopDrawing);
        canvas.addEventListener('pointerleave', stopDrawing);
        canvas.addEventListener('pointercancel', stopDrawing);

        canvas.addEventListener('touchstart', (event) => {
            event.preventDefault();
            startDrawing(event);
        }, { passive: false });
        canvas.addEventListener('touchmove', (event) => {
            event.preventDefault();
            draw(event);
        }, { passive: false });
        canvas.addEventListener('touchend', stopDrawing);
        canvas.addEventListener('touchcancel', stopDrawing);
    }

    document.querySelectorAll('[data-clear-canvas]').forEach((button) => {
        button.addEventListener('click', function () {
            const canvasId = this.getAttribute('data-clear-canvas');
            const canvas = document.getElementById(canvasId);
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            const input = document.getElementById(canvasId.replace('ttdWaka', 'ttdWakaInput'));
            if (input) input.value = '';
        });
    });

    document.querySelectorAll('[data-submit-approval]').forEach((button) => {
        button.addEventListener('click', function (event) {
            const form = this.closest('form');
            if (!form) return;
            const canvasId = form.querySelector('input[type="hidden"]').id.replace('Input', '');
            const hiddenInput = form.querySelector('input[type="hidden"]');
            const canvas = document.getElementById(canvasId);
            if (!canvas || !hiddenInput || !hiddenInput.value) {
                event.preventDefault();
                alert('Silakan tanda tangan Waka Kesiswaan terlebih dahulu.');
            }
        });
    });

    document.querySelectorAll('canvas[id^="ttdWaka"]').forEach((canvas) => {
        const id = canvas.id;
        const inputId = id.replace('ttdWaka', 'ttdWakaInput');
        initSignatureCanvas(id, inputId);
    });
</script>
@endsection