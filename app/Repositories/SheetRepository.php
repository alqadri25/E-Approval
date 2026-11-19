<?php

namespace App\Repositories;

use App\Contracts\SheetRepositoryInterface;
use App\Services\GoogleClientService;
use App\Services\GoogleSyncService;
use Google\Service\Sheets\ValueRange;
use Google\Service\Sheets\BatchUpdateSpreadsheetRequest;
use Google\Service\Sheets\Request as GoogleSheetRequest;
use Google\Service\Sheets\AddSheetRequest;
use Google\Service\Sheets\SheetProperties;
use Google\Service\Sheets\DeleteDimensionRequest;
use Google\Service\Sheets\DimensionRange;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Exception;

class SheetRepository implements SheetRepositoryInterface
{
    protected GoogleClientService $googleService;

    /**
     * Exact table definitions matching specification
     */
    public const SCHEMAS = [
        'users' => [
            'id', 'nama', 'email', 'password_hash', 'role', 'nrp', 'departemen', 'aktif', 'barcodettd', 'signature_path_link', 'created_at'
        ],
        'karyawan' => [
            'nrp', 'nama_lengkap', 'golongan', 'sub_gol', 'kj', 'jabatan', 'tgl_masuk', 'seksi', 'departemen', 'level_form', 'nrp_foreman', 'nrp_section_head', 'nrp_dept_head', 'nrp_div_head', 'code_bagian', 'nama_bagian', 'nama_sect', 'line_category', 'division'
        ],
        'periode' => [
            'id', 'tahun', 'tgl_mulai', 'tgl_selesai', 'status'
        ],
        'dokumen' => [
            'id', 'periode_id', 'nrp', 'kode_form', 'status', 'total_nilai', 'grade_original', 'docking_final', 'drive_file_id', 'drive_url', 'hash_dokumen', 'kode_verifikasi', 'created_at',
            'nrp_foreman', 'nama_foreman', 'nrp_section_head', 'nama_section_head', 'nrp_dept_head', 'nama_dept_head', 'nrp_div_head', 'nama_div_head'
        ],
        'nilai' => [
            'dokumen_id', 'level', 'aspek_kode', 'skor', 'catatan'
        ],
        'indikator' => [
            'dokumen_id', 'pilihan_potensi', 'catatan_potensi', 'kelebihan', 'kekurangan'
        ],
        'tanda_tangan' => [
            'dokumen_id', 'level', 'user_id', 'tgl_ttd', 'ip', 'user_agent', 'hash_snapshot', 'ttd_image', 'created_at', 'nrp', 'nama', 'role'
        ],
        'master_aspek' => [
            'kode_form', 'grup', 'aspek_kode', 'nama', 'bobot_grup', 'urutan'
        ],
        'master_kriteria' => [
            'aspek_kode', 'skor', 'deskripsi'
        ],
        'kuota_departemen' => [
            'id', 'departemen', 'grade_s_max', 'grade_a_max', 'grade_b_max', 'grade_c_max', 'grade_k_max'
        ],
        'Dokumen_Dinilai' => [
            'NRP', 'NAMA', 'NAMA BAGIAN', 'SECTION', 'DEPARTMENT', 'DIVISION', 'POSITION', 'START WORK', 'KJ', 'GOLONGAN', 'Hasil Kerja', 'Proses Kerja', 'Sikap Kerja', 'Man Management', 'Total Nilai', 'NILAI PK Foreman (Jika Ada)', 'NILAI PK SECT', 'NILAI PK DEPT', 'NILAI PK DIV', 'NILAI PK FINAL'
        ],
        'audit_log' => [
            'waktu', 'user_id', 'aksi', 'dokumen_id', 'detail'
        ],
        'pengaturan' => [
            'key', 'value'
        ],
    ];

    public function __construct(GoogleClientService $googleService)
    {
        $this->googleService = $googleService;
    }

    protected static bool $syncEnabled = true;

    public static function withoutSync(callable $callback)
    {
        $previous = static::$syncEnabled;
        static::$syncEnabled = false;
        try {
            return $callback();
        } finally {
            static::$syncEnabled = $previous;
        }
    }

    public function isSyncEnabled(): bool
    {
        return static::$syncEnabled;
    }

