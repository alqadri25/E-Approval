import os
from PIL import Image, ImageDraw, ImageFont

SS_DIR = r"C:\Users\USER\Music\E-Approval\storage\app\depthead_screenshots"
ANN_DIR = r"C:\Users\USER\Music\E-Approval\storage\app\depthead_annotated"
os.makedirs(ANN_DIR, exist_ok=True)

try:
    font_sm = ImageFont.truetype(r"C:\Windows\Fonts\segoeuib.ttf", 16)
except:
    font_sm = ImageFont.load_default()

def draw_callout(draw, box, number, badge_pos=None):
    x0, y0, x1, y1 = box
    draw.rectangle([x0, y0, x1, y1], outline="#e5252a", width=3)
    r = 14
    if badge_pos is not None:
        cx, cy = badge_pos
    else:
        cx = x0 - 5
        cy = y0 - 5
    draw.ellipse([cx - r, cy - r, cx + r, cy + r], fill="#e5252a", outline="#ffffff", width=2)
    num_str = str(number)
    bbox = font_sm.getbbox(num_str)
    w = bbox[2] - bbox[0]
    h = bbox[3] - bbox[1]
    draw.text((cx - w/2, cy - h/2 - 2), num_str, fill="#ffffff", font=font_sm)

# 1. Login (1280x800)
im1 = Image.open(os.path.join(SS_DIR, "ss_depthead_01_login.png"))
d1 = ImageDraw.Draw(im1)
draw_callout(d1, (715, 280, 1035, 320), 1)
draw_callout(d1, (715, 325, 1035, 365), 2)
draw_callout(d1, (715, 350, 780, 370), 3)
draw_callout(d1, (715, 365, 1040, 415), 4)
im1.save(os.path.join(ANN_DIR, "dh_01_login.png"))

# 2. Dashboard Department Head (1280x850)
im2 = Image.open(os.path.join(SS_DIR, "ss_depthead_02_dashboard.png"))
d2 = ImageDraw.Draw(im2)
draw_callout(d2, (10, 70, 245, 480), 1, badge_pos=(5, 65))        # Sidebar Navigasi Utama
draw_callout(d2, (240, 160, 1240, 248), 2, badge_pos=(235, 155))   # Alert Box Status Tugas & Pengingat Mandiri
draw_callout(d2, (240, 270, 1240, 415), 3, badge_pos=(235, 265))   # Kartu KPI & Progres Evaluasi Departemen
draw_callout(d2, (240, 440, 1240, 745), 4, badge_pos=(235, 435))   # Pelacakan Alur Status Dokumen Bawahan (4 Tahap)
draw_callout(d2, (1070, 55, 1215, 105), 5, badge_pos=(1065, 50))   # Tombol Cepat Buka Data Penilaian Departemen
im2.save(os.path.join(ANN_DIR, "dh_02_dashboard.png"))

# 3. Form 07-006-DH Penilaian Mandiri Department Head (1280x950)
im3 = Image.open(os.path.join(SS_DIR, "ss_depthead_03_form_07006_mandiri.png"))
d3 = ImageDraw.Draw(im3)
draw_callout(d3, (240, 250, 790, 425), 1, badge_pos=(235, 245))  # Identitas Pejabat Dept Head (Yudi Sanjaya, 18, IVE)
draw_callout(d3, (240, 425, 600, 945), 2, badge_pos=(235, 420))  # 4 Pilar Kompetensi (Hasil 55%, Proses 15%, Sikap 15%, Man Mgmt 15%)
draw_callout(d3, (600, 425, 680, 945), 3, badge_pos=(640, 420))  # Kolom Skor Mandiri / 自分で
draw_callout(d3, (680, 425, 1240, 945), 4, badge_pos=(690, 420)) # Indikator Evaluasi Potensi Diri
draw_callout(d3, (790, 250, 1240, 425), 5, badge_pos=(785, 245)) # Kolom TTD Digital Kary. Ybs & Kolom Div. Head
im3.save(os.path.join(ANN_DIR, "dh_03_form_07006_mandiri.png"))

