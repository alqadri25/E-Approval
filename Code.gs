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
    .addItem('⚡ Inisialisasi Seluruh Sheet Database', 'setupDatabaseSheets')
    .addItem('📁 Buat Subfolder Drive PDF Otomatis', 'setupDriveFolders')
    .addItem('📱 Sinkron Barcode TTD Users ke Drive & Tampil di Sheet', 'syncUserBarcodesToDriveAndSheet')
    .addItem('📊 Format & Rapikan Sheet Dokumen_Dinilai', 'setupDokumenDinilaiSheet')
    .addItem('✍️ Uji & Buat Folder Tanda Tangan Drive', 'testDriveAccess')
    .addSeparator()
    .addItem('📈 Cek Status & Statistik Data', 'showDatabaseStats')
    .addToUi();
}

/**
 * 2. INISIALISASI SELURUH SHEET DATABASE (TABEL RESMI)
 */
var CONFIG_TABLES = {
  'Master Karyawan': [
    'nrp', 'nama_lengkap', 'golongan', 'sub_gol', 'kj', 'jabatan', 'tgl_masuk',
    'seksi', 'departemen', 'level_form', 'nrp_foreman', 'nrp_section_head',
    'nrp_dept_head', 'nrp_div_head', 'code_bagian', 'nama_bagian', 'nama_sect',
    'line_category', 'division'
  ],
  'karyawan': [
    'nrp', 'nama_lengkap', 'golongan', 'sub_gol', 'kj', 'jabatan', 'tgl_masuk',
    'seksi', 'departemen', 'level_form', 'nrp_foreman', 'nrp_section_head',
    'nrp_dept_head', 'nrp_div_head', 'code_bagian', 'nama_bagian', 'nama_sect',
    'line_category', 'division'
  ],
  'periode': [
    'id', 'tahun', 'tgl_mulai', 'tgl_selesai', 'status'
  ],
  'dokumen': [
    'id', 'periode_id', 'nrp', 'kode_form', 'status', 'total_nilai',
    'grade_original', 'docking_final', 'drive_file_id', 'drive_url',
    'hash_dokumen', 'kode_verifikasi', 'created_at'
  ],
  'users': [
    'id', 'nama', 'email', 'role', 'nrp', 'departemen', 'aktif', 'barcodettd', 'ttdbarcode', 'created_at'
  ],
  'nilai': [
    'dokumen_id', 'level', 'aspek_kode', 'skor', 'catatan'
  ],
  'indikator': [
    'dokumen_id', 'pilihan_potensi', 'catatan_potensi', 'kelebihan', 'kekurangan'
  ],
  'tanda_tangan': [
    'dokumen_id', 'level', 'user_id', 'tgl_ttd', 'ip', 'user_agent',
    'hash_snapshot', 'ttd_image', 'created_at'
  ],
  'kuota_departemen': [
    'id', 'departemen', 'grade_s_max', 'grade_a_max', 'grade_b_max', 'grade_c_max', 'grade_k_max'
  ],
  'Dokumen_Dinilai': [
    'NRP', 'NAMA', 'NAMA BAGIAN', 'SECTION', 'DEPARTMENT', 'DIVISION', 'POSITION', 'START WORK', 'KJ', 'GOLONGAN', 'Hasil Kerja', 'Proses Kerja', 'Sikap Kerja', 'Man Management', 'Total Nilai', 'NILAI PK Foreman (Jika Ada)', 'NILAI PK SECT', 'NILAI PK DEPT', 'NILAI PK DIV', 'NILAI PK FINAL'
  ],
  'master_aspek': [
    'kode_form', 'grup', 'aspek_kode', 'nama', 'bobot_grup', 'urutan'
  ],
  'master_kriteria': [
    'aspek_kode', 'skor', 'deskripsi'
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
    var headerRange = sheet.getRange(1, 1, 1, headers.length);
    headerRange.setBackground('#92302E')
               .setFontColor('#FFFFFF')
               .setFontWeight('bold')
               .setFontFamily('Arial')
               .setFontSize(10)
               .setHorizontalAlignment('center');
    sheet.setFrozenRows(1);
  }

  var defaultSheet = ss.getSheetByName('Sheet1');
  if (defaultSheet && ss.getSheets().length > 1) {
    try { ss.deleteSheet(defaultSheet); } catch (e) {}
  }

  showAlertSafely('✅ Sukses! Seluruh tabel database resmi (termasuk kuota_departemen & Dokumen_Dinilai) telah dibuat dan diformat rapi.');
}

