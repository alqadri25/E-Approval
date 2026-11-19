<!-- ============================================================== -->
<!-- FOREMAN DASHBOARD VIEW (Informative & Sharp Industrial UI)     -->
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
        if (this.activeTab === 'todo' && sub.action_type === 'sudah_dikirim') return false;
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

    <!-- 1. Top Header Card (Merah Musashi) -->
    <div class="bg-gradient-to-r from-[#962226] via-[#92302e] to-[#781b1f] text-white rounded-none p-5 sm:p-6 border border-[#781b1f] shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight">
                Dashboard
            </h1>
            <span class="sr-only">Dashboard Foreman</span>
            <p class="text-xs text-red-100 font-medium mt-1">
                Selamat Datang {{ $user->nama }}
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

    <!-- 2. Live Action Banner: Yang Sudah Dilakukan & Yang Mau Dilakukan (Informative Status) -->
    @if($foremanRevisiCount > 0)
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
                            {{ $foremanRevisiCount }} Dokumen Dikembalikan oleh Section Head
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
    @elseif($foremanNeedActionCount > 0)
        <!-- Urgent Action Banner: Foreman To-Do (Desain sama dengan Section Head tanpa Foreman) -->
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
                            {{ $foremanNeedActionCount }} Karyawan Menunggu Penilaian Anda!
                        </span>
                    </div>
                    <p class="text-xs text-amber-900 mt-1">
                        Mohon segera lakukan pengisian penilaian kinerja &amp; tanda tangan digital untuk diteruskan ke Section Head.
                    </p>
                </div>
            </div>
            <a href="{{ route('penilaian.cari') }}" 
               class="px-4 py-2 rounded-none bg-[#92302e] hover:bg-[#7e2524] text-white font-bold text-xs shadow-sm whitespace-nowrap self-start md:self-center transition-colors">
                Mulai Penilaian &rarr;
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
                            Tugas Foreman Selesai 100%
                        </span>
                        <span class="text-xs font-bold text-emerald-950">
                            Seluruh {{ count($foremanSubordinates) }} Karyawan Bawahan Telah Selesai Dinilai & Dikirim
                        </span>
                    </div>
                    <p class="text-xs text-emerald-800 mt-1">
                        Semua berkas penilaian karyawan telah ditandatangani digital oleh Anda dan saat ini sedang dalam proses tinjauan Section Head / Department Head.
                    </p>
                </div>
            </div>
            <a href="{{ route('penilaian.riwayat') }}" 
               class="px-3.5 py-1.5 rounded-none bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs shadow-sm whitespace-nowrap self-start md:self-center">
                Lihat Riwayat & Rekap &rarr;
            </a>
        </div>
    @endif

    <!-- 3. Key Stat Cards in a Row (4 Columns Informing What Has Been Done & What Needs To Be Done) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
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
                    <p class="text-2xl font-black text-slate-900 mt-1.5 leading-none">{{ count($foremanSubordinates) }}</p>
                    <p class="text-[11px] text-slate-500 mt-1 leading-none">Anggota Foreman</p>
                </div>
            </div>
            <span class="text-[10px] font-bold px-2 py-1 bg-slate-100 text-slate-600 rounded-none border border-slate-200">
                100% Tim
            </span>
        </div>

        <!-- 2. Total Target (Klik: Scroll ke grafik Target Penilaian) -->
        <div @click="scrollToSection('chart-target-kategori')" 
             class="bg-white rounded-none p-4 border border-slate-300 hover:border-[#92302e] hover:shadow-md cursor-pointer transition-all flex items-center justify-between group"
             title="Klik untuk melihat grafik Target Penilaian berdasarkan kategori">
            <div class="flex items-center gap-3.5">
                <div class="w-10 h-10 rounded-none bg-[#fbe8e8] text-[#92302e] font-black flex items-center justify-center text-sm flex-shrink-0 border border-[#92302e]/20 group-hover:bg-[#92302e] group-hover:text-white transition-colors">
                    T
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-semibold leading-none group-hover:text-[#92302e] transition-colors">Total Target</p>
                    <p class="text-2xl font-black text-slate-900 mt-1.5 leading-none">{{ count($foremanSubordinates) }}</p>
                    <p class="text-[11px] text-slate-500 mt-1 leading-none">Karyawan yang dinilai</p>
                </div>
            </div>
            <span class="text-[10px] font-bold px-2 py-1 bg-blue-50 text-blue-700 rounded-none border border-blue-200">
                Target Kerja
            </span>
        </div>

        <!-- 3. Total Dinilai (Klik: Scroll ke tabel filter sudah dinilai) -->
        <div @click="scrollToSection('section-data-karyawan', 'evaluated')" 
             class="bg-white rounded-none p-4 border border-slate-300 hover:border-emerald-600 hover:shadow-md cursor-pointer transition-all flex items-center justify-between group"
             title="Klik untuk melihat karyawan yang sudah dinilai (draft & terkirim) di tabel">
            <div class="flex items-center gap-3.5">
                <div class="w-10 h-10 rounded-none bg-emerald-50 text-emerald-700 font-black flex items-center justify-center text-sm flex-shrink-0 border border-emerald-300 group-hover:bg-emerald-600 group-hover:text-white transition-colors">
                    ✓
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-semibold leading-none group-hover:text-emerald-700 transition-colors">Total Dinilai</p>
                    <p class="text-2xl font-black text-slate-900 mt-1.5 leading-none">{{ $stats['total_dinilai'] }}</p>
                    <p class="text-[11px] text-slate-500 font-medium mt-1 leading-none">
                        {{ $foremanDraftCount ?? 0 }} draft, {{ $foremanBelumCount ?? 0 }} belum dinilai
                    </p>
                </div>
            </div>
            <span class="text-[10px] font-bold px-2 py-1 bg-emerald-50 text-emerald-700 rounded-none border border-emerald-200">
                Nilai Terinput
            </span>
        </div>

        <!-- 4. Terkirim ke Section Head (Klik: Scroll ke tabel filter terkirim) -->
        <div @click="scrollToSection('section-data-karyawan', 'done')" 
             class="bg-white rounded-none p-4 border border-slate-300 hover:border-blue-600 hover:shadow-md cursor-pointer transition-all flex items-center justify-between group"
             title="Klik untuk melihat dokumen yang telah terkirim ke Section Head di tabel">
            <div class="flex items-center gap-3.5">
                <div class="w-10 h-10 rounded-none bg-blue-50 text-blue-700 font-black flex items-center justify-center text-sm flex-shrink-0 border border-blue-300 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                    ✈
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-semibold leading-none group-hover:text-blue-700 transition-colors">Terkirim ke Section Head</p>
                    <p class="text-2xl font-black text-slate-900 mt-1.5 leading-none">{{ $foremanDoneCount }}</p>
                    <p class="text-[11px] text-blue-600 font-bold mt-1 leading-none">
                        {{ $foremanNeedActionCount }} Perlu Tindakan (To-Do)
                    </p>
                </div>
            </div>
            <span class="text-[10px] font-bold px-2 py-1 {{ $foremanNeedActionCount > 0 ? 'bg-amber-100 text-amber-800 border-amber-300' : 'bg-emerald-100 text-emerald-800 border-emerald-300' }} rounded-none border">
                {{ $foremanNeedActionCount > 0 ? 'To-Do' : 'Tuntas' }}
            </span>
        </div>
    </div>

    <!-- 4. Visual Workflow Pipeline Tracker (Tahapan Penilaian Bawahan) -->
    <div class="bg-white rounded-none p-5 border border-slate-300 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-slate-200 gap-2">
            <div>
                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wide">
                    Pelacakan Alur Status Dokumen Bawahan
                </h3>
                <p class="text-xs text-slate-500">
                    Memantau posisi dokumen seluruh {{ count($foremanSubordinates) }} karyawan bawahan Anda di setiap tahapan sistem
                </p>
            </div>
            <span class="text-xs font-mono font-bold text-slate-500 bg-slate-100 px-2.5 py-1 rounded-none border border-slate-200 self-start sm:self-auto">
                {{ $foremanDoneCount }}/{{ count($foremanSubordinates) }} Telah Melewati Foreman
            </span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mt-4 items-stretch">
            <!-- Step 1: Penilaian Foreman (To-Do) (Klik: Scroll ke tabel to-do) -->
            <div @click="scrollToSection('section-data-karyawan', 'todo')" 
                 class="p-3.5 border {{ $foremanNeedActionCount > 0 ? 'border-amber-400 bg-amber-50/50 hover:border-amber-500' : 'border-slate-200 bg-slate-50/60' }} rounded-none cursor-pointer transition-all hover:shadow-sm flex flex-col justify-between"
                 title="Klik untuk melihat antrean to-do di tabel">
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">Tahap 1</span>
                        <span class="text-xs font-black {{ $foremanNeedActionCount > 0 ? 'text-amber-800 bg-amber-200' : 'text-slate-600 bg-slate-200' }} px-2 py-0.5 rounded-none">
                            {{ $foremanNeedActionCount }} Orang
                        </span>
                    </div>
                    <h4 class="text-xs font-bold text-slate-900">1. Penilaian Foreman (To-Do)</h4>
                    <p class="text-[11px] text-slate-600 mt-1 leading-snug">
                        {{ $foremanBelumCount }} belum dinilai &bull; {{ $foremanDraftCount }} draft tersimpan.
                    </p>
                </div>
                <div class="mt-2.5 pt-2 border-t border-slate-200/80 flex items-center justify-between text-[11px]">
                    <span class="font-bold {{ $foremanNeedActionCount > 0 ? 'text-amber-700' : 'text-slate-400' }}">
                        {{ $foremanNeedActionCount > 0 ? 'Perlu tindakan Anda' : 'Selesai diinput' }}
                    </span>
                    @if($foremanNeedActionCount > 0)
                        <button @click.stop="scrollToSection('section-data-karyawan', 'todo')" type="button" class="text-[#92302e] font-bold hover:underline inline-flex items-center gap-1">
                            Kerjakan &rarr;
                        </button>
                    @endif
                </div>
            </div>

            <!-- Step 2: Tinjauan Section Head (Klik: Scroll ke tabel filter terkirim) -->
            @php
                $waitingSectionCount = count(array_filter($foremanSubordinates, fn($s) => $s['status'] === 'menunggu_section_head'));
            @endphp
            <div @click="scrollToSection('section-data-karyawan', 'done')" 
                 class="p-3.5 border {{ $waitingSectionCount > 0 ? 'border-blue-300 bg-blue-50/40 hover:border-blue-400' : 'border-slate-200 bg-slate-50/60' }} rounded-none cursor-pointer transition-all hover:shadow-sm flex flex-col justify-between"
                 title="Klik untuk melihat dokumen yang sedang ditinjau Section Head di tabel">
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">Tahap 2</span>
                        <span class="text-xs font-black {{ $waitingSectionCount > 0 ? 'text-blue-800 bg-blue-100' : 'text-slate-600 bg-slate-200' }} px-2 py-0.5 rounded-none">
                            {{ $waitingSectionCount }} Dokumen
                        </span>
                    </div>
                    <h4 class="text-xs font-bold text-slate-900">2. Tinjauan Section Head</h4>
                    <p class="text-[11px] text-slate-600 mt-1 leading-snug">
                        Dokumen telah Anda tanda tangani dan sedang ditinjau Section Head.
                    </p>
                </div>
                <div class="mt-2.5 pt-2 border-t border-slate-200/80 flex items-center justify-between text-[11px]">
                    <span class="font-medium text-slate-500">
                        {{ $waitingSectionCount > 0 ? 'Menunggu review SH' : 'Tidak ada antrean' }}
                    </span>
                    <span class="font-mono text-slate-400 text-[10px]">Alur Verifikasi</span>
                </div>
            </div>

            <!-- Step 3: Approval Dept Head (Klik: Scroll ke tabel) -->
            @php
                $waitingDeptCount = count(array_filter($foremanSubordinates, fn($s) => in_array($s['status'], ['menunggu_dept_head', 'menunggu_div_head'])));
            @endphp
            <div @click="scrollToSection('section-data-karyawan', 'done')" 
                 class="p-3.5 border {{ $waitingDeptCount > 0 ? 'border-indigo-300 bg-indigo-50/40 hover:border-indigo-400' : 'border-slate-200 bg-slate-50/60' }} rounded-none cursor-pointer transition-all hover:shadow-sm flex flex-col justify-between"
                 title="Klik untuk melihat dokumen di tabel">
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">Tahap 3</span>
                        <span class="text-xs font-black {{ $waitingDeptCount > 0 ? 'text-indigo-800 bg-indigo-100' : 'text-slate-600 bg-slate-200' }} px-2 py-0.5 rounded-none">
                            {{ $waitingDeptCount }} Dokumen
                        </span>
                    </div>
                    <h4 class="text-xs font-bold text-slate-900">3. Approval Dept Head</h4>
                    <p class="text-[11px] text-slate-600 mt-1 leading-snug">
                        Telah disetujui Section Head, menunggu persetujuan Department Head.
                    </p>
                </div>
                <div class="mt-2.5 pt-2 border-t border-slate-200/80 flex items-center justify-between text-[11px]">
                    <span class="font-medium text-slate-500">
                        {{ $waitingDeptCount > 0 ? 'Proses persetujuan DH' : 'Tidak ada antrean' }}
                    </span>
                    <span class="font-mono text-slate-400 text-[10px]">Tingkat Akhir</span>
                </div>
            </div>

            <!-- Step 4: Selesai & Terarsip (Klik: Scroll ke tabel) -->
            <div @click="scrollToSection('section-data-karyawan', 'done')" 
                 class="p-3.5 border {{ $foremanSelesaiCount > 0 ? 'border-emerald-300 bg-emerald-50/40 hover:border-emerald-400' : 'border-slate-200 bg-slate-50/60' }} rounded-none cursor-pointer transition-all hover:shadow-sm flex flex-col justify-between"
                 title="Klik untuk melihat arsip dokumen selesai di tabel">
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">Tahap 4</span>
                        <span class="text-xs font-black {{ $foremanSelesaiCount > 0 ? 'text-emerald-800 bg-emerald-100' : 'text-slate-600 bg-slate-200' }} px-2 py-0.5 rounded-none">
                            {{ $foremanSelesaiCount }} Dokumen
                        </span>
                    </div>
                    <h4 class="text-xs font-bold text-slate-900">4. Selesai & Terarsip</h4>
                    <p class="text-[11px] text-slate-600 mt-1 leading-snug">
                        Penilaian disahkan penuh, PDF tersimpan dan nilai resmi tercatat.
                    </p>
                </div>
                <div class="mt-2.5 pt-2 border-t border-slate-200/80 flex items-center justify-between text-[11px]">
                    <span class="font-bold text-emerald-700">
                        {{ $foremanSelesaiCount > 0 ? 'Arsip final tersedia' : 'Belum selesai final' }}
                    </span>
                    <span class="font-mono text-emerald-600 text-[10px]">Tuntas</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. Matrix Tables Row: Target Penilaian & Hasil Foreman per KJ (Desain sama dengan Section Head tanpa Foreman) -->
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
                    <span class="text-[11px] font-bold px-2.5 py-1 bg-blue-50 text-blue-700 border border-blue-200">
                        Total Target: {{ ($foremanTargetKjSummary['total'] ?? 0) > 0 ? $foremanTargetKjSummary['total'] : ($totalTargetForeman ?? count($foremanSubordinates)) }}
                    </span>
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
                            @forelse($foremanTargetKjMatrix ?? [] as $kjName => $row)
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
                                    {{ $foremanTargetKjSummary['S'] ?? ($targetCounts['S'] ?? 0) }}
                                </td>
                                <td class="bg-[#0b5394] text-white font-black text-xs px-2.5 py-2.5 text-center border border-[#0b5394]/70">
                                    {{ $foremanTargetKjSummary['A'] ?? ($targetCounts['A'] ?? 0) }}
                                </td>
                                <td class="bg-[#0b5394] text-white font-black text-xs px-2.5 py-2.5 text-center border border-[#0b5394]/70">
                                    {{ $foremanTargetKjSummary['B'] ?? ($targetCounts['B'] ?? 0) }}
                                </td>
                                <td class="bg-[#0b5394] text-white font-black text-xs px-2.5 py-2.5 text-center border border-[#0b5394]/70">
                                    {{ $foremanTargetKjSummary['C'] ?? ($targetCounts['C'] ?? 0) }}
                                </td>
                                <td class="bg-[#0b5394] text-white font-black text-xs px-2.5 py-2.5 text-center border border-[#0b5394]/70">
                                    {{ $foremanTargetKjSummary['K'] ?? ($targetCounts['K'] ?? 0) }}
                                </td>
                                <td class="bg-[#084175] text-white font-black text-sm px-4 py-2.5 text-center border border-[#084175] whitespace-nowrap">
                                    {{ $foremanTargetKjSummary['total'] ?? ($totalTargetForeman ?? count($foremanSubordinates)) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="mt-3.5 pt-2.5 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
                <span class="flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-blue-600"></span>
                    Rencana target kuota perolehan kategori nilai per Klasifikasi Jabatan (KJ)
                </span>
                <span class="text-slate-400 text-[10px]">Tahun {{ date('Y') }}</span>
            </div>
        </div>

        <!-- Table 2: Hasil Foreman (Realisasi Dinilai Foreman per KJ) -->
        <div class="bg-white rounded-none p-5 border border-slate-300 shadow-sm flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between pb-3 border-b border-slate-200 mb-4">
                    <div class="flex items-center gap-2.5">
                        <span class="w-2.5 h-2.5 bg-emerald-600"></span>
                        <h3 class="text-sm font-bold text-slate-900 tracking-tight">
                            Hasil Foreman <span class="text-slate-400 font-normal text-xs">(nilai dari anda per KJ)</span>
                        </h3>
                    </div>
                    <span class="text-[11px] font-bold px-2.5 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200">
                        Total Dinilai: {{ $foremanActualKjSummary['total'] ?? ($stats['total_dinilai'] ?? 0) }}
                    </span>
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
                            @forelse($foremanActualKjMatrix ?? [] as $kjName => $row)
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
                                    {{ $foremanActualKjSummary['S'] ?? ($gradeCounts['S'] ?? 0) }}
                                </td>
                                <td class="bg-[#0b5394] text-white font-black text-xs px-2.5 py-2.5 text-center border border-[#0b5394]/70">
                                    {{ $foremanActualKjSummary['A'] ?? ($gradeCounts['A'] ?? 0) }}
                                </td>
                                <td class="bg-[#0b5394] text-white font-black text-xs px-2.5 py-2.5 text-center border border-[#0b5394]/70">
                                    {{ $foremanActualKjSummary['B'] ?? ($gradeCounts['B'] ?? 0) }}
                                </td>
                                <td class="bg-[#0b5394] text-white font-black text-xs px-2.5 py-2.5 text-center border border-[#0b5394]/70">
                                    {{ $foremanActualKjSummary['C'] ?? ($gradeCounts['C'] ?? 0) }}
                                </td>
                                <td class="bg-[#0b5394] text-white font-black text-xs px-2.5 py-2.5 text-center border border-[#0b5394]/70">
                                    {{ $foremanActualKjSummary['K'] ?? ($gradeCounts['K'] ?? 0) }}
                                </td>
                                <td class="bg-[#084175] text-white font-black text-sm px-4 py-2.5 text-center border border-[#084175] whitespace-nowrap">
                                    {{ $foremanActualKjSummary['total'] ?? ($stats['total_dinilai'] ?? 0) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="mt-3.5 pt-2.5 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
                <span class="flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
                    Realisasi perolehan nilai riil yang telah dinilai dan dikonfirmasi Foreman
                </span>
                <span class="text-slate-400 text-[10px]">{{ ($foremanActualKjSummary['total'] ?? ($stats['total_dinilai'] ?? 0)) . '/' . ($foremanTargetKjSummary['total'] ?? ($totalTargetForeman ?? count($foremanSubordinates))) }} Dinilai</span>
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
                        {{ count($foremanSubordinates) }} Karyawan
                    </span>
                </div>

                <!-- Toolbar: Dropdown Filter Status + Live Search Box Sejajar -->
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 w-full md:w-auto">
                    <!-- Dropdown Filter Status Penilaian (Disamping Cari Nama, NRP, Jabatan) -->
                    <div class="w-full sm:w-60">
                        <select x-model="activeTab" 
                                class="w-full py-1.5 px-3 text-xs font-semibold text-slate-700 rounded-none border border-slate-300 hover:border-slate-400 bg-white focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-xs">
                            <option value="all">Semua ({{ count($foremanSubordinates) }})</option>
                            <option value="todo">Perlu Tindakan / To-Do ({{ $foremanNeedActionCount }})</option>
                            <option value="draft">Draft Tersimpan ({{ $foremanDraftCount }})</option>
                            <option value="done">Sudah Terkirim / Selesai ({{ $foremanDoneCount }})</option>
                            <option value="evaluated">Sudah Dinilai ({{ $foremanSudahDinilaiCount ?? ($foremanDraftCount + $foremanDoneCount) }})</option>
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
                    @forelse($foremanSubordinates as $sub)
                    <tr class="hover:bg-slate-50 transition-colors"
                        x-show="matchesFilter({{ json_encode($sub) }})">
                        <td class="py-3 px-4 font-semibold text-slate-900 border border-slate-200">
                            <div>
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="block font-bold text-slate-900">{{ $sub['nama_lengkap'] }}</span>
                                    @if(strcasecmp($sub['nrp'] ?? '', $user->nrp ?? '') === 0 || strcasecmp(trim($sub['nama_lengkap'] ?? ''), trim($user->nama ?? '')) === 0)
                                        <span class="px-1.5 py-0.5 text-[9.5px] font-extrabold bg-[#fbe8e8] text-[#92302e] border border-[#ce5d5b]/40 rounded-none">(Anda)</span>
                                    @endif
                                </div>
                                <span class="text-[10px] text-slate-400 font-normal" title="Seksi: {{ $sub['nama_sect'] ?? $sub['seksi'] }}">{{ $sub['nama_sect'] ?? $sub['seksi'] }}</span>
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
                            <span class="inline-flex items-center px-2 py-0.5 rounded-none text-[10px] font-bold border {{ $sub['action_type'] === 'sudah_dikirim' ? 'bg-blue-50 text-blue-800 border-blue-200' : ($sub['action_type'] === 'draft' ? 'bg-amber-50 text-amber-800 border-amber-200' : ($sub['action_type'] === 'revisi' ? 'bg-rose-50 text-rose-800 border-rose-200' : 'bg-slate-100 text-slate-700 border-slate-200')) }}">
                                {{ $sub['stage_label'] }}
                            </span>
                        </td>
                        <td class="py-3 px-4 text-center border border-slate-200">
                            @if($sub['is_evaluated'] || in_array($sub['status'], ['menunggu_section_head', 'menunggu_dept_head', 'menunggu_div_head', 'selesai']))
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
                                <a href="{{ route('penilaian.show.foreman', $sub['doc_id']) }}" 
                                   class="inline-block px-2.5 py-1 rounded-none text-xs font-bold transition-colors {{ in_array($sub['status'], ['draft', 'menunggu_foreman', 'belum_ada', 'revisi']) ? 'bg-[#92302e] text-white hover:bg-[#7e2524]' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 border border-slate-300' }}">
                                    {{ in_array($sub['status'], ['draft', 'menunggu_foreman', 'belum_ada', 'revisi']) ? 'Nilai' : 'Lihat' }}
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

    <!-- 8. Panduan Alur Kerja Foreman (Petunjuk Pelaksanaan Penilaian) -->
    <div class="bg-white rounded-none p-5 border border-slate-300 shadow-sm">
        <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider mb-3">
            Panduan & Prosedur Evaluasi Foreman
        </h4>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-xs text-slate-600">
            <div class="p-3 bg-slate-50 border border-slate-200 rounded-none">
                <span class="font-bold text-[#92302e] block mb-1">Langkah 1: Identifikasi</span>
                <p>Cek daftar bawahan Anda pada tabel di atas. Perhatikan karyawan dengan status <em>Belum Dinilai</em> atau <em>Perlu Tindakan</em>.</p>
            </div>
            <div class="p-3 bg-slate-50 border border-slate-200 rounded-none">
                <span class="font-bold text-[#92302e] block mb-1">Langkah 2: Evaluasi Nilai</span>
                <p>Klik tombol <strong>Nilai</strong> untuk mengisi form penilaian karyawan sesuai kriteria prestasi, disiplin, dan kompetensi.</p>
            </div>
            <div class="p-3 bg-slate-50 border border-slate-200 rounded-none">
                <span class="font-bold text-[#92302e] block mb-1">Langkah 3: TTD & Kirim</span>
                <p>Simpan sebagai <em>Draft</em> jika belum tuntas, atau tanda tangani digital lalu kirim berkas untuk diteruskan ke Section Head.</p>
            </div>
            <div class="p-3 bg-slate-50 border border-slate-200 rounded-none">
                <span class="font-bold text-[#92302e] block mb-1">Langkah 4: Pantau Alur</span>
                <p>Pantau status dokumen pada pipeline tracker di atas hingga mendapat persetujuan akhir dari Department Head.</p>
            </div>
        </div>
    </div>

</div>