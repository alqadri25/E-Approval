<?php

namespace App\Http\Controllers;

use App\Contracts\SheetRepositoryInterface;
use App\Models\User;
use App\Services\AppraisalCalculationService;
use App\Services\ExcelKaryawanReader;
use App\Services\GoogleClientService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    protected SheetRepositoryInterface $repository;
    protected GoogleClientService $googleService;
    protected AppraisalCalculationService $calcService;

    public function __construct(
        SheetRepositoryInterface $repository, 
        GoogleClientService $googleService,
        AppraisalCalculationService $calcService
    ) {
        $this->repository = $repository;
        $this->googleService = $googleService;
        $this->calcService = $calcService;
    }

    public function index(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        // Sync dari Google Spreadsheet hanya jika diminta secara eksplisit via ?sync=1
        if ($request->boolean('sync')) {
            try {
                \App\Services\GoogleSyncService::syncOperationalTablesFromGoogle(true);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Sync from Google on dashboard: " . $e->getMessage());
            }
        }

        // 1. Employee query (department filtered if department user)
        $allKaryawan = $this->repository->all('karyawan');
        $allDepts = array_values(array_unique(array_filter(array_map(fn($k) => $k['departemen'] ?? '', $allKaryawan))));
        sort($allDepts);

        $selectedDept = $request->query('departemen');
        if (!$user->isSuperadmin() && !$user->isAdmin() && !$user->isHr()) {
            $selectedDept = $user->departemen;
        }

        // Map employees by NRP for quick access
        $karyawanMap = [];
        foreach ($allKaryawan as $k) {
            $karyawanMap[$k['nrp']] = $k;
        }

        // 1. Bersihkan dokumen orphan hanya jika ada ketidaksesuaian jumlah data (< 1ms check)
        $activeNrps = array_unique(array_filter(array_map('strval', array_column($allKaryawan, 'nrp'))));
        if (empty($activeNrps)) {
            DB::table('dokumen')->delete();
            if (Schema::hasTable('nilai')) DB::table('nilai')->delete();
            if (Schema::hasTable('indikator')) DB::table('indikator')->delete();
            if (Schema::hasTable('tanda_tangan')) DB::table('tanda_tangan')->delete();
            if (Schema::hasTable('signature')) DB::table('signature')->delete();
            if (Schema::hasTable('history_status')) DB::table('history_status')->delete();
            $this->repository->clearCache('dokumen');
            $this->repository->clearCache('nilai');
        } elseif (DB::table('dokumen')->count() > count($activeNrps)) {
            $orphanDocIds = DB::table('dokumen')->whereNotIn('nrp', $activeNrps)->pluck('id')->toArray();
            if (!empty($orphanDocIds)) {
                if (Schema::hasTable('nilai')) DB::table('nilai')->whereIn('dokumen_id', $orphanDocIds)->delete();
                if (Schema::hasTable('indikator')) DB::table('indikator')->whereIn('dokumen_id', $orphanDocIds)->delete();
                if (Schema::hasTable('tanda_tangan')) DB::table('tanda_tangan')->whereIn('dokumen_id', $orphanDocIds)->delete();
                if (Schema::hasTable('signature')) DB::table('signature')->whereIn('dokumen_id', $orphanDocIds)->delete();
                if (Schema::hasTable('history_status')) DB::table('history_status')->whereIn('dokumen_id', $orphanDocIds)->delete();
                DB::table('dokumen')->whereIn('id', $orphanDocIds)->delete();
                $this->repository->clearCache('dokumen');
                $this->repository->clearCache('nilai');
            }
        }

        // 2. Documents query & scores
        $allDocs = $this->repository->all('dokumen');
        $docsByNrp = [];
        foreach ($allDocs as $doc) {
            $docsByNrp[(string)$doc['nrp']] = $doc;
        }

        $missingDocEmployees = [];
        foreach ($allKaryawan as $k) {
            $nrpStr = (string)($k['nrp'] ?? '');
            if ($nrpStr !== '' && !isset($docsByNrp[$nrpStr])) {
                $missingDocEmployees[] = $k;
            }
        }

        if (!empty($missingDocEmployees)) {
            $maxDocId = (int)(DB::table('dokumen')->max('id') ?? 0);
            $activePeriodRow = DB::table('periode')->where('status', 'aktif')->first() ?? (object)['id' => 1];
            $nowStr = now()->toDateTimeString();

            foreach ($missingDocEmployees as $k) {
                $maxDocId++;
                $expectedKodeForm = PenilaianController::determineKodeForm($k);
                $isDivEmp = $expectedKodeForm === '07-006-DIV' || str_contains(strtoupper($k['jabatan'] ?? ''), 'DIV HEAD') || str_contains(strtoupper($k['jabatan'] ?? ''), 'DIVISION HEAD');
                $isSecretaryEmp = str_contains(strtoupper($k['seksi'] ?? ''), 'SECRETARY') || str_contains(strtoupper($k['jabatan'] ?? ''), 'SECRETARY') || in_array((string)($k['nrp'] ?? ''), ['268', '1237']);
                $isDeptEmp = $expectedKodeForm === '07-006-DH' || ((str_contains(strtoupper($k['jabatan'] ?? ''), 'DEPT HEAD') || str_contains(strtoupper($k['jabatan'] ?? ''), 'DEPARTMENT HEAD') || str_contains(strtoupper($k['jabatan'] ?? ''), 'MANAGER')) && !str_contains(strtoupper($k['jabatan'] ?? ''), 'NON KASIE'));

                if ($isDivEmp) {
                    $status = 'menunggu_div_head';
                } elseif ($isDeptEmp || $isSecretaryEmp) {
                    $status = 'menunggu_dept_head';
                } elseif (!empty($k['nrp_foreman'])) {
                    $status = 'menunggu_foreman';
                } else {
                    $status = 'menunggu_section_head';
                }

                $newDoc = [
                    'id' => $maxDocId,
                    'periode_id' => $activePeriodRow->id ?? 1,
                    'nrp' => $k['nrp'],
                    'kode_form' => $expectedKodeForm,
                    'status' => $status,
                    'total_nilai' => null,
                    'grade_original' => null,
                    'docking_final' => null,
                    'kode_verifikasi' => 'VERIF-' . strtoupper(\Illuminate\Support\Str::random(10)),
                    'created_at' => $nowStr,
                    'updated_at' => $nowStr,
                ];

                DB::table('dokumen')->insert($newDoc);
                $docsByNrp[(string)$k['nrp']] = $newDoc;
            }
            $this->repository->clearCache('dokumen');
            $allDocs = $this->repository->all('dokumen');
        }

        $allNilai = $this->repository->all('nilai');
        $nilaiGrouped = [];
        foreach ($allNilai as $n) {
            $docId = $n['dokumen_id'];
            $lvl = $n['level'] ?? 'foreman';
            $aspek = $n['aspek_kode'];
            $nilaiGrouped[$docId][$lvl][$aspek] = (float)$n['skor'];
        }

        $allTtd = $this->repository->all('tanda_tangan');
        $ttdGrouped = [];
        foreach ($allTtd as $t) {
            $docId = $t['dokumen_id'] ?? null;
            $lvl = $t['level'] ?? '';
            if ($docId && $lvl) {
                $ttdGrouped[$docId][$lvl] = $t;
            }
        }

        $activePeriod = $this->repository->find('periode', 'status', 'aktif');

        // =========================================================================
        // A. FOREMAN DASHBOARD LOGIC
        // =========================================================================
        $foremanSubordinates = [];
        $gradeCounts = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];
        $targetCounts = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];
        $totalDinilai = 0;
        $foremanNeedActionCount = 0;
        $foremanDoneCount = 0;
        $foremanDraftCount = 0;
        $foremanBelumCount = 0;
        $foremanRevisiCount = 0;
        $foremanSelesaiCount = 0;
        $foremanSudahDinilaiCount = 0;
        $foremanShSudahDinilai = 0;
        $foremanShBelumDinilai = 0;

        if ($user->isForeman()) {
            // Filter strictly to subordinates of this specific Foreman (termasuk evaluasi diri sendiri)
            $mySubordinates = array_values(array_filter($allKaryawan, function ($k) use ($user) {
                if (!empty($k['nrp_foreman']) && strcasecmp($k['nrp_foreman'], $user->nrp) === 0) {
                    return true;
                }
                return strcasecmp($k['nrp'] ?? '', $user->nrp) === 0;
            }));

            foreach ($mySubordinates as $k) {
                $nrp = $k['nrp'];
                $doc = $docsByNrp[$nrp] ?? null;
                $docId = $doc['id'] ?? null;
                $status = $doc['status'] ?? 'belum_ada';

                // Check foreman scores
                $foremanScores = ($docId && isset($nilaiGrouped[$docId]['foreman'])) 
                    ? $nilaiGrouped[$docId]['foreman'] 
                    : [];

                $calc = !empty($foremanScores) 
                    ? $this->calcService->calculate($doc['kode_form'] ?? '07-001', $foremanScores) 
                    : ['grand_total' => 0.00];

                $score = $doc['total_nilai'] ?? $calc['grand_total'];
                $isEvaluated = ($status !== 'belum_ada' && $status !== 'draft' && $status !== 'menunggu_foreman') || ($score > 0);

                $grade = '-';
                if ($isEvaluated && $score > 0) {
                    $grade = $this->calcService->scoreToGrade($score);
                    $totalDinilai++;
                    if (isset($gradeCounts[$grade])) {
                        $gradeCounts[$grade]++;
                    }
                }

                // Categorize Action & Workflow Stage for Foreman
                $actionType = 'belum_dinilai';
                $actionLabel = 'Belum Dinilai';
                $stageLabel = 'Foreman';

                $isSelf = (strcasecmp($nrp, $user->nrp) === 0);

                if ($isSelf) {
                    // Foreman KJ 5 ke atas (Form 07-006 / 07-004) dinilai langsung oleh Section Head -> Dept Head -> Div Head.
                    // Dokumen Foreman tidak memerlukan tindakan penilaian mandiri oleh Foreman sendiri.
                    if ($status === 'selesai') {
                        $actionType = 'sudah_dikirim';
                        $actionLabel = 'Selesai & Arsip';
                        $stageLabel = 'Selesai (Disetujui)';
                        $foremanDoneCount++;
                        $foremanSelesaiCount++;
                        $foremanShSudahDinilai++;
                    } elseif ($status === 'menunggu_dept_head' || $status === 'menunggu_div_head') {
                        $actionType = 'sudah_dikirim';
                        $actionLabel = 'Proses Dept Head';
                        $stageLabel = $status === 'menunggu_div_head' ? 'Division Head' : 'Department Head';
                        $foremanDoneCount++;
                        $foremanShSudahDinilai++;
                    } else {
                        // Menunggu Section Head
                        $actionType = 'sudah_dikirim';
                        $actionLabel = 'Terkirim ke Section Head';
                        $stageLabel = 'Section Head';
                        $foremanDoneCount++;
                        $hasShScore = ($docId && isset($nilaiGrouped[$docId]['section_head']) && !empty($nilaiGrouped[$docId]['section_head']));
                        if ($hasShScore) {
                            $foremanShSudahDinilai++;
                        } else {
                            $foremanShBelumDinilai++;
                        }
                    }
                } else {
                    // Karyawan Bawahan Foreman (Operator / Staff KJ 1-4)
                    if ($status === 'revisi') {
                        $actionType = 'revisi';
                        $actionLabel = 'Perlu Revisi';
                        $stageLabel = 'Revisi dari Section Head';
                        $foremanRevisiCount++;
                        $foremanNeedActionCount++;
                    } elseif ($status === 'selesai') {
                        $actionType = 'sudah_dikirim';
                        $actionLabel = 'Selesai & Arsip';
                        $stageLabel = 'Selesai (Disetujui)';
                        $foremanDoneCount++;
                        $foremanSelesaiCount++;
                        $foremanShSudahDinilai++;
                    } elseif ($status === 'menunggu_dept_head' || $status === 'menunggu_div_head') {
                        $actionType = 'sudah_dikirim';
                        $actionLabel = 'Proses Dept Head';
                        $stageLabel = $status === 'menunggu_div_head' ? 'Division Head' : 'Department Head';
                        $foremanDoneCount++;
                        $foremanShSudahDinilai++;
                    } elseif ($status === 'menunggu_section_head') {
                        $actionType = 'sudah_dikirim';
                        $actionLabel = 'Terkirim ke Section Head';
                        $stageLabel = 'Section Head';
                        $foremanDoneCount++;
                        $hasShScore = ($docId && isset($nilaiGrouped[$docId]['section_head']) && !empty($nilaiGrouped[$docId]['section_head']));
                        if ($hasShScore) {
                            $foremanShSudahDinilai++;
                        } else {
                            $foremanShBelumDinilai++;
                        }
                    } elseif ($status === 'draft' || ($isEvaluated && ($status === 'menunggu_foreman' || $status === 'belum_ada'))) {
                        $actionType = 'draft';
                        $actionLabel = 'Draft Tersimpan';
                        $stageLabel = 'Draft (Belum TTD)';
                        $foremanDraftCount++;
                        $foremanNeedActionCount++;
                    } else {
                        $actionType = 'belum_dinilai';
                        $actionLabel = 'Belum Dinilai';
                        $stageLabel = 'Belum Dinilai';
                        $foremanBelumCount++;
                        $foremanNeedActionCount++;
                    }
                }

                $rawSect = trim($k['nama_sect'] ?? '');
                if (!$rawSect) {
                    $rawS = trim($k['seksi'] ?? '');
                    $rawSUpper = strtoupper($rawS);
                    $deptUpper = strtoupper(trim($k['departemen'] ?? 'PROD 2'));
                    if (str_contains($rawSUpper, '2') || str_contains($rawSUpper, 'FACT-2')) {
                        $rawSect = 'FACTORY 2';
                    } elseif (str_contains($deptUpper, 'ENGINEERING') || str_contains($rawSUpper, 'ENG')) {
                        $rawSect = 'ENGINEERING';
                    } elseif (str_contains($deptUpper, 'QA') || str_contains($rawSUpper, 'QA')) {
                        $rawSect = 'QA';
                    } elseif (str_contains($deptUpper, 'QC') || str_contains($rawSUpper, 'QC')) {
                        $rawSect = 'QC';
                    } else {
                        $clean = trim(preg_replace('/\s+SEC\.?$/i', '', $rawS));
                        $rawSect = ($clean && $clean !== '-') ? $clean : 'FACTORY 1';
                    }
                }

                $foremanSubordinates[] = [
                    'nrp' => $nrp,
                    'nama_lengkap' => $k['nama_lengkap'],
                    'golongan' => $k['golongan'] ?? '-',
                    'sub_gol' => $k['sub_gol'] ?? '-',
                    'kj' => $k['kj'] ?? 'KJ 2',
                    'jabatan' => $k['jabatan'] ?? '-',
                    'seksi' => $rawSect,
                    'nama_sect' => $rawSect,
                    'nama_bagian' => $k['nama_bagian'] ?? ($k['seksi'] ?? '-'),
                    'tgl_masuk' => $k['tgl_masuk'] ?? '-',
                    'level_form' => $k['level_form'] ?? 'KJ13',
                    'kode_form' => $doc['kode_form'] ?? PenilaianController::determineKodeForm($k),
                    'doc_id' => $docId,
                    'status' => $status,
                    'is_evaluated' => $isEvaluated,
                    'score' => $score > 0 ? number_format($score, 2) : '-',
                    'grade' => $grade,
                    'action_type' => $actionType,
                    'action_label' => $actionLabel,
                    'stage_label' => $stageLabel,
                ];
            }

            usort($foremanSubordinates, fn($a, $b) => strcmp($a['nrp'], $b['nrp']));

            $foremanSudahDinilaiCount = count(array_filter($foremanSubordinates, function ($sub) {
                return in_array($sub['action_type'], ['draft', 'sudah_dikirim']) || !empty($sub['is_evaluated']);
            }));

            // Target distribution calculation (constrained by Admin HR quotas per KJ; non-07-006 only)
            $quotaForemanSubs = array_values(array_filter(
                $foremanSubordinates,
                fn ($sub) => !PenilaianController::isForm07006($sub['kode_form'] ?? null)
            ));
            $foremanCountByKj = [];
            foreach ($quotaForemanSubs as $sub) {
                $kj = ExcelKaryawanReader::normalizeQuotaKj($sub['kj'] ?? 'KJ 2');
                $foremanCountByKj[$kj] = ($foremanCountByKj[$kj] ?? 0) + 1;
            }
            $foremanActualKjMatrix = [];
            foreach ($quotaForemanSubs as $sub) {
                $kj = ExcelKaryawanReader::normalizeQuotaKj($sub['kj'] ?? 'KJ 2');
                if (!isset($foremanActualKjMatrix[$kj])) {
                    $foremanActualKjMatrix[$kj] = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0, 'total' => 0];
                }
                if (!empty($sub['is_evaluated'])) {
                    $grade = $sub['grade'] ?? '-';
                    if (isset($foremanActualKjMatrix[$kj][$grade])) {
                        $foremanActualKjMatrix[$kj][$grade]++;
                        $foremanActualKjMatrix[$kj]['total']++;
                    }
                }
            }
            $foremanKjKeys = array_keys($foremanCountByKj);
            if (empty($foremanKjKeys)) {
                $foremanKjKeys = ['KJ 2', 'KJ 3', 'KJ 4'];
            }
            sort($foremanKjKeys, SORT_NATURAL);
            foreach ($foremanKjKeys as $fkj) {
                if (!isset($foremanActualKjMatrix[$fkj])) {
                    $foremanActualKjMatrix[$fkj] = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0, 'total' => 0];
                }
            }
            uksort($foremanActualKjMatrix, 'strnatcmp');

            $foremanTargetKjMatrix = PenilaianController::buildDirectUnitTargetKjMatrix(
                $foremanKjKeys,
                $foremanCountByKj,
                strtoupper(trim($user->departemen ?? '')),
                (string)(PenilaianController::resolveDivisionCode($user, $foremanSubordinates) ?? '')
            );
            $foremanTargetKjSummary = PenilaianController::summarizeTargetKjMatrix($foremanTargetKjMatrix);
            $targetCounts = [
                'S' => $foremanTargetKjSummary['S'],
                'A' => $foremanTargetKjSummary['A'],
                'B' => $foremanTargetKjSummary['B'],
                'C' => $foremanTargetKjSummary['C'],
                'K' => $foremanTargetKjSummary['K'],
            ];
            $foremanActualKjSummary = PenilaianController::summarizeTargetKjMatrix($foremanActualKjMatrix);
            $totalTargetForeman = $foremanTargetKjSummary['total'];
        }

        // =========================================================================
        // B. SECTION HEAD DASHBOARD LOGIC (Mockup ca08c2af.png)
        // =========================================================================
        $totalTargetSection = 0;
        $totalTargetDept = 0;
        $totalTargetDiv = 0;
        $sectionSubordinates = [];
        $sectionMandiriCount = 0;
        $sectionBawahanCount = 0;
        $rekapForeman = [];
        $sectionTargetCounts = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];
        $foremanGradeCounts = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];
        $sectionGradeCounts = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];
        $sectionTotalDinilai = 0;
        $foremanTotalDinilai = 0;
        $sectionTargetKjMatrix = [];
        $sectionTargetKjSummary = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0, 'total' => 0];
        $sectionActualKjMatrix = [];
        $sectionActualKjSummary = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0, 'total' => 0];
        $hasForemanSubordinates = false;
        $sectionNeedActionCount = 0;
        $sectionDoneCount = 0;
        $sectionDraftCount = 0;
        $sectionBelumCount = 0;
        $sectionRevisiCount = 0;
        $sectionSelesaiCount = 0;
        $sectionSudahDinilaiCount = 0;
        $waitingDeptCount = 0;
        $waitingDivCount = 0;
        $foremanStageCounts = [
            'tahap1' => 0,
            'belum' => 0,
            'draft' => 0,
            'waiting_section' => 0,
            'waiting_dept' => 0,
            'selesai' => 0,
        ];

        if ($user->isSectionHead()) {
            // Find all employees under this Section Head (termasuk evaluasi diri sendiri)
            $sectionSubordinates = array_values(array_filter($allKaryawan, fn ($k) => $user->matchesSectionHeadScope($k)));

            // Check if this Section Head has any Foreman subordinates
            $hasForemanSubordinates = $user->hasForemanSubordinates();

            $enrichedSectionSubordinates = [];
            $foremenGroups = [];

            foreach ($sectionSubordinates as $k) {
                $nrp = $k['nrp'];
                $doc = $docsByNrp[$nrp] ?? null;
                $docId = $doc['id'] ?? null;
                $status = $doc['status'] ?? 'belum_ada';
                $foremanNrp = $k['nrp_foreman'] ?? null;
                $kodeFormDoc = $doc['kode_form'] ?? PenilaianController::determineKodeForm($k);
                $isGol4UpDoc = PenilaianController::isForm07006($kodeFormDoc);
                $isSelfDoc = (strcasecmp($user->nrp, $nrp) === 0);

                // 1. Check Foreman evaluation (hanya jika karyawan memang memiliki Foreman pada form KJ 1-5)
                $foremanScores = (!empty($foremanNrp) && !$isGol4UpDoc && !$isSelfDoc && $docId && isset($nilaiGrouped[$docId]['foreman'])) 
                    ? $nilaiGrouped[$docId]['foreman'] 
                    : [];
                $calcForeman = !empty($foremanScores) 
                    ? $this->calcService->calculate($kodeFormDoc, $foremanScores) 
                    : ['grand_total' => 0.00];
                $fScore = (!empty($foremanNrp) && !$isGol4UpDoc && !$isSelfDoc)
                    ? ($calcForeman['grand_total'] > 0 ? $calcForeman['grand_total'] : ($doc['total_nilai'] ?? 0))
                    : 0;
                $isEvaluatedByForeman = !empty($foremanNrp) && !$isGol4UpDoc && !$isSelfDoc && (($status !== 'belum_ada' && $status !== 'draft' && $status !== 'menunggu_foreman') || ($fScore > 0));
                $foremanGrade = '-';
                if ($isEvaluatedByForeman && $fScore > 0) {
                    $foremanGrade = $this->calcService->scoreToGrade($fScore);
                    $foremanTotalDinilai++;
                    if (isset($foremanGradeCounts[$foremanGrade])) {
                        $foremanGradeCounts[$foremanGrade]++;
                    }
                }

                // 2. Check Section Head evaluation
                // Khusus form 07-006-SH (evaluasi diri Section Head), nilai Section Head disimpan di kolom 'foreman' (Mandiri Kolom 1).
                // Untuk bawahan KJ 1-5 tanpa Foreman, nilai Section Head murni disimpan di kolom 'section_head' (Kolom 2).
                $hasShSign = $docId && (
                    isset($ttdGrouped[$docId]['section_head']) ||
                    ($isSelfDoc && isset($ttdGrouped[$docId]['foreman']))
                );
                $sectionScores = ($docId && isset($nilaiGrouped[$docId]['section_head'])) 
                    ? $nilaiGrouped[$docId]['section_head'] 
                    : (($docId && ($isSelfDoc || $isGol4UpDoc) && isset($nilaiGrouped[$docId]['foreman']))
                        ? $nilaiGrouped[$docId]['foreman']
                        : []);
                $calcSection = !empty($sectionScores) 
                    ? $this->calcService->calculate($kodeFormDoc, $sectionScores) 
                    : ['grand_total' => 0.00];
                $sScore = $calcSection['grand_total'] > 0
                    ? $calcSection['grand_total']
                    : ((!$isSelfDoc && !empty($doc['total_nilai']) && in_array($status, ['menunggu_dept_head', 'menunggu_div_head', 'selesai'])) ? ($doc['total_nilai'] ?? 0) : 0);
                $isEvaluatedBySection = ($calcSection['grand_total'] > 0) || $hasShSign || (!$isSelfDoc && !empty($doc['total_nilai']) && in_array($status, ['menunggu_dept_head', 'menunggu_div_head', 'selesai']));
                $sectionGrade = '-';
                if ($isEvaluatedBySection && $sScore > 0) {
                    $sectionGrade = $this->calcService->scoreToGrade($sScore);
                    $sectionTotalDinilai++;
                    if (!$isGol4UpDoc && isset($sectionGradeCounts[$sectionGrade])) {
                        $sectionGradeCounts[$sectionGrade]++;
                    }
                }

                // Workflow action & stage categorization
                if ($hasForemanSubordinates) {
                    if ($status === 'selesai') {
                        $actionType = 'sudah_dikirim';
                        $actionLabel = 'Selesai & Arsip';
                        $stageLabel = 'Selesai (Disetujui)';
                        $sectionDoneCount++;
                        $sectionSelesaiCount++;
                        $foremanDoneCount++;
                        $foremanStageCounts['selesai']++;
                    } elseif ($status === 'menunggu_dept_head' || $status === 'menunggu_div_head') {
                        if (!$hasShSign && $calcSection['grand_total'] == 0 && empty($doc['total_nilai']) && $status === 'menunggu_dept_head') {
                            $actionType = 'todo';
                            $actionLabel = 'Perlu Review / TTD';
                            $stageLabel = 'Section Head';
                            $sectionBelumCount++;
                            $sectionNeedActionCount++;
                            $foremanDoneCount++;
                            $foremanStageCounts['waiting_section']++;
                        } else {
                            $actionType = 'sudah_dikirim';
                            $actionLabel = 'Terkirim ke Dept Head';
                            $stageLabel = $status === 'menunggu_div_head' ? 'Division Head' : 'Department Head';
                            $sectionDoneCount++;
                            $waitingDeptCount++;
                            $foremanDoneCount++;
                            $foremanStageCounts['waiting_dept']++;
                        }
                    } elseif ($status === 'revisi') {
                        $actionType = 'revisi';
                        $actionLabel = 'Perlu Revisi';
                        $stageLabel = 'Revisi dari Dept Head';
                        $sectionRevisiCount++;
                        $sectionNeedActionCount++;
                        $foremanDoneCount++;
                        $foremanStageCounts['waiting_section']++;
                    } elseif ($status === 'menunggu_section_head') {
                        $foremanDoneCount++;
                        $foremanStageCounts['waiting_section']++;
                        $sectionNeedActionCount++;
                        if ($isEvaluatedBySection || $status === 'draft') {
                            $actionType = 'draft';
                            $actionLabel = 'Draft Tersimpan';
                            $stageLabel = 'Draft Section Head';
                            $sectionDraftCount++;
                        } else {
                            $actionType = 'todo';
                            $actionLabel = 'Perlu Review / TTD';
                            $stageLabel = 'Section Head';
                            $sectionBelumCount++;
                        }
                    } elseif ($status === 'draft') {
                        $actionType = 'menunggu_foreman';
                        $actionLabel = 'Draft Foreman';
                        $stageLabel = 'Draft Foreman';
                        $foremanStageCounts['tahap1']++;
                        $foremanStageCounts['draft']++;
                    } else { // belum_ada or menunggu_foreman
                        $actionType = 'menunggu_foreman';
                        $actionLabel = 'Menunggu Foreman';
                        $stageLabel = 'Foreman';
                        $foremanStageCounts['tahap1']++;
                        $foremanStageCounts['belum']++;
                    }
                } else {
                    // Direct Section Head evaluation (without Foreman):
                    // - Bawahan KJ 1-5: SH (Kolom 2) -> menunggu_dept_head (Tahap 2) -> DH (Kolom 3) -> selesai (Tahap 4)
                    // - Evaluasi Mandiri SH (07-006-SH): SH (Kolom 1) -> menunggu_dept_head (Tahap 2) -> DH (Kolom 2) -> menunggu_div_head (Tahap 3) -> Div Head (Kolom 3) -> selesai (Tahap 4)
                    if ($status === 'revisi') {
                        $actionType = 'revisi';
                        $actionLabel = 'Perlu Revisi';
                        $stageLabel = 'Revisi dari Dept Head';
                        $sectionRevisiCount++;
                        $sectionNeedActionCount++;
                    } elseif ($status === 'selesai') {
                        $actionType = 'sudah_dikirim';
                        $actionLabel = 'Selesai & Arsip';
                        $stageLabel = 'Selesai (Disetujui)';
                        $sectionDoneCount++;
                        $sectionSelesaiCount++;
                    } elseif ($status === 'menunggu_div_head') {
                        $actionType = 'sudah_dikirim';
                        $actionLabel = 'Review Div Head';
                        $stageLabel = 'Division Head';
                        $sectionDoneCount++;
                        $waitingDivCount++;
                    } elseif ($status === 'menunggu_dept_head') {
                        if (!$hasShSign && $calcSection['grand_total'] == 0 && empty($doc['total_nilai'])) {
                            $actionType = 'belum_dinilai';
                            $actionLabel = $isSelfDoc ? 'Evaluasi Mandiri' : 'Belum Dinilai';
                            $stageLabel = $isSelfDoc ? 'Evaluasi Mandiri' : 'Section Head';
                            $sectionBelumCount++;
                            $sectionNeedActionCount++;
                        } else {
                            $actionType = 'sudah_dikirim';
                            $actionLabel = 'Terkirim ke Dept Head';
                            $stageLabel = 'Department Head';
                            $sectionDoneCount++;
                            $waitingDeptCount++;
                        }
                    } elseif ($status === 'draft' || ($isEvaluatedBySection && in_array($status, ['menunggu_section_head', 'menunggu_foreman', 'belum_ada']))) {
                        $actionType = 'draft';
                        $actionLabel = 'Draft Tersimpan';
                        $stageLabel = 'Draft (Belum TTD)';
                        $sectionDraftCount++;
                        $sectionNeedActionCount++;
                    } else {
                        $actionType = 'belum_dinilai';
                        $actionLabel = $isSelfDoc ? 'Evaluasi Mandiri' : 'Belum Dinilai';
                        $stageLabel = $isSelfDoc ? 'Evaluasi Mandiri' : 'Belum Dinilai';
                        $sectionBelumCount++;
                        $sectionNeedActionCount++;
                    }
                }

                $enrichedSectionSubordinates[] = [
                    'nrp' => $nrp,
                    'nama_lengkap' => $k['nama_lengkap'],
                    'golongan' => $k['golongan'] ?? '-',
                    'sub_gol' => $k['sub_gol'] ?? '-',
                    'kj' => $k['kj'] ?? 'KJ 2',
                    'jabatan' => $k['jabatan'] ?? '-',
                    'seksi' => $k['seksi'] ?? '-',
                    'tgl_masuk' => $k['tgl_masuk'] ?? '-',
                    'level_form' => $k['level_form'] ?? PenilaianController::determineLevelForm($k),
                    'kode_form' => $kodeFormDoc,
                    'doc_id' => $docId,
                    'status' => $status,
                    'is_evaluated' => $isEvaluatedBySection,
                    'score' => $sScore > 0 ? number_format($sScore, 2) : '-',
                    'grade' => $sectionGrade,
                    'action_type' => $actionType,
                    'action_label' => $actionLabel,
                    'stage_label' => $stageLabel,
                ];

                if (!empty($foremanNrp)) {
                    if (!isset($foremenGroups[$foremanNrp])) {
                        $foremenGroups[$foremanNrp] = [
                            'nrp' => $foremanNrp,
                            'total' => 0,
                            'dinilai' => 0,
                        ];
                    }
                    $foremenGroups[$foremanNrp]['total']++;
                    if ($isEvaluatedByForeman) {
                        $foremenGroups[$foremanNrp]['dinilai']++;
                    }
                }
            }

            $sectionSubordinates = $enrichedSectionSubordinates;
            usort($sectionSubordinates, fn($a, $b) => strcmp($a['nrp'], $b['nrp']));

            $sectionMandiriCount = count(array_filter($sectionSubordinates, fn($s) => strcasecmp($s['nrp'] ?? '', $user->nrp) === 0));
            $sectionBawahanCount = count($sectionSubordinates) - $sectionMandiriCount;

            $sectionSudahDinilaiCount = count(array_filter($sectionSubordinates, function ($sub) {
                return in_array($sub['action_type'], ['draft', 'sudah_dikirim']) || !empty($sub['is_evaluated']);
            }));

            // Target distribution calculation for Section Head (strictly excludes 07-006 forms; constrained by Admin HR KJ quotas)
            $quotaSectionSubs = array_values(array_filter($sectionSubordinates, fn ($s) => !PenilaianController::isForm07006($s['kode_form'] ?? null)));
            $userDept = strtoupper(trim($user->departemen ?? ''));
            $divCode = (string)(PenilaianController::resolveDivisionCode($user, $sectionSubordinates) ?? '');

            // Calculate Per-KJ Matrix for Section Head Dashboard (strictly excludes 07-006 forms; KJ 5+ maps into KJ 5)
            $subCountByKj = [];
            foreach ($quotaSectionSubs as $s) {
                $kj = ExcelKaryawanReader::normalizeQuotaKj($s['kj'] ?? 'KJ 2');
                $subCountByKj[$kj] = ($subCountByKj[$kj] ?? 0) + 1;
            }
            $kjList = array_keys($subCountByKj);
            sort($kjList, SORT_NATURAL);

            // 1. ACTUAL MATRIX (Realisasi Nilai dari Section Head per KJ, non-07-006)
            foreach ($kjList as $kj) {
                $sectionActualKjMatrix[$kj] = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0, 'total' => 0];
            }
            foreach ($quotaSectionSubs as $s) {
                $kj = ExcelKaryawanReader::normalizeQuotaKj($s['kj'] ?? 'KJ 2');
                if (!isset($sectionActualKjMatrix[$kj])) {
                    $sectionActualKjMatrix[$kj] = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0, 'total' => 0];
                }
                $grade = $s['grade'] ?? '-';
                if (in_array($grade, ['S', 'A', 'B', 'C', 'K'])) {
                    $sectionActualKjMatrix[$kj][$grade]++;
                    $sectionActualKjMatrix[$kj]['total']++;
                    $sectionActualKjSummary[$grade]++;
                    $sectionActualKjSummary['total']++;
                }
            }

            // 2. TARGET MATRIX (Constrained by Admin HR quotas per KJ, non-07-006)
            $sectionTargetKjMatrix = PenilaianController::buildDirectUnitTargetKjMatrix(
                $kjList,
                $subCountByKj,
                $userDept,
                $divCode
            );
            $sectionTargetKjSummary = PenilaianController::summarizeTargetKjMatrix($sectionTargetKjMatrix);
            $sectionTargetCounts = [
                'S' => $sectionTargetKjSummary['S'],
                'A' => $sectionTargetKjSummary['A'],
                'B' => $sectionTargetKjSummary['B'],
                'C' => $sectionTargetKjSummary['C'],
                'K' => $sectionTargetKjSummary['K'],
            ];
            $totalTargetSection = $sectionTargetKjSummary['total'];

            if ($totalTargetSection <= 0) {
                $scopeTargetData = PenilaianController::getDepartmentTargetQuota($user, $sectionSubordinates);
                $totalTargetSection = (int)($scopeTargetData['total'] ?? array_sum($scopeTargetData));
                $sectionTargetKjSummary['total'] = $totalTargetSection;
                foreach (['S', 'A', 'B', 'C', 'K'] as $g) {
                    $sectionTargetCounts[$g] = (int)($scopeTargetData[$g] ?? 0);
                    $sectionTargetKjSummary[$g] = $sectionTargetCounts[$g];
                }
            } elseif (empty($kjList) && ($totalTargetSection ?? 0) > 0) {
                $sectionTargetKjSummary = array_merge($sectionTargetCounts, ['total' => (int)$totalTargetSection]);
            }

            // Build Rekap Penilaian per Foreman
            $allUsers = $this->repository->all('app_users');
            $userByNrp = [];
            foreach ($allUsers as $u) {
                if (!empty($u['nrp'])) {
                    $userByNrp[$u['nrp']] = $u;
                }
            }

            foreach ($foremenGroups as $fNrp => $fData) {
                $u = $userByNrp[$fNrp] ?? null;
                $k = $karyawanMap[$fNrp] ?? null;
                $fName = $u['nama'] ?? ($k['nama_lengkap'] ?? "Foreman ($fNrp)");
                $fNameClean = trim(preg_replace('/\s*\(.*?\)/', '', $fName));

                $pct = $fData['total'] > 0 ? round(($fData['dinilai'] / $fData['total']) * 100) : 0;
                $rekapForeman[] = [
                    'nrp' => $fNrp,
                    'nama_foreman' => $fNameClean,
                    'total_karyawan' => $fData['total'],
                    'sudah_dinilai' => $fData['dinilai'],
                    'persentase' => $pct . '%',
                    'pct_val' => $pct,
                ];
            }
            usort($rekapForeman, fn($a, $b) => strcmp($a['nama_foreman'], $b['nama_foreman']));
            if (!$hasForemanSubordinates) {
                $rekapForeman = [];
            }
        }

        // =========================================================================
        // C. DEPT HEAD DASHBOARD LOGIC (Sesuai Mockup 5. Department Head)
        // =========================================================================
        $deptSubordinates = [];
        $deptTargetCounts = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];
        $deptTargetKjMatrix = [];
        $deptTargetKjSummary = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0, 'total' => 0];
        $deptActualKjMatrix = [];
        $deptActualKjSummary = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0, 'total' => 0];
        $deptSectionGradeCounts = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];
        $deptFinalGradeCounts = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];
        $rekapSection = [];
        $deptTotalKaryawan = 0;
        $deptTotalDinilai = 0;
        $isDeptWithoutForeman = false;
        $deptStageCounts = [
            'tahap1_section' => 0,
            'belum_dinilai_sh' => 0,
            'draft_sh' => 0,
            'revisi_sh' => 0,
            'tahap2_dept' => 0,
            'tahap3_div' => 0,
            'tahap1_section' => 0,
            'tahap1_foreman' => 0,
            'belum_dinilai_fm' => 0,
            'draft_fm' => 0,
            'belum_dinilai_sh' => 0,
            'draft_sh' => 0,
            'revisi_sh' => 0,
            'tahap2_section' => 0,
            'tahap2_dept' => 0,
            'tahap3_dept' => 0,
            'tahap3_div' => 0,
            'tahap4_selesai' => 0,
            'selesai' => 0,
        ];
        $deptSubordinateRows = [];
        $deptDoneCount = 0;
        $deptNeedActionCount = 0;
        $deptWaitingSectionCount = 0;
        $deptForemanTotalDinilai = 0;
        $deptForemanGradeCounts = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];
        $deptSectionTotalDinilai = 0;
        $deptForemanDoneCount = 0;
        $deptSectionDoneCount = 0;
        $deptMandiriCount = 0;
        $deptAnggotaCount = 0;
        $hasAnyForeman = false;

        if ($user->isDepartmentHead()) {
            // Subordinates directly connected to this Dept Head (including self for total department team)
            if ($user->isSecretary()) {
                $deptSubordinates = array_values(array_filter(
                    $allKaryawan,
                    fn ($k) => !empty($user->nrp) && strcasecmp((string)($k['nrp'] ?? ''), (string)$user->nrp) === 0
                ));
            } else {
                $deptSubordinates = array_values(array_filter(
                    $allKaryawan,
                    fn ($k) => $user->matchesDeptHeadScope($k) || (!empty($user->nrp) && strcasecmp((string)($k['nrp'] ?? ''), (string)$user->nrp) === 0)
                ));
            }
            // Pin Dept Head self-evaluation (07-006-DH) first so dashboard matches /penilaian/dept & /penilaian/cari
            usort($deptSubordinates, function ($a, $b) use ($user) {
                $aSelf = !empty($user->nrp) && strcasecmp((string)($a['nrp'] ?? ''), (string)$user->nrp) === 0;
                $bSelf = !empty($user->nrp) && strcasecmp((string)($b['nrp'] ?? ''), (string)$user->nrp) === 0;
                if ($aSelf !== $bSelf) {
                    return $aSelf ? -1 : 1;
                }
                return 0;
            });

            $deptTotalKaryawan = count($deptSubordinates);

            foreach ($deptSubordinates as $subK) {
                if (!empty($subK['nrp_foreman'])) {
                    $hasAnyForeman = true;
                    break;
                }
            }
            $isDeptWithoutForeman = !$hasAnyForeman;

            $sectionGroups = [];
            foreach ($deptSubordinates as $k) {
                $nrp = $k['nrp'];
                $isSelf = (strcasecmp($nrp, $user->nrp) === 0);
                if ($isSelf) {
                    $deptMandiriCount++;
                } else {
                    $deptAnggotaCount++;
                }
                $doc = $docsByNrp[$nrp] ?? null;
                $docId = $doc['id'] ?? null;
                $status = $doc['status'] ?? 'belum_ada';
                $secNrp = $k['nrp_section_head'] ?? ($isSelf ? 'MANDIRI' : '112');
                $seksiName = (!empty($k['seksi']) && $k['seksi'] !== '-') ? $k['seksi'] : ($isSelf ? 'DEPT. HEAD' : 'FACTORY 1');
                $kodeFormDoc = $doc['kode_form'] ?? PenilaianController::determineKodeForm($k);
                $isGol4UpDoc = PenilaianController::isForm07006($kodeFormDoc);
                $isQuotaExempt = $isGol4UpDoc || PenilaianController::isQuotaExemptEmployee($k, $doc);
                $hasForemanEmp = !empty($k['nrp_foreman']) && !$isSelf && !$isGol4UpDoc;

                // 1. Foreman evaluation (strictly when employee has Foreman & KJ 1-5 form)
                $foremanScores = ($hasForemanEmp && $docId && isset($nilaiGrouped[$docId]['foreman'])) 
                    ? $nilaiGrouped[$docId]['foreman'] 
                    : [];
                $calcForeman = !empty($foremanScores) 
                    ? $this->calcService->calculate($kodeFormDoc, $foremanScores) 
                    : ['grand_total' => 0.00];
                $fScore = $hasForemanEmp
                    ? ($calcForeman['grand_total'] > 0 ? $calcForeman['grand_total'] : ($doc['total_nilai'] ?? 0))
                    : 0;
                $isEvaluatedByForeman = $hasForemanEmp && (($status !== 'belum_ada' && $status !== 'draft' && $status !== 'menunggu_foreman') || ($fScore > 0));
                $foremanGrade = '-';
                if ($isEvaluatedByForeman && $fScore > 0) {
                    $foremanGrade = $this->calcService->scoreToGrade($fScore);
                    $deptForemanTotalDinilai++;
                    if (isset($deptForemanGradeCounts[$foremanGrade])) {
                        $deptForemanGradeCounts[$foremanGrade]++;
                    }
                }

                // 2. Section Head evaluation
                // Khusus Form 07-006-SH (Evaluasi Mandiri Section Head), skor SH berada di slot 'foreman' (Kolom 1).
                $secScores = ($docId && isset($nilaiGrouped[$docId]['section_head'])) 
                    ? $nilaiGrouped[$docId]['section_head'] 
                    : (($docId && !$isSelf && $kodeFormDoc === '07-006-SH' && isset($nilaiGrouped[$docId]['foreman']))
                        ? $nilaiGrouped[$docId]['foreman']
                        : []);
                $calcSec = !empty($secScores) 
                    ? $this->calcService->calculate($kodeFormDoc, $secScores) 
                    : ['grand_total' => 0.00];
                
                $sScore = $calcSec['grand_total'] > 0
                    ? $calcSec['grand_total']
                    : (in_array($status, ['menunggu_dept_head', 'menunggu_div_head', 'selesai']) ? ($doc['total_nilai'] ?? 0) : 0);
                $isEvaluatedBySection = !$isSelf && (in_array($status, ['menunggu_dept_head', 'menunggu_div_head', 'selesai']) || ($calcSec['grand_total'] > 0)) && !in_array($status, ['menunggu_section_head', 'menunggu_foreman']);

                $secGrade = '-';
                if ($isEvaluatedBySection && $sScore > 0) {
                    $secGrade = $this->calcService->scoreToGrade($sScore);
                    $deptSectionTotalDinilai++;
                    if (!$isQuotaExempt && isset($deptSectionGradeCounts[$secGrade])) {
                        $deptSectionGradeCounts[$secGrade]++;
                    }
                }

                // Final grade
                $finalGrade = $doc['docking_final'] ?? $doc['grade_original'] ?? ($secGrade !== '-' ? $secGrade : ($foremanGrade !== '-' ? $foremanGrade : '-'));
                if (!$isQuotaExempt && $finalGrade !== '-' && isset($deptFinalGradeCounts[$finalGrade])) {
                    $deptFinalGradeCounts[$finalGrade]++;
                }

                // Workflow stage categorization
                if ($hasAnyForeman) {
                    // Pipeline Department WITH Foreman:
                    // Tahap 1: Penilaian Foreman
                    // Tahap 2: Tinjauan Section Head
                    // Tahap 3: Approval Dept Head (To-Do Anda)
                    // Tahap 4: Selesai & Terarsip
                    if ($status === 'selesai' || $status === 'menunggu_div_head') {
                        $stageKey = 'tahap4';
                        $deptStageCounts['tahap4_selesai']++;
                        $deptStageCounts['selesai']++;
                        $stageLabel = $status === 'menunggu_div_head' ? 'Division Head' : 'Selesai (Final)';
                        $badgeClass = 'bg-emerald-100 text-emerald-800 border-emerald-300';
                        $actionType = 'sudah_dikirim';
                        $actionLabel = 'Selesai & Arsip';
                        $deptDoneCount++;
                        if ($hasForemanEmp) {
                            $deptForemanDoneCount++;
                        }
                        if (!$isSelf) {
                            $deptSectionDoneCount++;
                        }
                    } elseif ($status === 'menunggu_dept_head') {
                        $stageKey = 'tahap3';
                        $deptStageCounts['tahap3_dept']++;
                        $stageLabel = 'Department Head';
                        $badgeClass = 'bg-amber-100 text-amber-900 border-amber-300 font-bold';
                        $actionType = 'todo';
                        $actionLabel = 'Perlu Review / TTD';
                        $deptNeedActionCount++;
                        if ($hasForemanEmp) {
                            $deptForemanDoneCount++;
                        }
                        if (!$isSelf) {
                            $deptSectionDoneCount++;
                        }
                    } elseif ($status === 'menunggu_section_head' || $status === 'revisi' || (empty($k['nrp_foreman']) && $status !== 'menunggu_foreman' && $status !== 'draft')) {
                        $stageKey = 'tahap2';
                        $deptStageCounts['tahap2_section']++;
                        $stageLabel = 'Section Head';
                        $badgeClass = 'bg-blue-100 text-blue-800 border-blue-200';
                        $actionType = 'menunggu_section';
                        $actionLabel = ($status === 'revisi' ? 'Revisi Section Head' : 'Menunggu Section Head');
                        if ($hasForemanEmp) {
                            $deptForemanDoneCount++;
                        }
                    } else {
                        $stageKey = 'tahap1';
                        $deptStageCounts['tahap1_foreman']++;
                        $stageLabel = 'Foreman';
                        $badgeClass = 'bg-slate-100 text-slate-700 border-slate-200';
                        $actionType = 'menunggu_foreman';
                        $actionLabel = ($status === 'draft' ? 'Draft Foreman' : 'Menunggu Foreman');
                        if ($status === 'draft') {
                            $deptStageCounts['draft_fm']++;
                        } else {
                            $deptStageCounts['belum_dinilai_fm']++;
                        }
                    }
                } else {
                    // Pipeline Department WITHOUT Foreman:
                    if ($user->isSecretary()) {
                        if ($status === 'selesai') {
                            $stageKey = 'tahap4';
                            $deptStageCounts['tahap4_selesai']++;
                            $stageLabel = 'Selesai (Final)';
                            $badgeClass = 'bg-emerald-100 text-emerald-800 border-emerald-300';
                            $actionType = 'selesai';
                            $actionLabel = 'Selesai Final';
                            $deptDoneCount++;
                        } elseif ($status === 'menunggu_div_head') {
                            $stageKey = 'tahap3';
                            $deptStageCounts['tahap3_div']++;
                            $stageLabel = 'Division Head';
                            $badgeClass = 'bg-indigo-100 text-indigo-800 border-indigo-300';
                            $actionType = 'sudah_dikirim';
                            $actionLabel = 'Review Div Head';
                            $deptDoneCount++;
                        } else {
                            // Belum dinilai Secretary (menunggu_dept_head, draft, revisi, menunggu_section_head, dll)
                            $stageKey = 'tahap2';
                            $deptStageCounts['tahap2_dept']++;
                            $stageLabel = 'Evaluasi Mandiri';
                            $badgeClass = 'bg-amber-100 text-amber-900 border-amber-300 font-bold';
                            $actionType = 'todo';
                            $actionLabel = 'Perlu Evaluasi Mandiri';
                            $deptNeedActionCount++;
                        }
                    } else {
                        // Tahap 1: Penilaian Section Head
                        // Tahap 2: Tinjauan Dept Head
                        // Tahap 3: Approval Div Head
                        // Tahap 4: Selesai & Terarsip
                        if ($status === 'selesai') {
                            $stageKey = 'tahap4';
                            $deptStageCounts['tahap4_selesai']++;
                            $stageLabel = 'Selesai (Final)';
                            $badgeClass = 'bg-emerald-100 text-emerald-800 border-emerald-300';
                            $actionType = 'sudah_dikirim';
                            $actionLabel = 'Selesai & Arsip';
                            $deptDoneCount++;
                        } elseif ($status === 'menunggu_div_head') {
                            $stageKey = 'tahap3';
                            $deptStageCounts['tahap3_div']++;
                            $stageLabel = 'Division Head';
                            $badgeClass = 'bg-indigo-100 text-indigo-800 border-indigo-300';
                            $actionType = 'sudah_dikirim';
                            $actionLabel = 'Review Div Head';
                            $deptDoneCount++;
                        } elseif ($status === 'menunggu_dept_head') {
                            $stageKey = 'tahap2';
                            $deptStageCounts['tahap2_dept']++;
                            $stageLabel = 'Department Head (To-Do)';
                            $badgeClass = 'bg-amber-100 text-amber-900 border-amber-300 font-bold';
                            $actionType = 'todo';
                            $actionLabel = 'Perlu Review / TTD';
                            $deptNeedActionCount++;
                        } elseif ($status === 'revisi') {
                            $stageKey = 'tahap1';
                            $deptStageCounts['tahap1_section']++;
                            $deptStageCounts['revisi_sh']++;
                            $stageLabel = 'Revisi ke Section Head';
                            $badgeClass = 'bg-rose-100 text-rose-800 border-rose-300';
                            $actionType = 'revisi';
                            $actionLabel = 'Perlu Revisi';
                        } elseif ($status === 'draft') {
                            $stageKey = 'tahap1';
                            $deptStageCounts['tahap1_section']++;
                            $deptStageCounts['draft_sh']++;
                            $stageLabel = 'Draft Section Head';
                            $badgeClass = 'bg-slate-100 text-slate-700 border-slate-300';
                            $actionType = 'draft';
                            $actionLabel = 'Draft Section Head';
                        } else {
                            $stageKey = 'tahap1';
                            $deptStageCounts['tahap1_section']++;
                            $deptStageCounts['belum_dinilai_sh']++;
                            $stageLabel = 'Section Head';
                            $badgeClass = 'bg-slate-100 text-slate-600 border-slate-200';
                            $actionType = 'belum_dinilai';
                            $actionLabel = 'Menunggu Section Head';
                        }
                    }
                }

                $isEvaluated = $isSelf
                    ? in_array($status, ['menunggu_div_head', 'selesai'])
                    : ($isEvaluatedBySection || $isEvaluatedByForeman || in_array($status, ['menunggu_dept_head', 'menunggu_div_head', 'selesai']));

                $rowGrade = ($secGrade !== '-') ? $secGrade : (($foremanGrade !== '-') ? $foremanGrade : ($doc['docking_final'] ?? $doc['grade_original'] ?? '-'));
                $rowScore = ($sScore > 0) ? number_format((float)$sScore, 2) : (($fScore > 0) ? number_format((float)$fScore, 2) : '-');

                $deptSubordinateRows[] = [
                    'nrp' => $nrp,
                    'nama' => $k['nama_lengkap'],
                    'nama_lengkap' => $k['nama_lengkap'],
                    'jabatan' => $k['jabatan'] ?? '-',
                    'seksi' => $seksiName,
                    'golongan' => $k['golongan'] ?? $k['sub_gol'] ?? '-',
                    'sub_gol' => $k['sub_gol'] ?? $k['golongan'] ?? '-',
                    'tgl_masuk' => $k['tgl_masuk'] ?? '-',
                    'doc_id' => $docId,
                    'status' => $status,
                    'stage_key' => $stageKey,
                    'stage_label' => $stageLabel,
                    'badge_class' => $badgeClass,
                    'action_type' => $actionType,
                    'action_label' => $actionLabel,
                    'grade' => $rowGrade,
                    'score' => $rowScore,
                    'is_evaluated' => $isEvaluated,
                    'is_self' => $isSelf,
                ];

                if (!$isSelf) {
                    if (!isset($sectionGroups[$secNrp])) {
                        $sectionGroups[$secNrp] = [
                            'nrp' => $secNrp,
                            'seksi' => $seksiName,
                            'total' => 0,
                            'dinilai' => 0,
                        ];
                    }
                    $sectionGroups[$secNrp]['total']++;
                    if ($isEvaluatedBySection) {
                        $sectionGroups[$secNrp]['dinilai']++;
                    }
                }
            }

            $deptTotalDinilai = $deptSectionTotalDinilai;
            $deptWaitingSectionCount = $deptStageCounts['tahap2_section'] ?? ($deptStageCounts['tahap1_section'] ?? 0);

            // Target distribution calculation for Dept Head synced strictly with Admin / Superadmin Target Kuota (excludes 07-006)
            $quotaDeptSubs = array_values(array_filter(
                $deptSubordinates,
                fn ($k) => !PenilaianController::isQuotaExemptEmployee($k, $docsByNrp[$k['nrp']] ?? null)
            ));
            $resolvedDeptCode = PenilaianController::resolveDepartmentCode($user, $deptSubordinates);
            $resolvedDivCode = PenilaianController::resolveDivisionCode($user, $deptSubordinates);
            $deptQuotaData = PenilaianController::getDepartmentTargetQuota($user, $deptSubordinates);
            $deptTargetCounts = [
                'S' => $deptQuotaData['S'] ?? 0,
                'A' => $deptQuotaData['A'] ?? 0,
                'B' => $deptQuotaData['B'] ?? 0,
                'C' => $deptQuotaData['C'] ?? 0,
                'K' => $deptQuotaData['K'] ?? 0,
            ];
            $totalTargetDept = (int)($deptQuotaData['total'] ?? array_sum($deptTargetCounts));

            // Build Per-KJ Target & Actual Matrices for Dept Head Dashboard (excludes 07-006 forms; KJ 5+ maps into KJ 5)
            $deptSubCountByKj = [];
            foreach ($quotaDeptSubs as $s) {
                $kj = ExcelKaryawanReader::normalizeQuotaKj($s['kj'] ?? 'KJ 2');
                $deptSubCountByKj[$kj] = ($deptSubCountByKj[$kj] ?? 0) + 1;
            }
            $deptKjList = array_keys($deptSubCountByKj);
            sort($deptKjList, SORT_NATURAL);

            foreach ($deptKjList as $kj) {
                $deptActualKjMatrix[$kj] = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0, 'total' => 0];
            }
            foreach ($quotaDeptSubs as $s) {
                $nrpSub = $s['nrp'];
                $doc = $docsByNrp[$nrpSub] ?? null;
                $docId = $doc['id'] ?? null;
                $status = $doc['status'] ?? 'belum_ada';
                $kodeFormDoc = $doc['kode_form'] ?? PenilaianController::determineKodeForm($s);
                $kj = ExcelKaryawanReader::normalizeQuotaKj($s['kj'] ?? 'KJ 2');

                $dhScores = ($docId && isset($nilaiGrouped[$docId]['dept_head']))
                    ? $nilaiGrouped[$docId]['dept_head']
                    : (($docId && $isDeptWithoutForeman && isset($nilaiGrouped[$docId]['section_head']))
                        ? $nilaiGrouped[$docId]['section_head']
                        : []);
                $calcDh = !empty($dhScores)
                    ? $this->calcService->calculate($kodeFormDoc, $dhScores)
                    : ['grand_total' => 0.00];
                $dhScore = $calcDh['grand_total'] > 0
                    ? $calcDh['grand_total']
                    : (in_array($status, ['menunggu_div_head', 'selesai']) ? ($doc['total_nilai'] ?? 0) : 0);
                $isEvaluatedByDeptHead = in_array($status, ['menunggu_div_head', 'selesai'])
                    || (!empty($dhScores) && ($dhScore > 0) && !in_array($status, ['belum_ada', 'draft', 'menunggu_foreman', 'menunggu_section_head', 'menunggu_dept_head']));

                if ($isEvaluatedByDeptHead) {
                    $dhGrade = $doc['docking_final'] ?? ($dhScore > 0 ? $this->calcService->scoreToGrade($dhScore) : ($doc['grade_original'] ?? null));
                    if (in_array($dhGrade, ['S', 'A', 'B', 'C', 'K'])) {
                        if (!isset($deptActualKjMatrix[$kj])) {
                            $deptActualKjMatrix[$kj] = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0, 'total' => 0];
                        }
                        $deptActualKjMatrix[$kj][$dhGrade]++;
                        $deptActualKjMatrix[$kj]['total']++;
                        $deptActualKjSummary[$dhGrade]++;
                        $deptActualKjSummary['total']++;
                    }
                }
            }

            $deptTargetKjMatrix = PenilaianController::buildTargetKjMatrix(
                $deptKjList,
                $deptSubCountByKj,
                $deptTargetCounts,
                $resolvedDeptCode,
                $resolvedDivCode
            );
            foreach ($deptTargetKjMatrix as $tRow) {
                foreach (['S', 'A', 'B', 'C', 'K', 'total'] as $gKey) {
                    $deptTargetKjSummary[$gKey] += (int)($tRow[$gKey] ?? 0);
                }
            }

            // Build Rekap Penilaian per Section (FACTORY 1, FACTORY 2, FACTORY 3)
            $sh1Count = $sectionGroups['112']['total'] ?? 0;
            $sh1Dinilai = $sectionGroups['112']['dinilai'] ?? 0;
            $sh1Pct = $sh1Count > 0 ? round(($sh1Dinilai / $sh1Count) * 100) : 0;

            $sh2Count = $sectionGroups['280']['total'] ?? 0;
            $sh2Dinilai = $sectionGroups['280']['dinilai'] ?? 0;
            $sh2Pct = $sh2Count > 0 ? round(($sh2Dinilai / $sh2Count) * 100) : 0;

            $sh3Count = $sectionGroups['99999']['total'] ?? 0;
            $sh3Dinilai = $sectionGroups['99999']['dinilai'] ?? 0;
            $sh3Pct = $sh3Count > 0 ? round(($sh3Dinilai / $sh3Count) * 100) : 0;

            $rekapSection = [
                [
                    'nrp' => '112',
                    'nama_section' => 'KRISTIYANTO',
                    'seksi' => 'FACTORY 1',
                    'total_karyawan' => $sh1Count,
                    'sudah_dinilai' => $sh1Dinilai,
                    'persentase' => $sh1Pct . '%',
                    'pct_val' => $sh1Pct,
                ],
                [
                    'nrp' => '280',
                    'nama_section' => 'MURYADI',
                    'seksi' => 'FACTORY 2',
                    'total_karyawan' => $sh2Count,
                    'sudah_dinilai' => $sh2Dinilai,
                    'persentase' => $sh2Pct . '%',
                    'pct_val' => $sh2Pct,
                ],
                [
                    'nrp' => '99999',
                    'nama_section' => 'AL QADRI',
                    'seksi' => 'FACTORY 3',
                    'total_karyawan' => $sh3Count,
                    'sudah_dinilai' => $sh3Dinilai,
                    'persentase' => $sh3Pct . '%',
                    'pct_val' => $sh3Pct,
                ],
            ];
        }

        // =========================================================================
        // D. DIVISION HEAD DASHBOARD LOGIC (Target Quota & Comparison S/A/B/C/K)
        // =========================================================================
        $targetPctSetting = \Illuminate\Support\Facades\DB::table('pengaturan')->where('key', 'target_kuota_pct')->value('value');
        $targetPercentages = $targetPctSetting ? json_decode($targetPctSetting, true) : ['S' => 10, 'A' => 30, 'B' => 30, 'C' => 20, 'K' => 10];

        $divSubordinates = [];
        $divTargetCounts = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];
        $divTargetKjMatrix = [];
        $divTargetKjSummary = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0, 'total' => 0];
        $divActualKjMatrix = [];
        $divActualKjSummary = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0, 'total' => 0];
        $divSectionGradeCounts = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];
        $divDeptGradeCounts = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];
        $divFinalGradeCounts = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];
        $divSectionTotalDinilai = 0;
        $divDeptTotalDinilai = 0;
        $divFinalTotalDinilai = 0;
        $divNeedActionCount = 0;
        $incomingDocsDivHead = [];
        $rekapDept = [];
        $divStageCounts = [
            'tahap1_section' => 0,
            'belum_dinilai_sh' => 0,
            'draft_sh' => 0,
            'revisi_sh' => 0,
            'tahap2_dept' => 0,
            'tahap3_div' => 0,
            'tahap4_selesai' => 0,
            'selesai' => 0,
        ];
        $divSubordinateRows = [];
        $divActualGradeCounts = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];
        $divActualGradeTotal = 0;

        $selfKaryawan = null;
        $selfDoc = null;
        $selfScores = [];
        $selfCalc = ['grand_total' => 0.00, 'groups' => []];
        $selfScoreFormatted = '-';
        $selfGrade = '-';
        $selfStatus = 'belum_ada';

        if ($user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head'])) {
            $divSubordinates = array_values(array_filter($allKaryawan, fn ($k) => $user->matchesDivisionHeadScope($k)));
            // Pin self-evaluation (07-006-DIV) first, followed by 07-006 (DH -> SH), then operational staff
            usort($divSubordinates, function ($a, $b) use ($user, $docsByNrp) {
                $rank = function (array $k) use ($user, $docsByNrp): int {
                    if (!empty($user->nrp) && strcasecmp((string)($k['nrp'] ?? ''), (string)$user->nrp) === 0) {
                        return 0;
                    }
                    $doc = $docsByNrp[$k['nrp'] ?? ''] ?? null;
                    $kf = strtoupper(trim((string)($doc['kode_form'] ?? PenilaianController::determineKodeForm($k))));
                    if ($kf === '07-006-DIV') {
                        return 1;
                    }
                    if ($kf === '07-006-DH') {
                        return 2;
                    }
                    if (str_starts_with($kf, '07-006')) {
                        return 3;
                    }
                    return 4;
                };
                $ra = $rank($a);
                $rb = $rank($b);
                if ($ra !== $rb) {
                    return $ra <=> $rb;
                }
                return strcmp((string)($a['nrp'] ?? ''), (string)($b['nrp'] ?? ''));
            });

            $divTotalKaryawan = count($divSubordinates);

            $quotaDivSubs = array_values(array_filter(
                $divSubordinates,
                fn ($k) => !PenilaianController::isQuotaExemptEmployee($k, $docsByNrp[$k['nrp'] ?? ''] ?? null)
            ));
            $resolvedDivCodeForDash = PenilaianController::resolveDivisionCode($user, $divSubordinates);
            $divTargetQuota = \App\Http\Controllers\PenilaianController::getDivisionTargetQuota($user, $quotaDivSubs);
            $divTargetCounts = [
                'S' => $divTargetQuota['S'] ?? 0,
                'A' => $divTargetQuota['A'] ?? 0,
                'B' => $divTargetQuota['B'] ?? 0,
                'C' => $divTargetQuota['C'] ?? 0,
                'K' => $divTargetQuota['K'] ?? 0,
            ];
            $totalTargetDiv = $divTargetQuota['total'] ?? count($quotaDivSubs);

            // If target sum is 0, check if Admin set department quotas under this division
            $sumDivTargets = array_sum($divTargetCounts);
            if ($sumDivTargets === 0) {
                $uniqueDepts = array_values(array_unique(array_filter(array_column($divSubordinates, 'departemen'))));
                $dSumS = 0; $dSumA = 0; $dSumB = 0; $dSumC = 0; $dSumK = 0;
                foreach ($uniqueDepts as $ud) {
                    $cDeptVal = DB::table('pengaturan')->where('key', 'target_kuota_dept_' . $ud)->value('value');
                    if ($cDeptVal) {
                        $cd = json_decode($cDeptVal, true);
                        if (isset($cd['S'])) {
                            $dSumS += (int)($cd['S'] ?? 0);
                            $dSumA += (int)($cd['A'] ?? 0);
                            $dSumB += (int)($cd['B'] ?? 0);
                            $dSumC += (int)($cd['C'] ?? 0);
                            $dSumK += (int)($cd['K'] ?? 0);
                        }
                    }
                }
                if (($dSumS + $dSumA + $dSumB + $dSumC + $dSumK) > 0) {
                    $divTargetCounts = [
                        'S' => $dSumS,
                        'A' => $dSumA,
                        'B' => $dSumB,
                        'C' => $dSumC,
                        'K' => $dSumK,
                    ];
                    $totalTargetDiv = array_sum($divTargetCounts);
                    $sumDivTargets = $totalTargetDiv;
                }
            }

            // Fallback: If still 0 and division has employees, calculate proportional target based on company percentage
            if ($sumDivTargets === 0 && $divTotalKaryawan > 0) {
                $pctSetting = DB::table('pengaturan')->where('key', 'target_kuota_pct')->first();
                $pcts = $pctSetting ? json_decode($pctSetting->value, true) : ['S' => 10, 'A' => 30, 'B' => 30, 'C' => 20, 'K' => 10];
                $targetS = max(0, (int)round($divTotalKaryawan * (($pcts['S'] ?? 10) / 100)));
                if ($targetS === 0 && $divTotalKaryawan >= 5) {
                    $targetS = 1;
                }
                $targetA = max(0, (int)round($divTotalKaryawan * (($pcts['A'] ?? 30) / 100)));
                $targetB = max(0, (int)round($divTotalKaryawan * (($pcts['B'] ?? 30) / 100)));
                $targetC = max(0, (int)round($divTotalKaryawan * (($pcts['C'] ?? 20) / 100)));
                $targetK = max(0, $divTotalKaryawan - ($targetS + $targetA + $targetB + $targetC));
                if ($targetK < 0) {
                    $targetK = 0;
                    $targetB = max(0, $divTotalKaryawan - ($targetS + $targetA + $targetC));
                }
                $divTargetCounts = ['S' => $targetS, 'A' => $targetA, 'B' => $targetB, 'C' => $targetC, 'K' => $targetK];
                $totalTargetDiv = $divTotalKaryawan;
            }

            $divSubCountByKj = [];
            foreach ($quotaDivSubs as $qs) {
                $kj = ExcelKaryawanReader::normalizeQuotaKj($qs['kj'] ?? 'KJ 2');
                $divSubCountByKj[$kj] = ($divSubCountByKj[$kj] ?? 0) + 1;
            }
            $divKjList = array_keys($divSubCountByKj);
            sort($divKjList, SORT_NATURAL);
            foreach ($divKjList as $kj) {
                $divActualKjMatrix[$kj] = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0, 'total' => 0];
            }
            $divTargetKjMatrix = PenilaianController::buildTargetKjMatrix(
                $divKjList,
                $divSubCountByKj,
                $divTargetCounts,
                '',
                $resolvedDivCodeForDash
            );
            foreach ($divTargetKjMatrix as $tRow) {
                foreach (['S', 'A', 'B', 'C', 'K', 'total'] as $gKey) {
                    $divTargetKjSummary[$gKey] += (int)($tRow[$gKey] ?? 0);
                }
            }

            $deptGroups = [];
            foreach ($divSubordinates as $k) {
                $nrp = $k['nrp'];
                $deptName = $k['departemen'] ?? 'ENGINEERING';
                $doc = $docsByNrp[$nrp] ?? null;
                $docId = $doc['id'] ?? null;
                $status = $doc['status'] ?? 'belum_ada';
                $isSelf = (strcasecmp($nrp, $user->nrp) === 0);

                if (!isset($deptGroups[$deptName])) {
                    $deptGroups[$deptName] = ['total' => 0, 'sec_dinilai' => 0, 'dept_dinilai' => 0, 'div_dinilai' => 0];
                }
                $deptGroups[$deptName]['total']++;

                // Special handling for Division Head Self-Evaluation (Menilai & Menghitung Diri Sendiri)
                if ($isSelf) {
                    $selfScores = ($docId && isset($nilaiGrouped[$docId]['dept_head'])) 
                        ? $nilaiGrouped[$docId]['dept_head'] 
                        : (($docId && isset($nilaiGrouped[$docId]['foreman'])) ? $nilaiGrouped[$docId]['foreman'] : []);
                    $calcSelf = !empty($selfScores) ? $this->calcService->calculate('07-006-DIV', $selfScores) : ['grand_total' => 0.00];
                    $selfScore = $calcSelf['grand_total'] > 0 ? $calcSelf['grand_total'] : (float)($doc['total_nilai'] ?? 0);
                    $isSelfEvaluated = ($status === 'selesai') || ($selfScore > 0);
                    $selfGrade = '-';
                    if ($isSelfEvaluated && $selfScore > 0) {
                        $selfGrade = $this->calcService->scoreToGrade($selfScore);
                    } elseif ($status === 'selesai' && !empty($doc['grade_original']) && $doc['grade_original'] !== '-') {
                        $selfGrade = $doc['grade_original'];
                    }

                    if ($status === 'selesai') {
                        $stageKey = 'tahap4';
                        $divStageCounts['tahap4_selesai']++;
                        $stageLabel = 'Selesai (Final)';
                        $badgeClass = 'bg-emerald-100 text-emerald-800 border-emerald-300';
                        $divFinalTotalDinilai++;
                        // 07-006-DIV self-evaluation is exempt from target quota grade counts
                        $deptGroups[$deptName]['div_dinilai']++;
                    } elseif ($status === 'draft') {
                        $stageKey = 'tahap3';
                        $divStageCounts['tahap3_div']++;
                        $stageLabel = 'Draft Mandiri Div. Head';
                        $badgeClass = 'bg-slate-100 text-slate-700 border-slate-300';
                    } else {
                        $stageKey = 'tahap3';
                        $divStageCounts['tahap3_div']++;
                        $stageLabel = 'Division Head (To-Do)';
                        $badgeClass = 'bg-amber-100 text-amber-900 border-amber-300 font-bold';
                    }

                    $rowScore = $selfScore > 0 ? number_format((float)$selfScore, 2) : '-';
                    $rowGrade = $selfGrade;

                    $rawTglMasuk = trim((string)($k['tgl_masuk'] ?? ''));
                    $fmtTglMasuk = ($rawTglMasuk && $rawTglMasuk !== '-' && strtotime($rawTglMasuk) !== false)
                        ? date('d-M-y', strtotime($rawTglMasuk))
                        : ($rawTglMasuk ?: '-');

                    $divSubordinateRows[] = [
                        'nrp' => $nrp,
                        'nama' => $k['nama_lengkap'],
                        'nama_lengkap' => $k['nama_lengkap'],
                        'jabatan' => $k['jabatan'] ?? '-',
                        'departemen' => $deptName,
                        'seksi' => (!empty($k['seksi']) && $k['seksi'] !== '-') ? $k['seksi'] : $deptName,
                        'golongan' => $k['golongan'] ?? $k['sub_gol'] ?? '-',
                        'sub_gol' => $k['sub_gol'] ?? $k['golongan'] ?? '-',
                        'tgl_masuk' => $fmtTglMasuk,
                        'doc_id' => $docId,
                        'status' => $status,
                        'stage_key' => $stageKey,
                        'stage_label' => $stageLabel,
                        'badge_class' => $badgeClass,
                        'score' => $rowScore,
                        'grade' => $rowGrade,
                        'grade_sec' => '-',
                        'grade_dept' => $rowGrade,
                        'is_evaluated' => $isSelfEvaluated,
                        'is_self' => true,
                    ];
                    continue;
                }

                // Subordinate Form Roles
                $isSubDeptHead = ($doc['kode_form'] ?? '') === '07-006-DH' || ($k['level_form'] ?? '') === 'DEPT_HEAD';
                $isSubSectionHead = ($doc['kode_form'] ?? '') === '07-006-SH' || ($k['level_form'] ?? '') === 'SECTION_HEAD';
                $isSubQuotaExempt = PenilaianController::isQuotaExemptEmployee($k, $doc);

                // 1. Section Head Evaluation
                $secScores = ($docId && isset($nilaiGrouped[$docId]['section_head'])) ? $nilaiGrouped[$docId]['section_head'] : [];
                if ($isSubSectionHead && empty($secScores) && isset($nilaiGrouped[$docId]['foreman'])) {
                    $secScores = $nilaiGrouped[$docId]['foreman'];
                }
                $calcSec = !empty($secScores) ? $this->calcService->calculate($doc['kode_form'] ?? '07-001', $secScores) : ['grand_total' => 0.00];
                $sScore = $calcSec['grand_total'] > 0 ? $calcSec['grand_total'] : ($doc['total_nilai'] ?? 0);
                $hasSecSign = isset($ttdGrouped[$docId]['section_head']) || ($isSubSectionHead && isset($ttdGrouped[$docId]['foreman']));
                $isEvaluatedBySec = ($calcSec['grand_total'] > 0) || $hasSecSign || ($status === 'selesai' && (float)($doc['total_nilai'] ?? 0) > 0);
                $secGrade = '-';
                if ($isEvaluatedBySec && $sScore > 0) {
                    $secGrade = $this->calcService->scoreToGrade($sScore);
                    if (!$isSubQuotaExempt && isset($divSectionGradeCounts[$secGrade])) {
                        $divSectionGradeCounts[$secGrade]++;
                    }
                    $divSectionTotalDinilai++;
                    $deptGroups[$deptName]['sec_dinilai']++;
                }

                // 2. Dept Head Evaluation
                $deptScores = ($docId && isset($nilaiGrouped[$docId]['dept_head'])) ? $nilaiGrouped[$docId]['dept_head'] : [];
                if ($isSubDeptHead && empty($deptScores) && isset($nilaiGrouped[$docId]['foreman'])) {
                    $deptScores = $nilaiGrouped[$docId]['foreman'];
                }
                $calcDept = !empty($deptScores) ? $this->calcService->calculate($doc['kode_form'] ?? '07-001', $deptScores) : ['grand_total' => 0.00];
                $dScore = $calcDept['grand_total'] > 0 ? $calcDept['grand_total'] : ($doc['total_nilai'] ?? 0);
                $hasDeptSign = isset($ttdGrouped[$docId]['dept_head']) || ($isSubDeptHead && isset($ttdGrouped[$docId]['foreman']));
                $isEvaluatedByDept = ($calcDept['grand_total'] > 0) || $hasDeptSign || ($status === 'selesai' && (float)($doc['total_nilai'] ?? 0) > 0);
                $deptGrade = '-';
                if ($isEvaluatedByDept && $dScore > 0) {
                    $deptGrade = $doc['docking_final'] ?? $this->calcService->scoreToGrade($dScore);
                    if (!$isSubQuotaExempt && $deptGrade !== '-' && isset($divDeptGradeCounts[$deptGrade])) {
                        $divDeptGradeCounts[$deptGrade]++;
                    }
                    $divDeptTotalDinilai++;
                    $deptGroups[$deptName]['dept_dinilai']++;
                }

                // 3. Division Head Evaluation
                $isFinalizedByDiv = ($status === 'selesai');
                $finalGrade = '-';
                if ($isFinalizedByDiv && (float)($doc['total_nilai'] ?? 0) > 0) {
                    $finalGrade = $doc['docking_final'] ?? $this->calcService->scoreToGrade((float)$doc['total_nilai']);
                    if (!$isSubQuotaExempt && $finalGrade !== '-' && isset($divFinalGradeCounts[$finalGrade])) {
                        $divFinalGradeCounts[$finalGrade]++;
                        $subKj = ExcelKaryawanReader::normalizeQuotaKj($k['kj'] ?? 'KJ 2');
                        if (!isset($divActualKjMatrix[$subKj])) {
                            $divActualKjMatrix[$subKj] = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0, 'total' => 0];
                        }
                        $divActualKjMatrix[$subKj][$finalGrade]++;
                        $divActualKjMatrix[$subKj]['total']++;
                        $divActualKjSummary[$finalGrade]++;
                        $divActualKjSummary['total']++;
                    }
                    $divFinalTotalDinilai++;
                    $deptGroups[$deptName]['div_dinilai']++;
                }

                // Workflow Stage Categorization for Subordinates
                $isDraft = ($status === 'draft') || (!empty($secScores) && in_array($status, ['menunggu_section_head', 'belum_ada']));

                $stageKey = 'tahap1';
                if ($status === 'selesai') {
                    $stageKey = 'tahap4';
                    $divStageCounts['tahap4_selesai']++;
                    $stageLabel = 'Selesai (Final)';
                    $badgeClass = 'bg-emerald-100 text-emerald-800 border-emerald-300';
                } elseif ($status === 'menunggu_div_head') {
                    $stageKey = 'tahap3';
                    $divStageCounts['tahap3_div']++;
                    $divNeedActionCount++;
                    $stageLabel = 'Division Head (To-Do)';
                    $badgeClass = 'bg-amber-100 text-amber-900 border-amber-300 font-bold';
                } elseif ($status === 'menunggu_dept_head') {
                    $stageKey = 'tahap2';
                    $divStageCounts['tahap2_dept']++;
                    $stageLabel = 'Department Head';
                    $badgeClass = 'bg-blue-100 text-blue-800 border-blue-300';
                } elseif ($status === 'revisi') {
                    $stageKey = 'tahap1';
                    $divStageCounts['tahap1_section']++;
                    $divStageCounts['revisi_sh']++;
                    $stageLabel = 'Revisi ke Section Head';
                    $badgeClass = 'bg-rose-100 text-rose-800 border-rose-300';
                } elseif ($isDraft) {
                    $stageKey = 'tahap1';
                    $divStageCounts['tahap1_section']++;
                    $divStageCounts['draft_sh']++;
                    $stageLabel = 'Draft Section Head';
                    $badgeClass = 'bg-slate-100 text-slate-700 border-slate-300';
                } else {
                    $stageKey = 'tahap1';
                    $divStageCounts['tahap1_section']++;
                    $divStageCounts['belum_dinilai_sh']++;
                    $stageLabel = 'Section Head';
                    $badgeClass = 'bg-slate-100 text-slate-600 border-slate-200';
                }

                $rowScore = '-';
                if ($dScore > 0 && $isEvaluatedByDept) {
                    $rowScore = number_format((float)$dScore, 2);
                } elseif ($sScore > 0 && $isEvaluatedBySec) {
                    $rowScore = number_format((float)$sScore, 2);
                }

                $rowGrade = '-';
                if ($isFinalizedByDiv && $finalGrade !== '-') {
                    $rowGrade = $finalGrade;
                } elseif ($isEvaluatedByDept && $deptGrade !== '-') {
                    $rowGrade = $deptGrade;
                } elseif ($isEvaluatedBySec && $secGrade !== '-') {
                    $rowGrade = $secGrade;
                }

                $rawTglMasuk = trim((string)($k['tgl_masuk'] ?? ''));
                $fmtTglMasuk = ($rawTglMasuk && $rawTglMasuk !== '-' && strtotime($rawTglMasuk) !== false)
                    ? date('d-M-y', strtotime($rawTglMasuk))
                    : ($rawTglMasuk ?: '-');

                $divSubordinateRows[] = [
                    'nrp' => $nrp,
                    'nama' => $k['nama_lengkap'],
                    'nama_lengkap' => $k['nama_lengkap'],
                    'jabatan' => $k['jabatan'] ?? '-',
                    'departemen' => $deptName,
                    'seksi' => (!empty($k['seksi']) && $k['seksi'] !== '-') ? $k['seksi'] : $deptName,
                    'golongan' => $k['golongan'] ?? $k['sub_gol'] ?? '-',
                    'sub_gol' => $k['sub_gol'] ?? $k['golongan'] ?? '-',
                    'tgl_masuk' => $fmtTglMasuk,
                    'doc_id' => $docId,
                    'status' => $status,
                    'stage_key' => $stageKey,
                    'stage_label' => $stageLabel,
                    'badge_class' => $badgeClass,
                    'score' => $rowScore,
                    'grade' => $rowGrade,
                    'grade_sec' => $secGrade,
                    'grade_dept' => $deptGrade,
                    'is_evaluated' => $isFinalizedByDiv || $isEvaluatedByDept,
                    'is_self' => false,
                ];
            }

            foreach ($deptGroups as $dName => $dStats) {
                $pct = $dStats['total'] > 0 ? round(($dStats['div_dinilai'] / $dStats['total']) * 100) : 0;
                $rekapDept[] = [
                    'departemen' => $dName,
                    'total_karyawan' => $dStats['total'],
                    'dinilai_sec' => $dStats['sec_dinilai'],
                    'dinilai_dept' => $dStats['dept_dinilai'],
                    'selesai_div' => $dStats['div_dinilai'],
                    'persentase' => $pct . '%',
                    'pct_val' => $pct,
                ];
            }

            // Calculate actual grade distribution across all division subordinates (informational)
            $divActualGradeCounts = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];
            foreach ($divSubordinateRows as $r) {
                $g = strtoupper(trim((string)($r['grade'] ?? '')));
                if (isset($divActualGradeCounts[$g])) {
                    $divActualGradeCounts[$g]++;
                }
            }
            $divActualGradeTotal = array_sum($divActualGradeCounts);

            // Self evaluation metrics specifically for Division Head Penilaian Mandiri Card
            $selfKaryawan = DB::table('karyawan')->where('nrp', $user->nrp)->first();
            if ($selfKaryawan) {
                $selfDoc = DB::table('dokumen')->where('nrp', $user->nrp)->orderByDesc('id')->first();
                if ($selfDoc) {
                    $selfStatus = $selfDoc->status;
                    $scoresRaw = DB::table('nilai')
                        ->where('dokumen_id', $selfDoc->id)
                        ->whereIn('level', ['dept_head', 'foreman'])
                        ->pluck('skor', 'aspek_kode')
                        ->toArray();
                    if (!empty($scoresRaw)) {
                        $selfScores = $scoresRaw;
                        $selfCalc = $this->calcService->calculate($selfDoc->kode_form ?? '07-006-DIV', $selfScores);
                        if ($selfCalc['grand_total'] > 0) {
                            $selfScoreFormatted = number_format($selfCalc['grand_total'], 2);
                            $selfGrade = $this->calcService->scoreToGrade($selfCalc['grand_total']);
                        }
                    } elseif (!empty($selfDoc->total_nilai) && (float)$selfDoc->total_nilai > 0) {
                        $selfScoreFormatted = number_format((float)$selfDoc->total_nilai, 2);
                        $selfGrade = $selfDoc->grade_original ?? $this->calcService->scoreToGrade((float)$selfDoc->total_nilai);
                    }
                }
            }
        }

        // =========================================================================
        // E. SECTION HEAD, DEPT HEAD & DIV HEAD INCOMING DOCUMENTS
        // =========================================================================
        $incomingDocsSectionHead = [];
        $incomingDocsDeptHead = [];

        foreach ($allDocs as $doc) {
            $nrp = $doc['nrp'] ?? '';
            $karyawan = $karyawanMap[$nrp] ?? null;
            if (!$karyawan) continue;

            $docDept = $karyawan['departemen'] ?? '';
            $status = $doc['status'] ?? '';
            $doc['karyawan'] = $karyawan;

            // Incoming to Section Head (status == 'menunggu_section_head')
            if ($status === 'menunggu_section_head') {
                if ($user->isSectionHead()) {
                    if ($karyawan && $user->matchesSectionHeadScope($karyawan)) {
                        $doc['score_foreman'] = $doc['total_nilai'] ?? '-';
                        $incomingDocsSectionHead[] = $doc;
                    }
                } elseif ($user->canAccessDepartment($docDept)) {
                    $doc['score_foreman'] = $doc['total_nilai'] ?? '-';
                    $incomingDocsSectionHead[] = $doc;
                }
            }

            // Incoming to Dept Head (status == 'menunggu_dept_head')
            if ($status === 'menunggu_dept_head') {
                if ($user->isDepartmentHead()) {
                    if ($karyawan && $user->matchesDeptHeadScope($karyawan)) {
                        $doc['score_section'] = $doc['total_nilai'] ?? '-';
                        $incomingDocsDeptHead[] = $doc;
                    }
                } elseif ($user->canAccessDepartment($docDept)) {
                    $doc['score_section'] = $doc['total_nilai'] ?? '-';
                    $incomingDocsDeptHead[] = $doc;
                }
            }

            // Incoming to Div Head (status == 'menunggu_div_head')
            if ($status === 'menunggu_div_head') {
                if ($user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head'])) {
                    if ($karyawan && $user->matchesDivisionHeadScope($karyawan)) {
                        $doc['score_dept'] = $doc['total_nilai'] ?? '-';
                        $incomingDocsDivHead[] = $doc;
                    }
                } elseif ($user->isAdmin() || $user->isSuperadmin()) {
                    $doc['score_dept'] = $doc['total_nilai'] ?? '-';
                    $incomingDocsDivHead[] = $doc;
                }
            }
        }

        // General stats
        $accessibleDocs = [];
        $myPendingCount = 0;

        foreach ($allDocs as $doc) {
            $nrp = $doc['nrp'] ?? '';
            $karyawan = $karyawanMap[$nrp] ?? null;
            $doc['karyawan'] = $karyawan;
            $docDept = $karyawan['departemen'] ?? '';

            if (!$karyawan) {
                continue;
            }
            $isAccessible = match (true) {
                $user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head']) => $user->matchesDivisionHeadScope($karyawan),
                $user->isDepartmentHead() => $user->matchesDeptHeadScope($karyawan),
                $user->isSectionHead() => $user->matchesSectionHeadScope($karyawan),
                default => $user->canAccessDepartment($docDept) || strcasecmp($karyawan['nrp'] ?? '', $user->nrp) === 0,
            };
            if (!$isAccessible) continue;
            if ($selectedDept && strcasecmp($docDept, $selectedDept) !== 0) continue;

            $status = $doc['status'] ?? 'draft';
            $needsMySign = false;

            if ($status === 'menunggu_foreman' && $user->isForeman() && strcasecmp($user->departemen, $docDept) === 0) {
                $needsMySign = true;
            } elseif ($status === 'menunggu_section_head' && $user->isSectionHead() && $user->matchesSectionHeadScope($karyawan)) {
                $needsMySign = true;
            } elseif ($status === 'menunggu_dept_head' && $user->isDepartmentHead() && $user->matchesDeptHeadScope($karyawan)) {
                $needsMySign = true;
            } elseif ($status === 'menunggu_div_head' && ($user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head'])) && $user->matchesDivisionHeadScope($karyawan)) {
                $needsMySign = true;
            }

            $doc['needs_my_sign'] = $needsMySign;
            if ($needsMySign) $myPendingCount++;

            $accessibleDocs[] = $doc;
        }

        $docIdsWithNilai = DB::table('nilai')->whereNotNull('skor')->where('skor', '>', 0)->pluck('dokumen_id')->unique()->toArray();
        $docIdsWithTtd = DB::table('tanda_tangan')->whereNotNull('ttd_image')->where('ttd_image', '!=', '')->pluck('dokumen_id')->unique()->toArray();

        $isDocEvaluated = function($doc) use ($docIdsWithNilai, $docIdsWithTtd) {
            if (!$doc) return false;
            if (($doc['status'] ?? '') === 'selesai') return true;
            if (!empty($doc['total_nilai']) && (float)$doc['total_nilai'] > 0) return true;
            if (!empty($doc['docking_final']) || !empty($doc['grade_original'])) return true;
            $docId = $doc['id'] ?? null;
            if ($docId && (in_array($docId, $docIdsWithNilai) || in_array($docId, $docIdsWithTtd))) return true;
            return false;
        };

        $adminRealEvaluatedCount = 0;
        foreach ($accessibleDocs as $ad) {
            if ($isDocEvaluated($ad)) {
                $adminRealEvaluatedCount++;
            }
        }

        $stats = [
            'total_karyawan' => $user->isForeman() ? count($foremanSubordinates) : ($user->isSectionHead() ? count($sectionSubordinates) : ($user->isDepartmentHead() ? count($deptSubordinates) : ($user->isDivisionHead() ? count($divSubordinates) : count($allKaryawan)))),
            'total_target' => $user->isForeman() ? count($foremanSubordinates) : ($user->isSectionHead() ? ($totalTargetSection ?: count($sectionSubordinates)) : ($user->isDepartmentHead() ? ($totalTargetDept ?: count($deptSubordinates)) : ($user->isDivisionHead() ? ($totalTargetDiv ?: count($divSubordinates)) : count($accessibleDocs)))),
            'total_dinilai' => $user->isForeman() ? $totalDinilai : ($user->isSectionHead() ? $sectionTotalDinilai : ($user->isDepartmentHead() ? $deptTotalDinilai : ($user->isDivisionHead() ? $divFinalTotalDinilai : $adminRealEvaluatedCount))),
            'total_belum' => $user->isForeman() ? (count($foremanSubordinates) - $totalDinilai) : ($user->isSectionHead() ? (count($sectionSubordinates) - $sectionTotalDinilai) : ($user->isDepartmentHead() ? (count($deptSubordinates) - $deptTotalDinilai) : ($user->isDivisionHead() ? (count($divSubordinates) - $divFinalTotalDinilai) : max(0, count($accessibleDocs) - $adminRealEvaluatedCount)))),
            'perlu_ttd_saya' => $myPendingCount,
            'dokumen_selesai' => count(array_filter($accessibleDocs, fn($d) => ($d['status'] ?? '') === 'selesai')),
        ];

        // Connection test report for Superadmin
        $connectionReport = null;
        if ($user->isSuperadmin()) {
            $connectionReport = [
                'spreadsheet_id' => $this->googleService->getSpreadsheetId(),
                'drive_folder_id' => $this->googleService->getDriveFolderId(),
                'is_configured' => $this->googleService->isConfigured(),
            ];
        }

        // List of accessible employees for instant lookup
        $accessibleKaryawan = [];
        foreach ($allKaryawan as $k) {
            $kDept = $k['departemen'] ?? '';
            $canSee = match (true) {
                $user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head']) => $user->matchesDivisionHeadScope($k),
                $user->isDepartmentHead() => $user->matchesDeptHeadScope($k),
                $user->isSectionHead() => $user->matchesSectionHeadScope($k),
                default => $user->canAccessDepartment($kDept) || strcasecmp($k['nrp'] ?? '', $user->nrp) === 0,
            };
            if ($canSee) {
                $accessibleKaryawan[] = $k;
            }
        }

        // =========================================================================
        // E. ADMIN & SUPERADMIN DASHBOARD METRICS (Mockup Screens 1 & 2)
        // =========================================================================
        $superadminStats = [
            'total_user' => 41,
            'total_karyawan' => 1248,
            'total_departemen' => 12,
            'aktivitas_hari_ini' => 8,
        ];

        $roleDistribution = [
            'foreman' => 18,
            'section_head' => 12,
            'dept_head' => 6,
            'div_head' => 2,
            'admin' => 2,
            'superadmin' => 1,
        ];

        $recentActivities = [
            [
                'user' => 'SARYANTA (Foreman)',
                'aksi' => 'Menandatangani digital form penilaian F1 CAT 1',
                'waktu' => '2 menit yang lalu',
                'tipe' => 'ttd',
            ],
            [
                'user' => 'KRISTIYANTO (Section Head)',
                'aksi' => 'Menyetujui dokumen penilaian FACTORY 1',
                'waktu' => '15 menit yang lalu',
                'tipe' => 'approve',
            ],
            [
                'user' => 'SUPARDI (Dept Head)',
                'aksi' => 'Melakukan penyesuaian kuota docking PROD 2',
                'waktu' => '1 jam yang lalu',
                'tipe' => 'docking',
            ],
            [
                'user' => 'Admin Penilaian Karyawan',
                'aksi' => 'Mengubah batas target kuota nilai tahun 2025',
                'waktu' => '3 jam yang lalu',
                'tipe' => 'config',
            ],
            [
                'user' => 'Super Administrator',
                'aksi' => 'Memperbarui konfigurasi alur persetujuan sistem',
                'waktu' => '5 jam yang lalu',
                'tipe' => 'system',
            ],
        ];

        // -------------------------------------------------------------------------
        // ADMIN DASHBOARD METRICS (Dynamic & Sesuai Hierarki Riil Karyawan)
        // -------------------------------------------------------------------------
        $totalKaryawanPerusahaan = count($allKaryawan);
        $uniqueDepts = array_unique(array_filter(array_map(fn($k) => strtoupper(trim((string)($k['departemen'] ?? ''))), $allKaryawan), fn($d) => $d !== '' && $d !== '-'));
        $totalDeptCount = count($uniqueDepts);

        $uniqueDivs = array_unique(array_filter(array_map(fn($k) => strtoupper(trim((string)($k['division'] ?? ''))), $allKaryawan), fn($d) => $d !== '' && $d !== '-'));
        $totalDivCount = count($uniqueDivs);

        $uniqueForemen = array_unique(array_filter(array_map(fn($k) => trim((string)($k['nrp_foreman'] ?? '')), $allKaryawan)));
        $totalForemanCount = count($uniqueForemen);

        $uniqueSectionHeads = array_unique(array_filter(array_map(fn($k) => trim((string)($k['nrp_section_head'] ?? '')), $allKaryawan)));
        $totalSectionHeadCount = count($uniqueSectionHeads);

        $docIdsWithNilai = DB::table('nilai')->whereNotNull('skor')->where('skor', '>', 0)->pluck('dokumen_id')->unique()->toArray();
        $docIdsWithTtd = DB::table('tanda_tangan')->whereNotNull('ttd_image')->where('ttd_image', '!=', '')->pluck('dokumen_id')->unique()->toArray();

        $isDocEvaluated = function($doc) use ($docIdsWithNilai, $docIdsWithTtd) {
            if (!$doc) return false;
            if (($doc['status'] ?? '') === 'selesai') return true;
            if (!empty($doc['total_nilai']) && (float)$doc['total_nilai'] > 0) return true;
            if (!empty($doc['docking_final']) || !empty($doc['grade_original'])) return true;
            $docId = $doc['id'] ?? null;
            if ($docId && (in_array($docId, $docIdsWithNilai) || in_array($docId, $docIdsWithTtd))) return true;
            return false;
        };

        $totalDinilaiCount = 0;
        foreach ($allKaryawan as $k) {
            $doc = $docsByNrp[(string)$k['nrp']] ?? null;
            if ($isDocEvaluated($doc)) {
                $totalDinilaiCount++;
            }
        }
        $totalBelumCount = max(0, $totalKaryawanPerusahaan - $totalDinilaiCount);
        $pctDinilai = $totalKaryawanPerusahaan > 0 ? round(($totalDinilaiCount / $totalKaryawanPerusahaan) * 100) : 0;
        $pctBelum = $totalKaryawanPerusahaan > 0 ? (100 - $pctDinilai) : 0;

        $adminStats = [
            'total_karyawan' => $totalKaryawanPerusahaan,
            'total_divisi' => $totalDivCount,
            'total_departemen' => $totalDeptCount,
            'total_section_head' => $totalSectionHeadCount,
            'total_foreman' => $totalForemanCount,
            'total_dinilai' => $totalDinilaiCount,
            'pct_dinilai' => $pctDinilai,
            'total_belum' => $totalBelumCount,
            'pct_belum' => $pctBelum,
        ];

        // Hierarchy Summary for Admin Dashboard
        $userMap = User::all()->keyBy(fn($u) => trim((string)($u->nrp ?? $u->username ?? '')));

        $countForemanA = count(array_filter($allKaryawan, fn($k) => ($k['nrp_foreman'] ?? '') === '62'));
        $countForemanB = count(array_filter($allKaryawan, fn($k) => ($k['nrp_foreman'] ?? '') === '51'));
        $countSectionC = count(array_filter($allKaryawan, fn($k) => ($k['nrp_section_head'] ?? '') === 'SH-11310' && empty($k['nrp_foreman'])));
        $countKasusE = count(array_filter($allKaryawan, fn($k) => ($k['nrp_section_head'] ?? '') === '99999'));

        // Dynamic Foremen breakdown from real Karyawan data
        $foremenMap = [];

        // 1. Ambil semua Foreman dari Master Karyawan (jabatan mengandung FOREMAN atau KETUA, atau role foreman di users)
        foreach ($allKaryawan as $k) {
            $jabUpper = strtoupper(trim((string)($k['jabatan'] ?? '')));
            $uObj = $userMap[$k['nrp']] ?? null;
            $uRole = strtolower(trim((string)($uObj->role ?? '')));
            $isForemanEmp = (str_contains($jabUpper, 'FOREMAN') || str_contains($jabUpper, 'KETUA') || $uRole === 'foreman') && !str_contains($jabUpper, 'SECTION');

            if ($isForemanEmp) {
                $fNrp = trim((string)$k['nrp']);
                if ($fNrp !== '' && !isset($foremenMap[$fNrp])) {
                    $nama = $uObj ? $uObj->nama : ($k['nama_lengkap'] ?? ($k['nama'] ?? "Foreman {$fNrp}"));
                    $div = trim((string)($k['division'] ?? ''));
                    if (empty($div) || $div === '-') {
                        $div = 'PRODUCTION A DIV';
                    }
                    $dept = $k['departemen'] ?? 'PROD 2';
                    $seksi = $k['seksi'] ?? '';
                    $foremenMap[$fNrp] = [
                        'level' => 'Foreman',
                        'nrp' => $fNrp,
                        'nama' => $nama,
                        'division' => $div,
                        'departemen' => $dept,
                        'seksi' => $seksi,
                        'count' => 0,
                    ];
                }
            }
        }

        // 2. Hitung binaan eksplisit yang terdaftar di nrp_foreman
        $assignedSubNrps = [];
        foreach ($allKaryawan as $k) {
            $fNrp = trim((string)($k['nrp_foreman'] ?? ''));
            if ($fNrp !== '') {
                if (!isset($foremenMap[$fNrp])) {
                    $u = $userMap[$fNrp] ?? null;
                    $leadEmp = $karyawanMap[$fNrp] ?? null;
                    $nama = $u ? $u->nama : ($leadEmp['nama_lengkap'] ?? ($leadEmp['nama'] ?? ($k['nama_foreman'] ?? "Foreman {$fNrp}")));
                    $div = trim((string)($leadEmp['division'] ?? ($k['division'] ?? '')));
                    if (empty($div) || $div === '-') {
                        $div = 'PRODUCTION A DIV';
                    }
                    $dept = $leadEmp['departemen'] ?? ($k['departemen'] ?? 'PROD 2');
                    $seksi = $leadEmp['seksi'] ?? ($k['seksi'] ?? '');
                    $foremenMap[$fNrp] = [
                        'level' => 'Foreman',
                        'nrp' => $fNrp,
                        'nama' => $nama,
                        'division' => $div,
                        'departemen' => $dept,
                        'seksi' => $seksi,
                        'count' => 0,
                    ];
                }
                $foremenMap[$fNrp]['count']++;
                $assignedSubNrps[] = $k['nrp'];
            }
        }

        // 3. Jika nrp_foreman belum diisi di database, petakan subordinat non-struktural ke Foreman di seksi/dept yang sama
        $foremenByDeptSeksi = [];
        $foremenByDept = [];
        foreach ($foremenMap as $fNrp => $fData) {
            $deptKey = strtoupper(trim($fData['departemen']));
            $seksiKey = strtoupper(trim($fData['seksi']));
            if ($seksiKey !== '' && $seksiKey !== '-') {
                $foremenByDeptSeksi["{$deptKey}|{$seksiKey}"][] = $fNrp;
            }
            $foremenByDept[$deptKey][] = $fNrp;
        }

        foreach ($allKaryawan as $k) {
            if (in_array($k['nrp'], $assignedSubNrps)) continue;
            $j = strtoupper(trim((string)($k['jabatan'] ?? '')));
            // Hanya bawahan non-manajerial (bukan Foreman, Section Head, Dept Head, Div Head)
            if (str_contains($j, 'SECTION') || str_contains($j, 'DEPT') || str_contains($j, 'DIV') || str_contains($j, 'MANAGER') || str_contains($j, 'FOREMAN') || str_contains($j, 'KETUA')) {
                continue;
            }

            $kDept = strtoupper(trim((string)($k['departemen'] ?? '')));
            $kSeksi = strtoupper(trim((string)($k['seksi'] ?? '')));

            // Cocokkan dengan Foreman pada Seksi & Dept yang sama
            if ($kSeksi !== '' && $kSeksi !== '-' && isset($foremenByDeptSeksi["{$kDept}|{$kSeksi}"])) {
                $targetFNrp = $foremenByDeptSeksi["{$kDept}|{$kSeksi}"][0];
                $foremenMap[$targetFNrp]['count']++;
                $assignedSubNrps[] = $k['nrp'];
            } elseif (isset($foremenByDept[$kDept]) && count($foremenByDept[$kDept]) === 1) {
                // Jika di departemen tersebut hanya ada 1 foreman
                $targetFNrp = $foremenByDept[$kDept][0];
                $foremenMap[$targetFNrp]['count']++;
                $assignedSubNrps[] = $k['nrp'];
            }
        }

        $foremenList = array_values($foremenMap);
        usort($foremenList, fn($a, $b) => strcmp($a['nrp'], $b['nrp']));

        // Dynamic Section Heads breakdown from real Karyawan data
        $shMap = [];
        foreach ($allKaryawan as $k) {
            $shNrp = trim((string)($k['nrp_section_head'] ?? ''));
            if ($shNrp !== '') {
                if (!isset($shMap[$shNrp])) {
                    $u = $userMap[$shNrp] ?? null;
                    $leadEmp = $karyawanMap[$shNrp] ?? null;
                    $nama = $u ? $u->nama : ($leadEmp['nama_lengkap'] ?? ($leadEmp['nama'] ?? ($k['nama_section_head'] ?? "Section Head {$shNrp}")));
                    $dept = $leadEmp['departemen'] ?? ($k['departemen'] ?? 'PROD 2');
                    $div = trim((string)($leadEmp['division'] ?? ($k['division'] ?? '')));
                    if (empty($div) || $div === '-') {
                        $div = ($dept === 'PROD 4') ? 'PRODUCTION B DIV' : 'PRODUCTION A DIV';
                    }
                    $seksi = $leadEmp['seksi'] ?? ($k['seksi'] ?? ($dept === 'PROD 1' ? 'ASSEMBLY 1 SEC.' : 'FACTORY'));
                    $shMap[$shNrp] = [
                        'level' => 'Section Head',
                        'nrp' => $shNrp,
                        'nama' => $nama,
                        'division' => $div,
                        'departemen' => $dept,
                        'seksi' => $seksi,
                        'count' => 0,
                    ];
                }
                $shMap[$shNrp]['count']++;
            }
        }
        $sectionHeadsList = array_values($shMap);

        // Dynamic Dept Heads breakdown from real Karyawan data
        $dhMap = [];
        foreach ($allKaryawan as $k) {
            $dhNrp = trim((string)($k['nrp_dept_head'] ?? ''));
            if ($dhNrp !== '') {
                if (!isset($dhMap[$dhNrp])) {
                    $u = $userMap[$dhNrp] ?? null;
                    $leadEmp = $karyawanMap[$dhNrp] ?? null;
                    $nama = $u ? $u->nama : ($leadEmp['nama_lengkap'] ?? ($leadEmp['nama'] ?? ($k['nama_dept_head'] ?? "Dept Head {$dhNrp}")));
                    $dept = $leadEmp['departemen'] ?? ($k['departemen'] ?? 'PROD 2');
                    $div = trim((string)($leadEmp['division'] ?? ($k['division'] ?? '')));
                    if (empty($div) || $div === '-') {
                        $div = 'PRODUCTION A DIV';
                    }
                    $dhMap[$dhNrp] = [
                        'level' => 'Dept Head',
                        'nrp' => $dhNrp,
                        'nama' => $nama,
                        'division' => $div,
                        'departemen' => $dept,
                        'seksi' => '-',
                        'count' => 0,
                    ];
                }
                $dhMap[$dhNrp]['count']++;
            }
        }
        $deptHeadsList = array_values($dhMap);

        // Dynamic Division Heads breakdown from real Karyawan data
        $divMap = [];
        foreach ($allKaryawan as $k) {
            $divNrp = trim((string)($k['nrp_div_head'] ?? ''));
            if ($divNrp !== '') {
                if (!isset($divMap[$divNrp])) {
                    $u = $userMap[$divNrp] ?? null;
                    $leadEmp = $karyawanMap[$divNrp] ?? null;
                    $nama = $u ? $u->nama : ($leadEmp['nama_lengkap'] ?? ($leadEmp['nama'] ?? ($k['nama_div_head'] ?? "Division Head {$divNrp}")));
                    $dept = $leadEmp['departemen'] ?? ($k['departemen'] ?? 'PROD 2');
                    $div = trim((string)($leadEmp['division'] ?? ($k['division'] ?? '')));
                    if (empty($div) || $div === '-') {
                        $div = 'PRODUCTION A DIV';
                    }
                    $divMap[$divNrp] = [
                        'level' => 'Division Head',
                        'nrp' => $divNrp,
                        'nama' => $nama,
                        'division' => $div,
                        'departemen' => $dept,
                        'seksi' => 'Seluruh Seksi',
                        'count' => 0,
                    ];
                }
                $divMap[$divNrp]['count']++;
            }
        }
        $divHeadsList = array_values($divMap);
        usort($divHeadsList, fn($a, $b) => $b['count'] <=> $a['count']);

        $adminHierarchy = [
            'foreman_a' => $countForemanA,
            'foreman_b' => $countForemanB,
            'section_c' => $countSectionC,
            'kasus_e' => $countKasusE,
            'total' => $totalKaryawanPerusahaan,
            'foremen_list' => $foremenList,
            'section_heads_list' => $sectionHeadsList,
            'dept_heads_list' => $deptHeadsList,
            'div_heads_list' => $divHeadsList,
        ];

        // Known Department / Division Definitions
        $knownDeptDefs = [
            'PROD 1' => [
                'kode' => 'PROD 1',
                'nama' => 'Production 1 (PROD 1)',
                'penilai' => 'Section Head C (Langsung)',
                'match' => ['PROD 1', 'PRODUCTION 1'],
            ],
            'PROD 2' => [
                'kode' => 'PROD 2',
                'nama' => 'Production 2 (PROD 2)',
                'penilai' => 'Foreman & Section Head ➔ Dept Head',
                'match' => ['PROD 2', 'PRODUCTION 2'],
            ],
            'PROD 3' => [
                'kode' => 'PROD 3',
                'nama' => 'Production 3 (PROD 3)',
                'penilai' => 'Section Head ➔ Dept Head',
                'match' => ['PROD 3', 'PRODUCTION 3'],
            ],
            'PROD 4' => [
                'kode' => 'PROD 4',
                'nama' => 'Production 4 (PROD 4)',
                'penilai' => 'Section Head ➔ Dept Head',
                'match' => ['PROD 4', 'PRODUCTION 4'],
            ],
            'QA/QC' => [
                'kode' => 'QA/QC',
                'nama' => 'Quality Control & QA Division',
                'penilai' => 'Quality Assurance Section',
                'match' => ['QA', 'QUALITY CONTROL', 'QUALITY ASSURANCE (QA)', 'QC'],
            ],
            'ENGINEERING' => [
                'kode' => 'ENGINEERING',
                'nama' => 'Technical Engineering Division',
                'penilai' => 'Foreman E ➔ Section Head E ➔ DH E',
                'match' => ['ENGINEERING', 'ENG'],
            ],
            'HR & GA' => [
                'kode' => 'HR & GA',
                'nama' => 'General Affairs, HR & IR Division',
                'penilai' => 'General Affairs & HR Section',
                'match' => ['HR & IR DEPT.', 'HR', 'GA', 'GENERAL AFFAIRS', 'GA DEPT.'],
            ],
        ];

        // Extract real departments present in Master Karyawan
        $karyawanDeptCounts = [];
        $karyawanDeptDivisions = [];
        foreach ($allKaryawan as $k) {
            $d = strtoupper(trim((string)($k['departemen'] ?? '')));
            if ($d !== '' && $d !== '-') {
                $karyawanDeptCounts[$d] = ($karyawanDeptCounts[$d] ?? 0) + 1;
                $div = trim((string)($k['division'] ?? ''));
                if ($div !== '' && $div !== '-' && !isset($karyawanDeptDivisions[$d])) {
                    $karyawanDeptDivisions[$d] = $div;
                }
            }
        }

        $divisiDefs = [];
        $matchedKnownKeys = [];

        foreach ($knownDeptDefs as $kKey => $kDef) {
            $hasMatch = false;
            foreach ($kDef['match'] as $m) {
                if (isset($karyawanDeptCounts[strtoupper($m)])) {
                    $hasMatch = true;
                    $matchedKnownKeys[strtoupper($m)] = true;
                }
            }
            if ($hasMatch) {
                $divisiDefs[] = $kDef;
            }
        }

        // Auto-discover dynamic departments from Master Karyawan that weren't in knownDeptDefs
        foreach ($karyawanDeptCounts as $dName => $cnt) {
            if (!isset($matchedKnownKeys[$dName])) {
                $cleanDept = preg_replace('/^PROD\s*(\d+)$/i', 'Production $1', $dName);
                $divName = $karyawanDeptDivisions[$dName] ?? '';
                $nama = ($divName !== '' && $divName !== '-') ? "{$cleanDept} ({$dName})" : "Departemen {$cleanDept}";
                $divisiDefs[] = [
                    'kode' => $dName,
                    'nama' => $nama,
                    'penilai' => 'Hierarki Sesuai Master',
                    'match' => [$dName],
                ];
            }
        }

        // Only fallback if master karyawan actually has employees but departments were unassigned
        if (empty($divisiDefs) && !empty($allKaryawan)) {
            $divisiDefs = [
                $knownDeptDefs['PROD 1'],
                $knownDeptDefs['PROD 2'],
            ];
        }

        $penilaiMapping = [
            'PROD 1' => 'Section Head C (Langsung)',
            'PROD 2' => 'Foreman A & B ➔ Section Head A',
            'PROD 3' => 'Section Head ➔ Dept Head',
            'PROD 4' => 'Section Head ➔ Dept Head',
            'ENGINEERING' => 'Foreman E ➔ Section Head E ➔ DH E',
            'QA/QC' => 'Quality Assurance Section',
            'HR & GA' => 'General Affairs & HR Section',
        ];

        $deptParamMapping = [
            'PROD 1' => 'PROD 1',
            'PROD 2' => 'PROD 2',
            'PROD 3' => 'PROD 3',
            'PROD 4' => 'PROD 4',
            'ENGINEERING' => 'ENGINEERING',
            'QA/QC' => 'QUALITY ASSURANCE (QA)',
            'HR & GA' => 'HR & IR DEPT.',
        ];

        // Fetch headcount quotas from pengaturan or kuota_departemen for real-time target matrix
        $allPengaturan = DB::table('pengaturan')->get()->keyBy('key');
        $allKuotaDept = DB::table('kuota_departemen')->get();

        $deptSums = [];
        foreach ($allKuotaDept as $kd) {
            $d = strtoupper(trim((string)$kd->departemen));
            if (!isset($deptSums[$d])) {
                $deptSums[$d] = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0, 'total' => 0];
            }
            $deptSums[$d]['S'] += (int)$kd->kuota_s;
            $deptSums[$d]['A'] += (int)$kd->kuota_a;
            $deptSums[$d]['B'] += (int)$kd->kuota_b;
            $deptSums[$d]['C'] += (int)$kd->kuota_c;
            $deptSums[$d]['K'] += (int)$kd->kuota_k;
            $deptSums[$d]['total'] += (int)$kd->total_karyawan;
        }

        $aktivitasDivisi = [];
        $ringkasanDivisi = [];
        $sumTargetCompanyS = 0;
        $sumTargetCompanyA = 0;
        $sumTargetCompanyB = 0;
        $sumTargetCompanyC = 0;
        $sumTargetCompanyK = 0;

        foreach ($divisiDefs as $def) {
            $matchingKaryawan = array_filter($allKaryawan, function($k) use ($def) {
                return in_array(strtoupper($k['departemen'] ?? ''), $def['match']);
            });
            $target = count($matchingKaryawan);

            $dinilai = 0;
            $deptRealS = 0; $deptRealA = 0; $deptRealB = 0; $deptRealC = 0; $deptRealK = 0;
            foreach ($matchingKaryawan as $mk) {
                $doc = $docsByNrp[(string)$mk['nrp']] ?? null;
                if ($isDocEvaluated($doc)) {
                    $dinilai++;
                    $score = $doc['total_nilai'] ?? 0;
                    $g = $doc['docking_final'] ?? (($score > 0) ? $this->calcService->scoreToGrade($score) : ($doc['grade_original'] ?? 'B'));
                    match($g) {
                        'S' => $deptRealS++,
                        'A' => $deptRealA++,
                        'B' => $deptRealB++,
                        'C' => $deptRealC++,
                        'K' => $deptRealK++,
                        default => $deptRealB++,
                    };
                }
            }

            $belum = max(0, $target - $dinilai);
            $pct = $target > 0 ? round(($dinilai / $target) * 100) : 0;

            $statusBadge = match(true) {
                $target === 0 => ['label' => 'Standby', 'bg' => 'bg-slate-100 text-slate-500 border-slate-200'],
                $pct === 100 => ['label' => '✓ Tuntas', 'bg' => 'bg-emerald-100 text-emerald-800 border-emerald-300'],
                $pct > 0 => ['label' => 'Sedang Berjalan', 'bg' => 'bg-blue-100 text-blue-800 border-blue-300'],
                default => ['label' => 'Belum Mulai', 'bg' => 'bg-amber-100 text-amber-800 border-amber-300'],
            };

            // Calculate Target Kuota per Rank (S, A, B, C, K) dynamically
            $customSettingKey = 'target_kuota_dept_' . $def['kode'];
            $customSetting = isset($allPengaturan[$customSettingKey]) ? json_decode($allPengaturan[$customSettingKey]->value, true) : null;

            if ($customSetting && isset($customSetting['S'])) {
                $tS = (int)($customSetting['S'] ?? 0);
                $tA = (int)($customSetting['A'] ?? 0);
                $tB = (int)($customSetting['B'] ?? 0);
                $tC = (int)($customSetting['C'] ?? 0);
                $tK = (int)($customSetting['K'] ?? 0);
            } elseif (isset($deptSums[$def['kode']]) && $deptSums[$def['kode']]['total'] > 0) {
                $tS = $deptSums[$def['kode']]['S'];
                $tA = $deptSums[$def['kode']]['A'];
                $tB = $deptSums[$def['kode']]['B'];
                $tC = $deptSums[$def['kode']]['C'];
                $tK = $deptSums[$def['kode']]['K'];
            } else {
                if ($target > 0) {
                    $tS = max(0, (int)round($target * 0.10));
                    $tA = max(0, (int)round($target * 0.30));
                    $tB = max(0, (int)round($target * 0.30));
                    $tC = max(0, (int)round($target * 0.20));
                    $tK = max(0, $target - ($tS + $tA + $tB + $tC));
                } else {
                    $tS = $tA = $tB = $tC = $tK = 0;
                }
            }

            $sumTargetCompanyS += $tS;
            $sumTargetCompanyA += $tA;
            $sumTargetCompanyB += $tB;
            $sumTargetCompanyC += $tC;
            $sumTargetCompanyK += $tK;

            $aktivitasDivisi[] = [
                'kode' => $def['kode'],
                'nama' => $def['nama'],
                'target' => $target,
                'dinilai' => $dinilai,
                'belum' => $belum,
                'persentase' => $pct,
                'penilai' => $penilaiMapping[$def['kode']] ?? '-',
                'status' => $statusBadge,
                'filter_dept' => $deptParamMapping[$def['kode']] ?? $def['kode'],
            ];

            $ringkasanDivisi[] = [
                'kode' => $def['kode'],
                'divisi' => $def['nama'],
                'division_name' => $karyawanDeptDivisions[$def['kode']] ?? ($def['kode'] === 'PROD 4' ? 'PRODUCTION B DIV' : 'PRODUCTION A DIV'),
                'total_karyawan' => $target,
                'sudah_dinilai' => $dinilai,
                'belum_dinilai' => $belum,
                'target' => $pct . '%',
                'pct_val' => $pct,
                'penilai' => $penilaiMapping[$def['kode']] ?? '-',
                'status' => $statusBadge,
                'filter_dept' => $deptParamMapping[$def['kode']] ?? $def['kode'],
                'target_s' => $tS,
                'target_a' => $tA,
                'target_b' => $tB,
                'target_c' => $tC,
                'target_k' => $tK,
                'total_target' => $tS + $tA + $tB + $tC + $tK,
                'real_s' => $deptRealS,
                'real_a' => $deptRealA,
                'real_b' => $deptRealB,
                'real_c' => $deptRealC,
                'real_k' => $deptRealK,
            ];
        }

        $totalTargetCompany = [
            'S' => $sumTargetCompanyS,
            'A' => $sumTargetCompanyA,
            'B' => $sumTargetCompanyB,
            'C' => $sumTargetCompanyC,
            'K' => $sumTargetCompanyK,
            'total' => $sumTargetCompanyS + $sumTargetCompanyA + $sumTargetCompanyB + $sumTargetCompanyC + $sumTargetCompanyK,
        ];

        // Total Penilaian Company (S, A, B, C, K distribution)
        $totalPenilaianCompany = [
            'S' => 0,
            'A' => 0,
            'B' => 0,
            'C' => 0,
            'K' => 0,
        ];
        foreach ($allKaryawan as $k) {
            $doc = $docsByNrp[(string)$k['nrp']] ?? null;
            if ($isDocEvaluated($doc)) {
                $score = $doc['total_nilai'] ?? 0;
                $g = $doc['docking_final'] ?? (($score > 0) ? $this->calcService->scoreToGrade($score) : ($doc['grade_original'] ?? 'B'));
                if (isset($totalPenilaianCompany[$g])) {
                    $totalPenilaianCompany[$g]++;
                } else {
                    $totalPenilaianCompany['B']++;
                }
            }
        }

        $foremanTargetKjMatrix = $foremanTargetKjMatrix ?? [];
        $foremanTargetKjSummary = $foremanTargetKjSummary ?? ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0, 'total' => 0];
        $foremanActualKjMatrix = $foremanActualKjMatrix ?? [];
        $foremanActualKjSummary = $foremanActualKjSummary ?? ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0, 'total' => 0];
        $totalTargetForeman = $totalTargetForeman ?? count($foremanSubordinates);

        return view('dashboard.index', compact(
            'user', 
            'stats', 
            'accessibleDocs', 
            'activePeriod', 
            'connectionReport', 
            'allDepts', 
            'selectedDept', 
            'accessibleKaryawan',
            'foremanSubordinates',
            'gradeCounts',
            'targetCounts',
            'foremanTargetKjMatrix',
            'foremanTargetKjSummary',
            'foremanActualKjMatrix',
            'foremanActualKjSummary',
            'totalTargetForeman',
            'foremanNeedActionCount',
            'foremanDoneCount',
            'foremanDraftCount',
            'foremanBelumCount',
            'foremanRevisiCount',
            'foremanSelesaiCount',
            'foremanSudahDinilaiCount',
            'foremanShSudahDinilai',
            'foremanShBelumDinilai',
            'incomingDocsSectionHead',
            'incomingDocsDeptHead',
            'sectionSubordinates',
            'sectionMandiriCount',
            'sectionBawahanCount',
            'totalTargetSection',
            'rekapForeman',
            'sectionTargetCounts',
            'sectionTargetKjMatrix',
            'sectionTargetKjSummary',
            'foremanGradeCounts',
            'sectionGradeCounts',
            'sectionActualKjMatrix',
            'sectionActualKjSummary',
            'sectionTotalDinilai',
            'foremanTotalDinilai',
            'foremanStageCounts',
            'hasForemanSubordinates',
            'sectionNeedActionCount',
            'sectionDoneCount',
            'sectionDraftCount',
            'sectionBelumCount',
            'sectionRevisiCount',
            'sectionSelesaiCount',
            'sectionSudahDinilaiCount',
            'waitingDeptCount',
            'waitingDivCount',
            'deptSubordinates',
            'totalTargetDept',
            'deptTargetCounts',
            'deptTargetKjMatrix',
            'deptTargetKjSummary',
            'deptActualKjMatrix',
            'deptActualKjSummary',
            'deptSectionGradeCounts',
            'deptFinalGradeCounts',
            'rekapSection',
            'deptTotalKaryawan',
            'deptTotalDinilai',
            'isDeptWithoutForeman',
            'hasAnyForeman',
            'deptStageCounts',
            'deptSubordinateRows',
            'deptMandiriCount',
            'deptAnggotaCount',
            'deptDoneCount',
            'deptNeedActionCount',
            'deptWaitingSectionCount',
            'deptForemanTotalDinilai',
            'deptForemanGradeCounts',
            'deptSectionTotalDinilai',
            'deptForemanDoneCount',
            'deptSectionDoneCount',
            'targetPercentages',
            'divSubordinates',
            'totalTargetDiv',
            'divTargetCounts',
            'divTargetKjMatrix',
            'divTargetKjSummary',
            'divActualKjMatrix',
            'divActualKjSummary',
            'divNeedActionCount',
            'divSectionGradeCounts',
            'divDeptGradeCounts',
            'divFinalGradeCounts',
            'divSectionTotalDinilai',
            'divDeptTotalDinilai',
            'divFinalTotalDinilai',
            'divStageCounts',
            'divSubordinateRows',
            'divActualGradeCounts',
            'divActualGradeTotal',
            'incomingDocsDivHead',
            'rekapDept',
            'selfKaryawan',
            'selfDoc',
            'selfCalc',
            'selfScoreFormatted',
            'selfGrade',
            'selfStatus',
            'adminStats',
            'adminHierarchy',
            'aktivitasDivisi',
            'totalPenilaianCompany',
            'ringkasanDivisi',
            'totalTargetCompany',
            'superadminStats',
            'roleDistribution',
            'recentActivities'
        ));
    }

    /**
     * Simpan Pengaturan Target Kuota Penilaian (S, A, B, C, K)
     */
    public function simpanTargetKuota(Request $request)
    {
        $s = (int)$request->input('quota_s', 10);
        $a = (int)$request->input('quota_a', 30);
        $b = (int)$request->input('quota_b', 30);
        $c = (int)$request->input('quota_c', 20);
        $k = (int)$request->input('quota_k', 10);

        $total = $s + $a + $b + $c + $k;
        if ($total !== 100) {
            return redirect()->back()->with('error', "Total persentase target kuota harus tepat 100% (saat ini: {$total}%).");
        }

        \Illuminate\Support\Facades\DB::table('pengaturan')->updateOrInsert(
            ['key' => 'target_kuota_pct'],
            [
                'value' => json_encode(['S' => $s, 'A' => $a, 'B' => $b, 'C' => $c, 'K' => $k]),
                'updated_at' => now(),
            ]
        );

        return redirect()->back()->with('success', "Target kuota penilaian berhasil disimpan! S: {$s}%, A: {$a}%, B: {$b}%, C: {$c}%, K: {$k}%");
    }
}
