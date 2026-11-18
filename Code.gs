/**
 * =========================================================================
 * GOOGLE APPS SCRIPT: PT MUSASHI AUTO PARTS INDONESIA
 * SISTEM E-APPROVAL & DIGITAL SIGNATURE PENILAIAN KARYA
 * =========================================================================
 * Tautan Spreadsheet ID : 14-xI_m24eXSj1L2aiX_gytMWrrS9HnjH5HzcRgWV_9g
 * Tautan Drive Folder ID : 1zB2loWV14fCpLWMCnNiEa4dVKAvhLZYE
 * =========================================================================
 */

var CONFIG = {
  SPREADSHEET_ID: '14-xI_m24eXSj1L2aiX_gytMWrrS9HnjH5HzcRgWV_9g',
  DRIVE_FOLDER_ID: '1zB2loWV14fCpLWMCnNiEa4dVKAvhLZYE',
  // Otomatis mengikuti tahun berjalan kalender (2026, 2027, 2028, dst)
  get DEFAULT_YEAR() {
    return String(new Date().getFullYear());
  },
  COMPANY_NAME: 'PT MUSASHI AUTO PARTS INDONESIA'
};

/**
 * Helper: Menampilkan alert hanya jika konteks UI tersedia (bukan saat dipanggil via Web App HTTP)
 */
function showAlertSafely(message) {
  try {
    SpreadsheetApp.getUi().alert(message);
  } catch (e) {
    Logger.log(message);
  }
}

/**
 * 1. MEMBUAT MENU RESMI MUSASHI PADA GOOGLE SPREADSHEET
 */
function onOpen() {
  SpreadsheetApp.getUi()
    .createMenu('🚀 Musashi E-Approval')
    .addItem('⚡ Inisialisasi & Rapikan Seluruh Sheet Database', 'setupDatabaseSheets')
    .addItem('📁 Buat Subfolder Drive Otomatis (Dari Data Karyawan)', 'setupDriveFolders')
    .addItem('📱 Sinkron Barcode TTD Users ke Drive & Tampil di Sheet', 'syncUserBarcodesToDriveAndSheet')
    .addItem('📊 Format & Rapikan Sheet Dokumen_Dinilai', 'setupDokumenDinilaiSheet')
    .addItem('📅 Rapikan Format Tanggal Masuk (dd-Mmm-yy)', 'formatAllDatesInSheets')
    .addItem('🗑️ Hapus Sheet yang Tidak Diperlukan (Termasuk Master Karyawan)', 'cleanUnusedSheets')
    .addSeparator()
    .addItem('⚠️ Kosongkan Seluruh Data Penilaian & PDF Drive (Wipe Total)', 'wipeAllDataAndPdfsMenu')
    .addItem('✍️ Uji & Buat Folder Tanda Tangan Drive', 'testDriveAccess')
    .addSeparator()
    .addItem('📈 Cek Status & Statistik Data', 'showDatabaseStats')
    .addToUi();
}

/**
 * 2. DAFTAR NAMA SHEET & STRUKTUR KOLOM RESMI (BERSIH & TERHUBUNG KE WEB)
 * CATATAN PENTING: Sheet 'Master Karyawan' ditiadakan agar tidak ada dobel database,
 * hanya menggunakan sheet tunggal resmi 'karyawan'.
 */
var CONFIG_TABLES = {
  'karyawan': [
    'nrp', 'nama_lengkap', 'golongan', 'sub_gol', 'kj', 'jabatan', 'tgl_masuk',
    'seksi', 'departemen', 'level_form', 'nrp_foreman', 'nrp_section_head',
    'nrp_dept_head', 'nrp_div_head', 'code_bagian', 'nama_bagian', 'nama_sect',
    'line_category', 'division'
  ],
  'dokumen': [
    'id', 'periode_id', 'nrp', 'kode_form', 'status', 'total_nilai',
    'grade_original', 'docking_final', 'drive_file_id', 'drive_url',
    'hash_dokumen', 'kode_verifikasi', 'created_at',
    'nrp_foreman', 'nama_foreman', 'nrp_section_head', 'nama_section_head',
    'nrp_dept_head', 'nama_dept_head', 'nrp_div_head', 'nama_div_head'
  ],
  'Dokumen_Dinilai': [
    'NRP', 'NAMA', 'NAMA BAGIAN', 'SECTION', 'DEPARTMENT', 'DIVISION', 'POSITION', 'START WORK', 'KJ', 'GOLONGAN', 'Hasil Kerja', 'Proses Kerja', 'Sikap Kerja', 'Man Management', 'Total Nilai', 'NILAI PK Foreman (Jika Ada)', 'NILAI PK SECT', 'NILAI PK DEPT', 'NILAI PK DIV', 'NILAI PK FINAL'
  ],
  'users': [
    'id', 'nama', 'email', 'role', 'nrp', 'departemen', 'aktif', 'barcodettd', 'signature_path_link', 'created_at'
  ],
  'kuota_departemen': [
    'id', 'departemen', 'grade_s_max', 'grade_a_max', 'grade_b_max', 'grade_c_max', 'grade_k_max'
  ],
  'nilai': [
    'dokumen_id', 'level', 'aspek_kode', 'skor', 'catatan'
  ],
  'indikator': [
    'dokumen_id', 'pilihan_potensi', 'catatan_potensi', 'kelebihan', 'kekurangan'
  ],
  'tanda_tangan': [
    'dokumen_id', 'level', 'user_id', 'tgl_ttd', 'ip', 'user_agent',
    'hash_snapshot', 'ttd_image', 'created_at', 'nrp', 'nama', 'role'
  ],
  'periode': [
    'id', 'tahun', 'tgl_mulai', 'tgl_selesai', 'status'
  ],
  'pengaturan': [
    'key', 'value', 'updated_at'
  ],
  'Master Tanda Tangan': [
    'nrp', 'nama', 'position', 'department', 'division', 'signature_url', 'drive_file_id', 'updated_at'
  ],
  'audit_log': [
    'id', 'waktu', 'user_id', 'aksi', 'dokumen_id', 'detail'
  ]
};

/**
 * 2B. KONFIGURASI LEBAR KOLOM & FORMAT PERATAAN (ALIGNMENT) RESMI
 */
var COLUMN_FORMATS = {
  'users': {
    widths: { 1: 60, 2: 220, 3: 220, 4: 140, 5: 90, 6: 180, 7: 70, 8: 100, 9: 280, 10: 160 },
    alignCenter: [1, 4, 5, 7, 8, 10]
  },
  'karyawan': {
    widths: { 1: 90, 2: 230, 3: 90, 4: 80, 5: 80, 6: 220, 7: 110, 8: 180, 9: 180, 10: 100, 11: 110, 12: 130, 13: 120, 14: 110, 15: 110, 16: 220, 17: 180, 18: 120, 19: 190 },
    alignCenter: [1, 3, 4, 5, 7, 10, 11, 12, 13, 14, 15, 18],
    textFormatCols: [1, 3, 4, 5, 7, 10, 11, 12, 13, 14, 15] // Kolom 7 (tgl_masuk) diformat Plain Text (@)
  },
  'Dokumen_Dinilai': {
    widths: { 1: 90, 2: 230, 3: 220, 4: 160, 5: 160, 6: 190, 7: 190, 8: 110, 9: 80, 10: 90, 11: 90, 12: 90, 13: 90, 14: 110, 15: 90, 16: 170, 17: 120, 18: 120, 19: 120, 20: 130 },
    alignCenter: [1, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20],
    textFormatCols: [1, 8, 9, 10] // Kolom 8 (START WORK) diformat Plain Text (@)
  },
  'kuota_departemen': {
    widths: { 1: 60, 2: 220, 3: 110, 4: 110, 5: 110, 6: 110, 7: 110 },
    alignCenter: [1, 3, 4, 5, 6, 7]
  },
  'Master Tanda Tangan': {
    widths: { 1: 90, 2: 220, 3: 150, 4: 180, 5: 180, 6: 280, 7: 260, 8: 160 },
    alignCenter: [1, 3, 8]
  },
  'dokumen': {
    widths: { 1: 70, 2: 80, 3: 90, 4: 100, 5: 170, 6: 90, 7: 110, 8: 110, 9: 260, 10: 280, 11: 220, 12: 140, 13: 160, 14: 90, 15: 180, 16: 110, 17: 180, 18: 110, 19: 180, 20: 110, 21: 180 },
    alignCenter: [1, 2, 3, 4, 5, 6, 7, 8, 12, 13, 14, 16, 18, 20],
    textFormatCols: [1, 2, 3, 4, 12, 14, 16, 18, 20]
  },
  'periode': {
    widths: { 1: 60, 2: 80, 3: 110, 4: 110, 5: 100 },
    alignCenter: [1, 2, 3, 4, 5]
  },
  'nilai': {
    widths: { 1: 90, 2: 120, 3: 110, 4: 80, 5: 280 },
    alignCenter: [1, 2, 3, 4]
  },
  'indikator': {
    widths: { 1: 90, 2: 220, 3: 260, 4: 260, 5: 260 },
    alignCenter: [1]
  },
  'tanda_tangan': {
    widths: { 1: 90, 2: 120, 3: 80, 4: 160, 5: 130, 6: 220, 7: 200, 8: 260, 9: 160, 10: 90, 11: 180, 12: 120 },
    alignCenter: [1, 2, 3, 4, 5, 9, 10, 12],
    textFormatCols: [1, 2, 3, 10, 12]
  },
  'pengaturan': {
    widths: { 1: 220, 2: 360, 3: 160 },
    alignCenter: [3]
  },
  'audit_log': {
    widths: { 1: 70, 2: 160, 3: 80, 4: 140, 5: 90, 6: 360 },
    alignCenter: [1, 2, 3, 4, 5]
  }
};

