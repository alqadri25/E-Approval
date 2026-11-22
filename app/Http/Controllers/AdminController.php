<?php

namespace App\Http\Controllers;

use App\Contracts\SheetRepositoryInterface;
use App\Models\User;
use App\Services\AppraisalCalculationService;
use App\Services\ExcelKaryawanReader;
use App\Services\GoogleSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    protected SheetRepositoryInterface $repository;

    public function __construct(SheetRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Admin Dashboard (Mockup Screen 1: 2. Admin)
     */
    public function dashboard(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $allKaryawan = $this->repository->all('karyawan');
        $allDocs = $this->repository->all('dokumen');
        $totalKaryawan = count($allKaryawan);

        $deptSet = [];
        $divisiSet = [];
        $divisiGroups = [];
        foreach ($allKaryawan as $k) {
            $d = trim((string)($k['departemen'] ?? ''));
            $div = trim((string)($k['division'] ?? ''));
            if ($d !== '' && $d !== '-') {
                $deptSet[$d] = true;
            }
            if ($div !== '' && $div !== '-') {
                $divisiSet[$div] = true;
            }
            $groupKey = $div !== '' && $div !== '-' ? $div : ($d !== '' && $d !== '-' ? $d : 'Lainnya');
            if (!isset($divisiGroups[$groupKey])) {
                $divisiGroups[$groupKey] = ['target' => 0, 'dinilai' => 0];
            }
            $divisiGroups[$groupKey]['target']++;
        }

        $docsByNrp = [];
        $totalDinilai = 0;
        $gradeCounts = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];

        foreach ($allDocs as $doc) {
            $nrp = (string)($doc['nrp'] ?? '');
            if ($nrp !== '') {
                $docsByNrp[$nrp] = $doc;
            }
            $st = strtolower((string)($doc['status'] ?? ''));
            $grade = strtoupper((string)($doc['grade_original'] ?? ($doc['docking_final'] ?? '')));
            if (in_array($st, ['selesai', 'approved', 'disetujui', 'disetujui_hr']) || !empty($doc['total_nilai'])) {
                $totalDinilai++;
                if (isset($gradeCounts[$grade])) {
                    $gradeCounts[$grade]++;
                }
            }
        }

        foreach ($allKaryawan as $k) {
            $nrp = (string)($k['nrp'] ?? '');
            $div = trim((string)($k['division'] ?? ''));
            $d = trim((string)($k['departemen'] ?? ''));
            $groupKey = $div !== '' && $div !== '-' ? $div : ($d !== '' && $d !== '-' ? $d : 'Lainnya');

            if (isset($docsByNrp[$nrp])) {
                $st = strtolower((string)($docsByNrp[$nrp]['status'] ?? ''));
                if (in_array($st, ['selesai', 'approved', 'disetujui', 'disetujui_hr']) || !empty($docsByNrp[$nrp]['total_nilai'])) {
                    if (isset($divisiGroups[$groupKey])) {
                        $divisiGroups[$groupKey]['dinilai']++;
                    }
                }
            }
        }

        $totalDept = count($deptSet);
        $totalBelum = max(0, $totalKaryawan - $totalDinilai);
        $pctDinilai = $totalKaryawan > 0 ? round(($totalDinilai / $totalKaryawan) * 100) : 0;
        $pctBelum = $totalKaryawan > 0 ? (100 - $pctDinilai) : 0;

        $adminStats = [
            'total_karyawan' => $totalKaryawan,
            'total_divisi' => count($divisiSet),
            'total_departemen' => $totalDept,
            'total_section_head' => count(array_unique(array_filter(array_map(fn($k) => trim((string)($k['nrp_section_head'] ?? '')), $allKaryawan)))),
            'total_foreman' => count(array_unique(array_filter(array_map(fn($k) => trim((string)($k['nrp_foreman'] ?? '')), $allKaryawan)))),
            'total_dinilai' => $totalDinilai,
            'pct_dinilai' => $pctDinilai,
            'total_belum' => $totalBelum,
            'pct_belum' => $pctBelum,
        ];

        $aktivitasDivisi = [];
        $ringkasanDivisi = [];

        foreach ($divisiGroups as $name => $stats) {
            $tgt = $stats['target'];
            $dn = $stats['dinilai'];
            $pct = $tgt > 0 ? round(($dn / $tgt) * 100) : 0;
            $kode = strtoupper(substr($name, 0, 10));

            $aktivitasDivisi[] = [
                'kode' => $kode,
                'nama' => $name,
                'target' => $tgt,
                'dinilai' => $dn,
                'persentase' => $pct,
            ];

            $ringkasanDivisi[] = [
                'divisi' => $name,
                'total_karyawan' => $tgt,
                'sudah_dinilai' => $dn,
                'target' => "{$pct}%",
                'pct_val' => $pct,
            ];
        }

        $totalPenilaianCompany = $gradeCounts;

        return view('dashboard.admin', compact(
            'user',
            'adminStats',
            'aktivitasDivisi',
            'totalPenilaianCompany',
            'ringkasanDivisi'
        ));
    }

    /**
     * Admin: Data Karyawan (Mockup Screen 2: Admin: Data Karyawan)
     */
    public function karyawan(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $allKaryawan = $this->repository->all('karyawan');
        $allDocs = $this->repository->all('dokumen');
        $docsByNrp = [];
        foreach ($allDocs as $d) {
            $docsByNrp[$d['nrp']] = $d;
        }

        // Filters
        $search = trim($request->input('q', ''));
        $filterDivisi = trim($request->input('divisi', ''));
        $filterDept = trim($request->input('departemen', ''));
        $filterSeksi = trim($request->input('seksi', ''));
        $filterGol = trim($request->input('golongan', ''));
        $filterAtasan = trim($request->input('atasan', ''));

        $seksiList = array_values(array_unique(array_filter(array_map(
            fn ($k) => strtoupper(trim((string)($k['nama_sect'] ?? ($k['seksi'] ?? '')))),
            $allKaryawan
        ), fn ($s) => $s !== '' && $s !== '-')));
        sort($seksiList);

        // Dynamic Divisions, Departments, and KJ / Golongan directly from real employee data (synced with Excel)
        $divisiList = array_values(array_unique(array_filter(array_map(
            fn ($k) => trim((string)($k['division'] ?? '')),
            $allKaryawan
        ), fn ($d) => $d !== '' && $d !== '-')));
        sort($divisiList, SORT_NATURAL | SORT_FLAG_CASE);

        $deptList = array_values(array_unique(array_filter(array_map(
            fn ($k) => strtoupper(trim((string)($k['departemen'] ?? ''))),
            $allKaryawan
        ), fn ($d) => $d !== '' && $d !== '-')));
        sort($deptList, SORT_NATURAL | SORT_FLAG_CASE);

        $golList = array_values(array_unique(array_filter(array_map(
            fn ($k) => \App\Services\ExcelKaryawanReader::normalizeKj($k['kj'] ?? ''),
            $allKaryawan
        ), fn ($g) => $g !== '')));
        if (empty($golList)) {
            $golList = ['KJ 2', 'KJ 3', 'KJ 4', 'KJ 5'];
        }
        sort($golList, SORT_NATURAL);

        $atasanList = [
            'foreman' => '⭐ Semua dengan Penilai Foreman',
            'section_head' => '⭐ Semua dengan Penilai Section Head',
            'dept_head' => '⭐ Semua dengan Penilai Dept Head',
            'div_head' => '⭐ Semua dengan Penilai Division Head',
            '62' => 'Foreman F1 CAT 1 (SARYANTA)',
            '349' => 'Foreman F1 CAT 2 (SUDIMAN)',
            '628' => 'Foreman F1 CAT 3 (ANDI PURWANTO)',
            '51' => 'Foreman F2 CAT 1 (PURWANTO)',
            '487' => 'Foreman F2 CAT 2 (SARWOTO)',
            '533' => 'Foreman F2 CAT 3 (HARIYANTO)',
            '112' => 'Section Head F1 (KRISTIYANTO)',
            '280' => 'Section Head F2 (MURYADI)',
            '99999' => 'Section Head F3 Tanpa Foreman (AL QADRI)',
            '31' => 'Dept Head PROD 2 (SUPARDI)',
            '11' => 'Div Head PRODUCTION A DIV (ATASAN)',
            'section_c' => 'Section Head C (Dwi Cahyono - PROD 1)',
        ];

        // Supervisor mapping
        $usersByNrp = [
            '62' => 'Foreman F1 CAT 1 (SARYANTA)',
            '349' => 'Foreman F1 CAT 2 (SUDIMAN)',
            '628' => 'Foreman F1 CAT 3 (ANDI PURWANTO)',
            '51' => 'Foreman F2 CAT 1 (PURWANTO)',
            '487' => 'Foreman F2 CAT 2 (SARWOTO)',
            '533' => 'Foreman F2 CAT 3 (HARIYANTO)',
            '112' => 'Section Head F1 (KRISTIYANTO)',
            '280' => 'Section Head F2 (MURYADI)',
            '99999' => 'Section Head F3 (AL QADRI)',
            '31' => 'Dept Head PROD 2 (SUPARDI)',
            '11' => 'Div Head PRODUCTION A DIV (ATASAN)',
            'SH-11310' => 'Section Head C (Dwi Cahyono)',
            'DH-11300' => 'Dept Head X (Agus Setiawan)',
            'DIV-11000' => 'Division Head PROD 1 (Bambang Widodo)',
        ];

        // Summary Counts per Supervisor / Role Level for Executive Cards & Filter Pills
        $countForemanA = count(array_filter($allKaryawan, fn($k) => ($k['nrp_foreman'] ?? '') === '62'));
        $countForemanB = count(array_filter($allKaryawan, fn($k) => ($k['nrp_foreman'] ?? '') === '51'));
        $countSectionA = count(array_filter($allKaryawan, fn($k) => ($k['nrp_section_head'] ?? '') === '112'));
        $countSectionB = count(array_filter($allKaryawan, fn($k) => ($k['nrp_section_head'] ?? '') === '280'));
        $countDeptA = count(array_filter($allKaryawan, fn($k) => ($k['nrp_dept_head'] ?? '') === '31'));
        $countSectionC = count(array_filter($allKaryawan, fn($k) => ($k['nrp_section_head'] ?? '') === 'SH-11310' && empty($k['nrp_foreman'])));
        $countForemanE = 0;
        $countSectionE = count(array_filter($allKaryawan, fn($k) => ($k['nrp_section_head'] ?? '') === '99999'));
        $countKasusE = count(array_filter($allKaryawan, fn($k) => ($k['nrp_section_head'] ?? '') === '99999' || ($k['departemen'] ?? '') === 'ENGINEERING'));
        $totalKaryawanPerusahaan = count($allKaryawan);

        // Registered user accounts per evaluator role
        $totalForemanUsers = User::whereIn('role', ['foreman', 'ketua'])->count();
        $totalSectionHeadUsers = User::where('role', 'section_head')->count();
        $totalDeptHeadUsers = User::where('role', 'department_head')->count();
        $totalDivHeadUsers = User::where('role', 'division_head')->count();

        // Karyawan subordinates per evaluator level
        $countKaryawanWithForeman = count(array_filter($allKaryawan, fn($k) => !empty($k['nrp_foreman'])));
        $countKaryawanWithSection = count(array_filter($allKaryawan, fn($k) => !empty($k['nrp_section_head'])));
        $countKaryawanWithDept = count(array_filter($allKaryawan, fn($k) => !empty($k['nrp_dept_head'])));
        $countKaryawanWithDiv = count(array_filter($allKaryawan, fn($k) => !empty($k['nrp_div_head'])));

        // Compute dynamic summary metrics directly from actual employee data
        $uniqueBagian = [];
        $uniqueSect = [];
        $uniqueDept = [];
        $uniqueJabatan = [];

        foreach ($allKaryawan as $k) {
            $seksiUpper = strtoupper(trim((string)($k['seksi'] ?? '')));
            $dept = trim((string)($k['departemen'] ?? ''));
            $deptUpper = strtoupper($dept);

            // BAGIAN
            $namaBagian = trim((string)($k['nama_bagian'] ?? ''));
            if ($namaBagian === '' && $seksiUpper !== '' && $seksiUpper !== '-') {
                $namaBagian = $seksiUpper;
            }
            $namaBagianNorm = rtrim($namaBagian, '.');
            if ($namaBagianNorm !== '' && $namaBagianNorm !== '-') {
                $uniqueBagian[$namaBagianNorm] = true;
            }

            // SECT.
            $namaSect = trim((string)($k['nama_sect'] ?? ($k['seksi'] ?? '')));
            if ($namaSect !== '' && $namaSect !== '-') {
                $uniqueSect[strtoupper($namaSect)] = true;
            }

            // DEPT.
            if ($dept !== '' && $dept !== '-') {
                $uniqueDept[$deptUpper] = true;
            }

            // JABATAN
            $jabatan = strtoupper(trim((string)($k['jabatan'] ?? '')));
            if ($jabatan !== '' && $jabatan !== '-') {
                $uniqueJabatan[$jabatan] = true;
            }
        }

        $totalBagianCount = count($uniqueBagian);
        $totalSectCount = count($uniqueSect);
        $totalDeptCount = count($uniqueDept);
        $totalJabatanCount = count($uniqueJabatan);

        $hierarchySummary = [
            'total' => $totalKaryawanPerusahaan,
            'total_karyawan' => $totalKaryawanPerusahaan,
            'total_bagian' => $totalBagianCount,
            'total_sect' => $totalSectCount,
            'total_dept' => $totalDeptCount,
            'total_jabatan' => $totalJabatanCount,
            'total_foreman' => $totalForemanUsers,
            'total_section_head' => $totalSectionHeadUsers,
            'total_dept_head' => $totalDeptHeadUsers,
            'total_div_head' => $totalDivHeadUsers,
            'karyawan_with_foreman' => $countKaryawanWithForeman,
            'karyawan_with_section' => $countKaryawanWithSection,
            'karyawan_with_dept' => $countKaryawanWithDept,
            'karyawan_with_div' => $countKaryawanWithDiv,
            'foreman_a' => $countForemanA,
            'foreman_b' => $countForemanB,
            'dept_a' => $countDeptA,
            'section_c' => $countSectionC,
            'foreman_e' => $countForemanE,
            'section_e' => $countSectionE,
            'kasus_e' => $countKasusE,
        ];

        $filtered = $allKaryawan;

        // Apply Search
        if ($search !== '') {
            $filtered = array_filter($filtered, function ($k) use ($search) {
                return stripos($k['nama_lengkap'] ?? '', $search) !== false
                    || stripos($k['nrp'] ?? '', $search) !== false
                    || stripos($k['jabatan'] ?? '', $search) !== false
                    || stripos($k['departemen'] ?? '', $search) !== false
                    || stripos($k['division'] ?? '', $search) !== false;
            });
        }

        // Apply Division Filter
        if ($filterDivisi !== '') {
            $filtered = array_filter($filtered, fn($k) => strcasecmp(trim((string)($k['division'] ?? '')), $filterDivisi) === 0);
        }

        // Apply Dept Filter
        if ($filterDept !== '') {
            $filtered = array_filter($filtered, fn($k) => strcasecmp($k['departemen'] ?? '', $filterDept) === 0);
        }

        // Apply Section Filter
        if ($filterSeksi !== '') {
            $filtered = array_filter($filtered, function ($k) use ($filterSeksi) {
                $sect = strtoupper(trim((string)($k['nama_sect'] ?? ($k['seksi'] ?? ''))));
                return strcasecmp($sect, $filterSeksi) === 0;
            });
        }

        // Apply Golongan / KJ Filter
        if ($filterGol !== '') {
            $normalizedFilterGol = \App\Services\ExcelKaryawanReader::normalizeKj($filterGol);
            $filtered = array_filter($filtered, function ($k) use ($filterGol, $normalizedFilterGol) {
                $rowKj = trim((string)($k['kj'] ?? ''));
                return strcasecmp($rowKj, $filterGol) === 0
                    || ($normalizedFilterGol !== '' && strcasecmp(\App\Services\ExcelKaryawanReader::normalizeKj($rowKj), $normalizedFilterGol) === 0);
            });
        }

        // Apply Atasan Filter (Supports role levels 'foreman', 'section_head', 'dept_head', 'div_head' and individual supervisors)
        if ($filterAtasan !== '') {
            $filtered = array_filter($filtered, function ($k) use ($filterAtasan) {
                if ($filterAtasan === 'foreman') return !empty($k['nrp_foreman']);
                if (in_array($filterAtasan, ['section_head', 'section'])) return !empty($k['nrp_section_head']);
                if (in_array($filterAtasan, ['dept_head', 'department_head'])) return !empty($k['nrp_dept_head']);
                if (in_array($filterAtasan, ['div_head', 'division_head'])) return !empty($k['nrp_div_head']);

                if ($filterAtasan === 'foreman_a') return ($k['nrp_foreman'] ?? '') === '62';
                if ($filterAtasan === 'foreman_b') return ($k['nrp_foreman'] ?? '') === '51';
                if ($filterAtasan === 'section_a') return ($k['nrp_section_head'] ?? '') === '112';
                if ($filterAtasan === 'section_b') return ($k['nrp_section_head'] ?? '') === '280';
                if ($filterAtasan === 'dept_a') return ($k['nrp_dept_head'] ?? '') === '31';
                if ($filterAtasan === 'section_c') return ($k['nrp_section_head'] ?? '') === 'SH-11310';
                if ($filterAtasan === 'section_e') return ($k['nrp_section_head'] ?? '') === '99999';
                if ($filterAtasan === 'dept_e') return ($k['nrp_dept_head'] ?? '') === '31';
                if ($filterAtasan === 'div_e') return ($k['nrp_div_head'] ?? '') === '11';
                return ($k['nrp_foreman'] ?? '') === $filterAtasan 
                    || ($k['nrp_section_head'] ?? '') === $filterAtasan
                    || ($k['nrp_dept_head'] ?? '') === $filterAtasan
                    || ($k['nrp_div_head'] ?? '') === $filterAtasan;
            });
        }

        // Prioritize key hierarchy & real PROD 2 records
        $priorityNrps = ['11', '31', '112', '280', '99999', '62', '349', '628', '51', '487', '533'];
        usort($filtered, function ($a, $b) use ($priorityNrps) {
            $idxA = array_search($a['nrp'], $priorityNrps);
            $idxB = array_search($b['nrp'], $priorityNrps);
            if ($idxA !== false && $idxB !== false) return $idxA <=> $idxB;
            if ($idxA !== false) return -1;
            if ($idxB !== false) return 1;
            return strcmp($a['nrp'], $b['nrp']);
        });

        // Enrich with Division, Document ID & Alur Penilai
        $karyawanRows = [];
        foreach ($filtered as $k) {
            $nrp = $k['nrp'];
            $doc = $docsByNrp[$nrp] ?? null;

            $dept = $k['departemen'] ?? 'PROD 2';
            $divisionRaw = trim((string)($k['division'] ?? ''));
            $divisi = $divisionRaw !== '' ? $divisionRaw : match (strtoupper($dept)) {
                'PROD 1', 'PROD 2' => 'PRODUCTION A DIV',
                'QUALITY ASSURANCE (QA)', 'QA' => 'QUALITY CONTROL DIV',
                'ENGINEERING' => 'TECHNICAL DIV',
                default => 'GENERAL AFFAIRS DIV',
            };

            $nrpForeman = $k['nrp_foreman'] ?? null;
            $nrpSection = $k['nrp_section_head'] ?? null;
            $nrpDept = $k['nrp_dept_head'] ?? null;
            $nrpDiv = $k['nrp_div_head'] ?? null;

            if ($nrpForeman) {
                $alurPenilai = ($usersByNrp[$nrpForeman] ?? $nrpForeman) . ' ➔ ' . ($usersByNrp[$nrpSection] ?? $nrpSection);
                $atasanUtama = $usersByNrp[$nrpForeman] ?? $nrpForeman;
                $levelAtasan = 'Foreman';
            } elseif ($nrpSection && $k['level_form'] !== 'SECTION_HEAD') {
                $alurPenilai = ($usersByNrp[$nrpSection] ?? $nrpSection) . ' (Langsung) ➔ ' . ($usersByNrp[$nrpDept] ?? $nrpDept);
                $atasanUtama = ($usersByNrp[$nrpSection] ?? $nrpSection);
                $levelAtasan = 'Section Head (Langsung)';
            } elseif ($k['level_form'] === 'SECTION_HEAD') {
                $alurPenilai = 'Section Head ➔ ' . ($usersByNrp[$nrpDept] ?? 'Dept Head');
                $atasanUtama = $usersByNrp[$nrpDept] ?? 'Dept Head';
                $levelAtasan = 'Dept Head';
            } elseif ($k['level_form'] === 'DEPT_HEAD') {
                $alurPenilai = 'Dept Head ➔ ' . ($usersByNrp[$nrpDiv] ?? 'Div Head');
                $atasanUtama = $usersByNrp[$nrpDiv] ?? 'Div Head';
                $levelAtasan = 'Division Head';
            } elseif ($k['level_form'] === 'DIV_HEAD') {
                $alurPenilai = 'Mandiri / Penilai Final (Div. Head)';
                $atasanUtama = 'Mandiri / Final';
                $levelAtasan = 'Division Head';
            } else {
                $alurPenilai = '-';
                $atasanUtama = '-';
                $levelAtasan = '-';
            }

            // Start Work formatted as Day-Month-Year (e.g. 07-May-96, 16-Jul-12, 20-Aug-01)
            $tglMasuk = $k['tgl_masuk'] ?? '';
            $startWork = \App\Services\GoogleSyncService::formatMusashiDate($tglMasuk);

            // Code Bagian, Nama Bagian, Nama Sect
            $seksiUpper = strtoupper($k['seksi'] ?? '');
            $deptUpper = strtoupper($dept);
            $codeBagian = $k['code_bagian'] ?? null;
            if (!$codeBagian) {
                if (str_contains($seksiUpper, 'FACTORY 2') || str_contains($seksiUpper, 'FACT-2')) {
                    $codeBagian = '1141700000';
                } elseif (str_contains($seksiUpper, 'MACHINING 1')) {
                    $codeBagian = '1141610000';
                } elseif (str_contains($seksiUpper, 'MACHINING 2')) {
                    $codeBagian = '1141620000';
                } elseif (str_contains($seksiUpper, 'FINISHING')) {
                    $codeBagian = '1141630000';
                } elseif (str_contains($seksiUpper, 'PRHC') || str_contains($seksiUpper, 'PINION') || str_contains($seksiUpper, 'HUB CLUTCH')) {
                    $codeBagian = '1141650000';
                } elseif (str_contains($seksiUpper, 'BALANCER')) {
                    $codeBagian = '1141660000';
                } elseif (str_contains($deptUpper, 'ENGINEERING')) {
                    $codeBagian = '1151500000';
                } elseif (str_contains($deptUpper, 'QA') || str_contains($deptUpper, 'QUALITY')) {
                    $codeBagian = '1131000000';
                } elseif (str_contains($deptUpper, 'HR')) {
                    $codeBagian = '1121100000';
                } else {
                    $codeBagian = '1141600000';
                }
            }

            $namaBagian = $k['nama_bagian'] ?? ($k['seksi'] ?: 'FACTORY 1 SEC');
            $namaSect = $k['nama_sect'] ?? (str_contains($seksiUpper, '2') || str_contains($seksiUpper, 'FACT-2') ? 'FACTORY 2' : (str_contains($deptUpper, 'ENGINEERING') ? 'ENGINEERING' : (str_contains($deptUpper, 'QA') ? 'QA' : 'FACTORY 1')));
            $kj = !empty($k['kj']) ? \App\Services\ExcelKaryawanReader::normalizeKj($k['kj']) : 'KJ 2';
            $subGol = !empty($k['sub_gol']) ? $k['sub_gol'] : (!empty($k['golongan']) && strlen($k['golongan']) <= 4 ? $k['golongan'] : 'IIA');

            $karyawanRows[] = [
                'nrp' => $nrp,
                'nama_lengkap' => $k['nama_lengkap'],
                'code_bagian' => $codeBagian,
                'nama_bagian' => $namaBagian,
                'nama_sect' => $namaSect,
                'jabatan' => $k['jabatan'] ?? 'Staff',
                'divisi' => $divisi,
                'departemen' => $dept,
                'seksi' => $k['seksi'] ?? '-',
                'level_form' => $k['level_form'] ?? 'KJ13',
                'golongan' => $kj . ' / ' . ($k['sub_gol'] ?? $k['golongan'] ?? '-'),
                'kj' => $kj,
                'sub_gol' => $subGol,
                'tgl_masuk' => $tglMasuk,
                'start_work' => $startWork,
                'doc_id' => $doc['id'] ?? null,
                'status' => $doc['status'] ?? 'belum_ada',
                'nrp_foreman' => $nrpForeman,
                'nrp_section_head' => $nrpSection,
                'nrp_dept_head' => $nrpDept,
                'atasan_utama' => $atasanUtama,
                'level_atasan' => $levelAtasan,
                'alur_penilai' => $alurPenilai,
            ];
        }

        $totalCount = count($karyawanRows);

        return view('admin.karyawan', compact(
            'user',
            'karyawanRows',
            'totalCount',
            'totalKaryawanPerusahaan',
            'divisiList',
            'deptList',
            'golList',
            'atasanList',
            'hierarchySummary',
            'search',
            'filterDivisi',
            'filterDept',
            'filterSeksi',
            'seksiList',
            'filterGol',
            'filterAtasan'
        ));
    }

    /**
     * Admin: Divisi & Departemen (Sesuai Jumlah Karyawan Riil Terkelola)
     */
    public function divisiDepartemen(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $allKaryawan = $this->repository->all('karyawan');
        $totalKaryawanPerusahaan = count($allKaryawan);

        // 1. Group employees strictly by real Division & Department from Master Karyawan (DATA PK)
        $deptStats = [];
        $divisionStats = [];
        $uniqueSections = [];

        foreach ($allKaryawan as $k) {
            $div = trim((string)($k['division'] ?? ''));
            $dept = strtoupper(trim((string)($k['departemen'] ?? '')));
            $sect = trim((string)($k['nama_sect'] ?? ($k['seksi'] ?? '')));
            $jabatan = strtoupper(trim((string)($k['jabatan'] ?? '')));
            $nama = trim((string)($k['nama_lengkap'] ?? ''));
            $nrp = trim((string)($k['nrp'] ?? ''));

            if ($sect !== '' && $sect !== '-') {
                $uniqueSections[$sect] = true;
            }

            // Division tracking (e.g. PRODUCTION A DIV, PRODUCTION B DIV)
            if ($div !== '' && $div !== '-') {
                if (!isset($divisionStats[$div])) {
                    $divisionStats[$div] = [
                        'nama' => $div,
                        'total_karyawan' => 0,
                        'div_heads' => [],
                        'departments' => [],
                    ];
                }
                $divisionStats[$div]['total_karyawan']++;
                if (str_contains($jabatan, 'DIV HEAD') || str_contains($jabatan, 'DIVISION HEAD')) {
                    $divHeadStr = "{$nama} ({$nrp})";
                    if (!in_array($divHeadStr, $divisionStats[$div]['div_heads'], true)) {
                        $divisionStats[$div]['div_heads'][] = $divHeadStr;
                    }
                }
            }

            // Department tracking (strictly real departments, ignoring '-' which belongs directly to division level)
            if ($dept !== '' && $dept !== '-') {
                if (!isset($deptStats[$dept])) {
                    $cleanDeptName = preg_replace('/^PROD\s*(\d+)$/i', 'Production $1', $dept);
                    $deptStats[$dept] = [
                        'kode' => $dept,
                        'nama' => "{$cleanDeptName} ({$dept})",
                        'clean_nama' => $cleanDeptName,
                        'divisi' => ($div !== '' && $div !== '-') ? $div : '-',
                        'dept_head' => '-',
                        'dept_head_nrp' => null,
                        'total_karyawan' => 0,
                        'sections' => [],
                        'foremen' => [],
                        'filter_dept' => $dept,
                    ];
                }
                $deptStats[$dept]['total_karyawan']++;
                if ($div !== '' && $div !== '-') {
                    $deptStats[$dept]['divisi'] = $div;
                    if (isset($divisionStats[$div]) && !in_array($dept, $divisionStats[$div]['departments'], true)) {
                        $divisionStats[$div]['departments'][] = $dept;
                    }
                }
                if ($sect !== '' && $sect !== '-' && !in_array($sect, $deptStats[$dept]['sections'], true)) {
                    $deptStats[$dept]['sections'][] = $sect;
                }
                if (str_contains($jabatan, 'DEPT HEAD') || str_contains($jabatan, 'DEPARTMENT HEAD')) {
                    $deptStats[$dept]['dept_head'] = "{$nama} ({$nrp})";
                    $deptStats[$dept]['dept_head_nrp'] = $nrp;
                }
                if (str_contains($jabatan, 'FOREMAN') || str_contains($jabatan, 'KETUA')) {
                    $foremanStr = "{$nama} ({$nrp})";
                    if (!in_array($foremanStr, $deptStats[$dept]['foremen'], true)) {
                        $deptStats[$dept]['foremen'][] = $foremanStr;
                    }
                }
            }
        }

        // Natural sort departments (PROD 1, PROD 2, PROD 3, PROD 4)
        ksort($deptStats);
        $departemenList = array_values($deptStats);
        foreach ($departemenList as $idx => &$dItem) {
            $dItem['id'] = $idx + 1;
        }
        unset($dItem);

        // Strictly real division options from employee master data (no phantom defaults)
        $divisiOptions = array_values(array_unique(array_keys($divisionStats)));

        // Fetch all available Dept Heads & Leadership users for dropdown
        $deptHeads = User::whereIn('role', ['department_head', 'division_head', 'superadmin', 'admin'])
            ->orderBy('nama')
            ->get();

        return view('admin.divisi_departemen', compact(
            'user',
            'departemenList',
            'divisiOptions',
            'divisionStats',
            'uniqueSections',
            'deptHeads',
            'totalKaryawanPerusahaan'
        ));
    }

    /**
     * Store / Update Departemen
     */
    public function simpanDepartemen(Request $request)
    {
        $nama = trim($request->input('nama_departemen', ''));
        $divisi = trim($request->input('divisi', ''));
        $deptHead = trim($request->input('dept_head', ''));

        if (empty($nama) || empty($divisi)) {
            return redirect()->back()->with('error', 'Nama Departemen dan Divisi wajib diisi.');
        }

        return redirect()->back()->with('success', "Departemen '{$nama}' di {$divisi} berhasil disimpan ke dalam sistem struktur organisasi.");
    }

    /**
     * Admin: Target & Kuota Nilai (Dynamic & Sesuai Hierarki Riil Seluruh Divisi & Departemen)
     */
    public function targetKuota(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->isAdmin() && !$user->isSuperadmin()) {
            abort(403, 'Akses terbatas untuk Admin dan Superadmin.');
        }

        $allKaryawan = $this->repository->all('karyawan');
        $allDocs = $this->repository->all('dokumen');
        $docsByNrp = [];
        foreach ($allDocs as $d) {
            if (!empty($d['nrp'])) {
                $docsByNrp[$d['nrp']] = $d;
            }
        }
        // Exclude Form 07-006 (Section Head / Dept Head / Division Head) from Admin target quota headcount
        $allQuotaBoundKaryawan = array_values(array_filter(
            $allKaryawan,
            fn ($k) => !PenilaianController::isQuotaExemptEmployee($k, $docsByNrp[$k['nrp'] ?? ''] ?? null)
        ));
        $exemptKaryawan = array_values(array_filter(
            $allKaryawan,
            fn ($k) => PenilaianController::isQuotaExemptEmployee($k, $docsByNrp[$k['nrp'] ?? ''] ?? null)
        ));
        $totalPerusahaanKaryawan = count($allQuotaBoundKaryawan);
        $totalExempt07006 = count($exemptKaryawan);

        // If no employees exist in Master Karyawan, return clean empty state without hardcoded dummy divisions/depts
        if (empty($allKaryawan)) {
            $divisiDefs = [];
            $deptDefs = [];
            $divisionCards = [];
            $simulasiDepartemen = [];
            $deptTargetsMap = [];
            $tabelKuotaKj = [];
            $summaryKjCards = [];
            $topSummaryStats = [
                'total_karyawan' => 0,
                'kena_target' => 0,
                'bebas_target' => 0,
                'total_division' => 0,
                'total_departemen' => 0,
                'total_foreman' => 0,
                'total_section_head' => 0,
                'total_dept_head' => 0,
                'total_leader' => 0,
                'sum_s' => 0, 'sum_a' => 0, 'sum_b' => 0, 'sum_c' => 0, 'sum_k' => 0, 'sum_total' => 0
            ];
            $realDivisionsStats = [];
            $rules = [];
            $currentDept = '';
            $selectedDivisi = '';
            $displayKjList = [];
            $targetPerusahaan = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];

            return view('admin.target_kuota', compact(
                'user',
                'divisiDefs',
                'deptDefs',
                'divisionCards',
                'simulasiDepartemen',
                'deptTargetsMap',
                'tabelKuotaKj',
                'summaryKjCards',
                'topSummaryStats',
                'realDivisionsStats',
                'rules',
                'totalPerusahaanKaryawan',
                'totalExempt07006',
                'targetPerusahaan',
                'displayKjList',
                'currentDept',
                'selectedDivisi'
            ));
        }

        // 1. Discover real Divisions & Departments dynamically from Data Karyawan
        $realDeptDivMap = [];
        $realDivDeptsMap = [];
        foreach ($allKaryawan as $kItem) {
            $rawDiv = trim((string)($kItem['division'] ?? ''));
            $rawDept = strtoupper(trim((string)($kItem['departemen'] ?? '')));
            if ($rawDept === '' || $rawDept === '-') {
                continue;
            }
            $divName = ($rawDiv !== '' && $rawDiv !== '-') ? $rawDiv : 'DIVISI LAINNYA';
            $realDeptDivMap[$rawDept] = $divName;
            if (!isset($realDivDeptsMap[$divName])) {
                $realDivDeptsMap[$divName] = [];
            }
            if (!in_array($rawDept, $realDivDeptsMap[$divName], true)) {
                $realDivDeptsMap[$divName][] = $rawDept;
            }
        }

        // Build $divisiDefs dynamically from real Data Karyawan
        $divisiDefs = [];
        foreach ($realDivDeptsMap as $divName => $deptsInDiv) {
            $divCode = 'DIV-' . strtoupper(\Illuminate\Support\Str::slug($divName));
            $divisiDefs[] = [
                'kode' => $divCode,
                'nama' => $divName,
                'singkat' => $divName,
                'departemen_desc' => implode(', ', $deptsInDiv),
                'depts' => $deptsInDiv,
            ];
        }

        // Build $deptDefs dynamically from real Data Karyawan
        $deptDefs = [];
        foreach ($realDeptDivMap as $deptCode => $divName) {
            $divCode = 'DIV-' . strtoupper(\Illuminate\Support\Str::slug($divName));
            $cleanDept = preg_replace('/^PROD\s*(\d+)$/i', 'Production $1', $deptCode);
            $deptDefs[] = [
                'kode' => $deptCode,
                'departemen' => $cleanDept,
                'divisi' => $divName,
                'divisi_kode' => $divCode,
                'match' => [$deptCode],
            ];
        }

        $resolveDeptDefKode = function (string $deptRaw) use (&$deptDefs): ?string {
            $deptUpper = strtoupper(trim($deptRaw));
            if ($deptUpper === '' || $deptUpper === '-') {
                return null;
            }
            foreach ($deptDefs as $dDef) {
                if (strtoupper($dDef['kode']) === $deptUpper || in_array($deptUpper, $dDef['match'], true)) {
                    return $dDef['kode'];
                }
            }
            return $deptUpper;
        };

        $realDivDeptMap = $realDeptDivMap;
        $realDivisionsStats = [];
        foreach ($allKaryawan as $kItem) {
            $rawDiv = trim((string)($kItem['division'] ?? ''));
            $rawDept = strtoupper(trim((string)($kItem['departemen'] ?? '')));
            $mappedKode = $resolveDeptDefKode($rawDept);

            if ($rawDiv !== '' && $rawDiv !== '-') {
                if (!isset($realDivisionsStats[$rawDiv])) {
                    $realDivisionsStats[$rawDiv] = [
                        'nama' => $rawDiv,
                        'total_karyawan' => 0,
                        'kena_target' => 0,
                        'foreman' => 0,
                        'section_head' => 0,
                        'dept_head' => 0,
                        'div_head' => 0,
                        'leader' => 0,
                        'dept_codes' => [],
                        'dept_details' => [],
                    ];
                }
                $realDivisionsStats[$rawDiv]['total_karyawan']++;
                $empDoc = $docsByNrp[$kItem['nrp'] ?? ''] ?? null;
                $isBound = !PenilaianController::isQuotaExemptEmployee($kItem, $empDoc);
                if ($isBound) {
                    $realDivisionsStats[$rawDiv]['kena_target']++;
                }
                $posUpper = strtoupper(trim((string)($kItem['jabatan'] ?? '')));
                $isForeman = str_contains($posUpper, 'FOREMAN');
                $isSection = str_contains($posUpper, 'SECTION HEAD') || str_contains($posUpper, 'SECT HEAD') || str_contains($posUpper, 'SECTION_HEAD') || str_contains($posUpper, 'SEC HEAD');
                $isDeptHead = str_contains($posUpper, 'DEPT HEAD') || str_contains($posUpper, 'DEPARTMENT HEAD');
                $isDivHead = str_contains($posUpper, 'DIV HEAD') || str_contains($posUpper, 'DIVISION HEAD');
                $isLeader = str_contains($posUpper, 'LEADER');

                if ($isForeman) $realDivisionsStats[$rawDiv]['foreman']++;
                if ($isSection) $realDivisionsStats[$rawDiv]['section_head']++;
                if ($isDeptHead) $realDivisionsStats[$rawDiv]['dept_head']++;
                if ($isDivHead) $realDivisionsStats[$rawDiv]['div_head']++;
                if ($isLeader) $realDivisionsStats[$rawDiv]['leader']++;

                if ($mappedKode !== null) {
                    if (!in_array($mappedKode, $realDivisionsStats[$rawDiv]['dept_codes'], true)) {
                        $realDivisionsStats[$rawDiv]['dept_codes'][] = $mappedKode;
                        $realDivisionsStats[$rawDiv]['dept_details'][$mappedKode] = [
                            'total_karyawan' => 0,
                            'kena_target' => 0,
                            'foreman' => 0,
                            'section_head' => 0,
                            'dept_head' => 0,
                            'leader' => 0,
                        ];
                    }
                    $realDivisionsStats[$rawDiv]['dept_details'][$mappedKode]['total_karyawan']++;
                    if ($isBound) $realDivisionsStats[$rawDiv]['dept_details'][$mappedKode]['kena_target']++;
                    if ($isForeman) $realDivisionsStats[$rawDiv]['dept_details'][$mappedKode]['foreman']++;
                    if ($isSection) $realDivisionsStats[$rawDiv]['dept_details'][$mappedKode]['section_head']++;
                    if ($isDeptHead) $realDivisionsStats[$rawDiv]['dept_details'][$mappedKode]['dept_head']++;
                    if ($isLeader) $realDivisionsStats[$rawDiv]['dept_details'][$mappedKode]['leader']++;
                }
            }
        }

        foreach ($deptDefs as &$dDefRef) {
            if (isset($realDivDeptMap[$dDefRef['kode']])) {
                $dDefRef['divisi'] = $realDivDeptMap[$dDefRef['kode']];
            }
        }
        unset($dDefRef);

        // Keep strictly the real departments present in Data Karyawan (or requested in query)
        $karyawanDeptCodes = [];
        foreach ($allKaryawan as $k) {
            $d = strtoupper(trim((string)($k['departemen'] ?? '')));
            if ($d !== '' && $d !== '-') {
                $karyawanDeptCodes[$d] = true;
            }
        }

        $reqDeptUpper = strtoupper(trim((string) request('dept', '')));
        if (!empty($karyawanDeptCodes)) {
            $deptDefs = array_values(array_filter($deptDefs, function ($dDef) use ($karyawanDeptCodes, $reqDeptUpper) {
                $kode = strtoupper($dDef['kode']);
                if ($reqDeptUpper !== '' && $reqDeptUpper !== 'ALL' && $kode === $reqDeptUpper) {
                    return true;
                }
                if (isset($karyawanDeptCodes[$kode])) {
                    return true;
                }
                foreach ($dDef['match'] as $m) {
                    if (isset($karyawanDeptCodes[strtoupper($m)])) {
                        return true;
                    }
                }
                return false;
            }));
        }

        // 3. Fetch headcount quotas from pengaturan or kuota_departemen
        $allPengaturan = DB::table('pengaturan')->get()->keyBy('key');
        $allKuotaDept = DB::table('kuota_departemen')->get();

        // Calculate actual sum per department from kuota_departemen
        $deptSums = [];
        foreach ($allKuotaDept as $kd) {
            $d = $kd->departemen;
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

        $simulasiDepartemen = [];
        $deptTargetsMap = [];
        $sumDeptS = $sumDeptA = $sumDeptB = $sumDeptC = $sumDeptK = 0;

        foreach ($deptDefs as $def) {
            $matchingKaryawan = array_filter($allQuotaBoundKaryawan, function($k) use ($def) {
                return in_array(strtoupper($k['departemen'] ?? ''), $def['match']);
            });
            $tot = count($matchingKaryawan);

            // Check if specific setting in pengaturan
            $customSettingKey = 'target_kuota_dept_' . $def['kode'];
            $customSetting = isset($allPengaturan[$customSettingKey]) ? json_decode($allPengaturan[$customSettingKey]->value, true) : null;

            // Jika departemen ini memiliki divisi_kode (seperti ENGINEERING -> DIV-E), hubungkan juga jika target divisi disetel
            if (!$customSetting && !empty($def['divisi_kode'])) {
                $customDivKey = 'target_kuota_div_' . $def['divisi_kode'];
                if (isset($allPengaturan[$customDivKey])) {
                    $customSetting = json_decode($allPengaturan[$customDivKey]->value, true);
                }
            }

            if ($customSetting && isset($customSetting['S'])) {
                $tS = (int)($customSetting['S'] ?? 0);
                $tA = (int)($customSetting['A'] ?? 0);
                $tB = (int)($customSetting['B'] ?? 0);
                $tC = (int)($customSetting['C'] ?? 0);
                $tK = (int)($customSetting['K'] ?? 0);
                $isCustom = true;
            } elseif (isset($deptSums[$def['kode']]) && ($deptSums[$def['kode']]['total'] === $tot || $tot === 0)) {
                $tS = $deptSums[$def['kode']]['S'];
                $tA = $deptSums[$def['kode']]['A'];
                $tB = $deptSums[$def['kode']]['B'];
                $tC = $deptSums[$def['kode']]['C'];
                $tK = $deptSums[$def['kode']]['K'];
                $isCustom = true;
            } else {
                if ($tot > 0) {
                    $tS = max(0, (int)round($tot * 0.10));
                    $tA = max(0, (int)round($tot * 0.30));
                    $tB = max(0, (int)round($tot * 0.30));
                    $tC = max(0, (int)round($tot * 0.20));
                    $tK = max(0, $tot - ($tS + $tA + $tB + $tC));
                } else {
                    $tS = $tA = $tB = $tC = $tK = 0;
                }
                $isCustom = false;
            }

            $sumDeptS += $tS;
            $sumDeptA += $tA;
            $sumDeptB += $tB;
            $sumDeptC += $tC;
            $sumDeptK += $tK;

            $deptTargetsMap[$def['kode']] = [
                's' => $tS,
                'a' => $tA,
                'b' => $tB,
                'c' => $tC,
                'k' => $tK,
                'total' => $tot,
                'name' => $def['departemen'],
                'type' => 'departemen',
            ];

            $simulasiDepartemen[] = [
                'kode' => $def['kode'],
                'departemen' => $def['departemen'],
                'divisi' => $def['divisi'],
                'divisi_kode' => $def['divisi_kode'],
                'total_karyawan' => $tot,
                'target_s' => $tS,
                'target_a' => $tA,
                'target_b' => $tB,
                'target_c' => $tC,
                'target_k' => $tK,
                'total_target' => $tS + $tA + $tB + $tC + $tK,
                'is_custom' => $isCustom,
            ];
        }

        // 4. Calculate Division Aggregates & Custom Division Quotas
        $simulasiDivisi = [];
        $sumDivS = $sumDivA = $sumDivB = $sumDivC = $sumDivK = 0;
        $sumDivTotKaryawan = 0;

        foreach ($divisiDefs as $divDef) {
            $divDepts = $divDef['depts'];
            $matchingKaryawanDiv = [];
            $sumDeptTargetS = 0;
            $sumDeptTargetA = 0;
            $sumDeptTargetB = 0;
            $sumDeptTargetC = 0;
            $sumDeptTargetK = 0;

            foreach ($divDepts as $dCode) {
                if (isset($deptTargetsMap[$dCode])) {
                    $sumDeptTargetS += $deptTargetsMap[$dCode]['s'];
                    $sumDeptTargetA += $deptTargetsMap[$dCode]['a'];
                    $sumDeptTargetB += $deptTargetsMap[$dCode]['b'];
                    $sumDeptTargetC += $deptTargetsMap[$dCode]['c'];
                    $sumDeptTargetK += $deptTargetsMap[$dCode]['k'];
                }
                foreach ($deptDefs as $dDef) {
                    if ($dDef['kode'] === $dCode) {
                        $divKaryawan = array_filter($allQuotaBoundKaryawan, function($k) use ($dDef) {
                            return in_array(strtoupper($k['departemen'] ?? ''), $dDef['match']);
                        });
                        $matchingKaryawanDiv = array_merge($matchingKaryawanDiv, $divKaryawan);
                    }
                }
            }

            $divTotKaryawan = count($matchingKaryawanDiv);
            $sumDivTotKaryawan += $divTotKaryawan;

            $customDivKey = 'target_kuota_div_' . $divDef['kode'];
            $customDivSetting = isset($allPengaturan[$customDivKey]) ? json_decode($allPengaturan[$customDivKey]->value, true) : null;
            if (!$customDivSetting && $divDef['kode'] === 'DIV-E' && isset($allPengaturan['target_kuota_dept_ENGINEERING'])) {
                $customDivSetting = json_decode($allPengaturan['target_kuota_dept_ENGINEERING']->value, true);
            }

            if ($customDivSetting && isset($customDivSetting['S'])) {
                $dS = (int)($customDivSetting['S'] ?? 0);
                $dA = (int)($customDivSetting['A'] ?? 0);
                $dB = (int)($customDivSetting['B'] ?? 0);
                $dC = (int)($customDivSetting['C'] ?? 0);
                $dK = (int)($customDivSetting['K'] ?? 0);
                $isCustomDiv = true;
            } else {
                if ($sumDeptTargetS + $sumDeptTargetA + $sumDeptTargetB + $sumDeptTargetC + $sumDeptTargetK > 0) {
                    $dS = $sumDeptTargetS;
                    $dA = $sumDeptTargetA;
                    $dB = $sumDeptTargetB;
                    $dC = $sumDeptTargetC;
                    $dK = $sumDeptTargetK;
                } elseif ($divTotKaryawan > 0) {
                    $dS = max(0, (int)round($divTotKaryawan * 0.10));
                    $dA = max(0, (int)round($divTotKaryawan * 0.30));
                    $dB = max(0, (int)round($divTotKaryawan * 0.30));
                    $dC = max(0, (int)round($divTotKaryawan * 0.20));
                    $dK = max(0, $divTotKaryawan - ($dS + $dA + $dB + $dC));
                } else {
                    $dS = $dA = $dB = $dC = $dK = 0;
                }
                $isCustomDiv = false;
            }

            $sumDivS += $dS;
            $sumDivA += $dA;
            $sumDivB += $dB;
            $sumDivC += $dC;
            $sumDivK += $dK;

            $deptTargetsMap[$divDef['kode']] = [
                's' => $dS,
                'a' => $dA,
                'b' => $dB,
                'c' => $dC,
                'k' => $dK,
                'total' => $divTotKaryawan,
                'name' => $divDef['nama'],
                'type' => 'divisi',
            ];

            $simulasiDivisi[] = [
                'kode' => $divDef['kode'],
                'nama' => $divDef['nama'],
                'singkat' => $divDef['singkat'],
                'departemen_desc' => $divDef['departemen_desc'],
                'total_karyawan' => $divTotKaryawan,
                'target_s' => $dS,
                'target_a' => $dA,
                'target_b' => $dB,
                'target_c' => $dC,
                'target_k' => $dK,
                'total_target' => $dS + $dA + $dB + $dC + $dK,
                'is_custom' => $isCustomDiv,
            ];
        }

        // 5. Global default targets
        $globalHeadcountSetting = $allPengaturan['target_kuota_headcount']->value ?? null;
        $globalHeadcount = $globalHeadcountSetting ? json_decode($globalHeadcountSetting, true) : null;

        if (!$globalHeadcount) {
            $globalHeadcount = [
                'S' => $sumDivS ?: 2,
                'A' => $sumDivA ?: 7,
                'B' => $sumDivB ?: 11,
                'C' => $sumDivC ?: 4,
                'K' => $sumDivK ?: 1,
            ];
        }

        $deptTargetsMap['all'] = [
            's' => (int)($globalHeadcount['S'] ?? $sumDivS),
            'a' => (int)($globalHeadcount['A'] ?? $sumDivA),
            'b' => (int)($globalHeadcount['B'] ?? $sumDivB),
            'c' => (int)($globalHeadcount['C'] ?? $sumDivC),
            'k' => (int)($globalHeadcount['K'] ?? $sumDivK),
            'total' => $totalPerusahaanKaryawan,
            'name' => 'Standar Seluruh Perusahaan',
            'type' => 'global',
        ];

        $totalSimulation = [
            'total_karyawan' => $totalPerusahaanKaryawan,
            'target_s' => $sumDeptS,
            'target_a' => $sumDeptA,
            'target_b' => $sumDeptB,
            'target_c' => $sumDeptC,
            'target_k' => $sumDeptK,
            'total_target' => $sumDeptS + $sumDeptA + $sumDeptB + $sumDeptC + $sumDeptK,
        ];

        $totalDivSimulation = [
            'total_karyawan' => $sumDivTotKaryawan,
            'target_s' => $sumDivS,
            'target_a' => $sumDivA,
            'target_b' => $sumDivB,
            'target_c' => $sumDivC,
            'target_k' => $sumDivK,
            'total_target' => $sumDivS + $sumDivA + $sumDivB + $sumDivC + $sumDivK,
        ];

        // 6. Build Unified Table List: Target Kuota per Divisi & Departemen
        // Rule: When there is a Division Head, show Division Head (omit subordinate Dept Heads to avoid duplicates).
        // When there is no Division Head (e.g. HR & GA), show Department Head directly.
        $tabelTargetKuota = [];
        $sumHierarkiKaryawan = 0;
        $sumHierarkiS = $sumHierarkiA = $sumHierarkiB = $sumHierarkiC = $sumHierarkiK = 0;

        foreach ($simulasiDivisi as $sDiv) {
            $hasDivHead = in_array($sDiv['kode'], ['DIV-E', 'DIV-MFG', 'DIV-QC']);
            $pejabatName = match($sDiv['kode']) {
                'DIV-E' => 'Division Head E',
                'DIV-MFG' => 'Bambang Widodo',
                'DIV-QC' => 'Hendra Gunawan',
                default => '-'
            };

            $showInHierarki = $hasDivHead; // Division with active Division Head

            if ($showInHierarki) {
                $sumHierarkiKaryawan += (int)$sDiv['total_karyawan'];
                $sumHierarkiS += (int)$sDiv['target_s'];
                $sumHierarkiA += (int)$sDiv['target_a'];
                $sumHierarkiB += (int)$sDiv['target_b'];
                $sumHierarkiC += (int)$sDiv['target_c'];
                $sumHierarkiK += (int)$sDiv['target_k'];
            }

            $tabelTargetKuota[] = [
                'id' => 'div_' . $sDiv['kode'],
                'kode' => $sDiv['kode'],
                'kategori' => 'divisi',
                'level' => 'DIV_HEAD',
                'level_label' => 'Division Head',
                'nama' => $sDiv['nama'],
                'singkat' => $sDiv['singkat'],
                'cakupan' => $sDiv['departemen_desc'],
                'pejabat' => $pejabatName,
                'has_div_head' => $hasDivHead,
                'show_in_hierarki' => $showInHierarki,
                'total_karyawan' => $sDiv['total_karyawan'],
                'target_s' => $sDiv['target_s'],
                'target_a' => $sDiv['target_a'],
                'target_b' => $sDiv['target_b'],
                'target_c' => $sDiv['target_c'],
                'target_k' => $sDiv['target_k'],
                'total_target' => $sDiv['total_target'],
                'is_custom' => $sDiv['is_custom'],
            ];
        }

        foreach ($simulasiDepartemen as $sDept) {
            $isStandalone = ($sDept['divisi_kode'] === 'DIV-GA'); // General Affairs has NO Division Head
            $pejabatName = match($sDept['kode']) {
                'ENGINEERING' => 'Arif Budiman',
                'PROD 1' => 'Agus Setiawan',
                'PROD 2', 'MACHINING' => 'SUPARDI',
                'QA/QC' => 'Suryo Utomo',
                'HR & GA' => 'Indra Wijaya & Fajar Santoso',
                default => '-'
            };

            $showInHierarki = $isStandalone; // Direct Dept Head without Division Head

            if ($showInHierarki) {
                $sumHierarkiKaryawan += (int)$sDept['total_karyawan'];
                $sumHierarkiS += (int)$sDept['target_s'];
                $sumHierarkiA += (int)$sDept['target_a'];
                $sumHierarkiB += (int)$sDept['target_b'];
                $sumHierarkiC += (int)$sDept['target_c'];
                $sumHierarkiK += (int)$sDept['target_k'];
            }

            $tabelTargetKuota[] = [
                'id' => 'dept_' . $sDept['kode'],
                'kode' => $sDept['kode'],
                'kategori' => 'departemen',
                'level' => 'DEPT_HEAD',
                'level_label' => 'Department Head',
                'nama' => $sDept['departemen'],
                'singkat' => $sDept['kode'],
                'cakupan' => $sDept['divisi'],
                'pejabat' => $pejabatName,
                'has_div_head' => !$isStandalone,
                'show_in_hierarki' => $showInHierarki,
                'total_karyawan' => $sDept['total_karyawan'],
                'target_s' => $sDept['target_s'],
                'target_a' => $sDept['target_a'],
                'target_b' => $sDept['target_b'],
                'target_c' => $sDept['target_c'],
                'target_k' => $sDept['target_k'],
                'total_target' => $sDept['total_target'],
                'is_custom' => $sDept['is_custom'],
            ];
        }

        $totalHierarki = [
            'total_karyawan' => $sumHierarkiKaryawan,
            'target_s' => $sumHierarkiS,
            'target_a' => $sumHierarkiA,
            'target_b' => $sumHierarkiB,
            'target_c' => $sumHierarkiC,
            'target_k' => $sumHierarkiK,
            'total_target' => $sumHierarkiS + $sumHierarkiA + $sumHierarkiB + $sumHierarkiC + $sumHierarkiK,
        ];

        // 7. Master KJ List & Quota per KJ (KJ 2, KJ 3, KJ 4, KJ 5, serta KJ operasional aktif seperti KJ 6 Foreman)
        $masterKjSetting = DB::table('pengaturan')->where('key', 'master_kj_list')->first();
        if ($masterKjSetting && !empty($masterKjSetting->value)) {
            $masterKjList = json_decode($masterKjSetting->value, true) ?: [];
        } else {
            $masterKjList = [
                ['kode' => 'KJ 2', 'golongan' => 'GOLONGAN II', 'deskripsi' => 'Operator / Staff (Form 07-001 • KJ 1-3)', 'is_default' => true],
                ['kode' => 'KJ 3', 'golongan' => 'GOLONGAN III', 'deskripsi' => 'Operator / Staff / Leader (Form 07-001 / 07-003)', 'is_default' => true],
                ['kode' => 'KJ 4', 'golongan' => 'GOLONGAN IV', 'deskripsi' => 'Leader / KJ 4-5 Non-Jabatan / Foreman (Form 07-003 / 07-002 / 07-004)', 'is_default' => true],
                ['kode' => 'KJ 5', 'golongan' => 'GOLONGAN V', 'deskripsi' => 'Foreman / KJ 4-5 Non-Jabatan (Form 07-004 / 07-002 • Bebas 07-006)', 'is_default' => true],
            ];
            DB::table('pengaturan')->updateOrInsert(
                ['key' => 'master_kj_list'],
                ['value' => json_encode($masterKjList), 'created_at' => now(), 'updated_at' => now()]
            );
        }

        // Normalize outdated KJ 5 label in memory so Section Head (07-006-SH) is never conflated with quota-bound KJ 5
        foreach ($masterKjList as &$mkItem) {
            if (($mkItem['kode'] ?? '') === 'KJ 5' && ($mkItem['deskripsi'] ?? '') === 'Section Head / Asisten Manajer') {
                $mkItem['deskripsi'] = 'Foreman / KJ 4-5 Non-Jabatan (Form 07-004 / 07-002 • Bebas 07-006)';
            }
        }
        unset($mkItem);

        // Group operational KJs above KJ 5 into KJ 5 quota bucket so no KJ 6+ row/card is auto-added
        $displayKjList = $masterKjList;
        $registeredKjCodes = array_map(fn ($item) => ExcelKaryawanReader::normalizeQuotaKj($item['kode'] ?? ''), $displayKjList);
        $detectedOperationalKjs = [];
        foreach ($allQuotaBoundKaryawan as $qbEmp) {
            $normKj = ExcelKaryawanReader::normalizeQuotaKj($qbEmp['kj'] ?? '');
            if ($normKj !== '' && !in_array($normKj, $registeredKjCodes, true) && !in_array($normKj, $detectedOperationalKjs, true)) {
                $detectedOperationalKjs[] = $normKj;
            }
        }
        sort($detectedOperationalKjs, SORT_NATURAL);
        foreach ($detectedOperationalKjs as $opKj) {
            $displayKjList[] = [
                'kode' => $opKj,
                'golongan' => 'GOLONGAN ' . trim(str_replace('KJ', '', $opKj)),
                'deskripsi' => 'Operasional / Foreman Non-07-006 (Kena Target)',
                'is_default' => false,
                'is_auto_detected' => true,
            ];
        }

        $deptRealTotals = [];
        foreach ($deptDefs as $dDef) {
            $deptRealTotals[$dDef['kode']] = count(array_filter(
                $allQuotaBoundKaryawan,
                fn ($k) => in_array(strtoupper($k['departemen'] ?? ''), $dDef['match'])
            ));
        }

        // Compute summary cards per KJ and detailed table per KJ & Departemen
        $allKd = DB::table('kuota_departemen')->get();
        $tabelKuotaKj = [];
        $summaryKjCards = [];
        $kjTargetsMap = [];

        foreach ($displayKjList as $kjDef) {
            $kCode = $kjDef['kode'];
            $kNorm = ExcelKaryawanReader::normalizeKj($kCode);
            $sumKjTot = 0;
            $sumKjS = $sumKjA = $sumKjB = $sumKjC = $sumKjK = 0;

            foreach ($deptDefs as $dDef) {
                $deptCode = $dDef['kode'];
                $matchingKaryawanKj = array_filter($allQuotaBoundKaryawan, function($k) use ($dDef, $kNorm) {
                    $matchDept = in_array(strtoupper($k['departemen'] ?? ''), $dDef['match']);
                    $matchKj = ExcelKaryawanReader::normalizeQuotaKj($k['kj'] ?? '') === $kNorm;
                    return $matchDept && $matchKj;
                });
                $totKaryawanKj = count($matchingKaryawanKj);
                $sumKjTot += $totKaryawanKj;

                // Always trust and prioritize configured kuota_departemen row
                $existingKd = $allKd->first(function($item) use ($deptCode, $kNorm) {
                    return $item->departemen === $deptCode && ExcelKaryawanReader::normalizeQuotaKj($item->kj ?? '') === $kNorm;
                });

                if ($existingKd) {
                    $kdS = (int)$existingKd->kuota_s;
                    $kdA = (int)$existingKd->kuota_a;
                    $kdB = (int)$existingKd->kuota_b;
                    $kdC = (int)$existingKd->kuota_c;
                    $kdK = (int)$existingKd->kuota_k;
                    $isCustomKd = true;
                } else {
                    if ($totKaryawanKj > 0) {
                        $kdS = max(0, (int)round($totKaryawanKj * 0.10));
                        $kdA = max(0, (int)round($totKaryawanKj * 0.30));
                        $kdB = max(0, (int)round($totKaryawanKj * 0.30));
                        $kdC = max(0, (int)round($totKaryawanKj * 0.20));
                        $kdK = max(0, $totKaryawanKj - ($kdS + $kdA + $kdB + $kdC));
                    } else {
                        $kdS = $kdA = $kdB = $kdC = $kdK = 0;
                    }
                    $isCustomKd = false;
                }

                $sumKjS += $kdS;
                $sumKjA += $kdA;
                $sumKjB += $kdB;
                $sumKjC += $kdC;
                $sumKjK += $kdK;

                $mapKey = $deptCode . '_' . str_replace(' ', '', $kCode);
                $kjTargetsMap[$mapKey] = [
                    'kj' => $kCode,
                    'dept' => $deptCode,
                    'dept_name' => $dDef['departemen'],
                    'golongan' => $kjDef['golongan'],
                    's' => $kdS,
                    'a' => $kdA,
                    'b' => $kdB,
                    'c' => $kdC,
                    'k' => $kdK,
                    'total' => $totKaryawanKj,
                ];

                $tabelKuotaKj[] = [
                    'id' => 'kj_' . str_replace(' ', '', $kCode) . '_' . $deptCode,
                    'kj' => $kCode,
                    'golongan' => $kjDef['golongan'],
                    'deskripsi' => $kjDef['deskripsi'] ?? '',
                    'departemen_kode' => $deptCode,
                    'departemen_nama' => $dDef['departemen'],
                    'divisi_kode' => $dDef['divisi_kode'] ?? '',
                    'divisi_nama' => $dDef['divisi'],
                    'total_karyawan' => $totKaryawanKj,
                    'target_s' => $kdS,
                    'kuota_s' => $kdS,
                    'target_a' => $kdA,
                    'kuota_a' => $kdA,
                    'target_b' => $kdB,
                    'kuota_b' => $kdB,
                    'target_c' => $kdC,
                    'kuota_c' => $kdC,
                    'target_k' => $kdK,
                    'kuota_k' => $kdK,
                    'total_target' => $kdS + $kdA + $kdB + $kdC + $kdK,
                    'is_custom' => $isCustomKd,
                ];
            }

            // Division mapping for this KJ across departments of each division
            foreach ($divisiDefs as $divDef) {
                $divCode = $divDef['kode'];
                $divDepts = $divDef['depts'];
                $totDivKj = 0;
                $sumDivKjS = $sumDivKjA = $sumDivKjB = $sumDivKjC = $sumDivKjK = 0;

                foreach ($divDepts as $dCode) {
                    $dKey = $dCode . '_' . str_replace(' ', '', $kCode);
                    if (isset($kjTargetsMap[$dKey])) {
                        $totDivKj += $kjTargetsMap[$dKey]['total'];
                        $sumDivKjS += $kjTargetsMap[$dKey]['s'];
                        $sumDivKjA += $kjTargetsMap[$dKey]['a'];
                        $sumDivKjB += $kjTargetsMap[$dKey]['b'];
                        $sumDivKjC += $kjTargetsMap[$dKey]['c'];
                        $sumDivKjK += $kjTargetsMap[$dKey]['k'];
                    }
                }

                $customDivKjKey = 'target_kuota_kj_' . $divCode . '_' . str_replace(' ', '', $kCode);
                $customDivKjSetting = isset($allPengaturan[$customDivKjKey]) ? json_decode($allPengaturan[$customDivKjKey]->value, true) : null;
                if ($customDivKjSetting && isset($customDivKjSetting['S'])) {
                    $sumDivKjS = (int)$customDivKjSetting['S'];
                    $sumDivKjA = (int)$customDivKjSetting['A'];
                    $sumDivKjB = (int)$customDivKjSetting['B'];
                    $sumDivKjC = (int)$customDivKjSetting['C'];
                    $sumDivKjK = (int)$customDivKjSetting['K'];
                }

                $mapDivKey = $divCode . '_' . str_replace(' ', '', $kCode);
                $kjTargetsMap[$mapDivKey] = [
                    'kj' => $kCode,
                    'dept' => $divCode,
                    'dept_name' => $divDef['nama'],
                    'golongan' => $kjDef['golongan'],
                    's' => $sumDivKjS,
                    'a' => $sumDivKjA,
                    'b' => $sumDivKjB,
                    'c' => $sumDivKjC,
                    'k' => $sumDivKjK,
                    'total' => $totDivKj,
                ];
            }

            // Global map for this KJ across all departments
            $mapAllKey = 'all_' . str_replace(' ', '', $kCode);
            $kjTargetsMap[$mapAllKey] = [
                'kj' => $kCode,
                'dept' => 'all',
                'dept_name' => 'Seluruh Perusahaan',
                'golongan' => $kjDef['golongan'],
                's' => $sumKjS,
                'a' => $sumKjA,
                'b' => $sumKjB,
                'c' => $sumKjC,
                'k' => $sumKjK,
                'total' => $sumKjTot,
            ];

            $summaryKjCards[] = [
                'kode' => $kCode,
                'golongan' => $kjDef['golongan'],
                'deskripsi' => $kjDef['deskripsi'] ?? '',
                'is_default' => $kjDef['is_default'] ?? false,
                'is_auto_detected' => $kjDef['is_auto_detected'] ?? false,
                'total_karyawan' => $sumKjTot,
                'target_s' => $sumKjS,
                'target_a' => $sumKjA,
                'target_b' => $sumKjB,
                'target_c' => $sumKjC,
                'target_k' => $sumKjK,
                'total_target' => $sumKjS + $sumKjA + $sumKjB + $sumKjC + $sumKjK,
            ];
        }

        // Sync display totals for Divisions only when explicit per-KJ division overrides exist (never clobber DB Mode 1 targets on GET)
        foreach ($divisiDefs as $divDef) {
            $divCode = $divDef['kode'];
            $hasExplicitDivKj = false;
            $sumKjDivS = 0; $sumKjDivA = 0; $sumKjDivB = 0; $sumKjDivC = 0; $sumKjDivK = 0;

            foreach ($displayKjList as $mDef) {
                $sanitizedKj = str_replace(' ', '', $mDef['kode']);
                if (isset($allPengaturan['target_kuota_kj_' . $divCode . '_' . $sanitizedKj])) {
                    $hasExplicitDivKj = true;
                }
                $kKey = $divCode . '_' . $sanitizedKj;
                if (isset($kjTargetsMap[$kKey])) {
                    $sumKjDivS += (int)$kjTargetsMap[$kKey]['s'];
                    $sumKjDivA += (int)$kjTargetsMap[$kKey]['a'];
                    $sumKjDivB += (int)$kjTargetsMap[$kKey]['b'];
                    $sumKjDivC += (int)$kjTargetsMap[$kKey]['c'];
                    $sumKjDivK += (int)$kjTargetsMap[$kKey]['k'];
                }
            }

            $sumKjDivTotal = $sumKjDivS + $sumKjDivA + $sumKjDivB + $sumKjDivC + $sumKjDivK;
            $hasExplicitMode1Div = isset($allPengaturan['target_kuota_div_' . $divCode])
                || ($divCode === 'DIV-E' && isset($allPengaturan['target_kuota_dept_ENGINEERING']));
            if ($hasExplicitDivKj && $sumKjDivTotal > 0 && !$hasExplicitMode1Div) {
                if (isset($deptTargetsMap[$divCode])) {
                    $deptTargetsMap[$divCode]['s'] = $sumKjDivS;
                    $deptTargetsMap[$divCode]['a'] = $sumKjDivA;
                    $deptTargetsMap[$divCode]['b'] = $sumKjDivB;
                    $deptTargetsMap[$divCode]['c'] = $sumKjDivC;
                    $deptTargetsMap[$divCode]['k'] = $sumKjDivK;
                    $deptTargetsMap[$divCode]['total'] = $sumKjDivTotal;
                }

                if ($divCode === 'DIV-E' && isset($deptTargetsMap['ENGINEERING'])) {
                    $deptTargetsMap['ENGINEERING']['s'] = $sumKjDivS;
                    $deptTargetsMap['ENGINEERING']['a'] = $sumKjDivA;
                    $deptTargetsMap['ENGINEERING']['b'] = $sumKjDivB;
                    $deptTargetsMap['ENGINEERING']['c'] = $sumKjDivC;
                    $deptTargetsMap['ENGINEERING']['k'] = $sumKjDivK;
                    $deptTargetsMap['ENGINEERING']['total'] = $sumKjDivTotal;
                }

                foreach ($simulasiDivisi as &$sDivRef) {
                    if ($sDivRef['kode'] === $divCode) {
                        $sDivRef['target_s'] = $sumKjDivS;
                        $sDivRef['target_a'] = $sumKjDivA;
                        $sDivRef['target_b'] = $sumKjDivB;
                        $sDivRef['target_c'] = $sumKjDivC;
                        $sDivRef['target_k'] = $sumKjDivK;
                        $sDivRef['total_target'] = $sumKjDivTotal;
                    }
                }
                unset($sDivRef);

                foreach ($simulasiDepartemen as &$sDeptRef) {
                    if ($divCode === 'DIV-E' && $sDeptRef['kode'] === 'ENGINEERING') {
                        $sDeptRef['target_s'] = $sumKjDivS;
                        $sDeptRef['target_a'] = $sumKjDivA;
                        $sDeptRef['target_b'] = $sumKjDivB;
                        $sDeptRef['target_c'] = $sumKjDivC;
                        $sDeptRef['target_k'] = $sumKjDivK;
                        $sDeptRef['total_target'] = $sumKjDivTotal;
                    }
                }
                unset($sDeptRef);

                foreach ($tabelTargetKuota as &$tRowRef) {
                    if ($tRowRef['kode'] === $divCode || ($divCode === 'DIV-E' && $tRowRef['kode'] === 'ENGINEERING')) {
                        $tRowRef['target_s'] = $sumKjDivS;
                        $tRowRef['target_a'] = $sumKjDivA;
                        $tRowRef['target_b'] = $sumKjDivB;
                        $tRowRef['target_c'] = $sumKjDivC;
                        $tRowRef['target_k'] = $sumKjDivK;
                        $tRowRef['total_target'] = $sumKjDivTotal;
                    }
                }
                unset($tRowRef);
            }
        }

        // 8. Pengaturan Target Level Form (Kena Target: Form Selain 07-006; 07-006 Bebas Target)
        $levelFormDefs = [
            [
                'kode' => 'KJ13',
                'kode_form' => '07-001',
                'nama' => 'Form KJ 1-3',
                'deskripsi' => 'Operator / Staff non-jabatan pada KJ 1 s/d KJ 3',
                'kena_target' => true,
            ],
            [
                'kode' => 'KJ45',
                'kode_form' => '07-002',
                'nama' => 'Form KJ 4-5 Non Jabatan',
                'deskripsi' => 'Staff / Specialist non-struktural pada KJ 4 ke atas',
                'kena_target' => true,
            ],
            [
                'kode' => 'LEADER',
                'kode_form' => '07-003',
                'nama' => 'Form Leader',
                'deskripsi' => 'Pemegang posisi Leader (berapapun KJ-nya)',
                'kena_target' => true,
            ],
            [
                'kode' => 'FOREMAN',
                'kode_form' => '07-004',
                'nama' => 'Form Foreman',
                'deskripsi' => 'Foreman & Sub-Foreman (KJ 4, KJ 5+ — terhubung ke Target KJ 5)',
                'kena_target' => true,
            ],
            [
                'kode' => 'EXEMPT_07006',
                'kode_form' => '07-006',
                'nama' => 'Form Section / Dept / Div Head (07-006)',
                'deskripsi' => 'Pimpinan Unit (07-006-SH, 07-006-DH, 07-006-DIV) — Bebas Target & Kunci Terbuka',
                'kena_target' => false,
            ],
        ];

        $resolveLevelKey = function (array $emp) use ($docsByNrp): string {
            $doc = $docsByNrp[$emp['nrp'] ?? ''] ?? null;
            $kodeForm = !empty($doc['kode_form']) ? strtoupper(trim((string)$doc['kode_form'])) : PenilaianController::determineKodeForm($emp);
            if (PenilaianController::isForm07006($kodeForm) || PenilaianController::isQuotaExemptEmployee($emp, $doc)) {
                return 'EXEMPT_07006';
            }
            return match ($kodeForm) {
                '07-004' => 'FOREMAN',
                '07-003' => 'LEADER',
                '07-002' => 'KJ45',
                default => 'KJ13',
            };
        };

        $unitScopesForLevel = [['kode' => 'all', 'nama' => 'Seluruh Perusahaan', 'match' => null, 'type' => 'global']];
        foreach ($divisiDefs as $divDef) {
            $divMatch = [];
            foreach ($deptDefs as $dDef) {
                if (in_array($dDef['kode'], $divDef['depts'], true)) {
                    $divMatch = array_merge($divMatch, $dDef['match']);
                }
            }
            $unitScopesForLevel[] = ['kode' => $divDef['kode'], 'nama' => $divDef['nama'], 'match' => $divMatch, 'type' => 'divisi'];
        }
        foreach ($deptDefs as $dDef) {
            $unitScopesForLevel[] = ['kode' => $dDef['kode'], 'nama' => $dDef['departemen'], 'match' => $dDef['match'], 'type' => 'departemen'];
        }

        $levelTargetsMap = [];
        $tabelKuotaLevel = [];
        $levelFormSummaryCards = [];

        foreach ($levelFormDefs as $lDef) {
            $lCode = $lDef['kode'];
            $isBound = $lDef['kena_target'];

            foreach ($unitScopesForLevel as $uScope) {
                $uCode = $uScope['kode'];
                $matchingUnitEmps = array_filter($allKaryawan, function ($k) use ($uScope, $lCode, $resolveLevelKey) {
                    if ($resolveLevelKey($k) !== $lCode) {
                        return false;
                    }
                    if ($uScope['match'] === null) {
                        return true;
                    }
                    return in_array(strtoupper($k['departemen'] ?? ''), $uScope['match']);
                });
                $cntEmp = count($matchingUnitEmps);

                if (!$isBound) {
                    $lS = $lA = $lB = $lC = $lK = 0;
                    $isCustomLvl = false;
                } else {
                    $lvlSettingKey = 'target_kuota_level_' . $uCode . '_' . $lCode;
                    $lvlSetting = isset($allPengaturan[$lvlSettingKey]) ? json_decode($allPengaturan[$lvlSettingKey]->value, true) : null;
                    if ($lvlSetting && isset($lvlSetting['S'])) {
                        $lS = (int)($lvlSetting['S'] ?? 0);
                        $lA = (int)($lvlSetting['A'] ?? 0);
                        $lB = (int)($lvlSetting['B'] ?? 0);
                        $lC = (int)($lvlSetting['C'] ?? 0);
                        $lK = (int)($lvlSetting['K'] ?? 0);
                        $isCustomLvl = true;
                    } elseif ($cntEmp > 0) {
                        $lS = max(0, (int)round($cntEmp * 0.10));
                        $lA = max(0, (int)round($cntEmp * 0.30));
                        $lB = max(0, (int)round($cntEmp * 0.30));
                        $lC = max(0, (int)round($cntEmp * 0.20));
                        $lK = max(0, $cntEmp - ($lS + $lA + $lB + $lC));
                        $isCustomLvl = false;
                    } else {
                        $lS = $lA = $lB = $lC = $lK = 0;
                        $isCustomLvl = false;
                    }
                }

                $mapLvlKey = $uCode . '_' . $lCode;
                $levelTargetsMap[$mapLvlKey] = [
                    'level' => $lCode,
                    'kode_form' => $lDef['kode_form'],
                    'nama' => $lDef['nama'],
                    'unit' => $uCode,
                    'unit_name' => $uScope['nama'],
                    'kena_target' => $isBound,
                    'total_karyawan' => $cntEmp,
                    's' => $lS,
                    'a' => $lA,
                    'b' => $lB,
                    'c' => $lC,
                    'k' => $lK,
                    'total_target' => $lS + $lA + $lB + $lC + $lK,
                    'is_custom' => $isCustomLvl,
                ];

                if ($uScope['type'] === 'departemen') {
                    $tabelKuotaLevel[] = $levelTargetsMap[$mapLvlKey];
                }
            }

            $globalLvl = $levelTargetsMap['all_' . $lCode];
            $levelFormSummaryCards[] = array_merge($lDef, [
                'total_karyawan' => $globalLvl['total_karyawan'],
                'target_s' => $globalLvl['s'],
                'target_a' => $globalLvl['a'],
                'target_b' => $globalLvl['b'],
                'target_c' => $globalLvl['c'],
                'target_k' => $globalLvl['k'],
                'total_target' => $globalLvl['total_target'],
            ]);
        }

        $sumDivS = array_sum(array_column($simulasiDivisi, 'target_s'));
        $sumDivA = array_sum(array_column($simulasiDivisi, 'target_a'));
        $sumDivB = array_sum(array_column($simulasiDivisi, 'target_b'));
        $sumDivC = array_sum(array_column($simulasiDivisi, 'target_c'));
        $sumDivK = array_sum(array_column($simulasiDivisi, 'target_k'));
        $totalDivSimulation['target_s'] = $sumDivS;
        $totalDivSimulation['target_a'] = $sumDivA;
        $totalDivSimulation['target_b'] = $sumDivB;
        $totalDivSimulation['target_c'] = $sumDivC;
        $totalDivSimulation['target_k'] = $sumDivK;
        $totalDivSimulation['total_target'] = $sumDivS + $sumDivA + $sumDivB + $sumDivC + $sumDivK;

        // System validation rules
        $rules = [
            'wajib_kunci' => true,
            'notifikasi_lebih' => true,
            'karyawan_sp' => true,
            'tahun' => (int)date('Y'),
        ];

        // Active State Preservation (Mode 1, Mode 2, Mode 3)
        $initialScope = $request->query('scope', session('selected_scope', 'all'));
        $initialKjDept = $request->query('dept', session('selected_kj_dept', 'all'));
        $initialKj = $request->query('kj', session('selected_kj', $masterKjList[0]['kode'] ?? 'KJ 2'));
        $initialLevelUnit = $request->query('level_unit', session('selected_level_unit', 'all'));
        $initialLevelForm = $request->query('level_form', session('selected_level_form', 'KJ13'));
        $initialTableTab = $request->query('table_tab', session('selected_table_tab', str_starts_with($initialScope, 'DIV-') ? 'divisi' : (str_starts_with($initialKjDept, 'DIV-') ? 'divisi' : 'all')));

        // Build $realDivisions sorted naturally from actual karyawan division data
        ksort($realDivisionsStats, SORT_NATURAL | SORT_FLAG_CASE);
        $deptNameLookup = [];
        foreach ($deptDefs as $dd) {
            $deptNameLookup[$dd['kode']] = $dd['departemen'];
        }
        $realDivisions = [];
        foreach ($realDivisionsStats as $divName => $rStat) {
            $dCodes = $rStat['dept_codes'];
            sort($dCodes, SORT_NATURAL | SORT_FLAG_CASE);
            $divTargetTotal = 0;
            $divS = $divA = $divB = $divC = $divK = 0;
            $deptItems = [];
            foreach ($dCodes as $dc) {
                $dcTarget = 0;
                $dcBound = 0;
                foreach ($tabelKuotaKj as $kRow) {
                    if ($kRow['departemen_kode'] === $dc) {
                        $dcBound += (int)($kRow['total_karyawan'] ?? 0);
                        $dcTarget += (int)($kRow['total_target'] ?? 0);
                        $divS += (int)($kRow['kuota_s'] ?? 0);
                        $divA += (int)($kRow['kuota_a'] ?? 0);
                        $divB += (int)($kRow['kuota_b'] ?? 0);
                        $divC += (int)($kRow['kuota_c'] ?? 0);
                        $divK += (int)($kRow['kuota_k'] ?? 0);
                    }
                }
                $divTargetTotal += $dcTarget;
                $dd = $rStat['dept_details'][$dc] ?? ['total_karyawan' => $dcBound, 'kena_target' => $dcBound, 'foreman' => 0, 'section_head' => 0, 'dept_head' => 0, 'leader' => 0];
                $divDeptBound = (int)($dd['kena_target'] ?? $dcBound);
                $deptItems[] = [
                    'kode' => $dc,
                    'nama' => $deptNameLookup[$dc] ?? $dc,
                    'total_karyawan' => (int)($dd['total_karyawan'] ?? $dcBound),
                    'kena_target' => $divDeptBound,
                    'target_total' => $dcTarget,
                    'foreman' => (int)($dd['foreman'] ?? 0),
                    'section_head' => (int)($dd['section_head'] ?? 0),
                    'dept_head' => (int)($dd['dept_head'] ?? 0),
                    'leader' => (int)($dd['leader'] ?? 0),
                    'is_complete' => ($dcBound > 0 && $dcTarget === $dcBound),
                ];
            }
            $realDivisions[] = [
                'nama' => $divName,
                'depts' => $deptItems,
                'dept_codes' => $dCodes,
                'first_dept' => $dCodes[0] ?? 'PROD 2',
                'total_karyawan' => $rStat['total_karyawan'],
                'kena_target' => $rStat['kena_target'],
                'target_total' => $divTargetTotal,
                's' => $divS,
                'a' => $divA,
                'b' => $divB,
                'c' => $divC,
                'k' => $divK,
                'foreman' => $rStat['foreman'],
                'section_head' => $rStat['section_head'],
                'dept_head' => $rStat['dept_head'],
                'div_head' => $rStat['div_head'],
                'leader' => $rStat['leader'],
                'is_complete' => ($rStat['kena_target'] > 0 && $divTargetTotal === $rStat['kena_target']),
            ];
        }

        // Top 5 summary cards metrics directly from real karyawan data
        $uniqueActiveDepts = [];
        $uniqueActiveSects = [];
        $deptEmpCount = 0;
        $sectEmpCount = 0;
        $foremanRoleCount = 0;
        $foremanSubCount = 0;
        foreach ($allKaryawan as $kItem) {
            $dRaw = strtoupper(trim((string)($kItem['departemen'] ?? '')));
            if ($dRaw !== '' && $dRaw !== '-') {
                $uniqueActiveDepts[$dRaw] = true;
                $deptEmpCount++;
            }
            $sRaw = strtoupper(trim((string)($kItem['nama_sect'] ?? ($kItem['seksi'] ?? ''))));
            if ($sRaw !== '' && $sRaw !== '-') {
                $uniqueActiveSects[$sRaw] = true;
                $sectEmpCount++;
            }
            $posRaw = strtoupper(trim((string)($kItem['jabatan'] ?? '')));
            if (str_contains($posRaw, 'FOREMAN')) {
                $foremanRoleCount++;
            }
            $nrpFm = trim((string)($kItem['nrp_foreman'] ?? ''));
            if ($nrpFm !== '' && $nrpFm !== '-') {
                $foremanSubCount++;
            }
        }
        $topSummaryStats = [
            'scope_label' => 'Seluruh Perusahaan',
            'total_karyawan' => count($allKaryawan),
            'kena_target' => $totalPerusahaanKaryawan,
            'bebas_target' => $totalExempt07006,
            'karyawan_subtitle' => "{$totalPerusahaanKaryawan} Kena Target • {$totalExempt07006} Bebas Target",
            'total_division' => count($realDivisions),
            'division_karyawan' => array_sum(array_column($realDivisions, 'total_karyawan')),
            'total_department' => count($uniqueActiveDepts),
            'department_karyawan' => $deptEmpCount,
            'total_section' => count($uniqueActiveSects),
            'section_karyawan' => $sectEmpCount,
            'total_foreman' => $foremanRoleCount,
            'foreman_subordinates' => $foremanSubCount,
        ];

        // Build interactive scope statistics for top cards (All, per-Division, per-Department)
        $computeScopeStats = function (array $emps, string $label, ?int $forceDiv = null, ?int $forceDept = null) use ($docsByNrp): array {
            $tot = count($emps);
            $kena = 0;
            $divs = [];
            $depts = [];
            $sects = [];
            $fms = 0;
            $fmSubs = 0;
            $deptEmpCount = 0;
            $sectEmpCount = 0;

            foreach ($emps as $e) {
                if (!PenilaianController::isQuotaExemptEmployee($e, $docsByNrp[$e['nrp'] ?? ''] ?? null)) {
                    $kena++;
                }
                $div = trim((string)($e['division'] ?? ''));
                if ($div !== '' && $div !== '-') {
                    $divs[$div] = true;
                }
                $d = strtoupper(trim((string)($e['departemen'] ?? '')));
                if ($d !== '' && $d !== '-') {
                    $depts[$d] = true;
                    $deptEmpCount++;
                }
                $s = strtoupper(trim((string)($e['nama_sect'] ?? ($e['seksi'] ?? ''))));
                if ($s !== '' && $s !== '-') {
                    $sects[$s] = true;
                    $sectEmpCount++;
                }
                if (str_contains(strtoupper((string)($e['jabatan'] ?? '')), 'FOREMAN')) {
                    $fms++;
                }
                $nrpFm = trim((string)($e['nrp_foreman'] ?? ''));
                if ($nrpFm !== '' && $nrpFm !== '-') {
                    $fmSubs++;
                }
            }

            $bebas = $tot - $kena;

            return [
                'scope_label' => $label,
                'total_karyawan' => $tot,
                'kena_target' => $kena,
                'bebas_target' => $bebas,
                'karyawan_subtitle' => "{$kena} Kena Target • {$bebas} Bebas Target",
                'total_division' => $forceDiv !== null ? $forceDiv : count($divs),
                'division_karyawan' => $tot,
                'total_department' => $forceDept !== null ? $forceDept : count($depts),
                'department_karyawan' => $deptEmpCount,
                'total_section' => count($sects),
                'section_karyawan' => $sectEmpCount,
                'total_foreman' => $fms,
                'foreman_subordinates' => $fmSubs,
            ];
        };

        $interactiveScopeStats = [
            'all' => $topSummaryStats,
        ];

        // Scopes for each division
        foreach ($realDivisionsStats as $divName => $rStat) {
            $divEmps = array_values(array_filter(
                $allKaryawan,
                fn ($k) => strcasecmp(trim((string)($k['division'] ?? '')), $divName) === 0
            ));
            $interactiveScopeStats['div_' . $divName] = $computeScopeStats($divEmps, 'Divisi ' . $divName, 1);
        }

        // Scopes for each department
        foreach ($deptDefs as $dDef) {
            $deptEmps = array_values(array_filter(
                $allKaryawan,
                fn ($k) => in_array(strtoupper(trim((string)($k['departemen'] ?? ''))), $dDef['match'], true) || strtoupper(trim((string)($k['departemen'] ?? ''))) === strtoupper($dDef['kode'])
            ));
            $interactiveScopeStats['dept_' . $dDef['kode']] = $computeScopeStats(
                $deptEmps,
                'Departemen ' . $dDef['kode'],
                count($deptEmps) > 0 ? 1 : 0,
                count($deptEmps) > 0 ? 1 : 0
            );
        }

        return view('admin.target_kuota', compact(
            'user',
            'globalHeadcount',
            'deptTargetsMap',
            'rules',
            'divisiDefs',
            'deptDefs',
            'realDivisions',
            'topSummaryStats',
            'interactiveScopeStats',
            'simulasiDivisi',
            'simulasiDepartemen',
            'tabelTargetKuota',
            'totalHierarki',
            'masterKjList',
            'displayKjList',
            'summaryKjCards',
            'tabelKuotaKj',
            'kjTargetsMap',
            'totalPerusahaanKaryawan',
            'totalExempt07006',
            'levelFormDefs',
            'levelFormSummaryCards',
            'levelTargetsMap',
            'tabelKuotaLevel',
            'initialLevelUnit',
            'initialLevelForm',
            'totalSimulation',
            'totalDivSimulation',
            'initialScope',
            'initialKjDept',
            'initialKj',
            'initialTableTab'
        ));
    }

    /**
     * Simpan Pengaturan Target & Kuota Nilai (Angka Bulat / Jumlah Orang)
     * Mendukung: Seluruh Perusahaan ('all'), Per Divisi (cth: 'DIV-E', 'DIV-MFG'), atau Per Departemen (cth: 'ENGINEERING', 'PROD 1')
     */
    public function simpanTargetKuota(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->isAdmin() && !$user->isSuperadmin()) {
            abort(403, 'Akses terbatas untuk Admin dan Superadmin.');
        }

        $scope = trim($request->input('scope', 'all'));
        $s = max(0, (int)$request->input('quota_s', 0));
        $a = max(0, (int)$request->input('quota_a', 0));
        $b = max(0, (int)$request->input('quota_b', 0));
        $c = max(0, (int)$request->input('quota_c', 0));
        $k = max(0, (int)$request->input('quota_k', 0));
        $totalInput = $s + $a + $b + $c + $k;

        $quotaData = ['S' => $s, 'A' => $a, 'B' => $b, 'C' => $c, 'K' => $k, 'total' => $totalInput];

        if ($scope === 'all' || empty($scope)) {
            DB::table('pengaturan')->updateOrInsert(
                ['key' => 'target_kuota_headcount'],
                [
                    'value' => json_encode($quotaData),
                    'updated_at' => now(),
                ]
            );

            // Also keep target_kuota_pct for compatibility
            $pctS = $totalInput > 0 ? round(($s / $totalInput) * 100) : 10;
            $pctA = $totalInput > 0 ? round(($a / $totalInput) * 100) : 30;
            $pctB = $totalInput > 0 ? round(($b / $totalInput) * 100) : 30;
            $pctC = $totalInput > 0 ? round(($c / $totalInput) * 100) : 20;
            $pctK = max(0, 100 - ($pctS + $pctA + $pctB + $pctC));
            $pctArr = ['S' => $pctS, 'A' => $pctA, 'B' => $pctB, 'C' => $pctC, 'K' => $pctK];
            DB::table('pengaturan')->updateOrInsert(
                ['key' => 'target_kuota_pct'],
                ['value' => json_encode($pctArr), 'updated_at' => now()]
            );

            // Sync kuota_departemen for departments without an explicit per-dept/div override so global % changes propagate cleanly
            $existingSettingsKeys = DB::table('pengaturan')->pluck('key')->all();
            $allKdRows = DB::table('kuota_departemen')->get()->groupBy('departemen');
            foreach ($allKdRows as $dName => $dRows) {
                $dUpper = strtoupper(trim((string)$dName));
                if (in_array('target_kuota_dept_' . $dUpper, $existingSettingsKeys, true)
                    || ($dUpper === 'ENGINEERING' && in_array('target_kuota_div_DIV-E', $existingSettingsKeys, true))) {
                    continue;
                }
                $dTotal = (int)$dRows->sum('total_karyawan');
                if ($dTotal <= 0) {
                    continue;
                }
                $desiredDept = PenilaianController::computeProportionalTargetCounts($dTotal, $pctArr);
                $rowAllocs = [];
                $colSums = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];
                foreach ($dRows as $rIdx => $dRow) {
                    $rTot = (int)$dRow->total_karyawan;
                    $rowAllocs[$rIdx] = PenilaianController::computeProportionalTargetCounts($rTot, $pctArr);
                    foreach (['S', 'A', 'B', 'C', 'K'] as $gk) {
                        $colSums[$gk] += $rowAllocs[$rIdx][$gk];
                    }
                }
                // Balance column totals so SUM(kuota_g across KJs) equals $desiredDept[g] while keeping each row's total_karyawan intact
                foreach (['S', 'A', 'B', 'C', 'K'] as $gk) {
                    $diff = ($desiredDept[$gk] ?? 0) - $colSums[$gk];
                    while ($diff > 0) {
                        $moved = false;
                        foreach ($rowAllocs as $rIdx => $alloc) {
                            foreach (['B', 'C', 'A', 'K', 'S'] as $donorG) {
                                if ($donorG !== $gk && $colSums[$donorG] > ($desiredDept[$donorG] ?? 0) && $rowAllocs[$rIdx][$donorG] > 0) {
                                    $rowAllocs[$rIdx][$gk]++;
                                    $rowAllocs[$rIdx][$donorG]--;
                                    $colSums[$gk]++;
                                    $colSums[$donorG]--;
                                    $diff--;
                                    $moved = true;
                                    break 2;
                                }
                            }
                        }
                        if (!$moved) {
                            break;
                        }
                    }
                }
                foreach ($dRows as $rIdx => $dRow) {
                    DB::table('kuota_departemen')->where('id', $dRow->id)->update([
                        'kuota_s' => $rowAllocs[$rIdx]['S'],
                        'kuota_a' => $rowAllocs[$rIdx]['A'],
                        'kuota_b' => $rowAllocs[$rIdx]['B'],
                        'kuota_c' => $rowAllocs[$rIdx]['C'],
                        'kuota_k' => $rowAllocs[$rIdx]['K'],
                        'updated_at' => now(),
                    ]);
                }
            }

            $msg = "Target kuota orang standar seluruh perusahaan (S: {$s} org, A: {$a} org, B: {$b} org, C: {$c} org, K: {$k} org - Total: {$totalInput} org) berhasil disimpan!";
        } elseif (str_starts_with($scope, 'DIV-')) {
            DB::table('pengaturan')->updateOrInsert(
                ['key' => 'target_kuota_div_' . $scope],
                [
                    'value' => json_encode($quotaData),
                    'updated_at' => now(),
                ]
            );

            // AUTO-LINK: Jika Division E diubah, maka Department di bawahnya (ENGINEERING) dan Section Head ikut targetnya
            if ($scope === 'DIV-E') {
                DB::table('pengaturan')->updateOrInsert(
                    ['key' => 'target_kuota_dept_ENGINEERING'],
                    [
                        'value' => json_encode($quotaData),
                        'updated_at' => now(),
                    ]
                );
                $this->syncDepartmentQuotaRows('ENGINEERING', $quotaData);
            }

            $divNames = [
                'DIV-E' => 'Division E (Technical & Engineering)',
                'DIV-MFG' => 'Manufacturing Division',
                'DIV-QC' => 'Quality Control Division',
                'DIV-GA' => 'General Affairs & Finance Division',
            ];
            $divLabel = $divNames[$scope] ?? $scope;

            $msg = "Target kuota khusus {$divLabel} (S: {$s} org, A: {$a} org, B: {$b} org, C: {$c} org, K: {$k} org - Total: {$totalInput} org) berhasil disimpan!";
        } else {
            DB::table('pengaturan')->updateOrInsert(
                ['key' => 'target_kuota_dept_' . $scope],
                [
                    'value' => json_encode($quotaData),
                    'updated_at' => now(),
                ]
            );

            $this->syncDepartmentQuotaRows(strtoupper($scope), $quotaData);

            // AUTO-LINK: Jika Department ENGINEERING diubah, maka Division E juga sinkron
            if (strtoupper($scope) === 'ENGINEERING') {
                DB::table('pengaturan')->updateOrInsert(
                    ['key' => 'target_kuota_div_DIV-E'],
                    [
                        'value' => json_encode($quotaData),
                        'updated_at' => now(),
                    ]
                );
            }

            $msg = "Target kuota orang khusus departemen '{$scope}' (S: {$s} org, A: {$a} org, B: {$b} org, C: {$c} org, K: {$k} org - Total: {$totalInput} org) berhasil disimpan!";
        }

        // Clear sheet repository cache so changes immediately propagate across Foreman, Section Head, Dept Head!
        $this->repository->clearCache();

        // Realtime sync ke Google Spreadsheet secara background non-blocking (< 5ms)
        try {
            GoogleSyncService::syncTablesAsync(['kuota_departemen', 'pengaturan', 'audit_log']);
        } catch (\Throwable $e) {}

        $redirectRoute = request()->is('superadmin*') ? 'superadmin.targetKuota' : 'admin.targetKuota';
        $tableTab = str_starts_with($scope, 'DIV-') ? 'divisi' : ($scope !== 'all' ? 'departemen' : 'all');
        return redirect()->route($redirectRoute, [
            'scope' => $scope,
            'table_tab' => $tableTab,
        ])->with('success', $msg)
          ->with('selected_scope', $scope)
          ->with('selected_table_tab', $tableTab);
    }

    /**
     * Rebalance existing kuota_departemen rows for a department so column sums match $desiredDept.
     */
    private function syncDepartmentQuotaRows(string $deptCode, array $desiredDept): void
    {
        $dRows = DB::table('kuota_departemen')->where('departemen', $deptCode)->orderBy('id')->get();
        if ($dRows->isEmpty()) {
            return;
        }

        $desired = [
            'S' => max(0, (int)($desiredDept['S'] ?? 0)),
            'A' => max(0, (int)($desiredDept['A'] ?? 0)),
            'B' => max(0, (int)($desiredDept['B'] ?? 0)),
            'C' => max(0, (int)($desiredDept['C'] ?? 0)),
            'K' => max(0, (int)($desiredDept['K'] ?? 0)),
        ];
        $desiredTotal = array_sum($desired);

        $weights = [];
        foreach ($dRows as $idx => $dRow) {
            $weights[$idx] = max(1, (int)$dRow->total_karyawan);
        }
        $weightSum = array_sum($weights);
        $rawExistingSum = (int)$dRows->sum('total_karyawan');

        $rowTargetTotals = [];
        if ($rawExistingSum > 0 && $rawExistingSum === $desiredTotal) {
            foreach ($dRows as $idx => $dRow) {
                $rowTargetTotals[$idx] = (int)$dRow->total_karyawan;
            }
        } else {
            $rowRems = [];
            foreach ($dRows as $idx => $dRow) {
                $exact = $weightSum > 0 ? ($desiredTotal * ($weights[$idx] / $weightSum)) : 0;
                $floorVal = (int)floor($exact);
                $rowTargetTotals[$idx] = $floorVal;
                $rowRems[$idx] = $exact - $floorVal;
            }
            $rowDiff = $desiredTotal - array_sum($rowTargetTotals);
            if ($rowDiff > 0) {
                arsort($rowRems);
                foreach (array_keys($rowRems) as $idx) {
                    if ($rowDiff <= 0) {
                        break;
                    }
                    $rowTargetTotals[$idx]++;
                    $rowDiff--;
                }
            }
        }

        $rowAllocs = [];
        foreach ($dRows as $idx => $dRow) {
            $rowAllocs[$idx] = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0];
        }

        foreach (['S', 'A', 'B', 'C', 'K'] as $gk) {
            $targetCol = $desired[$gk];
            if ($targetCol === 0) {
                continue;
            }
            $rems = [];
            $allocSum = 0;
            foreach ($dRows as $idx => $dRow) {
                $exact = $desiredTotal > 0 ? ($targetCol * ($rowTargetTotals[$idx] / $desiredTotal)) : 0;
                $floorVal = (int)floor($exact);
                $rowAllocs[$idx][$gk] = $floorVal;
                $allocSum += $floorVal;
                $rems[$idx] = $exact - $floorVal;
            }
            arsort($rems);
            $remLeft = $targetCol - $allocSum;
            foreach (array_keys($rems) as $idx) {
                if ($remLeft <= 0) {
                    break;
                }
                $rowAllocs[$idx][$gk]++;
                $remLeft--;
            }
        }

        // Balance row totals to match exact $rowTargetTotals while preserving grade column sums
        $maxSteps = ($desiredTotal * 2) + 20;
        for ($step = 0; $step < $maxSteps; $step++) {
            $highIdx = null;
            $lowIdx = null;
            foreach ($dRows as $idx => $dRow) {
                $rSum = array_sum($rowAllocs[$idx]);
                if ($highIdx === null && $rSum > $rowTargetTotals[$idx]) {
                    $highIdx = $idx;
                }
                if ($lowIdx === null && $rSum < $rowTargetTotals[$idx]) {
                    $lowIdx = $idx;
                }
            }
            if ($highIdx === null || $lowIdx === null) {
                break;
            }
            $moved = false;
            foreach (['C', 'B', 'K', 'A', 'S'] as $gk) {
                if ($rowAllocs[$highIdx][$gk] > 0) {
                    $rowAllocs[$highIdx][$gk]--;
                    $rowAllocs[$lowIdx][$gk]++;
                    $moved = true;
                    break;
                }
            }
            if (!$moved) {
                break;
            }
        }

        foreach ($dRows as $rIdx => $dRow) {
            DB::table('kuota_departemen')->where('id', $dRow->id)->update([
                'kuota_s' => $rowAllocs[$rIdx]['S'],
                'kuota_a' => $rowAllocs[$rIdx]['A'],
                'kuota_b' => $rowAllocs[$rIdx]['B'],
                'kuota_c' => $rowAllocs[$rIdx]['C'],
                'kuota_k' => $rowAllocs[$rIdx]['K'],
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Simpan Pengaturan Kuota per Jenjang KJ & Departemen
     */
    public function simpanTargetKuotaKj(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->isAdmin() && !$user->isSuperadmin()) {
            abort(403, 'Akses terbatas untuk Admin dan Superadmin.');
        }

        $departemen = trim($request->input('departemen', $request->input('target_departemen', $request->input('scope', 'PROD 2'))));

        if ($request->has('batch') && is_array($request->input('kj_rows'))) {
            $kj = 'KJ 2';
            foreach ($request->input('kj_rows') as $row) {
                $rKj = strtoupper(trim((string)($row['kj'] ?? '')));
                if ($rKj === '') continue;
                $rS = max(0, (int)($row['quota_s'] ?? 0));
                $rA = max(0, (int)($row['quota_a'] ?? 0));
                $rB = max(0, (int)($row['quota_b'] ?? 0));
                $rC = max(0, (int)($row['quota_c'] ?? 0));
                $rK = max(0, (int)($row['quota_k'] ?? 0));
                $rTot = $rS + $rA + $rB + $rC + $rK;

                DB::table('kuota_departemen')->updateOrInsert(
                    ['departemen' => $departemen, 'kj' => $rKj],
                    [
                        'kuota_s' => $rS,
                        'kuota_a' => $rA,
                        'kuota_b' => $rB,
                        'kuota_c' => $rC,
                        'kuota_k' => $rK,
                        'total_karyawan' => $rTot,
                        'updated_at' => now(),
                    ]
                );

                if ($departemen === 'ENGINEERING') {
                    DB::table('pengaturan')->updateOrInsert(
                        ['key' => 'target_kuota_kj_DIV-E_' . str_replace(' ', '', $rKj)],
                        ['value' => json_encode(['S' => $rS, 'A' => $rA, 'B' => $rB, 'C' => $rC, 'K' => $rK, 'total' => $rTot]), 'updated_at' => now()]
                    );
                }
            }
            $msg = "Seluruh target kuota jenjang KJ untuk Departemen '{$departemen}' berhasil disimpan!";
        } else {
            $kj = strtoupper(trim($request->input('kj', 'KJ 2')));
            $s = max(0, (int)$request->input('quota_s', $request->input('kuota_s', 0)));
            $a = max(0, (int)$request->input('quota_a', $request->input('kuota_a', 0)));
            $b = max(0, (int)$request->input('quota_b', $request->input('kuota_b', 0)));
            $c = max(0, (int)$request->input('quota_c', $request->input('kuota_c', 0)));
            $k = max(0, (int)$request->input('quota_k', $request->input('kuota_k', 0)));
            $totalInput = $s + $a + $b + $c + $k;

            if (str_starts_with($departemen, 'DIV-')) {
                $divKey = 'target_kuota_kj_' . $departemen . '_' . str_replace(' ', '', $kj);
                DB::table('pengaturan')->updateOrInsert(
                    ['key' => $divKey],
                    ['value' => json_encode(['S' => $s, 'A' => $a, 'B' => $b, 'C' => $c, 'K' => $k, 'total' => $totalInput]), 'updated_at' => now()]
                );

                // Auto-sync jika Division E diubah, maka department ENGINEERING ikut tersinkronisasi
                if ($departemen === 'DIV-E') {
                    DB::table('kuota_departemen')->updateOrInsert(
                        ['departemen' => 'ENGINEERING', 'kj' => $kj],
                        [
                            'kuota_s' => $s,
                            'kuota_a' => $a,
                            'kuota_b' => $b,
                            'kuota_c' => $c,
                            'kuota_k' => $k,
                            'total_karyawan' => $totalInput,
                            'updated_at' => now(),
                        ]
                    );
                }

                $msg = "Target kuota jenjang {$kj} khusus divisi '{$departemen}' (S: {$s}, A: {$a}, B: {$b}, C: {$c}, K: {$k}) berhasil disimpan!";
            } elseif ($departemen === 'all') {
                // Update global setting for this KJ
                DB::table('pengaturan')->updateOrInsert(
                    ['key' => 'target_kuota_kj_' . str_replace(' ', '', $kj)],
                    ['value' => json_encode(['S' => $s, 'A' => $a, 'B' => $b, 'C' => $c, 'K' => $k, 'total' => $totalInput]), 'updated_at' => now()]
                );
                $msg = "Target kuota standar untuk jenjang {$kj} (Seluruh Perusahaan) berhasil disimpan!";
            } else {
                DB::table('kuota_departemen')->updateOrInsert(
                    ['departemen' => $departemen, 'kj' => $kj],
                    [
                        'kuota_s' => $s,
                        'kuota_a' => $a,
                        'kuota_b' => $b,
                        'kuota_c' => $c,
                        'kuota_k' => $k,
                        'total_karyawan' => $totalInput,
                        'updated_at' => now(),
                    ]
                );

                $msg = "Target kuota jenjang {$kj} khusus departemen '{$departemen}' (S: {$s}, A: {$a}, B: {$b}, C: {$c}, K: {$k}) berhasil disimpan!";
            }
        }

        // AUTO-SYNC TARGET KUOTA INDUK (DIVISI & DEPARTEMEN) DENGAN PENJUMLAHAN SELURUH KJ OPERASIONAL
        // Menjaga agar Target Kuota Induk selalu berhubungan dan otomatis bertambah/berubah sesuai jumlah seluruh KJ (non-07-006)
        $masterKjSetting = DB::table('pengaturan')->where('key', 'master_kj_list')->first();
        $allKjList = $masterKjSetting ? (json_decode($masterKjSetting->value, true) ?: []) : [
            ['kode' => 'KJ 2'], ['kode' => 'KJ 3'], ['kode' => 'KJ 4'], ['kode' => 'KJ 5'],
        ];
        $knownKjNorms = array_map(fn ($x) => ExcelKaryawanReader::normalizeKj($x['kode'] ?? ''), $allKjList);
        $kjNormSubmitted = ExcelKaryawanReader::normalizeKj($kj);
        if (!in_array($kjNormSubmitted, $knownKjNorms, true)) {
            $allKjList[] = ['kode' => $kjNormSubmitted];
            $knownKjNorms[] = $kjNormSubmitted;
        }
        $allKaryawanAll = $this->repository->all('karyawan');
        foreach ($allKaryawanAll as $kItem) {
            if (PenilaianController::isQuotaExemptEmployee($kItem)) {
                continue;
            }
            $opKj = ExcelKaryawanReader::normalizeQuotaKj($kItem['kj'] ?? '');
            if ($opKj !== '' && !in_array($opKj, $knownKjNorms, true)) {
                $allKjList[] = ['kode' => $opKj];
                $knownKjNorms[] = $opKj;
            }
        }

        $totS = 0; $totA = 0; $totB = 0; $totC = 0; $totK = 0;

        if (str_starts_with($departemen, 'DIV-')) {
            foreach ($allKjList as $mKj) {
                $mkCode = strtoupper(trim($mKj['kode']));
                $sanitized = str_replace(' ', '', $mkCode);
                $divKjSetting = DB::table('pengaturan')->where('key', 'target_kuota_kj_' . $departemen . '_' . $sanitized)->first();
                if ($divKjSetting && !empty($divKjSetting->value)) {
                    $dVal = json_decode($divKjSetting->value, true);
                    $totS += (int)($dVal['S'] ?? 0);
                    $totA += (int)($dVal['A'] ?? 0);
                    $totB += (int)($dVal['B'] ?? 0);
                    $totC += (int)($dVal['C'] ?? 0);
                    $totK += (int)($dVal['K'] ?? 0);
                }
            }
            $parentTotal = $totS + $totA + $totB + $totC + $totK;
            $parentData = ['S' => $totS, 'A' => $totA, 'B' => $totB, 'C' => $totC, 'K' => $totK, 'total' => $parentTotal];
            DB::table('pengaturan')->updateOrInsert(
                ['key' => 'target_kuota_div_' . $departemen],
                ['value' => json_encode($parentData), 'updated_at' => now()]
            );
        } elseif ($departemen !== 'all') {
            $allKdDept = DB::table('kuota_departemen')->where('departemen', $departemen)->get()->keyBy(function($item) {
                return strtoupper(trim($item->kj));
            });
            foreach ($allKjList as $mKj) {
                $mkCode = strtoupper(trim($mKj['kode']));
                if (isset($allKdDept[$mkCode])) {
                    $totS += (int)$allKdDept[$mkCode]->kuota_s;
                    $totA += (int)$allKdDept[$mkCode]->kuota_a;
                    $totB += (int)$allKdDept[$mkCode]->kuota_b;
                    $totC += (int)$allKdDept[$mkCode]->kuota_c;
                    $totK += (int)$allKdDept[$mkCode]->kuota_k;
                }
            }
            $parentTotal = $totS + $totA + $totB + $totC + $totK;
            $parentData = ['S' => $totS, 'A' => $totA, 'B' => $totB, 'C' => $totC, 'K' => $totK, 'total' => $parentTotal];
            DB::table('pengaturan')->updateOrInsert(
                ['key' => 'target_kuota_dept_' . $departemen],
                ['value' => json_encode($parentData), 'updated_at' => now()]
            );
        }

        // Clear sheet repository cache so changes immediately propagate across Foreman, Section Head, Dept Head!
        $this->repository->clearCache();

        // Realtime sync ke Google Spreadsheet secara background non-blocking (< 5ms)
        try {
            GoogleSyncService::syncTablesAsync(['kuota_departemen', 'pengaturan', 'audit_log']);
        } catch (\Throwable $e) {}

        $redirectRoute = request()->is('superadmin*') ? 'superadmin.targetKuota' : 'admin.targetKuota';
        return redirect()->route($redirectRoute, [
            'tab' => 'kj',
            'dept' => $departemen,
            'kj' => $kj,
        ])->with('success', $msg)
          ->with('selected_kj_dept', $departemen)
          ->with('selected_kj', $kj);
    }

    /**
     * Simpan Pengaturan Target Kuota per Level Form (07-001 KJ 1-3, 07-002 KJ 4-5, 07-003 Leader, 07-004 Foreman).
     * Form 07-006 (Section Head / Dept Head / Div Head) dikecualikan (Bebas Target).
     */
    public function simpanTargetLevel(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->isAdmin() && !$user->isSuperadmin()) {
            abort(403, 'Akses terbatas untuk Admin dan Superadmin.');
        }

        $unit = trim((string)$request->input('level_unit', 'all'));
        $levelForm = strtoupper(trim((string)$request->input('level_form', 'KJ13')));
        if ($levelForm === 'EXEMPT_07006' || str_starts_with($levelForm, '07-006')) {
            return redirect()->back()->with('error', 'Form 07-006 (Section Head / Dept Head / Division Head) bersifat Bebas Target dan tidak dikenakan batas kuota.');
        }

        $allowedLevels = ['KJ13', 'KJ45', 'LEADER', 'FOREMAN'];
        if (!in_array($levelForm, $allowedLevels, true)) {
            $levelForm = 'KJ13';
        }

        $s = max(0, (int)$request->input('quota_s', 0));
        $a = max(0, (int)$request->input('quota_a', 0));
        $b = max(0, (int)$request->input('quota_b', 0));
        $c = max(0, (int)$request->input('quota_c', 0));
        $k = max(0, (int)$request->input('quota_k', 0));
        $totalInput = $s + $a + $b + $c + $k;

        $levelData = ['S' => $s, 'A' => $a, 'B' => $b, 'C' => $c, 'K' => $k, 'total' => $totalInput];
        DB::table('pengaturan')->updateOrInsert(
            ['key' => 'target_kuota_level_' . $unit . '_' . $levelForm],
            ['value' => json_encode($levelData), 'updated_at' => now()]
        );

        // Optional sync to unit quota target (summing the 4 quota-bound non-07-006 form levels)
        if ($request->boolean('sync_to_unit', true)) {
            $allKaryawan = $this->repository->all('karyawan');
            $allDocs = $this->repository->all('dokumen');
            $docsByNrp = [];
            foreach ($allDocs as $d) {
                if (!empty($d['nrp'])) {
                    $docsByNrp[$d['nrp']] = $d;
                }
            }
            $deptMatchMap = [
                'DIV-E' => ['ENGINEERING', 'ENG'],
                'DIV-MFG' => ['PROD 1', 'PRODUCTION 1', 'PROD 2', 'PRODUCTION 2', 'MACHINING'],
                'DIV-QC' => ['QA', 'QUALITY CONTROL', 'QUALITY ASSURANCE (QA)', 'QC'],
                'DIV-GA' => ['HR & IR DEPT.', 'HR', 'GA', 'GENERAL AFFAIRS', 'GA DEPT.'],
                'ENGINEERING' => ['ENGINEERING', 'ENG'],
                'PROD 1' => ['PROD 1', 'PRODUCTION 1'],
                'PROD 2' => ['PROD 2', 'PRODUCTION 2'],
                'MACHINING' => ['MACHINING'],
                'QA/QC' => ['QA', 'QUALITY CONTROL', 'QUALITY ASSURANCE (QA)', 'QC'],
                'HR & GA' => ['HR & IR DEPT.', 'HR', 'GA', 'GENERAL AFFAIRS', 'GA DEPT.'],
            ];

            $sumS = $sumA = $sumB = $sumC = $sumK = 0;
            foreach ($allowedLevels as $lvl) {
                $saved = DB::table('pengaturan')->where('key', 'target_kuota_level_' . $unit . '_' . $lvl)->value('value');
                $decoded = $saved ? json_decode($saved, true) : null;
                if ($decoded && isset($decoded['S'])) {
                    $sumS += (int)($decoded['S'] ?? 0);
                    $sumA += (int)($decoded['A'] ?? 0);
                    $sumB += (int)($decoded['B'] ?? 0);
                    $sumC += (int)($decoded['C'] ?? 0);
                    $sumK += (int)($decoded['K'] ?? 0);
                } else {
                    $cnt = count(array_filter($allKaryawan, function ($emp) use ($unit, $lvl, $docsByNrp, $deptMatchMap) {
                        $doc = $docsByNrp[$emp['nrp'] ?? ''] ?? null;
                        if (PenilaianController::isQuotaExemptEmployee($emp, $doc)) {
                            return false;
                        }
                        if ($unit !== 'all' && isset($deptMatchMap[$unit])) {
                            if (!in_array(strtoupper($emp['departemen'] ?? ''), $deptMatchMap[$unit], true)) {
                                return false;
                            }
                        }
                        $kf = !empty($doc['kode_form']) ? strtoupper(trim((string)$doc['kode_form'])) : PenilaianController::determineKodeForm($emp);
                        $mapped = match ($kf) {
                            '07-004' => 'FOREMAN',
                            '07-003' => 'LEADER',
                            '07-002' => 'KJ45',
                            default => 'KJ13',
                        };
                        return $mapped === $lvl;
                    }));
                    if ($cnt > 0) {
                        $lS = max(0, (int)round($cnt * 0.10));
                        $lA = max(0, (int)round($cnt * 0.30));
                        $lB = max(0, (int)round($cnt * 0.30));
                        $lC = max(0, (int)round($cnt * 0.20));
                        $lK = max(0, $cnt - ($lS + $lA + $lB + $lC));
                        $sumS += $lS; $sumA += $lA; $sumB += $lB; $sumC += $lC; $sumK += $lK;
                    }
                }
            }
            $unitTotal = $sumS + $sumA + $sumB + $sumC + $sumK;
            $unitArr = ['S' => $sumS, 'A' => $sumA, 'B' => $sumB, 'C' => $sumC, 'K' => $sumK, 'total' => $unitTotal];
            $unitPayload = json_encode($unitArr);
            if ($unit === 'all') {
                DB::table('pengaturan')->updateOrInsert(['key' => 'target_kuota_headcount'], ['value' => $unitPayload, 'updated_at' => now()]);
            } elseif (str_starts_with($unit, 'DIV-')) {
                DB::table('pengaturan')->updateOrInsert(['key' => 'target_kuota_div_' . $unit], ['value' => $unitPayload, 'updated_at' => now()]);
            } else {
                DB::table('pengaturan')->updateOrInsert(['key' => 'target_kuota_dept_' . $unit], ['value' => $unitPayload, 'updated_at' => now()]);
                $this->syncDepartmentQuotaRows(strtoupper($unit), $unitArr);
            }
        }

        $formCodeLabel = match ($levelForm) {
            'KJ13' => '07-001 (KJ 1-3)',
            'KJ45' => '07-002 (KJ 4-5 Non Jabatan)',
            'LEADER' => '07-003 (Leader)',
            'FOREMAN' => '07-004 (Foreman)',
            default => $levelForm,
        };
        $msg = "Target kuota Level {$formCodeLabel} untuk unit '{$unit}' (S: {$s}, A: {$a}, B: {$b}, C: {$c}, K: {$k} - Total: {$totalInput} org) berhasil disimpan!";
        // Clear sheet repository cache so changes immediately propagate across Foreman, Section Head, Dept Head!
        $this->repository->clearCache();

        // Realtime sync ke Google Spreadsheet secara background non-blocking (< 5ms)
        try {
            GoogleSyncService::syncTablesAsync(['kuota_departemen', 'pengaturan', 'audit_log']);
        } catch (\Throwable $e) {}

        $redirectRoute = request()->is('superadmin*') ? 'superadmin.targetKuota' : 'admin.targetKuota';
        return redirect()->route($redirectRoute, [
            'tab' => 'level',
            'level_unit' => $unit,
            'level_form' => $levelForm,
        ])->with('success', $msg)
          ->with('selected_level_unit', $unit)
          ->with('selected_level_form', $levelForm);
    }

    /**
     * Tambah Master Jenjang KJ Baru (cth: KJ 6, KJ 7, dst.)
     */
    public function tambahMasterKj(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->isAdmin() && !$user->isSuperadmin()) {
            abort(403, 'Akses terbatas untuk Admin dan Superadmin.');
        }

        $kodeKj = strtoupper(trim($request->input('kode_kj', $request->input('kode', ''))));
        $golongan = strtoupper(trim($request->input('golongan', '')));
        $deskripsi = trim($request->input('deskripsi', ''));

        if (empty($kodeKj)) {
            return redirect()->back()->with('error', 'Kode KJ tidak boleh kosong (contoh: KJ 6).');
        }

        $masterKjSetting = DB::table('pengaturan')->where('key', 'master_kj_list')->first();
        $list = $masterKjSetting ? (json_decode($masterKjSetting->value, true) ?: []) : [
            ['kode' => 'KJ 2', 'golongan' => 'GOLONGAN II', 'deskripsi' => 'Operator / Staff (Form 07-001 • KJ 1-3)', 'is_default' => true],
            ['kode' => 'KJ 3', 'golongan' => 'GOLONGAN III', 'deskripsi' => 'Operator / Staff / Leader (Form 07-001 / 07-003)', 'is_default' => true],
            ['kode' => 'KJ 4', 'golongan' => 'GOLONGAN IV', 'deskripsi' => 'Leader / KJ 4-5 Non-Jabatan / Foreman (Form 07-003 / 07-002 / 07-004)', 'is_default' => true],
            ['kode' => 'KJ 5', 'golongan' => 'GOLONGAN V', 'deskripsi' => 'Foreman / KJ 4-5 Non-Jabatan (Form 07-004 / 07-002 • Bebas 07-006)', 'is_default' => true],
        ];

        // Check if already exists
        foreach ($list as $item) {
            if ($item['kode'] === $kodeKj) {
                return redirect()->back()->with('error', "Jenjang '{$kodeKj}' sudah terdaftar di sistem.");
            }
        }

        $list[] = [
            'kode' => $kodeKj,
            'golongan' => $golongan ?: 'GOLONGAN ' . $kodeKj,
            'deskripsi' => $deskripsi ?: 'Jenjang ' . $kodeKj,
            'is_default' => false,
        ];

        DB::table('pengaturan')->updateOrInsert(
            ['key' => 'master_kj_list'],
            ['value' => json_encode(array_values($list)), 'updated_at' => now()]
        );

        // Pre-populate kuota_departemen only for departments present in Data Karyawan
        $depts = DB::table('karyawan')->whereNotNull('departemen')->where('departemen', '!=', '-')->pluck('departemen')->unique()->values()->toArray();
        foreach ($depts as $d) {
            DB::table('kuota_departemen')->updateOrInsert(
                ['departemen' => $d, 'kj' => $kodeKj],
                ['kuota_s' => 0, 'kuota_a' => 0, 'kuota_b' => 0, 'kuota_c' => 0, 'kuota_k' => 0, 'total_karyawan' => 0, 'updated_at' => now()]
            );
        }

        // Clear sheet repository cache so changes immediately propagate across Foreman, Section Head, Dept Head!
        $this->repository->clearCache();

        // Realtime sync ke Google Spreadsheet secara background non-blocking (< 5ms)
        try {
            GoogleSyncService::syncTablesAsync(['kuota_departemen', 'pengaturan', 'audit_log']);
        } catch (\Throwable $e) {}

        $redirectRoute = request()->is('superadmin*') ? 'superadmin.targetKuota' : 'admin.targetKuota';
        return redirect()->route($redirectRoute, ['tab' => 'kj'])->with('success', "Jenjang baru '{$kodeKj}' ({$golongan}) berhasil ditambahkan ke dalam sistem!");
    }

    /**
     * Hapus Master Jenjang KJ Kustom
     */
    public function hapusMasterKj(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->isAdmin() && !$user->isSuperadmin()) {
            abort(403, 'Akses terbatas untuk Admin dan Superadmin.');
        }

        $kodeKj = strtoupper(trim($request->input('kode_kj', $request->input('kode', ''))));
        if (empty($kodeKj)) {
            return redirect()->back()->with('error', 'Kode KJ tidak valid.');
        }

        $masterKjSetting = DB::table('pengaturan')->where('key', 'master_kj_list')->first();
        $list = $masterKjSetting ? (json_decode($masterKjSetting->value, true) ?: []) : [];

        $newList = [];
        $deleted = false;
        foreach ($list as $item) {
            if ($item['kode'] === $kodeKj) {
                if (!empty($item['is_default'])) {
                    return redirect()->back()->with('error', "Jenjang standar '{$kodeKj}' tidak dapat dihapus karena digunakan oleh sistem operasional.");
                }
                $deleted = true;
                continue;
            }
            $newList[] = $item;
        }

        if ($deleted) {
            DB::table('pengaturan')->updateOrInsert(
                ['key' => 'master_kj_list'],
                ['value' => json_encode(array_values($newList)), 'updated_at' => now()]
            );
            DB::table('kuota_departemen')->where('kj', $kodeKj)->delete();
            $msg = "Jenjang '{$kodeKj}' dan data kuota terkait berhasil dihapus.";
        } else {
            $msg = "Jenjang '{$kodeKj}' tidak ditemukan.";
        }

        // Clear sheet repository cache so changes immediately propagate across Foreman, Section Head, Dept Head!
        $this->repository->clearCache();

        // Realtime sync ke Google Spreadsheet secara background non-blocking (< 5ms)
        try {
            GoogleSyncService::syncTablesAsync(['kuota_departemen', 'pengaturan', 'audit_log']);
        } catch (\Throwable $e) {}

        $redirectRoute = request()->is('superadmin*') ? 'superadmin.targetKuota' : 'admin.targetKuota';
        return redirect()->route($redirectRoute, ['tab' => 'kj'])->with('success', $msg);
    }

    public function pengaturanForm(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->isAdmin() && !$user->isSuperadmin()) {
            abort(403, 'Akses terbatas untuk Admin dan Superadmin.');
        }

        // Fetch settings from pengaturan table
        $allSettings = DB::table('pengaturan')->pluck('value', 'key')->toArray();

        // Fetch active period
        $activePeriod = DB::table('periode')->where('id', 1)->first() 
            ?? DB::table('periode')->where('status', 'aktif')->first();

        $formSettings = [
            'tahun_penilaian' => $allSettings['tahun_penilaian'] ?? ($activePeriod->tahun ?? 2025),
            'periode_penilaian' => $allSettings['periode_penilaian'] ?? 'Jan - Des',
            'judul_form_pk' => $allSettings['judul_form_pk'] ?? 'PENILAIAN KARYA',
            'nama_perusahaan' => $allSettings['nama_perusahaan'] ?? 'PT MUSASHI AUTO PARTS INDONESIA',
            'catatan_petunjuk_penilai' => $allSettings['catatan_petunjuk_penilai'] ?? 'Pilih salah satu dari 5 pilihan kalimat kriteria di bawah ini yang paling sesuai.',
            'status_periode' => $allSettings['status_periode'] ?? ($activePeriod->status ?? 'aktif'),
            'tgl_mulai' => $allSettings['tgl_mulai'] ?? ($activePeriod->tgl_mulai ?? '2025-01-01'),
            'tgl_selesai' => $allSettings['tgl_selesai'] ?? ($activePeriod->tgl_selesai ?? '2025-12-31'),
            'label_kerahasiaan' => $allSettings['label_kerahasiaan'] ?? 'RAHASIA',
            // Catatan & Petunjuk Bawah Formulir
            'catatan_1' => $allSettings['catatan_1'] ?? 'Jika nilai pecahan, ditentukan dua angka dibelakang koma.',
            'catatan_2' => $allSettings['catatan_2'] ?? 'Pembulatan : ≤ 0,5 pembulatan ke bawah ; > 0,5 pembulatan ke atas.',
            // Teks Indikator Potensi & Kolom Karyawan
            'judul_evaluasi_potensi' => $allSettings['judul_evaluasi_potensi'] ?? 'INDIKATOR EVALUASI POTENSI KARYAWAN',
            'petunjuk_evaluasi_potensi' => $allSettings['petunjuk_evaluasi_potensi'] ?? 'Setiap Atasan diharapkan mengisi dengan seksama kolom ini untuk mengetahui seberapa jauh potensi karyawan dapat dikembangkan ke posisi & tanggung jawab yang lebih besar sesuai dengan ketentuan yang berlaku dan selagi ada kesempatan dalam struktur organisasi.',
            'opsi_potensi_1' => $allSettings['opsi_potensi_1'] ?? 'Memenuhi standard untuk dipromosikan pada level diatasnya, saran pada posisi :',
            'opsi_potensi_2' => $allSettings['opsi_potensi_2'] ?? 'Sesuai dengan posisi saat ini dan masih bisa dikembangkan dengan pengalaman dan pelatihan sesuai matriks.',
            'opsi_potensi_3' => $allSettings['opsi_potensi_3'] ?? 'Sesuai untuk posisi saat ini dan tidak bisa dikembangkan lagi, alasan :',
            'opsi_potensi_4' => $allSettings['opsi_potensi_4'] ?? 'Yang bersangkutan kurang sesuai dengan posisi saat ini, dengan alasan :',
            'judul_kelebihan' => $allSettings['judul_kelebihan'] ?? 'KELEBIHAN KARYAWAN',
            'judul_kekurangan' => $allSettings['judul_kekurangan'] ?? 'KEKURANGAN KARYAWAN',
        ];

        // Sample documents mapping for instant link & PDF download testing
        $sampleDocMap = [
            '07-001' => DB::table('dokumen')->where('kode_form', '07-001')->value('id') ?? 9,
            '07-002' => DB::table('dokumen')->where('kode_form', '07-002')->value('id') ?? 7,
            '07-003' => DB::table('dokumen')->where('kode_form', '07-003')->value('id') ?? 10,
            '07-004' => DB::table('dokumen')->where('kode_form', '07-004')->value('id') ?? 11,
            '07-006-SH' => DB::table('dokumen')->where('kode_form', '07-006-SH')->value('id') ?? 19,
            '07-006-DH' => DB::table('dokumen')->where('kode_form', '07-006-DH')->value('id') ?? 8,
            '07-006-DIV' => DB::table('dokumen')->where('kode_form', '07-006-DIV')->value('id') ?? 182,
        ];

        $daftarForm = [
            [
                'kode' => '07-001',
                'nama' => 'Formulir Penilaian Operator (KJ 1 - 3)',
                'nama_jp' => 'オペレーター評価表 (KJ 1-3)',
                'level' => '1 - 3',
                'sasaran' => 'Operator Machining, Operator Finishing, Teknisi Maintenance (KJ 1-3)',
                'penilai_1' => 'Foreman (Penilai Langsung)',
                'penilai_2' => 'Section Head (Reviewer)',
                'pengesahan' => 'Dept Head (Pengesahan)',
                'bobot' => 'Hasil Kerja (60%), Proses Kerja (20%), Sikap Kerja (20%)',
                'format' => '07-001.xlsx',
                'is_bilingual' => false,
                'sample_doc_id' => $sampleDocMap['07-001'] ?? 9,
                'sample_karyawan' => [
                    'nama' => 'NUR EKA PURNAMA',
                    'nrp' => '39',
                    'golongan' => 'IIIB',
                    'jabatan' => 'CONTROLLER',
                    'tgl_masuk' => '1996-05-08',
                    'seksi' => 'FACTORY 1',
                    'departemen' => 'PROD 2',
                    'banner' => 'KJ : 1 - 3',
                ],
                'ttd_config' => [
                    'is_single_ttd' => false,
                    'col1' => ['header' => 'Foreman', 'sub' => '', 'role' => 'Foreman', 'name' => 'SARYANTA', 'is_blocked' => false],
                    'col2' => ['header' => 'Section Head', 'sub' => '', 'role' => 'Section Head', 'name' => 'KRISTIYANTO', 'is_blocked' => false],
                    'col3' => ['header' => 'Department Head', 'sub' => '', 'role' => 'Dept. Head', 'name' => 'SUPARDI', 'is_blocked' => false],
                    'penilai_1_col' => 'Foreman (jika ada)',
                    'penilai_2_col' => 'Section Head / Deputy Section Head',
                    'penilai_3_col' => 'Dept. Head (Final)',
                    'col1_type' => 'dash',
                    'col2_type' => 'dash',
                    'col3_type' => 'dash',
                ],
            ],
            [
                'kode' => '07-002',
                'nama' => 'Formulir Penilaian Operator (KJ 4 - 5)',
                'nama_jp' => '上級オペレーター評価表 (KJ 4-5)',
                'level' => '4 - 5 non jabatan',
                'sasaran' => 'Staff Machining, Sub-Foreman Machining & Finishing (KJ 4-5)',
                'penilai_1' => 'Foreman (Penilai Langsung)',
                'penilai_2' => 'Section Head (Reviewer)',
                'pengesahan' => 'Dept Head (Pengesahan)',
                'bobot' => 'Hasil Kerja (60%), Proses Kerja (20%), Sikap Kerja (20%)',
                'format' => '07-002.xlsx',
                'is_bilingual' => false,
                'sample_doc_id' => $sampleDocMap['07-002'] ?? 7,
                'sample_karyawan' => [
                    'nama' => 'HADI PRIYONO',
                    'nrp' => '24',
                    'golongan' => 'IIID',
                    'jabatan' => 'OPERATOR',
                    'tgl_masuk' => '1996-05-08',
                    'seksi' => 'FACTORY 1',
                    'departemen' => 'PROD 2',
                    'banner' => 'KJ : 4 - 5 non jabatan',
                ],
                'ttd_config' => [
                    'is_single_ttd' => false,
                    'col1' => ['header' => 'Foreman', 'sub' => '', 'role' => 'Foreman', 'name' => 'SARYANTA', 'is_blocked' => false],
                    'col2' => ['header' => 'Section Head', 'sub' => '', 'role' => 'Section Head', 'name' => 'KRISTIYANTO', 'is_blocked' => false],
                    'col3' => ['header' => 'Department Head', 'sub' => '', 'role' => 'Dept. Head', 'name' => 'SUPARDI', 'is_blocked' => false],
                    'penilai_1_col' => 'Foreman (jika ada)',
                    'penilai_2_col' => 'Section Head / Deputy Section Head',
                    'penilai_3_col' => 'Dept. Head (Final)',
                    'col1_type' => 'dash',
                    'col2_type' => 'dash',
                    'col3_type' => 'dash',
                ],
            ],
            [
                'kode' => '07-003',
                'nama' => 'Formulir Penilaian Leader / Staff',
                'nama_jp' => 'リーダー・スタッフ評価表',
                'level' => 'LEADER',
                'sasaran' => 'Leader Machining, Leader Finishing, Staff Engineer E',
                'penilai_1' => 'Foreman (jika ada) / Section Head',
                'penilai_2' => 'Section Head (Reviewer)',
                'pengesahan' => 'Dept Head (Pengesahan)',
                'bobot' => 'Hasil Kerja (40%), Proses Kerja (35%), Sikap Kerja (25%)',
                'format' => '07-003.xlsx',
                'is_bilingual' => false,
                'sample_doc_id' => $sampleDocMap['07-003'] ?? 10,
                'sample_karyawan' => [
                    'nama' => 'WIDY NUGROHO',
                    'nrp' => '40',
                    'golongan' => 'IIID',
                    'jabatan' => 'LEADER',
                    'tgl_masuk' => '1996-05-08',
                    'seksi' => 'FACTORY 2',
                    'departemen' => 'PROD 2',
                    'banner' => 'LEADER',
                ],
                'ttd_config' => [
                    'is_single_ttd' => false,
                    'col1' => ['header' => 'Foreman', 'sub' => '(jika ada)', 'role' => 'Foreman', 'name' => 'PURWANTO', 'is_blocked' => false],
                    'col2' => ['header' => 'Section Head', 'sub' => '', 'role' => 'Section Head', 'name' => 'MURYADI', 'is_blocked' => false],
                    'col3' => ['header' => 'Department Head', 'sub' => '', 'role' => 'Dept. Head', 'name' => 'SUPARDI', 'is_blocked' => false],
                    'penilai_1_col' => 'Foreman (jika ada)',
                    'penilai_2_col' => 'Section Head / Deputy Section Head',
                    'penilai_3_col' => 'Dept. Head (Final)',
                    'col1_type' => 'dash',
                    'col2_type' => 'dash',
                    'col3_type' => 'dash',
                ],
            ],
            [
                'kode' => '07-004',
                'nama' => 'Formulir Penilaian Foreman',
                'nama_jp' => '職長 (フォーマン) 評価表',
                'level' => 'FOREMAN',
                'sasaran' => 'Foreman Produksi, Foreman Engineering',
                'penilai_1' => 'Foreman Mandiri (Self Appraisal)',
                'penilai_2' => 'Section Head (Reviewer)',
                'pengesahan' => 'Dept Head (Pengesahan)',
                'bobot' => 'Hasil Kerja (40%), Proses (30%), Sikap (15%), Man Mgt (15%)',
                'format' => '07-004.xlsx',
                'is_bilingual' => false,
                'sample_doc_id' => $sampleDocMap['07-004'] ?? 11,
                'sample_karyawan' => [
                    'nama' => 'PURWANTO',
                    'nrp' => '51',
                    'golongan' => 'IVA',
                    'jabatan' => 'FOREMAN',
                    'tgl_masuk' => '1996-07-15',
                    'seksi' => 'FACTORY 2',
                    'departemen' => 'PROD 2',
                    'banner' => 'FOREMAN',
                ],
                'ttd_config' => [
                    'is_single_ttd' => false,
                    'col1' => ['header' => 'Foreman', 'sub' => '(Mandiri)', 'role' => 'Foreman', 'name' => 'PURWANTO', 'is_blocked' => false],
                    'col2' => ['header' => 'Section Head', 'sub' => '', 'role' => 'Section Head', 'name' => 'MURYADI', 'is_blocked' => false],
                    'col3' => ['header' => 'Department Head', 'sub' => '', 'role' => 'Dept. Head', 'name' => 'SUPARDI', 'is_blocked' => false],
                    'penilai_1_col' => 'Mandiri',
                    'penilai_2_col' => 'Section Head / Deputy Section Head',
                    'penilai_3_col' => 'Dept. Head (Final)',
                    'col1_type' => 'dash',
                    'col2_type' => 'dash',
                    'col3_type' => 'dash',
                ],
            ],
            [
                'kode' => '07-006-SH',
                'nama' => 'Formulir Penilaian Section Head',
                'nama_jp' => 'セクションヘッド評価表 (07-006)',
                'level' => 'SECTION HEAD',
                'sasaran' => 'Section Head Produksi, Section Head Engineering',
                'penilai_1' => 'Section Head Mandiri (Self Appraisal)',
                'penilai_2' => 'Dept Head (Atasan Langsung)',
                'pengesahan' => 'Division Head (Penilai Final)',
                'bobot' => 'Hasil Kerja (55%), Proses (15%), Man Mgt (15%), Sikap (15%)',
                'format' => '07-006-SH.xlsx',
                'is_bilingual' => true,
                'sample_doc_id' => $sampleDocMap['07-006-SH'] ?? 19,
                'sample_karyawan' => [
                    'nama' => 'KRISTIYANTO',
                    'nrp' => '112',
                    'golongan' => 'IVA',
                    'jabatan' => 'SECTION HEAD',
                    'tgl_masuk' => '1996-09-16',
                    'seksi' => 'FACTORY 1',
                    'departemen' => 'PROD 2',
                    'banner' => 'SECTION HEAD / セクションヘッド',
                ],
                'ttd_config' => [
                    'is_single_ttd' => false,
                    'col1' => ['header' => 'Kary. Ybs', 'sub' => '個人的', 'role' => 'Section Head', 'name' => 'KRISTIYANTO', 'is_blocked' => false],
                    'col2' => ['header' => 'Atasan Langsung', 'sub' => '直属の上司', 'role' => 'Dept. Head', 'name' => 'SUPARDI', 'is_blocked' => false],
                    'col3' => ['header' => 'Penilai Final', 'sub' => '最終審査員', 'role' => 'Div. Head', 'name' => 'ATASAN PROD A', 'is_blocked' => false],
                    'penilai_1_col' => 'Mandiri (自分)',
                    'penilai_2_col' => 'Dept. Head',
                    'penilai_3_col' => 'Div. Head',
                    'col1_type' => 'button',
                    'col2_type' => 'dash',
                    'col3_type' => 'dash',
                ],
            ],
            [
                'kode' => '07-006-DH',
                'nama' => 'Formulir Penilaian Department Head',
                'nama_jp' => 'デパートメントヘッド評価表 (07-006)',
                'level' => 'DEPARTMENT HEAD',
                'sasaran' => 'Dept Head Produksi, Dept Head Engineering',
                'penilai_1' => 'Dept Head Mandiri (Self Appraisal)',
                'penilai_2' => 'Division Head (Penilai Final)',
                'pengesahan' => 'Division Head (Final)',
                'bobot' => 'Hasil Kerja (55%), Proses (15%), Man Mgt (15%), Sikap (15%)',
                'format' => '07-006-DH.xlsx',
                'is_bilingual' => true,
                'sample_doc_id' => $sampleDocMap['07-006-DH'] ?? 8,
                'sample_karyawan' => [
                    'nama' => 'SUPARDI',
                    'nrp' => '31',
                    'golongan' => 'VA',
                    'jabatan' => 'DEPT HEAD',
                    'tgl_masuk' => '1996-05-08',
                    'seksi' => 'PROD 2',
                    'departemen' => 'PRODUCTION A DIV',
                    'banner' => 'DEPARTMENT HEAD / 部長',
                ],
                'ttd_config' => [
                    'is_single_ttd' => false,
                    'col1' => ['header' => 'Kary. Ybs', 'sub' => '個人的', 'role' => 'Dept. Head', 'name' => 'SUPARDI', 'is_blocked' => false],
                    'col2' => ['header' => '', 'sub' => '', 'role' => '', 'name' => '', 'is_blocked' => true],
                    'col3' => ['header' => 'Penilai Final', 'sub' => '最終審査員', 'role' => 'Div. Head', 'name' => 'ATASAN PROD A', 'is_blocked' => false],
                    'penilai_1_col' => 'Mandiri / 自分',
                    'penilai_2_col' => '',
                    'penilai_3_col' => 'Div. Head',
                    'col1_type' => 'button',
                    'col2_type' => 'blocked',
                    'col3_type' => 'dash',
                ],
            ],
            [
                'kode' => '07-006-DIV',
                'nama' => 'Formulir Penilaian Division Head',
                'nama_jp' => 'ディビジョンヘッド評価表 (07-006)',
                'level' => 'DIVISION HEAD',
                'sasaran' => 'Division Head Manufacturing / All Units',
                'penilai_1' => 'Division Head Mandiri (Self Appraisal / 自分)',
                'penilai_2' => '-',
                'pengesahan' => '-',
                'bobot' => 'Hasil Kerja (55%), Proses (15%), Man Mgt (15%), Sikap (15%)',
                'format' => '07-006-DIV.xlsx',
                'is_bilingual' => true,
                'sample_doc_id' => $sampleDocMap['07-006-DIV'] ?? 182,
                'sample_karyawan' => [
                    'nama' => 'ATASAN PROD A',
                    'nrp' => '11',
                    'golongan' => 'VA',
                    'jabatan' => 'DIV HEAD',
                    'tgl_masuk' => '1996-05-08',
                    'seksi' => 'PRODUCTION A DIV',
                    'departemen' => 'PRODUCTION A DIV',
                    'banner' => 'DIVISION HEAD / 事業部長',
                ],
                'ttd_config' => [
                    'is_single_ttd' => false,
                    'col1' => ['header' => 'Kary. Ybs', 'sub' => '個人的', 'role' => 'Div. Head', 'name' => 'ATASAN PROD A', 'is_blocked' => false],
                    'col2' => ['header' => '', 'sub' => '', 'role' => '', 'name' => '', 'is_blocked' => true],
                    'col3' => ['header' => '', 'sub' => '', 'role' => '', 'name' => '', 'is_blocked' => true],
                    'penilai_1_col' => '',
                    'penilai_2_col' => '',
                    'penilai_3_col' => 'Mandiri (自分)',
                    'col1_type' => 'blocked',
                    'col2_type' => 'blocked',
                    'col3_type' => 'button',
                ],
            ],
        ];

        $calcService = app(\App\Services\AppraisalCalculationService::class);
        foreach ($daftarForm as &$df) {
            $df['structure'] = $calcService->getFormStructure($df['kode']);
        }
        unset($df);

        return view('admin.pengaturan_form', compact(
            'formSettings',
            'daftarForm',
            'activePeriod'
        ));
    }

    /**
     * Lembar Penilaian PK: Katalog Tampilan Asli Semua Jenis Form yang Digunakan
     * Selalu terhubung langsung dengan data hasil dari Kelola Form & Periode
     */
    public function lembarPenilaian(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->isAdmin() && !$user->isSuperadmin()) {
            abort(403, 'Akses terbatas untuk Admin dan Superadmin.');
        }

        /** @var \Illuminate\View\View $view */
        $view = $this->pengaturanForm($request);
        $data = $view->getData();

        // 1. Fetch criteria rubric descriptions (1-5) for every aspect code from master_kriteria
        $kriteriaRows = DB::table('master_kriteria')->get();
        $kriteriaMap = [];
        foreach ($kriteriaRows as $kr) {
            $kriteriaMap[$kr->aspek_kode][$kr->skor] = $kr->deskripsi;
        }

        // 2. Fetch all real employees grouped by kode_form from DATA PK
        $allDocs = DB::table('dokumen')
            ->join('karyawan', 'dokumen.nrp', '=', 'karyawan.nrp')
            ->select(
                'dokumen.id as doc_id',
                'dokumen.kode_form',
                'dokumen.status',
                'dokumen.total_nilai',
                'dokumen.grade_original',
                'dokumen.docking_final',
                'karyawan.nrp',
                'karyawan.nama_lengkap',
                'karyawan.golongan',
                'karyawan.sub_gol',
                'karyawan.jabatan',
                'karyawan.seksi',
                'karyawan.departemen',
                'karyawan.division',
                'karyawan.tgl_masuk',
                'karyawan.nrp_foreman',
                'karyawan.nrp_section_head',
                'karyawan.nrp_dept_head',
                'karyawan.nrp_div_head'
            )
            ->orderBy('karyawan.nrp')
            ->get();

        $userNames = DB::table('users')->pluck('nama', 'nrp')->toArray();
        $karyawanNames = DB::table('karyawan')->pluck('nama_lengkap', 'nrp')->toArray();
        $getName = function($nrp) use ($userNames, $karyawanNames) {
            if (!$nrp) return '-';
            return $userNames[$nrp] ?? ($karyawanNames[$nrp] ?? "NRP $nrp");
        };

        $employeesByForm = [];
        foreach ($allDocs as $doc) {
            $fCode = $doc->kode_form;
            if (!isset($employeesByForm[$fCode])) {
                $employeesByForm[$fCode] = [];
            }
            $cleanSeksi = trim(preg_replace('/\s+SEC\.?$/i', '', (string)$doc->seksi));
            $employeesByForm[$fCode][] = [
                'doc_id' => $doc->doc_id,
                'nrp' => $doc->nrp,
                'nama' => $doc->nama_lengkap,
                'golongan' => $doc->sub_gol ?: $doc->golongan,
                'jabatan' => $doc->jabatan,
                'seksi' => $cleanSeksi ?: ($doc->seksi ?: '-'),
                'departemen' => $doc->departemen ?: ($doc->division ?: '-'),
                'tgl_masuk' => $doc->tgl_masuk ?: '-',
                'foreman_nama' => $getName($doc->nrp_foreman),
                'sh_nama' => $getName($doc->nrp_section_head),
                'dh_nama' => $getName($doc->nrp_dept_head),
                'div_nama' => $getName($doc->nrp_div_head),
            ];
        }

        $data['kriteriaMap'] = $kriteriaMap;
        $data['employeesByForm'] = $employeesByForm;
        $data['initialKode'] = $request->query('kode', '07-001');
        $data['initialDocId'] = $request->query('dokumen_id', null);

        return view('admin.lembar_penilaian', $data);
    }

    /**
     * Simpan Pengaturan Formulir & Periode Penilaian
     */
    public function simpanPengaturanForm(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->isAdmin() && !$user->isSuperadmin()) {
            abort(403, 'Akses terbatas untuk Admin dan Superadmin.');
        }

        $validated = $request->validate([
            'tahun_penilaian' => 'required|integer|min:2020|max:2035',
            'periode_penilaian' => 'required|string|max:100',
            'judul_form_pk' => 'required|string|max:150',
            'nama_perusahaan' => 'nullable|string|max:150',
            'catatan_petunjuk_penilai' => 'nullable|string|max:500',
            'status_periode' => 'required|in:aktif,nonaktif,selesai',
            'tgl_mulai' => 'nullable|date',
            'tgl_selesai' => 'nullable|date',
            'label_kerahasiaan' => 'nullable|string|max:50',
            // Catatan Dokumen
            'catatan_1' => 'nullable|string|max:255',
            'catatan_2' => 'nullable|string|max:255',
            // Evaluasi Potensi & Kolom Karyawan
            'judul_evaluasi_potensi' => 'nullable|string|max:255',
            'petunjuk_evaluasi_potensi' => 'nullable|string|max:1000',
            'opsi_potensi_1' => 'nullable|string|max:255',
            'opsi_potensi_2' => 'nullable|string|max:255',
            'opsi_potensi_3' => 'nullable|string|max:255',
            'opsi_potensi_4' => 'nullable|string|max:255',
            'judul_kelebihan' => 'nullable|string|max:255',
            'judul_kekurangan' => 'nullable|string|max:255',
        ]);

        // 1. Update pengaturan table
        foreach ($validated as $key => $val) {
            DB::table('pengaturan')->updateOrInsert(
                ['key' => $key],
                ['value' => (string)$val, 'updated_at' => now()]
            );
        }

        // 2. Update periode table
        DB::table('periode')->updateOrInsert(
            ['id' => 1],
            [
                'tahun' => (int)$validated['tahun_penilaian'],
                'tgl_mulai' => $validated['tgl_mulai'] ?? '2025-01-01',
                'tgl_selesai' => $validated['tgl_selesai'] ?? '2025-12-31',
                'status' => $validated['status_periode'],
                'updated_at' => now(),
            ]
        );

        // 3. Optional: save custom form structure if provided
        if ($request->filled('custom_kode_form') && $request->filled('custom_form_structure')) {
            $cKode = trim($request->input('custom_kode_form'));
            $cStruct = $request->input('custom_form_structure');
            $cDecoded = is_array($cStruct) ? $cStruct : json_decode($cStruct, true);
            if (is_array($cDecoded) && !empty($cDecoded['groups'])) {
                DB::table('pengaturan')->updateOrInsert(
                    ['key' => 'form_structure_' . $cKode],
                    ['value' => json_encode($cDecoded, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]
                );
            }
        }

        // 4. Optional: reset custom form structure if requested
        if ($request->has('reset_custom_structure') && $request->filled('custom_kode_form')) {
            $cKode = trim($request->input('custom_kode_form'));
            DB::table('pengaturan')->where('key', 'form_structure_' . $cKode)->delete();
        }

        $this->repository->clearCache('periode');
        $this->repository->clearCache('pengaturan');

        $msg = "Pengaturan formulir berhasil disimpan! Tahun {$validated['tahun_penilaian']} ({$validated['periode_penilaian']}) dan judul '{$validated['judul_form_pk']}' kini aktif di seluruh formulir penilai (Foreman, Section Head, Dept Head, dan Div Head).";

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Update employee master data from Admin Karyawan
     */
    public function updateKaryawan(Request $request, string $nrp)
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user && ($user->isForeman() || (!$user->isAdmin() && !$user->isHr() && !$user->isSuperadmin()))) {
            return redirect()->back()->with('error', 'Akses ditolak: Foreman hanya memiliki izin untuk menilai kinerja bawahan, tidak dapat mengubah data karyawan.');
        }

        $karyawan = $this->repository->find('karyawan', 'nrp', $nrp);
        if (!$karyawan) {
            return redirect()->back()->with('error', "Karyawan dengan NRP {$nrp} tidak ditemukan.");
        }

        $validated = $request->validate([
            'nama_lengkap' => 'required|string|max:150',
            'jabatan' => 'required|string|max:100',
            'tgl_masuk' => 'nullable|string',
            'golongan' => 'nullable|string',
            'sub_gol' => 'nullable|string',
            'kj' => 'nullable|string',
            'departemen' => 'nullable|string',
            'seksi' => 'nullable|string',
            'code_bagian' => 'nullable|string',
            'nama_bagian' => 'nullable|string',
            'nama_sect' => 'nullable|string',
            'division' => 'nullable|string',
        ]);

        $subGol = strtoupper(trim($validated['sub_gol'] ?? $karyawan['sub_gol'] ?? ''));
        // Clean sub_gol from any "GOLONGAN" prefix
        $subGol = trim((string)preg_replace('/GOLONGAN\s*/i', '', $subGol));

        $kj = trim($validated['kj'] ?? $karyawan['kj'] ?? '');
        if ($kj !== '' && !str_starts_with(strtoupper($kj), 'KJ') && is_numeric($kj)) {
            $kj = 'KJ ' . $kj;
        }

        $jabatan = strtoupper(trim($validated['jabatan']));

        // Auto-derive golongan cleanly from sub_gol (I A s.d. VI D)
        $subUpper = strtoupper($subGol);
        if (str_starts_with($subUpper, 'VI')) {
            $golongan = 'GOLONGAN VI';
        } elseif (str_starts_with($subUpper, 'V')) {
            $golongan = 'GOLONGAN V';
        } elseif (str_starts_with($subUpper, 'IV')) {
            $golongan = 'GOLONGAN IV';
        } elseif (str_starts_with($subUpper, 'III')) {
            $golongan = 'GOLONGAN III';
        } elseif (str_starts_with($subUpper, 'II')) {
            $golongan = 'GOLONGAN II';
        } elseif (str_starts_with($subUpper, 'I')) {
            $golongan = 'GOLONGAN I';
        } else {
            $golongan = $validated['golongan'] ?? ($karyawan['golongan'] ?? 'GOLONGAN II');
        }

        $namaSect = strtoupper(trim($validated['nama_sect'] ?? ($validated['seksi'] ?? ($karyawan['nama_sect'] ?? ($karyawan['seksi'] ?? '')))));
        $seksi = !empty($validated['seksi']) ? strtoupper(trim($validated['seksi'])) : $namaSect;

        $tempKaryawan = array_merge($karyawan, [
            'kj' => $kj,
            'sub_gol' => $subGol,
            'jabatan' => $jabatan,
        ]);
        $levelForm = \App\Http\Controllers\PenilaianController::determineLevelForm($tempKaryawan);
        $freshKodeForm = \App\Http\Controllers\PenilaianController::determineKodeForm($tempKaryawan);

        $updateData = [
            'nama_lengkap' => strtoupper(trim($validated['nama_lengkap'])),
            'jabatan' => $jabatan,
            'tgl_masuk' => (!empty($validated['tgl_masuk'])) ? \App\Services\GoogleSyncService::formatMusashiDate($validated['tgl_masuk']) : \App\Services\GoogleSyncService::formatMusashiDate($karyawan['tgl_masuk'] ?? '01-Jan-20'),
            'golongan' => $golongan,
            'sub_gol' => strtoupper($subGol),
            'kj' => strtoupper($kj),
            'level_form' => $levelForm,
            'division' => trim((string)($validated['division'] ?? ($karyawan['division'] ?? ''))) ?: ($karyawan['division'] ?? null),
            'departemen' => $validated['departemen'] ?? ($karyawan['departemen'] ?? 'PROD 2'),
            'seksi' => $seksi,
            'code_bagian' => trim($validated['code_bagian'] ?? ($karyawan['code_bagian'] ?? '')),
            'nama_bagian' => strtoupper(trim($validated['nama_bagian'] ?? ($karyawan['nama_bagian'] ?? ''))),
            'nama_sect' => $namaSect,
        ];

        $this->repository->update('karyawan', 'nrp', $nrp, $updateData);
        $this->repository->clearCache('karyawan');

        // Cascade updated employee name to app_users, supervisor fields in dokumen, and signatures
        $empName = $updateData['nama_lengkap'];
        $linkedUser = DB::table('app_users')->where('nrp', $nrp)->first();
        if ($linkedUser) {
            $bc = User::generateBarcodeDataUri($nrp, $empName, $linkedUser->role);
            DB::table('app_users')->where('id', $linkedUser->id)->update([
                'nama' => $empName,
                'departemen' => $updateData['departemen'] !== 'ALL' ? $updateData['departemen'] : DB::raw('departemen'),
                'barcodettd' => $bc,
                'ttdbarcode' => $bc,
                'updated_at' => now(),
            ]);
        }

        DB::table('dokumen')->where('nrp_foreman', $nrp)->update(['nama_foreman' => $empName]);
        DB::table('dokumen')->where('nrp_section_head', $nrp)->update(['nama_section_head' => $empName]);
        DB::table('dokumen')->where('nrp_dept_head', $nrp)->update(['nama_dept_head' => $empName]);
        DB::table('dokumen')->where('nrp_div_head', $nrp)->update(['nama_div_head' => $empName]);

        if (Schema::hasTable('tanda_tangan')) {
            DB::table('tanda_tangan')->where('nrp', $nrp)->update(['nama' => $empName]);
        }

        $this->repository->clearCache('users');
        Cache::forget('sheet_all_users');

        // Synchronize supervisor hierarchy to dokumen table
        $kRow = DB::table('karyawan')->where('nrp', $nrp)->first();
        if ($kRow) {
            $getName = function (?string $n) {
                if (!$n) return null;
                $row = DB::table('karyawan')->where('nrp', $n)->first();
                if ($row && !empty($row->nama_lengkap)) return $row->nama_lengkap;
                $u = DB::table('app_users')->where('nrp', $n)->first();
                return $u ? $u->nama : null;
            };

            $nrpF = !empty($kRow->nrp_foreman) ? trim((string)$kRow->nrp_foreman) : null;
            $nrpS = !empty($kRow->nrp_section_head) ? trim((string)$kRow->nrp_section_head) : null;
            $nrpD = !empty($kRow->nrp_dept_head) ? trim((string)$kRow->nrp_dept_head) : null;
            $nrpDiv = !empty($kRow->nrp_div_head) ? trim((string)$kRow->nrp_div_head) : null;

            $docUpdates = [
                'nrp_foreman' => $nrpF,
                'nama_foreman' => $nrpF ? $getName($nrpF) : null,
                'nrp_section_head' => $nrpS,
                'nama_section_head' => $nrpS ? $getName($nrpS) : null,
                'nrp_dept_head' => $nrpD,
                'nama_dept_head' => $nrpD ? $getName($nrpD) : null,
                'nrp_div_head' => $nrpDiv,
                'nama_div_head' => $nrpDiv ? $getName($nrpDiv) : null,
                'updated_at' => now()->toDateTimeString(),
            ];

            $existingDoc = DB::table('dokumen')->where('nrp', $nrp)->orderByDesc('id')->first();
            if ($existingDoc && in_array($existingDoc->status, ['draft', 'menunggu_foreman', 'belum_dinilai']) && empty($existingDoc->total_nilai)) {
                $docUpdates['kode_form'] = $freshKodeForm;
            }

            DB::table('dokumen')->where('nrp', $nrp)->update($docUpdates);
            $this->repository->clearCache('dokumen');
        }

        // Fast real-time sync for this employee's account (< 15ms):
        // Automatically creates account if promoted/qualified (KJ > 5 or leadership),
        // and AUTOMATICALLY DELETES account from app_users and Google Spreadsheet if demoted to KJ <= 5 (e.g. STAF KJ 2/3/4/5)
        try {
            \App\Services\AccountSyncService::syncAccountForEmployee($nrp);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Account sync error in updateKaryawan: " . $e->getMessage());
        }

        // Sinkronisasi update karyawan ke Google Spreadsheet secara background non-blocking (< 5ms)
        \App\Services\GoogleSyncService::syncEmployeeRowAsync('upsert', $nrp);
        \App\Services\GoogleSyncService::syncTablesAsync(['karyawan', 'dokumen', 'users']);

        $redirectTo = (url()->previous() && str_contains(url()->previous(), 'karyawan') && !str_contains(url()->previous(), 'admin'))
            ? route('karyawan.index')
            : route('admin.karyawan');

        return redirect()->to($redirectTo)->with('success', "Data karyawan {$updateData['nama_lengkap']} (NRP: {$nrp}) berhasil diperbarui!");
    }

    /**
     * Halaman Revisi Form Penilaian (Admin & Super Admin)
     * Mengelola dan mengembalikan form penilaian agar dapat diisi ulang oleh penilai terkait.
     */
    public function revisiPenilaian(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->isAdmin() && !$user->isSuperadmin()) {
            abort(403, 'Akses terbatas untuk Admin dan Superadmin.');
        }

        $calcService = app(AppraisalCalculationService::class);

        // Filter parameters
        $search = trim((string)$request->query('search', ''));
        $filterDivisi = trim((string)$request->query('divisi', ''));
        $filterDept = trim((string)$request->query('departemen', ''));
        $filterSeksi = trim((string)$request->query('seksi', ''));
        $filterKj = trim((string)$request->query('kj', ''));
        $filterStatus = trim((string)$request->query('status', ''));

        // Master filters data
        $divisions = DB::table('karyawan')
            ->whereNotNull('division')
            ->where('division', '!=', '')
            ->where('division', '!=', '-')
            ->distinct()
            ->pluck('division')
            ->sort()
            ->values();
        $departments = DB::table('karyawan')->whereNotNull('departemen')->where('departemen', '!=', '')->distinct()->pluck('departemen')->sort()->values();
        $sections = DB::table('karyawan')->whereNotNull('seksi')->where('seksi', '!=', '')->distinct()->pluck('seksi')->sort()->values();
        $kjList = DB::table('karyawan')->whereNotNull('kj')->where('kj', '!=', '')->distinct()->pluck('kj')->sort()->values();

        // Status counts for overview stats cards
        $totalDokumen = DB::table('dokumen')->count();
        $totalMenunggu = DB::table('dokumen')->where('status', 'like', 'menunggu_%')->count();
        $totalRevisi = DB::table('dokumen')->where('status', 'revisi')->count();
        $totalSelesai = DB::table('dokumen')->where('status', 'selesai')->count();

        $query = DB::table('dokumen')
            ->join('karyawan', 'dokumen.nrp', '=', 'karyawan.nrp')
            ->select(
                'dokumen.id as doc_id',
                'dokumen.nrp',
                'dokumen.kode_form',
                'dokumen.status',
                'dokumen.total_nilai',
                'dokumen.grade_original',
                'dokumen.docking_final',
                'dokumen.updated_at',
                'karyawan.nama_lengkap',
                'karyawan.division',
                'karyawan.departemen',
                'karyawan.seksi',
                'karyawan.jabatan',
                'karyawan.kj',
                'karyawan.golongan',
                'karyawan.nrp_foreman',
                'karyawan.nrp_section_head',
                'karyawan.nrp_dept_head',
                'karyawan.nrp_div_head'
            );

        if ($search !== '') {
            $query->where(function($q) use ($search) {
                $q->where('karyawan.nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('karyawan.nrp', 'like', "%{$search}%");
            });
        }

        if ($filterDivisi !== '') {
            $query->where('karyawan.division', $filterDivisi);
        }
        if ($filterDept !== '') {
            $query->where('karyawan.departemen', $filterDept);
        }
        if ($filterSeksi !== '') {
            $query->where('karyawan.seksi', $filterSeksi);
        }
        if ($filterKj !== '') {
            $query->where('karyawan.kj', $filterKj);
        }
        if ($filterStatus !== '') {
            $query->where('dokumen.status', $filterStatus);
        }

        $dokumenList = $query->orderBy('karyawan.departemen')
            ->orderBy('karyawan.nama_lengkap')
            ->paginate(20)
            ->appends($request->query());

        // Batch fetch scores and signatures for items on current page
        $docIds = $dokumenList->pluck('doc_id')->toArray();

        $scoresByDoc = [];
        if (!empty($docIds)) {
            $nilaiRows = DB::table('nilai')
                ->whereIn('dokumen_id', $docIds)
                ->get(['dokumen_id', 'level', 'aspek_kode', 'skor']);
            foreach ($nilaiRows as $nr) {
                $scoresByDoc[$nr->dokumen_id][$nr->level][$nr->aspek_kode] = (float)$nr->skor;
            }
        }

        $ttdByDoc = [];
        if (!empty($docIds)) {
            $ttdRows = DB::table('tanda_tangan')
                ->whereIn('dokumen_id', $docIds)
                ->get(['dokumen_id', 'level', 'user_id', 'tgl_ttd', 'ttd_image']);
            foreach ($ttdRows as $tr) {
                $ttdByDoc[$tr->dokumen_id][$tr->level] = $tr;
            }
        }

        // Enrich items with calculation data and revision permissions
        $enrichedItems = [];
        foreach ($dokumenList as $doc) {
            $docId = $doc->doc_id;
            $kodeForm = $doc->kode_form;

            $isGol4Up = in_array($kodeForm, ['07-006-SH', '07-006-DH', '07-006-DIV']);

            // Needs Foreman?
            $needsForeman = !empty($doc->nrp_foreman) && trim($doc->nrp_foreman) !== '-' && !$isGol4Up;

            // 1. Foreman scores & signature
            $foremanScores = $scoresByDoc[$docId]['foreman'] ?? [];
            $foremanCalc = !empty($foremanScores) ? $calcService->calculate($kodeForm, $foremanScores) : null;
            $foremanTotal = $foremanCalc['grand_total'] ?? 0.00;
            $foremanSigned = !empty($ttdByDoc[$docId]['foreman'])
                || in_array($doc->status, ['menunggu_section_head', 'menunggu_dept_head', 'menunggu_div_head', 'selesai']);
            if (in_array($doc->status, ['menunggu_foreman', 'draft', 'revisi'])) {
                $foremanSigned = false;
            }
            if (!$needsForeman && empty($foremanScores) && empty($ttdByDoc[$docId]['foreman'])) {
                $foremanSigned = false;
            }
            $hasForemanValue = (!empty($foremanScores) && $foremanTotal > 0) || $foremanSigned;

            // 2. Section Head scores & signature
            // Pada Form 07-006 (07-006-SH / Mandiri Section Head): nilai Mandiri ada di slot 'foreman'
            if ($isGol4Up) {
                $sectionScores = $scoresByDoc[$docId]['foreman'] ?? [];
            } else {
                $sectionScores = $scoresByDoc[$docId]['section_head'] ?? [];
            }
            $sectionCalc = !empty($sectionScores) ? $calcService->calculate($kodeForm, $sectionScores) : null;
            $sectionTotal = $sectionCalc['grand_total'] ?? 0.00;
            if ($sectionTotal <= 0 && $isGol4Up && !empty($foremanTotal)) {
                $sectionTotal = $foremanTotal;
            }
            $sectionSigned = !empty($ttdByDoc[$docId]['section_head'])
                || ($isGol4Up && !empty($ttdByDoc[$docId]['foreman']))
                || in_array($doc->status, ['menunggu_dept_head', 'menunggu_div_head', 'selesai']);
            if (in_array($doc->status, ['menunggu_foreman', 'draft'])) {
                $sectionSigned = false;
                $hasSectionValue = false;
                $sectionTotal = 0.00;
            } else {
                $hasSectionValue = (!empty($sectionScores) && $sectionTotal > 0) || $sectionSigned;
            }

            // 3. Dept Head scores & signature
            // Pada Form 07-006: Atasan Langsung (Dept Head) disimpan di slot 'section_head'
            // Pada Form standar: Dept Head disimpan di slot 'dept_head'
            if ($isGol4Up) {
                $deptScores = $scoresByDoc[$docId]['section_head'] ?? [];
            } else {
                $deptScores = $scoresByDoc[$docId]['dept_head'] ?? [];
            }
            $deptCalc = !empty($deptScores) ? $calcService->calculate($kodeForm, $deptScores) : null;
            $deptTotal = $deptCalc['grand_total'] ?? 0.00;
            if ($deptTotal <= 0 && ($doc->total_nilai ?? 0) > 0 && in_array($doc->status, ['menunggu_div_head', 'selesai'])) {
                $deptTotal = (float)$doc->total_nilai;
            }
            $deptSigned = !empty($ttdByDoc[$docId]['dept_head'])
                || ($isGol4Up && !empty($ttdByDoc[$docId]['section_head']))
                || in_array($doc->status, ['menunggu_div_head', 'selesai']);

            // JIKA status masih menunggu foreman / draft / menunggu section head / revisi, Dept Head BELUM mengisi!
            if (in_array($doc->status, ['menunggu_foreman', 'draft', 'menunggu_section_head', 'revisi'])) {
                $deptSigned = false;
                $hasDeptValue = false;
                $deptTotal = 0.00;
            } else {
                $hasDeptValue = (!empty($deptScores) && $deptTotal > 0) || $deptSigned;
            }

            // 4. Div Head scores & signature
            $divScores = $scoresByDoc[$docId]['div_head'] ?? ($isGol4Up ? ($scoresByDoc[$docId]['dept_head'] ?? []) : []);
            $divSigned = !empty($ttdByDoc[$docId]['div_head']) || ($doc->status === 'selesai');
            $hasDivValue = !empty($divScores) || $divSigned || ($doc->status === 'selesai' && $isGol4Up);

            // Revision rules strictly matching user specification:
            // "KETIKA MISAL BELUM ADA NILAI SECTION HEAD MAKA AKSI UNTUK KEMBALI REVISI SECTION HEAD KEATAS IT GABISA HANYA KE FOREMAN JIKA FOREMAN SUDAH ADA NILAI"
            $canRevisiForeman = $needsForeman && $hasForemanValue;
            $canRevisiSection = $hasSectionValue;
            $canRevisiDept = $hasDeptValue;
            $canRevisiDiv = $hasDivValue;

            $hasAnyRevision = $canRevisiForeman || $canRevisiSection || $canRevisiDept || $canRevisiDiv;

            $enrichedItems[] = (object)[
                'raw' => $doc,
                'doc_id' => $docId,
                'nrp' => $doc->nrp,
                'nama_lengkap' => $doc->nama_lengkap,
                'division' => $doc->division ?? '-',
                'departemen' => $doc->departemen,
                'seksi' => $doc->seksi,
                'jabatan' => $doc->jabatan,
                'kj' => $doc->kj,
                'golongan' => $doc->golongan,
                'kode_form' => $kodeForm,
                'status' => $doc->status,
                'total_nilai' => in_array($doc->status, ['menunggu_foreman', 'draft', 'menunggu_section_head', 'revisi']) ? null : $doc->total_nilai,
                'grade_original' => in_array($doc->status, ['menunggu_foreman', 'draft', 'menunggu_section_head', 'revisi']) ? null : $doc->grade_original,
                'docking_final' => in_array($doc->status, ['menunggu_foreman', 'draft', 'menunggu_section_head', 'revisi']) ? null : $doc->docking_final,
                'updated_at' => $doc->updated_at,
                'needs_foreman' => $needsForeman,

                // Foreman
                'foreman_total' => $foremanTotal,
                'foreman_signed' => $foremanSigned,
                'has_foreman_value' => $hasForemanValue,

                // Section
                'section_total' => $sectionTotal,
                'section_signed' => $sectionSigned,
                'has_section_value' => $hasSectionValue,

                // Dept
                'dept_total' => $deptTotal,
                'dept_signed' => $deptSigned,
                'has_dept_value' => $hasDeptValue,

                // Div
                'div_signed' => $divSigned,
                'has_div_value' => $hasDivValue,

                // Revision capabilities
                'can_revisi_foreman' => $canRevisiForeman,
                'can_revisi_section' => $canRevisiSection,
                'can_revisi_dept' => $canRevisiDept,
                'can_revisi_div' => $canRevisiDiv,
                'has_any_revision' => $hasAnyRevision,
            ];
        }

        return view('admin.revisi_penilaian', compact(
            'dokumenList',
            'enrichedItems',
            'divisions',
            'departments',
            'sections',
            'kjList',
            'totalDokumen',
            'totalMenunggu',
            'totalRevisi',
            'totalSelesai',
            'search',
            'filterDivisi',
            'filterDept',
            'filterSeksi',
            'filterKj',
            'filterStatus'
        ));
    }

    /**
     * Memproses pengembalian revisi form penilaian oleh Admin / Super Admin
     */
    public function prosesRevisiPenilaian(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->isAdmin() && !$user->isSuperadmin()) {
            abort(403, 'Akses terbatas untuk Admin dan Superadmin.');
        }

        $dokumen = DB::table('dokumen')->where('id', $id)->first();
        if (!$dokumen) {
            return back()->with('error', 'Dokumen penilaian tidak ditemukan.');
        }

        $karyawan = DB::table('karyawan')->where('nrp', $dokumen->nrp)->first();
        if (!$karyawan) {
            return back()->with('error', 'Data karyawan tidak ditemukan.');
        }

        $request->validate([
            'target_level' => 'nullable|string|in:foreman,section_head,dept_head,div_head',
            'target_levels' => 'nullable|array',
            'target_levels.*' => 'string|in:foreman,section_head,dept_head,div_head',
            'alasan' => 'nullable|string|max:500',
            'reset_nilai' => 'nullable|boolean',
        ]);

        $targetLevels = (array)$request->input('target_levels', []);
        if (empty($targetLevels) && $request->filled('target_level')) {
            $targetLevels = [$request->input('target_level')];
        }
        $targetLevels = array_values(array_unique(array_filter($targetLevels)));

        if (empty($targetLevels)) {
            return back()->with('error', 'Pilih minimal satu tingkat revisi.');
        }

        // Tentukan primary target level (level terendah / paling awal yang dipilih untuk menentukan status dokumen baru)
        if (in_array('foreman', $targetLevels)) {
            $primaryTarget = 'foreman';
        } elseif (in_array('section_head', $targetLevels)) {
            $primaryTarget = 'section_head';
        } elseif (in_array('dept_head', $targetLevels)) {
            $primaryTarget = 'dept_head';
        } else {
            $primaryTarget = 'div_head';
        }

        $alasan = trim((string)$request->input('alasan', ''));
        if ($alasan === '') {
            $alasan = 'Revisi pengisian penilaian oleh ' . $user->nama;
        }
        $resetNilai = (bool)$request->input('reset_nilai', false);

        // Fetch existing scores and signatures to validate allowed target
        $scoresByLevel = DB::table('nilai')->where('dokumen_id', $id)->pluck('level')->unique()->toArray();
        $ttdByLevel = DB::table('tanda_tangan')->where('dokumen_id', $id)->pluck('level')->unique()->toArray();

        // Validasi setiap target yang dipilih
        if (in_array('section_head', $targetLevels) && !in_array('section_head', $scoresByLevel) && !in_array('section_head', $ttdByLevel)) {
            return back()->with('error', 'Tidak dapat merevisi ke Section Head karena Section Head belum pernah mengisi penilaian untuk dokumen ini.');
        }
        if (in_array('dept_head', $targetLevels) && !in_array('dept_head', $scoresByLevel) && !in_array('dept_head', $ttdByLevel)) {
            return back()->with('error', 'Tidak dapat merevisi ke Department Head karena Department Head belum pernah mengisi penilaian untuk dokumen ini.');
        }
        if (in_array('div_head', $targetLevels) && !in_array('div_head', $scoresByLevel) && !in_array('div_head', $ttdByLevel) && $dokumen->status !== 'selesai') {
            return back()->with('error', 'Tidak dapat merevisi ke Division Head karena Division Head belum menandatangani dokumen ini.');
        }

        $isGol4Up = in_array($dokumen->kode_form ?? '', ['07-006-SH', '07-006-DH', '07-006-DIV']);

        // Tentukan status dokumen baru berdasarkan level paling awal yang dipilih
        $newStatus = match($primaryTarget) {
            'foreman' => ($isGol4Up ? 'menunggu_section_head' : 'menunggu_foreman'),
            'section_head' => 'menunggu_section_head',
            'dept_head' => 'menunggu_dept_head',
            'div_head' => 'menunggu_div_head',
        };

        // Level tanda tangan yang harus diinvalidasi
        if ($isGol4Up) {
            $levelsToInvalidate = match($primaryTarget) {
                'foreman', 'section_head' => ['foreman', 'section_head', 'dept_head', 'div_head', 'division_head'],
                'dept_head' => ['section_head', 'dept_head', 'div_head', 'division_head'],
                'div_head' => ['dept_head', 'div_head', 'division_head'],
            };
        } else {
            $levelsToInvalidate = match($primaryTarget) {
                'foreman' => ['foreman', 'section_head', 'dept_head', 'div_head', 'division_head'],
                'section_head' => ['section_head', 'dept_head', 'div_head', 'division_head'],
                'dept_head' => ['dept_head', 'div_head', 'division_head'],
                'div_head' => ['div_head', 'division_head'],
            };
        }
        foreach ($targetLevels as $tl) {
            if (!in_array($tl, $levelsToInvalidate)) {
                $levelsToInvalidate[] = $tl;
            }
        }
        $levelsToInvalidate = array_values(array_unique($levelsToInvalidate));

        // Hapus berkas tanda tangan fisik & hapus record di tabel tanda_tangan
        $oldTtds = DB::table('tanda_tangan')->where('dokumen_id', $id)->whereIn('level', $levelsToInvalidate)->get();
        foreach ($oldTtds as $t) {
            if (!empty($t->ttd_image) && Storage::disk('public')->exists($t->ttd_image)) {
                try { Storage::disk('public')->delete($t->ttd_image); } catch (\Throwable $e) {}
            }
        }
        DB::table('tanda_tangan')->where('dokumen_id', $id)->whereIn('level', $levelsToInvalidate)->delete();

        // Tentukan tingkat penilaian (nilai) yang HARUS DIHAPUS dari database:
        // Aturan ketat:
        // - Revisi ke Section Head (atau Foreman): Nilai Dept Head dan Division Head WAJIB DIHAPUS dari DB!
        // - Revisi ke Dept Head: Nilai Division Head WAJIB DIHAPUS dari DB!
        // - Jika opsi $resetNilai dicentang: Nilai pada level target itu sendiri JUGA dihapus bersih!
        $scoresToWipe = [];
        if ($isGol4Up) {
            // Form 07-006:
            // Slot 'foreman' = Mandiri (Section Head)
            // Slot 'section_head' = Dept Head
            // Slot 'dept_head' = Div Head
            if (in_array($primaryTarget, ['foreman', 'section_head'])) {
                $scoresToWipe = ['section_head', 'dept_head', 'div_head', 'division_head'];
                if ($resetNilai) {
                    $scoresToWipe[] = 'foreman';
                }
            } elseif ($primaryTarget === 'dept_head') {
                $scoresToWipe = ['dept_head', 'div_head', 'division_head'];
                if ($resetNilai) {
                    $scoresToWipe[] = 'section_head';
                }
            } elseif ($primaryTarget === 'div_head') {
                if ($resetNilai) {
                    $scoresToWipe[] = 'dept_head';
                }
            }
        } else {
            // Form Standar:
            // Slot 'foreman' = Foreman
            // Slot 'section_head' = Section Head
            // Slot 'dept_head' = Dept Head
            if ($primaryTarget === 'foreman') {
                $scoresToWipe = ['section_head', 'dept_head', 'div_head', 'division_head'];
                if ($resetNilai) {
                    $scoresToWipe[] = 'foreman';
                }
            } elseif ($primaryTarget === 'section_head') {
                $scoresToWipe = ['dept_head', 'div_head', 'division_head'];
                if ($resetNilai) {
                    $scoresToWipe[] = 'section_head';
                }
            } elseif ($primaryTarget === 'dept_head') {
                $scoresToWipe = ['div_head', 'division_head'];
                if ($resetNilai) {
                    $scoresToWipe[] = 'dept_head';
                }
            } elseif ($primaryTarget === 'div_head') {
                if ($resetNilai) {
                    $scoresToWipe[] = 'div_head';
                }
            }
        }

        if (!empty($scoresToWipe)) {
            DB::table('nilai')->where('dokumen_id', $id)->whereIn('level', array_unique($scoresToWipe))->delete();
        }

        // Update status dokumen & bersihkan nilai akhir jika kembali ke tahap awal
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

        // Clear cache
        $this->repository->clearCache('dokumen');
        $this->repository->clearCache('tanda_tangan');
        $this->repository->clearCache('nilai');
        $this->repository->clearCache('indikator');
        Cache::forget("sheet_all_dokumen");
        Cache::forget("sheet_all_tanda_tangan");
        Cache::forget("sheet_all_nilai");
        Cache::forget("sheet_all_indikator");

        // Catat Audit Log
        $targetLabels = array_map(fn($t) => match($t) {
            'foreman' => 'Foreman',
            'section_head' => 'Section Head',
            'dept_head' => 'Department Head',
            'div_head' => 'Division Head',
            default => strtoupper($t),
        }, $targetLevels);
        $targetLabelStr = implode(', ', $targetLabels);

        $this->repository->insert('audit_log', [
            'waktu' => now()->toDateTimeString(),
            'user_id' => $user->id,
            'aksi' => 'admin_revisi_penilaian',
            'dokumen_id' => $id,
            'detail' => "{$user->nama} merevisi form penilaian {$karyawan->nama_lengkap} (NRP: {$karyawan->nrp}) kembali ke {$targetLabelStr}. Alasan: {$alasan}" . ($resetNilai ? ' (Nilai di-reset)' : ' (Nilai lama disimpan sebagai draf)'),
            'created_at' => now()->toDateTimeString(),
        ]);

        // Realtime sync ke spreadsheet secara background non-blocking (< 5ms)
        try {
            GoogleSyncService::syncTablesAsync(['dokumen', 'tanda_tangan', 'nilai', 'audit_log']);
        } catch (\Throwable $e) {}

        return back()->with('success', "Form penilaian {$karyawan->nama_lengkap} ({$karyawan->nrp}) berhasil dikembalikan untuk dinilai ulang oleh {$targetLabelStr}. Status saat ini: " . ucwords(str_replace('_', ' ', $newStatus)) . ".");
    }

    /**
     * History Penilaian (Audit Log Aktivitas Penilaian & Sinkronisasi Spreadsheet)
     * Khusus Admin & Super Admin
     */
    public function historyPenilaian(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->isAdmin() && !$user->isSuperadmin()) {
            abort(403, 'Akses terbatas untuk Admin dan Super Admin.');
        }

        $search = trim((string)$request->input('search', ''));
        $aksiFilter = trim((string)$request->input('aksi', ''));
        $tglMulai = trim((string)$request->input('tgl_mulai', ''));
        $tglSelesai = trim((string)$request->input('tgl_selesai', ''));

        if ($request->boolean('sync')) {
            try {
                \App\Services\GoogleSyncService::syncOperationalTablesFromGoogle(true);
            } catch (\Throwable $e) {}
        }

        // Query audit_log dari DB
        $query = DB::table('audit_log')
            ->leftJoin('app_users', 'audit_log.user_id', '=', 'app_users.id')
            ->leftJoin('dokumen', 'audit_log.dokumen_id', '=', 'dokumen.id')
            ->leftJoin('karyawan', 'dokumen.nrp', '=', 'karyawan.nrp')
            ->select(
                'audit_log.*',
                'app_users.nama as user_nama',
                'app_users.nrp as user_nrp',
                'app_users.role as user_role',
                'dokumen.kode_form as doc_kode_form',
                'dokumen.status as doc_status',
                'karyawan.nama_lengkap as karyawan_nama',
                'karyawan.nrp as karyawan_nrp',
                'karyawan.departemen as karyawan_departemen',
                'karyawan.division as karyawan_division'
            );

        if ($search !== '') {
            $query->where(function($q) use ($search) {
                $q->where('audit_log.detail', 'like', "%{$search}%")
                  ->orWhere('audit_log.aksi', 'like', "%{$search}%")
                  ->orWhere('app_users.nama', 'like', "%{$search}%")
                  ->orWhere('app_users.nrp', 'like', "%{$search}%")
                  ->orWhere('karyawan.nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('karyawan.nrp', 'like', "%{$search}%");
            });
        }

        if ($aksiFilter !== '') {
            $query->where('audit_log.aksi', $aksiFilter);
        }

        if ($tglMulai !== '') {
            $query->where('audit_log.waktu', '>=', $tglMulai . ' 00:00:00');
        }

        if ($tglSelesai !== '') {
            $query->where('audit_log.waktu', '<=', $tglSelesai . ' 23:59:59');
        }

        $logs = $query->orderBy('audit_log.id', 'desc')->paginate(20)->withQueryString();

        // Statistik ringkas
        $totalLogs = DB::table('audit_log')->count();
        $logsHariIni = DB::table('audit_log')->whereDate('waktu', today())->count();
        $totalRevisi = DB::table('audit_log')->where('aksi', 'like', '%revisi%')->count();
        $totalApproval = DB::table('audit_log')->where(function($q) {
            $q->where('aksi', 'like', '%ttd%')
              ->orWhere('aksi', 'like', '%approve%')
              ->orWhere('aksi', 'like', '%selesai%');
        })->count();

        // Daftar aksi untuk filter dropdown
        $daftarAksi = DB::table('audit_log')
            ->select('aksi', DB::raw('count(*) as total'))
            ->groupBy('aksi')
            ->orderBy('total', 'desc')
            ->get();

        return view('admin.history_penilaian', compact(
            'user',
            'logs',
            'totalLogs',
            'logsHariIni',
            'totalRevisi',
            'totalApproval',
            'daftarAksi',
            'search',
            'aksiFilter',
            'tglMulai',
            'tglSelesai'
        ));
    }

    /**
     * Sinkronkan Riwayat Audit Log dari Google Spreadsheet
     */
    public function syncAuditLog(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->isAdmin() && !$user->isSuperadmin()) {
            abort(403, 'Akses terbatas untuk Admin dan Super Admin.');
        }

        try {
            $sheetRepo = app(\App\Contracts\SheetRepositoryInterface::class);
            $sheetRepo->all('audit_log', 0);

            return back()->with('success', 'Riwayat aktivitas (audit_log) berhasil disinkronkan langsung dari Google Spreadsheet.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal menyinkronkan dengan Google Spreadsheet: ' . $e->getMessage());
        }
    }

    /**
     * Hapus satu baris riwayat audit log dan sinkronkan dengan sheet audit_log di Google Spreadsheet.
     */
    public function destroyAuditLog($id)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->isAdmin() && !$user->isSuperadmin()) {
            abort(403, 'Akses terbatas untuk Admin dan Super Admin.');
        }

        $log = DB::table('audit_log')->where('id', $id)->first();
        if (!$log) {
            return back()->with('error', 'Catatan riwayat aktivitas tidak ditemukan.');
        }

        try {
            DB::table('audit_log')->where('id', $id)->delete();
            \Illuminate\Support\Facades\Cache::put('last_google_operational_sync', true, 60);
            \Illuminate\Support\Facades\Cache::forget('sheet_all_audit_log');
            \App\Services\GoogleSyncService::syncAuditLogDelete([$id]);

            return back()->with('success', 'Catatan riwayat aktivitas #' . $id . ' berhasil dihapus dari sistem dan sheet audit_log Google Spreadsheet.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal menghapus riwayat aktivitas: ' . $e->getMessage());
        }
    }

    /**
     * Hapus massal riwayat audit log terpilih dan sinkronkan dengan Google Spreadsheet.
     */
    public function bulkDestroyAuditLog(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->isAdmin() && !$user->isSuperadmin()) {
            abort(403, 'Akses terbatas untuk Admin dan Super Admin.');
        }

        $ids = $request->input('ids', []);
        if (empty($ids) || !is_array($ids)) {
            return back()->with('error', 'Tidak ada data riwayat yang dipilih untuk dihapus.');
        }

        try {
            $count = DB::table('audit_log')->whereIn('id', $ids)->delete();
            \Illuminate\Support\Facades\Cache::put('last_google_operational_sync', true, 60);
            \Illuminate\Support\Facades\Cache::forget('sheet_all_audit_log');
            \App\Services\GoogleSyncService::syncAuditLogDelete($ids);

            return back()->with('success', "Sebanyak {$count} catatan riwayat aktivitas berhasil dihapus dari sistem dan sheet audit_log Google Spreadsheet.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal menghapus riwayat aktivitas terpilih: ' . $e->getMessage());
        }
    }

    /**
     * Kosongkan seluruh riwayat audit log (lokal & Google Spreadsheet).
     */
    public function clearAllAuditLog(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->isAdmin() && !$user->isSuperadmin()) {
            abort(403, 'Akses terbatas untuk Admin dan Super Admin.');
        }

        try {
            $totalCount = DB::table('audit_log')->count();
            DB::table('audit_log')->delete();
            \Illuminate\Support\Facades\Cache::put('last_google_operational_sync', true, 60);
            \Illuminate\Support\Facades\Cache::forget('sheet_all_audit_log');
            \App\Services\GoogleSyncService::syncAuditLogDelete([], true);

            return back()->with('success', "Seluruh riwayat aktivitas ({$totalCount} rekaman) berhasil dikosongkan dari sistem dan sheet audit_log Google Spreadsheet.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal mengosongkan riwayat aktivitas: ' . $e->getMessage());
        }
    }
}
