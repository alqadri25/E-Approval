<?php

namespace App\Http\Controllers;

use App\Contracts\SheetRepositoryInterface;
use App\Models\User;
use App\Services\AppraisalCalculationService;
use App\Services\ExcelKaryawanReader;
use App\Services\GoogleSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SummaryPKController extends Controller
{
    protected SheetRepositoryInterface $repository;
    protected AppraisalCalculationService $calcService;

    public function __construct(SheetRepositoryInterface $repository, AppraisalCalculationService $calcService)
    {
        $this->repository = $repository;
        $this->calcService = $calcService;
    }

    /**
     * Display Summary PK calibration & docking dashboard
     */
    public function index(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        // 1. Determine active department & access control
        $allKaryawan = $this->repository->all('karyawan');
        $candidateDepts = ['PROD 2', 'PROD 1', 'ENGINEERING', 'QA/QC', 'MACHINING', 'HR'];
        $allAvailableDepts = array_values(array_unique(array_merge(
            array_filter(array_column($allKaryawan, 'departemen')),
            $candidateDepts
        )));

        // Filter based on user access
        $distinctDepts = array_values(array_filter($allAvailableDepts, function($d) use ($user) {
            return $user->canAccessDepartment($d);
        }));

        if (empty($distinctDepts)) {
            $distinctDepts = [$user->departemen ?: 'PROD 2'];
        }

        $selectedDept = $request->input('departemen');
        if (!$selectedDept || !in_array($selectedDept, $distinctDepts)) {
            $selectedDept = in_array('PROD 2', $distinctDepts) ? 'PROD 2' : $distinctDepts[0];
        }

        // Department access check
        if (!$user->canAccessDepartment($selectedDept)) {
            abort(403, "Anda tidak memiliki hak akses ke departemen {$selectedDept}.");
        }

        // 2. Filter employees by department
        $deptKaryawan = array_values(array_filter($allKaryawan, function ($k) use ($selectedDept) {
            return strcasecmp($k['departemen'] ?? '', $selectedDept) === 0;
        }));

        // 3. Fetch documents & group score data BEFORE target/matrix counting
        $allDocs = $this->repository->all('dokumen');
        $docsByNrp = [];
        foreach ($allDocs as $doc) {
            $docsByNrp[$doc['nrp']] = $doc;
        }

        // Count ONLY quota-bound employees per KJ (exclude 07-006 SH/DH/DivHead forms)
        $actualCountByKj = [
            'KJ 2' => 0,
            'KJ 3' => 0,
            'KJ 4' => 0,
            'KJ 5' => 0,
        ];
        foreach ($deptKaryawan as $k) {
            $doc = $docsByNrp[$k['nrp']] ?? null;
            if (PenilaianController::isQuotaExemptEmployee($k, $doc)) {
                continue;
            }
            $kj = ExcelKaryawanReader::normalizeQuotaKj($k['kj'] ?? 'KJ 2');
            if (!isset($actualCountByKj[$kj])) {
                $actualCountByKj[$kj] = 0;
            }
            $actualCountByKj[$kj]++;
        }

        $kjList = ['KJ 2', 'KJ 3', 'KJ 4', 'KJ 5'];
        $extraKjs = array_filter(
            array_keys($actualCountByKj),
            fn ($kjCode) => !in_array($kjCode, $kjList, true) && ($actualCountByKj[$kjCode] ?? 0) > 0
        );
        sort($extraKjs, SORT_NATURAL);
        $kjList = array_values(array_merge($kjList, $extraKjs));

        $allNilai = $this->repository->all('nilai');
        $nilaiGrouped = [];
        foreach ($allNilai as $n) {
            $docId = $n['dokumen_id'];
            $lvl = $n['level'] ?? 'foreman';
            $aspek = $n['aspek_kode'];
            $nilaiGrouped[$docId][$lvl][$aspek] = (float)$n['skor'];
        }

        // 4. Build Detailed Employee Summary Rows
        $summaryRows = [];
        $no = 1;

        // Stats counters for PK ORIGINAL and DOCKING PK (dynamic across all operational KJs)
        $matrixOriginal = [];
        $matrixDocking = [];
        foreach ($kjList as $kjItem) {
            $matrixOriginal[$kjItem] = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0, 'Total' => 0];
            $matrixDocking[$kjItem] = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0, 'Total' => 0];
        }

        foreach ($deptKaryawan as $k) {
            $nrp = $k['nrp'];
            $doc = $docsByNrp[$nrp] ?? null;
            $docId = $doc['id'] ?? null;
            $kodeForm = $doc['kode_form'] ?? PenilaianController::determineKodeForm($k);
            $isQuotaExempt = PenilaianController::isQuotaExemptEmployee($k, $doc);

            // Get scores from dept_head (final) or foreman
            $scores = [];
            if ($docId && isset($nilaiGrouped[$docId])) {
                $scores = $nilaiGrouped[$docId]['dept_head'] 
                    ?? $nilaiGrouped[$docId]['section_head'] 
                    ?? $nilaiGrouped[$docId]['foreman'] 
                    ?? [];
            }

            $calc = $this->calcService->calculate($kodeForm, $scores);
            $totalNilai = $doc['total_nilai'] ?? $calc['grand_total'];
            $gradeOriginal = $doc['grade_original'] ?? ($totalNilai > 0 ? $this->calcService->scoreToGrade($totalNilai) : 'K');
            $dockingFinal = $doc['docking_final'] ?? $gradeOriginal;

            $kjKey = ExcelKaryawanReader::normalizeQuotaKj($k['kj'] ?? 'KJ 2');
            if (!$isQuotaExempt && !isset($matrixOriginal[$kjKey])) {
                $matrixOriginal[$kjKey] = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0, 'Total' => 0];
                $matrixDocking[$kjKey] = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0, 'Total' => 0];
                $kjList[] = $kjKey;
            }

            // Increment quota matrices ONLY for quota-bound employees (exclude 07-006 forms)
            if (!$isQuotaExempt && ($totalNilai > 0 || ($doc['status'] ?? '') === 'selesai')) {
                if (isset($matrixOriginal[$kjKey][$gradeOriginal])) {
                    $matrixOriginal[$kjKey][$gradeOriginal]++;
                    $matrixOriginal[$kjKey]['Total']++;
                }
            }

            if (!$isQuotaExempt && $dockingFinal && isset($matrixDocking[$kjKey][$dockingFinal])) {
                $matrixDocking[$kjKey][$dockingFinal]++;
                $matrixDocking[$kjKey]['Total']++;
            }

            $summaryRows[] = [
                'no' => $no++,
                'doc_id' => $docId,
                'nrp' => $nrp,
                'nama' => $k['nama_lengkap'],
                'seksi' => $k['seksi'] ?? '-',
                'departemen' => $k['departemen'],
                'jabatan' => $k['jabatan'] ?? '-',
                'start_work' => $k['tgl_masuk'] ?? '-',
                'kj' => !empty($k['kj']) ? ExcelKaryawanReader::normalizeKj($k['kj']) : 'KJ 2',
                'quota_kj' => $kjKey,
                'sub_gol' => $k['sub_gol'] ?? '-',
                'kode_form' => $kodeForm,
                'hasil_kerja' => $calc['groups']['hasil_kerja']['weighted_score'] ?? 0.00,
                'proses_kerja' => $calc['groups']['proses_kerja']['weighted_score'] ?? 0.00,
                'sikap_kerja' => $calc['groups']['sikap_kerja']['weighted_score'] ?? 0.00,
                'man_management' => $calc['groups']['man_management']['weighted_score'] ?? 0.00,
                'total_nilai' => $totalNilai,
                'grade_original' => $gradeOriginal,
                'docking_final' => $dockingFinal,
                'status' => $doc['status'] ?? 'draft',
            ];
        }

        // 5. Fetch or Auto-Sync Target Kuota from kuota_departemen
        $targetKuotaRecords = DB::table('kuota_departemen')
            ->where('departemen', $selectedDept)
            ->get()
            ->keyBy('kj');

        // Check if any KJ records are missing or department-wide target sum doesn't match actual operational headcount
        // Note: Individual KJ rows may deliberately differ slightly from raw KJ headcount (e.g. PROD 2 Excel targets: 64/82/21/4 = 171)
        $existingDeptTotal = (int)$targetKuotaRecords->sum('total_karyawan');
        $actualDeptTotal = array_sum($actualCountByKj);
        $missingKjs = [];
        foreach ($kjList as $kj) {
            if (!$targetKuotaRecords->get($kj)) {
                $missingKjs[] = $kj;
            }
        }
        $hasExplicitDeptSetting = DB::table('pengaturan')
            ->whereIn('key', array_values(array_unique([
                'target_kuota_dept_' . strtoupper($selectedDept),
                ...(strtoupper($selectedDept) === 'ENGINEERING' ? ['target_kuota_div_DIV-E'] : []),
            ])))
            ->exists();
        $needsSync = !empty($missingKjs) || (!$hasExplicitDeptSetting && $actualDeptTotal > 0 && $existingDeptTotal !== $actualDeptTotal);

        if ($needsSync) {
            $pctSetting = DB::table('pengaturan')->where('key', 'target_kuota_pct')->value('value');
            $pct = $pctSetting ? json_decode($pctSetting, true) : ['S' => 10, 'A' => 30, 'B' => 30, 'C' => 20, 'K' => 10];
            $kjsToUpdate = ($actualDeptTotal > 0 && $existingDeptTotal !== $actualDeptTotal) ? $kjList : $missingKjs;

            foreach ($kjsToUpdate as $kj) {
                $actualTotal = $actualCountByKj[$kj] ?? 0;
                $existing = $targetKuotaRecords->get($kj);

                if ($actualTotal === 0) {
                    $s = 0; $a = 0; $b = 0; $c = 0; $k = 0;
                } else {
                    $s = (int)round($actualTotal * ($pct['S'] ?? 10) / 100);
                    $a = (int)round($actualTotal * ($pct['A'] ?? 30) / 100);
                    $c = (int)round($actualTotal * ($pct['C'] ?? 20) / 100);
                    $k = (int)round($actualTotal * ($pct['K'] ?? 10) / 100);
                    $b = max(0, $actualTotal - ($s + $a + $c + $k));
                    $diff = $actualTotal - ($s + $a + $b + $c + $k);
                    if ($diff !== 0) {
                        $b += $diff;
                    }
                }

                DB::table('kuota_departemen')->updateOrInsert(
                    ['departemen' => $selectedDept, 'kj' => $kj],
                    [
                        'kuota_s' => $s,
                        'kuota_a' => $a,
                        'kuota_b' => $b,
                        'kuota_c' => $c,
                        'kuota_k' => $k,
                        'total_karyawan' => $actualTotal,
                        'updated_at' => now(),
                    ]
                );
            }

            $targetKuotaRecords = DB::table('kuota_departemen')
                ->where('departemen', $selectedDept)
                ->get()
                ->keyBy('kj');
        }

        $targetKuota = $targetKuotaRecords;

        // 6. Compute Quota vs Realization Comparison
        $sumTargetByGrade = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];
        foreach ($targetKuota as $q) {
            $sumTargetByGrade['S'] += ($q->kuota_s ?? 0);
            $sumTargetByGrade['A'] += ($q->kuota_a ?? 0);
            $sumTargetByGrade['B'] += ($q->kuota_b ?? 0);
            $sumTargetByGrade['C'] += ($q->kuota_c ?? 0);
            $sumTargetByGrade['K'] += ($q->kuota_k ?? 0);
        }

        $sumRealByGrade = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];
        foreach ($matrixOriginal as $mRow) {
            $sumRealByGrade['S'] += ($mRow['S'] ?? 0);
            $sumRealByGrade['A'] += ($mRow['A'] ?? 0);
            $sumRealByGrade['B'] += ($mRow['B'] ?? 0);
            $sumRealByGrade['C'] += ($mRow['C'] ?? 0);
            $sumRealByGrade['K'] += ($mRow['K'] ?? 0);
        }

        $quotaComparison = [];
        $hasOverQuota = false;
        $overQuotaGrades = [];
        foreach (['S', 'A', 'B', 'C', 'K'] as $g) {
            $tgt = $sumTargetByGrade[$g];
            $real = $sumRealByGrade[$g];
            $diff = $real - $tgt;
            if ($diff > 0) {
                $hasOverQuota = true;
                $overQuotaGrades[] = "Grade {$g} (+{$diff})";
            }
            $quotaComparison[$g] = [
                'target' => $tgt,
                'realisasi' => $real,
                'selisih' => $diff,
                'status' => $diff > 0 ? 'over' : ($diff === 0 ? 'pas' : 'sisa'),
            ];
        }

        return view('summary.index', compact(
            'distinctDepts',
            'selectedDept',
            'summaryRows',
            'kjList',
            'matrixOriginal',
            'matrixDocking',
            'targetKuota',
            'actualCountByKj',
            'quotaComparison',
            'hasOverQuota',
            'overQuotaGrades'
        ));
    }

    /**
     * Update target kuota (S, A, B, C, K) per KJ for the department
     */
    public function updateKuota(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $dept = trim($request->input('departemen', ''));
        if (!$dept || !$user->canAccessDepartment($dept)) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Anda tidak memiliki hak akses untuk departemen ini.'], 403);
            }
            return redirect()->back()->with('error', 'Anda tidak memiliki hak akses untuk departemen ini.');
        }

        $kuotaData = $request->input('kuota', []);
        $kjList = array_values(array_unique(array_merge(['KJ 2', 'KJ 3', 'KJ 4', 'KJ 5'], array_keys(is_array($kuotaData) ? $kuotaData : []))));

        foreach ($kjList as $kj) {
            if (isset($kuotaData[$kj])) {
                $row = $kuotaData[$kj];
                $s = max(0, (int)($row['s'] ?? 0));
                $a = max(0, (int)($row['a'] ?? 0));
                $b = max(0, (int)($row['b'] ?? 0));
                $c = max(0, (int)($row['c'] ?? 0));
                $k = max(0, (int)($row['k'] ?? 0));
                $total = $s + $a + $b + $c + $k;

                DB::table('kuota_departemen')->updateOrInsert(
                    ['departemen' => $dept, 'kj' => $kj],
                    [
                        'kuota_s' => $s,
                        'kuota_a' => $a,
                        'kuota_b' => $b,
                        'kuota_c' => $c,
                        'kuota_k' => $k,
                        'total_karyawan' => $total,
                        'updated_at' => now(),
                    ]
                );
            }
        }

        // Sync total department target into pengaturan so Admin, Dashboard, Riwayat, Laporan, & Penyesuaian stay aligned
        $deptUpper = strtoupper($dept);
        $savedDeptRows = DB::table('kuota_departemen')->where('departemen', $dept)->get();
        if ($savedDeptRows->isNotEmpty()) {
            $syncS = (int)$savedDeptRows->sum('kuota_s');
            $syncA = (int)$savedDeptRows->sum('kuota_a');
            $syncB = (int)$savedDeptRows->sum('kuota_b');
            $syncC = (int)$savedDeptRows->sum('kuota_c');
            $syncK = (int)$savedDeptRows->sum('kuota_k');
            $syncPayload = json_encode([
                'S' => $syncS,
                'A' => $syncA,
                'B' => $syncB,
                'C' => $syncC,
                'K' => $syncK,
                'total' => $syncS + $syncA + $syncB + $syncC + $syncK,
            ]);
            DB::table('pengaturan')->updateOrInsert(
                ['key' => 'target_kuota_dept_' . $deptUpper],
                ['value' => $syncPayload, 'updated_at' => now()]
            );
            if ($deptUpper === 'ENGINEERING') {
                DB::table('pengaturan')->updateOrInsert(
                    ['key' => 'target_kuota_div_DIV-E'],
                    ['value' => $syncPayload, 'updated_at' => now()]
                );
            }
        }

        $msg = "Target kuota nilai untuk Departemen {$dept} berhasil disimpan!";

        // Realtime sync ke Google Spreadsheet untuk target kuota dan pengaturan
        try {
            GoogleSyncService::syncTables(['kuota_departemen', 'pengaturan', 'audit_log']);
        } catch (\Throwable $e) {}

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }

        return redirect()->route('summary.index', ['departemen' => $dept])->with('success', $msg);
    }

    /**
     * Update docking final grade for an employee/document
     */
    public function updateDocking(Request $request)
    {
        $docId = $request->input('dokumen_id');
        $docking = strtoupper(trim($request->input('docking_final', '')));

        if (!in_array($docking, ['S', 'A', 'B', 'C', 'K'])) {
            return response()->json(['success' => false, 'message' => 'Grade docking tidak valid.'], 422);
        }

        $doc = DB::table('dokumen')->where('id', $docId)->first();
        if (!$doc) {
            return response()->json(['success' => false, 'message' => 'Dokumen tidak ditemukan.'], 404);
        }

        DB::table('dokumen')->where('id', $docId)->update([
            'docking_final' => $docking,
            'updated_at' => now(),
        ]);
        $this->repository->clearCache('dokumen');

        // Realtime sync ke Google Spreadsheet untuk update docking dokumen
        try {
            GoogleSyncService::syncTables(['dokumen', 'audit_log']);
        } catch (\Throwable $e) {}

        return response()->json([
            'success' => true, 
            'message' => "Docking berhasil diubah menjadi {$docking}."
        ]);
    }

    /**
     * Export Summary PK to CSV / Excel compatible format
     */
    public function export(Request $request)
    {
        $dept = $request->input('departemen', 'PROD 2');
        $filename = "Summary_PK_{$dept}_" . date('Ymd_His') . ".csv";

        $allKaryawan = $this->repository->all('karyawan');
        $allDocs = $this->repository->all('dokumen');
        $docsByNrp = [];
        foreach ($allDocs as $doc) {
            $docsByNrp[$doc['nrp']] = $doc;
        }

        $filteredKaryawan = array_filter($allKaryawan, fn($k) => strcasecmp($k['departemen'] ?? '', $dept) === 0);

        $response = new StreamedResponse(function () use ($filteredKaryawan, $docsByNrp) {
            $handle = fopen('php://output', 'w');
            // Add UTF-8 BOM
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // Header Row (Matches consistent naming standard)
            fputcsv($handle, [
                'NO', 'NRP', 'NAMA LENGKAP', 'SECTION', 'DEPARTMENT', 'POSITION',
                'START WORK', 'KJ', 'GOLONGAN', 'HASIL KERJA', 'PROSES KERJA',
                'SIKAP KERJA', 'MAN MANAGEMENT', 'TOTAL NILAI', 'NILAI PK ORIGINAL',
                'DOCKING FINAL', 'STATUS WORKFLOW'
            ]);

            $no = 1;
            foreach ($filteredKaryawan as $k) {
                $doc = $docsByNrp[$k['nrp']] ?? null;
                fputcsv($handle, [
                    $no++,
                    $k['nrp'],
                    $k['nama_lengkap'],
                    $k['seksi'] ?? '-',
                    $k['departemen'],
                    $k['jabatan'] ?? '-',
                    $k['tgl_masuk'] ?? '-',
                    $k['kj'] ?? 'KJ 2',
                    $k['sub_gol'] ?? '-',
                    0, 0, 0, 0, // aspect weights
                    $doc['total_nilai'] ?? 0,
                    $doc['grade_original'] ?? 'K',
                    $doc['docking_final'] ?? ($doc['grade_original'] ?? 'K'),
                    $doc['status'] ?? 'draft',
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', "attachment; filename=\"{$filename}\"");

        return $response;
    }
}

