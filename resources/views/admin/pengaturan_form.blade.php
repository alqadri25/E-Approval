@extends('layouts.app', ['title' => 'Kelola Formulir & Periode Penilaian (PK)'])

@section('content')
<div x-data="formPengaturanStudio()" x-init="init()" class="space-y-6">


    <!-- Top Header Banner (Musashi Red Banner, Solid & Clean) -->
    <div class="bg-gradient-to-r from-[#962226] via-[#92302e] to-[#781b1f] p-5 sm:p-6 text-white shadow-sm border border-[#781b1f] rounded-none">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl sm:text-2xl font-black tracking-tight text-white">
                    Kelola Formulir &amp; Periode Penilaian (PK)
                </h1>
                <p class="mt-1 text-red-100 text-xs sm:text-[13px] max-w-4xl font-normal leading-relaxed">
                    Pusat kendali parameter formulir dan kustomisasi aspek penilaian (KJ 1-3, KJ 4-5, Leader, Foreman, Section/Dept/Div Head). Seluruh perubahan bobot langsung terhubung ke lembar penilaian web penilai dan PDF resmi Musashi.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5 flex-shrink-0">
                <a href="{{ route('penilaian.cari') }}" class="px-4 py-2.5 text-xs font-bold text-slate-800 bg-white hover:bg-slate-100 shadow-xs flex items-center gap-1.5 transition-all rounded-none cursor-pointer">
                    <span>🔍</span> Buka Form Penilaian
                </a>
                <a href="{{ route('dashboard') }}" class="px-4 py-2.5 text-xs font-bold text-white bg-white/15 hover:bg-white/25 border border-white/30 shadow-xs flex items-center gap-1.5 transition-all rounded-none cursor-pointer">
                    <span>📊</span> Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Quick Info Cards (Bersudut Tegas, Border Slate Halus) -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-white p-4 border border-slate-300 shadow-2xs rounded-none">
            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">Tahun Penilaian (Aktif)</span>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-2xl font-black text-[#92302e] font-mono" x-text="formSettings.tahun_penilaian">{{ $formSettings['tahun_penilaian'] }}</span>
                <span class="text-xs font-semibold text-slate-500">Tahun Aktif</span>
            </div>
            <span class="text-[10.5px] text-slate-400 mt-1 block">Tampil di header web &amp; PDF</span>
        </div>

        <div class="bg-white p-4 border border-slate-300 shadow-2xs rounded-none">
            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">Periode Evaluasi</span>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-xl font-black text-slate-900" x-text="formSettings.periode_penilaian">{{ $formSettings['periode_penilaian'] }}</span>
            </div>
            <span class="text-[10.5px] text-slate-400 mt-1 block">Siklus penilaian berjalan</span>
        </div>

        <div class="bg-white p-4 border border-slate-300 shadow-2xs rounded-none">
            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">Status Periode</span>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-300 rounded-none">
                    <span class="w-1.5 h-1.5 bg-emerald-600 animate-pulse"></span>
                    <span x-text="(formSettings.status_periode || 'aktif').toUpperCase()">{{ strtoupper($formSettings['status_periode']) }}</span>
                </span>
            </div>
            <span class="text-[10.5px] text-slate-400 mt-1 block">Siap dinilai &amp; ditandatangani</span>
        </div>

        <div class="bg-white p-4 border border-slate-300 shadow-2xs rounded-none">
            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">Form Dipilih</span>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-xl font-black text-slate-900 font-mono" x-text="selectedKode">07-004</span>
                <span class="text-xs font-bold text-[#92302e]" x-text="'Total Bobot: ' + totalWeight() + '%'"></span>
            </div>
            <span class="text-[10.5px] text-slate-400 mt-1 block" x-text="currentFormMeta.nama"></span>
        </div>
    </div>

    <!-- MAIN 2-COLUMN SPLIT WORKSPACE: Form Customizer (Left 50%) & Pure PDF Preview (Right 50%) -->
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 items-start">

        <!-- ========================================================================= -->
        <!-- LEFT COLUMN: Customizer Form Lengkap (50% Lebar Layar)                    -->
        <!-- ========================================================================= -->
        <div class="w-full space-y-6">

            <!-- Selector Template Formulir -->
            <div class="bg-white p-4 sm:p-5 border border-slate-300 shadow-sm rounded-none space-y-3">
                <div class="flex items-center justify-between border-b border-slate-200 pb-2.5">
                    <div>
                        <h2 class="text-xs sm:text-sm font-bold text-slate-900 uppercase tracking-wide flex items-center gap-1.5">
                            <span>📑</span> Katalog <span x-text="forms.length"></span> Template Form PK - Pilih Jenis Formulir yang Ingin Dikustomisasi:
                        </h2>
                        <p class="text-[11px] text-slate-500 mt-0.5">
                            Pilih salah satu template di bawah untuk mengedit aspek kriteria, bobot nilai, atau teks parameternya.
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
                    <template x-for="f in forms" :key="f.kode">
                        <button type="button" 
                                @click="selectForm(f.kode)"
                                :class="selectedKode === f.kode ? 'bg-[#92302e] text-white border-[#92302e] shadow-xs' : 'bg-slate-50 text-slate-800 hover:bg-slate-100 border-slate-300'"
                                class="p-2.5 text-left border rounded-none transition-all cursor-pointer flex flex-col justify-between">
                            <div class="flex items-center justify-between">
                                <span class="font-mono font-black text-xs" x-text="f.kode"></span>
                                <span class="text-[9px] font-bold px-1 py-0.2 rounded-none"
                                      :class="selectedKode === f.kode ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700'"
                                      x-text="f.is_bilingual ? 'JP/ID' : 'ID'"></span>
                            </div>
                            <span class="text-[10.5px] font-bold mt-1 line-clamp-1" x-text="f.nama.replace('Formulir Penilaian ', '')"></span>
                            <span class="text-[9.5px] opacity-80 mt-0.5 block" x-text="f.level"></span>
                        </button>
                    </template>
                </div>
            </div>

            <!-- Form Customizer & Parameter Editor Card -->
            <div class="bg-white p-5 sm:p-6 border border-slate-300 shadow-sm rounded-none space-y-6">
                
                <form method="POST" action="{{ route('admin.simpanPengaturanForm') }}" class="space-y-6">
                    @csrf

                    <!-- Hidden Inputs for Form Customization -->
                    <input type="hidden" name="custom_kode_form" :value="selectedKode">
                    <input type="hidden" name="custom_form_structure" :value="serializedStructure">

                    <div class="border-b border-slate-200 pb-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 text-xs font-mono font-bold bg-[#92302e] text-white rounded-none" x-text="selectedKode"></span>
                                <h3 class="text-sm sm:text-base font-black text-slate-900 uppercase" x-text="'Kustomisasi Struktur ' + currentFormMeta.nama"></h3>
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Edit judul aspek, persentase bobot, dan tambah/hapus kriteria sub-aspek sesuai kebutuhan perusahaan.
                            </p>
                        </div>

                        <!-- Total Weight Validator -->
                        <div class="flex items-center gap-1.5 self-start sm:self-auto">
                            <span class="text-xs font-bold text-slate-600">Total Bobot:</span>
                            <span class="px-2.5 py-0.5 font-mono text-xs font-black rounded-none border"
                                  :class="totalWeight() === 100 ? 'bg-emerald-50 text-emerald-800 border-emerald-300' : 'bg-rose-50 text-rose-800 border-rose-300'"
                                  x-text="totalWeight() + '% ' + (totalWeight() === 100 ? '✓ Pas' : '⚠️ Harus 100%')">
                            </span>
                        </div>
                    </div>

                    <!-- ============================================================= -->
                    <!-- SECTION 1: ASPEK & BOBOT PENILAIAN (SEPERTI GAMBAR USER)      -->
                    <!-- ============================================================= -->
                    <div class="space-y-5">
                        
                        <!-- Loop Each Group (Hasil Kerja, Proses Kerja, Man Management, Sikap Kerja) -->
                        <template x-for="(group, gKey) in (activeStructure ? activeStructure.groups : {})" :key="gKey">
                            <div class="p-4 border border-slate-300 bg-slate-50/70 rounded-none space-y-3">
                                
                                <!-- Group Header: Nama & Bobot -->
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-200 pb-2.5">
                                    <div class="flex-1 space-y-0.5">
                                        <label class="block text-[10px] font-black uppercase text-slate-500 tracking-wider">
                                            Nama Kelompok Aspek:
                                        </label>
                                        <input type="text" 
                                               x-model="group.name" 
                                               class="w-full px-2.5 py-1 text-xs font-bold text-slate-900 uppercase border border-slate-300 focus:outline-none focus:border-[#92302e] rounded-none bg-white">
                                    </div>

                                    <div class="w-full sm:w-28 space-y-0.5 flex-shrink-0">
                                        <label class="block text-[10px] font-black uppercase text-slate-500 tracking-wider">
                                            Bobot (%):
                                        </label>
                                        <div class="relative">
                                            <input type="number" 
                                                   x-model.number="group.weight" 
                                                   min="0" 
                                                   max="100" 
                                                   class="w-full px-2.5 py-1 text-xs font-mono font-bold text-slate-900 border border-slate-300 focus:outline-none focus:border-[#92302e] rounded-none bg-white pr-6">
                                            <span class="absolute right-2 top-1 text-xs text-slate-400 font-bold">%</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Aspects List (Sub-Kriteria) -->
                                <div class="space-y-1.5">
                                    <div class="flex items-center justify-between">
                                        <span class="text-[11px] font-bold text-slate-700 uppercase">
                                            Daftar Sub-Aspek / Kriteria Penilaian:
                                        </span>
                                        <button type="button" 
                                                @click="addAspect(gKey)"
                                                class="text-[10.5px] font-bold text-[#92302e] hover:text-[#7e2524] bg-rose-50 hover:bg-rose-100 px-2 py-0.5 border border-rose-200 rounded-none transition flex items-center gap-1 cursor-pointer">
                                            <span>+ Tambah Kriteria</span>
                                        </button>
                                    </div>

                                    <div class="space-y-1.5">
                                        <template x-for="(aspName, aspKey) in group.aspects" :key="aspKey">
                                            <div class="flex items-center gap-2">
                                                <span class="w-5 text-[10.5px] font-mono text-slate-400 text-center font-bold">▶</span>
                                                <input type="text" 
                                                       x-model="group.aspects[aspKey]" 
                                                       placeholder="Nama kriteria..."
                                                       class="flex-1 px-2.5 py-1 text-xs font-semibold text-slate-800 border border-slate-300 focus:outline-none focus:border-[#92302e] rounded-none bg-white">
                                                <button type="button" 
                                                        @click="removeAspect(gKey, aspKey)"
                                                        title="Hapus kriteria ini"
                                                        class="px-2 py-1 text-xs font-bold text-slate-400 hover:text-rose-700 hover:bg-rose-50 border border-slate-300 rounded-none transition cursor-pointer">
                                                    ✕
                                                </button>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- Toggle Tambah / Hapus Kelompok MAN MANAGEMENT -->
                        <div class="p-3 bg-white border border-dashed border-slate-300 rounded-none flex items-center justify-between gap-3">
                            <div>
                                <span class="text-xs font-bold text-slate-900 block">Kelompok Aspek Man Management</span>
                                <span class="text-[11px] text-slate-500">Gunakan untuk posisi Leader, Foreman, Section Head ke atas yang membawahi tim/anak buah.</span>
                            </div>
                            <button type="button" 
                                    @click="toggleManManagement()"
                                    class="px-3 py-1.5 text-xs font-bold rounded-none border transition-all cursor-pointer"
                                    :class="activeStructure && activeStructure.groups['man_management'] ? 'bg-rose-50 text-rose-800 border-rose-300 hover:bg-rose-100' : 'bg-emerald-50 text-emerald-800 border-emerald-300 hover:bg-emerald-100'">
                                <span x-text="activeStructure && activeStructure.groups['man_management'] ? '✕ Nonaktifkan Man Management' : '+ Aktifkan Man Management'"></span>
                            </button>
                        </div>
                    </div>

                    <!-- ============================================================= -->
                    <!-- SECTION 2: PARAMETER GLOBAL FORMULIR (TAHUN, PERIODE, DLL)    -->
                    <!-- ============================================================= -->
                    <div class="pt-4 border-t border-slate-200 space-y-4">
                        <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
                            <span>⚙️</span> Parameter Header &amp; Periode Aktif Penilaian
                        </h4>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- 1. Tahun Penilaian -->
                            <div class="space-y-1">
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                    Tahun Penilaian (Aktif) <span class="text-rose-500">*</span>
                                </label>
                                <input type="number" 
                                       name="tahun_penilaian" 
                                       x-model="formSettings.tahun_penilaian" 
                                       min="2020" 
                                       max="2035" 
                                       required 
                                       class="w-full px-3 py-1.5 text-xs font-mono font-bold rounded-none border border-slate-300 focus:outline-none focus:border-[#92302e] bg-white">
                            </div>

                            <!-- 2. Periode Penilaian -->
                            <div class="space-y-1">
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                    Periode Evaluasi <span class="text-rose-500">*</span>
                                </label>
                                <input type="text" 
                                       name="periode_penilaian" 
                                       x-model="formSettings.periode_penilaian" 
                                       required 
                                       placeholder="Contoh: Jan - Des atau Semester I"
                                       class="w-full px-3 py-1.5 text-xs font-semibold rounded-none border border-slate-300 focus:outline-none focus:border-[#92302e] bg-white">
                            </div>

                            <!-- 3. Tanggal Formulir / Cetak -->
                            <div class="space-y-1">
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                    Tanggal Formulir / Cetak <span class="text-rose-500">*</span>
                                </label>
                                <input type="date" 
                                       name="tgl_mulai" 
                                       x-model="formSettings.tanggal_dokumen" 
                                       required 
                                       class="w-full px-3 py-1.5 text-xs font-semibold rounded-none border border-slate-300 focus:outline-none focus:border-[#92302e] bg-white">
                            </div>

                            <!-- 3. Judul Formulir -->
                            <div class="space-y-1">
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                    Judul Header Dokumen <span class="text-rose-500">*</span>
                                </label>
                                <input type="text" 
                                       name="judul_form_pk" 
                                       x-model="formSettings.judul_form_pk" 
                                       required 
                                       class="w-full px-3 py-1.5 text-xs font-bold uppercase rounded-none border border-slate-300 focus:outline-none focus:border-[#92302e] bg-white">
                            </div>

                            <!-- 4. Nama Perusahaan -->
                            <div class="space-y-1">
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                    Nama Perusahaan
                                </label>
                                <input type="text" 
                                       name="nama_perusahaan" 
                                       x-model="formSettings.nama_perusahaan" 
                                       class="w-full px-3 py-1.5 text-xs font-semibold rounded-none border border-slate-300 focus:outline-none focus:border-[#92302e] bg-white">
                            </div>

                            <!-- 5. Status Periode -->
                            <div class="space-y-1">
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                    Status Periode <span class="text-rose-500">*</span>
                                </label>
                                <select name="status_periode" 
                                        x-model="formSettings.status_periode" 
                                        class="w-full px-3 py-1.5 text-xs font-bold rounded-none border border-slate-300 focus:outline-none focus:border-[#92302e] bg-white">
                                    <option value="aktif">Aktif (Formulir Terbuka Input Nilai &amp; TTD)</option>
                                    <option value="nonaktif">Nonaktif / Draft (Form Dikunci Sementara)</option>
                                    <option value="selesai">Selesai / Terarsip (Periode Ditutup)</option>
                                </select>
                            </div>

                            <!-- 6. Label Kerahasiaan -->
                            <div class="space-y-1">
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                    Label Kerahasiaan Dokumen
                                </label>
                                <input type="text" 
                                       name="label_kerahasiaan" 
                                       x-model="formSettings.label_kerahasiaan" 
                                       class="w-full px-3 py-1.5 text-xs font-semibold rounded-none border border-slate-300 focus:outline-none focus:border-[#92302e] bg-white">
                            </div>
                        </div>

                        <!-- Petunjuk / Catatan Penilai Full Width -->
                        <div class="space-y-1">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                Catatan Petunjuk Penilai (Tampil di Popup Rubrik Skor)
                            </label>
                            <textarea name="catatan_petunjuk_penilai" 
                                      x-model="formSettings.catatan_petunjuk_penilai" 
                                      rows="2" 
                                      class="w-full px-3 py-1.5 text-xs rounded-none border border-slate-300 focus:outline-none focus:border-[#92302e] bg-white leading-relaxed"></textarea>
                        </div>
                    </div>

                    <!-- ============================================================= -->
                    <!-- SECTION 3: TEKS CATATAN & ATURAN FORMULIR                    -->
                    <!-- ============================================================= -->
                    <div class="pt-4 border-t border-slate-200 space-y-4">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
                                <span>📝</span> Teks Catatan Kaki &amp; Aturan Pembulatan Formulir
                            </h4>
                            <span class="text-[11px] text-slate-400">Langsung tampil di footer formulir</span>
                        </div>

                        <div class="space-y-3">
                            <div class="space-y-1">
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                    Catatan 1: Nilai Pecahan
                                </label>
                                <input type="text" 
                                       name="catatan_1" 
                                       x-model="formSettings.catatan_1" 
                                       placeholder="Jika nilai pecahan, ditentukan dua angka dibelakang koma."
                                       class="w-full px-3 py-1.5 text-xs rounded-none border border-slate-300 focus:outline-none focus:border-[#92302e] bg-white">
                            </div>

                            <div class="space-y-1">
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                    Catatan 2: Aturan Pembulatan
                                </label>
                                <input type="text" 
                                       name="catatan_2" 
                                       x-model="formSettings.catatan_2" 
                                       placeholder="Pembulatan : ≤ 0,5 pembulatan ke bawah ; > 0,5 pembulatan ke atas."
                                       class="w-full px-3 py-1.5 text-xs rounded-none border border-slate-300 focus:outline-none focus:border-[#92302e] bg-white">
                            </div>
                        </div>
                    </div>

                    <!-- ============================================================= -->
                    <!-- SECTION 4: TEKS EVALUASI POTENSI & CATATAN KARYAWAN          -->
                    <!-- ============================================================= -->
                    <div class="pt-4 border-t border-slate-200 space-y-4">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
                                <span>🎯</span> Teks Indikator Evaluasi Potensi, Kelebihan &amp; Kekurangan
                            </h4>
                            <span class="text-[11px] text-slate-400">Kolom kanan formulir</span>
                        </div>

                        <div class="space-y-3">
                            <div class="space-y-1">
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                    Judul Bagian Evaluasi Potensi
                                </label>
                                <input type="text" 
                                       name="judul_evaluasi_potensi" 
                                       x-model="formSettings.judul_evaluasi_potensi" 
                                       class="w-full px-3 py-1.5 text-xs font-bold uppercase rounded-none border border-slate-300 focus:outline-none focus:border-[#92302e] bg-white">
                            </div>

                            <div class="space-y-1">
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                    Petunjuk Pengisian Potensi Karyawan
                                </label>
                                <textarea name="petunjuk_evaluasi_potensi" 
                                          x-model="formSettings.petunjuk_evaluasi_potensi" 
                                          rows="3" 
                                          class="w-full px-3 py-1.5 text-xs rounded-none border border-slate-300 focus:outline-none focus:border-[#92302e] bg-white leading-relaxed"></textarea>
                            </div>

                            <div class="space-y-2 pt-1">
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                    4 Kalimat Pilihan Opsi Potensi Karyawan:
                                </label>

                                <div class="space-y-2">
                                    <div class="flex items-center gap-2">
                                        <span class="w-6 text-center font-bold text-xs text-slate-500 font-mono">1.</span>
                                        <input type="text" 
                                               name="opsi_potensi_1" 
                                               x-model="formSettings.opsi_potensi_1" 
                                               placeholder="Memenuhi standard untuk dipromosikan pada level diatasnya, saran pada posisi :"
                                               class="flex-1 px-3 py-1.5 text-xs rounded-none border border-slate-300 focus:outline-none focus:border-[#92302e] bg-white">
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <span class="w-6 text-center font-bold text-xs text-slate-500 font-mono">2.</span>
                                        <input type="text" 
                                               name="opsi_potensi_2" 
                                               x-model="formSettings.opsi_potensi_2" 
                                               placeholder="Sesuai dengan posisi saat ini dan masih bisa dikembangkan dengan pengalaman dan pelatihan sesuai matriks."
                                               class="flex-1 px-3 py-1.5 text-xs rounded-none border border-slate-300 focus:outline-none focus:border-[#92302e] bg-white">
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <span class="w-6 text-center font-bold text-xs text-slate-500 font-mono">3.</span>
                                        <input type="text" 
                                               name="opsi_potensi_3" 
                                               x-model="formSettings.opsi_potensi_3" 
                                               placeholder="Sesuai untuk posisi saat ini dan tidak bisa dikembangkan lagi, alasan :"
                                               class="flex-1 px-3 py-1.5 text-xs rounded-none border border-slate-300 focus:outline-none focus:border-[#92302e] bg-white">
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <span class="w-6 text-center font-bold text-xs text-slate-500 font-mono">4.</span>
                                        <input type="text" 
                                               name="opsi_potensi_4" 
                                               x-model="formSettings.opsi_potensi_4" 
                                               placeholder="Yang bersangkutan kurang sesuai dengan posisi saat ini, dengan alasan :"
                                               class="flex-1 px-3 py-1.5 text-xs rounded-none border border-slate-300 focus:outline-none focus:border-[#92302e] bg-white">
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                                <div class="space-y-1">
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                        Judul Kolom Kelebihan Karyawan
                                    </label>
                                    <input type="text" 
                                           name="judul_kelebihan" 
                                           x-model="formSettings.judul_kelebihan" 
                                           class="w-full px-3 py-1.5 text-xs font-bold uppercase rounded-none border border-slate-300 focus:outline-none focus:border-[#92302e] bg-white">
                                </div>

                                <div class="space-y-1">
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                        Judul Kolom Kekurangan Karyawan
                                    </label>
                                    <input type="text" 
                                           name="judul_kekurangan" 
                                           x-model="formSettings.judul_kekurangan" 
                                           class="w-full px-3 py-1.5 text-xs font-bold uppercase rounded-none border border-slate-300 focus:outline-none focus:border-[#92302e] bg-white">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ============================================================= -->
                    <!-- SECTION 5: STRUKTUR TANDA TANGAN & ALUR PENILAI (TTD DIGITAL) -->
                    <!-- ============================================================= -->
                    <div class="pt-4 border-t border-slate-200 space-y-4">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
                                <span>✍️</span> Struktur Tanda Tangan &amp; Alur Penilai (<span class="text-[#92302e] font-mono" x-text="selectedKode"></span>)
                            </h4>
                            <span class="text-[11px] text-slate-500 font-semibold" x-text="currentFormMeta.level"></span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                            <!-- Kolom TTD 1 -->
                            <div class="p-2.5 bg-slate-50 border border-slate-300 space-y-1">
                                <span class="text-[9.5px] font-black uppercase text-slate-500 block">Kolom TTD 1 (Penilai 1)</span>
                                <div class="font-bold text-xs text-slate-900" x-text="currentFormMeta.ttd_config ? currentFormMeta.ttd_config.col1.header : 'Foreman'"></div>
                                <div class="text-[10px] text-slate-500" x-text="currentFormMeta.penilai_1"></div>
                            </div>

                            <!-- Kolom TTD 2 -->
                            <div class="p-2.5 bg-slate-50 border border-slate-300 space-y-1"
                                 :class="currentFormMeta.ttd_config && currentFormMeta.ttd_config.col2.is_blocked ? 'opacity-60 bg-slate-100' : ''">
                                <span class="text-[9.5px] font-black uppercase text-slate-500 block">Kolom TTD 2 (Penilai 2)</span>
                                <div class="font-bold text-xs text-slate-900" x-text="currentFormMeta.ttd_config ? currentFormMeta.ttd_config.col2.header : 'Section Head'"></div>
                                <div class="text-[10px] text-slate-500" x-text="currentFormMeta.penilai_2"></div>
                            </div>

                            <!-- Kolom TTD 3 -->
                            <div class="p-2.5 bg-slate-50 border border-slate-300 space-y-1"
                                 :class="currentFormMeta.ttd_config && currentFormMeta.ttd_config.col3.is_blocked ? 'opacity-60 bg-slate-100' : ''">
                                <span class="text-[9.5px] font-black uppercase text-slate-500 block">Kolom TTD 3 (Pengesahan)</span>
                                <div class="font-bold text-xs text-slate-900" 
                                     x-text="currentFormMeta.ttd_config && currentFormMeta.ttd_config.col3.is_blocked ? '- (Tidak Ada)' : (currentFormMeta.ttd_config ? currentFormMeta.ttd_config.col3.header : 'Dept Head')"></div>
                                <div class="text-[10px] text-slate-500" x-text="currentFormMeta.pengesahan"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Submit & Reset Buttons -->
                    <div class="pt-4 border-t border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <button type="submit" 
                                name="reset_custom_structure" 
                                value="1"
                                onclick="return confirm('Apakah Anda yakin ingin mengembalikan struktur formulir ' + selectedKode + ' ke default bawaan Musashi?')"
                                class="text-xs font-bold text-slate-600 hover:text-rose-700 py-2 px-3 border border-slate-300 hover:border-rose-300 rounded-none transition flex items-center gap-1 cursor-pointer">
                            <span>🔄</span> Reset Form ke Default
                        </button>

                        <button type="submit" 
                                class="px-6 py-2.5 font-bold text-xs bg-[#92302e] hover:bg-[#7e2524] text-white shadow-xs hover:shadow-sm transition-all flex items-center justify-center gap-2 rounded-none cursor-pointer">
                            <span>💾</span> Simpan &amp; Terapkan Kustomisasi Form
                        </button>
                    </div>
                </form>
            </div>

        </div>

        <!-- ========================================================================= -->
        <!-- RIGHT COLUMN: MURNI HANYA TAMPILAN PDF / WUJUD ASLI LEMBAR FORMULIR (50%) -->
        <!-- ========================================================================= -->
        <div class="w-full sticky top-4 space-y-3">

            <div class="bg-white p-3.5 sm:p-4 border border-slate-300 shadow-sm rounded-none space-y-3">
                
                <!-- Live Preview PDF Header Info -->
                <div class="border-b border-slate-200 pb-2 flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 text-[10px] font-black bg-[#92302e] text-white uppercase rounded-none tracking-wider">
                                LIVE PREVIEW PDF RESMI
                            </span>
                            <span class="text-xs font-mono font-bold text-slate-800" x-text="selectedKode"></span>
                        </div>
                        <h3 class="text-xs sm:text-sm font-black text-slate-900 mt-1" x-text="currentFormMeta.nama"></h3>
                    </div>

                    <div class="flex items-center gap-2">
                        <!-- Toggle Simulasi TTD Digital -->
                        <label class="flex items-center gap-1.5 text-[10.5px] font-bold text-slate-700 cursor-pointer bg-slate-100 hover:bg-slate-200 border border-slate-300 px-2 py-1 rounded-none select-none transition">
                            <input type="checkbox" x-model="simulasiTtd" class="w-3.5 h-3.5 text-[#92302e] rounded-none focus:ring-0">
                            <span>Simulasi TTD Digital</span>
                        </label>

                        <a :href="'/dokumen/' + currentFormMeta.sample_doc_id + '/pdf'" 
                           target="_blank" 
                           class="text-xs font-bold text-[#92302e] hover:text-[#7e2524] bg-rose-50 hover:bg-rose-100 border border-rose-200 px-2.5 py-1 rounded-none transition flex items-center gap-1 cursor-pointer"
                           title="Unduh Contoh Dokumen PDF">
                            <span>📄 Unduh PDF</span> &rarr;
                        </a>
                    </div>
                </div>

                <!-- PURE OFFICIAL PDF SHEET VIEW (CONTAINER) -->
                <div class="p-2 sm:p-2.5 bg-slate-100 border border-slate-300 rounded-none overflow-x-auto max-h-[82vh] overflow-y-auto">
                    
                    <!-- Top Form Code Tag -->
                    <div class="text-[9px] font-mono font-bold text-slate-800 mb-1" x-text="'Code PDV Mapin - ' + selectedKode">
                        Code PDV Mapin - 07-001
                    </div>

                    <!-- Outer White Paper Sheet -->
                    <div class="bg-white p-2 border-2 border-slate-900 shadow-sm text-slate-900 min-w-[540px] text-[9.5px]">
                        
                        <!-- 1. HEADER TABLE (5 ROWS, 8 COLUMNS) - DINAMIS MENGIKUTI FORM & TTD (07-001 s/d 07-006-DIV) -->
                        <table class="w-full border-collapse border-2 border-slate-900 text-[9.5px]">
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
                                <td rowspan="5" class="border-r-2 border-b-2 border-slate-900 p-2 align-top bg-white">
                                    <div class="flex flex-col justify-between h-full min-h-[110px]">
                                        <div>
                                            <div class="mb-1.5">
                                                <img src="{{ asset('logomusashi.png') }}" alt="MUSASHI" class="h-6 max-w-full object-contain">
                                            </div>
                                            <div class="border border-slate-900 text-center py-0.5 font-bold tracking-wider text-slate-900 bg-white mb-1.5 text-[8.5px]" x-text="formSettings.label_kerahasiaan">
                                                {{ $formSettings['label_kerahasiaan'] }}
                                            </div>
                                        </div>
                                        <div class="space-y-0.5 text-[8px] text-slate-700 border-t border-slate-200 pt-1">
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
                                <td class="px-2 py-1 font-semibold text-slate-700 bg-white border-r border-b border-slate-900 align-middle text-[8.5px]">
                                    <span x-text="currentFormMeta.is_bilingual ? 'NAMA / 名前 :' : 'NAMA LENGKAP :'"></span>
                                </td>
                                <!-- Col 3: NAMA SAMPLE KARYAWAN -->
                                <td class="px-2 py-1 font-bold text-slate-900 uppercase bg-[#e2efda] border-r-2 border-b border-slate-900 align-middle text-[9px]"
                                    x-text="currentFormMeta.sample_karyawan ? currentFormMeta.sample_karyawan.nama : 'NUR EKA PURNAMA'">
                                    NUR EKA PURNAMA
                                </td>

                                <!-- Col 4 & 5: PENILAIAN KARYA Title -->
                                <!-- Bilingual: Rowspan 2 (no KJ banner) -->
                                <template x-if="currentFormMeta.is_bilingual">
                                    <td colspan="2" rowspan="2" class="py-1 px-2 text-center bg-white border-r-2 border-b border-slate-900 align-middle">
                                        <h4 class="text-xs font-black tracking-wide text-slate-900 uppercase leading-tight" 
                                            x-text="formSettings.judul_form_pk + ' / 仕事の評価'">
                                            PENILAIAN KARYA / 仕事の評価
                                        </h4>
                                    </td>
                                </template>
                                <!-- Non-Bilingual (Gol 1-3): Rowspan 1 -->
                                <template x-if="!currentFormMeta.is_bilingual">
                                    <td colspan="2" class="py-1 px-2 text-center bg-white border-r-2 border-b border-slate-900 align-middle">
                                        <h4 class="text-xs font-black tracking-wide text-slate-900 uppercase leading-tight" 
                                            x-text="formSettings.judul_form_pk">
                                            PENILAIAN KARYA
                                        </h4>
                                    </td>
                                </template>

                                <!-- Col 6, 7, 8: Signatures Header (Always 3 Distinct Columns) -->
                                <!-- Col 6 Header -->
                                <td class="bg-[#d9d9d9] border-b border-r border-slate-900 font-bold text-[8px] text-center p-1 text-slate-900 align-middle leading-tight">
                                    <span x-text="currentFormMeta.ttd_config ? currentFormMeta.ttd_config.col1.header : 'Foreman'"></span>
                                    <span x-show="currentFormMeta.ttd_config && currentFormMeta.ttd_config.col1.sub" 
                                          class="block text-[7px] text-slate-700 font-medium" 
                                          x-text="currentFormMeta.ttd_config ? currentFormMeta.ttd_config.col1.sub : ''"></span>
                                </td>

                                <!-- Col 7 Header -->
                                <template x-if="currentFormMeta.ttd_config && currentFormMeta.ttd_config.col2.is_blocked">
                                    <td class="bg-[#bfbfbf] border-b border-r border-slate-900"></td>
                                </template>
                                <template x-if="!currentFormMeta.ttd_config || !currentFormMeta.ttd_config.col2.is_blocked">
                                    <td class="bg-[#d9d9d9] border-b border-r border-slate-900 font-bold text-[8px] text-center p-1 text-slate-900 align-middle leading-tight">
                                        <span x-text="currentFormMeta.ttd_config ? currentFormMeta.ttd_config.col2.header : 'Section Head'"></span>
                                        <span x-show="currentFormMeta.ttd_config && currentFormMeta.ttd_config.col2.sub" 
                                              class="block text-[7px] text-slate-700 font-medium" 
                                              x-text="currentFormMeta.ttd_config ? currentFormMeta.ttd_config.col2.sub : ''"></span>
                                    </td>
                                </template>

                                <!-- Col 8 Header -->
                                <template x-if="currentFormMeta.ttd_config && currentFormMeta.ttd_config.col3.is_blocked">
                                    <td class="bg-[#bfbfbf] border-b border-slate-900"></td>
                                </template>
                                <template x-if="!currentFormMeta.ttd_config || !currentFormMeta.ttd_config.col3.is_blocked">
                                    <td class="bg-[#d9d9d9] border-b border-slate-900 font-bold text-[8px] text-center p-1 text-slate-900 align-middle leading-tight">
                                        <span x-text="currentFormMeta.ttd_config ? currentFormMeta.ttd_config.col3.header : 'Department Head'"></span>
                                        <span x-show="currentFormMeta.ttd_config && currentFormMeta.ttd_config.col3.sub" 
                                              class="block text-[7px] text-slate-700 font-medium" 
                                              x-text="currentFormMeta.ttd_config ? currentFormMeta.ttd_config.col3.sub : ''"></span>
                                    </td>
                                </template>
                            </tr>

                            <!-- ROW 2 -->
                            <tr>
                                <!-- Col 2: NRP -->
                                <td class="px-2 py-1 font-semibold text-slate-700 bg-white border-r border-b border-slate-900 align-middle text-[8.5px]">
                                    NRP :
                                </td>
                                <td class="px-2 py-1 font-mono font-bold text-slate-900 bg-[#e2efda] border-r-2 border-b border-slate-900 align-middle text-[9px]"
                                    x-text="currentFormMeta.sample_karyawan ? currentFormMeta.sample_karyawan.nrp : '39'">
                                    39
                                </td>

                                <!-- Col 4 & 5: Banner Jenjang / KJ (ONLY FOR NON-BILINGUAL GOL 1-3) -->
                                <template x-if="!currentFormMeta.is_bilingual">
                                    <td colspan="2" class="bg-[#00e000] text-center font-black text-slate-950 py-1 border-r-2 border-b border-slate-900 align-middle text-xs tracking-wider"
                                        x-text="currentFormMeta.sample_karyawan ? currentFormMeta.sample_karyawan.banner : ('KJ : ' + (currentFormMeta.level || '1 - 3'))">
                                        KJ : 1 - 3
                                    </td>
                                </template>

                                <!-- Col 6, 7, 8: Signature Boxes (Always 3 Distinct Columns) -->
                                <!-- Col 6 TTD Box -->
                                <td rowspan="2" class="border-b border-r border-slate-900 text-center align-middle p-1 bg-white text-[7.5px]">
                                    <template x-if="simulasiTtd">
                                        <div class="space-y-0.5">
                                            <span class="inline-block px-1.5 py-0.2 bg-emerald-100 text-emerald-800 border border-emerald-400 font-black text-[7px] tracking-wide">✓ TTD DIGITAL</span>
                                            <span class="block text-[6.5px] text-slate-600 font-semibold truncate" x-text="currentFormMeta.ttd_config ? currentFormMeta.ttd_config.col1.name : ''"></span>
                                        </div>
                                    </template>
                                    <template x-if="!simulasiTtd">
                                        <span class="text-slate-400 italic">Belum TTD</span>
                                    </template>
                                </td>

                                <!-- Col 7 TTD Box -->
                                <template x-if="currentFormMeta.ttd_config && currentFormMeta.ttd_config.col2.is_blocked">
                                    <td rowspan="2" class="bg-[#bfbfbf] border-b border-r border-slate-900"></td>
                                </template>
                                <template x-if="!currentFormMeta.ttd_config || !currentFormMeta.ttd_config.col2.is_blocked">
                                    <td rowspan="2" class="border-b border-r border-slate-900 text-center align-middle p-1 bg-white text-[7.5px]">
                                        <template x-if="simulasiTtd">
                                            <div class="space-y-0.5">
                                                <span class="inline-block px-1.5 py-0.2 bg-emerald-100 text-emerald-800 border border-emerald-400 font-black text-[7px] tracking-wide">✓ TTD DIGITAL</span>
                                                <span class="block text-[6.5px] text-slate-600 font-semibold truncate" x-text="currentFormMeta.ttd_config ? currentFormMeta.ttd_config.col2.name : ''"></span>
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
                                    <td rowspan="2" class="border-b border-slate-900 text-center align-middle p-1 bg-white text-[7.5px]">
                                        <template x-if="simulasiTtd">
                                            <div class="space-y-0.5">
                                                <span class="inline-block px-1.5 py-0.2 bg-emerald-100 text-emerald-800 border border-emerald-400 font-black text-[7px] tracking-wide">✓ TTD DIGITAL</span>
                                                <span class="block text-[6.5px] text-slate-600 font-semibold truncate" x-text="currentFormMeta.ttd_config ? currentFormMeta.ttd_config.col3.name : ''"></span>
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
                                <td class="px-2 py-1 font-semibold text-slate-700 bg-white border-r border-b border-slate-900 align-middle text-[8.5px]">
                                    <span x-text="currentFormMeta.is_bilingual ? 'GOLONGAN / 役 :' : 'GOLONGAN :'"></span>
                                </td>
                                <td class="px-2 py-1 font-bold text-slate-900 bg-[#e2efda] border-r-2 border-b border-slate-900 align-middle text-[9px]"
                                    x-text="currentFormMeta.sample_karyawan ? currentFormMeta.sample_karyawan.golongan : 'IVA'">
                                    IVA
                                </td>

                                <!-- Col 4 & 5: Subtitle boxes Seksi & Departemen -->
                                <template x-if="selectedKode === '07-006-DIV' || selectedKode === '07-006-DIRECT'">
                                    <td colspan="2" class="text-center font-bold text-[8px] uppercase text-slate-600 border-r-2 border-b border-slate-900 p-0.5">
                                        DIVISION
                                    </td>
                                </template>
                                <template x-if="selectedKode !== '07-006-DIV' && selectedKode !== '07-006-DIRECT'">
                                    <td class="text-center font-bold text-[8px] uppercase text-slate-600 border-r border-b border-slate-900 p-0.5">
                                        <span x-text="currentFormMeta.is_bilingual ? ((selectedKode === '07-006-DH' || selectedKode === '07-006-SECRETARY') ? 'DEPARTEMEN / 部門' : 'SECTION / セクション') : 'Seksi'"></span>
                                    </td>
                                </template>
                                <template x-if="selectedKode !== '07-006-DIV' && selectedKode !== '07-006-DIRECT'">
                                    <td class="text-center font-bold text-[8px] uppercase text-slate-600 border-r-2 border-b border-slate-900 p-0.5">
                                        <span x-text="currentFormMeta.is_bilingual ? ((selectedKode === '07-006-DH' || selectedKode === '07-006-SECRETARY') ? 'DIVISION' : 'DEPARTEMEN / 部門') : 'Departemen'"></span>
                                    </td>
                                </template>
                            </tr>

                            <!-- ROW 4 -->
                            <tr>
                                <!-- Col 2: JABATAN -->
                                <td class="px-2 py-1 font-semibold text-slate-700 bg-white border-r border-b border-slate-900 align-middle text-[8.5px]">
                                    <span x-text="currentFormMeta.is_bilingual ? 'JABATAN / 職位 :' : 'JABATAN :'"></span>
                                </td>
                                <td class="px-2 py-1 font-bold text-slate-900 uppercase bg-[#e2efda] border-r-2 border-b border-slate-900 align-middle text-[8.5px]"
                                    x-text="currentFormMeta.sample_karyawan ? currentFormMeta.sample_karyawan.jabatan : 'OPERATOR MACHINING'">
                                    OPERATOR MACHINING
                                </td>

                                <!-- Col 4 & 5: Seksi & Departemen Values (Rowspan 2) -->
                                <template x-if="selectedKode === '07-006-DIV' || selectedKode === '07-006-DIRECT'">
                                    <td colspan="2" rowspan="2" class="border-r-2 border-b-2 border-slate-900 text-center font-bold text-[8.5px] uppercase align-middle bg-white p-1"
                                        x-text="currentFormMeta.sample_karyawan ? currentFormMeta.sample_karyawan.seksi : 'ENGINEERING'">
                                        ENGINEERING
                                    </td>
                                </template>
                                <template x-if="selectedKode !== '07-006-DIV' && selectedKode !== '07-006-DIRECT'">
                                    <td rowspan="2" class="border-r border-b-2 border-slate-900 text-center font-bold text-[8.5px] uppercase align-middle bg-white p-1"
                                        x-text="currentFormMeta.sample_karyawan ? currentFormMeta.sample_karyawan.seksi : 'FACTORY 1 SEC.'">
                                        FACTORY 1 SEC.
                                    </td>
                                </template>
                                <template x-if="selectedKode !== '07-006-DIV' && selectedKode !== '07-006-DIRECT'">
                                    <td rowspan="2" class="border-r-2 border-b-2 border-slate-900 text-center font-bold text-[8.5px] uppercase align-middle bg-white p-1"
                                        x-text="currentFormMeta.sample_karyawan ? currentFormMeta.sample_karyawan.departemen : 'PROD 2'">
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
                                        <div class="font-bold text-[7.5px] text-slate-900 truncate" x-text="currentFormMeta.ttd_config ? currentFormMeta.ttd_config.col1.name : '-'"></div>
                                        <div class="text-[6.5px] text-slate-500 font-medium" x-text="currentFormMeta.ttd_config ? currentFormMeta.ttd_config.col1.role : ''"></div>
                                    </td>
                                </template>

                                <!-- Col 7 Signer -->
                                <template x-if="currentFormMeta.ttd_config && currentFormMeta.ttd_config.col2.is_blocked">
                                    <td rowspan="2" class="bg-[#bfbfbf] border-r border-b-2 border-slate-900"></td>
                                </template>
                                <template x-if="!currentFormMeta.ttd_config || !currentFormMeta.ttd_config.col2.is_blocked">
                                    <td rowspan="2" class="border-r border-b-2 border-slate-900 text-center align-middle p-1 bg-white">
                                        <div class="font-bold text-[7.5px] text-slate-900 truncate" x-text="currentFormMeta.ttd_config ? currentFormMeta.ttd_config.col2.name : '-'"></div>
                                        <div class="text-[6.5px] text-slate-500 font-medium" x-text="currentFormMeta.ttd_config ? currentFormMeta.ttd_config.col2.role : ''"></div>
                                    </td>
                                </template>

                                <!-- Col 8 Signer -->
                                <template x-if="currentFormMeta.ttd_config && currentFormMeta.ttd_config.col3.is_blocked">
                                    <td rowspan="2" class="bg-[#bfbfbf] border-b-2 border-slate-900"></td>
                                </template>
                                <template x-if="!currentFormMeta.ttd_config || !currentFormMeta.ttd_config.col3.is_blocked">
                                    <td rowspan="2" class="border-b-2 border-slate-900 text-center align-middle p-1 bg-white">
                                        <div class="font-bold text-[7.5px] text-slate-900 truncate" x-text="currentFormMeta.ttd_config ? currentFormMeta.ttd_config.col3.name : '-'"></div>
                                        <div class="text-[6.5px] text-slate-500 font-medium" x-text="currentFormMeta.ttd_config ? currentFormMeta.ttd_config.col3.role : ''"></div>
                                    </td>
                                </template>
                            </tr>

                            <!-- ROW 5 -->
                            <tr>
                                <!-- Col 2: TANGGAL MASUK -->
                                <td class="px-2 py-1 font-semibold text-slate-700 bg-white border-r border-b-2 border-slate-900 align-middle text-[8.5px]">
                                    <span x-text="currentFormMeta.is_bilingual ? 'TANGGAL MASUK / 入社 :' : 'TANGGAL MASUK :'"></span>
                                </td>
                                <td class="px-2 py-1 font-mono font-bold text-slate-900 bg-[#e2efda] border-r-2 border-b-2 border-slate-900 align-middle text-[9px]"
                                    x-text="currentFormMeta.sample_karyawan ? currentFormMeta.sample_karyawan.tgl_masuk : '2020-01-12'">
                                    2020-01-12
                                </td>
                            </tr>
                        </table>

                        <!-- 2. MAIN BODY (SPLIT 2 COLUMNS: ASPEK PENILAIAN KIRI, INDIKATOR POTENSI KANAN) -->
                        <div class="grid grid-cols-12 border-2 border-t-0 border-slate-900 bg-white">
                            
                            <!-- ========================================================= -->
                            <!-- BODY LEFT: ASPEK PENILAIAN + TANGGAL + CATATAN + TOTAL    -->
                            <!-- ========================================================= -->
                            <div class="col-span-7 border-r-2 border-slate-900 flex flex-col justify-between">
                                
                                <!-- Tabel Aspek Penilaian -->
                                <div>
                                    <table class="w-full border-collapse text-[9px]">
                                        <thead>
                                            <tr class="bg-[#d1d5db] font-bold text-center border-b border-slate-900">
                                                <th colspan="2" rowspan="2" class="w-[62%] p-1 border-r border-slate-900 uppercase text-[8.5px]">
                                                    <span x-text="currentFormMeta.is_bilingual ? 'ASPEK PENILAIAN / 評価項目' : 'ASPEK PENILAIAN'">ASPEK PENILAIAN</span>
                                                </th>
                                                <th colspan="3" class="w-[38%] p-0.5 border-b border-slate-900 uppercase text-[8px]">
                                                    <span x-text="currentFormMeta.is_bilingual ? 'PENILAIAN / 評価' : 'PENILAIAN'">PENILAIAN</span>
                                                </th>
                                            </tr>
                                            <tr class="bg-[#d9d9d9] font-bold text-center border-b-2 border-slate-900 text-[7.5px] leading-tight">
                                                <!-- Col 1 Header -->
                                                <th class="w-[12%] p-0.5 border-r border-slate-900" 
                                                    :class="{
                                                        'bg-[#94a3b8]': currentFormMeta.ttd_config && currentFormMeta.ttd_config.col1_type === 'blocked',
                                                        'bg-[#e2efda] text-emerald-950 font-bold': currentFormMeta.ttd_config && currentFormMeta.ttd_config.col1_type === 'button',
                                                        'bg-[#d9d9d9]': !currentFormMeta.ttd_config || (currentFormMeta.ttd_config.col1_type !== 'blocked' && currentFormMeta.ttd_config.col1_type !== 'button')
                                                    }"
                                                    x-text="currentFormMeta.ttd_config ? currentFormMeta.ttd_config.penilai_1_col : 'Foreman (jika ada)'">
                                                </th>

                                                <!-- Col 2 Header -->
                                                <th class="w-[13%] p-0.5 border-r border-slate-900"
                                                    :class="{
                                                        'bg-[#94a3b8]': currentFormMeta.ttd_config && currentFormMeta.ttd_config.col2_type === 'blocked',
                                                        'bg-[#e2efda] text-emerald-950 font-bold': currentFormMeta.ttd_config && currentFormMeta.ttd_config.col2_type === 'button',
                                                        'bg-[#d9d9d9]': !currentFormMeta.ttd_config || (currentFormMeta.ttd_config.col2_type !== 'blocked' && currentFormMeta.ttd_config.col2_type !== 'button')
                                                    }"
                                                    x-text="currentFormMeta.ttd_config ? currentFormMeta.ttd_config.penilai_2_col : 'Section Head / Deputy Section Head'">
                                                </th>

                                                <!-- Col 3 Header -->
                                                <th class="w-[13%] p-0.5" 
                                                    :class="{
                                                        'bg-[#94a3b8]': currentFormMeta.ttd_config && currentFormMeta.ttd_config.col3_type === 'blocked',
                                                        'bg-[#e2efda] text-emerald-950 font-bold': currentFormMeta.ttd_config && currentFormMeta.ttd_config.col3_type === 'button',
                                                        'bg-[#d9d9d9]': !currentFormMeta.ttd_config || (currentFormMeta.ttd_config.col3_type !== 'blocked' && currentFormMeta.ttd_config.col3_type !== 'button')
                                                    }"
                                                    x-text="currentFormMeta.ttd_config ? currentFormMeta.ttd_config.penilai_3_col : 'Dept. Head (Final)'">
                                                </th>
                                            </tr>
                                        </thead>

                                        <template x-for="(group, gKey) in (activeStructure ? activeStructure.groups : {})" :key="gKey">
                                            <tbody class="border-b-2 border-slate-900">
                                                <!-- Loop aspect criteria in this group (Using Object.entries so index is guaranteed 0, 1, 2...) -->
                                                <template x-for="(entry, index) in Object.entries(group.aspects || {})" :key="entry[0]">
                                                    <tr class="border-b border-slate-300">
                                                        <!-- Group Title with rowspan (ONLY ON FIRST ROW) -->
                                                        <template x-if="index === 0">
                                                            <td :rowspan="Object.keys(group.aspects).length" 
                                                                class="w-[26%] p-1 font-black text-center align-middle border-r border-slate-900 bg-white uppercase text-[8.5px]"
                                                                x-text="group.name">
                                                            </td>
                                                        </template>

                                                        <!-- Criterion Name -->
                                                        <td class="w-[36%] px-1.5 py-0.5 font-semibold border-r border-slate-900 bg-white text-[8px]"
                                                            x-text="entry[1]">
                                                        </td>

                                                        <!-- 3 Score Columns (Dinamis: Button / Dash / Blocked Dark Gray) -->
                                                        <!-- Col 1 -->
                                                        <template x-if="currentFormMeta.ttd_config && currentFormMeta.ttd_config.col1_type === 'blocked'">
                                                            <td class="w-[12%] p-0.5 border-r border-slate-900 bg-[#94a3b8]"></td>
                                                        </template>
                                                        <template x-if="currentFormMeta.ttd_config && currentFormMeta.ttd_config.col1_type === 'button'">
                                                            <td class="w-[12%] p-0.5 text-center border-r border-slate-900 bg-white">
                                                                <div class="inline-flex items-center justify-center gap-0.5 px-1 py-0.5 border border-emerald-500 bg-white text-emerald-700 text-[6.5px] font-bold shadow-2xs leading-none">
                                                                    <span>Pilih Kriteria</span>
                                                                    <span class="text-[5.5px]">▼</span>
                                                                </div>
                                                            </td>
                                                        </template>
                                                        <template x-if="!currentFormMeta.ttd_config || (currentFormMeta.ttd_config.col1_type !== 'blocked' && currentFormMeta.ttd_config.col1_type !== 'button')">
                                                            <td class="w-[12%] p-0.5 text-center font-mono font-bold border-r border-slate-900 bg-white text-slate-400 text-[8px]">-</td>
                                                        </template>

                                                        <!-- Col 2 -->
                                                        <template x-if="currentFormMeta.ttd_config && currentFormMeta.ttd_config.col2_type === 'blocked'">
                                                            <td class="w-[13%] p-0.5 border-r border-slate-900 bg-[#94a3b8]"></td>
                                                        </template>
                                                        <template x-if="currentFormMeta.ttd_config && currentFormMeta.ttd_config.col2_type === 'button'">
                                                            <td class="w-[13%] p-0.5 text-center border-r border-slate-900 bg-white">
                                                                <div class="inline-flex items-center justify-center gap-0.5 px-1 py-0.5 border border-emerald-500 bg-white text-emerald-700 text-[6.5px] font-bold shadow-2xs leading-none">
                                                                    <span>Pilih Kriteria</span>
                                                                    <span class="text-[5.5px]">▼</span>
                                                                </div>
                                                            </td>
                                                        </template>
                                                        <template x-if="!currentFormMeta.ttd_config || (currentFormMeta.ttd_config.col2_type !== 'blocked' && currentFormMeta.ttd_config.col2_type !== 'button')">
                                                            <td class="w-[13%] p-0.5 text-center font-mono font-bold border-r border-slate-900 bg-white text-slate-400 text-[8px]">-</td>
                                                        </template>

                                                        <!-- Col 3 -->
                                                        <template x-if="currentFormMeta.ttd_config && currentFormMeta.ttd_config.col3_type === 'blocked'">
                                                            <td class="w-[13%] p-0.5 bg-[#94a3b8]"></td>
                                                        </template>
                                                        <template x-if="currentFormMeta.ttd_config && currentFormMeta.ttd_config.col3_type === 'button'">
                                                            <td class="w-[13%] p-0.5 text-center bg-white">
                                                                <div class="inline-flex items-center justify-center gap-0.5 px-1 py-0.5 border border-emerald-500 bg-white text-emerald-700 text-[6.5px] font-bold shadow-2xs leading-none">
                                                                    <span>Pilih Kriteria</span>
                                                                    <span class="text-[5.5px]">▼</span>
                                                                </div>
                                                            </td>
                                                        </template>
                                                        <template x-if="!currentFormMeta.ttd_config || (currentFormMeta.ttd_config.col3_type !== 'blocked' && currentFormMeta.ttd_config.col3_type !== 'button')">
                                                            <td class="w-[13%] p-0.5 text-center font-mono font-bold bg-white text-slate-400 text-[8px]">-</td>
                                                        </template>
                                                    </tr>
                                                </template>

                                                <!-- TOTAL ROW FOR GROUP -->
                                                <tr class="border-b border-slate-900 bg-slate-50 font-bold text-[8px]">
                                                    <td colspan="2" class="px-1.5 py-0.5 text-right border-r border-slate-900 uppercase tracking-wide">
                                                        <span x-text="currentFormMeta.is_bilingual ? 'TOTAL 合計' : 'TOTAL'">TOTAL</span>
                                                    </td>
                                                    <template x-if="currentFormMeta.ttd_config && currentFormMeta.ttd_config.col1_type === 'blocked'">
                                                        <td class="p-0.5 border-r border-slate-900 bg-[#94a3b8]"></td>
                                                    </template>
                                                    <template x-if="!currentFormMeta.ttd_config || currentFormMeta.ttd_config.col1_type !== 'blocked'">
                                                        <td class="p-0.5 text-center font-mono border-r border-slate-900">0</td>
                                                    </template>

                                                    <template x-if="currentFormMeta.ttd_config && currentFormMeta.ttd_config.col2_type === 'blocked'">
                                                        <td class="p-0.5 border-r border-slate-900 bg-[#94a3b8]"></td>
                                                    </template>
                                                    <template x-if="!currentFormMeta.ttd_config || currentFormMeta.ttd_config.col2_type !== 'blocked'">
                                                        <td class="p-0.5 text-center font-mono border-r border-slate-900">0</td>
                                                    </template>

                                                    <template x-if="currentFormMeta.ttd_config && currentFormMeta.ttd_config.col3_type === 'blocked'">
                                                        <td class="p-0.5 bg-[#94a3b8]"></td>
                                                    </template>
                                                    <template x-if="!currentFormMeta.ttd_config || currentFormMeta.ttd_config.col3_type !== 'blocked'">
                                                        <td class="p-0.5 text-center font-mono">0</td>
                                                    </template>
                                                </tr>

                                                <!-- RATA - RATA ROW FOR GROUP -->
                                                <tr class="border-b border-slate-900 bg-white text-[8px]">
                                                    <td colspan="2" class="px-1.5 py-0.5 font-bold text-right border-r border-slate-900 text-slate-800">
                                                        <span x-text="formatRataRataLabel(group, gKey)"></span>
                                                    </td>
                                                    <template x-if="currentFormMeta.ttd_config && currentFormMeta.ttd_config.col1_type === 'blocked'">
                                                        <td class="p-0.5 border-r border-slate-900 bg-[#94a3b8]"></td>
                                                    </template>
                                                    <template x-if="!currentFormMeta.ttd_config || currentFormMeta.ttd_config.col1_type !== 'blocked'">
                                                        <td class="p-0.5 text-center font-mono border-r border-slate-900 font-bold">0.00</td>
                                                    </template>

                                                    <template x-if="currentFormMeta.ttd_config && currentFormMeta.ttd_config.col2_type === 'blocked'">
                                                        <td class="p-0.5 border-r border-slate-900 bg-[#94a3b8]"></td>
                                                    </template>
                                                    <template x-if="!currentFormMeta.ttd_config || currentFormMeta.ttd_config.col2_type !== 'blocked'">
                                                        <td class="p-0.5 text-center font-mono border-r border-slate-900 font-bold">0.00</td>
                                                    </template>

                                                    <template x-if="currentFormMeta.ttd_config && currentFormMeta.ttd_config.col3_type === 'blocked'">
                                                        <td class="p-0.5 bg-[#94a3b8]"></td>
                                                    </template>
                                                    <template x-if="!currentFormMeta.ttd_config || currentFormMeta.ttd_config.col3_type !== 'blocked'">
                                                        <td class="p-0.5 text-center font-mono font-bold">0.00</td>
                                                    </template>
                                                </tr>

                                                <!-- NILAI AKHIR ROW FOR GROUP -->
                                                <tr class="border-b-2 border-slate-900 bg-[#f8fafc] text-[8px]">
                                                    <td colspan="2" class="px-1.5 py-0.5 font-black text-right border-r border-slate-900 text-[#92302e]">
                                                        <span x-text="formatNilaiAkhirLabel(group, gKey)"></span>
                                                    </td>
                                                    <template x-if="currentFormMeta.ttd_config && currentFormMeta.ttd_config.col1_type === 'blocked'">
                                                        <td class="p-0.5 border-r border-slate-900 bg-[#94a3b8]"></td>
                                                    </template>
                                                    <template x-if="!currentFormMeta.ttd_config || currentFormMeta.ttd_config.col1_type !== 'blocked'">
                                                        <td class="p-0.5 text-center font-mono font-black border-r border-slate-900 text-[#92302e]">0.00</td>
                                                    </template>

                                                    <template x-if="currentFormMeta.ttd_config && currentFormMeta.ttd_config.col2_type === 'blocked'">
                                                        <td class="p-0.5 border-r border-slate-900 bg-[#94a3b8]"></td>
                                                    </template>
                                                    <template x-if="!currentFormMeta.ttd_config || currentFormMeta.ttd_config.col2_type !== 'blocked'">
                                                        <td class="p-0.5 text-center font-mono font-black border-r border-slate-900 text-[#92302e]">0.00</td>
                                                    </template>

                                                    <template x-if="currentFormMeta.ttd_config && currentFormMeta.ttd_config.col3_type === 'blocked'">
                                                        <td class="p-0.5 bg-[#94a3b8]"></td>
                                                    </template>
                                                    <template x-if="!currentFormMeta.ttd_config || currentFormMeta.ttd_config.col3_type !== 'blocked'">
                                                        <td class="p-0.5 text-center font-mono font-black text-[#92302e]">0.00</td>
                                                    </template>
                                                </tr>
                                            </tbody>
                                        </template>
                                    </table>
                                </div>

                                <!-- Bagian Bawah Kolom Kiri: Tanggal, Form Tag, Catatan & Penjumlahan Nilai -->
                                <div class="border-t-2 border-slate-900 bg-white">
                                    
                                    <!-- Tanggal & Form Tag -->
                                    <div class="px-2 py-0.5 flex items-center justify-between border-b border-slate-900 text-[8px] bg-slate-50">
                                        <div>
                                            <span class="font-bold text-slate-700" x-text="currentFormMeta.is_bilingual ? 'Tanggal 日付 : ' : 'Tanggal : '">Tanggal : </span>
                                            <span class="font-bold text-[#92302e]" x-text="formatDateIndo(formSettings.tanggal_dokumen)"></span>
                                        </div>
                                        <div>
                                            <span class="font-bold text-slate-700">Form: </span>
                                            <span class="font-mono font-black text-slate-900" x-text="selectedKode"></span>
                                        </div>
                                    </div>

                                    <!-- Catatan 1 & 2 -->
                                    <div class="px-2 py-1 text-[7.5px] text-slate-700 leading-tight border-b border-slate-900 space-y-0.5">
                                        <div>
                                            <strong>CATATAN :</strong> 1. <span x-text="formSettings.catatan_1"></span>
                                            <template x-if="currentFormMeta.is_bilingual">
                                                <div class="text-[7px] text-slate-500 font-normal">注：1.端数が出た場合は、小数点以下第2位まで計算されます</div>
                                            </template>
                                        </div>
                                        <div>
                                            2. <span x-text="formSettings.catatan_2"></span>
                                            <template x-if="currentFormMeta.is_bilingual">
                                                <div class="text-[7px] text-slate-500 font-normal">2. 四捨五入の目安は0.5まで切り捨て、0.5を超えると切り上げます</div>
                                            </template>
                                        </div>
                                    </div>

                                    <!-- Penjumlahan Penilaian Box -->
                                    <div class="bg-white">
                                        <div class="bg-[#d9d9d9] px-2 py-0.5 text-center font-black text-[8px] tracking-wider uppercase border-b border-slate-900 text-slate-900">
                                            PENJUMLAHAN PENILAIAN
                                        </div>
                                        <div class="p-1.5 bg-[#d4f8d4] flex flex-wrap items-center justify-center gap-1.5 text-[8px]">
                                            <template x-for="(group, gKey, idx) in (activeStructure ? activeStructure.groups : {})" :key="gKey">
                                                <div class="flex items-center gap-1">
                                                    <div class="text-center">
                                                        <span class="font-bold uppercase text-[7px] block text-slate-800 leading-tight">
                                                            <span x-text="cleanGroupName(group.name)"></span>
                                                            <template x-if="currentFormMeta.is_bilingual">
                                                                <span class="block text-[6px] text-slate-600 font-normal" x-text="getJapaneseGroupSub(gKey)"></span>
                                                            </template>
                                                        </span>
                                                        <span class="inline-block px-2 py-0.2 bg-white border border-slate-900 font-mono font-bold text-slate-900 shadow-2xs">0</span>
                                                    </div>
                                                    <span x-show="idx < Object.keys(activeStructure.groups).length - 1" class="font-black text-slate-700 text-xs mt-2">+</span>
                                                </div>
                                            </template>
                                            <span class="font-black text-slate-700 text-xs mt-2">=</span>
                                            <div class="text-center">
                                                <span class="font-black uppercase text-[7px] block text-slate-800 leading-tight">
                                                    <span>TOTAL</span>
                                                    <template x-if="currentFormMeta.is_bilingual">
                                                        <span class="block text-[6px] text-slate-600 font-normal">合計</span>
                                                    </template>
                                                </span>
                                                <span class="inline-block px-2.5 py-0.2 bg-white border-2 border-[#92302e] font-mono font-black text-[#92302e] shadow-2xs">0</span>
                                            </div>
                                        </div>
                                    </div>

                                </div>

                            </div>

                            <!-- ========================================================= -->
                            <!-- BODY RIGHT: INDIKATOR EVALUASI POTENSI, KELEBIHAN, KEKURANGAN -->
                            <!-- ========================================================= -->
                            <div class="col-span-5 flex flex-col justify-between bg-white text-[8px]">
                                
                                <!-- 1. Box Indikator Potensi -->
                                <div class="flex-1">
                                    <div class="bg-[#d1d5db] px-2 py-1 text-center font-black text-[8.5px] uppercase tracking-wide border-b border-slate-900 text-slate-900 flex items-center justify-between gap-1">
                                        <span class="flex-1 text-center" 
                                              x-text="currentFormMeta.is_bilingual ? (formSettings.judul_evaluasi_potensi + ' / 従業員の発展') : formSettings.judul_evaluasi_potensi">
                                            INDIKATOR EVALUASI POTENSI KARYAWAN
                                        </span>
                                        <template x-if="currentFormMeta.is_bilingual">
                                            <span class="px-1.5 py-0.5 bg-[#92302e] text-white text-[7px] font-bold rounded-none flex items-center gap-0.5 shadow-2xs">
                                                <span>💾</span> Simpan
                                            </span>
                                        </template>
                                    </div>
                                    
                                    <div class="p-2 space-y-2">
                                        <div>
                                            <span class="font-bold text-slate-900 block text-[7.5px]" 
                                                  x-text="currentFormMeta.is_bilingual ? 'Petunjuk Pengisian / 記入方法' : 'Petunjuk Pengisian.'">
                                                Petunjuk Pengisian.
                                            </span>
                                            <p class="text-[7.5px] text-slate-600 leading-relaxed mt-0.5" 
                                               x-text="formSettings.petunjuk_evaluasi_potensi">
                                            </p>
                                            <template x-if="currentFormMeta.is_bilingual">
                                                <p class="text-[7px] text-slate-500 leading-snug mt-0.5">
                                                    売上増加や能力・知識・スキル・意欲・責任感・リーダーシップ・専門性などを総合的に判断する
                                                </p>
                                            </template>
                                        </div>

                                        <!-- 4 Pilihan Opsi Potensi -->
                                        <div class="space-y-2 pt-0.5">
                                            <!-- Opsi 1 -->
                                            <div class="space-y-0.5">
                                                <div class="flex items-start gap-1.5">
                                                    <span class="w-3.5 h-3.5 border-2 border-slate-900 bg-slate-900 text-white flex items-center justify-center font-bold text-[8px] flex-shrink-0">✕</span>
                                                    <div>
                                                        <span class="font-semibold text-slate-900 leading-snug text-[7.5px]" 
                                                              x-text="formSettings.opsi_potensi_1">
                                                        </span>
                                                        <template x-if="currentFormMeta.is_bilingual">
                                                            <span class="block text-[7px] text-slate-500">能力が高く、今より上のレベルに上がれる。お勧めとなる職位は</span>
                                                        </template>
                                                    </div>
                                                </div>
                                                <div class="pl-5 text-slate-400 italic text-[7px]">
                                                    Tuliskan saran posisi promosi...
                                                </div>
                                            </div>

                                            <!-- Opsi 2 -->
                                            <div class="space-y-0.5">
                                                <div class="flex items-start gap-1.5">
                                                    <span class="w-3.5 h-3.5 border border-slate-900 bg-white flex items-center justify-center font-bold text-[8px] flex-shrink-0"></span>
                                                    <div>
                                                        <span class="font-semibold text-slate-900 leading-snug text-[7.5px]" 
                                                              x-text="formSettings.opsi_potensi_2">
                                                        </span>
                                                        <template x-if="currentFormMeta.is_bilingual">
                                                            <span class="block text-[7px] text-slate-500">今の能力よりもまだ成長することが出来そのトレーニングも継続可能である。役に立てそうない</span>
                                                        </template>
                                                    </div>
                                                </div>
                                                <div class="pl-5 text-slate-400 italic text-[7px]">
                                                    Tuliskan catatan pelatihan / pengembangan...
                                                </div>
                                            </div>

                                            <!-- Opsi 3 -->
                                            <div class="space-y-0.5">
                                                <div class="flex items-start gap-1.5">
                                                    <span class="w-3.5 h-3.5 border border-slate-900 bg-white flex items-center justify-center font-bold text-[8px] flex-shrink-0"></span>
                                                    <div>
                                                        <span class="font-semibold text-slate-900 leading-snug text-[7.5px]" 
                                                              x-text="formSettings.opsi_potensi_3">
                                                        </span>
                                                        <template x-if="currentFormMeta.is_bilingual">
                                                            <span class="block text-[7px] text-slate-500">現在のレベルでバランスが取れて展開する事は難しいだろう。何故なら？能力低下</span>
                                                        </template>
                                                    </div>
                                                </div>
                                                <div class="pl-5 text-slate-400 italic text-[7px]">
                                                    Tuliskan alasan...
                                                </div>
                                            </div>

                                            <!-- Opsi 4 -->
                                            <div class="space-y-0.5">
                                                <div class="flex items-start gap-1.5">
                                                    <span class="w-3.5 h-3.5 border border-slate-900 bg-white flex items-center justify-center font-bold text-[8px] flex-shrink-0"></span>
                                                    <div>
                                                        <span class="font-semibold text-slate-900 leading-snug text-[7.5px]" 
                                                              x-text="formSettings.opsi_potensi_4">
                                                        </span>
                                                        <template x-if="currentFormMeta.is_bilingual">
                                                            <span class="block text-[7px] text-slate-500">現状の職位に合ってない。その理由</span>
                                                        </template>
                                                    </div>
                                                </div>
                                                <div class="pl-5 text-slate-400 italic text-[7px]">
                                                    Tuliskan alasan...
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- 2. Box Kelebihan Karyawan -->
                                <div class="border-t-2 border-slate-900">
                                    <div class="bg-[#d1d5db] px-2 py-0.5 text-center font-black text-[8px] uppercase tracking-wide border-b border-slate-900 text-slate-900"
                                         x-text="currentFormMeta.is_bilingual ? (formSettings.judul_kelebihan + ' / 従業員のプラス所') : formSettings.judul_kelebihan">
                                        KELEBIHAN KARYAWAN
                                    </div>
                                    <div class="p-2 text-[7.5px] text-slate-400 italic min-h-[42px] bg-white">
                                        Tuliskan kelebihan karyawan...
                                    </div>
                                </div>

                                <!-- 3. Box Kekurangan Karyawan -->
                                <div class="border-t-2 border-slate-900">
                                    <div class="bg-[#d1d5db] px-2 py-0.5 text-center font-black text-[8px] uppercase tracking-wide border-b border-slate-900 text-slate-900"
                                         x-text="currentFormMeta.is_bilingual ? (formSettings.judul_kekurangan + ' / 従業員のマイナス所') : formSettings.judul_kekurangan">
                                        KEKURANGAN KARYAWAN
                                    </div>
                                    <div class="p-2 text-[7.5px] text-slate-400 italic min-h-[42px] bg-white">
                                        Tuliskan kekurangan / area pengembangan karyawan...
                                    </div>
                                </div>

                            </div>

                        </div>

                    </div>
                </div>

                <!-- Action Button under PDF Preview -->
                <div class="pt-1">
                    <a :href="'/dokumen/' + currentFormMeta.sample_doc_id + '/pdf'" 
                       target="_blank" 
                       class="w-full py-2.5 px-4 text-center font-bold text-xs text-white bg-[#92302e] hover:bg-[#7e2524] rounded-none transition flex items-center justify-center gap-2 cursor-pointer shadow-xs">
                        <span>📄</span> Buka Lembar PDF Asli Formulir Ini (Tab Baru) &rarr;
                    </a>
                </div>

            </div>

        </div>

    </div>

