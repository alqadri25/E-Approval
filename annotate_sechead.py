import os
from PIL import Image, ImageDraw, ImageFont

SS_DIR = r"C:\Users\USER\Music\E-Approval\storage\app\sechead_screenshots"
ANN_DIR = r"C:\Users\USER\Music\E-Approval\storage\app\sechead_annotated"
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
im1 = Image.open(os.path.join(SS_DIR, "ss_sechead_01_login.png"))
d1 = ImageDraw.Draw(im1)
draw_callout(d1, (715, 280, 1035, 320), 1)
draw_callout(d1, (715, 325, 1035, 365), 2)
draw_callout(d1, (715, 350, 780, 370), 3)
draw_callout(d1, (715, 365, 1040, 415), 4)
im1.save(os.path.join(ANN_DIR, "sh_01_login.png"))

# 2. Dashboard Section Head (1280x850)
im2 = Image.open(os.path.join(SS_DIR, "ss_sechead_02_dashboard.png"))
d2 = ImageDraw.Draw(im2)
draw_callout(d2, (10, 70, 245, 480), 1)      # Sidebar Navigasi Utama
draw_callout(d2, (275, 90, 1255, 180), 2)    # Alert Box Status Pengingat Mandiri
draw_callout(d2, (1060, 110, 1235, 155), 3)  # Tombol Cepat Buka Form Mandiri 07-006-SH
draw_callout(d2, (275, 195, 1255, 320), 4)   # Kartu KPI & Progres Tim Seksi
draw_callout(d2, (275, 345, 1255, 680), 5)   # Tabel Pelacakan Alur Status
im2.save(os.path.join(ANN_DIR, "sh_02_dashboard.png"))

# 3. Form 07-006-SH Penilaian Mandiri Section Head (1280x950)
im3 = Image.open(os.path.join(SS_DIR, "ss_sechead_03_form_07006_mandiri.png"))
d3 = ImageDraw.Draw(im3)
# Box 1: Identitas Section Head (y=200 to 350)
draw_callout(d3, (280, 200, 765, 350), 1, badge_pos=(275, 195))
# Box 2: 4 Pilar Kompetensi (Hasil 55%, Proses 15%, Sikap 15%, Man Mgmt 15%)
draw_callout(d3, (280, 350, 580, 945), 2, badge_pos=(275, 345))
# Box 3: Kolom Skor Mandiri / 自分で
draw_callout(d3, (580, 350, 680, 945), 3, badge_pos=(630, 345))
# Box 4: Indikator Evaluasi Potensi Diri
draw_callout(d3, (680, 350, 1240, 945), 4, badge_pos=(690, 345))
# Box 5: Kolom TTD Digital Kary. Ybs & Verifikasi Atasan
draw_callout(d3, (765, 200, 1240, 350), 5, badge_pos=(760, 195))
im3.save(os.path.join(ANN_DIR, "sh_03_form_07006_mandiri.png"))

# 4. Data Karyawan (1280x850)
im4 = Image.open(os.path.join(SS_DIR, "ss_sechead_04_karyawan.png"))
d4 = ImageDraw.Draw(im4)
draw_callout(d4, (275, 275, 950, 360), 1)    # Filter & Pencarian
draw_callout(d4, (275, 115, 1255, 230), 2)   # Kartu Ringkasan Struktur Tim
draw_callout(d4, (275, 370, 1255, 780), 3)   # Tabel Anggota Seksi
draw_callout(d4, (275, 430, 1255, 480), 4)   # Baris Karyawan Bawahan & Aksi
im4.save(os.path.join(ANN_DIR, "sh_04_karyawan.png"))

# 5. Form KJ 1-3 Penilaian Bawahan (Form 07-001 - 1280x950)
im5 = Image.open(os.path.join(SS_DIR, "ss_sechead_05_form_kj13_bawahan.png"))
d5 = ImageDraw.Draw(im5)
# Box 1: Identitas Bawahan KJ 1-3 (Al Qadri, Gol IIIA, Pelaksana) (y=235 to 393)
draw_callout(d5, (280, 235, 765, 393), 1, badge_pos=(275, 230))
# Box 2: Aspek Penilaian Kinerja Lapangan
draw_callout(d5, (280, 393, 563, 945), 2, badge_pos=(275, 388))
# Box 3: Review Nilai Foreman & Kolom Nilai Section Head
draw_callout(d5, (563, 393, 835, 945), 3, badge_pos=(700, 388))
# Box 4: Evaluasi Rekomendasi & Catatan Pengembangan
draw_callout(d5, (835, 393, 1240, 945), 4, badge_pos=(845, 388))
# Box 5: Kolom TTD Digital Section Head & Pengesahan
draw_callout(d5, (765, 235, 1240, 393), 5, badge_pos=(760, 230))
im5.save(os.path.join(ANN_DIR, "sh_05_form_kj13_bawahan.png"))

# 6. Form KJ 4-5 Penilaian Bawahan (Form 07-004 - 1280x950)
im6 = Image.open(os.path.join(SS_DIR, "ss_sechead_06_form_kj45_bawahan.png"))
d6 = ImageDraw.Draw(im6)
# Box 1: Identitas Karyawan Level Foreman (Joko Hartono, IVA, KJ 6) (y=200 to 356)
draw_callout(d6, (280, 200, 765, 356), 1, badge_pos=(275, 195))
# Box 2: Aspek Kinerja Teknis & Manajerial Lini
draw_callout(d6, (280, 356, 593, 945), 2, badge_pos=(275, 350))
# Box 3: Review Skor Foreman & Kolom Evaluasi Section Head
draw_callout(d6, (593, 356, 835, 945), 3, badge_pos=(714, 350))
# Box 4: Evaluasi Potensi Promosi & Kepemimpinan
draw_callout(d6, (835, 356, 1240, 945), 4, badge_pos=(845, 350))
# Box 5: Tanda Tangan Digital Section Head & Pengesahan ke Dept Head
draw_callout(d6, (765, 200, 1240, 356), 5, badge_pos=(760, 195))
im6.save(os.path.join(ANN_DIR, "sh_06_form_kj45_bawahan.png"))

# 7. Riwayat Penilaian (1280x850)
im7 = Image.open(os.path.join(SS_DIR, "ss_sechead_07_riwayat.png"))
d7 = ImageDraw.Draw(im7)
draw_callout(d7, (275, 115, 1255, 230), 1)   # Metrik Ringkasan Status Dokumen
draw_callout(d7, (275, 280, 950, 360), 2)    # Filter Periode & Pencarian
draw_callout(d7, (275, 370, 1255, 780), 3)   # Tabel Riwayat Dokumen
draw_callout(d7, (1120, 430, 1245, 480), 4)  # Tombol Detail & Unduh PDF Ber-barcode
im7.save(os.path.join(ANN_DIR, "sh_07_riwayat.png"))

print("Section Head screenshots re-annotated with pixel-perfect calibration!")
