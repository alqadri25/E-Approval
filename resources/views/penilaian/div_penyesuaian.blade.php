@php
    $isDeptMode = ($penyesuaianMode ?? 'div') === 'dept';
    $routesMap = $penyesuaianRoutes ?? [
        'simpan' => route('penilaian.div.penyesuaian.simpan'),
        'autoAdjust' => route('penilaian.div.penyesuaian.autoAdjust'),
        'reset' => route('penilaian.div.penyesuaian.reset'),
    ];
    $pageTitle = $isDeptMode
        ? 'Penyesuaian Penilaian (Docking Kuota) - Department Head'
        : 'Penyesuaian Penilaian (Docking Kuota) - Division Head';
@endphp
@extends('layouts.app', ['title' => $pageTitle])

@section('content')
<div class="space-y-5" x-data="penyesuaianKuotApp()">

    <!-- 1. Header Section Card (Merah Musashi Gradient & Rounded-None) -->
    <div class="bg-gradient-to-r from-[#962226] via-[#92302e] to-[#781b1f] text-white rounded-none p-5 sm:p-6 border border-[#781b1f] shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight">
                Penyesuaian Penilaian
            </h1>
            <p class="text-xs text-red-100 font-medium mt-1">
                {{ $isDeptMode ? 'Menyelaraskan nilai akhir departemen serta membandingkan riwayat nilai Foreman, Section Head, dan Department Head sesuai kuota target Admin' : 'Menyelaraskan dan menyeimbangkan distribusi ranking nilai akhir karyawan sesuai kuota target yang telah ditetapkan' }}
            </p>
        </div>

        <div class="flex items-center gap-2 sm:gap-2.5 flex-nowrap flex-shrink-0 whitespace-nowrap">
            <form action="{{ $routesMap['reset'] }}" method="POST" class="inline-flex flex-shrink-0"
                  @submit="return confirm('Apakah Anda yakin ingin mereset seluruh nilai penyesuaian kuota (docking) kembali ke status awal?')">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-none bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs transition shadow-sm border border-slate-700 cursor-pointer whitespace-nowrap flex-shrink-0"
                        title="Reset seluruh docking kuota">
                    <svg class="w-3.5 h-3.5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    <span>Reset</span>
                </button>
            </form>

            <form action="{{ $routesMap['autoAdjust'] }}" method="POST" class="inline-flex flex-shrink-0"
                  @submit.prevent="handleAutoAdjust($event)">
                @csrf
                <button type="submit"
                        :disabled="eligibleCount === 0"
                        :class="eligibleCount === 0 ? 'opacity-50 cursor-not-allowed bg-slate-300 border-slate-400 text-slate-600' : 'bg-amber-500 hover:bg-amber-600 text-slate-950 border-amber-400 cursor-pointer'"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-none font-black text-xs transition shadow-sm border whitespace-nowrap flex-shrink-0"
                        :title="eligibleCount === 0 ? 'Belum ada dokumen yang siap disesuaikan' : 'Sesuaikan ke Target'">
                    <svg class="w-3.5 h-3.5" :class="eligibleCount === 0 ? 'text-slate-500' : 'text-slate-950'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    <span>Sesuaikan Target</span>
                </button>
            </form>

            <button type="button" @click="submitAll()"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-none bg-white hover:bg-slate-100 text-[#92302e] font-black text-xs transition shadow-sm border border-white cursor-pointer whitespace-nowrap flex-shrink-0"
                    title="Simpan Penyesuaian Nilai">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                </svg>
                <span>Simpan</span>
            </button>
        </div>
    </div>

    <!-- 2. Dynamic Warning Banner (Alert jika ada kuota yang melebihi target) -->
    <div x-show="hasOverQuota" x-cloak
         class="bg-rose-50 border-2 border-rose-500 rounded-none p-4 shadow-sm transition-all duration-300">
        <div class="flex items-start gap-3.5">
            <div class="w-8 h-8 rounded-none bg-rose-600 text-white font-black flex items-center justify-center text-base flex-shrink-0 mt-0.5">
                !
            </div>
            <div class="flex-1">
                <h3 class="text-sm font-black text-rose-950 tracking-tight flex items-center gap-2">
                    <span>PERINGATAN KUOTA TARGET MELEBIHI BATAS!</span>
                    <span class="px-2 py-0.5 text-[10px] font-black bg-rose-200 text-rose-900 border border-rose-300">Perlu Penyesuaian</span>
                </h3>
                <p class="text-xs text-rose-800 font-medium mt-1 leading-relaxed">
                    Terdapat ranking yang melebih target kuota yang ditentukan oleh Admin:
                    <template x-for="(diff, grade) in overQuotaList" :key="grade">
                        <strong class="font-black text-rose-950 underline ml-1">Rank <span x-text="grade"></span> (+<span x-text="diff"></span> orang)</strong>
                    </template>.
                    Tolong lakukan penyesuaian pada grade karyawan di tabel bawah agar sesuai dengan alokasi kuota target dari Admin, atau klik tombol <strong>"Sesuaikan ke Target"</strong> di atas.
                </p>
                <div class="mt-2.5 flex flex-wrap items-center gap-2 text-xs">
                    <span class="text-slate-600 font-semibold">Slot Tersedia:</span>
                    <template x-for="(diff, grade) in availableSlotsList" :key="grade">
                        <span class="px-2 py-0.5 bg-blue-100 text-blue-900 font-black border border-blue-300 text-[11px]">
                            Rank <span x-text="grade"></span> (Tersedia: +<span x-text="diff"></span> slot)
                        </span>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- 2B. Balanced Success Banner -->
    <div x-show="!hasOverQuota" x-cloak
         class="bg-emerald-50 border-2 border-emerald-500 rounded-none p-4 shadow-sm transition-all duration-300">
        <div class="flex items-center gap-3.5">
            <div class="w-8 h-8 rounded-none bg-emerald-600 text-white font-black flex items-center justify-center text-sm flex-shrink-0">
                ✓
            </div>
            <div class="flex-1">
                <h3 class="text-sm font-black text-emerald-950 tracking-tight">
                    Distribusi Nilai Seimbang &amp; Sesuai Target Kuota
                </h3>
                <p class="text-xs text-emerald-800 font-medium mt-0.5">
                    Semua peringkat nilai (Rank S, A, B, C, K) telah memenuhi batas alokasi kuota target yang diberikan oleh Admin. Anda dapat menyimpan perubahan dengan mengklik tombol "Simpan Penyesuaian".
                </p>
            </div>
        </div>
    </div>

    <!-- 3. Target vs Aktual Stat Cards (5 Cards: S, A, B, C, K) -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        <!-- Rank S Card -->
        <div class="bg-white rounded-none p-4 border transition-all duration-200"
             :class="currentCounts.S > targetQuota.S ? 'border-rose-400 bg-rose-50/30' : (currentCounts.S === targetQuota.S ? 'border-emerald-300 bg-emerald-50/20' : 'border-slate-300')">
            <div class="flex items-center justify-between mb-2">
                <span class="px-2.5 py-0.5 bg-purple-100 text-purple-900 border border-purple-300 font-black text-xs">RANK S</span>
                <span class="text-[11px] font-bold"
                      :class="currentCounts.S > targetQuota.S ? 'text-rose-700' : (currentCounts.S === targetQuota.S ? 'text-emerald-700' : 'text-blue-700')">
                    <span x-text="currentCounts.S > targetQuota.S ? '⚠️ Over (+' + (currentCounts.S - targetQuota.S) + ')' : (currentCounts.S === targetQuota.S ? '✓ Pas' : 'Slot ' + (targetQuota.S - currentCounts.S))"></span>
                </span>
            </div>
            <div class="flex items-baseline justify-between mt-1">
                <div>
                    <div class="text-2xl font-black text-slate-900 leading-none" x-text="currentCounts.S"></div>
                    <span class="text-[10px] text-slate-500 font-semibold block mt-1">Aktual Saat Ini</span>
                </div>
                <div class="text-right">
                    <div class="text-sm font-black text-slate-600" x-text="targetQuota.S + ' orang'"></div>
                    <span class="text-[10px] text-slate-400 font-bold block mt-0.5">Target</span>
                </div>
            </div>
            <!-- Progress Bar -->
            <div class="w-full bg-slate-100 h-2 mt-3 border border-slate-200 overflow-hidden">
                <div class="h-full transition-all duration-300"
                     :class="currentCounts.S > targetQuota.S ? 'bg-rose-600' : 'bg-purple-600'"
                     :style="'width: ' + Math.min(100, targetQuota.S > 0 ? (currentCounts.S / targetQuota.S) * 100 : (currentCounts.S > 0 ? 100 : 0)) + '%'"></div>
            </div>
        </div>

        <!-- Rank A Card -->
        <div class="bg-white rounded-none p-4 border transition-all duration-200"
             :class="currentCounts.A > targetQuota.A ? 'border-rose-400 bg-rose-50/30' : (currentCounts.A === targetQuota.A ? 'border-emerald-300 bg-emerald-50/20' : 'border-slate-300')">
            <div class="flex items-center justify-between mb-2">
                <span class="px-2.5 py-0.5 bg-emerald-100 text-emerald-900 border border-emerald-300 font-black text-xs">RANK A</span>
                <span class="text-[11px] font-bold"
                      :class="currentCounts.A > targetQuota.A ? 'text-rose-700' : (currentCounts.A === targetQuota.A ? 'text-emerald-700' : 'text-blue-700')">
                    <span x-text="currentCounts.A > targetQuota.A ? '⚠️ Over (+' + (currentCounts.A - targetQuota.A) + ')' : (currentCounts.A === targetQuota.A ? '✓ Pas' : 'Slot ' + (targetQuota.A - currentCounts.A))"></span>
                </span>
            </div>
            <div class="flex items-baseline justify-between mt-1">
                <div>
                    <div class="text-2xl font-black text-slate-900 leading-none" x-text="currentCounts.A"></div>
                    <span class="text-[10px] text-slate-500 font-semibold block mt-1">Aktual Saat Ini</span>
                </div>
                <div class="text-right">
                    <div class="text-sm font-black text-slate-600" x-text="targetQuota.A + ' orang'"></div>
                    <span class="text-[10px] text-slate-400 font-bold block mt-0.5">Target</span>
                </div>
            </div>
            <!-- Progress Bar -->
            <div class="w-full bg-slate-100 h-2 mt-3 border border-slate-200 overflow-hidden">
                <div class="h-full transition-all duration-300"
                     :class="currentCounts.A > targetQuota.A ? 'bg-rose-600' : 'bg-emerald-600'"
                     :style="'width: ' + Math.min(100, targetQuota.A > 0 ? (currentCounts.A / targetQuota.A) * 100 : (currentCounts.A > 0 ? 100 : 0)) + '%'"></div>
            </div>
        </div>

        <!-- Rank B Card -->
        <div class="bg-white rounded-none p-4 border transition-all duration-200"
             :class="currentCounts.B > targetQuota.B ? 'border-rose-400 bg-rose-50/30' : (currentCounts.B === targetQuota.B ? 'border-emerald-300 bg-emerald-50/20' : 'border-slate-300')">
            <div class="flex items-center justify-between mb-2">
                <span class="px-2.5 py-0.5 bg-blue-100 text-blue-900 border border-blue-300 font-black text-xs">RANK B</span>
                <span class="text-[11px] font-bold"
                      :class="currentCounts.B > targetQuota.B ? 'text-rose-700' : (currentCounts.B === targetQuota.B ? 'text-emerald-700' : 'text-blue-700')">
                    <span x-text="currentCounts.B > targetQuota.B ? '⚠️ Over (+' + (currentCounts.B - targetQuota.B) + ')' : (currentCounts.B === targetQuota.B ? '✓ Pas' : 'Slot ' + (targetQuota.B - currentCounts.B))"></span>
                </span>
            </div>
            <div class="flex items-baseline justify-between mt-1">
                <div>
                    <div class="text-2xl font-black text-slate-900 leading-none" x-text="currentCounts.B"></div>
                    <span class="text-[10px] text-slate-500 font-semibold block mt-1">Aktual Saat Ini</span>
                </div>
                <div class="text-right">
                    <div class="text-sm font-black text-slate-600" x-text="targetQuota.B + ' orang'"></div>
                    <span class="text-[10px] text-slate-400 font-bold block mt-0.5">Target</span>
                </div>
            </div>
            <!-- Progress Bar -->
            <div class="w-full bg-slate-100 h-2 mt-3 border border-slate-200 overflow-hidden">
                <div class="h-full transition-all duration-300"
                     :class="currentCounts.B > targetQuota.B ? 'bg-rose-600' : 'bg-blue-600'"
                     :style="'width: ' + Math.min(100, targetQuota.B > 0 ? (currentCounts.B / targetQuota.B) * 100 : (currentCounts.B > 0 ? 100 : 0)) + '%'"></div>
            </div>
        </div>

        <!-- Rank C Card -->
        <div class="bg-white rounded-none p-4 border transition-all duration-200"
             :class="currentCounts.C > targetQuota.C ? 'border-rose-400 bg-rose-50/30' : (currentCounts.C === targetQuota.C ? 'border-emerald-300 bg-emerald-50/20' : 'border-slate-300')">
            <div class="flex items-center justify-between mb-2">
                <span class="px-2.5 py-0.5 bg-amber-100 text-amber-900 border border-amber-300 font-black text-xs">RANK C</span>
                <span class="text-[11px] font-bold"
                      :class="currentCounts.C > targetQuota.C ? 'text-rose-700' : (currentCounts.C === targetQuota.C ? 'text-emerald-700' : 'text-blue-700')">
                    <span x-text="currentCounts.C > targetQuota.C ? '⚠️ Over (+' + (currentCounts.C - targetQuota.C) + ')' : (currentCounts.C === targetQuota.C ? '✓ Pas' : 'Slot ' + (targetQuota.C - currentCounts.C))"></span>
                </span>
            </div>
            <div class="flex items-baseline justify-between mt-1">
                <div>
                    <div class="text-2xl font-black text-slate-900 leading-none" x-text="currentCounts.C"></div>
                    <span class="text-[10px] text-slate-500 font-semibold block mt-1">Aktual Saat Ini</span>
                </div>
                <div class="text-right">
                    <div class="text-sm font-black text-slate-600" x-text="targetQuota.C + ' orang'"></div>
                    <span class="text-[10px] text-slate-400 font-bold block mt-0.5">Target</span>
                </div>
            </div>
            <!-- Progress Bar -->
            <div class="w-full bg-slate-100 h-2 mt-3 border border-slate-200 overflow-hidden">
                <div class="h-full transition-all duration-300"
                     :class="currentCounts.C > targetQuota.C ? 'bg-rose-600' : 'bg-amber-600'"
                     :style="'width: ' + Math.min(100, targetQuota.C > 0 ? (currentCounts.C / targetQuota.C) * 100 : (currentCounts.C > 0 ? 100 : 0)) + '%'"></div>
            </div>
        </div>

        <!-- Rank K Card -->
        <div class="bg-white rounded-none p-4 border transition-all duration-200"
             :class="currentCounts.K > targetQuota.K ? 'border-rose-400 bg-rose-50/30' : (currentCounts.K === targetQuota.K ? 'border-emerald-300 bg-emerald-50/20' : 'border-slate-300')">
            <div class="flex items-center justify-between mb-2">
                <span class="px-2.5 py-0.5 bg-rose-100 text-rose-900 border border-rose-300 font-black text-xs">RANK K</span>
                <span class="text-[11px] font-bold"
                      :class="currentCounts.K > targetQuota.K ? 'text-rose-700' : (currentCounts.K === targetQuota.K ? 'text-emerald-700' : 'text-blue-700')">
                    <span x-text="currentCounts.K > targetQuota.K ? '⚠️ Over (+' + (currentCounts.K - targetQuota.K) + ')' : (currentCounts.K === targetQuota.K ? '✓ Pas' : 'Slot ' + (targetQuota.K - currentCounts.K))"></span>
                </span>
            </div>
            <div class="flex items-baseline justify-between mt-1">
                <div>
                    <div class="text-2xl font-black text-slate-900 leading-none" x-text="currentCounts.K"></div>
                    <span class="text-[10px] text-slate-500 font-semibold block mt-1">Aktual Saat Ini</span>
                </div>
                <div class="text-right">
                    <div class="text-sm font-black text-slate-600" x-text="targetQuota.K + ' orang'"></div>
                    <span class="text-[10px] text-slate-400 font-bold block mt-0.5">Target</span>
                </div>
            </div>
            <!-- Progress Bar -->
            <div class="w-full bg-slate-100 h-2 mt-3 border border-slate-200 overflow-hidden">
                <div class="h-full transition-all duration-300"
                     :class="currentCounts.K > targetQuota.K ? 'bg-rose-600' : 'bg-rose-500'"
                     :style="'width: ' + Math.min(100, targetQuota.K > 0 ? (currentCounts.K / targetQuota.K) * 100 : (currentCounts.K > 0 ? 100 : 0)) + '%'"></div>
            </div>
        </div>
    </div>

    <!-- 3B. Tabel Target Kuota & Realisasi per Jenjang KJ (Golongan) -->
    <div class="bg-white rounded-none border border-slate-300 p-4 sm:p-5 shadow-sm space-y-3.5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 pb-2.5 border-b border-slate-200">
            <div>
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 bg-[#92302e]"></span>
                    <h2 class="text-xs sm:text-sm font-black text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
                        <span>Target Kuota &amp; Realisasi per Jenjang KJ</span>
                    </h2>
                </div>
                <p class="text-[11px] text-slate-500 font-medium mt-0.5">
                    Target alokasi orang per grade (S, A, B, C, K) untuk jenjang kepangkatan operasional. Klik baris atau tombol filter KJ untuk menyaring daftar karyawan di bawah.
                </p>
            </div>
            <div class="flex items-center gap-2 flex-wrap self-start sm:self-auto">
                <span class="px-2.5 py-1 text-[11px] font-bold bg-slate-100 text-slate-700 border border-slate-300 rounded-none shadow-2xs">
                    Cakupan: <strong class="text-slate-900 font-black">{{ $divName ?? 'Tingkat Divisi' }}</strong>
                </span>
                <button type="button" x-show="filterKj !== 'ALL'" @click="filterKj = 'ALL'"
                        class="px-2.5 py-1 text-[11px] font-bold bg-[#fbe8e8] text-[#92302e] hover:bg-[#92302e] hover:text-white border border-[#f5c6c6] transition cursor-pointer rounded-none shadow-2xs">
                    Reset Filter KJ (<span x-text="filterKj"></span>) &times;
                </button>
            </div>
        </div>

        <!-- Tabel Target Kuota & Aktual per KJ -->
        <div class="overflow-x-auto border border-slate-300">
            <table class="w-full text-center text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-100 text-slate-700 font-black uppercase tracking-wider text-[10.5px] border-b border-slate-300">
                        <th rowspan="2" class="py-2 px-3 border-r border-slate-300 text-left w-24">Jenjang KJ</th>
                        <th rowspan="2" class="py-2 px-2.5 border-r border-slate-300 w-24">Karyawan</th>
                        <th colspan="6" class="py-1.5 px-2 border-r border-slate-300 bg-slate-200/70 text-slate-800 border-b border-slate-300">Target Orang</th>
                        <th colspan="6" class="py-1.5 px-2 border-r border-slate-300 bg-red-50/70 text-[#92302e] border-b border-slate-300">Aktual Saat Ini</th>
                        <th rowspan="2" class="py-2 px-3 border-r border-slate-300 w-36">Status Kuota</th>
                        <th rowspan="2" class="py-2 px-2.5 w-28">Filter</th>
                    </tr>
                    <tr class="bg-slate-50 text-slate-700 font-bold text-[10px] border-b border-slate-300">
                        <th class="py-1.5 px-2 border-r border-slate-200 bg-emerald-50/60 text-emerald-900">S</th>
                        <th class="py-1.5 px-2 border-r border-slate-200 bg-blue-50/60 text-blue-900">A</th>
                        <th class="py-1.5 px-2 border-r border-slate-200 bg-amber-50/60 text-amber-900">B</th>
                        <th class="py-1.5 px-2 border-r border-slate-200 bg-orange-50/60 text-orange-900">C</th>
                        <th class="py-1.5 px-2 border-r border-slate-200 bg-rose-50/60 text-rose-900">K</th>
                        <th class="py-1.5 px-2 border-r border-slate-300 bg-slate-100 font-black">Target</th>
                        <th class="py-1.5 px-2 border-r border-slate-200">S</th>
                        <th class="py-1.5 px-2 border-r border-slate-200">A</th>
                        <th class="py-1.5 px-2 border-r border-slate-200">B</th>
                        <th class="py-1.5 px-2 border-r border-slate-200">C</th>
                        <th class="py-1.5 px-2 border-r border-slate-200">K</th>
                        <th class="py-1.5 px-2 border-r border-slate-300 font-black">Aktual</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <template x-for="(kjData, kjCode) in kjSummary" :key="kjCode">
                        <tr class="transition-colors cursor-pointer"
                            :class="filterKj === kjCode ? 'bg-red-50/60 font-semibold' : 'hover:bg-slate-50'"
                            @click="toggleFilterKj(kjCode)">
                            <td class="py-2.5 px-3 border-r border-slate-300 text-left">
                                <span class="px-2 py-0.5 text-[11px] font-black bg-[#92302e] text-white rounded-none shadow-2xs inline-block" x-text="kjCode"></span>
                            </td>
                            <td class="py-2.5 px-2.5 border-r border-slate-300 font-mono font-bold text-slate-800">
                                <span x-text="kjData.total_karyawan"></span> <span class="text-[10px] font-normal text-slate-500">org</span>
                            </td>
                            <td class="py-2 px-2 border-r border-slate-200 font-mono font-bold bg-emerald-50/30 text-emerald-900" x-text="kjData.target.S"></td>
                            <td class="py-2 px-2 border-r border-slate-200 font-mono font-bold bg-blue-50/30 text-blue-900" x-text="kjData.target.A"></td>
                            <td class="py-2 px-2 border-r border-slate-200 font-mono font-bold bg-amber-50/30 text-amber-900" x-text="kjData.target.B"></td>
                            <td class="py-2 px-2 border-r border-slate-200 font-mono font-bold bg-orange-50/30 text-orange-900" x-text="kjData.target.C"></td>
                            <td class="py-2 px-2 border-r border-slate-200 font-mono font-bold bg-rose-50/30 text-rose-900" x-text="kjData.target.K"></td>
                            <td class="py-2 px-2 border-r border-slate-300 font-mono font-black bg-slate-100 text-slate-900" x-text="kjData.target.total"></td>
                            <td class="py-2 px-2 border-r border-slate-200 font-mono font-bold"
                                :class="getKjActualCounts(kjCode).S > kjData.target.S ? 'bg-rose-100 text-rose-800 font-black' : 'text-slate-800'"
                                x-text="getKjActualCounts(kjCode).S"></td>
                            <td class="py-2 px-2 border-r border-slate-200 font-mono font-bold"
                                :class="getKjActualCounts(kjCode).A > kjData.target.A ? 'bg-rose-100 text-rose-800 font-black' : 'text-slate-800'"
                                x-text="getKjActualCounts(kjCode).A"></td>
                            <td class="py-2 px-2 border-r border-slate-200 font-mono font-bold"
                                :class="getKjActualCounts(kjCode).B > kjData.target.B ? 'bg-rose-100 text-rose-800 font-black' : 'text-slate-800'"
                                x-text="getKjActualCounts(kjCode).B"></td>
                            <td class="py-2 px-2 border-r border-slate-200 font-mono font-bold"
                                :class="getKjActualCounts(kjCode).C > kjData.target.C ? 'bg-rose-100 text-rose-800 font-black' : 'text-slate-800'"
                                x-text="getKjActualCounts(kjCode).C"></td>
                            <td class="py-2 px-2 border-r border-slate-200 font-mono font-bold"
                                :class="getKjActualCounts(kjCode).K > kjData.target.K ? 'bg-rose-100 text-rose-800 font-black' : 'text-slate-800'"
                                x-text="getKjActualCounts(kjCode).K"></td>
                            <td class="py-2 px-2 border-r border-slate-300 font-mono font-black text-slate-900" x-text="getKjActualCounts(kjCode).total"></td>
                            <td class="py-2 px-3 border-r border-slate-300">
                                <span class="px-2 py-0.5 text-[10px] font-black border rounded-none inline-block"
                                      :class="getKjStatus(kjCode).class"
                                      x-text="getKjStatus(kjCode).text"></span>
                            </td>
                            <td class="py-2 px-2.5">
                                <span class="text-[11px] font-bold inline-block px-2 py-0.5 border rounded-none transition-colors"
                                      :class="filterKj === kjCode ? 'bg-[#92302e] text-white border-[#781b1f]' : 'bg-white text-slate-600 border-slate-300 hover:border-[#92302e] hover:text-[#92302e]'"
                                      x-text="filterKj === kjCode ? '✓ Aktif' : 'Filter KJ'"></span>
                            </td>
                        </tr>
                    </template>
                </tbody>
                <tfoot>
                    <tr class="bg-slate-100 font-black text-slate-900 border-t-2 border-slate-300">
                        <td class="py-2.5 px-3 border-r border-slate-300 text-left uppercase text-[11px]">Total</td>
                        <td class="py-2.5 px-2.5 border-r border-slate-300 font-mono"
                            x-text="Object.values(kjSummary).reduce((a, b) => a + (b.total_karyawan || 0), 0) + ' org'"></td>
                        <td class="py-2.5 px-2 border-r border-slate-200 font-mono" x-text="Object.values(kjSummary).reduce((a, b) => a + (b.target?.S || 0), 0)"></td>
                        <td class="py-2.5 px-2 border-r border-slate-200 font-mono" x-text="Object.values(kjSummary).reduce((a, b) => a + (b.target?.A || 0), 0)"></td>
                        <td class="py-2.5 px-2 border-r border-slate-200 font-mono" x-text="Object.values(kjSummary).reduce((a, b) => a + (b.target?.B || 0), 0)"></td>
                        <td class="py-2.5 px-2 border-r border-slate-200 font-mono" x-text="Object.values(kjSummary).reduce((a, b) => a + (b.target?.C || 0), 0)"></td>
                        <td class="py-2.5 px-2 border-r border-slate-200 font-mono" x-text="Object.values(kjSummary).reduce((a, b) => a + (b.target?.K || 0), 0)"></td>
                        <td class="py-2.5 px-2 border-r border-slate-300 font-mono bg-slate-200/80" x-text="Object.values(kjSummary).reduce((a, b) => a + (b.target?.total || 0), 0)"></td>
                        <td class="py-2.5 px-2 border-r border-slate-200 font-mono" x-text="currentCounts.S"></td>
                        <td class="py-2.5 px-2 border-r border-slate-200 font-mono" x-text="currentCounts.A"></td>
                        <td class="py-2.5 px-2 border-r border-slate-200 font-mono" x-text="currentCounts.B"></td>
                        <td class="py-2.5 px-2 border-r border-slate-200 font-mono" x-text="currentCounts.C"></td>
                        <td class="py-2.5 px-2 border-r border-slate-200 font-mono" x-text="currentCounts.K"></td>
                        <td class="py-2.5 px-2 border-r border-slate-300 font-mono bg-red-50 text-[#92302e]" x-text="currentCounts.S + currentCounts.A + currentCounts.B + currentCounts.C + currentCounts.K"></td>
                        <td colspan="2" class="py-2.5 px-3 text-[10px] text-slate-500 font-semibold">Target vs Aktual Kuota</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- 4. Tabel Perbandingan Target & Nilai Penilaian (Target, Foreman, Section Head, Dept/Div Head) -->
    <div class="bg-white rounded-none p-5 border border-slate-300 shadow-sm space-y-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 border-b border-slate-200 pb-3">
            <div>
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 bg-[#92302e]"></span>
                    <h2 class="text-sm font-black text-slate-900 uppercase tracking-wide">
                        Tabel Perbandingan Target &amp; Nilai
                    </h2>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">
                    {{ $isDeptMode ? 'Perbandingan Target kuota Admin dengan realisasi nilai Foreman, Section Head, dan Department Head (Anda).' : 'Perbandingan Target kuota Admin dengan realisasi nilai Foreman, Section, Dept, dan Div Head.' }}
                </p>
            </div>
            <!-- Legend Indicators -->
            <div class="flex items-center gap-1.5 text-[11px] font-bold whitespace-nowrap overflow-x-auto flex-nowrap shrink-0">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-slate-800 text-white rounded-none shadow-2xs">
                    <span class="w-2.5 h-2.5 bg-slate-400"></span>
                    <span>Target</span>
                </span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-sky-50 text-sky-900 border border-sky-300 rounded-none shadow-2xs">
                    <span class="w-2.5 h-2.5 bg-sky-600"></span>
                    <span>Foreman</span>
                </span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-teal-50 text-teal-900 border border-teal-300 rounded-none shadow-2xs">
                    <span class="w-2.5 h-2.5 bg-teal-600"></span>
                    <span>Section Head</span>
                </span>
                @if($isDeptMode)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-rose-50 text-rose-900 border border-rose-300 rounded-none shadow-2xs">
                    <span class="w-2.5 h-2.5 bg-[#92302e]"></span>
                    <span>Dept Head (Anda)</span>
                </span>
                @else
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-amber-50 text-amber-900 border border-amber-300 rounded-none shadow-2xs">
                    <span class="w-2.5 h-2.5 bg-amber-500"></span>
                    <span>Dept Head</span>
                </span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-rose-50 text-rose-900 border border-rose-300 rounded-none shadow-2xs">
                    <span class="w-2.5 h-2.5 bg-[#92302e]"></span>
                    <span>Division Head</span>
                </span>
                @endif
            </div>
        </div>

        <!-- Detailed Comparison Table Matrix -->
        <div class="overflow-x-auto border border-slate-300">
            <table class="w-full text-center text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-100 text-slate-700 font-bold border-b border-slate-300 text-[11px]">
                        <th class="py-2.5 px-3 text-left border-r border-slate-300">Tahapan / Level Penilaian</th>
                        <th class="py-2.5 px-3 border-r border-slate-200 text-purple-900 bg-purple-50/50">Rank S</th>
                        <th class="py-2.5 px-3 border-r border-slate-200 text-emerald-900 bg-emerald-50/50">Rank A</th>
                        <th class="py-2.5 px-3 border-r border-slate-200 text-blue-900 bg-blue-50/50">Rank B</th>
                        <th class="py-2.5 px-3 border-r border-slate-200 text-amber-900 bg-amber-50/50">Rank C</th>
                        <th class="py-2.5 px-3 border-r border-slate-200 text-slate-900 bg-slate-50">Rank K</th>
                        <th class="py-2.5 px-3 font-black text-slate-900">Total Karyawan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 text-slate-800 font-medium">
                    <!-- Target Admin -->
                    <tr class="hover:bg-slate-50">
                        <td class="py-2 px-3 text-left font-bold text-slate-900 border-r border-slate-300">
                            Target (Kuota)
                        </td>
                        <td class="py-2 px-3 font-mono font-bold border-r border-slate-200">{{ $comparisonData['target']['S'] ?? 0 }}</td>
                        <td class="py-2 px-3 font-mono font-bold border-r border-slate-200">{{ $comparisonData['target']['A'] ?? 0 }}</td>
                        <td class="py-2 px-3 font-mono font-bold border-r border-slate-200">{{ $comparisonData['target']['B'] ?? 0 }}</td>
                        <td class="py-2 px-3 font-mono font-bold border-r border-slate-200">{{ $comparisonData['target']['C'] ?? 0 }}</td>
                        <td class="py-2 px-3 font-mono font-bold border-r border-slate-200">{{ $comparisonData['target']['K'] ?? 0 }}</td>
                        <td class="py-2 px-3 font-mono font-black text-slate-900">{{ array_sum($comparisonData['target'] ?? []) }}</td>
                    </tr>
                    <!-- Foreman -->
                    <tr class="hover:bg-slate-50">
                        <td class="py-2 px-3 text-left font-bold text-sky-900 border-r border-slate-300">
                            Nilai Foreman
                        </td>
                        <td class="py-2 px-3 font-mono font-bold border-r border-slate-200">{{ $comparisonData['foreman']['S'] ?? 0 }}</td>
                        <td class="py-2 px-3 font-mono font-bold border-r border-slate-200">{{ $comparisonData['foreman']['A'] ?? 0 }}</td>
                        <td class="py-2 px-3 font-mono font-bold border-r border-slate-200">{{ $comparisonData['foreman']['B'] ?? 0 }}</td>
                        <td class="py-2 px-3 font-mono font-bold border-r border-slate-200">{{ $comparisonData['foreman']['C'] ?? 0 }}</td>
                        <td class="py-2 px-3 font-mono font-bold border-r border-slate-200">{{ $comparisonData['foreman']['K'] ?? 0 }}</td>
                        <td class="py-2 px-3 font-mono font-black text-sky-900">{{ array_sum($comparisonData['foreman'] ?? []) }}</td>
                    </tr>
                    <!-- Section Head -->
                    <tr class="hover:bg-slate-50">
                        <td class="py-2 px-3 text-left font-bold text-teal-900 border-r border-slate-300">
                            Nilai Section Head
                        </td>
                        <td class="py-2 px-3 font-mono font-bold border-r border-slate-200">{{ $comparisonData['section']['S'] ?? 0 }}</td>
                        <td class="py-2 px-3 font-mono font-bold border-r border-slate-200">{{ $comparisonData['section']['A'] ?? 0 }}</td>
                        <td class="py-2 px-3 font-mono font-bold border-r border-slate-200">{{ $comparisonData['section']['B'] ?? 0 }}</td>
                        <td class="py-2 px-3 font-mono font-bold border-r border-slate-200">{{ $comparisonData['section']['C'] ?? 0 }}</td>
                        <td class="py-2 px-3 font-mono font-bold border-r border-slate-200">{{ $comparisonData['section']['K'] ?? 0 }}</td>
                        <td class="py-2 px-3 font-mono font-black text-teal-900">{{ array_sum($comparisonData['section'] ?? []) }}</td>
                    </tr>
                    @if($isDeptMode)
                    <!-- Department Head (Anda - Live bound via Alpine.js) -->
                    <tr class="bg-red-50/40 font-bold">
                        <td class="py-2 px-3 text-left font-black text-[#92302e] border-r border-slate-300">
                            Nilai Department Head (Anda / Docking)
                        </td>
                        <td class="py-2 px-3 font-mono border-r border-slate-200" x-text="currentCounts.S"></td>
                        <td class="py-2 px-3 font-mono border-r border-slate-200" x-text="currentCounts.A"></td>
                        <td class="py-2 px-3 font-mono border-r border-slate-200" x-text="currentCounts.B"></td>
                        <td class="py-2 px-3 font-mono border-r border-slate-200" x-text="currentCounts.C"></td>
                        <td class="py-2 px-3 font-mono border-r border-slate-200" x-text="currentCounts.K"></td>
                        <td class="py-2 px-3 font-mono font-black text-[#92302e]" x-text="currentCounts.S + currentCounts.A + currentCounts.B + currentCounts.C + currentCounts.K"></td>
                    </tr>
                    @else
                    <!-- Dept Head -->
                    <tr class="hover:bg-slate-50">
                        <td class="py-2 px-3 text-left font-bold text-amber-900 border-r border-slate-300">
                            Nilai Dept Head
                        </td>
                        <td class="py-2 px-3 font-mono font-bold border-r border-slate-200">{{ $comparisonData['dept']['S'] ?? 0 }}</td>
                        <td class="py-2 px-3 font-mono font-bold border-r border-slate-200">{{ $comparisonData['dept']['A'] ?? 0 }}</td>
                        <td class="py-2 px-3 font-mono font-bold border-r border-slate-200">{{ $comparisonData['dept']['B'] ?? 0 }}</td>
                        <td class="py-2 px-3 font-mono font-bold border-r border-slate-200">{{ $comparisonData['dept']['C'] ?? 0 }}</td>
                        <td class="py-2 px-3 font-mono font-bold border-r border-slate-200">{{ $comparisonData['dept']['K'] ?? 0 }}</td>
                        <td class="py-2 px-3 font-mono font-black text-amber-900">{{ array_sum($comparisonData['dept'] ?? []) }}</td>
                    </tr>
                    <!-- Division Head (Live bound via Alpine.js) -->
                    <tr class="bg-red-50/40 font-bold">
                        <td class="py-2 px-3 text-left font-black text-[#92302e] border-r border-slate-300">
                            Nilai Division Head (Docking)
                        </td>
                        <td class="py-2 px-3 font-mono border-r border-slate-200" x-text="currentCounts.S"></td>
                        <td class="py-2 px-3 font-mono border-r border-slate-200" x-text="currentCounts.A"></td>
                        <td class="py-2 px-3 font-mono border-r border-slate-200" x-text="currentCounts.B"></td>
                        <td class="py-2 px-3 font-mono border-r border-slate-200" x-text="currentCounts.C"></td>
                        <td class="py-2 px-3 font-mono border-r border-slate-200" x-text="currentCounts.K"></td>
                        <td class="py-2 px-3 font-mono font-black text-[#92302e]" x-text="currentCounts.S + currentCounts.A + currentCounts.B + currentCounts.C + currentCounts.K"></td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>

        <!-- Per-KJ Breakdown Tables for Foreman & Section Head -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 pt-1">
            @include('dashboard.partials.kj_matrix_card', [
                'id' => 'penyesuaian-foreman-kj',
                'accentBg' => 'bg-sky-600',
                'dotColor' => 'bg-sky-500',
                'title' => 'Nilai Foreman',
                'subtitle' => '(realisasi penilaian foreman per KJ)',
                'badgeText' => 'Total: ' . array_sum($comparisonData['foreman'] ?? []),
                'badgeClass' => 'bg-sky-50 text-sky-800 border-sky-200',
                'lastColHeader' => 'Total',
                'emptyText' => 'Belum ada penilaian Foreman',
                'matrix' => $foremanKjMatrix ?? [],
                'summary' => array_merge($comparisonData['foreman'] ?? ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0], ['total' => array_sum($comparisonData['foreman'] ?? [])]),
                'footerLeft' => 'Rekap per Jenjang KJ',
                'footerRight' => array_sum($comparisonData['foreman'] ?? []) . ' Dinilai',
            ])
            @include('dashboard.partials.kj_matrix_card', [
                'id' => 'penyesuaian-section-kj',
                'accentBg' => 'bg-teal-600',
                'dotColor' => 'bg-teal-500',
                'title' => 'Nilai Section Head',
                'subtitle' => '(realisasi tinjauan section head per KJ)',
                'badgeText' => 'Total: ' . array_sum($comparisonData['section'] ?? []),
                'badgeClass' => 'bg-teal-50 text-teal-800 border-teal-200',
                'lastColHeader' => 'Total',
                'emptyText' => 'Belum ada penilaian Section Head',
                'matrix' => $sectionKjMatrix ?? [],
                'summary' => array_merge($comparisonData['section'] ?? ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0], ['total' => array_sum($comparisonData['section'] ?? [])]),
                'footerLeft' => 'Rekap per Jenjang KJ',
                'footerRight' => array_sum($comparisonData['section'] ?? []) . ' Dinilai',
            ])
        </div>
    </div>

    <!-- 5. Main Form: Tabel Karyawan & Penyesuaian Nilai -->
    <form id="formPenyesuaian" action="{{ $routesMap['simpan'] }}" method="POST"
          class="bg-white rounded-none border border-slate-300 shadow-sm p-4 space-y-4">
        @csrf
        <input type="hidden" name="force_save" :value="forceSaveVal ? 1 : 0">

        <!-- Toolbar Filter & Search -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-200 pb-3 overflow-x-auto">
            <div class="flex flex-nowrap items-center gap-2.5 shrink-0">
                <!-- 1. Search -->
                <div class="relative shrink-0">
                    <input type="text" x-model="searchQuery" placeholder="Cari NRP, Nama, Position..."
                           class="w-56 sm:w-64 pl-8 pr-3 py-1.5 text-xs border border-slate-300 rounded-none focus:outline-none focus:border-[#92302e] font-medium placeholder-slate-400">
                    <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>

                <!-- 2. Filter Status Kuota -->
                <select id="penyesuaianFilterStatus" x-model="filterStatus" class="text-xs border border-slate-300 rounded-none px-2.5 py-1.5 font-semibold text-slate-700 focus:outline-none focus:border-[#92302e] shrink-0">
                    <option value="all">Status Kuota</option>
                    <option value="original">PK Original</option>
                    <option value="adjusted">PK Disesuaikan</option>
                    <option value="over">PK Over Kuota</option>
                </select>

                <!-- 3. Filter Section -->
                @if(!empty($allSections) && count($allSections) > 0)
                <select id="penyesuaianFilterSection" x-model="filterSection" class="text-xs border border-slate-300 rounded-none px-2.5 py-1.5 font-semibold text-slate-700 focus:outline-none focus:border-[#92302e] shrink-0">
                    <option value="ALL">Section</option>
                    @foreach($allSections as $secOpt)
                    <option value="{{ $secOpt }}">{{ $secOpt }}</option>
                    @endforeach
                </select>
                @endif

                <!-- 4. Filter Position -->
                @if(!empty($allPositions) && count($allPositions) > 0)
                <select id="penyesuaianFilterPosition" x-model="filterPosition" class="text-xs border border-slate-300 rounded-none px-2.5 py-1.5 font-semibold text-slate-700 focus:outline-none focus:border-[#92302e] shrink-0">
                    <option value="ALL">Position</option>
                    @foreach($allPositions as $posOpt)
                    <option value="{{ $posOpt }}">{{ $posOpt }}</option>
                    @endforeach
                </select>
                @endif

                <!-- 5. Filter Jenjang KJ -->
                <select id="penyesuaianFilterKj" x-model="filterKj" class="text-xs border border-slate-300 rounded-none px-2.5 py-1.5 font-semibold text-slate-700 focus:outline-none focus:border-[#92302e] shrink-0">
                    <option value="ALL">KJ / Golongan</option>
                    @foreach($kjSummaryCards as $kCode => $kjInfo)
                    <option value="{{ $kCode }}">{{ $kCode }}</option>
                    @endforeach
                </select>

                <!-- Filter Department (jika multi-dept) -->
                @if(count($allDepts) > 1)
                <select id="penyesuaianFilterDept" x-model="filterDept" class="text-xs border border-slate-300 rounded-none px-2.5 py-1.5 font-semibold text-slate-700 focus:outline-none focus:border-[#92302e] shrink-0">
                    <option value="ALL">Department</option>
                    @foreach($allDepts as $d)
                    <option value="{{ $d }}">{{ $d }}</option>
                    @endforeach
                </select>
                @endif

                <button type="button"
                        x-show="searchQuery !== '' || filterStatus !== 'all' || filterSection !== 'ALL' || filterPosition !== 'ALL' || filterKj !== 'ALL' || filterDept !== 'ALL'"
                        @click="searchQuery = ''; filterStatus = 'all'; filterSection = 'ALL'; filterPosition = 'ALL'; filterKj = 'ALL'; filterDept = 'ALL'"
                        class="px-2.5 py-1.5 text-xs font-bold text-[#92302e] bg-rose-50 hover:bg-rose-100 border border-rose-300 rounded-none cursor-pointer transition shadow-2xs shrink-0 flex items-center gap-1 whitespace-nowrap">
                    <span>✕</span> Reset
                </button>
            </div>

            <!-- Quick Counter Summary -->
            <div class="text-xs font-medium text-slate-600 flex items-center gap-3">
                <span>Total: <strong class="text-slate-900" x-text="filteredKaryawan.length"></strong> karyawan</span>
                <span>•</span>
                <span>Disesuaikan: <strong class="text-[#92302e]" x-text="countAdjusted"></strong> orang</span>
            </div>
        </div>

        <!-- Table Karyawan -->
        <div class="overflow-x-auto border border-slate-300">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-100 text-slate-700 font-black uppercase tracking-wider text-[11px] border-b border-slate-300 whitespace-nowrap">
                        <th class="py-2.5 px-3 border-r border-slate-300 text-center w-12">NO</th>
                        <th class="py-2.5 px-3 border-r border-slate-300 min-w-[200px]">NRP &amp; NAMA LENGKAP</th>
                        <th class="py-2.5 px-3 border-r border-slate-300 text-center min-w-[80px]">KJ</th>
                        <th class="py-2.5 px-3 border-r border-slate-300 text-center w-28">DEPARTMENT</th>
                        <th class="py-2.5 px-3 border-r border-slate-300 text-center w-32">SECTION</th>
                        <th class="py-2.5 px-3 border-r border-slate-300 text-center min-w-[130px]">POSITION</th>
                        <th class="py-2.5 px-3 border-r border-slate-300 text-center w-28">NILAI FOREMAN</th>
                        <th class="py-2.5 px-3 border-r border-slate-300 text-center w-28">NILAI SECTION HEAD</th>
                        <th class="py-2.5 px-3 border-r border-slate-300 text-center w-28">{{ $isDeptMode ? 'NILAI DEPT HEAD (ANDA)' : 'NILAI DEPT HEAD' }}</th>
                        <th class="py-2.5 px-3 border-r border-slate-300 text-center min-w-[100px]">GRADE ASLI</th>
                        <th class="py-2.5 px-3 border-r border-slate-300 text-center min-w-[230px] bg-red-50/50 text-[#92302e]">
                            GRADE FINAL (DOCKING KUOTA)
                        </th>
                        <th class="py-2.5 px-3 border-r border-slate-300 text-center min-w-[185px]">STATUS KUOTA</th>
                        <th class="py-2.5 px-3 text-center min-w-[130px]">AKSI</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <template x-for="(k, idx) in filteredKaryawan" :key="k.doc_id || k.nrp">
                        <tr class="hover:bg-slate-50 transition-colors"
                            :class="{
                                'bg-rose-50/40': getKaryawanRowStatus(k).status === 'over',
                                'bg-amber-50/30': k.is_adjusted && getKaryawanRowStatus(k).status !== 'over'
                            }">
                            <!-- No -->
                            <td class="py-2.5 px-3 border-r border-slate-300 text-center font-bold text-slate-500" x-text="idx + 1"></td>

                            <!-- NRP & Nama -->
                            <td class="py-2.5 px-3 border-r border-slate-300">
                                <div class="font-black text-slate-900 text-xs flex items-center gap-1.5 flex-wrap">
                                    <a :href="k.form_url" class="hover:text-[#92302e] hover:underline" title="Buka Form Penilaian">
                                        <span x-text="k.nama"></span>
                                    </a>
                                    <span x-show="k.is_self" class="px-1.5 py-0.2 bg-slate-200 text-slate-700 text-[9px] font-black uppercase">Anda</span>
                                </div>
                                <div class="text-[11px] text-slate-500 font-medium mt-0.5">
                                    NRP: <a :href="k.form_url" class="font-semibold text-slate-700 hover:text-[#92302e] hover:underline font-mono" x-text="k.nrp"></a> • <span x-text="k.jabatan"></span>
                                </div>
                            </td>

                            <!-- KJ (Jenjang Kepangkatan Operasional) -->
                            <td class="py-2.5 px-3 border-r border-slate-300 text-center whitespace-nowrap">
                                <span class="px-2.5 py-1 text-xs font-black bg-[#92302e] text-white rounded-none shadow-2xs inline-block whitespace-nowrap"
                                      x-text="k.kj || '-'"></span>
                            </td>

                            <!-- Department -->
                            <td class="py-2.5 px-3 border-r border-slate-300 text-slate-800 text-center font-bold text-xs whitespace-nowrap" x-text="k.departemen || '-'"></td>

                            <!-- Section -->
                            <td class="py-2.5 px-3 border-r border-slate-300 text-slate-600 text-center text-xs font-medium whitespace-nowrap" x-text="(k.seksi && k.seksi !== k.departemen) ? k.seksi : '-'"></td>

                            <!-- Position -->
                            <td class="py-2.5 px-3 border-r border-slate-300 text-slate-800 text-center text-xs font-bold whitespace-nowrap" x-text="k.position || k.jabatan || '-'"></td>

                            <!-- Nilai Foreman -->
                            <td class="py-2.5 px-3 border-r border-slate-300 text-center">
                                <template x-if="k.foreman_score_formatted && k.foreman_score_formatted !== '-'">
                                    <div class="inline-flex flex-col items-center gap-0.5">
                                        <span class="font-black text-slate-900 text-xs px-1.5 py-0.5 bg-sky-50 border border-sky-200 font-mono" x-text="k.foreman_score_formatted"></span>
                                        <span class="text-[10px] font-black text-sky-800" x-text="'Rank ' + k.foreman_grade"></span>
                                    </div>
                                </template>
                                <template x-if="!k.foreman_score_formatted || k.foreman_score_formatted === '-'">
                                    <span class="text-slate-400 font-bold">-</span>
                                </template>
                            </td>

                            <!-- Nilai Section Head -->
                            <td class="py-2.5 px-3 border-r border-slate-300 text-center">
                                <template x-if="k.section_score_formatted && k.section_score_formatted !== '-'">
                                    <div class="inline-flex flex-col items-center gap-0.5">
                                        <span class="font-black text-slate-900 text-xs px-1.5 py-0.5 bg-teal-50 border border-teal-200 font-mono" x-text="k.section_score_formatted"></span>
                                        <span class="text-[10px] font-black text-teal-800" x-text="'Rank ' + k.section_grade"></span>
                                    </div>
                                </template>
                                <template x-if="!k.section_score_formatted || k.section_score_formatted === '-'">
                                    <span class="text-slate-400 font-bold">-</span>
                                </template>
                            </td>

                            <!-- Nilai Dept Head -->
                            <td class="py-2.5 px-3 border-r border-slate-300 text-center">
                                <template x-if="k.dept_score_formatted && k.dept_score_formatted !== '-'">
                                    <div class="inline-flex flex-col items-center gap-0.5">
                                        <span class="font-black text-slate-900 text-xs px-1.5 py-0.5 bg-amber-50 border border-amber-200 font-mono" x-text="k.dept_score_formatted"></span>
                                        <span class="text-[10px] font-black text-amber-800" x-text="'Rank ' + k.dept_grade"></span>
                                    </div>
                                </template>
                                <template x-if="!k.dept_score_formatted || k.dept_score_formatted === '-'">
                                    <span class="text-slate-400 font-bold">-</span>
                                </template>
                            </td>

                            <!-- Grade Asli -->
                            <td class="py-2.5 px-3 border-r border-slate-300 text-center whitespace-nowrap">
                                <template x-if="k.original_grade && k.original_grade !== '-'">
                                    <span class="px-2.5 py-0.5 text-xs font-black rounded-none border inline-block whitespace-nowrap"
                                          :class="badgeColor(k.original_grade)" x-text="'Rank ' + k.original_grade"></span>
                                </template>
                                <template x-if="!k.original_grade || k.original_grade === '-'">
                                    <span class="text-slate-400 font-bold">-</span>
                                </template>
                            </td>

                            <!-- Grade Final (Docking Selector) -->
                            <td class="py-2.5 px-3 border-r border-slate-300 text-center bg-red-50/20 whitespace-nowrap">
                                <template x-if="k.is_ready && (k.has_score || (k.final_grade && k.final_grade !== '-'))">
                                    <div>
                                        <div class="inline-flex items-center border border-slate-300 rounded-none overflow-hidden shadow-2xs">
                                            <template x-for="opt in ['S', 'A', 'B', 'C', 'K']" :key="opt">
                                                <button type="button"
                                                        @click="changeGrade(k, opt)"
                                                        class="px-3 py-1 font-black text-xs transition-all border-r last:border-r-0 cursor-pointer"
                                                        :class="k.final_grade === opt ? activeBtnClass(opt) : 'bg-white text-slate-700 hover:bg-slate-100 border-slate-200'"
                                                        x-text="opt">
                                                </button>
                                            </template>
                                        </div>

                                        <!-- Hidden input for form submit -->
                                        <input type="hidden" :name="'docking[' + k.doc_id + ']'" :value="k.final_grade">

                                        <div x-show="k.final_grade && k.original_grade && k.original_grade !== '-' && k.final_grade !== k.original_grade" class="text-[10px] font-bold text-amber-800 mt-1 whitespace-nowrap">
                                            ✏️ Disesuaikan (<span x-text="k.original_grade"></span> ➔ <span x-text="k.final_grade"></span>)
                                        </div>
                                    </div>
                                </template>
                                <template x-if="!k.is_ready">
                                    <span class="px-2.5 py-0.5 text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-300 inline-block whitespace-nowrap"
                                          x-text="k.waiting_label || '{{ $isDeptMode ? '⏳ Menunggu For / SH' : '⏳ Menunggu SH / DH' }}'">
                                    </span>
                                </template>
                                <template x-if="k.is_ready && !k.has_score && (!k.final_grade || k.final_grade === '-')">
                                    <span class="text-slate-400 font-bold">-</span>
                                </template>
                            </td>

                            <!-- Status Kuota Baris -->
                            <td class="py-2.5 px-3 border-r border-slate-300 text-center whitespace-nowrap">
                                <span class="px-2.5 py-0.5 text-[10px] font-black border inline-flex items-center gap-1 whitespace-nowrap"
                                      :class="getKaryawanRowStatus(k).class"
                                      x-text="getKaryawanRowStatus(k).text">
                                </span>
                            </td>

                            <!-- Form & Aksi Cepat -->
                            <td class="py-2.5 px-3 text-center whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5">
                                    <!-- Tombol Menuju Form Penilaian Khusus Division Head -->
                                    <template x-if="k.is_ready">
                                        <a :href="k.form_url"
                                           class="px-2.5 py-1 bg-[#92302e] hover:bg-[#7e2524] text-white font-black text-[10.5px] border border-[#781b1f] shadow-2xs inline-flex items-center gap-1 transition cursor-pointer"
                                           title="Buka Formulir untuk Melakukan Penilaian / Penyesuaian Ulang Khusus {{ $isDeptMode ? 'Department Head' : 'Division Head' }}">
                                            <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                            <span>Form &rarr;</span>
                                        </a>
                                    </template>
                                    <template x-if="!k.is_ready">
                                        <a :href="k.form_url"
                                           class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-[10.5px] border border-slate-300 shadow-2xs inline-flex items-center gap-1 transition cursor-pointer"
                                           title="Lihat Dokumen Penilaian (Terkunci - Menunggu Section Head & Dept Head)">
                                            <svg class="w-3 h-3 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            <span>Lihat</span>
                                        </a>
                                    </template>

                                    <template x-if="k.is_ready && currentCounts[k.final_grade] > targetQuota[k.final_grade]">
                                        <button type="button" @click="downgradeGrade(k)"
                                                title="Sesuaikan ke rank yang ada slot"
                                                class="px-2 py-1 bg-amber-500 hover:bg-amber-600 text-slate-950 font-black text-[10px] border border-amber-600 shadow-2xs inline-flex items-center gap-1 cursor-pointer">
                                            <span>Sesuaikan ➔</span>
                                        </button>
                                    </template>
                                    <template x-if="k.is_ready && k.final_grade && k.final_grade !== k.original_grade">
                                        <button type="button" @click="resetGrade(k)"
                                                title="Kembalikan ke Grade Asli"
                                                class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-[10px] border border-slate-300 shadow-2xs cursor-pointer">
                                            Reset
                                        </button>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    </template>

                    <tr x-show="filteredKaryawan.length === 0">
                        <td colspan="13" class="text-center py-8 text-slate-500 text-xs">
                            Tidak ada data karyawan yang cocok dengan kriteria filter.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Bottom Action Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-3 border-t border-slate-200">
            <div class="text-xs text-slate-500">
                Klik tombol grade pada kolom <strong>Grade Final (Docking Kuota)</strong> untuk mengubah grade karyawan. Kuota akan diverifikasi secara otomatis.
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('penilaian.div') }}" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs border border-slate-300">
                    Batal / Kembali
                </a>

                <button type="button" @click="submitAll()"
                        class="px-4 py-2 bg-[#92302e] hover:bg-[#781b1f] text-white font-black text-xs border border-[#781b1f] shadow-sm flex items-center gap-2 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span>Simpan &amp; Terapkan Penyesuaian</span>
                </button>
            </div>
        </div>
    </form>

    <!-- Modal Alert Dialog Kuota Penuh (Muncul saat user mencoba memilih rank yang over) -->
    <div x-show="showQuotaFullModal" x-cloak
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-2xs flex items-center justify-center p-4">
        <div class="bg-white rounded-none border-2 border-rose-600 max-w-md w-full shadow-2xl p-5 space-y-4 animate-in fade-in zoom-in duration-150">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-none bg-rose-600 text-white font-black flex items-center justify-center text-lg flex-shrink-0">
                    !
                </div>
                <div>
                    <h3 class="text-base font-black text-slate-900 tracking-tight">
                        <template x-if="modalIsKj">
                            <span>Maaf, Kuota Jenjang <span x-text="modalKj"></span> untuk Rank <span x-text="modalGrade"></span> Penuh / 0!</span>
                        </template>
                        <template x-if="!modalIsKj">
                            <span>Maaf, Kuota untuk Rank <span x-text="modalGrade"></span> Full!</span>
                        </template>
                    </h3>
                    <p class="text-xs text-slate-600 mt-1 leading-relaxed">
                        <template x-if="modalIsKj">
                            <span>
                                Alokasi kuota target untuk <strong>Jenjang <span x-text="modalKj"></span> Rank <span x-text="modalGrade"></span></strong> adalah 
                                <strong class="text-slate-900" x-text="modalKjTarget + ' orang'"></strong>, dan saat ini sudah terisi 
                                <strong class="text-rose-600" x-text="modalKjCurrent + ' orang'"></strong>.
                            </span>
                        </template>
                        <template x-if="!modalIsKj">
                            <span>
                                Alokasi kuota target dari Admin untuk <strong>Rank <span x-text="modalGrade"></span></strong> adalah 
                                <strong class="text-slate-900" x-text="modalTarget + ' orang'"></strong>, dan saat ini sudah terisi 
                                <strong class="text-rose-600" x-text="modalCurrent + ' orang'"></strong>.
                            </span>
                        </template>
                    </p>
                </div>
            </div>

            <div class="bg-amber-50 border border-amber-300 p-3 text-xs text-amber-900 space-y-1">
                <div class="font-bold flex items-center gap-1.5 text-amber-950">
                    <span>💡 Saran Penyesuaian:</span>
                </div>
                <p>
                    Tolong lakukan penyesuaian agar sesuai target kuota. Anda dapat memilih rank yang masih memiliki sisa kuota:
                    <template x-for="(diff, grade) in availableSlotsForPendingKj" :key="grade">
                        <span class="inline-block px-1.5 py-0.5 bg-amber-200 text-amber-950 font-black border border-amber-300 ml-1">Rank <span x-text="grade"></span> (<span x-text="diff"></span> slot)</span>
                    </template>.
                </p>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-200">
                <button type="button" @click="closeQuotaModal()"
                        class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold border border-slate-300">
                    Tutup &amp; Pilih Rank Lain
                </button>
                <button type="button" @click="autoDowngradeModal()"
                        class="px-4 py-2 bg-[#92302e] hover:bg-[#781b1f] text-white text-xs font-black border border-[#781b1f]">
                    Pindahkan ke Slot Tersedia
                </button>
            </div>
        </div>
    </div>

