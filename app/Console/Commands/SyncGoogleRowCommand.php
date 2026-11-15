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
    protected $signature = 'google:sync-row {action : upsert, delete, or sync_all} {--nrp= : Employee NRP}';

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

        if ($action === 'sync_all') {
            $res = GoogleSyncService::syncTables(['karyawan', 'dokumen', 'users']);
            if ($res['success'] ?? false) {
                $this->info("Successfully synced all tables to Google Spreadsheet.");
                return Command::SUCCESS;
            }
            $this->error("Failed syncing all tables: " . ($res['error'] ?? 'Unknown error'));
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

