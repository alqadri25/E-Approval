@extends('layouts.app')

@section('content')
<div x-data="summaryApp()" class="max-w-7xl mx-auto space-y-6">


    <!-- Header & Filter Bar -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="text-xl font-black text-slate-900 tracking-tight">Summary PK & Monitoring Kuota Nilai</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 font-mono">
                    Tahun {{ date('Y') }}
                </span>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-sky-100 text-sky-800 font-mono">
                    {{ $selectedDept }} ({{ count($summaryRows) }} Karyawan Riil)
                </span>
            </div>
            <p class="text-xs text-slate-500 mt-1">
                Rekapitulasi otomatis form penilaian karya dan kontrol kesesuaian distribusi nilai terhadap target kuota departemen.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <!-- Department Selector -->
            <form method="GET" action="{{ route('summary.index') }}" class="flex items-center gap-2">
                <label class="text-xs font-bold text-slate-500 uppercase">Department:</label>
                <select name="departemen" onchange="this.form.submit()" class="text-xs font-semibold py-2 px-3 rounded-none border border-slate-300 hover:border-slate-400 bg-white text-slate-700 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] shadow-xs transition">
                    @foreach($distinctDepts as $dept)
                    <option value="{{ $dept }}" {{ $selectedDept === $dept ? 'selected' : '' }}>{{ $dept }}</option>
                    @endforeach
                </select>
            </form>

            <!-- Button Ubah Target Kuota Modal -->
            <button type="button" @click="openTargetModal()" class="px-3.5 py-2 rounded-xl text-xs font-bold text-white bg-amber-600 hover:bg-amber-700 shadow-sm flex items-center gap-1.5 transition-all">
                <span>⚙️</span> Atur Target Kuota
            </button>

            <!-- Penyesuaian Dept Head Shortcut -->
            <a href="{{ route('penilaian.targetAturan') }}" class="px-3.5 py-2 rounded-xl text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 border border-slate-300 shadow-sm flex items-center gap-1.5 transition-all">
                <span>⚖️</span> Penyesuaian Dept Head
            </a>

            <!-- Export CSV Button -->
            <a href="{{ route('summary.export', ['departemen' => $selectedDept]) }}" class="px-3.5 py-2 rounded-xl text-xs font-bold text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-sm flex items-center gap-1.5 transition-all">
                <span>📥</span> Export CSV / Excel
            </a>
        </div>
    </div>

    <!-- Quick Stat Cards Sesuai Data Riil Karyawan -->
    @php
        $kjList = $kjList ?? ['KJ 2', 'KJ 3', 'KJ 4', 'KJ 5'];
        $totS = 0; $totA = 0; $totB = 0; $totC = 0; $totK = 0; $totAll = 0;
        foreach($kjList as $kj) {
            $q = $targetKuota[$kj] ?? null;
            $totS += ($q->kuota_s ?? 0);
            $totA += ($q->kuota_a ?? 0);
            $totB += ($q->kuota_b ?? 0);
            $totC += ($q->kuota_c ?? 0);
            $totK += ($q->kuota_k ?? 0);
            $totAll += ($q->total_karyawan ?? 0);
        }

        $origTotal = 0;
        foreach($matrixOriginal as $mRow) {
            $origTotal += ($mRow['Total'] ?? 0);
        }
    @endphp

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Total Karyawan Riil</span>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-2xl font-black text-slate-900">{{ count($summaryRows) }}</span>
                <span class="text-xs font-semibold text-slate-500">orang terdata</span>
            </div>
            <span class="text-[11px] text-slate-400 mt-1 block">Dept: {{ $selectedDept }}</span>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-amber-200/80 bg-amber-50/20 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-amber-900 uppercase tracking-wider block">Target Kuota Dept</span>
                <button type="button" @click="openTargetModal()" class="text-[10px] font-bold text-amber-700 hover:text-amber-900 underline">Ubah &rarr;</button>
            </div>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-2xl font-black text-amber-950">{{ $totAll }}</span>
                <span class="text-xs font-semibold text-amber-800">slot kuota</span>
            </div>
            <span class="text-[11px] font-semibold text-amber-700 mt-1 block">S:{{ $totS }} &bull; A:{{ $totA }} &bull; B:{{ $totB }} &bull; C:{{ $totC }} &bull; K:{{ $totK }}</span>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-emerald-200/80 bg-emerald-50/20 shadow-sm">
            <span class="text-xs font-bold text-emerald-900 uppercase tracking-wider block">Form PK Dinilai</span>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-2xl font-black text-emerald-950">{{ $origTotal }}</span>
                <span class="text-xs font-semibold text-emerald-800">/ {{ count($summaryRows) }} karyawan</span>
            </div>
            <div class="w-full bg-emerald-100 rounded-full h-1.5 mt-2 overflow-hidden">
                <div class="bg-emerald-600 h-1.5 rounded-full" style="width: {{ count($summaryRows) > 0 ? min(100, round(($origTotal / count($summaryRows)) * 100)) : 0 }}%"></div>
            </div>
        </div>

        <div class="bg-white p-4 rounded-2xl border {{ $hasOverQuota ? 'border-rose-300 bg-rose-50/30' : 'border-sky-200/80 bg-sky-50/20' }} shadow-sm">
            <span class="text-xs font-bold {{ $hasOverQuota ? 'text-rose-900' : 'text-sky-900' }} uppercase tracking-wider block">Status Kuota Nilai</span>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-base font-black {{ $hasOverQuota ? 'text-rose-700' : 'text-emerald-700' }}">
                    {{ $hasOverQuota ? '⚠️ Ada Lebih Kuota' : '✓ Sesuai Kuota' }}
                </span>
            </div>
            <span class="text-[11px] {{ $hasOverQuota ? 'text-rose-600 font-semibold' : 'text-slate-500' }} mt-1 block">
                {{ $hasOverQuota ? implode(', ', $overQuotaGrades) : 'Tidak ada grade yang melebihi batas' }}
            </span>
        </div>
    </div>

    <!-- 3 COMPARISON MATRIX TABLES: TARGET vs REALISASI vs KONTROL KUOTA -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-slate-100 pb-3 mb-4 gap-2">
            <div>
                <h2 class="text-sm font-bold uppercase tracking-wider text-slate-700 flex items-center gap-2">
                    <span>📊</span> Matriks Distribusi: Target Kuota vs Realisasi Nilai & Kontrol Kuota
                </h2>
                <p class="text-xs text-slate-400 mt-0.5">Departemen: <strong class="text-slate-800">{{ $selectedDept }}</strong> &bull; Total Riil: <strong class="text-slate-800">{{ count($summaryRows) }} Karyawan</strong></p>
            </div>
            <button type="button" @click="openTargetModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-amber-50 text-amber-800 border border-amber-300 hover:bg-amber-100 transition-colors">
                <span>✏️</span> Atur Target Kuota
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- TABEL 1: TARGET KUOTA DEPARTEMEN -->
            <div class="border border-amber-200 rounded-2xl overflow-hidden bg-amber-50/20 flex flex-col justify-between">
                <div>
                    <div class="p-3 bg-amber-100/70 border-b border-amber-200 font-bold text-xs text-amber-950 flex items-center justify-between">
                        <span class="flex items-center gap-1.5">
                            <span>🎯</span> DEPT. : {{ $selectedDept }} (Target Kuota)
                        </span>
                        <span class="text-[10px] bg-amber-200 px-2 py-0.5 rounded text-amber-900 font-mono font-bold">
                            {{ $totAll }} Org
                        </span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-center text-xs">
                            <thead class="bg-amber-100/50 text-amber-900 font-bold border-b border-amber-200">
                                <tr>
                                    <th class="py-2.5 px-2 text-left bg-amber-200/50">KJ</th>
                                    <th class="py-2.5 px-1">S</th>
                                    <th class="py-2.5 px-1 text-rose-700">A</th>
                                    <th class="py-2.5 px-1 text-rose-700">B</th>
                                    <th class="py-2.5 px-1 text-rose-700">C</th>
                                    <th class="py-2.5 px-1">K</th>
                                    <th class="py-2.5 px-2 bg-red-100/60 text-red-950">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-amber-100 font-semibold text-slate-800">
                                @foreach($kjList as $kj)
                                @php
                                    $q = $targetKuota[$kj] ?? null;
                                    $s = $q->kuota_s ?? 0;
                                    $a = $q->kuota_a ?? 0;
                                    $b = $q->kuota_b ?? 0;
                                    $c = $q->kuota_c ?? 0;
                                    $k = $q->kuota_k ?? 0;
                                    $tot = $q->total_karyawan ?? ($s + $a + $b + $c + $k);
                                @endphp
                                <tr class="hover:bg-amber-100/30">
                                    <td class="py-2 px-2 text-left font-bold bg-amber-50">
                                        {{ $kj }}
                                        <span class="text-[10px] text-slate-400 font-normal">({{ $actualCountByKj[$kj] ?? 0 }} riil)</span>
                                    </td>
                                    <td class="py-2 px-1">{{ $s }}</td>
                                    <td class="py-2 px-1 text-rose-600 font-bold">{{ $a }}</td>
                                    <td class="py-2 px-1 text-rose-600 font-bold">{{ $b }}</td>
                                    <td class="py-2 px-1 text-rose-600 font-bold">{{ $c }}</td>
                                    <td class="py-2 px-1">{{ $k }}</td>
                                    <td class="py-2 px-2 bg-red-50 text-red-950 font-bold">{{ $tot }}</td>
                                </tr>
                                @endforeach
                                <!-- Total Row -->
                                <tr class="bg-[#92302e] text-white font-black border-t-2 border-[#7e2524]">
                                    <td class="py-2 px-2 text-left">TOTAL</td>
                                    <td class="py-2 px-1">{{ $totS }}</td>
                                    <td class="py-2 px-1">{{ $totA }}</td>
                                    <td class="py-2 px-1">{{ $totB }}</td>
                                    <td class="py-2 px-1">{{ $totC }}</td>
                                    <td class="py-2 px-1">{{ $totK }}</td>
                                    <td class="py-2 px-2 bg-[#6b2220] text-white">{{ $totAll }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="p-2.5 bg-amber-100/40 border-t border-amber-200 text-center">
                    <button type="button" @click="openTargetModal()" class="text-xs font-bold text-amber-900 hover:text-amber-950 underline">
                        ✏️ Ubah Target Kuota {{ $selectedDept }}
                    </button>
                </div>
            </div>

            <!-- TABEL 2: PK ORIGINAL (Based on form PK) -->
            <div class="border border-emerald-200 rounded-2xl overflow-hidden bg-emerald-50/20 flex flex-col justify-between">
                <div>
                    <div class="p-3 bg-emerald-100/70 border-b border-emerald-200 font-bold text-xs text-emerald-950 flex items-center justify-between">
                        <span class="flex items-center gap-1.5">
                            <span>📋</span> PK ORIGINAL (Based on form PK)
                        </span>
                        <span class="text-[10px] bg-emerald-200 px-2 py-0.5 rounded text-emerald-900 font-mono font-bold">
                            {{ $origTotal }} Dinilai
                        </span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-center text-xs">
                            <thead class="bg-emerald-100/50 text-emerald-900 font-bold border-b border-emerald-200">
                                <tr>
                                    <th class="py-2.5 px-2 text-left bg-emerald-200/50">KJ/PK</th>
                                    <th class="py-2.5 px-1">S</th>
                                    <th class="py-2.5 px-1">A</th>
                                    <th class="py-2.5 px-1">B</th>
                                    <th class="py-2.5 px-1">C</th>
                                    <th class="py-2.5 px-1">K</th>
                                    <th class="py-2.5 px-2 bg-emerald-100/80 text-emerald-950">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-emerald-100 font-semibold text-slate-800">
                                @php
                                    $origS = 0; $origA = 0; $origB = 0; $origC = 0; $origK = 0; $origTotRow = 0;
                                @endphp
                                @foreach($kjList as $kj)
                                @php
                                    $row = $matrixOriginal[$kj] ?? ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0, 'Total' => 0];
                                    $rowTotal = $row['Total'] ?? ($row['S'] + $row['A'] + $row['B'] + $row['C'] + $row['K']);
                                    $origS += $row['S']; $origA += $row['A']; $origB += $row['B']; $origC += $row['C']; $origK += $row['K'];
                                    $origTotRow += $rowTotal;
                                @endphp
                                <tr class="hover:bg-emerald-100/30">
                                    <td class="py-2 px-2 text-left font-bold bg-emerald-50">{{ str_replace(' ', '', $kj) }}</td>
                                    <td class="py-2 px-1">{{ $row['S'] }}</td>
                                    <td class="py-2 px-1 {{ $row['A'] > 0 ? 'text-emerald-700 font-bold' : '' }}">{{ $row['A'] }}</td>
                                    <td class="py-2 px-1 {{ $row['B'] > 0 ? 'text-emerald-700 font-bold' : '' }}">{{ $row['B'] }}</td>
                                    <td class="py-2 px-1 {{ $row['C'] > 0 ? 'text-emerald-700 font-bold' : '' }}">{{ $row['C'] }}</td>
                                    <td class="py-2 px-1">{{ $row['K'] }}</td>
                                    <td class="py-2 px-2 bg-emerald-50 text-emerald-950 font-bold">{{ $rowTotal }}</td>
                                </tr>
                                @endforeach
                                <!-- Total Row -->
                                <tr class="bg-emerald-700 text-white font-black border-t-2 border-emerald-800">
                                    <td class="py-2 px-2 text-left">TOTAL</td>
                                    <td class="py-2 px-1">{{ $origS }}</td>
                                    <td class="py-2 px-1">{{ $origA }}</td>
                                    <td class="py-2 px-1">{{ $origB }}</td>
                                    <td class="py-2 px-1">{{ $origC }}</td>
                                    <td class="py-2 px-1">{{ $origK }}</td>
                                    <td class="py-2 px-2 bg-emerald-900 text-white">{{ $origTotRow }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="p-2.5 bg-emerald-100/40 border-t border-emerald-200 text-center text-xs text-emerald-800">
                    Nilai riil hasil evaluasi atasan dari form penilaian
                </div>
            </div>

            <!-- TABEL 3: KONTROL KUOTA (REALISASI VS TARGET) -->
            <div class="border border-sky-200 rounded-2xl overflow-hidden bg-sky-50/20 flex flex-col justify-between">
                <div>
                    <div class="p-3 bg-sky-100/70 border-b border-sky-200 font-bold text-xs text-sky-950 flex items-center justify-between">
                        <span class="flex items-center gap-1.5">
                            <span>⚖️</span> KONTROL KUOTA (Realisasi vs Target)
                        </span>
                        <span class="text-[10px] px-2 py-0.5 rounded font-mono font-bold {{ $hasOverQuota ? 'bg-rose-200 text-rose-900' : 'bg-emerald-200 text-emerald-900' }}">
                            {{ $hasOverQuota ? 'Over Kuota' : 'Sesuai Kuota' }}
                        </span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-center text-xs">
                            <thead class="bg-sky-100/50 text-sky-900 font-bold border-b border-sky-200">
                                <tr>
                                    <th class="py-2.5 px-3 text-left">Grade</th>
                                    <th class="py-2.5 px-2">Target</th>
                                    <th class="py-2.5 px-2">Realisasi</th>
                                    <th class="py-2.5 px-2">Selisih</th>
                                    <th class="py-2.5 px-2">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-sky-100 font-semibold text-slate-800">
                                @foreach(['S', 'A', 'B', 'C', 'K'] as $g)
                                @php
                                    $comp = $quotaComparison[$g] ?? ['target' => 0, 'realisasi' => 0, 'selisih' => 0, 'status' => 'pas'];
                                    $diff = $comp['selisih'];
                                @endphp
                                <tr class="hover:bg-sky-100/30">
                                    <td class="py-2.5 px-3 text-left font-bold text-slate-900">
                                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-md text-[10px] font-black
                                            {{ $g === 'S' ? 'bg-emerald-100 text-emerald-800' :
                                              ($g === 'A' ? 'bg-sky-100 text-sky-800' :
                                              ($g === 'B' ? 'bg-amber-100 text-amber-800' :
                                              ($g === 'C' ? 'bg-slate-100 text-slate-700' : 'bg-rose-100 text-rose-800'))) }}">
                                            {{ $g }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-2 font-mono">{{ $comp['target'] }}</td>
                                    <td class="py-2.5 px-2 font-mono font-bold">{{ $comp['realisasi'] }}</td>
                                    <td class="py-2.5 px-2 font-mono font-bold {{ $diff > 0 ? 'text-rose-600' : ($diff < 0 ? 'text-slate-500' : 'text-emerald-600') }}">
                                        {{ $diff > 0 ? "+{$diff}" : $diff }}
                                    </td>
                                    <td class="py-2.5 px-2">
                                        @if($diff > 0)
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800">
                                            Lebih +{{ $diff }}
                                        </span>
                                        @elseif($diff === 0)
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                            ✓ Pas
                                        </span>
                                        @else
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">
                                            Sisa {{ abs($diff) }}
                                        </span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                                <!-- Total Row -->
                                <tr class="bg-slate-800 text-white font-black border-t-2 border-slate-900">
                                    <td class="py-2 px-3 text-left">TOTAL</td>
                                    <td class="py-2 px-2">{{ $totAll }}</td>
                                    <td class="py-2 px-2">{{ $origTotal }}</td>
                                    <td class="py-2 px-2 {{ ($origTotal - $totAll) > 0 ? 'text-rose-400' : 'text-emerald-400' }}">
                                        {{ ($origTotal - $totAll) > 0 ? "+".($origTotal - $totAll) : ($origTotal - $totAll) }}
                                    </td>
                                    <td class="py-2 px-2 text-[10px] font-normal text-slate-300">
                                        {{ $origTotal === $totAll ? 'Pas 100%' : 'Dalam Proses' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Guidance for Department Head if Over Quota -->
                <div class="p-3 bg-sky-100/50 border-t border-sky-200 text-left">
                    @if($hasOverQuota)
                    <div class="p-2.5 rounded-xl bg-rose-50 border border-rose-200 text-[11px] text-rose-900 space-y-1">
                        <div class="font-bold flex items-center gap-1 text-rose-800">
                            <span>⚠️</span> Catatan Dept Head:
                        </div>
                        <p>
                            Nilai pada <strong>{{ implode(', ', $overQuotaGrades) }}</strong> melebihi target kuota. Department Head perlu menyesuaikan peringkat agar karyawan yang berlebih turun ke rank/grade yang masih ada kuota.
                        </p>
                        <a href="{{ route('penilaian.targetAturan') }}" class="inline-block mt-1 font-bold text-rose-700 hover:text-rose-900 underline">
                            Buka Penyesuaian Nilai Dept Head &rarr;
                        </a>
                    </div>
                    @else
                    <div class="p-2 rounded-xl bg-emerald-50 border border-emerald-200 text-[11px] text-emerald-800 flex items-center gap-1.5">
                        <span class="text-emerald-600 font-bold">✓</span>
                        <span>Seluruh distribusi nilai berada dalam batas target kuota.</span>
                    </div>
                    @endif
                </div>
            </div>

        </div>

        <div class="mt-4 pt-3 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between text-xs text-slate-500 gap-2">
            <div class="flex flex-wrap items-center gap-4">
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> S: &gt; 4.50</span>
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-sky-500"></span> A: &gt; 3.75 - 4.50</span>
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span> B: 3.00 - 3.75</span>
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-slate-400"></span> C: 1.00 - &lt; 3.00</span>
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span> K: &lt; 1.00</span>
            </div>
            <span>Target kuota dapat disesuaikan melalui tombol <strong>Atur Target Kuota</strong> di atas.</span>
        </div>
    </div>

    <!-- REKAPITULASI KARYAWAN TABLE -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="font-bold text-slate-900">Daftar Rekapitulasi Penilaian Karyawan</h3>
                <p class="text-xs text-slate-500 mt-0.5">Department: <strong>{{ $selectedDept }}</strong> &bull; Total: {{ count($summaryRows) }} Karyawan Riil &bull; Bobot Hasil, Proses, Sikap, dan Man Management</p>
            </div>

            <!-- KJ Filter -->
            <div class="flex items-center gap-2">
                <label class="text-xs font-semibold text-slate-500">KJ / Golongan:</label>
                <select x-model="filterKj" class="text-xs py-1.5 px-3 rounded-xl border border-slate-200 bg-slate-50 font-medium">
                    <option value="">Semua KJ / Golongan</option>
                    @foreach($kjList as $kjOpt)
                    <option value="{{ $kjOpt }}">{{ $kjOpt }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead class="bg-slate-50 font-bold uppercase tracking-wider text-slate-600 border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-3 text-center">NO</th>
                        <th class="py-3 px-3">NRP</th>
                        <th class="py-3 px-3">NAMA LENGKAP</th>
                        <th class="py-3 px-3">POSITION</th>
                        <th class="py-3 px-2 text-center">KJ</th>
                        <th class="py-3 px-2 text-center">GOLONGAN</th>
                        <th class="py-3 px-2 text-center">HASIL</th>
                        <th class="py-3 px-2 text-center">PROSES</th>
                        <th class="py-3 px-2 text-center">SIKAP</th>
                        <th class="py-3 px-2 text-center">MGT</th>
                        <th class="py-3 px-2 text-center bg-slate-100 text-slate-900">TOTAL</th>
                        <th class="py-3 px-2 text-center">PREDIKAT PK</th>
                        <th class="py-3 px-3 text-center">STATUS DOKUMEN</th>
                        <th class="py-3 px-3 text-center">AKSI</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @forelse($summaryRows as $row)
                    @php
                        $st = $row['status'] ?? 'draft';
                        $statusLabels = [
                            'menunggu_foreman' => ['label' => 'Menunggu Foreman', 'color' => 'bg-amber-100 text-amber-800'],
                            'menunggu_section_head' => ['label' => 'Menunggu Section Head', 'color' => 'bg-sky-100 text-sky-800'],
                            'menunggu_dept_head' => ['label' => 'Menunggu Dept Head', 'color' => 'bg-indigo-100 text-indigo-800'],
                            'menunggu_div_head' => ['label' => 'Menunggu Div Head', 'color' => 'bg-purple-100 text-purple-800'],
                            'selesai' => ['label' => 'Selesai (Final)', 'color' => 'bg-emerald-100 text-emerald-800'],
                            'draft' => ['label' => 'Draft', 'color' => 'bg-slate-100 text-slate-600'],
                        ];
                        $stBadge = $statusLabels[$st] ?? ['label' => ucwords(str_replace('_', ' ', $st)), 'color' => 'bg-slate-100 text-slate-700'];
                    @endphp
                    <tr x-show="!filterKj || filterKj === '{{ $row['kj'] }}' || filterKj === '{{ $row['quota_kj'] ?? '' }}'" 
                        class="hover:bg-slate-50/60 transition-colors {{ ($row['status'] ?? '') === 'selesai' ? 'bg-emerald-50/20' : '' }}">
                        <td class="py-3 px-3 text-center font-mono text-slate-400">{{ $row['no'] }}</td>
                        <td class="py-3 px-3 font-mono font-bold text-slate-900">{{ $row['nrp'] }}</td>
                        <td class="py-3 px-3 font-semibold text-slate-900">
                            {{ $row['nama'] }}
                            <span class="block text-[10px] text-slate-400 font-normal">{{ $row['seksi'] }}</span>
                        </td>
                        <td class="py-3 px-3 text-slate-600">{{ $row['jabatan'] }}</td>
                        <td class="py-3 px-2 text-center">
                            <span class="px-2 py-0.5 rounded font-bold font-mono text-[10px] bg-slate-100 text-slate-700">
                                {{ $row['kj'] }}
                            </span>
                        </td>
                        <td class="py-3 px-2 text-center font-mono">{{ $row['sub_gol'] }}</td>
                        <td class="py-3 px-2 text-center font-mono">{{ number_format((float)($row['hasil_kerja'] ?? 0), 2) }}</td>
                        <td class="py-3 px-2 text-center font-mono">{{ number_format((float)($row['proses_kerja'] ?? 0), 2) }}</td>
                        <td class="py-3 px-2 text-center font-mono">{{ number_format((float)($row['sikap_kerja'] ?? 0), 2) }}</td>
                        <td class="py-3 px-2 text-center font-mono">{{ number_format((float)($row['man_management'] ?? 0), 2) }}</td>
                        <td class="py-3 px-2 text-center font-mono font-bold text-slate-900 bg-slate-50">
                            {{ number_format((float)($row['total_nilai'] ?? 0), 2) }}
                        </td>
                        <td class="py-3 px-2 text-center">
                            @if($row['total_nilai'] > 0 || !empty($row['grade_original']))
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-lg font-black font-mono text-xs
                                {{ $row['grade_original'] === 'S' ? 'bg-emerald-100 text-emerald-800' :
                                  ($row['grade_original'] === 'A' ? 'bg-sky-100 text-sky-800' :
                                  ($row['grade_original'] === 'B' ? 'bg-amber-100 text-amber-800' :
                                  ($row['grade_original'] === 'C' ? 'bg-slate-100 text-slate-600' : 'bg-rose-100 text-rose-800'))) }}">
                                {{ $row['grade_original'] }}
                            </span>
                            @else
                            <span class="text-slate-400 font-mono text-xs">-</span>
                            @endif
                        </td>

                        <!-- Status Workflow Dokumen -->
                        <td class="py-2.5 px-3 text-center">
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold {{ $stBadge['color'] }}">
                                {{ $stBadge['label'] }}
                            </span>
                        </td>

                        <td class="py-3 px-3 text-center">
                            @if($row['doc_id'])
                            <a href="{{ \App\Http\Controllers\PenilaianController::getFormUrlForUser(auth()->user(), $row['doc_id']) }}" class="text-emerald-600 hover:text-emerald-700 font-semibold hover:underline">
                                Buka Form &rarr;
                            </a>
                            @else
                            <span class="text-slate-400">Belum Ada</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="14" class="py-8 text-center text-slate-400 italic">
                            Belum ada karyawan terdata di Departemen {{ $selectedDept }}.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- MODAL ATUR TARGET KUOTA DEPARTEMEN -->
    <div x-show="showTargetModal" 
         x-cloak 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-fade-in"
         @keydown.escape.window="showTargetModal = false">
        <div class="bg-white rounded-3xl max-w-2xl w-full shadow-2xl border border-slate-200 overflow-hidden transform transition-all"
             @click.away="showTargetModal = false">
            
            <!-- Modal Header -->
            <div class="p-6 bg-gradient-to-r from-amber-600 to-amber-700 text-white flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-black tracking-tight flex items-center gap-2">
                        <span>⚙️</span> Atur Target Kuota Nilai
                    </h3>
                    <p class="text-xs text-amber-100 mt-0.5">
                        Departemen: <strong>{{ $selectedDept }}</strong> &bull; Total Riil: <strong>{{ count($summaryRows) }} Karyawan</strong>
                    </p>
                </div>
                <button type="button" @click="showTargetModal = false" class="text-amber-200 hover:text-white text-xl font-bold p-1 rounded-lg hover:bg-amber-800/40 leading-none">
                    &times;
                </button>
            </div>

            <!-- Modal Body Form -->
            <form @submit.prevent="saveTargetKuota()" class="p-6 space-y-5">
                <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                    <span class="text-xs text-slate-500 font-semibold">
                        Tentukan alokasi target grade (S, A, B, C, K) per Kelompok Jabatan (KJ).
                    </span>
                    <button type="button" @click="autoDistribute()" class="text-xs font-bold text-amber-700 bg-amber-50 hover:bg-amber-100 border border-amber-200 px-3 py-1.5 rounded-xl transition-all flex items-center gap-1">
                        <span>⚡</span> Hitung Otomatis (Proporsional)
                    </button>
                </div>

                <!-- Table Input Kuota per KJ -->
                <div class="overflow-x-auto border border-slate-200 rounded-2xl">
                    <table class="w-full text-center text-xs">
                        <thead class="bg-slate-50 text-slate-700 font-bold border-b border-slate-200">
                            <tr>
                                <th class="py-2.5 px-3 text-left">KJ</th>
                                <th class="py-2.5 px-2 bg-slate-100">Riil</th>
                                <th class="py-2.5 px-2 text-emerald-800">S</th>
                                <th class="py-2.5 px-2 text-sky-800">A</th>
                                <th class="py-2.5 px-2 text-amber-800">B</th>
                                <th class="py-2.5 px-2 text-slate-800">C</th>
                                <th class="py-2.5 px-2 text-rose-800">K</th>
                                <th class="py-2.5 px-3 bg-amber-50 text-amber-950 font-black">Total</th>
                                <th class="py-2.5 px-2">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-semibold">
                            <template x-for="(data, kj) in targetKjData" :key="kj">
                                <tr class="hover:bg-slate-50/50">
                                    <td class="py-2.5 px-3 text-left font-bold text-slate-900" x-text="kj"></td>
                                    <td class="py-2.5 px-2 font-mono text-slate-500 bg-slate-50" x-text="data.actual"></td>
                                    <td class="py-2 px-1">
                                        <input type="number" min="0" x-model.number="data.s" class="w-12 text-center py-1 rounded-lg border border-slate-200 focus:ring-1 focus:ring-amber-500 font-mono font-bold text-xs">
                                    </td>
                                    <td class="py-2 px-1">
                                        <input type="number" min="0" x-model.number="data.a" class="w-12 text-center py-1 rounded-lg border border-slate-200 focus:ring-1 focus:ring-amber-500 font-mono font-bold text-xs">
                                    </td>
                                    <td class="py-2 px-1">
                                        <input type="number" min="0" x-model.number="data.b" class="w-12 text-center py-1 rounded-lg border border-slate-200 focus:ring-1 focus:ring-amber-500 font-mono font-bold text-xs">
                                    </td>
                                    <td class="py-2 px-1">
                                        <input type="number" min="0" x-model.number="data.c" class="w-12 text-center py-1 rounded-lg border border-slate-200 focus:ring-1 focus:ring-amber-500 font-mono font-bold text-xs">
                                    </td>
                                    <td class="py-2 px-1">
                                        <input type="number" min="0" x-model.number="data.k" class="w-12 text-center py-1 rounded-lg border border-slate-200 focus:ring-1 focus:ring-amber-500 font-mono font-bold text-xs">
                                    </td>
                                    <td class="py-2.5 px-3 bg-amber-50/60 font-mono font-black text-amber-950" x-text="(data.s || 0) + (data.a || 0) + (data.b || 0) + (data.c || 0) + (data.k || 0)"></td>
                                    <td class="py-2.5 px-2">
                                        <span x-show="((data.s || 0) + (data.a || 0) + (data.b || 0) + (data.c || 0) + (data.k || 0)) === data.actual" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                            ✓ Pas
                                        </span>
                                        <span x-show="((data.s || 0) + (data.a || 0) + (data.b || 0) + (data.c || 0) + (data.k || 0)) !== data.actual" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">
                                            Selisih
                                        </span>
                                    </td>
                                </tr>
                            </template>
                            <!-- Total Row in Modal -->
                            <tr class="bg-slate-900 text-white font-black">
                                <td class="py-2.5 px-3 text-left">TOTAL</td>
                                <td class="py-2.5 px-2 font-mono" x-text="totalModalActual">{{ count($summaryRows) }}</td>
                                <td class="py-2.5 px-1 font-mono" x-text="totalModalS"></td>
                                <td class="py-2.5 px-1 font-mono" x-text="totalModalA"></td>
                                <td class="py-2.5 px-1 font-mono" x-text="totalModalB"></td>
                                <td class="py-2.5 px-1 font-mono" x-text="totalModalC"></td>
                                <td class="py-2.5 px-1 font-mono" x-text="totalModalK"></td>
                                <td class="py-2.5 px-3 bg-slate-800 text-amber-400 font-mono font-black" x-text="totalModalTarget"></td>
                                <td class="py-2.5 px-2">
                                    <span x-show="totalModalTarget === totalModalActual" class="text-emerald-400 text-xs font-bold">✓ Sesuai</span>
                                    <span x-show="totalModalTarget !== totalModalActual" class="text-amber-400 text-xs font-bold">≠ Berbeda</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-between pt-2">
                    <span class="text-[11px] text-slate-500">
                        Target kuota disimpan ke database master kuota untuk Departemen <strong>{{ $selectedDept }}</strong>.
                    </span>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="showTargetModal = false" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 transition-all">
                            Batal
                        </button>
                        <button type="submit" :disabled="savingTarget" class="px-5 py-2 rounded-xl text-xs font-bold text-white bg-amber-600 hover:bg-amber-700 shadow-sm transition-all flex items-center gap-1.5 disabled:opacity-50">
                            <span x-show="!savingTarget">💾 Simpan Target Kuota</span>
                            <span x-show="savingTarget">Menyimpan...</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

</div>

<!-- Alpine Script for Target Kuota -->
<script>
function summaryApp() {
    return {
        filterKj: '',
        showTargetModal: false,
        savingTarget: false,
        targetKjData: {
            @foreach($kjList as $kj)
            @php
                $q = $targetKuota[$kj] ?? null;
                $act = $actualCountByKj[$kj] ?? 0;
            @endphp
            '{{ $kj }}': {
                actual: {{ $act }},
                s: {{ $q->kuota_s ?? 0 }},
                a: {{ $q->kuota_a ?? 0 }},
                b: {{ $q->kuota_b ?? 0 }},
                c: {{ $q->kuota_c ?? 0 }},
                k: {{ $q->kuota_k ?? 0 }},
            },
            @endforeach
        },

        openTargetModal() {
            this.showTargetModal = true;
        },

        autoDistribute() {
            for (let kj in this.targetKjData) {
                let act = this.targetKjData[kj].actual;
                if (act <= 0) {
                    this.targetKjData[kj].s = 0;
                    this.targetKjData[kj].a = 0;
                    this.targetKjData[kj].b = 0;
                    this.targetKjData[kj].c = 0;
                    this.targetKjData[kj].k = 0;
                    continue;
                }
                let s = Math.round(act * 0.10);
                let a = Math.round(act * 0.30);
                let c = Math.round(act * 0.20);
                let k = Math.round(act * 0.10);
                let b = Math.max(0, act - (s + a + c + k));
                let diff = act - (s + a + b + c + k);
                if (diff !== 0) {
                    b += diff;
                }
                this.targetKjData[kj].s = s;
                this.targetKjData[kj].a = a;
                this.targetKjData[kj].b = b;
                this.targetKjData[kj].c = c;
                this.targetKjData[kj].k = k;
            }
        },

        get totalModalActual() {
            let tot = 0;
            for (let kj in this.targetKjData) tot += (this.targetKjData[kj].actual || 0);
            return tot;
        },
        get totalModalS() {
            let s = 0;
            for (let kj in this.targetKjData) s += (Number(this.targetKjData[kj].s) || 0);
            return s;
        },
        get totalModalA() {
            let a = 0;
            for (let kj in this.targetKjData) a += (Number(this.targetKjData[kj].a) || 0);
            return a;
        },
        get totalModalB() {
            let b = 0;
            for (let kj in this.targetKjData) b += (Number(this.targetKjData[kj].b) || 0);
            return b;
        },
        get totalModalC() {
            let c = 0;
            for (let kj in this.targetKjData) c += (Number(this.targetKjData[kj].c) || 0);
            return c;
        },
        get totalModalK() {
            let k = 0;
            for (let kj in this.targetKjData) k += (Number(this.targetKjData[kj].k) || 0);
            return k;
        },
        get totalModalTarget() {
            return this.totalModalS + this.totalModalA + this.totalModalB + this.totalModalC + this.totalModalK;
        },

        async saveTargetKuota() {
            this.savingTarget = true;
            try {
                const response = await fetch("{{ route('summary.updateKuota') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        departemen: '{{ $selectedDept }}',
                        kuota: this.targetKjData
                    })
                });
                const res = await response.json();
                if (res.success) {
                    this.showTargetModal = false;
                    window.location.reload();
                } else {
                    alert('Gagal menyimpan target: ' + res.message);
                }
            } catch (err) {
                console.error(err);
                alert('Terjadi kesalahan koneksi.');
            } finally {
                this.savingTarget = false;
            }
        }
    }
}
</script>
@endsection
