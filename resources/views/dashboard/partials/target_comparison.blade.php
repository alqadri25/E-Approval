@php
    $tPct = $targetPercentages ?? ['S' => 10, 'A' => 30, 'B' => 30, 'C' => 20, 'K' => 10];
    $tCounts = $divTargetCounts ?? ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];
    $sCounts = $divSectionGradeCounts ?? ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];
    $dCounts = $divDeptGradeCounts ?? ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];
    $fCounts = $divFinalGradeCounts ?? ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];
    $totKaryawan = $divTotalKaryawan ?? (isset($divSubordinates) ? count($divSubordinates) : ($stats['total_karyawan'] ?? 14));
    $totSec = $divSectionTotalDinilai ?? array_sum($sCounts);
    $totDept = $divDeptTotalDinilai ?? array_sum($dCounts);
    $totDiv = $divFinalTotalDinilai ?? array_sum($fCounts);
    $instanceId = $chartInstanceId ?? 'default';

    $pctSec = $totKaryawan > 0 ? round(($totSec / $totKaryawan) * 100) : 0;
    $pctDept = $totKaryawan > 0 ? round(($totDept / $totKaryawan) * 100) : 0;
    $pctDiv = $totKaryawan > 0 ? round(($totDiv / $totKaryawan) * 100) : 0;
@endphp

