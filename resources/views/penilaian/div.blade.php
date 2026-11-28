@extends('layouts.app', ['title' => 'Data Penilaian - Division Head'])

@section('content')
<div class="space-y-5" x-data="{ 
    activeTab: 'semua',
    searchQuery: '',
    filterDept: '',
    filterSection: '',
    filterPosition: '',
    matchesFilter(statusRaw, nrp, nama, jabatan, isSelf, departemen = '', seksi = '', isWaitingDiv = false, status = '') {
        // Tab check
        if (this.activeTab === 'siap') {
            if (!isWaitingDiv) return false;
        }
        if (this.activeTab === 'proses') {
            if (isWaitingDiv || statusRaw === 'selesai' || status === 'Disahkan Final') return false;
        }
        if (this.activeTab === 'selesai') {
            if (statusRaw !== 'selesai' && status !== 'Disahkan Final') return false;
        }

        // Department filter
        if (this.filterDept !== '' && departemen.toLowerCase() !== this.filterDept.toLowerCase()) {
            return false;
        }

        // Section filter
        if (this.filterSection !== '' && seksi.toLowerCase() !== this.filterSection.toLowerCase()) {
            return false;
        }

        // Position filter
        if (this.filterPosition !== '' && jabatan.toLowerCase() !== this.filterPosition.toLowerCase()) {
            return false;
        }

        // Search check
        if (this.searchQuery.trim() !== '') {
            const q = this.searchQuery.toLowerCase();
            const nrpMatch = nrp.toLowerCase().includes(q);
            const namaMatch = nama.toLowerCase().includes(q);
            const jabMatch = jabatan.toLowerCase().includes(q);
            const deptMatch = departemen.toLowerCase().includes(q);
            const secMatch = seksi.toLowerCase().includes(q);
            return nrpMatch || namaMatch || jabMatch || deptMatch || secMatch;
        }
        return true;
    }
}">

    <!-- 1. Header Section Card (Merah Musashi Gradient & Rounded-None) -->
    <div class="bg-gradient-to-r from-[#962226] via-[#92302e] to-[#781b1f] text-white rounded-none p-5 sm:p-6 border border-[#781b1f] shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight">
                Data Penilaian
            </h1>
            <p class="text-xs text-red-100 font-medium mt-1">
                Meninjau hasil penilaian karyawan dari Section Head &amp; Department Head, menetapkan pengesahan nilai akhir, dan membubuhkan tanda tangan digital Division Head.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <a href="{{ route('penilaian.cari') }}"
               class="inline-flex items-center gap-2 px-3.5 py-2 rounded-none bg-white hover:bg-slate-100 text-[#92302e] text-xs font-bold transition shadow-sm border border-white">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <span>Buka Form Penilaian</span>
            </a>
        </div>
    </div>

    <!-- 2. Informative Assessment Stat Cards (4 Card Sejajar 1 Baris) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
        <!-- Card 1: Total Karyawan -->
        <div class="bg-white rounded-none p-4 border border-slate-300 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-500 block mb-1">Total Karyawan</span>
                <div class="text-2xl font-black text-slate-900 leading-none">{{ $countAll ?? count($rows) }}</div>
                <span class="text-[11px] text-slate-500 mt-1 block">Anggota {{ ($user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head'])) ? 'Division Head' : ($user->departemen === 'ALL' ? 'Divisi (Multi-Dept)' : $user->departemen) }}</span>
            </div>
            <div class="w-10 h-10 rounded-none bg-[#fbe8e8] text-[#92302e] font-black flex items-center justify-center text-sm border border-[#92302e]/20 shrink-0">
                K
            </div>
        </div>

        <!-- Card 2: Siap Dinilai (To-Do Div Head) -->
        <div class="bg-white rounded-none p-4 border {{ ($countSiap ?? 0) > 0 ? 'border-amber-400 bg-amber-50/40' : 'border-slate-300' }} shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[10px] font-black uppercase tracking-wider {{ ($countSiap ?? 0) > 0 ? 'text-amber-800' : 'text-slate-500' }} block mb-1">Siap Dinilai (To-Do)</span>
                <div class="text-2xl font-black {{ ($countSiap ?? 0) > 0 ? 'text-amber-900' : 'text-slate-900' }} leading-none">{{ $countSiap ?? 0 }}</div>
                <span class="text-[11px] {{ ($countSiap ?? 0) > 0 ? 'text-amber-700 font-bold' : 'text-slate-500' }} mt-1 block">
                    {{ ($countSiap ?? 0) > 0 ? 'Perlu tindakan Anda' : 'Tidak ada antrean' }}
                </span>
            </div>
            <div class="w-10 h-10 rounded-none {{ ($countSiap ?? 0) > 0 ? 'bg-amber-200 text-amber-900 font-black animate-pulse' : 'bg-slate-100 text-slate-600' }} flex items-center justify-center text-sm font-bold border border-amber-300 shrink-0">
                !
            </div>
        </div>

        <!-- Card 3: Proses Section Head -->
        <div class="bg-white rounded-none p-4 border border-slate-300 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-500 block mb-1">Proses Section Head</span>
                <div class="text-2xl font-black text-slate-900 leading-none">{{ $countProses ?? 0 }}</div>
                <span class="text-[11px] text-slate-500 mt-1 block">Pengerjaan Form Sect Head</span>
            </div>
            <div class="w-10 h-10 rounded-none bg-blue-50 text-blue-700 font-bold flex items-center justify-center text-sm border border-blue-200 shrink-0">
                ⏳
            </div>
        </div>

        <!-- Card 4: Disahkan Final (Selesai) -->
        <div class="bg-white rounded-none p-4 border border-slate-300 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-500 block mb-1">Disahkan Final (Selesai)</span>
                <div class="text-2xl font-black text-slate-900 leading-none">{{ $countSelesai ?? 0 }}</div>
                <span class="text-[11px] text-emerald-700 font-bold mt-1 block">Telah diverifikasi Div Head</span>
            </div>
            <div class="w-10 h-10 rounded-none bg-emerald-50 text-emerald-700 font-black flex items-center justify-center text-sm border border-emerald-200 shrink-0">
                ✓
            </div>
        </div>
    </div>

    <!-- 3. Notification Banner (Jika ada dokumen siap disahkan) -->
    @if(($countSiap ?? 0) > 0 && $latestPending)
    <div class="p-3.5 rounded-none bg-amber-50 border-l-4 border-amber-500 border-y border-r border-amber-300 text-amber-900 text-xs font-medium flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-xs">
        <div class="flex items-center gap-2.5">
            <span class="w-6 h-6 rounded-none bg-amber-500 text-white font-black text-xs flex items-center justify-center animate-pulse">!</span>
            <span>
                Ada <strong>{{ $countSiap }} dokumen</strong> penilaian masuk yang menunggu tindakan pengesahan &amp; tanda tangan digital Anda.
            </span>
        </div>
        @if(!empty($latestPending['doc_id']))
        <a href="{{ route('penilaian.show.divhead', $latestPending['doc_id']) }}" 
           class="px-3.5 py-1.5 rounded-none bg-[#92302e] hover:bg-[#7e2524] text-white font-bold text-xs transition shadow-xs whitespace-nowrap self-start sm:self-auto">
            Tinjau Berkas Pertama &rarr;
        </a>
        @endif
    </div>
    @endif

    <!-- 4. Quick NRP Jump & Filter Tabs Bar (Rounded-None) -->
    <div class="bg-white rounded-none p-3.5 sm:p-4 border border-slate-300 shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-3">
        <!-- Search NRP / Nama -->
        <div class="flex-1 max-w-md">
            <div class="relative">
                <input type="text" 
                       x-model="searchQuery"
                       placeholder="Cari nama, NRP, position, atau department..." 
                       class="w-full pl-9 pr-3 py-2 rounded-none border border-slate-300 text-xs font-semibold text-slate-800 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition">
                <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>
        </div>

        <!-- Filter Tabs (Semua | Siap Disahkan | Proses Awal | Selesai) -->
        <div class="flex flex-wrap items-center gap-1.5 self-start lg:self-auto">
            <button type="button" @click="activeTab = 'semua'"
                    :class="activeTab === 'semua' ? 'bg-[#92302e] text-white font-bold' : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-300 font-semibold'"
                    class="px-3 py-1.5 rounded-none text-xs transition-colors shadow-2xs">
                Semua ({{ $countAll ?? count($rows) }})
            </button>
            <button type="button" @click="activeTab = 'siap'"
                    :class="activeTab === 'siap' ? 'bg-[#92302e] text-white font-bold' : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-300 font-semibold'"
                    class="px-3 py-1.5 rounded-none text-xs transition-colors shadow-2xs">
                Siap Disahkan ({{ $countSiap ?? 0 }})
            </button>
            <button type="button" @click="activeTab = 'proses'"
                    :class="activeTab === 'proses' ? 'bg-[#92302e] text-white font-bold' : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-300 font-semibold'"
                    class="px-3 py-1.5 rounded-none text-xs transition-colors shadow-2xs">
                Proses Awal ({{ $countProses ?? 0 }})
            </button>
            <button type="button" @click="activeTab = 'selesai'"
                    :class="activeTab === 'selesai' ? 'bg-[#92302e] text-white font-bold' : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-300 font-semibold'"
                    class="px-3 py-1.5 rounded-none text-xs transition-colors shadow-2xs">
                Disahkan Final ({{ $countSelesai ?? 0 }})
            </button>
        </div>
    </div>

    <!-- 5. Form Container for Batch Action & Table -->
    @php
        $divDeptOptions = collect($rows)->pluck('departemen')->map(fn ($v) => trim((string)$v))->filter(fn ($v) => $v !== '' && $v !== '-')->unique()->sort()->values()->all();
        $divSectionOptions = collect($rows)->pluck('seksi')->map(fn ($v) => trim((string)$v))->filter(fn ($v) => $v !== '' && $v !== '-')->unique()->sort()->values()->all();
        $divPositionOptions = collect($rows)->pluck('jabatan')->map(fn ($v) => trim((string)$v))->filter(fn ($v) => $v !== '' && $v !== '-')->unique()->sort()->values()->all();
    @endphp
    <form action="{{ route('penilaian.div.saveDraft') }}" method="POST" class="bg-white rounded-none border border-slate-300 shadow-sm overflow-hidden">
        @csrf

        <!-- Grey Header & Filter Bar directly above the Table -->
        <div class="px-4 py-3 bg-slate-50 border-b border-slate-300 flex flex-col sm:flex-row sm:items-center justify-between gap-3 overflow-x-auto">
            <div class="flex items-center gap-2 shrink-0">
                <h3 class="text-xs font-black text-slate-800 uppercase tracking-wider">Daftar Penilaian Karyawan</h3>
                <span class="px-2 py-0.5 text-[10px] font-bold bg-slate-200 text-slate-800 rounded-none font-mono">{{ $countAll ?? count($rows) }} Karyawan</span>
            </div>
            <div class="flex flex-nowrap items-center gap-2.5 shrink-0">
                <div class="flex items-center gap-1.5 shrink-0">
                    <label for="divFilterDept" class="text-[10px] font-bold text-slate-700 uppercase tracking-wider whitespace-nowrap">DEPARTMENT</label>
                    <select id="divFilterDept" x-model="filterDept"
                            class="px-2.5 py-1.5 text-xs font-semibold bg-white border border-slate-300 hover:border-slate-400 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] text-slate-800 rounded-none cursor-pointer">
                        <option value="">Semua Department</option>
                        @foreach($divDeptOptions as $optDept)
                            <option value="{{ $optDept }}">{{ $optDept }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center gap-1.5 shrink-0">
                    <label for="divFilterSection" class="text-[10px] font-bold text-slate-700 uppercase tracking-wider whitespace-nowrap">SECTION</label>
                    <select id="divFilterSection" x-model="filterSection"
                            class="px-2.5 py-1.5 text-xs font-semibold bg-white border border-slate-300 hover:border-slate-400 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] text-slate-800 rounded-none cursor-pointer">
                        <option value="">Semua Section</option>
                        @foreach($divSectionOptions as $optSec)
                            <option value="{{ $optSec }}">{{ $optSec }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center gap-1.5 shrink-0">
                    <label for="divFilterPosition" class="text-[10px] font-bold text-slate-700 uppercase tracking-wider whitespace-nowrap">POSITION</label>
                    <select id="divFilterPosition" x-model="filterPosition"
                            class="px-2.5 py-1.5 text-xs font-semibold bg-white border border-slate-300 hover:border-slate-400 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] text-slate-800 rounded-none cursor-pointer max-w-[200px]">
                        <option value="">Semua Position</option>
                        @foreach($divPositionOptions as $optPos)
                            <option value="{{ $optPos }}">{{ $optPos }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="button"
                        x-show="filterDept !== '' || filterSection !== '' || filterPosition !== ''"
                        @click="filterDept = ''; filterSection = ''; filterPosition = ''"
                        class="px-2.5 py-1.5 text-xs font-bold text-[#92302e] bg-rose-50 hover:bg-rose-100 border border-rose-300 rounded-none cursor-pointer transition shadow-2xs shrink-0 flex items-center gap-1 whitespace-nowrap">
                    <span>✕</span> Reset
                </button>
            </div>
        </div>

        <div class="p-4 space-y-4">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-separate border-spacing-0 whitespace-nowrap">
                <thead>
                    <tr class="bg-slate-100 text-slate-700 font-bold text-[11px] uppercase tracking-wider">
                        <th class="py-3 px-3 border-t border-b border-l border-r border-slate-300 text-center w-12">NO</th>
                        <th class="py-3 px-3 border-t border-b border-r border-slate-300 text-center w-24">NRP</th>
                        <th class="py-3 px-4 border-t border-b border-r border-slate-300">NAMA LENGKAP</th>
                        <th class="py-3 px-3 border-t border-b border-r border-slate-300">POSITION</th>
                        <th class="py-3 px-3 border-t border-b border-r border-slate-300 text-center">KJ</th>
                        <th class="py-3 px-3 border-t border-b border-r border-slate-300 text-center">GOLONGAN</th>
                        <th class="py-3 px-3 border-t border-b border-r border-slate-300 text-center">FORM</th>
                        <th class="py-3 px-3 border-t border-b border-r border-slate-300 text-center">NILAI MANDIRI</th>
                        <th class="py-3 px-3 border-t border-b border-r border-slate-300">NILAI SECTION HEAD</th>
                        <th class="py-3 px-3 border-t border-b border-r border-slate-300">NILAI DEPARTMENT HEAD</th>
                        <th class="py-3 px-3 border-t border-b border-r border-slate-300 text-center w-28">NILAI DIVISION HEAD</th>
                        <th class="py-3 px-3 border-t border-b border-r border-slate-300 text-center w-28">STATUS ALUR</th>
                        <th class="py-3 px-3 border-t border-b border-r border-slate-300 text-center w-24">AKSI</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($rows as $idx => $row)
                    <tr class="hover:bg-slate-50 transition-colors"
                        x-show="matchesFilter('{{ $row['status_raw'] }}', '{{ $row['nrp'] }}', '{{ addslashes($row['nama_lengkap']) }}', '{{ addslashes($row['jabatan']) }}', {{ $row['is_self'] ? 'true' : 'false' }}, '{{ addslashes($row['departemen'] ?? '') }}', '{{ addslashes($row['seksi'] ?? '') }}', {{ !empty($row['is_waiting_div']) ? 'true' : 'false' }}, '{{ addslashes($row['status']) }}')">
                        
                        <!-- 1. NO -->
                        <td class="py-2.5 px-3 border-b border-l border-r border-slate-200 text-center font-bold text-slate-700">
                            {{ $idx + 1 }}
                        </td>

                        <!-- 2. NRP -->
                        <td class="py-2.5 px-3 border-b border-r border-slate-200 text-center font-mono font-bold text-slate-800">
                            @if(!empty($row['doc_id']))
                            <a href="{{ route('penilaian.show.divhead', $row['doc_id']) }}" 
                               class="hover:text-[#92302e] hover:underline transition-colors"
                               title="Buka form penilaian lengkap">
                                {{ $row['nrp'] }}
                            </a>
                            @else
                            <a href="{{ route('penilaian.byNrp', $row['nrp']) }}" 
                               class="hover:text-[#92302e] hover:underline transition-colors"
                               title="Inisialisasi &amp; buka form penilaian">
                                {{ $row['nrp'] }}
                            </a>
                            @endif
                        </td>

                        <!-- 3. Nama Lengkap & Division -->
                        <td class="py-2.5 px-4 border-b border-r border-slate-200">
                            <div class="flex items-center gap-1.5">
                                <span class="font-bold text-slate-900 text-xs">{{ $row['nama_lengkap'] }}</span>
                                @if($row['is_self'])
                                <span class="px-1.5 py-0.2 rounded-none bg-rose-100 text-rose-800 border border-rose-300 font-black text-[9.5px]">ANDA</span>
                                @endif
                            </div>
                            @php
                                $subDivDisplay = !empty($row['division']) && $row['division'] !== '-' ? $row['division'] : ($row['departemen'] ?? '-');
                            @endphp
                            @if(!empty($subDivDisplay) && $subDivDisplay !== '-')
                            <div class="text-[10px] text-slate-400 font-medium mt-0.5">
                                {{ $subDivDisplay }}
                            </div>
                            @endif
                        </td>

                        <!-- 4. Position -->
                        <td class="py-2.5 px-3 border-b border-r border-slate-200 text-slate-800 font-semibold text-xs">
                            {{ $row['jabatan'] }}
                        </td>

                        <!-- 5. KJ -->
                        <td class="py-2.5 px-3 border-b border-r border-slate-200 text-center font-mono font-bold text-slate-800">
                            {{ $row['kj'] ?? '-' }}
                        </td>

                        <!-- 6. Golongan -->
                        <td class="py-2.5 px-3 border-b border-r border-slate-200 text-center font-semibold text-slate-700">
                            {{ $row['golongan'] ?? '-' }}
                        </td>

                        <!-- 7. Kode Form -->
                        <td class="py-2.5 px-3 border-b border-r border-slate-200 text-center {{ !empty($row['is_secretary']) ? 'font-bold text-slate-800' : 'font-mono font-bold text-indigo-700' }}">
                            @if(!empty($row['is_secretary']))
                                Form Secretary
                            @else
                                {{ str_starts_with($row['kode_form'] ?? '', '07-006') ? '07-006' : ($row['kode_form'] ?? '07-001') }}
                            @endif
                        </td>

                        <!-- 8. NILAI MANDIRI -->
                        @php
                            $showMandiri = !empty($row['has_mandiri_col']);
                        @endphp
                        <td class="py-2.5 px-3 border-b border-r border-slate-200 text-center">
                            @if(!$showMandiri)
                                <span class="text-slate-400 font-semibold">-</span>
                            @elseif(!empty($row['is_self']))
                                @if(($row['nilai_mandiri'] ?? '-') !== '-')
                                    <div class="inline-flex flex-col items-center">
                                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-none font-black text-xs shadow-xs
                                            {{ $row['nilai_mandiri'] === 'S' ? 'bg-emerald-100 text-emerald-800' :
                                               ($row['nilai_mandiri'] === 'A' ? 'bg-blue-100 text-blue-800' :
                                               ($row['nilai_mandiri'] === 'B' ? 'bg-amber-100 text-amber-800' :
                                               ($row['nilai_mandiri'] === 'C' ? 'bg-orange-100 text-orange-800' : 'bg-rose-100 text-rose-800'))) }}">
                                            {{ $row['nilai_mandiri'] }}
                                        </span>
                                        <span class="text-[9px] text-emerald-700 font-bold mt-0.5">Mandiri</span>
                                    </div>
                                @else
                                    <span class="text-amber-700 font-medium text-[11px] italic">Belum Dinilai</span>
                                @endif
                            @else
                                <div class="flex items-center gap-2 text-left">
                                    @if(($row['nilai_mandiri'] ?? '-') !== '-')
                                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-none font-black text-xs shadow-xs
                                            {{ $row['nilai_mandiri'] === 'S' ? 'bg-emerald-100 text-emerald-800' :
                                               ($row['nilai_mandiri'] === 'A' ? 'bg-blue-100 text-blue-800' :
                                               ($row['nilai_mandiri'] === 'B' ? 'bg-amber-100 text-amber-800' :
                                               ($row['nilai_mandiri'] === 'C' ? 'bg-orange-100 text-orange-800' : 'bg-rose-100 text-rose-800'))) }}">
                                            {{ $row['nilai_mandiri'] }}
                                        </span>
                                    @else
                                        <span class="text-slate-400 font-semibold text-center w-7">-</span>
                                    @endif
                                    <div>
                                        <span class="text-[11px] font-bold text-slate-800 block truncate max-w-[130px]">{{ $row['mandiri_nama'] ?? 'Mandiri' }}</span>
                                        @if(!empty($row['has_mandiri_sign']))
                                            <span class="text-[9.5px] font-semibold text-emerald-700 block">✓ TTD Lengkap</span>
                                        @else
                                            <span class="text-[9.5px] text-slate-400 italic block">Belum TTD</span>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </td>

                        <!-- 9. NILAI SECTION HEAD -->
                        @php
                            $showSection = !empty($row['has_section_col']);
                        @endphp
                        <td class="py-2.5 px-3 border-b border-r border-slate-200 {{ !$showSection ? 'text-center' : '' }}">
                            @if(!$showSection)
                                <span class="text-slate-400 font-semibold">-</span>
                            @else
                            <div class="flex items-center gap-2">
                                @if(($row['nilai_section'] ?? '-') !== '-')
                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-none font-black text-xs shadow-xs
                                        {{ $row['nilai_section'] === 'S' ? 'bg-emerald-100 text-emerald-800' :
                                           ($row['nilai_section'] === 'A' ? 'bg-blue-100 text-blue-800' :
                                           ($row['nilai_section'] === 'B' ? 'bg-amber-100 text-amber-800' :
                                           ($row['nilai_section'] === 'C' ? 'bg-orange-100 text-orange-800' : 'bg-rose-100 text-rose-800'))) }}">
                                        {{ $row['nilai_section'] }}
                                    </span>
                                @else
                                    <span class="text-slate-400 font-semibold text-center w-7">-</span>
                                @endif
                                <div>
                                    <span class="text-[11px] font-bold text-slate-800 block truncate max-w-[130px]">{{ $row['section_nama'] }}</span>
                                    @if(!empty($row['has_section_sign']))
                                        <span class="text-[9.5px] font-semibold text-emerald-700 block">✓ TTD Lengkap</span>
                                    @else
                                        <span class="text-[9.5px] text-slate-400 italic block">Belum TTD</span>
                                    @endif
                                </div>
                            </div>
                            @endif
                        </td>

                        <!-- 10. NILAI DEPARTMENT HEAD -->
                        @php
                            $showDept = !empty($row['has_dept_col']);
                        @endphp
                        <td class="py-2.5 px-3 border-b border-r border-slate-200 {{ !$showDept ? 'text-center' : '' }}">
                            @if(!$showDept)
                                <span class="text-slate-400 font-semibold">-</span>
                            @else
                            <div class="flex items-center gap-2">
                                @if(($row['nilai_dept'] ?? '-') !== '-')
                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-none font-black text-xs shadow-xs
                                        {{ $row['nilai_dept'] === 'S' ? 'bg-emerald-100 text-emerald-800' :
                                           ($row['nilai_dept'] === 'A' ? 'bg-blue-100 text-blue-800' :
                                           ($row['nilai_dept'] === 'B' ? 'bg-amber-100 text-amber-800' :
                                           ($row['nilai_dept'] === 'C' ? 'bg-orange-100 text-orange-800' : 'bg-rose-100 text-rose-800'))) }}">
                                        {{ $row['nilai_dept'] }}
                                    </span>
                                @else
                                    <span class="text-slate-400 font-semibold text-center w-7">-</span>
                                @endif
                                <div>
                                    <span class="text-[11px] font-bold text-slate-800 block truncate max-w-[130px]">{{ $row['dept_nama'] }}</span>
                                    @if(!empty($row['has_dept_sign']))
                                        <span class="text-[9.5px] font-semibold text-emerald-700 block">✓ TTD Lengkap</span>
                                    @else
                                        <span class="text-[9.5px] text-slate-400 italic block">Belum TTD</span>
                                    @endif
                                </div>
                            </div>
                            @endif
                        </td>

                        <!-- 11. NILAI DIVISION HEAD -->
                        <td class="py-2 px-3 border-b border-r border-slate-200 text-center">
                            @if(!empty($row['is_self']))
                                @if(!empty($row['nilai_div']))
                                    <div class="inline-flex flex-col items-center">
                                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-none font-black text-xs shadow-xs
                                            {{ $row['nilai_div'] === 'S' ? 'bg-emerald-100 text-emerald-800' :
                                               ($row['nilai_div'] === 'A' ? 'bg-blue-100 text-blue-800' :
                                               ($row['nilai_div'] === 'B' ? 'bg-amber-100 text-amber-800' :
                                               ($row['nilai_div'] === 'C' ? 'bg-orange-100 text-orange-800' : 'bg-rose-100 text-rose-800'))) }}">
                                            {{ $row['nilai_div'] }}
                                        </span>
                                        <span class="text-[9px] text-emerald-700 font-bold mt-0.5">Div Head</span>
                                    </div>
                                @else
                                    <span class="text-amber-700 font-medium text-[11px] italic">Belum Dinilai</span>
                                @endif
                            @elseif(!empty($row['doc_id']))
                                @if($row['status_raw'] === 'selesai' || !empty($row['has_div_sign']))
                                    @if(!empty($row['nilai_div']))
                                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-none font-black text-xs shadow-xs
                                            {{ $row['nilai_div'] === 'S' ? 'bg-emerald-100 text-emerald-800' :
                                               ($row['nilai_div'] === 'A' ? 'bg-blue-100 text-blue-800' :
                                               ($row['nilai_div'] === 'B' ? 'bg-amber-100 text-amber-800' :
                                               ($row['nilai_div'] === 'C' ? 'bg-orange-100 text-orange-800' : 'bg-rose-100 text-rose-800'))) }}">
                                            {{ $row['nilai_div'] }}
                                        </span>
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                @else
                                    <span class="{{ !empty($row['can_evaluate_div']) ? 'text-amber-700 font-medium text-[11px] italic' : 'text-slate-400 font-semibold' }}">
                                        {{ !empty($row['can_evaluate_div']) ? 'Belum Dinilai' : '-' }}
                                    </span>
                                @endif
                            @else
                                <span class="text-slate-400">-</span>
                            @endif
                        </td>

                        <!-- 12. Status Alur -->
                        <td class="py-2.5 px-3 border-b border-r border-slate-200 text-center">
                            @if($row['status_raw'] === 'selesai' || $row['has_div_sign'] || $row['status'] === 'Disahkan Final')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-none text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                    ✓ Disahkan Final
                                </span>
                            @elseif($row['status'] === 'Proses Penilaian Mandiri')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-none text-[10px] font-bold bg-purple-100 text-purple-800 border border-purple-300">
                                    Proses Penilaian Mandiri
                                </span>
                            @elseif($row['is_waiting_div'] || $row['status'] === 'Menunggu Pengesahan Anda' || $row['status'] === 'Menunggu Penilaian Anda')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-none text-[10px] font-bold bg-amber-100 text-amber-900 border border-amber-300 animate-pulse" title="Perlu tindakan Anda">
                                    🔔 {{ $row['is_self'] ? 'Siap Dinilai' : 'Siap Disahkan' }}
                                </span>
                            @elseif($row['status_raw'] === 'menunggu_dept_head' || $row['status'] === 'Proses Dept Head')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-none text-[10px] font-bold bg-blue-100 text-blue-800 border border-blue-300">
                                    Proses Dept Head
                                </span>
                            @elseif($row['status_raw'] === 'menunggu_section_head' || $row['status'] === 'Proses Section Head')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-none text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-300">
                                    Proses Sect Head
                                </span>
                            @elseif($row['status_raw'] === 'menunggu_foreman' || $row['status'] === 'Proses Foreman')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-none text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-300">
                                    Proses Foreman
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-none text-[10px] font-medium bg-slate-100 text-slate-600 border border-slate-200">
                                    {{ $row['status'] ?: 'Draft' }}
                                </span>
                            @endif
                        </td>

                        <!-- 13. Aksi (Tombol Buka Form Penilaian) -->
                        <td class="py-2.5 px-3 border-b border-r border-slate-200 text-center">
                            @if(!empty($row['doc_id']))
                                @if(!empty($row['can_evaluate_div']) || !empty($row['is_waiting_div']))
                                <a href="{{ route('penilaian.show.divhead', $row['doc_id']) }}" 
                                   class="inline-flex items-center gap-1 px-3 py-1 rounded-none bg-[#92302e] hover:bg-[#7e2524] text-white text-xs font-bold transition shadow-xs">
                                    <span>Nilai</span>
                                    <span>&rarr;</span>
                                </a>
                                @else
                                <a href="{{ route('penilaian.show.divhead', $row['doc_id']) }}" 
                                   class="inline-flex items-center gap-1 px-2.5 py-1 rounded-none bg-slate-100 text-slate-600 border border-slate-300 hover:bg-slate-200 text-xs font-semibold transition shadow-xs">
                                    <span>Lihat</span>
                                </a>
                                @endif
                            @else
                                <a href="{{ route('penilaian.byNrp', $row['nrp']) }}" 
                                   class="inline-flex items-center gap-1 px-3 py-1 rounded-none bg-slate-700 hover:bg-slate-800 text-white text-xs font-bold transition shadow-xs">
                                    <span>Buka</span>
                                </a>
                            @endif
                        </td>

                    </tr>
                    @empty
                    <tr>
                        <td colspan="13" class="py-8 text-center text-slate-400 italic">
                            Tidak ada data penilaian untuk ditampilkan di divisi ini.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Footer Info -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between pt-3 border-t border-slate-200 gap-3">
            <span class="text-xs text-slate-500 font-medium">
                Total <strong>{{ count($rows) }}</strong> karyawan terdaftar di {{ ($user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head'])) ? 'Division Head' : ('divisi ' . ($user->departemen ?? '')) }}
            </span>
        </div>
        </div>
    </form>

</div>
@endsection

