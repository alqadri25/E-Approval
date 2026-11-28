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

    // Actual Grade Distribution across Division (Informational)
    $actS = $divActualGradeCounts['S'] ?? 0;
    $actA = $divActualGradeCounts['A'] ?? 0;
    $actB = $divActualGradeCounts['B'] ?? 0;
    $actC = $divActualGradeCounts['C'] ?? 0;
    $actK = $divActualGradeCounts['K'] ?? 0;
    $sumActual = $actS + $actA + $actB + $actC + $actK;
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
                        Seluruh {{ $totKaryawan }} Penilaian Karyawan Telah Disahkan Final
                    </span>
                </div>
                <p class="text-xs text-emerald-800 mt-1">
                    Semua berkas penilaian karyawan dalam lingkup divisi telah disahkan penuh oleh Division Head dan terarsip aman.
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
                    <p class="text-[11px] text-slate-500 mt-1 leading-none">
                        @if(($divMandiriCount ?? 0) > 0 && ($divAnggotaCount ?? 0) > 0)
                            {{ $divMandiriCount }} Mandiri, {{ $divAnggotaCount }} Bawahan
                        @elseif(($divAnggotaCount ?? 0) > 0)
                            {{ $divAnggotaCount }} Anggota Tim
                        @else
                            Evaluasi Diri Mandiri
                        @endif
                    </p>
                </div>
            </div>
            <div class="flex items-center">
                <span class="text-[10px] font-bold px-2 py-1 bg-slate-100 text-slate-600 rounded-none border border-slate-200">
                    100% Tim
                </span>
            </div>
        </div>

        <!-- 2. Dinilai Section Head -->
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
                    <p class="text-2xl font-black text-slate-900 mt-1.5 leading-none">{{ ($divSectionTotalDinilai ?? 0) + ($divSectionDraftCount ?? 0) }}</p>
                    <p class="text-[11px] text-slate-500 font-medium mt-1 leading-none">
                        {{ $divSectionDraftCount ?? 0 }} draft, {{ $divSectionBelumCount ?? 0 }} belum dinilai
                    </p>
                </div>
            </div>
            <div class="flex items-center">
                <span class="text-[10px] font-bold px-2 py-1 bg-teal-50 text-teal-700 rounded-none border border-teal-200">
                    Nilai SH
                </span>
            </div>
        </div>

        <!-- 3. Dinilai Department Head -->
        <div onclick="filterDivKaryawan('dept_done')" 
             role="button"
             tabindex="0"
             title="Klik untuk memfilter Data Karyawan yang telah dinilai Department Head"
             class="bg-white rounded-none p-4 border border-slate-300 hover:border-emerald-600 hover:shadow-md transition-all flex items-center justify-between group cursor-pointer active:scale-[0.99]">
            <div class="flex items-center gap-3.5">
                <div class="w-10 h-10 rounded-none bg-emerald-50 text-emerald-700 font-black flex items-center justify-center text-sm flex-shrink-0 border border-emerald-300 group-hover:bg-emerald-600 group-hover:text-white transition-colors">
                    ✓
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-semibold leading-none group-hover:text-emerald-700 transition-colors">Dinilai Department Head</p>
                    <p class="text-2xl font-black text-slate-900 mt-1.5 leading-none">{{ ($divDeptTotalDinilai ?? 0) + ($divDeptDraftCount ?? 0) }}</p>
                    <p class="text-[11px] text-slate-500 font-medium mt-1 leading-none">
                        {{ $divDeptDraftCount ?? 0 }} draft, {{ $divDeptBelumCount ?? 0 }} belum dinilai
                    </p>
                </div>
            </div>
            <div class="flex items-center">
                <span class="text-[10px] font-bold px-2 py-1 bg-emerald-50 text-emerald-700 rounded-none border border-emerald-200">
                    Nilai Dept
                </span>
            </div>
        </div>

        <!-- 4. Disahkan Division Head -->
        @php
            $divCardQueueTotal = ($divFinalTotalDinilai ?? 0) + ($pendingDiv ?? 0);
        @endphp
        <div onclick="filterDivKaryawan('div_queue')" 
             role="button"
             tabindex="0"
             title="Klik untuk memfilter Data Karyawan pada Tahap Division Head"
             class="bg-white rounded-none p-4 border border-slate-300 hover:border-blue-600 hover:shadow-md transition-all flex items-center justify-between group cursor-pointer active:scale-[0.99]">
            <div class="flex items-center gap-3.5">
                <div class="w-10 h-10 rounded-none bg-blue-50 text-blue-700 font-black flex items-center justify-center text-sm flex-shrink-0 border border-blue-300 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                    ✈
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-semibold leading-none group-hover:text-blue-700 transition-colors">Disahkan Division Head</p>
                    <p class="text-2xl font-black text-slate-900 mt-1.5 leading-none">{{ $divCardQueueTotal }}</p>
                    <p class="text-[11px] text-slate-500 font-medium mt-1 leading-none">
                        {{ $divFinalTotalDinilai ?? 0 }} sudah dinilai, {{ $pendingDiv ?? 0 }} belum dinilai
                    </p>
                </div>
            </div>
            <div class="flex items-center">
                <span class="text-[10px] font-bold px-2 py-1 {{ $divCardQueueTotal === 0 ? 'bg-slate-100 text-slate-600 border-slate-300' : (($pendingDiv ?? 0) > 0 ? 'bg-amber-100 text-amber-800 border-amber-300' : 'bg-emerald-100 text-emerald-800 border-emerald-300') }} rounded-none border">
                    {{ $divCardQueueTotal === 0 ? 'Belum Ada' : (($pendingDiv ?? 0) > 0 ? 'To-Do' : 'Tuntas') }}
                </span>
            </div>
        </div>
    </div>

    <!-- 4. Visual Workflow Pipeline Tracker (Foreman -> Section Head -> Department Head -> Division Head) -->
    <div class="bg-white rounded-none p-5 border border-slate-300 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-slate-200 gap-2">
            <div>
                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wide">
                    Pelacakan Alur Status Dokumen
                </h3>
                <p class="text-xs text-slate-500">
                    Memantau posisi dokumen seluruh {{ $totKaryawan }} karyawan divisi di setiap tahapan sistem
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mt-4 items-stretch">
            <!-- Tahap 1: Penilaian Foreman -->
            <div onclick="filterDivKaryawan('foreman')"
                 role="button"
                 tabindex="0"
                 title="Klik untuk memfilter dokumen pada tahap Penilaian Foreman"
                 class="p-3.5 border border-slate-200 bg-slate-50/60 hover:border-slate-300 rounded-none cursor-pointer transition-all hover:shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">Tahap 1</span>
                        <span class="text-xs font-black text-slate-700 bg-slate-200 px-2 py-0.5 rounded-none">
                            {{ $divStageCounts['tahap1_foreman'] ?? 0 }} Dokumen
                        </span>
                    </div>
                    <h4 class="text-xs font-bold text-slate-900">1. Penilaian Foreman</h4>
                    <p class="text-[11px] text-slate-600 mt-1 leading-snug">
                        Melakukan Penilaian untuk bawahannya.
                    </p>
                </div>
                <div class="mt-2.5 pt-2 border-t border-slate-200/80 flex items-center justify-between text-[11px]">
                    <span class="font-bold text-emerald-700">Sudah Dinilai: {{ $divStageCounts['foreman_done'] ?? 0 }}</span>
                    <span class="font-bold text-slate-500">Belum Dinilai: {{ $divStageCounts['tahap1_foreman'] ?? 0 }}</span>
                </div>
            </div>

            <!-- Tahap 2: Penilaian Section Head -->
            <div onclick="filterDivKaryawan('section_only')"
                 role="button"
                 tabindex="0"
                 title="Klik untuk memfilter dokumen pada tahap Penilaian Section Head"
                 class="p-3.5 border border-slate-200 bg-slate-50/60 hover:border-slate-300 rounded-none cursor-pointer transition-all hover:shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">Tahap 2</span>
                        <span class="text-xs font-black text-slate-700 bg-slate-200 px-2 py-0.5 rounded-none">
                            {{ $divStageCounts['tahap2_section_only'] ?? 0 }} Dokumen
                        </span>
                    </div>
                    <h4 class="text-xs font-bold text-slate-900">2. Penilaian Section Head</h4>
                    <p class="text-[11px] text-slate-600 mt-1 leading-snug">
                        Melakukan Penilaian untuk bawahannya.
                    </p>
                </div>
                <div class="mt-2.5 pt-2 border-t border-slate-200/80 flex items-center justify-between text-[11px]">
                    <span class="font-bold text-emerald-700">Sudah Dinilai: {{ $divStageCounts['section_done'] ?? 0 }}</span>
                    <span class="font-bold text-slate-500">Belum Dinilai: {{ $divStageCounts['tahap2_section_only'] ?? 0 }}</span>
                </div>
            </div>

            <!-- Tahap 3: Penilaian Department Head -->
            @php
                $divDhPendingCount = $divStageCounts['tahap2_dept'] ?? 0;
            @endphp
            <div onclick="filterDivKaryawan('tahap2')"
                 role="button"
                 tabindex="0"
                 title="Klik untuk memfilter dokumen pada tahap Penilaian Department Head"
                 class="p-3.5 border border-slate-200 bg-slate-50/60 hover:border-slate-300 rounded-none cursor-pointer transition-all hover:shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">Tahap 3</span>
                        <span class="text-xs font-black text-slate-700 bg-slate-200 px-2 py-0.5 rounded-none">
                            {{ $divDhPendingCount }} Dokumen
                        </span>
                    </div>
                    <h4 class="text-xs font-bold text-slate-900">3. Penilaian Department Head</h4>
                    <p class="text-[11px] text-slate-600 mt-1 leading-snug">
                        Telah disetujui Section Head, menunggu persetujuan Department Head.
                    </p>
                </div>
                <div class="mt-2.5 pt-2 border-t border-slate-200/80 flex items-center justify-between text-[11px]">
                    <span class="font-bold text-emerald-700">Sudah Dinilai: {{ $divStageCounts['dept_done'] ?? 0 }}</span>
                    <span class="font-bold text-slate-500">Belum Dinilai: {{ $divDhPendingCount }}</span>
                </div>
            </div>

            <!-- Tahap 4: Penilaian Division Head (Highlighted Amber for Division Head) -->
            @php
                $divTahap4Count = $divStageCounts['tahap3_div'] ?? 0;
                $divTahap4Draft = $divStageCounts['draft_div'] ?? 0;
                $divTahap4Belum = max(0, $divTahap4Count - $divTahap4Draft);
            @endphp
            <div onclick="filterDivKaryawan('tahap3')"
                 role="button"
                 tabindex="0"
                 title="Klik untuk memfilter dokumen yang menunggu Persetujuan Division Head"
                 class="p-3.5 border {{ $divTahap4Count > 0 ? 'border-amber-400 bg-amber-50/50 hover:border-amber-500 ring-1 ring-amber-300' : 'border-slate-200 bg-slate-50/60' }} rounded-none cursor-pointer transition-all hover:shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">Tahap 4</span>
                        <span class="text-xs font-black {{ $divTahap4Count > 0 ? 'text-amber-800 bg-amber-200' : 'text-slate-600 bg-slate-200' }} px-2 py-0.5 rounded-none">
                            {{ $divTahap4Count }} Dokumen
                        </span>
                    </div>
                    <h4 class="text-xs font-bold text-slate-900">4. Penilaian Division Head</h4>
                    <p class="text-[11px] text-slate-600 mt-1 leading-snug">
                        Telah disetujui Department Head, menunggu persetujuan Division Head.
                    </p>
                    <p class="text-[11px] text-slate-600 mt-1 leading-snug">
                        {{ $divTahap4Belum }} belum dinilai &bull; {{ $divTahap4Draft }} draft tersimpan.
                    </p>
                </div>
                <div class="mt-2.5 pt-2 border-t border-slate-200/80 flex items-center justify-between text-[11px]">
                    <span class="font-bold {{ $divTahap4Count > 0 ? 'text-amber-700' : 'text-slate-500' }}">
                        {{ $divTahap4Count > 0 ? 'Perlu tindakan Anda' : 'Tidak ada antrean' }}
                    </span>
                    @if($divTahap4Count > 0)
                    <a href="{{ route('penilaian.div') }}" onclick="event.stopPropagation()" class="text-[#92302e] font-bold hover:underline inline-flex items-center gap-1">
                        Kerjakan &rarr;
                    </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- 5. Target Penilaian Divisi (Compact Summary Table - Form 07-006 Semua Divisi) -->
    <div class="bg-white rounded-none border border-slate-300 shadow-sm flex flex-col justify-between min-w-0">
        <div>
            <div class="px-5 py-3.5 border-b border-slate-200 bg-slate-50/70 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                <div>
                    <h3 class="text-base sm:text-lg font-extrabold text-slate-900">
                        Target Penilaian Divisi
                    </h3>
                    <p class="text-xs sm:text-sm text-slate-600 mt-0.5">
                        Target kuota semua divisi (Form 07-006) dari Admin / Super Admin untuk kategori nilai S, A, B, C, K &amp; akumulasi sudah dinilai semua divisi
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    <span class="text-xs sm:text-sm font-extrabold px-3 py-1.5 rounded-none bg-blue-50 text-blue-800 border border-blue-200">
                        Target 07-006: {{ $sumDivTargets }} Orang
                    </span>
                    <span class="text-xs sm:text-sm font-extrabold px-3 py-1.5 rounded-none bg-emerald-50 text-emerald-800 border border-emerald-200">
                        Sudah Dinilai: {{ $sumActual }} Orang
                    </span>
                </div>
            </div>

            <div class="p-4">
                <div class="overflow-x-auto border border-slate-300">
                    <table class="w-full text-left text-sm border-collapse whitespace-nowrap">
                        <thead>
                            <tr class="border-b border-slate-300 text-slate-700 font-extrabold bg-slate-100 text-xs sm:text-sm uppercase tracking-wider">
                                <th class="py-3 px-4 border-r border-slate-300">Kategori Nilai</th>
                                <th class="py-3 px-4 text-center border-r border-slate-300 w-44">Target Kuota</th>
                                <th class="py-3 px-4 text-center w-44">Sudah Dinilai</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            @php
                                $divGradeRows = [
                                    ['code' => 'S', 'label' => 'Grade S', 'badge' => 'bg-purple-100 text-purple-900 border-purple-300', 'actText' => 'text-purple-700', 'subText' => 'text-purple-900/70', 'target' => $targetS, 'actual' => $actS],
                                    ['code' => 'A', 'label' => 'Grade A', 'badge' => 'bg-emerald-100 text-emerald-900 border-emerald-300', 'actText' => 'text-emerald-700', 'subText' => 'text-emerald-900/70', 'target' => $targetA, 'actual' => $actA],
                                    ['code' => 'B', 'label' => 'Grade B', 'badge' => 'bg-blue-100 text-blue-900 border-blue-300', 'actText' => 'text-blue-700', 'subText' => 'text-blue-900/70', 'target' => $targetB, 'actual' => $actB],
                                    ['code' => 'C', 'label' => 'Grade C', 'badge' => 'bg-amber-100 text-amber-900 border-amber-300', 'actText' => 'text-amber-700', 'subText' => 'text-amber-900/70', 'target' => $targetC, 'actual' => $actC],
                                    ['code' => 'K', 'label' => 'Grade K', 'badge' => 'bg-rose-100 text-rose-900 border-rose-300', 'actText' => 'text-rose-700', 'subText' => 'text-rose-900/70', 'target' => $targetK, 'actual' => $actK],
                                ];
                            @endphp
                            @foreach($divGradeRows as $gr)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="py-3 px-4 font-bold border-r border-slate-200">
                                    <div class="flex items-center gap-2.5">
                                        <span class="w-8 h-8 rounded-none border flex items-center justify-center font-black text-sm font-mono {{ $gr['badge'] }}">
                                            {{ $gr['code'] }}
                                        </span>
                                        <span class="text-sm sm:text-base font-bold text-slate-900">{{ $gr['label'] }}</span>
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-slate-900 font-extrabold text-center border-r border-slate-200 font-mono text-base sm:text-lg">
                                    {{ $gr['target'] }} <span class="text-xs sm:text-sm text-slate-500 font-medium">Orang</span>
                                </td>
                                <td class="py-3 px-4 {{ $gr['actText'] }} font-black text-center font-mono text-base sm:text-lg">
                                    {{ $gr['actual'] }} <span class="text-xs sm:text-sm {{ $gr['subText'] }} font-medium">Orang</span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-slate-100/90 font-bold border-t-2 border-slate-300 text-slate-900">
                            <tr>
                                <td class="py-3.5 px-4 border-r border-slate-300 uppercase tracking-wider text-xs sm:text-sm font-black">
                                    TOTAL PENILAIAN (FORM 07-006)
                                </td>
                                <td class="py-3.5 px-4 text-center border-r border-slate-300 font-mono text-base sm:text-lg font-black text-slate-900">
                                    {{ $sumDivTargets }} <span class="text-xs sm:text-sm text-slate-500 font-semibold">Orang</span>
                                </td>
                                <td class="py-3.5 px-4 text-center font-mono text-base sm:text-lg font-black text-[#92302e]">
                                    {{ $sumActual }} <span class="text-xs sm:text-sm text-[#92302e]/70 font-semibold">Orang</span>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
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
                     } else if (this.activeTab === 'dept_done' || this.activeTab === 'div_queue') {
                         if (!['tahap3', 'tahap4'].includes(row.stage_key)) return false;
                     } else if (this.activeTab === 'foreman') {
                         if (row.status !== 'menunggu_foreman') return false;
                     } else if (this.activeTab === 'section_only') {
                         if (row.status !== 'menunggu_section_head') return false;
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
                                        {{ $row['status'] === 'selesai' ? 'Lihat Form Mandiri' : 'Nilai' }}
                                    </a>
                                @else
                                    <a href="{{ route('penilaian.show.divhead', $row['doc_id']) }}" 
                                       class="inline-block px-2.5 py-1 rounded-none text-xs font-bold transition-colors {{ in_array($row['status'], ['menunggu_div_head']) ? 'bg-[#92302e] text-white hover:bg-[#7e2524]' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 border border-slate-300' }}">
                                        {{ in_array($row['status'], ['menunggu_div_head']) ? 'Nilai' : 'Lihat' }}
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
