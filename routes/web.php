<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HasilInputController;
use App\Http\Controllers\KaryawanController;
use App\Http\Controllers\PenilaianController;
use App\Http\Controllers\SummaryPKController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Superadmin\ConfigController;
use App\Http\Controllers\Superadmin\AccountController;

// Public & Authentication
Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::get('/quick-login/{id}', [AuthController::class, 'quickLogin'])->name('quick.login');
Route::match(['get', 'post'], '/logout', [AuthController::class, 'logout'])->name('logout');

// Public Document Verification via QR Code
Route::get('/verifikasi/{kode}', [PenilaianController::class, 'verifikasi'])->name('verifikasi');

// Direct User Digital Signature Stream (handles Data URI, Google Drive URLs, and local disk with instant image/png response)
Route::get('/user-signature/{user}', [ProfileController::class, 'showSignature'])->name('user.signature');

// Serve storage files (signatures, uploads) directly when storage symlink is not available
Route::get('/storage/{path}', function ($path) {
    if (!Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
        abort(404);
    }
    $mime = Illuminate\Support\Facades\Storage::disk('public')->mimeType($path) ?? 'image/png';
    return response(Illuminate\Support\Facades\Storage::disk('public')->get($path), 200, [
        'Content-Type' => $mime,
        'Cache-Control' => 'public, max-age=86400',
    ]);
})->where('path', '.*')->name('storage.local');

