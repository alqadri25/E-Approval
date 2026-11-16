<!-- ========================================================================= -->
<!-- DIVISION HEAD EXECUTIVE DASHBOARD (Matching Department Head Layout)       -->
<!-- ========================================================================= -->
@php
    $totKaryawan = count($divSubordinates);
    $pSec = $totKaryawan > 0 ? round(($divSectionTotalDinilai / $totKaryawan) * 100) : 0;
    $pDept = $totKaryawan > 0 ? round(($divDeptTotalDinilai / $totKaryawan) * 100) : 0;
    $pDiv = $totKaryawan > 0 ? round(($divFinalTotalDinilai / $totKaryawan) * 100) : 0;
    $pendingDiv = count($incomingDocsDivHead);

    // Target Distribution
    $targetS = $divTargetCounts['S'] ?? 0;
    $targetA = $divTargetCounts['A'] ?? 0;
    $targetB = $divTargetCounts['B'] ?? 0;
    $targetC = $divTargetCounts['C'] ?? 0;
    $targetK = $divTargetCounts['K'] ?? 0;
    $sumDivTargets = $targetS + $targetA + $targetB + $targetC + $targetK;
    $effectiveTotalTargetDiv = isset($totalTargetDiv) ? (int)$totalTargetDiv : $sumDivTargets;

    // Dept Head Hasil
    $deptS = $divDeptGradeCounts['S'] ?? 0;
    $deptA = $divDeptGradeCounts['A'] ?? 0;
    $deptB = $divDeptGradeCounts['B'] ?? 0;
    $deptC = $divDeptGradeCounts['C'] ?? 0;
    $deptK = $divDeptGradeCounts['K'] ?? 0;

    // Final Hasil Division Head
    $finS = $divFinalGradeCounts['S'] ?? 0;
    $finA = $divFinalGradeCounts['A'] ?? 0;
    $finB = $divFinalGradeCounts['B'] ?? 0;
    $finC = $divFinalGradeCounts['C'] ?? 0;
    $finK = $divFinalGradeCounts['K'] ?? 0;
@endphp

