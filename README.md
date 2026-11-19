# TTD Digital Penilaian Karyawan - PT Musashi Auto Parts Indonesia

Aplikasi web modern berbasis Laravel 11 untuk proses **Penilaian Karyawan (PK)** karyawan bertingkat dengan alur persetujuan tanda tangan digital (Foreman &rarr; Section Head &rarr; Department Head), integrasi database utama Google Sheets (API v4), dan penyimpanan arsip dokumen resmi PDF ber-QR Code di Google Drive.

---

## 1. Tech Stack
- **Framework**: Laravel 11, PHP 8.2 / 8.3
- **Frontend**: Blade + Tailwind CSS + Alpine.js (tanpa SPA, responsive, status badge, tombol minimal 44px)
- **Database Utama**: Google Sheets API v4 (`google/apiclient` + Service Account) melalui **Repository Pattern** (`SheetRepositoryInterface`)
- **Penyimpanan Dokumen**: Google Drive API v3 (Folder terstruktur `/Penilaian Karyawan/{Tahun}/{Departemen}/`)
- **Generator PDF**: `barryvdh/laravel-dompdf`
- **QR Code Verifikasi**: `simplesoftwareio/simple-qrcode`
- **Autentikasi**: Laravel Session Auth, password hash bcrypt, role-based authorization dengan Laravel Gates & Policies
- **Caching & Lock**: Cache 60 detik untuk mencegah Google API rate-limit dan `Cache::lock` untuk atomic writes & versioning

---

## 2. Struktur Google Sheets (1 Tab = 1 Tabel)
Sistem menggunakan satu spreadsheet dengan 10 tab tabel:
1. `users`: `id`, `nama`, `email`, `password_hash`, `role`, `nrp`, `departemen`, `aktif`, `created_at`
2. `karyawan`: `nrp`, `nama_lengkap`, `golongan`, `jabatan`, `tgl_masuk`, `seksi`, `departemen`, `level_form` (KJ13 | KJ45 | LEADER | FOREMAN), `nrp_foreman`, `nrp_section_head`, `nrp_dept_head`
3. `periode`: `id`, `tahun`, `tgl_mulai`, `tgl_selesai`, `status`
4. `dokumen`: `id`, `periode_id`, `nrp`, `kode_form` (07-001..07-004), `status`, `drive_file_id`, `drive_url`, `hash_dokumen`, `kode_verifikasi`, `created_at`
5. `nilai`: `dokumen_id`, `level` (foreman|section_head|dept_head), `aspek_kode`, `skor` (1-5), `catatan`
6. `indikator`: `dokumen_id`, `pilihan_potensi` (1-4), `kelebihan`, `kekurangan`
7. `tanda_tangan`: `dokumen_id`, `level`, `user_id`, `tgl_ttd`, `ip`, `user_agent`, `hash_snapshot`
8. `master_aspek`: `kode_form`, `grup`, `aspek_kode`, `nama`, `bobot_grup`, `urutan`
9. `master_kriteria`: `aspek_kode`, `skor`, `deskripsi`
10. `audit_log`: `waktu`, `user_id`, `aksi`, `dokumen_id`, `detail`
11. `pengaturan`: `key`, `value`

---

## 3. Akun Bawaan (Default Seeder)
Semua akun demo memiliki password: `password123`

| No | Nama | Email | Role | Departemen |
|----|------|-------|------|------------|
| 1 | Super Administrator | `superadmin@musashi.co.id` | `superadmin` | ALL |
| 2 | Admin Penilaian | `admin@musashi.co.id` | `admin` | ALL |
| 3 | HR Verifier & Personalia | `hr@musashi.co.id` | `hr` | HR & IR DEPT. |
| 4 | Ahmad Suhendar | `foreman.prod2@musashi.co.id` | `foreman` | PROD 2 |
| 5 | Budi Prasetyo | `section.prod2@musashi.co.id` | `section_head` | PROD 2 |
| 6 | Chandra Kusuma | `dept.prod2@musashi.co.id` | `department_head` | PROD 2 |
| 7 | Dedi Gunawan | `foreman.ga@musashi.co.id` | `foreman` | GA DEPT. |
| 8 | Eko Nurcahyono | `section.ga@musashi.co.id` | `section_head` | GA DEPT. |
| 9 | Fajar Santoso | `dept.ga@musashi.co.id` | `department_head` | GA DEPT. |
| 10 | Gilang Ramadhan | `foreman.hr@musashi.co.id` | `foreman` | HR & IR DEPT. |
| 11 | Hendra Setiawan | `section.hr@musashi.co.id` | `section_head` | HR & IR DEPT. |
| 12 | Indra Wijaya | `dept.hr@musashi.co.id` | `department_head` | HR & IR DEPT. |

> Tersedia fitur **One-Click Quick Login** di halaman `/login` untuk beralih antar role dengan satu klik selama pengujian.

---

## 4. Panduan Google Cloud (Sheets & Drive)

