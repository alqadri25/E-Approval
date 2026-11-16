<!DOCTYPE html>
<html lang="id" class="h-full overflow-hidden bg-[#f4f5f9]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Dashboard' }} - E-Approval - {{ config('app.name', 'TTD Digital Penilaian Karya') }}</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ asset('musashi.png') }}?v={{ file_exists(public_path('musashi.png')) ? filemtime(public_path('musashi.png')) : '1' }}">
    <link rel="shortcut icon" type="image/png" href="{{ asset('musashi.png') }}?v={{ file_exists(public_path('musashi.png')) ? filemtime(public_path('musashi.png')) : '1' }}">
    <link rel="apple-touch-icon" href="{{ asset('musashi.png') }}?v={{ file_exists(public_path('musashi.png')) ? filemtime(public_path('musashi.png')) : '1' }}">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        musashi: {
                            50: '#fdf4f4',
                            100: '#fbe8e8',
                            200: '#f7d5d5',
                            300: '#efb5b4',
                            400: '#e18886',
                            500: '#ce5d5b',
                            600: '#b84240',
                            700: '#92302e',
                            800: '#7e2b29',
                            900: '#6b2726',
                            950: '#3a1110',
                        },
                        brand: {
                            50: '#fdf4f4',
                            100: '#fbe8e8',
                            200: '#f7d5d5',
                            300: '#efb5b4',
                            400: '#e18886',
                            500: '#ce5d5b',
                            600: '#b84240',
                            700: '#92302e',
                            800: '#7e2b29',
                            900: '#6b2726',
                        }
                    }
                }
            }
        }
    </script>

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Chart.js for Donut / Pie Charts & Bar Charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Inter Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }
        .min-btn { min-height: 38px; }
        :root {
            --musashi-red: #92302e;
            --musashi-dark: #7a2423;
            --musashi-darker: #5e1918;
            --musashi-light: #fbe8e8;
        }
        .bg-musashi { background-color: #92302e !important; }
        .text-musashi { color: #92302e !important; }
        .border-musashi { border-color: #92302e !important; }
        .hover-bg-musashi:hover { background-color: #7e2b29 !important; }

        html, body {
            height: 100%;
            overflow: hidden !important;
            overscroll-behavior: none;
        }

        /* Sleek custom scrollbar for sidebar */
        .scrollbar-thin::-webkit-scrollbar {
            width: 5px;
        }
        .scrollbar-thin::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.15);
            border-radius: 9999px;
        }
        .scrollbar-thin::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.4);
            border-radius: 9999px;
        }
        .scrollbar-thin::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.7);
        }
        .scrollbar-thin {
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.4) rgba(0, 0, 0, 0.15);
        }
    </style>