/**
 * Format tampilan, border, lebar kolom, dan perataan teks secara rapi
 */
function formatSheetColumns(sheet, sheetName) {
  if (!sheet) return;
  var colCount = sheet.getLastColumn();
  if (colCount === 0) return;

  // Format Header Resmi Musashi (#92302E)
  var hr = sheet.getRange(1, 1, 1, colCount);
  hr.setBackground('#92302E')
    .setFontColor('#FFFFFF')
    .setFontWeight('bold')
    .setFontFamily('Arial')
    .setFontSize(10)
    .setHorizontalAlignment('center')
    .setVerticalAlignment('middle');
  sheet.setRowHeight(1, 32);
  sheet.setFrozenRows(1);

  var cfg = COLUMN_FORMATS[sheetName];
  if (cfg) {
    if (cfg.widths) {
      for (var colIdx in cfg.widths) {
        var cNum = parseInt(colIdx, 10);
        if (cNum <= colCount) {
          sheet.setColumnWidth(cNum, cfg.widths[colIdx]);
        }
      }
    }
    if (sheet.getLastRow() > 1) {
      if (cfg.alignCenter) {
        for (var a = 0; a < cfg.alignCenter.length; a++) {
          var colC = cfg.alignCenter[a];
          if (colC <= colCount) {
            sheet.getRange(2, colC, sheet.getLastRow() - 1, 1)
              .setHorizontalAlignment('center')
              .setVerticalAlignment('middle');
          }
        }
      }
      if (cfg.textFormatCols) {
        for (var t = 0; t < cfg.textFormatCols.length; t++) {
          var colT = cfg.textFormatCols[t];
          if (colT <= colCount) {
            sheet.getRange(2, colT, sheet.getLastRow() - 1, 1).setNumberFormat('@');
          }
        }
      }
    }
  }
}

/**
 * 2C. INISIALISASI & RAPIKAN SELURUH SHEET DATABASE
 */
function setupDatabaseSheets() {
  var ss = SpreadsheetApp.getActiveSpreadsheet();

  for (var name in CONFIG_TABLES) {
    var sheet = ss.getSheetByName(name);
    if (!sheet) {
      sheet = ss.insertSheet(name);
    }
    var headers = CONFIG_TABLES[name];
    if (sheet.getLastRow() === 0) {
      sheet.appendRow(headers);
    }
    formatSheetColumns(sheet, name);
  }

  // Bersihkan sheet usang / duplikat (Master Karyawan, master_aspek, dll)
  cleanUnusedSheets(true);

  // Format tanggal masuk di seluruh sheet agar seragam (dd-Mmm-yy)
  formatAllDatesInSheets(true);

  showAlertSafely('✅ Sukses! Seluruh tabel database resmi telah dibuat, dirapikan, dan sheet duplikat telah dibersihkan.');
}

/**
 * 2D. FORMAT & RAPIKAN SHEET DOKUMEN_DINILAI
 */
function setupDokumenDinilaiSheet() {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var sheet = ss.getSheetByName('Dokumen_Dinilai');
  var headers = CONFIG_TABLES['Dokumen_Dinilai'];
  if (!sheet) {
    sheet = ss.insertSheet('Dokumen_Dinilai');
  }
  if (sheet.getLastRow() === 0) {
    sheet.appendRow(headers);
  }
  formatSheetColumns(sheet, 'Dokumen_Dinilai');
  formatAllDatesInSheets(true);
  showAlertSafely('✅ Sheet Dokumen_Dinilai berhasil disiapkan dan dirapikan.');
}

/**
 * 2E. HAPUS & BERSIHKAN SHEET YANG TIDAK DIPERLUKAN / DUPLIKAT
 * Secara spesifik menghapus 'Master Karyawan' agar database tidak ganda dengan 'karyawan',
 * serta menghapus sheet usang lainnya ('master_aspek', 'master_kriteria', 'Sheet1').
 */
function cleanUnusedSheets(silent) {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var allowedSheets = Object.keys(CONFIG_TABLES);
  var sheets = ss.getSheets();
  var deletedNames = [];

  var obsoleteTargets = ['Master Karyawan', 'master_aspek', 'master_kriteria', 'Sheet1', 'Sheet 1'];

  for (var i = 0; i < sheets.length; i++) {
    var sName = sheets[i].getName();
    var isObsolete = (obsoleteTargets.indexOf(sName) > -1);
    var isNotAllowed = (allowedSheets.indexOf(sName) === -1);

    if (isObsolete || isNotAllowed) {
      if (ss.getSheets().length > 1) {
        try {
          ss.deleteSheet(sheets[i]);
          deletedNames.push(sName);
        } catch (e) {
          Logger.log('Gagal menghapus sheet ' + sName + ': ' + e);
        }
      }
    }
  }

  if (!silent) {
    showAlertSafely(
      '🗑️ Pembersihan Sheet Berhasil!\n\n' +
      'Sheet yang dihapus: ' + (deletedNames.length > 0 ? deletedNames.join(', ') : 'Tidak ada sheet usang.') + '\n\n' +
      'Kini spreadsheet bersih dan hanya memuat sheet database resmi yang aktif dan terintegrasi.'
    );
  }
  return deletedNames;
}

/**
 * 2F. RAPIKAN FORMAT TANGGAL MASUK (dd-Mmm-yy) PADA SELURUH SHEET
 * Mengubah tanggal mentah seperti 1996-05-07T17:00:00.000Z menjadi:
 * 07-May-96, 16-Jul-12, 20-Aug-01, 30-Apr-12, 28-May-12, 07-Dec-12
 */
function formatAllDatesInSheets(silent) {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var formattedCount = 0;

  // 1. Format di sheet 'karyawan' (kolom tgl_masuk)
  var kSheet = ss.getSheetByName('karyawan');
  if (kSheet && kSheet.getLastRow() > 1) {
    var kData = kSheet.getDataRange().getValues();
    var kHeaders = kData[0];
    var colTgl = -1;
    for (var h = 0; h < kHeaders.length; h++) {
      var hName = String(kHeaders[h] || '').trim().toLowerCase().replace(/[^a-z]/g, '');
      if (hName === 'tglmasuk' || hName === 'startwork' || hName === 'joindate') {
        colTgl = h;
        break;
      }
    }

    if (colTgl > -1) {
      var kUpdates = [];
      for (var r = 1; r < kData.length; r++) {
        var rawVal = kData[r][colTgl];
        var formatted = formatMusashiDateGAS(rawVal);
        kUpdates.push([formatted]);
        if (formatted !== String(rawVal)) formattedCount++;
      }
      kSheet.getRange(2, colTgl + 1, kUpdates.length, 1).setNumberFormat('@').setValues(kUpdates);
      kSheet.getRange(2, colTgl + 1, kUpdates.length, 1).setHorizontalAlignment('center');
    }
  }

  // 2. Format di sheet 'Dokumen_Dinilai' (kolom START WORK)
  var dSheet = ss.getSheetByName('Dokumen_Dinilai');
  if (dSheet && dSheet.getLastRow() > 1) {
    var dData = dSheet.getDataRange().getValues();
    var dHeaders = dData[0];
    var colSw = -1;
    for (var dh = 0; dh < dHeaders.length; dh++) {
      var dhName = String(dHeaders[dh] || '').trim().toLowerCase().replace(/[^a-z]/g, '');
      if (dhName === 'startwork' || dhName === 'tglmasuk') {
        colSw = dh;
        break;
      }
    }

    if (colSw > -1) {
      var dUpdates = [];
      for (var dr = 1; dr < dData.length; dr++) {
        var dRawVal = dData[dr][colSw];
        var dFormatted = formatMusashiDateGAS(dRawVal);
        dUpdates.push([dFormatted]);
      }
      dSheet.getRange(2, colSw + 1, dUpdates.length, 1).setNumberFormat('@').setValues(dUpdates);
      dSheet.getRange(2, colSw + 1, dUpdates.length, 1).setHorizontalAlignment('center');
    }
  }

  if (!silent) {
    showAlertSafely(
      '📅 Format Tanggal Masuk Berhasil Dirapikan!\n\n' +
      'Format resmi Musashi (dd-Mmm-yy) telah diterapkan ke seluruh baris karyawan dan Dokumen_Dinilai.\n' +
      'Contoh tampilan: 07-May-96, 16-Jul-12, 20-Aug-01, 30-Apr-12, 28-May-12, 07-Dec-12.'
    );
  }
}

