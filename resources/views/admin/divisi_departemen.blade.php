@extends('layouts.app', ['title' => 'Divisi & Departemen - Admin'])

@section('content')
<div class="space-y-5" x-data="{ addDivisiModal: false }">

    <!-- Header Section Card (Musashi Red Banner, Solid & Clean) -->
    <div class="bg-gradient-to-r from-[#962226] via-[#92302e] to-[#781b1f] text-white rounded-none p-5 sm:p-6 border border-[#781b1f] shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight">
                Divisi &amp; Departemen
            </h1>
            <p class="text-xs text-red-100 font-medium mt-0.5">
                Struktur organisasi divisi, alokasi unit kerja departemen, dan seksi terdaftar sesuai Master Karyawan (DATA PK)
            </p>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap shrink-0">
            <button @click="addDivisiModal = true" type="button"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-none bg-white text-[#92302e] hover:bg-slate-100 text-xs font-bold shadow-xs transition cursor-pointer">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                <span>Tambah Divisi</span>
            </button>
            <a href="{{ route('admin.targetKuota') }}" 
               class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-none bg-white text-slate-700 hover:bg-slate-50 text-xs font-bold shadow-xs transition">
                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                <span>Target &amp; Kuota Nilai</span>
            </a>
            <a href="{{ route('admin.karyawan') }}" 
               class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-none bg-white text-slate-700 hover:bg-slate-50 text-xs font-bold shadow-xs transition">
                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
                <span>Data Karyawan ({{ $totalKaryawanPerusahaan }})</span>
            </a>
        </div>
    </div>

    <!-- Quick Metric Summary Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <!-- Card 1: Total Karyawan -->
        <div class="bg-white rounded-none p-3.5 border border-slate-300 shadow-xs">
            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Total Karyawan Riil</span>
            <div class="mt-1 flex items-baseline gap-1.5">
                <span class="text-2xl font-black text-slate-900">{{ $totalKaryawanPerusahaan }}</span>
                <span class="text-xs font-medium text-slate-500">karyawan</span>
            </div>
            <span class="text-[10px] text-slate-400 mt-0.5 block truncate">Sesuai Master DATA PK</span>
        </div>

        <!-- Card 2: Divisi Induk -->
        <div class="bg-white rounded-none p-3.5 border border-slate-300 shadow-xs">
            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Divisi Induk Terdaftar</span>
            <div class="mt-1 flex items-baseline gap-1.5">
                <span class="text-2xl font-black text-slate-900">{{ count($divisiOptions) }}</span>
                <span class="text-xs font-medium text-slate-500">divisi</span>
            </div>
            <span class="text-[10px] text-slate-400 mt-0.5 block truncate">{{ !empty($divisiOptions) ? implode(', ', $divisiOptions) : '-' }}</span>
        </div>

        <!-- Card 3: Departemen Terdaftar -->
        <div class="bg-white rounded-none p-3.5 border border-slate-300 shadow-xs">
            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Departemen Terdaftar</span>
            <div class="mt-1 flex items-baseline gap-1.5">
                <span class="text-2xl font-black text-slate-900">{{ count($departemenList) }}</span>
                <span class="text-xs font-medium text-slate-500">departemen</span>
            </div>
            <span class="text-[10px] text-slate-400 mt-0.5 block truncate">
                {{ !empty($departemenList) ? implode(' • ', array_column($departemenList, 'kode')) : '-' }}
            </span>
        </div>

        <!-- Card 4: Seksi / Section -->
        <div class="bg-white rounded-none p-3.5 border border-slate-300 shadow-xs">
            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Seksi / Section Terdata</span>
            <div class="mt-1 flex items-baseline gap-1.5">
                <span class="text-2xl font-black text-slate-900">{{ count($uniqueSections) }}</span>
                <span class="text-xs font-medium text-slate-500">seksi</span>
            </div>
            <span class="text-[10px] text-slate-400 mt-0.5 block truncate">
                {{ !empty($uniqueSections) ? implode(', ', array_keys($uniqueSections)) : '-' }}
            </span>
        </div>
    </div>

    <!-- Visual Division Structure Cards (DATA PK Structure) -->
    <div class="bg-white rounded-none border border-slate-300 p-4 shadow-xs">
        <div class="flex items-center justify-between pb-3 border-b border-slate-200">
            <div>
                <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">
                    Struktur Hierarki Divisi &amp; Departemen (DATA PK)
                </h3>
                <p class="text-[11px] text-slate-400 font-medium">
                    Hubungan hierarkis antara Divisi Induk, Division Head, dan Departemen Operasional
                </p>
            </div>
            <span class="text-[11px] font-bold text-emerald-800 bg-emerald-50 px-2 py-0.5 border border-emerald-200">
                Data Sinkron &amp; Terhubung
            </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-3">
            @forelse($divisionStats as $divName => $divInfo)
            <div class="border border-slate-300 p-3 bg-slate-50/50">
                <div class="flex items-center justify-between">
                    <span class="font-black text-xs text-slate-900 uppercase tracking-wide flex items-center gap-1.5">
                        <span class="w-2 h-2 bg-[#92302e]"></span>
                        {{ $divName }}
                    </span>
                    <span class="px-2 py-0.5 text-[10px] font-black bg-white border border-slate-300 text-slate-800">
                        {{ $divInfo['total_karyawan'] }} Karyawan
                    </span>
                </div>
                <div class="mt-2 text-[11px] text-slate-600 space-y-1">
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-slate-500 w-24">Division Head:</span>
                        <span class="font-semibold text-slate-800">
                            {{ !empty($divInfo['div_heads']) ? implode(', ', $divInfo['div_heads']) : '-' }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-slate-500 w-24">Departemen:</span>
                        <div class="flex flex-wrap gap-1">
                            @foreach($divInfo['departments'] as $dKode)
                            <a href="{{ route('admin.karyawan', ['departemen' => $dKode]) }}" 
                               class="px-1.5 py-0.2 bg-white border border-slate-300 text-slate-800 font-bold hover:bg-slate-100 transition text-[10px]"
                               title="Filter Karyawan {{ $dKode }}">
                                {{ $dKode }}
                            </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-span-1 md:col-span-2 py-8 text-center text-slate-400 bg-slate-50 border border-slate-200">
                <p class="text-xs font-semibold text-slate-600">Belum ada divisi atau karyawan terdaftar</p>
                <p class="text-[11px] text-slate-400 mt-0.5">Struktur divisi akan terisi otomatis saat data Master Karyawan diunggah.</p>
            </div>
            @endforelse
        </div>
    </div>

    <!-- 2-Column Layout (Tabel Divisi & Departemen Col 8 + Form Tambah Col 4) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        
        <!-- Left Column: Tabel Divisi & Departemen (Col 8) -->
        <div class="lg:col-span-8 bg-white rounded-none border border-slate-300 shadow-xs overflow-hidden flex flex-col justify-between">
            <div>
                <div class="p-4 border-b border-slate-200 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800">
                            Struktur Divisi &amp; Departemen
                        </h3>
                        <p class="text-[11px] text-slate-400 font-medium">
                            Daftar departemen riil dan alokasi karyawan terdata (Total: {{ $totalKaryawanPerusahaan }} Karyawan)
                        </p>
                    </div>
                    <span class="text-xs font-bold text-emerald-800 bg-emerald-50 px-2.5 py-1 border border-emerald-200">
                        {{ count($departemenList) }} Departemen Terdaftar
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse border border-slate-300">
                        <thead>
                            <tr class="bg-slate-100 text-slate-700 font-bold text-[11px] uppercase tracking-wider">
                                <th class="py-3 px-3.5 border border-slate-300 bg-slate-100 font-bold text-slate-700 whitespace-nowrap">Department</th>
                                <th class="py-3 px-3.5 border border-slate-300 bg-slate-100 font-bold text-slate-700 whitespace-nowrap">Division</th>
                                <th class="py-3 px-3.5 border border-slate-300 bg-slate-100 font-bold text-slate-700 whitespace-nowrap">Dept Head (Penilai Final)</th>
                                <th class="py-3 px-3.5 border border-slate-300 bg-slate-100 font-bold text-slate-700 whitespace-nowrap">Section</th>
                                <th class="py-3 px-3.5 border border-slate-300 bg-slate-100 font-bold text-slate-700 whitespace-nowrap">Foreman</th>
                                <th class="py-3 px-3.5 border border-slate-300 bg-slate-100 font-bold text-slate-700 whitespace-nowrap text-center">Total Karyawan</th>
                                <th class="py-3 px-3.5 border border-slate-300 bg-slate-100 font-bold text-slate-700 whitespace-nowrap text-center w-28">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            @forelse($departemenList as $d)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <!-- Departemen -->
                                <td class="py-2.5 px-3.5 border border-slate-300 font-bold text-slate-800 whitespace-nowrap">
                                    <div class="font-bold text-slate-900 text-xs">{{ $d['nama'] }}</div>
                                    <span class="block text-[10px] text-slate-400 font-mono font-normal mt-0.5">{{ $d['kode'] }}</span>
                                </td>

                                <!-- Divisi -->
                                <td class="py-2.5 px-3.5 border border-slate-300 text-slate-700 whitespace-nowrap">
                                    <span class="px-2 py-0.5 text-[10px] font-bold bg-slate-100 text-slate-800 border border-slate-300">
                                        {{ $d['divisi'] }}
                                    </span>
                                </td>

                                <!-- Dept Head (Penilai Final) -->
                                <td class="py-2.5 px-3.5 border border-slate-300 font-semibold text-slate-700 whitespace-nowrap">
                                    {{ $d['dept_head'] }}
                                </td>

                                <!-- Section -->
                                <td class="py-2.5 px-3.5 border border-slate-300 text-slate-600 text-[11px]">
                                    @php
                                        $validSections = array_values(array_filter($d['sections'] ?? [], fn($s) => $s !== '-' && $s !== ''));
                                    @endphp
                                    @if(!empty($validSections))
                                    <div class="flex flex-wrap gap-1 max-w-[200px]">
                                        @foreach($validSections as $sec)
                                        <span class="px-1.5 py-0.5 bg-slate-100 text-slate-700 text-[10px] font-semibold border border-slate-300 whitespace-nowrap">
                                            {{ $sec }}
                                        </span>
                                        @endforeach
                                    </div>
                                    @else
                                    <span class="text-slate-400 font-medium">-</span>
                                    @endif
                                </td>

                                <!-- Foreman -->
                                <td class="py-2.5 px-3.5 border border-slate-300 text-slate-600 text-[11px]">
                                    @if(!empty($d['foremen']))
                                    <div class="flex flex-wrap gap-1 max-w-[260px]">
                                        @foreach($d['foremen'] as $frm)
                                        <span class="px-1.5 py-0.5 bg-slate-100 text-slate-700 text-[10px] font-semibold border border-slate-300 whitespace-nowrap">
                                            {{ $frm }}
                                        </span>
                                        @endforeach
                                    </div>
                                    @else
                                    <span class="text-slate-400 font-medium">-</span>
                                    @endif
                                </td>

                                <!-- Total Karyawan (Sesuai Data Riil) -->
                                <td class="py-2.5 px-3.5 border border-slate-300 text-center whitespace-nowrap">
                                    @if($d['total_karyawan'] > 0)
                                    <span class="px-2.5 py-1 bg-emerald-50 text-emerald-800 font-black text-xs border border-emerald-300 inline-block">
                                        {{ $d['total_karyawan'] }} org
                                    </span>
                                    @else
                                    <span class="px-2 py-0.5 bg-slate-100 text-slate-400 font-bold text-xs inline-block border border-slate-200">
                                        0 org
                                    </span>
                                    @endif
                                </td>

                                <!-- Aksi - Icon Buttons without rounded -->
                                <td class="py-2.5 px-3 border border-slate-300 text-center whitespace-nowrap">
                                    <div class="inline-flex items-center justify-center gap-1.5">
                                        @if($d['total_karyawan'] > 0)
                                        <a href="{{ route('admin.karyawan', ['departemen' => $d['filter_dept']]) }}" 
                                           class="p-1.5 bg-[#92302e] hover:bg-[#7e2524] text-white transition shadow-2xs inline-flex items-center justify-center cursor-pointer"
                                           title="Lihat Karyawan {{ $d['nama'] }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            <span class="sr-only">Lihat</span>
                                        </a>
                                        @endif
                                        <button type="button" class="p-1.5 border border-slate-300 bg-white hover:bg-slate-100 text-slate-600 hover:text-blue-600 transition shadow-2xs inline-flex items-center justify-center cursor-pointer" title="Edit Departemen">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                            <span class="sr-only">Edit</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="py-10 text-center text-slate-500 border border-slate-300 bg-slate-50">
                                    <div class="flex flex-col items-center justify-center gap-1.5">
                                        <p class="text-xs font-bold text-slate-700">Belum ada departemen terdaftar</p>
                                        <p class="text-[11px] text-slate-500">Struktur departemen terisi otomatis dari data Master Karyawan.</p>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="bg-slate-100 font-black text-slate-900 text-xs">
                                <td colspan="5" class="py-3 px-3.5 border border-slate-300 bg-slate-100 uppercase tracking-wider text-[11px]">
                                    Total Seluruh Departemen
                                </td>
                                <td class="py-3 px-3.5 border border-slate-300 bg-slate-100 text-center text-slate-900 font-black whitespace-nowrap">
                                    {{ $totalKaryawanPerusahaan }} Karyawan
                                </td>
                                <td class="py-3 px-3.5 border border-slate-300 bg-slate-100 text-center text-slate-400">
                                    -
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="p-3 border-t border-slate-200 bg-slate-50/50 flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-slate-500">
                <span>Perubahan struktur divisi &amp; departemen akan mempengaruhi jalur persetujuan penilaian karya.</span>
                <span class="font-bold text-emerald-800">100% Sesuai Data Master</span>
            </div>
        </div>

        <!-- Right Column: Card Tambah / Edit Departemen (Col 4) -->
        <div class="lg:col-span-4 bg-white rounded-none border border-slate-300 shadow-xs p-5 space-y-4">
            <div>
                <h3 class="text-sm font-bold text-slate-800 tracking-tight">
                    Tambah Departemen
                </h3>
                <p class="text-[11px] text-slate-400 font-medium mt-0.5">
                    Tambahkan unit kerja departemen baru ke dalam divisi perusahaan
                </p>
            </div>

            <form action="{{ route('admin.simpanDepartemen') }}" method="POST" class="space-y-3.5">
                @csrf
                
                <!-- Kode Departemen -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">
                        Kode Departemen
                    </label>
                    <input type="text" name="kode_departemen" placeholder="Contoh: PROD 5" required
                           class="w-full text-xs rounded-none border border-slate-300 py-2 px-3 focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] font-mono">
                </div>

                <!-- Nama Departemen -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">
                        Nama Departemen
                    </label>
                    <input type="text" name="nama_departemen" placeholder="Contoh: Production 5" required
                           class="w-full text-xs rounded-none border border-slate-300 py-2 px-3 focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] font-medium">
                </div>

                <!-- Pilih Divisi -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">
                        Divisi Induk
                    </label>
                    <select name="divisi" required
                            class="w-full text-xs rounded-none border border-slate-300 py-2 px-3 focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e]">
                        @foreach($divisiOptions as $opt)
                        <option value="{{ $opt }}">{{ $opt }}</option>
                        @endforeach
                    </select>
                    <p class="text-[10px] text-slate-400 mt-0.5">Sesuai nama divisi yang ada di Master Karyawan</p>
                </div>

                <!-- Pilih Department Head -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">
                        Department Head (Penilai Final)
                    </label>
                    <select name="dept_head" required
                            class="w-full text-xs rounded-none border border-slate-300 py-2 px-3 focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e]">
                        <option value="">Pilih Dept Head...</option>
                        @foreach($deptHeads as $dh)
                        <option value="{{ $dh->nama }}">{{ $dh->nama }} ({{ $dh->nrp ?? 'DH' }})</option>
                        @endforeach
                    </select>
                </div>

                <!-- Submit Button -->
                <div class="pt-2">
                    <button type="submit"
                            class="w-full px-4 py-2.5 rounded-none font-bold text-xs bg-[#92302e] hover:bg-[#7e2524] text-white shadow-xs transition-all flex items-center justify-center gap-1.5 cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span>Simpan Departemen</span>
                    </button>
                </div>

            </form>
        </div>

    </div>

    <!-- Modal Tambah Divisi Baru -->
    <div x-show="addDivisiModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div @click.away="addDivisiModal = false" class="bg-white rounded-none border border-slate-300 shadow-2xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in-95 duration-150">
            <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
                <h3 class="font-bold text-sm text-slate-900">Tambah Divisi Perusahaan Baru</h3>
                <button @click="addDivisiModal = false" type="button" class="text-slate-400 hover:text-slate-600 text-lg font-bold cursor-pointer">&times;</button>
            </div>
            <form action="{{ route('admin.simpanDepartemen') }}" method="POST" class="p-6 space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Nama Divisi Baru</label>
                    <input type="text" name="divisi" placeholder="Contoh: PRODUCTION C DIV" required
                           class="w-full text-xs rounded-none border border-slate-300 py-2 px-3 focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e]">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Departemen Pertama</label>
                    <input type="text" name="nama_departemen" placeholder="Contoh: Production 5" required
                           class="w-full text-xs rounded-none border border-slate-300 py-2 px-3 focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e]">
                </div>
                <div class="pt-3 border-t border-slate-200 flex items-center justify-end gap-2">
                    <button @click="addDivisiModal = false" type="button" class="px-4 py-2 rounded-none border border-slate-300 text-xs font-bold text-slate-600 hover:bg-slate-50 cursor-pointer">
                        Batal
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-none bg-[#92302e] hover:bg-[#7e2524] text-white text-xs font-bold shadow-xs cursor-pointer">
                        Simpan Divisi
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
