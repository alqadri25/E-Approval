import os
import subprocess
import shutil

CHROME = r"C:\Program Files\Google\Chrome\Application\chrome.exe"
PAGES_DIR = r"C:\Users\USER\Music\E-Approval\storage\app\depthead_pages"
SS_DIR = r"C:\Users\USER\Music\E-Approval\storage\app\depthead_screenshots"
os.makedirs(SS_DIR, exist_ok=True)

# 1. Login screenshot - copy from div_screenshots
src_login = r"C:\Users\USER\Music\E-Approval\storage\app\div_screenshots\ss_01_login.png"
dst_login = os.path.join(SS_DIR, "ss_depthead_01_login.png")
if os.path.exists(src_login):
    shutil.copy2(src_login, dst_login)
    print("1. Login copied successfully.")

tasks = [
    ("01_dashboard.html", "ss_depthead_02_dashboard.png", "1280,850"),
    ("03_form_07006_mandiri.html", "ss_depthead_03_form_07006_mandiri.png", "1280,950"),
    ("02_data_penilaian_dept.html", "ss_depthead_04_data_penilaian_dept.png", "1360,850"),
    ("04_form_bawahan_dept.html", "ss_depthead_05_form_bawahan_dept.png", "1280,950"),
    ("05_penyesuaian_dept.html", "ss_depthead_06_penyesuaian_dept.png", "1360,850"),
    ("06_riwayat.html", "ss_depthead_07_riwayat.png", "1280,850"),
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

print("All Department Head screenshots captured!")
