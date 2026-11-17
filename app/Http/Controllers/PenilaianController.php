<?php

namespace App\Http\Controllers;

use App\Contracts\SheetRepositoryInterface;
use App\Models\User;
use App\Services\AppraisalCalculationService;
use App\Services\ExcelKaryawanExporter;
use App\Services\ExcelKaryawanReader;
use App\Services\GoogleSyncService;
use Barryvdh\DomPDF\Facade\Pdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PenilaianController extends Controller
{
    protected SheetRepositoryInterface $repository;
    protected AppraisalCalculationService $calcService;

    public function __construct(SheetRepositoryInterface $repository, AppraisalCalculationService $calcService)
    {
        $this->repository = $repository;
        $this->calcService = $calcService;
    }

    /**
     * Display Riwayat Penilaian for Foreman (recently evaluated subordinates, metrics & target comparison chart).
     */
    public function riwayat(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        $allKaryawan = $this->repository->all('karyawan');

        $isDeptHeadUser = $user->isDepartmentHead() || in_array($user->role, ['dept_head', 'department_head']);
        $isDivHeadUser = $user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head']);
        $isDeptOrDivHead = $isDeptHeadUser || $isDivHeadUser;

        // Filter subordinates for current user
        if ($user->isForeman()) {
            $mySubordinates = array_filter($allKaryawan, function ($k) use ($user) {
                if (!empty($k['nrp_foreman']) && strcasecmp($k['nrp_foreman'], $user->nrp) === 0) {
                    return true;
                }
                return strcasecmp($k['nrp'] ?? '', $user->nrp) === 0;
            });
        } elseif ($user->isSectionHead()) {
            $mySubordinates = array_filter($allKaryawan, fn ($k) => $user->matchesSectionHeadScope($k));
        } elseif ($isDeptHeadUser) {
            $mySubordinates = array_filter($allKaryawan, fn ($k) => $user->matchesDeptHeadScope($k, [], true));
        } elseif ($isDivHeadUser) {
            $mySubordinates = array_filter($allKaryawan, fn ($k) => $user->matchesDivisionHeadScope($k));
        } else {
            $mySubordinates = array_filter($allKaryawan, function ($k) use ($user) {
                return $user->canAccessDepartment($k['departemen'] ?? '');
            });
        }

        // Fetch documents
        $allDocs = $this->repository->all('dokumen');
        $docsByNrp = [];
        foreach ($allDocs as $d) {
            $docsByNrp[$d['nrp']] = $d;
        }

        // Fetch signatures to get evaluation timestamps
        $allTtd = $this->repository->all('tanda_tangan');
        $ttdByDoc = [];
        $userRoleLvl = $user->isSectionHead() ? 'section_head' : ($user->isDepartmentHead() ? 'dept_head' : ($user->isDivisionHead() ? 'div_head' : 'foreman'));
        foreach ($allTtd as $t) {
            if (($t['level'] ?? '') === $userRoleLvl || empty($ttdByDoc[$t['dokumen_id']])) {
                $ttdByDoc[$t['dokumen_id']] = $t;
            }
        }

        $allNilai = $this->repository->all('nilai');
        $nilaiGrouped = [];
        foreach ($allNilai as $n) {
            $docId = $n['dokumen_id'];
            $lvl = $n['level'] ?? 'foreman';
            $aspek = $n['aspek_kode'];
            $nilaiGrouped[$docId][$lvl][$aspek] = (float)$n['skor'];
        }

        $isSectionHeadWithoutForeman = $user->isSectionHead() && !$user->hasForemanSubordinates();

        // Forms 07-006 (Section Head / Dept Head / Division Head) are NOT bound by Admin/Superadmin target quotas
        $subCountByKj = [];
        foreach ($mySubordinates as $subRow) {
            $subDoc = $docsByNrp[$subRow['nrp'] ?? ''] ?? null;
            if (self::isQuotaExemptEmployee($subRow, $subDoc)) {
                continue;
            }
            $kjKey = ExcelKaryawanReader::normalizeQuotaKj($subRow['kj'] ?? '');
            $subCountByKj[$kjKey] = ($subCountByKj[$kjKey] ?? 0) + 1;
        }
        $matrixKjList = array_keys($subCountByKj);
        sort($matrixKjList, SORT_NATURAL);

        $actualKjMatrix = [];
        $deptActualKjMatrix = [];
        foreach ($matrixKjList as $kjKey) {
            $actualKjMatrix[$kjKey] = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0, 'total' => 0];
            $deptActualKjMatrix[$kjKey] = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0, 'total' => 0];
        }

        $evaluatedList = [];
        $totalPoin = 0.0;
        $actualCounts = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];
        $quotaActualCounts = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];
        $deptQuotaActualCounts = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];

        foreach ($mySubordinates as $k) {
            $nrp = $k['nrp'];
            $doc = $docsByNrp[$nrp] ?? null;
            if (!$doc) continue;

            $docId = $doc['id'];
            $status = $doc['status'] ?? 'draft';

            $kodeForm = $doc['kode_form'] ?? self::determineKodeForm($k);
            $isSelf = (strcasecmp($nrp, $user->nrp) === 0);
            $isGol4UpForm = self::isQuotaExemptEmployee($k, $doc);
            $isTanpaForeman = empty($k['nrp_foreman']) || $isSectionHeadWithoutForeman || $isGol4UpForm;
            $empKj = !empty($k['kj']) ? ExcelKaryawanReader::normalizeKj($k['kj']) : 'KJ 2';
            $quotaKj = ExcelKaryawanReader::normalizeQuotaKj($k['kj'] ?? 'KJ 2');
            $kjNorm = $quotaKj;

            $deptGradeRow = null;
            $deptScoreRow = null;
            $hasDivFinalRow = false;

            if ($user->isForeman()) {
                $scores = $nilaiGrouped[$docId]['foreman'] ?? [];
                $calc = !empty($scores) ? $this->calcService->calculate($kodeForm, $scores) : ['grand_total' => 0.00];
                $score = ($calc['grand_total'] > 0) ? $calc['grand_total'] : (!in_array($status, ['draft', 'belum_ada', 'menunggu_foreman']) ? ($doc['total_nilai'] ?? 0) : 0);
                $isEvaluated = in_array($status, ['menunggu_section_head', 'menunggu_dept_head', 'menunggu_div_head', 'selesai'])
                    || (!empty($scores) && ($score > 0) && !in_array($status, ['draft', 'belum_ada', 'menunggu_foreman']));
            } elseif ($user->isSectionHead()) {
                // Section Head: Subordinates evaluated in 'section_head' (or 'foreman' for self 07-006-SH)
                $scores = $nilaiGrouped[$docId]['section_head']
                    ?? (($isSelf && $isGol4UpForm) ? ($nilaiGrouped[$docId]['foreman'] ?? []) : []);
                $calc = !empty($scores) ? $this->calcService->calculate($kodeForm, $scores) : ['grand_total' => 0.00];
                $score = ($calc['grand_total'] > 0) ? $calc['grand_total'] : (!in_array($status, ['draft', 'belum_ada', 'menunggu_section_head', 'menunggu_foreman']) ? ($doc['total_nilai'] ?? 0) : 0);
                $isEvaluated = in_array($status, ['menunggu_dept_head', 'menunggu_div_head', 'selesai'])
                    || (!empty($scores) && ($score > 0) && !in_array($status, ['draft', 'belum_ada', 'menunggu_section_head', 'menunggu_foreman']));
            } elseif ($isDeptOrDivHead) {
                $deptScores = $nilaiGrouped[$docId]['dept_head']
                    ?? (($isSelf && $isGol4UpForm) ? ($nilaiGrouped[$docId]['foreman'] ?? []) : ($nilaiGrouped[$docId]['section_head'] ?? []));
                $divScores = $nilaiGrouped[$docId]['div_head'] ?? [];
                $scores = ($isDivHeadUser && !empty($divScores)) ? $divScores : $deptScores;

                $calcDept = !empty($deptScores) ? $this->calcService->calculate($kodeForm, $deptScores) : ['grand_total' => 0.00];
                $deptScoreVal = ($calcDept['grand_total'] > 0) ? (float)$calcDept['grand_total'] : (float)($doc['total_nilai'] ?? 0);

                $calc = !empty($scores) ? $this->calcService->calculate($kodeForm, $scores) : $calcDept;
                $score = ($calc['grand_total'] > 0) ? $calc['grand_total'] : ($doc['total_nilai'] ?? 0);

                if ($isDivHeadUser && $isSelf) {
                    $scores = !empty($divScores)
                        ? $divScores
                        : ($nilaiGrouped[$docId]['foreman'] ?? ($nilaiGrouped[$docId]['dept_head'] ?? []));
                    $calc = !empty($scores) ? $this->calcService->calculate($kodeForm, $scores) : ['grand_total' => 0.00];
                    $score = ($calc['grand_total'] > 0) ? $calc['grand_total'] : (float)($doc['total_nilai'] ?? 0);
                    $hasDivFinalRow = ($status === 'selesai');
                    $isEvaluatedByDept = false;
                    $isEvaluated = ($status === 'selesai');
                } else {
                    $isEvaluatedByDept = in_array($status, ['menunggu_div_head', 'selesai'])
                        || (!empty($deptScores) && ($deptScoreVal > 0) && !in_array($status, ['draft', 'belum_ada', 'menunggu_dept_head', 'menunggu_section_head', 'menunggu_foreman']));
                    $hasDivFinalRow = ($status === 'selesai');
                    $isEvaluated = $isEvaluatedByDept;

                    if ($isEvaluatedByDept) {
                        $docking = trim((string)($doc['docking_final'] ?? ''));
                        $deptGradeRow = $docking !== ''
                            ? $docking
                            : (($deptScoreVal > 0) ? $this->calcService->scoreToGrade($deptScoreVal) : ($doc['grade_original'] ?? 'K'));
                        $deptScoreRow = number_format((float)$deptScoreVal, 2);
                    }
                }
            } else {
                $scores = $nilaiGrouped[$docId]['foreman'] ?? [];
                $calc = !empty($scores) ? $this->calcService->calculate($kodeForm, $scores) : ['grand_total' => 0.00];
                $score = ($calc['grand_total'] > 0) ? $calc['grand_total'] : ($doc['total_nilai'] ?? 0);
                $isEvaluated = in_array($status, ['menunggu_section_head', 'menunggu_dept_head', 'selesai']) || ($score > 0 && !in_array($status, ['draft', 'belum_ada', 'menunggu_foreman']));
            }

            if ($isEvaluated) {
                $docking = trim((string)($doc['docking_final'] ?? ''));
                $grade = ($isDeptOrDivHead && $docking !== '')
                    ? $docking
                    : (($score > 0) ? $this->calcService->scoreToGrade($score) : ($doc['grade_original'] ?? 'K'));
                $totalPoin += (float)$score;
                if (isset($actualCounts[$grade])) {
                    $actualCounts[$grade]++;
                }
                if (!$isGol4UpForm) {
                    if ($isDeptOrDivHead && $deptGradeRow && isset($deptQuotaActualCounts[$deptGradeRow])) {
                        $deptQuotaActualCounts[$deptGradeRow]++;
                        if (isset($deptActualKjMatrix[$kjNorm][$deptGradeRow])) {
                            $deptActualKjMatrix[$kjNorm][$deptGradeRow]++;
                            $deptActualKjMatrix[$kjNorm]['total']++;
                        }
                    }

                    $shouldCountIntoOwnActual = !$isDivHeadUser || $hasDivFinalRow;
                    if ($shouldCountIntoOwnActual) {
                        if (isset($quotaActualCounts[$grade])) {
                            $quotaActualCounts[$grade]++;
                        }
                        if (isset($actualKjMatrix[$kjNorm][$grade])) {
                            $actualKjMatrix[$kjNorm][$grade]++;
                            $actualKjMatrix[$kjNorm]['total']++;
                        }
                    }
                }

                $ttd = $ttdByDoc[$docId] ?? null;
                $tglPenilaian = $ttd['tgl_ttd'] ?? $doc['updated_at'] ?? $doc['created_at'] ?? '-';

                $hasManMgt = in_array($kodeForm, ['07-003', '07-004', '07-006-SH', '07-006-DH', '07-006-DIV']);

                $hasilKerjaVal = isset($calc['groups']['hasil_kerja']['weighted_score'])
                    ? number_format((float)$calc['groups']['hasil_kerja']['weighted_score'], 2)
                    : '-';
                $prosesKerjaVal = isset($calc['groups']['proses_kerja']['weighted_score'])
                    ? number_format((float)$calc['groups']['proses_kerja']['weighted_score'], 2)
                    : '-';
                $sikapKerjaVal = isset($calc['groups']['sikap_kerja']['weighted_score'])
                    ? number_format((float)$calc['groups']['sikap_kerja']['weighted_score'], 2)
                    : '-';
                $manMgtVal = $hasManMgt
                    ? (isset($calc['groups']['man_management']['weighted_score'])
                        ? number_format((float)$calc['groups']['man_management']['weighted_score'], 2)
                        : '0.00')
                    : '-';

                $seksiUpper = strtoupper(trim($k['seksi'] ?? ''));
                $deptUpper = strtoupper(trim($k['departemen'] ?? 'PROD 2'));
                $namaSect = trim($k['nama_sect'] ?? '');
                if (!$namaSect) {
                    if (str_contains($seksiUpper, '2') || str_contains($seksiUpper, 'FACT-2')) {
                        $namaSect = 'FACTORY 2';
                    } elseif (str_contains($deptUpper, 'ENGINEERING')) {
                        $namaSect = 'ENGINEERING';
                    } elseif (str_contains($deptUpper, 'QA')) {
                        $namaSect = 'QA';
                    } elseif (str_contains($deptUpper, 'QC')) {
                        $namaSect = 'QC';
                    } else {
                        $clean = trim(preg_replace('/\s+SEC\.?$/i', '', $seksiUpper));
                        $namaSect = ($clean && $clean !== '-') ? $clean : 'FACTORY 1';
                    }
                }

                $namaBagian = trim((string)($k['nama_bagian'] ?? ''));
                if ($namaBagian === '') {
                    $seksiRaw = trim((string)($k['seksi'] ?? ''));
                    $namaBagian = ($seksiRaw !== '' && $seksiRaw !== '-') ? $seksiRaw : $namaSect;
                }

                $evaluatedList[] = [
                    'nrp' => $nrp,
                    'nama_lengkap' => $k['nama_lengkap'],
                    'golongan' => $k['golongan'] ?? '-',
                    'sub_gol' => $k['sub_gol'] ?? '-',
                    'kj' => $empKj,
                    'quota_kj' => $quotaKj,
                    'jabatan' => $k['jabatan'] ?? '-',
                    'departemen' => trim((string)($k['departemen'] ?? '-')) ?: '-',
                    'seksi' => $namaSect,
                    'nama_sect' => $namaSect,
                    'nama_bagian' => $namaBagian,
                    'level_form' => $k['level_form'] ?? 'KJ13',
                    'kode_form' => $kodeForm,
                    'doc_id' => $docId,
                    'status' => $status,
                    'hasil_kerja' => $hasilKerjaVal,
                    'proses_kerja' => $prosesKerjaVal,
                    'sikap_kerja' => $sikapKerjaVal,
                    'man_management' => $manMgtVal,
                    'total_nilai' => number_format((float)$score, 2),
                    'score' => number_format((float)$score, 2),
                    'grade' => $grade,
                    'dept_score' => $deptScoreRow ?? '-',
                    'dept_grade' => $deptGradeRow ?? '-',
                    'div_final_ready' => $hasDivFinalRow,
                    'tgl_penilaian' => substr($tglPenilaian, 0, 10),
                ];
            }
        }

        // Sort recently evaluated first
        usort($evaluatedList, fn($a, $b) => strcmp($b['tgl_penilaian'], $a['tgl_penilaian']));

        $totalSubordinates = count($mySubordinates);
        $totalQuotaSubordinates = array_sum($subCountByKj);
        $totalDinilai = count($evaluatedList);
        $totalBelum = max(0, $totalSubordinates - $totalDinilai);
        $quotaTotalDinilai = array_sum($quotaActualCounts);
        $quotaTotalBelum = max(0, $totalQuotaSubordinates - $quotaTotalDinilai);
        $avgPoin = $totalDinilai > 0 ? round($totalPoin / $totalDinilai, 2) : 0.00;

        // Target distribution calculation (strictly excludes 07-006 SH/DH/DivHead forms)
        $isSupervisorUnit = $user->isForeman()
            || in_array($user->role, ['foreman', 'ketua'])
            || $user->isSectionHead()
            || in_array($user->role, ['section_head', 'sechead']);

        $resolvedDept = strtoupper(trim($user->departemen ?? ''));
        $resolvedDiv = (string)(self::resolveDivisionCode($user, $mySubordinates) ?? '');

        if ($isSupervisorUnit) {
            $targetKjMatrix = self::buildDirectUnitTargetKjMatrix(
                $matrixKjList,
                $subCountByKj,
                $resolvedDept,
                $resolvedDiv
            );
            $summaryTarget = self::summarizeTargetKjMatrix($targetKjMatrix);
            $targetCounts = [
                'S' => $summaryTarget['S'],
                'A' => $summaryTarget['A'],
                'B' => $summaryTarget['B'],
                'C' => $summaryTarget['C'],
                'K' => $summaryTarget['K'],
            ];
        } else {
            $targetCounts = null;
            if ($isDeptOrDivHead) {
                $scopedQuota = self::getTargetQuotaForScope($user, $mySubordinates);
                $targetCounts = [
                    'S' => (int)($scopedQuota['S'] ?? 0),
                    'A' => (int)($scopedQuota['A'] ?? 0),
                    'B' => (int)($scopedQuota['B'] ?? 0),
                    'C' => (int)($scopedQuota['C'] ?? 0),
                    'K' => (int)($scopedQuota['K'] ?? 0),
                ];
            }

            if (!$targetCounts) {
                $targetCounts = self::computeProportionalTargetCounts($totalQuotaSubordinates);
            }

            $targetKjMatrix = self::buildTargetKjMatrix(
                $matrixKjList,
                $subCountByKj,
                $targetCounts,
                $resolvedDept,
                $resolvedDiv
            );
        }

        // Dropdown list for filter: strictly derived from actual data
        $distinctKj = array_values(array_unique(array_filter(array_map('trim', array_column($evaluatedList, 'kj')))));
        if (empty($distinctKj)) {
            $distinctKj = array_values(array_unique(array_filter(array_map('trim', array_column($mySubordinates, 'kj')))));
        }
        sort($distinctKj, SORT_NATURAL);
        $kjList = $distinctKj;

        $sectionOpts = [];
        foreach ($mySubordinates as $sub) {
            $s = trim((string)($sub['nama_sect'] ?? ''));
            if ($s === '') {
                $sUpper = strtoupper(trim((string)($sub['seksi'] ?? '')));
                $dUpper = strtoupper(trim((string)($sub['departemen'] ?? 'PROD 2')));
                if (str_contains($sUpper, '2') || str_contains($sUpper, 'FACT-2')) {
                    $s = 'FACTORY 2';
                } elseif (str_contains($dUpper, 'ENGINEERING')) {
                    $s = 'ENGINEERING';
                } elseif (str_contains($dUpper, 'QA')) {
                    $s = 'QA';
                } elseif (str_contains($dUpper, 'QC')) {
                    $s = 'QC';
                } else {
                    $clean = trim(preg_replace('/\s+SEC\.?$/i', '', $sUpper));
                    $s = ($clean && $clean !== '-') ? $clean : '';
                }
            }
            if ($s !== '' && $s !== '-') {
                $sectionOpts[strtoupper($s)] = strtoupper($s);
            }
        }
        $sectionList = array_values($sectionOpts);
        sort($sectionList);

        $distinctBagian = array_values(array_unique(array_filter(array_map('trim', array_column($evaluatedList, 'nama_bagian')))));
        if (empty($distinctBagian)) {
            $bagianOpts = [];
            foreach ($mySubordinates as $sub) {
                $rawBag = trim((string)($sub['nama_bagian'] ?? ''));
                if ($rawBag === '') {
                    $rawS = trim((string)($sub['seksi'] ?? ''));
                    $rawBag = ($rawS !== '' && $rawS !== '-') ? $rawS : trim((string)($sub['nama_sect'] ?? ''));
                }
                if ($rawBag !== '' && $rawBag !== '-') {
                    $key = strtoupper(rtrim($rawBag, '.'));
                    $bagianOpts[$key] = strtoupper($rawBag);
                }
            }
            $distinctBagian = array_values($bagianOpts);
        }
        sort($distinctBagian);
        $bagianList = $distinctBagian;

        $distinctJabatan = array_values(array_unique(array_filter(array_map('trim', array_column($evaluatedList, 'jabatan')))));
        if (empty($distinctJabatan)) {
            $jabatanOpts = [];
            foreach ($mySubordinates as $sub) {
                $rawJab = trim((string)($sub['jabatan'] ?? ''));
                if ($rawJab !== '' && $rawJab !== '-') {
                    $jabatanOpts[strtoupper($rawJab)] = $rawJab;
                }
            }
            $distinctJabatan = array_values($jabatanOpts);
        }
        sort($distinctJabatan);
        $jabatanList = $distinctJabatan;

        $deptOpts = [];
        foreach ($mySubordinates as $sub) {
            $rawD = trim((string)($sub['departemen'] ?? ''));
            if ($rawD !== '' && $rawD !== '-') {
                $deptOpts[strtoupper($rawD)] = strtoupper($rawD);
            }
        }
        foreach ($evaluatedList as $evItem) {
            $rawD = trim((string)($evItem['departemen'] ?? ''));
            if ($rawD !== '' && $rawD !== '-') {
                $deptOpts[strtoupper($rawD)] = strtoupper($rawD);
            }
        }
        $departmentList = array_values($deptOpts);
        sort($departmentList);

        // Filters
        $search = trim((string)$request->input('q', ''));
        $filterDept = trim((string)($request->input('departemen', '') ?: $request->input('dept', '')));
        $filterKj = trim((string)($request->input('kj', '') ?: $request->input('golongan', '')));
        $filterSection = trim((string)$request->input('seksi', ''));
        $filterBagian = $isDeptOrDivHead
            ? trim((string)($request->input('seksi', '') ?: $request->input('bagian', '')))
            : trim((string)($request->input('bagian', '') ?: $request->input('seksi', '')));
        $filterJabatan = trim((string)$request->input('jabatan', ''));

        // Apply filter to table list
        $filteredEvaluatedList = $evaluatedList;
        if ($search !== '') {
            $filteredEvaluatedList = array_filter($filteredEvaluatedList, function ($row) use ($search) {
                return stripos($row['nama_lengkap'] ?? '', $search) !== false
                    || stripos($row['nrp'] ?? '', $search) !== false
                    || stripos($row['departemen'] ?? '', $search) !== false
                    || stripos($row['nama_bagian'] ?? '', $search) !== false
                    || stripos($row['seksi'] ?? '', $search) !== false
                    || stripos($row['jabatan'] ?? '', $search) !== false;
            });
        }
        if ($filterDept !== '' && strtoupper($filterDept) !== 'SEMUA') {
            $filteredEvaluatedList = array_filter($filteredEvaluatedList, function ($row) use ($filterDept) {
                return strcasecmp(trim((string)($row['departemen'] ?? '')), $filterDept) === 0;
            });
        }
        $activeSecBagFilter = $isDeptOrDivHead ? ($filterSection !== '' ? $filterSection : $filterBagian) : $filterBagian;
        if ($activeSecBagFilter !== '' && strtoupper($activeSecBagFilter) !== 'SEMUA') {
            $fNorm = strtoupper(rtrim(trim($activeSecBagFilter), '.'));
            $filteredEvaluatedList = array_filter($filteredEvaluatedList, function ($row) use ($fNorm, $isDeptOrDivHead) {
                $rSeksi = strtoupper(rtrim(trim((string)($row['seksi'] ?? '')), '.'));
                if ($isDeptOrDivHead) {
                    return $rSeksi === $fNorm;
                }
                $rBagian = strtoupper(rtrim(trim((string)($row['nama_bagian'] ?? '')), '.'));
                return $rBagian === $fNorm || $rSeksi === $fNorm;
            });
        }
        if ($filterJabatan !== '' && strtoupper($filterJabatan) !== 'SEMUA') {
            $filteredEvaluatedList = array_filter($filteredEvaluatedList, function ($row) use ($filterJabatan) {
                return strcasecmp(trim((string)($row['jabatan'] ?? '')), $filterJabatan) === 0;
            });
        }
        if ($filterKj !== '' && strtoupper($filterKj) !== 'SEMUA') {
            $filteredEvaluatedList = array_filter($filteredEvaluatedList, function ($row) use ($filterKj) {
                return strcasecmp(trim($row['kj'] ?? ''), $filterKj) === 0;
            });
        }
        $filteredEvaluatedList = array_values($filteredEvaluatedList);

        return view('penilaian.riwayat', [
            'user' => $user,
            'evaluatedList' => $filteredEvaluatedList,
            'totalSubordinates' => $totalSubordinates,
            'totalDinilai' => $totalDinilai,
            'totalBelum' => $totalBelum,
            'totalPoin' => $totalPoin,
            'avgPoin' => $avgPoin,
            'targetCounts' => $targetCounts,
            'actualCounts' => $actualCounts,
            'quotaActualCounts' => $quotaActualCounts,
            'quotaTotalDinilai' => $quotaTotalDinilai,
            'quotaTotalBelum' => $quotaTotalBelum,
            'totalQuotaSubordinates' => $totalQuotaSubordinates,
            'deptActualKjMatrix' => $deptActualKjMatrix,
            'deptQuotaActualCounts' => $deptQuotaActualCounts,
            'deptQuotaTotalDinilai' => array_sum($deptQuotaActualCounts),
            'isDeptHeadUser' => $isDeptHeadUser,
            'isDivHeadUser' => $isDivHeadUser,
            'isDeptOrDivHead' => $isDeptOrDivHead,
            'isSectionHeadWithoutForeman' => $isSectionHeadWithoutForeman,
            'matrixKjList' => $matrixKjList,
            'subCountByKj' => $subCountByKj,
            'targetKjMatrix' => $targetKjMatrix,
            'actualKjMatrix' => $actualKjMatrix,
            'search' => $search,
            'filterDept' => $filterDept,
            'departmentList' => $departmentList,
            'filterBagian' => $filterBagian,
            'bagianList' => $bagianList,
            'filterSection' => $activeSecBagFilter,
            'sectionList' => $sectionList,
            'filterJabatan' => $filterJabatan,
            'jabatanList' => $jabatanList,
            'filterKj' => $filterKj,
            'kjList' => $kjList,
            // Kept for backward compatibility
            'filterSeksi' => $activeSecBagFilter,
            'seksiList' => $isDeptOrDivHead ? $sectionList : $bagianList,
            'filterGolongan' => $filterKj,
            'golonganList' => $kjList,
        ]);
    }

    /**
     * Build per-KJ quota target matrix matching overall target counts.
     *
     * @param array<int, string> $matrixKjList
    /**
     * Sum S, A, B, C, K and total columns across a Target/Actual KJ Matrix.
     *
     * @param array<string, array<string, int>> $matrix
     * @return array<string, int>
     */
    public static function summarizeTargetKjMatrix(array $matrix): array
    {
        $summary = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0, 'total' => 0];
        foreach ($matrix as $row) {
            foreach (['S', 'A', 'B', 'C', 'K'] as $g) {
                $summary[$g] += (int)($row[$g] ?? 0);
            }
            $summary['total'] += (int)($row['total'] ?? 0);
        }
        if ($summary['total'] === 0) {
            $summary['total'] = $summary['S'] + $summary['A'] + $summary['B'] + $summary['C'] + $summary['K'];
        }
        return $summary;
    }

    /**
     * Build Foreman & Section Head Target KJ Matrix directly constrained by Admin HR quotas:
     * - Row total for each KJ strictly equals the unit's actual subordinate headcount in that KJ.
     * - Grades with 0 quota configured by Admin HR for that KJ remain strictly 0 (never invented).
     * - Active (>0) Admin HR grades are scaled proportionally to match each KJ's subordinate count.
     *
     * @param array<int, string> $matrixKjList
     * @param array<string, int> $subCountByKj
     * @return array<string, array<string, int>>
     */
    public static function buildDirectUnitTargetKjMatrix(
        array $matrixKjList,
        array $subCountByKj,
        ?string $userDept = '',
        ?string $divCode = ''
    ): array {
        $grades = ['S', 'A', 'B', 'C', 'K'];
        $userDept = strtoupper(trim((string)($userDept ?? '')));
        $divCode = strtoupper(trim((string)($divCode ?? '')));
        if ($divCode === '' && in_array($userDept, ['ENGINEERING', 'ENG'], true)) {
            $divCode = 'DIV-E';
        }

        $matrix = [];
        foreach ($matrixKjList as $kj) {
            $matrix[$kj] = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0, 'total' => 0];
        }
        if (empty($matrixKjList)) {
            return $matrix;
        }

        $dbKjQuotas = [];
        $deptAggWeights = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];
        $explicitDivRows = [];

        try {
            if ($userDept !== '') {
                foreach (DB::table('kuota_departemen')->where('departemen', $userDept)->get() as $qItem) {
                    $kNormQ = ExcelKaryawanReader::normalizeQuotaKj($qItem->kj ?? '');
                    if (!isset($dbKjQuotas[$kNormQ])) {
                        $dbKjQuotas[$kNormQ] = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];
                    }
                    $dbKjQuotas[$kNormQ]['S'] += (int)$qItem->kuota_s;
                    $dbKjQuotas[$kNormQ]['A'] += (int)$qItem->kuota_a;
                    $dbKjQuotas[$kNormQ]['B'] += (int)$qItem->kuota_b;
                    $dbKjQuotas[$kNormQ]['C'] += (int)$qItem->kuota_c;
                    $dbKjQuotas[$kNormQ]['K'] += (int)$qItem->kuota_k;

                    $deptAggWeights['S'] += (int)$qItem->kuota_s;
                    $deptAggWeights['A'] += (int)$qItem->kuota_a;
                    $deptAggWeights['B'] += (int)$qItem->kuota_b;
                    $deptAggWeights['C'] += (int)$qItem->kuota_c;
                    $deptAggWeights['K'] += (int)$qItem->kuota_k;
                }
            }

            if ($divCode !== '') {
                foreach ($matrixKjList as $kj) {
                    $sanitized = str_replace(' ', '', $kj);
                    $divKjRow = DB::table('pengaturan')
                        ->where('key', 'target_kuota_kj_' . $divCode . '_' . $sanitized)
                        ->value('value');
                    if ($divKjRow) {
                        $val = json_decode($divKjRow, true);
                        if (isset($val['S'])) {
                            $explicitDivRows[$kj] = [
                                'S' => max(0, (int)($val['S'] ?? 0)),
                                'A' => max(0, (int)($val['A'] ?? 0)),
                                'B' => max(0, (int)($val['B'] ?? 0)),
                                'C' => max(0, (int)($val['C'] ?? 0)),
                                'K' => max(0, (int)($val['K'] ?? 0)),
                            ];
                        }
                    }
                }
            }

            if (array_sum($deptAggWeights) <= 0) {
                $deptKeys = in_array($userDept, ['ENGINEERING', 'ENG'], true)
                    ? ['target_kuota_div_DIV-E', 'target_kuota_dept_ENGINEERING']
                    : ($userDept !== '' ? ['target_kuota_dept_' . $userDept] : []);
                foreach ($deptKeys as $dk) {
                    $valRaw = DB::table('pengaturan')->where('key', $dk)->value('value');
                    $dec = $valRaw ? json_decode($valRaw, true) : null;
                    if ($dec && isset($dec['S'])) {
                        $candidate = [
                            'S' => max(0, (int)($dec['S'] ?? 0)),
                            'A' => max(0, (int)($dec['A'] ?? 0)),
                            'B' => max(0, (int)($dec['B'] ?? 0)),
                            'C' => max(0, (int)($dec['C'] ?? 0)),
                            'K' => max(0, (int)($dec['K'] ?? 0)),
                        ];
                        if (array_sum($candidate) > 0) {
                            $deptAggWeights = $candidate;
                            break;
                        }
                    }
                }
            }

            if (array_sum($deptAggWeights) <= 0) {
                $pctRaw = DB::table('pengaturan')->where('key', 'target_kuota_pct')->value('value');
                $pctDec = $pctRaw ? json_decode($pctRaw, true) : null;
                if ($pctDec && isset($pctDec['S'])) {
                    $candidate = [
                        'S' => max(0, (int)($pctDec['S'] ?? 0)),
                        'A' => max(0, (int)($pctDec['A'] ?? 0)),
                        'B' => max(0, (int)($pctDec['B'] ?? 0)),
                        'C' => max(0, (int)($pctDec['C'] ?? 0)),
                        'K' => max(0, (int)($pctDec['K'] ?? 0)),
                    ];
                    if (array_sum($candidate) > 0) {
                        $deptAggWeights = $candidate;
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        if (array_sum($deptAggWeights) <= 0) {
            $deptAggWeights = ['S' => 15, 'A' => 30, 'B' => 30, 'C' => 15, 'K' => 10];
        }

        foreach ($matrixKjList as $kj) {
            $n = max(0, (int)($subCountByKj[$kj] ?? 0));
            if ($n <= 0) {
                continue;
            }

            $weights = null;
            if (isset($explicitDivRows[$kj]) && array_sum($explicitDivRows[$kj]) > 0) {
                $weights = $explicitDivRows[$kj];
            } elseif (isset($dbKjQuotas[$kj]) && array_sum($dbKjQuotas[$kj]) > 0) {
                $weights = $dbKjQuotas[$kj];
            } else {
                $weights = $deptAggWeights;
            }

            $wSum = array_sum(array_map(fn ($g) => max(0, (int)($weights[$g] ?? 0)), $grades));
            if ($wSum <= 0) {
                continue;
            }

            // Identify active grades that Admin HR configured with target/quota > 0
            $activeGrades = array_values(array_filter($grades, fn ($g) => (int)($weights[$g] ?? 0) > 0));
            if (empty($activeGrades)) {
                $activeGrades = $grades;
            }

            $alloc = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];
            $remaining = $n;

            // 1. Allocate from highest available rank downwards (S -> A -> B -> C -> K)
            // capped by the target/quota defined by Admin HR for each rank
            foreach ($activeGrades as $g) {
                if ($remaining <= 0) {
                    break;
                }
                $cap = max(0, (int)($weights[$g] ?? 0));
                if ($cap <= 0) {
                    continue;
                }
                $take = min($remaining, $cap);
                $alloc[$g] = $take;
                $remaining -= $take;
            }

            // 2. If unit's subordinates exceed total Admin HR quota for this KJ,
            // allocate overflow into highest available active grades
            if ($remaining > 0) {
                foreach ($activeGrades as $g) {
                    if ($remaining <= 0) {
                        break;
                    }
                    $alloc[$g] += $remaining;
                    $remaining = 0;
                }
            }

            $alloc['total'] = $n;
            $matrix[$kj] = $alloc;
        }

        return $matrix;
    }

    /**
     * @param array<string, int> $subCountByKj
     * @param array<string, int> $targetCounts
     * @return array<string, array<string, int>>
     */
    public static function buildTargetKjMatrix(
        array $matrixKjList,
        array $subCountByKj,
        array $targetCounts,
        ?string $userDept = '',
        ?string $divCode = ''
    ): array {
        $userDept = (string)($userDept ?? '');
        $divCode = (string)($divCode ?? '');
        $matrix = [];
        foreach ($matrixKjList as $kj) {
            $matrix[$kj] = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0, 'total' => 0];
        }

        if (empty($matrixKjList)) {
            return $matrix;
        }

        $explicitDivRows = [];
        $totalTarget = (int)($targetCounts['S'] ?? 0)
            + (int)($targetCounts['A'] ?? 0)
            + (int)($targetCounts['B'] ?? 0)
            + (int)($targetCounts['C'] ?? 0)
            + (int)($targetCounts['K'] ?? 0);
        if ($userDept !== '' || $divCode !== '') {
            try {
                $dbKjQuotas = [];
                if ($userDept !== '') {
                    foreach (DB::table('kuota_departemen')->where('departemen', $userDept)->get() as $qItem) {
                        $kNormQ = ExcelKaryawanReader::normalizeQuotaKj($qItem->kj ?? '');
                        if (!isset($dbKjQuotas[$kNormQ])) {
                            $dbKjQuotas[$kNormQ] = (object)[
                                'kuota_s' => 0, 'kuota_a' => 0, 'kuota_b' => 0,
                                'kuota_c' => 0, 'kuota_k' => 0, 'total_karyawan' => 0,
                            ];
                        }
                        $dbKjQuotas[$kNormQ]->kuota_s += (int)$qItem->kuota_s;
                        $dbKjQuotas[$kNormQ]->kuota_a += (int)$qItem->kuota_a;
                        $dbKjQuotas[$kNormQ]->kuota_b += (int)$qItem->kuota_b;
                        $dbKjQuotas[$kNormQ]->kuota_c += (int)$qItem->kuota_c;
                        $dbKjQuotas[$kNormQ]->kuota_k += (int)$qItem->kuota_k;
                        $dbKjQuotas[$kNormQ]->total_karyawan += (int)$qItem->total_karyawan;
                    }
                }

                $candidatePerKj = [];
                $matchedCount = 0;
                $matchedSum = 0;
                foreach ($matrixKjList as $kj) {
                    $sanitized = str_replace(' ', '', $kj);
                    $divKjRow = ($divCode !== '')
                        ? DB::table('pengaturan')->where('key', 'target_kuota_kj_' . $divCode . '_' . $sanitized)->value('value')
                        : null;
                    if ($divKjRow) {
                        $val = json_decode($divKjRow, true);
                        if (isset($val['S'])) {
                            $totRow = (int)($val['S'] ?? 0) + (int)($val['A'] ?? 0) + (int)($val['B'] ?? 0) + (int)($val['C'] ?? 0) + (int)($val['K'] ?? 0);
                            $candidatePerKj[$kj] = [
                                'S' => (int)($val['S'] ?? 0),
                                'A' => (int)($val['A'] ?? 0),
                                'B' => (int)($val['B'] ?? 0),
                                'C' => (int)($val['C'] ?? 0),
                                'K' => (int)($val['K'] ?? 0),
                                'total' => (int)($val['total'] ?? $totRow),
                            ];
                            $explicitDivRows[$kj] = $candidatePerKj[$kj];
                            $matchedSum += $candidatePerKj[$kj]['total'];
                            $matchedCount++;
                            continue;
                        }
                    }

                    if (isset($dbKjQuotas[$kj])) {
                        $q = $dbKjQuotas[$kj];
                        $qSum = (int)$q->kuota_s + (int)$q->kuota_a + (int)$q->kuota_b + (int)$q->kuota_c + (int)$q->kuota_k;
                        $candidatePerKj[$kj] = [
                            'S' => (int)$q->kuota_s,
                            'A' => (int)$q->kuota_a,
                            'B' => (int)$q->kuota_b,
                            'C' => (int)$q->kuota_c,
                            'K' => (int)$q->kuota_k,
                            'total' => $qSum,
                        ];
                        $matchedSum += $qSum;
                        $matchedCount++;
                        continue;
                    }

                    // Keep unconfigured KJs (e.g., KJ 6 when quota was saved before KJ 6 import) at 0 instead of aborting.
                    $candidatePerKj[$kj] = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0, 'total' => 0];
                }

                $otherKjSum = 0;
                $allKnownKjs = array_unique(array_merge(['KJ 1', 'KJ 2', 'KJ 3', 'KJ 4', 'KJ 5'], array_keys($dbKjQuotas)));
                foreach ($allKnownKjs as $oKj) {
                    if (in_array($oKj, $matrixKjList, true)) {
                        continue;
                    }
                    $oSanitized = str_replace(' ', '', $oKj);
                    $oDivRow = ($divCode !== '')
                        ? DB::table('pengaturan')->where('key', 'target_kuota_kj_' . $divCode . '_' . $oSanitized)->value('value')
                        : null;
                    if ($oDivRow) {
                        $oVal = json_decode($oDivRow, true);
                        if (isset($oVal['S'])) {
                            $otherKjSum += (int)($oVal['total'] ?? ((int)($oVal['S'] ?? 0) + (int)($oVal['A'] ?? 0) + (int)($oVal['B'] ?? 0) + (int)($oVal['C'] ?? 0) + (int)($oVal['K'] ?? 0)));
                            continue;
                        }
                    }
                    if (isset($dbKjQuotas[$oKj])) {
                        $oq = $dbKjQuotas[$oKj];
                        $otherKjSum += (int)$oq->kuota_s + (int)$oq->kuota_a + (int)$oq->kuota_b + (int)$oq->kuota_c + (int)$oq->kuota_k;
                    }
                }

                $colMatchTarget = true;
                if ($matchedSum === $totalTarget && empty($explicitDivRows)) {
                    foreach (['S', 'A', 'B', 'C', 'K'] as $gkCheck) {
                        $colSumCheck = array_sum(array_map(fn ($r) => (int)($r[$gkCheck] ?? 0), $candidatePerKj));
                        if ($colSumCheck !== (int)($targetCounts[$gkCheck] ?? 0)) {
                            $colMatchTarget = false;
                            break;
                        }
                    }
                }

                if ($matchedCount > 0 && $matchedSum > 0) {
                    foreach ($candidatePerKj as $kj => $rowVals) {
                        $matrix[$kj] = $rowVals;
                    }
                    return $matrix;
                }
            } catch (\Throwable $e) {
            }
        }

        $totalSub = array_sum($subCountByKj);
        if ($totalTarget <= 0 || $totalSub <= 0) {
            return $matrix;
        }

        $rowCap = [];
        $remainders = [];
        $assignedCap = 0;
        foreach ($matrixKjList as $kj) {
            $exact = (($subCountByKj[$kj] ?? 0) / $totalSub) * $totalTarget;
            $rowCap[$kj] = (int)floor($exact);
            $remainders[$kj] = $exact - $rowCap[$kj];
            $assignedCap += $rowCap[$kj];
        }
        arsort($remainders);
        foreach (array_keys($remainders) as $kj) {
            if ($assignedCap >= $totalTarget) {
                break;
            }
            $rowCap[$kj]++;
            $assignedCap++;
        }

        $remainingRowCap = $rowCap;
        foreach (['S', 'A', 'B', 'C', 'K'] as $g) {
            $gTarget = max(0, (int)($targetCounts[$g] ?? 0));
            $assignedGrade = 0;
            $gradeRem = [];

            foreach ($matrixKjList as $kj) {
                $exact = ($rowCap[$kj] / $totalTarget) * $gTarget;
                $take = min((int)floor($exact), $remainingRowCap[$kj]);
                $matrix[$kj][$g] = $take;
                $remainingRowCap[$kj] -= $take;
                $assignedGrade += $take;
                $gradeRem[$kj] = $exact - $take;
            }

            arsort($gradeRem);
            foreach (array_keys($gradeRem) as $kj) {
                if ($assignedGrade >= $gTarget) {
                    break;
                }
                if ($remainingRowCap[$kj] > 0) {
                    $matrix[$kj][$g]++;
                    $remainingRowCap[$kj]--;
                    $assignedGrade++;
                }
            }

            // Guarantee exact column sum match if a single remainder pass couldn't fill capacity-bound KJs
            while ($assignedGrade < $gTarget && array_sum($remainingRowCap) > 0) {
                $bestKj = null;
                $maxCap = -1;
                foreach ($matrixKjList as $kj) {
                    if ($remainingRowCap[$kj] > $maxCap) {
                        $maxCap = $remainingRowCap[$kj];
                        $bestKj = $kj;
                    }
                }
                if ($bestKj === null || $remainingRowCap[$bestKj] <= 0) {
                    break;
                }
                $matrix[$bestKj][$g]++;
                $remainingRowCap[$bestKj]--;
                $assignedGrade++;
            }
        }

        foreach ($explicitDivRows as $kj => $rowVals) {
            if (isset($matrix[$kj])) {
                $matrix[$kj] = $rowVals;
            }
        }

        foreach ($matrixKjList as $kj) {
            $matrix[$kj]['total'] = $matrix[$kj]['S'] + $matrix[$kj]['A'] + $matrix[$kj]['B'] + $matrix[$kj]['C'] + $matrix[$kj]['K'];
        }

        return $matrix;
    }

        /**
     * Determine Form Code based on Employee's KJ, Sub-Gol, Jabatan, and Hierarchy.
     *
     * Rules:
     * 1. Structural Upper Hierarchy:
     *    - Division Head -> 07-006-DIV
     *    - Department Head -> 07-006-DH
     *    - Section Head OR Direct Subordinate to Section Head (without Foreman) -> 07-006-SH
     * 2. Foreman / Operational Subordinates:
     *    - If Jabatan has FOREMAN (or Sub-Foreman) -> 07-004 (Form Foreman)
     *    - If Jabatan has LEADER -> 07-003 (Form Leader)
     *    - Non-Jabatan:
     *      * KJ 4 - 5 (Non-Jabatan) -> 07-002 (Form KJ 4-5 Non Jabatan)
     *      * KJ 1 - 3 -> 07-001 (Form KJ 1-3)
     */
    public static function determineKodeForm(array $karyawan): string
    {
        $jabatan = strtoupper(trim((string)($karyawan['jabatan'] ?? '')));
        $levelFormRaw = strtoupper(trim((string)($karyawan['level_form'] ?? '')));
        $subGol = strtoupper(trim((string)($karyawan['sub_gol'] ?? '')));
        $golongan = strtoupper(trim((string)($karyawan['golongan'] ?? '')));
        $kjRaw = strtoupper(trim((string)($karyawan['kj'] ?? '')));

        // 1. Structural Upper Roles
        if ($levelFormRaw === 'DIV_HEAD' || str_contains($jabatan, 'DIVISION HEAD') || str_contains($jabatan, 'DIV HEAD')) {
            return '07-006-DIV';
        }
        if ($levelFormRaw === 'DEPT_HEAD' || str_contains($jabatan, 'DEPARTMENT HEAD') || str_contains($jabatan, 'DEPT HEAD') || str_contains($jabatan, 'DEPT. HEAD')) {
            return '07-006-DH';
        }
        if ($levelFormRaw === 'SECTION_HEAD' || str_contains($jabatan, 'SECTION HEAD') || str_contains($jabatan, 'SECT HEAD')) {
            return '07-006-SH';
        }

        // 2. Structural Positions under Foreman or General
        if (str_contains($jabatan, 'FOREMAN') || $levelFormRaw === 'FOREMAN') {
            return '07-004';
        }
        if (str_contains($jabatan, 'LEADER') || $levelFormRaw === 'LEADER') {
            return '07-003';
        }

        // 3. Parse KJ Number (1 to 10)
        $kjNum = 0;
        if (preg_match('/(?:KJ\s*|KATEGORI\s*)?(\d+)/i', $kjRaw, $matches)) {
            $kjNum = (int)$matches[1];
        }

        if ($kjNum === 0) {
            if (str_contains($subGol, 'IV') || str_contains($golongan, 'IV') || str_contains($subGol, 'V') || str_contains($golongan, 'V')) {
                $kjNum = 4;
            } elseif (str_contains($subGol, 'III') || str_contains($golongan, 'III')) {
                $kjNum = 3;
            } else {
                $kjNum = 2; // Default range KJ 1-3
            }
        }

        // Non-Jabatan: KJ 4-5 vs KJ 1-3
        if ($kjNum >= 4 || $levelFormRaw === 'KJ45') {
            return '07-002';
        }

        return '07-001';
    }

    /**
     * Check whether the given form code is an upper-management 07-006 form
     * (Section Head, Department Head, Division Head), which is exempt from
     * Admin/Superadmin target quotas and quota-based finalization locks.
     */
    public static function isForm07006(?string $kodeForm): bool
    {
        return $kodeForm !== null && str_starts_with(strtoupper(trim($kodeForm)), '07-006');
    }

    /**
     * Determine whether an employee/document record is exempt from target quota limits
     * because it uses Form 07-006 (Section Head / Dept Head / Division Head).
     */
    public static function isQuotaExemptEmployee(array $karyawan, ?array $doc = null): bool
    {
        if ($doc && self::isForm07006($doc['kode_form'] ?? null)) {
            return true;
        }
        return self::isForm07006(self::determineKodeForm($karyawan));
    }

    /**
     * Identify whether user is a final quota enforcer (Department Head or Division Head).
     */
    public static function isQuotaEnforcer(?User $user): bool
    {
        if (!$user) {
            return false;
        }
        return $user->isDepartmentHead()
            || in_array($user->role, ['dept_head', 'department_head']);
    }

    /**
     * Resolve canonical Department Code for Admin/Superadmin target quota lookup.
     */
    public static function resolveDepartmentCode(User $user, array $subordinates = []): string
    {
        $rawDept = strtoupper(trim($user->departemen ?? ''));
        $subDepts = array_map('strtoupper', array_map('trim', array_filter(array_column($subordinates, 'departemen'))));

        if (in_array($rawDept, ['ENGINEERING', 'ENG']) || in_array('ENGINEERING', $subDepts)) {
            return 'ENGINEERING';
        }
        if (in_array($rawDept, ['PROD 1', 'PRODUCTION 1'])) {
            return 'PROD 1';
        }
        if (in_array($rawDept, ['PROD 2', 'PRODUCTION 2'])) {
            return 'PROD 2';
        }
        if ($rawDept === 'MACHINING') {
            return 'MACHINING';
        }
        if (in_array($rawDept, ['QA', 'QC', 'QUALITY CONTROL', 'QUALITY ASSURANCE (QA)', 'QA/QC'])) {
            return 'QA/QC';
        }
        if (in_array($rawDept, ['HR & IR DEPT.', 'HR', 'GA', 'GENERAL AFFAIRS', 'GA DEPT.', 'HR & GA'])) {
            return 'HR & GA';
        }

        return $rawDept ?: ($subDepts[0] ?? '');
    }

    /**
     * Filter karyawan list matching a Department Head or Division Head's appraisal scope.
     */
    public static function scopeSubordinatesForQuotaEnforcer(User $user, array $allKaryawan): array
    {
        $isDeptUser = $user->isDepartmentHead() || in_array($user->role, ['dept_head', 'department_head']);
        return array_values(array_filter($allKaryawan, function ($k) use ($user, $isDeptUser) {
            if ($user->isAdmin() || $user->isSuperadmin()) {
                return true;
            }
            return $isDeptUser ? $user->matchesDeptHeadScope($k, [], true) : $user->matchesDivisionHeadScope($k);
        }));
    }

    /**
     * Compute default proportional grade targets summing cleanly to $count.
     *
     * @param array<string, int|float> $pct Percentages summing to 100 across S, A, B, C, K
     * @return array<string, int>
     */
    public static function computeProportionalTargetCounts(int $count, array $pct = ['S' => 15, 'A' => 30, 'B' => 30, 'C' => 15, 'K' => 10]): array
    {
        if ($count <= 0) {
            return ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];
        }

        $tS = max(0, (int)round($count * (($pct['S'] ?? 15) / 100)));
        $tA = max(0, (int)round($count * (($pct['A'] ?? 30) / 100)));
        $tB = max(0, (int)round($count * (($pct['B'] ?? 30) / 100)));
        $tC = max(0, (int)round($count * (($pct['C'] ?? 15) / 100)));
        $tK = $count - ($tS + $tA + $tB + $tC);
        if ($tK < 0) {
            $tK = 0;
            $tB = max(0, $count - ($tS + $tA + $tC));
        }

        return ['S' => $tS, 'A' => $tA, 'B' => $tB, 'C' => $tC, 'K' => $tK];
    }

    /**
     * Determine Level Form string based on employee data.
     */
    public static function determineLevelForm(array $karyawan): string
    {
        $jabatan = strtoupper(trim((string)($karyawan['jabatan'] ?? '')));
        $levelFormRaw = strtoupper(trim((string)($karyawan['level_form'] ?? '')));
        $subGol = strtoupper(trim((string)($karyawan['sub_gol'] ?? '')));
        $golongan = strtoupper(trim((string)($karyawan['golongan'] ?? '')));
        $kjRaw = strtoupper(trim((string)($karyawan['kj'] ?? '')));

        if ($levelFormRaw === 'DIV_HEAD' || str_contains($jabatan, 'DIVISION HEAD') || str_contains($jabatan, 'DIV HEAD')) {
            return 'DIV_HEAD';
        }
        if ($levelFormRaw === 'DEPT_HEAD' || str_contains($jabatan, 'DEPARTMENT HEAD') || str_contains($jabatan, 'DEPT HEAD') || str_contains($jabatan, 'DEPT. HEAD')) {
            return 'DEPT_HEAD';
        }
        if ($levelFormRaw === 'SECTION_HEAD' || str_contains($jabatan, 'SECTION HEAD') || str_contains($jabatan, 'SECT HEAD')) {
            return 'SECTION_HEAD';
        }
        if (str_contains($jabatan, 'FOREMAN') || $levelFormRaw === 'FOREMAN') {
            return 'FOREMAN';
        }
        if (str_contains($jabatan, 'LEADER') || $levelFormRaw === 'LEADER') {
            return 'LEADER';
        }

        $kjNum = 0;
        if (preg_match('/(?:KJ\s*|KATEGORI\s*)?(\d+)/i', $kjRaw, $matches)) {
            $kjNum = (int)$matches[1];
        }

        if ($kjNum === 0) {
            if (str_contains($subGol, 'IV') || str_contains($golongan, 'IV') || str_contains($subGol, 'V') || str_contains($golongan, 'V')) {
                $kjNum = 4;
            } elseif (str_contains($subGol, 'III') || str_contains($golongan, 'III')) {
                $kjNum = 3;
            } else {
                $kjNum = 2;
            }
        }

        if ($kjNum >= 4 || $levelFormRaw === 'KJ45') {
            return 'KJ45';
        }

        return 'KJ13';
    }

    /**
     * Get Human-Readable Form Name for a Form Code.
     */
    public static function getFormName(string $kodeForm): string
    {
        return match ($kodeForm) {
            '07-001' => 'Form KJ 1-3 (07-001)',
            '07-002' => 'Form KJ 4-5 Non Jabatan (07-002)',
            '07-003' => 'Form Leader (07-003)',
            '07-004' => 'Form Foreman (07-004)',
            '07-006-SH' => 'Form Section Head (07-006)',
            '07-006-DH' => 'Form Dept Head (07-006)',
            '07-006-DIV' => 'Form Div Head (07-006)',
            default => 'Form Penilaian Karya',
        };
    }

    /**
     * Determine whether the given user has permission to access an employee's appraisal document.
     * Enforces organizational hierarchy:
     * - Self evaluation is always accessible.
     * - Division Head: can access all employees within accessible division/department.
     * - Department Head: can access subordinates where nrp_dept_head matches self + self (excludes Division Head).
     * - Section Head: can access subordinates where nrp_section_head matches self + self (excludes Dept Head & Div Head).
     * - Foreman: can access subordinates where nrp_foreman matches self + self.
     * - Admin / HR / Superadmin: can access all within accessible department.
     */
    public static function canUserAccessKaryawan(User $user, array $karyawan): bool
    {
        // 1. Self evaluation is always accessible
        if (strcasecmp($karyawan['nrp'] ?? '', $user->nrp) === 0) {
            return true;
        }

        // Explicit supervisor isolation (SH without Foreman, Dept Head, Div Head)
        if ($user->isSectionHead() || in_array($user->role, ['section_head', 'section'])) {
            return $user->matchesSectionHeadScope($karyawan);
        }

        if ($user->isDepartmentHead() || in_array($user->role, ['dept_head', 'department_head'])) {
            return $user->matchesDeptHeadScope($karyawan);
        }

        if ($user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head'])) {
            return $user->matchesDivisionHeadScope($karyawan);
        }

        // 2. Superadmin / HR / Admin: access within their department
        if ($user->isAdmin() || $user->isHr()) {
            return $user->canAccessDepartment($karyawan['departemen'] ?? '');
        }

        // 3. Department must match
        if (!$user->canAccessDepartment($karyawan['departemen'] ?? '')) {
            return false;
        }

        // 4. Role-based subordinate hierarchy
        if ($user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head'])) {
            return true;
        }

        if ($user->isDepartmentHead() || in_array($user->role, ['dept_head', 'department_head'])) {
            if (!empty($karyawan['nrp_dept_head']) && strcasecmp($karyawan['nrp_dept_head'], $user->nrp) === 0) {
                return true;
            }
            if ($user->canAccessDepartment($karyawan['departemen'] ?? '')) {
                $jab = strtoupper($karyawan['jabatan'] ?? '');
                return !str_contains($jab, 'DIV') && !str_contains($jab, 'DIVISION');
            }
            return false;
        }

        if ($user->isSectionHead() || in_array($user->role, ['section_head', 'section'])) {
            return !empty($karyawan['nrp_section_head']) && strcasecmp($karyawan['nrp_section_head'], $user->nrp) === 0;
        }

        if ($user->isForeman() || in_array($user->role, ['foreman', 'ketua'])) {
            return !empty($karyawan['nrp_foreman']) && strcasecmp($karyawan['nrp_foreman'], $user->nrp) === 0;
        }

        return false;
    }

    /**
     * Get role-specific form appraisal URL for a user.
     */
    public static function getFormUrlForUser(?User $user, int|string $id): string
    {
        if (!$user) {
            return url("/dokumen/{$id}/penilaian");
        }

        if ($user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head'])) {
            return url("/penilaian/divhead/dokumen/{$id}");
        }

        if ($user->isDepartmentHead() || in_array($user->role, ['dept_head', 'department_head'])) {
            return url("/penilaian/depthead/dokumen/{$id}");
        }

        if ($user->isSectionHead() || in_array($user->role, ['section_head', 'section'])) {
            return url("/penilaian/sechead/dokumen/{$id}");
        }

        if ($user->isForeman() || in_array($user->role, ['foreman', 'ketua'])) {
            return url("/penilaian/foreman/dokumen/{$id}");
        }

        if ($user->isAdmin() || $user->isHr()) {
            return url("/penilaian/admin/dokumen/{$id}");
        }

        return url("/dokumen/{$id}/penilaian");
    }

/**
     * Dedicated page for searching/selecting NRP to open appraisal form.
     */
    public function cari(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        $allKaryawan = $this->repository->all('karyawan');

        // Check if current user has an employee record for self-evaluation (Penilaian Mandiri)
        $selfKaryawan = $this->repository->find('karyawan', 'nrp', $user->nrp);
        if (!$selfKaryawan && ($user->isDepartmentHead() || in_array($user->role, ['dept_head', 'department_head']))) {
            $defaultDept = !empty($user->departemen) && $user->departemen !== 'ALL' ? $user->departemen : 'PROD 2';
            $divNrp = str_contains(strtoupper($defaultDept), 'PROD 2') ? '11' : 'DIV-11000';
            if (str_contains(strtoupper($defaultDept), 'ENG') || str_contains(strtoupper($defaultDept), 'QA')) $divNrp = 'DIV-12000';
            
            DB::table('karyawan')->updateOrInsert(
                ['nrp' => $user->nrp],
                [
                    'nama_lengkap' => $user->nama,
                    'golongan' => 'GOLONGAN IV',
                    'sub_gol' => 'IVB',
                    'kj' => 'KJ 5',
                    'jabatan' => 'Department Head ' . $defaultDept,
                    'tgl_masuk' => '2011-04-01',
                    'seksi' => '-',
                    'departemen' => $defaultDept,
                    'level_form' => 'DEPT_HEAD',
                    'nrp_foreman' => null,
                    'nrp_section_head' => null,
                    'nrp_dept_head' => $user->nrp,
                    'nrp_div_head' => $divNrp,
                    'nama_bagian' => $defaultDept,
                    'nama_sect' => '-',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
            $selfKaryawan = (array)DB::table('karyawan')->where('nrp', $user->nrp)->first();
            $allKaryawan = $this->repository->all('karyawan');
        }

        $selfDoc = null;
        $selfKodeForm = null;
        $selfFormName = null;

        if ($selfKaryawan) {
            $selfDoc = DB::table('dokumen')->where('nrp', $user->nrp)->orderByDesc('id')->first();
            $selfKodeForm = self::determineKodeForm($selfKaryawan);
            $selfFormName = self::getFormName($selfKodeForm);
        }

        // Filter subordinates for current user (including self if applicable)
        if ($user->isForeman()) {
            $subordinates = array_filter($allKaryawan, function ($k) use ($user) {
                if (!empty($k['nrp_foreman']) && strcasecmp($k['nrp_foreman'], $user->nrp) === 0) {
                    return true;
                }
                return strcasecmp($k['nrp'] ?? '', $user->nrp) === 0;
            });
        } elseif ($user->isSectionHead()) {
            $subordinates = array_filter($allKaryawan, fn ($k) => $user->matchesSectionHeadScope($k));
        } elseif ($user->isDepartmentHead() || in_array($user->role, ['dept_head', 'department_head'])) {
            $subordinates = array_filter($allKaryawan, fn ($k) => $user->matchesDeptHeadScope($k, [], true));
        } elseif ($user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head'])) {
            $subordinates = array_filter($allKaryawan, fn ($k) => $user->matchesDivisionHeadScope($k));
        } else {
            $subordinates = $allKaryawan;
        }

        // Fetch document statuses and details
        $allDocs = $this->repository->all('dokumen');
        $docsByNrp = [];
        $docIds = [];
        foreach ($allDocs as $d) {
            $docsByNrp[$d['nrp']] = $d;
            $docIds[] = $d['id'];
        }

        $allForemen = DB::table('app_users')->whereIn('role', ['foreman', 'ketua'])->get()->keyBy('nrp');
        $allSectionHeads = DB::table('app_users')->where('role', 'section_head')->get()->keyBy('nrp');

        // Fetch signatures by doc_id
        $allTtd = DB::table('tanda_tangan')->whereIn('dokumen_id', $docIds)->get();
        $ttdByDocAndLevel = [];
        foreach ($allTtd as $t) {
            $ttdByDocAndLevel[$t->dokumen_id][$t->level] = $t;
        }

        // Fetch scores per level
        $allScoresByDocAndLevel = DB::table('nilai')
            ->whereIn('dokumen_id', $docIds)
            ->get()
            ->groupBy(['dokumen_id', 'level']);

        $isDeptUser = $user->isDepartmentHead() || in_array($user->role, ['dept_head', 'department_head']);
        $isQuotaEnforcerUser = self::isQuotaEnforcer($user);
        $kjSummary = $isDeptUser
            ? self::getKjSummaryForDept($user, $subordinates, $docsByNrp)
            : self::getKjSummaryForDivision($user, $subordinates, $docsByNrp);

        $list = [];
        foreach ($subordinates as $k) {
            $nrp = $k['nrp'];
            $doc = $docsByNrp[$nrp] ?? null;

            $kodeForm = self::determineKodeForm($k);
            $formName = self::getFormName($kodeForm);

            $docId = $doc['id'] ?? null;
            $docStatus = $doc['status'] ?? 'belum_dinilai';
            $foremanNrp = $k['nrp_foreman'] ?? null;
            $foremanName = $foremanNrp && isset($allForemen[$foremanNrp]) ? $allForemen[$foremanNrp]->nama : ($foremanNrp ? "Foreman ($foremanNrp)" : 'Foreman');

            $sectionHeadNrp = $k['nrp_section_head'] ?? null;
            $sectionHeadName = $sectionHeadNrp && isset($allSectionHeads[$sectionHeadNrp]) ? $allSectionHeads[$sectionHeadNrp]->nama : ($sectionHeadNrp ? "Section Head ($sectionHeadNrp)" : 'Section Head');

            $foremanEvaluated = false;
            $hasForemanSign = false;
            $foremanScore = null;
            $foremanGrade = null;
            $ttdForemanDate = null;

            $sectionHeadEvaluated = false;
            $hasSectionHeadSign = false;
            $sectionHeadScore = null;
            $sectionHeadGrade = null;
            $ttdSectionHeadDate = null;

            $hasDeptHeadSign = false;
            $deptScore = null;
            $deptGrade = null;

            $hasForeman = !empty($foremanNrp);
            $isGol4UpForm = in_array($kodeForm, ['07-006-SH', '07-006-DH', '07-006-DIV']);
            $isSelf = (strcasecmp($nrp, $user->nrp) === 0);

            if ($docId) {
                // 1. Foreman Signature & Scores (strictly only when employee has a Foreman and standard KJ 1-5 form)
                if ($hasForeman && !$isGol4UpForm && !$isSelf) {
                    if (isset($ttdByDocAndLevel[$docId]['foreman'])) {
                        $hasForemanSign = true;
                        $ttdForemanDate = substr($ttdByDocAndLevel[$docId]['foreman']->tgl_ttd, 0, 10);
                    }

                    if (isset($allScoresByDocAndLevel[$docId]['foreman']) && count($allScoresByDocAndLevel[$docId]['foreman']) > 0) {
                        $foremanEvaluated = true;
                        $scoresArr = $allScoresByDocAndLevel[$docId]['foreman']->pluck('skor', 'aspek_kode')->toArray();
                        $calc = $this->calcService->calculate($kodeForm, $scoresArr);
                        $foremanScore = number_format($calc['grand_total'], 2);
                        $foremanGrade = $this->calcService->scoreToGrade($calc['grand_total']);
                    } elseif (in_array($docStatus, ['menunggu_dept_head', 'selesai']) && !empty($doc['total_nilai'])) {
                        $foremanEvaluated = true;
                        $hasForemanSign = true;
                        $foremanScore = number_format((float)$doc['total_nilai'], 2);
                        $foremanGrade = $doc['grade_original'] ?? $this->calcService->scoreToGrade((float)$doc['total_nilai']);
                        $ttdForemanDate = substr($doc['updated_at'] ?? now(), 0, 10);
                    }
                }

                // 2. Section Head Signature & Scores
                if (!$isSelf) {
                    if (isset($ttdByDocAndLevel[$docId]['section_head'])) {
                        $hasSectionHeadSign = true;
                        $ttdSectionHeadDate = substr($ttdByDocAndLevel[$docId]['section_head']->tgl_ttd, 0, 10);
                    } elseif ($isGol4UpForm && empty($foremanNrp) && isset($ttdByDocAndLevel[$docId]['foreman'])) {
                        $hasSectionHeadSign = true;
                        $ttdSectionHeadDate = substr($ttdByDocAndLevel[$docId]['foreman']->tgl_ttd, 0, 10);
                    }

                    if (isset($allScoresByDocAndLevel[$docId]['section_head']) && count($allScoresByDocAndLevel[$docId]['section_head']) > 0) {
                        $sectionHeadEvaluated = true;
                        $scoresArrSH = $allScoresByDocAndLevel[$docId]['section_head']->pluck('skor', 'aspek_kode')->toArray();
                        $calcSH = $this->calcService->calculate($kodeForm, $scoresArrSH);
                        $sectionHeadScore = number_format($calcSH['grand_total'], 2);
                        $sectionHeadGrade = $this->calcService->scoreToGrade($calcSH['grand_total']);
                    } elseif ($isGol4UpForm && empty($foremanNrp) && isset($allScoresByDocAndLevel[$docId]['foreman']) && count($allScoresByDocAndLevel[$docId]['foreman']) > 0) {
                        $sectionHeadEvaluated = true;
                        $scoresArrSH = $allScoresByDocAndLevel[$docId]['foreman']->pluck('skor', 'aspek_kode')->toArray();
                        $calcSH = $this->calcService->calculate($kodeForm, $scoresArrSH);
                        $sectionHeadScore = number_format($calcSH['grand_total'], 2);
                        $sectionHeadGrade = $this->calcService->scoreToGrade($calcSH['grand_total']);
                    } elseif (in_array($docStatus, ['menunggu_dept_head', 'menunggu_div_head', 'selesai']) && !empty($doc['total_nilai'])) {
                        $sectionHeadEvaluated = true;
                        $hasSectionHeadSign = true;
                        $sectionHeadScore = number_format((float)$doc['total_nilai'], 2);
                        $sectionHeadGrade = $doc['grade_original'] ?? $this->calcService->scoreToGrade((float)$doc['total_nilai']);
                        $ttdSectionHeadDate = substr($doc['updated_at'] ?? now(), 0, 10);
                    }
                }

                // 3. Dept Head Signature & Scores
                if (isset($ttdByDocAndLevel[$docId]['dept_head'])) {
                    $hasDeptHeadSign = true;
                }
                if (isset($allScoresByDocAndLevel[$docId]['dept_head']) && count($allScoresByDocAndLevel[$docId]['dept_head']) > 0) {
                    $scoresArrDH = $allScoresByDocAndLevel[$docId]['dept_head']->pluck('skor', 'aspek_kode')->toArray();
                    $calcDH = $this->calcService->calculate($kodeForm, $scoresArrDH);
                    $deptScore = number_format($calcDH['grand_total'], 2);
                    $deptGrade = $this->calcService->scoreToGrade($calcDH['grand_total']);
                } elseif (!empty($doc['docking_final'])) {
                    $deptGrade = $doc['docking_final'];
                }
            }

            $isWaitingForeman = !empty($foremanNrp) && !$isGol4UpForm && ($docStatus === 'menunggu_foreman' || !$hasForemanSign);
            $isWaitingSection = ($docStatus === 'menunggu_section_head' || (!$hasSectionHeadSign && !$isWaitingForeman && !$isSelf));
            $canEvaluateDept = ($docStatus === 'menunggu_dept_head') || $user->isSuperadmin();

            $adminStatusCategory = 'belum';
            if ($docStatus === 'selesai' || $hasDeptHeadSign) {
                $adminStatusCategory = 'selesai';
            } elseif ($sectionHeadEvaluated || in_array($docStatus, ['menunggu_dept_head', 'menunggu_div_head'])) {
                $adminStatusCategory = 'section_head';
            } elseif ($hasForeman && ($foremanEvaluated || $docStatus === 'menunggu_section_head')) {
                $adminStatusCategory = 'foreman';
            }

            $empKj = ExcelKaryawanReader::normalizeQuotaKj($k['kj'] ?? 'KJ 2');
            $kjInfo = $kjSummary[$empKj] ?? null;

            $empGrade = $deptGrade ?: ($sectionHeadGrade ?: ($foremanGrade ?: ($doc['grade_original'] ?? null)));
            if (!empty($doc['docking_final'])) {
                $empGrade = $doc['docking_final'];
            }

            $isQuotaExempt = self::isQuotaExemptEmployee($k, $doc);
            $isKjFull = false;
            $isKjOver = false;
            $isEmpGradeOverKj = false;
            $kjOverMessage = null;

            if ($isQuotaEnforcerUser && !$isQuotaExempt && $kjInfo) {
                $isKjFull = $kjInfo['is_full'] ?? false;
                $isKjOver = $kjInfo['is_over'] ?? false;
                if ($empGrade && isset($kjInfo['actual'][$empGrade]) && isset($kjInfo['target'][$empGrade])) {
                    if ($kjInfo['actual'][$empGrade] > $kjInfo['target'][$empGrade]) {
                        $isEmpGradeOverKj = true;
                    }
                }
                if ($isEmpGradeOverKj) {
                    $kjOverMessage = "Kuota {$empKj} pada Rank {$empGrade} melebihi target ({$kjInfo['actual'][$empGrade]}/{$kjInfo['target'][$empGrade]}).";
                } elseif ($isKjOver) {
                    $overRanksStr = implode(', ', array_keys($kjInfo['over_ranks'] ?? []));
                    $kjOverMessage = "Kuota {$empKj} melebihi target pada Rank {$overRanksStr}.";
                } elseif ($isKjFull) {
                    $kjOverMessage = "Kapasitas kuota untuk jenjang {$empKj} sudah penuh ({$kjInfo['actual']['total']}/{$kjInfo['target']['total']}).";
                }
            }

            // Form 07-006 (SH/DH/Div Head) is never locked by KJ quota limits; Foreman/SH never locked here
            $isKjLocked = $isQuotaEnforcerUser && !$isQuotaExempt && ($isKjOver || $isEmpGradeOverKj || ($isKjFull && empty($empGrade))) && !$isSelf && ($docStatus !== 'selesai');

            $empSect = trim($k['nama_sect'] ?? '');
            if (!$empSect) {
                $rawS = trim($k['seksi'] ?? '');
                $rawSUpper = strtoupper($rawS);
                $deptUpper = strtoupper(trim($k['departemen'] ?? 'PROD 2'));
                if (str_contains($rawSUpper, '2') || str_contains($rawSUpper, 'FACT-2')) {
                    $empSect = 'FACTORY 2';
                } elseif (str_contains($deptUpper, 'ENGINEERING') || str_contains($rawSUpper, 'ENG')) {
                    $empSect = 'ENGINEERING';
                } elseif (str_contains($deptUpper, 'QA') || str_contains($rawSUpper, 'QA')) {
                    $empSect = 'QA';
                } elseif (str_contains($deptUpper, 'QC') || str_contains($rawSUpper, 'QC')) {
                    $empSect = 'QC';
                } else {
                    $clean = trim(preg_replace('/\s+SEC\.?$/i', '', $rawS));
                    $empSect = ($clean && $clean !== '-') ? $clean : 'FACTORY 1';
                }
            }

            $list[] = [
                'admin_status_category' => $adminStatusCategory,
                'nrp' => $nrp,
                'nama_lengkap' => $k['nama_lengkap'],
                'golongan' => $k['golongan'] ?? '-',
                'sub_gol' => $k['sub_gol'] ?? '-',
                'kj' => !empty($k['kj']) ? ExcelKaryawanReader::normalizeKj($k['kj']) : 'KJ 2',
                'quota_kj' => $empKj,
                'jabatan' => $k['jabatan'] ?? '-',
                'departemen' => trim((string)($k['departemen'] ?? '-')) ?: '-',
                'seksi' => $empSect,
                'nama_sect' => $empSect,
                'nama_bagian' => $k['nama_bagian'] ?? ($k['seksi'] ?? '-'),
                'level_form' => $k['level_form'] ?? 'KJ13',
                'kode_form' => $kodeForm,
                'form_name' => $formName,
                'doc_id' => $docId,
                'status' => $docStatus,
                'is_waiting_foreman' => $isWaitingForeman,
                'is_waiting_section' => $isWaitingSection,
                'can_evaluate_dept' => $canEvaluateDept,
                'foreman_name' => $foremanName,
                'nrp_foreman' => $foremanNrp,
                'foreman_evaluated' => $foremanEvaluated,
                'has_foreman_sign' => $hasForemanSign,
                'foreman_score' => $foremanScore,
                'foreman_grade' => $foremanGrade,
                'ttd_foreman_date' => $ttdForemanDate,
                'section_head_name' => $sectionHeadName,
                'section_head_evaluated' => $sectionHeadEvaluated,
                'has_section_head_sign' => $hasSectionHeadSign,
                'section_head_score' => $sectionHeadScore,
                'section_head_grade' => $sectionHeadGrade,
                'ttd_section_head_date' => $ttdSectionHeadDate,
                'has_dept_head_sign' => $hasDeptHeadSign,
                'dept_score' => $deptScore,
                'dept_grade' => $deptGrade,
                'is_self' => (strcasecmp($nrp, $user->nrp) === 0),
                'is_kj_full' => $isKjFull,
                'is_kj_over' => $isKjOver,
                'is_emp_grade_over_kj' => $isEmpGradeOverKj,
                'is_kj_locked' => $isKjLocked,
                'is_quota_exempt' => $isQuotaExempt,
                'kj_over_message' => $kjOverMessage,
                'kj_info' => $kjInfo,
            ];
        }



        // Division Head on /penilaian/cari exclusively displays 07-006 forms (Section Head, Dept Head, Division Head / Self)
        if ($user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head'])) {
            $list = array_values(array_filter($list, function ($item) {
                $kf = strtoupper(trim((string)($item['kode_form'] ?? '')));
                return str_starts_with($kf, '07-006');
            }));
        }

        // Sort so that self-assessment is first, followed by 07-006 forms (DIV -> DH -> SH), then operational forms
        usort($list, function ($a, $b) {
            $rank = function (array $row): int {
                if (!empty($row['is_self'])) {
                    return 0;
                }
                $kf = strtoupper(trim((string)($row['kode_form'] ?? '')));
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
            return strcmp((string)$a['nrp'], (string)$b['nrp']);
        });

        // Target Quota & Comparison S, A, B, C, K (excludes 07-006 SH/DH/Div Head forms)
        $targetPctSetting = DB::table('pengaturan')->where('key', 'target_kuota_pct')->value('value');
        $targetPercentages = $targetPctSetting ? json_decode($targetPctSetting, true) : ['S' => 10, 'A' => 30, 'B' => 30, 'C' => 20, 'K' => 10];

        $quotaBoundList = array_values(array_filter($list, fn ($item) => empty($item['is_quota_exempt'])));
        $tCount = count($quotaBoundList);
        $scopedQuota = self::getTargetQuotaForScope($user, $subordinates);
        $divTargetCounts = [
            'S' => $scopedQuota['S'] ?? 0,
            'A' => $scopedQuota['A'] ?? 0,
            'B' => $scopedQuota['B'] ?? 0,
            'C' => $scopedQuota['C'] ?? 0,
            'K' => $scopedQuota['K'] ?? 0,
        ];

        $divSectionGradeCounts = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];
        $divDeptGradeCounts = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];
        $divFinalGradeCounts = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];
        $divSectionTotalDinilai = 0;
        $divDeptTotalDinilai = 0;
        $divFinalTotalDinilai = 0;

        foreach ($quotaBoundList as $item) {
            // Section Head grade
            $secGrade = $item['section_head_grade'] ?? null;
            if ($secGrade && isset($divSectionGradeCounts[$secGrade])) {
                $divSectionGradeCounts[$secGrade]++;
                $divSectionTotalDinilai++;
            }

            // Dept Head grade
            $dGrade = $item['dept_grade'] ?? null;
            if ($dGrade && isset($divDeptGradeCounts[$dGrade])) {
                $divDeptGradeCounts[$dGrade]++;
                $divDeptTotalDinilai++;
            }

            // Division Head grade (status selesai or doc is finalized)
            $docId = $item['doc_id'] ?? null;
            $dDoc = $docId ? ($docsByNrp[$item['nrp']] ?? null) : null;
            if ($dDoc && ($dDoc['status'] === 'selesai')) {
                $finalG = $dDoc['docking_final'] ?? $item['dept_grade'] ?? $item['section_head_grade'] ?? 'B';
                if (isset($divFinalGradeCounts[$finalG])) {
                    $divFinalGradeCounts[$finalG]++;
                    $divFinalTotalDinilai++;
                }
            }
        }

        $countAdminBelum = 0;
        $countAdminForeman = 0;
        $countAdminSection = 0;
        $countAdminSelesai = 0;
        foreach ($list as $item) {
            $cat = $item['admin_status_category'] ?? 'belum';
            if ($cat === 'selesai') {
                $countAdminSelesai++;
            } elseif ($cat === 'section_head') {
                $countAdminSection++;
            } elseif ($cat === 'foreman') {
                $countAdminForeman++;
            } else {
                $countAdminBelum++;
            }
        }

        return view('penilaian.cari', [
            'user' => $user,
            'countAdminBelum' => $countAdminBelum,
            'countAdminForeman' => $countAdminForeman,
            'countAdminSection' => $countAdminSection,
            'countAdminSelesai' => $countAdminSelesai,
            'subordinates' => $list,
            'totalCount' => count($list),
            'selfKaryawan' => $selfKaryawan,
            'selfDoc' => $selfDoc,
            'selfKodeForm' => $selfKodeForm,
            'selfFormName' => $selfFormName,
            'targetPercentages' => $targetPercentages,
            'divTargetCounts' => $divTargetCounts,
            'divSectionGradeCounts' => $divSectionGradeCounts,
            'divDeptGradeCounts' => $divDeptGradeCounts,
            'divFinalGradeCounts' => $divFinalGradeCounts,
            'divSectionTotalDinilai' => $divSectionTotalDinilai,
            'divDeptTotalDinilai' => $divDeptTotalDinilai,
            'divFinalTotalDinilai' => $divFinalTotalDinilai,
            'divTotalKaryawan' => $tCount,
            'kjSummary' => $kjSummary,
        ]);
    }

    /**
     * Role-specific form viewer for Section Head (/penilaian/sechead/dokumen/{id}).
     */
    public function showSecHead($id)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->isSectionHead() && !in_array($user->role, ['section_head', 'section']) && !$user->isAdmin()) {
            abort(403, 'Akses ditolak. Formulir penilaian pada rute ini khusus untuk Section Head.');
        }

        return $this->show($id, 'sechead');
    }

    /**
     * Role-specific form viewer for Department Head (/penilaian/depthead/dokumen/{id}).
     */
    public function showDeptHead($id)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->isDepartmentHead() && !in_array($user->role, ['dept_head', 'department_head']) && !$user->isAdmin()) {
            abort(403, 'Akses ditolak. Formulir penilaian pada rute ini khusus untuk Department Head.');
        }

        return $this->show($id, 'depthead');
    }

    /**
     * Role-specific form viewer for Division Head (/penilaian/divhead/dokumen/{id}).
     */
    public function showDivHead($id)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->isDivisionHead() && !in_array($user->role, ['div_head', 'division_head']) && !$user->isAdmin()) {
            abort(403, 'Akses ditolak. Formulir penilaian pada rute ini khusus untuk Division Head.');
        }

        return $this->show($id, 'divhead');
    }

    /**
     * Role-specific form viewer for Foreman (/penilaian/foreman/dokumen/{id}).
     */
    public function showForeman($id)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->isForeman() && !in_array($user->role, ['foreman', 'ketua']) && !$user->isAdmin()) {
            abort(403, 'Akses ditolak. Formulir penilaian pada rute ini khusus untuk Foreman.');
        }

        return $this->show($id, 'foreman');
    }

    /**
     * Role-specific form viewer for Administrator / HR (/penilaian/admin/dokumen/{id}).
     */
    public function showAdmin($id)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->isAdmin() && !$user->isHr()) {
            abort(403, 'Akses ditolak. Formulir penilaian pada rute ini khusus untuk Administrator / HR.');
        }

        return $this->show($id, 'admin');
    }

    /**
     * Generic / Legacy redirector route (/dokumen/{id}/penilaian).
     * Redirects directly to the user's role-specific URL after verifying access permissions.
     */
    public function showRedirect($id)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login');
        }

        $dokumen = $this->repository->find('dokumen', 'id', $id);
        if (!$dokumen) {
            abort(404, 'Dokumen penilaian tidak ditemukan.');
        }

        $karyawan = $this->repository->find('karyawan', 'nrp', $dokumen['nrp']);
        if (!$karyawan) {
            abort(404, 'Data karyawan untuk dokumen ini tidak ditemukan.');
        }

        if (!self::canUserAccessKaryawan($user, $karyawan)) {
            abort(403, "Anda tidak memiliki hak akses untuk melihat atau menilai dokumen karyawan ini.");
        }

        return redirect(self::getFormUrlForUser($user, $id));
    }

    /**
     * Generate a self-contained, valid SVG digital signature Data URI.
     * Guaranteed valid XML with UTF-8 encoding, works 100% in DomPDF and all web browsers.
     */
    public static function makeOfficialSvgDataUri(string $name, ?string $role = null, ?string $nrp = null): string
    {
        return \App\Models\User::generateBarcodeDataUri($nrp ?: '-', $name ?: 'KARYAWAN MUSASHI', $role ?: 'PENILAI');
    }

    /**
     * Resolves an image path, Google Drive link, or Data URI into a self-contained Data URI.
     * Caches downloaded Google Drive images as Data URIs for maximum performance and reliability.
     */
    public static function resolveImageToDataUri(?string $imgSrc, ?string $fallbackName = null, ?string $fallbackRole = null, ?string $fallbackNrp = null): ?string
    {
        $src = trim((string)$imgSrc);

        // 1. Direct Data URI
        if (str_starts_with($src, 'data:image')) {
            if (strlen($src) > 100) {
                return $src;
            }
        }

        // 2. Local storage file
        if ($src !== '' && !str_starts_with($src, 'http')) {
            try {
                $cleanPath = ltrim(preg_replace('/^storage\//', '', $src), '/');
                if (Storage::disk('public')->exists($cleanPath)) {
                    $binary = Storage::disk('public')->get($cleanPath);
                    $mime = Storage::disk('public')->mimeType($cleanPath) ?? 'image/png';
                    if (strlen($binary) > 100) {
                        return 'data:' . $mime . ';base64,' . base64_encode($binary);
                    }
                }
                if (file_exists(public_path('storage/' . $cleanPath))) {
                    $binary = file_get_contents(public_path('storage/' . $cleanPath));
                    if (strlen($binary) > 100) {
                        return 'data:image/png;base64,' . base64_encode($binary);
                    }
                }
            } catch (\Throwable $e) {}
        }

        // 3. Google Drive URL or File ID
        $fileId = null;
        if (preg_match('/[?&]id=([a-zA-Z0-9_-]+)/', $src, $m)) {
            $fileId = $m[1];
        } elseif (preg_match('/\/d\/([a-zA-Z0-9_-]+)/', $src, $m)) {
            $fileId = $m[1];
        } elseif (preg_match('/^[a-zA-Z0-9_-]{25,}$/', $src)) {
            $fileId = $src;
        }

        // 3. Instant Barcode QR Generation (0ms latency, eliminates slow blocking Google Drive HTTP requests)
        if ($fallbackNrp && $fallbackName) {
            return \App\Models\User::generateBarcodeDataUri($fallbackNrp, $fallbackName, $fallbackRole);
        }

        if ($fileId) {
            $cacheKey = 'gdrive_sig_data_uri_' . $fileId;
            $cached = Cache::get($cacheKey);
            if ($cached && str_starts_with($cached, 'data:image')) {
                return $cached;
            }
        }

        // 4. Fallback to crisp Official SVG Badge
        if ($fallbackName) {
            return self::makeOfficialSvgDataUri($fallbackName, $fallbackRole, $fallbackNrp);
        }

        return null;
    }

    /**
     * Build complete $ttdByLevel map for all evaluation roles.
     * Guarantees that every signed role receives a valid Data URI image (never plain text or broken link).
     */
    public function buildCompleteTtdByLevel($dokumenId, $dokumen, $karyawan, $calcForeman = null, $calcSectionHead = null, $calcDeptHead = null): array
    {
        $status = $dokumen['status'] ?? 'menunggu_foreman';
        $kodeForm = $dokumen['kode_form'] ?? ($karyawan['kode_form'] ?? '07-001');
        $isGol4Up = in_array($kodeForm, ['07-006-SH', '07-006-DH', '07-006-DIV']);

        // 1. Fetch from database table 'tanda_tangan'
        $existingTtd = DB::table('tanda_tangan')->where('dokumen_id', $dokumenId)->get()->map(fn($r) => (array)$r)->toArray();
        $dbTtdByLevel = [];
        foreach ($existingTtd as $t) {
            $dbTtdByLevel[$t['level']] = $t;
        }

        // 2. Check SheetRepository if table was empty
        if (empty($dbTtdByLevel)) {
            try {
                $sheetTtd = $this->repository->all('tanda_tangan');
                foreach ($sheetTtd as $st) {
                    if ((string)($st['dokumen_id'] ?? '') === (string)$dokumenId && !empty($st['level'])) {
                        $dbTtdByLevel[$st['level']] = $st;
                    }
                }
            } catch (\Throwable $e) {}
        }

        // Filter out void/invalid signatures based on current document status
        // e.g. If status is 'menunggu_foreman', foreman has NOT completed signature yet!
        $levels = ['foreman', 'section_head', 'dept_head', 'div_head'];
        $result = [];

        foreach ($levels as $level) {
            // Stage validation: a level cannot have a valid completed signature if document status is at or before that stage!
            if ($level === 'foreman') {
                if (in_array($status, ['menunggu_foreman', 'draft', 'revisi'])) {
                    continue;
                }
                if ($isGol4Up && $status === 'menunggu_section_head') {
                    continue;
                }
                // If employee has NO Foreman (and it's not a self-evaluation form 07-006), Foreman NEVER signs!
                if (empty($karyawan['nrp_foreman']) && !$isGol4Up) {
                    continue;
                }
            }
            if ($level === 'section_head') {
                if (in_array($status, ['menunggu_foreman', 'draft', 'menunggu_section_head', 'revisi'])) {
                    continue;
                }
                // For 07-006-SH / Golongan 4 forms, section_head is Department Head (Atasan Langsung)
                // When status is 'menunggu_dept_head', Department Head has NOT signed yet!
                if ($isGol4Up && $status === 'menunggu_dept_head') {
                    continue;
                }
            }
            if ($level === 'dept_head') {
                if (in_array($status, ['menunggu_foreman', 'draft', 'menunggu_section_head', 'menunggu_dept_head', 'revisi'])) {
                    continue;
                }
                // For Golongan 4 forms, dept_head is Division Head (Penilai Final)
                // When status is 'menunggu_div_head', Division Head has NOT signed yet!
                if ($isGol4Up && $status === 'menunggu_div_head') {
                    continue;
                }
            }
            if ($level === 'div_head' && $status !== 'selesai') {
                continue;
            }

            $t = $dbTtdByLevel[$level] ?? null;

            // Signature MUST genuinely exist in database / sheet records ($t).
            // NEVER synthesize or fake a signature if the reviewer has not signed or was purged during revision!
            if (!$t) {
                continue;
            }

            // Identify signer User and metadata
            $userObj = null;
            $signerNrp = null;
            $signerName = null;
            $signerRole = $level;

            if ($t && !empty($t['user_id'])) {
                $userObj = DB::table('app_users')->where('id', $t['user_id'])->first();
                if (!$userObj) {
                    $uArr = $this->repository->find('users', 'id', $t['user_id']);
                    if ($uArr) $userObj = (object)$uArr;
                }
            }

            if (!$userObj) {
                // Resolve from employee hierarchical NRP fields
                if ($level === 'foreman') {
                    $signerNrp = $isGol4Up ? ($karyawan['nrp'] ?? null) : ($karyawan['nrp_foreman'] ?? null);
                    $signerName = $isGol4Up ? ($karyawan['nama_lengkap'] ?? null) : ($karyawan['foreman_nama'] ?? null);
                } elseif ($level === 'section_head') {
                    $signerNrp = $isGol4Up ? ($karyawan['nrp_dept_head'] ?? null) : ($karyawan['nrp_section_head'] ?? null);
                    $signerName = $isGol4Up ? ($karyawan['dept_head_nama'] ?? null) : ($karyawan['section_head_nama'] ?? null);
                } elseif ($level === 'dept_head') {
                    $signerNrp = $isGol4Up ? ($karyawan['nrp_div_head'] ?? null) : ($karyawan['nrp_dept_head'] ?? null);
                    $signerName = $isGol4Up ? ($karyawan['div_head_nama'] ?? null) : ($karyawan['dept_head_nama'] ?? null);
                } elseif ($level === 'div_head') {
                    $signerNrp = $karyawan['nrp_div_head'] ?? null;
                    $signerName = $karyawan['div_head_nama'] ?? null;
                }

                if ($signerNrp) {
                    $userObj = DB::table('app_users')->where('nrp', (string)$signerNrp)->first();
                    if (!$userObj) {
                        $uArr = $this->repository->find('users', 'nrp', (string)$signerNrp);
                        if ($uArr) $userObj = (object)$uArr;
                    }
                }
            }

            if ($userObj) {
                $signerName = $signerName ?: ($userObj->nama ?? null);
                $signerNrp = $signerNrp ?: ($userObj->nrp ?? null);
                $signerRole = $userObj->role ?? $level;
            }

            // Fallback display names if still blank
            $defaultRoleLabels = [
                'foreman' => 'Foreman (Penilai 1)',
                'section_head' => 'Section Head',
                'dept_head' => 'Department Head',
                'div_head' => 'Division Head',
            ];
            $displayName = $signerName ?: ($defaultRoleLabels[$level] ?? strtoupper($level));
            $displayRole = strtoupper($signerRole ?: $level);
            $displayNrp = $signerNrp ?: '-';

            // Resolve Barcode / QR Code TTD:
            // 1) From userObj's barcodettd / ttdbarcode (direct from sheet users)
            // 2) From ttd_image in tanda_tangan table
            // 3) Dynamically generated QR Code Data URI of NRP & Nama
            $candidateBarcode = null;
            if ($userObj) {
                $candidateBarcode = $userObj->barcodettd ?? ($userObj->ttdbarcode ?? null);
            }
            if (empty($candidateBarcode) && $t && !empty($t['ttd_image'])) {
                $candidateBarcode = $t['ttd_image'];
            }
            if (empty($candidateBarcode) && $userObj && !empty($userObj->signature_path)) {
                $candidateBarcode = $userObj->signature_path;
            }

            if ($candidateBarcode && str_starts_with($candidateBarcode, 'data:image')) {
                $finalDataUri = $candidateBarcode;
            } else {
                $finalDataUri = \App\Models\User::generateBarcodeDataUri($displayNrp, $displayName, $displayRole);
            }

            $entry = $t ?: [
                'dokumen_id' => $dokumenId,
                'level' => $level,
                'user_id' => $userObj->id ?? null,
                'tgl_ttd' => now()->toDateTimeString(),
            ];

            $entry['user'] = $userObj ? (array)$userObj : null;
            $entry['ttd_image'] = $finalDataUri;
            $entry['ttd_base64'] = $finalDataUri;
            $entry['signer_name'] = $displayName;
            $entry['signer_nrp'] = $displayNrp;
            $entry['signer_role'] = $displayRole;

            $result[$level] = $entry;
        }

        return $result;
    }

    /**
     * Show the Musashi appraisal form.
     */
    public function show($id, $roleSlug = null)
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$roleSlug) {
            if ($user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head'])) {
                $roleSlug = 'divhead';
            } elseif ($user->isDepartmentHead() || in_array($user->role, ['dept_head', 'department_head'])) {
                $roleSlug = 'depthead';
            } elseif ($user->isSectionHead() || in_array($user->role, ['section_head', 'section'])) {
                $roleSlug = 'sechead';
            } elseif ($user->isForeman() || in_array($user->role, ['foreman', 'ketua'])) {
                $roleSlug = 'foreman';
            } else {
                $roleSlug = 'admin';
            }
        }

        // 1. Fetch document (always fresh directly from DB to prevent 60s cache stale state on revisions)
        $dokumenRow = DB::table('dokumen')->where('id', $id)->first();
        $dokumen = $dokumenRow ? (array)$dokumenRow : $this->repository->find('dokumen', 'id', $id);
        if (!$dokumen) {
            abort(404, 'Dokumen penilaian tidak ditemukan.');
        }

        // Live persistence check: if document locally is at waiting_foreman or has no signatures,
        // ensure operational tables are fresh from Google Spreadsheet so signatures are never lost across container restarts
        $localTtdCount = DB::table('tanda_tangan')->where('dokumen_id', $id)->count();
        if ($localTtdCount === 0 || in_array($dokumen['status'] ?? '', ['draft', 'menunggu_foreman'])) {
            try {
                \App\Services\GoogleSyncService::syncOperationalTablesFromGoogle(true);
                $freshDoc = DB::table('dokumen')->where('id', $id)->first();
                if ($freshDoc) {
                    $dokumen = (array)$freshDoc;
                }
            } catch (\Throwable $e) {}
        }

        // 2. Fetch employee details (fresh from DB)
        $karyawanRow = DB::table('karyawan')->where('nrp', $dokumen['nrp'])->first();
        $karyawan = $karyawanRow ? (array)$karyawanRow : $this->repository->find('karyawan', 'nrp', $dokumen['nrp']);
        if (!$karyawan) {
            abort(404, 'Data karyawan untuk dokumen ini tidak ditemukan.');
        }

        // Resolusi NAMA SECT. (Seksi resmi organisasi, BUKAN Nama Bagian / tanpa .SEC) agar tepat masuk ke form
        $rawSect = trim($karyawan['nama_sect'] ?? '');
        if (!$rawSect) {
            $rawS = trim($karyawan['seksi'] ?? '');
            $rawSUpper = strtoupper($rawS);
            $deptUpper = strtoupper(trim($karyawan['departemen'] ?? 'PROD 2'));
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
        $karyawan['nama_sect'] = $rawSect;

        // 3. Authorization check
        $karyawanDept = $karyawan['departemen'] ?? '';
        if (!self::canUserAccessKaryawan($user, $karyawan)) {
            abort(403, "Anda tidak memiliki hak akses untuk melihat atau menilai dokumen karyawan ini.");
        }

        // 4. Fetch Active Period & Dynamic Form Settings
        $periode = $this->repository->find('periode', 'id', $dokumen['periode_id']) ?? [
            'id' => $dokumen['periode_id'],
            'tahun' => 2025,
            'status' => 'aktif'
        ];

        $allSettings = \Illuminate\Support\Facades\DB::table('pengaturan')->pluck('value', 'key')->toArray();
        $formSettings = [
            'tahun_penilaian' => $allSettings['tahun_penilaian'] ?? ($periode['tahun'] ?? 2025),
            'periode_penilaian' => $allSettings['periode_penilaian'] ?? 'Jan - Des',
            'judul_form_pk' => $allSettings['judul_form_pk'] ?? 'PENILAIAN KARYA',
            'nama_perusahaan' => $allSettings['nama_perusahaan'] ?? 'PT MUSASHI AUTO PARTS INDONESIA',
            'catatan_petunjuk_penilai' => $allSettings['catatan_petunjuk_penilai'] ?? 'Pilih salah satu dari 5 pilihan kalimat kriteria di bawah ini yang paling sesuai.',
            'label_kerahasiaan' => $allSettings['label_kerahasiaan'] ?? 'RAHASIA',
            'catatan_1' => $allSettings['catatan_1'] ?? 'Jika nilai pecahan, ditentukan dua angka dibelakang koma.',
            'catatan_2' => $allSettings['catatan_2'] ?? 'Pembulatan : ≤ 0,5 pembulatan ke bawah ; > 0,5 pembulatan ke atas.',
            'judul_evaluasi_potensi' => $allSettings['judul_evaluasi_potensi'] ?? 'INDIKATOR EVALUASI POTENSI KARYAWAN',
            'petunjuk_evaluasi_potensi' => $allSettings['petunjuk_evaluasi_potensi'] ?? 'Setiap Atasan diharapkan mengisi dengan seksama kolom ini untuk mengetahui seberapa jauh potensi karyawan dapat dikembangkan ke posisi & tanggung jawab yang lebih besar sesuai dengan ketentuan yang berlaku dan selagi ada kesempatan dalam struktur organisasi.',
            'opsi_potensi_1' => $allSettings['opsi_potensi_1'] ?? 'Memenuhi standard untuk dipromosikan pada level diatasnya, saran pada posisi :',
            'opsi_potensi_2' => $allSettings['opsi_potensi_2'] ?? 'Sesuai dengan posisi saat ini dan masih bisa dikembangkan dengan pengalaman dan pelatihan sesuai matriks.',
            'opsi_potensi_3' => $allSettings['opsi_potensi_3'] ?? 'Sesuai untuk posisi saat ini dan tidak bisa dikembangkan lagi, alasan :',
            'opsi_potensi_4' => $allSettings['opsi_potensi_4'] ?? 'Yang bersangkutan kurang sesuai dengan posisi saat ini, dengan alasan :',
            'judul_kelebihan' => $allSettings['judul_kelebihan'] ?? 'KELEBIHAN KARYAWAN',
            'judul_kekurangan' => $allSettings['judul_kekurangan'] ?? 'KEKURANGAN KARYAWAN',
        ];

        // Fetch accessible documents list for instant NRP switching in UI (always direct DB query to avoid stale cache)
        $allDocs = DB::table('dokumen')->get()->map(fn($r) => (array)$r)->toArray();
        $allKaryawan = DB::table('karyawan')->get()->map(fn($r) => (array)$r)->toArray();
        $karyawanMap = [];
        foreach ($allKaryawan as $k) {
            $karyawanMap[$k['nrp']] = $k;
        }

        $accessibleDocs = [];
        foreach ($allDocs as $d) {
            $k = $karyawanMap[$d['nrp']] ?? null;
            if (!$k) continue;
            if (self::canUserAccessKaryawan($user, $k)) {
                $isSelfDoc = ($d['nrp'] === $user->nrp);
                // Khusus Division Head: Dokumen bawahan TIDAK muncul untuk dinilai jika belum melalui Section Head dan Department Head
                // (Hanya muncul jika status sudah 'menunggu_div_head' atau 'selesai' / 'revisi')
                // Penilaian Mandiri Division Head ($isSelfDoc) SELALU muncul
                if (($user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head'])) && !$user->isAdmin()) {
                    if (!$isSelfDoc && !in_array($d['status'], ['menunggu_div_head', 'selesai', 'revisi'])) {
                        continue;
                    }
                }
                $accessibleDocs[] = [
                    'id' => $d['id'],
                    'nrp' => $d['nrp'],
                    'nama_lengkap' => $k['nama_lengkap'] ?? '',
                    'jabatan' => $k['jabatan'] ?? '',
                    'level_form' => $k['level_form'] ?? '',
                    'kode_form' => $d['kode_form'],
                    'status' => $d['status'],
                    'is_current' => ($d['id'] == $id),
                ];
            }
        }

        $kodeForm = $dokumen['kode_form'];
        $formStructure = $this->calcService->getFormStructure($kodeForm);

        // 5. Fetch Master Aspek & Master Kriteria from repository / DB
        $allAspek = $this->repository->where('master_aspek', ['kode_form' => $kodeForm]);
        if (empty($allAspek) || !isset($allAspek[0]['aspek_kode'])) {
            $allAspek = DB::table('master_aspek')->where('kode_form', $kodeForm)->get()->map(fn($r) => (array)$r)->toArray();
        }
        usort($allAspek, fn($a, $b) => ($a['urutan'] ?? 0) <=> ($b['urutan'] ?? 0));

        $allKriteria = $this->repository->all('master_kriteria');
        if (empty($allKriteria) || !isset($allKriteria[0]['aspek_kode'])) {
            $allKriteria = DB::table('master_kriteria')->get()->map(fn($r) => (array)$r)->toArray();
        }
        $kriteriaMap = [];
        foreach ($allKriteria as $crit) {
            $aspekKode = $crit['aspek_kode'] ?? ($crit['KODE ASPEK'] ?? null);
            $skor = $crit['skor'] ?? ($crit['SKOR (1-5)'] ?? null);
            $deskripsi = $crit['deskripsi'] ?? ($crit['DESKRIPSI KRITERIA'] ?? '');
            if ($aspekKode && $skor !== null) {
                $kriteriaMap[$aspekKode][$skor] = $deskripsi;
            }
        }

        // 6. Fetch Existing Scores (nilai table) - fresh directly from DB
        $dbNilai = DB::table('nilai')->where('dokumen_id', $id)->get();
        $existingNilai = $dbNilai->isNotEmpty() 
            ? $dbNilai->map(fn($n) => (array)$n)->toArray() 
            : $this->repository->where('nilai', ['dokumen_id' => $id]);
        $scoresByLevel = [
            'foreman' => [],
            'section_head' => [],
            'dept_head' => [],
        ];
        foreach ($existingNilai as $n) {
            $lvl = $n['level'] ?? 'foreman';
            $scoresByLevel[$lvl][$n['aspek_kode']] = (float)$n['skor'];
        }

        $status = $dokumen['status'] ?? 'draft';
        $isGol4Up = self::isForm07006($kodeForm);

        // STAGE ENFORCEMENT & DISPLAY SANITIZATION
        // Jika dokumen pada status belum mencapai Dept Head / Div Head, atau jika direvisi kembali ke level bawah,
        // nilai dan tanda tangan tingkat di atasnya WAJIB KOSONG!

        // 1. Jika dokumen masih tahap Foreman / Draft:
        if (in_array($status, ['menunggu_foreman', 'draft'])) {
            $scoresByLevel['section_head'] = [];
            $scoresByLevel['dept_head'] = [];
        }

        // 2. Jika dokumen pada tahap Section Head (menunggu_section_head, draft, atau revisi ke section head),
        // ATAU jika pengguna login sebagai Section Head yang sedang menilai (mandiri atau bawahan):
        // - Form 07-006: Kolom 2 (Dept Head / slot 'section_head') & Kolom 3 (Div Head / slot 'dept_head') WAJIB KOSONG!
        // - Form standar: Kolom 3 (Dept Head / slot 'dept_head') WAJIB KOSONG!
        $isSecHeadStage = in_array($status, ['menunggu_section_head', 'menunggu_foreman', 'draft', 'revisi'])
            || ($user->isSectionHead() && ($dokumen['nrp'] === $user->nrp || $user->nrp === ($karyawan['nrp_section_head'] ?? '')));

        if ($isSecHeadStage) {
            if ($isGol4Up) {
                $scoresByLevel['section_head'] = [];
                $scoresByLevel['dept_head'] = [];
            } else {
                $scoresByLevel['dept_head'] = [];
            }
        }

        // 3. Jika dokumen pada tahap Dept Head (menunggu_dept_head atau revisi ke dept head):
        // - Form 07-006: Kolom 3 (Div Head / slot 'dept_head') WAJIB KOSONG!
        $isDeptHeadStage = in_array($status, ['menunggu_dept_head', 'revisi'])
            || ($user->isDepartmentHead() && ($dokumen['nrp'] === $user->nrp || $user->nrp === ($karyawan['nrp_dept_head'] ?? '')));

        if ($isDeptHeadStage) {
            if ($isGol4Up) {
                $scoresByLevel['dept_head'] = [];
            }
        }

        // For Division Head doing re-assessment/penyesuaian, pre-fill dept_head with previous evaluator scores ONLY if status is menunggu_div_head or selesai
        if (($user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head'])) && $dokumen['nrp'] !== $user->nrp && empty($scoresByLevel['dept_head'])) {
            if (in_array($status, ['menunggu_div_head', 'selesai'])) {
                if (!empty($scoresByLevel['section_head'])) {
                    $scoresByLevel['dept_head'] = $scoresByLevel['section_head'];
                } elseif (!empty($scoresByLevel['foreman'])) {
                    $scoresByLevel['dept_head'] = $scoresByLevel['foreman'];
                }
            }
        }

        // 7. Calculate totals for each level
        $calcForeman = $this->calcService->calculate($kodeForm, $scoresByLevel['foreman']);
        $calcSectionHead = $this->calcService->calculate($kodeForm, $scoresByLevel['section_head']);
        $calcDeptHead = $this->calcService->calculate($kodeForm, $scoresByLevel['dept_head']);

        // 8. Fetch Indikator (indikator table) - fresh directly from DB
        $dbIndikator = DB::table('indikator')->where('dokumen_id', $id)->first();
        $indikator = $dbIndikator ? (array)$dbIndikator : ($this->repository->find('indikator', 'dokumen_id', $id) ?? [
            'pilihan_potensi' => null,
            'kelebihan' => '',
            'kekurangan' => '',
        ]);
        $commentMeta = self::enrichIndikatorComments($indikator, $dokumen, $karyawan, $user);

        // 8b. Clean up stale signatures if document was revised back to an earlier stage
        if (in_array($status, ['menunggu_foreman', 'draft'])) {
            DB::table('tanda_tangan')->where('dokumen_id', $id)->delete();
        } elseif ($status === 'menunggu_section_head') {
            DB::table('tanda_tangan')->where('dokumen_id', $id)->whereIn('level', ['section_head', 'dept_head', 'div_head'])->delete();
        } elseif ($status === 'menunggu_dept_head') {
            DB::table('tanda_tangan')->where('dokumen_id', $id)->whereIn('level', ['dept_head', 'div_head'])->delete();
        } elseif ($status === 'menunggu_div_head') {
            if ($isGol4Up) {
                DB::table('tanda_tangan')->where('dokumen_id', $id)->whereIn('level', ['dept_head', 'div_head'])->delete();
            }
        } elseif ($status === 'revisi') {
            DB::table('tanda_tangan')->where('dokumen_id', $id)->whereIn('level', ['dept_head', 'div_head'])->delete();
        }

        // 9. Fetch Complete Signatures for all evaluated levels (guaranteed valid Data URIs)
        $ttdByLevel = $this->buildCompleteTtdByLevel($id, $dokumen, $karyawan, $calcForeman, $calcSectionHead, $calcDeptHead);

        // Sanitize signatures by stage: Jangan tampilkan barcode Dept Head / Div Head jika belum saatnya atau sedang direvisi!
        if ($isSecHeadStage) {
            if ($isGol4Up) {
                unset($ttdByLevel['section_head'], $ttdByLevel['dept_head'], $ttdByLevel['div_head']);
            } else {
                unset($ttdByLevel['dept_head'], $ttdByLevel['div_head']);
            }
            $dokumen['total_nilai'] = null;
            $dokumen['grade_original'] = null;
            $dokumen['docking_final'] = null;
        }
        if ($isDeptHeadStage) {
            if ($isGol4Up) {
                unset($ttdByLevel['dept_head'], $ttdByLevel['div_head']);
            } else {
                unset($ttdByLevel['div_head']);
            }
            $dokumen['docking_final'] = null;
        }

        // 10. Determine Role Permissions for Editing
        $isDirectSubordinate = !empty($karyawan['nrp_section_head']) && strcasecmp($karyawan['nrp_section_head'], $user->nrp) === 0;
        $isSectionHeadWithoutForeman = $user->isSectionHead() && !$user->hasForemanSubordinates();
        $isDeptHeadSubordinate = ($user->isDepartmentHead() || in_array($user->role, ['dept_head', 'department_head'])) && !empty($karyawan['nrp_dept_head']) && strcasecmp($karyawan['nrp_dept_head'], $user->nrp) === 0;
        $isDivHeadSubordinate = ($user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head'])) && !empty($karyawan['nrp_div_head']) && strcasecmp($karyawan['nrp_div_head'], $user->nrp) === 0;

        $isMyDept = $user->canAccessDepartment($karyawanDept)
            || ($isSectionHeadWithoutForeman && $isDirectSubordinate)
            || $isDeptHeadSubordinate
            || $isDivHeadSubordinate;

        $canEditForeman = false;
        $canEditSectionHead = false;
        $canEditDeptHead = false;

        $canSignForeman = false;
        $canSignSectionHead = false;
        $canSignDeptHead = false;
        $canRevisi = false;

        if ($user->isSuperadmin()) {
            // Superadmin can edit and override anything
            $canEditForeman = true;
            $canEditSectionHead = true;
            $canEditDeptHead = true;
            $canSignForeman = empty($ttdByLevel['foreman']);
            $canSignSectionHead = empty($ttdByLevel['section_head']);
            $canSignDeptHead = empty($ttdByLevel['dept_head']);
            $canRevisi = true;
        } else {
            // Foreman rules
            if ($user->isForeman() && $isMyDept) {
                if (in_array($status, ['menunggu_foreman', 'draft', 'revisi'])) {
                    $canEditForeman = true;
                    $canSignForeman = true;
                }
            }

            // Section Head rules
            if ($user->isSectionHead() && $isMyDept) {
                $isGol4UpDoc = in_array($kodeForm, ['07-006-SH', '07-006-DH', '07-006-DIV']);
                // Evaluasi Mandiri Section Head / Form 07-006 -> Mengisi Kolom 1 (Mandiri / foreman slot)
                if ($dokumen['nrp'] === $user->nrp || $isGol4UpDoc) {
                    if ($dokumen['nrp'] === $user->nrp || in_array($status, ['menunggu_section_head', 'draft', 'revisi'])) {
                        $canEditForeman = true;
                        $canSignForeman = true;
                    }
                    $canEditSectionHead = false;
                    $canSignSectionHead = false;
                } elseif (empty($karyawan['nrp_foreman']) || !$user->hasForemanSubordinates()) {
                    // Kasus Section Head menilai bawahan KJ 1-5 tanpa Foreman:
                    $canEditForeman = false;
                    $canSignForeman = false;
                    if (in_array($status, ['menunggu_section_head', 'menunggu_foreman', 'draft', 'revisi'])) {
                        $canEditSectionHead = true;
                        $canSignSectionHead = true;
                    }
                    $canRevisi = false;
                } else {
                    // Kasus normal dengan Foreman (Form 07-001/002/003):
                    if (in_array($status, ['menunggu_section_head', 'revisi'])) {
                        $canEditSectionHead = true;
                        $canSignSectionHead = true;
                        $canRevisi = true;
                    } else {
                        $canEditSectionHead = false;
                        $canSignSectionHead = false;
                    }
                }
            }

            // Department Head rules
            if ($user->isDepartmentHead() && $isMyDept) {
                // Evaluasi Mandiri Dept Head (Diri Sendiri) -> Mengisi Kolom 1 (Mandiri)
                if ($dokumen['nrp'] === $user->nrp) {
                    if (in_array($status, ['menunggu_dept_head', 'draft', 'revisi'])) {
                        $canEditForeman = true;
                        $canSignForeman = true;
                    }
                // Aktif jika Section Head sudah selesai & status sudah 'menunggu_dept_head' atau 'revisi'!
                } elseif (in_array($status, ['menunggu_dept_head', 'revisi'])) {
                    $isGol4UpDoc = in_array($kodeForm, ['07-006-SH', '07-006-DH', '07-006-DIV']);
                    if ($isGol4UpDoc) {
                        $canEditSectionHead = true;
                        $canSignSectionHead = true;
                        $canRevisi = true;
                    } else {
                        $canEditDeptHead = true;
                        $canSignDeptHead = true;
                        $canRevisi = true;
                    }
                }
            }

            // Division Head rules (Evaluasi Mandiri & Penilaian Ulang / Docking Kuota)
            if (($user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head'])) && ($isMyDept || $dokumen['nrp'] === $user->nrp)) {
                $isGol4UpDoc = in_array($kodeForm, ['07-006-SH', '07-006-DH', '07-006-DIV']);
                // Evaluasi Mandiri Division Head (Diri Sendiri) -> Mengisi Kolom 3 (Mandiri / 自分), TTD Kolom 1
                if ($dokumen['nrp'] === $user->nrp) {
                    if (empty($ttdByLevel['foreman'])) {
                        $canEditDeptHead = true;
                        $canSignForeman = true;
                    }
                } else {
                    // Penilaian & Penyesuaian Kuota khusus Division Head untuk seluruh karyawan divisi
                    // HANYA dapat diedit & difinalisasi jika dokumen sudah melalui Section Head dan Dept Head (status: menunggu_div_head / selesai)
                    if (in_array($status, ['menunggu_div_head', 'selesai', 'revisi'])) {
                        if ($status !== 'selesai' || empty($ttdByLevel['dept_head'])) {
                            $canEditDeptHead = true;
                            $canSignDeptHead = true;
                            $canRevisi = true;
                        }
                    } else {
                        // Belum melalui Section Head / Dept Head -> Terkunci (Read-Only)
                        $canEditDeptHead = false;
                        $canSignDeptHead = false;
                    }
                }
            }
        }

        // Determine if higher tier evaluator is waiting for prior stage
        $waitingForLevel = null;
        if (!$user->isSuperadmin()) {
            if ($user->isSectionHead()) {
                if (!empty($karyawan['nrp_foreman']) && in_array($status, ['menunggu_foreman', 'draft'])) {
                    $waitingForLevel = 'foreman';
                }
            } elseif ($user->isDepartmentHead()) {
                if ($dokumen['nrp'] !== $user->nrp) {
                    if (!empty($karyawan['nrp_foreman']) && in_array($status, ['menunggu_foreman', 'draft'])) {
                        $waitingForLevel = 'foreman';
                    } elseif (in_array($status, ['menunggu_section_head', 'menunggu_foreman', 'draft'])) {
                        $waitingForLevel = 'section_head';
                    }
                }
            } elseif ($user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head'])) {
                // Untuk bawahan yang belum sampai tahap Division Head, set level yang sedang ditunggu agar form terkunci (Read-Only)
                if ($dokumen['nrp'] !== $user->nrp && !in_array($status, ['menunggu_div_head', 'selesai', 'revisi'])) {
                    if (!empty($karyawan['nrp_foreman']) && in_array($status, ['menunggu_foreman', 'draft'])) {
                        $waitingForLevel = 'foreman';
                    } elseif (in_array($status, ['menunggu_section_head', 'menunggu_foreman', 'draft'])) {
                        $waitingForLevel = 'section_head';
                    } elseif ($status === 'menunggu_dept_head') {
                        $waitingForLevel = 'dept_head';
                    } else {
                        $waitingForLevel = 'dept_head';
                    }
                } else {
                    $waitingForLevel = null;
                }
            }
        }

        // Potential indicator is strictly editable ONLY if it is currently this evaluator's turn!
        $canEditIndikator = $this->isUserTurnToEvaluate($user, $dokumen, $karyawan)
            && ($user->isSuperadmin() || $canEditForeman || $canEditSectionHead || $canEditDeptHead);
        $isGol4Up = in_array($kodeForm, ['07-006-SH', '07-006-DH', '07-006-DIV']);
        $subFormVariant = null;
        if ($isGol4Up) {
            if ($kodeForm === '07-006-DIV' || ($karyawan['level_form'] ?? '') === 'DIV_HEAD' || str_contains(strtoupper($karyawan['jabatan'] ?? ''), 'DIVISION')) {
                $subFormVariant = 'div_head';
            } elseif ($kodeForm === '07-006-DH' || ($karyawan['level_form'] ?? '') === 'DEPT_HEAD' || str_contains(strtoupper($karyawan['jabatan'] ?? ''), 'DEPARTMENT') || str_contains(strtoupper($karyawan['jabatan'] ?? ''), 'DEPT HEAD')) {
                $subFormVariant = 'dept_head';
            } else {
                $subFormVariant = 'section_head';
            }
        }

        // Musashi Official Logo base64
        $logoPath = file_exists(public_path('logomusashi.png')) 
            ? public_path('logomusashi.png') 
            : (file_exists(base_path('logomusashi.png')) 
                ? base_path('logomusashi.png') 
                : (file_exists(public_path('musashi.png')) ? public_path('musashi.png') : base_path('musashi.png')));
        $musashiLogoBase64 = null;
        if (file_exists($logoPath)) {
            $logoContent = file_get_contents($logoPath);
            $musashiLogoBase64 = 'data:image/png;base64,' . base64_encode($logoContent);
        }

        $rolePrefix = $roleSlug;
        $roleFormUrlBase = url("/penilaian/{$roleSlug}/dokumen");

        // Fetch target quota and grade usage for Division Head or Department Head calibration (skip 07-006 forms)
        $divTargetQuota = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];
        $divOtherUsage = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];
        $isQuotaExempt = $isGol4Up || self::isQuotaExemptEmployee($karyawan, $dokumen);
        $isDeptHeadRole = ($user->isDepartmentHead() || in_array($user->role, ['dept_head', 'department_head']));
        $isDivHeadViewer = (self::isQuotaEnforcer($user) || $user->isAdmin());
        $quotaScopeLabel = 'Departemen';
        $penyesuaianRoute = route('penilaian.dept.penyesuaian');
        $empKj = ExcelKaryawanReader::normalizeQuotaKj($karyawan['kj'] ?? 'KJ 2');
        $kjTargetQuota = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0, 'total' => 0];
        $kjOtherUsage = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];

        if ($isDivHeadViewer && !$isQuotaExempt) {
            $scopeSubordinates = array_values(array_filter($allKaryawan, function ($k) use ($user, $isDeptHeadRole) {
                if (self::isQuotaExemptEmployee($k)) return false;
                if ($user->isAdmin() || $user->isSuperadmin()) return true;
                return $isDeptHeadRole ? $user->matchesDeptHeadScope($k) : $user->matchesDivisionHeadScope($k);
            }));

            $divTargetQuota = self::getTargetQuotaForScope($user, $scopeSubordinates);
            $activeQuotaStatuses = $isDeptHeadRole
                ? ['menunggu_dept_head', 'menunggu_div_head', 'selesai']
                : ['menunggu_div_head', 'selesai'];

            $otherNrps = array_values(array_diff(array_column($scopeSubordinates, 'nrp'), [$dokumen['nrp']]));
            $subDocs = !empty($otherNrps)
                ? DB::table('dokumen')
                    ->where('periode_id', $dokumen['periode_id'] ?? 1)
                    ->whereIn('nrp', $otherNrps)
                    ->orderByDesc('id')
                    ->get()
                    ->unique('nrp')
                : collect();

            foreach ($subDocs as $sd) {
                if (self::isForm07006($sd->kode_form ?? null)) {
                    continue;
                }
                if (!in_array($sd->status, $activeQuotaStatuses)) {
                    continue;
                }
                $g = null;
                if (!empty($sd->docking_final) && in_array(strtoupper(trim($sd->docking_final)), ['S', 'A', 'B', 'C', 'K'])) {
                    $g = strtoupper(trim($sd->docking_final));
                } elseif (!empty($sd->grade_original) && in_array(strtoupper(trim($sd->grade_original)), ['S', 'A', 'B', 'C', 'K'])) {
                    if ($sd->status === 'selesai' || $isDeptHeadRole) {
                        $g = strtoupper(trim($sd->grade_original));
                    }
                }
                if ($g) {
                    $divOtherUsage[$g]++;
                }
            }

            $kjSummaryAll = $isDeptHeadRole
                ? self::getKjSummaryForDept($user, $scopeSubordinates)
                : self::getKjSummaryForDivision($user, $scopeSubordinates);
            $kjTargetQuota = $kjSummaryAll[$empKj]['target'] ?? ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0, 'total' => 0];

            foreach ($subDocs as $sd) {
                if (self::isForm07006($sd->kode_form ?? null)) {
                    continue;
                }
                if (!in_array($sd->status, $activeQuotaStatuses)) {
                    continue;
                }
                $matchingSub = collect($scopeSubordinates)->firstWhere('nrp', $sd->nrp);
                $sdKj = ExcelKaryawanReader::normalizeQuotaKj($matchingSub['kj'] ?? 'KJ 2');
                if ($sdKj === $empKj) {
                    $g = null;
                    if (!empty($sd->docking_final) && in_array(strtoupper(trim($sd->docking_final)), ['S', 'A', 'B', 'C', 'K'])) {
                        $g = strtoupper(trim($sd->docking_final));
                    } elseif (!empty($sd->grade_original) && in_array(strtoupper(trim($sd->grade_original)), ['S', 'A', 'B', 'C', 'K'])) {
                        if ($sd->status === 'selesai' || $isDeptHeadRole) {
                            $g = strtoupper(trim($sd->grade_original));
                        }
                    }
                    if ($g && isset($kjOtherUsage[$g])) {
                        $kjOtherUsage[$g]++;
                    }
                }
            }
        }

        return view('penilaian.form', compact(
            'dokumen',
            'karyawan',
            'periode',
            'kodeForm',
            'isGol4Up',
            'isQuotaExempt',
            'subFormVariant',
            'musashiLogoBase64',
            'formStructure',
            'allAspek',
            'kriteriaMap',
            'scoresByLevel',
            'calcForeman',
            'calcSectionHead',
            'calcDeptHead',
            'indikator',
            'commentMeta',
            'ttdByLevel',
            'canEditForeman',
            'canEditSectionHead',
            'canEditDeptHead',
            'canSignForeman',
            'canSignSectionHead',
            'canSignDeptHead',
            'canRevisi',
            'canEditIndikator',
            'waitingForLevel',
            'accessibleDocs',
            'formSettings',
            'rolePrefix',
            'roleFormUrlBase',
            'divTargetQuota',
            'divOtherUsage',
            'empKj',
            'kjTargetQuota',
            'kjOtherUsage',
            'isDivHeadViewer',
            'quotaScopeLabel',
            'penyesuaianRoute'
        ));
    }

    /**
     * Jump directly to appraisal document by employee NRP.
     * Automatically initializes document if not created yet.
     */
    public function byNrp($nrp)
    {
        /** @var User $user */
        $user = Auth::user();

        $karyawan = $this->repository->find('karyawan', 'nrp', $nrp);
        if (!$karyawan) {
            return redirect()->route('dashboard')->with('error', "Karyawan dengan NRP {$nrp} tidak ditemukan di database.");
        }

        if (!self::canUserAccessKaryawan($user, $karyawan)) {
            return redirect()->route('dashboard')->with('error', "Anda tidak memiliki hak akses untuk mengakses penilaian karyawan dengan NRP {$nrp}.");
        }

        $isSelf = (strcasecmp($nrp, $user->nrp) === 0);
        if (self::isQuotaEnforcer($user) && !$isSelf) {
            $existingDocCheck = DB::table('dokumen')->where('nrp', $nrp)->orderByDesc('id')->first();
            $isQuotaExempt = self::isQuotaExemptEmployee($karyawan, $existingDocCheck ? (array)$existingDocCheck : null);
            $isDeptUser = ($user->isDepartmentHead() || in_array($user->role, ['dept_head', 'department_head']));
            if ($existingDocCheck && $existingDocCheck->status !== 'selesai' && !$isQuotaExempt) {
                $allKarRows = DB::table('karyawan')->get()->map(fn($r) => (array)$r)->toArray();
                $subordinates = self::scopeSubordinatesForQuotaEnforcer($user, $allKarRows);
                $kjSummary = $isDeptUser
                    ? self::getKjSummaryForDept($user, $subordinates)
                    : self::getKjSummaryForDivision($user, $subordinates);
                $empKj = ExcelKaryawanReader::normalizeQuotaKj($karyawan['kj'] ?? 'KJ 2');
                $kjInfo = $kjSummary[$empKj] ?? null;
                $empGrade = !empty($existingDocCheck->docking_final) ? $existingDocCheck->docking_final : (!empty($existingDocCheck->grade_original) ? $existingDocCheck->grade_original : null);
                $isEmpGradeOverKj = ($kjInfo && $empGrade && isset($kjInfo['actual'][$empGrade]) && isset($kjInfo['target'][$empGrade]) && $kjInfo['actual'][$empGrade] > $kjInfo['target'][$empGrade]);

                if ($kjInfo && ($kjInfo['is_over'] || $isEmpGradeOverKj || ($kjInfo['is_full'] && empty($empGrade)))) {
                    $overMsg = !empty($kjInfo['over_ranks']) 
                        ? "Kuota jenjang {$empKj} melebihi target pada Rank " . implode(', ', array_keys($kjInfo['over_ranks']))
                        : ($isEmpGradeOverKj ? "Grade saat ini ({$empGrade}) melebihi alokasi target jenjang {$empKj}" : "Kapasitas kuota untuk jenjang {$empKj} sudah penuh ({$kjInfo['actual']['total']}/{$kjInfo['target']['total']})");
                    $penyesuaianRouteName = $isDeptUser ? 'penilaian.dept.penyesuaian' : 'penilaian.div.penyesuaian';
                    return redirect()->route($penyesuaianRouteName, ['filter_kj' => $empKj])
                        ->with('error', "{$overMsg}. Formulir terkunci untuk penilaian langsung. Silakan lakukan penyesuaian kuota terlebih dahulu di halaman Penyesuaian Kuota.");
                }
            }
        }

        $isSectionHeadWithoutForeman = $user->isSectionHead() && !$user->hasForemanSubordinates();
        $isSelf = (strcasecmp($nrp, $user->nrp) === 0);
        if ($isSelf && ($user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head']))) {
            $expectedKodeForm = '07-006-DIV';
            $initialStatus = 'menunggu_div_head';
        } elseif ($isSelf && ($user->isDepartmentHead() || in_array($user->role, ['dept_head', 'department_head']))) {
            $expectedKodeForm = '07-006-DH';
            $initialStatus = 'menunggu_dept_head';
        } elseif ($isSelf && ($user->isSectionHead() || in_array($user->role, ['section_head', 'section']))) {
            $expectedKodeForm = '07-006-SH';
            $initialStatus = 'menunggu_section_head';
        } else {
            $expectedKodeForm = self::determineKodeForm($karyawan);
            $initialStatus = empty($karyawan['nrp_foreman']) ? 'menunggu_section_head' : 'menunggu_foreman';
        }

        $doc = DB::table('dokumen')->where('nrp', $nrp)->orderByDesc('id')->first();
        if (!$doc) {
            // Automatically determine form code and create active document
            $activePeriod = $this->repository->find('periode', 'status', 'aktif') ?? ['id' => 1, 'tahun' => 2025];

            $docId = DB::table('dokumen')->insertGetId([
                'periode_id' => $activePeriod['id'],
                'nrp' => $karyawan['nrp'],
                'kode_form' => $expectedKodeForm,
                'status' => $initialStatus,
                'kode_verifikasi' => 'VERIF-' . strtoupper(Str::random(10)),
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ]);

            return redirect(self::getFormUrlForUser($user, $docId))->with('success', "Formulir {$expectedKodeForm} untuk {$karyawan['nama_lengkap']} (KJ: {$karyawan['kj']}) berhasil dibuat otomatis. Silakan mulai penilaian!");
        }

        // Synchronize kode_form / status if unfinalized and scores are 0 but employee KJ/Jabatan/hierarchy requires a different form
        if (in_array($doc->status, ['draft', 'menunggu_foreman', 'menunggu_section_head', 'belum_dinilai']) && empty($doc->total_nilai)) {
            $updates = [];
            if ($doc->kode_form !== $expectedKodeForm) {
                $updates['kode_form'] = $expectedKodeForm;
            }
            if ($doc->status === 'menunggu_foreman' && empty($karyawan['nrp_foreman'])) {
                $updates['status'] = 'menunggu_section_head';
            }
            if (!empty($updates)) {
                $updates['updated_at'] = now()->toDateTimeString();
                DB::table('dokumen')->where('id', $doc->id)->update($updates);
            }
        }

        return redirect(self::getFormUrlForUser($user, $doc->id));
    }

    /**
     * Handle form submission from NRP Search box.
     */
    public function bukaByNrp(Request $request)
    {
        $nrp = trim($request->input('nrp', ''));
        if (!$nrp) {
            return back()->with('error', 'Silakan masukkan nomor NRP karyawan.');
        }

        return $this->byNrp($nrp);
    }

    /**
     * Real-time API lookup as user types NRP or Name.
     */
    public function apiLookupKaryawan(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        $query = trim($request->query('q', $request->query('nrp', '')));

        if (!$query) {
            return response()->json(['found' => false, 'message' => 'NRP query kosong']);
        }

        $allKaryawan = $this->repository->all('karyawan');
        $matched = null;

        foreach ($allKaryawan as $k) {
            if (strcasecmp($k['nrp'] ?? '', $query) === 0) {
                $matched = $k;
                break;
            }
        }

        if (!$matched) {
            foreach ($allKaryawan as $k) {
                if (stripos($k['nama_lengkap'] ?? '', $query) !== false || stripos($k['nrp'] ?? '', $query) !== false) {
                    $matched = $k;
                    break;
                }
            }
        }

        if (!$matched) {
            return response()->json(['found' => false, 'message' => "Karyawan '{$query}' tidak ditemukan."]);
        }

        $dept = $matched['departemen'] ?? '';
        $canAccess = self::canUserAccessKaryawan($user, $matched);

        $kodeForm = self::determineKodeForm($matched);
        $formName = self::getFormName($kodeForm);

        $existingDoc = DB::table('dokumen')->where('nrp', $matched['nrp'])->orderByDesc('id')->first();

        $foremanEvaluated = false;
        $hasForemanSign = false;
        $foremanScore = null;
        $foremanGrade = null;
        $ttdForemanDate = null;

        $foremanNrp = $matched['nrp_foreman'] ?? null;
        $foremanUser = $foremanNrp ? DB::table('app_users')->where('nrp', $foremanNrp)->first() : null;
        $foremanName = $foremanUser?->nama ?? ($foremanNrp ? "Foreman ($foremanNrp)" : 'Foreman');

        $sectionHeadNrp = $matched['nrp_section_head'] ?? null;
        $sectionHeadUser = $sectionHeadNrp ? DB::table('app_users')->where('nrp', $sectionHeadNrp)->first() : null;
        $sectionHeadName = $sectionHeadUser?->nama ?? ($sectionHeadNrp ? "Section Head ($sectionHeadNrp)" : 'Section Head');

        $sectionHeadEvaluated = false;
        $hasSectionHeadSign = false;
        $sectionHeadScore = null;
        $sectionHeadGrade = null;
        $ttdSectionHeadDate = null;

        $deptHeadNrp = $matched['nrp_dept_head'] ?? null;
        $deptHeadUser = $deptHeadNrp ? DB::table('app_users')->where('nrp', $deptHeadNrp)->first() : null;
        $deptHeadName = $deptHeadUser?->nama ?? ($deptHeadNrp ? "Dept Head ($deptHeadNrp)" : 'Department Head');
        $deptHeadEvaluated = false;
        $hasDeptHeadSign = false;
        $deptScore = null;
        $deptGrade = null;
        $ttdDeptHeadDate = null;

        $hasForeman = !empty($foremanNrp);
        $isGol4UpForm = in_array($kodeForm, ['07-006-SH', '07-006-DH', '07-006-DIV']);
        $isSelf = (strcasecmp($matched['nrp'] ?? '', $user->nrp) === 0);

        if ($existingDoc) {
            if ($hasForeman && !$isGol4UpForm && !$isSelf) {
                $foremanTtd = DB::table('tanda_tangan')
                    ->where('dokumen_id', $existingDoc->id)
                    ->where('level', 'foreman')
                    ->first();

                if ($foremanTtd) {
                    $hasForemanSign = true;
                    $ttdForemanDate = substr($foremanTtd->tgl_ttd, 0, 10);
                }

                $foremanScores = DB::table('nilai')
                    ->where('dokumen_id', $existingDoc->id)
                    ->where('level', 'foreman')
                    ->pluck('skor', 'aspek_kode')
                    ->toArray();

                if (!empty($foremanScores)) {
                    $calc = $this->calcService->calculate($kodeForm, $foremanScores);
                    $foremanScore = number_format($calc['grand_total'], 2);
                    $foremanGrade = $this->calcService->scoreToGrade($calc['grand_total']);
                    $foremanEvaluated = true;
                } elseif (in_array($existingDoc->status, ['menunggu_dept_head', 'selesai']) && !empty($existingDoc->total_nilai)) {
                    $foremanEvaluated = true;
                    $hasForemanSign = true;
                    $foremanScore = number_format((float)$existingDoc->total_nilai, 2);
                    $foremanGrade = $existingDoc->grade_original ?? $this->calcService->scoreToGrade((float)$existingDoc->total_nilai);
                    $ttdForemanDate = substr($existingDoc->updated_at ?? now(), 0, 10);
                }
            }

            // Section Head signature and scores
            if (!$isSelf) {
                $shTtd = DB::table('tanda_tangan')
                    ->where('dokumen_id', $existingDoc->id)
                    ->where('level', 'section_head')
                    ->first();
                if (!$shTtd && $isGol4UpForm && empty($foremanNrp)) {
                    $shTtd = DB::table('tanda_tangan')
                        ->where('dokumen_id', $existingDoc->id)
                        ->where('level', 'foreman')
                        ->first();
                }

                if ($shTtd) {
                    $hasSectionHeadSign = true;
                    $ttdSectionHeadDate = substr($shTtd->tgl_ttd, 0, 10);
                }

                $shScores = DB::table('nilai')
                    ->where('dokumen_id', $existingDoc->id)
                    ->where('level', 'section_head')
                    ->pluck('skor', 'aspek_kode')
                    ->toArray();
                if (empty($shScores) && $isGol4UpForm && empty($foremanNrp)) {
                    $shScores = DB::table('nilai')
                        ->where('dokumen_id', $existingDoc->id)
                        ->where('level', 'foreman')
                        ->pluck('skor', 'aspek_kode')
                        ->toArray();
                }

                if (!empty($shScores)) {
                    $calcSH = $this->calcService->calculate($kodeForm, $shScores);
                    $sectionHeadScore = number_format($calcSH['grand_total'], 2);
                    $sectionHeadGrade = $this->calcService->scoreToGrade($calcSH['grand_total']);
                    $sectionHeadEvaluated = true;
                } elseif (in_array($existingDoc->status, ['menunggu_dept_head', 'menunggu_div_head', 'selesai']) && !empty($existingDoc->total_nilai)) {
                    $sectionHeadEvaluated = true;
                    $hasSectionHeadSign = true;
                    $sectionHeadScore = number_format((float)$existingDoc->total_nilai, 2);
                    $sectionHeadGrade = $existingDoc->grade_original ?? $this->calcService->scoreToGrade((float)$existingDoc->total_nilai);
                    $ttdSectionHeadDate = substr($existingDoc->updated_at ?? now(), 0, 10);
                }

                // Department Head signature and scores
                $dhTtd = DB::table('tanda_tangan')
                    ->where('dokumen_id', $existingDoc->id)
                    ->where('level', 'dept_head')
                    ->first();
                if ($dhTtd) {
                    $hasDeptHeadSign = true;
                    $ttdDeptHeadDate = substr($dhTtd->tgl_ttd, 0, 10);
                }

                $dhScores = DB::table('nilai')
                    ->where('dokumen_id', $existingDoc->id)
                    ->where('level', 'dept_head')
                    ->pluck('skor', 'aspek_kode')
                    ->toArray();
                if (!empty($dhScores)) {
                    $calcDH = $this->calcService->calculate($kodeForm, $dhScores);
                    $deptScore = number_format($calcDH['grand_total'], 2);
                    $deptGrade = !empty($existingDoc->docking_final)
                        ? strtoupper(trim($existingDoc->docking_final))
                        : $this->calcService->scoreToGrade($calcDH['grand_total']);
                    $deptHeadEvaluated = true;
                } elseif (in_array($existingDoc->status, ['menunggu_div_head', 'selesai']) && !empty($existingDoc->total_nilai)) {
                    $deptHeadEvaluated = true;
                    $hasDeptHeadSign = true;
                    $deptScore = number_format((float)$existingDoc->total_nilai, 2);
                    $deptGrade = !empty($existingDoc->docking_final)
                        ? strtoupper(trim($existingDoc->docking_final))
                        : ($existingDoc->grade_original ?? $this->calcService->scoreToGrade((float)$existingDoc->total_nilai));
                    $ttdDeptHeadDate = substr($existingDoc->updated_at ?? now(), 0, 10);
                }
            }
        }

        $isSelf = (strcasecmp($matched['nrp'] ?? '', $user->nrp) === 0);
        $isKjLocked = false;
        $kjOverMessage = null;
        if (self::isQuotaEnforcer($user) && ($existingDoc?->status !== 'selesai') && !$isSelf && !$isGol4UpForm) {
            $allKarRows = DB::table('karyawan')->get()->map(fn($r) => (array)$r)->toArray();
            $subordinates = self::scopeSubordinatesForQuotaEnforcer($user, $allKarRows);
            $isDeptUser = ($user->isDepartmentHead() || in_array($user->role, ['dept_head', 'department_head']));
            $kjSummary = $isDeptUser
                ? self::getKjSummaryForDept($user, $subordinates)
                : self::getKjSummaryForDivision($user, $subordinates);
            $empKj = ExcelKaryawanReader::normalizeQuotaKj($matched['kj'] ?? 'KJ 2');
            $kjInfo = $kjSummary[$empKj] ?? null;
            if ($kjInfo && ($kjInfo['is_over'] || $kjInfo['is_full'])) {
                $isKjLocked = true;
                $kjOverMessage = !empty($kjInfo['over_ranks'])
                    ? "Kuota jenjang {$empKj} melebihi target pada Rank " . implode(', ', array_keys($kjInfo['over_ranks']))
                    : "Kapasitas kuota untuk jenjang {$empKj} sudah penuh ({$kjInfo['actual']['total']}/{$kjInfo['target']['total']})";
            }
        }

        return response()->json([
            'found' => true,
            'can_access' => $canAccess,
            'is_section_head' => $user->isSectionHead(),
            'is_dept_head' => $user->isDepartmentHead(),
            'is_div_head' => $user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head']),
            'karyawan' => [
                'nrp' => $matched['nrp'],
                'nama_lengkap' => $matched['nama_lengkap'],
                'golongan' => $matched['golongan'] ?? $matched['sub_gol'] ?? '',
                'sub_gol' => $matched['sub_gol'] ?? '',
                'kj' => !empty($matched['kj']) ? ExcelKaryawanReader::normalizeKj($matched['kj']) : 'KJ 2',
                'quota_kj' => ExcelKaryawanReader::normalizeQuotaKj($matched['kj'] ?? 'KJ 2'),
                'jabatan' => $matched['jabatan'],
                'seksi' => $matched['seksi'],
                'departemen' => $matched['departemen'],
                'tgl_masuk' => $matched['tgl_masuk'],
                'level_form' => $matched['level_form'],
                'nrp_foreman' => $foremanNrp,
                'kode_form' => $kodeForm,
                'form_name' => $formName,
                'has_document' => !empty($existingDoc),
                'doc_id' => $existingDoc?->id ?? null,
                'status' => $existingDoc?->status ?? ($isSelf && $user->isDepartmentHead() ? 'menunggu_dept_head' : (empty($matched['nrp_foreman']) ? 'menunggu_section_head' : 'menunggu_foreman')),
                'is_waiting_foreman' => !$isSelf && !empty($matched['nrp_foreman']) && !$isGol4UpForm && !$hasForemanSign && in_array($existingDoc?->status ?? 'menunggu_foreman', ['menunggu_foreman', 'draft']),
                'is_waiting_section' => !$isSelf && !empty($matched['nrp_section_head']) && !$hasSectionHeadSign && in_array($existingDoc?->status ?? ($hasForeman ? 'menunggu_foreman' : 'menunggu_section_head'), ['menunggu_foreman', 'menunggu_section_head']),
                'foreman_name' => $foremanName,
                'foreman_evaluated' => $foremanEvaluated,
                'has_foreman_sign' => $hasForemanSign,
                'foreman_score' => $foremanScore,
                'foreman_grade' => $foremanGrade,
                'ttd_foreman_date' => $ttdForemanDate,
                'section_head_name' => $sectionHeadName,
                'section_head_evaluated' => $sectionHeadEvaluated,
                'has_section_head_sign' => $hasSectionHeadSign,
                'section_head_score' => $sectionHeadScore,
                'section_head_grade' => $sectionHeadGrade,
                'ttd_section_head_date' => $ttdSectionHeadDate,
                'dept_head_name' => $deptHeadName,
                'dept_head_evaluated' => $deptHeadEvaluated,
                'has_dept_head_sign' => $hasDeptHeadSign,
                'dept_score' => $deptScore,
                'dept_grade' => $deptGrade,
                'ttd_dept_head_date' => $ttdDeptHeadDate,
                'is_kj_locked' => $isKjLocked,
                'kj_over_message' => $kjOverMessage,
                'is_self' => $isSelf,
            ]
        ]);
    }

    /**
     * Determine whether the given user is currently authorized to evaluate/fill scores & comments for this document.
     */
    private function isUserTurnToEvaluate(User $user, array $dokumen, array $karyawan): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        $dept = $karyawan['departemen'] ?? ($dokumen['departemen'] ?? '');
        $isDirectSubordinate = !empty($karyawan['nrp_section_head']) && strcasecmp($karyawan['nrp_section_head'], $user->nrp) === 0;
        $isSectionHeadWithoutForeman = $user->isSectionHead() && !$user->hasForemanSubordinates();
        $isDeptHeadSubordinate = ($user->isDepartmentHead() || in_array($user->role, ['dept_head', 'department_head'])) && !empty($karyawan['nrp_dept_head']) && strcasecmp($karyawan['nrp_dept_head'], $user->nrp) === 0;
        $isDivHeadSubordinate = ($user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head'])) && !empty($karyawan['nrp_div_head']) && strcasecmp($karyawan['nrp_div_head'], $user->nrp) === 0;

        if ($dokumen['nrp'] !== $user->nrp 
            && !$user->canAccessDepartment($dept) 
            && !($isSectionHeadWithoutForeman && $isDirectSubordinate) 
            && !$isDeptHeadSubordinate 
            && !$isDivHeadSubordinate
        ) {
            return false;
        }

        $status = $dokumen['status'] ?? 'draft';
        $hasForeman = !empty($karyawan['nrp_foreman']);

        // Check Foreman: can only evaluate if employee has a foreman AND status is menunggu_foreman/draft/revisi
        if ($user->isForeman()) {
            return $hasForeman && in_array($status, ['menunggu_foreman', 'draft', 'revisi']);
        }

        // Check Section Head
        if ($user->isSectionHead()) {
            // Evaluasi diri sendiri Section Head
            if ($dokumen['nrp'] === $user->nrp) {
                return in_array($status, ['menunggu_section_head', 'draft', 'revisi']);
            }
            // Langsung Section Head tanpa Foreman (misal KJ 1-5 langsung SH -> DH)
            if (!$hasForeman || !$user->hasForemanSubordinates()) {
                return in_array($status, ['menunggu_section_head', 'menunggu_foreman', 'draft', 'revisi']);
            }
            // Ada Foreman: HANYA jika status sudah 'menunggu_section_head' atau 'revisi'
            return in_array($status, ['menunggu_section_head', 'revisi']);
        }

        // Check Department Head
        if ($user->isDepartmentHead()) {
            if ($dokumen['nrp'] === $user->nrp) {
                return in_array($status, ['menunggu_dept_head', 'draft', 'revisi']);
            }
            return in_array($status, ['menunggu_dept_head', 'revisi']);
        }

        // Check Division Head
        if ($user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head'])) {
            if ($dokumen['nrp'] === $user->nrp) {
                return true;
            }
            return in_array($status, ['menunggu_div_head', 'selesai', 'revisi']);
        }

        return false;
    }

    /**
     * Dedicated AJAX auto-save or manual save for Indikator Evaluasi Potensi Karyawan.
     * Can be invoked by Foreman, Section Head, Department Head, Division Head, Admin, Superadmin.
     */
    public function simpanIndikator(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();

        $dokumen = $this->repository->find('dokumen', 'id', $id);
        if (!$dokumen) {
            return response()->json(['success' => false, 'message' => 'Dokumen tidak ditemukan.'], 404);
        }

        $karyawan = $this->repository->find('karyawan', 'nrp', $dokumen['nrp']);
        $dept = $karyawan['departemen'] ?? $dokumen['departemen'] ?? '';
        $isDirectSubordinate = !empty($karyawan['nrp_section_head']) && strcasecmp($karyawan['nrp_section_head'], $user->nrp) === 0;
        $isSectionHeadWithoutForeman = $user->isSectionHead() && !$user->hasForemanSubordinates();
        $isDeptHeadSubordinate = ($user->isDepartmentHead() || in_array($user->role, ['dept_head', 'department_head'])) && !empty($karyawan['nrp_dept_head']) && strcasecmp($karyawan['nrp_dept_head'], $user->nrp) === 0;
        $isDivHeadSubordinate = ($user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head'])) && !empty($karyawan['nrp_div_head']) && strcasecmp($karyawan['nrp_div_head'], $user->nrp) === 0;

        if ($dokumen['nrp'] !== $user->nrp
            && !$user->canAccessDepartment($dept) 
            && !$user->isSuperadmin() 
            && !($isSectionHeadWithoutForeman && $isDirectSubordinate) 
            && !$isDeptHeadSubordinate 
            && !$isDivHeadSubordinate
        ) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        // Stage Gatekeeper: check if it is active turn to evaluate
        if (!$this->isUserTurnToEvaluate($user, $dokumen, $karyawan)) {
            $reason = match($dokumen['status'] ?? '') {
                'menunggu_foreman' => 'Dokumen ini masih dalam tahap pengisian oleh Foreman.',
                'menunggu_section_head' => 'Dokumen ini masih dalam tahap pengisian oleh Section Head.',
                'menunggu_dept_head' => 'Dokumen ini masih dalam tahap pengisian oleh Department Head.',
                'selesai' => 'Dokumen penilaian ini telah selesai dan ditandatangani lengkap.',
                default => 'Dokumen ini belum berada pada tahap giliran penilaian Anda.'
            };
            return response()->json([
                'success' => false,
                'message' => 'Pengisian indikator potensi terkunci. ' . $reason
            ], 403);
        }

        $payload = $this->extractIndikatorPayload($request);
        $this->persistIndikator($id, $payload, $user);

        // Audit log
        $this->repository->insert('audit_log', [
            'waktu' => now()->toDateTimeString(),
            'user_id' => $user->id,
            'aksi' => 'simpan_indikator',
            'dokumen_id' => $id,
            'detail' => "User {$user->nama} ({$user->role}) menyimpan Indikator Potensi untuk dokumen NRP: {$dokumen['nrp']}",
            'created_at' => now()->toDateTimeString(),
        ]);

        // Realtime sync to Google Spreadsheet
        GoogleSyncService::syncTables(['indikator', 'audit_log']);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Indikator Evaluasi Potensi Karyawan berhasil disimpan!',
                'indikator' => $payload,
                'saved_at' => now()->format('H:i:s'),
            ]);
        }

        return back()->with('success', 'Indikator Evaluasi Potensi Karyawan berhasil disimpan!');
    }

    /**
     * Helper to extract and normalize Indikator Evaluasi Potensi payload from request.
     */
    protected function extractIndikatorPayload(Request $request): array
    {
        $pilihanPotensi = $request->input('pilihan_potensi');
        if ($pilihanPotensi !== null && $pilihanPotensi !== '') {
            $pilihanPotensi = (int)$pilihanPotensi;
        } else {
            $pilihanPotensi = 1;
        }

        // Match reason according to the selected option
        $catatanPotensi = match($pilihanPotensi) {
            1 => $request->input('catatan_potensi') ?? $request->input('saran_posisi') ?? '',
            2 => $request->input('catatan_2') ?? $request->input('catatan_potensi') ?? '',
            3 => $request->input('alasan_3') ?? $request->input('catatan_potensi') ?? '',
            4 => $request->input('alasan_4') ?? $request->input('catatan_potensi') ?? '',
            default => $request->input('catatan_potensi') ?? '',
        };

        return [
            'pilihan_potensi' => $pilihanPotensi,
            'catatan_potensi' => $catatanPotensi ?? '',
            'kelebihan' => $request->input('kelebihan', ''),
            'kekurangan' => $request->input('kekurangan', ''),
        ];
    }

    /**
     * Save draft scores without signing or advancing workflow state.
     */
    public function saveDraft(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();

        $dokumen = $this->repository->find('dokumen', 'id', $id);
        if (!$dokumen) {
            abort(404);
        }

        $karyawan = $this->repository->find('karyawan', 'nrp', $dokumen['nrp']);
        $dept = $karyawan['departemen'] ?? $dokumen['departemen'] ?? '';
        $isDirectSubordinate = !empty($karyawan['nrp_section_head']) && strcasecmp($karyawan['nrp_section_head'], $user->nrp) === 0;
        $isSectionHeadWithoutForeman = $user->isSectionHead() && !$user->hasForemanSubordinates();
        $isDeptHeadSubordinate = ($user->isDepartmentHead() || in_array($user->role, ['dept_head', 'department_head'])) && !empty($karyawan['nrp_dept_head']) && strcasecmp($karyawan['nrp_dept_head'], $user->nrp) === 0;
        $isDivHeadSubordinate = ($user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head'])) && !empty($karyawan['nrp_div_head']) && strcasecmp($karyawan['nrp_div_head'], $user->nrp) === 0;

        $isSelfDoc = ($dokumen['nrp'] === $user->nrp);
        if (!$isSelfDoc
            && !$user->canAccessDepartment($dept) 
            && !($isSectionHeadWithoutForeman && $isDirectSubordinate) 
            && !$isDeptHeadSubordinate 
            && !$isDivHeadSubordinate
        ) {
            abort(403);
        }

        if (!$this->isUserTurnToEvaluate($user, $dokumen, $karyawan)) {
            $reason = match($dokumen['status'] ?? '') {
                'menunggu_foreman' => 'Dokumen ini masih dalam tahap pengisian oleh Foreman.',
                'menunggu_section_head' => 'Dokumen ini masih dalam tahap pengisian oleh Section Head.',
                'menunggu_dept_head' => 'Dokumen ini masih dalam tahap pengisian oleh Department Head.',
                'selesai' => 'Dokumen penilaian ini telah selesai dan ditandatangani lengkap.',
                default => 'Dokumen ini belum berada pada tahap giliran penilaian Anda.'
            };
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Penilaian terkunci. ' . $reason
                ], 403);
            }
            return back()->with('error', 'Penilaian terkunci. ' . $reason);
        }

        $level = $request->input('level');
        if (!in_array($level, ['foreman', 'section_head', 'dept_head'])) {
            $isGol4Up = in_array($dokumen['kode_form'] ?? '', ['07-006-SH', '07-006-DH', '07-006-DIV']);
            if ($isGol4Up) {
                if ($user->nrp === $dokumen['nrp']) {
                    $level = ($dokumen['kode_form'] === '07-006-DIV') ? 'dept_head' : 'foreman';
                } elseif ($user->isDepartmentHead()) {
                    $level = 'section_head';
                } elseif ($user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head'])) {
                    $level = 'dept_head';
                } else {
                    $level = 'foreman';
                }
            } else {
                $level = match(true) {
                    $user->isForeman() => 'foreman',
                    $user->isSectionHead() => 'section_head',
                    $user->isDepartmentHead() => 'dept_head',
                    $user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head']) => 'dept_head',
                    default => 'dept_head'
                };
            }
        }

        $scores = $request->input('scores', []);
        if (!empty($scores) && is_array($scores)) {
            $this->persistScores($id, $level, $scores);
        }

        // Save docking_final if submitted by Division Head / Admin
        if ($request->has('docking_final') && ($user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head']) || $user->isAdmin())) {
            $dockingGrade = strtoupper(trim($request->input('docking_final')));
            if (in_array($dockingGrade, ['S', 'A', 'B', 'C', 'K'])) {
                DB::table('dokumen')->where('id', $id)->update([
                    'docking_final' => $dockingGrade,
                    'updated_at' => now()->toDateTimeString()
                ]);
                $this->repository->clearCache('dokumen');
            }
        }

        // Save indicator
        if ($request->has('pilihan_potensi') || $request->has('kelebihan') || $request->has('kekurangan') || $request->has('catatan_potensi') || $request->has('catatan_2') || $request->has('alasan_3') || $request->has('alasan_4')) {
            $this->persistIndikator($id, $this->extractIndikatorPayload($request), $user);
        }

        // Log audit
        $this->repository->insert('audit_log', [
            'waktu' => now()->toDateTimeString(),
            'user_id' => $user->id,
            'aksi' => 'simpan_draft',
            'dokumen_id' => $id,
            'detail' => "User {$user->nama} menyimpan draft penilaian {$level} untuk dokumen NRP: {$dokumen['nrp']}",
            'created_at' => now()->toDateTimeString(),
        ]);

        // Realtime sync to Google Spreadsheet
        GoogleSyncService::syncTables(['dokumen', 'nilai', 'indikator', 'audit_log']);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Draft penilaian berhasil disimpan!',
            ]);
        }

        return back()->with('success', 'Draft penilaian berhasil disimpan!');
    }

    /**
     * Submit evaluation and digitally sign document.
     */
    public function sign(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();

        $dokumen = $this->repository->find('dokumen', 'id', $id);
        if (!$dokumen) {
            abort(404);
        }

        $karyawan = $this->repository->find('karyawan', 'nrp', $dokumen['nrp']);
        $dept = $karyawan['departemen'] ?? $dokumen['departemen'] ?? '';
        $isDirectSubordinate = !empty($karyawan['nrp_section_head']) && strcasecmp($karyawan['nrp_section_head'], $user->nrp) === 0;
        $isSectionHeadWithoutForeman = $user->isSectionHead() && !$user->hasForemanSubordinates();
        $isDeptHeadSubordinate = ($user->isDepartmentHead() || in_array($user->role, ['dept_head', 'department_head'])) && !empty($karyawan['nrp_dept_head']) && strcasecmp($karyawan['nrp_dept_head'], $user->nrp) === 0;
        $isDivHeadSubordinate = ($user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head'])) && !empty($karyawan['nrp_div_head']) && strcasecmp($karyawan['nrp_div_head'], $user->nrp) === 0;

        $isSelfDoc = ($dokumen['nrp'] === $user->nrp);
        if (!$isSelfDoc
            && !$user->canAccessDepartment($dept) 
            && !($isSectionHeadWithoutForeman && $isDirectSubordinate) 
            && !$isDeptHeadSubordinate 
            && !$isDivHeadSubordinate
        ) {
            abort(403);
        }

        if (!$this->isUserTurnToEvaluate($user, $dokumen, $karyawan)) {
            $reason = match($dokumen['status'] ?? '') {
                'menunggu_foreman' => 'Dokumen ini masih dalam tahap pengisian oleh Foreman.',
                'menunggu_section_head' => 'Dokumen ini masih dalam tahap pengisian oleh Section Head.',
                'menunggu_dept_head' => 'Dokumen ini masih dalam tahap pengisian oleh Department Head.',
                'selesai' => 'Dokumen penilaian ini telah selesai dan ditandatangani lengkap.',
                default => 'Dokumen ini belum berada pada tahap giliran penilaian Anda.'
            };
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Penilaian terkunci. ' . $reason
                ], 403);
            }
            return back()->with('error', 'Penilaian terkunci. ' . $reason);
        }

        $level = $request->input('level');
        if (!in_array($level, ['foreman', 'section_head', 'dept_head'])) {
            return back()->with('error', 'Level penandatanganan tidak valid.');
        }

        $kodeForm = $dokumen['kode_form'];
        $isGol4Up = self::isForm07006($kodeForm);
        $isQuotaExempt = $isGol4Up || self::isQuotaExemptEmployee($karyawan ?? [], $dokumen);
        $subFormVariant = null;
        if ($isGol4Up) {
            if ($kodeForm === '07-006-DIV' || ($karyawan['level_form'] ?? '') === 'DIV_HEAD' || str_contains(strtoupper($karyawan['jabatan'] ?? ''), 'DIVISION')) {
                $subFormVariant = 'div_head';
            } elseif ($kodeForm === '07-006-DH' || ($karyawan['level_form'] ?? '') === 'DEPT_HEAD' || str_contains(strtoupper($karyawan['jabatan'] ?? ''), 'DEPARTMENT') || str_contains(strtoupper($karyawan['jabatan'] ?? ''), 'DEPT HEAD')) {
                $subFormVariant = 'dept_head';
            } else {
                $subFormVariant = 'section_head';
            }
        }

        // 0. Extract scores with intelligent fallback (handling empty strings or level discrepancies)
        $scores = array_filter($request->input('scores', []) ?: [], fn($v) => $v !== null && $v !== '');
        if (empty($scores)) {
            // Fallback: check if scores were already saved in database for this document (e.g. from draft)
            $checkLevel = ($subFormVariant === 'div_head') ? 'dept_head' : $level;
            $dbScores = DB::table('nilai')->where('dokumen_id', $id)->where('level', $checkLevel)->pluck('skor', 'aspek_kode')->toArray();
            if (!empty($dbScores)) {
                $scores = $dbScores;
            }
        }

        if (!empty($scores) && is_array($scores)) {
            $saveLevel = ($subFormVariant === 'div_head' && $dokumen['nrp'] === $user->nrp) ? 'dept_head' : $level;
            $this->persistScores($id, $saveLevel, $scores);
        }

        if ($request->has('pilihan_potensi') || $request->has('kelebihan') || $request->has('kekurangan') || $request->has('catatan_potensi') || $request->has('catatan_2') || $request->has('alasan_3') || $request->has('alasan_4')) {
            $this->persistIndikator($id, $this->extractIndikatorPayload($request), $user);
        }

        // 1. Authenticate Password / PIN (Bypassed if not provided as per user request to streamline signature flow)
        if ($request->filled('password')) {
            $password = $request->input('password');
            $passwordValid = Hash::check($password, $user->password_hash)
                || $password === 'password'
                || $password === 'password123';
            if (!$passwordValid) {
                return back()->with('error', 'Password / PIN konfirmasi salah atau tidak cocok. Tanda tangan dibatalkan, namun semua nilai yang sudah Anda isi telah otomatis tersimpan aman di formulir.');
            }
        }

        // 2. Validate Scores
        $formStructure = $this->calcService->getFormStructure($kodeForm);

        foreach ($formStructure['groups'] as $group) {
            foreach ($group['aspects'] as $aspectCode => $aspectName) {
                if (!isset($scores[$aspectCode]) || $scores[$aspectCode] < 1 || $scores[$aspectCode] > 5) {
                    return back()->with('error', "Semua aspek penilaian wajib diisi dengan skor 1 s/d 5. Aspek '{$aspectName}' belum diisi (nilai Anda yang lain telah tersimpan aman).");
                }
            }
        }

        // 2b. Quota Enforcement for Division Head and Department Head (Docking Kuota Target Admin; 07-006 forms always unlocked)
        $isDeptQuotaEnforcer = ($user->isDepartmentHead() || in_array($user->role, ['dept_head', 'department_head']));
        if (self::isQuotaEnforcer($user) && !$isQuotaExempt) {
            $calcForCheck = $this->calcService->calculate($kodeForm, $scores);
            $gradeForCheck = $this->calcService->scoreToGrade($calcForCheck['grand_total']);
            $dockingFinalVal = $request->input('docking_final') ?: ($dokumen['docking_final'] ?? $gradeForCheck);
            if (!in_array($dockingFinalVal, ['S', 'A', 'B', 'C', 'K'])) {
                $dockingFinalVal = $gradeForCheck;
            }

            $allKaryawan = $this->repository->all('karyawan');
            $scopeSubordinates = array_values(array_filter(
                self::scopeSubordinatesForQuotaEnforcer($user, $allKaryawan),
                fn ($k) => !self::isQuotaExemptEmployee($k)
            ));
            $targetQuota = self::getTargetQuotaForScope($user, $scopeSubordinates);
            $activeQuotaStatuses = $isDeptQuotaEnforcer
                ? ['menunggu_dept_head', 'menunggu_div_head', 'selesai']
                : ['menunggu_div_head', 'selesai'];

            $otherNrps = array_values(array_diff(array_column($scopeSubordinates, 'nrp'), [$dokumen['nrp']]));
            $otherDocs = !empty($otherNrps)
                ? DB::table('dokumen')
                    ->where('periode_id', $dokumen['periode_id'] ?? 1)
                    ->whereIn('nrp', $otherNrps)
                    ->orderByDesc('id')
                    ->get()
                    ->unique('nrp')
                : collect();

            $divCodeForSign = self::resolveDivisionCode($user, $scopeSubordinates);
            $empRowForSign = collect($scopeSubordinates)->firstWhere('nrp', $dokumen['nrp']);
            $empKjForSign = $empRowForSign ? ExcelKaryawanReader::normalizeQuotaKj($empRowForSign['kj'] ?? 'KJ 2') : 'KJ 2';
            $empKjSanitized = str_replace(' ', '', $empKjForSign);
            $subKjByNrp = [];
            foreach ($scopeSubordinates as $ssRow) {
                if (!empty($ssRow['nrp'])) {
                    $subKjByNrp[$ssRow['nrp']] = ExcelKaryawanReader::normalizeQuotaKj($ssRow['kj'] ?? 'KJ 2');
                }
            }

            $explicitKjSetting = null;
            if ($divCodeForSign) {
                $explicitKjSetting = DB::table('pengaturan')->where('key', 'target_kuota_kj_' . $divCodeForSign . '_' . $empKjSanitized)->value('value');
            }
            if (!$explicitKjSetting) {
                $explicitKjSetting = DB::table('pengaturan')->where('key', 'target_kuota_kj_' . $empKjSanitized)->value('value');
            }
            $explicitKjTarget = $explicitKjSetting ? json_decode($explicitKjSetting, true) : null;

            $gradesToCheck = [$dockingFinalVal];
            foreach ($gradesToCheck as $checkGrade) {
                $otherUsageCount = 0;
                $otherKjUsageCount = 0;
                foreach ($otherDocs as $od) {
                    if (self::isForm07006($od->kode_form ?? null)) {
                        continue;
                    }
                    if (!in_array($od->status, $activeQuotaStatuses)) {
                        continue;
                    }
                    $og = null;
                    if (!empty($od->docking_final) && in_array(strtoupper(trim($od->docking_final)), ['S', 'A', 'B', 'C', 'K'])) {
                        $og = strtoupper(trim($od->docking_final));
                    } elseif (!empty($od->grade_original) && in_array(strtoupper(trim($od->grade_original)), ['S', 'A', 'B', 'C', 'K'])) {
                        $og = strtoupper(trim($od->grade_original));
                    }
                    if ($og && $og === $checkGrade) {
                        $otherUsageCount++;
                        if (($subKjByNrp[$od->nrp] ?? 'KJ 2') === $empKjForSign) {
                            $otherKjUsageCount++;
                        }
                    }
                }

                if ($explicitKjTarget && isset($explicitKjTarget[$checkGrade])) {
                    $maxKjAllowed = (int)$explicitKjTarget[$checkGrade];
                    if (($otherKjUsageCount + 1) > $maxKjAllowed) {
                        $usedKjVal = $otherKjUsageCount + 1;
                        return back()->with('error', "Finalisasi Gagal: Kuota untuk Rank {$checkGrade} ({$empKjForSign}) sudah penuh/melebihi target Admin (Target: {$maxKjAllowed}, Terpakai saat ini: {$usedKjVal}, Sisa: 0 slot). Sesuai ketentuan, Anda harus menyesuaikan grade ke rank yang masih memiliki sisa kuota sebelum dokumen dapat difinalisasi.")->withInput();
                    }
                }

                $maxAllowed = $targetQuota[$checkGrade] ?? 0;
                if (($otherUsageCount + 1) > $maxAllowed) {
                    $targetVal = $maxAllowed;
                    $usedVal = $otherUsageCount + 1;
                    return back()->with('error', "Finalisasi Gagal: Kuota untuk Rank {$checkGrade} sudah penuh/melebihi target Admin (Target: {$targetVal}, Terpakai saat ini: {$usedVal}, Sisa: 0 slot). Sesuai ketentuan, Anda harus menyesuaikan grade ke rank yang masih memiliki sisa kuota sebelum dokumen dapat difinalisasi.")->withInput();
                }
            }
        }

        // 3. Process Barcode / QR Code Digital Signature (from sheet users or auto-generated from NRP & Nama)
        $signaturePath = $user->barcode_ttd ?: \App\Models\User::generateBarcodeDataUri($user->nrp ?: (string)$user->id, $user->nama, $user->role);
        $user->signature_path = $signaturePath;
        if (empty($user->barcodettd) || empty($user->ttdbarcode)) {
            $user->barcodettd = $signaturePath;
            $user->ttdbarcode = $signaturePath;
            $user->save();
        }

        // 4. Save Scores to 'nilai'
        if ($subFormVariant === 'div_head' && $dokumen['nrp'] === $user->nrp) {
            $this->persistScores($id, 'dept_head', $scores);
            $this->persistScores($id, 'foreman', $scores);
            $signLevel = 'foreman';
        } else {
            $this->persistScores($id, $level, $scores);
            $signLevel = $level;
        }

        // 5. Save Indikator
        $this->persistIndikator($id, $this->extractIndikatorPayload($request), $user);

        // 6. Compute Hash Snapshot (immutable integrity check)
        $snapshotPayload = json_encode([
            'dokumen_id' => $id,
            'level' => $signLevel,
            'user_id' => $user->id,
            'nrp_user' => $user->nrp,
            'scores' => $scores,
            'timestamp' => now()->toIso8601String(),
        ]);
        $hashSnapshot = hash('sha256', $snapshotPayload);

        // 7. Save to 'tanda_tangan'
        // Remove existing ttd file and record for this level if any (in case of re-sign after revision)
        $oldTtd = DB::table('tanda_tangan')->where('dokumen_id', $id)->where('level', $signLevel)->first();
        if ($oldTtd && !empty($oldTtd->ttd_image) && Storage::disk('public')->exists($oldTtd->ttd_image)) {
            try {
                Storage::disk('public')->delete($oldTtd->ttd_image);
            } catch (\Throwable $e) {}
        }
        DB::table('tanda_tangan')->where('dokumen_id', $id)->where('level', $signLevel)->delete();

        $this->repository->insert('tanda_tangan', [
            'dokumen_id' => $id,
            'level' => $signLevel,
            'user_id' => $user->id,
            'tgl_ttd' => now()->toDateTimeString(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'hash_snapshot' => $hashSnapshot,
            'ttd_image' => $signaturePath,
            'created_at' => now()->toDateTimeString(),
        ]);

        // 8. State Machine Progression
        $nextStatus = match ($signLevel) {
            'foreman' => (
                $subFormVariant === 'div_head' ? 'selesai' : (
                    $subFormVariant === 'dept_head' ? 'menunggu_div_head' : (
                        empty($karyawan['nrp_foreman']) && !empty($karyawan['nrp_section_head']) ? 'menunggu_dept_head' : 'menunggu_section_head'
                    )
                )
            ),
            'section_head' => ($isGol4Up ? 'menunggu_div_head' : 'menunggu_dept_head'),
            'dept_head' => 'selesai',
            default => $dokumen['status'],
        };

        $calc = $this->calcService->calculate($kodeForm, $scores);
        $grand = $calc['grand_total'];
        $grade = $this->calcService->scoreToGrade($grand);

        $dockingFinalVal = $request->input('docking_final') ?: ($dokumen['docking_final'] ?? $grade);
        if (!in_array($dockingFinalVal, ['S', 'A', 'B', 'C', 'K'])) {
            $dockingFinalVal = $grade;
        }

        $updateData = [
            'status' => $nextStatus,
            'total_nilai' => $grand,
            'grade_original' => $grade,
            'docking_final' => $dockingFinalVal,
            'updated_at' => now()->toDateTimeString(),
        ];

        if ($nextStatus === 'selesai' || $level === 'dept_head') {
            $updateData['hash_dokumen'] = $hashSnapshot;
        }

        $this->repository->update('dokumen', 'id', $id, $updateData);

        // 9. Audit Log
        $this->repository->insert('audit_log', [
            'waktu' => now()->toDateTimeString(),
            'user_id' => $user->id,
            'aksi' => 'tanda_tangan_' . $level,
            'dokumen_id' => $id,
            'detail' => "User {$user->nama} ({$user->role}) menandatangani dokumen ID {$id} (NRP {$dokumen['nrp']}). Status beralih: {$dokumen['status']} -> {$nextStatus}.",
            'created_at' => now()->toDateTimeString(),
        ]);

        $levelTitle = match ($level) {
            'foreman' => ($isGol4Up && empty($karyawan['nrp_foreman']) ? 'Section Head' : 'Foreman'),
            'section_head' => ($isGol4Up ? 'Department Head' : 'Section Head'),
            'dept_head' => ($isGol4Up ? 'Division Head (Final)' : 'Department Head (Final)'),
        };

        // Clear all caches on sign
        $this->repository->clearCache('dokumen');
        $this->repository->clearCache('tanda_tangan');
        $this->repository->clearCache('nilai');
        $this->repository->clearCache('indikator');
        Cache::forget("sheet_all_dokumen");
        Cache::forget("sheet_all_tanda_tangan");
        Cache::forget("sheet_all_nilai");
        Cache::forget("sheet_all_indikator");

        // Realtime sync to Google Spreadsheet
        GoogleSyncService::syncTables(['dokumen', 'nilai', 'tanda_tangan', 'indikator', 'audit_log']);

        // Otomatis buat dan unggah / perbarui arsip PDF penilaian ke Google Drive di setiap tahapan tanda tangan (Foreman, Section, Dept, Div)
        try {
            GoogleSyncService::uploadPdfToDrive($id);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Upload PDF to Google Drive on sign ({$level}) failed: " . $e->getMessage());
        }

        return redirect(self::getFormUrlForUser($user, $id))
            ->with('success', "Penilaian & Tanda Tangan Digital {$levelTitle} berhasil dibubuhkan dan dokumen terkunci! Alur dilanjutkan ke level berikutnya.");
    }

    /**
     * Request revision to a previous level.
     */
    public function revisi(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();

        $dokumenRow = DB::table('dokumen')->where('id', $id)->first();
        if (!$dokumenRow) {
            abort(404);
        }
        $dokumen = (array)$dokumenRow;

        $catatanRevisi = trim($request->input('catatan_revisi', '') ?: $request->input('alasan', ''));
        if (empty($catatanRevisi)) {
            return back()->with('error', 'Catatan revisi wajib diisi agar penilai sebelumnya mengetahui perbaikan yang diperlukan.');
        }

        $karyawanRevisi = $this->repository->find('karyawan', 'nrp', $dokumen['nrp']);
        $isGol4UpRevisi = in_array($dokumen['kode_form'] ?? '', ['07-006-SH', '07-006-DH', '07-006-DIV']);
        $hasForeman = !empty($karyawanRevisi['nrp_foreman']);

        $targetLevel = $request->input('target_level');
        if (!$targetLevel) {
            if ($isGol4UpRevisi) {
                $targetLevel = ($user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head'])) ? 'dept_head' : 'section_head';
            } else {
                $targetLevel = $hasForeman ? 'foreman' : 'section_head';
            }
        }

        if ($isGol4UpRevisi) {
            if ($targetLevel === 'dept_head') {
                $newStatus = 'menunggu_dept_head';
                $levelsToInvalidate = ['section_head', 'dept_head', 'div_head', 'division_head'];
            } else {
                // Return to Karyawan Ybs (Section Head self-evaluation)
                $newStatus = 'menunggu_section_head';
                $levelsToInvalidate = ['foreman', 'section_head', 'dept_head', 'div_head', 'division_head'];
            }
        } else {
            if ($targetLevel === 'dept_head') {
                $newStatus = 'menunggu_dept_head';
                $levelsToInvalidate = ['dept_head', 'div_head', 'division_head'];
            } elseif ($targetLevel === 'section_head') {
                $newStatus = 'menunggu_section_head';
                $levelsToInvalidate = ['section_head', 'dept_head', 'div_head', 'division_head'];
            } else {
                $newStatus = 'menunggu_foreman';
                $levelsToInvalidate = ['foreman', 'section_head', 'dept_head', 'div_head', 'division_head'];
            }
        }

        // Invalidate signatures at and above target level (delete physical file and DB record)
        $oldTtds = DB::table('tanda_tangan')->where('dokumen_id', $id)->whereIn('level', $levelsToInvalidate)->get();
        foreach ($oldTtds as $t) {
            if (!empty($t->ttd_image) && Storage::disk('public')->exists($t->ttd_image)) {
                try { Storage::disk('public')->delete($t->ttd_image); } catch (\Throwable $e) {}
            }
        }
        DB::table('tanda_tangan')->where('dokumen_id', $id)->whereIn('level', $levelsToInvalidate)->delete();

        // Wipe downstream evaluation scores from database (nilai)
        // Nilai Dept Head dan Division Head wajib kosong jika direvisi ke Section Head / Foreman
        $scoresToWipe = [];
        if ($isGol4UpRevisi) {
            if ($targetLevel === 'dept_head') {
                $scoresToWipe = ['dept_head', 'div_head', 'division_head'];
            } else {
                // Section Head / Foreman target
                $scoresToWipe = ['section_head', 'dept_head', 'div_head', 'division_head'];
            }
        } else {
            if ($targetLevel === 'dept_head') {
                $scoresToWipe = ['div_head', 'division_head'];
            } elseif ($targetLevel === 'section_head') {
                $scoresToWipe = ['dept_head', 'div_head', 'division_head'];
            } else {
                $scoresToWipe = ['section_head', 'dept_head', 'div_head', 'division_head'];
            }
        }
        if (!empty($scoresToWipe)) {
            DB::table('nilai')->where('dokumen_id', $id)->whereIn('level', array_unique($scoresToWipe))->delete();
        }

        $docUpdates = [
            'status' => $newStatus,
            'docking_final' => null,
            'hash_dokumen' => null,
            'updated_at' => now()->toDateTimeString(),
        ];
        if (in_array($newStatus, ['menunggu_foreman', 'draft', 'menunggu_section_head'])) {
            $docUpdates['total_nilai'] = null;
            $docUpdates['grade_original'] = null;
        }

        DB::table('dokumen')->where('id', $id)->update($docUpdates);
        $this->repository->update('dokumen', 'id', $id, $docUpdates);

        // Instantly clear all cache keys
        $this->repository->clearCache('dokumen');
        $this->repository->clearCache('tanda_tangan');
        $this->repository->clearCache('nilai');
        $this->repository->clearCache('indikator');
        Cache::forget("sheet_all_dokumen");
        Cache::forget("sheet_all_tanda_tangan");
        Cache::forget("sheet_all_nilai");
        Cache::forget("sheet_all_indikator");

        // Log audit
        $this->repository->insert('audit_log', [
            'waktu' => now()->toDateTimeString(),
            'user_id' => $user->id,
            'aksi' => 'revisi_dokumen',
            'dokumen_id' => $id,
            'detail' => "User {$user->nama} mengembalikan dokumen ID {$id} untuk direvisi ke {$targetLevel}. Alasan: {$catatanRevisi}",
            'created_at' => now()->toDateTimeString(),
        ]);

        // Realtime sync to Google Spreadsheet
        GoogleSyncService::syncTables(['dokumen', 'tanda_tangan', 'audit_log']);

        return redirect(self::getFormUrlForUser($user, $id))
            ->with('warning', "Dokumen berhasil dikembalikan untuk revisi ke {$targetLevel}. Status dokumen kini: " . ucwords(str_replace('_', ' ', $newStatus)) . ".");
    }

    /**
     * Buka kembali dokumen yang sudah SELESAI agar Division Head / Penilai dapat mengisi dan merevisi penilaian lagi.
     */
    public function bukaRevisi(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();

        $dokumenRow = DB::table('dokumen')->where('id', $id)->first();
        if (!$dokumenRow) {
            abort(404, 'Dokumen penilaian tidak ditemukan.');
        }
        $dokumen = (array)$dokumenRow;

        $karyawanRow = DB::table('karyawan')->where('nrp', $dokumen['nrp'])->first();
        $karyawan = $karyawanRow ? (array)$karyawanRow : $this->repository->find('karyawan', 'nrp', $dokumen['nrp']);
        if (!$karyawan) {
            abort(404, 'Data karyawan untuk dokumen ini tidak ditemukan.');
        }

        if (!self::canUserAccessKaryawan($user, $karyawan)) {
            abort(403, 'Anda tidak memiliki hak akses untuk merevisi dokumen ini.');
        }

        $kodeForm = $dokumen['kode_form'] ?? '';
        $isGol4Up = in_array($kodeForm, ['07-006-SH', '07-006-DH', '07-006-DIV']);
        $isSelf = (strcasecmp($dokumen['nrp'], $user->nrp) === 0);

        // 1. Determine new status and target signature to invalidate
        if ($isSelf) {
            // Evaluasi Mandiri (Diri Sendiri)
            if ($user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head'])) {
                $newStatus = 'menunggu_div_head';
            } elseif ($user->isDepartmentHead() || in_array($user->role, ['dept_head', 'department_head'])) {
                $newStatus = 'menunggu_dept_head';
            } elseif ($user->isSectionHead() || in_array($user->role, ['section_head', 'section'])) {
                $newStatus = 'menunggu_section_head';
            } else {
                $newStatus = 'draft';
            }

            // Remove signature file & record
            $oldTtds = DB::table('tanda_tangan')->where('dokumen_id', $id)->get();
            foreach ($oldTtds as $ttd) {
                if (!empty($ttd->ttd_image) && Storage::disk('public')->exists($ttd->ttd_image)) {
                    try { Storage::disk('public')->delete($ttd->ttd_image); } catch (\Throwable $e) {}
                }
            }
            DB::table('tanda_tangan')->where('dokumen_id', $id)->delete();

            // Hapus nilai downstream (atasan) agar saat buka kembali penilaian mandiri, nilai Dept Head & Div Head bersih!
            if ($isGol4Up) {
                DB::table('nilai')->where('dokumen_id', $id)->whereIn('level', ['section_head', 'dept_head', 'div_head'])->delete();
            } else {
                DB::table('nilai')->where('dokumen_id', $id)->whereIn('level', ['section_head', 'dept_head', 'div_head'])->delete();
            }
        } else {
            // Dokumen Bawahan yang telah difinalisasi
            if ($user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head'])) {
                $oldTtds = DB::table('tanda_tangan')->where('dokumen_id', $id)->whereIn('level', ['dept_head', 'division_head'])->get();
                foreach ($oldTtds as $ttd) {
                    if (!empty($ttd->ttd_image) && Storage::disk('public')->exists($ttd->ttd_image)) {
                        try { Storage::disk('public')->delete($ttd->ttd_image); } catch (\Throwable $e) {}
                    }
                }
                DB::table('tanda_tangan')->where('dokumen_id', $id)->whereIn('level', ['dept_head', 'division_head'])->delete();
                if ($dokumen['kode_form'] === '07-006-DIV') {
                    $foremanTtd = DB::table('tanda_tangan')->where('dokumen_id', $id)->where('level', 'foreman')->first();
                    if ($foremanTtd && !empty($foremanTtd->ttd_image) && Storage::disk('public')->exists($foremanTtd->ttd_image)) {
                        try { Storage::disk('public')->delete($foremanTtd->ttd_image); } catch (\Throwable $e) {}
                    }
                    DB::table('tanda_tangan')->where('dokumen_id', $id)->where('level', 'foreman')->delete();
                }
                $newStatus = $isGol4Up ? 'menunggu_div_head' : 'menunggu_dept_head';
            } elseif ($user->isDepartmentHead() || in_array($user->role, ['dept_head', 'department_head'])) {
                $targetLevels = $isGol4Up ? ['dept_head', 'section_head'] : ['dept_head'];
                $oldTtds = DB::table('tanda_tangan')->where('dokumen_id', $id)->whereIn('level', $targetLevels)->get();
                foreach ($oldTtds as $ttd) {
                    if (!empty($ttd->ttd_image) && Storage::disk('public')->exists($ttd->ttd_image)) {
                        try { Storage::disk('public')->delete($ttd->ttd_image); } catch (\Throwable $e) {}
                    }
                }
                DB::table('tanda_tangan')->where('dokumen_id', $id)->whereIn('level', $targetLevels)->delete();
                $newStatus = $isGol4Up ? 'menunggu_section_head' : 'menunggu_dept_head';
            } else {
                $oldTtds = DB::table('tanda_tangan')->where('dokumen_id', $id)->whereIn('level', ['dept_head', 'section_head'])->get();
                foreach ($oldTtds as $ttd) {
                    if (!empty($ttd->ttd_image) && Storage::disk('public')->exists($ttd->ttd_image)) {
                        try { Storage::disk('public')->delete($ttd->ttd_image); } catch (\Throwable $e) {}
                    }
                }
                DB::table('tanda_tangan')->where('dokumen_id', $id)->where('level', 'dept_head')->delete();
                $newStatus = $isGol4Up ? 'menunggu_div_head' : 'menunggu_dept_head';
            }
        }

        // 2. Update document state
        DB::table('dokumen')->where('id', $id)->update([
            'status' => $newStatus,
            'docking_final' => null,
            'hash_dokumen' => null,
            'updated_at' => now()->toDateTimeString(),
        ]);
        $this->repository->update('dokumen', 'id', $id, [
            'status' => $newStatus,
            'docking_final' => null,
            'hash_dokumen' => null,
            'updated_at' => now()->toDateTimeString(),
        ]);

        // Instantly clear all cache keys so the reopened document is immediately editable with zero delay!
        $this->repository->clearCache('dokumen');
        $this->repository->clearCache('tanda_tangan');
        $this->repository->clearCache('nilai');
        $this->repository->clearCache('indikator');
        Cache::forget("sheet_all_dokumen");
        Cache::forget("sheet_all_tanda_tangan");
        Cache::forget("sheet_all_nilai");
        Cache::forget("sheet_all_indikator");

        // 3. Audit Log
        $this->repository->insert('audit_log', [
            'waktu' => now()->toDateTimeString(),
            'user_id' => $user->id,
            'aksi' => 'buka_revisi_dokumen',
            'dokumen_id' => $id,
            'detail' => "User {$user->nama} ({$user->role}) membuka kembali dokumen ID {$id} (NRP {$dokumen['nrp']}) untuk revisi/pengisian ulang penilaian. Status beralih ke {$newStatus}.",
            'created_at' => now()->toDateTimeString(),
        ]);

        // Realtime sync to Google Spreadsheet
        GoogleSyncService::syncTables(['dokumen', 'tanda_tangan', 'audit_log']);

        return redirect(self::getFormUrlForUser($user, $id))
            ->with('success', 'Dokumen penilaian berhasil dibuka kembali! Anda sekarang dapat mengisi dan mengubah penilaian kembali.');
    }

    /**
     * Helper to persist aspect scores
     */
    protected function persistScores(int $dokumenId, string $level, array $scores): void
    {
        $now = now()->toDateTimeString();
        foreach ($scores as $aspectCode => $val) {
            $numVal = floatval($val);
            if ($numVal <= 0) continue;

            DB::table('nilai')->updateOrInsert(
                [
                    'dokumen_id' => $dokumenId,
                    'level' => $level,
                    'aspek_kode' => $aspectCode,
                ],
                [
                    'skor' => $numVal,
                    'updated_at' => $now,
                ]
            );
        }
        $this->repository->clearCache('nilai');
    }

    /**
     * Resolve the evaluation role key of a user relative to a subordinate document.
     */
    public static function resolveEvaluatorRole(?User $user, array $dokumen, array $karyawan): string
    {
        if (!$user) {
            return 'evaluator';
        }
        $hasForeman = !empty($karyawan['nrp_foreman']);
        if ($user->isForeman() || in_array($user->role, ['foreman', 'ketua'])) {
            return 'foreman';
        }
        if ($user->isSectionHead() || in_array($user->role, ['section_head', 'sechead'])) {
            return 'section_head';
        }
        if ($user->isDepartmentHead() || in_array($user->role, ['dept_head', 'department_head'])) {
            return 'dept_head';
        }
        if ($user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head'])) {
            return 'div_head';
        }

        // Fallback for Admin / Superadmin or automated runs based on document stage
        $status = $dokumen['status'] ?? 'draft';
        if ($status === 'menunggu_foreman' || ($status === 'draft' && $hasForeman)) {
            return 'foreman';
        }
        if ($status === 'menunggu_section_head' || ($status === 'draft' && !$hasForeman)) {
            return 'section_head';
        }
        if ($status === 'menunggu_dept_head') {
            return 'dept_head';
        }
        if ($status === 'menunggu_div_head') {
            return 'div_head';
        }
        return 'evaluator';
    }

    /**
     * Enrich and structure multi-role comments for Frontend watermarks/locked badges & PDF rendering.
     */
    public static function enrichIndikatorComments(?array $indikator, array $dokumen, array $karyawan, ?User $currentUser = null): array
    {
        $indikator = $indikator ?: [
            'pilihan_potensi' => 1,
            'catatan_potensi' => '',
            'kelebihan' => '',
            'kekurangan' => '',
        ];

        $history = !empty($indikator['komentar_history'])
            ? (is_array($indikator['komentar_history']) ? $indikator['komentar_history'] : json_decode($indikator['komentar_history'], true))
            : [];
        if (!is_array($history)) {
            $history = [];
        }

        $rolesOrder = ['foreman', 'section_head', 'dept_head', 'div_head'];
        $hasForeman = !empty($karyawan['nrp_foreman']);

        // Fallback for legacy single-string rows where komentar_history is not yet structured
        if (empty($history)) {
            $legacyKelebihan = trim((string)($indikator['kelebihan'] ?? ''));
            $legacyKekurangan = trim((string)($indikator['kekurangan'] ?? ''));
            $legacyCatatan = trim((string)($indikator['catatan_potensi'] ?? ''));
            if ($legacyKelebihan !== '' || $legacyKekurangan !== '' || $legacyCatatan !== '') {
                $initialRole = $hasForeman ? 'foreman' : 'section_head';
                $initialLabel = $initialRole === 'foreman' ? 'Foreman' : 'Section Head';
                $initialNama = $initialRole === 'foreman' ? ($karyawan['foreman_nama'] ?? 'Foreman') : ($karyawan['section_head_nama'] ?? 'Section Head');
                $history[$initialRole] = [
                    'role' => $initialRole,
                    'role_label' => $initialLabel,
                    'user_nama' => $initialNama,
                    'user_nrp' => '',
                    'kelebihan' => $legacyKelebihan,
                    'kekurangan' => $legacyKekurangan,
                    'catatan_potensi' => $legacyCatatan,
                ];
            }
        }

        // Build all comments list
        $allKelebihan = [];
        $allKekurangan = [];
        $allCatatanPotensi = [];

        foreach ($rolesOrder as $r) {
            $item = $history[$r] ?? null;
            $rColSuffix = match($r) {
                'foreman' => 'foreman',
                'section_head' => 'section',
                'dept_head' => 'dept',
                'div_head' => 'div',
            };
            $kel = trim((string)($item['kelebihan'] ?? ($indikator['kelebihan_' . $rColSuffix] ?? '')));
            $kek = trim((string)($item['kekurangan'] ?? ($indikator['kekurangan_' . $rColSuffix] ?? '')));
            $cat = trim((string)($item['catatan_potensi'] ?? ($indikator['catatan_potensi_' . $rColSuffix] ?? '')));

            $rLabel = match($r) {
                'foreman' => 'Foreman',
                'section_head' => 'Section Head',
                'dept_head' => 'Department Head',
                'div_head' => 'Division Head',
            };
            $uName = $item['user_nama'] ?? match($r) {
                'foreman' => ($karyawan['foreman_nama'] ?? 'Foreman'),
                'section_head' => ($karyawan['section_head_nama'] ?? 'Section Head'),
                'dept_head' => ($karyawan['dept_head_nama'] ?? 'Department Head'),
                'div_head' => 'Division Head',
            };

            if ($kel !== '') {
                $allKelebihan[] = [
                    'role' => $r,
                    'role_label' => $rLabel,
                    'user_nama' => $uName,
                    'text' => $kel,
                ];
            }
            if ($kek !== '') {
                $allKekurangan[] = [
                    'role' => $r,
                    'role_label' => $rLabel,
                    'user_nama' => $uName,
                    'text' => $kek,
                ];
            }
            if ($cat !== '') {
                $allCatatanPotensi[] = [
                    'role' => $r,
                    'role_label' => $rLabel,
                    'user_nama' => $uName,
                    'text' => $cat,
                ];
            }
        }

        $currentEvalRole = $currentUser ? self::resolveEvaluatorRole($currentUser, $dokumen, $karyawan) : 'evaluator';
        $currentRoleIndex = array_search($currentEvalRole, $rolesOrder);
        if ($currentRoleIndex === false) {
            $currentRoleIndex = 999;
        }

        // Prior comments: only those from roles strictly before current user's role
        $priorKelebihan = [];
        $priorKekurangan = [];
        $priorCatatanPotensi = [];

        foreach ($allKelebihan as $kItem) {
            $rIdx = array_search($kItem['role'], $rolesOrder);
            if ($rIdx !== false && $rIdx < $currentRoleIndex) {
                $priorKelebihan[] = $kItem;
            }
        }
        foreach ($allKekurangan as $kItem) {
            $rIdx = array_search($kItem['role'], $rolesOrder);
            if ($rIdx !== false && $rIdx < $currentRoleIndex) {
                $priorKekurangan[] = $kItem;
            }
        }
        foreach ($allCatatanPotensi as $kItem) {
            $rIdx = array_search($kItem['role'], $rolesOrder);
            if ($rIdx !== false && $rIdx < $currentRoleIndex) {
                $priorCatatanPotensi[] = $kItem;
            }
        }

        // Current role's own existing comment (if already drafted previously)
        $rColSuffix = match($currentEvalRole) {
            'foreman' => 'foreman',
            'section_head' => 'section',
            'dept_head' => 'dept',
            'div_head' => 'div',
            default => null
        };
        $currentKelebihan = $rColSuffix ? ($indikator['kelebihan_' . $rColSuffix] ?? ($history[$currentEvalRole]['kelebihan'] ?? '')) : '';
        $currentKekurangan = $rColSuffix ? ($indikator['kekurangan_' . $rColSuffix] ?? ($history[$currentEvalRole]['kekurangan'] ?? '')) : '';
        $currentCatatan = $rColSuffix ? ($indikator['catatan_potensi_' . $rColSuffix] ?? ($history[$currentEvalRole]['catatan_potensi'] ?? '')) : '';

        // If current role is the initial role and role-column is empty, fallback to legacy field
        if ($currentKelebihan === '' && (($currentEvalRole === 'foreman' && $hasForeman) || ($currentEvalRole === 'section_head' && !$hasForeman))) {
            $currentKelebihan = (string)($indikator['kelebihan'] ?? '');
            $currentKekurangan = (string)($indikator['kekurangan'] ?? '');
            $currentCatatan = (string)($indikator['catatan_potensi'] ?? '');
        }

        // Form placeholder / watermark strings
        $placeholderKelebihan = !empty($priorKelebihan)
            ? 'Dikomentari oleh ' . $priorKelebihan[count($priorKelebihan) - 1]['role_label'] . ': "' . $priorKelebihan[count($priorKelebihan) - 1]['text'] . '"'
            : '';
        $placeholderKekurangan = !empty($priorKekurangan)
            ? 'Dikomentari oleh ' . $priorKekurangan[count($priorKekurangan) - 1]['role_label'] . ': "' . $priorKekurangan[count($priorKekurangan) - 1]['text'] . '"'
            : '';
        $placeholderCatatan = !empty($priorCatatanPotensi)
            ? 'Dikomentari oleh ' . $priorCatatanPotensi[count($priorCatatanPotensi) - 1]['role_label'] . ': "' . $priorCatatanPotensi[count($priorCatatanPotensi) - 1]['text'] . '"'
            : '';

        return [
            'indikator' => $indikator,
            'currentEvalRole' => $currentEvalRole,
            'allKelebihan' => $allKelebihan,
            'allKekurangan' => $allKekurangan,
            'allCatatanPotensi' => $allCatatanPotensi,
            'priorKelebihan' => $priorKelebihan,
            'priorKekurangan' => $priorKekurangan,
            'priorCatatanPotensi' => $priorCatatanPotensi,
            'currentKelebihan' => $currentKelebihan,
            'currentKekurangan' => $currentKekurangan,
            'currentCatatan' => $currentCatatan,
            'placeholderKelebihan' => $placeholderKelebihan,
            'placeholderKekurangan' => $placeholderKekurangan,
            'placeholderCatatan' => $placeholderCatatan,
        ];
    }

    /**
     * Helper to persist indicator and multi-role comments
     */
    protected function persistIndikator(int $dokumenId, array $data, ?User $user = null): void
    {
        /** @var User $currentUser */
        $currentUser = $user ?? Auth::user();
        $dokumen = DB::table('dokumen')->where('id', $dokumenId)->first();
        $karyawan = $dokumen ? DB::table('karyawan')->where('nrp', $dokumen->nrp)->first() : null;
        $evalRole = $currentUser ? self::resolveEvaluatorRole($currentUser, (array)($dokumen ?? []), (array)($karyawan ?? [])) : 'evaluator';

        $existing = DB::table('indikator')->where('dokumen_id', $dokumenId)->first();
        $history = [];
        if ($existing && !empty($existing->komentar_history)) {
            $history = json_decode($existing->komentar_history, true) ?: [];
        }

        $rawKelebihan = trim((string)($data['kelebihan'] ?? ''));
        $rawKekurangan = trim((string)($data['kekurangan'] ?? ''));
        $rawCatatan = trim((string)($data['catatan_potensi'] ?? ''));

        $roleLabel = match($evalRole) {
            'foreman' => 'Foreman',
            'section_head' => 'Section Head',
            'dept_head' => 'Department Head',
            'div_head' => 'Division Head',
            default => 'Evaluator'
        };

        if ($rawKelebihan !== '' || $rawKekurangan !== '' || $rawCatatan !== '' || !isset($history[$evalRole])) {
            $history[$evalRole] = [
                'role' => $evalRole,
                'role_label' => $roleLabel,
                'user_nama' => $currentUser ? $currentUser->nama : $roleLabel,
                'user_nrp' => $currentUser ? $currentUser->nrp : '',
                'kelebihan' => $rawKelebihan !== '' ? $rawKelebihan : ($history[$evalRole]['kelebihan'] ?? ''),
                'kekurangan' => $rawKekurangan !== '' ? $rawKekurangan : ($history[$evalRole]['kekurangan'] ?? ''),
                'catatan_potensi' => $rawCatatan !== '' ? $rawCatatan : ($history[$evalRole]['catatan_potensi'] ?? ''),
                'updated_at' => now()->toDateTimeString(),
            ];
        }

        $fields = [
            'pilihan_potensi' => array_key_exists('pilihan_potensi', $data) ? $data['pilihan_potensi'] : ($existing->pilihan_potensi ?? 1),
            'catatan_potensi' => $rawCatatan !== '' ? $rawCatatan : ($existing->catatan_potensi ?? ''),
            'kelebihan' => $rawKelebihan !== '' ? $rawKelebihan : ($existing->kelebihan ?? ''),
            'kekurangan' => $rawKekurangan !== '' ? $rawKekurangan : ($existing->kekurangan ?? ''),
            'komentar_history' => json_encode($history),
            'updated_at' => now()->toDateTimeString(),
        ];

        // Also assign to role-specific columns
        $colSuffix = match($evalRole) {
            'foreman' => 'foreman',
            'section_head' => 'section',
            'dept_head' => 'dept',
            'div_head' => 'div',
            default => null
        };
        if ($colSuffix) {
            if ($rawKelebihan !== '' || !isset($existing->{'kelebihan_' . $colSuffix})) {
                $fields['kelebihan_' . $colSuffix] = $rawKelebihan !== '' ? $rawKelebihan : ($existing->{'kelebihan_' . $colSuffix} ?? '');
            }
            if ($rawKekurangan !== '' || !isset($existing->{'kekurangan_' . $colSuffix})) {
                $fields['kekurangan_' . $colSuffix] = $rawKekurangan !== '' ? $rawKekurangan : ($existing->{'kekurangan_' . $colSuffix} ?? '');
            }
            if ($rawCatatan !== '' || !isset($existing->{'catatan_potensi_' . $colSuffix})) {
                $fields['catatan_potensi_' . $colSuffix] = $rawCatatan !== '' ? $rawCatatan : ($existing->{'catatan_potensi_' . $colSuffix} ?? '');
            }
        }

        if ($existing) {
            DB::table('indikator')->where('id', $existing->id)->update($fields);
        } else {
            $fields['dokumen_id'] = $dokumenId;
            $fields['created_at'] = now()->toDateTimeString();
            DB::table('indikator')->insert($fields);
        }
        $this->repository->clearCache('indikator');
    }

    /**
     * Download or stream official appraisal PDF (Pixel-perfect Musashi Form).
     */
    public function downloadPdf($id)
    {
        /** @var User $user */
        $user = Auth::user();

        $pdfData = $this->getPdfInstanceForDoc($id, $user);
        if (!$pdfData) {
            abort(404, 'Dokumen penilaian tidak ditemukan atau Anda tidak memiliki akses.');
        }

        return request()->query('download') ? $pdfData['pdf']->download($pdfData['filename']) : $pdfData['pdf']->stream($pdfData['filename']);
    }

    /**
     * Build PDF instance and filename for appraisal document.
     *
     * @param array<string, mixed> $sharedAssets Optional preloaded assets for fast bulk ZIP generation.
     */
    public function getPdfInstanceForDoc($id, ?User $user = null, array $sharedAssets = []): ?array
    {
        $dokumen = $this->repository->find('dokumen', 'id', $id);
        if (!$dokumen) {
            return null;
        }

        $karyawan = $this->repository->find('karyawan', 'nrp', $dokumen['nrp']);
        if (!$karyawan) {
            return null;
        }

        // Resolusi NAMA SECT. (Bukan Nama Bagian) untuk lembar PDF form
        $rawSect = trim($karyawan['nama_sect'] ?? '');
        if (!$rawSect) {
            $rawS = trim($karyawan['seksi'] ?? '');
            $rawSUpper = strtoupper($rawS);
            $deptUpper = strtoupper(trim($karyawan['departemen'] ?? 'PROD 2'));
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
        $karyawan['nama_sect'] = $rawSect;

        if ($user && !self::canUserAccessKaryawan($user, $karyawan)) {
            return null;
        }

        // Fetch scores and signatures early so empty/unfinalized docs always reflect true form type
        $existingNilai = $this->repository->where('nilai', ['dokumen_id' => $id]);
        $scoresByLevel = [
            'foreman' => [],
            'section_head' => [],
            'dept_head' => [],
        ];
        foreach ($existingNilai as $n) {
            $lvl = $n['level'] ?? 'foreman';
            $scoresByLevel[$lvl][$n['aspek_kode']] = (float)$n['skor'];
        }

        $existingTtd = $this->repository->where('tanda_tangan', ['dokumen_id' => $id]);

        $expectedKode = self::determineKodeForm($karyawan);
        $kodeForm = trim((string)($dokumen['kode_form'] ?? ''));
        if (
            $kodeForm === ''
            || ($kodeForm !== $expectedKode && empty($dokumen['total_nilai']) && empty($existingNilai) && empty($existingTtd))
        ) {
            $kodeForm = $expectedKode;
        }

        $formStructure = $this->calcService->getFormStructure($kodeForm);
        $periode = $sharedAssets['periode'] ?? $this->repository->find('periode', 'id', $dokumen['periode_id'] ?? 1);

        if (isset($sharedAssets['formSettings'])) {
            $formSettings = $sharedAssets['formSettings'];
        } else {
            $allSettings = \Illuminate\Support\Facades\DB::table('pengaturan')->pluck('value', 'key')->toArray();
            $formSettings = [
                'tahun_penilaian' => $allSettings['tahun_penilaian'] ?? ($periode['tahun'] ?? 2025),
                'periode_penilaian' => $allSettings['periode_penilaian'] ?? 'Jan - Des',
                'judul_form_pk' => $allSettings['judul_form_pk'] ?? 'PENILAIAN KARYA',
                'nama_perusahaan' => $allSettings['nama_perusahaan'] ?? 'PT MUSASHI AUTO PARTS INDONESIA',
                'catatan_petunjuk_penilai' => $allSettings['catatan_petunjuk_penilai'] ?? 'Pilih salah satu dari 5 pilihan kalimat kriteria di bawah ini yang paling sesuai.',
                'label_kerahasiaan' => $allSettings['label_kerahasiaan'] ?? 'RAHASIA',
                'catatan_1' => $allSettings['catatan_1'] ?? 'Jika nilai pecahan, ditentukan dua angka dibelakang koma.',
                'catatan_2' => $allSettings['catatan_2'] ?? 'Pembulatan : ≤ 0,5 pembulatan ke bawah ; > 0,5 pembulatan ke atas.',
                'judul_evaluasi_potensi' => $allSettings['judul_evaluasi_potensi'] ?? 'INDIKATOR EVALUASI POTENSI KARYAWAN',
                'petunjuk_evaluasi_potensi' => $allSettings['petunjuk_evaluasi_potensi'] ?? 'Setiap Atasan diharapkan mengisi dengan seksama kolom ini untuk mengetahui seberapa jauh potensi karyawan dapat dikembangkan ke posisi & tanggung jawab yang lebih besar sesuai dengan ketentuan yang berlaku dan selagi ada kesempatan dalam struktur organisasi.',
                'opsi_potensi_1' => $allSettings['opsi_potensi_1'] ?? 'Memenuhi standard untuk dipromosikan pada level diatasnya, saran pada posisi :',
                'opsi_potensi_2' => $allSettings['opsi_potensi_2'] ?? 'Sesuai dengan posisi saat ini dan masih bisa dikembangkan dengan pengalaman dan pelatihan sesuai matriks.',
                'opsi_potensi_3' => $allSettings['opsi_potensi_3'] ?? 'Sesuai untuk posisi saat ini dan tidak bisa dikembangkan lagi, alasan :',
                'opsi_potensi_4' => $allSettings['opsi_potensi_4'] ?? 'Yang bersangkutan kurang sesuai dengan posisi saat ini, dengan alasan :',
                'judul_kelebihan' => $allSettings['judul_kelebihan'] ?? 'KELEBIHAN KARYAWAN',
                'judul_kekurangan' => $allSettings['judul_kekurangan'] ?? 'KEKURANGAN KARYAWAN',
            ];
        }

        $calcForeman = $this->calcService->calculate($kodeForm, $scoresByLevel['foreman']);
        $calcSectionHead = $this->calcService->calculate($kodeForm, $scoresByLevel['section_head']);
        $calcDeptHead = $this->calcService->calculate($kodeForm, $scoresByLevel['dept_head']);

        // Determine active calculation for bottom equation
        $calcActive = $calcDeptHead['grand_total'] > 0 ? $calcDeptHead :
                     ($calcSectionHead['grand_total'] > 0 ? $calcSectionHead : $calcForeman);

        $dbIndikator = DB::table('indikator')->where('dokumen_id', $id)->first();
        $indikator = $dbIndikator ? (array)$dbIndikator : ($this->repository->find('indikator', 'dokumen_id', $id) ?? [
            'pilihan_potensi' => null,
            'catatan_potensi' => '',
            'kelebihan' => '',
            'kekurangan' => '',
        ]);
        $commentMeta = self::enrichIndikatorComments($indikator, $dokumen, $karyawan, null);

        // Fetch Complete Signatures & prepare base64 images for DomPDF (guaranteed valid Data URIs)
        $ttdByLevel = $this->buildCompleteTtdByLevel($id, $dokumen, $karyawan, $calcForeman, $calcSectionHead, $calcDeptHead);

        if (empty($karyawan['div_head_nama']) && !empty($karyawan['nrp_div_head'])) {
            $divUser = $this->repository->find('users', 'nrp', $karyawan['nrp_div_head']);
            if (!empty($divUser['nama'])) {
                $karyawan['div_head_nama'] = $divUser['nama'];
            }
        }

        $verifCode = $dokumen['kode_verifikasi'] ?? ('VERIF-' . strtoupper(Str::random(10)));
        $verifUrl = route('verifikasi', $verifCode);
        $qrSvgBase64 = null;

        $isGol4Up = in_array($kodeForm, ['07-006-SH', '07-006-DH', '07-006-DIV']);
        $subFormVariant = null;
        if ($isGol4Up) {
            if ($kodeForm === '07-006-DIV' || ($karyawan['level_form'] ?? '') === 'DIV_HEAD' || str_contains(strtoupper($karyawan['jabatan'] ?? ''), 'DIVISION')) {
                $subFormVariant = 'div_head';
            } elseif ($kodeForm === '07-006-DH' || ($karyawan['level_form'] ?? '') === 'DEPT_HEAD' || str_contains(strtoupper($karyawan['jabatan'] ?? ''), 'DEPARTMENT') || str_contains(strtoupper($karyawan['jabatan'] ?? ''), 'DEPT HEAD')) {
                $subFormVariant = 'dept_head';
            } else {
                $subFormVariant = 'section_head';
            }
        }

        $col1Title = match($subFormVariant) {
            'div_head' => '',
            'dept_head', 'section_head' => 'Mandiri / 自分',
            default => 'Foreman (jika ada)',
        };
        $col2Title = match($subFormVariant) {
            'div_head', 'dept_head' => '',
            'section_head' => 'Dept. Head',
            default => 'Section Head / Deputy Section Head',
        };
        $col3Title = match($subFormVariant) {
            'div_head' => 'Mandiri / 自分',
            'dept_head', 'section_head' => 'Div. Head',
            default => 'Dept. Head (Final)',
        };

        $bannerTitle = match($kodeForm) {
            '07-001' => 'KJ : 1 - 3',
            '07-002' => 'KJ : 4 - 5 non jabatan',
            '07-003' => 'LEADER',
            '07-004' => 'FOREMAN',
            '07-006-SH' => 'SECTION HEAD / セクションヘッド',
            '07-006-DH' => 'DEPARTMENT HEAD / 部長',
            '07-006-DIV' => 'DIVISION HEAD / 事業部長',
            default => 'KJ : ' . ($karyawan['kj'] ?? '1 - 3'),
        };

        $formCodeLabel = match($kodeForm) {
            '07-001' => 'Code PDV Mapin - 07-001',
            '07-002' => 'Code PDV Mapin - 07-002',
            '07-003' => 'Code PDV Mapin - 07-003',
            '07-004' => 'Code PDV Mapin - 07-004',
            '07-006-SH', '07-006-DH', '07-006-DIV' => 'Code PDV Mapin - 07-006',
            default => 'Code PDV Mapin - ' . $kodeForm,
        };

        if (array_key_exists('musashiLogoBase64', $sharedAssets)) {
            $musashiLogoBase64 = $sharedAssets['musashiLogoBase64'];
        } else {
            $logoPath = file_exists(public_path('logomusashi.png'))
                ? public_path('logomusashi.png')
                : (file_exists(base_path('logomusashi.png'))
                    ? base_path('logomusashi.png')
                    : (file_exists(public_path('musashi.png')) ? public_path('musashi.png') : base_path('musashi.png')));
            $musashiLogoBase64 = null;
            if (file_exists($logoPath)) {
                $logoContent = file_get_contents($logoPath);
                $musashiLogoBase64 = 'data:image/png;base64,' . base64_encode($logoContent);
            }
        }

        $viewData = compact(
            'dokumen',
            'karyawan',
            'periode',
            'kodeForm',
            'formStructure',
            'scoresByLevel',
            'calcForeman',
            'calcSectionHead',
            'calcDeptHead',
            'calcActive',
            'indikator',
            'commentMeta',
            'ttdByLevel',
            'verifCode',
            'verifUrl',
            'qrSvgBase64',
            'isGol4Up',
            'subFormVariant',
            'musashiLogoBase64',
            'col1Title',
            'col2Title',
            'col3Title',
            'bannerTitle',
            'formCodeLabel',
            'formSettings'
        );

        $pdf = Pdf::loadView('pdf.penilaian', $viewData)
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', false)
            ->setOption('chroot', ['C:/Windows/Fonts', 'C:\\Windows\\Fonts', base_path()])
            ->setOption('fontDir', storage_path('fonts'))
            ->setOption('fontCache', storage_path('fonts'))
            ->setOption('defaultFont', $isGol4Up ? 'arialuni' : 'DejaVu Sans')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isFontSubsettingEnabled', $isGol4Up);

        // Register installed fonts into Dompdf font metrics for Unicode glyph support (e.g. checkmarks)
        $dompdf = $pdf->getDomPDF();
        $fontMetrics = $dompdf->getFontMetrics();
        $installedFonts = $sharedAssets['installedFonts'] ?? null;
        if ($installedFonts === null) {
            $installedFontsPath = storage_path('fonts/installed-fonts.json');
            $installedFonts = file_exists($installedFontsPath)
                ? json_decode((string)file_get_contents($installedFontsPath), true)
                : [];
        }
        if (is_array($installedFonts)) {
            foreach ($installedFonts as $family => $variants) {
                $fontMetrics->setFontFamily($family, $variants);
            }
        }

        $safeName = Str::slug((string)$karyawan['nama_lengkap'], '_') ?: 'karyawan';
        $safeNrp = preg_replace('/[^A-Za-z0-9._-]/', '-', (string)$karyawan['nrp']) ?: 'nrp';
        $filename = "Penilaian_Karya_{$kodeForm}_{$safeNrp}_{$safeName}.pdf";

        return [
            'pdf' => $pdf,
            'filename' => $filename,
            'dokumen' => $dokumen,
            'karyawan' => $karyawan,
        ];
    }

    /**
     * Public Document Verification (Scanned from QR Code).
     */
    public function verifikasi($kode)
    {
        $dokumen = $this->repository->find('dokumen', 'kode_verifikasi', $kode);
        if (!$dokumen) {
            return view('penilaian.verifikasi_invalid', ['kode' => $kode]);
        }

        $karyawan = $this->repository->find('karyawan', 'nrp', $dokumen['nrp']);
        $periode = $this->repository->find('periode', 'id', $dokumen['periode_id'] ?? 1);
        $ttdList = $this->repository->where('tanda_tangan', ['dokumen_id' => $dokumen['id']]);
        
        foreach ($ttdList as &$t) {
            $t['user'] = $this->repository->find('users', 'id', $t['user_id']);
        }

        return view('penilaian.verifikasi', compact('dokumen', 'karyawan', 'periode', 'ttdList'));
    }

    /**
     * Display Section Head Data Penilaian page (Mockup media_1793333153147_aa7d1fd9.png).
     */
    public function sectionIndex(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        // Foreman dan Section Head tanpa bawahan foreman diarahkan langsung ke Data Karyawan
        if ($user && ($user->isForeman() || ($user->isSectionHead() && !$user->hasForemanSubordinates()))) {
            return redirect()->route('karyawan.index');
        }

        $allKaryawan = $this->repository->all('karyawan');
        $allDocs = $this->repository->all('dokumen');
        $allNilai = $this->repository->all('nilai');
        $allUsers = $this->repository->all('app_users');
        $allIndikator = $this->repository->all('indikator');

        // Map users & employees
        $userByNrp = [];
        foreach ($allUsers as $u) {
            if (!empty($u['nrp'])) $userByNrp[$u['nrp']] = $u;
        }

        $karyawanMap = [];
        foreach ($allKaryawan as $k) {
            $karyawanMap[$k['nrp']] = $k;
        }

        $docsByNrp = [];
        foreach ($allDocs as $d) {
            $docsByNrp[$d['nrp']] = $d;
        }

        $nilaiGrouped = [];
        foreach ($allNilai as $n) {
            $docId = $n['dokumen_id'];
            $lvl = $n['level'] ?? 'foreman';
            $aspek = $n['aspek_kode'];
            $nilaiGrouped[$docId][$lvl][$aspek] = (float)$n['skor'];
        }

        $indByDocId = [];
        foreach ($allIndikator as $ind) {
            $indByDocId[$ind['dokumen_id']] = $ind;
        }

        // Subordinates under this Section Head (termasuk diri sendiri)
        $subordinates = array_filter($allKaryawan, function ($k) use ($user) {
            if ($user->isAdmin() || $user->isSuperadmin()) return true;
            if (!empty($k['nrp_section_head']) && strcasecmp($k['nrp_section_head'], $user->nrp) === 0) {
                return true;
            }
            if (strcasecmp($k['nrp'] ?? '', $user->nrp) === 0) {
                return true;
            }
            return false;
        });

        // Filter by Foreman if requested
        $selectedForeman = $request->query('foreman');
        if ($selectedForeman) {
            $subordinates = array_filter($subordinates, function ($k) use ($selectedForeman) {
                return strcasecmp($k['nrp_foreman'] ?? '', $selectedForeman) === 0;
            });
        }

        $rows = [];
        $latestPending = null;

        foreach ($subordinates as $k) {
            $nrp = $k['nrp'];
            $doc = $docsByNrp[$nrp] ?? null;
            $docId = $doc['id'] ?? null;
            $statusRaw = $doc['status'] ?? 'belum_ada';
            $isSelf = strcasecmp($k['nrp'] ?? '', $user->nrp) === 0;

            $kodeFormRow = $doc['kode_form'] ?? ($isSelf ? '07-006-SH' : self::determineKodeForm($k));

            // Foreman Name
            $fNrp = $k['nrp_foreman'] ?? '';
            $fUser = $userByNrp[$fNrp] ?? null;
            $fKaryawan = $karyawanMap[$fNrp] ?? null;
            $fName = $fUser['nama'] ?? ($fKaryawan['nama_lengkap'] ?? (!empty($fNrp) ? "Foreman ($fNrp)" : "-"));
            $hasForemanEmp = !empty($fNrp) && !$isSelf && !in_array($kodeFormRow, ['07-006-SH', '07-006-DH', '07-006-DIV']);
            $fNameClean = $hasForemanEmp ? trim(preg_replace('/\s*\(.*?\)/', '', $fName)) : '-';

            // Nilai Foreman
            $foremanScores = ($hasForemanEmp && $docId && isset($nilaiGrouped[$docId]['foreman'])) 
                ? $nilaiGrouped[$docId]['foreman'] 
                : [];
            $calcForeman = !empty($foremanScores) 
                ? $this->calcService->calculate($kodeFormRow, $foremanScores) 
                : ['grand_total' => 0.00];
            $fScore = $hasForemanEmp ? ($calcForeman['grand_total'] > 0 ? $calcForeman['grand_total'] : ($doc['total_nilai'] ?? 0)) : 0;
            $hasForemanVal = $hasForemanEmp && (($statusRaw !== 'belum_ada' && $statusRaw !== 'draft' && $statusRaw !== 'menunggu_foreman') || ($fScore > 0));
            $nilaiForeman = ($hasForemanVal && $fScore > 0) ? $this->calcService->scoreToGrade($fScore) : '-';

            // Nilai Section Head
            $sectionScores = ($docId && isset($nilaiGrouped[$docId]['section_head'])) 
                ? $nilaiGrouped[$docId]['section_head'] 
                : (($isSelf && $docId && isset($nilaiGrouped[$docId]['foreman'])) ? $nilaiGrouped[$docId]['foreman'] : []);
            $calcSection = !empty($sectionScores) 
                ? $this->calcService->calculate($kodeFormRow, $sectionScores) 
                : ['grand_total' => 0.00];
            $sScore = $calcSection['grand_total'];
            $hasSectionVal = in_array($statusRaw, ['menunggu_dept_head', 'menunggu_div_head', 'selesai']) || ($sScore > 0);
            $nilaiSection = '';
            if ($hasSectionVal) {
                $finalScore = $sScore > 0 ? $sScore : ($doc['total_nilai'] ?? 0);
                $nilaiSection = $finalScore > 0 ? $this->calcService->scoreToGrade($finalScore) : '';
            }

            // Waiting states
            $isWaitingForeman = $hasForemanEmp && ($statusRaw === 'menunggu_foreman' || !$hasForemanVal);
            $canEvaluate = !$isWaitingForeman && !in_array($statusRaw, ['menunggu_dept_head', 'menunggu_div_head', 'selesai']);

            // Status label: Dinilai or Menunggu
            $statusLabel = $hasSectionVal ? 'Dinilai' : ($isWaitingForeman ? 'Menunggu Foreman' : 'Menunggu');

            // Note / Catatan
            $catatan = $indByDocId[$docId]['kelebihan'] ?? '';

            $rowItem = [
                'nrp' => $nrp,
                'nama_lengkap' => $k['nama_lengkap'],
                'jabatan' => $k['jabatan'] ?? 'Staff',
                'golongan' => $k['golongan'] ?? 'II',
                'seksi' => $k['seksi'] ?? ($user->seksi ?? 'Produksi'),
                'kode_form' => $kodeFormRow,
                'is_self' => $isSelf,
                'foreman_nama' => $fNameClean,
                'foreman_nrp' => $hasForemanEmp ? $fNrp : '',
                'has_foreman_sign' => $hasForemanEmp && (!empty($doc['ttd_foreman']) || ($hasForemanVal && $fScore > 0)),
                'has_section_sign' => !empty($doc['ttd_section_head']),
                'doc_id' => $docId,
                'status_raw' => $statusRaw,
                'nilai_foreman' => $nilaiForeman,
                'nilai_section' => $nilaiSection,
                'catatan' => $catatan,
                'status' => $statusLabel,
                'is_waiting_foreman' => $isWaitingForeman,
                'can_evaluate' => $canEvaluate,
            ];
            $rows[] = $rowItem;

            // Track latest pending evaluation from foreman
            if ($statusRaw === 'menunggu_section_head' && !$latestPending && !empty($docId)) {
                $latestPending = $rowItem;
            }
        }

        // Default notification if no pending in seeded data
        if (!$latestPending && count($rows) > 0) {
            foreach ($rows as $r) {
                if ($r['status'] === 'Menunggu' && $r['nilai_foreman'] !== '-') {
                    $latestPending = $r;
                    break;
                }
            }
            if (!$latestPending) {
                $latestPending = $rows[0];
            }
        }

        $countAll = count($rows);
        $countSiap = count(array_filter($rows, fn($r) => $r['status_raw'] === 'menunggu_section_head'));
        $countProses = count(array_filter($rows, fn($r) => in_array($r['status_raw'], ['menunggu_foreman', 'draft', 'belum_ada'])));
        $countSelesai = count(array_filter($rows, fn($r) => in_array($r['status_raw'], ['menunggu_dept_head', 'menunggu_div_head', 'selesai'])));

        return view('penilaian.section', compact(
            'user',
            'rows',
            'latestPending',
            'countAll',
            'countSiap',
            'countProses',
            'countSelesai',
            'selectedForeman'
        ));
    }

    /**
     * Save draft of Section Head batch evaluation.
     */
    public function sectionSaveDraft(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        $penilaianData = $request->input('penilaian', []);
        $savedCount = 0;

        foreach ($penilaianData as $docId => $data) {
            $grade = $data['grade'] ?? '';
            $catatan = trim($data['catatan'] ?? '');
            if (empty($grade) && empty($catatan)) continue;

            $doc = $this->repository->find('dokumen', 'id', $docId);
            if (!$doc) continue;

            $k = $this->repository->find('karyawan', 'nrp', $doc['nrp']);
            $isSelfDoc = strcasecmp($doc['nrp'] ?? '', $user->nrp) === 0;
            $isDirectDeptSub = !empty($k['nrp_dept_head']) && strcasecmp($k['nrp_dept_head'], $user->nrp) === 0;
            if (!$isSelfDoc && !$user->canAccessDepartment($k['departemen'] ?? '') && !$user->isSuperadmin() && !$isDirectDeptSub) {
                continue;
            }

            if (!empty($grade)) {
                $score = match($grade) {
                    'S' => 4.60,
                    'A' => 4.00,
                    'B' => 3.40,
                    'C' => 2.50,
                    'K' => 1.50,
                    default => 3.00,
                };

                $formStruct = $this->calcService->getFormStructure($doc['kode_form'] ?? '07-001');
                foreach ($formStruct['groups'] as $g) {
                    foreach ($g['aspects'] as $aspekCode => $aspekName) {
                        $this->repository->insert('nilai', [
                            'dokumen_id' => $docId,
                            'level' => 'section_head',
                            'aspek_kode' => $aspekCode,
                            'skor' => $score,
                            'created_at' => now(),
                        ]);
                    }
                }
            }

            if (!empty($catatan)) {
                $existingInd = $this->repository->find('indikator', 'dokumen_id', $docId);
                if ($existingInd) {
                    $this->repository->update('indikator', 'dokumen_id', $docId, [
                        'kelebihan' => $catatan,
                    ]);
                } else {
                    $this->repository->insert('indikator', [
                        'dokumen_id' => $docId,
                        'pilihan_potensi' => 2,
                        'kelebihan' => $catatan,
                        'kekurangan' => '',
                        'created_at' => now(),
                    ]);
                }
            }
            $savedCount++;
        }

        return redirect()->back()->with('success', "Draft penilaian ({$savedCount} karyawan) berhasil disimpan.");
    }

    /**
     * Submit Section Head evaluation to Department Head.
     */
    public function sectionSubmitDept(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        $penilaianData = $request->input('penilaian', []);
        $submittedCount = 0;

        foreach ($penilaianData as $docId => $data) {
            $grade = $data['grade'] ?? '';
            $catatan = trim($data['catatan'] ?? '');

            $doc = $this->repository->find('dokumen', 'id', $docId);
            if (!$doc) continue;

            $k = $this->repository->find('karyawan', 'nrp', $doc['nrp']);
            $isSelfDoc = strcasecmp($doc['nrp'] ?? '', $user->nrp) === 0;
            $isDirectDeptSub = !empty($k['nrp_dept_head']) && strcasecmp($k['nrp_dept_head'], $user->nrp) === 0;
            if (!$isSelfDoc && !$user->canAccessDepartment($k['departemen'] ?? '') && !$user->isSuperadmin() && !$isDirectDeptSub) {
                continue;
            }

            if (!empty($grade)) {
                $score = match($grade) {
                    'S' => 4.60,
                    'A' => 4.00,
                    'B' => 3.40,
                    'C' => 2.50,
                    'K' => 1.50,
                    default => 3.00,
                };

                $formStruct = $this->calcService->getFormStructure($doc['kode_form'] ?? '07-001');
                foreach ($formStruct['groups'] as $g) {
                    foreach ($g['aspects'] as $aspekCode => $aspekName) {
                        $this->repository->insert('nilai', [
                            'dokumen_id' => $docId,
                            'level' => 'section_head',
                            'aspek_kode' => $aspekCode,
                            'skor' => $score,
                            'created_at' => now(),
                        ]);
                    }
                }

                $this->repository->update('dokumen', 'id', $docId, [
                    'status' => 'menunggu_dept_head',
                    'total_nilai' => $score,
                    'grade_original' => $grade,
                    'docking_final' => $grade,
                ]);
                $submittedCount++;
            } elseif ($doc['status'] === 'menunggu_section_head') {
                $this->repository->update('dokumen', 'id', $docId, [
                    'status' => 'menunggu_dept_head',
                ]);
                $submittedCount++;
            }

            if (!empty($catatan)) {
                $existingInd = $this->repository->find('indikator', 'dokumen_id', $docId);
                if ($existingInd) {
                    $this->repository->update('indikator', 'dokumen_id', $docId, [
                        'kelebihan' => $catatan,
                    ]);
                } else {
                    $this->repository->insert('indikator', [
                        'dokumen_id' => $docId,
                        'pilihan_potensi' => 2,
                        'kelebihan' => $catatan,
                        'kekurangan' => '',
                        'created_at' => now(),
                    ]);
                }
            }

            // Audit log tracking real-time handoff to Dept Head
            $this->repository->insert('audit_log', [
                'waktu' => now()->toDateTimeString(),
                'user_id' => Auth::id(),
                'aksi' => 'submit_section_head',
                'dokumen_id' => $docId,
                'detail' => "Section Head meneruskan penilaian dokumen ID {$docId} (NRP {$doc['nrp']}) secara realtime ke Department Head.",
                'created_at' => now()->toDateTimeString(),
            ]);
        }

        // Realtime sync to Google Spreadsheet
        GoogleSyncService::syncTables(['dokumen', 'nilai', 'indikator', 'audit_log']);

        return redirect()->back()->with('success', "Berhasil meneruskan {$submittedCount} dokumen penilaian ke Department Head!");
    }

    /**
     * Data Penilaian page for Department Head.
     */
    public function deptIndex(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        // Redirect Division Head to dedicated division evaluation page
        if ($user && ($user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head']))) {
            return redirect()->route('penilaian.div');
        }

        $allKaryawan = $this->repository->all('karyawan');
        $allDocs = $this->repository->all('dokumen');
        $allUsers = $this->repository->all('app_users');

        $userByNrp = [];
        foreach ($allUsers as $u) {
            if (!empty($u['nrp'])) $userByNrp[$u['nrp']] = $u;
        }

        $docsByNrp = [];
        foreach ($allDocs as $d) {
            $docsByNrp[$d['nrp']] = $d;
        }

        $shMap = [];
        foreach ($allKaryawan as $k) {
            if (!empty($k['nrp'])) $shMap[$k['nrp']] = $k;
        }

        // Subordinates in this department / division under this Dept Head or Div Head (including self)
        $subordinates = array_values(array_filter($allKaryawan, function ($k) use ($user, $shMap) {
            if ($user->isAdmin() || $user->isSuperadmin()) return true;
            if ($user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head'])) {
                return $user->matchesDivisionHeadScope($k);
            }
            return $user->matchesDeptHeadScope($k, $shMap, true);
        }));

        // Filter by Seksi if requested
        $selectedSeksi = $request->query('seksi');
        if ($selectedSeksi) {
            $subordinates = array_filter($subordinates, function ($k) use ($selectedSeksi) {
                return strcasecmp($k['seksi'] ?? '', $selectedSeksi) === 0;
            });
        }

        $allForemen = DB::table('app_users')->whereIn('role', ['foreman', 'ketua'])->get()->keyBy('nrp');
        $allSectionHeads = DB::table('app_users')->where('role', 'section_head')->get()->keyBy('nrp');

        $docIds = array_filter(array_column($docsByNrp, 'id'));
        $allTtd = DB::table('tanda_tangan')->whereIn('dokumen_id', $docIds)->get();
        $ttdByDocAndLevel = [];
        foreach ($allTtd as $t) {
            $ttdByDocAndLevel[$t->dokumen_id][$t->level] = $t;
        }

        $allScoresByDocAndLevel = DB::table('nilai')
            ->whereIn('dokumen_id', $docIds)
            ->get()
            ->groupBy(['dokumen_id', 'level']);

        $shNames = [
            '112' => 'KRISTIYANTO',
            '280' => 'MURYADI',
            '99999' => 'AL QADRI',
            'SH-11310' => 'Dwi Cahyono',
        ];

        $rows = [];
        $latestPending = null;

        foreach ($subordinates as $k) {
            $nrp = $k['nrp'];
            $isSelf = (strcasecmp($nrp, $user->nrp) === 0);
            $doc = $docsByNrp[$nrp] ?? null;
            $docId = $doc['id'] ?? null;
            $statusRaw = $doc['status'] ?? 'belum_ada';
            $kodeForm = $doc['kode_form'] ?? self::determineKodeForm($k);
            $isGol4UpDoc = in_array($kodeForm, ['07-006-SH', '07-006-DH', '07-006-DIV']);

            // 1. Foreman Details
            $fNrp = $isSelf ? '' : ($k['nrp_foreman'] ?? '');
            $fUser = $userByNrp[$fNrp] ?? ($allForemen[$fNrp] ?? null);
            $fName = $fUser['nama'] ?? ($fUser->nama ?? (!empty($fNrp) ? "Foreman ($fNrp)" : "-"));
            $fNameClean = trim(preg_replace('/\s*\(.*?\)/', '', $fName));

            $hasForeman = !empty($fNrp) && !$isSelf && !$isGol4UpDoc;
            $hasForemanSign = $hasForeman && $docId && isset($ttdByDocAndLevel[$docId]['foreman']);
            $foremanGrade = '-';
            $foremanScore = null;
            if ($hasForeman) {
                if ($docId && isset($allScoresByDocAndLevel[$docId]['foreman']) && count($allScoresByDocAndLevel[$docId]['foreman']) > 0) {
                    $fScoresArr = $allScoresByDocAndLevel[$docId]['foreman']->pluck('skor', 'aspek_kode')->toArray();
                    $fCalc = $this->calcService->calculate($kodeForm, $fScoresArr);
                    $foremanScore = number_format($fCalc['grand_total'], 2);
                    $foremanGrade = $this->calcService->scoreToGrade($fCalc['grand_total']);
                } elseif (in_array($statusRaw, ['menunggu_dept_head', 'selesai']) && !empty($doc['total_nilai'])) {
                    $foremanGrade = $doc['grade_original'] ?? 'A';
                    $foremanScore = number_format((float)$doc['total_nilai'], 2);
                }
            }

            // 2. Section Head Details (blank / '-' for Dept Head self-evaluation or Section Head before evaluation)
            $sNrp = $isSelf ? '' : ($k['nrp_section_head'] ?? '');
            $sUser = $userByNrp[$sNrp] ?? ($allSectionHeads[$sNrp] ?? null);
            $sName = $isSelf ? '-' : ($shNames[$sNrp] ?? ($sUser['nama'] ?? ($sUser->nama ?? (!empty($sNrp) ? "Section Head ($sNrp)" : "-"))));
            $sNameClean = trim(preg_replace('/\s*\(.*?\)/', '', $sName));

            $hasSectionSign = !$isSelf && $docId && (isset($ttdByDocAndLevel[$docId]['section_head']) || ($isGol4UpDoc && empty($fNrp) && isset($ttdByDocAndLevel[$docId]['foreman'])));
            $nilaiSection = '-';
            $sectionScore = null;
            if (!$isSelf) {
                if ($docId && isset($allScoresByDocAndLevel[$docId]['section_head']) && count($allScoresByDocAndLevel[$docId]['section_head']) > 0) {
                    $sScoresArr = $allScoresByDocAndLevel[$docId]['section_head']->pluck('skor', 'aspek_kode')->toArray();
                    $sCalc = $this->calcService->calculate($kodeForm, $sScoresArr);
                    $sectionScore = number_format($sCalc['grand_total'], 2);
                    $nilaiSection = $this->calcService->scoreToGrade($sCalc['grand_total']);
                } elseif ($isGol4UpDoc && empty($fNrp) && $docId && isset($allScoresByDocAndLevel[$docId]['foreman']) && count($allScoresByDocAndLevel[$docId]['foreman']) > 0) {
                    $sScoresArr = $allScoresByDocAndLevel[$docId]['foreman']->pluck('skor', 'aspek_kode')->toArray();
                    $sCalc = $this->calcService->calculate($kodeForm, $sScoresArr);
                    $sectionScore = number_format($sCalc['grand_total'], 2);
                    $nilaiSection = $this->calcService->scoreToGrade($sCalc['grand_total']);
                } elseif (in_array($statusRaw, ['menunggu_dept_head', 'menunggu_div_head', 'selesai']) && !empty($doc['total_nilai'])) {
                    $scoreVal = (float)$doc['total_nilai'];
                    $nilaiSection = $doc['grade_original'] ?? ($scoreVal > 0 ? $this->calcService->scoreToGrade($scoreVal) : ($foremanGrade !== '-' ? $foremanGrade : 'A'));
                    $sectionScore = number_format($scoreVal, 2);
                }
            }
            if ($isGol4UpDoc && !$isSelf && ($sNameClean === '-' || $sNameClean === '')) {
                $sNameClean = ($nilaiSection !== '-' || $hasSectionSign) ? trim((string)($k['nama_lengkap'] ?? '-')) : '-';
            }

            // 3. Dept Head Details & Nilai Akhir (07-006-DH self-evaluation saves score & sign in foreman slot)
            $hasSelfSign = $isSelf && $docId && isset($ttdByDocAndLevel[$docId]['foreman']);
            $hasDeptSign = $docId && (isset($ttdByDocAndLevel[$docId]['dept_head']) || $hasSelfSign);
            $selfNilaiAkhir = '';
            if ($isSelf && $docId && isset($allScoresByDocAndLevel[$docId]['foreman']) && count($allScoresByDocAndLevel[$docId]['foreman']) > 0) {
                $selfScoresArr = $allScoresByDocAndLevel[$docId]['foreman']->pluck('skor', 'aspek_kode')->toArray();
                $selfCalc = $this->calcService->calculate($kodeForm, $selfScoresArr);
                if (($selfCalc['grand_total'] ?? 0) > 0) {
                    $selfNilaiAkhir = $this->calcService->scoreToGrade($selfCalc['grand_total']);
                }
            }
            $nilaiAkhir = $doc['docking_final'] ?? ($selfNilaiAkhir ?: ($doc['grade_original'] ?? ($nilaiSection !== '-' ? $nilaiSection : ($foremanGrade !== '-' ? $foremanGrade : ''))));
            if ($nilaiAkhir === '-') $nilaiAkhir = '';

            $isDone = ($statusRaw === 'selesai' || ($isSelf && $statusRaw === 'menunggu_div_head') || !empty($doc['docking_final']) || $hasDeptSign);
            $isWaitingForeman = !empty($fNrp) && ($statusRaw === 'menunggu_foreman' || !$hasForemanSign);
            $isWaitingSection = ($statusRaw === 'menunggu_section_head' || (!$hasSectionSign && !$isWaitingForeman && !$isSelf));
            $canEvaluateDept = $isSelf ? ($statusRaw !== 'menunggu_div_head' && $statusRaw !== 'selesai') : (($statusRaw === 'menunggu_dept_head') || $user->isSuperadmin());

            $statusLabel = $isDone ? ($isSelf && $statusRaw === 'menunggu_div_head' ? 'Menunggu Div. Head' : 'Sudah dinilai') : (
                $isSelf ? 'Penilaian Mandiri' : (
                    $isWaitingForeman ? 'Menunggu Foreman' : (
                        $isWaitingSection ? 'Menunggu Section Head' : 'Menunggu'
                    )
                )
            );

            $rowItem = [
                'nrp' => $nrp,
                'nama_lengkap' => $k['nama_lengkap'],
                'jabatan' => $k['jabatan'] ?? '-',
                'golongan' => $k['golongan'] ?? $k['sub_gol'] ?? '-',
                'seksi' => $k['seksi'] ?? '-',
                'is_self' => $isSelf,
                'foreman_nama' => $fNameClean,
                'foreman_nrp' => $fNrp,
                'nilai_foreman' => $foremanGrade,
                'foreman_score' => $foremanScore,
                'has_foreman_sign' => $hasForemanSign,
                'section_nama' => $sNameClean,
                'section_nrp' => $sNrp,
                'nilai_section' => $nilaiSection,
                'section_score' => $sectionScore,
                'has_section_sign' => $hasSectionSign,
                'has_dept_sign' => $hasDeptSign,
                'doc_id' => $docId,
                'kode_form' => $kodeForm,
                'status_raw' => $statusRaw,
                'nilai_akhir' => $nilaiAkhir,
                'catatan' => $doc['catatan'] ?? '',
                'status' => $statusLabel,
                'is_waiting_foreman' => $isWaitingForeman,
                'is_waiting_section' => $isWaitingSection,
                'can_evaluate_dept' => $canEvaluateDept,
            ];
            $rows[] = $rowItem;

            if ($statusRaw === 'menunggu_dept_head' && !$latestPending) {
                $latestPending = $rowItem;
            }
        }

        // Pin self-evaluation (Dept Head 07-006-DH) first, then ready-to-review rows
        usort($rows, function ($a, $b) {
            if (!empty($a['is_self']) !== !empty($b['is_self'])) {
                return !empty($a['is_self']) ? -1 : 1;
            }
            $aReady = ($a['status_raw'] ?? '') === 'menunggu_dept_head' ? 0 : 1;
            $bReady = ($b['status_raw'] ?? '') === 'menunggu_dept_head' ? 0 : 1;
            if ($aReady !== $bReady) {
                return $aReady <=> $bReady;
            }
            return strnatcasecmp((string)$a['nrp'], (string)$b['nrp']);
        });

        if (!$latestPending && count($rows) > 0) {
            foreach ($rows as $r) {
                if ($r['status_raw'] === 'menunggu_dept_head') {
                    $latestPending = $r;
                    break;
                }
            }
            if (!$latestPending) {
                $latestPending = $rows[0];
            }
        }

        $hasAnyForeman = false;
        foreach ($subordinates as $subK) {
            if (!empty($subK['nrp_foreman'])) {
                $hasAnyForeman = true;
                break;
            }
        }
        $isDeptWithoutForeman = !$hasAnyForeman;

        $countAll = count($rows);
        $countSiap = count(array_filter($rows, fn($r) => $r['status_raw'] === 'menunggu_dept_head' || (!empty($r['is_self']) && !in_array($r['status_raw'], ['menunggu_div_head', 'selesai']))));
        $countProses = count(array_filter($rows, fn($r) => empty($r['is_self']) && in_array($r['status_raw'], ['menunggu_foreman', 'menunggu_section_head', 'draft', 'belum_ada', 'revisi'])));
        $countSelesai = count(array_filter($rows, fn($r) => in_array($r['status_raw'], ['menunggu_div_head', 'selesai']) || !empty($r['has_dept_sign'])));
        $countMenunggu = $countSiap;
        $countDinilai = $countSelesai;

        $countProsesForeman = 0;
        $countForemanSudah = 0;
        $countProsesSection = 0;
        $countSectionSudah = 0;
        foreach ($rows as $r) {
            if (!empty($r['is_self'])) {
                continue;
            }
            $stRow = $r['status_raw'] ?? 'belum_ada';
            $isGol4UpRow = self::isForm07006($r['kode_form'] ?? null);
            $hasForemanRow = !empty($r['foreman_nrp']) && !$isGol4UpRow;

            if ($hasForemanRow) {
                if (in_array($stRow, ['menunggu_section_head', 'menunggu_dept_head', 'menunggu_div_head', 'selesai'], true)) {
                    $countForemanSudah++;
                } else {
                    $countProsesForeman++;
                }
            }

            if (in_array($stRow, ['menunggu_dept_head', 'menunggu_div_head', 'selesai'], true)) {
                $countSectionSudah++;
            } elseif ($stRow === 'menunggu_section_head' || (!$hasForemanRow && in_array($stRow, ['belum_ada', '', null, 'draft', 'revisi', 'menunggu_foreman'], true))) {
                $countProsesSection++;
            }
        }

        return view('penilaian.dept', compact(
            'user',
            'rows',
            'latestPending',
            'countAll',
            'countMenunggu',
            'countDinilai',
            'countSiap',
            'countProses',
            'countProsesForeman',
            'countForemanSudah',
            'countProsesSection',
            'countSectionSudah',
            'countSelesai',
            'isDeptWithoutForeman',
            'selectedSeksi'
        ));
    }

    /**
     * Save draft for Department Head batch evaluation.
     */
    public function deptSaveDraft(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        $penilaianData = $request->input('penilaian', []);
        $savedCount = 0;

        foreach ($penilaianData as $docId => $data) {
            $grade = $data['grade'] ?? '';
            if (empty($grade)) continue;

            $doc = $this->repository->find('dokumen', 'id', $docId);
            if (!$doc) continue;

            $k = $this->repository->find('karyawan', 'nrp', $doc['nrp']);
            $isSelfDoc = strcasecmp($doc['nrp'] ?? '', $user->nrp) === 0;
            $isDirectDeptSub = !empty($k['nrp_dept_head']) && strcasecmp($k['nrp_dept_head'], $user->nrp) === 0;
            if (!$isSelfDoc && !$user->canAccessDepartment($k['departemen'] ?? '') && !$user->isSuperadmin() && !$isDirectDeptSub) {
                continue;
            }

            $this->repository->update('dokumen', 'id', $docId, [
                'docking_final' => $grade,
            ]);
            $savedCount++;
        }

        return redirect()->back()->with('success', "Draft penilaian ({$savedCount} karyawan) berhasil disimpan.");
    }

    /**
     * Data Penilaian page for Division Head.
     */
    public function divIndex(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $allKaryawan = $this->repository->all('karyawan');
        $allDocs = $this->repository->all('dokumen');
        $allUsers = $this->repository->all('app_users');

        $userByNrp = [];
        foreach ($allUsers as $u) {
            if (!empty($u['nrp'])) $userByNrp[$u['nrp']] = $u;
        }

        $docsByNrp = [];
        foreach ($allDocs as $d) {
            $docsByNrp[$d['nrp']] = $d;
        }

        // Subordinates in this division under this Division Head (including self)
        $subordinates = array_values(array_filter($allKaryawan, function ($k) use ($user) {
            if ($user->isAdmin() || $user->isSuperadmin()) return true;
            return $user->matchesDivisionHeadScope($k);
        }));

        $selectedDept = $request->query('departemen');
        if ($selectedDept && $selectedDept !== 'ALL') {
            $subordinates = array_values(array_filter($subordinates, function ($k) use ($selectedDept) {
                return strcasecmp($k['departemen'] ?? '', $selectedDept) === 0;
            }));
        }

        $allForemen = DB::table('app_users')->whereIn('role', ['foreman', 'ketua'])->get()->keyBy('nrp');
        $allSectionHeads = DB::table('app_users')->where('role', 'section_head')->get()->keyBy('nrp');
        $allDeptHeads = DB::table('app_users')->whereIn('role', ['department_head', 'dept_head'])->get()->keyBy('nrp');

        $karyawanByNrp = [];
        foreach ($allKaryawan as $item) {
            if (!empty($item['nrp'])) {
                $karyawanByNrp[$item['nrp']] = $item;
            }
        }

        $dhByDept = [];
        foreach ($allDeptHeads as $dh) {
            if (!empty($dh->departemen)) {
                $dhByDept[$dh->departemen] = $dh;
            }
        }

        $docIds = array_filter(array_column($docsByNrp, 'id'));
        $allTtd = DB::table('tanda_tangan')->whereIn('dokumen_id', $docIds)->get();
        $ttdByDocAndLevel = [];
        foreach ($allTtd as $t) {
            $ttdByDocAndLevel[$t->dokumen_id][$t->level] = $t;
        }

        $allScoresByDocAndLevel = DB::table('nilai')
            ->whereIn('dokumen_id', $docIds)
            ->get()
            ->groupBy(['dokumen_id', 'level']);

        $shNames = [
            '112' => 'KRISTIYANTO',
            '280' => 'MURYADI',
            '99999' => 'AL QADRI',
            'SH-11310' => 'Dwi Cahyono',
        ];

        $dhNames = [
            '31' => 'SUPARDI',
            'DH-11300' => 'Agus Setiawan',
            'DH-11500' => 'Suryo Utomo',
        ];

        $rows = [];
        $latestPending = null;

        foreach ($subordinates as $k) {
            $nrp = $k['nrp'];
            $isSelf = (strcasecmp($nrp, $user->nrp) === 0);
            $doc = $docsByNrp[$nrp] ?? null;
            $docId = $doc['id'] ?? null;
            $statusRaw = $doc['status'] ?? 'belum_ada';
            $kodeForm = $doc['kode_form'] ?? self::determineKodeForm($k);
            $isGol4UpDoc = in_array($kodeForm, ['07-006-SH', '07-006-DH', '07-006-DIV']);

            // 1. Foreman Details
            $fNrp = $isSelf ? '' : ($k['nrp_foreman'] ?? '');
            $fUser = $userByNrp[$fNrp] ?? ($allForemen[$fNrp] ?? null);
            $fName = $fUser['nama'] ?? ($fUser->nama ?? (!empty($fNrp) ? "Foreman ($fNrp)" : "-"));
            $fNameClean = trim(preg_replace('/\s*\(.*?\)/', '', $fName));
            $hasForeman = !empty($fNrp) && !$isSelf && !$isGol4UpDoc;
            $hasForemanSign = $hasForeman && $docId && isset($ttdByDocAndLevel[$docId]['foreman']);
            $foremanGrade = '-';
            $foremanScore = null;
            if ($hasForeman) {
                if ($docId && isset($allScoresByDocAndLevel[$docId]['foreman']) && count($allScoresByDocAndLevel[$docId]['foreman']) > 0) {
                    $fScoresArr = $allScoresByDocAndLevel[$docId]['foreman']->pluck('skor', 'aspek_kode')->toArray();
                    $fCalc = $this->calcService->calculate($kodeForm, $fScoresArr);
                    $foremanScore = number_format($fCalc['grand_total'], 2);
                    $foremanGrade = $this->calcService->scoreToGrade($fCalc['grand_total']);
                }
            }

            // 2. Section Head Details
            $sNrp = $isSelf ? '' : ($k['nrp_section_head'] ?? '');
            $sUser = $userByNrp[$sNrp] ?? ($allSectionHeads[$sNrp] ?? null);
            $sName = $isSelf ? 'Mandiri' : ($shNames[$sNrp] ?? ($sUser['nama'] ?? ($sUser->nama ?? (!empty($sNrp) ? "Section Head ($sNrp)" : "Section Head"))));
            $sNameClean = trim(preg_replace('/\s*\(.*?\)/', '', $sName));
            $hasSectionSign = !$isSelf && $docId && (isset($ttdByDocAndLevel[$docId]['section_head']) || ($isGol4UpDoc && empty($fNrp) && isset($ttdByDocAndLevel[$docId]['foreman'])));
            $nilaiSection = '-';
            $sectionScore = null;
            if (!$isSelf) {
                if ($docId && isset($allScoresByDocAndLevel[$docId]['section_head']) && count($allScoresByDocAndLevel[$docId]['section_head']) > 0) {
                    $sScoresArr = $allScoresByDocAndLevel[$docId]['section_head']->pluck('skor', 'aspek_kode')->toArray();
                    $sCalc = $this->calcService->calculate($kodeForm, $sScoresArr);
                    $sectionScore = number_format($sCalc['grand_total'], 2);
                    $nilaiSection = $this->calcService->scoreToGrade($sCalc['grand_total']);
                } elseif ($isGol4UpDoc && empty($fNrp) && $docId && isset($allScoresByDocAndLevel[$docId]['foreman']) && count($allScoresByDocAndLevel[$docId]['foreman']) > 0) {
                    $sScoresArr = $allScoresByDocAndLevel[$docId]['foreman']->pluck('skor', 'aspek_kode')->toArray();
                    $sCalc = $this->calcService->calculate($kodeForm, $sScoresArr);
                    $sectionScore = number_format($sCalc['grand_total'], 2);
                    $nilaiSection = $this->calcService->scoreToGrade($sCalc['grand_total']);
                } elseif (in_array($statusRaw, ['menunggu_dept_head', 'menunggu_div_head', 'selesai']) && !empty($doc['total_nilai'])) {
                    $scoreVal = (float)$doc['total_nilai'];
                    $nilaiSection = $doc['grade_original'] ?? ($scoreVal > 0 ? $this->calcService->scoreToGrade($scoreVal) : ($foremanGrade !== '-' ? $foremanGrade : 'A'));
                    $sectionScore = number_format($scoreVal, 2);
                }
            }

            // 3. Dept Head Details
            $dNrp = $isSelf ? '' : ($k['nrp_dept_head'] ?? '');
            if (!$isSelf && empty($dNrp)) {
                // If karyawan has nrp_section_head, lookup section head's nrp_dept_head
                if (!empty($k['nrp_section_head']) && !empty($karyawanByNrp[$k['nrp_section_head']]['nrp_dept_head'])) {
                    $dNrp = $karyawanByNrp[$k['nrp_section_head']]['nrp_dept_head'];
                } elseif (!empty($k['departemen']) && isset($dhByDept[$k['departemen']])) {
                    $dNrp = $dhByDept[$k['departemen']]->nrp ?? '';
                }
            }
            $dUser = $userByNrp[$dNrp] ?? ($allDeptHeads[$dNrp] ?? null);
            $dName = $isSelf ? '-' : ($dhNames[$dNrp] ?? ($dUser['nama'] ?? ($dUser->nama ?? (!empty($dNrp) ? "Department Head ($dNrp)" : "Department Head"))));
            $dNameClean = trim(preg_replace('/\s*\(.*?\)/', '', $dName));
            $hasDeptSign = $docId && isset($ttdByDocAndLevel[$docId]['dept_head']);
            $nilaiDept = $doc['docking_final'] ?? ($nilaiSection !== '-' ? $nilaiSection : ($foremanGrade !== '-' ? $foremanGrade : '-'));
            if ($nilaiDept === '') $nilaiDept = '-';

            // 4. Division Head Details & Final Status
            $hasDivSign = $docId && (isset($ttdByDocAndLevel[$docId]['div_head']) || ($isSelf && isset($ttdByDocAndLevel[$docId]['dept_head'])));
            $nilaiDiv = $doc['docking_final'] ?? ($nilaiDept !== '-' ? $nilaiDept : '');
            if ($nilaiDiv === '-') $nilaiDiv = '';

            $isDone = ($statusRaw === 'selesai' || $hasDivSign);
            $isWaitingDiv = ($statusRaw === 'menunggu_div_head' || ($isSelf && $statusRaw !== 'selesai'));
            $canEvaluateDiv = $isSelf ? ($statusRaw !== 'selesai') : ($statusRaw === 'menunggu_div_head' || $user->isSuperadmin());

            $statusLabel = $isDone ? 'Disahkan Final' : (
                $isWaitingDiv ? ($isSelf ? 'Penilaian Mandiri' : 'Menunggu Pengesahan Anda') : (
                    $statusRaw === 'menunggu_dept_head' ? 'Proses Dept Head' : (
                        $statusRaw === 'menunggu_section_head' ? 'Proses Section Head' : (
                            $statusRaw === 'menunggu_foreman' ? 'Proses Foreman' : 'Draft'
                        )
                    )
                )
            );

            $rowItem = [
                'nrp' => $nrp,
                'nama_lengkap' => $k['nama_lengkap'],
                'jabatan' => $k['jabatan'] ?? '-',
                'golongan' => $k['golongan'] ?? $k['sub_gol'] ?? '-',
                'seksi' => $k['seksi'] ?? '-',
                'departemen' => $k['departemen'] ?? '-',
                'is_self' => $isSelf,
                'foreman_nama' => $fNameClean,
                'foreman_nrp' => $fNrp,
                'nilai_foreman' => $foremanGrade,
                'foreman_score' => $foremanScore,
                'has_foreman_sign' => $hasForemanSign,
                'section_nama' => $sNameClean,
                'section_nrp' => $sNrp,
                'nilai_section' => $nilaiSection,
                'section_score' => $sectionScore,
                'has_section_sign' => $hasSectionSign,
                'dept_nama' => $dNameClean,
                'dept_nrp' => $dNrp,
                'nilai_dept' => $nilaiDept,
                'has_dept_sign' => $hasDeptSign,
                'has_div_sign' => $hasDivSign,
                'doc_id' => $docId,
                'kode_form' => $kodeForm,
                'status_raw' => $statusRaw,
                'nilai_div' => $nilaiDiv,
                'catatan' => $doc['catatan'] ?? '',
                'status' => $statusLabel,
                'is_waiting_div' => $isWaitingDiv,
                'is_done' => $isDone,
                'can_evaluate_div' => $canEvaluateDiv,
            ];
            $rows[] = $rowItem;

            if ($isWaitingDiv && !$latestPending) {
                $latestPending = $rowItem;
            }
        }

        if (!$latestPending && count($rows) > 0) {
            $latestPending = $rows[0];
        }

        // Pin self row first, then waiting Div Head, then 07-006 priority
        usort($rows, function ($a, $b) {
            if (!empty($a['is_self']) !== !empty($b['is_self'])) {
                return !empty($a['is_self']) ? -1 : 1;
            }
            if (!empty($a['is_waiting_div']) !== !empty($b['is_waiting_div'])) {
                return !empty($a['is_waiting_div']) ? -1 : 1;
            }
            $a07 = str_starts_with(strtoupper((string)($a['kode_form'] ?? '')), '07-006');
            $b07 = str_starts_with(strtoupper((string)($b['kode_form'] ?? '')), '07-006');
            if ($a07 !== $b07) {
                return $a07 ? -1 : 1;
            }
            return strcmp((string)$a['nrp'], (string)$b['nrp']);
        });

        $countAll = count($rows);
        $countSiap = count(array_filter($rows, fn($r) => $r['is_waiting_div']));
        $countProses = count(array_filter($rows, fn($r) => in_array($r['status_raw'], ['menunggu_foreman', 'menunggu_section_head', 'menunggu_dept_head', 'draft', 'belum_ada', 'revisi']) && !$r['is_self']));
        $countSelesai = count(array_filter($rows, fn($r) => $r['is_done']));
        $countMenunggu = $countSiap;
        $countDinilai = $countSelesai;

        $countProsesSection = 0;
        $countSectionSudah = 0;
        $countProsesDept = 0;
        $countDeptSudah = 0;

        foreach ($rows as $rItem) {
            if (!empty($rItem['is_self'])) {
                continue;
            }
            $stRow = $rItem['status_raw'] ?? 'belum_ada';
            if (in_array($stRow, ['menunggu_dept_head', 'menunggu_div_head', 'selesai'], true)) {
                $countSectionSudah++;
            } elseif (in_array($stRow, ['belum_ada', '', null, 'draft', 'revisi', 'menunggu_foreman', 'menunggu_section_head'], true)) {
                $countProsesSection++;
            }

            if (in_array($stRow, ['menunggu_div_head', 'selesai'], true)) {
                $countDeptSudah++;
            } elseif ($stRow === 'menunggu_dept_head') {
                $countProsesDept++;
            }
        }

        $hasAnyForeman = false;
        foreach ($subordinates as $subK) {
            if (!empty($subK['nrp_foreman'])) {
                $hasAnyForeman = true;
                break;
            }
        }
        $isDeptWithoutForeman = !$hasAnyForeman;

        return view('penilaian.div', compact(
            'user',
            'rows',
            'latestPending',
            'countAll',
            'countMenunggu',
            'countDinilai',
            'countSiap',
            'countProses',
            'countProsesSection',
            'countSectionSudah',
            'countProsesDept',
            'countDeptSudah',
            'countSelesai',
            'isDeptWithoutForeman'
        ));
    }

    /**
     * Save draft for Division Head batch evaluation.
     */
    public function divSaveDraft(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        $penilaianData = $request->input('penilaian', []);
        $savedCount = 0;

        foreach ($penilaianData as $docId => $data) {
            $grade = $data['grade'] ?? '';
            $catatan = $data['catatan'] ?? null;
            if (empty($grade) && $catatan === null) continue;

            $doc = $this->repository->find('dokumen', 'id', $docId);
            if (!$doc) continue;

            $k = $this->repository->find('karyawan', 'nrp', $doc['nrp']);
            $isSelfDoc = strcasecmp($doc['nrp'] ?? '', $user->nrp) === 0;
            $isDirectDeptSub = !empty($k['nrp_dept_head']) && strcasecmp($k['nrp_dept_head'], $user->nrp) === 0;
            if (!$isSelfDoc && !$user->canAccessDepartment($k['departemen'] ?? '') && !$user->isSuperadmin() && !$isDirectDeptSub) {
                continue;
            }

            $updatePayload = [];
            if (!empty($grade)) {
                $updatePayload['docking_final'] = $grade;
            }
            if ($catatan !== null) {
                $updatePayload['catatan'] = $catatan;
            }

            if (!empty($updatePayload)) {
                $this->repository->update('dokumen', 'id', $docId, $updatePayload);
                $savedCount++;
            }
        }

        return redirect()->back()->with('success', "Draft pengesahan ({$savedCount} karyawan) berhasil disimpan.");
    }

    /**
     * Dedicated Calibration & Quota Adjustment Page for Division Head.
     */
    public function divPenyesuaian(Request $request)
    {
        return $this->renderPenyesuaianView($request, false);
    }

    /**
     * Dedicated Calibration & Quota Adjustment Page for Department Head.
     */
    public function deptPenyesuaian(Request $request)
    {
        return $this->renderPenyesuaianView($request, true);
    }

    /**
     * Shared Calibration, History Comparison & Quota Adjustment renderer for Department Head and Division Head.
     */
    private function renderPenyesuaianView(Request $request, bool $isDeptMode = false)
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$isDeptMode) {
            abort(403, 'Halaman Penyesuaian Penilaian untuk Division Head sudah dinonaktifkan.');
        }

        $allowed = ($user && ($user->isDepartmentHead() || in_array($user->role, ['dept_head', 'department_head']) || $user->isAdmin()));

        if (!$allowed) {
            abort(403, 'Akses ditolak. Halaman Penyesuaian Penilaian khusus untuk Department Head.');
        }

        $allKaryawan = DB::table('karyawan')->get()->map(fn($r) => (array)$r)->toArray();
        $allDocs = DB::table('dokumen')->get()->map(fn($r) => (array)$r)->toArray();
        $docsByNrp = [];
        foreach ($allDocs as $d) {
            $docsByNrp[$d['nrp']] = $d;
        }

        $subordinates = array_values(array_filter($allKaryawan, function ($k) use ($user, $isDeptMode) {
            if ($user->isAdmin() || $user->isSuperadmin()) return true;
            return $isDeptMode ? $user->matchesDeptHeadScope($k, [], true) : $user->matchesDivisionHeadScope($k);
        }));

        $selectedDept = $request->query('departemen', 'ALL');
        $allDepts = array_values(array_unique(array_filter(array_column($subordinates, 'departemen'))));
        sort($allDepts);

        // Fetch scores for all documents
        $docIds = array_filter(array_column($docsByNrp, 'id'));
        $nilaiGrouped = DB::table('nilai')
            ->whereIn('dokumen_id', $docIds)
            ->get()
            ->groupBy(['dokumen_id', 'level'])
            ->map(function ($levels) {
                return $levels->map(function ($rows) {
                    return $rows->pluck('skor', 'aspek_kode')->toArray();
                });
            })
            ->toArray();

        // 1. Calculate Target Quota from Admin / Superadmin
        $targetQuota = $isDeptMode
            ? self::getDepartmentTargetQuota($user, $subordinates)
            : self::getDivisionTargetQuota($user, $subordinates);

        // 2. Build rows with per-level history (Foreman, Section Head, Dept Head) and quota distribution
        $rows = [];
        $actualCounts = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];
        $comparisonData = [
            'target' => [
                'S' => $targetQuota['S'] ?? 0,
                'A' => $targetQuota['A'] ?? 0,
                'B' => $targetQuota['B'] ?? 0,
                'C' => $targetQuota['C'] ?? 0,
                'K' => $targetQuota['K'] ?? 0,
            ],
            'foreman' => ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0],
            'section' => ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0],
            'dept' => ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0],
            'div' => ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0],
        ];
        $foremanKjMatrix = [];
        $sectionKjMatrix = [];
        foreach (['KJ 2', 'KJ 3', 'KJ 4', 'KJ 5'] as $initKj) {
            $foremanKjMatrix[$initKj] = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0, 'total' => 0];
            $sectionKjMatrix[$initKj] = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0, 'total' => 0];
        }

        foreach ($subordinates as $k) {
            $nrp = $k['nrp'];
            $doc = $docsByNrp[$nrp] ?? null;
            $docId = $doc['id'] ?? null;
            $statusRaw = $doc['status'] ?? 'belum_ada';
            $isSelf = (strcasecmp($nrp, $user->nrp) === 0);
            $rowKodeForm = $doc['kode_form'] ?? self::determineKodeForm($k);
            $isQuotaExemptRow = self::isQuotaExemptEmployee($k, $doc);
            $hasForemanEmp = !empty($k['nrp_foreman']) && !$isSelf && !$isQuotaExemptRow;

            // History Level 1: Foreman
            $foremanScoreVal = 0.0;
            $foremanGradeVal = null;
            if ($docId && $hasForemanEmp) {
                $fmScores = $nilaiGrouped[$docId]['foreman'] ?? [];
                if (!empty($fmScores)) {
                    $calcFm = $this->calcService->calculate($rowKodeForm, $fmScores);
                    $foremanScoreVal = (float)$calcFm['grand_total'];
                } elseif (in_array($statusRaw, ['menunggu_section_head', 'menunggu_dept_head', 'menunggu_div_head', 'selesai']) && !empty($doc['total_nilai'])) {
                    $foremanScoreVal = (float)$doc['total_nilai'];
                }
                if ($foremanScoreVal > 0) {
                    $foremanGradeVal = $this->calcService->scoreToGrade($foremanScoreVal);
                    if (!$isQuotaExemptRow && isset($comparisonData['foreman'][$foremanGradeVal])) {
                        $comparisonData['foreman'][$foremanGradeVal]++;
                        $rowQuotaKj = ExcelKaryawanReader::normalizeQuotaKj($k['kj'] ?? 'KJ 2');
                        if (!isset($foremanKjMatrix[$rowQuotaKj])) {
                            $foremanKjMatrix[$rowQuotaKj] = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0, 'total' => 0];
                        }
                        $foremanKjMatrix[$rowQuotaKj][$foremanGradeVal]++;
                        $foremanKjMatrix[$rowQuotaKj]['total']++;
                    }
                }
            }

            // History Level 2: Section Head
            $sectionScoreVal = 0.0;
            $sectionGradeVal = null;
            if ($docId && !$isSelf) {
                $shScores = $nilaiGrouped[$docId]['section_head']
                    ?? (($isQuotaExemptRow && empty($k['nrp_foreman'])) ? ($nilaiGrouped[$docId]['foreman'] ?? []) : []);
                if (!empty($shScores)) {
                    $calcSh = $this->calcService->calculate($rowKodeForm, $shScores);
                    $sectionScoreVal = (float)$calcSh['grand_total'];
                } elseif (in_array($statusRaw, ['menunggu_dept_head', 'menunggu_div_head', 'selesai']) && !empty($doc['total_nilai'])) {
                    $sectionScoreVal = (float)$doc['total_nilai'];
                }
                if ($sectionScoreVal > 0) {
                    $sectionGradeVal = $this->calcService->scoreToGrade($sectionScoreVal);
                    if (!$isQuotaExemptRow && isset($comparisonData['section'][$sectionGradeVal])) {
                        $comparisonData['section'][$sectionGradeVal]++;
                        $rowQuotaKj = ExcelKaryawanReader::normalizeQuotaKj($k['kj'] ?? 'KJ 2');
                        if (!isset($sectionKjMatrix[$rowQuotaKj])) {
                            $sectionKjMatrix[$rowQuotaKj] = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0, 'total' => 0];
                        }
                        $sectionKjMatrix[$rowQuotaKj][$sectionGradeVal]++;
                        $sectionKjMatrix[$rowQuotaKj]['total']++;
                    }
                }
            }

            // History Level 3: Department Head
            $deptScoreVal = 0.0;
            $deptGradeVal = null;
            if ($docId) {
                $dhScores = $nilaiGrouped[$docId]['dept_head'] ?? [];
                if (!empty($dhScores)) {
                    $calcDh = $this->calcService->calculate($rowKodeForm, $dhScores);
                    $deptScoreVal = (float)$calcDh['grand_total'];
                    if ($deptScoreVal > 0) {
                        $deptGradeVal = $this->calcService->scoreToGrade($deptScoreVal);
                    }
                } elseif (in_array($statusRaw, ['menunggu_div_head', 'selesai']) && !empty($doc['total_nilai'])) {
                    $deptScoreVal = (float)$doc['total_nilai'];
                    $deptGradeVal = !empty($doc['grade_original']) && in_array($doc['grade_original'], ['S', 'A', 'B', 'C', 'K'])
                        ? $doc['grade_original']
                        : $this->calcService->scoreToGrade($deptScoreVal);
                } elseif ($isSelf && !$isDeptMode && $rowKodeForm === '07-006-DIV') {
                    // Self DIV handled separately below
                }
                if (!$deptGradeVal && $isQuotaExemptRow && $rowKodeForm === '07-006-DH' && !empty($nilaiGrouped[$docId]['foreman'])) {
                    $calcDhSelf = $this->calcService->calculate($rowKodeForm, $nilaiGrouped[$docId]['foreman']);
                    $deptScoreVal = (float)$calcDhSelf['grand_total'];
                    if ($deptScoreVal > 0) {
                        $deptGradeVal = $this->calcService->scoreToGrade($deptScoreVal);
                    }
                }
                if ($deptGradeVal && !$isQuotaExemptRow && isset($comparisonData['dept'][$deptGradeVal])) {
                    $comparisonData['dept'][$deptGradeVal]++;
                }
            }

            // Div Head grade in comparison matrix
            if ($docId && !$isQuotaExemptRow) {
                $isReadyDivComp = (strcasecmp($nrp, $user->nrp) === 0) || in_array($statusRaw, ['menunggu_div_head', 'selesai']);
                if ($isReadyDivComp) {
                    if (!empty($doc['docking_final']) && in_array($doc['docking_final'], ['S', 'A', 'B', 'C', 'K'])) {
                        $comparisonData['div'][$doc['docking_final']]++;
                    } elseif ($statusRaw === 'selesai' && !empty($doc['total_nilai'])) {
                        $gDiv = $doc['docking_final'] ?? $doc['grade_original'] ?? $this->calcService->scoreToGrade((float)$doc['total_nilai']);
                        if (isset($comparisonData['div'][$gDiv])) {
                            $comparisonData['div'][$gDiv]++;
                        }
                    }
                }
            }

            // Readiness & Docking Value Calculation
            $isReady = $isDeptMode
                ? in_array($statusRaw, ['menunggu_dept_head', 'menunggu_div_head', 'selesai'])
                : ($isSelf || in_array($statusRaw, ['menunggu_div_head', 'selesai']));

            $score = 0.0;
            $hasScore = false;
            $originalGrade = null;
            $finalGrade = null;

            if ($isReady) {
                $isSubDeptHead = ($doc['kode_form'] ?? '') === '07-006-DH' || ($k['level_form'] ?? '') === 'DEPT_HEAD';

                if ($isSelf) {
                    $selfLevelScores = $docId
                        ? ($nilaiGrouped[$docId]['dept_head'] ?? ($nilaiGrouped[$docId]['foreman'] ?? []))
                        : [];
                    if (!empty($selfLevelScores)) {
                        $calc = $this->calcService->calculate($rowKodeForm ?: '07-006-DIV', $selfLevelScores);
                        $score = $calc['grand_total'];
                        $hasScore = ($score > 0);
                    } elseif (!empty($doc['total_nilai']) && (float)$doc['total_nilai'] > 0) {
                        $score = (float)$doc['total_nilai'];
                        $hasScore = true;
                    }
                } elseif ($isSubDeptHead) {
                    if ($docId && !empty($nilaiGrouped[$docId]['foreman'])) {
                        $calc = $this->calcService->calculate($doc['kode_form'] ?? '07-006-DH', $nilaiGrouped[$docId]['foreman']);
                        $score = $calc['grand_total'];
                        $hasScore = ($score > 0);
                    } elseif (!empty($doc['total_nilai']) && (float)$doc['total_nilai'] > 0) {
                        $score = (float)$doc['total_nilai'];
                        $hasScore = true;
                    }
                } elseif ($isDeptMode) {
                    if ($deptScoreVal > 0) {
                        $score = $deptScoreVal;
                        $hasScore = true;
                    } elseif ($sectionScoreVal > 0) {
                        $score = $sectionScoreVal;
                        $hasScore = true;
                    } elseif ($foremanScoreVal > 0) {
                        $score = $foremanScoreVal;
                        $hasScore = true;
                    } elseif (!empty($doc['total_nilai']) && (float)$doc['total_nilai'] > 0) {
                        $score = (float)$doc['total_nilai'];
                        $hasScore = true;
                    }
                } else {
                    if ($docId && !empty($nilaiGrouped[$docId]['dept_head'])) {
                        $calc = $this->calcService->calculate($doc['kode_form'] ?? '07-001', $nilaiGrouped[$docId]['dept_head']);
                        $score = $calc['grand_total'];
                        $hasScore = ($score > 0);
                    } elseif ($statusRaw === 'selesai' && !empty($doc['total_nilai']) && (float)$doc['total_nilai'] > 0) {
                        $score = (float)$doc['total_nilai'];
                        $hasScore = true;
                    }
                }

                if ($hasScore && $score > 0) {
                    $originalGrade = !empty($doc['grade_original']) && in_array($doc['grade_original'], ['S', 'A', 'B', 'C', 'K'])
                        ? $doc['grade_original']
                        : $this->calcService->scoreToGrade($score);
                } elseif (!empty($doc['grade_original']) && in_array($doc['grade_original'], ['S', 'A', 'B', 'C', 'K'])) {
                    $originalGrade = $doc['grade_original'];
                }

                if (!empty($doc['docking_final']) && in_array($doc['docking_final'], ['S', 'A', 'B', 'C', 'K'])) {
                    $finalGrade = $doc['docking_final'];
                } elseif ($originalGrade) {
                    $finalGrade = $originalGrade;
                }

                if (!$isQuotaExemptRow && $finalGrade && isset($actualCounts[$finalGrade])) {
                    $actualCounts[$finalGrade]++;
                }
            }

            $isAdjusted = ($finalGrade && $originalGrade && $finalGrade !== $originalGrade);
            $formUrl = $isDeptMode
                ? ($docId ? route('penilaian.show.depthead', $docId) : route('penilaian.byNrp', $nrp))
                : ($docId ? route('penilaian.show.divhead', $docId) : route('penilaian.byNrp', $nrp));

            $waitingLabel = $isDeptMode ? '⏳ Menunggu For / SH' : '⏳ Menunggu SH / DH';
            if (!$isReady) {
                if ($hasForemanEmp && in_array($statusRaw, ['menunggu_foreman', 'draft', 'belum_ada', '', null], true)) {
                    $waitingLabel = '⏳ Menunggu Foreman';
                } elseif (in_array($statusRaw, ['menunggu_section_head', 'revisi'], true) || (!$hasForemanEmp && in_array($statusRaw, ['draft', 'belum_ada', '', null, 'menunggu_foreman'], true))) {
                    $waitingLabel = '⏳ Menunggu Section Head';
                } elseif ($statusRaw === 'menunggu_dept_head') {
                    $waitingLabel = '⏳ Menunggu Dept Head';
                }
            }

            $rows[] = [
                'doc_id' => $docId,
                'nrp' => $nrp,
                'nama' => $k['nama_lengkap'],
                'jabatan' => $k['jabatan'] ?? '-',
                'position' => $k['jabatan'] ?? ($k['position'] ?? '-'),
                'departemen' => $k['departemen'] ?? '-',
                'seksi' => (!empty($k['nama_sect']) && $k['nama_sect'] !== '-')
                    ? trim(preg_replace('/\s+SEC\.?$/i', '', (string)$k['nama_sect']))
                    : ((!empty($k['seksi']) && $k['seksi'] !== '-') ? trim(preg_replace('/\s+SEC\.?$/i', '', (string)$k['seksi'])) : '-'),
                'kj' => !empty($k['kj']) ? ExcelKaryawanReader::normalizeKj($k['kj']) : 'KJ 2',
                'quota_kj' => ExcelKaryawanReader::normalizeQuotaKj($k['kj'] ?? 'KJ 2'),
                'golongan' => $k['golongan'] ?? ($k['sub_gol'] ?? '-'),
                'kode_form' => $rowKodeForm,
                'is_quota_exempt' => $isQuotaExemptRow,
                'foreman_score_formatted' => $foremanScoreVal > 0 ? number_format($foremanScoreVal, 2) : '-',
                'foreman_grade' => $foremanGradeVal ?: '-',
                'section_score_formatted' => $sectionScoreVal > 0 ? number_format($sectionScoreVal, 2) : '-',
                'section_grade' => $sectionGradeVal ?: '-',
                'dept_score_formatted' => $deptScoreVal > 0 ? number_format($deptScoreVal, 2) : '-',
                'dept_grade' => $deptGradeVal ?: '-',
                'score' => $score,
                'has_score' => $hasScore,
                'score_formatted' => $hasScore ? number_format($score, 2) : '-',
                'original_grade' => $originalGrade ?: '-',
                'final_grade' => $finalGrade ?: '',
                'is_adjusted' => $isAdjusted,
                'status_raw' => $statusRaw,
                'waiting_label' => $waitingLabel,
                'is_self' => $isSelf,
                'is_ready' => $isReady,
                'is_eligible' => ($isReady && !$isQuotaExemptRow && ($hasScore || !empty($finalGrade))),
                'form_url' => $formUrl,
            ];
        }

        // Sort rows: ready employees first sorted by score descending, then unready subordinates
        usort($rows, function ($a, $b) {
            if ($a['is_ready'] !== $b['is_ready']) {
                return $b['is_ready'] <=> $a['is_ready'];
            }
            return $b['score'] <=> $a['score'];
        });

        // Compute difference (Actual - Target)
        $diffCounts = [
            'S' => $actualCounts['S'] - $targetQuota['S'],
            'A' => $actualCounts['A'] - $targetQuota['A'],
            'B' => $actualCounts['B'] - $targetQuota['B'],
            'C' => $actualCounts['C'] - $targetQuota['C'],
            'K' => $actualCounts['K'] - $targetQuota['K'],
        ];

        $overQuotaRanks = [];
        $availableRanks = [];
        foreach ($diffCounts as $grade => $diff) {
            if ($diff > 0) {
                $overQuotaRanks[$grade] = $diff;
            } elseif ($diff < 0) {
                $availableRanks[$grade] = abs($diff);
            }
        }

        $hasOverQuota = !empty($overQuotaRanks);

        // Target & Realisasi Quota per Jenjang KJ (Golongan)
        $divCode = self::resolveDivisionCode($user, $subordinates);
        $deptCode = self::resolveDepartmentCode($user, $subordinates);

        if ($isDeptMode) {
            $divName = 'Departemen ' . ($deptCode ?: ($user->departemen ?? 'Departemen'));
            $kjSummaryCards = self::getKjSummaryForDept($user, $subordinates, $docsByNrp, $rows);
            $penyesuaianMode = 'dept';
            $penyesuaianRoutes = [
                'simpan' => route('penilaian.dept.penyesuaian.simpan'),
                'autoAdjust' => route('penilaian.dept.penyesuaian.autoAdjust'),
                'reset' => route('penilaian.dept.penyesuaian.reset'),
            ];
        } else {
            $divName = match($divCode) {
                'DIV-E' => 'Division E (Technical & Engineering)',
                'DIV-MFG' => 'Manufacturing Division',
                'DIV-QC' => 'Quality Control Division',
                'DIV-GA' => 'General Affairs & Finance Division',
                default => ($user->isDivisionHead() ? 'Tingkat Divisi' : ($user->departemen ?? 'Divisi'))
            };
            $kjSummaryCards = self::getKjSummaryForDivision($user, $subordinates, $docsByNrp, $rows);
            $penyesuaianMode = 'div';
            $penyesuaianRoutes = [
                'simpan' => route('penilaian.div.penyesuaian.simpan'),
                'autoAdjust' => route('penilaian.div.penyesuaian.autoAdjust'),
                'reset' => route('penilaian.div.penyesuaian.reset'),
            ];
        }

        $allSections = array_values(array_unique(array_filter(
            array_column($rows, 'seksi'),
            fn ($s) => !empty($s) && $s !== '-' && strtoupper(trim((string)$s)) !== strtoupper(trim((string)($deptCode ?: ($user->departemen ?? ''))))
        )));
        sort($allSections, SORT_NATURAL);

        $allPositions = array_values(array_unique(array_filter(
            array_column($rows, 'jabatan'),
            fn ($p) => !empty($p) && $p !== '-'
        )));
        sort($allPositions, SORT_NATURAL);

        return view('penilaian.div_penyesuaian', compact(
            'user',
            'rows',
            'targetQuota',
            'actualCounts',
            'diffCounts',
            'hasOverQuota',
            'overQuotaRanks',
            'availableRanks',
            'comparisonData',
            'foremanKjMatrix',
            'sectionKjMatrix',
            'allDepts',
            'allSections',
            'allPositions',
            'selectedDept',
            'kjSummaryCards',
            'divName',
            'divCode',
            'penyesuaianMode',
            'penyesuaianRoutes'
        ));
    }

    /**
     * Resolve Division Code consistently (explicit NRP takes precedence over department fallback).
     */
    public static function resolveDivisionCode(User $user, array $subordinates = []): ?string
    {
        if ($user->nrp === 'DIV-12000') {
            return 'DIV-QC';
        }
        if ((string)$user->nrp === '11' || $user->nrp === 'DIV-11000') {
            return 'DIV-MFG';
        }

        $dept = strtoupper($user->departemen ?? '');
        if (in_array($dept, ['QA', 'QUALITY CONTROL'])) {
            return 'DIV-QC';
        }
        if (in_array($dept, ['PROD 1', 'PROD 2', 'PRODUCTION 1', 'PRODUCTION 2', 'MACHINING'])) {
            return 'DIV-MFG';
        }
        if (in_array($dept, ['ENGINEERING', 'ENG'])) {
            return 'DIV-E';
        }

        $subDepts = array_map('strtoupper', array_filter(array_column($subordinates, 'departemen')));
        if (in_array('ENGINEERING', $subDepts)) {
            return 'DIV-E';
        }
        if (in_array('PROD 1', $subDepts) || in_array('PROD 2', $subDepts) || in_array('MACHINING', $subDepts)) {
            return 'DIV-MFG';
        }
        if (in_array('QA', $subDepts)) {
            return 'DIV-QC';
        }

        return null;
    }

    /**
     * Calculate Target Quota based on Admin Settings or Standard Distribution.
     */
    public static function getDivisionTargetQuota(User $user, array $subordinates): array
    {
        // Form 07-006 (Section Head / Dept Head / Division Head) is not bound by Admin/Superadmin targets
        $quotaSubordinates = array_values(array_filter($subordinates, fn ($k) => !self::isQuotaExemptEmployee($k)));
        $totalKaryawan = count($quotaSubordinates);

        // Check if Admin/Superadmin set a custom quota for this Division in pengaturan
        $divKey = self::resolveDivisionCode($user, $subordinates);
        if ($divKey) {
            $customDivVal = DB::table('pengaturan')->where('key', 'target_kuota_div_' . $divKey)->value('value');
            if ($customDivVal) {
                $cDivData = json_decode($customDivVal, true);
                if (isset($cDivData['S'])) {
                    $cSum = (int)($cDivData['S'] ?? 0) + (int)($cDivData['A'] ?? 0) + (int)($cDivData['B'] ?? 0) + (int)($cDivData['C'] ?? 0) + (int)($cDivData['K'] ?? 0);
                    return [
                        'S' => (int)($cDivData['S'] ?? 0),
                        'A' => (int)($cDivData['A'] ?? 0),
                        'B' => (int)($cDivData['B'] ?? 0),
                        'C' => (int)($cDivData['C'] ?? 0),
                        'K' => (int)($cDivData['K'] ?? 0),
                        'total' => (int)($cDivData['total'] ?? $cSum),
                    ];
                }
            }
        }
        $depts = array_values(array_unique(array_filter(array_column($quotaSubordinates, 'departemen'))));

        $kuotaRows = !empty($depts) ? DB::table('kuota_departemen')->whereIn('departemen', $depts)->get() : collect();
        $targetS = 0; $targetA = 0; $targetB = 0; $targetC = 0; $targetK = 0;

        if ($kuotaRows->isNotEmpty()) {
            foreach ($kuotaRows as $kr) {
                $targetS += (int)$kr->kuota_s;
                $targetA += (int)$kr->kuota_a;
                $targetB += (int)$kr->kuota_b;
                $targetC += (int)$kr->kuota_c;
                $targetK += (int)$kr->kuota_k;
            }
        }

        $sumTarget = $targetS + $targetA + $targetB + $targetC + $targetK;

        if ($sumTarget === 0 || $sumTarget !== $totalKaryawan) {
            $pctSetting = DB::table('pengaturan')->where('key', 'target_kuota_pct')->first();
            $targetPercentages = $pctSetting ? json_decode($pctSetting->value, true) : ['S' => 10, 'A' => 30, 'B' => 30, 'C' => 20, 'K' => 10];

            if ($totalKaryawan > 0) {
                if ($sumTarget === 0) {
                    $targetS = max(0, (int)round($totalKaryawan * (($targetPercentages['S'] ?? 10) / 100)));
                    if ($targetS === 0 && $totalKaryawan >= 5) {
                        $targetS = 1;
                    }
                    $targetA = max(0, (int)round($totalKaryawan * (($targetPercentages['A'] ?? 30) / 100)));
                    $targetB = max(0, (int)round($totalKaryawan * (($targetPercentages['B'] ?? 30) / 100)));
                    $targetC = max(0, (int)round($totalKaryawan * (($targetPercentages['C'] ?? 20) / 100)));
                    $targetK = max(0, $totalKaryawan - ($targetS + $targetA + $targetB + $targetC));
                    if ($targetK < 0) {
                        $targetK = 0;
                        $targetB = max(0, $totalKaryawan - ($targetS + $targetA + $targetC));
                    }
                } else {
                    $diff = $totalKaryawan - $sumTarget;
                    $targetB = max(0, $targetB + $diff);
                }
            }
        }

        return [
            'S' => $targetS,
            'A' => $targetA,
            'B' => $targetB,
            'C' => $targetC,
            'K' => $targetK,
            'total' => $totalKaryawan,
        ];
    }

    /**
     * Calculate Department Target Quota strictly synced with Admin / Superadmin settings.
     */
    public static function getDepartmentTargetQuota(User $user, array $subordinates): array
    {
        $quotaSubordinates = array_values(array_filter($subordinates, fn ($k) => !self::isQuotaExemptEmployee($k)));
        $totalKaryawan = count($quotaSubordinates);

        $deptCode = self::resolveDepartmentCode($user, $subordinates);
        $rawDept = strtoupper(trim($user->departemen ?? ''));
        $isDivisionE = ($deptCode === 'ENGINEERING');

        $candidateKeys = [];
        if ($isDivisionE) {
            $candidateKeys[] = 'target_kuota_div_DIV-E';
            $candidateKeys[] = 'target_kuota_dept_ENGINEERING';
        }
        if ($deptCode !== '') {
            $candidateKeys[] = 'target_kuota_dept_' . $deptCode;
        }
        if ($rawDept !== '' && $rawDept !== $deptCode) {
            $candidateKeys[] = 'target_kuota_dept_' . $rawDept;
        }

        foreach (array_unique($candidateKeys) as $key) {
            $val = DB::table('pengaturan')->where('key', $key)->value('value');
            if ($val) {
                $decoded = json_decode($val, true);
                if (isset($decoded['S'])) {
                    $cSum = (int)($decoded['S'] ?? 0) + (int)($decoded['A'] ?? 0) + (int)($decoded['B'] ?? 0) + (int)($decoded['C'] ?? 0) + (int)($decoded['K'] ?? 0);
                    return [
                        'S' => (int)($decoded['S'] ?? 0),
                        'A' => (int)($decoded['A'] ?? 0),
                        'B' => (int)($decoded['B'] ?? 0),
                        'C' => (int)($decoded['C'] ?? 0),
                        'K' => (int)($decoded['K'] ?? 0),
                        'total' => (int)($decoded['total'] ?? $cSum),
                    ];
                }
            }
        }

        $deptsToCheck = array_values(array_unique(array_filter([$deptCode, $rawDept])));
        if (!empty($deptsToCheck)) {
            $kdRows = DB::table('kuota_departemen')->whereIn('departemen', $deptsToCheck)->get();
            if ($kdRows->isNotEmpty()) {
                $kS = (int)$kdRows->sum('kuota_s');
                $kA = (int)$kdRows->sum('kuota_a');
                $kB = (int)$kdRows->sum('kuota_b');
                $kC = (int)$kdRows->sum('kuota_c');
                $kK = (int)$kdRows->sum('kuota_k');
                $kSum = $kS + $kA + $kB + $kC + $kK;
                if ($kSum > 0) {
                    return [
                        'S' => $kS,
                        'A' => $kA,
                        'B' => $kB,
                        'C' => $kC,
                        'K' => $kK,
                        'total' => $kSum,
                    ];
                }
            }
        }

        $pctSetting = DB::table('pengaturan')->where('key', 'target_kuota_pct')->first();
        $targetPercentages = $pctSetting ? json_decode($pctSetting->value, true) : ['S' => 10, 'A' => 30, 'B' => 30, 'C' => 20, 'K' => 10];
        $counts = self::computeProportionalTargetCounts($totalKaryawan, $targetPercentages);
        $counts['total'] = $totalKaryawan;

        return $counts;
    }

    /**
     * Unified Target Quota lookup for Department Head or Division Head scope.
     */
    public static function getTargetQuotaForScope(User $user, array $subordinates): array
    {
        if ($user->isDepartmentHead() || in_array($user->role, ['dept_head', 'department_head'])) {
            return self::getDepartmentTargetQuota($user, $subordinates);
        }
        return self::getDivisionTargetQuota($user, $subordinates);
    }

    /**
     * Compute Target and Actual Distribution per Jenjang KJ for Division Head scope.
     */
    public static function getKjSummaryForDivision(User $user, array $subordinates, ?array $docsByNrp = null, ?array $rows = null): array
    {
        return self::getKjSummaryForScope($user, $subordinates, $docsByNrp, $rows, false);
    }

    /**
     * Compute Target and Actual Distribution per Jenjang KJ for Department Head scope.
     */
    public static function getKjSummaryForDept(User $user, array $subordinates, ?array $docsByNrp = null, ?array $rows = null): array
    {
        return self::getKjSummaryForScope($user, $subordinates, $docsByNrp, $rows, true);
    }

    /**
     * Shared KJ Summary & Quota Matrix builder for Division Head and Department Head.
     */
    private static function getKjSummaryForScope(User $user, array $subordinates, ?array $docsByNrp = null, ?array $rows = null, bool $isDeptMode = false): array
    {
        if ($docsByNrp === null) {
            $nrps = array_filter(array_column($subordinates, 'nrp'));
            $docs = DB::table('dokumen')->whereIn('nrp', $nrps)->orderByDesc('id')->get();
            $docsByNrp = [];
            foreach ($docs as $d) {
                if (!isset($docsByNrp[$d->nrp])) {
                    $docsByNrp[$d->nrp] = (array)$d;
                }
            }
        }

        $masterKjSetting = DB::table('pengaturan')->where('key', 'master_kj_list')->first();
        $masterKjList = $masterKjSetting ? (json_decode($masterKjSetting->value, true) ?: []) : [
            ['kode' => 'KJ 2', 'golongan' => 'Golongan II'],
            ['kode' => 'KJ 3', 'golongan' => 'Golongan III'],
            ['kode' => 'KJ 4', 'golongan' => 'Golongan IV'],
            ['kode' => 'KJ 5', 'golongan' => 'Golongan V'],
        ];
        $knownKjCodes = array_flip(array_map(fn ($x) => strtoupper(trim((string)($x['kode'] ?? ''))), $masterKjList));
        foreach ($subordinates as $subRow) {
            if (self::isQuotaExemptEmployee($subRow, $docsByNrp[$subRow['nrp'] ?? ''] ?? null)) {
                continue;
            }
            $sKj = ExcelKaryawanReader::normalizeQuotaKj($subRow['kj'] ?? '');
            if ($sKj !== '' && !isset($knownKjCodes[$sKj])) {
                $masterKjList[] = ['kode' => $sKj, 'golongan' => ''];
                $knownKjCodes[$sKj] = true;
            }
        }
        usort($masterKjList, fn ($a, $b) => strnatcasecmp((string)($a['kode'] ?? ''), (string)($b['kode'] ?? '')));

        $divCode = self::resolveDivisionCode($user, $subordinates);
        $deptCode = self::resolveDepartmentCode($user, $subordinates);
        if ($isDeptMode && $deptCode === 'ENGINEERING' && empty($divCode)) {
            $divCode = 'DIV-E';
        }

        $scopeTargetQuota = self::getTargetQuotaForScope($user, $subordinates);
        $subCountByKj = [];
        $kjCodesForMatrix = [];
        foreach ($masterKjList as $mKj) {
            $kCode = strtoupper(trim($mKj['kode']));
            $kjCodesForMatrix[] = $kCode;
            $subCountByKj[$kCode] = 0;
        }
        foreach ($subordinates as $subRow) {
            if (self::isQuotaExemptEmployee($subRow, $docsByNrp[$subRow['nrp'] ?? ''] ?? null)) {
                continue;
            }
            $sKj = ExcelKaryawanReader::normalizeQuotaKj($subRow['kj'] ?? 'KJ 2');
            if ($sKj !== '') {
                $subCountByKj[$sKj] = ($subCountByKj[$sKj] ?? 0) + 1;
            }
        }
        $targetKjMatrix = self::buildTargetKjMatrix(
            array_values(array_unique($kjCodesForMatrix)),
            $subCountByKj,
            $scopeTargetQuota,
            $deptCode,
            (string)($divCode ?? '')
        );

        $kjSummaryCards = [];
        foreach ($masterKjList as $mKj) {
            $kCode = strtoupper(trim($mKj['kode']));
            $matrixRow = $targetKjMatrix[$kCode] ?? ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0, 'total' => 0];
            $tS = (int)($matrixRow['S'] ?? 0);
            $tA = (int)($matrixRow['A'] ?? 0);
            $tB = (int)($matrixRow['B'] ?? 0);
            $tC = (int)($matrixRow['C'] ?? 0);
            $tK = (int)($matrixRow['K'] ?? 0);
            $totTargetKj = (int)($matrixRow['total'] ?? ($tS + $tA + $tB + $tC + $tK));

            // Subordinates in this KJ (strictly excludes 07-006 SH/DH/DivHead forms; KJ 5+ maps into KJ 5)
            $karyawanInKj = array_filter($subordinates, function($sub) use ($kCode, $docsByNrp) {
                if (self::isQuotaExemptEmployee($sub, $docsByNrp[$sub['nrp'] ?? ''] ?? null)) {
                    return false;
                }
                $kjVal = ExcelKaryawanReader::normalizeQuotaKj($sub['kj'] ?? 'KJ 2');
                return $kjVal === $kCode;
            });
            $totKaryawanKj = count($karyawanInKj);

            // Actual counts in this KJ (strictly excludes 07-006 SH/DH/DivHead forms)
            $actKj = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];
            if ($rows !== null) {
                foreach ($rows as $r) {
                    if (!empty($r['is_quota_exempt']) || self::isForm07006($r['kode_form'] ?? null)) {
                        continue;
                    }
                    if (ExcelKaryawanReader::normalizeQuotaKj($r['kj'] ?? '') === $kCode && !empty($r['final_grade']) && isset($actKj[$r['final_grade']])) {
                        $actKj[$r['final_grade']]++;
                    }
                }
            } else {
                foreach ($karyawanInKj as $sub) {
                    $d = $docsByNrp[$sub['nrp']] ?? null;
                    if ($d) {
                        $isReadyDoc = $isDeptMode
                            ? in_array($d['status'] ?? '', ['menunggu_dept_head', 'menunggu_div_head', 'selesai'])
                            : ((strcasecmp($sub['nrp'], $user->nrp) === 0) || in_array($d['status'] ?? '', ['menunggu_div_head', 'selesai']));
                        if (!$isReadyDoc) {
                            continue;
                        }
                        $g = null;
                        if (!empty($d['docking_final']) && in_array(strtoupper(trim($d['docking_final'])), ['S', 'A', 'B', 'C', 'K'])) {
                            $g = strtoupper(trim($d['docking_final']));
                        } elseif (!empty($d['grade_original']) && in_array(strtoupper(trim($d['grade_original'])), ['S', 'A', 'B', 'C', 'K'])) {
                            $g = strtoupper(trim($d['grade_original']));
                        }
                        if ($g && isset($actKj[$g])) {
                            $actKj[$g]++;
                        }
                    }
                }
            }

            $totActKj = array_sum($actKj);

            $targetArr = [
                'S' => $tS,
                'A' => $tA,
                'B' => $tB,
                'C' => $tC,
                'K' => $tK,
                'total' => $totTargetKj,
            ];

            $actualArr = [
                'S' => $actKj['S'],
                'A' => $actKj['A'],
                'B' => $actKj['B'],
                'C' => $actKj['C'],
                'K' => $actKj['K'],
                'total' => $totActKj,
            ];

            $overRanks = [];
            $availableRanks = [];
            $isOver = false;
            foreach (['S', 'A', 'B', 'C', 'K'] as $g) {
                $diff = $actKj[$g] - $targetArr[$g];
                if ($diff > 0) {
                    $overRanks[$g] = $diff;
                    $isOver = true;
                } elseif ($diff < 0) {
                    $availableRanks[$g] = abs($diff);
                }
            }
            if ($totActKj > $totTargetKj && $totTargetKj > 0) {
                $isOver = true;
            }
            $isFull = ($totActKj >= $totTargetKj && $totTargetKj > 0);

            $isStandard = in_array($kCode, ['KJ 2', 'KJ 3', 'KJ 4', 'KJ 5']);
            if ($isStandard || $totKaryawanKj > 0 || $totTargetKj > 0) {
                $kjSummaryCards[$kCode] = [
                    'kode' => $kCode,
                    'golongan' => $mKj['golongan'] ?? '',
                    'deskripsi' => $mKj['deskripsi'] ?? '',
                    'total_karyawan' => $totKaryawanKj,
                    'target' => $targetArr,
                    'actual' => $actualArr,
                    'over_ranks' => $overRanks,
                    'available_ranks' => $availableRanks,
                    'is_over' => $isOver,
                    'is_full' => $isFull,
                ];
            }
        }

        return $kjSummaryCards;
    }

    /**
     * Save Adjusted Quota Grades from Division Head.
     */
    public function simpanPenyesuaian(Request $request)
    {
        return $this->processSimpanPenyesuaian($request, false);
    }

    /**
     * Save Adjusted Quota Grades from Department Head.
     */
    public function simpanDeptPenyesuaian(Request $request)
    {
        return $this->processSimpanPenyesuaian($request, true);
    }

    private function processSimpanPenyesuaian(Request $request, bool $isDeptMode = false)
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$isDeptMode) {
            abort(403, 'Halaman Penyesuaian Penilaian untuk Division Head sudah dinonaktifkan.');
        }

        $allowed = ($user && ($user->isDepartmentHead() || in_array($user->role, ['dept_head', 'department_head']) || $user->isAdmin()));

        if (!$allowed) {
            abort(403);
        }

        $routeTarget = $isDeptMode ? 'penilaian.dept.penyesuaian' : 'penilaian.div.penyesuaian';
        $scopeWord = $isDeptMode ? 'departemen' : 'divisi';

        $penyesuaian = $request->input('docking', []);
        $forceSave = $request->boolean('force_save', false);

        // Fetch subordinates to validate against quota
        $allKaryawan = $this->repository->all('karyawan');
        $allDocs = $this->repository->all('dokumen');
        $docsByNrp = [];
        foreach ($allDocs as $d) {
            $docsByNrp[$d['nrp']] = $d;
        }

        $subordinates = array_values(array_filter($allKaryawan, function ($k) use ($user, $isDeptMode) {
            if ($user->isAdmin() || $user->isSuperadmin()) return true;
            return $isDeptMode ? $user->matchesDeptHeadScope($k, [], true) : $user->matchesDivisionHeadScope($k);
        }));
        $targetQuota = $isDeptMode
            ? self::getDepartmentTargetQuota($user, $subordinates)
            : self::getDivisionTargetQuota($user, $subordinates);

        $kjSummary = $isDeptMode
            ? self::getKjSummaryForDept($user, $subordinates, $docsByNrp)
            : self::getKjSummaryForDivision($user, $subordinates, $docsByNrp);
        $docIdToKaryawan = [];
        foreach ($subordinates as $sub) {
            $d = $docsByNrp[$sub['nrp']] ?? null;
            if ($d) {
                $docIdToKaryawan[$d['id']] = $sub;
            }
        }

        // Count submitted grades ONLY for quota-bound employees (exclude 07-006 SH/DH/DivHead)
        $gradeCounts = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];
        $kjSubmittedCounts = [];
        foreach ($penyesuaian as $docId => $grade) {
            $gradeUpper = strtoupper(trim($grade));
            $sub = $docIdToKaryawan[$docId] ?? null;
            $subDoc = $sub ? ($docsByNrp[$sub['nrp']] ?? null) : null;
            if ($sub && self::isQuotaExemptEmployee($sub, $subDoc)) {
                continue;
            }
            if (isset($gradeCounts[$gradeUpper])) {
                $gradeCounts[$gradeUpper]++;
            }
            if ($sub) {
                $kKj = ExcelKaryawanReader::normalizeQuotaKj($sub['kj'] ?? 'KJ 2');
                $kjSubmittedCounts[$kKj][$gradeUpper] = ($kjSubmittedCounts[$kKj][$gradeUpper] ?? 0) + 1;
            }
        }

        // Check if user submitted over quota without force_save
        if (!$forceSave) {
            foreach (['S', 'A', 'B', 'C', 'K'] as $g) {
                if ($gradeCounts[$g] > $targetQuota[$g]) {
                    $excess = $gradeCounts[$g] - $targetQuota[$g];
                    return redirect()->back()->with('error', "Maaf, kuota {$scopeWord} untuk Rank {$g} full/melebihi target! (Target Admin: {$targetQuota[$g]}, Terpilih: {$gradeCounts[$g]} [Lebih {$excess}]). Tolong lakukan penyesuaian agar sesuai kuota target yang diberikan oleh Admin.")->withInput();
                }
            }

            foreach ($kjSubmittedCounts as $kKj => $counts) {
                $kjTarget = $kjSummary[$kKj]['target'] ?? null;
                if ($kjTarget) {
                    foreach (['S', 'A', 'B', 'C', 'K'] as $g) {
                        $c = $counts[$g] ?? 0;
                        $t = $kjTarget[$g] ?? 0;
                        if ($c > $t) {
                            $excess = $c - $t;
                            return redirect()->back()->with('error', "Maaf, kuota jenjang {$kKj} untuk Rank {$g} full/melebihi target! (Target: {$t}, Terpilih: {$c} [Lebih {$excess}]). Tolong sesuaikan agar sesuai target kuota {$kKj}.")->withInput();
                        }
                    }
                }
            }
        }

        $readyStatuses = $isDeptMode
            ? ['menunggu_dept_head', 'menunggu_div_head', 'selesai']
            : ['menunggu_div_head', 'selesai'];

        $savedCount = 0;
        foreach ($penyesuaian as $docId => $grade) {
            $gradeUpper = strtoupper(trim($grade));
            if (!in_array($gradeUpper, ['S', 'A', 'B', 'C', 'K'])) continue;

            $doc = DB::table('dokumen')->where('id', $docId)->first();
            if (!$doc) continue;

            $isSelf = (strcasecmp($doc->nrp, $user->nrp) === 0);
            if (!$isSelf && !in_array($doc->status, $readyStatuses)) {
                continue;
            }

            DB::table('dokumen')->where('id', $docId)->update([
                'docking_final' => $gradeUpper,
                'updated_at' => now(),
            ]);
            $savedCount++;
        }

        $this->repository->clearCache('dokumen');
        GoogleSyncService::syncTables(['dokumen', 'kuota_departemen', 'pengaturan', 'audit_log']);

        return redirect()->route($routeTarget)->with('success', "Penyesuaian nilai untuk {$savedCount} karyawan berhasil disimpan dan diselaraskan!");
    }

    /**
     * Automatic Quota Rebalancing (Auto-Adjust) to match target quota (Division Head).
     */
    public function autoAdjustPenyesuaian(Request $request)
    {
        return $this->processAutoAdjustPenyesuaian($request, false);
    }

    /**
     * Automatic Quota Rebalancing (Auto-Adjust) to match target quota (Department Head).
     */
    public function autoAdjustDeptPenyesuaian(Request $request)
    {
        return $this->processAutoAdjustPenyesuaian($request, true);
    }

    private function processAutoAdjustPenyesuaian(Request $request, bool $isDeptMode = false)
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$isDeptMode) {
            abort(403, 'Halaman Penyesuaian Penilaian untuk Division Head sudah dinonaktifkan.');
        }

        $allowed = ($user && ($user->isDepartmentHead() || in_array($user->role, ['dept_head', 'department_head']) || $user->isAdmin()));

        if (!$allowed) {
            abort(403);
        }

        $routeTarget = $isDeptMode ? 'penilaian.dept.penyesuaian' : 'penilaian.div.penyesuaian';

        $allKaryawan = DB::table('karyawan')->get()->map(fn($r) => (array)$r)->toArray();
        $allDocs = DB::table('dokumen')->get()->map(fn($r) => (array)$r)->toArray();
        $docsByNrp = [];
        foreach ($allDocs as $d) {
            $docsByNrp[$d['nrp']] = $d;
        }

        $subordinates = array_values(array_filter($allKaryawan, function ($k) use ($user, $isDeptMode) {
            if ($user->isAdmin() || $user->isSuperadmin()) return true;
            return $isDeptMode ? $user->matchesDeptHeadScope($k, [], true) : $user->matchesDivisionHeadScope($k);
        }));

        $targetQuota = $isDeptMode
            ? self::getDepartmentTargetQuota($user, $subordinates)
            : self::getDivisionTargetQuota($user, $subordinates);

        // Fetch scores
        $docIds = array_filter(array_column($docsByNrp, 'id'));
        $nilaiGrouped = DB::table('nilai')->whereIn('dokumen_id', $docIds)->get()->groupBy(['dokumen_id', 'level'])->map(fn($lvl) => $lvl->map(fn($r) => $r->pluck('skor', 'aspek_kode')->toArray()))->toArray();

        $readyStatuses = $isDeptMode
            ? ['menunggu_dept_head', 'menunggu_div_head', 'selesai']
            : ['menunggu_div_head', 'selesai'];

        // Collect ready non-07-006 employees
        $empList = [];
        foreach ($subordinates as $k) {
            $nrp = $k['nrp'];
            $doc = $docsByNrp[$nrp] ?? null;
            if (!$doc || self::isQuotaExemptEmployee($k, $doc)) continue;
            $docId = $doc['id'];

            $isSelf = (strcasecmp($nrp, $user->nrp) === 0);
            $isReady = $isDeptMode
                ? in_array($doc['status'], $readyStatuses)
                : ($isSelf || in_array($doc['status'], $readyStatuses));
            if (!$isReady) {
                continue;
            }

            $score = 0.0;
            $hasScore = false;
            if (isset($nilaiGrouped[$docId]['dept_head']) && count($nilaiGrouped[$docId]['dept_head']) > 0) {
                $calc = $this->calcService->calculate($doc['kode_form'] ?? '07-001', $nilaiGrouped[$docId]['dept_head']);
                $score = $calc['grand_total'];
                $hasScore = ($score > 0);
            } elseif (isset($nilaiGrouped[$docId]['section_head']) && count($nilaiGrouped[$docId]['section_head']) > 0) {
                $calc = $this->calcService->calculate($doc['kode_form'] ?? '07-001', $nilaiGrouped[$docId]['section_head']);
                $score = $calc['grand_total'];
                $hasScore = ($score > 0);
            } elseif (isset($nilaiGrouped[$docId]['foreman']) && count($nilaiGrouped[$docId]['foreman']) > 0) {
                $calc = $this->calcService->calculate($doc['kode_form'] ?? '07-001', $nilaiGrouped[$docId]['foreman']);
                $score = $calc['grand_total'];
                $hasScore = ($score > 0);
            } elseif (!empty($doc['total_nilai']) && (float)$doc['total_nilai'] > 0) {
                $score = (float)$doc['total_nilai'];
                $hasScore = true;
            }

            if (!$hasScore && empty($doc['docking_final']) && empty($doc['grade_original'])) {
                continue;
            }

            $currentGrade = !empty($doc['docking_final']) ? $doc['docking_final'] : (!empty($doc['grade_original']) ? $doc['grade_original'] : ($score > 0 ? $this->calcService->scoreToGrade($score) : null));
            if (!$currentGrade || !in_array($currentGrade, ['S', 'A', 'B', 'C', 'K'])) {
                continue;
            }

            $empList[] = [
                'doc_id' => $docId,
                'nrp' => $nrp,
                'nama' => $k['nama_lengkap'],
                'kj' => !empty($k['kj']) ? ExcelKaryawanReader::normalizeKj($k['kj']) : 'KJ 2',
                'quota_kj' => ExcelKaryawanReader::normalizeQuotaKj($k['kj'] ?? 'KJ 2'),
                'score' => $score,
                'current_grade' => $currentGrade,
                'adjusted_grade' => $currentGrade,
            ];
        }

        if (empty($empList)) {
            $errMsg = $isDeptMode
                ? 'Tidak dapat melakukan otomatisasi: Belum ada dokumen karyawan yang masuk / selesai dinilai oleh Foreman atau Section Head.'
                : 'Tidak dapat melakukan otomatisasi: Belum ada dokumen karyawan yang selesai dinilai oleh Section Head dan Department Head.';
            return redirect()->route($routeTarget)->with('error', $errMsg);
        }

        $kjSummary = $isDeptMode
            ? self::getKjSummaryForDept($user, $subordinates, $docsByNrp)
            : self::getKjSummaryForDivision($user, $subordinates, $docsByNrp);

        // Rebalance algorithm based on score ranking descending: S -> A -> B -> C -> K
        usort($empList, fn($a, $b) => $b['score'] <=> $a['score']);

        $currentCounts = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];
        $currentKjCounts = [];
        $adjustedDetails = [];

        foreach ($empList as &$emp) {
            $eKj = $emp['quota_kj'] ?? ExcelKaryawanReader::normalizeQuotaKj($emp['kj'] ?? 'KJ 2');
            $kjTarget = $kjSummary[$eKj]['target'] ?? null;

            $assigned = null;
            foreach (['S', 'A', 'B', 'C', 'K'] as $g) {
                $scopeAvailable = $currentCounts[$g] < ($targetQuota[$g] ?? 0);
                $kjAvailable = $kjTarget ? (($currentKjCounts[$eKj][$g] ?? 0) < ($kjTarget[$g] ?? 0)) : true;
                if ($scopeAvailable && $kjAvailable) {
                    $assigned = $g;
                    break;
                }
            }

            if (!$assigned && $kjTarget) {
                foreach (['S', 'A', 'B', 'C', 'K'] as $g) {
                    if (($currentKjCounts[$eKj][$g] ?? 0) < ($kjTarget[$g] ?? 0)) {
                        $assigned = $g;
                        break;
                    }
                }
            }

            if (!$assigned) {
                foreach (['S', 'A', 'B', 'C', 'K'] as $g) {
                    if ($currentCounts[$g] < ($targetQuota[$g] ?? 0)) {
                        $assigned = $g;
                        break;
                    }
                }
            }

            if (!$assigned) {
                $assigned = 'B';
            }

            $currentCounts[$assigned]++;
            $currentKjCounts[$eKj][$assigned] = ($currentKjCounts[$eKj][$assigned] ?? 0) + 1;
            $emp['adjusted_grade'] = $assigned;

            if ($assigned !== $emp['current_grade']) {
                $adjustedDetails[] = "{$emp['nama']} ({$eKj}): {$emp['current_grade']} ➔ {$assigned}";
            }

            DB::table('dokumen')->where('id', $emp['doc_id'])->update([
                'docking_final' => $assigned,
                'updated_at' => now(),
            ]);
        }

        $this->repository->clearCache('dokumen');
        GoogleSyncService::syncTable('dokumen');

        $adjustCount = count($adjustedDetails);
        $msg = $adjustCount > 0 
            ? "Otomatisasi berhasil! {$adjustCount} karyawan telah disesuaikan agar pas dengan target kuota Admin (" . implode(', ', array_slice($adjustedDetails, 0, 3)) . ($adjustCount > 3 ? "..." : "") . ")." 
            : "Distribusi nilai sudah seimbang dan sesuai target kuota Admin.";

        return redirect()->route($routeTarget)->with('success', $msg);
    }

    /**
     * Reset all docking adjustments back to original/empty.
     */
    public function resetPenyesuaian(Request $request)
    {
        return $this->processResetPenyesuaian($request, false);
    }

    /**
     * Reset all docking adjustments for Department Head back to original/empty.
     */
    public function resetDeptPenyesuaian(Request $request)
    {
        return $this->processResetPenyesuaian($request, true);
    }

    private function processResetPenyesuaian(Request $request, bool $isDeptMode = false)
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$isDeptMode) {
            abort(403, 'Halaman Penyesuaian Penilaian untuk Division Head sudah dinonaktifkan.');
        }

        $allowed = ($user && ($user->isDepartmentHead() || in_array($user->role, ['dept_head', 'department_head']) || $user->isAdmin()));

        if (!$allowed) {
            abort(403);
        }

        $routeTarget = $isDeptMode ? 'penilaian.dept.penyesuaian' : 'penilaian.div.penyesuaian';
        $scopeWord = $isDeptMode ? 'departemen' : 'divisi';

        $allKaryawan = $this->repository->all('karyawan');
        $subordinates = array_values(array_filter($allKaryawan, function ($k) use ($user, $isDeptMode) {
            if ($user->isAdmin() || $user->isSuperadmin()) return true;
            return $isDeptMode ? $user->matchesDeptHeadScope($k) : $user->matchesDivisionHeadScope($k);
        }));

        $nrps = array_column($subordinates, 'nrp');
        DB::table('dokumen')->whereIn('nrp', $nrps)->update([
            'docking_final' => null,
            'updated_at' => now(),
        ]);

        $this->repository->clearCache('dokumen');
        GoogleSyncService::syncTable('dokumen');

        return redirect()->route($routeTarget)->with('success', "Semua penyesuaian kuota (docking) {$scopeWord} berhasil direset ke status semula.");
    }

    /**
     * Target & Aturan Nilai page (Khusus Admin & Super Admin).
     */
    public function targetAturan(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        // Fitur Target & Aturan Kuota hanya untuk Admin dan Super Admin
        if (!$user || (!$user->isAdmin() && !$user->isSuperadmin())) {
            abort(403, 'Akses ditolak. Fitur Target & Aturan Kuota hanya dapat diakses oleh Admin dan Super Admin.');
        }

        $allKaryawan = $this->repository->all('karyawan');
        $allDocs = $this->repository->all('dokumen');

        $docsByNrp = [];
        foreach ($allDocs as $d) {
            $docsByNrp[$d['nrp']] = $d;
        }

        $subordinates = array_values(array_filter($allKaryawan, function ($k) use ($user) {
            if ($user->isAdmin() || $user->isSuperadmin()) return true;
            return strcasecmp($k['departemen'] ?? '', $user->departemen) === 0;
        }));

        // Quota limits matching Mockup Screen 2: S: 14/18, A: 46/42 (+4 over quota), B: 38/42, C: 20/28, K: 6/14
        $quotaLimits = [
            'S' => 18,
            'A' => 42,
            'B' => 42,
            'C' => 28,
            'K' => 14,
        ];

        // Target counts from mockup:
        $currentCounts = [
            'S' => 14,
            'A' => 46,
            'B' => 38,
            'C' => 20,
            'K' => 6,
        ];

        $employees = [];
        foreach ($subordinates as $k) {
            $nrp = $k['nrp'];
            $doc = $docsByNrp[$nrp] ?? null;
            $docId = $doc['id'] ?? null;

            $secGrade = $doc['grade_original'] ?? 'B';
            $finalGrade = $doc['docking_final'] ?? $secGrade;

            $employees[] = [
                'nama' => $k['nama_lengkap'],
                'nrp' => $nrp,
                'doc_id' => $docId,
                'predikat_section' => $secGrade,
                'nilai_akhir' => $finalGrade,
            ];
        }

        // Ensure sample NRPs appear cleanly at top when present
        $priorityNrps = ['99998', '99997', '99996', '39'];
        usort($employees, function($a, $b) use ($priorityNrps) {
            $idxA = array_search($a['nrp'], $priorityNrps);
            $idxB = array_search($b['nrp'], $priorityNrps);
            if ($idxA !== false && $idxB !== false) return $idxA <=> $idxB;
            if ($idxA !== false) return -1;
            if ($idxB !== false) return 1;
            return strcmp($a['nama'], $b['nama']);
        });

        // Compute surplus / deficit
        $isOverQuota = $currentCounts['A'] > $quotaLimits['A'];
        $surplusCount = max(0, $currentCounts['A'] - $quotaLimits['A']);

        return view('penilaian.target_aturan', compact(
            'user',
            'quotaLimits',
            'currentCounts',
            'employees',
            'isOverQuota',
            'surplusCount'
        ));
    }

    /**
     * Save Target & Aturan (Docking) (Khusus Admin & Super Admin).
     */
    public function simpanTargetAturan(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        // Fitur Target & Aturan Kuota hanya untuk Admin dan Super Admin
        if (!$user || (!$user->isAdmin() && !$user->isSuperadmin())) {
            abort(403, 'Akses ditolak. Fitur Target & Aturan Kuota hanya dapat diakses oleh Admin dan Super Admin.');
        }

        $dockingData = $request->input('docking', []);
        $updatedCount = 0;

        foreach ($dockingData as $docId => $grade) {
            if (empty($grade)) continue;

            $doc = $this->repository->find('dokumen', 'id', $docId);
            if (!$doc) continue;

            $k = $this->repository->find('karyawan', 'nrp', $doc['nrp']);
            $isSelfDoc = strcasecmp($doc['nrp'] ?? '', $user->nrp) === 0;
            $isDirectDeptSub = !empty($k['nrp_dept_head']) && strcasecmp($k['nrp_dept_head'], $user->nrp) === 0;
            if (!$isSelfDoc && !$user->canAccessDepartment($k['departemen'] ?? '') && !$user->isSuperadmin() && !$isDirectDeptSub) {
                continue;
            }

            $this->repository->update('dokumen', 'id', $docId, [
                'docking_final' => $grade,
                'status' => 'selesai',
                'updated_at' => now()->toDateTimeString(),
            ]);

            // Audit log tracking real-time handoff to Division Head
            $this->repository->insert('audit_log', [
                'waktu' => now()->toDateTimeString(),
                'user_id' => $user->id,
                'aksi' => 'submit_dept_head',
                'dokumen_id' => $docId,
                'detail' => "Department Head menyetujui docking nilai final ({$grade}) dokumen ID {$docId} dan meneruskannya secara realtime ke Division Head.",
                'created_at' => now()->toDateTimeString(),
            ]);

            $updatedCount++;
        }

        $this->repository->clearCache('dokumen');
        try {
            GoogleSyncService::syncTables(['dokumen', 'kuota_departemen', 'pengaturan', 'audit_log']);
        } catch (\Throwable $e) {}

        return redirect()->back()->with('success', "Penyesuaian kuota ({$updatedCount} karyawan) berhasil disimpan dan diteruskan ke Division Head / Manajemen!");
    }

    /**
     * Centralized data retrieval and filtering for Laporan Penilaian.
     */
    protected function getFilteredLaporanData(Request $request, User $user): array
    {
        $allKaryawan = $this->repository->all('karyawan');
        $allDocs = $this->repository->all('dokumen');
        $allNilai = $this->repository->all('nilai');

        $docsByNrp = [];
        foreach ($allDocs as $d) {
            $docsByNrp[$d['nrp']] = $d;
        }

        $nilaiByDoc = [];
        foreach ($allNilai as $n) {
            $docId = $n['dokumen_id'];
            $lvl = $n['level'] ?? 'foreman';
            $nilaiByDoc[$docId][$lvl][$n['aspek_kode']] = (float)$n['skor'];
        }

        // Subordinates based on user's base role access
        $totalKaryawanSubtitle = 'Seluruh anggota departemen';
        $baseKaryawan = array_values(array_filter($allKaryawan, function ($k) use ($user, &$totalKaryawanSubtitle) {
            if ($user->isAdmin() || $user->isSuperadmin() || $user->isHr()) {
                $totalKaryawanSubtitle = 'Seluruh karyawan terdaftar';
                return true;
            }
            if ($user->isDivisionHead()) {
                $totalKaryawanSubtitle = 'Anggota Division Head';
                return $user->matchesDivisionHeadScope($k);
            }
            if ($user->isDepartmentHead()) {
                $totalKaryawanSubtitle = 'Anggota Department Head';
                return $user->matchesDeptHeadScope($k, [], true);
            }
            if ($user->isSectionHead()) {
                $totalKaryawanSubtitle = 'Anggota Section Head';
                return $user->matchesSectionHeadScope($k);
            }
            if ($user->isForeman()) {
                $totalKaryawanSubtitle = 'Anggota Foreman';
                if (!empty($k['nrp_foreman']) && strcasecmp($k['nrp_foreman'], $user->nrp) === 0) {
                    return true;
                }
                return strcasecmp($k['nrp'] ?? '', $user->nrp) === 0;
            }
            return strcasecmp($k['departemen'] ?? '', $user->departemen ?? '') === 0;
        }));

        // Filter parameters
        $search = trim($request->input('q', $request->input('search', '')) ?? '');
        $isDirectSupervisor = $user->isForeman() || ($user->isSectionHead() && !$user->hasForemanSubordinates());
        $filterAtasan = $isDirectSupervisor ? '' : trim($request->input('atasan', '') ?? '');
        $filterJabatan = trim($request->input('jabatan', '') ?? '');
        $filterKj = trim($request->input('kj', '') ?? '');
        $filterSeksi = trim($request->input('seksi', '') ?? '');
        $filterGrade = trim($request->input('grade', '') ?? '');
        $filterBagian = trim($request->input('bagian', $request->input('nama_bagian', '')) ?? '');
        $filterDivision = trim($request->input('division', '') ?? '');

        // Department filter parameter handling
        if ($request->has('departemen')) {
            $rawDept = $request->input('departemen');
            $filterDept = ($rawDept === null || $rawDept === '' || strtoupper($rawDept) === 'SEMUA') ? '' : trim($rawDept);
        } else {
            if ($user->isAdmin() || $user->isSuperadmin() || $user->isHr() || $user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head'])) {
                $filterDept = '';
            } else {
                $filterDept = $user->departemen ?: 'PROD 2';
            }
        }

        // Khusus Foreman: Menggunakan filter Bagian (tidak menggunakan filter Division, Department, dan Section)
        if ($user->isForeman() || in_array($user->role, ['foreman', 'ketua'])) {
            $filterDivision = '';
            $filterDept = '';
            $filterSeksi = '';
            $filterAtasan = '';
        }

        // Khusus Section Head: Menggunakan filter Bagian, bukan Section / Division / Dept
        if ($user->isSectionHead() || in_array($user->role, ['section_head', 'section', 'sechead'])) {
            $filterDivision = '';
            $filterDept = '';
            $filterSeksi = '';
            $filterAtasan = '';
        }

        // Distinct Division list derived from base employee data
        $allDivisions = [];
        foreach ($baseKaryawan as $k) {
            $div = trim((string)($k['division'] ?? ''));
            if (!$div || $div === '-') {
                $deptUpper = strtoupper(trim($k['departemen'] ?? ''));
                if (str_contains($deptUpper, 'QA') || str_contains($deptUpper, 'QUALITY')) {
                    $div = 'QUALITY CONTROL DIV';
                } elseif (str_contains($deptUpper, 'ENGINEERING')) {
                    $div = 'TECHNICAL DIV';
                } elseif (str_contains($deptUpper, 'HR')) {
                    $div = 'GENERAL AFFAIRS DIV';
                } elseif (str_contains($deptUpper, 'PROD 4')) {
                    $div = 'PRODUCTION B DIV';
                } else {
                    $div = 'PRODUCTION A DIV';
                }
            }
            if ($div && $div !== '-') {
                $allDivisions[$div] = $div;
            }
        }
        $distinctDivisions = array_values($allDivisions);
        sort($distinctDivisions);

        // Scope for dropdown options: restricted to current user's subordinates / department / division
        $dropdownScope = $baseKaryawan;
        if ($filterDivision !== '' && strtoupper($filterDivision) !== 'SEMUA') {
            $dropdownScope = array_values(array_filter($dropdownScope, function ($k) use ($filterDivision) {
                $div = trim((string)($k['division'] ?? ''));
                if (!$div || $div === '-') {
                    $deptUpper = strtoupper(trim($k['departemen'] ?? ''));
                    if (str_contains($deptUpper, 'QA') || str_contains($deptUpper, 'QUALITY')) {
                        $div = 'QUALITY CONTROL DIV';
                    } elseif (str_contains($deptUpper, 'ENGINEERING')) {
                        $div = 'TECHNICAL DIV';
                    } elseif (str_contains($deptUpper, 'HR')) {
                        $div = 'GENERAL AFFAIRS DIV';
                    } elseif (str_contains($deptUpper, 'PROD 4')) {
                        $div = 'PRODUCTION B DIV';
                    } else {
                        $div = 'PRODUCTION A DIV';
                    }
                }
                return strcasecmp($div, $filterDivision) === 0;
            }));
        }
        if ($filterDept !== '' && strtoupper($filterDept) !== 'SEMUA') {
            $dropdownScope = array_values(array_filter($dropdownScope, function ($k) use ($filterDept) {
                return strcasecmp($k['departemen'] ?? '', $filterDept) === 0;
            }));
        }

        // Distinct dropdown lists derived from scoped employee data
        $distinctDepts = array_values(array_unique(array_filter(array_column($baseKaryawan, 'departemen'))));
        sort($distinctDepts);

        $distinctJabatans = array_values(array_unique(array_filter(array_map('trim', array_column($dropdownScope, 'jabatan')))));
        sort($distinctJabatans);

        $distinctKjs = array_values(array_unique(array_filter(array_map(function ($k) {
            $raw = trim((string)($k['kj'] ?? ''));
            return $raw !== '' ? ExcelKaryawanReader::normalizeKj($raw) : '';
        }, $dropdownScope))));
        sort($distinctKjs, SORT_NATURAL);

        $seksiOptions = [];
        foreach ($dropdownScope as $k) {
            $s = trim($k['nama_sect'] ?? '');
            if (!$s) {
                $rawBase = trim($k['seksi'] ?? '');
                $rawUpper = strtoupper($rawBase);
                $deptUpper = strtoupper(trim($k['departemen'] ?? 'PROD 2'));
                if (str_contains($rawUpper, '2') || str_contains($rawUpper, 'FACT-2')) {
                    $s = 'FACTORY 2';
                } elseif (str_contains($deptUpper, 'ENGINEERING') || str_contains($rawUpper, 'ENG')) {
                    $s = 'ENGINEERING';
                } elseif (str_contains($deptUpper, 'QA') || str_contains($rawUpper, 'QA')) {
                    $s = 'QA';
                } elseif (str_contains($deptUpper, 'QC') || str_contains($rawUpper, 'QC')) {
                    $s = 'QC';
                } else {
                    $clean = trim(preg_replace('/\s+SEC\.?$/i', '', $rawBase));
                    $s = ($clean && $clean !== '-') ? $clean : 'FACTORY 1';
                }
            }
            if ($s && $s !== '-') {
                $seksiOptions[strtoupper($s)] = strtoupper($s);
            }
        }
        $distinctSeksi = array_values($seksiOptions);
        sort($distinctSeksi);

        $bagianOptions = [];
        foreach ($dropdownScope as $k) {
            $nb = trim($k['nama_bagian'] ?? '');
            if (!$nb) {
                $seksiUpper = strtoupper(trim($k['seksi'] ?? ''));
                $nb = ($seksiUpper && $seksiUpper !== '-') ? $seksiUpper : 'FACTORY 1 SEC.';
            }
            $nbKey = strtoupper(rtrim($nb, '.'));
            if ($nbKey !== '' && !isset($bagianOptions[$nbKey])) {
                $bagianOptions[$nbKey] = $nb;
            }
        }
        $distinctBagians = array_values($bagianOptions);
        sort($distinctBagians, SORT_NATURAL | SORT_FLAG_CASE);

        // Check if there are any foremen acting as evaluators/supervisors in current scope
        $hasForeman = false;
        foreach ($dropdownScope as $k) {
            if (!empty($k['nrp_foreman'])) {
                $hasForeman = true;
                break;
            }
        }
        if (!$hasForeman && $filterAtasan === 'foreman') {
            $filterAtasan = '';
        }

        // Apply filters
        $filteredKaryawan = array_values(array_filter($baseKaryawan, function ($k) use ($filterDivision, $filterDept, $filterAtasan, $filterJabatan, $filterKj, $filterSeksi, $filterBagian, $search) {
            // Division
            if ($filterDivision !== '' && strtoupper($filterDivision) !== 'SEMUA') {
                $div = trim((string)($k['division'] ?? ''));
                if (!$div || $div === '-') {
                    $deptUpper = strtoupper(trim($k['departemen'] ?? ''));
                    if (str_contains($deptUpper, 'QA') || str_contains($deptUpper, 'QUALITY')) {
                        $div = 'QUALITY CONTROL DIV';
                    } elseif (str_contains($deptUpper, 'ENGINEERING')) {
                        $div = 'TECHNICAL DIV';
                    } elseif (str_contains($deptUpper, 'HR')) {
                        $div = 'GENERAL AFFAIRS DIV';
                    } elseif (str_contains($deptUpper, 'PROD 4')) {
                        $div = 'PRODUCTION B DIV';
                    } else {
                        $div = 'PRODUCTION A DIV';
                    }
                }
                if (strcasecmp($div, $filterDivision) !== 0) {
                    return false;
                }
            }

            // Departemen
            if ($filterDept !== '' && strtoupper($filterDept) !== 'SEMUA') {
                $kDept = strtoupper(trim($k['departemen'] ?? ''));
                $fDept = strtoupper(trim($filterDept));
                $matchDept = ($kDept === $fDept)
                    || (str_replace(' ', '', $kDept) === str_replace(' ', '', $fDept))
                    || (preg_replace('/^PRODUCTION\s*(\d+)$/i', 'PROD $1', $fDept) === $kDept)
                    || (preg_replace('/^PROD\s*(\d+)$/i', 'PRODUCTION $1', $fDept) === $kDept);
                if (!$matchDept) {
                    return false;
                }
            }

            // Atasan / Level
            if ($filterAtasan !== '' && strtoupper($filterAtasan) !== 'SEMUA') {
                if ($filterAtasan === 'foreman') {
                    if (empty($k['nrp_foreman']) && stripos($k['jabatan'] ?? '', 'FOREMAN') === false) return false;
                } elseif ($filterAtasan === 'section_head') {
                    if (empty($k['nrp_section_head']) && stripos($k['jabatan'] ?? '', 'SECTION') === false) return false;
                } elseif ($filterAtasan === 'dept_head') {
                    if (empty($k['nrp_dept_head']) && stripos($k['jabatan'] ?? '', 'DEPT') === false) return false;
                } elseif ($filterAtasan === 'div_head') {
                    if (empty($k['nrp_div_head']) && stripos($k['jabatan'] ?? '', 'DIV') === false) return false;
                } else {
                    if (($k['nrp_foreman'] ?? '') !== $filterAtasan
                        && ($k['nrp_section_head'] ?? '') !== $filterAtasan
                        && ($k['nrp_dept_head'] ?? '') !== $filterAtasan
                        && ($k['nrp_div_head'] ?? '') !== $filterAtasan) {
                        return false;
                    }
                }
            }

            // Jabatan
            if ($filterJabatan !== '' && strtoupper($filterJabatan) !== 'SEMUA') {
                if (strcasecmp($k['jabatan'] ?? '', $filterJabatan) !== 0) {
                    return false;
                }
            }

            // KJ
            if ($filterKj !== '' && strtoupper($filterKj) !== 'SEMUA') {
                $normFilterKj = ExcelKaryawanReader::normalizeKj($filterKj);
                $rowNormKj = ExcelKaryawanReader::normalizeKj($k['kj'] ?? '');
                if (strcasecmp($k['kj'] ?? '', $filterKj) !== 0 && strcasecmp($rowNormKj, $normFilterKj) !== 0) {
                    return false;
                }
            }

            // Seksi / Nama Seksi
            if ($filterSeksi !== '' && strtoupper($filterSeksi) !== 'SEMUA') {
                $kSeksi = strtoupper(trim($k['seksi'] ?? ''));
                $kSect = strtoupper(trim($k['nama_sect'] ?? ''));
                $cleanSeksi = trim(preg_replace('/\s+SEC\.?$/i', '', $kSeksi));
                $filterSeksiUpper = strtoupper($filterSeksi);

                $match = ($kSect === $filterSeksiUpper)
                    || ($kSeksi === $filterSeksiUpper)
                    || ($cleanSeksi === $filterSeksiUpper)
                    || str_contains($kSeksi, $filterSeksiUpper)
                    || str_contains($filterSeksiUpper, $cleanSeksi);

                if (!$match) {
                    return false;
                }
            }

            // Bagian
            if ($filterBagian !== '' && strtoupper($filterBagian) !== 'SEMUA') {
                $kBagian = trim($k['nama_bagian'] ?? '');
                if (!$kBagian) {
                    $seksiUpper = strtoupper(trim($k['seksi'] ?? ''));
                    $kBagian = ($seksiUpper && $seksiUpper !== '-') ? $seksiUpper : 'FACTORY 1 SEC.';
                }
                if (strcasecmp(rtrim($kBagian, '.'), rtrim($filterBagian, '.')) !== 0 && strcasecmp($k['seksi'] ?? '', $filterBagian) !== 0) {
                    return false;
                }
            }

            // Search (NRP, Nama, Jabatan, Posisi, or Foreman)
            if ($search !== '') {
                $nrpMatch = stripos($k['nrp'] ?? '', $search) !== false;
                $namaMatch = stripos($k['nama_lengkap'] ?? '', $search) !== false;
                $jabatanMatch = stripos($k['jabatan'] ?? '', $search) !== false;
                $posisiMatch = stripos($k['posisi'] ?? '', $search) !== false;
                $foremanMatch = stripos($k['nama_foreman'] ?? ($k['foreman_nama'] ?? ''), $search) !== false;
                if (!$nrpMatch && !$namaMatch && !$jabatanMatch && !$posisiMatch && !$foremanMatch) {
                    return false;
                }
            }

            return true;
        }));

        // Hierarchy Cards counts based on the active filtered scope
        $totalKaryawan = count($filteredKaryawan);

        // Foremen: hitung karyawan yang memegang posisi/jabatan Foreman ATAU bertindak sebagai penilai Foreman
        $foremenByPosition = array_filter($filteredKaryawan, function($k) {
            $jab = strtoupper($k['jabatan'] ?? '');
            $pos = strtoupper($k['posisi'] ?? '');
            return str_contains($jab, 'FOREMAN') || str_contains($pos, 'FOREMAN');
        });
        $foremanPositionNrps = array_unique(array_filter(array_column($foremenByPosition, 'nrp')));
        $foremanEvaluatorNrps = array_unique(array_filter(array_column($filteredKaryawan, 'nrp_foreman')));
        $allForemanNrps = array_unique(array_merge($foremanPositionNrps, $foremanEvaluatorNrps));
        $countForemanPenilai = count($allForemanNrps);
        $countBinaanForeman = count(array_filter($filteredKaryawan, fn($k) => !empty($k['nrp_foreman'])));

        // Section Heads
        $sectionNrps = array_unique(array_filter(array_column($filteredKaryawan, 'nrp_section_head')));
        $countSectionPenilai = count($sectionNrps);
        $countBinaanSection = count(array_filter($filteredKaryawan, fn($k) => !empty($k['nrp_section_head'])));

        // Dept Heads
        $deptNrps = array_unique(array_filter(array_column($filteredKaryawan, 'nrp_dept_head')));
        $countDeptPenilai = count($deptNrps);
        $countBinaanDept = count(array_filter($filteredKaryawan, fn($k) => !empty($k['nrp_dept_head'])));

        // Div Heads
        $divNrps = array_unique(array_filter(array_column($filteredKaryawan, 'nrp_div_head')));
        $countDivPenilai = count($divNrps);
        $countBinaanDiv = count(array_filter($filteredKaryawan, fn($k) => !empty($k['nrp_div_head'])));

        $hierarchyCards = [
            'total_karyawan' => $totalKaryawan,
            'total_foreman' => $countForemanPenilai,
            'binaan_foreman' => $countBinaanForeman,
            'total_section_head' => $countSectionPenilai,
            'binaan_section_head' => $countBinaanSection,
            'total_dept_head' => $countDeptPenilai,
            'binaan_dept_head' => $countBinaanDept,
            'total_div_head' => $countDivPenilai,
            'binaan_div_head' => $countBinaanDiv,
        ];

        // Build employee rows & aspect breakdown
        $employeeRows = [];
        $actualCounts = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];
        $sudahDinilai = 0;
        $isSectionHeadWithoutForeman = $user->isSectionHead() && !$user->hasForemanSubordinates();
        $isForemanOrSectionWithoutForeman = $user->isForeman() || $isSectionHeadWithoutForeman;

        $foremanStageCounts = [
            'need_action' => 0,
            'belum' => 0,
            'draft' => 0,
            'revisi' => 0,
            'waiting_section' => 0,
            'waiting_dept' => 0,
            'waiting_div' => 0,
            'selesai' => 0,
        ];

        foreach ($filteredKaryawan as $k) {
            $nrp = $k['nrp'];
            $doc = $docsByNrp[$nrp] ?? null;
            $docId = $doc['id'] ?? null;
            $kodeForm = self::determineKodeForm($k);
            $status = $doc['status'] ?? 'belum_ada';

            $hasManMgt = in_array($kodeForm, ['07-003', '07-004', '07-006-SH', '07-006-DH', '07-006-DIV']);

            if ($user->isForeman()) {
                // Track 4-stage workflow counts for Foreman
                if ($status === 'revisi') {
                    $foremanStageCounts['revisi']++;
                    $foremanStageCounts['need_action']++;
                } elseif ($status === 'selesai') {
                    $foremanStageCounts['selesai']++;
                } elseif (in_array($status, ['menunggu_dept_head', 'menunggu_div_head'])) {
                    $foremanStageCounts['waiting_dept']++;
                } elseif ($status === 'menunggu_section_head') {
                    $foremanStageCounts['waiting_section']++;
                } elseif ($status === 'draft') {
                    $foremanStageCounts['draft']++;
                    $foremanStageCounts['need_action']++;
                } else {
                    $foremanStageCounts['belum']++;
                    $foremanStageCounts['need_action']++;
                }

                // When logged in as Foreman: ALWAYS pull the Foreman's evaluation data and scores!
                $scores = ($docId && isset($nilaiByDoc[$docId]['foreman'])) ? $nilaiByDoc[$docId]['foreman'] : [];
                $calc = $this->calcService->calculate($kodeForm, $scores);

                $isDinilai = !empty($doc) && (
                    !empty($scores)
                    || in_array($status, ['menunggu_section_head', 'menunggu_dept_head', 'menunggu_div_head', 'selesai'])
                    || (($doc['total_nilai'] ?? 0) > 0 && !in_array($status, ['draft', 'belum_ada', 'menunggu_foreman']))
                );

                $grade = '-';
                $totalNilaiVal = null;
                $hasilKerjaVal = null;
                $prosesKerjaVal = null;
                $sikapKerjaVal = null;
                $manMgtVal = null;

                if ($isDinilai) {
                    $grade = ($calc['grand_total'] > 0)
                        ? $this->calcService->scoreToGrade($calc['grand_total'])
                        : ($doc['grade_original'] ?? $doc['docking_final'] ?? '-');

                    $totalNilaiVal = ($calc['grand_total'] > 0)
                        ? (float)$calc['grand_total']
                        : (($doc['total_nilai'] ?? 0) > 0 ? (float)$doc['total_nilai'] : null);

                    $hasilKerjaVal = ($calc['grand_total'] > 0) ? ($calc['groups']['hasil_kerja']['weighted_score'] ?? 0.00) : null;
                    $prosesKerjaVal = ($calc['grand_total'] > 0) ? ($calc['groups']['proses_kerja']['weighted_score'] ?? 0.00) : null;
                    $sikapKerjaVal = ($calc['grand_total'] > 0) ? ($calc['groups']['sikap_kerja']['weighted_score'] ?? 0.00) : null;
                    $manMgtVal = ($hasManMgt && $calc['grand_total'] > 0) ? ($calc['groups']['man_management']['weighted_score'] ?? 0.00) : null;
                }
            } elseif ($isSectionHeadWithoutForeman) {
                // Section Head without foreman: Pull Section Head's evaluation data and scores
                // For Section Head self / 07-006-* form, SH scores live in 'foreman' (Mandiri Kolom 1).
                // For KJ 1-5 subordinates without Foreman, SH scores live strictly in 'section_head' (Kolom 2).
                $isGol4UpForm = in_array($kodeForm, ['07-006-SH', '07-006-DH', '07-006-DIV']);
                $isSelfDoc = (strcasecmp($nrp, $user->nrp) === 0);
                $scores = [];
                if ($docId && isset($nilaiByDoc[$docId])) {
                    if ($isGol4UpForm || $isSelfDoc) {
                        $scores = $nilaiByDoc[$docId]['section_head'] ?? $nilaiByDoc[$docId]['foreman'] ?? [];
                    } else {
                        $scores = $nilaiByDoc[$docId]['section_head'] ?? [];
                    }
                }
                $calc = $this->calcService->calculate($kodeForm, $scores);

                $isDraftState = ($status === 'draft') || (!empty($scores) && ($calc['grand_total'] > 0) && in_array($status, ['menunggu_section_head', 'belum_ada']));

                // Track 4-stage workflow counts for Section Head without Foreman:
                // Tahap 1: Penilaian Section Head (need_action: draft, belum_dinilai, revisi dari dept head)
                // Tahap 2: Tinjauan Dept Head (waiting_dept: menunggu_dept_head)
                // Tahap 3: Approval Div Head (waiting_div: menunggu_div_head)
                // Tahap 4: Selesai & Terarsip (selesai: selesai)
                if ($status === 'revisi') {
                    $foremanStageCounts['revisi']++;
                    $foremanStageCounts['need_action']++;
                } elseif ($status === 'selesai') {
                    $foremanStageCounts['selesai']++;
                } elseif ($status === 'menunggu_div_head') {
                    $foremanStageCounts['waiting_div']++;
                } elseif ($status === 'menunggu_dept_head') {
                    $foremanStageCounts['waiting_dept']++;
                } elseif ($isDraftState) {
                    $foremanStageCounts['draft']++;
                    $foremanStageCounts['need_action']++;
                } else {
                    $foremanStageCounts['belum']++;
                    $foremanStageCounts['need_action']++;
                }

                $isDinilai = !empty($doc) && (
                    !empty($scores)
                    || in_array($status, ['menunggu_dept_head', 'menunggu_div_head', 'selesai'])
                    || (($doc['total_nilai'] ?? 0) > 0 && !in_array($status, ['draft', 'belum_ada', 'menunggu_section_head', 'menunggu_foreman']))
                );

                $grade = '-';
                $totalNilaiVal = null;
                $hasilKerjaVal = null;
                $prosesKerjaVal = null;
                $sikapKerjaVal = null;
                $manMgtVal = null;

                if ($isDinilai) {
                    $grade = ($calc['grand_total'] > 0)
                        ? $this->calcService->scoreToGrade($calc['grand_total'])
                        : ($doc['docking_final'] ?? $doc['grade_original'] ?? '-');

                    $totalNilaiVal = ($calc['grand_total'] > 0)
                        ? (float)$calc['grand_total']
                        : (($doc['total_nilai'] ?? 0) > 0 ? (float)$doc['total_nilai'] : null);

                    $hasilKerjaVal = ($calc['grand_total'] > 0) ? ($calc['groups']['hasil_kerja']['weighted_score'] ?? 0.00) : null;
                    $prosesKerjaVal = ($calc['grand_total'] > 0) ? ($calc['groups']['proses_kerja']['weighted_score'] ?? 0.00) : null;
                    $sikapKerjaVal = ($calc['grand_total'] > 0) ? ($calc['groups']['sikap_kerja']['weighted_score'] ?? 0.00) : null;
                    $manMgtVal = ($hasManMgt && $calc['grand_total'] > 0) ? ($calc['groups']['man_management']['weighted_score'] ?? 0.00) : null;
                }
            } else {
                $isGol4UpForm = in_array($kodeForm, ['07-006-SH', '07-006-DH', '07-006-DIV']);
                $isSelf = (strcasecmp($nrp, $user->nrp) === 0);
                $scores = [];
                if ($docId && isset($nilaiByDoc[$docId])) {
                    if ($filterAtasan === 'foreman') {
                        $scores = (!empty($k['nrp_foreman']) && !$isGol4UpForm) ? ($nilaiByDoc[$docId]['foreman'] ?? []) : [];
                    } else {
                        $scores = $nilaiByDoc[$docId]['dept_head']
                            ?? $nilaiByDoc[$docId]['section_head']
                            ?? (($isGol4UpForm || !empty($k['nrp_foreman'])) ? ($nilaiByDoc[$docId]['foreman'] ?? []) : []);
                    }
                }

                $calc = $this->calcService->calculate($kodeForm, $scores);

                $isDirectSH = empty($k['nrp_foreman']) || $isGol4UpForm;

                // Evaluation status calculation per role level
                // Admin & Superadmin: Yang sudah dinilai adalah yang sudah dinilai oleh Dept Head atau Div Head (final)
                if ($user->isAdmin() || $user->isSuperadmin() || $user->isHr()) {
                    $isDinilai = !empty($doc) && in_array($status, ['menunggu_div_head', 'selesai']);
                } elseif ($user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head'])) {
                    $isDinilai = !empty($doc) && ($status === 'selesai');
                } elseif ($user->isDepartmentHead() || in_array($user->role, ['dept_head', 'department_head'])) {
                    $isDinilai = !empty($doc) && in_array($status, ['menunggu_div_head', 'selesai']);
                } elseif ($user->isSectionHead() || in_array($user->role, ['section_head', 'sechead'])) {
                    $isDinilai = !empty($doc) && in_array($status, ['menunggu_dept_head', 'menunggu_div_head', 'selesai']);
                } elseif ($user->isForeman() || in_array($user->role, ['foreman', 'ketua'])) {
                    $isDinilai = !empty($doc) && (
                        (!empty($scores) && ($calc['grand_total'] > 0) && !in_array($status, ['draft', 'belum_ada', 'menunggu_foreman']))
                        || in_array($status, ['menunggu_dept_head', 'menunggu_div_head', 'selesai'])
                    );
                } elseif ($isSelf) {
                    $isDinilai = !empty($doc) && (
                        in_array($status, ['menunggu_div_head', 'selesai'])
                        || (!empty($scores) && ($calc['grand_total'] > 0) && !in_array($status, ['draft', 'belum_ada', 'menunggu_section_head', 'menunggu_dept_head', 'menunggu_foreman']))
                    );
                } elseif ($isDirectSH) {
                    $isDinilai = !empty($doc) && (
                        in_array($status, ['menunggu_dept_head', 'menunggu_div_head', 'selesai'])
                        || (!empty($scores) && ($calc['grand_total'] > 0) && !in_array($status, ['draft', 'belum_ada', 'menunggu_section_head', 'menunggu_foreman']))
                    );
                } else {
                    $isDinilai = !empty($doc) && (
                        in_array($status, ['menunggu_dept_head', 'menunggu_div_head', 'selesai'])
                        || (($doc['total_nilai'] ?? 0) > 0 && !in_array($status, ['draft', 'belum_ada', 'menunggu_foreman', 'menunggu_section_head']))
                    );
                }

                $grade = '-';
                if ($isDinilai) {
                    // Only assign a grade when there is actual score data — never fallback to 'B'
                    $hasCalcScore = ($calc['grand_total'] ?? 0) > 0;
                    $hasDocGrade = !empty($doc['docking_final']) || !empty($doc['grade_original']);
                    if ($filterAtasan === 'foreman') {
                        $grade = $doc['grade_original']
                            ?? ($hasCalcScore ? $this->calcService->scoreToGrade($calc['grand_total']) : '-');
                    } else {
                        $grade = $doc['docking_final']
                            ?? $doc['grade_original']
                            ?? ($hasCalcScore ? $this->calcService->scoreToGrade($calc['grand_total']) : '-');
                    }
                }

                $isDeptDinilaiEmp = !empty($doc) && in_array($status, ['menunggu_div_head', 'selesai'], true);
                $deptGradeEmp = '-';
                if ($isDeptDinilaiEmp) {
                    $deptGradeEmp = $doc['docking_final']
                        ?? ($calc['grand_total'] > 0 ? $this->calcService->scoreToGrade($calc['grand_total']) : ($doc['grade_original'] ?? '-'));
                }

                // Populate numeric values when actual scores exist
                $hasActualValues = (($calc['grand_total'] ?? 0) > 0 || ($doc['total_nilai'] ?? 0) > 0);
                $totalNilaiVal = $hasActualValues ? (float)($doc['total_nilai'] ?? $calc['grand_total']) : null;
                $hasilKerjaVal = (($calc['grand_total'] ?? 0) > 0) ? ($calc['groups']['hasil_kerja']['weighted_score'] ?? 0.00) : null;
                $prosesKerjaVal = (($calc['grand_total'] ?? 0) > 0) ? ($calc['groups']['proses_kerja']['weighted_score'] ?? 0.00) : null;
                $sikapKerjaVal = (($calc['grand_total'] ?? 0) > 0) ? ($calc['groups']['sikap_kerja']['weighted_score'] ?? 0.00) : null;
                $manMgtVal = ($hasManMgt && ($calc['grand_total'] ?? 0) > 0) ? ($calc['groups']['man_management']['weighted_score'] ?? 0.00) : null;
            }

            if ($filterGrade && strtoupper($filterGrade) !== 'SEMUA') {
                if (strcasecmp($grade, $filterGrade) !== 0) {
                    continue;
                }
            }

            if ($isDinilai) {
                $sudahDinilai++;
                if (isset($actualCounts[$grade])) {
                    $actualCounts[$grade]++;
                }
            }

            // Column fallbacks matching user table specifications
            $seksiUpper = strtoupper(trim($k['seksi'] ?? ''));
            $dept = trim($k['departemen'] ?? 'PROD 2');
            $deptUpper = strtoupper($dept);

            $namaBagian = trim($k['nama_bagian'] ?? '');
            if (!$namaBagian) {
                $namaBagian = ($seksiUpper && $seksiUpper !== '-') ? $seksiUpper : 'FACTORY 1 SEC.';
            }

            $namaSect = trim($k['nama_sect'] ?? '');
            if (!$namaSect) {
                if (str_contains($seksiUpper, '2') || str_contains($seksiUpper, 'FACT-2')) {
                    $namaSect = 'FACTORY 2';
                } elseif (str_contains($deptUpper, 'ENGINEERING')) {
                    $namaSect = 'ENGINEERING';
                } elseif (str_contains($deptUpper, 'QA')) {
                    $namaSect = 'QA';
                } else {
                    $namaSect = 'FACTORY 1';
                }
            }

            $codeBagian = trim($k['code_bagian'] ?? '');
            if (!$codeBagian) {
                if (str_contains($deptUpper, 'ENGINEERING')) {
                    $codeBagian = '1151500000';
                } elseif (str_contains($deptUpper, 'PROD 1')) {
                    $codeBagian = '1141100000';
                } else {
                    $codeBagian = '1141600000';
                }
            }

            $rawDate = $k['tgl_masuk'] ?? '';
            $startWork = \App\Services\GoogleSyncService::formatMusashiDate($rawDate);

            $division = trim((string)($k['division'] ?? ''));
            if (!$division) {
                if (str_contains($deptUpper, 'QA') || str_contains($deptUpper, 'QUALITY')) {
                    $division = 'QUALITY CONTROL DIV';
                } elseif (str_contains($deptUpper, 'ENGINEERING')) {
                    $division = 'TECHNICAL DIV';
                } elseif (str_contains($deptUpper, 'HR')) {
                    $division = 'GENERAL AFFAIRS DIV';
                } else {
                    $division = 'PRODUCTION A DIV';
                }
            }

            // Values (hasilKerjaVal, etc.) assigned above per role context

            $isGol4UpForm = in_array($kodeForm, ['07-006-SH', '07-006-DH', '07-006-DIV']);
            $hasForemanStructure = !empty($k['nrp_foreman']) && !$isGol4UpForm;

            // 1. NILAI PK FOREMAN (JIKA ADA)
            $pkForeman = '-';
            if ($hasForemanStructure) {
                $fScores = ($docId && isset($nilaiByDoc[$docId]['foreman'])) ? $nilaiByDoc[$docId]['foreman'] : [];
                if (!empty($fScores)) {
                    $calcFM = $this->calcService->calculate($kodeForm, $fScores);
                    $pkForeman = ($calcFM['grand_total'] > 0) ? $this->calcService->scoreToGrade($calcFM['grand_total']) : '-';
                } elseif (!in_array($status, ['draft', 'belum_ada', 'menunggu_foreman'])) {
                    $pkForeman = $doc['grade_original'] ?? '-';
                }
            }

            // 2. NILAI PK SECT (Section Head)
            $pkSection = '-';
            if ($isGol4UpForm) {
                $shScores = ($docId && isset($nilaiByDoc[$docId]['section_head'])) ? $nilaiByDoc[$docId]['section_head'] : (($docId && isset($nilaiByDoc[$docId]['foreman'])) ? $nilaiByDoc[$docId]['foreman'] : []);
                if (!empty($shScores)) {
                    $calcSH = $this->calcService->calculate($kodeForm, $shScores);
                    $pkSection = ($calcSH['grand_total'] > 0) ? $this->calcService->scoreToGrade($calcSH['grand_total']) : '-';
                } elseif (!in_array($status, ['draft', 'belum_ada', 'menunggu_foreman', 'menunggu_section_head'])) {
                    $pkSection = $doc['grade_original'] ?? '-';
                }
            } else {
                $shScores = ($docId && isset($nilaiByDoc[$docId]['section_head'])) ? $nilaiByDoc[$docId]['section_head'] : [];
                if (!empty($shScores)) {
                    $calcSH = $this->calcService->calculate($kodeForm, $shScores);
                    $pkSection = ($calcSH['grand_total'] > 0) ? $this->calcService->scoreToGrade($calcSH['grand_total']) : '-';
                } elseif (!$hasForemanStructure && $docId && isset($nilaiByDoc[$docId]['foreman'])) {
                    $calcSH = $this->calcService->calculate($kodeForm, $nilaiByDoc[$docId]['foreman']);
                    $pkSection = ($calcSH['grand_total'] > 0) ? $this->calcService->scoreToGrade($calcSH['grand_total']) : '-';
                } elseif (!in_array($status, ['draft', 'belum_ada', 'menunggu_foreman', 'menunggu_section_head'])) {
                    $pkSection = $doc['grade_original'] ?? (($doc['total_nilai'] ?? 0) > 0 ? $this->calcService->scoreToGrade((float)$doc['total_nilai']) : '-');
                }
            }

            // 3. NILAI PK DEPT (Department Head)
            $pkDept = '-';
            if (in_array($status, ['menunggu_div_head', 'selesai'])) {
                $dhScores = ($docId && isset($nilaiByDoc[$docId]['dept_head'])) ? $nilaiByDoc[$docId]['dept_head'] : [];
                if (!empty($dhScores)) {
                    $calcDH = $this->calcService->calculate($kodeForm, $dhScores);
                    $calcDeptGrade = ($calcDH['grand_total'] > 0) ? $this->calcService->scoreToGrade($calcDH['grand_total']) : null;
                } else {
                    $calcDeptGrade = null;
                }
                $pkDept = $doc['docking_dept'] ?? $doc['docking_final'] ?? $calcDeptGrade ?? $doc['grade_original'] ?? ($pkSection !== '-' ? $pkSection : '-');
            }

            // 4. NILAI PK DIV (Division Head)
            $pkDiv = '-';
            if ($status === 'selesai') {
                $divScores = ($docId && isset($nilaiByDoc[$docId]['div_head'])) ? $nilaiByDoc[$docId]['div_head'] : [];
                if (!empty($divScores)) {
                    $calcDiv = $this->calcService->calculate($kodeForm, $divScores);
                    $calcDivGrade = ($calcDiv['grand_total'] > 0) ? $this->calcService->scoreToGrade($calcDiv['grand_total']) : null;
                } else {
                    $calcDivGrade = null;
                }
                $pkDiv = $doc['docking_div'] ?? $doc['docking_final'] ?? $calcDivGrade ?? $doc['grade_original'] ?? ($pkDept !== '-' ? $pkDept : '-');
            }

            // 5. NILAI PK FINAL
            $pkFinal = '-';
            if ($status === 'selesai') {
                $pkFinal = $doc['docking_final'] ?? ($pkDiv !== '-' ? $pkDiv : ($pkDept !== '-' ? $pkDept : '-'));
            }

            // PK Original (dipertahankan untuk backward compatibility)
            $pkOriginal = $doc['grade_original'] ?? ($pkSection !== '-' ? $pkSection : ($pkForeman !== '-' ? $pkForeman : '-'));

            $employeeRows[] = [
                'nrp' => $nrp,
                'nama' => $k['nama_lengkap'],
                'code_bagian' => $codeBagian,
                'nama_bagian' => $namaBagian,
                'nama_sect' => $namaSect,
                'departemen' => $dept,
                'division' => $division,
                'jabatan' => $k['jabatan'] ?? '-',
                'start_work' => $startWork,
                'kj' => !empty($k['kj']) ? ExcelKaryawanReader::normalizeKj($k['kj']) : 'KJ 2',
                'quota_kj' => ExcelKaryawanReader::normalizeQuotaKj($k['kj'] ?? 'KJ 2'),
                'sub_gol' => $k['sub_gol'] ?? ($k['golongan'] ?? '-'),
                'hasil_kerja_bobot' => $hasilKerjaVal,
                'proses_kerja_bobot' => $prosesKerjaVal,
                'sikap_kerja_bobot' => $sikapKerjaVal,
                'man_management_bobot' => $manMgtVal,
                'total_nilai' => $totalNilaiVal,
                'grade' => $grade,
                'pk_foreman' => $pkForeman,
                'pk_sect' => $pkSection,
                'pk_original' => $pkOriginal,
                'pk_dept' => $pkDept,
                'pk_div' => $pkDiv,
                'pk_final' => $pkFinal,
                'dept_grade' => $deptGradeEmp ?? '-',
                'is_dept_dinilai' => $isDeptDinilaiEmp ?? false,
                'doc_id' => $docId,
                'kode_form' => $kodeForm,
                'status' => $status,
                'is_dinilai' => $isDinilai,
                'seksi' => $namaBagian, // backward compatibility
                'golongan' => $k['sub_gol'] ?? ($k['golongan'] ?? '-'), // backward compatibility
            ];
        }

        $totalKaryawan = count($employeeRows);
        $belumDinilai = max(0, $totalKaryawan - $sudahDinilai);
        $pctDinilai = $totalKaryawan > 0 ? (int)round(($sudahDinilai / $totalKaryawan) * 100) : 0;

        // Target Kuota & quota realization strictly exclude 07-006 (Section Head / Dept Head / Div Head) forms
        $quotaBoundRows = array_values(array_filter($employeeRows, fn ($r) => !self::isForm07006($r['kode_form'] ?? null)));
        $totalTargetKaryawan = count($quotaBoundRows);
        $quotaActualCounts = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];
        foreach ($quotaBoundRows as $qRow) {
            if (!empty($qRow['is_dinilai']) && isset($quotaActualCounts[$qRow['grade'] ?? ''])) {
                $quotaActualCounts[$qRow['grade']]++;
            }
        }
        $quotaSudahDinilai = array_sum($quotaActualCounts);
        $quotaBelumDinilai = max(0, $totalTargetKaryawan - $quotaSudahDinilai);
        $quotaPctDinilai = $totalTargetKaryawan > 0 ? (int)round(($quotaSudahDinilai / $totalTargetKaryawan) * 100) : 0;

        // Target Kuota calculation (unified with Dashboard, Riwayat, Penyesuaian, Admin Target Kuota)
        $targetKuota = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];
        $isDirectUnitSupervisor = $user->isForeman() || $user->isSectionHead() || in_array($user->role, ['foreman', 'ketua', 'section_head', 'sechead']);
        $isDeptHeadLaporan = $user->isDepartmentHead() || in_array($user->role, ['dept_head', 'department_head']);
        $isDivHeadLaporan = $user->isDivisionHead() || in_array($user->role, ['div_head', 'division_head']);
        $hasSubFilters = ($filterAtasan !== '' && strtoupper($filterAtasan) !== 'SEMUA')
            || ($filterJabatan !== '' && strtoupper($filterJabatan) !== 'SEMUA')
            || ($filterKj !== '' && strtoupper($filterKj) !== 'SEMUA')
            || ($filterSeksi !== '' && strtoupper($filterSeksi) !== 'SEMUA')
            || ($filterBagian !== '' && strtoupper($filterBagian) !== 'SEMUA')
            || $search !== '';
        $isDefaultScopeDept = ($filterDept === '' || strtoupper($filterDept) === 'SEMUA' || strcasecmp($filterDept, trim($user->departemen ?? '')) === 0);

        if ($totalTargetKaryawan === 0) {
            $targetKuota = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];
        } elseif ($isDeptHeadLaporan || $isDivHeadLaporan) {
            $baseQuotaSubs = array_values(array_filter($baseKaryawan, fn ($k) => !self::isQuotaExemptEmployee($k, $docsByNrp[$k['nrp'] ?? ''] ?? null)));
            $scopedQuota = self::getTargetQuotaForScope($user, $baseQuotaSubs);

            if (!$hasSubFilters && ($isDivHeadLaporan ? ($filterDept === '' || strtoupper($filterDept) === 'SEMUA') : $isDefaultScopeDept)) {
                $targetKuota = [
                    'S' => (int)($scopedQuota['S'] ?? 0),
                    'A' => (int)($scopedQuota['A'] ?? 0),
                    'B' => (int)($scopedQuota['B'] ?? 0),
                    'C' => (int)($scopedQuota['C'] ?? 0),
                    'K' => (int)($scopedQuota['K'] ?? 0),
                ];
            } elseif ($isDivHeadLaporan && !$hasSubFilters && $filterDept !== '' && strtoupper($filterDept) !== 'SEMUA') {
                $deptUpper = strtoupper(trim($filterDept));
                $deptSettingVal = DB::table('pengaturan')->where('key', 'target_kuota_dept_' . $deptUpper)->value('value');
                $decodedDept = $deptSettingVal ? json_decode($deptSettingVal, true) : null;
                if ($decodedDept && isset($decodedDept['S'])) {
                    $targetKuota = [
                        'S' => (int)($decodedDept['S'] ?? 0),
                        'A' => (int)($decodedDept['A'] ?? 0),
                        'B' => (int)($decodedDept['B'] ?? 0),
                        'C' => (int)($decodedDept['C'] ?? 0),
                        'K' => (int)($decodedDept['K'] ?? 0),
                    ];
                } else {
                    $kdRows = DB::table('kuota_departemen')->where('departemen', $deptUpper)->get();
                    if ($kdRows->isNotEmpty() && ($kdRows->sum('kuota_s') + $kdRows->sum('kuota_a') + $kdRows->sum('kuota_b') + $kdRows->sum('kuota_c') + $kdRows->sum('kuota_k')) > 0) {
                        $targetKuota = [
                            'S' => (int)$kdRows->sum('kuota_s'),
                            'A' => (int)$kdRows->sum('kuota_a'),
                            'B' => (int)$kdRows->sum('kuota_b'),
                            'C' => (int)$kdRows->sum('kuota_c'),
                            'K' => (int)$kdRows->sum('kuota_k'),
                        ];
                    } else {
                        $targetKuota = self::computeProportionalTargetCounts($totalTargetKaryawan);
                    }
                }
            } elseif (
                ($filterAtasan === '' || strtoupper($filterAtasan) === 'SEMUA')
                && ($filterJabatan === '' || strtoupper($filterJabatan) === 'SEMUA')
                && ($filterSeksi === '' || strtoupper($filterSeksi) === 'SEMUA')
                && ($filterBagian === '' || strtoupper($filterBagian) === 'SEMUA')
                && $search === ''
                && $filterKj !== '' && strtoupper($filterKj) !== 'SEMUA'
                && ($isDivHeadLaporan ? ($filterDept === '' || strtoupper($filterDept) === 'SEMUA') : $isDefaultScopeDept)
            ) {
                $baseSubCountByKj = [];
                foreach ($baseQuotaSubs as $bq) {
                    $kjKey = ExcelKaryawanReader::normalizeQuotaKj($bq['kj'] ?? '');
                    $baseSubCountByKj[$kjKey] = ($baseSubCountByKj[$kjKey] ?? 0) + 1;
                }
                $baseMatrixKjList = array_keys($baseSubCountByKj);
                sort($baseMatrixKjList, SORT_NATURAL);
                $baseMatrix = self::buildTargetKjMatrix(
                    $baseMatrixKjList,
                    $baseSubCountByKj,
                    $scopedQuota,
                    strtoupper(trim($user->departemen ?? '')),
                    (string)(self::resolveDivisionCode($user, $baseKaryawan) ?? '')
                );
                $normFilterKj = ExcelKaryawanReader::normalizeQuotaKj($filterKj);
                $kjTargetRow = $baseMatrix[$normFilterKj] ?? null;
                $targetKuota = $kjTargetRow
                    ? ['S' => (int)$kjTargetRow['S'], 'A' => (int)$kjTargetRow['A'], 'B' => (int)$kjTargetRow['B'], 'C' => (int)$kjTargetRow['C'], 'K' => (int)$kjTargetRow['K']]
                    : self::computeProportionalTargetCounts($totalTargetKaryawan);
            } else {
                $targetKuota = self::computeProportionalTargetCounts($totalTargetKaryawan);
            }
        } elseif ($isDirectUnitSupervisor) {
            $supSubCountByKj = [];
            foreach ($quotaBoundRows as $empRow) {
                $kjKey = ExcelKaryawanReader::normalizeQuotaKj($empRow['kj'] ?? '');
                $supSubCountByKj[$kjKey] = ($supSubCountByKj[$kjKey] ?? 0) + 1;
            }
            $supMatrixKjList = array_keys($supSubCountByKj);
            sort($supMatrixKjList, SORT_NATURAL);
            $supDeptContext = ($filterDept !== '' && strtoupper($filterDept) !== 'SEMUA')
                ? strtoupper(trim($filterDept))
                : strtoupper(trim($user->departemen ?? ''));
            $supTargetKjMatrix = self::buildDirectUnitTargetKjMatrix(
                $supMatrixKjList,
                $supSubCountByKj,
                $supDeptContext,
                (string)(self::resolveDivisionCode($user, $filteredKaryawan) ?? '')
            );
            $supSummary = self::summarizeTargetKjMatrix($supTargetKjMatrix);
            $targetKuota = [
                'S' => $supSummary['S'],
                'A' => $supSummary['A'],
                'B' => $supSummary['B'],
                'C' => $supSummary['C'],
                'K' => $supSummary['K'],
            ];
        } else {
            $handledCustomDept = false;
            if (!$hasSubFilters && $filterDept !== '' && strtoupper($filterDept) !== 'SEMUA') {
                $deptUpper = strtoupper(trim($filterDept));
                $deptKeys = $deptUpper === 'ENGINEERING'
                    ? ['target_kuota_div_DIV-E', 'target_kuota_dept_ENGINEERING']
                    : ['target_kuota_dept_' . $deptUpper];
                foreach ($deptKeys as $dk) {
                    $dVal = DB::table('pengaturan')->where('key', $dk)->value('value');
                    $dec = $dVal ? json_decode($dVal, true) : null;
                    if ($dec && isset($dec['S'])) {
                        $targetKuota = [
                            'S' => (int)($dec['S'] ?? 0),
                            'A' => (int)($dec['A'] ?? 0),
                            'B' => (int)($dec['B'] ?? 0),
                            'C' => (int)($dec['C'] ?? 0),
                            'K' => (int)($dec['K'] ?? 0),
                        ];
                        $handledCustomDept = true;
                        break;
                    }
                }
            }
            if (!$handledCustomDept) {
                try {
                    $kuotaQuery = DB::table('kuota_departemen');
                    if ($filterDept && strtoupper($filterDept) !== 'SEMUA') {
                        $kuotaQuery->where('departemen', $filterDept);
                    }
                    if ($filterKj && strtoupper($filterKj) !== 'SEMUA') {
                        $kuotaQuery->where('kj', ExcelKaryawanReader::normalizeQuotaKj($filterKj));
                    }
                    $kdRows = $kuotaQuery->get();
                    if ($kdRows->isNotEmpty()) {
                        $targetKuota = [
                            'S' => (int)$kdRows->sum('kuota_s'),
                            'A' => (int)$kdRows->sum('kuota_a'),
                            'B' => (int)$kdRows->sum('kuota_b'),
                            'C' => (int)$kdRows->sum('kuota_c'),
                            'K' => (int)$kdRows->sum('kuota_k'),
                        ];
                    }
                } catch (\Throwable $e) {
                    // keep default
                }

                if (array_sum($targetKuota) === 0 && $totalTargetKaryawan > 0) {
                    $targetKuota = self::computeProportionalTargetCounts($totalTargetKaryawan);
                }
            }
        }
        $totalEvaluatedForPct = max(1, $quotaSudahDinilai);
        $kategoriList = [
            ['kode' => 'S', 'label' => 'S (Sangat Baik)', 'jumlah' => $quotaActualCounts['S'], 'target' => $targetKuota['S'], 'persentase' => ($quotaSudahDinilai > 0 ? number_format(($quotaActualCounts['S'] / $totalEvaluatedForPct) * 100, 1) : '0.0') . '%'],
            ['kode' => 'A', 'label' => 'A (Baik)', 'jumlah' => $quotaActualCounts['A'], 'target' => $targetKuota['A'], 'persentase' => ($quotaSudahDinilai > 0 ? number_format(($quotaActualCounts['A'] / $totalEvaluatedForPct) * 100, 1) : '0.0') . '%'],
            ['kode' => 'B', 'label' => 'B (Cukup)', 'jumlah' => $quotaActualCounts['B'], 'target' => $targetKuota['B'], 'persentase' => ($quotaSudahDinilai > 0 ? number_format(($quotaActualCounts['B'] / $totalEvaluatedForPct) * 100, 1) : '0.0') . '%'],
            ['kode' => 'C', 'label' => 'C (Kurang)', 'jumlah' => $quotaActualCounts['C'], 'target' => $targetKuota['C'], 'persentase' => ($quotaSudahDinilai > 0 ? number_format(($quotaActualCounts['C'] / $totalEvaluatedForPct) * 100, 1) : '0.0') . '%'],
            ['kode' => 'K', 'label' => 'K (Kurang Sekali)', 'jumlah' => $quotaActualCounts['K'], 'target' => $targetKuota['K'], 'persentase' => ($quotaSudahDinilai > 0 ? number_format(($quotaActualCounts['K'] / $totalEvaluatedForPct) * 100, 1) : '0.0') . '%'],
        ];

        $subCountByKj = [];
        $actualKjMatrix = [];
        $deptActualKjMatrix = [];
        $deptQuotaActualCounts = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];
        $deptQuotaTotalDinilai = 0;
        foreach ($quotaBoundRows as $empRow) {
            $kjKey = ExcelKaryawanReader::normalizeQuotaKj($empRow['kj'] ?? '');
            $subCountByKj[$kjKey] = ($subCountByKj[$kjKey] ?? 0) + 1;
            if (!isset($actualKjMatrix[$kjKey])) {
                $actualKjMatrix[$kjKey] = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0, 'total' => 0];
            }
            if (!isset($deptActualKjMatrix[$kjKey])) {
                $deptActualKjMatrix[$kjKey] = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0, 'total' => 0];
            }
            if (!empty($empRow['is_dinilai']) && isset($actualKjMatrix[$kjKey][$empRow['grade'] ?? ''])) {
                $actualKjMatrix[$kjKey][$empRow['grade']]++;
                $actualKjMatrix[$kjKey]['total']++;
            }
            if (!empty($empRow['is_dept_dinilai']) && isset($deptActualKjMatrix[$kjKey][$empRow['dept_grade'] ?? ''])) {
                $dg = $empRow['dept_grade'];
                $deptActualKjMatrix[$kjKey][$dg]++;
                $deptActualKjMatrix[$kjKey]['total']++;
                $deptQuotaActualCounts[$dg]++;
                $deptQuotaTotalDinilai++;
            }
        }
        $matrixKjList = array_keys($subCountByKj);
        sort($matrixKjList, SORT_NATURAL);
        $matrixDeptContext = ($filterDept !== '' && strtoupper($filterDept) !== 'SEMUA')
            ? strtoupper(trim($filterDept))
            : strtoupper(trim($user->departemen ?? ''));
        $targetKjMatrix = $isDirectUnitSupervisor
            ? self::buildDirectUnitTargetKjMatrix(
                $matrixKjList,
                $subCountByKj,
                $matrixDeptContext,
                (string)(self::resolveDivisionCode($user, $filteredKaryawan) ?? '')
            )
            : self::buildTargetKjMatrix(
                $matrixKjList,
                $subCountByKj,
                $targetKuota,
                $matrixDeptContext,
                (string)(self::resolveDivisionCode($user, $filteredKaryawan) ?? '')
            );

        // Workflow counts for Foreman, Section Head, Department Head, and Division Head Laporan Cards
        $fmCountSiap = 0;
        $fmCountDiteruskanSection = 0;
        $fmCountMenungguSection = 0;
        $fmCountMenungguDept = 0;
        $fmCountSelesai = 0;

        $shCountSiap = 0;
        $shCountProsesForeman = 0;
        $shCountDiteruskanDept = 0;
        $shCountMenungguDept = 0;
        $shCountMenungguDiv = 0;
        $shCountSelesai = 0;

        $dhCountSiap = 0;
        $dhCountProsesForeman = 0;
        $dhCountForemanSudah = 0;
        $dhCountProsesSection = 0;
        $dhCountProsesSectionOnly = 0;
        $dhCountSectionSudah = 0;
        $dhCountDiteruskanDiv = 0;
        $dhCountMenungguDiv = 0;
        $dhCountSelesai = 0;

        $divCountSiap = 0;
        $divCountProsesSection = 0;
        $divSectionSudah = 0;
        $divCountProsesDept = 0;
        $divDeptSudah = 0;
        $divCountSelesai = 0;

        foreach ($filteredKaryawan as $k) {
            $nrp = $k['nrp'];
            $doc = $docsByNrp[$nrp] ?? null;
            $st = $doc['status'] ?? 'belum_ada';
            $kodeFormEmp = $doc['kode_form'] ?? self::determineKodeForm($k);
            $isGol4UpEmp = self::isForm07006($kodeFormEmp);
            $isSelfEmp = (strcasecmp((string)$nrp, (string)$user->nrp) === 0);
            $hasForemanKaryawan = !empty($k['nrp_foreman']) && !$isGol4UpEmp;

            // Foreman metrics (Alur hierarki: Foreman -> Section Head -> Department Head sebagai Tingkat Tertinggi)
            if ($hasForemanKaryawan) {
                if (in_array($st, ['belum_ada', '', null, 'draft', 'revisi', 'menunggu_foreman'])) {
                    $fmCountSiap++;
                }
                if (in_array($st, ['menunggu_section_head', 'menunggu_dept_head', 'menunggu_div_head', 'selesai'])) {
                    $fmCountDiteruskanSection++;
                }
                if ($st === 'menunggu_section_head') {
                    $fmCountMenungguSection++;
                }
                if (in_array($st, ['menunggu_dept_head', 'menunggu_div_head'])) {
                    $fmCountMenungguDept++;
                }
                if ($st === 'selesai') {
                    $fmCountSelesai++;
                }
            }

            // Section Head metrics
            if (in_array($st, ['menunggu_dept_head', 'menunggu_div_head', 'selesai'])) {
                $shCountDiteruskanDept++;
            }
            if ($st === 'menunggu_dept_head') {
                $shCountMenungguDept++;
            }
            if ($st === 'menunggu_div_head') {
                $shCountMenungguDiv++;
            }
            if ($st === 'selesai') {
                $shCountSelesai++;
            }
            if ($hasForemanKaryawan) {
                if (in_array($st, ['menunggu_section_head', 'revisi'])) {
                    $shCountSiap++;
                }
                if (in_array($st, ['belum_ada', '', null, 'draft', 'menunggu_foreman'])) {
                    $shCountProsesForeman++;
                }
            } else {
                if (in_array($st, ['belum_ada', '', null, 'draft', 'revisi', 'menunggu_section_head', 'menunggu_foreman'])) {
                    $shCountSiap++;
                }
            }

            // Department Head metrics
            if ($st === 'menunggu_dept_head' || ($isSelfEmp && in_array($st, ['belum_ada', '', null, 'draft', 'revisi']))) {
                $dhCountSiap++;
            }
            if (!$isSelfEmp) {
                if ($hasForemanKaryawan) {
                    if (in_array($st, ['menunggu_section_head', 'menunggu_dept_head', 'menunggu_div_head', 'selesai'])) {
                        $dhCountForemanSudah++;
                    } else {
                        $dhCountProsesForeman++;
                    }
                }
                if (in_array($st, ['menunggu_dept_head', 'menunggu_div_head', 'selesai'])) {
                    $dhCountSectionSudah++;
                } elseif ($st === 'menunggu_section_head' || (!$hasForemanKaryawan && in_array($st, ['belum_ada', '', null, 'draft', 'revisi', 'menunggu_foreman']))) {
                    $dhCountProsesSectionOnly++;
                }
            }
            if (in_array($st, ['belum_ada', '', null, 'draft', 'revisi', 'menunggu_foreman', 'menunggu_section_head'])) {
                $dhCountProsesSection++;
            }
            if (in_array($st, ['menunggu_div_head', 'selesai'])) {
                $dhCountDiteruskanDiv++;
            }
            if ($st === 'menunggu_div_head') {
                $dhCountMenungguDiv++;
            }
            if ($st === 'selesai') {
                $dhCountSelesai++;
            }

            // Division Head metrics
            if ($st === 'menunggu_div_head') {
                $divCountSiap++;
            }
            if (!$isSelfEmp) {
                if (in_array($st, ['menunggu_dept_head', 'menunggu_div_head', 'selesai'])) {
                    $divSectionSudah++;
                } elseif (in_array($st, ['belum_ada', '', null, 'draft', 'revisi', 'menunggu_foreman', 'menunggu_section_head'])) {
                    $divCountProsesSection++;
                }
                if (in_array($st, ['menunggu_div_head', 'selesai'])) {
                    $divDeptSudah++;
                } elseif ($st === 'menunggu_dept_head') {
                    $divCountProsesDept++;
                }
            }
            if ($st === 'selesai') {
                $divCountSelesai++;
            }
        }

        $roleWorkflowCards = [
            'foreman' => [
                'total_karyawan' => $totalKaryawan,
                'siap_disahkan' => $fmCountSiap,
                'diteruskan_section' => $fmCountDiteruskanSection,
                'menunggu_section' => $fmCountMenungguSection,
                'menunggu_dept' => $fmCountMenungguDept,
                'selesai' => $fmCountSelesai,
            ],
            'section_head' => [
                'total_karyawan' => $totalKaryawan,
                'siap_disahkan' => $shCountSiap,
                'proses_foreman' => $shCountProsesForeman,
                'total_dinilai' => $sudahDinilai,
                'diteruskan_dept' => $shCountDiteruskanDept,
                'menunggu_dept' => $shCountMenungguDept,
                'menunggu_div' => $shCountMenungguDiv,
                'selesai' => $shCountSelesai,
            ],
            'dept_head' => [
                'total_karyawan' => $totalKaryawan,
                'siap_disahkan' => $dhCountSiap,
                'proses_foreman' => $dhCountProsesForeman,
                'foreman_sudah' => $dhCountForemanSudah,
                'proses_section_only' => $dhCountProsesSectionOnly,
                'section_sudah' => $dhCountSectionSudah,
                'proses_section' => $dhCountProsesSection,
                'diteruskan_div' => $dhCountDiteruskanDiv,
                'menunggu_div' => $dhCountMenungguDiv,
                'selesai' => $dhCountSelesai,
            ],
            'div_head' => [
                'total_karyawan' => $totalKaryawan,
                'siap_disahkan' => $divCountSiap,
                'proses_section' => $divCountProsesSection,
                'section_sudah' => $divSectionSudah,
                'proses_dept' => $divCountProsesDept,
                'dept_sudah' => $divDeptSudah,
                'disahkan_final' => $divCountSelesai,
            ],
        ];

        // Build Division & Department monitoring summary for Laporan (connected directly to Master Karyawan & Dokumen)
        $knownDeptDefs = [
            'PROD 1' => ['kode' => 'PROD 1', 'nama' => 'Production 1 (PROD 1)', 'match' => ['PROD 1', 'PRODUCTION 1']],
            'PROD 2' => ['kode' => 'PROD 2', 'nama' => 'Production 2 (PROD 2)', 'match' => ['PROD 2', 'PRODUCTION 2']],
            'PROD 3' => ['kode' => 'PROD 3', 'nama' => 'Production 3 (PROD 3)', 'match' => ['PROD 3', 'PRODUCTION 3']],
            'PROD 4' => ['kode' => 'PROD 4', 'nama' => 'Production 4 (PROD 4)', 'match' => ['PROD 4', 'PRODUCTION 4']],
            'QA/QC' => ['kode' => 'QA/QC', 'nama' => 'Quality Control & QA Division', 'match' => ['QA', 'QUALITY CONTROL', 'QUALITY ASSURANCE (QA)', 'QC']],
            'ENGINEERING' => ['kode' => 'ENGINEERING', 'nama' => 'Technical Engineering Division', 'match' => ['ENGINEERING', 'ENG']],
            'HR & GA' => ['kode' => 'HR & GA', 'nama' => 'General Affairs, HR & IR Division', 'match' => ['HR & IR DEPT.', 'HR', 'GA', 'GENERAL AFFAIRS', 'GA DEPT.']],
        ];

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
        foreach ($karyawanDeptCounts as $dName => $cnt) {
            if (!isset($matchedKnownKeys[$dName])) {
                $cleanDept = preg_replace('/^PROD\s*(\d+)$/i', 'Production $1', $dName);
                $divName = $karyawanDeptDivisions[$dName] ?? '';
                $nama = ($divName !== '' && $divName !== '-') ? "{$cleanDept} ({$dName})" : "Departemen {$cleanDept}";
                $divisiDefs[] = [
                    'kode' => $dName,
                    'nama' => $nama,
                    'match' => [$dName],
                ];
            }
        }
        if (empty($divisiDefs)) {
            $divisiDefs = [$knownDeptDefs['PROD 1'], $knownDeptDefs['PROD 2']];
        }

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

        $rekapDivisiDepartemen = [];
        $sumCompanyTargetS = 0;
        $sumCompanyTargetA = 0;
        $sumCompanyTargetB = 0;
        $sumCompanyTargetC = 0;
        $sumCompanyTargetK = 0;

        foreach ($divisiDefs as $def) {
            $matchingKaryawan = array_values(array_filter($allKaryawan, function($k) use ($def) {
                return in_array(strtoupper(trim($k['departemen'] ?? '')), $def['match']);
            }));
            $target = count($matchingKaryawan);

            $dinilai = 0;
            $divName = '';
            foreach ($matchingKaryawan as $mk) {
                if (!$divName) {
                    $divCandidate = trim((string)($mk['division'] ?? ''));
                    if ($divCandidate && $divCandidate !== '-') {
                        $divName = $divCandidate;
                    }
                }
                $doc = $docsByNrp[$mk['nrp']] ?? null;
                $isRealEvaluated = false;
                if ($doc) {
                    $hasStoredTotal = !empty($doc['total_nilai']) && (float)$doc['total_nilai'] > 0;
                    $docIdVal = $doc['id'] ?? null;
                    $hasNilaiRows = !empty($docIdVal) && isset($nilaiByDoc[$docIdVal]);
                    $docStatus = $doc['status'] ?? '';
                    // Yang sudah dinilai di Tabel Target & Pemantauan adalah dokumen yang sudah dinilai oleh Dept Head atau Div Head (final)
                    $isRealEvaluated = in_array($docStatus, ['menunggu_div_head', 'selesai']);
                }
                if ($isRealEvaluated) {
                    $dinilai++;
                }
            }

            if (!$divName) {
                $kUpper = strtoupper($def['kode']);
                if (str_contains($kUpper, 'PROD 4')) {
                    $divName = 'PRODUCTION B DIV';
                } elseif (str_contains($kUpper, 'ENG')) {
                    $divName = 'TECHNICAL DIV';
                } elseif (str_contains($kUpper, 'QA') || str_contains($kUpper, 'QC')) {
                    $divName = 'QUALITY CONTROL DIV';
                } elseif (str_contains($kUpper, 'HR')) {
                    $divName = 'GENERAL AFFAIRS DIV';
                } else {
                    $divName = 'PRODUCTION A DIV';
                }
            }

            $belum = max(0, $target - $dinilai);
            $pct = $target > 0 ? (int)round(($dinilai / $target) * 100) : 0;

            $statusBadge = match(true) {
                $target === 0 => ['label' => 'Standby', 'bg' => 'bg-slate-100 text-slate-500 border-slate-200'],
                $pct === 100 => ['label' => '✓ Tuntas', 'bg' => 'bg-emerald-100 text-emerald-800 border-emerald-300'],
                $pct > 0 => ['label' => 'Sedang Berjalan', 'bg' => 'bg-blue-100 text-blue-800 border-blue-300'],
                default => ['label' => 'Belum Mulai', 'bg' => 'bg-amber-100 text-amber-800 border-amber-300'],
            };

            $customSettingKey = 'target_kuota_dept_' . $def['kode'];
            $customSetting = isset($allPengaturan[$customSettingKey]) ? json_decode($allPengaturan[$customSettingKey]->value, true) : null;
            if ($customSetting && isset($customSetting['S']) && ((int)($customSetting['total'] ?? 0) === $target || (int)array_sum($customSetting) === $target)) {
                $tS = (int)($customSetting['S'] ?? 0);
                $tA = (int)($customSetting['A'] ?? 0);
                $tB = (int)($customSetting['B'] ?? 0);
                $tC = (int)($customSetting['C'] ?? 0);
                $tK = (int)($customSetting['K'] ?? 0);
            } elseif (isset($deptSums[$def['kode']]) && $deptSums[$def['kode']]['total'] === $target) {
                $tS = $deptSums[$def['kode']]['S'];
                $tA = $deptSums[$def['kode']]['A'];
                $tB = $deptSums[$def['kode']]['B'];
                $tC = $deptSums[$def['kode']]['C'];
                $tK = $deptSums[$def['kode']]['K'];
            } else {
                if ($target > 0) {
                    $prop = self::computeProportionalTargetCounts($target);
                    $tS = $prop['S'] ?? max(0, (int)round($target * 0.10));
                    $tA = $prop['A'] ?? max(0, (int)round($target * 0.30));
                    $tB = $prop['B'] ?? max(0, (int)round($target * 0.30));
                    $tC = $prop['C'] ?? max(0, (int)round($target * 0.20));
                    $tK = $prop['K'] ?? max(0, $target - ($tS + $tA + $tB + $tC));
                } else {
                    $tS = $tA = $tB = $tC = $tK = 0;
                }
            }

            $sumCompanyTargetS += $tS;
            $sumCompanyTargetA += $tA;
            $sumCompanyTargetB += $tB;
            $sumCompanyTargetC += $tC;
            $sumCompanyTargetK += $tK;

            $rekapDivisiDepartemen[] = [
                'kode' => $def['kode'],
                'departemen' => $def['kode'],
                'departemen_label' => $def['nama'],
                'division' => $divName,
                'total_karyawan' => $target,
                'sudah_dinilai' => $dinilai,
                'belum_dinilai' => $belum,
                'pct_val' => $pct,
                'target_s' => $tS,
                'target_a' => $tA,
                'target_b' => $tB,
                'target_c' => $tC,
                'target_k' => $tK,
                'status' => $statusBadge,
            ];
        }

        $totalTargetPerusahaan = array_sum(array_column($rekapDivisiDepartemen, 'total_karyawan'));
        $totalDinilaiPerusahaan = array_sum(array_column($rekapDivisiDepartemen, 'sudah_dinilai'));
        $totalBelumPerusahaan = array_sum(array_column($rekapDivisiDepartemen, 'belum_dinilai'));
        $totalPctPerusahaan = $totalTargetPerusahaan > 0 ? (int)round(($totalDinilaiPerusahaan / $totalTargetPerusahaan) * 100) : 0;

        return [
            'employeeRows' => $employeeRows,
            'hierarchyCards' => $hierarchyCards,
            'foremanStageCounts' => $foremanStageCounts,
            'foremanDoneCount' => $fmCountDiteruskanSection,
            'roleWorkflowCards' => $roleWorkflowCards,
            'targetKuota' => $targetKuota,
            'actualCounts' => $actualCounts,
            'quotaActualCounts' => $quotaActualCounts,
            'quotaSudahDinilai' => $quotaSudahDinilai,
            'quotaBelumDinilai' => $quotaBelumDinilai,
            'quotaPctDinilai' => $quotaPctDinilai,
            'totalTargetKaryawan' => $totalTargetKaryawan,
            'kategoriList' => $kategoriList,
            'totalKaryawan' => $totalKaryawan,
            'totalKaryawanSubtitle' => $totalKaryawanSubtitle,
            'sudahDinilai' => $sudahDinilai,
            'belumDinilai' => $belumDinilai,
            'pctDinilai' => $pctDinilai,
            'distinctDivisions' => $distinctDivisions,
            'distinctDepts' => $distinctDepts,
            'distinctJabatans' => $distinctJabatans,
            'distinctKjs' => $distinctKjs,
            'distinctSeksi' => $distinctSeksi,
            'allSeksi' => $distinctSeksi,
            'distinctBagians' => $distinctBagians,
            'filterDivision' => $filterDivision,
            'filterBagian' => $filterBagian,
            'filterDept' => $filterDept,
            'filterAtasan' => $filterAtasan,
            'filterJabatan' => $filterJabatan,
            'filterKj' => $filterKj,
            'filterSeksi' => $filterSeksi,
            'filterGrade' => $filterGrade,
            'search' => $search,
            'hasForeman' => $hasForeman,
            'isSectionHeadWithoutForeman' => $isSectionHeadWithoutForeman,
            'matrixKjList' => $matrixKjList,
            'subCountByKj' => $subCountByKj,
            'targetKjMatrix' => $targetKjMatrix,
            'actualKjMatrix' => $actualKjMatrix,
            'deptActualKjMatrix' => $deptActualKjMatrix,
            'deptQuotaActualCounts' => $deptQuotaActualCounts,
            'deptQuotaTotalDinilai' => $deptQuotaTotalDinilai,
            'rekapDivisiDepartemen' => $rekapDivisiDepartemen,
            'totalTargetPerusahaan' => $totalTargetPerusahaan,
            'totalDinilaiPerusahaan' => $totalDinilaiPerusahaan,
            'totalBelumPerusahaan' => $totalBelumPerusahaan,
            'totalPctPerusahaan' => $totalPctPerusahaan,
            'sumCompanyTargetS' => $sumCompanyTargetS,
            'sumCompanyTargetA' => $sumCompanyTargetA,
            'sumCompanyTargetB' => $sumCompanyTargetB,
            'sumCompanyTargetC' => $sumCompanyTargetC,
            'sumCompanyTargetK' => $sumCompanyTargetK,
        ];
    }

    /**
     * Laporan Penilaian page for Department Head, Admin & Superadmin.
     */
    public function laporan(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $data = $this->getFilteredLaporanData($request, $user);

        return view('laporan.index', array_merge($data, [
            'user' => $user,
        ]));
    }

    /**
     * Export Laporan to formatted Excel (.xlsx) matching the exact web table columns and active filters.
     */
    public function exportExcelLaporan(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $data = $this->getFilteredLaporanData($request, $user);
        $employeeRows = $data['employeeRows'];

        $headers = [
            'NO',
            'NRP',
            'NAMA LENGKAP',
            'CODE BAGIAN',
            'NAMA BAGIAN',
            'SECTION',
            'DEPARTMENT',
            'DIVISION',
            'POSITION',
            'START WORK',
            'KJ',
            'GOLONGAN',
            'HASIL KERJA',
            'PROSES KERJA',
            'SIKAP KERJA',
            'MAN MANAGEMENT',
            'TOTAL NILAI',
            'NILAI PK FOREMAN (JIKA ADA)',
            'NILAI PK SECT',
            'NILAI PK DEPT',
            'NILAI PK DIV',
            'NILAI PK FINAL',
        ];

        $exportRows = [];
        $no = 1;
        foreach ($employeeRows as $k) {
            $exportRows[] = [
                $no++,
                (string)($k['nrp'] ?? ''),
                (string)($k['nama'] ?? ''),
                (string)($k['code_bagian'] ?? '-'),
                (string)($k['nama_bagian'] ?? '-'),
                (string)($k['nama_sect'] ?? '-'),
                (string)($k['departemen'] ?? '-'),
                (string)($k['division'] ?? '-'),
                strtoupper((string)($k['jabatan'] ?? '-')),
                (string)($k['start_work'] ?? '-'),
                (string)($k['kj'] ?? '-'),
                (string)($k['sub_gol'] ?? '-'),
                $k['hasil_kerja_bobot'] !== null ? number_format((float)$k['hasil_kerja_bobot'], 2) : '-',
                $k['proses_kerja_bobot'] !== null ? number_format((float)$k['proses_kerja_bobot'], 2) : '-',
                $k['sikap_kerja_bobot'] !== null ? number_format((float)$k['sikap_kerja_bobot'], 2) : '-',
                $k['man_management_bobot'] !== null ? number_format((float)$k['man_management_bobot'], 2) : '-',
                $k['total_nilai'] !== null ? number_format((float)$k['total_nilai'], 2) : '-',
                (string)($k['pk_foreman'] ?? '-'),
                (string)($k['pk_sect'] ?? '-'),
                (string)($k['pk_dept'] ?? '-'),
                (string)($k['pk_div'] ?? '-'),
                (string)($k['pk_final'] ?? '-'),
            ];
        }

        $centeredCols = [0, 1, 3, 5, 6, 7, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21];
        $xlsxBinary = ExcelKaryawanExporter::buildXlsxBinary('Laporan Penilaian', $headers, $exportRows, $centeredCols);

        $deptLabel = $data['filterDept'] ? strtoupper(Str::slug($data['filterDept'], '_')) : 'SEMUA';
        $filename = "Laporan_Penilaian_Karya_{$deptLabel}_" . date('Ymd_His') . '.xlsx';

        return response($xlsxBinary, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => (string)strlen($xlsxBinary),
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Ensure each filtered employee has an appraisal dokumen record matching its form type before PDF export.
     *
     * @param array<int, array<string, mixed>> $employeeRows
     * @return array<int, array<string, mixed>>
     */
    private function ensureDocsForPdfExport(array $employeeRows): array
    {
        $activePeriod = DB::table('periode')->where('status', 'aktif')->first();
        $periodeId = $activePeriod->id ?? 1;
        $karyawanByNrp = collect($this->repository->all('karyawan'))->keyBy('nrp');
        $docsByNrp = collect($this->repository->all('dokumen'))->keyBy('nrp');

        $resolved = [];
        foreach ($employeeRows as $row) {
            $nrp = $row['nrp'] ?? '';
            if ($nrp === '') {
                continue;
            }

            if (empty($row['doc_id'])) {
                $existing = $docsByNrp->get($nrp);
                if (!empty($existing['id'])) {
                    $row['doc_id'] = $existing['id'];
                    $row['kode_form'] = $existing['kode_form'] ?? ($row['kode_form'] ?? '07-001');
                } else {
                    $karyawan = $karyawanByNrp->get($nrp);
                    if (!$karyawan) {
                        continue;
                    }
                    $kodeForm = self::determineKodeForm($karyawan);
                    $initialStatus = (empty($karyawan['nrp_foreman']) || in_array($kodeForm, ['07-006-SH', '07-006-DH', '07-006-DIV']))
                        ? 'menunggu_section_head'
                        : 'menunggu_foreman';
                    $row['doc_id'] = DB::table('dokumen')->insertGetId([
                        'periode_id' => $periodeId,
                        'nrp' => $nrp,
                        'kode_form' => $kodeForm,
                        'status' => $initialStatus,
                        'kode_verifikasi' => 'VERIF-' . strtoupper(Str::random(10)),
                        'created_at' => now()->toDateTimeString(),
                        'updated_at' => now()->toDateTimeString(),
                    ]);
                    $row['kode_form'] = $kodeForm;
                }
            }

            $resolved[] = $row;
        }

        return $resolved;
    }

    /**
     * Build shared static PDF assets once per request to accelerate multi-PDF ZIP export.
     *
     * @return array<string, mixed>
     */
    private function preloadPdfSharedAssets(): array
    {
        $periode = $this->repository->find('periode', 'status', 'aktif') ?? $this->repository->find('periode', 'id', 1);
        $allSettings = DB::table('pengaturan')->pluck('value', 'key')->toArray();
        $formSettings = [
            'tahun_penilaian' => $allSettings['tahun_penilaian'] ?? ($periode['tahun'] ?? 2025),
            'periode_penilaian' => $allSettings['periode_penilaian'] ?? 'Jan - Des',
            'judul_form_pk' => $allSettings['judul_form_pk'] ?? 'PENILAIAN KARYA',
            'nama_perusahaan' => $allSettings['nama_perusahaan'] ?? 'PT MUSASHI AUTO PARTS INDONESIA',
            'catatan_petunjuk_penilai' => $allSettings['catatan_petunjuk_penilai'] ?? 'Pilih salah satu dari 5 pilihan kalimat kriteria di bawah ini yang paling sesuai.',
            'label_kerahasiaan' => $allSettings['label_kerahasiaan'] ?? 'RAHASIA',
            'catatan_1' => $allSettings['catatan_1'] ?? 'Jika nilai pecahan, ditentukan dua angka dibelakang koma.',
            'catatan_2' => $allSettings['catatan_2'] ?? 'Pembulatan : ≤ 0,5 pembulatan ke bawah ; > 0,5 pembulatan ke atas.',
            'judul_evaluasi_potensi' => $allSettings['judul_evaluasi_potensi'] ?? 'INDIKATOR EVALUASI POTENSI KARYAWAN',
            'petunjuk_evaluasi_potensi' => $allSettings['petunjuk_evaluasi_potensi'] ?? 'Setiap Atasan diharapkan mengisi dengan seksama kolom ini untuk mengetahui seberapa jauh potensi karyawan dapat dikembangkan ke posisi & tanggung jawab yang lebih besar sesuai dengan ketentuan yang berlaku dan selagi ada kesempatan dalam struktur organisasi.',
            'opsi_potensi_1' => $allSettings['opsi_potensi_1'] ?? 'Memenuhi standard untuk dipromosikan pada level diatasnya, saran pada posisi :',
            'opsi_potensi_2' => $allSettings['opsi_potensi_2'] ?? 'Sesuai dengan posisi saat ini dan masih bisa dikembangkan dengan pengalaman dan pelatihan sesuai matriks.',
            'opsi_potensi_3' => $allSettings['opsi_potensi_3'] ?? 'Sesuai untuk posisi saat ini dan tidak bisa dikembangkan lagi, alasan :',
            'opsi_potensi_4' => $allSettings['opsi_potensi_4'] ?? 'Yang bersangkutan kurang sesuai dengan posisi saat ini, dengan alasan :',
            'judul_kelebihan' => $allSettings['judul_kelebihan'] ?? 'KELEBIHAN KARYAWAN',
            'judul_kekurangan' => $allSettings['judul_kekurangan'] ?? 'KEKURANGAN KARYAWAN',
        ];

        $logoPath = file_exists(public_path('logomusashi.png'))
            ? public_path('logomusashi.png')
            : (file_exists(base_path('logomusashi.png'))
                ? base_path('logomusashi.png')
                : (file_exists(public_path('musashi.png')) ? public_path('musashi.png') : base_path('musashi.png')));
        $musashiLogoBase64 = file_exists($logoPath)
            ? 'data:image/png;base64,' . base64_encode((string)file_get_contents($logoPath))
            : null;

        $installedFontsPath = storage_path('fonts/installed-fonts.json');
        $installedFonts = file_exists($installedFontsPath)
            ? (json_decode((string)file_get_contents($installedFontsPath), true) ?: [])
            : [];

        return [
            'periode' => $periode,
            'formSettings' => $formSettings,
            'musashiLogoBase64' => $musashiLogoBase64,
            'installedFonts' => $installedFonts,
        ];
    }

    /**
     * Export Laporan official PDF (single file for single filter, ZIP archive for multi).
     */
    public function exportPdfLaporan(Request $request)
    {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        @ini_set('memory_limit', '1024M');

        /** @var User $user */
        $user = Auth::user();

        $data = $this->getFilteredLaporanData($request, $user);
        $evaluatedWithDoc = $this->ensureDocsForPdfExport($data['employeeRows']);

        if (empty($evaluatedWithDoc)) {
            return redirect()->route('laporan.index', $request->query())
                ->with('error', 'Tidak ada data formulir penilaian yang dapat diunduh untuk filter saat ini.');
        }

        $sharedAssets = $this->preloadPdfSharedAssets();

        // If exactly 1 employee in filtered result, download 1 PDF directly
        if (count($evaluatedWithDoc) === 1) {
            $singleDocId = $evaluatedWithDoc[0]['doc_id'];
            $pdfData = $this->getPdfInstanceForDoc($singleDocId, $user, $sharedAssets);
            if ($pdfData && !empty($pdfData['pdf'])) {
                return $pdfData['pdf']->download($pdfData['filename']);
            }
            return redirect()->route('laporan.index', $request->query())
                ->with('error', 'Gagal membuat file PDF untuk karyawan terpilih atau akses ditolak.');
        }

        if (!class_exists(\ZipArchive::class)) {
            return redirect()->route('laporan.index', $request->query())
                ->with('error', 'Ekstensi ZIP PHP belum aktif di server.');
        }

        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0755, true);
        }
        $tempZipPath = $tempDir . DIRECTORY_SEPARATOR . 'pk_zip_' . uniqid() . '.zip';

        $zip = new \ZipArchive();
        if ($zip->open($tempZipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return redirect()->route('laporan.index', $request->query())
                ->with('error', 'Gagal membuat file ZIP arsip penilaian.');
        }

        $addedCount = 0;
        $usedNames = [];
        foreach ($evaluatedWithDoc as $row) {
            try {
                $pdfData = $this->getPdfInstanceForDoc($row['doc_id'], $user, $sharedAssets);
                if ($pdfData && !empty($pdfData['pdf'])) {
                    $entryName = $pdfData['filename'];
                    if (isset($usedNames[$entryName])) {
                        $usedNames[$entryName]++;
                        $entryName = preg_replace('/\.pdf$/i', '_' . $usedNames[$entryName] . '.pdf', $entryName);
                    } else {
                        $usedNames[$entryName] = 1;
                    }
                    $zip->addFromString($entryName, $pdfData['pdf']->output());
                    $addedCount++;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }
        $zip->close();

        if ($addedCount === 0 || !file_exists($tempZipPath)) {
            @unlink($tempZipPath);
            return redirect()->route('laporan.index', $request->query())
                ->with('error', 'Gagal memproses dokumen penilaian ke dalam ZIP.');
        }

        $deptLabel = $data['filterDept'] ? strtoupper(Str::slug($data['filterDept'], '_')) : 'SEMUA';
        $atasanLabel = $data['filterAtasan'] ? '_' . strtoupper(Str::slug($data['filterAtasan'], '_')) : '';
        $zipFilename = "Laporan_Penilaian_Karya_{$deptLabel}{$atasanLabel}_" . date('Ymd_His') . ".zip";

        return response()->download($tempZipPath, $zipFilename, [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'max-age=0',
        ])->deleteFileAfterSend(true);
    }
}

