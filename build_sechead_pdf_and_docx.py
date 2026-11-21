import os
import base64
import subprocess
import shutil
import time
from docx import Document
from docx.shared import Inches, Pt, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.table import WD_TABLE_ALIGNMENT
from docx.oxml import parse_xml
from docx.oxml.ns import nsdecls

IMG_DIR = r"C:\Users\USER\Music\E-Approval\storage\app\sechead_annotated"
OUT_HTML = r"C:\Users\USER\Music\E-Approval\storage\app\panduan_sechead.html"
TEMP_PDF = r"C:\Users\USER\Music\E-Approval\storage\app\panduan_sechead_temp.pdf"
OUT_PDF = r"C:\Users\USER\Music\E-Approval\public\panduan\Panduan_Lengkap_Section_Head.pdf"
OUT_PDF_USER = r"C:\Users\USER\Music\Dashboard\PANDUAN_PDF\Panduan_Lengkap_Section_Head.pdf"
OUT_DOCX = r"C:\Users\USER\Music\E-Approval\public\panduan\Panduan_Lengkap_Section_Head.docx"
OUT_DOCX_USER = r"C:\Users\USER\Music\Dashboard\PANDUAN_PDF\Panduan_Lengkap_Section_Head.docx"

os.makedirs(os.path.dirname(OUT_PDF), exist_ok=True)
os.makedirs(os.path.dirname(OUT_PDF_USER), exist_ok=True)

def b64_img(filename):
    path = os.path.join(IMG_DIR, filename)
    if not os.path.exists(path):
        print(f"Warning: {path} not found")
        return ""
    with open(path, "rb") as f:
        data = base64.b64encode(f.read()).decode("utf-8")
    return f"data:image/png;base64,{data}"

img_01 = b64_img("sh_01_login.png")
img_02 = b64_img("sh_02_dashboard.png")
img_03 = b64_img("sh_03_form_07006_mandiri.png")
img_04 = b64_img("sh_04_karyawan.png")
img_05 = b64_img("sh_05_form_kj13_bawahan.png")
img_06 = b64_img("sh_06_form_kj45_bawahan.png")
img_07 = b64_img("sh_07_riwayat.png")

