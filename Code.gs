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
  get DEFAULT_YEAR() {
    return String(new Date().getFullYear());
  },
  COMPANY_NAME: 'PT MUSASHI AUTO PARTS INDONESIA'
};

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
    .addItem('🗑️ Hapus Sheet yang Tidak Diperlukan', 'cleanUnusedSheets')
    .addSeparator()
    .addItem('⚠️ Kosongkan Seluruh Data Penilaian & PDF Drive (Wipe Total)', 'wipeAllDataAndPdfsMenu')
    .addItem('✍️ Uji & Buat Folder Tanda Tangan Drive', 'testDriveAccess')
    .addSeparator()
    .addItem('📈 Cek Status & Statistik Data', 'showDatabaseStats')
    .addToUi();
}

/**
 * 2. DAFTAR NAMA SHEET & STRUKTUR KOLOM RESMI (DENGAN SUPERVISOR & IDENTITAS TTD)
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
 * 2B. KONFIGURASI LEBAR KOLOM & FORMAT PERATAAN RESMI
 */
var COLUMN_FORMATS = {
  'users': {
    widths: { 1: 60, 2: 220, 3: 220, 4: 140, 5: 90, 6: 180, 7: 70, 8: 100, 9: 280, 10: 160 },
    alignCenter: [1, 4, 5, 7, 8, 10]
  },
  'karyawan': {
    widths: { 1: 90, 2: 230, 3: 90, 4: 80, 5: 80, 6: 220, 7: 110, 8: 180, 9: 180, 10: 100, 11: 110, 12: 130, 13: 120, 14: 110, 15: 110, 16: 220, 17: 180, 18: 120, 19: 190 },
    alignCenter: [1, 3, 4, 5, 7, 10, 11, 12, 13, 14, 15, 18],
    textFormatCols: [1, 3, 4, 5, 7, 10, 11, 12, 13, 14, 15]
  },
  'Dokumen_Dinilai': {
    widths: { 1: 90, 2: 230, 3: 220, 4: 160, 5: 160, 6: 190, 7: 190, 8: 110, 9: 80, 10: 90, 11: 90, 12: 90, 13: 90, 14: 110, 15: 90, 16: 170, 17: 120, 18: 120, 19: 120, 20: 130 },
    alignCenter: [1, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20],
    textFormatCols: [1, 8, 9, 10]
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
 * Inisialisasi & Rapikan Seluruh Sheet Database
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
  cleanUnusedSheets(true);
  formatAllDatesInSheets(true);
  showAlertSafely('✅ Sukses! Seluruh tabel database resmi telah dibuat, dirapikan, dan sheet duplikat telah dibersihkan.');
}

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
      'Sheet yang dihapus: ' + (deletedNames.length > 0 ? deletedNames.join(', ') : 'Tidak ada sheet usang.')
    );
  }
  return deletedNames;
}

function formatAllDatesInSheets(silent) {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
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
        kUpdates.push([formatMusashiDateGAS(kData[r][colTgl])]);
      }
      kSheet.getRange(2, colTgl + 1, kUpdates.length, 1).setNumberFormat('@').setValues(kUpdates);
    }
  }

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
        dUpdates.push([formatMusashiDateGAS(dData[dr][colSw])]);
      }
      dSheet.getRange(2, colSw + 1, dUpdates.length, 1).setNumberFormat('@').setValues(dUpdates);
    }
  }
}

