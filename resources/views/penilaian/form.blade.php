@extends('layouts.app', ['title' => 'Form Penilaian Karya - ' . $dokumen['kode_form']])

@section('content')
<style>
@media print {
    @page {
        size: A4 portrait;
        margin: 4mm 5mm 4mm 5mm;
    }
    html, body {
        background: #ffffff !important;
        color: #000000 !important;
        margin: 0 !important;
        padding: 0 !important;
        font-size: 7.2pt !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    header, nav, footer, .min-btn, button, #topActionBar, #activeEvaluatorAlert, .no-print {
        display: none !important;
    }
    #officialFormSheet {
        border: 1.5px solid #000000 !important;
        box-shadow: none !important;
        padding: 0 !important;
        margin: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        page-break-inside: avoid !important;
        page-break-after: avoid !important;
        overflow: visible !important;
    }
    input::placeholder, textarea::placeholder {
        color: transparent !important;
    }
}
</style>

@php
    $user = Auth::user();
    $kodeFormStr = (string)($kodeForm ?? ($dokumen['kode_form'] ?? ''));
    $isGol4Up = \App\Http\Controllers\PenilaianController::isForm07006($kodeFormStr);
    $isSubordinate07006 = $isGol4Up && !\App\Http\Controllers\PenilaianController::isActualSectionHead($karyawan) && !\App\Http\Controllers\PenilaianController::isActualDeptHead($karyawan) && !\App\Http\Controllers\PenilaianController::isActualDivHead($karyawan);
    $isTanpaForemanStd = !$isGol4Up && empty($karyawan['nrp_foreman']);
    $showForemanData = !$isTanpaForemanStd || !empty($ttdByLevel['foreman']) || !empty($scoresByLevel['foreman']);
    $currentUserSignatureUrl = $user?->signature_url;
    $hasUserSignature = $user?->hasSignature() ?? false;
    $officialFallbackSvg = $user ? \App\Http\Controllers\PenilaianController::makeOfficialSvgDataUri($user->nama, $user->role, $user->nrp) : '';
    $canViewForeman = $canViewForeman ?? true;
    $canViewSectionHead = $canViewSectionHead ?? true;
    $canViewDeptHead = $canViewDeptHead ?? true;
@endphp