# ==========================================
# 1. BUILD HTML FOR PDF (8 PAGES)
# ==========================================
html_content = f"""<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Buku Panduan Section Head - E-Performance Appraisal</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
  
  @page {{
    size: A4 portrait;
    margin: 10mm 14mm 12mm 14mm;
  }}
  
  * {{
    box-sizing: border-box;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }}
  
  body {{
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    color: #1e293b;
    margin: 0;
    padding: 0;
    font-size: 10pt;
    line-height: 1.4;
    background-color: #ffffff;
  }}
  
  .page {{
    page-break-after: always;
    height: 272mm;
    position: relative;
    padding-bottom: 24px;
    display: flex;
    flex-direction: column;
  }}
  
  .page:last-child {{
    page-break-after: avoid;
  }}
  
  .doc-header {{
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 2.5px solid #0f2744;
    padding-bottom: 6px;
    margin-bottom: 12px;
  }}
  
  .doc-header .company {{
    font-size: 11pt;
    font-weight: 800;
    color: #0f2744;
    letter-spacing: 0.5px;
  }}
  
  .doc-header .tag {{
    font-size: 8pt;
    font-weight: 700;
    color: #ffffff;
    background-color: #c5221f;
    padding: 2px 8px;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }}
  
  .doc-footer {{
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid #cbd5e1;
    padding-top: 5px;
    font-size: 8pt;
    color: #64748b;
  }}
  
  .step-badge {{
    display: inline-block;
    background-color: #0f2744;
    color: #ffffff;
    font-weight: 700;
    font-size: 8.5pt;
    padding: 2px 8px;
    border-radius: 4px;
    margin-bottom: 4px;
    letter-spacing: 0.5px;
    width: fit-content;
  }}
  
  h1.page-title {{
    font-size: 15pt;
    font-weight: 800;
    color: #0f2744;
    margin: 0 0 2px 0;
    line-height: 1.2;
  }}
  
  p.page-desc {{
    font-size: 9.5pt;
    color: #475569;
    margin: 0 0 10px 0;
  }}
  
  .screenshot-card {{
    background: #f8fafc;
    border: 1.5px solid #cbd5e1;
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 10px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    text-align: center;
  }}
  
  .screenshot-card img {{
    width: 100%;
    max-height: 122mm;
    object-fit: contain;
    display: block;
    background: #ffffff;
  }}
  
  .steps-table {{
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 8px;
    font-size: 8.5pt;
  }}
  
  .steps-table th {{
    background-color: #f1f5f9;
    color: #334155;
    font-weight: 700;
    text-align: left;
    padding: 5px 8px;
    border-bottom: 1.5px solid #cbd5e1;
  }}
  
  .steps-table td {{
    padding: 5px 8px;
    border-bottom: 1px solid #e2e8f0;
    vertical-align: top;
  }}
  
  .callout-num {{
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background-color: #c5221f;
    color: #ffffff;
    font-weight: 800;
    font-size: 8pt;
    margin-right: 4px;
  }}
  
  .info-box {{
    background-color: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-left: 4px solid #16a34a;
    border-radius: 6px;
    padding: 6px 10px;
    font-size: 8.5pt;
    color: #166534;
    margin-top: auto;
  }}
  
  .warn-box {{
    background-color: #fffbeb;
    border: 1px solid #fde68a;
    border-left: 4px solid #d97706;
    border-radius: 6px;
    padding: 6px 10px;
    font-size: 8.5pt;
    color: #92400e;
    margin-top: auto;
  }}

  /* COVER STYLING */
  .cover-page {{
    background: linear-gradient(135deg, #0f2744 0%, #1e3a8a 50%, #0f2744 100%);
    color: #ffffff;
    padding: 30px;
    border-radius: 10px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    height: 272mm;
  }}
  
  .cover-logo-box {{
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 2px solid rgba(255,255,255,0.2);
    padding-bottom: 14px;
  }}
  
  .cover-company {{
    font-size: 15pt;
    font-weight: 800;
    letter-spacing: 1.5px;
    color: #ffffff;
  }}
  
  .cover-sop {{
    background: #c5221f;
    padding: 5px 12px;
    border-radius: 4px;
    font-weight: 800;
    font-size: 9pt;
    letter-spacing: 1px;
  }}
  
  .cover-center {{
    margin: auto 0;
  }}
  
  .cover-badge {{
    display: inline-block;
    background: rgba(255,255,255,0.15);
    border: 1px solid rgba(255,255,255,0.3);
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 10pt;
    font-weight: 700;
    margin-bottom: 16px;
    letter-spacing: 1px;
    color: #fca5a5;
  }}
  
  .cover-title {{
    font-size: 26pt;
    font-weight: 900;
    line-height: 1.15;
    margin: 0 0 12px 0;
    color: #ffffff;
  }}
  
  .cover-subtitle {{
    font-size: 13pt;
    color: #93c5fd;
    font-weight: 500;
    line-height: 1.4;
    margin: 0 0 24px 0;
  }}
  
  .cover-grid {{
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-top: 15px;
  }}
  
  .cover-card {{
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 8px;
    padding: 12px 16px;
  }}
  
  .cover-card-title {{
    font-size: 8.5pt;
    color: #93c5fd;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
  }}
  
  .cover-card-val {{
    font-size: 11pt;
    font-weight: 700;
    color: #ffffff;
  }}
  
  .cover-footer-box {{
    border-top: 1px solid rgba(255,255,255,0.2);
    padding-top: 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 8.5pt;
    color: #cbd5e1;
  }}
</style>
</head>
<body>

<!-- ============================================================ -->
<!-- HALAMAN 1: COVER PAGE                                        -->
<!-- ============================================================ -->
<div class="page">
  <div class="cover-page">
    <div class="cover-logo-box">
      <div class="cover-company">PT MUSASHI AUTO PARTS INDONESIA</div>
      <div class="cover-sop">SOP-PA-02-SH</div>
    </div>
    
    <div class="cover-center">
      <div class="cover-badge">MODUL OPERASIONAL RESMI &bull; PANDUAN LENGKAP</div>
      <h1 class="cover-title">PETUNJUK PENGGUNAAN SISTEM E-PERFORMANCE APPRAISAL</h1>
      <div class="cover-subtitle">
        Panduan Komprehensif Peran <strong>Section Head (Kepala Seksi)</strong>: Evaluasi Diri Sendiri (Form KJ 07-006) dan Penilaian Bawahan Lini (KJ 1-3 Form Standar &amp; KJ 4-5 Form Foreman/Staff)
      </div>
      
      <div class="cover-grid">
        <div class="cover-card">
          <div class="cover-card-title">Peran Pengguna (Role)</div>
          <div class="cover-card-val">Section Head / Kepala Seksi</div>
        </div>
        <div class="cover-card">
          <div class="cover-card-title">Cakupan Evaluasi Mandiri</div>
          <div class="cover-card-val">Form 07-006-SH (4 Pilar Manajerial 100%)</div>
        </div>
        <div class="cover-card">
          <div class="cover-card-title">Cakupan Penilaian Bawahan</div>
          <div class="cover-card-val">Bawahan KJ 1-3 &amp; KJ 4-5 (Foreman / Staff)</div>
        </div>
        <div class="cover-card">
          <div class="cover-card-title">Alamat Portal Resmi</div>
          <div class="cover-card-val" style="font-size: 9.5pt; word-break: break-all;">performanceappraisal.vercel.app</div>
        </div>
      </div>
    </div>
    
    <div class="cover-footer-box">
      <div>&copy; 2026 PT Musashi Auto Parts Indonesia &bull; Human Capital Development</div>
      <div>Dokumen Resmi Kontrol Mutu Internal &bull; Versi 2.0</div>
    </div>
  </div>
</div>

<!-- ============================================================ -->
<!-- HALAMAN 2: LANGKAH 1 - LOGIN & OTENTIKASI SECTION HEAD       -->
<!-- ============================================================ -->
<div class="page">
  <div class="doc-header">
    <div class="company">PT MUSASHI AUTO PARTS INDONESIA</div>
    <div class="tag">PANDUAN SECTION HEAD &bull; HAL. 2</div>
  </div>
  
  <div class="step-badge">LANGKAH 1</div>
  <h1 class="page-title">Akses Portal &amp; Prosedur Masuk (Login)</h1>
  <p class="page-desc">
    Buka peramban (Google Chrome / Microsoft Edge) dan akses portal <strong>https://performanceappraisal.vercel.app/login</strong>. Sistem menggunakan teknologi cerdas <em>Auto-Role Detection</em> tanpa pemilihan peran manual.
  </p>
  
  <div class="screenshot-card">
    <img src="{img_01}" alt="Halaman Login Section Head">
  </div>
  
  <table class="steps-table">
    <thead>
      <tr>
        <th style="width: 30px;">No</th>
        <th style="width: 220px;">Elemen Antarmuka</th>
        <th>Instruksi Operasional &amp; Penjelasan Teknis</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><span class="callout-num">1</span></td>
        <td><strong>Input Email / NRP Pengguna</strong></td>
        <td>Ketikkan Email resmi Section Head (contoh: <code>909sectionhead@musashi.id</code>) atau Nomor Registrasi Pokok (NRP) 909 pada kolom isian akun.</td>
      </tr>
      <tr>
        <td><span class="callout-num">2</span></td>
        <td><strong>Input Kata Sandi (Password)</strong></td>
        <td>Masukkan kata sandi akun terdaftar. Klik ikon mata di sebelah kanan bila perlu memverifikasi karakter password yang Anda ketikkan.</td>
      </tr>
      <tr>
        <td><span class="callout-num">3</span></td>
        <td><strong>Opsi Ingat Saya (Remember)</strong></td>
        <td>Centang kotak ini jika Anda menggunakan komputer kantor resmi agar sesi login tersimpan aman dan tidak perlu input ulang setiap sesi.</td>
      </tr>
      <tr>
        <td><span class="callout-num">4</span></td>
        <td><strong>Tombol Masuk Portal (Auto-Route)</strong></td>
        <td>Klik tombol <strong>Masuk Portal</strong>. Sistem seketika mendeteksi wewenang Section Head dan mengarahkan langsung ke Dashboard Seksi Anda.</td>
      </tr>
    </tbody>
  </table>
  
  <div class="info-box">
    <strong>Fitur Otomatisasi Peran (Zero-Role Selector):</strong> Sistem secara otomatis mengidentifikasi hak akses Section Head. Anda tidak perlu memilih role secara manual. Hak verifikasi dokumen seksi dan form penilaian mandiri langsung aktif.
  </div>
  
  <div class="doc-footer">
    <div>SOP-PA-02-SH &bull; Modul Operasional Section Head</div>
    <div>Halaman 2 dari 8</div>
  </div>
</div>

<!-- ============================================================ -->
<!-- HALAMAN 3: LANGKAH 2 - DASHBOARD SECTION HEAD                -->
<!-- ============================================================ -->
<div class="page">
  <div class="doc-header">
    <div class="company">PT MUSASHI AUTO PARTS INDONESIA</div>
    <div class="tag">PANDUAN SECTION HEAD &bull; HAL. 3</div>
  </div>
  
  <div class="step-badge">LANGKAH 2</div>
  <h1 class="page-title">Navigasi Dashboard &amp; Monitoring Evaluasi Seksi</h1>
  <p class="page-desc">
    Dashboard Section Head menyediakan ringkasan terpadu: status pengingat evaluasi diri sendiri (Form 07-006-SH), metrik progres penilaian tim seksi, dan tabel pelacakan berkas secara real-time.
  </p>
  
  <div class="screenshot-card">
    <img src="{img_02}" alt="Dashboard Section Head">
  </div>
  
  <table class="steps-table">
    <thead>
      <tr>
        <th style="width: 30px;">No</th>
        <th style="width: 220px;">Elemen Antarmuka</th>
        <th>Instruksi Operasional &amp; Penjelasan Teknis</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><span class="callout-num">1</span></td>
        <td><strong>Sidebar Navigasi Utama</strong></td>
        <td>Akses cepat menuju menu: <em>Dashboard Utama</em>, <em>Penilaian Seksi</em>, <em>Data Karyawan</em>, dan <em>Riwayat Penilaian</em>.</td>
      </tr>
      <tr>
        <td><span class="callout-num">2</span></td>
        <td><strong>Banner Pengingat Tugas Mandiri</strong></td>
        <td>Pemberitahuan aktif yang menginformasikan status pengisian lembar evaluasi karya diri sendiri (Form KJ 07-006) sebelum tenggat waktu.</td>
      </tr>
      <tr>
        <td><span class="callout-num">3</span></td>
        <td><strong>Tombol Buka Penilaian Mandiri</strong></td>
        <td>Klik tombol <strong>Buka Form Penilaian Mandiri</strong> untuk langsung masuk ke formulir evaluasi diri Anda tanpa mencari di daftar manual.</td>
      </tr>
      <tr>
        <td><span class="callout-num">4</span></td>
        <td><strong>Kartu KPI Metrik Tim Seksi</strong></td>
        <td>Memantau statistik total anggota seksi: <em>Belum Dinilai</em>, <em>Proses Section Head</em>, <em>Menunggu Dept Head</em>, dan <em>Selesai</em>.</td>
      </tr>
      <tr>
        <td><span class="callout-num">5</span></td>
        <td><strong>Tabel Pelacakan Dokumen Anggota</strong></td>
        <td>Daftar dokumen bawahan dengan indikator status alur dan tombol aksi evaluasi atau peninjauan berkas.</td>
      </tr>
    </tbody>
  </table>
  
  <div class="info-box">
    <strong>Peran Ganda Section Head:</strong> Sebagai Section Head, Anda memiliki dua tanggung jawab krusial: (1) Menyelesaikan Penilaian Mandiri Anda sendiri ke Dept Head, dan (2) Memeriksa serta menilai seluruh bawahan di seksi Anda (KJ 1-3 &amp; KJ 4-5).
  </div>
  
  <div class="doc-footer">
    <div>SOP-PA-02-SH &bull; Modul Operasional Section Head</div>
    <div>Halaman 3 dari 8</div>
  </div>
</div>

<!-- ============================================================ -->
<!-- HALAMAN 4: LANGKAH 3 - PENILAIAN MANDIRI FORM 07-006-SH     -->
<!-- ============================================================ -->
<div class="page">
  <div class="doc-header">
    <div class="company">PT MUSASHI AUTO PARTS INDONESIA</div>
    <div class="tag">PANDUAN SECTION HEAD &bull; HAL. 4</div>
  </div>
  
  <div class="step-badge">LANGKAH 3</div>
  <h1 class="page-title">Penilaian Mandiri Section Head (Form KJ 07-006)</h1>
  <p class="page-desc">
    Section Head mengisi evaluasi kinerja diri sendiri menggunakan standar kompetensi manajerial <strong>Form 07-006-SH</strong> pada kolom <em>Mandiri / 自分で</em> sebelum berkas dinilai oleh Department Head.
  </p>
  
  <div class="screenshot-card">
    <img src="{img_03}" alt="Form Penilaian Mandiri 07-006 Section Head">
  </div>
  
  <table class="steps-table">
    <thead>
      <tr>
        <th style="width: 30px;">No</th>
        <th style="width: 220px;">Elemen Antarmuka</th>
        <th>Instruksi Operasional &amp; Penjelasan Teknis</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><span class="callout-num">1</span></td>
        <td><strong>Identitas Pejabat Section Head</strong></td>
        <td>Verifikasi data pribadi: Nama lengkap (IRFAN NUDIN NAWAWI), NRP 909, Golongan IVB, Jabatan SECTION HEAD, dan Departemen HR &amp; IR.</td>
      </tr>
      <tr>
        <td><span class="callout-num">2</span></td>
        <td><strong>4 Pilar Kompetensi Manajerial</strong></td>
        <td>Terdiri atas: (1) <strong>Hasil Kerja (55%)</strong>, (2) <strong>Proses Kerja (15%)</strong>, (3) <strong>Sikap Kerja (15%)</strong>, dan (4) <strong>Man Management (15%)</strong>. Total bobot: 100%.</td>
      </tr>
      <tr>
        <td><span class="callout-num">3</span></td>
        <td><strong>Kolom Skor Mandiri / 自分で</strong></td>
        <td>Masukkan skor evaluasi diri (skala 1 - 100) pada setiap butir penilaian. Total nilai dan bobot otomatis dihitung oleh kalkulator sistem.</td>
      </tr>
      <tr>
        <td><span class="callout-num">4</span></td>
        <td><strong>Indikator Evaluasi Potensi Diri</strong></td>
        <td>Tinjau kriteria evaluasi potensi: kesiapan tugas lebih tinggi, kompetensi teknis, dan kesiapan manajerial.</td>
      </tr>
      <tr>
        <td><span class="callout-num">5</span></td>
        <td><strong>Tanda Tangan Karyawan Ybs &amp; Submit</strong></td>
        <td>Klik tombol <strong>Tanda Tangan Digital</strong> pada kolom <em>Kary. Ybs / 個人的</em>, lalu klik <strong>Simpan Draft</strong> atau <strong>Kirim ke Dept Head</strong>.</td>
      </tr>
    </tbody>
  </table>
  
  <div class="warn-box">
    <strong>Penting &bull; Urutan Alur:</strong> Form 07-006 Section Head wajib diisi dan ditandatangani secara mandiri terlebih dahulu. Setelah Anda submit, dokumen akan otomatis berstatus <code>menunggu_dept_head</code> untuk dinilai oleh Atasan Langsung Anda.
  </div>
  
  <div class="doc-footer">
    <div>SOP-PA-02-SH &bull; Modul Operasional Section Head</div>
    <div>Halaman 4 dari 8</div>
  </div>
</div>

<!-- ============================================================ -->
<!-- HALAMAN 5: LANGKAH 4 - DATA KARYAWAN & FILTER SEKSI          -->
<!-- ============================================================ -->
<div class="page">
  <div class="doc-header">
    <div class="company">PT MUSASHI AUTO PARTS INDONESIA</div>
    <div class="tag">PANDUAN SECTION HEAD &bull; HAL. 5</div>
  </div>
  
  <div class="step-badge">LANGKAH 4</div>
  <h1 class="page-title">Pemeriksaan Data Karyawan &amp; Anggota Seksi</h1>
  <p class="page-desc">
    Menu <strong>Data Karyawan</strong> memungkinkan Section Head memverifikasi seluruh personel yang berada di bawah komando seksinya, memastikan kesesuaian golongan (KJ), jabatan, dan hierarki penilai langsung.
  </p>
  
  <div class="screenshot-card">
    <img src="{img_04}" alt="Data Karyawan Seksi">
  </div>
  
  <table class="steps-table">
    <thead>
      <tr>
        <th style="width: 30px;">No</th>
        <th style="width: 220px;">Elemen Antarmuka</th>
        <th>Instruksi Operasional &amp; Penjelasan Teknis</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><span class="callout-num">1</span></td>
        <td><strong>Area Filter &amp; Kolom Pencarian</strong></td>
        <td>Saring personel berdasarkan Seksi spesifik, Jenjang Jabatan (Operator, Foreman, Staff, Gol 4), atau ketik nama/NRP langsung.</td>
      </tr>
      <tr>
        <td><span class="callout-num">2</span></td>
        <td><strong>Kartu Ringkasan Struktur Seksi</strong></td>
        <td>Menampilkan total anggota tim aktif, jumlah pelaksana operasional lini (KJ 1-3), dan personel tingkat penyelia (KJ 4-5).</td>
      </tr>
      <tr>
        <td><span class="callout-num">3</span></td>
        <td><strong>Tabel Inventaris Personel Seksi</strong></td>
        <td>Memuat informasi: NRP, Nama Lengkap, Golongan/Sub-golongan, Jabatan, Seksi, Departemen, dan Atasan Langsung resmi.</td>
      </tr>
      <tr>
        <td><span class="callout-num">4</span></td>
        <td><strong>Detail Data &amp; Verifikasi Hierarki</strong></td>
        <td>Periksa apakah kolom <em>Foreman</em> dan <em>Section Head</em> sudah terisi sesuai struktur organisasi lini produksi / departemen Anda.</td>
      </tr>
    </tbody>
  </table>
  
  <div class="info-box">
    <strong>Sinkronisasi Data Personel:</strong> Jika terdapat anggota seksi baru atau mutasi yang belum tercatat pada daftar, segera hubungi Administrator HRD untuk menjalankan sinkronisasi data master sebelum penilaian final disahkan.
  </div>
  
  <div class="doc-footer">
    <div>SOP-PA-02-SH &bull; Modul Operasional Section Head</div>
    <div>Halaman 5 dari 8</div>
  </div>
</div>

<!-- ============================================================ -->
<!-- HALAMAN 6: LANGKAH 5 - MENILAI BAWAHAN KJ 1-3 (FORM STANDAR) -->
<!-- ============================================================ -->
<div class="page">
  <div class="doc-header">
    <div class="company">PT MUSASHI AUTO PARTS INDONESIA</div>
    <div class="tag">PANDUAN SECTION HEAD &bull; HAL. 6</div>
  </div>
  
  <div class="step-badge">LANGKAH 5</div>
  <h1 class="page-title">Menilai Bawahan KJ 1 - 3 (Operator / Pelaksana Lini)</h1>
  <p class="page-desc">
    Prosedur evaluasi karyawan pelaksana lini lapangan (KJ 1 - 3) menggunakan formulir standar kinerja (Form 07-001 / 07-002 / 07-003). Section Head berperan sebagai Penilai 2 (atau Penilai 1 jika seksi tanpa Foreman).
  </p>
  
  <div class="screenshot-card">
    <img src="{img_05}" alt="Penilaian Bawahan KJ 1-3 Form Standar">
  </div>
  
  <table class="steps-table">
    <thead>
      <tr>
        <th style="width: 30px;">No</th>
        <th style="width: 220px;">Elemen Antarmuka</th>
        <th>Instruksi Operasional &amp; Penjelasan Teknis</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><span class="callout-num">1</span></td>
        <td><strong>Identitas Bawahan KJ 1-3</strong></td>
        <td>Periksa identitas anggota: Nama lengkap (AL QADRI), NRP 199405, Golongan IIIA, KJ 3, dan unit seksi penempatan kerja.</td>
      </tr>
      <tr>
        <td><span class="callout-num">2</span></td>
        <td><strong>Aspek Kinerja Operasional &amp; Disiplin</strong></td>
        <td>Menilai indikator lapangan: Kualitas Hasil Kerja, Produktivitas / Output, 5S, Keselamatan Kerja (K3), Disiplin &amp; Absensi.</td>
      </tr>
      <tr>
        <td><span class="callout-num">3</span></td>
        <td><strong>Review Foreman &amp; Input Section Head</strong></td>
        <td>Tinjau skor dari Foreman di Kolom Penilai 1 (jika ada Foreman). Selanjutnya Section Head mengisi skor di Kolom Penilai 2 (skala 1 - 100).</td>
      </tr>
      <tr>
        <td><span class="callout-num">4</span></td>
        <td><strong>Evaluasi Rekomendasi &amp; Catatan</strong></td>
        <td>Pilih status evaluasi potensi (memenuhi standar promosi / rotasi) dan berikan saran pelatihan teknis yang dibutuhkan.</td>
      </tr>
      <tr>
        <td><span class="callout-num">5</span></td>
        <td><strong>Tanda Tangan Section Head &amp; Submit</strong></td>
        <td>Bubuhkan tanda tangan digital pada kolom <em>Atasan Langsung (Section Head)</em>, kemudian klik tombol <strong>Kirim ke Dept Head</strong>.</td>
      </tr>
    </tbody>
  </table>
  
  <div class="info-box">
    <strong>Struktur Seksi Tanpa Foreman:</strong> Pada seksi yang tidak memiliki jabatan Foreman, Section Head bertindak langsung sebagai Penilai Utama. Skor langsung dimasukkan pada kolom evaluasi dan berkas langsung diajukan ke Department Head.
  </div>
  
  <div class="doc-footer">
    <div>SOP-PA-02-SH &bull; Modul Operasional Section Head</div>
    <div>Halaman 6 dari 8</div>
  </div>
</div>

<!-- ============================================================ -->
<!-- HALAMAN 7: LANGKAH 6 - MENILAI BAWAHAN KJ 4-5 (FOREMAN/STAFF)-->
<!-- ============================================================ -->
<div class="page">
  <div class="doc-header">
    <div class="company">PT MUSASHI AUTO PARTS INDONESIA</div>
    <div class="tag">PANDUAN SECTION HEAD &bull; HAL. 7</div>
  </div>
  
  <div class="step-badge">LANGKAH 6</div>
  <h1 class="page-title">Menilai Bawahan KJ 4 - 5 (Foreman / Staff / Gol 4)</h1>
  <p class="page-desc">
    Prosedur evaluasi bawahan tingkat penyelia lini (Foreman / Leader / Staff KJ 4-5) menggunakan <strong>Form 07-004 / 07-005</strong> atau <strong>Form 07-006</strong> bagi Golongan 4 Non Kasie.
  </p>
  
  <div class="screenshot-card">
    <img src="{img_06}" alt="Penilaian Bawahan KJ 4-5 Form Foreman">
  </div>
  
  <table class="steps-table">
    <thead>
      <tr>
        <th style="width: 30px;">No</th>
        <th style="width: 220px;">Elemen Antarmuka</th>
        <th>Instruksi Operasional &amp; Penjelasan Teknis</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><span class="callout-num">1</span></td>
        <td><strong>Identitas Bawahan Penyelia KJ 4-5</strong></td>
        <td>Verifikasi data bawahan: Nama lengkap (JOKO HARTONO), Jabatan FOREMAN, Golongan IVA, KJ 6, dan Seksi penempatan.</td>
      </tr>
      <tr>
        <td><span class="callout-num">2</span></td>
        <td><strong>Aspek Kinerja &amp; Kepemimpinan Regu</strong></td>
        <td>Meliputi: Pencapaian Target Produksi/Proyek, Efisiensi Biaya, Manajemen Anggota Regu, Kaizen / QCC, dan Keselamatan Kerja.</td>
      </tr>
      <tr>
        <td><span class="callout-num">3</span></td>
        <td><strong>Review Nilai Mandiri &amp; Skor Section Head</strong></td>
        <td>Periksa skor evaluasi mandiri yang telah diinput oleh Foreman. Masukkan skor Section Head secara objektif dan seimbang.</td>
      </tr>
      <tr>
        <td><span class="callout-num">4</span></td>
        <td><strong>Evaluasi Potensi Kepemimpinan (Promosi)</strong></td>
        <td>Isi rekomendasi promosi ke jenjang berikutnya (misal: calon Section Head / Special Staff) dan kebutuhan pelatihan manajerial.</td>
      </tr>
      <tr>
        <td><span class="callout-num">5</span></td>
        <td><strong>Tanda Tangan Digital &amp; Submit Dept Head</strong></td>
        <td>Klik tombol tanda tangan digital Section Head pada kolom <em>Atasan Langsung</em>, simpan draft atau langsung teruskan ke Department Head.</td>
      </tr>
    </tbody>
  </table>
  
  <div class="info-box">
    <strong>Bawahan Gol 4 Non Kasie (Form 07-006):</strong> Untuk staf spesialis atau Gol 4 non-struktural di bawah seksi Anda, formulir yang digunakan adalah Form 07-006 dengan 4 pilar kompetensi. Alur verifikasi tetap sama: Section Head bertindak sebagai atasan penilai langsung.
  </div>
  
  <div class="doc-footer">
    <div>SOP-PA-02-SH &bull; Modul Operasional Section Head</div>
    <div>Halaman 7 dari 8</div>
  </div>
</div>

<!-- ============================================================ -->
<!-- HALAMAN 8: LANGKAH 7 - RIWAYAT & PELACAKAN ALUR DOKUMEN      -->
<!-- ============================================================ -->
<div class="page">
  <div class="doc-header">
    <div class="company">PT MUSASHI AUTO PARTS INDONESIA</div>
    <div class="tag">PANDUAN SECTION HEAD &bull; HAL. 8</div>
  </div>
  
  <div class="step-badge">LANGKAH 7</div>
  <h1 class="page-title">Riwayat Penilaian &amp; Pelacakan Alur Dokumen</h1>
  <p class="page-desc">
    Menu <strong>Riwayat Penilaian</strong> menyajikan rekam jejak digital lengkap seluruh berkas evaluasi seksi Anda, status verifikasi bertingkat, serta fasilitas cetak / unduh berkas PDF resmi ber-barcode verifikasi.
  </p>
  
  <div class="screenshot-card">
    <img src="{img_07}" alt="Riwayat Penilaian & Pelacakan Berkas">
  </div>
  
  <table class="steps-table">
    <thead>
      <tr>
        <th style="width: 30px;">No</th>
        <th style="width: 220px;">Elemen Antarmuka</th>
        <th>Instruksi Operasional &amp; Penjelasan Teknis</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><span class="callout-num">1</span></td>
        <td><strong>Kartu Metrik Riwayat Seksi</strong></td>
        <td>Menampilkan total berkas seksi: <em>Menunggu Dept Head</em>, <em>Menunggu Div Head</em>, <em>Selesai Dinilai</em>, dan <em>Arsip Selesai</em>.</td>
      </tr>
      <tr>
        <td><span class="callout-num">2</span></td>
        <td><strong>Filter Periode &amp; Pencarian Dokumen</strong></td>
        <td>Pilih tahun periode penilaian (contoh: 2026), filter status alur pengajuan, atau cari berdasarkan nama karyawan / NRP tertentu.</td>
      </tr>
      <tr>
        <td><span class="callout-num">3</span></td>
        <td><strong>Tabel Rekapitulasi Berkas Evaluasi</strong></td>
        <td>Menampilkan data terperinci: Nomor Dokumen, Nama Karyawan, Form, Skor Akhir, Grade Mutu, dan Badge Status Alur Berkas.</td>
      </tr>
      <tr>
        <td><span class="callout-num">4</span></td>
        <td><strong>Unduh Berkas PDF Resmi (Barcode)</strong></td>
        <td>Klik tombol <strong>Cetak / PDF</strong> pada baris dokumen untuk mengunduh salinan resmi ber-QR Code digital yang telah disahkan atasan.</td>
      </tr>
    </tbody>
  </table>
  
  <div class="info-box">
    <strong>Kepastian Alur Pengesahan:</strong> Pastikan seluruh berkas anggota seksi Anda telah disubmit sebelum batas waktu penilaian departemen (cut-off). Dokumen yang telah berstatus <code>selesai</code> akan tersimpan permanen dalam arsip audit HRD.
  </div>
  
  <div class="doc-footer">
    <div>SOP-PA-02-SH &bull; Modul Operasional Section Head</div>
    <div>Halaman 8 dari 8</div>
  </div>
</div>

</body>
</html>
"""

