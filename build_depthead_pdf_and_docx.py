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

IMG_DIR = r"C:\Users\USER\Music\E-Approval\storage\app\depthead_annotated"
OUT_HTML = r"C:\Users\USER\Music\E-Approval\storage\app\panduan_depthead.html"
TEMP_PDF = r"C:\Users\USER\Music\E-Approval\storage\app\panduan_depthead_temp.pdf"
OUT_PDF = r"C:\Users\USER\Music\E-Approval\public\panduan\Panduan_Lengkap_Department_Head.pdf"
OUT_PDF_USER = r"C:\Users\USER\Music\Dashboard\PANDUAN_PDF\Panduan_Lengkap_Department_Head.pdf"
OUT_DOCX = r"C:\Users\USER\Music\E-Approval\public\panduan\Panduan_Lengkap_Department_Head.docx"
OUT_DOCX_USER = r"C:\Users\USER\Music\Dashboard\PANDUAN_PDF\Panduan_Lengkap_Department_Head.docx"

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

img_01 = b64_img("dh_01_login.png")
img_02 = b64_img("dh_02_dashboard.png")
img_03 = b64_img("dh_03_form_07006_mandiri.png")
img_04 = b64_img("dh_04_data_penilaian_dept.png")
img_05 = b64_img("dh_05_form_bawahan_dept.png")
img_06 = b64_img("dh_06_penyesuaian_dept.png")
img_07 = b64_img("dh_07_riwayat.png")

