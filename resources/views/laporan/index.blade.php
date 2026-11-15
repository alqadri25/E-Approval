@extends('layouts.app', ['title' => 'Laporan Penilaian'])

@section('content')
@php
    $exportCount = count($employeeRows);
    $pdfFormCounts = [];
    $rowsByDocId = [];
    foreach ($employeeRows as $rowItem) {
        $kode = trim((string)($rowItem['kode_form'] ?? ''));
        $label = $kode !== '' ? \App\Http\Controllers\PenilaianController::getFormName($kode) : 'Form Penilaian Karya';
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

        <!-- Action Export Buttons -->
        <div class="flex flex-wrap items-center gap-2">
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
    <!-- 5 Workflow Progress Cards for Foreman (Alur: Foreman -> Section Head -> Department Head sebagai Tingkat Tertinggi) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
        <!-- Card 1: Total Karyawan -->
        <div class="bg-white rounded-none border border-slate-300 p-4 shadow-sm flex flex-col justify-between">
            <div>
                <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 block mb-1">Total Karyawan</span>
                <div class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight leading-none">
                    {{ $roleWorkflowCards['foreman']['total_karyawan'] ?? $totalKaryawan }}
                </div>
                <span class="text-[11px] text-slate-500 mt-1 block font-medium">
                    Anggota Foreman
                </span>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-200 flex items-center justify-between text-[11px]">
                <span class="font-bold text-emerald-700">Sudah: {{ $sudahDinilai }}</span>
                <span class="font-bold text-slate-500">Belum: {{ $belumDinilai }}</span>
            </div>
        </div>

        <!-- Card 2: Siap Disahkan (To-Do) -->
        <div class="bg-white rounded-none border {{ ($roleWorkflowCards['foreman']['siap_disahkan'] ?? 0) > 0 ? 'border-amber-400 bg-amber-50/40' : 'border-slate-300' }} p-4 shadow-sm flex flex-col justify-between">
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
        <div class="bg-white rounded-none border border-slate-300 p-4 shadow-sm flex flex-col justify-between">
            <div>
                <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 block mb-1">Diteruskan Ke Section Head</span>
                <div class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight leading-none">
                    {{ $roleWorkflowCards['foreman']['diteruskan_section'] ?? 0 }}
                </div>
                <span class="text-[11px] text-slate-500 mt-1 block font-medium">
                    Telah diverifikasi Foreman
                </span>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-200 flex items-center justify-between text-[11px]">
                <span class="font-bold text-blue-700">Terkirim ke Section</span>
                <span class="font-mono text-slate-400 text-[10px]">Alur Verifikasi</span>
            </div>
        </div>

        <!-- Card 4: Menunggu Section Head -->
        <div class="bg-white rounded-none border {{ ($roleWorkflowCards['foreman']['menunggu_section'] ?? 0) > 0 ? 'border-blue-300 bg-blue-50/30' : 'border-slate-300' }} p-4 shadow-sm flex flex-col justify-between">
            <div>
                <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 block mb-1">Menunggu Section Head</span>
                <div class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight leading-none">
                    {{ $roleWorkflowCards['foreman']['menunggu_section'] ?? 0 }}
                </div>
                <span class="text-[11px] text-slate-500 mt-1 block font-medium">
                    {{ ($roleWorkflowCards['foreman']['menunggu_section'] ?? 0) > 0 ? 'Sedang ditinjau Section Head' : 'Tidak ada antrean' }}
                </span>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-200 flex items-center justify-between text-[11px]">
                <span class="font-medium text-slate-500">{{ ($roleWorkflowCards['foreman']['menunggu_section'] ?? 0) > 0 ? 'Proses review SH' : 'Selesai ditinjau' }}</span>
                <span class="font-mono text-slate-400 text-[10px]">Tahap Sect</span>
            </div>
        </div>

        <!-- Card 5: Menunggu Department Head -->
        <div class="bg-white rounded-none border {{ ($roleWorkflowCards['foreman']['menunggu_dept'] ?? 0) > 0 ? 'border-indigo-300 bg-indigo-50/30' : 'border-slate-300' }} p-4 shadow-sm flex flex-col justify-between">
            <div>
                <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 block mb-1">Menunggu Department Head</span>
                <div class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight leading-none">
                    {{ $roleWorkflowCards['foreman']['menunggu_dept'] ?? 0 }}
                </div>
                <span class="text-[11px] text-slate-500 mt-1 block font-medium">
                    {{ ($roleWorkflowCards['foreman']['menunggu_dept'] ?? 0) > 0 ? 'Telah disetujui Section Head, menunggu Department Head' : 'Tidak ada antrean' }}
                </span>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-200 flex items-center justify-between text-[11px]">
                <span class="font-medium text-slate-500">{{ ($roleWorkflowCards['foreman']['selesai'] ?? 0) > 0 ? ($roleWorkflowCards['foreman']['selesai'] . ' selesai final') : 'Tingkat Akhir' }}</span>
                <span class="font-mono text-slate-400 text-[10px]">Tingkat Akhir</span>
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

        <!-- Card 2: Siap Disahkan (To-Do) -->
        <div class="bg-white rounded-none border {{ ($roleWorkflowCards['section_head']['siap_disahkan'] ?? 0) > 0 ? 'border-amber-400 bg-amber-50/40' : 'border-slate-300' }} p-4 shadow-sm flex flex-col justify-between">
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

        {{-- Card 3 --}}
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
        <!-- Card 3: Proses Foreman -->
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
                <span class="font-medium text-slate-500">Belum ke Section Head</span>
                <span class="font-mono text-slate-400 text-[10px]">Proses Foreman</span>
            </div>
        </div>
        @endif

        <!-- Card 4: Diteruskan Dept Head -->
        <div class="bg-white rounded-none border border-slate-300 p-4 shadow-sm flex flex-col justify-between">
            <div>
                <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 block mb-1">Diteruskan Dept Head</span>
                <div class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight leading-none">
                    {{ $roleWorkflowCards['section_head']['diteruskan_dept'] ?? 0 }}
                </div>
                <span class="text-[11px] text-slate-500 mt-1 block font-medium">
                    Telah diverifikasi Section Head
                </span>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-200 flex items-center justify-between text-[11px]">
                <span class="font-bold text-blue-700">Terkirim ke Dept</span>
                <span class="font-mono text-slate-400 text-[10px]">Alur Verifikasi</span>
            </div>
        </div>

        <!-- Card 5: Approval Department Head -->
        <div class="bg-white rounded-none border {{ ($roleWorkflowCards['section_head']['menunggu_dept'] ?? 0) > 0 ? 'border-blue-300 bg-blue-50/30' : 'border-slate-300' }} p-4 shadow-sm flex flex-col justify-between">
            <div>
                <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 block mb-1">Approval Department Head</span>
                <div class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight leading-none">
                    {{ $roleWorkflowCards['section_head']['menunggu_dept'] ?? 0 }}
                </div>
                <span class="text-[11px] text-slate-500 mt-1 block font-medium">
                    {{ ($roleWorkflowCards['section_head']['menunggu_dept'] ?? 0) > 0 ? 'Sedang ditinjau Department Head' : 'Tidak ada antrean' }}
                </span>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-200 flex items-center justify-between text-[11px]">
                <span class="font-medium text-slate-500">{{ ($roleWorkflowCards['section_head']['menunggu_dept'] ?? 0) > 0 ? 'Proses review DH' : 'Selesai ditinjau' }}</span>
                <span class="font-mono text-slate-400 text-[10px]">Tahap Dept</span>
            </div>
        </div>
    </div>

    @elseif($user->isDepartmentHead() || in_array($user->role, ['dept_head', 'department_head']))
    <!-- 5 Workflow Progress Cards for Department Head -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
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
                <span class="font-bold text-emerald-700">Sudah: {{ $roleWorkflowCards['dept_head']['foreman_sudah'] ?? 0 }}</span>
                <span class="font-bold text-slate-500">Belum: {{ $roleWorkflowCards['dept_head']['proses_foreman'] ?? 0 }}</span>
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
                <span class="font-bold text-emerald-700">Sudah: {{ $roleWorkflowCards['dept_head']['section_sudah'] ?? 0 }}</span>
                <span class="font-bold text-slate-500">Belum: {{ $roleWorkflowCards['dept_head']['proses_section_only'] ?? 0 }}</span>
            </div>
        </div>

        <!-- Card 4: Siap Disahkan (To-Do) -->
        <div class="bg-white rounded-none border {{ ($roleWorkflowCards['dept_head']['siap_disahkan'] ?? 0) > 0 ? 'border-amber-400 bg-amber-50/40' : 'border-slate-300' }} p-4 shadow-sm flex flex-col justify-between">
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
                    {{ ($roleWorkflowCards['dept_head']['siap_disahkan'] ?? 0) > 0 ? 'Menunggu review' : 'Tidak ada antrean' }}
                </span>
                @if(($roleWorkflowCards['dept_head']['siap_disahkan'] ?? 0) > 0)
                    <a href="{{ route('penilaian.dept') }}" class="text-[#92302e] font-bold hover:underline inline-flex items-center gap-1">
                        Kerjakan &rarr;
                    </a>
                @endif
            </div>
        </div>

        <!-- Card 5: Approval Division Head -->
        <div class="bg-white rounded-none border {{ ($roleWorkflowCards['dept_head']['menunggu_div'] ?? 0) > 0 ? 'border-indigo-300 bg-indigo-50/30' : 'border-slate-300' }} p-4 shadow-sm flex flex-col justify-between">
            <div>
                <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 block mb-1">Approval Division Head</span>
                <div class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight leading-none">
                    {{ $roleWorkflowCards['dept_head']['diteruskan_div'] ?? 0 }}
                </div>
                <span class="text-[11px] text-slate-500 mt-1 block font-medium">
                    {{ ($roleWorkflowCards['dept_head']['menunggu_div'] ?? 0) > 0 ? 'Menunggu approval akhir Division Head' : 'Pengerjaan Form Division Head' }}
                </span>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-200 flex items-center justify-between text-[11px]">
                <span class="font-bold text-emerald-700">Sudah: {{ $roleWorkflowCards['dept_head']['selesai'] ?? 0 }}</span>
                <span class="font-bold text-amber-700">Belum: {{ $roleWorkflowCards['dept_head']['menunggu_div'] ?? 0 }}</span>
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
        <div class="bg-white rounded-none border {{ ($roleWorkflowCards['div_head']['siap_disahkan'] ?? 0) > 0 ? 'border-amber-400 bg-amber-50/40' : 'border-slate-300' }} p-4 shadow-sm flex flex-col justify-between">
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
        <div class="bg-white rounded-none border {{ ($roleWorkflowCards['div_head']['disahkan_final'] ?? 0) > 0 ? 'border-emerald-300 bg-emerald-50/30' : 'border-slate-300' }} p-4 shadow-sm flex flex-col justify-between">
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
    <!-- Hierarchy Cards (Total Karyawan, Total Section Head, Total Dept Head, Total Div Head + Optional Total Foreman) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 {{ ($hasForeman ?? true) ? 'lg:grid-cols-5' : 'lg:grid-cols-4' }} gap-3">
        
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

        @if($hasForeman ?? true)
        <!-- Card 2: Total Foreman (Hanya ditampilkan jika terdapat struktur Foreman) -->
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
        @endif

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

    <!-- Grafik Batang / Tabel Terpadu Target vs Actual Nilai S, A, B, C, K -->
    @php
        $isForemanLaporan = $user->isForeman();
        $isDeptHeadLaporan = $user->isDepartmentHead() || in_array($user->role, ['dept_head', 'department_head']);
        $isDivHeadLaporan = $user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head']);
    @endphp
    @if($isForemanLaporan || $user->isSectionHead() || in_array($user->role, ['section_head', 'sechead']) || $isDeptHeadLaporan || $isDivHeadLaporan)
    @include('penilaian.partials.target_actual_matrix_table', [
        'evaluatorRoleLabel' => $isDivHeadLaporan ? 'Division Head' : ($isDeptHeadLaporan ? 'Department Head' : ($isForemanLaporan ? 'Foreman' : 'Section Head')),
        'includeDeptComparison' => $isDivHeadLaporan,
        'subtitleText' => $isDivHeadLaporan
            ? 'Ringkasan target kuota, nilai Department Head, & actual Division Head per KJ dalam satu tabel.'
            : ($isDeptHeadLaporan
                ? 'Berdasarkan penilaian department head per KJ (Target vs Actual).'
                : ($isForemanLaporan
                    ? 'Berdasarkan penilaian foreman per KJ (Target vs Actual).'
                    : 'Berdasarkan penilaian section head per KJ (Target vs Actual).')),
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
                <h3 class="text-xs sm:text-sm font-black text-slate-900 tracking-wider flex items-center gap-2">
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
                        <td colspan="13" class="py-3 px-3.5 border border-slate-300 text-center text-slate-400">Tidak ada data divisi / departemen terdaftar.</td>
                    </tr>
                    @endforelse
                </tbody>
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
                            {{ ($totalDinilaiPerusahaan ?? $sudahDinilai) >= ($totalTargetPerusahaan ?? 1) ? 'Tuntas' : 'Sedang Berjalan' }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
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

    <!-- Tabel Rincian Laporan Penilaian (19 Kolom Lengkap Termasuk DIVISION) -->
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
                        <th class="py-3 px-3.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center whitespace-nowrap">NILAI PK ORIGINAL</th>
                        <th class="py-3 px-3.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center whitespace-nowrap">NILAI PK DEPT</th>
                        <th class="py-3 px-3.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center whitespace-nowrap">NILAI PK DIV</th>
                        <th class="py-3 px-3.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center whitespace-nowrap">NILAI PK FINAL</th>
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
                            {{ $row['start_work'] }}
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
                            {{ $row['is_dinilai'] && $row['hasil_kerja_bobot'] !== null ? number_format($row['hasil_kerja_bobot'], 2) : '-' }}
                        </td>

                        <!-- Proses Kerja -->
                        <td class="py-2.5 px-3.5 border-b border-r border-slate-300 text-center font-mono font-semibold text-slate-800 whitespace-nowrap">
                            {{ $row['is_dinilai'] && $row['proses_kerja_bobot'] !== null ? number_format($row['proses_kerja_bobot'], 2) : '-' }}
                        </td>

                        <!-- Sikap Kerja -->
                        <td class="py-2.5 px-3.5 border-b border-r border-slate-300 text-center font-mono font-semibold text-slate-800 whitespace-nowrap">
                            {{ $row['is_dinilai'] && $row['sikap_kerja_bobot'] !== null ? number_format($row['sikap_kerja_bobot'], 2) : '-' }}
                        </td>

                        <!-- Man Management -->
                        <td class="py-2.5 px-3.5 border-b border-r border-slate-300 text-center font-mono font-semibold text-slate-800 whitespace-nowrap">
                            {{ $row['is_dinilai'] && $row['man_management_bobot'] !== null ? number_format($row['man_management_bobot'], 2) : '-' }}
                        </td>

                        <!-- Total Nilai -->
                        <td class="py-2.5 px-3.5 border-b border-r border-slate-300 text-center font-mono font-black text-slate-900 bg-slate-50/70 whitespace-nowrap">
                            {{ $row['is_dinilai'] && $row['total_nilai'] !== null ? number_format($row['total_nilai'], 2) : '-' }}
                        </td>

                        <!-- NILAI PK ORIGINAL -->
                        <td class="py-2.5 px-3 border-b border-r border-slate-300 text-center whitespace-nowrap">
                            @if(($row['pk_original'] ?? '-') !== '-')
                            <span class="inline-block px-2 py-0.5 border text-xs font-mono font-bold rounded-none
                                {{ ($row['pk_original'] ?? '') === 'S' ? 'bg-emerald-50 text-emerald-800 border-emerald-400' :
                                  (($row['pk_original'] ?? '') === 'A' ? 'bg-sky-50 text-sky-800 border-sky-400' :
                                  (($row['pk_original'] ?? '') === 'B' ? 'bg-amber-50 text-amber-800 border-amber-400' :
                                  (($row['pk_original'] ?? '') === 'C' ? 'bg-slate-100 text-slate-700 border-slate-400' : 'bg-rose-50 text-rose-800 border-rose-400'))) }}">
                                {{ $row['pk_original'] }}
                            </span>
                            @else
                            <span class="text-slate-400 font-mono font-bold">-</span>
                            @endif
                        </td>

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
                        <td colspan="22" class="py-8 text-center text-slate-400 font-semibold border-b border-r border-slate-300">
                            Tidak ada data karyawan yang sesuai dengan filter pencarian.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Footer Bar flush inside card -->
        <div class="px-4 py-3 bg-white border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500">
            <div>
                Menampilkan <span class="font-bold text-slate-700">{{ count($employeeRows) }}</span> data karyawan pada laporan penilaian
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
                        Tidak ada data karyawan yang sesuai filter saat ini. Silakan ubah filter terlebih dahulu sebelum melakukan download.
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
                    totalLabel: count + ' karyawan (18 kolom)',
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
                totalLabel: count + ' karyawan',
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
