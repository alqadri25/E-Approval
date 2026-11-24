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
    <div class="grid grid-cols-1 sm:grid-cols-2 {{ !$user->isSecretary() ? 'lg:grid-cols-5' : 'lg:grid-cols-3' }} gap-3 sm:gap-3.5 items-stretch">
        <!-- Card 1: Total Anggota -->
        <div class="bg-white rounded-none p-4 border border-slate-300 shadow-sm flex flex-col justify-between h-full">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-500 block">Total Karyawan</span>
                    <div class="text-2xl font-black text-slate-900 leading-none mt-1.5">{{ $countAll ?? count($rows) }}</div>
                    <span class="text-[11px] text-slate-500 mt-1.5 block truncate">Anggota {{ $user->isSecretary() ? 'Secretary' : (($user->isDepartmentHead() || in_array($user->role, ['dept_head', 'department_head'])) ? 'Dept. Head' : ($user->departemen ?? 'Departemen')) }}</span>
                </div>
                <div class="w-9 h-9 rounded-none bg-[#fbe8e8] text-[#92302e] font-black flex items-center justify-center text-xs border border-[#92302e]/20 shrink-0">
                    K
                </div>
            </div>
            <div class="mt-3 pt-2 border-t border-slate-200 flex items-center justify-between text-[11px]">
                <span class="font-bold text-emerald-700">Sudah: {{ $countSelesai ?? 0 }}</span>
                <span class="font-bold text-slate-500">Belum: {{ max(0, ($countAll ?? count($rows)) - ($countSelesai ?? 0)) }}</span>
            </div>
        </div>

        <!-- Card 2: Siap Dinilai (To-Do Dept Head) -->
        <div class="bg-white rounded-none p-4 border {{ ($countSiap ?? 0) > 0 ? 'border-amber-400 bg-amber-50/40' : 'border-slate-300' }} shadow-sm flex flex-col justify-between h-full">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <span class="text-[10px] font-black uppercase tracking-wider {{ ($countSiap ?? 0) > 0 ? 'text-amber-800' : 'text-slate-500' }} block">Siap Dinilai (To-Do)</span>
                    <div class="text-2xl font-black {{ ($countSiap ?? 0) > 0 ? 'text-amber-900' : 'text-slate-900' }} leading-none mt-1.5">{{ $countSiap ?? 0 }}</div>
                    <span class="text-[11px] {{ ($countSiap ?? 0) > 0 ? 'text-amber-700 font-bold' : 'text-slate-500' }} mt-1.5 block truncate">
                        {{ ($countSiap ?? 0) > 0 ? 'Perlu tindakan Anda' : 'Tidak ada antrean' }}
                    </span>
                </div>
                <div class="w-9 h-9 rounded-none {{ ($countSiap ?? 0) > 0 ? 'bg-amber-200 text-amber-900 font-black animate-pulse' : 'bg-slate-100 text-slate-600' }} flex items-center justify-center text-xs font-bold border border-amber-300 shrink-0">
                    !
                </div>
            </div>
            <div class="mt-3 pt-2 border-t border-slate-200 flex items-center justify-between text-[11px]">
                <span class="font-bold {{ ($countSiap ?? 0) > 0 ? 'text-amber-700' : 'text-slate-500' }}">Siap Review: {{ $countSiap ?? 0 }}</span>
                <span class="font-semibold text-slate-400">{{ $user->isSecretary() ? 'Secretary' : 'Dept Head' }}</span>
            </div>
        </div>

        @if(!$user->isSecretary())
        <!-- Card 3: Proses Foreman -->
        <div class="bg-white rounded-none p-4 border border-slate-300 shadow-sm flex flex-col justify-between h-full">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-500 block">Proses Foreman</span>
                    <div class="text-2xl font-black text-slate-900 leading-none mt-1.5">{{ $countProsesForeman ?? 0 }}</div>
                    <span class="text-[11px] text-slate-500 mt-1.5 block truncate">Pengerjaan Foreman</span>
                </div>
                <div class="w-9 h-9 rounded-none bg-blue-50 text-blue-700 font-bold flex items-center justify-center text-xs border border-blue-200 shrink-0">
                    ⏳
                </div>
            </div>
            <div class="mt-3 pt-2 border-t border-slate-200 flex items-center justify-between text-[11px]">
                <span class="font-bold text-emerald-700">Sudah: {{ $countForemanSudah ?? 0 }}</span>
                <span class="font-bold text-slate-500">Belum: {{ $countProsesForeman ?? 0 }}</span>
            </div>
        </div>

        <!-- Card 4: Proses Section Head -->
        <div class="bg-white rounded-none p-4 border border-slate-300 shadow-sm flex flex-col justify-between h-full">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-500 block">Proses Section Head</span>
                    <div class="text-2xl font-black text-slate-900 leading-none mt-1.5">{{ $countProsesSection ?? 0 }}</div>
                    <span class="text-[11px] text-slate-500 mt-1.5 block truncate">Pengerjaan Section Head</span>
                </div>
                <div class="w-9 h-9 rounded-none bg-blue-50 text-blue-700 font-bold flex items-center justify-center text-xs border border-blue-200 shrink-0">
                    ⏳
                </div>
            </div>
            <div class="mt-3 pt-2 border-t border-slate-200 flex items-center justify-between text-[11px]">
                <span class="font-bold text-emerald-700">Sudah: {{ $countSectionSudah ?? 0 }}</span>
                <span class="font-bold text-slate-500">Belum: {{ $countProsesSection ?? 0 }}</span>
            </div>
        </div>
        @endif

        <!-- Card 5: Diteruskan / Selesai -->
        <div class="bg-white rounded-none p-4 border border-slate-300 shadow-sm flex flex-col justify-between h-full">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-500 block">Diteruskan / Selesai</span>
                    <div class="text-2xl font-black text-slate-900 leading-none mt-1.5">{{ $countSelesai ?? 0 }}</div>
                    <span class="text-[11px] text-emerald-700 font-bold mt-1.5 block truncate">Verifikasi {{ $user->isSecretary() ? 'Secretary' : 'Dept Head' }}</span>
                </div>
                <div class="w-9 h-9 rounded-none bg-emerald-50 text-emerald-700 font-black flex items-center justify-center text-xs border border-emerald-200 shrink-0">
                    ✓
                </div>
            </div>
            <div class="mt-3 pt-2 border-t border-slate-200 flex items-center justify-between text-[11px]">
                <span class="font-bold text-emerald-700">Sudah: {{ $countSelesai ?? 0 }}</span>
                <span class="font-bold text-slate-500">Belum: {{ max(0, ($countAll ?? count($rows)) - ($countSelesai ?? 0)) }}</span>
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
                        <th class="py-3 px-4 border-t border-b border-r border-slate-300">NAMA LENGKAP &amp; POSITION</th>
                        <th class="py-3 px-3 border-t border-b border-r border-slate-300 text-center">FORM</th>
                        @if(!$user->isSecretary())
                        <th class="py-3 px-3 border-t border-b border-r border-slate-300">HASIL FOREMAN</th>
                        @endif
                        <th class="py-3 px-3 border-t border-b border-r border-slate-300">HASIL PENILAIAN MANDIRI</th>
                        @if(!$user->isSecretary())
                        <th class="py-3 px-3 border-t border-b border-r border-slate-300">HASIL SECTION HEAD</th>
                        @endif
                        <th class="py-3 px-3 border-t border-b border-r border-slate-300 text-center w-28">{{ $user->isSecretary() ? 'NILAI AKHIR MANDIRI' : 'NILAI AKHIR DEPT' }}</th>
                        <th class="py-3 px-3.5 border-t border-b border-r border-slate-300 min-w-[180px]">{{ $user->isSecretary() ? 'CATATAN MANDIRI' : 'CATATAN DEPT HEAD' }}</th>
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

                        <!-- 3. Nama & Jabatan -->
                        <td class="py-2.5 px-4 border-b border-r border-slate-200">
                            <div class="flex items-center gap-1.5">
                                <span class="font-bold text-slate-900 text-xs">{{ $row['nama_lengkap'] }}</span>
                                @if(!empty($row['is_self']))
                                <span class="px-1.5 py-0.2 rounded-none bg-rose-100 text-rose-800 border border-rose-300 font-black text-[9.5px]">ANDA</span>
                                @endif
                            </div>
                            <div class="text-[10px] text-slate-500 font-medium mt-0.5">
                                {{ $row['jabatan'] }} &bull; {{ $row['golongan'] }}{{ (!empty($row['seksi']) && $row['seksi'] !== '-') ? ' • ' . $row['seksi'] : '' }}
                            </div>
                        </td>

                        <!-- 4. Kode Form -->
                        <td class="py-2.5 px-3 border-b border-r border-slate-200 text-center font-mono font-bold text-indigo-700">
                            {{ str_starts_with($row['kode_form'] ?? '', '07-006') ? '07-006' : ($row['kode_form'] ?? '07-001') }}
                        </td>

                        @if(!$user->isSecretary())
                        <!-- 5. HASIL FOREMAN -->
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

                        <!-- 6. HASIL PENILAIAN MANDIRI -->
                        @php
                            $showMandiri = !empty($row['has_mandiri_col']);
                        @endphp
                        <td class="py-2.5 px-3 border-b border-r border-slate-200 {{ !$showMandiri ? 'text-center' : '' }}">
                            @if(!$showMandiri)
                                <span class="text-slate-400 font-semibold">-</span>
                            @else
                            <div class="flex items-center gap-2">
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
                        <!-- 7. HASIL SECTION HEAD -->
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

                        <!-- 8. Nilai Akhir Dept Head (Dropdown Docking) -->
                        <td class="py-2 px-3 border-b border-r border-slate-200 text-center">
                            @if(!empty($row['doc_id']))
                                @php
                                    $canEditThisRow = !empty($row['can_evaluate_dept']);
                                @endphp
                                <select name="penilaian[{{ $row['doc_id'] }}][grade]"
                                        {{ !$canEditThisRow ? 'disabled' : '' }}
                                        title="{{ !$canEditThisRow ? 'Terkunci: Menunggu tahapan review sebelumnya' : 'Pilih nilai akhir Dept Head' }}"
                                        class="w-20 px-2 py-1 text-xs font-bold text-center rounded-none border {{ !$canEditThisRow ? 'bg-slate-100 text-slate-400 border-slate-200 cursor-not-allowed' : 'border-slate-300 focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] bg-white shadow-xs' }}">
                                    <option value="">-</option>
                                    <option value="S" {{ $row['nilai_akhir'] === 'S' ? 'selected' : '' }}>S</option>
                                    <option value="A" {{ $row['nilai_akhir'] === 'A' ? 'selected' : '' }}>A</option>
                                    <option value="B" {{ $row['nilai_akhir'] === 'B' ? 'selected' : '' }}>B</option>
                                    <option value="C" {{ $row['nilai_akhir'] === 'C' ? 'selected' : '' }}>C</option>
                                    <option value="K" {{ $row['nilai_akhir'] === 'K' ? 'selected' : '' }}>K</option>
                                </select>
                            @else
                                <span class="text-slate-400">-</span>
                            @endif
                        </td>

                        <!-- 9. Catatan -->
                        <td class="py-2 px-3.5 border-b border-r border-slate-200">
                            @if(!empty($row['doc_id']) && !empty($row['can_evaluate_dept']))
                            <input type="text"
                                   name="penilaian[{{ $row['doc_id'] }}][catatan]"
                                   value="{{ $row['catatan'] }}"
                                   placeholder="{{ $user->isSecretary() ? 'Catatan penilaian mandiri...' : 'Catatan penilaian departemen...' }}"
                                   class="w-full px-2 py-1 rounded-none border border-slate-300 text-xs font-medium focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] bg-white">
                            @else
                            <span class="text-slate-600 text-[11px] truncate max-w-xs block">
                                {{ $row['catatan'] ?: 'Sesuai dengan target dan pencapaian kerja.' }}
                            </span>
                            @endif
                        </td>

                        <!-- 10. Status Alur -->
                        <td class="py-2.5 px-3 border-b border-r border-slate-200 text-center">
                            @if($row['status_raw'] === 'selesai' || (!empty($row['is_done']) && empty($row['is_self'])))
                                <span class="inline-flex items-center px-2 py-0.5 rounded-none text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                    ✓ Selesai
                                </span>
                            @elseif($row['status_raw'] === 'menunggu_div_head')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-none text-[10px] font-bold bg-indigo-100 text-indigo-800 border border-indigo-300">
                                    Review Div Head
                                </span>
                            @elseif(!empty($row['is_self']))
                                <span class="inline-flex items-center px-2 py-0.5 rounded-none text-[10px] font-bold bg-purple-100 text-purple-800 border border-purple-300">
                                    Penilaian Mandiri
                                </span>
                            @elseif($row['status_raw'] === 'menunggu_dept_head' || !empty($row['can_evaluate_dept']))
                                <span class="inline-flex items-center px-2 py-0.5 rounded-none text-[10px] font-bold bg-amber-100 text-amber-900 border border-amber-300 animate-pulse" title="Perlu tindakan Anda">
                                    🔔 Siap Dinilai
                                </span>
                            @elseif($row['status_raw'] === 'menunggu_section_head')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-none text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-300">
                                    Proses Sect Head
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
                                    {{ !empty($row['has_foreman_col']) ? 'Proses Foreman' : 'Proses Sect Head' }}
                                </span>
                            @endif
                        </td>

                        <!-- 11. Aksi (Tombol Buka Form Penilaian) -->
                        <td class="py-2.5 px-3 border-b border-r border-slate-200 text-center">
                            @if(!empty($row['doc_id']))
                                @if(!empty($row['can_evaluate_dept']))
                                <a href="{{ route($user->isSecretary() ? 'penilaian.show.secretary' : 'penilaian.show.depthead', $row['doc_id']) }}" 
                                   class="inline-flex items-center gap-1 px-3 py-1 rounded-none bg-[#92302e] hover:bg-[#7e2524] text-white text-xs font-bold transition shadow-xs">
                                    <span>Form &rarr;</span>
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
                        <td colspan="{{ $user->isSecretary() ? 9 : 11 }}" class="py-8 text-center text-slate-400 italic">
                            Tidak ada data penilaian untuk ditampilkan di departemen ini.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Footer Actions -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between pt-3 border-t border-slate-200 gap-3">
            <span class="text-xs text-slate-500 font-medium">
                @if($user->isSecretary())
                    Total <strong>{{ count($rows) }}</strong> karyawan terdaftar di Secretary
                @else
                    Total <strong>{{ count($rows) }}</strong> karyawan terdaftar di {{ ($user->isDepartmentHead() || in_array($user->role, ['dept_head', 'department_head'])) ? 'Department Head' : ('departemen ' . ($user->departemen ?? '')) }}
                @endif
            </span>

            <div class="flex items-center gap-2.5">
                <button type="submit"
                        class="px-4 py-2 rounded-none border border-slate-300 bg-white hover:bg-slate-100 text-slate-700 font-bold text-xs transition shadow-xs">
                    Simpan Draft
                </button>
                <a href="{{ route('penilaian.cari') }}"
                   class="px-4 py-2 rounded-none bg-[#92302e] hover:bg-[#7e2524] text-white font-bold text-xs transition inline-flex items-center gap-1.5 shadow-xs">
                    <span>Buka Form Penilaian Lengkap</span>
                    <span>&rarr;</span>
                </a>
            </div>
        </div>
        </div>
    </form>

</div>
@endsection
