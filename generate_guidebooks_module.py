import os
import subprocess
import time
from pathlib import Path

BASE_DIR = Path(r"C:\Users\USER\Music\E-Approval")
OUTPUT_DIR = BASE_DIR / "public" / "panduan"
OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
CHROME_PATH = r"C:\Program Files\Google\Chrome\Application\chrome.exe"

COMMON_CSS = """
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap');

@page {
    size: A4 portrait;
    margin: 10mm 12mm 12mm 12mm;
    @bottom-right {
        content: counter(page);
    }
}

* {
    box-sizing: border-box;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
}

body {
    font-family: 'Plus Jakarta Sans', sans-serif;
    color: #1e293b;
    margin: 0;
    padding: 0;
    font-size: 11px;
    line-height: 1.45;
    background: #ffffff;
}

.page {
    page-break-after: always;
    position: relative;
    padding-bottom: 25px;
}
.page:last-child {
    page-break-after: auto;
}

/* Header Banner */
.doc-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 2px solid #7b181b;
    padding-bottom: 10px;
    margin-bottom: 14px;
}
.doc-logo-area {
    display: flex;
    align-items: center;
    gap: 10px;
}
.doc-logo {
    width: 38px;
    height: 38px;
    background: #7b181b;
    color: white;
    font-weight: 900;
    font-size: 20px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.doc-title-group h1 {
    font-size: 14px;
    font-weight: 800;
    color: #7b181b;
    margin: 0;
    letter-spacing: -0.2px;
}
.doc-title-group p {
    font-size: 9.5px;
    color: #64748b;
    margin: 2px 0 0 0;
    font-weight: 600;
}
.doc-meta {
    text-align: right;
    font-size: 9px;
    color: #475569;
}
.role-badge {
    display: inline-block;
    background: #7b181b;
    color: #ffffff;
    font-weight: 800;
    font-size: 10px;
    padding: 3px 10px;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Section Titles */
.section-title {
    font-size: 12px;
    font-weight: 800;
    color: #0f172a;
    border-left: 3.5px solid #7b181b;
    padding-left: 8px;
    margin: 12px 0 8px 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.section-sub {
    font-size: 10px;
    color: #64748b;
    margin-top: -4px;
    margin-bottom: 10px;
    padding-left: 11.5px;
}

/* Callout Number Badges */
.num-callout {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    height: 18px;
    background: #dc2626;
    color: #ffffff;
    border-radius: 9999px;
    font-size: 10px;
    font-weight: 800;
    box-shadow: 0 0 0 2px #fecaca;
    margin-right: 4px;
    vertical-align: middle;
}
.num-marker {
    position: absolute;
    width: 22px;
    height: 22px;
    background: #dc2626;
    color: #ffffff;
    border-radius: 9999px;
    font-size: 11px;
    font-weight: 900;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 0 0 3px #ffffff, 0 2px 5px rgba(0,0,0,0.3);
    z-index: 30;
    transform: translate(-50%, -50%);
}
.target-box {
    position: absolute;
    border: 2px dashed #dc2626;
    background: rgba(220, 38, 38, 0.08);
    border-radius: 4px;
    pointer-events: none;
    z-index: 20;
}

/* Browser Mockup */
.browser-mockup {
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    overflow: hidden;
    background: #ffffff;
    box-shadow: 0 2px 6px rgba(0,0,0,0.06);
    margin-bottom: 10px;
    position: relative;
}
.browser-bar {
    background: #f1f5f9;
    border-bottom: 1px solid #cbd5e1;
    padding: 6px 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.browser-dots {
    display: flex;
    gap: 4px;
}
.browser-dot {
    width: 8px;
    height: 8px;
    border-radius: 999px;
}
.dot-red { background: #ef4444; }
.dot-yellow { background: #f59e0b; }
.dot-green { background: #10b981; }
.browser-url {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    padding: 2px 8px;
    font-family: 'JetBrains Mono', monospace;
    font-size: 8.5px;
    color: #475569;
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.browser-body {
    padding: 10px;
    position: relative;
    background: #f8fafc;
}

/* Steps Explanation Grid */
.steps-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 6px;
    margin-top: 8px;
}
.step-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-left: 3px solid #7b181b;
    border-radius: 5px;
    padding: 7px 9px;
    display: flex;
    align-items: flex-start;
    gap: 8px;
}
.step-card.success-border { border-left-color: #10b981; }
.step-card.blue-border { border-left-color: #2563eb; }
.step-card.amber-border { border-left-color: #d97706; }
.step-card.purple-border { border-left-color: #7c3aed; }
.step-content h4 {
    margin: 0 0 2px 0;
    font-size: 10.5px;
    font-weight: 700;
    color: #0f172a;
}
.step-content p {
    margin: 0;
    font-size: 9.5px;
    color: #475569;
    line-height: 1.35;
}

/* Info Alert Callout */
.info-alert {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-left: 3.5px solid #2563eb;
    border-radius: 5px;
    padding: 7px 10px;
    font-size: 9.5px;
    color: #1e40af;
    margin: 8px 0;
    display: flex;
    align-items: flex-start;
    gap: 6px;
}
.warning-alert {
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-left: 3.5px solid #d97706;
    border-radius: 5px;
    padding: 7px 10px;
    font-size: 9.5px;
    color: #92400e;
    margin: 8px 0;
    display: flex;
    align-items: flex-start;
    gap: 6px;
}
.danger-alert {
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-left: 3.5px solid #dc2626;
    border-radius: 5px;
    padding: 7px 10px;
    font-size: 9.5px;
    color: #991b1b;
    margin: 8px 0;
    display: flex;
    align-items: flex-start;
    gap: 6px;
}

/* Footer note */
.doc-footer {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    border-top: 1px solid #e2e8f0;
    padding-top: 4px;
    display: flex;
    justify-content: space-between;
    font-size: 8px;
    color: #94a3b8;
}

/* Custom mini components inside mockups */
.mock-topbar {
    background: #7b181b;
    color: white;
    padding: 6px 10px;
    border-radius: 4px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 9px;
    font-weight: 700;
    margin-bottom: 8px;
}
.mock-nav-tabs {
    display: flex;
    gap: 5px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    padding: 4px;
    margin-bottom: 8px;
}
.mock-tab {
    padding: 3px 8px;
    font-size: 8.5px;
    font-weight: 600;
    color: #475569;
    border-radius: 3px;
}
.mock-tab.active {
    background: #7b181b;
    color: #ffffff;
    font-weight: 700;
}
.mock-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 8px;
    background: #ffffff;
    border: 1px solid #cbd5e1;
}
.mock-table th {
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    padding: 4px 6px;
    font-weight: 700;
    color: #334155;
    text-align: left;
}
.mock-table td {
    border: 1px solid #e2e8f0;
    padding: 4px 6px;
    color: #1e293b;
}
.mock-badge {
    display: inline-block;
    padding: 1.5px 5px;
    font-size: 7.5px;
    font-weight: 700;
    border-radius: 3px;
}
.bg-green-soft { background: #dcfce7; color: #166534; }
.bg-blue-soft { background: #dbeafe; color: #1e40af; }
.bg-amber-soft { background: #fef3c7; color: #92400e; }
.bg-purple-soft { background: #f3e8ff; color: #6b21a8; }
.bg-rose-soft { background: #ffe4e6; color: #9f1239; }
.btn-mock {
    display: inline-block;
    background: #7b181b;
    color: white;
    font-weight: 700;
    font-size: 8px;
    padding: 3px 8px;
    border-radius: 3px;
    text-align: center;
}
.btn-mock-secondary {
    display: inline-block;
    background: #e2e8f0;
    color: #334155;
    font-weight: 600;
    font-size: 8px;
    padding: 3px 6px;
    border-radius: 3px;
}
"""

def generate_pdf(html_content, output_pdf_path):
    temp_html = output_pdf_path.with_suffix(".html")
    temp_html.write_text(html_content, encoding="utf-8")
    
    cmd = [
        CHROME_PATH,
        "--headless",
        "--disable-gpu",
        "--no-pdf-header-footer",
        f"--print-to-pdf={output_pdf_path.resolve()}",
        str(temp_html.resolve())
    ]
    subprocess.run(cmd, check=True)
    if temp_html.exists():
        temp_html.unlink()
    print(f"✓ Generated: {output_pdf_path.name} ({output_pdf_path.stat().st_size} bytes)")

print("PDF Generator Module loaded successfully.")
