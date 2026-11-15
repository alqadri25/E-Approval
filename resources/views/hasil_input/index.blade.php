@extends('layouts.app')

@section('title', 'Hasil Penilaian - E-Approval Penilaian Karya')

@section('content')
<div class="space-y-6">

    <!-- Header & Action Controls (Tegas & Tanpa Lengkungan) -->
    <div class="bg-white border border-slate-300 p-5 shadow-sm rounded-none flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-xl font-black text-slate-900 tracking-tight">Hasil Penilaian</h1>
                <span class="sr-only">Database Hasil Input PK 2025</span>
            </div>
            <p class="text-xs text-slate-500 mt-1">
                Data rekapitulasi penilaian kinerja karyawan: Hasil Kerja, Proses Kerja, Sikap Kerja, Man Management, Total Nilai, Grade, dan Catatan Atasan.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('hasil-penilaian.export', ['departemen' => $selectedDept, 'jabatan' => $selectedJabatan, 'kj' => $selectedKj, 'q' => $search]) }}" 
               class="px-4 py-2 text-xs font-bold text-slate-800 bg-white border border-slate-400 hover:bg-slate-100 shadow-2xs flex items-center gap-2 rounded-none transition">
                <svg class="w-4 h-4 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span>Export CSV / Excel</span>
            </a>
        </div>
    </div>

    <!-- Filter Bar (Tegas & Tanpa Lengkungan) -->
    <div class="bg-white border border-slate-300 p-4 shadow-sm rounded-none overflow-x-auto">
        <form method="GET" action="{{ route('hasil-penilaian.index') }}" class="flex flex-nowrap items-end gap-3 min-w-max">
            
            <!-- Search NRP / NAMA -->
            <div class="w-64 sm:w-72 shrink-0">
                <label class="block text-[10px] font-bold text-slate-700 uppercase tracking-wider mb-1">Cari Karyawan (NRP / Nama)</label>
                <div class="relative">
                    <input type="text" name="q" value="{{ $search }}" placeholder="Cari NRP atau nama karyawan..."
                           onchange="this.form.submit()"
                           class="w-full text-xs font-semibold text-slate-800 border border-slate-300 hover:border-slate-400 bg-white py-2 pl-9 pr-3 rounded-none focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-xs">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
            </div>

            <!-- Filter DEPARTMENT -->
            <div class="w-44 shrink-0">
                <label class="block text-[10px] font-bold text-slate-700 uppercase tracking-wider mb-1">Department</label>
                <select name="departemen" onchange="this.form.submit()" class="w-full text-xs font-semibold text-slate-700 border border-slate-300 hover:border-slate-400 bg-white py-2 px-2.5 rounded-none focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-xs">
                    <option value="" {{ empty($selectedDept) ? 'selected' : '' }}>Semua Department</option>
                    @foreach($distinctDepts as $dept)
                    <option value="{{ $dept }}" {{ (!empty($selectedDept) && strcasecmp($selectedDept, $dept) === 0) ? 'selected' : '' }}>{{ $dept }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Filter POSITION -->
            <div class="w-40 shrink-0">
                <label class="block text-[10px] font-bold text-slate-700 uppercase tracking-wider mb-1">Position</label>
                <select name="jabatan" onchange="this.form.submit()" class="w-full text-xs font-semibold text-slate-700 border border-slate-300 hover:border-slate-400 bg-white py-2 px-2.5 rounded-none focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-xs">
                    <option value="" {{ empty($selectedJabatan) ? 'selected' : '' }}>Semua Position</option>
                    @foreach($distinctJabatans as $jb)
                    <option value="{{ $jb }}" {{ (!empty($selectedJabatan) && strcasecmp($selectedJabatan, $jb) === 0) ? 'selected' : '' }}>{{ $jb }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Filter KJ / Golongan -->
            <div class="w-36 shrink-0">
                <label class="block text-[10px] font-bold text-slate-700 uppercase tracking-wider mb-1">KJ / Golongan</label>
                <select name="kj" onchange="this.form.submit()" class="w-full text-xs font-semibold text-slate-700 border border-slate-300 hover:border-slate-400 bg-white py-2 px-2.5 rounded-none focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-xs">
                    <option value="" {{ empty($selectedKj) ? 'selected' : '' }}>Semua KJ / Golongan</option>
                    @foreach($distinctKjs as $kVal)
                    <option value="{{ $kVal }}" {{ (!empty($selectedKj) && strcasecmp($selectedKj, $kVal) === 0) ? 'selected' : '' }}>{{ $kVal }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Hidden submit button for Enter key -->
            <button type="submit" class="hidden" tabindex="-1"></button>

            @if($search || !empty($selectedDept) || !empty($selectedJabatan) || !empty($selectedKj))
            <div class="flex items-center shrink-0">
                <a href="{{ route('hasil-penilaian.index') }}" 
                   class="px-3.5 py-2 border border-rose-300 bg-rose-50 hover:bg-rose-100 text-xs font-bold text-[#92302e] rounded-none cursor-pointer transition shadow-2xs flex items-center gap-1 whitespace-nowrap">
                    <span>✕</span> Reset Filter
                </a>
            </div>
            @endif
        </form>
    </div>

    <!-- Tabel Hasil Penilaian (Tegas & Persegi seperti Master Karyawan) -->
    <div class="bg-white border border-slate-300 shadow-sm rounded-none overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-separate border-spacing-0  whitespace-nowrap">
                <thead>
                    <tr class="bg-slate-100 text-slate-700 font-bold text-[11px] uppercase tracking-wider sticky top-0 z-10 shadow-2xs">
                        <th class="py-3 px-2 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-12 min-w-[48px] max-w-[48px] whitespace-nowrap sticky left-0 z-30">NO</th>
                        <th class="py-3 px-2.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-28 min-w-[100px] max-w-[100px] whitespace-nowrap sticky left-[48px] z-30">NRP</th>
                        <th class="py-3 px-3.5 border-b border-r-2 border-r-slate-500 border-slate-300 bg-slate-100 font-bold text-slate-700 w-52 min-w-[200px] max-w-[200px] whitespace-nowrap sticky left-[148px] z-30 shadow-[4px_0_8px_-2px_rgba(0,0,0,0.15)]">NAMA LENGKAP</th>
                        <th class="py-3 px-3.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 whitespace-nowrap">DEPARTMENT</th>
                        <th class="py-3 px-3.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 whitespace-nowrap">POSITION</th>
                        <th class="py-3 px-3.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center whitespace-nowrap">KJ</th>
                        <th class="py-3 px-3.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center whitespace-nowrap">
                            Hasil Kerja
                            <span class="sr-only">HASIL KERJA</span>
                        </th>
                        <th class="py-3 px-3.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center whitespace-nowrap">
                            Proses Kerja
                            <span class="sr-only">PROSES KERJA</span>
                        </th>
                        <th class="py-3 px-3.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center whitespace-nowrap">
                            Sikap Kerja
                            <span class="sr-only">SIKAP KERJA</span>
                        </th>
                        <th class="py-3 px-3.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center whitespace-nowrap">
                            Man Management
                            <span class="sr-only">MAN MGT</span>
                        </th>
                        <th class="py-3 px-3.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center whitespace-nowrap">Total Nilai</th>
                        <th class="py-3 px-3.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center whitespace-nowrap">NILAI PK ORIGINAL</th>
                        <th class="py-3 px-4 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 whitespace-nowrap">CATATAN ATASAN</th>
                        <th class="py-3 px-3 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-20 whitespace-nowrap">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $r)
                    <tr class="hover:bg-slate-50 transition-colors group">
                        
                        <!-- NO (Sticky Left, 100% Solid) -->
                        <td class="py-2.5 px-2 border-b border-r border-slate-300 text-center font-bold text-slate-800 bg-white group-hover:bg-slate-100 whitespace-nowrap w-12 min-w-[48px] max-w-[48px] sticky left-0 z-20">
                            {{ $loop->iteration }}
                        </td>

                        <!-- NRP (Sticky Left, 100% Solid) -->
                        <td class="py-2.5 px-2.5 border-b border-r border-slate-300 text-center font-mono font-normal text-slate-800 bg-white group-hover:bg-slate-100 whitespace-nowrap w-28 min-w-[100px] max-w-[100px] sticky left-[48px] z-20">
                            {{ $r['nrp'] }}
                        </td>

                        <!-- NAMA (Sticky Left, 100% Solid, Divider Tebal) -->
                        <td class="py-2.5 px-3.5 border-b border-r-2 border-r-slate-500 border-slate-300 font-normal text-slate-800 bg-white group-hover:bg-slate-100 whitespace-nowrap w-52 min-w-[200px] max-w-[200px] sticky left-[148px] z-20 shadow-[4px_0_8px_-2px_rgba(0,0,0,0.15)]">
                            {{ $r['name'] }}
                        </td>

                        <!-- NAMA DEPT. -->
                        <td class="py-2.5 px-3.5 border-b border-r border-slate-300 text-slate-700 font-semibold">
                            {{ $r['dept'] }}
                        </td>

                        <!-- JABATAN -->
                        <td class="py-2.5 px-3.5 border-b border-r border-slate-300 text-slate-800">
                            {{ $r['jabatan'] }}
                        </td>

                        <!-- KJ -->
                        <td class="py-2.5 px-3.5 border-b border-r border-slate-300 text-center font-mono font-bold text-slate-800">
                            {{ $r['kj'] }}
                        </td>

                        <!-- Hasil Kerja -->
                        <td class="py-2.5 px-3.5 border-b border-r border-slate-300 text-center font-mono font-semibold text-slate-800">
                            {{ number_format($r['hasil_kerja_bobot'], 2) }}
                        </td>

                        <!-- Proses Kerja -->
                        <td class="py-2.5 px-3.5 border-b border-r border-slate-300 text-center font-mono font-semibold text-slate-800">
                            {{ number_format($r['proses_kerja_bobot'], 2) }}
                        </td>

                        <!-- Sikap Kerja -->
                        <td class="py-2.5 px-3.5 border-b border-r border-slate-300 text-center font-mono font-semibold text-slate-800">
                            {{ number_format($r['sikap_kerja_bobot'], 2) }}
                        </td>

                        <!-- Man Management -->
                        <td class="py-2.5 px-3.5 border-b border-r border-slate-300 text-center font-mono font-semibold text-slate-800">
                            {{ $r['man_management_bobot'] !== null ? number_format($r['man_management_bobot'], 2) : '-' }}
                        </td>

                        <!-- Total Nilai -->
                        <td class="py-2.5 px-3.5 border-b border-r border-slate-300 text-center font-mono font-black text-slate-900 bg-slate-50/70">
                            {{ number_format($r['nilai_pk'], 2) }}
                        </td>

                        <!-- NILAI PK ORIGINAL -->
                        <td class="py-2.5 px-3.5 border-b border-r border-slate-300 text-center">
                            <span class="inline-block px-2.5 py-0.5 border text-xs font-mono font-bold rounded-none
                                {{ $r['grade'] === 'S' ? 'bg-emerald-50 text-emerald-800 border-emerald-400' :
                                  ($r['grade'] === 'A' ? 'bg-sky-50 text-sky-800 border-sky-400' :
                                  ($r['grade'] === 'B' ? 'bg-amber-50 text-amber-800 border-amber-400' :
                                  ($r['grade'] === 'C' ? 'bg-slate-100 text-slate-700 border-slate-400' : 'bg-rose-50 text-rose-800 border-rose-400'))) }}">
                                {{ $r['grade'] }}
                            </span>
                        </td>

                        <!-- CATATAN ATASAN -->
                        <td class="py-2.5 px-4 border-b border-r border-slate-300 text-slate-600 max-w-xs truncate" title="{{ $r['catatan_atasan'] }}">
                            {{ $r['catatan_atasan'] ?: '-' }}
                        </td>

                        <!-- AKSI -->
                        <td class="py-2.5 px-3 border-b border-r border-slate-300 text-center">
                            @if(!empty($r['doc_id']))
                            <a href="{{ route('penilaian.show', $r['doc_id']) }}" 
                               class="p-1.5 border-b border-r border-slate-300 bg-white hover:bg-blue-50 hover:border-blue-400 text-slate-600 hover:text-blue-600 transition shadow-2xs inline-flex items-center justify-center cursor-pointer rounded-none"
                               title="Buka Lembar Penilaian (Formulir PK)">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <span class="sr-only">Buka Penilaian</span>
                            </a>
                            @else
                            <a href="{{ route('penilaian.byNrp', $r['nrp']) }}" 
                               class="p-1.5 border-b border-r border-slate-300 bg-white hover:bg-emerald-50 hover:border-emerald-400 text-slate-600 hover:text-emerald-700 transition shadow-2xs inline-flex items-center justify-center cursor-pointer rounded-none"
                               title="Mulai Input Penilaian">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                                <span class="sr-only">Input Penilaian</span>
                            </a>
                            @endif
                        </td>

                    </tr>
                    @empty
                    <tr>
                        <td colspan="14" class="py-8 text-center text-slate-400 border-b border-r border-slate-300">
                            Tidak ada data penilaian yang cocok dengan kriteria filter.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Footer Info -->
        <div class="p-3 bg-slate-50 border-t border-slate-200 flex items-center justify-between text-xs text-slate-500">
            <span>Total <strong>{{ count($rows) }}</strong> baris penilaian ditampilkan</span>
            <span class="text-[11px] text-slate-400">Data terintegrasi otomatis dengan sistem formulir PK & e-approval</span>
        </div>
    </div>

</div>
@endsection
