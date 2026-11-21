import os
import subprocess
from pathlib import Path

BASE_DIR = Path(r"C:\Users\USER\Music\E-Approval")
OUTPUT_DIR = BASE_DIR / "public" / "panduan"
OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
CHROME_PATH = r"C:\Program Files\Google\Chrome\Application\chrome.exe"

from generate_guidebooks_module import COMMON_CSS, generate_pdf
from build_all_6_pdfs import render_login_mockup, render_login_explanation
from guide_helpers import get_header, get_footer

# ==============================================================================
# 1. PANDUAN FOREMAN
# ==============================================================================
def build_guide_foreman():
    html = f"""<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Panduan Penggunaan - Foreman</title>
    <style>{COMMON_CSS}</style>
</head>
<body>

<!-- HALAMAN 1: Akses Login & Ikhtisar Alur -->
<div class="page">
    {get_header("PANDUAN OPERASIONAL APLIKASI", "ROLE: FOREMAN", "SOP-PA-01-FRM")}
    
    <div style="background:#fdf2f2; border:1px solid #fecaca; border-radius:6px; padding:10px 14px; margin-bottom:12px; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <div style="font-size:12px; font-weight:800; color:#7b181b;">PANDUAN PENGGUNAAN PERFORMANCE APPRAISAL</div>
            <div style="font-size:9.5px; color:#475569; margin-top:2px;">Petunjuk Teknis Penilaian Kinerja Tingkat Foreman & Pelaksana Lini</div>
        </div>
        <div style="text-align:right;">
            <span style="font-size:8.5px; background:#ffffff; border:1px solid #fca5a5; color:#7b181b; font-weight:700; padding:3px 8px; border-radius:4px;">
                Link: https://performanceappraisal.vercel.app/
            </span>
        </div>
    </div>

    <div class="section-title">
        <span>BAGIAN 1: PROSEDUR MASUK (LOGIN) KE APLIKASI</span>
        <span style="font-size:8.5px; font-weight:600; color:#64748b;">Langkah Otentikasi</span>
    </div>
    <div class="section-sub">Buka peramban (Chrome / Edge) pada HP atau Komputer Anda dan akses alamat website resmi.</div>

    {render_login_mockup("Foreman")}
    {render_login_explanation()}

    <div class="info-alert" style="margin-top:12px;">
        <span style="font-size:14px; font-weight:bold;">ℹ</span>
        <div>
            <strong>Catatan Otomatisasi Peran (Auto-Routing):</strong><br>
            Anda tidak perlu lagi memilih peran pada halaman login. Sistem PerformanceAppraisal secara cerdas mengenali akun Foreman Anda dan langsung menampilkan menu khusus Foreman: <strong>Dashboard, Data Karyawan, Form Penilaian, Riwayat Penilaian, dan Pusat Laporan</strong>.
        </div>
    </div>

    {get_footer("Foreman", 1, 3)}
</div>

<!-- HALAMAN 2: Menilai Karyawan Bawahan (Lini / Operator) -->
<div class="page">
    {get_header("PENILAIAN KARYAWAN BAWAHAN", "ROLE: FOREMAN", "SOP-PA-01-FRM")}

    <div class="section-title">
        <span>SKENARIO A: MENILAI KARYAWAN BAWAHAN (OPERATOR / LINI PRODUKSI)</span>
        <span style="font-size:8.5px; font-weight:600; color:#64748b;">Form 07-001 s/d 07-005</span>
    </div>
    <div class="section-sub">Foreman berperan sebagai <strong>Penilai Pertama</strong> untuk mengevaluasi kinerja teknis operator/staf di bawah kendalinya.</div>

    <!-- Mockup Form Penilaian Foreman -->
    <div class="browser-mockup">
        <div class="browser-bar">
            <div class="browser-dots">
                <div class="browser-dot dot-red"></div>
                <div class="browser-dot dot-yellow"></div>
                <div class="browser-dot dot-green"></div>
            </div>
            <div class="browser-url">https://performanceappraisal.vercel.app/penilaian/form/10293</div>
        </div>
        <div class="browser-body">
            <!-- Mock Top Bar Info -->
            <div class="mock-topbar">
                <span>FORMULIR PENILAIAN PRESTASI KERJA - TAHAP 1 (FOREMAN)</span>
                <span class="mock-badge bg-rose-soft" style="background:#ffffff; color:#7b181b;">FORM 07-001 (OPERATOR)</span>
            </div>

            <!-- Target 1: Identitas Karyawan -->
            <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:4px; padding:8px; margin-bottom:8px; position:relative;">
                <div style="font-size:9px; font-weight:800; color:#0f172a; margin-bottom:4px;">1. DATA POKOK KARYAWAN YANG DINILAI</div>
                <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:6px; font-size:8px;">
                    <div><span style="color:#64748b;">NRP:</span> <strong>204512</strong></div>
                    <div><span style="color:#64748b;">Nama:</span> <strong>AHMAD KURNIAWAN</strong></div>
                    <div><span style="color:#64748b;">Departemen:</span> <strong>PRODUCTION 2</strong></div>
                    <div><span style="color:#64748b;">Jabatan:</span> <strong>OPERATOR (KJ 2)</strong></div>
                </div>
                <div class="target-box" style="top:-2px; left:-2px; right:-2px; bottom:-2px;"></div>
                <div class="num-marker" style="top:50%; right:-10px;">1</div>
            </div>

            <!-- Target 2: Aspek Penilaian -->
            <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:4px; padding:8px; margin-bottom:8px; position:relative;">
                <div style="font-size:9px; font-weight:800; color:#0f172a; margin-bottom:4px;">2. EVALUASI ASPEK PRESTASI KERJA (BOBOT & NILAI)</div>
                <table class="mock-table">
                    <thead>
                        <tr>
                            <th style="width:25px;">NO</th>
                            <th>UNSUR PENILAIAN</th>
                            <th style="width:40px; text-align:center;">BOBOT</th>
                            <th style="width:70px; text-align:center;">SKOR (1 - 4)</th>
                            <th style="width:50px; text-align:center;">SUBTOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="text-align:center;">I</td>
                            <td><strong>HASIL KERJA</strong> (Pencapaian Target Q, C, D, S, M)</td>
                            <td style="text-align:center;">40%</td>
                            <td style="text-align:center; background:#fef2f2; font-weight:bold; color:#7b181b;">3 (Baik)</td>
                            <td style="text-align:center;">1.20</td>
                        </tr>
                        <tr>
                            <td style="text-align:center;">II</td>
                            <td><strong>PROSES KERJA</strong> (Standar Operasional, 5S, Kaizen)</td>
                            <td style="text-align:center;">30%</td>
                            <td style="text-align:center; background:#fef2f2; font-weight:bold; color:#7b181b;">3 (Baik)</td>
                            <td style="text-align:center;">0.90</td>
                        </tr>
                        <tr>
                            <td style="text-align:center;">III</td>
                            <td><strong>SIKAP KERJA</strong> (Disiplin, Absensi, Kerjasama, Safety)</td>
                            <td style="text-align:center;">30%</td>
                            <td style="text-align:center; background:#fef2f2; font-weight:bold; color:#7b181b;">4 (Sangat Baik)</td>
                            <td style="text-align:center;">1.20</td>
                        </tr>
                        <tr style="background:#f1f5f9; font-weight:bold;">
                            <td colspan="4" style="text-align:right;">TOTAL NILAI PRESTASI KERJA (GRADE B):</td>
                            <td style="text-align:center; color:#15803d; font-size:9px;">3.30 (B)</td>
                        </tr>
                    </tbody>
                </table>
                <div class="target-box" style="top:-2px; left:-2px; right:-2px; bottom:-2px;"></div>
                <div class="num-marker" style="top:50%; right:-10px;">2</div>
            </div>

            <!-- Target 3: Catatan & TTD -->
            <div style="display:flex; gap:10px; margin-bottom:8px;">
                <div style="flex:1; background:#ffffff; border:1px solid #cbd5e1; border-radius:4px; padding:6px; position:relative;">
                    <div style="font-size:8.5px; font-weight:800; color:#0f172a; margin-bottom:3px;">3. CATATAN & REKOMENDASI FOREMAN</div>
                    <textarea readonly style="width:100%; height:32px; font-size:8px; border:1px solid #e2e8f0; border-radius:3px; padding:3px; resize:none;">Target produksi harian tercapai baik. Terus pertahankan kedisiplinan APD.</textarea>
                    <div class="target-box" style="top:-2px; left:-2px; right:-2px; bottom:-2px;"></div>
                    <div class="num-marker" style="top:50%; right:-10px;">3</div>
                </div>
                <div style="width:180px; background:#ffffff; border:1px solid #cbd5e1; border-radius:4px; padding:6px; position:relative;">
                    <div style="font-size:8.5px; font-weight:800; color:#0f172a; margin-bottom:3px;">4. PENGESAHAN TTD FOREMAN</div>
                    <div style="border:1px dashed #10b981; background:#f0fdf4; border-radius:4px; padding:4px; text-align:center;">
                        <span style="font-size:7.5px; color:#15803d; font-weight:bold;">✓ TANDA TANGAN DIGITAL SAH</span>
                        <div style="font-size:8px; font-weight:800; color:#0f172a; margin-top:2px;">BAMBANG IRAWAN (NRP: 104)</div>
                        <div style="font-size:7px; color:#64748b;">Foreman Line Machining 2</div>
                    </div>
                    <div class="target-box" style="top:-2px; left:-2px; right:-2px; bottom:-2px;"></div>
                    <div class="num-marker" style="top:50%; right:-10px;">4</div>
                </div>
            </div>

            <!-- Target 5: Action Submit -->
            <div style="display:flex; justify-content:flex-end; gap:6px; position:relative;">
                <button class="btn-mock-secondary">Simpan Draft Sementara</button>
                <button class="btn-mock">Kirim ke Section Head &rarr;</button>
                <div class="target-box" style="top:-2px; left:-2px; right:-2px; bottom:-2px;"></div>
                <div class="num-marker" style="top:50%; right:-10px;">5</div>
            </div>
        </div>
    </div>

    <!-- Explanations -->
    <div class="steps-grid">
        <div class="step-card">
            <span class="num-callout">1</span>
            <div class="step-content">
                <h4>Periksa Data Pokok Karyawan</h4>
                <p>Pastikan NRP, Nama, Bagian, dan Kode Form sudah sesuai dengan posisi bawahan yang dinilai (Form 07-001 s/d 07-005).</p>
            </div>
        </div>
        <div class="step-card">
            <span class="num-callout">2</span>
            <div class="step-content">
                <h4>Input Nilai Skor Prestasi Kerja (Skala 1 - 4)</h4>
                <p>Pilih skor objektif pada setiap unsur: <strong>Hasil Kerja, Proses Kerja, dan Sikap Kerja</strong>. Nilai total dan Grade (S, A, B, C, K) akan terkalkulasi secara otomatis oleh sistem.</p>
            </div>
        </div>
        <div class="step-card">
            <span class="num-callout">3</span>
            <div class="step-content">
                <h4>Tuliskan Catatan dan Feedback Kinerja</h4>
                <p>Berikan catatan pembinaan konkret mengenai pencapaian positif maupun aspek yang perlu ditingkatkan oleh karyawan bersangkutan.</p>
            </div>
        </div>
        <div class="step-card">
            <span class="num-callout">4</span>
            <div class="step-content">
                <h4>Bubuhi Tanda Tangan Digital Foreman</h4>
                <p>Klik tombol tanda tangan untuk mengesahkan penilaian Anda secara elektronik. Nama dan NRP Anda akan tertera resmi.</p>
            </div>
        </div>
        <div class="step-card success-border">
            <span class="num-callout" style="background:#10b981; box-shadow:0 0 0 2px #d1fae5;">5</span>
            <div class="step-content">
                <h4>Kirim Penilaian ke Section Head</h4>
                <p>Klik <strong>Kirim ke Section Head</strong>. Status penilaian akan berubah menjadi <code>Menunggu Section Head</code> dan berpindah ke antrean review atasan Anda.</p>
            </div>
        </div>
    </div>

    {get_footer("Foreman", 2, 3)}
</div>

<!-- HALAMAN 3: Skenario Foreman KJ >= 5 & Aturan Revisi -->
<div class="page">
    {get_header("SKENARIO KHUSUS & ATURAN REVISI", "ROLE: FOREMAN", "SOP-PA-01-FRM")}

    <div class="section-title">
        <span>SKENARIO B: FOREMAN DENGAN KATEGORI KJ 5 KE ATAS (FORM 07-006 MANDIRI)</span>
        <span style="font-size:8.5px; font-weight:600; color:#64748b;">Self-Evaluation Flow</span>
    </div>
    <div class="section-sub">Ketentuan Khusus bagi Foreman yang Memiliki Tingkat Golongan / KJ 5 ke Atas:</div>

    <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:6px; padding:10px; margin-bottom:12px;">
        <div style="display:flex; gap:12px; align-items:flex-start;">
            <div style="font-size:24px; color:#7b181b; font-weight:900;">⚡</div>
            <div style="font-size:9.5px; color:#334155;">
                <strong style="color:#7b181b; font-size:10.5px;">SINKRONISASI OTOMATIS NILAI FOREMAN & MANDIRI:</strong><br>
                Jika Anda adalah seorang <strong>Foreman dan sekaligus berkategori KJ 5 ke atas</strong>, Anda menggunakan <strong>Form 07-006</strong> untuk melakukan <strong>Penilaian Mandiri</strong> terhadap diri sendiri.<br>
                Sesuai ketentuan sistem PT Musashi Auto Parts Indonesia, <strong>Nilai Foreman dan Nilai Penilaian Mandiri akan otomatis terisi SAMA PERSIS</strong>. Anda tidak perlu menginput ganda, cukup selesaikan evaluasi mandiri Anda pada Form 07-006.
            </div>
        </div>
    </div>

    <div class="section-title" style="border-left-color:#dc2626; color:#991b1b;">
        <span>PENTING: KEBIJAKAN INTEGRITAS & KETENTUAN REVISI NILAI</span>
        <span style="font-size:8.5px; font-weight:600; color:#dc2626;">Sistem Keamanan Penilaian</span>
    </div>

    <div class="danger-alert">
        <span style="font-size:16px; font-weight:bold;">⛔</span>
        <div>
            <strong style="font-size:10px;">FOREMAN TIDAK BISA LANGSUNG MENGUBAH / MEREVISI NILAI YANG SUDAH DIKIRIM!</strong><br>
            Karena Foreman adalah <strong>Penilai Tahap Pertama</strong> (bukan penilai akhir), sistem secara ketat mengunci form setelah status dikirimkan. Hal ini bertujuan mencegah ketidaksinkronan data ketika tahapan review berikutnya (Section Head / Dept Head) sudah mulai berjalan.
        </div>
    </div>

    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:10px; margin-top:8px;">
        <div style="font-size:10px; font-weight:800; color:#0f172a; margin-bottom:6px;">PROSEDUR RESMI JIKA FOREMAN INGIN MEREVISI NILAI:</div>
        <div style="display:flex; flex-direction:column; gap:6px;">
            <div style="display:flex; gap:8px; font-size:9px;">
                <span class="num-callout" style="background:#475569; width:16px; height:16px; font-size:9px;">1</span>
                <span><strong>Konfirmasi ke Admin / Superadmin:</strong> Hubungi PIC Administrator HR / IT dan informasikan NRP karyawan serta alasan revisi skor.</span>
            </div>
            <div style="display:flex; gap:8px; font-size:9px;">
                <span class="num-callout" style="background:#475569; width:16px; height:16px; font-size:9px;">2</span>
                <span><strong>Pembukaan Kunci oleh Admin:</strong> Admin akan membuka akses revisi melalui menu <em>Manajemen Revisi Nilai</em> resmi.</span>
            </div>
            <div style="display:flex; gap:8px; font-size:9px;">
                <span class="num-callout" style="background:#475569; width:16px; height:16px; font-size:9px;">3</span>
                <span><strong>Penginputan Ulang:</strong> Setelah gembok dibuka, form penilaian karyawan tersebut akan kembali berstatus <em>Draft / Siap Dinilai</em> sehingga Foreman dapat memperbaiki nilai dan menandatangani ulang.</span>
            </div>
        </div>
    </div>

    <div style="margin-top:14px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; padding:10px;">
        <div style="font-size:10px; font-weight:800; color:#166534; margin-bottom:4px;">RINGKASAN TANGGUNG JAWAB FOREMAN:</div>
        <ul style="margin:0; padding-left:18px; font-size:9px; color:#14532d; line-height:1.5;">
            <li>Menilai seluruh operator dan teknisi bawahan langsung secara adil, objektif, dan transparan.</li>
            <li>Memastikan seluruh penilaian tahap 1 selesai sebelum batas waktu review Section Head dimulai.</li>
            <li>Menyertakan catatan pembinaan yang membangun pada setiap formulir bawahan.</li>
            <li>Mengisi formulir penilaian mandiri Form 07-006 jika berkategori golongan KJ 5 ke atas.</li>
        </ul>
    </div>

    {get_footer("Foreman", 3, 3)}
</div>

</body>
</html>
"""
    generate_pdf(html, OUTPUT_DIR / "Panduan_01_Foreman.pdf")