/**
 * HELPER RESMI: Memformat nilai tanggal ke format Musashi: dd-Mmm-yy
 * Mendukung Date object, String ISO (1996-05-07T17:00:00.000Z), YYYY-MM-DD, dan DD/MM/YYYY
 */
function formatMusashiDateGAS(rawDate) {
  if (!rawDate || rawDate === '-' || rawDate === 'null' || rawDate === 'undefined') {
    return '-';
  }

  var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

  // Jika berupa Object Date JavaScript
  if (Object.prototype.toString.call(rawDate) === '[object Date]') {
    if (isNaN(rawDate.getTime())) return '-';
    var d = ('0' + rawDate.getDate()).slice(-2);
    var m = months[rawDate.getMonth()];
    var y = String(rawDate.getFullYear()).slice(-2);
    return d + '-' + m + '-' + y;
  }

  var str = String(rawDate).trim();

  // Jika sudah dalam format dd-Mmm-yy (contoh: 07-May-96, 16-Jul-12, 20-Aug-01)
  var regexAlready = /^(\d{2})-([A-Za-z]{3})-(\d{2})$/;
  var matchA = str.match(regexAlready);
  if (matchA) {
    return matchA[1] + '-' + matchA[2].charAt(0).toUpperCase() + matchA[2].slice(1).toLowerCase() + '-' + matchA[3];
  }

  // Jika format ISO YYYY-MM-DD... (contoh: 1996-05-07T17:00:00.000Z atau 1996-05-07)
  var regexIso = /^(\d{4})-(\d{2})-(\d{2})/;
  var matchIso = str.match(regexIso);
  if (matchIso) {
    var year = matchIso[1].slice(-2);
    var monthNum = parseInt(matchIso[2], 10);
    var day = matchIso[3];
    var monthStr = months[monthNum - 1] || 'Jan';
    return day + '-' + monthStr + '-' + year;
  }

  // Jika format DD/MM/YYYY atau DD-MM-YYYY
  var regexDm = /^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/;
  var matchDm = str.match(regexDm);
  if (matchDm) {
    var day2 = ('0' + matchDm[1]).slice(-2);
    var monthNum2 = parseInt(matchDm[2], 10);
    var year2 = matchDm[3].slice(-2);
    var monthStr2 = months[monthNum2 - 1] || 'Jan';
    return day2 + '-' + monthStr2 + '-' + year2;
  }

  // Fallback ke Date parser
  var parsed = new Date(str);
  if (!isNaN(parsed.getTime())) {
    var d3 = ('0' + parsed.getDate()).slice(-2);
    var m3 = months[parsed.getMonth()];
    var y3 = String(parsed.getFullYear()).slice(-2);
    return d3 + '-' + m3 + '-' + y3;
  }

  return str;
}

/**
 * 3. STRUKTUR FOLDER GOOGLE DRIVE DINAMIS DARI DATA DEPARTEMEN KARYAWAN
 * Membaca Departemen, Divisi, dan Seksi langsung dari data sheet 'karyawan' asli tanpa dummy!
 */
function setupDriveFolders() {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var empSheet = ss.getSheetByName('karyawan');
  var rootFolder = DriveApp.getFolderById(CONFIG.DRIVE_FOLDER_ID);

  if (!empSheet || empSheet.getLastRow() <= 1) {
    showAlertSafely('⚠️ Sheet karyawan belum memiliki data. Silakan isi data karyawan terlebih dahulu agar departemen dapat dibaca otomatis.');
    return;
  }

  var data = empSheet.getDataRange().getValues();
  var headers = data[0];

  var idxDiv = -1, idxDept = -1, idxSeksi = -1, idxJabatan = -1;
  for (var h = 0; h < headers.length; h++) {
    var head = String(headers[h] || '').trim().toLowerCase();
    if (head === 'division' || head === 'divisi') idxDiv = h;
    else if (head === 'departemen' || head === 'department' || head === 'dept') idxDept = h;
    else if (head === 'seksi' || head === 'section' || head === 'nama_sect' || head === 'nama_bagian') {
      if (idxSeksi === -1) idxSeksi = h;
    }
    else if (head === 'jabatan' || head === 'position' || head === 'posisi' || head === 'level_form') {
      if (idxJabatan === -1) idxJabatan = h;
    }
  }

  // 1. Kumpulkan daftar tahun target secara dinamis
  var targetYears = {};
  var currentYearNum = new Date().getFullYear();
  targetYears[String(currentYearNum)] = true;       // Tahun berjalan saat ini (misal: 2026)
  targetYears[String(currentYearNum + 1)] = true;   // Tahun berikutnya (misal: 2027)

  var periodeSheet = ss.getSheetByName('periode');
  if (periodeSheet && periodeSheet.getLastRow() > 1) {
    var pData = periodeSheet.getDataRange().getValues();
    var pHeaders = pData[0];
    var pYearIdx = -1;
    for (var ph = 0; ph < pHeaders.length; ph++) {
      if (String(pHeaders[ph] || '').trim().toLowerCase() === 'tahun') {
        pYearIdx = ph;
        break;
      }
    }
    if (pYearIdx > -1) {
      for (var pr = 1; pr < pData.length; pr++) {
        var yrVal = String(pData[pr][pYearIdx] || '').trim();
        if (yrVal && yrVal.length === 4) {
          targetYears[yrVal] = true;
        }
      }
    }
  }

  // 2. Kumpulkan struktur hierarki 100% DINAMIS dari data karyawan asli
  var hierarchy = {};
  var deptList = {};
  var divCount = 0, deptCount = 0, seksiCount = 0, posCount = 0;
  var uniqueRoles = {};
  var standardRoles = ['FOREMAN', 'SECTION_HEAD', 'DEPT_HEAD', 'DIV_HEAD', 'ADMIN'];
  for (var sr = 0; sr < standardRoles.length; sr++) {
    uniqueRoles[standardRoles[sr]] = true;
  }

  for (var i = 1; i < data.length; i++) {
    var row = data[i];
    var div = (idxDiv > -1 && row[idxDiv]) ? String(row[idxDiv]).trim().toUpperCase() : '';
    var dept = (idxDept > -1 && row[idxDept]) ? String(row[idxDept]).trim().toUpperCase() : '';
    var seksi = (idxSeksi > -1 && row[idxSeksi]) ? String(row[idxSeksi]).trim().toUpperCase() : '';
    var jab = (idxJabatan > -1 && row[idxJabatan]) ? String(row[idxJabatan]).trim().toUpperCase() : '';

    if (!dept && !div && !seksi) continue;

    if (!div) {
      if (dept.indexOf('QA') > -1 || dept.indexOf('QUALITY') > -1) div = 'QUALITY CONTROL DIV';
      else if (dept.indexOf('ENGINEERING') > -1) div = 'TECHNICAL DIV';
      else if (dept.indexOf('HR') > -1 || dept.indexOf('FINANCE') > -1) div = 'GENERAL AFFAIRS DIV';
      else div = 'MANUFACTURING DIVISION';
    }

    if (!dept) dept = 'GENERAL DEPT';

    if (!hierarchy[div]) {
      hierarchy[div] = {};
      divCount++;
    }
    if (!hierarchy[div][dept]) {
      hierarchy[div][dept] = {};
      deptCount++;
      deptList[dept] = true;
    }
    if (seksi && seksi !== '-' && seksi !== 'SEMUA') {
      if (!hierarchy[div][dept][seksi]) {
        hierarchy[div][dept][seksi] = true;
        seksiCount++;
      }
    }

    if (jab && jab !== '-') {
      var cleanJab = jab.replace(/[^A-Z0-9]/g, '_');
      if (cleanJab && !uniqueRoles[cleanJab]) {
        uniqueRoles[cleanJab] = true;
      }
    }
  }

  // 3. Bangun folder di Google Drive sesuai Departemen Karyawan Asli
  var yearsList = Object.keys(targetYears).sort();
  for (var y = 0; y < yearsList.length; y++) {
    var yFolder = getOrCreateSubfolder(rootFolder, 'TAHUN ' + yearsList[y]);
    for (var dName in hierarchy) {
      var divFolder = getOrCreateSubfolder(yFolder, dName);
      var deptsObj = hierarchy[dName];
      for (var dpName in deptsObj) {
        var deptFolder = getOrCreateSubfolder(divFolder, dpName);
        var seksisObj = deptsObj[dpName];
        for (var sName in seksisObj) {
          getOrCreateSubfolder(deptFolder, sName);
        }
      }
    }
  }

  // 4. Buat subfolder tanda tangan
  var mainTtdFolder = getOrCreateSubfolder(rootFolder, 'TANDA_TANGAN');
  for (var posKey in uniqueRoles) {
    getOrCreateSubfolder(mainTtdFolder, posKey);
    posCount++;
  }

  var deptsFound = Object.keys(deptList);
  showAlertSafely(
    '✅ Sukses! Folder Google Drive Berhasil Dibuat Berdasarkan Data Departemen Karyawan Asli:\n\n' +
    '📁 Departemen Karyawan Ditemukan (' + deptsFound.length + '):\n' +
    '   • ' + deptsFound.join('\n   • ') + '\n\n' +
    '📁 Tahun Folder Arsip: ' + yearsList.map(function(yr) { return 'TAHUN ' + yr; }).join(', ') + '\n' +
    '   ├── ' + divCount + ' Folder Divisi\n' +
    '   ├── ' + deptCount + ' Folder Departemen\n' +
    '   └── ' + seksiCount + ' Folder Seksi\n\n' +
    '📁 TANDA_TANGAN: ' + posCount + ' Posisi/Role\n\n' +
    'Semua folder dibuat 100% otomatis dari data sheet karyawan tanpa data dummy.'
  );
}

