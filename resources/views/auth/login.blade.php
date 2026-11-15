<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - TTD Digital Penilaian Karya</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ asset('musashi.png') }}?v={{ file_exists(public_path('musashi.png')) ? filemtime(public_path('musashi.png')) : '1' }}">
    <link rel="shortcut icon" type="image/png" href="{{ asset('musashi.png') }}?v={{ file_exists(public_path('musashi.png')) ? filemtime(public_path('musashi.png')) : '1' }}">
    <link rel="apple-touch-icon" href="{{ asset('musashi.png') }}?v={{ file_exists(public_path('musashi.png')) ? filemtime(public_path('musashi.png')) : '1' }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #150505;
        }
        .font-mono-code {
            font-family: 'JetBrains Mono', monospace;
        }
        .bg-navy-900 { background-color: #150505; }
        .bg-navy-800 { background-color: #230808; }
        .bg-navy-700 { background-color: #330d0c; }
        .border-navy-600 { border-color: #551918; }
        .border-navy-500 { border-color: #7b2624; }
        
        .glow-accent {
            box-shadow: 0 0 25px -5px rgba(146, 48, 46, 0.45);
        }
        .glow-green {
            box-shadow: 0 0 25px -5px rgba(16, 185, 129, 0.35);
        }
        .input-highlight {
            animation: pulse-border 1.2s ease-in-out;
        }
        @keyframes pulse-border {
            0% { border-color: #92302e; box-shadow: 0 0 0 3px rgba(146, 48, 46, 0.4); }
            50% { border-color: #b84240; box-shadow: 0 0 0 5px rgba(184, 66, 64, 0.6); }
            100% { border-color: #1F2E5A; box-shadow: none; }
        }
    </style>
</head>
<body class="min-h-full text-slate-200 antialiased py-6 sm:py-8 px-3 sm:px-6 lg:px-8 2xl:px-12 selection:bg-[#92302e] selection:text-white">

    <div class="w-full max-w-7xl 2xl:max-w-[1600px] 3xl:max-w-[1920px] mx-auto space-y-6 sm:space-y-8">
        
        <!-- Header Branding -->
        <header class="text-center pt-2 pb-4">
            <div class="inline-flex items-center gap-3 px-4 py-1.5 rounded-full bg-red-950/70 border border-[#7b2624] text-red-200 text-xs font-semibold mb-4 shadow-sm">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                PT Musashi Auto Parts Indonesia &bull; Sistem Penilaian Karya 2025
            </div>
            <div class="flex items-center justify-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-[#92302e] to-[#b84240] text-white font-extrabold text-2xl flex items-center justify-center shadow-lg shadow-[#92302e]/50 border border-white/20">
                    PK
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white">
                    TTD Digital Penilaian Karya
                </h1>
            </div>
            <p class="mt-2 text-sm text-slate-400 max-w-2xl mx-auto">
                Platform persetujuan berjenjang real-time terintegrasi: <span class="text-emerald-400 font-semibold">Foreman</span> &rarr; <span class="text-blue-400 font-semibold">Section Head</span> &rarr; <span class="text-purple-400 font-semibold">Department Head</span> &rarr; <span class="text-amber-400 font-semibold">Division Head</span>.
            </p>
        </header>

        <!-- Main Layout: 2 Columns (Form Login & Pusat Akun Uji) -->
        <main class="grid grid-cols-1 lg:grid-cols-12 2xl:grid-cols-12 gap-6 lg:gap-8 items-start">
            
            <!-- LEFT COLUMN: Form Login (5 Cols) -->
            <section class="lg:col-span-5 2xl:col-span-5 bg-navy-800 border border-navy-600 rounded-3xl p-5 sm:p-7 2xl:p-8 shadow-2xl relative overflow-hidden" id="loginFormSection">
                <!-- Top Decorative Glow -->
                <div class="absolute -top-16 -right-16 w-44 h-44 bg-[#92302e]/25 rounded-full blur-3xl pointer-events-none"></div>

                <div class="mb-6">
                    <h2 class="text-xl font-bold text-white flex items-center gap-2">
                        <span>🔐 Masuk ke Sistem</span>
                    </h2>
                    <p class="text-xs text-slate-400 mt-1">
                        Pilih hak akses role (opsional), lalu masukkan email dan password akun Anda.
                    </p>
                </div>

                @if ($errors->any())
                <div class="rounded-2xl bg-rose-950/70 border border-rose-800/80 p-4 mb-6 text-sm text-rose-200">
                    <div class="flex items-start gap-2.5">
                        <span class="text-rose-400 text-base flex-shrink-0">⚠️</span>
                        <div class="space-y-1">
                            @foreach ($errors->all() as $error)
                                <p class="text-xs sm:text-sm font-medium">{{ $error }}</p>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endif

                @if (session('info'))
                <div class="rounded-2xl bg-red-950/70 border border-[#7b2624] p-4 mb-6 text-xs text-red-200 flex items-center gap-2">
                    <span>ℹ️</span> <span>{{ session('info') }}</span>
                </div>
                @endif

                <form class="space-y-5" action="{{ route('login.post') }}" method="POST" id="mainLoginForm">
                    @csrf

                    <!-- 1. ROLE SELECTOR (Dropdown / Pilihan Role) -->
                    <div>
                        <label for="roleSelector" class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5 flex items-center justify-between">
                            <span>1. Pilih Hak Akses / Role</span>
                            <span class="text-[10px] text-red-300 font-normal">Otomatis / Validasi Role</span>
                        </label>
                        <div class="relative">
                            <select id="roleSelector" name="role" 
                                    class="w-full px-4 py-3 bg-navy-700 border border-navy-600 rounded-xl text-white text-sm focus:outline-none focus:ring-2 focus:ring-[#92302e] focus:border-[#92302e] transition-all font-medium appearance-none cursor-pointer">
                                <option value="all">🌐 Semua Role (Deteksi Otomatis dari Akun)</option>
                                <option value="foreman">👷 Foreman (Penilai Awal Lapangan / Shift)</option>
                                <option value="section_head">👔 Section Head (Pemeriksa & Evaluasi Seksi)</option>
                                <option value="department_head">🏢 Department Head (Penetapan & Docking Final)</option>
                                <option value="division_head">👑 Division Head (Pengesahan Tingkat Divisi)</option>
                                <option value="admin">🛡️ Admin / Superadmin (Operasional Sistem)</option>
                            </select>
                            <div class="absolute inset-y-0 right-0 flex items-center px-3.5 pointer-events-none text-slate-400 text-xs">
                                ▼
                            </div>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1">
                            Pilih role untuk memastikan Anda masuk sesuai hak akses yang diinginkan.
                        </p>
                    </div>

                    <!-- 2. EMAIL INPUT -->
                    <div>
                        <label for="emailInput" class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5 flex items-center justify-between">
                            <span>2. Email Perusahaan</span>
                            <span class="text-[10px] text-slate-400 font-normal">Contoh: saryanta@musashi.co.id</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 text-sm">
                                ✉️
                            </div>
                            <input id="emailInput" name="email" type="email" autocomplete="email" required
                                   value="{{ old('email', $defaultEmail) }}"
                                   placeholder="nama.user@musashi.co.id"
                                   class="w-full pl-10 pr-4 py-3 bg-navy-700 border border-navy-600 rounded-xl text-white text-sm focus:outline-none focus:ring-2 focus:ring-[#92302e] focus:border-[#92302e] transition-all placeholder-slate-500 font-mono-code">
                        </div>
                    </div>

                    <!-- 3. PASSWORD INPUT -->
                    <div>
                        <label for="passwordInput" class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5 flex items-center justify-between">
                            <span>3. Password Akun</span>
                            <span class="text-[10px] text-emerald-400 font-medium">Standar: password123</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 text-sm">
                                🔑
                            </div>
                            <input id="passwordInput" name="password" type="password" autocomplete="current-password" required
                                   value="{{ $defaultPassword }}"
                                   class="w-full pl-10 pr-12 py-3 bg-navy-700 border border-navy-600 rounded-xl text-white text-sm focus:outline-none focus:ring-2 focus:ring-[#92302e] focus:border-[#92302e] transition-all placeholder-slate-500 font-mono-code">
                            <button type="button" onclick="togglePasswordVisibility()" 
                                    class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-xs text-slate-400 hover:text-white transition-colors" title="Lihat/Sembunyikan Password">
                                <span id="togglePasswordIcon">👁️</span>
                            </button>
                        </div>
                    </div>

                    <!-- Remember Me -->
                    <div class="flex items-center justify-between pt-1">
                        <label class="flex items-center gap-2 cursor-pointer text-xs text-slate-300 select-none">
                            <input id="remember" name="remember" type="checkbox" checked
                                   class="w-4 h-4 rounded bg-navy-700 border-navy-600 text-[#92302e] focus:ring-[#92302e] focus:ring-offset-navy-800">
                            <span>Ingat sesi saya di peramban ini</span>
                        </label>
                    </div>

                    <!-- SUBMIT BUTTON -->
                    <div>
                        <button type="submit" 
                                class="w-full flex justify-center items-center gap-2 py-3.5 px-4 rounded-xl text-sm font-bold text-white bg-[#92302e] hover:bg-[#7e2524] active:scale-[0.99] transition-all shadow-lg shadow-[#92302e]/40 glow-accent cursor-pointer">
                            <span>Masuk ke Sistem</span>
                            <span>&rarr;</span>
                        </button>
                    </div>
                </form>

                <!-- Quick Help / Instructions -->
                <div class="mt-6 pt-5 border-t border-navy-600/70 text-xs text-slate-400 space-y-2">
                    <div class="flex items-center gap-2 text-slate-300 font-semibold">
                        <span>💡 Tips Pengujian Cepat:</span>
                    </div>
                    <p class="leading-relaxed">
                        Klik tombol <strong>[ 📝 Isi Form ]</strong> pada kartu di sebelah kanan untuk mengisi email & password secara otomatis, atau klik <strong>[ ⚡ Login 1-Klik ]</strong> untuk langsung masuk tanpa mengetik.
                    </p>
                </div>
            </section>

            <!-- RIGHT COLUMN: Pusat Akun Uji Coba Berdasarkan Role & Skenario Kasus (7 Cols) -->
            <section class="lg:col-span-7 2xl:col-span-7 bg-navy-800 border border-navy-600 rounded-3xl p-5 sm:p-7 2xl:p-8 shadow-2xl space-y-6">
                
                @php
                    $roleCounts = [
                        'all' => count($testAccounts),
                        'foreman' => collect($testAccounts)->where('role', 'foreman')->count(),
                        'section_head' => collect($testAccounts)->where('role', 'section_head')->count(),
                        'department_head' => collect($testAccounts)->where('role', 'department_head')->count(),
                        'division_head' => collect($testAccounts)->where('role', 'division_head')->count(),
                        'admin' => collect($testAccounts)->where('role', 'admin')->count(),
                    ];
                @endphp

                <!-- Section Header -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-navy-600/70 pb-5">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="px-2.5 py-0.5 rounded-full bg-[#92302e]/20 text-red-200 text-xs font-bold border border-[#92302e]/40">
                                {{ $roleCounts['all'] }} Akun Lengkap Siap Uji
                            </span>
                        </div>
                        <h2 class="text-xl font-bold text-white mt-1.5">
                            Pusat Akun Uji &amp; Skenario Kasus
                        </h2>
                        <p class="text-xs text-slate-400 mt-0.5">
                            {{ $roleCounts['foreman'] }} Foreman (F1/F2 CAT 1-3) &bull; {{ $roleCounts['section_head'] }} Section Head &bull; {{ $roleCounts['department_head'] }} Dept Head &bull; {{ $roleCounts['division_head'] }} Div Head &bull; {{ $roleCounts['admin'] }} Admin
                        </p>
                    </div>
                    <div class="flex items-center gap-2 self-start sm:self-auto">
                        <span class="text-xs text-slate-400 bg-navy-700 px-3 py-1.5 rounded-xl border border-navy-600 font-mono-code">
                            Pass: <strong class="text-emerald-400">password123</strong>
                        </span>
                    </div>
                </div>

                <!-- FILTER BAR 1: ROLE FILTER TABS -->
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2">
                        Filter Berdasarkan Role:
                    </label>
                    <div class="flex flex-wrap gap-1.5" id="roleFilterButtons">
                        <button type="button" onclick="filterByRole('all', this)" 
                                class="role-filter-btn px-3 py-1.5 rounded-xl text-xs font-bold bg-[#92302e] text-white shadow-sm transition-all border border-[#b84240]">
                            Semua ({{ $roleCounts['all'] }})
                        </button>
                        <button type="button" onclick="filterByRole('foreman', this)" 
                                class="role-filter-btn px-3 py-1.5 rounded-xl text-xs font-medium bg-navy-700 text-slate-300 hover:text-white hover:bg-navy-600 transition-all border border-navy-600">
                            👷 Foreman ({{ $roleCounts['foreman'] }})
                        </button>
                        <button type="button" onclick="filterByRole('section_head', this)" 
                                class="role-filter-btn px-3 py-1.5 rounded-xl text-xs font-medium bg-navy-700 text-slate-300 hover:text-white hover:bg-navy-600 transition-all border border-navy-600">
                            👔 Section Head ({{ $roleCounts['section_head'] }})
                        </button>
                        <button type="button" onclick="filterByRole('department_head', this)" 
                                class="role-filter-btn px-3 py-1.5 rounded-xl text-xs font-medium bg-navy-700 text-slate-300 hover:text-white hover:bg-navy-600 transition-all border border-navy-600">
                            🏢 Dept Head ({{ $roleCounts['department_head'] }})
                        </button>
                        <button type="button" onclick="filterByRole('division_head', this)" 
                                class="role-filter-btn px-3 py-1.5 rounded-xl text-xs font-medium bg-navy-700 text-slate-300 hover:text-white hover:bg-navy-600 transition-all border border-navy-600">
                            👑 Div Head ({{ $roleCounts['division_head'] }})
                        </button>
                        <button type="button" onclick="filterByRole('admin', this)" 
                                class="role-filter-btn px-3 py-1.5 rounded-xl text-xs font-medium bg-navy-700 text-slate-300 hover:text-white hover:bg-navy-600 transition-all border border-navy-600">
                            🛡️ Admin ({{ $roleCounts['admin'] }})
                        </button>
                    </div>
                </div>

                <!-- FILTER BAR 2: SCENARIO / KASUS FILTER TABS -->
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2">
                        Filter Berdasarkan Skenario Kasus:
                    </label>
                    <div class="flex flex-wrap gap-1.5" id="caseFilterButtons">
                        <button type="button" onclick="filterByCase('all', this)" 
                                class="case-filter-btn px-3 py-1.5 rounded-xl text-xs font-bold bg-slate-700 text-white shadow-sm transition-all border border-slate-600">
                            Semua Skenario
                        </button>
                        <button type="button" onclick="filterByCase('kasus1', this)" 
                                class="case-filter-btn px-3 py-1.5 rounded-xl text-xs font-medium bg-navy-700 text-slate-300 hover:text-white hover:bg-navy-600 transition-all border border-navy-600">
                            🎯 Kasus 1: PROD 2 (Foreman CAT &rarr; SH &rarr; SUPARDI &rarr; ATASAN)
                        </button>
                        <button type="button" onclick="filterByCase('kasus2', this)" 
                                class="case-filter-btn px-3 py-1.5 rounded-xl text-xs font-medium bg-navy-700 text-slate-300 hover:text-white hover:bg-navy-600 transition-all border border-navy-600">
                            ⚡ Kasus 2: PROD 2 FACTORY 3 (AL QADRI Tanpa Foreman)
                        </button>
                        <button type="button" onclick="filterByCase('kasus3', this)" 
                                class="case-filter-btn px-3 py-1.5 rounded-xl text-xs font-medium bg-navy-700 text-slate-300 hover:text-white hover:bg-navy-600 transition-all border border-navy-600">
                            🔀 Kasus 3: PROD 1 (Alur Seksi Lainnya)
                        </button>
                    </div>
                </div>

                <!-- ACCOUNT CARDS LIST (Scrollable Container) -->
                <div class="space-y-3 max-h-[580px] overflow-y-auto pr-1.5 custom-scrollbar" id="accountCardsContainer">
                    
                    @foreach ($testAccounts as $acc)
                    @php
                        // Color styling per role
                        $roleColors = match($acc['role']) {
                            'foreman' => [
                                'bg' => 'bg-emerald-500/10',
                                'text' => 'text-emerald-400',
                                'border' => 'border-emerald-500/30',
                                'badge_bg' => 'bg-emerald-950/80',
                                'avatar' => 'from-emerald-600 to-teal-500',
                            ],
                            'section_head' => [
                                'bg' => 'bg-blue-500/10',
                                'text' => 'text-blue-400',
                                'border' => 'border-blue-500/30',
                                'badge_bg' => 'bg-blue-950/80',
                                'avatar' => 'from-blue-600 to-cyan-500',
                            ],
                            'department_head' => [
                                'bg' => 'bg-purple-500/10',
                                'text' => 'text-purple-400',
                                'border' => 'border-purple-500/30',
                                'badge_bg' => 'bg-purple-950/80',
                                'avatar' => 'from-purple-600 to-indigo-500',
                            ],
                            'division_head' => [
                                'bg' => 'bg-amber-500/10',
                                'text' => 'text-amber-400',
                                'border' => 'border-amber-500/30',
                                'badge_bg' => 'bg-amber-950/80',
                                'avatar' => 'from-amber-600 to-orange-500',
                            ],
                            default => [
                                'bg' => 'bg-rose-500/10',
                                'text' => 'text-rose-400',
                                'border' => 'border-rose-500/30',
                                'badge_bg' => 'bg-rose-950/80',
                                'avatar' => 'from-rose-600 to-pink-500',
                            ],
                        };

                        // Two initials for avatar
                        $words = explode(' ', $acc['nama']);
                        $initials = strtoupper(substr($words[0] ?? 'U', 0, 1) . substr($words[1] ?? '', 0, 1));
                    @endphp

                    <div class="account-card p-4 rounded-2xl bg-navy-700/80 border border-navy-600 hover:border-navy-500 transition-all flex flex-col sm:flex-row sm:items-center justify-between gap-4 group"
                         data-role="{{ $acc['role'] }}"
                         data-kasus="{{ $acc['kasus'] }}"
                         data-email="{{ $acc['email'] }}"
                         data-password="password123">
                        
                        <!-- Left: Info & Avatar -->
                        <div class="flex items-start gap-3.5 min-w-0">
                            <!-- Avatar -->
                            <div class="w-11 h-11 rounded-2xl bg-gradient-to-tr {{ $roleColors['avatar'] }} text-white font-extrabold text-sm flex items-center justify-center flex-shrink-0 shadow-md border border-white/10 mt-0.5">
                                {{ $initials }}
                            </div>

                            <div class="min-w-0 space-y-1">
                                <!-- Top Row: Name, NRP & Role Badge -->
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-bold text-white text-sm group-hover:text-blue-300 transition-colors">
                                        {{ $acc['nama'] }}
                                    </span>
                                    <span class="text-[11px] font-mono-code text-slate-400 bg-navy-800 px-1.5 py-0.5 rounded border border-navy-600">
                                        {{ $acc['nrp'] }}
                                    </span>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $roleColors['bg'] }} {{ $roleColors['text'] }} {{ $roleColors['border'] }}">
                                        {{ $acc['role_label'] }}
                                    </span>
                                </div>

                                <!-- Middle Row: Department, Section & Case Tag -->
                                <div class="flex flex-wrap items-center gap-2 text-xs text-slate-400">
                                    <span class="text-slate-300 font-medium">{{ $acc['departemen'] }}</span>
                                    <span>&bull;</span>
                                    <span>{{ $acc['seksi'] }}</span>
                                    <span>&bull;</span>
                                    <span class="text-[10px] font-semibold text-emerald-300 bg-emerald-950/70 px-2 py-0.5 rounded-md border border-emerald-800/60">
                                        {{ $acc['kasus_badge'] }}
                                    </span>
                                </div>

                                <!-- Email & Description -->
                                <div class="text-[11px] text-slate-400 flex flex-wrap items-center gap-1.5 pt-0.5">
                                    <span class="font-mono-code text-blue-300 bg-navy-900/60 px-2 py-0.5 rounded border border-navy-700">
                                        {{ $acc['email'] }}
                                    </span>
                                    <span class="text-slate-500 hidden sm:inline">&bull;</span>
                                    <span class="text-slate-400 line-clamp-1 italic">{{ $acc['deskripsi'] }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Right: Action Buttons -->
                        <div class="flex sm:flex-col items-center sm:items-end justify-between sm:justify-center gap-2 flex-shrink-0 pt-2 sm:pt-0 border-t sm:border-t-0 border-navy-600/60">
                            <!-- Button 1: Isi Form -->
                            <button type="button" 
                                    onclick="fillLoginForm('{{ $acc['email'] }}', 'password123', '{{ $acc['role'] }}')"
                                    class="px-3 py-1.5 rounded-xl bg-navy-800 hover:bg-blue-900/40 text-blue-300 hover:text-white border border-navy-600 hover:border-blue-500 text-xs font-semibold flex items-center gap-1.5 transition-all active:scale-95 cursor-pointer"
                                    title="Isi email & password pada form di samping">
                                <span>📝</span>
                                <span>Isi Form</span>
                            </button>

                            <!-- Button 2: Quick Login 1-Klik -->
                            <a href="{{ route('quick.login', $acc['id']) }}" 
                               class="px-3.5 py-1.5 rounded-xl bg-[#92302e] hover:bg-[#7e2524] text-white text-xs font-bold flex items-center gap-1.5 transition-all shadow shadow-[#92302e]/40 active:scale-95"
                               title="Langsung login masuk ke sistem">
                                <span>⚡</span>
                                <span>Login 1-Klik</span>
                            </a>
                        </div>

                    </div>
                    @endforeach

                </div>

                <!-- Footer Summary of Test Scenarios -->
                <div class="pt-4 border-t border-navy-600/70 grid grid-cols-1 md:grid-cols-3 gap-3 text-[11px] text-slate-400">
                    <div class="p-2.5 rounded-xl bg-navy-700/50 border border-navy-600/60">
                        <strong class="text-emerald-400 block mb-0.5">🎯 Kasus 1 (PROD 2 F1/F2):</strong>
                        Foreman CAT (F1/F2 CAT 1-3) &rarr; Section Head (112 KRISTIYANTO / 280 MURYADI) &rarr; Dept Head 31 SUPARDI &rarr; Div Head 11 ATASAN.
                    </div>
                    <div class="p-2.5 rounded-xl bg-navy-700/50 border border-navy-600/60">
                        <strong class="text-blue-400 block mb-0.5">⚡ Kasus 2 (FACTORY 3):</strong>
                        Section Head 99999 AL QADRI menilai staf langsung tanpa Foreman &rarr; diteruskan ke 31 SUPARDI &rarr; 11 ATASAN.
                    </div>
                    <div class="p-2.5 rounded-xl bg-navy-700/50 border border-navy-600/60">
                        <strong class="text-purple-400 block mb-0.5">🔀 Kasus 3 (PROD 1 &amp; Lainnya):</strong>
                        Alur Seksi PROD 1 (Dwi Cahyono &rarr; Agus Setiawan &rarr; Bambang Widodo).
                    </div>
                </div>

            </section>

        </main>

        <!-- Page Footer -->
        <footer class="text-center text-xs text-slate-500 py-4">
            <p>PT Musashi Auto Parts Indonesia &bull; Penilaian Karya Digital &bull; Database SQLite & Google Sheets v4 API</p>
        </footer>

    </div>

    <!-- Interactive JavaScript Helpers -->
    <script>
        // 1. Password Visibility Toggle
        function togglePasswordVisibility() {
            const pwd = document.getElementById('passwordInput');
            const icon = document.getElementById('togglePasswordIcon');
            if (pwd.type === 'password') {
                pwd.type = 'text';
                icon.textContent = '🙈';
            } else {
                pwd.type = 'password';
                icon.textContent = '👁️';
            }
        }

        // 2. Fill Form Automatically from Account Card
        function fillLoginForm(email, password, role) {
            const emailInput = document.getElementById('emailInput');
            const passwordInput = document.getElementById('passwordInput');
            const roleSelect = document.getElementById('roleSelector');

            emailInput.value = email;
            passwordInput.value = password;

            // Map role to dropdown option
            if (role === 'foreman' || role === 'ketua') {
                roleSelect.value = 'foreman';
            } else if (role === 'section_head') {
                roleSelect.value = 'section_head';
            } else if (role === 'department_head') {
                roleSelect.value = 'department_head';
            } else if (role === 'division_head') {
                roleSelect.value = 'division_head';
            } else if (role === 'admin' || role === 'superadmin') {
                roleSelect.value = 'admin';
            } else {
                roleSelect.value = 'all';
            }

            // Visual feedback animation
            [emailInput, passwordInput, roleSelect].forEach(el => {
                el.classList.remove('input-highlight');
                void el.offsetWidth; // trigger reflow
                el.classList.add('input-highlight');
            });

            // Smooth scroll on small screens
            if (window.innerWidth < 1024) {
                document.getElementById('loginFormSection').scrollIntoView({ behavior: 'smooth' });
            }
        }

        // 3. Filter Cards by Role
        let currentRoleFilter = 'all';
        let currentCaseFilter = 'all';

        function filterByRole(role, btn) {
            currentRoleFilter = role;

            // Update button styles
            document.querySelectorAll('.role-filter-btn').forEach(b => {
                b.className = 'role-filter-btn px-3 py-1.5 rounded-xl text-xs font-medium bg-navy-700 text-slate-300 hover:text-white hover:bg-navy-600 transition-all border border-navy-600';
            });
            btn.className = 'role-filter-btn px-3 py-1.5 rounded-xl text-xs font-bold bg-[#92302e] text-white shadow-sm transition-all border border-[#b84240]';

            // Also synchronize with the Role Selector dropdown in form!
            const roleSelect = document.getElementById('roleSelector');
            if (role === 'all') {
                roleSelect.value = 'all';
            } else if (role === 'foreman') {
                roleSelect.value = 'foreman';
            } else if (role === 'section_head') {
                roleSelect.value = 'section_head';
            } else if (role === 'department_head') {
                roleSelect.value = 'department_head';
            } else if (role === 'division_head') {
                roleSelect.value = 'division_head';
            } else if (role === 'admin') {
                roleSelect.value = 'admin';
            }

            applyFilters();
        }

        // 4. Filter Cards by Case
        function filterByCase(kasus, btn) {
            currentCaseFilter = kasus;

            // Update button styles
            document.querySelectorAll('.case-filter-btn').forEach(b => {
                b.className = 'case-filter-btn px-3 py-1.5 rounded-xl text-xs font-medium bg-navy-700 text-slate-300 hover:text-white hover:bg-navy-600 transition-all border border-navy-600';
            });
            btn.className = 'case-filter-btn px-3 py-1.5 rounded-xl text-xs font-bold bg-slate-700 text-white shadow-sm transition-all border border-slate-600';

            applyFilters();
        }

        function applyFilters() {
            const cards = document.querySelectorAll('.account-card');

            cards.forEach(card => {
                const cardRole = card.getAttribute('data-role');
                const cardKasus = card.getAttribute('data-kasus') || '';

                const matchesRole = (currentRoleFilter === 'all') || 
                                    (currentRoleFilter === 'admin' && (cardRole === 'admin' || cardRole === 'superadmin')) ||
                                    (cardRole === currentRoleFilter);

                const matchesCase = (currentCaseFilter === 'all') || 
                                    (cardKasus.includes(currentCaseFilter));

                if (matchesRole && matchesCase) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>
