<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - PerformanceAppraisal | PT Musashi Auto Parts Indonesia</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ asset('musashi.png') }}?v={{ file_exists(public_path('musashi.png')) ? filemtime(public_path('musashi.png')) : '1' }}">
    <link rel="shortcut icon" type="image/png" href="{{ asset('musashi.png') }}?v={{ file_exists(public_path('musashi.png')) ? filemtime(public_path('musashi.png')) : '1' }}">
    <link rel="apple-touch-icon" href="{{ asset('musashi.png') }}?v={{ file_exists(public_path('musashi.png')) ? filemtime(public_path('musashi.png')) : '1' }}">

    <!-- Google Fonts & Tailwind CSS -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#fdf3f3',
                            100: '#fbe6e6',
                            200: '#f7d0d1',
                            300: '#f0adad',
                            400: '#e37a7b',
                            500: '#d04e50',
                            600: '#b83437',
                            700: '#9b2629',
                            800: '#7b181b', // Primary Maroon
                            900: '#641517',
                            950: '#380709',
                        }
                    },
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                        mono: ['"JetBrains Mono"', 'monospace'],
                    }
                }
            }
        }
    </script>

    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .highlight-pulse {
            animation: inputPulse 1.2s ease-in-out;
        }
        @keyframes inputPulse {
            0% { border-color: #7b181b; box-shadow: 0 0 0 3px rgba(123, 24, 27, 0.35); }
            50% { border-color: #b83437; box-shadow: 0 0 0 5px rgba(184, 52, 55, 0.45); }
            100% { border-color: #d1d5db; box-shadow: none; }
        }
        /* Custom scrollbar for demo drawer */
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 9999px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>
</head>
<body class="min-h-screen bg-neutral-100 flex flex-col justify-center text-neutral-800 antialiased selection:bg-[#7b181b] selection:text-white">

    <div class="min-h-screen w-full flex flex-col lg:flex-row bg-white">
        
        <!-- ============================================================== -->
        <!-- LEFT PANEL: Corporate Maroon Banner with Flowing Waves         -->
        <!-- ============================================================== -->
        <section class="relative lg:w-1/2 bg-gradient-to-br from-[#741518] via-[#7b181b] to-[#5e1214] text-white p-8 sm:p-12 lg:p-16 xl:p-20 flex flex-col justify-between overflow-hidden min-h-[380px] lg:min-h-screen">
            
            <!-- Subtle Radial Gradient Background Glow -->
            <div class="absolute top-0 right-0 w-[500px] h-[500px] bg-red-500/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute bottom-0 left-0 w-[400px] h-[400px] bg-black/20 rounded-full blur-2xl pointer-events-none"></div>

            <!-- Flowing Waves Graphic (Bottom Left Corner) -->
            <div class="absolute inset-x-0 bottom-0 pointer-events-none overflow-hidden opacity-90 select-none">
                <svg class="w-full h-48 sm:h-64 lg:h-80" viewBox="0 0 800 350" fill="none" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
                    <!-- Layer 1: Dark Wave -->
                    <path d="M-50 350 C 150 310, 260 220, 480 270 C 660 310, 750 260, 850 240 L 850 350 L -50 350 Z" fill="rgba(0, 0, 0, 0.16)"/>
                    <!-- Layer 2: Subtle Soft Maroon Accent -->
                    <path d="M-50 350 C 120 280, 310 180, 520 240 C 700 290, 770 210, 850 200 L 850 350 L -50 350 Z" fill="rgba(255, 255, 255, 0.04)"/>
                    <!-- Layer 3: Main Elegant Curve Ribbon -->
                    <path d="M-50 350 C 180 230, 320 320, 560 210 C 700 140, 780 190, 850 170 L 850 350 L -50 350 Z" fill="rgba(255, 255, 255, 0.05)"/>
                    <!-- Layer 4: Fine Stroke Line Wave -->
                    <path d="M-50 320 C 140 250, 300 210, 510 260 C 690 300, 760 220, 850 210" stroke="rgba(255, 255, 255, 0.16)" stroke-width="2.5" stroke-linecap="round"/>
                    <path d="M-50 290 C 190 200, 340 300, 580 190 C 690 140, 790 170, 850 160" stroke="rgba(255, 255, 255, 0.12)" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
            </div>

            <!-- Top Header / App Branding -->
            <div class="relative z-10 flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-2xl bg-white shadow-md flex items-center justify-center p-2 flex-shrink-0 border border-white/40">
                    <img src="{{ asset('musashi.png') }}" alt="Logo" class="w-full h-full object-contain" onerror="this.outerHTML='<span class=\'font-black text-xl text-[#7b181b]\'>M</span>'">
                </div>
                <div>
                    <h2 class="text-white font-extrabold text-xl sm:text-2xl tracking-tight leading-none">
                        PerformanceAppraisal
                    </h2>
                    <p class="text-red-200/80 text-[11px] font-medium tracking-wide mt-1">
                        PT Musashi Auto Parts Indonesia
                    </p>
                </div>
            </div>

            <!-- Center Typography: Catchphrase -->
            <div class="relative z-10 my-auto py-12 lg:py-16">
                <h1 class="text-3xl sm:text-4xl xl:text-5xl font-extrabold text-white leading-[1.15] tracking-tight">
                    Kelola Penilaian<br class="hidden sm:inline"> dalam satu tempat.
                </h1>
                <p class="mt-5 text-red-100/90 text-sm sm:text-base font-normal leading-relaxed max-w-md">
                    Masuk sesuai peran Anda untuk melihat data dan laporan.
                </p>

                <!-- Flow Process Indicator (Foreman -> Section -> Dept -> Division) -->
                <div class="mt-8 pt-6 border-t border-white/10 hidden sm:flex items-center gap-2 text-xs text-red-100/80">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-black/20 backdrop-blur-sm border border-white/10">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Foreman
                    </span>
                    <span>&rarr;</span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-black/20 backdrop-blur-sm border border-white/10">
                        <span class="w-1.5 h-1.5 rounded-full bg-blue-400"></span> Section Head
                    </span>
                    <span>&rarr;</span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-black/20 backdrop-blur-sm border border-white/10">
                        <span class="w-1.5 h-1.5 rounded-full bg-purple-400"></span> Dept Head
                    </span>
                    <span>&rarr;</span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-black/20 backdrop-blur-sm border border-white/10">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span> Div Head
                    </span>
                </div>
            </div>

            <!-- Bottom Left Footer / Copyright -->
            <div class="relative z-10 text-xs text-red-200/70 font-medium">
                &copy; <span class="current-year">{{ date('Y') }}</span> PT Musashi Auto Parts Indonesia
            </div>
        </section>

        <!-- ============================================================== -->
        <!-- RIGHT PANEL: Clean White Authentication Form                   -->
        <!-- ============================================================== -->
        <section class="lg:w-1/2 bg-white flex flex-col justify-center items-center p-6 sm:p-10 lg:p-16 xl:p-24 relative">
            
            <div class="w-full max-w-[420px] mx-auto space-y-6">
                
                <!-- Header Title -->
                <div>
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-neutral-900 tracking-tight">
                        Masuk
                    </h2>
                    <p class="text-sm text-neutral-500 mt-2">
                        Silakan masukkan akun Anda untuk melanjutkan.
                    </p>
                </div>

                <!-- Alert Messages -->
                @if ($errors->any())
                    <div class="rounded-xl bg-red-50 border border-red-200 p-4 text-xs sm:text-sm text-red-800 space-y-1">
                        <div class="font-bold flex items-center gap-2 text-red-900">
                            <svg class="w-4 h-4 text-red-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            Gagal Masuk
                        </div>
                        @foreach ($errors->all() as $error)
                            <p class="text-xs text-red-700 pl-6">{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                @if (session('error'))
                    <div class="rounded-xl bg-red-50 border border-red-200 p-4 text-xs sm:text-sm text-red-800 flex items-start gap-2.5">
                        <svg class="w-4 h-4 text-red-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <span>{{ session('error') }}</span>
                    </div>
                @endif

                @if (session('success'))
                    <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-xs sm:text-sm text-emerald-800 flex items-start gap-2.5">
                        <svg class="w-4 h-4 text-emerald-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                @if (session('info'))
                    <div class="rounded-xl bg-blue-50 border border-blue-200 p-4 text-xs sm:text-sm text-blue-800 flex items-start gap-2.5">
                        <svg class="w-4 h-4 text-blue-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                        <span>{{ session('info') }}</span>
                    </div>
                @endif

                <!-- Login Form -->
                <form method="POST" action="{{ route('login.post') }}" class="space-y-4" id="mainLoginForm">
                    @csrf

                    <!-- FIELD: USERNAME ATAU EMAIL -->
                    <div>
                        <label for="emailInput" class="block text-xs sm:text-sm font-semibold text-neutral-800 mb-1.5">
                            Username atau Email
                        </label>
                        <div class="relative">
                            <!-- Left Icon: User / Profile -->
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-neutral-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                            </div>
                            <input type="text" name="email" id="emailInput" value="{{ old('email', $defaultEmail ?? '') }}" required placeholder="nama@perusahaan.com" class="w-full pl-11 pr-4 py-3 rounded-xl border border-neutral-300 text-neutral-800 text-sm focus:outline-none focus:ring-2 focus:ring-[#7b181b]/25 focus:border-[#7b181b] transition placeholder-neutral-400 bg-white">
                        </div>
                    </div>

                    <!-- FIELD 3: KATA SANDI -->
                    <div>
                        <label for="passwordInput" class="block text-xs sm:text-sm font-semibold text-neutral-800 mb-1.5">
                            Kata Sandi
                        </label>
                        <div class="relative">
                            <!-- Left Icon: Lock -->
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-neutral-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                </svg>
                            </div>
                            <input type="password" name="password" id="passwordInput" value="{{ $defaultPassword ?? '' }}" required placeholder="Masukkan kata sandi" class="w-full pl-11 pr-11 py-3 rounded-xl border border-neutral-300 text-neutral-800 text-sm focus:outline-none focus:ring-2 focus:ring-[#7b181b]/25 focus:border-[#7b181b] transition placeholder-neutral-400 bg-white">
                            
                            <!-- Toggle Visibility Eye Button -->
                            <button type="button" onclick="togglePasswordVisibility()" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-neutral-400 hover:text-neutral-700 focus:outline-none transition-colors" title="Lihat / Sembunyikan Kata Sandi">
                                <svg id="eyeIcon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                                <svg id="eyeOffIcon" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- ROW: INGAT SAYA & LUPA KATA SANDI -->
                    <div class="flex items-center justify-between pt-1">
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input type="checkbox" name="remember" id="remember" class="w-4 h-4 rounded text-[#7b181b] border-neutral-300 focus:ring-[#7b181b] focus:ring-offset-0 cursor-pointer">
                            <span class="text-xs sm:text-sm text-neutral-600">Ingat saya</span>
                        </label>
                        <button type="button" onclick="openForgotModal()" class="text-xs sm:text-sm font-semibold text-blue-600 hover:text-blue-700 hover:underline">
                            Lupa kata sandi?
                        </button>
                    </div>

                    <!-- TOMBOL MASUK -->
                    <div class="pt-2">
                        <button type="submit" class="w-full py-3.5 px-4 bg-[#7b181b] hover:bg-[#651316] active:scale-[0.99] text-white font-semibold text-sm sm:text-base rounded-xl transition duration-150 shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-[#7b181b] focus:ring-offset-2 flex items-center justify-center gap-2">
                            <span>Masuk</span>
                        </button>
                    </div>
                </form>

            </div>

            <!-- Footer Small Screen Copyright -->
            <div class="mt-10 lg:hidden text-center text-xs text-neutral-400">
                &copy; <span class="current-year">{{ date('Y') }}</span> PT Musashi Auto Parts Indonesia
            </div>

        </section>

    </div>

    <!-- ============================================================== -->
    <!-- FLOATING BUTTON: Akun Uji Coba Demo (Non-intrusive)           -->
    <!-- ============================================================== -->
    <button type="button" onclick="openDemoDrawer()" class="fixed bottom-4 right-4 z-40 bg-neutral-900/90 hover:bg-neutral-900 text-white text-xs font-semibold px-4 py-2.5 rounded-full shadow-xl border border-neutral-700 hover:border-neutral-500 backdrop-blur-md flex items-center gap-2.5 transition duration-200 group">
        <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
        <span class="tracking-wide">⚡ Akun Demo / Uji Coba</span>
        <span class="text-[10px] bg-neutral-800 text-neutral-300 px-2 py-0.5 rounded-full border border-neutral-700 group-hover:bg-[#7b181b] group-hover:text-white transition">Buka</span>
    </button>

    <!-- ============================================================== -->
    <!-- SLIDE-OVER DRAWER: Quick Test Accounts Center                  -->
    <!-- ============================================================== -->
    <div id="demoDrawer" class="fixed inset-0 z-50 overflow-hidden hidden" aria-labelledby="slide-over-title" role="dialog" aria-modal="true">
        <!-- Backdrop -->
        <div id="demoBackdrop" onclick="closeDemoDrawer()" class="fixed inset-0 bg-neutral-950/60 backdrop-blur-xs transition-opacity duration-300 opacity-0"></div>

        <div class="fixed inset-y-0 right-0 max-w-full flex pl-10">
            <div id="demoDrawerPanel" class="w-screen max-w-lg bg-neutral-900 text-neutral-100 shadow-2xl flex flex-col transform translate-x-full transition-transform duration-300 ease-in-out border-l border-neutral-800">
                
                <!-- Drawer Header -->
                <div class="p-5 border-b border-neutral-800 flex items-center justify-between bg-neutral-900/95">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-[#7b181b] flex items-center justify-center text-white font-bold text-sm shadow">
                            ⚡
                        </div>
                        <div>
                            <h3 class="font-bold text-base text-white leading-tight">Pusat Akun Uji Coba</h3>
                            <p class="text-xs text-neutral-400 mt-0.5">Pilih akun untuk mengisi form atau login instan</p>
                        </div>
                    </div>
                    <button type="button" onclick="closeDemoDrawer()" class="w-8 h-8 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-neutral-400 hover:text-white flex items-center justify-center transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <!-- Filter Role Pills -->
                <div class="p-3 bg-neutral-950 border-b border-neutral-800 flex items-center gap-1.5 overflow-x-auto custom-scrollbar">
                    <button type="button" onclick="filterDrawerRole('all', this)" class="drawer-filter-btn px-3 py-1 rounded-lg text-xs font-semibold bg-[#7b181b] text-white">Semua</button>
                    <button type="button" onclick="filterDrawerRole('foreman', this)" class="drawer-filter-btn px-3 py-1 rounded-lg text-xs font-medium text-neutral-400 hover:bg-neutral-800">Foreman</button>
                    <button type="button" onclick="filterDrawerRole('section_head', this)" class="drawer-filter-btn px-3 py-1 rounded-lg text-xs font-medium text-neutral-400 hover:bg-neutral-800">Section Head</button>
                    <button type="button" onclick="filterDrawerRole('kj5', this)" class="drawer-filter-btn px-3 py-1 rounded-lg text-xs font-medium text-neutral-400 hover:bg-neutral-800">KJ &gt; 5 / Gol 4</button>
                    <button type="button" onclick="filterDrawerRole('department_head', this)" class="drawer-filter-btn px-3 py-1 rounded-lg text-xs font-medium text-neutral-400 hover:bg-neutral-800">Dept Head</button>
                    <button type="button" onclick="filterDrawerRole('division_head', this)" class="drawer-filter-btn px-3 py-1 rounded-lg text-xs font-medium text-neutral-400 hover:bg-neutral-800">Div Head</button>
                    <button type="button" onclick="filterDrawerRole('admin', this)" class="drawer-filter-btn px-3 py-1 rounded-lg text-xs font-medium text-neutral-400 hover:bg-neutral-800">Superadmin</button>
                </div>

                <!-- Accounts List -->
                <div class="flex-1 overflow-y-auto p-4 space-y-3 custom-scrollbar" id="drawerAccountList">
                    @forelse($testAccounts ?? [] as $acc)
                        <div class="drawer-account-item p-3.5 rounded-xl bg-neutral-800/80 hover:bg-neutral-800 border border-neutral-700/70 transition space-y-2.5"
                             data-role="{{ $acc['role'] }}"
                             data-email="{{ $acc['email'] }}"
                             data-nrp="{{ $acc['nrp'] }}">
                            
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h4 class="font-bold text-sm text-white">{{ $acc['nama'] }}</h4>
                                        <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-neutral-700 text-neutral-300">NRP {{ $acc['nrp'] }}</span>
                                    </div>
                                    <p class="text-xs text-neutral-400 mt-0.5">{{ $acc['email'] }}</p>
                                </div>
                                <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full 
                                    @if($acc['role'] == 'foreman') bg-emerald-950 text-emerald-300 border border-emerald-800
                                    @elseif($acc['role'] == 'section_head') bg-blue-950 text-blue-300 border border-blue-800
                                    @elseif(in_array($acc['role'], ['GOL 4 NON KASIE', 'SENIOR STAFF', 'STAFF', 'OPERATOR (ORIGIN : STAFF KJ5)']) || str_contains($acc['role'], 'STAFF') || str_contains($acc['role'], 'NON KASIE')) bg-indigo-950 text-indigo-300 border border-indigo-800
                                    @elseif($acc['role'] == 'department_head') bg-purple-950 text-purple-300 border border-purple-800
                                    @elseif($acc['role'] == 'division_head') bg-amber-950 text-amber-300 border border-amber-800
                                    @else bg-rose-950 text-rose-300 border border-rose-800 @endif">
                                    {{ $acc['role_label'] }}
                                </span>
                            </div>

                            <div class="text-[11px] text-neutral-400 flex items-center justify-between pt-1 border-t border-neutral-700/40">
                                <span>Dept: <strong class="text-neutral-200">{{ $acc['departemen'] }}</strong> ({{ $acc['seksi'] }})</span>
                                @if(!empty($acc['kasus_badge']))
                                    <span class="text-[10px] bg-neutral-900 text-neutral-300 px-2 py-0.5 rounded">{{ $acc['kasus_badge'] }}</span>
                                @endif
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex items-center gap-2 pt-1">
                                <button type="button" 
                                        onclick="fillFormFromDrawer('{{ $acc['email'] }}', 'password123', '{{ $acc['role'] }}')"
                                        class="flex-1 py-1.5 px-3 rounded-lg bg-neutral-700 hover:bg-[#7b181b] text-white text-xs font-semibold transition flex items-center justify-center gap-1.5 border border-neutral-600">
                                    <span>Pilih & Isi Form</span>
                                </button>
                                <a href="{{ route('quick.login', $acc['nrp']) }}"
                                   class="py-1.5 px-3 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold transition flex items-center justify-center gap-1">
                                    <span>Masuk Cepat</span>
                                    &rarr;
                                </a>
                            </div>

                        </div>
                    @empty
                        <div class="text-center py-10 text-neutral-400 text-xs">
                            Tidak ada data akun pengujian.
                        </div>
                    @endforelse
                </div>

                <!-- Drawer Footer -->
                <div class="p-4 bg-neutral-950 border-t border-neutral-800 text-xs text-neutral-400 text-center">
                    Semua akun demo menggunakan password default: <code class="text-amber-400 font-mono font-bold">password123</code>
                </div>

            </div>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- MODAL: Lupa Kata Sandi                                         -->
    <!-- ============================================================== -->
    <div id="forgotModal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <!-- Backdrop -->
            <div onclick="closeForgotModal()" class="fixed inset-0 bg-neutral-950/60 backdrop-blur-xs transition-opacity"></div>

            <div class="relative bg-white rounded-2xl max-w-md w-full p-6 text-left shadow-2xl border border-neutral-200 transform transition-all my-8">
                <div class="flex items-center justify-between pb-4 border-b border-neutral-200">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-red-50 text-[#7b181b] flex items-center justify-center font-bold">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-neutral-900">Bantuan Lupa Kata Sandi</h3>
                            <p class="text-xs text-neutral-500">PT Musashi Auto Parts Indonesia</p>
                        </div>
                    </div>
                    <button type="button" onclick="closeForgotModal()" class="w-8 h-8 rounded-lg bg-neutral-100 hover:bg-neutral-200 text-neutral-500 hover:text-neutral-800 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div class="py-4 space-y-3 text-sm text-neutral-600">
                    <p>
                        Untuk menjaga keamanan data dan persetujuan penilaian kerja di lingkungan PT Musashi Auto Parts Indonesia, pemulihan kata sandi dilakukan secara terpusat oleh <strong class="text-neutral-900">Superadmin / IT Administrator</strong>.
                    </p>
                    <div class="p-3.5 rounded-xl bg-neutral-50 border border-neutral-200 space-y-1.5 text-xs">
                        <div class="font-semibold text-neutral-800">Langkah Pemulihan:</div>
                        <ul class="list-disc list-inside space-y-1 text-neutral-600 pl-1">
                            <li>Siapkan Nomor Registrasi Pokok (<strong class="text-neutral-800">NRP</strong>) Anda.</li>
                            <li>Hubungi PIC IT Support atau Superadmin via pesan internal atau WhatsApp HR.</li>
                            <li>Kata sandi sementara akan diterbitkan dan dikirimkan ke email internal Anda.</li>
                        </ul>
                    </div>
                </div>

                <div class="pt-3 border-t border-neutral-200 flex items-center justify-end gap-2">
                    <button type="button" onclick="closeForgotModal()" class="w-full py-2.5 px-4 rounded-xl bg-[#7b181b] hover:bg-[#651316] text-white text-xs font-semibold transition">
                        Saya Mengerti
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- JAVASCRIPT: Interactivity, Role Sync, and Drawer Management    -->
    <!-- ============================================================== -->
    <script>
        // 1. Password Visibility Toggle
        function togglePasswordVisibility() {
            const pwdInput = document.getElementById('passwordInput');
            const eyeIcon = document.getElementById('eyeIcon');
            const eyeOffIcon = document.getElementById('eyeOffIcon');

            if (pwdInput.type === 'password') {
                pwdInput.type = 'text';
                eyeIcon.classList.add('hidden');
                eyeOffIcon.classList.remove('hidden');
            } else {
                pwdInput.type = 'password';
                eyeIcon.classList.remove('hidden');
                eyeOffIcon.classList.add('hidden');
            }
        }

        // 2. Slide-over Demo Drawer Controls
        function openDemoDrawer() {
            const drawer = document.getElementById('demoDrawer');
            const backdrop = document.getElementById('demoBackdrop');
            const panel = document.getElementById('demoDrawerPanel');

            drawer.classList.remove('hidden');
            setTimeout(() => {
                backdrop.classList.remove('opacity-0');
                backdrop.classList.add('opacity-100');
                panel.classList.remove('translate-x-full');
                panel.classList.add('translate-x-0');
            }, 10);
        }

        function closeDemoDrawer() {
            const drawer = document.getElementById('demoDrawer');
            const backdrop = document.getElementById('demoBackdrop');
            const panel = document.getElementById('demoDrawerPanel');

            backdrop.classList.remove('opacity-100');
            backdrop.classList.add('opacity-0');
            panel.classList.remove('translate-x-0');
            panel.classList.add('translate-x-full');

            setTimeout(() => {
                drawer.classList.add('hidden');
            }, 300);
        }

        // Close drawer on Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeDemoDrawer();
                closeForgotModal();
            }
        });

        // 3. Fill Form Automatically from Drawer
        function fillFormFromDrawer(email, password, role) {
            const emailInput = document.getElementById('emailInput');
            const passwordInput = document.getElementById('passwordInput');

            if (emailInput) emailInput.value = email;
            if (passwordInput) passwordInput.value = password;

            // Trigger visual pulse effect on input elements
            [emailInput, passwordInput].forEach(el => {
                if (!el) return;
                el.classList.remove('highlight-pulse');
                void el.offsetWidth; // re-flow
                el.classList.add('highlight-pulse');
            });

            // Close drawer
            closeDemoDrawer();

            // Focus on email input
            if (emailInput) emailInput.focus();
        }

        // 4. Filter Drawer Accounts
        let activeDrawerRole = 'all';
        function filterDrawerRole(role, btn) {
            activeDrawerRole = role;

            // Update button styles
            document.querySelectorAll('.drawer-filter-btn').forEach(b => {
                b.className = 'drawer-filter-btn px-3 py-1 rounded-lg text-xs font-medium text-neutral-400 hover:bg-neutral-800 transition';
            });
            btn.className = 'drawer-filter-btn px-3 py-1 rounded-lg text-xs font-semibold bg-[#7b181b] text-white transition';

            // Filter items
            const kj5Roles = ['GOL 4 NON KASIE', 'SENIOR STAFF', 'STAFF', 'OPERATOR (ORIGIN : STAFF KJ5)'];
            const items = document.querySelectorAll('.drawer-account-item');
            items.forEach(item => {
                const itemRole = item.getAttribute('data-role') || '';
                const isKj5 = kj5Roles.includes(itemRole) || itemRole.includes('NON KASIE') || itemRole.includes('STAFF');
                const match = (role === 'all') ||
                              (role === 'admin' && (itemRole === 'admin' || itemRole === 'superadmin')) ||
                              (role === 'kj5' && isKj5) ||
                              (itemRole === role);
                item.style.display = match ? '' : 'none';
            });
        }

        // 5. Dynamic Real-time Copyright Year
        document.addEventListener('DOMContentLoaded', function() {
            const currentYear = new Date().getFullYear();
            document.querySelectorAll('.current-year').forEach(el => {
                el.textContent = currentYear;
            });
        });

        // 5. Forgot Password Modal
        function openForgotModal() {
            document.getElementById('forgotModal').classList.remove('hidden');
        }
        function closeForgotModal() {
            document.getElementById('forgotModal').classList.add('hidden');
        }
    </script>

</body>
</html>