print("Foreman guide builder ready.")
# ==============================================================================
# 2. PANDUAN SECTION HEAD
# ==============================================================================
def build_guide_section_head():
    html = f"""<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Panduan Penggunaan - Section Head</title>
    <style>{COMMON_CSS}</style>
</head>
<body>

<!-- HALAMAN 1: Akses Login & Menu Navigasi -->
<div class="page">
    {get_header("PANDUAN OPERASIONAL APLIKASI", "ROLE: SECTION HEAD", "SOP-PA-02-SEC")}
    
    <div style="background:#fdf2f2; border:1px solid #fecaca; border-radius:6px; padding:10px 14px; margin-bottom:12px; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <div style="font-size:12px; font-weight:800; color:#7b181b;">PANDUAN PENGGUNAAN PERFORMANCE APPRAISAL</div>
            <div style="font-size:9.5px; color:#475569; margin-top:2px;">Petunjuk Teknis Penilaian Kinerja Kepala Seksi (Section Head)</div>
        </div>
        <div style="text-align:right;">
            <span style="font-size:8.5px; background:#ffffff; border:1px solid #fca5a5; color:#7b181b; font-weight:700; padding:3px 8px; border-radius:4px;">
                Link: https://performanceappraisal.vercel.app/
            </span>
        </div>
    </div>

    <div class="section-title">
        <span>BAGIAN 1: PROSEDUR MASUK (LOGIN) & STRUKTUR MENU</span>
        <span style="font-size:8.5px; font-weight:600; color:#64748b;">Langkah Otentikasi</span>
    </div>
    <div class="section-sub">Akses website melalui peramban Google Chrome atau Microsoft Edge.</div>

    {render_login_mockup("Section Head")}
    {render_login_explanation()}

    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:8px 12px; margin-top:10px;">
        <div style="font-size:9.5px; font-weight:800; color:#0f172a; margin-bottom:4px;">5 MENU UTAMA PADA HALAMAN SECTION HEAD:</div>
        <div style="display:grid; grid-template-columns: repeat(5, 1fr); gap:6px; font-size:8px;">
            <div style="background:white; border:1px solid #cbd5e1; border-radius:4px; padding:5px; text-align:center;">
                <strong>1. Dashboard</strong><br><span style="color:#64748b;">Ringkasan seksi</span>
            </div>
            <div style="background:white; border:1px solid #cbd5e1; border-radius:4px; padding:5px; text-align:center;">
                <strong>2. Data Karyawan</strong><br><span style="color:#64748b;">Daftar anggota seksi</span>
            </div>
            <div style="background:#fef2f2; border:1px solid #fca5a5; border-radius:4px; padding:5px; text-align:center; color:#7b181b;">
                <strong>3. Form Penilaian</strong><br><span>Evaluasi kinerja</span>
            </div>
            <div style="background:white; border:1px solid #cbd5e1; border-radius:4px; padding:5px; text-align:center;">
                <strong>4. Riwayat</strong><br><span style="color:#64748b;">Arsip penilaian</span>
            </div>
            <div style="background:white; border:1px solid #cbd5e1; border-radius:4px; padding:5px; text-align:center;">
                <strong>5. Pusat Laporan</strong><br><span style="color:#64748b;">Rekap & cetak</span>
            </div>
        </div>
    </div>

    {get_footer("Section Head", 1, 3)}
</div>

<!-- HALAMAN 2: Menilai Bawahan Seksi -->
<div class="page">
    {get_header("PENILAIAN BAWAHAN SEKSI", "ROLE: SECTION HEAD", "SOP-PA-02-SEC")}

    <div class="section-title">
        <span>SKENARIO A: MENILAI KARYAWAN BAWAHAN SEKSI (OPERATOR, FOREMAN, STAF KJ 5+)</span>
        <span style="font-size:8.5px; font-weight:600; color:#64748b;">Review Tahap 2</span>
    </div>
    <div class="section-sub">Section Head memeriksa penilaian awal Foreman, menilai staf seksi, dan membubuhkan persetujuan seksi.</div>

    <!-- Mockup Tabel Section Head -->
    <div class="browser-mockup">
        <div class="browser-bar">
            <div class="browser-dots">
                <div class="browser-dot dot-red"></div>
                <div class="browser-dot dot-yellow"></div>
                <div class="browser-dot dot-green"></div>
            </div>
            <div class="browser-url">https://performanceappraisal.vercel.app/penilaian/section</div>
        </div>
        <div class="browser-body">
            <!-- Filter Bar -->
            <div style="display:flex; justify-content:space-between; align-items:center; background:#ffffff; border:1px solid #cbd5e1; border-radius:4px; padding:6px 10px; margin-bottom:8px; position:relative;">
                <div style="display:flex; gap:10px; align-items:center; font-size:8.5px;">
                    <div><span style="color:#64748b;">SECTION:</span> <strong>MACHINING LINE A</strong></div>
                    <div><span style="color:#64748b;">JUMLAH:</span> <strong>12 Karyawan</strong></div>
                </div>
                <div style="display:flex; gap:6px;">
                    <input type="text" placeholder="Cari Nama / NRP..." readonly style="font-size:8px; padding:3px 6px; border:1px solid #cbd5e1; border-radius:3px;">
                    <button class="btn-mock-secondary">Filter</button>
                </div>
                <div class="target-box" style="top:-2px; left:-2px; right:-2px; bottom:-2px;"></div>
                <div class="num-marker" style="top:50%; right:-10px;">1</div>
            </div>

            <!-- Table Section Head -->
            <table class="mock-table" style="position:relative;">
                <thead>
                    <tr>
                        <th style="width:20px; text-align:center;">NO</th>
                        <th style="width:50px;">NRP</th>
                        <th>NAMA & JABATAN</th>
                        <th style="width:45px; text-align:center;">FORM</th>
                        <th style="width:85px;">HASIL FOREMAN</th>
                        <th style="width:85px;">HASIL MANDIRI</th>
                        <th style="width:75px; text-align:center;">STATUS ALUR</th>
                        <th style="width:55px; text-align:center;">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="text-align:center;">1</td>
                        <td style="font-family:'JetBrains Mono'; font-weight:bold;">204512</td>
                        <td>
                            <strong>AHMAD KURNIAWAN</strong><br>
                            <span style="color:#64748b; font-size:7px;">OPERATOR &bull; GOL 2</span>
                        </td>
                        <td style="text-align:center; font-weight:bold; color:#4338ca;">07-001</td>
                        <td>
                            <span class="mock-badge bg-blue-soft">B (3.30)</span><br>
                            <span style="font-size:6.5px; color:#15803d;">✓ TTD Bambang</span>
                        </td>
                        <td style="text-align:center; color:#94a3b8;">-</td>
                        <td style="text-align:center;">
                            <span class="mock-badge bg-amber-soft">🔔 Siap Dinilai</span>
                        </td>
                        <td style="text-align:center;">
                            <button class="btn-mock">Form &rarr;</button>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align:center;">2</td>
                        <td style="font-family:'JetBrains Mono'; font-weight:bold;">198821</td>
                        <td>
                            <strong>HENDRA WIJAYA</strong><br>
                            <span style="color:#64748b; font-size:7px;">GOL 4 NON KASIE &bull; KJ 5</span>
                        </td>
                        <td style="text-align:center; font-weight:bold; color:#4338ca;">07-006</td>
                        <td style="text-align:center; color:#94a3b8;">-</td>
                        <td>
                            <span class="mock-badge bg-green-soft">A (3.60)</span><br>
                            <span style="font-size:6.5px; color:#15803d;">✓ TTD Mandiri</span>
                        </td>
                        <td style="text-align:center;">
                            <span class="mock-badge bg-amber-soft">🔔 Siap Dinilai</span>
                        </td>
                        <td style="text-align:center;">
                            <button class="btn-mock">Form &rarr;</button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="num-marker" style="top:42%; left:58%;">2</div>
            <div class="num-marker" style="top:42%; left:72%;">3</div>
            <div class="num-marker" style="top:60%; right:15px;">4</div>
        </div>
    </div>

    <!-- Explanations -->
    <div class="steps-grid">
        <div class="step-card">
            <span class="num-callout">1</span>
            <div class="step-content">
                <h4>Filter Seksi & Pemeriksaan Daftar Anggota</h4>
                <p>Gunakan bar filter untuk menyeleksi karyawan bawahan pada seksi Anda. Sistem akan menampilkan seluruh operator, foreman, dan staf yang berada di bawah wewenang Anda.</p>
            </div>
        </div>
        <div class="step-card">
            <span class="num-callout">2</span>
            <div class="step-content">
                <h4>Memeriksa Kolom HASIL FOREMAN (Untuk Operator / Lini)</h4>
                <p>Lihat grade dan kelengkapan tanda tangan Foreman. Kolom ini menunjukkan hasil evaluasi tahap 1 yang telah dilakukan oleh pengawas lini.</p>
            </div>
        </div>
        <div class="step-card">
            <span class="num-callout">3</span>
            <div class="step-content">
                <h4>Memeriksa Kolom HASIL PENILAIAN MANDIRI (Untuk Staf KJ 5+)</h4>
                <p>Khusus bawahan berkategori KJ 5 ke atas (seperti Gol 4 Non Kasie / Senior Staff), periksa nilai evaluasi mandiri yang telah mereka input pada Form 07-006.</p>
            </div>
        </div>
        <div class="step-card success-border">
            <span class="num-callout" style="background:#10b981; box-shadow:0 0 0 2px #d1fae5;">4</span>
            <div class="step-content">
                <h4>Klik "Form &rarr;" untuk Mengisi Evaluasi & TTD Section Head</h4>
                <p>Buka formulir penilaian, berikan skor evaluasi Section Head, sertakan catatan pembinaan, lalu bubuhi tanda tangan digital Anda dan klik <strong>Kirim ke Department Head</strong>.</p>
            </div>
        </div>
    </div>

    {get_footer("Section Head", 2, 3)}
</div>

<!-- HALAMAN 3: Menilai Diri Sendiri & Aturan Revisi -->
<div class="page">
    {get_header("MENILAI DIRI SENDIRI & ATURAN REVISI", "ROLE: SECTION HEAD", "SOP-PA-02-SEC")}

    <div class="section-title">
        <span>SKENARIO B: MENILAI DIRI SENDIRI (PENILAIAN MANDIRI - FORM 07-006-SH)</span>
        <span style="font-size:8.5px; font-weight:600; color:#64748b;">Self-Appraisal Kasie</span>
    </div>
    <div class="section-sub">Sebagai Kepala Seksi (KJ 5+), Section Head wajib mengisi evaluasi mandiri terhadap kinerja kepemimpinannya.</div>

    <div class="browser-mockup">
        <div class="browser-bar">
            <div class="browser-dots">
                <div class="browser-dot dot-red"></div>
                <div class="browser-dot dot-yellow"></div>
                <div class="browser-dot dot-green"></div>
            </div>
            <div class="browser-url">https://performanceappraisal.vercel.app/penilaian/section?self=1</div>
        </div>
        <div class="browser-body">
            <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:4px; padding:10px; position:relative;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <div>
                        <span class="mock-badge bg-rose-soft" style="background:#ffe4e6; color:#9f1239; font-weight:900;">BARIS ANDA (SELF-APPRAISAL)</span>
                        <strong style="margin-left:6px; font-size:10px;">KRISTIYANTO &bull; SECTION HEAD MACHINING</strong>
                    </div>
                    <span class="mock-badge bg-purple-soft">FORM 07-006-SH</span>
                </div>
                <div style="font-size:8.5px; color:#475569; line-height:1.4;">
                    1. Klik tombol <strong>Buka Form Mandiri</strong> pada baris nama Anda.<br>
                    2. Berikan skor mandiri pada 4 pilar kompetensi: <strong>Hasil Kerja (30%), Proses Kerja (25%), Sikap Kerja (20%), dan Man Management (25%)</strong>.<br>
                    3. Bubuhi <strong>Tanda Tangan Digital Mandiri</strong> Anda lalu klik <strong>Submit Penilaian Mandiri</strong>.<br>
                    4. Nilai Anda akan langsung mengalir ke <strong>Department Head</strong> untuk docking nilai akhir (tanpa melalui section head lain).
                </div>
                <div class="target-box" style="top:-2px; left:-2px; right:-2px; bottom:-2px;"></div>
                <div class="num-marker" style="top:50%; right:-10px;">1</div>
            </div>
        </div>
    </div>

    <div class="danger-alert" style="margin-top:12px;">
        <span style="font-size:16px; font-weight:bold;">⛔</span>
        <div>
            <strong style="font-size:10px;">ATURAN REVISI NILAI UNTUK SECTION HEAD:</strong><br>
            Section Head <strong>TIDAK DAPAT MEREVISI LANGSUNG</strong> nilai bawahan maupun nilai mandiri yang sudah dikirimkan ke Department Head. Hal ini bertujuan melindungi konsistensi penilaian manajerial departemen.
        </div>
    </div>

    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:10px; margin-top:8px;">
        <div style="font-size:10px; font-weight:800; color:#0f172a; margin-bottom:6px;">PROSEDUR RESMI PENGAJUAN REVISI NILAI:</div>
        <div style="display:flex; flex-direction:column; gap:6px; font-size:9px;">
            <div style="display:flex; gap:8px;">
                <span class="num-callout" style="background:#475569; width:16px; height:16px; font-size:9px;">1</span>
                <span>Laporkan kepada <strong>Department Head</strong> dan <strong>Admin/Superadmin</strong> mengenai butir skor yang keliru diinput.</span>
            </div>
            <div style="display:flex; gap:8px;">
                <span class="num-callout" style="background:#475569; width:16px; height:16px; font-size:9px;">2</span>
                <span>Admin akan membuka gembok revisi dokumen karyawan tersebut di menu <em>Pusat Revisi Nilai</em>.</span>
            </div>
            <div style="display:flex; gap:8px;">
                <span class="num-callout" style="background:#475569; width:16px; height:16px; font-size:9px;">3</span>
                <span>Form penilaian akan kembali berstatus dapat diedit sehingga Section Head dapat memperbarui skor dan menandatangani ulang.</span>
            </div>
        </div>
    </div>

    {get_footer("Section Head", 3, 3)}
</div>

</body>
</html>
"""
    generate_pdf(html, OUTPUT_DIR / "Panduan_02_Section_Head.pdf")