function formatMusashiDateGAS(rawDate) {
  if (!rawDate || rawDate === '-' || rawDate === 'null' || rawDate === 'undefined') return '-';
  var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  if (Object.prototype.toString.call(rawDate) === '[object Date]') {
    if (isNaN(rawDate.getTime())) return '-';
    return ('0' + rawDate.getDate()).slice(-2) + '-' + months[rawDate.getMonth()] + '-' + String(rawDate.getFullYear()).slice(-2);
  }
  var str = String(rawDate).trim();
  var regexAlready = /^(\d{2})-([A-Za-z]{3})-(\d{2})$/;
  var matchA = str.match(regexAlready);
  if (matchA) {
    return matchA[1] + '-' + matchA[2].charAt(0).toUpperCase() + matchA[2].slice(1).toLowerCase() + '-' + matchA[3];
  }
  var matchIso = str.match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (matchIso) {
    var mNum = parseInt(matchIso[2], 10);
    return matchIso[3] + '-' + (months[mNum - 1] || 'Jan') + '-' + matchIso[1].slice(-2);
  }
  var regexDm = /^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/;
  var matchDm = str.match(regexDm);
  if (matchDm) {
    var d2 = ('0' + matchDm[1]).slice(-2);
    var mNum2 = parseInt(matchDm[2], 10);
    var y2 = matchDm[3].slice(-2);
    return d2 + '-' + (months[mNum2 - 1] || 'Jan') + '-' + y2;
  }
  return str;
}

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

function setupDriveFolders() {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var empSheet = ss.getSheetByName('karyawan');
  var rootFolder = DriveApp.getFolderById(CONFIG.DRIVE_FOLDER_ID);
  if (!empSheet || empSheet.getLastRow() <= 1) return;

  var data = empSheet.getDataRange().getValues();
  var headers = data[0];
  var idxDiv = -1, idxDept = -1, idxSeksi = -1;
  for (var h = 0; h < headers.length; h++) {
    var head = String(headers[h] || '').trim().toLowerCase();
    if (head === 'division' || head === 'divisi') idxDiv = h;
    else if (head === 'departemen' || head === 'department' || head === 'dept') idxDept = h;
    else if (head === 'seksi' || head === 'section' || head === 'nama_sect') idxSeksi = h;
  }

  var currentYear = new Date().getFullYear();
  var yFolder = getOrCreateSubfolder(rootFolder, 'TAHUN ' + currentYear);

  for (var i = 1; i < data.length; i++) {
    var div = (idxDiv > -1 && data[i][idxDiv]) ? String(data[i][idxDiv]).trim().toUpperCase() : 'MANUFACTURING DIVISION';
    var dept = (idxDept > -1 && data[i][idxDept]) ? String(data[i][idxDept]).trim().toUpperCase() : 'GENERAL DEPT';
    var seksi = (idxSeksi > -1 && data[i][idxSeksi]) ? String(data[i][idxSeksi]).trim().toUpperCase() : '';

    var divFolder = getOrCreateSubfolder(yFolder, div);
    var deptFolder = getOrCreateSubfolder(divFolder, dept);
    if (seksi && seksi !== '-') {
      getOrCreateSubfolder(deptFolder, seksi);
    }
  }
  showAlertSafely('✅ Folder hierarki Google Drive berhasil disesuaikan dengan data karyawan.');
}

function testDriveAccess() {
  try {
    var rootFolder = DriveApp.getFolderById(CONFIG.DRIVE_FOLDER_ID);
    var ttdFolder = getOrCreateSubfolder(rootFolder, 'TANDA_TANGAN');
    var testFile = ttdFolder.createFile('test_' + new Date().getTime() + '.txt', 'OK');
    testFile.setTrashed(true);
    showAlertSafely('✅ Koneksi Google Drive Berhasil!\nFolder Induk: ' + rootFolder.getName());
  } catch (err) {
    showAlertSafely('❌ Gagal mengakses Google Drive: ' + err.toString());
  }
}

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
        try { file.setSharing(DriveApp.Access.ANYONE_WITH_LINK, DriveApp.Permission.VIEW); } catch (eShare) {}

        uSheet.getRange(r + 1, colBarcodeTtd + 1).setFormula('=IMAGE("' + qrApiUrl + '")');
        uSheet.getRange(r + 1, colSigLink + 1).setValue(file.getUrl());
        uSheet.setRowHeight(r + 1, 75);
        processedCount++;
      } catch (eErr) {
        Logger.log("Error generating barcode for " + nama + ": " + eErr);
      }
    }

    formatSheetColumns(uSheet, 'users');
    showAlertSafely('✅ Sinkronisasi Barcode TTD Users Selesai! ' + processedCount + ' QR Code diproses.');
  } catch (err) {
    showAlertSafely('Gagal sinkron barcode: ' + err.toString());
  }
}