</div>

@push('scripts')
<script>
    function formPengaturanStudio() {
        return {
            selectedKode: '07-001',
            simulasiTtd: false,
            formSettings: {
                tahun_penilaian: '{{ $formSettings['tahun_penilaian'] }}',
                periode_penilaian: '{{ $formSettings['periode_penilaian'] }}',
                judul_form_pk: '{{ $formSettings['judul_form_pk'] }}',
                nama_perusahaan: '{{ $formSettings['nama_perusahaan'] }}',
                catatan_petunjuk_penilai: '{{ addslashes($formSettings['catatan_petunjuk_penilai']) }}',
                status_periode: '{{ $formSettings['status_periode'] }}',
                tanggal_dokumen: '{{ $formSettings['tgl_mulai'] ?? date('Y-m-d') }}',
                tgl_mulai: '{{ $formSettings['tgl_mulai'] }}',
                tgl_selesai: '{{ $formSettings['tgl_selesai'] }}',
                label_kerahasiaan: '{{ $formSettings['label_kerahasiaan'] }}',
                catatan_1: '{{ addslashes($formSettings['catatan_1']) }}',
                catatan_2: '{{ addslashes($formSettings['catatan_2']) }}',
                judul_evaluasi_potensi: '{{ addslashes($formSettings['judul_evaluasi_potensi']) }}',
                petunjuk_evaluasi_potensi: '{{ addslashes($formSettings['petunjuk_evaluasi_potensi']) }}',
                opsi_potensi_1: '{{ addslashes($formSettings['opsi_potensi_1']) }}',
                opsi_potensi_2: '{{ addslashes($formSettings['opsi_potensi_2']) }}',
                opsi_potensi_3: '{{ addslashes($formSettings['opsi_potensi_3']) }}',
                opsi_potensi_4: '{{ addslashes($formSettings['opsi_potensi_4']) }}',
                judul_kelebihan: '{{ addslashes($formSettings['judul_kelebihan']) }}',
                judul_kekurangan: '{{ addslashes($formSettings['judul_kekurangan']) }}'
            },
            forms: @json($daftarForm),
            activeStructure: null,

            init() {
                this.selectForm(this.selectedKode);
            },

            selectForm(kode) {
                this.selectedKode = kode;
                const f = this.forms.find(x => x.kode === kode) || this.forms[0];
                if (f && f.structure) {
                    this.activeStructure = JSON.parse(JSON.stringify(f.structure));
                }
            },

            get currentFormMeta() {
                return this.forms.find(x => x.kode === this.selectedKode) || this.forms[0];
            },

            addAspect(groupKey) {
                if (!this.activeStructure || !this.activeStructure.groups || !this.activeStructure.groups[groupKey]) return;
                const curCount = Object.keys(this.activeStructure.groups[groupKey].aspects || {}).length + 1;
                const uniqueKey = groupKey.substring(0, 2).toUpperCase() + '_CUSTOM_' + Date.now();
                this.activeStructure.groups[groupKey].aspects[uniqueKey] = 'Kriteria Aspek ' + curCount;
            },

            removeAspect(groupKey, aspectKey) {
                if (!this.activeStructure || !this.activeStructure.groups || !this.activeStructure.groups[groupKey]) return;
                if (Object.keys(this.activeStructure.groups[groupKey].aspects).length <= 1) {
                    alert('Setiap kelompok aspek minimal harus memiliki 1 kriteria!');
                    return;
                }
                delete this.activeStructure.groups[groupKey].aspects[aspectKey];
            },

            toggleManManagement() {
                if (!this.activeStructure || !this.activeStructure.groups) return;
                if (this.activeStructure.groups['man_management']) {
                    delete this.activeStructure.groups['man_management'];
                } else {
                    this.activeStructure.groups['man_management'] = {
                        name: 'MAN MANAGEMENT (20%)',
                        weight: 20,
                        aspects: {
                            'MM_KEPEMIMPINAN': 'Kepemimpinan',
                            'MM_KEPUTUSAN': 'Pengambilan Keputusan',
                            'MM_DELEGASI': 'Pendelegasian Tugas',
                            'MM_PENGEMBANGAN': 'Pengembangan Bawahan (Counching & Counseling)'
                        }
                    };
                }
            },

            totalWeight() {
                if (!this.activeStructure || !this.activeStructure.groups) return 0;
                let sum = 0;
                for (let k in this.activeStructure.groups) {
                    sum += parseFloat(this.activeStructure.groups[k].weight || 0);
                }
                return Math.round(sum);
            },

            cleanGroupName(name) {
                if (!name) return '';
                return name.replace(/\s*\([0-9]+%\).*/i, '').trim();
            },

            formatRataRataLabel(group, gKey) {
                const aspCount = group.aspects ? Object.keys(group.aspects).length : 0;
                const cleanName = this.cleanGroupName(group.name);
                if (!this.currentFormMeta.is_bilingual) {
                    return 'RATA - RATA ' + cleanName + ' (Total Nilai dibagi ' + aspCount + ')';
                }
                if (gKey === 'hasil_kerja') {
                    return 'RATA - RATA HASIL KERJA (Total Nilai dibagi ' + aspCount + ') / 仕事の結果の平均 (合計値を' + aspCount + 'で割ったもの)';
                } else if (gKey === 'proses_kerja') {
                    return 'RATA - RATA PROSES KERJA (Total Nilai dibagi ' + aspCount + ') / 仕事のプロセスの平均 (合計値を' + aspCount + 'で割ったもの)';
                } else if (gKey === 'man_management') {
                    return 'RATA - RATA MANAGEMENT (Total Nilai dibagi ' + aspCount + ') / 管理能力の平均 (合計値を' + aspCount + 'で割ったもの)';
                } else if (gKey === 'sikap_kerja') {
                    return 'RATA - RATA SIKAP KERJA (Total Nilai dibagi ' + aspCount + ') / 仕事の姿勢の平均 (合計値を' + aspCount + 'で割ったもの)';
                }
                return 'RATA - RATA ' + cleanName + ' (Total Nilai dibagi ' + aspCount + ')';
            },

            formatNilaiAkhirLabel(group, gKey) {
                const cleanName = this.cleanGroupName(group.name);
                const weight = group.weight || 0;
                if (!this.currentFormMeta.is_bilingual) {
                    return 'NILAI AKHIR ' + cleanName + ' (Rata-Rata ' + cleanName + ' X ' + weight + '%)';
                }
                if (gKey === 'hasil_kerja') {
                    return 'NILAI AKHIR HASIL KERJA (Rata-Rata Hasil Kerja X ' + weight + '%) / 仕事の結果の最終得点 (平均点数掛ける' + weight + '%)';
                } else if (gKey === 'proses_kerja') {
                    return 'NILAI AKHIR PROSES KERJA (Rata-Rata Proses Kerja X ' + weight + '%) / 最終的な仕事手順 (平均点数掛ける' + weight + '%)';
                } else if (gKey === 'man_management') {
                    return 'NILAI AKHIR MANAGEMENT (Rata-Rata Sikap Kerja X ' + weight + '%) / ファイナルバリューマネジメント (平均点数掛ける' + weight + '%)';
                } else if (gKey === 'sikap_kerja') {
                    return 'NILAI AKHIR SIKAP KERJA (Rata-Rata Sikap Kerja X ' + weight + '%) / 態度における最終的な得点 (平均点数掛ける' + weight + '%)';
                }
                return 'NILAI AKHIR ' + cleanName + ' (Rata-Rata ' + cleanName + ' X ' + weight + '%)';
            },

            getJapaneseGroupSub(gKey) {
                if (gKey === 'hasil_kerja') return '仕事の結果';
                if (gKey === 'proses_kerja') return '仕事の方法';
                if (gKey === 'man_management') return '人材管理';
                if (gKey === 'sikap_kerja') return '仕事に関する性格';
                return '';
            },

            formatDateIndo(dateStr) {
                if (!dateStr) return '04 November 2026';
                const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                try {
                    const parts = dateStr.split('-');
                    if (parts.length === 3) {
                        const day = parseInt(parts[2], 10);
                        const mIdx = parseInt(parts[1], 10) - 1;
                        const year = parts[0];
                        return (day < 10 ? '0' + day : day) + ' ' + (months[mIdx] || '') + ' ' + year;
                    }
                } catch(e) {}
                return dateStr;
            },

            get serializedStructure() {
                return this.activeStructure ? JSON.stringify(this.activeStructure) : '';
            }
        };
    }
</script>
@endpush
@endsection