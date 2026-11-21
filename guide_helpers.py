import os
import subprocess
from pathlib import Path

BASE_DIR = Path(r"C:\Users\USER\Music\E-Approval")
OUTPUT_DIR = BASE_DIR / "public" / "panduan"
OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
CHROME_PATH = r"C:\Program Files\Google\Chrome\Application\chrome.exe"

from generate_guidebooks_module import COMMON_CSS, generate_pdf
from build_all_6_pdfs import render_login_mockup, render_login_explanation

def get_header(title, role_badge, doc_no):
    return f"""
    <div class="doc-header">
        <div class="doc-logo-area">
            <div class="doc-logo">M</div>
            <div class="doc-title-group">
                <h1>PT MUSASHI AUTO PARTS INDONESIA</h1>
                <p>PerformanceAppraisal &bull; Pedoman Resmi Operasional Penilaian Kerja</p>
            </div>
        </div>
        <div class="doc-meta">
            <div><span class="role-badge">{role_badge}</span></div>
            <div style="margin-top:3px; font-weight:700; color:#7b181b;">{doc_no}</div>
            <div style="font-size:8px; color:#64748b;">Tahun Pelaksanaan: 2026 / 2027</div>
        </div>
    </div>
    """

def get_footer(role_name, page_num, total_pages):
    return f"""
    <div class="doc-footer">
        <div>Aplikasi PerformanceAppraisal &bull; PT Musashi Auto Parts Indonesia &bull; Link: https://performanceappraisal.vercel.app/</div>
        <div>Panduan {role_name} &bull; Halaman {page_num} dari {total_pages}</div>
    </div>
    """

print("Header & Footer helpers compiled.")
