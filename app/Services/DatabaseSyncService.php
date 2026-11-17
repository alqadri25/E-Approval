<?php

namespace App\Services;

use App\Contracts\SheetRepositoryInterface;
use App\Http\Controllers\PenilaianController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseSyncService
{
    /**
     * Run full database synchronization.
     *
     * @return array<string, mixed>
     */
    public static function syncAll(): array
    {
        $repository = app(SheetRepositoryInterface::class);

        // 1. Sync / Ensure Master Karyawan from DATA PK.xlsx
        $excelPath = base_path('DATA PK.xlsx');
        $karyawanList = [];
        $syncedFromExcel = false;

        if (file_exists($excelPath)) {
            $excelRows = ExcelKaryawanReader::extractDepartmentEmployeesFromExcel(
                $excelPath,
                [
                    'nrp_section_head' => '99999',
                    'nrp_dept_head' => '31',
                    'nrp_div_head' => '11',
                ],
                true
            );

            if (!empty($excelRows)) {
                $syncedFromExcel = true;
                foreach ($excelRows as $row) {
                    $nrp = trim((string)($row['nrp'] ?? ''));
                    if ($nrp === '') continue;

                    $karyawanList[] = $row;

                    // Update or insert into database table 'karyawan'
                    DB::table('karyawan')->updateOrInsert(
                        ['nrp' => $nrp],
                        array_merge($row, ['updated_at' => now()])
                    );
                }
            }
        }

        // 2. Ensure Active Periode exists
        $currentYear = (int)date('Y');
        $activePeriod = DB::table('periode')->where('status', 'aktif')->first();
        if (!$activePeriod) {
            $periodId = DB::table('periode')->insertGetId([
                'tahun' => $currentYear,
                'tgl_mulai' => $currentYear . '-01-01',
                'tgl_selesai' => $currentYear . '-12-31',
                'status' => 'aktif',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $periodeId = $periodId;
            $periodeTahun = $currentYear;
        } else {
            $periodeId = $activePeriod->id;
            $periodeTahun = $activePeriod->tahun;
        }

        // 3. Ensure appraisal document exists for each employee
        $allKaryawan = DB::table('karyawan')->get();
        $existingDocNrps = DB::table('dokumen')->pluck('nrp')->map(fn($n) => trim((string)$n))->flip()->toArray();

        $docsCreated = 0;
        foreach ($allKaryawan as $k) {
            $kArray = (array)$k;
            $nrp = trim((string)$k->nrp);
            if (!isset($existingDocNrps[$nrp])) {
                $kodeForm = PenilaianController::determineKodeForm($kArray);
                
                // Determine appropriate initial status
                $isDiv = $kodeForm === '07-006-DIV' || ($k->level_form ?? '') === 'DIV_HEAD' || str_contains(strtoupper($k->jabatan ?? ''), 'DIV HEAD') || str_contains(strtoupper($k->jabatan ?? ''), 'DIVISION HEAD');
                $isDept = $kodeForm === '07-006-DH' || ($k->level_form ?? '') === 'DEPT_HEAD' || str_contains(strtoupper($k->jabatan ?? ''), 'DEPT HEAD') || str_contains(strtoupper($k->jabatan ?? ''), 'DEPARTMENT HEAD');
                $isSect = $kodeForm === '07-006-SH' || ($k->level_form ?? '') === 'SECTION_HEAD' || str_contains(strtoupper($k->jabatan ?? ''), 'SECTION HEAD') || str_contains(strtoupper($k->jabatan ?? ''), 'SECT HEAD');
                
                if ($isDiv) {
                    $status = 'menunggu_div_head';
                } elseif ($isDept) {
                    $status = 'menunggu_dept_head';
                } elseif ($isSect) {
                    $status = 'menunggu_section_head';
                } else {
                    $hasForeman = !empty($k->nrp_foreman) && trim((string)$k->nrp_foreman) !== '-' && strcasecmp(trim((string)$k->nrp_foreman), $nrp) !== 0;
                    $status = $hasForeman ? 'menunggu_foreman' : 'menunggu_section_head';
                }

                DB::table('dokumen')->insert([
                    'periode_id' => $periodeId,
                    'nrp' => $nrp,
                    'kode_form' => $kodeForm,
                    'status' => $status,
                    'total_nilai' => null,
                    'grade_original' => null,
                    'docking_final' => null,
                    'drive_file_id' => null,
                    'drive_url' => null,
                    'hash_dokumen' => null,
                    'kode_verifikasi' => 'PK-' . $periodeTahun . '-' . $nrp . '-' . strtoupper(Str::random(6)),
                    'version' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $docsCreated++;
                $existingDocNrps[$nrp] = true;
            } else {
                // Keep kode_form synchronized with employee's actual position/KJ/golongan
                DB::table('dokumen')->where('nrp', $nrp)->update([
                    'kode_form' => $kodeForm,
                    'updated_at' => now(),
                ]);
            }
        }

        // 4. Synchronize Leadership Accounts from Master Karyawan
        $accountSyncResult = AccountSyncService::syncLeadershipAccountsFromKaryawan();

        // 5. Clear application & repository cache
        Cache::flush();

        // 6. Record last sync timestamp in 'pengaturan'
        $syncTime = now()->format('Y-m-d H:i:s');
        $displaySyncTime = now()->translatedFormat('d F Y, H:i') . ' WIB';

        DB::table('pengaturan')->updateOrInsert(
            ['key' => 'terakhir_sinkron_db'],
            ['value' => $syncTime, 'updated_at' => now()]
        );
        DB::table('pengaturan')->updateOrInsert(
            ['key' => 'terakhir_sinkron_db_display'],
            ['value' => $displaySyncTime, 'updated_at' => now()]
        );

        $totalKaryawan = DB::table('karyawan')->count();
        $totalDokumen = DB::table('dokumen')->count();
        $totalUsers = \App\Models\User::count();

        return [
            'success' => true,
            'synced_from_excel' => $syncedFromExcel,
            'karyawan_total' => $totalKaryawan,
            'dokumen_total' => $totalDokumen,
            'docs_created' => $docsCreated,
            'users_total' => $totalUsers,
            'account_created' => $accountSyncResult['created'] ?? 0,
            'account_updated' => $accountSyncResult['updated'] ?? 0,
            'account_deleted' => $accountSyncResult['deleted'] ?? 0,
            'sync_time' => $displaySyncTime,
        ];
    }
}