print("Section Head guide builder ready.")
# ==============================================================================
# 3. PANDUAN KARYAWAN GOLONGAN KJ 5 KE ATAS (FORM 07-006 MANDIRI)
# ==============================================================================
def build_guide_kj5():
    html = f"""<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Panduan Penggunaan - Karyawan KJ 5 Ke Atas</title>
    <style>{COMMON_CSS}</style>
</head>
<body>

<!-- HALAMAN 1: Akses Login & Desain Halaman KJ 5+ -->
<div class="page">
    {get_header("PANDUAN OPERASIONAL APLIKASI", "ROLE: KARYAWAN KJ 5 KE ATAS", "SOP-PA-03-KJ5")}
    
    <div style="background:#fdf2f2; border:1px solid #fecaca; border-radius:6px; padding:10px 14px; margin-bottom:12px; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <div style="font-size:12px; font-weight:800; color:#7b181b;">PANDUAN PENGGUNAAN PERFORMANCE APPRAISAL</div>
            <div style="font-size:9.5px; color:#475569; margin-top:2px;">Petunjuk Penilaian Mandiri Karyawan Berkategori Golongan / KJ 5 Ke Atas (Form 07-006)</div>
        </div>
        <div style="text-align:right;">
            <span style="font-size:8.5px; background:#ffffff; border:1px solid #fca5a5; color:#7b181b; font-weight:700; padding:3px 8px; border-radius:4px;">
                Link: https://performanceappraisal.vercel.app/
            </span>
        </div>
    </div>

    <div class="section-title">
        <span>BAGIAN 1: SASARAN PENGGUNA & PROSEDUR LOGIN</span>
        <span style="font-size:8.5px; font-weight:600; color:#64748b;">Standar Akses</span>
    </div>
    <div class="section-sub">Panduan ini berlaku untuk seluruh posisi dengan <strong>KJ &ge; 5</strong>: GOL 4 NON KASIE, SENIOR STAFF, STAFF (KJ 5+), OPERATOR (Origin: Staff KJ5), dll.</div>

    {render_login_mockup("Karyawan KJ 5+")}
    {render_login_explanation()}

    <div class="info-alert" style="margin-top:10px;">
        <span style="font-size:14px; font-weight:bold;">ℹ</span>
        <div>
            <strong>Desain Antarmuka Menyerupai Section Head:</strong><br>
            Untuk memberikan pengalaman kerja yang profesional dan setara, seluruh karyawan dengan KJ 5 ke atas mendapatkan akses antarmuka lengkap seperti Section Head yang mencakup 5 menu utama: <strong>Dashboard, Data Karyawan, Form Penilaian Mandiri, Riwayat Penilaian, dan Pusat Laporan</strong>.
        </div>
    </div>

    {get_footer("Karyawan KJ 5+", 1, 3)}
</div>

<!-- HALAMAN 2: Pengisian Form 07-006 Penilaian Mandiri -->
<div class="page">
    {get_header("PENILAIAN MANDIRI (FORM 07-006)", "ROLE: KARYAWAN KJ 5 KE ATAS", "SOP-PA-03-KJ5")}

    <div class="section-title">
        <span>BAGIAN 2: LANGKAH PENGISIAN FORM 07-006 PENILAIAN MANDIRI</span>
        <span style="font-size:8.5px; font-weight:600; color:#64748b;">Self-Appraisal 4 Aspek</span>
    </div>
    <div class="section-sub">Evaluasi pencapaian kerja mandiri Anda secara objektif berdasarkan target tahun berjalan.</div>

    <!-- Mockup Form 07-006 Mandiri -->
    <div class="browser-mockup">
        <div class="browser-bar">
            <div class="browser-dots">
                <div class="browser-dot dot-red"></div>
                <div class="browser-dot dot-yellow"></div>
                <div class="browser-dot dot-green"></div>
            </div>
            <div class="browser-url">https://performanceappraisal.vercel.app/penilaian/mandiri</div>
        </div>
        <div class="browser-body">
            <div class="mock-topbar">
                <span>FORMULIR PENILAIAN PRESTASI KERJA MANDIRI (KJ &ge; 5)</span>
                <span class="mock-badge bg-purple-soft" style="background:#ffffff; color:#6b21a8;">FORM 07-006</span>
            </div>

            <!-- Target 1: Identitas Diri -->
            <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:4px; padding:8px; margin-bottom:8px; position:relative;">
                <div style="font-size:9px; font-weight:800; color:#0f172a; margin-bottom:4px;">1. IDENTITAS KARYAWAN PENILAI MANDIRI</div>
                <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:6px; font-size:8px;">
                    <div><span style="color:#64748b;">NRP:</span> <strong>1414</strong></div>
                    <div><span style="color:#64748b;">Nama:</span> <strong>IQBAL YUSRA</strong></div>
                    <div><span style="color:#64748b;">Jabatan:</span> <strong>GOL 4 NON KASIE</strong></div>
                    <div><span style="color:#64748b;">Kategori:</span> <strong style="color:#7b181b;">KJ 5 (Form 07-006)</strong></div>
                </div>
                <div class="target-box" style="top:-2px; left:-2px; right:-2px; bottom:-2px;"></div>
                <div class="num-marker" style="top:50%; right:-10px;">1</div>
            </div>

            <!-- Target 2: 4 Aspek Form 07-006 -->
            <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:4px; padding:8px; margin-bottom:8px; position:relative;">
                <div style="font-size:9px; font-weight:800; color:#0f172a; margin-bottom:4px;">2. EVALUASI 4 PILAR PRESTASI KERJA MANDIRI</div>
                <table class="mock-table">
                    <thead>
                        <tr>
                            <th style="width:25px;">NO</th>
                            <th>ASPEK KOMPETENSI</th>
                            <th style="width:50px; text-align:center;">BOBOT</th>
                            <th style="width:80px; text-align:center;">SKOR MANDIRI</th>
                            <th style="width:60px; text-align:center;">NILAI BOBOT</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="text-align:center;">I</td>
                            <td><strong>HASIL KERJA</strong> (Target Individu & Proyek Selesai)</td>
                            <td style="text-align:center;">30%</td>
                            <td style="text-align:center; background:#f5f3ff; font-weight:bold; color:#6b21a8;">3.00 (Baik)</td>
                            <td style="text-align:center;">0.90</td>
                        </tr>
                        <tr>
                            <td style="text-align:center;">II</td>
                            <td><strong>PROSES KERJA</strong> (Inovasi, Kepatuhan Prosedur, Kaizen)</td>
                            <td style="text-align:center;">25%</td>
                            <td style="text-align:center; background:#f5f3ff; font-weight:bold; color:#6b21a8;">3.00 (Baik)</td>
                            <td style="text-align:center;">0.75</td>
                        </tr>
                        <tr>
                            <td style="text-align:center;">III</td>
                            <td><strong>SIKAP KERJA</strong> (Integritas, Kerjasama Tim, Leadership)</td>
                            <td style="text-align:center;">20%</td>
                            <td style="text-align:center; background:#f5f3ff; font-weight:bold; color:#6b21a8;">4.00 (Sangat Baik)</td>
                            <td style="text-align:center;">0.80</td>
                        </tr>
                        <tr>
                            <td style="text-align:center;">IV</td>
                            <td><strong>MAN MANAGEMENT</strong> (Koordinasi & Pengembangan Staf)</td>
                            <td style="text-align:center;">25%</td>
                            <td style="text-align:center; background:#f5f3ff; font-weight:bold; color:#6b21a8;">3.00 (Baik)</td>
                            <td style="text-align:center;">0.75</td>
                        </tr>
                        <tr style="background:#f1f5f9; font-weight:bold;">
                            <td colspan="4" style="text-align:right;">TOTAL SKOR MANDIRI (GRADE B):</td>
                            <td style="text-align:center; color:#15803d; font-size:9px;">3.20 (B)</td>
                        </tr>
                    </tbody>
                </table>
                <div class="target-box" style="top:-2px; left:-2px; right:-2px; bottom:-2px;"></div>
                <div class="num-marker" style="top:50%; right:-10px;">2</div>
            </div>

            <!-- Target 3: Bukti & TTD -->
            <div style="display:flex; gap:10px; margin-bottom:8px;">
                <div style="flex:1; background:#ffffff; border:1px solid #cbd5e1; border-radius:4px; padding:6px; position:relative;">
                    <div style="font-size:8.5px; font-weight:800; color:#0f172a; margin-bottom:3px;">3. DESKRIPSI CAPAIAN & BUKTI PRESTASI</div>
                    <textarea readonly style="width:100%; height:32px; font-size:8px; border:1px solid #e2e8f0; border-radius:3px; padding:3px; resize:none;">Telah menyelesaikan project efisiensi sistem internal sesuai milestone Q3.</textarea>
                    <div class="target-box" style="top:-2px; left:-2px; right:-2px; bottom:-2px;"></div>
                    <div class="num-marker" style="top:50%; right:-10px;">3</div>
                </div>
                <div style="width:180px; background:#ffffff; border:1px solid #cbd5e1; border-radius:4px; padding:6px; position:relative;">
                    <div style="font-size:8.5px; font-weight:800; color:#0f172a; margin-bottom:3px;">4. PENGESAHAN TTD MANDIRI</div>
                    <div style="border:1px dashed #10b981; background:#f0fdf4; border-radius:4px; padding:4px; text-align:center;">
                        <span style="font-size:7.5px; color:#15803d; font-weight:bold;">✓ TTD MANDIRI RESMI</span>
                        <div style="font-size:8px; font-weight:800; color:#0f172a; margin-top:2px;">IQBAL YUSRA (NRP: 1414)</div>
                        <div style="font-size:7px; color:#64748b;">Karyawan KJ 5 Ke Atas</div>
                    </div>
                    <div class="target-box" style="top:-2px; left:-2px; right:-2px; bottom:-2px;"></div>
                    <div class="num-marker" style="top:50%; right:-10px;">4</div>
                </div>
            </div>

            <!-- Target 5: Kirim -->
            <div style="display:flex; justify-content:flex-end; gap:6px; position:relative;">
                <button class="btn-mock">Kirim Penilaian Mandiri ke Atasan &rarr;</button>
                <div class="target-box" style="top:-2px; left:-2px; right:-2px; bottom:-2px;"></div>
                <div class="num-marker" style="top:50%; right:-10px;">5</div>
            </div>
        </div>
    </div>

    <!-- Explanations -->
    <div class="steps-grid">
        <div class="step-card">
            <span class="num-callout">1</span>
            <div class="step-content">
                <h4>Verifikasi Identitas & Golongan KJ &ge; 5</h4>
                <p>Pastikan data diri Anda tertera benar dengan form penugasan <strong>Form 07-006</strong>.</p>
            </div>
        </div>
        <div class="step-card">
            <span class="num-callout">2</span>
            <div class="step-content">
                <h4>Input Nilai Mandiri pada 4 Pilar Evaluasi</h4>
                <p>Beri penilaian objektif pada: <strong>Hasil Kerja, Proses Kerja, Sikap Kerja, dan Man Management</strong>. Nilai total dan predikat Grade akan otomatis dihitung.</p>
            </div>
        </div>
        <div class="step-card">
            <span class="num-callout">3</span>
            <div class="step-content">
                <h4>Uraikan Bukti & Catatan Capaian Kerja</h4>
                <p>Tuliskan pencapaian kunci, keberhasilan proyek, atau inisiatif Kaizen yang telah Anda laksanakan selama periode penilaian.</p>
            </div>
        </div>
        <div class="step-card">
            <span class="num-callout">4</span>
            <div class="step-content">
                <h4>Bubuhi Tanda Tangan Digital Mandiri</h4>
                <p>Klik tombol pengesahan digital untuk memvalidasi bahwa penilaian mandiri ini diisi secara sadar dan akurat.</p>
            </div>
        </div>
        <div class="step-card success-border">
            <span class="num-callout" style="background:#10b981; box-shadow:0 0 0 2px #d1fae5;">5</span>
            <div class="step-content">
                <h4>Kirim Penilaian Mandiri ke Atasan Langsung</h4>
                <p>Setelah terkirim, penilaian mandiri Anda akan masuk ke antrean atasan langsung (Section Head / Department Head) untuk proses review dan docking.</p>
            </div>
        </div>
    </div>

    {get_footer("Karyawan KJ 5+", 2, 3)}
</div>

<!-- HALAMAN 3: Pemantauan Status & Prosedur Revisi -->
<div class="page">
    {get_header("MONITORING STATUS & PROSEDUR REVISI", "ROLE: KARYAWAN KJ 5 KE ATAS", "SOP-PA-03-KJ5")}

    <div class="section-title">
        <span>BAGIAN 3: MEMAHAMI STATUS ALUR PENILAIAN MANDIRI</span>
        <span style="font-size:8.5px; font-weight:600; color:#64748b;">Monitoring Lifecycle</span>
    </div>
    <div class="section-sub">Anda dapat memantau pergerakan berkas penilaian Anda melalui menu <strong>Riwayat Penilaian</strong>.</div>

    <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:8px; margin-bottom:12px;">
        <div style="background:#fdf4ff; border:1px solid #f0abfc; border-radius:5px; padding:8px; text-align:center;">
            <span class="mock-badge bg-purple-soft" style="font-size:8px;">Proses Penilaian Mandiri</span>
            <div style="font-size:8px; color:#475569; margin-top:4px;">Anda belum menyelesaikan pengisian / TTD Form 07-006.</div>
        </div>
        <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:5px; padding:8px; text-align:center;">
            <span class="mock-badge bg-blue-soft" style="font-size:8px;">Review Sect Head</span>
            <div style="font-size:8px; color:#475569; margin-top:4px;">Berkas sedang dievaluasi oleh Kepala Seksi Anda.</div>
        </div>
        <div style="background:#fef3c7; border:1px solid #fde68a; border-radius:5px; padding:8px; text-align:center;">
            <span class="mock-badge bg-amber-soft" style="font-size:8px;">Review Dept Head</span>
            <div style="font-size:8px; color:#475569; margin-top:4px;">Tahap docking nilai akhir oleh Department Head.</div>
        </div>
        <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:5px; padding:8px; text-align:center;">
            <span class="mock-badge bg-green-soft" style="font-size:8px;">✓ Selesai</span>
            <div style="font-size:8px; color:#475569; margin-top:4px;">Penilaian disetujui penuh & telah disahkan Div Head.</div>
        </div>
    </div>

    <div class="danger-alert">
        <span style="font-size:16px; font-weight:bold;">⛔</span>
        <div>
            <strong style="font-size:10px;">ATURAN PENGUNCIAN REVISI NILAI MANDIRI:</strong><br>
            Setelah Anda mengklik tombol <em>Kirim Penilaian Mandiri</em>, form Anda otomatis terkunci dan tidak dapat diubah sendiri. Kebijakan ini diterapkan agar nilai mandiri yang sudah mulai dievaluasi oleh atasan tidak mengalami perubahan sepihak di tengah jalan.
        </div>
    </div>

    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:10px; margin-top:10px;">
        <div style="font-size:10px; font-weight:800; color:#0f172a; margin-bottom:6px;">CARA MEMPERBAIKI KESALAHAN INPUT NILAI MANDIRI:</div>
        <div style="display:flex; flex-direction:column; gap:6px; font-size:9px;">
            <div style="display:flex; gap:8px;">
                <span class="num-callout" style="background:#475569; width:16px; height:16px; font-size:9px;">1</span>
                <span>Hubungi <strong>Administrator / Superadmin</strong> melalui saluran WhatsApp / Email HR resmi.</span>
            </div>
            <div style="display:flex; gap:8px;">
                <span class="num-callout" style="background:#475569; width:16px; height:16px; font-size:9px;">2</span>
                <span>Sebutkan <strong>NRP, Nama</strong>, dan rincian butir aspek yang salah diinput.</span>
            </div>
            <div style="display:flex; gap:8px;">
                <span class="num-callout" style="background:#475569; width:16px; height:16px; font-size:9px;">3</span>
                <span>Admin akan membuka gembok revisi. Status form Anda akan kembali menjadi <em>Proses Penilaian Mandiri</em> sehingga Anda dapat memperbaikinya.</span>
            </div>
        </div>
    </div>

    {get_footer("Karyawan KJ 5+", 3, 3)}
</div>

</body>
</html>
"""
    generate_pdf(html, OUTPUT_DIR / "Panduan_03_KJ5_Keatas_Mandiri.pdf")