# Save HTML
with open(OUT_HTML, "w", encoding="utf-8") as f:
    f.write(html_content)
print(f"HTML saved: {OUT_HTML} ({len(html_content)} bytes)")

# ==========================================
# 2. GENERATE PDF VIA HEADLESS CHROME
# ==========================================
print("Generating PDF via headless Chrome...")
chrome_path = r"C:\Program Files\Google\Chrome\Application\chrome.exe"
cmd = [
    chrome_path,
    "--headless=new",
    "--disable-gpu",
    "--no-pdf-header-footer",
    f"--print-to-pdf={TEMP_PDF}",
    OUT_HTML
]
subprocess.run(cmd, check=True)

# Copy to final destinations with retry
def safe_copy(src, dst):
    for attempt in range(5):
        try:
            shutil.copy2(src, dst)
            print(f"Successfully copied to: {dst}")
            return
        except PermissionError:
            print(f"Attempt {attempt+1}: File {dst} locked, waiting 1s...")
            time.sleep(1)
    # If still locked, try terminating locking processes
    try:
        subprocess.run(["powershell", "-Command", "Stop-Process -Name '*wps*' -Force -ErrorAction SilentlyContinue"], capture_output=True)
        time.sleep(1)
        shutil.copy2(src, dst)
        print(f"Successfully copied after terminating viewer: {dst}")
    except Exception as e:
        print(f"Failed to copy to {dst}: {e}")

