@extends('layouts.app', ['title' => 'Verifikasi Dokumen Penilaian Karya'])

@section('content')
<div class="max-w-2xl mx-auto py-8 px-4">
    <div class="bg-white rounded-3xl border border-slate-200 shadow-xl overflow-hidden">
        
        <!-- Verification Header Badge -->
        <div class="p-6 bg-gradient-to-r from-emerald-700 via-teal-800 to-slate-900 text-white text-center relative">
            <div class="w-16 h-16 rounded-2xl bg-white/10 backdrop-blur-md border border-white/20 mx-auto flex items-center justify-center text-3xl mb-3 shadow-inner">
                ✓
            </div>
            <span class="inline-block px-3 py-1 rounded-full text-xs font-black tracking-wider uppercase bg-emerald-400 text-emerald-950 mb-1">
                DOKUMEN ASLI &amp; TERVERIFIKASI
            </span>
            <h1 class="text-xl font-black mt-1">PT Musashi Auto Parts Indonesia</h1>
            <p class="text-xs text-emerald-200 mt-0.5">Sistem Tanda Tangan Digital &amp; Penilaian Karya Resmi</p>
        </div>

        <div class="p-6 space-y-6 text-xs">
            
            <!-- Document Details Card -->
            <div class="border border-slate-200 rounded-2xl p-4 bg-slate-50/50 space-y-2.5">
                <div class="flex justify-between items-center border-b border-slate-200 pb-2">
                    <span class="text-slate-500 font-semibold">Kode Verifikasi:</span>
                    <span class="font-mono font-bold text-emerald-700 text-sm">{{ $dokumen['kode_verifikasi'] }}</span>
                </div>
                <div class="flex justify-between items-center border-b border-slate-200 pb-2">
                    <span class="text-slate-500 font-semibold">Formulir:</span>
                    <span class="font-bold text-slate-900">{{ $dokumen['kode_form'] }} ({{ $karyawan['kj'] ?? 'KJ 1-3' }})</span>
                </div>
                <div class="flex justify-between items-center border-b border-slate-200 pb-2">
                    <span class="text-slate-500 font-semibold">Nama Karyawan:</span>
                    <span class="font-bold text-slate-900 uppercase">{{ $karyawan['nama_lengkap'] }}</span>
                </div>
                <div class="flex justify-between items-center border-b border-slate-200 pb-2">
                    <span class="text-slate-500 font-semibold">NRP:</span>
                    <span class="font-mono font-bold text-slate-800">{{ $karyawan['nrp'] }}</span>
                </div>
                <div class="flex justify-between items-center border-b border-slate-200 pb-2">
                    <span class="text-slate-500 font-semibold">Departemen / Seksi:</span>
                    <span class="font-bold text-slate-800">{{ $karyawan['departemen'] }} &bull; {{ $karyawan['seksi'] }}</span>
                </div>
                <div class="flex justify-between items-center border-b border-slate-200 pb-2">
                    <span class="text-slate-500 font-semibold">Tahun Periode:</span>
                    <span class="font-bold font-mono text-slate-800">{{ $periode['tahun'] ?? 2025 }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-slate-500 font-semibold">Status Dokumen:</span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold {{ $dokumen['status'] === 'selesai' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                        {{ strtoupper(str_replace('_', ' ', $dokumen['status'])) }}
                    </span>
                </div>
            </div>

            <!-- Total Score & Grade (if completed) -->
            @if(!empty($dokumen['total_nilai']))
            <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-4 flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold text-emerald-900 block">Total Nilai Akhir Penilaian</span>
                    <span class="text-xs text-emerald-700">Telah ditandatangani dan dikunci secara digital</span>
                </div>
                <div class="text-right">
                    <div class="text-2xl font-black font-mono text-emerald-950">{{ number_format($dokumen['total_nilai'], 2) }}</div>
                    @if(!empty($dokumen['grade_original']))
                        <span class="inline-block px-2 py-0.5 rounded bg-emerald-600 text-white font-bold text-[10px]">
                            Grade: {{ $dokumen['docking_final'] ?? $dokumen['grade_original'] }}
                        </span>
                    @endif
                </div>
            </div>
            @endif

            <!-- Signatures Audit Trail -->
            <div>
                <h3 class="text-sm font-bold text-slate-900 mb-3 flex items-center gap-2">
                    <span>✍</span> Riwayat Penandatanganan Digital
                </h3>
                <div class="space-y-3">
                    @forelse($ttdList as $ttd)
                    <div class="p-3.5 rounded-xl border border-slate-200 bg-white shadow-sm flex items-start gap-3">
                        <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-800 font-black flex items-center justify-center flex-shrink-0 text-xs">
                            ✓
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-slate-900 text-xs uppercase">{{ $ttd['level'] }}</span>
                                <span class="text-[10px] text-slate-400 font-mono">{{ $ttd['tgl_ttd'] }}</span>
                            </div>
                            <div class="text-slate-600 text-[11px] mt-0.5">
                                Ditandatangani oleh: <strong>{{ $ttd['user']['nama'] ?? 'User ID: ' . $ttd['user_id'] }}</strong> (NRP: {{ $ttd['user']['nrp'] ?? '-' }})
                            </div>
                            @if(!empty($ttd['hash_snapshot']))
                            <div class="text-[9.5px] text-slate-400 font-mono mt-1 truncate">
                                SHA-256: {{ $ttd['hash_snapshot'] }}
                            </div>
                            @endif
                        </div>
                    </div>
                    @empty
                    <div class="p-4 rounded-xl border border-dashed border-slate-300 text-center text-slate-400 italic">
                        Belum ada tanda tangan digital yang dibubuhkan pada dokumen ini.
                    </div>
                    @endforelse
                </div>
            </div>

            <div class="text-center pt-2">
                <a href="{{ route('login') }}" class="min-btn inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200">
                    &larr; Masuk ke Sistem Penilaian Karya
                </a>
            </div>

        </div>

    </div>
</div>
@endsection

