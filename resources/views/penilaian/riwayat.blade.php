@extends('layouts.app', ['title' => 'Riwayat Penilaian'])

@section('content')
@php
    $isStructuralSh = $user->isStructuralSectionHead();
    $isDeptHeadRiwayat = !empty($isDeptHeadUser) || $user->isDepartmentHead();
    $isSectionHeadRiwayat = $user->isSectionHead() || in_array($user->role, ['section_head', 'section', 'sechead']);
    $isForemanRiwayat = $user->isForeman() || in_array($user->role, ['foreman', 'ketua']);
    $isDivHeadRiwayat = !empty($isDivHeadUser) || $user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head']);
    $isMandiriUser = (!$isStructuralSh && ($user->isKj5Up() || $user->isGol4NonKasie())) || (!$user->isAdmin() && !$user->isHr() && !$isForemanRiwayat && !$isSectionHeadRiwayat && !$isDeptHeadRiwayat && !$isDivHeadRiwayat);
    $hideSemuaLabel = $isForemanRiwayat || $isSectionHeadRiwayat || $isDeptHeadRiwayat || $isMandiriUser;
    $isDeptOrDivRiwayat = !empty($isDeptOrDivHead) || $isDeptHeadRiwayat || $isDivHeadRiwayat;
    $evaluatorRoleLabel = $isStructuralSh ? 'Section Head' : ($isDeptHeadRiwayat ? 'Dept Head' : ($isDivHeadRiwayat ? 'Division Head' : ($isMandiriUser ? 'Mandiri' : 'Foreman')));
    $evaluatorPkColLabel = $isStructuralSh ? 'Sect Head' : ($isDeptHeadRiwayat ? 'Dept Head' : ($isDivHeadRiwayat ? 'Div Head' : ($isMandiriUser ? 'Mandiri' : 'Foreman')));
