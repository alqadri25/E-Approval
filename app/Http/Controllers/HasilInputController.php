<?php

namespace App\Http\Controllers;

use App\Contracts\SheetRepositoryInterface;
use App\Models\User;
use App\Services\AppraisalCalculationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HasilInputController extends Controller
{
    protected SheetRepositoryInterface $repository;
    protected AppraisalCalculationService $calcService;

    public function __construct(SheetRepositoryInterface $repository, AppraisalCalculationService $calcService)
    {
        $this->repository = $repository;
        $this->calcService = $calcService;
    }

    /**
     * Display centralized Hasil Penilaian table
     */
    public function index(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $allKaryawan = $this->repository->all('karyawan');
        $allDocs = $this->repository->all('dokumen');
        $allNilai = $this->repository->all('nilai');
        $allIndikator = $this->repository->all('indikator');

        // Department, Jabatan, & KJ lists for filter dropdowns
        $distinctDepts = array_values(array_unique(array_filter(array_column($allKaryawan, 'departemen'))));
        sort($distinctDepts);

        $distinctJabatans = array_values(array_unique(array_filter(array_column($allKaryawan, 'jabatan'))));
        sort($distinctJabatans);

        $distinctKjs = array_values(array_unique(array_filter(array_column($allKaryawan, 'kj'))));
        sort($distinctKjs);

        // Filter inputs
        $search = trim($request->input('q', '') ?? '');
        $selectedJabatan = trim($request->input('jabatan', '') ?? '');
        $selectedKj = trim($request->input('kj', '') ?? '');

        // Department filter handling:
        // If request has 'departemen' parameter (even empty "?departemen="),
        // it means the user explicitly chose or reset it -> show all if empty or SEMUA!
        if ($request->has('departemen')) {
            $rawDept = $request->input('departemen');
            $selectedDept = ($rawDept === null || $rawDept === '' || strtoupper($rawDept) === 'SEMUA') ? '' : trim($rawDept);
        } else {
            // First time landing on the page without query parameters
            if ($user->isSuperadmin() || $user->isAdmin() || $user->isHr()) {
                $selectedDept = ''; // Admin/Superadmin/HR sees all by default
            } else {
                $selectedDept = $user->departemen ?: 'PROD 2';
            }
        }

        if ($selectedDept !== '' && $selectedDept !== 'SEMUA' && !$user->canAccessDepartment($selectedDept)) {
            abort(403, "Anda tidak memiliki hak akses ke departemen {$selectedDept}.");
        }

        $activeTab = $request->input('tab', 'semua'); // backward compatibility for test

        $docsByNrp = [];
        foreach ($allDocs as $doc) {
            $docsByNrp[$doc['nrp']] = $doc;
        }

        $nilaiByDoc = [];
        foreach ($allNilai as $n) {
            $docId = $n['dokumen_id'];
            $lvl = $n['level'] ?? 'foreman';
            $nilaiByDoc[$docId][$lvl][$n['aspek_kode']] = (float)$n['skor'];
        }

        $indikatorByDoc = [];
        foreach ($allIndikator as $ind) {
            $indikatorByDoc[$ind['dokumen_id']] = $ind;
        }

        // Build rows
        $rows = [];
        foreach ($allKaryawan as $k) {
            // Filter by Departemen
            if ($selectedDept !== '' && $selectedDept !== 'SEMUA') {
                if (strcasecmp($k['departemen'] ?? '', $selectedDept) !== 0) {
                    continue;
                }
            } elseif (!$user->isSuperadmin() && !$user->isAdmin() && !$user->isHr()) {
                // If showing all departments, restrict non-admin to departments they can access
                if (!$user->canAccessDepartment($k['departemen'] ?? '')) {
                    continue;
                }
            }

            // Filter by Jabatan
            if ($selectedJabatan !== '' && strtoupper($selectedJabatan) !== 'SEMUA') {
                if (strcasecmp($k['jabatan'] ?? '', $selectedJabatan) !== 0) {
                    continue;
                }
            }

            // Filter by KJ
            if ($selectedKj !== '' && strtoupper($selectedKj) !== 'SEMUA') {
                if (strcasecmp($k['kj'] ?? '', $selectedKj) !== 0) {
                    continue;
                }
            }

            // Filter by Search (NRP or Nama)
            if ($search !== '') {
                $nrpMatch = stripos($k['nrp'] ?? '', $search) !== false;
                $nameMatch = stripos($k['nama_lengkap'] ?? '', $search) !== false;
                if (!$nrpMatch && !$nameMatch) {
                    continue;
                }
            }

            $nrp = $k['nrp'];
            $doc = $docsByNrp[$nrp] ?? null;
            $docId = $doc['id'] ?? null;
            $kodeForm = $doc['kode_form'] ?? '07-001';

            // Filter by active tab (test suite backward compatibility)
            if ($activeTab === 'kj13' && $kodeForm !== '07-001') continue;
            if ($activeTab === 'kj45' && $kodeForm !== '07-002') continue;
            if ($activeTab === 'leader_foreman' && !in_array($kodeForm, ['07-003', '07-004'])) continue;
            if ($activeTab === 'gol4up' && !in_array($kodeForm, ['07-006-SH', '07-006-DH', '07-006-DIV'])) continue;

            $scores = [];
            if ($docId && isset($nilaiByDoc[$docId])) {
                $scores = $nilaiByDoc[$docId]['dept_head'] 
                    ?? $nilaiByDoc[$docId]['section_head'] 
                    ?? $nilaiByDoc[$docId]['foreman'] 
                    ?? [];
            }

            $calc = $this->calcService->calculate($kodeForm, $scores);
            $ind = $docId ? ($indikatorByDoc[$docId] ?? null) : null;

            $catatan = '';
            if ($ind) {
                if (!empty($ind['kelebihan'])) $catatan .= 'Kelebihan: ' . $ind['kelebihan'] . '. ';
                if (!empty($ind['kekurangan'])) $catatan .= 'Kekurangan: ' . $ind['kekurangan'];
            }
            if (!$catatan && !empty($doc['catatan_atasan'])) {
                $catatan = $doc['catatan_atasan'];
            }

            $hasManMgt = in_array($kodeForm, ['07-003', '07-004', '07-006-SH', '07-006-DH', '07-006-DIV']);
            $manMgtVal = $hasManMgt ? ($calc['groups']['man_management']['weighted_score'] ?? 0.00) : null;

            $rows[] = [
                'doc_id' => $docId,
                'nrp' => $nrp,
                'name' => $k['nama_lengkap'],
                'organization' => $k['seksi'] ?? '-',
                'dept' => $k['departemen'] ?? '-',
                'jabatan' => $k['jabatan'] ?? '-',
                'kj' => $k['kj'] ?? 'KJ 2',
                'kode_form' => $kodeForm,
                'status' => $doc['status'] ?? 'draft',

                // Category weighted values
                'hasil_kerja_bobot' => $calc['groups']['hasil_kerja']['weighted_score'] ?? 0.00,
                'proses_kerja_bobot' => $calc['groups']['proses_kerja']['weighted_score'] ?? 0.00,
                'sikap_kerja_bobot' => $calc['groups']['sikap_kerja']['weighted_score'] ?? 0.00,
                'man_management_bobot' => $manMgtVal,

                // Total & Notes
                'nilai_pk' => (float)($doc['total_nilai'] ?? $calc['grand_total']),
                'grade' => $doc['grade_original'] ?? ($calc['grand_total'] > 0 ? $this->calcService->scoreToGrade($calc['grand_total']) : 'K'),
                'catatan_atasan' => $catatan,
                'bagian' => $k['seksi'] ?? '-',
            ];
        }

        return view('hasil_input.index', compact(
            'rows',
            'distinctDepts',
            'distinctJabatans',
            'distinctKjs',
            'selectedDept',
            'selectedJabatan',
            'selectedKj',
            'search',
            'activeTab'
        ));
    }

    /**
     * Export Hasil Penilaian to CSV
     */
    public function export(Request $request)
    {
        $rawDept = $request->input('departemen');
        $dept = ($rawDept === null || $rawDept === '' || strtoupper($rawDept) === 'SEMUA') ? '' : trim($rawDept);
        $rawJabatan = $request->input('jabatan', '');
        $jabatan = ($rawJabatan === null || $rawJabatan === '' || strtoupper($rawJabatan) === 'SEMUA') ? '' : trim($rawJabatan);
        $rawKj = $request->input('kj', '');
        $kj = ($rawKj === null || $rawKj === '' || strtoupper($rawKj) === 'SEMUA') ? '' : trim($rawKj);
        $search = trim($request->input('q', '') ?? '');
        $filename = "Hasil_Penilaian_" . ($dept ? "{$dept}_" : "Semua_Dept_") . date('Ymd_His') . ".csv";

        $response = new StreamedResponse(function () use ($dept, $jabatan, $kj, $search) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM

            // 13 Columns matching consistent naming standard
            fputcsv($handle, [
                'NO', 'NRP', 'NAMA LENGKAP', 'DEPARTMENT', 'POSITION', 'KJ',
                'Hasil Kerja', 'Proses Kerja', 'Sikap Kerja', 'Man Management',
                'Total Nilai', 'NILAI PK ORIGINAL', 'CATATAN ATASAN'
            ]);

            $allKaryawan = $this->repository->all('karyawan');
            $allDocs = $this->repository->all('dokumen');
            $allNilai = $this->repository->all('nilai');
            $allIndikator = $this->repository->all('indikator');

            $docsByNrp = [];
            foreach ($allDocs as $doc) {
                $docsByNrp[$doc['nrp']] = $doc;
            }

            $nilaiByDoc = [];
            foreach ($allNilai as $n) {
                $docId = $n['dokumen_id'];
                $lvl = $n['level'] ?? 'foreman';
                $nilaiByDoc[$docId][$lvl][$n['aspek_kode']] = (float)$n['skor'];
            }

            $indikatorByDoc = [];
            foreach ($allIndikator as $ind) {
                $indikatorByDoc[$ind['dokumen_id']] = $ind;
            }

            $no = 1;
            foreach ($allKaryawan as $k) {
                if ($dept !== '' && $dept !== 'SEMUA' && strcasecmp($k['departemen'] ?? '', $dept) !== 0) continue;
                if ($jabatan !== '' && $jabatan !== 'SEMUA' && strcasecmp($k['jabatan'] ?? '', $jabatan) !== 0) continue;
                if ($kj !== '' && $kj !== 'SEMUA' && strcasecmp($k['kj'] ?? '', $kj) !== 0) continue;
                if ($search !== '') {
                    $nrpMatch = stripos($k['nrp'] ?? '', $search) !== false;
                    $nameMatch = stripos($k['nama_lengkap'] ?? '', $search) !== false;
                    if (!$nrpMatch && !$nameMatch) continue;
                }

                $doc = $docsByNrp[$k['nrp']] ?? null;
                $docId = $doc['id'] ?? null;
                $kodeForm = $doc['kode_form'] ?? '07-001';

                $scores = [];
                if ($docId && isset($nilaiByDoc[$docId])) {
                    $scores = $nilaiByDoc[$docId]['dept_head'] 
                        ?? $nilaiByDoc[$docId]['section_head'] 
                        ?? $nilaiByDoc[$docId]['foreman'] 
                        ?? [];
                }

                $calc = $this->calcService->calculate($kodeForm, $scores);
                $ind = $docId ? ($indikatorByDoc[$docId] ?? null) : null;

                $catatan = '';
                if ($ind) {
                    if (!empty($ind['kelebihan'])) $catatan .= 'Kelebihan: ' . $ind['kelebihan'] . '. ';
                    if (!empty($ind['kekurangan'])) $catatan .= 'Kekurangan: ' . $ind['kekurangan'];
                }
                if (!$catatan && !empty($doc['catatan_atasan'])) {
                    $catatan = $doc['catatan_atasan'];
                }

                $hasManMgt = in_array($kodeForm, ['07-003', '07-004', '07-006-SH', '07-006-DH', '07-006-DIV']);
                $manMgtScore = $hasManMgt 
                    ? number_format($calc['groups']['man_management']['weighted_score'] ?? 0.00, 2)
                    : '-';

                $totalNilai = $doc['total_nilai'] ?? $calc['grand_total'];
                $grade = $doc['grade_original'] ?? ($calc['grand_total'] > 0 ? $this->calcService->scoreToGrade($calc['grand_total']) : 'K');

                fputcsv($handle, [
                    $no++,
                    $k['nrp'],
                    $k['nama_lengkap'],
                    $k['departemen'] ?? '-',
                    $k['jabatan'] ?? '-',
                    $k['kj'] ?? '-',
                    number_format($calc['groups']['hasil_kerja']['weighted_score'] ?? 0.00, 2),
                    number_format($calc['groups']['proses_kerja']['weighted_score'] ?? 0.00, 2),
                    number_format($calc['groups']['sikap_kerja']['weighted_score'] ?? 0.00, 2),
                    $manMgtScore,
                    number_format((float)$totalNilai, 2),
                    $grade,
                    $catatan ?: '-'
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', "attachment; filename=\"{$filename}\"");

        return $response;
    }
}
