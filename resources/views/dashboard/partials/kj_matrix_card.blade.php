@props([])
<div @if(!empty($id)) id="{{ $id }}" @endif class="bg-white rounded-none p-5 border border-slate-300 shadow-sm scroll-mt-20 flex flex-col justify-between">
    <div>
        <div class="flex items-center justify-between pb-3 border-b border-slate-200 mb-4 gap-2">
            <div class="flex items-center gap-2.5">
                <span class="w-2.5 h-2.5 {{ $accentBg }}"></span>
                <h3 class="text-sm font-bold text-slate-900 tracking-tight">
                    {{ $title }} <span class="text-slate-400 font-normal text-xs">{{ $subtitle }}</span>
                </h3>
            </div>
            <span class="text-[11px] font-bold px-2.5 py-1 border whitespace-nowrap {{ $badgeClass }}">
                {{ $badgeText }}
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full border-collapse border border-slate-300 text-center select-none">
                <thead>
                    <tr class="text-slate-800 text-xs">
                        <th class="w-24 bg-[#fff2cc] font-extrabold px-3 py-2.5 text-center border border-slate-300 tracking-wider">
                            KJ \ PK
                        </th>
                        <th class="w-12 bg-[#d9ead3] font-bold px-2.5 py-2.5 text-center border border-slate-300">S</th>
                        <th class="w-12 bg-[#d9ead3] font-bold px-2.5 py-2.5 text-center border border-slate-300">A</th>
                        <th class="w-12 bg-[#d9ead3] font-bold px-2.5 py-2.5 text-center border border-slate-300">B</th>
                        <th class="w-12 bg-[#d9ead3] font-bold px-2.5 py-2.5 text-center border border-slate-300">C</th>
                        <th class="w-12 bg-[#d9ead3] font-bold px-2.5 py-2.5 text-center border border-slate-300">K</th>
                        <th class="bg-[#d9ead3] font-extrabold px-4 py-2.5 text-center border border-slate-300 whitespace-nowrap">
                            {{ $lastColHeader ?? 'Total' }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($matrix ?? [] as $kjName => $row)
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="bg-[#ffe599] text-slate-900 font-extrabold text-xs px-3 py-2.5 text-center border border-slate-300 whitespace-nowrap">
                            {{ $kjName }}
                        </td>
                        @foreach(['S', 'A', 'B', 'C', 'K'] as $gradeKey)
                        <td class="bg-white text-xs px-2.5 py-2.5 text-center border border-slate-300 {{ ($row[$gradeKey] ?? 0) > 0 ? 'text-red-600 font-black text-sm' : 'text-slate-600 font-medium' }}">
                            {{ $row[$gradeKey] ?? 0 }}
                        </td>
                        @endforeach
                        <td class="bg-[#cfe2f3] text-slate-900 font-extrabold text-xs px-4 py-2.5 text-center border border-slate-300 whitespace-nowrap">
                            {{ $row['total'] ?? 0 }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-xs text-slate-400 py-4 text-center border border-slate-300">
                            {{ $emptyText ?? 'Belum ada data penilaian' }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="bg-[#0b5394] text-white">
                        <td class="bg-[#0b5394] text-white font-black text-xs px-3 py-2.5 text-center border border-[#0b5394]/70 uppercase tracking-wider">
                            TOTAL
                        </td>
                        @foreach(['S', 'A', 'B', 'C', 'K'] as $gradeKey)
                        <td class="bg-[#0b5394] text-white font-black text-xs px-2.5 py-2.5 text-center border border-[#0b5394]/70">
                            {{ $summary[$gradeKey] ?? 0 }}
                        </td>
                        @endforeach
                        <td class="bg-[#084175] text-white font-black text-sm px-4 py-2.5 text-center border border-[#084175] whitespace-nowrap">
                            {{ $summary['total'] ?? 0 }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    @if(!empty($footerLeft) || !empty($footerRight))
    <div class="mt-3.5 pt-2.5 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
        <span class="flex items-center gap-1.5">
            <span class="w-1.5 h-1.5 rounded-full {{ $dotColor ?? 'bg-slate-400' }}"></span>
            {{ $footerLeft }}
        </span>
        <span class="text-slate-400 text-[10px]">{{ $footerRight }}</span>
    </div>
    @endif
</div>