    public function isOnline(): bool
    {
        return $this->googleService->isConfigured() || $this->hasAppscript();
    }

    public function hasAppscript(): bool
    {
        if (app()->runningUnitTests()) {
            return false;
        }

        $config = GoogleSyncService::getConfig();
        return !empty($config['appscript_url']) && filter_var($config['appscript_url'], FILTER_VALIDATE_URL);
    }

    /**
     * Map sheet table name to local DB table name
     */
    protected function getLocalTableName(string $table): string
    {
        return $table === 'users' ? 'app_users' : $table;
    }

    /**
     * Fetch all records with caching (Local SQLite First)
     */
    public function all(string $table, ?int $cacheTtl = 3600): array
    {
        $cacheKey = "sheet_all_{$table}";
        $cacheTtl = $cacheTtl ?? config('google.cache_ttl', 3600);

        return Cache::remember($cacheKey, $cacheTtl, function () use ($table) {
            $localTable = $this->getLocalTableName($table);

            // 1. FAST LOCAL-FIRST: Check local SQLite database first (< 1ms)
            if (Schema::hasTable($localTable)) {
                $localRows = $this->getFromLocal($table);
                if (!empty($localRows)) {
                    return $localRows;
                }

                // If local table exists and database is initialized (settings/users/periode exist),
                // an empty table genuinely has 0 records (e.g. intentionally deleted by user).
                // Do NOT query Google Apps Script and resurrect deleted data!
                $isInitialized = DB::table('pengaturan')->exists()
                    || DB::table('app_users')->exists()
                    || DB::table('periode')->exists();

                if ($isInitialized) {
                    return [];
                }
            }

            // 2. Fetch fresh data from Google Apps Script Web App only if local DB is empty
            $appscriptTables = [
                'karyawan', 'dokumen', 'users', 'kuota_departemen', 'nilai', 'indikator', 'tanda_tangan', 'pengaturan', 'audit_log'
            ];

            if ($this->hasAppscript() && in_array($table, $appscriptTables)) {
                try {
                    $config = GoogleSyncService::getConfig();
                    $appscriptUrl = $config['appscript_url'] ?? '';

                    $tableVariants = [$table];
                    if ($table === 'karyawan') {
                        $tableVariants = ['karyawan', 'Master Karyawan'];
                    }

                    foreach ($tableVariants as $sheetTable) {
                        $response = \Illuminate\Support\Facades\Http::timeout(5)->withoutVerifying()->get($appscriptUrl, [
                            'action' => 'get_table',
                            'table' => $sheetTable,
                        ]);

                        if ($response->successful()) {
                            $json = $response->json();
                            if (isset($json['status']) && $json['status'] === 'success' && isset($json['data']) && is_array($json['data'])) {
                                $rows = $json['data'];
                                if (!empty($rows)) {
                                    $this->mirrorToLocal($table, $rows);
                                    return $rows;
                                }
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning("Apps Script read failed for table '{$table}': " . $e->getMessage() . ". Falling back to local database.");
                }
            }

            // 3. Direct Google Sheets API if service account configured and local is empty
            if ($this->googleService->isConfigured()) {
                try {
                    $sheets = $this->googleService->getSheetsService();
                    $spreadsheetId = $this->googleService->getSpreadsheetId();
                    $range = "{$table}!A1:Z";

                    $response = $sheets->spreadsheets_values->get($spreadsheetId, $range);
                    $values = $response->getValues();

                    if (!empty($values) && count($values) > 1) {
                        $headers = array_map('trim', $values[0]);
                        $rows = [];

                        for ($i = 1; $i < count($values); $i++) {
                            $row = $values[$i];
                            $record = [];
                            foreach ($headers as $colIdx => $header) {
                                $record[$header] = $row[$colIdx] ?? null;
                            }
                            $record['_row_index'] = $i + 1; // Google Sheets 1-based index
                            $rows[] = $record;
                        }

                        $this->mirrorToLocal($table, $rows);
                        return $rows;
                    }
                } catch (\Throwable $e) {
                    Log::warning("Google Sheets read failed for table '{$table}': " . $e->getMessage() . ". Falling back to local database.");
                }
            }

            // 4. Return local DB (even if empty)
            if (Schema::hasTable($localTable)) {
                return $this->getFromLocal($table);
            }

            return [];
        });
    }

    /**
     * Find single record by column (Local SQLite First)
     */
    public function find(string $table, string $column, $value): ?array
    {
        // 1. FAST PATH: Direct query to local database (indexed, sub-millisecond)
        $localTable = $this->getLocalTableName($table);
        if (Schema::hasTable($localTable)) {
            $row = DB::table($localTable)->where($column, $value)->first();
            if ($row) {
                return (array)$row;
            }

            // If the local table exists and database is initialized,
            // the record genuinely does not exist! Do NOT make blocking network calls or resurrect via all().
            $isInitialized = DB::table('pengaturan')->exists()
                || DB::table('app_users')->exists()
                || DB::table('periode')->exists();

            if ($isInitialized) {
                return null;
            }
        }

        // 2. Secondary check via all() only if table does not exist or has 0 rows
        $records = $this->all($table);
        foreach ($records as $record) {
            if (isset($record[$column]) && (string)$record[$column] === (string)$value) {
                return $record;
            }
        }

        return null;
    }

    /**
     * Filter records matching multiple criteria (Local SQLite First)
     */
    public function where(string $table, array $criteria): array
    {
        // 1. FAST PATH: Direct query to local database with WHERE filters
        $localTable = $this->getLocalTableName($table);
        if (Schema::hasTable($localTable)) {
            $query = DB::table($localTable);
            foreach ($criteria as $col => $val) {
                $query->where($col, $val);
            }
            // Return local database results immediately (even if empty array [])
            return $query->get()->map(fn($r) => (array)$r)->toArray();
        }

        // 2. Fallback to all() only if schema table does not exist
        $records = $this->all($table);
        return array_values(array_filter($records, function ($record) use ($criteria) {
            foreach ($criteria as $col => $val) {
                if (!isset($record[$col]) || (string)$record[$col] !== (string)$val) {
                    return false;
                }
            }
            return true;
        }));
    }

    /**
     * Insert a single row with Cache::lock concurrency protection
     */
    public function insert(string $table, array $data): array
    {
        // 1. If sync is disabled, insert local DB directly without locking or network overhead
        if (!$this->isSyncEnabled()) {
            $localTable = $this->getLocalTableName($table);
            if (Schema::hasTable($localTable)) {
                $columns = self::SCHEMAS[$table] ?? array_keys($data);
                $insertPayload = [];
                foreach ($columns as $col) {
                    if (Schema::hasColumn($localTable, $col) && array_key_exists($col, $data)) {
                        $insertPayload[$col] = $data[$col];
                    }
                }
                $keyCol = match ($table) {
                    'pengaturan' => 'key',
                    'karyawan' => 'nrp',
                    default => 'id',
                };
                if (isset($data[$keyCol]) && Schema::hasColumn($localTable, $keyCol)) {
                    DB::table($localTable)->updateOrInsert([$keyCol => $data[$keyCol]], $insertPayload);
                } else {
                    $newId = DB::table($localTable)->insertGetId($insertPayload);
                    $data['id'] = $newId;
                }
            }
            return $data;
        }

        $lockKey = "sheet_lock_{$table}";

        try {
            return Cache::lock($lockKey, 10)->block(3, function () use ($table, $data) {
                $columns = self::SCHEMAS[$table] ?? array_keys($data);

                // Auto-increment ID if missing and needed
                if (in_array('id', $columns) && empty($data['id'])) {
                    $localTable = $this->getLocalTableName($table);
                    if (Schema::hasTable($localTable)) {
                        $maxId = (int)DB::table($localTable)->max('id');
                    } else {
                        $all = $this->all($table);
                        $maxId = 0;
                        foreach ($all as $item) {
                            if (isset($item['id']) && is_numeric($item['id'])) {
                                $maxId = max($maxId, (int)$item['id']);
                            }
                        }
                    }
                    $data['id'] = $maxId + 1;
                }

                if (in_array('created_at', $columns) && empty($data['created_at'])) {
                    $data['created_at'] = date('Y-m-d H:i:s');
                }

                // Prepare row in exact column order
                $rowValues = [];
                foreach ($columns as $col) {
                    $rowValues[] = $data[$col] ?? '';
                }

                if ($this->googleService->isConfigured()) {
                    try {
                        $sheets = $this->googleService->getSheetsService();
                        $spreadsheetId = $this->googleService->getSpreadsheetId();

                        $body = new ValueRange([
                            'values' => [$rowValues]
                        ]);

                        $sheets->spreadsheets_values->append(
                            $spreadsheetId,
                            "{$table}!A1",
                            $body,
                            ['valueInputOption' => 'USER_ENTERED']
                        );
                    } catch (Exception $e) {
                        Log::warning("Google Sheets insert failed for table '{$table}': " . $e->getMessage());
                    }
                }

                // Always write to local DB
                $localTable = $this->getLocalTableName($table);
                if (Schema::hasTable($localTable)) {
                    $insertPayload = [];
                    foreach ($columns as $col) {
                        if (Schema::hasColumn($localTable, $col)) {
                            $insertPayload[$col] = $data[$col] ?? null;
                        }
                    }

                    $keyCol = match ($table) {
                        'pengaturan' => 'key',
                        'karyawan' => 'nrp',
                        default => 'id',
                    };

                    if (isset($data[$keyCol]) && Schema::hasColumn($localTable, $keyCol)) {
                        DB::table($localTable)->updateOrInsert(
                            [$keyCol => $data[$keyCol]],
                            $insertPayload
                        );
                    } else {
                        DB::table($localTable)->insert($insertPayload);
                    }
                }

                // Sync to Google Spreadsheet via non-blocking background async worker
                if ($this->hasAppscript() && $this->isSyncEnabled() && $table === 'karyawan' && !empty($data['nrp'])) {
                    GoogleSyncService::syncEmployeeRowAsync('upsert', (string)$data['nrp']);
                }

                $this->clearCache($table);
                return $data;
            });
        } catch (\Throwable $e) {
            Log::warning("SheetRepository insert fallback to local DB for table '{$table}': " . $e->getMessage());
            $localTable = $this->getLocalTableName($table);
            if (Schema::hasTable($localTable)) {
                $columns = self::SCHEMAS[$table] ?? array_keys($data);
                $insertPayload = [];
                foreach ($columns as $col) {
                    if (Schema::hasColumn($localTable, $col) && array_key_exists($col, $data)) {
                        $insertPayload[$col] = $data[$col];
                    }
                }
                $keyCol = match ($table) {
                    'pengaturan' => 'key',
                    'karyawan' => 'nrp',
                    default => 'id',
                };
                if (isset($data[$keyCol]) && Schema::hasColumn($localTable, $keyCol)) {
                    DB::table($localTable)->updateOrInsert([$keyCol => $data[$keyCol]], $insertPayload);
                } else {
                    $newId = DB::table($localTable)->insertGetId($insertPayload);
                    $data['id'] = $newId;
                }
            }
            return $data;
        }
    }

    /**
     * Batch insert rows
     */
    public function batchInsert(string $table, array $rows): int
    {
        if (empty($rows)) {
            return 0;
        }

        $lockKey = "sheet_lock_{$table}";

        return Cache::lock($lockKey, 20)->block(10, function () use ($table, $rows) {
            $columns = self::SCHEMAS[$table] ?? array_keys($rows[0]);
            $sheetRows = [];
            $all = $this->all($table, 0);
            $maxId = 0;
            foreach ($all as $item) {
                if (isset($item['id']) && is_numeric($item['id'])) {
                    $maxId = max($maxId, (int)$item['id']);
                }
            }

            $now = date('Y-m-d H:i:s');
            $localPayloads = [];

            foreach ($rows as $r) {
                if (in_array('id', $columns) && empty($r['id'])) {
                    $maxId++;
                    $r['id'] = $maxId;
                }
                if (in_array('created_at', $columns) && empty($r['created_at'])) {
                    $r['created_at'] = $now;
                }

                $rowValues = [];
                foreach ($columns as $col) {
                    $rowValues[] = $r[$col] ?? '';
                }
                $sheetRows[] = $rowValues;

                $localItem = [];
                foreach ($columns as $col) {
                    $localItem[$col] = $r[$col] ?? null;
                }
                $localPayloads[] = $localItem;
            }

            if ($this->googleService->isConfigured()) {
                try {
                    $sheets = $this->googleService->getSheetsService();
                    $spreadsheetId = $this->googleService->getSpreadsheetId();

                    $body = new ValueRange([
                        'values' => $sheetRows
                    ]);

                    $sheets->spreadsheets_values->append(
                        $spreadsheetId,
                        "{$table}!A1",
                        $body,
                        ['valueInputOption' => 'USER_ENTERED']
                    );
                } catch (Exception $e) {
                    Log::warning("Google Sheets batchInsert failed for table '{$table}': " . $e->getMessage());
                }
            }

            // Local insert
            $localTable = $this->getLocalTableName($table);
            if (Schema::hasTable($localTable)) {
                foreach ($localPayloads as $payload) {
                    $keyField = isset($payload['id']) ? 'id' : (isset($payload['nrp']) ? 'nrp' : (isset($payload['key']) ? 'key' : null));
                    if ($keyField) {
                        DB::table($localTable)->updateOrInsert([$keyField => $payload[$keyField]], $payload);
                    } else {
                        DB::table($localTable)->insert($payload);
                    }
                }
            }

            // Sync to Google Spreadsheet via non-blocking background async worker
            if ($this->hasAppscript() && $this->isSyncEnabled() && $table === 'karyawan') {
                GoogleSyncService::syncEmployeeRowAsync('upsert', (string)($rows[0]['nrp'] ?? ''));
            }

            $this->clearCache($table);
            return count($rows);
        });
    }

    /**
     * Update row by key column
     */
    public function update(string $table, string $keyColumn, $keyValue, array $data): bool
    {
        // 1. If sync is disabled or running in withoutSync, update local DB directly without locking or network overhead
        if (!$this->isSyncEnabled()) {
            $localTable = $this->getLocalTableName($table);
            if (Schema::hasTable($localTable)) {
                $columns = self::SCHEMAS[$table] ?? array_keys($data);
                $updatePayload = [];
                foreach ($columns as $col) {
                    if (Schema::hasColumn($localTable, $col) && array_key_exists($col, $data)) {
                        $updatePayload[$col] = $data[$col];
                    }
                }
                if (!empty($updatePayload)) {
                    DB::table($localTable)->where($keyColumn, $keyValue)->update($updatePayload);
                }
            }
            return true;
        }

        $lockKey = "sheet_lock_{$table}";

        try {
            return Cache::lock($lockKey, 10)->block(3, function () use ($table, $keyColumn, $keyValue, $data) {
                $all = $this->all($table, 0); // Fresh data
                $rowIndex = null;
                $existing = null;

                foreach ($all as $item) {
                    if (isset($item[$keyColumn]) && (string)$item[$keyColumn] === (string)$keyValue) {
                        $rowIndex = $item['_row_index'] ?? null;
                        $existing = $item;
                        break;
                    }
                }

                if (!$existing && !$this->find($table, $keyColumn, $keyValue)) {
                    return false;
                }

                $merged = array_merge($existing ?? [], $data);
                $columns = self::SCHEMAS[$table] ?? array_keys($merged);

                // Google Sheets update if row index found
                if ($this->googleService->isConfigured() && $rowIndex) {
                    try {
                        $sheets = $this->googleService->getSheetsService();
                        $spreadsheetId = $this->googleService->getSpreadsheetId();

                        $rowValues = [];
                        foreach ($columns as $col) {
                            $rowValues[] = $merged[$col] ?? '';
                        }

                        $range = "{$table}!A{$rowIndex}";
                        $body = new ValueRange([
                            'values' => [$rowValues]
                        ]);

                        $sheets->spreadsheets_values->update(
                            $spreadsheetId,
                            $range,
                            $body,
                            ['valueInputOption' => 'USER_ENTERED']
                        );
                    } catch (Exception $e) {
                        Log::warning("Google Sheets update failed for table '{$table}', row {$rowIndex}: " . $e->getMessage());
                    }
                }

                // Always update local database
                $localTable = $this->getLocalTableName($table);
                if (Schema::hasTable($localTable)) {
                    $updatePayload = [];
                    foreach ($columns as $col) {
                        if (Schema::hasColumn($localTable, $col) && array_key_exists($col, $data)) {
                            $updatePayload[$col] = $data[$col];
                        }
                    }
                    if (!empty($updatePayload)) {
                        DB::table($localTable)->where($keyColumn, $keyValue)->update($updatePayload);
                    }
                }

                // Sync to Google Spreadsheet via non-blocking background async worker
                if ($this->hasAppscript() && $this->isSyncEnabled() && $table === 'karyawan') {
                    $nrpVal = ($keyColumn === 'nrp') ? (string)$keyValue : (string)($data['nrp'] ?? '');
                    if ($nrpVal !== '') {
                        GoogleSyncService::syncEmployeeRowAsync('upsert', $nrpVal);
                    }
                }

                $this->clearCache($table);
                return true;
            });
        } catch (\Throwable $e) {
            Log::warning("SheetRepository update fallback to local DB for table '{$table}': " . $e->getMessage());
            $localTable = $this->getLocalTableName($table);
            if (Schema::hasTable($localTable)) {
                $columns = self::SCHEMAS[$table] ?? array_keys($data);
                $updatePayload = [];
                foreach ($columns as $col) {
                    if (Schema::hasColumn($localTable, $col) && array_key_exists($col, $data)) {
                        $updatePayload[$col] = $data[$col];
                    }
                }
                if (!empty($updatePayload)) {
                    DB::table($localTable)->where($keyColumn, $keyValue)->update($updatePayload);
                }
            }
            return true;
        }
    }

    /**
     * Delete row by key column
     */
    public function delete(string $table, string $keyColumn, $keyValue): bool
    {
        $lockKey = "sheet_lock_{$table}";

        return Cache::lock($lockKey, 10)->block(5, function () use ($table, $keyColumn, $keyValue) {
            $all = $this->all($table, 0);
            $rowIndex = null;

            foreach ($all as $item) {
                if (isset($item[$keyColumn]) && (string)$item[$keyColumn] === (string)$keyValue) {
                    $rowIndex = $item['_row_index'] ?? null;
                    break;
                }
            }

            // If online and row index found, delete row from Google Sheets
            if ($this->googleService->isConfigured() && $rowIndex) {
                try {
                    $sheets = $this->googleService->getSheetsService();
                    $spreadsheetId = $this->googleService->getSpreadsheetId();

                    $spreadsheet = $sheets->spreadsheets->get($spreadsheetId);
                    $sheetId = null;
                    foreach ($spreadsheet->getSheets() as $s) {
                        if ($s->getProperties()->getTitle() === $table) {
                            $sheetId = $s->getProperties()->getSheetId();
                            break;
                        }
                    }

                    if ($sheetId !== null) {
                        $deleteRequest = new GoogleSheetRequest([
                            'deleteDimension' => new DeleteDimensionRequest([
                                'range' => new DimensionRange([
                                    'sheetId' => $sheetId,
                                    'dimension' => 'ROWS',
                                    'startIndex' => $rowIndex - 1, // 0-based
                                    'endIndex' => $rowIndex
                                ])
                            ])
                        ]);

                        $batchRequest = new BatchUpdateSpreadsheetRequest([
                            'requests' => [$deleteRequest]
                        ]);

                        $sheets->spreadsheets->batchUpdate($spreadsheetId, $batchRequest);
                    }
                } catch (Exception $e) {
                    Log::warning("Google Sheets delete failed: " . $e->getMessage());
                }
            }

            // Local delete
            $localTable = $this->getLocalTableName($table);
            if (Schema::hasTable($localTable)) {
                DB::table($localTable)->where($keyColumn, $keyValue)->delete();
            }

            // Sync to Google Spreadsheet via non-blocking background async worker
            if ($this->hasAppscript() && $this->isSyncEnabled() && $table === 'karyawan') {
                $nrpVal = ($keyColumn === 'nrp') ? (string)$keyValue : '';
                if ($nrpVal !== '') {
                    GoogleSyncService::syncEmployeeRowAsync('delete', $nrpVal);
                }
            }

            $this->clearCache($table);
            return true;
        });
    }

    /**
     * Invalidate cache
     */
    public function clearCache(?string $table = null): void
    {
        if ($table) {
            Cache::forget("sheet_all_{$table}");
        } else {
            foreach (array_keys(self::SCHEMAS) as $t) {
                Cache::forget("sheet_all_{$t}");
            }
        }
    }

    /**
     * Setup Schema: creates tabs & headers in Google Sheets and local database
     */
    public function setupSchema(): array
    {
        $report = [];

        // 1. Google Sheets Setup if online
        if ($this->isOnline()) {
            try {
                $sheets = $this->googleService->getSheetsService();
                $spreadsheetId = $this->googleService->getSpreadsheetId();

                $spreadsheet = $sheets->spreadsheets->get($spreadsheetId);
                $existingSheets = [];
                foreach ($spreadsheet->getSheets() as $s) {
                    $existingSheets[] = $s->getProperties()->getTitle();
                }

                foreach (self::SCHEMAS as $tableName => $headers) {
                    $tabStatus = 'existing';

                    // If tab missing, add it
                    if (!in_array($tableName, $existingSheets)) {
                        $addSheetReq = new GoogleSheetRequest([
                            'addSheet' => new AddSheetRequest([
                                'properties' => new SheetProperties([
                                    'title' => $tableName
                                ])
                            ])
                        ]);

                        $sheets->spreadsheets->batchUpdate(
                            $spreadsheetId,
                            new BatchUpdateSpreadsheetRequest(['requests' => [$addSheetReq]])
                        );
                        $tabStatus = 'created';
                    }

                    // Check if header row is present
                    $checkRange = "{$tableName}!A1:Z1";
                    $existingHeader = $sheets->spreadsheets_values->get($spreadsheetId, $checkRange)->getValues();

                    if (empty($existingHeader)) {
                        $body = new ValueRange(['values' => [$headers]]);
                        $sheets->spreadsheets_values->update(
                            $spreadsheetId,
                            "{$tableName}!A1",
                            $body,
                            ['valueInputOption' => 'RAW']
                        );
                        $report[$tableName] = "{$tabStatus} + header written";
                    } else {
                        $report[$tableName] = "{$tabStatus} (header verified)";
                    }
                }
            } catch (Exception $e) {
                $report['google_sheets_error'] = $e->getMessage();
                Log::error('setupSchema Google Sheets failed: ' . $e->getMessage());
            }
        } else {
            $report['google_sheets_note'] = 'Service Account belum aktif, schema lokal SQLite siap digunakan.';
        }

        $this->clearCache();
        return $report;
    }

    /**
     * Helper to load from local SQLite
     */
    protected function getFromLocal(string $table): array
    {
        $localTable = $this->getLocalTableName($table);
        if (!Schema::hasTable($localTable)) {
            return [];
        }

        $rows = DB::table($localTable)->get();
        return $rows->map(function ($row) {
            return (array)$row;
        })->toArray();
    }

    /**
     * Helper to mirror Google Sheets rows into local SQLite
     */
    protected function mirrorToLocal(string $table, array $rows): void
    {
        $localTable = $this->getLocalTableName($table);
        if (!Schema::hasTable($localTable) || empty($rows)) {
            return;
        }

        try {
            $columns = Schema::getColumnListing($localTable);
            $colSet = array_flip($columns);

            DB::transaction(function () use ($localTable, $rows, $colSet, $table) {
                $insertedKeys = [];
                foreach ($rows as $row) {
                    $cleanRow = [];
                    foreach ($row as $k => $v) {
                        $kLower = strtolower(trim((string)$k));
                        if ($kLower !== '_row_index' && isset($colSet[$kLower])) {
                            $cleanRow[$kLower] = $v;
                        }
                    }

                    $keyField = isset($cleanRow['id']) ? 'id' : (isset($cleanRow['nrp']) ? 'nrp' : (isset($cleanRow['key']) ? 'key' : null));
                    if ($keyField && !empty($cleanRow[$keyField])) {
                        DB::table($localTable)->updateOrInsert([$keyField => $cleanRow[$keyField]], $cleanRow);
                        $insertedKeys[$keyField][] = $cleanRow[$keyField];
                    }
                }

                // If mirroring full operational table and some rows were deleted from Google Sheets, prune local DB
                if (in_array($table, ['karyawan', 'dokumen', 'kuota_departemen']) && !empty($insertedKeys)) {
                    foreach ($insertedKeys as $kCol => $keys) {
                        DB::table($localTable)->whereNotIn($kCol, $keys)->delete();
                    }
                }
            });

            if ($table === 'karyawan') {
                \App\Services\AccountSyncService::syncLeadershipAccountsFromKaryawan();
            }
        } catch (Exception $e) {
            Log::info("Mirror error for table {$table}: " . $e->getMessage());
        }
    }
}
