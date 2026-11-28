@extends('layouts.app', ['title' => 'Target & Kuota Divisi - Admin & Superadmin'])

@section('content')
<div class="space-y-6" x-data="{
    targetS: {{ $globalTarget['S'] ?? 12 }},
    targetA: {{ $globalTarget['A'] ?? 36 }},
    targetB: {{ $globalTarget['B'] ?? 36 }},
    targetC: {{ $globalTarget['C'] ?? 24 }},
    targetK: {{ $globalTarget['K'] ?? 13 }},
    get totalTarget() {
        return (parseInt(this.targetS) || 0) + 
               (parseInt(this.targetA) || 0) + 
               (parseInt(this.targetB) || 0) + 
               (parseInt(this.targetC) || 0) + 
               (parseInt(this.targetK) || 0);
    }
}">

    <!-- 1. Header Banner Musashi Red (Sharp & Clean) -->
    <div class="bg-gradient-to-r from-[#962226] via-[#92302e] to-[#781b1f] text-white rounded-none p-5 sm:p-6 border border-[#781b1f] shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight">
                Target &amp; Kuota Penilaian Divisi
            </h1>
            <p class="text-xs text-red-100 font-medium mt-1 max-w-3xl leading-relaxed">
                Atur target kuota grade (S, A, B, C, K) untuk seluruh karyawan pemakai <strong>Form 07-006</strong> di seluruh divisi dan tampil di dashboard Division Head.
            </p>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('admin.karyawan') }}" 
               class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-none bg-white/10 hover:bg-white/20 text-white text-xs font-bold border border-white/30 transition shadow-xs">
                <svg class="w-4 h-4 text-red-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
                <span>Master Karyawan</span>
            </a>
            <a href="{{ route('dashboard') }}" 
               class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-none bg-white hover:bg-slate-100 text-[#92302e] text-xs font-black border border-white transition shadow-xs">
                <svg class="w-4 h-4 text-[#92302e]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
                <span>Dashboard</span>
            </a>
        </div>
    </div>

    <!-- Alert Notifications -->
    @if(session('success'))
        <div class="p-4 bg-emerald-50 border-l-4 border-emerald-600 text-emerald-900 rounded-none text-xs font-medium flex items-center justify-between gap-3 shadow-xs">
            <div class="flex items-center gap-2">
                <span class="text-base">✓</span>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="text-emerald-700 hover:text-emerald-900 font-bold">&times;</button>
        </div>
    @endif

    @if(session('error'))
        <div class="p-4 bg-rose-50 border-l-4 border-rose-600 text-rose-900 rounded-none text-xs font-medium flex items-center justify-between gap-3 shadow-xs">
            <div class="flex items-center gap-2">
                <span class="text-base">⚠️</span>
                <span>{{ session('error') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="text-rose-700 hover:text-rose-900 font-bold">&times;</button>
        </div>
    @endif

    <!-- 3. KPI Top Stat Cards (Clean Department Style) -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <!-- Card 1: Total Karyawan Form 07-006 -->
        <div class="bg-white rounded-none p-3.5 border border-slate-300 shadow-2xs">
            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Populasi 07-006</span>
            <div class="mt-1 flex items-baseline gap-1.5">
                <span class="text-2xl font-black text-slate-900">{{ $totalKaryawan07006 }}</span>
                <span class="text-xs font-medium text-slate-500">karyawan</span>
            </div>
            <span class="text-[10px] text-slate-500 mt-0.5 block truncate">Karyawan Seluruh Divisi</span>
        </div>

        <!-- Card 2: Total Target Kuota -->
        <div class="bg-white rounded-none p-3.5 border border-slate-300 shadow-2xs">
            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Target Kuota Admin</span>
            <div class="mt-1 flex items-baseline gap-1.5">
                <span class="text-2xl font-black text-[#92302e]" x-text="totalTarget">{{ $globalTarget['total'] }}</span>
                <span class="text-xs font-medium text-slate-500">orang</span>
            </div>
            <span class="text-[10px] text-slate-500 mt-0.5 block truncate">Alokasi Target Predikat</span>
        </div>

        <!-- Card 3: Sudah Dinilai -->
        <div class="bg-white rounded-none p-3.5 border border-slate-300 shadow-2xs">
            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Realisasi Nilai</span>
            <div class="mt-1 flex items-baseline gap-1.5">
                <span class="text-2xl font-black text-emerald-700">{{ $totalActual }}</span>
                <span class="text-xs font-medium text-slate-500">dinilai</span>
            </div>
            <span class="text-[10px] text-slate-500 mt-0.5 block truncate">Karyawan Sudah Dinilai</span>
        </div>

        <!-- Card 4: Total Divisi Terlibat -->
        <div class="bg-white rounded-none p-3.5 border border-slate-300 shadow-2xs">
            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Total Divisi</span>
            <div class="mt-1 flex items-baseline gap-1.5">
                <span class="text-2xl font-black text-slate-800">{{ $totalDivisi }}</span>
                <span class="text-xs font-medium text-slate-500">divisi</span>
            </div>
            <span class="text-[10px] text-slate-500 mt-0.5 block truncate">Divisi Pemakai 07-006</span>
        </div>
    </div>

    <!-- 4. Form Card: Atur Target Kuota Divisi (S, A, B, C, K) -->
    <div class="bg-white rounded-none border border-slate-300 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-200 bg-slate-50/70 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                    <span>Pengaturan Target Kuota Divisi (Form 07-006)</span>
                    <span class="text-[10px] font-mono px-2 py-0.5 rounded-none bg-emerald-100 text-emerald-800 font-bold border border-emerald-300 uppercase tracking-wider">
                        Bebas Disesuaikan
                    </span>
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">
                    Tentukan jumlah target kuota orang untuk masing-masing kategori nilai S, A, B, C, K bagi seluruh karyawan berpenilai Form 07-006.
                </p>
            </div>
            <div class="text-xs font-mono font-bold text-slate-600 bg-white px-2.5 py-1 rounded-none border border-slate-300">
                Terakhir Update: <span id="realtimeClock">{{ $terakhirUpdate }}</span>
            </div>
        </div>

        <form action="{{ route(request()->is('superadmin*') ? 'superadmin.simpanTargetDivisi' : 'admin.simpanTargetDivisi') }}" method="POST" class="p-5">
            @csrf

            <div class="overflow-x-auto border border-slate-300">
                <table class="w-full text-left text-xs border-collapse whitespace-nowrap">
                    <thead>
                        <tr class="border-b border-slate-300 text-slate-700 font-bold bg-slate-100 text-[11px] uppercase tracking-wider">
                            <th class="py-3 px-4 border-r border-slate-300">Kategori Nilai</th>
                            <th class="py-3 px-4 text-center border-r border-slate-300">Realisasi Saat Ini</th>
                            <th class="py-3 px-4 text-center border-r border-slate-300">Target Kuota Admin (Orang)</th>
                            <th class="py-3 px-4 text-center">Panduan Proposional</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        <!-- Grade S -->
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="py-2.5 px-4 font-bold border-r border-slate-200">
                                <div class="flex items-center gap-2">
                                    <span class="w-7 h-7 rounded-none bg-purple-100 text-purple-900 border border-purple-300 flex items-center justify-center font-black text-xs font-mono">
                                        S
                                    </span>
                                    <span class="font-bold text-slate-900">Grade S</span>
                                </div>
                            </td>
                            <td class="py-2.5 px-4 text-purple-700 font-black text-center border-r border-slate-200 font-mono text-sm">
                                {{ $totalRow ? (int)$totalRow->actual_s : 0 }} <span class="text-[10px] text-purple-900/60 font-normal">Orang</span>
                            </td>
                            <td class="py-2.5 px-4 text-center border-r border-slate-200">
                                <div class="inline-flex items-center gap-2">
                                    <input type="number" 
                                           name="target_s" 
                                           min="0" 
                                           x-model.number="targetS"
                                           class="w-24 px-3 py-1.5 text-center font-mono font-bold text-sm border border-slate-300 focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] rounded-none bg-slate-50 focus:bg-white"
                                           required>
                                    <span class="text-xs text-slate-500 font-medium">Orang</span>
                                </div>
                            </td>
                            <td class="py-2.5 px-4 text-center text-slate-500 text-[11px] font-mono">
                                ~10% (~{{ (int)round($totalKaryawan07006 * 0.10) }} Orang)
                            </td>
                        </tr>

                        <!-- Grade A -->
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="py-2.5 px-4 font-bold border-r border-slate-200">
                                <div class="flex items-center gap-2">
                                    <span class="w-7 h-7 rounded-none bg-emerald-100 text-emerald-900 border border-emerald-300 flex items-center justify-center font-black text-xs font-mono">
                                        A
                                    </span>
                                    <span class="font-bold text-slate-900">Grade A</span>
                                </div>
                            </td>
                            <td class="py-2.5 px-4 text-emerald-700 font-black text-center border-r border-slate-200 font-mono text-sm">
                                {{ $totalRow ? (int)$totalRow->actual_a : 0 }} <span class="text-[10px] text-emerald-900/60 font-normal">Orang</span>
                            </td>
                            <td class="py-2.5 px-4 text-center border-r border-slate-200">
                                <div class="inline-flex items-center gap-2">
                                    <input type="number" 
                                           name="target_a" 
                                           min="0" 
                                           x-model.number="targetA"
                                           class="w-24 px-3 py-1.5 text-center font-mono font-bold text-sm border border-slate-300 focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] rounded-none bg-slate-50 focus:bg-white"
                                           required>
                                    <span class="text-xs text-slate-500 font-medium">Orang</span>
                                </div>
                            </td>
                            <td class="py-2.5 px-4 text-center text-slate-500 text-[11px] font-mono">
                                ~30% (~{{ (int)round($totalKaryawan07006 * 0.30) }} Orang)
                            </td>
                        </tr>

                        <!-- Grade B -->
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="py-2.5 px-4 font-bold border-r border-slate-200">
                                <div class="flex items-center gap-2">
                                    <span class="w-7 h-7 rounded-none bg-blue-100 text-blue-900 border border-blue-300 flex items-center justify-center font-black text-xs font-mono">
                                        B
                                    </span>
                                    <span class="font-bold text-slate-900">Grade B</span>
                                </div>
                            </td>
                            <td class="py-2.5 px-4 text-blue-700 font-black text-center border-r border-slate-200 font-mono text-sm">
                                {{ $totalRow ? (int)$totalRow->actual_b : 0 }} <span class="text-[10px] text-blue-900/60 font-normal">Orang</span>
                            </td>
                            <td class="py-2.5 px-4 text-center border-r border-slate-200">
                                <div class="inline-flex items-center gap-2">
                                    <input type="number" 
                                           name="target_b" 
                                           min="0" 
                                           x-model.number="targetB"
                                           class="w-24 px-3 py-1.5 text-center font-mono font-bold text-sm border border-slate-300 focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] rounded-none bg-slate-50 focus:bg-white"
                                           required>
                                    <span class="text-xs text-slate-500 font-medium">Orang</span>
                                </div>
                            </td>
                            <td class="py-2.5 px-4 text-center text-slate-500 text-[11px] font-mono">
                                ~30% (~{{ (int)round($totalKaryawan07006 * 0.30) }} Orang)
                            </td>
                        </tr>

                        <!-- Grade C -->
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="py-2.5 px-4 font-bold border-r border-slate-200">
                                <div class="flex items-center gap-2">
                                    <span class="w-7 h-7 rounded-none bg-amber-100 text-amber-900 border border-amber-300 flex items-center justify-center font-black text-xs font-mono">
                                        C
                                    </span>
                                    <span class="font-bold text-slate-900">Grade C</span>
                                </div>
                            </td>
                            <td class="py-2.5 px-4 text-amber-700 font-black text-center border-r border-slate-200 font-mono text-sm">
                                {{ $totalRow ? (int)$totalRow->actual_c : 0 }} <span class="text-[10px] text-amber-900/60 font-normal">Orang</span>
                            </td>
                            <td class="py-2.5 px-4 text-center border-r border-slate-200">
                                <div class="inline-flex items-center gap-2">
                                    <input type="number" 
                                           name="target_c" 
                                           min="0" 
                                           x-model.number="targetC"
                                           class="w-24 px-3 py-1.5 text-center font-mono font-bold text-sm border border-slate-300 focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] rounded-none bg-slate-50 focus:bg-white"
                                           required>
                                    <span class="text-xs text-slate-500 font-medium">Orang</span>
                                </div>
                            </td>
                            <td class="py-2.5 px-4 text-center text-slate-500 text-[11px] font-mono">
                                ~20% (~{{ (int)round($totalKaryawan07006 * 0.20) }} Orang)
                            </td>
                        </tr>

                        <!-- Grade K -->
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="py-2.5 px-4 font-bold border-r border-slate-200">
                                <div class="flex items-center gap-2">
                                    <span class="w-7 h-7 rounded-none bg-rose-100 text-rose-900 border border-rose-300 flex items-center justify-center font-black text-xs font-mono">
                                        K
                                    </span>
                                    <span class="font-bold text-slate-900">Grade K</span>
                                </div>
                            </td>
                            <td class="py-2.5 px-4 text-rose-700 font-black text-center border-r border-slate-200 font-mono text-sm">
                                {{ $totalRow ? (int)$totalRow->actual_k : 0 }} <span class="text-[10px] text-rose-900/60 font-normal">Orang</span>
                            </td>
                            <td class="py-2.5 px-4 text-center border-r border-slate-200">
                                <div class="inline-flex items-center gap-2">
                                    <input type="number" 
                                           name="target_k" 
                                           min="0" 
                                           x-model.number="targetK"
                                           class="w-24 px-3 py-1.5 text-center font-mono font-bold text-sm border border-slate-300 focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] rounded-none bg-slate-50 focus:bg-white"
                                           required>
                                    <span class="text-xs text-slate-500 font-medium">Orang</span>
                                </div>
                            </td>
                            <td class="py-2.5 px-4 text-center text-slate-500 text-[11px] font-mono">
                                Sisa Kuota (~{{ max(0, $totalKaryawan07006 - (int)round($totalKaryawan07006 * 0.90)) }} Orang)
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-slate-100/90 font-bold border-t-2 border-slate-300 text-slate-900">
                        <tr>
                            <td class="py-3 px-4 border-r border-slate-300 uppercase tracking-wider text-[11px]">
                                TOTAL TARGET &amp; REALISASI
                            </td>
                            <td class="py-3 px-4 text-center border-r border-slate-300 font-mono text-sm text-[#92302e]">
                                {{ $totalActual }} <span class="text-[10px] text-[#92302e]/60 font-normal">Orang</span>
                            </td>
                            <td class="py-3 px-4 text-center border-r border-slate-300 font-mono text-sm text-blue-900">
                                <span x-text="totalTarget">{{ $globalTarget['total'] }}</span> <span class="text-[10px] text-slate-500 font-normal">Orang</span>
                            </td>
                            <td class="py-3 px-4 text-center font-mono text-xs text-slate-600">
                                Populasi: {{ $totalKaryawan07006 }} Orang
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="mt-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-3 border-t border-slate-200">
                <div class="text-xs text-slate-500 flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span>Target kuota bersifat fleksibel tanpa pembatasan batas kaku dan langsung tersimpan ke spreadsheet.</span>
                </div>
                <button type="submit" 
                        class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-none font-bold text-xs bg-[#92302e] hover:bg-[#7e2524] text-white shadow-sm transition-all cursor-pointer whitespace-nowrap">
                    <span>💾 Simpan Target Divisi &amp; Sinkronkan Spreadsheet</span>
                </button>
            </div>
        </form>
    </div>

    <!-- 5. Card: Rekap Realisasi Penilaian Form 07-006 Seluruh Divisi -->
    <div class="bg-white rounded-none border border-slate-300 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-200 bg-slate-50/70 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                    <span>Rekap Realisasi Nilai Form 07-006 per Divisi</span>
                    <span class="text-[10px] font-mono px-2 py-0.5 rounded-none bg-blue-100 text-blue-800 font-bold border border-blue-200 uppercase tracking-wider">
                        {{ $totalDivisi }} Divisi
                    </span>
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">
                    Rincian nilai aktual (S, A, B, C, K) dari tiap divisi yang tampil di dashboard Division Head.
                </p>
            </div>
        </div>

        <div class="p-4 sm:p-5">
            <div class="overflow-x-auto border border-slate-300">
                <table class="w-full text-left text-xs border-collapse whitespace-nowrap">
                    <thead>
                        <tr class="border-b border-slate-300 text-slate-700 font-bold bg-slate-100 text-[11px] uppercase tracking-wider">
                            <th class="py-3 px-3 text-center border-r border-slate-300 w-12">No</th>
                            <th class="py-3 px-4 border-r border-slate-300">Nama Divisi</th>
                            <th class="py-3 px-3 text-center border-r border-slate-300">Populasi 07-006</th>
                            <th class="py-3 px-3 text-center border-r border-slate-300 text-purple-800 bg-purple-50/50">Grade S</th>
                            <th class="py-3 px-3 text-center border-r border-slate-300 text-emerald-800 bg-emerald-50/50">Grade A</th>
                            <th class="py-3 px-3 text-center border-r border-slate-300 text-blue-800 bg-blue-50/50">Grade B</th>
                            <th class="py-3 px-3 text-center border-r border-slate-300 text-amber-800 bg-amber-50/50">Grade C</th>
                            <th class="py-3 px-3 text-center border-r border-slate-300 text-rose-800 bg-rose-50/50">Grade K</th>
                            <th class="py-3 px-3 text-center border-r border-slate-300 font-bold bg-slate-200/50">Sudah Dinilai</th>
                            <th class="py-3 px-3 text-center border-r border-slate-300">Belum Dinilai</th>
                            <th class="py-3 px-3 text-center">Progres</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @forelse($dbDivRows as $idx => $row)
                            @php
                                $dTot = (int)$row->total_karyawan_07006;
                                $dAct = (int)$row->total_actual;
                                $dBelum = max(0, $dTot - $dAct);
                                $dPct = $dTot > 0 ? (int)round(($dAct / $dTot) * 100) : 0;
                            @endphp
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="py-3 px-3 text-center border-r border-slate-200 font-mono text-slate-500 text-[11px]">
                                    {{ $idx + 1 }}
                                </td>
                                <td class="py-3 px-4 border-r border-slate-200 font-bold text-slate-900">
                                    {{ $row->divisi }}
                                </td>
                                <td class="py-3 px-3 text-center border-r border-slate-200 font-mono font-bold text-slate-800">
                                    {{ $dTot }}
                                </td>
                                <td class="py-3 px-3 text-center border-r border-slate-200 font-mono {{ (int)$row->actual_s > 0 ? 'font-black text-purple-700 bg-purple-50/30' : 'text-slate-400' }}">
                                    {{ (int)$row->actual_s }}
                                </td>
                                <td class="py-3 px-3 text-center border-r border-slate-200 font-mono {{ (int)$row->actual_a > 0 ? 'font-black text-emerald-700 bg-emerald-50/30' : 'text-slate-400' }}">
                                    {{ (int)$row->actual_a }}
                                </td>
                                <td class="py-3 px-3 text-center border-r border-slate-200 font-mono {{ (int)$row->actual_b > 0 ? 'font-black text-blue-700 bg-blue-50/30' : 'text-slate-400' }}">
                                    {{ (int)$row->actual_b }}
                                </td>
                                <td class="py-3 px-3 text-center border-r border-slate-200 font-mono {{ (int)$row->actual_c > 0 ? 'font-black text-amber-700 bg-amber-50/30' : 'text-slate-400' }}">
                                    {{ (int)$row->actual_c }}
                                </td>
                                <td class="py-3 px-3 text-center border-r border-slate-200 font-mono {{ (int)$row->actual_k > 0 ? 'font-black text-rose-700 bg-rose-50/30' : 'text-slate-400' }}">
                                    {{ (int)$row->actual_k }}
                                </td>
                                <td class="py-3 px-3 text-center border-r border-slate-200 font-mono font-bold {{ $dAct > 0 ? 'text-[#92302e] bg-[#fdf5f5]' : 'text-slate-400' }}">
                                    {{ $dAct }}
                                </td>
                                <td class="py-3 px-3 text-center border-r border-slate-200 font-mono text-slate-600">
                                    {{ $dBelum }}
                                </td>
                                <td class="py-3 px-3 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <div class="w-16 bg-slate-200 h-2 rounded-none overflow-hidden">
                                            <div class="bg-[#92302e] h-full" style="width: {{ $dPct }}%"></div>
                                        </div>
                                        <span class="font-mono text-[11px] font-bold text-slate-700">{{ $dPct }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="py-8 text-center text-slate-500 font-medium text-xs">
                                    Tidak ada data divisi yang ditemukan untuk Form 07-006.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-slate-100 font-black border-t-2 border-slate-300 text-slate-900">
                        @php
                            $sumPop = $dbDivRows->sum('total_karyawan_07006');
                            $sumS = $dbDivRows->sum('actual_s');
                            $sumA = $dbDivRows->sum('actual_a');
                            $sumB = $dbDivRows->sum('actual_b');
                            $sumC = $dbDivRows->sum('actual_c');
                            $sumK = $dbDivRows->sum('actual_k');
                            $sumAct = $dbDivRows->sum('total_actual');
                            $sumBelum = max(0, $sumPop - $sumAct);
                            $grandPct = $sumPop > 0 ? (int)round(($sumAct / $sumPop) * 100) : 0;
                        @endphp
                        <tr>
                            <td colspan="2" class="py-3.5 px-4 text-left border-r border-slate-300 uppercase tracking-wider text-[11px]">
                                TOTAL GABUNGAN SELURUH DIVISI
                            </td>
                            <td class="py-3.5 px-3 text-center border-r border-slate-300 font-mono text-sm text-slate-900">
                                {{ $sumPop }}
                            </td>
                            <td class="py-3.5 px-3 text-center border-r border-slate-300 font-mono text-sm text-purple-900 bg-purple-100/50">
                                {{ $sumS }}
                            </td>
                            <td class="py-3.5 px-3 text-center border-r border-slate-300 font-mono text-sm text-emerald-900 bg-emerald-100/50">
                                {{ $sumA }}
                            </td>
                            <td class="py-3.5 px-3 text-center border-r border-slate-300 font-mono text-sm text-blue-900 bg-blue-100/50">
                                {{ $sumB }}
                            </td>
                            <td class="py-3.5 px-3 text-center border-r border-slate-300 font-mono text-sm text-amber-900 bg-amber-100/50">
                                {{ $sumC }}
                            </td>
                            <td class="py-3.5 px-3 text-center border-r border-slate-300 font-mono text-sm text-rose-900 bg-rose-100/50">
                                {{ $sumK }}
                            </td>
                            <td class="py-3.5 px-3 text-center border-r border-slate-300 font-mono text-sm text-[#92302e] bg-[#fbe8e8]">
                                {{ $sumAct }}
                            </td>
                            <td class="py-3.5 px-3 text-center border-r border-slate-300 font-mono text-sm text-slate-600">
                                {{ $sumBelum }}
                            </td>
                            <td class="py-3.5 px-3 text-center font-mono text-sm text-slate-900">
                                {{ $grandPct }}%
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function updateWibClock() {
        const el = document.getElementById('realtimeClock');
        if (!el) return;
        const now = new Date();
        const utc = now.getTime() + (now.getTimezoneOffset() * 60000);
        const wib = new Date(utc + (3600000 * 7));
        const pad = (n) => String(n).padStart(2, '0');
        const str = pad(wib.getDate()) + '-' + pad(wib.getMonth() + 1) + '-' + wib.getFullYear() + ' ' + pad(wib.getHours()) + ':' + pad(wib.getMinutes()) + ' WIB';
        el.innerText = str;
    }
    setInterval(updateWibClock, 10000);
});
</script>
@endsection
