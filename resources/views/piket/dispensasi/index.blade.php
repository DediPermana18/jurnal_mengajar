@extends('layouts.app')

@section('title', 'Dispensasi Siswa - Guru Piket')

@section('content')
<div class="container-fluid px-0">

    {{-- Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 800; font-size: 1.75rem;">
                Dispensasi Siswa
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                Kelola surat dispensasi siswa yang diterbitkan oleh Guru Piket.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <form method="GET" action="{{ route('piket.dispensasi.index') }}" class="d-flex align-items-center gap-2">
                <label class="text-muted fw-semibold small mb-0"><i class="bi bi-calendar3 me-1"></i>Tanggal:</label>
                <input type="date"
                       name="tanggal"
                       value="{{ $tanggal }}"
                       max="{{ $today }}"
                       class="form-control form-control-sm rounded-3"
                       style="width: auto;"
                       onchange="this.form.submit()">
            </form>
            <a href="{{ route('piket.dispensasi.pengajuan') }}" class="btn btn-outline-warning rounded-3 px-3 py-2 fw-semibold shadow-sm">
                <i class="bi bi-check2-square me-1"></i> Approval Dispen
                @if($totalPending > 0)
                    <span class="badge bg-warning text-dark ms-1 rounded-pill">{{ $totalPending }}</span>
                @endif
            </a>
            <a href="{{ route('piket.dispensasi.create') }}" class="btn btn-primary rounded-3 px-3 py-2 fw-semibold shadow-sm">
                <i class="bi bi-plus-lg me-1"></i> Buat Dispen
            </a>
        </div>
    </div>

    {{-- Alert --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4 d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-check-circle-fill text-success fs-5"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Info Bar --}}
    <div class="alert border-0 rounded-4 mb-4 py-3 px-4 d-flex align-items-center gap-3 {{ $tanggal === $today ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}" role="alert">
        <i class="bi {{ $tanggal === $today ? 'bi-calendar2-week-fill' : 'bi-calendar2-x-fill' }} fs-5"></i>
        <div>
            <strong>{{ \Carbon\Carbon::parse($tanggal)->translatedFormat('l, d F Y') }}</strong><br>
            <span class="small">Total <strong>{{ $totalHariIni }}</strong> surat dispensasi pada tanggal ini.</span>
        </div>
    </div>

    {{-- Tabel --}}
    <div class="table-card-custom mb-4">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h5 class="fw-bold text-dark mb-0">Daftar Dispensasi</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th>NO</th>
                        <th>SISWA</th>
                        <th>KELAS</th>
                        <th>JAM KE</th>
                        <th>ALASAN</th>
                        <th>STATUS</th>
                        <th>BUKTI SURAT</th>
                        <th class="text-end">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dataDispensasi as $dispen)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>
                                <div class="fw-semibold text-dark">{{ $dispen->siswa->nama ?? '-' }}</div>
                                <div class="text-muted small">NISN: {{ $dispen->siswa->nisn ?: '-' }}</div>
                            </td>
                            <td>{{ $dispen->siswa?->kelas?->nama ?? '-' }}</td>
                            <td>
                                <span class="badge bg-light text-dark border rounded-3 px-2 py-1">{{ $dispen->jam_ke_label }}</span>
                            </td>
                            <td style="max-width: 260px;"><span class="text-wrap">{{ $dispen->alasan }}</span></td>
                            <td><span class="badge {{ $dispen->status_badge }} rounded-pill px-2 py-2">{{ $dispen->status_label }}</span></td>
                            <td>
                                @if(!empty($dispen->bukti_surat))
                                    @php $buktiAda = \Illuminate\Support\Facades\Storage::disk('public')->exists($dispen->bukti_surat); @endphp
                                    @if($buktiAda)
                                        <div class="d-flex align-items-center gap-2">
                                            @if($dispen->bukti_type === 'image')
                                                <img src="{{ asset('storage/' . $dispen->bukti_surat) }}" alt="Foto Bukti Surat"
                                                     class="rounded-3 border" style="height: 38px; width: 60px; object-fit: cover; background: #fff;">
                                            @else
                                                <span class="badge bg-danger-subtle text-danger border rounded-3"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</span>
                                            @endif
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-info rounded-3"
                                                    title="Lihat Foto Bukti Surat"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modalBuktiSurat"
                                                    data-nama-siswa="{{ addslashes($dispen->siswa->nama ?? 'Siswa') }}"
                                                    data-bukti-src="{{ asset('storage/' . $dispen->bukti_surat) }}"
                                                    data-bukti-type="{{ $dispen->bukti_type }}">
                                                <i class="bi bi-eye me-1"></i>Lihat
                                            </button>
                                        </div>
                                    @else
                                        <span class="text-danger small">Foto bukti tidak tersedia</span>
                                    @endif
                                @else
                                    <span class="text-muted small">Tidak Ada</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex flex-wrap justify-content-end align-items-center gap-1">
                                <a href="{{ route('piket.dispensasi.surat', $dispen->id) }}" target="_blank"
                                       class="btn btn-sm btn-outline-dark rounded-3"
                                       title="Lihat Surat Dispensasi & TTD Digital (tab baru)">
                                    <i class="bi bi-file-earmark-text me-1"></i>Lihat Surat / TTD
                                </a>
                                @if($dispen->isApproved())
                                    <a href="{{ route('piket.dispensasi.surat', $dispen->id) }}" target="_blank"
                                       class="btn btn-sm btn-outline-primary rounded-3" title="Cetak Surat Dispen Resmi">
                                        <i class="bi bi-printer me-1"></i>Cetak Surat
                                    </a>
                                @endif
                                @if($dispen->approval_url)
                                    <a href="#" class="btn btn-sm btn-wa rounded-3"
                                       title="Kirim Link Approval langsung ke WhatsApp Waka Kesiswaan"
                                       data-wa-approval="{{ $dispen->approval_url }}"
                                       data-nama="{{ addslashes($dispen->siswa->nama ?? 'Siswa') }}"
                                       data-alasan="{{ addslashes($dispen->alasan) }}"
                                       data-jam="{{ $dispen->jam_ke_label }}">
                                        <i class="bi bi-whatsapp me-1"></i>Kirim WA ke Waka
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-success rounded-3"
                                            title="Salin Link Approval ke WhatsApp untuk diteruskan ke Waka Kesiswaan"
                                            data-salin-link="{{ $dispen->approval_url }}">
                                        <i class="bi bi-clipboard me-1"></i>Salin Link WA
                                    </button>
                                    @php $qrSvgApproval = \App\Support\QrCodeHelper::svg($dispen->approval_url, 6); @endphp
                                    <button type="button" class="btn btn-sm btn-outline-dark rounded-3"
                                            title="Tampilkan QR Approval untuk dipindai Waka Kesiswaan"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalApprovalQr"
                                            data-qr-target="qr-approval-{{ $dispen->id }}"
                                            data-link="{{ $dispen->approval_url }}">
                                        <i class="bi bi-qr-code me-1"></i>QR Approval
                                    </button>
                                    <div class="d-none" id="qr-approval-{{ $dispen->id }}">{!! $qrSvgApproval !!}</div>
                                @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                                Belum ada surat dispensasi pada tanggal ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

{{-- Modal Lihat Foto Bukti Surat --}}
<div class="modal fade" id="modalBuktiSurat" tabindex="-1" aria-labelledby="modalBuktiSuratLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark" id="modalBuktiSuratLabel">
                    <i class="bi bi-camera text-primary me-1"></i> Foto Bukti Surat Dispen
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-3">
                <div class="text-muted small mb-3">
                    Siswa: <strong id="modalNamaSiswa" class="text-dark">-</strong>
                </div>
                <div id="modalBuktiContent" class="text-center bg-light-subtle rounded-3 p-2" style="min-height: 220px;">
                    <div class="text-muted"><i class="bi bi-hourglass-split me-1"></i> Memuat...</div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

{{-- Modal QR Approval --}}
<div class="modal fade" id="modalApprovalQr" tabindex="-1" aria-labelledby="modalApprovalQrLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark" id="modalApprovalQrLabel">
                    <i class="bi bi-qr-code text-success me-1"></i> QR Approval Dispen
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-3 text-center">
                <p class="text-muted small mb-3">
                    Guru Piket dapat memindai, atau mengirimkan tautan di bawah ini ke
                    Waka Kesiswaan / Penyetuju via WhatsApp.
                </p>
                <div class="qr-approval-preview d-inline-block border rounded-3 p-3 bg-white shadow-sm"></div>
                <div id="modalApprovalQrLink" class="text-break text-muted small mt-3 mb-0"></div>
            </div>
            <div class="modal-footer border-0 justify-content-center">
                <a href="#" id="btnWaApproval" target="_blank" class="btn btn-success rounded-3 fw-semibold">
                    <i class="bi bi-whatsapp me-1"></i> Kirim via WhatsApp
                </a>
                <button type="button" id="btnSalinQrLink" class="btn btn-outline-success rounded-3">
                    <i class="bi bi-clipboard me-1"></i> Salin Link
                </button>
                <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<style>
    .qr-approval-preview svg {
        width: 220px;
        height: 220px;
        display: block;
    }

    /* Tombol hijau khas WhatsApp */
    .btn-wa {
        background-color: #25D366 !important;
        border-color: #25D366 !important;
        color: #fff !important;
    }
    .btn-wa:hover,
    .btn-wa:focus,
    .btn-wa:active {
        background-color: #1EBE5D !important;
        border-color: #1EBE5D !important;
        color: #fff !important;
    }
</style>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const noWaWaka = @json($noWaWaka);

        // ===== Kirim WA Approval (wa.me) =====
        document.querySelectorAll('[data-wa-approval]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const link   = btn.dataset.waApproval || '';
                const nama   = btn.dataset.nama || 'Siswa';
                const alasan = btn.dataset.alasan || '';
                const jam    = btn.dataset.jam || '';

                const teks = 'Halo Bapak/Ibu Waka Kesiswaan, berikut adalah pengajuan Dispensasi Siswa:\n'
                    + '- Nama: ' + nama + '\n'
                    + '- Alasan: ' + alasan + '\n'
                    + '- Jam Ke: ' + jam + '\n'
                    + '\n'
                    + 'Mohon untuk menyetujui dan menandatangani surat dispensasi melalui link berikut:\n'
                    + link + '\n'
                    + '\n'
                    + 'Terima kasih.';

                const base = noWaWaka ? ('https://wa.me/' + noWaWaka) : 'https://wa.me/';
                const url  = base + '?text=' + encodeURIComponent(teks);
                window.open(url, '_blank');
            });
        });

        const modalContent = document.getElementById('modalBuktiContent');
        const modalNama    = document.getElementById('modalNamaSiswa');

        document.querySelectorAll('[data-bukti-src]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const src  = btn.dataset.buktiSrc;
                const type = btn.dataset.buktiType;
                const nama = btn.dataset.namaSiswa || 'Siswa';

                modalNama.textContent = nama;
                const fallbackHtml =
                    '<div class="text-muted py-4"><i class="bi bi-image fs-1 d-block mb-2 opacity-50"></i>Foto bukti tidak tersedia</div>';

                if (type === 'pdf') {
                    modalContent.innerHTML =
                        '<iframe src="' + src + '#toolbar=0" class="w-100 rounded-3" style="height: 65vh; border: 0; background: #fff;"></iframe>';
                } else {
                    modalContent.innerHTML = '';
                    const img = document.createElement('img');
                    img.src = src;
                    img.alt = 'Foto Bukti Surat Dispen';
                    img.className = 'img-fluid rounded-3';
                    img.style.maxHeight = '65vh';
                    img.style.objectFit = 'contain';
                    img.onerror = function () {
                        modalContent.innerHTML = fallbackHtml;
                    };
                    modalContent.appendChild(img);
                }
            });
        });

        // ===== Salin Link Approval WA =====
        function flashButton(btn, msg) {
            const old = btn.innerHTML;
            btn.innerHTML = msg;
            setTimeout(function () { btn.innerHTML = old; }, 1600);
        }

        document.querySelectorAll('[data-salin-link]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const link = btn.dataset.salinLink;
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(link).then(function () {
                        flashButton(btn, '<i class="bi bi-check-lg me-1"></i>Link Disalin!');
                    }, function () {
                        window.prompt('Salin link approval ini:', link);
                    });
                } else {
                    window.prompt('Salin link approval ini:', link);
                }
            });
        });

        // ===== QR Approval Modal =====
        const qrPreview   = document.querySelector('#modalApprovalQr .qr-approval-preview');
        const qrLinkText  = document.getElementById('modalApprovalQrLink');
        const btnWaApproval = document.getElementById('btnWaApproval');
        const btnSalinQrLink = document.getElementById('btnSalinQrLink');

        document.querySelectorAll('[data-qr-target]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const qrEl  = document.getElementById(btn.dataset.qrTarget);
                const link  = btn.dataset.link;
                const waText = 'Persetujuan Surat Dispen Digital — mohon diproses: ' + link;

                qrPreview.innerHTML = qrEl ? qrEl.innerHTML : '';
                qrLinkText.textContent = link;
                btnWaApproval.href = 'https://wa.me/?text=' + encodeURIComponent(waText);
                btnSalinQrLink.dataset.link = link;
            });
        });

        if (btnSalinQrLink) {
            btnSalinQrLink.addEventListener('click', function () {
                const link = this.dataset.link || '';
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(link).then(function () {
                        flashButton(btnSalinQrLink, '<i class="bi bi-check-lg me-1"></i>Disalin!');
                    }, function () {
                        window.prompt('Salin link approval ini:', link);
                    });
                } else {
                    window.prompt('Salin link approval ini:', link);
                }
            });
        }
    });
</script>
@endpush