print("KJ 5+ guide builder ready.")
# ==============================================================================
# 4. PANDUAN DEPARTMENT HEAD
# ==============================================================================
def build_guide_dept_head():
    html = f"""<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Panduan Penggunaan - Department Head</title>
    <style>{COMMON_CSS}</style>
</head>
<body>

<!-- HALAMAN 1: Akses Login & Tanggung Jawab Departemen -->
<div class="page">
    {get_header("PANDUAN OPERASIONAL APLIKASI", "ROLE: DEPARTMENT HEAD", "SOP-PA-04-DPT")}
    
    <div style="background:#fdf2f2; border:1px solid #fecaca; border-radius:6px; padding:10px 14px; margin-bottom:12px; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <div style="font-size:12px; font-weight:800; color:#7b181b;">PANDUAN PENGGUNAAN PERFORMANCE APPRAISAL</div>
            <div style="font-size:9.5px; color:#475569; margin-top:2px;">Petunjuk Teknis Penilaian, Docking Nilai, & Approval Kepala Departemen</div>
        </div>
        <div style="text-align:right;">
            <span style="font-size:8.5px; background:#ffffff; border:1px solid #fca5a5; color:#7b181b; font-weight:700; padding:3px 8px; border-radius:4px;">
                Link: https://performanceappraisal.vercel.app/
            </span>
        </div>
    </div>

    <div class="section-title">
        <span>BAGIAN 1: PROSEDUR LOGIN & OTORITAS MANAJERIAL</span>
        <span style="font-size:8.5px; font-weight:600; color:#64748b;">Akses Departemen</span>
    </div>
    <div class="section-sub">Department Head memiliki wewenang final docking dan penyeimbangan kuota nilai seluruh seksi dalam departemen.</div>

    {render_login_mockup("Department Head")}
    {render_login_explanation()}

    <div class="info-alert" style="margin-top:10px;">
        <span style="font-size:14px; font-weight:bold;">ℹ</span>
        <div>
            <strong>Otoritas Docking & Finalisasi Departemen:</strong><br>
            Department Head berwenang memvalidasi seluruh usulan nilai dari Foreman dan Section Head, melakukan penyesuaian (docking) grade prestasi kerja (S, A, B, C, K), memberikan catatan manajerial, serta menilai mandiri kinerjanya sendiri sebelum diteruskan ke Division Head.
        </div>
    </div>

    {get_footer("Department Head", 1, 3)}
</div>

<!-- HALAMAN 2: Menilai Bawahan & Tabel 11 Kolom -->
<div class="page">
    {get_header("EVALUASI BAWAHAN & FINAL DOCKING", "ROLE: DEPARTMENT HEAD", "SOP-PA-04-DPT")}

    <div class="section-title">
        <span>SKENARIO A: EVALUASI BAWAHAN DENGAN TABEL 11 KOLOM LENGKAP</span>
        <span style="font-size:8.5px; font-weight:600; color:#64748b;">Halaman /penilaian/dept</span>
    </div>
    <div class="section-sub">Tabel penilaian departemen menampilkan riwayat penilaian berjenjang dari Foreman, Mandiri, hingga Section Head.</div>

    <!-- Mockup Tabel 11 Kolom Department Head -->
    <div class="browser-mockup">
        <div class="browser-bar">
            <div class="browser-dots">
                <div class="browser-dot dot-red"></div>
                <div class="browser-dot dot-yellow"></div>
                <div class="browser-dot dot-green"></div>
            </div>
            <div class="browser-url">https://performanceappraisal.vercel.app/penilaian/dept</div>
        </div>
        <div class="browser-body">
            <!-- Filter Bar -->
            <div style="display:flex; justify-content:space-between; align-items:center; background:#ffffff; border:1px solid #cbd5e1; border-radius:4px; padding:5px 8px; margin-bottom:8px; position:relative;">
                <div style="display:flex; gap:8px; font-size:8px;">
                    <div><span style="color:#64748b;">SECTION:</span> <strong>Semua Seksi</strong></div>
                    <div><span style="color:#64748b;">POSITION:</span> <strong>Semua Posisi</strong></div>
                </div>
                <div class="target-box" style="top:-2px; left:-2px; right:-2px; bottom:-2px;"></div>
                <div class="num-marker" style="top:50%; right:-10px;">1</div>
            </div>

            <!-- 11 Columns Table -->
            <table class="mock-table" style="position:relative; font-size:7px;">
                <thead>
                    <tr style="background:#f1f5f9;">
                        <th style="width:16px;">NO</th>
                        <th style="width:35px;">NRP</th>
                        <th>NAMA & POSITION</th>
                        <th style="width:35px; text-align:center;">FORM</th>
                        <th style="width:65px;">HASIL FOREMAN</th>
                        <th style="width:65px;">HASIL MANDIRI</th>
                        <th style="width:65px;">HASIL SECT HEAD</th>
                        <th style="width:50px; text-align:center;">NILAI AKHIR</th>
                        <th style="width:75px;">CATATAN DEPT</th>
                        <th style="width:55px; text-align:center;">STATUS</th>
                        <th style="width:40px; text-align:center;">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Baris 1: Selesai (Paling Atas) -->
                    <tr style="background:#f8fafc;">
                        <td style="text-align:center;">1</td>
                        <td style="font-family:'JetBrains Mono'; font-weight:bold;">1149</td>
                        <td>
                            <strong>ROZALIA</strong><br>
                            <span style="color:#64748b;">SECTION HEAD GA</span>
                        </td>
                        <td style="text-align:center; font-weight:bold; color:#4338ca;">07-006</td>
                        <td style="text-align:center; color:#94a3b8;">-</td>
                        <td>
                            <span class="mock-badge bg-blue-soft">B (3.00)</span><br>
                            <span style="color:#15803d; font-size:6px;">✓ TTD Mandiri</span>
                        </td>
                        <td style="text-align:center; color:#94a3b8;">-</td>
                        <td style="text-align:center;">
                            <select style="font-size:7.5px; font-weight:bold; padding:1px 3px; border:1px solid #7b181b; border-radius:2px;">
                                <option selected>C</option>
                            </select>
                        </td>
                        <td style="font-size:6.5px; color:#475569;">Target terpenuhi baik.</td>
                        <td style="text-align:center;"><span class="mock-badge bg-green-soft">✓ Selesai</span></td>
                        <td style="text-align:center;"><button class="btn-mock" style="font-size:7px; padding:2px 4px;">Lihat</button></td>
                    </tr>

                    <!-- Baris 2: Siap Dinilai -->
                    <tr>
                        <td style="text-align:center;">2</td>
                        <td style="font-family:'JetBrains Mono'; font-weight:bold;">1414</td>
                        <td>
                            <strong>IQBAL YUSRA</strong><br>
                            <span style="color:#64748b;">GOL 4 NON KASIE &bull; KJ 5</span>
                        </td>
                        <td style="text-align:center; font-weight:bold; color:#4338ca;">07-006</td>
                        <td style="text-align:center; color:#94a3b8;">-</td>
                        <td>
                            <span class="mock-badge bg-purple-soft">B (3.20)</span><br>
                            <span style="color:#15803d; font-size:6px;">✓ TTD Mandiri</span>
                        </td>
                        <td>
                            <span class="mock-badge bg-blue-soft">B (3.15)</span><br>
                            <span style="color:#15803d; font-size:6px;">✓ TTD Kasie</span>
                        </td>
                        <td style="text-align:center;">
                            <select style="font-size:7.5px; font-weight:bold; padding:1px 3px; border:1px solid #cbd5e1; border-radius:2px;">
                                <option>-</option><option>S</option><option>A</option><option selected>B</option><option>C</option><option>K</option>
                            </select>
                        </td>
                        <td><input type="text" value="Disetujui Dept Head" readonly style="font-size:6.5px; width:100%; border:1px solid #cbd5e1; padding:1px 2px;"></td>
                        <td style="text-align:center;"><span class="mock-badge bg-amber-soft">🔔 Siap Dinilai</span></td>
                        <td style="text-align:center;"><button class="btn-mock" style="font-size:7px; padding:2px 4px;">Form &rarr;</button></td>
                    </tr>
                </tbody>
            </table>
            <!-- Markers across columns -->
            <div class="num-marker" style="top:48%; left:37%;">2</div>
            <div class="num-marker" style="top:48%; left:49%;">3</div>
            <div class="num-marker" style="top:48%; left:62%;">4</div>
            <div class="num-marker" style="top:48%; left:74%;">5</div>
            <div class="num-marker" style="top:48%; left:85%;">6</div>
        </div>
    </div>

    <!-- Explanations -->
    <div class="steps-grid">
        <div class="step-card">
            <span class="num-callout">1</span>
            <div class="step-content">
                <h4>Filter Seksi & Posisi</h4>
                <p>Pilih seksi atau posisi tertentu untuk memfilter tampilan daftar penilaian departemen.</p>
            </div>
        </div>
        <div class="step-card">
            <span class="num-callout">2</span>
            <div class="step-content">
                <h4>HASIL FOREMAN (Kolom 5)</h4>
                <p>Menampilkan nilai awal dari Foreman lini. Jika karyawan adalah Section Head atau staf tanpa foreman, kolom bernilai tanda strip (<code>-</code>).</p>
            </div>
        </div>
        <div class="step-card">
            <span class="num-callout">3</span>
            <div class="step-content">
                <h4>HASIL PENILAIAN MANDIRI (Kolom 6)</h4>
                <p>Khusus Form 07-006 (KJ 5+ dan Section Head): Menampilkan skor mandiri yang diinput sendiri oleh karyawan bersangkutan.</p>
            </div>
        </div>
        <div class="step-card">
            <span class="num-callout">4</span>
            <div class="step-content">
                <h4>HASIL SECTION HEAD (Kolom 7)</h4>
                <p>Menampilkan evaluasi dari Kepala Seksi. Khusus baris Section Head itu sendiri, kolom bernilai strip (<code>-</code>) karena langsung dinilai Dept Head.</p>
            </div>
        </div>
        <div class="step-card">
            <span class="num-callout">5</span>
            <div class="step-content">
                <h4>NILAI AKHIR DEPT (Kolom 8 - Dropdown Docking)</h4>
                <p>Pilih grade akhir departemen: <strong>S, A, B, C, atau K</strong>. Grade ini yang menjadi dasar docking kuota departemen.</p>
            </div>
        </div>
        <div class="step-card success-border">
            <span class="num-callout" style="background:#10b981; box-shadow:0 0 0 2px #d1fae5;">6</span>
            <div class="step-content">
                <h4>CATATAN DEPT HEAD & PENGURUTAN OTOMATIS</h4>
                <p>Tuliskan catatan arahan kinerja. Sistem otomatis mengurutkan karyawan yang <strong>sudah dinilai</strong> di baris paling atas agar monitoring efisien.</p>
            </div>
        </div>
    </div>

    {get_footer("Department Head", 2, 3)}
</div>

<!-- HALAMAN 3: Menilai Diri Sendiri & Alur ke Div Head -->
<div class="page">
    {get_header("MENILAI DIRI SENDIRI & SUBMIT DIVISI", "ROLE: DEPARTMENT HEAD", "SOP-PA-04-DPT")}

    <div class="section-title">
        <span>SKENARIO B: MENILAI DIRI SENDIRI (FORM 07-006-DH MANDIRI)</span>
        <span style="font-size:8.5px; font-weight:600; color:#64748b;">Self-Appraisal Dept Head</span>
    </div>
    <div class="section-sub">Sebagai pimpinan departemen, Department Head melakukan evaluasi mandiri atas pencapaian strategis departemennya.</div>

    <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:6px; padding:10px; margin-bottom:12px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
            <div style="font-size:10px; font-weight:800; color:#7b181b;">TAHAPAN PENILAIAN MANDIRI DEPT HEAD:</div>
            <span class="mock-badge bg-rose-soft" style="background:#ffe4e6; color:#9f1239; font-weight:900;">BARIS ANDA (FORM 07-006-DH)</span>
        </div>
        <div style="font-size:9px; color:#334155; line-height:1.45;">
            1. Temukan baris dengan badge <strong>ANDA</strong> pada tabel penilaian departemen.<br>
            2. Klik <strong>Form &rarr;</strong> untuk membuka lembar Form 07-006-DH.<br>
            3. Input skor evaluasi diri pada 4 aspek manajerial: Target Departemen, Continuous Improvement, Kepemimpinan, dan Pengelolaan Sumber Daya.<br>
            4. Bubuhi <strong>Tanda Tangan Digital Mandiri Dept Head</strong>.<br>
            5. Berkas akan otomatis mengalir ke <strong>Division Head</strong> untuk pengesahan tingkat divisi.
        </div>
    </div>

    <div class="section-title">
        <span>PENGESAHAN DOCKING & PENGIRIMAN KE DIVISION HEAD</span>
        <span style="font-size:8.5px; font-weight:600; color:#64748b;">Finalisasi Departemen</span>
    </div>

    <div class="browser-mockup">
        <div class="browser-bar">
            <div class="browser-dots"><div class="browser-dot dot-red"></div><div class="browser-dot dot-yellow"></div><div class="browser-dot dot-green"></div></div>
            <div class="browser-url">https://performanceappraisal.vercel.app/penilaian/dept/submit</div>
        </div>
        <div class="browser-body">
            <div style="display:flex; justify-content:space-between; align-items:center; background:#ffffff; border:1px solid #cbd5e1; border-radius:4px; padding:8px 12px;">
                <div>
                    <div style="font-size:9.5px; font-weight:800; color:#0f172a;">PENGESAHAN FINAL DOCKING DEPARTEMEN</div>
                    <div style="font-size:8px; color:#15803d; font-weight:bold; margin-top:2px;">✓ Seluruh 5 Anggota Departemen Selesai Dinilai</div>
                </div>
                <div style="display:flex; gap:8px;">
                    <button class="btn-mock-secondary">Simpan Draft</button>
                    <button class="btn-mock">Kirim Pengesahan ke Div Head &rarr;</button>
                </div>
            </div>
        </div>
    </div>

    <div class="info-alert" style="margin-top:10px;">
        <span style="font-size:16px; font-weight:bold;">ℹ</span>
        <div>
            <strong>Status Setelah Dikirim ke Division Head:</strong><br>
            Setelah seluruh nilai departemen disubmit, status alur berubah menjadi <code>Review Div Head</code>. Division Head akan melakukan telaah pengesahan menyeluruh sebelum sistem menandai status akhir sebagai <code>✓ Selesai</code>.
        </div>
    </div>

    {get_footer("Department Head", 3, 3)}
</div>

</body>
</html>
"""
    generate_pdf(html, OUTPUT_DIR / "Panduan_04_Department_Head.pdf")

