@extends('layouts.app', ['title' => 'Pengaturan Sistem & Database'])

@section('content')
<div class="space-y-6">

    <!-- Top Header Card (Musashi Red Banner, Solid & Clean) -->
    <div class="bg-gradient-to-r from-[#962226] via-[#92302e] to-[#781b1f] text-white rounded-none p-5 sm:p-6 border border-[#781b1f] shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight">Pengaturan</h1>
            <p class="text-xs sm:text-sm text-red-100 font-medium mt-0.5">Konfigurasi database Google Spreadsheet &amp; Apps Script, sinkronisasi data, dan ekspor resmi</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ $user->isSuperadmin() ? route('superadmin.database.exportExcel') : route('admin.database.exportExcel') }}" 
               class="inline-flex items-center gap-1.5 px-4 py-2 bg-white text-[#92302e] hover:bg-slate-100 text-xs font-bold rounded-none shadow-xs transition cursor-pointer">
                <svg class="w-4 h-4 text-[#92302e]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <span>Unduh Seluruh Database (.xlsx)</span>
            </a>
        </div>
    </div>

    <!-- Flash Notification Success / Error -->
    @if(session('success'))
    <div class="bg-emerald-50 border-l-4 border-emerald-600 p-4 rounded-none flex items-start gap-3 shadow-xs">
        <svg class="w-5 h-5 text-emerald-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <div class="text-xs font-medium text-emerald-900">
            <p class="font-bold">Berhasil</p>
            <p class="mt-0.5">{{ session('success') }}</p>
        </div>
    </div>
    @endif

    @if(session('error'))
    <div class="bg-rose-50 border-l-4 border-[#92302e] p-4 rounded-none flex items-start gap-3 shadow-xs">
        <svg class="w-5 h-5 text-[#92302e] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <div class="text-xs font-medium text-rose-900">
            <p class="font-bold">Terjadi Kesalahan</p>
            <p class="mt-0.5">{{ session('error') }}</p>
        </div>
    </div>
    @endif

    <!-- ========================================================================= -->
    <!-- 1. PUSAT SINKRONISASI DATABASE                                            -->
    <!-- ========================================================================= -->
    <div class="bg-white rounded-none border border-slate-300 p-5 sm:p-6 shadow-sm space-y-5">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-4 border-b border-slate-200">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-none bg-[#fbe8e8] text-[#92302e] flex items-center justify-center font-black text-lg flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                </div>
                <div>
                    <h2 class="text-base font-black text-slate-900 tracking-tight">Pusat Sinkronisasi Database</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Sinkronkan data master karyawan riil Excel (<code class="text-slate-700 font-bold">DATA PK.xlsx</code>), dokumen penilaian karya, dan akun pimpinan</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-none bg-emerald-50 text-emerald-800 text-[11px] font-bold border border-emerald-200">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    Database SQLite: Terhubung
                </span>
            </div>
        </div>

        <!-- 4 Status Metric Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3.5">
            <!-- Metric 1: Karyawan -->
            <div class="bg-slate-50 p-4 rounded-none border border-slate-200 flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Master Karyawan</p>
                    <p class="text-2xl font-black text-slate-900 mt-1">{{ $totalKaryawan }}</p>
                    <p class="text-[10px] text-slate-500 mt-0.5">Riil DATA PK.xlsx</p>
                </div>
                <div class="w-9 h-9 rounded-none bg-white text-[#92302e] border border-slate-200 flex items-center justify-center font-bold">
                    👥
                </div>
            </div>

            <!-- Metric 2: Dokumen PK -->
            <div class="bg-slate-50 p-4 rounded-none border border-slate-200 flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Dokumen Penilaian</p>
                    <p class="text-2xl font-black text-slate-900 mt-1">{{ $totalDokumen }}</p>
                    <p class="text-[10px] text-slate-500 mt-0.5">Periode 2025 Aktif</p>
                </div>
                <div class="w-9 h-9 rounded-none bg-white text-indigo-700 border border-slate-200 flex items-center justify-center font-bold">
                    📄
                </div>
            </div>

            <!-- Metric 3: Akun Pengguna -->
            <div class="bg-slate-50 p-4 rounded-none border border-slate-200 flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Akun Pengguna</p>
                    <p class="text-2xl font-black text-slate-900 mt-1">{{ $totalUsers }}</p>
                    <p class="text-[10px] text-slate-500 mt-0.5">Pimpinan &amp; Admin</p>
                </div>
                <div class="w-9 h-9 rounded-none bg-white text-emerald-700 border border-slate-200 flex items-center justify-center font-bold">
                    🔑
                </div>
            </div>

            <!-- Metric 4: Terakhir Sinkron -->
            <div class="bg-slate-50 p-4 rounded-none border border-slate-200 flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Terakhir Sinkron</p>
                    <p class="text-xs font-black text-slate-900 mt-2 leading-snug">{{ $lastSync }}</p>
                    <p class="text-[10px] text-emerald-600 font-bold mt-0.5">Real-Time Terhubung</p>
                </div>
                <div class="w-9 h-9 rounded-none bg-white text-amber-700 border border-slate-200 flex items-center justify-center font-bold">
                    ⏱️
                </div>
            </div>
        </div>

        <!-- Sync Action Buttons -->
        <div class="pt-2 flex flex-wrap items-center gap-2.5">
            <!-- 1. Tombol Utama Sinkron Database -->
            <form method="POST" action="{{ $user->isSuperadmin() ? route('superadmin.database.sync') : route('admin.database.sync') }}"
                  onsubmit="return confirm('Mulai sinkronisasi seluruh database dari berkas DATA PK.xlsx? Proses ini akan menyelaraskan 180 data karyawan, dokumen penilaian, dan akun pimpinan.');">
                @csrf
                <button type="submit" 
                        class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#92302e] hover:bg-[#7e2524] text-white text-xs font-black rounded-none shadow-xs transition">
                    <svg class="w-4 h-4 animate-spin-hover" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    <span>Sinkronkan Seluruh Database Sekarang</span>
                </button>
            </form>

            <!-- 2. Sinkronkan Akun Pimpinan -->
            <form method="POST" action="{{ $user->isSuperadmin() ? route('superadmin.users.sync') : route('admin.users.sync') }}"
                  onsubmit="return confirm('Selaraskan akun pimpinan (Foreman, Section Head, Dept Head, Div Head) dengan Master Karyawan?');">
                @csrf
                <button type="submit" 
                        class="inline-flex items-center gap-1.5 px-3.5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold rounded-none border border-slate-300 shadow-xs transition">
                    <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                    <span>Sinkronkan Akun Pengguna</span>
                </button>
            </form>

            <!-- 3. Bersihkan Cache Sistem -->
            <form method="POST" action="{{ $user->isSuperadmin() ? route('superadmin.database.clearCache') : route('admin.database.clearCache') }}">
                @csrf
                <button type="submit" 
                        class="inline-flex items-center gap-1.5 px-3.5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold rounded-none border border-slate-300 shadow-xs transition">
                    <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                    <span>Bersihkan Cache Sistem</span>
                </button>
            </form>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 2. DOWNLOAD DATABASE DALAM BENTUK EXCEL (.XLSX)                            -->
    <!-- ========================================================================= -->
    <div class="bg-white rounded-none border border-slate-300 p-5 sm:p-6 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-slate-200">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-none bg-emerald-50 text-emerald-800 border border-emerald-200 flex items-center justify-center font-black text-lg flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <div>
                    <h2 class="text-base font-black text-slate-900 tracking-tight">Download Database (Format Excel .xlsx)</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Unduh seluruh tabel database sistem ke dalam berkas Excel (.xlsx) resmi dengan multi-sheet terstruktur lengkap</p>
                </div>
            </div>
            <div>
                <!-- Tombol Utama Download All Database -->
                <a href="{{ $user->isSuperadmin() ? route('superadmin.database.exportExcel') : route('admin.database.exportExcel') }}" 
                   class="inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-black rounded-none shadow-sm transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                    </svg>
                    <span>Unduh Seluruh Database (Multi-Sheet .xlsx)</span>
                </a>
            </div>
        </div>

        <!-- Single Table Quick Download Badges -->
        <div>
            <p class="text-xs font-bold text-slate-600 mb-2">Atau unduh berkas Excel spesifik per tabel:</p>
            <div class="flex flex-wrap items-center gap-2">
                @php
                    $exportBase = $user->isSuperadmin() ? route('superadmin.database.exportExcel') : route('admin.database.exportExcel');
                @endphp
                <a href="{{ $exportBase }}?table=karyawan" 
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-semibold rounded-none border border-slate-300 transition">
                    <span>👥 Master Karyawan (180 Data)</span>
                    <span class="text-emerald-700 font-bold">&darr;</span>
                </a>
                <a href="{{ $exportBase }}?table=dokumen" 
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-semibold rounded-none border border-slate-300 transition">
                    <span>📄 Dokumen Penilaian (180 Form)</span>
                    <span class="text-emerald-700 font-bold">&darr;</span>
                </a>
                <a href="{{ $exportBase }}?table=users" 
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-semibold rounded-none border border-slate-300 transition">
                    <span>🔑 Manajemen Akun &amp; Pengguna</span>
                    <span class="text-emerald-700 font-bold">&darr;</span>
                </a>
                <a href="{{ $exportBase }}?table=kuota_departemen" 
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-semibold rounded-none border border-slate-300 transition">
                    <span>🎯 Target &amp; Kuota Nilai</span>
                    <span class="text-emerald-700 font-bold">&darr;</span>
                </a>
                <a href="{{ $exportBase }}?table=master_kriteria" 
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-semibold rounded-none border border-slate-300 transition">
                    <span>📋 Kriteria Rubrik Form</span>
                    <span class="text-emerald-700 font-bold">&darr;</span>
                </a>
                <a href="{{ $exportBase }}?table=nilai" 
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-semibold rounded-none border border-slate-300 transition">
                    <span>📊 Detail Skor Nilai</span>
                    <span class="text-emerald-700 font-bold">&darr;</span>
                </a>
                <a href="{{ $exportBase }}?table=tanda_tangan" 
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-semibold rounded-none border border-slate-300 transition">
                    <span>✍️ Riwayat Tanda Tangan Digital</span>
                    <span class="text-emerald-700 font-bold">&darr;</span>
                </a>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 3. INTEGRASI BASIS DATA GOOGLE SPREADSHEET, DRIVE & GOOGLE APPS SCRIPT    -->
    <!-- ========================================================================= -->
    <div class="bg-white rounded-none border border-slate-300 p-5 sm:p-6 shadow-sm space-y-6">
        
        <!-- Header Section -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-4 border-b border-slate-200">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-none bg-emerald-50 text-emerald-800 border border-emerald-300 flex items-center justify-center font-black text-lg flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <div>
                    <h2 class="text-base font-black text-slate-900 tracking-tight">Integrasi Google Spreadsheet &amp; Apps Script (Database Utama)</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Kelola tautan Google Spreadsheet sebagai basis data utama, tautan Google Drive untuk arsip PDF rapi, dan Google Apps Script Web App</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-none bg-emerald-50 text-emerald-800 text-[11px] font-bold border border-emerald-200">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    Spreadsheet ID: {{ substr($googleConfig['spreadsheet_id'] ?? '14-xI_m24eXSj1L2aiX_gytMWrrS9HnjH5HzcRgWV_9g', 0, 16) }}...
                </span>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-none bg-amber-50 text-amber-800 text-[11px] font-bold border border-amber-200">
                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                    Drive Folder ID: {{ substr($googleConfig['drive_folder_id'] ?? '1zB2loWV14fCpLWMCnNiEa4dVKAvhLZYE', 0, 16) }}...
                </span>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-none {{ !empty($googleConfig['appscript_url']) ? 'bg-indigo-50 text-indigo-800 border border-indigo-200' : 'bg-slate-100 text-slate-600 border border-slate-300' }} text-[11px] font-bold">
                    <span class="w-2 h-2 rounded-full {{ !empty($googleConfig['appscript_url']) ? 'bg-indigo-500' : 'bg-slate-400' }}"></span>
                    {{ !empty($googleConfig['appscript_url']) ? 'Apps Script Aktif' : 'Apps Script Belum Diatur' }}
                </span>
            </div>
        </div>

        <!-- Connection Test Diagnostic Alert if test triggered -->
        @if(session('test_results'))
        @php $diag = session('test_results'); @endphp
        <div class="p-4 rounded-none border {{ ($diag['success'] ?? false) ? 'bg-emerald-50 border-emerald-300 text-emerald-900' : 'bg-amber-50 border-amber-300 text-amber-900' }}">
            <div class="flex items-start gap-3">
                <span class="text-lg">{{ ($diag['success'] ?? false) ? '✓' : '⚠️' }}</span>
                <div class="space-y-1 text-xs">
                    <p class="font-black text-sm">Hasil Diagnostik Uji Koneksi Google Spreadsheet &amp; Apps Script</p>
                    <ul class="list-disc list-inside space-y-0.5 text-slate-700">
                        @foreach($diag['messages'] ?? [] as $m)
                            <li>{{ $m }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
        @endif

        <!-- Main Form: Save & Switch Google Links -->
        <form method="POST" action="{{ $user->isSuperadmin() ? route('superadmin.google.links') : route('admin.google.links') }}" class="space-y-5">
            @csrf

            <!-- Field 1: Link Google Spreadsheet (Database Utama) -->
            <div x-data="{ copiedSheet: false }">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 mb-1.5">
                    <label class="block text-xs font-black text-slate-800 uppercase tracking-wider">
                        1. Link Google Spreadsheet (Basis Data Utama) <span class="text-[#92302e]">*</span>
                    </label>
                    <div class="flex items-center gap-2">
                        <button type="button" 
                                @click="navigator.clipboard.writeText('{{ $googleConfig['spreadsheet_url'] }}'); copiedSheet = true; setTimeout(() => copiedSheet = false, 2500)"
                                class="inline-flex items-center gap-1 text-[11px] font-bold text-slate-600 hover:text-slate-900 border border-slate-300 px-2.5 py-1 rounded-none bg-slate-50 transition cursor-pointer">
                            <span x-show="!copiedSheet">📋 Salin Link Spreadsheet</span>
                            <span x-show="copiedSheet" class="text-emerald-700 font-black">✓ Link Tersalin!</span>
                        </button>
                        @if(!empty($googleConfig['spreadsheet_url']))
                        <a href="{{ $googleConfig['spreadsheet_url'] }}" target="_blank" rel="noopener noreferrer"
                           class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700 hover:text-emerald-900 px-2.5 py-1 border border-emerald-300 bg-emerald-50 rounded-none hover:underline">
                            <span>Buka Google Spreadsheet ↗</span>
                        </a>
                        @endif
                    </div>
                </div>
                <div class="relative">
                    <input type="text" name="google_spreadsheet_url" 
                           value="{{ old('google_spreadsheet_url', $googleConfig['spreadsheet_url'] ?? '') }}"
                           placeholder="https://docs.google.com/spreadsheets/d/14-xI_m24eXSj1L2aiX_gytMWrrS9HnjH5HzcRgWV_9g/edit?usp=sharing"
                           class="w-full px-3.5 py-2.5 rounded-none border border-slate-300 font-mono text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-[#92302e]">
                </div>
                <p class="text-[11px] text-slate-500 mt-1">
                    Anda dapat menyalin atau mengubah link spreadsheet ini kapan saja. Saat disimpan, sistem langsung aktif menggunakan basis data baru untuk 180 data karyawan, dokumen penilaian, dan tanda tangan digital.
                </p>
            </div>

            <!-- Field 2: Link Google Drive Folder (Penyimpanan Arsip PDF) -->
            <div x-data="{ copiedDrive: false }">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 mb-1.5">
                    <label class="block text-xs font-black text-slate-800 uppercase tracking-wider">
                        2. Link Folder Google Drive (Arsip Dokumen PDF) <span class="text-[#92302e]">*</span>
                    </label>
                    <div class="flex items-center gap-2">
                        <button type="button" 
                                @click="navigator.clipboard.writeText('{{ $googleConfig['drive_folder_url'] }}'); copiedDrive = true; setTimeout(() => copiedDrive = false, 2500)"
                                class="inline-flex items-center gap-1 text-[11px] font-bold text-slate-600 hover:text-slate-900 border border-slate-300 px-2.5 py-1 rounded-none bg-slate-50 transition cursor-pointer">
                            <span x-show="!copiedDrive">📋 Salin Link Google Drive</span>
                            <span x-show="copiedDrive" class="text-amber-700 font-black">✓ Link Tersalin!</span>
                        </button>
                        @if(!empty($googleConfig['drive_folder_url']))
                        <a href="{{ $googleConfig['drive_folder_url'] }}" target="_blank" rel="noopener noreferrer"
                           class="inline-flex items-center gap-1 text-[11px] font-bold text-amber-700 hover:text-amber-900 px-2.5 py-1 border border-amber-300 bg-amber-50 rounded-none hover:underline">
                            <span>Buka Google Drive ↗</span>
                        </a>
                        @endif
                    </div>
                </div>
                <div class="relative">
                    <input type="text" name="google_drive_folder_url" 
                           value="{{ old('google_drive_folder_url', $googleConfig['drive_folder_url'] ?? '') }}"
                           placeholder="https://drive.google.com/drive/folders/1zB2loWV14fCpLWMCnNiEa4dVKAvhLZYE?usp=sharing"
                           class="w-full px-3.5 py-2.5 rounded-none border border-slate-300 font-mono text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-[#92302e]">
                </div>
                <p class="text-[11px] text-slate-500 mt-1">
                    Folder Google Drive induk untuk menyimpan ekspor file PDF resmi. Folder ini otomatis dirapikan per tahun dan per departemen sesuai standar Musashi.
                </p>
            </div>

            <!-- Field 3: Link Google Apps Script & Mode Basis Data (2 Cols) -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-1">
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-black text-slate-800 uppercase tracking-wider">
                            3. Link Google Apps Script (Web App URL / API)
                        </label>
                        @if(!empty($googleConfig['appscript_url']))
                        <a href="{{ $googleConfig['appscript_url'] }}" target="_blank" rel="noopener noreferrer"
                           class="inline-flex items-center gap-1 text-[11px] font-bold text-indigo-700 hover:text-indigo-900 hover:underline">
                            <span>Buka Web App ↗</span>
                        </a>
                        @endif
                    </div>
                    <input type="text" name="google_appscript_url" 
                           value="{{ old('google_appscript_url', $googleConfig['appscript_url'] ?? '') }}"
                           placeholder="https://script.google.com/macros/s/AKfycb.../exec"
                           class="w-full px-3.5 py-2.5 rounded-none border border-slate-300 font-mono text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-[#92302e]">
                    <p class="text-[11px] text-slate-400 mt-1">Masukkan Web App URL berakhiran <code class="text-slate-800 font-bold">/exec</code> setelah men-deploy kode Apps Script di bawah.</p>
                </div>

                <div>
                    <label class="block text-xs font-black text-slate-800 uppercase tracking-wider mb-1.5">
                        4. Mode Basis Data
                    </label>
                    <select name="google_sync_mode" 
                            class="w-full px-3.5 py-2.5 rounded-none border border-slate-300 text-xs font-bold text-slate-800 focus:outline-none focus:ring-1 focus:ring-[#92302e]">
                        <option value="hybrid" {{ ($googleConfig['sync_mode'] ?? '') === 'hybrid' ? 'selected' : '' }}>
                            Hybrid (SQLite Lokal Cepat + Sinkronisasi Google Spreadsheet) [Direkomendasikan]
                        </option>
                        <option value="sheets_live" {{ ($googleConfig['sync_mode'] ?? '') === 'sheets_live' ? 'selected' : '' }}>
                            Google Spreadsheet Live (Real-Time Cloud Database)
                        </option>
                    </select>
                    <p class="text-[11px] text-slate-400 mt-1">Mode hybrid memastikan aplikasi tetap berkinerja tinggi sekaligus tersinkron ke Cloud.</p>
                </div>
            </div>

            <!-- Form Action Footer -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-3 border-t border-slate-200">
                <div class="text-[11px] text-slate-500 space-y-0.5">
                    <p>Terakhir Sinkron: <span class="font-bold text-slate-700">{{ $googleConfig['terakhir_sinkron_google'] }}</span></p>
                    <p>Terakhir Ekspor / Push: <span class="font-bold text-slate-700">{{ $googleConfig['terakhir_push_google'] }}</span></p>
                </div>
                <div class="flex items-center gap-2">
                    <!-- Uji Koneksi Button -->
                    <button type="submit" 
                            formaction="{{ $user->isSuperadmin() ? route('superadmin.google.test') : route('admin.google.test') }}"
                            class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold rounded-none border border-slate-300 shadow-xs transition inline-flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
                        </svg>
                        <span>Uji Koneksi Link</span>
                    </button>

                    <!-- Simpan Config Button -->
                    <button type="submit" 
                            class="px-5 py-2.5 bg-[#92302e] hover:bg-[#7e2524] text-white text-xs font-black rounded-none shadow-xs transition inline-flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path>
                        </svg>
                        <span>Simpan Konfigurasi Link Baru</span>
                    </button>
                </div>
            </div>
        </form>

        <!-- Structure Info Card: Standar Penataan Folder Google Drive & Penamaan PDF -->
        <div class="bg-amber-50/70 p-4 rounded-none border border-amber-300 space-y-3">
            <div class="flex items-center gap-2">
                <span class="w-6 h-6 rounded-none bg-amber-700 text-white flex items-center justify-center font-bold text-xs">📁</span>
                <h3 class="text-xs font-black text-amber-950 uppercase tracking-wide">Standar Struktur Folder Google Drive &amp; Format Penamaan PDF</h3>
            </div>
            <p class="text-[11px] text-amber-950 leading-relaxed">
                Seluruh dokumen PDF hasil penilaian karya yang diarsipkan ke Google Drive akan otomatis ditata secara rapi dan profesional dengan format resmi PT Musashi:
            </p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                <!-- Box A: Struktur Folder -->
                <div class="bg-white p-3 rounded-none border border-amber-200 space-y-1.5 font-mono text-[11px] text-slate-800">
                    <p class="font-bold text-amber-900 font-sans text-xs">🗂️ Struktur Hierarki Folder di Google Drive:</p>
                    <div class="pl-2 border-l-2 border-amber-400 space-y-1">
                        <p class="font-bold text-slate-900">📁 Folder Induk (ID: {{ $googleConfig['drive_folder_id'] }})</p>
                        <p class="pl-3">└── 📁 TAHUN 2025</p>
                        <p class="pl-6">├── 📁 PRODUCTION 1 DEPT</p>
                        <p class="pl-6">├── 📁 PRODUCTION 2 DEPT</p>
                        <p class="pl-6">├── 📁 QA QC DEPT</p>
                        <p class="pl-6">├── 📁 ENGINEERING DEPT</p>
                        <p class="pl-6">└── 📁 HR GA FINANCE DEPT</p>
                    </div>
                </div>

                <!-- Box B: Standar Nama File PDF -->
                <div class="bg-white p-3 rounded-none border border-amber-200 space-y-1.5 text-[11px] text-slate-800">
                    <p class="font-bold text-amber-900 text-xs">📄 Format Penamaan File PDF Resmi:</p>
                    <div class="p-2 bg-slate-900 text-emerald-300 font-mono text-[11px] rounded-none">
                        PK_[TAHUN]_[NRP]_[NAMA]_[JABATAN]_[GOLONGAN].pdf
                    </div>
                    <p class="text-[11px] text-slate-600">
                        <strong>Contoh Nyata:</strong><br>
                        • <code class="text-slate-900 font-bold">PK_2025_62_SARYANTA_FOREMAN_IVA.pdf</code><br>
                        • <code class="text-slate-900 font-bold">PK_2025_100_UNTUNG_PRASETIYO_OPERATOR_IIIB.pdf</code><br>
                        • <code class="text-slate-900 font-bold">PK_2025_31_SUPARDI_DEPT_HEAD_VA.pdf</code>
                    </p>
                    <p class="text-[10px] text-slate-500 italic">
                        *Nama dibersihkan dari spasi dan karakter khusus, otomatis dikonversi ke huruf kapital rapi agar mudah dicari di bilah pencarian Google Drive.
                    </p>
                </div>
            </div>
        </div>

        <!-- Dedicated Dual Action Box: Tarik Data (Pull) & Kirim Data (Push) -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-1">
            
            <!-- Box 1: Tarik Data dari Spreadsheet / Apps Script -->
            <div class="bg-emerald-50/50 p-4 rounded-none border border-emerald-200 flex flex-col justify-between space-y-3">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="w-6 h-6 rounded-none bg-emerald-700 text-white flex items-center justify-center font-bold text-xs">↓</span>
                        <h3 class="text-xs font-black text-emerald-950 uppercase tracking-wide">Tarik / Sinkronkan dari Spreadsheet</h3>
                    </div>
                    <p class="text-[11px] text-emerald-900/80 mt-1.5 leading-relaxed">
                        Tarik pembaruan data karyawan dan status penilaian dari Google Spreadsheet / Apps Script ke dalam basis data sistem lokal.
                    </p>
                </div>
                <form method="POST" action="{{ $user->isSuperadmin() ? route('superadmin.google.sync') : route('admin.google.sync') }}"
                      onsubmit="return confirm('Mulai sinkronisasi data dari Google Spreadsheet / Google Apps Script?');">
                    @csrf
                    <input type="hidden" name="google_spreadsheet_id" value="{{ $googleConfig['spreadsheet_id'] }}">
                    <input type="hidden" name="google_appscript_url" value="{{ $googleConfig['appscript_url'] }}">
                    <button type="submit" 
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold rounded-none shadow-xs transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        <span>Sinkronkan dari Google Spreadsheet Sekarang</span>
                    </button>
                </form>
            </div>

            <!-- Box 2: Kirim / Push Data ke Google Spreadsheet -->
            <div class="bg-indigo-50/50 p-4 rounded-none border border-indigo-200 flex flex-col justify-between space-y-3">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="w-6 h-6 rounded-none bg-indigo-700 text-white flex items-center justify-center font-bold text-xs">↑</span>
                        <h3 class="text-xs font-black text-indigo-950 uppercase tracking-wide">Kirim / Push Data ke Spreadsheet</h3>
                    </div>
                    <p class="text-[11px] text-indigo-900/80 mt-1.5 leading-relaxed">
                        Kirim seluruh 180 data karyawan, 180 dokumen penilaian karya, target kuota, dan akun pengguna saat ini ke Google Spreadsheet.
                    </p>
                </div>
                <form method="POST" action="{{ $user->isSuperadmin() ? route('superadmin.google.push') : route('admin.google.push') }}"
                      onsubmit="return confirm('Kirim seluruh data database sistem ke Google Spreadsheet via Apps Script?');">
                    @csrf
                    <input type="hidden" name="google_spreadsheet_id" value="{{ $googleConfig['spreadsheet_id'] }}">
                    <input type="hidden" name="google_appscript_url" value="{{ $googleConfig['appscript_url'] }}">
                    <button type="submit" 
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 bg-indigo-700 hover:bg-indigo-800 text-white text-xs font-bold rounded-none shadow-xs transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                        <span>Kirim Database ke Google Spreadsheet</span>
                    </button>
                </form>
            </div>

        </div>

        <!-- FULL APPS SCRIPT CODE BOX WITH 1-CLICK COPY BUTTON -->
        <div class="bg-slate-900 border-2 border-slate-800 rounded-none p-5 space-y-4 text-white" x-data="{ copiedCode: false }">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-slate-800">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="w-6 h-6 rounded-none bg-[#92302e] text-white flex items-center justify-center font-bold text-xs">⚡</span>
                        <h3 class="text-sm font-black text-white tracking-wide uppercase">Kode Lengkap Google Apps Script (Siap Salin &amp; Tempel)</h3>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">
                        Kode ini sudah otomatis disematkan dengan ID Spreadsheet Anda (<code class="text-emerald-400">{{ $googleConfig['spreadsheet_id'] }}</code>) dan ID Drive Folder Anda (<code class="text-amber-400">{{ $googleConfig['drive_folder_id'] }}</code>).
                    </p>
                </div>
                <div>
                    <!-- 1-Click Copy Button -->
                    <button type="button" 
                            @click="navigator.clipboard.writeText($refs.scriptText.innerText); copiedCode = true; setTimeout(() => copiedCode = false, 3000)"
                            class="inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black rounded-none shadow-md transition cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path>
                        </svg>
                        <span x-show="!copiedCode">📋 Salin Seluruh Kode Apps Script</span>
                        <span x-show="copiedCode" class="text-amber-300 font-black">✓ Kode Berhasil Disalin ke Clipboard!</span>
                    </button>
                </div>
            </div>

            <!-- Panduan 5 Langkah Cepat -->
            <div class="bg-slate-950 p-3.5 border border-slate-800 rounded-none text-xs text-slate-300 space-y-1.5">
                <p class="font-bold text-white text-[11px] uppercase tracking-wider text-amber-400">Cara Penggunaan di Google Spreadsheet:</p>
                <ol class="list-decimal list-inside space-y-1 text-slate-300 text-[11px]">
                    <li>Klik tombol <strong class="text-white">Salin Seluruh Kode Apps Script</strong> di atas.</li>
                    <li>Buka berkas Google Spreadsheet Anda (<a href="{{ $googleConfig['spreadsheet_url'] }}" target="_blank" class="text-emerald-400 underline font-bold">Klik di sini untuk membuka spreadsheet</a>).</li>
                    <li>Pada menu atas Spreadsheet, klik <strong class="text-white">Ekstensi (Extensions)</strong> &gt; <strong class="text-white">Apps Script</strong>.</li>
                    <li>Hapus seluruh kode default yang ada di editor Apps Script, lalu <strong class="text-white">Tempel (Paste / Ctrl+V)</strong> kode ini.</li>
                    <li>Klik tombol <strong class="text-white">Simpan (Ikon Disket)</strong>, lalu klik tombol biru <strong class="text-white">Terapkan (Deploy)</strong> &gt; <strong class="text-white">Penerapan Baru (New Deployment)</strong>.</li>
                    <li>Pilih jenis <strong class="text-white">Aplikasi Web (Web App)</strong>, lalu set:
                        <br>&bull; <strong class="text-white">Jalankan sebagai (Execute as)</strong>: <code class="text-emerald-400">Saya (Me)</code>
                        <br>&bull; <strong class="text-white">Akses (Who has access)</strong>: <code class="text-emerald-400">Siapa saja (Anyone)</code>
                    </li>
                    <li>Klik <strong class="text-white">Deploy</strong>, salin tautan <strong class="text-white">URL Aplikasi Web</strong> (berakhiran <code class="text-white font-bold">/exec</code>), dan tempelkan ke kolom <strong class="text-white">Link Google Apps Script</strong> di atas, lalu klik <strong>Simpan Konfigurasi Link Baru</strong>.</li>
                </ol>
            </div>

            <!-- Code Display Block -->
            <div class="relative">
                <div class="max-h-96 overflow-y-auto p-4 bg-slate-950 rounded-none border border-slate-800 text-[11px] font-mono text-emerald-300 leading-relaxed select-all">
<pre x-ref="scriptText">{{ $googleConfig['appscript_code'] }}</pre>
                </div>
            </div>
        </div>

    </div>

</div>
@endsection