<div x-data="penilaianApp()" class="max-w-[1300px] mx-auto space-y-5">

    <!-- Pop-up Toast Peringatan Kuota Penuh (Muncul selama 5 detik jika kuota full & sudah giliran penilai) -->
    @if(empty($waitingForLevel) && empty($isQuotaExempt ?? $isGol4Up))
    <div x-show="showQuotaToast" 
         x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform -translate-y-4 scale-95"
         x-transition:enter-end="opacity-100 transform translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 transform translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 transform -translate-y-4 scale-95"
         class="fixed top-5 right-5 z-50 max-w-md w-full bg-white border-2 border-[#92302e] shadow-2xl overflow-hidden rounded-none animate-in fade-in duration-200 pointer-events-auto">
        <div class="p-3.5 flex items-start gap-3 bg-red-50/95">
            <div class="w-9 h-9 rounded-none bg-[#92302e] text-white flex items-center justify-center shrink-0 font-bold text-base shadow-xs mt-0.5">
                ⛔
            </div>
            <div class="flex-1 min-w-0 pr-1">
                <div class="text-xs font-black text-[#92302e] leading-snug tracking-wide">
                    ⛔ Kuota Rank <span x-text="dockingGrade || calcScoreToGrade(getActiveGrandTotal())"></span> Penuh! Sesuaikan Grade!
                </div>
                <p class="text-xs font-bold text-slate-800 leading-snug mt-1">
                    Tolong Perhatikan Kuota dan Target yang diberikan oleh admin
                </p>
                <div class="text-[11px] text-slate-600 font-medium mt-1">
                    Target Admin: <strong class="text-slate-900" x-text="divTargetQuota[dockingGrade] || 0"></strong> | Terpakai: <strong class="text-red-700" x-text="getTotalUsage(dockingGrade)"></strong> (Sisa: 0 slot)
                </div>
                <div class="mt-2.5 flex items-center gap-2">
                    <button type="button" 
                            @click="autoSesuaikanRankTersedia(); showQuotaToast = false;"
                            class="px-3 py-1.5 bg-[#92302e] hover:bg-[#7e2524] text-white text-[11px] font-black shadow-xs inline-flex items-center gap-1.5 cursor-pointer transition">
                        <span>🎯</span>
                        <span>Sesuaikan ke Rank Tersedia</span>
                    </button>
                    <button type="button" 
                            @click="showQuotaToast = false"
                            class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-[11px] font-bold border border-slate-300 cursor-pointer transition">
                        Tutup
                    </button>
                </div>
            </div>
            <button type="button" @click="showQuotaToast = false" class="text-slate-400 hover:text-slate-800 p-1 font-bold text-lg leading-none cursor-pointer transition" title="Tutup">&times;</button>
        </div>
        <!-- Progress Bar Countdown (5 seconds) -->
        <div class="w-full bg-red-100 h-1">
            <div class="bg-[#92302e] h-1 transition-all duration-75 ease-linear" :style="'width: ' + quotaToastProgress + '%'"></div>
        </div>
    </div>
    @endif

    <!-- Top Action & NRP Switcher Bar -->
    <div id="topActionBar" class="no-print bg-white px-3.5 py-2.5 sm:px-4 sm:py-3 rounded-2xl border border-slate-200/90 shadow-xs flex items-center justify-between gap-3 overflow-x-auto min-w-0">
        
        <!-- Left: Back Button & NRP Selector ("Ketika pilih NRP otomatis muncul nama dan formnya") -->
        <div class="flex items-center gap-2 sm:gap-2.5 flex-nowrap flex-shrink min-w-0">
            <a href="{{ route('dashboard') }}" class="min-btn inline-flex items-center justify-center w-9 h-9 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-700 transition-colors shadow-sm flex-shrink-0" title="Kembali ke Dashboard">
                <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </a>

            <!-- NRP Quick Switcher: Direct Text Input with Datalist & Search Button -->
            <div class="flex items-center gap-1.5 sm:gap-2 bg-slate-50 border border-slate-300/80 rounded-xl px-2.5 py-1.5 shadow-inner flex-nowrap flex-shrink min-w-0">
                <span class="text-xs font-bold text-slate-700 uppercase flex items-center gap-1 whitespace-nowrap flex-shrink-0">
                    <span>🔍</span> <span class="hidden sm:inline">Cari </span>NRP:
                </span>
                <div class="flex items-center gap-1.5 flex-shrink-0">
                    <input type="text" 
                           list="accessibleNrpList"
                           x-model="quickNrpInput" 
                           @change="jumpToNrp()"
                           @keydown.enter.prevent="jumpToNrp()" 
                           placeholder="Ketik NRP atau nama..." 
                           class="w-40 sm:w-56 lg:w-64 text-xs font-bold bg-white border border-slate-300 text-slate-900 rounded-lg px-2.5 py-1.5 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                    <datalist id="accessibleNrpList">
                        @foreach($accessibleDocs as $d)
                            <option value="{{ $d['nrp'] }} {{ $d['nama_lengkap'] }}"></option>
                        @endforeach
                    </datalist>
                    <button type="button" @click="jumpToNrp()" class="min-btn px-3 py-1.5 rounded-lg text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm transition-all whitespace-nowrap">
                        Cari
                    </button>
                </div>
            </div>
        </div>

        <!-- Right: Status Workflow Badge & Main Action Buttons -->
        <div class="flex items-center gap-2 sm:gap-2.5 flex-nowrap flex-shrink-0">

            <!-- Status Badge -->
            <div class="text-right flex flex-col items-end flex-shrink-0 mr-0.5">
                <span class="text-[9px] uppercase font-bold tracking-wider text-slate-400 block leading-tight">Status Dokumen</span>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold whitespace-nowrap leading-snug
                    {{ $dokumen['status'] === 'selesai' ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 
                      ($dokumen['status'] === 'revisi' ? 'bg-rose-100 text-rose-800 border border-rose-300' : 'bg-amber-100 text-amber-800 border border-amber-300') }}">
                    <span class="w-1.5 h-1.5 rounded-full mr-1.5 {{ $dokumen['status'] === 'selesai' ? 'bg-emerald-500' : ($dokumen['status'] === 'revisi' ? 'bg-rose-500' : 'bg-amber-500 animate-pulse') }}"></span>
                    @php
                        $isSelfDoc = (strcasecmp($dokumen['nrp'] ?? '', Auth::user()?->nrp ?? '') === 0);
                        $isMandiriStage = $isGol4Up && in_array($dokumen['status'] ?? '', ['menunggu_section_head', 'menunggu_foreman', 'draft', 'belum_ada']) && empty($ttdByLevel['foreman']);
                        $isViewerSelfOrAuthorized = $isSelfDoc || in_array($rolePrefix ?? '', ['foreman', 'sechead']) || (Auth::user()?->isAdmin() || Auth::user()?->isSuperadmin());
                        
                        $statusBadgeText = match($dokumen['status'] ?? '') {
                            'menunggu_div_head' => 'MENUNGGU DIVISION HEAD',
                            'menunggu_dept_head' => 'MENUNGGU DEPARTMENT HEAD',
                            'menunggu_section_head' => $isMandiriStage 
                                ? ($isViewerSelfOrAuthorized ? 'MENUNGGU PENILAIAN ANDA' : 'MENUNGGU EVALUASI MANDIRI') 
                                : 'MENUNGGU SECTION HEAD',
                            'menunggu_foreman' => $isMandiriStage 
                                ? ($isViewerSelfOrAuthorized ? 'MENUNGGU PENILAIAN ANDA' : 'MENUNGGU EVALUASI MANDIRI') 
                                : 'MENUNGGU FOREMAN',
                            'selesai' => 'SELESAI',
                            'revisi' => 'PERLU REVISI',
                            default => $isMandiriStage 
                                ? ($isViewerSelfOrAuthorized ? 'MENUNGGU PENILAIAN ANDA' : 'MENUNGGU EVALUASI MANDIRI') 
                                : strtoupper(str_replace('_', ' ', $dokumen['status'] ?? 'DRAFT'))
                        };
                    @endphp
                    {{ $statusBadgeText }}
                </span>
            </div>

            <!-- Unduh PDF Button (Download exact official Musashi Form) -->
            <a href="{{ route('penilaian.downloadPdf', $dokumen['id']) }}" 
               target="_blank" 
               class="min-btn inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold text-slate-800 bg-white hover:bg-slate-100 border border-slate-300 transition-all shadow-sm whitespace-nowrap flex-shrink-0"
               title="Unduh / Cetak Dokumen PDF Resmi">
                <span>📄</span> PDF
            </a>

            @if($canEditSectionHead && !$isTanpaForemanStd && $subFormVariant !== 'karyawan_gol4')
            <button type="button" @click="copyForemanScores()" class="min-btn inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold text-emerald-800 bg-emerald-50 hover:bg-emerald-100 border border-emerald-300 shadow-sm transition-all whitespace-nowrap flex-shrink-0" title="Salin semua skor sebagai acuan dasar (dapat Anda modifikasi)">
                <span>📋</span> {{ $isGol4Up ? 'Sama dg Mandiri' : 'Sama dg Foreman' }}
            </button>
            @elseif($canEditDeptHead)
            <button type="button" @click="copySectionHeadScores()" class="min-btn inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold text-emerald-800 bg-emerald-50 hover:bg-emerald-100 border border-emerald-300 shadow-sm transition-all whitespace-nowrap flex-shrink-0" title="Salin semua skor sebagai acuan dasar (dapat Anda modifikasi)">
                <span>📋</span> {{ $subFormVariant === 'karyawan_gol4' ? 'Sama dg Section Head' : ($isGol4Up ? 'Sama dg Dept Head' : 'Sama dg Section Head') }}
            </button>
            @elseif($canEditDivHead)
            <button type="button" @click="copyDeptHeadScores()" class="min-btn inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold text-emerald-800 bg-emerald-50 hover:bg-emerald-100 border border-emerald-300 shadow-sm transition-all whitespace-nowrap flex-shrink-0" title="Salin semua skor Dept Head sebagai acuan dasar (dapat Anda modifikasi)">
                <span>📋</span> Sama dg Dept Head
            </button>
            @endif

            <!-- Save Draft Button -->
            @if($canEditForeman || $canEditSectionHead || $canEditDeptHead || $canEditDivHead)
            <button type="submit" form="formPenilaian" class="min-btn inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 border border-slate-300 transition-all shadow-sm whitespace-nowrap flex-shrink-0" title="Simpan Draft">
                <span>💾</span> Draft
            </button>
            @endif

            <!-- Main Sign & Forward Workflow Buttons -->
            @if($canSignForeman)
                @if($subFormVariant === 'div_head')
                <button type="button" 
                        @click="if (isDivHeadViewer && isCurrentGradeOverQuota) { triggerQuotaToast(); return false; } openSignModal('foreman', 'Division Head (Mandiri)')" 
                        :disabled="isDivHeadViewer && isCurrentGradeOverQuota"
                        :class="isDivHeadViewer && isCurrentGradeOverQuota 
                            ? 'opacity-40 cursor-not-allowed bg-slate-500 hover:bg-slate-500 shadow-none' 
                            : 'bg-[#92302e] hover:bg-[#7e2524] shadow-md shadow-red-200'"
                        class="min-btn inline-flex items-center gap-1.5 px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold text-white transition-all whitespace-nowrap flex-shrink-0"
                        :title="isDivHeadViewer && isCurrentGradeOverQuota ? 'Finalisasi Terkunci: Kuota Rank ' + dockingGrade + ' Penuh' : 'Finalisasi Dokumen Penilaian'">
                    <span x-text="isDivHeadViewer && isCurrentGradeOverQuota ? '⛔' : '🔏'"></span>
                    <span>Finalisasi</span>
                </button>
                @elseif($subFormVariant === 'dept_head')
                <button type="button" @click="openSignModal('foreman', 'Department Head (Mandiri)')" class="min-btn inline-flex items-center gap-1.5 px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold text-white bg-[#92302e] hover:bg-[#7e2524] shadow-md shadow-red-200 transition-all whitespace-nowrap flex-shrink-0">
                    <span>🔏</span> Kirim
                </button>
                @elseif($subFormVariant === 'section_head')
                @php
                    $btnSignTitle = 'Mandiri / 自分';
                @endphp
                <button type="button" @click="openSignModal('foreman', '{{ $btnSignTitle }}')" class="min-btn inline-flex items-center gap-1.5 px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold text-white bg-[#92302e] hover:bg-[#7e2524] shadow-md shadow-red-200 transition-all whitespace-nowrap flex-shrink-0">
                    <span>🔏</span> Kirim
                </button>
                @else
                <button type="button" @click="openSignModal('foreman', 'Foreman (Penilai 1)')" class="min-btn inline-flex items-center gap-1.5 px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold text-white bg-[#92302e] hover:bg-[#7e2524] shadow-md shadow-red-200 transition-all whitespace-nowrap flex-shrink-0">
                    <span>✍</span> Kirim
                </button>
                @endif
            @elseif($canSignSectionHead)
            <button type="button" @click="openSignModal('section_head', '{{ $subFormVariant === 'karyawan_gol4' ? 'Section Head' : ($isGol4Up ? 'Department Head (Penilai 2)' : 'Section Head (Penilai 2)') }}')" class="min-btn inline-flex items-center gap-1.5 px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold text-white bg-[#92302e] hover:bg-[#7e2524] shadow-md shadow-red-200 transition-all whitespace-nowrap flex-shrink-0">
                <span>✍</span> Kirim
            </button>
            @elseif($canSignDeptHead)
            <button type="button" 
                    @click="if (isDivHeadViewer && isCurrentGradeOverQuota) { triggerQuotaToast(); return false; } openSignModal('dept_head', '{{ $subFormVariant === 'karyawan_gol4' ? 'Department Head' : ($isGol4Up ? 'Division Head (Penilai Final)' : 'Department Head (Penilai Final)') }}')" 
                    :disabled="isDivHeadViewer && isCurrentGradeOverQuota"
                    :class="isDivHeadViewer && isCurrentGradeOverQuota 
                        ? 'opacity-40 cursor-not-allowed bg-slate-500 hover:bg-slate-500 shadow-none' 
                        : 'bg-[#92302e] hover:bg-[#7e2524] shadow-md shadow-red-200'"
                    class="min-btn inline-flex items-center gap-1.5 px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold text-white transition-all whitespace-nowrap flex-shrink-0"
                    :title="isDivHeadViewer && isCurrentGradeOverQuota ? 'Finalisasi Terkunci: Kuota Rank ' + dockingGrade + ' Penuh' : ($subFormVariant === 'karyawan_gol4' ? 'Kirim ke Division Head' : 'Finalisasi Dokumen Penilaian')">
                <span x-text="isDivHeadViewer && isCurrentGradeOverQuota ? '⛔' : '{{ $subFormVariant === 'karyawan_gol4' ? '✍' : '✓' }}'"></span>
                <span>{{ $subFormVariant === 'karyawan_gol4' ? 'Kirim' : 'Finalisasi' }}</span>
            </button>
            @elseif($canSignDivHead)
            <button type="button" 
                    @click="openSignModal('div_head', 'Division Head (Penilai Final)')" 
                    class="min-btn inline-flex items-center gap-1.5 px-3.5 sm:px-4 py-2 rounded-xl text-xs font-bold text-white bg-[#92302e] hover:bg-[#7e2524] shadow-md shadow-red-200 transition-all whitespace-nowrap flex-shrink-0"
                    title="Finalisasi Dokumen Penilaian Division Head">
                <span>✓</span>
                <span>Finalisasi</span>
            </button>
            @endif
        </div>
    </div>

    @if(!empty($isReadOnlySubordinate07006))
    <!-- Banner Khusus Section Head Melihat Nilai Bawahan KJ >= 5 / Form 07-006 -->
    <div class="no-print bg-amber-50 border-l-4 border-amber-500 border border-amber-300 p-3.5 shadow-xs flex items-center justify-between gap-3 text-amber-900 text-xs">
        <div class="flex items-center gap-3">
            <span class="text-xl">👁️</span>
            <div>
                <strong class="block text-amber-950 font-bold">Akses Lihat Nilai (Read-Only)</strong>
                <span class="text-amber-800">Karyawan ini (KJ 5 ke atas / Form 07-006) dinilai langsung oleh Department Head. Anda dapat melihat hasil dan perkembangan penilaian tanpa mengubah nilai.</span>
            </div>
        </div>
        <span class="px-2.5 py-1 bg-amber-100 text-amber-900 border border-amber-300 font-bold text-[10px] uppercase whitespace-nowrap">
            Mode Lihat Nilai
        </span>
    </div>
    @endif

    <!-- Dedicated Banner / Panel Kuota: Form 07-006 Bebas Kuota (Untuk Semua Level) & Panel Penyesuaian Kuota -->
    @if(empty($waitingForLevel) && !empty($isQuotaExempt ?? $isGol4Up))
        <div class="no-print bg-sky-50/80 border border-sky-300 rounded-none p-3 sm:p-3.5 shadow-2xs flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div class="flex items-center gap-2.5">
                <span class="px-2 py-0.5 bg-sky-700 text-white text-[10.5px] font-black uppercase tracking-wider rounded-none">
                    Form 07-006 &bull; Bebas Target Kuota
                </span>
                <span class="text-xs text-sky-950 font-medium">
                    Penilaian menggunakan Form <strong>{{ $kodeForm }}</strong> (Section Head / Department Head / Division Head) tidak terikat target kuota Admin/Superadmin dan kunci finalisasi selalu terbuka.
                </span>
            </div>
            @if(!empty($isDivHeadViewer))
            <input type="hidden" name="docking_final" :value="dockingGrade" form="formPenilaian">
            <input type="hidden" name="docking_final" :value="dockingGrade" form="signSubmitForm">
            @endif
        </div>
    @elseif(!empty($isDivHeadViewer) && empty($waitingForLevel))
    <div class="no-print bg-white border-2 border-[#92302e] rounded-none p-3.5 sm:p-4 shadow-sm space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5 border-b border-slate-200 pb-2.5">
            <div class="flex flex-wrap items-center gap-2">
                    <span class="px-2 py-0.5 bg-[#92302e] text-white text-[10.5px] font-black uppercase tracking-wider rounded-none">
                        {{ ($quotaScopeLabel ?? 'Divisi') === 'Departemen' ? 'Penyesuaian Kuota Departemen' : 'Penyesuaian Kuota Divisi' }}
                    </span>
                    <span class="text-xs text-slate-600 font-medium">
                        Pastikan nilai akhir sesuai target kuota admin sebelum difinalisasi.
                    </span>
                </div>

                <div class="flex items-center gap-2 flex-shrink-0">
                    <a href="{{ $penyesuaianRoute ?? route('penilaian.dept.penyesuaian') }}" 
                       class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold border border-slate-300 inline-flex items-center gap-1.5 shadow-2xs transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    <span>Tabel Penyesuaian Kuota</span>
                </a>
            </div>
        </div>

        <!-- Quota Target & Availability Cards (Info Target S & Semua Rank) -->
        <div class="bg-slate-50 border border-slate-300 p-3 space-y-2">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1.5 pb-2 border-b border-slate-200">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-black text-slate-900 uppercase tracking-wider">🎯 Status Kuota Target untuk {{ ($quotaScopeLabel ?? 'Divisi') === 'Departemen' ? 'Department Head' : 'Division Head' }} &bull; Jenjang {{ $empKj ?? ($karyawan['kj'] ?? 'KJ') }}</span>
                </div>
                <div class="text-[11px] font-bold">
                    <span x-show="isCurrentGradeOverQuota" x-cloak class="text-red-700 font-black inline-flex items-center gap-1">
                        <span>⛔</span> Rank <span x-text="dockingGrade"></span> MELEBIHI KUOTA
                    </span>
                    <span x-show="!isCurrentGradeOverQuota" class="text-emerald-700 font-black inline-flex items-center gap-1">
                        <span>✓</span> Rank <span x-text="dockingGrade"></span> Sesuai Kuota Tersedia
                    </span>
                </div>
            </div>

            <!-- 5 Quota Cards (S, A, B, C, K) -->
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-2.5 pt-1">
                <!-- RANK S (Highlighted Special Card) -->
                <div class="p-2.5 border-2 transition-all relative"
                     :class="{
                         'border-[#92302e] bg-red-50/80 shadow-xs': dockingGrade === 'S',
                         'border-rose-300 bg-white hover:border-[#92302e]': dockingGrade !== 'S'
                     }">
                    <div class="flex items-center justify-between pb-1 border-b border-rose-200">
                        <span class="text-[11px] font-black px-1.5 py-0.5 bg-[#92302e] text-white">RANK S</span>
                        <span class="text-[9.5px] font-black uppercase px-1.5 py-0.5"
                              :class="(divTargetQuota['S'] - getTotalUsage('S')) < 0 ? 'bg-red-600 text-white' : (((divTargetQuota['S'] - getTotalUsage('S')) === 0) ? 'bg-amber-100 text-amber-900 border border-amber-300' : 'bg-emerald-100 text-emerald-800 border border-emerald-300')"
                              x-text="(divTargetQuota['S'] - getTotalUsage('S')) < 0 ? 'Kelebihan' : (((divTargetQuota['S'] - getTotalUsage('S')) === 0) ? 'Penuh' : 'Tersedia')">
                        </span>
                    </div>
                    <div class="mt-2 text-center space-y-0.5">
                        <div class="text-[11px] text-slate-600 font-semibold">Target Admin: <strong class="text-slate-900 font-black text-xs" x-text="divTargetQuota['S'] || 0"></strong></div>
                        <div class="text-[11px] text-slate-600 font-semibold">Terpakai: <strong class="text-slate-900 font-black text-xs" x-text="getTotalUsage('S')"></strong></div>
                        <div class="pt-1.5 mt-1 border-t border-rose-200 text-xs font-black"
                             :class="(divTargetQuota['S'] - getTotalUsage('S')) < 0 ? 'text-red-700 font-black' : (((divTargetQuota['S'] - getTotalUsage('S')) === 0) ? 'text-amber-800' : 'text-emerald-700')">
                            Sisa: <span x-text="Math.max(0, divTargetQuota['S'] - getTotalUsage('S'))"></span> slot
                        </div>
                    </div>
                    <div x-show="dockingGrade === 'S'" class="mt-1 text-center">
                        <span class="text-[9.5px] font-extrabold uppercase text-[#92302e] bg-white px-1.5 py-0.5 border border-[#92302e]">● Pilihan Anda</span>
                    </div>
                </div>

                <!-- RANK A -->
                <div class="p-2.5 border-2 transition-all"
                     :class="{
                         'border-[#92302e] bg-red-50/50 shadow-xs': dockingGrade === 'A',
                         'border-slate-300 bg-white hover:border-slate-400': dockingGrade !== 'A'
                     }">
                    <div class="flex items-center justify-between pb-1 border-b border-slate-200">
                        <span class="text-[11px] font-black px-1.5 py-0.5 bg-slate-800 text-white">RANK A</span>
                        <span class="text-[9.5px] font-black uppercase px-1.5 py-0.5"
                              :class="(divTargetQuota['A'] - getTotalUsage('A')) < 0 ? 'bg-red-600 text-white' : (((divTargetQuota['A'] - getTotalUsage('A')) === 0) ? 'bg-amber-100 text-amber-900 border border-amber-300' : 'bg-emerald-100 text-emerald-800 border border-emerald-300')"
                              x-text="(divTargetQuota['A'] - getTotalUsage('A')) < 0 ? 'Kelebihan' : (((divTargetQuota['A'] - getTotalUsage('A')) === 0) ? 'Penuh' : 'Tersedia')">
                        </span>
                    </div>
                    <div class="mt-2 text-center space-y-0.5">
                        <div class="text-[11px] text-slate-600 font-semibold">Target Admin: <strong class="text-slate-900 font-black text-xs" x-text="divTargetQuota['A'] || 0"></strong></div>
                        <div class="text-[11px] text-slate-600 font-semibold">Terpakai: <strong class="text-slate-900 font-black text-xs" x-text="getTotalUsage('A')"></strong></div>
                        <div class="pt-1.5 mt-1 border-t border-slate-200 text-xs font-black"
                             :class="(divTargetQuota['A'] - getTotalUsage('A')) < 0 ? 'text-red-700' : (((divTargetQuota['A'] - getTotalUsage('A')) === 0) ? 'text-amber-800' : 'text-emerald-700')">
                            Sisa: <span x-text="Math.max(0, divTargetQuota['A'] - getTotalUsage('A'))"></span> slot
                        </div>
                    </div>
                    <div x-show="dockingGrade === 'A'" class="mt-1 text-center">
                        <span class="text-[9.5px] font-extrabold uppercase text-[#92302e] bg-white px-1.5 py-0.5 border border-[#92302e]">● Pilihan Anda</span>
                    </div>
                </div>

                <!-- RANK B -->
                <div class="p-2.5 border-2 transition-all"
                     :class="{
                         'border-[#92302e] bg-red-50/50 shadow-xs': dockingGrade === 'B',
                         'border-slate-300 bg-white hover:border-slate-400': dockingGrade !== 'B'
                     }">
                    <div class="flex items-center justify-between pb-1 border-b border-slate-200">
                        <span class="text-[11px] font-black px-1.5 py-0.5 bg-slate-800 text-white">RANK B</span>
                        <span class="text-[9.5px] font-black uppercase px-1.5 py-0.5"
                              :class="(divTargetQuota['B'] - getTotalUsage('B')) < 0 ? 'bg-red-600 text-white' : (((divTargetQuota['B'] - getTotalUsage('B')) === 0) ? 'bg-amber-100 text-amber-900 border border-amber-300' : 'bg-emerald-100 text-emerald-800 border border-emerald-300')"
                              x-text="(divTargetQuota['B'] - getTotalUsage('B')) < 0 ? 'Kelebihan' : (((divTargetQuota['B'] - getTotalUsage('B')) === 0) ? 'Penuh' : 'Tersedia')">
                        </span>
                    </div>
                    <div class="mt-2 text-center space-y-0.5">
                        <div class="text-[11px] text-slate-600 font-semibold">Target Admin: <strong class="text-slate-900 font-black text-xs" x-text="divTargetQuota['B'] || 0"></strong></div>
                        <div class="text-[11px] text-slate-600 font-semibold">Terpakai: <strong class="text-slate-900 font-black text-xs" x-text="getTotalUsage('B')"></strong></div>
                        <div class="pt-1.5 mt-1 border-t border-slate-200 text-xs font-black"
                             :class="(divTargetQuota['B'] - getTotalUsage('B')) < 0 ? 'text-red-700' : (((divTargetQuota['B'] - getTotalUsage('B')) === 0) ? 'text-amber-800' : 'text-emerald-700')">
                            Sisa: <span x-text="Math.max(0, divTargetQuota['B'] - getTotalUsage('B'))"></span> slot
                        </div>
                    </div>
                    <div x-show="dockingGrade === 'B'" class="mt-1 text-center">
                        <span class="text-[9.5px] font-extrabold uppercase text-[#92302e] bg-white px-1.5 py-0.5 border border-[#92302e]">● Pilihan Anda</span>
                    </div>
                </div>

                <!-- RANK C -->
                <div class="p-2.5 border-2 transition-all"
                     :class="{
                         'border-[#92302e] bg-red-50/50 shadow-xs': dockingGrade === 'C',
                         'border-slate-300 bg-white hover:border-slate-400': dockingGrade !== 'C'
                     }">
                    <div class="flex items-center justify-between pb-1 border-b border-slate-200">
                        <span class="text-[11px] font-black px-1.5 py-0.5 bg-slate-800 text-white">RANK C</span>
                        <span class="text-[9.5px] font-black uppercase px-1.5 py-0.5"
                              :class="(divTargetQuota['C'] - getTotalUsage('C')) < 0 ? 'bg-red-600 text-white' : (((divTargetQuota['C'] - getTotalUsage('C')) === 0) ? 'bg-amber-100 text-amber-900 border border-amber-300' : 'bg-emerald-100 text-emerald-800 border border-emerald-300')"
                              x-text="(divTargetQuota['C'] - getTotalUsage('C')) < 0 ? 'Kelebihan' : (((divTargetQuota['C'] - getTotalUsage('C')) === 0) ? 'Penuh' : 'Tersedia')">
                        </span>
                    </div>
                    <div class="mt-2 text-center space-y-0.5">
                        <div class="text-[11px] text-slate-600 font-semibold">Target Admin: <strong class="text-slate-900 font-black text-xs" x-text="divTargetQuota['C'] || 0"></strong></div>
                        <div class="text-[11px] text-slate-600 font-semibold">Terpakai: <strong class="text-slate-900 font-black text-xs" x-text="getTotalUsage('C')"></strong></div>
                        <div class="pt-1.5 mt-1 border-t border-slate-200 text-xs font-black"
                             :class="(divTargetQuota['C'] - getTotalUsage('C')) < 0 ? 'text-red-700' : (((divTargetQuota['C'] - getTotalUsage('C')) === 0) ? 'text-amber-800' : 'text-emerald-700')">
                            Sisa: <span x-text="Math.max(0, divTargetQuota['C'] - getTotalUsage('C'))"></span> slot
                        </div>
                    </div>
                    <div x-show="dockingGrade === 'C'" class="mt-1 text-center">
                        <span class="text-[9.5px] font-extrabold uppercase text-[#92302e] bg-white px-1.5 py-0.5 border border-[#92302e]">● Pilihan Anda</span>
                    </div>
                </div>

                <!-- RANK K -->
                <div class="p-2.5 border-2 transition-all"
                     :class="{
                         'border-[#92302e] bg-red-50/50 shadow-xs': dockingGrade === 'K',
                         'border-slate-300 bg-white hover:border-slate-400': dockingGrade !== 'K'
                     }">
                    <div class="flex items-center justify-between pb-1 border-b border-slate-200">
                        <span class="text-[11px] font-black px-1.5 py-0.5 bg-slate-800 text-white">RANK K</span>
                        <span class="text-[9.5px] font-black uppercase px-1.5 py-0.5"
                              :class="(divTargetQuota['K'] - getTotalUsage('K')) < 0 ? 'bg-red-600 text-white' : (((divTargetQuota['K'] - getTotalUsage('K')) === 0) ? 'bg-amber-100 text-amber-900 border border-amber-300' : 'bg-emerald-100 text-emerald-800 border border-emerald-300')"
                              x-text="(divTargetQuota['K'] - getTotalUsage('K')) < 0 ? 'Kelebihan' : (((divTargetQuota['K'] - getTotalUsage('K')) === 0) ? 'Penuh' : 'Tersedia')">
                        </span>
                    </div>
                    <div class="mt-2 text-center space-y-0.5">
                        <div class="text-[11px] text-slate-600 font-semibold">Target Admin: <strong class="text-slate-900 font-black text-xs" x-text="divTargetQuota['K'] || 0"></strong></div>
                        <div class="text-[11px] text-slate-600 font-semibold">Terpakai: <strong class="text-slate-900 font-black text-xs" x-text="getTotalUsage('K')"></strong></div>
                        <div class="pt-1.5 mt-1 border-t border-slate-200 text-xs font-black"
                             :class="(divTargetQuota['K'] - getTotalUsage('K')) < 0 ? 'text-red-700' : (((divTargetQuota['K'] - getTotalUsage('K')) === 0) ? 'text-amber-800' : 'text-emerald-700')">
                            Sisa: <span x-text="Math.max(0, divTargetQuota['K'] - getTotalUsage('K'))"></span> slot
                        </div>
                    </div>
                    <div x-show="dockingGrade === 'K'" class="mt-1 text-center">
                        <span class="text-[9.5px] font-extrabold uppercase text-[#92302e] bg-white px-1.5 py-0.5 border border-[#92302e]">● Pilihan Anda</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Over Quota Warning & Quick Downgrade Alert -->
        <div x-show="isCurrentGradeOverQuota" x-cloak class="p-3 bg-red-50 border-2 border-red-500 text-red-950 flex flex-col md:flex-row md:items-center justify-between gap-3 shadow-xs">
            <div class="flex items-start gap-2.5">
                <span class="text-2xl flex-shrink-0 leading-none">⚠️</span>
                <div>
                    <div class="font-black text-xs uppercase tracking-wide text-red-900 flex items-center gap-1.5">
                        <span>KUOTA RANK <span x-text="dockingGrade"></span> PENUH / TIDAK TERSEDIA!</span>
                    </div>
                    <div class="text-[11.5px] text-red-800 mt-1 leading-relaxed">
                        Target Admin untuk Rank <strong x-text="dockingGrade"></strong> adalah <strong x-text="divTargetQuota[dockingGrade] || 0"></strong>, sedangkan yang terpakai saat ini mencapai <strong x-text="getTotalUsage(dockingGrade)"></strong>.
                        <br>
                        <strong class="text-red-950 underline">Tombol Finalisasi dikunci.</strong> Sesuai ketentuan, Anda harus menyesuaikan grade ke rank yang masih memiliki sisa kuota sebelum dokumen ini dapat difinalisasi / dikirim.
                    </div>
                </div>
            </div>
            <div class="flex-shrink-0 self-end md:self-center">
                <button type="button" 
                        @click="autoSesuaikanRankTersedia()" 
                        class="px-3.5 py-2 bg-[#92302e] hover:bg-[#7e2524] text-white text-xs font-black shadow inline-flex items-center gap-1.5 transition cursor-pointer">
                    <span>🎯</span>
                    <span>Sesuaikan ke Rank Tersedia</span>
                </button>
            </div>
        </div>

        <!-- Grade Comparison & Docking Controls -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-1">
            <div class="flex flex-wrap items-center gap-3">
                <!-- Grade Asli -->
                <div class="bg-slate-50 border border-slate-300 px-3 py-1.5">
                    <span class="text-[10px] text-slate-500 font-bold uppercase block">Grade Asli (Skor):</span>
                    <div class="font-black text-sm text-slate-900 flex items-center gap-1.5 mt-0.5">
                        <span class="font-mono text-xs px-1.5 py-0.2 bg-slate-200" x-text="formatCleanNumber(getActiveGrandTotal())"></span>
                        <span>➔</span>
                        <span class="px-2 py-0.5 text-xs text-white bg-slate-700 font-black" x-text="'Rank ' + calcScoreToGrade(getActiveGrandTotal())"></span>
                    </div>
                </div>

                <!-- Grade Final / Docking Selector with Live Slot Info -->
                <div class="bg-red-50/50 border border-rose-300 px-3 py-1.5">
                    <span class="text-[10px] text-[#92302e] font-bold uppercase block mb-1">Pilih Grade Final (Penyesuaian Kuota):</span>
                    <div class="inline-flex items-center border border-slate-300 overflow-hidden shadow-2xs">
                        <template x-for="opt in ['S', 'A', 'B', 'C', 'K']" :key="opt">
                            <button type="button" 
                                    @click="selectDockingGrade(opt)"
                                    class="px-3 py-1.5 text-xs font-black border-r last:border-r-0 border-slate-300 transition-all cursor-pointer flex flex-col items-center justify-center min-w-[54px]"
                                    :class="{
                                        'bg-[#92302e] text-white shadow-inner': dockingGrade === opt,
                                        'bg-white text-slate-700 hover:bg-slate-100': dockingGrade !== opt
                                    }">
                                <span class="text-sm font-black" x-text="opt"></span>
                                <span class="text-[8.5px] font-bold leading-none mt-0.5"
                                      :class="{
                                          'text-white opacity-95': dockingGrade === opt,
                                          'text-red-600': dockingGrade !== opt && (divTargetQuota[opt] - getTotalUsage(opt)) <= 0,
                                          'text-emerald-700': dockingGrade !== opt && (divTargetQuota[opt] - getTotalUsage(opt)) > 0
                                      }"
                                      x-text="(divTargetQuota[opt] - getTotalUsage(opt)) <= 0 ? 'Penuh' : 'Sisa ' + (divTargetQuota[opt] - getTotalUsage(opt))">
                                </span>
                            </button>
                        </template>
                    </div>
                </div>

                <div x-show="dockingGrade !== calcScoreToGrade(getActiveGrandTotal())" x-cloak class="text-xs text-amber-800 font-bold flex items-center gap-1">
                    <span>✏️ Disesuaikan: Rank <span x-text="calcScoreToGrade(getActiveGrandTotal())"></span> ➔ Rank <strong class="text-[#92302e]" x-text="dockingGrade"></strong></span>
                </div>
            </div>

            <!-- Quick Draft / Save message -->
            <div class="text-right">
                <span class="text-[11px] text-slate-500 block">Perubahan nilai atau grade akan otomatis tersimpan saat Anda mengklik:</span>
                <span class="text-xs font-bold text-[#92302e]">"💾 Draft" atau "✓ Finalisasi" di atas</span>
            </div>
        </div>

        <input type="hidden" name="docking_final" :value="dockingGrade" form="formPenilaian">
        <input type="hidden" name="docking_final" :value="dockingGrade" form="signSubmitForm">
    </div>
    @endif

    <!-- Banner Status Selesai & Opsi Buka Revisi -->
    @php
        $isUserDivHead = Auth::user()->isDivisionHead() || in_array(Auth::user()->role, ['div_head', 'division_head']);
        $isUserAdmin = Auth::user()->isAdmin() || Auth::user()->isSuperadmin();
        $isUserDeptHead = Auth::user()->isDepartmentHead() || in_array(Auth::user()->role, ['dept_head', 'department_head']);
        $hasDivHeadApprover = !empty($karyawan['nrp_div_head']) || !empty($dokumen['nrp_div_head']);

        $isPenilaiAkhir = $isPenilaiAkhir ?? \App\Http\Controllers\PenilaianController::isUserPenilaiAkhir(Auth::user(), $dokumen, $karyawan);
    @endphp
    @if($dokumen['status'] === 'selesai')
        @if($isPenilaiAkhir)
        <div class="no-print bg-emerald-50 border-2 border-emerald-400 rounded-none p-3.5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 shadow-xs mb-3">
            <div class="flex items-center gap-2.5">
                <span class="text-xl">✅</span>
                <div>
                    <h4 class="text-xs font-black text-emerald-950 uppercase tracking-wide">
                        Dokumen Penilaian Ini Telah Selesai &amp; Ditandatangani Lengkap
                    </h4>
                    <p class="text-[11.5px] text-emerald-800 mt-0.5">
                        Seluruh penilaian dan tanda tangan digital telah tervalidasi. Jika Anda ingin melakukan perbaikan nilai atau merevisi penilaian, silakan klik tombol di samping.
                    </p>
                </div>
            </div>
            <div class="flex-shrink-0">
                <form method="POST" action="{{ route('penilaian.bukaRevisi', $dokumen['id']) }}" onsubmit="return confirm('Apakah Anda yakin ingin merevisi dokumen penilaian ini? Dokumen akan dibuka kembali sehingga Anda dapat mengisi dan mengubah penilaian lagi.')">
                    @csrf
                    <button type="submit" class="px-3.5 py-2 bg-amber-600 hover:bg-amber-700 text-white text-xs font-black rounded-none shadow-sm inline-flex items-center gap-1.5 transition cursor-pointer">
                        <span>✏️</span>
                        <span>Ingin merevisi? Klik untuk Isi Lagi</span>
                    </button>
                </form>
            </div>
        </div>
        @else
        <!-- Informasi Dokumen Selesai untuk Non-Penilai Akhir (Foreman, Section Head, Gol 4 Non Kasie, Penilaian Mandiri): Terkunci Read-Only -->
        <div class="no-print bg-slate-50 border border-slate-300 rounded-none p-3.5 flex items-center gap-2.5 shadow-2xs mb-3">
            <span class="text-lg">🔒</span>
            <div>
                <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wide">
                    Dokumen Penilaian Ini Telah Selesai &amp; Ditandatangani Lengkap
                </h4>
                <p class="text-[11px] text-slate-600 mt-0.5">
                    Seluruh penilaian dan tanda tangan digital telah tervalidasi secara permanen. Perubahan atau revisi nilai untuk peran Anda hanya dapat dilakukan setelah konfirmasi dan persetujuan <strong>Administrator / Superadmin</strong> (Menu Revisi Penilaian).
                </p>
            </div>
        </div>
        @endif
    @elseif($isSelfDoc && !empty($ttdByLevel['foreman']))
    <!-- Informasi Tahap Berjalan untuk Penilaian Mandiri yang Sudah Menandatangani -->
    <div class="no-print bg-sky-50 border border-sky-300 rounded-none p-3.5 flex items-center gap-2.5 shadow-2xs mb-3">
        <span class="text-lg">📋</span>
        <div>
            <h4 class="text-xs font-bold text-sky-950 uppercase tracking-wide">
                Penilaian Mandiri Telah Diserahkan
            </h4>
            <p class="text-[11px] text-sky-800 mt-0.5">
                Evaluasi mandiri telah berhasil Anda tanda tangani dan diteruskan ke Atasan Langsung. Formulir saat ini berstatus <strong>Read-Only</strong>. Untuk perbaikan nilai, silakan ajukan permohonan ke Administrator.
            </p>
        </div>
    </div>
    @elseif(Auth::user()?->isForeman() && !$isSelfDoc && !empty($ttdByLevel['foreman']))
    <!-- Informasi untuk Foreman yang Sudah Menandatangani Subordinat -->
    <div class="no-print bg-sky-50 border border-sky-300 rounded-none p-3.5 flex items-center gap-2.5 shadow-2xs mb-3">
        <span class="text-lg">📋</span>
        <div>
            <h4 class="text-xs font-bold text-sky-950 uppercase tracking-wide">
                Penilaian Foreman Telah Diserahkan
            </h4>
            <p class="text-[11px] text-sky-800 mt-0.5">
                Penilaian telah berhasil Anda tanda tangani dan diteruskan ke tahap berikutnya (Read-Only). Untuk perbaikan nilai, silakan hubungi Administrator.
            </p>
        </div>
    </div>
    @endif

    <!-- Stage Gate Warning Alert (If waiting for prior evaluator) -->
    @if(isset($waitingForLevel) && $waitingForLevel)
    <div class="no-print bg-amber-50 border-2 border-amber-300 rounded-2xl p-4 flex items-start gap-3 shadow-sm mb-4">
        <div class="w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center font-bold text-lg flex-shrink-0 shadow">
            🔒
        </div>
        <div>
            <h4 class="text-sm font-bold text-amber-950">
                @if($waitingForLevel === 'foreman')
                    Form Penilaian Terkunci: Masih Menunggu Penilaian Foreman
                @elseif($waitingForLevel === 'section_head')
                    Form Penilaian Terkunci: {{ $isGol4Up ? 'Masih Menunggu Evaluasi Mandiri Karyawan' : 'Masih Menunggu Penilaian Section Head' }}
                @elseif($waitingForLevel === 'dept_head')
                    Form Penilaian Terkunci: Masih Menunggu Penilaian Department Head
                @else
                    Form Penilaian Terkunci (Read-Only)
                @endif
            </h4>
            <p class="text-xs text-amber-900 mt-1 leading-relaxed">
                @if($waitingForLevel === 'foreman')
                    Karyawan ini berada di bawah supervisi Foreman (<strong>{{ $karyawan['foreman_nama'] ?? 'Foreman' }}</strong>). Seluruh kolom penilaian, komentar, dan Indikator Evaluasi Potensi Karyawan saat ini terkunci (Read-Only). Kolom Section Head baru akan aktif setelah Foreman menyelesaikan dan menandatangani penilaian secara digital.
                @elseif($waitingForLevel === 'section_head')
                    @if($isGol4Up)
                        Dokumen penilaian ini sedang dalam tahap evaluasi mandiri oleh karyawan yang bersangkutan (<strong>{{ $karyawan['nama_lengkap'] ?? ($karyawan['nama'] ?? 'Karyawan') }}</strong>). Seluruh kolom penilaian saat ini terkunci (Read-Only) dan baru dapat diisi oleh Department Head setelah evaluasi mandiri selesai ditandatangani.
                    @else
                        Dokumen penilaian ini sedang dalam tahap evaluasi oleh Section Head. Seluruh kolom penilaian, komentar, dan Indikator Evaluasi Potensi Karyawan saat ini terkunci (Read-Only) dan baru dapat diisi oleh Department Head setelah Section Head menandatangani penilaian.
                    @endif
                @elseif($waitingForLevel === 'dept_head')
                    Dokumen penilaian ini sedang dalam tahap review dan penilaian oleh Department Head. Seluruh kolom penilaian dan indikator terkunci (Read-Only) hingga giliran Division Head tiba.
                @else
                    Dokumen ini sedang dibuka dalam mode tinjauan (Read-Only). Semua nilai dan catatan ditampilkan sesuai arsip.
                @endif
            </p>
        </div>
    </div>
    @endif

    <!-- ========================================================================= -->
    <!-- FORMULIR RESMI PENILAIAN KARYA (PERSIS SEPERTI GAMBAR EXCEL MUSASHI)      -->
    <!-- ========================================================================= -->
    <div id="officialFormSheet" class="bg-white border-2 border-slate-900 shadow-xl rounded-none p-4 sm:p-6 overflow-x-auto text-slate-900">
        
        <!-- Top Form Code Tag -->
        @php
            $kodeFormStr = (string)($kodeForm ?? ($dokumen['kode_form'] ?? ''));
            $isGol4Up = \App\Http\Controllers\PenilaianController::isForm07006($kodeFormStr);
            $subFormVariant = $subFormVariant ?? null;
            if ($isGol4Up && empty($subFormVariant)) {
                if ($kodeFormStr === '07-006-DIV' || ($karyawan['level_form'] ?? '') === 'DIV_HEAD' || str_contains(strtoupper($karyawan['jabatan'] ?? ''), 'DIVISION')) {
                    $subFormVariant = 'div_head';
                } elseif ($kodeFormStr === '07-006-DH' || ($karyawan['level_form'] ?? '') === 'DEPT_HEAD' || str_contains(strtoupper($karyawan['jabatan'] ?? ''), 'DEPARTMENT') || str_contains(strtoupper($karyawan['jabatan'] ?? ''), 'DEPT HEAD')) {
                    $subFormVariant = 'dept_head';
                } elseif ($kodeFormStr === '07-006-KARYAWAN' || $isSubordinate07006) {
                    $subFormVariant = 'karyawan_gol4';
                } else {
                    $subFormVariant = 'section_head';
                }
            }

            $bannerTitle = match($kodeFormStr) {
                '07-001' => 'KJ : 1 - 3',
                '07-002' => 'KJ : 4 - 5 non jabatan',
                '07-003' => 'LEADER',
                '07-004' => 'FOREMAN',
                default => 'KJ : ' . ($karyawan['kj'] ?? '1 - 3'),
            };

            $activeLevel = $canEditForeman ? 'foreman' : ($canEditSectionHead ? 'section_head' : ($canEditDeptHead ? 'dept_head' : ($canEditDivHead ? 'div_head' : '')));
            
            $col1Title = match($subFormVariant) {
                'div_head' => '',
                'karyawan_gol4' => 'Sect. Head',
                'dept_head', 'section_head' => 'Mandiri / 自分',
                default => 'Foreman (jika ada)',
            };
            $col2Title = match($subFormVariant) {
                'div_head', 'dept_head' => '',
                'karyawan_gol4' => 'Dept. head',
                'section_head' => 'Dept. Head',
                default => 'Section Head / Deputy Section Head',
            };
            $col3Title = match($subFormVariant) {
                'div_head' => 'Mandiri / 自分',
                'karyawan_gol4' => 'Div. Head',
                'dept_head', 'section_head' => 'Div. Head',
                default => 'Dept. Head (Final)',
            };

            $formCodeLabel = match($kodeForm) {
                '07-001' => 'Code PDV Mapin - 07-001',
                '07-002' => 'Code PDV Mapin - 07-002',
                '07-003' => 'Code PDV Mapin - 07-003',
                '07-004' => 'Code PDV Mapin - 07-004',
                '07-006-SH', '07-006-DH', '07-006-DIV', '07-006-KARYAWAN' => 'Code PDV Mapin - 07-006',
                default => 'Code PDV Mapin - ' . $kodeForm,
            };
        @endphp

        <div class="text-xs font-bold font-mono mb-1 text-slate-800">
            {{ $formCodeLabel }}
        </div>

        @php
            $rawForemanNama = $ttdByLevel['foreman']['user']['nama'] ?? ($karyawan['foreman_nama'] ?? '');
            $foremanNama = trim(preg_replace('/\s*\(.*?\)/', '', $rawForemanNama));
            $foremanNrp = $ttdByLevel['foreman']['user']['nrp'] ?? ($karyawan['nrp_foreman'] ?? '');

            $rawShNama = $ttdByLevel['section_head']['user']['nama'] ?? ($karyawan['section_head_nama'] ?? '');
            $shNama = trim(preg_replace('/\s*\(.*?\)/', '', $rawShNama));
            $shNrp = $ttdByLevel['section_head']['user']['nrp'] ?? ($karyawan['nrp_section_head'] ?? '');

            $rawDhNama = $ttdByLevel['dept_head']['user']['nama'] ?? ($karyawan['dept_head_nama'] ?? '');
            $dhNama = trim(preg_replace('/\s*\(.*?\)/', '', $rawDhNama));
            $dhNrp = $ttdByLevel['dept_head']['user']['nrp'] ?? ($karyawan['nrp_dept_head'] ?? '');

            $rawDivHeadNama = $ttdByLevel['div_head']['user']['nama'] ?? ($karyawan['div_head_nama'] ?? '');
            $divHeadNama = trim(preg_replace('/\s*\(.*?\)/', '', $rawDivHeadNama));
            $divHeadNrp = $ttdByLevel['div_head']['user']['nrp'] ?? ($karyawan['nrp_div_head'] ?? '');

            // Resolusi NAMA SECT. (Seksi resmi organisasi, BUKAN Nama Bagian / tanpa akhiran .SEC)
            $displaySect = trim($karyawan['nama_sect'] ?? '');
            if (!$displaySect) {
                $rawS = trim($karyawan['seksi'] ?? '');
                $rawSUpper = strtoupper($rawS);
                $deptUpper = strtoupper(trim($karyawan['departemen'] ?? 'PROD 2'));
                if (str_contains($rawSUpper, '2') || str_contains($rawSUpper, 'FACT-2')) {
                    $displaySect = 'FACTORY 2';
                } elseif (str_contains($deptUpper, 'ENGINEERING') || str_contains($rawSUpper, 'ENG')) {
                    $displaySect = 'ENGINEERING';
                } elseif (str_contains($deptUpper, 'QA') || str_contains($rawSUpper, 'QA')) {
                    $displaySect = 'QA';
                } elseif (str_contains($deptUpper, 'QC') || str_contains($rawSUpper, 'QC')) {
                    $displaySect = 'QC';
                } else {
                    $cleanS = trim(preg_replace('/\s+SEC\.?$/i', '', $rawS));
                    $displaySect = ($cleanS && $cleanS !== '-') ? $cleanS : 'FACTORY 1';
                }
            }
        @endphp

        <!-- ======================= -->
        <!-- HEADER TABLE RESMI      -->
        <!-- ======================= -->
        <table class="w-full border-collapse border-2 border-slate-900 text-xs bg-white">
            <colgroup>
                <col style="width: 15%;">
                <col style="width: 13%;">
                <col style="width: 17%;">
                <col style="width: 15.5%;">
                <col style="width: 15.5%;">
                <col style="width: 8%;">
                <col style="width: 8%;">
                <col style="width: 8%;">
            </colgroup>

            <!-- ROW 1 -->
            <tr>
                <!-- Col 1: Musashi Logo + RAHASIA Box + Periode + Tahun (rowspan 5) -->
                <td rowspan="5" class="w-[15%] border-r-2 border-b-2 border-slate-900 p-2 align-top bg-white">
                    <div class="flex flex-col justify-between h-full min-h-[105px]">
                        <div>
                            <!-- Musashi Official Full Logo -->
                            <div class="mb-2">
                                <img src="{{ !empty($musashiLogoBase64) ? $musashiLogoBase64 : asset('logomusashi.png') }}" alt="MUSASHI Power to Value" class="h-8 max-w-full object-contain">
                            </div>

                            <!-- RAHASIA Box -->
                            <div class="border border-slate-900 text-center py-0.5 font-bold tracking-wider text-slate-900 bg-slate-50 mb-2 text-xs">
                                {{ $formSettings['label_kerahasiaan'] ?? ($isGol4Up ? 'RAHASIA 秘密' : 'RAHASIA') }}
                            </div>
                        </div>

                        <div class="space-y-0.5 text-[11px] text-slate-700">
                            <div class="flex justify-between border-t border-slate-200 pt-1">
                                <span class="whitespace-nowrap">{{ $isGol4Up ? 'PERIODE 期間 :' : 'Periode :' }}</span>
                                <span class="font-medium whitespace-nowrap">{{ $formSettings['periode_penilaian'] ?? 'Jan - Des' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="whitespace-nowrap">{{ $isGol4Up ? 'TAHUN 年 :' : 'Tahun :' }}</span>
                                <span class="font-medium font-mono whitespace-nowrap">{{ $formSettings['tahun_penilaian'] ?? ($periode['tahun'] ?? 2025) }}</span>
                            </div>
                        </div>
                    </div>
                </td>

                <!-- Col 2: NAMA LENGKAP label -->
                <td class="w-[13%] whitespace-nowrap px-2 py-1 font-semibold text-slate-700 bg-white border-r border-slate-300 border-b border-slate-900 align-middle">
                    {{ $isGol4Up ? 'NAMA / 名前 :' : 'NAMA LENGKAP :' }}
                </td>
                <!-- Col 3: NAMA LENGKAP value -->
                <td class="w-[17%] px-2 py-1 font-bold text-slate-900 uppercase bg-[#e2efda] truncate border-r-2 border-slate-900 border-b border-slate-900 align-middle">
                    {{ $karyawan['nama_lengkap'] }}
                </td>

                <!-- Col 4 & 5: Title -->
                @if($isGol4Up)
                <td colspan="2" rowspan="2" class="w-[31%] py-2 px-2 text-center bg-white border-r-2 border-slate-900 border-b border-slate-900 align-middle">
                    <h2 class="text-sm sm:text-base font-black tracking-wide text-slate-900 uppercase leading-tight">
                        {{ $formSettings['judul_form_pk'] ?? 'PENILAIAN KARYA' }} / 仕事の評価
                    </h2>
                </td>
                @else
                <td colspan="2" class="w-[31%] py-1 px-2 text-center bg-white border-r-2 border-slate-900 border-b border-slate-900 align-middle">
                    <h2 class="text-sm sm:text-base font-black tracking-wide text-slate-900 uppercase leading-tight">
                        {{ $formSettings['judul_form_pk'] ?? 'PENILAIAN KARYA' }}
                    </h2>
                </td>
                @endif

                <!-- Col 6: Header Col 1 -->
                <td class="w-[8%] bg-[#d9d9d9] border-b border-r border-slate-900 font-bold text-[10px] sm:text-[11px] text-center p-1 text-slate-900 align-middle">
                    @if($subFormVariant === 'karyawan_gol4')
                        Section Head
                    @else
                        {{ $isGol4Up ? 'Kary. Ybs 個人的' : 'Foreman' }}
                    @endif
                </td>

                <!-- Col 7: Header Col 2 -->
                @if($subFormVariant === 'div_head' || $subFormVariant === 'dept_head')
                <td class="w-[8%] bg-[#bfbfbf] border-b border-r border-slate-900"></td>
                @else
                <td class="w-[8%] bg-[#d9d9d9] border-b border-r border-slate-900 font-bold text-[9.5px] sm:text-[10px] text-center p-1 text-slate-900 align-middle">
                    @if($subFormVariant === 'karyawan_gol4')
                        Department Head
                    @else
                        {{ $isGol4Up ? 'Atasan Langsung 直属の上司' : 'Section Head' }}
                    @endif
                </td>
                @endif

                <!-- Col 8: Header Col 3 -->
                @if($subFormVariant === 'div_head')
                <td class="w-[8%] bg-[#bfbfbf] border-b border-slate-900"></td>
                @else
                <td class="w-[8%] bg-[#d9d9d9] border-b border-slate-900 font-bold text-[9.5px] sm:text-[10px] text-center p-1 text-slate-900 align-middle">
                    @if($subFormVariant === 'karyawan_gol4')
                        Division Head
                    @else
                        {{ $isGol4Up ? 'Penilai Final 最終審査員' : 'Department Head' }}
                    @endif
                </td>
                @endif
            </tr>

            <!-- ROW 2 -->
            <tr>
                <!-- Col 2: NRP label -->
                <td class="whitespace-nowrap px-2 py-1 font-semibold text-slate-700 bg-white border-r border-slate-300 border-b border-slate-900 align-middle">
                    NRP :
                </td>
                <!-- Col 3: NRP value -->
                <td class="px-2 py-1 font-bold font-mono text-slate-900 bg-[#e2efda] border-r-2 border-slate-900 border-b border-slate-900 align-middle">
                    {{ $karyawan['nrp'] }}
                </td>

                @if(!$isGol4Up)
                <!-- Col 4 & 5: Bright Lime Green Banner (colspan 2) for standard forms only -->
                <td colspan="2" class="bg-[#00e000] text-slate-950 text-center font-black py-1 px-2 text-xs sm:text-sm border-r-2 border-b border-slate-900 tracking-wider align-middle">
                    {{ $bannerTitle }}
                </td>
                @endif
                <!-- If $isGol4Up, Col 4 & 5 are covered by rowspan="2" from Row 1! -->

                <!-- Col 6: Signature Col 1 (rowspan 2 for rows 2 & 3) -->
                <td rowspan="2" class="p-1 border-r border-b border-slate-900 align-middle text-center bg-white">
                    <div class="flex items-center justify-center h-full min-h-[46px]">
                        @php
                            if ($subFormVariant === 'karyawan_gol4') {
                                $foremanImg = $ttdByLevel['section_head']['ttd_base64'] ?? $ttdByLevel['section_head']['ttd_image'] ?? null;
                            } elseif ($subFormVariant === 'div_head') {
                                $foremanImg = $ttdByLevel['dept_head']['ttd_base64'] ?? $ttdByLevel['dept_head']['ttd_image'] ?? null;
                            } else {
                                $foremanImg = $ttdByLevel['foreman']['ttd_base64'] ?? $ttdByLevel['foreman']['ttd_image'] ?? null;
                            }
                        @endphp
                        @if($isTanpaForemanStd && $subFormVariant !== 'karyawan_gol4')
                            <span class="text-[11px] text-slate-300 italic font-medium">-</span>
                        @elseif(!empty($foremanImg))
                            <div class="p-1 flex items-center justify-center w-full h-full">
                                <img src="{{ $foremanImg }}" alt="Barcode TTD {{ $subFormVariant === 'karyawan_gol4' ? 'Section Head' : ($isGol4Up ? 'Kary. Ybs' : 'Foreman') }}" class="w-full h-full max-h-[82px] max-w-[82px] object-contain mx-auto p-1">
                            </div>
                        @else
                            <span class="text-[11px] text-slate-300 italic font-medium">Belum TTD</span>
                        @endif
                    </div>
                </td>

                <!-- Col 7: Signature Col 2 (rowspan 2 for rows 2 & 3) -->
                @if($subFormVariant === 'div_head' || $subFormVariant === 'dept_head')
                <td rowspan="2" class="p-1 border-r border-b border-slate-900 align-middle text-center bg-[#bfbfbf]"></td>
                @else
                <td rowspan="2" class="p-1 border-r border-b border-slate-900 align-middle text-center bg-white">
                    <div class="flex items-center justify-center h-full min-h-[46px]">
                        @php
                            if ($subFormVariant === 'karyawan_gol4') {
                                $shImg = $ttdByLevel['dept_head']['ttd_base64'] ?? $ttdByLevel['dept_head']['ttd_image'] ?? null;
                            } else {
                                $shImg = $ttdByLevel['section_head']['ttd_base64'] ?? $ttdByLevel['section_head']['ttd_image'] ?? null;
                            }
                        @endphp
                        @if(!empty($shImg))
                            <div class="p-0.5 flex items-center justify-center w-full h-full">
                                <img src="{{ $shImg }}" alt="Barcode TTD {{ $subFormVariant === 'karyawan_gol4' ? 'Department Head' : ($isGol4Up ? 'Atasan Langsung' : 'Section Head') }}" class="w-full h-full max-h-[82px] max-w-[82px] object-contain mx-auto p-1">
                            </div>
                        @else
                            <span class="text-[11px] text-slate-300 italic font-medium">Belum TTD</span>
                        @endif
                    </div>
                </td>
                @endif

                <!-- Col 8: Signature Col 3 (rowspan 2 for rows 2 & 3) -->
                @if($subFormVariant === 'div_head')
                <td rowspan="2" class="p-1 border-b border-slate-900 align-middle text-center bg-[#bfbfbf]"></td>
                @else
                <td rowspan="2" class="p-1 border-b border-slate-900 align-middle text-center bg-white">
                    <div class="flex items-center justify-center h-full min-h-[46px]">
                        @php
                            if ($subFormVariant === 'karyawan_gol4') {
                                $dhImg = $ttdByLevel['div_head']['ttd_base64'] ?? $ttdByLevel['div_head']['ttd_image'] ?? null;
                            } else {
                                $dhImg = $ttdByLevel['dept_head']['ttd_base64'] ?? $ttdByLevel['dept_head']['ttd_image'] ?? null;
                            }
                        @endphp
                        @if(!empty($dhImg))
                            <div class="p-0.5 flex items-center justify-center w-full h-full">
                                <img src="{{ $dhImg }}" alt="Barcode TTD {{ $subFormVariant === 'karyawan_gol4' ? 'Division Head' : ($isGol4Up ? 'Penilai Final' : 'Dept Head') }}" class="w-full h-full max-h-[82px] max-w-[82px] object-contain mx-auto p-1">
                            </div>
                        @else
                            <span class="text-[11px] text-slate-300 italic font-medium">Belum TTD</span>
                        @endif
                    </div>
                </td>
                @endif
            </tr>

            <!-- ROW 3 -->
            <tr>
                <!-- Col 2: GOLONGAN label -->
                <td class="whitespace-nowrap px-2 py-1 font-semibold text-slate-700 bg-white border-r border-slate-300 border-b border-slate-900 align-middle">
                    {{ $isGol4Up ? 'GOLONGAN / 役 :' : 'GOLONGAN :' }}
                </td>
                <!-- Col 3: GOLONGAN value -->
                <td class="px-2 py-1 font-semibold text-slate-800 bg-[#e2efda] border-r-2 border-slate-900 border-b border-slate-900 align-middle">
                    {{ $karyawan['sub_gol'] ?? $karyawan['golongan'] }}
                </td>

                <!-- Col 4 & 5: Subtitle boxes -->
                @if(!$isGol4Up)
                <td class="w-[15.5%] px-1 py-0.5 border-r border-slate-900 border-b border-slate-900 align-middle bg-white text-center">
                    <span class="text-[10px] text-slate-600 font-semibold uppercase block leading-tight">Seksi</span>
                </td>
                <td class="w-[15.5%] px-1 py-0.5 border-r-2 border-slate-900 border-b border-slate-900 align-middle bg-white text-center">
                    <span class="text-[10px] text-slate-600 font-semibold uppercase block leading-tight">Departemen</span>
                </td>
                @elseif($subFormVariant === 'div_head')
                <td colspan="2" class="w-[31%] px-1 py-0.5 border-r-2 border-slate-900 border-b border-slate-900 align-middle bg-white text-center font-bold text-[10.5px] text-slate-900 tracking-wider">
                    DIVISION
                </td>
                @elseif($subFormVariant === 'dept_head')
                <td class="w-[15.5%] px-1 py-0.5 border-r border-slate-900 border-b border-slate-900 align-middle bg-white text-center font-bold text-[9.5px] text-slate-900">
                    DEPARTEMEN / 部門
                </td>
                <td class="w-[15.5%] px-1 py-0.5 border-r-2 border-slate-900 border-b border-slate-900 align-middle bg-white text-center font-bold text-[9.5px] text-slate-900">
                    DIVISION
                </td>
                @else
                <td class="w-[15.5%] px-1 py-0.5 border-r border-slate-900 border-b border-slate-900 align-middle bg-white text-center font-bold text-[9.5px] text-slate-900">
                    SECTION / セクション
                </td>
                <td class="w-[15.5%] px-1 py-0.5 border-r-2 border-slate-900 border-b border-slate-900 align-middle bg-white text-center font-bold text-[9.5px] text-slate-900">
                    DEPARTEMEN / 部門
                </td>
                @endif
                <!-- Col 6, 7, 8 covered by rowspan 2 from row 2 -->
            </tr>

            <!-- ROW 4 -->
            <tr>
                <!-- Col 2: JABATAN label -->
                <td class="whitespace-nowrap px-2 py-1 font-semibold text-slate-700 bg-white border-r border-slate-300 border-b border-slate-900 align-middle">
                    {{ $isGol4Up ? 'JABATAN / 職位 :' : 'JABATAN :' }}
                </td>
                <!-- Col 3: JABATAN value -->
                <td class="px-2 py-1 font-semibold text-slate-800 truncate bg-[#e2efda] border-r-2 border-slate-900 border-b border-slate-900 align-middle">
                    {{ $karyawan['jabatan'] }}
                </td>

                <!-- Col 4 & 5 Values (rowspan 2 for rows 4 & 5) -->
                @if(!$isGol4Up)
                <td rowspan="2" class="px-1 py-1 border-r border-b-2 border-slate-900 align-middle bg-white text-center">
                    <span class="font-bold text-slate-900 uppercase block text-xs leading-tight truncate" title="Nama Seksi: {{ $displaySect }}">{{ $displaySect }}</span>
                </td>
                <td rowspan="2" class="px-1 py-1 border-r-2 border-b-2 border-slate-900 align-middle bg-white text-center">
                    <span class="font-bold text-slate-900 uppercase block text-xs leading-tight truncate">{{ $karyawan['departemen'] }}</span>
                </td>
                @elseif($subFormVariant === 'div_head')
                <td colspan="2" rowspan="2" class="px-1 py-1 border-r-2 border-b-2 border-slate-900 align-middle bg-white text-center">
                    <span class="font-bold text-slate-900 uppercase block text-xs leading-tight truncate">{{ $karyawan['departemen'] ?? 'MANUFACTURING' }}</span>
                </td>
                @elseif($subFormVariant === 'dept_head')
                <td rowspan="2" class="px-1 py-1 border-r border-b-2 border-slate-900 align-middle bg-white text-center">
                    <span class="font-bold text-slate-900 uppercase block text-xs leading-tight truncate">{{ $karyawan['departemen'] }}</span>
                </td>
                <td rowspan="2" class="px-1 py-1 border-r-2 border-b-2 border-slate-900 align-middle bg-white text-center">
                    <span class="font-bold text-slate-900 uppercase block text-xs leading-tight truncate">MANUFACTURING</span>
                </td>
                @else
                <td rowspan="2" class="px-1 py-1 border-r border-b-2 border-slate-900 align-middle bg-white text-center">
                    <span class="font-bold text-slate-900 uppercase block text-xs leading-tight truncate" title="Nama Seksi: {{ $displaySect }}">{{ $displaySect }}</span>
                </td>
                <td rowspan="2" class="px-1 py-1 border-r-2 border-b-2 border-slate-900 align-middle bg-white text-center">
                    <span class="font-bold text-slate-900 uppercase block text-xs leading-tight truncate">{{ $karyawan['departemen'] }}</span>
                </td>
                @endif

                <!-- Col 6: Foreman / Kary Ybs / Section Head Name (rowspan 2 for rows 4 & 5) -->
                <td rowspan="2" class="px-1 py-1 border-r border-b-2 border-slate-900 text-center align-middle bg-white">
                    @php
                        if ($subFormVariant === 'karyawan_gol4') {
                            $rawSignCol6 = $ttdByLevel['section_head']['user']['nama'] ?? '';
                            $karyName = !empty($rawSignCol6) ? trim(preg_replace('/\s*\(.*?\)/', '', $rawSignCol6)) : $shNama;
                        } else {
                            $rawSignForeman = $ttdByLevel['foreman']['user']['nama'] ?? '';
                            $karyName = !empty($rawSignForeman) ? trim(preg_replace('/\s*\(.*?\)/', '', $rawSignForeman)) : ($isGol4Up ? $karyawan['nama_lengkap'] : ($isTanpaForemanStd ? '' : $foremanNama));
                        }
                    @endphp
                    @if($isTanpaForemanStd && $subFormVariant !== 'karyawan_gol4' && empty($rawSignForeman))
                        &nbsp;
                    @elseif(!empty($karyName))
                        <span class="text-[10px] sm:text-[11px] text-slate-900 font-bold block leading-tight">{{ $karyName }}</span>
                    @else
                        <span class="text-[10px] text-slate-400 italic font-medium">-</span>
                    @endif
                    @if($subFormVariant === 'karyawan_gol4')<span class="text-[8.5px] text-slate-500 font-medium block">Section Head</span>@endif
                </td>

                <!-- Col 7: Section Head / Dept Head Name (rowspan 2 for rows 4 & 5) -->
                @if($subFormVariant === 'div_head' || $subFormVariant === 'dept_head')
                <td rowspan="2" class="px-1 py-1 border-r border-b-2 border-slate-900 bg-[#bfbfbf]"></td>
                @else
                <td rowspan="2" class="px-1 py-1 border-r border-b-2 border-slate-900 text-center align-middle bg-white">
                    @php
                        if ($subFormVariant === 'karyawan_gol4') {
                            $rawSignSh = $ttdByLevel['dept_head']['user']['nama'] ?? '';
                            $col7Name = !empty($rawSignSh) ? trim(preg_replace('/\s*\(.*?\)/', '', $rawSignSh)) : $dhNama;
                        } else {
                            $rawSignSh = $ttdByLevel['section_head']['user']['nama'] ?? '';
                            $col7Name = !empty($rawSignSh) ? trim(preg_replace('/\s*\(.*?\)/', '', $rawSignSh)) : ($isGol4Up ? ($dhNama ?: $shNama) : $shNama);
                        }
                    @endphp
                    @if(!empty($col7Name))
                        <span class="text-[10px] sm:text-[11px] text-slate-900 font-bold block leading-tight">{{ $col7Name }}</span>
                    @else
                        <span class="text-[10px] text-slate-400 italic font-medium">-</span>
                    @endif
                    @if($isGol4Up || $subFormVariant === 'karyawan_gol4')<span class="text-[8.5px] text-slate-500 font-medium block">Dept. Head</span>@endif
                </td>
                @endif

                <!-- Col 8: Dept Head / Div Head Name (rowspan 2 for rows 4 & 5) -->
                @if($subFormVariant === 'div_head')
                <td rowspan="2" class="px-1 py-1 border-b-2 border-slate-900 bg-[#bfbfbf]"></td>
                @else
                <td rowspan="2" class="px-1 py-1 border-b-2 border-slate-900 text-center align-middle bg-white">
                    @php
                        if ($subFormVariant === 'karyawan_gol4') {
                            $rawSignDh = $ttdByLevel['div_head']['user']['nama'] ?? '';
                            $col8Name = !empty($rawSignDh) ? trim(preg_replace('/\s*\(.*?\)/', '', $rawSignDh)) : $divHeadNama;
                        } else {
                            $rawSignDh = $ttdByLevel['dept_head']['user']['nama'] ?? '';
                            $col8Name = !empty($rawSignDh) ? trim(preg_replace('/\s*\(.*?\)/', '', $rawSignDh)) : ($isGol4Up ? '' : $dhNama);
                        }
                    @endphp
                    @if(!empty($col8Name))
                        <span class="text-[10px] sm:text-[11px] text-slate-900 font-bold block leading-tight">{{ $col8Name }}</span>
                    @else
                        <span class="text-[10px] text-slate-400 italic font-medium">-</span>
                    @endif
                    @if($isGol4Up || $subFormVariant === 'karyawan_gol4')<span class="text-[8.5px] text-slate-500 font-medium block">Div. Head</span>@endif
                </td>
                @endif
            </tr>

            <!-- ROW 5 -->
            <tr>
                <!-- Col 2: TANGGAL MASUK label -->
                <td class="whitespace-nowrap px-2 py-1 font-semibold text-slate-700 bg-white border-r border-slate-300 border-b-2 border-slate-900 align-middle">
                    {{ $isGol4Up ? 'TANGGAL MASUK / 入社 :' : 'TANGGAL MASUK :' }}
                </td>
                <!-- Col 3: TANGGAL MASUK value -->
                <td class="px-2 py-1 font-semibold text-slate-800 bg-[#e2efda] border-r-2 border-b-2 border-slate-900 align-middle">
                    {{ \App\Services\GoogleSyncService::formatMusashiDate($karyawan['tgl_masuk'] ?? '') }}
                </td>
                <!-- Col 1, 4, 5, 6, 7, 8 all covered by rowspans spanning into row 5! -->
            </tr>
        </table>

        <!-- ========================================================================= -->
        <!-- TWO-COLUMN MAIN BODY: LEFT (ASPEK PENILAIAN) vs RIGHT (INDIKATOR POTENSI) -->
        <!-- ========================================================================= -->
        <form id="formPenilaian" method="POST" action="{{ route('penilaian.saveDraft', $dokumen['id']) }}">
            @csrf
            <input type="hidden" name="level" value="{{ $activeLevel }}">

            <div class="grid grid-cols-12 border-2 border-slate-900 text-xs mt-[-2px]">
                
                <!-- ============================================== -->
                <!-- LEFT COLUMN: ASPEK PENILAIAN & PERHITUNGAN BOBOT (Col Span: 7) -->
                <!-- ============================================== -->
                <div class="col-span-12 lg:col-span-7 border-b lg:border-b-0 lg:border-r-2 border-slate-900 flex flex-col justify-between">
                    
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-200 border-b border-slate-900 font-bold text-slate-900 text-center text-[11px]">
                                <th colspan="2" rowspan="2" class="border-r border-slate-900 p-2 w-7/12 text-center uppercase tracking-wider">
                                    {{ $isGol4Up ? 'ASPEK PENILAIAN / 評価項目' : 'ASPEK PENILAIAN' }}
                                </th>
                                <th colspan="3" class="border-b border-slate-900 p-1.5 uppercase text-center w-5/12">
                                    {{ $isGol4Up ? 'PENILAIAN / 点数' : 'PENILAIAN' }}
                                </th>
                            </tr>
                            <tr class="bg-slate-100 border-b border-slate-900 font-bold text-slate-800 text-[10px] text-center">
                                @if($subFormVariant === 'karyawan_gol4')
                                <th class="border-r border-slate-900 p-1.5 w-1/12 {{ $canEditSectionHead ? 'bg-emerald-100 text-emerald-950 font-black' : '' }}">
                                    {{ $col1Title }}
                                </th>
                                <th class="border-r border-slate-900 p-1.5 w-2/12 {{ $canEditDeptHead ? 'bg-emerald-100 text-emerald-950 font-black' : '' }}">
                                    {{ $col2Title }}
                                </th>
                                <th class="p-1.5 w-2/12 {{ $canEditDivHead ? 'bg-emerald-100 text-emerald-950 font-black' : '' }}">
                                    {{ $col3Title }}
                                </th>
                                @elseif($subFormVariant === 'div_head')
                                <th class="border-r border-slate-900 p-1.5 w-1/12 bg-[#bfbfbf]"></th>
                                <th class="border-r border-slate-900 p-1.5 w-2/12 bg-[#bfbfbf]"></th>
                                <th class="p-1.5 w-2/12 {{ $canEditDeptHead ? 'bg-emerald-100 text-emerald-950 font-black' : '' }}">
                                    {{ $col3Title }}
                                </th>
                                @elseif($subFormVariant === 'dept_head')
                                <th class="border-r border-slate-900 p-1.5 w-1/12 {{ $canEditForeman ? 'bg-emerald-100 text-emerald-950 font-black' : '' }}">
                                    {{ $col1Title }}
                                </th>
                                <th class="border-r border-slate-900 p-1.5 w-2/12 bg-[#bfbfbf]"></th>
                                <th class="p-1.5 w-2/12 {{ $canEditDeptHead ? 'bg-emerald-100 text-emerald-950 font-black' : '' }}">
                                    {{ $col3Title }}
                                </th>
                                @else
                                <th class="border-r border-slate-900 p-1.5 w-1/12 {{ $canEditForeman ? 'bg-emerald-100 text-emerald-950 font-black' : '' }}">
                                    {{ $col1Title }}
                                </th>
                                <th class="border-r border-slate-900 p-1.5 w-2/12 {{ $canEditSectionHead ? 'bg-emerald-100 text-emerald-950 font-black' : '' }}">
                                    {{ $col2Title }}
                                </th>
                                <th class="p-1.5 w-2/12 {{ $canEditDeptHead ? 'bg-emerald-100 text-emerald-950 font-black' : '' }}">
                                    {{ $col3Title }}
                                </th>
                                @endif
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-400 text-xs">
                            @foreach($formStructure['groups'] as $groupKey => $group)
                                
                                <!-- Aspect Rows -->
                                @php $isFirstInGroup = true; @endphp
                                @foreach($group['aspects'] as $aspectCode => $aspectName)
                                <tr class="hover:bg-slate-50 transition-colors">
                                    
                                    <!-- Group Name spanning vertically on first row (Col 1) -->
                                    @if($isFirstInGroup)
                                    <td rowspan="{{ count($group['aspects']) }}" class="border-r border-slate-900 p-2 align-middle text-center font-bold bg-white text-slate-900 border-b border-slate-900 w-3/12">
                                        <div class="uppercase tracking-wide leading-tight">
                                            @if($isGol4Up)
                                                @php $parts = explode('/', $group['name']); @endphp
                                                <div class="font-bold text-slate-900">{{ trim($parts[0] ?? $group['name']) }}</div>
                                                @if(isset($parts[1]))
                                                    <div class="text-[10px] text-slate-600 font-medium mt-0.5 normal-case">{{ trim($parts[1]) }}</div>
                                                @endif
                                            @else
                                                {{ $group['name'] }}
                                            @endif
                                        </div>
                                    </td>
                                    @php $isFirstInGroup = false; @endphp
                                    @endif

                                    <!-- Aspect Title (Col 2): Clean label like in Excel -->
                                    <td class="border-r border-slate-900 p-2 align-middle bg-white w-4/12">
                                        @php
                                            $dispAspect = $aspectName;
                                            if ($isGol4Up) {
                                                if ($aspectCode === 'PK_PLAN') $dispAspect = 'Plan';
                                                elseif ($aspectCode === 'PK_DO') $dispAspect = 'Do';
                                                elseif ($aspectCode === 'PK_CHECK') $dispAspect = 'Check';
                                                elseif ($aspectCode === 'PK_ACTION') $dispAspect = 'Action';
                                                elseif ($aspectCode === 'MM_KEPUTUSAN') $dispAspect = 'Pengambil Keputusan / 決まる能力';
                                                elseif ($aspectCode === 'MM_PENGEMBANGAN') $dispAspect = 'Pengembangan Bawahan (Counching & Counseling) / 部下の発展の能力';
                                            }
                                        @endphp
                                        <span class="font-medium text-slate-900 leading-tight block">{{ $dispAspect }}</span>
                                        <template x-if="kriteriaDescriptions['{{ $aspectCode }}']">
                                            <span class="text-[9.5px] text-emerald-800 italic block mt-1 leading-snug font-sans bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200"
                                                  x-text="'“' + kriteriaDescriptions['{{ $aspectCode }}'] + '”'"></span>
                                        </template>
                                    </td>

                                    <!-- Foreman / Section Head Score Column (Col 3): Clickable Excel-style cell -->
                                    @if($subFormVariant === 'karyawan_gol4')
                                    <td class="border-r border-slate-900 p-1 text-center align-middle {{ $canEditSectionHead ? 'bg-[#e2efda] hover:bg-[#d5ebd0] cursor-pointer' : 'bg-white' }}"
                                        @if($canEditSectionHead)
                                        @click.stop="openRubrikModal('{{ $aspectCode }}', '{{ addslashes($aspectName) }}', 'section_head')"
                                        title="Klik untuk memilih dari 5 kalimat kriteria Musashi"
                                        @endif>
                                        @if($canEditSectionHead)
                                            <input type="hidden" 
                                                   name="scores[{{ $aspectCode }}]" 
                                                   :value="scoresSectionHead['{{ $aspectCode }}'] || ''">
                                            
                                            <!-- Unified Single Click-Selector Box -->
                                            <div class="py-1 px-1 flex items-center justify-center">
                                                <button type="button" 
                                                        @click.stop="openRubrikModal('{{ $aspectCode }}', '{{ addslashes($aspectName) }}', 'section_head')"
                                                        class="w-full min-h-[34px] px-2.5 py-1 rounded-lg border flex items-center justify-between gap-1.5 transition-all shadow-xs group cursor-pointer"
                                                        :class="scoresSectionHead['{{ $aspectCode }}'] 
                                                            ? 'bg-emerald-700 hover:bg-emerald-800 text-white border-emerald-800 shadow-emerald-200' 
                                                            : 'bg-white hover:bg-emerald-50 text-slate-800 border-emerald-500 hover:border-emerald-600'"
                                                        title="Klik untuk memilih dari 5 kalimat kriteria (Skor 1-5)">
                                                    <div class="flex items-center gap-1.5 font-mono">
                                                        <span x-show="scoresSectionHead['{{ $aspectCode }}']" 
                                                              x-text="scoresSectionHead['{{ $aspectCode }}']" 
                                                              class="font-black text-base text-white leading-none"></span>
                                                        <span x-show="!scoresSectionHead['{{ $aspectCode }}']" 
                                                              class="text-xs font-bold text-slate-400 leading-none">-</span>
                                                    </div>
                                                    <div class="flex items-center gap-1">
                                                        <span x-show="!scoresSectionHead['{{ $aspectCode }}']" 
                                                              class="text-[10.5px] font-semibold text-emerald-800 group-hover:text-emerald-950">
                                                            Pilih Kriteria
                                                        </span>
                                                        <span x-show="scoresSectionHead['{{ $aspectCode }}']" 
                                                              class="text-[10px] text-emerald-100 font-medium">
                                                            Ubah
                                                        </span>
                                                        <svg class="w-3.5 h-3.5 opacity-80 group-hover:opacity-100 transition-transform group-hover:translate-y-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                                        </svg>
                                                    </div>
                                                </button>
                                            </div>
                                        @else
                                            @if(!$canViewSectionHead)
                                                <span class="font-bold font-mono text-sm text-slate-400">-</span>
                                            @else
                                                <span class="font-bold font-mono text-sm text-slate-900">
                                                    {{ isset($scoresByLevel['section_head'][$aspectCode]) ? number_format($scoresByLevel['section_head'][$aspectCode], 0) : '-' }}
                                                </span>
                                            @endif
                                        @endif
                                    </td>
                                    @elseif($subFormVariant === 'div_head')
                                    <td class="border-r border-slate-900 p-1 bg-[#bfbfbf]"></td>
                                    @else
                                    <td class="border-r border-slate-900 p-1 text-center align-middle {{ $canEditForeman ? 'bg-[#e2efda] hover:bg-[#d5ebd0] cursor-pointer' : 'bg-white' }}"
                                        @if($canEditForeman)
                                        @click.stop="openRubrikModal('{{ $aspectCode }}', '{{ addslashes($aspectName) }}', 'foreman')"
                                        title="Klik untuk memilih dari 5 kalimat kriteria Musashi"
                                        @endif>
                                        @if($canEditForeman)
                                            <input type="hidden" 
                                                   name="scores[{{ $aspectCode }}]" 
                                                   :value="scoresForeman['{{ $aspectCode }}'] || ''">
                                            
                                            <!-- Unified Single Click-Selector Box -->
                                            <div class="py-1 px-1 flex items-center justify-center">
                                                <button type="button" 
                                                        @click.stop="openRubrikModal('{{ $aspectCode }}', '{{ addslashes($aspectName) }}', 'foreman')"
                                                        class="w-full min-h-[34px] px-2.5 py-1 rounded-lg border flex items-center justify-between gap-1.5 transition-all shadow-xs group cursor-pointer"
                                                        :class="scoresForeman['{{ $aspectCode }}'] 
                                                            ? 'bg-emerald-700 hover:bg-emerald-800 text-white border-emerald-800 shadow-emerald-200' 
                                                            : 'bg-white hover:bg-emerald-50 text-slate-800 border-emerald-500 hover:border-emerald-600'"
                                                        title="Klik untuk memilih dari 5 kalimat kriteria (Skor 1-5)">
                                                    
                                                    <!-- Left: Score number or '-' -->
                                                    <div class="flex items-center gap-1.5 font-mono">
                                                        <span x-show="scoresForeman['{{ $aspectCode }}']" 
                                                              x-text="scoresForeman['{{ $aspectCode }}']" 
                                                              class="font-black text-base text-white leading-none"></span>
                                                        <span x-show="!scoresForeman['{{ $aspectCode }}']" 
                                                              class="text-xs font-bold text-slate-400 leading-none">-</span>
                                                    </div>

                                                    <!-- Right: Label + Dropdown Icon -->
                                                    <div class="flex items-center gap-1">
                                                        <span x-show="!scoresForeman['{{ $aspectCode }}']" 
                                                              class="text-[10.5px] font-semibold text-emerald-800 group-hover:text-emerald-950">
                                                            Pilih Kriteria
                                                        </span>
                                                        <span x-show="scoresForeman['{{ $aspectCode }}']" 
                                                              class="text-[10px] text-emerald-100 font-medium">
                                                            Ubah
                                                        </span>
                                                        <svg class="w-3.5 h-3.5 opacity-80 group-hover:opacity-100 transition-transform group-hover:translate-y-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                                        </svg>
                                                    </div>
                                                </button>
                                            </div>
                                        @else
                                            @if(!$canViewForeman || ($isTanpaForemanStd && !$showForemanData))
                                                <span class="font-bold font-mono text-sm text-slate-400">-</span>
                                            @else
                                                <span class="font-bold font-mono text-sm text-slate-900">
                                                    {{ isset($scoresByLevel['foreman'][$aspectCode]) ? number_format($scoresByLevel['foreman'][$aspectCode], 0) : '-' }}
                                                </span>
                                            @endif
                                        @endif
                                    </td>
                                    @endif

                                    <!-- Section Head / Dept Head Score Column (Col 4): Clickable Excel-style cell -->
                                    @if($subFormVariant === 'karyawan_gol4')
                                    <td class="border-r border-slate-900 p-1 text-center align-middle {{ $canEditDeptHead ? 'bg-[#e2efda] hover:bg-[#d5ebd0] cursor-pointer' : 'bg-white' }}"
                                        @if($canEditDeptHead)
                                        @click.stop="openRubrikModal('{{ $aspectCode }}', '{{ addslashes($aspectName) }}', 'dept_head')"
                                        title="Klik untuk memilih dari 5 kalimat kriteria Musashi"
                                        @endif>
                                        @if($canEditDeptHead)
                                            <input type="hidden" 
                                                   name="scores[{{ $aspectCode }}]" 
                                                   :value="scoresDeptHead['{{ $aspectCode }}'] || ''">
                                            
                                            <!-- Unified Single Click-Selector Box -->
                                            <div class="py-1 px-1 flex items-center justify-center">
                                                <button type="button" 
                                                        @click.stop="openRubrikModal('{{ $aspectCode }}', '{{ addslashes($aspectName) }}', 'dept_head')"
                                                        class="w-full min-h-[34px] px-2.5 py-1 rounded-lg border flex items-center justify-between gap-1.5 transition-all shadow-xs group cursor-pointer"
                                                        :class="scoresDeptHead['{{ $aspectCode }}'] 
                                                            ? 'bg-emerald-700 hover:bg-emerald-800 text-white border-emerald-800 shadow-emerald-200' 
                                                            : 'bg-white hover:bg-emerald-50 text-slate-800 border-emerald-500 hover:border-emerald-600'"
                                                        title="Klik untuk memilih dari 5 kalimat kriteria (Skor 1-5)">
                                                    <div class="flex items-center gap-1.5 font-mono">
                                                        <span x-show="scoresDeptHead['{{ $aspectCode }}']" 
                                                              x-text="scoresDeptHead['{{ $aspectCode }}']" 
                                                              class="font-black text-base text-white leading-none"></span>
                                                        <span x-show="!scoresDeptHead['{{ $aspectCode }}']" 
                                                              class="text-xs font-bold text-slate-400 leading-none">-</span>
                                                    </div>
                                                    <div class="flex items-center gap-1">
                                                        <span x-show="!scoresDeptHead['{{ $aspectCode }}']" 
                                                              class="text-[10.5px] font-semibold text-emerald-800 group-hover:text-emerald-950">
                                                            Pilih Kriteria
                                                        </span>
                                                        <span x-show="scoresDeptHead['{{ $aspectCode }}']" 
                                                              class="text-[10px] text-emerald-100 font-medium">
                                                            Ubah
                                                        </span>
                                                        <svg class="w-3.5 h-3.5 opacity-80 group-hover:opacity-100 transition-transform group-hover:translate-y-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                                        </svg>
                                                    </div>
                                                </button>
                                            </div>
                                        @else
                                            @if(!$canViewDeptHead)
                                                <span class="font-bold font-mono text-sm text-slate-400">-</span>
                                            @else
                                                <span class="font-bold font-mono text-sm text-slate-900">
                                                    {{ isset($scoresByLevel['dept_head'][$aspectCode]) ? number_format($scoresByLevel['dept_head'][$aspectCode], 0) : '-' }}
                                                </span>
                                            @endif
                                        @endif
                                    </td>
                                    @elseif($subFormVariant === 'div_head' || $subFormVariant === 'dept_head')
                                    <td class="border-r border-slate-900 p-1 bg-[#bfbfbf]"></td>
                                    @else
                                    <td class="border-r border-slate-900 p-1 text-center align-middle {{ $canEditSectionHead ? 'bg-[#e2efda] hover:bg-[#d5ebd0] cursor-pointer' : 'bg-white' }}"
                                        @if($canEditSectionHead)
                                        @click.stop="openRubrikModal('{{ $aspectCode }}', '{{ addslashes($aspectName) }}', 'section_head')"
                                        title="Klik untuk memilih dari 5 kalimat kriteria Musashi"
                                        @endif>
                                        @if($canEditSectionHead)
                                            <input type="hidden" 
                                                   name="scores[{{ $aspectCode }}]" 
                                                   :value="scoresSectionHead['{{ $aspectCode }}'] || ''">
                                            
                                            <!-- Unified Single Click-Selector Box -->
                                            <div class="py-1 px-1 flex items-center justify-center">
                                                <button type="button" 
                                                        @click.stop="openRubrikModal('{{ $aspectCode }}', '{{ addslashes($aspectName) }}', 'section_head')"
                                                        class="w-full min-h-[34px] px-2.5 py-1 rounded-lg border flex items-center justify-between gap-1.5 transition-all shadow-xs group cursor-pointer"
                                                        :class="scoresSectionHead['{{ $aspectCode }}'] 
                                                            ? 'bg-emerald-700 hover:bg-emerald-800 text-white border-emerald-800 shadow-emerald-200' 
                                                            : 'bg-white hover:bg-emerald-50 text-slate-800 border-emerald-500 hover:border-emerald-600'"
                                                        title="Klik untuk memilih dari 5 kalimat kriteria (Skor 1-5)">
                                                    
                                                    <!-- Left: Score number or '-' -->
                                                    <div class="flex items-center gap-1.5 font-mono">
                                                        <span x-show="scoresSectionHead['{{ $aspectCode }}']" 
                                                              x-text="scoresSectionHead['{{ $aspectCode }}']" 
                                                              class="font-black text-base text-white leading-none"></span>
                                                        <span x-show="!scoresSectionHead['{{ $aspectCode }}']" 
                                                              class="text-xs font-bold text-slate-400 leading-none">-</span>
                                                    </div>

                                                    <!-- Right: Label + Dropdown Icon -->
                                                    <div class="flex items-center gap-1">
                                                        <span x-show="!scoresSectionHead['{{ $aspectCode }}']" 
                                                              class="text-[10.5px] font-semibold text-emerald-800 group-hover:text-emerald-950">
                                                            Pilih Kriteria
                                                        </span>
                                                        <span x-show="scoresSectionHead['{{ $aspectCode }}']" 
                                                              class="text-[10px] text-emerald-100 font-medium">
                                                            Ubah
                                                        </span>
                                                        <svg class="w-3.5 h-3.5 opacity-80 group-hover:opacity-100 transition-transform group-hover:translate-y-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                                        </svg>
                                                    </div>
                                                </button>
                                            </div>
                                        @else
                                            @if(!$canViewSectionHead)
                                                <span class="font-bold font-mono text-sm text-slate-400">-</span>
                                            @else
                                                <span class="font-bold font-mono text-sm text-slate-900">
                                                    {{ isset($scoresByLevel['section_head'][$aspectCode]) ? number_format($scoresByLevel['section_head'][$aspectCode], 0) : '-' }}
                                                </span>
                                            @endif
                                        @endif
                                    </td>
                                    @endif

                                    <!-- Dept Head / Div Head Score Column (Col 5): Clickable Excel-style cell -->
                                    @if($subFormVariant === 'karyawan_gol4')
                                    <td class="p-1 text-center align-middle {{ $canEditDivHead ? 'bg-[#e2efda] hover:bg-[#d5ebd0] cursor-pointer' : 'bg-white' }}"
                                        @if($canEditDivHead)
                                        @click.stop="openRubrikModal('{{ $aspectCode }}', '{{ addslashes($aspectName) }}', 'div_head')"
                                        title="Klik untuk memilih dari 5 kalimat kriteria Musashi"
                                        @endif>
                                        @if($canEditDivHead)
                                            <input type="hidden" 
                                                   name="scores[{{ $aspectCode }}]" 
                                                   :value="scoresDivHead['{{ $aspectCode }}'] || ''">
                                            
                                            <!-- Unified Single Click-Selector Box -->
                                            <div class="py-1 px-1 flex items-center justify-center">
                                                <button type="button" 
                                                        @click.stop="openRubrikModal('{{ $aspectCode }}', '{{ addslashes($aspectName) }}', 'div_head')"
                                                        class="w-full min-h-[34px] px-2.5 py-1 rounded-lg border flex items-center justify-between gap-1.5 transition-all shadow-xs group cursor-pointer"
                                                        :class="scoresDivHead['{{ $aspectCode }}'] 
                                                            ? 'bg-emerald-700 hover:bg-emerald-800 text-white border-emerald-800 shadow-emerald-200' 
                                                            : 'bg-white hover:bg-emerald-50 text-slate-800 border-emerald-500 hover:border-emerald-600'"
                                                        title="Klik untuk memilih dari 5 kalimat kriteria (Skor 1-5)">
                                                    <div class="flex items-center gap-1.5 font-mono">
                                                        <span x-show="scoresDivHead['{{ $aspectCode }}']" 
                                                              x-text="scoresDivHead['{{ $aspectCode }}']" 
                                                              class="font-black text-base text-white leading-none"></span>
                                                        <span x-show="!scoresDivHead['{{ $aspectCode }}']" 
                                                              class="text-xs font-bold text-slate-400 leading-none">-</span>
                                                    </div>
                                                    <div class="flex items-center gap-1">
                                                        <span x-show="!scoresDivHead['{{ $aspectCode }}']" 
                                                              class="text-[10.5px] font-semibold text-emerald-800 group-hover:text-emerald-950">
                                                            Pilih Kriteria
                                                        </span>
                                                        <span x-show="scoresDivHead['{{ $aspectCode }}']" 
                                                              class="text-[10px] text-emerald-100 font-medium">
                                                            Ubah
                                                        </span>
                                                        <svg class="w-3.5 h-3.5 opacity-80 group-hover:opacity-100 transition-transform group-hover:translate-y-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                                        </svg>
                                                    </div>
                                                </button>
                                            </div>
                                        @else
                                            @if(!$canViewDeptHead)
                                                <span class="font-bold font-mono text-sm text-slate-400">-</span>
                                            @else
                                                <span class="font-bold font-mono text-sm text-slate-900">
                                                    {{ isset($scoresByLevel['div_head'][$aspectCode]) ? number_format($scoresByLevel['div_head'][$aspectCode], 0) : '-' }}
                                                </span>
                                            @endif
                                        @endif
                                    </td>
                                    @else
                                    <td class="p-1 text-center align-middle {{ $canEditDeptHead ? 'bg-[#e2efda] hover:bg-[#d5ebd0] cursor-pointer' : 'bg-white' }}"
                                        @if($canEditDeptHead)
                                        @click.stop="openRubrikModal('{{ $aspectCode }}', '{{ addslashes($aspectName) }}', 'dept_head')"
                                        title="Klik untuk memilih dari 5 kalimat kriteria Musashi"
                                        @endif>
                                        @if($canEditDeptHead)
                                            <input type="hidden" 
                                                   name="scores[{{ $aspectCode }}]" 
                                                   :value="scoresDeptHead['{{ $aspectCode }}'] || ''">
                                            
                                            <!-- Unified Single Click-Selector Box -->
                                            <div class="py-1 px-1 flex items-center justify-center">
                                                <button type="button" 
                                                        @click.stop="openRubrikModal('{{ $aspectCode }}', '{{ addslashes($aspectName) }}', 'dept_head')"
                                                        class="w-full min-h-[34px] px-2.5 py-1 rounded-lg border flex items-center justify-between gap-1.5 transition-all shadow-xs group cursor-pointer"
                                                        :class="scoresDeptHead['{{ $aspectCode }}'] 
                                                            ? 'bg-emerald-700 hover:bg-emerald-800 text-white border-emerald-800 shadow-emerald-200' 
                                                            : 'bg-white hover:bg-emerald-50 text-slate-800 border-emerald-500 hover:border-emerald-600'"
                                                        title="Klik untuk memilih dari 5 kalimat kriteria (Skor 1-5)">
                                                    
                                                    <!-- Left: Score number or '-' -->
                                                    <div class="flex items-center gap-1.5 font-mono">
                                                        <span x-show="scoresDeptHead['{{ $aspectCode }}']" 
                                                              x-text="scoresDeptHead['{{ $aspectCode }}']" 
                                                              class="font-black text-base text-white leading-none"></span>
                                                        <span x-show="!scoresDeptHead['{{ $aspectCode }}']" 
                                                              class="text-xs font-bold text-slate-400 leading-none">-</span>
                                                    </div>

                                                    <!-- Right: Label + Dropdown Icon -->
                                                    <div class="flex items-center gap-1">
                                                        <span x-show="!scoresDeptHead['{{ $aspectCode }}']" 
                                                              class="text-[10.5px] font-semibold text-emerald-800 group-hover:text-emerald-950">
                                                            Pilih Kriteria
                                                        </span>
                                                        <span x-show="scoresDeptHead['{{ $aspectCode }}']" 
                                                              class="text-[10px] text-emerald-100 font-medium">
                                                            Ubah
                                                        </span>
                                                        <svg class="w-3.5 h-3.5 opacity-80 group-hover:opacity-100 transition-transform group-hover:translate-y-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                                        </svg>
                                                    </div>
                                                </button>
                                            </div>
                                        @else
                                            @if(!$canViewDeptHead)
                                                <span class="font-bold font-mono text-sm text-slate-400">-</span>
                                            @else
                                                <span class="font-bold font-mono text-sm text-slate-900">
                                                    {{ isset($scoresByLevel['dept_head'][$aspectCode]) ? number_format($scoresByLevel['dept_head'][$aspectCode], 0) : '-' }}
                                                </span>
                                            @endif
                                        @endif
                                    </td>
                                    @endif

                                </tr>
                                @endforeach

                                <!-- Group TOTAL Row -->
                                <tr class="bg-white font-bold border-t border-slate-900">
                                    <td colspan="2" class="border-r border-slate-900 p-1.5 text-right uppercase text-[11px] text-slate-800">
                                        {{ $isGol4Up ? 'TOTAL 合計' : 'TOTAL' }}
                                    </td>
                                    @if($subFormVariant === 'karyawan_gol4')
                                    <td class="border-r border-slate-900 p-1 text-center font-mono font-bold text-slate-900">
                                        @if(!$canViewSectionHead)
                                            <span class="text-slate-400 font-normal">-</span>
                                        @else
                                            <span x-text="totalsSectionHead['{{ $groupKey }}']?.sum ?? '0'">0</span>
                                        @endif
                                    </td>
                                    <td class="border-r border-slate-900 p-1 text-center font-mono font-bold text-slate-900">
                                        @if(!$canViewDeptHead)
                                            <span class="text-slate-400 font-normal">-</span>
                                        @else
                                            <span x-text="totalsDeptHead['{{ $groupKey }}']?.sum ?? '0'">0</span>
                                        @endif
                                    </td>
                                    <td class="p-1 text-center font-mono font-bold text-slate-900">
                                        @if(!$canViewDeptHead)
                                            <span class="text-slate-400 font-normal">-</span>
                                        @else
                                            <span x-text="totalsDivHead['{{ $groupKey }}']?.sum ?? '0'">0</span>
                                        @endif
                                    </td>
                                    @elseif($subFormVariant === 'div_head')
                                    <td class="border-r border-slate-900 p-1 bg-[#bfbfbf]"></td>
                                    <td class="border-r border-slate-900 p-1 bg-[#bfbfbf]"></td>
                                    <td class="p-1 text-center font-mono font-bold text-slate-900">
                                        @if(!$canViewDeptHead)
                                            <span class="text-slate-400 font-normal">-</span>
                                        @else
                                            <span x-text="totalsDeptHead['{{ $groupKey }}']?.sum ?? '0'">0</span>
                                        @endif
                                    </td>
                                    @elseif($subFormVariant === 'dept_head')
                                    <td class="border-r border-slate-900 p-1 text-center font-mono font-bold text-slate-900">
                                        @if(!$canViewForeman)
                                            <span class="text-slate-400 font-normal">-</span>
                                        @else
                                            <span x-text="totalsForeman['{{ $groupKey }}']?.sum ?? '0'">0</span>
                                        @endif
                                    </td>
                                    <td class="border-r border-slate-900 p-1 bg-[#bfbfbf]"></td>
                                    <td class="p-1 text-center font-mono font-bold text-slate-900">
                                        @if(!$canViewDeptHead)
                                            <span class="text-slate-400 font-normal">-</span>
                                        @else
                                            <span x-text="totalsDeptHead['{{ $groupKey }}']?.sum ?? '0'">0</span>
                                        @endif
                                    </td>
                                    @else
                                    <td class="border-r border-slate-900 p-1 text-center font-mono font-bold text-slate-900">
                                        @if(!$canViewForeman || ($isTanpaForemanStd && !$canEditForeman && !$showForemanData))
                                            <span class="text-slate-400 font-normal">-</span>
                                        @else
                                            <span x-text="totalsForeman['{{ $groupKey }}']?.sum ?? '0'">0</span>
                                        @endif
                                    </td>
                                    <td class="border-r border-slate-900 p-1 text-center font-mono font-bold text-slate-900">
                                        @if(!$canViewSectionHead)
                                            <span class="text-slate-400 font-normal">-</span>
                                        @else
                                            <span x-text="totalsSectionHead['{{ $groupKey }}']?.sum ?? '0'">0</span>
                                        @endif
                                    </td>
                                    <td class="p-1 text-center font-mono font-bold text-slate-900">
                                        @if(!$canViewDeptHead)
                                            <span class="text-slate-400 font-normal">-</span>
                                        @else
                                            <span x-text="totalsDeptHead['{{ $groupKey }}']?.sum ?? '0'">0</span>
                                        @endif
                                    </td>
                                    @endif
                                </tr>

                                @php
                                    $rataTitle = match(true) {
                                        $isGol4Up && $groupKey === 'hasil_kerja' => 'RATA - RATA HASIL KERJA (Total Nilai dibagi 5) / 仕事の成果の平均（合計値を5で割ったもの）',
                                        $isGol4Up && $groupKey === 'proses_kerja' => 'RATA - RATA PROSES KERJA (Total Nilai dibagi 4) 作業プロセスの平均（合計値を4で割ったもの）',
                                        $isGol4Up && $groupKey === 'man_management' => 'RATA - RATA MANAGEMENT (Total Nilai dibagi 4) 平均管理（合計値を4で割ったもの',
                                        $isGol4Up && $groupKey === 'sikap_kerja' => 'RATA - RATA SIKAP KERJA (Total Nilai dibagi 3) 仕事の平均（合計値を3で割ったもの',
                                        default => 'RATA - RATA ' . strtoupper(explode('(', $group['name'])[0]) . ' (Total Nilai dibagi ' . count($group['aspects']) . ')',
                                    };
                                    $nilaiAkhirTitle = match(true) {
                                        $isGol4Up && $groupKey === 'hasil_kerja' => 'NILAI AKHIR HASIL KERJA (Rata-Rata Hasil Kerja X 55%) / 仕事成果の最終得点 平均労働記述X 55%)',
                                        $isGol4Up && $groupKey === 'proses_kerja' => 'NILAI AKHIR PROSES KERJA (Rata-Rata Proses Kerja X 15%) / 最終的な作業手順（平均作業手順X 15%)',
                                        $isGol4Up && $groupKey === 'man_management' => 'NILAI AKHIR MANAGEMENT (Rata-Rata Sikap Kerja X 15%) ファイナルバリユーマネジメント（平均作業姿勢X 15%)',
                                        $isGol4Up && $groupKey === 'sikap_kerja' => 'NILAI AKHIR SIKAP KERJA (Rata-Rata Sikap Kerja X 15%) 職場における最終的な価値（平均職務態度X 15%）',
                                        default => 'NILAI AKHIR ' . strtoupper(explode('(', $group['name'])[0]) . ' (Rata-Rata ' . explode('(', $group['name'])[0] . ' X ' . $group['weight'] . '%)',
                                    };
                                @endphp

                                <!-- Group RATA - RATA Row -->
                                <tr class="bg-white font-bold border-t border-slate-300">
                                    <td colspan="2" class="border-r border-slate-900 p-1.5 text-slate-700 text-[10.5px]">
                                        {{ $rataTitle }}
                                    </td>
                                    @if($subFormVariant === 'karyawan_gol4')
                                    <td class="border-r border-slate-900 p-1 text-center font-mono text-slate-900">
                                        @if(!$canViewSectionHead)
                                            <span class="text-slate-400 font-normal">-</span>
                                        @else
                                            <span x-text="totalsSectionHead['{{ $groupKey }}']?.avg ?? '0.00'">0.00</span>
                                        @endif
                                    </td>
                                    <td class="border-r border-slate-900 p-1 text-center font-mono text-slate-900">
                                        @if(!$canViewDeptHead)
                                            <span class="text-slate-400 font-normal">-</span>
                                        @else
                                            <span x-text="totalsDeptHead['{{ $groupKey }}']?.avg ?? '0.00'">0.00</span>
                                        @endif
                                    </td>
                                    <td class="p-1 text-center font-mono text-slate-900">
                                        @if(!$canViewDeptHead)
                                            <span class="text-slate-400 font-normal">-</span>
                                        @else
                                            <span x-text="totalsDivHead['{{ $groupKey }}']?.avg ?? '0.00'">0.00</span>
                                        @endif
                                    </td>
                                    @elseif($subFormVariant === 'div_head')
                                    <td class="border-r border-slate-900 p-1 bg-[#bfbfbf]"></td>
                                    <td class="border-r border-slate-900 p-1 bg-[#bfbfbf]"></td>
                                    <td class="p-1 text-center font-mono text-slate-900">
                                        @if(!$canViewDeptHead)
                                            <span class="text-slate-400 font-normal">-</span>
                                        @else
                                            <span x-text="totalsDeptHead['{{ $groupKey }}']?.avg ?? '0.00'">0.00</span>
                                        @endif
                                    </td>
                                    @elseif($subFormVariant === 'dept_head')
                                    <td class="border-r border-slate-900 p-1 text-center font-mono text-slate-900">
                                        @if(!$canViewForeman)
                                            <span class="text-slate-400 font-normal">-</span>
                                        @else
                                            <span x-text="totalsForeman['{{ $groupKey }}']?.avg ?? '0.00'">0.00</span>
                                        @endif
                                    </td>
                                    <td class="border-r border-slate-900 p-1 bg-[#bfbfbf]"></td>
                                    <td class="p-1 text-center font-mono text-slate-900">
                                        @if(!$canViewDeptHead)
                                            <span class="text-slate-400 font-normal">-</span>
                                        @else
                                            <span x-text="totalsDeptHead['{{ $groupKey }}']?.avg ?? '0.00'">0.00</span>
                                        @endif
                                    </td>
                                    @else
                                    <td class="border-r border-slate-900 p-1 text-center font-mono text-slate-900">
                                        @if(!$canViewForeman || ($isTanpaForemanStd && !$canEditForeman && !$showForemanData))
                                            <span class="text-slate-400 font-normal">-</span>
                                        @else
                                            <span x-text="totalsForeman['{{ $groupKey }}']?.avg ?? '0.00'">0.00</span>
                                        @endif
                                    </td>
                                    <td class="border-r border-slate-900 p-1 text-center font-mono text-slate-900">
                                        @if(!$canViewSectionHead)
                                            <span class="text-slate-400 font-normal">-</span>
                                        @else
                                            <span x-text="totalsSectionHead['{{ $groupKey }}']?.avg ?? '0.00'">0.00</span>
                                        @endif
                                    </td>
                                    <td class="p-1 text-center font-mono text-slate-900">
                                        @if(!$canViewDeptHead)
                                            <span class="text-slate-400 font-normal">-</span>
                                        @else
                                            <span x-text="totalsDeptHead['{{ $groupKey }}']?.avg ?? '0.00'">0.00</span>
                                        @endif
                                    </td>
                                    @endif
                                </tr>

                                <!-- Group NILAI AKHIR Row -->
                                <tr class="bg-slate-50 font-bold border-t border-b-2 border-slate-900">
                                    <td colspan="2" class="border-r border-slate-900 p-1.5 text-slate-900 text-[10.5px]">
                                        {{ $nilaiAkhirTitle }}
                                    </td>
                                    @if($subFormVariant === 'karyawan_gol4')
                                    <td class="border-r border-slate-900 p-1 text-center font-mono font-black text-slate-900 bg-slate-100">
                                        @if(!$canViewSectionHead)
                                            <span class="text-slate-400 font-normal">-</span>
                                        @else
                                            <span x-text="totalsSectionHead['{{ $groupKey }}']?.weighted ?? '0.00'">0.00</span>
                                        @endif
                                    </td>
                                    <td class="border-r border-slate-900 p-1 text-center font-mono font-black text-slate-900 bg-slate-100">
                                        @if(!$canViewDeptHead)
                                            <span class="text-slate-400 font-normal">-</span>
                                        @else
                                            <span x-text="totalsDeptHead['{{ $groupKey }}']?.weighted ?? '0.00'">0.00</span>
                                        @endif
                                    </td>
                                    <td class="p-1 text-center font-mono font-black text-slate-900 bg-slate-100">
                                        @if(!$canViewDeptHead)
                                            <span class="text-slate-400 font-normal">-</span>
                                        @else
                                            <span x-text="totalsDivHead['{{ $groupKey }}']?.weighted ?? '0.00'">0.00</span>
                                        @endif
                                    </td>
                                    @elseif($subFormVariant === 'div_head')
                                    <td class="border-r border-slate-900 p-1 bg-[#bfbfbf]"></td>
                                    <td class="border-r border-slate-900 p-1 bg-[#bfbfbf]"></td>
                                    <td class="p-1 text-center font-mono font-black text-slate-900 bg-slate-100">
                                        @if(!$canViewDeptHead)
                                            <span class="text-slate-400 font-normal">-</span>
                                        @else
                                            <span x-text="totalsDeptHead['{{ $groupKey }}']?.weighted ?? '0.00'">0.00</span>
                                        @endif
                                    </td>
                                    @elseif($subFormVariant === 'dept_head')
                                    <td class="border-r border-slate-900 p-1 text-center font-mono font-black text-slate-900 bg-slate-100">
                                        @if(!$canViewForeman)
                                            <span class="text-slate-400 font-normal">-</span>
                                        @else
                                            <span x-text="totalsForeman['{{ $groupKey }}']?.weighted ?? '0.00'">0.00</span>
                                        @endif
                                    </td>
                                    <td class="border-r border-slate-900 p-1 bg-[#bfbfbf]"></td>
                                    <td class="p-1 text-center font-mono font-black text-slate-900 bg-slate-100">
                                        @if(!$canViewDeptHead)
                                            <span class="text-slate-400 font-normal">-</span>
                                        @else
                                            <span x-text="totalsDeptHead['{{ $groupKey }}']?.weighted ?? '0.00'">0.00</span>
                                        @endif
                                    </td>
                                    @else
                                    <td class="border-r border-slate-900 p-1 text-center font-mono font-black text-slate-900 bg-slate-100">
                                        @if(!$canViewForeman || ($isTanpaForemanStd && !$canEditForeman && !$showForemanData))
                                            <span class="text-slate-400 font-normal">-</span>
                                        @else
                                            <span x-text="totalsForeman['{{ $groupKey }}']?.weighted ?? '0.00'">0.00</span>
                                        @endif
                                    </td>
                                    <td class="border-r border-slate-900 p-1 text-center font-mono font-black text-slate-900 bg-slate-100">
                                        @if(!$canViewSectionHead)
                                            <span class="text-slate-400 font-normal">-</span>
                                        @else
                                            <span x-text="totalsSectionHead['{{ $groupKey }}']?.weighted ?? '0.00'">0.00</span>
                                        @endif
                                    </td>
                                    <td class="p-1 text-center font-mono font-black text-slate-900 bg-slate-100">
                                        @if(!$canViewDeptHead)
                                            <span class="text-slate-400 font-normal">-</span>
                                        @else
                                            <span x-text="totalsDeptHead['{{ $groupKey }}']?.weighted ?? '0.00'">0.00</span>
                                        @endif
                                    </td>
                                    @endif
                                </tr>

                            @endforeach
                        </tbody>
                    </table>

                    <!-- Bottom Notes & PENJUMLAHAN PENILAIAN Equation Box (Sesuai Gambar Excel) -->
                    <div class="p-3 bg-white border-t border-slate-900">
                        <div class="text-[10px] text-slate-500 mb-2 font-mono flex justify-between">
                            <span>{{ $isGol4Up ? 'Tanggal 日付 :' : 'Tanggal :' }} {{ date('d F Y') }}</span>
                            <span>Form: {{ $kodeForm }}</span>
                        </div>
                        
                        <table class="border-none border-collapse text-[9.5px] text-slate-600 mb-3 italic">
                            <tr>
                                <td class="border-none p-0 pr-2 font-bold whitespace-nowrap align-top text-slate-700">CATATAN :</td>
                                <td class="border-none p-0 pr-1.5 font-bold whitespace-nowrap align-top text-slate-700 w-4">1.</td>
                                <td class="border-none p-0 align-top">
                                    {{ $formSettings['catatan_1'] ?? 'Jika nilai pecahan, ditentukan dua angka dibelakang koma.' }}
                                    @if($isGol4Up)<div class="text-[8.5px] not-italic">注：1.端数が出た場合は、小数点以下第2位まで計算されます</div>@endif
                                </td>
                            </tr>
                            <tr>
                                <td class="border-none p-0"></td>
                                <td class="border-none p-0 pr-1.5 font-bold whitespace-nowrap align-top text-slate-700 w-4">2.</td>
                                <td class="border-none p-0 align-top">
                                    {{ $formSettings['catatan_2'] ?? 'Pembulatan : ≤ 0,5 pembulatan ke bawah ; > 0,5 pembulatan ke atas.' }}
                                    @if($isGol4Up)<div class="text-[8.5px] not-italic">2. 四捨五入の目安は0.5まで切り捨て、0.5を超えると切り上げます</div>@endif
                                </td>
                            </tr>
                        </table>

                        <!-- PENJUMLAHAN PENILAIAN White Equation Box (Sesuai Scan Resmi) -->
                        <div class="border border-slate-900 p-2.5 bg-white">
                            <div class="text-[9.5px] font-bold uppercase tracking-wider text-slate-900 mb-1.5">
                                PENJUMLAHAN PENILAIAN
                            </div>
                            
                            <div class="flex flex-nowrap items-center justify-between gap-1 text-[10px] text-slate-800">
                                
                                <!-- Hasil Kerja -->
                                <div class="flex-1 text-center min-w-0">
                                    <div class="text-[8.5px] font-semibold text-slate-700 leading-tight">
                                        <span>HASIL KERJA</span>
                                        @if($isGol4Up)<span class="block text-[7.5px] text-slate-500">仕事の結果</span>@endif
                                    </div>
                                    <div class="border border-slate-900 bg-white px-1.5 py-0.5 font-mono text-xs font-bold text-slate-950 mt-1">
                                        <span x-text="formatCleanNumber(getActiveWeighted('hasil_kerja'))">0</span>
                                    </div>
                                </div>

                                <div class="text-xs font-bold text-slate-900 px-0.5 self-center mt-3">+</div>

                                <!-- Proses Kerja -->
                                <div class="flex-1 text-center min-w-0">
                                    <div class="text-[8.5px] font-semibold text-slate-700 leading-tight">
                                        <span>PROSES KERJA</span>
                                        @if($isGol4Up)<span class="block text-[7.5px] text-slate-500">仕事の方法</span>@endif
                                    </div>
                                    <div class="border border-slate-900 bg-white px-1.5 py-0.5 font-mono text-xs font-bold text-slate-950 mt-1">
                                        <span x-text="formatCleanNumber(getActiveWeighted('proses_kerja'))">0</span>
                                    </div>
                                </div>

                                @if(isset($formStructure['groups']['man_management']))
                                <div class="text-xs font-bold text-slate-900 px-0.5 self-center mt-3">+</div>

                                <!-- People Man -->
                                <div class="flex-1 text-center min-w-0">
                                    <div class="text-[8.5px] font-semibold text-slate-700 leading-tight">
                                        <span>PEOPLE MAN</span>
                                        @if($isGol4Up)<span class="block text-[7.5px] text-slate-500">人材管理</span>@endif
                                    </div>
                                    <div class="border border-slate-900 bg-white px-1.5 py-0.5 font-mono text-xs font-bold text-slate-950 mt-1">
                                        <span x-text="formatCleanNumber(getActiveWeighted('man_management'))">0</span>
                                    </div>
                                </div>
                                @endif

                                <div class="text-xs font-bold text-slate-900 px-0.5 self-center mt-3">+</div>

                                <!-- Sikap Kerja -->
                                <div class="flex-1 text-center min-w-0">
                                    <div class="text-[8.5px] font-semibold text-slate-700 leading-tight">
                                        <span>SIKAP KERJA</span>
                                        @if($isGol4Up)<span class="block text-[7.5px] text-slate-500">仕事に関する性格</span>@endif
                                    </div>
                                    <div class="border border-slate-900 bg-white px-1.5 py-0.5 font-mono text-xs font-bold text-slate-950 mt-1">
                                        <span x-text="formatCleanNumber(getActiveWeighted('sikap_kerja'))">0</span>
                                    </div>
                                </div>

                                <div class="text-xs font-bold text-slate-900 px-0.5 self-center mt-3">=</div>

                                <!-- TOTAL -->
                                <div class="flex-1 text-center min-w-0">
                                    <div class="text-[8.5px] font-bold text-slate-900 leading-tight">
                                        <span>TOTAL</span>
                                        @if($isGol4Up)<span class="block text-[7.5px] text-slate-600">合計</span>@endif
                                    </div>
                                    <div class="border border-slate-900 bg-white px-2 py-0.5 font-mono text-xs font-bold text-slate-950 mt-1">
                                        <span x-text="formatCleanNumber(getActiveGrandTotal())">0</span>
                                    </div>
                                </div>

                            </div>
                        </div>

                    </div>

                </div>

                <!-- ============================================== -->
                <!-- RIGHT COLUMN: INDIKATOR EVALUASI POTENSI KARYAWAN (Col Span: 5) -->
                <!-- ============================================== -->
                <div class="col-span-12 lg:col-span-5 flex flex-col bg-white text-xs h-full">
                    
                    <!-- Top section: Header, Status Bar, Petunjuk, & 4 Options -->
                    <div class="flex-shrink-0">
                        <!-- Header Bar (Sesuai Gray Bar Excel) -->
                        <div class="bg-[#d1d5db] border-b border-slate-900 p-2 text-center font-bold uppercase tracking-wider text-slate-900 flex items-center justify-between gap-2">
                            <span class="flex-1 text-center font-bold">
                                {{ $isGol4Up ? (($formSettings['judul_evaluasi_potensi'] ?? 'INDIKATOR EVALUASI POTENSI KARYAWAN') . ' / 従業員の発展') : ($formSettings['judul_evaluasi_potensi'] ?? 'INDIKATOR EVALUASI POTENSI KARYAWAN') }}
                            </span>
                            @if($canEditIndikator)
                            <button type="button" 
                                    @click="manualSaveIndikator()" 
                                    :disabled="isSavingIndikator"
                                    title="Klik untuk menyimpan indikator potensi karyawan sekarang"
                                    class="no-print inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[10.5px] font-bold text-white bg-[#92302e] hover:bg-[#7e2524] shadow-sm transition-all flex-shrink-0 cursor-pointer">
                                <span x-show="!isSavingIndikator">💾 Simpan</span>
                                <span x-show="isSavingIndikator">⏳ Menyimpan...</span>
                            </button>
                            @endif
                        </div>

                        <!-- Real-time Auto-Save Status Bar -->
                        <div x-show="saveStatusMessage" x-cloak class="no-print px-3 py-1 bg-emerald-50 border-b border-emerald-200 text-[10.5px] text-emerald-800 flex items-center justify-between font-semibold transition-all">
                            <span class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                <span x-text="saveStatusMessage"></span>
                            </span>
                            <span class="text-[9.5px] text-emerald-600 font-normal">Tersimpan ke database</span>
                        </div>

                        <!-- Petunjuk Pengisian -->
                        <div class="p-3 border-b border-slate-300 bg-slate-50/50">
                            <span class="font-bold text-[11px] block text-slate-900 mb-1">{{ $isGol4Up ? 'Petunjuk Pengisian / 記入方法' : 'Petunjuk Pengisian.' }}</span>
                            <p class="text-[10px] text-slate-700 leading-relaxed text-justify">
                                {{ $formSettings['petunjuk_evaluasi_potensi'] ?? 'Setiap Atasan diharapkan mengisi dengan seksama kolom ini untuk mengetahui seberapa jauh potensi karyawan dapat dikembangkan ke posisi & tanggung jawab yang lebih besar sesuai dengan ketentuan yang berlaku dan selagi ada kesempatan dalam struktur organisasi.' }}
                            </p>
                            @if($isGol4Up)
                            <p class="text-[9.5px] text-slate-600 mt-1 leading-snug">
                                各上司が部下の能力を把握し今より高い職位ともっと重い責任を任せれるかどうかを記入する
                            </p>
                            @endif
                        </div>

                        <!-- 4 Options (Custom Square Checkboxes Sesuai Excel) -->
                        <div class="p-3 space-y-3.5">
                            
                            <!-- Option 1 -->
                            <label class="flex items-start gap-2 cursor-pointer group">
                                <input type="radio" 
                                       id="potensi_1" 
                                       name="pilihan_potensi" 
                                       value="1" 
                                       x-model="pilihanPotensi"
                                       @change="saveIndikatorToServer(false)"
                                       {{ ($indikator['pilihan_potensi'] ?? 1) == 1 ? 'checked' : '' }}
                                       {{ !$canEditIndikator ? 'disabled' : '' }}
                                       class="sr-only peer">
                                <span class="w-3.5 h-3.5 border border-slate-900 bg-white flex items-center justify-center font-bold text-[10px] text-slate-900 flex-shrink-0 mt-0.5 peer-checked:bg-slate-900 peer-checked:text-white transition-colors">
                                    <span x-show="pilihanPotensi == 1" class="font-black text-[11px]">✓</span>
                                </span>
                                <div class="flex-1">
                                    <span class="font-bold text-slate-900 text-[10.5px] block leading-tight">
                                        {{ $formSettings['opsi_potensi_1'] ?? 'Memenuhi standard untuk dipromosikan pada level diatasnya, saran pada posisi :' }}
                                    </span>
                                    @if($isGol4Up)
                                    <span class="text-[9.5px] text-slate-600 block leading-tight mt-0.5">
                                        能力が高く、今より上のレベルに上がれる。お勧めとなる職位は
                                    </span>
                                    @endif
                                    <input type="text" 
                                           name="catatan_potensi" 
                                           x-model="catatanPotensi1"
                                           @input="triggerAutoSaveIndikator()"
                                           placeholder="Tuliskan saran posisi promosi..." 
                                           {{ !$canEditIndikator ? 'readonly' : '' }}
                                           class="w-full mt-1 border-b border-slate-900 focus:border-emerald-600 outline-none text-[11px] bg-transparent py-0.5 font-medium">
                                </div>
                            </label>

                            <!-- Option 2 -->
                            <label class="flex items-start gap-2 cursor-pointer group">
                                <input type="radio" 
                                       id="potensi_2" 
                                       name="pilihan_potensi" 
                                       value="2" 
                                       x-model="pilihanPotensi"
                                       @change="saveIndikatorToServer(false)"
                                       {{ ($indikator['pilihan_potensi'] ?? 1) == 2 ? 'checked' : '' }}
                                       {{ !$canEditIndikator ? 'disabled' : '' }}
                                       class="sr-only peer">
                                <span class="w-3.5 h-3.5 border border-slate-900 bg-white flex items-center justify-center font-bold text-[10px] text-slate-900 flex-shrink-0 mt-0.5 peer-checked:bg-slate-900 peer-checked:text-white transition-colors">
                                    <span x-show="pilihanPotensi == 2" class="font-black text-[11px]">✓</span>
                                </span>
                                <div class="flex-1">
                                    <span class="font-bold text-slate-900 text-[10.5px] block leading-tight">
                                        {{ $formSettings['opsi_potensi_2'] ?? 'Sesuai dengan posisi saat ini dan masih bisa dikembangkan dengan pengalaman dan pelatihan sesuai matriks.' }}
                                    </span>
                                    @if($isGol4Up)
                                    <span class="text-[9.5px] text-slate-600 block leading-tight mt-0.5">
                                        今の能力よりもまだ発展することがなるためトレーニングと経験を高める為に今のままでいい
                                    </span>
                                    @endif
                                    <input type="text" 
                                           name="catatan_2" 
                                           x-model="catatanPotensi2"
                                           @input="triggerAutoSaveIndikator()"
                                           placeholder="Tuliskan catatan pelatihan / pengembangan..." 
                                           {{ !$canEditIndikator ? 'readonly' : '' }}
                                           class="w-full mt-1 border-b border-slate-900 focus:border-emerald-600 outline-none text-[11px] bg-transparent py-0.5 font-medium">
                                </div>
                            </label>

                            <!-- Option 3 -->
                            <label class="flex items-start gap-2 cursor-pointer group">
                                <input type="radio" 
                                       id="potensi_3" 
                                       name="pilihan_potensi" 
                                       value="3" 
                                       x-model="pilihanPotensi"
                                       @change="saveIndikatorToServer(false)"
                                       {{ ($indikator['pilihan_potensi'] ?? 1) == 3 ? 'checked' : '' }}
                                       {{ !$canEditIndikator ? 'disabled' : '' }}
                                       class="sr-only peer">
                                <span class="w-3.5 h-3.5 border border-slate-900 bg-white flex items-center justify-center font-bold text-[10px] text-slate-900 flex-shrink-0 mt-0.5 peer-checked:bg-slate-900 peer-checked:text-white transition-colors">
                                    <span x-show="pilihanPotensi == 3" class="font-black text-[11px]">✓</span>
                                </span>
                                <div class="flex-1">
                                    <span class="font-bold text-slate-900 text-[10.5px] block leading-tight">
                                        {{ $formSettings['opsi_potensi_3'] ?? 'Sesuai untuk posisi saat ini dan tidak bisa dikembangkan lagi, alasan :' }}
                                    </span>
                                    @if($isGol4Up)
                                    <span class="text-[9.5px] text-slate-600 block leading-tight mt-0.5">
                                        能力のキャパがマックスであり発揮する事は来ないので今のままで残す理由：
                                    </span>
                                    @endif
                                    <input type="text" 
                                           name="alasan_3" 
                                           x-model="catatanPotensi3"
                                           @input="triggerAutoSaveIndikator()"
                                           placeholder="Tuliskan alasan..." 
                                           {{ !$canEditIndikator ? 'readonly' : '' }}
                                           class="w-full mt-1 border-b border-slate-900 focus:border-emerald-600 outline-none text-[11px] bg-transparent py-0.5 font-medium">
                                </div>
                            </label>

                            <!-- Option 4 -->
                            <label class="flex items-start gap-2 cursor-pointer group">
                                <input type="radio" 
                                       id="potensi_4" 
                                       name="pilihan_potensi" 
                                       value="4" 
                                       x-model="pilihanPotensi"
                                       @change="saveIndikatorToServer(false)"
                                       {{ ($indikator['pilihan_potensi'] ?? 1) == 4 ? 'checked' : '' }}
                                       {{ !$canEditIndikator ? 'disabled' : '' }}
                                       class="sr-only peer">
                                <span class="w-3.5 h-3.5 border border-slate-900 bg-white flex items-center justify-center font-bold text-[10px] text-slate-900 flex-shrink-0 mt-0.5 peer-checked:bg-slate-900 peer-checked:text-white transition-colors">
                                    <span x-show="pilihanPotensi == 4" class="font-black text-[11px]">✓</span>
                                </span>
                                <div class="flex-1">
                                    <span class="font-bold text-slate-900 text-[10.5px] block leading-tight">
                                        {{ $formSettings['opsi_potensi_4'] ?? 'Yang bersangkutan kurang sesuai dengan posisi saat ini, dengan alasan :' }}
                                    </span>
                                    @if($isGol4Up)
                                    <span class="text-[9.5px] text-slate-600 block leading-tight mt-0.5">
                                        現在の職位に合わない。その理由：
                                    </span>
                                    @endif
                                    <input type="text" 
                                           name="alasan_4" 
                                           x-model="catatanPotensi4"
                                           @input="triggerAutoSaveIndikator()"
                                           placeholder="Tuliskan alasan..." 
                                           {{ !$canEditIndikator ? 'readonly' : '' }}
                                           class="w-full mt-1 border-b border-slate-900 focus:border-emerald-600 outline-none text-[11px] bg-transparent py-0.5 font-medium">
                                </div>
                            </label>

                        </div>
                    </div>

                    @php
                        $commentMeta = $commentMeta ?? [
                            'allKelebihan' => [],
                            'allKekurangan' => [],
                            'allCatatanPotensi' => [],
                            'priorKelebihan' => [],
                            'priorKekurangan' => [],
                            'priorCatatanPotensi' => [],
                            'currentKelebihan' => $indikator['kelebihan'] ?? '',
                            'currentKekurangan' => $indikator['kekurangan'] ?? '',
                            'currentCatatan' => $indikator['catatan_potensi'] ?? '',
                            'placeholderKelebihan' => '',
                            'placeholderKekurangan' => '',
                        ];
                    @endphp

                    <!-- KELEBIHAN KARYAWAN (Flex to fill half remaining height) -->
                    <div class="border-t border-slate-900 flex-1 flex flex-col min-h-[150px]">
                        <div class="bg-[#d1d5db] border-b border-slate-900 py-1 px-3 font-bold text-center uppercase tracking-wider text-slate-900 text-[10.5px] flex items-center justify-between flex-shrink-0">
                            <span class="flex-1 text-center font-bold">
                                {{ $isGol4Up ? (($formSettings['judul_kelebihan'] ?? 'KELEBIHAN KARYAWAN') . ' / 従業員のプラス所') : ($formSettings['judul_kelebihan'] ?? 'KELEBIHAN KARYAWAN') }}
                            </span>
                        </div>
                        <div class="p-2 flex-1 flex flex-col">
                            @if(!$canEditIndikator)
                                @if(!empty($commentMeta['allKelebihan']))
                                    <div class="space-y-1.5 flex-1 overflow-y-auto">
                                        @foreach($commentMeta['allKelebihan'] as $c)
                                            <div class="bg-slate-50 border border-slate-200 rounded p-1.5 text-[11px] text-slate-800 leading-snug">
                                                <div class="text-[9.5px] font-bold text-slate-600 uppercase flex items-center gap-1">
                                                    <span>🔒 Dikomentari oleh {{ $c['role_label'] }}</span>
                                                    @if(!empty($c['user_nama']))
                                                        <span class="text-slate-400 font-normal">({{ $c['user_nama'] }})</span>
                                                    @endif
                                                </div>
                                                <div class="italic text-slate-900 mt-0.5">"{{ $c['text'] }}"</div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="text-[11px] italic text-slate-400 p-1">- Belum ada catatan kelebihan -</div>
                                @endif
                            @else
                                {{-- Prior Comments (Locked, cannot be edited or deleted) --}}
                                @if(!empty($commentMeta['priorKelebihan']))
                                    <div class="mb-2 space-y-1">
                                        @foreach($commentMeta['priorKelebihan'] as $pKel)
                                            <div class="bg-amber-50/90 border border-amber-200/90 rounded px-2 py-1 text-[10.5px] text-amber-950 flex items-start gap-1.5 select-none" title="Komentar dari penilai sebelumnya terkunci dan tidak dapat diubah">
                                                <span class="text-amber-600 text-xs mt-0.5 flex-shrink-0">🔒</span>
                                                <div class="flex-1 leading-tight">
                                                    <span class="font-bold text-amber-900">Dikomentari oleh {{ $pKel['role_label'] }}@if(!empty($pKel['user_nama'])) ({{ $pKel['user_nama'] }})@endif:</span>
                                                    <span class="italic text-amber-950 ml-1">"{{ $pKel['text'] }}"</span>
                                                </div>
                                                <span class="text-[8.5px] uppercase px-1 py-0.5 rounded bg-amber-200/70 text-amber-800 font-bold tracking-wider flex-shrink-0">Terkunci</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                {{-- Textarea with Ghost Watermark ("samar-samar" di belakang textarea, hilang saat user mengetik) --}}
                                <div class="relative flex-1 flex flex-col min-h-[90px]">
                                    @if(!empty($commentMeta['priorKelebihan']))
                                        <div x-show="!kelebihan || kelebihan.trim() === ''"
                                             x-transition.opacity.duration.150ms
                                             class="absolute inset-0 pointer-events-none p-1 text-slate-400/50 italic text-[11px] leading-[24px] select-none z-0 overflow-hidden line-clamp-3">
                                            <span class="font-medium text-slate-400/70">Dikomentari oleh {{ $commentMeta['priorKelebihan'][count($commentMeta['priorKelebihan']) - 1]['role_label'] }}:</span>
                                            "{{ $commentMeta['priorKelebihan'][count($commentMeta['priorKelebihan']) - 1]['text'] }}"
                                        </div>
                                    @endif
                                    <textarea name="kelebihan" 
                                              x-model="kelebihan"
                                              @input="triggerAutoSaveIndikator()"
                                              placeholder="{{ empty($commentMeta['priorKelebihan']) ? 'Tuliskan kelebihan karyawan...' : '' }}" 
                                              style="background-image: repeating-linear-gradient(transparent, transparent 23px, #cbd5e1 24px); line-height: 24px;"
                                              class="relative z-10 w-full flex-1 border-none p-1 text-[11px] text-slate-900 outline-none bg-transparent resize-none leading-[24px] overflow-hidden"></textarea>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- KEKURANGAN KARYAWAN (Flex to fill other half, flush to bottom) -->
                    <div class="border-t border-slate-900 flex-1 flex flex-col min-h-[150px]">
                        <div class="bg-[#d1d5db] border-b border-slate-900 py-1 px-3 font-bold text-center uppercase tracking-wider text-slate-900 text-[10.5px] flex items-center justify-between flex-shrink-0">
                            <span class="flex-1 text-center font-bold">
                                {{ $isGol4Up ? (($formSettings['judul_kekurangan'] ?? 'KEKURANGAN KARYAWAN') . ' / 従業員のマイナス所') : ($formSettings['judul_kekurangan'] ?? 'KEKURANGAN KARYAWAN') }}
                            </span>
                        </div>
                        <div class="p-2 flex-1 flex flex-col">
                            @if(!$canEditIndikator)
                                @if(!empty($commentMeta['allKekurangan']))
                                    <div class="space-y-1.5 flex-1 overflow-y-auto">
                                        @foreach($commentMeta['allKekurangan'] as $c)
                                            <div class="bg-slate-50 border border-slate-200 rounded p-1.5 text-[11px] text-slate-800 leading-snug">
                                                <div class="text-[9.5px] font-bold text-slate-600 uppercase flex items-center gap-1">
                                                    <span>🔒 Dikomentari oleh {{ $c['role_label'] }}</span>
                                                    @if(!empty($c['user_nama']))
                                                        <span class="text-slate-400 font-normal">({{ $c['user_nama'] }})</span>
                                                    @endif
                                                </div>
                                                <div class="italic text-slate-900 mt-0.5">"{{ $c['text'] }}"</div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="text-[11px] italic text-slate-400 p-1">- Belum ada catatan kekurangan -</div>
                                @endif
                            @else
                                {{-- Prior Comments (Locked, cannot be edited or deleted) --}}
                                @if(!empty($commentMeta['priorKekurangan']))
                                    <div class="mb-2 space-y-1">
                                        @foreach($commentMeta['priorKekurangan'] as $pKek)
                                            <div class="bg-amber-50/90 border border-amber-200/90 rounded px-2 py-1 text-[10.5px] text-amber-950 flex items-start gap-1.5 select-none" title="Komentar dari penilai sebelumnya terkunci dan tidak dapat diubah">
                                                <span class="text-amber-600 text-xs mt-0.5 flex-shrink-0">🔒</span>
                                                <div class="flex-1 leading-tight">
                                                    <span class="font-bold text-amber-900">Dikomentari oleh {{ $pKek['role_label'] }}@if(!empty($pKek['user_nama'])) ({{ $pKek['user_nama'] }})@endif:</span>
                                                    <span class="italic text-amber-950 ml-1">"{{ $pKek['text'] }}"</span>
                                                </div>
                                                <span class="text-[8.5px] uppercase px-1 py-0.5 rounded bg-amber-200/70 text-amber-800 font-bold tracking-wider flex-shrink-0">Terkunci</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                {{-- Textarea with Ghost Watermark ("samar-samar" di belakang textarea, hilang saat user mengetik) --}}
                                <div class="relative flex-1 flex flex-col min-h-[90px]">
                                    @if(!empty($commentMeta['priorKekurangan']))
                                        <div x-show="!kekurangan || kekurangan.trim() === ''"
                                             x-transition.opacity.duration.150ms
                                             class="absolute inset-0 pointer-events-none p-1 text-slate-400/50 italic text-[11px] leading-[24px] select-none z-0 overflow-hidden line-clamp-3">
                                            <span class="font-medium text-slate-400/70">Dikomentari oleh {{ $commentMeta['priorKekurangan'][count($commentMeta['priorKekurangan']) - 1]['role_label'] }}:</span>
                                            "{{ $commentMeta['priorKekurangan'][count($commentMeta['priorKekurangan']) - 1]['text'] }}"
                                        </div>
                                    @endif
                                    <textarea name="kekurangan" 
                                              x-model="kekurangan"
                                              @input="triggerAutoSaveIndikator()"
                                              placeholder="{{ empty($commentMeta['priorKekurangan']) ? 'Tuliskan kekurangan / area pengembangan karyawan...' : '' }}" 
                                              style="background-image: repeating-linear-gradient(transparent, transparent 23px, #cbd5e1 24px); line-height: 24px;"
                                              class="relative z-10 w-full flex-1 border-none p-1 text-[11px] text-slate-900 outline-none bg-transparent resize-none leading-[24px] overflow-hidden"></textarea>
                                </div>
                            @endif
                        </div>
                    </div>

                </div>

            </div>

        </form>

    </div>

    <!-- ========================================================================= -->
    <!-- MODAL RUBRIK KRITERIA MANUAL ("KATA-KATA DULU BARU MASUK ANGKA FORM")     -->
    <!-- ========================================================================= -->
    <div x-show="showRubrikModal" 
         x-cloak 
         @click.self="showRubrikModal = false"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
        
        <div class="bg-white rounded-2xl max-w-6xl w-full border border-slate-200 shadow-2xl overflow-hidden flex flex-col max-h-[92vh] animate-in fade-in duration-200">
            
            <!-- Modal Header -->
            <div class="p-5 bg-gradient-to-r from-emerald-800 to-teal-900 text-white flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-200">Rubrik Panduan Penilaian Manual Musashi</span>
                    <h3 class="text-base font-bold text-white mt-0.5" x-text="activeAspectName"></h3>
                    <span class="text-xs font-mono text-emerald-300" x-text="activeAspectCode"></span>
                </div>
                <button type="button" @click="showRubrikModal = false" class="text-white/80 hover:text-white p-1 rounded-lg hover:bg-white/10 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Notice bar -->
            <div class="bg-emerald-50 px-5 py-2.5 border-b border-emerald-200 flex items-center justify-between gap-3 text-xs text-emerald-950">
                <div class="flex items-center gap-2">
                    <span class="text-base">💡</span>
                    <span><strong>Petunjuk Penilai:</strong> {{ $formSettings['catatan_petunjuk_penilai'] ?? 'Pilih salah satu dari 5 pilihan kalimat kriteria di bawah ini yang paling sesuai.' }} <strong>Klik kalimat atau tombol di bawahnya, maka skor angka otomatis masuk ke tabel formulir.</strong></span>
                </div>
            </div>

            <!-- Modal Body: 5 Columns side by side (Matching User's Manual Sheet) -->
            <div class="p-5 overflow-y-auto">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-3.5">
                    @for($s = 5; $s >= 1; $s--)
                    <div @click="selectRubrikScore({{ $s }})" 
                         class="p-4 rounded-xl border-2 transition-all cursor-pointer flex flex-col justify-between hover:shadow-lg relative group min-h-[220px]"
                         :class="isSelectedScore({{ $s }}) ? 'border-emerald-600 bg-emerald-50/80 ring-2 ring-emerald-300' : 'border-slate-200 hover:border-emerald-400 bg-white hover:bg-slate-50/70'">
                        
                        <div>
                            <!-- Card Header: Score Badge + Title -->
                            <div class="flex items-center justify-between gap-2 border-b border-slate-100 pb-2.5 mb-2.5">
                                <div class="flex items-center gap-2">
                                    <span class="w-8 h-8 rounded-lg flex items-center justify-center font-black text-sm shadow-sm font-mono text-white"
                                          :class="{{ $s }} >= 4 ? 'bg-emerald-600' : ({{ $s }} === 3 ? 'bg-blue-600' : 'bg-amber-600')">
                                        {{ $s }}
                                    </span>
                                    <div>
                                        <span class="text-xs font-black block tracking-tight text-slate-900">
                                            Skor {{ $s }}
                                        </span>
                                        <span class="text-[10px] text-slate-500 block font-medium">
                                            @if($s === 5) Istimewa
                                            @elseif($s === 4) Baik Sekali
                                            @elseif($s === 3) Baik / Standar
                                            @elseif($s === 2) Kurang
                                            @else Sangat Kurang
                                            @endif
                                        </span>
                                    </div>
                                </div>

                                <span x-show="isSelectedScore({{ $s }})" class="text-[10px] font-bold text-emerald-800 bg-emerald-200/90 px-2 py-0.5 rounded-full border border-emerald-400">
                                    ✓ Terpilih
                                </span>
                            </div>

                            <!-- Full Descriptive Text from Manual Sheet -->
                            <p class="text-xs text-slate-800 leading-relaxed text-left font-normal select-none" 
                               x-text="getKriteriaText(activeAspectCode, {{ $s }}) || 'Deskripsi kriteria manual untuk skor {{ $s }}'"></p>
                        </div>

                        <!-- Card Action Button -->
                        <div class="mt-4 pt-2.5 border-t border-slate-100 text-center">
                            <button type="button" 
                                    @click.stop="selectRubrikScore({{ $s }})"
                                    class="w-full py-2 px-2.5 rounded-lg text-xs font-bold transition-all shadow-xs flex items-center justify-center gap-1.5"
                                    :class="isSelectedScore({{ $s }}) ? 'bg-emerald-700 text-white shadow-emerald-200' : 'bg-slate-100 text-slate-700 group-hover:bg-emerald-600 group-hover:text-white'">
                                <span x-text="isSelectedScore({{ $s }}) ? '✓ Teks Terpilih (Skor {{ $s }} Masuk)' : '👉 Pilih Teks Ini (Skor {{ $s }})'"></span>
                            </button>
                        </div>

                    </div>
                    @endfor
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="p-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
                <button type="button" 
                        @click="clearRubrikScore()" 
                        class="px-3.5 py-2 rounded-xl text-xs font-semibold text-rose-600 hover:text-rose-800 hover:bg-rose-50 transition-colors">
                    🗑 Hapus / Kosongkan Nilai Aspek Ini
                </button>
                <button type="button" @click="showRubrikModal = false" class="px-5 py-2 rounded-xl text-xs font-bold text-slate-700 bg-slate-200 hover:bg-slate-300 transition-colors">
                    Tutup
                </button>
            </div>

        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL DIGITAL SIGNATURE & PIN CONFIRMATION                                -->
    <!-- ========================================================================= -->
    <div x-show="showSignatureModal" 
         x-cloak 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
        
        <div @click.away="showSignatureModal = false" 
             class="bg-white rounded-2xl max-w-lg w-full border border-slate-200 shadow-2xl overflow-hidden flex flex-col animate-in fade-in duration-200">
            
            <form id="signSubmitForm" method="POST" action="{{ route('penilaian.sign', $dokumen['id']) }}" enctype="multipart/form-data" @submit="beforeSignSubmit($event)">
                @csrf
                <input type="hidden" name="level" :value="signLevel">
                <input type="hidden" name="signature_base64" id="signature_base64">

                <!-- Hidden inputs for form aspects -->
                @foreach($formStructure['groups'] as $groupKey => $group)
                    @foreach($group['aspects'] as $aspectCode => $aspectName)
                        <input type="hidden" 
                               name="scores[{{ $aspectCode }}]" 
                               id="modal_score_{{ $aspectCode }}"
                               :value="(activeScores['{{ $aspectCode }}'] || (subFormVariant === 'div_head' ? scoresDeptHead['{{ $aspectCode }}'] : (subFormVariant === 'karyawan_gol4' ? (signLevel === 'section_head' ? scoresSectionHead['{{ $aspectCode }}'] : (signLevel === 'dept_head' ? scoresDeptHead['{{ $aspectCode }}'] : scoresDivHead['{{ $aspectCode }}'])) : (signLevel === 'foreman' ? scoresForeman['{{ $aspectCode }}'] : (signLevel === 'section_head' ? scoresSectionHead['{{ $aspectCode }}'] : scoresDeptHead['{{ $aspectCode }}']))))) || ''">
                    @endforeach
                @endforeach

                <!-- Hidden inputs for potential & remarks -->
                <input type="hidden" name="pilihan_potensi" id="modal_pilihan_potensi">
                <input type="hidden" name="catatan_potensi" id="modal_catatan_potensi">
                <input type="hidden" name="kelebihan" id="modal_kelebihan">
                <input type="hidden" name="kekurangan" id="modal_kekurangan">
                <input type="hidden" name="docking_final" :value="dockingGrade">

                <!-- Modal Header -->
                <div class="p-5 bg-gradient-to-r from-[#92302e] to-[#6f1f1d] text-white flex items-center justify-between">
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-red-200">Konfirmasi Tanda Tangan Digital</span>
                        <h3 class="text-base font-bold text-white mt-0.5" x-text="'TTD ' + signRoleTitle"></h3>
                    </div>
                    <button type="button" @click="showSignatureModal = false" class="text-white/80 hover:text-white p-1 rounded-lg hover:bg-white/10">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-5 space-y-4 text-xs">
                    
                    <!-- Flow Notice -->
                    <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-900 flex items-start gap-2.5">
                        <span class="text-base">ℹ️</span>
                        <div>
                            <span class="font-bold block text-[11.5px]">Alur Otomatis Penilaian:</span>
                            <span class="text-[11px] text-emerald-800" x-text="getFlowNoticeText()"></span>
                        </div>
                    </div>

                    <!-- Barcode Tanda Tangan Digital Section -->
                    <div class="space-y-3">
                        <div class="p-3.5 bg-slate-50 border border-slate-300 rounded-xl space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                                    <span class="text-emerald-600 font-black">✓</span> Barcode Tanda Tangan Anda Siap
                                </span>
                            </div>

                            <div class="bg-white p-4 border border-slate-300 rounded-lg text-center shadow-2xs">
                                <div class="p-2 bg-white border border-slate-200 shadow-xs inline-block rounded">
                                    <img src="{{ Auth::user()->barcode_ttd }}" 
                                         alt="Barcode TTD {{ Auth::user()->nama }}" 
                                         class="w-28 h-28 mx-auto object-contain">
                                </div>
                                <div class="mt-2.5 space-y-0.5 text-xs">
                                    <div class="font-bold text-slate-900">{{ Auth::user()->nama }}</div>
                                    <div class="font-mono text-slate-600 text-[11px]">NRP: {{ Auth::user()->nrp ?: '-' }} &bull; {{ Auth::user()->roleLabel() }}</div>
                                    <div class="text-[10px] text-emerald-700 font-semibold mt-1">
                                        ✓ QR Barcode Digital Resmi Musashi (Siap Dibubuhkan)
                                    </div>
                                </div>
                            </div>
                            <p class="text-[11px] text-slate-500 text-center">
                                Tanda tangan digital resmi terverifikasi yang siap dibubuhkan langsung pada dokumen penilaian ini.
                            </p>
                        </div>
                    </div>

                </div>

                <!-- Modal Footer -->
                <div class="p-4 bg-slate-50 border-t border-slate-200 flex items-center justify-end gap-2">
                    <button type="button" :disabled="isSigning" @click="showSignatureModal = false" class="min-btn px-4 py-2 rounded-xl text-xs font-bold text-slate-700 bg-slate-200 hover:bg-slate-300 disabled:opacity-50">
                        Batal
                    </button>
                    <button type="submit" :disabled="isSigning" class="min-btn px-5 py-2 rounded-xl text-xs font-bold text-white bg-[#92302e] hover:bg-[#7e2524] disabled:opacity-60 shadow-md shadow-red-200 flex items-center gap-1.5 cursor-pointer">
                        <span x-show="!isSigning">🔒</span>
                        <svg x-show="isSigning" class="animate-spin w-3.5 h-3.5 text-white inline-block" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <span x-text="isSigning ? 'Mengirim...' : 'Kirim'">Kirim</span>
                    </button>
                </div>

            </form>

        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL REVISI DOKUMEN                                                      -->
    <!-- ========================================================================= -->
    <div x-show="openRevisiModal" 
         x-cloak 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
        
        <div @click.away="openRevisiModal = false" 
             class="bg-white rounded-2xl max-w-md w-full border border-slate-200 shadow-2xl p-6 animate-in fade-in duration-200">
            
            <h3 class="text-base font-bold text-slate-900 mb-1">↩ Kembalikan Dokumen untuk Direvisi</h3>
            <p class="text-xs text-slate-500 mb-4">
                Dokumen akan dikembalikan ke penilai sebelumnya. Tanda tangan setelahnya akan dibatalkan, namun nilai tetap tersimpan.
            </p>

            <form method="POST" action="{{ route('penilaian.revisi', $dokumen['id']) }}" class="space-y-4">
                @csrf
                @if($isTanpaForemanStd)
                    <input type="hidden" name="target_level" value="section_head">
                @else
                    <div class="mb-3">
                        <label class="block text-xs font-bold text-slate-800 mb-1">Kembalikan ke:</label>
                        <select name="target_level" class="w-full text-xs rounded-xl border border-slate-300 p-2.5 focus:ring-2 focus:ring-rose-500 outline-none bg-white font-medium">
                            @if($subFormVariant === 'karyawan_gol4')
                                @if($user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head']))
                                    <option value="dept_head">Department Head (Penilai 2)</option>
                                    <option value="section_head">Section Head (Penilai 1)</option>
                                @elseif($user->isDepartmentHead() || in_array($user->role, ['dept_head', 'department_head']))
                                    <option value="section_head">Section Head (Penilai 1)</option>
                                @endif
                            @elseif($isGol4Up)
                                <option value="dept_head">Department Head (Penilai 1 / Atasan Langsung)</option>
                                <option value="section_head">Karyawan Ybs (Penilaian Mandiri)</option>
                            @else
                                @if($user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head']))
                                    <option value="dept_head">Department Head (Penilai 3)</option>
                                    <option value="section_head">Section Head (Penilai 2)</option>
                                    <option value="foreman">Foreman (Penilai 1)</option>
                                @elseif($user->isDepartmentHead() || in_array($user->role, ['dept_head', 'department_head']))
                                    <option value="section_head">Section Head (Penilai 2)</option>
                                    <option value="foreman">Foreman (Penilai 1)</option>
                                @else
                                    <option value="foreman">Foreman (Penilai 1)</option>
                                @endif
                            @endif
                        </select>
                    </div>
                @endif
                <div>
                    <label class="block text-xs font-bold text-slate-800 mb-1">Alasan Revisi (Wajib Diisi):</label>
                    <textarea name="catatan_revisi" rows="3" required placeholder="Tuliskan alasan pengembalian dokumen..." class="w-full text-xs rounded-xl border border-slate-300 p-2.5 focus:ring-2 focus:ring-rose-500 outline-none"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2">
                    <button type="button" @click="openRevisiModal = false" class="min-btn px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200">
                        Batal
                    </button>
                    <button type="submit" class="min-btn px-4 py-2 rounded-xl text-xs font-bold text-white bg-rose-600 hover:bg-rose-700">
                        Kembalikan Sekarang
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<!-- ========================================================================= -->
<!-- ALPINE.JS LOGIC & FORM INTERACTIVITY                                      -->
<!-- ========================================================================= -->
<script>
function penilaianApp() {
    return {
        // Modals state
        showRubrikModal: false,
        showSignatureModal: false,
        openRevisiModal: false,
        isSigning: false,
        hasSavedSignature: {{ $hasUserSignature ? 'true' : 'false' }},
        wantChangeSignature: false,
        signMode: 'upload',
        uploadedPreview: null,
        uploadedFileName: '',

        // Pop-up Toast Kuota Penuh (Muncul selama 5 detik jika kuota full)
        showQuotaToast: false,
        quotaToastProgress: 100,
        quotaToastTimer: null,
        quotaToastInterval: null,

        triggerQuotaToast() {
            if (!this.isReadyForQuota || !this.isCurrentGradeOverQuota) {
                this.showQuotaToast = false;
                return;
            }
            this.showQuotaToast = true;
            this.quotaToastProgress = 100;
            if (this.quotaToastInterval) clearInterval(this.quotaToastInterval);
            if (this.quotaToastTimer) clearTimeout(this.quotaToastTimer);

            const startTime = Date.now();
            const duration = 5000;
            this.quotaToastInterval = setInterval(() => {
                const elapsed = Date.now() - startTime;
                this.quotaToastProgress = Math.max(0, 100 - (elapsed / duration) * 100);
                if (elapsed >= duration) {
                    clearInterval(this.quotaToastInterval);
                    this.showQuotaToast = false;
                }
            }, 40);

            this.quotaToastTimer = setTimeout(() => {
                this.showQuotaToast = false;
                if (this.quotaToastInterval) clearInterval(this.quotaToastInterval);
            }, duration);
        },

        // Active aspect for Rubrik modal
        activeAspectCode: '',
        activeAspectName: '',
        activeAspectLevel: '{{ $activeLevel }}',

        // Signature modal info
        signLevel: 'foreman',
        signRoleTitle: 'Foreman',
        subFormVariant: '{{ $subFormVariant ?? '' }}',

        // Scores state per role (guaranteed Plain Objects)
        scoresForeman: @json((object)($scoresByLevel['foreman'] ?? [])),
        scoresSectionHead: @json((object)($scoresByLevel['section_head'] ?? [])),
        scoresDeptHead: @json((object)($scoresByLevel['dept_head'] ?? [])),
        scoresDivHead: @json((object)($scoresByLevel['div_head'] ?? [])),

        // Groups definitions for calculation
        groupsDef: @json($formStructure['groups']),

        // Master Criteria Map (Aspect Code => Score => Description)
        kriteriaMap: @json($kriteriaMap),

        // Selected descriptions for UI feedback
        kriteriaDescriptions: {},

        // Totals per group & Grand totals
        totalsForeman: {},
        totalsSectionHead: {},
        totalsDeptHead: {},
        totalsDivHead: {},
        grandTotalForeman: '{{ number_format($calcForeman['grand_total'] ?? 0, 2) }}',
        grandTotalSectionHead: '{{ number_format($calcSectionHead['grand_total'] ?? 0, 2) }}',
        grandTotalDeptHead: '{{ number_format($calcDeptHead['grand_total'] ?? 0, 2) }}',
        grandTotalDivHead: '{{ number_format($calcDivHead['grand_total'] ?? 0, 2) }}',

        // Target & Usage Quota for Division Head
        divTargetQuota: @json($divTargetQuota),
        divOtherUsage: @json($divOtherUsage),
        empKj: '{{ $empKj ?? ($karyawan['kj'] ?? 'KJ 2') }}',
        kjTargetQuota: @json($kjTargetQuota ?? []),
        kjOtherUsage: @json($kjOtherUsage ?? []),
        isDivHeadViewer: {{ $isDivHeadViewer ? 'true' : 'false' }},
        isQuotaExempt: {{ !empty($isQuotaExempt ?? $isGol4Up) ? 'true' : 'false' }},
        isWaitingForPriorLevel: {{ !empty($waitingForLevel) ? 'true' : 'false' }},
        docStatus: '{{ $dokumen['status'] ?? 'draft' }}',

        // Penyesuaian Penilaian / Docking Kuota (Division Head)
        dockingGrade: '{{ $dokumen['docking_final'] ?? ($dokumen['grade_original'] ?? '') }}',
        hasDockingChanged: {{ !empty($dokumen['docking_final']) && ($dokumen['docking_final'] !== ($dokumen['grade_original'] ?? '')) ? 'true' : 'false' }},
        calcScoreToGrade(score) {
            let s = parseFloat(score || 0);
            if (s <= 0) return 'K';
            if (s > 5) {
                if (s > 90) return 'S';
                if (s > 75) return 'A';
                if (s >= 60) return 'B';
                if (s >= 20) return 'C';
                return 'K';
            }
            if (s > 4.50) return 'S';
            if (s > 3.75) return 'A';
            if (s >= 3.00) return 'B';
            if (s >= 1.00) return 'C';
            return 'K';
        },
        getTotalUsage(grade) {
            let other = parseInt((this.divOtherUsage && this.divOtherUsage[grade]) || 0);
            return (this.dockingGrade === grade) ? (other + 1) : other;
        },
        getRemainingQuota(grade) {
            let target = parseInt((this.divTargetQuota && this.divTargetQuota[grade]) || 0);
            let total = this.getTotalUsage(grade);
            return target - total;
        },
        get isReadyForQuota() {
            if (this.isQuotaExempt || !this.isDivHeadViewer || this.isWaitingForPriorLevel) {
                return false;
            }
            let validStatuses = ['menunggu_dept_head', 'menunggu_div_head', 'selesai', 'revisi'];
            if (!validStatuses.includes(this.docStatus) && '{{ $dokumen['nrp'] ?? '' }}' !== '{{ $user->nrp ?? '' }}') {
                return false;
            }
            let grandTotal = parseFloat(this.getActiveGrandTotal() || 0);
            if (!this.dockingGrade && grandTotal <= 0) {
                return false;
            }
            return true;
        },
        get isCurrentGradeOverQuota() {
            if (!this.isReadyForQuota) return false;
            let grandTotal = parseFloat(this.getActiveGrandTotal() || 0);
            let currentDocking = this.dockingGrade || (grandTotal > 0 ? this.calcScoreToGrade(grandTotal) : null);
            if (!currentDocking) return false;

            let targetDiv = parseInt((this.divTargetQuota && this.divTargetQuota[currentDocking]) || 0);
            let usedDiv = this.getTotalUsage(currentDocking);
            if (usedDiv > targetDiv) return true;

            // Also check Jenjang KJ quota
            if (this.kjTargetQuota && this.kjTargetQuota[currentDocking] !== undefined) {
                let targetKj = parseInt(this.kjTargetQuota[currentDocking] || 0);
                let otherKj = parseInt((this.kjOtherUsage && this.kjOtherUsage[currentDocking]) || 0);
                let usedKj = otherKj + 1;
                if (usedKj > targetKj) return true;
            }

            // Also check actual calculated score grade
            if (grandTotal > 0) {
                let calcGrade = this.calcScoreToGrade(grandTotal);
                if (calcGrade !== currentDocking) {
                    let calcTarget = parseInt((this.divTargetQuota && this.divTargetQuota[calcGrade]) || 0);
                    let otherCalc = parseInt((this.divOtherUsage && this.divOtherUsage[calcGrade]) || 0);
                    if ((otherCalc + 1) > calcTarget) {
                        return true;
                    }
                }
            }

            return false;
        },
        get currentGradeExcess() {
            if (!this.isReadyForQuota) return 0;
            let grandTotal = parseFloat(this.getActiveGrandTotal() || 0);
            let currentDocking = this.dockingGrade || (grandTotal > 0 ? this.calcScoreToGrade(grandTotal) : null);
            if (!currentDocking) return 0;
            let target = parseInt((this.divTargetQuota && this.divTargetQuota[currentDocking]) || 0);
            let used = this.getTotalUsage(currentDocking);
            return Math.max(0, used - target);
        },
        selectDockingGrade(grade, syncForm = true) {
            this.dockingGrade = grade;
            this.hasDockingChanged = true;
            if (syncForm) {
                this.applyGradeUpperBoundToForm(grade);
            }
            if (this.isReadyForQuota && this.isCurrentGradeOverQuota) {
                this.triggerQuotaToast();
            } else {
                this.showQuotaToast = false;
            }
        },
        autoSesuaikanRankTersedia() {
            const ranks = ['S', 'A', 'B', 'C', 'K'];
            let curIdx = ranks.indexOf(this.dockingGrade);
            let targetGrade = null;
            // 1. Check if a lower rank is available in BOTH division and Jenjang KJ
            if (curIdx >= 0) {
                for (let i = curIdx + 1; i < ranks.length; i++) {
                    let r = ranks[i];
                    let targetDiv = parseInt((this.divTargetQuota && this.divTargetQuota[r]) || 0);
                    let otherDiv = parseInt((this.divOtherUsage && this.divOtherUsage[r]) || 0);
                    let targetKj = this.kjTargetQuota && this.kjTargetQuota[r] !== undefined ? parseInt(this.kjTargetQuota[r] || 0) : null;
                    let otherKj = parseInt((this.kjOtherUsage && this.kjOtherUsage[r]) || 0);

                    let divOk = (otherDiv + 1) <= targetDiv;
                    let kjOk = targetKj !== null ? ((otherKj + 1) <= targetKj) : true;
                    if (divOk && kjOk) {
                        targetGrade = r;
                        break;
                    }
                }
            }
            // 2. If no lower rank has quota, check all ranks that fit both
            if (!targetGrade) {
                for (let r of ['B', 'A', 'S', 'C', 'K']) {
                    let targetDiv = parseInt((this.divTargetQuota && this.divTargetQuota[r]) || 0);
                    let otherDiv = parseInt((this.divOtherUsage && this.divOtherUsage[r]) || 0);
                    let targetKj = this.kjTargetQuota && this.kjTargetQuota[r] !== undefined ? parseInt(this.kjTargetQuota[r] || 0) : null;
                    let otherKj = parseInt((this.kjOtherUsage && this.kjOtherUsage[r]) || 0);

                    let divOk = (otherDiv + 1) <= targetDiv;
                    let kjOk = targetKj !== null ? ((otherKj + 1) <= targetKj) : true;
                    if (divOk && kjOk) {
                        targetGrade = r;
                        break;
                    }
                }
            }
            // 3. Fallback to rank available in KJ
            if (!targetGrade && this.kjTargetQuota) {
                for (let r of ['A', 'B', 'C', 'S', 'K']) {
                    let targetKj = parseInt(this.kjTargetQuota[r] || 0);
                    let otherKj = parseInt((this.kjOtherUsage && this.kjOtherUsage[r]) || 0);
                    if ((otherKj + 1) <= targetKj) {
                        targetGrade = r;
                        break;
                    }
                }
            }
            if (!targetGrade) targetGrade = 'A';
            this.selectDockingGrade(targetGrade, true);
        },
        autoDowngradeToAvailable() {
            this.autoSesuaikanRankTersedia();
        },
        applyGradeUpperBoundToForm(targetRank) {
            let targetMax = 3.75;
            let baseVal = 3;
            if (targetRank === 'S') { targetMax = 5.00; baseVal = 5; }
            else if (targetRank === 'A') { targetMax = 4.50; baseVal = 4; }
            else if (targetRank === 'B') { targetMax = 3.75; baseVal = 3; }
            else if (targetRank === 'C') { targetMax = 2.95; baseVal = 2; }
            else if (targetRank === 'K') { targetMax = 0.95; baseVal = 1; }

            let targetObj = this.scoresDeptHead;
            if (!targetObj || Object.keys(targetObj).length === 0) {
                targetObj = this.scoresSectionHead || this.scoresForeman || {};
            }
            let isDivSelf = (this.subFormVariant === 'div_head');

            let aspectCodes = [];
            for (let gKey in this.groupsDef) {
                let g = this.groupsDef[gKey];
                for (let aCode in g.aspects) {
                    aspectCodes.push(aCode);
                    targetObj[aCode] = baseVal;
                    if (isDivSelf && this.scoresForeman) {
                        this.scoresForeman[aCode] = baseVal;
                    }
                }
            }

            if (baseVal < 5) {
                for (let aCode of aspectCodes) {
                    targetObj[aCode] = baseVal + 1;
                    if (isDivSelf && this.scoresForeman) {
                        this.scoresForeman[aCode] = baseVal + 1;
                    }
                    let tmpTotals = {};
                    let grand = parseFloat(this.calcLevel(targetObj, tmpTotals));
                    let grade = this.calcScoreToGrade(grand);
                    if (grand > targetMax || grade !== targetRank) {
                        targetObj[aCode] = baseVal;
                        if (isDivSelf && this.scoresForeman) {
                            this.scoresForeman[aCode] = baseVal;
                        }
                    }
                }
            }

            this.$nextTick(() => {
                for (let aCode of aspectCodes) {
                    let val = targetObj[aCode];
                    let modalInput = document.getElementById('modal_score_' + aCode);
                    if (modalInput) modalInput.value = val;
                    let formInputs = document.querySelectorAll(`input[name="scores[${aCode}]"]`);
                    formInputs.forEach(inp => { inp.value = val; });
                }
            });

            this.recalculate();
            this.saveScoresDraftAjax();
        },
        saveScoresDraftAjax() {
            try {
                let lvl = '{{ $activeLevel }}';
                if (!lvl || lvl === '') {
                    lvl = (this.subFormVariant === 'div_head') ? 'dept_head' : 'dept_head';
                }
                let curScores = (this.subFormVariant === 'div_head') ? this.scoresDeptHead : (this.scoresDeptHead || this.scoresForeman);
                const draftData = new FormData();
                draftData.append('_token', '{{ csrf_token() }}');
                draftData.append('level', lvl);
                draftData.append('docking_final', this.dockingGrade);
                for (let code in curScores) {
                    if (curScores[code]) {
                        draftData.append('scores[' + code + ']', curScores[code]);
                    }
                }
                draftData.append('pilihan_potensi', this.pilihanPotensi);
                draftData.append('catatan_potensi', this.getActiveCatatanPotensi());
                draftData.append('catatan_2', this.catatanPotensi2 || '');
                draftData.append('alasan_3', this.catatanPotensi3 || '');
                draftData.append('alasan_4', this.catatanPotensi4 || '');
                draftData.append('kelebihan', this.kelebihan || '');
                draftData.append('kekurangan', this.kekurangan || '');

                fetch('{{ route("penilaian.saveDraft", $dokumen["id"]) }}', {
                    method: 'POST',
                    body: draftData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                }).catch(e => console.log('Auto-save draft error:', e));
            } catch (e) {
                console.log('Auto-save draft exception:', e);
            }
        },

        // Indikator Evaluasi Potensi Karyawan state
        pilihanPotensi: {{ (int)($indikator['pilihan_potensi'] ?? 1) }},
        catatanPotensi1: @json(($indikator['pilihan_potensi'] ?? 1) == 1 ? ($commentMeta['currentCatatan'] ?? ($indikator['catatan_potensi'] ?? '')) : ''),
        catatanPotensi2: @json(($indikator['pilihan_potensi'] ?? 1) == 2 ? ($commentMeta['currentCatatan'] ?? ($indikator['catatan_potensi'] ?? '')) : ''),
        catatanPotensi3: @json(($indikator['pilihan_potensi'] ?? 1) == 3 ? ($commentMeta['currentCatatan'] ?? ($indikator['catatan_potensi'] ?? '')) : ''),
        catatanPotensi4: @json(($indikator['pilihan_potensi'] ?? 1) == 4 ? ($commentMeta['currentCatatan'] ?? ($indikator['catatan_potensi'] ?? '')) : ''),
        kelebihan: @json($commentMeta['currentKelebihan'] ?? ($indikator['kelebihan'] ?? '')),
        kekurangan: @json($commentMeta['currentKekurangan'] ?? ($indikator['kekurangan'] ?? '')),

        isSavingIndikator: false,
        saveStatusMessage: '',
        saveTimeout: null,

        getActiveCatatanPotensi() {
            if (this.pilihanPotensi == 1) return this.catatanPotensi1 || '';
            if (this.pilihanPotensi == 2) return this.catatanPotensi2 || '';
            if (this.pilihanPotensi == 3) return this.catatanPotensi3 || '';
            if (this.pilihanPotensi == 4) return this.catatanPotensi4 || '';
            return '';
        },

        triggerAutoSaveIndikator() {
            if (!{{ $canEditIndikator ? 'true' : 'false' }}) return;
            if (this.saveTimeout) clearTimeout(this.saveTimeout);
            this.saveStatusMessage = 'Menyimpan perubahan...';
            this.saveTimeout = setTimeout(() => {
                this.saveIndikatorToServer(false);
            }, 500);
        },

        manualSaveIndikator() {
            if (!{{ $canEditIndikator ? 'true' : 'false' }}) return;
            if (this.saveTimeout) clearTimeout(this.saveTimeout);
            this.saveIndikatorToServer(true);
        },

        async saveIndikatorToServer(showAlert = false) {
            if (!{{ $canEditIndikator ? 'true' : 'false' }}) return;
            this.isSavingIndikator = true;
            this.saveStatusMessage = 'Menyimpan ke database...';

            const activeCatatan = this.getActiveCatatanPotensi();

            const formData = new FormData();
            formData.append('_token', '{{ csrf_token() }}');
            formData.append('pilihan_potensi', this.pilihanPotensi);
            formData.append('catatan_potensi', activeCatatan);
            formData.append('catatan_2', this.catatanPotensi2 || '');
            formData.append('alasan_3', this.catatanPotensi3 || '');
            formData.append('alasan_4', this.catatanPotensi4 || '');
            formData.append('kelebihan', this.kelebihan || '');
            formData.append('kekurangan', this.kekurangan || '');

            try {
                const response = await fetch('{{ route("penilaian.simpanIndikator", $dokumen["id"]) }}', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const res = await response.json();
                this.isSavingIndikator = false;
                if (res.success) {
                    this.saveStatusMessage = '✓ Tersimpan (' + (res.saved_at || new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', second:'2-digit'})) + ')';
                    if (showAlert) {
                        alert('Indikator Evaluasi Potensi Karyawan berhasil disimpan!');
                    }
                    setTimeout(() => {
                        if (this.saveStatusMessage.startsWith('✓ Tersimpan')) {
                            this.saveStatusMessage = '';
                        }
                    }, 4000);
                } else {
                    this.saveStatusMessage = 'Gagal menyimpan: ' + (res.message || 'Error');
                }
            } catch (err) {
                this.isSavingIndikator = false;
                this.saveStatusMessage = 'Koneksi terputus saat menyimpan';
                console.error('Error auto-saving indicator:', err);
            }
        },

        activeScores: {},

        init() {
            // Ensure scores objects are clean JavaScript objects, not arrays
            if (Array.isArray(this.scoresForeman) || typeof this.scoresForeman !== 'object' || this.scoresForeman === null) {
                this.scoresForeman = {};
            }
            if (Array.isArray(this.scoresSectionHead) || typeof this.scoresSectionHead !== 'object' || this.scoresSectionHead === null) {
                this.scoresSectionHead = {};
            }
            if (Array.isArray(this.scoresDeptHead) || typeof this.scoresDeptHead !== 'object' || this.scoresDeptHead === null) {
                this.scoresDeptHead = {};
            }
            if (Array.isArray(this.scoresDivHead) || typeof this.scoresDivHead !== 'object' || this.scoresDivHead === null) {
                this.scoresDivHead = {};
            }

            // Preload descriptions for existing scores
            let initialScores = Object.assign({}, this.scoresDivHead, this.scoresDeptHead, this.scoresSectionHead, this.scoresForeman);
            for (let aCode in initialScores) {
                let score = initialScores[aCode];
                if (score && this.kriteriaMap[aCode] && this.kriteriaMap[aCode][score]) {
                    this.kriteriaDescriptions[aCode] = this.kriteriaMap[aCode][score];
                }
            }
            this.recalculate(true);
            this.$nextTick(() => {
                this.initCanvas();
                if (this.isReadyForQuota && this.isCurrentGradeOverQuota) {
                    this.triggerQuotaToast();
                }
            });
        },

        switchEmployee(docId) {
            if (docId) {
                const baseUrl = '{{ $roleFormUrlBase ?? url("/dokumen/".$rolePrefix."/penilaian") }}';
                window.location.href = baseUrl + '/' + docId;
            }
        },

        quickNrpInput: '',

        jumpToNrp() {
            let val = this.quickNrpInput.trim();
            if (!val) return;

            const docs = @json($accessibleDocs);
            const found = docs.find(d => 
                (d.nrp && d.nrp.toLowerCase() === val.toLowerCase()) ||
                (d.nrp && val.toLowerCase().startsWith(d.nrp.toLowerCase())) ||
                (d.nama_lengkap && d.nama_lengkap.toLowerCase().includes(val.toLowerCase()))
            );
            if (found && found.id) {
                this.switchEmployee(found.id);
                return;
            }

            let nrpMatch = val.match(/^([A-Za-z0-9\-_]+)/);
            let nrp = nrpMatch ? nrpMatch[1] : val;
            window.location.href = '/penilaian/nrp/' + encodeURIComponent(nrp);
        },

        setScore(level, aspectCode, score) {
            let numScore = (score === '' || score === null || score === undefined) ? null : parseInt(score);
            if (level === 'foreman') {
                let updated = Object.assign({}, this.scoresForeman);
                if (numScore) updated[aspectCode] = numScore;
                else delete updated[aspectCode];
                this.scoresForeman = updated;
            } else if (level === 'section_head') {
                let updated = Object.assign({}, this.scoresSectionHead);
                if (numScore) updated[aspectCode] = numScore;
                else delete updated[aspectCode];
                this.scoresSectionHead = updated;
            } else if (level === 'dept_head') {
                let updated = Object.assign({}, this.scoresDeptHead);
                if (numScore) updated[aspectCode] = numScore;
                else delete updated[aspectCode];
                this.scoresDeptHead = updated;
            } else if (level === 'div_head') {
                let updated = Object.assign({}, this.scoresDivHead);
                if (numScore) updated[aspectCode] = numScore;
                else delete updated[aspectCode];
                this.scoresDivHead = updated;
            }

            if (numScore && this.kriteriaMap[aspectCode] && this.kriteriaMap[aspectCode][numScore]) {
                this.kriteriaDescriptions = Object.assign({}, this.kriteriaDescriptions, {
                    [aspectCode]: this.kriteriaMap[aspectCode][numScore]
                });
            } else {
                let updatedDesc = Object.assign({}, this.kriteriaDescriptions);
                delete updatedDesc[aspectCode];
                this.kriteriaDescriptions = updatedDesc;
            }

            // Sync docking grade with new score grade whenever aspects are manually edited
            this.hasDockingChanged = false;

            this.recalculate();
        },

        copyForemanScores() {
            if (!this.scoresForeman || Object.keys(this.scoresForeman).length === 0) {
                alert('Belum ada nilai yang diinput oleh Foreman pada formulir ini.');
                return;
            }
            this.scoresSectionHead = Object.assign({}, this.scoresForeman);
            let updatedDesc = Object.assign({}, this.kriteriaDescriptions);
            for (let aCode in this.scoresSectionHead) {
                let score = this.scoresSectionHead[aCode];
                if (score && this.kriteriaMap[aCode] && this.kriteriaMap[aCode][score]) {
                    updatedDesc[aCode] = this.kriteriaMap[aCode][score];
                }
            }
            this.kriteriaDescriptions = updatedDesc;
            this.recalculate();
        },

        copySectionHeadScores() {
            if (!this.scoresSectionHead || Object.keys(this.scoresSectionHead).length === 0) {
                if (this.scoresForeman && Object.keys(this.scoresForeman).length > 0) {
                    this.scoresDeptHead = Object.assign({}, this.scoresForeman);
                } else {
                    alert('Belum ada nilai yang diinput oleh Section Head atau Foreman pada formulir ini.');
                    return;
                }
            } else {
                this.scoresDeptHead = Object.assign({}, this.scoresSectionHead);
            }
            let updatedDesc = Object.assign({}, this.kriteriaDescriptions);
            for (let aCode in this.scoresDeptHead) {
                let score = this.scoresDeptHead[aCode];
                if (score && this.kriteriaMap[aCode] && this.kriteriaMap[aCode][score]) {
                    updatedDesc[aCode] = this.kriteriaMap[aCode][score];
                }
            }
            this.kriteriaDescriptions = updatedDesc;
            this.recalculate();
        },

        copyDeptHeadScores() {
            if (!this.scoresDeptHead || Object.keys(this.scoresDeptHead).length === 0) {
                alert('Belum ada nilai yang diinput oleh Department Head pada formulir ini.');
                return;
            }
            this.scoresDivHead = Object.assign({}, this.scoresDeptHead);
            let updatedDesc = Object.assign({}, this.kriteriaDescriptions);
            for (let aCode in this.scoresDivHead) {
                let score = this.scoresDivHead[aCode];
                if (score && this.kriteriaMap[aCode] && this.kriteriaMap[aCode][score]) {
                    updatedDesc[aCode] = this.kriteriaMap[aCode][score];
                }
            }
            this.kriteriaDescriptions = updatedDesc;
            this.recalculate();
        },

        getActiveScore(aspectCode) {
            let lvl = this.activeAspectLevel || '{{ $activeLevel }}';
            if (lvl === 'foreman') return this.scoresForeman[aspectCode] || '';
            if (lvl === 'section_head') return this.scoresSectionHead[aspectCode] || '';
            if (lvl === 'dept_head') return this.scoresDeptHead[aspectCode] || '';
            if (lvl === 'div_head') return this.scoresDivHead[aspectCode] || '';
            return this.scoresDivHead[aspectCode] || this.scoresDeptHead[aspectCode] || this.scoresSectionHead[aspectCode] || this.scoresForeman[aspectCode] || '';
        },

        onCriteriaSelectChanged(aspectCode, score) {
            let lvl = this.activeAspectLevel || '{{ $activeLevel }}';
            if (!lvl) {
                @if($canEditForeman) lvl = 'foreman';
                @elseif($canEditSectionHead) lvl = 'section_head';
                @elseif($canEditDeptHead) lvl = 'dept_head';
                @elseif($canEditDivHead) lvl = 'div_head';
                @endif
            }
            this.setScore(lvl, aspectCode, score);
        },

        openRubrikModal(aspectCode, aspectName, level = null) {
            this.activeAspectCode = aspectCode;
            this.activeAspectName = aspectName;
            this.activeAspectLevel = level || '{{ $activeLevel }}';
            this.showRubrikModal = true;
        },

        getKriteriaText(aspectCode, score) {
            if (this.kriteriaMap[aspectCode] && this.kriteriaMap[aspectCode][score]) {
                return this.kriteriaMap[aspectCode][score];
            }
            return '';
        },

        isSelectedScore(score) {
            let activeLvl = this.activeAspectLevel || '{{ $activeLevel }}';
            let curScore = null;
            if (activeLvl === 'foreman') curScore = this.scoresForeman[this.activeAspectCode];
            else if (activeLvl === 'section_head') curScore = this.scoresSectionHead[this.activeAspectCode];
            else if (activeLvl === 'dept_head') curScore = this.scoresDeptHead[this.activeAspectCode];
            else if (activeLvl === 'div_head') curScore = this.scoresDivHead[this.activeAspectCode];
            return parseInt(curScore) === parseInt(score);
        },

        selectRubrikScore(score) {
            let activeLvl = this.activeAspectLevel || '{{ $activeLevel }}';
            let aCode = this.activeAspectCode;
            this.setScore(activeLvl, aCode, score);
            this.showRubrikModal = false;
        },

        onScoreChanged(aspectCode, score) {
            let activeLvl = this.activeAspectLevel || '{{ $activeLevel }}';
            this.setScore(activeLvl, aspectCode, score);
        },

        clearRubrikScore() {
            let activeLvl = this.activeAspectLevel || '{{ $activeLevel }}';
            let aCode = this.activeAspectCode;
            this.setScore(activeLvl, aCode, null);
            this.showRubrikModal = false;
        },

        getActiveWeighted(groupKey) {
            let lvl = '{{ $activeLevel }}';
            let val = null;
            if (this.subFormVariant === 'karyawan_gol4') {
                if (lvl === 'div_head' && {{ $canViewDeptHead ? 'true' : 'false' }}) val = this.totalsDivHead[groupKey]?.weighted;
                else if (lvl === 'dept_head' && {{ $canViewDeptHead ? 'true' : 'false' }}) val = this.totalsDeptHead[groupKey]?.weighted;
                else if (lvl === 'section_head' && {{ $canViewSectionHead ? 'true' : 'false' }}) val = this.totalsSectionHead[groupKey]?.weighted;

                if (val !== undefined && val !== null && val !== '' && val !== '0.00') {
                    return val;
                }
                if ({{ $canViewDeptHead ? 'true' : 'false' }} && parseFloat(this.totalsDivHead[groupKey]?.weighted || 0) > 0) return this.totalsDivHead[groupKey].weighted;
                if ({{ $canViewDeptHead ? 'true' : 'false' }} && parseFloat(this.totalsDeptHead[groupKey]?.weighted || 0) > 0) return this.totalsDeptHead[groupKey].weighted;
                if ({{ $canViewSectionHead ? 'true' : 'false' }} && parseFloat(this.totalsSectionHead[groupKey]?.weighted || 0) > 0) return this.totalsSectionHead[groupKey].weighted;
                return '0.00';
            }

            if (lvl === 'dept_head' && {{ $canViewDeptHead ? 'true' : 'false' }}) val = this.totalsDeptHead[groupKey]?.weighted;
            else if (lvl === 'section_head' && {{ $canViewSectionHead ? 'true' : 'false' }}) val = this.totalsSectionHead[groupKey]?.weighted;
            else if (lvl === 'foreman' && {{ $canViewForeman ? 'true' : 'false' }}) val = this.totalsForeman[groupKey]?.weighted;

            if (val !== undefined && val !== null && val !== '' && val !== '0.00') {
                return val;
            }

            // Fallback: highest level with value > 0 that this user is permitted to view
            if ({{ $canViewDeptHead ? 'true' : 'false' }} && parseFloat(this.totalsDeptHead[groupKey]?.weighted || 0) > 0) return this.totalsDeptHead[groupKey].weighted;
            if ({{ $canViewSectionHead ? 'true' : 'false' }} && parseFloat(this.totalsSectionHead[groupKey]?.weighted || 0) > 0) return this.totalsSectionHead[groupKey].weighted;
            if ({{ $canViewForeman ? 'true' : 'false' }} && parseFloat(this.totalsForeman[groupKey]?.weighted || 0) > 0) return this.totalsForeman[groupKey].weighted;
            return '0.00';
        },

        getActiveGrandTotal() {
            let lvl = '{{ $activeLevel }}';
            let grand = null;
            if (this.subFormVariant === 'karyawan_gol4') {
                if (lvl === 'div_head' && {{ $canViewDeptHead ? 'true' : 'false' }}) grand = this.grandTotalDivHead;
                else if (lvl === 'dept_head' && {{ $canViewDeptHead ? 'true' : 'false' }}) grand = this.grandTotalDeptHead;
                else if (lvl === 'section_head' && {{ $canViewSectionHead ? 'true' : 'false' }}) grand = this.grandTotalSectionHead;

                if (grand !== undefined && grand !== null && grand !== '' && grand !== '0.00') {
                    return grand;
                }
                if ({{ $canViewDeptHead ? 'true' : 'false' }} && parseFloat(this.grandTotalDivHead || 0) > 0) return this.grandTotalDivHead;
                if ({{ $canViewDeptHead ? 'true' : 'false' }} && parseFloat(this.grandTotalDeptHead || 0) > 0) return this.grandTotalDeptHead;
                if ({{ $canViewSectionHead ? 'true' : 'false' }} && parseFloat(this.grandTotalSectionHead || 0) > 0) return this.grandTotalSectionHead;
                return '0.00';
            }

            if (lvl === 'dept_head' && {{ $canViewDeptHead ? 'true' : 'false' }}) grand = this.grandTotalDeptHead;
            else if (lvl === 'section_head' && {{ $canViewSectionHead ? 'true' : 'false' }}) grand = this.grandTotalSectionHead;
            else if (lvl === 'foreman' && {{ $canViewForeman ? 'true' : 'false' }}) grand = this.grandTotalForeman;

            if (grand !== undefined && grand !== null && grand !== '' && grand !== '0.00') {
                return grand;
            }

            if ({{ $canViewDeptHead ? 'true' : 'false' }} && parseFloat(this.grandTotalDeptHead || 0) > 0) return this.grandTotalDeptHead;
            if ({{ $canViewSectionHead ? 'true' : 'false' }} && parseFloat(this.grandTotalSectionHead || 0) > 0) return this.grandTotalSectionHead;
            if ({{ $canViewForeman ? 'true' : 'false' }} && parseFloat(this.grandTotalForeman || 0) > 0) return this.grandTotalForeman;
            return '0.00';
        },

        formatCleanNumber(val) {
            if (val === undefined || val === null || val === '') return '0';
            let n = parseFloat(val);
            if (isNaN(n) || n === 0) return '0';
            return (n % 1 === 0) ? n.toFixed(0) : n.toFixed(2);
        },

        recalculate(isInitial = false) {
            this.grandTotalForeman = this.calcLevel(this.scoresForeman, this.totalsForeman);
            this.grandTotalSectionHead = this.calcLevel(this.scoresSectionHead, this.totalsSectionHead);
            this.grandTotalDeptHead = this.calcLevel(this.scoresDeptHead, this.totalsDeptHead);
            this.grandTotalDivHead = this.calcLevel(this.scoresDivHead, this.totalsDivHead);
            let grandTotal = parseFloat(this.getActiveGrandTotal() || 0);
            let calculatedGrade = grandTotal > 0 ? this.calcScoreToGrade(grandTotal) : '';
            if (!this.hasDockingChanged || !this.dockingGrade || this.dockingGrade === '') {
                if (calculatedGrade) {
                    this.dockingGrade = calculatedGrade;
                }
            }
            if (this.isReadyForQuota && this.isCurrentGradeOverQuota && !isInitial) {
                this.triggerQuotaToast();
            }
        },

        calcLevel(scores, outTotals) {
            let grand = 0;
            for (let gKey in this.groupsDef) {
                let g = this.groupsDef[gKey];
                let sum = 0;
                let count = 0;
                for (let aCode in g.aspects) {
                    let v = parseFloat(scores[aCode] || 0);
                    sum += v;
                    count++;
                }
                let avg = count > 0 ? (sum / count) : 0;
                let weighted = avg * (g.weight / 100);
                let rounded = Math.round(weighted * 100) / 100;
                outTotals[gKey] = {
                    sum: sum.toFixed(0),
                    avg: avg.toFixed(2),
                    weighted: rounded.toFixed(2)
                };
                grand += rounded;
            }
            return (Math.round(grand * 100) / 100).toFixed(2);
        },

        openSignModal(level, title) {
            if (this.isDivHeadViewer && this.isCurrentGradeOverQuota) {
                this.triggerQuotaToast();
                return;
            }

            let curScores = (this.subFormVariant === 'div_head' && level === 'foreman') 
                ? this.scoresDeptHead 
                : ((this.subFormVariant === 'karyawan_gol4')
                    ? (level === 'section_head' ? this.scoresSectionHead : (level === 'dept_head' ? this.scoresDeptHead : this.scoresDivHead))
                    : ((level === 'foreman') ? this.scoresForeman : 
                       ((level === 'section_head') ? this.scoresSectionHead : this.scoresDeptHead)));

            // Validate that all aspects have been filled with scores 1 to 5
            let missingAspects = [];
            for (let gKey in this.groupsDef) {
                let g = this.groupsDef[gKey];
                for (let aCode in g.aspects) {
                    let val = curScores[aCode];
                    if (!val || parseFloat(val) < 1 || parseFloat(val) > 5) {
                        missingAspects.push(g.aspects[aCode]);
                    }
                }
            }

            if (missingAspects.length > 0) {
                alert('Mohon lengkapi semua aspek penilaian (skor 1 s/d 5) sebelum menandatangani!\n\nAspek yang belum dinilai:\n- ' + missingAspects.slice(0, 5).join('\n- ') + (missingAspects.length > 5 ? '\n...dan ' + (missingAspects.length - 5) + ' aspek lainnya.' : ''));
                return;
            }

            this.signLevel = level;
            this.signRoleTitle = title;
            this.activeScores = Object.assign({}, curScores);

            // Copy indicator values into modal form
            document.getElementById('modal_pilihan_potensi').value = this.pilihanPotensi;
            document.getElementById('modal_catatan_potensi').value = this.getActiveCatatanPotensi();
            document.getElementById('modal_kelebihan').value = this.kelebihan || '';
            document.getElementById('modal_kekurangan').value = this.kekurangan || '';

            // Auto-save draft via AJAX di background saat tombol tanda tangan diklik
            // Memastikan seluruh nilai tersimpan aman di database bahkan sebelum modal ditutup atau password dimasukkan
            try {
                const draftData = new FormData();
                draftData.append('_token', '{{ csrf_token() }}');
                draftData.append('level', (this.subFormVariant === 'div_head' && level === 'foreman') ? 'dept_head' : level);
                for (let code in curScores) {
                    if (curScores[code]) {
                        draftData.append('scores[' + code + ']', curScores[code]);
                    }
                }
                draftData.append('pilihan_potensi', this.pilihanPotensi);
                draftData.append('catatan_potensi', this.getActiveCatatanPotensi());
                draftData.append('catatan_2', this.catatanPotensi2 || '');
                draftData.append('alasan_3', this.catatanPotensi3 || '');
                draftData.append('alasan_4', this.catatanPotensi4 || '');
                draftData.append('kelebihan', this.kelebihan || '');
                draftData.append('kekurangan', this.kekurangan || '');

                fetch('{{ route("penilaian.saveDraft", $dokumen["id"]) }}', {
                    method: 'POST',
                    body: draftData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                }).catch(e => console.log('Auto-save draft error:', e));
            } catch(e) {
                console.log('Auto-save draft exception:', e);
            }

            this.showSignatureModal = true;
            this.$nextTick(() => {
                this.wantChangeSignature = false;
                this.clearUploadedFile();
            });
        },

        getFlowNoticeText() {
            if (this.subFormVariant === 'karyawan_gol4') {
                if (this.signLevel === 'section_head') {
                    return 'Setelah Anda menandatangani formulir ini, status akan otomatis berpindah ke "Menunggu Dept Head" dan langsung masuk ke dashboard Department Head.';
                } else if (this.signLevel === 'dept_head') {
                    return 'Setelah Anda menandatangani formulir ini, status akan otomatis berpindah ke "Menunggu Div Head" dan langsung masuk ke dashboard Division Head.';
                } else {
                    return 'Setelah Anda menandatangani formulir ini, dokumen akan diselesaikan (Status: Selesai), nilai akhir dan Grade dihitung otomatis, serta diarsip ke Google Drive.';
                }
            } else if (this.subFormVariant === 'div_head') {
                return 'Setelah Anda menandatangani formulir evaluasi mandiri ini, dokumen akan diselesaikan (Status: Selesai) dan nilai akhir dihitung otomatis.';
            } else if (this.subFormVariant === 'dept_head' && this.signLevel === 'foreman') {
                return 'Setelah Anda menandatangani formulir evaluasi mandiri ini, status akan otomatis berpindah ke "Menunggu Div Head" dan langsung masuk ke dashboard Division Head.';
            } else if (this.subFormVariant === 'section_head' && this.signLevel === 'foreman') {
                return 'Setelah Anda menandatangani formulir evaluasi mandiri ini, status akan otomatis berpindah ke "Menunggu Dept Head" dan langsung masuk ke dashboard Department Head.';
            } else if (this.subFormVariant === 'section_head' && this.signLevel === 'section_head') {
                return 'Setelah Anda menandatangani formulir Section Head ini, status akan otomatis berpindah ke "Menunggu Div Head" untuk persetujuan akhir Division Head.';
            } else if (this.signLevel === 'foreman') {
                return 'Setelah Anda menandatangani formulir ini, status akan otomatis berpindah ke "Menunggu Section Head" dan langsung masuk ke dashboard Section Head.';
            } else if (this.signLevel === 'section_head') {
                return 'Setelah Anda menandatangani formulir ini, status akan otomatis berpindah ke "Menunggu Dept Head" dan langsung masuk ke dashboard Department Head.';
            } else {
                return 'Setelah Anda menandatangani formulir ini, dokumen akan diselesaikan (Status: Selesai), nilai akhir dan Grade dihitung otomatis, serta diarsip ke Google Drive.';
            }
        },

        // HTML5 Canvas Pad & Upload State
        canvas: null,
        ctx: null,
        isDrawing: false,

        initCanvas() {
            this.canvas = document.getElementById('signaturePad');
            if (!this.canvas) return;
            this.ctx = this.canvas.getContext('2d');
            this.ctx.strokeStyle = '#020617';
            this.ctx.lineWidth = 3;
            this.ctx.lineCap = 'round';
            this.ctx.lineJoin = 'round';

            if (this.canvasInitialized) return;

            const startDraw = (e) => {
                this.isDrawing = true;
                this.hasDrawn = true;
                const pos = this.getCanvasPos(e);
                this.ctx.beginPath();
                this.ctx.moveTo(pos.x, pos.y);
                this.ctx.lineTo(pos.x, pos.y);
                this.ctx.stroke();
            };

            const draw = (e) => {
                if (!this.isDrawing) return;
                this.hasDrawn = true;
                const pos = this.getCanvasPos(e);
                this.ctx.lineTo(pos.x, pos.y);
                this.ctx.stroke();
            };

            const stopDraw = () => {
                if (this.isDrawing && this.hasDrawn && this.canvas) {
                    this.uploadedPreview = this.canvas.toDataURL('image/png');
                    this.uploadedFileName = 'Coretan Tanda Tangan Layar';
                    const sigIn = document.getElementById('signature_base64');
                    if (sigIn) sigIn.value = this.uploadedPreview;
                }
                this.isDrawing = false;
            };

            this.canvas.addEventListener('mousedown', startDraw);
            this.canvas.addEventListener('mousemove', draw);
            window.addEventListener('mouseup', stopDraw);
            this.canvas.addEventListener('mouseleave', () => {
                if (this.isDrawing) stopDraw();
            });

            this.canvas.addEventListener('touchstart', (e) => {
                e.preventDefault();
                startDraw(e.touches[0]);
            }, { passive: false });

            this.canvas.addEventListener('touchmove', (e) => {
                e.preventDefault();
                draw(e.touches[0]);
            }, { passive: false });

            window.addEventListener('touchend', stopDraw);
            this.canvasInitialized = true;
        },

        getCanvasPos(e) {
            const rect = this.canvas.getBoundingClientRect();
            const scaleX = this.canvas.width / rect.width;
            const scaleY = this.canvas.height / rect.height;
            return {
                x: (e.clientX - rect.left) * scaleX,
                y: (e.clientY - rect.top) * scaleY
            };
        },

        clearSignaturePad() {
            if (this.canvas && this.ctx) {
                this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
                this.hasDrawn = false;
                if (this.uploadedFileName === 'Coretan Tanda Tangan Layar') {
                    this.uploadedPreview = null;
                    this.uploadedFileName = '';
                    const sigIn = document.getElementById('signature_base64');
                    if (sigIn) sigIn.value = '';
                }
            }
        },

        handleSignatureFileUpload(event) {
            const file = event.target.files ? event.target.files[0] : null;
            if (file) {
                if (file.size > 5 * 1024 * 1024) {
                    alert('Ukuran berkas tanda tangan maksimal 5MB!');
                    this.clearUploadedFile();
                    return;
                }
                this.uploadedFileName = file.name;
                const reader = new FileReader();
                reader.onload = (e) => {
                    this.uploadedPreview = e.target.result;
                };
                reader.readAsDataURL(file);
            } else {
                this.clearUploadedFile();
            }
        },

        clearUploadedFile() {
            this.uploadedPreview = null;
            this.uploadedFileName = '';
            const fileIn = document.getElementById('signature_file_input');
            if (fileIn) fileIn.value = '';
        },

        beforeSignSubmit(e) {
            if (this.isSigning) {
                e.preventDefault();
                return false;
            }

            if (this.isDivHeadViewer && this.isCurrentGradeOverQuota) {
                e.preventDefault();
                this.triggerQuotaToast();
                return false;
            }

            document.getElementById('modal_pilihan_potensi').value = this.pilihanPotensi;
            document.getElementById('modal_catatan_potensi').value = this.getActiveCatatanPotensi();
            document.getElementById('modal_kelebihan').value = this.kelebihan || '';
            document.getElementById('modal_kekurangan').value = this.kekurangan || '';

            // Explicitly sync score inputs in modal to guarantee all scores are submitted
            let curScores = (this.subFormVariant === 'div_head' && this.signLevel === 'foreman') 
                ? this.scoresDeptHead 
                : ((this.subFormVariant === 'karyawan_gol4')
                    ? (this.signLevel === 'section_head' ? this.scoresSectionHead : (this.signLevel === 'dept_head' ? this.scoresDeptHead : this.scoresDivHead))
                    : ((this.signLevel === 'foreman') ? this.scoresForeman : 
                       ((this.signLevel === 'section_head') ? this.scoresSectionHead : this.scoresDeptHead)));
            
            for (let aCode in curScores) {
                let el = document.getElementById('modal_score_' + aCode) || document.querySelector(`input[name="scores[${aCode}]"]`);
                if (el && curScores[aCode]) {
                    el.value = curScores[aCode];
                }
            }

            this.isSigning = true;
            return true;
        }
    }
}
</script>
@endsection