function deleteDriveFilesByNrps(nrpsList, fileIdsList) {
  var deletedCount = 0;
  var rootFolder = DriveApp.getFolderById(CONFIG.DRIVE_FOLDER_ID);

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
      } catch (eFid) {}
    }
  }

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
          if (fName.indexOf('_' + tNrp + '_') > -1 || fName.indexOf('PK_' + tNrp + '_') > -1 || fName.indexOf(tNrp + '_') === 0) {
            file.setTrashed(true);
            deletedCount++;
            break;
          }
        }
      }
      var subfolders = folder.getFolders();
      while (subfolders.hasNext()) {
        var sub = subfolders.next();
        if (!sub.isTrashed()) searchAndTrashByNrp(sub);
      }
    }

    try { searchAndTrashByNrp(rootFolder); } catch (eSearch) {}
  }

  return deletedCount;
}

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
      if (!sub.isTrashed() && sub.getName() !== 'TANDA_TANGAN') {
        searchAndTrashAllPdfs(sub);
      }
    }
  }

  try { searchAndTrashAllPdfs(rootFolder); } catch (eWipe) {}
  return deletedCount;
}

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

function wipeAllDataAndPdfsMenu() {
  var ui = SpreadsheetApp.getUi();
  var response = ui.alert(
    '⚠️ KONFIRMASI PEMBERSIHAN TOTAL',
    'Apakah Anda yakin ingin MENGHAPUS SELURUH data penilaian di spreadsheet dan berkas PDF di Google Drive?',
    ui.ButtonSet.YES_NO
  );
  if (response !== ui.Button.YES) return;

  wipeAllAppraisalSheetsData();
  var trashedPdfs = wipeAllAppraisalPdfsInDrive();
  cleanUnusedSheets(true);
  showAlertSafely('✅ Pembersihan Total Berhasil! ' + trashedPdfs + ' berkas PDF dipindahkan ke Trash.');
}

function showDatabaseStats() {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var empSheet = ss.getSheetByName('karyawan');
  var docSheet = ss.getSheetByName('dokumen');
  var userSheet = ss.getSheetByName('users');
  var dinilaiSheet = ss.getSheetByName('Dokumen_Dinilai');
  var totalKaryawan = empSheet ? Math.max(0, empSheet.getLastRow() - 1) : 0;
  var totalDokumen = docSheet ? Math.max(0, docSheet.getLastRow() - 1) : 0;
  var totalUsers = userSheet ? Math.max(0, userSheet.getLastRow() - 1) : 0;
  var totalDinilai = dinilaiSheet ? Math.max(0, dinilaiSheet.getLastRow() - 1) : 0;

  showAlertSafely(
    '📊 STATISTIK BASIS DATA MUSASHI E-APPROVAL:\n\n' +
    '• Data Karyawan: ' + totalKaryawan + ' Orang\n' +
    '• Dokumen Penilaian: ' + totalDokumen + ' Dokumen\n' +
    '• Akun Pengguna: ' + totalUsers + ' Akun\n' +
    '• Dokumen Dinilai (Laporan): ' + totalDinilai + ' Baris'
  );
}

