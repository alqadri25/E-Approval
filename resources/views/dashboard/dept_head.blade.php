<!-- ============================================================== -->
<!-- DEPARTMENT HEAD DASHBOARD VIEW                                 -->
<!-- Desain & Struktur Identik dengan Mockup Dashboard Musashi      -->
<!-- Khusus Department Head (Tingkat Approval / Posisi Tertinggi)  -->
<!-- ============================================================== -->
<div class="space-y-5" x-data="{ 
    activeTab: 'all', 
    searchQuery: '',
    scrollToSection(id, tab = null) {
        if (tab) {
            this.activeTab = tab;
        }
        this.$nextTick(() => {
            if (typeof window.smoothScrollToElement === 'function') {
                window.smoothScrollToElement(id, 16);
            } else {
                const el = document.getElementById(id);
                if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    },
    matchesFilter(sub) {
        // Tab filter
        if (this.activeTab === 'all') { /* continue to search filter */ }
        else if (this.activeTab === 'belum') {
            if (sub.is_evaluated || sub.action_type === 'sudah_dikirim' || sub.action_type === 'selesai' || sub.status === 'menunggu_div_head') return false;
        }
        else if (this.activeTab === 'dinilai' || this.activeTab === 'evaluated') {
            if (!sub.is_evaluated && sub.action_type !== 'sudah_dikirim' && sub.action_type !== 'selesai' && sub.status !== 'menunggu_div_head') return false;
        }
        else if (this.activeTab === 'done') {
            if (sub.action_type !== 'sudah_dikirim' && sub.action_type !== 'selesai' && sub.status !== 'menunggu_div_head' && sub.stage_key !== 'tahap3' && sub.stage_key !== 'tahap4') return false;
        }
        else if (this.activeTab === 'todo' && sub.action_type !== 'todo') return false;
        else if (this.activeTab === 'foreman' && sub.action_type !== 'menunggu_foreman') return false;
        else if (this.activeTab === 'foreman_done' && !['tahap2', 'tahap3', 'tahap4'].includes(sub.stage_key)) return false;
        else if (this.activeTab === 'section' && !(sub.action_type === 'menunggu_section' || (sub.stage_key === 'tahap1' && sub.action_type !== 'menunggu_foreman'))) return false;
        else if (this.activeTab === 'tahap1' && sub.stage_key !== 'tahap1') return false;
        else if (this.activeTab === 'tahap2' && sub.stage_key !== 'tahap2') return false;
        else if (this.activeTab === 'tahap3' && sub.stage_key !== 'tahap3') return false;
        else if (this.activeTab === 'tahap4' && sub.stage_key !== 'tahap4') return false;
        
        // Search filter
        if (this.searchQuery.trim() !== '') {
            const q = this.searchQuery.toLowerCase();
            const nameMatch = ((sub.nama_lengkap || sub.nama) || '').toLowerCase().includes(q);
            const nrpMatch = (sub.nrp || '').toLowerCase().includes(q);
            const jabMatch = (sub.jabatan || '').toLowerCase().includes(q);
            const seksiMatch = (sub.seksi || '').toLowerCase().includes(q);
            return nameMatch || nrpMatch || jabMatch || seksiMatch;
        }
        return true;
    }
}">

    <!-- 1. Top Header Card (Merah Musashi Gradient & Rounded-None) -->
    <div class="bg-gradient-to-r from-[#962226] via-[#92302e] to-[#781b1f] text-white rounded-none p-5 sm:p-6 border border-[#781b1f] shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight">
                Dashboard
            </h1>
            <span class="sr-only">Dashboard Department Head</span>
            <p class="text-xs text-red-100 font-medium mt-1">
                Selamat Datang {{ $user->nama }}{{ $user->isSecretary() ? ' • SECRETARY' : '' }}
            </p>
        </div>

        <div class="flex items-center gap-2.5">
            <a href="{{ $user->isSecretary() ? url('/secretary') : route('penilaian.dept') }}" 
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-none bg-white hover:bg-slate-100 text-[#92302e] font-bold text-xs shadow-sm transition-colors border border-white">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <span>Data Penilaian</span>
            </a>
        </div>
    </div>

    <!-- 2. Live Action Banner: Yang Sudah Dilakukan & Yang Mau Dilakukan -->
    @php
        $isSecretary = $user->isSecretary();
        $myDocRow = null;
        if (!empty($deptSubordinateRows)) {
            foreach ($deptSubordinateRows as $r) {
                if (!empty($user->nrp) && strcasecmp((string)($r['nrp'] ?? ''), (string)$user->nrp) === 0) {
                    $myDocRow = $r;
                    break;
                }
            }
        }
        $isSecSelfEvaluated = $myDocRow ? (in_array($myDocRow['status'] ?? '', ['menunggu_div_head', 'selesai']) || !empty($myDocRow['is_evaluated'])) : false;
    @endphp

    @if($isSecretary)
        @if(!$isSecSelfEvaluated)
            <!-- Urgent Action Banner Khusus Secretary: Evaluasi Mandiri Belum Diisi -->
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
                                Penilaian Mandiri Belum Diisi / Ditandatangani
                            </span>
                        </div>
                        <p class="text-xs text-amber-900 mt-1">
                            Dokumen penilaian mandiri Anda belum diisi atau ditandatangani digital. Silakan lakukan evaluasi mandiri sekarang untuk diteruskan ke Division Head.
                        </p>
                    </div>
                </div>
                <a href="{{ $myDocRow && !empty($myDocRow['doc_id']) ? route('penilaian.show.secretary', $myDocRow['doc_id']) : url('/secretary') }}" 
                   class="px-4 py-2 rounded-none bg-[#92302e] hover:bg-[#7e2524] text-white font-bold text-xs shadow-sm whitespace-nowrap self-start md:self-center transition-colors">
                    Buka Form Penilaian Mandiri &rarr;
                </a>
            </div>
        @else
            <!-- All Done Success Banner Khusus Secretary: Sudah Terkirim ke Div Head -->
            <div class="bg-emerald-50 border-l-4 border-emerald-600 p-4 border-y border-r border-emerald-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-none bg-emerald-600 text-white flex items-center justify-center font-bold text-sm flex-shrink-0">
                        ✓
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 bg-emerald-200 text-emerald-900 rounded-none">
                                Tugas Penilaian Mandiri Tuntas 100%
                            </span>
                            <span class="text-xs font-bold text-emerald-950">
                                Penilaian Mandiri Anda Telah Ditandatangani &amp; Diteruskan ke Division Head
                            </span>
                        </div>
                        <p class="text-xs text-emerald-800 mt-1">
                            Dokumen penilaian mandiri Anda saat ini dalam proses peninjauan &amp; pengesahan akhir oleh Division Head.
                        </p>
                    </div>
                </div>
                <a href="{{ route('penilaian.riwayat') }}" 
                   class="px-3.5 py-1.5 rounded-none bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs shadow-sm whitespace-nowrap self-start md:self-center">
                    Lihat Riwayat &amp; Rekap &rarr;
                </a>
            </div>
        @endif
    @elseif(($deptNeedActionCount ?? 0) > 0)
        <!-- Urgent Action Banner: Dokumen Menunggu Persetujuan Department Head (To-Do) -->
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
                            {{ $deptNeedActionCount }} Dokumen Penilaian Menunggu Persetujuan Final
                        </span>
                    </div>
                    <p class="text-xs text-amber-900 mt-1">
                        Mohon segera lakukan peninjauan nilai &amp; bubuhkan tanda tangan digital Anda untuk pengesahan final.
                    </p>
                </div>
            </div>
            <a href="{{ route('penilaian.dept') }}" 
               class="px-4 py-2 rounded-none bg-[#92302e] hover:bg-[#7e2524] text-white font-bold text-xs shadow-sm whitespace-nowrap self-start md:self-center transition-colors">
                Tinjau Dokumen Masuk &rarr;
            </a>
        </div>
    @elseif(($deptWaitingSectionCount ?? 0) > 0 || ($deptStageCounts['tahap1_foreman'] ?? 0) > 0)
        <!-- Info Banner: Sedang Diproses Foreman / Section Head -->
        <div class="bg-blue-50/70 border-l-4 border-blue-500 p-4 border-y border-r border-blue-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-start gap-3.5">
                <div class="w-8 h-8 rounded-none bg-blue-600 text-white flex items-center justify-center font-bold text-sm flex-shrink-0">
                    ℹ
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 bg-blue-200 text-blue-900 rounded-none">
                            Proses Supervisor (Foreman &amp; Section Head)
                        </span>
                        <span class="text-xs text-blue-950 font-bold">
                            {{ $deptStageCounts['tahap2_section'] ?? 0 }} Dokumen di Section Head, {{ $deptStageCounts['tahap1_foreman'] ?? 0 }} Dokumen di Foreman
                        </span>
                    </div>
                    <p class="text-xs text-blue-800 mt-1">
                        Dokumen penilaian sedang diproses oleh Foreman dan Section Head, dan akan otomatis masuk ke antrean approval Department Head setelah disetujui Section Head.
                    </p>
                </div>
            </div>
            <a href="{{ route('penilaian.dept') }}" 
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
                            Tugas Department Head Tuntas 100%
                        </span>
                        <span class="text-xs font-bold text-emerald-950">
                            Seluruh {{ $deptTotalKaryawan }} Penilaian Karyawan Telah Disahkan / Disetujui Penuh
                        </span>
                    </div>
                    <p class="text-xs text-emerald-800 mt-1">
                        Semua berkas penilaian karyawan departemen telah diverifikasi dan disetujui resmi sebagai tingkat persetujuan tertinggi.
                    </p>
                </div>
            </div>
            <a href="{{ route('penilaian.riwayat') }}" 
               class="px-3.5 py-1.5 rounded-none bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs shadow-sm whitespace-nowrap self-start md:self-center">
                Lihat Riwayat &amp; Rekap &rarr;
            </a>
        </div>
    @endif

    @php
        // Target distribution
        $targetS = $deptTargetCounts['S'] ?? 0;
        $targetA = $deptTargetCounts['A'] ?? 0;
        $targetB = $deptTargetCounts['B'] ?? 0;
        $targetC = $deptTargetCounts['C'] ?? 0;
        $targetK = $deptTargetCounts['K'] ?? 0;
        $effectiveTotalTarget = isset($totalTargetDept) ? (int)$totalTargetDept : ($targetS + $targetA + $targetB + $targetC + $targetK);

        if (!isset($deptMandiriCount) || !isset($deptAnggotaCount)) {
            $deptMandiriCount = 0;
            $deptAnggotaCount = 0;
            if (!empty($deptSubordinateRows)) {
                foreach ($deptSubordinateRows as $r) {
                    if (!empty($r['is_self'])) {
                        $deptMandiriCount++;
                    } else {
                        $deptAnggotaCount++;
                    }
                }
            } elseif (!empty($deptSubordinates)) {
                foreach ($deptSubordinates as $s) {
                    if (!empty($user->nrp) && strcasecmp((string)($s['nrp'] ?? ''), (string)$user->nrp) === 0) {
                        $deptMandiriCount++;
                    } else {
                        $deptAnggotaCount++;
                    }
                }
            }
        }

        $secTotal = $deptTotalKaryawan;
        $secSudah = 0;
        $secBelum = 0;
        $secDivSudah = 0;
        $secDivBelum = 0;
        if (!empty($deptSubordinateRows)) {
            foreach ($deptSubordinateRows as $r) {
                $st = $r['status'] ?? '';
                $isEval = in_array($st, ['menunggu_div_head', 'selesai']) || !empty($r['is_evaluated']);
                if ($isEval) {
                    $secSudah++;
                } else {
                    $secBelum++;
                }

                // Status evaluasi Division Head
                if ($st === 'selesai') {
                    $secDivSudah++;
                } elseif ($st === 'menunggu_div_head') {
                    $secDivBelum++;
                }
            }
        }
        $secDivTotal = $secDivSudah + $secDivBelum;

        $deptForemanPendingCount = $deptStageCounts['tahap1_foreman'] ?? 0;
        $deptSectionPendingCount = ($hasAnyForeman ?? false)
            ? ($deptStageCounts['tahap2_section'] ?? 0)
            : ($deptStageCounts['tahap1_section'] ?? 0);
        $deptDivDoneStageCount = $deptDoneCount ?? (($deptStageCounts['tahap3_div'] ?? 0) + ($deptStageCounts['tahap4_selesai'] ?? 0));
    @endphp

    @if($user->isSecretary())
    <!-- 3. Key Stat Cards Khusus Secretary (4 Card Kecil Sesuai Alur Secretary) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Card 1: Total Karyawan -->
        <div @click="scrollToSection('dept-data-karyawan', 'all')" 
             class="bg-white rounded-none p-4 border border-slate-300 hover:border-[#92302e] hover:shadow-md cursor-pointer transition-all flex flex-col justify-between group"
             title="Klik untuk melihat seluruh karyawan di tabel">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-none bg-[#fbe8e8] text-[#92302e] font-black flex items-center justify-center text-sm flex-shrink-0 border border-[#92302e]/20 group-hover:bg-[#92302e] group-hover:text-white transition-colors">
                        K
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 font-semibold leading-none group-hover:text-[#92302e] transition-colors">Total Karyawan</p>
                        <p class="text-2xl font-black text-slate-900 mt-1.5 leading-none">{{ $secTotal }}</p>
                        <p class="text-[11px] text-slate-500 mt-1 leading-none">Anggota Secretary</p>
                    </div>
                </div>
                <span class="text-[10px] font-bold px-2 py-1 bg-slate-100 text-slate-600 rounded-none border border-slate-200">
                    100% Tim
                </span>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-200 flex items-center justify-between text-[11px]">
                <span class="text-slate-600 font-medium">Sudah Dinilai: <strong class="text-emerald-700 font-bold">{{ $secSudah }}</strong> • Belum Dinilai: <strong class="{{ $secBelum > 0 ? 'text-rose-600' : 'text-slate-500' }} font-bold">{{ $secBelum }}</strong></span>
                <span class="text-[10px] font-mono text-slate-400">Total: {{ $secTotal }}</span>
            </div>
        </div>

        <!-- Card 2: Belum Dinilai -->
        <div @click="scrollToSection('dept-data-karyawan', 'belum')" 
             class="bg-white rounded-none p-4 border {{ $secBelum > 0 ? 'border-amber-400 bg-amber-50/30 ring-1 ring-amber-300' : 'border-slate-300' }} hover:border-amber-500 hover:shadow-md cursor-pointer transition-all flex flex-col justify-between group"
             title="Klik untuk melihat dokumen yang belum dinilai">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-none {{ $secBelum > 0 ? 'bg-amber-100 text-amber-800 border-amber-300' : 'bg-slate-100 text-slate-600 border-slate-200' }} font-black flex items-center justify-center text-sm flex-shrink-0 border group-hover:bg-amber-500 group-hover:text-white transition-colors">
                        ⏳
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 font-semibold leading-none group-hover:text-amber-700 transition-colors">Belum Dinilai</p>
                        <p class="text-2xl font-black text-slate-900 mt-1.5 leading-none">{{ $secBelum }}</p>
                        <p class="text-[11px] text-slate-500 mt-1 leading-none">Menunggu Evaluasi Mandiri</p>
                    </div>
                </div>
                <span class="text-[10px] font-bold px-2 py-1 {{ $secBelum > 0 ? 'bg-amber-100 text-amber-800 border-amber-200' : 'bg-slate-100 text-slate-600 border-slate-200' }} rounded-none border">
                    Belum Dinilai
                </span>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-200 flex items-center justify-between text-[11px]">
                <span class="font-medium {{ $secBelum > 0 ? 'text-amber-700 font-bold' : 'text-slate-500' }}">
                    {{ $secBelum > 0 ? "Belum dinilai: {$secBelum} • Perlu tindakan Anda" : "Belum dinilai: 0 • Tidak ada antrean" }}
                </span>
                <span class="text-[10px] font-mono text-slate-400">{{ $secBelum > 0 ? 'To-Do' : 'Tuntas' }}</span>
            </div>
        </div>

        <!-- Card 3: Penilaian Anda (Menggantikan Tahap 3) -->
        <div @click="scrollToSection('dept-data-karyawan', 'dinilai')" 
             class="bg-white rounded-none p-4 border {{ $secSudah > 0 ? 'border-emerald-400 bg-emerald-50/30 ring-1 ring-emerald-300' : 'border-slate-300' }} hover:border-emerald-500 hover:shadow-md cursor-pointer transition-all flex flex-col justify-between group"
             title="Klik untuk melihat dokumen evaluasi mandiri Anda">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-none {{ $secSudah > 0 ? 'bg-emerald-100 text-emerald-800 border-emerald-300' : 'bg-slate-100 text-slate-600 border-slate-200' }} font-black flex items-center justify-center text-sm flex-shrink-0 border group-hover:bg-emerald-600 group-hover:text-white transition-colors">
                        ✓
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 font-semibold leading-none group-hover:text-emerald-700 transition-colors">Penilaian Anda</p>
                        <p class="text-2xl font-black text-slate-900 mt-1.5 leading-none">{{ $secSudah }}</p>
                        <p class="text-[11px] text-slate-500 mt-1 leading-none">Evaluasi Mandiri Secretary</p>
                    </div>
                </div>
                <span class="text-[10px] font-bold px-2 py-1 {{ $secSudah > 0 ? 'bg-emerald-100 text-emerald-800 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200' }} rounded-none border">
                    {{ $secSudah > 0 ? 'Sudah Dinilai' : 'Belum Dinilai' }}
                </span>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-200 flex items-center justify-between text-[11px]">
                <span class="font-bold {{ $secSudah > 0 ? 'text-emerald-700' : 'text-slate-500' }}">
                    {{ $secSudah > 0 ? 'Sudah dinilai & TTD digital' : 'Belum dinilai' }}
                </span>
                @if($secBelum > 0)
                    <a href="{{ $myDocRow && !empty($myDocRow['doc_id']) ? route('penilaian.show.secretary', $myDocRow['doc_id']) : url('/secretary') }}" @click.stop class="text-[#92302e] font-bold hover:underline inline-flex items-center gap-1">
                        Kerjakan &rarr;
                    </a>
                @else
                    <span class="text-[10px] font-mono text-emerald-600 font-semibold">Tuntas</span>
                @endif
            </div>
        </div>

        <!-- Card 4: Terkirim ke Division Head (Menggantikan Tahap 4) -->
        <div @click="scrollToSection('dept-data-karyawan', 'done')" 
             class="bg-white rounded-none p-4 border {{ $secDivSudah > 0 ? 'border-emerald-400 bg-emerald-50/30 ring-1 ring-emerald-300' : ($secDivBelum > 0 ? 'border-blue-400 bg-blue-50/30 ring-1 ring-blue-300' : 'border-slate-300') }} hover:border-blue-500 hover:shadow-md cursor-pointer transition-all flex flex-col justify-between group"
             title="Klik untuk melihat dokumen yang telah diteruskan ke Division Head">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-none {{ $secDivSudah > 0 ? 'bg-emerald-100 text-emerald-800 border-emerald-300' : ($secDivBelum > 0 ? 'bg-blue-100 text-blue-800 border-blue-300' : 'bg-slate-100 text-slate-600 border-slate-200') }} font-black flex items-center justify-center text-sm flex-shrink-0 border group-hover:bg-blue-600 group-hover:text-white transition-colors">
                        ✈
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 font-semibold leading-none group-hover:text-blue-700 transition-colors">Terkirim ke Division Head</p>
                        <p class="text-2xl font-black text-slate-900 mt-1.5 leading-none">{{ $secDivTotal }}</p>
                        <p class="text-[11px] text-slate-500 mt-1 leading-none">Pengesahan Akhir Division Head</p>
                    </div>
                </div>
                <span class="text-[10px] font-bold px-2 py-1 {{ $secDivSudah > 0 ? 'bg-emerald-100 text-emerald-800 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200' }} rounded-none border">
                    {{ $secDivSudah > 0 ? 'Sudah Dinilai' : 'Belum Dinilai' }}
                </span>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-200 flex items-center justify-between text-[11px]">
                <span class="text-slate-600 font-medium">Sudah Dinilai: <strong class="text-emerald-700 font-bold">{{ $secDivSudah }}</strong> • Belum Dinilai: <strong class="{{ $secDivBelum > 0 ? 'text-amber-600' : 'text-slate-500' }} font-bold">{{ $secDivBelum }}</strong></span>
                @if($secDivSudah > 0)
                    <span class="text-[10px] font-mono text-emerald-600 font-semibold">Selesai</span>
                @elseif($secDivBelum > 0)
                    <span class="text-[10px] font-mono text-blue-600 font-semibold">Review Div Head</span>
                @else
                    <span class="text-[10px] font-mono text-slate-400 font-semibold">Menunggu</span>
                @endif
            </div>
        </div>
    </div>
    @else
    <!-- 3. Key Stat Cards in a Row (3 Columns - Khusus Department Head) -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <!-- 1. Total Karyawan (Klik: Scroll ke tabel filter semua) -->
        <div @click="scrollToSection('dept-data-karyawan', 'all')" 
             class="bg-white rounded-none p-4 border border-slate-300 hover:border-[#92302e] hover:shadow-md cursor-pointer transition-all flex items-center justify-between group"
             title="Klik untuk melihat seluruh karyawan bawahan di tabel">
            <div class="flex items-center gap-3.5">
                <div class="w-10 h-10 rounded-none bg-[#fbe8e8] text-[#92302e] font-black flex items-center justify-center text-sm flex-shrink-0 border border-[#92302e]/20 group-hover:bg-[#92302e] group-hover:text-white transition-colors">
                    K
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-semibold leading-none group-hover:text-[#92302e] transition-colors">Total Karyawan</p>
                    <p class="text-2xl font-black text-slate-900 mt-1.5 leading-none">{{ $deptTotalKaryawan }}</p>
                    <p class="text-[11px] text-slate-500 mt-1 leading-none">
                        @if(($deptMandiriCount ?? 0) > 0 && ($deptAnggotaCount ?? 0) > 0)
                            {{ $deptMandiriCount }} Mandiri, {{ $deptAnggotaCount }} Bawahan
                        @elseif(($deptAnggotaCount ?? 0) > 0)
                            {{ $deptAnggotaCount }} Anggota Tim
                        @else
                            Evaluasi Diri Mandiri
                        @endif
                    </p>
                </div>
            </div>
            <span class="text-[10px] font-bold px-2 py-1 bg-slate-100 text-slate-600 rounded-none border border-slate-200">
                100% Tim
            </span>
        </div>

        <!-- 2. Total Dinilai (Klik: Scroll ke tabel filter sudah dinilai) -->
        <div @click="scrollToSection('dept-data-karyawan', 'evaluated')" 
             class="bg-white rounded-none p-4 border border-slate-300 hover:border-emerald-600 hover:shadow-md cursor-pointer transition-all flex items-center justify-between group"
             title="Klik untuk melihat karyawan yang sudah dinilai (draft & terkirim) di tabel">
            <div class="flex items-center gap-3.5">
                <div class="w-10 h-10 rounded-none bg-emerald-50 text-emerald-700 font-black flex items-center justify-center text-sm flex-shrink-0 border border-emerald-300 group-hover:bg-emerald-600 group-hover:text-white transition-colors">
                    ✓
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-semibold leading-none group-hover:text-emerald-700 transition-colors">Total Dinilai</p>
                    <p class="text-2xl font-black text-slate-900 mt-1.5 leading-none">{{ $deptCardDinilaiCount ?? 0 }}</p>
                    <p class="text-[11px] text-slate-500 font-medium mt-1 leading-none">
                        {{ $deptCardDraftCount ?? 0 }} draft, {{ $deptCardBelumCount ?? $deptTotalKaryawan }} belum dinilai
                    </p>
                </div>
            </div>
            <span class="text-[10px] font-bold px-2 py-1 bg-emerald-50 text-emerald-700 rounded-none border border-emerald-200">
                Nilai Terinput
            </span>
        </div>

        <!-- 3. Terkirim ke Division Head (Klik: Scroll ke tabel filter terkirim) -->
        <div @click="scrollToSection('dept-data-karyawan', 'done')" 
             class="bg-white rounded-none p-4 border border-slate-300 hover:border-blue-600 hover:shadow-md cursor-pointer transition-all flex items-center justify-between group"
             title="Klik untuk melihat dokumen yang telah terkirim ke Division Head di tabel">
            <div class="flex items-center gap-3.5">
                <div class="w-10 h-10 rounded-none bg-blue-50 text-blue-700 font-black flex items-center justify-center text-sm flex-shrink-0 border border-blue-300 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                    ✈
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-semibold leading-none group-hover:text-blue-700 transition-colors">
                        Terkirim ke Division Head
                    </p>
                    <p class="text-2xl font-black text-slate-900 mt-1.5 leading-none">{{ $deptDivSentCount ?? 0 }}</p>
                    <p class="text-[11px] text-slate-500 font-medium mt-1 leading-none">
                        {{ $deptDivSudahDinilai ?? 0 }} sudah dinilai, {{ $deptDivBelumDinilai ?? 0 }} belum dinilai
                    </p>
                </div>
            </div>
            <span class="text-[10px] font-bold px-2 py-1 {{ ($deptDivSentCount ?? 0) === 0 ? 'bg-slate-100 text-slate-600 border-slate-300' : (($deptDivBelumDinilai ?? 0) > 0 ? 'bg-amber-100 text-amber-800 border-amber-300' : 'bg-emerald-100 text-emerald-800 border-emerald-300') }} rounded-none border">
                {{ ($deptDivSentCount ?? 0) === 0 ? 'Belum Ada' : (($deptDivBelumDinilai ?? 0) > 0 ? 'To-Do' : 'Tuntas') }}
            </span>
        </div>
    </div>

    <!-- 4. Visual Workflow Pipeline Tracker (Pelacakan Alur Status Dokumen Bawahan - Khusus Dept Head) -->
    <div class="bg-white rounded-none p-5 border border-slate-300 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-slate-200 gap-2">
            <div>
                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wide">
                    Pelacakan Alur Status Dokumen Bawahan
                </h3>
                <p class="text-xs text-slate-500">
                    Memantau posisi dokumen seluruh {{ $deptTotalKaryawan }} karyawan departemen di setiap tahapan sistem
                </p>
            </div>
            <span class="text-xs font-mono font-bold text-slate-500 bg-slate-100 px-2.5 py-1 rounded-none border border-slate-200 self-start sm:self-auto">
                {{ $deptDivDoneStageCount }}/{{ $deptTotalKaryawan }} Telah Melewati Dept Head
            </span>
        </div>

        <!-- Alur 4 Tahap Terpadu: Foreman -> Section Head -> Approval Dept Head -> Approval Division Head -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mt-4">
            <!-- Tahap 1: Penilaian Foreman -->
            <div @click="scrollToSection('dept-data-karyawan', 'foreman')" 
                 class="p-3.5 border {{ $deptForemanPendingCount > 0 ? 'border-amber-400 bg-amber-50/50 hover:border-amber-500' : 'border-slate-200 bg-slate-50/60' }} rounded-none cursor-pointer transition-all hover:shadow-sm flex flex-col justify-between"
                 title="Klik untuk melihat antrean pengerjaan Foreman di tabel">
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">Tahap 1</span>
                        <span class="text-xs font-black {{ $deptForemanPendingCount > 0 ? 'text-amber-800 bg-amber-200' : 'text-slate-600 bg-slate-200' }} px-2 py-0.5 rounded-none">
                            {{ $deptForemanPendingCount }} Dokumen
                        </span>
                    </div>
                    <h4 class="text-xs font-bold text-slate-900">1. Penilaian Foreman</h4>
                    <p class="text-[11px] text-slate-600 mt-1 leading-snug">
                        {{ $deptStageCounts['belum_dinilai_fm'] ?? 0 }} belum dinilai • {{ $deptStageCounts['draft_fm'] ?? 0 }} draft tersimpan.
                    </p>
                </div>
                <div class="mt-2.5 pt-2 border-t border-slate-200/80 flex items-center justify-between text-[11px]">
                    <span class="font-medium text-slate-500">Proses pengerjaan Foreman</span>
                    <span class="font-mono text-slate-400 text-[10px]">Alur For-SH-DH</span>
                </div>
            </div>

            <!-- Tahap 2: Penilaian Section Head -->
            <div @click="scrollToSection('dept-data-karyawan', 'section')" 
                 class="p-3.5 border {{ $deptSectionPendingCount > 0 ? 'border-blue-400 bg-blue-50/50 hover:border-blue-500 ring-1 ring-blue-300' : 'border-slate-200 bg-slate-50/60' }} rounded-none cursor-pointer transition-all hover:shadow-sm flex flex-col justify-between"
                 title="Klik untuk melihat dokumen yang sedang diproses Section Head di tabel">
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">Tahap 2</span>
                        <span class="text-xs font-black {{ $deptSectionPendingCount > 0 ? 'text-blue-800 bg-blue-200' : 'text-slate-600 bg-slate-200' }} px-2 py-0.5 rounded-none">
                            {{ $deptSectionPendingCount }} Dokumen
                        </span>
                    </div>
                    <h4 class="text-xs font-bold text-slate-900">2. Penilaian Section Head</h4>
                    <p class="text-[11px] text-slate-600 mt-1 leading-snug">
                        @if($hasAnyForeman ?? false)
                            Dokumen telah melewati Foreman atau langsung diisi &amp; ditinjau oleh Section Head.
                        @else
                            {{ $deptStageCounts['belum_dinilai_sh'] ?? 0 }} belum dinilai • {{ ($deptStageCounts['draft_sh'] ?? 0) + ($deptStageCounts['revisi_sh'] ?? 0) }} draft/revisi.
                        @endif
                    </p>
                </div>
                <div class="mt-2.5 pt-2 border-t border-slate-200/80 flex items-center justify-between text-[11px]">
                    <span class="font-medium text-slate-500">Proses pengerjaan SH</span>
                    <span class="font-mono text-slate-400 text-[10px]">Alur SH-DH</span>
                </div>
            </div>

            <!-- Tahap 3: Approval Department Head -->
            <div @click="scrollToSection('dept-data-karyawan', 'todo')" 
                 class="p-3.5 border {{ ($deptNeedActionCount ?? 0) > 0 ? 'border-amber-500 bg-amber-100/60 shadow-xs ring-1 ring-amber-400' : 'border-slate-200 bg-slate-50/60' }} rounded-none cursor-pointer transition-all hover:shadow-sm flex flex-col justify-between"
                 title="Klik untuk melihat dokumen yang siap Anda review dan sahkan di tabel">
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">Tahap 3</span>
                        <span class="text-xs font-black {{ ($deptNeedActionCount ?? 0) > 0 ? 'text-amber-900 bg-amber-300' : 'text-slate-600 bg-slate-200' }} px-2 py-0.5 rounded-none">
                            {{ $deptNeedActionCount ?? 0 }} Dokumen
                        </span>
                    </div>
                    <h4 class="text-xs font-bold text-slate-900">3. Approval Department Head</h4>
                    <p class="text-[11px] text-slate-600 mt-1 leading-snug">
                        KJ 1-5, dan form Section Head &amp; Dept Head diteruskan Division Head.
                    </p>
                </div>
                <div class="mt-2.5 pt-2 border-t border-slate-200/80 flex items-center justify-between text-[11px]">
                    <span class="font-bold {{ ($deptNeedActionCount ?? 0) > 0 ? 'text-amber-800' : 'text-slate-500' }}">
                        {{ ($deptNeedActionCount ?? 0) > 0 ? 'Perlu tindakan Anda' : 'Tidak ada antrean' }}
                    </span>
                    <a href="{{ route('penilaian.dept') }}" @click.stop class="text-[#92302e] font-bold hover:underline inline-flex items-center gap-1">
                        Kerjakan &rarr;
                    </a>
                </div>
            </div>

            <!-- Tahap 4: Approval Division Head -->
            <div @click="scrollToSection('dept-data-karyawan', 'done')" 
                 class="p-3.5 border {{ $deptDivDoneStageCount > 0 ? 'border-emerald-300 bg-emerald-50/40 hover:border-emerald-400' : 'border-slate-200 bg-slate-50/60' }} rounded-none cursor-pointer transition-all hover:shadow-sm flex flex-col justify-between"
                 title="Klik untuk melihat dokumen yang diteruskan ke Division Head / selesai final di tabel">
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">Tahap 4</span>
                        <span class="text-xs font-black {{ $deptDivDoneStageCount > 0 ? 'text-emerald-800 bg-emerald-100' : 'text-slate-600 bg-slate-200' }} px-2 py-0.5 rounded-none">
                            {{ $deptDivDoneStageCount }} Dokumen
                        </span>
                    </div>
                    <h4 class="text-xs font-bold text-slate-900">4. Approval Division Head</h4>
                    <p class="text-[11px] text-slate-600 mt-1 leading-snug">
                        Penilaian masuk ke pengesahan akhir Division Head hingga selesai final &amp; arsip resmi tercatat.
                    </p>
                </div>
                <div class="mt-2.5 pt-2 border-t border-slate-200/80 flex items-center justify-between text-[11px]">
                    <span class="font-bold text-emerald-700">Selesai Final</span>
                    <span class="font-mono text-emerald-600 text-[10px]">Tuntas</span>
                </div>
            </div>
        </div>
    </div>
    @endif

    @if(!$user->isSecretary())
    <!-- 5. Matrix Tables Row: Target Penilaian & Hasil Penilaian Department Head per KJ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mt-6">
        @include('dashboard.partials.kj_matrix_card', [
            'id' => 'chart-target-kategori',
            'accentBg' => 'bg-[#92302e]',
            'title' => 'Target Penilaian',
            'subtitle' => '(berdasarkan kategori & KJ)',
            'badgeClass' => 'bg-blue-50 text-blue-700 border-blue-200',
            'badgeText' => 'Total Target: ' . ($deptTargetKjSummary['total'] ?? $effectiveTotalTarget),
            'lastColHeader' => 'Total Karyawan',
            'matrix' => $deptTargetKjMatrix ?? [],
            'summary' => $deptTargetKjSummary ?? ['S' => $targetS, 'A' => $targetA, 'B' => $targetB, 'C' => $targetC, 'K' => $targetK, 'total' => $effectiveTotalTarget],
            'emptyText' => 'Belum ada data target per KJ',
            'dotColor' => 'bg-blue-600',
            'footerLeft' => 'Rencana target kuota perolehan kategori nilai per Klasifikasi Jabatan (KJ)',
            'footerRight' => 'Tahun ' . date('Y'),
        ])

        @include('dashboard.partials.kj_matrix_card', [
            'accentBg' => 'bg-emerald-600',
            'title' => 'Hasil Penilaian Department Head',
            'subtitle' => '(nilai dari anda per KJ)',
            'badgeClass' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'badgeText' => 'Total Dinilai: ' . ($deptActualKjSummary['total'] ?? 0),
            'lastColHeader' => 'Total Dinilai',
            'matrix' => $deptActualKjMatrix ?? [],
            'summary' => $deptActualKjSummary ?? ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0, 'total' => 0],
            'emptyText' => 'Belum ada data realisasi dinilai Department Head per KJ',
            'dotColor' => 'bg-emerald-600',
            'footerLeft' => 'Realisasi perolehan nilai riil yang telah dinilai & disahkan Department Head',
            'footerRight' => ($deptActualKjSummary['total'] ?? 0) . '/' . ($deptTargetKjSummary['total'] ?? $effectiveTotalTarget) . ' Dinilai',
        ])
    </div>
    @endif

    <!-- 7. Bottom Table: Data Karyawan & Penilaian (Lengkap dengan Toolbar Filter & Pencarian) -->
    <div id="dept-data-karyawan" class="bg-white rounded-none border border-slate-300 shadow-sm overflow-hidden scroll-mt-20">
        <!-- Table Header & Filter Toolbar (Sesuai Desain Section Head & Mockup Screen 3) -->
        <div class="p-4 border-b border-slate-200">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <h3 class="text-sm font-bold text-slate-900 whitespace-nowrap">
                        Data Karyawan &amp; Penilaian
                    </h3>
                    <span class="text-xs px-2.5 py-0.5 rounded-none bg-slate-100 text-slate-700 font-bold border border-slate-200 whitespace-nowrap">
                        {{ count($deptSubordinateRows) }} Karyawan
                    </span>
                </div>

                <!-- Toolbar: Dropdown Filter Status + Live Search Box Sejajar -->
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 w-full md:w-auto">
                    <!-- Dropdown Filter Status Penilaian -->
                    <div class="w-full sm:w-60">
                        <select x-model="activeTab" 
                                class="w-full py-1.5 px-3 text-xs font-semibold text-slate-700 rounded-none border border-slate-300 hover:border-slate-400 bg-white focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-xs cursor-pointer">
                            <option value="all">Semua ({{ count($deptSubordinateRows) }})</option>
                            @if($user->isSecretary())
                                <option value="belum">Belum Dinilai ({{ $secBelum }})</option>
                                <option value="dinilai">Penilaian Anda / Sudah Dinilai ({{ $secSudah }})</option>
                                <option value="done">Terkirim ke Division Head ({{ $secDivTotal }})</option>
                            @else
                                <option value="foreman">Foreman ({{ $deptForemanPendingCount }})</option>
                                <option value="foreman_done">Sudah Dinilai Foreman ({{ $deptForemanTotalDinilai ?? 0 }})</option>
                                <option value="section">Section Head ({{ $deptSectionPendingCount }})</option>
                                <option value="todo">Department Head ({{ $deptNeedActionCount ?? 0 }})</option>
                                <option value="done">Division Head ({{ $deptDivDoneStageCount }})</option>
                                <option value="evaluated">Sudah Dinilai Section Head ({{ $deptSectionTotalDinilai ?? 0 }})</option>
                            @endif
                        </select>
                    </div>

                    <!-- Live Search Box -->
                    <div class="w-full sm:w-72">
                        <div class="relative">
                            <input type="text" 
                                   x-model="searchQuery" 
                                   placeholder="Cari nama, NRP, position, section..." 
                                   class="w-full pl-8 pr-3 py-1.5 text-xs rounded-none border border-slate-300 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] bg-slate-50">
                            <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table Content -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse border border-slate-300">
                <thead>
                    <tr class="text-slate-700 font-bold border-b border-slate-300 bg-slate-100/90 uppercase tracking-wider text-[11px]">
                        <th class="py-3 px-4 border border-slate-300">Nama Lengkap</th>
                        <th class="py-3 px-4 border border-slate-300">NRP</th>
                        <th class="py-3 px-4 border border-slate-300">Position</th>
                        <th class="py-3 px-4 border border-slate-300">Golongan</th>
                        <th class="py-3 px-4 border border-slate-300">Start Work</th>
                        <th class="py-3 px-4 border border-slate-300">Tahap Dokumen</th>
                        <th class="py-3 px-4 text-center border border-slate-300">Status</th>
                        <th class="py-3 px-4 text-center border border-slate-300">Skor / Grade</th>
                        <th class="py-3 px-4 text-center w-28 border border-slate-300">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($deptSubordinateRows as $sub)
                    <tr class="hover:bg-slate-50 transition-colors"
                        x-show="matchesFilter({{ json_encode($sub) }})">
                        <td class="py-3 px-4 font-semibold text-slate-900 border border-slate-200">
                            <div>
                                <div class="flex items-center gap-1.5">
                                    <span class="font-bold">{{ $sub['nama_lengkap'] }}</span>
                                    @if(!empty($sub['is_self']))
                                        <span class="px-1.5 py-0.2 rounded-none bg-rose-100 text-rose-800 border border-rose-300 font-black text-[9px]">ANDA</span>
                                    @endif
                                </div>
                                <span class="text-[10px] text-slate-400 font-normal">{{ $sub['seksi'] }}</span>
                            </div>
                        </td>
                        <td class="py-3 px-4 font-mono text-slate-700 font-medium border border-slate-200">
                            {{ $sub['nrp'] }}
                        </td>
                        <td class="py-3 px-4 text-slate-600 border border-slate-200">
                            {{ $sub['jabatan'] }}
                        </td>
                        <td class="py-3 px-4 text-slate-600 font-medium font-mono text-[11px] border border-slate-200">
                            {{ $sub['sub_gol'] ?? $sub['golongan'] ?? '-' }}
                        </td>
                        <td class="py-3 px-4 text-slate-600 font-mono text-[11px] border border-slate-200">
                            {{ \App\Services\GoogleSyncService::formatMusashiDate($sub['tgl_masuk'] ?? '') }}
                        </td>
                        <td class="py-3 px-4 border border-slate-200">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-none text-[10px] font-bold border {{ $sub['badge_class'] ?? 'bg-slate-100 text-slate-700 border-slate-200' }}">
                                @if($user->isSecretary())
                                    {{ in_array($sub['status'], ['menunggu_div_head', 'selesai']) ? 'Terkirim ke Division Head' : 'Penilaian Mandiri' }}
                                @else
                                    {{ $sub['stage_label'] ?? '-' }}
                                @endif
                            </span>
                        </td>
                        <td class="py-3 px-4 text-center border border-slate-200">
                            @php
                                $isSubDone = $user->isSecretary()
                                    ? (in_array($sub['status'], ['menunggu_div_head', 'selesai']) || !empty($sub['is_evaluated']))
                                    : (!empty($sub['is_evaluated']) || in_array($sub['status'], ['menunggu_dept_head', 'menunggu_div_head', 'selesai']));
                            @endphp
                            @if($isSubDone)
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
                            @if(!empty($sub['score']) && $sub['score'] !== '-')
                                <span class="font-bold text-slate-900">{{ $sub['score'] }}</span>
                                <span class="ml-1 text-[10px] font-black px-1.5 py-0.2 rounded-none bg-slate-100 border border-slate-200 text-slate-800">{{ $sub['grade'] ?? '-' }}</span>
                            @elseif(!empty($sub['grade']) && $sub['grade'] !== '-')
                                <span class="text-[10px] font-black px-1.5 py-0.2 rounded-none bg-slate-100 border border-slate-200 text-slate-800">{{ $sub['grade'] }}</span>
                            @else
                                <span class="text-slate-400">-</span>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-center border border-slate-200">
                            @if(!empty($sub['doc_id']))
                                @php
                                    $isActionable = false;
                                    $btnLabel = 'Lihat';
                                    if (!empty($sub['is_self'])) {
                                        // Evaluasi Mandiri Department Head / Manager
                                        if (!in_array($sub['status'], ['menunggu_div_head', 'selesai'])) {
                                            $isActionable = true;
                                            $btnLabel = 'Nilai Mandiri';
                                        }
                                    } elseif ($sub['status'] === 'menunggu_dept_head') {
                                        // Hanya ketika dokumen sudah sampai di tahap Department Head
                                        $isActionable = true;
                                        $btnLabel = $user->isSecretary() ? 'Nilai Mandiri' : 'Tinjau / Sahkan';
                                    }
                                @endphp
                                <a href="{{ route($user->isSecretary() ? 'penilaian.show.secretary' : 'penilaian.show.depthead', $sub['doc_id']) }}" 
                                   class="inline-block px-2.5 py-1 rounded-none text-xs font-bold transition-colors {{ $isActionable ? 'bg-[#92302e] text-white hover:bg-[#7e2524]' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 border border-slate-300' }}">
                                    {{ $btnLabel }}
                                </a>
                            @else
                                <a href="{{ $user->isSecretary() ? url('/secretary') : route('penilaian.dept') }}" 
                                   class="inline-block px-2.5 py-1 rounded-none text-xs font-bold bg-slate-100 text-slate-700 hover:bg-slate-200 border border-slate-300 transition-colors">
                                    Lihat
                                </a>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="py-8 text-center text-slate-400 border border-slate-200">
                            Belum ada data bawahan di departemen ini.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-3 bg-slate-50 border-t border-slate-200 flex items-center justify-end text-xs text-slate-500">
            <div class="flex items-center gap-3">
                <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-none bg-emerald-500 inline-block"></span> Sudah Dinilai / Disahkan</span>
                <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-none bg-amber-500 inline-block"></span> Draft / To-Do</span>
                <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-none bg-rose-500 inline-block"></span> Belum Dinilai</span>
            </div>
        </div>
    </div>

    <!-- 8. Panduan Alur Kerja (Petunjuk Pelaksanaan Evaluasi) -->
    <div class="bg-white rounded-none p-5 border border-slate-300 shadow-sm">
        <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider mb-3">
            {{ $user->isSecretary() ? 'Panduan & Prosedur Evaluasi Secretary' : 'Panduan & Prosedur Evaluasi Department Head' }}
        </h4>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-xs text-slate-600">
            @if($user->isSecretary())
            <div class="p-3 bg-slate-50 border border-slate-200 rounded-none">
                <span class="font-bold text-[#92302e] block mb-1">Langkah 1: Akses Form Penilaian</span>
                <p>Buka formulir penilaian evaluasi mandiri Secretary (Form 07-006) melalui menu Form Penilaian atau tombol di dashboard.</p>
            </div>
            <div class="p-3 bg-slate-50 border border-slate-200 rounded-none">
                <span class="font-bold text-[#92302e] block mb-1">Langkah 2: Evaluasi Mandiri</span>
                <p>Lakukan penilaian mandiri secara objektif atas seluruh aspek hasil kerja, kedisiplinan, dan kompetensi.</p>
            </div>
            <div class="p-3 bg-slate-50 border border-slate-200 rounded-none">
                <span class="font-bold text-[#92302e] block mb-1">Langkah 3: Tanda Tangan Digital</span>
                <p>Bubuhkan tanda tangan digital Anda secara resmi dan klik Kirim untuk menyelesaikan evaluasi mandiri.</p>
            </div>
            <div class="p-3 bg-slate-50 border border-slate-200 rounded-none">
                <span class="font-bold text-[#92302e] block mb-1">Langkah 4: Pengesahan Division Head</span>
                <p>Dokumen otomatis terkirim ke Division Head untuk dilakukan peninjauan dan pengesahan akhir (final).</p>
            </div>
            @else
            <div class="p-3 bg-slate-50 border border-slate-200 rounded-none">
                <span class="font-bold text-[#92302e] block mb-1">Langkah 1: Monitoring Alur</span>
                <p>Pantau posisi dokumen seluruh karyawan departemen pada alur pipeline mulai dari Foreman dan Section Head.</p>
            </div>
            <div class="p-3 bg-slate-50 border border-slate-200 rounded-none">
                <span class="font-bold text-[#92302e] block mb-1">Langkah 2: Tinjau Nilai Masuk</span>
                <p>Periksa berkas penilaian yang telah diverifikasi dan diteruskan oleh Section Head pada antrean To-Do.</p>
            </div>
            <div class="p-3 bg-slate-50 border border-slate-200 rounded-none">
                <span class="font-bold text-[#92302e] block mb-1">Langkah 3: Approval &amp; TTD Digital</span>
                <p>Lakukan pengesahan tingkat departemen dengan membubuhkan tanda tangan digital secara resmi sebagai tingkat persetujuan tertinggi.</p>
            </div>
            <div class="p-3 bg-slate-50 border border-slate-200 rounded-none">
                <span class="font-bold text-[#92302e] block mb-1">Langkah 4: Rekap &amp; Arsip Final</span>
                <p>Pantau rekapitulasi nilai akhir departemen dan pastikan target alokasi kuota sesuai ketentuan perusahaan.</p>
            </div>
            @endif
        </div>
    </div>

</div>
