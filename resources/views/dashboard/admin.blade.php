<!-- ========================================================================= -->
<!-- 2. DASHBOARD ADMIN (Table-First, Sesuai Master Karyawan & Sharp Musashi)   -->
<!-- ========================================================================= -->
<div class="space-y-6">

    <!-- Top Header Card (Musashi Red Banner, Solid & Clean) -->
    <div class="bg-gradient-to-r from-[#962226] via-[#92302e] to-[#781b1f] text-white rounded-none p-5 sm:p-6 border border-[#781b1f] shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight">
                {{ $user->isSuperadmin() ? 'Dashboard Super Admin' : 'Dashboard Admin' }}
            </h1>
            <p class="text-xs text-red-100 font-medium mt-0.5">
                Selamat datang, <strong class="text-white">{{ $user->nama ?? ($user->isSuperadmin() ? 'Super Administrator' : 'Admin Penilaian Karyawan') }}</strong> &bull; Pantauan realisasi &amp; struktur penilaian karyawan riil
            </p>
        </div>
        
        <!-- Action Buttons (Kelola Karyawan & Sinkron DB) -->
        <div class="flex flex-wrap items-center gap-2">
            @if($user->isSuperadmin())
            <a href="{{ route('superadmin.pengaturan') }}" 
               class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-none bg-white/10 hover:bg-white/20 text-white text-xs font-bold border border-white/30 shadow-xs transition">
                <svg class="w-3.5 h-3.5 text-red-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                <span>Pengaturan Sistem</span>
            </a>
            @else
            <a href="{{ route('admin.historyPenilaian') }}" 
               class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-none bg-white/10 hover:bg-white/20 text-white text-xs font-bold border border-white/30 shadow-xs transition">
                <svg class="w-3.5 h-3.5 text-red-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span>History Penilaian</span>
            </a>
            @endif
            <a href="{{ route('admin.karyawan') }}" 
               class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-none bg-white hover:bg-slate-100 text-[#92302e] text-xs font-bold shadow-xs transition">
                <svg class="w-3.5 h-3.5 text-[#92302e]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
                <span>Kelola Karyawan ({{ $adminStats['total_karyawan'] }})</span>
            </a>
        </div>
    </div>

    <!-- 5 Stat Cards di Atas (Total Karyawan Seluruhnya, Division, Department, Section Head, Foreman) -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3.5">
        
        <!-- 1. Total Karyawan (Seluruhnya) -->
        <a href="{{ route('admin.karyawan') }}" 
           class="bg-white rounded-none p-4 border border-slate-300 shadow-sm flex items-center justify-between hover:border-slate-400 transition group">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-none bg-[#fbe8e8] text-[#92302e] font-black flex items-center justify-center text-sm flex-shrink-0 group-hover:scale-105 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-medium leading-none">Total Karyawan</p>
                    <div class="flex items-baseline gap-1 mt-1.5">
                        <span class="text-2xl font-black text-slate-900 leading-none">{{ $adminStats['total_karyawan'] }}</span>
                        <span class="text-[10px] text-slate-400 font-medium leading-none">(Semua Divisi)</span>
                    </div>
                </div>
            </div>
            <span class="text-[10px] font-bold text-slate-400 group-hover:text-slate-700">&rarr;</span>
        </a>

        <!-- 2. Total Division -->
        <a href="{{ route('admin.divisiDepartemen') }}" 
           class="bg-white rounded-none p-4 border border-slate-300 shadow-sm flex items-center justify-between hover:border-slate-400 transition group">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-none bg-indigo-50 text-indigo-700 font-black flex items-center justify-center text-sm flex-shrink-0 group-hover:scale-105 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-medium leading-none">Total Divisi</p>
                    <div class="flex items-baseline gap-1 mt-1.5">
                        <span class="text-2xl font-black text-slate-900 leading-none">{{ $adminStats['total_divisi'] ?? 0 }}</span>
                        <span class="text-[10px] text-slate-400 font-medium leading-none">Divisi Aktif</span>
                    </div>
                </div>
            </div>
            <span class="text-[10px] font-bold text-slate-400 group-hover:text-slate-700">&rarr;</span>
        </a>

        <!-- 3. Total Departemen -->
        <a href="{{ route('admin.divisiDepartemen') }}" 
           class="bg-white rounded-none p-4 border border-slate-300 shadow-sm flex items-center justify-between hover:border-slate-400 transition group">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-none bg-blue-50 text-blue-700 font-black flex items-center justify-center text-sm flex-shrink-0 group-hover:scale-105 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-medium leading-none">Total Departemen</p>
                    <div class="flex items-baseline gap-1 mt-1.5">
                        <span class="text-2xl font-black text-slate-900 leading-none">{{ $adminStats['total_departemen'] }}</span>
                        <span class="text-[10px] text-slate-400 font-medium leading-none">Tahun {{ date('Y') }}</span>
                    </div>
                </div>
            </div>
            <span class="text-[10px] font-bold text-slate-400 group-hover:text-slate-700">&rarr;</span>
        </a>

        <!-- 4. Total Section Head -->
        <a href="#tabel-hierarki" 
           class="bg-white rounded-none p-4 border border-slate-300 shadow-sm flex items-center justify-between hover:border-slate-400 transition group">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-none bg-amber-50 text-amber-800 font-black flex items-center justify-center text-sm flex-shrink-0 group-hover:scale-105 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-medium leading-none">Total Section Head</p>
                    <div class="flex items-baseline gap-1 mt-1.5">
                        <span class="text-2xl font-black text-slate-900 leading-none">{{ $adminStats['total_section_head'] ?? 0 }}</span>
                        <span class="text-[10px] text-slate-400 font-medium leading-none">Penilai Seksi</span>
                    </div>
                </div>
            </div>
            <span class="text-[10px] font-bold text-slate-400 group-hover:text-slate-700">&darr;</span>
        </a>

        <!-- 5. Total Foreman -->
        <a href="#tabel-hierarki" 
           class="bg-white rounded-none p-4 border border-slate-300 shadow-sm flex items-center justify-between hover:border-slate-400 transition group">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-none bg-emerald-50 text-emerald-700 font-black flex items-center justify-center text-sm flex-shrink-0 group-hover:scale-105 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-medium leading-none">Total Foreman</p>
                    <div class="flex items-baseline gap-1 mt-1.5">
                        <span class="text-2xl font-black text-slate-900 leading-none">{{ $adminStats['total_foreman'] ?? 0 }}</span>
                        <span class="text-[10px] text-slate-400 font-medium leading-none">Penilai Lapangan</span>
                    </div>
                </div>
            </div>
            <span class="text-[10px] font-bold text-slate-400 group-hover:text-slate-700">&darr;</span>
        </a>

    </div>

    <!-- Ringkasan Status Evaluasi Karyawan (Total Sudah Dinilai & Total Belum Dinilai) -->
    <div class="bg-white rounded-none p-4 border border-slate-300 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-6">
            <!-- Total Sudah Dinilai -->
            <div class="flex items-center gap-3">
                <span class="w-3 h-3 rounded-none bg-emerald-500 flex-shrink-0"></span>
                <div>
                    <p class="text-xs text-slate-500 font-medium">Total Sudah Dinilai</p>
                    <div class="flex items-baseline gap-1.5">
                        <span class="text-xl font-black text-slate-900">{{ $adminStats['total_dinilai'] }}</span>
                        <span class="text-xs font-bold text-emerald-700">({{ $adminStats['pct_dinilai'] }}%)</span>
                    </div>
                </div>
            </div>

            <!-- Separator -->
            <div class="hidden sm:block h-8 w-px bg-slate-200"></div>

            <!-- Total Belum Dinilai -->
            <div class="flex items-center gap-3">
                <span class="w-3 h-3 rounded-none bg-amber-500 flex-shrink-0"></span>
                <div>
                    <p class="text-xs text-slate-500 font-medium">Total Belum Dinilai</p>
                    <div class="flex items-baseline gap-1.5">
                        <span class="text-xl font-black text-slate-900">{{ $adminStats['total_belum'] }}</span>
                        <span class="text-xs font-bold text-amber-700">({{ $adminStats['pct_belum'] }}%)</span>
                    </div>
                </div>
            </div>

            <!-- Separator -->
            <div class="hidden sm:block h-8 w-px bg-slate-200"></div>

            <!-- Target Seluruh Perusahaan -->
            <div>
                <p class="text-xs text-slate-500 font-medium">Target Evaluasi Perusahaan</p>
                <p class="text-xl font-black text-slate-900">{{ $adminStats['total_karyawan'] }} <span class="text-xs font-medium text-slate-500">Karyawan</span></p>
            </div>
        </div>

        <!-- Progress Bar Realisasi Perusahaan -->
        <div class="w-full md:w-64">
            <div class="flex items-center justify-between text-xs font-bold mb-1">
                <span class="text-slate-600">Progres Penilaian Masuk</span>
                <span class="text-[#92302e] font-black">{{ $adminStats['pct_dinilai'] }}%</span>
            </div>
            <div class="w-full bg-slate-100 rounded-none h-2.5 overflow-hidden border border-slate-200">
                <div class="bg-emerald-600 h-2.5 rounded-none" style="width: {{ $adminStats['pct_dinilai'] }}%"></div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- TABEL 1: DISTRIBUSI HIERARKI PENILAI & KARYAWAN BINAAN                    -->
    <!-- (Tabel Lengkap Detail: Foreman, Section Head, Dept Head)                  -->
    <!-- ========================================================================= -->
    <div id="tabel-hierarki" class="bg-white rounded-none border border-slate-300 shadow-sm overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50/60">
            <div>
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-none bg-[#92302e]"></span>
                    <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider">
                        Distribusi Hierarki Penilai &amp; Karyawan Binaan
                    </h2>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">
                    Struktur evaluasi bertingkat riil sesuai Master Karyawan (Total: <strong>{{ $adminStats['total_karyawan'] }}</strong> Karyawan)
                </p>
            </div>
            <a href="{{ route('admin.karyawan') }}" 
               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-slate-300 hover:bg-slate-50 text-xs font-bold text-[#92302e] shadow-2xs transition">
                <span>Buka Master Karyawan</span>
                <span>&rarr;</span>
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse border border-slate-300">
                <thead>
                    <tr class="bg-slate-100 text-slate-700 font-bold text-[11px] uppercase tracking-wider">
                        <th class="py-3 px-3.5 border border-slate-300 text-center w-12 whitespace-nowrap">No</th>
                        <th class="py-3 px-3.5 border border-slate-300 whitespace-nowrap">Tingkat / Level</th>
                        <th class="py-3 px-3.5 border border-slate-300 whitespace-nowrap">NRP</th>
                        <th class="py-3 px-3.5 border border-slate-300 whitespace-nowrap">Nama Penilai</th>
                        <th class="py-3 px-3.5 border border-slate-300 whitespace-nowrap">Division</th>
                        <th class="py-3 px-3.5 border border-slate-300 whitespace-nowrap">Department</th>
                        <th class="py-3 px-3.5 border border-slate-300 whitespace-nowrap">Section</th>
                        <th class="py-3 px-3.5 border border-slate-300 text-center whitespace-nowrap">Total Binaan</th>
                        <th class="py-3 px-3.5 border border-slate-300 text-center w-24 whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    
                    <!-- 1. TINGKAT FOREMAN (Penilai Pertama) -->
                    <tr class="bg-blue-50/70 font-bold text-blue-900 border-t-2 border-b border-blue-200">
                        <td colspan="9" class="py-2.5 px-3.5 border border-slate-300">
                            <div class="flex items-center justify-between">
                                <span class="flex items-center gap-2 text-xs uppercase tracking-wider">
                                    <span class="w-2 h-2 rounded-none bg-blue-600"></span>
                                    <span>1. Tingkat Foreman &bull; {{ count($adminHierarchy['foremen_list'] ?? []) }} Akun Penilai Riil</span>
                                </span>
                                <span class="text-[11px] font-bold text-blue-800">
                                    Total Binaan: {{ array_sum(array_column($adminHierarchy['foremen_list'] ?? [], 'count')) }} orang
                                </span>
                            </div>
                        </td>
                    </tr>
                    @php $rowNo = 1; @endphp
                    @forelse($adminHierarchy['foremen_list'] ?? [] as $f)
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="py-2.5 px-3.5 border border-slate-300 text-center text-slate-500 font-mono">{{ $rowNo++ }}</td>
                        <td class="py-2.5 px-3.5 border border-slate-300 whitespace-nowrap">
                            <span class="px-2 py-0.5 rounded-none text-[10px] font-bold bg-blue-100 text-blue-800 border border-blue-200">
                                Foreman
                            </span>
                        </td>
                        <td class="py-2.5 px-3.5 border border-slate-300 font-mono font-bold text-slate-800 whitespace-nowrap">{{ $f['nrp'] }}</td>
                        <td class="py-2.5 px-3.5 border border-slate-300 font-bold text-slate-900 whitespace-nowrap">{{ $f['nama'] }}</td>
                        <td class="py-2.5 px-3.5 border border-slate-300 text-slate-600 whitespace-nowrap">{{ $f['division'] ?? 'PRODUCTION A DIV' }}</td>
                        <td class="py-2.5 px-3.5 border border-slate-300 font-semibold text-slate-800 whitespace-nowrap">{{ $f['departemen'] }}</td>
                        <td class="py-2.5 px-3.5 border border-slate-300 text-slate-600 whitespace-nowrap">{{ $f['seksi'] ?: '-' }}</td>
                        <td class="py-2.5 px-3.5 border border-slate-300 text-center font-black text-slate-900 whitespace-nowrap">
                            <span class="px-2 py-0.5 rounded-none bg-slate-100 font-black text-slate-900">
                                {{ $f['count'] }} binaan
                            </span>
                        </td>
                        <td class="py-2.5 px-3.5 border border-slate-300 text-center whitespace-nowrap">
                            <a href="{{ route('admin.karyawan', ['search' => $f['nama']]) }}" 
                               class="inline-flex items-center gap-1 px-2.5 py-1 rounded-none bg-white border border-slate-300 hover:bg-slate-50 text-[11px] font-bold text-[#92302e] shadow-2xs transition">
                                <span>Lihat</span>
                                <span>&rarr;</span>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="py-3 px-3.5 border border-slate-300 text-center text-slate-400">Tidak ada data foreman.</td>
                    </tr>
                    @endforelse

                    <!-- 2. TINGKAT SECTION HEAD (Penilai Seksi) -->
                    <tr class="bg-amber-50/70 font-bold text-amber-900 border-t-2 border-b border-amber-200">
                        <td colspan="9" class="py-2.5 px-3.5 border border-slate-300">
                            <div class="flex items-center justify-between">
                                <span class="flex items-center gap-2 text-xs uppercase tracking-wider">
                                    <span class="w-2 h-2 rounded-none bg-amber-600"></span>
                                    <span>2. Tingkat Section Head &bull; {{ count($adminHierarchy['section_heads_list'] ?? []) }} Penilai Seksi</span>
                                </span>
                                <span class="text-[11px] font-bold text-amber-800">
                                    Total Karyawan Binaan: {{ array_sum(array_column($adminHierarchy['section_heads_list'] ?? [], 'count')) }} orang
                                </span>
                            </div>
                        </td>
                    </tr>
                    @forelse($adminHierarchy['section_heads_list'] ?? [] as $sh)
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="py-2.5 px-3.5 border border-slate-300 text-center text-slate-500 font-mono">{{ $rowNo++ }}</td>
                        <td class="py-2.5 px-3.5 border border-slate-300 whitespace-nowrap">
                            <span class="px-2 py-0.5 rounded-none text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                Section Head
                            </span>
                        </td>
                        <td class="py-2.5 px-3.5 border border-slate-300 font-mono font-bold text-slate-800 whitespace-nowrap">{{ $sh['nrp'] }}</td>
                        <td class="py-2.5 px-3.5 border border-slate-300 font-bold text-slate-900 whitespace-nowrap">{{ $sh['nama'] }}</td>
                        <td class="py-2.5 px-3.5 border border-slate-300 text-slate-600 whitespace-nowrap">{{ $sh['division'] ?? 'PRODUCTION A DIV' }}</td>
                        <td class="py-2.5 px-3.5 border border-slate-300 font-semibold text-slate-800 whitespace-nowrap">{{ $sh['departemen'] }}</td>
                        <td class="py-2.5 px-3.5 border border-slate-300 text-slate-600 whitespace-nowrap">{{ $sh['seksi'] ?: '-' }}</td>
                        <td class="py-2.5 px-3.5 border border-slate-300 text-center font-black text-slate-900 whitespace-nowrap">
                            <span class="px-2 py-0.5 rounded-none bg-slate-100 font-black text-slate-900">
                                {{ $sh['count'] }} karyawan
                            </span>
                        </td>
                        <td class="py-2.5 px-3.5 border border-slate-300 text-center whitespace-nowrap">
                            <a href="{{ route('admin.karyawan', ['search' => $sh['nama']]) }}" 
                               class="inline-flex items-center gap-1 px-2.5 py-1 rounded-none bg-white border border-slate-300 hover:bg-slate-50 text-[11px] font-bold text-[#92302e] shadow-2xs transition">
                                <span>Lihat</span>
                                <span>&rarr;</span>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="py-3 px-3.5 border border-slate-300 text-center text-slate-400">Tidak ada data section head.</td>
                    </tr>
                    @endforelse

                    <!-- 3. TINGKAT DEPT HEAD (Penilai Final) -->
                    <tr class="bg-emerald-50/70 font-bold text-emerald-900 border-t-2 border-b border-emerald-200">
                        <td colspan="9" class="py-2.5 px-3.5 border border-slate-300">
                            <div class="flex items-center justify-between">
                                <span class="flex items-center gap-2 text-xs uppercase tracking-wider">
                                    <span class="w-2 h-2 rounded-none bg-emerald-600"></span>
                                    <span>3. Tingkat Dept Head &bull; {{ count($adminHierarchy['dept_heads_list'] ?? []) }} Penilai Final</span>
                                </span>
                                <span class="text-[11px] font-bold text-emerald-800">
                                    Penilai Tingkat Departemen
                                </span>
                            </div>
                        </td>
                    </tr>
                    @forelse($adminHierarchy['dept_heads_list'] ?? [] as $dh)
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="py-2.5 px-3.5 border border-slate-300 text-center text-slate-500 font-mono">{{ $rowNo++ }}</td>
                        <td class="py-2.5 px-3.5 border border-slate-300 whitespace-nowrap">
                            <span class="px-2 py-0.5 rounded-none text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                Dept Head
                            </span>
                        </td>
                        <td class="py-2.5 px-3.5 border border-slate-300 font-mono font-bold text-slate-800 whitespace-nowrap">{{ $dh['nrp'] }}</td>
                        <td class="py-2.5 px-3.5 border border-slate-300 font-bold text-slate-900 whitespace-nowrap">{{ $dh['nama'] }}</td>
                        <td class="py-2.5 px-3.5 border border-slate-300 text-slate-600 whitespace-nowrap">{{ $dh['division'] ?? 'PRODUCTION A DIV' }}</td>
                        <td class="py-2.5 px-3.5 border border-slate-300 font-semibold text-slate-800 whitespace-nowrap">{{ $dh['departemen'] }}</td>
                        <td class="py-2.5 px-3.5 border border-slate-300 text-slate-600 whitespace-nowrap">{{ $dh['seksi'] ?: '-' }}</td>
                        <td class="py-2.5 px-3.5 border border-slate-300 text-center font-black text-slate-900 whitespace-nowrap">
                            <span class="px-2 py-0.5 rounded-none bg-slate-100 font-black text-slate-900">
                                {{ $dh['count'] }} karyawan
                            </span>
                        </td>
                        <td class="py-2.5 px-3.5 border border-slate-300 text-center whitespace-nowrap">
                            <a href="{{ route('admin.karyawan', ['search' => $dh['nama']]) }}" 
                               class="inline-flex items-center gap-1 px-2.5 py-1 rounded-none bg-white border border-slate-300 hover:bg-slate-50 text-[11px] font-bold text-[#92302e] shadow-2xs transition">
                                <span>Lihat</span>
                                <span>&rarr;</span>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="py-3 px-3.5 border border-slate-300 text-center text-slate-400">Tidak ada data dept head.</td>
                    </tr>
                    @endforelse

                    <!-- 4. TINGKAT DIVISION HEAD (Penilai Eksekutif Divisi) -->
                    <tr class="bg-purple-50/70 font-bold text-purple-900 border-t-2 border-b border-purple-200">
                        <td colspan="9" class="py-2.5 px-3.5 border border-slate-300">
                            <div class="flex items-center justify-between">
                                <span class="flex items-center gap-2 text-xs uppercase tracking-wider">
                                    <span class="w-2 h-2 rounded-none bg-purple-600"></span>
                                    <span>4. Tingkat Division Head &bull; {{ count($adminHierarchy['div_heads_list'] ?? []) }} Penilai Eksekutif Divisi</span>
                                </span>
                                <span class="text-[11px] font-bold text-purple-800">
                                    Persetujuan Tingkat Divisi / BOD
                                </span>
                            </div>
                        </td>
                    </tr>
                    @forelse($adminHierarchy['div_heads_list'] ?? [] as $divH)
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="py-2.5 px-3.5 border border-slate-300 text-center text-slate-500 font-mono">{{ $rowNo++ }}</td>
                        <td class="py-2.5 px-3.5 border border-slate-300 whitespace-nowrap">
                            <span class="px-2 py-0.5 rounded-none text-[10px] font-bold bg-purple-100 text-purple-800 border border-purple-200">
                                Division Head
                            </span>
                        </td>
                        <td class="py-2.5 px-3.5 border border-slate-300 font-mono font-bold text-slate-800 whitespace-nowrap">{{ $divH['nrp'] }}</td>
                        <td class="py-2.5 px-3.5 border border-slate-300 font-bold text-slate-900 whitespace-nowrap">{{ $divH['nama'] }}</td>
                        <td class="py-2.5 px-3.5 border border-slate-300 text-slate-600 whitespace-nowrap">{{ $divH['division'] ?? 'PRODUCTION A DIV' }}</td>
                        <td class="py-2.5 px-3.5 border border-slate-300 font-semibold text-slate-800 whitespace-nowrap">{{ $divH['departemen'] }}</td>
                        <td class="py-2.5 px-3.5 border border-slate-300 text-slate-600 whitespace-nowrap">{{ $divH['seksi'] ?: 'Seluruh Seksi' }}</td>
                        <td class="py-2.5 px-3.5 border border-slate-300 text-center font-black text-slate-900 whitespace-nowrap">
                            <span class="px-2 py-0.5 rounded-none bg-slate-100 font-black text-slate-900">
                                {{ $divH['count'] }} karyawan
                            </span>
                        </td>
                        <td class="py-2.5 px-3.5 border border-slate-300 text-center whitespace-nowrap">
                            <a href="{{ route('admin.karyawan', ['search' => $divH['nama']]) }}" 
                               class="inline-flex items-center gap-1 px-2.5 py-1 rounded-none bg-white border border-slate-300 hover:bg-slate-50 text-[11px] font-bold text-[#92302e] shadow-2xs transition">
                                <span>Lihat</span>
                                <span>&rarr;</span>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="py-3 px-3.5 border border-slate-300 text-center text-slate-400">Tidak ada data division head.</td>
                    </tr>
                    @endforelse

                </tbody>
                <tfoot>
                    <tr class="bg-slate-100 font-bold text-slate-900 border-t-2 border-slate-300 text-xs">
                        <td colspan="7" class="py-2.5 px-3.5 border border-slate-300 uppercase tracking-wider text-[11px]">
                            Total Akun Penilai Terdata Sesuai Hierarki Master Karyawan
                        </td>
                        <td class="py-2.5 px-3.5 border border-slate-300 text-center text-slate-900 font-black">
                            {{ $adminStats['total_karyawan'] }}
                        </td>
                        <td class="py-2.5 px-3.5 border border-slate-300 text-center text-slate-400">
                            -
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Middle Card: Aktivitas Penilaian (Aktivitas per Divisi / Departemen) -->
    <div class="bg-white rounded-none p-5 border border-slate-300 shadow-sm space-y-4">
        
        <!-- Header & Controls -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100">
            <div>
                <h3 class="text-sm font-bold text-slate-800 tracking-tight">
                    Aktivitas Penilaian
                </h3>
                <p class="text-[11px] text-slate-400 font-medium">
                    Perbandingan target kuota dan realisasi penilaian per divisi &amp; departemen (Data Riil)
                </p>
            </div>

            <div class="flex items-center gap-3">
                <!-- Legend -->
                <div class="flex items-center gap-3 text-xs">
                    <span class="inline-flex items-center gap-1.5 text-slate-600 font-medium">
                        <span class="w-2.5 h-2.5 rounded-none bg-[#f87171]"></span>
                        <span>Target</span>
                    </span>
                    <span class="inline-flex items-center gap-1.5 text-slate-600 font-medium">
                        <span class="w-2.5 h-2.5 rounded-none bg-[#60a5fa]"></span>
                        <span>Sudah Dinilai</span>
                    </span>
                </div>

                <!-- Link to Master Karyawan -->
                <a href="{{ route('admin.karyawan') }}" class="text-xs font-bold text-emerald-700 hover:text-emerald-800 underline">
                    Lihat Karyawan &rarr;
                </a>
            </div>
        </div>

        <!-- Division KPI Pills -->
        <div class="grid grid-cols-2 md:grid-flow-col md:auto-cols-fr gap-2 pt-1">
            @forelse($aktivitasDivisi as $div)
            <div class="p-2.5 rounded-none bg-slate-50 border border-slate-300 text-center">
                <span class="text-[10px] font-black text-slate-700 uppercase block truncate">{{ $div['kode'] }}</span>
                <span class="text-sm font-black text-slate-900 mt-0.5 block">{{ $div['persentase'] }}%</span>
                <span class="text-[10px] text-slate-500 block font-medium">{{ $div['dinilai'] }} / {{ $div['target'] }} org</span>
            </div>
            @empty
            <div class="col-span-full py-2 text-center text-xs text-slate-400 font-medium">
                Belum ada data aktivitas divisi (Data Karyawan Kosong)
            </div>
            @endforelse
        </div>

        <!-- Bar Chart Canvas -->
        <div class="h-60 sm:h-72 w-full pt-2">
            <canvas id="adminAktivitasChart"></canvas>
        </div>

    </div>

    <!-- ========================================================================= -->
    <!-- TABEL 2: RINGKASAN DIVISI & TARGET KUOTA PENILAIAN PER DEPARTEMEN         -->
    <!-- (Tabel Komprehensif Sesuai Master Karyawan: Progres & Kuota S, A, B, C, K)-->
    <!-- ========================================================================= -->
    <div class="bg-white rounded-none border border-slate-300 shadow-sm overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50/60">
            <div>
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-none bg-[#92302e]"></span>
                    <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider">
                        Ringkasan Divisi
                    </h3>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">
                    Monitoring realisasi target, kuota nilai (S, A, B, C, K), dan atasan penilai per unit terhubung langsung ke Master Karyawan
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.targetKuota') }}" class="inline-flex items-center gap-1 px-3 py-1.5 bg-white border border-slate-300 hover:bg-slate-50 text-xs font-bold text-[#92302e] shadow-2xs transition">
                    <span>Aturan Kuota</span>
                    <span>&rarr;</span>
                </a>
                <a href="{{ route('admin.divisiDepartemen') }}" class="inline-flex items-center gap-1 px-3 py-1.5 bg-[#92302e] hover:bg-[#7e2524] text-xs font-bold text-white shadow-2xs transition">
                    <span>Kelola Divisi</span>
                    <span>&rarr;</span>
                </a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse border border-slate-300">
                <thead>
                    <tr class="bg-slate-100 text-slate-700 font-bold text-[11px] uppercase tracking-wider">
                        <th rowspan="2" class="py-2.5 px-3 border border-slate-300 text-center w-10 whitespace-nowrap">No</th>
                        <th rowspan="2" class="py-2.5 px-3 border border-slate-300 whitespace-nowrap">Division</th>
                        <th rowspan="2" class="py-2.5 px-3 border border-slate-300 whitespace-nowrap">Department</th>
                        <th rowspan="2" class="py-2.5 px-3 border border-slate-300 whitespace-nowrap">Atasan &amp; Penilai</th>
                        <th rowspan="2" class="py-2.5 px-3 border border-slate-300 text-center whitespace-nowrap">Total Karyawan</th>
                        <th colspan="3" class="py-1.5 px-3 border border-slate-300 text-center bg-blue-50/70 text-blue-900 whitespace-nowrap">
                            Progres Penilaian
                        </th>
                        <th colspan="5" class="py-1.5 px-3 border border-slate-300 text-center bg-amber-50/70 text-amber-900 whitespace-nowrap">
                            Target Kuota Nilai (Headcount)
                        </th>
                        <th rowspan="2" class="py-2.5 px-3 border border-slate-300 text-center whitespace-nowrap">Status</th>
                        <th rowspan="2" class="py-2.5 px-3 border border-slate-300 text-center w-16 whitespace-nowrap">Aksi</th>
                    </tr>
                    <tr class="bg-slate-50 text-slate-700 font-bold text-[10px] uppercase tracking-wider">
                        <!-- Progres sub-headers -->
                        <th class="py-2 px-2.5 border border-slate-300 text-center text-emerald-800 whitespace-nowrap">Sudah</th>
                        <th class="py-2 px-2.5 border border-slate-300 text-center text-amber-800 whitespace-nowrap">Belum</th>
                        <th class="py-2 px-2.5 border border-slate-300 text-right whitespace-nowrap">Realisasi</th>
                        <!-- Target Kuota sub-headers -->
                        <th class="py-2 px-2.5 border border-slate-300 text-center whitespace-nowrap bg-emerald-50/40 text-emerald-800">
                            <span class="inline-block px-1.5 py-0.5 rounded-none bg-emerald-100 text-emerald-800 font-black">S</span>
                        </th>
                        <th class="py-2 px-2.5 border border-slate-300 text-center whitespace-nowrap bg-blue-50/40 text-blue-800">
                            <span class="inline-block px-1.5 py-0.5 rounded-none bg-blue-100 text-blue-800 font-black">A</span>
                        </th>
                        <th class="py-2 px-2.5 border border-slate-300 text-center whitespace-nowrap bg-amber-50/40 text-amber-800">
                            <span class="inline-block px-1.5 py-0.5 rounded-none bg-amber-100 text-amber-800 font-black">B</span>
                        </th>
                        <th class="py-2 px-2.5 border border-slate-300 text-center whitespace-nowrap bg-orange-50/40 text-orange-800">
                            <span class="inline-block px-1.5 py-0.5 rounded-none bg-orange-100 text-orange-800 font-black">C</span>
                        </th>
                        <th class="py-2 px-2.5 border border-slate-300 text-center whitespace-nowrap bg-red-50/40 text-red-800">
                            <span class="inline-block px-1.5 py-0.5 rounded-none bg-red-100 text-red-800 font-black">K</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @php $rdNo = 1; @endphp
                    @forelse($ringkasanDivisi as $r)
                    <tr class="hover:bg-slate-50 transition-colors">
                        <!-- No -->
                        <td class="py-2.5 px-3 border border-slate-300 text-center text-slate-500 font-mono">{{ $rdNo++ }}</td>

                        <!-- Division (Kolom Terpisah) -->
                        <td class="py-2.5 px-3 border border-slate-300 font-bold text-slate-700 whitespace-nowrap">
                            {{ $r['division_name'] ?? 'PRODUCTION A DIV' }}
                        </td>

                        <!-- Department (Nama Lengkap & Kode) -->
                        <td class="py-2.5 px-3 border border-slate-300 font-bold text-slate-900 whitespace-nowrap">
                            <span>{{ $r['divisi'] }}</span>
                        </td>

                        <!-- Atasan & Penilai (Sejajar Satu Baris) -->
                        <td class="py-2.5 px-3 border border-slate-300 text-slate-600 text-[11px] font-medium whitespace-nowrap">
                            {{ $r['penilai'] }}
                        </td>

                        <!-- Total Karyawan -->
                        <td class="py-2.5 px-3 border border-slate-300 text-center font-bold text-slate-900 whitespace-nowrap">
                            {{ $r['total_karyawan'] }} <span class="text-[10px] text-slate-400 font-normal">org</span>
                        </td>

                        <!-- Sudah Dinilai -->
                        <td class="py-2.5 px-2.5 border border-slate-300 text-center font-bold text-emerald-700 whitespace-nowrap">
                            {{ $r['sudah_dinilai'] }} <span class="text-[10px] text-slate-400 font-normal">org</span>
                        </td>

                        <!-- Belum Dinilai (Sisa) -->
                        <td class="py-2.5 px-2.5 border border-slate-300 text-center font-bold {{ $r['belum_dinilai'] > 0 ? 'text-amber-700' : 'text-slate-400' }} whitespace-nowrap">
                            {{ $r['belum_dinilai'] }} <span class="text-[10px] text-slate-400 font-normal">org</span>
                        </td>

                        <!-- Realisasi Target (Bar + %) -->
                        <td class="py-2.5 px-3 border border-slate-300 text-right whitespace-nowrap">
                            <div class="flex items-center justify-end gap-2">
                                <div class="w-14 bg-slate-100 rounded-none h-2 overflow-hidden border border-slate-200">
                                    <div class="bg-emerald-600 h-2 rounded-none" style="width: {{ $r['pct_val'] }}%"></div>
                                </div>
                                <span class="font-bold text-slate-900 text-xs w-8 text-right">{{ $r['target'] }}</span>
                            </div>
                        </td>

                        <!-- Target S -->
                        <td class="py-2.5 px-2.5 border border-slate-300 text-center font-bold text-slate-800 bg-emerald-50/20 whitespace-nowrap">
                            <span class="{{ $r['target_s'] > 0 ? 'text-emerald-700 font-black' : 'text-slate-400' }}">{{ $r['target_s'] }}</span>
                        </td>

                        <!-- Target A -->
                        <td class="py-2.5 px-2.5 border border-slate-300 text-center font-bold text-slate-800 bg-blue-50/20 whitespace-nowrap">
                            <span class="{{ $r['target_a'] > 0 ? 'text-blue-700 font-black' : 'text-slate-400' }}">{{ $r['target_a'] }}</span>
                        </td>

                        <!-- Target B -->
                        <td class="py-2.5 px-2.5 border border-slate-300 text-center font-bold text-slate-800 bg-amber-50/20 whitespace-nowrap">
                            <span class="{{ $r['target_b'] > 0 ? 'text-amber-700 font-black' : 'text-slate-400' }}">{{ $r['target_b'] }}</span>
                        </td>

                        <!-- Target C -->
                        <td class="py-2.5 px-2.5 border border-slate-300 text-center font-bold text-slate-800 bg-orange-50/20 whitespace-nowrap">
                            <span class="{{ $r['target_c'] > 0 ? 'text-orange-700 font-black' : 'text-slate-400' }}">{{ $r['target_c'] }}</span>
                        </td>

                        <!-- Target K -->
                        <td class="py-2.5 px-2.5 border border-slate-300 text-center font-bold text-slate-800 bg-red-50/20 whitespace-nowrap">
                            <span class="{{ $r['target_k'] > 0 ? 'text-red-700 font-black' : 'text-slate-400' }}">{{ $r['target_k'] }}</span>
                        </td>

                        <!-- Status Evaluasi -->
                        <td class="py-2.5 px-3 border border-slate-300 text-center whitespace-nowrap">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-none text-[10px] font-bold border {{ $r['status']['bg'] }}">
                                {{ $r['status']['label'] }}
                            </span>
                        </td>

                        <!-- Aksi Cepat -->
                        <td class="py-2.5 px-2.5 border border-slate-300 text-center whitespace-nowrap">
                            @if($r['total_karyawan'] > 0)
                            <a href="{{ route('admin.karyawan', ['departemen' => $r['filter_dept']]) }}" 
                               class="px-2 py-1 bg-[#92302e] hover:bg-[#7e2524] text-white text-[11px] font-bold transition shadow-2xs inline-flex items-center justify-center cursor-pointer"
                               title="Lihat Karyawan {{ $r['divisi'] }}">
                                <span>Lihat</span>
                            </a>
                            @else
                            <span class="text-slate-300">-</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="15" class="py-6 px-3 border border-slate-300 text-center text-slate-400 font-medium">
                            Belum ada data divisi / departemen terdaftar pada data karyawan.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="bg-slate-100 font-bold text-slate-900 border-t-2 border-slate-300 text-xs">
                        <td colspan="4" class="py-2.5 px-3 border border-slate-300 uppercase tracking-wider text-[11px]">
                            Total Seluruh Divisi
                        </td>
                        <td class="py-2.5 px-3 border border-slate-300 text-center text-slate-900 font-black">
                            {{ $adminStats['total_karyawan'] }}
                        </td>
                        <td class="py-2.5 px-2.5 border border-slate-300 text-center text-emerald-800 font-black">
                            {{ $adminStats['total_dinilai'] }}
                        </td>
                        <td class="py-2.5 px-2.5 border border-slate-300 text-center text-amber-800 font-black">
                            {{ $adminStats['total_belum'] }}
                        </td>
                        <td class="py-2.5 px-3 border border-slate-300 text-right font-black text-slate-900">
                            {{ $adminStats['pct_dinilai'] }}% Selesai
                        </td>
                        <!-- Footer Target S, A, B, C, K -->
                        <td class="py-2.5 px-2.5 border border-slate-300 text-center text-emerald-800 font-black bg-emerald-50/30">
                            {{ $totalTargetCompany['S'] ?? 0 }}
                        </td>
                        <td class="py-2.5 px-2.5 border border-slate-300 text-center text-blue-800 font-black bg-blue-50/30">
                            {{ $totalTargetCompany['A'] ?? 0 }}
                        </td>
                        <td class="py-2.5 px-2.5 border border-slate-300 text-center text-amber-800 font-black bg-amber-50/30">
                            {{ $totalTargetCompany['B'] ?? 0 }}
                        </td>
                        <td class="py-2.5 px-2.5 border border-slate-300 text-center text-orange-800 font-black bg-orange-50/30">
                            {{ $totalTargetCompany['C'] ?? 0 }}
                        </td>
                        <td class="py-2.5 px-2.5 border border-slate-300 text-center text-red-800 font-black bg-red-50/30">
                            {{ $totalTargetCompany['K'] ?? 0 }}
                        </td>
                        <td colspan="2" class="py-2.5 px-3 border border-slate-300 text-center text-slate-400">
                            -
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="p-3 border-t border-slate-200 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs text-slate-500">
            <span>Total {{ count($ringkasanDivisi) }} Unit &amp; Divisi Terdata Sesuai Hierarki Master Karyawan</span>
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.targetKuota') }}" class="font-bold text-[#92302e] hover:underline">
                    Aturan Kuota &rarr;
                </a>
                <span class="text-slate-300">&bull;</span>
                <a href="{{ $user->isSuperadmin() ? route('laporan.index') : route('admin.historyPenilaian') }}" class="font-bold text-emerald-700 hover:underline">
                    {{ $user->isSuperadmin() ? 'Buka Laporan Lengkap →' : 'Buka History Penilaian →' }}
                </a>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- CARD: TOTAL PENILAIAN PERUSAHAAN (Distribusi Predikat Grade S, A, B, C, K) -->
    <!-- ========================================================================= -->
    <div class="bg-white rounded-none border border-slate-300 shadow-sm p-5 space-y-4">
        
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-slate-200 gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-none bg-[#92302e]"></span>
                    <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider">
                        Total Penilaian (Seluruh Perusahaan)
                    </h3>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">
                    Target <strong>{{ $adminStats['total_karyawan'] }}</strong> Karyawan &bull; <strong>{{ $adminStats['total_dinilai'] }}</strong> Selesai Dinilai
                </p>
            </div>

            <div class="flex items-center gap-3">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-none text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                    <span class="w-2 h-2 rounded-none bg-emerald-600"></span>
                    <span>Realisasi {{ $adminStats['pct_dinilai'] }}%</span>
                </span>
                <a href="{{ route('admin.targetKuota') }}" class="text-xs font-bold text-[#92302e] hover:underline">
                    Aturan Kuota &rarr;
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-center">
            
            <!-- Left: Donut Charts & Summary Cards (Col 4) -->
            <div class="lg:col-span-4 bg-slate-50/60 p-4 border border-slate-200 flex flex-col items-center justify-center space-y-4">
                <div class="grid grid-cols-2 gap-2 w-full items-center">
                    <!-- Donut 1: Realisasi Masuk -->
                    <div class="flex flex-col items-center">
                        <div class="h-28 w-28 relative">
                            <canvas id="adminStatusDonutChart"></canvas>
                            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                                <span class="text-sm font-black text-slate-900 leading-none">{{ $adminStats['pct_dinilai'] }}%</span>
                                <span class="text-[7px] text-slate-400 font-bold uppercase">Realisasi</span>
                            </div>
                        </div>
                        <span class="text-[10px] font-bold text-slate-600 mt-1">Status Progres</span>
                    </div>

                    <!-- Donut 2: Sebaran Grade S-K -->
                    <div class="flex flex-col items-center">
                        <div class="h-28 w-28 relative">
                            <canvas id="adminTotalPenilaianChart"></canvas>
                            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                                <span class="text-sm font-black text-slate-900 leading-none">{{ $adminStats['total_dinilai'] }}</span>
                                <span class="text-[7px] text-slate-400 font-bold uppercase">Dinilai</span>
                            </div>
                        </div>
                        <span class="text-[10px] font-bold text-slate-600 mt-1">Sebaran Grade</span>
                    </div>
                </div>

                <div class="w-full space-y-1.5 text-xs">
                    <div class="p-2 bg-emerald-50 border border-emerald-200 flex items-center justify-between">
                        <span class="font-bold text-emerald-900 flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-none bg-emerald-500"></span>
                            <span>Sudah Dinilai</span>
                        </span>
                        <span class="font-black text-emerald-800">{{ $adminStats['total_dinilai'] }} org</span>
                    </div>
                    <div class="p-2 bg-amber-50 border border-amber-200 flex items-center justify-between">
                        <span class="font-bold text-amber-900 flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-none bg-amber-500"></span>
                            <span>Belum Dinilai</span>
                        </span>
                        <span class="font-black text-amber-800">{{ $adminStats['total_belum'] }} org</span>
                    </div>
                    <div class="p-2 bg-white border border-slate-200 flex items-center justify-between text-[11px] text-slate-600">
                        <span>Total Target:</span>
                        <span class="font-black text-slate-900">{{ $adminStats['total_karyawan'] }} Karyawan</span>
                    </div>
                </div>
            </div>

            <!-- Right: Tabel Distribusi Kuota Grade (Col 8) -->
            <div class="lg:col-span-8 overflow-x-auto">
                <span class="text-xs font-bold text-slate-700 uppercase tracking-wider block mb-2">
                    Rincian Distribusi Grade Penilaian (S, A, B, C, K):
                </span>
                <table class="w-full text-left text-xs border-collapse border border-slate-300">
                    <thead>
                        <tr class="bg-slate-100 text-slate-700 font-bold text-[11px] uppercase tracking-wider">
                            <th class="py-2.5 px-3 border border-slate-300 whitespace-nowrap">Grade</th>
                            <th class="py-2.5 px-3 border border-slate-300 whitespace-nowrap">Predikat Evaluasi</th>
                            <th class="py-2.5 px-3 border border-slate-300 text-center whitespace-nowrap">Target Kuota</th>
                            <th class="py-2.5 px-3 border border-slate-300 text-center whitespace-nowrap">Jumlah Realisasi</th>
                            <th class="py-2.5 px-3 border border-slate-300 text-right whitespace-nowrap">Persentase</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <!-- S -->
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="py-2.5 px-3 border border-slate-300 font-bold text-slate-900 whitespace-nowrap flex items-center gap-2">
                                <span class="w-3 h-3 rounded-none bg-[#10b981] flex-shrink-0"></span>
                                <span>S (Sangat Baik)</span>
                            </td>
                            <td class="py-2.5 px-3 border border-slate-300 text-slate-600 whitespace-nowrap">Istimewa / Di Atas Ekspektasi</td>
                            <td class="py-2.5 px-3 border border-slate-300 text-center text-slate-500 whitespace-nowrap">Maks 10% ({{ $totalTargetCompany['S'] ?? 0 }} org)</td>
                            <td class="py-2.5 px-3 border border-slate-300 text-center font-bold text-slate-900 whitespace-nowrap">{{ $totalPenilaianCompany['S'] ?? 0 }} org</td>
                            <td class="py-2.5 px-3 border border-slate-300 text-right font-bold text-slate-800 whitespace-nowrap">
                                {{ $adminStats['total_dinilai'] > 0 ? round((($totalPenilaianCompany['S'] ?? 0) / $adminStats['total_dinilai']) * 100, 1) : 0 }}%
                            </td>
                        </tr>
                        <!-- A -->
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="py-2.5 px-3 border border-slate-300 font-bold text-slate-900 whitespace-nowrap flex items-center gap-2">
                                <span class="w-3 h-3 rounded-none bg-[#2563eb] flex-shrink-0"></span>
                                <span>A (Baik)</span>
                            </td>
                            <td class="py-2.5 px-3 border border-slate-300 text-slate-600 whitespace-nowrap">Memenuhi Semua Ekspektasi</td>
                            <td class="py-2.5 px-3 border border-slate-300 text-center text-slate-500 whitespace-nowrap">Maks 20% ({{ $totalTargetCompany['A'] ?? 0 }} org)</td>
                            <td class="py-2.5 px-3 border border-slate-300 text-center font-bold text-slate-900 whitespace-nowrap">{{ $totalPenilaianCompany['A'] ?? 0 }} org</td>
                            <td class="py-2.5 px-3 border border-slate-300 text-right font-bold text-slate-800 whitespace-nowrap">
                                {{ $adminStats['total_dinilai'] > 0 ? round((($totalPenilaianCompany['A'] ?? 0) / $adminStats['total_dinilai']) * 100, 1) : 0 }}%
                            </td>
                        </tr>
                        <!-- B -->
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="py-2.5 px-3 border border-slate-300 font-bold text-slate-900 whitespace-nowrap flex items-center gap-2">
                                <span class="w-3 h-3 rounded-none bg-[#eab308] flex-shrink-0"></span>
                                <span>B (Cukup)</span>
                            </td>
                            <td class="py-2.5 px-3 border border-slate-300 text-slate-600 whitespace-nowrap">Standar Kinerja Standar</td>
                            <td class="py-2.5 px-3 border border-slate-300 text-center text-slate-500 whitespace-nowrap">Target 50% ({{ $totalTargetCompany['B'] ?? 0 }} org)</td>
                            <td class="py-2.5 px-3 border border-slate-300 text-center font-bold text-slate-900 whitespace-nowrap">{{ $totalPenilaianCompany['B'] ?? 0 }} org</td>
                            <td class="py-2.5 px-3 border border-slate-300 text-right font-bold text-slate-800 whitespace-nowrap">
                                {{ $adminStats['total_dinilai'] > 0 ? round((($totalPenilaianCompany['B'] ?? 0) / $adminStats['total_dinilai']) * 100, 1) : 0 }}%
                            </td>
                        </tr>
                        <!-- C -->
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="py-2.5 px-3 border border-slate-300 font-bold text-slate-900 whitespace-nowrap flex items-center gap-2">
                                <span class="w-3 h-3 rounded-none bg-[#f97316] flex-shrink-0"></span>
                                <span>C (Kurang)</span>
                            </td>
                            <td class="py-2.5 px-3 border border-slate-300 text-slate-600 whitespace-nowrap">Perlu Peningkatan Kinerja</td>
                            <td class="py-2.5 px-3 border border-slate-300 text-center text-slate-500 whitespace-nowrap">Maks 15% ({{ $totalTargetCompany['C'] ?? 0 }} org)</td>
                            <td class="py-2.5 px-3 border border-slate-300 text-center font-bold text-slate-900 whitespace-nowrap">{{ $totalPenilaianCompany['C'] ?? 0 }} org</td>
                            <td class="py-2.5 px-3 border border-slate-300 text-right font-bold text-slate-800 whitespace-nowrap">
                                {{ $adminStats['total_dinilai'] > 0 ? round((($totalPenilaianCompany['C'] ?? 0) / $adminStats['total_dinilai']) * 100, 1) : 0 }}%
                            </td>
                        </tr>
                        <!-- K -->
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="py-2.5 px-3 border border-slate-300 font-bold text-slate-900 whitespace-nowrap flex items-center gap-2">
                                <span class="w-3 h-3 rounded-none bg-[#ef4444] flex-shrink-0"></span>
                                <span>K (Kurang Sekali)</span>
                            </td>
                            <td class="py-2.5 px-3 border border-slate-300 text-slate-600 whitespace-nowrap">Di Bawah Standar Minimum</td>
                            <td class="py-2.5 px-3 border border-slate-300 text-center text-slate-500 whitespace-nowrap">Maks 5% ({{ $totalTargetCompany['K'] ?? 0 }} org)</td>
                            <td class="py-2.5 px-3 border border-slate-300 text-center font-bold text-slate-900 whitespace-nowrap">{{ $totalPenilaianCompany['K'] ?? 0 }} org</td>
                            <td class="py-2.5 px-3 border border-slate-300 text-right font-bold text-slate-800 whitespace-nowrap">
                                {{ $adminStats['total_dinilai'] > 0 ? round((($totalPenilaianCompany['K'] ?? 0) / $adminStats['total_dinilai']) * 100, 1) : 0 }}%
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="bg-slate-100 font-bold text-slate-900 border-t-2 border-slate-300 text-xs">
                            <td colspan="3" class="py-2.5 px-3 border border-slate-300 uppercase tracking-wider text-[11px]">Total Masuk Evaluasi</td>
                            <td class="py-2.5 px-3 border border-slate-300 text-center font-black text-slate-900">{{ $adminStats['total_dinilai'] }} org</td>
                            <td class="py-2.5 px-3 border border-slate-300 text-right font-black text-slate-900">100%</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

        </div>

        <!-- Footer -->
        <div class="pt-3 border-t border-slate-200 flex items-center justify-between text-xs text-slate-500">
            <span>Total target perusahaan: <strong>{{ $adminStats['total_karyawan'] }}</strong> Karyawan</span>
            <a href="{{ route('admin.targetKuota') }}" class="font-bold text-[#92302e] hover:underline">
                Aturan Kuota &rarr;
            </a>
        </div>
    </div>

</div>

<!-- Chart.js Scripts for Admin Dashboard -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Bar Chart: Aktivitas Penilaian (Aktivitas per Divisi)
    const ctxAktivitas = document.getElementById('adminAktivitasChart');
    if (ctxAktivitas) {
        new Chart(ctxAktivitas, {
            type: 'bar',
            data: {
                labels: @json(array_column($aktivitasDivisi, 'kode')),
                datasets: [
                    {
                        label: 'Target',
                        data: @json(array_column($aktivitasDivisi, 'target')),
                        backgroundColor: '#f87171',
                        borderRadius: 0,
                        barPercentage: 0.6,
                        categoryPercentage: 0.6,
                    },
                    {
                        label: 'Sudah Dinilai',
                        data: @json(array_column($aktivitasDivisi, 'dinilai')),
                        backgroundColor: '#60a5fa',
                        borderRadius: 0,
                        barPercentage: 0.6,
                        categoryPercentage: 0.6,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return ' ' + context.dataset.label + ': ' + context.raw + ' orang';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 11, weight: 'bold' }, color: '#475569' }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                        ticks: { font: { size: 10 }, color: '#94a3b8' }
                    }
                }
            }
        });
    }

    // 2. Donut Chart 1: Status Realisasi (Sudah Dinilai vs Belum Dinilai)
    const ctxStatus = document.getElementById('adminStatusDonutChart');
    if (ctxStatus) {
        new Chart(ctxStatus, {
            type: 'doughnut',
            data: {
                labels: ['Sudah Dinilai', 'Belum Dinilai'],
                datasets: [{
                    data: [{{ $adminStats['total_dinilai'] }}, {{ $adminStats['total_belum'] }}],
                    backgroundColor: ['#10b981', '#f59e0b'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }

    // 3. Donut Chart 2: Total Penilaian Grade S, A, B, C, K
    const ctxDonut = document.getElementById('adminTotalPenilaianChart');
    if (ctxDonut) {
        const donutData = [
            {{ $totalPenilaianCompany['S'] ?? 0 }},
            {{ $totalPenilaianCompany['A'] ?? 0 }},
            {{ $totalPenilaianCompany['B'] ?? 0 }},
            {{ $totalPenilaianCompany['C'] ?? 0 }},
            {{ $totalPenilaianCompany['K'] ?? 0 }}
        ];
        const hasData = donutData.reduce((a, b) => a + b, 0) > 0;

        new Chart(ctxDonut, {
            type: 'doughnut',
            data: {
                labels: ['S (Sangat Baik)', 'A (Baik)', 'B (Cukup)', 'C (Kurang)', 'K (Kurang Sekali)'],
                datasets: [{
                    data: hasData ? donutData : [1, 1, 1, 1, 1],
                    backgroundColor: hasData 
                        ? ['#10b981', '#2563eb', '#eab308', '#f97316', '#ef4444']
                        : ['#e2e8f0', '#cbd5e1', '#94a3b8', '#64748b', '#475569'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }
});
</script>
