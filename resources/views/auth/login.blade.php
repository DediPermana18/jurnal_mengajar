<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Login - WebJournal Management System</title>

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
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                        }
                    }
                }
            }
        }
    </script>

    <!-- Alpine.js CDN -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col justify-between" x-data="{ mode: '{{ old('mode', 'guru') }}' }">

    <!-- ================= TOP HEADER ================= -->
    <header class="w-full bg-white border-b border-slate-200/80 px-6 lg:px-12 py-4 flex items-center justify-between sticky top-0 z-30 shadow-sm">
        <!-- Logo Left -->
        <div class="flex items-center gap-3.5">
            <div class="w-11 h-11 bg-brand-600 text-white rounded-2xl flex items-center justify-center text-xl shadow-md shadow-brand-600/30 shrink-0">
                <i class="bi bi-journal-bookmark-fill"></i>
            </div>
            <div>
                <span class="text-xl lg:text-2xl font-black text-brand-600 tracking-tight block leading-none">WebJournal</span>
                <span class="text-[9px] font-extrabold tracking-[0.18em] text-slate-500 uppercase block mt-1">MANAGEMENT SYSTEM</span>
            </div>
        </div>

        <!-- Support Right -->
        <div>
            <a href="#" class="text-base lg:text-lg font-bold text-slate-900 hover:text-brand-600 transition-colors">Support</a>
        </div>
    </header>

    <!-- ================= MAIN SPLIT SCREEN LAYOUT ================= -->
    <main class="flex-1 flex flex-col md:flex-row w-full min-h-[calc(100vh-77px)]">
        
        <!-- ================= LEFT COLUMN: CONTEXTUAL BACKGROUND IMAGE ================= -->
        <div class="w-full md:w-1/2 lg:w-5/12 xl:w-1/2 relative overflow-hidden min-h-[260px] md:min-h-full border-r border-slate-200/60 bg-slate-200">
            <!-- Mode GURU Background -->
            <div x-show="mode === 'guru'" 
                 x-transition:enter="transition ease-out duration-500"
                 x-transition:enter-start="opacity-0 scale-105"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-300"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="absolute inset-0 bg-cover bg-center"
                 style="background-image: url('https://images.unsplash.com/photo-1580582932707-520aed937b7b?w=1200&auto=format&fit=crop&q=80');">
                <div class="absolute inset-0 bg-slate-900/10 backdrop-blur-[1px]"></div>
            </div>

            <!-- Mode ADMIN Background -->
            <div x-show="mode === 'admin'" 
                 x-transition:enter="transition ease-out duration-500"
                 x-transition:enter-start="opacity-0 scale-105"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-300"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="absolute inset-0 bg-cover bg-center"
                 style="background-image: url('https://images.unsplash.com/photo-1497366216548-37526070297c?w=1200&auto=format&fit=crop&q=80');">
                <div class="absolute inset-0 bg-slate-900/10 backdrop-blur-[1px]"></div>
            </div>
        </div>

        <!-- ================= RIGHT COLUMN: LOGIN FORM CENTER ================= -->
        <div class="w-full md:w-1/2 lg:w-7/12 xl:w-1/2 bg-[#f8fafc] flex items-center justify-center p-6 lg:p-12">
            
            <div class="w-full max-w-md mx-auto">
                
                <!-- TAB SWITCHER PILL BUTTON -->
                <div class="flex justify-center mb-8">
                    <div class="inline-flex items-center bg-slate-200/80 p-1.5 rounded-full border border-slate-300/70 shadow-inner">
                        <!-- GURU TAB -->
                        <button type="button" 
                                @click="mode = 'guru'" 
                                :class="mode === 'guru' ? 'bg-brand-600 text-white shadow-md shadow-brand-600/40 ring-1 ring-brand-500' : 'text-slate-600 hover:text-slate-900'"
                                class="px-8 py-2 rounded-full font-black text-sm uppercase tracking-wider transition-all duration-200">
                            GURU
                        </button>
                        
                        <!-- ADMIN TAB -->
                        <button type="button" 
                                @click="mode = 'admin'" 
                                :class="mode === 'admin' ? 'bg-brand-600 text-white shadow-md shadow-brand-600/40 ring-1 ring-brand-500' : 'text-slate-600 hover:text-slate-900'"
                                class="px-8 py-2 rounded-full font-black text-sm uppercase tracking-wider transition-all duration-200">
                            ADMIN
                        </button>
                    </div>
                </div>

                <!-- ALERT NOTIFIKASI ERROR GENERAL / SUCCESS -->
                @if ($errors->any())
                    <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold shadow-sm">
                        <div class="flex items-center gap-2 mb-1 text-rose-800 font-bold">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <span>Gagal Masuk Ke Sistem</span>
                        </div>
                        <ul class="list-disc list-inside space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (session('success'))
                    <div class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-semibold shadow-sm flex items-center gap-2">
                        <i class="bi bi-check-circle-fill"></i>
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                <!-- LOGIN FORM -->
                <form action="{{ route('login.post') }}" method="POST" class="space-y-5">
                    @csrf
                    <!-- Hidden input role mode -->
                    <input type="hidden" name="mode" :value="mode">

                    <!-- USERNAME / NIP -->
                    <div>
                        <label class="block text-xs font-black tracking-wider text-slate-700 uppercase mb-2">USERNAME/NIP</label>
                        <input type="text" 
                               name="login_id" 
                               required 
                               value="{{ old('login_id') }}"
                               placeholder="Masukkan Username atau NIP" 
                               class="w-full px-6 py-3.5 bg-white border border-slate-300/80 rounded-full focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 shadow-sm text-slate-900 text-sm font-medium transition-all placeholder:text-slate-400">
                    </div>

                    <!-- PASSWORD -->
                    <div>
                        <label class="block text-xs font-black tracking-wider text-slate-700 uppercase mb-2">PASSWORD</label>
                        <input type="password" 
                               name="password" 
                               required 
                               placeholder="Masukkan Password" 
                               class="w-full px-6 py-3.5 bg-white border border-slate-300/80 rounded-full focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 shadow-sm text-slate-900 text-sm font-medium transition-all placeholder:text-slate-400">
                    </div>

                    <!-- KODE AKTIVASI (KHUSUS ADMIN) -->
                    <div x-show="mode === 'admin'" 
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 -translate-y-2"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-cloak>
                        <label class="block text-xs font-black tracking-wider text-slate-700 uppercase mb-2">KODE AKTIVASI</label>
                        <input type="text" 
                               name="kode_aktivasi" 
                               :disabled="mode !== 'admin'"
                               value="{{ old('kode_aktivasi') }}"
                               placeholder="Masukkan Kode Aktivasi Admin (misal: ADMIN123)" 
                               class="w-full px-6 py-3.5 bg-white border border-slate-300/80 rounded-full focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 shadow-sm text-slate-900 text-sm font-medium transition-all placeholder:text-slate-400">
                        <p class="text-[11px] text-slate-500 mt-1.5 px-3">
                            <i class="bi bi-info-circle me-1"></i> Kode aktivasi dev: <code class="font-bold text-brand-600 bg-slate-200/70 px-1.5 py-0.5 rounded">ADMIN123</code> atau <code class="font-bold text-brand-600 bg-slate-200/70 px-1.5 py-0.5 rounded">WEBJOURNAL2026</code>
                        </p>
                    </div>

                    <!-- LUPA SANDI LINK -->
                    <div class="text-right pt-1 pb-2">
                        <a href="#" 
                           class="text-xs font-extrabold text-brand-600 hover:text-brand-700 hover:underline transition-colors"
                           x-text="mode === 'guru' ? 'lupa sandi?' : 'lupa sandi/kode aktivasi?'">
                        </a>
                    </div>

                    <!-- TOMBOL MASUK -->
                    <button type="submit" 
                            class="w-full py-4 bg-brand-600 hover:bg-brand-700 active:bg-brand-800 text-white font-black text-sm uppercase tracking-widest rounded-full shadow-lg shadow-brand-600/35 transition-all duration-200 hover:shadow-brand-600/50 transform hover:-translate-y-0.5 active:translate-y-0">
                        MASUK
                    </button>

                </form>

            </div>

        </div>

    </main>

</body>
</html>