</div>

<script>
function penyesuaianKuotApp() {
    return {
        // Initial data passed from PHP controller
        targetQuota: @json($targetQuota),
        comparisonData: @json($comparisonData ?? []),
        kjSummary: @json($kjSummaryCards ?? []),
        karyawan: @json($rows),
        totalKaryawan: {{ count($rows) }},
        
        searchQuery: '',
        filterStatus: 'all',
        filterKj: 'ALL',
        filterSection: 'ALL',
        filterPosition: 'ALL',
        filterDept: '{{ $selectedDept ?? "ALL" }}',
        forceSaveVal: false,

        // Modal State
        showQuotaFullModal: false,
        modalGrade: '',
        modalTarget: 0,
        modalCurrent: 0,
        modalKj: '',
        modalKjTarget: 0,
        modalKjCurrent: 0,
        modalIsKj: false,
        pendingTargetKaryawan: null,

        init() {
        },

        updateComparisonChart() {
        },

        get currentCounts() {
            let counts = { S: 0, A: 0, B: 0, C: 0, K: 0 };
            this.karyawan.forEach(k => {
                if (!k.is_quota_exempt && k.is_ready && k.final_grade && k.final_grade !== '-' && counts[k.final_grade.toUpperCase()] !== undefined) {
                    counts[k.final_grade.toUpperCase()]++;
                }
            });
            return counts;
        },

        get overQuotaList() {
            let list = {};
            ['S', 'A', 'B', 'C', 'K'].forEach(g => {
                let diff = this.currentCounts[g] - this.targetQuota[g];
                if (diff > 0) {
                    list[g] = diff;
                }
            });
            return list;
        },

        get availableSlotsList() {
            let list = {};
            ['S', 'A', 'B', 'C', 'K'].forEach(g => {
                let diff = this.targetQuota[g] - this.currentCounts[g];
                if (diff > 0) {
                    list[g] = diff;
                }
            });
            return list;
        },

        get availableSlotsForPendingKj() {
            let list = {};
            if (!this.pendingTargetKaryawan) return this.availableSlotsList;
            const kKj = (this.pendingTargetKaryawan.kj || 'KJ 2').toUpperCase().trim();
            const kjTarget = this.kjSummary[kKj]?.target;
            if (!kjTarget) return this.availableSlotsList;
            const kjActual = this.getKjActualCounts(kKj);
            ['S', 'A', 'B', 'C', 'K'].forEach(g => {
                let diff = (kjTarget[g] || 0) - (kjActual[g] || 0);
                if (diff > 0) {
                    list[g] = diff;
                }
            });
            return Object.keys(list).length > 0 ? list : this.availableSlotsList;
        },

        getKaryawanRowStatus(k) {
            if (k.is_quota_exempt) {
                return { status: 'exempt', text: '✓ Bebas Kuota (07-006)', class: 'bg-sky-50 text-sky-800 border-sky-200' };
            }
            if (!k.is_ready) {
                return { status: 'waiting', text: k.waiting_label || '{{ $isDeptMode ? '⏳ Menunggu For / SH' : '⏳ Menunggu SH / DH' }}', class: 'bg-slate-100 text-slate-500 border-slate-200' };
            }
            if (!k.final_grade || k.final_grade === '-') {
                return { status: 'unrated', text: '⏳ Belum Dinilai', class: 'bg-slate-100 text-slate-500 border-slate-200' };
            }
            const g = (k.final_grade || '').toUpperCase();
            const kQuotaKj = (k.quota_kj || k.kj || 'KJ 2').toUpperCase().trim();
            const kjTarget = this.kjSummary[kQuotaKj]?.target?.[g] ?? null;
            const kjActual = this.getKjActualCounts(kQuotaKj);

            const isDivOver = this.currentCounts[g] > this.targetQuota[g];
            const isKjOver = kjTarget !== null && ((kjActual[g] || 0) > kjTarget);

            if (isKjOver) {
                return { status: 'over', text: '⚠️ Over ' + kQuotaKj + ' (' + kjActual[g] + '/' + kjTarget + ')', class: 'bg-rose-100 text-rose-800 border-rose-300' };
            }
            if (isDivOver) {
                return { status: 'over', text: '⚠️ Over {{ $isDeptMode ? 'Departemen' : 'Divisi' }} (' + this.currentCounts[g] + '/' + this.targetQuota[g] + ')', class: 'bg-rose-100 text-rose-800 border-rose-300' };
            }
            if (this.currentCounts[g] === this.targetQuota[g]) {
                return { status: 'pas', text: '✓ Sesuai Kuota', class: 'bg-emerald-100 text-emerald-800 border-emerald-300' };
            }
            return { status: 'slot', text: 'ℹ️ Ada Slot', class: 'bg-blue-100 text-blue-800 border-blue-300' };
        },

        get hasOverQuota() {
            return Object.keys(this.overQuotaList).length > 0;
        },

        get eligibleCount() {
            return this.karyawan.filter(k => !k.is_quota_exempt && k.is_ready && (k.has_score || (k.final_grade && k.final_grade !== '-'))).length;
        },

        handleAutoAdjust(e) {
            if (this.eligibleCount === 0) {
                alert('{{ $isDeptMode ? 'Tidak dapat melakukan otomatisasi: Belum ada dokumen karyawan yang selesai dinilai oleh Foreman atau Section Head.' : 'Tidak dapat melakukan otomatisasi: Belum ada dokumen karyawan yang selesai dinilai oleh Section Head dan Department Head.' }}');
                return;
            }
            if (confirm('Sistem akan otomatis menyesuaikan karyawan pada rank yang over-kuota ke rank berikutnya yang masih memiliki slot (misal S ke A). Lanjutkan otomatisasi?')) {
                e.target.submit();
            }
        },

        get countAdjusted() {
            return this.karyawan.filter(k => k.is_ready && k.final_grade && k.final_grade !== '-' && k.final_grade !== k.original_grade).length;
        },

        getKjActualCounts(kjCode) {
            let counts = { S: 0, A: 0, B: 0, C: 0, K: 0, total: 0 };
            const normalizedKj = (kjCode || '').toUpperCase().replace(/\s+/g, '');
            this.karyawan.forEach(k => {
                const kQuotaKj = (k.quota_kj || k.kj || '').toUpperCase().replace(/\s+/g, '');
                if (!k.is_quota_exempt && kQuotaKj === normalizedKj && k.is_ready) {
                    const g = (k.final_grade || '').toUpperCase();
                    if (counts[g] !== undefined) {
                        counts[g]++;
                        counts.total++;
                    }
                }
            });
            return counts;
        },

        getKjStatus(kjCode) {
            const target = this.kjSummary[kjCode]?.target || { S: 0, A: 0, B: 0, C: 0, K: 0, total: 0 };
            const actual = this.getKjActualCounts(kjCode);
            let over = 0;
            ['S', 'A', 'B', 'C', 'K'].forEach(g => {
                if (actual[g] > (target[g] || 0)) {
                    over += (actual[g] - (target[g] || 0));
                }
            });
            if (over > 0) {
                return { status: 'over', text: '⚠️ Over (+' + over + ')', class: 'bg-rose-100 text-rose-900 border-rose-300' };
            }
            if (actual.total === target.total && target.total > 0) {
                return { status: 'pas', text: '✓ Sesuai Kuota', class: 'bg-emerald-100 text-emerald-900 border-emerald-300' };
            }
            if (actual.total < target.total) {
                return { status: 'slot', text: 'Slot (' + actual.total + '/' + target.total + ')', class: 'bg-blue-100 text-blue-900 border-blue-300' };
            }
            return { status: 'pas', text: '✓ Pas (' + actual.total + ' Org)', class: 'bg-slate-100 text-slate-800 border-slate-300' };
        },

        toggleFilterKj(kjCode) {
            if (this.filterKj === kjCode) {
                this.filterKj = 'ALL';
            } else {
                this.filterKj = kjCode;
            }
        },

        get filteredKaryawan() {
            return this.karyawan.filter(k => {
                // Dept check
                if (this.filterDept !== 'ALL' && k.departemen !== this.filterDept) {
                    return false;
                }

                // KJ check
                if (this.filterKj !== 'ALL') {
                    const normalizedFilter = this.filterKj.toUpperCase().replace(/\s+/g, '');
                    const kKj = (k.kj || '').toUpperCase().replace(/\s+/g, '');
                    const kQuotaKj = (k.quota_kj || k.kj || '').toUpperCase().replace(/\s+/g, '');
                    if (kKj !== normalizedFilter && kQuotaKj !== normalizedFilter) return false;
                }

                // Section check
                if (this.filterSection !== 'ALL') {
                    const kSec = (k.seksi || '').toUpperCase().trim();
                    if (kSec !== this.filterSection.toUpperCase().trim()) return false;
                }

                // Position check
                if (this.filterPosition !== 'ALL') {
                    const kPos = (k.position || k.jabatan || '').toUpperCase().trim();
                    if (kPos !== this.filterPosition.toUpperCase().trim()) return false;
                }

                // Status check
                if (this.filterStatus === 'over') {
                    const isOverRank = k.final_grade && (this.currentCounts[k.final_grade] > this.targetQuota[k.final_grade]);
                    const isRowOver = this.getKaryawanRowStatus(k).status === 'over';
                    if (!isOverRank && !isRowOver) return false;
                } else if (this.filterStatus === 'adjusted') {
                    if (!k.final_grade || k.final_grade === k.original_grade) return false;
                } else if (this.filterStatus === 'original') {
                    if (k.final_grade && k.final_grade !== k.original_grade) return false;
                }

                // Search query
                if (this.searchQuery.trim() !== '') {
                    let q = this.searchQuery.toLowerCase();
                    let nrpMatch = (k.nrp || '').toLowerCase().includes(q);
                    let namaMatch = (k.nama || '').toLowerCase().includes(q);
                    let jabMatch = (k.jabatan || '').toLowerCase().includes(q) || (k.position || '').toLowerCase().includes(q);
                    let deptMatch = (k.departemen || '').toLowerCase().includes(q);
                    if (!nrpMatch && !namaMatch && !jabMatch && !deptMatch) return false;
                }

                return true;
            });
        },

        changeGrade(k, newGrade) {
            if (k.final_grade === newGrade) return;

            if (k.is_quota_exempt) {
                k.final_grade = newGrade;
                k.is_adjusted = (k.final_grade !== k.original_grade);
                this.updateComparisonChart();
                return;
            }

            const kQuotaKj = (k.quota_kj || k.kj || 'KJ 2').toUpperCase().trim();
            const kjData = this.kjSummary[kQuotaKj] || null;
            const kjTarget = kjData?.target?.[newGrade] ?? null;
            const kjActual = this.getKjActualCounts(kQuotaKj);

            let targetDiv = this.targetQuota[newGrade] || 0;
            let currentDiv = this.currentCounts[newGrade] || 0;

            let isKjFull = false;
            if (kjTarget !== null) {
                let curInKj = kjActual[newGrade] || 0;
                if (curInKj >= kjTarget) {
                    isKjFull = true;
                }
            }

            if (currentDiv >= targetDiv || isKjFull) {
                this.modalGrade = newGrade;
                this.modalTarget = targetDiv;
                this.modalCurrent = currentDiv;
                this.modalKj = kQuotaKj;
                this.modalKjTarget = kjTarget !== null ? kjTarget : targetDiv;
                this.modalKjCurrent = kjActual[newGrade] || 0;
                this.modalIsKj = isKjFull;
                this.pendingTargetKaryawan = k;
                this.showQuotaFullModal = true;
                return;
            }

            // Otherwise, update grade
            k.final_grade = newGrade;
            k.is_adjusted = (k.final_grade !== k.original_grade);
            this.updateComparisonChart();
        },

        closeQuotaModal() {
            this.showQuotaFullModal = false;
            this.pendingTargetKaryawan = null;
        },

        autoDowngradeModal() {
            if (!this.pendingTargetKaryawan) {
                this.closeQuotaModal();
                return;
            }
            this.downgradeGrade(this.pendingTargetKaryawan);
            this.closeQuotaModal();
        },

        downgradeGrade(k) {
            const kQuotaKj = (k.quota_kj || k.kj || 'KJ 2').toUpperCase().trim();
            const kjActual = this.getKjActualCounts(kQuotaKj);
            const kjTarget = this.kjSummary[kQuotaKj]?.target || null;

            let order = ['S', 'A', 'B', 'C', 'K'];
            let currentIdx = k.final_grade ? order.indexOf(k.final_grade) : -1;
            let nextRank = null;

            // Priority 1: Check rank after currentIdx that is available in BOTH division AND this KJ
            for (let i = currentIdx + 1; i < order.length; i++) {
                let candidate = order[i];
                let divAvail = this.currentCounts[candidate] < (this.targetQuota[candidate] || 0);
                let kjAvail = kjTarget ? ((kjActual[candidate] || 0) < (kjTarget[candidate] || 0)) : true;
                if (divAvail && kjAvail) {
                    nextRank = candidate;
                    break;
                }
            }

            // Priority 2: Check any rank that is available in BOTH division AND this KJ
            if (!nextRank) {
                for (let i = 0; i < order.length; i++) {
                    let candidate = order[i];
                    let divAvail = this.currentCounts[candidate] < (this.targetQuota[candidate] || 0);
                    let kjAvail = kjTarget ? ((kjActual[candidate] || 0) < (kjTarget[candidate] || 0)) : true;
                    if (divAvail && kjAvail) {
                        nextRank = candidate;
                        break;
                    }
                }
            }

            // Priority 3: Fallback available in this KJ
            if (!nextRank && kjTarget) {
                for (let i = 0; i < order.length; i++) {
                    let candidate = order[i];
                    if ((kjActual[candidate] || 0) < (kjTarget[candidate] || 0)) {
                        nextRank = candidate;
                        break;
                    }
                }
            }

            if (nextRank) {
                k.final_grade = nextRank;
                k.is_adjusted = (k.final_grade !== k.original_grade);
                this.updateComparisonChart();
            } else {
                alert('Semua kuota rank untuk jenjang ' + kQuotaKj + ' sudah pas atau penuh.');
            }
        },

        resetGrade(k) {
            k.final_grade = (k.original_grade && k.original_grade !== '-') ? k.original_grade : '';
            k.is_adjusted = false;
            this.updateComparisonChart();
        },

        submitAll() {
            if (this.hasOverQuota) {
                let overRanks = Object.keys(this.overQuotaList).join(', ');
                if (!confirm(`PERINGATAN: Distribusi saat ini masih melebih target kuota pada Rank ${overRanks}! Apakah Anda yakin ingin memaksakan penyimpanan kuota ini? (Disarankan untuk menyeimbangkan kuota terlebih dahulu)`)) {
                    return;
                }
                this.forceSaveVal = true;
            } else {
                this.forceSaveVal = false;
            }

            document.getElementById('formPenyesuaian').submit();
        },

        badgeColor(grade) {
            switch(grade) {
                case 'S': return 'bg-purple-100 text-purple-900 border-purple-300';
                case 'A': return 'bg-emerald-100 text-emerald-900 border-emerald-300';
                case 'B': return 'bg-blue-100 text-blue-900 border-blue-300';
                case 'C': return 'bg-amber-100 text-amber-900 border-amber-300';
                case 'K': return 'bg-rose-100 text-rose-900 border-rose-300';
                default: return 'bg-slate-100 text-slate-800 border-slate-300';
            }
        },

        activeBtnClass(grade) {
            switch(grade) {
                case 'S': return 'bg-purple-700 text-white border-purple-800 shadow-inner';
                case 'A': return 'bg-emerald-700 text-white border-emerald-800 shadow-inner';
                case 'B': return 'bg-blue-700 text-white border-blue-800 shadow-inner';
                case 'C': return 'bg-amber-600 text-white border-amber-700 shadow-inner';
                case 'K': return 'bg-rose-700 text-white border-rose-800 shadow-inner';
                default: return 'bg-[#92302e] text-white border-[#781b1f] shadow-inner';
            }
        }
    }
}
</script>
@endsection