safe_copy(TEMP_PDF, OUT_PDF)
safe_copy(TEMP_PDF, OUT_PDF_USER)

# Verify page count
try:
    import fitz # PyMuPDF
    doc_pdf = fitz.open(OUT_PDF)
    print(f"PDF Page count: {len(doc_pdf)} pages (Target: 8 pages)")
    doc_pdf.close()
except ImportError:
    pass

# ==========================================
# 3. BUILD DOCX DOCUMENT (MICROSOFT WORD)
# ==========================================
print("Generating Section Head Word Document (.docx)...")
doc = Document()

# Set Margins
for s in doc.sections:
    s.top_margin = Inches(0.4)
    s.bottom_margin = Inches(0.4)
    s.left_margin = Inches(0.55)
    s.right_margin = Inches(0.55)

# Color Constants
C_NAVY = RGBColor(15, 39, 68)     # #0f2744
C_RED = RGBColor(197, 34, 31)     # #c5221f
C_GRAY = RGBColor(100, 116, 139)  # #64748b
C_DARK = RGBColor(30, 41, 59)     # #1e293b
HEX_NAVY = "0F2744"
HEX_RED = "C5221F"
HEX_LIGHT = "F8FAFC"
HEX_BORDER = "CBD5E1"
HEX_INFO_BG = "F0FDF4"
HEX_INFO_BORDER = "16A34A"