<div class="space-y-6" x-data="targetComparisonWidget_{{ $instanceId }}()">

    <!-- SECTION 1: 4 DIAGRAM LINGKARAN (Doughnut Charts: Target vs Section Head vs Dept Head vs Division Head) -->
    <div class="space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <h3 class="text-sm font-bold text-slate-900 tracking-tight flex items-center gap-2">
                    <span>⭕ Diagram Lingkaran Distribusi Grade (S, A, B, C, K)</span>
                </h3>
                <p class="text-[11px] text-slate-500 font-medium">
                    Diagram lingkaran perbandingan sebaran nilai: Target Kuota, Nilai Section Head, Nilai Department Head, dan Nilai Akhir Division Head
                </p>
            </div>
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 text-xs font-bold font-mono">
                    Total: {{ $totKaryawan }} Karyawan
                </span>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            
            <!-- Donut 1: Target Kuota -->
            <div class="bg-white rounded-2xl p-4.5 border border-slate-200 shadow-sm flex flex-col justify-between">
                <div class="flex items-center justify-between pb-2.5 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#64748b]"></span>
                        <h4 class="text-xs font-bold text-slate-800">1. Target Kuota</h4>
                    </div>
                    <span class="text-[10px] font-mono font-bold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-full">
                        {{ $totKaryawan }} Org (100%)
                    </span>
                </div>

                <div class="flex items-center justify-around gap-2 my-auto pt-3">
                    <div class="relative w-32 h-32 flex items-center justify-center flex-shrink-0">
                        <canvas id="donutTarget_{{ $instanceId }}"></canvas>
                        <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                            <span class="text-lg font-black text-slate-900 leading-none">{{ $totKaryawan }}</span>
                            <span class="text-[9px] text-slate-500 font-semibold mt-0.5">Target</span>
                        </div>
                    </div>

                    <div class="space-y-1 text-xs text-slate-700 min-w-[105px]">
                        <div class="flex items-center justify-between gap-1.5">
                            <span class="flex items-center gap-1 text-[11px] text-slate-600">
                                <span class="w-2 h-2 rounded-full bg-[#10b981]"></span> S
                            </span>
                            <span class="font-bold text-slate-900 text-xs">{{ $tCounts['S'] }} <span class="text-[10px] font-normal text-slate-400">({{ $tPct['S'] ?? 10 }}%)</span></span>
                        </div>
                        <div class="flex items-center justify-between gap-1.5">
                            <span class="flex items-center gap-1 text-[11px] text-slate-600">
                                <span class="w-2 h-2 rounded-full bg-[#2563eb]"></span> A
                            </span>
                            <span class="font-bold text-slate-900 text-xs">{{ $tCounts['A'] }} <span class="text-[10px] font-normal text-slate-400">({{ $tPct['A'] ?? 30 }}%)</span></span>
                        </div>
                        <div class="flex items-center justify-between gap-1.5">
                            <span class="flex items-center gap-1 text-[11px] text-slate-600">
                                <span class="w-2 h-2 rounded-full bg-[#f59e0b]"></span> B
                            </span>
                            <span class="font-bold text-slate-900 text-xs">{{ $tCounts['B'] }} <span class="text-[10px] font-normal text-slate-400">({{ $tPct['B'] ?? 30 }}%)</span></span>
                        </div>
                        <div class="flex items-center justify-between gap-1.5">
                            <span class="flex items-center gap-1 text-[11px] text-slate-600">
                                <span class="w-2 h-2 rounded-full bg-[#f97316]"></span> C
                            </span>
                            <span class="font-bold text-slate-900 text-xs">{{ $tCounts['C'] }} <span class="text-[10px] font-normal text-slate-400">({{ $tPct['C'] ?? 20 }}%)</span></span>
                        </div>
                        <div class="flex items-center justify-between gap-1.5">
                            <span class="flex items-center gap-1 text-[11px] text-slate-600">
                                <span class="w-2 h-2 rounded-full bg-[#ef4444]"></span> K
                            </span>
                            <span class="font-bold text-slate-900 text-xs">{{ $tCounts['K'] }} <span class="text-[10px] font-normal text-slate-400">({{ $tPct['K'] ?? 10 }}%)</span></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Donut 2: Hasil Section Head -->
            <div class="bg-white rounded-2xl p-4.5 border border-slate-200 shadow-sm flex flex-col justify-between">
                <div class="flex items-center justify-between pb-2.5 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#3b82f6]"></span>
                        <h4 class="text-xs font-bold text-slate-800">2. Dari Section Head</h4>
                    </div>
                    <span class="text-[10px] font-mono font-bold text-blue-700 bg-blue-50 px-2 py-0.5 rounded-full border border-blue-100">
                        {{ $totSec }} / {{ $totKaryawan }} ({{ $pctSec }}%)
                    </span>
                </div>

                <div class="flex items-center justify-around gap-2 my-auto pt-3">
                    <div class="relative w-32 h-32 flex items-center justify-center flex-shrink-0">
                        <canvas id="donutSection_{{ $instanceId }}"></canvas>
                        <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                            <span class="text-lg font-black text-slate-900 leading-none">{{ $totSec }}</span>
                            <span class="text-[9px] text-slate-500 font-semibold mt-0.5">Dinilai</span>
                        </div>
                    </div>

                    <div class="space-y-1 text-xs text-slate-700 min-w-[105px]">
                        <div class="flex items-center justify-between gap-1.5">
                            <span class="flex items-center gap-1 text-[11px] text-slate-600">
                                <span class="w-2 h-2 rounded-full bg-[#10b981]"></span> S
                            </span>
                            <span class="font-bold text-slate-900 text-xs">{{ $sCounts['S'] }} <span class="text-[10px] font-normal text-slate-400">({{ $totKaryawan > 0 ? round(($sCounts['S']/$totKaryawan)*100) : 0 }}%)</span></span>
                        </div>
                        <div class="flex items-center justify-between gap-1.5">
                            <span class="flex items-center gap-1 text-[11px] text-slate-600">
                                <span class="w-2 h-2 rounded-full bg-[#2563eb]"></span> A
                            </span>
                            <span class="font-bold text-slate-900 text-xs">{{ $sCounts['A'] }} <span class="text-[10px] font-normal text-slate-400">({{ $totKaryawan > 0 ? round(($sCounts['A']/$totKaryawan)*100) : 0 }}%)</span></span>
                        </div>
                        <div class="flex items-center justify-between gap-1.5">
                            <span class="flex items-center gap-1 text-[11px] text-slate-600">
                                <span class="w-2 h-2 rounded-full bg-[#f59e0b]"></span> B
                            </span>
                            <span class="font-bold text-slate-900 text-xs">{{ $sCounts['B'] }} <span class="text-[10px] font-normal text-slate-400">({{ $totKaryawan > 0 ? round(($sCounts['B']/$totKaryawan)*100) : 0 }}%)</span></span>
                        </div>
                        <div class="flex items-center justify-between gap-1.5">
                            <span class="flex items-center gap-1 text-[11px] text-slate-600">
                                <span class="w-2 h-2 rounded-full bg-[#f97316]"></span> C
                            </span>
                            <span class="font-bold text-slate-900 text-xs">{{ $sCounts['C'] }} <span class="text-[10px] font-normal text-slate-400">({{ $totKaryawan > 0 ? round(($sCounts['C']/$totKaryawan)*100) : 0 }}%)</span></span>
                        </div>
                        <div class="flex items-center justify-between gap-1.5">
                            <span class="flex items-center gap-1 text-[11px] text-slate-600">
                                <span class="w-2 h-2 rounded-full bg-[#ef4444]"></span> K
                            </span>
                            <span class="font-bold text-slate-900 text-xs">{{ $sCounts['K'] }} <span class="text-[10px] font-normal text-slate-400">({{ $totKaryawan > 0 ? round(($sCounts['K']/$totKaryawan)*100) : 0 }}%)</span></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Donut 3: Hasil Department Head -->
            <div class="bg-white rounded-2xl p-4.5 border border-slate-200 shadow-sm flex flex-col justify-between">
                <div class="flex items-center justify-between pb-2.5 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#f59e0b]"></span>
                        <h4 class="text-xs font-bold text-slate-800">3. Dari Dept Head</h4>
                    </div>
                    <span class="text-[10px] font-mono font-bold text-amber-700 bg-amber-50 px-2 py-0.5 rounded-full border border-amber-100">
                        {{ $totDept }} / {{ $totKaryawan }} ({{ $pctDept }}%)
                    </span>
                </div>

                <div class="flex items-center justify-around gap-2 my-auto pt-3">
                    <div class="relative w-32 h-32 flex items-center justify-center flex-shrink-0">
                        <canvas id="donutDept_{{ $instanceId }}"></canvas>
                        <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                            <span class="text-lg font-black text-slate-900 leading-none">{{ $totDept }}</span>
                            <span class="text-[9px] text-slate-500 font-semibold mt-0.5">Dinilai</span>
                        </div>
                    </div>

                    <div class="space-y-1 text-xs text-slate-700 min-w-[105px]">
                        <div class="flex items-center justify-between gap-1.5">
                            <span class="flex items-center gap-1 text-[11px] text-slate-600">
                                <span class="w-2 h-2 rounded-full bg-[#10b981]"></span> S
                            </span>
                            <span class="font-bold text-slate-900 text-xs">{{ $dCounts['S'] }} <span class="text-[10px] font-normal text-slate-400">({{ $totKaryawan > 0 ? round(($dCounts['S']/$totKaryawan)*100) : 0 }}%)</span></span>
                        </div>
                        <div class="flex items-center justify-between gap-1.5">
                            <span class="flex items-center gap-1 text-[11px] text-slate-600">
                                <span class="w-2 h-2 rounded-full bg-[#2563eb]"></span> A
                            </span>
                            <span class="font-bold text-slate-900 text-xs">{{ $dCounts['A'] }} <span class="text-[10px] font-normal text-slate-400">({{ $totKaryawan > 0 ? round(($dCounts['A']/$totKaryawan)*100) : 0 }}%)</span></span>
                        </div>
                        <div class="flex items-center justify-between gap-1.5">
                            <span class="flex items-center gap-1 text-[11px] text-slate-600">
                                <span class="w-2 h-2 rounded-full bg-[#f59e0b]"></span> B
                            </span>
                            <span class="font-bold text-slate-900 text-xs">{{ $dCounts['B'] }} <span class="text-[10px] font-normal text-slate-400">({{ $totKaryawan > 0 ? round(($dCounts['B']/$totKaryawan)*100) : 0 }}%)</span></span>
                        </div>
                        <div class="flex items-center justify-between gap-1.5">
                            <span class="flex items-center gap-1 text-[11px] text-slate-600">
                                <span class="w-2 h-2 rounded-full bg-[#f97316]"></span> C
                            </span>
                            <span class="font-bold text-slate-900 text-xs">{{ $dCounts['C'] }} <span class="text-[10px] font-normal text-slate-400">({{ $totKaryawan > 0 ? round(($dCounts['C']/$totKaryawan)*100) : 0 }}%)</span></span>
                        </div>
                        <div class="flex items-center justify-between gap-1.5">
                            <span class="flex items-center gap-1 text-[11px] text-slate-600">
                                <span class="w-2 h-2 rounded-full bg-[#ef4444]"></span> K
                            </span>
                            <span class="font-bold text-slate-900 text-xs">{{ $dCounts['K'] }} <span class="text-[10px] font-normal text-slate-400">({{ $totKaryawan > 0 ? round(($dCounts['K']/$totKaryawan)*100) : 0 }}%)</span></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Donut 4: Hasil Division Head (Final) -->
            <div class="bg-white rounded-2xl p-4.5 border border-slate-200 shadow-sm flex flex-col justify-between">
                <div class="flex items-center justify-between pb-2.5 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#92302e]"></span>
                        <h4 class="text-xs font-bold text-slate-800">4. Dari Div Head (Final)</h4>
                    </div>
                    <span class="text-[10px] font-mono font-bold text-[#92302e] bg-rose-50 px-2 py-0.5 rounded-full border border-rose-100">
                        {{ $totDiv }} / {{ $totKaryawan }} ({{ $pctDiv }}%)
                    </span>
                </div>

                <div class="flex items-center justify-around gap-2 my-auto pt-3">
                    <div class="relative w-32 h-32 flex items-center justify-center flex-shrink-0">
                        <canvas id="donutDiv_{{ $instanceId }}"></canvas>
                        <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                            <span class="text-lg font-black text-slate-900 leading-none">{{ $totDiv }}</span>
                            <span class="text-[9px] text-slate-500 font-semibold mt-0.5">Finalisasi</span>
                        </div>
                    </div>

                    <div class="space-y-1 text-xs text-slate-700 min-w-[105px]">
                        <div class="flex items-center justify-between gap-1.5">
                            <span class="flex items-center gap-1 text-[11px] text-slate-600">
                                <span class="w-2 h-2 rounded-full bg-[#10b981]"></span> S
                            </span>
                            <span class="font-bold text-slate-900 text-xs">{{ $fCounts['S'] }} <span class="text-[10px] font-normal text-slate-400">({{ $totKaryawan > 0 ? round(($fCounts['S']/$totKaryawan)*100) : 0 }}%)</span></span>
                        </div>
                        <div class="flex items-center justify-between gap-1.5">
                            <span class="flex items-center gap-1 text-[11px] text-slate-600">
                                <span class="w-2 h-2 rounded-full bg-[#2563eb]"></span> A
                            </span>
                            <span class="font-bold text-slate-900 text-xs">{{ $fCounts['A'] }} <span class="text-[10px] font-normal text-slate-400">({{ $totKaryawan > 0 ? round(($fCounts['A']/$totKaryawan)*100) : 0 }}%)</span></span>
                        </div>
                        <div class="flex items-center justify-between gap-1.5">
                            <span class="flex items-center gap-1 text-[11px] text-slate-600">
                                <span class="w-2 h-2 rounded-full bg-[#f59e0b]"></span> B
                            </span>
                            <span class="font-bold text-slate-900 text-xs">{{ $fCounts['B'] }} <span class="text-[10px] font-normal text-slate-400">({{ $totKaryawan > 0 ? round(($fCounts['B']/$totKaryawan)*100) : 0 }}%)</span></span>
                        </div>
                        <div class="flex items-center justify-between gap-1.5">
                            <span class="flex items-center gap-1 text-[11px] text-slate-600">
                                <span class="w-2 h-2 rounded-full bg-[#f97316]"></span> C
                            </span>
                            <span class="font-bold text-slate-900 text-xs">{{ $fCounts['C'] }} <span class="text-[10px] font-normal text-slate-400">({{ $totKaryawan > 0 ? round(($fCounts['C']/$totKaryawan)*100) : 0 }}%)</span></span>
                        </div>
                        <div class="flex items-center justify-between gap-1.5">
                            <span class="flex items-center gap-1 text-[11px] text-slate-600">
                                <span class="w-2 h-2 rounded-full bg-[#ef4444]"></span> K
                            </span>
                            <span class="font-bold text-slate-900 text-xs">{{ $fCounts['K'] }} <span class="text-[10px] font-normal text-slate-400">({{ $totKaryawan > 0 ? round(($fCounts['K']/$totKaryawan)*100) : 0 }}%)</span></span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- SECTION 2: DIAGRAM BATANG PERBANDINGAN S, A, B, C, K (Grouped Bar Chart) & TABEL MATRIKS -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-6">
        
        <!-- Header Diagram -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100">
            <div>
                <h3 class="text-sm font-bold text-slate-900 tracking-tight flex items-center gap-2">
                    <span>📊 Diagram Batang Perbandingan Penilaian (S, A, B, C, K)</span>
                </h3>
                <p class="text-[11px] text-slate-500 font-medium mt-0.5">
                    Visualisasi perbandingan jumlah karyawan per grade antara <strong>Target Kuota</strong>, <strong>Section Head</strong>, <strong>Department Head</strong>, dan <strong>Division Head</strong>
                </p>
            </div>

            <!-- Custom Interactive Legend -->
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="inline-flex items-center gap-1.5 text-slate-700 px-2 py-1 rounded-lg bg-slate-50 border border-slate-200 font-medium">
                    <span class="w-2.5 h-2.5 rounded-full bg-[#64748b]"></span>
                    <span>Target Kuota</span>
                </span>
                <span class="inline-flex items-center gap-1.5 text-slate-700 px-2 py-1 rounded-lg bg-blue-50/60 border border-blue-200 font-medium">
                    <span class="w-2.5 h-2.5 rounded-full bg-[#3b82f6]"></span>
                    <span>Section Head</span>
                </span>
                <span class="inline-flex items-center gap-1.5 text-slate-700 px-2 py-1 rounded-lg bg-amber-50/60 border border-amber-200 font-medium">
                    <span class="w-2.5 h-2.5 rounded-full bg-[#f59e0b]"></span>
                    <span>Dept Head</span>
                </span>
                <span class="inline-flex items-center gap-1.5 text-slate-700 px-2 py-1 rounded-lg bg-rose-50/60 border border-rose-200 font-medium">
                    <span class="w-2.5 h-2.5 rounded-full bg-[#92302e]"></span>
                    <span>Division Head</span>
                </span>
            </div>
        </div>

        <!-- Bar Chart Canvas -->
        <div class="w-full h-72 sm:h-80 relative">
            <canvas id="chartPerbandinganGrade_{{ $instanceId }}"></canvas>
        </div>

        <!-- MATRIKS TABEL PERBANDINGAN REAL-TIME -->
        <div class="border border-slate-200 rounded-xl overflow-hidden shadow-2xs">
            <div class="px-4 py-2.5 bg-slate-50/80 border-b border-slate-200 flex items-center justify-between">
                <span class="text-xs font-bold text-slate-800 uppercase tracking-wider">Matriks Perbandingan Distribusi Grade &amp; Kepatuhan Kuota</span>
                <span class="text-[10px] text-slate-500 font-medium">Real-time sinkronisasi dari database dokumen &amp; nilai</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-slate-100 text-slate-700 uppercase text-[10px] font-bold border-b border-slate-200">
                        <tr>
                            <th class="py-2.5 px-3">Grade</th>
                            <th class="py-2.5 px-3">Deskripsi Kinerja</th>
                            <th class="py-2.5 px-3 text-center bg-slate-200/50">🎯 Target (Kuota)</th>
                            <th class="py-2.5 px-3 text-center bg-blue-50 text-blue-900">👤 Section Head</th>
                            <th class="py-2.5 px-3 text-center bg-amber-50 text-amber-900">👥 Dept Head</th>
                            <th class="py-2.5 px-3 text-center bg-rose-50 text-rose-950">👑 Division Head</th>
                            <th class="py-2.5 px-3 text-center">Status Kuota (Div vs Target)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                        @foreach(['S' => 'Sangat Baik', 'A' => 'Baik', 'B' => 'Cukup', 'C' => 'Kurang', 'K' => 'Kurang Sekali'] as $grd => $desc)
                        @php
                            $t = $tCounts[$grd] ?? 0;
                            $s = $sCounts[$grd] ?? 0;
                            $d = $dCounts[$grd] ?? 0;
                            $f = $fCounts[$grd] ?? 0;
                            $diff = $f - $t;
                        @endphp
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-2 px-3 font-black text-slate-900">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-lg font-bold text-xs 
                                      {{ $grd === 'S' ? 'bg-emerald-100 text-emerald-800' : ($grd === 'A' ? 'bg-blue-100 text-blue-800' : ($grd === 'B' ? 'bg-amber-100 text-amber-800' : ($grd === 'C' ? 'bg-orange-100 text-orange-800' : 'bg-rose-100 text-rose-800'))) }}">
                                    {{ $grd }}
                                </span>
                            </td>
                            <td class="py-2 px-3 text-slate-600">{{ $desc }}</td>
                            <td class="py-2 px-3 text-center font-bold bg-slate-50/50">
                                <span>{{ $t }}</span>
                                <span class="text-[10px] text-slate-400 font-normal">({{ $tPct[$grd] ?? 0 }}%)</span>
                            </td>
                            <td class="py-2 px-3 text-center font-bold text-blue-700 bg-blue-50/20">
                                <span>{{ $s }}</span>
                                <span class="text-[10px] text-slate-400 font-normal">({{ $totKaryawan > 0 ? round(($s/$totKaryawan)*100) : 0 }}%)</span>
                            </td>
                            <td class="py-2 px-3 text-center font-bold text-amber-700 bg-amber-50/20">
                                <span>{{ $d }}</span>
                                <span class="text-[10px] text-slate-400 font-normal">({{ $totKaryawan > 0 ? round(($d/$totKaryawan)*100) : 0 }}%)</span>
                            </td>
                            <td class="py-2 px-3 text-center font-bold text-[#92302e] bg-rose-50/20">
                                <span>{{ $f }}</span>
                                <span class="text-[10px] text-slate-400 font-normal">({{ $totKaryawan > 0 ? round(($f/$totKaryawan)*100) : 0 }}%)</span>
                            </td>
                            <td class="py-2 px-3 text-center">
                                @if($diff > 0)
                                    <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-700 border border-rose-200">
                                        +{{ $diff }} Lebih Kuota
                                    </span>
                                @elseif($diff < 0)
                                    <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700 border border-amber-200">
                                        {{ $diff }} Di Bawah Kuota
                                    </span>
                                @else
                                    <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700 border border-emerald-200">
                                        ✓ Sesuai Target
                                    </span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-slate-100/80 font-bold text-slate-900 text-xs border-t-2 border-slate-200">
                        <tr>
                            <td colspan="2" class="py-2.5 px-3 uppercase tracking-wider text-[11px]">Total Seluruh Karyawan</td>
                            <td class="py-2.5 px-3 text-center font-mono">{{ $totKaryawan }} (100%)</td>
                            <td class="py-2.5 px-3 text-center font-mono text-blue-800">{{ $totSec }} ({{ $pctSec }}%)</td>
                            <td class="py-2.5 px-3 text-center font-mono text-amber-800">{{ $totDept }} ({{ $pctDept }}%)</td>
                            <td class="py-2.5 px-3 text-center font-mono text-[#92302e]">{{ $totDiv }} ({{ $pctDiv }}%)</td>
                            <td class="py-2.5 px-3 text-center text-slate-500 font-normal text-[11px]">
                                {{ $totDiv === $totKaryawan ? '✓ 100% Selesai Difinalisasi' : ($totKaryawan - $totDiv) . ' Belum Finalisasi' }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

    </div>

</div>

<script>
function targetComparisonWidget_{{ $instanceId }}() {
    return {
        init() {
            this.$nextTick(() => {
                this.renderAllCharts();
            });
        },

        renderAllCharts() {
            const gradeColors = ['#10b981', '#2563eb', '#f59e0b', '#f97316', '#ef4444'];
            const gradeLabels = ['S (Sangat Baik)', 'A (Baik)', 'B (Cukup)', 'C (Kurang)', 'K (Kurang Sekali)'];

            const createDonut = (canvasId, counts, totalLabel) => {
                const canvas = document.getElementById(canvasId);
                if (!canvas) return;
                const ctx = canvas.getContext('2d');
                if (!ctx) return;

                const values = [counts.S || 0, counts.A || 0, counts.B || 0, counts.C || 0, counts.K || 0];
                const hasData = values.some(v => v > 0);

                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: gradeLabels,
                        datasets: [{
                            data: hasData ? values : [1],
                            backgroundColor: hasData ? gradeColors : ['#e2e8f0'],
                            borderWidth: 2,
                            borderColor: '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(c) {
                                        if (!hasData) return ' Belum ada data';
                                        return ` ${c.label}: ${c.raw} Orang`;
                                    }
                                }
                            }
                        },
                        cutout: '72%'
                    }
                });
            };

            // 1. Donut Target
            createDonut('donutTarget_{{ $instanceId }}', @json($tCounts), 'Target');

            // 2. Donut Section Head
            createDonut('donutSection_{{ $instanceId }}', @json($sCounts), 'Section Head');

            // 3. Donut Dept Head
            createDonut('donutDept_{{ $instanceId }}', @json($dCounts), 'Dept Head');

            // 4. Donut Division Head
            createDonut('donutDiv_{{ $instanceId }}', @json($fCounts), 'Division Head');

            // 5. Grouped Bar Chart
            const barCanvas = document.getElementById('chartPerbandinganGrade_{{ $instanceId }}');
            if (barCanvas) {
                const barCtx = barCanvas.getContext('2d');
                if (barCtx) {
                    if (window._myGradeChart_{{ $instanceId }}) {
                        window._myGradeChart_{{ $instanceId }}.destroy();
                    }

                    const labels = ['Grade S', 'Grade A', 'Grade B', 'Grade C', 'Grade K'];
                    const targetData = [{{ $tCounts['S'] }}, {{ $tCounts['A'] }}, {{ $tCounts['B'] }}, {{ $tCounts['C'] }}, {{ $tCounts['K'] }}];
                    const sectionData = [{{ $sCounts['S'] }}, {{ $sCounts['A'] }}, {{ $sCounts['B'] }}, {{ $sCounts['C'] }}, {{ $sCounts['K'] }}];
                    const deptData = [{{ $dCounts['S'] }}, {{ $dCounts['A'] }}, {{ $dCounts['B'] }}, {{ $dCounts['C'] }}, {{ $dCounts['K'] }}];
                    const divData = [{{ $fCounts['S'] }}, {{ $fCounts['A'] }}, {{ $fCounts['B'] }}, {{ $fCounts['C'] }}, {{ $fCounts['K'] }}];

                    window._myGradeChart_{{ $instanceId }} = new Chart(barCtx, {
                        type: 'bar',
                        data: {
                            labels: labels,
                            datasets: [
                                {
                                    label: '🎯 Target Kuota',
                                    data: targetData,
                                    backgroundColor: '#64748b',
                                    borderColor: '#475569',
                                    borderWidth: 1,
                                    borderRadius: 6,
                                    barPercentage: 0.8,
                                    categoryPercentage: 0.8
                                },
                                {
                                    label: '👤 Section Head',
                                    data: sectionData,
                                    backgroundColor: '#3b82f6',
                                    borderColor: '#2563eb',
                                    borderWidth: 1,
                                    borderRadius: 6,
                                    barPercentage: 0.8,
                                    categoryPercentage: 0.8
                                },
                                {
                                    label: '👥 Dept Head',
                                    data: deptData,
                                    backgroundColor: '#f59e0b',
                                    borderColor: '#d97706',
                                    borderWidth: 1,
                                    borderRadius: 6,
                                    barPercentage: 0.8,
                                    categoryPercentage: 0.8
                                },
                                {
                                    label: '👑 Division Head',
                                    data: divData,
                                    backgroundColor: '#92302e',
                                    borderColor: '#7e2524',
                                    borderWidth: 1,
                                    borderRadius: 6,
                                    barPercentage: 0.8,
                                    categoryPercentage: 0.8
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    backgroundColor: 'rgba(15, 23, 42, 0.95)',
                                    titleFont: { size: 12, weight: 'bold' },
                                    bodyFont: { size: 11 },
                                    padding: 10,
                                    cornerRadius: 8,
                                    callbacks: {
                                        label: function(context) {
                                            const val = context.parsed.y;
                                            const total = {{ $totKaryawan }};
                                            const pct = total > 0 ? Math.round((val / total) * 100) : 0;
                                            return `${context.dataset.label}: ${val} Orang (${pct}%)`;
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    grid: { display: false },
                                    ticks: {
                                        font: { weight: 'bold', size: 11 },
                                        color: '#1e293b'
                                    }
                                },
                                y: {
                                    beginAtZero: true,
                                    grid: { color: '#f1f5f9' },
                                    ticks: {
                                        precision: 0,
                                        font: { size: 10 },
                                        color: '#64748b'
                                    }
                                }
                            }
                        }
                    });
                }
            }
        }
    };
}
</script>
