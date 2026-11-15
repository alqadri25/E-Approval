@php
    $roleLabel = $evaluatorRoleLabel ?? 'Section Head';
    $showDeptComparison = !empty($includeDeptComparison);
    $targetGrandTotal = array_sum(array_map(fn($g) => (int)($targetCounts[$g] ?? 0), ['S', 'A', 'B', 'C', 'K']));
    $actualGrandTotal = array_sum(array_map(fn($g) => (int)($actualCounts[$g] ?? 0), ['S', 'A', 'B', 'C', 'K']));
    $deptActualGrandTotal = array_sum(array_map(fn($g) => (int)($deptActualCounts[$g] ?? 0), ['S', 'A', 'B', 'C', 'K']));
    $displayDinilai = $totalDinilai ?? $actualGrandTotal;
    $displayDeptDinilai = $deptTotalDinilai ?? $deptActualGrandTotal;
    $displayBelum = $totalBelum ?? max(0, $targetGrandTotal - $displayDinilai);
    $pctDisplay = $pctDinilai ?? ($targetGrandTotal > 0 ? (int)round(($displayDinilai / $targetGrandTotal) * 100) : 0);
    $subtitle = $subtitleText ?? ($showDeptComparison
        ? "Ringkasan target kuota, nilai Department Head, & actual {$roleLabel} per KJ dalam satu tabel."
        : "Ringkasan target kuota & actual {$roleLabel} per KJ dalam satu tabel.");