function jsonResponse(data, code) {
  return ContentService.createTextOutput(JSON.stringify(data))
    .setMimeType(ContentService.MimeType.JSON);
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
        time: new Date().toISOString()
      });
    }

    if (action === 'init_sheets' || action === 'setup_sheets') {
      setupDatabaseSheets();
      return jsonResponse({ status: 'success', message: 'Sheets initialized' });
    }

    if (action === 'pull_all' || action === 'export_database') {
      var result = {};
      var sheetNames = Object.keys(CONFIG_TABLES);
      for (var i = 0; i < sheetNames.length; i++) {
        var sName = sheetNames[i];
        var sh = ss.getSheetByName(sName);
        result[sName] = (sh && sh.getLastRow() > 1) ? sheetToObjects(sh) : [];
      }
      return jsonResponse({ status: 'success', data: result, timestamp: new Date().toISOString() });
    }

    if (action === 'get_table' || action === 'get_tables') {
      var tableName = p.table || 'karyawan';
      var sh = ss.getSheetByName(tableName);
      if (!sh) return jsonResponse({ status: 'error', message: 'Tabel ' + tableName + ' tidak ditemukan' }, 404);
      return jsonResponse({ status: 'success', table: tableName, data: sheetToObjects(sh) });
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
      try { postData = JSON.parse(e.postData.contents); } catch (ex) { postData = e.parameter || {}; }
    } else if (e && e.parameter) {
      postData = e.parameter;
    }

    var action = postData.action || (e && e.parameter ? e.parameter.action : 'push_all');
    var ss = SpreadsheetApp.getActiveSpreadsheet();

    if (action === 'init_sheets' || action === 'setup_sheets') {
      setupDatabaseSheets();
      return jsonResponse({ status: 'success', message: 'Sheets initialized' });
    }

    if (action === 'format_dates') {
      formatAllDatesInSheets(true);
      return jsonResponse({ status: 'success', message: 'Format tanggal berhasil dirapikan' });
    }

    // PEMBERSIHAN TOTAL (WIPE ALL APPRAISAL DATA & DRIVE PDFS)
    if (action === 'wipe_all_data' || action === 'wipe_appraisals' || action === 'reset_database') {
      wipeAllAppraisalSheetsData();
      var trashedPdfs = (postData.wipe_drive_pdfs !== false) ? wipeAllAppraisalPdfsInDrive() : 0;
      cleanUnusedSheets(true);
      return jsonResponse({ status: 'success', action: 'wipe_all_data', trashed_drive_pdfs: trashedPdfs });
    }

    // HAPUS DATA KARYAWAN & BERKAS DRIVE (BERDASARKAN DAFTAR NRP / FILE ID)
    if (action === 'delete_employee_data' || action === 'delete_employees') {
      var nrps = postData.nrps || (postData.nrp ? [postData.nrp] : []);
      var fileIds = postData.file_ids || (postData.file_id ? [postData.file_id] : []);
      var deletedRows = deleteEmployeeRowsFromSheets(nrps);
      var trashedDrivePdfs = deleteDriveFilesByNrps(nrps, fileIds);

      return jsonResponse({
        status: 'success',
        action: 'delete_employee_data',
        deleted_rows: deletedRows,
        trashed_drive_pdfs: trashedDrivePdfs
      });
    }

    // A. SIMPAN TANDA TANGAN DIGITAL KE GOOGLE DRIVE & UPDATE DATABASE
    if (action === 'save_signature' || action === 'upload_signature') {
      var sigBase64 = String(postData.signature_base64 || postData.image_base64 || postData.ttd_image || postData.base64 || '');
      if (!sigBase64) return jsonResponse({ status: 'error', message: 'Data gambar tanda tangan kosong.' }, 400);

      var nrp = String(postData.nrp || '0').trim();
      var nama = String(postData.nama || postData.nama_lengkap || 'USER').toUpperCase().replace(/[^A-Z0-9]/g, '_');
      var position = String(postData.position || postData.role || postData.jabatan || 'FOREMAN').toUpperCase().replace(/[^A-Z0-9]/g, '_');
      var departemen = String(postData.department || postData.departemen || 'DEPT').toUpperCase().replace(/[^A-Z0-9]/g, '_');
      var division = String(postData.division || postData.divisi || 'MANUFACTURING DIVISION').toUpperCase().trim();
      var filename = postData.filename || (nrp + '_' + nama + '_' + position + '.png');

      var rootFolder = DriveApp.getFolderById(CONFIG.DRIVE_FOLDER_ID);
      var mainTtdFolder = getOrCreateSubfolder(rootFolder, 'TANDA_TANGAN');
      var roleFolder = getOrCreateSubfolder(mainTtdFolder, position);

      if (sigBase64.indexOf(',') > -1) sigBase64 = sigBase64.split(',')[1];
      var blob = Utilities.newBlob(Utilities.base64Decode(sigBase64), 'image/png', filename);

      var existingFiles = roleFolder.getFilesByName(filename);
      while (existingFiles.hasNext()) {
        var oldFile = existingFiles.next();
        if (!oldFile.isTrashed()) oldFile.setTrashed(true);
      }

      var file = roleFolder.createFile(blob);
      try { file.setSharing(DriveApp.Access.ANYONE_WITH_LINK, DriveApp.Permission.VIEW); } catch (eS) {}

      var fileUrl = file.getUrl();
      var nowFormatted = Utilities.formatDate(new Date(), 'Asia/Jakarta', 'yyyy-MM-dd HH:mm:ss');

      // Update sheet 'Master Tanda Tangan'
      try {
        var ttdSheet = ss.getSheetByName('Master Tanda Tangan');
        if (!ttdSheet) {
          ttdSheet = ss.insertSheet('Master Tanda Tangan');
          ttdSheet.appendRow(CONFIG_TABLES['Master Tanda Tangan']);
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
        if (!rowUpdated) ttdSheet.appendRow([nrp, nama, position, departemen, division, fileUrl, file.getId(), nowFormatted]);
        formatSheetColumns(ttdSheet, 'Master Tanda Tangan');
      } catch (eMttd) {}

      // Update kolom signature_path_link di sheet 'users'
      try {
        var uSheet = ss.getSheetByName('users');
        if (uSheet && uSheet.getLastRow() > 1) {
          var uData = uSheet.getDataRange().getValues();
          var uHeaders = uData[0];
          var uNrpCol = -1, uSigCol = -1;
          for (var uh = 0; uh < uHeaders.length; uh++) {
            var colN = String(uHeaders[uh]).toLowerCase().trim();
            if (colN === 'nrp') uNrpCol = uh;
            if (colN === 'signature_path_link' || colN === 'signature_path' || colN === 'ttdbarcode') {
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
      } catch (eU) {}

      return jsonResponse({
        status: 'success',
        file_id: file.getId(),
        file_name: filename,
        file_url: fileUrl,
        timestamp: new Date().toISOString()
      });
    }

    // B. SIMPAN ARSIP PDF KE DRIVE
    if (action === 'save_pdf') {
      var pdfBase64 = postData.pdf_base64;
      if (!pdfBase64) return jsonResponse({ status: 'error', message: 'Data pdf_base64 kosong.' }, 400);

      var nrp = String(postData.nrp || '0').trim();
      var nama = String(postData.nama_lengkap || 'KARYAWAN').toUpperCase().replace(/[^A-Z0-9]/g, '_');
      var jabatan = String(postData.jabatan || 'OPERATOR').toUpperCase().replace(/[^A-Z0-9]/g, '_');
      var golongan = String(postData.golongan || 'KJ').toUpperCase().replace(/[^A-Z0-9]/g, '_');
      var departemen = String(postData.departemen || 'PRODUCTION 2 DEPT').toUpperCase().trim();
      var division = String(postData.division || postData.divisi || 'MANUFACTURING DIVISION').toUpperCase().trim();
      var seksi = String(postData.seksi || '').toUpperCase().trim();
      var tahun = String(postData.tahun || postData.periode_tahun || CONFIG.DEFAULT_YEAR).trim();

      var filename = 'PK_' + tahun + '_' + nrp + '_' + nama + '_' + jabatan + '_' + golongan + '.pdf';
      var rootFolder = DriveApp.getFolderById(CONFIG.DRIVE_FOLDER_ID);
      var yearFolder = getOrCreateSubfolder(rootFolder, 'TAHUN ' + tahun);
      var divFolder = getOrCreateSubfolder(yearFolder, division);
      var deptFolder = getOrCreateSubfolder(divFolder, departemen);
      var targetFolder = (seksi && seksi !== '-' && seksi !== 'SEMUA') ? getOrCreateSubfolder(deptFolder, seksi) : deptFolder;

      var blob = Utilities.newBlob(Utilities.base64Decode(pdfBase64), 'application/pdf', filename);
      var existingFiles = targetFolder.getFilesByName(filename);
      while (existingFiles.hasNext()) {
        var old = existingFiles.next();
        if (!old.isTrashed()) old.setTrashed(true);
      }

      var file = targetFolder.createFile(blob);
      try { file.setSharing(DriveApp.Access.ANYONE_WITH_LINK, DriveApp.Permission.VIEW); } catch (eS) {}

      return jsonResponse({
        status: 'success',
        file_id: file.getId(),
        file_name: filename,
        file_url: file.getUrl(),
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

    // D. HAPUS 1 BARIS KILAT (DELETE ROW BY NRP & CASCADE DELETE DRIVE PDFS)
    if (action === 'delete_row') {
      var targetTables = postData.tables || ['karyawan', 'dokumen', 'Dokumen_Dinilai', 'nilai', 'indikator', 'tanda_tangan', 'Master Tanda Tangan', 'users'];
      var keyColumn = (postData.key_column || 'nrp').toLowerCase();
      var keyValue = String(postData.key_value || '').trim().toLowerCase();
      var deletedStats = {};

      for (var t = 0; t < targetTables.length; t++) {
        var sName = targetTables[t];
        var sh = ss.getSheetByName(sName);
        if (!sh || sh.getLastRow() <= 1) continue;

        var data = sh.getDataRange().getValues();
        var headers = data[0].map(function(h) { return String(h).trim().toLowerCase(); });
        var keyIdx = headers.indexOf(keyColumn);
        if (keyIdx === -1) keyIdx = 0;

        var count = 0;
        for (var r = data.length - 1; r >= 1; r--) {
          if (String(data[r][keyIdx]).trim().toLowerCase() === keyValue) {
            sh.deleteRow(r + 1);
            count++;
          }
        }
        deletedStats[sName] = count;
      }

      var trashedDriveFiles = 0;
      if (keyColumn === 'nrp' && keyValue) {
        trashedDriveFiles = deleteDriveFilesByNrps([keyValue], []);
      }

      return jsonResponse({
        status: 'success',
        action: 'delete_row',
        key_value: keyValue,
        deleted_stats: deletedStats,
        trashed_drive_pdfs: trashedDriveFiles
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
        if (!sh) sh = ss.insertSheet(sName);

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
        updated_stats: updatedStats
      });
    }

    return jsonResponse({ status: 'unknown_action', action: action });
  } catch (err) {
    return jsonResponse({ status: 'error', error: err.toString() }, 500);
  }
}

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

function writeObjectsToSheet(ss, sheetName, items) {
  var targetName = (sheetName === 'Master Karyawan') ? 'karyawan' : sheetName;
  var sheet = ss.getSheetByName(targetName);
  if (!sheet) sheet = ss.insertSheet(targetName);

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

      var hNorm = hKey.toLowerCase().replace(/[^a-z]/g, '');
      if (hNorm === 'tglmasuk' || hNorm === 'startwork') {
        val = formatMusashiDateGAS(val);
      }

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
