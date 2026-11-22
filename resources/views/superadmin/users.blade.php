@extends('layouts.app', ['title' => 'Manajemen Akun'])

@section('content')
{{-- SUPER ADMIN & ADMIN: MANAJEMEN AKUN (Persis Mockup Screen 2: Super Admin: Kelola User) --}}
<div class="space-y-6" x-data="{ 
    showAddModal: false, 
    showEditModal: false, 
    showUploadModal: false,
    editUser: { id: '', nama: '', email: '', password: '', role: '', nrp: '', divisi: 'ALL', departemen: 'ALL' },
    newUser: { nama: '', email: '', password: 'password123', role: 'foreman', nrp: '', divisi: 'ALL', departemen: 'ALL' },
    karyawanMap: {{ \Illuminate\Support\Js::from($karyawanLookupMap ?? []) }},
    lookupNrp(nrp) {
        if (!nrp) return null;
        let clean = String(nrp).trim();
        return this.karyawanMap[clean] || this.karyawanMap[clean.toUpperCase()] || null;
    },
    onNrpChange(target) {
        let nrpVal = (target === 'edit' ? this.editUser.nrp : this.newUser.nrp || '').trim();
        if (!nrpVal) return;
        let found = this.lookupNrp(nrpVal);
        if (found) {
            this.applyKaryawanData(target, found);
            return;
        }
        fetch(`/api/user-karyawan-lookup?nrp=${encodeURIComponent(nrpVal)}`)
            .then(res => res.json())
            .then(data => {
                if (data.found && data.karyawan) {
                    this.applyKaryawanData(target, data.karyawan);
                }
            })
            .catch(() => {});
    },
    applyKaryawanData(target, k) {
        if (target === 'edit') {
            if (k.nama) this.editUser.nama = k.nama;
            if (k.division) this.editUser.divisi = k.division;
            if (k.departemen) this.editUser.departemen = k.departemen;
            if (k.role) this.editUser.role = k.role;
            if (k.email) this.editUser.email = k.email;
            if (k.password) this.editUser.password = k.password;
        } else {
            if (k.nama) this.newUser.nama = k.nama;
            if (k.division) this.newUser.divisi = k.division;
            if (k.departemen) this.newUser.departemen = k.departemen;
            if (k.role) this.newUser.role = k.role;
            if (k.email) this.newUser.email = k.email;
            if (k.password) this.newUser.password = k.password;
        }
    }
}">

    <!-- Top Header Card (Musashi Red Banner, Solid & Clean) -->
    <div class="bg-gradient-to-r from-[#962226] via-[#92302e] to-[#781b1f] text-white rounded-none p-5 sm:p-6 border border-[#781b1f] shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight">
                Manajemen Akun <span class="sr-only">Kelola User</span>
            </h1>
            <p class="text-xs sm:text-sm text-red-100 font-medium mt-1">
                Manajemen akun dan hak akses pengguna sistem (Foreman, Section Head, Department Head, Division Head, Admin)
            </p>
        </div>
        <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap shrink-0">
            <!-- Sinkronkan Akun dari Master Karyawan -->
            <form method="POST" action="{{ route('admin.users.sync') }}" class="inline-block m-0">
                @csrf
                <button type="submit" 
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shadow-xs transition cursor-pointer" 
                        title="Sinkronkan akun FOREMAN, SECTION HEAD, DEPT HEAD, DIV HEAD dari Master Karyawan">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span>Sinkronkan Akun</span>
                </button>
            </form>

            <!-- Upload Sheet Master Karyawan -->
            <button @click="showUploadModal = true" type="button" 
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-xs transition cursor-pointer" 
                    title="Upload file Excel Master Karyawan & Otomatis Buat Akun">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                <span>Upload Excel Karyawan</span>
            </button>

            <!-- Tambah User -->
            <button @click="showAddModal = true" type="button" 
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white text-[#92302e] hover:bg-slate-100 text-xs font-bold shadow-xs transition cursor-pointer">
                <svg class="w-4 h-4 text-[#92302e]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span class="sr-only">+ Tambah User</span>
                <span>Tambah User</span>
            </button>
        </div>
    </div>

<!-- Role Filter Metric Cards (Semua, Super Admin, Admin, Foreman, Section Head, Dept. Head, Div. Head, Gol 4 Non Kasie, Staf) - Sharp Cards Grid -->
    @php
        $isSuperadmin = auth()->user()->isSuperadmin();
        $tabs = [
            'semua' => ['label' => 'Semua', 'count' => $counts['semua'] ?? 0, 'sub' => 'Total Akun Sistem', 'code' => 'ALL'],
        ];

        if ($isSuperadmin) {
            $tabs['superadmin'] = ['label' => 'Super Admin', 'count' => $counts['superadmin'] ?? 0, 'sub' => 'Akses Tertinggi', 'code' => 'SPR'];
        }

        $tabs += [
            'admin' => ['label' => 'Admin', 'count' => $counts['admin'] ?? 0, 'sub' => 'Admin Penilaian', 'code' => 'ADM'],
            'foreman' => ['label' => 'Foreman', 'count' => $counts['foreman'] ?? 0, 'sub' => 'Penilai Tingkat 1', 'code' => 'FRM'],
            'section_head' => ['label' => 'Section Head', 'count' => $counts['section_head'] ?? 0, 'sub' => 'Penilai Tingkat 2', 'code' => 'SH'],
            'dept_head' => ['label' => 'Dept. Head', 'count' => $counts['dept_head'] ?? 0, 'sub' => 'Penilai Tingkat 3', 'code' => 'DH'],
            'div_head' => ['label' => 'Div. Head', 'count' => $counts['div_head'] ?? 0, 'sub' => 'Penilai Final Divisi', 'code' => 'DIV'],
            'gol_4_non_kasie' => ['label' => 'Gol 4 Non Kasie', 'count' => $counts['gol_4_non_kasie'] ?? 0, 'sub' => 'KJ 5 Keatas', 'code' => 'G4'],
            'staff' => ['label' => 'Staf / Posisi', 'count' => $counts['staff'] ?? 0, 'sub' => 'Posisi Staf', 'code' => 'STF'],
        ];
        $currentRoute = request()->routeIs('admin.*') ? 'admin.users' : 'superadmin.users';
        $gridCols = $isSuperadmin ? 'grid-cols-2 sm:grid-cols-3 md:grid-cols-5 xl:grid-cols-9' : 'grid-cols-2 sm:grid-cols-4 lg:grid-cols-8';
    @endphp

    <div class="grid {{ $gridCols }} gap-3">
        @foreach($tabs as $key => $tab)
            <a href="{{ route($currentRoute, array_merge(request()->query(), ['role' => $key])) }}" 
               class="p-3.5 border transition block cursor-pointer {{ ($activeRole === $key) ? 'border-[#92302e] bg-[#fbe8e8]/30 ring-1 ring-[#92302e]' : 'bg-white border-slate-300 hover:border-slate-500 shadow-2xs' }}"
               title="Filter pengguna dengan role {{ $tab['label'] }}">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-black uppercase tracking-wider {{ ($activeRole === $key) ? 'text-[#92302e]' : 'text-slate-600' }}">
                        {{ $tab['label'] }}
                    </span>
                    <span class="px-1.5 py-0.2 text-[9px] font-bold {{ ($activeRole === $key) ? 'bg-[#92302e] text-white' : 'bg-slate-100 text-slate-700 border border-slate-300' }}">
                        {{ $tab['code'] }}
                    </span>
                </div>
                <div class="mt-1.5 flex items-baseline gap-1">
                    <span class="text-2xl font-black text-slate-900">{{ $tab['count'] }}</span>
                    <span class="text-xs font-semibold text-slate-500">user</span>
                </div>
                <div class="text-[10px] text-slate-500 mt-1 truncate font-medium">
                    {{ $tab['sub'] }}
                </div>
            </a>
        @endforeach
    </div>

    <!-- Filter Bar: Search Input, Role Filter & Status Filter (Clean Slate Borders Matching Gambar 1) -->
    <form method="GET" action="{{ route($currentRoute) }}" class="bg-white p-4 border border-slate-300 shadow-sm flex flex-col md:flex-row items-center justify-between gap-3 rounded-none overflow-x-auto">
        <div class="relative w-full md:w-96 shrink-0">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </span>
            <input type="text" name="q" value="{{ $search }}" placeholder="Cari nama, email, alias login, atau department..." 
                   onchange="this.form.submit()"
                   class="w-full pl-9 pr-3 py-2 rounded-none border border-slate-300 hover:border-slate-400 text-xs font-semibold text-slate-800 placeholder-slate-400 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] bg-white transition shadow-xs">
        </div>

        <div class="flex flex-nowrap items-center gap-2 w-full md:w-auto shrink-0">
            <!-- Filter Role (Sebelum Filter Semua Status) -->
            <select name="role" onchange="this.form.submit()" class="py-2 px-3 border border-slate-300 hover:border-slate-400 text-xs font-semibold text-slate-700 bg-white rounded-none focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-xs">
                <option value="semua" {{ $activeRole === 'semua' ? 'selected' : '' }}>Semua Role & Posisi</option>
                @if(auth()->user()->isSuperadmin())
                    <option value="superadmin" {{ $activeRole === 'superadmin' ? 'selected' : '' }}>Super Admin</option>
                @endif
                <option value="admin" {{ $activeRole === 'admin' ? 'selected' : '' }}>Admin</option>
                <option value="foreman" {{ $activeRole === 'foreman' ? 'selected' : '' }}>Foreman</option>
                <option value="section_head" {{ $activeRole === 'section_head' ? 'selected' : '' }}>Section Head</option>
                <option value="dept_head" {{ $activeRole === 'dept_head' ? 'selected' : '' }}>Dept. Head</option>
                <option value="div_head" {{ $activeRole === 'div_head' ? 'selected' : '' }}>Div. Head</option>
                <option value="gol_4_non_kasie" {{ $activeRole === 'gol_4_non_kasie' ? 'selected' : '' }}>Gol 4 Non Kasie</option>
                <option value="staff" {{ in_array($activeRole, ['staff', 'staf']) ? 'selected' : '' }}>Staf / Posisi</option>
            </select>

            <!-- Filter Status -->
            <select name="status" onchange="this.form.submit()" class="py-2 px-3 border border-slate-300 hover:border-slate-400 text-xs font-semibold text-slate-700 bg-white rounded-none focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-xs">
                <option value="" {{ $statusFilter === '' ? 'selected' : '' }}>Semua Status</option>
                <option value="aktif" {{ $statusFilter === 'aktif' ? 'selected' : '' }}>Status: Aktif</option>
                <option value="nonaktif" {{ $statusFilter === 'nonaktif' ? 'selected' : '' }}>Status: Nonaktif</option>
            </select>

            <!-- Hidden submit button for Enter key -->
            <button type="submit" class="hidden" tabindex="-1"></button>

            @if($search !== '' || $statusFilter !== '' || $activeRole !== 'semua')
                <a href="{{ route($currentRoute) }}" class="px-3.5 py-2 border border-rose-300 bg-rose-50 text-[#92302e] text-xs font-bold hover:bg-rose-100 rounded-none transition flex items-center gap-1 shadow-2xs whitespace-nowrap shrink-0">
                    <span>✕</span> Reset
                </a>
            @endif
        </div>
    </form>

    <!-- Table of Users matching Mockup Screen 2 (Enriched with Login Alias & Penilai Details) -->
    <div class="bg-white border border-slate-300 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse border border-slate-300 text-xs">
                <thead>
                    <tr class="bg-slate-100 text-slate-700 font-bold text-[11px] uppercase tracking-wider">
                        <th class="py-3 px-3 border border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-12">No</th>
                        <th class="py-3 px-3.5 border border-slate-300 bg-slate-100 font-bold text-slate-700">Nama Lengkap</th>
                        <th class="py-3 px-3.5 border border-slate-300 bg-slate-100 font-bold text-slate-700">Email</th>
                        <th class="py-3 px-3.5 border border-slate-300 bg-slate-100 font-bold text-slate-700">Password</th>
                        <th class="py-3 px-3.5 border border-slate-300 bg-slate-100 font-bold text-slate-700">NRP</th>
                        <th class="py-3 px-3.5 border border-slate-300 bg-slate-100 font-bold text-slate-700">Role / Posisi</th>
                        <th class="py-3 px-3.5 border border-slate-300 bg-slate-100 font-bold text-slate-700">Division</th>
                        <th class="py-3 px-3.5 border border-slate-300 bg-slate-100 font-bold text-slate-700">Department</th>
                        <th class="py-3 px-3.5 border border-slate-300 bg-slate-100 font-bold text-slate-700 text-center">Status</th>
                        <th class="py-3 px-3.5 border border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-28">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($filteredUsers as $u)
                    <tr class="hover:bg-slate-50 transition-colors">
                        
                        <!-- No -->
                        <td class="py-2.5 px-3 border border-slate-300 text-center font-bold text-slate-600 text-xs">
                            {{ $loop->iteration }}
                        </td>

                        <!-- Nama -->
                        <td class="py-2.5 px-3.5 border border-slate-300 font-bold text-slate-900">
                            {{ $u->nama }}
                        </td>

                        <!-- Email -->
                        <td class="py-2.5 px-3.5 border border-slate-300 font-mono text-[11px] text-slate-600">
                            {{ $u->email }}
                        </td>

                        <!-- Password -->
                        <td class="py-2.5 px-3.5 border border-slate-300 font-mono text-slate-700 whitespace-nowrap">
                            <div class="inline-flex items-center gap-1.5" x-data="{ copied: false }">
                                <span class="font-mono text-[11px] font-bold text-slate-800 bg-slate-100 px-2 py-0.5 border border-slate-300 select-all tracking-wide">
                                    {{ $u->password ?: '-' }}
                                </span>
                                @if(!empty($u->password))
                                <button type="button" 
                                        @click="navigator.clipboard.writeText('{{ addslashes($u->password) }}'); copied = true; setTimeout(() => copied = false, 1500)" 
                                        class="p-1 text-slate-400 hover:text-slate-700 cursor-pointer" 
                                        :title="copied ? 'Tersalin!' : 'Salin Password'">
                                    <svg x-show="!copied" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                    <svg x-show="copied" class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </button>
                                @endif
                            </div>
                        </td>

                        <!-- NRP -->
                        <td class="py-2.5 px-3.5 border border-slate-300 font-mono text-slate-700 font-semibold whitespace-nowrap">
                            {{ $u->nrp ?? '-' }}
                        </td>

                        <!-- Role / Posisi -->
                        <td class="py-2.5 px-3.5 border border-slate-300 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-1 text-[11px] font-bold 
                                @if($u->isSuperadmin()) bg-purple-100 text-purple-900 border border-purple-200
                                @elseif($u->isAdmin()) bg-blue-100 text-blue-900 border border-blue-200
                                @elseif($u->isDivisionHead() || in_array($u->role, ['div_head', 'division_head'])) bg-indigo-100 text-indigo-900 border border-indigo-200
                                @elseif($u->isDepartmentHead()) bg-amber-100 text-amber-900 border border-amber-200
                                @elseif($u->isSectionHead()) bg-teal-100 text-teal-900 border border-teal-200
                                @elseif($u->isForeman()) bg-emerald-100 text-emerald-900 border border-emerald-200
                                @elseif($u->isGol4NonKasie()) bg-sky-100 text-sky-900 border border-sky-300
                                @elseif($u->isStaff()) bg-cyan-100 text-cyan-900 border border-cyan-300
                                @elseif($u->isOperator()) bg-slate-100 text-slate-800 border border-slate-300
                                @else bg-slate-100 text-slate-800 border border-slate-200
                                @endif">
                                {{ $u->roleLabel() }}
                            </span>
                        </td>

                        <!-- Division -->
                        <td class="py-2.5 px-3.5 border border-slate-300 text-slate-700 font-medium whitespace-nowrap">
                            <span class="px-2 py-0.5 text-[11px] font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                {{ $u->getDivisionName() }}
                            </span>
                        </td>

                        <!-- Department -->
                        <td class="py-2.5 px-3.5 border border-slate-300 text-slate-700 font-medium whitespace-nowrap">
                            <span class="px-2 py-0.5 text-[11px] font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                {{ $u->getDepartmentName() }}
                            </span>
                        </td>

                        <!-- Status Badge (Aktif / Nonaktif) -->
                        <td class="py-2.5 px-3.5 border border-slate-300 text-center whitespace-nowrap">
                            @if($u->aktif)
                                <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-bold bg-[#fbe8e8] text-[#92302e] border border-rose-200">
                                    Aktif
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-bold bg-[#f8d7da] text-[#842029] border border-rose-300">
                                    Nonaktif
                                </span>
                            @endif
                        </td>

                        <!-- Aksi Buttons (Edit, Reset Password, Nonaktifkan) - Compact Icon Buttons -->
                        <td class="py-2.5 px-3 border border-slate-300 text-center whitespace-nowrap">
                            @if($u->isSuperadmin() && !auth()->user()->isSuperadmin())
                                <span class="px-2 py-0.5 text-[10px] font-bold bg-purple-50 text-purple-700 border border-purple-200">
                                    Terproteksi
                                </span>
                            @else
                            <div class="inline-flex items-center justify-center gap-1.5">
                                <!-- 1. Edit User (Pencil Icon) -->
                                <button type="button" 
                                        @click="
                                            editUser.id = '{{ $u->id }}';
                                            editUser.nama = '{{ addslashes($u->nama) }}';
                                            editUser.email = '{{ addslashes($u->email) }}';
                                            editUser.password = '{{ addslashes($u->password ?? '') }}';
                                            editUser.role = '{{ $u->role }}';
                                            editUser.nrp = '{{ addslashes($u->nrp ?? '') }}';
                                            editUser.divisi = '{{ addslashes($u->getDivisionName()) }}';
                                            editUser.departemen = '{{ addslashes($u->getDepartmentName()) }}';
                                            showEditModal = true;
                                        "
                                        class="p-1.5 border border-slate-300 bg-white hover:bg-blue-50 hover:border-blue-400 text-slate-600 hover:text-blue-600 transition shadow-2xs cursor-pointer"
                                        title="Edit Data User">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                    <span class="sr-only">Edit</span>
                                </button>

                                <!-- 2. Reset Password (Key Icon) -->
                                <form method="POST" action="{{ route('superadmin.users.resetPassword', $u->id) }}" onsubmit="return confirm('Reset password user {{ $u->nama }} ke password default (password123)?');" class="inline-block m-0">
                                    @csrf
                                    <button type="submit" 
                                            class="p-1.5 border border-amber-300 bg-amber-50 hover:bg-amber-100 text-amber-700 transition shadow-2xs cursor-pointer"
                                            title="Reset Password ke default (password123)">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                                        </svg>
                                        <span class="sr-only">Reset Password</span>
                                    </button>
                                </form>

                                <!-- 3. Toggle Status (Power/Ban Icon) -->
                                @if(!$u->isSuperadmin() || $u->id !== auth()->id())
                                <form method="POST" action="{{ route('superadmin.users.toggleStatus', $u->id) }}" class="inline-block m-0">
                                    @csrf
                                    @if($u->aktif)
                                    <button type="submit" 
                                            class="p-1.5 border border-rose-300 bg-rose-50 hover:bg-rose-100 text-rose-600 transition shadow-2xs cursor-pointer"
                                            title="Nonaktifkan Akun">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                        </svg>
                                        <span class="sr-only">Nonaktifkan</span>
                                    </button>
                                    @else
                                    <button type="submit" 
                                            class="p-1.5 border border-emerald-300 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 transition shadow-2xs cursor-pointer"
                                            title="Aktifkan Akun">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        <span class="sr-only">Aktifkan</span>
                                    </button>
                                    @endif
                                </form>
                                @endif

                                <!-- 4. Hapus User (Trash Icon) -->
                                @if((!$u->isSuperadmin() || auth()->user()->isSuperadmin()) && $u->id !== auth()->id())
                                <form method="POST" action="{{ route('superadmin.users.delete', $u->id) }}" onsubmit="return confirm('Apakah Anda yakin ingin menghapus akun {{ $u->nama }} ({{ $u->nrp }}) secara permanen?');" class="inline-block m-0">
                                    @csrf
                                    <button type="submit" 
                                            class="p-1.5 border border-slate-300 bg-slate-50 hover:bg-rose-50 hover:border-rose-400 hover:text-rose-600 text-slate-500 transition shadow-2xs cursor-pointer"
                                            title="Hapus Akun Permanen">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                        <span class="sr-only">Hapus</span>
                                    </button>
                                </form>
                                @endif
                            </div>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="py-8 text-center text-slate-400 border border-slate-300">
                            Tidak ditemukan user dengan kriteria filter tersebut.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Table Footer / Pagination Info -->
        <div class="px-4 py-3 bg-slate-50 border-t border-slate-200/90 flex flex-col sm:flex-row items-center justify-between text-xs text-slate-500 gap-2">
            <span>Menampilkan {{ $filteredUsers->count() }} akun dari total {{ $counts['semua'] ?? 0 }} user terdaftar</span>
            <span class="font-medium">Total Akun Terdaftar: {{ $counts['semua'] ?? 0 }} User Akses</span>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- MODAL: TAMBAH USER                                            -->
    <!-- ============================================================== -->
    <div x-show="showAddModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showAddModal = false"></div>
        <div class="min-h-full flex items-center justify-center p-4">
            <div class="relative w-full max-w-lg bg-white shadow-2xl p-6 border border-slate-300 z-10" @click.stop>
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-base font-bold text-slate-900">Tambah User Baru</h3>
                    <button @click="showAddModal = false" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                </div>

                <form method="POST" action="{{ route('superadmin.users.store') }}" class="space-y-4 mt-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Nama Lengkap</label>
                        <input type="text" name="nama" x-model="newUser.nama" required placeholder="Contoh: Danu Kurniawan" 
                               class="w-full px-3 py-2 border border-slate-300 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#92302e]">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">NRP</label>
                            <input type="text" name="nrp" x-model="newUser.nrp" 
                                   @input.debounce.300ms="onNrpChange('add')"
                                   @change="onNrpChange('add')"
                                   placeholder="Contoh: 1045" 
                                   class="w-full px-3 py-2 border border-slate-300 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#92302e]">
                            <span class="text-[10px] text-slate-400 mt-0.5 block leading-tight">Ketik NRP untuk memanggil data otomatis</span>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Role Akun / Posisi</label>
                            <select name="role" x-model="newUser.role" required class="w-full px-3 py-2 border border-slate-300 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#92302e] bg-white">
                                @foreach($roleGroups as $groupLabel => $roles)
                                    <optgroup label="{{ $groupLabel }}">
                                        @foreach($roles as $val => $lbl)
                                            <option value="{{ $val }}">{{ $lbl }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Email</label>
                        <input type="email" name="email" x-model="newUser.email" required placeholder="nama@musashi.co.id" 
                               class="w-full px-3 py-2 border border-slate-300 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#92302e]">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Password Awal</label>
                        <input type="text" name="password" x-model="newUser.password" required 
                               class="w-full px-3 py-2 border border-slate-300 text-xs font-mono font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#92302e]">
                        <span class="text-[10px] text-slate-400 mt-0.5 block leading-tight">Otomatis diisi NRP@Musashi{YY} saat NRP dimasukkan</span>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Divisi (Division)</label>
                            <select name="divisi" x-model="newUser.divisi" class="w-full px-3 py-2 border border-slate-300 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#92302e] bg-white">
                                @foreach($divisiOptions as $dOpt)
                                    <option value="{{ $dOpt }}">{{ $dOpt }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Departemen (Department)</label>
                            <select name="departemen" x-model="newUser.departemen" class="w-full px-3 py-2 border border-slate-300 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#92302e] bg-white">
                                @foreach($departemenOptions as $opt)
                                    <option value="{{ $opt }}">{{ $opt }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                        <button @click="showAddModal = false" type="button" class="px-4 py-2 border border-slate-300 bg-slate-100 text-slate-700 text-xs font-bold hover:bg-slate-200">
                            Batal
                        </button>
                        <button type="submit" class="px-4 py-2 bg-[#92302e] hover:bg-[#7e2524] text-white text-xs font-bold shadow-sm">
                            Simpan User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- MODAL: EDIT USER                                              -->
    <!-- ============================================================== -->
    <div x-show="showEditModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showEditModal = false"></div>
        <div class="min-h-full flex items-center justify-center p-4">
            <div class="relative w-full max-w-lg bg-white shadow-2xl p-6 border border-slate-300 z-10" @click.stop>
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-base font-bold text-slate-900">Edit Data User</h3>
                    <button @click="showEditModal = false" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                </div>

                <form :action="(window.location.pathname.startsWith('/admin') ? '/admin/manajemen-akun/' : '/superadmin/users/') + editUser.id + '/update'" method="POST" class="space-y-4 mt-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Nama Lengkap</label>
                        <input type="text" name="nama" x-model="editUser.nama" required 
                               class="w-full px-3 py-2 border border-slate-300 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#92302e]">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">NRP</label>
                            <input type="text" name="nrp" x-model="editUser.nrp" 
                                   @input.debounce.300ms="onNrpChange('edit')"
                                   @change="onNrpChange('edit')"
                                   class="w-full px-3 py-2 border border-slate-300 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#92302e]">
                            <span class="text-[10px] text-slate-400 mt-0.5 block leading-tight">Ubah NRP untuk memanggil data otomatis</span>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Role Akun / Posisi</label>
                            <select name="role" x-model="editUser.role" required class="w-full px-3 py-2 border border-slate-300 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#92302e] bg-white">
                                @foreach($roleGroups as $groupLabel => $roles)
                                    <optgroup label="{{ $groupLabel }}">
                                        @foreach($roles as $val => $lbl)
                                            <option value="{{ $val }}">{{ $lbl }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Email</label>
                        <input type="email" name="email" x-model="editUser.email" required 
                               class="w-full px-3 py-2 border border-slate-300 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#92302e]">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Password</label>
                        <div class="relative" x-data="{ showEditPw: true }">
                            <input :type="showEditPw ? 'text' : 'password'" name="password" x-model="editUser.password" required 
                                   class="w-full px-3 py-2 pr-8 border border-slate-300 text-xs font-mono font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#92302e]">
                            <button type="button" @click="showEditPw = !showEditPw" class="absolute inset-y-0 right-0 pr-2 flex items-center text-slate-400 hover:text-slate-700 cursor-pointer" title="Lihat/Sembunyikan Password">
                                <svg x-show="!showEditPw" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <svg x-show="showEditPw" class="w-4 h-4 text-[#92302e]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                            </button>
                        </div>
                        <span class="text-[10px] text-slate-500 mt-0.5 block leading-tight">Password saat ini. Ubah di sini untuk mengganti password.</span>
                    </div>

                    <!-- Pisahkan Divisi dan Departemen -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Divisi (Division)</label>
                            <select name="divisi" x-model="editUser.divisi" class="w-full px-3 py-2 border border-slate-300 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#92302e] bg-white">
                                @foreach($divisiOptions as $dOpt)
                                    <option value="{{ $dOpt }}">{{ $dOpt }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Departemen (Department)</label>
                            <select name="departemen" x-model="editUser.departemen" class="w-full px-3 py-2 border border-slate-300 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#92302e] bg-white">
                                @foreach($departemenOptions as $opt)
                                    <option value="{{ $opt }}">{{ $opt }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                        <button @click="showEditModal = false" type="button" class="px-4 py-2 border border-slate-300 bg-slate-100 text-slate-700 text-xs font-bold hover:bg-slate-200">
                            Batal
                        </button>
                        <button type="submit" class="px-4 py-2 bg-[#92302e] hover:bg-[#7e2524] text-white text-xs font-bold shadow-sm">
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL: UPLOAD SHEET MASTER KARYAWAN & AUTO-BUAT AKUN -->
    <div x-show="showUploadModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div @click.away="showUploadModal = false" class="bg-white border border-slate-300 shadow-2xl w-full max-w-lg overflow-hidden animate-in fade-in zoom-in-95 duration-150">
            <div class="p-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-sm text-slate-900">Upload Excel Master Karyawan &amp; Buat Akun Otomatis</h3>
                    <p class="text-[11px] text-slate-500 mt-0.5">Otomatis sinkron ke Master Karyawan &amp; Manajemen Akun</p>
                </div>
                <button @click="showUploadModal = false" type="button" class="text-slate-400 hover:text-slate-700 text-xl font-bold cursor-pointer leading-none">&times;</button>
            </div>
            <form action="{{ route('karyawan.import') }}" method="POST" enctype="multipart/form-data" class="p-6 space-y-4" x-data="{ isUploading: false }" @submit="isUploading = true">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">Pilih File Excel / CSV (.XLSX / .CSV)</label>
                    <input type="file" name="sheet_file" accept=".xlsx, .csv, .txt" required
                           class="w-full text-xs text-slate-700 file:mr-3 file:py-2 file:px-3 file:border-0 file:text-xs file:font-bold file:bg-[#92302e] file:text-white hover:file:bg-[#7e2524] file:cursor-pointer border border-slate-300 bg-white p-1">
                </div>

                <div class="flex items-start gap-2 bg-slate-50 border border-slate-200 p-3">
                    <input type="checkbox" name="sync_existing" id="sync_existing_users" value="1" checked class="mt-0.5 accent-[#92302e]">
                    <label for="sync_existing_users" class="text-xs text-slate-700 leading-snug cursor-pointer">
                        <strong class="block text-slate-900">Perbarui data jika NRP sudah ada &amp; sinkronkan akun pimpinan</strong>
                        Otomatis membuat/memperbarui akun pimpinan (FOREMAN, SECTION HEAD, DEPT HEAD, DIV HEAD).
                    </label>
                </div>

                <div class="bg-amber-50/70 border border-amber-200 p-3 text-[11px] text-amber-900 space-y-1.5">
                    <p class="font-bold text-slate-800">Format Kolom Excel yang Didukung:</p>
                    <div class="font-mono text-[10px] text-slate-600 bg-white p-2 border border-amber-100 overflow-x-auto whitespace-nowrap">
                        NRP &bull; NAMA LENGKAP &bull; CODE BAGIAN &bull; NAMA BAGIAN &bull; SECTION &bull; DEPARTMENT &bull; DIVISION &bull; POSITION &bull; START WORK &bull; KJ &bull; GOLONGAN &bull; AKSI
                    </div>
                    <ul class="list-disc pl-4 space-y-0.5 text-[11px] text-slate-600">
                        <li><strong>Akun Otomatis</strong>: Dibuat untuk posisi <code>FOREMAN</code>, <code>SECTION HEAD</code>, <code>DEPT HEAD</code>, dan <code>DIV HEAD</code>.</li>
                        <li><strong>Format Email</strong>: <code>NRPPOSITION@musashi.id</code> (Contoh: <code>112SECTIONHEAD@musashi.id</code>).</li>
                        <li><strong>Format Password Default</strong>: <code>NRP@Musashi{TahunMasuk}</code> (Contoh: <code>9@Musashi96</code>, <code>16353@Musashi23</code>). Diambil 2 digit terakhir tahun masuk karyawan.</li>
                    </ul>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-200">
                    <button @click="showUploadModal = false" :disabled="isUploading" type="button" class="px-3.5 py-2 border border-slate-300 bg-slate-100 hover:bg-slate-200 text-xs font-bold text-slate-700 cursor-pointer transition">
                        Batal
                    </button>
                    <button type="submit" :disabled="isUploading" class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#92302e] hover:bg-[#7e2524] disabled:opacity-60 text-white text-xs font-bold shadow-sm cursor-pointer transition">
                        <svg x-show="isUploading" class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <svg x-show="!isUploading" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        <span x-show="!isUploading">Import &amp; Sinkronkan Akun</span>
                        <span x-show="isUploading">Mengimpor &amp; Menyinkronkan...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