@endphp
<div class="bg-white rounded-none border border-slate-300 p-4 sm:p-5 shadow-sm space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-slate-200 gap-2">
        <div>
            <h3 class="text-xs sm:text-sm font-black text-slate-900 tracking-wider flex items-center gap-2">
                <span class="w-2.5 h-2.5 bg-[#92302e]"></span>
                <span>Tabel Perbandingan: Target Penilaian vs {{ $showDeptComparison ? 'Nilai Dept Head & ' : '' }}Actual {{ $roleLabel }}</span>
            </h3>
            <p class="text-[11px] text-slate-500 font-medium mt-0.5">
                {{ $subtitle }}
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2 text-xs font-bold">
            <span class="px-2.5 py-1 bg-slate-100 text-slate-800 border border-slate-300">
                Target: {{ $targetGrandTotal }}
            </span>
            @if($showDeptComparison)
            <span class="px-2.5 py-1 bg-emerald-50 text-emerald-800 border border-emerald-300">
                Dept Head: {{ $displayDeptDinilai }}
            </span>
            @endif
            <span class="px-2.5 py-1 bg-[#fbe8e8] text-[#92302e] border border-[#ce5d5b]/40">
                Actual: {{ $displayDinilai }} ({{ $pctDisplay }}%)
            </span>
            <span class="px-2.5 py-1 bg-amber-50 text-amber-800 border border-amber-300">
                Belum Dinilai: {{ $displayBelum }}
            </span>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full border-collapse border border-slate-300 text-center text-xs select-none">
            <thead>
                <tr>
                    <th rowspan="2" class="w-24 bg-[#fff2cc] text-slate-900 font-extrabold px-3 py-2.5 border border-slate-300 tracking-wider whitespace-nowrap">
                        KJ \ PK
                    </th>
                    <th colspan="6" class="bg-[#d9ead3] text-slate-900 font-extrabold px-3 py-2 border border-slate-300 uppercase tracking-wider text-[11px]">
                        Target Penilaian (Kuota)
                    </th>
                    @if($showDeptComparison)
                    <th colspan="6" class="bg-[#d1fae5] text-emerald-950 font-extrabold px-3 py-2 border border-slate-300 uppercase tracking-wider text-[11px]">
                        Nilai Dept Head
                    </th>
                    @endif
                    <th colspan="6" class="bg-[#fbe8e8] text-[#92302e] font-extrabold px-3 py-2 border border-slate-300 uppercase tracking-wider text-[11px]">
                        Actual {{ $roleLabel }}
                    </th>
                    <th rowspan="2" class="w-28 bg-slate-100 text-slate-800 font-extrabold px-3 py-2.5 border border-slate-300 uppercase tracking-wider text-[11px] whitespace-nowrap">
                        Belum Dinilai
                    </th>
                </tr>
                <tr class="text-[11px]">
                    <th class="w-11 bg-[#e8f3e3] text-slate-800 font-bold px-2 py-2 border border-slate-300">S</th>
                    <th class="w-11 bg-[#e8f3e3] text-slate-800 font-bold px-2 py-2 border border-slate-300">A</th>
                    <th class="w-11 bg-[#e8f3e3] text-slate-800 font-bold px-2 py-2 border border-slate-300">B</th>
                    <th class="w-11 bg-[#e8f3e3] text-slate-800 font-bold px-2 py-2 border border-slate-300">C</th>
                    <th class="w-11 bg-[#e8f3e3] text-slate-800 font-bold px-2 py-2 border border-slate-300">K</th>
                    <th class="w-16 bg-[#cfe2f3] text-slate-900 font-extrabold px-2.5 py-2 border border-slate-300">Total</th>

                    @if($showDeptComparison)
                    <th class="w-11 bg-[#ecfdf5] text-emerald-900 font-bold px-2 py-2 border border-slate-300">S</th>
                    <th class="w-11 bg-[#ecfdf5] text-emerald-900 font-bold px-2 py-2 border border-slate-300">A</th>
                    <th class="w-11 bg-[#ecfdf5] text-emerald-900 font-bold px-2 py-2 border border-slate-300">B</th>
                    <th class="w-11 bg-[#ecfdf5] text-emerald-900 font-bold px-2 py-2 border border-slate-300">C</th>
                    <th class="w-11 bg-[#ecfdf5] text-emerald-900 font-bold px-2 py-2 border border-slate-300">K</th>
                    <th class="w-16 bg-[#a7f3d0] text-emerald-950 font-extrabold px-2.5 py-2 border border-slate-300">Total</th>
                    @endif

                    <th class="w-11 bg-[#fdf4f4] text-[#92302e] font-bold px-2 py-2 border border-slate-300">S</th>
                    <th class="w-11 bg-[#fdf4f4] text-[#92302e] font-bold px-2 py-2 border border-slate-300">A</th>
                    <th class="w-11 bg-[#fdf4f4] text-[#92302e] font-bold px-2 py-2 border border-slate-300">B</th>
                    <th class="w-11 bg-[#fdf4f4] text-[#92302e] font-bold px-2 py-2 border border-slate-300">C</th>
                    <th class="w-11 bg-[#fdf4f4] text-[#92302e] font-bold px-2 py-2 border border-slate-300">K</th>
                    <th class="w-16 bg-[#f7d5d5] text-[#7e2b29] font-extrabold px-2.5 py-2 border border-slate-300">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse($matrixKjList ?? [] as $kjName)
                @php
                    $tRow = $targetKjMatrix[$kjName] ?? ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0, 'total' => 0];
                    $dRow = $deptActualKjMatrix[$kjName] ?? ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0, 'total' => 0];
                    $aRow = $actualKjMatrix[$kjName] ?? ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0, 'total' => 0];
                    $belumKj = max(0, ($subCountByKj[$kjName] ?? $tRow['total']) - ($aRow['total'] ?? 0));
                @endphp
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="bg-[#ffe599] text-slate-900 font-extrabold px-3 py-2.5 border border-slate-300 whitespace-nowrap">
                        {{ $kjName }}
                    </td>
                    @foreach(['S', 'A', 'B', 'C', 'K'] as $g)
                    <td class="bg-white px-2 py-2.5 border border-slate-300 {{ ($tRow[$g] ?? 0) > 0 ? 'text-slate-900 font-bold' : 'text-slate-400 font-medium' }}">
                        {{ $tRow[$g] ?? 0 }}
                    </td>
                    @endforeach
                    <td class="bg-[#cfe2f3]/60 text-slate-900 font-extrabold px-2.5 py-2.5 border border-slate-300 whitespace-nowrap">
                        {{ $tRow['total'] ?? 0 }}
                    </td>

                    @if($showDeptComparison)
                    @foreach(['S', 'A', 'B', 'C', 'K'] as $g)
                    @php
                        $dVal = $dRow[$g] ?? 0;
                        $tVal = $tRow[$g] ?? 0;
                        $dOver = $dVal > $tVal;
                    @endphp
                    <td class="px-2 py-2.5 border border-slate-300 {{ $dOver ? 'bg-rose-50 text-rose-700 font-black' : ($dVal > 0 ? 'bg-emerald-50/60 text-emerald-800 font-black' : 'bg-white text-slate-400 font-medium') }}"
                        @if($dOver) title="Nilai Dept Head melebihi target kuota (+{{ $dVal - $tVal }})" @endif>
                        {{ $dVal }}
                    </td>
                    @endforeach
                    <td class="bg-[#d1fae5]/70 text-emerald-950 font-extrabold px-2.5 py-2.5 border border-slate-300 whitespace-nowrap">
                        {{ $dRow['total'] ?? 0 }}
                    </td>
                    @endif

                    @foreach(['S', 'A', 'B', 'C', 'K'] as $g)
                    @php
                        $aVal = $aRow[$g] ?? 0;
                        $tVal = $tRow[$g] ?? 0;
                        $overTarget = $aVal > $tVal;
                    @endphp
                    <td class="px-2 py-2.5 border border-slate-300 {{ $overTarget ? 'bg-rose-50 text-rose-700 font-black' : ($aVal > 0 ? 'bg-emerald-50/40 text-emerald-700 font-black' : 'bg-white text-slate-400 font-medium') }}"
                        @if($overTarget) title="Actual melebihi target kuota (+{{ $aVal - $tVal }})" @endif>
                        {{ $aVal }}
                    </td>
                    @endforeach
                    <td class="bg-[#fbe8e8]/60 text-[#92302e] font-extrabold px-2.5 py-2.5 border border-slate-300 whitespace-nowrap">
                        {{ $aRow['total'] ?? 0 }}
                    </td>

                    <td class="bg-amber-50/50 text-amber-800 font-bold px-3 py-2.5 border border-slate-300 whitespace-nowrap">
                        {{ $belumKj }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="{{ $showDeptComparison ? 20 : 14 }}" class="text-xs text-slate-400 py-5 text-center border border-slate-300">
                        Belum ada data target &amp; realisasi penilaian per KJ.
                    </td>
                </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td class="bg-[#0b5394] text-white font-black px-3 py-2.5 border border-[#0b5394]/70 uppercase tracking-wider">
                        TOTAL
                    </td>
                    @foreach(['S', 'A', 'B', 'C', 'K'] as $g)
                    <td class="bg-[#0b5394] text-white font-black px-2 py-2.5 border border-[#0b5394]/70">
                        {{ $targetCounts[$g] ?? 0 }}
                    </td>
                    @endforeach
                    <td class="bg-[#084175] text-white font-black px-2.5 py-2.5 border border-[#084175] whitespace-nowrap">
                        {{ $targetGrandTotal }}
                    </td>

                    @if($showDeptComparison)
                    @foreach(['S', 'A', 'B', 'C', 'K'] as $g)
                    <td class="bg-emerald-800 text-white font-black px-2 py-2.5 border border-emerald-700">
                        {{ $deptActualCounts[$g] ?? 0 }}
                    </td>
                    @endforeach
                    <td class="bg-emerald-950 text-white font-black px-2.5 py-2.5 border border-emerald-900 whitespace-nowrap">
                        {{ $displayDeptDinilai }}
                    </td>
                    @endif

                    @foreach(['S', 'A', 'B', 'C', 'K'] as $g)
                    <td class="bg-[#7e2b29] text-white font-black px-2 py-2.5 border border-[#6b2726]">
                        {{ $actualCounts[$g] ?? 0 }}
                    </td>
                    @endforeach
                    <td class="bg-[#5e1918] text-white font-black px-2.5 py-2.5 border border-[#5e1918] whitespace-nowrap">
                        {{ $displayDinilai }}
                    </td>

                    <td class="bg-slate-800 text-amber-300 font-black px-3 py-2.5 border border-slate-900 whitespace-nowrap">
                        {{ $displayBelum }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