# 4. Data Penilaian Departemen (1360x850)
im4 = Image.open(os.path.join(SS_DIR, "ss_depthead_04_data_penilaian_dept.png"))
d4 = ImageDraw.Draw(im4)
draw_callout(d4, (280, 180, 1320, 350), 1, badge_pos=(275, 175))   # Kartu Ringkasan Status Evaluasi Departemen
draw_callout(d4, (280, 540, 1320, 620), 2, badge_pos=(275, 535))   # Filter Tabs & Kolom Pencarian Karyawan
draw_callout(d4, (745, 660, 1180, 845), 3, badge_pos=(740, 655))   # Kolom Komparasi Multi-Level (Foreman, Mandiri, Section Head)
draw_callout(d4, (1180, 660, 1320, 845), 4, badge_pos=(1175, 655)) # Kolom Nilai Akhir Dept Head & Catatan
draw_callout(d4, (280, 660, 745, 845), 5, badge_pos=(275, 655))    # Identitas Karyawan & Jabatan (NRP, Nama, Form)
im4.save(os.path.join(ANN_DIR, "dh_04_data_penilaian_dept.png"))

# 5. Form Penilaian Bawahan Departemen (1280x950)
im5 = Image.open(os.path.join(SS_DIR, "ss_depthead_05_form_bawahan_dept.png"))
d5 = ImageDraw.Draw(im5)
draw_callout(d5, (240, 290, 790, 465), 1, badge_pos=(235, 285))   # Identitas Bawahan Departemen (Iqbal Yusra, 1414, IVA)
draw_callout(d5, (218, 120, 1240, 225), 2, badge_pos=(213, 115))  # Banner Status Alur (Menunggu Mandiri / Ready Review)
draw_callout(d5, (240, 465, 600, 945), 3, badge_pos=(235, 460))   # Aspek Penilaian Kinerja Lini
draw_callout(d5, (600, 465, 680, 945), 4, badge_pos=(640, 460))   # Kolom Evaluasi Nilai Dept Head
draw_callout(d5, (790, 290, 1240, 465), 5, badge_pos=(785, 285))  # Kolom Pengesahan TTD Digital Dept Head
im5.save(os.path.join(ANN_DIR, "dh_05_form_bawahan_dept.png"))

# 6. Halaman Penyesuaian Penilaian & Kalibrasi Kuota Departemen (1360x850)
im6 = Image.open(os.path.join(SS_DIR, "ss_depthead_06_penyesuaian_dept.png"))
d6 = ImageDraw.Draw(im6)
draw_callout(d6, (945, 65, 1300, 110), 1, badge_pos=(940, 60))    # Tombol Kontrol: Reset, Sesuaikan Target (Auto-Adjust), Simpan
draw_callout(d6, (280, 180, 1320, 285), 2, badge_pos=(275, 175))   # Banner Validasi Distribusi Nilai Sesuai Target Kuota
draw_callout(d6, (280, 305, 1320, 460), 3, badge_pos=(275, 300))   # Kartu Peringkat Kuota: Rank S, A, B, C, K (Aktual vs Target)
draw_callout(d6, (280, 480, 1320, 840), 4, badge_pos=(275, 475))   # Tabel Realisasi Target Kuota Per Jenjang KJ (KJ 2, 3, 4, 5)
draw_callout(d6, (10, 70, 245, 480), 5, badge_pos=(5, 65))         # Menu Pintas Navigasi Penyesuaian Penilaian di Sidebar
im6.save(os.path.join(ANN_DIR, "dh_06_penyesuaian_dept.png"))

# 7. Riwayat Penilaian (1280x850)
im7 = Image.open(os.path.join(SS_DIR, "ss_depthead_07_riwayat.png"))
d7 = ImageDraw.Draw(im7)
draw_callout(d7, (240, 165, 1240, 295), 1, badge_pos=(235, 160))   # Metrik Ringkasan Status Dokumen & Rata-rata Skor
draw_callout(d7, (240, 330, 1240, 640), 2, badge_pos=(235, 325))   # Tabel Perbandingan: Target Penilaian Kuota vs Actual Dept Head
draw_callout(d7, (240, 670, 1240, 845), 3, badge_pos=(235, 665))   # Tabel Riwayat Dokumen Selesai Dinilai Departemen
draw_callout(d7, (1040, 55, 1215, 105), 4, badge_pos=(1035, 50))   # Tombol Nilai Karyawan Lain & Unduh Berkas
im7.save(os.path.join(ANN_DIR, "dh_07_riwayat.png"))

print("Department Head screenshots annotated with pixel-perfect calibration!")
