import os
import subprocess
import shutil

CHROME = r"C:\Program Files\Google\Chrome\Application\chrome.exe"
PAGES_DIR = r"C:\Users\USER\Music\E-Approval\storage\app\sechead_pages"
SS_DIR = r"C:\Users\USER\Music\E-Approval\storage\app\sechead_screenshots"
os.makedirs(SS_DIR, exist_ok=True)

# 1. Login screenshot - copy from div_screenshots or capture
src_login = r"C:\Users\USER\Music\E-Approval\storage\app\div_screenshots\ss_01_login.png"
dst_login = os.path.join(SS_DIR, "ss_sechead_01_login.png")
if os.path.exists(src_login):
    shutil.copy2(src_login, dst_login)
    print("1. Login copied successfully.")

tasks = [
    ("01_dashboard.html", "ss_sechead_02_dashboard.png", "1280,850"),
    ("03_form_07006_mandiri.html", "ss_sechead_03_form_07006_mandiri.png", "1280,950"),
    ("02_karyawan.html", "ss_sechead_04_karyawan.png", "1280,850"),
    ("04_form_kj13_bawahan.html", "ss_sechead_05_form_kj13_bawahan.png", "1280,950"),
    ("05_form_kj45_bawahan.html", "ss_sechead_06_form_kj45_bawahan.png", "1280,950"),
    ("06_riwayat.html", "ss_sechead_07_riwayat.png", "1280,850"),
]

for html_file, png_file, win_size in tasks:
    html_path = os.path.join(PAGES_DIR, html_file)
    png_path = os.path.join(SS_DIR, png_file)
    cmd = [
        CHROME,
        "--headless",
        "--disable-gpu",
        f"--window-size={win_size}",
        f"--screenshot={png_path}",
        html_path
    ]
    res = subprocess.run(cmd, capture_output=True, text=True)
    if os.path.exists(png_path):
        print(f"Captured {png_file} ({os.path.getsize(png_path)} bytes)")
    else:
        print(f"Failed to capture {png_file}: {res.stderr}")

print("All screenshots captured!")
