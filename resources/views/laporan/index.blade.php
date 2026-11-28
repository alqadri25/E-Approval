@extends('layouts.app', ['title' => 'Laporan Penilaian'])

@section('content')
@php
    $evaluatedRows = array_values(array_filter($employeeRows, function ($r) {
        return !empty($r['has_score']);
    }));
    $exportCount = count($evaluatedRows);
    $pdfFormCounts = [];
    $rowsByDocId = [];
    foreach ($evaluatedRows as $rowItem) {
        $kode = trim((string)($rowItem['kode_form'] ?? ''));
        $label = $kode !== '' ? \App\Http\Controllers\PenilaianController::getFormName($kode, $rowItem) : 'Form Penilaian Karya';
        $pdfFormCounts[$label] = ($pdfFormCounts[$label] ?? 0) + 1;
        if (!empty($rowItem['doc_id'])) {
            $rowsByDocId[(string)$rowItem['doc_id']] = [
                'nrp' => (string)($rowItem['nrp'] ?? ''),
                'nama' => (string)($rowItem['nama'] ?? ''),
                'formLabel' => $label,
            ];
        }
    }
    $pdfFormBreakdown = [];
    foreach ($pdfFormCounts as $formLabel => $formTotal) {
        $pdfFormBreakdown[] = [
            'label' => $formLabel,
            'count' => $formTotal,
        ];
    }
    $exportConfig = [
        'count' => $exportCount,
        'pdfUrl' => route('laporan.exportPdf', request()->query()),
        'excelUrl' => route('laporan.exportExcel', request()->query()),
        'formBreakdown' => $pdfFormBreakdown,
        'rowsByDocId' => $rowsByDocId,
        'isSectionHeadWithoutForeman' => !empty($isSectionHeadWithoutForeman),
    ];
