@extends('layouts.app', ['title' => 'Target & Aturan Nilai - Admin'])

@section('content')
<div class="space-y-6" x-data="targetAturanApp()">

    <!-- Top Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
                Target &amp; Aturan Nilai
            </h1>
            <p class="text-xs text-slate-500 mt-0.5">
                Kelola nilai sesuai target kuota departemen
            </p>
        </div>
        <div class="w-10 h-10 rounded-xl bg-[#fbe8e8] text-[#92302e] font-black flex items-center justify-center text-sm shadow-sm">
            D
        </div>
    </div>

    <!-- 5 Quota Cards (Nilai S, Nilai A, Nilai B, Nilai C, Nilai K) -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3.5">
        
        <!-- Nilai S Card -->
        <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm flex flex-col justify-between">
            <div>
                <p class="text-xs font-bold text-slate-500 leading-none">Nilai S</p>
                <div class="flex items-baseline gap-1 mt-2">
                    <span class="text-2xl font-black text-slate-900 leading-none" x-text="counts.S">14</span>
                    <span class="text-xs font-semibold text-slate-400">/ <span x-text="limits.S">18</span></span>
                </div>
            </div>
            <div class="mt-3">
                <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden">
                    <div class="bg-[#10b981] h-1.5 rounded-full transition-all duration-300" 
                         :style="'width: ' + Math.min(100, Math.round((counts.S / limits.S) * 100)) + '%'"></div>
                </div>
                <div class="flex justify-between items-center text-[10px] text-slate-400 mt-1 font-medium">
                    <span x-text="Math.round((counts.S / limits.S) * 100) + '%'">77%</span>
                    <span class="text-emerald-700 font-bold" x-show="counts.S <= limits.S">✓ Sesuai</span>
                </div>
            </div>
        </div>

        <!-- Nilai A Card (Highlighted with Red Badge when exceeding quota!) -->
        <div class="bg-white rounded-xl p-4 border transition-all shadow-sm flex flex-col justify-between"
             :class="counts.A > limits.A ? 'border-rose-300 ring-2 ring-rose-100 bg-rose-50/10' : 'border-slate-200'">
            <div>
                <div class="flex items-center justify-between">
                    <p class="text-xs font-bold text-slate-500 leading-none">Nilai A</p>
                    <span x-show="counts.A > limits.A" 
                          class="px-1.5 py-0.5 rounded text-[9px] font-black bg-[#dc3545] text-white shadow-xs"
                          x-text="'+' + (counts.A - limits.A) + ' LEBIH KUOTA'">
                        +4 LEBIH KUOTA
                    </span>
                </div>
                <div class="flex items-baseline gap-1 mt-2">
                    <span class="text-2xl font-black leading-none" 
                          :class="counts.A > limits.A ? 'text-rose-600' : 'text-slate-900'"
                          x-text="counts.A">46</span>
                    <span class="text-xs font-semibold text-slate-400">/ <span x-text="limits.A">42</span></span>
                </div>
            </div>
            <div class="mt-3">
                <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden">
                    <div class="h-1.5 rounded-full transition-all duration-300"
                         :class="counts.A > limits.A ? 'bg-[#dc3545]' : 'bg-[#10b981]'"
                         :style="'width: ' + Math.min(100, Math.round((counts.A / limits.A) * 100)) + '%'"></div>
                </div>
                <div class="flex justify-between items-center text-[10px] mt-1 font-medium">
                    <span :class="counts.A > limits.A ? 'text-rose-600 font-bold' : 'text-slate-400'"
                          x-text="Math.round((counts.A / limits.A) * 100) + '%'">109%</span>
                    <span class="text-rose-600 font-bold" x-show="counts.A > limits.A">Lebih Kuota</span>
                    <span class="text-emerald-700 font-bold" x-show="counts.A <= limits.A">✓ Sesuai</span>
                </div>
            </div>
        </div>

        <!-- Nilai B Card -->
        <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm flex flex-col justify-between">
            <div>
                <p class="text-xs font-bold text-slate-500 leading-none">Nilai B</p>
                <div class="flex items-baseline gap-1 mt-2">
                    <span class="text-2xl font-black text-slate-900 leading-none" x-text="counts.B">38</span>
                    <span class="text-xs font-semibold text-slate-400">/ <span x-text="limits.B">42</span></span>
                </div>
            </div>
            <div class="mt-3">
                <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden">
                    <div class="bg-[#10b981] h-1.5 rounded-full transition-all duration-300"
                         :style="'width: ' + Math.min(100, Math.round((counts.B / limits.B) * 100)) + '%'"></div>
                </div>
                <div class="flex justify-between items-center text-[10px] text-slate-400 mt-1 font-medium">
                    <span x-text="Math.round((counts.B / limits.B) * 100) + '%'">90%</span>
                    <span class="text-emerald-700 font-bold" x-show="counts.B <= limits.B">✓ Sesuai</span>
                </div>
            </div>
        </div>

        <!-- Nilai C Card -->
        <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm flex flex-col justify-between">
            <div>
                <p class="text-xs font-bold text-slate-500 leading-none">Nilai C</p>
                <div class="flex items-baseline gap-1 mt-2">
                    <span class="text-2xl font-black text-slate-900 leading-none" x-text="counts.C">20</span>
                    <span class="text-xs font-semibold text-slate-400">/ <span x-text="limits.C">28</span></span>
                </div>
            </div>
            <div class="mt-3">
                <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden">
                    <div class="bg-[#10b981] h-1.5 rounded-full transition-all duration-300"
                         :style="'width: ' + Math.min(100, Math.round((counts.C / limits.C) * 100)) + '%'"></div>
                </div>
                <div class="flex justify-between items-center text-[10px] text-slate-400 mt-1 font-medium">
                    <span x-text="Math.round((counts.C / limits.C) * 100) + '%'">71%</span>
                    <span class="text-emerald-700 font-bold" x-show="counts.C <= limits.C">✓ Sesuai</span>
                </div>
            </div>
        </div>

        <!-- Nilai K Card -->
        <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm flex flex-col justify-between">
            <div>
                <p class="text-xs font-bold text-slate-500 leading-none">Nilai K</p>
                <div class="flex items-baseline gap-1 mt-2">
                    <span class="text-2xl font-black text-slate-900 leading-none" x-text="counts.K">6</span>
                    <span class="text-xs font-semibold text-slate-400">/ <span x-text="limits.K">14</span></span>
                </div>
            </div>
            <div class="mt-3">
                <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden">
                    <div class="bg-[#10b981] h-1.5 rounded-full transition-all duration-300"
                         :style="'width: ' + Math.min(100, Math.round((counts.K / limits.K) * 100)) + '%'"></div>
                </div>
                <div class="flex justify-between items-center text-[10px] text-slate-400 mt-1 font-medium">
                    <span x-text="Math.round((counts.K / limits.K) * 100) + '%'">42%</span>
                    <span class="text-emerald-700 font-bold" x-show="counts.K <= limits.K">✓ Sesuai</span>
                </div>
            </div>
        </div>

    </div>

    <!-- Warning / Status Alert Banner matching Mockup -->
    <div class="p-4 rounded-xl text-xs leading-relaxed transition-all"
         :class="counts.A > limits.A ? 'bg-amber-50 border border-amber-300 text-amber-900' : 'bg-emerald-50 border border-emerald-300 text-emerald-900'">
        <div class="flex items-center gap-2 font-medium">
            <span class="text-sm" x-text="counts.A > limits.A ? '⚠️' : '✓'"></span>
            <span x-show="counts.A > limits.A">
                Kuota A lebih pada <strong x-text="(counts.A - limits.A)">4</strong> orang. Silakan klik dropdown di bawah untuk revisi / docking nilai karyawan yang bersangkutan agar sesuai aturan kuota departemen.
            </span>
            <span x-show="counts.A <= limits.A">
                Seluruh kuota grade telah sesuai dengan target departemen. Anda dapat langsung menyimpan dan meneruskan ke Division Head / Manajemen.
            </span>
        </div>
    </div>

    <!-- Table: Penyesuaian Nilai Akhir (Docking) -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        
        <form id="formDocking" method="POST" action="{{ route('penilaian.simpanTargetAturan') }}">
            @csrf

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 text-slate-400 font-semibold text-[11px]">
                            <th class="py-3 px-5 font-normal">Nama Lengkap</th>
                            <th class="py-3 px-5 font-normal">NRP</th>
                            <th class="py-3 px-5 font-normal">Predikat Section Head</th>
                            <th class="py-3 px-5 font-normal">Nilai Akhir</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($employees as $emp)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="py-3.5 px-5 font-bold text-slate-800">
                                {{ $emp['nama'] }}
                            </td>
                            <td class="py-3.5 px-5 font-mono text-slate-500 font-semibold">
                                {{ $emp['nrp'] }}
                            </td>
                            <td class="py-3.5 px-5 font-bold">
                                <span class="inline-block px-2.5 py-0.5 rounded text-xs font-black
                                    {{ $emp['predikat_section'] === 'S' ? 'bg-emerald-100 text-emerald-800' : 
                                      ($emp['predikat_section'] === 'A' ? 'bg-blue-100 text-blue-800' : 
                                      ($emp['predikat_section'] === 'B' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-800')) }}">
                                    {{ $emp['predikat_section'] }}
                                </span>
                            </td>
                            <td class="py-2.5 px-5">
                                @if(!empty($emp['doc_id']))
                                <select name="docking[{{ $emp['doc_id'] }}]" 
                                        x-model="employeeGrades['{{ $emp['nrp'] }}']"
                                        @change="onGradeChanged('{{ $emp['nrp'] }}', $event.target.value)"
                                        class="bg-white border border-slate-300 text-xs font-bold text-slate-800 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none cursor-pointer w-24 shadow-xs">
                                    <option value="S">S</option>
                                    <option value="A">A</option>
                                    <option value="B">B</option>
                                    <option value="C">C</option>
                                    <option value="K">K</option>
                                </select>
                                @else
                                <span class="text-slate-400 italic font-mono">-</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Bottom Action Button matching Mockup -->
            <div class="p-4 border-t border-slate-100 bg-slate-50/40 flex justify-end">
                <button type="submit" 
                        class="min-btn px-6 py-2.5 rounded-xl font-bold text-xs bg-[#92302e] hover:bg-[#146c43] text-white shadow-sm transition-all flex items-center gap-2">
                    <span>Simpan &amp; Kirim ke Division Head</span>
                </button>
            </div>

        </form>

    </div>

</div>

@push('scripts')
<script>
    function targetAturanApp() {
        return {
            limits: {
                S: {{ $quotaLimits['S'] ?? 18 }},
                A: {{ $quotaLimits['A'] ?? 42 }},
                B: {{ $quotaLimits['B'] ?? 42 }},
                C: {{ $quotaLimits['C'] ?? 28 }},
                K: {{ $quotaLimits['K'] ?? 14 }},
            },
            counts: {
                S: {{ $currentCounts['S'] ?? 14 }},
                A: {{ $currentCounts['A'] ?? 46 }},
                B: {{ $currentCounts['B'] ?? 38 }},
                C: {{ $currentCounts['C'] ?? 20 }},
                K: {{ $currentCounts['K'] ?? 6 }},
            },
            employeeGrades: {
                @foreach($employees as $emp)
                '{{ $emp['nrp'] }}': '{{ $emp['nilai_akhir'] ?? $emp['predikat_section'] }}',
                @endforeach
            },
            initialGrades: {
                @foreach($employees as $emp)
                '{{ $emp['nrp'] }}': '{{ $emp['nilai_akhir'] ?? $emp['predikat_section'] }}',
                @endforeach
            },

            onGradeChanged(nrp, newGrade) {
                const oldGrade = this.initialGrades[nrp];
                if (oldGrade && this.counts[oldGrade] > 0) {
                    this.counts[oldGrade]--;
                }
                if (newGrade && this.counts[newGrade] !== undefined) {
                    this.counts[newGrade]++;
                }
                this.initialGrades[nrp] = newGrade;
            }
        };
    }
</script>
@endpush
@endsection

