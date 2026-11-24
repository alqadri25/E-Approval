@extends('layouts.app')

@section('title', 'Revisi Form Penilaian - Musashi PerformanceAppraisal')

@section('content')
<div class="space-y-6" x-data="revisiManager()">
    
    <!-- Header Section Card (Musashi Red Banner, Solid & Clean) -->
    <div class="bg-gradient-to-r from-[#962226] via-[#92302e] to-[#781b1f] text-white rounded-none p-5 sm:p-6 border border-[#781b1f] shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight">
                Revisi Form Penilaian
            </h1>
            <p class="text-xs text-red-100 font-medium mt-0.5">
                Kelola pengembalian formulir penilaian karyawan agar dapat dinilai ulang oleh penilai terkait (Foreman, Section Head, Dept Head, Div Head)
            </p>
        </div>
        
        <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap shrink-0">
            <span class="text-xs text-white bg-white/10 px-3.5 py-2 border border-white/25 font-mono font-bold shadow-xs">
                Total: {{ number_format($totalDokumen, 0, ',', '.') }} Dokumen
            </span>
        </div>
    </div>

    <!-- 4 Metric Cards (Grey Border & Solid Minimalist matching Data Karyawan) -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <!-- 1. Total Form PK -->
        <a href="{{ url()->current() }}" 
           class="bg-white p-3.5 border border-slate-300 shadow-xs hover:border-slate-400 transition group cursor-pointer"
           title="Tampilkan seluruh dokumen penilaian">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-700 block">Total Form PK</span>
            <div class="mt-1.5 flex items-baseline gap-1.5">
                <span class="text-2xl font-black text-slate-900">{{ number_format($totalDokumen, 0, ',', '.') }}</span>
                <span class="text-xs font-semibold text-slate-500">dokumen</span>
            </div>
            <div class="text-[10px] text-slate-500 mt-1 truncate font-medium">Seluruh formulir penilaian</div>
        </a>

        <!-- 2. Menunggu Penilaian -->
        <div class="bg-white p-3.5 border border-slate-300 shadow-xs transition" title="Formulir dalam proses penilaian">
            <span class="text-[10px] font-black uppercase tracking-wider text-amber-700 block">Menunggu Penilaian</span>
            <div class="mt-1.5 flex items-baseline gap-1.5">
                <span class="text-2xl font-black text-slate-900">{{ number_format($totalMenunggu, 0, ',', '.') }}</span>
                <span class="text-xs font-semibold text-amber-600">dokumen</span>
            </div>
            <div class="text-[10px] text-slate-500 mt-1 truncate font-medium">Dalam antrean penilaian</div>
        </div>

        <!-- 3. Sedang Direvisi -->
        <div class="bg-white p-3.5 border border-slate-300 shadow-xs transition" title="Formulir yang sedang dikembalikan/direvisi">
            <span class="text-[10px] font-black uppercase tracking-wider text-rose-700 block">Sedang Direvisi</span>
            <div class="mt-1.5 flex items-baseline gap-1.5">
                <span class="text-2xl font-black text-slate-900">{{ number_format($totalRevisi, 0, ',', '.') }}</span>
                <span class="text-xs font-semibold text-rose-600">dokumen</span>
            </div>
            <div class="text-[10px] text-slate-500 mt-1 truncate font-medium">Perbaikan pengisian nilai</div>
        </div>

        <!-- 4. Telah Selesai -->
        <div class="bg-white p-3.5 border border-slate-300 shadow-xs transition" title="Formulir penilaian telah selesai">
            <span class="text-[10px] font-black uppercase tracking-wider text-emerald-700 block">Telah Selesai</span>
            <div class="mt-1.5 flex items-baseline gap-1.5">
                <span class="text-2xl font-black text-slate-900">{{ number_format($totalSelesai, 0, ',', '.') }}</span>
                <span class="text-xs font-semibold text-emerald-600">dokumen</span>
            </div>
            <div class="text-[10px] text-slate-500 mt-1 truncate font-medium">Penilaian final lengkap</div>
        </div>
    </div>

    <!-- Alert Notifications -->
    @if(session('success'))
    <div class="p-3.5 bg-emerald-50 border border-emerald-300 text-emerald-900 text-xs flex items-center justify-between shadow-xs">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span class="font-medium">{{ session('success') }}</span>
        </div>
        <button type="button" @click="$el.parentElement.remove()" class="text-emerald-700 hover:text-emerald-900 font-bold cursor-pointer">&times;</button>
    </div>
    @endif

    @if(session('error'))
    <div class="p-3.5 bg-rose-50 border border-rose-300 text-rose-900 text-xs flex items-center justify-between shadow-xs">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            <span class="font-medium">{{ session('error') }}</span>
        </div>
        <button type="button" @click="$el.parentElement.remove()" class="text-rose-700 hover:text-rose-900 font-bold cursor-pointer">&times;</button>
    </div>
    @endif

    <!-- Filter & Search Bar with Clean Slate Borders (Always One Single Horizontal Line, Flush with Card) -->
    <div class="bg-white border border-slate-300 shadow-sm p-3.5 sm:p-4 overflow-x-auto">
        <form method="GET" action="{{ url()->current() }}" class="flex flex-nowrap items-end gap-2 sm:gap-2.5 w-full min-w-[780px]">
            
            <!-- Cari Karyawan -->
            <div class="flex-1 min-w-[140px]">
                <label class="block text-[10px] font-bold text-slate-700 uppercase tracking-wider mb-1 truncate">Cari Karyawan</label>
                <div class="relative">
                    <input type="text" 
                           name="search" 
                           value="{{ $search }}" 
                           placeholder="Cari nama, NRP..."
                           onchange="this.form.submit()"
                           class="w-full text-xs font-semibold text-slate-800 border border-slate-300 hover:border-slate-400 bg-white py-2 pl-8 pr-2 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-xs">
                    <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
            </div>

            <!-- Filter Division (No 'Semua' word) -->
            <div class="w-28 sm:w-32 shrink-0">
                <label class="block text-[10px] font-bold text-slate-700 uppercase tracking-wider mb-1 truncate">Division</label>
                <select name="divisi" onchange="this.form.submit()" class="w-full text-xs font-semibold text-slate-700 border border-slate-300 hover:border-slate-400 bg-white py-2 px-2 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-xs cursor-pointer">
                    <option value="">Division</option>
                    @foreach(($divisions ?? []) as $div)
                    <option value="{{ $div }}" {{ ($filterDivisi ?? '') === $div ? 'selected' : '' }}>{{ $div }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Filter Department (No 'Semua' word) -->
            <div class="w-32 sm:w-36 shrink-0">
                <label class="block text-[10px] font-bold text-slate-700 uppercase tracking-wider mb-1 truncate">Department</label>
                <select name="departemen" onchange="this.form.submit()" class="w-full text-xs font-semibold text-slate-700 border border-slate-300 hover:border-slate-400 bg-white py-2 px-2 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-xs cursor-pointer">
                    <option value="">Department</option>
                    @foreach($departments as $dept)
                    <option value="{{ $dept }}" {{ $filterDept === $dept ? 'selected' : '' }}>{{ $dept }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Filter Section (No 'Semua' word) -->
            <div class="w-28 sm:w-32 shrink-0">
                <label class="block text-[10px] font-bold text-slate-700 uppercase tracking-wider mb-1 truncate">Section</label>
                <select name="seksi" onchange="this.form.submit()" class="w-full text-xs font-semibold text-slate-700 border border-slate-300 hover:border-slate-400 bg-white py-2 px-2 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-xs cursor-pointer">
                    <option value="">Section</option>
                    @foreach($sections as $sec)
                    <option value="{{ $sec }}" {{ $filterSeksi === $sec ? 'selected' : '' }}>{{ $sec }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Filter KJ / Golongan (No 'Semua' word) -->
            <div class="w-24 sm:w-28 shrink-0">
                <label class="block text-[10px] font-bold text-slate-700 uppercase tracking-wider mb-1 truncate">KJ / Golongan</label>
                <select name="kj" onchange="this.form.submit()" class="w-full text-xs font-semibold text-slate-700 border border-slate-300 hover:border-slate-400 bg-white py-2 px-2 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-xs cursor-pointer">
                    <option value="">KJ / Golongan</option>
                    @foreach($kjList as $k)
                    <option value="{{ $k }}" {{ $filterKj === $k ? 'selected' : '' }}>{{ $k }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Filter Status Penilaian (No 'Semua' word) -->
            <div class="w-32 sm:w-36 shrink-0">
                <label class="block text-[10px] font-bold text-slate-700 uppercase tracking-wider mb-1 truncate">Status Penilaian</label>
                <select name="status" onchange="this.form.submit()" class="w-full text-xs font-semibold text-slate-700 border border-slate-300 hover:border-slate-400 bg-white py-2 px-2 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-xs cursor-pointer">
                    <option value="">Status Penilaian</option>
                    <option value="menunggu_foreman" {{ $filterStatus === 'menunggu_foreman' ? 'selected' : '' }}>Menunggu Foreman</option>
                    <option value="menunggu_section_head" {{ $filterStatus === 'menunggu_section_head' ? 'selected' : '' }}>Menunggu Section Head</option>
                    <option value="menunggu_dept_head" {{ $filterStatus === 'menunggu_dept_head' ? 'selected' : '' }}>Menunggu Dept Head</option>
                    <option value="menunggu_div_head" {{ $filterStatus === 'menunggu_div_head' ? 'selected' : '' }}>Menunggu Div Head</option>
                    <option value="revisi" {{ $filterStatus === 'revisi' ? 'selected' : '' }}>Sedang Revisi</option>
                    <option value="selesai" {{ $filterStatus === 'selesai' ? 'selected' : '' }}>Selesai</option>
                </select>
            </div>

            <!-- Urutkan Berdasarkan -->
            <div class="w-40 sm:w-48 shrink-0">
                <label class="block text-[10px] font-bold text-slate-700 uppercase tracking-wider mb-1 truncate">Urutan Prioritas</label>
                <select name="sort" onchange="this.form.submit()" class="w-full text-xs font-semibold text-slate-700 border border-slate-300 hover:border-slate-400 bg-white py-2 px-2 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-xs cursor-pointer">
                    <option value="ttd_desc" {{ ($sortBy ?? 'ttd_desc') === 'ttd_desc' ? 'selected' : '' }}>★ TTD & Nilai Terlengkap</option>
                    <option value="terbaru" {{ ($sortBy ?? '') === 'terbaru' ? 'selected' : '' }}>Aktivitas Terbaru</option>
                    <option value="nama_asc" {{ ($sortBy ?? '') === 'nama_asc' ? 'selected' : '' }}>Nama Karyawan (A-Z)</option>
                    <option value="dept_asc" {{ ($sortBy ?? '') === 'dept_asc' ? 'selected' : '' }}>Departemen (A-Z)</option>
                </select>
            </div>

            <!-- Tampilkan Data Per Halaman (20, 50, 100, Semua) -->
            <div class="w-24 sm:w-28 shrink-0">
                <label class="block text-[10px] font-bold text-slate-700 uppercase tracking-wider mb-1 truncate">Tampilkan</label>
                <select name="per_page" onchange="this.form.submit()" class="w-full text-xs font-semibold text-slate-700 border border-slate-300 hover:border-slate-400 bg-white py-2 px-2 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-xs cursor-pointer">
                    <option value="20" {{ ($perPage ?? '20') === '20' ? 'selected' : '' }}>20 Data</option>
                    <option value="50" {{ ($perPage ?? '') === '50' ? 'selected' : '' }}>50 Data</option>
                    <option value="100" {{ ($perPage ?? '') === '100' ? 'selected' : '' }}>100 Data</option>
                    <option value="semua" {{ ($perPage ?? '') === 'semua' ? 'selected' : '' }}>Semua Data</option>
                </select>
            </div>

            <!-- Hidden Submit for Enter Key -->
            <button type="submit" class="hidden" tabindex="-1"></button>

            <!-- Reset Filter (Red Button) -->
            @if(!empty($search) || !empty($filterDivisi) || !empty($filterDept) || !empty($filterSeksi) || !empty($filterKj) || !empty($filterStatus) || ($sortBy ?? 'ttd_desc') !== 'ttd_desc' || ($perPage ?? '20') !== '20')
            <div class="shrink-0">
                <a href="{{ url()->current() }}"
                   class="px-3 py-2 border border-rose-300 bg-rose-50 text-[#92302e] text-xs font-bold hover:bg-rose-100 transition shadow-2xs flex items-center gap-1 whitespace-nowrap">
                    <span>✕</span> Reset Filter
                </a>
            </div>
            @endif
        </form>
    </div>

    <!-- Table Card: Clean, Solid, Grey Headers, Flush like Data Karyawan -->
    <div class="bg-white border border-slate-300 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-separate border-spacing-0 text-xs">
                <thead>
                    <tr class="bg-slate-100 text-slate-700 font-bold text-[11px] uppercase tracking-wider sticky top-0 z-10 shadow-2xs">
                        <th class="py-3 px-2 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-12 min-w-[48px] max-w-[48px] whitespace-nowrap sticky left-0 z-30">NO</th>
                        <th class="py-3 px-2.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-28 min-w-[100px] max-w-[100px] whitespace-nowrap sticky left-[48px] z-30">NRP</th>
                        <th class="py-3 px-3.5 border-b border-r-2 border-r-slate-500 border-slate-300 bg-slate-100 font-bold text-slate-700 w-52 min-w-[200px] max-w-[200px] whitespace-nowrap sticky left-[148px] z-30 shadow-[4px_0_8px_-2px_rgba(0,0,0,0.15)]">NAMA LENGKAP</th>
                        <th class="py-3 px-2.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-28 whitespace-nowrap">DIVISION</th>
                        <th class="py-3 px-2.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-28 whitespace-nowrap">DEPARTMENT</th>
                        <th class="py-3 px-2.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-28 whitespace-nowrap">SECTION</th>
                        <th class="py-3 px-2 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-20 whitespace-nowrap">KJ / FORM</th>
                        <th class="py-3 px-3 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center min-w-[130px] whitespace-nowrap">STATUS SAAT INI</th>
                        <th class="py-3 px-3 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center min-w-[110px] whitespace-nowrap">FOREMAN</th>
                        <th class="py-3 px-3 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center min-w-[110px] whitespace-nowrap">SECTION HEAD</th>
                        <th class="py-3 px-3 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center min-w-[110px] whitespace-nowrap">DEPT HEAD</th>
                        <th class="py-3 px-3 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center min-w-[110px] whitespace-nowrap">DIV HEAD</th>
                        <th class="py-3 px-3 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center min-w-[100px] whitespace-nowrap">NILAI AKHIR</th>
                        <th class="py-3 px-2.5 border-b border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-36 min-w-[140px] whitespace-nowrap">AKSI REVISI</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($enrichedItems as $idx => $item)
                    <tr class="hover:bg-slate-50 transition-colors group">
                        
                        <!-- NO (Sticky Left) -->
                        <td class="py-2.5 px-2 border-b border-r border-slate-300 text-center font-bold text-slate-800 bg-white group-hover:bg-slate-100 whitespace-nowrap w-12 min-w-[48px] max-w-[48px] sticky left-0 z-20">
                            {{ $dokumenList->firstItem() + $idx }}
                        </td>

                        <!-- NRP (Sticky Left) -->
                        <td class="py-2.5 px-2.5 border-b border-r border-slate-300 text-center font-mono text-slate-800 bg-white group-hover:bg-slate-100 whitespace-nowrap w-28 min-w-[100px] max-w-[100px] sticky left-[48px] z-20 font-semibold">
                            {{ $item->nrp }}
                        </td>

                        <!-- NAMA LENGKAP & JABATAN (Sticky Left, Divider Tebal) -->
                        <td class="py-2.5 px-3.5 border-b border-r-2 border-r-slate-500 border-slate-300 text-slate-800 bg-white group-hover:bg-slate-100 whitespace-nowrap w-52 min-w-[200px] max-w-[200px] sticky left-[148px] z-20 shadow-[4px_0_8px_-2px_rgba(0,0,0,0.15)]">
                            <div class="font-bold text-slate-900 text-xs leading-tight">
                                {{ $item->nama_lengkap }}
                            </div>
                            <div class="text-[10.5px] text-slate-500 truncate max-w-[180px] mt-0.5">
                                {{ $item->jabatan ?? '-' }}
                            </div>
                        </td>

                        <!-- DIVISION -->
                        <td class="py-2.5 px-2.5 border-b border-r border-slate-300 text-slate-700 font-medium text-center whitespace-nowrap">
                            {{ $item->division ?? '-' }}
                        </td>

                        <!-- DEPARTMENT -->
                        <td class="py-2.5 px-2.5 border-b border-r border-slate-300 text-slate-700 font-medium text-center whitespace-nowrap">
                            {{ $item->departemen ?? '-' }}
                        </td>

                        <!-- SECTION -->
                        <td class="py-2.5 px-2.5 border-b border-r border-slate-300 text-center whitespace-nowrap">
                            <span class="px-2 py-0.5 text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200 inline-block whitespace-nowrap">
                                {{ $item->seksi ?? '-' }}
                            </span>
                        </td>

                        <!-- KJ / FORM -->
                        <td class="py-2.5 px-2 border-b border-r border-slate-300 text-center whitespace-nowrap">
                            <span class="font-mono font-bold text-slate-800 text-[11px] block">{{ $item->kj ?? '-' }}</span>
                            <span class="text-[10px] text-slate-500 font-mono block mt-0.5">{{ $item->kode_form }}</span>
                        </td>

                        <!-- STATUS SAAT INI -->
                        <td class="py-2.5 px-3 border-b border-r border-slate-300 text-center whitespace-nowrap">
                            @php
                                $statusBadgeClass = match($item->status) {
                                    'menunggu_foreman' => 'bg-amber-50 text-amber-800 border-amber-300',
                                    'menunggu_section_head' => 'bg-sky-50 text-sky-800 border-sky-300',
                                    'menunggu_dept_head' => 'bg-indigo-50 text-indigo-800 border-indigo-300',
                                    'menunggu_div_head' => 'bg-purple-50 text-purple-800 border-purple-300',
                                    'selesai' => 'bg-emerald-50 text-emerald-800 border-emerald-300 font-bold',
                                    'revisi' => 'bg-rose-50 text-rose-800 border-rose-300 font-bold',
                                    default => 'bg-slate-100 text-slate-700 border-slate-300',
                                };
                                $statusLabel = match($item->status) {
                                    'menunggu_foreman' => 'Menunggu Foreman',
                                    'menunggu_section_head' => 'Menunggu Section',
                                    'menunggu_dept_head' => 'Menunggu Dept Head',
                                    'menunggu_div_head' => 'Menunggu Div Head',
                                    'selesai' => 'Selesai',
                                    'revisi' => 'Revisi',
                                    default => ucwords(str_replace('_', ' ', $item->status)),
                                };
                            @endphp
                            <span class="px-2 py-0.5 text-[10px] font-bold border inline-block whitespace-nowrap {{ $statusBadgeClass }}">
                                {{ $statusLabel }}
                            </span>
                        </td>

                        <!-- NILAI FOREMAN -->
                        <td class="py-2.5 px-3 border-b border-r border-slate-300 text-center whitespace-nowrap">
                            @if(!$item->needs_foreman)
                                <span class="text-[10.5px] text-slate-400 italic">Tanpa FM</span>
                            @elseif($item->foreman_signed)
                                <div class="font-black text-slate-900 font-mono text-xs">
                                    {{ $item->foreman_total > 0 ? number_format($item->foreman_total, 2) : '-' }}
                                </div>
                                <div class="mt-0.5">
                                    <span class="px-1.5 py-0.2 text-[9.5px] font-bold border border-emerald-300 bg-emerald-50 text-emerald-800 inline-block">TTD ✓</span>
                                </div>
                            @elseif($item->has_foreman_value)
                                <div class="font-black text-slate-900 font-mono text-xs">
                                    {{ number_format($item->foreman_total, 2) }}
                                </div>
                                <div class="mt-0.5">
                                    <span class="px-1.5 py-0.2 text-[9.5px] font-bold border border-amber-300 bg-amber-50 text-amber-800 inline-block">Draft</span>
                                </div>
                            @else
                                <span class="text-[10.5px] text-slate-400 italic">Belum Isi</span>
                            @endif
                        </td>

                        <!-- NILAI SECTION HEAD -->
                        <td class="py-2.5 px-3 border-b border-r border-slate-300 text-center whitespace-nowrap">
                            @if(!$item->needs_section_head)
                                <span class="text-[10.5px] text-slate-400 italic">-</span>
                            @elseif($item->section_signed)
                                <div class="font-black text-slate-900 font-mono text-xs">
                                    {{ $item->section_total > 0 ? number_format($item->section_total, 2) : '-' }}
                                </div>
                                <div class="mt-0.5">
                                    <span class="px-1.5 py-0.2 text-[9.5px] font-bold border border-emerald-300 bg-emerald-50 text-emerald-800 inline-block">TTD ✓</span>
                                </div>
                            @elseif($item->has_section_value)
                                <div class="font-black text-slate-900 font-mono text-xs">
                                    {{ number_format($item->section_total, 2) }}
                                </div>
                                <div class="mt-0.5">
                                    <span class="px-1.5 py-0.2 text-[9.5px] font-bold border border-amber-300 bg-amber-50 text-amber-800 inline-block">Draft</span>
                                </div>
                            @else
                                <span class="text-[10.5px] text-slate-400 italic">Belum Isi</span>
                            @endif
                        </td>

                        <!-- NILAI DEPT HEAD -->
                        <td class="py-2.5 px-3 border-b border-r border-slate-300 text-center whitespace-nowrap">
                            @if(!$item->needs_dept_head)
                                <span class="text-[10.5px] text-slate-400 italic">-</span>
                            @elseif($item->dept_signed)
                                <div class="font-black text-slate-900 font-mono text-xs">
                                    {{ $item->dept_total > 0 ? number_format($item->dept_total, 2) : '-' }}
                                </div>
                                <div class="mt-0.5">
                                    <span class="px-1.5 py-0.2 text-[9.5px] font-bold border border-emerald-300 bg-emerald-50 text-emerald-800 inline-block">TTD ✓</span>
                                </div>
                            @elseif($item->has_dept_value)
                                <div class="font-black text-slate-900 font-mono text-xs">
                                    {{ number_format($item->dept_total, 2) }}
                                </div>
                                <div class="mt-0.5">
                                    <span class="px-1.5 py-0.2 text-[9.5px] font-bold border border-amber-300 bg-amber-50 text-amber-800 inline-block">Draft</span>
                                </div>
                            @else
                                <span class="text-[10.5px] text-slate-400 italic">Belum Isi</span>
                            @endif
                        </td>

                        <!-- NILAI DIV HEAD -->
                        <td class="py-2.5 px-3 border-b border-r border-slate-300 text-center whitespace-nowrap">
                            @if(!$item->needs_div_head)
                                <span class="text-[10.5px] text-slate-400 italic">-</span>
                            @elseif($item->div_signed)
                                <div class="font-black text-slate-900 font-mono text-xs">
                                    {{ $item->div_total > 0 ? number_format($item->div_total, 2) : '-' }}
                                </div>
                                <div class="mt-0.5">
                                    <span class="px-1.5 py-0.2 text-[9.5px] font-bold border border-emerald-300 bg-emerald-50 text-emerald-800 inline-block">TTD ✓</span>
                                </div>
                            @elseif($item->has_div_value)
                                <div class="font-black text-slate-900 font-mono text-xs">
                                    {{ number_format($item->div_total, 2) }}
                                </div>
                                <div class="mt-0.5">
                                    <span class="px-1.5 py-0.2 text-[9.5px] font-bold border border-amber-300 bg-amber-50 text-amber-800 inline-block">Draft</span>
                                </div>
                            @else
                                <span class="text-[10.5px] text-slate-400 italic">Belum Isi</span>
                            @endif
                        </td>

                        <!-- NILAI AKHIR / GRADE -->
                        <td class="py-2.5 px-3 border-b border-r border-slate-300 text-center whitespace-nowrap bg-slate-50/40">
                            @if(!in_array($item->status, ['menunggu_foreman', 'draft', 'menunggu_section_head', 'revisi']) && ($item->total_nilai ?? 0) > 0)
                                <div class="font-black text-slate-900 font-mono text-xs">{{ number_format($item->total_nilai, 2) }}</div>
                                @if(!empty($item->grade_original))
                                    <span class="px-1.5 py-0.2 text-[9.5px] font-bold bg-slate-800 text-white inline-block mt-0.5">{{ $item->grade_original }}</span>
                                @endif
                            @else
                                <span class="text-[10.5px] text-slate-400">-</span>
                            @endif
                        </td>

                        <!-- AKSI REVISI -->
                        <td class="py-2.5 px-2.5 border-b border-slate-300 text-center whitespace-nowrap">
                            <div class="flex items-center justify-center gap-1.5">
                                @if($item->has_any_revision)
                                    <button type="button" 
                                            @click="openRevisiModal({{ json_encode($item) }})"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-[#92302e] hover:bg-[#7e2524] text-white text-[11px] font-bold shadow-xs transition cursor-pointer"
                                            title="Buka pilihan revisi penilai untuk form ini">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                        </svg>
                                        <span>Revisi</span>
                                    </button>
                                @else
                                    <span class="inline-block px-2 py-1 text-[10px] text-slate-400 bg-slate-100 border border-slate-200 italic" title="Belum ada evaluasi dari penilai yang dapat direvisi">
                                        Belum Dinilai
                                    </span>
                                @endif

                                <a href="{{ route('penilaian.show', ['id' => $item->doc_id]) }}" 
                                   target="_blank" 
                                   class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-[11px] font-bold shadow-xs transition cursor-pointer"
                                   title="Buka form penilaian karyawan ini di tab baru">
                                    <svg class="w-3 h-3 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                    <span>Form</span>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="14" class="py-8 text-center text-slate-400 text-xs italic bg-white border-b border-slate-300">
                            Tidak ada dokumen penilaian yang sesuai dengan filter pencarian.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Footer Pagination Info Flush Inside Card (Matching Data Karyawan) -->
        <div class="px-4 py-3 bg-white border-t border-slate-300 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500">
            <div>
                Menampilkan <span class="font-bold text-slate-700">{{ $dokumenList->firstItem() ?? 0 }}</span> - <span class="font-bold text-slate-700">{{ $dokumenList->lastItem() ?? 0 }}</span> dari <span class="font-bold text-slate-700">{{ $dokumenList->total() }}</span> formulir penilaian terkelola
                @if(($perPage ?? '') === 'semua')
                    <span class="ml-1 text-slate-400 font-semibold">(Seluruh Data Ditampilkan)</span>
                @else
                    <span class="ml-1 text-slate-400">({{ $perPage ?? 20 }} per halaman)</span>
                @endif
                @if(($sortBy ?? 'ttd_desc') === 'ttd_desc')
                    <span class="ml-1.5 text-emerald-700 font-semibold hidden sm:inline">• Prioritas TTD & Penilaian Terlengkap</span>
                @elseif(($sortBy ?? '') === 'terbaru')
                    <span class="ml-1.5 text-slate-500 font-semibold hidden sm:inline">• Urutan Aktivitas Terbaru</span>
                @elseif(($sortBy ?? '') === 'nama_asc')
                    <span class="ml-1.5 text-slate-500 font-semibold hidden sm:inline">• Urutan Nama (A-Z)</span>
                @elseif(($sortBy ?? '') === 'dept_asc')
                    <span class="ml-1.5 text-slate-500 font-semibold hidden sm:inline">• Urutan Departemen (A-Z)</span>
                @endif
            </div>
            <div>
                {{ $dokumenList->links() }}
            </div>
        </div>
    </div>

    <!-- Modal Dialog Revisi Form Penilaian (Zero Radius / Square matching Data Karyawan) -->
    <div x-show="showModal" 
         x-cloak 
         class="fixed inset-0 z-50 overflow-y-auto p-3 sm:p-4 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center animate-in fade-in duration-150">
        
        <div class="bg-white border border-slate-300 shadow-2xl w-full max-w-lg my-auto flex flex-col max-h-[92vh] overflow-hidden animate-in zoom-in-95 duration-150"
             @click.away="showModal = false">
            
            <!-- Modal Header (Fixed / Sticky at Top) -->
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50 flex-shrink-0">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 bg-rose-50 border border-rose-200 text-[#92302e] flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-sm text-slate-900 leading-tight">Revisi Form Penilaian</h3>
                        <p class="text-[11px] text-slate-500 leading-tight mt-0.5">Kembalikan dokumen agar penilai terkait dapat mengisi ulang</p>
                    </div>
                </div>
                <button type="button" @click="showModal = false" class="text-slate-400 hover:text-slate-700 text-xl font-bold cursor-pointer leading-none">&times;</button>
            </div>

            <!-- Modal Body Form with Scrollable Content & Pinned Sticky Footer -->
            <form :action="formActionUrl" method="POST" class="flex flex-col flex-1 overflow-hidden">
                @csrf
                <input type="hidden" name="target_level" :value="primaryTargetLevel">

                <!-- Scrollable Body Container -->
                <div class="p-5 space-y-4 overflow-y-auto flex-1 max-h-[calc(92vh-140px)]">
                    <!-- Employee Snapshot Card in Modal -->
                    <div class="bg-slate-50 border border-slate-300 p-3 text-xs space-y-1">
                        <div class="flex items-center justify-between">
                            <span class="font-black text-slate-900 text-sm" x-text="activeDoc ? activeDoc.nama_lengkap : ''"></span>
                            <span class="px-2 py-0.5 text-[10px] font-bold border border-slate-300 bg-white" x-text="activeDoc ? activeDoc.status.replace('_', ' ').toUpperCase() : ''"></span>
                        </div>
                        <div class="text-slate-600 font-mono text-[11px] flex items-center gap-2">
                            <span>NRP: <strong class="text-[#92302e]" x-text="activeDoc ? activeDoc.nrp : ''"></strong></span>
                            <span>&bull;</span>
                            <span x-text="activeDoc ? ((activeDoc.division && activeDoc.division !== '-' ? activeDoc.division + ' &bull; ' : '') + activeDoc.departemen + ' / ' + (activeDoc.seksi || '-')) : ''"></span>
                            <span>&bull;</span>
                            <span>Form: <strong x-text="activeDoc ? activeDoc.kode_form : ''"></strong></span>
                        </div>
                    </div>

                    <!-- Target Level Revision Selection (Checkbox Cards - Can Pick Multiple) -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-bold text-slate-900 uppercase tracking-wider">
                                Pilih Target Tingkat Revisi <span class="text-rose-600">*</span>
                            </label>
                            <span class="text-[11px] font-semibold text-[#92302e] bg-red-50 px-2 py-0.5 border border-red-200"
                                  x-text="selectedTargets.length + ' dipilih'"></span>
                        </div>
                        <p class="text-[11px] text-slate-500 mb-2.5">
                            Bisa memilih satu atau beberapa tingkat penilai sekaligus (checkbox).
                        </p>
                        <div class="space-y-2">

                            <!-- 1. Option Foreman -->
                            <label class="relative flex items-start gap-3 p-3 border cursor-pointer transition-all select-none"
                                   :class="activeDoc && activeDoc.can_revisi_foreman ? (selectedTargets.includes('foreman') ? 'border-[#92302e] bg-red-50/50 ring-1 ring-[#92302e]' : 'border-slate-300 hover:border-slate-400 bg-white') : 'border-slate-200 bg-slate-100 opacity-60 cursor-not-allowed'">
                                <input type="checkbox" 
                                       name="target_levels[]" 
                                       value="foreman" 
                                       x-model="selectedTargets"
                                       :disabled="!activeDoc || !activeDoc.can_revisi_foreman" 
                                       class="mt-1 text-[#92302e] focus:ring-[#92302e] rounded-none border-slate-300">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-xs text-slate-900">Revisi ke Foreman</span>
                                        <template x-if="!activeDoc || !activeDoc.can_revisi_foreman">
                                            <span class="text-[10px] font-semibold text-slate-500 bg-slate-200 px-1.5 py-0.5 border border-slate-300">
                                                <span x-text="activeDoc && !activeDoc.needs_foreman ? 'Tanpa Foreman' : 'Belum Ada Nilai Foreman'"></span>
                                            </span>
                                        </template>
                                    </div>
                                    <p class="text-[11px] text-slate-500 mt-0.5">
                                        Status dikembalikan ke <strong>Menunggu Foreman</strong>. Foreman dapat mengisi ulang seluruh aspek penilaian.
                                    </p>
                                </div>
                            </label>

                            <!-- 2. Option Section Head (Strict rule: cannot be chosen if section head hasn't evaluated yet) -->
                            <label class="relative flex items-start gap-3 p-3 border cursor-pointer transition-all select-none"
                                   :class="activeDoc && activeDoc.can_revisi_section ? (selectedTargets.includes('section_head') ? 'border-[#92302e] bg-red-50/50 ring-1 ring-[#92302e]' : 'border-slate-300 hover:border-slate-400 bg-white') : 'border-slate-200 bg-slate-100 opacity-60 cursor-not-allowed'">
                                <input type="checkbox" 
                                       name="target_levels[]" 
                                       value="section_head" 
                                       x-model="selectedTargets"
                                       :disabled="!activeDoc || !activeDoc.can_revisi_section" 
                                       class="mt-1 text-[#92302e] focus:ring-[#92302e] rounded-none border-slate-300">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-xs text-slate-900">Revisi ke Section Head</span>
                                        <template x-if="!activeDoc || !activeDoc.can_revisi_section">
                                            <span class="text-[10px] font-semibold text-rose-700 bg-rose-100 px-1.5 py-0.5 border border-rose-300">
                                                Belum Ada Nilai Section Head (Tidak Bisa Dipilih)
                                            </span>
                                        </template>
                                    </div>
                                    <p class="text-[11px] text-slate-500 mt-0.5">
                                        Status dikembalikan ke <strong>Menunggu Section Head</strong>. Section Head dapat mengisi ulang atau mengoreksi nilai.
                                    </p>
                                </div>
                            </label>

                            <!-- 3. Option Department Head -->
                            <label class="relative flex items-start gap-3 p-3 border cursor-pointer transition-all select-none"
                                   :class="activeDoc && activeDoc.can_revisi_dept ? (selectedTargets.includes('dept_head') ? 'border-[#92302e] bg-red-50/50 ring-1 ring-[#92302e]' : 'border-slate-300 hover:border-slate-400 bg-white') : 'border-slate-200 bg-slate-100 opacity-60 cursor-not-allowed'">
                                <input type="checkbox" 
                                       name="target_levels[]" 
                                       value="dept_head" 
                                       x-model="selectedTargets"
                                       :disabled="!activeDoc || !activeDoc.can_revisi_dept" 
                                       class="mt-1 text-[#92302e] focus:ring-[#92302e] rounded-none border-slate-300">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-xs text-slate-900">Revisi ke Department Head</span>
                                        <template x-if="!activeDoc || !activeDoc.can_revisi_dept">
                                            <span class="text-[10px] font-semibold text-slate-500 bg-slate-200 px-1.5 py-0.5 border border-slate-300">
                                                Belum Ada Nilai Dept Head
                                            </span>
                                        </template>
                                    </div>
                                    <p class="text-[11px] text-slate-500 mt-0.5">
                                        Status dikembalikan ke <strong>Menunggu Dept Head</strong>. Dept Head dapat mengubah penyesuaian nilai atau docking.
                                    </p>
                                </div>
                            </label>

                            <!-- 4. Option Division Head -->
                            <template x-if="activeDoc && (activeDoc.has_div_value || activeDoc.kode_form.includes('07-006'))">
                                <label class="relative flex items-start gap-3 p-3 border cursor-pointer transition-all select-none"
                                       :class="activeDoc && activeDoc.can_revisi_div ? (selectedTargets.includes('div_head') ? 'border-[#92302e] bg-red-50/50 ring-1 ring-[#92302e]' : 'border-slate-300 hover:border-slate-400 bg-white') : 'border-slate-200 bg-slate-100 opacity-60 cursor-not-allowed'">
                                    <input type="checkbox" 
                                           name="target_levels[]" 
                                           value="div_head" 
                                           x-model="selectedTargets"
                                           :disabled="!activeDoc || !activeDoc.can_revisi_div" 
                                           class="mt-1 text-[#92302e] focus:ring-[#92302e] rounded-none border-slate-300">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between">
                                            <span class="font-bold text-xs text-slate-900">Revisi ke Division Head</span>
                                            <template x-if="!activeDoc || !activeDoc.can_revisi_div">
                                                <span class="text-[10px] font-semibold text-slate-500 bg-slate-200 px-1.5 py-0.5 border border-slate-300">
                                                    Belum Disahkan Div Head
                                                </span>
                                            </template>
                                        </div>
                                        <p class="text-[11px] text-slate-500 mt-0.5">
                                            Status dikembalikan ke <strong>Menunggu Div Head</strong> untuk tinjauan dan pengesahan ulang.
                                        </p>
                                    </div>
                                </label>
                            </template>

                        </div>
                    </div>

                    <!-- Catatan / Alasan Revisi -->
                    <div>
                        <label class="block text-xs font-bold text-slate-900 mb-1">
                            Catatan / Alasan Revisi <span class="text-slate-400 font-normal">(Disampaikan kepada penilai terkait)</span>
                        </label>
                        <textarea name="alasan" 
                                  rows="2" 
                                  x-model="alasanRevisi"
                                  placeholder="Misal: Mohon koreksi kembali skor aspek hasil kerja atau kesesuaian target..." 
                                  class="w-full p-2.5 text-xs border border-slate-300 hover:border-slate-400 focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] outline-none shadow-xs"></textarea>
                    </div>

                    <!-- Checkbox Opsi Reset Nilai -->
                    <div class="pt-2 border-t border-slate-200">
                        <label class="flex items-start gap-2.5 cursor-pointer select-none">
                            <input type="checkbox" 
                                   name="reset_nilai" 
                                   value="1" 
                                   class="mt-0.5 text-[#92302e] focus:ring-[#92302e] rounded-none border-slate-300">
                            <div class="text-[11px] text-slate-600 leading-tight">
                                <span class="font-bold text-slate-800">Kosongkan/Reset nilai pada tingkat revisi ke atas</span>
                                <p class="text-slate-500 mt-0.5">
                                    Jika tidak dicentang, nilai lama tetap disimpan sebagai draf sehingga penilai hanya perlu memperbaiki aspek yang salah tanpa mengisi dari awal.
                                </p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Modal Actions (Sticky Pinned Footer) -->
                <div class="flex items-center justify-end gap-2 px-5 py-3 border-t border-slate-200 bg-slate-50 flex-shrink-0">
                    <button type="button" 
                            @click="showModal = false" 
                            class="px-4 py-2 bg-white border border-slate-300 hover:bg-slate-100 text-slate-700 text-xs font-bold transition shadow-xs cursor-pointer">
                        Batal
                    </button>
                    <button type="submit" 
                            :disabled="selectedTargets.length === 0"
                            class="px-4 py-2 bg-[#92302e] hover:bg-[#7e2524] disabled:bg-slate-300 disabled:cursor-not-allowed text-white text-xs font-bold transition shadow-xs cursor-pointer">
                        Kembalikan Untuk Revisi
                    </button>
                </div>

            </form>
        </div>
    </div>

</div>

<script>
function revisiManager() {
    return {
        showModal: false,
        activeDoc: null,
        selectedTargets: [],
        alasanRevisi: '',
        formActionUrl: '',

        get primaryTargetLevel() {
            if (this.selectedTargets.includes('foreman')) return 'foreman';
            if (this.selectedTargets.includes('section_head')) return 'section_head';
            if (this.selectedTargets.includes('dept_head')) return 'dept_head';
            if (this.selectedTargets.includes('div_head')) return 'div_head';
            return '';
        },

        openRevisiModal(doc) {
            this.activeDoc = doc;
            this.alasanRevisi = '';
            this.selectedTargets = [];
            
            // Set default selected target based on the highest available level
            if (doc.can_revisi_div) {
                this.selectedTargets = ['div_head'];
            } else if (doc.can_revisi_dept) {
                this.selectedTargets = ['dept_head'];
            } else if (doc.can_revisi_section) {
                this.selectedTargets = ['section_head'];
            } else if (doc.can_revisi_foreman) {
                this.selectedTargets = ['foreman'];
            }

            // Set form POST url dynamically based on current role
            const baseRoute = "{{ auth()->user()->isSuperadmin() ? route('superadmin.revisiPenilaian.proses', ['id' => ':id']) : route('admin.revisiPenilaian.proses', ['id' => ':id']) }}";
            this.formActionUrl = baseRoute.replace(':id', doc.doc_id);

            this.showModal = true;
        }
    }
}
</script>
@endsection
