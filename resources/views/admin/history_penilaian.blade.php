@extends('layouts.app')

@section('title', 'History Penilaian (Audit Log) - E-Approval')

@section('content')
<div class="space-y-6">

    <!-- Header Section -->
    <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-100 text-[#92302e]">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </span>
                <h1 class="text-xl font-bold text-slate-900 tracking-tight">History Penilaian</h1>
            </div>
            <p class="text-xs text-slate-500 mt-1">
                Catatan riwayat aktivitas penilaian karya karyawan yang terhubung langsung dengan sheet <strong>audit_log</strong> di Google Spreadsheet.
            </p>
        </div>

        <!-- Tombol Sinkronisasi Spreadsheet -->
        <div class="flex items-center gap-3">
            <form action="{{ route('admin.historyPenilaian.sync') }}" method="POST" onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').classList.add('opacity-75');">
                @csrf
                <button type="submit" 
                        class="inline-flex items-center gap-2 px-4 py-2 bg-[#92302e] hover:bg-[#7a2826] text-white text-xs font-semibold rounded-lg shadow-sm transition-all cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    <span>Sinkronkan Spreadsheet</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Alert Success / Error -->
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <svg class="w-5 h-5 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="font-medium">{{ session('success') }}</span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-900">&times;</button>
        </div>
    @endif
    @if(session('error'))
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <svg class="w-5 h-5 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="font-medium">{{ session('error') }}</span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-rose-600 hover:text-rose-900">&times;</button>
        </div>
    @endif

    <!-- Stat Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <!-- Card 1: Total Log -->
        <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Total Log Aktivitas</span>
                <span class="p-2 rounded-lg bg-slate-100 text-slate-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-black text-slate-900 font-mono">{{ number_format($totalLogs) }}</span>
                <span class="text-[11px] text-slate-400">rekaman</span>
            </div>
        </div>

        <!-- Card 2: Hari Ini -->
        <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Aktivitas Hari Ini</span>
                <span class="p-2 rounded-lg bg-blue-50 text-blue-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-black text-blue-700 font-mono">{{ number_format($logsHariIni) }}</span>
                <span class="text-[11px] text-slate-400">log</span>
            </div>
        </div>

        <!-- Card 3: Revisi -->
        <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Aksi Revisi</span>
                <span class="p-2 rounded-lg bg-rose-50 text-rose-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-black text-rose-700 font-mono">{{ number_format($totalRevisi) }}</span>
                <span class="text-[11px] text-slate-400">kali revisi</span>
            </div>
        </div>

        <!-- Card 4: Approval / TTD -->
        <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Approval / TTD</span>
                <span class="p-2 rounded-lg bg-emerald-50 text-emerald-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-black text-emerald-700 font-mono">{{ number_format($totalApproval) }}</span>
                <span class="text-[11px] text-slate-400">ttd selesai</span>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-xs">
        <form method="GET" action="{{ route('admin.historyPenilaian') }}" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
            <!-- Search Keyword -->
            <div class="md:col-span-4">
                <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Cari Kata Kunci</label>
                <div class="relative">
                    <input type="text" 
                           name="search" 
                           value="{{ $search }}" 
                           placeholder="Cari aktor, NRP, target karyawan, detail..." 
                           class="w-full text-xs pl-8 pr-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-[#92302e] focus:border-[#92302e]">
                    <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
            </div>

            <!-- Filter Aksi -->
            <div class="md:col-span-3">
                <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Jenis Aksi</label>
                <select name="aksi" class="w-full text-xs px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-[#92302e] focus:border-[#92302e] bg-white">
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
                <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Dari Tanggal</label>
                <input type="date" 
                       name="tgl_mulai" 
                       value="{{ $tglMulai }}" 
                       class="w-full text-xs px-2.5 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-[#92302e] focus:border-[#92302e]">
            </div>

            <!-- Tgl Selesai -->
            <div class="md:col-span-2">
                <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Sampai Tanggal</label>
                <input type="date" 
                       name="tgl_selesai" 
                       value="{{ $tglSelesai }}" 
                       class="w-full text-xs px-2.5 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-[#92302e] focus:border-[#92302e]">
            </div>

            <!-- Action Buttons -->
            <div class="md:col-span-1 flex items-center gap-1.5">
                <button type="submit" 
                        title="Terapkan Filter"
                        class="w-full px-3 py-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold rounded-lg shadow-sm transition-all cursor-pointer text-center">
                    Filter
                </button>
                @if($search || $aksiFilter || $tglMulai || $tglSelesai)
                    <a href="{{ route('admin.historyPenilaian') }}" 
                       title="Reset Filter"
                       class="p-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg transition-all flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Table Audit Log -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-xs overflow-hidden">
        <div class="p-4 border-b border-slate-200 flex items-center justify-between">
            <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wider">
                Daftar Riwayat Aktivitas Penilaian ({{ $logs->total() }} Data)
            </h2>
            <span class="text-[11px] text-slate-500 font-mono">
                Menampilkan halaman {{ $logs->currentPage() }} dari {{ $logs->lastPage() }}
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider text-[10.5px]">
                        <th class="py-3 px-3 text-center w-12">#</th>
                        <th class="py-3 px-3 w-40">Waktu</th>
                        <th class="py-3 px-3 w-48">Aktor / Pengguna</th>
                        <th class="py-3 px-3 w-36">Aksi</th>
                        <th class="py-3 px-3 w-56">Karyawan Terkait</th>
                        <th class="py-3 px-4">Detail Riwayat Aktivitas</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($logs as $index => $log)
                        @php
                            $aksiLower = strtolower($log->aksi ?? '');
                            $isRevisi = str_contains($aksiLower, 'revisi');
                            $isTtd = str_contains($aksiLower, 'ttd') || str_contains($aksiLower, 'tanda_tangan') || str_contains($aksiLower, 'approve');
                            $isDraft = str_contains($aksiLower, 'draft');

                            $badgeClass = match(true) {
                                $isRevisi => 'bg-rose-100 text-rose-800 border-rose-200',
                                $isTtd => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                $isDraft => 'bg-blue-100 text-blue-800 border-blue-200',
                                default => 'bg-slate-100 text-slate-800 border-slate-200',
                            };
                        @endphp
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="py-3 px-3 text-center font-mono text-slate-400 text-[11px]">
                                {{ $logs->firstItem() + $index }}
                            </td>
                            <td class="py-3 px-3 font-mono text-[11px] text-slate-700 whitespace-nowrap">
                                <div class="font-bold text-slate-900">
                                    {{ \Carbon\Carbon::parse($log->waktu)->format('d M Y') }}
                                </div>
                                <div class="text-[10px] text-slate-500">
                                    {{ \Carbon\Carbon::parse($log->waktu)->format('H:i:s') }} WIB
                                </div>
                            </td>
                            <td class="py-3 px-3">
                                <div class="font-bold text-slate-900 text-xs">
                                    {{ $log->user_nama ?: ('User #' . ($log->user_id ?? '-')) }}
                                </div>
                                <div class="flex items-center gap-1.5 mt-0.5">
                                    @if($log->user_nrp)
                                        <span class="font-mono text-[10px] text-slate-500">NRP: {{ $log->user_nrp }}</span>
                                    @endif
                                    @if($log->user_role)
                                        <span class="px-1.5 py-0.2 rounded text-[9.5px] font-semibold bg-slate-100 text-slate-600 border border-slate-200 uppercase">
                                            {{ str_replace('_', ' ', $log->user_role) }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="py-3 px-3 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10.5px] font-semibold border {{ $badgeClass }}">
                                    {{ ucwords(str_replace('_', ' ', $log->aksi)) }}
                                </span>
                            </td>
                            <td class="py-3 px-3">
                                @if($log->karyawan_nama || $log->karyawan_nrp)
                                    <div class="font-bold text-slate-900 text-xs">
                                        {{ $log->karyawan_nama ?: '-' }}
                                    </div>
                                    <div class="flex items-center gap-1.5 text-[10.5px] text-slate-500 font-mono mt-0.5">
                                        <span>NRP: {{ $log->karyawan_nrp }}</span>
                                        @if($log->doc_kode_form)
                                            <span>&bull;</span>
                                            <span class="font-bold text-[#92302e]">{{ $log->doc_kode_form }}</span>
                                        @endif
                                    </div>
                                    <div class="text-[10px] text-slate-400">
                                        {{ $log->karyawan_departemen }}
                                    </div>
                                @elseif($log->dokumen_id)
                                    <span class="font-mono text-[11px] text-slate-500">Dokumen #{{ $log->dokumen_id }}</span>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-slate-700 leading-relaxed text-[11.5px]">
                                {{ $log->detail }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-slate-400">
                                <svg class="w-10 h-10 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                </svg>
                                <p class="text-xs font-semibold text-slate-600">Belum ada riwayat aktivitas yang tercatat</p>
                                <p class="text-[11px] text-slate-400 mt-0.5">Coba gunakan tombol "Sinkronkan Spreadsheet" di atas untuk menarik data terbaru.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($logs->hasPages())
            <div class="p-4 border-t border-slate-200 bg-slate-50">
                {{ $logs->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
