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
                <div class="bg-white border border-slate-300 shadow-sm rounded-none overflow-hidden">
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
                                <input type="password" id="passwordInput" name="password" placeholder="Minimal 4 karakter..." 
                                       class="w-full text-xs font-medium text-slate-900 border border-slate-300 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] px-3 py-2 transition shadow-2xs">
                            </div>
                            <div>
                                <label for="passwordConfirmInput" class="block text-[11px] font-bold uppercase tracking-wider text-slate-700 mb-1">
                                    Konfirmasi Password Baru
                                </label>
                                <input type="password" id="passwordConfirmInput" name="password_confirmation" placeholder="Ulangi password baru..." 
                                       class="w-full text-xs font-medium text-slate-900 border border-slate-300 focus:outline-none focus:border-[#92302e] focus:ring-1 focus:ring-[#92302e] px-3 py-2 transition shadow-2xs">
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- RIGHT COLUMN: Tanda Tangan Digital (Gambar TTD) (5 Cols) -->
            <div class="lg:col-span-5 space-y-6">
                
                <div class="bg-white border-2 border-[#92302e]/30 shadow-md rounded-none overflow-hidden">
                    <div class="px-5 py-3.5 bg-gradient-to-r from-[#92302e] to-[#781b1f] text-white flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-base">✍️</span>
                            <h2 class="text-xs font-black uppercase tracking-wider">Tanda Tangan Digital (TTD)</h2>
                        </div>
                        @if($user->signature_url)
                            <span class="px-2 py-0.5 text-[9.5px] font-black uppercase tracking-wider bg-emerald-500 text-white shadow-xs">
                                ✓ Siap Pakai
                            </span>
                        @else
                            <span class="px-2 py-0.5 text-[9.5px] font-black uppercase tracking-wider bg-amber-400 text-amber-950 shadow-xs">
                                Belum Ada
                            </span>
                        @endif
                    </div>

                    <div class="p-5 sm:p-6 space-y-5">
                        
                        <!-- Status & Penjelasan Otomasi TTD -->
                        <div class="p-3.5 bg-emerald-50 border border-emerald-300 text-emerald-950 text-xs flex items-start gap-3">
                            <span class="text-lg leading-none mt-0.5 text-emerald-600 font-bold">ℹ️</span>
                            <div class="space-y-1">
                                <strong class="font-black text-emerald-900 block text-[11.5px]">Otomasi Tanda Tangan Digital:</strong>
                                <p class="text-[11px] text-emerald-800 leading-relaxed">
                                    Gambar tanda tangan yang Anda simpan di sini akan <strong>otomatis langsung terpasang</strong> saat Anda menandatangani penilaian karya. Anda tidak perlu lagi menggambar di layar atau memasukkan password saat approval.
                                </p>
                            </div>
                        </div>

                        <!-- Current Signature Box -->
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-700 mb-2">
                                Tampilan Tanda Tangan Saat Ini:
                            </label>

                            <!-- Live preview or saved signature -->
                            <div class="border-2 border-dashed border-slate-300 bg-slate-50 p-4 min-h-[160px] flex flex-col items-center justify-center text-center relative overflow-hidden group">
                                
                                <template x-if="newSignaturePreview">
                                    <div class="space-y-2 w-full">
                                        <div class="text-[10px] font-bold uppercase tracking-wider text-emerald-700 bg-emerald-100 px-2 py-0.5 inline-block">
                                            Preview Gambar Baru (Belum Disimpan)
                                        </div>
                                        <img :src="newSignaturePreview" alt="Preview TTD Baru" class="max-h-28 mx-auto object-contain bg-white p-2 border border-slate-300 shadow-sm">
                                        <p class="text-[11px] font-mono text-slate-600 truncate max-w-xs mx-auto" x-text="newSignatureFileName"></p>
                                        <button type="button" @click="cancelNewSignature()" class="text-xs text-rose-600 hover:text-rose-800 font-bold underline">
                                            Batal Ganti Gambar
                                        </button>
                                    </div>
                                </template>

                                <template x-if="!newSignaturePreview">
                                    <div>
                                        @if($user->signature_url)
                                            <div class="space-y-3">
                                                <div class="bg-white p-3 border border-slate-300 shadow-xs inline-block">
                                                    <img src="{{ $user->signature_url }}" alt="TTD {{ $user->nama }}" class="max-h-28 max-w-full object-contain mx-auto">
                                                </div>
                                                <div class="flex items-center justify-center gap-2">
                                                    <span class="text-[11px] text-emerald-700 font-bold flex items-center gap-1">
                                                        <span>✓</span> Tanda Tangan Aktif Tersimpan
                                                    </span>
                                                </div>
                                            </div>
                                        @else
                                            <div class="py-4 space-y-2 text-slate-400">
                                                <svg class="w-12 h-12 mx-auto text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                                </svg>
                                                <p class="text-xs font-semibold text-slate-600">Belum Ada Tanda Tangan Tersimpan</p>
                                                <p class="text-[11px] text-slate-400">Silakan unggah berkas gambar tanda tangan Anda di bawah ini.</p>
                                            </div>
                                        @endif
                                    </div>
                                </template>

                            </div>
                        </div>

                        <!-- Upload File Input -->
                        <div class="space-y-2">
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-700">
                                Unggah / Ganti Gambar Tanda Tangan:
                            </label>
                            
                            <div class="flex items-center gap-2">
                                <input type="file" 
                                       id="signature_file_field"
                                       name="signature_file" 
                                       accept="image/png,image/jpeg,image/webp" 
                                       @change="handleFileSelect($event)"
                                       class="w-full text-xs text-slate-700 file:mr-3 file:py-2 file:px-4 file:rounded-none file:border-0 file:text-xs file:font-bold file:bg-[#92302e] file:text-white hover:file:bg-[#7e2524] file:cursor-pointer border border-slate-300 bg-white cursor-pointer shadow-2xs">
                            </div>
                            <p class="text-[10.5px] text-slate-500">
                                Format: <strong>PNG</strong> (sangat disarankan dengan background transparan), JPG, atau WebP (Maksimal 5MB).
                            </p>
                        </div>

                        <!-- Delete Existing Signature Option -->
                        @if($user->signature_url)
                        <div class="pt-3 border-t border-slate-200">
                            <label class="inline-flex items-center gap-2 cursor-pointer text-xs text-rose-700 font-bold hover:text-rose-900">
                                <input type="checkbox" name="delete_signature" value="1" class="rounded-none text-rose-600 focus:ring-rose-500 border-slate-300">
                                <span>Centang untuk menghapus tanda tangan yang tersimpan saat ini</span>
                            </label>
                        </div>
                        @endif

                    </div>
                </div>

                <!-- Info Synchronization -->
                <div class="p-4 bg-slate-100 border border-slate-300 text-slate-600 text-xs space-y-1.5">
                    <strong class="font-bold text-slate-800 block text-[11px] uppercase tracking-wider">Keamanan &amp; Integrasi Cloud:</strong>
                    <p class="text-[11px] leading-relaxed">
                        Tanda tangan digital Anda disimpan secara aman di server lokal dan otomatis tersinkronisasi ke folder database dokumen (Google Drive &amp; Sheets) saat berkas penilaian karya difinalisasi.
                    </p>
                </div>

            </div>

        </div>

        <!-- Sticky Bottom Action Bar -->
        <div class="bg-white p-4 border border-slate-300 shadow-md flex flex-col sm:flex-row items-center justify-between gap-3">
            <p class="text-xs text-slate-500 font-medium text-center sm:text-left">
                Pastikan data profil dan gambar tanda tangan sudah benar sebelum menekan tombol simpan.
            </p>
            <div class="flex items-center gap-2.5 w-full sm:w-auto justify-end">
                <a href="{{ route('dashboard') }}" class="px-4 py-2.5 border border-slate-300 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-colors">
                    Batal
                </a>
                <button type="submit" class="px-6 py-2.5 bg-[#92302e] hover:bg-[#7e2524] text-white text-xs font-bold transition-all shadow-md flex items-center justify-center gap-2 cursor-pointer">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span>Simpan Perubahan Profil &amp; TTD</span>
                </button>
            </div>
        </div>

    </form>

</div>

<script>
function profileManager() {
    return {
        newSignaturePreview: null,
        newSignatureFileName: '',
        handleFileSelect(event) {
            const file = event.target.files[0];
            if (!file) {
                this.newSignaturePreview = null;
                this.newSignatureFileName = '';
                return;
            }
            this.newSignatureFileName = file.name;
            const reader = new FileReader();
            reader.onload = (e) => {
                this.newSignaturePreview = e.target.result;
            };
            reader.readAsDataURL(file);
        },
        cancelNewSignature() {
            this.newSignaturePreview = null;
            this.newSignatureFileName = '';
            const input = document.getElementById('signature_file_field');
            if (input) input.value = '';
        }
    };
}
</script>
@endsection

