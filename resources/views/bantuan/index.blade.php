@extends('layouts.app')

@section('title', 'Pusat Bantuan & Panduan - WebJournal')

@section('content')
<div class="container-fluid px-0">

    {{-- Header --}}
    <div class="text-center mb-4 pb-2">
        <div class="d-inline-flex align-items-center justify-content-center bg-white shadow-sm border rounded-4 mb-3" style="width: 74px; height: 74px;">
            <i class="bi bi-life-preserver text-primary" style="font-size: 2.1rem;"></i>
        </div>
        <h2 class="fw-black text-dark mb-2" style="font-weight: 900; font-size: 1.9rem; letter-spacing: -0.02em;">
            Pusat Bantuan & Panduan Penggunaan WebJournal
        </h2>
        <p class="text-muted mx-auto" style="max-width: 720px; font-size: 0.95rem;">
            Panduan lengkap penggunaan sistem jurnal digital & perizinan — mulai dari mengisi jurnal mengajar,
            mengajukan & memverifikasi izin, hingga menyetujui izin melalui link WhatsApp dan tanda tangan digital.
        </p>
    </div>

    {{-- Accordion Panduan --}}
    <div class="row g-4 mb-4">
        <div class="col-12 col-lg-8">

            <div class="table-card-custom h-100">
                <h5 class="fw-bold text-dark mb-3" style="font-size: 1.05rem;"><i class="bi bi-journal-bookmark-fill me-2 text-primary"></i>Panduan Penggunaan</h5>

                <div class="accordion accordion-flush" id="accPanduan">

                    {{-- 1. Panduan Guru --}}
                    <div class="accordion-item border-0 mb-3" x-data="{ open: true }">
                        <h2 class="accordion-header">
                            <button class="accordion-button rounded-3 shadow-sm fw-bold text-dark" 
                                    type="button" 
                                    :class="{ 'collapsed': !open }"
                                    :aria-expanded="open"
                                    @click.prevent="open = !open" 
                                    style="font-size: 1rem;">
                                <i class="bi bi-person-video3 me-2 text-primary" style="font-size: 1.15rem;"></i> Panduan Guru (Guru Mapel)
                            </button>
                        </h2>
                        <div x-show="open" 
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 transform -translate-y-2"
                             x-transition:enter-end="opacity-100 transform translate-y-0"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 transform translate-y-0"
                             x-transition:leave-end="opacity-0 transform -translate-y-2"
                             id="panduanGuru">
                            <div class="accordion-body px-3 py-3 bg-white rounded-bottom-3 border border-top-0">
                                <div class="row g-4">
                                    <div class="col-12 col-md-6">
                                        <div class="d-flex gap-2 mb-2 align-items-center">
                                            <i class="bi bi-journal-text text-success" style="font-size: 1.1rem;"></i>
                                            <h6 class="fw-bold text-dark mb-0">Mengisi & Mengedit Jurnal Mengajar</h6>
                                        </div>
                                        <ol class="help-steps mb-0" style="padding-left: 1.1rem;">
                                            <li>Buka menu <a href="{{ Route::has('guru.jurnal') ? route('guru.jurnal') : '#' }}" class="link-primary">Jurnal Saya</a>.</li>
                                            <li>Pilih salah satu slot KBM yang belum terisi (status <span class="badge bg-warning-subtle text-warning-emphasis border">Belum Terisi</span>).</li>
                                            <li>Isi <strong>materi pembelajaran</strong>, tambahkan <strong>catatan kejadian</strong> dan <strong>foto kegiatan</strong> bila diperlukan, lalu simpan.</li>
                                            <li>Jurnal yang sudah terisi dapat diedit pada <strong>hari yang sama</strong> lewat tombol Edit pada detail jurnal; jurnal H+1 otomatis terkunci & ditandai "Terisi (Terlambat)".</li>
                                        </ol>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="d-flex gap-2 mb-2 align-items-center">
                                            <i class="bi bi-person-raised-hand text-warning" style="font-size: 1.1rem;"></i>
                                            <h6 class="fw-bold text-dark mb-0">Mengajukan Izin Guru</h6>
                                        </div>
                                        <ol class="help-steps mb-0" style="padding-left: 1.1rem;">
                                            <li>Buka menu <a href="{{ Route::has('guru.izin.create') ? route('guru.izin.create') : '#' }}" class="link-primary">Pengajuan Izin Guru</a>.</li>
                                            <li>Isi <strong>tanggal izin</strong>, <strong>alasan</strong>, dan <strong>tugas untuk siswa</strong> selagi guru berhalangan.</li>
                                            <li>Buat <strong>tanda tangan digital</strong> pada kanvas TTD (mouse/touch), lampirkan surat pendukung bila ada, lalu pilih <strong>Kirim Pengajuan</strong>.</li>
                                            <li>Pantau status pengajuan dari daftar riwayat: <em>Pending Piket → Waka → Kepala Sekolah → Disetujui / Ditolak</em>.</li>
                                        </ol>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 2. Panduan Guru Piket --}}
                    <div class="accordion-item border-0 mb-3" x-data="{ open: false }">
                        <h2 class="accordion-header">
                            <button class="accordion-button rounded-3 shadow-sm fw-bold text-dark" 
                                    type="button" 
                                    :class="{ 'collapsed': !open }"
                                    :aria-expanded="open"
                                    @click.prevent="open = !open" 
                                    style="font-size: 1rem;">
                                <i class="bi bi-shield-check me-2 text-success" style="font-size: 1.15rem;"></i> Panduan Guru Piket
                            </button>
                        </h2>
                        <div x-show="open" 
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 transform -translate-y-2"
                             x-transition:enter-end="opacity-100 transform translate-y-0"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 transform translate-y-0"
                             x-transition:leave-end="opacity-0 transform -translate-y-2"
                             id="panduanPiket">
                            <div class="accordion-body px-3 py-3 bg-white rounded-bottom-3 border border-top-0">
                                <div class="row g-4">
                                    <div class="col-12 col-md-6">
                                        <div class="d-flex gap-2 mb-2 align-items-center">
                                            <i class="bi bi-clipboard-check text-primary" style="font-size: 1.1rem;"></i>
                                            <h6 class="fw-bold text-dark mb-0">Input Siswa Dispen / Izin Terlambat</h6>
                                        </div>
                                        <ol class="help-steps mb-0" style="padding-left: 1.1rem;">
                                            <li>Buka menu <a href="{{ Route::has('piket.dispensasi.create') ? route('piket.dispensasi.create') : '#' }}" class="link-primary">Input Dispensasi Siswa</a>.</li>
                                            <li>Isi <strong>nama siswa</strong>, <strong>jenis</strong> (Dispen / Izin Terlambat), <strong>alasan</strong>, dan <strong>tanggal</strong>.</li>
                                            <li>Simpan untuk menerbitkan <strong>nomor surat</strong> & <strong>QR validasi</strong> otomatis.</li>
                                            <li>Opsi <strong>dispen otomatis seluruh kelas</strong> tersedia dari daftar kelas pada hari itu.</li>
                                        </ol>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="d-flex gap-2 mb-2 align-items-center">
                                            <i class="bi bi-person-check-fill text-info" style="font-size: 1.1rem;"></i>
                                            <h6 class="fw-bold text-dark mb-0">Verifikasi Izin Guru</h6>
                                        </div>
                                        <ol class="help-steps mb-0" style="padding-left: 1.1rem;">
                                            <li>Buka menu <a href="{{ Route::has('piket.izin.index') ? route('piket.izin.index') : '#' }}" class="link-primary">Izin Guru</a>.</li>
                                            <li>Periksa <strong>detail & lampiran</strong> pengajuan izin yang masuk.</li>
                                            <li>Pilih <strong>Lanjut / Verifikasi</strong> untuk meneruskan sesuai alur, atau <strong>Tolak</strong> dengan catatan penolakan.</li>
                                            <li>Setelah diverifikasi, izin otomatis diteruskan ke <strong>Waka / Kepala Sekolah</strong> sesuai alur yang diatur.</li>
                                        </ol>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 3. Panduan Waka / Kepsek --}}
                    <div class="accordion-item border-0" x-data="{ open: false }">
                        <h2 class="accordion-header">
                            <button class="accordion-button rounded-3 shadow-sm fw-bold text-dark" 
                                    type="button" 
                                    :class="{ 'collapsed': !open }"
                                    :aria-expanded="open"
                                    @click.prevent="open = !open" 
                                    style="font-size: 1rem;">
                                <i class="bi bi-eyeglasses me-2 text-danger" style="font-size: 1.15rem;"></i> Panduan Waka Kesiswaan / Kepala Sekolah
                            </button>
                        </h2>
                        <div x-show="open" 
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 transform -translate-y-2"
                             x-transition:enter-end="opacity-100 transform translate-y-0"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 transform translate-y-0"
                             x-transition:leave-end="opacity-0 transform -translate-y-2"
                             id="panduanApproval">
                            <div class="accordion-body px-3 py-3 bg-white rounded-bottom-3 border border-top-0">
                                <div class="d-flex gap-2 mb-3 align-items-center">
                                    <i class="bi bi-whatsapp text-success" style="font-size: 1.1rem;"></i>
                                    <h6 class="fw-bold text-dark mb-0">Menyetujui Izin via Link WhatsApp & TTD Canvas</h6>
                                </div>
                                <ol class="help-steps mb-3" style="padding-left: 1.1rem;">
                                    <li>Guru Piket / Waka mengirim tautan persetujuan lewat tombol <strong>"Kirim WA ke Waka / Kepala Sekolah"</strong> pada menu Approval Izin Guru.</li>
                                    <li>Anda menerima pesan WhatsApp berisi link persetujuan unik (<code>/approve-izin/…</code>); buka link tersebut — <strong>tanpa perlu login</strong>.</li>
                                    <li>Tinjau <strong>detail pengajuan</strong> dan status langkah persetujuan pada halaman.</li>
                                    <li>Buat <strong>tanda tangan digital di kanvas TTD</strong> (tekan & geser dengan jari/stylus), lalu pilih <strong>Setujui</strong> — atau <strong>Tolak</strong> serta isi catatan penolakan.</li>
                                    <li>Status otomatis maju: <em>Waka → Kepala Sekolah → Disetujui final</em>. Link yang sudah diproses akan menampilkan notifikasi <strong>"Pengajuan ini sudah diproses"</strong>.</li>
                                </ol>
                                <div class="d-flex align-items-start gap-2 bg-light border rounded-3 p-3" style="--bs-border-opacity: .5;">
                                    <i class="bi bi-diagram-3 text-primary mt-1" style="font-size: 1.1rem;"></i>
                                    <div class="small text-muted">
                                        Alur persetujuan dapat disesuaikan oleh Waka Kurikulum pada menu
                                        <a href="{{ Route::has('kurikulum.izin.setting') ? route('kurikulum.izin.setting') : '#' }}" class="link-primary">Pengaturan Alur Izin</a>:
                                        <strong>3 level</strong> (Piket → Waka → Kepsek) / <strong>2 level</strong> (Piket → Kepsek) / <strong>1 level</strong> (Piket → final).
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </div>

        {{-- Kontak Bantuan Teknis --}}
        <div class="col-12 col-lg-4">
            <div class="h-100">
                <div class="card border-0 shadow-sm rounded-4 h-100" style="background: linear-gradient(160deg, #ffffff 0%, #f8fbff 100%);">
                    <div class="card-body p-4">
                        <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-3 mb-3" style="width: 54px; height: 54px;">
                            <i class="bi bi-headset text-primary" style="font-size: 1.6rem;"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-1">Bantuan Teknis</h5>
                        <p class="text-muted small mb-4">Admin IT / Tim Pengembang Sekolah</p>

                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div class="d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle flex-shrink-0" style="width: 48px; height: 48px;">
                                <i class="bi bi-person-fill" style="font-size: 1.3rem;"></i>
                            </div>
                            <div>
                                <div class="text-muted text-uppercase small fw-bold" style="font-size: 0.68rem; letter-spacing: 0.06em;">Kontak Layanan</div>
                                <div class="fw-bold text-dark">{{ $namaKontak }}</div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="text-muted text-uppercase small fw-bold mb-2" style="font-size: 0.68rem; letter-spacing: 0.06em;">Jam Operasional</div>
                            <ul class="list-unstyled mb-0">
                                @foreach($jamOperasional as $hari => $jam)
                                    <li class="d-flex justify-content-between align-items-center py-1 border-bottom" style="border-color: #eef2f7 !important;">
                                        <span class="text-secondary small">{{ $hari }}</span>
                                        <span class="fw-semibold text-dark small">{{ $jam }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        @if($noWaKontak)
                            <a href="{{ 'https://wa.me/' . $noWaKontak . '?text=' . urlencode('Halo, saya butuh bantuan teknis penggunaan WebJournal.') }}"
                               target="_blank" rel="noopener" class="btn btn-wa w-100 rounded-3 fw-semibold py-2">
                                <i class="bi bi-whatsapp me-1"></i> Chat WhatsApp
                            </a>
                            <p class="text-center text-muted small mt-3 mb-0">
                                Pesan di luar jam operasional akan dijawab pada hari kerja berikutnya.
                            </p>
                        @else
                            <div class="alert alert-warning border-0 rounded-3 small mb-0">
                                <i class="bi bi-exclamation-triangle me-1"></i> Nomor WhatsApp layanan belum diatur. Hubungi admin untuk melengkapi nomor pada profil admin.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<style>
    .btn-wa { background-color: #25D366 !important; border-color: #25D366 !important; color: #fff !important; }
    .btn-wa:hover, .btn-wa:focus, .btn-wa:active { background-color: #1EBE5D !important; border-color: #1EBE5D !important; color: #fff !important; }

    .accordion-item .accordion-button {
        background: #f8fafc;
        border: 1px solid #e8eef5;
        padding: 0.95rem 1.1rem;
    }
    .accordion-item .accordion-button:not(.collapsed) {
        background: #ffffff;
        color: #0f172a;
        box-shadow: inset 0 -1px 0 #e8eef5;
    }
    .accordion-item .accordion-button:focus {
        box-shadow: 0 0 0 3px rgba(22, 119, 255, 0.15);
        border-color: #93c5fd;
    }
    .help-steps li {
        margin-bottom: 0.55rem;
        color: #334155;
        font-size: 0.9rem;
        line-height: 1.55;
    }
</style>
@endsection