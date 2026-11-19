<?php

namespace App\Console\Commands;

use App\Services\GoogleSyncService;
use Illuminate\Console\Command;

class SyncGoogleRowCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'google:sync-row {action : upsert, delete, sync_all, or upload_pdf} {--nrp= : Employee NRP} {--doc-id= : Document ID for PDF upload} {--tables= : Comma-separated tables to sync}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync employee data or tables to Google Spreadsheet in real time';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = strtolower(trim($this->argument('action')));

        if ($action === 'sync_all' || $action === 'sync_tables') {
            $rawTables = $this->option('tables');
            $tables = !empty($rawTables)
                ? array_filter(array_map('trim', explode(',', (string)$rawTables)))
                : ['karyawan', 'dokumen', 'Dokumen_Dinilai', 'Target_Pemantauan', 'users', 'nilai', 'indikator', 'tanda_tangan', 'audit_log'];

            $res = GoogleSyncService::syncTables($tables);
            if ($res['success'] ?? false) {
                $this->info("Successfully synced tables (" . implode(', ', $tables) . ") to Google Spreadsheet.");
                return Command::SUCCESS;
            }
            $this->error("Failed syncing tables: " . ($res['error'] ?? 'Unknown error'));
            return Command::FAILURE;
        }

        if ($action === 'upload_pdf') {
            $docId = (int)$this->option('doc-id');
            if ($docId <= 0) {
                $this->error('Option --doc-id is required for upload_pdf action.');
                return Command::FAILURE;
            }
            $res = GoogleSyncService::uploadPdfToDrive($docId);
            if ($res['success'] ?? false) {
                $this->info("Successfully uploaded PDF for Document ID {$docId} to Google Drive.");
                return Command::SUCCESS;
            }
            $this->error("Failed uploading PDF for Document ID {$docId}: " . ($res['error'] ?? $res['message'] ?? 'Unknown error'));
            return Command::FAILURE;
        }

        $nrp = trim((string)$this->option('nrp'));

        if (empty($nrp)) {
            $this->error('Option --nrp is required for upsert or delete action.');
            return Command::FAILURE;
        }

        $res = GoogleSyncService::syncEmployeeRow($action, $nrp);
        if ($res['success'] ?? false) {
            $this->info("Successfully synced employee NRP {$nrp} ({$action}) to Google Spreadsheet.");
            return Command::SUCCESS;
        }

        $this->error("Failed syncing employee NRP {$nrp}: " . ($res['error'] ?? $res['message'] ?? 'Unknown error'));
        return Command::FAILURE;
    }
}