@endphp
<div class="space-y-6">

    <!-- Top Header Card (Merah Musashi & Rounded-None) -->
    <div class="bg-gradient-to-r from-[#962226] via-[#92302e] to-[#781b1f] text-white rounded-none p-5 sm:p-6 border border-[#781b1f] shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight">
                Riwayat Penilaian
            </h1>
            <p class="text-xs text-red-100 font-medium mt-1">
                Catatan historis penilaian karyawan yang telah diselesaikan beserta akumulasi poin dan perbandingan target.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('penilaian.cari') }}" 
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-none bg-white hover:bg-slate-100 text-[#92302e] font-bold text-xs shadow-sm transition-colors border border-white"
               title="Buka form untuk menilai karyawan lainnya">
                <svg class="w-4 h-4 text-[#92302e]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                <span>Nilai Karyawan Lain</span>
            </a>
        </div>
    </div>

    <!-- 4 Metric Cards (Termasuk Akumulasi Poin - Rounded-None) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <!-- 1. Total Dinilai -->
        <div class="bg-white rounded-none border border-slate-300 p-4 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 block mb-1">Total Sudah Dinilai</span>
                <div class="text-2xl sm:text-3xl font-black text-[#92302e] tracking-tight">
                    {{ $totalDinilai }}
                </div>
                <span class="text-[11px] text-slate-500 mt-1 block font-medium">Karyawan Bawahan</span>
            </div>
            <div class="w-10 h-10 rounded-none bg-[#fbe8e8] text-[#92302e] border border-red-200 flex items-center justify-center text-lg font-black">
                ✓
            </div>
        </div>

        <!-- 2. Belum Dinilai -->
        <div class="bg-white rounded-none border border-slate-300 p-4 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 block mb-1">Belum Dinilai</span>
                <div class="text-2xl sm:text-3xl font-black text-amber-600 tracking-tight">
                    {{ $totalBelum }}
                </div>
                <span class="text-[11px] text-slate-500 mt-1 block font-medium">Menunggu Penilaian</span>
            </div>
            <div class="w-10 h-10 rounded-none bg-amber-50 text-amber-600 border border-amber-200 flex items-center justify-center text-lg font-black">
                ⏳
            </div>
        </div>

        <!-- 3. Akumulasi Poin yang Dinilai -->
        <div class="bg-white rounded-none border border-slate-300 p-4 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 block mb-1">Akumulasi Poin Skor</span>
                <div class="text-2xl sm:text-3xl font-black text-indigo-700 font-mono tracking-tight">
                    {{ number_format($totalPoin, 2) }}
                </div>
                <span class="text-[11px] text-slate-500 mt-1 block font-medium">Total Poin Diberikan</span>
            </div>
            <div class="w-10 h-10 rounded-none bg-indigo-50 text-indigo-700 border border-indigo-200 flex items-center justify-center text-lg font-black font-mono">
                Σ
            </div>
        </div>

        <!-- 4. Rata-Rata Poin Skor -->
        <div class="bg-white rounded-none border border-slate-300 p-4 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 block mb-1">Rata-Rata Skor</span>
                <div class="text-2xl sm:text-3xl font-black text-slate-900 font-mono tracking-tight">
                    {{ number_format($avgPoin, 2) }}
                </div>
                <span class="text-[11px] text-slate-500 mt-1 block font-medium">Skala 1.00 s/d 5.00</span>
            </div>
            <div class="w-10 h-10 rounded-none bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center justify-center text-lg font-black font-mono">
                ★
            </div>
        </div>
    </div>

    @if(!$isDivHeadRiwayat && !$user->isSecretary())
    {{-- ============================================================== --}}
    {{-- PERBANDINGAN: TARGET VS REALISASI PENILAIAN                    --}}
    {{-- ============================================================== --}}
    @if($user->isForeman() || $user->isSectionHead() || in_array($user->role, ['section_head', 'sechead']) || $isDeptHeadRiwayat)
    @include('penilaian.partials.target_actual_matrix_table', [
        'evaluatorRoleLabel' => $evaluatorRoleLabel,
        'includeDeptComparison' => false,
        'matrixKjList' => $matrixKjList ?? [],
        'subCountByKj' => $subCountByKj ?? [],
        'targetKjMatrix' => $targetKjMatrix ?? [],
        'actualKjMatrix' => $actualKjMatrix ?? [],
        'deptActualKjMatrix' => $deptActualKjMatrix ?? [],
        'targetCounts' => $targetCounts ?? [],
        'actualCounts' => $quotaActualCounts ?? ($actualCounts ?? []),
        'deptActualCounts' => $deptQuotaActualCounts ?? [],
        'totalDinilai' => $quotaTotalDinilai ?? ($totalDinilai ?? 0),
        'deptTotalDinilai' => $deptQuotaTotalDinilai ?? 0,
        'totalBelum' => $quotaTotalBelum ?? ($totalBelum ?? 0),
    ])
    @else
    <div class="bg-white rounded-none border border-slate-300 p-4 sm:p-5 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-slate-200 gap-2">
            <div>
                <h3 class="text-xs sm:text-sm font-black text-slate-900 tracking-wider flex items-center gap-2">
                    <span class="w-2.5 h-2.5 bg-[#92302e]"></span>
                    <span>Grafik Perbandingan: Target Kategori vs Realisasi Dinilai {{ $evaluatorRoleLabel }}</span>
                </h3>
                <p class="text-[11px] text-slate-500 font-medium mt-0.5">
                    Membandingkan rencana target perolehan kategori nilai (S, A, B, C, K) dengan perolehan riil yang telah dinilai oleh {{ $evaluatorRoleLabel }}.
                </p>
            </div>
            <div class="flex items-center gap-4 text-xs font-bold">
                <span class="inline-flex items-center gap-1.5 text-slate-800">
                    <span class="w-3 h-3 bg-slate-900 inline-block"></span> Target: {{ array_sum($targetCounts) }}
                </span>
                <span class="inline-flex items-center gap-1.5 text-[#92302e]">
                    <span class="w-3 h-3 bg-[#92302e] inline-block"></span> Realisasi Dinilai: {{ $quotaTotalDinilai ?? $totalDinilai }}
                </span>
            </div>
        </div>

        <div class="relative w-full h-72 sm:h-80">
            <canvas id="comparisonBarChart"></canvas>
        </div>
    </div>
    @endif
    @endif

    <!-- ============================================================== -->
    <!-- TABEL KARYAWAN YANG BARU SAJA SUDAH DINILAI                   -->
    <!-- ============================================================== -->
    <div class="bg-white rounded-none border border-slate-300 shadow-sm overflow-hidden"
         x-data="{
             isDeptOrDiv: {{ $isDeptOrDivRiwayat ? 'true' : 'false' }},
             search: '{{ addslashes($search ?? '') }}',
             filterDept: '{{ addslashes($filterDept ?? '') }}',
             filterBagian: '{{ addslashes($filterBagian ?? '') }}',
             filterSection: '{{ addslashes($filterSection ?? '') }}',
             filterJabatan: '{{ addslashes($filterJabatan ?? '') }}',
             filterKj: '{{ addslashes($filterKj ?? '') }}',
             matches(row) {
                 const s = this.search.toLowerCase().trim();
                 const matchSearch = !s || 
                     (row.nrp && row.nrp.toLowerCase().includes(s)) ||
                     (row.nama_lengkap && row.nama_lengkap.toLowerCase().includes(s)) ||
                     (row.departemen && row.departemen.toLowerCase().includes(s)) ||
                     (row.nama_bagian && row.nama_bagian.toLowerCase().includes(s)) ||
                     (row.seksi && row.seksi.toLowerCase().includes(s)) ||
                     (row.jabatan && row.jabatan.toLowerCase().includes(s));

                 const matchDept = !this.filterDept ||
                     (row.departemen && row.departemen.trim().toLowerCase() === this.filterDept.trim().toLowerCase());

                 const normBagian = (v) => String(v || '').trim().replace(/\.+$/, '').toLowerCase();
                 const matchBagian = this.isDeptOrDiv
                     ? (!this.filterSection || (normBagian(row.seksi) === normBagian(this.filterSection)))
                     : (!this.filterBagian || (normBagian(row.nama_bagian) === normBagian(this.filterBagian)) || (normBagian(row.seksi) === normBagian(this.filterBagian)));

                 const matchJabatan = !this.filterJabatan || 
                     (row.jabatan && row.jabatan.trim().toLowerCase() === this.filterJabatan.trim().toLowerCase());

                 const matchKj = !this.filterKj || 
                     (row.kj && row.kj.trim().toLowerCase() === this.filterKj.trim().toLowerCase());

                 return matchSearch && matchDept && matchBagian && matchJabatan && matchKj;
             }
         }">
        <div class="p-4 sm:p-5 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50">
            <div>
                <h3 class="text-xs sm:text-sm font-black text-slate-900 tracking-wider">
                    Daftar Karyawan yang Baru Saja Selesai Dinilai
                </h3>
                <p class="text-[11px] text-slate-500 font-medium mt-0.5">
                    Menampilkan bawahan yang telah dinilai dan dikunci tanda tangannya oleh {{ $isDivHeadRiwayat ? 'Dept Head / Division Head' : $evaluatorRoleLabel }}.
                </p>
            </div>
            <span class="text-xs font-bold font-mono text-slate-600 bg-white border border-slate-300 px-2.5 py-1">
                {{ count($evaluatedList) }} Selesai Dinilai
            </span>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="p-4 border-b border-slate-200 bg-slate-50/50 overflow-x-auto">
            <form method="GET" action="{{ route('penilaian.riwayat') }}" class="flex flex-nowrap items-end gap-3 min-w-max">
                
                <!-- 1. Search Box -->
                <div class="w-64 sm:w-72 shrink-0">
                    <label class="block text-[10px] font-bold text-slate-700 uppercase tracking-wider mb-1">
                        {{ $isDeptOrDivRiwayat ? 'Cari NRP / Nama / Section' : 'Cari NRP / Nama / Bagian' }}
                    </label>
                    <div class="relative">
                        <input type="text" name="q" value="{{ $search ?? '' }}" 
                               x-model="search"
                               placeholder="{{ $isDeptOrDivRiwayat ? 'Ketik NRP, nama karyawan, section, atau position...' : 'Ketik NRP, nama karyawan, bagian, atau position...' }}"
                               class="w-full text-xs font-semibold text-slate-800 border border-slate-300 hover:border-slate-400 bg-white py-2 pl-8 pr-3 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-2xs rounded-none">
                        <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                </div>

                @if($isDivHeadRiwayat)
                <!-- 2. Filter Department (Division Head) -->
                <div class="w-40 sm:w-44 shrink-0">
                    <label class="block text-[10px] font-bold text-slate-700 uppercase tracking-wider mb-1">
                        Department
                    </label>
                    <select name="departemen" 
                            x-model="filterDept"
                            class="w-full text-xs font-semibold text-slate-700 border border-slate-300 hover:border-slate-400 bg-white py-2 px-2.5 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-2xs rounded-none cursor-pointer">
                        <option value="">{{ $hideSemuaLabel ? 'Department' : 'Semua Department' }}</option>
                        @foreach($departmentList ?? [] as $dep)
                        <option value="{{ $dep }}" {{ strcasecmp($filterDept ?? '', $dep) === 0 ? 'selected' : '' }}>{{ $dep }}</option>
                        @endforeach
                    </select>
                </div>
                @endif

                <!-- Filter Section (Dept/Div Head) atau Bagian (Foreman/Section Head/Admin) -->
                @if($isDeptOrDivRiwayat)
                <div class="w-40 sm:w-44 shrink-0">
                    <label class="block text-[10px] font-bold text-slate-700 uppercase tracking-wider mb-1">
                        Section
                    </label>
                    <select name="seksi" 
                            x-model="filterSection"
                            class="w-full text-xs font-semibold text-slate-700 border border-slate-300 hover:border-slate-400 bg-white py-2 px-2.5 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-2xs rounded-none cursor-pointer">
                        <option value="">{{ $hideSemuaLabel ? 'Section' : 'Semua Section' }}</option>
                        @foreach($sectionList ?? [] as $sec)
                        <option value="{{ $sec }}" {{ strcasecmp(rtrim($filterSection ?? '', '.'), rtrim($sec, '.')) === 0 ? 'selected' : '' }}>{{ $sec }}</option>
                        @endforeach
                    </select>
                </div>
                @else
                <div class="w-40 sm:w-44 shrink-0">
                    <label class="block text-[10px] font-bold text-slate-700 uppercase tracking-wider mb-1">
                        Bagian
                    </label>
                    <select name="bagian" 
                            x-model="filterBagian"
                            class="w-full text-xs font-semibold text-slate-700 border border-slate-300 hover:border-slate-400 bg-white py-2 px-2.5 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-2xs rounded-none cursor-pointer">
                        <option value="">{{ $hideSemuaLabel ? 'Bagian' : 'Semua Bagian' }}</option>
                        @foreach($bagianList ?? [] as $b)
                        <option value="{{ $b }}" {{ strcasecmp(rtrim($filterBagian ?? '', '.'), rtrim($b, '.')) === 0 ? 'selected' : '' }}>{{ $b }}</option>
                        @endforeach
                    </select>
                </div>
                @endif

                @if(!$isDivHeadRiwayat)
                <!-- 3. Filter Position -->
                <div class="w-40 sm:w-44 shrink-0">
                    <label class="block text-[10px] font-bold text-slate-700 uppercase tracking-wider mb-1">
                        Position
                    </label>
                    <select name="jabatan" 
                            x-model="filterJabatan"
                            class="w-full text-xs font-semibold text-slate-700 border border-slate-300 hover:border-slate-400 bg-white py-2 px-2.5 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-2xs rounded-none cursor-pointer">
                        <option value="">{{ $hideSemuaLabel ? 'Position' : 'Semua Position' }}</option>
                        @foreach($jabatanList ?? [] as $jb)
                        <option value="{{ $jb }}" {{ strcasecmp($filterJabatan ?? '', $jb) === 0 ? 'selected' : '' }}>{{ $jb }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- 4. Filter KJ / Golongan -->
                <div class="w-36 sm:w-40 shrink-0">
                    <label class="block text-[10px] font-bold text-slate-700 uppercase tracking-wider mb-1">
                        KJ / Golongan
                    </label>
                    <select name="kj" 
                            x-model="filterKj"
                            class="w-full text-xs font-semibold text-slate-700 border border-slate-300 hover:border-slate-400 bg-white py-2 px-2.5 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-2xs rounded-none cursor-pointer">
                        <option value="">{{ $hideSemuaLabel ? 'KJ / Golongan' : 'Semua KJ / Golongan' }}</option>
                        @foreach($kjList as $k)
                        <option value="{{ $k }}" {{ ($filterKj ?? '') === $k ? 'selected' : '' }}>{{ $k }}</option>
                        @endforeach
                    </select>
                </div>
                @endif

                <!-- Hidden submit button for Enter key -->
                <button type="submit" class="hidden" tabindex="-1"></button>

                <!-- 5. Reset Button -->
                <div class="flex items-center gap-1.5 shrink-0" x-show="search || filterDept || filterKj || filterBagian || filterSection || filterJabatan">
                    @if(!empty($search) || !empty($filterDept) || !empty($filterKj) || !empty($filterBagian) || !empty($filterSection) || !empty($filterJabatan))
                    <a href="{{ route('penilaian.riwayat') }}"
                       class="px-3.5 py-2 border border-rose-300 bg-rose-50 text-[#92302e] text-xs font-bold hover:bg-rose-100 transition rounded-none flex items-center gap-1 shadow-2xs whitespace-nowrap">
                        <span>✕</span> Reset
                    </a>
                    @else
                    <button type="button" 
                            @click="search = ''; filterDept = ''; filterBagian = ''; filterSection = ''; filterJabatan = ''; filterKj = ''"
                            class="px-3.5 py-2 border border-rose-300 bg-rose-50 text-[#92302e] text-xs font-bold hover:bg-rose-100 transition rounded-none cursor-pointer flex items-center gap-1 shadow-2xs whitespace-nowrap">
                        <span>✕</span> Reset
                    </button>
                    @endif
                </div>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-100 text-slate-700 font-bold border-b border-slate-300 uppercase text-[10px] tracking-wider whitespace-nowrap">
                        <th class="py-3 px-3 w-10 text-center border-r border-slate-300">NO</th>
                        <th class="py-3 px-3 border-r border-slate-300 text-center">NRP</th>
                        <th class="py-3 px-3.5 border-r border-slate-300">Nama Lengkap</th>
                        @if($isDivHeadRiwayat)
                        <th class="py-3 px-3 border-r border-slate-300 text-center">Department</th>
                        @endif
                        <th class="py-3 px-3 border-r border-slate-300 text-center">Section</th>
                        <th class="py-3 px-3.5 border-r border-slate-300">Nama Bagian</th>
                        <th class="py-3 px-3.5 border-r border-slate-300">Position</th>
                        <th class="py-3 px-3 border-r border-slate-300 text-center">KJ</th>
                        <th class="py-3 px-3 border-r border-slate-300 text-center">Golongan</th>
                        <th class="py-3 px-3 border-r border-slate-300 text-center">Tanggal Penilaian</th>
                        <th class="py-3 px-3 border-r border-slate-300 text-center">Hasil Kerja</th>
                        <th class="py-3 px-3 border-r border-slate-300 text-center">Proses Kerja</th>
                        <th class="py-3 px-3 border-r border-slate-300 text-center">Sikap Kerja</th>
                        <th class="py-3 px-3 border-r border-slate-300 text-center">Man Management</th>
                        <th class="py-3 px-3 border-r border-slate-300 text-center">Total Nilai</th>
                        @if($isDivHeadRiwayat)
                        <th class="py-3 px-3.5 border-r border-slate-300 text-center">Nilai PK Dept Head</th>
                        <th class="py-3 px-3.5 border-r border-slate-300 text-center">Nilai PK Div Head</th>
                        @elseif($isDeptHeadRiwayat)
                            @if($user->isSecretary())
                            <th class="py-3 px-3.5 border-r border-slate-300 text-center">Nilai PK Mandiri</th>
                            @else
                            <th class="py-3 px-3.5 border-r border-slate-300 text-center">Nilai PK Foreman</th>
                            <th class="py-3 px-3.5 border-r border-slate-300 text-center">Nilai Mandiri</th>
                            <th class="py-3 px-3.5 border-r border-slate-300 text-center">Nilai PK Sect Head</th>
                            <th class="py-3 px-3.5 border-r border-slate-300 text-center">Nilai PK Dept Head</th>
                            @endif
                        @elseif($isStructuralSh)
                        <th class="py-3 px-3.5 border-r border-slate-300 text-center">Nilai PK Foreman</th>
                        <th class="py-3 px-3.5 border-r border-slate-300 text-center">Nilai Mandiri</th>
                        <th class="py-3 px-3.5 border-r border-slate-300 text-center">Nilai PK Sect Head</th>
                        @elseif($isForemanRiwayat)
                        <th class="py-3 px-3.5 border-r border-slate-300 text-center">Nilai PK Foreman</th>
                        @elseif($isMandiriUser)
                        <th class="py-3 px-3.5 border-r border-slate-300 text-center">Nilai PK Mandiri</th>
                        @else
                        <th class="py-3 px-3.5 border-r border-slate-300 text-center">Nilai PK {{ $evaluatorPkColLabel }}</th>
                        @endif
                        <th class="py-3 px-3 border-r border-slate-300 text-center">Status Dokumen</th>
                        <th class="py-3 px-3 text-center w-28">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse($evaluatedList as $idx => $row)
                    <tr class="hover:bg-slate-50 transition-colors"
                        x-show="matches({
                            nrp: '{{ addslashes($row['nrp']) }}',
                            nama_lengkap: '{{ addslashes($row['nama_lengkap']) }}',
                            departemen: '{{ addslashes($row['departemen'] ?? '-') }}',
                            nama_bagian: '{{ addslashes($row['nama_bagian'] ?? $row['seksi']) }}',
                            seksi: '{{ addslashes($row['seksi']) }}',
                            jabatan: '{{ addslashes($row['jabatan']) }}',
                            sub_gol: '{{ addslashes($row['sub_gol']) }}',
                            golongan: '{{ addslashes($row['golongan']) }}',
                            kj: '{{ addslashes($row['kj']) }}'
                        })">
                        <td class="py-3 px-3 text-center font-bold text-slate-400 border-r border-slate-200">
                            {{ $idx + 1 }}
                        </td>
                        <td class="py-3 px-3 font-mono font-bold text-slate-800 border-r border-slate-200 text-center whitespace-nowrap">
                            {{ $row['nrp'] }}
                        </td>
                        <td class="py-3 px-3.5 font-bold text-slate-900 border-r border-slate-200 whitespace-nowrap">
                            <div class="inline-flex items-center gap-1.5 whitespace-nowrap">
                                <span class="whitespace-nowrap">{{ $row['nama_lengkap'] }}</span>
                                @php
                                    $isSelf = (strcasecmp($row['nrp'] ?? '', $user->nrp ?? '') === 0 
                                            || strcasecmp(trim($row['nama_lengkap'] ?? ''), trim($user->nama ?? '')) === 0
                                            || (!empty($row['nama_lengkap']) && !empty($user->nama) && (stripos($user->nama, $row['nama_lengkap']) !== false || stripos($row['nama_lengkap'], $user->nama) !== false)));
                                @endphp
                                @if($isSelf)
                                    <span class="px-1.5 py-0.5 text-[9.5px] font-extrabold bg-[#fbe8e8] text-[#92302e] border border-[#ce5d5b]/40 rounded-none inline-flex items-center shrink-0 whitespace-nowrap" title="Penilaian Mandiri (Self Appraisal) - Anda menilai diri sendiri">
                                        (Anda)
                                    </span>
                                @endif
                            </div>
                        </td>
                        @if($isDivHeadRiwayat)
                        <td class="py-3 px-3 text-center border-r border-slate-200 whitespace-nowrap font-semibold text-slate-700">
                            {{ $row['departemen'] ?? '-' }}
                        </td>
                        @endif
                        <td class="py-3 px-3 text-center border-r border-slate-200 whitespace-nowrap text-slate-600 font-medium">
                            {{ $row['seksi'] }}
                        </td>
                        <td class="py-3 px-3.5 border-r border-slate-200 whitespace-nowrap text-slate-700 font-medium">
                            {{ $row['nama_bagian'] ?? '-' }}
                        </td>
                        <td class="py-3 px-3.5 border-r border-slate-200 whitespace-nowrap font-medium text-slate-800">
                            {{ $row['jabatan'] }}
                        </td>
                        <td class="py-3 px-2 border-r border-slate-200 text-center font-bold text-slate-800 whitespace-nowrap">
                            {{ $row['kj'] }}
                        </td>
                        <td class="py-3 px-3 border-r border-slate-200 text-center whitespace-nowrap">
                            <span class="inline-block px-2 py-0.5 rounded-none font-bold text-[10px] bg-slate-100 text-slate-700 border border-slate-300">
                                {{ $row['sub_gol'] }}
                            </span>
                        </td>
                        <td class="py-3 px-3 text-center font-mono text-slate-600 border-r border-slate-200 whitespace-nowrap">
                            {{ $row['tgl_penilaian'] }}
                        </td>
                        <td class="py-3 px-3 text-center font-mono font-semibold text-slate-800 border-r border-slate-200 whitespace-nowrap">
                            {{ $row['hasil_kerja'] }}
                        </td>
                        <td class="py-3 px-3 text-center font-mono font-semibold text-slate-800 border-r border-slate-200 whitespace-nowrap">
                            {{ $row['proses_kerja'] }}
                        </td>
                        <td class="py-3 px-3 text-center font-mono font-semibold text-slate-800 border-r border-slate-200 whitespace-nowrap">
                            {{ $row['sikap_kerja'] }}
                        </td>
                        <td class="py-3 px-3 text-center font-mono font-semibold text-slate-800 border-r border-slate-200 whitespace-nowrap">
                            {{ $row['man_management'] }}
                        </td>
                        <td class="py-3 px-3 text-center font-mono font-black text-slate-900 bg-slate-50/70 border-r border-slate-200 whitespace-nowrap">
                            {{ $row['total_nilai'] }}
                        </td>
                        @php
                            $gradeBadgeMap = [
                                'S' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
                                'A' => 'bg-blue-100 text-blue-800 border-blue-300',
                                'B' => 'bg-amber-100 text-amber-800 border-amber-300',
                                'C' => 'bg-orange-100 text-orange-800 border-orange-300',
                                'K' => 'bg-rose-100 text-rose-800 border-rose-300',
                            ];
                        @endphp
                        @if($isDivHeadRiwayat)
                        @php $dGrade = $row['dept_grade'] ?? '-'; @endphp
                        <td class="py-3 px-3.5 text-center border-r border-slate-200 whitespace-nowrap">
                            @if(isset($gradeBadgeMap[$dGrade]))
                                <span class="px-2 py-0.5 rounded-none font-black text-xs border {{ $gradeBadgeMap[$dGrade] }}">{{ $dGrade }}</span>
                            @else
                                <span class="text-slate-300 font-bold">-</span>
                            @endif
                        </td>
                        @php $pkGrade = (empty($row['div_final_ready']) && ($row['status'] ?? '') !== 'selesai') ? '-' : ($row['grade'] ?? '-'); @endphp
                        <td class="py-3 px-3.5 text-center border-r border-slate-200 whitespace-nowrap">
                            @if(isset($gradeBadgeMap[$pkGrade]))
                                <span class="px-2 py-0.5 rounded-none font-black text-xs border {{ $gradeBadgeMap[$pkGrade] }}">{{ $pkGrade }}</span>
                            @else
                                <span class="text-slate-300 font-bold">-</span>
                            @endif
                        </td>
                        @elseif($isDeptHeadRiwayat)
                            @if(!$user->isSecretary())
                            @php $fGrade = $row['foreman_grade'] ?? '-'; @endphp
                            <td class="py-3 px-3.5 text-center border-r border-slate-200 whitespace-nowrap">
                                @if(isset($gradeBadgeMap[$fGrade]))
                                    <span class="px-2 py-0.5 rounded-none font-black text-xs border {{ $gradeBadgeMap[$fGrade] }}">{{ $fGrade }}</span>
                                @else
                                    <span class="text-slate-300 font-bold">-</span>
                                @endif
                            </td>
                            @php $mGrade = $row['mandiri_grade'] ?? '-'; @endphp
                            <td class="py-3 px-3.5 text-center border-r border-slate-200 whitespace-nowrap">
                                @if(isset($gradeBadgeMap[$mGrade]))
                                    <span class="px-2 py-0.5 rounded-none font-black text-xs border {{ $gradeBadgeMap[$mGrade] }}">{{ $mGrade }}</span>
                                @else
                                    <span class="text-slate-300 font-bold">-</span>
                                @endif
                            </td>
                            @php $sGrade = $row['sect_grade'] ?? '-'; @endphp
                            <td class="py-3 px-3.5 text-center border-r border-slate-200 whitespace-nowrap">
                                @if(isset($gradeBadgeMap[$sGrade]))
                                    <span class="px-2 py-0.5 rounded-none font-black text-xs border {{ $gradeBadgeMap[$sGrade] }}">{{ $sGrade }}</span>
                                @else
                                    <span class="text-slate-300 font-bold">-</span>
                                @endif
                            </td>
                            @endif
                            @php $dGrade = $row['dept_grade'] ?? $row['mandiri_grade'] ?? $row['grade'] ?? '-'; @endphp
                            <td class="py-3 px-3.5 text-center border-r border-slate-200 whitespace-nowrap">
                                @if(isset($gradeBadgeMap[$dGrade]))
                                    <span class="px-2 py-0.5 rounded-none font-black text-xs border {{ $gradeBadgeMap[$dGrade] }}">{{ $dGrade }}</span>
                                @else
                                    <span class="text-slate-300 font-bold">-</span>
                                @endif
                            </td>
                        @elseif($isStructuralSh)
                        @php $fGrade = $row['foreman_grade'] ?? '-'; @endphp
                        <td class="py-3 px-3.5 text-center border-r border-slate-200 whitespace-nowrap">
                            @if(isset($gradeBadgeMap[$fGrade]))
                                <span class="px-2 py-0.5 rounded-none font-black text-xs border {{ $gradeBadgeMap[$fGrade] }}">{{ $fGrade }}</span>
                            @else
                                <span class="text-slate-300 font-bold">-</span>
                            @endif
                        </td>
                        @php $mGrade = $row['mandiri_grade'] ?? '-'; @endphp
                        <td class="py-3 px-3.5 text-center border-r border-slate-200 whitespace-nowrap">
                            @if(isset($gradeBadgeMap[$mGrade]))
                                <span class="px-2 py-0.5 rounded-none font-black text-xs border {{ $gradeBadgeMap[$mGrade] }}">{{ $mGrade }}</span>
                            @else
                                <span class="text-slate-300 font-bold">-</span>
                            @endif
                        </td>
                        @php $sGrade = $row['sect_grade'] ?? $row['grade'] ?? '-'; @endphp
                        <td class="py-3 px-3.5 text-center border-r border-slate-200 whitespace-nowrap">
                            @if(isset($gradeBadgeMap[$sGrade]))
                                <span class="px-2 py-0.5 rounded-none font-black text-xs border {{ $gradeBadgeMap[$sGrade] }}">{{ $sGrade }}</span>
                            @else
                                <span class="text-slate-300 font-bold">-</span>
                            @endif
                        </td>
                        @elseif($isForemanRiwayat)
                        @php $fGrade = $row['foreman_grade'] ?? $row['grade'] ?? '-'; @endphp
                        <td class="py-3 px-3.5 text-center border-r border-slate-200 whitespace-nowrap">
                            @if(isset($gradeBadgeMap[$fGrade]))
                                <span class="px-2 py-0.5 rounded-none font-black text-xs border {{ $gradeBadgeMap[$fGrade] }}">{{ $fGrade }}</span>
                            @else
                                <span class="text-slate-300 font-bold">-</span>
                            @endif
                        </td>
                        @elseif($isMandiriUser)
                        @php $mGrade = $row['mandiri_grade'] ?? $row['grade'] ?? '-'; @endphp
                        <td class="py-3 px-3.5 text-center border-r border-slate-200 whitespace-nowrap">
                            @if(isset($gradeBadgeMap[$mGrade]))
                                <span class="px-2 py-0.5 rounded-none font-black text-xs border {{ $gradeBadgeMap[$mGrade] }}">{{ $mGrade }}</span>
                            @else
                                <span class="text-slate-300 font-bold">-</span>
                            @endif
                        </td>
                        @else
                        @php $pkGrade = $row['grade'] ?? '-'; @endphp
                        <td class="py-3 px-3.5 text-center border-r border-slate-200 whitespace-nowrap">
                            @if(isset($gradeBadgeMap[$pkGrade]))
                                <span class="px-2 py-0.5 rounded-none font-black text-xs border {{ $gradeBadgeMap[$pkGrade] }}">{{ $pkGrade }}</span>
                            @else
                                <span class="text-slate-300 font-bold">-</span>
                            @endif
                        </td>
                        @endif
                        <td class="py-3 px-3 text-center border-r border-slate-200 whitespace-nowrap">
                            @if($row['status'] === 'selesai')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-none text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                    ✓ Selesai
                                </span>
                            @elseif($row['status'] === 'menunggu_div_head')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-none text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-300">
                                    Menunggu Div Head
                                </span>
                            @elseif($row['status'] === 'menunggu_section_head')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-none text-[10px] font-bold bg-blue-100 text-blue-800 border border-blue-300">
                                    Di Section Head
                                </span>
                            @elseif($row['status'] === 'menunggu_dept_head')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-none text-[10px] font-bold bg-purple-100 text-purple-800 border border-purple-300">
                                    Di Dept Head
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-none text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-300">
                                    {{ $row['status'] }}
                                </span>
                            @endif
                        </td>
                        <td class="py-3 px-3 text-center whitespace-nowrap">
                            <a href="{{ \App\Http\Controllers\PenilaianController::getFormUrlForUser($user, $row['doc_id']) }}" 
                               class="inline-block px-3 py-1.5 rounded-none font-bold text-xs bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-300 shadow-xs transition-colors">
                                Lihat Form
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ $isDivHeadRiwayat ? 19 : ($isDeptHeadRiwayat && !$user->isSecretary() ? 20 : ($isStructuralSh ? 19 : ($isForemanRiwayat || $isMandiriUser || $user->isSecretary() ? 17 : 18))) }}" class="py-12 text-center text-slate-400">
                            <div class="text-3xl mb-2">📜</div>
                            <p class="font-bold text-slate-600 text-sm">Belum ada karyawan yang selesai dinilai.</p>
                            <p class="text-xs text-slate-400 mt-1">Mulai penilaian bawahan melalui menu <a href="{{ route('penilaian.cari') }}" class="text-[#92302e] font-bold underline">Form Penilaian</a>.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

