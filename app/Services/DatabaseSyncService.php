<?php

namespace App\Services;

use App\Contracts\SheetRepositoryInterface;
use App\Http\Controllers\PenilaianController;
use App\Models\User;
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

        // Map Section Heads by section for employees without line category
        $shMap = [];
        foreach ($allKaryawan as $emp) {
            $pos = strtoupper(trim((string)$emp->jabatan));
            $isSh = (str_contains($pos, 'SECTION HEAD') || str_contains($pos, 'SECT HEAD') || str_contains($pos, 'DEPUTY SECTION HEAD')) && !str_contains($pos, 'NON KASIE');
            if ($isSh) {
                $s = strtoupper(trim((string)($emp->seksi ?: ($emp->nama_sect ?: ''))));
                if ($s !== '' && $s !== '-') {
                    $normS = strtoupper(trim(preg_replace('/\s*(SEC|SECTION)\.?$/i', '', $s)));
                    if (!isset($shMap[$normS]) || !str_contains($pos, 'DEPUTY')) {
                        $shMap[$normS] = $emp;
                    }
                }
            }
        }

        // Map Foremen by [line_category]
        $fmMap = [];
        foreach ($allKaryawan as $emp) {
            $pos = strtoupper(trim((string)$emp->jabatan));
            $isForemanEmp = (str_contains($pos, 'FOREMAN') || str_contains(strtoupper((string)($emp->level_form ?? '')), 'FOREMAN'))
                && !str_contains($pos, 'SECTION HEAD')
                && !str_contains($pos, 'SECT HEAD')
                && !str_contains($pos, 'DEPT HEAD')
                && !str_contains($pos, 'MANAGER')
                && !str_contains($pos, 'DIV HEAD');

            if ($isForemanEmp) {
                $lc = strtoupper(trim((string)($emp->line_category ?? '')));
                if ($lc !== '' && $lc !== '-' && $lc !== 'NULL') {
                    $deptNorm = User::normalizeDepartmentName($emp->departemen ?? '');
                    $sectNorm = strtoupper(trim(preg_replace('/\s*(SEC|SECTION)\.?$/i', '', (string)($emp->seksi ?: ($emp->nama_sect ?: '')))));

                    if ($sectNorm !== '') {
                        $fmMap[$sectNorm . '|' . $lc] = $emp;
                    }
                    if ($deptNorm !== '') {
                        $fmMap[$deptNorm . '|' . $lc] = $emp;
                    }
                    if (!isset($fmMap[$lc])) {
                        $fmMap[$lc] = $emp;
                    }
                }
            }
        }

        $docsCreated = 0;
        foreach ($allKaryawan as $k) {
            $kArray = (array)$k;
            $nrp = trim((string)$k->nrp);
            $kodeForm = PenilaianController::determineKodeForm($kArray);

            // Check if employee has no line category and matches a Section Head in the same section
            $lineCat = trim((string)($k->line_category ?? ''));
            $lineCatUpper = strtoupper($lineCat);
            $hasLineCat = ($lineCatUpper !== '' && $lineCatUpper !== '-' && $lineCatUpper !== 'NULL');
            $noLineCat = !$hasLineCat;
            $posUpper = strtoupper(trim((string)($k->jabatan ?? '')));
            $isUpper = (
                str_contains($posUpper, 'SECTION HEAD') ||
                str_contains($posUpper, 'SECT HEAD') ||
                str_contains($posUpper, 'DEPUTY SECTION HEAD') ||
                str_contains($posUpper, 'KASIE') ||
                str_contains($posUpper, 'DEPT HEAD') ||
                str_contains($posUpper, 'DEPARTMENT HEAD') ||
                str_contains($posUpper, 'DIV HEAD') ||
                str_contains($posUpper, 'DIVISION HEAD') ||
                str_contains($posUpper, 'MANAGER') ||
                str_contains($posUpper, 'MANAJER')
            ) && !str_contains($posUpper, 'NON KASIE');

            $resolvedShNrp = !empty($k->nrp_section_head) && trim((string)$k->nrp_section_head) !== '-' ? trim((string)$k->nrp_section_head) : null;
            $resolvedShNama = null;

            if ($noLineCat && !$isUpper) {
                $s = strtoupper(trim((string)($k->seksi ?: ($k->nama_sect ?: ''))));
                $normS = strtoupper(trim(preg_replace('/\s*(SEC|SECTION)\.?$/i', '', $s)));
                if ($normS !== '' && isset($shMap[$normS])) {
                    $shObj = $shMap[$normS];
                    $resolvedShNrp = $shObj->nrp;
                    $resolvedShNama = $shObj->nama_lengkap;

                    // Update karyawan table if empty
                    if (empty($k->nrp_section_head) || trim((string)$k->nrp_section_head) === '-') {
                        DB::table('karyawan')->where('nrp', $nrp)->update([
                            'nrp_section_head' => $resolvedShNrp,
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            // Check if employee has a line_category matching a Foreman
            $resolvedFmNrp = !empty($k->nrp_foreman) && trim((string)$k->nrp_foreman) !== '-' ? trim((string)$k->nrp_foreman) : null;
            $resolvedFmNama = null;

            if ($hasLineCat && !$isUpper && empty($resolvedFmNrp)) {
                $s = strtoupper(trim((string)($k->seksi ?: ($k->nama_sect ?: ''))));
                $normS = strtoupper(trim(preg_replace('/\s*(SEC|SECTION)\.?$/i', '', $s)));
                $d = User::normalizeDepartmentName($k->departemen ?? '');

                $matchedFm = $fmMap[$normS . '|' . $lineCatUpper] ?? ($fmMap[$d . '|' . $lineCatUpper] ?? ($fmMap[$lineCatUpper] ?? null));
                if ($matchedFm && strcasecmp($matchedFm->nrp, $nrp) !== 0) {
                    $resolvedFmNrp = $matchedFm->nrp;
                    $resolvedFmNama = $matchedFm->nama_lengkap;

                    // Update karyawan table
                    DB::table('karyawan')->where('nrp', $nrp)->update([
                        'nrp_foreman' => $resolvedFmNrp,
                        'updated_at' => now(),
                    ]);
                }
            }

            $isDiv = $kodeForm === '07-006-DIV' || ($k->level_form ?? '') === 'DIV_HEAD' || str_contains($posUpper, 'DIV HEAD') || str_contains($posUpper, 'DIVISION HEAD');
            $isDept = $kodeForm === '07-006-DH' || ($k->level_form ?? '') === 'DEPT_HEAD' || str_contains($posUpper, 'DEPT HEAD') || str_contains($posUpper, 'DEPARTMENT HEAD');
            $isActualSH = (str_contains($posUpper, 'SECTION HEAD') || str_contains($posUpper, 'SECT HEAD') || str_contains($posUpper, 'DEPUTY SECTION HEAD')) && !str_contains($posUpper, 'NON KASIE');
            $empKjNum = isset($k->kj) ? (int)preg_replace('/[^0-9]/', '', (string)$k->kj) : 0;
            $isSubordinate07006 = (str_starts_with($kodeForm, '07-006') || $empKjNum >= 5) && !$isActualSH && !$isDept && !$isDiv;

            $isDirectDiv = \App\Http\Controllers\PenilaianController::isDirectDivSubordinate((array)$k);
            $isDirectDeptDiv = \App\Http\Controllers\PenilaianController::isDirectDeptDivSubordinate((array)$k);
            $isSecretary = str_contains($seksiUpper, 'SECRETARY') || str_contains($posUpper, 'SECRETARY') || in_array((string)$nrp, ['268', '1237']);
            if ($isDirectDiv) {
                $kodeForm = '07-006-DIV';
                $status = 'menunggu_div_head';
            } elseif ($isDirectDeptDiv) {
                $kodeForm = '07-006-DH';
                $status = 'menunggu_dept_head';
            } elseif ($isSecretary) {
                $kodeForm = '07-006-DH';
                $status = 'menunggu_dept_head';
            } elseif ($isDiv) {
                $status = 'menunggu_div_head';
            } elseif ($isDept) {
                $status = 'menunggu_dept_head';
            } elseif ($isActualSH) {
                $status = 'menunggu_section_head';
            } elseif ($kodeForm === '07-006-KARYAWAN') {
                // Form 07-006-KARYAWAN (KJ 5+ non-Section Head): Dinilai pertama oleh Section Head!
                $status = 'menunggu_section_head';
            } else {
                $kArray = (array)$k;
                $hasForeman = PenilaianController::requiresForemanStage($kArray, $kodeForm);
                $status = $hasForeman ? 'menunggu_foreman' : 'menunggu_section_head';
            }

            if (!isset($existingDocNrps[$nrp])) {
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
                    'nrp_foreman' => $resolvedFmNrp,
                    'nama_foreman' => $resolvedFmNama,
                    'nrp_section_head' => $resolvedShNrp,
                    'nama_section_head' => $resolvedShNama,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $docsCreated++;
                $existingDocNrps[$nrp] = true;
            } else {
                $docUpdate = [
                    'kode_form' => $kodeForm,
                    'updated_at' => now(),
                ];
                if ($resolvedFmNrp) {
                    $docUpdate['nrp_foreman'] = $resolvedFmNrp;
                    if ($resolvedFmNama) {
                        $docUpdate['nama_foreman'] = $resolvedFmNama;
                    }
                }
                if ($resolvedShNrp) {
                    $docUpdate['nrp_section_head'] = $resolvedShNrp;
                    if ($resolvedShNama) {
                        $docUpdate['nama_section_head'] = $resolvedShNama;
                    }
                }
                // Dokumen yang belum ditandatangani Section Head: jika bukan Dept Head / Div Head, posisinya masih di Section Head atau Foreman
                $existingDoc = DB::table('dokumen')->where('nrp', $nrp)->first();
                if ($existingDoc && !$isDiv && !$isDept && !$isDirectDeptDiv) {
                    $hasShTtd = DB::table('tanda_tangan')->where('dokumen_id', $existingDoc->id)->where('level', 'section_head')->exists();
                    $hasShScore = DB::table('nilai')->where('dokumen_id', $existingDoc->id)->where('level', 'section_head')->exists();
                    $hasTotalScore = !empty($existingDoc->total_nilai);
                    if (!$hasShTtd && !$hasShScore && !$hasTotalScore && in_array($existingDoc->status, ['draft', 'menunggu_dept_head', 'menunggu_section_head'])) {
                        $kArray = (array)$k;
                        $hasForemanEmp = PenilaianController::requiresForemanStage($kArray, $kodeForm);
                        $docUpdate['status'] = $hasForemanEmp ? 'menunggu_foreman' : 'menunggu_section_head';
                    }
                } elseif ($existingDoc && $isDirectDeptDiv) {
                    $hasDeptTtd = DB::table('tanda_tangan')->where('dokumen_id', $existingDoc->id)->where('level', 'dept_head')->exists();
                    if (!$hasDeptTtd && in_array($existingDoc->status, ['draft', 'menunggu_section_head', 'menunggu_foreman'])) {
                        $docUpdate['status'] = 'menunggu_dept_head';
                    }
                }
                DB::table('dokumen')->where('nrp', $nrp)->update($docUpdate);
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

    /**
     * Memperbaiki dan menertibkan status dokumen yang belum pernah dinilai sama sekali
     * agar tidak loncat ke menunggu_dept_head jika belum ditandatangani Section Head.
     */
    public static function healUnsignedDocumentStatuses(): int
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('dokumen') || !\Illuminate\Support\Facades\Schema::hasTable('karyawan')) {
            return 0;
        }

        self::resolveForemanSubordinates();

        $docs = DB::table('dokumen')->get();
        $healed = 0;

        foreach ($docs as $d) {
            $hasShTtd = DB::table('tanda_tangan')->where('dokumen_id', $d->id)->where('level', 'section_head')->exists();
            $hasShScore = DB::table('nilai')->where('dokumen_id', $d->id)->where('level', 'section_head')->exists();
            $hasForemanTtd = DB::table('tanda_tangan')->where('dokumen_id', $d->id)->where('level', 'foreman')->exists();
            $hasForemanScore = DB::table('nilai')->where('dokumen_id', $d->id)->where('level', 'foreman')->exists();
            $hasDeptTtd = DB::table('tanda_tangan')->where('dokumen_id', $d->id)->where('level', 'dept_head')->exists();
            $hasDivTtd = DB::table('tanda_tangan')->where('dokumen_id', $d->id)->where('level', 'div_head')->exists();
            $hasTotal = !empty($d->total_nilai);

            // Jika dokumen sudah memiliki nilai atau tanda tangan digital asli, jangan sentuh
            if ($hasTotal || $hasShTtd || $hasForemanTtd || $hasDeptTtd || $hasDivTtd || $hasShScore || $hasForemanScore) {
                continue;
            }

            $k = DB::table('karyawan')->where('nrp', $d->nrp)->first();
            if (!$k) continue;

            $jab = strtoupper(trim((string)$k->jabatan));
            $isDiv = str_contains($jab, 'DIVISION HEAD') || str_contains($jab, 'DIV HEAD');
            $isDept = (str_contains($jab, 'DEPARTMENT HEAD') || str_contains($jab, 'DEPT HEAD') || str_contains($jab, 'DEPT. HEAD') || str_contains($jab, 'MANAGER') || str_contains($jab, 'MANAJER')) && !str_contains($jab, 'NON KASIE');
            $hasForeman = PenilaianController::requiresForemanStage((array)$k, $d->kode_form);

            $isDirectDiv = \App\Http\Controllers\PenilaianController::isDirectDivSubordinate($k, (array)$d);
            $isDirectDeptDiv = \App\Http\Controllers\PenilaianController::isDirectDeptDivSubordinate($k, (array)$d);
            $seksi = strtoupper(trim((string)($k->seksi ?? '')));
            $isSecretary = str_contains($seksi, 'SECRETARY') || str_contains($jab, 'SECRETARY') || in_array((string)$k->nrp, ['268', '1237']);
            $targetStatus = 'menunggu_section_head';
            if ($isDirectDiv) {
                $targetStatus = 'menunggu_div_head';
                if ($d->kode_form !== '07-006-DIV') {
                    DB::table('dokumen')->where('id', $d->id)->update(['kode_form' => '07-006-DIV']);
                }
            } elseif ($isDirectDeptDiv) {
                $targetStatus = 'menunggu_dept_head';
                if ($d->kode_form !== '07-006-DH') {
                    DB::table('dokumen')->where('id', $d->id)->update(['kode_form' => '07-006-DH']);
                }
            } elseif ($isSecretary) {
                $targetStatus = 'menunggu_dept_head';
                if ($d->kode_form !== '07-006-DH') {
                    DB::table('dokumen')->where('id', $d->id)->update(['kode_form' => '07-006-DH']);
                }
            } elseif ($isDiv) {
                $targetStatus = 'menunggu_div_head';
            } elseif ($isDept) {
                $targetStatus = 'menunggu_dept_head';
            } elseif ($hasForeman) {
                $targetStatus = 'menunggu_foreman';
            } else {
                $targetStatus = 'menunggu_section_head';
            }

            $docUpdate = [];
            if ($d->status !== $targetStatus) {
                $docUpdate['status'] = $targetStatus;
            }
            if (empty($d->nrp_foreman) && !empty($k->nrp_foreman)) {
                $docUpdate['nrp_foreman'] = $k->nrp_foreman;
                $fmUser = DB::table('karyawan')->where('nrp', $k->nrp_foreman)->first();
                if ($fmUser) {
                    $docUpdate['nama_foreman'] = $fmUser->nama_lengkap;
                }
            }

            if (!empty($docUpdate)) {
                $docUpdate['updated_at'] = now();
                DB::table('dokumen')->where('id', $d->id)->update($docUpdate);
                $healed++;
            }
        }

        if ($healed > 0) {
            try {
                app(\App\Contracts\SheetRepositoryInterface::class)->clearCache('dokumen');
            } catch (\Throwable $e) {}
            \Illuminate\Support\Facades\Cache::forget('sheet_all_dokumen');
            \Illuminate\Support\Facades\Cache::forget('sheet_all_karyawan');
        }

        return $healed;
    }

    /**
     * Resolusi otomatis relasi Foreman untuk karyawan yang memiliki line_category (FM1, FM2, dll.)
     */
    public static function resolveForemanSubordinates(): int
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('karyawan') || !\Illuminate\Support\Facades\Schema::hasTable('dokumen')) {
            return 0;
        }

        $allKaryawan = DB::table('karyawan')->get();
        if ($allKaryawan->isEmpty()) {
            return 0;
        }

        // Map Foremen by [line_category]
        $fmMap = [];
        foreach ($allKaryawan as $emp) {
            $pos = strtoupper(trim((string)$emp->jabatan));
            $isForemanEmp = (str_contains($pos, 'FOREMAN') || str_contains(strtoupper((string)($emp->level_form ?? '')), 'FOREMAN'))
                && !str_contains($pos, 'SECTION HEAD')
                && !str_contains($pos, 'SECT HEAD')
                && !str_contains($pos, 'DEPT HEAD')
                && !str_contains($pos, 'MANAGER')
                && !str_contains($pos, 'DIV HEAD');

            if ($isForemanEmp) {
                $lc = strtoupper(trim((string)($emp->line_category ?? '')));
                if ($lc !== '' && $lc !== '-' && $lc !== 'NULL') {
                    $deptNorm = User::normalizeDepartmentName($emp->departemen ?? '');
                    $sectNorm = strtoupper(trim(preg_replace('/\s*(SEC|SECTION)\.?$/i', '', (string)($emp->seksi ?: ($emp->nama_sect ?: '')))));

                    if ($sectNorm !== '') {
                        $fmMap[$sectNorm . '|' . $lc] = $emp;
                    }
                    if ($deptNorm !== '') {
                        $fmMap[$deptNorm . '|' . $lc] = $emp;
                    }
                    if (!isset($fmMap[$lc])) {
                        $fmMap[$lc] = $emp;
                    }
                }
            }
        }

        if (empty($fmMap)) {
            return 0;
        }

        $resolvedCount = 0;
        foreach ($allKaryawan as $k) {
            $nrp = trim((string)$k->nrp);
            $posUpper = strtoupper(trim((string)($k->jabatan ?? '')));
            $isUpper = str_contains($posUpper, 'FOREMAN') ||
                str_contains($posUpper, 'SECTION HEAD') ||
                str_contains($posUpper, 'SECT HEAD') ||
                str_contains($posUpper, 'DEPUTY SECTION HEAD') ||
                str_contains($posUpper, 'KASIE') ||
                str_contains($posUpper, 'DEPT HEAD') ||
                str_contains($posUpper, 'DEPARTMENT HEAD') ||
                str_contains($posUpper, 'DIV HEAD') ||
                str_contains($posUpper, 'DIVISION HEAD') ||
                str_contains($posUpper, 'MANAGER') ||
                str_contains($posUpper, 'MANAJER');

            if ($isUpper) {
                continue;
            }

            $lineCat = strtoupper(trim((string)($k->line_category ?? '')));
            if ($lineCat === '' || $lineCat === '-' || $lineCat === 'NULL') {
                continue;
            }

            $currentFmNrp = trim((string)($k->nrp_foreman ?? ''));
            if ($currentFmNrp !== '' && $currentFmNrp !== '-') {
                continue;
            }

            $s = strtoupper(trim((string)($k->seksi ?: ($k->nama_sect ?: ''))));
            $normS = strtoupper(trim(preg_replace('/\s*(SEC|SECTION)\.?$/i', '', $s)));
            $d = User::normalizeDepartmentName($k->departemen ?? '');

            $matchedFm = $fmMap[$normS . '|' . $lineCat] ?? ($fmMap[$d . '|' . $lineCat] ?? ($fmMap[$lineCat] ?? null));
            if ($matchedFm && strcasecmp($matchedFm->nrp, $nrp) !== 0) {
                // Update karyawan table
                DB::table('karyawan')->where('nrp', $nrp)->update([
                    'nrp_foreman' => $matchedFm->nrp,
                    'updated_at' => now(),
                ]);

                // Update dokumen if exists and not evaluated
                $existingDoc = DB::table('dokumen')->where('nrp', $nrp)->first();
                if ($existingDoc) {
                    $hasShTtd = DB::table('tanda_tangan')->where('dokumen_id', $existingDoc->id)->where('level', 'section_head')->exists();
                    $hasFmTtd = DB::table('tanda_tangan')->where('dokumen_id', $existingDoc->id)->where('level', 'foreman')->exists();
                    $hasScore = DB::table('nilai')->where('dokumen_id', $existingDoc->id)->exists();

                    $updateDoc = [
                        'nrp_foreman' => $matchedFm->nrp,
                        'nama_foreman' => $matchedFm->nama_lengkap,
                        'updated_at' => now(),
                    ];

                    if (!$hasShTtd && !$hasFmTtd && !$hasScore && empty($existingDoc->total_nilai)) {
                        $updateDoc['status'] = 'menunggu_foreman';
                    }

                    DB::table('dokumen')->where('id', $existingDoc->id)->update($updateDoc);
                }
                $resolvedCount++;
            }
        }

        if ($resolvedCount > 0) {
            try {
                app(\App\Repositories\SheetRepository::class)->clearCache('karyawan');
                app(\App\Repositories\SheetRepository::class)->clearCache('dokumen');
            } catch (\Throwable $e) {}
        }

        return $resolvedCount;
    }
}