def set_cell_bg(cell, hex_color):
    tcPr = cell._tc.get_or_add_tcPr()
    tcPr.append(parse_xml(f'<w:shd {nsdecls("w")} w:fill="{hex_color}"/>'))

def set_cell_margins(cell, top=100, bottom=100, left=150, right=150):
    tcPr = cell._tc.get_or_add_tcPr()
    tcMar = parse_xml(f'<w:tcMar {nsdecls("w")}><w:top w:w="{top}" w:type="dxa"/><w:bottom w:w="{bottom}" w:type="dxa"/><w:left w:w="{left}" w:type="dxa"/><w:right w:w="{right}" w:type="dxa"/></w:tcMar>')
    tcPr.append(tcMar)

def add_header_banner(doc, step_num, step_title, step_desc):
    p_head = doc.add_paragraph()
    p_head.paragraph_format.space_before = Pt(4)
    p_head.paragraph_format.space_after = Pt(2)
    r_comp = p_head.add_run("PT MUSASHI AUTO PARTS INDONESIA")
    r_comp.font.name = "Arial"
    r_comp.font.size = Pt(9)
    r_comp.font.bold = True
    r_comp.font.color.rgb = C_NAVY
    
    r_sop = p_head.add_run("   |   SOP-PA-02-SH   |   MODUL RESMI SECTION HEAD")
    r_sop.font.name = "Arial"
    r_sop.font.size = Pt(8.5)
    r_sop.font.color.rgb = C_GRAY
    
    p_title = doc.add_paragraph()
    p_title.paragraph_format.space_before = Pt(2)
    p_title.paragraph_format.space_after = Pt(2)
    
    r_badge = p_title.add_run(f"LANGKAH {step_num}: ")
    r_badge.font.name = "Arial"
    r_badge.font.size = Pt(13)
    r_badge.font.bold = True
    r_badge.font.color.rgb = C_RED
    
    r_t = p_title.add_run(step_title)
    r_t.font.name = "Arial"
    r_t.font.size = Pt(13)
    r_t.font.bold = True
    r_t.font.color.rgb = C_NAVY
    
    p_desc = doc.add_paragraph()
    p_desc.paragraph_format.space_before = Pt(0)
    p_desc.paragraph_format.space_after = Pt(6)
    r_d = p_desc.add_run(step_desc)
    r_d.font.name = "Calibri"
    r_d.font.size = Pt(9.5)
    r_d.font.color.rgb = C_DARK