### Target Database & Drive Default:
- **Spreadsheet Database**:
  `https://docs.google.com/spreadsheets/d/1M9rUwWr15cBHRC4U5DjCgi0kYr4GlbNOQ5GDykszS-g/edit?usp=sharing`
  (ID: `1M9rUwWr15cBHRC4U5DjCgi0kYr4GlbNOQ5GDykszS-g`)
- **Google Drive Folder**:
  `https://drive.google.com/drive/folders/175fmn9Qq1h2s7mLQzkiQ1Bpqu4CyjiPR?usp=sharing`
  (ID: `175fmn9Qq1h2s7mLQzkiQ1Bpqu4CyjiPR`)

### Langkah Menghubungkan:
1. Buka [Google Cloud Console](https://console.cloud.google.com/).
2. Aktifkan **Google Sheets API** dan **Google Drive API**.
3. Buat Service Account pada menu *IAM & Admin > Service Accounts*.
4. Klik tab *Keys* > *Add Key* > *Create new key* > Pilih format **JSON**.
5. Salin alamat email Service Account (contoh: `xxx@project.iam.gserviceaccount.com`).
6. Bagikan (Share) spreadsheet database dan folder Drive ke email Service Account tersebut dengan peran **Editor**.
7. Buka aplikasi sebagai **Super Administrator**, masuk ke menu **Pengaturan Sistem** (`/superadmin/config`), lalu unggah atau tempel isi file JSON Service Account.
8. Tekan tombol **"Uji Koneksi API"** untuk memastikan koneksi live berhasil.
9. Tekan tombol **"Jalankan sheets:setup"** untuk membuat seluruh tab dan header secara otomatis di spreadsheet.

---

## 5. Menjalankan Perintah Artisan
```bash
# Inisialisasi tab dan header di Google Sheets & Database
php artisan sheets:setup

# Menjalankan seeder akun & data karyawan contoh
php artisan db:seed

# Menjalankan Automated Feature Tests
vendor/bin/phpunit

---

## 6. Integrasi Lengkap 3 File Excel Penilaian Karyawan

Aplikasi web ini telah mengintegrasikan secara penuh seluruh formulir, rubrik penilaian, rekapitulasi, dan kalibrasi kuota dari 3 file Excel utama:

1. **`MANUAL & FORM PK Gol 1-3 (1).xlsx`**:
   - `Form KJ 1-3` (07-001) & rubrik `manual KJ 1-3`: Hasil Kerja 60% (4 aspek), Proses Kerja 20% (3 aspek), Sikap Kerja 20% (3 aspek).
   - `Form KJ 4-5` (07-002) & rubrik `manual KJ 4-5`: Hasil Kerja 60% (4 aspek), Proses Kerja 20% (3 aspek), Sikap Kerja 20% (4 aspek).
   - `Form Leader` (07-003) & rubrik `manual Leader`: Hasil Kerja 40% (5 aspek), Proses Kerja 20% (4 aspek), Man Management 20% (4 aspek), Sikap Kerja 20% (3 aspek).
   - `Form Foreman` (07-004) & rubrik `manual Foreman`: Sama dengan Leader dengan kolom evaluasi Mandiri.

2. **`MANUAL & FORM PK Gol 4-up New (1).xlsx`**:
   - Formulir manajemen untuk **Section Head** (`07-006-SH`), **Department Head** (`07-006-DH`), dan **Division Head** (`07-006-DIV`).
   - Rubrik bilingual `Manual PK` dengan pembobotan: Hasil Kerja 55%, Proses Kerja 15%, Man Management 15%, Sikap Kerja 15%.

3. **`INPUT PK 2025_Production 2 (2).xlsx`**:
   - **Database Hasil Input (`/hasil-input`)**: Matriks 31-kolom lengkap yang mengumpulkan nilai detail per aspek dan subtotal berbobot dari seluruh jenis formulir, dilengkapi filter tab (`Semua`, `KJ 1-3`, `KJ 4-5`, `Leader & Foreman`, `Gol 4-Up`) dan fitur Ekspor CSV.
   - **Summary PK & Docking (`/summary-pk`)**: Tiga tabel perbandingan kalibrasi sesuai format resmi Musashi:
     - **Tabel 1: Target Quota** (KJ 2: 64, KJ 3: 82, KJ 4: 21, KJ 5: 4, Total 171 untuk Dept PROD 2; S=0, A=2, B=42, C=127, K=0).
     - **Tabel 2: PK ORIGINAL (Based on form PK)**: Agregasi otomatis grade asli hasil perhitungan formulir (S > 4.50, A > 3.75 - 4.50, B >= 3.00 - 3.75, C >= 1.00 - < 3.00, K < 1.00).
     - **Tabel 3: DOCKING PK**: Rekap grade akhir setelah penyesuaian/kalibrasi manajerial.
     - **Tabel Rekap Karyawan & Docking Dropdown**: Pengguna berwenang (Dept Head, Div Head, Admin, HR) dapat langsung mengubah Grade Docking (S/A/B/C/K) via dropdown interaktif dengan update real-time via AJAX dan ekspor CSV.
```