<div class="space-y-6">

    <!-- 1. Top Header Card (Merah Musashi Gradient & Rounded-None) -->
    <div class="bg-gradient-to-r from-[#962226] via-[#92302e] to-[#781b1f] text-white rounded-none p-5 sm:p-6 border border-[#781b1f] shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight">
                Dashboard
            </h1>
            <p class="text-xs text-red-100 font-medium mt-1">
                Selamat Datang {{ $user->nama }}
            </p>
        </div>

        <div class="flex items-center gap-2.5">
            <a href="{{ route('penilaian.div') }}" 
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-none bg-white hover:bg-slate-100 text-[#92302e] font-bold text-xs shadow-sm transition-colors border border-white">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <span>Data Penilaian</span>
            </a>
        </div>
    </div>

    <!-- 2. Live Action Banner -->
    @if($pendingDiv > 0)
    <!-- Urgent Action Banner: Dokumen Masuk dari Department Head -->
    <div class="bg-amber-50/80 border-l-4 border-amber-500 p-4 border-y border-r border-amber-300 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-start gap-3.5">
            <div class="w-8 h-8 rounded-none bg-amber-500 text-white flex items-center justify-center font-bold text-base flex-shrink-0 animate-pulse">
                !
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 bg-amber-200 text-amber-900 rounded-none">
                        Perlu Tindakan Anda (To-Do)
                    </span>
                    <span class="text-xs text-amber-950 font-bold">
                        {{ $pendingDiv }} Dokumen Penilaian Menunggu Persetujuan Final (Form 07-006)
                    </span>
                </div>
                <p class="text-xs text-amber-900 mt-1">
                    Mohon segera lakukan peninjauan nilai &amp; bubuhkan tanda tangan digital Anda untuk pengesahan final.
                </p>
            </div>
        </div>
        <a href="{{ route('penilaian.div') }}" 
           class="px-4 py-2 rounded-none bg-[#92302e] hover:bg-[#7e2524] text-white font-bold text-xs shadow-sm whitespace-nowrap self-start md:self-center transition-colors">
            Tinjau Dokumen Masuk &rarr;
        </a>
    </div>

    <!-- Preview Cards for Pending Documents -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        @foreach(array_slice($incomingDocsDivHead, 0, 4) as $inDoc)
        <div class="bg-white rounded-none p-3.5 border border-amber-300 shadow-xs flex flex-col justify-between hover:shadow-md transition">
            <div>
                <div class="flex items-center justify-between gap-1">
                    <span class="font-bold text-slate-900 text-xs truncate" title="{{ $inDoc['karyawan']['nama_lengkap'] ?? $inDoc['nrp'] }}">
                        {{ $inDoc['karyawan']['nama_lengkap'] ?? $inDoc['nrp'] }}
                    </span>
                    <span class="text-[9px] font-mono px-1.5 py-0.5 rounded-none bg-slate-100 text-slate-700 font-bold">
                        {{ $inDoc['kode_form'] }}
                    </span>
                </div>
                <p class="text-[10px] text-slate-500 font-mono mt-0.5">
                    NRP: {{ $inDoc['nrp'] }} &bull; {{ $inDoc['karyawan']['departemen'] ?? '-' }}
                </p>
                <div class="mt-2 text-[11px] text-slate-600">
                    <span>{{ (($inDoc['nrp'] ?? '') === $user->nrp) ? 'Penilaian Mandiri: ' : 'Nilai Dept Head: ' }}</span>
                    <strong class="text-amber-700 font-bold">{{ $inDoc['total_nilai'] ?? '-' }}</strong>
                </div>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-end">
                <a href="{{ route('penilaian.show.divhead', $inDoc['id']) }}" 
                   class="w-full text-center py-1.5 px-3 rounded-none bg-[#92302e] hover:bg-[#7e2524] text-white font-bold text-xs shadow-xs transition">
                    {{ (($inDoc['nrp'] ?? '') === $user->nrp) ? 'Nilai Form Mandiri →' : 'Review & Sahkan →' }}
                </a>
            </div>
        </div>
        @endforeach
    </div>

    @elseif(($divStageCounts['tahap1_section'] + $divStageCounts['tahap2_dept']) > 0)
    <!-- Info Banner: Sedang Dikerjakan Section Head & Dept Head -->
    <div class="bg-blue-50/70 border-l-4 border-blue-500 p-4 border-y border-r border-blue-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-start gap-3.5">
            <div class="w-8 h-8 rounded-none bg-blue-600 text-white flex items-center justify-center font-bold text-sm flex-shrink-0">
                📋
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 bg-blue-200 text-blue-900 rounded-none">
                        Proses Berjalan
                    </span>
                    <span class="text-xs text-blue-950 font-bold">
                        {{ $divStageCounts['tahap1_section'] + $divStageCounts['tahap2_dept'] }} dari {{ $totKaryawan }} Dokumen Sedang Dikerjakan Section Head &amp; Dept Head
                    </span>
                </div>
                <p class="text-xs text-blue-800 mt-1">
                    Penilaian sedang diproses di tingkat Seksi dan Departemen. Berkas akan otomatis masuk ke antrean Anda begitu Department Head menandatangani.
                </p>
            </div>
        </div>
        <a href="{{ route('penilaian.div') }}" 
           class="px-3.5 py-1.5 rounded-none bg-blue-700 hover:bg-blue-800 text-white font-bold text-xs shadow-sm whitespace-nowrap self-start md:self-center">
            Lihat Data Penilaian &rarr;
        </a>
    </div>

    @else
    <!-- All Done Success Banner -->
    <div class="bg-emerald-50 border-l-4 border-emerald-600 p-4 border-y border-r border-emerald-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-start gap-3">
            <div class="w-8 h-8 rounded-none bg-emerald-600 text-white flex items-center justify-center font-bold text-sm flex-shrink-0">
                ✓
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 bg-emerald-200 text-emerald-900 rounded-none">
                        Tugas Division Head Tuntas 100%
                    </span>
                    <span class="text-xs font-bold text-emerald-950">
                        Seluruh {{ $totKaryawan }} Penilaian Karya Telah Disahkan Final
                    </span>
                </div>
                <p class="text-xs text-emerald-800 mt-1">
                    Semua berkas penilaian karya dalam lingkup divisi telah disahkan penuh oleh Division Head dan terarsip aman.
                </p>
            </div>
        </div>
        <a href="{{ route('penilaian.riwayat') }}" 
           class="px-3.5 py-1.5 rounded-none bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs shadow-sm whitespace-nowrap self-start md:self-center">
            Lihat Riwayat &amp; Rekap &rarr;
        </a>
    </div>
    @endif

    <!-- 3. Key Stat Cards in a Row (4 Columns - Interactive Click to Filter) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- 1. Total Karyawan -->
        <div onclick="filterDivKaryawan('all')" 
             role="button"
             tabindex="0"
             title="Klik untuk melihat semua data karyawan & penilaian divisi"
             class="bg-white rounded-none p-4 border border-slate-300 hover:border-[#92302e] hover:shadow-md transition-all flex items-center justify-between group cursor-pointer active:scale-[0.99]">
            <div class="flex items-center gap-3.5">
                <div class="w-10 h-10 rounded-none bg-[#fbe8e8] text-[#92302e] font-black flex items-center justify-center text-sm flex-shrink-0 border border-[#92302e]/20 group-hover:bg-[#92302e] group-hover:text-white transition-colors">
                    K
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-semibold leading-none group-hover:text-[#92302e] transition-colors">Total Karyawan</p>
                    <p class="text-2xl font-black text-slate-900 mt-1.5 leading-none">{{ $totKaryawan }}</p>
                    <p class="text-[11px] text-slate-500 mt-1 leading-none">Anggota Division Head</p>
                </div>
            </div>
            <div class="flex items-center">
                <span class="text-[10px] font-bold px-2 py-1 bg-slate-100 text-slate-600 rounded-none border border-slate-200">
                    100% Tim
                </span>
            </div>
        </div>

        <!-- 2. Dinilai Section Head (Tanpa Foreman) -->
        <div onclick="filterDivKaryawan('sec_done')" 
             role="button"
             tabindex="0"
             title="Klik untuk melihat dokumen yang sudah dinilai Section Head di tabel"
             class="bg-white rounded-none p-4 border border-slate-300 hover:border-teal-600 hover:shadow-md transition-all flex items-center justify-between group cursor-pointer active:scale-[0.99]">
            <div class="flex items-center gap-3.5">
                <div class="w-10 h-10 rounded-none bg-teal-50 text-teal-700 font-black flex items-center justify-center text-sm flex-shrink-0 border border-teal-300 group-hover:bg-teal-600 group-hover:text-white transition-colors">
                    ✓
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-semibold leading-none group-hover:text-teal-700 transition-colors">Dinilai Section Head</p>
                    <p class="text-2xl font-black text-slate-900 mt-1.5 leading-none">{{ $divSectionTotalDinilai ?? 0 }}</p>
                    <p class="text-[11px] text-teal-700 font-bold mt-1 leading-none">
                        ({{ $pSec }}%) Diteruskan ke Dept Head
                    </p>
                </div>
            </div>
            <div class="flex items-center">
                <span class="text-[10px] font-bold px-2 py-1 bg-teal-50 text-teal-700 rounded-none border border-teal-200">
                    Nilai SH
                </span>
            </div>
        </div>

        <!-- 3. Dinilai Dept. Head -->
        <div onclick="filterDivKaryawan('tahap2')" 
             role="button"
             tabindex="0"
             title="Klik untuk memfilter Data Karyawan pada Tahap Dept Head"
             class="bg-white rounded-none p-4 border border-slate-300 hover:border-emerald-600 hover:shadow-md transition-all flex items-center justify-between group cursor-pointer active:scale-[0.99]">
            <div class="flex items-center gap-3.5">
                <div class="w-10 h-10 rounded-none bg-emerald-50 text-emerald-700 font-black flex items-center justify-center text-sm flex-shrink-0 border border-emerald-300 group-hover:bg-emerald-600 group-hover:text-white transition-colors">
                    ✓
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-semibold leading-none group-hover:text-emerald-700 transition-colors">Dinilai Dept. Head</p>
                    <p class="text-2xl font-black text-slate-900 mt-1.5 leading-none">{{ $divDeptTotalDinilai }}</p>
                    <p class="text-[11px] text-emerald-600 font-bold mt-1 leading-none">
                        ({{ $pDept }}%) Diverifikasi Dept
                    </p>
                </div>
            </div>
            <div class="flex items-center">
                <span class="text-[10px] font-bold px-2 py-1 bg-emerald-50 text-emerald-700 rounded-none border border-emerald-200">
                    Nilai Dept
                </span>
            </div>
        </div>

        <!-- 4. Disahkan Div. Head -->
        <div onclick="filterDivKaryawan('tahap3')" 
             role="button"
             tabindex="0"
             title="Klik untuk memfilter Data Karyawan pada Tahap Division Head (To-Do)"
             class="bg-white rounded-none p-4 border border-slate-300 hover:border-blue-600 hover:shadow-md transition-all flex items-center justify-between group cursor-pointer active:scale-[0.99]">
            <div class="flex items-center gap-3.5">
                <div class="w-10 h-10 rounded-none bg-blue-50 text-blue-700 font-black flex items-center justify-center text-sm flex-shrink-0 border border-blue-300 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                    ✈
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-semibold leading-none group-hover:text-blue-700 transition-colors">Disahkan Div. Head</p>
                    <p class="text-2xl font-black text-slate-900 mt-1.5 leading-none">{{ $divFinalTotalDinilai }}</p>
                    <p class="text-[11px] text-blue-600 font-bold mt-1 leading-none">
                        {{ $pendingDiv }} Perlu Tindakan (To-Do)
                    </p>
                </div>
            </div>
            <div class="flex items-center">
                <span class="text-[10px] font-bold px-2 py-1 {{ $pendingDiv > 0 ? 'bg-amber-100 text-amber-800 border-amber-300' : 'bg-emerald-100 text-emerald-800 border-emerald-300' }} rounded-none border">
                    {{ $pendingDiv > 0 ? 'To-Do' : 'Tuntas' }}
                </span>
            </div>
        </div>
    </div>

    <!-- 4. Visual Workflow Pipeline Tracker (Tahapan Penilaian Section Head -> Dept Head -> Division Head -> Selesai) -->
    <div class="bg-white rounded-none p-5 border border-slate-300 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-slate-200 gap-2">
            <div>
                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wide">
                    Pelacakan Alur Status Dokumen (Section Head &rarr; Dept Head &rarr; Division Head)
                </h3>
                <p class="text-xs text-slate-500">
                    Memantau alur berkas penilaian karya dari tingkat Seksi, tinjauan Departemen, hingga pengesahan akhir Division Head
                </p>
            </div>
            <span class="text-xs font-mono font-bold text-slate-500 bg-slate-100 px-2.5 py-1 rounded-none border border-slate-200 self-start sm:self-auto">
                {{ $divFinalTotalDinilai }}/{{ $totKaryawan }} Telah Disahkan Final
            </span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mt-4">
            <!-- Tahap 1: Penilaian Section Head -->
            <div onclick="filterDivKaryawan('tahap1')"
                 role="button"
                 tabindex="0"
                 title="Klik untuk memfilter dokumen pada tahap Penilaian Section Head"
                 class="p-3.5 border {{ ($divStageCounts['tahap1_section'] ?? 0) > 0 ? 'border-amber-400 bg-amber-50/50 hover:border-amber-500' : 'border-slate-200 bg-slate-50/60' }} rounded-none cursor-pointer transition-all hover:shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">Tahap 1</span>
                        <span class="text-xs font-black {{ ($divStageCounts['tahap1_section'] ?? 0) > 0 ? 'text-amber-800 bg-amber-200' : 'text-slate-600 bg-slate-200' }} px-2 py-0.5 rounded-none">
                            {{ $divStageCounts['tahap1_section'] ?? 0 }} Dokumen
                        </span>
                    </div>
                    <h4 class="text-xs font-bold text-slate-900">1. Penilaian Section Head</h4>
                    <p class="text-[11px] text-slate-600 mt-1 leading-snug">
                        {{ $divStageCounts['belum_dinilai_sh'] ?? 0 }} belum dinilai &bull; {{ $divStageCounts['draft_sh'] ?? 0 }} draft tersimpan.
                    </p>
                </div>
                <div class="mt-2.5 pt-2 border-t border-slate-200/80 flex items-center justify-between text-[11px]">
                    <span class="font-medium text-slate-500">
                        {{ ($divStageCounts['tahap1_section'] ?? 0) > 0 ? 'Proses pengerjaan SH' : 'Selesai diinput SH' }}
                    </span>
                    <span class="font-mono text-slate-400 text-[10px]">Tingkat Seksi</span>
                </div>
            </div>

            <!-- Tahap 2: Tinjauan Dept. Head -->
            <div onclick="filterDivKaryawan('tahap2')"
                 role="button"
                 tabindex="0"
                 title="Klik untuk memfilter dokumen pada tahap Tinjauan Dept. Head"
                 class="p-3.5 border {{ ($divStageCounts['tahap2_dept'] ?? 0) > 0 ? 'border-blue-300 bg-blue-50/50 hover:border-blue-400' : 'border-slate-200 bg-slate-50/60' }} rounded-none cursor-pointer transition-all hover:shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">Tahap 2</span>
                        <span class="text-xs font-black {{ ($divStageCounts['tahap2_dept'] ?? 0) > 0 ? 'text-blue-800 bg-blue-100' : 'text-slate-600 bg-slate-200' }} px-2 py-0.5 rounded-none">
                            {{ $divStageCounts['tahap2_dept'] ?? 0 }} Dokumen
                        </span>
                    </div>
                    <h4 class="text-xs font-bold text-slate-900">2. Tinjauan Dept. Head</h4>
                    <p class="text-[11px] text-slate-600 mt-1 leading-snug">
                        Sedang diverifikasi &amp; ditandatangani oleh Department Head.
                    </p>
                </div>
                <div class="mt-2.5 pt-2 border-t border-slate-200/80 flex items-center justify-between text-[11px]">
                    <span class="font-medium text-slate-500">
                        {{ ($divStageCounts['tahap2_dept'] ?? 0) > 0 ? 'Proses Dept Head' : 'Tuntas diverifikasi' }}
                    </span>
                    <span class="font-mono text-slate-400 text-[10px]">Tingkat Departemen</span>
                </div>
            </div>

            <!-- Tahap 3: Persetujuan Division Head (To-Do Anda) -->
            <div onclick="filterDivKaryawan('tahap3')"
                 role="button"
                 tabindex="0"
                 title="Klik untuk memfilter dokumen yang menunggu Persetujuan Division Head"
                 class="p-3.5 border {{ ($pendingDiv > 0) ? 'border-amber-500 bg-amber-100/60 shadow-xs ring-1 ring-amber-400' : 'border-slate-200 bg-slate-50/60' }} rounded-none cursor-pointer transition-all hover:shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">Tahap 3</span>
                        <span class="text-xs font-black {{ ($pendingDiv > 0) ? 'text-amber-900 bg-amber-300' : 'text-slate-600 bg-slate-200' }} px-2 py-0.5 rounded-none">
                            {{ $pendingDiv }} Dokumen
                        </span>
                    </div>
                    <h4 class="text-xs font-bold text-slate-900">3. Persetujuan Div. Head</h4>
                    <p class="text-[11px] text-slate-600 mt-1 leading-snug">
                        Masuk dari Dept Head. Menunggu peninjauan nilai akhir &amp; TTD Anda.
                    </p>
                </div>
                <div class="mt-2.5 pt-2 border-t border-slate-200/80 flex items-center justify-between text-[11px]">
                    <span class="font-bold {{ ($pendingDiv > 0) ? 'text-amber-800' : 'text-slate-400' }}">
                        {{ ($pendingDiv > 0) ? 'Perlu tindakan Anda' : 'Tidak ada antrean' }}
                    </span>
                    @if($pendingDiv > 0)
                    <a href="{{ route('penilaian.div') }}" onclick="event.stopPropagation()" class="text-[#92302e] font-bold hover:underline inline-flex items-center gap-1">
                        Kerjakan &rarr;
                    </a>
                    @endif
                </div>
            </div>

            <!-- Tahap 4: Selesai & Terarsip -->
            <div onclick="filterDivKaryawan('tahap4')"
                 role="button"
                 tabindex="0"
                 title="Klik untuk memfilter dokumen yang telah Selesai & Terarsip"
                 class="p-3.5 border {{ ($divStageCounts['tahap4_selesai'] ?? 0) > 0 ? 'border-emerald-300 bg-emerald-50/40 hover:border-emerald-400' : 'border-slate-200 bg-slate-50/60' }} rounded-none cursor-pointer transition-all hover:shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">Tahap 4</span>
                        <span class="text-xs font-black {{ ($divStageCounts['tahap4_selesai'] ?? 0) > 0 ? 'text-emerald-800 bg-emerald-100' : 'text-slate-600 bg-slate-200' }} px-2 py-0.5 rounded-none">
                            {{ $divStageCounts['tahap4_selesai'] ?? 0 }} Dokumen
                        </span>
                    </div>
                    <h4 class="text-xs font-bold text-slate-900">4. Selesai &amp; Terarsip</h4>
                    <p class="text-[11px] text-slate-600 mt-1 leading-snug">
                        Penilaian disahkan penuh Division Head, nomor registrasi terbit &amp; PDF terarsip aman.
                    </p>
                </div>
                <div class="mt-2.5 pt-2 border-t border-slate-200/80 flex items-center justify-between text-[11px]">
                    <span class="font-bold text-emerald-700">
                        {{ ($divStageCounts['tahap4_selesai'] ?? 0) > 0 ? 'Arsip final tersedia' : 'Belum selesai final' }}
                    </span>
                    <span class="font-mono text-emerald-600 text-[10px]">Tuntas</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. Rekap Penilaian per Departemen (Left) & Penilaian Mandiri (Right) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-stretch">
        
        <!-- Left: Rekap Penilaian per Departemen (Col 8) -->
        <div class="lg:col-span-8 bg-white rounded-none border border-slate-300 shadow-sm flex flex-col justify-between min-w-0">
            <div>
                <div class="px-5 py-4 border-b border-slate-200 bg-slate-50/70 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">
                            Rekap Penilaian per Departemen
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Progres penyelesaian penilaian karyawan pada tiap departemen dalam divisi
                        </p>
                    </div>
                    <span class="text-xs font-mono font-bold bg-white text-slate-700 px-2.5 py-1 rounded-none border border-slate-300 self-start sm:self-auto whitespace-nowrap">
                        {{ count($rekapDept) }} Departemen
                    </span>
                </div>

                <div class="p-4 sm:p-5">
                    <div class="overflow-x-auto border border-slate-300">
                        <table class="w-full text-left text-xs border-collapse whitespace-nowrap">
                            <thead>
                                <tr class="border-b border-slate-300 text-slate-700 font-bold bg-slate-100 text-[11px] uppercase tracking-wider">
                                    <th class="py-3 px-4 border-r border-slate-300">Department</th>
                                    <th class="py-3 px-3.5 text-center border-r border-slate-300">Total Karyawan</th>
                                    <th class="py-3 px-3.5 text-center border-r border-slate-300">Dinilai Sec Head</th>
                                    <th class="py-3 px-3.5 text-center border-r border-slate-300">Dinilai Dept Head</th>
                                    <th class="py-3 px-3.5 text-center border-r border-slate-300">Disahkan Divisi</th>
                                    <th class="py-3 px-4 text-center border-r border-slate-300">Progres</th>
                                    <th class="py-3 px-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                @forelse($rekapDept as $dept)
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="py-3.5 px-4 font-bold text-slate-900 border-r border-slate-200">
                                        {{ $dept['departemen'] }}
                                    </td>
                                    <td class="py-3.5 px-3.5 text-slate-700 font-semibold text-center border-r border-slate-200 font-mono">
                                        {{ $dept['total_karyawan'] }}
                                    </td>
                                    <td class="py-3.5 px-3.5 text-teal-700 font-bold text-center border-r border-slate-200 font-mono">
                                        {{ $dept['dinilai_sec'] }}
                                    </td>
                                    <td class="py-3.5 px-3.5 text-amber-700 font-bold text-center border-r border-slate-200 font-mono">
                                        {{ $dept['dinilai_dept'] }}
                                    </td>
                                    <td class="py-3.5 px-3.5 text-[#92302e] font-bold text-center border-r border-slate-200 font-mono">
                                        {{ $dept['selesai_div'] }}
                                    </td>
                                    <td class="py-3.5 px-4 text-center border-r border-slate-200">
                                        <div class="flex items-center justify-center gap-2.5">
                                            <div class="w-20 bg-slate-100 rounded-none h-2 overflow-hidden border border-slate-200">
                                                <div class="bg-[#92302e] h-2 rounded-none transition-all duration-300" style="width: {{ $dept['pct_val'] }}%"></div>
                                            </div>
                                            <span class="font-mono font-bold text-slate-800 text-[11px]">{{ $dept['persentase'] }}</span>
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        <a href="{{ route('penilaian.div') }}" 
                                           class="inline-flex items-center justify-center px-3 py-1 bg-slate-100 hover:bg-slate-200 text-[#92302e] border border-slate-300 text-xs font-bold rounded-none transition-colors">
                                            Lihat
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="py-6 text-center text-slate-400">
                                        Tidak ada data departemen yang terdaftar.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="px-5 py-3 bg-slate-50 border-t border-slate-200 flex items-center justify-between text-[11px] text-slate-500">
                <span>Ringkasan pencapaian seluruh departemen di divisi Anda</span>
                <span class="font-mono font-bold text-slate-700">{{ $divFinalTotalDinilai }}/{{ $totKaryawan }} Disahkan</span>
            </div>
        </div>

        <!-- Right: Penilaian Mandiri Division Head (Col 4) -->
        <div class="lg:col-span-4 bg-white rounded-none border-2 border-[#92302e] shadow-sm p-5 flex flex-col justify-between min-w-0">
            <div>
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <span class="w-8 h-8 rounded-none bg-[#fbe8e8] text-[#92302e] flex items-center justify-center font-black text-sm flex-shrink-0 border border-[#92302e]/20">
                            👤
                        </span>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 leading-tight">
                                Penilaian Mandiri Anda
                            </h3>
                            <p class="text-[11px] text-slate-500 font-mono">Form 07-006-DIV</p>
                        </div>
                    </div>
                    @if($selfStatus === 'selesai')
                        <span class="text-[9.5px] font-black uppercase tracking-wider px-2 py-0.5 rounded-none bg-emerald-100 text-emerald-800 border border-emerald-300">
                            ✓ Tuntas
                        </span>
                    @elseif($selfStatus === 'draft')
                        <span class="text-[9.5px] font-black uppercase tracking-wider px-2 py-0.5 rounded-none bg-blue-100 text-blue-800 border border-blue-300">
                            📝 Draft
                        </span>
                    @else
                        <span class="text-[9.5px] font-black uppercase tracking-wider px-2 py-0.5 rounded-none bg-amber-100 text-amber-900 border border-amber-300 animate-pulse">
                            ⚠️ Perlu Diisi
                        </span>
                    @endif
                </div>

                <!-- Division Head Profile Details -->
                <div class="mt-3.5 space-y-1">
                    <h4 class="font-bold text-slate-900 text-sm">
                        {{ $selfKaryawan->nama_lengkap ?? $user->nama }}
                    </h4>
                    <p class="text-xs text-slate-600">
                        {{ $selfKaryawan->jabatan ?? 'Division Head' }} &bull; <span class="font-mono">{{ $user->nrp }}</span>
                    </p>
                    <p class="text-[11px] text-slate-500 font-medium">
                        Golongan: <strong>{{ $selfKaryawan->golongan ?? 'GOLONGAN V' }}</strong> &bull; KJ: <strong>{{ $selfKaryawan->kj ?? 'KJ 5' }}</strong>
                    </p>
                </div>

                <!-- Calculation Score & Grade Box -->
                <div class="mt-4 p-3 bg-slate-50 border border-slate-200 rounded-none">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Nilai Mandiri Anda</span>
                            <span class="text-2xl font-black text-slate-900 leading-none mt-0.5 block font-mono">
                                {{ $selfScoreFormatted }}
                            </span>
                        </div>
                        <div class="text-right">
                            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Predikat Akhir</span>
                            @if($selfGrade !== '-')
                                <span class="inline-block mt-0.5 px-2.5 py-0.5 text-xs font-black rounded-none bg-[#92302e] text-white font-mono shadow-xs">
                                    Grade {{ $selfGrade }}
                                </span>
                            @else
                                <span class="text-xs font-bold text-slate-400 font-mono">-</span>
                            @endif
                        </div>
                    </div>

                    <!-- Breakdown 4 Bobot Komponen Penilaian Karya -->
                    <div class="mt-3 pt-2.5 border-t border-slate-200/80 grid grid-cols-2 gap-2 text-[11px]">
                        <div>
                            <span class="text-slate-500 block text-[10px]">Hasil Kerja (55%):</span>
                            <strong class="text-slate-800 font-mono">{{ isset($selfCalc['groups']['hasil_kerja']['weighted_score']) ? number_format($selfCalc['groups']['hasil_kerja']['weighted_score'], 2) : '-' }}</strong>
                        </div>
                        <div>
                            <span class="text-slate-500 block text-[10px]">Proses Kerja (15%):</span>
                            <strong class="text-slate-800 font-mono">{{ isset($selfCalc['groups']['proses_kerja']['weighted_score']) ? number_format($selfCalc['groups']['proses_kerja']['weighted_score'], 2) : '-' }}</strong>
                        </div>
                        <div>
                            <span class="text-slate-500 block text-[10px]">Man Management (15%):</span>
                            <strong class="text-slate-800 font-mono">{{ isset($selfCalc['groups']['man_management']['weighted_score']) ? number_format($selfCalc['groups']['man_management']['weighted_score'], 2) : '-' }}</strong>
                        </div>
                        <div>
                            <span class="text-slate-500 block text-[10px]">Sikap Kerja (15%):</span>
                            <strong class="text-slate-800 font-mono">{{ isset($selfCalc['groups']['sikap_kerja']['weighted_score']) ? number_format($selfCalc['groups']['sikap_kerja']['weighted_score'], 2) : '-' }}</strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Button: Open & Evaluate Form -->
            <div class="mt-5">
                @if($selfDoc)
                    <a href="{{ route('penilaian.show.divhead', $selfDoc->id) }}" 
                       class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-none font-bold text-xs bg-[#92302e] hover:bg-[#7e2524] text-white shadow-sm transition-all text-center cursor-pointer">
                        <span>✍️ {{ $selfStatus === 'selesai' ? 'Lihat Form Mandiri Saya' : 'Buka & Nilai Form Mandiri Saya' }}</span>
                        <span>&rarr;</span>
                    </a>
                @elseif($selfKaryawan)
                    <form method="POST" action="{{ route('penilaian.buka') }}">
                        @csrf
                        <input type="hidden" name="nrp" value="{{ $selfKaryawan->nrp }}">
                        <button type="submit" 
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-none font-bold text-xs bg-[#92302e] hover:bg-[#7e2524] text-white shadow-sm transition-all text-center cursor-pointer">
                            <span>✍️ Buka & Nilai Form Mandiri Saya</span>
                            <span>&rarr;</span>
                        </button>
                    </form>
                @else
                    <a href="{{ route('penilaian.cari') }}" 
                       class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-none font-bold text-xs bg-[#92302e] hover:bg-[#7e2524] text-white shadow-sm transition-all text-center">
                        <span>✍️ Buka Form Penilaian</span>
                        <span>&rarr;</span>
                    </a>
                @endif
            </div>
        </div>

    </div>

    <!-- 7. Tabel Data Karyawan & Penilaian (Sesuai Desain Department Head - Dropdown Tahap Dokumen & Search) -->
    <div id="div-data-karyawan" class="bg-white rounded-none border border-slate-300 shadow-sm overflow-hidden scroll-mt-20 transition-all duration-300"
         x-data="{ 
             activeTab: 'all',
             searchQuery: '',
             matchesFilter(row) {
                 if (this.activeTab !== 'all') {
                     if (this.activeTab === 'sec_done') {
                         if (!['tahap2', 'tahap3', 'tahap4'].includes(row.stage_key)) return false;
                     } else if (this.activeTab === 'tahap1' && row.stage_key !== 'tahap1') {
                         return false;
                     } else if (this.activeTab === 'tahap2' && row.stage_key !== 'tahap2') {
                         return false;
                     } else if (this.activeTab === 'tahap3' && row.stage_key !== 'tahap3') {
                         return false;
                     } else if (this.activeTab === 'tahap4' && row.stage_key !== 'tahap4') {
                         return false;
                     }
                 }
                 if (this.searchQuery.trim() !== '') {
                     const q = this.searchQuery.toLowerCase();
                     const matchNama = ((row.nama_lengkap || row.nama) || '').toLowerCase().includes(q);
                     const matchNrp = (row.nrp || '').toLowerCase().includes(q);
                     const matchJabatan = (row.jabatan || '').toLowerCase().includes(q);
                     const matchDept = (row.departemen || '').toLowerCase().includes(q);
                     const matchSeksi = (row.seksi || '').toLowerCase().includes(q);
                     if (!matchNama && !matchNrp && !matchJabatan && !matchDept && !matchSeksi) return false;
                 }
                 return true;
             }
         }"
         @set-div-tab.window="activeTab = $event.detail">
        <!-- Table Header & Filter Toolbar -->
        <div class="p-4 border-b border-slate-200">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <h3 class="text-sm font-bold text-slate-900 whitespace-nowrap">
                        Data Karyawan &amp; Penilaian
                    </h3>
                    <span class="text-xs px-2.5 py-0.5 rounded-none bg-slate-100 text-slate-700 font-bold border border-slate-200 whitespace-nowrap">
                        {{ count($divSubordinateRows ?? []) }} Karyawan
                    </span>
                    <span class="sr-only">Daftar Karyawan Divisi</span>
                </div>

                <!-- Toolbar: Dropdown Filter Tahap Dokumen + Live Search Box Sejajar (100% Matching Screenshot) -->
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 w-full md:w-auto">
                    <!-- Dropdown Filter dari kolom Tahap Dokumen -->
                    <div class="w-full sm:w-64">
                        <select x-model="activeTab" 
                                id="divStatusFilter"
                                class="w-full py-1.5 px-3 text-xs font-semibold text-slate-700 rounded-none border border-slate-300 hover:border-slate-400 bg-white focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-xs cursor-pointer">
                            <option value="all">Semua ({{ count($divSubordinateRows ?? []) }})</option>
                            <option value="tahap1">⏳ Section Head ({{ $divStageCounts['tahap1_section'] ?? 0 }})</option>
                            <option value="tahap2">🔔 Department Head ({{ $divStageCounts['tahap2_dept'] ?? 0 }})</option>
                            <option value="tahap3">⏱️ Division Head ({{ $divStageCounts['tahap3_div'] ?? 0 }})</option>
                            <option value="tahap4">✓ Selesai Final ({{ $divStageCounts['tahap4_selesai'] ?? 0 }})</option>
                        </select>
                    </div>

                    <!-- Live Search Box -->
                    <div class="w-full sm:w-72">
                        <div class="relative">
                            <input type="text" 
                                   x-model="searchQuery" 
                                   placeholder="Cari nama, NRP, position, department..." 
                                   class="w-full pl-8 pr-3 py-1.5 text-xs rounded-none border border-slate-300 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] bg-slate-50">
                            <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse border border-slate-300">
                <thead>
                    <tr class="text-slate-700 font-bold border-b border-slate-300 bg-slate-100/90 uppercase tracking-wider text-[11px]">
                        <th class="py-3 px-4 border border-slate-300">Nama Lengkap</th>
                        <th class="py-3 px-4 border border-slate-300">NRP</th>
                        <th class="py-3 px-4 border border-slate-300">Position</th>
                        <th class="py-3 px-4 border border-slate-300">Department</th>
                        <th class="py-3 px-4 border border-slate-300">Golongan</th>
                        <th class="py-3 px-4 border border-slate-300">Start Work</th>
                        <th class="py-3 px-4 border border-slate-300">Tahap Dokumen</th>
                        <th class="py-3 px-4 text-center border border-slate-300">Status</th>
                        <th class="py-3 px-4 text-center border border-slate-300">Skor / Grade</th>
                        <th class="py-3 px-4 text-center w-28 border border-slate-300">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($divSubordinateRows ?? [] as $row)
                    <tr class="hover:bg-slate-50 transition-colors"
                        x-show="matchesFilter({{ json_encode($row) }})">
                        <td class="py-3 px-4 font-semibold text-slate-900 border border-slate-200">
                            <div>
                                <div class="flex items-center gap-1.5">
                                    <span class="block font-bold">{{ $row['nama_lengkap'] }}</span>
                                    @if($row['is_self'] ?? false)
                                        <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-none bg-rose-100 text-[#92302e] border border-rose-200 uppercase">Anda</span>
                                    @endif
                                </div>
                                <span class="text-[10px] text-slate-400 font-normal">{{ $row['seksi'] }}</span>
                            </div>
                        </td>
                        <td class="py-3 px-4 font-mono text-slate-700 font-medium border border-slate-200">
                            {{ $row['nrp'] }}
                        </td>
                        <td class="py-3 px-4 text-slate-600 border border-slate-200">
                            {{ $row['jabatan'] }}
                        </td>
                        <td class="py-3 px-4 text-slate-600 font-medium font-mono text-[11px] border border-slate-200">
                            {{ $row['departemen'] }}
                        </td>
                        <td class="py-3 px-4 text-slate-600 font-medium font-mono text-[11px] border border-slate-200">
                            {{ $row['sub_gol'] ?? $row['golongan'] ?? '-' }}
                        </td>
                        <td class="py-3 px-4 text-slate-600 font-mono text-[11px] border border-slate-200">
                            {{ \App\Services\GoogleSyncService::formatMusashiDate($row['tgl_masuk'] ?? '') }}
                        </td>
                        <td class="py-3 px-4 border border-slate-200">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-none text-[10px] font-bold border {{ $row['badge_class'] }}">
                                {{ $row['stage_label'] }}
                            </span>
                        </td>
                        <td class="py-3 px-4 text-center border border-slate-200">
                            @if(!empty($row['is_evaluated']))
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-none text-[10px] font-bold bg-[#fbe8e8] text-[#92302e] border border-[#92302e]/20">
                                    Sudah dinilai
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-none text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                    Belum dinilai
                                </span>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-center font-mono border border-slate-200">
                            @if(!empty($row['score']) && $row['score'] !== '-' && !empty($row['grade']) && $row['grade'] !== '-')
                                <span class="font-bold text-slate-900">{{ $row['score'] }}</span>
                                <span class="ml-1 text-[10px] font-black px-1.5 py-0.2 rounded-none bg-slate-100 border border-slate-200 text-slate-800">{{ $row['grade'] }}</span>
                            @elseif(!empty($row['grade']) && $row['grade'] !== '-')
                                <span class="text-[10px] font-black px-1.5 py-0.2 rounded-none bg-slate-100 border border-slate-200 text-slate-800">{{ $row['grade'] }}</span>
                            @else
                                <span class="text-slate-400">-</span>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-center border border-slate-200">
                            @if($row['doc_id'])
                                @if($row['is_self'] ?? false)
                                    <a href="{{ route('penilaian.show.divhead', $row['doc_id']) }}" 
                                       class="inline-block px-2.5 py-1 rounded-none text-xs font-bold transition-colors {{ $row['status'] === 'selesai' ? 'bg-slate-100 text-slate-700 hover:bg-slate-200 border border-slate-300' : 'bg-[#92302e] text-white hover:bg-[#7e2524]' }}">
                                        {{ $row['status'] === 'selesai' ? 'Lihat Form Mandiri' : 'Nilai Form Mandiri' }}
                                    </a>
                                @else
                                    <a href="{{ route('penilaian.show.divhead', $row['doc_id']) }}" 
                                       class="inline-block px-2.5 py-1 rounded-none text-xs font-bold transition-colors {{ in_array($row['status'], ['menunggu_div_head']) ? 'bg-[#92302e] text-white hover:bg-[#7e2524]' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 border border-slate-300' }}">
                                        {{ in_array($row['status'], ['menunggu_div_head']) ? 'Review & Sahkan' : 'Lihat' }}
                                    </a>
                                @endif
                            @else
                                <a href="{{ route('penilaian.div') }}" 
                                   class="inline-block px-2.5 py-1 rounded-none text-xs font-bold bg-slate-100 text-slate-700 hover:bg-slate-200 border border-slate-300 transition-colors">
                                    Periksa
                                </a>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="py-8 text-center text-slate-400 border border-slate-200">
                            Tidak ada karyawan terdaftar di lingkup divisi ini.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3 bg-slate-50 border-t border-slate-200 flex items-center justify-end text-xs text-slate-500">
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center gap-1">
                    <span class="w-2 h-2 rounded-none bg-[#92302e]"></span>
                    Sudah Disahkan: <strong class="text-slate-700 ml-0.5">{{ $divFinalTotalDinilai ?? 0 }}</strong>
                </span>
                <span class="inline-flex items-center gap-1">
                    <span class="w-2 h-2 rounded-none bg-amber-500"></span>
                    Menunggu Anda: <strong class="text-slate-700 ml-0.5">{{ $pendingDiv ?? 0 }}</strong>
                </span>
                <span class="inline-flex items-center gap-1">
                    <span class="w-2 h-2 rounded-none bg-rose-400"></span>
                    Belum Selesai: <strong class="text-slate-700 ml-0.5">{{ max(0, $totKaryawan - $divFinalTotalDinilai) }}</strong>
                </span>
            </div>
        </div>
    </div>

    <!-- 8. Panduan Alur Kerja Division Head (Matching Department Head Guide Panel) -->
    <div class="bg-white rounded-none p-5 border border-slate-300 shadow-sm">
        <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider mb-3">
            Panduan &amp; Prosedur Evaluasi Division Head
        </h4>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-xs text-slate-600">
            <div class="p-3 bg-slate-50 border border-slate-200 rounded-none">
                <span class="font-bold text-[#92302e] block mb-1">Langkah 1: Monitoring Alur</span>
                <p>Pantau posisi dokumen seluruh karyawan divisi pada alur pipeline mulai dari Section Head hingga Department Head.</p>
            </div>
            <div class="p-3 bg-slate-50 border border-slate-200 rounded-none">
                <span class="font-bold text-[#92302e] block mb-1">Langkah 2: Tinjau Nilai Masuk</span>
                <p>Periksa berkas penilaian yang telah diverifikasi dan diteruskan oleh Department Head pada antrean To-Do.</p>
            </div>
            <div class="p-3 bg-slate-50 border border-slate-200 rounded-none">
                <span class="font-bold text-[#92302e] block mb-1">Langkah 3: Approval &amp; TTD Digital</span>
                <p>Lakukan pengesahan final tingkat divisi dengan membubuhkan tanda tangan digital secara resmi.</p>
            </div>
            <div class="p-3 bg-slate-50 border border-slate-200 rounded-none">
                <span class="font-bold text-[#92302e] block mb-1">Langkah 4: Rekap &amp; Arsip Final</span>
                <p>Pantau rekapitulasi nilai akhir dan pastikan pencapaian target kuota divisi terdokumentasi di riwayat &amp; laporan.</p>
            </div>
        </div>
    </div>

</div>

@push('scripts')
<script>
    function filterDivKaryawan(tabKey) {
        window.dispatchEvent(new CustomEvent('set-div-tab', { detail: tabKey }));
        const el = document.getElementById('div-data-karyawan');
        if (el) {
            if (typeof window.smoothScrollToElement === 'function') {
                window.smoothScrollToElement(el, 16);
            } else {
                const mainContainer = document.querySelector('main');
                if (mainContainer) {
                    const elRect = el.getBoundingClientRect();
                    const mainRect = mainContainer.getBoundingClientRect();
                    mainContainer.scrollTo({
                        top: Math.max(0, mainContainer.scrollTop + (elRect.top - mainRect.top) - 16),
                        behavior: 'smooth'
                    });
                } else {
                    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
            el.classList.add('ring-2', 'ring-[#92302e]', 'ring-offset-2');
            setTimeout(() => {
                el.classList.remove('ring-2', 'ring-[#92302e]', 'ring-offset-2');
            }, 1500);
        }
    }
</script>
@endpush