/**
 * 4. UJI AKSES DRIVE & INISIALISASI FOLDER TANDA TANGAN
 */
function testDriveAccess() {
  try {
    var rootFolder = DriveApp.getFolderById(CONFIG.DRIVE_FOLDER_ID);
    var ttdFolder = getOrCreateSubfolder(rootFolder, 'TANDA_TANGAN');

    var testFileName = 'test_permission_' + new Date().getTime() + '.txt';
    var testFile = ttdFolder.createFile(testFileName, 'Drive Access OK');
    var fileId = testFile.getId();
    testFile.setTrashed(true);

    showAlertSafely('✅ Koneksi Google Drive Berhasil!\n\nFolder Induk: ' + rootFolder.getName() + '\nFolder Tanda Tangan: TANDA_TANGAN');
  } catch (err) {
    showAlertSafely('❌ Gagal mengakses Google Drive:\n' + err.toString() + '\n\nPastikan Anda sudah menyetujui (Authorize) izin akses saat diminta Google.');
  }
}

/**
 * 4B. GENERATE & SINKRONKAN BARCODE TTD SETIAP USER KE GOOGLE DRIVE & TAMPIL DI SHEET USERS
 * - Membuat berkas gambar QR Code resmi untuk setiap user di sheet users
 * - Menyimpan berkas di Google Drive: TANDA_TANGAN / [ROLE] / [NRP]_[NAMA].png
 * - Di sheet users:
 *     - Kolom 'barcodettd': rumus =IMAGE("url") agar gambar barcode LANGSUNG MUNCUL di sel!
 *     - Kolom 'signature_path_link': tautan Google Drive langsung ke berkas gambar PNG
 */
function syncUserBarcodesToDriveAndSheet() {
  try {
    var ss = SpreadsheetApp.getActiveSpreadsheet();
    var uSheet = ss.getSheetByName('users');
    if (!uSheet || uSheet.getLastRow() < 2) {
      showAlertSafely('Sheet users tidak ditemukan atau belum ada data user.');
      return;
    }

    var rootFolder = DriveApp.getFolderById(CONFIG.DRIVE_FOLDER_ID);
    var mainTtdFolder = getOrCreateSubfolder(rootFolder, 'TANDA_TANGAN');

    var uData = uSheet.getDataRange().getValues();
    var headers = uData[0];

    var colNrp = -1, colNama = -1, colRole = -1, colBarcodeTtd = -1, colSigLink = -1;
    for (var h = 0; h < headers.length; h++) {
      var hName = String(headers[h] || '').trim().toLowerCase();
      if (hName === 'nrp' || hName === 'nip' || (hName === 'id' && colNrp === -1)) colNrp = h;
      if (hName === 'nama' || hName === 'nama_lengkap' || hName === 'name') colNama = h;
      if (hName === 'role' || hName === 'jabatan' || hName === 'position') colRole = h;
      if (hName === 'barcodettd') colBarcodeTtd = h;
      if (hName === 'signature_path_link' || hName === 'signature_path' || hName === 'ttdbarcode') {
        if (colSigLink === -1) colSigLink = h;
      }
    }

    if (colBarcodeTtd === -1) {
      colBarcodeTtd = headers.length;
      uSheet.getRange(1, colBarcodeTtd + 1).setValue('barcodettd');
      headers.push('barcodettd');
    }
    if (colSigLink === -1) {
      colSigLink = headers.length;
      uSheet.getRange(1, colSigLink + 1).setValue('signature_path_link');
      headers.push('signature_path_link');
    } else {
      uSheet.getRange(1, colSigLink + 1).setValue('signature_path_link');
    }

    formatSheetColumns(uSheet, 'users');

    var processedCount = 0;

    for (var r = 1; r < uData.length; r++) {
      var nrp = (colNrp > -1) ? String(uData[r][colNrp] || '').trim() : '';
      var nama = (colNama > -1) ? String(uData[r][colNama] || '').trim().toUpperCase() : '';
      var role = (colRole > -1) ? String(uData[r][colRole] || 'FOREMAN').trim().toUpperCase().replace(/[^A-Z0-9]/g, '_') : 'FOREMAN';

      if (!nrp || !nama) continue;

      var cleanNama = nama.replace(/[^A-Z0-9]/g, '_');
      var filename = nrp + '_' + cleanNama + '_' + role + '.png';

      var roleFolder = getOrCreateSubfolder(mainTtdFolder, role);

      var payload = "PT MUSASHI AUTO PARTS INDONESIA\nE-APPROVAL DIGITAL SIGNATURE\nNRP: " + nrp + "\nNAMA: " + nama + "\nROLE: " + role + "\nSTATUS: VERIFIED";
      var qrApiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=8&data=" + encodeURIComponent(payload);

      try {
        var blob = UrlFetchApp.fetch(qrApiUrl).getBlob().setName(filename);

        var existingFiles = roleFolder.getFilesByName(filename);
        while (existingFiles.hasNext()) {
          var ef = existingFiles.next();
          if (!ef.isTrashed()) ef.setTrashed(true);
        }

        var file = roleFolder.createFile(blob);
        try {
          file.setSharing(DriveApp.Access.ANYONE_WITH_LINK, DriveApp.Permission.VIEW);
        } catch (eShare) {}

        var driveViewUrl = file.getUrl();

        // 1. Tampilkan gambar Barcode di sheet users (menggunakan rumus =IMAGE agar otomatis render visual)
        uSheet.getRange(r + 1, colBarcodeTtd + 1).setFormula('=IMAGE("' + qrApiUrl + '")');
        // 2. Simpan tautan Google Drive berkas PNG di kolom signature_path_link
        uSheet.getRange(r + 1, colSigLink + 1).setValue(driveViewUrl);

        uSheet.setRowHeight(r + 1, 75);
        uSheet.getRange(r + 1, colBarcodeTtd + 1).setHorizontalAlignment('center').setVerticalAlignment('middle');
        processedCount++;
      } catch (eErr) {
        Logger.log("Error generating barcode for " + nama + ": " + eErr);
      }
    }

    formatSheetColumns(uSheet, 'users');

    showAlertSafely(
      '✅ Sinkronisasi Barcode TTD Users Selesai!\n\n' +
      '• ' + processedCount + ' Barcode QR berhasil dibuat.\n' +
      '• Berkas gambar PNG tersimpan rapi di Google Drive (Folder TANDA_TANGAN / [ROLE]).\n' +
      '• Barcode TAMPIL LANGSUNG secara visual di kolom barcodettd sheet users.\n' +
      '• Tautan berkas Drive tersimpan di kolom signature_path_link.'
    );
  } catch (err) {
    showAlertSafely('Gagal sinkron barcode: ' + err.toString());
  }
}

/**
 * 5. PENGHAPUSAN DAN PEMBERSIHAN DATA PENILAIAN & BERKAS DRIVE (CASCADE WIPE)
 */

/**
 * Menghapus / memindahkan ke Trash berkas PDF di Google Drive berdasarkan daftar NRP atau fileId
 */
