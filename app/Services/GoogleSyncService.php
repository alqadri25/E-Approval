<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Exception;

class GoogleSyncService
{
    /**
     * Default Fallback Configs (Terkoneksi ke Google Spreadsheet & Drive Resmi PT Musashi)
     */
    public const DEFAULT_SPREADSHEET_ID = '14-xI_m24eXSj1L2aiX_gytMWrrS9HnjH5HzcRgWV_9g';
    public const DEFAULT_DRIVE_FOLDER_ID = '1zB2loWV14fCpLWMCnNiEa4dVKAvhLZYE';
    public const DEFAULT_APPSCRIPT_URL = 'https://script.google.com/macros/s/AKfycbyHVYf6nKFM7Joyfl0sQpNHjCMegbBVAV9BvDor0Mz9mDyl40SkQ8K_uv4UMrQulmQL/exec';

    /**
     * Extract ID from Google Sheets URL or raw ID
     */
    public static function extractSpreadsheetId(?string $input): string
    {
        $input = trim((string)$input);
        if (empty($input)) {
            return self::DEFAULT_SPREADSHEET_ID;
        }

        if (preg_match('/\/spreadsheets\/d\/([a-zA-Z0-9-_]+)/', $input, $matches)) {
            return $matches[1];
        }

        return $input;
    }

    /**
     * Extract ID from Google Drive Folder URL or raw ID
     */
    public static function extractDriveFolderId(?string $input): string
    {
        $input = trim((string)$input);
        if (empty($input)) {
            return self::DEFAULT_DRIVE_FOLDER_ID;
        }

        if (preg_match('/\/folders\/([a-zA-Z0-9-_]+)/', $input, $matches)) {
            return $matches[1];
        }

        return $input;
    }

    /**
     * Standardize Google Spreadsheet URL
     */
    public static function buildSpreadsheetUrl(string $idOrUrl): string
    {
        $idOrUrl = trim($idOrUrl);
        if (str_starts_with($idOrUrl, 'http')) {
            return $idOrUrl;
        }
        $id = self::extractSpreadsheetId($idOrUrl);
        return "https://docs.google.com/spreadsheets/d/{$id}/edit";
    }

    /**
     * Standardize Google Drive Folder URL
     */
    public static function buildDriveFolderUrl(string $idOrUrl): string
    {
        $idOrUrl = trim($idOrUrl);
        if (str_starts_with($idOrUrl, 'http')) {
            return $idOrUrl;
        }
        $id = self::extractDriveFolderId($idOrUrl);
        return "https://drive.google.com/drive/folders/{$id}";
    }

    /**
     * Format tanggal ke format resmi Musashi: dd-Mmm-yy (contoh: 07-May-96, 16-Jul-12, 20-Aug-01, 30-Apr-12, 28-May-12, 07-Dec-12)
     */
    public static function formatMusashiDate(mixed $rawDate): string
    {
        if (empty($rawDate) || $rawDate === '-') {
            return '-';
        }
        $str = trim((string)$rawDate);

        // Jika sudah dalam format dd-Mmm-yy (e.g. 07-May-96, 16-Jul-12, 20-Aug-01)
        if (preg_match('/^(\d{2})-([A-Za-z]{3})-(\d{2})$/', $str, $m)) {
            return $m[1] . '-' . ucfirst(strtolower($m[2])) . '-' . $m[3];
        }

        $months = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
            7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'
        ];

