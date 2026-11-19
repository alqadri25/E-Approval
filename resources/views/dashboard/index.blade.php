@extends('layouts.app', ['title' => 'Dashboard Utama'])

@section('content')

    @if($user->isSuperadmin() || $user->isAdmin())
        {{-- Tampilan Super Admin & Admin: Pantauan Seluruh Divisi & Departemen (Sama Persis) --}}
        @include('dashboard.admin')

    @elseif($user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head']))
        {{-- Tampilan Khusus Division Head: Pengaturan Target Kuota & Diagram Perbandingan (S, A, B, C, K) --}}
        @include('dashboard.div_head')

    @elseif($user->isDepartmentHead())
        {{-- Tampilan Khusus Dept Head: Dokumen Masuk dari Section Head, Persetujuan Final & TTD --}}
        @include('dashboard.dept_head')

    @elseif($user->isSectionHead())
        {{-- Tampilan Khusus Section Head: Dokumen Masuk dari Foreman, Tinjauan & TTD --}}
        @include('dashboard.section_head')

    @elseif($user->isForeman())
        {{-- Tampilan Khusus Foreman: Total Bawahan, Target, Berapa Dinilai, Diagram Lingkaran S/A/B/C/K, Tabel Bawahan --}}
        @include('dashboard.foreman')

    @else
        {{-- Tampilan Administrator / Superadmin / HR --}}
        <div class="space-y-6">
            <!-- Header & Welcome Banner -->
            <div class="bg-gradient-to-r from-slate-900 via-emerald-950 to-slate-900 rounded-none p-6 text-white shadow-lg">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-none bg-white/10 backdrop-blur-sm text-xs font-semibold text-emerald-300 mb-2 border border-white/10">
                            <span>Periode: {{ $activePeriod['tahun'] ?? 2025 }}</span>
                            <span>&bull;</span>
                            <span class="capitalize">{{ $activePeriod['status'] ?? 'Aktif' }}</span>
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">
                            Dashboard Pengawasan Penilaian Karyawan
                        </h1>
                        <p class="mt-1 text-slate-300 text-sm max-w-2xl">
                            Role: <strong class="text-white">{{ $user->roleLabel() }}</strong> &bull; Hak Akses: <strong class="text-emerald-300">{{ $user->departemen === 'ALL' ? 'Seluruh Departemen (Multi-Division)' : $user->departemen }}</strong>
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        @can('superadmin')
                        <a href="{{ route('superadmin.config') }}" class="min-btn inline-flex items-center justify-center px-4 py-2.5 rounded-none bg-white text-slate-900 font-bold text-xs shadow hover:bg-slate-100 transition-colors">
                            ⚙️ DB & Drive
                        </a>
                        <a href="{{ route('superadmin.accounts') }}" class="min-btn inline-flex items-center justify-center px-4 py-2.5 rounded-none bg-purple-700 text-white font-bold text-xs shadow hover:bg-purple-600 transition-colors">
                            👥 Akun Pengguna
                        </a>
                        @endcan
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white rounded-none p-5 border border-slate-300 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Karyawan</p>
                        <p class="text-3xl font-black text-slate-900 mt-1">{{ $stats['total_karyawan'] }}</p>
                        <p class="text-xs text-slate-400 mt-1">Perusahaan</p>
                    </div>
                    <div class="w-12 h-12 rounded-none bg-blue-50 text-blue-600 flex items-center justify-center text-2xl font-bold shadow-inner">
                        👥
                    </div>
                </div>

                <div class="bg-white rounded-none p-5 border border-slate-300 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Dokumen</p>
                        <p class="text-3xl font-black text-slate-900 mt-1">{{ count($accessibleDocs) }}</p>
                        <p class="text-xs text-slate-400 mt-1">Dalam Sistem</p>
                    </div>
                    <div class="w-12 h-12 rounded-none bg-purple-50 text-purple-600 flex items-center justify-center text-2xl font-bold shadow-inner">
                        📄
                    </div>
                </div>

                <div class="bg-white rounded-none p-5 border border-slate-300 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-bold text-amber-700 uppercase tracking-wider">Sedang Berjalan</p>
                        <p class="text-3xl font-black text-amber-600 mt-1">
                            {{ count(array_filter($accessibleDocs, fn($d) => in_array($d['status'] ?? '', ['menunggu_foreman', 'menunggu_section_head', 'menunggu_dept_head']))) }}
                        </p>
                        <p class="text-xs text-slate-400 mt-1">Foreman / Section / Dept</p>
                    </div>
                    <div class="w-12 h-12 rounded-none bg-amber-50 text-amber-600 flex items-center justify-center text-2xl font-bold shadow-inner">
                        ⏳
                    </div>
                </div>

                <div class="bg-white rounded-none p-5 border border-slate-300 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-bold text-emerald-700 uppercase tracking-wider">Selesai & Drive</p>
                        <p class="text-3xl font-black text-emerald-600 mt-1">{{ $stats['dokumen_selesai'] }}</p>
                        <p class="text-xs text-slate-400 mt-1">Telah Terbit PDF & QR</p>
                    </div>
                    <div class="w-12 h-12 rounded-none bg-emerald-50 text-emerald-600 flex items-center justify-center text-2xl font-bold shadow-inner">
                        ✅
                    </div>
                </div>
            </div>

            <!-- Monitoring Table -->
            <div class="bg-white rounded-none border border-slate-300 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50/50">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Monitoring Seluruh Dokumen Penilaian</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Pemantauan progres persetujuan digital di seluruh divisi.</p>
                    </div>
                    <a href="{{ route('penilaian.cari') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-none bg-emerald-600 text-white font-bold text-xs shadow-sm hover:bg-emerald-700">
                        <span>🔍</span>
                        <span>Cari / Buka Dokumen</span>
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-100 text-slate-700 font-bold border-b border-slate-200 uppercase text-[10px] tracking-wider">
                                <th class="py-3 px-4 w-12 text-center">No</th>
                                <th class="py-3 px-4">NRP & Nama Lengkap</th>
                                <th class="py-3 px-4">Department / Section</th>
                                <th class="py-3 px-4">Jenis Form</th>
                                <th class="py-3 px-4 text-center">Status</th>
                                <th class="py-3 px-4 text-center">Nilai</th>
                                <th class="py-3 px-4 text-center w-36">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            @forelse($accessibleDocs as $idx => $doc)
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="py-3 px-4 text-center font-bold text-slate-400">{{ $idx + 1 }}</td>
                                <td class="py-3 px-4">
                                    <span class="font-bold text-slate-900 block">{{ $doc['karyawan']['nama_lengkap'] ?? '-' }}</span>
                                    <span class="text-[10px] font-mono text-slate-500">NRP: {{ $doc['nrp'] }}</span>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="font-bold text-slate-700 block">{{ $doc['karyawan']['departemen'] ?? '-' }}</span>
                                    <span class="text-[10px] text-slate-400">{{ $doc['karyawan']['seksi'] ?? '-' }}</span>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-0.5 rounded-none font-mono font-bold text-[10px] bg-slate-100 text-slate-700 border border-slate-300">
                                        {{ $doc['kode_form'] }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    @if(($doc['status'] ?? '') === 'selesai')
                                        <span class="px-2 py-0.5 rounded-none text-[10px] font-bold bg-emerald-100 text-emerald-800">Selesai</span>
                                    @elseif(($doc['status'] ?? '') === 'menunggu_dept_head')
                                        <span class="px-2 py-0.5 rounded-none text-[10px] font-bold bg-purple-100 text-purple-800">Dept Head</span>
                                    @elseif(($doc['status'] ?? '') === 'menunggu_section_head')
                                        <span class="px-2 py-0.5 rounded-none text-[10px] font-bold bg-blue-100 text-blue-800">Section Head</span>
                                    @elseif(($doc['status'] ?? '') === 'menunggu_foreman')
                                        <span class="px-2 py-0.5 rounded-none text-[10px] font-bold bg-amber-100 text-amber-800">Foreman</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-none text-[10px] font-bold bg-slate-100 text-slate-600">{{ $doc['status'] ?? '-' }}</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-center font-bold font-mono">
                                    {{ !empty($doc['total_nilai']) ? number_format((float)$doc['total_nilai'], 2) : '-' }}
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <a href="{{ route('penilaian.show.admin', $doc['id']) }}" class="px-3 py-1.5 rounded-none font-bold text-xs bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 shadow-sm inline-block">
                                        Lihat Form
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="py-8 text-center text-slate-400">Belum ada dokumen penilaian aktif.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

@endsection