# ==========================================
# 1. BUILD HTML FOR PDF (8 PAGES)
# ==========================================
html_content = f"""<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Buku Panduan Department Head - E-Performance Appraisal</title>
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
      <div class="cover-sop">SOP-PA-03-DH</div>
    </div>
    
    <div class="cover-center">
      <div class="cover-badge">MODUL OPERASIONAL RESMI &bull; PANDUAN LENGKAP</div>
      <h1 class="cover-title">PETUNJUK PENGGUNAAN SISTEM E-PERFORMANCE APPRAISAL</h1>
      <div class="cover-subtitle">
        Panduan Komprehensif Peran <strong>Department Head (Kepala Departemen)</strong>: Evaluasi Diri (Form KJ 07-006-DH), Penilaian Seluruh Bawahan Departemen, dan <strong>Kalibrasi Penyesuaian Kuota Nilai</strong>
      </div>
      
      <div class="cover-grid">
        <div class="cover-card">
          <div class="cover-card-title">Peran Pengguna (Role)</div>
          <div class="cover-card-val">Department Head / Deputy Dept Head</div>
        </div>
        <div class="cover-card">
          <div class="cover-card-title">Cakupan Evaluasi Mandiri</div>
          <div class="cover-card-val">Form 07-006-DH (4 Pilar Manajerial 100%)</div>
        </div>
        <div class="cover-card">
          <div class="cover-card-title">Fitur Khusus Departemen</div>
          <div class="cover-card-val">Kalibrasi Penyesuaian Nilai &amp; Kuota Target</div>
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
<!-- HALAMAN 2: LANGKAH 1 - LOGIN & OTENTIKASI DEPT HEAD          -->
<!-- ============================================================ -->
<div class="page">
  <div class="doc-header">
    <div class="company">PT MUSASHI AUTO PARTS INDONESIA</div>
    <div class="tag">PANDUAN DEPT HEAD &bull; HAL. 2</div>
  </div>
  
  <div class="step-badge">LANGKAH 1</div>
  <h1 class="page-title">Akses Portal &amp; Prosedur Masuk (Login)</h1>
  <p class="page-desc">
    Buka peramban (Google Chrome / Microsoft Edge) dan akses portal resmi <strong>https://performanceappraisal.vercel.app/login</strong>. Sistem menggunakan fitur cerdas <em>Auto-Role Detection</em> tanpa pemilihan peran manual.
  </p>
  
  <div class="screenshot-card">
    <img src="{img_01}" alt="Halaman Login Department Head">
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
        <td>Ketikkan Email resmi Department Head (contoh: <code>18depthead@musashi.id</code>) atau Nomor Registrasi Pokok (NRP) 18 pada kolom akun.</td>
      </tr>
      <tr>
        <td><span class="callout-num">2</span></td>
        <td><strong>Input Kata Sandi (Password)</strong></td>
        <td>Masukkan kata sandi akun terdaftar. Gunakan ikon mata di sisi kanan kolom untuk memverifikasi karakter password yang Anda ketikkan.</td>
      </tr>
      <tr>
        <td><span class="callout-num">3</span></td>
        <td><strong>Opsi Ingat Saya (Remember Me)</strong></td>
        <td>Centang kotak ini bila menggunakan komputer kerja resmi agar sesi login tersimpan secara aman dan tidak perlu memasukkan ulang setiap saat.</td>
      </tr>
      <tr>
        <td><span class="callout-num">4</span></td>
        <td><strong>Tombol Masuk Portal (Auto-Route)</strong></td>
        <td>Klik tombol <strong>Masuk Portal</strong>. Sistem otomatis mendeteksi wewenang Department Head dan mengarahkan ke Dashboard Departemen Anda.</td>
      </tr>
    </tbody>
  </table>
  
  <div class="info-box">
    <strong>Otomatisasi Peran (Zero-Role Selector):</strong> Sistem secara otomatis mengenali identitas Department Head. Menu khusus pengesahan berkas departemen, penilaian mandiri ke Division Head, dan menu penyesuaian nilai kuota langsung aktif.
  </div>
  
  <div class="doc-footer">
    <div>SOP-PA-03-DH &bull; Modul Operasional Department Head</div>
    <div>Halaman 2 dari 8</div>
  </div>
</div>

<!-- ============================================================ -->
<!-- HALAMAN 3: LANGKAH 2 - DASHBOARD DEPARTMENT HEAD             -->
<!-- ============================================================ -->
<div class="page">
  <div class="doc-header">
    <div class="company">PT MUSASHI AUTO PARTS INDONESIA</div>
    <div class="tag">PANDUAN DEPT HEAD &bull; HAL. 3</div>
  </div>
  
  <div class="step-badge">LANGKAH 2</div>
  <h1 class="page-title">Navigasi Dashboard &amp; Monitoring Departemen</h1>
  <p class="page-desc">
    Dashboard Department Head menyajikan ringkasan komprehensif: status pengingat tugas penilaian mandiri, metrik progres tim departemen, serta pelacakan alur dokumen 4 tahap secara transparan.
  </p>
  
  <div class="screenshot-card">
    <img src="{img_02}" alt="Dashboard Department Head">
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
        <td>Akses langsung ke menu: <em>Dashboard</em>, <em>Data Penilaian</em>, <em>Penyesuaian Penilaian</em>, <em>Form Penilaian</em>, dan <em>Riwayat Penilaian</em>.</td>
      </tr>
      <tr>
        <td><span class="callout-num">2</span></td>
        <td><strong>Banner Status Tugas Departemen</strong></td>
        <td>Notifikasi dinamis status pengesahan: menunjukkan jumlah berkas bawahan yang telah disahkan penuh atau yang masih memerlukan tindakan.</td>
      </tr>
      <tr>
        <td><span class="callout-num">3</span></td>
        <td><strong>Kartu Metrik KPI Departemen</strong></td>
        <td>Menampilkan ringkasan: Total Karyawan Departemen, Target Kerja, Berkas Dinilai Foreman, dan Berkas Dinilai Section Head.</td>
      </tr>
      <tr>
        <td><span class="callout-num">4</span></td>
        <td><strong>Pelacakan Alur Status 4 Tahap</strong></td>
        <td>Monitoring posisi berkas: <em>Tahap 1 Foreman</em> &rarr; <em>Tahap 2 Section Head</em> &rarr; <em>Tahap 3 Approval Dept Head</em> &rarr; <em>Tahap 4 Division Head</em>.</td>
      </tr>
      <tr>
        <td><span class="callout-num">5</span></td>
        <td><strong>Tombol Buka Data Penilaian</strong></td>
        <td>Akses cepat menuju lembar kerja tabel penilaian seluruh karyawan departemen untuk melakukan review dan pengesahan nilai akhir.</td>
      </tr>
    </tbody>
  </table>
  
  <div class="info-box">
    <strong>Peran Kunci Department Head:</strong> Menjadi penentu nilai kinerja akhir departemen sebelum diteruskan ke Division Head, serta bertanggung jawab penuh atas kalibrasi distribusi nilai agar seimbang dengan target kuota perusahaan.
  </div>
  
  <div class="doc-footer">
    <div>SOP-PA-03-DH &bull; Modul Operasional Department Head</div>
    <div>Halaman 3 dari 8</div>
  </div>
</div>

<!-- ============================================================ -->
<!-- HALAMAN 4: LANGKAH 3 - PENILAIAN MANDIRI FORM 07-006-DH     -->
<!-- ============================================================ -->
<div class="page">
  <div class="doc-header">
    <div class="company">PT MUSASHI AUTO PARTS INDONESIA</div>
    <div class="tag">PANDUAN DEPT HEAD &bull; HAL. 4</div>
  </div>
  
  <div class="step-badge">LANGKAH 3</div>
  <h1 class="page-title">Penilaian Mandiri Department Head (Form KJ 07-006)</h1>
  <p class="page-desc">
    Department Head mengisi evaluasi kinerja diri sendiri menggunakan standar kompetensi manajerial <strong>Form 07-006-DH</strong> pada kolom <em>Mandiri / 自分で</em> sebelum berkas dievaluasi oleh Division Head.
  </p>
  
  <div class="screenshot-card">
    <img src="{img_03}" alt="Form Penilaian Mandiri 07-006 Department Head">
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
        <td><strong>Identitas Pejabat Dept Head</strong></td>
        <td>Verifikasi identitas diri: Nama lengkap (YUDI SANJAYA), NRP 18, Golongan IVE, Jabatan DEPUTY DEPT HEAD, dan Departemen GA.</td>
      </tr>
      <tr>
        <td><span class="callout-num">2</span></td>
        <td><strong>4 Pilar Kompetensi Manajerial</strong></td>
        <td>Meliputi 4 aspek evaluasi: (1) <strong>Hasil Kerja (55%)</strong>, (2) <strong>Proses Kerja (15%)</strong>, (3) <strong>Sikap Kerja (15%)</strong>, dan (4) <strong>Man Management (15%)</strong>.</td>
      </tr>
      <tr>
        <td><span class="callout-num">3</span></td>
        <td><strong>Kolom Skor Mandiri / 自分で</strong></td>
        <td>Input nilai evaluasi diri (skala 1 - 100) pada setiap butir penilaian. Total nilai dan bobot otomatis dihitung oleh kalkulator sistem.</td>
      </tr>
      <tr>
        <td><span class="callout-num">4</span></td>
        <td><strong>Indikator Evaluasi Potensi Diri</strong></td>
        <td>Pilih indikator kesiapan tugas tingkat divisi, kompetensi strategis, dan potensi pengembangan kepemimpinan korporat.</td>
      </tr>
      <tr>
        <td><span class="callout-num">5</span></td>
        <td><strong>Tanda Tangan Digital &amp; Submit</strong></td>
        <td>Klik tombol <strong>Tanda Tangan Digital</strong> pada kolom <em>Kary. Ybs / 個人的</em>, lalu klik <strong>Simpan Draft</strong> atau <strong>Kirim ke Division Head</strong>.</td>
      </tr>
    </tbody>
  </table>
  
  <div class="warn-box">
    <strong>Bebas Target Kuota (Form 07-006):</strong> Penilaian pejabat struktural (Department Head &amp; Division Head) tidak terikat kuota docking nilai dan kunci finalisasi selalu terbuka mandiri ke atasan langsung (Division Head).
  </div>
  
  <div class="doc-footer">
    <div>SOP-PA-03-DH &bull; Modul Operasional Department Head</div>
    <div>Halaman 4 dari 8</div>
  </div>
</div>

<!-- ============================================================ -->
<!-- HALAMAN 5: LANGKAH 4 - DATA PENILAIAN DEPARTEMEN             -->
<!-- ============================================================ -->
<div class="page">
  <div class="doc-header">
    <div class="company">PT MUSASHI AUTO PARTS INDONESIA</div>
    <div class="tag">PANDUAN DEPT HEAD &bull; HAL. 5</div>
  </div>
  
  <div class="step-badge">LANGKAH 4</div>
  <h1 class="page-title">Data Penilaian Karyawan Departemen (/penilaian/dept)</h1>
  <p class="page-desc">
    Tabel konsolidasi seluruh personel departemen dilengkapi kolom komparasi multi-level cerdas: <strong>Hasil Foreman, Hasil Mandiri, dan Hasil Section Head</strong> untuk memudahkan perbandingan sebelum penetapan nilai akhir.
  </p>
  
  <div class="screenshot-card">
    <img src="{img_04}" alt="Tabel Data Penilaian Departemen">
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
        <td><strong>Kartu Ringkasan Status Evaluasi</strong></td>
        <td>Memantau ringkasan: <em>Total Karyawan</em>, <em>Siap Dinilai (To-Do)</em>, <em>Proses Foreman/Mandiri</em>, dan <em>Diteruskan/Selesai</em>.</td>
      </tr>
      <tr>
        <td><span class="callout-num">2</span></td>
        <td><strong>Filter Tabs &amp; Kolom Pencarian</strong></td>
        <td>Saring karyawan dengan tab: <em>Semua</em>, <em>Siap Dinilai</em>, <em>Proses Awal</em>, <em>Selesai</em>, atau filter spesifik <em>Section</em> &amp; <em>Position</em>.</td>
      </tr>
      <tr>
        <td><span class="callout-num">3</span></td>
        <td><strong>Kolom Komparasi Multi-Level</strong></td>
        <td>Menampilkan nilai bertingkat: <strong>HASIL FOREMAN</strong>, <strong>HASIL PENILAIAN MANDIRI</strong>, dan <strong>HASIL SECTION HEAD</strong> secara transparan.</td>
      </tr>
      <tr>
        <td><span class="callout-num">4</span></td>
        <td><strong>Kolom Nilai Akhir &amp; Catatan Dept</strong></td>
        <td>Input nilai evaluasi Dept Head, catatan pembinaan karyawan, serta penetapan grade kinerja departemen.</td>
      </tr>
      <tr>
        <td><span class="callout-num">5</span></td>
        <td><strong>Status Alur &amp; Tombol Aksi Nilai</strong></td>
        <td>Badge status alur (misal: <em>Proses Penilaian Mandiri</em>, <em>Menunggu Dept Head</em>) dan tombol <strong>Nilai / Buka Form</strong>.</td>
      </tr>
    </tbody>
  </table>
  
  <div class="info-box">
    <strong>Proteksi Berkas Terkunci (Auto-Locking):</strong> Jika bawahan (seperti Foreman atau Gol 4 Non Kasie) belum menyelesaikan penilaian mandirinya, tombol aksi berstatus 'Lihat' dan form terkunci agar hak evaluasi diri bawahan terlindungi penuh.
  </div>
  
  <div class="doc-footer">
    <div>SOP-PA-03-DH &bull; Modul Operasional Department Head</div>
    <div>Halaman 5 dari 8</div>
  </div>
</div>

<!-- ============================================================ -->
<!-- HALAMAN 6: LANGKAH 5 - MENILAI BAWAHAN DEPARTEMEN            -->
<!-- ============================================================ -->
<div class="page">
  <div class="doc-header">
    <div class="company">PT MUSASHI AUTO PARTS INDONESIA</div>
    <div class="tag">PANDUAN DEPT HEAD &bull; HAL. 6</div>
  </div>
  
  <div class="step-badge">LANGKAH 5</div>
  <h1 class="page-title">Menilai Bawahan Departemen (Form Penilaian Karya)</h1>
  <p class="page-desc">
    Prosedur verifikasi dan penetapan nilai atasan oleh Department Head pada Form Penilaian Karya (Form 07-001/002/003 untuk pelaksana, 07-004/005 untuk penyelia, dan 07-006 untuk Section Head / Gol 4 Non Kasie).
  </p>
  
  <div class="screenshot-card">
    <img src="{img_05}" alt="Form Penilaian Bawahan Departemen">
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
        <td><strong>Identitas Bawahan Departemen</strong></td>
        <td>Verifikasi data bawahan: Nama lengkap (IQBAL YUSRA), NRP 1414, Golongan IVA, Jabatan GOL 4 NON KASIE, Unit GA Cikarang.</td>
      </tr>
      <tr>
        <td><span class="callout-num">2</span></td>
        <td><strong>Banner Informasi Status Alur</strong></td>
        <td>Menampilkan status ketersediaan formulir: apakah siap dinilai atau masih menunggu tanda tangan evaluasi mandiri karyawan.</td>
      </tr>
      <tr>
        <td><span class="callout-num">3</span></td>
        <td><strong>Aspek Kinerja &amp; Uraian Standar</strong></td>
        <td>Memuat rincian aspek penilaian teknis dan manajerial sesuai kode form (Kualitas, Kuantitas, Kaizen, Disiplin, dsb).</td>
      </tr>
      <tr>
        <td><span class="callout-num">4</span></td>
        <td><strong>Kolom Input Nilai Dept Head</strong></td>
        <td>Review skor yang diberikan oleh bawahan/atasan lini sebelumnya, lalu masukkan skor evaluasi Department Head (skala 1 - 100).</td>
      </tr>
      <tr>
        <td><span class="callout-num">5</span></td>
        <td><strong>Pengesahan TTD Digital &amp; Submit</strong></td>
        <td>Bubuhkan tanda tangan digital pada kolom <em>Atasan Langsung / Dept. Head</em>, lalu teruskan berkas ke Division Head.</td>
      </tr>
    </tbody>
  </table>
  
  <div class="info-box">
    <strong>Hak Prerogatif Evaluasi Potensi:</strong> Department Head berhak mengisi kotak centang rekomendasi promosi jabatan atau rotasi tugas bawahan serta mencantumkan saran pengembangan karir dan jenis pelatihan yang relevan.
  </div>
  
  <div class="doc-footer">
    <div>SOP-PA-03-DH &bull; Modul Operasional Department Head</div>
    <div>Halaman 6 dari 8</div>
  </div>
</div>

<!-- ============================================================ -->
<!-- HALAMAN 7: LANGKAH 6 - PENYESUAIAN PENILAIAN & KALIBRASI     -->
<!-- ============================================================ -->
<div class="page">
  <div class="doc-header">
    <div class="company">PT MUSASHI AUTO PARTS INDONESIA</div>
    <div class="tag">PANDUAN DEPT HEAD &bull; HAL. 7</div>
  </div>
  
  <div class="step-badge">LANGKAH 6 &bull; FITUR KHUSUS</div>
  <h1 class="page-title">Halaman Penyesuaian Penilaian (/penilaian/dept/penyesuaian)</h1>
  <p class="page-desc">
    Fitur kalibrasi eksklusif Department Head untuk menyelaraskan distribusi peringkat nilai karyawan (Rank S, A, B, C, K) agar sesuai dengan kuota target perusahaan (aturan Bell Curve / Normal Distribution) sebelum finalisasi.
  </p>
  
  <div class="screenshot-card">
    <img src="{img_06}" alt="Halaman Penyesuaian Penilaian Departemen">
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
        <td><strong>Tombol Aksi Kalibrasi Nilai</strong></td>
        <td>Terdiri atas: (a) <strong>Reset</strong> untuk membatalkan perubahan, (b) <strong>Sesuaikan Target (Auto-Adjust)</strong>, dan (c) <strong>Simpan Penyesuaian</strong>.</td>
      </tr>
      <tr>
        <td><span class="callout-num">2</span></td>
        <td><strong>Banner Validasi Distribusi Kuota</strong></td>
        <td>Indikator cerdas: Hijau (Distribusi Nilai Seimbang &amp; Sesuai Target Kuota) atau Kuning/Merah jika ada kuota grade yang terlampaui.</td>
      </tr>
      <tr>
        <td><span class="callout-num">3</span></td>
        <td><strong>Kartu Alokasi Peringkat Kuota</strong></td>
        <td>Menampilkan perbandingan <em>Aktual Saat Ini</em> vs <em>Target</em> untuk tiap grade: <strong>RANK S</strong>, <strong>RANK A</strong>, <strong>RANK B</strong>, <strong>RANK C</strong>, dan <strong>RANK K</strong>.</td>
      </tr>
      <tr>
        <td><span class="callout-num">4</span></td>
        <td><strong>Tabel Target &amp; Realisasi Per Jenjang KJ</strong></td>
        <td>Rincian target orang per jenjang operasional (KJ 2, KJ 3, KJ 4, KJ 5) dengan status kuota <em>Pas</em> atau <em>Over</em> dan tombol Filter KJ.</td>
      </tr>
      <tr>
        <td><span class="callout-num">5</span></td>
        <td><strong>Navigasi Menu Penyesuaian Nilai</strong></td>
        <td>Menu pintas pada sidebar untuk mengakses modul penyesuaian kuota kapan pun selama masa sidang kalibrasi departemen berlangsung.</td>
      </tr>
    </tbody>
  </table>
  
  <div class="info-box">
    <strong>Fitur Cerdas 'Sesuaikan Target' (Auto-Adjust):</strong> Jika distribusi nilai departemen belum seimbang, klik tombol <strong>Sesuaikan Target</strong>. Sistem secara otomatis mengkalkulasi penyesuaian docking nilai mikro secara proporsional hingga seluruh kuota terpenuhi sempurna.
  </div>
  
  <div class="doc-footer">
    <div>SOP-PA-03-DH &bull; Modul Operasional Department Head</div>
    <div>Halaman 7 dari 8</div>
  </div>
</div>

<!-- ============================================================ -->
<!-- HALAMAN 8: LANGKAH 7 - RIWAYAT & ARSIP HISTORIS DEPARTEMEN   -->
<!-- ============================================================ -->
<div class="page">
  <div class="doc-header">
    <div class="company">PT MUSASHI AUTO PARTS INDONESIA</div>
    <div class="tag">PANDUAN DEPT HEAD &bull; HAL. 8</div>
  </div>
  
  <div class="step-badge">LANGKAH 7</div>
  <h1 class="page-title">Riwayat Penilaian &amp; Pelacakan Alur Dokumen</h1>
  <p class="page-desc">
    Menu <strong>Riwayat Penilaian</strong> menyajikan rekapitulasi historis menyeluruh berkas evaluasi departemen, metrik skor akumulasi, perbandingan kuota target vs aktual, serta fitur cetak PDF resmi ber-barcode verifikasi.
  </p>
  
  <div class="screenshot-card">
    <img src="{img_07}" alt="Riwayat Penilaian & Rekap Historis">
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
        <td><strong>Metrik Historis Skor Departemen</strong></td>
        <td>Statistik kinerja departemen: <em>Total Sudah Dinilai</em>, <em>Belum Dinilai</em>, <em>Akumulasi Poin Skor</em>, dan <em>Rata-Rata Skor</em>.</td>
      </tr>
      <tr>
        <td><span class="callout-num">2</span></td>
        <td><strong>Tabel Target Kuota vs Actual Dept Head</strong></td>
        <td>Tabel komparasi matriks jenjang KJ terhadap target kuota (S, A, B, C, K) dibandingkan realisasi aktual pengesahan Department Head.</td>
      </tr>
      <tr>
        <td><span class="callout-num">3</span></td>
        <td><strong>Daftar Karyawan Selesai Dinilai</strong></td>
        <td>Rekapitulasi berkas terverifikasi: NRP, Nama Lengkap, Seksi, Posisi, KJ, Golongan, Tanggal Penilaian, Nilai Akhir, dan Grade.</td>
      </tr>
      <tr>
        <td><span class="callout-num">4</span></td>
        <td><strong>Unduh Berkas PDF Resmi (Barcode)</strong></td>
        <td>Fasilitas pencetakan dan unduh salinan berkas fisik PDF resmi yang telah dilengkapi tanda tangan digital dan QR Code validasi keaslian.</td>
      </tr>
    </tbody>
  </table>
  
  <div class="info-box">
    <strong>Arsip Digital Permanen:</strong> Berkas yang telah disahkan oleh Department Head dan disetujui Division Head akan otomatis terarsip dalam database audit HRD dan dapat ditelusuri riwayatnya untuk keperluan promosi, mutasi, dan suksesi.
  </div>
  
  <div class="doc-footer">
    <div>SOP-PA-03-DH &bull; Modul Operasional Department Head</div>
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
print("Generating Department Head Word Document (.docx)...")
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
    
    r_sop = p_head.add_run("   |   SOP-PA-03-DH   |   MODUL RESMI DEPARTMENT HEAD")
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

r_tag = p_cov.add_run("STANDAR OPERASIONAL PROSEDUR (SOP) &bull; SOP-PA-03-DH\n\n")
r_tag.font.name = "Arial"
r_tag.font.size = Pt(9.5)
r_tag.font.bold = True
r_tag.font.color.rgb = C_RED

r_title = p_cov.add_run("BUKU PANDUAN PENGGUNAAN SISTEM\nE-PERFORMANCE APPRAISAL\n")
r_title.font.name = "Arial"
r_title.font.size = Pt(22)
r_title.font.bold = True
r_title.font.color.rgb = C_NAVY

r_sub = p_cov.add_run("Pedoman Komprehensif Peran Department Head: Penilaian Mandiri (Form KJ 07-006-DH), Penilaian Seluruh Bawahan Departemen, dan Halaman Kalibrasi Penyesuaian Kuota Nilai\n\n")
r_sub.font.name = "Calibri"
r_sub.font.size = Pt(12)
r_sub.font.color.rgb = C_GRAY

# Cover Table
t_meta = doc.add_table(rows=5, cols=2)
t_meta.alignment = WD_TABLE_ALIGNMENT.CENTER
meta_data = [
    ("Peran Pengguna (Role):", "Department Head / Deputy Dept Head"),
    ("Cakupan Penilaian Mandiri:", "Form 07-006-DH (4 Pilar Manajerial 100%)"),
    ("Cakupan Penilaian Bawahan:", "Seluruh Personel Departemen (Operator, Foreman, Staff, Kasie)"),
    ("Fitur Khusus Departemen:", "Modul Kalibrasi & Penyesuaian Kuota Nilai (/penilaian/dept/penyesuaian)"),
    ("Alamat Portal & Versi Sistem:", "https://performanceappraisal.vercel.app/ - Versi 2.0 (Resmi 2026)")
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
path_01 = os.path.join(IMG_DIR, "dh_01_login.png")
if os.path.exists(path_01):
    doc.add_picture(path_01, width=Inches(7.1))
add_callout_table(doc, [
    (1, "Input Email / NRP", "Ketikkan email resmi Department Head (contoh: 18depthead@musashi.id) atau NRP 18 pada kolom isian akun."),
    (2, "Input Kata Sandi", "Masukkan password akun terdaftar. Gunakan ikon mata bila ingin memverifikasi karakter password yang Anda ketikkan."),
    (3, "Opsi Ingat Saya", "Centang kotak Ingat Saya bila menggunakan laptop/komputer kerja resmi agar sesi login tersimpan secara aman."),
    (4, "Tombol Masuk Portal", "Klik Masuk Portal. Sistem mendeteksi otomatis peran Department Head dan langsung membuka menu Dashboard Departemen.")
])
add_info_callout(doc, "Fitur Otomatisasi Peran: Sistem secara cerdas mengidentifikasi wewenang Department Head dan langsung membuka hak akses penuh terhadap seluruh berkas seksi.")

# --- HALAMAN 3: LANGKAH 2 - DASHBOARD ---
doc.add_page_break()
add_header_banner(
    doc, 2, "Navigasi Dashboard & Monitoring Progres Departemen",
    "Dashboard Department Head menyajikan ringkasan terpadu: status pengingat evaluasi mandiri, metrik progres tim departemen, dan pelacakan alur dokumen 4 tahap."
)
path_02 = os.path.join(IMG_DIR, "dh_02_dashboard.png")
if os.path.exists(path_02):
    doc.add_picture(path_02, width=Inches(7.1))
add_callout_table(doc, [
    (1, "Sidebar Navigasi Utama", "Akses menu utama aplikasi: Dashboard, Data Penilaian, Penyesuaian Penilaian, Form Penilaian, dan Riwayat Penilaian."),
    (2, "Banner Status Tugas", "Pemberitahuan aktif yang menginformasikan status penyelesaian dokumen evaluasi seluruh departemen."),
    (3, "Kartu Metrik KPI Departemen", "Memantau statistik total anggota departemen, target kerja, berkas dinilai Foreman, dan berkas dinilai Section Head."),
    (4, "Pelacakan Alur Status 4 Tahap", "Monitoring posisi berkas secara transparan: Tahap 1 Foreman, Tahap 2 Section Head, Tahap 3 Approval Dept Head, dan Tahap 4 Approval Div Head."),
    (5, "Tombol Cepat Data Penilaian", "Klik tombol Buka Data Penilaian untuk langsung memeriksa tabel penilaian seluruh karyawan departemen.")
])
add_info_callout(doc, "Peran Kunci Department Head: Memastikan seluruh dokumen dari seksi di bawah departemen telah lengkap dan nilai akhir terkalibrasi secara adil.")

# --- HALAMAN 4: LANGKAH 3 - FORM 07-006-DH MANDIRI ---
doc.add_page_break()
add_header_banner(
    doc, 3, "Penilaian Mandiri Department Head (Form KJ 07-006)",
    "Department Head wajib mengisi evaluasi kinerja diri sendiri pada Form 07-006-DH di Kolom Mandiri / 自分で sebelum dokumen diajukan ke Division Head."
)
path_03 = os.path.join(IMG_DIR, "dh_03_form_07006_mandiri.png")
if os.path.exists(path_03):
    doc.add_picture(path_03, width=Inches(7.1))
add_callout_table(doc, [
    (1, "Identitas Pejabat Dept Head", "Verifikasi data identitas: Nama lengkap (YUDI SANJAYA), NRP 18, Golongan IVE, Jabatan DEPUTY DEPT HEAD, Departemen GA."),
    (2, "4 Pilar Manajerial (100%)", "Empat dimensi kompetensi: Hasil Kerja (55%), Proses Kerja (15%), Sikap Kerja (15%), dan Man Management (15%)."),
    (3, "Kolom Skor Mandiri", "Input skor evaluasi mandiri (1 - 100) pada tiap kriteria. Total nilai dan bobot otomatis dihitung oleh kalkulator sistem."),
    (4, "Evaluasi Potensi Diri", "Tinjau kriteria evaluasi potensi pengembangan karir, kompetensi strategis, dan kesiapan manajerial tingkat divisi."),
    (5, "TTD Digital & Submit Div Head", "Klik tombol Tanda Tangan Digital pada kolom Kary. Ybs / 個人的, lalu klik Simpan Draft atau Kirim ke Division Head.")
])
add_info_callout(doc, "Bebas Target Kuota: Penilaian mandiri pejabat struktural Form 07-006 tidak terikat kuota docking nilai dan kunci finalisasi selalu terbuka mandiri.")

# --- HALAMAN 5: LANGKAH 4 - DATA PENILAIAN DEPARTEMEN ---
doc.add_page_break()
add_header_banner(
    doc, 4, "Data Penilaian Karyawan Departemen (/penilaian/dept)",
    "Tabel konsolidasi seluruh personel departemen dilengkapi kolom komparasi multi-level: Hasil Foreman, Hasil Mandiri, dan Hasil Section Head."
)
path_04 = os.path.join(IMG_DIR, "dh_04_data_penilaian_dept.png")
if os.path.exists(path_04):
    doc.add_picture(path_04, width=Inches(7.1))
add_callout_table(doc, [
    (1, "Kartu Ringkasan Status Evaluasi", "Memantau statistik: Total Karyawan, Siap Dinilai (To-Do), Proses Foreman/Mandiri, dan Diteruskan/Selesai."),
    (2, "Filter Tabs & Pencarian", "Saring karyawan dengan tab: Semua, Siap Dinilai, Proses Awal, Selesai, atau filter dropdown Section & Position."),
    (3, "Kolom Komparasi Multi-Level", "Menampilkan nilai bertingkat: HASIL FOREMAN, HASIL PENILAIAN MANDIRI, dan HASIL SECTION HEAD secara transparan."),
    (4, "Kolom Nilai Akhir & Catatan Dept", "Input skor evaluasi Department Head, catatan rekomendasi pembinaan karyawan, serta penetapan grade kinerja."),
    (5, "Status Alur & Tombol Aksi Nilai", "Badge status alur dokumen (misal: Proses Penilaian Mandiri, Menunggu Dept Head) dan tombol Nilai / Buka Form.")
])
add_info_callout(doc, "Proteksi Form Terkunci: Bila bawahan belum menyelesaikan penilaian mandirinya, tombol aksi berstatus 'Lihat' dan form terkunci agar hak bawahan terlindungi.")

# --- HALAMAN 6: LANGKAH 5 - MENILAI BAWAHAN DEPARTEMEN ---
doc.add_page_break()
add_header_banner(
    doc, 5, "Menilai Bawahan Departemen (Form Penilaian Karya)",
    "Prosedur verifikasi dan penetapan nilai atasan oleh Department Head pada Form Penilaian Karya seluruh tingkatan personel departemen."
)
path_05 = os.path.join(IMG_DIR, "dh_05_form_bawahan_dept.png")
if os.path.exists(path_05):
    doc.add_picture(path_05, width=Inches(7.1))
add_callout_table(doc, [
    (1, "Identitas Bawahan Departemen", "Verifikasi data bawahan: Nama lengkap (IQBAL YUSRA), NRP 1414, Golongan IVA, Jabatan GOL 4 NON KASIE, Unit GA Cikarang."),
    (2, "Banner Status Alur Berkas", "Menampilkan status ketersediaan formulir: apakah siap dinilai atau masih dalam tahap pengisian evaluasi mandiri."),
    (3, "Aspek Kinerja & Standar Kompetensi", "Memuat rincian aspek penilaian teknis dan manajerial sesuai kode form (Kualitas, Kuantitas, Kaizen, Disiplin, dsb)."),
    (4, "Kolom Input Nilai Dept Head", "Review skor dari penilai lini sebelumnya, lalu masukkan skor evaluasi Department Head (skala 1 - 100)."),
    (5, "Pengesahan TTD Digital & Submit", "Bubuhkan tanda tangan digital pada kolom Atasan Langsung / Dept. Head, lalu teruskan berkas ke Division Head.")
])
add_info_callout(doc, "Hak Evaluasi Potensi: Department Head berhak mengisi rekomendasi promosi jabatan atau rotasi serta mencantumkan saran pelatihan yang relevan.")

# --- HALAMAN 7: LANGKAH 6 - PENYESUAIAN PENILAIAN & KALIBRASI ---
doc.add_page_break()
add_header_banner(
    doc, 6, "Halaman Penyesuaian Penilaian (/penilaian/dept/penyesuaian)",
    "Fitur kalibrasi eksklusif Department Head untuk menyelaraskan distribusi peringkat nilai karyawan (Rank S, A, B, C, K) agar sesuai kuota target perusahaan."
)
path_06 = os.path.join(IMG_DIR, "dh_06_penyesuaian_dept.png")
if os.path.exists(path_06):
    doc.add_picture(path_06, width=Inches(7.1))
add_callout_table(doc, [
    (1, "Tombol Aksi Kalibrasi Nilai", "Terdiri atas: (a) Reset untuk membatalkan perubahan, (b) Sesuaikan Target (Auto-Adjust), dan (c) Simpan Penyesuaian."),
    (2, "Banner Validasi Distribusi Kuota", "Indikator cerdas: Hijau (Distribusi Nilai Seimbang & Sesuai Target Kuota) atau Kuning/Merah jika kuota grade terlampaui."),
    (3, "Kartu Alokasi Peringkat Kuota", "Menampilkan perbandingan Aktual Saat Ini vs Target untuk tiap grade: RANK S, RANK A, RANK B, RANK C, dan RANK K."),
    (4, "Tabel Target & Realisasi Per KJ", "Rincian target orang per jenjang operasional (KJ 2, KJ 3, KJ 4, KJ 5) dengan status kuota Pas atau Over dan tombol Filter KJ."),
    (5, "Menu Pintas Penyesuaian Nilai", "Menu pintas pada sidebar untuk mengakses modul penyesuaian kuota kapan pun selama masa sidang kalibrasi berlangsung.")
])
add_info_callout(doc, "Fitur Cerdas 'Sesuaikan Target' (Auto-Adjust): Sistem secara otomatis mengkalkulasi docking nilai mikro secara proporsional hingga seluruh kuota terpenuhi.")

# --- HALAMAN 8: LANGKAH 7 - RIWAYAT & ARSIP HISTORIS ---
doc.add_page_break()
add_header_banner(
    doc, 7, "Riwayat Penilaian & Pelacakan Alur Dokumen",
    "Menu Riwayat Penilaian menyajikan rekapitulasi historis menyeluruh berkas evaluasi departemen, metrik skor akumulasi, dan unduh berkas PDF resmi."
)
path_07 = os.path.join(IMG_DIR, "dh_07_riwayat.png")
if os.path.exists(path_07):
    doc.add_picture(path_07, width=Inches(7.1))
add_callout_table(doc, [
    (1, "Metrik Historis Skor Departemen", "Statistik kinerja departemen: Total Sudah Dinilai, Belum Dinilai, Akumulasi Poin Skor, dan Rata-Rata Skor."),
    (2, "Tabel Kuota Target vs Actual Dept", "Tabel komparasi matriks jenjang KJ terhadap target kuota (S, A, B, C, K) dibandingkan realisasi aktual Dept Head."),
    (3, "Daftar Karyawan Selesai Dinilai", "Rekapitulasi berkas terverifikasi: NRP, Nama Lengkap, Seksi, Posisi, KJ, Golongan, Tanggal, Nilai Akhir, dan Grade."),
    (4, "Unduh Berkas PDF Resmi (Barcode)", "Fasilitas pencetakan dan unduh salinan berkas fisik PDF resmi yang telah dilengkapi tanda tangan digital dan QR Code validasi.")
])
add_info_callout(doc, "Arsip Digital Permanen: Berkas yang disahkan Department Head dan disetujui Division Head tersimpan permanen dalam database audit HRD.")

# Save Docx
doc.save(OUT_DOCX)
print(f"DOCX created: {OUT_DOCX} ({os.path.getsize(OUT_DOCX)} bytes)")
safe_copy(OUT_DOCX, OUT_DOCX_USER)
print(f"Copied DOCX to: {OUT_DOCX_USER}")
print("ALL DEPARTMENT HEAD GUIDES COMPLETED SUCCESSFULLY!")
