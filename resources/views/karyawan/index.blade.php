@extends('layouts.app', ['title' => 'Data Karyawan Bawahan'])

@section('content')
<div class="space-y-6" x-data="{ 
    addModal: false, 
    uploadModal: false,
    showEditModal: false,
    newNrp: '',
    existingNrps: @json($existingNrpsMap ?? []),
    get isNrpDuplicate() {
        const clean = (this.newNrp || '').trim().toLowerCase();
        return clean !== '' && Boolean(this.existingNrps && this.existingNrps[clean]);
    },
    get duplicateEmployeeName() {
        const clean = (this.newNrp || '').trim().toLowerCase();
        return (this.existingNrps && this.existingNrps[clean]) ? this.existingNrps[clean] : '';
    },
    editKaryawan: {
        nrp: '',
        nama_lengkap: '',
        jabatan: '',
        tgl_masuk: '',
        golongan: 'GOLONGAN II',
        sub_gol: 'IIB',
        kj: 'KJ 2',
        departemen: 'PROD 2',
        seksi: '',
        code_bagian: '',
        nama_bagian: '',
        nama_sect: ''
    }
}">


    <!-- Header Section Card (Merah Musashi & Rounded-None) -->
    <div class="bg-gradient-to-r from-[#962226] via-[#92302e] to-[#781b1f] text-white p-5 sm:p-6 border border-[#781b1f] shadow-xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 rounded-none">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight">
                Data Karyawan
            </h1>
            <span class="sr-only">Data Karyawan Bawahan</span>
            <p class="text-xs text-red-100 font-medium mt-1">
                Daftar karyawan yang dievaluasi kinerjanya
            </p>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-wrap items-center gap-2">
            @if(!$user->isForeman() && ($user->isAdmin() || $user->isHr() || $user->isSuperadmin()))
            <!-- + Tambah Karyawan -->
            <button @click="newNrp = ''; addModal = true" type="button"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-none bg-white hover:bg-slate-100 text-[#92302e] text-xs font-bold shadow-sm transition cursor-pointer border border-white">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                <span>Tambah Karyawan</span>
            </button>

            <!-- Upload Sheet -->
            <button @click="uploadModal = true" type="button"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-none bg-white/15 hover:bg-white/25 text-white border border-white/30 text-xs font-bold shadow-sm transition cursor-pointer">
                <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                </svg>
                <span>Upload Sheet</span>
            </button>
            @endif

            <!-- Refresh & Sinkron Sheet -->
            <a href="{{ route('karyawan.index', array_merge(request()->query(), ['sync' => 1])) }}"
               class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-none bg-emerald-600 hover:bg-emerald-700 text-white border border-emerald-500 text-xs font-bold shadow-sm transition cursor-pointer"
               title="Tarik data terbaru dari Google Spreadsheet & selaraskan karyawan yang dihapus/ditambah">
                <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                <span>Sinkron Sheet</span>
            </a>

            <!-- Export -->
            <a href="{{ route('karyawan.export', request()->query()) }}"
               class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-none bg-white/15 hover:bg-white/25 text-white border border-white/30 text-xs font-bold shadow-sm transition">
                <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                </svg>
                <span>Export</span>
            </a>
        </div>
    </div>

    <!-- 5 Metric Cards: Total Karyawan, Total BAGIAN, Total SECTION, TOTAL KJ / GOLONGAN, Total POSITION -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        <!-- 1. Total Karyawan -->
        <a href="{{ route('karyawan.index') }}" 
           class="bg-white p-3.5 border border-slate-300 shadow-xs hover:border-slate-400 transition group cursor-pointer"
           title="Tampilkan seluruh data karyawan">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-700 block">Total Karyawan</span>
            <div class="mt-1.5 flex items-baseline gap-1.5">
                <span class="text-2xl font-black text-slate-900">{{ $hierarchySummary['total_karyawan'] ?? count($karyawanRows) }}</span>
                <span class="text-xs font-semibold text-slate-500">karyawan</span>
            </div>
        </a>

        <!-- 2. Total BAGIAN -->
        <div class="bg-white p-3.5 border border-slate-300 shadow-xs transition" title="Jumlah total bagian kerja">
            <span class="text-[10px] font-black uppercase tracking-wider text-blue-700 block">Total BAGIAN</span>
            <div class="mt-1.5 flex items-baseline gap-1.5">
                <span class="text-2xl font-black text-slate-900">{{ $hierarchySummary['total_bagian'] ?? 1 }}</span>
                <span class="text-xs font-semibold text-blue-600">bagian</span>
            </div>
        </div>

        <!-- 3. Total SECTION -->
        <div class="bg-white p-3.5 border border-slate-300 shadow-xs transition" title="Jumlah total section organisasi">
            <span class="text-[10px] font-black uppercase tracking-wider text-teal-700 block">Total SECTION</span>
            <div class="mt-1.5 flex items-baseline gap-1.5">
                <span class="text-2xl font-black text-slate-900">{{ $hierarchySummary['total_sect'] ?? 1 }}</span>
                <span class="text-xs font-semibold text-teal-600">section</span>
            </div>
        </div>

        <!-- 4. TOTAL KJ / GOLONGAN -->
        <div class="bg-white p-3.5 border border-slate-300 shadow-xs transition" title="Jumlah jenjang KJ / Golongan ({{ $hierarchySummary['kj_list_label'] ?? 'aktif' }})">
            <span class="text-[10px] font-black uppercase tracking-wider text-amber-700 block">TOTAL KJ / GOLONGAN</span>
            <div class="mt-1.5 flex items-baseline gap-1.5">
                <span class="text-2xl font-black text-slate-900">{{ $hierarchySummary['total_kj'] ?? 1 }}</span>
                <span class="text-xs font-semibold text-amber-600">KJ / Golongan</span>
            </div>
        </div>

        <!-- 5. Total POSITION -->
        <div class="bg-white p-3.5 border border-slate-300 shadow-xs transition" title="Jumlah total position karyawan">
            <span class="text-[10px] font-black uppercase tracking-wider text-purple-700 block">Total POSITION</span>
            <div class="mt-1.5 flex items-baseline gap-1.5">
                <span class="text-2xl font-black text-slate-900">{{ $hierarchySummary['total_jabatan'] ?? 1 }}</span>
                <span class="text-xs font-semibold text-purple-600">position</span>
            </div>
        </div>
    </div>

    <!-- Filter & Search Bar with Clean Slate Borders (Meniru Master Karyawan Admin) -->
    <div class="bg-white border border-slate-300 shadow-sm p-4 overflow-x-auto">
        <form method="GET" action="{{ route('karyawan.index') }}" class="flex flex-nowrap items-end gap-3 min-w-max">
            
            <!-- Search Input -->
            <div class="w-64 sm:w-72 shrink-0">
                <label class="block text-[10px] font-bold text-slate-700 uppercase tracking-wider mb-1">Cari Karyawan</label>
                <div class="relative">
                    <input type="text" name="q" value="{{ $search }}" placeholder="Cari nama, NRP, position..."
                           onchange="this.form.submit()"
                           class="w-full text-xs font-semibold text-slate-800 border border-slate-300 hover:border-slate-400 bg-white py-2 pl-8 pr-3 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-xs rounded-none">
                    <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
            </div>

            @php
                $isForemanKaryawan = $user->isForeman() || in_array($user->role, ['foreman', 'ketua']);
                $hideSemuaKaryawan = $isForemanKaryawan || $user->isSectionHead() || $user->isDepartmentHead() || in_array($user->role, ['section_head', 'dept_head', 'department_head']);
            @endphp

            <!-- Filter Department (Dihilangkan untuk Foreman) -->
            @if(!empty($deptList) && count($deptList) > 1 && !$user->isForeman())
            <div class="w-44 shrink-0">
                <label class="block text-[10px] font-bold text-slate-700 uppercase tracking-wider mb-1">Department</label>
                <select name="departemen" onchange="this.form.submit()" class="w-full text-xs font-semibold text-slate-700 border border-slate-300 hover:border-slate-400 bg-white py-2 px-2.5 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-xs rounded-none">
                    <option value="">{{ $hideSemuaKaryawan ? 'Department' : 'Semua Department' }}</option>
                    @foreach($deptList as $d)
                    <option value="{{ $d }}" {{ ($filterDept ?? '') === $d ? 'selected' : '' }}>{{ $d }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            <!-- Filter Bagian (Posisi ditukar sebelum Position) -->
            @if(!empty($namaBagianList))
            <div class="w-44 shrink-0">
                <label class="block text-[10px] font-bold text-slate-700 uppercase tracking-wider mb-1">Bagian</label>
                <select name="nama_bagian" onchange="this.form.submit()" class="w-full text-xs font-semibold text-slate-700 border border-slate-300 hover:border-slate-400 bg-white py-2 px-2.5 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-xs rounded-none">
                    <option value="">{{ $hideSemuaKaryawan ? 'Bagian' : 'Semua Bagian' }}</option>
                    @foreach($namaBagianList as $nb)
                    <option value="{{ $nb }}" {{ ($filterNamaBagian ?? '') === $nb ? 'selected' : '' }}>{{ $nb }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            <!-- Filter Position -->
            @if(!empty($jabatanList))
            <div class="w-40 shrink-0">
                <label class="block text-[10px] font-bold text-slate-700 uppercase tracking-wider mb-1">Position</label>
                <select name="jabatan" onchange="this.form.submit()" class="w-full text-xs font-semibold text-slate-700 border border-slate-300 hover:border-slate-400 bg-white py-2 px-2.5 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-xs rounded-none">
                    <option value="">{{ $hideSemuaKaryawan ? 'Position' : 'Semua Position' }}</option>
                    @foreach($jabatanList as $j)
                    <option value="{{ $j }}" {{ ($filterJabatan ?? '') === $j ? 'selected' : '' }}>{{ $j }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            <!-- Filter KJ / Golongan -->
            <div class="w-36 shrink-0">
                <label class="block text-[10px] font-bold text-slate-700 uppercase tracking-wider mb-1">KJ / Golongan</label>
                <select name="golongan" onchange="this.form.submit()" class="w-full text-xs font-semibold text-slate-700 border border-slate-300 hover:border-slate-400 bg-white py-2 px-2.5 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-xs rounded-none">
                    <option value="">{{ $hideSemuaKaryawan ? 'KJ / Golongan' : 'Semua KJ / Golongan' }}</option>
                    @foreach($golList as $g)
                    <option value="{{ $g }}" {{ ($filterGol ?? '') === $g ? 'selected' : '' }}>{{ $g }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Hidden submit for Enter key in search input -->
            <button type="submit" class="hidden" tabindex="-1"></button>

            <!-- Reset Button -->
            @if($search || (!$user->isForeman() && !empty($filterDept)) || !empty($filterGol) || !empty($filterJabatan) || !empty($filterNamaBagian))
            <div class="flex items-center shrink-0">
                <a href="{{ route('karyawan.index') }}"
                   class="px-3.5 py-2 border border-rose-300 bg-rose-50 text-[#92302e] text-xs font-bold hover:bg-rose-100 transition rounded-none flex items-center gap-1 shadow-2xs whitespace-nowrap">
                    <span>✕</span> Reset
                </a>
            </div>
            @endif
        </form>
    </div>

    <!-- Table Card: Clean, Solid & Flush (100% Persis Master Karyawan Admin) -->
    <div class="bg-white border border-slate-300 shadow-sm overflow-hidden rounded-none">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-separate border-spacing-0 text-xs">
                <thead>
                    @php
                        $useExcelCols = !empty($isSectionHeadWithoutForeman) || $user->isForeman();
                    @endphp
                    <tr class="bg-slate-100 text-slate-700 font-bold text-[11px] uppercase tracking-wider sticky top-0 z-10 shadow-2xs">
                        <th class="py-3 px-2 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-12 min-w-[48px] max-w-[48px] whitespace-nowrap sticky left-0 z-30">NO</th>
                        @if($useExcelCols)
                            <th class="py-3 px-2.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-28 min-w-[100px] max-w-[100px] whitespace-nowrap sticky left-[48px] z-30">NRP</th>
                            <th class="py-3 px-3.5 border-b border-r-2 border-r-slate-500 border-slate-300 bg-slate-100 font-bold text-slate-700 w-52 min-w-[200px] max-w-[200px] whitespace-nowrap sticky left-[148px] z-30 shadow-[4px_0_8px_-2px_rgba(0,0,0,0.15)]">NAMA LENGKAP</th>
                            <th class="py-3 px-2.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-28 whitespace-nowrap">CODE BAGIAN</th>
                            <th class="py-3 px-3 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 min-w-[160px] whitespace-nowrap">NAMA BAGIAN</th>
                            <th class="py-3 px-2.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-28 whitespace-nowrap">LINE CATEGORY</th>
                            <th class="py-3 px-3 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 min-w-[150px] whitespace-nowrap">POSITION</th>
                            <th class="py-3 px-2.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-36 whitespace-nowrap">DIVISION</th>
                            <th class="py-3 px-2.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-24 whitespace-nowrap">DEPARTMENT</th>
                            <th class="py-3 px-2.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-28 whitespace-nowrap">SECTION</th>
                            <th class="py-3 px-2 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-20 whitespace-nowrap">KJ</th>
                            <th class="py-3 px-2 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-24 whitespace-nowrap">GOLONGAN</th>
                            <th class="py-3 px-2 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-28 whitespace-nowrap">START WORK</th>
                        @else
                            <th class="py-3 px-2.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-28 min-w-[100px] max-w-[100px] whitespace-nowrap sticky left-[48px] z-30">NRP</th>
                            <th class="py-3 px-3.5 border-b border-r-2 border-r-slate-500 border-slate-300 bg-slate-100 font-bold text-slate-700 w-52 min-w-[200px] max-w-[200px] whitespace-nowrap sticky left-[148px] z-30 shadow-[4px_0_8px_-2px_rgba(0,0,0,0.15)]">NAMA LENGKAP</th>
                            <th class="py-3 px-2.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-28 whitespace-nowrap">CODE BAGIAN</th>
                            <th class="py-3 px-3 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 min-w-[120px]">NAMA BAGIAN</th>
                            <th class="py-3 px-2.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-28 whitespace-nowrap">SECTION</th>
                            <th class="py-3 px-2.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-24 whitespace-nowrap">DEPARTMENT</th>
                            <th class="py-3 px-2.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-36 whitespace-nowrap">DIVISION</th>
                            <th class="py-3 px-3 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 min-w-[130px]">POSITION</th>
                            <th class="py-3 px-2 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-24 whitespace-nowrap">START WORK</th>
                            <th class="py-3 px-2 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-12 whitespace-nowrap">KJ</th>
                            <th class="py-3 px-2 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-16 whitespace-nowrap">GOLONGAN</th>
                        @endif
                        <th class="py-3 px-2.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-28 min-w-[100px] whitespace-nowrap">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($karyawanRows as $k)
                    <tr class="hover:bg-slate-50 transition-colors group">
                        
                        <!-- NO (Sticky Left) -->
                        <td class="py-2.5 px-2 border-b border-r border-slate-300 text-center font-bold text-slate-800 bg-white group-hover:bg-slate-100 whitespace-nowrap w-12 min-w-[48px] max-w-[48px] sticky left-0 z-20">
                            {{ $loop->iteration }}
                        </td>

                        <!-- NRP (Sticky Left) -->
                        <td class="py-2.5 px-2.5 border-b border-r border-slate-300 text-center font-mono font-normal text-slate-800 bg-white group-hover:bg-slate-100 whitespace-nowrap w-28 min-w-[100px] max-w-[100px] sticky left-[48px] z-20">
                            {{ $k['nrp'] }}
                        </td>

                        <!-- NAMA (Sticky Left, Divider Tebal) -->
                        <td class="py-2.5 px-3.5 border-b border-r-2 border-r-slate-500 border-slate-300 font-normal text-slate-800 bg-white group-hover:bg-slate-100 whitespace-nowrap w-52 min-w-[200px] max-w-[200px] sticky left-[148px] z-20 shadow-[4px_0_8px_-2px_rgba(0,0,0,0.15)]">
                            <div class="inline-flex items-center gap-1.5 whitespace-nowrap">
                                <span class="font-bold text-slate-900 whitespace-nowrap">{{ $k['nama_lengkap'] }}</span>
                                @php
                                    $isSelf = (strcasecmp($k['nrp'] ?? '', $user->nrp ?? '') === 0 
                                            || strcasecmp(trim($k['nama_lengkap'] ?? ''), trim($user->nama ?? '')) === 0
                                            || (!empty($k['nama_lengkap']) && !empty($user->nama) && (stripos($user->nama, $k['nama_lengkap']) !== false || stripos($k['nama_lengkap'], $user->nama) !== false)));
                                @endphp
                                @if($isSelf)
                                    <span class="px-1.5 py-0.5 text-[9.5px] font-extrabold bg-[#fbe8e8] text-[#92302e] border border-[#ce5d5b]/40 rounded-none inline-flex items-center shrink-0 whitespace-nowrap" title="Penilaian Mandiri (Self Appraisal) - Anda menilai diri sendiri">
                                        (Anda)
                                    </span>
                                @endif
                            </div>
                        </td>

                        @if($useExcelCols)
                            <!-- ORGSTRUCTURECODE -->
                            <td class="py-2.5 px-2.5 border-b border-r border-slate-300 font-mono text-slate-700 text-center whitespace-nowrap">
                                {{ $k['code_bagian'] }}
                            </td>

                            <!-- ORGSTRUCTURENAME -->
                            <td class="py-2.5 px-3 border-b border-r border-slate-300 text-slate-800 font-medium leading-snug whitespace-nowrap">
                                {{ $k['nama_bagian'] }}
                            </td>

                            <!-- LINE CATEGORY -->
                            <td class="py-2.5 px-2.5 border-b border-r border-slate-300 text-slate-700 font-medium text-center whitespace-nowrap">
                                {{ $k['line_category'] ?? '-' }}
                            </td>

                            <!-- POSITION -->
                            <td class="py-2.5 px-3 border-b border-r border-slate-300 text-slate-800 font-medium uppercase leading-snug whitespace-nowrap">
                                {{ $k['jabatan'] }}
                            </td>

                            <!-- DIVISION -->
                            <td class="py-2.5 px-2.5 border-b border-r border-slate-300 text-slate-700 font-medium text-center whitespace-nowrap">
                                {{ $k['division'] ?? 'PRODUCTION A DIV' }}
                            </td>

                            <!-- DEPT -->
                            <td class="py-2.5 px-2.5 border-b border-r border-slate-300 text-slate-700 font-medium text-center whitespace-nowrap">
                                {{ $k['departemen'] }}
                            </td>

                            <!-- SECTION -->
                            <td class="py-2.5 px-2.5 border-b border-r border-slate-300 text-center">
                                <span class="px-2 py-0.5 text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200 inline-block whitespace-nowrap">
                                    {{ $k['nama_sect'] }}
                                </span>
                            </td>

                            <!-- KJ -->
                            <td class="py-2.5 px-2 border-b border-r border-slate-300 text-center font-bold text-slate-800 whitespace-nowrap">
                                {{ $k['kj'] }}
                            </td>

                            <!-- GOLONGAN -->
                            <td class="py-2.5 px-2 border-b border-r border-slate-300 text-center font-semibold text-slate-700 whitespace-nowrap">
                                {{ $k['sub_gol'] }}
                            </td>

                            <!-- JOINDATE -->
                            <td class="py-2.5 px-2 border-b border-r border-slate-300 text-center font-mono text-slate-700 whitespace-nowrap">
                                {{ $k['start_work'] }}
                            </td>
                        @else
                            <!-- CODE BAGIAN -->
                            <td class="py-2.5 px-2.5 border-b border-r border-slate-300 font-mono text-slate-700 text-center whitespace-nowrap">
                                {{ $k['code_bagian'] }}
                            </td>

                            <!-- NAMA BAGIAN -->
                            <td class="py-2.5 px-3 border-b border-r border-slate-300 text-slate-800 font-medium leading-snug">
                                {{ $k['nama_bagian'] }}
                            </td>

                            <!-- NAMA SECT. -->
                            <td class="py-2.5 px-2.5 border-b border-r border-slate-300 text-center">
                                <span class="px-2 py-0.5 text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200 inline-block whitespace-nowrap">
                                    {{ $k['nama_sect'] }}
                                </span>
                            </td>

                            <!-- DEPARTMENT -->
                            <td class="py-2.5 px-2.5 border-b border-r border-slate-300 text-slate-700 font-medium text-center whitespace-nowrap">
                                {{ $k['departemen'] }}
                            </td>

                            <!-- DIVISION -->
                            <td class="py-2.5 px-2.5 border-b border-r border-slate-300 text-slate-700 font-medium text-center whitespace-nowrap">
                                {{ $k['division'] ?? '-' }}
                            </td>

                            <!-- POSITION -->
                            <td class="py-2.5 px-3 border-b border-r border-slate-300 text-slate-800 font-medium uppercase leading-snug">
                                {{ $k['jabatan'] }}
                            </td>

                            <!-- START WORK -->
                            <td class="py-2.5 px-2 border-b border-r border-slate-300 text-center font-mono text-slate-700 whitespace-nowrap">
                                {{ $k['start_work'] }}
                            </td>

                            <!-- KJ -->
                            <td class="py-2.5 px-2 border-b border-r border-slate-300 text-center whitespace-nowrap">
                                <span class="font-bold text-slate-800 block">{{ $k['kj'] }}</span>
                                <span class="text-[9px] font-semibold px-1 py-0.5 bg-slate-100 text-slate-600 border border-slate-200 inline-block mt-0.5" title="{{ $k['form_name'] ?? '' }}">{{ str_starts_with($k['kode_form'] ?? '', '07-006') ? '07-006' : ($k['kode_form'] ?? '') }}</span>
                            </td>

                            <!-- Sub Gol -->
                            <td class="py-2.5 px-2 border-b border-r border-slate-300 text-center font-semibold text-slate-700 whitespace-nowrap">
                                {{ $k['sub_gol'] }}
                            </td>
                        @endif

                        <!-- Aksi Buttons (Nilai / Edit) -->
                        <td class="py-2.5 px-2.5 border-b border-r border-slate-300 text-center whitespace-nowrap">
                            <div class="inline-flex items-center justify-center gap-1.5">
                                @php
                                    $isSub07006Row = !empty($k['is_subordinate_07006']) || (!$isSelf && (str_starts_with($k['kode_form'] ?? '', '07-006') || ((int)($k['kj'] ?? 0) >= 5)));
                                @endphp
                                @if(!empty($k['doc_id']))
                                    @if($user->isSectionHead() && $isSub07006Row)
                                        <a href="{{ route('penilaian.show', $k['doc_id']) }}"
                                           class="p-1.5 bg-sky-50 text-sky-700 hover:bg-sky-100 border border-sky-300 transition shadow-2xs inline-flex items-center justify-center cursor-pointer"
                                           title="Lihat Nilai (Karyawan KJ 5+ dinilai langsung oleh Dept Head): {{ $k['form_name'] ?? 'Form Penilaian' }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            <span class="sr-only">Lihat Nilai</span>
                                        </a>
                                    @else
                                        <a href="{{ route('penilaian.show', $k['doc_id']) }}"
                                           class="p-1.5 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-300 transition shadow-2xs inline-flex items-center justify-center cursor-pointer"
                                           title="{{ $isSelf ? ('Buka Lembar Penilaian Mandiri (Anda): ' . ($k['form_name'] ?? 'Form Penilaian')) : ('Buka Lembar Penilaian: ' . ($k['form_name'] ?? 'Form Penilaian')) }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                            <span class="sr-only">{{ $isSelf ? 'Nilai Mandiri (Anda)' : 'Form Penilaian' }}</span>
                                        </a>
                                    @endif
                                @else
                                    <form method="POST" action="{{ route('penilaian.buka') }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="nrp" value="{{ $k['nrp'] }}">
                                        @if($user->isSectionHead() && $isSub07006Row)
                                            <button type="submit" 
                                                    class="p-1.5 bg-sky-50 text-sky-700 hover:bg-sky-100 border border-sky-300 transition shadow-2xs inline-flex items-center justify-center cursor-pointer"
                                                    title="Lihat Nilai (Karyawan KJ 5+ dinilai langsung oleh Dept Head): {{ $k['form_name'] ?? 'Form Penilaian' }}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                                <span class="sr-only">Lihat Nilai</span>
                                            </button>
                                        @else
                                            <button type="submit" 
                                                    class="p-1.5 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-300 transition shadow-2xs inline-flex items-center justify-center cursor-pointer"
                                                    title="{{ $isSelf ? ('Nilai Mandiri (Anda): ' . ($k['form_name'] ?? 'Form Penilaian')) : ('Nilai Karyawan: ' . ($k['form_name'] ?? 'Form Penilaian')) }}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                                </svg>
                                                <span class="sr-only">{{ $isSelf ? 'Nilai Mandiri (Anda)' : 'Nilai Karyawan' }}</span>
                                            </button>
                                        @endif
                                    </form>
                                @endif

                                @if(!$user->isForeman() && ($user->isAdmin() || $user->isHr() || $user->isSuperadmin()))
                                <button type="button" 
                                        @click="
                                            editKaryawan.nrp = '{{ $k['nrp'] }}';
                                            editKaryawan.nama_lengkap = '{{ addslashes($k['nama_lengkap']) }}';
                                            editKaryawan.jabatan = '{{ addslashes($k['jabatan']) }}';
                                            editKaryawan.tgl_masuk = '{{ \App\Services\GoogleSyncService::toYmd($k['tgl_masuk'] ?? '') }}';
                                            editKaryawan.golongan = '{{ $k['golongan'] ?? 'GOLONGAN II' }}';
                                            let rawSub = '{{ $k['sub_gol'] ?: ($k['golongan'] ?? '') }}';
                                            editKaryawan.sub_gol = rawSub.replace(/^GOLONGAN\s*/i, '').trim().toUpperCase();
                                            let rawKj = '{{ $k['kj'] }}';
                                            let mKj = rawKj.match(/\d+/);
                                            editKaryawan.kj = mKj ? ('KJ ' + parseInt(mKj[0], 10)) : (rawKj || 'KJ 2');
                                            editKaryawan.departemen = '{{ addslashes($k['departemen']) }}';
                                            editKaryawan.code_bagian = '{{ addslashes($k['code_bagian'] ?? '') }}';
                                            editKaryawan.nama_bagian = '{{ addslashes($k['nama_bagian'] ?? '') }}';
                                            editKaryawan.nama_sect = '{{ addslashes($k['nama_sect'] ?? ($k['seksi'] ?? '')) }}';
                                            showEditModal = true;
                                        "
                                        class="p-1.5 border border-slate-300 bg-white hover:bg-blue-50 hover:border-blue-400 text-slate-600 hover:text-blue-600 transition shadow-2xs inline-flex items-center justify-center cursor-pointer"
                                        title="Edit Data Karyawan">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                    <span class="sr-only">Edit Karyawan</span>
                                </button>

                                <form method="POST" action="{{ route('karyawan.destroy', $k['nrp']) }}" 
                                      onsubmit="return confirm('Apakah Anda yakin ingin menghapus data karyawan {{ addslashes($k['nama_lengkap']) }} (NRP: {{ $k['nrp'] }})? Data yang dihapus tidak dapat dikembalikan.');" 
                                      class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" 
                                            class="p-1.5 border border-slate-300 bg-white hover:bg-red-50 hover:border-red-400 text-slate-600 hover:text-red-600 transition shadow-2xs inline-flex items-center justify-center cursor-pointer"
                                            title="Hapus Data Karyawan">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                        <span class="sr-only">Hapus Karyawan</span>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>

                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ $useExcelCols ? 14 : 13 }}" class="py-8 text-center text-slate-400 border-b border-r border-slate-300">
                            Tidak ada data karyawan yang cocok dengan kriteria pencarian.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if(!$user->isForeman() && ($user->isAdmin() || $user->isHr() || $user->isSuperadmin()))
    <!-- ============================================================== -->
    <!-- MODAL 1: TAMBAH KARYAWAN (Crisp, Rounded-None)                 -->
    <!-- ============================================================== -->
    <div x-show="addModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div @click.away="addModal = false" class="bg-white border border-slate-300 shadow-2xl w-full max-w-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-150">
            <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
                <h3 class="font-bold text-sm text-slate-900 uppercase tracking-wider flex items-center gap-2">
                    <span class="w-2.5 h-2.5 bg-[#92302e]"></span>
                    <span>Tambah Karyawan</span>
                </h3>
                <button @click="addModal = false" type="button" class="text-slate-400 hover:text-slate-700 text-xl font-bold cursor-pointer leading-none">&times;</button>
            </div>
            <form action="{{ route('karyawan.store') }}" method="POST" 
                  @submit="if (isNrpDuplicate) { alert('Maaf, NRP ' + newNrp + ' sudah ditambahkan / terdaftar dalam sistem (atas nama: ' + duplicateEmployeeName + '). NRP tidak boleh dobel!'); $event.preventDefault(); return false; }"
                  class="p-6 space-y-4">
                @csrf

                <!-- Real-Time Duplicate NRP Warning Alert -->
                <div x-show="isNrpDuplicate" x-cloak 
                     class="p-3 bg-red-50 border-l-4 border-[#92302e] border-y border-r border-red-200 text-[#92302e] text-xs flex items-start gap-2.5 rounded-none animate-in fade-in duration-150">
                    <svg class="w-5 h-5 text-[#92302e] shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <div>
                        <div class="font-bold text-sm text-[#92302e] flex items-center gap-1.5">
                            <span>NRP Sudah Ditambahkan / Terdaftar!</span>
                        </div>
                        <p class="text-xs text-red-800 mt-1 leading-relaxed">
                            NRP <strong class="font-mono text-red-900 bg-red-100 px-1 py-0.5" x-text="newNrp"></strong> sudah terdaftar dalam sistem atas nama <strong class="text-red-950 font-bold underline" x-text="duplicateEmployeeName"></strong>. Sistem tidak mengizinkan NRP dobel. Silakan periksa kembali atau gunakan NRP lainnya.
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    
                    <!-- NRP -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-bold text-slate-700">NRP <span class="text-rose-500">*</span></label>
                            <span x-show="isNrpDuplicate" x-cloak class="text-[11px] text-[#92302e] font-bold uppercase tracking-wider">
                                ✕ NRP Sudah Ada
                            </span>
                        </div>
                        <input type="text" name="nrp" x-model="newNrp" required placeholder="Contoh: 2023099"
                               :class="isNrpDuplicate ? 'border-rose-500 bg-rose-50/50 text-rose-900 focus:border-[#92302e] focus:ring-[#92302e] ring-1 ring-[#92302e]' : 'border-slate-400 bg-white text-slate-900 focus:border-[#92302e] focus:ring-[#92302e]'"
                               class="w-full text-xs font-mono font-bold border py-2 px-3 rounded-none shadow-2xs focus:outline-none focus:ring-1">
                        <p x-show="isNrpDuplicate" x-cloak class="text-[11px] text-[#92302e] font-semibold mt-1">
                            ⚠️ Sudah terdaftar atas nama: <span class="font-bold underline" x-text="duplicateEmployeeName"></span> (NRP tidak boleh dobel)
                        </p>
                    </div>

                    <!-- NAMA -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">NAMA <span class="text-rose-500">*</span></label>
                        <input type="text" name="nama_lengkap" required placeholder="Contoh: BUDI SANTOSO"
                               class="w-full text-xs font-semibold border border-slate-400 bg-white py-2 px-3 text-slate-900 rounded-none shadow-2xs uppercase focus:border-[#92302e] focus:outline-none focus:ring-1 focus:ring-[#92302e]">
                    </div>

                    <!-- CODE BAGIAN -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">CODE BAGIAN</label>
                        <input type="text" name="code_bagian" placeholder="Contoh: 1141600000" value="{{ $defaultCodeBagian ?? '1141600000' }}"
                                class="w-full text-xs font-mono border border-slate-400 bg-white py-2 px-3 text-slate-900 rounded-none shadow-2xs focus:border-[#92302e] focus:outline-none focus:ring-1 focus:ring-[#92302e]">
                    </div>

                    <!-- NAMA BAGIAN -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">NAMA BAGIAN</label>
                        <input type="text" name="nama_bagian" placeholder="Contoh: FACTORY 1 SEC." value="{{ $defaultNamaBagian ?? 'FACTORY 1 SEC.' }}"
                               class="w-full text-xs border border-slate-400 bg-white py-2 px-3 text-slate-900 rounded-none shadow-2xs focus:border-[#92302e] focus:outline-none focus:ring-1 focus:ring-[#92302e]">
                    </div>

                    <!-- NAMA SECT. -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">SECTION</label>
                        <input type="text" name="nama_sect" placeholder="Contoh: FACTORY 1" value="{{ $defaultNamaSect ?? 'FACTORY 1' }}"
                               class="w-full text-xs border border-slate-400 bg-white py-2 px-3 text-slate-900 rounded-none shadow-2xs focus:border-[#92302e] focus:outline-none focus:ring-1 focus:ring-[#92302e]">
                    </div>

                    <!-- NAMA DEPT. -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">DEPARTMENT <span class="text-rose-500">*</span></label>
                        <input type="text" name="departemen" required value="{{ $defaultDept ?? ($user->departemen ?? 'PROD 2') }}"
                               class="w-full text-xs border border-slate-400 bg-white py-2 px-3 text-slate-900 rounded-none shadow-2xs focus:border-[#92302e] focus:outline-none focus:ring-1 focus:ring-[#92302e]">
                    </div>

                    <!-- POSITION -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">POSITION <span class="text-rose-500">*</span></label>
                        <input type="text" name="jabatan" required placeholder="Contoh: OPERATOR MACHINING"
                               class="w-full text-xs border border-slate-400 bg-white py-2 px-3 text-slate-900 rounded-none shadow-2xs uppercase focus:border-[#92302e] focus:outline-none focus:ring-1 focus:ring-[#92302e]">
                    </div>

                    <!-- START WORK -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">START WORK (Tanggal Masuk) <span class="text-rose-500">*</span></label>
                        <input type="date" name="tgl_masuk" required value="{{ date('Y-m-d') }}"
                               class="w-full text-xs border border-slate-400 bg-white py-2 px-3 text-slate-900 rounded-none shadow-2xs focus:border-[#92302e] focus:outline-none focus:ring-1 focus:ring-[#92302e]">
                    </div>

                    <!-- KJ -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">KJ <span class="text-rose-500">*</span></label>
                        <select name="kj" required class="w-full text-xs border border-slate-400 bg-white py-2 px-3 text-slate-900 rounded-none shadow-2xs focus:border-[#92302e] focus:outline-none focus:ring-1 focus:ring-[#92302e]">
                            <option value="KJ 1">KJ 1</option>
                            <option value="KJ 2" selected>KJ 2</option>
                            <option value="KJ 3">KJ 3</option>
                            <option value="KJ 4">KJ 4</option>
                            <option value="KJ 5">KJ 5</option>
                            <option value="KJ 6">KJ 6</option>
                            <option value="KJ 7">KJ 7</option>
                            <option value="KJ 8">KJ 8</option>
                            <option value="KJ 9">KJ 9</option>
                            <option value="KJ 10">KJ 10</option>
                            <option value="KJ 11">KJ 11</option>
                            <option value="KJ 12">KJ 12</option>
                        </select>
                    </div>

                    <!-- GOLONGAN -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">GOLONGAN <span class="text-rose-500">*</span></label>
                        <select name="sub_gol" required class="w-full text-xs border border-slate-400 bg-white py-2 px-3 text-slate-900 rounded-none shadow-2xs focus:border-[#92302e] focus:outline-none focus:ring-1 focus:ring-[#92302e]">
                            <optgroup label="Golongan I">
                                <option value="IA">IA</option>
                                <option value="IB">IB</option>
                                <option value="IC">IC</option>
                                <option value="ID">ID</option>
                            </optgroup>
                            <optgroup label="Golongan II">
                                <option value="IIA">IIA</option>
                                <option value="IIB" selected>IIB</option>
                                <option value="IIC">IIC</option>
                                <option value="IID">IID</option>
                                <option value="IIE">IIE</option>
                                <option value="IIF">IIF</option>
                            </optgroup>
                            <optgroup label="Golongan III">
                                <option value="IIIA">IIIA</option>
                                <option value="IIIB">IIIB</option>
                                <option value="IIIC">IIIC</option>
                                <option value="IIID">IIID</option>
                                <option value="IIIE">IIIE</option>
                                <option value="IIIF">IIIF</option>
                            </optgroup>
                            <optgroup label="Golongan IV">
                                <option value="IVA">IVA</option>
                                <option value="IVB">IVB</option>
                                <option value="IVC">IVC</option>
                                <option value="IVD">IVD</option>
                                <option value="IVE">IVE</option>
                                <option value="IVF">IVF</option>
                            </optgroup>
                            <optgroup label="Golongan V">
                                <option value="VA">VA</option>
                                <option value="VB">VB</option>
                                <option value="VC">VC</option>
                                <option value="VD">VD</option>
                                <option value="VE">VE</option>
                            </optgroup>
                            <optgroup label="Golongan VI">
                                <option value="VIA">VIA</option>
                                <option value="VIB">VIB</option>
                                <option value="VIC">VIC</option>
                                <option value="VID">VID</option>
                            </optgroup>
                        </select>
                    </div>

                </div>

                <div class="pt-4 border-t border-slate-200 flex items-center justify-end gap-2">
                    <button @click="addModal = false" type="button"
                            class="px-4 py-2 border border-slate-300 bg-slate-100 hover:bg-slate-200 text-xs font-bold text-slate-700 rounded-none cursor-pointer transition">
                        Batal
                    </button>
                    <button type="submit"
                            :disabled="isNrpDuplicate"
                            :class="isNrpDuplicate ? 'bg-slate-300 text-slate-500 border border-slate-300 cursor-not-allowed opacity-60' : 'bg-[#92302e] hover:bg-[#7e2524] text-white cursor-pointer'"
                            class="px-5 py-2 text-xs font-bold rounded-none shadow-sm transition">
                        Simpan Karyawan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- MODAL 2: UPLOAD SHEET (CSV / EXCEL - Crisp, Rounded-None)      -->
    <!-- ============================================================== -->
    <div x-show="uploadModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div @click.away="uploadModal = false" class="bg-white border border-slate-300 shadow-2xl w-full max-w-lg overflow-hidden animate-in fade-in zoom-in-95 duration-150">
            <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
                <h3 class="font-bold text-sm text-slate-900 uppercase tracking-wider flex items-center gap-2">
                    <span class="w-2.5 h-2.5 bg-[#92302e]"></span>
                    <span>Upload Sheet Karyawan</span>
                </h3>
                <button @click="uploadModal = false" type="button" class="text-slate-400 hover:text-slate-700 text-xl font-bold cursor-pointer leading-none">&times;</button>
            </div>
            <form action="{{ route('karyawan.import') }}" method="POST" enctype="multipart/form-data" class="p-6 space-y-4" x-data="{ isUploading: false }" @submit="isUploading = true">
                @csrf
                <input type="hidden" name="sync_existing" value="1">
                <p class="text-xs text-slate-600 leading-relaxed">
                    Unggah file spreadsheet (format <strong>.XLSX</strong> atau <strong>.CSV</strong>) berisi daftar karyawan bawahan. Karyawan yang diunggah akan otomatis terhubung ke akun Anda sebagai penilai.
                </p>

                <div class="p-3 bg-blue-50 border border-blue-200 text-xs text-blue-900 rounded-none">
                    <span class="font-bold block mb-1">Header Kolom yang Dibutuhkan:</span>
                    <code class="text-[11px] font-mono block bg-white p-1.5 border border-blue-200 text-blue-800 rounded-none overflow-x-auto whitespace-nowrap">
                        EMPLOYEENO; FULLNAME; ORGSTRUCTURECODE; ORGSTRUCTURENAME; LINE CATEGORY; POSITION; DIVISION; DEPT; SECTION; JOBCLASSCODE; JOBLEVELCODE; JOINDATE
                    </code>
                    <div class="mt-2 text-right">
                        <a href="{{ route('karyawan.template') }}" class="font-bold underline text-blue-700 hover:text-blue-900">
                            Unduh Contoh Template CSV &rarr;
                        </a>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Pilih File Spreadsheet (.XLSX / .CSV) <span class="text-rose-600">*</span></label>
                    <input type="file" name="sheet_file" required accept=".xlsx,.csv,.txt"
                           class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:border-0 file:text-xs file:font-bold file:bg-[#92302e] file:text-white hover:file:bg-[#7e2524] border border-slate-300 p-1 bg-slate-50 rounded-none">
                </div>

                <div class="pt-4 border-t border-slate-200 flex items-center justify-end gap-2">
                    <button @click="uploadModal = false" :disabled="isUploading" type="button"
                            class="px-4 py-2 border border-slate-300 bg-slate-100 hover:bg-slate-200 text-xs font-bold text-slate-700 rounded-none cursor-pointer transition">
                        Batal
                    </button>
                    <button type="submit" :disabled="isUploading"
                            class="inline-flex items-center gap-2 px-5 py-2 bg-[#92302e] hover:bg-[#7e2524] disabled:opacity-60 text-white text-xs font-bold rounded-none shadow-sm cursor-pointer transition">
                        <svg x-show="isUploading" class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span x-show="!isUploading">Mulai Impor File</span>
                        <span x-show="isUploading">Mengimpor &amp; Menyinkronkan...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- MODAL 3: EDIT DATA KARYAWAN (Crisp, Rounded-None)              -->
    <!-- ============================================================== -->
    <div x-show="showEditModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div @click.away="showEditModal = false" class="bg-white border border-slate-300 shadow-2xl w-full max-w-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-150 rounded-none">
            <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
                <div>
                    <h3 class="font-bold text-sm text-slate-900">Edit Data Karyawan</h3>
                    <p class="text-[11px] text-slate-500 mt-0.5">
                        Memperbarui data karyawan NRP: <span class="font-mono font-bold text-slate-900 bg-slate-200 px-1.5 py-0.5 border border-slate-300" x-text="editKaryawan.nrp"></span>
                    </p>
                </div>
                <button @click="showEditModal = false" type="button" class="text-slate-400 hover:text-slate-700 text-xl font-bold cursor-pointer leading-none">&times;</button>
            </div>
            <form :action="'/karyawan/' + editKaryawan.nrp + '/update'" method="POST" class="p-6 space-y-4">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    
                    <!-- NRP (Readonly) -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">NRP</label>
                        <input type="text" name="nrp" x-model="editKaryawan.nrp" readonly
                               class="w-full text-xs font-mono font-bold border border-slate-400 bg-slate-100 py-2 px-3 text-slate-600 shadow-2xs cursor-not-allowed rounded-none">
                    </div>

                    <!-- NAMA LENGKAP -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">NAMA LENGKAP <span class="text-rose-500">*</span></label>
                        <input type="text" name="nama_lengkap" x-model="editKaryawan.nama_lengkap" required
                               class="w-full text-xs font-semibold border border-slate-400 bg-white py-2 px-3 text-slate-900 shadow-2xs focus:border-[#92302e] focus:outline-none focus:ring-1 focus:ring-[#92302e] rounded-none">
                    </div>

                    <!-- CODE BAGIAN -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">CODE BAGIAN</label>
                        <input type="text" name="code_bagian" x-model="editKaryawan.code_bagian" placeholder="Contoh: 1141600000"
                               class="w-full text-xs font-mono border border-slate-400 bg-white py-2 px-3 text-slate-900 shadow-2xs focus:border-[#92302e] focus:outline-none focus:ring-1 focus:ring-[#92302e] rounded-none">
                    </div>

                    <!-- NAMA BAGIAN -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">NAMA BAGIAN</label>
                        <input type="text" name="nama_bagian" x-model="editKaryawan.nama_bagian" placeholder="Contoh: FACTORY 1 SEC"
                               class="w-full text-xs border border-slate-400 bg-white py-2 px-3 text-slate-900 shadow-2xs focus:border-[#92302e] focus:outline-none focus:ring-1 focus:ring-[#92302e] rounded-none">
                    </div>

                    <!-- SECTION -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">SECTION</label>
                        <input type="text" name="nama_sect" x-model="editKaryawan.nama_sect" placeholder="Contoh: FACTORY 1"
                               class="w-full text-xs border border-slate-400 bg-white py-2 px-3 text-slate-900 shadow-2xs focus:border-[#92302e] focus:outline-none focus:ring-1 focus:ring-[#92302e] rounded-none">
                    </div>

                    <!-- DEPARTMENT -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">DEPARTMENT <span class="text-rose-500">*</span></label>
                        <input type="text" name="departemen" x-model="editKaryawan.departemen" required
                               class="w-full text-xs border border-slate-400 bg-white py-2 px-3 text-slate-900 shadow-2xs focus:border-[#92302e] focus:outline-none focus:ring-1 focus:ring-[#92302e] rounded-none">
                    </div>

                    <!-- POSITION -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">POSITION <span class="text-rose-500">*</span></label>
                        <input type="text" name="jabatan" x-model="editKaryawan.jabatan" required placeholder="FOREMAN / LEADER / OPERATOR"
                               class="w-full text-xs border border-slate-400 bg-white py-2 px-3 text-slate-900 shadow-2xs focus:border-[#92302e] focus:outline-none focus:ring-1 focus:ring-[#92302e] rounded-none">
                    </div>

                    <!-- START WORK -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">START WORK (Tanggal Masuk)</label>
                        <input type="date" name="tgl_masuk" x-model="editKaryawan.tgl_masuk"
                               class="w-full text-xs border border-slate-400 bg-white py-2 px-3 text-slate-900 shadow-2xs focus:border-[#92302e] focus:outline-none focus:ring-1 focus:ring-[#92302e] rounded-none">
                    </div>

                    <!-- KJ -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">KJ</label>
                        <select name="kj" x-model="editKaryawan.kj"
                                class="w-full text-xs border border-slate-400 bg-white py-2 px-3 text-slate-900 shadow-2xs focus:border-[#92302e] focus:outline-none focus:ring-1 focus:ring-[#92302e] rounded-none">
                            <option value="KJ 1">KJ 1</option>
                            <option value="KJ 2">KJ 2</option>
                            <option value="KJ 3">KJ 3</option>
                            <option value="KJ 4">KJ 4</option>
                            <option value="KJ 5">KJ 5</option>
                            <option value="KJ 6">KJ 6</option>
                            <option value="KJ 7">KJ 7</option>
                            <option value="KJ 8">KJ 8</option>
                            <option value="KJ 9">KJ 9</option>
                            <option value="KJ 10">KJ 10</option>
                            <option value="KJ 11">KJ 11</option>
                            <option value="KJ 12">KJ 12</option>
                        </select>
                    </div>

                    <!-- GOLONGAN -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">GOLONGAN <span class="text-rose-500">*</span></label>
                        <select name="sub_gol" x-model="editKaryawan.sub_gol" required
                                class="w-full text-xs border border-slate-400 bg-white py-2 px-3 text-slate-900 rounded-none shadow-2xs focus:border-[#92302e] focus:outline-none focus:ring-1 focus:ring-[#92302e]">
                            <optgroup label="Golongan I">
                                <option value="IA">IA</option>
                                <option value="IB">IB</option>
                                <option value="IC">IC</option>
                                <option value="ID">ID</option>
                            </optgroup>
                            <optgroup label="Golongan II">
                                <option value="IIA">IIA</option>
                                <option value="IIB">IIB</option>
                                <option value="IIC">IIC</option>
                                <option value="IID">IID</option>
                                <option value="IIE">IIE</option>
                                <option value="IIF">IIF</option>
                            </optgroup>
                            <optgroup label="Golongan III">
                                <option value="IIIA">IIIA</option>
                                <option value="IIIB">IIIB</option>
                                <option value="IIIC">IIIC</option>
                                <option value="IIID">IIID</option>
                                <option value="IIIE">IIIE</option>
                                <option value="IIIF">IIIF</option>
                            </optgroup>
                            <optgroup label="Golongan IV">
                                <option value="IVA">IVA</option>
                                <option value="IVB">IVB</option>
                                <option value="IVC">IVC</option>
                                <option value="IVD">IVD</option>
                                <option value="IVE">IVE</option>
                                <option value="IVF">IVF</option>
                            </optgroup>
                            <optgroup label="Golongan V">
                                <option value="VA">VA</option>
                                <option value="VB">VB</option>
                                <option value="VC">VC</option>
                                <option value="VD">VD</option>
                                <option value="VE">VE</option>
                            </optgroup>
                            <optgroup label="Golongan VI">
                                <option value="VIA">VIA</option>
                                <option value="VIB">VIB</option>
                                <option value="VIC">VIC</option>
                                <option value="VID">VID</option>
                            </optgroup>
                        </select>
                    </div>

                </div>

                <div class="pt-4 border-t border-slate-200 flex items-center justify-end gap-2">
                    <button @click="showEditModal = false" type="button"
                            class="px-4 py-2 border border-slate-300 bg-slate-100 hover:bg-slate-200 text-xs font-bold text-slate-700 rounded-none cursor-pointer transition">
                        Batal
                    </button>
                    <button type="submit"
                            class="px-5 py-2 bg-[#92302e] hover:bg-[#7e2524] text-white text-xs font-bold rounded-none shadow-sm cursor-pointer transition">
                        Perbarui Data
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

</div>
@endsection
