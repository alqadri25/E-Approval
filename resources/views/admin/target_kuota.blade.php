@extends('layouts.app', ['title' => 'Target & Kuota Nilai - Admin & Superadmin'])

@section('content')
@php
    // Group tabelKuotaKj by departemen_kode
    $kuotaByDept = [];
    foreach ($tabelKuotaKj as $row) {
        $kuotaByDept[$row['departemen_kode']][] = $row;
    }

    // Compute department stats
    $deptStats = [];
    foreach ($deptDefs as $d) {
        $dCode = $d['kode'];
        $rows = $kuotaByDept[$dCode] ?? [];
        $dEmpCount = 0;
        $dTargetSum = 0;
        $dS = $dA = $dB = $dC = $dK = 0;
        foreach ($rows as $r) {
            $dEmpCount += (int)($r['total_karyawan'] ?? 0);
            $dS += (int)($r['kuota_s'] ?? 0);
            $dA += (int)($r['kuota_a'] ?? 0);
            $dB += (int)($r['kuota_b'] ?? 0);
            $dC += (int)($r['kuota_c'] ?? 0);
            $dK += (int)($r['kuota_k'] ?? 0);
            $dTargetSum += (int)($r['total_target'] ?? 0);
        }
        $deptStats[$dCode] = [
            'headcount' => $dEmpCount,
            'target_total' => $dTargetSum,
            's' => $dS, 'a' => $dA, 'b' => $dB, 'c' => $dC, 'k' => $dK,
            'is_complete' => ($dEmpCount > 0 && $dTargetSum === $dEmpCount),
        ];
    }

    // Build DIVISION cards purely from real karyawan division data (auto-updates as divisions are added, e.g. up to 9 divisions in 3x3 grid)
    $divisionCards = [];
    foreach (($realDivisions ?? []) as $rDiv) {
        $divisionCards[] = $rDiv;
    }

    // Determine active department ('all' when nothing is explicitly selected yet)
    $requestedDept = trim((string) request('dept', ''));
    $currentDept = ($requestedDept !== '' && $requestedDept !== 'all') ? $requestedDept : 'all';
    $isDeptSelected = ($currentDept !== 'all');

    // Build aggregated "all" KJ rows from controller's $summaryKjCards
    $allSummaryKjRows = [];
    $allStatEmp = 0; $allStatS = 0; $allStatA = 0; $allStatB = 0; $allStatC = 0; $allStatK = 0; $allStatTarget = 0;
    foreach (($summaryKjCards ?? []) as $sCard) {
        $rEmp = (int)($sCard['total_karyawan'] ?? 0);
        $rS = (int)($sCard['target_s'] ?? 0);
        $rA = (int)($sCard['target_a'] ?? 0);
        $rB = (int)($sCard['target_b'] ?? 0);
        $rC = (int)($sCard['target_c'] ?? 0);
        $rK = (int)($sCard['target_k'] ?? 0);
        $rTot = $rS + $rA + $rB + $rC + $rK;
        $allSummaryKjRows[] = [
            'kj' => $sCard['kode'],
            'golongan' => $sCard['golongan'] ?? 'GOLONGAN',
            'total_karyawan' => $rEmp,
            'kuota_s' => $rS,
            'kuota_a' => $rA,
            'kuota_b' => $rB,
            'kuota_c' => $rC,
            'kuota_k' => $rK,
            'total_target' => $rTot,
        ];
        $allStatEmp += $rEmp;
        $allStatS += $rS;
        $allStatA += $rA;
        $allStatB += $rB;
        $allStatC += $rC;
        $allStatK += $rK;
        $allStatTarget += $rTot;
    }

    $activeKjRows = $isDeptSelected ? ($kuotaByDept[$currentDept] ?? []) : $allSummaryKjRows;
    $activeDeptStat = $isDeptSelected
        ? ($deptStats[$currentDept] ?? ['headcount' => 0, 'target_total' => 0, 's' => 0, 'a' => 0, 'b' => 0, 'c' => 0, 'k' => 0, 'is_complete' => false])
        : ['headcount' => $allStatEmp, 'target_total' => $allStatTarget, 's' => $allStatS, 'a' => $allStatA, 'b' => $allStatB, 'c' => $allStatC, 'k' => $allStatK, 'is_complete' => ($allStatEmp > 0 && $allStatTarget === $allStatEmp)];
    $activeDeptDef = $isDeptSelected
        ? (collect($deptDefs)->firstWhere('kode', $currentDept) ?? ['kode' => $currentDept, 'departemen' => $currentDept, 'divisi' => ''])
        : ['kode' => 'all', 'departemen' => 'Semua Departemen (All)', 'divisi' => '-'];

    $activeDivisionName = $isDeptSelected ? ($activeDeptDef['divisi'] ?? '-') : '-';
    foreach ($divisionCards as &$divCardRef) {
        $divCardRef['is_selected'] = $isDeptSelected && in_array($currentDept, $divCardRef['dept_codes'] ?? [], true);
        if ($divCardRef['is_selected']) {
            $activeDivisionName = $divCardRef['nama'];
        }
    }
    unset($divCardRef);
@endphp

