<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Reset Kredensial - WebJournal Management System</title>

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
        <div class="w-full max-w-md mx-auto">

            @if ($errors->any())
                <div class="mb-5 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold shadow-sm">
                    <div class="flex items-center gap-2 mb-1 text-rose-800 font-bold">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span>Gagal memperbarui kredensial</span>
                    </div>
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- KARTU RESET -->
            <div class="bg-white rounded-3xl shadow-lg shadow-slate-200/60 border border-slate-200/80 overflow-hidden">
                <div class="bg-gradient-to-r from-brand-600 to-brand-700 px-7 py-6 text-white">
                    <h1 class="text-lg lg:text-xl font-black tracking-tight flex items-center gap-2.5">
                        <i class="bi bi-shield-lock-fill"></i>
                        {{ $reset->jenis_pengajuan === App\Models\ResetRequest::JENIS_LUPA_SANDI ? 'Atur Ulang Sandi' : 'Atur Ulang Kode Aktivasi' }}
                    </h1>
                    <p class="text-sm text-brand-50/90 mt-1.5">
                        Pengajuan atas nama <strong>{{ $reset->user?->nama ?? 'Pemohon' }}</strong>
                    </p>
                    <p class="text-[11px] text-brand-50/75 mt-1">
                        <i class="bi bi-clock-history me-1"></i>Tautan ini berlaku hingga
                        {{ $reset->token_expires_at?->translatedFormat('d F Y, H:i') }} dan hanya dapat dipakai sekali.
                    </p>
                </div>

                <div class="p-7">
                    <form action="{{ route('reset-credentials.submit', $reset->reset_token) }}" method="POST" class="space-y-5">
                        @csrf

                        @if($reset->jenis_pengajuan === App\Models\ResetRequest::JENIS_LUPA_SANDI)
                            {{-- ===== FORM PASSWORD BARU ===== --}}
                            <div x-data="{ show: false }">
                                <label for="password" class="block text-xs font-black tracking-wider text-slate-700 uppercase mb-2">PASSWORD BARU</label>
                                <div class="relative">
                                    <input type="password" name="password" id="password"
                                           :type="show ? 'text' : 'password'"
                                           required minlength="8" maxlength="255"
                                           autocomplete="new-password"
                                           placeholder="Minimal 8 karakter"
                                           class="w-full px-5 py-3.5 bg-white border border-slate-300/80 rounded-2xl focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 shadow-sm text-slate-900 text-sm font-medium transition-all placeholder:text-slate-400 pr-12">
                                    <button type="button" @click="show = !show"
                                            class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-slate-600"
                                            aria-label="Tampilkan / sembunyikan password">
                                        <i class="bi" :class="show ? 'bi-eye-slash' : 'bi-eye'"></i>
                                    </button>
                                </div>
                                <p class="text-[11px] text-slate-500 mt-1.5"><i class="bi bi-info-circle me-1"></i>Minimal 8 karakter. Simpan baik-baik.</p>
                            </div>

                            <div x-data="{ show: false }">
                                <label for="password_confirmation" class="block text-xs font-black tracking-wider text-slate-700 uppercase mb-2">KONFIRMASI PASSWORD BARU</label>
                                <div class="relative">
                                    <input type="password" name="password_confirmation" id="password_confirmation"
                                           :type="show ? 'text' : 'password'"
                                           required minlength="8" maxlength="255"
                                           autocomplete="new-password"
                                           placeholder="Ulangi password baru"
                                           class="w-full px-5 py-3.5 bg-white border border-slate-300/80 rounded-2xl focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 shadow-sm text-slate-900 text-sm font-medium transition-all placeholder:text-slate-400 pr-12">
                                    <button type="button" @click="show = !show"
                                            class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-slate-600"
                                            aria-label="Tampilkan / sembunyikan password">
                                        <i class="bi" :class="show ? 'bi-eye-slash' : 'bi-eye'"></i>
                                    </button>
                                </div>
                            </div>
                        @else
                            {{-- ===== FORM KODE AKTIVASI BARU ===== --}}
                            <div>
                                <label for="kode_aktivasi" class="block text-xs font-black tracking-wider text-slate-700 uppercase mb-2">KODE AKTIVASI BARU</label>
                                <input type="text" name="kode_aktivasi" id="kode_aktivasi"
                                       required maxlength="100"
                                       value="{{ old('kode_aktivasi') }}"
                                       placeholder="Contoh: AKT-XXXXXXXX"
                                       class="w-full px-5 py-3.5 bg-white border border-slate-300/80 rounded-2xl focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 shadow-sm text-slate-900 text-sm font-medium transition-all placeholder:text-slate-400">
                                <p class="text-[11px] text-slate-500 mt-1.5 flex items-center gap-1">
                                    <i class="bi bi-info-circle"></i> Kode aktivasi baru wajib diisi dan belum digunakan akun lain. Gunakan saat login bersama password.
                                </p>
                            </div>
                        @endif

                        <button type="submit"
                                class="w-full py-4 bg-brand-600 hover:bg-brand-700 active:bg-brand-800 text-white font-black text-sm uppercase tracking-widest rounded-2xl shadow-lg shadow-brand-600/35 transition-all duration-200 hover:shadow-brand-600/50 transform hover:-translate-y-0.5 active:translate-y-0">
                            <i class="bi bi-check2-circle me-1.5"></i> Simpan & Selesai
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <!-- Alpine.js CDN -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

</body>
</html>