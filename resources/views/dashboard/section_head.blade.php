<!-- ============================================================== -->
<!-- SECTION HEAD DASHBOARD VIEW                                      -->
<!-- Desain & Struktur Identik dengan Dashboard Foreman Musashi     -->
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
        if (this.activeTab === 'todo' && !['todo', 'draft', 'revisi', 'belum_dinilai'].includes(sub.action_type)) return false;
        if (this.activeTab === 'foreman' && sub.action_type !== 'menunggu_foreman') return false;
        if (this.activeTab === 'draft' && sub.action_type !== 'draft') return false;
        if (this.activeTab === 'done' && sub.action_type !== 'sudah_dikirim') return false;
        if (this.activeTab === 'evaluated' && sub.action_type !== 'draft' && sub.action_type !== 'sudah_dikirim' && !sub.is_evaluated) return false;
        
        // Search filter
        if (this.searchQuery.trim() !== '') {
            const q = this.searchQuery.toLowerCase();
            const nameMatch = sub.nama_lengkap.toLowerCase().includes(q);
            const nrpMatch = sub.nrp.toLowerCase().includes(q);
            const jabMatch = sub.jabatan.toLowerCase().includes(q);
            return nameMatch || nrpMatch || jabMatch;
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
            <span class="sr-only">Dashboard Section Head</span>
            <p class="text-xs text-red-100 font-medium mt-1">
                Selamat Datang {{ $user->nama }} &bull; <span class="text-red-200 font-normal">{{ $user->resolved_jabatan }}</span>
            </p>
        </div>

        <div class="flex items-center gap-2.5">
            <!-- Form Penilaian Button -->
            <a href="{{ route('penilaian.cari') }}" 
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-none bg-white hover:bg-slate-100 text-[#92302e] font-bold text-xs shadow-sm transition-colors border border-white">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <span>Form Penilaian</span>
            </a>
        </div>
    </div>

    <!-- 2. Live Action Banner: Yang Sudah Dilakukan & Yang Mau Dilakukan -->
    @php
        $totalSubCount = count($sectionSubordinates);
        $isSectionFullyDone = ($totalSubCount > 0)
            && ($sectionDoneCount >= $totalSubCount)
            && ($sectionNeedActionCount === 0)
            && ($sectionBelumCount === 0)
            && ($sectionDraftCount === 0);
    @endphp

    @if($sectionRevisiCount > 0)
        <!-- Urgent Revision Alert -->
        <div class="bg-rose-50 border-l-4 border-rose-600 p-4 border-y border-r border-rose-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-start gap-3">
                <div class="w-8 h-8 rounded-none bg-rose-600 text-white flex items-center justify-center font-bold text-base flex-shrink-0">
                    !
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-[11px] font-black uppercase tracking-wider px-2 py-0.5 bg-rose-200 text-rose-900 rounded-none">
                            Perlu Revisi Segera
                        </span>
                        <span class="text-xs text-rose-800 font-bold">
                            {{ $sectionRevisiCount }} Dokumen Dikembalikan oleh Department Head
                        </span>
                    </div>
                    <p class="text-xs text-rose-700 mt-1">
                        Ada dokumen penilaian yang memerlukan penyesuaian catatan atau nilai. Silakan klik tab <strong>To-Do</strong> di bawah untuk melihat dan merevisi dokumen terkait.
                    </p>
                </div>
            </div>
            <button @click="scrollToSection('section-data-karyawan', 'todo')" type="button" 
                    class="px-3.5 py-1.5 rounded-none bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs shadow-sm whitespace-nowrap self-start md:self-center">
                Lihat Dokumen Revisi &rarr;
            </button>
        </div>
    @elseif($hasForemanSubordinates && ($foremanStageCounts['waiting_section'] ?? 0) > 0)
        <!-- Urgent Action Banner: Dokumen Masuk dari Foreman -->
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
                            {{ $foremanStageCounts['waiting_section'] }} Dokumen Penilaian Menunggu Persetujuan!
                        </span>
                    </div>
                    <p class="text-xs text-amber-900 mt-1">
                        Mohon segera lakukan peninjauan nilai &amp; tanda tangan digital untuk diteruskan ke Department Head.
                    </p>
                </div>
            </div>
            <a href="{{ route('penilaian.sectionhead') }}" 
               class="px-4 py-2 rounded-none bg-[#92302e] hover:bg-[#7e2524] text-white font-bold text-xs shadow-sm whitespace-nowrap self-start md:self-center transition-colors">
                Tinjau Dokumen Masuk &rarr;
            </a>
        </div>
    @elseif($hasForemanSubordinates && ($foremanStageCounts['tahap1'] ?? 0) > 0)
        <!-- Info Banner: Sedang Dikerjakan Foreman -->
        <div class="bg-blue-50/70 border-l-4 border-blue-500 p-4 border-y border-r border-blue-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-start gap-3.5">
                <div class="w-8 h-8 rounded-none bg-blue-600 text-white flex items-center justify-center font-bold text-sm flex-shrink-0">
                    ℹ
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 bg-blue-200 text-blue-900 rounded-none">
                            Proses Foreman
                        </span>
                        <span class="text-xs text-blue-950 font-bold">
                            {{ $foremanStageCounts['tahap1'] }} dari {{ count($sectionSubordinates) }} Dokumen Sedang Dikerjakan Foreman
                        </span>
                    </div>
                    <p class="text-xs text-blue-800 mt-1">
                        Dokumen penilaian sedang diproses oleh Foreman dan akan otomatis masuk ke antrean persetujuan Section Head setelah ditandatangani.
                    </p>
                </div>
            </div>
            <a href="{{ route('penilaian.sectionhead') }}" 
               class="px-3.5 py-1.5 rounded-none bg-blue-700 hover:bg-blue-800 text-white font-bold text-xs shadow-sm whitespace-nowrap self-start md:self-center">
                Lihat Data Penilaian &rarr;
            </a>
        </div>
    @elseif($isSectionFullyDone)
        <!-- All Done Success Banner -->
        <div class="bg-emerald-50 border-l-4 border-emerald-600 p-4 border-y border-r border-emerald-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-start gap-3">
                <div class="w-8 h-8 rounded-none bg-emerald-600 text-white flex items-center justify-center font-bold text-sm flex-shrink-0">
                    ✓
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 bg-emerald-200 text-emerald-900 rounded-none">
                            {{ !$user->isStructuralSectionHead() && $totalSubCount <= 1 ? 'Penilaian Mandiri Selesai' : 'Tugas Section Head Selesai 100%' }}
                        </span>
                        <span class="text-xs font-bold text-emerald-950">
                            @if(!$user->isStructuralSectionHead() && $totalSubCount <= 1)
                                Dokumen Penilaian Mandiri Anda Telah Selesai Dinilai & Dikirim ke Dept. Head
                            @elseif(($sectionBawahanCount ?? 0) > 0 && ($sectionMandiriCount ?? 0) > 0)
                                Seluruh {{ $totalSubCount }} Dokumen ({{ $sectionBawahanCount }} Bawahan & {{ $sectionMandiriCount }} Mandiri) Telah Selesai Dinilai & Dikirim ke Dept. Head
                            @else
                                Seluruh {{ $totalSubCount }} Karyawan Bawahan Telah Selesai Dinilai & Dikirim ke Dept. Head
                            @endif
                        </span>
                    </div>
                    <p class="text-xs text-emerald-800 mt-1">
                        @if($hasForemanSubordinates)
                            Semua berkas penilaian karyawan telah ditandatangani digital oleh Anda dan saat ini sedang dalam proses tinjauan Department Head.
                        @else
                            Semua berkas penilaian karyawan telah ditandatangani digital oleh Anda (bawahan KJ 1-5 tuntas setelah pengesahan Department Head, sedangkan form Section Head 07-006 diteruskan ke Division Head).
                        @endif
                    </p>
                </div>
            </div>
            <a href="{{ route('penilaian.riwayat') }}" 
               class="px-3.5 py-1.5 rounded-none bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs shadow-sm whitespace-nowrap self-start md:self-center">
                Lihat Riwayat & Rekap &rarr;
            </a>
        </div>
    @elseif($sectionNeedActionCount > 0)
        <!-- Urgent Action Banner: Direct Section Head Subordinates -->
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
                            {{ $sectionNeedActionCount }} Dokumen Penilaian Menunggu Penilaian &amp; Persetujuan Anda!
                        </span>
                    </div>
                    <p class="text-xs text-amber-900 mt-1">
                        @if(($sectionDoneCount ?? 0) > 0)
                            Sebanyak {{ $sectionDoneCount }} dari {{ $totalSubCount }} dokumen telah Anda selesaikan. Mohon segera selesaikan sisa {{ $sectionNeedActionCount }} dokumen lainnya.
                        @else
                            Belum ada dokumen yang dinilai. Mohon segera lakukan penilaian &amp; tanda tangan digital untuk diteruskan ke Department Head.
                        @endif
                    </p>
                </div>
            </div>
            <a href="{{ route('penilaian.cari') }}" 
               class="px-4 py-2 rounded-none bg-[#92302e] hover:bg-[#7e2524] text-white font-bold text-xs shadow-sm whitespace-nowrap self-start md:self-center transition-colors">
                Buka Form Penilaian &rarr;
            </a>
        </div>
    @else
        <!-- Standby / Progress Banner -->
        <div class="bg-slate-50 border-l-4 border-slate-500 p-4 border-y border-r border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-start gap-3">
                <div class="w-8 h-8 rounded-none bg-slate-500 text-white flex items-center justify-center font-bold text-sm flex-shrink-0">
                    ℹ
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 bg-slate-200 text-slate-800 rounded-none">
                            Status Alur Penilaian
                        </span>
                        <span class="text-xs font-bold text-slate-900">
                            Progres Penilaian: {{ $sectionDoneCount ?? 0 }} / {{ $totalSubCount }} Selesai Dinilai
                        </span>
                    </div>
                    <p class="text-xs text-slate-600 mt-1">
                        Dokumen yang telah Anda tandatangani sedang dalam proses review di tahapan pimpinan selanjutnya.
                    </p>
                </div>
            </div>
            <a href="{{ route('penilaian.riwayat') }}" 
               class="px-3.5 py-1.5 rounded-none bg-slate-700 hover:bg-slate-800 text-white font-bold text-xs shadow-sm whitespace-nowrap self-start md:self-center">
                Lihat Riwayat & Rekap &rarr;
            </a>
        </div>
    @endif

    <!-- 3. Key Stat Cards in a Row (3 Columns: Total Karyawan, Total Dinilai, Diteruskan / Selesai) -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <!-- 1. Total Karyawan (Klik: Scroll ke tabel filter semua) -->
        <div @click="scrollToSection('section-data-karyawan', 'all')" 
             class="bg-white rounded-none p-4 border border-slate-300 hover:border-[#92302e] hover:shadow-md cursor-pointer transition-all flex items-center justify-between group"
             title="Klik untuk melihat seluruh karyawan bawahan di tabel">
            <div class="flex items-center gap-3.5">
                <div class="w-10 h-10 rounded-none bg-[#fbe8e8] text-[#92302e] font-black flex items-center justify-center text-sm flex-shrink-0 border border-[#92302e]/20 group-hover:bg-[#92302e] group-hover:text-white transition-colors">
                    K
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-semibold leading-none group-hover:text-[#92302e] transition-colors">Total Karyawan</p>
                    <p class="text-2xl font-black text-slate-900 mt-1.5 leading-none">{{ count($sectionSubordinates) }}</p>
                    <p class="text-[11px] text-slate-500 mt-1 leading-none">
                        @if(($sectionBawahanCount ?? 0) > 0 && ($sectionMandiriCount ?? 0) > 0)
                            {{ $sectionMandiriCount }} Mandiri, {{ $sectionBawahanCount }} Bawahan
                        @elseif(($sectionBawahanCount ?? 0) > 0)
                            {{ $sectionBawahanCount }} Anggota Tim
                        @else
                            Evaluasi Diri Mandiri
                        @endif
                    </p>
                </div>
            </div>
            <span class="text-[10px] font-bold px-2 py-1 bg-slate-100 text-slate-600 rounded-none border border-slate-200">
                {{ !$user->isStructuralSectionHead() && count($sectionSubordinates) <= 1 ? 'Mandiri' : '100% Tim' }}
            </span>
        </div>

        <!-- 2. Total Dinilai (Klik: Scroll ke tabel filter sudah dinilai) -->
        <div @click="scrollToSection('section-data-karyawan', 'evaluated')" 
             class="bg-white rounded-none p-4 border border-slate-300 hover:border-emerald-600 hover:shadow-md cursor-pointer transition-all flex items-center justify-between group"
             title="Klik untuk melihat karyawan yang sudah dinilai (draft & terkirim) di tabel">
            <div class="flex items-center gap-3.5">
                <div class="w-10 h-10 rounded-none bg-emerald-50 text-emerald-700 font-black flex items-center justify-center text-sm flex-shrink-0 border border-emerald-300 group-hover:bg-emerald-600 group-hover:text-white transition-colors">
                    ✓
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-semibold leading-none group-hover:text-emerald-700 transition-colors">Total Dinilai</p>
                    <p class="text-2xl font-black text-slate-900 mt-1.5 leading-none">{{ $sectionTotalDinilai }}</p>
                    @php
                        $shDraftCount = $sectionDraftCount ?? 0;
                        $shBelumCount = max(0, count($sectionSubordinates) - ($sectionDoneCount ?? 0) - $shDraftCount);
                    @endphp
                    <p class="text-[11px] text-slate-500 font-medium mt-1 leading-none">
                        {{ $shDraftCount }} draft, {{ $shBelumCount }} belum dinilai
                    </p>
                </div>
            </div>
            <span class="text-[10px] font-bold px-2 py-1 bg-emerald-50 text-emerald-700 rounded-none border border-emerald-200">
                Nilai Terinput
            </span>
        </div>

        <!-- 3. Terkirim ke Department Head (Klik: Scroll ke tabel filter terkirim) -->
        <div @click="scrollToSection('section-data-karyawan', 'done')" 
             class="bg-white rounded-none p-4 border border-slate-300 hover:border-blue-600 hover:shadow-md cursor-pointer transition-all flex items-center justify-between group"
             title="Klik untuk melihat dokumen yang telah terkirim ke Department Head di tabel">
            <div class="flex items-center gap-3.5">
                <div class="w-10 h-10 rounded-none bg-blue-50 text-blue-700 font-black flex items-center justify-center text-sm flex-shrink-0 border border-blue-300 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                    ✈
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-semibold leading-none group-hover:text-blue-700 transition-colors">
                        Terkirim ke Department Head
                    </p>
                    <p class="text-2xl font-black text-slate-900 mt-1.5 leading-none">{{ $sectionDoneCount }}</p>
                    <p class="text-[11px] text-slate-500 font-medium mt-1 leading-none">
                        {{ $sectionDhSudahDinilai ?? 0 }} sudah dinilai, {{ $sectionDhBelumDinilai ?? 0 }} belum dinilai
                    </p>
                </div>
            </div>
            <span class="text-[10px] font-bold px-2 py-1 {{ ($sectionDoneCount ?? 0) === 0 ? 'bg-slate-100 text-slate-600 border-slate-300' : (($sectionDhBelumDinilai ?? 0) > 0 ? 'bg-amber-100 text-amber-800 border-amber-300' : 'bg-emerald-100 text-emerald-800 border-emerald-300') }} rounded-none border">
                {{ ($sectionDoneCount ?? 0) === 0 ? 'Belum Ada' : (($sectionDhBelumDinilai ?? 0) > 0 ? 'To-Do' : 'Tuntas') }}
            </span>
        </div>
    </div>

    <!-- 4. Visual Workflow Pipeline Tracker (Tahapan Penilaian Bawahan) -->
    <div class="bg-white rounded-none p-5 border border-slate-300 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-slate-200 gap-2">
            <div>
                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wide">
                    Pelacakan Alur Status Dokumen
                </h3>
                <p class="text-xs text-slate-500">
                    Memantau posisi seluruh {{ count($sectionSubordinates) }} berkas penilaian di setiap tahapan sistem
                </p>
            </div>
        </div>

        @if($hasForemanSubordinates)
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mt-4 items-stretch">
                <!-- Step 1: Penilaian Foreman (To-Do) (Klik: Filter antrean Foreman) -->
                <div @click="scrollToSection('section-data-karyawan', 'foreman')" 
                     class="p-3.5 border border-slate-200 bg-slate-50/60 hover:border-slate-300 rounded-none cursor-pointer transition-all hover:shadow-sm flex flex-col justify-between"
                     title="Klik untuk melihat antrean pengerjaan Foreman di tabel">
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">Tahap 1</span>
                            <span class="text-xs font-black text-slate-700 bg-slate-200 px-2 py-0.5 rounded-none">
                                {{ $foremanStageCounts['tahap1'] ?? 0 }} Orang
                            </span>
                        </div>
                        <h4 class="text-xs font-bold text-slate-900">1. Penilaian Foreman (To-Do)</h4>
                        <p class="text-[11px] text-slate-600 mt-1 leading-snug">
                            Foreman Melakukan Penilaian untuk bawahannya
                        </p>
                    </div>
                    <div class="mt-2.5 pt-2 border-t border-slate-200/80 flex flex-col gap-0.5 text-[11px]">
                        <span class="font-bold text-emerald-700">
                            {{ $foremanDoneCount ?? 0 }} Sudah dinilai
                        </span>
                        <span class="font-bold text-slate-500">
                            {{ $foremanStageCounts['tahap1'] ?? 0 }} Belum dinilai
                        </span>
                    </div>
                </div>

                <!-- Step 2: Tinjauan Section Head (Tahap Aktif Section Head - Highlighted Amber) -->
                <div @click="scrollToSection('section-data-karyawan', 'todo')" 
                     class="p-3.5 border {{ ($foremanStageCounts['waiting_section'] ?? 0) > 0 ? 'border-amber-400 bg-amber-50/50 hover:border-amber-500 ring-1 ring-amber-300' : 'border-slate-200 bg-slate-50/60' }} rounded-none cursor-pointer transition-all hover:shadow-sm flex flex-col justify-between"
                     title="Klik untuk melihat dokumen yang siap Anda tinjau di tabel">
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">Tahap 2</span>
                            <span class="text-xs font-black {{ ($foremanStageCounts['waiting_section'] ?? 0) > 0 ? 'text-amber-800 bg-amber-200' : 'text-slate-600 bg-slate-200' }} px-2 py-0.5 rounded-none">
                                {{ $foremanStageCounts['waiting_section'] ?? 0 }} Dokumen
                            </span>
                        </div>
                        <h4 class="text-xs font-bold text-slate-900">2. Tinjauan Section Head</h4>
                        <p class="text-[11px] text-slate-600 mt-1 leading-snug">
                            Section Head Melakukan Penilaian untuk bawahannya.
                        </p>
                        <p class="text-[11px] text-slate-600 mt-1 leading-snug">
                            {{ $sectionBelumCount ?? 0 }} belum dinilai &bull; {{ $sectionDraftCount ?? 0 }} draft tersimpan.
                        </p>
                    </div>
                    <div class="mt-2.5 pt-2 border-t border-slate-200/80 flex items-center justify-between text-[11px]">
                        <span class="font-bold {{ ($foremanStageCounts['waiting_section'] ?? 0) > 0 ? 'text-amber-700' : 'text-slate-500' }}">
                            {{ ($foremanStageCounts['waiting_section'] ?? 0) > 0 ? 'Perlu tindakan Anda' : 'Tidak ada antrean' }}
                        </span>
                        @if(($foremanStageCounts['waiting_section'] ?? 0) > 0)
                            <button @click.stop="scrollToSection('section-data-karyawan', 'todo')" type="button" class="text-[#92302e] font-bold hover:underline inline-flex items-center gap-1">
                                Kerjakan &rarr;
                            </button>
                        @endif
                    </div>
                </div>

                <!-- Step 3: Approval Dept Head -->
                <div @click="scrollToSection('section-data-karyawan', 'done')" 
                     class="p-3.5 border border-slate-200 bg-slate-50/60 hover:border-slate-300 rounded-none cursor-pointer transition-all hover:shadow-sm flex flex-col justify-between"
                     title="Klik untuk melihat dokumen di tabel">
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">Tahap 3</span>
                            <span class="text-xs font-black text-slate-700 bg-slate-200 px-2 py-0.5 rounded-none">
                                {{ $foremanStageCounts['waiting_dept'] ?? 0 }} Dokumen
                            </span>
                        </div>
                        <h4 class="text-xs font-bold text-slate-900">3. Approval Dept Head</h4>
                        <p class="text-[11px] text-slate-600 mt-1 leading-snug">
                            Telah disetujui Section Head, menunggu persetujuan Department Head.
                        </p>
                    </div>
                    <div class="mt-2.5 pt-2 border-t border-slate-200/80 flex flex-col gap-0.5 text-[11px]">
                        <span class="font-bold text-emerald-700">
                            {{ $sectionDhSudahDinilai ?? 0 }} Sudah dinilai
                        </span>
                        <span class="font-bold text-slate-500">
                            {{ $sectionDhBelumDinilai ?? 0 }} Belum dinilai
                        </span>
                    </div>
                </div>

                <!-- Step 4: Selesai & Terarsip -->
                @php
                    $shTrackerSelesai = $sectionSelesaiCount ?? ($foremanStageCounts['selesai'] ?? 0);
                    $shTrackerBelumDisahkan = max(0, count($sectionSubordinates) - $shTrackerSelesai);
                @endphp
                <div @click="scrollToSection('section-data-karyawan', 'done')" 
                     class="p-3.5 border border-slate-200 bg-slate-50/60 hover:border-slate-300 rounded-none cursor-pointer transition-all hover:shadow-sm flex flex-col justify-between"
                     title="Klik untuk melihat arsip dokumen selesai di tabel">
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">Tahap 4</span>
                            <span class="text-xs font-black text-slate-700 bg-slate-200 px-2 py-0.5 rounded-none">
                                {{ $shTrackerSelesai }} Dokumen
                            </span>
                        </div>
                        <h4 class="text-xs font-bold text-slate-900">4. Selesai & Terarsip</h4>
                        <p class="text-[11px] text-slate-600 mt-1 leading-snug">
                            Penilaian disahkan penuh, PDF tersimpan dan nilai resmi tercatat.
                        </p>
                    </div>
                    <div class="mt-2.5 pt-2 border-t border-slate-200/80 flex flex-col gap-0.5 text-[11px]">
                        <span class="font-bold text-emerald-700">
                            {{ $shTrackerSelesai }} Disahkan
                        </span>
                        <span class="font-bold text-slate-500">
                            {{ $shTrackerBelumDisahkan }} Belum disahkan
                        </span>
                    </div>
                </div>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mt-4 items-stretch">
                <!-- Step 1: Penilaian Section Head (To-Do) (Klik: Scroll ke tabel to-do) -->
                <div @click="scrollToSection('section-data-karyawan', 'todo')" 
                     class="p-3.5 border {{ $sectionNeedActionCount > 0 ? 'border-amber-400 bg-amber-50/50 hover:border-amber-500' : 'border-slate-200 bg-slate-50/60' }} rounded-none cursor-pointer transition-all hover:shadow-sm flex flex-col justify-between"
                     title="Klik untuk melihat antrean to-do di tabel">
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">Tahap 1</span>
                            <span class="text-xs font-black {{ $sectionNeedActionCount > 0 ? 'text-amber-800 bg-amber-200' : 'text-slate-600 bg-slate-200' }} px-2 py-0.5 rounded-none">
                                {{ $sectionNeedActionCount }} Orang
                            </span>
                        </div>
                        <h4 class="text-xs font-bold text-slate-900">1. Penilaian Section Head (To-Do)</h4>
                        <p class="text-[11px] text-slate-600 mt-1 leading-snug min-h-[30px]">
                            {{ $sectionBelumCount }} belum dinilai &bull; {{ $sectionDraftCount }} draft tersimpan. Segera selesaikan penilaian.
                        </p>
                    </div>
                    <div class="mt-2.5 pt-2 border-t border-slate-200/80 flex items-center justify-between text-[11px]">
                        <span class="font-bold {{ $sectionNeedActionCount > 0 ? 'text-amber-700' : 'text-slate-400' }}">
                            {{ $sectionNeedActionCount > 0 ? 'Perlu tindakan Anda' : 'Selesai diinput' }}
                        </span>
                        @if($sectionNeedActionCount > 0)
                            <button @click.stop="scrollToSection('section-data-karyawan', 'todo')" type="button" class="text-[#92302e] font-bold hover:underline inline-flex items-center gap-1">
                                Kerjakan &rarr;
                            </button>
                        @endif
                    </div>
                </div>

                <!-- Step 2: Tinjauan Department Head (Klik: Scroll ke tabel filter terkirim) -->
                <div @click="scrollToSection('section-data-karyawan', 'done')" 
                     class="p-3.5 border {{ $waitingDeptCount > 0 ? 'border-blue-300 bg-blue-50/40 hover:border-blue-400' : 'border-slate-200 bg-slate-50/60' }} rounded-none cursor-pointer transition-all hover:shadow-sm flex flex-col justify-between"
                     title="Klik untuk melihat dokumen yang sedang ditinjau Department Head di tabel">
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">Tahap 2</span>
                            <span class="text-xs font-black {{ $waitingDeptCount > 0 ? 'text-blue-800 bg-blue-100' : 'text-slate-600 bg-slate-200' }} px-2 py-0.5 rounded-none">
                                {{ $waitingDeptCount }} Dokumen
                            </span>
                        </div>
                        <h4 class="text-xs font-bold text-slate-900">2. Tinjauan Department Head</h4>
                        <p class="text-[11px] text-slate-600 mt-1 leading-snug min-h-[30px]">
                            Sudah Anda TTD &amp; sedang ditinjau oleh Department Head.
                        </p>
                    </div>
                    <div class="mt-2.5 pt-2 border-t border-slate-200/80 flex items-center justify-between text-[11px]">
                        <span class="font-medium text-slate-500">
                            {{ $waitingDeptCount > 0 ? 'Menunggu review DH' : 'Tidak ada antrean' }}
                        </span>
                        <span class="font-mono text-slate-400 text-[10px]">Alur Verifikasi</span>
                    </div>
                </div>

                <!-- Step 3: Approval Division Head (Khusus Form Section Head 07-006) -->
                <div @click="scrollToSection('section-data-karyawan', 'done')" 
                     class="p-3.5 border {{ $waitingDivCount > 0 ? 'border-indigo-300 bg-indigo-50/40 hover:border-indigo-400' : 'border-slate-200 bg-slate-50/60' }} rounded-none cursor-pointer transition-all hover:shadow-sm flex flex-col justify-between"
                     title="Khusus evaluasi diri Section Head (07-006) yang telah disetujui Dept. Head dan menunggu Division Head">
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">Tahap 3</span>
                            <span class="text-xs font-black {{ $waitingDivCount > 0 ? 'text-indigo-800 bg-indigo-100' : 'text-slate-600 bg-slate-200' }} px-2 py-0.5 rounded-none">
                                {{ $waitingDivCount }} Dokumen
                            </span>
                        </div>
                        <h4 class="text-xs font-bold text-slate-900">3. Approval Division Head</h4>
                        <p class="text-[11px] text-slate-600 mt-1 leading-snug min-h-[30px]">
                            Khusus Form 07-006 (Gol. 4 &amp; SH) disetujui DH, menunggu persetujuan Division Head.
                        </p>
                    </div>
                    <div class="mt-2.5 pt-2 border-t border-slate-200/80 flex items-center justify-between text-[11px]">
                        <span class="font-medium text-slate-500">
                            {{ $waitingDivCount > 0 ? 'Proses persetujuan Div Head' : 'Tidak ada antrean' }}
                        </span>
                        <span class="font-mono text-slate-400 text-[10px]">Khusus Form 07-006</span>
                    </div>
                </div>

                <!-- Step 4: Selesai & Terarsip (Klik: Scroll ke tabel) -->
                <div @click="scrollToSection('section-data-karyawan', 'done')" 
                     class="p-3.5 border {{ $sectionSelesaiCount > 0 ? 'border-emerald-300 bg-emerald-50/40 hover:border-emerald-400' : 'border-slate-200 bg-slate-50/60' }} rounded-none cursor-pointer transition-all hover:shadow-sm flex flex-col justify-between"
                     title="Klik untuk melihat arsip dokumen selesai di tabel">
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">Tahap 4</span>
                            <span class="text-xs font-black {{ $sectionSelesaiCount > 0 ? 'text-emerald-800 bg-emerald-100' : 'text-slate-600 bg-slate-200' }} px-2 py-0.5 rounded-none">
                                {{ $sectionSelesaiCount }} Dokumen
                            </span>
                        </div>
                        <h4 class="text-xs font-bold text-slate-900">4. Selesai & Terarsip</h4>
                        <p class="text-[11px] text-slate-600 mt-1 leading-snug min-h-[30px]">
                            KJ 1-5 tuntas di Dept Head dan Form 07-006 disahkan final di Div Head.
                        </p>
                    </div>
                    <div class="mt-2.5 pt-2 border-t border-slate-200/80 flex items-center justify-between text-[11px]">
                        <span class="font-bold text-emerald-700">
                            {{ $sectionSelesaiCount > 0 ? 'Arsip final tersedia' : 'Belum selesai final' }}
                        </span>
                        <span class="font-mono text-emerald-600 text-[10px]">Tuntas</span>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <!-- 5. Matrix Tables Row: Target Penilaian & Hasil Section Head per KJ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mt-6">
        
        <!-- Table 1: Target Penilaian (Target Kuota per KJ) -->
        <div id="chart-target-kategori" class="bg-white rounded-none p-5 border border-slate-300 shadow-sm scroll-mt-20 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between pb-3 border-b border-slate-200 mb-4">
                    <div class="flex items-center gap-2.5">
                        <span class="w-2.5 h-2.5 bg-[#92302e]"></span>
                        <h3 class="text-sm font-bold text-slate-900 tracking-tight">
                            Target Penilaian <span class="text-slate-400 font-normal text-xs">(berdasarkan kategori &amp; KJ)</span>
                        </h3>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full border-collapse border border-slate-300 text-center select-none">
                        <thead>
                            <tr class="text-slate-800 text-xs">
                                <th class="w-24 bg-[#fff2cc] font-extrabold px-3 py-2.5 text-center border border-slate-300 tracking-wider">
                                    KJ \ PK
                                </th>
                                <th class="w-12 bg-[#d9ead3] font-bold px-2.5 py-2.5 text-center border border-slate-300">S</th>
                                <th class="w-12 bg-[#d9ead3] font-bold px-2.5 py-2.5 text-center border border-slate-300">A</th>
                                <th class="w-12 bg-[#d9ead3] font-bold px-2.5 py-2.5 text-center border border-slate-300">B</th>
                                <th class="w-12 bg-[#d9ead3] font-bold px-2.5 py-2.5 text-center border border-slate-300">C</th>
                                <th class="w-12 bg-[#d9ead3] font-bold px-2.5 py-2.5 text-center border border-slate-300">K</th>
                                <th class="bg-[#d9ead3] font-extrabold px-4 py-2.5 text-center border border-slate-300 whitespace-nowrap">
                                    Total Karyawan
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($sectionTargetKjMatrix ?? [] as $kjName => $row)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="bg-[#ffe599] text-slate-900 font-extrabold text-xs px-3 py-2.5 text-center border border-slate-300 whitespace-nowrap">
                                    {{ $kjName }}
                                </td>
                                <td class="bg-white text-xs px-2.5 py-2.5 text-center border border-slate-300 {{ ($row['S'] ?? 0) > 0 ? 'text-red-600 font-black text-sm' : 'text-slate-600 font-medium' }}">
                                    {{ $row['S'] ?? 0 }}
                                </td>
                                <td class="bg-white text-xs px-2.5 py-2.5 text-center border border-slate-300 {{ ($row['A'] ?? 0) > 0 ? 'text-red-600 font-black text-sm' : 'text-slate-600 font-medium' }}">
                                    {{ $row['A'] ?? 0 }}
                                </td>
                                <td class="bg-white text-xs px-2.5 py-2.5 text-center border border-slate-300 {{ ($row['B'] ?? 0) > 0 ? 'text-red-600 font-black text-sm' : 'text-slate-600 font-medium' }}">
                                    {{ $row['B'] ?? 0 }}
                                </td>
                                <td class="bg-white text-xs px-2.5 py-2.5 text-center border border-slate-300 {{ ($row['C'] ?? 0) > 0 ? 'text-red-600 font-black text-sm' : 'text-slate-600 font-medium' }}">
                                    {{ $row['C'] ?? 0 }}
                                </td>
                                <td class="bg-white text-xs px-2.5 py-2.5 text-center border border-slate-300 {{ ($row['K'] ?? 0) > 0 ? 'text-red-600 font-black text-sm' : 'text-slate-600 font-medium' }}">
                                    {{ $row['K'] ?? 0 }}
                                </td>
                                <td class="bg-[#cfe2f3] text-slate-900 font-extrabold text-xs px-4 py-2.5 text-center border border-slate-300 whitespace-nowrap">
                                    {{ $row['total'] ?? 0 }}
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-xs text-slate-400 py-4 text-center border border-slate-300">
                                    Belum ada data target per KJ
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="bg-[#0b5394] text-white">
                                <td class="bg-[#0b5394] text-white font-black text-xs px-3 py-2.5 text-center border border-[#0b5394]/70 uppercase tracking-wider">
                                    TOTAL
                                </td>
                                <td class="bg-[#0b5394] text-white font-black text-xs px-2.5 py-2.5 text-center border border-[#0b5394]/70">
                                    {{ $sectionTargetKjSummary['S'] ?? ($sectionTargetCounts['S'] ?? 0) }}
                                </td>
                                <td class="bg-[#0b5394] text-white font-black text-xs px-2.5 py-2.5 text-center border border-[#0b5394]/70">
                                    {{ $sectionTargetKjSummary['A'] ?? ($sectionTargetCounts['A'] ?? 0) }}
                                </td>
                                <td class="bg-[#0b5394] text-white font-black text-xs px-2.5 py-2.5 text-center border border-[#0b5394]/70">
                                    {{ $sectionTargetKjSummary['B'] ?? ($sectionTargetCounts['B'] ?? 0) }}
                                </td>
                                <td class="bg-[#0b5394] text-white font-black text-xs px-2.5 py-2.5 text-center border border-[#0b5394]/70">
                                    {{ $sectionTargetKjSummary['C'] ?? ($sectionTargetCounts['C'] ?? 0) }}
                                </td>
                                <td class="bg-[#0b5394] text-white font-black text-xs px-2.5 py-2.5 text-center border border-[#0b5394]/70">
                                    {{ $sectionTargetKjSummary['K'] ?? ($sectionTargetCounts['K'] ?? 0) }}
                                </td>
                                <td class="bg-[#084175] text-white font-black text-sm px-4 py-2.5 text-center border border-[#084175] whitespace-nowrap">
                                    {{ $sectionTargetKjSummary['total'] ?? ($totalTargetSection ?? 0) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Table 2: Hasil Section Head (Realisasi Dinilai Section Head per KJ) -->
        <div class="bg-white rounded-none p-5 border border-slate-300 shadow-sm flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between pb-3 border-b border-slate-200 mb-4">
                    <div class="flex items-center gap-2.5">
                        <span class="w-2.5 h-2.5 bg-emerald-600"></span>
                        <h3 class="text-sm font-bold text-slate-900 tracking-tight">
                            Hasil Section Head <span class="text-slate-400 font-normal text-xs">(nilai dari anda per KJ)</span>
                        </h3>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full border-collapse border border-slate-300 text-center select-none">
                        <thead>
                            <tr class="text-slate-800 text-xs">
                                <th class="w-24 bg-[#fff2cc] font-extrabold px-3 py-2.5 text-center border border-slate-300 tracking-wider">
                                    KJ \ PK
                                </th>
                                <th class="w-12 bg-[#d9ead3] font-bold px-2.5 py-2.5 text-center border border-slate-300">S</th>
                                <th class="w-12 bg-[#d9ead3] font-bold px-2.5 py-2.5 text-center border border-slate-300">A</th>
                                <th class="w-12 bg-[#d9ead3] font-bold px-2.5 py-2.5 text-center border border-slate-300">B</th>
                                <th class="w-12 bg-[#d9ead3] font-bold px-2.5 py-2.5 text-center border border-slate-300">C</th>
                                <th class="w-12 bg-[#d9ead3] font-bold px-2.5 py-2.5 text-center border border-slate-300">K</th>
                                <th class="bg-[#d9ead3] font-extrabold px-4 py-2.5 text-center border border-slate-300 whitespace-nowrap">
                                    Total Dinilai
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($sectionActualKjMatrix ?? [] as $kjName => $row)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="bg-[#ffe599] text-slate-900 font-extrabold text-xs px-3 py-2.5 text-center border border-slate-300 whitespace-nowrap">
                                    {{ $kjName }}
                                </td>
                                <td class="bg-white text-xs px-2.5 py-2.5 text-center border border-slate-300 {{ ($row['S'] ?? 0) > 0 ? 'text-red-600 font-black text-sm' : 'text-slate-600 font-medium' }}">
                                    {{ $row['S'] ?? 0 }}
                                </td>
                                <td class="bg-white text-xs px-2.5 py-2.5 text-center border border-slate-300 {{ ($row['A'] ?? 0) > 0 ? 'text-red-600 font-black text-sm' : 'text-slate-600 font-medium' }}">
                                    {{ $row['A'] ?? 0 }}
                                </td>
                                <td class="bg-white text-xs px-2.5 py-2.5 text-center border border-slate-300 {{ ($row['B'] ?? 0) > 0 ? 'text-red-600 font-black text-sm' : 'text-slate-600 font-medium' }}">
                                    {{ $row['B'] ?? 0 }}
                                </td>
                                <td class="bg-white text-xs px-2.5 py-2.5 text-center border border-slate-300 {{ ($row['C'] ?? 0) > 0 ? 'text-red-600 font-black text-sm' : 'text-slate-600 font-medium' }}">
                                    {{ $row['C'] ?? 0 }}
                                </td>
                                <td class="bg-white text-xs px-2.5 py-2.5 text-center border border-slate-300 {{ ($row['K'] ?? 0) > 0 ? 'text-red-600 font-black text-sm' : 'text-slate-600 font-medium' }}">
                                    {{ $row['K'] ?? 0 }}
                                </td>
                                <td class="bg-[#cfe2f3] text-slate-900 font-extrabold text-xs px-4 py-2.5 text-center border border-slate-300 whitespace-nowrap">
                                    {{ $row['total'] ?? 0 }}
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-xs text-slate-400 py-4 text-center border border-slate-300">
                                    Belum ada data realisasi dinilai per KJ
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="bg-[#0b5394] text-white">
                                <td class="bg-[#0b5394] text-white font-black text-xs px-3 py-2.5 text-center border border-[#0b5394]/70 uppercase tracking-wider">
                                    TOTAL
                                </td>
                                <td class="bg-[#0b5394] text-white font-black text-xs px-2.5 py-2.5 text-center border border-[#0b5394]/70">
                                    {{ $sectionActualKjSummary['S'] ?? ($sectionGradeCounts['S'] ?? 0) }}
                                </td>
                                <td class="bg-[#0b5394] text-white font-black text-xs px-2.5 py-2.5 text-center border border-[#0b5394]/70">
                                    {{ $sectionActualKjSummary['A'] ?? ($sectionGradeCounts['A'] ?? 0) }}
                                </td>
                                <td class="bg-[#0b5394] text-white font-black text-xs px-2.5 py-2.5 text-center border border-[#0b5394]/70">
                                    {{ $sectionActualKjSummary['B'] ?? ($sectionGradeCounts['B'] ?? 0) }}
                                </td>
                                <td class="bg-[#0b5394] text-white font-black text-xs px-2.5 py-2.5 text-center border border-[#0b5394]/70">
                                    {{ $sectionActualKjSummary['C'] ?? ($sectionGradeCounts['C'] ?? 0) }}
                                </td>
                                <td class="bg-[#0b5394] text-white font-black text-xs px-2.5 py-2.5 text-center border border-[#0b5394]/70">
                                    {{ $sectionActualKjSummary['K'] ?? ($sectionGradeCounts['K'] ?? 0) }}
                                </td>
                                <td class="bg-[#084175] text-white font-black text-sm px-4 py-2.5 text-center border border-[#084175] whitespace-nowrap">
                                    {{ $sectionActualKjSummary['total'] ?? $sectionTotalDinilai }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

    </div>



    <!-- 7. Bottom Table: Data Karyawan & Penilaian (Lengkap dengan Tab Filter & Pencarian) -->
    <div id="section-data-karyawan" class="bg-white rounded-none border border-slate-300 shadow-sm overflow-hidden scroll-mt-20">
        <!-- Table Header & Filter Toolbar -->
        <div class="p-4 border-b border-slate-200">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <h3 class="text-sm font-bold text-slate-900 whitespace-nowrap">
                        Data Karyawan & Penilaian
                    </h3>
                    <span class="text-xs px-2.5 py-0.5 rounded-none bg-slate-100 text-slate-700 font-bold border border-slate-200 whitespace-nowrap">
                        {{ count($sectionSubordinates) }} Karyawan
                    </span>
                </div>

                <!-- Toolbar: Dropdown Filter Status + Live Search Box Sejajar -->
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 w-full md:w-auto">
                    <!-- Dropdown Filter Status Penilaian (Disamping Cari Nama, NRP, Jabatan) -->
                    <div class="w-full sm:w-60">
                        <select x-model="activeTab" 
                                class="w-full py-1.5 px-3 text-xs font-semibold text-slate-700 rounded-none border border-slate-300 hover:border-slate-400 bg-white focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-xs">
                            <option value="all">Semua ({{ count($sectionSubordinates) }})</option>
                            <option value="todo">Perlu Tindakan / To-Do ({{ $sectionNeedActionCount }})</option>
                            @if($hasForemanSubordinates)
                                <option value="foreman">Menunggu Foreman ({{ $foremanStageCounts['tahap1'] ?? 0 }})</option>
                            @endif
                            <option value="draft">Draft Tersimpan ({{ $sectionDraftCount }})</option>
                            <option value="done">Sudah Terkirim / Selesai ({{ $sectionDoneCount }})</option>
                            <option value="evaluated">Sudah Dinilai ({{ $sectionSudahDinilaiCount ?? ($sectionDraftCount + $sectionDoneCount) }})</option>
                        </select>
                    </div>

                    <!-- Live Search Box -->
                    <div class="w-full sm:w-72">
                        <div class="relative">
                            <input type="text" 
                                   x-model="searchQuery" 
                                   placeholder="Cari nama, NRP, position..." 
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
                    @forelse($sectionSubordinates as $sub)
                    <tr class="hover:bg-slate-50 transition-colors"
                        x-show="matchesFilter({{ json_encode($sub) }})">
                        <td class="py-3 px-4 font-semibold text-slate-900 border border-slate-200">
                            <div>
                                <span class="block font-bold">{{ $sub['nama_lengkap'] }}</span>
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
                            {{ $sub['sub_gol'] }}
                        </td>
                        <td class="py-3 px-4 text-slate-600 font-mono text-[11px] border border-slate-200">
                            {{ \App\Services\GoogleSyncService::formatMusashiDate($sub['tgl_masuk'] ?? '') }}
                        </td>
                        <td class="py-3 px-4 border border-slate-200">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-none text-[10px] font-bold border {{ $sub['action_type'] === 'sudah_dikirim' ? 'bg-blue-50 text-blue-800 border-blue-200' : ($sub['action_type'] === 'draft' ? 'bg-amber-50 text-amber-800 border-amber-200' : ($sub['action_type'] === 'revisi' ? 'bg-rose-50 text-rose-800 border-rose-200' : ($sub['action_type'] === 'todo' ? 'bg-amber-50 text-amber-800 border-amber-200' : 'bg-slate-100 text-slate-700 border-slate-200'))) }}">
                                {{ $sub['stage_label'] }}
                            </span>
                        </td>
                        <td class="py-3 px-4 text-center border border-slate-200">
                            @if($sub['is_evaluated'] || in_array($sub['status'], ['menunggu_dept_head', 'menunggu_div_head', 'selesai']))
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
                            @if($sub['score'] !== '-')
                                <span class="font-bold text-slate-900">{{ $sub['score'] }}</span>
                                <span class="ml-1 text-[10px] font-black px-1.5 py-0.2 rounded-none bg-slate-100 border border-slate-200 text-slate-800">{{ $sub['grade'] }}</span>
                            @else
                                <span class="text-slate-400">-</span>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-center border border-slate-200">
                            @if($sub['doc_id'])
                                <a href="{{ route('penilaian.show.sechead', $sub['doc_id']) }}" 
                                   class="inline-block px-2.5 py-1 rounded-none text-xs font-bold transition-colors {{ in_array($sub['status'], ['draft', 'menunggu_section_head', 'belum_ada', 'revisi']) ? 'bg-[#92302e] text-white hover:bg-[#7e2524]' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 border border-slate-300' }}">
                                    {{ in_array($sub['status'], ['draft', 'menunggu_section_head', 'belum_ada', 'revisi']) ? 'Nilai' : 'Lihat' }}
                                </a>
                            @else
                                <form method="POST" action="{{ route('penilaian.buka') }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="nrp" value="{{ $sub['nrp'] }}">
                                    <button type="submit" class="inline-block px-2.5 py-1 rounded-none text-xs font-bold bg-[#92302e] text-white hover:bg-[#7e2524] transition-colors">
                                        Nilai
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="py-8 text-center text-slate-400 border border-slate-200">
                            Belum ada data bawahan. Silakan kelola data karyawan pada menu <a href="{{ route('karyawan.index') }}" class="font-bold text-[#92302e] hover:underline">Data Karyawan</a>.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-3 bg-slate-50 border-t border-slate-200 flex items-center justify-end text-xs text-slate-500">
            <div class="flex items-center gap-3">
                <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-none bg-emerald-500 inline-block"></span> Sudah Dinilai</span>
                <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-none bg-amber-500 inline-block"></span> Draft / To-Do</span>
                <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-none bg-rose-500 inline-block"></span> Belum Dinilai</span>
            </div>
        </div>
    </div>


    <!-- 8. Panduan Alur Kerja Section Head (Petunjuk Pelaksanaan Penilaian Langsung) -->
    <div class="bg-white rounded-none p-5 border border-slate-300 shadow-sm">
        <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider mb-3">
            Panduan & Prosedur Evaluasi Section Head
        </h4>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-xs text-slate-600">
            <div class="p-3 bg-slate-50 border border-slate-200 rounded-none">
                <span class="font-bold text-[#92302e] block mb-1">Langkah 1: Identifikasi</span>
                <p>Cek daftar bawahan Anda pada tabel di atas. Perhatikan karyawan dengan status <em>Belum Dinilai</em> atau <em>Perlu Tindakan</em>.</p>
            </div>
            <div class="p-3 bg-slate-50 border border-slate-200 rounded-none">
                <span class="font-bold text-[#92302e] block mb-1">Langkah 2: Evaluasi Nilai</span>
                <p>Klik tombol <strong>Nilai</strong> untuk mengisi form penilaian karyawan sesuai kriteria prestasi kerja dan kompetensi.</p>
            </div>
            <div class="p-3 bg-slate-50 border border-slate-200 rounded-none">
                <span class="font-bold text-[#92302e] block mb-1">Langkah 3: TTD & Kirim</span>
                <p>Simpan sebagai <em>Draft</em> jika belum tuntas, atau tanda tangani digital lalu kirim berkas untuk diteruskan ke Department Head.</p>
            </div>
            <div class="p-3 bg-slate-50 border border-slate-200 rounded-none">
                <span class="font-bold text-[#92302e] block mb-1">Langkah 4: Pantau Alur</span>
                <p>
                    @if($hasForemanSubordinates)
                        Pantau status dokumen pada pipeline tracker di atas hingga mendapat persetujuan akhir dari Department Head.
                    @else
                        Bawahan KJ 1-5 selesai saat disahkan Department Head, sedangkan form Section Head (07-006) selesai setelah disahkan Division Head.
                    @endif
                </p>
            </div>
        </div>
    </div>

</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Chart 1: Target Donut
        const targetEl = document.getElementById('targetSectionDirectChart') || document.getElementById('targetSectionChart');
        if (targetEl) {
            const ctxTarget = targetEl.getContext('2d');
            const targetData = @json($sectionTargetCounts);

            const targetValues = [
                targetData['S'] || 0,
                targetData['A'] || 0,
                targetData['B'] || 0,
                targetData['C'] || 0,
                targetData['K'] || 0
            ];

            new Chart(ctxTarget, {
                type: 'doughnut',
                data: {
                    labels: ['S (Sangat Baik)', 'A (Baik)', 'B (Cukup)', 'C (Kurang)', 'K (Kurang Sekali)'],
                    datasets: [{
                        data: targetValues.some(v => v > 0) ? targetValues : [1],
                        backgroundColor: [
                            '#10b981', // S - Green
                            '#2563eb', // A - Blue
                            '#f59e0b', // B - Yellow
                            '#f97316', // C - Orange
                            '#ef4444'  // K - Red
                        ],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return ` ${context.label}: ${context.raw} Target`;
                                }
                            }
                        }
                    },
                    cutout: '72%'
                }
            });
        }

        // Chart: Hasil Foreman Donut (jika ada)
        const foremanEl = document.getElementById('resultForemanChart');
        if (foremanEl) {
            const ctxForeman = foremanEl.getContext('2d');
            const foremanData = @json($foremanGradeCounts);

            const foremanValues = [
                foremanData['S'] || 0,
                foremanData['A'] || 0,
                foremanData['B'] || 0,
                foremanData['C'] || 0,
                foremanData['K'] || 0
            ];

            const hasForemanResults = foremanValues.some(v => v > 0);

            new Chart(ctxForeman, {
                type: 'doughnut',
                data: {
                    labels: ['S (Sangat Baik)', 'A (Baik)', 'B (Cukup)', 'C (Kurang)', 'K (Kurang Sekali)'],
                    datasets: [{
                        data: hasForemanResults ? foremanValues : [1],
                        backgroundColor: hasForemanResults ? [
                            '#10b981', // S - Green
                            '#2563eb', // A - Blue
                            '#f59e0b', // B - Yellow
                            '#f97316', // C - Orange
                            '#ef4444'  // K - Red
                        ] : ['#e2e8f0'],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    if (!hasForemanResults) return ' Belum ada data';
                                    return ` ${context.label}: ${context.raw} Dinilai Foreman`;
                                }
                            }
                        }
                    },
                    cutout: '72%'
                }
            });
        }

        // Chart 2: Result Donut (dari Section Head)
        const resultEl = document.getElementById('resultSectionDirectChart') || document.getElementById('resultSectionChart');
        if (resultEl) {
            const ctxResult = resultEl.getContext('2d');
            const resultData = @json($sectionGradeCounts);

            const resultValues = [
                resultData['S'] || 0,
                resultData['A'] || 0,
                resultData['B'] || 0,
                resultData['C'] || 0,
                resultData['K'] || 0
            ];

            const hasResults = resultValues.some(v => v > 0);

            new Chart(ctxResult, {
                type: 'doughnut',
                data: {
                    labels: ['S (Sangat Baik)', 'A (Baik)', 'B (Cukup)', 'C (Kurang)', 'K (Kurang Sekali)'],
                    datasets: [{
                        data: hasResults ? resultValues : [1],
                        backgroundColor: hasResults ? [
                            '#10b981', // S - Green
                            '#2563eb', // A - Blue
                            '#f59e0b', // B - Yellow
                            '#f97316', // C - Orange
                            '#ef4444'  // K - Red
                        ] : ['#e2e8f0'],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    if (!hasResults) return ' Belum ada data';
                                    return ` ${context.label}: ${context.raw} Dinilai`;
                                }
                            }
                        }
                    },
                    cutout: '72%'
                }
            });
        }
    });
</script>
@endpush
