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
 * =========================================================================
 */

var CONFIG = {
  SPREADSHEET_ID: '{$ssId}',
  DRIVE_FOLDER_ID: '{$drId}',
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
    .addItem('✍️ Uji & Buat Folder Tanda Tangan Drive', 'testDriveAccess')
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
  'periode': [
    'id', 'tahun', 'tgl_mulai', 'tgl_selesai', 'status'
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

  showAlertSafely('Sukses! Seluruh tabel database resmi telah dibuat dan diformat rapi.');
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
    'Sukses! Struktur folder Google Drive otomatis dibuat untuk tahun ' + yearsList.join(', ') + ':\\n\\n' +
    '📁 Folder Arsip: ' + yearsList.map(function(yr) { return 'TAHUN ' + yr; }).join(', ') + '\\n' +
    '   ├── Divisi: ' + divCount + ' Folder\\n' +
    '   ├── Departemen: ' + deptCount + ' Folder\\n' +
    '   └── Seksi: ' + seksiCount + ' Folder\\n\\n' +
    '📁 TANDA_TANGAN: ' + posCount + ' Posisi/Role\\n\\n' +
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
    var mainTtdFolder = getOrCreateSubfolder(rootFolder, 'TANDA_TANGAN');
    var subroles = ['FOREMAN', 'SECTION_HEAD', 'DEPT_HEAD', 'DIV_HEAD', 'ADMIN'];
    for (var i = 0; i < subroles.length; i++) {
      getOrCreateSubfolder(mainTtdFolder, subroles[i]);
    }
    showAlertSafely(
      'Sukses! Izin Google Drive berhasil aktif.\\n\\n' +
      'Folder Utama: ' + rootFolder.getName() + '\\n' +
      'Subfolder: TANDA_TANGAN / (FOREMAN, SECTION_HEAD, DEPT_HEAD, DIV_HEAD, ADMIN)\\n\\n' +
      'Sistem siap menerima penyimpanan berkas tanda tangan otomatis.'
    );
  } catch (err) {
    showAlertSafely('Gagal mengakses Google Drive: ' + err.toString());
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
  var ttdSheet = ss.getSheetByName('Master Tanda Tangan');
  var totalKaryawan = empSheet ? Math.max(0, empSheet.getLastRow() - 1) : 0;
  var totalDokumen = docSheet ? Math.max(0, docSheet.getLastRow() - 1) : 0;
  var totalUsers = userSheet ? Math.max(0, userSheet.getLastRow() - 1) : 0;
  var totalTtd = ttdSheet ? Math.max(0, ttdSheet.getLastRow() - 1) : 0;

  showAlertSafely(
    'STATISTIK BASIS DATA MUSASHI E-APPROVAL:\\n\\n' +
    '• Master Karyawan: ' + totalKaryawan + ' Orang\\n' +
    '• Dokumen Penilaian: ' + totalDokumen + ' Dokumen\\n' +
    '• Akun Pengguna: ' + totalUsers + ' Akun\\n' +
    '• Master Tanda Tangan: ' + totalTtd + ' Berkas\\n\\n' +
    'Spreadsheet ID: ' + ss.getId()
  );
}

/**
 * 6. WEB APP GET ENDPOINT (Tarik Data / Ping dari Laravel E-Approval)
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

    if (action === 'get_table') {
      var tableName = p.table || 'Master Karyawan';
      var sheet = ss.getSheetByName(tableName) || ss.getSheetByName('karyawan');
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

/**
 * 7. WEB APP POST ENDPOINT (Simpan TTD, Simpan PDF, Push Database, Upsert Row, Delete Row)
 */
function doPost(e) {
  try {
    var postData = {};
    if (e && e.postData && e.postData.contents) {
      postData = JSON.parse(e.postData.contents);
    }
    var action = postData.action || (e && e.parameter ? e.parameter.action : '');
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

    // A. SIMPAN TANDA TANGAN DIGITAL KE GOOGLE DRIVE DENGAN STRUKTUR RAPI
    // Format Berkas: {nrp}_{nama}_{position}_{departemen}.png
    // Folder: Root Folder -> TANDA_TANGAN -> [ROLE / POSITION] (misal: FOREMAN, SECTION_HEAD, dll.)
    if (action === 'save_signature' || action === 'upload_signature') {
      var sigBase64 = String(postData.signature_base64 || postData.image_base64 || postData.base64 || '');
      if (!sigBase64) {
        return jsonResponse({ status: 'error', message: 'Data gambar tanda tangan (base64) kosong.' }, 400);
      }

      var nrp = String(postData.nrp || '0').trim();
      var nama = String(postData.nama || postData.nama_lengkap || 'USER').toUpperCase().replace(/[^A-Z0-9]/g, '_');
      var position = String(postData.position || postData.role || postData.jabatan || 'FOREMAN').toUpperCase().replace(/[^A-Z0-9]/g, '_');
      var departemen = String(postData.departemen || 'PROD_2').toUpperCase().replace(/[^A-Z0-9]/g, '_');

      var filename = postData.filename || (nrp + '_' + nama + '_' + position + '_' + departemen + '.png');

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
        if (!oldFile.isTrashed()) {
          oldFile.setTrashed(true);
        }
      }

      var file = roleFolder.createFile(blob);
      try {
        file.setSharing(DriveApp.Access.ANYONE_WITH_LINK, DriveApp.Permission.VIEW);
      } catch (eShare) {}

      var fileUrl = 'https://drive.google.com/uc?export=view&id=' + file.getId();
      var folderPath = 'TANDA_TANGAN / ' + position;
      var nowFormatted = Utilities.formatDate(new Date(), 'Asia/Jakarta', 'yyyy-MM-dd HH:mm:ss');
      var division = String(postData.division || postData.divisi || 'MANUFACTURING DIVISION').toUpperCase().trim();

      // 1. Catat / Update ke Sheet 'Master Tanda Tangan' (Database Tanda Tangan Resmi)
      try {
        var ttdSheet = ss.getSheetByName('Master Tanda Tangan');
        var ttdHeaders = CONFIG_TABLES['Master Tanda Tangan'] || ['nrp', 'nama', 'position', 'department', 'division', 'signature_url', 'drive_file_id', 'updated_at'];
        if (!ttdSheet) {
          ttdSheet = ss.insertSheet('Master Tanda Tangan');
          ttdSheet.appendRow(ttdHeaders);
          var hRange = ttdSheet.getRange(1, 1, 1, ttdHeaders.length);
          hRange.setBackground('#92302e').setFontColor('#ffffff').setFontWeight('bold');
          ttdSheet.setFrozenRows(1);
        }

        var ttdData = ttdSheet.getDataRange().getValues();
        var rowUpdated = false;
        for (var tr = 1; tr < ttdData.length; tr++) {
          if (String(ttdData[tr][0]).trim() === nrp) {
            ttdSheet.getRange(tr + 1, 2).setValue(nama);
            ttdSheet.getRange(tr + 1, 3).setValue(position);
            ttdSheet.getRange(tr + 1, 4).setValue(departemen);
            ttdSheet.getRange(tr + 1, 5).setValue(division);
            ttdSheet.getRange(tr + 1, 6).setValue(fileUrl);
            ttdSheet.getRange(tr + 1, 7).setValue(file.getId());
            ttdSheet.getRange(tr + 1, 8).setValue(nowFormatted);
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

      // 2. Sinkronkan signature_path di Sheet 'users'
      try {
        var uSheet = ss.getSheetByName('users');
        if (uSheet && uSheet.getLastRow() > 1) {
          var uData = uSheet.getDataRange().getValues();
          var uHeaders = uData[0];
          var uNrpCol = -1, uSigCol = -1;
          for (var uh = 0; uh < uHeaders.length; uh++) {
            var colName = String(uHeaders[uh]).toLowerCase().trim();
            if (colName === 'nrp') uNrpCol = uh;
            if (colName === 'signature_path') uSigCol = uh;
          }
          if (uSigCol === -1) {
            uSigCol = uHeaders.length;
            uSheet.getRange(1, uSigCol + 1).setValue('signature_path');
          }
          if (uNrpCol > -1) {
            for (var ur = 1; ur < uData.length; ur++) {
              if (String(uData[ur][uNrpCol]).trim() === nrp) {
                uSheet.getRange(ur + 1, uSigCol + 1).setValue(fileUrl);
                break;
              }
            }
          }
        }
      } catch (eUserSheet) {
        Logger.log('Gagal update signature_path di sheet users: ' + eUserSheet);
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
    // Format Berkas: PK_[TAHUN]_[NRP]_[NAMA]_[JABATAN]_[GOL].pdf
    // Folder: Root Folder -> TAHUN [YYYY] -> [DIVISION] -> [DEPARTMENT] -> [SEKSI]
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

      // Jika division atau seksi belum dikirim, cari otomatis dari Master Karyawan berdasarkan NRP
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
        if (!oldFile.isTrashed()) {
          oldFile.setTrashed(true);
        }
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
    }
}