function deleteDriveFilesByNrps(nrpsList, fileIdsList) {
  var deletedCount = 0;
  var rootFolder = DriveApp.getFolderById(CONFIG.DRIVE_FOLDER_ID);

  // 1. Hapus berdasarkan file_ids spesifik jika ada
  if (fileIdsList && Array.isArray(fileIdsList)) {
    for (var f = 0; f < fileIdsList.length; f++) {
      var fid = String(fileIdsList[f] || '').trim();
      if (!fid) continue;
      try {
        var fObj = DriveApp.getFileById(fid);
        if (fObj && !fObj.isTrashed()) {
          fObj.setTrashed(true);
          deletedCount++;
        }
      } catch (eFid) {
        Logger.log('Gagal trash file ID ' + fid + ': ' + eFid);
      }
    }
  }

  // 2. Cari berdasarkan pola nama berkas yang mengandung NRP target
  if (nrpsList && nrpsList.length > 0) {
    var nrpMap = {};
    for (var n = 0; n < nrpsList.length; n++) {
      var nrpStr = String(nrpsList[n] || '').trim();
      if (nrpStr) nrpMap[nrpStr] = true;
    }

    function searchAndTrashByNrp(folder) {
      var files = folder.getFiles();
      while (files.hasNext()) {
        var file = files.next();
        if (file.isTrashed()) continue;
        var fName = file.getName();
        for (var tNrp in nrpMap) {
          var p1 = '_' + tNrp + '_';
          var p2 = 'PK_' + tNrp + '_';
          var p3 = tNrp + '_';
          if (fName.indexOf(p1) > -1 || fName.indexOf(p2) > -1 || fName.indexOf(p3) === 0) {
            file.setTrashed(true);
            deletedCount++;
            break;
          }
        }
      }

      var subfolders = folder.getFolders();
      while (subfolders.hasNext()) {
        var sub = subfolders.next();
        if (!sub.isTrashed()) {
          searchAndTrashByNrp(sub);
        }
      }
    }

    try {
      searchAndTrashByNrp(rootFolder);
    } catch (eSearch) {
      Logger.log('Error saat mencari berkas Drive berdasarkan NRP: ' + eSearch);
    }
  }

  return deletedCount;
}

/**
 * Menghapus seluruh berkas PDF penilaian di Google Drive (misal saat reset atau hapus semua karyawan)
 */
function wipeAllAppraisalPdfsInDrive() {
  var rootFolder = DriveApp.getFolderById(CONFIG.DRIVE_FOLDER_ID);
  var deletedCount = 0;

  function searchAndTrashAllPdfs(folder) {
    var files = folder.getFiles();
    while (files.hasNext()) {
      var file = files.next();
      if (file.isTrashed()) continue;
      var fName = file.getName().toLowerCase();
      var mime = file.getMimeType();
      if (mime === 'application/pdf' || fName.endsWith('.pdf') || fName.indexOf('pk_') === 0) {
        file.setTrashed(true);
        deletedCount++;
      }
    }

    var subfolders = folder.getFolders();
    while (subfolders.hasNext()) {
      var sub = subfolders.next();
      if (!sub.isTrashed()) {
        // Folder TANDA_TANGAN dipelihara kecuali jika diminta khusus
        if (sub.getName() !== 'TANDA_TANGAN') {
          searchAndTrashAllPdfs(sub);
        }
      }
    }
  }

  try {
    searchAndTrashAllPdfs(rootFolder);
  } catch (eWipe) {
    Logger.log('Error saat wipe PDF Drive: ' + eWipe);
  }

  return deletedCount;
}

/**
 * Mengosongkan seluruh baris data penilaian di seluruh tabel (tetap pertahankan header resmi Musashi)
 */
function wipeAllAppraisalSheetsData() {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var targetSheets = ['karyawan', 'dokumen', 'Dokumen_Dinilai', 'nilai', 'indikator', 'tanda_tangan', 'kuota_departemen', 'Master Tanda Tangan'];

  for (var i = 0; i < targetSheets.length; i++) {
    var sName = targetSheets[i];
    var sh = ss.getSheetByName(sName);
    if (!sh) continue;

    var lastRow = sh.getLastRow();
    var lastCol = sh.getLastColumn();
    if (lastRow > 1 && lastCol > 0) {
      sh.deleteRows(2, lastRow - 1);
    }
    formatSheetColumns(sh, sName);
  }
}

/**
 * Hapus data baris untuk daftar NRP tertentu dari seluruh sheet penilaian
 */
function deleteEmployeeRowsFromSheets(nrpsList) {
  if (!nrpsList || nrpsList.length === 0) return {};
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var targetSheets = ['karyawan', 'dokumen', 'Dokumen_Dinilai', 'nilai', 'indikator', 'tanda_tangan', 'Master Tanda Tangan'];
  var nrpMap = {};
  for (var n = 0; n < nrpsList.length; n++) {
    var s = String(nrpsList[n] || '').trim().toLowerCase();
    if (s) nrpMap[s] = true;
  }

  var stats = {};

  for (var t = 0; t < targetSheets.length; t++) {
    var sName = targetSheets[t];
    var sh = ss.getSheetByName(sName);
    if (!sh || sh.getLastRow() <= 1) continue;

    var data = sh.getDataRange().getValues();
    var headers = data[0].map(function(h) { return String(h).trim().toLowerCase(); });
    
    var keyColIdx = headers.indexOf('nrp');
    if (keyColIdx === -1) keyColIdx = 0;

    var count = 0;
    for (var r = data.length - 1; r >= 1; r--) {
      var val = String(data[r][keyColIdx]).trim().toLowerCase();
      if (nrpMap[val]) {
        sh.deleteRow(r + 1);
        count++;
      }
    }
    stats[sName] = count;
  }

  return stats;
}

/**
 * Menu UI: Kosongkan Seluruh Data Penilaian & PDF Drive (Wipe Total)
 */
function wipeAllDataAndPdfsMenu() {
  var ui = SpreadsheetApp.getUi();
  var response = ui.alert(
    '⚠️ KONFIRMASI PEMBERSIHAN TOTAL',
    'Apakah Anda yakin ingin MENGHAPUS SELURUH data pada sheet karyawan, dokumen, Dokumen_Dinilai, nilai, indikator, tanda tangan, kuota departemen, SERTA seluruh berkas PDF penilaian di Google Drive?\n\nTindakan ini akan mengosongkan seluruh data penilaian dan memindahkan seluruh PDF ke Trash.',
    ui.ButtonSet.YES_NO
  );

  if (response !== ui.Button.YES) {
    showAlertSafely('Pembersihan total dibatalkan.');
    return;
  }

  // 1. Bersihkan seluruh baris data di spreadsheet
  wipeAllAppraisalSheetsData();

  // 2. Bersihkan seluruh berkas PDF di Google Drive
  var trashedPdfs = wipeAllAppraisalPdfsInDrive();

  // 3. Bersihkan sheet usang / duplikat
  cleanUnusedSheets(true);

  showAlertSafely(
    '✅ Pembersihan Total Berhasil!\n\n' +
    '• Seluruh sheet penilaian (karyawan, dokumen, Dokumen_Dinilai, nilai, dll) telah dikosongkan dengan rapi.\n' +
    '• ' + trashedPdfs + ' berkas PDF penilaian di Google Drive telah dipindahkan ke Trash.\n' +
    '• Header dan format resmi Musashi tetap terjaga utuh dan siap digunakan kembali.'
  );
}

/**
 * 6. RINGKASAN STATUS DATA DATABASE
 */
function showDatabaseStats() {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var empSheet = ss.getSheetByName('karyawan');
  var docSheet = ss.getSheetByName('dokumen');
  var userSheet = ss.getSheetByName('users');
  var dinilaiSheet = ss.getSheetByName('Dokumen_Dinilai');
  var ttdSheet = ss.getSheetByName('Master Tanda Tangan');
  var totalKaryawan = empSheet ? Math.max(0, empSheet.getLastRow() - 1) : 0;
  var totalDokumen = docSheet ? Math.max(0, docSheet.getLastRow() - 1) : 0;
  var totalUsers = userSheet ? Math.max(0, userSheet.getLastRow() - 1) : 0;
  var totalDinilai = dinilaiSheet ? Math.max(0, dinilaiSheet.getLastRow() - 1) : 0;
  var totalTtd = ttdSheet ? Math.max(0, ttdSheet.getLastRow() - 1) : 0;

  var msg = '📊 STATISTIK BASIS DATA MUSASHI E-APPROVAL:\n\n' +
            '• Data Karyawan: ' + totalKaryawan + ' Orang\n' +
            '• Dokumen Penilaian: ' + totalDokumen + ' Dokumen\n' +
            '• Akun Pengguna: ' + totalUsers + ' Akun\n' +
            '• Dokumen Dinilai (Laporan): ' + totalDinilai + ' Baris\n' +
            '• Master Tanda Tangan: ' + totalTtd + ' Berkas\n\n' +
            'Spreadsheet ID: ' + ss.getId();
  showAlertSafely(msg);
}

/**
 * =========================================================================
 * WEB APP ENDPOINT HTTP: GET & POST (Terhubung Penuh dengan Frontend Laravel)
 * =========================================================================
 */