@if(!$user->isForeman() && !$user->isSectionHead() && !in_array($user->role, ['section_head', 'sechead']) && empty($isDeptOrDivRiwayat))
@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const canvas = document.getElementById('comparisonBarChart');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        const target = @json($targetCounts);
        const actual = @json($quotaActualCounts ?? $actualCounts);

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['S (Sangat Baik)', 'A (Baik)', 'B (Cukup)', 'C (Kurang)', 'K (Kurang Sekali)'],
                datasets: [
                    {
                        label: 'Target Kategori',
                        data: [
                            target['S'] || 0,
                            target['A'] || 0,
                            target['B'] || 0,
                            target['C'] || 0,
                            target['K'] || 0
                        ],
                        backgroundColor: '#1e293b',
                        borderRadius: 0,
                        borderSkipped: false,
                        barPercentage: 0.6,
                        categoryPercentage: 0.7
                    },
                    {
                        label: 'Realisasi Dinilai {{ $evaluatorRoleLabel }}',
                        data: [
                            actual['S'] || 0,
                            actual['A'] || 0,
                            actual['B'] || 0,
                            actual['C'] || 0,
                            actual['K'] || 0
                        ],
                        backgroundColor: '#92302e',
                        borderRadius: 0,
                        borderSkipped: false,
                        barPercentage: 0.6,
                        categoryPercentage: 0.7
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                            font: { family: 'Inter', size: 11 }
                        },
                        grid: { color: '#e2e8f0' }
                    },
                    x: {
                        ticks: {
                            font: { family: 'Inter', size: 11, weight: 'bold' }
                        },
                        grid: { display: false }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            font: { family: 'Inter', size: 12, weight: 'bold' },
                            padding: 16,
                            boxWidth: 12,
                            boxHeight: 12,
                            usePointStyle: false
                        }
                    },
                    tooltip: {
                        padding: 10,
                        cornerRadius: 0,
                        callbacks: {
                            label: function(context) {
                                return ` ${context.dataset.label}: ${context.raw} orang`;
                            }
                        }
                    }
                }
            }
        });
    });
</script>
@endpush
@endif
@endsection
