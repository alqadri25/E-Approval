<?php

namespace App\Services;

use Google\Client as GoogleApiClient;
use Google\Service\Sheets as GoogleSheets;
use Google\Service\Drive as GoogleDrive;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Exception;

class GoogleClientService
{
    protected ?GoogleApiClient $client = null;
    protected ?GoogleSheets $sheetsService = null;
    protected ?GoogleDrive $driveService = null;

    /**
     * Get or initialize Google API Client
     */
    public function getClient(): GoogleApiClient
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $client = new GoogleApiClient();
        $client->setApplicationName('TTD Digital Penilaian Karyawan');
        $client->setScopes([
            GoogleSheets::SPREADSHEETS,
            GoogleDrive::DRIVE,
            GoogleDrive::DRIVE_FILE,
        ]);
        $client->setAccessType('offline');

        // Look for credentials:
        // 1. Raw JSON from settings
        // 2. File from path
        $credentialsJson = $this->getServiceAccountCredentialsJson();

        if ($credentialsJson) {
            $client->setAuthConfig(json_decode($credentialsJson, true));
        } else {
            $credentialsPath = $this->getServiceAccountPath();
            if ($credentialsPath && file_exists($credentialsPath)) {
                $client->setAuthConfig($credentialsPath);
            }
        }

        $this->client = $client;
        return $this->client;
    }

    /**
     * Get Google Sheets Service
     */
    public function getSheetsService(): GoogleSheets
    {
        if ($this->sheetsService !== null) {
            return $this->sheetsService;
        }

        $this->sheetsService = new GoogleSheets($this->getClient());
        return $this->sheetsService;
    }

    /**
     * Get Google Drive Service
     */
    public function getDriveService(): GoogleDrive
    {
        if ($this->driveService !== null) {
            return $this->driveService;
        }

        $this->driveService = new GoogleDrive($this->getClient());
        return $this->driveService;
    }

    /**
     * Check if Google Service Account credentials are fully configured
     */
    public function isConfigured(): bool
    {
        if (!class_exists('Google\Client') || !class_exists('Google\Service\Sheets')) {
            return false;
        }

        $raw = $this->getServiceAccountCredentialsJson();
        if (!empty($raw) && json_decode($raw, true)) {
            return true;
        }

        $path = $this->getServiceAccountPath();
        return !empty($path) && file_exists($path);
    }

    /**
     * Test connection to Google Sheets and Google Drive
     */
    public function testConnection(): array
    {
        $results = [
            'configured' => $this->isConfigured(),
            'sheets_connected' => false,
            'drive_connected' => false,
            'spreadsheet_id' => $this->getSpreadsheetId(),
            'drive_folder_id' => $this->getDriveFolderId(),
            'messages' => [],
        ];

        if (!$results['configured']) {
            $results['messages'][] = 'Service Account JSON belum dikonfigurasi. Sistem saat ini berjalan dalam mode lokal repository.';
            return $results;
        }

        // Test Sheets
        try {
            $sheets = $this->getSheetsService();
            $spreadsheet = $sheets->spreadsheets->get($results['spreadsheet_id']);
            $results['sheets_connected'] = true;
            $results['spreadsheet_title'] = $spreadsheet->getProperties()->getTitle();
            $results['messages'][] = "Berhasil terhubung ke Google Sheets: '{$results['spreadsheet_title']}'.";
        } catch (Exception $e) {
            $results['messages'][] = 'Gagal terhubung ke Google Sheets: ' . $e->getMessage();
            Log::warning('Google Sheets testConnection failed: ' . $e->getMessage());
        }

        // Test Drive
        try {
            $drive = $this->getDriveService();
            $folder = $drive->files->get($results['drive_folder_id'], ['fields' => 'id, name, mimeType']);
            $results['drive_connected'] = true;
            $results['drive_folder_name'] = $folder->getName();
            $results['messages'][] = "Berhasil terhubung ke Google Drive folder: '{$results['drive_folder_name']}'.";
        } catch (Exception $e) {
            $results['messages'][] = 'Gagal terhubung ke Google Drive: ' . $e->getMessage();
            Log::warning('Google Drive testConnection failed: ' . $e->getMessage());
        }

        return $results;
    }

    /**
     * Get dynamic or env Spreadsheet ID
     */
    public function getSpreadsheetId(): string
    {
        $dbVal = $this->getSetting('google_spreadsheet_id');
        if (!empty($dbVal)) {
            return $this->extractSpreadsheetId($dbVal);
        }

        return $this->extractSpreadsheetId(config('google.spreadsheet_id', '14-xI_m24eXSj1L2aiX_gytMWrrS9HnjH5HzcRgWV_9g'));
    }

    /**
     * Get dynamic or env Drive Folder ID
     */
    public function getDriveFolderId(): string
    {
        $dbVal = $this->getSetting('google_drive_folder_id');
        if (!empty($dbVal)) {
            return $this->extractDriveFolderId($dbVal);
        }

        return $this->extractDriveFolderId(config('google.drive_folder_id', '1zB2loWV14fCpLWMCnNiEa4dVKAvhLZYE'));
    }

    /**
     * Get Service Account Credentials JSON String
     */
    public function getServiceAccountCredentialsJson(): ?string
    {
        $fromDb = $this->getSetting('google_service_account_json');
        if (!empty($fromDb)) {
            return $fromDb;
        }

        return config('google.service_account_json');
    }

    /**
     * Get Service Account Path
     */
    public function getServiceAccountPath(): ?string
    {
        return config('google.service_account_path', storage_path('app/google-service-account.json'));
    }

    /**
     * Extract ID from Google Sheets URL or raw ID
     */
    public function extractSpreadsheetId(string $input): string
    {
        $input = trim($input);
        if (preg_match('/\/spreadsheets\/d\/([a-zA-Z0-9-_]+)/', $input, $matches)) {
            return $matches[1];
        }
        return $input;
    }

    /**
     * Extract ID from Google Drive folder URL or raw ID
     */
    public function extractDriveFolderId(string $input): string
    {
        $input = trim($input);
        if (preg_match('/\/folders\/([a-zA-Z0-9-_]+)/', $input, $matches)) {
            return $matches[1];
        }
        return $input;
    }

    /**
     * Helper to read dynamic setting
     */
    protected function getSetting(string $key): ?string
    {
        try {
            if (Schema::hasTable('pengaturan')) {
                $row = DB::table('pengaturan')->where('key', $key)->first();
                return $row ? $row->value : null;
            }
        } catch (Exception $e) {
            // Ignore if tables not yet created
        }
        return null;
    }
}