<div class="space-y-6" x-data="targetKuotaApp()">

    <!-- 1. Header Banner Musashi Red (Sharp & Clean) -->
    <div class="bg-gradient-to-r from-[#962226] via-[#92302e] to-[#781b1f] text-white rounded-none p-5 sm:p-6 border border-[#781b1f] shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight">
                Target &amp; Kuota Nilai
            </h1>
            <p class="text-xs text-red-100 font-medium mt-1 max-w-3xl leading-relaxed">
                Atur target kuota grade (S, A, B, C, K) per <strong>Departemen</strong> dan per <strong>Jenjang KJ &amp; Golongan</strong>. Data kuota otomatis terhubung dan disahkan ke dashboard serta form evaluasi Foreman, Section Head, dan Department Head.
            </p>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('admin.karyawan') }}" 
               class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-none bg-white/10 hover:bg-white/20 text-white text-xs font-bold border border-white/30 transition shadow-xs">
                <svg class="w-4 h-4 text-red-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
                <span>Data Karyawan ({{ $totalPerusahaanKaryawan }})</span>
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

    <!-- 2. Navigation Tabs (Division vs Department) -->
    <div class="bg-white rounded-none border border-slate-300 shadow-sm p-1.5 flex items-center justify-between gap-2">
        <div class="flex items-center gap-1">
            <a href="{{ route(request()->is('superadmin*') ? 'superadmin.targetDivisi' : 'admin.targetDivisi') }}" 
               class="inline-flex items-center gap-2 px-4 py-2 text-xs font-bold uppercase tracking-wider rounded-none bg-white hover:bg-slate-100 text-slate-700 transition border border-transparent hover:border-slate-300">
                <span>🏢 DIVISION (Form 07-006)</span>
            </a>
            <a href="{{ route(request()->is('superadmin*') ? 'superadmin.targetKuota' : 'admin.targetKuota') }}" 
               class="inline-flex items-center gap-2 px-4 py-2 text-xs font-bold uppercase tracking-wider rounded-none bg-[#92302e] text-white shadow-xs border border-[#92302e]">
                <span>📁 DEPARTMENT</span>
            </a>
        </div>
        <div class="text-[11px] text-slate-500 font-mono hidden sm:block pr-3">
            Target Headcount &amp; KJ Matrix
        </div>
    </div>

    <!-- 2. Scope Indicator & Summary Cards (Interaktif mengikuti Division/Department yang dipilih) -->
    <div class="space-y-2">
        <div class="flex items-center justify-between gap-2 px-0.5">
            <div class="flex items-center gap-2">
                <span class="inline-block w-2.5 h-2.5 bg-[#92302e]"></span>
                <span class="text-xs font-bold text-slate-700">
                    Cakupan Ringkasan Data: <span class="text-[#92302e] font-black" x-text="currentScopeStat.scope_label">{{ $isDeptSelected ? ('Departemen ' . $currentDept) : 'Seluruh Perusahaan' }}</span>
                </span>
            </div>
            <template x-if="currentScopeKey !== 'all'">
                <button type="button" 
                        @click="resetToAllScope()"
                        class="text-[11px] font-bold text-[#92302e] hover:text-[#7a2423] underline flex items-center gap-1 cursor-pointer">
                    <span>↺</span>
                    <span>Tampilkan Seluruh Perusahaan (All)</span>
                </button>
            </template>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            <div class="bg-white rounded-none p-3.5 border border-slate-300 shadow-2xs">
                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Total Karyawan</span>
                <div class="mt-1 flex items-baseline gap-1.5">
                    <span class="text-2xl font-black text-slate-900" x-text="currentScopeStat.total_karyawan">{{ $topSummaryStats['total_karyawan'] ?? $totalPerusahaanKaryawan }}</span>
                    <span class="text-xs font-medium text-slate-500">karyawan</span>
                </div>
                <span class="text-[10px] text-slate-500 mt-0.5 block truncate" x-text="currentScopeStat.karyawan_subtitle">{{ $topSummaryStats['kena_target'] ?? $totalPerusahaanKaryawan }} Kena Target &bull; {{ $topSummaryStats['bebas_target'] ?? ($totalExempt07006 ?? 0) }} Bebas Target</span>
            </div>
            <div class="bg-white rounded-none p-3.5 border border-slate-300 shadow-2xs">
                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Total Division</span>
                <div class="mt-1 flex items-baseline gap-1.5">
                    <span class="text-2xl font-black text-[#92302e]" x-text="currentScopeStat.total_division">{{ $topSummaryStats['total_division'] ?? count($divisionCards) }}</span>
                    <span class="text-xs font-medium text-slate-500">division</span>
                </div>
                <span class="text-[10px] text-slate-500 mt-0.5 block truncate" x-text="currentScopeStat.division_karyawan + ' Jumlah Karyawan'">{{ $topSummaryStats['division_karyawan'] ?? 0 }} Jumlah Karyawan</span>
            </div>
            <div class="bg-white rounded-none p-3.5 border border-slate-300 shadow-2xs">
                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Total Department</span>
                <div class="mt-1 flex items-baseline gap-1.5">
                    <span class="text-2xl font-black text-slate-800" x-text="currentScopeStat.total_department">{{ $topSummaryStats['total_department'] ?? count($deptDefs) }}</span>
                    <span class="text-xs font-medium text-slate-500">departemen</span>
                </div>
                <span class="text-[10px] text-slate-500 mt-0.5 block truncate" x-text="currentScopeStat.department_karyawan + ' Jumlah Karyawan'">{{ $topSummaryStats['department_karyawan'] ?? 0 }} Jumlah Karyawan</span>
            </div>
            <div class="bg-white rounded-none p-3.5 border border-slate-300 shadow-2xs">
                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Total Section</span>
                <div class="mt-1 flex items-baseline gap-1.5">
                    <span class="text-2xl font-black text-slate-800" x-text="currentScopeStat.total_section">{{ $topSummaryStats['total_section'] ?? 0 }}</span>
                    <span class="text-xs font-medium text-slate-500">section</span>
                </div>
                <span class="text-[10px] text-slate-500 mt-0.5 block truncate" x-text="currentScopeStat.section_karyawan + ' Jumlah Karyawan'">{{ $topSummaryStats['section_karyawan'] ?? 0 }} Jumlah Karyawan</span>
            </div>
            <div class="bg-white rounded-none p-3.5 border border-slate-300 shadow-2xs col-span-2 sm:col-span-1">
                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Total Foreman</span>
                <div class="mt-1 flex items-baseline gap-1.5">
                    <span class="text-2xl font-black text-slate-800" x-text="currentScopeStat.total_foreman">{{ $topSummaryStats['total_foreman'] ?? 0 }}</span>
                    <span class="text-xs font-medium text-slate-500">foreman</span>
                </div>
                <span class="text-[10px] text-slate-500 mt-0.5 block truncate" x-text="currentScopeStat.foreman_subordinates + ' Jumlah Karyawan'">{{ $topSummaryStats['foreman_subordinates'] ?? 0 }} Jumlah Karyawan</span>
            </div>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- BAGIAN UTAMA: PENGATURAN TARGET KUOTA PER DEPARTEMEN & KJ      -->
    <!-- (Clean & Minimalist: Pilih Division -> Pilih Dept -> Atur KJ)  -->
    <!-- ============================================================== -->
    <div class="space-y-6">

        <!-- 3. Division Selector — 3 Cards Per Row, Klik Card untuk Pilih Departemen -->
        <div class="bg-white rounded-none border border-slate-300 p-4 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-3.5 pb-2.5 border-b border-slate-200">
                <div class="flex items-center gap-2">
                    <span class="text-sm">🏭</span>
                    <span class="text-xs font-black text-slate-900 uppercase tracking-wider">Pilih Division untuk Pengaturan Target</span>
                </div>
                <span class="text-[11px] font-semibold text-slate-600">
                    @if($isDeptSelected)
                        Division Aktif: <strong class="text-slate-900">{{ $activeDivisionName }}</strong>
                        &bull;
                        Departemen Diatur: <strong class="text-[#92302e]">{{ $activeDeptDef['departemen'] ?? $currentDept }}</strong>
                    @else
                        Tampilan Saat Ini: <strong class="text-[#92302e]">Semua Departemen (All)</strong>
                    @endif
                </span>
            </div>

            @if(count($divisionCards) > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($divisionCards as $card)
                        @php
                            $isCurrentDeptDiv = !empty($card['is_selected']);
                            $isComplete = !empty($card['is_complete']);
                            $hasTarget = ((int)($card['target_total'] ?? 0)) > 0;
                        @endphp
                        <button type="button"
                                data-div-name="{{ $card['nama'] }}"
                                @click="selectDivision($el.dataset.divName)"
                                :class="(deptPickerOpen && selectedDivision === $el.dataset.divName)
                                    ? 'border-[#92302e] bg-[#92302e] text-white shadow-md ring-2 ring-[#92302e]/30'
                                    : ({{ $isCurrentDeptDiv ? 'true' : 'false' }}
                                        ? 'border-[#92302e] bg-red-50/40 hover:bg-red-50/70 text-slate-800 shadow-sm'
                                        : 'border-slate-300 bg-white hover:border-[#92302e] hover:shadow-sm text-slate-800')"
                                class="w-full text-left border rounded-none transition-all flex flex-col justify-between cursor-pointer focus:outline-none">

                            <div class="w-full flex-1">
                                {{-- Card Header --}}
                                <div class="px-3 py-2 border-b flex items-center justify-between gap-2"
                                     :class="(deptPickerOpen && selectedDivision === $el.closest('button').dataset.divName)
                                        ? 'border-[#7a2423] bg-[#7a2423]/40'
                                        : 'border-slate-200 bg-slate-50'">
                                    <span class="text-[11px] font-black uppercase tracking-wide truncate"
                                          :class="(deptPickerOpen && selectedDivision === $el.closest('button').dataset.divName) ? 'text-white' : 'text-slate-900'">
                                        {{ $card['nama'] }}
                                    </span>
                                    <div class="flex items-center gap-1 flex-shrink-0">
                                        @if($isComplete)
                                            <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-none"
                                                  :class="(deptPickerOpen && selectedDivision === $el.closest('button').dataset.divName) ? 'bg-emerald-400 text-emerald-950' : 'bg-emerald-100 text-emerald-800 border border-emerald-300'">
                                                ✓ 100%
                                            </span>
                                        @elseif($hasTarget)
                                            <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-none"
                                                  :class="(deptPickerOpen && selectedDivision === $el.closest('button').dataset.divName) ? 'bg-amber-300 text-slate-900' : 'bg-amber-100 text-amber-800 border border-amber-300'">
                                                Parsial
                                            </span>
                                        @else
                                            <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-none"
                                                  :class="(deptPickerOpen && selectedDivision === $el.closest('button').dataset.divName) ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-600'">
                                                Belum Diatur
                                            </span>
                                        @endif
                                        @if($isCurrentDeptDiv)
                                            <span class="text-[9px] font-black px-1.5 py-0.5 rounded-none uppercase tracking-wider"
                                                  :class="(deptPickerOpen && selectedDivision === $el.closest('button').dataset.divName) ? 'bg-white text-[#92302e]' : 'bg-[#92302e] text-white'">
                                                AKTIF
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Card Body --}}
                                <div class="p-3 space-y-2">
                                    {{-- 3 Summary Metrics --}}
                                    <div class="grid grid-cols-3 gap-1.5">
                                        <div class="text-center py-1.5 px-1 rounded-none border"
                                             :class="(deptPickerOpen && selectedDivision === $el.closest('button').dataset.divName) ? 'bg-white/10 border-white/15' : 'bg-slate-50 border-slate-200'">
                                            <div class="text-xs font-black leading-tight"
                                                 :class="(deptPickerOpen && selectedDivision === $el.closest('button').dataset.divName) ? 'text-white' : 'text-slate-900'">
                                                {{ $card['total_karyawan'] }}
                                            </div>
                                            <div class="text-[9px] font-medium mt-0.5"
                                                 :class="(deptPickerOpen && selectedDivision === $el.closest('button').dataset.divName) ? 'text-red-100' : 'text-slate-500'">
                                                Total Karyawan
                                            </div>
                                        </div>
                                        <div class="text-center py-1.5 px-1 rounded-none border"
                                             :class="(deptPickerOpen && selectedDivision === $el.closest('button').dataset.divName) ? 'bg-white/10 border-white/15' : 'bg-slate-50 border-slate-200'">
                                            <div class="text-xs font-black leading-tight"
                                                 :class="(deptPickerOpen && selectedDivision === $el.closest('button').dataset.divName) ? 'text-amber-300' : 'text-[#92302e]'">
                                                {{ $card['target_total'] }}
                                            </div>
                                            <div class="text-[9px] font-medium mt-0.5"
                                                 :class="(deptPickerOpen && selectedDivision === $el.closest('button').dataset.divName) ? 'text-red-100' : 'text-slate-500'">
                                                Target ({{ $card['kena_target'] }})
                                            </div>
                                        </div>
                                        <div class="text-center py-1.5 px-1 rounded-none border"
                                             :class="(deptPickerOpen && selectedDivision === $el.closest('button').dataset.divName) ? 'bg-white/10 border-white/15' : 'bg-slate-50 border-slate-200'">
                                            <div class="text-xs font-black leading-tight"
                                                 :class="(deptPickerOpen && selectedDivision === $el.closest('button').dataset.divName) ? 'text-white' : 'text-slate-800'">
                                                {{ count($card['depts']) }}
                                            </div>
                                            <div class="text-[9px] font-medium mt-0.5"
                                                 :class="(deptPickerOpen && selectedDivision === $el.closest('button').dataset.divName) ? 'text-red-100' : 'text-slate-500'">
                                                Departemen
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Role Breakdown (Compact, Small Fonts) --}}
                                    <div class="grid grid-cols-2 gap-1 pt-1.5 border-t text-[10px]"
                                         :class="(deptPickerOpen && selectedDivision === $el.closest('button').dataset.divName) ? 'border-white/20' : 'border-slate-200'">
                                        <div class="flex items-center justify-between px-2 py-1 border"
                                             :class="(deptPickerOpen && selectedDivision === $el.closest('button').dataset.divName) ? 'bg-white/10 text-white border-white/15' : 'bg-slate-50 text-slate-700 border-slate-200'">
                                            <span>Foreman</span>
                                            <strong>{{ $card['foreman'] }}</strong>
                                        </div>
                                        <div class="flex items-center justify-between px-2 py-1 border"
                                             :class="(deptPickerOpen && selectedDivision === $el.closest('button').dataset.divName) ? 'bg-white/10 text-white border-white/15' : 'bg-slate-50 text-slate-700 border-slate-200'">
                                            <span>Section Head</span>
                                            <strong>{{ $card['section_head'] }}</strong>
                                        </div>
                                        <div class="flex items-center justify-between px-2 py-1 border"
                                             :class="(deptPickerOpen && selectedDivision === $el.closest('button').dataset.divName) ? 'bg-white/10 text-white border-white/15' : 'bg-slate-50 text-slate-700 border-slate-200'">
                                            <span>Dept Head</span>
                                            <strong>{{ $card['dept_head'] }}</strong>
                                        </div>
                                        <div class="flex items-center justify-between px-2 py-1 border"
                                             :class="(deptPickerOpen && selectedDivision === $el.closest('button').dataset.divName) ? 'bg-white/10 text-white border-white/15' : 'bg-slate-50 text-slate-700 border-slate-200'">
                                            <span>Leader</span>
                                            <strong>{{ $card['leader'] }}</strong>
                                        </div>
                                        @if(($card['div_head'] ?? 0) > 0)
                                            <div class="col-span-2 flex items-center justify-between px-2 py-1 border"
                                                 :class="(deptPickerOpen && selectedDivision === $el.closest('button').dataset.divName) ? 'bg-white/10 text-white border-white/15' : 'bg-slate-50 text-slate-700 border-slate-200'">
                                                <span>Division Head</span>
                                                <strong>{{ $card['div_head'] }}</strong>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            {{-- Card Footer: Prompt to click and choose department --}}
                            <div class="w-full px-3 py-2 border-t flex items-center justify-end gap-1.5"
                                 :class="(deptPickerOpen && selectedDivision === $el.closest('button').dataset.divName) ? 'border-[#7a2423] bg-[#7a2423]/30 text-white' : 'border-slate-200 bg-slate-50 text-slate-600'">
                                <span class="text-[9.5px] font-black uppercase tracking-wide flex-shrink-0"
                                      :class="(deptPickerOpen && selectedDivision === $el.closest('button').dataset.divName) ? 'text-amber-300' : 'text-[#92302e]'"
                                      x-text="(deptPickerOpen && selectedDivision === $el.closest('button').dataset.divName) ? '▾ Pilih Dept di Bawah' : 'Pilih Departemen ▸'">
                                    Pilih Departemen ▸
                                </span>
                            </div>
                        </button>
                    @endforeach
                </div>

                {{-- Department Selection Drawer (appears when a Division Card is clicked) --}}
                @php
                    $routeName = request()->is('superadmin*') ? 'superadmin.targetKuota' : 'admin.targetKuota';
                @endphp
                <div x-show="deptPickerOpen" x-cloak
                     id="deptPickerPanel"
                     class="mt-4 border-2 border-[#92302e] bg-red-50/30 rounded-none p-3.5 transition-all">
                    @foreach($divisionCards as $card)
                        <div x-show="selectedDivision === @js($card['nama'])">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pb-2.5 mb-3 border-b border-red-200">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-xs font-black text-[#92302e] uppercase tracking-wider">
                                        📂 Pilih Departemen yang Mau Diatur — {{ $card['nama'] }}
                                    </span>
                                    <span class="text-[10px] font-bold px-2 py-0.5 bg-white text-slate-700 border border-slate-300">
                                        {{ count($card['depts']) }} Departemen
                                    </span>
                                </div>
                                <button type="button" @click="closeDeptPicker()"
                                        class="self-start sm:self-auto text-[10px] font-bold px-2.5 py-1 bg-white hover:bg-slate-100 text-slate-600 border border-slate-300 cursor-pointer">
                                    ✕ Tutup Pilihan
                                </button>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
                                @forelse($card['depts'] as $dp)
                                    @php
                                        $dpIsActive = ($dp['kode'] === $currentDept);
                                        $dpUrl = route($routeName, ['dept' => $dp['kode']]) . '#matrixTargetKj';
                                    @endphp
                                    <a href="{{ $dpUrl }}"
                                       class="block border rounded-none p-3 transition group
                                              {{ $dpIsActive
                                                 ? 'border-[#92302e] bg-[#92302e] text-white shadow-sm'
                                                 : 'border-slate-300 bg-white hover:border-[#92302e] hover:shadow-sm text-slate-800' }}">
                                        <div class="flex items-center justify-between gap-2 mb-2">
                                            <div class="min-w-0">
                                                <div class="text-xs font-black uppercase tracking-wide truncate {{ $dpIsActive ? 'text-white' : 'text-slate-900' }}">
                                                    {{ $dp['kode'] }}
                                                </div>
                                                <div class="text-[10px] truncate {{ $dpIsActive ? 'text-red-100' : 'text-slate-500' }}">
                                                    {{ $dp['nama'] }}
                                                </div>
                                            </div>
                                            @if($dpIsActive)
                                                <span class="text-[9px] font-black px-1.5 py-0.5 bg-white text-[#92302e] uppercase flex-shrink-0">SEDANG DIATUR</span>
                                            @elseif(!empty($dp['is_complete']))
                                                <span class="text-[9px] font-bold px-1.5 py-0.5 bg-emerald-100 text-emerald-800 border border-emerald-300 flex-shrink-0">✓ 100%</span>
                                            @else
                                                <span class="text-[9px] font-bold px-1.5 py-0.5 bg-slate-100 text-[#92302e] border border-slate-300 group-hover:bg-[#92302e] group-hover:text-white flex-shrink-0">Pilih →</span>
                                            @endif
                                        </div>

                                        <div class="grid grid-cols-3 gap-1 text-center text-[10px] pt-2 border-t {{ $dpIsActive ? 'border-white/20' : 'border-slate-200' }}">
                                            <div class="py-1 {{ $dpIsActive ? 'bg-white/10' : 'bg-slate-50' }}">
                                                <div class="font-black {{ $dpIsActive ? 'text-white' : 'text-slate-900' }}">{{ $dp['total_karyawan'] ?? $dp['kena_target'] }}</div>
                                                <div class="text-[9px] {{ $dpIsActive ? 'text-red-100' : 'text-slate-500' }}">Karyawan</div>
                                            </div>
                                            <div class="py-1 {{ $dpIsActive ? 'bg-white/10' : 'bg-slate-50' }}">
                                                <div class="font-black {{ $dpIsActive ? 'text-amber-300' : 'text-[#92302e]' }}">{{ $dp['target_total'] }} / {{ $dp['kena_target'] }}</div>
                                                <div class="text-[9px] {{ $dpIsActive ? 'text-red-100' : 'text-slate-500' }}">Target</div>
                                            </div>
                                            <div class="py-1 {{ $dpIsActive ? 'bg-white/10' : 'bg-slate-50' }}">
                                                <div class="font-black {{ $dpIsActive ? 'text-white' : 'text-slate-800' }}">{{ $dp['foreman'] ?? 0 }} / {{ $dp['section_head'] ?? 0 }}</div>
                                                <div class="text-[9px] {{ $dpIsActive ? 'text-red-100' : 'text-slate-500' }}">FM / Sect</div>
                                            </div>
                                        </div>
                                    </a>
                                @empty
                                    <div class="col-span-3 p-3 text-xs text-slate-500 italic bg-white border border-slate-200">
                                        Belum ada departemen terdaftar di division ini.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="p-6 text-center bg-slate-50 border border-dashed border-slate-300 text-xs text-slate-500">
                    Belum ada data Division pada Data Karyawan. Tambahkan atau impor data karyawan terlebih dahulu di menu <strong>Data Karyawan</strong>.
                </div>
            @endif
        </div>

        <!-- 4. Formulir Matrix Target Kuota per KJ (All secara default, atau per Departemen terpilih) -->
        <div class="bg-white rounded-none border border-slate-300 shadow-sm" id="matrixTargetKj">
            
            <!-- Header Bar Merah Musashi -->
            <div class="bg-[#92302e] text-white px-5 py-3.5 rounded-none border-b border-[#7a2423] flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-none bg-white/10 flex items-center justify-center border border-white/20">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138z"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-sm font-bold text-white uppercase tracking-wider">
                            Pengaturan Target Departemen per Golongan/KJ
                        </h2>
                        <p class="text-[11px] text-red-100 font-medium">
                            @if($isDeptSelected)
                                Tentukan jumlah orang target grade S, A, B, C, K untuk tiap jenjang KJ di departemen ini
                            @else
                                Tampilan semua departemen (All) &bull; Pilih Division dan Departemen di atas untuk mengatur kuota spesifik
                            @endif
                        </p>
                    </div>
                </div>

                @if($isDeptSelected)
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="px-3 py-1 rounded-none text-xs font-black border bg-white text-[#92302e] border-white">
                            Departemen: {{ $currentDept }} ({{ $activeDeptStat['headcount'] }} Karyawan)
                        </span>
                        <span class="px-3 py-1 rounded-none text-xs font-black border"
                              :class="getDeptTotalTarget() === {{ $activeDeptStat['headcount'] }} ? 'bg-emerald-600 text-white border-emerald-500' : 'bg-amber-400 text-slate-900 border-amber-300'">
                            <span x-text="'Total Kuota: ' + getDeptTotalTarget() + ' / {{ $activeDeptStat['headcount'] }} Org'"></span>
                        </span>
                        <a href="{{ route(request()->is('superadmin*') ? 'superadmin.targetKuota' : 'admin.targetKuota') }}"
                           class="px-2.5 py-1 rounded-none text-[11px] font-bold bg-white/15 hover:bg-white/25 text-white border border-white/30 transition">
                            ↺ Semua (All)
                        </a>
                    </div>
                @endif
            </div>

            <!-- Single Batch Form to Save All KJ Quotas in This Department -->
            <form action="{{ request()->is('superadmin*') ? route('superadmin.simpanTargetKuotaKj') : route('admin.simpanTargetKuotaKj') }}" 
                  method="POST" 
                  class="p-5 sm:p-6 space-y-5">
                @csrf
                <input type="hidden" name="departemen" value="{{ $currentDept }}">
                <input type="hidden" name="batch" value="1">

                <!-- Matrix Table per KJ (Clean, Sharp & Minimalist) -->
                <div class="overflow-x-auto border border-slate-300">
                    <table class="w-full text-xs text-left border-collapse">
                        <thead>
                            <tr class="bg-[#d1d5db] border-b border-slate-900 text-slate-900 font-black uppercase text-[11px]">
                                <th class="p-3 border-r border-slate-300 min-w-[200px]">Jenjang KJ / Golongan</th>
                                <th class="p-3 text-center border-r border-slate-300 text-emerald-800 bg-emerald-100/60 min-w-[100px]">
                                    S (Sangat Baik)
                                </th>
                                <th class="p-3 text-center border-r border-slate-300 text-blue-800 bg-blue-100/60 min-w-[100px]">
                                    A (Baik)
                                </th>
                                <th class="p-3 text-center border-r border-slate-300 text-amber-800 bg-amber-100/60 min-w-[100px]">
                                    B (Cukup)
                                </th>
                                <th class="p-3 text-center border-r border-slate-300 text-orange-800 bg-orange-100/60 min-w-[100px]">
                                    C (Kurang)
                                </th>
                                <th class="p-3 text-center border-r border-slate-300 text-rose-800 bg-rose-100/60 min-w-[100px]">
                                    K (Kurang Sekali)
                                </th>
                                <th class="p-3 text-center border-r border-slate-300 min-w-[140px]">Total Target KJ</th>
                                <th class="p-3 text-center min-w-[100px]">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            @forelse($activeKjRows as $idx => $row)
                                @php
                                    $kCode = $row['kj'];
                                    $kSanitized = str_replace(' ', '', $kCode);
                                    $headcountKj = (int)($row['total_karyawan'] ?? 0);
                                    $sVal = (int)($row['kuota_s'] ?? 0);
                                    $aVal = (int)($row['kuota_a'] ?? 0);
                                    $bVal = (int)($row['kuota_b'] ?? 0);
                                    $cVal = (int)($row['kuota_c'] ?? 0);
                                    $kVal = (int)($row['kuota_k'] ?? 0);
                                @endphp
                                <tr class="hover:bg-slate-50 transition border-b border-slate-200">
                                    <!-- Jenjang KJ & Golongan -->
                                    <td class="p-3 border-r border-slate-200 font-medium">
                                        @if($isDeptSelected)
                                            <input type="hidden" name="kj_rows[{{ $idx }}][kj]" value="{{ $kCode }}">
                                        @endif
                                        <div class="flex items-center gap-2">
                                            <span class="font-black text-slate-900 text-sm tracking-tight">{{ $kCode }}</span>
                                            <span class="text-[10px] font-bold text-slate-500 bg-slate-100 border border-slate-200 px-1.5 py-0.5">
                                                {{ $row['golongan'] ?? 'Golongan' }}
                                            </span>
                                        </div>
                                        <div class="text-[10.5px] text-slate-500 mt-1 flex items-center gap-1">
                                            <span>👤</span>
                                            @if($isDeptSelected)
                                                <span><strong>{{ $headcountKj }}</strong> Karyawan di {{ $currentDept }}</span>
                                            @else
                                                <span><strong>{{ $headcountKj }}</strong> Karyawan (Semua Dept)</span>
                                            @endif
                                        </div>
                                    </td>

                                    @if($isDeptSelected)
                                        <!-- S -->
                                        <td class="p-2 border-r border-slate-200 bg-emerald-50/20 text-center">
                                            <input type="number" 
                                                   name="kj_rows[{{ $idx }}][quota_s]" 
                                                   x-model.number="kjMatrix['{{ $kSanitized }}'].s"
                                                   min="0"
                                                   class="w-20 text-center text-xs font-bold text-emerald-900 border border-slate-300 rounded-none p-1.5 focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600 bg-white shadow-2xs">
                                        </td>

                                        <!-- A -->
                                        <td class="p-2 border-r border-slate-200 bg-blue-50/20 text-center">
                                            <input type="number" 
                                                   name="kj_rows[{{ $idx }}][quota_a]" 
                                                   x-model.number="kjMatrix['{{ $kSanitized }}'].a"
                                                   min="0"
                                                   class="w-20 text-center text-xs font-bold text-blue-900 border border-slate-300 rounded-none p-1.5 focus:border-blue-600 focus:ring-1 focus:ring-blue-600 bg-white shadow-2xs">
                                        </td>

                                        <!-- B -->
                                        <td class="p-2 border-r border-slate-200 bg-amber-50/20 text-center">
                                            <input type="number" 
                                                   name="kj_rows[{{ $idx }}][quota_b]" 
                                                   x-model.number="kjMatrix['{{ $kSanitized }}'].b"
                                                   min="0"
                                                   class="w-20 text-center text-xs font-bold text-amber-900 border border-slate-300 rounded-none p-1.5 focus:border-amber-600 focus:ring-1 focus:ring-amber-600 bg-white shadow-2xs">
                                        </td>

                                        <!-- C -->
                                        <td class="p-2 border-r border-slate-200 bg-orange-50/20 text-center">
                                            <input type="number" 
                                                   name="kj_rows[{{ $idx }}][quota_c]" 
                                                   x-model.number="kjMatrix['{{ $kSanitized }}'].c"
                                                   min="0"
                                                   class="w-20 text-center text-xs font-bold text-orange-900 border border-slate-300 rounded-none p-1.5 focus:border-orange-600 focus:ring-1 focus:ring-orange-600 bg-white shadow-2xs">
                                        </td>

                                        <!-- K -->
                                        <td class="p-2 border-r border-slate-200 bg-rose-50/20 text-center">
                                            <input type="number" 
                                                   name="kj_rows[{{ $idx }}][quota_k]" 
                                                   x-model.number="kjMatrix['{{ $kSanitized }}'].k"
                                                   min="0"
                                                   class="w-20 text-center text-xs font-bold text-rose-900 border border-slate-300 rounded-none p-1.5 focus:border-rose-600 focus:ring-1 focus:ring-rose-600 bg-white shadow-2xs">
                                        </td>
                                    @else
                                        <!-- Read-only All Summary Cells -->
                                        <td class="p-3 border-r border-slate-200 bg-emerald-50/20 text-center font-black text-emerald-900 text-sm">{{ $sVal }}</td>
                                        <td class="p-3 border-r border-slate-200 bg-blue-50/20 text-center font-black text-blue-900 text-sm">{{ $aVal }}</td>
                                        <td class="p-3 border-r border-slate-200 bg-amber-50/20 text-center font-black text-amber-900 text-sm">{{ $bVal }}</td>
                                        <td class="p-3 border-r border-slate-200 bg-orange-50/20 text-center font-black text-orange-900 text-sm">{{ $cVal }}</td>
                                        <td class="p-3 border-r border-slate-200 bg-rose-50/20 text-center font-black text-rose-900 text-sm">{{ $kVal }}</td>
                                    @endif

                                    <!-- Total Target KJ -->
                                    <td class="p-3 border-r border-slate-200 text-center font-bold">
                                        <div class="flex flex-col items-center justify-center">
                                            <span class="text-sm font-black text-slate-900" x-text="getRowTotal('{{ $kSanitized }}')">
                                                {{ $sVal + $aVal + $bVal + $cVal + $kVal }}
                                            </span>
                                            <span class="text-[10px] text-slate-500">
                                                dari {{ $headcountKj }} Karyawan
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Aksi Individual -->
                                    <td class="p-3 text-center">
                                        @if($isDeptSelected)
                                            <button type="button" 
                                                    @click="saveSingleKj('{{ $kCode }}', '{{ $kSanitized }}')"
                                                    class="px-2.5 py-1 text-[11px] font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 border border-slate-300 rounded-none transition shadow-2xs cursor-pointer">
                                                💾 Simpan
                                            </button>
                                        @else
                                            <span class="text-[10px] font-semibold text-slate-500">Pilih Dept di atas</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="p-6 text-center text-slate-500 italic bg-slate-50">
                                        Belum ada data KJ terdaftar untuk departemen {{ $currentDept }}.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="bg-slate-100 border-t-2 border-slate-900 font-black text-slate-900 text-xs">
                                <td class="p-3 border-r border-slate-300 uppercase">
                                    {{ $isDeptSelected ? 'TOTAL TARGET ' . $currentDept : 'TOTAL TARGET SEMUA DEPARTEMEN' }}
                                </td>
                                <td class="p-3 text-center border-r border-slate-300 text-emerald-800" x-text="getColTotal('s')">
                                    {{ $activeDeptStat['s'] }}
                                </td>
                                <td class="p-3 text-center border-r border-slate-300 text-blue-800" x-text="getColTotal('a')">
                                    {{ $activeDeptStat['a'] }}
                                </td>
                                <td class="p-3 text-center border-r border-slate-300 text-amber-800" x-text="getColTotal('b')">
                                    {{ $activeDeptStat['b'] }}
                                </td>
                                <td class="p-3 text-center border-r border-slate-300 text-orange-800" x-text="getColTotal('c')">
                                    {{ $activeDeptStat['c'] }}
                                </td>
                                <td class="p-3 text-center border-r border-slate-300 text-rose-800" x-text="getColTotal('k')">
                                    {{ $activeDeptStat['k'] }}
                                </td>
                                <td class="p-3 text-center border-r border-slate-300">
                                    <span class="text-sm font-black text-slate-900" x-text="getDeptTotalTarget() + ' / {{ $activeDeptStat['headcount'] }} Org'">
                                        {{ $activeDeptStat['target_total'] }} / {{ $activeDeptStat['headcount'] }} Org
                                    </span>
                                </td>
                                <td class="p-3 text-center">
                                    <span class="text-[10px] font-bold text-slate-500">{{ $isDeptSelected ? 'Auto-calculated' : 'Rekap All' }}</span>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Bottom Action Row: Big Save All Button (Only when a specific department is selected) -->
                @if($isDeptSelected)
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pt-3 border-t border-slate-200">
                        <div class="text-xs text-slate-600">
                            <span class="font-bold text-slate-900">Perhatian:</span> Klik tombol di sebelah kanan untuk menyimpan semua target kuota jenjang KJ departemen <strong>{{ $currentDept }}</strong> sekaligus.
                        </div>
                        <button type="submit" 
                                class="px-5 py-2.5 rounded-none bg-[#92302e] hover:bg-[#7a2423] text-white text-xs font-black uppercase tracking-wider border border-[#7a2423] transition shadow-xs flex items-center justify-center gap-2 cursor-pointer">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                            </svg>
                            <span>💾 Simpan Semua Target Kuota {{ $currentDept }}</span>
                        </button>
                    </div>
                @else
                    <div class="pt-3 border-t border-slate-200 text-xs text-slate-600 flex items-center justify-between gap-2">
                        <span>Menampilkan total gabungan seluruh departemen. Klik card <strong>Division</strong> dan pilih <strong>Departemen</strong> di atas (atau klik <strong>Atur Kuota &rarr;</strong> pada tabel di bawah) untuk mengubah angka kuota per departemen.</span>
                    </div>
                @endif

            </form>
        </div>

        <!-- 5. Tabel Rekapitulasi Rincian Target Kuota Seluruh Departemen -->
        <div class="bg-white rounded-none border border-slate-300 shadow-sm">
            <div class="bg-[#d1d5db] border-b border-slate-900 px-4 py-2.5 flex items-center justify-between">
                <span class="text-xs font-black text-slate-900 uppercase tracking-wider">
                    📋 Rincian Pengaturan Target per KJ &amp; Departemen
                </span>
                <span class="text-[11px] font-bold text-slate-700">
                    Total: {{ count($deptDefs) }} Departemen
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left border-collapse whitespace-nowrap">
                    <thead>
                        <tr class="bg-slate-100 border-b border-slate-300 text-slate-800 font-bold uppercase text-[10.5px] whitespace-nowrap">
                            <th class="p-2.5 border-r border-slate-300 whitespace-nowrap">Kode Dept</th>
                            <th class="p-2.5 border-r border-slate-300 whitespace-nowrap">Divisi</th>
                            <th class="p-2.5 border-r border-slate-300 whitespace-nowrap">Departemen</th>
                            <th class="p-2.5 text-center border-r border-slate-300 whitespace-nowrap">Karyawan</th>
                            <th class="p-2.5 text-center border-r border-slate-300 text-emerald-800 whitespace-nowrap">Target S</th>
                            <th class="p-2.5 text-center border-r border-slate-300 text-blue-800 whitespace-nowrap">Target A</th>
                            <th class="p-2.5 text-center border-r border-slate-300 text-amber-800 whitespace-nowrap">Target B</th>
                            <th class="p-2.5 text-center border-r border-slate-300 text-orange-800 whitespace-nowrap">Target C</th>
                            <th class="p-2.5 text-center border-r border-slate-300 text-rose-800 whitespace-nowrap">Target K</th>
                            <th class="p-2.5 text-center border-r border-slate-300 font-black whitespace-nowrap">Total Target</th>
                            <th class="p-2.5 text-center border-r border-slate-300 whitespace-nowrap">Status</th>
                            <th class="p-2.5 text-center whitespace-nowrap">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach($deptDefs as $d)
                            @php
                                $dCode = $d['kode'];
                                $st = $deptStats[$dCode] ?? ['headcount' => 0, 'target_total' => 0, 's' => 0, 'a' => 0, 'b' => 0, 'c' => 0, 'k' => 0, 'is_complete' => false];
                                $isCurrent = ($currentDept === $dCode);
                                $cleanDeptName = trim(preg_replace('/\s*\([^)]*\)$/', '', $d['departemen']));
                            @endphp
                            <tr class="hover:bg-slate-50 transition whitespace-nowrap {{ $isCurrent ? 'bg-amber-50/50' : '' }}">
                                <td class="p-2.5 border-r border-slate-200 font-bold text-slate-900 whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1.5 whitespace-nowrap">
                                        <span class="whitespace-nowrap">{{ $d['kode'] }}</span>
                                        @if($isCurrent)
                                            <span class="text-[9.5px] px-1.5 py-0.5 bg-[#92302e] text-white font-bold rounded-none whitespace-nowrap tracking-wide">(Aktif)</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="p-2.5 border-r border-slate-200 text-slate-600 font-medium whitespace-nowrap">
                                    {{ $d['divisi'] }}
                                </td>
                                <td class="p-2.5 border-r border-slate-200 text-slate-800 font-medium whitespace-nowrap">
                                    {{ $cleanDeptName }}
                                </td>
                                <td class="p-2.5 border-r border-slate-200 text-center font-bold text-slate-900 whitespace-nowrap">
                                    {{ $st['headcount'] }}
                                </td>
                                <td class="p-2.5 border-r border-slate-200 text-center font-bold text-emerald-800 whitespace-nowrap">
                                    {{ $st['s'] }}
                                </td>
                                <td class="p-2.5 border-r border-slate-200 text-center font-bold text-blue-800 whitespace-nowrap">
                                    {{ $st['a'] }}
                                </td>
                                <td class="p-2.5 border-r border-slate-200 text-center font-bold text-amber-800 whitespace-nowrap">
                                    {{ $st['b'] }}
                                </td>
                                <td class="p-2.5 border-r border-slate-200 text-center font-bold text-orange-800 whitespace-nowrap">
                                    {{ $st['c'] }}
                                </td>
                                <td class="p-2.5 border-r border-slate-200 text-center font-bold text-rose-800 whitespace-nowrap">
                                    {{ $st['k'] }}
                                </td>
                                <td class="p-2.5 border-r border-slate-200 text-center font-black text-slate-900 whitespace-nowrap">
                                    {{ $st['target_total'] }} / {{ $st['headcount'] }}
                                </td>
                                <td class="p-2.5 border-r border-slate-200 text-center whitespace-nowrap">
                                    @if($st['is_complete'])
                                        <span class="inline-block px-2 py-0.5 text-[10px] font-bold text-emerald-800 bg-emerald-100 border border-emerald-300 whitespace-nowrap">
                                            ✓ Sesuai (100%)
                                        </span>
                                    @elseif($st['target_total'] > 0)
                                        <span class="inline-block px-2 py-0.5 text-[10px] font-bold text-amber-800 bg-amber-100 border border-amber-300 whitespace-nowrap">
                                            Parsial
                                        </span>
                                    @else
                                        <span class="inline-block px-2 py-0.5 text-[10px] font-bold text-slate-500 bg-slate-100 border border-slate-300 whitespace-nowrap">
                                            Belum Diatur
                                        </span>
                                    @endif
                                </td>
                                <td class="p-2.5 text-center whitespace-nowrap">
                                    <a href="{{ request()->is('superadmin*') ? route('superadmin.targetKuota', ['dept' => $dCode]) : route('admin.targetKuota', ['dept' => $dCode]) }}#matrixTargetKj"
                                       class="inline-flex items-center gap-1 px-2.5 py-1 text-[10.5px] font-bold text-[#92302e] hover:text-white hover:bg-[#92302e] border border-[#92302e] transition whitespace-nowrap">
                                        Atur Target &rarr;
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Hidden Form for Single Row Save -->
    <form id="singleKjForm" action="{{ request()->is('superadmin*') ? route('superadmin.simpanTargetKuotaKj') : route('admin.simpanTargetKuotaKj') }}" method="POST" style="display: none;">
        @csrf
        <input type="hidden" name="departemen" value="{{ $currentDept }}">
        <input type="hidden" name="kj" id="singleKjName" value="">
        <input type="hidden" name="quota_s" id="singleKjS" value="0">
        <input type="hidden" name="quota_a" id="singleKjA" value="0">
        <input type="hidden" name="quota_b" id="singleKjB" value="0">
        <input type="hidden" name="quota_c" id="singleKjC" value="0">
        <input type="hidden" name="quota_k" id="singleKjK" value="0">
    </form>

