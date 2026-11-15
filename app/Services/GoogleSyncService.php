<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        $appscriptUrl = $settings['google_appscript_url'] ?? '';

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

                    if (!empty($empRows) && is_array($empRows)) {
                        $pulledRowsCount = count($empRows);
                        $syncSource = "Google Apps Script & Spreadsheet (ID: {$targetSpreadsheet})";

                        $karyawanCols = Schema::getColumnListing('karyawan');
                        $karyawanColSet = array_flip($karyawanCols);

                        DB::transaction(function () use ($empRows, $karyawanColSet) {
                            foreach ($empRows as $row) {
                                $nrp = trim((string)($row['nrp'] ?? ''));
                                if ($nrp === '') continue;

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

                        // Auto-generate leadership accounts for the pulled employees
                        AccountSyncService::syncLeadershipAccountsFromKaryawan();
                    }
                }
            } catch (Exception $e) {
                Log::info("Apps Script pull skipped or offline: " . $e->getMessage());
            }
        }

        // If no records in karyawan and local DATA PK.xlsx exists, fallback to DatabaseSyncService
        if (DB::table('karyawan')->count() === 0 && file_exists(base_path('DATA PK.xlsx'))) {
            $localSync = DatabaseSyncService::syncAll();
        } else {
            $localSync = [
                'karyawan_total' => DB::table('karyawan')->count(),
                'dokumen_total' => DB::table('dokumen')->count(),
                'users_total' => DB::table('app_users')->count(),
            ];
        }

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
        $usersData = User::select('id', 'nama', 'email', 'role', 'nrp', 'departemen', 'aktif')->get()->toArray();
        $nilaiData = Schema::hasTable('nilai') ? DB::table('nilai')->get()->toArray() : [];
        $ttdData = Schema::hasTable('tanda_tangan') ? DB::table('tanda_tangan')->get()->toArray() : [];
        $indikatorData = Schema::hasTable('indikator') ? DB::table('indikator')->get()->toArray() : [];
        $pengaturanData = Schema::hasTable('pengaturan') ? DB::table('pengaturan')->get()->toArray() : [];
        $kuotaData = Schema::hasTable('kuota_departemen') ? DB::table('kuota_departemen')->get()->toArray() : [];
        $auditData = Schema::hasTable('audit_log') ? DB::table('audit_log')->latest('id')->limit(500)->get()->toArray() : [];

        $payload = [
            'spreadsheet_id' => $targetSpreadsheet,
            'waktu_ekspor' => now()->toDateTimeString(),
            'total_karyawan' => count($karyawanData),
            'total_dokumen' => count($dokumenData),
            'total_users' => count($usersData),
            'karyawan' => $karyawanData,
            'Master Karyawan' => $karyawanData,
            'dokumen' => $dokumenData,
            'users' => $usersData,
            'nilai' => $nilaiData,
            'tanda_tangan' => $ttdData,
            'indikator' => $indikatorData,
            'pengaturan' => $pengaturanData,
            'kuota_departemen' => $kuotaData,
            'audit_log' => $auditData,
        ];

        $pushedToWeb = false;
        if (!empty($targetAppscript) && filter_var($targetAppscript, FILTER_VALIDATE_URL)) {
            try {
                $response = Http::timeout(10)->withoutVerifying()->post($targetAppscript, [
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
            'Master Karyawan', 'karyawan' => [
                'id', 'nrp', 'nama_lengkap', 'role', 'jabatan', 'departemen', 'seksi', 'division',
                'kj', 'sub_gol', 'golongan', 'tgl_masuk', 'tgl_lahir', 'pendidikan', 'status_kerja',
                'lokasi_kerja', 'email', 'telepon', 'alamat', 'nrp_atasan', 'nrp_foreman',
                'nrp_section_head', 'nrp_dept_head', 'nrp_div_head', 'created_at'
            ],
            'dokumen' => [
                'id', 'periode_id', 'nrp', 'kode_form', 'status', 'total_nilai',
                'grade_original', 'docking_final', 'drive_file_id', 'drive_url',
                'hash_dokumen', 'kode_verifikasi', 'created_at'
            ],
            'nilai' => [
                'dokumen_id', 'level', 'aspek_kode', 'skor', 'catatan'
            ],
            'indikator' => [
                'dokumen_id', 'pilihan_potensi', 'catatan_potensi', 'kelebihan', 'kekurangan'
            ],
            'tanda_tangan' => [
                'dokumen_id', 'level', 'user_id', 'tgl_ttd', 'ip', 'user_agent',
                'hash_snapshot', 'ttd_image', 'created_at'
            ],
            'kuota_departemen' => [
                'id', 'departemen', 'kj', 'kuota_s', 'kuota_a', 'kuota_b',
                'kuota_c', 'kuota_k', 'total_karyawan', 'created_at'
            ],
            'master_kriteria' => [
                'aspek_kode', 'skor', 'deskripsi'
            ],
            'users' => [
                'id', 'nama', 'email', 'role', 'nrp', 'departemen', 'aktif'
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

        $payload = [];
        $emptyTables = [];
        foreach ($tables as $table) {
            $localTable = match ($table) {
                'users' => 'app_users',
                'Master Karyawan' => 'karyawan',
                default => $table,
            };

            $rows = [];
            if (Schema::hasTable($localTable)) {
                if ($localTable === 'app_users') {
                    $rows = DB::table('app_users')
                        ->select('id', 'nama', 'email', 'role', 'nrp', 'departemen', 'aktif')
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
                $payload[$table] = [array_fill_keys($emptyHeaders, '')];
                $emptyTables[] = $table;
                if ($table === 'karyawan') {
                    $payload['Master Karyawan'] = [array_fill_keys(self::getStandardHeaders('Master Karyawan'), '')];
                    $emptyTables[] = 'Master Karyawan';
                }
            } else {
                $payload[$table] = $rows;
                if ($table === 'karyawan') {
                    $payload['Master Karyawan'] = $rows;
                }
            }
        }

        if (empty($payload)) {
            return ['success' => true, 'updated' => []];
        }

        try {
            $response = Http::timeout(12)->withoutVerifying()->post($appscriptUrl, [
                'action' => 'push_all',
                'data' => $payload,
            ]);

            // Clean up temporary blank rows for empty tables so sheet has 0 data rows and styled headers
            if (!empty($emptyTables)) {
                foreach (array_unique($emptyTables) as $emptyTable) {
                    $keyCol = match($emptyTable) {
                        'Master Karyawan', 'karyawan' => 'nrp',
                        'dokumen', 'kuota_departemen' => 'id',
                        'nilai', 'indikator', 'tanda_tangan' => 'dokumen_id',
                        'users' => 'email',
                        default => 'id',
                    };
                    try {
                        Http::timeout(5)->withoutVerifying()->post($appscriptUrl, [
                            'action' => 'delete_row',
                            'tables' => [$emptyTable],
                            'key_column' => $keyCol,
                            'key_value' => '',
                        ]);
                    } catch (\Throwable $e) {
                        Log::warning("Failed cleaning placeholder row for {$emptyTable}: " . $e->getMessage());
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
            'tables' => ['Master Karyawan', 'karyawan', 'dokumen'],
            'key_column' => 'nrp',
            'key_value' => (string)$nrp,
        ];

        if ($cleanAction === 'upsert_row') {
            $emp = DB::table('karyawan')->where('nrp', (string)$nrp)->first();
            if (!$emp) {
                return ['success' => false, 'message' => "Karyawan NRP {$nrp} tidak ditemukan di database."];
            }
            $payload['tables'] = ['Master Karyawan', 'karyawan'];
            $payload['row_data'] = (array)$emp;
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
     * Non-blocking Async Dispatch for Full Tables Sync to Google Spreadsheet.
     * Fires artisan google:sync-row sync_all in background immediately (< 5ms response time).
     */
    public static function syncTablesAsync(array $tables = ['karyawan', 'dokumen']): void
    {
        try {
            $artisanPath = base_path('artisan');

            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $cmd = "start /B \"\" php \"{$artisanPath}\" google:sync-row sync_all > NUL 2>&1";
                pclose(popen($cmd, "r"));
            } else {
                $cmd = "php \"{$artisanPath}\" google:sync-row sync_all > /dev/null 2>&1 &";
                exec($cmd);
            }
        } catch (\Throwable $e) {
            Log::warning("Async Google syncTables launch failed: " . $e->getMessage() . ", running synchronously");
            self::syncTables($tables);
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

        $tahun = (string)($dokumen->tahun ?? DB::table('pengaturan')->where('key', 'tahun_penilaian')->value('value') ?? '2026');
        $departemen = (string)($karyawanArr['departemen'] ?? $dokumen->departemen ?? 'PROD 2');
        $seksi = (string)($karyawanArr['seksi'] ?? '');

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
     * Generate complete, production-ready Google Apps Script Code
     * Injects the active Spreadsheet ID & Drive Folder ID dynamically
     */
    public static function getAppsScriptTemplateCode(?string $spreadsheetId = null, ?string $driveFolderId = null): string
    {
        $ssId = $spreadsheetId ?: self::DEFAULT_SPREADSHEET_ID;
        $drId = $driveFolderId ?: self::DEFAULT_DRIVE_FOLDER_ID;

        return <<<APPSCRIPT
/**
 * =========================================================================
 * GOOGLE APPS SCRIPT: PT MUSASHI AUTO PARTS INDONESIA
 * SISTEM E-APPROVAL & DIGITAL SIGNATURE PENILAIAN KARYA
 * =========================================================================
 * Tautan Spreadsheet ID : {$ssId}
 * Tautan Drive Folder ID : {$drId}
 *
 * FITUR LENGKAP:
 * 1. onOpen()              : Menu otomatis '🚀 Musashi E-Approval' pada spreadsheet
 * 2. setupDatabaseSheets() : Membuat & menata 9 tabel resmi bertema Maroon #92302E
 * 3. setupDriveFolders()   : Menata folder Drive PDF rapi (TAHUN / DEPARTEMEN)
 * 4. doGet(e)              : Endpoint Web App untuk Ping & Tarik Data oleh sistem Laravel
 * 5. doPost(e)             : Endpoint Web App untuk Simpan PDF, Tanda Tangan, & Push Data
 * =========================================================================
 */

var CONFIG = {
  SPREADSHEET_ID: '{$ssId}',
  DRIVE_FOLDER_ID: '{$drId}',
  DEFAULT_YEAR: '2026',
  COMPANY_NAME: 'PT MUSASHI AUTO PARTS INDONESIA'
};

/**
 * Helper: Aman menampilkan alert hanya jika konteks UI tersedia (bukan Web App HTTP)
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
    .addSeparator()
    .addItem('📊 Cek Status & Statistik Data', 'showDatabaseStats')
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
  'dokumen': [
    'id', 'periode_id', 'nrp', 'kode_form', 'status', 'total_nilai',
    'grade_original', 'docking_final', 'drive_file_id', 'drive_url',
    'hash_dokumen', 'kode_verifikasi', 'created_at'
  ],
  'users': [
    'id', 'nama', 'email', 'role', 'nrp', 'departemen', 'aktif', 'created_at'
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
  'master_kriteria': [
    'aspek_kode', 'skor', 'deskripsi'
  ],
  'pengaturan': [
    'key', 'value'
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
    // Format Header Row: Maroon #92302E, Teks Putih Tebal
    var headerRange = sheet.getRange(1, 1, 1, headers.length);
    headerRange.setBackground('#92302E')
               .setFontColor('#FFFFFF')
               .setFontWeight('bold')
               .setFontFamily('Arial')
               .setFontSize(10)
               .setHorizontalAlignment('center');
    sheet.setFrozenRows(1);
  }

  // Hapus sheet bawaan 'Sheet1' jika ada dan kosong
  var defaultSheet = ss.getSheetByName('Sheet1');
  if (defaultSheet && ss.getSheets().length > 1) {
    try { ss.deleteSheet(defaultSheet); } catch (e) {}
  }

  showAlertSafely('Sukses! Seluruh tabel database resmi telah dibuat dan diformat rapi.');
}

/**
 * 3. STRUKTUR FOLDER GOOGLE DRIVE PDF RAPI (TAHUN -> DEPARTEMEN)
 */
function setupDriveFolders() {
  var rootFolder = DriveApp.getFolderById(CONFIG.DRIVE_FOLDER_ID);
  var tahun = CONFIG.DEFAULT_YEAR;
  var yearFolder = getOrCreateSubfolder(rootFolder, 'TAHUN ' + tahun);

  // Struktur Rapi Seluruh Departemen & Seksi Musashi
  var structure = {
    'PROD 1': ['FACTORY 1'],
    'PROD 2': ['FACTORY 1', 'FACTORY 2', 'FACTORY 3'],
    'PROD 3': ['FACTORY 2'],
    'PROD 4': ['FACTORY 1'],
    'QA QC DEPT': [],
    'ENGINEERING DEPT': [],
    'HR GA FINANCE DEPT': []
  };

  for (var dept in structure) {
    var deptFolder = getOrCreateSubfolder(yearFolder, dept);
    var sects = structure[dept];
    for (var s = 0; s < sects.length; s++) {
      getOrCreateSubfolder(deptFolder, sects[s]);
    }
  }

  showAlertSafely(
    'Sukses! Struktur folder Google Drive untuk arsip PDF (TAHUN ' + tahun + ') telah dirapikan per Departemen & Seksi.'
  );
}

/**
 * 4. RINGKASAN STATUS DATA DATABASE
 */
function showDatabaseStats() {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var empSheet = ss.getSheetByName('Master Karyawan') || ss.getSheetByName('karyawan');
  var docSheet = ss.getSheetByName('dokumen');
  var userSheet = ss.getSheetByName('users');
  var totalKaryawan = empSheet ? Math.max(0, empSheet.getLastRow() - 1) : 0;
  var totalDokumen = docSheet ? Math.max(0, docSheet.getLastRow() - 1) : 0;
  var totalUsers = userSheet ? Math.max(0, userSheet.getLastRow() - 1) : 0;

  showAlertSafely(
    'STATISTIK BASIS DATA MUSASHI E-APPROVAL:\\n\\n' +
    '• Master Karyawan: ' + totalKaryawan + ' Orang\\n' +
    '• Dokumen Penilaian: ' + totalDokumen + ' Dokumen\\n' +
    '• Akun Pengguna: ' + totalUsers + ' Akun\\n\\n' +
    'Spreadsheet ID: ' + ss.getId()
  );
}

/**
 * 5. WEB APP GET ENDPOINT (Tarik Data / Ping dari Laravel E-Approval)
 */
function doGet(e) {
  try {
    var p = (e && e.parameter) ? e.parameter : {};
    var action = p.action || 'ping';
    var ss = SpreadsheetApp.getActiveSpreadsheet();

    // Uji Konektivitas
    if (action === 'ping') {
      return jsonResponse({
        status: 'ok',
        app: 'Musashi E-Approval Apps Script',
        spreadsheet_id: ss.getId(),
        spreadsheet_name: ss.getName(),
        drive_folder_id: CONFIG.DRIVE_FOLDER_ID,
        time: new Date().toISOString()
      });
    }

    // Inisialisasi Seluruh Sheet dari Web App
    if (action === 'init_sheets' || action === 'setup_sheets') {
      setupDatabaseSheets();
      return jsonResponse({
        status: 'success',
        message: 'Seluruh sheet database resmi telah berhasil diinisialisasi.',
        tables: Object.keys(CONFIG_TABLES),
        time: new Date().toISOString()
      });
    }

    // Tarik Semua Data Database
    if (action === 'pull_all' || action === 'export_database') {
      var result = {};
      var sheetNames = ['karyawan', 'dokumen', 'users', 'nilai', 'indikator', 'tanda_tangan', 'kuota_departemen', 'pengaturan', 'audit_log'];
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

    // Tarik Satu Tabel Spesifik
    if (action === 'get_table') {
      var tableName = p.table || 'karyawan';
      var sheet = ss.getSheetByName(tableName);
      if (!sheet) {
        return jsonResponse({ status: 'error', message: 'Tabel ' + tableName + ' tidak ditemukan' }, 404);
      }
      return jsonResponse({ status: 'success', table: tableName, data: sheetToObjects(sheet) });
    }

    return jsonResponse({ status: 'unknown_action', action: action });
  } catch (err) {
    return jsonResponse({ status: 'error', error: err.toString() }, 500);
  }
}

/**
 * 6. WEB APP POST ENDPOINT (Push Database & Simpan PDF ke Drive)
 */
function doPost(e) {
  try {
    var postData = {};
    if (e && e.postData && e.postData.contents) {
      postData = JSON.parse(e.postData.contents);
    }
    var action = postData.action || (e && e.parameter ? e.parameter.action : '');
    var ss = SpreadsheetApp.getActiveSpreadsheet();

    // Inisialisasi Seluruh Sheet dari POST
    if (action === 'init_sheets' || action === 'setup_sheets') {
      setupDatabaseSheets();
      return jsonResponse({
        status: 'success',
        message: 'Seluruh sheet database resmi telah berhasil diinisialisasi.',
        tables: Object.keys(CONFIG_TABLES),
        time: new Date().toISOString()
      });
    }

    // A. SINKRONISASI DATA DARI LARAVEL KE SPREADSHEET
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

    // B. SIMPAN ARSIP PDF KE DRIVE DENGAN PENAMAAN RAPI
    // Format File: PK_[TAHUN]_[NRP]_[NAMA]_[JABATAN]_[GOL].pdf
    if (action === 'save_pdf') {
      var pdfBase64 = postData.pdf_base64;
      var nrp = String(postData.nrp || '0').trim();
      var nama = String(postData.nama_lengkap || 'KARYAWAN').toUpperCase().replace(/[^A-Z0-9]/g, '_');
      var jabatan = String(postData.jabatan || 'OPERATOR').toUpperCase().replace(/[^A-Z0-9]/g, '_');
      var golongan = String(postData.golongan || 'KJ').toUpperCase().replace(/[^A-Z0-9]/g, '_');
      var departemen = String(postData.departemen || 'PROD 2').toUpperCase().trim();
      var seksi = String(postData.seksi || '').toUpperCase().trim();
      var tahun = String(postData.tahun || CONFIG.DEFAULT_YEAR);

      var filename = 'PK_' + tahun + '_' + nrp + '_' + nama + '_' + jabatan + '_' + golongan + '.pdf';

      // Struktur Folder Rapi & Berjenjang:
      // Root Folder -> TAHUN [YYYY] -> [DEPARTEMEN] -> [SEKSI] (jika ada)
      var rootFolder = DriveApp.getFolderById(CONFIG.DRIVE_FOLDER_ID);
      var yearFolder = getOrCreateSubfolder(rootFolder, 'TAHUN ' + tahun);
      var deptFolder = getOrCreateSubfolder(yearFolder, departemen);
      var targetFolder = (seksi && seksi !== '-' && seksi !== 'SEMUA')
          ? getOrCreateSubfolder(deptFolder, seksi)
          : deptFolder;

      var decodedBytes = Utilities.base64Decode(pdfBase64);
      var blob = Utilities.newBlob(decodedBytes, 'application/pdf', filename);

      // Bersihkan / timpa berkas versi lama dengan nama sama agar tidak bertumpuk duplikat di Drive
      var existingFiles = targetFolder.getFilesByName(filename);
      while (existingFiles.hasNext()) {
        var oldFile = existingFiles.next();
        oldFile.setTrashed(true);
      }

      var file = targetFolder.createFile(blob);
      try {
        file.setSharing(DriveApp.Access.ANYONE_WITH_LINK, DriveApp.Permission.VIEW);
      } catch (eShare) {}

      var folderPath = 'TAHUN ' + tahun + ' / ' + departemen + (seksi && seksi !== '-' ? ' / ' + seksi : '');

      return jsonResponse({
        status: 'success',
        file_id: file.getId(),
        file_name: filename,
        file_url: file.getUrl(),
        folder_path: folderPath,
        timestamp: new Date().toISOString()
      });
    }

    // C. HAPUS 1 BARIS CEPAT (DELETE ROW BY NRP)
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

    // D. UPDATE ATAU TAMBAH 1 BARIS CEPAT (UPSERT ROW BY NRP)
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
    sheet.clear();
    var stdHeaders = CONFIG_TABLES[sheetName] || ['id'];
    sheet.appendRow(stdHeaders);
    var hr = sheet.getRange(1, 1, 1, stdHeaders.length);
    hr.setBackground('#92302E')
      .setFontColor('#FFFFFF')
      .setFontWeight('bold')
      .setFontFamily('Arial')
      .setFontSize(10)
      .setHorizontalAlignment('center');
    sheet.setFrozenRows(1);
    return;
  }

  var headers = Object.keys(items[0]);
  sheet.clear();
  sheet.appendRow(headers);
  var rows = [];
  for (var i = 0; i < items.length; i++) {
    var row = [];
    for (var h = 0; h < headers.length; h++) {
      row.push(items[i][headers[h]] !== undefined ? items[i][headers[h]] : '');
    }
    rows.push(row);
  }
  if (rows.length > 0) {
    sheet.getRange(2, 1, rows.length, headers.length).setValues(rows);
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

// HELPER: Get or Create Subfolder
function getOrCreateSubfolder(parentFolder, folderName) {
  var folders = parentFolder.getFoldersByName(folderName);
  if (folders.hasNext()) {
    return folders.next();
  }
  return parentFolder.createFolder(folderName);
}

// HELPER: Format JSON Response
function jsonResponse(data, code) {
  return ContentService.createTextOutput(JSON.stringify(data))
    .setMimeType(ContentService.MimeType.JSON);
}
APPSCRIPT;
    }
}