print("Dept Head guide builder ready.")
# ==============================================================================
# 5. PANDUAN DIVISION HEAD
# ==============================================================================
def build_guide_div_head():
    html = f"""<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Panduan Penggunaan - Division Head</title>
    <style>{COMMON_CSS}</style>
</head>
<body>

<!-- HALAMAN 1: Akses Login & Otoritas Divisi -->
<div class="page">
    {get_header("PANDUAN OPERASIONAL APLIKASI", "ROLE: DIVISION HEAD", "SOP-PA-05-DIV")}
    
    <div style="background:#fdf2f2; border:1px solid #fecaca; border-radius:6px; padding:10px 14px; margin-bottom:12px; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <div style="font-size:12px; font-weight:800; color:#7b181b;">PANDUAN PENGGUNAAN PERFORMANCE APPRAISAL</div>
            <div style="font-size:9.5px; color:#475569; margin-top:2px;">Petunjuk Teknis Pengesahan & Approval Akhir Tingkat Divisi</div>
        </div>
        <div style="text-align:right;">
            <span style="font-size:8.5px; background:#ffffff; border:1px solid #fca5a5; color:#7b181b; font-weight:700; padding:3px 8px; border-radius:4px;">
                Link: https://performanceappraisal.vercel.app/
            </span>
        </div>
    </div>

    <div class="section-title">
        <span>BAGIAN 1: PROSEDUR LOGIN & PENGESAHAN TERTINGGI DIVISI</span>
        <span style="font-size:8.5px; font-weight:600; color:#64748b;">Akses Divisi</span>
    </div>
    <div class="section-sub">Division Head bertindak sebagai <strong>Penilai Akhir</strong> yang mengesahkan seluruh usulan nilai dari para Department Head di bawah divisinya.</div>

    {render_login_mockup("Division Head")}
    {render_login_explanation()}

    <div class="info-alert" style="margin-top:10px;">
        <span style="font-size:14px; font-weight:bold;">ℹ</span>
        <div>
            <strong>Karakteristik Khusus Halaman Division Head:</strong><br>
            Sesuai regulasi sistem, antarmuka Division Head dirancang bersih dan fokus pada pengesahan menyeluruh (tabel target kuota kaku per seksi dihilangkan) karena peran Division Head berfokus pada approval strategis, evaluasi lintas departemen, dan pengesahan status akhir <code>✓ Selesai</code>.
        </div>
    </div>

    {get_footer("Division Head", 1, 3)}
</div>

<!-- HALAMAN 2: Pengesahan & Approval Divisi -->
<div class="page">
    {get_header("PENGESAHAN & APPROVAL DIVISI", "ROLE: DIVISION HEAD", "SOP-PA-05-DIV")}

    <div class="section-title">
        <span>SKENARIO A: REVIEW & PENGESAHAN DOCKING SELURUH DEPARTEMEN</span>
        <span style="font-size:8.5px; font-weight:600; color:#64748b;">Halaman /penilaian/div</span>
    </div>
    <div class="section-sub">Meninjau hasil penilaian mandiri dan rekomendasi Department Head untuk disahkan menjadi nilai final.</div>

    <!-- Mockup Tabel Division Head -->
    <div class="browser-mockup">
        <div class="browser-bar">
            <div class="browser-dots"><div class="browser-dot dot-red"></div><div class="browser-dot dot-yellow"></div><div class="browser-dot dot-green"></div></div>
            <div class="browser-url">https://performanceappraisal.vercel.app/penilaian/div</div>
        </div>
        <div class="browser-body">
            <!-- Filter Departemen -->
            <div style="display:flex; justify-content:space-between; align-items:center; background:#ffffff; border:1px solid #cbd5e1; border-radius:4px; padding:5px 8px; margin-bottom:8px; position:relative;">
                <div style="font-size:8px;">
                    <span style="color:#64748b;">DIVISI:</span> <strong>PRODUCTION A DIVISION</strong> &bull; <span style="color:#64748b;">TOTAL:</span> <strong>38 Karyawan</strong>
                </div>
                <div class="target-box" style="top:-2px; left:-2px; right:-2px; bottom:-2px;"></div>
                <div class="num-marker" style="top:50%; right:-10px;">1</div>
            </div>

            <!-- Table Div Head -->
            <table class="mock-table" style="position:relative; font-size:7.5px;">
                <thead>
                    <tr style="background:#f1f5f9;">
                        <th style="width:20px; text-align:center;">NO</th>
                        <th style="width:40px;">NRP</th>
                        <th>NAMA LENGKAP & POSITION</th>
                        <th style="width:40px; text-align:center;">FORM</th>
                        <th style="width:85px;">HASIL PENILAIAN MANDIRI</th>
                        <th style="width:80px;">HASIL DEPT HEAD</th>
                        <th style="width:65px; text-align:center;">PENGESAHAN</th>
                        <th style="width:90px;">CATATAN DIV. HEAD</th>
                        <th style="width:60px; text-align:center;">STATUS ALUR</th>
                        <th style="width:40px; text-align:center;">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="text-align:center;">1</td>
                        <td style="font-family:'JetBrains Mono'; font-weight:bold;">18</td>
                        <td>
                            <strong>YUDI SANJAYA</strong><br>
                            <span style="color:#64748b; font-size:7px;">DEPARTMENT HEAD GA</span>
                        </td>
                        <td style="text-align:center; font-weight:bold; color:#4338ca;">07-006</td>
                        <td>
                            <span class="mock-badge bg-purple-soft">A (3.55)</span><br>
                            <span style="color:#15803d; font-size:6.5px;">✓ TTD Mandiri</span>
                        </td>
                        <td>
                            <span class="mock-badge bg-blue-soft">A (3.50)</span><br>
                            <span style="color:#15803d; font-size:6.5px;">✓ Rekomendasi</span>
                        </td>
                        <td style="text-align:center;">
                            <select style="font-size:7.5px; font-weight:bold; padding:1px 3px; border:1px solid #7b181b; border-radius:2px;">
                                <option selected>A</option>
                            </select>
                        </td>
                        <td><input type="text" value="Pencapaian GA sangat memuaskan." readonly style="font-size:7px; width:100%; border:1px solid #cbd5e1; padding:1px 2px;"></td>
                        <td style="text-align:center;"><span class="mock-badge bg-green-soft">✓ Selesai</span></td>
                        <td style="text-align:center;"><button class="btn-mock" style="font-size:7px; padding:2px 4px;">Lihat</button></td>
                    </tr>
                </tbody>
            </table>
            <!-- Markers on headers & inputs -->
            <div class="num-marker" style="top:48%; left:38%;">2</div>
            <div class="num-marker" style="top:48%; left:51%;">3</div>
            <div class="num-marker" style="top:48%; left:65%;">4</div>
            <div class="num-marker" style="top:48%; left:79%;">5</div>
            <div class="num-marker" style="top:48%; left:93%;">6</div>
        </div>
    </div>

    <!-- Explanations -->
    <div class="steps-grid">
        <div class="step-card">
            <span class="num-callout">1</span>
            <div class="step-content">
                <h4>Filter Departemen Divisi</h4>
                <p>Pilih departemen tertentu di bawah divisi Anda atau tampilkan seluruh departemen untuk melakukan pengesahan terpadu.</p>
            </div>
        </div>
        <div class="step-card">
            <span class="num-callout">2</span>
            <div class="step-content">
                <h4>HASIL PENILAIAN MANDIRI (Kolom 5)</h4>
                <p>Khusus pada halaman Division Head, kolom ini menampilkan hasil penilaian mandiri staf KJ 5+ dan para kepala departemen.</p>
            </div>
        </div>
        <div class="step-card">
            <span class="num-callout">3</span>
            <div class="step-content">
                <h4>HASIL DEPT HEAD (Kolom 6)</h4>
                <p>Menampilkan nilai docking dan catatan usulan yang telah disahkan oleh Department Head bersangkutan.</p>
            </div>
        </div>
        <div class="step-card">
            <span class="num-callout">4</span>
            <div class="step-content">
                <h4>PENGESAHAN DIV. HEAD (Kolom 7)</h4>
                <p>Pilih persetujuan grade final tingkat divisi (S, A, B, C, K) untuk mengunci hasil penilaian akhir karyawan.</p>
            </div>
        </div>
        <div class="step-card">
            <span class="num-callout">5</span>
            <div class="step-content">
                <h4>CATATAN DIV. HEAD (Kolom 8)</h4>
                <p>Berikan arahan strategis, apresiasi, atau masukan pengembangan divisi bagi karyawan atau departemen terkait.</p>
            </div>
        </div>
        <div class="step-card success-border">
            <span class="num-callout" style="background:#10b981; box-shadow:0 0 0 2px #d1fae5;">6</span>
            <div class="step-content">
                <h4>Pengesahan Final & Status "✓ Selesai"</h4>
                <p>Setelah pengesahan disubmit, sistem akan menandai status alur menjadi <code>✓ Selesai</code> dan nilai masuk ke arsip laporan resmi PT Musashi Auto Parts Indonesia.</p>
            </div>
        </div>
    </div>

    {get_footer("Division Head", 2, 3)}
</div>

<!-- HALAMAN 3: Menilai Diri Sendiri & Laporan Divisi -->
<div class="page">
    {get_header("MENILAI DIRI SENDIRI & LAPORAN DIVISI", "ROLE: DIVISION HEAD", "SOP-PA-05-DIV")}

    <div class="section-title">
        <span>SKENARIO B: MENILAI DIRI SENDIRI (FORM 07-006-DIV MANDIRI)</span>
        <span style="font-size:8.5px; font-weight:600; color:#64748b;">Executive Self-Appraisal</span>
    </div>
    <div class="section-sub">Sebagai pimpinan divisi, Division Head mengisi evaluasi mandiri pencapaian strategis tingkat direksi.</div>

    <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:6px; padding:10px; margin-bottom:12px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
            <div style="font-size:10px; font-weight:800; color:#7b181b;">TAHAPAN EVALUASI MANDIRI DIVISION HEAD:</div>
            <span class="mock-badge bg-rose-soft" style="background:#ffe4e6; color:#9f1239; font-weight:900;">FORM 07-006-DIV</span>
        </div>
        <div style="font-size:9px; color:#334155; line-height:1.45;">
            1. Buka lembar evaluasi mandiri Form 07-006-DIV.<br>
            2. Isi skor penilaian pada aspek: Sasaran Strategis Divisi, Efisiensi Biaya (Cost Reduction), Pengembangan SDM Divisi, dan Keselamatan Kerja (Safety Zero Accident).<br>
            3. Bubuhkan <strong>Tanda Tangan Digital Pengesahan</strong>.<br>
            4. Berkas penilaian mandiri Anda otomatis tersimpan dalam rekapitulasi eksekutif perusahaan.
        </div>
    </div>

    <div class="section-title">
        <span>MONITORING REKAPITULASI DIVISI & UNDUH LAPORAN</span>
        <span style="font-size:8.5px; font-weight:600; color:#64748b;">Pusat Laporan & Rekap</span>
    </div>

    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:10px;">
        <div style="font-size:9.5px; font-weight:800; color:#0f172a; margin-bottom:6px;">KETENTUAN PENGUNDUHAN LAPORAN DIVISI:</div>
        <div style="display:flex; flex-direction:column; gap:6px; font-size:9px;">
            <div style="display:flex; gap:8px;">
                <span class="num-callout" style="background:#10b981; width:16px; height:16px; font-size:9px;">✓</span>
                <span><strong>Ekspor Excel & PDF:</strong> Division Head dapat mengunduh rekap nilai lengkap satu divisi.</span>
            </div>
            <div style="display:flex; gap:8px;">
                <span class="num-callout" style="background:#10b981; width:16px; height:16px; font-size:9px;">✓</span>
                <span><strong>Filter Data Bernilai:</strong> Sesuai logika sistem, file yang diunduh hanya menyertakan karyawan yang <strong>sudah dinilai</strong> sehingga berkas laporan bersih dan siap diajukan ke Direksi/HR.</span>
            </div>
        </div>
    </div>

    {get_footer("Division Head", 3, 3)}
</div>

</body>
</html>
"""
    generate_pdf(html, OUTPUT_DIR / "Panduan_05_Division_Head.pdf")

