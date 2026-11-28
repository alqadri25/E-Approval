@extends('layouts.app', ['title' => 'Cari Karyawan & Buka Form Penilaian'])

@section('content')
<div class="space-y-6" x-data="nrpSearchHandler()">

    <!-- Top Header Banner (Dark Red #92302e Brand, Persegi & Clean) -->
    <div class="bg-gradient-to-r from-[#92302e] via-[#7e2524] to-[#5c1917] p-5 sm:p-6 text-white shadow-sm border border-[#7e2524] rounded-none">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl sm:text-2xl font-black tracking-tight text-white">
                    Form Penilaian Karyawan
                </h1>
                <p class="mt-1 text-rose-100/90 text-xs sm:text-sm max-w-2xl font-normal leading-relaxed">
                    Cari atau pilih karyawan untuk membuka formulir penilaian karyawan.
                </p>
            </div>
            <div class="flex items-center gap-2">
                @if(!empty($countPerluDinilai) && $countPerluDinilai > 0)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-400 text-slate-900 text-xs font-black shadow-xs rounded-none">
                        <span class="w-2 h-2 rounded-full bg-[#92302e] animate-ping"></span>
                        ⚡ {{ $countPerluDinilai }} Siap Dinilai
                    </span>
                @endif
                <span class="inline-block px-3 py-1.5 bg-white/15 backdrop-blur-sm text-xs font-bold text-white border border-white/20 font-mono rounded-none">
                    {{ $totalCount }} Bawahan Terdaftar
                </span>
            </div>
        </div>
    </div>

    @if(!empty($selfKaryawan))
    <!-- Dedicated Hero Card: Penilaian Mandiri (Evaluasi Diri Sendiri) -->
    <div class="bg-white p-5 sm:p-6 border-2 border-[#92302e] shadow-xs rounded-none relative overflow-hidden">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-5 relative z-10">
            <div class="flex items-start sm:items-center gap-4">
                <div class="w-12 h-12 bg-rose-50 border border-rose-200 text-[#92302e] flex items-center justify-center text-xl flex-shrink-0 rounded-none shadow-2xs font-bold">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="px-2.5 py-0.5 text-[10px] font-black bg-[#92302e] text-white uppercase tracking-wider rounded-none shadow-2xs flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 bg-white animate-pulse"></span>
                            Penilaian Mandiri Saya
                        </span>
                        <span class="px-2 py-0.5 text-xs font-mono font-bold bg-slate-100 text-slate-800 border border-slate-300 rounded-none">
                            NRP: {{ $selfKaryawan['nrp'] }}
                        </span>
                        <span class="px-2 py-0.5 text-xs font-bold bg-slate-100 text-slate-700 border border-slate-300 rounded-none">
                            {{ $selfFormName }}
                        </span>
                    </div>
                    <h2 class="text-lg sm:text-xl font-black text-slate-900 mt-1 flex flex-wrap items-center gap-2">
                        <span>{{ $selfKaryawan['nama_lengkap'] }}</span>
                        <span class="text-xs font-semibold text-slate-500">({{ $selfKaryawan['jabatan'] }} &bull; {{ $selfKaryawan['departemen'] }})</span>
                    </h2>
                    <p class="text-xs text-slate-600 mt-0.5 max-w-xl">
                        @if($user->isForeman())
                            Formulir penilaian karya resmi untuk evaluasi kinerja Anda sendiri.
                        @else
                            Formulir penilaian karya resmi untuk evaluasi kinerja Anda sendiri. Isi kolom <strong>Mandiri / 自分</strong> dan bubuhkan tanda tangan digital Anda.
                        @endif
                    </p>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 flex-shrink-0">
                @if($selfDoc)
                    <a href="{{ \App\Http\Controllers\PenilaianController::getFormUrlForUser($user, $selfDoc->id) }}" 
                       class="px-5 py-2.5 font-bold text-xs sm:text-sm bg-[#92302e] hover:bg-[#7e2524] text-white shadow-xs hover:shadow-sm transition-all flex items-center justify-center gap-2 rounded-none cursor-pointer">
                        <span>✍️ Buka Penilaian Mandiri Saya</span>
                        <span>&rarr;</span>
                    </a>
                @else
                    <form method="POST" action="{{ route('penilaian.buka') }}">
                        @csrf
                        <input type="hidden" name="nrp" value="{{ $selfKaryawan['nrp'] }}">
                        <button type="submit" 
                                class="w-full px-5 py-2.5 font-bold text-xs sm:text-sm bg-[#92302e] hover:bg-[#7e2524] text-white shadow-xs hover:shadow-sm transition-all flex items-center justify-center gap-2 rounded-none cursor-pointer">
                            <span>✍️ Buka Penilaian Mandiri Saya</span>
                            <span>&rarr;</span>
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
    @endif

@if(!$user->isAdmin())
    <!-- Live NRP Search Box Card (Bersudut Tegas & Border Slate Halus) -->
    <div class="bg-white p-5 sm:p-6 border border-slate-300 shadow-sm rounded-none">
        <form method="POST" action="{{ route('penilaian.buka') }}" @submit.prevent="submitForm">
            @csrf
            
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-2">
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                @if($user->isDepartmentHead() || $user->isSectionHead() || $user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head']))
                    Masukkan / Ketik NRP Karyawan yang Ingin Ditinjau &amp; Dinilai:
                @else
                    Masukkan / Ketik NRP Karyawan yang Ingin Dinilai:
                @endif
                </label>
                @if(!empty($selfKaryawan))
                <button type="button" 
                        @click="nrpInput = '{{ $selfKaryawan['nrp'] }}'; lookupKaryawan()"
                        class="text-xs font-bold text-[#92302e] hover:text-[#7e2524] bg-rose-50 hover:bg-rose-100 px-2.5 py-1 border border-rose-200 transition-colors flex items-center gap-1 self-start sm:self-auto rounded-none cursor-pointer">
                    <span>👤 Ketik NRP Mandiri ({{ $selfKaryawan['nrp'] }})</span>
                </button>
                @endif
            </div>

            <div class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    <input type="text" 
                           x-model="nrpInput" 
                           @input.debounce.300ms="lookupKaryawan" 
                           placeholder="Ketik NRP karyawan (Contoh: 39, 99998, 112, 280, 99999)..." 
                           class="w-full pl-10 pr-4 py-2.5 text-xs font-semibold rounded-none border border-slate-300 hover:border-slate-400 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] font-mono text-slate-900 bg-white placeholder-slate-400 uppercase transition shadow-xs">
                    
                    <template x-if="isLoading">
                        <div class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none">
                            <svg class="animate-spin h-4 w-4 text-[#92302e]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                        </div>
                    </template>
                </div>

                <button type="submit" 
                        :disabled="!matchedKaryawan"
                        class="px-5 py-2.5 font-bold text-xs bg-[#92302e] hover:bg-[#7e2524] disabled:bg-slate-200 disabled:text-slate-400 text-white shadow-sm transition-all flex items-center justify-center gap-2 rounded-none cursor-pointer">
                    <span>🚀 Buka Form Penilaian</span>
                </button>
            </div>
        </form>

        <!-- Live Preview Card (Appears as user types NRP) -->
        <template x-if="matchedKaryawan">
            <div class="mt-4 p-4 sm:p-5 bg-rose-50/50 border border-rose-200 shadow-xs rounded-none transition-all">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="space-y-1.5 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="px-2.5 py-0.5 text-xs font-black bg-[#92302e] text-white font-mono rounded-none" x-text="'NRP: ' + matchedKaryawan.nrp"></span>
                            <span class="px-2.5 py-0.5 text-xs font-bold bg-slate-900 text-white rounded-none" x-text="matchedKaryawan.form_name"></span>
                            
                            <!-- Badges for Foreman Status -->
                            <template x-if="matchedKaryawan.foreman_evaluated">
                                <span class="px-2 py-0.5 text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-300 rounded-none flex items-center gap-1">
                                    <span>✓</span> Foreman: Grade <strong x-text="matchedKaryawan.foreman_grade || 'A'"></strong>
                                </span>
                            </template>

                            <!-- Badges for Section Head Status -->
                            <template x-if="matchedKaryawan.section_head_evaluated">
                                <span class="px-2 py-0.5 text-xs font-bold bg-teal-50 text-teal-800 border border-teal-300 rounded-none flex items-center gap-1">
                                    <span>✓</span> Section Head: Grade <strong x-text="matchedKaryawan.section_head_grade || 'A'"></strong>
                                </span>
                            </template>

                            <!-- Badges for Department Head Status -->
                            <template x-if="matchedKaryawan.dept_head_evaluated && isDivHead">
                                <span class="px-2 py-0.5 text-xs font-bold bg-indigo-50 text-indigo-800 border border-indigo-300 rounded-none flex items-center gap-1">
                                    <span>✓</span> Dept Head: Grade <strong x-text="matchedKaryawan.dept_grade || 'A'"></strong>
                                </span>
                            </template>

                            <template x-if="matchedKaryawan.is_self">
                                <span class="px-2 py-0.5 text-xs font-bold bg-[#fbe8e8] text-[#92302e] border border-[#92302e]/30 rounded-none">
                                    ✍️ Penilaian Mandiri Anda
                                </span>
                            </template>

                            <template x-if="!matchedKaryawan.foreman_evaluated && matchedKaryawan.nrp_foreman && matchedKaryawan.kode_form && !matchedKaryawan.kode_form.startsWith('07-006') && isDeptHead && !matchedKaryawan.is_self">
                                <span class="px-2 py-0.5 text-xs font-bold bg-amber-50 text-amber-800 border border-amber-300 rounded-none">
                                    ⏳ Menunggu Penilaian Foreman
                                </span>
                            </template>

                            <template x-if="!matchedKaryawan.section_head_evaluated && (!matchedKaryawan.nrp_foreman || matchedKaryawan.foreman_evaluated || (matchedKaryawan.kode_form && matchedKaryawan.kode_form.startsWith('07-006'))) && (isDeptHead || isDivHead) && !matchedKaryawan.is_self">
                                <span class="px-2 py-0.5 text-xs font-bold bg-amber-50 text-amber-800 border border-amber-300 rounded-none">
                                    ⏳ Menunggu Penilaian Section Head
                                </span>
                            </template>

                            <template x-if="matchedKaryawan.section_head_evaluated && !matchedKaryawan.dept_head_evaluated && isDivHead && !matchedKaryawan.is_self">
                                <span class="px-2 py-0.5 text-xs font-bold bg-amber-50 text-amber-800 border border-amber-300 rounded-none">
                                    ⏳ Menunggu Penilaian Department Head
                                </span>
                            </template>

                            <template x-if="!matchedKaryawan.foreman_evaluated && matchedKaryawan.nrp_foreman && isSectionHead && !matchedKaryawan.is_self">
                                <span class="px-2 py-0.5 text-xs font-bold bg-amber-50 text-amber-800 border border-amber-300 rounded-none">
                                    ⏳ Menunggu Penilaian Foreman
                                </span>
                            </template>
                        </div>

                        <h3 class="text-lg font-black text-slate-900" x-text="matchedKaryawan.nama_lengkap"></h3>
                        
                        <p class="text-xs text-slate-600 flex flex-wrap items-center gap-2">
                            <span class="font-bold text-slate-800" x-text="matchedKaryawan.jabatan"></span>
                            <span>&bull;</span>
                            <span x-text="matchedKaryawan.golongan + ' (' + matchedKaryawan.kj + ')'"></span>
                            <span>&bull;</span>
                            <span x-text="matchedKaryawan.seksi"></span>
                            <template x-if="matchedKaryawan.foreman_name">
                                <span class="text-[#92302e] font-semibold" x-text="'&bull; Foreman: ' + matchedKaryawan.foreman_name"></span>
                            </template>
                            <template x-if="matchedKaryawan.section_head_name">
                                <span class="text-[#92302e] font-semibold" x-text="'&bull; Section: ' + matchedKaryawan.section_head_name"></span>
                            </template>
                        </p>

                        <!-- Highlight box for Division Head showing BOTH Section Head & Department Head Scores & TTD -->
                        <template x-if="isDivHead && !matchedKaryawan.is_self">
                            <div class="mt-2.5 p-3 bg-white border border-rose-200 text-xs grid grid-cols-1 sm:grid-cols-2 gap-3 rounded-none shadow-2xs">
                                <div class="flex items-center justify-between p-2 bg-slate-50 border border-slate-200 rounded-none">
                                    <div>
                                        <span class="text-[10px] font-bold text-slate-600 uppercase block">1. Hasil Section Head</span>
                                        <span class="text-xs font-bold text-slate-800" x-text="matchedKaryawan.section_head_name || 'Section Head'"></span>
                                    </div>
                                    <div class="text-right">
                                        <span class="font-mono text-slate-900 font-black text-sm" x-text="matchedKaryawan.section_head_score || '-'"></span>
                                        <span class="text-[10px] font-bold text-teal-700 block" x-text="'Grade: ' + (matchedKaryawan.section_head_grade || '-')"></span>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between p-2 bg-slate-50 border border-slate-200 rounded-none">
                                    <div>
                                        <span class="text-[10px] font-bold text-slate-600 uppercase block">2. Hasil Department Head</span>
                                        <span class="text-xs font-bold text-slate-800" x-text="matchedKaryawan.dept_head_name || 'Department Head'"></span>
                                    </div>
                                    <div class="text-right">
                                        <span class="font-mono text-slate-900 font-black text-sm" x-text="matchedKaryawan.dept_score || '-'"></span>
                                        <span class="text-[10px] font-bold text-emerald-700 block" x-text="'Grade: ' + (matchedKaryawan.dept_grade || '-')"></span>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- Highlight box for Department Head showing BOTH Foreman & Section Head Scores & TTD -->
                        <template x-if="isDeptHead && !matchedKaryawan.is_self">
                            <div class="mt-2.5 p-3 bg-white border border-rose-200 text-xs grid grid-cols-1 sm:grid-cols-2 gap-3 rounded-none shadow-2xs">
                                <template x-if="matchedKaryawan.nrp_foreman">
                                    <div class="flex items-center justify-between p-2 bg-slate-50 border border-slate-200 rounded-none">
                                        <div>
                                            <span class="text-[10px] font-bold text-slate-600 uppercase block">1. Hasil Foreman</span>
                                            <span class="text-xs font-bold text-slate-800" x-text="matchedKaryawan.foreman_name || 'Foreman'"></span>
                                        </div>
                                        <div class="text-right">
                                            <span class="font-mono text-slate-900 font-black text-sm" x-text="matchedKaryawan.foreman_score || '-'"></span>
                                            <span class="text-[10px] font-bold text-emerald-700 block" x-text="'Grade: ' + (matchedKaryawan.foreman_grade || '-')"></span>
                                        </div>
                                    </div>
                                </template>
                                <template x-if="!matchedKaryawan.nrp_foreman">
                                    <div class="flex items-center justify-between p-2 bg-slate-50 border border-slate-200 rounded-none">
                                        <div>
                                            <span class="text-[10px] font-bold text-slate-600 uppercase block">1. Alur Formulir</span>
                                            <span class="text-xs font-bold text-slate-800">Penilaian Langsung Section Head</span>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-[10px] font-bold text-slate-500 block">Tanpa Foreman</span>
                                            <span class="text-[10px] font-bold text-indigo-700 block" x-text="matchedKaryawan.kode_form"></span>
                                        </div>
                                    </div>
                                </template>
                                <div class="flex items-center justify-between p-2 bg-slate-50 border border-slate-200 rounded-none">
                                    <div>
                                        <span class="text-[10px] font-bold text-slate-600 uppercase block">2. Hasil Section Head</span>
                                        <span class="text-xs font-bold text-slate-800" x-text="matchedKaryawan.section_head_name || 'Section Head'"></span>
                                    </div>
                                    <div class="text-right">
                                        <span class="font-mono text-slate-900 font-black text-sm" x-text="matchedKaryawan.section_head_score || '-'"></span>
                                        <span class="text-[10px] font-bold text-teal-700 block" x-text="'Grade: ' + (matchedKaryawan.section_head_grade || '-')"></span>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- Highlight box for Section Head showing Foreman's Score & TTD -->
                        <template x-if="isSectionHead && matchedKaryawan.foreman_evaluated">
                            <div class="mt-2 p-2.5 bg-white border border-rose-200 text-xs flex flex-wrap items-center gap-3 rounded-none shadow-2xs">
                                <div class="flex items-center gap-1.5 text-slate-800 font-bold">
                                    <span>📊</span>
                                    <span>Nilai Foreman: <span class="font-mono text-slate-900 font-black text-sm" x-text="matchedKaryawan.foreman_score || '3.80'"></span> (Grade: <span class="font-black text-emerald-700" x-text="matchedKaryawan.foreman_grade || 'A'"></span>)</span>
                                </div>
                                <span class="text-slate-300">|</span>
                                <div class="flex items-center gap-1.5 text-slate-800 font-bold">
                                    <span>✍️</span>
                                    <span x-text="matchedKaryawan.has_foreman_sign ? 'Tanda Tangan Foreman: ✓ Sudah TTD Digital' : 'Tanda Tangan Foreman: ✓ Sudah TTD'"></span>
                                    <span class="text-slate-400 font-mono text-[11px]" x-show="matchedKaryawan.ttd_foreman_date" x-text="'(' + matchedKaryawan.ttd_foreman_date + ')'"></span>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="flex flex-col items-end gap-2 flex-shrink-0">
                        <template x-if="!isDivHead && matchedKaryawan.is_kj_locked && matchedKaryawan.status !== 'selesai' && !matchedKaryawan.is_self">
                            <div class="p-2 bg-rose-50 border border-rose-300 text-[11px] text-rose-900 rounded-none max-w-xs text-right">
                                <div class="font-bold flex items-center justify-end gap-1">
                                    <span>⛔</span>
                                    <span x-text="'Kuota ' + (matchedKaryawan.quota_kj || matchedKaryawan.kj) + ' Penuh / Over'"></span>
                                </div>
                                <a :href="'{{ route('penilaian.dept.penyesuaian') }}?filter_kj=' + (matchedKaryawan.quota_kj || matchedKaryawan.kj)"
                                   class="text-[10.5px] font-bold text-[#92302e] hover:underline block mt-0.5">
                                    ⚖️ Buka Penyesuaian Kuota &rarr;
                                </a>
                            </div>
                        </template>

                        <form method="POST" action="{{ route('penilaian.buka') }}" class="w-full sm:w-auto">
                            @csrf
                            <input type="hidden" name="nrp" :value="matchedKaryawan.nrp">
                            <button type="submit" 
                                    :class="matchedKaryawan.is_kj_locked && matchedKaryawan.status !== 'selesai' && !matchedKaryawan.is_self ? 'bg-rose-100 hover:bg-rose-200 text-rose-900 border border-rose-300' : 'bg-[#92302e] hover:bg-[#7e2524] text-white'"
                                    class="w-full sm:w-auto px-5 py-2.5 font-bold text-xs shadow-sm transition-colors flex items-center justify-center gap-2 rounded-none cursor-pointer">
                                <span x-text="matchedKaryawan.is_kj_locked && matchedKaryawan.status !== 'selesai' && !matchedKaryawan.is_self ? ('🔒 Buka Form (Kuota ' + matchedKaryawan.kj + ' Penuh)') : (matchedKaryawan.is_self ? ('✍️ Buka Penilaian Mandiri (Form ' + (matchedKaryawan.kode_form || '07-006') + ')') : (isDivHead ? (matchedKaryawan.status === 'menunggu_div_head' ? '✍️ Tinjau & Final Approval' : (matchedKaryawan.status === 'selesai' ? '👁️ Lihat Form Penilaian' : '🔒 Buka Form (Terkunci)')) : (isDeptHead ? (matchedKaryawan.status === 'menunggu_dept_head' ? '✍️ Tinjau & Tetapkan Nilai Dept Head' : '🔒 Buka Form (Terkunci)') : (isSectionHead ? (matchedKaryawan.status === 'menunggu_foreman' || matchedKaryawan.is_waiting_foreman ? '🔒 Buka Form (Menunggu Foreman)' : (matchedKaryawan.foreman_evaluated || (!matchedKaryawan.nrp_foreman && matchedKaryawan.kode_form !== '07-004') ? '✍️ Tinjau & Isi Nilai Section Head' : '🔒 Buka Form (Menunggu Foreman)')) : '✍️ Mulai Penilaian Sekarang'))))"></span>
                                <span>&rarr;</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </template>

        <!-- Not Found Notification -->
        <template x-if="notFoundMessage">
            <div class="mt-3 p-3 bg-rose-50 border border-rose-300 text-xs text-rose-800 flex items-center gap-2 font-medium rounded-none">
                <span>⚠️</span>
                <span x-text="notFoundMessage"></span>
            </div>
        </template>
    </div>
    @endif

    <!-- Daftar Karyawan & Penilaian (Tampilan Tabel Clean) -->
    <div class="space-y-3">

        @if($user->isAdmin())
        <!-- Toolbar Filter & Live Search Khusus Admin & Super Admin -->
        <div class="bg-white p-3.5 border border-slate-300 shadow-2xs space-y-3 rounded-none">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
                <!-- Filter Status Dropdown -->
                <div class="flex items-center gap-2 flex-1 max-w-sm">
                    <label for="adminStatusSelect" class="text-xs font-bold text-slate-700 whitespace-nowrap flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-[#92302e]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        <span>Filter Status:</span>
                    </label>
                    <select id="adminStatusSelect" 
                            x-model="adminFilter"
                            class="w-full px-3 py-1.5 text-xs font-semibold rounded-none border border-slate-300 hover:border-slate-400 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] bg-white text-slate-900 cursor-pointer shadow-2xs">
                        <option value="all">Semua ({{ $totalCount }})</option>
                        <option value="belum">⏳ Belum Dinilai ({{ $countAdminBelum ?? 0 }})</option>
                        <option value="foreman">✓ Sudah Dinilai Foreman ({{ $countAdminForeman ?? 0 }})</option>
                        <option value="section_head">✓ Sudah Dinilai Section Head ({{ $countAdminSection ?? 0 }})</option>
                        <option value="selesai">✓ Selesai Dept Head ({{ $countAdminSelesai ?? 0 }})</option>
                    </select>
                    <button type="button" 
                            x-show="adminFilter !== 'all'" 
                            @click="adminFilter = 'all'" 
                            title="Reset status ke Semua"
                            class="px-2 py-1.5 text-xs font-bold text-[#92302e] hover:text-[#7e2524] bg-rose-50 hover:bg-rose-100 border border-rose-200 rounded-none cursor-pointer">
                        ✕
                    </button>
                </div>

                <!-- Instant Search Box in Table -->
                <div class="relative w-full md:w-72">
                    <input type="text" x-model="adminSearch" placeholder="Filter nama, NRP, position..." 
                           class="w-full pl-8 pr-7 py-1.5 text-xs font-medium border border-slate-300 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] rounded-none">
                    <span class="absolute left-2.5 top-2 text-slate-400 text-xs pointer-events-none">🔍</span>
                    <button type="button" x-show="adminSearch" @click="adminSearch = ''" class="absolute right-2.5 top-1.5 text-slate-400 hover:text-slate-600 text-xs cursor-pointer">✕</button>
                </div>
            </div>
        </div>
        @endif

        @php
            $isDivHeadCari = $user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head']);
            $isForeman = $user->isForeman() || in_array($user->role, ['foreman', 'ketua']);
            $isSectionHead = $user->isSectionHead() || in_array($user->role, ['section_head', 'section', 'sechead']);
            $isDeptHead = $user->isDepartmentHead() || in_array($user->role, ['dept_head', 'department_head']);
            $hideSemuaCari = $isForeman || $isSectionHead || $isDeptHead;
            $useBagianFilter = $isForeman || $isSectionHead;
            $deptFilterOptions = collect($subordinates)->pluck('departemen')->map(fn ($v) => trim((string)$v))->filter(fn ($v) => $v !== '' && $v !== '-')->unique()->sort()->values()->all();
            $sectionFilterOptions = collect($subordinates)->pluck('seksi')->map(fn ($v) => trim((string)$v))->filter(fn ($v) => $v !== '' && $v !== '-')->unique()->sort()->values()->all();
            $bagianFilterOptions = collect($subordinates)->map(function ($k) {
                $nb = trim($k['nama_bagian'] ?? '');
                if (!$nb) {
                    $seksiUpper = strtoupper(trim($k['seksi'] ?? ''));
                    $nb = ($seksiUpper && $seksiUpper !== '-') ? $seksiUpper : 'FACTORY 1 SEC.';
                }
                return $nb;
            })->filter(fn ($v) => $v !== '' && $v !== '-')->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
            $kjFilterOptions = collect($subordinates)->pluck('kj')->map(fn ($v) => trim((string)$v))->filter(fn ($v) => $v !== '' && $v !== '-')->unique()->sort(SORT_NATURAL)->values()->all();
            $jabatanFilterOptions = collect($subordinates)->pluck('jabatan')->map(fn ($v) => trim((string)$v))->filter(fn ($v) => $v !== '' && $v !== '-')->unique()->sort()->values()->all();
        @endphp

        <div class="bg-white border border-slate-300 shadow-sm overflow-hidden rounded-none">
            <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex flex-col lg:flex-row lg:items-center justify-between gap-3 overflow-x-auto">
                <div class="flex items-center gap-2 shrink-0">
                    <h3 class="text-xs font-black text-slate-800 uppercase tracking-wider">Daftar Karyawan &amp; Formulir</h3>
                    <span class="px-2 py-0.5 text-[10px] font-bold bg-slate-200 text-slate-800 rounded-none font-mono">{{ $totalCount }} Karyawan</span>
                </div>

                <div class="flex flex-nowrap items-center gap-2 sm:gap-3 shrink-0">
                    @if($isDivHeadCari)
                    <!-- Filter Department (Division Head) -->
                    <div class="flex items-center gap-1.5 shrink-0">
                        <label for="cariFilterDept" class="text-[10px] font-bold text-slate-700 uppercase tracking-wider whitespace-nowrap">DEPARTMENT</label>
                        <select id="cariFilterDept" x-model="filterDept"
                                class="px-2.5 py-1.5 text-xs font-semibold bg-white border border-slate-300 hover:border-slate-400 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] text-slate-800 rounded-none cursor-pointer min-w-[130px] max-w-[180px] shadow-2xs transition">
                            <option value="">{{ $hideSemuaCari ? 'Department' : 'Semua Department' }}</option>
                            @foreach($deptFilterOptions as $optDept)
                                <option value="{{ $optDept }}">{{ $optDept }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif

                    @if($useBagianFilter)
                    <!-- Filter Bagian (Khusus Section Head & Foreman) -->
                    <div class="flex items-center gap-1.5 shrink-0">
                        <label for="cariFilterBagian" class="text-[10px] font-bold text-slate-700 uppercase tracking-wider whitespace-nowrap">BAGIAN</label>
                        <select id="cariFilterBagian" x-model="filterBagian"
                                class="px-2.5 py-1.5 text-xs font-semibold bg-white border border-slate-300 hover:border-slate-400 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] text-slate-800 rounded-none cursor-pointer min-w-[130px] max-w-[200px] shadow-2xs transition">
                            <option value="">{{ $hideSemuaCari ? 'Bagian' : 'Semua Bagian' }}</option>
                            @foreach($bagianFilterOptions as $optBagian)
                                <option value="{{ $optBagian }}">{{ $optBagian }}</option>
                            @endforeach
                        </select>
                    </div>
                    @else
                    <!-- Filter Section (Role selain Section Head & Foreman) -->
                    <div class="flex items-center gap-1.5 shrink-0">
                        <label for="cariFilterSection" class="text-[10px] font-bold text-slate-700 uppercase tracking-wider whitespace-nowrap">SECTION</label>
                        <select id="cariFilterSection" x-model="filterSection"
                                class="px-2.5 py-1.5 text-xs font-semibold bg-white border border-slate-300 hover:border-slate-400 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] text-slate-800 rounded-none cursor-pointer min-w-[130px] max-w-[180px] shadow-2xs transition">
                            <option value="">{{ $hideSemuaCari ? 'Section' : 'Semua Section' }}</option>
                            @foreach($sectionFilterOptions as $optSec)
                                <option value="{{ $optSec }}">{{ $optSec }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif

                    @if(!$isDivHeadCari)
                    <!-- Filter Position -->
                    <div class="flex items-center gap-1.5 shrink-0">
                        <label for="cariFilterJabatan" class="text-[10px] font-bold text-slate-700 uppercase tracking-wider whitespace-nowrap">POSITION</label>
                        <select id="cariFilterJabatan" x-model="filterJabatan"
                                class="px-2.5 py-1.5 text-xs font-semibold bg-white border border-slate-300 hover:border-slate-400 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] text-slate-800 rounded-none cursor-pointer min-w-[130px] max-w-[200px] shadow-2xs transition">
                            <option value="">{{ $hideSemuaCari ? 'Position' : 'Semua Position' }}</option>
                            @foreach($jabatanFilterOptions as $optJab)
                                <option value="{{ $optJab }}">{{ $optJab }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Filter KJ / Golongan -->
                    <div class="flex items-center gap-1.5 shrink-0">
                        <label for="cariFilterKj" class="text-[10px] font-bold text-slate-700 uppercase tracking-wider whitespace-nowrap">KJ / GOLONGAN</label>
                        <select id="cariFilterKj" x-model="filterKj"
                                class="px-2.5 py-1.5 text-xs font-semibold bg-white border border-slate-300 hover:border-slate-400 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] text-slate-800 rounded-none cursor-pointer min-w-[130px] max-w-[170px] shadow-2xs transition">
                            <option value="">{{ $hideSemuaCari ? 'KJ / Golongan' : 'Semua KJ / Golongan' }}</option>
                            @foreach($kjFilterOptions as $optKj)
                                <option value="{{ $optKj }}">{{ $optKj }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif

                    <button type="button"
                            x-show="filterDept || filterSection || filterBagian || filterJabatan || filterKj"
                            @click="filterDept = ''; filterSection = ''; filterBagian = ''; filterJabatan = ''; filterKj = ''"
                            class="px-2.5 py-1.5 text-xs font-bold border border-rose-300 bg-rose-50 hover:bg-rose-100 text-[#92302e] rounded-none cursor-pointer transition shadow-2xs shrink-0 flex items-center gap-1 whitespace-nowrap">
                        <span>✕</span> Reset
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-separate border-spacing-0 text-xs">
                    <thead>
                        <tr class="bg-slate-100 text-slate-700 font-bold text-[11px] uppercase tracking-wider sticky top-0 z-10 shadow-2xs">
                            <th class="py-2.5 px-2.5 border-b border-r border-slate-300 text-center w-10 whitespace-nowrap">NO</th>
                            <th class="py-2.5 px-3 border-b border-r border-slate-300 text-center w-24 whitespace-nowrap">NRP</th>
                            <th class="py-2.5 px-3 border-b border-r border-slate-300 min-w-[180px] whitespace-nowrap">NAMA LENGKAP</th>
                            <th class="py-2.5 px-3 border-b border-r border-slate-300 min-w-[150px] whitespace-nowrap">POSITION</th>
                            <th class="py-2.5 px-3 border-b border-r border-slate-300 text-center w-28 whitespace-nowrap">DEPARTMENT</th>
                            <th class="py-2.5 px-3 border-b border-r border-slate-300 text-center w-28 whitespace-nowrap">{{ $useBagianFilter ? 'BAGIAN' : 'SECTION' }}</th>
                            <th class="py-2.5 px-2.5 border-b border-r border-slate-300 text-center w-20 whitespace-nowrap">KJ</th>
                            <th class="py-2.5 px-2.5 border-b border-r border-slate-300 text-center w-24 whitespace-nowrap">GOLONGAN</th>
                            <th class="py-2.5 px-3 border-b border-r border-slate-300 min-w-[140px] whitespace-nowrap">FORMULIR</th>
                            <th class="py-2.5 px-3 border-b border-r border-slate-300 min-w-[150px] whitespace-nowrap">PROGRESS / STATUS</th>
                            <th class="py-2.5 px-3 border-b border-slate-300 text-center w-36 whitespace-nowrap">AKSI</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($subordinates as $sub)
                        @php
                            $isSelf = !empty($sub['is_self']) || ($sub['nrp'] === $user->nrp);
                            $bagianVal = trim($sub['nama_bagian'] ?? '');
                            if (!$bagianVal) {
                                $seksiUpper = strtoupper(trim($sub['seksi'] ?? ''));
                                $bagianVal = ($seksiUpper && $seksiUpper !== '-') ? $seksiUpper : 'FACTORY 1 SEC.';
                            }
                            $searchTokens = strtolower(addslashes(trim($sub['nrp'] . ' ' . $sub['nama_lengkap'] . ' ' . $sub['jabatan'] . ' ' . ($sub['departemen'] ?? '') . ' ' . $sub['seksi'] . ' ' . $bagianVal . ' ' . ($sub['kj'] ?? ''))));
                            $rowKj = addslashes(trim((string)($sub['kj'] ?? '')));
                            $rowJab = addslashes(trim((string)($sub['jabatan'] ?? '')));
                            $rowSec = addslashes(trim((string)($sub['seksi'] ?? '')));
                            $rowDept = addslashes(trim((string)($sub['departemen'] ?? '')));
                            $rowBagian = addslashes($bagianVal);
                        @endphp
                        <tr x-show="matchesRowFilter('{{ $sub['admin_status_category'] ?? 'belum' }}', '{{ $searchTokens }}', '{{ $rowKj }}', '{{ $rowJab }}', '{{ $rowSec }}', '{{ $rowDept }}', '{{ $rowBagian }}')"
                            class="{{ !empty($sub['can_evaluate_by_role']) ? ($isSelf ? 'bg-rose-50/60 font-medium' : 'bg-rose-50/25 hover:bg-rose-50/50') : ($isSelf ? 'bg-rose-50/40' : 'bg-white hover:bg-slate-50/90') }} transition-colors">
                            <td class="py-2.5 px-2.5 border-b border-r border-slate-200 text-center font-bold text-slate-700 whitespace-nowrap">
                                {{ $loop->iteration }}
                            </td>

                            <td class="py-2.5 px-3 border-b border-r border-slate-200 text-center whitespace-nowrap">
                                <span class="px-2 py-0.5 font-mono font-bold text-[11px] {{ $isSelf ? 'bg-[#92302e] text-white' : 'bg-slate-100 text-slate-800 border border-slate-300' }} rounded-none inline-block">
                                    {{ $sub['nrp'] }}
                                </span>
                            </td>

                            <td class="py-2.5 px-3 border-b border-r border-slate-200 whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5">
                                    <span class="font-bold text-slate-900">{{ $sub['nama_lengkap'] }}</span>
                                    @if($isSelf)
                                        <span class="px-1.5 py-0.5 text-[9.5px] font-extrabold bg-[#fbe8e8] text-[#92302e] border border-[#ce5d5b]/40 rounded-none shrink-0" title="Penilaian Mandiri (Anda)">
                                            (Anda)
                                        </span>
                                    @endif
                                </div>
                            </td>

                            <td class="py-2.5 px-3 border-b border-r border-slate-200 font-semibold text-slate-800 whitespace-nowrap">
                                {{ $sub['jabatan'] }}
                            </td>

                            <td class="py-2.5 px-3 border-b border-r border-slate-200 text-center font-bold text-slate-700 whitespace-nowrap">
                                {{ $sub['departemen'] ?? '-' }}
                            </td>

                            <td class="py-2.5 px-3 border-b border-r border-slate-200 text-center whitespace-nowrap">
                                <span class="px-2 py-0.5 text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200 inline-block whitespace-nowrap">
                                    {{ $useBagianFilter ? $bagianVal : $sub['seksi'] }}
                                </span>
                            </td>

                            <td class="py-2.5 px-2.5 border-b border-r border-slate-200 text-center font-bold text-slate-800 whitespace-nowrap">
                                {{ $sub['kj'] }}
                            </td>

                            <td class="py-2.5 px-2.5 border-b border-r border-slate-200 text-center font-semibold text-slate-700 whitespace-nowrap">
                                {{ $sub['sub_gol'] }}
                            </td>

                            <td class="py-2.5 px-3 border-b border-r border-slate-200 whitespace-nowrap">
                                @php
                                    $displayFormName = preg_replace('/\s*\(07-\d+\)\s*$/', '', trim((string)($sub['form_name'] ?? '')));
                                    if (!empty($sub['is_secretary']) || str_contains(strtoupper($sub['seksi'] ?? ''), 'SECRETARY') || in_array((string)($sub['nrp'] ?? ''), ['268', '1237'])) {
                                        $displayFormName = 'Form Secretary';
                                    }
                                @endphp
                                <span class="inline-block text-[10.5px] font-bold px-2 py-0.5 bg-slate-100 text-slate-700 border border-slate-200 rounded-none">
                                    {{ $displayFormName }}
                                </span>
                            </td>

                            <td class="py-2.5 px-3 border-b border-r border-slate-200 whitespace-nowrap">
                                <div class="space-y-1">
                                    @if($isSelf)
                                        @if($sub['status'] === 'selesai')
                                            <span class="px-2 py-0.5 font-bold text-[10px] bg-emerald-50 text-emerald-800 border border-emerald-300 rounded-none inline-flex items-center whitespace-nowrap">
                                                ✓ Selesai
                                            </span>
                                        @elseif($sub['status'] === 'menunggu_div_head')
                                            <span class="px-2 py-0.5 font-bold text-[10px] bg-teal-50 text-teal-800 border border-teal-300 rounded-none inline-flex items-center whitespace-nowrap">
                                                ✓ Terkirim ke Div Head
                                            </span>
                                        @elseif(($sub['has_section_head_sign'] || $sub['section_head_evaluated'] || $sub['has_foreman_sign']) && $sub['status'] !== 'menunggu_section_head')
                                            <span class="px-2 py-0.5 font-bold text-[10px] bg-teal-50 text-teal-800 border border-teal-300 rounded-none inline-flex items-center whitespace-nowrap">
                                                ✓ Terkirim ke Dept Head
                                            </span>
                                        @else
                                            <span class="px-2 py-0.5 font-bold text-[10px] bg-amber-50 text-amber-900 border border-amber-300 rounded-none inline-flex items-center whitespace-nowrap">
                                                ✍️ Evaluasi Mandiri (Belum Dinilai)
                                            </span>
                                        @endif
                                    @elseif($user->isAdmin())
                                        @if($sub['status'] === 'selesai' || $sub['has_dept_head_sign'])
                                            <span class="px-2 py-0.5 font-bold text-[10px] bg-emerald-50 text-emerald-800 border border-emerald-300 rounded-none inline-flex items-center whitespace-nowrap">✓ Sudah Dinilai Dept Head (Selesai)</span>
                                        @elseif($sub['status'] === 'revisi')
                                            <span class="px-2 py-0.5 font-bold text-[10px] bg-rose-50 text-rose-800 border border-rose-300 rounded-none inline-flex items-center whitespace-nowrap">↩️ Perlu Revisi</span>
                                        @elseif($sub['status'] === 'menunggu_div_head')
                                            <span class="px-2 py-0.5 font-bold text-[10px] bg-amber-50 text-amber-900 border border-amber-300 rounded-none inline-flex items-center whitespace-nowrap">🔔 Menunggu Div Head</span>
                                        @elseif($sub['status'] === 'menunggu_dept_head')
                                            <span class="px-2 py-0.5 font-bold text-[10px] {{ ($sub['section_head_evaluated'] || $sub['has_section_head_sign']) ? 'bg-teal-50 text-teal-800 border-teal-300' : 'bg-amber-50 text-amber-900 border-amber-300' }} rounded-none inline-flex items-center whitespace-nowrap">
                                                {{ ($sub['section_head_evaluated'] || $sub['has_section_head_sign']) ? '🔔 Menunggu Dept Head (✓ SH Dinilai)' : '🔔 Menunggu Dept Head' }}
                                            </span>
                                        @elseif($sub['status'] === 'menunggu_section_head')
                                            <span class="px-2 py-0.5 font-bold text-[10px] {{ (!empty($sub['nrp_foreman']) && ($sub['foreman_evaluated'] || $sub['has_foreman_sign'])) ? 'bg-blue-50 text-blue-800 border-blue-300' : 'bg-amber-50 text-amber-800 border-amber-300' }} rounded-none inline-flex items-center whitespace-nowrap">
                                                {{ (!empty($sub['nrp_foreman']) && ($sub['foreman_evaluated'] || $sub['has_foreman_sign'])) ? '⏳ Menunggu Section Head (✓ Foreman Dinilai)' : '⏳ Menunggu Section Head' }}
                                            </span>
                                        @elseif($sub['status'] === 'menunggu_foreman')
                                            <span class="px-2 py-0.5 font-bold text-[10px] bg-amber-50 text-amber-800 border border-amber-300 rounded-none inline-flex items-center whitespace-nowrap">⏳ Menunggu Foreman</span>
                                        @else
                                            <span class="px-2 py-0.5 font-bold text-[10px] bg-slate-100 text-slate-700 border border-slate-300 rounded-none inline-flex items-center whitespace-nowrap">⏳ Belum Dinilai</span>
                                        @endif
                                        <div class="text-[10px] text-slate-600 leading-tight whitespace-nowrap">
                                            @if(!empty($sub['nrp_foreman']))
                                                F: {{ $sub['foreman_evaluated'] ? ($sub['foreman_grade'] ?? '✓') : '-' }} &bull;
                                            @endif
                                            @if(!empty($sub['nrp_section_head']) || in_array($sub['level_form'] ?? '', ['KARYAWAN_GOL4', 'KASIE']))
                                                SH: {{ $sub['section_head_evaluated'] ? ($sub['section_head_grade'] ?? '✓') : '-' }} &bull;
                                            @endif
                                            DH: {{ ($sub['has_dept_head_sign'] || $sub['status'] === 'selesai') ? ($sub['dept_grade'] ?? '✓') : '-' }}
                                        </div>
                                    @elseif($user->isDepartmentHead())
                                        @if($sub['status'] === 'selesai')
                                            <span class="px-2 py-0.5 font-bold text-[10px] bg-emerald-50 text-emerald-800 border border-emerald-300 rounded-none inline-flex items-center whitespace-nowrap">✓ Selesai Dept Head</span>
                                        @elseif($sub['status'] === 'menunggu_div_head' || $sub['has_dept_head_sign'])
                                            <span class="px-2 py-0.5 font-bold text-[10px] bg-emerald-50 text-emerald-800 border border-emerald-300 rounded-none inline-flex items-center whitespace-nowrap">✓ Menunggu Approval Div Head</span>
                                        @elseif($sub['status'] === 'menunggu_dept_head')
                                            <span class="px-2 py-0.5 font-bold text-[10px] bg-amber-50 text-amber-900 border border-amber-300 rounded-none inline-flex items-center whitespace-nowrap">🔔 Perlu Dinilai Dept Head</span>
                                        @elseif($sub['status'] === 'revisi')
                                            <span class="px-2 py-0.5 font-bold text-[10px] bg-rose-50 text-rose-800 border border-rose-300 rounded-none inline-flex items-center whitespace-nowrap">↩️ Perlu Revisi</span>
                                        @elseif(!empty($sub['is_waiting_foreman']) || $sub['status'] === 'menunggu_foreman')
                                            <span class="px-2 py-0.5 font-bold text-[10px] bg-slate-100 text-slate-600 border border-slate-300 rounded-none inline-flex items-center whitespace-nowrap">⏳ Menunggu Penilaian Foreman</span>
                                        @elseif(!empty($sub['is_waiting_section']) || $sub['status'] === 'menunggu_section_head')
                                            <span class="px-2 py-0.5 font-bold text-[10px] bg-slate-100 text-slate-600 border border-slate-300 rounded-none inline-flex items-center whitespace-nowrap">⏳ Menunggu Penilaian Section Head</span>
                                        @else
                                            <span class="px-2 py-0.5 font-bold text-[10px] bg-slate-100 text-slate-600 border border-slate-300 rounded-none inline-flex items-center whitespace-nowrap">⏳ Belum Dinilai</span>
                                        @endif
                                    @elseif($user->isSectionHead())
                                        @if($isSelf)
                                            @if($sub['status'] === 'selesai' || $sub['section_head_evaluated'] || $sub['has_section_head_sign'])
                                                <span class="px-2 py-0.5 font-bold text-[10px] bg-emerald-50 text-emerald-800 border border-emerald-300 rounded-none inline-flex items-center whitespace-nowrap">✓ Mandiri Terisi (Proses Dept Head)</span>
                                            @else
                                                <span class="px-2 py-0.5 font-bold text-[10px] bg-amber-50 text-amber-800 border border-amber-300 rounded-none inline-flex items-center whitespace-nowrap">✍️ Evaluasi Mandiri (Belum Dinilai)</span>
                                            @endif
                                        @elseif($sub['status'] === 'selesai')
                                            <span class="px-2 py-0.5 font-bold text-[10px] bg-emerald-50 text-emerald-800 border border-emerald-300 rounded-none inline-flex items-center whitespace-nowrap">✓ Selesai</span>
                                        @elseif($sub['status'] === 'revisi')
                                            <span class="px-2 py-0.5 font-bold text-[10px] bg-rose-50 text-rose-800 border border-rose-300 rounded-none inline-flex items-center whitespace-nowrap">↩️ Perlu Revisi</span>
                                        @elseif($sub['section_head_evaluated'] || $sub['has_section_head_sign'])
                                            <span class="px-2 py-0.5 font-bold text-[10px] bg-emerald-50 text-emerald-800 border border-emerald-300 rounded-none inline-flex items-center whitespace-nowrap">✓ Selesai Section Head (Proses Dept Head)</span>
                                        @elseif(!empty($sub['is_waiting_foreman']) || $sub['status'] === 'menunggu_foreman')
                                            <span class="px-2 py-0.5 font-bold text-[10px] bg-amber-50 text-amber-800 border border-amber-300 rounded-none inline-flex items-center whitespace-nowrap">⏳ Menunggu Foreman</span>
                                        @else
                                            @if($sub['foreman_evaluated'] || $sub['has_foreman_sign'])
                                                <span class="px-2 py-0.5 font-bold text-[10px] bg-amber-50 text-amber-800 border border-amber-300 rounded-none inline-flex items-center whitespace-nowrap">🔔 Perlu Dinilai Section Head (✓ Foreman Grade {{ $sub['foreman_grade'] ?? '✓' }})</span>
                                            @else
                                                <span class="px-2 py-0.5 font-bold text-[10px] bg-amber-50 text-amber-800 border border-amber-300 rounded-none inline-flex items-center whitespace-nowrap">🔔 Perlu Dinilai Section Head</span>
                                            @endif
                                        @endif
                                    @elseif($user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head']))
                                        @if($sub['status'] === 'selesai')
                                            <span class="px-2 py-0.5 font-bold text-[10px] bg-emerald-50 text-emerald-800 border border-emerald-300 rounded-none inline-flex items-center whitespace-nowrap">✓ Disahkan Final (Selesai)</span>
                                        @elseif($sub['status'] === 'menunggu_div_head')
                                            <span class="px-2 py-0.5 font-bold text-[10px] bg-amber-50 text-amber-900 border border-amber-300 rounded-none inline-flex items-center whitespace-nowrap">🔔 Menunggu Div Head</span>
                                        @elseif($sub['status'] === 'menunggu_dept_head')
                                            <span class="px-2 py-0.5 font-bold text-[10px] bg-blue-50 text-blue-800 border border-blue-300 rounded-none inline-flex items-center whitespace-nowrap">⏳ Proses Dept Head</span>
                                        @elseif($sub['status'] === 'menunggu_section_head')
                                            <span class="px-2 py-0.5 font-bold text-[10px] bg-slate-100 text-slate-700 border border-slate-300 rounded-none inline-flex items-center whitespace-nowrap">⏳ Proses Section Head</span>
                                        @elseif($sub['status'] === 'menunggu_foreman')
                                            <span class="px-2 py-0.5 font-bold text-[10px] bg-slate-100 text-slate-700 border border-slate-300 rounded-none inline-flex items-center whitespace-nowrap">⏳ Proses Foreman</span>
                                        @else
                                            <span class="px-2 py-0.5 font-bold text-[10px] bg-slate-100 text-slate-600 border border-slate-300 rounded-none inline-flex items-center whitespace-nowrap">⏳ Belum Dinilai</span>
                                        @endif
                                    @else
                                        @if($sub['status'] === 'selesai')
                                            <span class="px-2 py-0.5 font-bold text-[10px] bg-emerald-50 text-emerald-800 border border-emerald-300 rounded-none inline-flex items-center whitespace-nowrap">✓ Selesai</span>
                                        @elseif($sub['foreman_evaluated'] || $sub['has_foreman_sign'])
                                            <span class="px-2 py-0.5 font-bold text-[10px] bg-emerald-50 text-emerald-800 border border-emerald-300 rounded-none inline-flex items-center whitespace-nowrap">✓ Selesai Dinilai Foreman</span>
                                        @elseif($sub['status'] === 'revisi')
                                            <span class="px-2 py-0.5 font-bold text-[10px] bg-rose-50 text-rose-800 border border-rose-300 rounded-none inline-flex items-center whitespace-nowrap">↩️ Perlu Revisi</span>
                                        @else
                                            <span class="px-2 py-0.5 font-bold text-[10px] bg-amber-50 text-amber-800 border border-amber-300 rounded-none inline-flex items-center whitespace-nowrap">🔔 Perlu Dinilai Foreman</span>
                                        @endif
                                    @endif

                                    @if(!empty($sub['is_kj_locked']) && !$isSelf && $sub['status'] !== 'selesai')
                                        <div class="p-1.5 bg-rose-50 border border-rose-300 text-[10px] text-rose-900 rounded-none whitespace-nowrap">
                                            <span class="font-bold">⛔ Kuota {{ $sub['quota_kj'] ?? $sub['kj'] }} Penuh / Over</span>
                                        </div>
                                    @endif
                                </div>
                            </td>

                            <td class="py-2.5 px-3 border-b border-slate-200 text-center whitespace-nowrap">
                                <div class="inline-flex flex-col items-stretch gap-1 w-full">
                                    <form method="POST" action="{{ route('penilaian.buka') }}">
                                        @csrf
                                        <input type="hidden" name="nrp" value="{{ $sub['nrp'] }}">
                                        <button type="submit"
                                                title="{{ $isSelf ? ('Buka Penilaian Mandiri (Form ' . $sub['kode_form'] . ')') : ('Buka Form ' . ($sub['kode_form'] ?? '')) }}"
                                                class="w-full px-3 py-1.5 text-[11px] font-bold transition-all shadow-2xs flex items-center justify-center gap-1 rounded-none cursor-pointer
                                            @if(!empty($sub['can_evaluate_by_role']))
                                                bg-[#92302e] hover:bg-[#7e2524] text-white shadow-xs
                                            @elseif(!empty($sub['is_kj_locked']) && $sub['status'] !== 'selesai')
                                                bg-rose-100 hover:bg-rose-200 text-rose-900 border border-rose-300
                                            @elseif($user->isAdmin())
                                                bg-[#92302e] hover:bg-[#7e2524] text-white
                                            @else
                                                bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300
                                            @endif">
                                            @if($isSelf)
                                                <span>{{ $sub['status'] === 'selesai' ? '👁️ Lihat Form Mandiri (Selesai)' : '✍️ Nilai Mandiri' }}</span>
                                            @elseif(!empty($sub['is_kj_locked']) && $sub['status'] !== 'selesai')
                                                <span>🔒 Buka Form (Kuota {{ $sub['quota_kj'] ?? $sub['kj'] }} Penuh)</span>
                                            @elseif($user->isDepartmentHead())
                                                <span>{{ $sub['status'] === 'menunggu_dept_head' ? '✍️ Nilai Dept Head' : (!empty($sub['is_waiting_foreman']) ? '🔒 Buka Form (Menunggu Foreman)' : (!empty($sub['is_waiting_section']) ? '🔒 Buka Form (Menunggu Section)' : ($sub['status'] === 'selesai' ? '👁️ Lihat Form Selesai' : '👁️ Buka Form Penilaian'))) }}</span>
                                            @elseif($user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head']))
                                                <span>{{ $sub['status'] === 'menunggu_div_head' ? '✍️ Tinjau & Final Approval' : ($sub['status'] === 'selesai' ? '👁️ Lihat Form Selesai' : ($sub['status'] === 'menunggu_dept_head' ? '🔒 Buka Form (Menunggu Dept Head)' : ($sub['status'] === 'menunggu_section_head' ? '🔒 Buka Form (Menunggu Section)' : ($sub['status'] === 'menunggu_foreman' ? '🔒 Buka Form (Menunggu Foreman)' : '👁️ Buka Form Penilaian')))) }}</span>
                                            @elseif($user->isSectionHead())
                                                @if($sub['section_head_evaluated'] || $sub['has_section_head_sign'])
                                                    <span>👁️ Buka Formulir</span>
                                                @elseif(!empty($sub['is_waiting_foreman']) || $sub['status'] === 'menunggu_foreman')
                                                    <span>🔒 Buka Form (Menunggu Foreman)</span>
                                                @elseif($sub['status'] === 'selesai')
                                                    <span>👁️ Lihat Form Selesai</span>
                                                @else
                                                    <span>✍️ Nilai Section Head</span>
                                                @endif
                                            @elseif($user->isAdmin())
                                                <span>{{ $sub['status'] === 'selesai' ? '👁️ Lihat Form Selesai' : '👁️ Buka Form Penilaian' }}</span>
                                            @else
                                                <span>{{ (!$sub['foreman_evaluated'] && in_array($sub['status'], ['draft', 'menunggu_foreman', 'belum_dinilai'])) ? '✍️ Nilai Karyawan' : '👁️ Buka Formulir' }}</span>
                                            @endif
                                        </button>
                                    </form>

                                    @if(!empty($sub['is_kj_locked']) && !$isSelf && $sub['status'] !== 'selesai' && ($user->isDepartmentHead() || in_array($user->role, ['dept_head', 'department_head']) || $user->isAdmin()))
                                    <a href="{{ route('penilaian.dept.penyesuaian', ['filter_kj' => $sub['quota_kj'] ?? $sub['kj']]) }}" 
                                       class="block text-center py-1 px-2 bg-[#92302e] hover:bg-[#7e2524] text-white text-[10.5px] font-bold rounded-none shadow-2xs transition">
                                        ⚖️ Lakukan Penyesuaian Kuota {{ $sub['quota_kj'] ?? $sub['kj'] }} &rarr;
                                    </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="11" class="py-10 bg-white text-center text-slate-400 text-xs font-medium">
                                Belum ada data karyawan bawahan.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

@push('scripts')
<script>
    function nrpSearchHandler() {
        return {
            nrpInput: '',
            matchedKaryawan: null,
            notFoundMessage: null,
            isLoading: false,
            isSectionHead: {{ $user->isSectionHead() ? 'true' : 'false' }},
            isDeptHead: {{ $user->isDepartmentHead() ? 'true' : 'false' }},
            isDivHead: {{ ($user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head'])) ? 'true' : 'false' }},
            isAdmin: {{ $user->isAdmin() ? 'true' : 'false' }},
            adminFilter: 'all',
            adminSearch: '',
            filterDept: '',
            filterSection: '',
            filterBagian: '',
            filterKj: '',
            filterJabatan: '',

            matchesRowFilter(cat, searchStr, kj = '', jabatan = '', section = '', dept = '', bagian = '') {
                if (this.filterDept && dept.toUpperCase() !== this.filterDept.toUpperCase()) {
                    return false;
                }
                if (this.filterSection && section.toUpperCase() !== this.filterSection.toUpperCase()) {
                    return false;
                }
                if (this.filterBagian && bagian.toUpperCase() !== this.filterBagian.toUpperCase()) {
                    return false;
                }
                if (this.filterKj && kj.toUpperCase() !== this.filterKj.toUpperCase()) {
                    return false;
                }
                if (this.filterJabatan && jabatan.toUpperCase() !== this.filterJabatan.toUpperCase()) {
                    return false;
                }
                if (this.isAdmin) {
                    if (this.adminFilter !== 'all' && this.adminFilter !== cat) {
                        return false;
                    }
                    if (this.adminSearch && this.adminSearch.trim() !== '') {
                        const q = this.adminSearch.trim().toLowerCase();
                        return searchStr.toLowerCase().indexOf(q) !== -1;
                    }
                    return true;
                }
                return true;
            },

            matchesAdminFilter(cat, searchStr, kj = '', jabatan = '', section = '', dept = '', bagian = '') {
                return this.matchesRowFilter(cat, searchStr, kj, jabatan, section, dept, bagian);
            },

            lookupKaryawan() {
                const q = this.nrpInput.trim();
                if (!q || q.length < 2) {
                    this.matchedKaryawan = null;
                    this.notFoundMessage = null;
                    return;
                }

                this.isLoading = true;
                this.notFoundMessage = null;

                fetch(`/api/karyawan-lookup?nrp=${encodeURIComponent(q)}`)
                    .then(res => res.json())
                    .then(data => {
                        this.isLoading = false;
                        if (data.found && data.karyawan) {
                            this.matchedKaryawan = data.karyawan;
                            this.notFoundMessage = null;
                        } else {
                            this.matchedKaryawan = null;
                            this.notFoundMessage = data.message || `Karyawan dengan NRP '${q}' tidak ditemukan.`;
                        }
                    })
                    .catch(() => {
                        this.isLoading = false;
                        this.matchedKaryawan = null;
                    });
            },

            submitForm(e) {
                if (this.matchedKaryawan) {
                    e.target.submit();
                }
            }
        };
    }
</script>
@endpush
@endsection
