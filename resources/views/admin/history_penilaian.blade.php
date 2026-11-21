@extends('layouts.app')

@section('title', 'History Penilaian - PerformanceAppraisal')

@section('content')
<div class="space-y-6">

    <!-- Header Section (Musashi Red Banner, Solid & Kotak Tanpa Lengkungan) -->
    <div class="bg-gradient-to-r from-[#962226] via-[#92302e] to-[#781b1f] text-white rounded-none p-5 sm:p-6 border border-[#781b1f] shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight">
                History Penilaian
            </h1>
            <p class="text-xs sm:text-sm text-red-100 font-medium mt-1">
                Catatan riwayat aktivitas penilaian karyawan yang terhubung langsung dengan Google Spreadsheet.
            </p>
        </div>

        <!-- Tombol Aksi Header: Sinkronisasi & Kosongkan Log -->
        <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap shrink-0">
            <!-- Sinkronkan Spreadsheet -->
            <form action="{{ request()->routeIs('superadmin.*') ? route('superadmin.historyPenilaian.sync') : route('admin.historyPenilaian.sync') }}" method="POST" onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').classList.add('opacity-75');" class="m-0">
                @csrf
                <button type="submit" 
                        class="inline-flex items-center gap-2 px-4 py-2.5 bg-white text-[#92302e] hover:bg-slate-100 text-xs font-bold rounded-none shadow-xs transition cursor-pointer">
                    <svg class="w-4 h-4 text-[#92302e]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    <span>Sinkronkan Spreadsheet</span>
                </button>
            </form>

            <!-- Kosongkan Seluruh Riwayat Log (Clear All Logs) -->
            <form action="{{ request()->routeIs('superadmin.*') ? route('superadmin.historyPenilaian.clearAll') : route('admin.historyPenilaian.clearAll') }}" 
                  method="POST" 
                  onsubmit="return confirm('PERINGATAN: Apakah Anda yakin ingin mengosongkan SELURUH catatan riwayat aktivitas penilaian?\n\nSeluruh data pada Google Spreadsheet juga akan ikut dihapus permanen.');" 
                  class="m-0">
                @csrf
                <button type="submit" 
                        class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#781b1f] hover:bg-[#601418] text-white border border-red-300/30 text-xs font-bold rounded-none shadow-xs transition cursor-pointer"
                        title="Kosongkan seluruh riwayat log aktivitas (lokal & spreadsheet)">
                    <svg class="w-4 h-4 text-red-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    <span>Kosongkan Semua Log</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Alert Success / Error (Kotak Tanpa Lengkungan) -->
    @if(session('success'))
        <div class="p-3.5 rounded-none bg-emerald-50 border border-emerald-300 text-emerald-900 text-xs flex items-center justify-between shadow-2xs">
            <div class="flex items-center gap-2.5">
                <svg class="w-4 h-4 text-emerald-700 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="font-bold">{{ session('success') }}</span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-emerald-700 hover:text-emerald-950 font-bold text-sm leading-none cursor-pointer">&times;</button>
        </div>
    @endif
    @if(session('error'))
        <div class="p-3.5 rounded-none bg-rose-50 border border-rose-300 text-rose-900 text-xs flex items-center justify-between shadow-2xs">
            <div class="flex items-center gap-2.5">
                <svg class="w-4 h-4 text-rose-700 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="font-bold">{{ session('error') }}</span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-rose-700 hover:text-rose-950 font-bold text-sm leading-none cursor-pointer">&times;</button>
        </div>
    @endif

    <!-- Stat Cards (Kotak Tanpa Lengkungan - Sharp Musashi Cards) -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3.5 sm:gap-4">
        <!-- Card 1: Total Log -->
        <div class="bg-white border border-slate-300 rounded-none p-4 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-[10px] sm:text-[11px] font-black text-slate-600 uppercase tracking-wider">Total Log Aktivitas</span>
                <span class="p-2 rounded-none bg-slate-100 text-slate-700 border border-slate-200">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-1.5">
                <span class="text-2xl sm:text-3xl font-black text-slate-900 font-mono">{{ number_format($totalLogs) }}</span>
                <span class="text-xs font-semibold text-slate-500">rekaman</span>
            </div>
            <div class="text-[10px] text-slate-500 mt-1 font-medium">Seluruh jejak audit sistem</div>
        </div>

        <!-- Card 2: Hari Ini -->
        <div class="bg-white border border-slate-300 rounded-none p-4 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-[10px] sm:text-[11px] font-black text-blue-800 uppercase tracking-wider">Aktivitas Hari Ini</span>
                <span class="p-2 rounded-none bg-blue-50 text-blue-700 border border-blue-200">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-1.5">
                <span class="text-2xl sm:text-3xl font-black text-blue-700 font-mono">{{ number_format($logsHariIni) }}</span>
                <span class="text-xs font-semibold text-slate-500">log</span>
            </div>
            <div class="text-[10px] text-slate-500 mt-1 font-medium">{{ \Carbon\Carbon::today()->format('d M Y') }}</div>
        </div>

        <!-- Card 3: Revisi -->
        <div class="bg-white border border-slate-300 rounded-none p-4 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-[10px] sm:text-[11px] font-black text-[#92302e] uppercase tracking-wider">Aksi Revisi</span>
                <span class="p-2 rounded-none bg-rose-50 text-[#92302e] border border-rose-200">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-1.5">
                <span class="text-2xl sm:text-3xl font-black text-[#92302e] font-mono">{{ number_format($totalRevisi) }}</span>
                <span class="text-xs font-semibold text-slate-500">kali revisi</span>
            </div>
            <div class="text-[10px] text-slate-500 mt-1 font-medium">Permintaan pengembalian form</div>
        </div>

        <!-- Card 4: Approval / TTD -->
        <div class="bg-white border border-slate-300 rounded-none p-4 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-[10px] sm:text-[11px] font-black text-emerald-800 uppercase tracking-wider">Approval / TTD</span>
                <span class="p-2 rounded-none bg-emerald-50 text-emerald-700 border border-emerald-200">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-1.5">
                <span class="text-2xl sm:text-3xl font-black text-emerald-700 font-mono">{{ number_format($totalApproval) }}</span>
                <span class="text-xs font-semibold text-slate-500">ttd selesai</span>
            </div>
            <div class="text-[10px] text-slate-500 mt-1 font-medium">Validasi tanda tangan digital</div>
        </div>
    </div>

    <!-- Filter Bar (Kotak Tanpa Lengkungan - Sharp Controls) -->
    <div class="bg-white border border-slate-300 rounded-none p-4 shadow-2xs">
        <form method="GET" action="{{ request()->routeIs('superadmin.*') ? route('superadmin.historyPenilaian') : route('admin.historyPenilaian') }}" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
            <!-- Search Keyword -->
            <div class="md:col-span-4">
                <label class="block text-[10px] font-black text-slate-600 uppercase tracking-wider mb-1">Cari Kata Kunci</label>
                <div class="relative">
                    <input type="text" 
                           name="search" 
                           value="{{ $search }}" 
                           placeholder="Cari aktor, NRP, target karyawan, detail..." 
                           class="w-full text-xs font-semibold text-slate-800 placeholder-slate-400 pl-8 pr-3 py-2 border border-slate-300 hover:border-slate-400 rounded-none bg-white focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-xs">
                    <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2.5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
            </div>

            <!-- Filter Aksi -->
            <div class="md:col-span-3">
                <label class="block text-[10px] font-black text-slate-600 uppercase tracking-wider mb-1">Jenis Aksi</label>
                <select name="aksi" class="w-full text-xs font-semibold text-slate-800 px-3 py-2 border border-slate-300 hover:border-slate-400 rounded-none bg-white focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-xs">
                    <option value="">Semua Jenis Aksi</option>
                    @foreach($daftarAksi as $a)
                        <option value="{{ $a->aksi }}" {{ $aksiFilter === $a->aksi ? 'selected' : '' }}>
                            {{ ucwords(str_replace('_', ' ', $a->aksi)) }} ({{ $a->total }})
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Tgl Mulai -->
            <div class="md:col-span-2">
                <label class="block text-[10px] font-black text-slate-600 uppercase tracking-wider mb-1">Dari Tanggal</label>
                <input type="date" 
                       name="tgl_mulai" 
                       value="{{ $tglMulai }}" 
                       class="w-full text-xs font-semibold text-slate-800 px-2.5 py-2 border border-slate-300 hover:border-slate-400 rounded-none bg-white focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-xs">
            </div>

            <!-- Tgl Selesai -->
            <div class="md:col-span-2">
                <label class="block text-[10px] font-black text-slate-600 uppercase tracking-wider mb-1">Sampai Tanggal</label>
                <input type="date" 
                       name="tgl_selesai" 
                       value="{{ $tglSelesai }}" 
                       class="w-full text-xs font-semibold text-slate-800 px-2.5 py-2 border border-slate-300 hover:border-slate-400 rounded-none bg-white focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-xs">
            </div>

            <!-- Action Buttons -->
            <div class="md:col-span-1 flex items-center gap-1.5">
                <button type="submit" 
                        title="Terapkan Filter"
                        class="w-full px-3 py-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold rounded-none shadow-xs transition cursor-pointer text-center">
                    Filter
                </button>
                @if($search || $aksiFilter || $tglMulai || $tglSelesai)
                    <a href="{{ request()->routeIs('superadmin.*') ? route('superadmin.historyPenilaian') : route('admin.historyPenilaian') }}" 
                       title="Reset Filter"
                       class="px-2.5 py-2 border border-rose-300 bg-rose-50 hover:bg-rose-100 text-[#92302e] text-xs font-bold rounded-none transition flex items-center justify-center shadow-2xs">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Table Audit Log (Kotak Tajam, Border Slate-300, Konsisten dengan Tabel Lain) -->
    <div class="bg-white border border-slate-300 rounded-none shadow-sm overflow-hidden">
        <div class="p-3.5 bg-slate-50 border-b border-slate-300 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <h2 class="text-xs font-black text-slate-800 uppercase tracking-wider flex items-center gap-2">
                    <span class="w-2 h-2 bg-[#92302e] inline-block"></span>
                    <span>Daftar Riwayat Aktivitas Penilaian ({{ number_format($logs->total()) }} Data)</span>
                </h2>
                <!-- Tombol Hapus Terpilih (Bulk Delete) -->
                <button type="button" 
                        id="btnBulkDelete" 
                        onclick="submitBulkDelete()" 
                        class="hidden items-center gap-1.5 px-3 py-1 bg-[#92302e] hover:bg-[#7e2524] text-white text-[11px] font-bold rounded-none shadow-xs transition cursor-pointer">
                    <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    <span>Hapus Terpilih (<span id="selectedCount">0</span>)</span>
                </button>
            </div>
            <span class="text-[11px] text-slate-700 font-mono font-bold bg-white px-3 py-1 border border-slate-300 shadow-2xs rounded-none self-start sm:self-auto">
                Halaman {{ $logs->currentPage() }} dari {{ $logs->lastPage() }}
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse border border-slate-300 text-xs">
                <thead>
                    <tr class="bg-slate-100 text-slate-700 font-bold text-[11px] uppercase tracking-wider">
                        <th class="py-3 px-2 border border-slate-300 bg-slate-100 text-center w-10 whitespace-nowrap">
                            <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)" class="rounded-none border-slate-300 text-[#92302e] focus:ring-[#92302e] cursor-pointer" title="Pilih Semua di Halaman Ini">
                        </th>
                        <th class="py-3 px-3 border border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-12 whitespace-nowrap">No</th>
                        <th class="py-3 px-3.5 border border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-36 whitespace-nowrap">Waktu</th>
                        <th class="py-3 px-3.5 border border-slate-300 bg-slate-100 font-bold text-slate-700 w-52 whitespace-nowrap">Aktor / Pengguna</th>
                        <th class="py-3 px-3.5 border border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-36 whitespace-nowrap">Aksi</th>
                        <th class="py-3 px-3.5 border border-slate-300 bg-slate-100 font-bold text-slate-700 w-60 whitespace-nowrap">Karyawan Terkait</th>
                        <th class="py-3 px-4 border border-slate-300 bg-slate-100 font-bold text-slate-700 min-w-[280px]">Detail Riwayat Aktivitas</th>
                        <th class="py-3 px-3 border border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-20 whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($logs as $index => $log)
                        @php
                            $aksiLower = strtolower($log->aksi ?? '');
                            $isRevisi = str_contains($aksiLower, 'revisi');
                            $isTtd = str_contains($aksiLower, 'ttd') || str_contains($aksiLower, 'tanda_tangan') || str_contains($aksiLower, 'approve') || str_contains($aksiLower, 'selesai');
                            $isDraft = str_contains($aksiLower, 'draft') || str_contains($aksiLower, 'simpan');

                            $badgeClass = match(true) {
                                $isRevisi => 'bg-rose-100 text-rose-900 border border-rose-300',
                                $isTtd => 'bg-emerald-100 text-emerald-900 border border-emerald-300',
                                $isDraft => 'bg-blue-100 text-blue-900 border border-blue-300',
                                default => 'bg-slate-100 text-slate-800 border border-slate-300',
                            };
                        @endphp
                        <tr class="hover:bg-slate-50 transition-colors">
                            <!-- Checkbox -->
                            <td class="py-2.5 px-2 border border-slate-300 text-center whitespace-nowrap">
                                <input type="checkbox" name="log_ids[]" value="{{ $log->id }}" onchange="updateSelectedCount()" class="log-checkbox rounded-none border-slate-300 text-[#92302e] focus:ring-[#92302e] cursor-pointer">
                            </td>

                            <!-- No -->
                            <td class="py-2.5 px-3 border border-slate-300 text-center font-bold text-slate-600 font-mono text-xs">
                                {{ $logs->firstItem() + $index }}
                            </td>

                            <!-- Waktu -->
                            <td class="py-2.5 px-3.5 border border-slate-300 text-center font-mono text-[11px] text-slate-700 whitespace-nowrap">
                                <div class="font-bold text-slate-900">
                                    {{ \Carbon\Carbon::parse($log->waktu)->format('d M Y') }}
                                </div>
                                <div class="text-[10px] text-slate-500 font-medium mt-0.5">
                                    {{ \Carbon\Carbon::parse($log->waktu)->format('H:i:s') }} WIB
                                </div>
                            </td>

                            <!-- Aktor / Pengguna -->
                            <td class="py-2.5 px-3.5 border border-slate-300">
                                <div class="font-bold text-slate-900 text-xs">
                                    {{ $log->user_nama ?: ('User #' . ($log->user_id ?? '-')) }}
                                </div>
                                <div class="flex items-center gap-1.5 mt-1 flex-wrap">
                                    @if($log->user_nrp)
                                        <span class="font-mono text-[10px] text-slate-600 font-semibold">NRP: {{ $log->user_nrp }}</span>
                                    @endif
                                    @if($log->user_role)
                                        <span class="px-1.5 py-0.2 rounded-none text-[9px] font-bold bg-slate-100 text-slate-700 border border-slate-300 uppercase">
                                            {{ str_replace('_', ' ', $log->user_role) }}
                                        </span>
                                    @endif
                                </div>
                            </td>

                            <!-- Aksi -->
                            <td class="py-2.5 px-3.5 border border-slate-300 text-center whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-none text-[10.5px] font-bold uppercase tracking-wider {{ $badgeClass }}">
                                    {{ ucwords(str_replace('_', ' ', $log->aksi)) }}
                                </span>
                            </td>

                            <!-- Karyawan Terkait -->
                            <td class="py-2.5 px-3.5 border border-slate-300">
                                @if($log->karyawan_nama || $log->karyawan_nrp)
                                    <div class="font-bold text-slate-900 text-xs">
                                        {{ $log->karyawan_nama ?: '-' }}
                                    </div>
                                    <div class="flex items-center gap-1.5 text-[10.5px] text-slate-600 font-mono mt-0.5">
                                        <span class="font-semibold">NRP: {{ $log->karyawan_nrp }}</span>
                                        @if($log->doc_kode_form)
                                             <span class="text-slate-300">&bull;</span>
                                            <span class="px-1.5 py-0.2 rounded-none text-[9.5px] font-mono font-bold bg-[#fbe8e8] text-[#92302e] border border-rose-200">
                                                {{ $log->doc_kode_form }}
                                            </span>
                                        @endif
                                    </div>
                                    @if($log->karyawan_departemen)
                                        <div class="text-[10px] text-slate-500 font-medium mt-0.5">
                                            {{ $log->karyawan_departemen }}
                                        </div>
                                    @endif
                                @elseif($log->dokumen_id)
                                    <span class="font-mono text-[11px] font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 border border-slate-200 rounded-none">
                                        Dokumen #{{ $log->dokumen_id }}
                                    </span>
                                @else
                                    <span class="text-slate-400 font-mono">-</span>
                                @endif
                            </td>

                            <!-- Detail Riwayat Aktivitas -->
                            <td class="py-2.5 px-4 border border-slate-300 text-slate-800 leading-relaxed text-xs">
                                {{ $log->detail }}
                            </td>

                            <!-- Aksi (Tombol Hapus Baris) -->
                            <td class="py-2.5 px-3 border border-slate-300 text-center whitespace-nowrap">
                                <form action="{{ request()->routeIs('superadmin.*') ? route('superadmin.historyPenilaian.destroy', $log->id) : route('admin.historyPenilaian.destroy', $log->id) }}"
                                      method="POST"
                                      onsubmit="return confirm('Hapus catatan riwayat aktivitas ini? Data pada Google Spreadsheet juga akan ikut terhapus.');"
                                      class="inline-block m-0">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="p-1.5 border border-rose-300 bg-rose-50 hover:bg-rose-100 text-[#92302e] rounded-none transition shadow-2xs inline-flex items-center justify-center cursor-pointer"
                                            title="Hapus catatan riwayat ini (lokal & spreadsheet)">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-slate-500 border border-slate-300 bg-slate-50">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <svg class="w-9 h-9 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                    </svg>
                                    <p class="text-xs font-bold text-slate-700">Belum ada riwayat aktivitas yang tercatat</p>
                                    <p class="text-[11px] text-slate-500">Gunakan tombol "Sinkronkan Spreadsheet" di bagian atas untuk memperbarui data langsung dari Google Spreadsheet.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination (Kotak Tanpa Lengkungan) -->
        @if($logs->hasPages())
            <div class="p-3.5 border-t border-slate-300 bg-slate-50 rounded-none">
                {{ $logs->links() }}
            </div>
        @endif
    </div>

    <!-- Form Tersembunyi untuk Hapus Terpilih (Bulk Delete) -->
    <form id="bulkDeleteForm" action="{{ request()->routeIs('superadmin.*') ? route('superadmin.historyPenilaian.bulkDelete') : route('admin.historyPenilaian.bulkDelete') }}" method="POST" class="hidden">
        @csrf
        <div id="bulkDeleteInputs"></div>
    </form>

</div>

<script>
    function toggleSelectAll(master) {
        const checkboxes = document.querySelectorAll('.log-checkbox');
        checkboxes.forEach(cb => cb.checked = master.checked);
        updateSelectedCount();
    }

    function updateSelectedCount() {
        const checked = document.querySelectorAll('.log-checkbox:checked');
        const count = checked.length;
        const btn = document.getElementById('btnBulkDelete');
        const countSpan = document.getElementById('selectedCount');
        const master = document.getElementById('selectAllCheckbox');
        const allCheckboxes = document.querySelectorAll('.log-checkbox');

        if (countSpan) countSpan.textContent = count;
        if (btn) {
            if (count > 0) {
                btn.classList.remove('hidden');
                btn.classList.add('inline-flex');
            } else {
                btn.classList.add('hidden');
                btn.classList.remove('inline-flex');
            }
        }
        if (master && allCheckboxes.length > 0) {
            master.checked = (count === allCheckboxes.length);
        }
    }

    function submitBulkDelete() {
        const checked = document.querySelectorAll('.log-checkbox:checked');
        if (checked.length === 0) {
            alert('Pilih setidaknya satu riwayat aktivitas untuk dihapus.');
            return;
        }

        if (!confirm(`Hapus ${checked.length} catatan riwayat aktivitas terpilih?\n\nCatatan terkait pada Google Spreadsheet juga akan ikut terhapus.`)) {
            return;
        }

        const container = document.getElementById('bulkDeleteInputs');
        container.innerHTML = '';
        checked.forEach(cb => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = cb.value;
            container.appendChild(input);
        });

        document.getElementById('bulkDeleteForm').submit();
    }
</script>
@endsection