def add_callout_table(doc, items):
    table = doc.add_table(rows=1, cols=3)
    table.alignment = WD_TABLE_ALIGNMENT.CENTER
    table.autofit = False
    
    widths = [Inches(0.5), Inches(2.2), Inches(4.5)]
    hdr_cells = table.rows[0].cells
    hdr_titles = ["No", "Elemen Antarmuka", "Instruksi Operasional & Penjelasan Teknis"]
    for i, title in enumerate(hdr_titles):
        hdr_cells[i].width = widths[i]
        set_cell_bg(hdr_cells[i], "F1F5F9")
        set_cell_margins(hdr_cells[i], top=60, bottom=60, left=100, right=100)
        p = hdr_cells[i].paragraphs[0]
        p.paragraph_format.space_before = Pt(2)
        p.paragraph_format.space_after = Pt(2)
        run = p.add_run(title)
        run.font.name = "Arial"
        run.font.size = Pt(8.5)
        run.font.bold = True
        run.font.color.rgb = C_NAVY
        if i == 0:
            p.alignment = WD_ALIGN_PARAGRAPH.CENTER
            
    for item in items:
        row_cells = table.add_row().cells
        for i in range(3):
            row_cells[i].width = widths[i]
            set_cell_margins(row_cells[i], top=60, bottom=60, left=100, right=100)
            p = row_cells[i].paragraphs[0]
            p.paragraph_format.space_before = Pt(2)
            p.paragraph_format.space_after = Pt(2)
            
        p_num = row_cells[0].paragraphs[0]
        p_num.alignment = WD_ALIGN_PARAGRAPH.CENTER
        r_num = p_num.add_run(str(item[0]))
        r_num.font.name = "Arial"
        r_num.font.size = Pt(8.5)
        r_num.font.bold = True
        r_num.font.color.rgb = C_RED
        
        p_elem = row_cells[1].paragraphs[0]
        r_elem = p_elem.add_run(item[1])
        r_elem.font.name = "Calibri"
        r_elem.font.size = Pt(8.5)
        r_elem.font.bold = True
        r_elem.font.color.rgb = C_NAVY
        
        p_desc = row_cells[2].paragraphs[0]
        r_desc = p_desc.add_run(item[2])
        r_desc.font.name = "Calibri"
        r_desc.font.size = Pt(8.5)
        r_desc.font.color.rgb = C_DARK

def add_info_callout(doc, text):
    tbl = doc.add_table(rows=1, cols=1)
    tbl.alignment = WD_TABLE_ALIGNMENT.CENTER
    c = tbl.rows[0].cells[0]
    c.width = Inches(7.2)
    set_cell_bg(c, HEX_INFO_BG)
    set_cell_margins(c, top=80, bottom=80, left=140, right=140)
    p = c.paragraphs[0]
    p.paragraph_format.space_before = Pt(2)
    p.paragraph_format.space_after = Pt(2)
    r_tag = p.add_run("PANDUAN OPERASIONAL &bull; ")
    r_tag.font.name = "Arial"
    r_tag.font.size = Pt(8.5)
    r_tag.font.bold = True
    r_tag.font.color.rgb = RGBColor(22, 101, 52)
    r_t = p.add_run(text)
    r_t.font.name = "Calibri"
    r_t.font.size = Pt(8.5)
    r_t.font.color.rgb = RGBColor(22, 101, 52)

# --- HALAMAN 1: COVER DOCX ---
p_cov = doc.add_paragraph()
r_comp = p_cov.add_run("PT MUSASHI AUTO PARTS INDONESIA\n")
r_comp.font.name = "Arial"
r_comp.font.size = Pt(16)
r_comp.font.bold = True
r_comp.font.color.rgb = C_NAVY

r_tag = p_cov.add_run("STANDAR OPERASIONAL PROSEDUR (SOP) &bull; SOP-PA-02-SH\n\n")
r_tag.font.name = "Arial"
r_tag.font.size = Pt(9.5)
r_tag.font.bold = True
r_tag.font.color.rgb = C_RED

r_title = p_cov.add_run("BUKU PANDUAN PENGGUNAAN SISTEM\nE-PERFORMANCE APPRAISAL\n")
r_title.font.name = "Arial"
r_title.font.size = Pt(22)
r_title.font.bold = True
r_title.font.color.rgb = C_NAVY

r_sub = p_cov.add_run("Pedoman Komprehensif Peran Section Head (Kepala Seksi): Penilaian Mandiri (Form KJ 07-006) dan Penilaian Bawahan (KJ 1-3 Form Standar & KJ 4-5 Form Foreman/Staff)\n\n")
r_sub.font.name = "Calibri"
r_sub.font.size = Pt(12)
r_sub.font.color.rgb = C_GRAY

