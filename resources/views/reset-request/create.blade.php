<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Lupa Sandi / Kode Aktivasi - WebJournal Management System</title>

    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] },
                    colors: {
                        brand: {
                            50: '#f0f9ff', 100: '#e0f2fe',
                            500: '#0ea5e9', 600: '#0284c7', 700: '#0369a1', 800: '#075985',
                        }
                    }
                }
            }
        }
    </script>

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        [x-cloak] { display: none !important; }
        /* Canvas signature: cegah scroll saat menggambar di layar sentuh */
        #sigCanvas { touch-action: none; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">

    <!-- ================= TOP HEADER ================= -->
    <header class="w-full bg-white border-b border-slate-200/80 px-6 lg:px-12 py-4 flex items-center justify-between sticky top-0 z-30 shadow-sm">
        <a href="{{ route('login') }}" class="flex items-center gap-3.5">
            <div class="w-11 h-11 bg-brand-600 text-white rounded-2xl flex items-center justify-center text-xl shadow-md shadow-brand-600/30 shrink-0">
                <i class="bi bi-journal-bookmark-fill"></i>
            </div>
            <div>
                <span class="text-xl lg:text-2xl font-black text-brand-600 tracking-tight block leading-none">WebJournal</span>
                <span class="text-[9px] font-extrabold tracking-[0.18em] text-slate-500 uppercase block mt-1">MANAGEMENT SYSTEM</span>
            </div>
        </a>
        <a href="{{ route('login') }}" class="text-sm font-bold text-slate-600 hover:text-brand-600 transition-colors flex items-center gap-1.5">
            <i class="bi bi-arrow-left"></i> Kembali ke Login
        </a>
    </header>

    <!-- ================= MAIN ================= -->
    <main class="flex-1 flex items-center justify-center p-6 lg:p-10">
        <div class="w-full max-w-2xl mx-auto">

            <!-- KARTU FORMULIR -->
            <div class="bg-white rounded-3xl shadow-lg shadow-slate-200/60 border border-slate-200/80 overflow-hidden">
                <div class="bg-gradient-to-r from-brand-600 to-brand-700 px-7 py-6 text-white">
                    <h1 class="text-xl lg:text-2xl font-black tracking-tight flex items-center gap-2.5">
                        <i class="bi bi-key-fill"></i> Lupa Sandi / Kode Aktivasi
                    </h1>
                    <p class="text-sm text-brand-50/90 mt-1.5">
                        Isi formulir & tanda tangan digital. Admin TU akan memverifikasi lalu mengirimkan tautan reset unik melalui WhatsApp.
                    </p>
                </div>

                <div class="p-7">

                    <!-- ALERT ERROR -->
                    @if ($errors->any())
                        <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold shadow-sm">
                            <div class="flex items-center gap-2 mb-1 text-rose-800 font-bold">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <span>Pengajuan gagal diproses</span>
                            </div>
                            <ul class="list-disc list-inside space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <!-- ALERT SUCCESS -->
                    @if (session('success'))
                        <div class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-semibold shadow-sm flex items-center gap-2">
                            <i class="bi bi-check-circle-fill text-base"></i>
                            <span>{{ session('success') }}</span>
                        </div>
                    @endif

                    <form action="{{ route('reset-request.store') }}" method="POST" class="space-y-6" x-data="resetRequestForm()">
                        @csrf

                        <!-- USERNAME / NIP -->
                        <div>
                            <label for="login_id" class="block text-xs font-black tracking-wider text-slate-700 uppercase mb-2">
                                USERNAME / NIP
                            </label>
                            <input type="text"
                                   id="login_id"
                                   name="login_id"
                                   required
                                   x-model="login_id"
                                   @blur="cekAkun()"
                                   @input.debounce.700ms="cekAkun()"
                                   value="{{ old('login_id') }}"
                                   placeholder="Masukkan Username atau NIP Anda"
                                   autocomplete="off"
                                   class="w-full px-5 py-3.5 bg-white border border-slate-300/80 rounded-2xl focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 shadow-sm text-slate-900 text-sm font-medium transition-all placeholder:text-slate-400">
                            <p class="text-[11px] text-slate-500 mt-1.5 flex items-center gap-1"><i class="bi bi-info-circle"></i> Gunakan akun yang sama saat login (Username atau NIP).</p>

                            <!-- STATUS CEK AKUN (Alpine) -->
                            <p x-cloak x-show="akunStatus === 'loading'"
                               class="text-[11px] text-brand-600 mt-1.5 flex items-center gap-1 font-semibold">
                                <i class="bi bi-arrow-repeat animate-spin"></i> Memeriksa akun...
                            </p>
                            <p x-cloak x-show="akunStatus === 'not_found'"
                               class="text-[11px] text-rose-600 mt-1.5 flex items-center gap-1 font-semibold">
                                <i class="bi bi-exclamation-triangle-fill"></i> Akun tidak ditemukan.
                            </p>
                        </div>

                        <!-- JENIS PENGAJUAN -->
                        <div>
                            <label class="block text-xs font-black tracking-wider text-slate-700 uppercase mb-2">JENIS PENGAJUAN</label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <button type="button" @click="jenis = 'lupa_sandi'"
                                        :class="jenis === 'lupa_sandi' ? 'border-brand-500 bg-brand-50 ring-2 ring-brand-500/20' : 'border-slate-300 bg-white hover:border-slate-400'"
                                        class="flex items-center gap-3 p-4 rounded-2xl border-2 text-left transition-all select-none">
                                    <div :class="jenis === 'lupa_sandi' ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-500'" class="w-10 h-10 rounded-xl flex items-center justify-center text-lg shrink-0">
                                        <i class="bi bi-shield-lock"></i>
                                    </div>
                                    <div>
                                        <p class="font-extrabold text-sm text-slate-900">Lupa Sandi</p>
                                        <p class="text-[11px] text-slate-500">Password tidak bisa digunakan untuk login.</p>
                                    </div>
                                </button>
                                <button type="button" @click="jenis = 'lupa_kode_aktivasi'"
                                        :disabled="kodeDisabled"
                                        :class="jenis === 'lupa_kode_aktivasi'
                                            ? 'border-brand-500 bg-brand-50 ring-2 ring-brand-500/20'
                                            : (kodeDisabled
                                                ? 'border-slate-200 bg-slate-50 opacity-60 cursor-not-allowed'
                                                : 'border-slate-300 bg-white hover:border-slate-400')"
                                        class="flex items-center gap-3 p-4 rounded-2xl border-2 text-left transition-all select-none">
                                    <div :class="jenis === 'lupa_kode_aktivasi' ? 'bg-brand-600 text-white' : (kodeDisabled ? 'bg-slate-100 text-slate-400' : 'bg-slate-100 text-slate-500')" class="w-10 h-10 rounded-xl flex items-center justify-center text-lg shrink-0">
                                        <i class="bi bi-hash"></i>
                                    </div>
                                    <div>
                                        <p class="font-extrabold text-sm text-slate-900">Lupa Kode Aktivasi</p>
                                        <p class="text-[11px] text-slate-500" :class="kodeDisabled ? 'text-slate-400' : 'text-slate-500'">Kode aktivasi login hilang / lupa.</p>
                                    </div>
                                </button>
                            </div>
                            <input type="hidden" name="jenis_pengajuan" :value="jenis">

                            <!-- HINT: akun tidak memakai Kode Aktivasi -->
                            <p x-cloak x-show="akunStatus === 'ok' && !punyaKodeAktivasi"
                               class="text-[11px] text-amber-800 bg-amber-50 border border-amber-200 rounded-xl px-3 py-2 mt-3 flex items-center gap-1.5 font-semibold">
                                <i class="bi bi-info-circle-fill"></i> Akun ini tidak memiliki Kode Aktivasi. Silakan pilih Lupa Sandi.
                            </p>
                        </div>

                        <!-- TANDA TANGAN DIGITAL -->
                        <div x-data="signaturePad()" x-init="init()">
                            <label class="flex items-center justify-between mb-2">
                                <span class="block text-xs font-black tracking-wider text-slate-700 uppercase">TANDA TANGAN DIGITAL</span>
                                <button type="button" @click="clear()"
                                        class="text-[11px] font-bold text-slate-500 hover:text-rose-600 transition-colors flex items-center gap-1">
                                    <i class="bi bi-eraser"></i> Hapus & Gambar Ulang
                                </button>
                            </label>

                            <div class="relative border-2 border-dashed border-slate-300 rounded-2xl overflow-hidden bg-slate-50"
                                 :class="dataUrl ? 'border-emerald-400 bg-emerald-50/40' : ''">
                                <canvas id="sigCanvas" x-ref="canvas" width="600" height="220"
                                        class="w-full h-52 cursor-crosshair"
                                        @pointerdown.prevent="start($event)"
                                        @pointermove.prevent="move($event)"
                                        @pointerup="end()"
                                        @pointerleave="end()"></canvas>

                                <!-- Placeholder saat kosong -->
                                <div x-show="!dataUrl" x-cloak
                                     class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none text-slate-400">
                                    <i class="bi bi-pen text-3xl mb-1"></i>
                                    <p class="text-xs font-semibold">Tanda tangan di sini (mouse / jari)</p>
                                </div>
                            </div>

                            <input type="hidden" name="tanda_tangan" :value="dataUrl">

                            <div class="flex items-center justify-between mt-2">
                                <p class="text-[11px] text-slate-500 flex items-center gap-1">
                                    <i class="bi bi-shield-check"></i> Tanda tangan digunakan untuk memverifikasi identitas pemohon.
                                </p>
                                <span x-show="dataUrl" x-cloak class="text-[11px] font-bold text-emerald-600 flex items-center gap-1">
                                    <i class="bi bi-check-circle-fill"></i> Tanda tangan tersimpan
                                </span>
                            </div>
                        </div>

                        <!-- SUBMIT -->
                        <button type="submit"
                                class="w-full py-4 bg-brand-600 hover:bg-brand-700 active:bg-brand-800 text-white font-black text-sm uppercase tracking-widest rounded-2xl shadow-lg shadow-brand-600/35 transition-all duration-200 hover:shadow-brand-600/50 transform hover:-translate-y-0.5 active:translate-y-0">
                            <i class="bi bi-send-fill me-1.5"></i> Ajukan Pengajuan
                        </button>

                        <p class="text-center text-[11px] text-slate-400 mt-3">
                            Proses verifikasi dilakukan oleh Admin TU pada jam kerja. Pastikan nomor WhatsApp terdaftar pada akun Anda.
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <!-- ================= SIGNATURE PAD & FORM RESET SCRIPT ================= -->
    <script>
        // State form /lupa-sandi: cek akun saat user berhenti mengetik
        // (debounce input + blur). Jika akun tidak memakai Kode Aktivasi,
        // kartu 'Lupa Kode Aktivasi' dinonaktifkan.
        function resetRequestForm() {
            return {
                login_id: @js(old('login_id') ?? ''),
                jenis: @js(old('jenis_pengajuan', 'lupa_sandi')),
                akunStatus: 'idle', // 'idle' | 'loading' | 'ok' | 'not_found'
                punyaKodeAktivasi: false,
                terakhirDiperiksa: '',
                init() {
                    // Bila akun ternyata tanpa Kode Aktivasi, kembalikan pilihan
                    // ke 'Lupa Sandi' (jika sedang terpilih).
                    this.$watch('punyaKodeAktivasi', (v) => {
                        if (!v && this.jenis === 'lupa_kode_aktivasi') {
                            this.jenis = 'lupa_sandi';
                        }
                    });
                },
                // Kartu 'Lupa Kode Aktivasi' hanya aktif bila akun terbukti
                // terdaftar DAN memiliki kode aktivasi.
                get kodeDisabled() {
                    return this.akunStatus === 'ok' && !this.punyaKodeAktivasi;
                },
                async cekAkun() {
                    const nilai = this.login_id.trim();
                    if (nilai === '') {
                        this.akunStatus = 'idle';
                        this.punyaKodeAktivasi = false;
                        return;
                    }
                    if (nilai === this.terakhirDiperiksa) return;
                    this.terakhirDiperiksa = nilai;
                    this.akunStatus = 'loading';
                    try {
                        const res = await fetch(
                            `{{ route('reset-request.check-account') }}?login_id=${encodeURIComponent(nilai)}`,
                            { headers: { Accept: 'application/json' } }
                        );
                        if (!res.ok) throw new Error('Gagal memeriksa akun');
                        const data = await res.json();
                        // Nilai sudah berubah selagi fetch berjalan -> abaikan.
                        if (this.login_id.trim() !== nilai) return;
                        this.akunStatus = data.found ? 'ok' : 'not_found';
                        this.punyaKodeAktivasi = Boolean(data.has_kode_aktivasi);
                    } catch (e) {
                        this.akunStatus = 'idle';
                    }
                },
            };
        }

        function signaturePad() {
            return {
                drawing: false,
                touched: false,
                dataUrl: '',
                init() {
                    const canvas = this.$refs.canvas;
                    const dpr = window.devicePixelRatio || 1;
                    // Ukuran logis dari CSS (lebar kontainer, tinggi 220px)
                    const rect = canvas.getBoundingClientRect();
                    canvas.width = rect.width * dpr;
                    canvas.height = rect.height * dpr;
                    const ctx = canvas.getContext('2d');
                    ctx.scale(dpr, dpr);
                    ctx.lineWidth = 2.75;
                    ctx.lineCap = 'round';
                    ctx.lineJoin = 'round';
                    ctx.strokeStyle = '#1e293b';
                    this.canvas = canvas;
                    this.ctx = ctx;
                },
                pos(e) {
                    const rect = this.canvas.getBoundingClientRect();
                    const clientX = e.clientX ?? (e.touches && e.touches[0] ? e.touches[0].clientX : 0);
                    const clientY = e.clientY ?? (e.touches && e.touches[0] ? e.touches[0].clientY : 0);
                    return { x: clientX - rect.left, y: clientY - rect.top };
                },
                start(e) {
                    this.drawing = true;
                    this.touched = true;
                    const p = this.pos(e);
                    this.ctx.beginPath();
                    this.ctx.moveTo(p.x, p.y);
                },
                move(e) {
                    if (!this.drawing) return;
                    const p = this.pos(e);
                    this.ctx.lineTo(p.x, p.y);
                    this.ctx.stroke();
                },
                end() {
                    if (!this.drawing) return;
                    this.drawing = false;
                    if (this.touched) {
                        this.dataUrl = this.canvas.toDataURL('image/png');
                    }
                },
                clear() {
                    this.drawing = false;
                    this.touched = false;
                    this.dataUrl = '';
                    this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
                },
            };
        }
    </script>
    <!-- Alpine.js CDN (defer — fungsi signaturePad() sudah didefinisikan di atas) -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

</body>
</html>