function doGet(e) {
  try {
    var p = (e && e.parameter) ? e.parameter : {};
    var action = p.action || 'ping';
    var ss = SpreadsheetApp.getActiveSpreadsheet();

    if (action === 'ping') {
      return jsonResponse({
        status: 'ok',
        app: 'Musashi E-Approval Apps Script',
        spreadsheet_id: ss.getId(),
        spreadsheet_name: ss.getName(),
        drive_folder_id: CONFIG.DRIVE_FOLDER_ID,
        default_year: CONFIG.DEFAULT_YEAR,
        time: new Date().toISOString()
      });
    }

    if (action === 'init_sheets' || action === 'setup_sheets') {
      setupDatabaseSheets();
      return jsonResponse({
        status: 'success',
        message: 'Seluruh sheet database resmi telah berhasil diinisialisasi.',
        tables: Object.keys(CONFIG_TABLES),
        time: new Date().toISOString()
      });
    }

    if (action === 'clean_unused_sheets' || action === 'delete_unused_sheets') {
      var deleted = cleanUnusedSheets(true);
      return jsonResponse({
        status: 'success',
        message: 'Sheet yang tidak diperlukan berhasil dibersihkan.',
        deleted_sheets: deleted,
        remaining_tables: Object.keys(CONFIG_TABLES),
        time: new Date().toISOString()
      });
    }

    if (action === 'format_dates') {
      formatAllDatesInSheets(true);
      return jsonResponse({
        status: 'success',
        message: 'Format tanggal tgl_masuk dan START WORK berhasil dirapikan menjadi dd-Mmm-yy.',
        time: new Date().toISOString()
      });
    }

    if (action === 'wipe_all_data' || action === 'wipe_appraisals' || action === 'reset_database') {
      wipeAllAppraisalSheetsData();
      var trashedPdfs = wipeAllAppraisalPdfsInDrive();
      cleanUnusedSheets(true);
      return jsonResponse({
        status: 'success',
        action: 'wipe_all_data',
        message: 'Seluruh data karyawan, dokumen penilaian, Dokumen_Dinilai, dan berkas PDF Drive berhasil dibersihkan total.',
        trashed_drive_pdfs: trashedPdfs,
        time: new Date().toISOString()
      });
    }

    if (action === 'pull_all' || action === 'export_database') {
      var result = {};
      var sheetNames = Object.keys(CONFIG_TABLES);
      for (var i = 0; i < sheetNames.length; i++) {
        var sName = sheetNames[i];
        var sh = ss.getSheetByName(sName);
        result[sName] = (sh && sh.getLastRow() > 1) ? sheetToObjects(sh) : [];
      }
      return jsonResponse({
        status: 'success',
        source: 'Google Spreadsheet Live (' + ss.getName() + ')',
        timestamp: new Date().toISOString(),
        data: result
      });
    }

    if (action === 'get_table' || action === 'get_tables') {
      var tableName = p.table || 'karyawan';
      var targetSheetName = (tableName === 'Master Karyawan') ? 'karyawan' : tableName;
      var sheet = ss.getSheetByName(targetSheetName);
      if (!sheet) {
        return jsonResponse({ status: 'error', message: 'Tabel ' + tableName + ' tidak ditemukan' }, 404);
      }
      return jsonResponse({ status: 'success', table: tableName, data: sheetToObjects(sheet) });
    }

    return jsonResponse({ status: 'ok', message: 'Musashi E-Approval Endpoint Aktif' });
  } catch (err) {
    return jsonResponse({ status: 'error', error: err.toString() }, 500);
  }
}