@endphp
<div class="space-y-6" x-data="laporanExportModal()" @keydown.escape.window="closeModal()">

    <!-- Header Section Card (Merah Musashi & Rounded-None) -->
    <div class="bg-gradient-to-r from-[#962226] via-[#92302e] to-[#781b1f] text-white rounded-none p-5 sm:p-6 border border-[#781b1f] shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight">
                Laporan
            </h1>
            <p class="text-xs text-red-100 font-medium mt-1">
                Rekap penilaian dan export data karyawan
            </p>
        </div>

        @php
            $hideSyncSpreadsheetBtn = $user->isForeman()
                || $user->isSectionHead()
                || $user->isDepartmentHead()
                || $user->isDivisionHead()
                || in_array(strtolower((string)$user->role), ['foreman', 'ketua', 'section_head', 'section', 'sechead', 'dept_head', 'department_head', 'div_head', 'division_head']);
        @endphp
        <!-- Action Export Buttons -->
        <div class="flex flex-wrap items-center gap-2">
            @if(!$hideSyncSpreadsheetBtn)
            <a href="{{ route('laporan.index', array_merge(request()->query(), ['sync' => 1])) }}"
               class="inline-flex items-center gap-2 px-3.5 py-2 rounded-none bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm transition border border-emerald-500 cursor-pointer"
               title="Tarik & Sinkronkan langsung data terbaru dari Google Spreadsheet">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                <span>Sinkron Spreadsheet</span>
            </a>
            @endif

            <button type="button"
                    @click="openConfirm('pdf')"
                    data-export-url="{{ route('laporan.exportPdf', request()->query()) }}"
                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-none bg-white/15 hover:bg-white/25 text-white border border-white/30 text-xs font-bold shadow-sm transition cursor-pointer"
                    title="Download 1 formulir PDF jika filter 1 orang, atau paket ZIP jika banyak karyawan">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                </svg>
                <span>Export PDF</span>
            </button>

            <button type="button"
                    @click="openConfirm('excel')"
                    data-export-url="{{ route('laporan.exportExcel', request()->query()) }}"
                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-none bg-white hover:bg-slate-100 text-[#92302e] text-xs font-bold shadow-sm transition border border-white cursor-pointer"
                    title="Download seluruh 18 kolom data Excel (.xlsx) sesuai filter aktif">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <span>Export Excel</span>
            </button>
        </div>
    </div>

    @php
        $isDirectSupervisor = $user->isForeman() || ($user->isSectionHead() && !$user->hasForemanSubordinates());
    @endphp

    @if($user->isForeman())
    <!-- 4 Workflow Progress Cards for Foreman -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <!-- Card 1: Total Karyawan -->
        <div class="bg-white rounded-none border border-slate-300 p-4 shadow-sm flex flex-col justify-between">
            <div>
                <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 block mb-1">Total Karyawan</span>
                <div class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight leading-none">
                    {{ $roleWorkflowCards['foreman']['total_karyawan'] ?? $totalKaryawan }}
                </div>
                @php
                    $fmMandiriLaporan = count(array_filter($employeeRows ?? [], fn($r) => strcasecmp($r['nrp'] ?? '', $user->nrp ?? '') === 0));
                    $fmAnggotaLaporan = max(0, ($roleWorkflowCards['foreman']['total_karyawan'] ?? $totalKaryawan) - $fmMandiriLaporan);
                @endphp
                <span class="text-[11px] text-slate-500 mt-1 block font-medium">
                    @if($fmMandiriLaporan > 0 && $fmAnggotaLaporan > 0)
                        {{ $fmMandiriLaporan }} Mandiri, {{ $fmAnggotaLaporan }} Anggota
                    @elseif($fmMandiriLaporan > 0)
                        {{ $fmMandiriLaporan }} Mandiri
                    @else
                        Anggota Foreman
                    @endif
                </span>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-200 flex items-center justify-between text-[11px]">
                <span class="font-bold text-emerald-700">Sudah: {{ $sudahDinilai }}</span>
                <span class="font-bold text-slate-500">Belum: {{ $belumDinilai }}</span>
            </div>
        </div>

        <!-- Card 2: Siap Disahkan (To-Do) -->
        <div class="rounded-none border {{ ($roleWorkflowCards['foreman']['siap_disahkan'] ?? 0) > 0 ? 'border-amber-400 bg-amber-50/40' : 'border-slate-300 bg-white' }} p-4 shadow-sm flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-1">
                    <span class="text-[11px] font-black uppercase tracking-wider {{ ($roleWorkflowCards['foreman']['siap_disahkan'] ?? 0) > 0 ? 'text-amber-800' : 'text-slate-500' }}">Siap Disahkan (To-Do)</span>
                    @if(($roleWorkflowCards['foreman']['siap_disahkan'] ?? 0) > 0)
                        <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                    @endif
                </div>
                <div class="text-2xl sm:text-3xl font-black {{ ($roleWorkflowCards['foreman']['siap_disahkan'] ?? 0) > 0 ? 'text-amber-900' : 'text-slate-900' }} tracking-tight leading-none">
                    {{ $roleWorkflowCards['foreman']['siap_disahkan'] ?? 0 }}
                </div>
                <span class="text-[11px] {{ ($roleWorkflowCards['foreman']['siap_disahkan'] ?? 0) > 0 ? 'text-amber-700 font-bold' : 'text-slate-500' }} mt-1 block">
                    {{ ($roleWorkflowCards['foreman']['siap_disahkan'] ?? 0) > 0 ? 'Perlu tindakan Anda' : 'Tidak ada antrean' }}
                </span>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-200 flex items-center justify-between text-[11px]">
                <span class="font-bold {{ ($roleWorkflowCards['foreman']['siap_disahkan'] ?? 0) > 0 ? 'text-amber-700' : 'text-slate-400' }}">
                    {{ ($roleWorkflowCards['foreman']['siap_disahkan'] ?? 0) > 0 ? 'Perlu tindakan Anda' : 'Selesai diinput' }}
                </span>
                @if(($roleWorkflowCards['foreman']['siap_disahkan'] ?? 0) > 0)
                    <a href="{{ route('penilaian.cari') }}" class="text-[#92302e] font-bold hover:underline inline-flex items-center gap-1">
                        Kerjakan &rarr;
                    </a>
                @endif
            </div>
        </div>

        <!-- Card 3: Diteruskan Ke Section Head -->
        @php
            $fmShSudah = $roleWorkflowCards['foreman']['sh_sudah'] ?? 0;
            $fmShBelum = $roleWorkflowCards['foreman']['sh_belum'] ?? 0;
            $fmShTotal = $roleWorkflowCards['foreman']['diteruskan_section'] ?? ($fmShSudah + $fmShBelum);
        @endphp
        <div class="bg-white rounded-none border border-slate-300 p-4 shadow-sm flex flex-col justify-between">
            <div>
                <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 block mb-1">Diteruskan Ke Section Head</span>
                <div class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight leading-none">
                    {{ $fmShTotal }}
                </div>
                <span class="text-[11px] text-slate-500 mt-1 block font-medium">
                    Telah diverifikasi Foreman
                </span>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-200 flex items-center justify-between text-[11px]">
                <span class="font-bold text-emerald-700">Sudah Dinilai: {{ $fmShSudah }}</span>
                <span class="font-bold {{ $fmShBelum > 0 ? 'text-amber-700' : 'text-slate-500' }}">Belum Dinilai: {{ $fmShBelum }}</span>
            </div>
        </div>

        <!-- Card 4: Diteruskan Ke Department Head -->
        @php
            $fmDhSudah = $roleWorkflowCards['foreman']['dh_sudah'] ?? 0;
            $fmDhBelum = $roleWorkflowCards['foreman']['dh_belum'] ?? 0;
            $fmDhTotal = $roleWorkflowCards['foreman']['diteruskan_dept'] ?? ($fmDhSudah + $fmDhBelum);
        @endphp
        <div class="bg-white rounded-none border border-slate-300 p-4 shadow-sm flex flex-col justify-between">
            <div>
                <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 block mb-1">Diteruskan Ke Department Head</span>
                <div class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight leading-none">
                    {{ $fmDhTotal }}
                </div>
                <span class="text-[11px] text-slate-500 mt-1 block font-medium">
                    Telah diverifikasi Section Head
                </span>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-200 flex items-center justify-between text-[11px]">
                <span class="font-bold text-emerald-700">Sudah Dinilai: {{ $fmDhSudah }}</span>
                <span class="font-bold {{ $fmDhBelum > 0 ? 'text-amber-700' : 'text-slate-500' }}">Belum Dinilai: {{ $fmDhBelum }}</span>
            </div>
        </div>
    </div>

    @elseif($user->isSectionHead())
    <!-- 5 Workflow Progress Cards for Section Head -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
        <!-- Card 1: Total Karyawan -->
        <div class="bg-white rounded-none border border-slate-300 p-4 shadow-sm flex flex-col justify-between">
            <div>
                <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 block mb-1">Total Karyawan</span>
                <div class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight leading-none">
                    {{ $roleWorkflowCards['section_head']['total_karyawan'] ?? $totalKaryawan }}
                </div>
                <span class="text-[11px] text-slate-500 mt-1 block font-medium">
                    Anggota Section Head
                </span>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-200 flex items-center justify-between text-[11px]">
                <span class="font-bold text-emerald-700">Sudah: {{ $sudahDinilai }}</span>
                <span class="font-bold text-slate-500">Belum: {{ $belumDinilai }}</span>
            </div>
        </div>

        {{-- Card 2: Proses Foreman (or TOTAL DINILAI if no Foreman) --}}
        @if(empty($hasForeman))
        <div class="bg-white rounded-none border border-slate-300 p-4 shadow-sm flex flex-col justify-between">
            <div>
                <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 block mb-1">TOTAL DINILAI</span>
                <div class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight leading-none">
                    {{ $roleWorkflowCards['section_head']['total_dinilai'] ?? $sudahDinilai }}
                </div>
                <span class="text-[11px] text-slate-500 mt-1 block font-medium">
                    Pengerjaan FORM Section Head
                </span>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-200 flex items-center justify-between text-[11px]">
                <span class="font-medium text-slate-500">Proses Section</span>
                <span class="font-mono text-slate-400 text-[10px]">Tahap Sect</span>
            </div>
        </div>
        @else
        <!-- Card 2: Proses Foreman -->
        @php
            $shFmSudah = $roleWorkflowCards['section_head']['fm_sudah'] ?? 0;
            $shFmBelum = $roleWorkflowCards['section_head']['fm_belum'] ?? ($roleWorkflowCards['section_head']['proses_foreman'] ?? 0);
        @endphp
        <div class="bg-white rounded-none border border-slate-300 p-4 shadow-sm flex flex-col justify-between">
            <div>
                <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 block mb-1">Proses Foreman</span>
                <div class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight leading-none">
                    {{ $roleWorkflowCards['section_head']['proses_foreman'] ?? 0 }}
                </div>
                <span class="text-[11px] text-slate-500 mt-1 block font-medium">
                    Pengerjaan Form Foreman
                </span>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-200 flex items-center justify-between text-[11px]">
                <span class="font-bold text-emerald-700">Sudah Dinilai: {{ $shFmSudah }}</span>
                <span class="font-bold {{ $shFmBelum > 0 ? 'text-amber-700' : 'text-slate-500' }}">Belum Dinilai: {{ $shFmBelum }}</span>
            </div>
        </div>
        @endif

        <!-- Card 3: Siap Disahkan (To-Do) -->
        <div class="rounded-none border {{ ($roleWorkflowCards['section_head']['siap_disahkan'] ?? 0) > 0 ? 'border-amber-400 bg-amber-50/40' : 'border-slate-300 bg-white' }} p-4 shadow-sm flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-1">
                    <span class="text-[11px] font-black uppercase tracking-wider {{ ($roleWorkflowCards['section_head']['siap_disahkan'] ?? 0) > 0 ? 'text-amber-800' : 'text-slate-500' }}">Siap Disahkan (To-Do)</span>
                    @if(($roleWorkflowCards['section_head']['siap_disahkan'] ?? 0) > 0)
                        <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                    @endif
                </div>
                <div class="text-2xl sm:text-3xl font-black {{ ($roleWorkflowCards['section_head']['siap_disahkan'] ?? 0) > 0 ? 'text-amber-900' : 'text-slate-900' }} tracking-tight leading-none">
                    {{ $roleWorkflowCards['section_head']['siap_disahkan'] ?? 0 }}
                </div>
                <span class="text-[11px] {{ ($roleWorkflowCards['section_head']['siap_disahkan'] ?? 0) > 0 ? 'text-amber-700 font-bold' : 'text-slate-500' }} mt-1 block">
                    {{ ($roleWorkflowCards['section_head']['siap_disahkan'] ?? 0) > 0 ? 'Perlu tindakan Anda' : 'Tidak ada antrean' }}
                </span>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-200 flex items-center justify-between text-[11px]">
                <span class="font-bold {{ ($roleWorkflowCards['section_head']['siap_disahkan'] ?? 0) > 0 ? 'text-amber-700' : 'text-slate-400' }}">
                    {{ ($roleWorkflowCards['section_head']['siap_disahkan'] ?? 0) > 0 ? 'Perlu tindakan Anda' : 'Selesai diinput' }}
                </span>
                @if(($roleWorkflowCards['section_head']['siap_disahkan'] ?? 0) > 0)
                    <a href="{{ route('penilaian.section') }}" class="text-[#92302e] font-bold hover:underline inline-flex items-center gap-1">
                        Kerjakan &rarr;
                    </a>
                @endif
            </div>
        </div>

        <!-- Card 4: Diteruskan Ke Dept Head -->
        @php
            $shDhSudah = $roleWorkflowCards['section_head']['dh_sudah'] ?? 0;
            $shDhBelum = $roleWorkflowCards['section_head']['dh_belum'] ?? 0;
            $shDhTotal = $roleWorkflowCards['section_head']['diteruskan_dept'] ?? ($shDhSudah + $shDhBelum);
        @endphp
        <div class="bg-white rounded-none border border-slate-300 p-4 shadow-sm flex flex-col justify-between">
            <div>
                <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 block mb-1">Diteruskan Ke Dept Head</span>
                <div class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight leading-none">
                    {{ $shDhTotal }}
                </div>
                <span class="text-[11px] text-slate-500 mt-1 block font-medium">
                    Telah diverifikasi Section Head
                </span>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-200 flex items-center justify-between text-[11px]">
                <span class="font-bold text-emerald-700">Sudah Dinilai: {{ $shDhSudah }}</span>
                <span class="font-bold {{ $shDhBelum > 0 ? 'text-amber-700' : 'text-slate-500' }}">Belum Dinilai: {{ $shDhBelum }}</span>
            </div>
        </div>

        <!-- Card 5: Diteruskan Ke Division Head -->
        @php
            $shDivSudah = $roleWorkflowCards['section_head']['div_sudah'] ?? 0;
            $shDivBelum = $roleWorkflowCards['section_head']['div_belum'] ?? 0;
            $shDivTotal = $roleWorkflowCards['section_head']['diteruskan_div'] ?? ($shDivSudah + $shDivBelum);
        @endphp
        <div class="bg-white rounded-none border border-slate-300 p-4 shadow-sm flex flex-col justify-between">
            <div>
                <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 block mb-1">Diteruskan Ke Division Head</span>
                <div class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight leading-none">
                    {{ $shDivTotal }}
                </div>
                <span class="text-[11px] text-slate-500 mt-1 block font-medium">
                    Telah diverifikasi Department Head
                </span>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-200 flex items-center justify-between text-[11px]">
                <span class="font-bold text-emerald-700">Sudah Dinilai: {{ $shDivSudah }}</span>
                <span class="font-bold {{ $shDivBelum > 0 ? 'text-amber-700' : 'text-slate-500' }}">Belum Dinilai: {{ $shDivBelum }}</span>
            </div>
        </div>
    </div>

    @elseif($user->isDepartmentHead() || in_array($user->role, ['dept_head', 'department_head']))
    <!-- Workflow Progress Cards for Department Head / Secretary -->
    <div class="grid grid-cols-1 {{ $user->isSecretary() ? 'sm:grid-cols-3' : 'sm:grid-cols-2 lg:grid-cols-5' }} gap-3">
        <!-- Card 1: Total Karyawan -->
        <div class="bg-white rounded-none border border-slate-300 p-4 shadow-sm flex flex-col justify-between">
            <div>
                <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 block mb-1">Total Karyawan</span>
                <div class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight leading-none">
                    {{ $roleWorkflowCards['dept_head']['total_karyawan'] ?? $totalKaryawan }}
                </div>
                <span class="text-[11px] text-slate-500 mt-1 block font-medium">
                    Anggota Department Head
                </span>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-200 flex items-center justify-between text-[11px]">
                <span class="font-bold text-emerald-700">Sudah: {{ $sudahDinilai }}</span>
                <span class="font-bold text-slate-500">Belum: {{ $belumDinilai }}</span>
            </div>
        </div>

        @if(!$user->isSecretary())
        <!-- Card 2: Proses Foreman -->
        <div class="bg-white rounded-none border border-slate-300 p-4 shadow-sm flex flex-col justify-between">
            <div>
                <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 block mb-1">Proses Foreman</span>
                <div class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight leading-none">
                    {{ $roleWorkflowCards['dept_head']['proses_foreman'] ?? 0 }}
                </div>
                <span class="text-[11px] text-slate-500 mt-1 block font-medium">
                    Pengerjaan Form Foreman
                </span>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-200 flex items-center justify-between text-[11px]">
                <span class="font-bold text-emerald-700">Sudah Dinilai: {{ $roleWorkflowCards['dept_head']['foreman_sudah'] ?? 0 }}</span>
                <span class="font-bold text-slate-500">Belum Dinilai: {{ $roleWorkflowCards['dept_head']['proses_foreman'] ?? 0 }}</span>
            </div>
        </div>

        <!-- Card 3: Proses Section Head -->
        <div class="bg-white rounded-none border border-slate-300 p-4 shadow-sm flex flex-col justify-between">
            <div>
                <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 block mb-1">Proses Section Head</span>
                <div class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight leading-none">
                    {{ $roleWorkflowCards['dept_head']['proses_section_only'] ?? 0 }}
                </div>
                <span class="text-[11px] text-slate-500 mt-1 block font-medium">
                    Pengerjaan Form Section Head
                </span>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-200 flex items-center justify-between text-[11px]">
                <span class="font-bold text-emerald-700">Sudah Dinilai: {{ $roleWorkflowCards['dept_head']['section_sudah'] ?? 0 }}</span>
                <span class="font-bold text-slate-500">Belum Dinilai: {{ $roleWorkflowCards['dept_head']['proses_section_only'] ?? 0 }}</span>
            </div>
        </div>
        @endif

        <!-- Card 4: Siap Disahkan (To-Do) -->
        <div class="rounded-none border {{ ($roleWorkflowCards['dept_head']['siap_disahkan'] ?? 0) > 0 ? 'border-amber-400 bg-amber-50/40' : 'border-slate-300 bg-white' }} p-4 shadow-sm flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-1">
                    <span class="text-[11px] font-black uppercase tracking-wider {{ ($roleWorkflowCards['dept_head']['siap_disahkan'] ?? 0) > 0 ? 'text-amber-800' : 'text-slate-500' }}">Siap Disahkan (To-Do)</span>
                    @if(($roleWorkflowCards['dept_head']['siap_disahkan'] ?? 0) > 0)
                        <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                    @endif
                </div>
                <div class="text-2xl sm:text-3xl font-black {{ ($roleWorkflowCards['dept_head']['siap_disahkan'] ?? 0) > 0 ? 'text-amber-900' : 'text-slate-900' }} tracking-tight leading-none">
                    {{ $roleWorkflowCards['dept_head']['siap_disahkan'] ?? 0 }}
                </div>
                <span class="text-[11px] {{ ($roleWorkflowCards['dept_head']['siap_disahkan'] ?? 0) > 0 ? 'text-amber-700 font-bold' : 'text-slate-500' }} mt-1 block">
                    {{ ($roleWorkflowCards['dept_head']['siap_disahkan'] ?? 0) > 0 ? 'Perlu tindakan Anda' : 'Tidak ada antrean' }}
                </span>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-200 flex items-center justify-between text-[11px]">
                <span class="font-bold {{ ($roleWorkflowCards['dept_head']['siap_disahkan'] ?? 0) > 0 ? 'text-amber-700' : 'text-slate-400' }}">
                    {{ ($roleWorkflowCards['dept_head']['siap_disahkan'] ?? 0) > 0 ? 'Perlu tindakan Anda' : 'Tidak ada antrean' }}
                </span>
                @if(($roleWorkflowCards['dept_head']['siap_disahkan'] ?? 0) > 0)
                    <a href="{{ route('penilaian.dept') }}" class="text-[#92302e] font-bold hover:underline inline-flex items-center gap-1">
                        Kerjakan &rarr;
                    </a>
                @endif
            </div>
        </div>

        <!-- Card 5: Diteruskan Ke Division Head -->
        <div class="bg-white rounded-none border border-slate-300 p-4 shadow-sm flex flex-col justify-between">
            <div>
                <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 block mb-1">Diteruskan Ke Division Head</span>
                <div class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight leading-none">
                    {{ $roleWorkflowCards['dept_head']['diteruskan_div'] ?? 0 }}
                </div>
                <span class="text-[11px] text-slate-500 mt-1 block font-medium">
                    Telah diverifikasi Department Head
                </span>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-200 flex items-center justify-between text-[11px]">
                <span class="font-bold text-emerald-700">Sudah Dinilai: {{ $roleWorkflowCards['dept_head']['selesai'] ?? 0 }}</span>
                <span class="font-bold text-amber-700">Belum Dinilai: {{ $roleWorkflowCards['dept_head']['menunggu_div'] ?? 0 }}</span>
            </div>
        </div>
    </div>

    @elseif($user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head']))
    <!-- 5 Workflow Progress Cards for Division Head -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
        <!-- Card 1: Total Karyawan -->
        <div class="bg-white rounded-none border border-slate-300 p-4 shadow-sm flex flex-col justify-between">
            <div>
                <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 block mb-1">Total Karyawan</span>
                <div class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight leading-none">
                    {{ $roleWorkflowCards['div_head']['total_karyawan'] ?? $totalKaryawan }}
                </div>
                <span class="text-[11px] text-slate-500 mt-1 block font-medium">
                    Anggota Division Head
                </span>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-200 flex items-center justify-between text-[11px]">
                <span class="font-bold text-emerald-700">Sudah: {{ $sudahDinilai }}</span>
                <span class="font-bold text-slate-500">Belum: {{ $belumDinilai }}</span>
            </div>
        </div>

        <!-- Card 2: Siap Disahkan (To-Do) -->
        <div class="rounded-none border {{ ($roleWorkflowCards['div_head']['siap_disahkan'] ?? 0) > 0 ? 'border-amber-400 bg-amber-50/40' : 'border-slate-300 bg-white' }} p-4 shadow-sm flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-1">
                    <span class="text-[11px] font-black uppercase tracking-wider {{ ($roleWorkflowCards['div_head']['siap_disahkan'] ?? 0) > 0 ? 'text-amber-800' : 'text-slate-500' }}">Siap Disahkan (To-Do)</span>
                    @if(($roleWorkflowCards['div_head']['siap_disahkan'] ?? 0) > 0)
                        <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                    @endif
                </div>
                <div class="text-2xl sm:text-3xl font-black {{ ($roleWorkflowCards['div_head']['siap_disahkan'] ?? 0) > 0 ? 'text-amber-900' : 'text-slate-900' }} tracking-tight leading-none">
                    {{ $roleWorkflowCards['div_head']['siap_disahkan'] ?? 0 }}
                </div>
                <span class="text-[11px] {{ ($roleWorkflowCards['div_head']['siap_disahkan'] ?? 0) > 0 ? 'text-amber-700 font-bold' : 'text-slate-500' }} mt-1 block">
                    {{ ($roleWorkflowCards['div_head']['siap_disahkan'] ?? 0) > 0 ? 'Perlu tindakan Anda' : 'Tidak ada antrean' }}
                </span>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-200 flex items-center justify-between text-[11px]">
                <span class="font-bold {{ ($roleWorkflowCards['div_head']['siap_disahkan'] ?? 0) > 0 ? 'text-amber-700' : 'text-slate-400' }}">
                    {{ ($roleWorkflowCards['div_head']['siap_disahkan'] ?? 0) > 0 ? 'Menunggu approval' : 'Tidak ada antrean' }}
                </span>
                @if(($roleWorkflowCards['div_head']['siap_disahkan'] ?? 0) > 0)
                    <a href="{{ route('penilaian.div') }}" class="text-[#92302e] font-bold hover:underline inline-flex items-center gap-1">
                        Kerjakan &rarr;
                    </a>
                @endif
            </div>
        </div>

        <!-- Card 3: Proses Section Head -->
        <div class="bg-white rounded-none border border-slate-300 p-4 shadow-sm flex flex-col justify-between">
            <div>
                <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 block mb-1">Proses Section Head</span>
                <div class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight leading-none">
                    {{ $roleWorkflowCards['div_head']['proses_section'] ?? 0 }}
                </div>
                <span class="text-[11px] text-slate-500 mt-1 block font-medium">
                    Pengerjaan Form Section Head
                </span>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-200 flex items-center justify-between text-[11px]">
                <span class="font-bold text-emerald-700">Sudah: {{ $roleWorkflowCards['div_head']['section_sudah'] ?? 0 }}</span>
                <span class="font-bold text-slate-500">Belum: {{ $roleWorkflowCards['div_head']['proses_section'] ?? 0 }}</span>
            </div>
        </div>

        <!-- Card 4: Proses Department Head -->
        <div class="bg-white rounded-none border border-slate-300 p-4 shadow-sm flex flex-col justify-between">
            <div>
                <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 block mb-1">Proses Department Head</span>
                <div class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight leading-none">
                    {{ $roleWorkflowCards['div_head']['proses_dept'] ?? 0 }}
                </div>
                <span class="text-[11px] text-slate-500 mt-1 block font-medium">
                    Sedang ditinjau Department Head
                </span>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-200 flex items-center justify-between text-[11px]">
                <span class="font-bold text-emerald-700">Sudah: {{ $roleWorkflowCards['div_head']['dept_sudah'] ?? 0 }}</span>
                <span class="font-bold text-slate-500">Belum: {{ $roleWorkflowCards['div_head']['proses_dept'] ?? 0 }}</span>
            </div>
        </div>

        <!-- Card 5: Disahkan Final (Selesai) -->
        <div class="bg-white rounded-none border border-slate-300 p-4 shadow-sm flex flex-col justify-between">
            <div>
                <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 block mb-1">Disahkan Final (Selesai)</span>
                <div class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight leading-none">
                    {{ $roleWorkflowCards['div_head']['disahkan_final'] ?? 0 }}
                </div>
                <span class="text-[11px] text-slate-500 mt-1 block font-medium">
                    Tuntas dan terarsip
                </span>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-200 flex items-center justify-between text-[11px]">
                <span class="font-bold text-emerald-700">Sudah: {{ $roleWorkflowCards['div_head']['disahkan_final'] ?? 0 }}</span>
                <span class="font-bold text-slate-500">Belum: {{ max(0, ($roleWorkflowCards['div_head']['total_karyawan'] ?? $totalKaryawan) - ($roleWorkflowCards['div_head']['disahkan_final'] ?? 0)) }}</span>
            </div>
        </div>
    </div>


    @else
    <!-- Hierarchy Cards (Total Karyawan, Total Foreman, Total Section Head, Total Dept Head, Total Div Head) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
        
        <!-- Card 1: Total Karyawan -->
        <div class="bg-white rounded-none border border-slate-300 p-4 shadow-sm flex flex-col justify-between">
            <div>
                <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 block mb-1">Total Karyawan</span>
                <div class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">
                    {{ $totalKaryawan }}
                </div>
                <span class="text-[11px] text-slate-500 mt-1 block font-medium">
                    {{ $totalKaryawanSubtitle ?? 'Karyawan dalam filter' }}
                </span>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-200 flex items-center justify-between text-[11px]">
                <span class="font-bold text-emerald-700">Sudah Dinilai: {{ $sudahDinilai }}</span>
                <span class="font-bold text-slate-500">Belum Dinilai: {{ $belumDinilai }}</span>
            </div>
        </div>

        <!-- Card 2: Total Foreman (Selalu ditampilkan, bernilai 0 jika tidak ada posisi/struktur foreman) -->
        <div class="bg-white rounded-none border border-slate-300 p-4 shadow-sm flex flex-col justify-between">
            <div>
                <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 block mb-1">Total Foreman</span>
                <div class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight flex items-baseline gap-1.5">
                    {{ $hierarchyCards['total_foreman'] }}
                    <span class="text-xs font-semibold text-slate-500">penilai</span>
                </div>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-200 text-[11px] font-bold text-[#92302e]">
                {{ $hierarchyCards['binaan_foreman'] }} karyawan binaan
            </div>
        </div>

        <!-- Card 3: Total Section Head -->
        <div class="bg-white rounded-none border border-slate-300 p-4 shadow-sm flex flex-col justify-between">
            <div>
                <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 block mb-1">Total Section Head</span>
                <div class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight flex items-baseline gap-1.5">
                    {{ $hierarchyCards['total_section_head'] }}
                    <span class="text-xs font-semibold text-slate-500">penilai</span>
                </div>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-200 text-[11px] font-bold text-blue-700">
                {{ $hierarchyCards['binaan_section_head'] }} karyawan binaan
            </div>
        </div>

        <!-- Card 4: Total Department Head -->
        <div class="bg-white rounded-none border border-slate-300 p-4 shadow-sm flex flex-col justify-between">
            <div>
                <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 block mb-1">Total Department Head</span>
                <div class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight flex items-baseline gap-1.5">
                    {{ $hierarchyCards['total_dept_head'] }}
                    <span class="text-xs font-semibold text-slate-500">penilai</span>
                </div>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-200 text-[11px] font-bold text-purple-700">
                {{ $hierarchyCards['binaan_dept_head'] }} karyawan binaan
            </div>
        </div>

        <!-- Card 5: Total Division Head -->
        <div class="bg-white rounded-none border border-slate-300 p-4 shadow-sm flex flex-col justify-between">
            <div>
                <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 block mb-1">Total Division Head</span>
                <div class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight flex items-baseline gap-1.5">
                    {{ $hierarchyCards['total_div_head'] }}
                    <span class="text-xs font-semibold text-slate-500">penilai</span>
                </div>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-200 text-[11px] font-bold text-emerald-800">
                {{ $hierarchyCards['binaan_div_head'] }} karyawan binaan
            </div>
        </div>

    </div>
    @endif

    @php
        $isForemanLaporan = $user->isForeman();
        $isDeptHeadLaporan = $user->isDepartmentHead() || in_array($user->role, ['dept_head', 'department_head']);
        $isDivHeadLaporan = $user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head']);
    @endphp
    @if(!$isDivHeadLaporan && !$user->isSecretary())
    {{-- Grafik Batang / Tabel Terpadu Target vs Actual Nilai S, A, B, C, K --}}
    @if($isForemanLaporan || $user->isSectionHead() || in_array($user->role, ['section_head', 'sechead']) || $isDeptHeadLaporan)
    @include('penilaian.partials.target_actual_matrix_table', [
        'evaluatorRoleLabel' => $isDeptHeadLaporan ? 'Department Head' : ($isForemanLaporan ? 'Foreman' : 'Section Head'),
        'includeDeptComparison' => false,
        'subtitleText' => $isDeptHeadLaporan
            ? 'Berdasarkan penilaian department head per KJ (Target vs Actual).'
            : ($isForemanLaporan
                ? 'Berdasarkan penilaian foreman per KJ (Target vs Actual).'
                : 'Berdasarkan penilaian section head per KJ (Target vs Actual).'),
        'matrixKjList' => $matrixKjList ?? [],
        'subCountByKj' => $subCountByKj ?? [],
        'targetKjMatrix' => $targetKjMatrix ?? [],
        'actualKjMatrix' => $actualKjMatrix ?? [],
        'deptActualKjMatrix' => $deptActualKjMatrix ?? [],
        'targetCounts' => $targetKuota ?? [],
        'actualCounts' => $quotaActualCounts ?? ($actualCounts ?? []),
        'deptActualCounts' => $deptQuotaActualCounts ?? [],
        'totalDinilai' => $quotaSudahDinilai ?? ($sudahDinilai ?? 0),
        'deptTotalDinilai' => $deptQuotaTotalDinilai ?? 0,
        'totalBelum' => $quotaBelumDinilai ?? ($belumDinilai ?? 0),
        'pctDinilai' => $quotaPctDinilai ?? ($pctDinilai ?? 0),
    ])
    @else
    <!-- ========================================================================= -->
    <!-- TABEL MONITORING: TARGET & REALISASI DIVISI & DEPARTEMEN                  -->
    <!-- (Memantau Semua Divisi & Departemen: Target vs Realisasi & Kuota S-K)     -->
    <!-- ========================================================================= -->
    <div class="bg-white rounded-none border border-slate-300 p-4 sm:p-5 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-slate-200 gap-2">
            <div>
                <h3 class="text-xs sm:text-sm font-black text-slate-900 tracking-wider flex items-center gap-2 flex-wrap">
                    <span class="w-2.5 h-2.5 bg-[#92302e]"></span>
                    <span>Tabel Target &amp; Pemantauan Divisi dan Departemen</span>
                </h3>
                <p class="text-[11px] text-slate-500 font-medium mt-0.5">
                    Monitoring realisasi target, sisa evaluasi, dan alokasi kuota nilai (S, A, B, C, K) per divisi &amp; departemen terhubung langsung ke Master Karyawan.
                </p>
            </div>
            <div class="flex items-center gap-3 text-xs font-bold">
                <span class="inline-flex items-center gap-1.5 text-slate-700">
                    <span class="w-3 h-3 bg-slate-800"></span> Target Kuota: {{ $totalTargetPerusahaan ?? array_sum($targetKuota) }}
                </span>
                <span class="inline-flex items-center gap-1.5 text-emerald-700 font-bold">
                    <span class="w-3 h-3 bg-emerald-600"></span> Sudah: {{ $totalDinilaiPerusahaan ?? ($quotaSudahDinilai ?? $sudahDinilai) }} ({{ $totalPctPerusahaan ?? ($quotaPctDinilai ?? $pctDinilai) }}%)
                </span>
                <span class="inline-flex items-center gap-1.5 text-amber-700 font-bold">
                    <span class="w-3 h-3 bg-amber-500"></span> Belum: {{ $totalBelumPerusahaan ?? ($quotaBelumDinilai ?? $belumDinilai) }}
                </span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse border border-slate-300">
                <thead>
                    <tr class="bg-slate-100 text-slate-700 font-bold text-[11px] uppercase tracking-wider">
                        <th rowspan="2" class="py-2.5 px-3 border border-slate-300 text-center w-10 whitespace-nowrap">No</th>
                        <th rowspan="2" class="py-2.5 px-3 border border-slate-300 whitespace-nowrap">Division</th>
                        <th rowspan="2" class="py-2.5 px-3 border border-slate-300 whitespace-nowrap">Department</th>
                        <th rowspan="2" class="py-2.5 px-3 border border-slate-300 text-center whitespace-nowrap">Target Kuota</th>
                        <th colspan="3" class="py-1.5 px-3 border border-slate-300 text-center bg-blue-50/70 text-blue-900 whitespace-nowrap">
                            Progres Penilaian Aktual
                        </th>
                        <th colspan="5" class="py-1.5 px-3 border border-slate-300 text-center bg-amber-50/70 text-amber-900 whitespace-nowrap">
                            Target Kuota Nilai (S, A, B, C, K)
                        </th>
                        <th rowspan="2" class="py-2.5 px-3 border border-slate-300 text-center whitespace-nowrap">Status</th>
                    </tr>
                    <tr class="bg-slate-50 text-slate-700 font-bold text-[10px] uppercase tracking-wider">
                        <th class="py-2 px-2.5 border border-slate-300 text-center text-emerald-800 whitespace-nowrap">Sudah</th>
                        <th class="py-2 px-2.5 border border-slate-300 text-center text-amber-800 whitespace-nowrap">Belum</th>
                        <th class="py-2 px-2.5 border border-slate-300 text-right whitespace-nowrap">Realisasi</th>
                        <th class="py-2 px-2.5 border border-slate-300 text-center whitespace-nowrap bg-emerald-50/40 text-emerald-800">
                            <span class="inline-block px-1.5 py-0.5 rounded-none bg-emerald-100 text-emerald-800 font-black">S</span>
                        </th>
                        <th class="py-2 px-2.5 border border-slate-300 text-center whitespace-nowrap bg-blue-50/40 text-blue-800">
                            <span class="inline-block px-1.5 py-0.5 rounded-none bg-blue-100 text-blue-800 font-black">A</span>
                        </th>
                        <th class="py-2 px-2.5 border border-slate-300 text-center whitespace-nowrap bg-amber-50/40 text-amber-800">
                            <span class="inline-block px-1.5 py-0.5 rounded-none bg-amber-100 text-amber-800 font-black">B</span>
                        </th>
                        <th class="py-2 px-2.5 border border-slate-300 text-center whitespace-nowrap bg-orange-50/40 text-orange-800">
                            <span class="inline-block px-1.5 py-0.5 rounded-none bg-orange-100 text-orange-800 font-black">C</span>
                        </th>
                        <th class="py-2 px-2.5 border border-slate-300 text-center whitespace-nowrap bg-red-50/40 text-red-800">
                            <span class="inline-block px-1.5 py-0.5 rounded-none bg-red-100 text-red-800 font-black">K</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($rekapDivisiDepartemen ?? [] as $index => $rd)
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="py-2.5 px-3 border border-slate-300 text-center text-slate-500 font-mono">{{ $index + 1 }}</td>
                        <td class="py-2.5 px-3 border border-slate-300 font-bold text-slate-700 whitespace-nowrap">{{ $rd['division'] }}</td>
                        <td class="py-2.5 px-3 border border-slate-300 font-bold text-slate-900 whitespace-nowrap">{{ $rd['departemen_label'] }}</td>
                        <td class="py-2.5 px-3 border border-slate-300 text-center font-bold text-slate-900 whitespace-nowrap">{{ $rd['total_karyawan'] }} org</td>
                        <td class="py-2.5 px-2.5 border border-slate-300 text-center font-bold text-emerald-700 whitespace-nowrap">{{ $rd['sudah_dinilai'] }} org</td>
                        <td class="py-2.5 px-2.5 border border-slate-300 text-center font-bold text-amber-700 whitespace-nowrap">{{ $rd['belum_dinilai'] }} org</td>
                        <td class="py-2.5 px-2.5 border border-slate-300 text-right font-black text-slate-900 whitespace-nowrap">{{ $rd['pct_val'] }}%</td>
                        <td class="py-2.5 px-2.5 border border-slate-300 text-center font-bold text-emerald-800 whitespace-nowrap">{{ $rd['target_s'] }}</td>
                        <td class="py-2.5 px-2.5 border border-slate-300 text-center font-bold text-blue-800 whitespace-nowrap">{{ $rd['target_a'] }}</td>
                        <td class="py-2.5 px-2.5 border border-slate-300 text-center font-bold text-amber-800 whitespace-nowrap">{{ $rd['target_b'] }}</td>
                        <td class="py-2.5 px-2.5 border border-slate-300 text-center font-bold text-orange-800 whitespace-nowrap">{{ $rd['target_c'] }}</td>
                        <td class="py-2.5 px-2.5 border border-slate-300 text-center font-bold text-red-800 whitespace-nowrap">{{ $rd['target_k'] }}</td>
                        <td class="py-2.5 px-3 border border-slate-300 text-center whitespace-nowrap">
                            <span class="inline-block px-2 py-0.5 text-[11px] font-bold rounded-none border {{ $rd['status']['bg'] ?? 'bg-slate-100 text-slate-700 border-slate-300' }}">
                                {{ $rd['status']['label'] ?? 'Sedang Berjalan' }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="13" class="py-6 px-3.5 border border-slate-300 text-center text-slate-400 font-medium">Belum ada data divisi / departemen terdaftar pada master karyawan.</td>
                    </tr>
                    @endforelse
                </tbody>
                @if(!empty($rekapDivisiDepartemen))
                <tfoot>
                    <tr class="bg-slate-100 font-bold text-slate-900 border-t-2 border-slate-300 text-xs">
                        <td colspan="3" class="py-2.5 px-3 border border-slate-300 uppercase tracking-wider text-[11px]">
                            Total Seluruh Divisi &amp; Departemen
                        </td>
                        <td class="py-2.5 px-3 border border-slate-300 text-center font-black">
                            {{ $totalTargetPerusahaan ?? count($employeeRows) }} org
                        </td>
                        <td class="py-2.5 px-2.5 border border-slate-300 text-center font-black text-emerald-700">
                            {{ $totalDinilaiPerusahaan ?? $sudahDinilai }} org
                        </td>
                        <td class="py-2.5 px-2.5 border border-slate-300 text-center font-black text-amber-700">
                            {{ $totalBelumPerusahaan ?? $belumDinilai }} org
                        </td>
                        <td class="py-2.5 px-2.5 border border-slate-300 text-right font-black text-slate-900">
                            {{ $totalPctPerusahaan ?? $pctDinilai }}%
                        </td>
                        <td class="py-2.5 px-2.5 border border-slate-300 text-center font-black text-emerald-800">
                            {{ $sumCompanyTargetS ?? ($targetKuota['S'] ?? 0) }}
                        </td>
                        <td class="py-2.5 px-2.5 border border-slate-300 text-center font-black text-blue-800">
                            {{ $sumCompanyTargetA ?? ($targetKuota['A'] ?? 0) }}
                        </td>
                        <td class="py-2.5 px-2.5 border border-slate-300 text-center font-black text-amber-800">
                            {{ $sumCompanyTargetB ?? ($targetKuota['B'] ?? 0) }}
                        </td>
                        <td class="py-2.5 px-2.5 border border-slate-300 text-center font-black text-orange-800">
                            {{ $sumCompanyTargetC ?? ($targetKuota['C'] ?? 0) }}
                        </td>
                        <td class="py-2.5 px-2.5 border border-slate-300 text-center font-black text-red-800">
                            {{ $sumCompanyTargetK ?? ($targetKuota['K'] ?? 0) }}
                        </td>
                        <td class="py-2.5 px-3 border border-slate-300 text-center font-bold text-slate-700">
                            {{ ($totalTargetPerusahaan ?? 0) > 0 ? (($totalDinilaiPerusahaan ?? $sudahDinilai) >= $totalTargetPerusahaan ? 'Tuntas' : 'Sedang Berjalan') : '-' }}
                        </td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
    @endif
    @endif

    @php
        $isDivHeadFilterBar = $user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head']);
        $isAdminOrSuperadmin = $user->isAdmin() || $user->isSuperadmin();
        $isForeman = $user->isForeman() || in_array($user->role, ['foreman', 'ketua']);
        $isSectionHead = $user->isSectionHead() || in_array($user->role, ['section_head', 'section', 'sechead']);
        $isDeptHeadLaporan = $user->isDepartmentHead() || in_array($user->role, ['dept_head', 'department_head']);
        $hideSemuaLaporan = $isForeman || $isSectionHead || $isDeptHeadLaporan;
        $canFilterDivisionAndDept = !$isForeman && !$isSectionHead && !$isDivHeadFilterBar && ($isAdminOrSuperadmin || $user->isHr() || (count($distinctDivisions ?? []) > 1) || (count($distinctDepts ?? []) > 1));
    @endphp
    <!-- Filter Bar Card (Terpadu, Desain Siku Tegas Rounded-None, Sejajar Satu Baris) -->
    <div class="bg-white rounded-none border border-slate-300 p-3 sm:p-4 shadow-sm overflow-x-auto">
        <form method="GET" action="{{ route('laporan.index') }}" class="flex flex-nowrap items-end gap-2.5 min-w-max">
            <span class="sr-only">Indikator Unit / Seksi</span>
            
            <!-- Filter Search NRP / Nama -->
            <div class="w-56 sm:w-64 shrink-0">
                <label class="block text-[10px] font-black text-slate-600 uppercase tracking-wider mb-1">Cari NRP / Nama</label>
                <div class="relative">
                    <input type="text"
                           name="q"
                           value="{{ $search }}"
                           onchange="this.form.submit();"
                           placeholder="Ketik NRP atau nama karyawan..."
                           class="w-full text-xs font-semibold text-slate-800 rounded-none border border-slate-300 hover:border-slate-400 bg-white py-2 pl-8 pr-3 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-xs">
                    <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
            </div>

            @if($isDivHeadFilterBar)
            <!-- Filter DEPARTMENT (Division Head: Department -> Section -> Grade) -->
            <div class="w-full sm:w-40 lg:w-44 shrink-0">
                <label class="block text-[10px] font-black text-slate-600 uppercase tracking-wider mb-1">Department</label>
                <select name="departemen"
                        onchange="this.form.submit();"
                        class="w-full text-xs font-semibold text-slate-700 rounded-none border border-slate-300 hover:border-slate-400 bg-white py-2 px-2.5 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-xs">
                    <option value="">{{ $hideSemuaLaporan ? 'Department' : 'Semua Department' }}</option>
                    @foreach($distinctDepts ?? [] as $dp)
                    <option value="{{ $dp }}" {{ strcasecmp($filterDept ?? '', $dp) === 0 ? 'selected' : '' }}>{{ $dp }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            @if($canFilterDivisionAndDept)
            <!-- Filter DIVISION -->
            <div class="w-full sm:w-40 lg:w-44 shrink-0">
                <label class="block text-[10px] font-black text-slate-600 uppercase tracking-wider mb-1">Division</label>
                <select name="division"
                        onchange="if(this.form.departemen) this.form.departemen.value=''; this.form.submit();"
                        class="w-full text-xs font-semibold text-slate-700 rounded-none border border-slate-300 hover:border-slate-400 bg-white py-2 px-2.5 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-xs">
                    <option value="">{{ $hideSemuaLaporan ? 'Division' : 'Semua Division' }}</option>
                    @foreach($distinctDivisions ?? [] as $divItem)
                    <option value="{{ $divItem }}" {{ strcasecmp($filterDivision ?? '', $divItem) === 0 ? 'selected' : '' }}>{{ $divItem }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Filter DEPARTMENT -->
            <div class="w-full sm:w-36 lg:w-40 shrink-0">
                <label class="block text-[10px] font-black text-slate-600 uppercase tracking-wider mb-1">Department</label>
                <select name="departemen"
                        onchange="this.form.submit();"
                        class="w-full text-xs font-semibold text-slate-700 rounded-none border border-slate-300 hover:border-slate-400 bg-white py-2 px-2.5 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-xs">
                    <option value="">{{ $hideSemuaLaporan ? 'Department' : 'Semua Department' }}</option>
                    @foreach($distinctDepts ?? [] as $dp)
                    <option value="{{ $dp }}" {{ strcasecmp($filterDept ?? '', $dp) === 0 ? 'selected' : '' }}>{{ $dp }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            @if($isForeman || $isSectionHead || !empty($isSectionHeadWithoutForeman))
            <!-- Filter BAGIAN -->
            <div class="w-full sm:w-44 lg:w-48 shrink-0">
                <label class="block text-[10px] font-black text-slate-600 uppercase tracking-wider mb-1">Bagian</label>
                <select name="bagian"
                        onchange="this.form.submit();"
                        class="w-full text-xs font-semibold text-slate-700 rounded-none border border-slate-300 hover:border-slate-400 bg-white py-2 px-2.5 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-xs">
                    <option value="">{{ $hideSemuaLaporan ? 'Bagian' : 'Semua Bagian' }}</option>
                    @foreach($distinctBagians as $b)
                    <option value="{{ $b }}" {{ strcasecmp(rtrim($filterBagian ?? '', '.'), rtrim($b, '.')) === 0 ? 'selected' : '' }}>{{ $b }}</option>
                    @endforeach
                </select>
            </div>
            @elseif(!$isForeman)
            <!-- Filter SECTION -->
            <div class="w-full sm:w-36 lg:w-40 shrink-0">
                <label class="block text-[10px] font-black text-slate-600 uppercase tracking-wider mb-1">Section</label>
                <select name="seksi"
                        onchange="this.form.submit();"
                        class="w-full text-xs font-semibold text-slate-700 rounded-none border border-slate-300 hover:border-slate-400 bg-white py-2 px-2.5 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-xs">
                    <option value="">{{ $hideSemuaLaporan ? 'Section' : 'Semua Section' }}</option>
                    @foreach($distinctSeksi as $s)
                    <option value="{{ $s }}" {{ (strcasecmp($filterSeksi ?? '', $s) === 0 || strcasecmp($filterBagian ?? '', $s) === 0) ? 'selected' : '' }}>{{ $s }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            @if(!$isDivHeadFilterBar)
            <!-- Filter POSITION -->
            <div class="w-full sm:w-36 lg:w-40 shrink-0">
                <label class="block text-[10px] font-black text-slate-600 uppercase tracking-wider mb-1">Position</label>
                <select name="jabatan"
                        onchange="this.form.submit();"
                        class="w-full text-xs font-semibold text-slate-700 rounded-none border border-slate-300 hover:border-slate-400 bg-white py-2 px-2.5 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-xs">
                    <option value="">{{ $hideSemuaLaporan ? 'Position' : 'Semua Position' }}</option>
                    @foreach($distinctJabatans as $jb)
                    <option value="{{ $jb }}" {{ strcasecmp($filterJabatan, $jb) === 0 ? 'selected' : '' }}>{{ $jb }}</option>
                    @endforeach
                </select>
            </div>

            @if(!$isAdminOrSuperadmin)
            <!-- Filter KJ / Golongan (Khusus Non-Admin / Penilai Unit) -->
            <div class="w-full sm:w-36 lg:w-40 shrink-0">
                <label class="block text-[10px] font-black text-slate-600 uppercase tracking-wider mb-1">KJ / Golongan</label>
                <select name="kj"
                        onchange="this.form.submit();"
                        class="w-full text-xs font-semibold text-slate-700 rounded-none border border-slate-300 hover:border-slate-400 bg-white py-2 px-2.5 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-xs">
                    <option value="">{{ $hideSemuaLaporan ? 'KJ / Golongan' : 'Semua KJ / Golongan' }}</option>
                    @foreach($distinctKjs as $kjVal)
                    <option value="{{ $kjVal }}" {{ strcasecmp($filterKj, $kjVal) === 0 ? 'selected' : '' }}>{{ $kjVal }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            @endif

            <!-- Filter Grade Nilai -->
            <div class="w-full sm:w-28 lg:w-32 shrink-0">
                <label class="block text-[10px] font-black text-slate-600 uppercase tracking-wider mb-1">Grade Nilai</label>
                <select name="grade"
                        onchange="this.form.submit();"
                        class="w-full text-xs font-semibold text-slate-700 rounded-none border border-slate-300 hover:border-slate-400 bg-white py-2 px-2.5 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-xs">
                    <option value="">{{ $hideSemuaLaporan ? 'Grade' : 'Semua Grade' }}</option>
                    <option value="S" {{ $filterGrade === 'S' ? 'selected' : '' }}>Grade S</option>
                    <option value="A" {{ $filterGrade === 'A' ? 'selected' : '' }}>Grade A</option>
                    <option value="B" {{ $filterGrade === 'B' ? 'selected' : '' }}>Grade B</option>
                    <option value="C" {{ $filterGrade === 'C' ? 'selected' : '' }}>Grade C</option>
                    <option value="K" {{ $filterGrade === 'K' ? 'selected' : '' }}>Grade K</option>
                </select>
            </div>

            <!-- Hidden submit for Enter key -->
            <button type="submit" class="hidden" tabindex="-1"></button>

            <!-- Tombol Aksi Reset -->
            <div class="shrink-0 flex items-center gap-2">
                @if($search || $filterJabatan || $filterKj || $filterGrade || (($isForeman || $isSectionHead) && ($filterBagian ?? '')) || (!$isForeman && !$isSectionHead && ($filterAtasan || ($filterDivision ?? '') || $filterDept || ($filterBagian ?? '') || $filterSeksi)))
                <a href="{{ route('laporan.index') }}"
                   class="px-3.5 py-2 rounded-none border border-rose-300 bg-rose-50 hover:bg-rose-100 text-[#92302e] text-xs font-bold transition whitespace-nowrap flex items-center gap-1 shadow-2xs">
                    <span>✕</span> Reset
                </a>
                @endif
            </div>

        </form>
    </div>

    <!-- Tabel Rincian Laporan Penilaian (Dinamis Kolom Nilai PK Sesuai Role Pengguna) -->
    @php
        $userRole = strtolower($user->role ?? '');
        $isSuperOrAdmin = $user->isAdmin() || $user->isSuperadmin();
        $isDivHeadLaporan = $user->isDivisionHead() || in_array($userRole, ['div_head', 'division_head']);
        $isDeptHeadLaporan = $user->isDepartmentHead() || in_array($userRole, ['dept_head', 'department_head']);
        $isSectionHeadLaporan = $user->isSectionHead() || in_array($userRole, ['section_head', 'section', 'sechead']);
        $isForemanLaporan = $user->isForeman() || in_array($userRole, ['foreman', 'ketua']);

        $showColForeman = !$user->isSecretary() && !$isDivHeadLaporan;
        $showColMandiri = !$isForemanLaporan;
        $showColSect = !$user->isSecretary() && ($isSuperOrAdmin || $isDivHeadLaporan || $isDeptHeadLaporan || $isSectionHeadLaporan);
        $showColDept = !$user->isSecretary() && ($isSuperOrAdmin || $isDivHeadLaporan || $isDeptHeadLaporan);
        $showColDiv = $isSuperOrAdmin || $isDivHeadLaporan;
        $showColFinal = !$isForemanLaporan && !$isSectionHeadLaporan && !$user->isSecretary();

        // 17 kolom identitas & bobot + 1 kolom aksi + jumlah kolom nilai PK yang aktif
        $totalTableCols = 18 + ($showColForeman ? 1 : 0) + ($showColMandiri ? 1 : 0) + ($showColSect ? 1 : 0) + ($showColDept ? 1 : 0) + ($showColDiv ? 1 : 0) + ($showColFinal ? 1 : 0);
    @endphp
    <div class="bg-white border border-slate-300 shadow-sm rounded-none overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-separate border-spacing-0 whitespace-nowrap">
                <thead>
                    <tr class="bg-slate-100 text-slate-700 font-bold text-[11px] uppercase tracking-wider sticky top-0 z-10 shadow-2xs">
                        <th class="py-3 px-2 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-12 min-w-[48px] max-w-[48px] whitespace-nowrap sticky left-0 z-30">NO</th>
                        <th class="py-3 px-2.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-28 min-w-[100px] max-w-[100px] whitespace-nowrap sticky left-[48px] z-30">NRP</th>
                        <th class="py-3 px-3.5 border-b border-r-2 border-r-slate-500 border-slate-300 bg-slate-100 font-bold text-slate-700 w-52 min-w-[200px] max-w-[200px] whitespace-nowrap sticky left-[148px] z-30 shadow-[4px_0_8px_-2px_rgba(0,0,0,0.15)]">NAMA LENGKAP</th>
                        <th class="py-3 px-3.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center whitespace-nowrap">CODE BAGIAN</th>
                        <th class="py-3 px-3.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 whitespace-nowrap">NAMA BAGIAN</th>
                        <th class="py-3 px-3.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center whitespace-nowrap">SECTION</th>
                        <th class="py-3 px-3.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 whitespace-nowrap">DEPARTMENT</th>
                        <th class="py-3 px-3.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center whitespace-nowrap">DIVISION</th>
                        <th class="py-3 px-3.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 whitespace-nowrap">POSITION</th>
                        <th class="py-3 px-3.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center whitespace-nowrap">START WORK</th>
                        <th class="py-3 px-3.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center whitespace-nowrap">KJ</th>
                        <th class="py-3 px-3.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center whitespace-nowrap">GOLONGAN</th>
                        <th class="py-3 px-3.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center whitespace-nowrap">Hasil Kerja</th>
                        <th class="py-3 px-3.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center whitespace-nowrap">Proses Kerja</th>
                        <th class="py-3 px-3.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center whitespace-nowrap">Sikap Kerja</th>
                        <th class="py-3 px-3.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center whitespace-nowrap">Man Management</th>
                        <th class="py-3 px-3.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center whitespace-nowrap">Total Nilai</th>
                        @if($showColForeman)
                        <th class="py-3 px-3.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center whitespace-nowrap">{{ $isForemanLaporan ? 'NILAI PK FOREMAN' : 'NILAI PK FOREMAN (JIKA ADA)' }}</th>
                        @endif
                        @if($showColMandiri)
                        <th class="py-3 px-3.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center whitespace-nowrap">{{ $user->isSecretary() ? 'NILAI MANDIRI' : 'NILAI PK MANDIRI' }}</th>
                        @endif
                        @if($showColSect)
                        <th class="py-3 px-3.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center whitespace-nowrap">{{ $isSectionHeadLaporan ? 'NILAI PK SECTION HEAD' : 'NILAI PK SECT' }}</th>
                        @endif
                        @if($showColDept)
                        <th class="py-3 px-3.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center whitespace-nowrap">NILAI PK DEPT</th>
                        @endif
                        @if($showColDiv)
                        <th class="py-3 px-3.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center whitespace-nowrap">NILAI PK DIV</th>
                        @endif
                        @if($showColFinal)
                        <th class="py-3 px-3.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center whitespace-nowrap">NILAI PK FINAL</th>
                        @endif
                        <th class="py-3 px-3 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-24 whitespace-nowrap">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($employeeRows as $index => $row)
                    <tr class="hover:bg-slate-50 transition-colors group">
                        
                        <!-- NO (Sticky Left, 100% Solid) -->
                        <td class="py-2.5 px-2 border-b border-r border-slate-300 text-center font-bold text-slate-800 bg-white group-hover:bg-slate-100 whitespace-nowrap w-12 min-w-[48px] max-w-[48px] sticky left-0 z-20">
                            {{ $index + 1 }}
                        </td>

                        <!-- NRP (Sticky Left, 100% Solid) -->
                        <td class="py-2.5 px-2.5 border-b border-r border-slate-300 text-center font-mono font-normal text-slate-800 bg-white group-hover:bg-slate-100 whitespace-nowrap w-28 min-w-[100px] max-w-[100px] sticky left-[48px] z-20">
                            {{ $row['nrp'] }}
                        </td>

                        <!-- NAMA (Sticky Left, 100% Solid, Divider Tebal) -->
                        <td class="py-2.5 px-3.5 border-b border-r-2 border-r-slate-500 border-slate-300 font-normal text-slate-800 bg-white group-hover:bg-slate-100 whitespace-nowrap w-52 min-w-[200px] max-w-[200px] sticky left-[148px] z-20 shadow-[4px_0_8px_-2px_rgba(0,0,0,0.15)]">
                            <div class="inline-flex items-center gap-1.5 whitespace-nowrap">
                                <span class="whitespace-nowrap">{{ $row['nama'] }}</span>
                                @php
                                    $isSelf = (strcasecmp($row['nrp'] ?? '', $user->nrp ?? '') === 0 
                                            || strcasecmp(trim($row['nama'] ?? ''), trim($user->nama ?? '')) === 0
                                            || (!empty($row['nama']) && !empty($user->nama) && (stripos($user->nama, $row['nama']) !== false || stripos($row['nama'], $user->nama) !== false)));
                                @endphp
                                @if($isSelf)
                                    <span class="px-1.5 py-0.5 text-[9.5px] font-extrabold bg-[#fbe8e8] text-[#92302e] border border-[#ce5d5b]/40 rounded-none inline-flex items-center shrink-0 whitespace-nowrap" title="Penilaian Mandiri (Self Appraisal) - Anda menilai diri sendiri">
                                        (Anda)
                                    </span>
                                @endif
                            </div>
                        </td>

                        <!-- CODE BAGIAN -->
                        <td class="py-2.5 px-3.5 border-b border-r border-slate-300 text-center font-mono text-slate-700 whitespace-nowrap">
                            {{ $row['code_bagian'] }}
                        </td>

                        <!-- NAMA BAGIAN -->
                        <td class="py-2.5 px-3.5 border-b border-r border-slate-300 font-medium text-slate-800 whitespace-nowrap">
                            {{ $row['nama_bagian'] }}
                        </td>

                        <!-- NAMA SECT. -->
                        <td class="py-2.5 px-3.5 border-b border-r border-slate-300 text-center whitespace-nowrap">
                            <span class="px-2 py-0.5 text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200 inline-block">
                                {{ $row['nama_sect'] }}
                            </span>
                        </td>

                        <!-- DEPARTMENT -->
                        <td class="py-2.5 px-3.5 border-b border-r border-slate-300 font-semibold text-slate-700 whitespace-nowrap">
                            {{ $row['departemen'] }}
                        </td>

                        <!-- DIVISION -->
                        <td class="py-2.5 px-3.5 border-b border-r border-slate-300 font-medium text-slate-700 text-center whitespace-nowrap">
                            {{ $row['division'] ?? '-' }}
                        </td>

                        <!-- POSITION -->
                        <td class="py-2.5 px-3.5 border-b border-r border-slate-300 text-slate-800 whitespace-nowrap font-medium">
                            {{ $row['jabatan'] }}
                        </td>

                        <!-- START WORK -->
                        <td class="py-2.5 px-3.5 border-b border-r border-slate-300 text-center font-mono text-slate-700 whitespace-nowrap">
                            {{ \App\Services\GoogleSyncService::formatMusashiDate($row['start_work'] ?? '') }}
                        </td>

                        <!-- KJ -->
                        <td class="py-2.5 px-3.5 border-b border-r border-slate-300 text-center font-mono font-bold text-slate-800 whitespace-nowrap">
                            {{ $row['kj'] }}
                        </td>

                        <!-- Sub Gol -->
                        <td class="py-2.5 px-3.5 border-b border-r border-slate-300 text-center font-semibold text-slate-700 whitespace-nowrap">
                            {{ $row['sub_gol'] }}
                        </td>

                        <!-- Hasil Kerja -->
                        <td class="py-2.5 px-3.5 border-b border-r border-slate-300 text-center font-mono font-semibold text-slate-800 whitespace-nowrap">
                            {{ $row['hasil_kerja_bobot'] !== null ? number_format($row['hasil_kerja_bobot'], 2) : '-' }}
                        </td>

                        <!-- Proses Kerja -->
                        <td class="py-2.5 px-3.5 border-b border-r border-slate-300 text-center font-mono font-semibold text-slate-800 whitespace-nowrap">
                            {{ $row['proses_kerja_bobot'] !== null ? number_format($row['proses_kerja_bobot'], 2) : '-' }}
                        </td>

                        <!-- Sikap Kerja -->
                        <td class="py-2.5 px-3.5 border-b border-r border-slate-300 text-center font-mono font-semibold text-slate-800 whitespace-nowrap">
                            {{ $row['sikap_kerja_bobot'] !== null ? number_format($row['sikap_kerja_bobot'], 2) : '-' }}
                        </td>

                        <!-- Man Management -->
                        <td class="py-2.5 px-3.5 border-b border-r border-slate-300 text-center font-mono font-semibold text-slate-800 whitespace-nowrap">
                            {{ $row['man_management_bobot'] !== null ? number_format($row['man_management_bobot'], 2) : '-' }}
                        </td>

                        <!-- Total Nilai -->
                        <td class="py-2.5 px-3.5 border-b border-r border-slate-300 text-center font-mono font-black text-slate-900 bg-slate-50/70 whitespace-nowrap">
                            {{ $row['total_nilai'] !== null ? number_format($row['total_nilai'], 2) : '-' }}
                        </td>

                        @if($showColForeman)
                        <!-- NILAI PK FOREMAN (JIKA ADA) -->
                        <td class="py-2.5 px-3 border-b border-r border-slate-300 text-center whitespace-nowrap">
                            @if(($row['pk_foreman'] ?? '-') !== '-')
                            <span class="inline-block px-2 py-0.5 border text-xs font-mono font-bold rounded-none
                                {{ ($row['pk_foreman'] ?? '') === 'S' ? 'bg-emerald-50 text-emerald-800 border-emerald-400' :
                                  (($row['pk_foreman'] ?? '') === 'A' ? 'bg-sky-50 text-sky-800 border-sky-400' :
                                  (($row['pk_foreman'] ?? '') === 'B' ? 'bg-amber-50 text-amber-800 border-amber-400' :
                                  (($row['pk_foreman'] ?? '') === 'C' ? 'bg-slate-100 text-slate-700 border-slate-400' : 'bg-rose-50 text-rose-800 border-rose-400'))) }}">
                                {{ $row['pk_foreman'] }}
                            </span>
                            @else
                            <span class="text-slate-400 font-mono font-bold">-</span>
                            @endif
                        </td>
                        @endif

                        @if($showColMandiri)
                        <!-- NILAI PK MANDIRI -->
                        <td class="py-2.5 px-3 border-b border-r border-slate-300 text-center whitespace-nowrap">
                            @if(($row['pk_mandiri'] ?? '-') !== '-')
                            <span class="inline-block px-2 py-0.5 border text-xs font-mono font-bold rounded-none
                                {{ ($row['pk_mandiri'] ?? '') === 'S' ? 'bg-emerald-50 text-emerald-800 border-emerald-400' :
                                  (($row['pk_mandiri'] ?? '') === 'A' ? 'bg-sky-50 text-sky-800 border-sky-400' :
                                  (($row['pk_mandiri'] ?? '') === 'B' ? 'bg-amber-50 text-amber-800 border-amber-400' :
                                  (($row['pk_mandiri'] ?? '') === 'C' ? 'bg-slate-100 text-slate-700 border-slate-400' : 'bg-rose-50 text-rose-800 border-rose-400'))) }}">
                                {{ $row['pk_mandiri'] }}
                            </span>
                            @else
                            <span class="text-slate-400 font-mono font-bold">-</span>
                            @endif
                        </td>
                        @endif

                        @if($showColSect)
                        <!-- NILAI PK SECT -->
                        <td class="py-2.5 px-3 border-b border-r border-slate-300 text-center whitespace-nowrap">
                            @if(($row['pk_sect'] ?? '-') !== '-')
                            <span class="inline-block px-2 py-0.5 border text-xs font-mono font-bold rounded-none
                                {{ ($row['pk_sect'] ?? '') === 'S' ? 'bg-emerald-50 text-emerald-800 border-emerald-400' :
                                  (($row['pk_sect'] ?? '') === 'A' ? 'bg-sky-50 text-sky-800 border-sky-400' :
                                  (($row['pk_sect'] ?? '') === 'B' ? 'bg-amber-50 text-amber-800 border-amber-400' :
                                  (($row['pk_sect'] ?? '') === 'C' ? 'bg-slate-100 text-slate-700 border-slate-400' : 'bg-rose-50 text-rose-800 border-rose-400'))) }}">
                                {{ $row['pk_sect'] }}
                            </span>
                            @else
                            <span class="text-slate-400 font-mono font-bold">-</span>
                            @endif
                        </td>
                        @endif

                        @if($showColDept)
                        <!-- NILAI PK DEPT -->
                        <td class="py-2.5 px-3 border-b border-r border-slate-300 text-center whitespace-nowrap">
                            @if(($row['pk_dept'] ?? '-') !== '-')
                            <span class="inline-block px-2 py-0.5 border text-xs font-mono font-bold rounded-none
                                {{ ($row['pk_dept'] ?? '') === 'S' ? 'bg-emerald-50 text-emerald-800 border-emerald-400' :
                                  (($row['pk_dept'] ?? '') === 'A' ? 'bg-sky-50 text-sky-800 border-sky-400' :
                                  (($row['pk_dept'] ?? '') === 'B' ? 'bg-amber-50 text-amber-800 border-amber-400' :
                                  (($row['pk_dept'] ?? '') === 'C' ? 'bg-slate-100 text-slate-700 border-slate-400' : 'bg-rose-50 text-rose-800 border-rose-400'))) }}">
                                {{ $row['pk_dept'] }}
                            </span>
                            @else
                            <span class="text-slate-400 font-mono font-bold">-</span>
                            @endif
                        </td>
                        @endif

                        @if($showColDiv)
                        <!-- NILAI PK DIV -->
                        <td class="py-2.5 px-3 border-b border-r border-slate-300 text-center whitespace-nowrap">
                            @if(($row['pk_div'] ?? '-') !== '-')
                            <span class="inline-block px-2 py-0.5 border text-xs font-mono font-bold rounded-none
                                {{ ($row['pk_div'] ?? '') === 'S' ? 'bg-emerald-50 text-emerald-800 border-emerald-400' :
                                  (($row['pk_div'] ?? '') === 'A' ? 'bg-sky-50 text-sky-800 border-sky-400' :
                                  (($row['pk_div'] ?? '') === 'B' ? 'bg-amber-50 text-amber-800 border-amber-400' :
                                  (($row['pk_div'] ?? '') === 'C' ? 'bg-slate-100 text-slate-700 border-slate-400' : 'bg-rose-50 text-rose-800 border-rose-400'))) }}">
                                {{ $row['pk_div'] }}
                            </span>
                            @else
                            <span class="text-slate-400 font-mono font-bold">-</span>
                            @endif
                        </td>
                        @endif

                        @if($showColFinal)
                        <!-- NILAI PK FINAL -->
                        <td class="py-2.5 px-3 border-b border-r border-slate-300 text-center whitespace-nowrap">
                            @if(($row['pk_final'] ?? '-') !== '-')
                            <span class="inline-block px-2 py-0.5 border text-xs font-mono font-black rounded-none
                                {{ ($row['pk_final'] ?? '') === 'S' ? 'bg-emerald-100 text-emerald-900 border-emerald-500' :
                                  (($row['pk_final'] ?? '') === 'A' ? 'bg-sky-100 text-sky-900 border-sky-500' :
                                  (($row['pk_final'] ?? '') === 'B' ? 'bg-amber-100 text-amber-900 border-amber-500' :
                                  (($row['pk_final'] ?? '') === 'C' ? 'bg-slate-200 text-slate-800 border-slate-500' : 'bg-rose-100 text-rose-900 border-rose-500'))) }}">
                                {{ $row['pk_final'] }}
                            </span>
                            @else
                            <span class="text-slate-400 font-mono font-bold">-</span>
                            @endif
                        </td>
                        @endif

                        <!-- AKSI -->
                        <td class="py-2.5 px-3 border-b border-r border-slate-300 text-center whitespace-nowrap">
                            @if(!empty($row['doc_id']))
                            <div class="inline-flex items-center gap-1.5">
                                <a href="{{ route('penilaian.show', $row['doc_id']) }}"
                                   class="p-1.5 border-b border-r border-slate-300 bg-white hover:bg-blue-50 hover:border-blue-400 text-slate-600 hover:text-blue-600 transition shadow-2xs inline-flex items-center justify-center cursor-pointer rounded-none"
                                   title="Buka Lembar Penilaian (Formulir PK)">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    <span class="sr-only">Buka Penilaian</span>
                                </a>
                                <a href="{{ route('penilaian.downloadPdf', $row['doc_id']) }}?download=1"
                                   class="p-1.5 border border-[#92302e] bg-[#92302e] hover:bg-[#7e2524] text-white transition shadow-2xs inline-flex items-center justify-center cursor-pointer rounded-none"
                                   title="Download Formulir PDF">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    <span class="sr-only">Download PDF</span>
                                </a>
                            </div>
                            @else
                            <span class="text-slate-300 font-mono font-bold">-</span>
                            @endif
                        </td>

                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ $totalTableCols }}" class="py-10 text-center text-slate-500 font-medium border-b border-r border-slate-300">
                            <div class="flex flex-col items-center justify-center gap-2">
                                <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <span class="text-xs font-semibold text-slate-600">Tidak ada data karyawan yang sesuai dengan filter pencarian saat ini.</span>
                                <a href="{{ route('laporan.index') }}" class="mt-1 px-3.5 py-1.5 text-xs font-bold text-white bg-[#92302e] hover:bg-[#7e2524] rounded-none transition shadow-xs inline-flex items-center gap-1.5">
                                    <span>✕</span> Reset Semua Filter
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Footer Bar flush inside card -->
        <div class="px-4 py-3 bg-white border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500">
            <div>
                Menampilkan <span class="font-bold text-slate-700">{{ count($employeeRows) }}</span> data karyawan pada laporan penilaian (<span class="font-bold text-emerald-700">{{ count($evaluatedRows) }}</span> sudah dinilai)
            </div>
            <div class="inline-flex items-center gap-1.5">
                <span class="text-[11px] text-slate-400 font-semibold">Tabel Sinkron Sesuai Kriteria Filter</span>
            </div>
        </div>

    </div>

    <!-- Modal Konfirmasi Sebelum Download (PDF / Excel) -->
    <div x-show="confirmModal.open"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-[1px]"
         role="dialog"
         aria-modal="true"
         aria-labelledby="laporan-export-modal-title"
         @click.self="closeModal()">
        <div class="w-full max-w-lg bg-white border border-slate-300 shadow-2xl rounded-none overflow-hidden">
            <!-- Modal Header -->
            <div class="px-5 py-3.5 bg-[#92302e] text-white flex items-center justify-between border-b border-[#781b1f]">
                <div class="flex items-center gap-2.5">
                    <svg class="w-5 h-5 text-white shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <h3 id="laporan-export-modal-title" class="text-sm font-black tracking-wide uppercase">
                        Konfirmasi Download
                    </h3>
                </div>
                <button type="button"
                        @click="closeModal()"
                        class="text-white/80 hover:text-white text-lg font-bold leading-none px-1 cursor-pointer"
                        aria-label="Tutup dialog konfirmasi">&times;</button>
            </div>

            <!-- Modal Body -->
            <div class="p-5 space-y-4 text-xs text-slate-700">
                <p class="font-semibold text-slate-800 leading-relaxed" x-text="confirmModal.headline"></p>

                <div class="bg-slate-50 border border-slate-200 p-3.5 space-y-2">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-slate-500 font-semibold">Format File</span>
                        <span class="font-black text-slate-900 px-2 py-0.5 bg-white border border-slate-300" x-text="confirmModal.formatLabel"></span>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-slate-500 font-semibold">Jumlah Data</span>
                        <span class="font-black text-slate-900" x-text="confirmModal.totalLabel"></span>
                    </div>

                    <template x-if="confirmModal.singleDetail">
                        <div class="pt-2 border-t border-slate-200 flex items-center justify-between gap-2">
                            <span class="text-slate-500 font-semibold">Target Formulir</span>
                            <span class="font-bold text-[#92302e] text-right" x-text="confirmModal.singleDetail"></span>
                        </div>
                    </template>
                </div>

                <!-- Breakdown Jenis Form untuk PDF -->
                <template x-if="confirmModal.mode === 'pdf' && confirmModal.forms.length > 0 && confirmModal.canDownload">
                    <div class="bg-white border border-slate-200 p-3 space-y-2">
                        <div class="font-bold text-slate-800 uppercase tracking-wider text-[11px]">
                            Rincian Formulir PDF yang Akan Dibuat:
                        </div>
                        <ul class="space-y-1">
                            <template x-for="item in confirmModal.forms" :key="item.label">
                                <li class="flex items-center justify-between text-slate-700">
                                    <span class="font-medium" x-text="item.label"></span>
                                    <span class="font-bold text-slate-900" x-text="item.count + ' PDF'"></span>
                                </li>
                            </template>
                        </ul>
                        <p class="text-[11px] text-slate-500 pt-1 border-t border-slate-100 leading-relaxed">
                            Isi dan kode tiap PDF otomatis menyesuaikan masing-masing formulir karyawan (mis. Form Section Head 07-006 untuk Section Head, Form KJ 1-3 07-001 untuk KJ 1-3).
                        </p>
                    </div>
                </template>

                <!-- Info Struktur Excel -->
                <template x-if="confirmModal.mode === 'excel' && confirmModal.canDownload">
                    <div class="bg-emerald-50/70 border border-emerald-200 p-3 text-[11px] text-emerald-900 leading-relaxed">
                        File Excel (<span class="font-bold">.xlsx</span>) berisi tabel rapi <span class="font-bold">18 kolom</span> sesuai filter aktif dengan header tetap (freeze pane), filter kolom otomatis, garis batas tabel lengkap, dan lebar kolom otomatis (auto-fit).
                    </div>
                </template>

                <!-- Catatan Pembuatan ZIP Banyak PDF -->
                <template x-if="confirmModal.mode === 'pdf' && confirmModal.isBulkZip && confirmModal.canDownload">
                    <div class="bg-amber-50 border border-amber-200 p-3 text-[11px] text-amber-900 leading-relaxed">
                        Karena terdapat lebih dari 1 karyawan, sistem akan membundel semua PDF ke dalam 1 arsip <span class="font-bold">.zip</span>. Mohon tunggu hingga proses download selesai.
                    </div>
                </template>

                <!-- Peringatan Data Kosong -->
                <template x-if="!confirmModal.canDownload">
                    <div class="bg-rose-50 border border-rose-200 p-3 text-[11px] text-rose-800 font-semibold leading-relaxed">
                        Tidak ada data penilaian yang sudah dinilai pada filter saat ini. Hanya data yang telah dinilai yang dapat diexport.
                    </div>
                </template>

                <!-- Status Sedang Mengunduh -->
                <template x-if="confirmModal.downloading">
                    <div class="bg-sky-50 border border-sky-200 p-3 text-[11px] text-sky-900 font-semibold flex items-center gap-2">
                        <svg class="w-4 h-4 text-sky-600 animate-spin shrink-0" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span>Permintaan download sedang diproses oleh server. File akan otomatis terunduh.</span>
                    </div>
                </template>
            </div>

            <!-- Modal Footer -->
            <div class="px-5 py-3 bg-slate-50 border-t border-slate-200 flex items-center justify-end gap-2">
                <button type="button"
                        @click="closeModal()"
                        class="px-4 py-2 rounded-none bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 text-xs font-bold transition cursor-pointer">
                    Batal
                </button>
                <button type="button"
                        @click="proceedDownload()"
                        :disabled="!confirmModal.canDownload || confirmModal.downloading"
                        :class="(!confirmModal.canDownload || confirmModal.downloading) ? 'opacity-50 cursor-not-allowed' : 'hover:bg-[#7e2524] cursor-pointer'"
                        class="px-4 py-2 rounded-none bg-[#92302e] text-white border border-[#781b1f] text-xs font-bold transition inline-flex items-center gap-1.5">
                    <span x-text="confirmModal.downloading ? 'Memproses...' : 'Ya, Lanjutkan Download'"></span>
                </button>
            </div>
        </div>
    </div>

</div>

@if(!$user->isForeman() && !$user->isSectionHead() && !in_array($user->role, ['section_head', 'sechead']) && !($user->isDepartmentHead() || in_array($user->role, ['dept_head', 'department_head']) || $user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head'])))
<!-- Chart.js Script for Target Kuota vs Realisasi Aktual Bar Chart -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const canvas = document.getElementById('targetVsActualChart');
    if (!canvas) return;

    const targetData = @json($targetKuota);
    const actualData = @json($quotaActualCounts ?? $actualCounts);

    const labels = ['S (Sangat Baik)', 'A (Baik)', 'B (Cukup)', 'C (Kurang)', 'K (Kurang Sekali)'];
    const targets = [
        targetData['S'] || 0,
        targetData['A'] || 0,
        targetData['B'] || 0,
        targetData['C'] || 0,
        targetData['K'] || 0
    ];
    const actuals = [
        actualData['S'] || 0,
        actualData['A'] || 0,
        actualData['B'] || 0,
        actualData['C'] || 0,
        actualData['K'] || 0
    ];

    new Chart(canvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Target Kuota',
                    data: targets,
                    backgroundColor: '#1e293b', // Slate 800
                    borderRadius: 0,
                    barPercentage: 0.65,
                    categoryPercentage: 0.7
                },
                {
                    label: 'Realisasi Aktual',
                    data: actuals,
                    backgroundColor: '#92302e', // Musashi Maroon
                    borderRadius: 0,
                    barPercentage: 0.65,
                    categoryPercentage: 0.7
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        boxWidth: 14,
                        boxHeight: 14,
                        borderRadius: 0,
                        font: {
                            family: 'ui-sans-serif, system-ui, sans-serif',
                            size: 11,
                            weight: 'bold'
                        }
                    }
                },
                tooltip: {
                    backgroundColor: '#0f172a',
                    cornerRadius: 0,
                    titleFont: { size: 12, weight: 'bold' },
                    bodyFont: { size: 11 }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        font: { size: 10, weight: 'bold' }
                    },
                    grid: {
                        color: '#e2e8f0'
                    }
                },
                x: {
                    ticks: {
                        font: { size: 10, weight: 'bold' }
                    },
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
});
</script>
@endif

<script>
window.__laporanExportConfig = @json($exportConfig);
window.laporanExportModal = function () {
    const cfg = window.__laporanExportConfig || {
        count: 0,
        pdfUrl: '',
        excelUrl: '',
        formBreakdown: [],
        rowsByDocId: {},
        isSectionHeadWithoutForeman: false,
    };

    return {
        confirmModal: {
            open: false,
            mode: 'pdf',
            url: '',
            headline: '',
            formatLabel: '',
            totalLabel: '',
            singleDetail: '',
            forms: [],
            isBulkZip: false,
            canDownload: false,
            downloading: false,
        },
        downloadTimer: null,

        init() {
            this.$el.addEventListener('click', (event) => {
                const rowPdfLink = event.target.closest('a[title="Download Formulir PDF"]');
                if (!rowPdfLink) return;
                event.preventDefault();
                this.openRowPdfConfirm(rowPdfLink);
            });
        },

        openConfirm(mode) {
            const count = Number(cfg.count || 0);
            const hasRows = count > 0;
            this.clearDownloadTimer();

            if (mode === 'excel') {
                this.confirmModal = {
                    open: true,
                    mode: 'excel',
                    url: cfg.excelUrl,
                    headline: 'Apakah Anda yakin ingin mendownload laporan dalam format Excel (.xlsx)?',
                    formatLabel: 'Excel (.xlsx)',
                    totalLabel: count + ' karyawan (yang sudah dinilai, 18 kolom)',
                    singleDetail: '',
                    forms: [],
                    isBulkZip: false,
                    canDownload: hasRows,
                    downloading: false,
                };
                return;
            }

            const isBulkZip = count > 1;
            const singleFormLabel = (cfg.formBreakdown && cfg.formBreakdown.length === 1)
                ? cfg.formBreakdown[0].label
                : '';

            this.confirmModal = {
                open: true,
                mode: 'pdf',
                url: cfg.pdfUrl,
                headline: isBulkZip
                    ? 'Apakah Anda yakin ingin mendownload seluruh formulir PDF sesuai filter saat ini?'
                    : 'Apakah Anda yakin ingin mendownload formulir PDF karyawan yang dipilih?',
                formatLabel: isBulkZip ? 'Paket Arsip ZIP (.zip berisi PDF)' : 'Dokumen PDF (.pdf)',
                totalLabel: count + ' karyawan (yang sudah dinilai)',
                singleDetail: !isBulkZip && singleFormLabel ? singleFormLabel : '',
                forms: Array.isArray(cfg.formBreakdown) ? cfg.formBreakdown : [],
                isBulkZip: isBulkZip,
                canDownload: hasRows,
                downloading: false,
            };
        },

        openRowPdfConfirm(linkEl) {
            const href = linkEl.getAttribute('href') || '';
            const match = href.match(/\/penilaian\/(\d+)\/pdf/);
            const docId = match ? match[1] : '';
            const rowInfo = docId && cfg.rowsByDocId ? cfg.rowsByDocId[docId] : null;

            const tr = linkEl.closest('tr');
            const cells = tr ? tr.querySelectorAll('td') : [];
            const nrp = rowInfo ? rowInfo.nrp : (cells[1] ? cells[1].textContent.trim() : '');
            const nama = rowInfo ? rowInfo.nama : (cells[2] ? cells[2].textContent.trim() : '');
            const formLabel = rowInfo ? rowInfo.formLabel : 'Form Penilaian Karya';

            this.clearDownloadTimer();
            this.confirmModal = {
                open: true,
                mode: 'pdf',
                url: href,
                headline: 'Apakah Anda yakin ingin mendownload formulir PDF untuk karyawan ini?',
                formatLabel: 'Dokumen PDF (.pdf)',
                totalLabel: '1 karyawan',
                singleDetail: [nrp, nama].filter(Boolean).join(' — ') + ' (' + formLabel + ')',
                forms: [{ label: formLabel, count: 1 }],
                isBulkZip: false,
                canDownload: href !== '',
                downloading: false,
            };
        },

        proceedDownload() {
            if (!this.confirmModal.canDownload || this.confirmModal.downloading || !this.confirmModal.url) {
                return;
            }
            this.confirmModal.downloading = true;
            window.location.assign(this.confirmModal.url);

            const resetMs = this.confirmModal.isBulkZip ? 8000 : 2500;
            this.downloadTimer = setTimeout(() => {
                this.confirmModal.downloading = false;
                this.confirmModal.open = false;
            }, resetMs);
        },

        closeModal() {
            this.clearDownloadTimer();
            this.confirmModal.downloading = false;
            this.confirmModal.open = false;
        },

        clearDownloadTimer() {
            if (this.downloadTimer) {
                clearTimeout(this.downloadTimer);
                this.downloadTimer = null;
            }
        },
    };
};
</script>

<!-- Print Stylesheet -->
<style>
@media print {
    body {
        background-color: #ffffff !important;
        font-size: 10pt !important;
    }
    aside, header, nav, .print\:hidden, [x-cloak] {
        display: none !important;
    }
    main {
        padding: 0 !important;
        margin: 0 !important;
        width: 100% !important;
    }
    .shadow-sm, .border-2 {
        box-shadow: none !important;
    }
}
</style>
@endsection