print("Division Head guide builder ready.")


# ==============================================================================
# 6. PANDUAN ADMINISTRATOR / SUPERADMIN
# ==============================================================================
def build_guide_admin():
    html = f"""<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Panduan Penggunaan - Administrator / Superadmin</title>
    <style>{COMMON_CSS}</style>
</head>
<body>

<!-- HALAMAN 1: Akses Login & Dashboard Pengendalian -->
<div class="page">
    {get_header("PANDUAN OPERASIONAL APLIKASI", "ROLE: ADMINISTRATOR / SUPERADMIN", "SOP-PA-06-ADM")}
    
    <div style="background:#fdf2f2; border:1px solid #fecaca; border-radius:6px; padding:10px 14px; margin-bottom:12px; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <div style="font-size:12px; font-weight:800; color:#7b181b;">PANDUAN PENGGUNAAN PERFORMANCE APPRAISAL</div>
            <div style="font-size:9.5px; color:#475569; margin-top:2px;">Petunjuk Pengelolaan Sistem, Buka Kunci Revisi Nilai, & Pusat Laporan</div>
        </div>
        <div style="text-align:right;">
            <span style="font-size:8.5px; background:#ffffff; border:1px solid #fca5a5; color:#7b181b; font-weight:700; padding:3px 8px; border-radius:4px;">
                Link: https://performanceappraisal.vercel.app/
            </span>
        </div>
    </div>

    <div class="section-title">
        <span>BAGIAN 1: PROSEDUR LOGIN & KONTROL SISTEM TERPUSAT</span>
        <span style="font-size:8.5px; font-weight:600; color:#64748b;">Hak Akses Penuh</span>
    </div>
    <div class="section-sub">Superadmin / Admin HR & IT bertanggung jawab atas kelancaran operasional dan integritas data penilaian seluruh perusahaan.</div>

    {render_login_mockup("Administrator")}
    {render_login_explanation()}

    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:8px 12px; margin-top:10px;">
        <div style="font-size:9.5px; font-weight:800; color:#0f172a; margin-bottom:4px;">5 HAK AKSES UTAMA ADMINISTRATOR / SUPERADMIN:</div>
        <div style="display:grid; grid-template-columns: repeat(5, 1fr); gap:6px; font-size:8px;">
            <div style="background:white; border:1px solid #cbd5e1; border-radius:4px; padding:5px; text-align:center;">
                <strong>1. Monitoring Global</strong><br><span style="color:#64748b;">Pantau semua divisi</span>
            </div>
            <div style="background:#fef2f2; border:1px solid #fca5a5; border-radius:4px; padding:5px; text-align:center; color:#7b181b;">
                <strong>2. Revisi Nilai</strong><br><span>Buka kunci koreksi</span>
            </div>
            <div style="background:white; border:1px solid #cbd5e1; border-radius:4px; padding:5px; text-align:center;">
                <strong>3. Manajemen Akun</strong><br><span style="color:#64748b;">Kelola pengguna</span>
            </div>
            <div style="background:white; border:1px solid #cbd5e1; border-radius:4px; padding:5px; text-align:center;">
                <strong>4. Sinkronisasi Data</strong><br><span style="color:#64748b;">Update master sheet</span>
            </div>
            <div style="background:white; border:1px solid #cbd5e1; border-radius:4px; padding:5px; text-align:center;">
                <strong>5. Pusat Laporan</strong><br><span style="color:#64748b;">Export Excel & PDF</span>
            </div>
        </div>
    </div>

    {get_footer("Administrator", 1, 3)}
</div>

<!-- HALAMAN 2: Manajemen Revisi Nilai -->
<div class="page">
    {get_header("MANAJEMEN REVISI NILAI RESMI", "ROLE: ADMINISTRATOR / SUPERADMIN", "SOP-PA-06-ADM")}

    <div class="section-title">
        <span>FITUR KUNCI: PEMBUKAAN KUNCI REVISI NILAI (KONFIRMASI ADMIN)</span>
        <span style="font-size:8.5px; font-weight:600; color:#64748b;">Menu /admin/revisi</span>
    </div>
    <div class="section-sub">Sesuai aturan sistem, jika penilai (Foreman/Sect Head/Staf KJ 5+) salah input skor, pembukaan form wajib melalui konfirmasi Admin.</div>

    <!-- Mockup Modal Revisi Nilai -->
    <div class="browser-mockup">
        <div class="browser-bar">
            <div class="browser-dots"><div class="browser-dot dot-red"></div><div class="browser-dot dot-yellow"></div><div class="browser-dot dot-green"></div></div>
            <div class="browser-url">https://performanceappraisal.vercel.app/admin/revisi-nilai</div>
        </div>
        <div class="browser-body">
            <!-- Modal Box inside Mockup -->
            <div style="max-width:420px; margin:0 auto; background:#ffffff; border:1px solid #cbd5e1; border-radius:6px; padding:12px; box-shadow:0 4px 12px rgba(0,0,0,0.1); position:relative;">
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0; padding-bottom:6px; margin-bottom:8px;">
                    <div style="font-weight:800; font-size:10px; color:#7b181b;">🔐 BUKA GEMBOK REVISI PENILAIAN KARYAWAN</div>
                    <span class="mock-badge bg-rose-soft" style="font-size:7px;">OTORITAS ADMIN</span>
                </div>

                <!-- Target 1: Pencarian Dokumen -->
                <div style="margin-bottom:6px; position:relative;">
                    <label style="font-size:7.5px; font-weight:700; color:#334155; display:block; margin-bottom:2px;">1. CARI KARYAWAN (NRP / NAMA)</label>
                    <input type="text" value="204512 - AHMAD KURNIAWAN (Machining Line A)" readonly style="width:100%; font-size:8px; padding:3px 6px; border:1px solid #cbd5e1; border-radius:3px;">
                    <div class="target-box" style="top:-2px; left:-2px; right:-2px; bottom:-2px;"></div>
                    <div class="num-marker" style="top:50%; right:-10px;">1</div>
                </div>

                <!-- Target 2: Pilihan Level Revisi -->
                <div style="margin-bottom:6px; position:relative;">
                    <label style="font-size:7.5px; font-weight:700; color:#334155; display:block; margin-bottom:2px;">2. TAHAPAN PENILAIAN YANG DIIZINKAN DIUBAH</label>
                    <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:4px; font-size:7.5px;">
                        <label style="background:#fef2f2; border:1px solid #fca5a5; padding:3px; border-radius:3px; display:block;"><input type="checkbox" checked readonly> Foreman</label>
                        <label style="background:#f8fafc; border:1px solid #cbd5e1; padding:3px; border-radius:3px; display:block;"><input type="checkbox" readonly> Sect Head</label>
                        <label style="background:#f8fafc; border:1px solid #cbd5e1; padding:3px; border-radius:3px; display:block;"><input type="checkbox" readonly> Mandiri</label>
                    </div>
                    <div class="target-box" style="top:-2px; left:-2px; right:-2px; bottom:-2px;"></div>
                    <div class="num-marker" style="top:50%; right:-10px;">2</div>
                </div>

                <!-- Target 3: Catatan Alasan Revisi -->
                <div style="margin-bottom:8px; position:relative;">
                    <label style="font-size:7.5px; font-weight:700; color:#334155; display:block; margin-bottom:2px;">3. ALASAN REVISI (AUDIT TRAIL HR)</label>
                    <textarea readonly style="width:100%; height:28px; font-size:7.5px; border:1px solid #cbd5e1; border-radius:3px; padding:3px; resize:none;">Koreksi salah input skor Sikap Kerja oleh Foreman Bambang Irawan.</textarea>
                    <div class="target-box" style="top:-2px; left:-2px; right:-2px; bottom:-2px;"></div>
                    <div class="num-marker" style="top:50%; right:-10px;">3</div>
                </div>

                <!-- Target 4: Tombol Buka Kunci -->
                <div style="display:flex; justify-content:flex-end; gap:6px; position:relative;">
                    <button class="btn-mock-secondary" style="font-size:7.5px;">Batal</button>
                    <button class="btn-mock" style="background:#dc2626; font-size:7.5px;">Buka Gembok Revisi &rarr;</button>
                    <div class="target-box" style="top:-2px; left:-2px; right:-2px; bottom:-2px;"></div>
                    <div class="num-marker" style="top:50%; right:-10px;">4</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Explanations -->
    <div class="steps-grid">
        <div class="step-card">
            <span class="num-callout">1</span>
            <div class="step-content">
                <h4>Cari Data Karyawan Bersangkutan</h4>
                <p>Ketikkan NRP atau nama karyawan yang diajukan untuk revisi skor oleh penilai lini / kepala seksi.</p>
            </div>
        </div>
        <div class="step-card">
            <span class="num-callout">2</span>
            <div class="step-content">
                <h4>Tentukan Tahapan Penilaian yang Dibuka</h4>
                <p>Pilih secara spesifik level mana yang diizinkan untuk direvisi: <strong>Level Foreman, Level Section Head, atau Level Mandiri</strong>. Level lain yang tidak dipilih tetap terkunci aman.</p>
            </div>
        </div>
        <div class="step-card">
            <span class="num-callout">3</span>
            <div class="step-content">
                <h4>Isi Alasan Revisi untuk Rekam Jejak Audit</h4>
                <p>Wajib mengisi alasan permohonan revisi secara jelas. Catatan ini tersimpan permanen dalam riwayat sistem untuk keperluan audit HR management.</p>
            </div>
        </div>
        <div class="step-card success-border">
            <span class="num-callout" style="background:#10b981; box-shadow:0 0 0 2px #d1fae5;">4</span>
            <div class="step-content">
                <h4>Eksekusi "Buka Gembok Revisi"</h4>
                <p>Klik tombol konfirmasi. Status form karyawan akan langsung terbuka kembali bagi penilai terkait untuk menginput ulang skor dan menandatangani ulang.</p>
            </div>
        </div>
    </div>

    {get_footer("Administrator", 2, 3)}
</div>

<!-- HALAMAN 3: Manajemen Akun & Pusat Laporan -->
<div class="page">
    {get_header("MANAJEMEN AKUN & PUSAT LAPORAN", "ROLE: ADMINISTRATOR / SUPERADMIN", "SOP-PA-06-ADM")}

    <div class="section-title">
        <span>PUSAT LAPORAN & KETENTUAN EKSPOR EXCEL & PDF</span>
        <span style="font-size:8.5px; font-weight:600; color:#64748b;">Halaman /laporan</span>
    </div>
    <div class="section-sub">Aturan resmi pengunduhan data rekapitulasi penilaian kinerja PT Musashi Auto Parts Indonesia:</div>

    <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:6px; padding:10px; margin-bottom:12px;">
        <div style="font-size:10px; font-weight:800; color:#7b181b; margin-bottom:6px;">LOGIKA EKSPOR RESMI (EXCEL & PDF):</div>
        <div style="display:flex; flex-direction:column; gap:6px; font-size:9px; color:#334155;">
            <div style="display:flex; gap:8px;">
                <span class="num-callout" style="background:#10b981; width:16px; height:16px; font-size:9px;">1</span>
                <span><strong>Hanya Data yang Sudah Dinilai:</strong> Berkas Excel dan PDF yang diunduh hanya menyertakan karyawan yang <strong>sudah memiliki nilai</strong> (tidak menyertakan baris kosong yang belum dinilai).</span>
            </div>
            <div style="display:flex; gap:8px;">
                <span class="num-callout" style="background:#10b981; width:16px; height:16px; font-size:9px;">2</span>
                <span><strong>Urutan Data Paling Atas:</strong> Pada tabel tampilan layar maupun berkas unduhan, karyawan yang sudah selesai dinilai otomatis disusun di baris teratas.</span>
            </div>
            <div style="display:flex; gap:8px;">
                <span class="num-callout" style="background:#10b981; width:16px; height:16px; font-size:9px;">3</span>
                <span><strong>Otomasi QR Code Verifikasi:</strong> Dokumen cetak PDF dilengkapi QR Code resmi yang langsung mendeteksi sistem <em>PerformanceAppraisal</em>.</span>
            </div>
        </div>
    </div>

    <div class="section-title">
        <span>MANAJEMEN PENGGUNA & SINKRONISASI DATA KARYAWAN</span>
        <span style="font-size:8.5px; font-weight:600; color:#64748b;">User & Data Master Management</span>
    </div>

    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
        <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:5px; padding:8px;">
            <strong style="font-size:9px; color:#0f172a;">Manajemen Akun Pengguna:</strong>
            <ul style="margin:4px 0 0 0; padding-left:14px; font-size:8px; color:#475569; line-height:1.4;">
                <li>Reset kata sandi jika ada pengguna yang lupa password.</li>
                <li>Pengaturan role: Foreman, Section Head, KJ 5+, Dept Head, Div Head.</li>
                <li>Aktivasi atau penonaktifan akun karyawan non-aktif.</li>
            </ul>
        </div>
        <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:5px; padding:8px;">
            <strong style="font-size:9px; color:#0f172a;">Sinkronisasi Data Karyawan:</strong>
            <ul style="margin:4px 0 0 0; padding-left:14px; font-size:8px; color:#475569; line-height:1.4;">
                <li>Sinkronisasi mutasi jabatan, departemen, dan seksi.</li>
                <li>Pemetaan atasan langsung (NRP Foreman & Section Head).</li>
                <li>Penyesuaian form otomatis sesuai golongan KJ (KJ &ge; 5 = Form 07-006).</li>
            </ul>
        </div>
    </div>

    {get_footer("Administrator", 3, 3)}
</div>

</body>
</html>
"""
    generate_pdf(html, OUTPUT_DIR / "Panduan_06_Admin_Superadmin.pdf")

print("Admin guide builder ready.")

if __name__ == "__main__":
    print("=" * 60)
    print("START GENERATING 6 OFFICIAL USER GUIDES (PDF)")
    print("=" * 60)
    build_guide_foreman()
    build_guide_section_head()
    build_guide_kj5()
    build_guide_dept_head()
    build_guide_div_head()
    build_guide_admin()
    print("=" * 60)
    print("ALL 6 PDF GUIDES GENERATED SUCCESSFULLY IN:", OUTPUT_DIR)
    print("=" * 60)