</div>

<script>
function targetKuotaApp() {
    return {
        selectedDept: @js($currentDept),
        selectedDivision: @js($activeDivisionName),
        deptPickerOpen: false,
        interactiveScopeStats: @json($interactiveScopeStats ?? []),
        currentScopeKey: @js($isDeptSelected ? ('dept_' . $currentDept) : 'all'),

        get currentScopeStat() {
            return this.interactiveScopeStats[this.currentScopeKey] || this.interactiveScopeStats['all'] || {
                scope_label: 'Seluruh Perusahaan',
                total_karyawan: 0,
                kena_target: 0,
                bebas_target: 0,
                karyawan_subtitle: '-',
                total_division: 0,
                division_karyawan: 0,
                total_department: 0,
                department_karyawan: 0,
                total_section: 0,
                section_karyawan: 0,
                total_foreman: 0,
                foreman_subordinates: 0
            };
        },

        selectDivision(divName) {
            if (this.deptPickerOpen && this.selectedDivision === divName) {
                this.deptPickerOpen = false;
                this.currentScopeKey = (this.selectedDept !== 'all') ? ('dept_' + this.selectedDept) : 'all';
                return;
            }
            this.selectedDivision = divName;
            this.deptPickerOpen = true;
            this.currentScopeKey = 'div_' + divName;
            this.$nextTick(() => {
                const panel = document.getElementById('deptPickerPanel');
                if (panel) {
                    panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            });
        },

        closeDeptPicker() {
            this.deptPickerOpen = false;
            this.currentScopeKey = (this.selectedDept !== 'all') ? ('dept_' + this.selectedDept) : 'all';
        },

        resetToAllScope() {
            this.deptPickerOpen = false;
            this.currentScopeKey = 'all';
        },

        // Active KJ Matrix state for real-time live total updates
        kjMatrix: {
            @foreach($activeKjRows as $row)
                '{{ str_replace(" ", "", $row["kj"]) }}': {
                    s: {{ (int)($row['kuota_s'] ?? 0) }},
                    a: {{ (int)($row['kuota_a'] ?? 0) }},
                    b: {{ (int)($row['kuota_b'] ?? 0) }},
                    c: {{ (int)($row['kuota_c'] ?? 0) }},
                    k: {{ (int)($row['kuota_k'] ?? 0) }},
                    headcount: {{ (int)($row['total_karyawan'] ?? 0) }}
                },
            @endforeach
        },

        getRowTotal(kSanitized) {
            const row = this.kjMatrix[kSanitized];
            if (!row) return 0;
            return (Number(row.s) || 0) + (Number(row.a) || 0) + (Number(row.b) || 0) + (Number(row.c) || 0) + (Number(row.k) || 0);
        },

        getColTotal(grade) {
            let sum = 0;
            for (const key in this.kjMatrix) {
                sum += (Number(this.kjMatrix[key][grade]) || 0);
            }
            return sum;
        },

        getDeptTotalTarget() {
            return this.getColTotal('s') + this.getColTotal('a') + this.getColTotal('b') + this.getColTotal('c') + this.getColTotal('k');
        },

        saveSingleKj(kjName, kSanitized) {
            const row = this.kjMatrix[kSanitized];
            if (!row) return;
            document.getElementById('singleKjName').value = kjName;
            document.getElementById('singleKjS').value = row.s || 0;
            document.getElementById('singleKjA').value = row.a || 0;
            document.getElementById('singleKjB').value = row.b || 0;
            document.getElementById('singleKjC').value = row.c || 0;
            document.getElementById('singleKjK').value = row.k || 0;
            document.getElementById('singleKjForm').submit();
        }
    };
}
</script>
@endsection