function doPost(e) {
  try {
    var postData = {};
    if (e && e.postData && e.postData.contents) {
      try {
        postData = JSON.parse(e.postData.contents);
      } catch (ex) {
        postData = (e.parameter) ? e.parameter : {};
      }
    } else if (e && e.parameter) {
      postData = e.parameter;
    }

    var action = postData.action || (e && e.parameter ? e.parameter.action : 'push_all');
    var ss = SpreadsheetApp.getActiveSpreadsheet();

    if (action === 'init_sheets' || action === 'setup_sheets') {
      setupDatabaseSheets();
      return jsonResponse({
        status: 'success',
        message: 'Seluruh sheet database resmi telah berhasil diinisialisasi.',
        tables: Object.keys(CONFIG_TABLES),
        time: new Date().toISOString()
      });
    }

    if (action === 'clean_unused_sheets' || action === 'delete_unused_sheets') {
      var deleted = cleanUnusedSheets(true);
      return jsonResponse({
        status: 'success',
        message: 'Sheet yang tidak diperlukan berhasil dibersihkan.',
        deleted_sheets: deleted,
        remaining_tables: Object.keys(CONFIG_TABLES),
        time: new Date().toISOString()
      });
    }

    if (action === 'format_dates') {
      formatAllDatesInSheets(true);
      return jsonResponse({
        status: 'success',
        message: 'Format tanggal tgl_masuk dan START WORK berhasil dirapikan menjadi dd-Mmm-yy.',
        time: new Date().toISOString()
      });
    }

    // PEMBERSIHAN TOTAL (WIPE ALL APPRAISAL DATA & DRIVE PDFS)
    if (action === 'wipe_all_data' || action === 'wipe_appraisals' || action === 'reset_database') {
      wipeAllAppraisalSheetsData();
      var trashedPdfs = 0;
      if (postData.wipe_drive_pdfs !== false) {
        trashedPdfs = wipeAllAppraisalPdfsInDrive();
      }
      cleanUnusedSheets(true);
      return jsonResponse({
        status: 'success',
        action: 'wipe_all_data',
        message: 'Seluruh data karyawan, dokumen penilaian, Dokumen_Dinilai, dan berkas PDF Drive berhasil dibersihkan total.',
        trashed_drive_pdfs: trashedPdfs,
        timestamp: new Date().toISOString()
      });
    }

    // HAPUS DATA KARYAWAN & BERKAS DRIVE BERDASARKAN DAFTAR NRP / FILE ID
    if (action === 'delete_employee_data' || action === 'delete_employees') {
      var nrps = postData.nrps || (postData.nrp ? [postData.nrp] : []);
      var fileIds = postData.file_ids || (postData.file_id ? [postData.file_id] : []);

      var deletedRows = deleteEmployeeRowsFromSheets(nrps);
      var trashedDrivePdfs = deleteDriveFilesByNrps(nrps, fileIds);

      return jsonResponse({
        status: 'success',
        action: 'delete_employee_data',
        nrps: nrps,
        deleted_rows: deletedRows,
        trashed_drive_pdfs: trashedDrivePdfs,
        timestamp: new Date().toISOString()
      });
    }

    // A. SIMPAN TANDA TANGAN DIGITAL KE GOOGLE DRIVE & UPDATE DATABASE
    if (action === 'save_signature' || action === 'upload_signature') {
      var sigBase64 = String(postData.signature_base64 || postData.image_base64 || postData.ttd_image || postData.base64 || '');
      if (!sigBase64) {
        return jsonResponse({ status: 'error', message: 'Data gambar tanda tangan (base64) kosong.' }, 400);
      }

      var nrp = String(postData.nrp || '0').trim();
      var nama = String(postData.nama || postData.nama_lengkap || 'USER').toUpperCase().replace(/[^A-Z0-9]/g, '_');
      var position = String(postData.position || postData.role || postData.jabatan || 'FOREMAN').toUpperCase().replace(/[^A-Z0-9]/g, '_');
      var departemen = String(postData.department || postData.departemen || 'DEPT').toUpperCase().replace(/[^A-Z0-9]/g, '_');
      var division = String(postData.division || postData.divisi || 'MANUFACTURING DIVISION').toUpperCase().trim();

      var filename = postData.filename || (nrp + '_' + nama + '_' + position + '.png');

      var rootFolder = DriveApp.getFolderById(CONFIG.DRIVE_FOLDER_ID);
      var mainTtdFolder = getOrCreateSubfolder(rootFolder, 'TANDA_TANGAN');
      var roleFolder = getOrCreateSubfolder(mainTtdFolder, position);

      if (sigBase64.indexOf(',') > -1) {
        sigBase64 = sigBase64.split(',')[1];
      }

      var decodedBytes = Utilities.base64Decode(sigBase64);
      var blob = Utilities.newBlob(decodedBytes, 'image/png', filename);

      var existingFiles = roleFolder.getFilesByName(filename);
      while (existingFiles.hasNext()) {
        var oldFile = existingFiles.next();
        if (!oldFile.isTrashed()) oldFile.setTrashed(true);
      }

      var file = roleFolder.createFile(blob);
      try {
        file.setSharing(DriveApp.Access.ANYONE_WITH_LINK, DriveApp.Permission.VIEW);
      } catch (eShare) {}

      var fileUrl = file.getUrl();
      var folderPath = 'TANDA_TANGAN / ' + position;
      var nowFormatted = Utilities.formatDate(new Date(), 'Asia/Jakarta', 'yyyy-MM-dd HH:mm:ss');

      // Update sheet 'Master Tanda Tangan'
      try {
        var ttdSheet = ss.getSheetByName('Master Tanda Tangan');
        var ttdHeaders = CONFIG_TABLES['Master Tanda Tangan'];
        if (!ttdSheet) {
          ttdSheet = ss.insertSheet('Master Tanda Tangan');
          ttdSheet.appendRow(ttdHeaders);
        }

        var ttdData = ttdSheet.getDataRange().getValues();
        var rowUpdated = false;
        for (var tr = 1; tr < ttdData.length; tr++) {
          if (String(ttdData[tr][0]).trim() === nrp) {
            ttdSheet.getRange(tr + 1, 1, 1, 8).setValues([[nrp, nama, position, departemen, division, fileUrl, file.getId(), nowFormatted]]);
            rowUpdated = true;
            break;
          }
        }
        if (!rowUpdated) {
          ttdSheet.appendRow([nrp, nama, position, departemen, division, fileUrl, file.getId(), nowFormatted]);
        }
        formatSheetColumns(ttdSheet, 'Master Tanda Tangan');
      } catch (eSheetTtd) {
        Logger.log('Gagal update sheet Master Tanda Tangan: ' + eSheetTtd);
      }

      // Update kolom signature_path_link di sheet 'users'
      try {
        var uSheet = ss.getSheetByName('users');
        if (uSheet && uSheet.getLastRow() > 1) {
          var uData = uSheet.getDataRange().getValues();
          var uHeaders = uData[0];
          var uNrpCol = -1, uSigCol = -1;
          for (var uh = 0; uh < uHeaders.length; uh++) {
            var colName = String(uHeaders[uh]).toLowerCase().trim();
            if (colName === 'nrp') uNrpCol = uh;
            if (colName === 'signature_path_link' || colName === 'signature_path' || colName === 'ttdbarcode') {
              if (uSigCol === -1) uSigCol = uh;
            }
          }
          if (uNrpCol > -1 && uSigCol > -1) {
            for (var ur = 1; ur < uData.length; ur++) {
              if (String(uData[ur][uNrpCol]).trim() === nrp) {
                uSheet.getRange(ur + 1, uSigCol + 1).setValue(fileUrl);
                break;
              }
            }
          }
        }
      } catch (eUserSheet) {
        Logger.log('Gagal update sheet users: ' + eUserSheet);
      }

      return jsonResponse({
        status: 'success',
        file_id: file.getId(),
        file_name: filename,
        file_url: fileUrl,
        folder_path: folderPath,
        timestamp: new Date().toISOString()
      });
    }

    // B. SIMPAN ARSIP PDF KE DRIVE DENGAN PENAMAAN DAN STRUKTUR HIERARKI RAPI
    if (action === 'save_pdf') {
      var pdfBase64 = postData.pdf_base64;
      if (!pdfBase64) {
        return jsonResponse({ status: 'error', message: 'Data pdf_base64 kosong.' }, 400);
      }

      var nrp = String(postData.nrp || '0').trim();
      var nama = String(postData.nama_lengkap || 'KARYAWAN').toUpperCase().replace(/[^A-Z0-9]/g, '_');
      var jabatan = String(postData.jabatan || 'OPERATOR').toUpperCase().replace(/[^A-Z0-9]/g, '_');
      var golongan = String(postData.golongan || 'KJ').toUpperCase().replace(/[^A-Z0-9]/g, '_');
      var departemen = String(postData.departemen || '').toUpperCase().trim();
      var division = String(postData.division || postData.divisi || '').toUpperCase().trim();
      var seksi = String(postData.seksi || '').toUpperCase().trim();
      var tahun = String(postData.tahun || postData.periode_tahun || CONFIG.DEFAULT_YEAR || new Date().getFullYear()).trim();
      if (!tahun || tahun === 'undefined' || tahun === 'null' || tahun.length < 4) {
        tahun = String(new Date().getFullYear());
      }

      if ((!division || !seksi) && nrp) {
        var empSheet = ss.getSheetByName('karyawan');
        if (empSheet && empSheet.getLastRow() > 1) {
          var eData = empSheet.getDataRange().getValues();
          var eHeaders = eData[0];
          var eNrpIdx = -1, eDivIdx = -1, eDeptIdx = -1, eSeksiIdx = -1;
          for (var eh = 0; eh < eHeaders.length; eh++) {
            var col = String(eHeaders[eh] || '').trim().toLowerCase();
            if (col === 'nrp') eNrpIdx = eh;
            else if (col === 'division' || col === 'divisi') eDivIdx = eh;
            else if (col === 'departemen' || col === 'department') eDeptIdx = eh;
            else if (col === 'seksi' || col === 'nama_sect' || col === 'nama_bagian') { if (eSeksiIdx === -1) eSeksiIdx = eh; }
          }
          if (eNrpIdx > -1) {
            for (var er = 1; er < eData.length; er++) {
              if (String(eData[er][eNrpIdx]).trim() === nrp) {
                if (!division && eDivIdx > -1) division = String(eData[er][eDivIdx] || '').trim().toUpperCase();
                if (!departemen && eDeptIdx > -1) departemen = String(eData[er][eDeptIdx] || '').trim().toUpperCase();
                if (!seksi && eSeksiIdx > -1) seksi = String(eData[er][eSeksiIdx] || '').trim().toUpperCase();
                break;
              }
            }
          }
        }
      }

      if (!division) division = 'MANUFACTURING DIVISION';
      if (!departemen) departemen = 'PRODUCTION 2 DEPT';

      var filename = 'PK_' + tahun + '_' + nrp + '_' + nama + '_' + jabatan + '_' + golongan + '.pdf';

      var rootFolder = DriveApp.getFolderById(CONFIG.DRIVE_FOLDER_ID);
      var yearFolder = getOrCreateSubfolder(rootFolder, 'TAHUN ' + tahun);
      var divFolder = getOrCreateSubfolder(yearFolder, division);
      var deptFolder = getOrCreateSubfolder(divFolder, departemen);
      var targetFolder = (seksi && seksi !== '-' && seksi !== 'SEMUA')
          ? getOrCreateSubfolder(deptFolder, seksi)
          : deptFolder;

      var decodedBytes = Utilities.base64Decode(pdfBase64);
      var blob = Utilities.newBlob(decodedBytes, 'application/pdf', filename);

      var existingFiles = targetFolder.getFilesByName(filename);
      while (existingFiles.hasNext()) {
        var oldFile = existingFiles.next();
        if (!oldFile.isTrashed()) oldFile.setTrashed(true);
      }

      var file = targetFolder.createFile(blob);
      try {
        file.setSharing(DriveApp.Access.ANYONE_WITH_LINK, DriveApp.Permission.VIEW);
      } catch (eShare) {}

      var folderPath = 'TAHUN ' + tahun + ' / ' + division + ' / ' + departemen + (seksi && seksi !== '-' ? ' / ' + seksi : '');

      return jsonResponse({
        status: 'success',
        file_id: file.getId(),
        file_name: filename,
        file_url: file.getUrl(),
        folder_path: folderPath,
        timestamp: new Date().toISOString()
      });
    }

    // C. SINKRONISASI MASSAL SEMUA DATA DARI LARAVEL KE SPREADSHEET
    if (action === 'push_all' || action === 'sync_database') {
      var payload = postData.data || postData;
      var updatedTables = [];

      for (var table in payload) {
        if (Array.isArray(payload[table])) {
          writeObjectsToSheet(ss, table, payload[table]);
          updatedTables.push(table);
        }
      }
      return jsonResponse({
        status: 'success',
        message: 'Data berhasil disinkronkan ke Google Spreadsheet',
        updated_tables: updatedTables,
        timestamp: new Date().toISOString()
      });
    }

    // D. HAPUS 1 BARIS KILAT (DELETE ROW BY NRP)
    // Otomatis menghapus baris di seluruh tabel DAN menghapus berkas PDF di Google Drive
    if (action === 'delete_row') {
      var targetTables = postData.tables || ['karyawan', 'dokumen', 'Dokumen_Dinilai', 'nilai', 'indikator', 'tanda_tangan', 'Master Tanda Tangan', 'users'];
      var keyColumn = (postData.key_column || 'nrp').toLowerCase();
      var keyValue = String(postData.key_value || '').trim().toLowerCase();
      var deletedStats = {};

      for (var t = 0; t < targetTables.length; t++) {
        var sName = targetTables[t];
        var sh = ss.getSheetByName(sName);
        if (!sh) continue;

        var data = sh.getDataRange().getValues();
        if (data.length <= 1) continue;

        var headers = data[0].map(function(h) { return String(h).trim().toLowerCase(); });
        var keyIdx = headers.indexOf(keyColumn);
        if (keyIdx === -1) keyIdx = 0;

        var count = 0;
        for (var r = data.length - 1; r >= 1; r--) {
          var cellVal = String(data[r][keyIdx]).trim().toLowerCase();
          if (cellVal === keyValue) {
            sh.deleteRow(r + 1);
            count++;
          }
        }
        deletedStats[sName] = count;
      }

      // Hapus berkas PDF di Drive jika keyColumn adalah nrp
      var trashedDriveFiles = 0;
      if (keyColumn === 'nrp' && keyValue) {
        trashedDriveFiles = deleteDriveFilesByNrps([keyValue], []);
      }

      return jsonResponse({
        status: 'success',
        action: 'delete_row',
        key_value: keyValue,
        deleted_stats: deletedStats,
        trashed_drive_pdfs: trashedDriveFiles,
        timestamp: new Date().toISOString()
      });
    }

    // E. UPDATE ATAU TAMBAH 1 BARIS KILAT (UPSERT ROW BY NRP)
    if (action === 'upsert_row') {
      var targetTables = postData.tables || ['karyawan'];
      var keyColumn = (postData.key_column || 'nrp').toLowerCase();
      var rowData = postData.row_data || {};
      var keyValue = String(postData.key_value || rowData[keyColumn] || rowData.nrp || '').trim().toLowerCase();
      var updatedStats = {};

      for (var t = 0; t < targetTables.length; t++) {
        var sName = targetTables[t];
        var sh = ss.getSheetByName(sName);
        if (!sh) {
          sh = ss.insertSheet(sName);
        }

        var data = sh.getDataRange().getValues();
        var headers = [];

        if (sh.getLastRow() === 0 || data.length === 0) {
          headers = CONFIG_TABLES[sName] || Object.keys(rowData);
          sh.appendRow(headers);
          data = [headers];
        } else {
          headers = data[0];
        }

        var headerLower = headers.map(function(h) { return String(h).trim().toLowerCase(); });
        var keyIdx = headerLower.indexOf(keyColumn);
        if (keyIdx === -1) keyIdx = 0;

        var rowValues = [];
        for (var h = 0; h < headers.length; h++) {
          var hName = headers[h];
          var hNameLower = headerLower[h];
          var val = (rowData[hName] !== undefined)
            ? rowData[hName]
            : ((rowData[hNameLower] !== undefined) ? rowData[hNameLower] : '');

          // Khusus tanggal masuk: selalu format resmi Musashi dd-Mmm-yy
          var hNorm = hNameLower.replace(/[^a-z]/g, '');
          if (hNorm === 'tglmasuk' || hNorm === 'startwork') {
            val = formatMusashiDateGAS(val);
          }
          
          if (sName === 'users' && hNameLower === 'barcodettd' && (!val || !String(val).startsWith('='))) {
            var uNrp = String(rowData['nrp'] || rowData['id'] || '').trim();
            var uNama = String(rowData['nama'] || rowData['nama_lengkap'] || '').trim();
            var uRole = String(rowData['role'] || '').trim();
            if (uNrp && uNama) {
              var qText = "PT MUSASHI AUTO PARTS INDONESIA\nE-APPROVAL DIGITAL SIGNATURE\nNRP: " + uNrp + "\nNAMA: " + uNama + "\nROLE: " + uRole;
              val = '=IMAGE("https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=8&data=' + encodeURIComponent(qText) + '")';
            }
          }

          rowValues.push(val !== null ? val : '');
        }

        var foundRowIndex = -1;
        for (var r = 1; r < data.length; r++) {
          if (String(data[r][keyIdx]).trim().toLowerCase() === keyValue) {
            foundRowIndex = r + 1;
            break;
          }
        }

        if (foundRowIndex > 0) {
          sh.getRange(foundRowIndex, 1, 1, rowValues.length).setValues([rowValues]);
          updatedStats[sName] = 'updated (row ' + foundRowIndex + ')';
        } else {
          sh.appendRow(rowValues);
          updatedStats[sName] = 'inserted (row ' + (sh.getLastRow()) + ')';
        }

        if (sName === 'users') {
          var targetRow = foundRowIndex > 0 ? foundRowIndex : sh.getLastRow();
          sh.setRowHeight(targetRow, 75);
        }

        formatSheetColumns(sh, sName);
      }

      return jsonResponse({
        status: 'success',
        action: 'upsert_row',
        key_value: keyValue,
        updated_stats: updatedStats,
        timestamp: new Date().toISOString()
      });
    }

    return jsonResponse({ status: 'unknown_action', action: action });
  } catch (err) {
    return jsonResponse({ status: 'error', error: err.toString() }, 500);
  }
}