# Cover Table
t_meta = doc.add_table(rows=5, cols=2)
t_meta.alignment = WD_TABLE_ALIGNMENT.CENTER
meta_data = [
    ("Peran Pengguna (Role):", "Section Head / Kepala Seksi"),
    ("Cakupan Penilaian Mandiri:", "Form 07-006-SH (4 Pilar Manajerial 100%)"),
    ("Cakupan Penilaian Bawahan:", "Bawahan KJ 1-3 (Form Standar) & KJ 4-5 (Form Foreman/Staff)"),
    ("Alamat Portal Web Resmi:", "https://performanceappraisal.vercel.app/"),
    ("Versi Sistem & Tanggal Efektif:", "Versi 2.0 (Resmi) - Periode Tahun 2026")
]
for i, (k, v) in enumerate(meta_data):
    c0 = t_meta.rows[i].cells[0]
    c1 = t_meta.rows[i].cells[1]
    c0.width = Inches(2.6)
    c1.width = Inches(4.6)
    set_cell_bg(c0, "F1F5F9")
    set_cell_margins(c0, top=60, bottom=60, left=100, right=100)
    set_cell_margins(c1, top=60, bottom=60, left=100, right=100)
    
    p0 = c0.paragraphs[0]
    p0.paragraph_format.space_before = Pt(2)
    p0.paragraph_format.space_after = Pt(2)
    r0 = p0.add_run(k)
    r0.font.name = "Arial"
    r0.font.size = Pt(9)
    r0.font.bold = True
    r0.font.color.rgb = C_NAVY
    
    p1 = c1.paragraphs[0]
    p1.paragraph_format.space_before = Pt(2)
    p1.paragraph_format.space_after = Pt(2)
    r1 = p1.add_run(v)
    r1.font.name = "Calibri"
    r1.font.size = Pt(9.5)
    r1.font.color.rgb = C_DARK

# --- HALAMAN 2: LANGKAH 1 - LOGIN ---
doc.add_page_break()
add_header_banner(
    doc, 1, "Akses Portal & Prosedur Masuk (Login)",
    "Buka peramban resmi (Chrome/Edge) ke portal https://performanceappraisal.vercel.app/login. Sistem memiliki auto-role detection tanpa pilihan peran manual."
)
path_01 = os.path.join(IMG_DIR, "sh_01_login.png")
if os.path.exists(path_01):
    doc.add_picture(path_01, width=Inches(7.1))
add_callout_table(doc, [
    (1, "Input Email / NRP", "Ketikkan email resmi Section Head (contoh: 909sectionhead@musashi.id) atau NRP 909 pada kolom isian akun."),
    (2, "Input Kata Sandi", "Masukkan password akun terdaftar. Gunakan ikon mata bila ingin memverifikasi karakter password yang Anda ketikkan."),
    (3, "Opsi Ingat Saya", "Centang kotak Ingat Saya bila menggunakan laptop/komputer kerja resmi agar sesi login tersimpan secara aman."),
    (4, "Tombol Masuk Portal", "Klik Masuk Portal. Sistem mendeteksi otomatis peran Section Head dan langsung membuka menu Dashboard Seksi.")
])
add_info_callout(doc, "Fitur Otomatisasi Peran: Sistem secara cerdas mengarahkan akun Section Head langsung ke wewenang penilaian seksi tanpa perlu memilih role manual.")

# --- HALAMAN 3: LANGKAH 2 - DASHBOARD ---
doc.add_page_break()
add_header_banner(
    doc, 2, "Navigasi Dashboard & Monitoring Tim Seksi",
    "Dashboard Section Head menyajikan ringkasan terpadu: status evaluasi mandiri Section Head, kartu KPI progres tim seksi, dan tabel pelacakan berkas."
)
path_02 = os.path.join(IMG_DIR, "sh_02_dashboard.png")
if os.path.exists(path_02):
    doc.add_picture(path_02, width=Inches(7.1))
add_callout_table(doc, [
    (1, "Sidebar Navigasi Utama", "Akses menu utama aplikasi: Dashboard, Penilaian Seksi, Data Karyawan, dan Riwayat Penilaian."),
    (2, "Banner Pengingat Mandiri", "Alert aktif yang menampilkan status evaluasi diri sendiri (Form KJ 07-006) sebelum dinilai oleh Dept Head."),
    (3, "Tombol Form Mandiri", "Klik tombol Buka Form Penilaian Mandiri untuk langsung mengisi lembar evaluasi karya diri sendiri."),
    (4, "Kartu KPI Metrik Tim", "Memantau rekapitulasi progres penilaian seluruh personel seksi: Belum Dinilai, Proses Seksi, dan Menunggu Dept Head."),
    (5, "Tabel Pelacakan Dokumen", "Tabel ringkasan berkas pengajuan seluruh anggota seksi dengan indikator status alur dan tombol aksi evaluasi.")
])
add_info_callout(doc, "Peran Ganda Section Head: Section Head bertindak sebagai pihak yang dinilai (oleh Dept Head) sekaligus atasan langsung penilai bawahan di seksinya.")

# --- HALAMAN 4: LANGKAH 3 - FORM 07-006 MANDIRI ---
doc.add_page_break()
add_header_banner(
    doc, 3, "Penilaian Mandiri Section Head (Form KJ 07-006)",
    "Section Head wajib mengisi evaluasi diri sendiri pada Form 07-006-SH di Kolom Mandiri / 自分で sebelum dokumen disahkan dan dinilai Atasan Langsung (Dept Head)."
)
path_03 = os.path.join(IMG_DIR, "sh_03_form_07006_mandiri.png")
if os.path.exists(path_03):
    doc.add_picture(path_03, width=Inches(7.1))
add_callout_table(doc, [
    (1, "Identitas Section Head", "Verifikasi data identitas: Nama lengkap (IRFAN NUDIN NAWAWI), NRP 909, Golongan IVB, Jabatan SECTION HEAD, Dept HR & IR."),
    (2, "4 Pilar Manajerial (100%)", "Empat dimensi kompetensi: Hasil Kerja (55%), Proses Kerja (15%), Sikap Kerja (15%), dan Man Management (15%)."),
    (3, "Kolom Skor Mandiri", "Input skor evaluasi mandiri (1 - 100) pada tiap kriteria. Total nilai dan bobot otomatis dihitung oleh kalkulator sistem."),
    (4, "Evaluasi Potensi Diri", "Tinjau kriteria evaluasi potensi pengembangan karir dan kesiapan tanggung jawab jenjang yang lebih tinggi."),
    (5, "TTD Digital & Submit", "Klik tombol Tanda Tangan Digital pada kolom Kary. Ybs / 個人的, lalu klik Simpan Draft atau Kirim ke Dept Head.")
])
add_info_callout(doc, "Kewajiban Alur Mandiri: Pastikan form evaluasi mandiri telah disubmit ke Dept Head sebelum batas cut-off penilaian tengah semester.")

# --- HALAMAN 5: LANGKAH 4 - DATA KARYAWAN ---
doc.add_page_break()
add_header_banner(
    doc, 4, "Data Karyawan & Filter Anggota Seksi",
    "Menu Data Karyawan memungkinkan Section Head memverifikasi seluruh personel yang berada di bawah komando seksinya dan memeriksa hierarki atasan langsung."
)
path_04 = os.path.join(IMG_DIR, "sh_04_karyawan.png")
if os.path.exists(path_04):
    doc.add_picture(path_04, width=Inches(7.1))
