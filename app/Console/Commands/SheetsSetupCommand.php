<?php

namespace App\Console\Commands;

use App\Contracts\SheetRepositoryInterface;
use App\Repositories\SheetRepository;
use App\Services\GoogleClientService;
use Illuminate\Console\Command;

class SheetsSetupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sheets:setup {--force : Force overwrite / recreate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup all Google Sheets tabs with official column headers and ensure database synchronization';

    /**
     * Execute the console command.
     */
    public function handle(SheetRepositoryInterface $repository, GoogleClientService $googleService): int
    {
        $this->info('=====================================================');
        $this->info('  TTD Digital Penilaian Karyawan - Google Sheets Setup  ');
        $this->info('=====================================================');

        $spreadsheetId = $googleService->getSpreadsheetId();
        $driveFolderId = $googleService->getDriveFolderId();

        $this->line("Target Spreadsheet ID : <comment>{$spreadsheetId}</comment>");
        $this->line("Target Drive Folder ID: <comment>{$driveFolderId}</comment>");
        $this->line("Status Service Account: " . ($googleService->isConfigured() ? '<info>TERKONFIGURASI</info>' : '<comment>BELUM AKTIF (Lokal Repository)</comment>'));

        $this->newLine();
        $this->info('Menyiapkan tab spreadsheet dan struktur header...');

        $report = $repository->setupSchema();

        $tableRows = [];
        foreach (SheetRepository::SCHEMAS as $sheet => $columns) {
            $status = $report[$sheet] ?? 'Tersedia';
            $tableRows[] = [
                $sheet,
                count($columns),
                implode(', ', array_slice($columns, 0, 4)) . '...',
                $status,
            ];
        }

        $this->table(['Tab / Sheet', 'Jumlah Kolom', 'Kolom Awal', 'Status'], $tableRows);

        // Pre-seed default settings into 'pengaturan'
        $repository->insert('pengaturan', [
            'key' => 'google_spreadsheet_id',
            'value' => $spreadsheetId,
        ]);
        $repository->insert('pengaturan', [
            'key' => 'google_drive_folder_id',
            'value' => $driveFolderId,
        ]);

        $this->newLine();
        $this->info('✓ Setup tab dan header selesai!');
        if (!$googleService->isConfigured()) {
            $this->comment('Catatan: Masukkan Service Account JSON di menu Superadmin atau di storage/app/google-service-account.json untuk sinkronisasi live.');
        }

        return Command::SUCCESS;
    }
}

