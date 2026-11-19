@extends('layouts.app', ['title' => 'Lembar Penilaian PK - Formulir Resmi Penilaian Karya'])

@section('content')
<div class="space-y-5" x-data="lembarPenilaianPkHandler()">

    <!-- Top Header Banner (Musashi Red Banner, Solid & Clean) -->
    <div class="bg-gradient-to-r from-[#962226] via-[#92302e] to-[#781b1f] p-5 sm:p-6 text-white border border-[#781b1f] shadow-sm rounded-none">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl sm:text-2xl font-black tracking-tight text-white flex items-center gap-2">
                    <span>Lembar Penilaian PK</span>
                    <span class="text-xs font-normal text-rose-200 font-sans tracking-normal">(Formulir Resmi Penilaian Karya)</span>
                </h1>
                <p class="mt-1 text-xs sm:text-sm text-rose-100/90 leading-relaxed">
                    Formulir resmi penilaian karya yang digunakan oleh penilai dan terhubung real-time dengan Kelola Form &amp; Periode.
                </p>
            </div>

            <!-- Quick Action: Edit Settings in Kelola Form -->
            <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                <a href="{{ route('admin.pengaturanForm') }}" 
                   class="px-4 py-2.5 text-xs font-bold bg-white text-[#92302e] hover:bg-rose-50 border border-white shadow-xs hover:shadow-sm transition rounded-none flex items-center gap-2">
                    <svg class="w-4 h-4 text-[#92302e]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    <span>Ubah di Kelola Form &amp; Periode &rarr;</span>
                </a>
            </div>
        </div>

        <!-- Meta Indicators Bar -->
        <div class="mt-4 pt-3 border-t border-white/20 flex flex-wrap items-center gap-3 sm:gap-6 text-xs text-rose-100">
            <div>
                <span class="text-white/70">Periode Aktif:</span>
                <strong class="text-white ml-1 font-mono" x-text="formSettings.periode_penilaian + ' ' + formSettings.tahun_penilaian">{{ $formSettings['periode_penilaian'] }} {{ $formSettings['tahun_penilaian'] }}</strong>
            </div>
            <div class="hidden sm:block text-white/40">•</div>
            <div>
                <span class="text-white/70">Judul Dokumen:</span>
                <strong class="text-white ml-1 uppercase" x-text="formSettings.judul_form_pk">{{ $formSettings['judul_form_pk'] }}</strong>
            </div>
            <div class="hidden sm:block text-white/40">•</div>
            <div>
                <span class="text-white/70">Perusahaan:</span>
                <strong class="text-white ml-1" x-text="formSettings.nama_perusahaan">{{ $formSettings['nama_perusahaan'] }}</strong>
            </div>
            <div class="hidden sm:block text-white/40">•</div>
            <div>
                <span class="text-white/70">Status:</span>
                <span class="ml-1 px-2 py-0.2 text-[10px] font-black uppercase tracking-wider rounded-none"
                      :class="(formSettings.status_periode || 'aktif') === 'aktif' ? 'bg-emerald-400 text-emerald-950' : 'bg-amber-400 text-amber-950'"
                      x-text="(formSettings.status_periode || 'aktif').toUpperCase()">
                    {{ strtoupper($formSettings['status_periode']) }}
                </span>
            </div>
        </div>
    </div>

    <!-- Alert Sinkronisasi Otomatis Real-Time -->
    <div class="bg-amber-50 border border-amber-300 p-3 sm:p-4 text-xs text-amber-900 rounded-none flex items-start gap-3 shadow-xs">
        <span class="text-base leading-none">💡</span>
        <div class="flex-1">
            <strong class="font-bold">Sinkronisasi Real-Time &amp; Interaktif:</strong>
            Setiap perubahan parameter formulir (judul, periode, tahun, bobot aspek, sub-kriteria, petunjuk evaluasi potensi, opsi potensi, atau catatan) yang Anda simpan di 
            <a href="{{ route('admin.pengaturanForm') }}" class="font-bold underline text-[#92302e] hover:text-[#7e2524]">Kelola Form &amp; Periode</a> 
            langsung tercermin secara otomatis pada lembar formulir di bawah ini. Anda juga dapat memilih karyawan manapun dari daftar 180 data resmi untuk melihat lembar penilaian aslinya, mencoba memilih kriteria rubrik manual, atau mengunduh PDF resminya.
        </div>
    </div>

    <!-- Tab Selector: 7 Jenis Formulir yang Digunakan (Desain Siku Tegas Sesuai Standar Musashi) -->
    <div class="bg-white border border-slate-300 p-3 sm:p-4 shadow-sm rounded-none space-y-3">
        <div class="flex items-center justify-between border-b border-slate-200 pb-2">
            <h2 class="text-xs sm:text-sm font-bold text-slate-900 uppercase tracking-wide flex items-center gap-1.5">
                <span>📑</span> Pilih Jenis Formulir Penilaian Karya yang Ingin Ditampilkan:
            </h2>
            <span class="text-[11px] font-bold text-slate-500">
                Pilih tab untuk berganti jenis form
            </span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2">
            <template x-for="f in forms" :key="f.kode">
                <button type="button" 
                        @click="selectForm(f.kode)"
                        :class="selectedKode === f.kode ? 'bg-[#92302e] text-white border-[#92302e] shadow-xs' : 'bg-slate-50 text-slate-800 hover:bg-slate-100 border-slate-300'"
                        class="p-2 text-left border rounded-none transition-all cursor-pointer flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="font-mono font-black text-xs" x-text="f.kode"></span>
                        <span class="text-[8.5px] font-bold px-1 py-0.2 rounded-none"
                              :class="selectedKode === f.kode ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700'"
                              x-text="f.is_bilingual ? 'JP/ID' : 'ID'"></span>
                    </div>
                    <span class="text-[10px] font-bold mt-1 line-clamp-1" x-text="f.nama.replace('Formulir Penilaian ', '')"></span>
                    <span class="text-[9px] opacity-80 mt-0.5 block truncate" x-text="f.level"></span>
                </button>
            </template>
        </div>
    </div>

    <!-- Toolbar Detail Formulir, Pemilih Karyawan Riil, & Aksi Cepat (Desain Rapi & Elegan) -->
    <div class="bg-white border border-slate-300 shadow-sm rounded-none overflow-hidden">
        
        <!-- BAGIAN 1: Header Formulir & Tombol Aksi Dokumen Utama -->
        <div class="p-3.5 sm:p-4 bg-slate-50 border-b border-slate-200 flex flex-col md:flex-row md:items-center justify-between gap-3">
            <div class="flex items-center gap-2.5 flex-wrap min-w-0">
                <span class="px-2.5 py-1 text-xs font-mono font-black bg-[#92302e] text-white rounded-none shadow-2xs shrink-0" x-text="selectedKode"></span>
                <h2 class="text-base sm:text-lg font-black text-slate-900 tracking-tight uppercase" x-text="currentFormMeta.nama"></h2>
                <template x-if="currentFormMeta.is_bilingual && currentFormMeta.nama_jp">
                    <span class="text-xs font-medium text-slate-600 bg-slate-200/90 px-2 py-0.5 rounded-none whitespace-nowrap" x-text="currentFormMeta.nama_jp"></span>
                </template>
            </div>

            <!-- Tombol Aksi Dokumen (Buka Form Penilai & Unduh PDF) -->
            <div class="flex items-center gap-2 flex-wrap shrink-0">
                <a :href="'/penilaian/admin/dokumen/' + currentEmployee.doc_id" 
                   target="_blank" 
                   class="px-3 py-1.5 text-xs font-bold text-[#92302e] bg-rose-50 hover:bg-rose-100 border border-rose-300 rounded-none transition flex items-center gap-1.5 shadow-2xs cursor-pointer"
                   title="Buka form ini di halaman pengisian penilaian evaluator">
                    <span>📝 Buka di Form Atasan</span> &rarr;
                </a>

                <a :href="'/dokumen/' + currentEmployee.doc_id + '/pdf'" 
                   target="_blank" 
                   class="px-3.5 py-1.5 text-xs font-bold text-white bg-[#92302e] hover:bg-[#7e2524] border border-[#7e2524] rounded-none transition flex items-center gap-1.5 shadow-xs cursor-pointer"
                   title="Unduh Dokumen PDF Resmi Asli Formulir Ini">
                    <span>📄 Unduh PDF Resmi</span> &rarr;
                </a>
            </div>
        </div>

        <!-- BAGIAN 2: Informasi Metadata Formulir (Grid 3 Kolom Rapi) -->
        <div class="p-3.5 sm:px-4 bg-white border-b border-slate-200 grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
            <div class="bg-slate-50 p-2.5 border border-slate-200">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 block">Sasaran Jabatan:</span>
                <strong class="text-slate-800 font-bold text-xs mt-0.5 block leading-snug" x-text="currentFormMeta.sasaran"></strong>
            </div>
            <div class="bg-slate-50 p-2.5 border border-slate-200">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 block">Alur Penilai:</span>
                <strong class="text-slate-800 font-bold text-xs mt-0.5 block leading-snug" x-text="currentFormMeta.penilai_1 + ' → ' + currentFormMeta.penilai_2 + ' → ' + currentFormMeta.pengesahan"></strong>
            </div>
            <div class="bg-slate-50 p-2.5 border border-slate-200">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 block">Format File Template:</span>
                <strong class="text-slate-800 font-mono font-bold text-xs mt-0.5 block leading-snug" x-text="currentFormMeta.format"></strong>
            </div>
        </div>

        <!-- BAGIAN 3: Control Bar (Pemilih Karyawan Riil + Simulasi Nilai & TTD) -->
        <div class="p-3.5 sm:px-4 bg-slate-50/80 border-b border-slate-200 flex flex-col lg:flex-row lg:items-center justify-between gap-3">
            
            <!-- Pemilih Karyawan Riil -->
            <div class="flex items-center gap-2 flex-1 min-w-0 max-w-2xl">
                <label class="text-xs font-bold text-slate-700 uppercase tracking-wide shrink-0 flex items-center gap-1">
                    <span>👤</span> Karyawan:
                </label>
                <select x-model="selectedDocId" 
                        @change="onSelectEmployee($event.target.value)"
                        class="w-full text-xs font-bold text-slate-900 bg-white border border-slate-300 px-3 py-1.5 rounded-none outline-none focus:ring-1 focus:ring-[#92302e] shadow-2xs truncate cursor-pointer">
                    <template x-for="emp in currentFormEmployees" :key="emp.doc_id">
                        <option :value="emp.doc_id" 
                                x-text="emp.nrp + ' - ' + emp.nama + ' (' + emp.jabatan + ' - ' + emp.seksi + ')'"
                                :selected="emp.doc_id === currentEmployee.doc_id"></option>
                    </template>
                </select>
            </div>

            <!-- Kontrol Simulasi (Simulasi TTD, Isi Contoh Nilai, Kosongkan) -->
            <div class="flex items-center gap-2 flex-wrap shrink-0">
                <label class="flex items-center gap-1.5 text-xs font-bold text-slate-700 cursor-pointer bg-white hover:bg-slate-100 border border-slate-300 px-3 py-1.5 rounded-none select-none transition shadow-2xs">
                    <input type="checkbox" x-model="simulasiTtd" class="w-3.5 h-3.5 text-[#92302e] rounded-none focus:ring-0">
                    <span>Simulasi TTD</span>
                </label>

                <button type="button" 
                        @click="fillSampleScores()" 
                        class="text-xs font-bold text-slate-800 bg-white hover:bg-emerald-50 hover:border-emerald-500 border border-slate-300 px-3 py-1.5 rounded-none transition flex items-center gap-1.5 shadow-2xs cursor-pointer"
                        title="Isi contoh skor untuk melihat simulasi kalkulasi subtotal dan grand total">
                    <span class="text-amber-500">⚡</span> Isi Contoh Nilai
                </button>

                <button type="button" 
                        @click="resetScores()" 
                        class="text-xs font-bold text-slate-600 bg-white hover:bg-rose-50 hover:border-rose-400 hover:text-rose-700 border border-slate-300 px-2.5 py-1.5 rounded-none transition flex items-center gap-1 shadow-2xs cursor-pointer"
                        title="Kosongkan nilai aspek untuk melihat tampilan default">
                    <span>↺</span> Kosongkan
                </button>
            </div>
        </div>

        <!-- BAGIAN 4: Footer Bar (Komposisi Bobot & Total Karyawan) -->
        <div class="p-3 sm:px-4 bg-white flex flex-wrap items-center justify-between gap-3 text-xs">
            <div class="flex flex-wrap items-center gap-2">
                <span class="font-black text-slate-600 uppercase text-[10.5px] tracking-wider">Komposisi Bobot Aktif:</span>
                <template x-for="(group, gKey) in (activeStructure ? activeStructure.groups : {})" :key="gKey">
                    <span class="px-2.5 py-1 bg-slate-100 border border-slate-300 text-slate-800 font-bold rounded-none flex items-center gap-1.5">
                        <span x-text="group.name"></span>
                        <span class="px-1.5 py-0.2 bg-[#92302e] text-white text-[10px] font-mono font-black" x-text="group.weight + '%'"></span>
                    </span>
                </template>
            </div>
            <div class="text-[11px] text-slate-500 font-mono">
                Total Karyawan Terdaftar Form <span class="font-bold text-slate-800" x-text="selectedKode"></span>: <strong class="text-[#92302e]" x-text="currentFormEmployees.length"></strong> Orang
            </div>
        </div>

    </div>

    <!-- ========================================================================= -->
    <!-- WUJUD ASLI LEMBAR FORMULIR PENILAIAN KARYA (FULL-WIDTH RESMI MUSASHI)     -->
    <!-- IDENTIK DENGAN TAMPILAN DI KELOLA FORM & SAAT PENILAI MELAKUKAN PENILAIAN -->
    <!-- ========================================================================= -->
    <div id="officialFormSheet" class="bg-white border-2 border-slate-900 shadow-xl rounded-none p-4 sm:p-6 overflow-x-auto text-slate-900">
        
        <!-- Top Form Code Tag -->
        <div class="flex items-center justify-between mb-2">
            <div class="text-xs font-bold font-mono text-slate-800" x-text="getFormCodeLabel()">
                Code PDV Mapin - 07-001
            </div>
            <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">
                PT MUSASHI AUTO PARTS INDONESIA • OFFICIAL APPRAISAL FORM
            </div>
        </div>

        <!-- Outer Paper Sheet (Lebar Penuh, Format Persis Excel Musashi) -->
        <div class="bg-white border-2 border-slate-900 shadow-xs text-slate-900 min-w-[780px] text-[10px]">
            
            <!-- 1. HEADER TABLE (5 ROWS, 8 COLUMNS) - DINAMIS MENGIKUTI FORM & TTD -->
            <table class="w-full border-collapse border-2 border-slate-900 text-[10px] bg-white">
                <colgroup>
                    <col style="width: 17%;">
                    <col style="width: 13%;">
                    <col style="width: 17%;">
                    <col style="width: 15%;">
                    <col style="width: 15%;">
                    <col style="width: 7.6%;">
                    <col style="width: 7.7%;">
                    <col style="width: 7.7%;">
                </colgroup>

                <!-- ROW 1 -->
                <tr>
                    <!-- Col 1: Musashi Logo + RAHASIA + Periode + Tahun (Rowspan 5) -->
                    <td rowspan="5" class="border-r-2 border-b-2 border-slate-900 p-2.5 align-top bg-white">
                        <div class="flex flex-col justify-between h-full min-h-[120px]">
                            <div>
                                <div class="mb-2">
                                    <img src="{{ asset('logomusashi.png') }}" alt="MUSASHI" class="h-7 max-w-full object-contain">
                                </div>
                                <div class="border border-slate-900 text-center py-0.5 font-bold tracking-wider text-slate-900 bg-white mb-2 text-[9px]" x-text="formSettings.label_kerahasiaan">
                                    {{ $formSettings['label_kerahasiaan'] }}
                                </div>
                            </div>
                            <div class="space-y-0.5 text-[8.5px] text-slate-700 border-t border-slate-200 pt-1.5">
                                <div class="flex justify-between">
                                    <span class="font-semibold" x-text="currentFormMeta.is_bilingual ? 'PERIODE 期間 :' : 'Periode :'">Periode :</span>
                                    <span class="font-bold text-slate-900" x-text="formSettings.periode_penilaian">{{ $formSettings['periode_penilaian'] }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="font-semibold" x-text="currentFormMeta.is_bilingual ? 'TAHUN 年 :' : 'Tahun :'">Tahun :</span>
                                    <span class="font-bold font-mono text-[#92302e]" x-text="formSettings.tahun_penilaian">{{ $formSettings['tahun_penilaian'] }}</span>
                                </div>
                            </div>
                        </div>
                    </td>

                    <!-- Col 2: NAMA LENGKAP -->
                    <td class="px-2.5 py-1.5 font-semibold text-slate-700 bg-white border-r border-b border-slate-900 align-middle text-[9px]">
                        <span x-text="currentFormMeta.is_bilingual ? 'NAMA / 氏名 :' : 'NAMA LENGKAP :'"></span>
                    </td>
                    <!-- Col 3: NAMA KARYAWAN AKTIF -->
                    <td class="px-2.5 py-1.5 font-bold text-slate-900 uppercase bg-[#e2efda] border-r-2 border-b border-slate-900 align-middle text-[9.5px]"
                        x-text="currentEmployee.nama">
                        NUR EKA PURNAMA
                    </td>

                    <!-- Col 4 & 5: PENILAIAN KARYA Title -->
                    <!-- Bilingual: Rowspan 2 (no KJ banner) -->
                    <template x-if="currentFormMeta.is_bilingual">
                        <td colspan="2" rowspan="2" class="py-1.5 px-2 text-center bg-white border-r-2 border-b border-slate-900 align-middle">
                            <h4 class="text-xs sm:text-sm font-black tracking-wide text-slate-900 uppercase leading-tight" 
                                x-text="formSettings.judul_form_pk + ' / 仕事の評価'">
                                PENILAIAN KARYA / 仕事の評価
                            </h4>
                        </td>
                    </template>
                    <!-- Non-Bilingual: Rowspan 1 -->
                    <template x-if="!currentFormMeta.is_bilingual">
                        <td colspan="2" class="py-1.5 px-2 text-center bg-white border-r-2 border-b border-slate-900 align-middle">
                            <h4 class="text-xs sm:text-sm font-black tracking-wide text-slate-900 uppercase leading-tight" 
                                x-text="formSettings.judul_form_pk">
                                PENILAIAN KARYA
                            </h4>
                        </td>
                    </template>

                    <!-- Col 6, 7, 8: Signatures Header (Always 3 Distinct Columns) -->
                    <!-- Col 6 Header -->
                    <td class="bg-[#d9d9d9] border-b border-r border-slate-900 font-bold text-[8.5px] text-center p-1 text-slate-900 align-middle leading-tight"
                        :class="currentFormMeta.ttd_config && currentFormMeta.ttd_config.col1.is_blocked ? 'bg-[#bfbfbf]' : 'bg-[#d9d9d9]'">
                        <span x-text="currentFormMeta.ttd_config ? currentFormMeta.ttd_config.col1.header : 'Foreman'"></span>
                        <span x-show="currentFormMeta.ttd_config && currentFormMeta.ttd_config.col1.sub" 
                              class="block text-[7.5px] text-slate-700 font-medium" 
                              x-text="currentFormMeta.ttd_config ? currentFormMeta.ttd_config.col1.sub : ''"></span>
                    </td>

                    <!-- Col 7 Header -->
                    <template x-if="currentFormMeta.ttd_config && currentFormMeta.ttd_config.col2.is_blocked">
                        <td class="bg-[#bfbfbf] border-b border-r border-slate-900"></td>
                    </template>
                    <template x-if="!currentFormMeta.ttd_config || !currentFormMeta.ttd_config.col2.is_blocked">
                        <td class="bg-[#d9d9d9] border-b border-r border-slate-900 font-bold text-[8.5px] text-center p-1 text-slate-900 align-middle leading-tight">
                            <span x-text="currentFormMeta.ttd_config ? currentFormMeta.ttd_config.col2.header : 'Section Head'"></span>
                            <span x-show="currentFormMeta.ttd_config && currentFormMeta.ttd_config.col2.sub" 
                                  class="block text-[7.5px] text-slate-700 font-medium" 
                                  x-text="currentFormMeta.ttd_config ? currentFormMeta.ttd_config.col2.sub : ''"></span>
                        </td>
                    </template>

                    <!-- Col 8 Header -->
                    <template x-if="currentFormMeta.ttd_config && currentFormMeta.ttd_config.col3.is_blocked">
                        <td class="bg-[#bfbfbf] border-b border-slate-900"></td>
                    </template>
                    <template x-if="!currentFormMeta.ttd_config || !currentFormMeta.ttd_config.col3.is_blocked">
                        <td class="bg-[#d9d9d9] border-b border-slate-900 font-bold text-[8.5px] text-center p-1 text-slate-900 align-middle leading-tight">
                            <span x-text="currentFormMeta.ttd_config ? currentFormMeta.ttd_config.col3.header : 'Department Head'"></span>
                            <span x-show="currentFormMeta.ttd_config && currentFormMeta.ttd_config.col3.sub" 
                                  class="block text-[7.5px] text-slate-700 font-medium" 
                                  x-text="currentFormMeta.ttd_config ? currentFormMeta.ttd_config.col3.sub : ''"></span>
                        </td>
                    </template>
                </tr>

                <!-- ROW 2 -->
                <tr>
                    <!-- Col 2: NRP -->
                    <td class="px-2.5 py-1.5 font-semibold text-slate-700 bg-white border-r border-b border-slate-900 align-middle text-[9px]">
                        NRP :
                    </td>
                    <td class="px-2.5 py-1.5 font-mono font-bold text-slate-900 bg-[#e2efda] border-r-2 border-b border-slate-900 align-middle text-[9.5px]"
                        x-text="currentEmployee.nrp">
                        39
                    </td>

                    <!-- Col 4 & 5: Banner Jenjang / KJ (ONLY FOR NON-BILINGUAL GOL 1-3) -->
                    <template x-if="!currentFormMeta.is_bilingual">
                        <td colspan="2" class="bg-[#00e000] text-center font-black text-slate-950 py-1.5 border-r-2 border-b border-slate-900 align-middle text-xs sm:text-sm tracking-wider"
                            x-text="getBannerTitle()">
                            KJ : 1 - 3
                        </td>
                    </template>

                    <!-- Col 6, 7, 8: Signature Boxes -->
                    <!-- Col 6 TTD Box -->
                    <template x-if="currentFormMeta.ttd_config && currentFormMeta.ttd_config.col1.is_blocked">
                        <td rowspan="2" class="bg-[#bfbfbf] border-b border-r border-slate-900"></td>
                    </template>
                    <template x-if="!currentFormMeta.ttd_config || !currentFormMeta.ttd_config.col1.is_blocked">
                        <td rowspan="2" class="border-b border-r border-slate-900 text-center align-middle p-1 bg-white text-[8px]">
                            <template x-if="simulasiTtd">
                                <div class="space-y-0.5">
                                    <span class="inline-block px-1.5 py-0.2 bg-emerald-100 text-emerald-800 border border-emerald-400 font-black text-[7.5px] tracking-wide">✓ TTD DIGITAL</span>
                                    <span class="block text-[7px] text-slate-600 font-semibold truncate" x-text="getSignerName(1)"></span>
                                </div>
                            </template>
                            <template x-if="!simulasiTtd">
                                <span class="text-slate-400 italic">Belum TTD</span>
                            </template>
                        </td>
                    </template>

                    <!-- Col 7 TTD Box -->
                    <template x-if="currentFormMeta.ttd_config && currentFormMeta.ttd_config.col2.is_blocked">
                        <td rowspan="2" class="bg-[#bfbfbf] border-b border-r border-slate-900"></td>
                    </template>
                    <template x-if="!currentFormMeta.ttd_config || !currentFormMeta.ttd_config.col2.is_blocked">
                        <td rowspan="2" class="border-b border-r border-slate-900 text-center align-middle p-1 bg-white text-[8px]">
                            <template x-if="simulasiTtd">
                                <div class="space-y-0.5">
                                    <span class="inline-block px-1.5 py-0.2 bg-emerald-100 text-emerald-800 border border-emerald-400 font-black text-[7.5px] tracking-wide">✓ TTD DIGITAL</span>
                                    <span class="block text-[7px] text-slate-600 font-semibold truncate" x-text="getSignerName(2)"></span>
                                </div>
                            </template>
                            <template x-if="!simulasiTtd">
                                <span class="text-slate-400 italic">Belum TTD</span>
                            </template>
                        </td>
                    </template>

                    <!-- Col 8 TTD Box -->
                    <template x-if="currentFormMeta.ttd_config && currentFormMeta.ttd_config.col3.is_blocked">
                        <td rowspan="2" class="bg-[#bfbfbf] border-b border-slate-900"></td>
                    </template>
                    <template x-if="!currentFormMeta.ttd_config || !currentFormMeta.ttd_config.col3.is_blocked">
                        <td rowspan="2" class="border-b border-slate-900 text-center align-middle p-1 bg-white text-[8px]">
                            <template x-if="simulasiTtd">
                                <div class="space-y-0.5">
                                    <span class="inline-block px-1.5 py-0.2 bg-emerald-100 text-emerald-800 border border-emerald-400 font-black text-[7.5px] tracking-wide">✓ TTD DIGITAL</span>
                                    <span class="block text-[7px] text-slate-600 font-semibold truncate" x-text="getSignerName(3)"></span>
                                </div>
                            </template>
                            <template x-if="!simulasiTtd">
                                <span class="text-slate-400 italic">Belum TTD</span>
                            </template>
                        </td>
                    </template>
                </tr>

                <!-- ROW 3 -->
                <tr>
                    <!-- Col 2: GOLONGAN -->
                    <td class="px-2.5 py-1.5 font-semibold text-slate-700 bg-white border-r border-b border-slate-900 align-middle text-[9px]">
                        <span x-text="currentFormMeta.is_bilingual ? 'GOLONGAN / 役 :' : 'GOLONGAN :'"></span>
                    </td>
                    <td class="px-2.5 py-1.5 font-bold text-slate-900 bg-[#e2efda] border-r-2 border-b border-slate-900 align-middle text-[9.5px]"
                        x-text="currentEmployee.golongan">
                        IIIB
                    </td>

                    <!-- Col 4 & 5: Subtitle boxes Seksi & Departemen -->
                    <template x-if="selectedKode === '07-006-DIV'">
                        <td colspan="2" class="text-center font-bold text-[8.5px] uppercase text-slate-600 border-r-2 border-b border-slate-900 p-0.5">
                            DIVISION
                        </td>
                    </template>
                    <template x-if="selectedKode !== '07-006-DIV'">
                        <td class="text-center font-bold text-[8.5px] uppercase text-slate-600 border-r border-b border-slate-900 p-0.5">
                            <span x-text="currentFormMeta.is_bilingual ? (selectedKode === '07-006-DH' ? 'DEPARTEMEN / 部門' : 'SECTION / セクション') : 'Seksi'"></span>
                        </td>
                    </template>
                    <template x-if="selectedKode !== '07-006-DIV'">
                        <td class="text-center font-bold text-[8.5px] uppercase text-slate-600 border-r-2 border-b border-slate-900 p-0.5">
                            <span x-text="currentFormMeta.is_bilingual ? (selectedKode === '07-006-DH' ? 'DIVISION' : 'DEPARTEMEN / 部門') : 'Departemen'"></span>
                        </td>
                    </template>
                </tr>

                <!-- ROW 4 -->
                <tr>
                    <!-- Col 2: JABATAN -->
                    <td class="px-2.5 py-1.5 font-semibold text-slate-700 bg-white border-r border-b border-slate-900 align-middle text-[9px]">
                        <span x-text="currentFormMeta.is_bilingual ? 'JABATAN / 職位 :' : 'JABATAN :'"></span>
                    </td>
                    <td class="px-2.5 py-1.5 font-bold text-slate-900 uppercase bg-[#e2efda] border-r-2 border-b border-slate-900 align-middle text-[9px]"
                        x-text="currentEmployee.jabatan">
                        CONTROLLER
                    </td>

                    <!-- Col 4 & 5: Seksi & Departemen Values (Rowspan 2) -->
                    <template x-if="selectedKode === '07-006-DIV'">
                        <td colspan="2" rowspan="2" class="border-r-2 border-b-2 border-slate-900 text-center font-bold text-[9px] uppercase align-middle bg-white p-1"
                            x-text="currentEmployee.departemen || 'PRODUCTION A DIV'">
                            PRODUCTION A DIV
                        </td>
                    </template>
                    <template x-if="selectedKode !== '07-006-DIV'">
                        <td rowspan="2" class="border-r border-b-2 border-slate-900 text-center font-bold text-[9px] uppercase align-middle bg-white p-1"
                            x-text="currentEmployee.seksi">
                            FACTORY 1
                        </td>
                    </template>
                    <template x-if="selectedKode !== '07-006-DIV'">
                        <td rowspan="2" class="border-r-2 border-b-2 border-slate-900 text-center font-bold text-[9px] uppercase align-middle bg-white p-1"
                            x-text="currentEmployee.departemen">
                            PROD 2
                        </td>
                    </template>

                    <!-- Col 6, 7, 8: Signer Names & Roles (Always 3 Distinct Columns) -->
                    <!-- Col 6 Signer -->
                    <template x-if="currentFormMeta.ttd_config && currentFormMeta.ttd_config.col1.is_blocked">
                        <td rowspan="2" class="bg-[#bfbfbf] border-r border-b-2 border-slate-900"></td>
                    </template>
                    <template x-if="!currentFormMeta.ttd_config || !currentFormMeta.ttd_config.col1.is_blocked">
                        <td rowspan="2" class="border-r border-b-2 border-slate-900 text-center align-middle p-1 bg-white">
                            <div class="font-bold text-[8px] text-slate-900 truncate" x-text="getSignerName(1)"></div>
                            <div class="text-[7px] text-slate-500 font-medium" x-text="currentFormMeta.ttd_config ? currentFormMeta.ttd_config.col1.role : ''"></div>
                        </td>
                    </template>

                    <!-- Col 7 Signer -->
                    <template x-if="currentFormMeta.ttd_config && currentFormMeta.ttd_config.col2.is_blocked">
                        <td rowspan="2" class="bg-[#bfbfbf] border-r border-b-2 border-slate-900"></td>
                    </template>
                    <template x-if="!currentFormMeta.ttd_config || !currentFormMeta.ttd_config.col2.is_blocked">
                        <td rowspan="2" class="border-r border-b-2 border-slate-900 text-center align-middle p-1 bg-white">
                            <div class="font-bold text-[8px] text-slate-900 truncate" x-text="getSignerName(2)"></div>
                            <div class="text-[7px] text-slate-500 font-medium" x-text="currentFormMeta.ttd_config ? currentFormMeta.ttd_config.col2.role : ''"></div>
                        </td>
                    </template>

                    <!-- Col 8 Signer -->
                    <template x-if="currentFormMeta.ttd_config && currentFormMeta.ttd_config.col3.is_blocked">
                        <td rowspan="2" class="bg-[#bfbfbf] border-b-2 border-slate-900"></td>
                    </template>
                    <template x-if="!currentFormMeta.ttd_config || !currentFormMeta.ttd_config.col3.is_blocked">
                        <td rowspan="2" class="border-b-2 border-slate-900 text-center align-middle p-1 bg-white">
                            <div class="font-bold text-[8px] text-slate-900 truncate" x-text="getSignerName(3)"></div>
                            <div class="text-[7px] text-slate-500 font-medium" x-text="currentFormMeta.ttd_config ? currentFormMeta.ttd_config.col3.role : ''"></div>
                        </td>
                    </template>
                </tr>

                <!-- ROW 5 -->
                <tr>
                    <!-- Col 2: TANGGAL MASUK -->
                    <td class="px-2.5 py-1.5 font-semibold text-slate-700 bg-white border-r border-b-2 border-slate-900 align-middle text-[9px]">
                        <span x-text="currentFormMeta.is_bilingual ? 'TANGGAL MASUK / 入社 :' : 'TANGGAL MASUK :'"></span>
                    </td>
                    <td class="px-2.5 py-1.5 font-mono font-bold text-slate-900 bg-[#e2efda] border-r-2 border-b-2 border-slate-900 align-middle text-[9.5px]"
                        x-text="currentEmployee.tgl_masuk">
                        1996-05-08
                    </td>
                </tr>
            </table>

            <!-- 2. MAIN BODY (SPLIT 2 COLUMNS: ASPEK PENILAIAN KIRI, INDIKATOR POTENSI KANAN) -->
            <div class="grid grid-cols-12 border-2 border-t-0 border-slate-900 bg-white">
                
                <!-- ========================================================= -->
                <!-- BODY LEFT: ASPEK PENILAIAN + TANGGAL + CATATAN + EQUATION -->
                <!-- ========================================================= -->
                <div class="col-span-12 lg:col-span-7 border-r-0 lg:border-r-2 border-slate-900 flex flex-col justify-between">
                    
                    <!-- Tabel Aspek Penilaian Lengkap dengan Subtotal, Rata-Rata & Nilai Akhir Bobot -->
                    <div>
                        <table class="w-full border-collapse text-[9.5px]">
                            <thead>
                                <tr class="bg-[#d1d5db] font-bold text-center border-b border-slate-900">
                                    <th colspan="2" rowspan="2" class="w-[58%] p-1.5 border-r border-slate-900 uppercase text-[9.5px]">
                                        <span x-text="currentFormMeta.is_bilingual ? 'ASPEK PENILAIAN / 評価項目' : 'ASPEK PENILAIAN'">ASPEK PENILAIAN</span>
                                    </th>
                                    <th colspan="3" class="w-[42%] p-1 border-b border-slate-900 uppercase text-[9px]">
                                        <span x-text="currentFormMeta.is_bilingual ? 'PENILAIAN / 点数' : 'PENILAIAN'">PENILAIAN</span>
                                    </th>
                                </tr>
                                <tr class="bg-[#d9d9d9] font-bold text-center border-b-2 border-slate-900 text-[8.5px] leading-tight">
                                    <!-- Col 1 Header -->
                                    <th class="w-[14%] p-1 border-r border-slate-900" 
                                        :class="getColHeaderClass(1)"
                                        x-text="currentFormMeta.ttd_config ? currentFormMeta.ttd_config.penilai_1_col : 'Foreman (jika ada)'">
                                    </th>

                                    <!-- Col 2 Header -->
                                    <th class="w-[14%] p-1 border-r border-slate-900"
                                        :class="getColHeaderClass(2)"
                                        x-text="currentFormMeta.ttd_config ? currentFormMeta.ttd_config.penilai_2_col : 'Section Head / Deputy Section Head'">
                                    </th>

                                    <!-- Col 3 Header -->
                                    <th class="w-[14%] p-1" 
                                        :class="getColHeaderClass(3)"
                                        x-text="currentFormMeta.ttd_config ? currentFormMeta.ttd_config.penilai_3_col : 'Dept. Head (Final)'">
                                    </th>
                                </tr>
                            </thead>

                            <template x-for="(group, gKey) in (activeStructure ? activeStructure.groups : {})" :key="gKey">
                                <tbody class="border-b-2 border-slate-900">
                                    <!-- Loop aspect criteria in this group -->
                                    <template x-for="(entry, index) in Object.entries(group.aspects || {})" :key="entry[0]">
                                        <tr class="border-b border-slate-300 hover:bg-slate-50 transition-colors">
                                            
                                            <!-- Group Title with rowspan (ONLY ON FIRST ROW) -->
                                            <template x-if="index === 0">
                                                <td :rowspan="Object.keys(group.aspects).length" 
                                                    class="w-[24%] p-2 font-black text-center align-middle border-r border-slate-900 bg-white uppercase text-[9.5px]">
                                                    <div class="leading-tight" x-text="group.name"></div>
                                                    <span class="block text-[8.5px] font-mono font-bold text-[#92302e] mt-1" x-text="'(' + group.weight + '%)'"></span>
                                                </td>
                                            </template>

                                            <!-- Criterion Name -->
                                            <td class="w-[34%] px-2.5 py-1.5 font-medium border-r border-slate-900 bg-white text-[9px] leading-tight text-slate-900">
                                                <span x-text="entry[1]"></span>
                                                <!-- Rubrik Deskripsi Kalimat Terpilih (jika ada nilai) -->
                                                <template x-if="scores[entry[0]] && kriteriaMap[entry[0]] && kriteriaMap[entry[0]][scores[entry[0]]]">
                                                    <span class="text-[8px] text-emerald-800 italic block mt-0.5 leading-snug font-sans bg-emerald-50 px-1 py-0.2 rounded border border-emerald-200"
                                                          x-text="'“' + kriteriaMap[entry[0]][scores[entry[0]]] + '”'"></span>
                                                </template>
                                            </td>

                                            <!-- 3 Score Columns (Dinamis: Button / Dash / Blocked Dark Gray) -->
                                            <!-- Col 1 -->
                                            <template x-if="isColBlocked(1)">
                                                <td class="w-[14%] p-1 border-r border-slate-900 bg-[#bfbfbf]"></td>
                                            </template>
                                            <template x-if="!isColBlocked(1) && isColInteractive(1)">
                                                <td class="w-[14%] p-1 text-center align-middle border-r border-slate-900 bg-[#e2efda]">
                                                    <button type="button" 
                                                            @click="openRubrikModal(entry[0], entry[1])"
                                                            class="w-full py-1 px-1.5 border border-emerald-600 bg-white text-emerald-800 hover:bg-emerald-50 rounded-none shadow-2xs text-[8.5px] font-bold flex items-center justify-between gap-1 cursor-pointer transition">
                                                        <span class="font-mono text-sm font-black text-slate-950" x-text="scores[entry[0]] || '-'"></span>
                                                        <span class="text-[7px] text-emerald-700" x-text="scores[entry[0]] ? 'Ubah ▼' : 'Pilih ▼'"></span>
                                                    </button>
                                                </td>
                                            </template>
                                            <template x-if="!isColBlocked(1) && !isColInteractive(1)">
                                                <td class="w-[14%] p-1 text-center font-mono font-bold border-r border-slate-900 bg-white text-slate-400 text-[9px]">
                                                    <span x-text="scores[entry[0]] || '-'"></span>
                                                </td>
                                            </template>

                                            <!-- Col 2 -->
                                            <template x-if="isColBlocked(2)">
                                                <td class="w-[14%] p-1 border-r border-slate-900 bg-[#bfbfbf]"></td>
                                            </template>
                                            <template x-if="!isColBlocked(2) && isColInteractive(2)">
                                                <td class="w-[14%] p-1 text-center align-middle border-r border-slate-900 bg-[#e2efda]">
                                                    <button type="button" 
                                                            @click="openRubrikModal(entry[0], entry[1])"
                                                            class="w-full py-1 px-1.5 border border-emerald-600 bg-white text-emerald-800 hover:bg-emerald-50 rounded-none shadow-2xs text-[8.5px] font-bold flex items-center justify-between gap-1 cursor-pointer transition">
                                                        <span class="font-mono text-sm font-black text-slate-950" x-text="scores[entry[0]] || '-'"></span>
                                                        <span class="text-[7px] text-emerald-700" x-text="scores[entry[0]] ? 'Ubah ▼' : 'Pilih ▼'"></span>
                                                    </button>
                                                </td>
                                            </template>
                                            <template x-if="!isColBlocked(2) && !isColInteractive(2)">
                                                <td class="w-[14%] p-1 text-center font-mono font-bold border-r border-slate-900 bg-white text-slate-400 text-[9px]">
                                                    <span x-text="scores[entry[0]] || '-'"></span>
                                                </td>
                                            </template>

                                            <!-- Col 3 -->
                                            <template x-if="isColBlocked(3)">
                                                <td class="w-[14%] p-1 bg-[#bfbfbf]"></td>
                                            </template>
                                            <template x-if="!isColBlocked(3) && isColInteractive(3)">
                                                <td class="w-[14%] p-1 text-center align-middle bg-[#e2efda]">
                                                    <button type="button" 
                                                            @click="openRubrikModal(entry[0], entry[1])"
                                                            class="w-full py-1 px-1.5 border border-emerald-600 bg-white text-emerald-800 hover:bg-emerald-50 rounded-none shadow-2xs text-[8.5px] font-bold flex items-center justify-between gap-1 cursor-pointer transition">
                                                        <span class="font-mono text-sm font-black text-slate-950" x-text="scores[entry[0]] || '-'"></span>
                                                        <span class="text-[7px] text-emerald-700" x-text="scores[entry[0]] ? 'Ubah ▼' : 'Pilih ▼'"></span>
                                                    </button>
                                                </td>
                                            </template>
                                            <template x-if="!isColBlocked(3) && !isColInteractive(3)">
                                                <td class="w-[14%] p-1 text-center font-mono font-bold bg-white text-slate-400 text-[9px]">
                                                    <span x-text="scores[entry[0]] || '-'"></span>
                                                </td>
                                            </template>
                                        </tr>
                                    </template>

                                    <!-- 1. GROUP TOTAL ROW (SUM) -->
                                    <tr class="border-t border-slate-900 bg-white font-bold text-[9px]">
                                        <td colspan="2" class="p-1.5 text-right uppercase border-r border-slate-900 text-slate-800">
                                            <span x-text="currentFormMeta.is_bilingual ? 'TOTAL 合計' : 'TOTAL'">TOTAL</span>
                                        </td>
                                        <template x-if="isColBlocked(1)"><td class="p-1 border-r border-slate-900 bg-[#bfbfbf]"></td></template>
                                        <template x-if="!isColBlocked(1)"><td class="p-1 text-center font-mono font-bold border-r border-slate-900" x-text="getGroupSum(gKey)">0</td></template>

                                        <template x-if="isColBlocked(2)"><td class="p-1 border-r border-slate-900 bg-[#bfbfbf]"></td></template>
                                        <template x-if="!isColBlocked(2)"><td class="p-1 text-center font-mono font-bold border-r border-slate-900" x-text="getGroupSum(gKey)">0</td></template>

                                        <template x-if="isColBlocked(3)"><td class="p-1 bg-[#bfbfbf]"></td></template>
                                        <template x-if="!isColBlocked(3)"><td class="p-1 text-center font-mono font-bold" x-text="getGroupSum(gKey)">0</td></template>
                                    </tr>

                                    <!-- 2. GROUP RATA - RATA ROW (AVERAGE) -->
                                    <tr class="border-t border-slate-300 bg-white font-bold text-[8.5px]">
                                        <td colspan="2" class="p-1.5 text-left border-r border-slate-900 text-slate-700">
                                            <span x-text="getRataTitle(group, gKey)"></span>
                                        </td>
                                        <template x-if="isColBlocked(1)"><td class="p-1 border-r border-slate-900 bg-[#bfbfbf]"></td></template>
                                        <template x-if="!isColBlocked(1)"><td class="p-1 text-center font-mono border-r border-slate-900 text-slate-900" x-text="getGroupAvg(gKey)">0.00</td></template>

                                        <template x-if="isColBlocked(2)"><td class="p-1 border-r border-slate-900 bg-[#bfbfbf]"></td></template>
                                        <template x-if="!isColBlocked(2)"><td class="p-1 text-center font-mono border-r border-slate-900 text-slate-900" x-text="getGroupAvg(gKey)">0.00</td></template>

                                        <template x-if="isColBlocked(3)"><td class="p-1 bg-[#bfbfbf]"></td></template>
                                        <template x-if="!isColBlocked(3)"><td class="p-1 text-center font-mono text-slate-900" x-text="getGroupAvg(gKey)">0.00</td></template>
                                    </tr>

                                    <!-- 3. GROUP NILAI AKHIR ROW (WEIGHTED %) -->
                                    <tr class="border-t border-slate-300 bg-slate-50 font-black text-[8.5px]">
                                        <td colspan="2" class="p-1.5 text-left border-r border-slate-900 text-slate-900">
                                            <span x-text="getNilaiAkhirTitle(group, gKey)"></span>
                                        </td>
                                        <template x-if="isColBlocked(1)"><td class="p-1 border-r border-slate-900 bg-[#bfbfbf]"></td></template>
                                        <template x-if="!isColBlocked(1)"><td class="p-1 text-center font-mono font-black border-r border-slate-900 bg-slate-100 text-slate-950" x-text="getGroupWeighted(gKey)">0.00</td></template>

                                        <template x-if="isColBlocked(2)"><td class="p-1 border-r border-slate-900 bg-[#bfbfbf]"></td></template>
                                        <template x-if="!isColBlocked(2)"><td class="p-1 text-center font-mono font-black border-r border-slate-900 bg-slate-100 text-slate-950" x-text="getGroupWeighted(gKey)">0.00</td></template>

                                        <template x-if="isColBlocked(3)"><td class="p-1 bg-[#bfbfbf]"></td></template>
                                        <template x-if="!isColBlocked(3)"><td class="p-1 text-center font-mono font-black bg-slate-100 text-slate-950" x-text="getGroupWeighted(gKey)">0.00</td></template>
                                    </tr>
                                </tbody>
                            </template>

                        </table>
                    </div>

                    <!-- Footer Bagian Kiri: Tanggal Dokumen, Catatan Bawah & Equation Box -->
                    <div class="p-3 border-t-2 border-slate-900 bg-white space-y-2.5">
                        <div class="text-[9.5px] text-slate-500 font-mono flex justify-between">
                            <span><strong class="text-slate-700">Tanggal Penilaian:</strong> <span x-text="formatDateIndo(formSettings.tanggal_dokumen || formSettings.tgl_mulai)"></span></span>
                            <span>Form: <strong class="text-slate-800" x-text="selectedKode"></strong></span>
                        </div>

                        <!-- Catatan Dokumen Sesuai Pengaturan Kelola Form -->
                        <table class="border-none border-collapse text-[9px] text-slate-600 italic">
                            <tr>
                                <td class="border-none p-0 pr-2 font-bold whitespace-nowrap align-top text-slate-700">CATATAN :</td>
                                <td class="border-none p-0 pr-1 font-bold whitespace-nowrap align-top text-slate-700 w-3">1.</td>
                                <td class="border-none p-0 align-top">
                                    <span x-text="formSettings.catatan_1"></span>
                                    <template x-if="currentFormMeta.is_bilingual">
                                        <div class="text-[8px] not-italic text-slate-500">注：1.端数が出た場合は、小数点以下第2位まで計算されます</div>
                                    </template>
                                </td>
                            </tr>
                            <tr>
                                <td class="border-none p-0"></td>
                                <td class="border-none p-0 pr-1 font-bold whitespace-nowrap align-top text-slate-700 w-3">2.</td>
                                <td class="border-none p-0 align-top">
                                    <span x-text="formSettings.catatan_2"></span>
                                    <template x-if="currentFormMeta.is_bilingual">
                                        <div class="text-[8px] not-italic text-slate-500">2. 四捨五入の目安は0.5まで切り捨て、0.5を超えると切り上げます</div>
                                    </template>
                                </td>
                            </tr>
                        </table>

                        <!-- PENJUMLAHAN PENILAIAN White Equation Box (Persis Seperti di Excel & Form Penilai) -->
                        <div class="border border-slate-900 p-2.5 bg-white shadow-2xs">
                            <div class="text-[9.5px] font-bold uppercase tracking-wider text-slate-900 mb-1.5 flex items-center justify-between">
                                <span>PENJUMLAHAN PENILAIAN</span>
                                <span class="text-[8.5px] font-normal text-slate-500 font-sans">Kalkulasi Otomatis Berdasarkan Bobot Aspek</span>
                            </div>
                            
                            <div class="flex flex-nowrap items-center justify-between gap-1 text-[9.5px] text-slate-800">
                                
                                <!-- Hasil Kerja -->
                                <div class="flex-1 text-center min-w-0">
                                    <div class="text-[8.5px] font-semibold text-slate-700 leading-tight">
                                        <span>HASIL KERJA</span>
                                        <span x-show="currentFormMeta.is_bilingual" class="block text-[7px] text-slate-500">仕事の結果</span>
                                    </div>
                                    <div class="border border-slate-900 bg-white px-1.5 py-0.5 font-mono text-xs font-bold text-slate-950 mt-1"
                                         x-text="getGroupWeighted('hasil_kerja')">
                                        0.00
                                    </div>
                                </div>

                                <div class="text-xs font-bold text-slate-900 px-0.5 self-center mt-2.5">+</div>

                                <!-- Proses Kerja -->
                                <div class="flex-1 text-center min-w-0">
                                    <div class="text-[8.5px] font-semibold text-slate-700 leading-tight">
                                        <span>PROSES KERJA</span>
                                        <span x-show="currentFormMeta.is_bilingual" class="block text-[7px] text-slate-500">仕事の方法</span>
                                    </div>
                                    <div class="border border-slate-900 bg-white px-1.5 py-0.5 font-mono text-xs font-bold text-slate-950 mt-1"
                                         x-text="getGroupWeighted('proses_kerja')">
                                        0.00
                                    </div>
                                </div>

                                <!-- People Man (if present) -->
                                <template x-if="activeStructure && activeStructure.groups && activeStructure.groups.man_management">
                                    <div class="flex items-center gap-1 flex-1 min-w-0">
                                        <div class="text-xs font-bold text-slate-900 px-0.5 self-center mt-2.5">+</div>
                                        <div class="flex-1 text-center min-w-0">
                                            <div class="text-[8.5px] font-semibold text-slate-700 leading-tight">
                                                <span>PEOPLE MAN</span>
                                                <span x-show="currentFormMeta.is_bilingual" class="block text-[7px] text-slate-500">人材管理</span>
                                            </div>
                                            <div class="border border-slate-900 bg-white px-1.5 py-0.5 font-mono text-xs font-bold text-slate-950 mt-1"
                                                 x-text="getGroupWeighted('man_management')">
                                                0.00
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <div class="text-xs font-bold text-slate-900 px-0.5 self-center mt-2.5">+</div>

                                <!-- Sikap Kerja -->
                                <div class="flex-1 text-center min-w-0">
                                    <div class="text-[8.5px] font-semibold text-slate-700 leading-tight">
                                        <span>SIKAP KERJA</span>
                                        <span x-show="currentFormMeta.is_bilingual" class="block text-[7px] text-slate-500">仕事に関する性格</span>
                                    </div>
                                    <div class="border border-slate-900 bg-white px-1.5 py-0.5 font-mono text-xs font-bold text-slate-950 mt-1"
                                         x-text="getGroupWeighted('sikap_kerja')">
                                        0.00
                                    </div>
                                </div>

                                <div class="text-xs font-bold text-slate-900 px-0.5 self-center mt-2.5">=</div>

                                <!-- TOTAL -->
                                <div class="flex-1 text-center min-w-0">
                                    <div class="text-[8.5px] font-bold text-slate-900 leading-tight">
                                        <span>TOTAL</span>
                                        <span x-show="currentFormMeta.is_bilingual" class="block text-[7px] text-slate-600">合計</span>
                                    </div>
                                    <div class="border-2 border-[#92302e] bg-rose-50 px-2 py-0.5 font-mono text-xs font-black text-[#92302e] mt-1 flex items-center justify-center gap-1">
                                        <span x-text="getGrandTotal()">0.00</span>
                                        <span class="text-[10px] font-black px-1 bg-[#92302e] text-white rounded-none" x-text="getGrandGrade()">K</span>
                                    </div>
                                </div>

                            </div>
                        </div>

                    </div>
                </div>

                <!-- ========================================================= -->
                <!-- BODY RIGHT: EVALUASI POTENSI, KELEBIHAN & KEKURANGAN      -->
                <!-- ========================================================= -->
                <div class="col-span-12 lg:col-span-5 flex flex-col bg-white text-xs h-full justify-between">
                    
                    <!-- Top section: Header, Petunjuk & 4 Opsi Potensi Sesuai Pengaturan Form -->
                    <div class="flex-shrink-0">
                        
                        <!-- Header Bar (Sesuai Gray Bar #d1d5db Excel) -->
                        <div class="bg-[#d1d5db] border-b border-slate-900 p-2 text-center font-bold uppercase tracking-wider text-slate-900 flex items-center justify-between gap-2">
                            <span class="flex-1 text-center font-bold text-[10px]"
                                  x-text="currentFormMeta.is_bilingual ? (formSettings.judul_evaluasi_potensi + ' / 従業員の発展') : formSettings.judul_evaluasi_potensi">
                                INDIKATOR EVALUASI POTENSI KARYAWAN
                            </span>
                        </div>

                        <!-- Petunjuk Pengisian -->
                        <div class="p-3 border-b border-slate-300 bg-slate-50/50">
                            <span class="font-bold text-[10.5px] block text-slate-900 mb-1"
                                  x-text="currentFormMeta.is_bilingual ? 'Petunjuk Pengisian / 記入方法' : 'Petunjuk Pengisian.'">
                                Petunjuk Pengisian.
                            </span>
                            <p class="text-[9.5px] text-slate-700 leading-relaxed text-justify"
                               x-text="formSettings.petunjuk_evaluasi_potensi">
                            </p>
                            <template x-if="currentFormMeta.is_bilingual">
                                <p class="text-[8.5px] text-slate-500 mt-1 leading-snug">
                                    各上司が部下の能力を把握し今より高い職位ともっと重い責任を任せられるかどうかを記入する
                                </p>
                            </template>
                        </div>

                        <!-- 4 Options (Square Checkbox ✕ Sesuai Form Asli) -->
                        <div class="p-3 space-y-3">
                            
                            <!-- Option 1 -->
                            <label class="flex items-start gap-2 cursor-pointer group">
                                <input type="radio" 
                                       name="pilihan_potensi" 
                                       value="1" 
                                       x-model="pilihanPotensi"
                                       class="sr-only peer">
                                <span class="w-3.5 h-3.5 border border-slate-900 bg-white flex items-center justify-center font-bold text-[10px] text-slate-900 flex-shrink-0 mt-0.5 peer-checked:bg-slate-900 peer-checked:text-white transition-colors">
                                    <span x-show="pilihanPotensi == 1" class="font-black text-[11px]">✓</span>
                                </span>
                                <div class="flex-1">
                                    <span class="font-bold text-slate-900 text-[10px] block leading-tight"
                                          x-text="formSettings.opsi_potensi_1">
                                    </span>
                                    <template x-if="currentFormMeta.is_bilingual">
                                        <span class="text-[8.5px] text-slate-500 block leading-tight mt-0.5">
                                            能力が高く、今より上のレベルに上がれる。お勧めとなる職位は
                                        </span>
                                    </template>
                                    <input type="text" 
                                           x-model="catatanPotensi1"
                                           placeholder="Tuliskan saran posisi promosi..." 
                                           class="w-full mt-1 border-b border-slate-900 focus:border-[#92302e] outline-none text-[10px] bg-transparent py-0.5 font-medium">
                                </div>
                            </label>

                            <!-- Option 2 -->
                            <label class="flex items-start gap-2 cursor-pointer group">
                                <input type="radio" 
                                       name="pilihan_potensi" 
                                       value="2" 
                                       x-model="pilihanPotensi"
                                       class="sr-only peer">
                                <span class="w-3.5 h-3.5 border border-slate-900 bg-white flex items-center justify-center font-bold text-[10px] text-slate-900 flex-shrink-0 mt-0.5 peer-checked:bg-slate-900 peer-checked:text-white transition-colors">
                                    <span x-show="pilihanPotensi == 2" class="font-black text-[11px]">✓</span>
                                </span>
                                <div class="flex-1">
                                    <span class="font-bold text-slate-900 text-[10px] block leading-tight"
                                          x-text="formSettings.opsi_potensi_2">
                                    </span>
                                    <template x-if="currentFormMeta.is_bilingual">
                                        <span class="text-[8.5px] text-slate-500 block leading-tight mt-0.5">
                                            今の能力よりもまだ発展することになるためトレーニングと経験を高める為に今のままでいい
                                        </span>
                                    </template>
                                    <input type="text" 
                                           x-model="catatanPotensi2"
                                           placeholder="Tuliskan catatan pelatihan / pengembangan..." 
                                           class="w-full mt-1 border-b border-slate-900 focus:border-[#92302e] outline-none text-[10px] bg-transparent py-0.5 font-medium">
                                </div>
                            </label>

                            <!-- Option 3 -->
                            <label class="flex items-start gap-2 cursor-pointer group">
                                <input type="radio" 
                                       name="pilihan_potensi" 
                                       value="3" 
                                       x-model="pilihanPotensi"
                                       class="sr-only peer">
                                <span class="w-3.5 h-3.5 border border-slate-900 bg-white flex items-center justify-center font-bold text-[10px] text-slate-900 flex-shrink-0 mt-0.5 peer-checked:bg-slate-900 peer-checked:text-white transition-colors">
                                    <span x-show="pilihanPotensi == 3" class="font-black text-[11px]">✓</span>
                                </span>
                                <div class="flex-1">
                                    <span class="font-bold text-slate-900 text-[10px] block leading-tight"
                                          x-text="formSettings.opsi_potensi_3">
                                    </span>
                                    <template x-if="currentFormMeta.is_bilingual">
                                        <span class="text-[8.5px] text-slate-500 block leading-tight mt-0.5">
                                            能力のキャパがマックスであり発揮する事は来ないので今のままで残す理由：
                                        </span>
                                    </template>
                                    <input type="text" 
                                           x-model="catatanPotensi3"
                                           placeholder="Tuliskan alasan..." 
                                           class="w-full mt-1 border-b border-slate-900 focus:border-[#92302e] outline-none text-[10px] bg-transparent py-0.5 font-medium">
                                </div>
                            </label>

                            <!-- Option 4 -->
                            <label class="flex items-start gap-2 cursor-pointer group">
                                <input type="radio" 
                                       name="pilihan_potensi" 
                                       value="4" 
                                       x-model="pilihanPotensi"
                                       class="sr-only peer">
                                <span class="w-3.5 h-3.5 border border-slate-900 bg-white flex items-center justify-center font-bold text-[10px] text-slate-900 flex-shrink-0 mt-0.5 peer-checked:bg-slate-900 peer-checked:text-white transition-colors">
                                    <span x-show="pilihanPotensi == 4" class="font-black text-[11px]">✓</span>
                                </span>
                                <div class="flex-1">
                                    <span class="font-bold text-slate-900 text-[10px] block leading-tight"
                                          x-text="formSettings.opsi_potensi_4">
                                    </span>
                                    <template x-if="currentFormMeta.is_bilingual">
                                        <span class="text-[8.5px] text-slate-500 block leading-tight mt-0.5">
                                            現在の職位に合わない。その理由：
                                        </span>
                                    </template>
                                    <input type="text" 
                                           x-model="catatanPotensi4"
                                           placeholder="Tuliskan alasan..." 
                                           class="w-full mt-1 border-b border-slate-900 focus:border-[#92302e] outline-none text-[10px] bg-transparent py-0.5 font-medium">
                                </div>
                            </label>

                        </div>
                    </div>

                    <!-- KELEBIHAN KARYAWAN (Lined Paper Style Sesuai Format Penilaian) -->
                    <div class="border-t border-slate-900 flex-1 flex flex-col min-h-[140px]">
                        <div class="bg-[#d1d5db] border-b border-slate-900 py-1 px-3 font-bold text-center uppercase tracking-wider text-slate-900 text-[10px] flex-shrink-0"
                             x-text="currentFormMeta.is_bilingual ? (formSettings.judul_kelebihan + ' / 従業員のプラス所') : formSettings.judul_kelebihan">
                            KELEBIHAN KARYAWAN
                        </div>
                        <div class="p-2 flex-1 flex flex-col">
                            <textarea x-model="kelebihan"
                                      placeholder="Tuliskan catatan kelebihan karyawan..." 
                                      style="background-image: repeating-linear-gradient(transparent, transparent 23px, #cbd5e1 24px); line-height: 24px;"
                                      class="w-full flex-1 border-none p-1 text-[10px] text-slate-900 outline-none bg-transparent resize-none leading-[24px]"></textarea>
                        </div>
                    </div>

                    <!-- KEKURANGAN KARYAWAN (Lined Paper Style Sesuai Format Penilaian) -->
                    <div class="border-t border-slate-900 flex-1 flex flex-col min-h-[140px]">
                        <div class="bg-[#d1d5db] border-b border-slate-900 py-1 px-3 font-bold text-center uppercase tracking-wider text-slate-900 text-[10px] flex-shrink-0"
                             x-text="currentFormMeta.is_bilingual ? (formSettings.judul_kekurangan + ' / 従業員のマイナス所') : formSettings.judul_kekurangan">
                            KEKURANGAN KARYAWAN
                        </div>
                        <div class="p-2 flex-1 flex flex-col">
                            <textarea x-model="kekurangan"
                                      placeholder="Tuliskan kekurangan / area pengembangan karyawan..." 
                                      style="background-image: repeating-linear-gradient(transparent, transparent 23px, #cbd5e1 24px); line-height: 24px;"
                                      class="w-full flex-1 border-none p-1 text-[10px] text-slate-900 outline-none bg-transparent resize-none leading-[24px]"></textarea>
                        </div>
                    </div>

                </div>

            </div>

        </div>

    </div>

    <!-- ========================================================================= -->
    <!-- MODAL RUBRIK KRITERIA MANUAL ("5 PILIHAN KALIMAT STANDAR MUSASHI")        -->
    <!-- IDENTIK DENGAN MODAL YANG DIBUKA OLEH PENILAI SAAT MEMILIH SKOR ASPEK     -->
    <!-- ========================================================================= -->
    <div x-show="showRubrikModal" 
         x-cloak 
         @click.self="showRubrikModal = false"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        
        <div class="bg-white rounded-none max-w-6xl w-full border-2 border-slate-900 shadow-2xl overflow-hidden flex flex-col max-h-[92vh] animate-in fade-in duration-150">
            
            <!-- Modal Header -->
            <div class="p-4 bg-gradient-to-r from-[#92302e] to-[#6d1b1d] text-white flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-rose-200">Rubrik Panduan Penilaian Manual Musashi</span>
                    <h3 class="text-base font-black text-white mt-0.5" x-text="activeAspectName"></h3>
                    <span class="text-xs font-mono text-rose-200" x-text="activeAspectCode"></span>
                </div>
                <button type="button" @click="showRubrikModal = false" class="text-white/80 hover:text-white p-1 hover:bg-white/10 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Petunjuk Penilai Bar -->
            <div class="bg-amber-50 px-5 py-2.5 border-b border-amber-200 flex items-center justify-between gap-3 text-xs text-amber-950">
                <div class="flex items-center gap-2">
                    <span class="text-base">💡</span>
                    <span><strong>Petunjuk Penilai:</strong> <span x-text="formSettings.catatan_petunjuk_penilai"></span> <strong>Klik pada kalimat atau tombol skor, maka nilai angka otomatis masuk ke tabel lembar penilaian.</strong></span>
                </div>
            </div>

            <!-- Modal Body: 5 Pilihan Kalimat Rubrik Standar Musashi Berdampingan -->
            <div class="p-5 overflow-y-auto">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                    <template x-for="s in [5, 4, 3, 2, 1]" :key="s">
                        <div @click="selectRubrikScore(s)" 
                             class="p-4 rounded-none border-2 transition-all cursor-pointer flex flex-col justify-between hover:shadow-md relative group min-h-[220px]"
                             :class="scores[activeAspectCode] == s ? 'border-[#92302e] bg-rose-50/70 ring-2 ring-[#92302e]' : 'border-slate-300 hover:border-[#92302e] bg-white'">
                            
                            <div>
                                <!-- Card Header: Score Badge + Label -->
                                <div class="flex items-center justify-between gap-2 border-b border-slate-200 pb-2 mb-2">
                                    <div class="flex items-center gap-2">
                                        <span class="w-8 h-8 rounded-none flex items-center justify-center font-black text-sm font-mono text-white"
                                              :class="s >= 4 ? 'bg-emerald-600' : (s === 3 ? 'bg-blue-600' : 'bg-amber-600')"
                                              x-text="s">
                                        </span>
                                        <div>
                                            <span class="text-xs font-black block tracking-tight text-slate-900" x-text="'Skor ' + s"></span>
                                            <span class="text-[9.5px] text-slate-500 block font-medium" x-text="getScoreLabel(s)"></span>
                                        </div>
                                    </div>

                                    <span x-show="scores[activeAspectCode] == s" class="text-[9.5px] font-bold text-[#92302e] bg-rose-100 px-1.5 py-0.2 rounded-none border border-rose-300">
                                        ✓ Terpilih
                                    </span>
                                </div>

                                <!-- Full Descriptive Text from Master Kriteria -->
                                <p class="text-xs text-slate-800 leading-relaxed text-left font-normal select-none" 
                                   x-text="getKriteriaText(activeAspectCode, s) || ('Deskripsi kriteria standar Musashi untuk skor ' + s)"></p>
                            </div>

                            <!-- Action Button -->
                            <div class="mt-4 pt-2 border-t border-slate-200 text-center">
                                <button type="button" 
                                        @click.stop="selectRubrikScore(s)"
                                        class="w-full py-1.5 px-2 rounded-none text-xs font-bold transition-all shadow-2xs flex items-center justify-center gap-1.5"
                                        :class="scores[activeAspectCode] == s ? 'bg-[#92302e] text-white' : 'bg-slate-100 text-slate-700 group-hover:bg-[#92302e] group-hover:text-white'">
                                    <span x-text="scores[activeAspectCode] == s ? '✓ Skor ' + s + ' Terpilih' : '👉 Pilih Teks Ini (Skor ' + s + ')'"></span>
                                </button>
                            </div>

                        </div>
                    </template>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="p-3.5 bg-slate-100 border-t border-slate-300 flex items-center justify-between">
                <button type="button" 
                        @click="clearRubrikScore()" 
                        class="px-3 py-1.5 text-xs font-bold text-rose-700 hover:text-rose-900 bg-white border border-rose-300 hover:bg-rose-50 transition rounded-none">
                    🗑 Kosongkan Nilai Aspek Ini
                </button>
                <button type="button" 
                        @click="showRubrikModal = false" 
                        class="px-5 py-1.5 text-xs font-bold text-slate-800 bg-white border border-slate-300 hover:bg-slate-200 transition rounded-none">
                    Tutup
                </button>
            </div>

        </div>
    </div>

</div>

<!-- Real-time Interactive Alpine.js Calculation & Form Engine -->
<script>
function lembarPenilaianPkHandler() {
    return {
        forms: @json($daftarForm),
        formSettings: @json($formSettings),
        kriteriaMap: @json($kriteriaMap),
        employeesByForm: @json($employeesByForm),
        
        selectedKode: @json($initialKode ?? '07-001'),
        selectedDocId: @json($initialDocId ?? null),
        
        currentEmployee: {},
        currentFormEmployees: [],
        currentFormMeta: {},
        activeStructure: {},
        
        simulasiTtd: true,
        showRubrikModal: false,
        activeAspectCode: '',
        activeAspectName: '',
        
        scores: {},
        pilihanPotensi: 2,
        catatanPotensi1: '',
        catatanPotensi2: '',
        catatanPotensi3: '',
        catatanPotensi4: '',
        kelebihan: '',
        kekurangan: '',

        init() {
            this.selectForm(this.selectedKode);
        },

        selectForm(kode) {
            this.selectedKode = kode;
            const meta = this.forms.find(f => f.kode === kode) || this.forms[0];
            this.currentFormMeta = meta;
            this.activeStructure = meta.structure || {};
            
            // Populate employees list for this form
            this.currentFormEmployees = this.employeesByForm[kode] || [];
            
            // Set current employee
            if (this.currentFormEmployees.length > 0) {
                const matchDoc = this.currentFormEmployees.find(e => e.doc_id == this.selectedDocId);
                this.currentEmployee = matchDoc || this.currentFormEmployees[0];
                this.selectedDocId = this.currentEmployee.doc_id;
            } else if (meta.sample_karyawan) {
                this.currentEmployee = {
                    doc_id: meta.sample_doc_id || 1,
                    nrp: meta.sample_karyawan.nrp,
                    nama: meta.sample_karyawan.nama,
                    golongan: meta.sample_karyawan.golongan,
                    jabatan: meta.sample_karyawan.jabatan,
                    seksi: meta.sample_karyawan.seksi,
                    departemen: meta.sample_karyawan.departemen,
                    tgl_masuk: meta.sample_karyawan.tgl_masuk,
                    foreman_nama: meta.ttd_config ? meta.ttd_config.col1.name : '-',
                    sh_nama: meta.ttd_config ? meta.ttd_config.col2.name : '-',
                    dh_nama: meta.ttd_config ? meta.ttd_config.col3.name : '-',
                    div_nama: '-',
                };
                this.selectedDocId = this.currentEmployee.doc_id;
            }

            // Inisialisasi skor kosong (atau pertahankan jika sudah diisi untuk form ini)
            // Agar saat dibuka belum dinilai (sesuai request user)
            this.scores = {};
        },

        onSelectEmployee(docId) {
            const emp = this.currentFormEmployees.find(e => e.doc_id == docId);
            if (emp) {
                this.currentEmployee = emp;
                this.selectedDocId = emp.doc_id;
            }
        },

        getSignerName(colNum) {
            if (this.selectedKode === '07-006-DIV') {
                return colNum === 3 ? (this.currentEmployee.nama || 'ATASAN PROD A') : '-';
            }
            if (this.selectedKode === '07-006-DH') {
                if (colNum === 1) return this.currentEmployee.nama || 'SUPARDI';
                if (colNum === 3) return this.currentEmployee.div_nama && this.currentEmployee.div_nama !== '-' ? this.currentEmployee.div_nama : 'ATASAN PROD A';
                return '-';
            }
            if (this.selectedKode === '07-006-SH') {
                if (colNum === 1) return this.currentEmployee.nama || 'KRISTIYANTO';
                if (colNum === 2) return this.currentEmployee.dh_nama && this.currentEmployee.dh_nama !== '-' ? this.currentEmployee.dh_nama : 'SUPARDI';
                if (colNum === 3) return this.currentEmployee.div_nama && this.currentEmployee.div_nama !== '-' ? this.currentEmployee.div_nama : 'ATASAN PROD A';
                return '-';
            }
            // 07-001, 07-002, 07-003, 07-004
            if (colNum === 1) return this.currentEmployee.foreman_nama && this.currentEmployee.foreman_nama !== '-' ? this.currentEmployee.foreman_nama : 'SARYANTA';
            if (colNum === 2) return this.currentEmployee.sh_nama && this.currentEmployee.sh_nama !== '-' ? this.currentEmployee.sh_nama : 'KRISTIYANTO';
            if (colNum === 3) return this.currentEmployee.dh_nama && this.currentEmployee.dh_nama !== '-' ? this.currentEmployee.dh_nama : 'SUPARDI';
            return '-';
        },

        getFormCodeLabel() {
            if (['07-006-SH', '07-006-DH', '07-006-DIV'].includes(this.selectedKode)) {
                return 'Code PDV Mapin - 07-006';
            }
            return 'Code PDV Mapin - ' + this.selectedKode;
        },

        getBannerTitle() {
            switch(this.selectedKode) {
                case '07-001': return 'KJ : 1 - 3';
                case '07-002': return 'KJ : 4 - 5 non jabatan';
                case '07-003': return 'LEADER';
                case '07-004': return 'FOREMAN';
                case '07-006-SH': return 'SECTION HEAD / セクションヘッド';
                case '07-006-DH': return 'DEPARTMENT HEAD / 部長';
                case '07-006-DIV': return 'DIVISION HEAD / 事業部長';
                default: return 'KJ : 1 - 3';
            }
        },

        isColBlocked(colNum) {
            if (this.selectedKode === '07-006-DIV') {
                return colNum === 1 || colNum === 2;
            }
            if (this.selectedKode === '07-006-DH') {
                return colNum === 2;
            }
            return false;
        },

        isColInteractive(colNum) {
            // Interactive clickable selector to choose rubric score
            if (this.selectedKode === '07-006-DIV') {
                return colNum === 3;
            }
            if (this.selectedKode === '07-006-DH' || this.selectedKode === '07-006-SH') {
                return colNum === 1;
            }
            // For 07-001, 07-002, 07-003, 07-004: Col 1 is direct evaluator
            return colNum === 1;
        },

        getColHeaderClass(colNum) {
            if (this.isColBlocked(colNum)) return 'bg-[#bfbfbf] text-slate-400';
            if (this.isColInteractive(colNum)) return 'bg-[#e2efda] text-emerald-950 font-black';
            return 'bg-[#d9d9d9] text-slate-800';
        },

        openRubrikModal(code, name) {
            this.activeAspectCode = code;
            this.activeAspectName = name;
            this.showRubrikModal = true;
        },

        selectRubrikScore(score) {
            this.scores[this.activeAspectCode] = score;
            this.showRubrikModal = false;
        },

        clearRubrikScore() {
            delete this.scores[this.activeAspectCode];
            this.showRubrikModal = false;
        },

        getKriteriaText(code, score) {
            if (this.kriteriaMap && this.kriteriaMap[code] && this.kriteriaMap[code][score]) {
                return this.kriteriaMap[code][score];
            }
            return '';
        },

        getScoreLabel(score) {
            switch(score) {
                case 5: return 'Istimewa';
                case 4: return 'Baik Sekali';
                case 3: return 'Baik / Standar';
                case 2: return 'Kurang';
                case 1: return 'Sangat Kurang';
                default: return '';
            }
        },

        // Perhitungan Subtotal, Rata-Rata, dan Nilai Akhir Bobot Real-Time
        getGroupSum(groupKey) {
            const group = this.activeStructure?.groups?.[groupKey];
            if (!group || !group.aspects) return 0;
            let sum = 0;
            for (const code of Object.keys(group.aspects)) {
                if (this.scores[code]) {
                    sum += Number(this.scores[code]);
                }
            }
            return sum;
        },

        getGroupAvg(groupKey) {
            const group = this.activeStructure?.groups?.[groupKey];
            if (!group || !group.aspects) return '0.00';
            const count = Object.keys(group.aspects).length;
            if (count === 0) return '0.00';
            const sum = this.getGroupSum(groupKey);
            return (sum / count).toFixed(2);
        },

        getGroupWeighted(groupKey) {
            const group = this.activeStructure?.groups?.[groupKey];
            if (!group) return '0.00';
            const avg = Number(this.getGroupAvg(groupKey));
            const weight = Number(group.weight || 0);
            return (avg * (weight / 100)).toFixed(2);
        },

        getGrandTotal() {
            if (!this.activeStructure?.groups) return '0.00';
            let grand = 0;
            for (const gKey of Object.keys(this.activeStructure.groups)) {
                grand += Number(this.getGroupWeighted(gKey));
            }
            return grand.toFixed(2);
        },

        getGrandGrade() {
            const total = Number(this.getGrandTotal());
            if (total >= 4.5) return 'S';
            if (total >= 3.5) return 'A';
            if (total >= 2.5) return 'B';
            if (total >= 1.5) return 'C';
            return 'K';
        },

        getRataTitle(group, groupKey) {
            const count = Object.keys(group.aspects || {}).length;
            const isBilingual = this.currentFormMeta.is_bilingual;
            if (isBilingual) {
                if (groupKey === 'hasil_kerja') return 'RATA - RATA HASIL KERJA (Total Nilai dibagi ' + count + ') 作業プロセスの平均（合計値を' + count + 'で割ったもの）';
                if (groupKey === 'proses_kerja') return 'RATA - RATA PROSES KERJA (Total Nilai dibagi ' + count + ') 作業プロセスの平均（合計値を' + count + 'で割ったもの）';
                if (groupKey === 'man_management') return 'RATA - RATA MANAGEMENT (Total Nilai dibagi ' + count + ') 平均管理（合計値を' + count + 'で割ったもの';
                if (groupKey === 'sikap_kerja') return 'RATA - RATA SIKAP KERJA (Total Nilai dibagi ' + count + ') 仕事の平均（合計値を' + count + 'で割ったもの';
            }
            const cleanName = (group.name || '').split('(')[0].trim().toUpperCase();
            return 'RATA - RATA ' + cleanName + ' (Total Nilai dibagi ' + count + ')';
        },

        getNilaiAkhirTitle(group, groupKey) {
            const isBilingual = this.currentFormMeta.is_bilingual;
            if (isBilingual) {
                if (groupKey === 'hasil_kerja') return 'NILAI AKHIR HASIL KERJA (Rata-Rata Hasil Kerja X ' + group.weight + '%) / 仕事成果の最終得点 平均労働記述X ' + group.weight + '%)';
                if (groupKey === 'proses_kerja') return 'NILAI AKHIR PROSES KERJA (Rata-Rata Proses Kerja X ' + group.weight + '%) / 最終的な作業手順（平均作業手順X ' + group.weight + '%)';
                if (groupKey === 'man_management') return 'NILAI AKHIR MANAGEMENT (Rata-Rata Sikap Kerja X ' + group.weight + '%) ファイナル・バリューマネジメント（平均作業姿勢X ' + group.weight + '%)';
                if (groupKey === 'sikap_kerja') return 'NILAI AKHIR SIKAP KERJA (Rata-Rata Sikap Kerja X ' + group.weight + '%) 職場における最終的な価値（平均勤務態度X ' + group.weight + '%）';
            }
            const cleanName = (group.name || '').split('(')[0].trim().toUpperCase();
            return 'NILAI AKHIR ' + cleanName + ' (Rata-Rata ' + cleanName + ' X ' + group.weight + '%)';
        },

        fillSampleScores() {
            if (!this.activeStructure?.groups) return;
            const newScores = {};
            let alt = 0;
            for (const group of Object.values(this.activeStructure.groups)) {
                for (const code of Object.keys(group.aspects || {})) {
                    newScores[code] = (alt % 2 === 0) ? 4 : 3;
                    alt++;
                }
            }
            this.scores = newScores;
            this.pilihanPotensi = 1;
            this.catatanPotensi1 = 'Staff Senior / Team Leader';
            this.kelebihan = 'Disiplin tinggi, selalu mencapai target output harian dengan tingkat scrap minimal.';
            this.kekurangan = 'Perlu pendalaman lebih lanjut untuk troubleshooting mandiri pada mesin baru.';
        },

        resetScores() {
            this.scores = {};
            this.pilihanPotensi = 2;
            this.catatanPotensi1 = '';
            this.catatanPotensi2 = '';
            this.catatanPotensi3 = '';
            this.catatanPotensi4 = '';
            this.kelebihan = '';
            this.kekurangan = '';
        },

        formatDateIndo(dateStr) {
            if (!dateStr) return '01 Januari 2025';
            try {
                const d = new Date(dateStr);
                if (isNaN(d.getTime())) return dateStr;
                const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                return String(d.getDate()).padStart(2, '0') + ' ' + months[d.getMonth()] + ' ' + d.getFullYear();
            } catch (e) {
                return dateStr;
            }
        }
    }
}
</script>
@endsection
