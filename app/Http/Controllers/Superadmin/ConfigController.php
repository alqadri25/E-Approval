<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Contracts\SheetRepositoryInterface;
use App\Services\GoogleClientService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ConfigController extends Controller
{
    protected GoogleClientService $googleService;
    protected SheetRepositoryInterface $repository;

    public function __construct(GoogleClientService $googleService, SheetRepositoryInterface $repository)
    {
        $this->googleService = $googleService;
        $this->repository = $repository;
    }

    /**
     * Show configuration panel
     */
    public function index()
    {
        $spreadsheetId = $this->googleService->getSpreadsheetId();
        $driveFolderId = $this->googleService->getDriveFolderId();
        $isConfigured = $this->googleService->isConfigured();
        $serviceAccountPath = $this->googleService->getServiceAccountPath();

        $sheetsUrl = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/edit";
        $driveUrl = "https://drive.google.com/drive/folders/{$driveFolderId}";

        return view('superadmin.config', compact(
            'spreadsheetId',
            'driveFolderId',
            'isConfigured',
            'serviceAccountPath',
            'sheetsUrl',
            'driveUrl'
        ));
    }

    /**
     * Update Google Spreadsheet and Drive IDs/URLs
     */
    public function updateLinks(Request $request)
    {
        $request->validate([
            'spreadsheet_input' => 'required|string',
            'drive_folder_input' => 'required|string',
        ]);

        $spreadsheetId = $this->googleService->extractSpreadsheetId($request->spreadsheet_input);
        $driveFolderId = $this->googleService->extractDriveFolderId($request->drive_folder_input);

        $this->repository->insert('pengaturan', [
            'key' => 'google_spreadsheet_id',
            'value' => $spreadsheetId,
        ]);

        $this->repository->insert('pengaturan', [
            'key' => 'google_drive_folder_id',
            'value' => $driveFolderId,
        ]);

        $this->repository->clearCache();

        // Audit log
        $this->repository->insert('audit_log', [
            'waktu' => now()->toDateTimeString(),
            'user_id' => auth()->id(),
            'aksi' => 'UPDATE_CONFIG',
            'dokumen_id' => null,
            'detail' => "Update spreadsheet_id: {$spreadsheetId}, drive_folder_id: {$driveFolderId}",
        ]);

        return back()->with('success', 'Link database Google Spreadsheet dan Google Drive berhasil diperbarui!');
    }

    /**
     * Upload or paste Service Account JSON credentials
     */
    public function updateCredentials(Request $request)
    {
        $request->validate([
            'service_account_file' => 'nullable|file|mimes:json,txt',
            'service_account_json' => 'nullable|string',
        ]);

        $jsonContent = null;

        if ($request->hasFile('service_account_file')) {
            $jsonContent = file_get_contents($request->file('service_account_file')->getRealPath());
        } elseif (!empty($request->service_account_json)) {
            $jsonContent = trim($request->service_account_json);
        }

        if ($jsonContent) {
            $decoded = json_decode($jsonContent, true);
            if (!$decoded || empty($decoded['client_email']) || empty($decoded['private_key'])) {
                return back()->with('error', 'Format JSON Service Account tidak valid. Pastikan file JSON resmi dari Google Cloud Console.');
            }

            // Save to storage path securely
            $targetPath = storage_path('app/google-service-account.json');
            if (!is_dir(dirname($targetPath))) {
                mkdir(dirname($targetPath), 0755, true);
            }
            file_put_contents($targetPath, $jsonContent);

            // Also store in settings
            $this->repository->insert('pengaturan', [
                'key' => 'google_service_account_json',
                'value' => $jsonContent,
            ]);

            $this->repository->clearCache();

            return back()->with('success', "Service Account ({$decoded['client_email']}) berhasil disimpan dan diaktifkan!");
        }

        return back()->with('error', 'Silakan pilih file JSON atau tempel isi JSON kredensial.');
    }

    /**
     * Test Google connection live
     */
    public function testConnection()
    {
        $results = $this->googleService->testConnection();

        if ($results['sheets_connected'] && $results['drive_connected']) {
            return back()->with('success', 'Koneksi Sukses! Terhubung ke Google Sheets dan Google Drive.');
        }

        return back()->with('connection_results', $results);
    }

    /**
     * Run sheets:setup artisan command from UI
     */
    public function runSetup()
    {
        try {
            Artisan::call('sheets:setup');
            $output = Artisan::output();
            return back()->with('setup_output', $output)->with('success', 'Setup tab & header spreadsheet berhasil dijalankan!');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menjalankan setup: ' . $e->getMessage());
        }
    }
}