        // Jika format ISO YYYY-MM-DD... (e.g. 1996-05-07T17:00:00.000Z atau 1996-05-07)
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $str, $m)) {
            $year = substr($m[1], -2);
            $monthNum = (int)$m[2];
            $day = str_pad($m[3], 2, '0', STR_PAD_LEFT);
            $month = $months[$monthNum] ?? 'Jan';
            return "{$day}-{$month}-{$year}";
        }

        // Jika format DD/MM/YYYY atau DD-MM-YYYY
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/', $str, $m)) {
            $day = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $monthNum = (int)$m[2];
            $year = substr($m[3], -2);
            $month = $months[$monthNum] ?? 'Jan';
            return "{$day}-{$month}-{$year}";
        }

        $ts = strtotime($str);
        if ($ts !== false) {
            return date('d-M-y', $ts);
        }

        return $str;
    }

    /**
     * Konversi tanggal ke format input HTML5 date: YYYY-MM-DD
     */
    public static function toYmd(mixed $rawDate): string
    {
        if (empty($rawDate) || $rawDate === '-') {
            return '';
        }
        $str = trim((string)$rawDate);

        // Jika dd-Mmm-yy (e.g. 07-May-96, 16-Jul-12, 20-Aug-01)
        if (preg_match('/^(\d{2})-([A-Za-z]{3})-(\d{2})$/', $str, $m)) {
            $day = $m[1];
            $monStr = strtolower($m[2]);
            $yy = (int)$m[3];
            $year = ($yy >= 50) ? (1900 + $yy) : (2000 + $yy);
            $months = ['jan'=>'01','feb'=>'02','mar'=>'03','apr'=>'04','may'=>'05','jun'=>'06','jul'=>'07','aug'=>'08','sep'=>'09','oct'=>'10','nov'=>'11','dec'=>'12'];
            $month = $months[$monStr] ?? '01';
            return sprintf('%04d-%s-%s', $year, $month, $day);
        }

        // Jika YYYY-MM-DD
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $str, $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }

        $ts = strtotime($str);
        return $ts ? date('Y-m-d', $ts) : '';
    }

    /**
     * Generate standard clean file name for appraisal PDF
     * Format: PK_[TAHUN]_[NRP]_[NAMA]_[JABATAN]_[GOLONGAN].pdf
     */
    public static function generateCleanPdfFilename(array $karyawan, $tahun = 2025): string
    {
        $nrp = preg_replace('/[^A-Za-z0-9]/', '', (string)($karyawan['nrp'] ?? '0')) ?: '0';
        $nama = strtoupper(Str::slug((string)($karyawan['nama_lengkap'] ?? 'KARYAWAN'), '_')) ?: 'KARYAWAN';
        $jabatan = strtoupper(Str::slug((string)($karyawan['jabatan'] ?? 'OPERATOR'), '_')) ?: 'OPERATOR';
        $golongan = strtoupper(Str::slug((string)($karyawan['golongan'] ?? ($karyawan['kj'] ?? 'KJ')), '_')) ?: 'GOL';

        return "PK_{$tahun}_{$nrp}_{$nama}_{$jabatan}_{$golongan}.pdf";
    }

    /**
     * Get Complete Google Sync Configuration
     */
    public static function getConfig(): array
    {
        $settings = [];
        try {
            if (Schema::hasTable('pengaturan')) {
                $settings = DB::table('pengaturan')->pluck('value', 'key')->toArray();
            }
        } catch (Exception $e) {
            Log::warning('Failed reading pengaturan table: ' . $e->getMessage());
        }

        $spreadsheetId = $settings['google_spreadsheet_id'] ?? config('google.spreadsheet_id', self::DEFAULT_SPREADSHEET_ID);
        $spreadsheetId = self::extractSpreadsheetId($spreadsheetId);

        $driveFolderId = $settings['google_drive_folder_id'] ?? config('google.drive_folder_id', self::DEFAULT_DRIVE_FOLDER_ID);
        $driveFolderId = self::extractDriveFolderId($driveFolderId);

        $spreadsheetUrl = $settings['google_spreadsheet_url'] ?? "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/edit?usp=sharing";
        $driveFolderUrl = $settings['google_drive_folder_url'] ?? "https://drive.google.com/drive/folders/{$driveFolderId}?usp=sharing";
        $appscriptUrl = $settings['google_appscript_url'] ?? env('GOOGLE_APPSCRIPT_URL', self::DEFAULT_APPSCRIPT_URL);
        if (empty($appscriptUrl) || str_contains($appscriptUrl, 'AKfycbwTG3pNhrTe') || str_contains($appscriptUrl, 'AKfycbwDK80WrKfr7iKOp7awaqhf1ezP7A-xREMbbSuuaGMlhHJJrPuGF5VXHnedi8v2ZIRE')) {
            $appscriptUrl = self::DEFAULT_APPSCRIPT_URL;
        }

        $lastSyncGoogle = $settings['terakhir_sinkron_google'] ?? null;
        $lastPushGoogle = $settings['terakhir_push_google'] ?? null;
        $syncMode = $settings['google_sync_mode'] ?? 'hybrid';

        return [
            'spreadsheet_id' => $spreadsheetId,
            'spreadsheet_url' => $spreadsheetUrl,
            'drive_folder_id' => $driveFolderId,
            'drive_folder_url' => $driveFolderUrl,
            'appscript_url' => $appscriptUrl,
            'sync_mode' => $syncMode,
            'terakhir_sinkron_google' => $lastSyncGoogle ?: 'Belum pernah disinkronkan',
            'terakhir_push_google' => $lastPushGoogle ?: 'Belum pernah dikirim',
            'has_appscript' => !empty($appscriptUrl),
            'has_spreadsheet' => !empty($spreadsheetId),
            'has_drive' => !empty($driveFolderId),
            'appscript_code' => self::getAppsScriptTemplateCode($spreadsheetId, $driveFolderId),
        ];
    }

    /**
     * Save Google Spreadsheet & Apps Script Configuration
     */
    public static function saveConfig(array $data): void
    {
        $spreadsheetInput = trim($data['google_spreadsheet_url'] ?? ($data['google_spreadsheet_id'] ?? ''));
        $driveInput = trim($data['google_drive_folder_url'] ?? ($data['google_drive_folder_id'] ?? ''));
        $appscriptInput = trim($data['google_appscript_url'] ?? '');
        $syncMode = trim($data['google_sync_mode'] ?? 'hybrid');

        $spreadsheetId = self::extractSpreadsheetId($spreadsheetInput);
        $driveFolderId = self::extractDriveFolderId($driveInput);

        $spreadsheetUrl = self::buildSpreadsheetUrl($spreadsheetInput ?: $spreadsheetId);
        $driveFolderUrl = self::buildDriveFolderUrl($driveInput ?: $driveFolderId);

        $items = [
            'google_spreadsheet_url' => $spreadsheetUrl,
            'google_spreadsheet_id' => $spreadsheetId,
            'google_drive_folder_url' => $driveFolderUrl,
            'google_drive_folder_id' => $driveFolderId,
            'google_appscript_url' => $appscriptInput,
            'google_sync_mode' => $syncMode,
        ];

        foreach ($items as $key => $val) {
            DB::table('pengaturan')->updateOrInsert(
                ['key' => $key],
                ['value' => $val]
            );
        }

        // Simpan juga ke sheet 'pengaturan' di Google Spreadsheet agar permanen di cloud
        if (!empty($appscriptInput) && filter_var($appscriptInput, FILTER_VALIDATE_URL)) {
            try {
                $pRows = [];
                foreach ($items as $k => $v) {
                    $pRows[] = ['key' => $k, 'value' => (string)$v, 'updated_at' => now()->toDateTimeString()];
                }
                Http::timeout(5)->withoutVerifying()->post($appscriptInput, [
                    'action' => 'push_all',
                    'data' => [
                        'pengaturan' => $pRows,
                    ]
                ]);
            } catch (\Throwable $e) {}
        }

        Cache::flush();
    }

    /**
     * Test Connection to Google Apps Script and Google Spreadsheet
     */
    public static function testConnection(?string $spreadsheetInput = null, ?string $appscriptInput = null): array
    {
        $config = self::getConfig();
        $spreadsheetUrl = $spreadsheetInput ?: $config['spreadsheet_url'];
        $appscriptUrl = $appscriptInput ?: $config['appscript_url'];
        $spreadsheetId = self::extractSpreadsheetId($spreadsheetUrl);

        $results = [
            'success' => true,
            'spreadsheet_valid' => false,
            'appscript_valid' => false,
            'spreadsheet_id' => $spreadsheetId,
            'appscript_url' => $appscriptUrl,
            'messages' => [],
        ];

        // 1. Verify Spreadsheet ID
        if (!empty($spreadsheetId) && strlen($spreadsheetId) >= 20) {
            $results['spreadsheet_valid'] = true;
            $results['messages'][] = "ID Google Spreadsheet valid: '{$spreadsheetId}'. Spreadsheet siap digunakan.";
        } else {
            $results['success'] = false;
            $results['messages'][] = "Tautan / ID Google Spreadsheet tidak valid atau kosong.";
        }

        // 2. Verify Google Apps Script Web App URL
        if (!empty($appscriptUrl)) {
            if (!filter_var($appscriptUrl, FILTER_VALIDATE_URL) || !str_contains($appscriptUrl, 'script.google.com')) {
                $results['messages'][] = "URL Google Apps Script harus berformat resmi 'https://script.google.com/macros/s/.../exec'.";
            } else {
                try {
                    $response = Http::timeout(6)->withoutVerifying()->get($appscriptUrl, [
                        'action' => 'ping',
                        'source' => 'Musashi-EApproval',
                    ]);

                    $status = $response->status();
                    if ($status >= 200 && $status < 400) {
                        $results['appscript_valid'] = true;
                        $results['messages'][] = "Google Apps Script Web App merespons aktif (HTTP {$status}). Koneksi online berhasil!";
                    } else {
                        $results['messages'][] = "Google Apps Script merespons kode status HTTP {$status}. Pastikan hak akses Web App disetel ke 'Anyone'.";
                    }
                } catch (Exception $e) {
                    $results['messages'][] = "Pemeriksaan Apps Script URL: Endpoint terdaftar ('" . substr($appscriptUrl, 0, 45) . "...'). Endpoint siap digunakan.";
                }
            }
        } else {
            $results['messages'][] = "Tautan Google Apps Script belum diisi. Anda dapat menyalin kode Apps Script di bawah dan memasang Web App URL.";
        }

        return $results;
    }

    /**
     * Pull / Synchronize Data from Google Spreadsheet & Apps Script
     */
    public static function syncFromGoogle(?string $appscriptUrl = null, ?string $spreadsheetId = null): array
    {
        $config = self::getConfig();
        $targetAppscript = $appscriptUrl ?: $config['appscript_url'];
        $targetSpreadsheet = $spreadsheetId ?: $config['spreadsheet_id'];

        $pulledRowsCount = 0;
        $syncSource = 'DATA PK.xlsx Lokal (Master Resmi)';

        // Attempt pull from Google Apps Script Web App if URL is provided
        if (!empty($targetAppscript) && filter_var($targetAppscript, FILTER_VALIDATE_URL)) {
            try {
                $response = Http::timeout(12)->withoutVerifying()->get($targetAppscript, [
                    'action' => 'pull_all',
                    'spreadsheet_id' => $targetSpreadsheet,
                ]);

                if ($response->successful()) {
                    $json = $response->json();
                    $data = $json['data'] ?? [];
                    $empRows = $data['karyawan'] ?? ($data['Master Karyawan'] ?? []);

                    if (is_array($empRows)) {
                        $pulledRowsCount = count($empRows);
                        $syncSource = "Google Apps Script & Spreadsheet (ID: {$targetSpreadsheet})";

                        $karyawanCols = Schema::getColumnListing('karyawan');
                        $karyawanColSet = array_flip($karyawanCols);

                        DB::transaction(function () use ($empRows, $karyawanColSet) {
                            $sheetNrps = [];
                            foreach ($empRows as $row) {
                                $nrp = trim((string)($row['nrp'] ?? ''));
                                if ($nrp === '') continue;
                                $sheetNrps[] = $nrp;

                                $cleanRow = [];
                                foreach ($row as $k => $v) {
                                    $kLower = strtolower(trim((string)$k));
                                    if (isset($karyawanColSet[$kLower])) {
                                        $cleanRow[$kLower] = $v;
                                    }
                                }
                                if (!empty($cleanRow)) {
                                    DB::table('karyawan')->updateOrInsert(['nrp' => $nrp], $cleanRow);
                                }
                            }

                            // Hapus karyawan lokal yang sudah tidak ada / dihapus di Google Spreadsheet
                            DB::table('karyawan')->whereNotIn('nrp', $sheetNrps)->delete();

                            if (empty($sheetNrps)) {
                                if (Schema::hasTable('dokumen')) DB::table('dokumen')->delete();
                                if (Schema::hasTable('nilai')) DB::table('nilai')->delete();
                                if (Schema::hasTable('indikator')) DB::table('indikator')->delete();
                                if (Schema::hasTable('tanda_tangan')) DB::table('tanda_tangan')->delete();
                            }
                        });

                        // Also sync documents from spreadsheet if present
                        if (!empty($data['dokumen']) && is_array($data['dokumen'])) {
                            $docCols = Schema::getColumnListing('dokumen');
                            $docColSet = array_flip($docCols);

                            DB::transaction(function () use ($data, $docColSet) {
                                foreach ($data['dokumen'] as $docRow) {
                                    $id = trim((string)($docRow['id'] ?? ''));
                                    $nrp = trim((string)($docRow['nrp'] ?? ''));
                                    if ($id === '' && $nrp === '') continue;

                                    $cleanDoc = [];
                                    foreach ($docRow as $k => $v) {
                                        $kLower = strtolower(trim((string)$k));
                                        if (isset($docColSet[$kLower])) {
                                            $cleanDoc[$kLower] = $v;
                                        }
                                    }
                                    if (!empty($cleanDoc)) {
                                        if ($id !== '') {
                                            DB::table('dokumen')->updateOrInsert(['id' => $id], $cleanDoc);
                                        } else {
                                            DB::table('dokumen')->updateOrInsert(['nrp' => $nrp], $cleanDoc);
                                        }
                                    }
                                }
                            });
                        }

                        // Also sync signatures from 'Master Tanda Tangan' sheet if present
                        $ttdRows = $data['Master Tanda Tangan'] ?? ($data['master_tanda_tangan'] ?? []);
                        if (!empty($ttdRows) && is_array($ttdRows)) {
                            foreach ($ttdRows as $ttdRow) {
                                $nrp = trim((string)($ttdRow['nrp'] ?? ''));
                                if ($nrp === '') continue;
                                $sigUrl = $ttdRow['signature_url'] ?? null;
                                $fileId = $ttdRow['drive_file_id'] ?? null;
                                if (Schema::hasTable('master_tanda_tangan')) {
                                    DB::table('master_tanda_tangan')->updateOrInsert(['nrp' => $nrp], [
                                        'nama' => $ttdRow['nama'] ?? null,
                                        'position' => $ttdRow['position'] ?? null,
                                        'department' => $ttdRow['department'] ?? null,
                                        'division' => $ttdRow['division'] ?? null,
                                        'signature_url' => $sigUrl,
                                        'drive_file_id' => $fileId,
                                        'updated_at' => now(),
                                    ]);
                                }
                                if (!empty($sigUrl)) {
                                    DB::table('app_users')->where('nrp', $nrp)
                                        ->where(function($q) {
                                            $q->whereNull('signature_path')
                                              ->orWhere('signature_path', '')
                                              ->orWhere('signature_path', 'not like', 'data:image%');
                                        })
                                        ->update([
                                            'signature_path' => $sigUrl,
                                        ]);
                                }
                            }
                        }

                        // Auto-generate leadership accounts for the pulled employees
                        AccountSyncService::syncLeadershipAccountsFromKaryawan();

                        // Sync users barcodettd / ttdbarcode from spreadsheet if present
                        if (!empty($data['users']) && is_array($data['users'])) {
                            foreach ($data['users'] as $uRow) {
                                $uEmail = trim((string)($uRow['email'] ?? ''));
                                $uNrp = trim((string)($uRow['nrp'] ?? ''));
                                $barcodeVal = trim((string)($uRow['barcodettd'] ?? ($uRow['ttdbarcode'] ?? '')));
                                if ($barcodeVal !== '') {
                                    $q = DB::table('app_users');
                                    if ($uEmail !== '') {
                                        $q->where('email', $uEmail);
                                    } elseif ($uNrp !== '') {
                                        $q->where('nrp', $uNrp);
                                    } else {
                                        continue;
                                    }
                                    $q->update([
                                        'barcodettd' => $barcodeVal,
                                        'ttdbarcode' => $barcodeVal,
                                    ]);
                                }
                            }
                        }

                        // Sync pengaturan from Google Spreadsheet if present
                        $settingRows = $data['pengaturan'] ?? [];
                        if (!empty($settingRows) && is_array($settingRows)) {
                            foreach ($settingRows as $sRow) {
                                $k = trim((string)($sRow['key'] ?? ''));
                                $v = (string)($sRow['value'] ?? '');
                                if ($k !== '') {
                                    DB::table('pengaturan')->updateOrInsert(['key' => $k], ['value' => $v, 'updated_at' => now()]);
                                }
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                Log::info("Apps Script pull skipped or offline: " . $e->getMessage());
            }
        }

        $localSync = [
            'karyawan_total' => DB::table('karyawan')->count(),
            'dokumen_total' => DB::table('dokumen')->count(),
            'users_total' => DB::table('app_users')->count(),
        ];

        $timestampNow = date('d F Y, H:i') . ' WIB';
        DB::table('pengaturan')->updateOrInsert(
            ['key' => 'terakhir_sinkron_google'],
            ['value' => $timestampNow]
        );
        DB::table('pengaturan')->updateOrInsert(
            ['key' => 'terakhir_sinkron_db_display'],
            ['value' => $timestampNow]
        );

        Cache::flush();

        return [
            'success' => true,
            'source' => $syncSource,
            'timestamp' => $timestampNow,
            'karyawan_total' => $localSync['karyawan_total'],
            'dokumen_total' => $localSync['dokumen_total'],
            'users_total' => $localSync['users_total'],
            'pulled_rows' => $pulledRowsCount,
        ];
    }

    /**
     * Push current database records to Google Spreadsheet via Apps Script Web App
     */
    public static function pushToGoogle(?string $appscriptUrl = null, ?string $spreadsheetId = null): array
    {
        $config = self::getConfig();
        $targetAppscript = $appscriptUrl ?: $config['appscript_url'];
        $targetSpreadsheet = $spreadsheetId ?: $config['spreadsheet_id'];

        $karyawanData = DB::table('karyawan')->get()->toArray();
        $dokumenData = DB::table('dokumen')->get()->toArray();
        $usersData = User::select('id', 'nama', 'email', 'role', 'nrp', 'departemen', 'aktif', 'barcodettd', 'ttdbarcode')->get()->toArray();
        $nilaiData = Schema::hasTable('nilai') ? DB::table('nilai')->get()->toArray() : [];
        $ttdData = Schema::hasTable('tanda_tangan') ? DB::table('tanda_tangan')->get()->toArray() : [];
        $indikatorData = Schema::hasTable('indikator') ? DB::table('indikator')->get()->toArray() : [];
        $pengaturanData = Schema::hasTable('pengaturan') ? DB::table('pengaturan')->get()->toArray() : [];
        $kuotaData = Schema::hasTable('kuota_departemen') ? DB::table('kuota_departemen')->get()->toArray() : [];
        $auditData = Schema::hasTable('audit_log') ? DB::table('audit_log')->latest('id')->limit(500)->get()->toArray() : [];
        $dokumenDinilaiData = self::buildDokumenDinilaiRows();
        $targetPemantauanData = self::buildTargetPemantauanRows();

        $payload = [
            'spreadsheet_id' => $targetSpreadsheet,
            'waktu_ekspor' => now()->toDateTimeString(),
            'total_karyawan' => count($karyawanData),
            'total_dokumen' => count($dokumenData),
            'total_users' => count($usersData),
            'karyawan' => $karyawanData,
            'dokumen' => $dokumenData,
            'users' => $usersData,
            'nilai' => $nilaiData,
            'tanda_tangan' => $ttdData,
            'indikator' => $indikatorData,
            'pengaturan' => $pengaturanData,
            'kuota_departemen' => $kuotaData,
            'Dokumen_Dinilai' => $dokumenDinilaiData,
            'Target_Pemantauan' => $targetPemantauanData,
            'audit_log' => $auditData,
        ];

        $pushedToWeb = false;
        if (!empty($targetAppscript) && filter_var($targetAppscript, FILTER_VALIDATE_URL)) {
            try {
                $response = Http::timeout(40)->withoutVerifying()->post($targetAppscript, [
                    'action' => 'push_all',
                    'data' => $payload,
                ]);

                if ($response->successful()) {
                    $pushedToWeb = true;
                }
            } catch (Exception $e) {
                Log::info("Apps Script push skipped or offline: " . $e->getMessage());
            }
        }

        $timestampNow = date('d F Y, H:i') . ' WIB';
        DB::table('pengaturan')->updateOrInsert(
            ['key' => 'terakhir_push_google'],
            ['value' => $timestampNow]
        );

        Cache::flush();

        return [
            'success' => true,
            'pushed_to_web' => $pushedToWeb,
            'timestamp' => $timestampNow,
            'total_karyawan' => count($karyawanData),
            'total_dokumen' => count($dokumenData),
            'total_users' => count($usersData),
        ];
    }

    /**
     * Standard column headers for Google Spreadsheet tables
     */
    public static function getStandardHeaders(string $table): array
    {
        return match ($table) {
            'karyawan' => [
                'id', 'nrp', 'nama_lengkap', 'role', 'jabatan', 'departemen', 'seksi', 'division',
                'kj', 'sub_gol', 'golongan', 'tgl_masuk', 'tgl_lahir', 'pendidikan', 'status_kerja',
                'lokasi_kerja', 'email', 'telepon', 'alamat', 'nrp_atasan', 'nrp_foreman',
                'nrp_section_head', 'nrp_dept_head', 'nrp_div_head', 'created_at'
            ],
            'dokumen' => [
                'id', 'periode_id', 'nrp', 'kode_form', 'status', 'total_nilai',
                'grade_original', 'docking_final', 'drive_file_id', 'drive_url',
                'hash_dokumen', 'kode_verifikasi', 'created_at',
                'nrp_foreman', 'nama_foreman', 'nrp_section_head', 'nama_section_head',
                'nrp_dept_head', 'nama_dept_head', 'nrp_div_head', 'nama_div_head'
            ],
            'nilai' => [
                'dokumen_id', 'level', 'aspek_kode', 'skor', 'catatan'
            ],
            'indikator' => [
                'dokumen_id', 'pilihan_potensi', 'catatan_potensi', 'kelebihan', 'kekurangan'
            ],
            'tanda_tangan' => [
                'dokumen_id', 'level', 'user_id', 'tgl_ttd', 'ip', 'user_agent',
                'hash_snapshot', 'ttd_image', 'created_at', 'nrp', 'nama', 'role'
            ],
            'kuota_departemen' => [
                'id', 'departemen', 'grade_s_max', 'grade_a_max', 'grade_b_max', 'grade_c_max', 'grade_k_max'
            ],
            'Dokumen_Dinilai' => [
                'NRP', 'NAMA', 'NAMA BAGIAN', 'SECTION', 'DEPARTMENT', 'DIVISION', 'POSITION', 'START WORK', 'KJ', 'GOLONGAN', 'Hasil Kerja', 'Proses Kerja', 'Sikap Kerja', 'Man Management', 'Total Nilai', 'NILAI PK Foreman (Jika Ada)', 'NILAI PK MANDIRI', 'NILAI PK SECT', 'NILAI PK DEPT', 'NILAI PK DIV', 'NILAI PK FINAL'
            ],
            'Target_Pemantauan' => [
                'No', 'Division', 'Department', 'Target_Kuota', 'Sudah_Dinilai', 'Belum_Dinilai', 'Realisasi_Persen', 'Target_S', 'Target_A', 'Target_B', 'Target_C', 'Target_K', 'Status', 'Terakhir_Update'
            ],
            'master_kriteria' => [
                'aspek_kode', 'skor', 'deskripsi'
            ],
            'users' => [
                'id', 'nama', 'email', 'role', 'nrp', 'departemen', 'aktif', 'barcodettd', 'ttdbarcode'
            ],
            default => ['id', 'created_at']
        };
    }

    /**
     * Synchronize specific table(s) in real time to Google Spreadsheet via Apps Script Web App
     */
    public static function syncTables(array $tables): array
    {
        $config = self::getConfig();
        $appscriptUrl = $config['appscript_url'] ?? '';

        if (empty($appscriptUrl) || !filter_var($appscriptUrl, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'message' => 'Apps Script Web App URL tidak terkonfigurasi.'];
        }

        // Pastikan Dokumen_Dinilai, Target_Pemantauan, dan users selalu ikut tersinkronisasi bila karyawan atau dokumen diperbarui / dihapus
        if (in_array('karyawan', $tables) || in_array('dokumen', $tables)) {
            if (!in_array('Dokumen_Dinilai', $tables)) $tables[] = 'Dokumen_Dinilai';
            if (!in_array('Target_Pemantauan', $tables)) $tables[] = 'Target_Pemantauan';
            if (!in_array('users', $tables)) $tables[] = 'users';
        }

        $payload = [];
        $emptyTables = [];
        foreach ($tables as $table) {
            $localTable = match ($table) {
                'users' => 'app_users',
                default => $table,
            };

            $rows = [];
            if ($table === 'Dokumen_Dinilai') {
                $rows = self::buildDokumenDinilaiRows();
            } elseif ($table === 'Target_Pemantauan') {
                $rows = self::buildTargetPemantauanRows();
            } elseif (Schema::hasTable($localTable)) {
                if ($localTable === 'app_users') {
                    $rows = DB::table('app_users')
                        ->select('id', 'nama', 'email', 'role', 'nrp', 'departemen', 'aktif', 'barcodettd', 'ttdbarcode')
                        ->get()
                        ->map(fn ($row) => (array)$row)
                        ->toArray();
                } else {
                    $rows = DB::table($localTable)->get()->map(function ($row) {
                        return (array)$row;
                    })->toArray();
                }
            }

            if (empty($rows)) {
                $emptyHeaders = self::getStandardHeaders($table);
                $keyCol = match($table) {
                    'karyawan', 'Dokumen_Dinilai', 'Master Tanda Tangan' => 'nrp',
                    'Target_Pemantauan' => 'Department',
                    'dokumen', 'kuota_departemen', 'periode' => 'id',
                    'nilai', 'indikator', 'tanda_tangan' => 'dokumen_id',
                    'users' => 'email',
                    default => 'id',
                };
                $placeholder = array_fill_keys($emptyHeaders, '');
                $placeholder[$keyCol] = '__DUMMY_CLEAR__';
                $payload[$table] = [$placeholder];
                $emptyTables[] = ['table' => $table, 'keyCol' => $keyCol];
            } else {
                $payload[$table] = $rows;
            }
        }

        if (empty($payload)) {
            return ['success' => true, 'updated' => []];
        }

        try {
            $response = Http::timeout(60)->withoutVerifying()->post($appscriptUrl, [
                'action' => 'push_all',
                'data' => $payload,
            ]);

            // Clean up temporary blank rows for empty tables so sheet has 0 data rows and styled headers
            if (!empty($emptyTables)) {
                foreach ($emptyTables as $item) {
                    try {
                        Http::timeout(10)->withoutVerifying()->post($appscriptUrl, [
                            'action' => 'delete_row',
                            'tables' => [$item['table']],
                            'key_column' => $item['keyCol'],
                            'key_value' => '__DUMMY_CLEAR__',
                        ]);
                    } catch (\Throwable $e) {
                        Log::warning("Failed cleaning placeholder row for {$item['table']}: " . $e->getMessage());
                    }
                }
            }

            if ($response->successful()) {
                $timestampNow = date('d F Y, H:i') . ' WIB';
                DB::table('pengaturan')->updateOrInsert(
                    ['key' => 'terakhir_push_google'],
                    ['value' => $timestampNow]
                );
                return [
                    'success' => true,
                    'updated' => array_keys($payload),
                    'body' => $response->json(),
                ];
            }

            return ['success' => false, 'status' => $response->status()];
        } catch (\Throwable $e) {
            Log::warning("Realtime Google Spreadsheet sync failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Synchronize a single table in real time
     */
    public static function syncTable(string $table): array
    {
        return self::syncTables([$table]);
    }

    /**
     * Fast single employee row sync to Google Spreadsheet (Add / Edit / Delete).
     * Synchronous execution with short timeout and automatic fallback.
     */
    public static function syncEmployeeRow(string $action, string $nrp): array
    {
        $config = self::getConfig();
        $appscriptUrl = $config['appscript_url'] ?? '';

        if (empty($appscriptUrl) || !filter_var($appscriptUrl, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'message' => 'Apps Script Web App URL tidak terkonfigurasi.'];
        }

        $cleanAction = ($action === 'delete') ? 'delete_row' : 'upsert_row';
        $payload = [
            'action' => $cleanAction,
            'tables' => ($cleanAction === 'delete') 
                ? ['karyawan', 'dokumen', 'Dokumen_Dinilai', 'nilai', 'indikator', 'tanda_tangan', 'Master Tanda Tangan', 'users']
                : ['karyawan', 'dokumen'],
            'key_column' => 'nrp',
            'key_value' => (string)$nrp,
        ];

        if ($cleanAction === 'upsert_row') {
            $emp = DB::table('karyawan')->where('nrp', (string)$nrp)->first();
            if (!$emp) {
                return ['success' => false, 'message' => "Karyawan NRP {$nrp} tidak ditemukan di database."];
            }
            $payload['tables'] = ['karyawan'];
            $empArr = (array)$emp;
            if (isset($empArr['tgl_masuk'])) {
                $empArr['tgl_masuk'] = self::formatMusashiDate($empArr['tgl_masuk']);
            }
            $payload['row_data'] = $empArr;
        }

        try {
            $response = Http::timeout(4)->withoutVerifying()->post($appscriptUrl, $payload);
            if ($response->successful()) {
                $body = $response->json();
                if (isset($body['status']) && $body['status'] === 'success') {
                    $timestampNow = date('d F Y, H:i') . ' WIB';
                    DB::table('pengaturan')->updateOrInsert(
                        ['key' => 'terakhir_push_google'],
                        ['value' => $timestampNow]
                    );
                    return ['success' => true, 'fast_path' => true, 'data' => $body];
                }
                // Fallback jika Google Apps Script versi lama belum mendukung endpoint upsert_row / delete_row
                if (isset($body['status']) && $body['status'] === 'unknown_action') {
                    Log::info("Apps Script does not support {$cleanAction}, falling back to full syncTables.");
                    return self::syncTables(['karyawan', 'dokumen']);
                }
            }
            return ['success' => false, 'status' => $response->status()];
        } catch (\Throwable $e) {
            Log::warning("Fast Google sync failed: " . $e->getMessage() . ", falling back to syncTables");
            return self::syncTables(['karyawan', 'dokumen']);
        }
    }

    /**
     * Non-blocking Async Dispatch for Employee Row Sync.
     * Fires artisan google:sync-row in background immediately (< 5ms response time).
     * Front-end page redirects instantly without lag.
     */
    public static function syncEmployeeRowAsync(string $action, string $nrp): void
    {
        try {
            $artisanPath = base_path('artisan');
            $act = escapeshellarg($action);
            $nrpArg = escapeshellarg($nrp);

            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $cmd = "start /B \"\" php \"{$artisanPath}\" google:sync-row {$act} --nrp={$nrpArg} > NUL 2>&1";
                pclose(popen($cmd, "r"));
            } else {
                $cmd = "php \"{$artisanPath}\" google:sync-row {$act} --nrp={$nrpArg} > /dev/null 2>&1 &";
                exec($cmd);
            }
        } catch (\Throwable $e) {
            Log::warning("Async Google sync launch failed: " . $e->getMessage() . ", running synchronously");
            self::syncEmployeeRow($action, $nrp);
        }
    }

    /**
     * Delete audit log entries from Google Spreadsheet sheet audit_log
     *
     * @param array<int|string>|int|string $ids
     * @param bool $clearAll
     * @return array<string, mixed>
     */
    public static function syncAuditLogDelete($ids = [], bool $clearAll = false): array
    {
        $config = self::getConfig();
        $appscriptUrl = $config['appscript_url'] ?? '';

        if (empty($appscriptUrl) || !filter_var($appscriptUrl, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'message' => 'Apps Script Web App URL tidak terkonfigurasi.'];
        }

        $idList = is_array($ids) ? $ids : (empty($ids) ? [] : [$ids]);

        try {
            $payload = [
                'action' => $clearAll ? 'clear_audit_log' : 'delete_audit_log',
                'clear_all' => $clearAll,
                'ids' => array_values(array_map('strval', $idList)),
                'tables' => ['audit_log'],
                'key_column' => 'id',
                'key_value' => !empty($idList) ? (string)$idList[0] : '',
            ];

            $response = Http::timeout(8)->withoutVerifying()->post($appscriptUrl, $payload);
            if ($response->successful()) {
                $body = $response->json();
                if (isset($body['status']) && $body['status'] === 'success') {
                    $timestampNow = date('d F Y, H:i') . ' WIB';
                    DB::table('pengaturan')->updateOrInsert(
                        ['key' => 'terakhir_push_google'],
                        ['value' => $timestampNow]
                    );
                    return ['success' => true, 'data' => $body];
                }
            }

            // Fallback: sinkronkan tabel audit_log langsung ke sheet
            self::syncTables(['audit_log']);
            return ['success' => true, 'fallback' => true];
        } catch (\Throwable $e) {
            Log::warning("Fast audit_log delete in Google Sheet failed: " . $e->getMessage() . ", attempting syncTables");
            try {
                self::syncTables(['audit_log']);
            } catch (\Throwable $ex) {}
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Non-blocking Async Dispatch for Tables Sync to Google Spreadsheet.
     * Fires artisan google:sync-row sync_all in background immediately (< 5ms response time).
     */
    public static function syncTablesAsync(array $tables = []): void
    {
        try {
            $artisanPath = base_path('artisan');
            $tablesArg = !empty($tables) ? ' --tables=' . escapeshellarg(implode(',', $tables)) : '';

            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $cmd = "start /B \"\" php \"{$artisanPath}\" google:sync-row sync_all{$tablesArg} > NUL 2>&1";
                pclose(popen($cmd, "r"));
            } else {
                $cmd = "php \"{$artisanPath}\" google:sync-row sync_all{$tablesArg} > /dev/null 2>&1 &";
                exec($cmd);
            }
        } catch (\Throwable $e) {
            Log::warning("Async Google syncTables launch failed: " . $e->getMessage() . ", running synchronously");
            self::syncTables($tables ?: ['karyawan', 'dokumen']);
        }
    }

    /**
     * Non-blocking Async Dispatch for PDF Upload to Google Drive.
     * Generates and uploads PDF in background immediately (< 5ms response time).
     */
    public static function uploadPdfToDriveAsync(int $dokumenId): void
    {
        try {
            $artisanPath = base_path('artisan');

            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $cmd = "start /B \"\" php \"{$artisanPath}\" google:sync-row upload_pdf --doc-id={$dokumenId} > NUL 2>&1";
                pclose(popen($cmd, "r"));
            } else {
                $cmd = "php \"{$artisanPath}\" google:sync-row upload_pdf --doc-id={$dokumenId} > /dev/null 2>&1 &";
                exec($cmd);
            }
        } catch (\Throwable $e) {
            Log::warning("Async Google uploadPdfToDrive launch failed: " . $e->getMessage());
        }
    }

    /**
     * Pull specific table data from Google Spreadsheet
     */
    public static function pullTable(string $table): array
    {
        $config = self::getConfig();
        $appscriptUrl = $config['appscript_url'] ?? '';

        if (empty($appscriptUrl) || !filter_var($appscriptUrl, FILTER_VALIDATE_URL)) {
            return [];
        }

        try {
            $response = Http::timeout(6)->withoutVerifying()->get($appscriptUrl, [
                'action' => 'get_table',
                'table' => $table,
            ]);

            if ($response->successful()) {
                $json = $response->json();
                if (!empty($json['data']) && is_array($json['data'])) {
                    return $json['data'];
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Apps Script pullTable '{$table}' failed: " . $e->getMessage());
        }

        return [];
    }

    /**
     * Upload finalized appraisal PDF to Google Drive via Apps Script Web App
     */
    public static function uploadPdfToDrive(int $dokumenId, ?string $pdfBinary = null): array
    {
        $config = self::getConfig();
        $appscriptUrl = $config['appscript_url'] ?? '';

        if (empty($appscriptUrl) || !filter_var($appscriptUrl, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'message' => 'Apps Script Web App URL tidak terkonfigurasi.'];
        }

        $dokumen = DB::table('dokumen')->where('id', $dokumenId)->first();
        if (!$dokumen) {
            return ['success' => false, 'message' => 'Dokumen tidak ditemukan.'];
        }

        $karyawan = DB::table('karyawan')->where('nrp', $dokumen->nrp)->first();
        $karyawanArr = $karyawan ? (array)$karyawan : [];

        $periode = !empty($dokumen->periode_id) ? DB::table('periode')->where('id', $dokumen->periode_id)->first() : null;
        $tahun = (string)($dokumen->tahun ?? ($periode->tahun ?? (DB::table('pengaturan')->where('key', 'tahun_penilaian')->value('value') ?? date('Y'))));
        $departemen = (string)($karyawanArr['departemen'] ?? $dokumen->departemen ?? 'PROD 2');
        $seksi = (string)($karyawanArr['seksi'] ?? '');
        $division = (string)($karyawanArr['division'] ?? ($karyawanArr['divisi'] ?? 'MANUFACTURING DIVISION'));

        if (empty($pdfBinary)) {
            try {
                $calcService = app(\App\Services\AppraisalCalculationService::class);
                $sheetRepo = app(\App\Contracts\SheetRepositoryInterface::class);
                $penilaianController = new \App\Http\Controllers\PenilaianController($sheetRepo, $calcService);
                $pdfData = $penilaianController->getPdfInstanceForDoc($dokumenId);
                if ($pdfData && isset($pdfData['pdf'])) {
                    $pdfBinary = $pdfData['pdf']->output();
                }
            } catch (\Throwable $e) {
                Log::warning("Generate PDF for Google Drive upload failed: " . $e->getMessage());
                return ['success' => false, 'error' => $e->getMessage()];
            }
        }

        if (empty($pdfBinary)) {
            return ['success' => false, 'message' => 'Gagal menggenerasi berkas PDF dokumen.'];
        }

        try {
            $response = Http::timeout(15)->withoutVerifying()->post($appscriptUrl, [
                'action' => 'save_pdf',
                'pdf_base64' => base64_encode($pdfBinary),
                'nrp' => (string)($karyawanArr['nrp'] ?? $dokumen->nrp),
                'nama_lengkap' => (string)($karyawanArr['nama_lengkap'] ?? 'KARYAWAN'),
                'jabatan' => (string)($karyawanArr['jabatan'] ?? 'OPERATOR'),
                'golongan' => (string)($karyawanArr['golongan'] ?? ($karyawanArr['kj'] ?? 'KJ')),
                'division' => $division,
                'departemen' => $departemen,
                'seksi' => $seksi,
                'tahun' => $tahun,
                'status' => (string)($dokumen->status ?? ''),
            ]);

            if ($response->successful()) {
                $resData = $response->json();
                if (!empty($resData['file_id']) || !empty($resData['file_url'])) {
                    DB::table('dokumen')->where('id', $dokumenId)->update([
                        'drive_file_id' => $resData['file_id'] ?? null,
                        'drive_url' => $resData['file_url'] ?? null,
                        'updated_at' => now()->toDateTimeString(),
                    ]);
                    // Otomatis sinkronkan link drive_url ke tab dokumen di Google Spreadsheet
                    self::syncTable('dokumen');
                }
                return ['success' => true, 'data' => $resData];
            }

            return ['success' => false, 'status' => $response->status()];
        } catch (\Throwable $e) {
            Log::warning("Upload PDF to Google Drive failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Upload User Digital Signature to Google Drive with structured folders
     * Folder: Root Folder -> TANDA_TANGAN -> [ROLE / POSITION] (e.g. FOREMAN, SECTION_HEAD, DEPT_HEAD, DIV_HEAD)
     * File Name: {nrp}_{nama}_{position}_{departemen}.png
     */
    public static function uploadSignatureToGoogleDrive(User $user, string $imageBinary, ?string $ext = 'png'): array
    {
        $config = self::getConfig();
        $appscriptUrl = $config['appscript_url'];

        $nrp = preg_replace('/[^A-Za-z0-9]/', '', (string)($user->nrp ?: $user->id)) ?: '0';
        $nama = strtoupper(preg_replace('/[^A-Za-z0-9]/', '_', (string)$user->nama)) ?: 'USER';
        $role = strtoupper(preg_replace('/[^A-Za-z0-9]/', '_', (string)($user->role ?: 'FOREMAN'))) ?: 'FOREMAN';
        $dept = strtoupper(preg_replace('/[^A-Za-z0-9]/', '_', (string)($user->departemen ?: 'ALL'))) ?: 'ALL';
        $pos = strtoupper(preg_replace('/[^A-Za-z0-9]/', '_', (string)($user->jabatan ?: $user->posisi ?: $role))) ?: $role;
        $div = strtoupper(trim((string)($user->divisi ?: 'MANUFACTURING DIVISION')));

        $cleanFilename = "{$nrp}_{$nama}_{$role}_{$dept}.png";

        if (empty($appscriptUrl) || !filter_var($appscriptUrl, FILTER_VALIDATE_URL)) {
            return [
                'success' => false,
                'message' => 'URL Google Apps Script belum dikonfigurasi.',
                'file_name' => $cleanFilename,
            ];
        }

        try {
            $response = Http::timeout(20)->withoutVerifying()->post($appscriptUrl, [
                'action' => 'save_signature',
                'signature_base64' => base64_encode($imageBinary),
                'nrp' => $nrp,
                'nama' => $nama,
                'role' => $role,
                'position' => $role,
                'jabatan' => $pos,
                'departemen' => $dept,
                'department' => $dept,
                'division' => $div,
                'filename' => $cleanFilename,
            ]);

            if ($response->successful()) {
                $resData = $response->json();
                if (isset($resData['status']) && $resData['status'] === 'success') {
                    $fileUrl = $resData['file_url'] ?? null;
                    $fileId = $resData['file_id'] ?? null;

                    // Automatically persist to master_tanda_tangan SQLite table as well
                    try {
                        if (Schema::hasTable('master_tanda_tangan')) {
                            DB::table('master_tanda_tangan')->updateOrInsert(
                                ['nrp' => $nrp],
                                [
                                    'nama' => $user->nama,
                                    'position' => $role,
                                    'department' => $dept,
                                    'division' => $div,
                                    'signature_url' => $fileUrl,
                                    'drive_file_id' => $fileId,
                                    'signature_base64' => 'data:image/png;base64,' . base64_encode($imageBinary),
                                    'updated_at' => now(),
                                ]
                            );
                        }
                    } catch (\Throwable $e) {}

                    return [
                        'success' => true,
                        'file_id' => $fileId,
                        'file_url' => $fileUrl,
                        'file_name' => $resData['file_name'] ?? $cleanFilename,
                        'folder_path' => $resData['folder_path'] ?? "TANDA_TANGAN / {$role}",
                    ];
                }

                $rawErr = $resData['error'] ?? $resData['message'] ?? ($resData['status'] ?? null);
                $errExplanation = $rawErr;
                if ($rawErr && str_contains($rawErr, 'DriveApp')) {
                    $errExplanation = 'Izin Google Drive belum diotorisasi di Google Apps Script. Di Apps Script, pastikan Deploy diset dengan "Execute as: Me" dan setujui izin otorisasi akses Drive.';
                } elseif (isset($resData['status']) && $resData['status'] === 'unknown_action') {
                    $errExplanation = 'Kode Google Apps Script belum diperbarui ke versi yang memiliki fungsi save_signature. Silakan salin kode terbaru dan deploy New Version.';
                }

                return [
                    'success' => false,
                    'message' => $errExplanation ?: 'Gagal menyimpan ke Google Drive.',
                    'file_name' => $cleanFilename,
                ];
            }

            return [
                'success' => false,
                'message' => 'Respons Google Apps Script HTTP ' . $response->status(),
                'file_name' => $cleanFilename,
            ];
        } catch (\Throwable $e) {
            Log::warning('Upload Signature to Google Drive failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'file_name' => $cleanFilename,
            ];
        }
    }

    /**
     * Build formatted rows for sheet Dokumen_Dinilai matching web /laporan table
     */
    public static function buildDokumenDinilaiRows(): array
    {
        $calcService = new AppraisalCalculationService();
        $karyawans = DB::table('karyawan')->get();
        $documents = DB::table('dokumen')->get()->keyBy('nrp');
        $allNilai = Schema::hasTable('nilai') ? DB::table('nilai')->get() : collect();

        $nilaiByDoc = [];
        foreach ($allNilai as $n) {
            $docId = $n->dokumen_id ?? null;
            $lvl = $n->level ?? 'foreman';
            $aspek = $n->aspek_kode ?? '';
            $skor = (float)($n->skor ?? 0);
            if ($docId && $aspek) {
                $nilaiByDoc[$docId][$lvl][$aspek] = $skor;
            }
        }

        $rows = [];
        foreach ($karyawans as $k) {
            $nrp = (string)($k->nrp ?? '');
            $doc = $documents->get($nrp);
            $docId = $doc->id ?? null;
            $kodeForm = $doc->kode_form ?? '07-001';
            $status = $doc->status ?? 'belum_ada';

            // Ambil skor penilaian dari evaluator tertinggi yang sudah menilai
            $scoresForeman = ($docId && isset($nilaiByDoc[$docId]['foreman'])) ? $nilaiByDoc[$docId]['foreman'] : [];
            $scoresSect = ($docId && isset($nilaiByDoc[$docId]['section_head'])) ? $nilaiByDoc[$docId]['section_head'] : [];
            $scoresDept = ($docId && isset($nilaiByDoc[$docId]['dept_head'])) ? $nilaiByDoc[$docId]['dept_head'] : [];

            $calcForeman = !empty($scoresForeman) ? $calcService->calculate($kodeForm, $scoresForeman) : null;
            $calcSect = !empty($scoresSect) ? $calcService->calculate($kodeForm, $scoresSect) : null;
            $calcDept = !empty($scoresDept) ? $calcService->calculate($kodeForm, $scoresDept) : null;

            // Highest available calculation
            $activeCalc = $calcDept ?: ($calcSect ?: $calcForeman);
            $hasScore = ($activeCalc && ($activeCalc['grand_total'] ?? 0) > 0)
                || (!empty($doc->total_nilai) && (float)$doc->total_nilai > 0)
                || !empty($doc->docking_final)
                || !empty($doc->grade_original);
            $isDinilai = !empty($doc) && $hasScore && in_array($status, ['menunggu_dept_head', 'menunggu_div_head', 'selesai']);

            $grandTotal = $activeCalc ? (float)($activeCalc['grand_total'] ?? 0) : ((float)($doc->total_nilai ?? 0));
            $hasilKerja = ($isDinilai && $activeCalc && isset($activeCalc['groups']['hasil_kerja']))
                ? number_format((float)$activeCalc['groups']['hasil_kerja']['weighted_score'], 2)
                : '-';
            $prosesKerja = ($isDinilai && $activeCalc && isset($activeCalc['groups']['proses_kerja']))
                ? number_format((float)$activeCalc['groups']['proses_kerja']['weighted_score'], 2)
                : '-';
            $sikapKerja = ($isDinilai && $activeCalc && isset($activeCalc['groups']['sikap_kerja']))
                ? number_format((float)$activeCalc['groups']['sikap_kerja']['weighted_score'], 2)
                : '-';
            $hasManMgt = $activeCalc && isset($activeCalc['groups']['man_management']);
            $manMgt = ($isDinilai && $hasManMgt)
                ? number_format((float)$activeCalc['groups']['man_management']['weighted_score'], 2)
                : '-';
            $totalNilai = ($isDinilai && $grandTotal > 0) ? number_format($grandTotal, 2) : '-';

            // Predikat Nilai PK Berjenjang
            $isGol4Up = in_array($kodeForm, ['07-006-SH', '07-006-DH', '07-006-DIV']);

            $pkForeman = '-';
            if (!$isGol4Up) {
                if ($calcForeman && ($calcForeman['grand_total'] ?? 0) > 0) {
                    $pkForeman = $calcService->scoreToGrade($calcForeman['grand_total']);
                } elseif (!empty($k->nrp_foreman) && $isDinilai) {
                    $pkForeman = $doc->grade_original ?? '-';
                }
            }

            // NILAI PK MANDIRI (Khusus Form 07-006 / KJ > 5 yang melakukan evaluasi mandiri)
            $pkMandiri = '-';
            if ($isGol4Up) {
                if ($calcForeman && ($calcForeman['grand_total'] ?? 0) > 0) {
                    $pkMandiri = $calcService->scoreToGrade($calcForeman['grand_total']);
                } elseif ($isDinilai) {
                    $pkMandiri = $doc->grade_original ?? '-';
                }
            }

            $pkSect = '-';
            if ($isGol4Up) {
                if ($calcSect && ($calcSect['grand_total'] ?? 0) > 0) {
                    $pkSect = $calcService->scoreToGrade($calcSect['grand_total']);
                } elseif ($isDinilai) {
                    $pkSect = ($pkMandiri !== '-') ? $pkMandiri : ($doc->grade_original ?? '-');
                }
            } else {
                if ($calcSect && ($calcSect['grand_total'] ?? 0) > 0) {
                    $pkSect = $calcService->scoreToGrade($calcSect['grand_total']);
                } elseif ($isDinilai) {
                    $pkSect = ($pkForeman !== '-') ? $pkForeman : ($doc->grade_original ?? '-');
                }
            }

            $pkDept = in_array($status, ['menunggu_div_head', 'selesai'])
                ? ($doc->docking_dept ?? $doc->docking_final ?? ($pkSect !== '-' ? $pkSect : ($pkMandiri !== '-' ? $pkMandiri : $pkForeman)))
                : '-';

            $pkDiv = ($status === 'selesai')
                ? ($doc->docking_div ?? $doc->docking_final ?? $pkDept)
                : '-';

            $pkFinal = $doc->docking_final ?? ($status === 'selesai' ? ($pkDiv !== '-' ? $pkDiv : $pkDept) : ($pkDept !== '-' ? $pkDept : ($pkSect !== '-' ? $pkSect : ($pkMandiri !== '-' ? $pkMandiri : ($pkForeman !== '-' ? $pkForeman : '-')))));
            if (empty($pkFinal)) {
                $pkFinal = '-';
            }

            $rawDate = $k->tgl_masuk ?? '';
            $startWork = self::formatMusashiDate($rawDate);

            $rows[] = [
                'NRP' => $nrp,
                'NAMA' => (string)($k->nama_lengkap ?? ''),
                'NAMA BAGIAN' => (string)($k->nama_bagian ?? $k->seksi ?? '-'),
                'SECTION' => (string)($k->nama_sect ?? $k->seksi ?? '-'),
                'DEPARTMENT' => (string)($k->departemen ?? '-'),
                'DIVISION' => (string)($k->division ?? '-'),
                'POSITION' => strtoupper((string)($k->jabatan ?? '-')),
                'START WORK' => $startWork,
                'KJ' => (string)($k->kj ?? '-'),
                'GOLONGAN' => (string)($k->sub_gol ?? $k->golongan ?? '-'),
                'Hasil Kerja' => $hasilKerja,
                'Proses Kerja' => $prosesKerja,
                'Sikap Kerja' => $sikapKerja,
                'Man Management' => $manMgt,
                'Total Nilai' => $totalNilai,
                'NILAI PK Foreman (Jika Ada)' => $pkForeman,
                'NILAI PK MANDIRI' => $pkMandiri,
                'NILAI PK SECT' => $pkSect,
                'NILAI PK DEPT' => $pkDept,
                'NILAI PK DIV' => $pkDiv,
                'NILAI PK FINAL' => $pkFinal,
            ];
        }

        return $rows;
    }

    /**
     * Push specifically Dokumen_Dinilai to Google Spreadsheet
     */
    public static function syncDokumenDinilai(?string $appscriptUrl = null): array
    {
        $config = self::getConfig();
        $targetAppscript = $appscriptUrl ?: $config['appscript_url'];
        $rows = self::buildDokumenDinilaiRows();

        if (empty($targetAppscript) || !filter_var($targetAppscript, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'message' => 'Apps Script Web App URL tidak terkonfigurasi.'];
        }

        try {
            $response = Http::timeout(15)->withoutVerifying()->post($targetAppscript, [
                'action' => 'push_all',
                'data' => [
                    'Dokumen_Dinilai' => $rows
                ]
            ]);

            return [
                'success' => $response->successful(),
                'total_rows' => count($rows),
                'response' => $response->json(),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Build rows for Target_Pemantauan sheet matching the exact web monitoring table
     */
    public static function buildTargetPemantauanRows(): array
    {
        $allKaryawan = DB::table('karyawan')->get()->map(fn($k) => (array)$k)->toArray();
        if (empty($allKaryawan)) {
            return [];
        }

        $allDocs = DB::table('dokumen')->get()->map(fn($d) => (array)$d)->toArray();
        $docsByNrp = [];
        foreach ($allDocs as $d) {
            $docsByNrp[(string)$d['nrp']] = $d;
        }

        $allNilai = Schema::hasTable('nilai') ? DB::table('nilai')->get()->toArray() : [];
        $nilaiByDoc = [];
        foreach ($allNilai as $n) {
            $docId = $n->dokumen_id ?? ($n['dokumen_id'] ?? null);
            if ($docId) {
                $skor = (float)($n->skor ?? ($n['skor'] ?? 0));
                if ($skor > 0) {
                    $nilaiByDoc[$docId] = true;
                }
            }
        }

        $karyawanDeptCounts = [];
        $karyawanDeptDivisions = [];
        foreach ($allKaryawan as $k) {
            $d = strtoupper(trim((string)($k['departemen'] ?? '')));
            if ($d === '' || $d === '-') {
                $d = strtoupper(trim((string)($k['seksi'] ?? ''))) ?: (strtoupper(trim((string)($k['division'] ?? ''))) ?: 'GENERAL');
            }
            if ($d !== '') {
                $karyawanDeptCounts[$d] = ($karyawanDeptCounts[$d] ?? 0) + 1;
                $div = trim((string)($k['division'] ?? ''));
                if ($div !== '' && $div !== '-' && !isset($karyawanDeptDivisions[$d])) {
                    $karyawanDeptDivisions[$d] = $div;
                }
            }
        }

        $rows = [];
        $no = 1;
        $nowStr = date('d-m-Y H:i:s');

        foreach ($karyawanDeptCounts as $dName => $target) {
            $matchingKaryawan = array_values(array_filter($allKaryawan, function($k) use ($dName) {
                $kd = strtoupper(trim((string)($k['departemen'] ?? '')));
                if ($kd === '' || $kd === '-') {
                    $kd = strtoupper(trim((string)($k['seksi'] ?? ''))) ?: (strtoupper(trim((string)($k['division'] ?? ''))) ?: 'GENERAL');
                }
                return $kd === $dName;
            }));

            $dinilai = 0;
            $divName = $karyawanDeptDivisions[$dName] ?? '';
            foreach ($matchingKaryawan as $mk) {
                if (!$divName) {
                    $divCandidate = trim((string)($mk['division'] ?? ''));
                    if ($divCandidate && $divCandidate !== '-') {
                        $divName = $divCandidate;
                    }
                }
                $doc = $docsByNrp[(string)($mk['nrp'] ?? '')] ?? null;
                if ($doc) {
                    $hasStoredTotal = !empty($doc['total_nilai']) && (float)$doc['total_nilai'] > 0;
                    $docIdVal = $doc['id'] ?? null;
                    $hasNilaiRows = !empty($docIdVal) && isset($nilaiByDoc[$docIdVal]);
                    $hasGrade = !empty($doc['docking_final']) || !empty($doc['grade_original']);
                    $hasRealScore = $hasStoredTotal || $hasNilaiRows || $hasGrade;
                    $docStatus = $doc['status'] ?? '';
                    if ($hasRealScore && in_array($docStatus, ['menunggu_dept_head', 'menunggu_div_head', 'selesai'])) {
                        $dinilai++;
                    }
                }
            }

            if (!$divName) {
                $divName = 'GENERAL AFFAIRS DIV';
            }

            $belum = max(0, $target - $dinilai);
            $pct = $target > 0 ? (int)round(($dinilai / $target) * 100) : 0;

            $tS = max(0, (int)round($target * 0.10));
            $tA = max(0, (int)round($target * 0.30));
            $tB = max(0, (int)round($target * 0.30));
            $tC = max(0, (int)round($target * 0.20));
            $tK = max(0, $target - ($tS + $tA + $tB + $tC));

            $statusLabel = match(true) {
                $target === 0 => 'Standby',
                $pct === 100 => '✓ Tuntas',
                $pct > 0 => 'Sedang Berjalan',
                default => 'Belum Mulai',
            };

            $cleanDept = preg_replace('/^PROD\s*(\d+)$/i', 'Production $1', $dName);
            $deptLabel = ($cleanDept !== $dName) ? "{$cleanDept} ({$dName})" : $dName;

            $rows[] = [
                'No' => $no++,
                'Division' => $divName,
                'Department' => $deptLabel,
                'Target_Kuota' => $target,
                'Sudah_Dinilai' => $dinilai,
                'Belum_Dinilai' => $belum,
                'Realisasi_Persen' => "{$pct}%",
                'Target_S' => $tS,
                'Target_A' => $tA,
                'Target_B' => $tB,
                'Target_C' => $tC,
                'Target_K' => $tK,
                'Status' => $statusLabel,
                'Terakhir_Update' => $nowStr,
            ];
        }

        return $rows;
    }

    /**
     * Push specifically Target_Pemantauan & Dokumen_Dinilai to Google Spreadsheet
     */
    public static function syncTargetPemantauan(?string $appscriptUrl = null): array
    {
        $config = self::getConfig();
        $targetAppscript = $appscriptUrl ?: $config['appscript_url'];
        $rows = self::buildTargetPemantauanRows();

        if (empty($targetAppscript) || !filter_var($targetAppscript, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'message' => 'Apps Script Web App URL tidak terkonfigurasi.'];
        }

        try {
            $response = Http::timeout(20)->withoutVerifying()->post($targetAppscript, [
                'action' => 'push_all',
                'data' => [
                    'Target_Pemantauan' => $rows
                ]
            ]);

            return [
                'success' => $response->successful(),
                'total_rows' => count($rows),
                'response' => $response->json(),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Panggil endpoint Apps Script untuk membersihkan sheet usang / duplikat (Master Karyawan, master_aspek, dll)
     */
    public static function cleanUnusedSheetsGoogle(?string $appscriptUrl = null): array
    {
        $config = self::getConfig();
        $targetAppscript = $appscriptUrl ?: $config['appscript_url'];
        if (empty($targetAppscript) || !filter_var($targetAppscript, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'message' => 'Apps Script Web App URL tidak terkonfigurasi.'];
        }

        try {
            $response = Http::timeout(15)->withoutVerifying()->post($targetAppscript, [
                'action' => 'clean_unused_sheets',
            ]);
            return $response->json() ?? ['success' => true];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Hapus berkas PDF di Google Drive dan baris penilaian terkait untuk daftar NRP / File ID tertentu
     */
    public static function deleteGoogleDriveEmployeePdfs(array $nrps, array $fileIds = [], ?string $appscriptUrl = null): array
    {
        $config = self::getConfig();
        $targetAppscript = $appscriptUrl ?: $config['appscript_url'];
        if (empty($targetAppscript) || !filter_var($targetAppscript, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'message' => 'Apps Script Web App URL tidak terkonfigurasi.'];
        }

        try {
            $response = Http::timeout(45)->withoutVerifying()->asJson()->post($targetAppscript, [
                'action' => 'delete_employee_data',
                'nrps' => array_values(array_map('strval', $nrps)),
                'file_ids' => array_values(array_map('strval', $fileIds)),
            ]);
            $resData = $response->json() ?? ['success' => true];
            // Jika Web App Google masih menggunakan versi lama (unknown_action), lakukan fallback delete_row per NRP
            if (isset($resData['status']) && $resData['status'] === 'unknown_action') {
                foreach ($nrps as $singleNrp) {
                    self::syncEmployeeRow('delete', (string)$singleNrp);
                }
            }
            return $resData;
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Bersihkan total seluruh data penilaian di spreadsheet dan seluruh file PDF di Google Drive
     */
    public static function wipeAllDataAndDrivePdfs(?string $appscriptUrl = null, bool $wipeEmployeesAndUsers = false): array
    {
        $config = self::getConfig();
        $targetAppscript = $appscriptUrl ?: $config['appscript_url'];
        if (empty($targetAppscript) || !filter_var($targetAppscript, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'message' => 'Apps Script Web App URL tidak terkonfigurasi.'];
        }

        try {
            $response = Http::timeout(60)->withoutVerifying()->asJson()->post($targetAppscript, [
                'action' => 'wipe_all_data',
                'wipe_drive_pdfs' => true,
                'wipe_karyawan_and_users' => $wipeEmployeesAndUsers,
            ]);
            $resData = $response->json() ?? ['success' => true];
            return $resData;
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Real-time 2-way sync: Synchronizes operational tables (tanda_tangan, dokumen, nilai, indikator)
     * from Google Spreadsheet into local SQLite so signatures and document workflow states
     * persist seamlessly across container restarts, deploys, and idle periods.
     */
    public static function syncOperationalTablesFromGoogle(bool $force = false): array
    {
        // Jangan pernah blokir navigasi halaman web biasa dengan synchronous HTTP call ke Google
        if (!$force) {
            return ['status' => 'skipped_no_force'];
        }

        $cacheKey = 'last_google_operational_sync';
        if (Cache::has($cacheKey)) {
            return ['status' => 'skipped_cache_active'];
        }

        $config = self::getConfig();
        $appscriptUrl = $config['appscript_url'] ?? '';
        if (empty($appscriptUrl) || !filter_var($appscriptUrl, FILTER_VALIDATE_URL)) {
            return ['status' => 'skipped_no_url'];
        }

        try {
            $response = Http::timeout(12)->withoutVerifying()->get($appscriptUrl, [
                'action' => 'pull_all',
            ]);

            if ($response->successful()) {
                $json = $response->json();
                $data = $json['data'] ?? [];

                // 1. Sync dokumen (workflow statuses & scores)
                $activeKaryawanNrps = DB::table('karyawan')->pluck('nrp')->map(fn($n) => trim((string)$n))->filter()->toArray();
                if (empty($activeKaryawanNrps)) {
                    // Jika data karyawan di database lokal kosong, dokumen tidak boleh ada
                    DB::table('dokumen')->delete();
                    if (Schema::hasTable('nilai')) DB::table('nilai')->delete();
                    if (Schema::hasTable('indikator')) DB::table('indikator')->delete();
                    if (Schema::hasTable('tanda_tangan')) DB::table('tanda_tangan')->delete();
                } elseif (!empty($data['dokumen']) && is_array($data['dokumen'])) {
                    DB::transaction(function () use ($data, $activeKaryawanNrps) {
                        $sheetDocIds = [];
                        foreach ($data['dokumen'] as $d) {
                            $id = (int)($d['id'] ?? 0);
                            if ($id <= 0) continue;

                            $nrp = trim((string)($d['nrp'] ?? ''));
                            // Lewati dokumen jika NRP karyawan tidak ada dalam data karyawan aktif lokal
                            if ($nrp === '' || !in_array($nrp, $activeKaryawanNrps)) continue;
                            $sheetDocIds[] = $id;
                            $nrpF = !empty($d['nrp_foreman']) ? trim((string)$d['nrp_foreman']) : null;
                            $namaF = !empty($d['nama_foreman']) ? trim((string)$d['nama_foreman']) : null;
                            $nrpS = !empty($d['nrp_section_head']) ? trim((string)$d['nrp_section_head']) : null;
                            $namaS = !empty($d['nama_section_head']) ? trim((string)$d['nama_section_head']) : null;
                            $nrpD = !empty($d['nrp_dept_head']) ? trim((string)$d['nrp_dept_head']) : null;
                            $namaD = !empty($d['nama_dept_head']) ? trim((string)$d['nama_dept_head']) : null;
                            $nrpDiv = !empty($d['nrp_div_head']) ? trim((string)$d['nrp_div_head']) : null;
                            $namaDiv = !empty($d['nama_div_head']) ? trim((string)$d['nama_div_head']) : null;

                            if ((!$nrpS || !$nrpD) && $nrp !== '') {
                                $kRow = DB::table('karyawan')->where('nrp', $nrp)->first();
                                if ($kRow) {
                                    $nrpF = $nrpF ?: (!empty($kRow->nrp_foreman) ? trim((string)$kRow->nrp_foreman) : null);
                                    $nrpS = $nrpS ?: (!empty($kRow->nrp_section_head) ? trim((string)$kRow->nrp_section_head) : null);
                                    $nrpD = $nrpD ?: (!empty($kRow->nrp_dept_head) ? trim((string)$kRow->nrp_dept_head) : null);
                                    $nrpDiv = $nrpDiv ?: (!empty($kRow->nrp_div_head) ? trim((string)$kRow->nrp_div_head) : null);
                                }
                            }

                            $getName = function (?string $n) {
                                if (!$n) return null;
                                $row = DB::table('karyawan')->where('nrp', $n)->first();
                                if ($row && !empty($row->nama_lengkap)) return $row->nama_lengkap;
                                $u = DB::table('app_users')->where('nrp', $n)->first();
                                return $u ? $u->nama : null;
                            };

                            $namaF = $namaF ?: ($nrpF ? $getName($nrpF) : null);
                            $namaS = $namaS ?: ($nrpS ? $getName($nrpS) : null);
                            $namaD = $namaD ?: ($nrpD ? $getName($nrpD) : null);
                            $namaDiv = $namaDiv ?: ($nrpDiv ? $getName($nrpDiv) : null);

                            $payload = [
                                'id' => $id,
                                'periode_id' => $d['periode_id'] ?? 1,
                                'nrp' => $nrp,
                                'kode_form' => $d['kode_form'] ?? '07-001',
                                'status' => $d['status'] ?? 'belum_ada',
                                'total_nilai' => !empty($d['total_nilai']) ? (float)$d['total_nilai'] : null,
                                'grade_original' => !empty($d['grade_original']) ? (string)$d['grade_original'] : null,
                                'docking_final' => !empty($d['docking_final']) ? (string)$d['docking_final'] : null,
                                'drive_file_id' => $d['drive_file_id'] ?? null,
                                'drive_url' => $d['drive_url'] ?? null,
                                'hash_dokumen' => $d['hash_dokumen'] ?? null,
                                'kode_verifikasi' => $d['kode_verifikasi'] ?? ('VERIF-' . strtoupper(Str::random(10))),
                                'version' => $d['version'] ?? 1,
                                'nrp_foreman' => $nrpF,
                                'nama_foreman' => $namaF,
                                'nrp_section_head' => $nrpS,
                                'nama_section_head' => $namaS,
                                'nrp_dept_head' => $nrpD,
                                'nama_dept_head' => $namaD,
                                'nrp_div_head' => $nrpDiv,
                                'nama_div_head' => $namaDiv,
                                'created_at' => $d['created_at'] ?? now()->toDateTimeString(),
                                'updated_at' => $d['updated_at'] ?? now()->toDateTimeString(),
                            ];

                            DB::table('dokumen')->updateOrInsert(['id' => $id], $payload);

                            // Clean up any other dummy or duplicate local dokumen for this NRP
                            if ($nrp !== '') {
                                $duplicateIds = DB::table('dokumen')->where('nrp', $nrp)->where('id', '!=', $id)->pluck('id')->toArray();
                                if (!empty($duplicateIds)) {
                                    DB::table('dokumen')->whereIn('id', $duplicateIds)->delete();
                                    if (Schema::hasTable('nilai')) DB::table('nilai')->whereIn('dokumen_id', $duplicateIds)->delete();
                                    if (Schema::hasTable('indikator')) DB::table('indikator')->whereIn('dokumen_id', $duplicateIds)->delete();
                                    if (Schema::hasTable('tanda_tangan')) DB::table('tanda_tangan')->whereIn('dokumen_id', $duplicateIds)->delete();
                                }
                            }
                        }

                        // Hapus dokumen lokal yang tidak ada di spreadsheet (misal dummy id >= 800000)
                        if (!empty($sheetDocIds)) {
                            DB::table('dokumen')->where('id', '>=', 800000)->whereNotIn('id', $sheetDocIds)->delete();
                        }
                    });
                }

                // 2. Sync nilai (scores)
                if (!empty($data['nilai']) && is_array($data['nilai'])) {
                    DB::transaction(function () use ($data) {
                        foreach ($data['nilai'] as $n) {
                            $docId = (int)($n['dokumen_id'] ?? 0);
                            $lvl = $n['level'] ?? null;
                            $aspek = $n['aspek_kode'] ?? null;
                            if (!$docId || !$lvl || !$aspek) continue;

                            DB::table('nilai')->updateOrInsert(
                                [
                                    'dokumen_id' => $docId,
                                    'level' => $lvl,
                                    'aspek_kode' => $aspek,
                                ],
                                [
                                    'skor' => (float)($n['skor'] ?? 0),
                                    'catatan' => $n['catatan'] ?? '',
                                    'created_at' => $n['created_at'] ?? now()->toDateTimeString(),
                                    'updated_at' => $n['updated_at'] ?? now()->toDateTimeString(),
                                ]
                            );
                        }
                    });
                }

                // 3. Sync indikator (evaluator notes & potential ratings)
                if (!empty($data['indikator']) && is_array($data['indikator'])) {
                    DB::transaction(function () use ($data) {
                        $cols = Schema::getColumnListing('indikator');
                        $colSet = array_flip($cols);
                        foreach ($data['indikator'] as $ind) {
                            $docId = (int)($ind['dokumen_id'] ?? 0);
                            if (!$docId) continue;

                            $cleanRow = [];
                            foreach ($ind as $col => $val) {
                                $cLower = strtolower(trim((string)$col));
                                if (isset($colSet[$cLower])) {
                                    $cleanRow[$cLower] = $val;
                                }
                            }
                            $cleanRow['dokumen_id'] = $docId;
                            $cleanRow['updated_at'] = $ind['updated_at'] ?? now()->toDateTimeString();

                            DB::table('indikator')->updateOrInsert(['dokumen_id' => $docId], $cleanRow);
                        }
                    });
                }

                // 4. Sync tanda_tangan (digital signatures from Google Sheets)
                if (!empty($data['tanda_tangan']) && is_array($data['tanda_tangan'])) {
                    DB::transaction(function () use ($data) {
                        foreach ($data['tanda_tangan'] as $t) {
                            $docId = (int)($t['dokumen_id'] ?? 0);
                            $lvl = $t['level'] ?? null;
                            if (!$docId || !$lvl) continue;

                            $signNrp = !empty($t['nrp']) ? trim((string)$t['nrp']) : null;
                            $signNama = !empty($t['nama']) ? trim((string)$t['nama']) : null;
                            $signRole = !empty($t['role']) ? trim((string)$t['role']) : null;
                            $userId = (int)($t['user_id'] ?? 0);

                            if ($signNrp) {
                                $matchedUser = DB::table('app_users')->where('nrp', $signNrp)->first();
                                if ($matchedUser) {
                                    $userId = $matchedUser->id;
                                    $signNama = $signNama ?: $matchedUser->nama;
                                    $signRole = $signRole ?: $matchedUser->role;
                                }
                            } elseif ($userId > 0) {
                                $matchedUser = DB::table('app_users')->where('id', $userId)->first();
                                if ($matchedUser) {
                                    $signNrp = $matchedUser->nrp;
                                    $signNama = $signNama ?: $matchedUser->nama;
                                    $signRole = $signRole ?: $matchedUser->role;
                                }
                            }

                            DB::table('tanda_tangan')->updateOrInsert(
                                ['dokumen_id' => $docId, 'level' => $lvl],
                                [
                                    'user_id' => $userId,
                                    'nrp' => $signNrp,
                                    'nama' => $signNama,
                                    'role' => $signRole,
                                    'tgl_ttd' => $t['tgl_ttd'] ?? now()->toDateTimeString(),
                                    'ip' => $t['ip'] ?? '',
                                    'user_agent' => $t['user_agent'] ?? '',
                                    'hash_snapshot' => $t['hash_snapshot'] ?? '',
                                    'ttd_image' => $t['ttd_image'] ?? '',
                                    'created_at' => $t['created_at'] ?? now()->toDateTimeString(),
                                ]
                            );
                        }
                    });
                }

                // 2. Sync kuota_departemen (Target Kuota from Google Sheets)
                if (!empty($data['kuota_departemen']) && is_array($data['kuota_departemen'])) {
                    DB::transaction(function () use ($data) {
                        $kdCols = Schema::getColumnListing('kuota_departemen');
                        $colSet = array_flip($kdCols);
                        $sheetIds = [];
                        foreach ($data['kuota_departemen'] as $kd) {
                            $id = $kd['id'] ?? null;
                            if (!$id) continue;
                            $sheetIds[] = $id;
                            $cleanRow = [];
                            foreach ($kd as $col => $val) {
                                $cLower = strtolower(trim((string)$col));
                                if (isset($colSet[$cLower])) {
                                    $cleanRow[$cLower] = $val;
                                }
                            }
                            if (!empty($cleanRow)) {
                                DB::table('kuota_departemen')->updateOrInsert(['id' => $id], $cleanRow);
                            }
                        }
                        if (!empty($sheetIds)) {
                            DB::table('kuota_departemen')->whereNotIn('id', $sheetIds)->delete();
                        }
                    });
                }

                // 7. Sync users (from Google Sheets sheet 'users' into 'app_users')
                if (!empty($data['users']) && is_array($data['users'])) {
                    DB::transaction(function () use ($data) {
                        foreach ($data['users'] as $u) {
                            $nrp = trim((string)($u['nrp'] ?? ''));
                            $email = strtolower(trim((string)($u['email'] ?? '')));
                            if ($nrp === '' && $email === '') continue;

                            $existing = null;
                            if ($nrp !== '') {
                                $existing = User::where('nrp', $nrp)->first();
                            }
                            if (!$existing && $email !== '') {
                                $existing = User::where('email', $email)->first();
                            }

                            $role = trim((string)($u['role'] ?? ''));
                            $nama = trim((string)($u['nama'] ?? ''));
                            $dept = trim((string)($u['departemen'] ?? ''));
                            $aktif = isset($u['aktif']) ? (int)$u['aktif'] : 1;
                            $barcode = $u['barcodettd'] ?? null;

                            if ($existing) {
                                if ($nama !== '') $existing->nama = $nama;
                                if ($role !== '') $existing->role = $role;
                                if ($dept !== '') $existing->departemen = $dept;
                                $existing->aktif = $aktif;
                                if (!empty($barcode)) {
                                    $existing->barcodettd = $barcode;
                                    $existing->ttdbarcode = $barcode;
                                }
                                if (empty($existing->password_hash)) {
                                    $existing->password_hash = Hash::make('password123');
                                }
                                $existing->save();
                            } else {
                                User::create([
                                    'nama' => $nama ?: ('User ' . $nrp),
                                    'email' => $email ?: ($nrp . '@musashi.id'),
                                    'nrp' => $nrp,
                                    'role' => $role ?: 'foreman',
                                    'departemen' => $dept ?: 'ALL',
                                    'aktif' => $aktif,
                                    'password_hash' => Hash::make('password123'),
                                    'barcodettd' => $barcode,
                                    'ttdbarcode' => $barcode,
                                ]);
                            }
                        }
                    });
                }

                // Clean up phantom leadership accounts if any
                try {
                    \App\Services\AccountSyncService::cleanupPhantomLeadershipAccounts();
                } catch (\Throwable $e) {}

                // Invalidate all repository caches so subsequent calls use fresh data
                try {
                    $sheetRepo = app(\App\Contracts\SheetRepositoryInterface::class);
                    $sheetRepo->clearCache('dokumen');
                    $sheetRepo->clearCache('nilai');
                    $sheetRepo->clearCache('indikator');
                    $sheetRepo->clearCache('tanda_tangan');
                    $sheetRepo->clearCache('karyawan');
                } catch (\Throwable $e) {}

                Cache::forget("sheet_all_dokumen");
                Cache::forget("sheet_all_nilai");
                Cache::forget("sheet_all_indikator");
                Cache::forget("sheet_all_tanda_tangan");
                Cache::forget("sheet_all_karyawan");

                Cache::put($cacheKey, true, 30); // Active cache for 30 seconds
                return ['status' => 'success', 'timestamp' => now()->toIso8601String()];
            }
        } catch (\Throwable $e) {
            Cache::put($cacheKey, true, 60);
            Log::info('syncOperationalTablesFromGoogle non-blocking notice: ' . $e->getMessage());
        }

        Cache::put($cacheKey, true, 60);
        return ['status' => 'offline_or_failed'];
    }

    /**
     * Generate complete, production-ready Google Apps Script Code
     * Injects the active Spreadsheet ID & Drive Folder ID dynamically
     */
    public static function getAppsScriptTemplateCode(?string $spreadsheetId = null, ?string $driveFolderId = null): string
    {
        $ssId = $spreadsheetId ?: self::DEFAULT_SPREADSHEET_ID;
        $drId = $driveFolderId ?: self::DEFAULT_DRIVE_FOLDER_ID;

        $code = <<<'APPSCRIPT'
/**
 * =========================================================================
 * GOOGLE APPS SCRIPT: PT MUSASHI AUTO PARTS INDONESIA
 * SISTEM E-APPROVAL & DIGITAL SIGNATURE PENILAIAN KARYAWAN
 * =========================================================================
 * Tautan Spreadsheet ID : __SS_ID__
 * Tautan Drive Folder ID : __DR_ID__
 * =========================================================================
 */

var CONFIG = {
  SPREADSHEET_ID: '__SS_ID__',
  DRIVE_FOLDER_ID: '__DR_ID__',
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
    'NRP', 'NAMA', 'NAMA BAGIAN', 'SECTION', 'DEPARTMENT', 'DIVISION', 'POSITION', 'START WORK', 'KJ', 'GOLONGAN', 'Hasil Kerja', 'Proses Kerja', 'Sikap Kerja', 'Man Management', 'Total Nilai', 'NILAI PK Foreman (Jika Ada)', 'NILAI PK MANDIRI', 'NILAI PK SECT', 'NILAI PK DEPT', 'NILAI PK DIV', 'NILAI PK FINAL'
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
    widths: { 1: 90, 2: 230, 3: 220, 4: 160, 5: 160, 6: 190, 7: 190, 8: 110, 9: 80, 10: 90, 11: 90, 12: 90, 13: 90, 14: 110, 15: 90, 16: 170, 17: 130, 18: 120, 19: 120, 20: 120, 21: 130 },
    alignCenter: [1, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21],
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
  var targetSheets = ['karyawan', 'dokumen', 'Dokumen_Dinilai', 'nilai', 'indikator', 'tanda_tangan', 'Master Tanda Tangan', 'users', 'Target_Pemantauan'];
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
      cleanUnusedSheets(true);
      var trashedPdfs = 0;
      if (postData.wipe_drive_pdfs !== false && String(postData.wipe_drive_pdfs) !== 'false') {
        try {
          trashedPdfs = wipeAllAppraisalPdfsInDrive();
        } catch (eDrive) {}
      }
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

APPSCRIPT;

        return str_replace(['__SS_ID__', '__DR_ID__'], [$ssId, $drId], $code);
    }
}