// Authenticated Application
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Profil Pengguna & Tanda Tangan Digital (TTD)
    Route::get('/profil', [ProfileController::class, 'index'])->name('profile.index');
    Route::post('/profil', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profil/signature', [ProfileController::class, 'deleteSignature'])->name('profile.deleteSignature');

    // Data Karyawan (Daftar Bawahan, Tambah Karyawan & Upload Sheet)
    Route::get('/karyawan', [KaryawanController::class, 'index'])->name('karyawan.index');
    Route::post('/karyawan', [KaryawanController::class, 'store'])->name('karyawan.store');
    Route::delete('/karyawan/{nrp}', [KaryawanController::class, 'destroy'])->name('karyawan.destroy');
    Route::post('/karyawan/{nrp}/delete', [KaryawanController::class, 'destroy'])->name('karyawan.delete');
    Route::get('/karyawan/import', function () {
        return redirect()->route('admin.karyawan');
    });
    Route::post('/karyawan/import', [KaryawanController::class, 'import'])->name('karyawan.import');
    Route::get('/karyawan/template', [KaryawanController::class, 'downloadTemplate'])->name('karyawan.template');
    Route::get('/karyawan/export', [KaryawanController::class, 'export'])->name('karyawan.export');

    // Penilaian Karyawan & Digital Signature
    Route::get('/penilaian/cari', [PenilaianController::class, 'cari'])->name('penilaian.cari');
    Route::get('/penilaian/riwayat', [PenilaianController::class, 'riwayat'])->name('penilaian.riwayat');
    Route::get('/penilaian/section', [PenilaianController::class, 'sectionIndex'])->name('penilaian.section');
    Route::get('/penilaian/sectionhead', [PenilaianController::class, 'sectionIndex'])->name('penilaian.sectionhead');
    Route::post('/penilaian/section/simpan-draft', [PenilaianController::class, 'sectionSaveDraft'])->name('penilaian.section.saveDraft');
    Route::post('/penilaian/section/submit-dept', [PenilaianController::class, 'sectionSubmitDept'])->name('penilaian.section.submitDept');
    
    // Department Head Pages (Mockup Screens 1, 2, 3 & Data Penilaian & Penyesuaian Kuota)
    Route::get('/penilaian/dept', [PenilaianController::class, 'deptIndex'])->name('penilaian.dept');
    Route::post('/penilaian/dept/simpan-draft', [PenilaianController::class, 'deptSaveDraft'])->name('penilaian.dept.saveDraft');
    Route::get('/penilaian/dept/penyesuaian', [PenilaianController::class, 'deptPenyesuaian'])->name('penilaian.dept.penyesuaian');
    Route::post('/penilaian/dept/penyesuaian/simpan', [PenilaianController::class, 'simpanDeptPenyesuaian'])->name('penilaian.dept.penyesuaian.simpan');
    Route::post('/penilaian/dept/penyesuaian/auto-adjust', [PenilaianController::class, 'autoAdjustDeptPenyesuaian'])->name('penilaian.dept.penyesuaian.autoAdjust');
    Route::post('/penilaian/dept/penyesuaian/reset', [PenilaianController::class, 'resetDeptPenyesuaian'])->name('penilaian.dept.penyesuaian.reset');

    // Division Head Pages (Data Penilaian & Penyesuaian Kuota)
    Route::get('/penilaian/div', [PenilaianController::class, 'divIndex'])->name('penilaian.div');
    Route::post('/penilaian/div/simpan-draft', [PenilaianController::class, 'divSaveDraft'])->name('penilaian.div.saveDraft');
    Route::get('/penilaian/div/penyesuaian', [PenilaianController::class, 'divPenyesuaian'])->name('penilaian.div.penyesuaian');
    Route::post('/penilaian/div/penyesuaian/simpan', [PenilaianController::class, 'simpanPenyesuaian'])->name('penilaian.div.penyesuaian.simpan');
    Route::post('/penilaian/div/penyesuaian/auto-adjust', [PenilaianController::class, 'autoAdjustPenyesuaian'])->name('penilaian.div.penyesuaian.autoAdjust');
    Route::post('/penilaian/div/penyesuaian/reset', [PenilaianController::class, 'resetPenyesuaian'])->name('penilaian.div.penyesuaian.reset');
    Route::get('/target-aturan', [PenilaianController::class, 'targetAturan'])->name('penilaian.targetAturan');
    Route::post('/target-aturan/simpan', [PenilaianController::class, 'simpanTargetAturan'])->name('penilaian.simpanTargetAturan');
    Route::post('/target-kuota/simpan', [DashboardController::class, 'simpanTargetKuota'])->name('target.simpan');
    Route::get('/laporan', [PenilaianController::class, 'laporan'])->name('laporan.index');
    Route::get('/laporan/export-excel', [PenilaianController::class, 'exportExcelLaporan'])->name('laporan.exportExcel');
    Route::get('/laporan/export-pdf', [PenilaianController::class, 'exportPdfLaporan'])->name('laporan.exportPdf');

    // Admin Dedicated Routes (Mockup Screens: Data Karyawan, Divisi & Departemen, Target & Kuota Nilai)
    Route::get('/admin/karyawan', [AdminController::class, 'karyawan'])->name('admin.karyawan');
    Route::get('/superadmin/karyawan', [AdminController::class, 'karyawan'])->name('superadmin.karyawan');
    Route::post('/admin/karyawan/bulk-delete', [KaryawanController::class, 'bulkDestroy'])->name('admin.karyawan.bulkDelete');
    Route::delete('/admin/karyawan/bulk-delete', [KaryawanController::class, 'bulkDestroy']);
    Route::post('/superadmin/karyawan/bulk-delete', [KaryawanController::class, 'bulkDestroy'])->name('superadmin.karyawan.bulkDelete');
    Route::delete('/superadmin/karyawan/bulk-delete', [KaryawanController::class, 'bulkDestroy']);
    Route::post('/admin/karyawan/{nrp}/update', [AdminController::class, 'updateKaryawan'])->name('admin.karyawan.update');
    Route::delete('/admin/karyawan/{nrp}', [KaryawanController::class, 'destroy'])->name('admin.karyawan.destroy');
    Route::post('/admin/karyawan/{nrp}/delete', [KaryawanController::class, 'destroy'])->name('admin.karyawan.delete');
    Route::post('/karyawan/{nrp}/update', [AdminController::class, 'updateKaryawan'])->name('karyawan.update');
    Route::get('/admin/divisi-departemen', [AdminController::class, 'divisiDepartemen'])->name('admin.divisiDepartemen');
    Route::post('/admin/divisi-departemen', [AdminController::class, 'simpanDepartemen'])->name('admin.simpanDepartemen');
    Route::get('/admin/target-kuota', [AdminController::class, 'targetKuota'])->name('admin.targetKuota');
    Route::post('/admin/target-kuota', [AdminController::class, 'simpanTargetKuota'])->name('admin.simpanTargetKuota');
    Route::post('/admin/target-kuota/kj', [AdminController::class, 'simpanTargetKuotaKj'])->name('admin.simpanTargetKuotaKj');
    Route::post('/admin/target-kuota/level', [AdminController::class, 'simpanTargetLevel'])->name('admin.simpanTargetLevel');
    Route::post('/admin/target-kuota/tambah-kj', [AdminController::class, 'tambahMasterKj'])->name('admin.tambahMasterKj');
    Route::post('/admin/target-kuota/hapus-kj', [AdminController::class, 'hapusMasterKj'])->name('admin.hapusMasterKj');

    Route::get('/superadmin/target-kuota', [AdminController::class, 'targetKuota'])->name('superadmin.targetKuota');
    Route::post('/superadmin/target-kuota', [AdminController::class, 'simpanTargetKuota'])->name('superadmin.simpanTargetKuota');
    Route::post('/superadmin/target-kuota/kj', [AdminController::class, 'simpanTargetKuotaKj'])->name('superadmin.simpanTargetKuotaKj');
    Route::post('/superadmin/target-kuota/level', [AdminController::class, 'simpanTargetLevel'])->name('superadmin.simpanTargetLevel');
    Route::post('/superadmin/target-kuota/tambah-kj', [AdminController::class, 'tambahMasterKj'])->name('superadmin.tambahMasterKj');
    Route::post('/superadmin/target-kuota/hapus-kj', [AdminController::class, 'hapusMasterKj'])->name('superadmin.hapusMasterKj');
    Route::get('/admin/pengaturan-form', [AdminController::class, 'pengaturanForm'])->name('admin.pengaturanForm');
    Route::post('/admin/pengaturan-form', [AdminController::class, 'simpanPengaturanForm'])->name('admin.simpanPengaturanForm');
    Route::get('/admin/lembar-penilaian', [AdminController::class, 'lembarPenilaian'])->name('admin.lembarPenilaian');
    Route::get('/superadmin/lembar-penilaian', [AdminController::class, 'lembarPenilaian'])->name('superadmin.lembarPenilaian');
    Route::get('/admin/revisi-penilaian', [AdminController::class, 'revisiPenilaian'])->name('admin.revisiPenilaian');
    Route::get('/superadmin/revisi-penilaian', [AdminController::class, 'revisiPenilaian'])->name('superadmin.revisiPenilaian');
    Route::post('/admin/revisi-penilaian/{id}', [AdminController::class, 'prosesRevisiPenilaian'])->name('admin.revisiPenilaian.proses');
    Route::post('/superadmin/revisi-penilaian/{id}', [AdminController::class, 'prosesRevisiPenilaian'])->name('superadmin.revisiPenilaian.proses');
    Route::get('/admin/history-penilaian', [AdminController::class, 'historyPenilaian'])->name('admin.historyPenilaian');
    Route::get('/superadmin/history-penilaian', [AdminController::class, 'historyPenilaian'])->name('superadmin.historyPenilaian');
    Route::post('/admin/history-penilaian/sync', [AdminController::class, 'syncAuditLog'])->name('admin.historyPenilaian.sync');
    Route::post('/superadmin/history-penilaian/sync', [AdminController::class, 'syncAuditLog'])->name('superadmin.historyPenilaian.sync');
    Route::delete('/admin/history-penilaian/{id}', [AdminController::class, 'destroyAuditLog'])->name('admin.historyPenilaian.destroy');
    Route::post('/admin/history-penilaian/{id}/delete', [AdminController::class, 'destroyAuditLog'])->name('admin.historyPenilaian.delete');
    Route::delete('/superadmin/history-penilaian/{id}', [AdminController::class, 'destroyAuditLog'])->name('superadmin.historyPenilaian.destroy');
    Route::post('/superadmin/history-penilaian/{id}/delete', [AdminController::class, 'destroyAuditLog'])->name('superadmin.historyPenilaian.delete');
    Route::post('/admin/history-penilaian/bulk-delete', [AdminController::class, 'bulkDestroyAuditLog'])->name('admin.historyPenilaian.bulkDelete');
    Route::delete('/admin/history-penilaian/bulk-delete', [AdminController::class, 'bulkDestroyAuditLog']);
    Route::post('/superadmin/history-penilaian/bulk-delete', [AdminController::class, 'bulkDestroyAuditLog'])->name('superadmin.historyPenilaian.bulkDelete');
    Route::delete('/superadmin/history-penilaian/bulk-delete', [AdminController::class, 'bulkDestroyAuditLog']);
    Route::post('/admin/history-penilaian/clear-all', [AdminController::class, 'clearAllAuditLog'])->name('admin.historyPenilaian.clearAll');
    Route::post('/superadmin/history-penilaian/clear-all', [AdminController::class, 'clearAllAuditLog'])->name('superadmin.historyPenilaian.clearAll');

    // User Management Routes (Mockup Screens: Kelola User / Manajemen Akun, Pengaturan)
    Route::get('/superadmin/users', [SuperAdminController::class, 'kelolaUser'])->name('superadmin.users');
    Route::get('/admin/manajemen-akun', [SuperAdminController::class, 'kelolaUser'])->name('admin.users');
    Route::get('/api/user-karyawan-lookup', [SuperAdminController::class, 'apiLookupKaryawan'])->name('api.user.karyawan.lookup');
    Route::post('/superadmin/users/sync', [SuperAdminController::class, 'syncFromKaryawan'])->name('superadmin.users.sync');
    Route::post('/admin/manajemen-akun/sync', [SuperAdminController::class, 'syncFromKaryawan'])->name('admin.users.sync');
    Route::post('/superadmin/users', [SuperAdminController::class, 'storeUser'])->name('superadmin.users.store');
    Route::post('/admin/manajemen-akun', [SuperAdminController::class, 'storeUser'])->name('admin.users.store');
    Route::post('/superadmin/users/{id}/update', [SuperAdminController::class, 'updateUser'])->name('superadmin.users.update');
    Route::post('/admin/manajemen-akun/{id}/update', [SuperAdminController::class, 'updateUser'])->name('admin.users.update');
    Route::post('/superadmin/users/{id}/toggle-status', [SuperAdminController::class, 'toggleUserStatus'])->name('superadmin.users.toggleStatus');
    Route::post('/admin/manajemen-akun/{id}/toggle-status', [SuperAdminController::class, 'toggleUserStatus'])->name('admin.users.toggleStatus');
    Route::post('/superadmin/users/{id}/reset-password', [SuperAdminController::class, 'resetUserPassword'])->name('superadmin.users.resetPassword');
    Route::post('/admin/manajemen-akun/{id}/reset-password', [SuperAdminController::class, 'resetUserPassword'])->name('admin.users.resetPassword');
    Route::post('/superadmin/users/{id}/delete', [SuperAdminController::class, 'deleteUser'])->name('superadmin.users.delete');
    Route::delete('/superadmin/users/{id}', [SuperAdminController::class, 'deleteUser'])->name('superadmin.users.destroy');
    Route::post('/admin/manajemen-akun/{id}/delete', [SuperAdminController::class, 'deleteUser'])->name('admin.users.delete');
    Route::get('/superadmin/pengaturan', [SuperAdminController::class, 'pengaturan'])->name('superadmin.pengaturan');
    Route::get('/admin/pengaturan', [SuperAdminController::class, 'pengaturan'])->name('admin.pengaturan');
    Route::post('/superadmin/pengaturan', [SuperAdminController::class, 'simpanPengaturan'])->name('superadmin.simpanPengaturan');
    Route::post('/admin/pengaturan', [SuperAdminController::class, 'simpanPengaturan'])->name('admin.simpanPengaturan');

    // Google Spreadsheet & Apps Script Integration Routes
    Route::post('/superadmin/google/links', [SuperAdminController::class, 'simpanGoogleLinks'])->name('superadmin.google.links');
    Route::post('/admin/google/links', [SuperAdminController::class, 'simpanGoogleLinks'])->name('admin.google.links');
    Route::post('/superadmin/google/sync', [SuperAdminController::class, 'syncFromGoogle'])->name('superadmin.google.sync');
    Route::post('/admin/google/sync', [SuperAdminController::class, 'syncFromGoogle'])->name('admin.google.sync');
    Route::post('/superadmin/google/push', [SuperAdminController::class, 'pushToGoogle'])->name('superadmin.google.push');
    Route::post('/admin/google/push', [SuperAdminController::class, 'pushToGoogle'])->name('admin.google.push');
    Route::post('/superadmin/google/test', [SuperAdminController::class, 'testGoogleConnection'])->name('superadmin.google.test');
    Route::post('/admin/google/test', [SuperAdminController::class, 'testGoogleConnection'])->name('admin.google.test');

    // Database Sync & Export Excel Routes
    Route::post('/superadmin/database/sync', [SuperAdminController::class, 'syncDatabase'])->name('superadmin.database.sync');
    Route::post('/admin/database/sync', [SuperAdminController::class, 'syncDatabase'])->name('admin.database.sync');
    Route::get('/superadmin/database/export-excel', [SuperAdminController::class, 'exportDatabaseExcel'])->name('superadmin.database.exportExcel');
    Route::get('/admin/database/export-excel', [SuperAdminController::class, 'exportDatabaseExcel'])->name('admin.database.exportExcel');
    Route::post('/superadmin/database/clear-cache', [SuperAdminController::class, 'clearSystemCache'])->name('superadmin.database.clearCache');
    Route::post('/admin/database/clear-cache', [SuperAdminController::class, 'clearSystemCache'])->name('admin.database.clearCache');

    // Role-specific appraisal form routes (Clean URLs & Access Control: /penilaian/{role}/dokumen/{id})
    Route::get('/penilaian/sechead/dokumen/{id}', [PenilaianController::class, 'showSecHead'])->name('penilaian.show.sechead');
    Route::get('/penilaian/sectionhead/dokumen/{id}', fn($id) => redirect()->route('penilaian.show.sechead', $id));
    Route::get('/penilaian/sectthead/dokumen/{id}', fn($id) => redirect()->route('penilaian.show.sechead', $id));
    Route::get('/penilaian/depthead/dokumen/{id}', [PenilaianController::class, 'showDeptHead'])->name('penilaian.show.depthead');
    Route::get('/penilaian/divhead/dokumen/{id}', [PenilaianController::class, 'showDivHead'])->name('penilaian.show.divhead');
    Route::get('/penilaian/foreman/dokumen/{id}', [PenilaianController::class, 'showForeman'])->name('penilaian.show.foreman');
    Route::get('/penilaian/admin/dokumen/{id}', [PenilaianController::class, 'showAdmin'])->name('penilaian.show.admin');
    Route::get('/dokumen/{id}/penilaian', [PenilaianController::class, 'showRedirect'])->name('penilaian.show');

    // Backward compatibility redirects for previous reversed structure (/dokumen/{role}/penilaian/{id})
    Route::get('/dokumen/sechead/penilaian/{id}', fn($id) => redirect()->route('penilaian.show.sechead', $id));
    Route::get('/dokumen/depthead/penilaian/{id}', fn($id) => redirect()->route('penilaian.show.depthead', $id));
    Route::get('/dokumen/divhead/penilaian/{id}', fn($id) => redirect()->route('penilaian.show.divhead', $id));
    Route::get('/dokumen/foreman/penilaian/{id}', fn($id) => redirect()->route('penilaian.show.foreman', $id));
    Route::get('/dokumen/admin/penilaian/{id}', fn($id) => redirect()->route('penilaian.show.admin', $id));
    Route::get('/dokumen/{id}/pdf', [PenilaianController::class, 'downloadPdf'])->name('penilaian.downloadPdf');
    Route::get('/penilaian/nrp/{nrp}', [PenilaianController::class, 'byNrp'])->name('penilaian.byNrp');
    Route::post('/penilaian/buka', [PenilaianController::class, 'bukaByNrp'])->name('penilaian.buka');
    Route::get('/api/karyawan-lookup', [PenilaianController::class, 'apiLookupKaryawan'])->name('api.karyawan.lookup');
    Route::post('/dokumen/{id}/simpan-draft', [PenilaianController::class, 'saveDraft'])->name('penilaian.saveDraft');
    Route::post('/dokumen/{id}/simpan-indikator', [PenilaianController::class, 'simpanIndikator'])->name('penilaian.simpanIndikator');
    Route::post('/dokumen/{id}/ttd', [PenilaianController::class, 'sign'])->name('penilaian.sign');
    Route::post('/dokumen/{id}/revisi', [PenilaianController::class, 'revisi'])->name('penilaian.revisi');
    Route::post('/dokumen/{id}/buka-revisi', [PenilaianController::class, 'bukaRevisi'])->name('penilaian.bukaRevisi');

    // Summary PK & Docking Kuota (Sesuai Excel Summary PK)
    Route::get('/summary-pk', [SummaryPKController::class, 'index'])->name('summary.index');
    Route::post('/summary-pk/update-docking', [SummaryPKController::class, 'updateDocking'])->name('summary.updateDocking');
    Route::post('/summary-pk/update-kuota', [SummaryPKController::class, 'updateKuota'])->name('summary.updateKuota');
    Route::get('/summary-pk/export', [SummaryPKController::class, 'export'])->name('summary.export');

    // Hasil Penilaian (Database Hasil Penilaian PK)
    Route::get('/hasil-penilaian', [HasilInputController::class, 'index'])->name('hasil-penilaian.index');
    Route::get('/hasil-penilaian/export', [HasilInputController::class, 'export'])->name('hasil-penilaian.export');

    // Database Hasil Input (Alias backward compatibility)
    Route::get('/hasil-input', [HasilInputController::class, 'index'])->name('hasil-input.index');
    Route::get('/hasil-input/export', [HasilInputController::class, 'export'])->name('hasil-input.export');

    // Superadmin only routes
    Route::middleware('can:superadmin')->prefix('superadmin')->name('superadmin.')->group(function () {
        // Configuration: Spreadsheet & Drive links, Service Account
        Route::get('/config', [ConfigController::class, 'index'])->name('config');
        Route::post('/config/links', [ConfigController::class, 'updateLinks'])->name('config.links');
        Route::post('/config/credentials', [ConfigController::class, 'updateCredentials'])->name('config.credentials');
        Route::post('/config/test', [ConfigController::class, 'testConnection'])->name('config.test');
        Route::post('/config/setup-sheets', [ConfigController::class, 'runSetup'])->name('config.setup');

        // Accounts Management
        Route::get('/accounts', [AccountController::class, 'index'])->name('accounts');
        Route::post('/accounts', [AccountController::class, 'store'])->name('accounts.store');
        Route::post('/accounts/{id}/toggle', [AccountController::class, 'toggleActive'])->name('accounts.toggle');
        Route::post('/accounts/{id}/reset-password', [AccountController::class, 'resetPassword'])->name('accounts.reset-password');
    });
});

// Persistent session keepalive endpoint (ultra-lightweight heartbeat to keep session active)
Route::get('/session-keepalive', function () {
    return response()->json([
        'status' => 'ok',
        'authenticated' => \Illuminate\Support\Facades\Auth::check(),
        'user_id' => \Illuminate\Support\Facades\Auth::id(),
        'timestamp' => now()->toIso8601String()
    ]);
})->middleware('web')->name('session.keepalive');
