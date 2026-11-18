@extends('layouts.app', ['title' => 'Data Karyawan Seluruh Divisi - Admin'])

@section('content')
<div class="space-y-6" x-data="{ 
    addModal: false, 
    uploadModal: false,
    showEditModal: false,
    deleteBulkModal: false,
    deleteScope: '{{ ($search || $filterDivisi || $filterDept || !empty($filterSeksi) || $filterGol || $filterAtasan) ? 'filtered' : 'all' }}',
    confirmDeleteText: '',
    editKaryawan: {
        nrp: '',
        nama_lengkap: '',
        jabatan: '',
        tgl_masuk: '',
        golongan: 'GOLONGAN II',
        sub_gol: 'IIB',
        kj: 'KJ2',
        division: '',
        departemen: 'PROD 2',
        seksi: '',
        code_bagian: '',
        nama_bagian: '',
        nama_sect: ''
    }
}">

    <!-- Header Section Card (Musashi Red Banner, Solid & Clean) -->
    <div class="bg-gradient-to-r from-[#962226] via-[#92302e] to-[#781b1f] text-white rounded-none p-5 sm:p-6 border border-[#781b1f] shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight">
                Data Karyawan
            </h1>
            <p class="text-xs text-red-100 font-medium mt-0.5">
                Daftar profil seluruh karyawan yang dievaluasi kinerjanya
            </p>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap shrink-0">
            <!-- + Tambah Karyawan -->
            <button @click="addModal = true" type="button"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white text-[#92302e] hover:bg-slate-100 text-xs font-bold shadow-xs transition cursor-pointer">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                <span>Tambah Karyawan</span>
            </button>

            <!-- Upload Sheet -->
            <button @click="uploadModal = true" type="button"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white text-slate-700 hover:bg-slate-50 text-xs font-bold shadow-xs transition cursor-pointer">
                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                </svg>
                <span>Upload Sheet</span>
            </button>

            <!-- Sinkronkan Google Spreadsheet -->
            <form method="POST" action="{{ route('admin.google.sync') }}" class="inline m-0 p-0">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold shadow-xs transition cursor-pointer"
                        title="Tarik & selaraskan data terbaru dari Google Spreadsheet ke aplikasi">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    <span>Sinkronkan Spreadsheet</span>
                </button>
            </form>

            <!-- Export -->
            <a href="{{ route('laporan.exportExcel') }}"
               class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white text-slate-700 hover:bg-slate-50 text-xs font-bold shadow-xs transition cursor-pointer">
                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                </svg>
                <span>Export</span>
            </a>

            <!-- Hapus Data Karyawan (Massal / Filter) -->
            <button @click="
                        deleteScope = '{{ ($search || $filterDivisi || $filterDept || !empty($filterSeksi) || $filterGol || $filterAtasan) ? 'filtered' : 'all' }}';
                        confirmDeleteText = '';
                        deleteBulkModal = true;
                    "
                    type="button"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-rose-950/70 hover:bg-rose-950 text-rose-100 border border-rose-400/40 text-xs font-bold shadow-xs transition cursor-pointer"
                    title="Hapus data karyawan secara massal (berdasarkan filter aktif atau hapus seluruh data)">
                <svg class="w-3.5 h-3.5 text-rose-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                @if($search || $filterDivisi || $filterDept || !empty($filterSeksi) || $filterGol || $filterAtasan)
                    <span>Hapus Terfilter ({{ $totalCount }})</span>
                @else
                    <span>Hapus Semua Karyawan</span>
                @endif
            </button>
        </div>
    </div>

    <!-- 5 Metric Cards: Total Karyawan, Total BAGIAN, Total SECT., Total DEPT., Total JABATAN -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        
        <!-- 1. Total Karyawan (Clean Grey Border Matching Gambar 2) -->
        <a href="{{ route('admin.karyawan') }}" 
           class="bg-white p-3.5 border border-slate-300 shadow-xs hover:border-slate-400 transition group cursor-pointer"
           title="Tampilkan seluruh data karyawan">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-700 block">Total Karyawan</span>
            <div class="mt-1.5 flex items-baseline gap-1.5">
                <span class="text-2xl font-black text-slate-900">{{ $hierarchySummary['total_karyawan'] }}</span>
                <span class="text-xs font-semibold text-slate-500">karyawan</span>
            </div>
            <div class="text-[10px] text-slate-500 mt-1 truncate font-medium">Karyawan terdaftar</div>
        </a>

        <!-- 2. Total BAGIAN -->
        <div class="bg-white p-3.5 border border-slate-300 shadow-xs transition" title="Jumlah total bagian kerja">
            <span class="text-[10px] font-black uppercase tracking-wider text-blue-700 block">Total BAGIAN</span>
            <div class="mt-1.5 flex items-baseline gap-1.5">
                <span class="text-2xl font-black text-slate-900">{{ $hierarchySummary['total_bagian'] }}</span>
                <span class="text-xs font-semibold text-blue-600">bagian</span>
            </div>
            <div class="text-[10px] text-slate-500 mt-1 truncate font-medium">Bagian / lini kerja</div>
        </div>

        <!-- 3. Total SECTION -->
        <div class="bg-white p-3.5 border border-slate-300 shadow-xs transition" title="Jumlah total section organisasi">
            <span class="text-[10px] font-black uppercase tracking-wider text-teal-700 block">Total SECTION</span>
            <div class="mt-1.5 flex items-baseline gap-1.5">
                <span class="text-2xl font-black text-slate-900">{{ $hierarchySummary['total_sect'] }}</span>
                <span class="text-xs font-semibold text-teal-600">section</span>
            </div>
            <div class="text-[10px] text-slate-500 mt-1 truncate font-medium">Section organisasi</div>
        </div>

        <!-- 4. Total DEPARTMENT -->
        <div class="bg-white p-3.5 border border-slate-300 shadow-xs transition" title="Jumlah total department">
            <span class="text-[10px] font-black uppercase tracking-wider text-amber-700 block">Total DEPARTMENT</span>
            <div class="mt-1.5 flex items-baseline gap-1.5">
                <span class="text-2xl font-black text-slate-900">{{ $hierarchySummary['total_dept'] }}</span>
                <span class="text-xs font-semibold text-amber-600">department</span>
            </div>
            <div class="text-[10px] text-slate-500 mt-1 truncate font-medium">Department aktif</div>
        </div>

        <!-- 5. Total POSITION -->
        <div class="bg-white p-3.5 border border-slate-300 shadow-xs transition" title="Jumlah total position karyawan">
            <span class="text-[10px] font-black uppercase tracking-wider text-purple-700 block">Total POSITION</span>
            <div class="mt-1.5 flex items-baseline gap-1.5">
                <span class="text-2xl font-black text-slate-900">{{ $hierarchySummary['total_jabatan'] }}</span>
                <span class="text-xs font-semibold text-purple-600">position</span>
            </div>
            <div class="text-[10px] text-slate-500 mt-1 truncate font-medium">Posisi / peran kerja</div>
        </div>

    </div>

    <!-- Filter & Search Bar with Clean Slate Borders (Meniru Gambar 2 & Manajemen Akun) -->
    <div class="bg-white border border-slate-300 shadow-sm p-4 overflow-x-auto">
        <form method="GET" action="{{ route('admin.karyawan') }}" class="flex flex-nowrap items-end gap-3 min-w-max">
            
            <!-- Search Input -->
            <div class="w-64 sm:w-72 shrink-0">
                <label class="block text-[10px] font-bold text-slate-700 uppercase tracking-wider mb-1">Cari Karyawan</label>
                <div class="relative">
                    <input type="text" name="q" value="{{ $search }}" placeholder="Cari nama, NRP, position (Tekan Enter)..."
                           onchange="this.form.submit()"
                           class="w-full text-xs font-semibold text-slate-800 border border-slate-300 hover:border-slate-400 bg-white py-2 pl-8 pr-3 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-xs">
                    <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
            </div>

            <!-- Filter Division -->
            <div class="w-44 shrink-0">
                <label class="block text-[10px] font-bold text-slate-700 uppercase tracking-wider mb-1">Division</label>
                <select name="divisi" onchange="this.form.submit()" class="w-full text-xs font-semibold text-slate-700 border border-slate-300 hover:border-slate-400 bg-white py-2 px-2.5 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-xs cursor-pointer">
                    <option value="">Semua Division</option>
                    @foreach($divisiList as $div)
                    <option value="{{ $div }}" {{ $filterDivisi === $div ? 'selected' : '' }}>{{ $div }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Filter Department -->
            <div class="w-44 shrink-0">
                <label class="block text-[10px] font-bold text-slate-700 uppercase tracking-wider mb-1">Department</label>
                <select name="departemen" onchange="this.form.submit()" class="w-full text-xs font-semibold text-slate-700 border border-slate-300 hover:border-slate-400 bg-white py-2 px-2.5 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-xs cursor-pointer">
                    <option value="">Semua Department</option>
                    @foreach($deptList as $d)
                    <option value="{{ $d }}" {{ $filterDept === $d ? 'selected' : '' }}>{{ $d }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Filter Section -->
            <div class="w-40 shrink-0">
                <label class="block text-[10px] font-bold text-slate-700 uppercase tracking-wider mb-1">Section</label>
                <select name="seksi" onchange="this.form.submit()" class="w-full text-xs font-semibold text-slate-700 border border-slate-300 hover:border-slate-400 bg-white py-2 px-2.5 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-xs cursor-pointer">
                    <option value="">Semua Section</option>
                    @foreach($seksiList ?? [] as $s)
                    <option value="{{ $s }}" {{ ($filterSeksi ?? '') === $s ? 'selected' : '' }}>{{ $s }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Filter KJ / Golongan -->
            <div class="w-36 shrink-0">
                <label class="block text-[10px] font-bold text-slate-700 uppercase tracking-wider mb-1">KJ / Golongan</label>
                <select name="golongan" onchange="this.form.submit()" class="w-full text-xs font-semibold text-slate-700 border border-slate-300 hover:border-slate-400 bg-white py-2 px-2.5 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] transition shadow-xs cursor-pointer">
                    <option value="">Semua KJ / Golongan</option>
                    @foreach($golList as $g)
                    <option value="{{ $g }}" {{ $filterGol === $g ? 'selected' : '' }}>{{ $g }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Hidden Submit for Enter Key & Action Shortcuts -->
            <button type="submit" class="hidden" tabindex="-1"></button>

            @if($search || $filterDivisi || $filterDept || !empty($filterSeksi) || $filterGol || $filterAtasan)
            <div class="flex items-center gap-1.5 shrink-0">
                <a href="{{ route('admin.karyawan') }}"
                   class="px-3.5 py-2 border border-rose-300 bg-rose-50 text-[#92302e] text-xs font-bold hover:bg-rose-100 transition shadow-2xs flex items-center gap-1 whitespace-nowrap">
                    <span>✕</span> Reset Filter
                </a>
                <button type="button"
                        @click="deleteScope = 'filtered'; confirmDeleteText = ''; deleteBulkModal = true;"
                        class="px-3.5 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-bold transition shadow-xs flex items-center gap-1.5 cursor-pointer whitespace-nowrap"
                        title="Hapus seluruh karyawan yang sesuai hasil filter saat ini">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    <span>Hapus Hasil Filter ({{ $totalCount }} Orang)</span>
                </button>
            </div>
            @endif
        </form>
    </div>

    <!-- Table Card: Clean, Solid & Flush like Manajemen Akun -->
    <div class="bg-white border border-slate-300 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-separate border-spacing-0  text-xs">
                <thead>
                    <tr class="bg-slate-100 text-slate-700 font-bold text-[11px] uppercase tracking-wider sticky top-0 z-10 shadow-2xs">
                        <th class="py-3 px-2 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-12 min-w-[48px] max-w-[48px] whitespace-nowrap sticky left-0 z-30">NO</th>
                        <th class="py-3 px-2.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-28 min-w-[100px] max-w-[100px] whitespace-nowrap sticky left-[48px] z-30">NRP</th>
                        <th class="py-3 px-3.5 border-b border-r-2 border-r-slate-500 border-slate-300 bg-slate-100 font-bold text-slate-700 w-52 min-w-[200px] max-w-[200px] whitespace-nowrap sticky left-[148px] z-30 shadow-[4px_0_8px_-2px_rgba(0,0,0,0.15)]">NAMA LENGKAP</th>
                        <th class="py-3 px-2.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-28 whitespace-nowrap">CODE BAGIAN</th>
                        <th class="py-3 px-3 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 min-w-[120px]">NAMA BAGIAN</th>
                        <th class="py-3 px-2.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-28 whitespace-nowrap">SECTION</th>
                        <th class="py-3 px-2.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-24 whitespace-nowrap">DEPARTMENT</th>
                        <th class="py-3 px-2.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-36 whitespace-nowrap">DIVISION</th>
                        <th class="py-3 px-3 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 min-w-[130px]">POSITION</th>
                        <th class="py-3 px-2 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-24 whitespace-nowrap">START WORK</th>
                        <th class="py-3 px-2 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-12 whitespace-nowrap">KJ</th>
                        <th class="py-3 px-2 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-16 whitespace-nowrap">GOLONGAN</th>
                        <th class="py-3 px-2.5 border-b border-r border-slate-300 bg-slate-100 font-bold text-slate-700 text-center w-28 min-w-[100px] whitespace-nowrap">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($karyawanRows as $k)
                    <tr class="hover:bg-slate-50 transition-colors group">
                        
                        <!-- NO (Sticky Left, 100% Solid) -->
                        <td class="py-2.5 px-2 border-b border-r border-slate-300 text-center font-bold text-slate-800 bg-white group-hover:bg-slate-100 whitespace-nowrap w-12 min-w-[48px] max-w-[48px] sticky left-0 z-20">
                            {{ $loop->iteration }}
                        </td>

                        <!-- NRP (Sticky Left, 100% Solid) -->
                        <td class="py-2.5 px-2.5 border-b border-r border-slate-300 text-center font-mono font-normal text-slate-800 bg-white group-hover:bg-slate-100 whitespace-nowrap w-28 min-w-[100px] max-w-[100px] sticky left-[48px] z-20">
                            {{ $k['nrp'] }}
                        </td>

                        <!-- NAMA LENGKAP (Sticky Left, 100% Solid, Divider Tebal) -->
                        <td class="py-2.5 px-3.5 border-b border-r-2 border-r-slate-500 border-slate-300 font-normal text-slate-800 bg-white group-hover:bg-slate-100 whitespace-nowrap w-52 min-w-[200px] max-w-[200px] sticky left-[148px] z-20 shadow-[4px_0_8px_-2px_rgba(0,0,0,0.15)]">
                            <div>{{ $k['nama_lengkap'] }}</div>
                        </td>

                        <!-- CODE BAGIAN -->
                        <td class="py-2.5 px-2.5 border-b border-r border-slate-300 font-mono text-slate-700 text-center whitespace-nowrap">
                            {{ $k['code_bagian'] }}
                        </td>

                        <!-- NAMA BAGIAN -->
                        <td class="py-2.5 px-3 border-b border-r border-slate-300 text-slate-800 font-medium leading-snug">
                            {{ $k['nama_bagian'] }}
                        </td>

                        <!-- SECTION -->
                        <td class="py-2.5 px-2.5 border-b border-r border-slate-300 text-center">
                            <span class="px-2 py-0.5 text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200 inline-block whitespace-nowrap">
                                {{ $k['nama_sect'] }}
                            </span>
                        </td>

                        <!-- DEPARTMENT -->
                        <td class="py-2.5 px-2.5 border-b border-r border-slate-300 text-slate-700 font-medium text-center whitespace-nowrap">
                            {{ $k['departemen'] }}
                        </td>

                        <!-- DIVISION -->
                        <td class="py-2.5 px-2.5 border-b border-r border-slate-300 text-slate-700 font-medium text-center whitespace-nowrap">
                            {{ $k['divisi'] ?? '-' }}
                        </td>

                        <!-- POSITION -->
                        <td class="py-2.5 px-3 border-b border-r border-slate-300 text-slate-800 font-medium uppercase leading-snug">
                            {{ $k['jabatan'] }}
                        </td>

                        <!-- START WORK -->
                        <td class="py-2.5 px-2 border-b border-r border-slate-300 text-center font-mono text-slate-700 whitespace-nowrap">
                            {{ $k['start_work'] }}
                        </td>

                        <!-- KJ -->
                        <td class="py-2.5 px-2 border-b border-r border-slate-300 text-center font-bold text-slate-800 whitespace-nowrap">
                            {{ $k['kj'] }}
                        </td>

                        <!-- Sub Gol -->
                        <td class="py-2.5 px-2 border-b border-r border-slate-300 text-center font-semibold text-slate-700 whitespace-nowrap">
                            {{ $k['sub_gol'] }}
                        </td>

                        <!-- Aksi Buttons (Edit / Form) - Icon Buttons without rounded -->
                        <td class="py-2.5 px-2.5 border-b border-r border-slate-300 text-center whitespace-nowrap">
                            <div class="inline-flex items-center justify-center gap-1.5">
                                @if(!empty($k['doc_id']))
                                <a href="{{ route('penilaian.show.admin', $k['doc_id']) }}"
                                   class="p-1.5 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-300 transition shadow-2xs inline-flex items-center justify-center cursor-pointer"
                                   title="Buka Lembar Penilaian">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    <span class="sr-only">Form Penilaian</span>
                                </a>
                                @else
                                <span class="p-1.5 text-slate-300 border border-slate-200 inline-flex items-center justify-center" title="Belum Ada Form">
                                    <svg class="w-4 h-4 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>
                                    </svg>
                                </span>
                                @endif
                                <button type="button" 
                                        @click="
                                            editKaryawan.nrp = '{{ $k['nrp'] }}';
                                            editKaryawan.nama_lengkap = '{{ addslashes($k['nama_lengkap']) }}';
                                            editKaryawan.jabatan = '{{ addslashes($k['jabatan']) }}';
                                            editKaryawan.tgl_masuk = '{{ \App\Services\GoogleSyncService::toYmd($k['tgl_masuk'] ?? '') }}';
                                            editKaryawan.golongan = '{{ $k['golongan'] ?? 'GOLONGAN II' }}';
                                            let rawSub = '{{ $k['sub_gol'] ?: ($k['golongan'] ?? '') }}';
                                            editKaryawan.sub_gol = rawSub.replace(/^GOLONGAN\s*/i, '').trim().toUpperCase();
                                            let rawKj = '{{ $k['kj'] }}';
                                            let mKj = rawKj.match(/\d+/);
                                            editKaryawan.kj = mKj ? ('KJ ' + parseInt(mKj[0], 10)) : (rawKj || 'KJ 2');
                                            editKaryawan.division = '{{ addslashes($k['divisi'] ?? '') }}';
                                            editKaryawan.departemen = '{{ addslashes($k['departemen']) }}';
                                            editKaryawan.seksi = '{{ addslashes($k['seksi']) }}';
                                            editKaryawan.code_bagian = '{{ addslashes($k['code_bagian'] ?? '') }}';
                                            editKaryawan.nama_bagian = '{{ addslashes($k['nama_bagian'] ?? '') }}';
                                            editKaryawan.nama_sect = '{{ addslashes($k['nama_sect'] ?? '') }}';
                                            showEditModal = true;
                                        "
                                        class="p-1.5 border-b border-r border-slate-300 bg-white hover:bg-blue-50 hover:border-blue-400 text-slate-600 hover:text-blue-600 transition shadow-2xs inline-flex items-center justify-center cursor-pointer"
                                        title="Edit Data Karyawan">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                    <span class="sr-only">Edit Karyawan</span>
                                </button>

                                <form method="POST" action="{{ route('admin.karyawan.destroy', $k['nrp']) }}" 
                                      onsubmit="return confirm('Apakah Anda yakin ingin menghapus data karyawan {{ addslashes($k['nama_lengkap']) }} (NRP: {{ $k['nrp'] }})? Data yang dihapus tidak dapat dikembalikan.');" 
                                      class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" 
                                            class="p-1.5 border border-slate-300 bg-white hover:bg-red-50 hover:border-red-400 text-slate-600 hover:text-red-600 transition shadow-2xs inline-flex items-center justify-center cursor-pointer"
                                            title="Hapus Data Karyawan">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                        <span class="sr-only">Hapus Karyawan</span>
                                    </button>
                                </form>
                                
                            </div>
                        </td>

                    </tr>
                    @empty
                    <tr>
                        <td colspan="13" class="py-8 text-center text-slate-400 border-b border-r border-slate-300">
                            Tidak ada data karyawan yang cocok dengan kriteria pencarian.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Footer Pagination Info Flush Inside Card -->
        <div class="px-4 py-3 bg-white border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500">
            <div>
                Menampilkan <span class="font-bold text-slate-700">{{ $totalCount }}</span> dari <span class="font-bold text-slate-700">{{ $totalKaryawanPerusahaan }}</span> data karyawan terkelola (Sesuai Struktur Organisasi Riil)
            </div>
            <div class="inline-flex items-center gap-1">
                <button type="button" class="px-2.5 py-1 border border-slate-300 bg-white text-slate-400 font-bold text-xs cursor-not-allowed" disabled>&larr; Prev</button>
                <span class="px-2.5 py-1 bg-[#92302e] text-white font-bold text-xs border border-[#92302e]">1</span>
                <button type="button" class="px-2.5 py-1 border border-slate-300 bg-white text-slate-600 hover:bg-slate-50 font-bold text-xs cursor-pointer">Next &rarr;</button>
            </div>
        </div>

    </div>

    <!-- Modal Tambah Karyawan (Garis Jelas & Tegas Sesuai Struktur Kolom Master) -->
    <div x-show="addModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div @click.away="addModal = false" class="bg-white border border-slate-300 shadow-2xl w-full max-w-2xl overflow-hidden rounded-none animate-in fade-in zoom-in-95 duration-150">
            <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
                <div>
                    <h3 class="font-bold text-sm text-slate-900">Tambah Data Karyawan Baru</h3>
                    <p class="text-[11px] text-slate-500 mt-0.5">Masukkan data profil karyawan baru ke dalam sistem master karyawan</p>
                </div>
                <button @click="addModal = false" type="button" class="text-slate-400 hover:text-slate-700 text-xl font-bold cursor-pointer leading-none">&times;</button>
            </div>
            <form action="{{ route('karyawan.store') }}" method="POST" class="p-6 space-y-4">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    
                    <!-- NRP -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">NRP <span class="text-rose-500">*</span></label>
                        <input type="text" name="nrp" required placeholder="Contoh: 1001"
                               class="w-full text-xs font-mono font-bold border border-slate-400 bg-white py-2 px-3 text-slate-900 rounded-none shadow-2xs focus:border-[#92302e] focus:outline-none focus:ring-1 focus:ring-[#92302e]">
                    </div>

                    <!-- Nama Lengkap -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Nama Lengkap <span class="text-rose-500">*</span></label>
                        <input type="text" name="nama_lengkap" required placeholder="Nama lengkap karyawan"
                               class="w-full text-xs font-semibold border border-slate-400 bg-white py-2 px-3 text-slate-900 rounded-none shadow-2xs focus:border-[#92302e] focus:outline-none focus:ring-1 focus:ring-[#92302e]">
                    </div>

                    <!-- CODE BAGIAN -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">CODE BAGIAN</label>
                        <input type="text" name="code_bagian" placeholder="Contoh: 1141600000"
                               class="w-full text-xs font-mono border border-slate-400 bg-white py-2 px-3 text-slate-900 rounded-none shadow-2xs focus:border-[#92302e] focus:outline-none focus:ring-1 focus:ring-[#92302e]">
                    </div>

                    <!-- NAMA BAGIAN -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">NAMA BAGIAN</label>
                        <input type="text" name="nama_bagian" placeholder="Contoh: FACTORY 1 SEC"
                               class="w-full text-xs border border-slate-400 bg-white py-2 px-3 text-slate-900 rounded-none shadow-2xs focus:border-[#92302e] focus:outline-none focus:ring-1 focus:ring-[#92302e]">
                    </div>

                    <!-- SECTION -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">SECTION</label>
                        <input type="text" name="nama_sect" placeholder="Contoh: FACTORY 1"
                               class="w-full text-xs border border-slate-400 bg-white py-2 px-3 text-slate-900 rounded-none shadow-2xs focus:border-[#92302e] focus:outline-none focus:ring-1 focus:ring-[#92302e]">
                    </div>

                    <!-- DIVISION -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">DIVISION</label>
                        <input type="text" name="division" list="divisionOptions" placeholder="Contoh: PRODUCTION A DIV"
                               class="w-full text-xs border border-slate-400 bg-white py-2 px-3 text-slate-900 rounded-none shadow-2xs focus:border-[#92302e] focus:outline-none focus:ring-1 focus:ring-[#92302e]">
                    </div>

                    <!-- DEPARTMENT -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">DEPARTMENT <span class="text-rose-500">*</span></label>
                        <input type="text" name="departemen" list="deptOptions" value="PROD 2" required placeholder="Contoh: PROD 2"
                               class="w-full text-xs border border-slate-400 bg-white py-2 px-3 text-slate-900 rounded-none shadow-2xs focus:border-[#92302e] focus:outline-none focus:ring-1 focus:ring-[#92302e]">
                    </div>

                    <!-- POSITION -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">POSITION <span class="text-rose-500">*</span></label>
                        <input type="text" name="jabatan" required placeholder="Contoh: OPERATOR / LEADER / FOREMAN"
                               class="w-full text-xs border border-slate-400 bg-white py-2 px-3 text-slate-900 rounded-none shadow-2xs focus:border-[#92302e] focus:outline-none focus:ring-1 focus:ring-[#92302e]">
                    </div>

                    <!-- START WORK -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">START WORK (Tanggal Masuk) <span class="text-rose-500">*</span></label>
                        <input type="date" name="tgl_masuk" required value="{{ date('Y-m-d') }}"
                               class="w-full text-xs border border-slate-400 bg-white py-2 px-3 text-slate-900 rounded-none shadow-2xs focus:border-[#92302e] focus:outline-none focus:ring-1 focus:ring-[#92302e]">
                    </div>

                    <!-- KJ -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">KJ</label>
                        <select name="kj" class="w-full text-xs border border-slate-400 bg-white py-2 px-3 text-slate-900 rounded-none shadow-2xs focus:border-[#92302e] focus:outline-none focus:ring-1 focus:ring-[#92302e]">
                            <option value="KJ 1">KJ 1</option>
                            <option value="KJ 2" selected>KJ 2</option>
                            <option value="KJ 3">KJ 3</option>
                            <option value="KJ 4">KJ 4</option>
                            <option value="KJ 5">KJ 5</option>
                            <option value="KJ 6">KJ 6</option>
                            <option value="KJ 7">KJ 7</option>
                            <option value="KJ 8">KJ 8</option>
                            <option value="KJ 9">KJ 9</option>
                            <option value="KJ 10">KJ 10</option>
                            <option value="KJ 11">KJ 11</option>
                            <option value="KJ 12">KJ 12</option>
                        </select>
                    </div>

                    <!-- GOLONGAN -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">GOLONGAN <span class="text-rose-500">*</span></label>
                        <select name="sub_gol" required class="w-full text-xs border border-slate-400 bg-white py-2 px-3 text-slate-900 rounded-none shadow-2xs focus:border-[#92302e] focus:outline-none focus:ring-1 focus:ring-[#92302e]">
                            <optgroup label="Golongan I">
                                <option value="IA">IA</option>
                                <option value="IB">IB</option>
                                <option value="IC">IC</option>
                                <option value="ID">ID</option>
                            </optgroup>
                            <optgroup label="Golongan II">
                                <option value="IIA">IIA</option>
                                <option value="IIB" selected>IIB</option>
                                <option value="IIC">IIC</option>
                                <option value="IID">IID</option>
                                <option value="IIE">IIE</option>
                                <option value="IIF">IIF</option>
                            </optgroup>
                            <optgroup label="Golongan III">
                                <option value="IIIA">IIIA</option>
                                <option value="IIIB">IIIB</option>
                                <option value="IIIC">IIIC</option>
                                <option value="IIID">IIID</option>
                                <option value="IIIE">IIIE</option>
                                <option value="IIIF">IIIF</option>
                            </optgroup>
                            <optgroup label="Golongan IV">
                                <option value="IVA">IVA</option>
                                <option value="IVB">IVB</option>
                                <option value="IVC">IVC</option>
                                <option value="IVD">IVD</option>
                                <option value="IVE">IVE</option>
                                <option value="IVF">IVF</option>
                            </optgroup>
                            <optgroup label="Golongan V">
                                <option value="VA">VA</option>
                                <option value="VB">VB</option>
                                <option value="VC">VC</option>
                                <option value="VD">VD</option>
                                <option value="VE">VE</option>
                            </optgroup>
                            <optgroup label="Golongan VI">
                                <option value="VIA">VIA</option>
                                <option value="VIB">VIB</option>
                                <option value="VIC">VIC</option>
                                <option value="VID">VID</option>
                            </optgroup>
                        </select>
                    </div>

                </div>

                <div class="pt-4 border-t border-slate-200 flex items-center justify-end gap-2">
                    <button @click="addModal = false" type="button"
                            class="px-4 py-2 border border-slate-300 bg-slate-100 hover:bg-slate-200 text-xs font-bold text-slate-700 rounded-none cursor-pointer transition">
                        Batal
                    </button>
                    <button type="submit"
                            class="px-5 py-2 bg-[#92302e] hover:bg-[#7e2524] text-white text-xs font-bold rounded-none shadow-sm cursor-pointer transition">
                        Simpan Karyawan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit Data Karyawan (Garis Jelas & Berfungsi Penuh) -->
    <div x-show="showEditModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div @click.away="showEditModal = false" class="bg-white border border-slate-300 shadow-2xl w-full max-w-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-150">
            <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
                <div>
                    <h3 class="font-bold text-sm text-slate-900">Edit Data Master Karyawan</h3>
                    <p class="text-[11px] text-slate-500 mt-0.5">
                        Memperbarui data karyawan NRP: <span class="font-mono font-bold text-slate-900 bg-slate-200 px-1.5 py-0.5 border border-slate-300" x-text="editKaryawan.nrp"></span>
                    </p>
                </div>
                <button @click="showEditModal = false" type="button" class="text-slate-400 hover:text-slate-700 text-xl font-bold cursor-pointer leading-none">&times;</button>
            </div>
            <form :action="'/admin/karyawan/' + editKaryawan.nrp + '/update'" method="POST" class="p-6 space-y-4">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    
                    <!-- NRP (Readonly) -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">NRP</label>
                        <input type="text" name="nrp" x-model="editKaryawan.nrp" readonly
                               class="w-full text-xs font-mono font-bold border border-slate-400 bg-slate-100 py-2 px-3 text-slate-600 shadow-2xs cursor-not-allowed">
                    </div>

                    <!-- Nama Lengkap -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Nama Lengkap <span class="text-rose-500">*</span></label>
                        <input type="text" name="nama_lengkap" x-model="editKaryawan.nama_lengkap" required
                               class="w-full text-xs font-semibold border border-slate-400 bg-white py-2 px-3 text-slate-900 shadow-2xs focus:border-[#92302e] focus:outline-none focus:ring-1 focus:ring-[#92302e]">
                    </div>

                    <!-- CODE BAGIAN -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">CODE BAGIAN</label>
                        <input type="text" name="code_bagian" x-model="editKaryawan.code_bagian" placeholder="Contoh: 1141600000"
                               class="w-full text-xs font-mono border border-slate-400 bg-white py-2 px-3 text-slate-900 shadow-2xs focus:border-[#92302e] focus:outline-none focus:ring-1 focus:ring-[#92302e]">
                    </div>

                    <!-- NAMA BAGIAN -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">NAMA BAGIAN</label>
                        <input type="text" name="nama_bagian" x-model="editKaryawan.nama_bagian" placeholder="Contoh: FACTORY 1 SEC"
                               class="w-full text-xs border border-slate-400 bg-white py-2 px-3 text-slate-900 shadow-2xs focus:border-[#92302e] focus:outline-none focus:ring-1 focus:ring-[#92302e]">
                    </div>

                    <!-- SECTION -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">SECTION</label>
                        <input type="text" name="nama_sect" x-model="editKaryawan.nama_sect" placeholder="Contoh: FACTORY 1"
                               class="w-full text-xs border border-slate-400 bg-white py-2 px-3 text-slate-900 shadow-2xs focus:border-[#92302e] focus:outline-none focus:ring-1 focus:ring-[#92302e]">
                    </div>

                    <!-- DIVISION -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">DIVISION</label>
                        <input type="text" name="division" list="divisionOptions" x-model="editKaryawan.division" placeholder="Contoh: PRODUCTION A DIV"
                               class="w-full text-xs border border-slate-400 bg-white py-2 px-3 text-slate-900 rounded-none shadow-2xs focus:border-[#92302e] focus:outline-none focus:ring-1 focus:ring-[#92302e]">
                    </div>

                    <!-- DEPARTMENT -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">DEPARTMENT <span class="text-rose-500">*</span></label>
                        <input type="text" name="departemen" list="deptOptions" x-model="editKaryawan.departemen" required placeholder="Contoh: PROD 2"
                               class="w-full text-xs border border-slate-400 bg-white py-2 px-3 text-slate-900 rounded-none shadow-2xs focus:border-[#92302e] focus:outline-none focus:ring-1 focus:ring-[#92302e]">
                    </div>

                    <!-- POSITION -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">POSITION <span class="text-rose-500">*</span></label>
                        <input type="text" name="jabatan" x-model="editKaryawan.jabatan" required placeholder="FOREMAN / LEADER / OPERATOR"
                               class="w-full text-xs border border-slate-400 bg-white py-2 px-3 text-slate-900 shadow-2xs focus:border-[#92302e] focus:outline-none focus:ring-1 focus:ring-[#92302e]">
                    </div>

                    <!-- START WORK -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">START WORK (Tanggal Masuk)</label>
                        <input type="date" name="tgl_masuk" x-model="editKaryawan.tgl_masuk"
                               class="w-full text-xs border border-slate-400 bg-white py-2 px-3 text-slate-900 shadow-2xs focus:border-[#92302e] focus:outline-none focus:ring-1 focus:ring-[#92302e]">
                    </div>

                    <!-- KJ -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">KJ</label>
                        <select name="kj" x-model="editKaryawan.kj"
                                class="w-full text-xs border border-slate-400 bg-white py-2 px-3 text-slate-900 rounded-none shadow-2xs focus:border-[#92302e] focus:outline-none focus:ring-1 focus:ring-[#92302e]">
                            <option value="KJ 1">KJ 1</option>
                            <option value="KJ 2">KJ 2</option>
                            <option value="KJ 3">KJ 3</option>
                            <option value="KJ 4">KJ 4</option>
                            <option value="KJ 5">KJ 5</option>
                            <option value="KJ 6">KJ 6</option>
                            <option value="KJ 7">KJ 7</option>
                            <option value="KJ 8">KJ 8</option>
                            <option value="KJ 9">KJ 9</option>
                            <option value="KJ 10">KJ 10</option>
                            <option value="KJ 11">KJ 11</option>
                            <option value="KJ 12">KJ 12</option>
                        </select>
                    </div>

                    <!-- GOLONGAN -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">GOLONGAN <span class="text-rose-500">*</span></label>
                        <select name="sub_gol" x-model="editKaryawan.sub_gol" required
                                class="w-full text-xs border border-slate-400 bg-white py-2 px-3 text-slate-900 rounded-none shadow-2xs focus:border-[#92302e] focus:outline-none focus:ring-1 focus:ring-[#92302e]">
                            <optgroup label="Golongan I">
                                <option value="IA">IA</option>
                                <option value="IB">IB</option>
                                <option value="IC">IC</option>
                                <option value="ID">ID</option>
                            </optgroup>
                            <optgroup label="Golongan II">
                                <option value="IIA">IIA</option>
                                <option value="IIB">IIB</option>
                                <option value="IIC">IIC</option>
                                <option value="IID">IID</option>
                                <option value="IIE">IIE</option>
                                <option value="IIF">IIF</option>
                            </optgroup>
                            <optgroup label="Golongan III">
                                <option value="IIIA">IIIA</option>
                                <option value="IIIB">IIIB</option>
                                <option value="IIIC">IIIC</option>
                                <option value="IIID">IIID</option>
                                <option value="IIIE">IIIE</option>
                                <option value="IIIF">IIIF</option>
                            </optgroup>
                            <optgroup label="Golongan IV">
                                <option value="IVA">IVA</option>
                                <option value="IVB">IVB</option>
                                <option value="IVC">IVC</option>
                                <option value="IVD">IVD</option>
                                <option value="IVE">IVE</option>
                                <option value="IVF">IVF</option>
                            </optgroup>
                            <optgroup label="Golongan V">
                                <option value="VA">VA</option>
                                <option value="VB">VB</option>
                                <option value="VC">VC</option>
                                <option value="VD">VD</option>
                                <option value="VE">VE</option>
                            </optgroup>
                            <optgroup label="Golongan VI">
                                <option value="VIA">VIA</option>
                                <option value="VIB">VIB</option>
                                <option value="VIC">VIC</option>
                                <option value="VID">VID</option>
                            </optgroup>
                        </select>
                    </div>

                </div>

                <div class="pt-4 border-t border-slate-200 flex items-center justify-end gap-2">
                    <button @click="showEditModal = false" type="button"
                            class="px-4 py-2 border border-slate-300 bg-slate-100 hover:bg-slate-200 text-xs font-bold text-slate-700 cursor-pointer transition">
                        Batal
                    </button>
                    <button type="submit"
                            class="px-5 py-2 bg-blue-700 hover:bg-blue-800 text-white text-xs font-bold shadow-sm cursor-pointer transition">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Upload Sheet (Garis Jelas & Tegas) -->
    <div x-show="uploadModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div @click.away="uploadModal = false" class="bg-white border border-slate-300 shadow-2xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in-95 duration-150">
            <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
                <h3 class="font-bold text-sm text-slate-900">Upload Data Karyawan (CSV / Excel)</h3>
                <button @click="uploadModal = false" type="button" class="text-slate-400 hover:text-slate-700 text-xl font-bold cursor-pointer leading-none">&times;</button>
            </div>
            <form action="{{ route('karyawan.import') }}" method="POST" enctype="multipart/form-data" class="p-6 space-y-4" x-data="{ isUploading: false }" @submit="isUploading = true">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">Pilih File Excel / CSV (.XLSX / .CSV)</label>
                    <input type="file" name="sheet_file" accept=".xlsx,.csv,.txt" required
                           class="w-full text-xs text-slate-600 border border-slate-400 bg-white py-2 px-3 file:mr-3 file:py-1.5 file:px-3 file:border-0 file:text-xs file:font-bold file:bg-slate-900 file:text-white hover:file:bg-black cursor-pointer shadow-2xs">
                </div>
                <label class="flex items-start gap-2.5 p-3 bg-amber-50/70 border border-amber-300 cursor-pointer">
                    <input type="checkbox" name="sync_existing" value="1" checked
                           class="mt-0.5 h-4 w-4 border-slate-400 text-[#92302e] focus:ring-[#92302e] rounded-none">
                    <span class="text-[11px] text-slate-700 leading-relaxed">
                        <strong class="block text-slate-900">Perbarui data karyawan jika NRP sudah ada (Sinkronkan perubahan Excel)</strong>
                        Otomatis memperbarui Division, Dept, Section, KJ, Golongan, serta menyambungkan Divisi/Departemen baru ke halaman <strong>Target &amp; Kuota</strong>.
                    </span>
                </label>
                <div class="p-3 bg-slate-50 border border-slate-300 text-[11px] text-slate-600 space-y-1">
                    <p class="font-bold text-slate-800">Format Kolom Excel / CSV yang Didukung:</p>
                    <p class="font-mono text-[10px] text-slate-700 font-semibold bg-white p-1.5 border border-slate-200 overflow-x-auto whitespace-nowrap">NRP &bull; NAMA LENGKAP &bull; CODE BAGIAN &bull; NAMA BAGIAN &bull; SECTION &bull; DEPARTMENT &bull; DIVISION &bull; POSITION &bull; START WORK &bull; KJ &bull; GOLONGAN &bull; AKSI</p>
                    <p class="text-[10px] text-slate-500 font-mono">Format standar sistem juga didukung: EMPLOYEENO, FULLNAME, ORGSTRUCTURECODE, ORGSTRUCTURENAME, LINE CATEGORY, POSITION, DIVISION, DEPT, SECTION, EMPLOYMENTTYPE, JOBCLASSCODE, JOBLEVELCODE, JOINDATE.</p>
                    <p class="text-[10px] text-emerald-700 font-bold mt-1">&check; Otomatis membuat/memperbarui akun login di Manajemen Akun untuk posisi FOREMAN, SECTION HEAD, DEPT HEAD, DIV HEAD (Email: NRPPOSITION@musashi.id, Password: NRP+POSITION).</p>
                </div>
                <div class="pt-3 border-t border-slate-200 flex items-center justify-between">
                    <a href="{{ route('karyawan.template') }}" class="text-xs font-bold text-emerald-700 hover:underline">
                        Unduh Template CSV
                    </a>
                    <div class="flex items-center gap-2">
                        <button @click="uploadModal = false" :disabled="isUploading" type="button" class="px-3.5 py-2 border border-slate-300 bg-slate-100 hover:bg-slate-200 text-xs font-bold text-slate-700 cursor-pointer transition">
                            Batal
                        </button>
                        <button type="submit" :disabled="isUploading" class="inline-flex items-center gap-2 px-4 py-2 bg-[#92302e] hover:bg-[#7e2524] disabled:opacity-60 text-white text-xs font-bold shadow-sm cursor-pointer transition">
                            <svg x-show="isUploading" class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span x-show="!isUploading">Import Sekarang</span>
                            <span x-show="isUploading">Mengimpor &amp; Menyinkronkan...</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Hapus Data Karyawan (Hapus Semua / Hapus Berdasarkan Filter) -->
    <div x-show="deleteBulkModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div @click.away="deleteBulkModal = false" class="bg-white border-2 border-red-600 shadow-2xl w-full max-w-lg overflow-hidden animate-in fade-in zoom-in-95 duration-150">
            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-red-50">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center text-red-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-black text-sm text-red-950 uppercase tracking-tight">Hapus Data Karyawan Massal</h3>
                        <p class="text-[11px] text-red-700 font-medium mt-0.5">Penghapusan data karyawan dan seluruh riwayat dokumen terkait</p>
                    </div>
                </div>
                <button @click="deleteBulkModal = false" type="button" class="text-slate-400 hover:text-slate-700 text-xl font-bold cursor-pointer leading-none">&times;</button>
            </div>

            <!-- Modal Form -->
            <form action="{{ route('admin.karyawan.bulkDelete') }}" method="POST" class="p-6 space-y-4">
                @csrf

                <!-- Hidden inputs for filter criteria -->
                <input type="hidden" name="divisi" value="{{ $filterDivisi }}">
                <input type="hidden" name="departemen" value="{{ $filterDept }}">
                <input type="hidden" name="seksi" value="{{ $filterSeksi }}">
                <input type="hidden" name="golongan" value="{{ $filterGol }}">
                <input type="hidden" name="atasan" value="{{ $filterAtasan }}">
                <input type="hidden" name="q" value="{{ $search }}">

                @php
                    $hasActiveFilter = ($search || $filterDivisi || $filterDept || !empty($filterSeksi) || $filterGol || $filterAtasan);
                @endphp

                <div class="space-y-2.5">
                    <label class="block text-xs font-bold text-slate-800 uppercase tracking-wider">Pilih Cakupan Penghapusan:</label>

                    @if($hasActiveFilter)
                    <!-- Option 1: Hapus Data Terfilter -->
                    <label class="flex items-start gap-3 p-3 border-2 cursor-pointer transition"
                           :class="deleteScope === 'filtered' ? 'border-red-600 bg-red-50/60' : 'border-slate-300 bg-white hover:bg-slate-50'">
                        <input type="radio" name="scope" value="filtered" x-model="deleteScope" class="mt-1 text-red-600 focus:ring-red-500">
                        <div class="flex-1 text-xs">
                            <div class="flex items-center justify-between">
                                <strong class="text-slate-900 font-bold">Hapus Karyawan Terfilter Saja (Rekomendasi)</strong>
                                <span class="px-2 py-0.5 bg-red-600 text-white font-mono font-black text-[10px]">{{ $totalCount }} Orang</span>
                            </div>
                            <p class="text-[11px] text-slate-600 mt-1 leading-relaxed">
                                Hanya menghapus <strong>{{ $totalCount }} orang karyawan</strong> yang saat ini muncul sesuai filter aktif:
                            </p>
                            <div class="mt-1.5 p-2 bg-white/80 border border-slate-300 rounded-none text-[11px] font-mono text-slate-700 space-y-0.5">
                                @if($filterDivisi) <div>• Division: <strong>{{ $filterDivisi }}</strong></div> @endif
                                @if($filterDept) <div>• Department: <strong>{{ $filterDept }}</strong></div> @endif
                                @if($filterSeksi) <div>• Section: <strong>{{ $filterSeksi }}</strong></div> @endif
                                @if($filterGol) <div>• Golongan: <strong>{{ $filterGol }}</strong></div> @endif
                                @if($filterAtasan) <div>• Atasan: <strong>{{ $filterAtasan }}</strong></div> @endif
                                @if($search) <div>• Pencarian: <strong>"{{ $search }}"</strong></div> @endif
                            </div>
                        </div>
                    </label>

                    <!-- Option 2: Hapus Seluruh Data Karyawan -->
                    <label class="flex items-start gap-3 p-3 border-2 cursor-pointer transition"
                           :class="deleteScope === 'all' ? 'border-red-600 bg-red-50/60' : 'border-slate-300 bg-white hover:bg-slate-50'">
                        <input type="radio" name="scope" value="all" x-model="deleteScope" class="mt-1 text-red-600 focus:ring-red-500">
                        <div class="flex-1 text-xs">
                            <div class="flex items-center justify-between">
                                <strong class="text-slate-900 font-bold">Hapus Seluruh Data Karyawan (Semua Divisi)</strong>
                                <span class="px-2 py-0.5 bg-slate-800 text-white font-mono font-black text-[10px]">{{ $totalKaryawanPerusahaan }} Orang</span>
                            </div>
                            <p class="text-[11px] text-slate-500 mt-1">
                                Menghapus <strong>seluruh {{ $totalKaryawanPerusahaan }} karyawan</strong> di database dan mengosongkan Master Karyawan di Google Spreadsheet.
                            </p>
                        </div>
                    </label>
                    @else
                    <!-- No Filter Active: Option Hapus Semua -->
                    <div class="p-3.5 bg-red-50 border border-red-200 text-xs text-red-900 leading-relaxed">
                        <div class="font-bold flex items-center gap-1.5 text-red-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            <span>Perhatian: Tidak ada filter yang aktif saat ini.</span>
                        </div>
                        <p class="mt-1 text-[11px]">
                            Aksi ini akan menghapus <strong>seluruh {{ $totalKaryawanPerusahaan }} data karyawan</strong> yang ada di sistem dan mengosongkan sheet <em>Master Karyawan</em> di Google Spreadsheet.
                        </p>
                        <input type="hidden" name="scope" value="all">
                    </div>
                    @endif
                </div>

                <!-- Warning Callout -->
                <div class="p-3 bg-amber-50 border border-amber-300 text-[11px] text-amber-900 leading-relaxed space-y-1">
                    <div class="font-bold text-amber-950 flex items-center gap-1">
                        <span>⚠️</span> Konsekuensi Penghapusan:
                    </div>
                    <div>• Seluruh formulir penilaian, riwayat nilai aspek, indikator potensi, dan tanda tangan digital karyawan terkait akan ikut terhapus bersih.</div>
                    <div>• Perubahan akan langsung disinkronkan secara otomatis ke Google Spreadsheet di latar belakang.</div>
                </div>

                <!-- Confirmation Box -->
                <div class="pt-2">
                    <label class="block text-xs font-bold text-slate-800 mb-1">
                        Ketik <span class="font-mono bg-red-100 text-red-800 px-1 py-0.5 border border-red-300 font-black">HAPUS</span> untuk mengonfirmasi:
                    </label>
                    <input type="text" x-model="confirmDeleteText" placeholder="Ketik HAPUS di sini..."
                           class="w-full text-xs font-mono font-bold uppercase tracking-wider border-2 border-slate-400 bg-white py-2 px-3 focus:outline-none focus:border-red-600 focus:ring-1 focus:ring-red-600">
                </div>

                <!-- Action Buttons -->
                <div class="pt-4 border-t border-slate-200 flex items-center justify-end gap-2.5">
                    <button @click="deleteBulkModal = false" type="button"
                            class="px-4 py-2 border border-slate-300 bg-slate-100 hover:bg-slate-200 text-xs font-bold text-slate-700 cursor-pointer transition">
                        Batal
                    </button>
                    <button type="submit"
                            :disabled="confirmDeleteText.trim().toUpperCase() !== 'HAPUS'"
                            :class="confirmDeleteText.trim().toUpperCase() === 'HAPUS' ? 'bg-red-600 hover:bg-red-700 cursor-pointer shadow-sm' : 'bg-slate-300 text-slate-500 cursor-not-allowed'"
                            class="px-5 py-2 text-white text-xs font-black transition uppercase tracking-wide flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        <span>Ya, Hapus Sekarang</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

{{-- Datalist options for modals placed outside space-y-6 container to prevent top margin injection --}}
<datalist id="divisionOptions">
    @foreach(($divisiList ?? []) as $divOpt)
        <option value="{{ $divOpt }}"></option>
    @endforeach
</datalist>
<datalist id="deptOptions">
    @foreach(($deptList ?? []) as $deptOpt)
        <option value="{{ $deptOpt }}"></option>
    @endforeach
</datalist>
@endsection
