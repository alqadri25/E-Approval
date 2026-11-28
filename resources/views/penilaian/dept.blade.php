@extends('layouts.app', ['title' => ($user->isSecretary() ? 'Data Penilaian - Secretary' : 'Data Penilaian - Department Head')])

@section('content')
<div class="space-y-5" x-data="{ 
    activeTab: 'semua',
    searchQuery: '',
    filterSection: '',
    filterPosition: '',
    matchesFilter(statusRaw, nrp, nama, jabatan, seksi = '', isSelf = false) {
        // Tab check
        if (this.activeTab === 'siap') {
            if (statusRaw !== 'menunggu_dept_head' && !(isSelf && statusRaw !== 'selesai' && statusRaw !== 'menunggu_div_head')) return false;
        }
        if (this.activeTab === 'proses' && !['menunggu_foreman', 'menunggu_section_head', 'draft', 'belum_ada', 'revisi'].includes(statusRaw)) return false;
        if (this.activeTab === 'selesai' && !['menunggu_div_head', 'selesai'].includes(statusRaw)) return false;

        // Section check
        if (this.filterSection !== '' && seksi.toLowerCase() !== this.filterSection.toLowerCase()) {
            return false;
        }

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
                @if($user->isSecretary())
                    Melakukan pengisian penilaian mandiri dan menetapkan nilai akhir serta tanda tangan digital untuk diteruskan ke Division Head
                @else
                    Meninjau hasil penilaian dari Foreman, Penilaian Mandiri, dan Section Head, serta menetapkan nilai akhir &amp; tanda tangan digital Department Head
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

    <!-- 2. Informative Assessment Stat Cards (5 Card Sejajar 1 Baris) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 {{ !$user->isSecretary() ? 'lg:grid-cols-5' : 'lg:grid-cols-3' }} gap-3.5">
        <!-- Card 1: Total Anggota -->
        <div class="bg-white rounded-none p-4 border border-slate-300 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-500 block mb-1">Total Karyawan</span>
                <div class="text-2xl font-black text-slate-900 leading-none">{{ $countAll ?? count($rows) }}</div>
                <span class="text-[11px] text-slate-500 mt-1 block">Anggota {{ $user->isSecretary() ? 'Secretary' : 'Department Head' }}</span>
            </div>
            <div class="w-10 h-10 rounded-none bg-[#fbe8e8] text-[#92302e] font-black flex items-center justify-center text-sm border border-[#92302e]/20 shrink-0">
                K
            </div>
        </div>

        <!-- Card 2: Siap Dinilai (To-Do Dept Head) -->
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

        @if(!$user->isSecretary())
        <!-- Card 3: Proses Foreman -->
        <div class="bg-white rounded-none p-4 border border-slate-300 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-500 block mb-1">Proses Foreman</span>
                <div class="text-2xl font-black text-slate-900 leading-none">{{ $countProsesForeman ?? 0 }}</div>
                <span class="text-[11px] text-slate-500 mt-1 block">Pengerjaan Form Foreman</span>
            </div>
            <div class="w-10 h-10 rounded-none bg-blue-50 text-blue-700 font-bold flex items-center justify-center text-sm border border-blue-200 shrink-0">
                ⏳
            </div>
        </div>

        <!-- Card 4: Proses Section Head -->
        <div class="bg-white rounded-none p-4 border border-slate-300 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-500 block mb-1">Proses Section Head</span>
                <div class="text-2xl font-black text-slate-900 leading-none">{{ $countProsesSection ?? 0 }}</div>
                <span class="text-[11px] text-slate-500 mt-1 block">Pengerjaan Form Section Head</span>
            </div>
            <div class="w-10 h-10 rounded-none bg-blue-50 text-blue-700 font-bold flex items-center justify-center text-sm border border-blue-200 shrink-0">
                ⏳
            </div>
        </div>
        @endif

        <!-- Card 5: Diteruskan Ke Division Head -->
        <div class="bg-white rounded-none p-4 border border-slate-300 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-500 block mb-1">{{ $user->isSecretary() ? 'Diteruskan Ke Division Head' : 'Diteruskan Ke Division Head' }}</span>
                <div class="text-2xl font-black text-slate-900 leading-none">{{ $countSelesai ?? 0 }}</div>
                <span class="text-[11px] text-emerald-700 font-bold mt-1 block">Telah diverifikasi {{ $user->isSecretary() ? 'Secretary' : 'Department Head' }}</span>
            </div>
            <div class="w-10 h-10 rounded-none bg-emerald-50 text-emerald-700 font-black flex items-center justify-center text-sm border border-emerald-200 shrink-0">
                ✓
            </div>
        </div>
    </div>

    <!-- 3. Notification Banner (Jika ada dokumen siap dinilai) -->
    @if(($countSiap ?? 0) > 0 && $latestPending)
    <div class="p-3.5 rounded-none bg-amber-50 border-l-4 border-amber-500 border-y border-r border-amber-300 text-amber-900 text-xs font-medium flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-xs">
        <div class="flex items-center gap-2.5">
            <span class="w-6 h-6 rounded-none bg-amber-500 text-white font-black text-xs flex items-center justify-center animate-pulse">!</span>
            <span>
                @if($user->isSecretary())
                    Ada <strong>{{ $countSiap }} dokumen</strong> penilaian mandiri yang menunggu pengisian nilai &amp; tanda tangan digital Anda.
                @else
                    Ada <strong>{{ $countSiap }} dokumen</strong> penilaian masuk dari Section Head yang menunggu review &amp; tanda tangan digital Anda.
                @endif
            </span>
        </div>
        @if(!empty($latestPending['doc_id']))
        <a href="{{ route($user->isSecretary() ? 'penilaian.show.secretary' : 'penilaian.show.depthead', $latestPending['doc_id']) }}" 
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
        $deptSectionOptions = collect($rows)->pluck('seksi')->map(fn ($v) => trim((string)$v))->filter(fn ($v) => $v !== '' && $v !== '-')->unique()->sort()->values()->all();
        $deptPositionOptions = collect($rows)->pluck('jabatan')->map(fn ($v) => trim((string)$v))->filter(fn ($v) => $v !== '' && $v !== '-')->unique()->sort()->values()->all();
    @endphp
    <form action="{{ route('penilaian.dept.saveDraft') }}" method="POST" class="bg-white rounded-none border border-slate-300 shadow-sm overflow-hidden">
        @csrf

        <!-- Grey Header & Filter Bar (Section & Position) directly above the Table -->
        <div class="px-4 py-3 bg-slate-50 border-b border-slate-300 flex flex-col sm:flex-row sm:items-center justify-between gap-3 overflow-x-auto">
            <div class="flex items-center gap-2 shrink-0">
                <h3 class="text-xs font-black text-slate-800 uppercase tracking-wider">Daftar Penilaian Karyawan</h3>
                <span class="px-2 py-0.5 text-[10px] font-bold bg-slate-200 text-slate-800 rounded-none font-mono">{{ $countAll ?? count($rows) }} Karyawan</span>
            </div>
            <div class="flex flex-nowrap items-center gap-2.5 shrink-0">
                <div class="flex items-center gap-1.5 shrink-0">
                    <label for="deptFilterSection" class="text-[10px] font-bold text-slate-700 uppercase tracking-wider whitespace-nowrap">SECTION</label>
                    <select id="deptFilterSection" x-model="filterSection"
                            class="px-2.5 py-1.5 text-xs font-semibold bg-white border border-slate-300 hover:border-slate-400 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] text-slate-800 rounded-none cursor-pointer">
                        <option value="">Section</option>
                        @foreach($deptSectionOptions as $optSec)
                            <option value="{{ $optSec }}">{{ $optSec }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center gap-1.5 shrink-0">
                    <label for="deptFilterPosition" class="text-[10px] font-bold text-slate-700 uppercase tracking-wider whitespace-nowrap">POSITION</label>
                    <select id="deptFilterPosition" x-model="filterPosition"
                            class="px-2.5 py-1.5 text-xs font-semibold bg-white border border-slate-300 hover:border-slate-400 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] text-slate-800 rounded-none cursor-pointer max-w-[200px]">
                        <option value="">Position</option>
                        @foreach($deptPositionOptions as $optPos)
                            <option value="{{ $optPos }}">{{ $optPos }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="button"
                        x-show="filterSection !== '' || filterPosition !== ''"
                        @click="filterSection = ''; filterPosition = ''"
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
                        <th class="py-3 px-3 border-t border-b border-r border-slate-300 text-center">{{ $user->isSecretary() ? 'FORMULIR' : 'FORM' }}</th>
                        @if(!$user->isSecretary())
                        <th class="py-3 px-3 border-t border-b border-r border-slate-300">HASIL FOREMAN</th>
                        @endif
                        <th class="py-3 px-3 border-t border-b border-r border-slate-300 text-center">NILAI MANDIRI</th>
                        @if(!$user->isSecretary())
                        <th class="py-3 px-3 border-t border-b border-r border-slate-300">NILAI SECTION HEAD</th>
                        @endif
                        <th class="py-3 px-3 border-t border-b border-r border-slate-300 text-center w-28">{{ $user->isSecretary() ? 'NILAI AKHIR MANDIRI' : 'NILAI DEPARTMENT HEAD' }}</th>
                        <th class="py-3 px-3 border-t border-b border-r border-slate-300 text-center w-28">STATUS ALUR</th>
                        <th class="py-3 px-3 border-t border-b border-r border-slate-300 text-center w-24">AKSI</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($rows as $idx => $row)
                    <tr class="hover:bg-slate-50 transition-colors"
                        x-show="matchesFilter('{{ $row['status_raw'] }}', '{{ $row['nrp'] }}', '{{ addslashes($row['nama_lengkap']) }}', '{{ addslashes($row['jabatan']) }}', '{{ addslashes($row['seksi']) }}', {{ !empty($row['is_self']) ? 'true' : 'false' }})">
                        
                        <!-- 1. NO -->
                        <td class="py-2.5 px-3 border-b border-l border-r border-slate-200 text-center font-bold text-slate-700">
                            {{ $idx + 1 }}
                        </td>

                        <!-- 2. NRP -->
                        <td class="py-2.5 px-3 border-b border-r border-slate-200 text-center font-mono font-bold text-slate-800">
                            @if(!empty($row['doc_id']))
                            <a href="{{ route($user->isSecretary() ? 'penilaian.show.secretary' : 'penilaian.show.depthead', $row['doc_id']) }}" 
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
                            @if(!empty($row['seksi']) && $row['seksi'] !== '-')
                            <div class="text-[10px] text-slate-400 font-medium mt-0.5">
                                {{ $row['seksi'] }}
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

                        <!-- 7. Form -->
                        <td class="py-2.5 px-3 border-b border-r border-slate-200 text-center {{ ($user->isSecretary() || !empty($row['is_secretary'])) ? 'font-bold text-slate-800' : 'font-mono font-bold text-indigo-700' }}">
                            @if($user->isSecretary() || !empty($row['is_secretary']))
                                Form Secretary
                            @else
                                {{ str_starts_with($row['kode_form'] ?? '', '07-006') ? '07-006' : ($row['kode_form'] ?? '07-001') }}
                            @endif
                        </td>

                        @if(!$user->isSecretary())
                        <!-- 8. HASIL FOREMAN -->
                        @php
                            $showForeman = !empty($row['has_foreman_col']);
                        @endphp
                        <td class="py-2.5 px-3 border-b border-r border-slate-200 {{ !$showForeman ? 'text-center' : '' }}">
                            @if(!$showForeman)
                                <span class="text-slate-400 font-semibold">-</span>
                            @else
                            <div class="flex items-center gap-2">
                                @if(($row['nilai_foreman'] ?? '-') !== '-')
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
                                    @if(!empty($row['has_foreman_sign']))
                                        <span class="text-[9.5px] font-semibold text-emerald-700 block">✓ TTD Lengkap</span>
                                    @else
                                        <span class="text-[9.5px] text-slate-400 italic block">Belum TTD</span>
                                    @endif
                                </div>
                            </div>
                            @endif
                        </td>
                        @endif

                        <!-- 9. NILAI MANDIRI -->
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

                        @if(!$user->isSecretary())
                        <!-- 10. NILAI SECTION HEAD -->
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
                        @endif

                        <!-- 11. NILAI DEPARTMENT HEAD -->
                        <td class="py-2 px-3 border-b border-r border-slate-200 text-center">
                            @if(!empty($row['is_self']))
                                @if(!empty($row['nilai_akhir']))
                                    <div class="inline-flex flex-col items-center">
                                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-none font-black text-xs shadow-xs
                                            {{ $row['nilai_akhir'] === 'S' ? 'bg-emerald-100 text-emerald-800' :
                                               ($row['nilai_akhir'] === 'A' ? 'bg-blue-100 text-blue-800' :
                                               ($row['nilai_akhir'] === 'B' ? 'bg-amber-100 text-amber-800' :
                                               ($row['nilai_akhir'] === 'C' ? 'bg-orange-100 text-orange-800' : 'bg-rose-100 text-rose-800'))) }}">
                                            {{ $row['nilai_akhir'] }}
                                        </span>
                                        <span class="text-[9px] text-emerald-700 font-bold mt-0.5">Dept Head</span>
                                    </div>
                                @else
                                    <span class="text-amber-700 font-medium text-[11px] italic">Belum Dinilai</span>
                                @endif
                            @elseif(!empty($row['doc_id']))
                                @if(in_array($row['status_raw'], ['menunggu_div_head', 'selesai']) || (!empty($row['has_dept_sign']) && empty($row['can_evaluate_dept'])))
                                    @if(!empty($row['nilai_akhir']))
                                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-none font-black text-xs shadow-xs
                                            {{ $row['nilai_akhir'] === 'S' ? 'bg-emerald-100 text-emerald-800' :
                                               ($row['nilai_akhir'] === 'A' ? 'bg-blue-100 text-blue-800' :
                                               ($row['nilai_akhir'] === 'B' ? 'bg-amber-100 text-amber-800' :
                                               ($row['nilai_akhir'] === 'C' ? 'bg-orange-100 text-orange-800' : 'bg-rose-100 text-rose-800'))) }}">
                                            {{ $row['nilai_akhir'] }}
                                        </span>
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                @else
                                    <span class="{{ !empty($row['can_evaluate_dept']) ? 'text-amber-700 font-medium text-[11px] italic' : 'text-slate-400 font-semibold' }}">
                                        {{ !empty($row['nilai_akhir']) ? $row['nilai_akhir'] : (!empty($row['can_evaluate_dept']) ? 'Belum Dinilai' : '-') }}
                                    </span>
                                @endif
                            @else
                                <span class="text-slate-400">-</span>
                            @endif
                        </td>

                        <!-- 12. Status Alur -->
                        <td class="py-2.5 px-3 border-b border-r border-slate-200 text-center">
                            @if($row['status_raw'] === 'selesai')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-none text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                    ✓ Selesai
                                </span>
                            @elseif($row['status_raw'] === 'menunggu_div_head')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-none text-[10px] font-bold bg-indigo-100 text-indigo-800 border border-indigo-300">
                                    Proses Div Head
                                </span>
                            @elseif($row['status_raw'] === 'menunggu_dept_head' || !empty($row['can_evaluate_dept']))
                                <span class="inline-flex items-center px-2 py-0.5 rounded-none text-[10px] font-bold bg-amber-100 text-amber-900 border border-amber-300 animate-pulse" title="Perlu tindakan Anda">
                                    🔔 Siap Dinilai
                                </span>
                            @elseif($row['status_raw'] === 'menunggu_section_head')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-none text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-300">
                                    Proses Section Head
                                </span>
                            @elseif($row['status_raw'] === 'menunggu_foreman')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-none text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-300">
                                    Proses Foreman
                                </span>
                            @elseif($row['status_raw'] === 'revisi')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-none text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-300">
                                    Revisi
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-none text-[10px] font-medium bg-slate-100 text-slate-600 border border-slate-200">
                                    {{ !empty($row['has_foreman_col']) ? 'Proses Foreman' : 'Proses Section Head' }}
                                </span>
                            @endif
                        </td>

                        <!-- 13. Aksi (Tombol Buka Form Penilaian) -->
                        <td class="py-2.5 px-3 border-b border-r border-slate-200 text-center">
                            @if(!empty($row['doc_id']))
                                @if(!empty($row['can_evaluate_dept']))
                                <a href="{{ route($user->isSecretary() ? 'penilaian.show.secretary' : 'penilaian.show.depthead', $row['doc_id']) }}" 
                                   class="inline-flex items-center gap-1 px-3 py-1 rounded-none bg-[#92302e] hover:bg-[#7e2524] text-white text-xs font-bold transition shadow-xs">
                                    <span>Nilai</span>
                                    <span>&rarr;</span>
                                </a>
                                @else
                                <a href="{{ route($user->isSecretary() ? 'penilaian.show.secretary' : 'penilaian.show.depthead', $row['doc_id']) }}" 
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
                        <td colspan="{{ $user->isSecretary() ? 10 : 13 }}" class="py-8 text-center text-slate-400 italic">
                            Tidak ada data penilaian untuk ditampilkan di departemen ini.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Footer Info -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between pt-3 border-t border-slate-200 gap-3">
            <span class="text-xs text-slate-500 font-medium">
                @if($user->isSecretary())
                    Total <strong>{{ count($rows) }}</strong> karyawan terdaftar di Secretary
                @else
                    Total <strong>{{ count($rows) }}</strong> karyawan terdaftar di {{ ($user->isDepartmentHead() || in_array($user->role, ['dept_head', 'department_head'])) ? 'Department Head' : ('departemen ' . ($user->departemen ?? '')) }}
                @endif
            </span>
        </div>
        </div>
    </form>

</div>
@endsection
