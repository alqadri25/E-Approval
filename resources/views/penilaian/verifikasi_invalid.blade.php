@extends('layouts.app', ['title' => 'Verifikasi Dokumen Tidak Ditemukan'])

@section('content')
<div class="max-w-md mx-auto py-12 px-4">
    <div class="bg-white rounded-3xl border border-rose-200 shadow-xl p-6 text-center space-y-4">
        <div class="w-16 h-16 rounded-2xl bg-rose-100 text-rose-600 mx-auto flex items-center justify-center text-3xl font-black shadow-inner">
            ✕
        </div>
        <div>
            <h1 class="text-lg font-black text-slate-900">Dokumen Tidak Valid</h1>
            <p class="text-xs text-slate-500 mt-1">
                Kode verifikasi <code class="bg-slate-100 px-1.5 py-0.5 rounded text-rose-600 font-mono font-bold">{{ $kode }}</code> tidak terdaftar di dalam sistem PT Musashi Auto Parts Indonesia.
            </p>
        </div>
        <p class="text-[11px] text-slate-400">
            Pastikan Anda memindai kode QR resmi yang tercetak pada dokumen penilaian karyawan Musashi yang sah.
        </p>
        <div class="pt-2">
            <a href="{{ route('login') }}" class="min-btn inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200">
                Kembali ke Beranda
            </a>
        </div>
    </div>
</div>
@endsection