/**
 * 2B. FORMAT & RAPIKAN SHEET DOKUMEN_DINILAI
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
  var colCount = Math.max(headers.length, sheet.getLastColumn());
  var hRange = sheet.getRange(1, 1, 1, colCount);
  hRange.setBackground('#92302E')
        .setFontColor('#FFFFFF')
        .setFontWeight('bold')
        .setFontFamily('Arial')
        .setFontSize(10)
        .setHorizontalAlignment('center');
  sheet.setFrozenRows(1);
  showAlertSafely('✅ Sheet Dokumen_Dinilai berhasil disiapkan dan diformat rapi.');
}

/**
 * 3. STRUKTUR FOLDER GOOGLE DRIVE DINAMIS DARI MASTER KARYAWAN
 * Otomatis membaca Hirarki Tanpa Dobel: DIVISION -> DEPARTMENT -> SECTION -> (dan POSITION TTD)
 * Otomatis mendeteksi tahun berjalan saat ini (misal 2026), tahun depan (2027), dan seluruh tahun di tabel periode!
 */
function setupDriveFolders() {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var empSheet = ss.getSheetByName('Master Karyawan') || ss.getSheetByName('karyawan');
  var rootFolder = DriveApp.getFolderById(CONFIG.DRIVE_FOLDER_ID);

  // 1. Kumpulkan daftar tahun target secara otomatis (Tahun ini + Tahun depan + Sheet Periode)
  var targetYears = {};
  var currentYearNum = new Date().getFullYear();
  targetYears[String(currentYearNum)] = true;       // Tahun berjalan saat ini (misal: 2026)
  targetYears[String(currentYearNum + 1)] = true;   // Otomatis siapkan tahun depan (misal: 2027)

  // Baca juga tahun yang terdaftar di sheet 'periode' jika ada
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

  // Buat folder induk tanda tangan
  var mainTtdFolder = getOrCreateSubfolder(rootFolder, 'TANDA_TANGAN');

  var divCount = 0;
  var deptCount = 0;
  var seksiCount = 0;
  var posCount = 0;

  var uniqueRoles = {};
  var standardRoles = ['FOREMAN', 'SECTION_HEAD', 'DEPT_HEAD', 'DIV_HEAD', 'ADMIN'];
  for (var r = 0; r < standardRoles.length; r++) {
    uniqueRoles[standardRoles[r]] = true;
  }

  // Siapkan struktur hierarki unik dari data karyawan
  var hierarchy = {}; // { division: { dept: { seksi: true } } }

  if (empSheet && empSheet.getLastRow() > 1) {
    var data = empSheet.getDataRange().getValues();
    var headers = data[0];

    // Temukan index kolom secara otomatis
    var idxDiv = -1;
    var idxDept = -1;
    var idxSeksi = -1;
    var idxJabatan = -1;

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

    for (var i = 1; i < data.length; i++) {
      var row = data[i];
      var div = (idxDiv > -1 && row[idxDiv]) ? String(row[idxDiv]).trim().toUpperCase() : '';
      var dept = (idxDept > -1 && row[idxDept]) ? String(row[idxDept]).trim().toUpperCase() : '';
      var seksi = (idxSeksi > -1 && row[idxSeksi]) ? String(row[idxSeksi]).trim().toUpperCase() : '';
      var jab = (idxJabatan > -1 && row[idxJabatan]) ? String(row[idxJabatan]).trim().toUpperCase() : '';

      if (!div && !dept && !seksi) continue;

      if (!div) div = 'MANUFACTURING DIVISION';
      if (!dept) dept = 'GENERAL DEPT';

      if (!hierarchy[div]) {
        hierarchy[div] = {};
        divCount++;
      }
      if (!hierarchy[div][dept]) {
        hierarchy[div][dept] = {};
        deptCount++;
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
  } else {
    // Fallback jika belum ada data baris di sheet karyawan
    var defaultDepts = [
      'PRODUCTION 1 DEPT',
      'PRODUCTION 2 DEPT',
      'QA QC DEPT',
      'ENGINEERING DEPT',
      'HR GA FINANCE DEPT'
    ];
    hierarchy['MANUFACTURING DIVISION'] = {};
    for (var k = 0; k < defaultDepts.length; k++) {
      hierarchy['MANUFACTURING DIVISION'][defaultDepts[k]] = {};
    }
  }

  // Bangun hirarki folder di Google Drive untuk setiap tahun target (2026, 2027, dst)
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

  // Buat subfolder tanda tangan berdasarkan posisi / role unik
  for (var posKey in uniqueRoles) {
    getOrCreateSubfolder(mainTtdFolder, posKey);
    posCount++;
  }

  showAlertSafely(
    '✅ Sukses! Struktur folder Google Drive otomatis dibuat untuk tahun ' + yearsList.join(', ') + ':\n\n' +
    '📁 Folder Arsip: ' + yearsList.map(function(yr) { return 'TAHUN ' + yr; }).join(', ') + '\n' +
    '   ├── Divisi: ' + divCount + ' Folder\n' +
    '   ├── Departemen: ' + deptCount + ' Folder\n' +
    '   └── Seksi: ' + seksiCount + ' Folder\n\n' +
    '📁 TANDA_TANGAN: ' + posCount + ' Posisi/Role\n\n' +
    'Sistem otomatis menyesuaikan tahun kalender berjalan (2026, 2027, 2028, dst) tanpa perlu ubah script manual.'
  );
}

/**
 * 4. UJI AKSES DRIVE & INISIALISASI FOLDER TANDA TANGAN
 * (Jalankan fungsi ini di Apps Script editor untuk menyetujui izin DriveApp)
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
 * - Membuat file gambar QR Code resmi untuk setiap user di sheet users
 * - Menyimpan file di Google Drive: TANDA_TANGAN / [ROLE] / [NRP]_[NAMA].png
 * - Di sheet users:
 *     - Kolom 'barcodettd': rumus =IMAGE("url") agar gambar barcode LANGSUNG MUNCUL di dalam sel!
 *     - Kolom 'ttdbarcode': link Google Drive langsung ke file gambar
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

    // Cari index kolom secara fleksibel
    var colNrp = -1, colNama = -1, colRole = -1, colBarcodeTtd = -1, colTtdBarcode = -1;
    for (var h = 0; h < headers.length; h++) {
      var hName = String(headers[h] || '').trim().toLowerCase();
      if (hName === 'nrp' || hName === 'nip' || (hName === 'id' && colNrp === -1)) colNrp = h;
      if (hName === 'nama' || hName === 'nama_lengkap' || hName === 'name') colNama = h;
      if (hName === 'role' || hName === 'jabatan' || hName === 'position') colRole = h;
      if (hName === 'barcodettd') colBarcodeTtd = h;
      if (hName === 'ttdbarcode') colTtdBarcode = h;
    }

    // Tambahkan kolom jika belum ada
    if (colBarcodeTtd === -1) {
      colBarcodeTtd = headers.length;
      uSheet.getRange(1, colBarcodeTtd + 1).setValue('barcodettd');
      headers.push('barcodettd');
    }
    if (colTtdBarcode === -1) {
      colTtdBarcode = headers.length;
      uSheet.getRange(1, colTtdBarcode + 1).setValue('ttdbarcode');
      headers.push('ttdbarcode');
    }

    // Format header
    var hRange = uSheet.getRange(1, 1, 1, headers.length);
    hRange.setBackground('#92302E').setFontColor('#FFFFFF').setFontWeight('bold').setFontFamily('Arial').setFontSize(10).setHorizontalAlignment('center');
    uSheet.setFrozenRows(1);

    var processedCount = 0;

    for (var r = 1; r < uData.length; r++) {
      var nrp = (colNrp > -1) ? String(uData[r][colNrp] || '').trim() : '';
      var nama = (colNama > -1) ? String(uData[r][colNama] || '').trim().toUpperCase() : '';
      var role = (colRole > -1) ? String(uData[r][colRole] || 'FOREMAN').trim().toUpperCase().replace(/[^A-Z0-9]/g, '_') : 'FOREMAN';

      if (!nrp || !nama) continue;

      var cleanNama = nama.replace(/[^A-Z0-9]/g, '_');
      var filename = nrp + '_' + cleanNama + '_' + role + '.png';

      // Folder tujuan di Drive: TANDA_TANGAN / [ROLE]
      var roleFolder = getOrCreateSubfolder(mainTtdFolder, role);

      // Buat QR Code melalui API resmi QR
      var payload = "PT MUSASHI AUTO PARTS INDONESIA\nE-APPROVAL DIGITAL SIGNATURE\nNRP: " + nrp + "\nNAMA: " + nama + "\nROLE: " + role + "\nSTATUS: VERIFIED";
      var qrApiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=8&data=" + encodeURIComponent(payload);

      try {
        var blob = UrlFetchApp.fetch(qrApiUrl).getBlob().setName(filename);

        // Bersihkan duplikat lama
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

        // 1. Tampilkan gambar Barcode di sheet users (menggunakan setFormula agar langsung render visual di spreadsheet)
        uSheet.getRange(r + 1, colBarcodeTtd + 1).setFormula('=IMAGE("' + qrApiUrl + '")');
        // 2. Tampilkan URL file Google Drive di kolom ttdbarcode
        uSheet.getRange(r + 1, colTtdBarcode + 1).setValue(driveViewUrl);

        // Atur tinggi baris agar gambar QR terlihat besar, presisi & rapi
        uSheet.setRowHeight(r + 1, 75);
        uSheet.getRange(r + 1, colBarcodeTtd + 1).setHorizontalAlignment('center').setVerticalAlignment('middle');
        processedCount++;
      } catch (eErr) {
        Logger.log("Error generating barcode for " + nama + ": " + eErr);
      }
    }

    // Atur lebar kolom
    uSheet.setColumnWidth(colBarcodeTtd + 1, 95);
    uSheet.setColumnWidth(colTtdBarcode + 1, 280);

    showAlertSafely(
      '✅ Sinkronisasi Barcode TTD Users Selesai!\n\n' +
      '• ' + processedCount + ' Barcode QR berhasil dibuat.\n' +
      '• File gambar PNG tersimpan rapi di Google Drive (Folder TANDA_TANGAN / [ROLE]).\n' +
      '• Barcode tampil langsung secara visual di kolom barcodettd sheet users.\n' +
      '• Link Drive tersimpan di kolom ttdbarcode.'
    );
  } catch (err) {
    showAlertSafely('Gagal sinkron barcode: ' + err.toString());
  }
}

/**
 * 5. RINGKASAN STATUS DATA DATABASE
 */
function showDatabaseStats() {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var empSheet = ss.getSheetByName('Master Karyawan') || ss.getSheetByName('karyawan');
  var docSheet = ss.getSheetByName('dokumen');
  var userSheet = ss.getSheetByName('users');
  var dinilaiSheet = ss.getSheetByName('Dokumen_Dinilai');
  var ttdSheet = ss.getSheetByName('Master Tanda Tangan');
  var totalKaryawan = empSheet ? Math.max(0, empSheet.getLastRow() - 1) : 0;
  var totalDokumen = docSheet ? Math.max(0, docSheet.getLastRow() - 1) : 0;
  var totalUsers = userSheet ? Math.max(0, userSheet.getLastRow() - 1) : 0;
  var totalDinilai = dinilaiSheet ? Math.max(0, dinilaiSheet.getLastRow() - 1) : 0;
  var totalTtd = ttdSheet ? Math.max(0, ttdSheet.getLastRow() - 1) : 0;

  var msg = '📊 STATUS DATABASE MUSASHI E-APPROVAL:\n\n' +
            '• Total Karyawan: ' + totalKaryawan + ' orang\n' +
            '• Total Dokumen Penilaian: ' + totalDokumen + ' berkas\n' +
            '• Total Pengguna Sistem: ' + totalUsers + ' user\n' +
            '• Dokumen Dinilai (Rekap Laporan): ' + totalDinilai + ' baris\n' +
            '• Arsip Tanda Tangan: ' + totalTtd + ' berkas\n' +
            '• Tahun Default: ' + CONFIG.DEFAULT_YEAR + '\n\n' +
            'Database dan Drive siap digunakan.';
  showAlertSafely(msg);
}

/**
 * =========================================================================
 * WEB APP ENDPOINT HTTP: GET & POST
 * =========================================================================
 */
function doGet(e) {
  try {
    var p = (e && e.parameter) ? e.parameter : {};
    var action = p.action || 'ping';

    if (action === 'ping') {
      return jsonResponse({
        status: 'online',
        app: 'Musashi E-Approval System Apps Script',
        timestamp: new Date().toISOString(),
        spreadsheet_id: CONFIG.SPREADSHEET_ID,
        drive_folder_id: CONFIG.DRIVE_FOLDER_ID,
        default_year: CONFIG.DEFAULT_YEAR
      });
    }

    if (action === 'get_tables') {
      var ss = SpreadsheetApp.getActiveSpreadsheet();
      var tableName = p.table;
      if (tableName) {
        var sh = ss.getSheetByName(tableName);
        if (!sh) return jsonResponse({ status: 'error', message: 'Tabel tidak ditemukan: ' + tableName }, 404);
        return jsonResponse({ status: 'success', table: tableName, data: sheetToObjects(sh) });
      }

      var allData = {};
      var sheets = ss.getSheets();
      for (var s = 0; s < sheets.length; s++) {
        var sName = sheets[s].getName();
        allData[sName] = sheetToObjects(sheets[s]);
      }
      return jsonResponse({ status: 'success', data: allData });
    }

    return jsonResponse({ status: 'unknown_action', action: action });
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

    var action = postData.action || 'push_all';
    var ss = SpreadsheetApp.getActiveSpreadsheet();

    // A. SIMPAN FILE TANDA TANGAN DIGITAL DARI FRONTEND
    if (action === 'save_signature') {
      var imageBase64 = postData.image_base64 || postData.ttd_image;
      if (!imageBase64) {
        return jsonResponse({ status: 'error', message: 'Data image_base64 kosong.' }, 400);
      }

      var nrp = String(postData.nrp || '0').trim();
      var nama = String(postData.nama || 'KARYAWAN').toUpperCase().replace(/[^A-Z0-9]/g, '_');
      var position = String(postData.position || postData.role || 'FOREMAN').toUpperCase().replace(/[^A-Z0-9]/g, '_');
      var departemen = String(postData.department || postData.departemen || 'DEPT').toUpperCase().replace(/[^A-Z0-9]/g, '_');
      var division = String(postData.division || 'DIV').toUpperCase().replace(/[^A-Z0-9]/g, '_');

      var cleanBase64 = imageBase64.replace(/^data:image\/\w+;base64,/, '');
      var decodedBytes = Utilities.base64Decode(cleanBase64);
      var filename = nrp + '_' + nama + '_' + position + '.png';

      var rootFolder = DriveApp.getFolderById(CONFIG.DRIVE_FOLDER_ID);
      var mainTtdFolder = getOrCreateSubfolder(rootFolder, 'TANDA_TANGAN');
      var roleFolder = getOrCreateSubfolder(mainTtdFolder, position);

      var existingFiles = roleFolder.getFilesByName(filename);
      while (existingFiles.hasNext()) {
        var oldFile = existingFiles.next();
        if (!oldFile.isTrashed()) oldFile.setTrashed(true);
      }

      var blob = Utilities.newBlob(decodedBytes, 'image/png', filename);
      var file = roleFolder.createFile(blob);
      try {
        file.setSharing(DriveApp.Access.ANYONE_WITH_LINK, DriveApp.Permission.VIEW);
      } catch (eShare) {}

      var fileUrl = file.getUrl();
      var folderPath = 'TANDA_TANGAN / ' + position;

      // Update sheet Master Tanda Tangan
      try {
        var ttdSheet = ss.getSheetByName('Master Tanda Tangan');
        if (!ttdSheet) {
          ttdSheet = ss.insertSheet('Master Tanda Tangan');
          var ttdHeaders = CONFIG_TABLES['Master Tanda Tangan'];
          ttdSheet.appendRow(ttdHeaders);
          var hr = ttdSheet.getRange(1, 1, 1, ttdHeaders.length);
          hr.setBackground('#92302E').setFontColor('#FFFFFF').setFontWeight('bold');
          ttdSheet.setFrozenRows(1);
        }

        var tData = ttdSheet.getDataRange().getValues();
        var rowUpdated = false;
        var nowFormatted = Utilities.formatDate(new Date(), 'Asia/Jakarta', 'yyyy-MM-dd HH:mm:ss');

        for (var tr = 1; tr < tData.length; tr++) {
          if (String(tData[tr][0]).trim() === nrp) {
            ttdSheet.getRange(tr + 1, 1, 1, 8).setValues([[nrp, nama, position, departemen, division, fileUrl, file.getId(), nowFormatted]]);
            rowUpdated = true;
            break;
          }
        }
        if (!rowUpdated) {
          ttdSheet.appendRow([nrp, nama, position, departemen, division, fileUrl, file.getId(), nowFormatted]);
        }
      } catch (eSheetTtd) {
        Logger.log('Gagal update sheet Master Tanda Tangan: ' + eSheetTtd);
      }

      // Sinkronkan signature_path & ttdbarcode di sheet users
      try {
        var uSheet = ss.getSheetByName('users');
        if (uSheet && uSheet.getLastRow() > 1) {
          var uData = uSheet.getDataRange().getValues();
          var uHeaders = uData[0];
          var uNrpCol = -1, uSigCol = -1, uTtdBarcodeCol = -1;
          for (var uh = 0; uh < uHeaders.length; uh++) {
            var colName = String(uHeaders[uh]).toLowerCase().trim();
            if (colName === 'nrp') uNrpCol = uh;
            if (colName === 'signature_path') uSigCol = uh;
            if (colName === 'ttdbarcode') uTtdBarcodeCol = uh;
          }
          if (uNrpCol > -1) {
            for (var ur = 1; ur < uData.length; ur++) {
              if (String(uData[ur][uNrpCol]).trim() === nrp) {
                if (uSigCol > -1) uSheet.getRange(ur + 1, uSigCol + 1).setValue(fileUrl);
                if (uTtdBarcodeCol > -1) uSheet.getRange(ur + 1, uTtdBarcodeCol + 1).setValue(fileUrl);
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

    // B. SIMPAN ARSIP PDF KE DRIVE
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
        var empSheet = ss.getSheetByName('Master Karyawan') || ss.getSheetByName('karyawan');
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
    if (action === 'delete_row') {
      var targetTables = postData.tables || ['Master Karyawan', 'karyawan', 'dokumen'];
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

      return jsonResponse({
        status: 'success',
        action: 'delete_row',
        key_value: keyValue,
        deleted_stats: deletedStats,
        timestamp: new Date().toISOString()
      });
    }

    // E. UPDATE ATAU TAMBAH 1 BARIS KILAT (UPSERT ROW BY NRP)
    if (action === 'upsert_row') {
      var targetTables = postData.tables || ['Master Karyawan', 'karyawan'];
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
          headers = CONFIG_TABLES[sName] || CONFIG_TABLES['karyawan'] || Object.keys(rowData);
          sh.appendRow(headers);
          var hr = sh.getRange(1, 1, 1, headers.length);
          hr.setBackground('#92302E').setFontColor('#FFFFFF').setFontWeight('bold').setFontFamily('Arial').setFontSize(10).setHorizontalAlignment('center');
          sh.setFrozenRows(1);
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
  var sheet = ss.getSheetByName(sheetName);
  if (!sheet) {
    sheet = ss.insertSheet(sheetName);
  }

  if (!items || items.length === 0) {
    var stdHeaders = CONFIG_TABLES[sheetName] || (sheetName === 'Master Karyawan' ? CONFIG_TABLES['karyawan'] : null);
    if (sheet.getLastRow() === 0 && stdHeaders) {
      sheet.appendRow(stdHeaders);
      var hr = sheet.getRange(1, 1, 1, stdHeaders.length);
      hr.setBackground('#92302E')
        .setFontColor('#FFFFFF')
        .setFontWeight('bold')
        .setFontFamily('Arial')
        .setFontSize(10)
        .setHorizontalAlignment('center');
      sheet.setFrozenRows(1);
    }
    return;
  }

  var headers = Object.keys(items[0]);
  sheet.clear();
  sheet.appendRow(headers);
  var rows = [];
  for (var i = 0; i < items.length; i++) {
    var row = [];
    for (var h = 0; h < headers.length; h++) {
      var hKey = headers[h];
      var val = (items[i][hKey] !== undefined) ? items[i][hKey] : '';

      // Khusus sheet users: jika barcodettd belum berisi rumus =IMAGE, buat otomatis rumus visualnya
      if (sheetName === 'users' && hKey.toLowerCase() === 'barcodettd' && (!val || !String(val).startsWith('='))) {
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

    // Atur tinggi baris jika sheet users agar QR tampil besar & rapi
    if (sheetName === 'users') {
      for (var rIdx = 0; rIdx < rows.length; rIdx++) {
        sheet.setRowHeight(rIdx + 2, 75);
      }
    }
  }
  var headerRange = sheet.getRange(1, 1, 1, headers.length);
  headerRange.setBackground('#92302E')
             .setFontColor('#FFFFFF')
             .setFontWeight('bold')
             .setFontFamily('Arial')
             .setFontSize(10)
             .setHorizontalAlignment('center');
  sheet.setFrozenRows(1);
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