add_callout_table(doc, [
    (1, "Filter & Kolom Pencarian", "Saring anggota berdasarkan Seksi spesifik, Jenjang Jabatan (Operator, Foreman, Staff, Gol 4), atau cari nama/NRP langsung."),
    (2, "Kartu Struktur Tim", "Menampilkan statistik anggota seksi aktif, jumlah pelaksana lini (KJ 1-3), dan personel penyelia lini (KJ 4-5)."),
    (3, "Tabel Inventaris Personel", "Memuat data: NRP, Nama Lengkap, Golongan/Sub-golongan, Jabatan, Seksi, Departemen, dan Atasan Langsung."),
    (4, "Baris Data & Detail", "Periksa kesesuaian penugasan atasan langsung (Foreman dan Section Head) untuk memastikan akurasi alur approval.")
])
add_info_callout(doc, "Verifikasi Hierarki Tim: Pastikan seluruh anggota seksi telah terdaftar dengan atasan langsung yang tepat sebelum proses penilaian serentak dimulai.")

# --- HALAMAN 6: LANGKAH 5 - MENILAI BAWAHAN KJ 1-3 ---
doc.add_page_break()
add_header_banner(
    doc, 5, "Menilai Bawahan KJ 1 - 3 (Operator / Pelaksana Lini)",
    "Prosedur evaluasi karyawan pelaksana lini lapangan (KJ 1 - 3) menggunakan formulir standar (Form 07-001/002/003) sebagai Penilai 2 atau Penilai 1 langsung."
)
path_05 = os.path.join(IMG_DIR, "sh_05_form_kj13_bawahan.png")
if os.path.exists(path_05):
    doc.add_picture(path_05, width=Inches(7.1))
add_callout_table(doc, [
    (1, "Identitas Bawahan KJ 1-3", "Periksa identitas anggota: Nama lengkap (AL QADRI), NRP 199405, Golongan IIIA, KJ 3, dan unit seksi penempatan kerja."),
    (2, "Aspek Kinerja Lapangan", "Menilai indikator lapangan: Kualitas Kerja, Produktivitas / Output, 5S, Keselamatan Kerja (K3), Disiplin & Absensi."),
    (3, "Review Skor & Input Nilai", "Tinjau skor Foreman di Kolom Penilai 1 (jika ada Foreman), kemudian Section Head mengisi skor di Kolom Penilai 2 (skala 1 - 100)."),
    (4, "Evaluasi Potensi & Rekomendasi", "Pilih rekomendasi potensi (promosi / rotasi) dan tuliskan saran pelatihan teknis yang dibutuhkan untuk pengembangan karir."),
    (5, "TTD Section Head & Submit", "Bubuhkan tanda tangan digital pada kolom Atasan Langsung (Section Head), lalu klik tombol Kirim ke Dept Head.")
])
add_info_callout(doc, "Seksi Tanpa Foreman: Bila seksi tidak memiliki jabatan Foreman, Section Head bertindak langsung sebagai Penilai Utama dan langsung submit ke Dept Head.")

# --- HALAMAN 7: LANGKAH 6 - MENILAI BAWAHAN KJ 4-5 ---
doc.add_page_break()
add_header_banner(
    doc, 6, "Menilai Bawahan KJ 4 - 5 (Foreman / Staff / Gol 4)",
    "Prosedur evaluasi bawahan tingkat penyelia lini (Foreman / Leader / Staff KJ 4-5) menggunakan Form 07-004/005 atau Form 07-006 bagi Golongan 4 Non Kasie."
)
path_06 = os.path.join(IMG_DIR, "sh_06_form_kj45_bawahan.png")
if os.path.exists(path_06):
    doc.add_picture(path_06, width=Inches(7.1))
add_callout_table(doc, [
    (1, "Identitas Penyelia KJ 4-5", "Verifikasi data bawahan: Nama lengkap (JOKO HARTONO), Jabatan FOREMAN, Golongan IVA, KJ 6, dan Seksi penempatan."),
    (2, "Aspek Kinerja & Kepemimpinan", "Meliputi: Target Produksi/Proyek, Efisiensi Biaya, Manajemen Anggota Regu, Kaizen / QCC, dan Keselamatan Kerja."),
    (3, "Review Nilai & Skor Section Head", "Tinjau skor evaluasi mandiri dari Foreman, lalu masukkan skor evaluasi objektif Section Head secara seimbang."),
    (4, "Evaluasi Rekomendasi Promosi", "Isi saran promosi ke jenjang berikutnya (calon Section Head / Special Staff) dan kebutuhan pelatihan kepemimpinan."),
    (5, "TTD Digital & Submit Dept Head", "Klik tombol tanda tangan digital Section Head pada kolom Atasan Langsung, lalu simpan draft atau teruskan ke Dept Head.")
])
add_info_callout(doc, "Bawahan Gol 4 Non Kasie: Untuk staf spesialis atau Gol 4 non-struktural di bawah seksi Anda, formulir yang digunakan adalah Form 07-006.")

# --- HALAMAN 8: LANGKAH 7 - RIWAYAT & PELACAKAN ---
doc.add_page_break()
add_header_banner(
    doc, 7, "Riwayat Penilaian & Pelacakan Alur Dokumen",
    "Menu Riwayat Penilaian menyajikan rekam jejak digital lengkap seluruh berkas evaluasi seksi Anda, status alur persetujuan, dan fasilitas cetak PDF ber-barcode."
)
path_07 = os.path.join(IMG_DIR, "sh_07_riwayat.png")
if os.path.exists(path_07):
    doc.add_picture(path_07, width=Inches(7.1))
add_callout_table(doc, [
    (1, "Kartu Metrik Riwayat Seksi", "Menampilkan rekapitulasi berkas: Menunggu Dept Head, Menunggu Div Head, Selesai Dinilai, dan Arsip Selesai."),
    (2, "Filter Periode & Status", "Pilih tahun periode penilaian (2026), filter status alur pengajuan, atau cari nama/NRP anggota seksi tertentu."),
    (3, "Tabel Rekapitulasi Berkas", "Menampilkan rincian: Nomor Dokumen, Nama Anggota, Form, Nilai Akhir, Grade Mutu, dan Badge Status Alur Berkas."),
    (4, "Unduh Berkas PDF Resmi", "Klik tombol Cetak / PDF pada baris dokumen untuk mengunduh berkas fisik resmi ber-QR Code digital yang telah disahkan.")
])
add_info_callout(doc, "Kepastian Alur Pengesahan: Seluruh berkas seksi wajib diselesaikan tepat waktu sebelum batas cut-off agar dapat diproses ke sidang evaluasi Departemen.")

# Save Docx
doc.save(OUT_DOCX)
print(f"DOCX created: {OUT_DOCX} ({os.path.getsize(OUT_DOCX)} bytes)")
safe_copy(OUT_DOCX, OUT_DOCX_USER)
print(f"Copied DOCX to: {OUT_DOCX_USER}")
print("ALL SECTION HEAD GUIDES COMPLETED SUCCESSFULLY!")
