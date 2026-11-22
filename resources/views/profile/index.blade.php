@extends('layouts.app', ['title' => 'Profil Pengguna & TTD'])

@section('content')
<div class="space-y-6" x-data="profileManager()">

    <!-- 1. Top Header Banner (Merah Musashi) -->
    <div class="bg-gradient-to-r from-[#962226] via-[#92302e] to-[#781b1f] text-white rounded-none p-5 sm:p-6 border border-[#781b1f] shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 bg-white/10 backdrop-blur-sm border border-white/20 flex items-center justify-center text-white text-xl font-black shadow-inner flex-shrink-0">
                <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                </svg>
            </div>
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight">
                        {{ $user->nama }}
                    </h1>
                    <span class="px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider bg-white text-[#92302e] shadow-xs">
                        {{ $user->roleLabel() }}
                    </span>
                </div>
                <p class="text-xs text-red-100 font-medium mt-1">
                    NRP: <strong class="font-mono text-white">{{ $user->nrp ?: '-' }}</strong> &bull; {{ $user->email }} &bull; {{ $user->resolved_departemen }}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('dashboard') }}" class="px-3.5 py-2 bg-white/10 hover:bg-white/20 text-white font-bold text-xs border border-white/20 transition-all flex items-center gap-2">
                <span>&larr;</span> Kembali ke Dashboard
            </a>
        </div>
    </div>

    <!-- 2. Main Profile Form -->
    <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            
            <!-- LEFT COLUMN: Biodata Master Karyawan & Akun Pengguna (7 Cols) -->
            <div class="lg:col-span-7 space-y-6">
                
                <!-- Card 1: Biodata Profil Pengguna -->
                <div class="bg-white border border-slate-300 shadow-sm rounded-none overflow-hidden">
                    <div class="px-5 py-3.5 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-base text-[#92302e]">👤</span>
                            <h2 class="text-xs font-black text-slate-800 uppercase tracking-wider">Biodata Profil Pengguna</h2>
                        </div>
                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Master Karyawan &amp; Akun</span>
                    </div>

                    <div class="p-5 sm:p-6 space-y-4">
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- NRP (Readonly) -->
                            <div>
                                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-700 mb-1">
                                    NRP (Nomor Registrasi Pokok)
                                </label>
                                <input type="text" value="{{ $user->nrp ?: '-' }}" readonly 
                                       class="w-full text-xs font-mono font-bold text-slate-700 bg-slate-100 border border-slate-300 px-3 py-2 cursor-not-allowed">
                                <p class="text-[10px] text-slate-400 mt-0.5">Identitas NRP terhubung ke database master karyawan.</p>
                            </div>

                            <!-- Hak Akses / Role (Readonly) -->
                            <div>
                                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-700 mb-1">
                                    Hak Akses (Role)
                                </label>
                                <input type="text" value="{{ $user->roleLabel() }}" readonly 
                                       class="w-full text-xs font-bold text-slate-700 bg-slate-100 border border-slate-300 px-3 py-2 cursor-not-allowed">
                                <p class="text-[10px] text-slate-400 mt-0.5">Role diset oleh Super Administrator.</p>
                            </div>
                        </div>

                        <!-- Nama Lengkap -->
                        <div>
                            <label for="namaInput" class="block text-[11px] font-bold uppercase tracking-wider text-slate-700 mb-1">
                                Nama Lengkap <span class="text-rose-600">*</span>
                            </label>
                            <input type="text" id="namaInput" name="nama" value="{{ old('nama', $user->nama) }}" required 
                                   class="w-full text-xs font-semibold text-slate-900 border border-slate-300 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] px-3 py-2 transition shadow-2xs">
                        </div>

                        <!-- Email Perusahaan -->
                        <div>
                            <label for="emailInput" class="block text-[11px] font-bold uppercase tracking-wider text-slate-700 mb-1">
                                Email Perusahaan <span class="text-rose-600">*</span>
                            </label>
                            <input type="email" id="emailInput" name="email" value="{{ old('email', $user->email) }}" required 
                                   class="w-full text-xs font-medium font-mono text-slate-900 border border-slate-300 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] px-3 py-2 transition shadow-2xs">
                            <p class="text-[10px] text-slate-400 mt-0.5">Digunakan untuk login dan notifikasi sistem.</p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-slate-200">
                            <!-- Jabatan -->
                            <div>
                                <label for="jabatanInput" class="block text-[11px] font-bold uppercase tracking-wider text-slate-700 mb-1">
                                    Jabatan
                                </label>
                                <input type="text" id="jabatanInput" name="jabatan" value="{{ old('jabatan', $user->resolved_jabatan) }}" 
                                       placeholder="Contoh: FOREMAN, SECTION HEAD, STAFF..."
                                       class="w-full text-xs font-semibold text-slate-900 border border-slate-300 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] px-3 py-2 transition shadow-2xs">
                            </div>

                            <!-- Posisi / Bagian / Seksi -->
                            <div>
                                <label for="posisiInput" class="block text-[11px] font-bold uppercase tracking-wider text-slate-700 mb-1">
                                    Posisi / Seksi / Bagian
                                </label>
                                <input type="text" id="posisiInput" name="posisi" value="{{ old('posisi', $user->resolved_posisi) }}" 
                                       placeholder="Contoh: PRODUCTION 2 SECTION, ASSY..."
                                       class="w-full text-xs font-semibold text-slate-900 border border-slate-300 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] px-3 py-2 transition shadow-2xs">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Departemen -->
                            <div>
                                <label for="departemenInput" class="block text-[11px] font-bold uppercase tracking-wider text-slate-700 mb-1">
                                    Departemen
                                </label>
                                @if($user->departemen === 'ALL')
                                    <input type="text" value="ALL (Semua Departemen)" readonly 
                                           class="w-full text-xs font-bold text-slate-700 bg-slate-100 border border-slate-300 px-3 py-2 cursor-not-allowed">
                                @else
                                    <input type="text" id="departemenInput" name="departemen" value="{{ old('departemen', $user->resolved_departemen) }}" 
                                           class="w-full text-xs font-semibold text-slate-900 border border-slate-300 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] px-3 py-2 transition shadow-2xs">
                                @endif
                            </div>

                            <!-- Divisi -->
                            <div>
                                <label for="divisiInput" class="block text-[11px] font-bold uppercase tracking-wider text-slate-700 mb-1">
                                    Divisi
                                </label>
                                <input type="text" id="divisiInput" name="divisi" value="{{ old('divisi', $user->resolved_divisi) }}" 
                                       placeholder="Contoh: PRODUCTION, TECHNICAL, GENERAL..."
                                       class="w-full text-xs font-semibold text-slate-900 border border-slate-300 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] px-3 py-2 transition shadow-2xs">
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Card 2: Pengaturan Keamanan Password (Opsional) -->
                <div class="bg-white border border-slate-300 shadow-sm rounded-none overflow-hidden" x-data="{ showNewPw: false, showConfirmPw: false }">
                    <div class="px-5 py-3.5 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-base text-amber-600">🔒</span>
                            <h2 class="text-xs font-black text-slate-800 uppercase tracking-wider">Ubah Password Akun (Opsional)</h2>
                        </div>
                        <span class="text-[10px] text-slate-500 font-medium">Kosongkan jika tidak ingin mengubah</span>
                    </div>

                    <div class="p-5 sm:p-6 space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="passwordInput" class="block text-[11px] font-bold uppercase tracking-wider text-slate-700 mb-1">
                                    Password Baru
                                </label>
                                <div class="relative">
                                    <input :type="showNewPw ? 'text' : 'password'" id="passwordInput" name="password" placeholder="Minimal 4 karakter..." 
                                           class="w-full text-xs font-mono font-semibold text-slate-900 border border-slate-300 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] px-3 py-2 pr-9 transition shadow-2xs">
                                    <button type="button" @click="showNewPw = !showNewPw" class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-400 hover:text-slate-700 cursor-pointer" title="Lihat/Sembunyikan Password">
                                        <svg x-show="!showNewPw" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        <svg x-show="showNewPw" class="w-4 h-4 text-[#92302e]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label for="passwordConfirmInput" class="block text-[11px] font-bold uppercase tracking-wider text-slate-700 mb-1">
                                    Konfirmasi Password Baru
                                </label>
                                <div class="relative">
                                    <input :type="showConfirmPw ? 'text' : 'password'" id="passwordConfirmInput" name="password_confirmation" placeholder="Ulangi password baru..." 
                                           class="w-full text-xs font-mono font-semibold text-slate-900 border border-slate-300 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] px-3 py-2 pr-9 transition shadow-2xs">
                                    <button type="button" @click="showConfirmPw = !showConfirmPw" class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-400 hover:text-slate-700 cursor-pointer" title="Lihat/Sembunyikan Password">
                                        <svg x-show="!showConfirmPw" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        <svg x-show="showConfirmPw" class="w-4 h-4 text-[#92302e]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- RIGHT COLUMN: Barcode Tanda Tangan (QR Code Digital) (5 Cols) -->
            <div class="lg:col-span-5 space-y-6">
                
                <div class="bg-white border-2 border-[#92302e]/30 shadow-md rounded-none overflow-hidden">
                    <div class="px-5 py-3.5 bg-gradient-to-r from-[#92302e] to-[#781b1f] text-white flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-base">📱</span>
                            <h2 class="text-xs font-black uppercase tracking-wider">Barcode Tanda Tangan</h2>
                        </div>
                        <span class="px-2 py-0.5 text-[9.5px] font-black uppercase tracking-wider bg-emerald-500 text-white shadow-xs">
                            ✓ Terverifikasi Otomatis
                        </span>
                    </div>

                    <div class="p-5 sm:p-6 space-y-5">
                        
                        <!-- Status & Penjelasan Otomasi Barcode TTD -->
                        <div class="p-3.5 bg-emerald-50 border border-emerald-300 text-emerald-950 text-xs flex items-start gap-3">
                            <span class="text-lg leading-none mt-0.5 text-emerald-600 font-bold">ℹ️</span>
                            <div class="space-y-1">
                                <strong class="font-black text-emerald-900 block text-[11.5px]">Otomasi Barcode Tanda Tangan (Sheet users):</strong>
                                <p class="text-[11px] text-emerald-800 leading-relaxed">
                                    Tanda tangan digital Anda kini menggunakan <strong>Barcode Tanda Tangan Resmi (QR Code)</strong> yang tersinkronisasi langsung dari kolom <code class="font-mono bg-emerald-100 px-1 py-0.5 text-emerald-950 font-bold">barcodettd</code> sheet <strong>users</strong>.
                                    Anda <strong>tidak perlu lagi mengunggah tanda tangan</strong> secara manual. Barcode ini akan langsung digunakan secara otomatis saat Anda menandatangani penilaian karyawan.
                                </p>
                            </div>
                        </div>

                        <!-- Barcode Display Box -->
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-700 mb-2">
                                Tampilan Barcode Tanda Tangan Saat Ini:
                            </label>

                            <div class="border-2 border-slate-300 bg-white p-5 flex flex-col items-center justify-center text-center shadow-xs">
                                <div class="p-3 bg-white border border-slate-200 shadow-sm inline-block">
                                    <img src="{{ $user->barcode_ttd }}" 
                                         alt="Barcode TTD {{ $user->nama }}" 
                                         class="w-36 h-36 mx-auto object-contain">
                                </div>
                                <div class="mt-4 space-y-1 w-full max-w-xs text-left bg-slate-50 p-3 border border-slate-200 text-xs">
                                    <div class="flex justify-between border-b border-slate-200 pb-1">
                                        <span class="text-slate-500 font-medium">NRP:</span>
                                        <span class="font-bold font-mono text-slate-800">{{ $user->nrp ?: '-' }}</span>
                                    </div>
                                    <div class="flex justify-between border-b border-slate-200 py-1">
                                        <span class="text-slate-500 font-medium">Nama:</span>
                                        <span class="font-bold text-slate-800 truncate ml-2">{{ $user->nama }}</span>
                                    </div>
                                    <div class="flex justify-between border-b border-slate-200 py-1">
                                        <span class="text-slate-500 font-medium">Peran / Jabatan:</span>
                                        <span class="font-bold text-[#92302e] ml-2">{{ $user->roleLabel() }}</span>
                                    </div>
                                    <div class="flex justify-between pt-1">
                                        <span class="text-slate-500 font-medium">Departemen:</span>
                                        <span class="font-bold text-slate-800 ml-2 truncate">{{ $user->resolved_departemen }}</span>
                                    </div>
                                </div>
                                <div class="mt-3 flex items-center justify-center gap-1.5 text-[11px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-3 py-1 rounded">
                                    <span>✓</span> QR Barcode Aktif &amp; Terverifikasi Resmi PT Musashi
                                </div>
                            </div>
                        </div>

                        <!-- Sheet Users Integration Box -->
                        <div class="p-3 bg-blue-50/80 border border-blue-200 text-blue-950 text-xs space-y-1">
                            <div class="font-bold flex items-center justify-between text-[11px]">
                                <span class="flex items-center gap-1.5 text-blue-900">
                                    <svg class="w-4 h-4 text-blue-600 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96z"/>
                                    </svg>
                                    Sinkronisasi Sheet users:
                                </span>
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-mono bg-blue-100 text-blue-800 font-bold">
                                    kolom: barcodettd
                                </span>
                            </div>
                            <p class="text-[10.5px] text-blue-800 leading-tight">
                                Saat akun kepemimpinan dibuat atau disinkronkan dari Master Karyawan, barcode identitas ini otomatis tersimpan di sheet <code>users</code> dan langsung aktif untuk proses PerformanceAppraisal.
                            </p>
                        </div>

                    </div>
                </div>

            </div>

        </div>

        <!-- Sticky Bottom Action Bar -->
        <div class="bg-white p-4 border border-slate-300 shadow-md flex flex-col sm:flex-row items-center justify-between gap-3">
            <p class="text-xs text-slate-500 font-medium text-center sm:text-left">
                Pastikan data profil sudah benar sebelum menekan tombol simpan.
            </p>
            <div class="flex items-center gap-2.5 w-full sm:w-auto justify-end">
                <a href="{{ route('dashboard') }}" class="px-4 py-2.5 border border-slate-300 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-colors">
                    Batal
                </a>
                <button type="submit" class="px-6 py-2.5 bg-[#92302e] hover:bg-[#7e2524] text-white text-xs font-bold transition-all shadow-md flex items-center justify-center gap-2 cursor-pointer">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span>Simpan Perubahan Profil</span>
                </button>
            </div>
        </div>

    </form>

</div>

<script>
function profileManager() {
    return {};
}
</script>
@endsection

