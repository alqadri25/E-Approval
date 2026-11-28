@extends('layouts.app', ['title' => 'Data Penilaian - Section Head'])

@section('content')
<div class="space-y-5" x-data="{ 
    activeTab: 'semua',
    searchQuery: '',
    filterPosition: '',
    matchesFilter(statusRaw, nrp, nama, jabatan, isSelf = false) {
        // Tab check
        if (this.activeTab === 'siap') {
            if (statusRaw !== 'menunggu_section_head') return false;
        }
        if (this.activeTab === 'proses' && !['menunggu_foreman', 'draft', 'belum_ada'].includes(statusRaw)) return false;
        if (this.activeTab === 'selesai' && !['menunggu_dept_head', 'menunggu_div_head', 'selesai'].includes(statusRaw)) return false;

        // Position check
        if (this.filterPosition !== '' && jabatan.toLowerCase() !== this.filterPosition.toLowerCase()) {
            return false;
        }

        // Search check
        if (this.searchQuery.trim() !== '') {
            const q = this.searchQuery.toLowerCase();
            const nrpMatch = nrp.toLowerCase().includes(q);
            const namaMatch = nama.toLowerCase().includes(q);
            const jabMatch = jabatan.toLowerCase().includes(q);
            const selfMatch = isSelf && ('anda'.includes(q) || q === 'anda');
            return nrpMatch || namaMatch || jabMatch || selfMatch;
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
                @if($user->hasForemanSubordinates())
                    Meninjau hasil penilaian langsung dari Foreman, menetapkan nilai akhir, dan menandatangani digital untuk diteruskan ke Department Head
                @else
                    Meninjau hasil penilaian langsung dari Foreman, menetapkan nilai akhir, dan menandatangani digital untuk diteruskan ke Department Head
                @endif
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

    <!-- 2. Informative Assessment Stat Cards (4 Columns) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
        <!-- Card 1: Total Anggota -->
        <div class="bg-white rounded-none p-4 border border-slate-300 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-500 block mb-1">Total Karyawan</span>
                <div class="text-2xl font-black text-slate-900 leading-none">{{ $countAll ?? count($rows) }}</div>
                <span class="text-[11px] text-slate-500 mt-1 block">Anggota Section Head</span>
            </div>
            <div class="w-10 h-10 rounded-none bg-[#fbe8e8] text-[#92302e] font-black flex items-center justify-center text-sm border border-[#92302e]/20">
                K
            </div>
        </div>

        <!-- Card 2: Siap Dinilai (To-Do) -->
        <div class="bg-white rounded-none p-4 border {{ ($countSiap ?? 0) > 0 ? 'border-amber-400 bg-amber-50/40' : 'border-slate-300' }} shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[10px] font-black uppercase tracking-wider {{ ($countSiap ?? 0) > 0 ? 'text-amber-800' : 'text-slate-500' }} block mb-1">Siap Dinilai (To-Do)</span>
                <div class="text-2xl font-black {{ ($countSiap ?? 0) > 0 ? 'text-amber-900' : 'text-slate-900' }} leading-none">{{ $countSiap ?? 0 }}</div>
                <span class="text-[11px] {{ ($countSiap ?? 0) > 0 ? 'text-amber-700 font-bold' : 'text-slate-500' }} mt-1 block">
                    {{ ($countSiap ?? 0) > 0 ? 'Perlu tindakan Anda' : 'Tidak ada antrean' }}
                </span>
            </div>
            <div class="w-10 h-10 rounded-none {{ ($countSiap ?? 0) > 0 ? 'bg-amber-200 text-amber-900 font-black animate-pulse' : 'bg-slate-100 text-slate-600' }} flex items-center justify-center text-sm font-bold border border-amber-300">
                !
            </div>
        </div>

        <!-- Card 3: Proses Foreman -->
        <div class="bg-white rounded-none p-4 border border-slate-300 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-500 block mb-1">
                    Proses Foreman
                </span>
                <div class="text-2xl font-black text-slate-900 leading-none">{{ $countProses ?? 0 }}</div>
                <span class="text-[11px] text-slate-500 mt-1 block">
                    Pengerjaan Form Foreman
                </span>
            </div>
            <div class="w-10 h-10 rounded-none bg-blue-50 text-blue-700 font-bold flex items-center justify-center text-sm border border-blue-200">
                ⏳
            </div>
        </div>

        <!-- Card 4: Diteruskan / Selesai -->
        <div class="bg-white rounded-none p-4 border border-slate-300 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-500 block mb-1">Diteruskan / Selesai</span>
                <div class="text-2xl font-black text-slate-900 leading-none">{{ $countSelesai ?? 0 }}</div>
                <span class="text-[11px] text-emerald-700 font-bold mt-1 block">Telah diverifikasi Section Head</span>
            </div>
            <div class="w-10 h-10 rounded-none bg-emerald-50 text-emerald-700 font-black flex items-center justify-center text-sm border border-emerald-200">
                ✓
            </div>
        </div>
    </div>

    <!-- 3. Notification Banner (Jika ada dokumen siap dinilai dari Foreman) -->
    @if(($countSiap ?? 0) > 0 && $latestPending)
    <div class="p-3.5 rounded-none bg-amber-50 border-l-4 border-amber-500 border-y border-r border-amber-300 text-amber-900 text-xs font-medium flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-xs">
        <div class="flex items-center gap-2.5">
            <span class="w-6 h-6 rounded-none bg-amber-500 text-white font-black text-xs flex items-center justify-center animate-pulse">!</span>
            <span>
                Ada <strong>{{ $countSiap }} dokumen</strong> penilaian masuk dari Foreman yang menunggu review &amp; tanda tangan digital Anda.
            </span>
        </div>
        @if(!empty($latestPending['doc_id']))
        <a href="{{ route('penilaian.show.sechead', $latestPending['doc_id']) }}" 
           class="px-3.5 py-1.5 rounded-none bg-[#92302e] hover:bg-[#7e2524] text-white font-bold text-xs transition shadow-xs whitespace-nowrap self-start sm:self-auto">
            Tinjau Berkas Pertama &rarr;
        </a>
        @endif
    </div>
    @endif

    <!-- 4. Quick Search & Filter Tabs Bar (Rounded-None) -->
    <div class="bg-white rounded-none p-3.5 sm:p-4 border border-slate-300 shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-3">
        <!-- Search NRP / Nama -->
        <div class="flex-1 max-w-md">
            <div class="relative">
                <input type="text" 
                       x-model="searchQuery"
                       placeholder="Cari nama, NRP, atau position karyawan..." 
                       class="w-full pl-9 pr-3 py-2 rounded-none border border-slate-300 text-xs font-semibold text-slate-800 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition">
                <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>
        </div>

        <!-- Filter Tabs (Semua | Siap Dinilai | Proses Awal | Selesai) -->
        <div class="flex flex-wrap items-center gap-1.5 self-start lg:self-auto">
            <button type="button" @click="activeTab = 'semua'"
                    :class="activeTab === 'semua' ? 'bg-[#92302e] text-white font-bold' : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-300 font-semibold'"
                    class="px-3 py-1.5 rounded-none text-xs transition-colors shadow-2xs">
                Semua ({{ $countAll ?? count($rows) }})
            </button>
            <button type="button" @click="activeTab = 'siap'"
                    :class="activeTab === 'siap' ? 'bg-[#92302e] text-white font-bold' : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-300 font-semibold'"
                    class="px-3 py-1.5 rounded-none text-xs transition-colors shadow-2xs">
                Siap Dinilai ({{ $countSiap ?? 0 }})
            </button>
            <button type="button" @click="activeTab = 'proses'"
                    :class="activeTab === 'proses' ? 'bg-[#92302e] text-white font-bold' : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-300 font-semibold'"
                    class="px-3 py-1.5 rounded-none text-xs transition-colors shadow-2xs">
                Proses Awal ({{ $countProses ?? 0 }})
            </button>
            <button type="button" @click="activeTab = 'selesai'"
                    :class="activeTab === 'selesai' ? 'bg-[#92302e] text-white font-bold' : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-300 font-semibold'"
                    class="px-3 py-1.5 rounded-none text-xs transition-colors shadow-2xs">
                Selesai ({{ $countSelesai ?? 0 }})
            </button>
        </div>
    </div>

    <!-- 5. Form Container for Batch Action & Table -->
    @php
        $secPositionOptions = collect($rows)->pluck('jabatan')->map(fn ($v) => trim((string)$v))->filter(fn ($v) => $v !== '' && $v !== '-')->unique()->sort()->values()->all();
    @endphp
    <form action="{{ route('penilaian.section.saveDraft') }}" method="POST" class="bg-white rounded-none border border-slate-300 shadow-sm overflow-hidden">
        @csrf

        <!-- Grey Header & Filter Bar directly above the Table -->
        <div class="px-4 py-3 bg-slate-50 border-b border-slate-300 flex flex-col sm:flex-row sm:items-center justify-between gap-3 overflow-x-auto">
            <div class="flex items-center gap-2 shrink-0">
                <h3 class="text-xs font-black text-slate-800 uppercase tracking-wider">Daftar Penilaian Karyawan</h3>
                <span class="px-2 py-0.5 text-[10px] font-bold bg-slate-200 text-slate-800 rounded-none font-mono">{{ $countAll ?? count($rows) }} Karyawan</span>
            </div>
            <div class="flex flex-nowrap items-center gap-2.5 shrink-0">
                <div class="flex items-center gap-1.5 shrink-0">
                    <label for="sectionFilterPosition" class="text-[10px] font-bold text-slate-700 uppercase tracking-wider whitespace-nowrap">POSITION</label>
                    <select id="sectionFilterPosition" x-model="filterPosition"
                            class="px-2.5 py-1.5 text-xs font-semibold bg-white border border-slate-300 hover:border-slate-400 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] text-slate-800 rounded-none cursor-pointer max-w-[200px]">
                        <option value="">Position</option>
                        @foreach($secPositionOptions as $optPos)
                            <option value="{{ $optPos }}">{{ $optPos }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="button"
                        x-show="filterPosition !== ''"
                        @click="filterPosition = ''"
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
                        <th class="py-3 px-3 border-t border-b border-r border-slate-300 text-center w-20">FORM</th>
                        <th class="py-3 px-3 border-t border-b border-r border-slate-300">HASIL FOREMAN</th>
                        <th class="py-3 px-3 border-t border-b border-r border-slate-300 text-center w-28">NILAI MANDIRI</th>
                        <th class="py-3 px-3 border-t border-b border-r border-slate-300 text-center w-28">NILAI SECTION HEAD</th>
                        <th class="py-3 px-3 border-t border-b border-r border-slate-300 text-center w-28">STATUS ALUR</th>
                        <th class="py-3 px-3 border-t border-b border-r border-slate-300 text-center w-24">AKSI</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($rows as $idx => $row)
                    <tr class="hover:bg-slate-50 transition-colors"
                        x-show="matchesFilter('{{ $row['status_raw'] }}', '{{ $row['nrp'] }}', '{{ addslashes($row['nama_lengkap']) }}', '{{ addslashes($row['jabatan']) }}', {{ !empty($row['is_self']) ? 'true' : 'false' }})">
                        
                        <!-- 1. NO -->
                        <td class="py-2.5 px-3 border-b border-l border-r border-slate-200 text-center font-bold text-slate-700">
                            {{ $idx + 1 }}
                        </td>

                        <!-- 2. NRP -->
                        <td class="py-2.5 px-3 border-b border-r border-slate-200 text-center font-mono font-bold text-slate-800">
                            @if(!empty($row['doc_id']))
                            <a href="{{ route('penilaian.show.sechead', $row['doc_id']) }}" 
                               class="hover:text-[#92302e] hover:underline transition-colors"
                               title="Buka form penilaian lengkap">
                                {{ $row['nrp'] }}
                            </a>
                            @else
                            <a href="{{ route('penilaian.byNrp', $row['nrp']) }}" 
                               class="hover:text-[#92302e] hover:underline transition-colors"
                               title="Inisialisasi & buka form penilaian">
                                {{ $row['nrp'] }}
                            </a>
                            @endif
                        </td>

                        <!-- 3. Nama Lengkap -->
                        <td class="py-2.5 px-4 border-b border-r border-slate-200">
                            <div class="flex items-center gap-1.5">
                                <span class="font-bold text-slate-900 text-xs">{{ $row['nama_lengkap'] }}</span>
                                @if(!empty($row['is_self']))
                                <span class="px-1.5 py-0.2 rounded-none bg-rose-100 text-rose-800 border border-rose-300 font-black text-[9.5px]">ANDA</span>
                                @endif
                            </div>
                            <div class="text-[10px] text-slate-400 font-medium mt-0.5">
                                {{ $row['seksi'] }}
                            </div>
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
                        <td class="py-2.5 px-3 border-b border-r border-slate-200 text-center font-mono font-bold text-indigo-700">
                            {{ $row['kode_form'] ?? '07-001' }}
                        </td>

                        <!-- 8. Hasil Foreman -->
                        <td class="py-2.5 px-3 border-b border-r border-slate-200">
                            @if(!empty($row['is_self']) || empty($row['foreman_nrp']))
                                <span class="text-slate-400 font-medium italic text-[11px]">-</span>
                            @else
                            <div class="flex items-center gap-2">
                                @if($row['nilai_foreman'] !== '-')
                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-none font-black text-xs shadow-xs
                                        {{ $row['nilai_foreman'] === 'S' ? 'bg-emerald-100 text-emerald-800' :
                                           ($row['nilai_foreman'] === 'A' ? 'bg-blue-100 text-blue-800' :
                                           ($row['nilai_foreman'] === 'B' ? 'bg-amber-100 text-amber-800' :
                                           ($row['nilai_foreman'] === 'C' ? 'bg-orange-100 text-orange-800' : 'bg-rose-100 text-rose-800'))) }}">
                                        {{ $row['nilai_foreman'] }}
                                    </span>
                                @else
                                    <span class="text-slate-400 font-semibold text-center w-7">-</span>
                                @endif
                                <div>
                                    <span class="text-[11px] font-bold text-slate-800 block truncate max-w-[120px]">{{ $row['foreman_nama'] }}</span>
                                    @if($row['has_foreman_sign'])
                                        <span class="text-[9.5px] font-semibold text-emerald-700 block">✓ TTD Lengkap</span>
                                    @else
                                        <span class="text-[9.5px] text-slate-400 italic block">Belum TTD</span>
                                    @endif
                                </div>
                            </div>
                            @endif
                        </td>

                        <!-- 9. Nilai Mandiri (Hanya ada untuk Section Head sendiri, nilainya sama dengan Nilai Section Head) -->
                        <td class="py-2 px-3 border-b border-r border-slate-200 text-center">
                            @if(!empty($row['is_self']))
                                @if(!empty($row['nilai_section']) && $row['nilai_section'] !== '-')
                                    <div class="inline-flex flex-col items-center">
                                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-none font-black text-xs shadow-xs
                                            {{ $row['nilai_section'] === 'S' ? 'bg-emerald-100 text-emerald-800' :
                                               ($row['nilai_section'] === 'A' ? 'bg-blue-100 text-blue-800' :
                                               ($row['nilai_section'] === 'B' ? 'bg-amber-100 text-amber-800' :
                                               ($row['nilai_section'] === 'C' ? 'bg-orange-100 text-orange-800' : 'bg-rose-100 text-rose-800'))) }}">
                                            {{ $row['nilai_section'] }}
                                        </span>
                                        <span class="text-[9px] text-emerald-700 font-bold mt-0.5">Mandiri</span>
                                    </div>
                                @else
                                    <span class="text-amber-700 font-medium text-[11px] italic">Belum Dinilai</span>
                                @endif
                            @else
                                <span class="text-slate-400 font-medium text-xs">-</span>
                            @endif
                        </td>

                        <!-- 10. Nilai Section Head -->
                        <td class="py-2 px-3 border-b border-r border-slate-200 text-center">
                            @if(!empty($row['is_self']) || !empty($row['is_subordinate_07006']))
                                @if(!empty($row['nilai_section']) && $row['nilai_section'] !== '-')
                                    <div class="inline-flex flex-col items-center">
                                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-none font-black text-xs shadow-xs
                                            {{ $row['nilai_section'] === 'S' ? 'bg-emerald-100 text-emerald-800' :
                                               ($row['nilai_section'] === 'A' ? 'bg-blue-100 text-blue-800' :
                                               ($row['nilai_section'] === 'B' ? 'bg-amber-100 text-amber-800' :
                                               ($row['nilai_section'] === 'C' ? 'bg-orange-100 text-orange-800' : 'bg-rose-100 text-rose-800'))) }}">
                                            {{ $row['nilai_section'] }}
                                        </span>
                                        <span class="text-[9px] text-emerald-700 font-bold mt-0.5">Section Head</span>
                                    </div>
                                @else
                                    <span class="text-amber-700 font-medium text-[11px] italic">Belum Dinilai</span>
                                @endif
                            @elseif(!empty($row['doc_id']))
                                @php
                                    $canEditThisRow = !empty($row['can_evaluate']);
                                @endphp
                                <select name="penilaian[{{ $row['doc_id'] }}][grade]"
                                        {{ !$canEditThisRow ? 'disabled' : '' }}
                                        title="{{ !$canEditThisRow ? 'Terkunci: Menunggu tahapan review Foreman' : 'Pilih nilai akhir Section Head' }}"
                                        class="w-20 px-2 py-1 text-xs font-bold text-center rounded-none border {{ !$canEditThisRow ? 'bg-slate-100 text-slate-400 border-slate-200 cursor-not-allowed' : 'border-slate-300 focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] bg-white shadow-xs' }}">
                                    <option value="">-</option>
                                    <option value="S" {{ $row['nilai_section'] === 'S' ? 'selected' : '' }}>S</option>
                                    <option value="A" {{ $row['nilai_section'] === 'A' ? 'selected' : '' }}>A</option>
                                    <option value="B" {{ $row['nilai_section'] === 'B' ? 'selected' : '' }}>B</option>
                                    <option value="C" {{ $row['nilai_section'] === 'C' ? 'selected' : '' }}>C</option>
                                    <option value="K" {{ $row['nilai_section'] === 'K' ? 'selected' : '' }}>K</option>
                                </select>
                            @else
                                <span class="text-slate-400 font-medium text-xs">-</span>
                            @endif
                        </td>

                        <!-- 11. Status Alur -->
                        <td class="py-2.5 px-3 border-b border-r border-slate-200 text-center">
                            @if($row['status_raw'] === 'selesai')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-none text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                    ✓ Selesai
                                </span>
                            @elseif($row['status_raw'] === 'menunggu_div_head')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-none text-[10px] font-bold bg-purple-100 text-purple-800 border border-purple-300">
                                    Review Div Head
                                </span>
                            @elseif(!empty($row['has_section_sign']) || (!empty($row['nilai_section']) && $row['status_raw'] === 'menunggu_dept_head'))
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-none text-[10px] font-bold bg-blue-100 text-blue-800 border border-blue-300">
                                    Proses Dept Head
                                </span>
                            @elseif($row['status_raw'] === 'menunggu_foreman')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-none text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-300">
                                    Proses Foreman
                                </span>
                            @elseif($row['status_raw'] === 'draft')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-none text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                    Draft Section
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-none text-[10px] font-bold bg-amber-100 text-amber-900 border border-amber-300 animate-pulse">
                                    🔔 Siap Dinilai
                                </span>
                            @endif
                        </td>

                        <!-- 12. Aksi -->
                        <td class="py-2.5 px-3 border-b border-r border-slate-200 text-center">
                            @if((!empty($row['is_self']) || !empty($row['is_subordinate_07006'])) && !empty($row['doc_id']))
                                @if(!empty($row['can_evaluate']))
                                <a href="{{ route('penilaian.show.sechead', $row['doc_id']) }}"
                                   class="inline-flex items-center gap-1 px-3 py-1 text-xs font-bold rounded-none bg-[#92302e] hover:bg-[#7e2524] text-white border border-[#92302e] transition shadow-2xs"
                                   title="{{ !empty($row['is_self']) ? 'Isi Evaluasi Mandiri Section Head' : 'Nilai Karyawan (Form 07-006-KARYAWAN)' }}">
                                    <span>Nilai</span>
                                    <span>&rarr;</span>
                                </a>
                                @else
                                <a href="{{ route('penilaian.show.sechead', $row['doc_id']) }}"
                                   class="inline-flex items-center gap-1 px-3 py-1 text-xs font-bold rounded-none bg-slate-100 hover:bg-[#92302e] text-slate-700 hover:text-white border border-slate-300 transition shadow-2xs"
                                   title="Lihat nilai karyawan (Read-Only)">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <span>Lihat Nilai</span>
                                </a>
                                @endif
                            @elseif(!empty($row['doc_id']))
                            <a href="{{ route('penilaian.show.sechead', $row['doc_id']) }}"
                               class="inline-flex items-center gap-1 px-3 py-1 text-xs font-bold rounded-none bg-white hover:bg-[#92302e] text-[#92302e] hover:text-white border border-[#92302e] transition shadow-2xs">
                                <span>Review</span>
                                <span>&rarr;</span>
                            </a>
                            @else
                            <a href="{{ route('penilaian.byNrp', $row['nrp']) }}"
                               class="inline-flex items-center gap-1 px-3 py-1 text-xs font-bold rounded-none bg-slate-100 hover:bg-[#92302e] text-slate-700 hover:text-white border border-slate-300 transition shadow-2xs">
                                <span>Buka</span>
                                <span>&rarr;</span>
                            </a>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="12" class="py-8 text-center text-slate-400 font-medium">
                            Tidak ada data penilaian karyawan ditemukan.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- 6. Bottom Form Action Buttons -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-3 border-t border-slate-200">
            <span class="text-xs text-slate-500 font-medium">
                Menampilkan <strong class="text-slate-800">{{ $countAll ?? count($rows) }}</strong> karyawan anggota Section Head
            </span>
            <div class="flex items-center gap-2.5 self-end sm:self-auto">
                <button type="submit" 
                        formaction="{{ route('penilaian.section.saveDraft') }}" 
                        class="px-5 py-2.5 rounded-none border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 font-bold text-xs shadow-sm transition-colors">
                    Simpan Draft
                </button>
                <button type="submit" 
                        formaction="{{ route('penilaian.section.submitDept') }}" 
                        class="px-6 py-2.5 rounded-none bg-[#92302e] hover:bg-[#7e2524] text-white font-bold text-xs shadow-sm transition-colors flex items-center gap-1.5">
                    <span>Teruskan ke Dept. Head</span>
                    <span>&rarr;</span>
                </button>
            </div>
        </div>
        </div>
    </form>

</div>
@endsection