</head>
<body class="h-full flex overflow-hidden text-slate-800 antialiased bg-[#f4f5f9]" x-data="{ mobileMenu: false }">

    @auth
    @php
        $user = auth()->user();
        $userName = $user->nama ?? 'User';
        $cleanName = trim(preg_replace('/\s*\(.*?\)/', '', $userName));
        $words = explode(' ', $cleanName);
        if (count($words) >= 2) {
            $initials = strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
        } else {
            $initials = strtoupper(substr($cleanName, 0, 2));
        }
        $userEmail = $user->email ?? (!empty($user->nrp) ? strtolower($user->nrp) . '@musashi.co.id' : 'admin1@musashi.co.id');
        $userRoleLabel = $user->roleLabel();
    @endphp

    <!-- ============================================================== -->
    <!-- DESKTOP SIDEBAR (Sleek Dark Navy Theme with Royal Blue Active) -->
    <!-- ============================================================== -->
  <aside class="hidden lg:flex lg:flex-col lg:w-64 bg-gradient-to-b from-[#962226] via-[#92302e] to-[#781b1f] text-white flex-shrink-0 z-30 justify-between select-none h-full relative overflow-hidden border-r-2 border-white/50 shadow-xl">
    
    <!-- Layered Decorative Waves matching Mockup (Bottom Left) -->
    <div class="pointer-events-none absolute bottom-0 left-0 right-0 h-48 overflow-hidden z-0 select-none">
      <svg class="w-full h-full object-cover" viewBox="0 0 256 180" fill="none" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
        <!-- Base dark wave swooping upwards -->
        <path d="M-20 180 C 35 95, 115 145, 280 80 L280 180 Z" fill="#521114" fill-opacity="0.6"/>
        <!-- Middle crimson curve -->
        <path d="M-30 180 C 30 70, 135 120, 280 48 L280 180 Z" fill="#b03035" fill-opacity="0.35"/>
        <!-- Top delicate curve highlight -->
        <path d="M-10 180 C 45 120, 145 140, 270 95 L270 180 Z" fill="#e05359" fill-opacity="0.18"/>
      </svg>
    </div>

    <div class="flex-1 flex flex-col min-h-0 relative z-10">
      <!-- Brand Header: logomusashi.png (Pure White) -->
      <div class="pt-6 pb-4 px-5 text-center flex-shrink-0">
        <img src="{{ asset('logomusashi.png') }}" alt="MUSASHI E-Approval" class="h-9 max-w-[175px] object-contain brightness-0 invert mx-auto filter drop-shadow-sm">
        <span class="sr-only">E-Approval</span>
      </div>

      <!-- Navigation Links Scroll Area -->
      <nav class="flex-1 overflow-y-auto px-4 py-1 space-y-1.5 scrollbar-thin">

        @if($user->isSuperadmin())
          <!-- 1. DASHBOARD & MONITORING -->
          <div class="pt-1">
            <div class="text-[9.5px] font-bold uppercase tracking-wider text-red-200/80 px-3 pb-1">
              DASHBOARD
            </div>
            <div class="space-y-1">
              <a href="{{ route('dashboard') }}" 
                class="flex items-center gap-3.5 px-4 py-2.5 text-[13px] transition-all {{ request()->routeIs('dashboard') ? 'bg-[#b2373b] text-white font-bold shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:text-white hover:bg-white/10 font-medium group' }}">
                <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('dashboard') ? 'text-white' : 'text-red-200 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                <span>Dashboard</span>
              </a>
            </div>
          </div>

          <!-- 2. MASTER DATA & PENGGUNA -->
          <div class="pt-2">
            <div class="text-[9.5px] font-bold uppercase tracking-wider text-red-200/80 px-3 pb-1">
              MASTER DATA &amp; PENGGUNA
            </div>
            <div class="space-y-1">
              <a href="{{ route('superadmin.users') }}" 
                class="flex items-center gap-3.5 px-4 py-2.5 text-[13px] transition-all {{ request()->routeIs('superadmin.users*') || request()->routeIs('admin.users*') ? 'bg-[#b2373b] text-white font-bold shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:text-white hover:bg-white/10 font-medium group' }}">
                <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('superadmin.users*') || request()->routeIs('admin.users*') ? 'text-white' : 'text-red-200 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
                <span>Manajemen Akun</span>
                <span class="sr-only">Kelola User</span>
              </a>

              <a href="{{ route('admin.karyawan') }}" 
                class="flex items-center gap-3.5 px-4 py-2.5 text-[13px] transition-all {{ request()->routeIs('admin.karyawan*') || (request()->routeIs('karyawan.*') && !request()->routeIs('admin.*')) ? 'bg-[#b2373b] text-white font-bold shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:text-white hover:bg-white/10 font-medium group' }}">
                <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('admin.karyawan*') || (request()->routeIs('karyawan.*') && !request()->routeIs('admin.*')) ? 'text-white' : 'text-red-200 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                <span>Master Karyawan</span>
                </a>

              <a href="{{ route('admin.divisiDepartemen') }}" 
                class="flex items-center gap-3.5 px-4 py-2.5 text-[13px] transition-all {{ request()->routeIs('admin.divisiDepartemen*') ? 'bg-[#b2373b] text-white font-bold shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:text-white hover:bg-white/10 font-medium group' }}">
                <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('admin.divisiDepartemen*') ? 'text-white' : 'text-red-200 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                </svg>
                <span>Divisi &amp; Departemen</span>
              </a>

              <a href="{{ route('admin.targetKuota') }}" 
                class="flex items-center gap-3.5 px-4 py-2.5 text-[13px] transition-all {{ request()->routeIs('admin.targetKuota*') ? 'bg-[#b2373b] text-white font-bold shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:text-white hover:bg-white/10 font-medium group' }}">
                <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('admin.targetKuota*') ? 'text-white' : 'text-red-200 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/>
                </svg>
                <span>Target &amp; Kuota Nilai</span>
              </a>

              <a href="{{ route('admin.pengaturanForm') }}" 
                class="flex items-center gap-3.5 px-4 py-2.5 text-[13px] transition-all {{ request()->routeIs('admin.pengaturanForm*') ? 'bg-[#b2373b] text-white font-bold shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:text-white hover:bg-white/10 font-medium group' }}">
                <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('admin.pengaturanForm*') ? 'text-white' : 'text-red-200 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span>Kelola Form &amp; Periode</span>
              </a>

              <a href="{{ route('admin.lembarPenilaian') }}" 
                class="flex items-center gap-3.5 px-4 py-2.5 text-[13px] transition-all {{ request()->routeIs('admin.lembarPenilaian*') || request()->routeIs('superadmin.lembarPenilaian*') ? 'bg-[#b2373b] text-white font-bold shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:text-white hover:bg-white/10 font-medium group' }}">
                <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('admin.lembarPenilaian*') || request()->routeIs('superadmin.lembarPenilaian*') ? 'text-white' : 'text-red-200 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
                <span>Lembar Penilaian PK</span>
              </a>
            </div>
          </div>

          <!-- 4. AUDIT & SISTEM -->
          <div class="pt-2">
            <div class="text-[9.5px] font-bold uppercase tracking-wider text-red-200/80 px-3 pb-1">
              AUDIT &amp; SISTEM
            </div>
            <div class="space-y-1">
              <a href="{{ route('superadmin.revisiPenilaian') }}" 
                class="flex items-center gap-3.5 px-4 py-2.5 text-[13px] transition-all {{ request()->routeIs('*revisiPenilaian*') ? 'bg-[#b2373b] text-white font-bold shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:text-white hover:bg-white/10 font-medium group' }}">
                <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('*revisiPenilaian*') ? 'text-white' : 'text-red-200 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                <span>Revisi Form Penilaian</span>
              </a>

              <a href="{{ route('laporan.index') }}" 
                class="flex items-center gap-3.5 px-4 py-2.5 text-[13px] transition-all {{ request()->routeIs('laporan.*') ? 'bg-[#b2373b] text-white font-bold shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:text-white hover:bg-white/10 font-medium group' }}">
                <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('laporan.*') ? 'text-white' : 'text-red-200 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span>Pusat Laporan &amp; Rekap</span>
              </a>

              <a href="{{ route('superadmin.pengaturan') }}" 
                class="flex items-center gap-3.5 px-4 py-2.5 text-[13px] transition-all {{ request()->routeIs('superadmin.pengaturan*') ? 'bg-[#b2373b] text-white font-bold shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:text-white hover:bg-white/10 font-medium group' }}">
                <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('superadmin.pengaturan*') ? 'text-white' : 'text-red-200 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span>Pengaturan Sistem</span>
              </a>
            </div>
          </div>

        @elseif($user->isAdmin())
          <!-- 1. DASHBOARD & MONITORING -->
          <div class="pt-1">
            <div class="text-[9.5px] font-bold uppercase tracking-wider text-red-200/80 px-3 pb-1">
              DASHBOARD
            </div>
            <div class="space-y-1">
              <a href="{{ route('dashboard') }}" 
                class="flex items-center gap-3.5 px-4 py-2.5 text-[13px] transition-all {{ request()->routeIs('dashboard') ? 'bg-[#b2373b] text-white font-bold shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:text-white hover:bg-white/10 font-medium group' }}">
                <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('dashboard') ? 'text-white' : 'text-red-200 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                <span>Dashboard</span>
              </a>
            </div>
          </div>

          <!-- 2. MASTER & KELOMPOK -->
          <div class="pt-2">
            <div class="text-[9.5px] font-bold uppercase tracking-wider text-red-200/80 px-3 pb-1">
              MASTER &amp; KELOMPOK
            </div>
            <div class="space-y-1">
              <a href="{{ route('admin.users') }}" 
                class="flex items-center gap-3.5 px-4 py-2.5 text-[13px] transition-all {{ request()->routeIs('admin.users*') || request()->routeIs('superadmin.users*') ? 'bg-[#b2373b] text-white font-bold shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:text-white hover:bg-white/10 font-medium group' }}">
                <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('admin.users*') || request()->routeIs('superadmin.users*') ? 'text-white' : 'text-red-200 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
                <span>Manajemen Akun</span>
                </a>

              <a href="{{ route('admin.karyawan') }}" 
                class="flex items-center gap-3.5 px-4 py-2.5 text-[13px] transition-all {{ request()->routeIs('admin.karyawan*') || (request()->routeIs('karyawan.*') && !request()->routeIs('admin.*')) ? 'bg-[#b2373b] text-white font-bold shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:text-white hover:bg-white/10 font-medium group' }}">
                <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('admin.karyawan*') ? 'text-white' : 'text-red-200 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                <span>Master Karyawan</span>
                </a>

              <a href="{{ route('admin.divisiDepartemen') }}" 
                class="flex items-center gap-3.5 px-4 py-2.5 text-[13px] transition-all {{ request()->routeIs('admin.divisiDepartemen*') ? 'bg-[#b2373b] text-white font-bold shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:text-white hover:bg-white/10 font-medium group' }}">
                <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('admin.divisiDepartemen*') ? 'text-white' : 'text-red-200 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                </svg>
                <span>Divisi &amp; Departemen</span>
              </a>

              <a href="{{ route('admin.targetKuota') }}" 
                class="flex items-center gap-3.5 px-4 py-2.5 text-[13px] transition-all {{ request()->routeIs('admin.targetKuota*') ? 'bg-[#b2373b] text-white font-bold shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:text-white hover:bg-white/10 font-medium group' }}">
                <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('admin.targetKuota*') ? 'text-white' : 'text-red-200 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/>
                </svg>
                <span>Target &amp; Kuota Nilai</span>
              </a>

              <a href="{{ route('admin.pengaturanForm') }}" 
                class="flex items-center gap-3.5 px-4 py-2.5 text-[13px] transition-all {{ request()->routeIs('admin.pengaturanForm*') ? 'bg-[#b2373b] text-white font-bold shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:text-white hover:bg-white/10 font-medium group' }}">
                <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('admin.pengaturanForm*') ? 'text-white' : 'text-red-200 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span>Kelola Form &amp; Periode</span>
              </a>

              <a href="{{ route('admin.lembarPenilaian') }}" 
                class="flex items-center gap-3.5 px-4 py-2.5 text-[13px] transition-all {{ request()->routeIs('admin.lembarPenilaian*') || request()->routeIs('superadmin.lembarPenilaian*') ? 'bg-[#b2373b] text-white font-bold shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:text-white hover:bg-white/10 font-medium group' }}">
                <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('admin.lembarPenilaian*') || request()->routeIs('superadmin.lembarPenilaian*') ? 'text-white' : 'text-red-200 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
                <span>Lembar Penilaian PK</span>
              </a>
            </div>
          </div>

          <!-- 4. AUDIT & SISTEM -->
          <div class="pt-2">
            <div class="text-[9.5px] font-bold uppercase tracking-wider text-red-200/80 px-3 pb-1">
              AUDIT &amp; SISTEM
            </div>
            <div class="space-y-1">
              <a href="{{ route('admin.revisiPenilaian') }}" 
                class="flex items-center gap-3.5 px-4 py-2.5 text-[13px] transition-all {{ request()->routeIs('*revisiPenilaian*') ? 'bg-[#b2373b] text-white font-bold shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:text-white hover:bg-white/10 font-medium group' }}">
                <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('*revisiPenilaian*') ? 'text-white' : 'text-red-200 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                <span>Revisi Form Penilaian</span>
              </a>

              <a href="{{ route('laporan.index') }}" 
                class="flex items-center gap-3.5 px-4 py-2.5 text-[13px] transition-all {{ request()->routeIs('laporan.*') ? 'bg-[#b2373b] text-white font-bold shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:text-white hover:bg-white/10 font-medium group' }}">
                <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('laporan.*') ? 'text-white' : 'text-red-200 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span>Pusat Laporan &amp; Rekap</span>
              </a>

              <a href="{{ route('admin.pengaturan') }}" 
                class="flex items-center gap-3.5 px-4 py-2.5 text-[13px] transition-all {{ request()->routeIs('admin.pengaturan*') || request()->routeIs('superadmin.pengaturan*') ? 'bg-[#b2373b] text-white font-bold shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:text-white hover:bg-white/10 font-medium group' }}">
                <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('admin.pengaturan*') || request()->routeIs('superadmin.pengaturan*') ? 'text-white' : 'text-red-200 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span>Pengaturan Sistem</span>
              </a>
            </div>
          </div>

        @elseif($user->isDepartmentHead())
          <!-- 1. DASHBOARD & MONITORING -->
          <div class="pt-1">
            <div class="text-[9.5px] font-bold uppercase tracking-wider text-red-200/80 px-3 pb-1">
              DASHBOARD
            </div>
            <div class="space-y-1">
              <a href="{{ route('dashboard') }}" 
                class="flex items-center gap-3.5 px-4 py-2.5 text-[13px] transition-all {{ request()->routeIs('dashboard') ? 'bg-[#b2373b] text-white font-bold shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:text-white hover:bg-white/10 font-medium group' }}">
                <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('dashboard') ? 'text-white' : 'text-red-200 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                <span>Dashboard</span>
              </a>
            </div>
          </div>

          <!-- 2. PENILAIAN DEPARTEMEN -->
          <div class="pt-2">
            <div class="text-[9.5px] font-bold uppercase tracking-wider text-red-200/80 px-3 pb-1">
              PENILAIAN DEPARTEMEN
            </div>
            <div class="space-y-1">
              <a href="{{ route('penilaian.dept') }}" 
                class="flex items-center gap-3.5 px-4 py-2.5 text-[13px] transition-all {{ request()->routeIs('penilaian.dept') || request()->routeIs('penilaian.dept.saveDraft') ? 'bg-[#b2373b] text-white font-bold shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:text-white hover:bg-white/10 font-medium group' }}">
                <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('penilaian.dept') || request()->routeIs('penilaian.dept.saveDraft') ? 'text-white' : 'text-red-200 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                <span>Data Penilaian</span>
              </a>

              <a href="{{ route('penilaian.dept.penyesuaian') }}" 
                class="flex items-center gap-3.5 px-4 py-2.5 text-[13px] transition-all {{ request()->routeIs('penilaian.dept.penyesuaian*') ? 'bg-[#b2373b] text-white font-bold shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:text-white hover:bg-white/10 font-medium group' }}">
                <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('penilaian.dept.penyesuaian*') ? 'text-white' : 'text-red-200 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                </svg>
                <span>Penyesuaian Penilaian</span>
              </a>

              <a href="{{ route('penilaian.cari') }}" 
                class="flex items-center gap-3.5 px-4 py-2.5 text-[13px] transition-all {{ request()->routeIs('penilaian.cari') || request()->routeIs('penilaian.show*') ? 'bg-[#b2373b] text-white font-bold shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:text-white hover:bg-white/10 font-medium group' }}">
                <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('penilaian.cari') || request()->routeIs('penilaian.show*') ? 'text-white' : 'text-red-200 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                <span>Form Penilaian</span>
              </a>

              <a href="{{ route('penilaian.riwayat') }}" 
                class="flex items-center gap-3.5 px-4 py-2.5 text-[13px] transition-all {{ request()->routeIs('penilaian.riwayat') ? 'bg-[#b2373b] text-white font-bold shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:text-white hover:bg-white/10 font-medium group' }}">
                <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('penilaian.riwayat') ? 'text-white' : 'text-red-200 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span>Riwayat Penilaian</span>
              </a>
            </div>
          </div>

          <!-- 3. LAPORAN & REKAP -->
          <div class="pt-2">
            <div class="text-[9.5px] font-bold uppercase tracking-wider text-red-200/80 px-3 pb-1">
              LAPORAN &amp; REKAP
            </div>
            <div class="space-y-1">
              <a href="{{ route('laporan.index') }}" 
                class="flex items-center gap-3.5 px-4 py-2.5 text-[13px] transition-all {{ request()->routeIs('laporan.*') ? 'bg-[#b2373b] text-white font-bold shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:text-white hover:bg-white/10 font-medium group' }}">
                <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('laporan.*') ? 'text-white' : 'text-red-200 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span>Pusat Laporan &amp; Rekap</span>
              </a>
            </div>
          </div>

        @elseif($user->isSectionHead())
          <!-- 1. DASHBOARD & MONITORING -->
          <div class="pt-1">
            <div class="text-[9.5px] font-bold uppercase tracking-wider text-red-200/80 px-3 pb-1">
              DASHBOARD
            </div>
            <div class="space-y-1">
              <a href="{{ route('dashboard') }}" 
                class="flex items-center gap-3.5 px-4 py-2.5 text-[13px] transition-all {{ request()->routeIs('dashboard') ? 'bg-[#b2373b] text-white font-bold shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:text-white hover:bg-white/10 font-medium group' }}">
                <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('dashboard') ? 'text-white' : 'text-red-200 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                <span>Dashboard</span>
              </a>
            </div>
          </div>

          <!-- 2. PENILAIAN SEKSI / PENILAIAN KARYAWAN -->
          <div class="pt-2">
            <div class="text-[9.5px] font-bold uppercase tracking-wider text-red-200/80 px-3 pb-1">
              {{ $user->hasForemanSubordinates() ? 'PENILAIAN SEKSI' : 'PENILAIAN KARYAWAN' }}
            </div>
            <div class="space-y-1">
              @if($user->hasForemanSubordinates())
                <a href="{{ route('penilaian.section') }}" 
                  class="flex items-center gap-3.5 px-4 py-2.5 text-[13px] transition-all {{ request()->routeIs('penilaian.section*') ? 'bg-[#b2373b] text-white font-bold shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:text-white hover:bg-white/10 font-medium group' }}">
                  <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('penilaian.section*') ? 'text-white' : 'text-red-200 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                  </svg>
                  <span>Data Penilaian</span>
                </a>
              @else
                <a href="{{ route('karyawan.index') }}" 
                  class="flex items-center gap-3.5 px-4 py-2.5 text-[13px] transition-all {{ request()->routeIs('karyawan.*') ? 'bg-[#b2373b] text-white font-bold shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:text-white hover:bg-white/10 font-medium group' }}">
                  <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('karyawan.*') ? 'text-white' : 'text-red-200 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                  </svg>
                  <span>Data Karyawan</span>
                </a>
              @endif

              <a href="{{ route('penilaian.cari') }}" 
                class="flex items-center gap-3.5 px-4 py-2.5 text-[13px] transition-all {{ request()->routeIs('penilaian.cari') || request()->routeIs('penilaian.show*') ? 'bg-[#b2373b] text-white font-bold shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:text-white hover:bg-white/10 font-medium group' }}">
                <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('penilaian.cari') || request()->routeIs('penilaian.show*') ? 'text-white' : 'text-red-200 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                <span>Form Penilaian</span>
              </a>

              <a href="{{ route('penilaian.riwayat') }}" 
                class="flex items-center gap-3.5 px-4 py-2.5 text-[13px] transition-all {{ request()->routeIs('penilaian.riwayat') ? 'bg-[#b2373b] text-white font-bold shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:text-white hover:bg-white/10 font-medium group' }}">
                <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('penilaian.riwayat') ? 'text-white' : 'text-red-200 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span>Riwayat Penilaian</span>
              </a>
            </div>
          </div>

          <!-- 3. LAPORAN & REKAP -->
          <div class="pt-2">
            <div class="text-[9.5px] font-bold uppercase tracking-wider text-red-200/80 px-3 pb-1">
              LAPORAN &amp; REKAP
            </div>
            <div class="space-y-1">
              <a href="{{ route('laporan.index') }}" 
                class="flex items-center gap-3.5 px-4 py-2.5 text-[13px] transition-all {{ request()->routeIs('laporan.*') ? 'bg-[#b2373b] text-white font-bold shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:text-white hover:bg-white/10 font-medium group' }}">
                <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('laporan.*') ? 'text-white' : 'text-red-200 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span>Pusat Laporan &amp; Rekap</span>
              </a>
            </div>
          </div>

        @elseif($user->isForeman())
          <!-- 1. DASHBOARD & MONITORING -->
          <div class="pt-1">
            <div class="text-[9.5px] font-bold uppercase tracking-wider text-red-200/80 px-3 pb-1">
              DASHBOARD
            </div>
            <div class="space-y-1">
              <a href="{{ route('dashboard') }}" 
                class="flex items-center gap-3.5 px-4 py-2.5 text-[13px] transition-all {{ request()->routeIs('dashboard') ? 'bg-[#b2373b] text-white font-bold shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:text-white hover:bg-white/10 font-medium group' }}">
                <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('dashboard') ? 'text-white' : 'text-red-200 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                <span>Dashboard</span>
              </a>
            </div>
          </div>

          <!-- 2. PENILAIAN KARYAWAN -->
          <div class="pt-2">
            <div class="text-[9.5px] font-bold uppercase tracking-wider text-red-200/80 px-3 pb-1">
              PENILAIAN KARYAWAN
            </div>
            <div class="space-y-1">
              <a href="{{ route('karyawan.index') }}" 
                class="flex items-center gap-3.5 px-4 py-2.5 text-[13px] transition-all {{ request()->routeIs('karyawan.*') ? 'bg-[#b2373b] text-white font-bold shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:text-white hover:bg-white/10 font-medium group' }}">
                <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('karyawan.*') ? 'text-white' : 'text-red-200 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
                <span>Data Karyawan</span>
              </a>

              <a href="{{ route('penilaian.cari') }}" 
                class="flex items-center gap-3.5 px-4 py-2.5 text-[13px] transition-all {{ request()->routeIs('penilaian.cari') || request()->routeIs('penilaian.show*') ? 'bg-[#b2373b] text-white font-bold shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:text-white hover:bg-white/10 font-medium group' }}">
                <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('penilaian.cari') || request()->routeIs('penilaian.show*') ? 'text-white' : 'text-red-200 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                <span>Form Penilaian</span>
              </a>

              <a href="{{ route('penilaian.riwayat') }}" 
                class="flex items-center gap-3.5 px-4 py-2.5 text-[13px] transition-all {{ request()->routeIs('penilaian.riwayat') ? 'bg-[#b2373b] text-white font-bold shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:text-white hover:bg-white/10 font-medium group' }}">
                <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('penilaian.riwayat') ? 'text-white' : 'text-red-200 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span>Riwayat Penilaian</span>
              </a>
            </div>
          </div>

          <!-- 3. LAPORAN & REKAP -->
          <div class="pt-2">
            <div class="text-[9.5px] font-bold uppercase tracking-wider text-red-200/80 px-3 pb-1">
              LAPORAN &amp; REKAP
            </div>
            <div class="space-y-1">
              <a href="{{ route('laporan.index') }}" 
                class="flex items-center gap-3.5 px-4 py-2.5 text-[13px] transition-all {{ request()->routeIs('laporan.*') ? 'bg-[#b2373b] text-white font-bold shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:text-white hover:bg-white/10 font-medium group' }}">
                <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('laporan.*') ? 'text-white' : 'text-red-200 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span>Pusat Laporan &amp; Rekap</span>
              </a>
            </div>
          </div>

        @elseif($user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head']))
          <!-- 1. DASHBOARD & MONITORING -->
          <div class="pt-1">
            <div class="text-[9.5px] font-bold uppercase tracking-wider text-red-200/80 px-3 pb-1">
              DASHBOARD
            </div>
            <div class="space-y-1">
              <a href="{{ route('dashboard') }}" 
                class="flex items-center gap-3.5 px-4 py-2.5 text-[13px] transition-all {{ request()->routeIs('dashboard') ? 'bg-[#b2373b] text-white font-bold shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:text-white hover:bg-white/10 font-medium group' }}">
                <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('dashboard') ? 'text-white' : 'text-red-200 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                <span>Dashboard</span>
              </a>
            </div>
          </div>

          <!-- 2. PENILAIAN KARYAWAN (DIVISION HEAD) -->
          <div class="pt-2">
            <div class="text-[9.5px] font-bold uppercase tracking-wider text-red-200/80 px-3 pb-1">
              PENILAIAN KARYAWAN
            </div>
            <div class="space-y-1">
              <a href="{{ route('penilaian.div') }}" 
                class="flex items-center gap-3.5 px-4 py-2.5 text-[13px] transition-all {{ request()->routeIs('penilaian.div') ? 'bg-[#b2373b] text-white font-bold shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:text-white hover:bg-white/10 font-medium group' }}">
                <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('penilaian.div') ? 'text-white' : 'text-red-200 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                <span>Data Penilaian</span>
              </a>

              <a href="{{ route('penilaian.cari') }}" 
                class="flex items-center gap-3.5 px-4 py-2.5 text-[13px] transition-all {{ request()->routeIs('penilaian.cari') || request()->routeIs('penilaian.show*') ? 'bg-[#b2373b] text-white font-bold shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:text-white hover:bg-white/10 font-medium group' }}">
                <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('penilaian.cari') || request()->routeIs('penilaian.show*') ? 'text-white' : 'text-red-200 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                <span>Form Penilaian</span>
              </a>

              <a href="{{ route('penilaian.riwayat') }}" 
                class="flex items-center gap-3.5 px-4 py-2.5 text-[13px] transition-all {{ request()->routeIs('penilaian.riwayat') ? 'bg-[#b2373b] text-white font-bold shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:text-white hover:bg-white/10 font-medium group' }}">
                <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('penilaian.riwayat') ? 'text-white' : 'text-red-200 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span>Riwayat Penilaian</span>
              </a>
            </div>
          </div>

          <!-- 3. LAPORAN & REKAP -->
          <div class="pt-2">
            <div class="text-[9.5px] font-bold uppercase tracking-wider text-red-200/80 px-3 pb-1">
              LAPORAN &amp; REKAP
            </div>
            <div class="space-y-1">
              <a href="{{ route('laporan.index') }}" 
                class="flex items-center gap-3.5 px-4 py-2.5 text-[13px] transition-all {{ request()->routeIs('laporan.*') ? 'bg-[#b2373b] text-white font-bold shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:text-white hover:bg-white/10 font-medium group' }}">
                <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('laporan.*') ? 'text-white' : 'text-red-200 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span>Pusat Laporan &amp; Rekap</span>
              </a>
            </div>
          </div>

        @else
          <!-- DEFAULT / DIVISION HEAD ROLE -->
          <div class="space-y-1.5 pt-1">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3.5 px-4 py-3 text-[13px] transition-all {{ request()->routeIs('dashboard') ? 'bg-[#b2373b] text-white font-bold shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:text-white hover:bg-white/10 font-medium' }}">
              <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('dashboard') ? 'text-white' : 'text-red-200' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
              </svg>
              <span>Dashboard</span>
            </a>
            <a href="{{ route('penilaian.cari') }}" class="flex items-center gap-3.5 px-4 py-3 text-[13px] transition-all {{ request()->routeIs('penilaian.*') ? 'bg-[#b2373b] text-white font-bold shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:text-white hover:bg-white/10 font-medium' }}">
              <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('penilaian.*') ? 'text-white' : 'text-red-200' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
              </svg>
              <span>Form Penilaian</span>
            </a>
          </div>
        @endif

      

      </nav>
    </div>

    <!-- ============================================================== -->
    <!-- BOTTOM USER PROFILE BAR (100% Matching Screenshot)       -->
    <!-- ============================================================== -->
    <div class="relative z-10 p-4 pt-2 flex-shrink-0 border-t border-white/10" x-data="{ openProfile: false }">
      
      <!-- Quick Role Switcher Dropup Popover -->
      <div x-show="openProfile" @click.away="openProfile = false" x-cloak 
         class="mb-3 p-3.5 bg-[#7a2024]/95 backdrop-blur-md border border-white/20 shadow-2xl space-y-3 text-white animate-in fade-in duration-150">
        <div class="border-b border-white/15 pb-2.5">
          <span class="text-[10px] font-bold text-red-200 uppercase tracking-wider block">Profil Pengguna</span>
          <p class="font-bold text-xs text-white mt-1">{{ $cleanName }}</p>
          <p class="text-[10px] text-red-100 mt-1">
            <span class="font-semibold text-red-200">NRP :</span>
            <span class="font-mono text-white font-semibold">{{ $user->nrp }}</span>
          </p>
          <p class="text-[10px] text-red-100 mt-0.5">
            <span class="font-semibold text-red-200">DEPARTMENT :</span>
            <span class="text-white font-semibold uppercase">{{ $user->departemen ?? 'PT MUSASHI' }}</span>
          </p>
        </div>
        <div>
          <a href="{{ route('profile.index') }}" 
             class="w-full py-2.5 px-3 bg-white text-[#92302e] hover:bg-slate-100 font-bold text-xs flex items-center justify-center gap-2 transition-all shadow-sm">
            <svg class="w-4 h-4 text-[#92302e]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
            <span>Buka Profil &amp; TTD</span>
          </a>
        </div>
        <div class="pt-2 border-t border-white/15">
          <form method="POST" action="{{ route('logout') }}" class="w-full">
            @csrf
            <button type="submit" 
                class="w-full py-2 px-3 bg-black/30 hover:bg-black/50 border border-white/15 text-red-100 text-xs font-semibold flex items-center justify-center gap-2 transition-colors cursor-pointer shadow-sm">
              <svg class="w-3.5 h-3.5 text-rose-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
              </svg>
              <span>Keluar (Logout)</span>
            </button>
          </form>
        </div>
      </div>

      <!-- Clickable Profile Pill matching Mockup exactly -->
      <button @click="openProfile = !openProfile" type="button" 
          class="w-full flex items-center gap-3 p-1.5 pr-2 hover:bg-white/10 transition-colors text-left cursor-pointer group">
        <!-- Circular Avatar with User Silhouette -->
        <div class="w-10 h-10 bg-slate-100 flex items-center justify-center text-slate-500 shadow-md flex-shrink-0">
          <svg class="w-6 h-6 text-slate-400" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
          </svg>
        </div>
        <!-- User Name and Role Underneath -->
        <div class="min-w-0 flex-1">
          <h4 class="font-bold text-[13px] text-white truncate leading-tight">{{ $cleanName }}</h4>
          <p class="text-[11px] text-red-200/90 truncate mt-0.5 leading-tight">{{ $userRoleLabel }}</p>
        </div>
        <!-- Chevron Dropdown Indicator -->
        <svg class="w-4 h-4 text-white/80 flex-shrink-0 transition-transform duration-200" :class="{ 'rotate-180': openProfile }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
      </button>
    </div>

  </aside>
    @endauth

    <!-- Mobile Drawer & Main Content Container -->
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden bg-[#f4f5f9]">
        
        <!-- Mobile Top Navbar -->
        <header class="lg:hidden bg-gradient-to-r from-[#962226] to-[#92302e] text-white p-3.5 flex items-center justify-between shadow-md z-20 border-b border-[#7e2524]">
            <div class="flex items-center gap-2.5">
                <button @click="mobileMenu = !mobileMenu" class="p-1 hover:bg-white/10 text-red-100 hover:text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div class="flex items-center gap-2">
                    <div class="w-6 h-6 bg-white text-[#92302e] flex items-center justify-center font-black text-xs shadow-xs">
                        E
                    </div>
                    <span class="font-bold text-sm tracking-wide text-white">E-Approval</span>
                </div>
            </div>
            @auth
            <span class="text-[10px] bg-[#5e1918] text-red-100 border border-[#b84240]/40 px-2.5 py-0.5 font-bold uppercase tracking-wider">{{ auth()->user()->roleLabel() }}</span>
            @endauth
        </header>

    <!-- Mobile Drawer Navigation -->
    @auth
    <div x-show="mobileMenu" x-cloak class="fixed inset-0 z-50 lg:hidden">
      <div class="fixed inset-0 bg-slate-900/70 backdrop-blur-sm" @click="mobileMenu = false"></div>
      <div class="relative w-72 max-w-[85%] bg-gradient-to-b from-[#962226] via-[#92302e] to-[#781b1f] text-white h-full p-4 flex flex-col justify-between z-50 shadow-2xl overflow-hidden border-r border-white/10">
        
        <!-- Decorative Waves in Mobile Drawer -->
        <div class="pointer-events-none absolute bottom-0 left-0 right-0 h-40 overflow-hidden z-0 select-none">
          <svg class="w-full h-full object-cover" viewBox="0 0 256 160" fill="none" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M-20 160 C 40 85, 120 135, 276 75 L276 160 Z" fill="#521114" fill-opacity="0.55"/>
            <path d="M-30 160 C 35 65, 140 110, 276 45 L276 160 Z" fill="#b03035" fill-opacity="0.32"/>
            <path d="M-10 160 C 50 115, 150 135, 270 90 L270 160 Z" fill="#d94a50" fill-opacity="0.18"/>
          </svg>
        </div>

        <div class="flex-1 flex flex-col min-h-0 relative z-10">
          <!-- Brand Header Mobile -->
          <div class="flex items-center justify-between pb-3.5 border-b border-white/15">
            <img src="{{ asset('logomusashi.png') }}" alt="MUSASHI" class="h-7 max-w-[140px] object-contain brightness-0 invert filter drop-shadow-sm">
            <button @click="mobileMenu = false" class="text-white/80 hover:text-white text-2xl p-1 hover:bg-white/10 cursor-pointer">&times;</button>
          </div>

          

          <!-- Drawer Nav Links -->
          <nav class="flex-1 overflow-y-auto space-y-1.5 py-1 scrollbar-thin">
            @if($user->isSuperadmin())
              <div class="text-[9.5px] font-bold uppercase tracking-wider text-red-200/80 px-2.5 pt-2">DASHBOARD</div>
              <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('dashboard') ? 'bg-[#b2373b] text-white shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:bg-white/10' }}">Dashboard</a>
              <a href="{{ route('superadmin.users') }}" class="flex items-center gap-3 px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('superadmin.users*') || request()->routeIs('admin.users*') ? 'bg-[#b2373b] text-white shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:bg-white/10' }}">
                <span>Manajemen Akun</span>
                <span class="sr-only">Kelola User</span>
              </a>
              <a href="{{ route('admin.karyawan') }}" class="flex items-center gap-3 px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('admin.karyawan*') ? 'bg-[#b2373b] text-white shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:bg-white/10' }}">Master Karyawan</a>
              <a href="{{ route('admin.divisiDepartemen') }}" class="flex items-center gap-3 px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('admin.divisiDepartemen*') ? 'bg-[#b2373b] text-white shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:bg-white/10' }}">Divisi &amp; Departemen</a>
              <a href="{{ route('admin.targetKuota') }}" class="flex items-center gap-3 px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('admin.targetKuota*') ? 'bg-[#b2373b] text-white shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:bg-white/10' }}">Target &amp; Kuota</a>
              <a href="{{ route('admin.pengaturanForm') }}" class="flex items-center gap-3 px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('admin.pengaturanForm*') ? 'bg-[#b2373b] text-white shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:bg-white/10' }}">Kelola Form &amp; Periode</a>
              <a href="{{ route('admin.lembarPenilaian') }}" class="flex items-center gap-3 px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('admin.lembarPenilaian*') || request()->routeIs('superadmin.lembarPenilaian*') ? 'bg-[#b2373b] text-white shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:bg-white/10' }}">Lembar Penilaian PK</a>
              <div class="text-[9.5px] font-bold uppercase tracking-wider text-red-200/80 px-2.5 pt-2">AUDIT &amp; SISTEM</div>
              <a href="{{ route('superadmin.revisiPenilaian') }}" class="flex items-center gap-3 px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('*revisiPenilaian*') ? 'bg-[#b2373b] text-white shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:bg-white/10' }}">Revisi Form Penilaian</a>
              <a href="{{ route('laporan.index') }}" class="flex items-center gap-3 px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('laporan.*') ? 'bg-[#b2373b] text-white shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:bg-white/10' }}">Pusat Laporan &amp; Rekap</a>
              <a href="{{ route('superadmin.pengaturan') }}" class="flex items-center gap-3 px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('superadmin.pengaturan*') ? 'bg-[#b2373b] text-white shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:bg-white/10' }}">Pengaturan Sistem</a>
            @elseif($user->isAdmin())
              <div class="text-[9.5px] font-bold uppercase tracking-wider text-red-200/80 px-2.5 pt-2">DASHBOARD</div>
              <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('dashboard') ? 'bg-[#b2373b] text-white shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:bg-white/10' }}">Dashboard</a>
              <a href="{{ route('admin.users') }}" class="flex items-center gap-3 px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('admin.users*') || request()->routeIs('superadmin.users*') ? 'bg-[#b2373b] text-white shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:bg-white/10' }}">
                <span>Manajemen Akun</span>
                </a>
              <a href="{{ route('admin.karyawan') }}" class="flex items-center gap-3 px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('admin.karyawan*') ? 'bg-[#b2373b] text-white shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:bg-white/10' }}">
                <span>Master Karyawan</span>
                </a>
              <a href="{{ route('admin.divisiDepartemen') }}" class="flex items-center gap-3 px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('admin.divisiDepartemen*') ? 'bg-[#b2373b] text-white shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:bg-white/10' }}">Divisi &amp; Departemen</a>
              <a href="{{ route('admin.targetKuota') }}" class="flex items-center gap-3 px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('admin.targetKuota*') ? 'bg-[#b2373b] text-white shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:bg-white/10' }}">Target &amp; Kuota</a>
              <a href="{{ route('admin.pengaturanForm') }}" class="flex items-center gap-3 px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('admin.pengaturanForm*') ? 'bg-[#b2373b] text-white shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:bg-white/10' }}">Kelola Form &amp; Periode</a>
              <a href="{{ route('admin.lembarPenilaian') }}" class="flex items-center gap-3 px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('admin.lembarPenilaian*') || request()->routeIs('superadmin.lembarPenilaian*') ? 'bg-[#b2373b] text-white shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:bg-white/10' }}">Lembar Penilaian PK</a>
              <div class="text-[9.5px] font-bold uppercase tracking-wider text-red-200/80 px-2.5 pt-2">AUDIT &amp; SISTEM</div>
              <a href="{{ route('admin.revisiPenilaian') }}" class="flex items-center gap-3 px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('*revisiPenilaian*') ? 'bg-[#b2373b] text-white shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:bg-white/10' }}">Revisi Form Penilaian</a>
              <a href="{{ route('laporan.index') }}" class="flex items-center gap-3 px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('laporan.*') ? 'bg-[#b2373b] text-white shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:bg-white/10' }}">Pusat Laporan &amp; Rekap</a>
              <a href="{{ route('admin.pengaturan') }}" class="flex items-center gap-3 px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('admin.pengaturan*') || request()->routeIs('superadmin.pengaturan*') ? 'bg-[#b2373b] text-white shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:bg-white/10' }}">Pengaturan Sistem</a>
            @elseif($user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head']))
              <div class="text-[9.5px] font-bold uppercase tracking-wider text-red-200/80 px-2.5 pt-2">DASHBOARD</div>
              <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('dashboard') ? 'bg-[#b2373b] text-white shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:bg-white/10' }}">Dashboard</a>
              <div class="text-[9.5px] font-bold uppercase tracking-wider text-red-200/80 px-2.5 pt-2">PENILAIAN KARYAWAN</div>
              <a href="{{ route('penilaian.div') }}" class="flex items-center gap-3 px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('penilaian.div') ? 'bg-[#b2373b] text-white shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:bg-white/10' }}">Data Penilaian</a>
              <a href="{{ route('penilaian.cari') }}" class="flex items-center gap-3 px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('penilaian.cari') || request()->routeIs('penilaian.show*') ? 'bg-[#b2373b] text-white shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:bg-white/10' }}">Form Penilaian</a>
              <a href="{{ route('penilaian.riwayat') }}" class="flex items-center gap-3 px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('penilaian.riwayat') ? 'bg-[#b2373b] text-white shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:bg-white/10' }}">Riwayat Penilaian</a>
              <div class="text-[9.5px] font-bold uppercase tracking-wider text-red-200/80 px-2.5 pt-2">LAPORAN &amp; REKAP</div>
              <a href="{{ route('laporan.index') }}" class="flex items-center gap-3 px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('laporan.*') ? 'bg-[#b2373b] text-white shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:bg-white/10' }}">Pusat Laporan &amp; Rekap</a>
            @elseif($user->isDepartmentHead())
              <div class="text-[9.5px] font-bold uppercase tracking-wider text-red-200/80 px-2.5 pt-2">DASHBOARD</div>
              <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('dashboard') ? 'bg-[#b2373b] text-white shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:bg-white/10' }}">Dashboard</a>
              <div class="text-[9.5px] font-bold uppercase tracking-wider text-red-200/80 px-2.5 pt-2">PENILAIAN DEPARTEMEN</div>
              <a href="{{ route('penilaian.dept') }}" class="flex items-center gap-3 px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('penilaian.dept') || request()->routeIs('penilaian.dept.saveDraft') ? 'bg-[#b2373b] text-white shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:bg-white/10' }}">Data Penilaian</a>
              <a href="{{ route('penilaian.dept.penyesuaian') }}" class="flex items-center gap-3 px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('penilaian.dept.penyesuaian*') ? 'bg-[#b2373b] text-white shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:bg-white/10' }}">Penyesuaian Penilaian</a>
              <a href="{{ route('penilaian.cari') }}" class="flex items-center gap-3 px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('penilaian.cari') || request()->routeIs('penilaian.show*') ? 'bg-[#b2373b] text-white shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:bg-white/10' }}">Form Penilaian</a>
              <a href="{{ route('penilaian.riwayat') }}" class="flex items-center gap-3 px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('penilaian.riwayat') ? 'bg-[#b2373b] text-white shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:bg-white/10' }}">Riwayat Penilaian</a>
              <div class="text-[9.5px] font-bold uppercase tracking-wider text-red-200/80 px-2.5 pt-2">LAPORAN &amp; REKAP</div>
              <a href="{{ route('laporan.index') }}" class="flex items-center gap-3 px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('laporan.*') ? 'bg-[#b2373b] text-white shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:bg-white/10' }}">Pusat Laporan &amp; Rekap</a>
            @elseif($user->isSectionHead())
              <div class="text-[9.5px] font-bold uppercase tracking-wider text-red-200/80 px-2.5 pt-2">DASHBOARD</div>
              <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('dashboard') ? 'bg-[#b2373b] text-white shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:bg-white/10' }}">Dashboard</a>
              <div class="text-[9.5px] font-bold uppercase tracking-wider text-red-200/80 px-2.5 pt-2">
                {{ $user->hasForemanSubordinates() ? 'PENILAIAN SEKSI' : 'PENILAIAN KARYAWAN' }}
              </div>
              @if($user->hasForemanSubordinates())
                <a href="{{ route('penilaian.section') }}" class="flex items-center gap-3 px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('penilaian.section*') ? 'bg-[#b2373b] text-white shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:bg-white/10' }}">Data Penilaian</a>
              @else
                <a href="{{ route('karyawan.index') }}" class="flex items-center gap-3 px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('karyawan.*') ? 'bg-[#b2373b] text-white shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:bg-white/10' }}">Data Karyawan</a>
              @endif
              <a href="{{ route('penilaian.cari') }}" class="flex items-center gap-3 px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('penilaian.cari') || request()->routeIs('penilaian.show*') ? 'bg-[#b2373b] text-white shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:bg-white/10' }}">Form Penilaian</a>
              <a href="{{ route('penilaian.riwayat') }}" class="flex items-center gap-3 px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('penilaian.riwayat') ? 'bg-[#b2373b] text-white shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:bg-white/10' }}">Riwayat Penilaian</a>
              <div class="text-[9.5px] font-bold uppercase tracking-wider text-red-200/80 px-2.5 pt-2">LAPORAN &amp; REKAP</div>
              <a href="{{ route('laporan.index') }}" class="flex items-center gap-3 px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('laporan.*') ? 'bg-[#b2373b] text-white shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:bg-white/10' }}">Pusat Laporan &amp; Rekap</a>
            @elseif($user->isForeman())
              <div class="text-[9.5px] font-bold uppercase tracking-wider text-red-200/80 px-2.5 pt-2">DASHBOARD</div>
              <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('dashboard') ? 'bg-[#b2373b] text-white shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:bg-white/10' }}">Dashboard</a>
              <div class="text-[9.5px] font-bold uppercase tracking-wider text-red-200/80 px-2.5 pt-2">PENILAIAN KARYAWAN</div>
              <a href="{{ route('karyawan.index') }}" class="flex items-center gap-3 px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('karyawan.*') ? 'bg-[#b2373b] text-white shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:bg-white/10' }}">Data Karyawan</a>
              <a href="{{ route('penilaian.cari') }}" class="flex items-center gap-3 px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('penilaian.cari') || request()->routeIs('penilaian.show*') ? 'bg-[#b2373b] text-white shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:bg-white/10' }}">Form Penilaian</a>
              <a href="{{ route('penilaian.riwayat') }}" class="flex items-center gap-3 px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('penilaian.riwayat') ? 'bg-[#b2373b] text-white shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:bg-white/10' }}">Riwayat Penilaian</a>
              <div class="text-[9.5px] font-bold uppercase tracking-wider text-red-200/80 px-2.5 pt-2">LAPORAN &amp; REKAP</div>
              <a href="{{ route('laporan.index') }}" class="flex items-center gap-3 px-3.5 py-2.5 text-xs font-bold transition-all {{ request()->routeIs('laporan.*') ? 'bg-[#b2373b] text-white shadow-md shadow-black/20 border border-white/10' : 'text-white/85 hover:bg-white/10' }}">Pusat Laporan &amp; Rekap</a>
            @endif

          </nav>
        </div>

        <!-- User Profile in Mobile Drawer -->
        <div class="pt-3 border-t border-white/15 relative z-10">
          <div class="flex items-center justify-between gap-3 mb-3">
            <div class="flex items-center gap-3 min-w-0">
              <div class="w-10 h-10 bg-slate-100 flex items-center justify-center text-slate-500 shadow-sm flex-shrink-0">
                <svg class="w-6 h-6 text-slate-400" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                </svg>
              </div>
              <div class="min-w-0">
                <h4 class="font-bold text-xs text-white truncate">{{ $cleanName }}</h4>
                <p class="text-[10px] text-red-200/90 font-mono"><span class="text-red-200/70 font-semibold">NRP :</span> {{ $user->nrp }}</p>
                <p class="text-[10px] text-red-200/80 uppercase truncate"><span class="text-red-200/70 font-semibold">DEPARTMENT :</span> {{ $user->departemen ?? 'PT MUSASHI' }}</p>
              </div>
            </div>
          </div>
          <div class="mb-2.5">
            <a href="{{ route('profile.index') }}" 
               class="w-full py-2 px-3 bg-white text-[#92302e] hover:bg-slate-100 font-bold text-xs flex items-center justify-center gap-2 transition-all shadow-sm">
              <svg class="w-4 h-4 text-[#92302e]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
              </svg>
              <span>Buka Profil &amp; TTD</span>
            </a>
          </div>
          <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="w-full py-2 bg-black/30 hover:bg-black/50 text-red-100 text-xs font-bold transition-colors flex items-center justify-center gap-2 border border-white/15 cursor-pointer">
              <svg class="w-4 h-4 text-rose-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
              <span>Keluar (Logout)</span>
            </button>
          </form>
        </div>
      </div>
    </div>
    @endauth

    <!-- Scrollable Main Content (Ultra Responsive: Tablets/NexMax, Small/Medium/Wide Laptops, Desktop Monitors, TVs/4K) -->
    <main class="flex-1 overflow-y-auto px-3.5 pt-3.5 pb-6 sm:px-6 sm:pt-4 sm:pb-8 lg:px-6 lg:pt-4.5 lg:pb-8">
      <div class="w-full max-w-[1920px] 2xl:max-w-[2400px] mx-auto">
        <!-- Global Pop-up Notification Toast (Auto-dismiss 4-5s, no double, crisp Musashi styling) -->
    <div class="fixed top-5 right-5 z-50 flex flex-col gap-3 max-w-md w-full px-4 sm:px-0 pointer-events-none" aria-live="polite">
        @if(session('success'))
        <div x-data="{ show: true, progress: 100 }"
             x-init="
                let start = Date.now();
                let duration = 4000;
                let timer = setInterval(() => {
                    let elapsed = Date.now() - start;
                    progress = Math.max(0, 100 - (elapsed / duration) * 100);
                    if (elapsed >= duration) {
                        clearInterval(timer);
                        show = false;
                    }
                }, 40);
             "
             x-show="show"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 transform translate-y-[-10px] scale-95"
             x-transition:enter-end="opacity-100 transform translate-y-0 scale-100"
             x-transition:leave="transition ease-in duration-300"
             x-transition:leave-start="opacity-100 transform translate-y-0 scale-100"
             x-transition:leave-end="opacity-0 transform translate-y-[-10px] scale-95"
             class="pointer-events-auto bg-white border-2 border-emerald-600 shadow-xl rounded-none overflow-hidden flex flex-col">
            <div class="p-3.5 flex items-start justify-between gap-3 bg-emerald-50/80">
                <div class="flex items-start gap-2.5">
                    <div class="w-6 h-6 rounded-none bg-emerald-600 text-white flex items-center justify-center shrink-0 font-bold text-xs shadow-xs mt-0.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <div>
                        <div class="text-[11px] font-black uppercase tracking-wider text-emerald-900">Berhasil</div>
                        <p class="text-xs font-semibold text-slate-800 leading-snug mt-0.5">{{ session('success') }}</p>
                    </div>
                </div>
                <button type="button" @click="show = false" class="text-slate-400 hover:text-slate-800 p-1 font-bold text-base leading-none cursor-pointer transition" title="Tutup">&times;</button>
            </div>
            <!-- Progress Bar Countdown (4 seconds) -->
            <div class="w-full bg-emerald-100 h-1">
                <div class="bg-emerald-600 h-1 transition-all duration-75 ease-linear" :style="'width: ' + progress + '%'"></div>
            </div>
        </div>
        @endif

        @if(session('error'))
        <div x-data="{ show: true, progress: 100 }"
             x-init="
                let start = Date.now();
                let duration = 5000;
                let timer = setInterval(() => {
                    let elapsed = Date.now() - start;
                    progress = Math.max(0, 100 - (elapsed / duration) * 100);
                    if (elapsed >= duration) {
                        clearInterval(timer);
                        show = false;
                    }
                }, 40);
             "
             x-show="show"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 transform translate-y-[-10px] scale-95"
             x-transition:enter-end="opacity-100 transform translate-y-0 scale-100"
             x-transition:leave="transition ease-in duration-300"
             x-transition:leave-start="opacity-100 transform translate-y-0 scale-100"
             x-transition:leave-end="opacity-0 transform translate-y-[-10px] scale-95"
             class="pointer-events-auto bg-white border-2 border-rose-600 shadow-xl rounded-none overflow-hidden flex flex-col">
            <div class="p-3.5 flex items-start justify-between gap-3 bg-rose-50/80">
                <div class="flex items-start gap-2.5">
                    <div class="w-6 h-6 rounded-none bg-rose-600 text-white flex items-center justify-center shrink-0 font-bold text-xs shadow-xs mt-0.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="text-[11px] font-black uppercase tracking-wider text-rose-900">Perhatian / Gagal</div>
                        <p class="text-xs font-semibold text-slate-800 leading-snug mt-0.5">{{ session('error') }}</p>
                    </div>
                </div>
                <button type="button" @click="show = false" class="text-slate-400 hover:text-slate-800 p-1 font-bold text-base leading-none cursor-pointer transition" title="Tutup">&times;</button>
            </div>
            <!-- Progress Bar Countdown (5 seconds) -->
            <div class="w-full bg-rose-100 h-1">
                <div class="bg-rose-600 h-1 transition-all duration-75 ease-linear" :style="'width: ' + progress + '%'"></div>
            </div>
        </div>
        @endif
    </div>

        @yield('content')
      </div>
    </main>
  </div>

  <script>
    // Robust smooth scrolling for elements inside <main>, strictly preventing window/body viewport displacement
    window.smoothScrollToElement = function(target, offset = 16) {
        const el = typeof target === 'string' ? document.getElementById(target) : target;
        if (!el) return;
        const mainContainer = document.querySelector('main');
        if (mainContainer) {
            const elRect = el.getBoundingClientRect();
            const mainRect = mainContainer.getBoundingClientRect();
            const targetScrollTop = mainContainer.scrollTop + (elRect.top - mainRect.top) - offset;
            mainContainer.scrollTo({
                top: Math.max(0, targetScrollTop),
                behavior: 'smooth'
            });
        } else {
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        if (window.scrollY !== 0 || window.scrollX !== 0) {
            window.scrollTo(0, 0);
        }
        if (document.documentElement.scrollTop !== 0) {
            document.documentElement.scrollTop = 0;
        }
        if (document.body.scrollTop !== 0) {
            document.body.scrollTop = 0;
        }
    };

    // Ensure outer document viewport stays strictly pinned at 0 in this full-height flex layout
    window.addEventListener('scroll', function() {
        if (window.scrollY !== 0 || window.scrollX !== 0) {
            window.scrollTo(0, 0);
        }
    }, { passive: true });
  </script>

  @stack('scripts')
  <script>
        // Instant Top Loading Progress Bar on Navigation Click
    document.addEventListener('click', function(e) {
      const link = e.target.closest('a');
      if (link && link.href && link.target !== '_blank' && !link.href.includes('#') && !link.href.includes('javascript') && !link.hasAttribute('download')) {
        const bar = document.getElementById('page-progress-bar');
        if (bar) {
          bar.style.opacity = '1';
          bar.style.width = '80%';
        }
      }
    });

    // 1. Session Keep-Alive Heartbeat: Ping server every 2 minutes so idle tabs never expire unexpectedly
    setInterval(function() {
      fetch('/up', { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .catch(function() {});
    }, 120000);

    // 2. Instant Navigation Prefetch for Sidebar links on mouse hover / touch
    document.addEventListener('DOMContentLoaded', function() {
      const prefetchedUrls = new Set();
      document.querySelectorAll('aside a[href^="/"], nav a[href^="/"]').forEach(function(link) {
        link.addEventListener('mouseenter', function() {
          const href = link.getAttribute('href');
          if (href && !prefetchedUrls.has(href) && !href.includes('/logout') && !href.includes('/export')) {
            prefetchedUrls.add(href);
            const linkTag = document.createElement('link');
            linkTag.rel = 'prefetch';
            linkTag.href = href;
            document.head.appendChild(linkTag);
          }
        }, { passive: true });
      });
    });
  </script>

</body>
</html>