// HELPER: Convert Sheet Rows to JSON Objects
function sheetToObjects(sheet) {
  var data = sheet.getDataRange().getValues();
  if (data.length <= 1) return [];
  var headers = data[0];
  var rows = [];
  for (var i = 1; i < data.length; i++) {
    var obj = {};
    for (var j = 0; j < headers.length; j++) {
      obj[headers[j]] = data[i][j];
    }
    rows.push(obj);
  }
  return rows;
}

// HELPER: Write Array of Objects to Sheet
function writeObjectsToSheet(ss, sheetName, items) {
  var targetName = (sheetName === 'Master Karyawan') ? 'karyawan' : sheetName;
  var sheet = ss.getSheetByName(targetName);
  if (!sheet) {
    sheet = ss.insertSheet(targetName);
  }

  // Jika payload kosong atau payload berisi objek kosong: Bersihkan baris lama tapi pertahankan header resmi
  var isEmptyPayload = (!items || items.length === 0);
  if (!isEmptyPayload && items.length === 1) {
    var firstObj = items[0];
    var allEmpty = true;
    for (var k in firstObj) {
      if (firstObj[k] !== '' && firstObj[k] !== null && firstObj[k] !== undefined) {
        allEmpty = false;
        break;
      }
    }
    if (allEmpty) isEmptyPayload = true;
  }

  if (isEmptyPayload) {
    var stdHeaders = CONFIG_TABLES[targetName];
    if (stdHeaders) {
      sheet.clear();
      sheet.appendRow(stdHeaders);
      formatSheetColumns(sheet, targetName);
    }
    return;
  }

  var headers = Object.keys(items[0]);

  // Normalisasi kolom users
  if (targetName === 'users') {
    for (var hIdx = 0; hIdx < headers.length; hIdx++) {
      if (headers[hIdx] === 'ttdbarcode' || headers[hIdx] === 'signature_path') {
        headers[hIdx] = 'signature_path_link';
      }
    }
  }

  sheet.clear();
  sheet.appendRow(headers);
  var rows = [];
  for (var i = 0; i < items.length; i++) {
    var row = [];
    for (var h = 0; h < headers.length; h++) {
      var hKey = headers[h];
      var val = (items[i][hKey] !== undefined)
        ? items[i][hKey]
        : (hKey === 'signature_path_link' ? (items[i]['ttdbarcode'] || items[i]['signature_path'] || '') : '');

      // Format kolom tanggal tgl_masuk dan START WORK ke format resmi Musashi: dd-Mmm-yy
      var hNorm = hKey.toLowerCase().replace(/[^a-z]/g, '');
      if (hNorm === 'tglmasuk' || hNorm === 'startwork') {
        val = formatMusashiDateGAS(val);
      }

      // Khusus sheet users: jika barcodettd belum berisi rumus =IMAGE, buat otomatis rumus visualnya
      if (targetName === 'users' && hNorm === 'barcodettd' && (!val || !String(val).startsWith('='))) {
        var uNrp = String(items[i]['nrp'] || items[i]['id'] || '').trim();
        var uNama = String(items[i]['nama'] || items[i]['nama_lengkap'] || '').trim();
        var uRole = String(items[i]['role'] || '').trim();
        if (uNrp && uNama) {
          var qText = "PT MUSASHI AUTO PARTS INDONESIA\nE-APPROVAL DIGITAL SIGNATURE\nNRP: " + uNrp + "\nNAMA: " + uNama + "\nROLE: " + uRole;
          val = '=IMAGE("https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=8&data=' + encodeURIComponent(qText) + '")';
        }
      }

      row.push(val !== null ? val : '');
    }
    rows.push(row);
  }

  if (rows.length > 0) {
    sheet.getRange(2, 1, rows.length, headers.length).setValues(rows);

    if (targetName === 'users') {
      for (var rIdx = 0; rIdx < rows.length; rIdx++) {
        sheet.setRowHeight(rIdx + 2, 75);
      }
    }
  }

  formatSheetColumns(sheet, targetName);
}

// HELPER: Get or Create Subfolder (mengabaikan folder yang terhapus di Trash)
function getOrCreateSubfolder(parentFolder, folderName) {
  var folders = parentFolder.getFoldersByName(folderName);
  while (folders.hasNext()) {
    var f = folders.next();
    if (!f.isTrashed()) {
      return f;
    }
  }
  return parentFolder.createFolder(folderName);
}

// HELPER: Format JSON Response
function jsonResponse(data, code) {
  return ContentService.createTextOutput(JSON.stringify(data))
    .setMimeType(ContentService.MimeType.JSON);
}
