<?php

namespace App\Http\Controllers;

use App\Contracts\SheetRepositoryInterface;
use App\Models\User;
use App\Services\ExcelKaryawanExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class KaryawanController extends Controller
{
    protected SheetRepositoryInterface $repository;

    public function __construct(SheetRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Display list of employees (subordinates for Foreman, Section Head, or Dept Head).
     * Matching Master Karyawan format with full 12 columns, metrics, and filters.
     */
    public function index(Request $request)
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
        $filterGol = trim($request->input('golongan', ''));
        $filterSeksi = trim($request->input('seksi', ''));
        $filterJabatan = trim($request->input('jabatan', ''));
        $filterNamaBagian = trim($request->input('nama_bagian', ''));

        if ($user->isForeman()) {
            $filterDept = '';
            $filterDivisi = '';
        }

        // Base filter based on user's supervisory role
        $baseKaryawan = self::scopeByUser($user, $allKaryawan);

        // For Section Head without foreman subordinates, ensure all subordinates immediately have an active document with proper KJ/Self form
        if ($user->isSectionHead() && !$user->hasForemanSubordinates()) {
            $activePeriod = DB::table('periode')->where('status', 'aktif')->first() ?? (object)['id' => 1];
            foreach ($baseKaryawan as $k) {
                $nrpK = $k['nrp'] ?? '';
                if (!$nrpK) continue;
                $expectedKode = (strcasecmp($nrpK, $user->nrp) === 0)
                    ? '07-006-SH'
                    : PenilaianController::determineKodeForm($k);

                if (empty($docsByNrp[$nrpK])) {
                    $newDocId = DB::table('dokumen')->insertGetId([
                        'periode_id' => $activePeriod->id ?? 1,
                        'nrp' => $nrpK,
                        'kode_form' => $expectedKode,
                        'status' => 'menunggu_section_head',
                        'kode_verifikasi' => 'VERIF-' . strtoupper(Str::random(10)),
                        'created_at' => now()->toDateTimeString(),
                        'updated_at' => now()->toDateTimeString(),
                    ]);
                    $docsByNrp[$nrpK] = [
                        'id' => $newDocId,
                        'periode_id' => $activePeriod->id ?? 1,
                        'nrp' => $nrpK,
                        'kode_form' => $expectedKode,
                        'status' => 'menunggu_section_head',
                    ];
                } elseif (in_array($docsByNrp[$nrpK]['status'] ?? '', ['draft', 'menunggu_foreman', 'menunggu_section_head', 'belum_dinilai']) && empty($docsByNrp[$nrpK]['total_nilai'])) {
                    $updates = [];
                    if (($docsByNrp[$nrpK]['kode_form'] ?? '') !== $expectedKode) {
                        $updates['kode_form'] = $expectedKode;
                        $docsByNrp[$nrpK]['kode_form'] = $expectedKode;
                    }
                    if (($docsByNrp[$nrpK]['status'] ?? '') === 'menunggu_foreman') {
                        $updates['status'] = 'menunggu_section_head';
                        $docsByNrp[$nrpK]['status'] = 'menunggu_section_head';
                    }
                    if (!empty($updates)) {
                        $updates['updated_at'] = now()->toDateTimeString();
                        DB::table('dokumen')->where('id', $docsByNrp[$nrpK]['id'])->update($updates);
                    }
                }
            }
        }

        // Dropdown option lists from employee scope
        $deptList = array_values(array_unique(array_filter(array_column($baseKaryawan, 'departemen'))));
        if (empty($deptList)) {
            $deptList = ['PROD 1', 'PROD 2', 'QUALITY ASSURANCE (QA)', 'ENGINEERING', 'HR & IR DEPT.'];
        }

        // Clean & true Seksi (NAMA SECT.) and Golongan / KJ strictly derived from actual employees in scope
        $seksiOptions = [];
        $actualGolMap = [];

        foreach ($baseKaryawan as $bk) {
            // 1. Seksi (NAMA SECT. - BUKAN Nama Bagian / tanpa akhiran .SEC)
            $rawS = trim($bk['nama_sect'] ?? '');
            if (!$rawS) {
                $rawBase = trim($bk['seksi'] ?? '');
                $rawUpper = strtoupper($rawBase);
                $deptUpper = strtoupper(trim($bk['departemen'] ?? 'PROD 2'));
                if (str_contains($rawUpper, '2') || str_contains($rawUpper, 'FACT-2')) {
                    $rawS = 'FACTORY 2';
                } elseif (str_contains($deptUpper, 'ENGINEERING') || str_contains($rawUpper, 'ENG')) {
                    $rawS = 'ENGINEERING';
                } elseif (str_contains($deptUpper, 'QA') || str_contains($rawUpper, 'QA')) {
                    $rawS = 'QA';
                } elseif (str_contains($deptUpper, 'QC') || str_contains($rawUpper, 'QC')) {
                    $rawS = 'QC';
                } else {
                    $clean = trim(preg_replace('/\s+SEC\.?$/i', '', $rawBase));
                    $rawS = ($clean && $clean !== '-') ? $clean : 'FACTORY 1';
                }
            }
            if ($rawS && $rawS !== '-') {
                $seksiOptions[strtoupper($rawS)] = strtoupper($rawS);
            }

            // 2. Golongan / KJ (Hanya KJ yang benar-benar ada di data tabel)
            $kjVal = trim($bk['kj'] ?? '');
            if (!$kjVal) {
                $subGol = strtoupper(trim($bk['sub_gol'] ?? ''));
                if (str_contains($subGol, 'IV') || str_contains($subGol, 'V')) {
                    $kjVal = 'KJ 4';
                } elseif (str_contains($subGol, 'III')) {
                    $kjVal = 'KJ 3';
                } else {
                    $kjVal = 'KJ 2';
                }
            } else {
                if (preg_match('/KJ\s*(\d)/i', $kjVal, $m)) {
                    $kjVal = 'KJ ' . $m[1];
                } else {
                    $kjVal = strtoupper($kjVal);
                }
            }
            if ($kjVal && $kjVal !== '-') {
                $actualGolMap[$kjVal] = $kjVal;
            }
        }

        $seksiList = array_values($seksiOptions);
        sort($seksiList);

        $jabatanList = array_values(array_unique(array_filter(array_map('trim', array_column($baseKaryawan, 'jabatan')))));
        sort($jabatanList);

        $namaBagianOptions = [];
        foreach ($baseKaryawan as $bk) {
            $nb = self::resolveNamaBagian($bk);
            $nbKey = strtoupper(rtrim($nb, '.'));
            if ($nbKey !== '' && !isset($namaBagianOptions[$nbKey])) {
                $namaBagianOptions[$nbKey] = $nb;
            }
        }
        $namaBagianList = array_values($namaBagianOptions);
        sort($namaBagianList, SORT_NATURAL | SORT_FLAG_CASE);

        // Urutkan KJ secara natural berdasarkan data riil yang ada (misal: KJ 2, KJ 3, KJ 4)
        $golList = array_values($actualGolMap);
        natsort($golList);
        $golList = array_values($golList);
        if (empty($golList)) {
            $golList = ['KJ 2', 'KJ 3', 'KJ 4'];
        }
        $divisiList = [
            'Manufacturing Division',
            'Quality Control Division',
            'Technical & Engineering Division',
            'General Affairs & HR Division',
        ];

        // Metric Counts from base subordinates
        $uniqueBagian = [];
        $uniqueSect = [];
        $uniqueDept = [];
        $uniqueJabatan = [];
        $uniqueKj = [];

        foreach ($baseKaryawan as $k) {
            $seksiUpper = strtoupper(trim($k['seksi'] ?? ''));
            $dept = trim($k['departemen'] ?? 'PROD 2');
            $deptUpper = strtoupper($dept);

            $namaBagianNorm = strtoupper(rtrim(self::resolveNamaBagian($k), '.'));
            if ($namaBagianNorm !== '') $uniqueBagian[$namaBagianNorm] = true;

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
            if ($namaSect) $uniqueSect[$namaSect] = true;
            if ($dept) $uniqueDept[$dept] = true;

            $jabatan = strtoupper(trim($k['jabatan'] ?? ''));
            if ($jabatan) $uniqueJabatan[$jabatan] = true;

            $rawKj = trim((string)($k['kj'] ?? ''));
            if ($rawKj !== '') {
                $normKj = \App\Services\ExcelKaryawanReader::normalizeKj($rawKj);
                $uniqueKj[$normKj] = true;
            } else {
                $subGolCheck = strtoupper(trim((string)($k['sub_gol'] ?? '')));
                $fallbackKj = (str_contains($subGolCheck, 'IV') || str_contains($subGolCheck, 'V')) ? 'KJ 4' : (str_contains($subGolCheck, 'III') ? 'KJ 3' : 'KJ 2');
                $uniqueKj[$fallbackKj] = true;
            }
        }

        $kjKeys = array_keys($uniqueKj);
        sort($kjKeys, SORT_NATURAL);

        $hierarchySummary = [
            'total_karyawan' => count($baseKaryawan),
            'total_bagian' => count($uniqueBagian) ?: 1,
            'total_sect' => count($uniqueSect) ?: 1,
            'total_dept' => count($uniqueDept) ?: 1,
            'total_kj' => count($kjKeys) ?: 1,
            'kj_list_label' => !empty($kjKeys) ? implode(', ', $kjKeys) : 'KJ 2',
            'total_jabatan' => count($uniqueJabatan) ?: 1,
        ];

        // Apply Search & Additional Filters
        $filtered = self::filterEmployees($baseKaryawan, [
            'search' => $search,
            'departemen' => $filterDept,
            'seksi' => $filterSeksi,
            'jabatan' => $filterJabatan,
            'nama_bagian' => $filterNamaBagian,
            'golongan' => $filterGol,
        ]);

        // Sort by NRP ascending
        usort($filtered, fn($a, $b) => strcmp($a['nrp'] ?? '', $b['nrp'] ?? ''));

        // Enrich rows exactly matching Master Karyawan format
        $karyawanRows = [];
        foreach ($filtered as $k) {
            $nrp = $k['nrp'];
            $doc = $docsByNrp[$nrp] ?? null;
            $enriched = self::enrichEmployeeDisplayRow($k);

            $kodeForm = (strcasecmp($k['nrp'] ?? '', $user->nrp) === 0 && $user->isSectionHead())
                ? '07-006-SH'
                : PenilaianController::determineKodeForm($enriched);
            $formName = PenilaianController::getFormName($kodeForm);

            // Synchronize unfinalized documents without scores to proper KJ 1-5 / self form and status
            if (!empty($doc['id']) && in_array($doc['status'] ?? '', ['draft', 'menunggu_foreman', 'menunggu_section_head']) && empty($doc['total_nilai'])) {
                $updates = [];
                if (($doc['kode_form'] ?? '') !== $kodeForm) {
                    $updates['kode_form'] = $kodeForm;
                }
                if (empty($k['nrp_foreman']) && ($doc['status'] ?? '') === 'menunggu_foreman') {
                    $updates['status'] = 'menunggu_section_head';
                }
                if (!empty($updates)) {
                    $updates['updated_at'] = now()->toDateTimeString();
                    \Illuminate\Support\Facades\DB::table('dokumen')->where('id', $doc['id'])->update($updates);
                }
            }

            $karyawanRows[] = array_merge($enriched, [
                'kode_form' => $kodeForm,
                'form_name' => $formName,
                'doc_id' => $doc['id'] ?? null,
            ]);
        }

        // Compute smart defaults for Tambah Karyawan modal based on user's context & department
        $defaultDept = (!empty($user->departemen) && $user->departemen !== '-') ? $user->departemen : 'PROD 2';
        $defaultSeksi = $user->seksi ?? '';
        $deptUpper = strtoupper($defaultDept);

        if (str_contains($deptUpper, 'ENGINEERING')) {
            $defaultCodeBagian = '1151500000';
            $defaultNamaBagian = 'ENGINEERING SEC.';
            $defaultNamaSect = 'ENGINEERING';
        } elseif (str_contains($deptUpper, 'QA') || str_contains($deptUpper, 'QUALITY')) {
            $defaultCodeBagian = '1131000000';
            $defaultNamaBagian = 'QA SEC.';
            $defaultNamaSect = 'QA';
        } elseif (str_contains($deptUpper, 'HR')) {
            $defaultCodeBagian = '1121100000';
            $defaultNamaBagian = 'HR SEC.';
            $defaultNamaSect = 'HR & IR';
        } elseif ($defaultSeksi && (str_contains(strtoupper($defaultSeksi), '2') || str_contains(strtoupper($defaultSeksi), 'FACT-2'))) {
            $defaultCodeBagian = '1141700000';
            $defaultNamaBagian = 'FACTORY 2 SEC.';
            $defaultNamaSect = 'FACTORY 2';
        } else {
            // Check if user has subordinates with valid code/sect
            $foundValid = false;
            foreach ($karyawanRows as $sub) {
                if (!empty($sub['code_bagian']) && !empty($sub['nama_sect']) && !str_contains(strtolower($sub['departemen'] ?? ''), 'apalah')) {
                    $defaultCodeBagian = $sub['code_bagian'];
                    $defaultNamaBagian = $sub['nama_bagian'] ?? ($sub['nama_sect'] . ' SEC.');
                    $defaultNamaSect = $sub['nama_sect'];
                    $foundValid = true;
                    break;
                }
            }
            if (!$foundValid) {
                $defaultCodeBagian = '1141600000';
                $defaultNamaBagian = 'FACTORY 1 SEC.';
                $defaultNamaSect = 'FACTORY 1';
            }
        }

        // Build map of all existing NRPs (normalized to lowercase) for instant real-time duplicate validation in the view
        $existingNrpsMap = [];
        $dbKaryawan = DB::table('karyawan')->select('nrp', 'nama_lengkap')->get();
        foreach ($dbKaryawan as $row) {
            if (!empty($row->nrp)) {
                $existingNrpsMap[strtolower(trim($row->nrp))] = $row->nama_lengkap ?? '-';
            }
        }
        foreach ($allKaryawan as $row) {
            $nrpKey = strtolower(trim($row['nrp'] ?? ''));
            if ($nrpKey && !isset($existingNrpsMap[$nrpKey])) {
                $existingNrpsMap[$nrpKey] = $row['nama_lengkap'] ?? '-';
            }
        }

        return view('karyawan.index', [
            'user' => $user,
            'karyawanList' => $karyawanRows,
            'karyawanRows' => $karyawanRows,
            'hierarchySummary' => $hierarchySummary,
            'totalCount' => count($karyawanRows),
            'search' => $search,
            'filterDivisi' => $filterDivisi,
            'filterDept' => $filterDept,
            'filterGol' => $filterGol,
            'filterSeksi' => $filterSeksi,
            'filterJabatan' => $filterJabatan,
            'filterNamaBagian' => $filterNamaBagian,
            'divisiList' => $divisiList,
            'deptList' => $deptList,
            'seksiList' => $seksiList,
            'jabatanList' => $jabatanList,
            'namaBagianList' => $namaBagianList,
            'golList' => $golList,
            'defaultDept' => $defaultDept,
            'defaultCodeBagian' => $defaultCodeBagian,
            'defaultNamaBagian' => $defaultNamaBagian,
            'defaultNamaSect' => $defaultNamaSect,
            'existingNrpsMap' => $existingNrpsMap,
            'isSectionHeadWithoutForeman' => $user->isSectionHead() && !$user->hasForemanSubordinates(),
        ]);
    }

    /**
     * Scope master employee list according to the logged-in user's supervisory role.
     *
     * @param array<int, array<string, mixed>> $allKaryawan
     * @return array<int, array<string, mixed>>
     */
    private static function scopeByUser(User $user, array $allKaryawan): array
    {
        if ($user->isForeman()) {
            return array_values(array_filter($allKaryawan, function ($k) use ($user) {
                if (!empty($k['nrp_foreman']) && strcasecmp($k['nrp_foreman'], $user->nrp) === 0) {
                    return true;
                }
                return strcasecmp($k['nrp'] ?? '', $user->nrp) === 0;
            }));
        }

        if ($user->isSectionHead()) {
            return array_values(array_filter($allKaryawan, fn ($k) => $user->matchesSectionHeadScope($k)));
        }

        if ($user->isDepartmentHead()) {
            return array_values(array_filter($allKaryawan, function ($k) use ($user) {
                return $user->matchesDeptHeadScope($k) && strcasecmp($k['nrp'] ?? '', $user->nrp) !== 0;
            }));
        }

        return $allKaryawan;
    }

    /**
     * Enrich employee row fields identically for the web table and Excel export.
     *
     * @param array<string, mixed> $k
     * @return array<string, mixed>
     */
    private static function enrichEmployeeDisplayRow(array $k): array
    {
        $dept = $k['departemen'] ?? 'PROD 2';
        $seksiUpper = strtoupper($k['seksi'] ?? '');
        $deptUpper = strtoupper($dept);

        $tglMasuk = $k['tgl_masuk'] ?? '';
        $startWork = '-';
        if (!empty($tglMasuk)) {
            try {
                $ts = strtotime($tglMasuk);
                $startWork = $ts ? date('d-M-y', $ts) : $tglMasuk;
            } catch (\Exception $e) {
                $startWork = $tglMasuk;
            }
        }

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

        $namaBagian = self::resolveNamaBagian($k);

        $namaSect = $k['nama_sect'] ?? null;
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

        $kj = $k['kj'] ?? null;
        if (!$kj) {
            $subGolUpper = strtoupper($k['sub_gol'] ?? '');
            if (str_contains($subGolUpper, 'IV') || str_contains($subGolUpper, 'V')) {
                $kj = 'KJ 4';
            } elseif (str_contains($subGolUpper, 'III')) {
                $kj = 'KJ 3';
            } else {
                $kj = 'KJ 2';
            }
        }

        $subGol = $k['sub_gol'] ?? ($k['golongan'] ?? 'IIB');

        $division = trim((string)($k['division'] ?? ''));
        if ($division === '') {
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

        return array_merge($k, [
            'code_bagian' => $codeBagian,
            'nama_bagian' => $namaBagian,
            'nama_sect' => $namaSect,
            'line_category' => trim((string)($k['line_category'] ?? '')) ?: '-',
            'division' => $division,
            'start_work' => $startWork,
            'kj' => $kj,
            'sub_gol' => $subGol,
        ]);
    }

    /**
     * Resolve display name for an employee's organizational section/unit (Nama Bagian).
     */
    private static function resolveNamaBagian(array $k): string
    {
        $namaBagian = trim((string)($k['nama_bagian'] ?? ''));
        if ($namaBagian !== '') {
            return $namaBagian;
        }
        $seksi = trim((string)($k['seksi'] ?? ''));
        return ($seksi !== '' && $seksi !== '-') ? $seksi : 'FACTORY 1 SEC.';
    }

    /**
     * Filter employee list uniformly across index and export endpoints.
     */
    private static function filterEmployees(array $rows, array $filters): array
    {
        $search = trim((string)($filters['search'] ?? ''));
        $filterDept = trim((string)($filters['departemen'] ?? ''));
        $filterSeksi = trim((string)($filters['seksi'] ?? ''));
        $filterJabatan = trim((string)($filters['jabatan'] ?? ''));
        $filterNamaBagian = trim((string)($filters['nama_bagian'] ?? ''));
        $filterGol = trim((string)($filters['golongan'] ?? ''));

        $filtered = $rows;

        if ($search !== '') {
            $filtered = array_filter($filtered, function ($k) use ($search) {
                return stripos($k['nama_lengkap'] ?? '', $search) !== false
                    || stripos($k['nrp'] ?? '', $search) !== false
                    || stripos($k['jabatan'] ?? '', $search) !== false
                    || stripos($k['seksi'] ?? '', $search) !== false
                    || stripos(self::resolveNamaBagian($k), $search) !== false
                    || stripos($k['departemen'] ?? '', $search) !== false;
            });
        }

        if ($filterDept !== '') {
            $filtered = array_filter($filtered, fn($k) => strcasecmp($k['departemen'] ?? '', $filterDept) === 0);
        }

        if ($filterSeksi !== '') {
            $filtered = array_filter($filtered, function ($k) use ($filterSeksi) {
                $rawSeksi = trim($k['seksi'] ?? '');
                $cleanSeksi = trim(preg_replace('/\s+SEC\.?$/i', '', $rawSeksi));
                $namaSect = trim($k['nama_sect'] ?? '');
                return strcasecmp($rawSeksi, $filterSeksi) === 0
                    || strcasecmp($cleanSeksi, $filterSeksi) === 0
                    || strcasecmp($namaSect, $filterSeksi) === 0;
            });
        }

        if ($filterJabatan !== '') {
            $filtered = array_filter($filtered, fn($k) => strcasecmp(trim($k['jabatan'] ?? ''), $filterJabatan) === 0);
        }

        if ($filterNamaBagian !== '') {
            $filterBagianNorm = rtrim($filterNamaBagian, '.');
            $filtered = array_filter($filtered, function ($k) use ($filterBagianNorm) {
                return strcasecmp(rtrim(self::resolveNamaBagian($k), '.'), $filterBagianNorm) === 0;
            });
        }

        if ($filterGol !== '') {
            $filtered = array_filter($filtered, function ($k) use ($filterGol) {
                $kj = trim($k['kj'] ?? '');
                if (!$kj) {
                    $subGol = strtoupper(trim($k['sub_gol'] ?? ''));
                    $kj = (str_contains($subGol, 'IV') || str_contains($subGol, 'V')) ? 'KJ 4' : (str_contains($subGol, 'III') ? 'KJ 3' : 'KJ 2');
                } else {
                    if (preg_match('/KJ\s*(\d)/i', $kj, $m)) {
                        $kj = 'KJ ' . $m[1];
                    }
                }
                $cleanFilter = str_replace(' ', '', strtoupper($filterGol));
                $cleanKj = str_replace(' ', '', strtoupper($kj));
                return $cleanFilter === $cleanKj || strcasecmp($kj, $filterGol) === 0;
            });
        }

        return $filtered;
    }

    /**
     * Add a new subordinate employee.
     */
    public function store(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user && ($user->isForeman() || (!$user->isAdmin() && !$user->isHr() && !$user->isSuperadmin()))) {
            return redirect()->back()->with('error', 'Akses ditolak: Foreman hanya memiliki izin untuk menilai kinerja bawahan, tidak dapat menambah data karyawan.');
        }

        $validator = Validator::make($request->all(), [
            'nrp' => 'required|string|max:20',
            'nama_lengkap' => 'required|string|max:150',
            'golongan' => 'nullable|string|max:50',
            'sub_gol' => 'required|string|max:20',
            'kj' => 'nullable|string|max:20',
            'jabatan' => 'required|string|max:100',
            'tgl_masuk' => 'required|date',
            'seksi' => 'nullable|string|max:100',
            'code_bagian' => 'nullable|string|max:50',
            'nama_bagian' => 'nullable|string|max:100',
            'nama_sect' => 'nullable|string|max:100',
            'departemen' => 'nullable|string|max:100',
            'division' => 'nullable|string|max:100',
            'line_category' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $inputNrp = trim($request->input('nrp'));

        // 1. Strict check in DB table 'karyawan' to prevent duplicates
        $existingDb = DB::table('karyawan')
            ->whereRaw('LOWER(TRIM(nrp)) = ?', [strtolower($inputNrp)])
            ->first();

        if ($existingDb) {
            $existingName = !empty($existingDb->nama_lengkap) ? $existingDb->nama_lengkap : 'Karyawan Terdaftar';
            return redirect()->back()
                ->with('error', "Maaf, NRP {$inputNrp} sudah ditambahkan / terdaftar dalam sistem (atas nama: '{$existingName}'). NRP tidak boleh dobel, silakan gunakan NRP yang berbeda.")
                ->withInput();
        }

        // 2. Strict check against repository cached records
        $allKaryawan = $this->repository->all('karyawan');
        foreach ($allKaryawan as $k) {
            if (strcasecmp(trim($k['nrp'] ?? ''), $inputNrp) === 0) {
                $existingName = !empty($k['nama_lengkap']) ? $k['nama_lengkap'] : 'Karyawan Terdaftar';
                return redirect()->back()
                    ->with('error', "Maaf, NRP {$inputNrp} sudah ditambahkan / terdaftar dalam sistem (atas nama: '{$existingName}'). NRP tidak boleh dobel, silakan gunakan NRP yang berbeda.")
                    ->withInput();
            }
        }

        $subGol = strtoupper(trim($request->input('sub_gol')));
        $jabatan = strtoupper(trim($request->input('jabatan')));

        $kjInput = trim($request->input('kj', ''));
        if (!$kjInput) {
            if (str_contains($subGol, 'V') && !str_contains($subGol, 'IV')) {
                $kjInput = 'KJ 5';
            } elseif (str_contains($subGol, 'IV')) {
                $kjInput = 'KJ 4';
            } elseif (str_contains($subGol, 'III')) {
                $kjInput = 'KJ 3';
            } else {
                $kjInput = 'KJ 2';
            }
        }
        $kj = \App\Services\ExcelKaryawanReader::normalizeKj($kjInput);

        // Auto-derive golongan from sub_gol if not provided
        $golongan = trim($request->input('golongan', ''));
        if (!$golongan) {
            $golongan = \App\Services\ExcelKaryawanReader::subGolToGolongan($subGol);
        }

        $departemenVal = trim($request->input('departemen') ?? ($user->departemen ?? 'PROD 2'));
        $namaSectVal = strtoupper(trim($request->input('nama_sect') ?? ($request->input('seksi') ?? 'FACTORY 1')));
        $seksiVal = strtoupper(trim($request->input('seksi') ?? $namaSectVal));
        $namaBagianVal = strtoupper(trim($request->input('nama_bagian') ?? ($namaSectVal . ' SEC.')));
        $codeBagianVal = trim($request->input('code_bagian') ?? '1141600000');
        $lineCategoryVal = trim((string)($request->input('line_category') ?? '')) ?: '-';
        $divisionVal = trim((string)($request->input('division') ?? '')) ?: 'PRODUCTION A DIV';

        $hierarchy = $this->resolveEmployeeHierarchy($user, $departemenVal, $namaSectVal, $jabatan);
        $nrpForemanVal = $hierarchy['nrp_foreman'];
        $nrpSectionVal = $hierarchy['nrp_section_head'];
        $nrpDeptVal = $hierarchy['nrp_dept_head'];
        $nrpDivVal = $hierarchy['nrp_div_head'];

        if (empty($nrpDivVal) && $divisionVal !== '') {
            $divHeadRow = DB::table('karyawan')
                ->whereRaw('UPPER(TRIM(division)) = ?', [strtoupper($divisionVal)])
                ->where('level_form', 'DIV_HEAD')
                ->first();
            if ($divHeadRow) {
                $nrpDivVal = $divHeadRow->nrp;
            }
        }

        if ($user->isAdmin() || $user->isSuperadmin() || $user->role === 'hr') {
            $reqForeman = trim((string)$request->input('nrp_foreman', ''));
            if ($reqForeman !== '') {
                $nrpForemanVal = $reqForeman;
                $foremanRow = DB::table('karyawan')->where('nrp', $reqForeman)->first();
                if ($foremanRow) {
                    $nrpSectionVal = $nrpSectionVal ?: $foremanRow->nrp_section_head;
                    $nrpDeptVal = $nrpDeptVal ?: $foremanRow->nrp_dept_head;
                    $nrpDivVal = $nrpDivVal ?: $foremanRow->nrp_div_head;
                }
            }
        }

        $tempKaryawan = [
            'kj' => $kj,
            'sub_gol' => $subGol,
            'jabatan' => $jabatan,
            'nrp_foreman' => $nrpForemanVal,
            'nrp_section_head' => $nrpSectionVal,
            'nrp_dept_head' => $nrpDeptVal,
            'nrp_div_head' => $nrpDivVal,
        ];
        $levelForm = PenilaianController::determineLevelForm($tempKaryawan);

        $newEmployee = [
            'nrp' => trim($request->input('nrp')),
            'nama_lengkap' => strtoupper(trim($request->input('nama_lengkap'))),
            'golongan' => $golongan,
            'sub_gol' => $subGol,
            'kj' => $kj,
            'jabatan' => $jabatan,
            'tgl_masuk' => $request->input('tgl_masuk'),
            'departemen' => $departemenVal,
            'seksi' => $seksiVal,
            'code_bagian' => $codeBagianVal,
            'nama_bagian' => $namaBagianVal,
            'nama_sect' => $namaSectVal,
            'line_category' => $lineCategoryVal,
            'division' => $divisionVal,
            'level_form' => $levelForm,
            'nrp_foreman' => $nrpForemanVal,
            'nrp_section_head' => $nrpSectionVal,
            'nrp_dept_head' => $nrpDeptVal,
            'nrp_div_head' => $nrpDivVal,
        ];

        $this->repository->insert('karyawan', $newEmployee);
        $this->repository->clearCache('karyawan');

        if (!empty($hierarchy['auto_create_doc'])) {
            $this->ensureInitialDocument($newEmployee, $hierarchy['default_status'] ?? 'menunggu_section_head');
        }

        // Sinkronisasi tambah karyawan ke Google Spreadsheet secara real-time
        try {
            \App\Services\GoogleSyncService::syncTables(['karyawan', 'dokumen', 'users', 'audit_log']);
        } catch (\Throwable $e) {
            \App\Services\GoogleSyncService::syncEmployeeRowAsync('upsert', $newEmployee['nrp']);
        }

        $targetRoute = str_contains((string)url()->previous(), '/admin/karyawan') ? 'admin.karyawan' : 'karyawan.index';
        return redirect()->route($targetRoute)->with('success', "Karyawan {$newEmployee['nama_lengkap']} (NRP: {$newEmployee['nrp']}) berhasil ditambahkan!");
    }

    /**
     * Resolve supervisory chain consistently for manual creation and Excel/CSV import.
     * For Admin/Superadmin master uploads, automatically links non-Foreman/non-Leader rows
     * matching an active Section Head without Foreman's (DEPT, SECTION) such as PROD 2 / FACTORY 2.
     */
    private function resolveEmployeeHierarchy(User $actor, string $departemen, string $seksi, string $jabatan): array
    {
        $isActorShNoForeman = $actor->isSectionHead() && !$actor->hasForemanSubordinates();

        if ($actor->isAdmin() || $actor->isSuperadmin() || $actor->isHr()) {
            $deptUpper = strtoupper(trim($departemen));
            $sectUpper = strtoupper(trim($seksi));

            if (!\App\Services\ExcelKaryawanReader::isExcludedPosition($jabatan)) {
                $shWithoutForeman = DB::table('app_users')
                    ->where('role', 'section_head')
                    ->whereIn('nrp', ['99999'])
                    ->first();

                if ($shWithoutForeman) {
                    $shRow = DB::table('karyawan')->where('nrp', $shWithoutForeman->nrp)->first();
                    $shDept = strtoupper(trim((string)($shRow->departemen ?? $shWithoutForeman->departemen ?? 'PROD 2')));
                    $shSect = strtoupper(trim((string)($shRow->seksi ?? $shRow->nama_sect ?? 'FACTORY 3')));

                    if ($deptUpper === $shDept && $sectUpper === $shSect) {
                        return [
                            'nrp_foreman' => null,
                            'nrp_section_head' => $shWithoutForeman->nrp,
                            'nrp_dept_head' => $shRow->nrp_dept_head ?? '31',
                            'nrp_div_head' => $shRow->nrp_div_head ?? '11',
                            'auto_create_doc' => true,
                        ];
                    }
                }
            }

            $matchedDhNrp = null;
            $dhUsers = DB::table('app_users')->whereIn('role', ['department_head', 'dept_head'])->get();
            $targetDeptNorm = User::normalizeDepartmentName($departemen);
            foreach ($dhUsers as $dhu) {
                if (User::normalizeDepartmentName($dhu->departemen ?? '') === $targetDeptNorm) {
                    $matchedDhNrp = $dhu->nrp;
                    break;
                }
            }

            $posUpper = strtoupper(trim($jabatan));
            $isUpperHead = str_contains($posUpper, 'DEPT HEAD')
                || str_contains($posUpper, 'DEPARTMENT HEAD')
                || str_contains($posUpper, 'DEPT. HEAD')
                || str_contains($posUpper, 'DIV HEAD')
                || str_contains($posUpper, 'DIVISION HEAD');
            $isShPos = str_contains($posUpper, 'SECTION HEAD') || str_contains($posUpper, 'SECT HEAD');

            return [
                'nrp_foreman' => null,
                'nrp_section_head' => null,
                'nrp_dept_head' => $matchedDhNrp,
                'nrp_div_head' => null,
                'auto_create_doc' => !$isUpperHead,
                'default_status' => $isShPos ? 'menunggu_section_head' : 'menunggu_foreman',
            ];
        }

        if ($isActorShNoForeman) {
            $nrpForemanVal = null;
            $nrpSectionVal = $actor->nrp;
        } else {
            $nrpForemanVal = $actor->isForeman() ? $actor->nrp : ($actor->nrp_foreman ?? null);
            $nrpSectionVal = $actor->isSectionHead() ? $actor->nrp : ($actor->nrp_section_head ?? null);
        }

        $nrpDeptVal = $actor->isDepartmentHead() ? $actor->nrp : ($actor->nrp_dept_head ?? null);
        $nrpDivVal = $actor->isDivisionHead() ? $actor->nrp : ($actor->nrp_div_head ?? null);

        if (empty($nrpDeptVal) && !empty($nrpSectionVal)) {
            $shKaryawan = DB::table('karyawan')->where('nrp', $nrpSectionVal)->first();
            if ($shKaryawan && !empty($shKaryawan->nrp_dept_head)) {
                $nrpDeptVal = $shKaryawan->nrp_dept_head;
            }
        }
        if (empty($nrpDeptVal) && !empty($actor->departemen)) {
            $dhUser = DB::table('app_users')->whereIn('role', ['department_head', 'dept_head'])->where('departemen', $actor->departemen)->first();
            if ($dhUser) $nrpDeptVal = $dhUser->nrp;
        }
        if (empty($nrpDeptVal) && !empty($departemen)) {
            $dhUser = DB::table('app_users')->whereIn('role', ['department_head', 'dept_head'])->where('departemen', $departemen)->first();
            if ($dhUser) $nrpDeptVal = $dhUser->nrp;
        }

        if (empty($nrpDivVal) && !empty($nrpSectionVal)) {
            $shKaryawan = DB::table('karyawan')->where('nrp', $nrpSectionVal)->first();
            if ($shKaryawan && !empty($shKaryawan->nrp_div_head)) {
                $nrpDivVal = $shKaryawan->nrp_div_head;
            }
        }
        if (empty($nrpDivVal) && !empty($actor->departemen)) {
            $divUser = DB::table('app_users')->whereIn('role', ['division_head', 'div_head'])->where('departemen', $actor->departemen)->first();
            if ($divUser) $nrpDivVal = $divUser->nrp;
        }
        if (empty($nrpDivVal) && !empty($departemen)) {
            $divUser = DB::table('app_users')->whereIn('role', ['division_head', 'div_head'])->where('departemen', $departemen)->first();
            if ($divUser) $nrpDivVal = $divUser->nrp;
        }

        return [
            'nrp_foreman' => $nrpForemanVal,
            'nrp_section_head' => $nrpSectionVal,
            'nrp_dept_head' => $nrpDeptVal,
            'nrp_div_head' => $nrpDivVal,
            'auto_create_doc' => $isActorShNoForeman,
        ];
    }

    private function ensureInitialDocument(array $employee, string $status = 'menunggu_section_head'): void
    {
        $exists = DB::table('dokumen')->where('nrp', $employee['nrp'])->exists();
        if ($exists) {
            return;
        }
        $activePeriod = DB::table('periode')->where('status', 'aktif')->first() ?? (object)['id' => 1];
        $this->repository->insert('dokumen', [
            'periode_id' => $activePeriod->id ?? 1,
            'nrp' => $employee['nrp'],
            'kode_form' => PenilaianController::determineKodeForm($employee),
            'status' => $status,
            'kode_verifikasi' => 'VERIF-' . strtoupper(Str::random(10)),
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Download CSV Template for Import matching the exact 11 columns.
     */
    public function downloadTemplate()
    {
        $filename = "Template_Data_Karyawan_Bawahan.csv";

        return Response::streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

            // Exact 11 headers (semicolon delimiter for neat Excel columns)
            fputcsv($handle, [
                'NO',
                'NRP',
                'NAMA',
                'CODE BAGIAN',
                'NAMA BAGIAN',
                'NAMA SECT.',
                'NAMA DEPT.',
                'JABATAN',
                'START WORK',
                'KJ',
                'Sub Gol',
            ], ';');

            fputcsv($handle, [
                '1',
                '9001',
                'BUDI SANTOSO',
                '1141600000',
                'FACTORY 1 SEC.',
                'FACTORY 1',
                'PROD 2',
                'OPERATOR MACHINING',
                '2022-01-10',
                'KJ 2',
                'IIB',
            ], ';');

            fputcsv($handle, [
                '2',
                '9002',
                'SITI RAHMAWATI',
                '1141600000',
                'FACTORY 1 SEC.',
                'FACTORY 1',
                'PROD 2',
                'OPERATOR QC',
                '2023-03-15',
                'KJ 2',
                'IIA',
            ], ';');

            fputcsv($handle, [
                '3',
                '9003',
                'HENDRA WIJAYA',
                '1141600000',
                'FACTORY 1 SEC.',
                'FACTORY 1',
                'PROD 2',
                'LEADER MACHINING',
                '2019-07-01',
                'KJ 3',
                'IIIA',
            ], ';');

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Export Subordinate Employees matching the exact web table columns and order in a formatted .xlsx file.
     */
    public function export(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $allKaryawan = $this->repository->all('karyawan');
        $baseKaryawan = self::scopeByUser($user, $allKaryawan);

        $filtered = self::filterEmployees($baseKaryawan, [
            'search' => trim($request->input('q', '')),
            'departemen' => trim($request->input('departemen', '')),
            'seksi' => trim($request->input('seksi', '')),
            'jabatan' => trim($request->input('jabatan', '')),
            'nama_bagian' => trim($request->input('nama_bagian', '')),
            'golongan' => trim($request->input('golongan', '')),
        ]);

        usort($filtered, fn($a, $b) => strcmp($a['nrp'] ?? '', $b['nrp'] ?? ''));

        $useExcelCols = $user->isForeman() || ($user->isSectionHead() && !$user->hasForemanSubordinates());

        if ($useExcelCols) {
            $headers = [
                'NO',
                'NRP',
                'NAMA LENGKAP',
                'CODE BAGIAN',
                'NAMA BAGIAN',
                'LINE CATEGORY',
                'POSITION',
                'DIVISION',
                'DEPARTMENT',
                'SECTION',
                'KJ',
                'GOLONGAN',
                'START WORK',
            ];
            $centeredCols = [0, 1, 3, 5, 7, 8, 9, 10, 11, 12];
        } else {
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
            ];
            $centeredCols = [0, 1, 3, 5, 6, 7, 9, 10, 11];
        }

        $exportRows = [];
        $no = 1;
        foreach ($filtered as $k) {
            $row = self::enrichEmployeeDisplayRow($k);

            if ($useExcelCols) {
                $exportRows[] = [
                    $no++,
                    (string)($row['nrp'] ?? ''),
                    (string)($row['nama_lengkap'] ?? ''),
                    (string)$row['code_bagian'],
                    (string)$row['nama_bagian'],
                    (string)$row['line_category'],
                    strtoupper((string)($row['jabatan'] ?? '')),
                    (string)$row['division'],
                    (string)($row['departemen'] ?? ''),
                    (string)$row['nama_sect'],
                    (string)$row['kj'],
                    (string)$row['sub_gol'],
                    (string)$row['start_work'],
                ];
            } else {
                $exportRows[] = [
                    $no++,
                    (string)($row['nrp'] ?? ''),
                    (string)($row['nama_lengkap'] ?? ''),
                    (string)$row['code_bagian'],
                    (string)$row['nama_bagian'],
                    (string)$row['nama_sect'],
                    (string)$row['departemen'] ?? '',
                    (string)$row['division'],
                    strtoupper((string)($row['jabatan'] ?? '')),
                    (string)$row['start_work'],
                    (string)$row['kj'],
                    (string)$row['sub_gol'],
                ];
            }
        }

        $xlsxBinary = ExcelKaryawanExporter::buildXlsxBinary('Data Karyawan', $headers, $exportRows, $centeredCols);
        $filename = 'Data_Karyawan_Bawahan_' . date('Ymd_His') . '.xlsx';

        return Response::make($xlsxBinary, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => (string)strlen($xlsxBinary),
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Import Subordinates via CSV/Excel with auto-detection of delimiters & headers.
     */
    public function import(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user && ($user->isForeman() || (!$user->isAdmin() && !$user->isHr() && !$user->isSuperadmin()))) {
            return redirect()->back()->with('error', 'Akses ditolak: Foreman hanya memiliki izin untuk menilai kinerja bawahan, tidak dapat mengimpor data karyawan.');
        }

        if (!$request->hasFile('sheet_file') && !$request->hasFile('file')) {
            $request->validate(['sheet_file' => 'required|file']);
        }

        $file = $request->file('sheet_file') ?? $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());

        if (!in_array($ext, ['csv', 'txt', 'xlsx'])) {
            return redirect()->back()->with('error', 'Format file tidak didukung. Mohon unggah file format .CSV atau .XLSX.');
        }

        $syncExisting = $request->boolean('sync_existing');
        $imported = 0;
        $updatedExisting = 0;
        $skippedDuplicates = [];
        $existingNrps = [];
        
        // Load all existing NRPs from DB table 'karyawan'
        $dbRows = DB::table('karyawan')->select('nrp', 'nama_lengkap')->get();
        foreach ($dbRows as $row) {
            if (!empty($row->nrp)) {
                $existingNrps[strtolower(trim($row->nrp))] = $row->nama_lengkap ?? '-';
            }
        }
        
        // Also check repository
        $allKaryawan = $this->repository->all('karyawan');
        foreach ($allKaryawan as $k) {
            if (!empty($k['nrp'])) {
                $kKey = strtolower(trim($k['nrp']));
                if (!isset($existingNrps[$kKey])) {
                    $existingNrps[$kKey] = $k['nama_lengkap'] ?? '-';
                }
            }
        }

        $isSectionHeadWithoutForeman = $user->isSectionHead() && !$user->hasForemanSubordinates();

        $parsedRows = [];
        if ($ext === 'xlsx') {
            $xlsxRows = \App\Services\ExcelKaryawanReader::parseXlsx($file->getRealPath());
            foreach ($xlsxRows as $xr) {
                $parsedRows[] = [
                    'nrp' => $xr['NRP'] ?? ($xr['EMPLOYEENO'] ?? ($xr['NIK'] ?? '')),
                    'nama_lengkap' => $xr['NAMA LENGKAP'] ?? ($xr['FULLNAME'] ?? ($xr['NAMA'] ?? '')),
                    'code_bagian' => $xr['CODE BAGIAN'] ?? ($xr['ORGSTRUCTURECODE'] ?? ($xr['KODE BAGIAN'] ?? '')),
                    'nama_bagian' => $xr['NAMA BAGIAN'] ?? ($xr['ORGSTRUCTURENAME'] ?? ''),
                    'line_category' => $xr['LINE CATEGORY'] ?? ($xr['LINE'] ?? ''),
                    'jabatan' => $xr['POSITION'] ?? ($xr['JABATAN'] ?? ''),
                    'division' => $xr['DIVISION'] ?? ($xr['DIVISI'] ?? ''),
                    'departemen' => $xr['DEPARTMENT'] ?? ($xr['DEPT'] ?? ($xr['DEPARTEMEN'] ?? '')),
                    'nama_sect' => $xr['SECTION'] ?? ($xr['NAMA SECT'] ?? ($xr['SEKSI'] ?? '')),
                    'sub_gol' => $xr['GOLONGAN'] ?? ($xr['JOBCLASSCODE'] ?? ($xr['SUB GOL'] ?? '')),
                    'kj' => $xr['KJ'] ?? ($xr['JOBLEVELCODE'] ?? ''),
                    'tgl_masuk' => $xr['START WORK'] ?? ($xr['JOINDATE'] ?? ($xr['TANGGAL MASUK'] ?? '')),
                    'golongan' => $xr['GOLONGAN'] ?? ($xr['JOBCLASSCODE'] ?? ($xr['SUB GOL'] ?? '')),
                ];
            }
        } elseif (($handle = fopen($file->getRealPath(), 'r')) !== false) {
            $firstLine = fgets($handle);
            if ($firstLine !== false) {
                if (str_starts_with($firstLine, "\xEF\xBB\xBF")) {
                    $firstLine = substr($firstLine, 3);
                }

                $delimiter = ';';
                if (preg_match('/^sep=([;,|\t])/i', trim($firstLine), $m)) {
                    $delimiter = $m[1];
                    $firstLine = fgets($handle);
                } else {
                    $countSemi = substr_count($firstLine, ';');
                    $countComma = substr_count($firstLine, ',');
                    $countTab = substr_count($firstLine, "\t");

                    if ($countSemi >= $countComma && $countSemi >= $countTab) {
                        $delimiter = ';';
                    } elseif ($countTab > $countComma) {
                        $delimiter = "\t";
                    } else {
                        $delimiter = ',';
                    }
                }

                $rawHeaders = str_getcsv(trim((string)$firstLine), $delimiter);
                $headerMap = [];
                foreach ($rawHeaders as $idx => $h) {
                    $norm = strtolower(trim((string)preg_replace('/[^a-zA-Z0-9]/', '', (string)$h)));
                    if (in_array($norm, ['no', 'nomor', 'aksi', 'action'])) {
                        continue;
                    } elseif (in_array($norm, ['nrp', 'nik', 'id', 'employeeno'])) {
                        $headerMap['nrp'] = $idx;
                    } elseif (in_array($norm, ['nama', 'namalengkap', 'namakaryawan', 'name', 'fullname'])) {
                        $headerMap['nama_lengkap'] = $idx;
                    } elseif (in_array($norm, ['codebagian', 'kodebagian', 'orgstructurecode'])) {
                        $headerMap['code_bagian'] = $idx;
                    } elseif (in_array($norm, ['namabagian', 'bagian', 'orgstructurename'])) {
                        $headerMap['nama_bagian'] = $idx;
                    } elseif (in_array($norm, ['linecategory', 'line'])) {
                        $headerMap['line_category'] = $idx;
                    } elseif (in_array($norm, ['division', 'divisi'])) {
                        $headerMap['division'] = $idx;
                    } elseif (in_array($norm, ['namasect', 'namasection', 'sect', 'section', 'seksi'])) {
                        $headerMap['nama_sect'] = $idx;
                    } elseif (in_array($norm, ['namadept', 'namadepartemen', 'departemen', 'dept', 'department'])) {
                        $headerMap['departemen'] = $idx;
                    } elseif (in_array($norm, ['jabatan', 'position', 'posisi'])) {
                        $headerMap['jabatan'] = $idx;
                    } elseif (in_array($norm, ['startwork', 'tglmasuk', 'tanggalmasuk', 'masuk', 'joindate'])) {
                        $headerMap['tgl_masuk'] = $idx;
                    } elseif (in_array($norm, ['kj', 'kategorijabatan', 'joblevelcode'])) {
                        $headerMap['kj'] = $idx;
                    } elseif (in_array($norm, ['subgol', 'subgolongan', 'jobclasscode'])) {
                        $headerMap['sub_gol'] = $idx;
                    } elseif (in_array($norm, ['golongan', 'gol'])) {
                        $headerMap['golongan'] = $idx;
                    } elseif (in_array($norm, ['nrpforeman', 'foreman'])) {
                        $headerMap['nrp_foreman'] = $idx;
                    }
                }

                while (($row = fgetcsv($handle, 2000, $delimiter)) !== false) {
                    if (empty($row) || count($row) < 2) {
                        continue;
                    }
                    $parsedRows[] = [
                        'nrp' => isset($headerMap['nrp']) ? ($row[$headerMap['nrp']] ?? '') : '',
                        'nama_lengkap' => isset($headerMap['nama_lengkap']) ? ($row[$headerMap['nama_lengkap']] ?? '') : '',
                        'code_bagian' => isset($headerMap['code_bagian']) ? ($row[$headerMap['code_bagian']] ?? '') : '',
                        'nama_bagian' => isset($headerMap['nama_bagian']) ? ($row[$headerMap['nama_bagian']] ?? '') : '',
                        'line_category' => isset($headerMap['line_category']) ? ($row[$headerMap['line_category']] ?? '') : '',
                        'division' => isset($headerMap['division']) ? ($row[$headerMap['division']] ?? '') : '',
                        'nama_sect' => isset($headerMap['nama_sect']) ? ($row[$headerMap['nama_sect']] ?? '') : '',
                        'departemen' => isset($headerMap['departemen']) ? ($row[$headerMap['departemen']] ?? '') : '',
                        'jabatan' => isset($headerMap['jabatan']) ? ($row[$headerMap['jabatan']] ?? '') : '',
                        'tgl_masuk' => isset($headerMap['tgl_masuk']) ? ($row[$headerMap['tgl_masuk']] ?? '') : '',
                        'kj' => isset($headerMap['kj']) ? ($row[$headerMap['kj']] ?? '') : '',
                        'sub_gol' => isset($headerMap['sub_gol']) ? ($row[$headerMap['sub_gol']] ?? '') : '',
                        'golongan' => isset($headerMap['golongan']) ? ($row[$headerMap['golongan']] ?? '') : '',
                        'nrp_foreman' => isset($headerMap['nrp_foreman']) ? ($row[$headerMap['nrp_foreman']] ?? '') : '',
                    ];
                }
            }
            fclose($handle);
        }

        // Pass 1: Build supervisory lookup maps across existing employees + uploaded sheet
        $divHeadByDiv = [];
        $deptHeadByDivDept = [];
        $deptHeadByDept = [];
        $shByDivDeptSect = [];
        $foremanByCat = [];

        $indexLeadership = function (string $nrpVal, string $posRaw, string $kjVal, string $divVal, string $deptVal, string $sectVal, string $catVal) use (&$divHeadByDiv, &$deptHeadByDivDept, &$deptHeadByDept, &$shByDivDeptSect, &$foremanByCat) {
            if ($nrpVal === '') {
                return;
            }
            $lvl = \App\Services\ExcelKaryawanReader::kjToLevelForm($kjVal, $posRaw);
            $divU = strtoupper(trim($divVal));
            $deptU = strtoupper(trim($deptVal));
            $sectU = strtoupper(trim($sectVal));
            $catU = strtoupper(trim($catVal));

            if ($lvl === 'DIV_HEAD' && $divU !== '' && $divU !== '-') {
                $divHeadByDiv[$divU] = $nrpVal;
            } elseif ($lvl === 'DEPT_HEAD' && $deptU !== '' && $deptU !== '-') {
                $deptHeadByDept[$deptU] = $nrpVal;
                if ($divU !== '' && $divU !== '-') {
                    $deptHeadByDivDept["{$divU}|{$deptU}"] = $nrpVal;
                }
            } elseif ($lvl === 'SECTION_HEAD' && $sectU !== '' && $sectU !== '-' && $deptU !== '' && $deptU !== '-') {
                $divKey = ($divU !== '' && $divU !== '-') ? $divU : 'PRODUCTION A DIV';
                $shByDivDeptSect["{$divKey}|{$deptU}|{$sectU}"] = $nrpVal;
            } elseif ($lvl === 'FOREMAN' && $catU !== '' && $catU !== '-') {
                $foremanByCat[$catU] = $nrpVal;
            }
        };

        foreach ($allKaryawan as $ek) {
            $indexLeadership(
                trim((string)($ek['nrp'] ?? '')),
                (string)($ek['jabatan'] ?? 'OPERATOR'),
                (string)($ek['kj'] ?? 'KJ 2'),
                (string)($ek['division'] ?? 'PRODUCTION A DIV'),
                (string)($ek['departemen'] ?? ''),
                (string)($ek['nama_sect'] ?? ($ek['seksi'] ?? '')),
                (string)($ek['line_category'] ?? '')
            );
        }
        foreach ($parsedRows as $pr) {
            $rawKjTmp = trim((string)($pr['kj'] ?? ''));
            $normKjTmp = $rawKjTmp !== '' ? \App\Services\ExcelKaryawanReader::normalizeKj($rawKjTmp) : 'KJ 2';
            $indexLeadership(
                trim((string)($pr['nrp'] ?? '')),
                (string)($pr['jabatan'] ?? 'OPERATOR'),
                $normKjTmp,
                (string)($pr['division'] ?? 'PRODUCTION A DIV'),
                (string)($pr['departemen'] ?? ''),
                (string)($pr['nama_sect'] ?? ''),
                (string)($pr['line_category'] ?? '')
            );
        }

        foreach ($parsedRows as $rawItem) {
            $nrp = trim((string)($rawItem['nrp'] ?? ''));
            if ($nrp === '') {
                continue;
            }

            $lowerNrp = strtolower($nrp);
            $isDuplicate = isset($existingNrps[$lowerNrp]);
            if ($isDuplicate && !$syncExisting) {
                $existingPerson = $existingNrps[$lowerNrp];
                $skippedDuplicates[] = "{$nrp} (" . ($existingPerson !== '-' ? $existingPerson : 'Terdaftar') . ")";
                continue;
            }

            $jabatan = strtoupper(trim((string)($rawItem['jabatan'] ?? ''))) ?: 'OPERATOR';
            if ($isSectionHeadWithoutForeman && \App\Services\ExcelKaryawanReader::isExcludedPosition($jabatan)) {
                continue;
            }

            $namaLengkap = strtoupper(trim((string)($rawItem['nama_lengkap'] ?? ''))) ?: ('KARYAWAN ' . $nrp);
            $subGol = strtoupper(trim((string)($rawItem['sub_gol'] ?? ''))) ?: 'IIB';
            $rawKj = strtoupper(trim((string)($rawItem['kj'] ?? '')));
            if ($rawKj !== '') {
                $kj = \App\Services\ExcelKaryawanReader::normalizeKj($rawKj);
            } elseif (str_contains($subGol, 'V') && !str_contains($subGol, 'IV')) {
                $kj = 'KJ 5';
            } elseif (str_contains($subGol, 'IV')) {
                $kj = 'KJ 4';
            } elseif (str_contains($subGol, 'III')) {
                $kj = 'KJ 3';
            } else {
                $kj = 'KJ 2';
            }

            $golongan = trim((string)($rawItem['golongan'] ?? ''));
            if ($golongan === '') {
                $golongan = \App\Services\ExcelKaryawanReader::subGolToGolongan($subGol);
            }

            $levelByPos = \App\Services\ExcelKaryawanReader::kjToLevelForm($kj, $jabatan);
            $tglMasuk = \App\Services\ExcelKaryawanReader::excelDateToYmd($rawItem['tgl_masuk'] ?? null);
            $codeBagian = trim((string)($rawItem['code_bagian'] ?? '')) ?: '1141600000';
            $rawDept = trim((string)($rawItem['departemen'] ?? ''));
            $dept = $rawDept !== '' ? $rawDept : ($levelByPos === 'DIV_HEAD' ? '-' : ($user->departemen ?? 'PROD 2'));
            $rawSect = trim((string)($rawItem['nama_sect'] ?? ''));
            $namaSect = $rawSect !== '' ? strtoupper($rawSect) : (in_array($levelByPos, ['DIV_HEAD', 'DEPT_HEAD'], true) ? '-' : 'FACTORY 1');
            $namaBagian = strtoupper(trim((string)($rawItem['nama_bagian'] ?? ''))) ?: (($namaSect !== '' && $namaSect !== '-') ? ($namaSect . ' SEC.') : '-');
            $lineCategory = trim((string)($rawItem['line_category'] ?? '')) ?: '-';
            $division = trim((string)($rawItem['division'] ?? '')) ?: 'PRODUCTION A DIV';

            $hierarchy = $this->resolveEmployeeHierarchy($user, $dept, $namaSect, $jabatan);
            $nrpForemanVal = $hierarchy['nrp_foreman'];
            $nrpSectionVal = $hierarchy['nrp_section_head'];
            $nrpDeptVal = $hierarchy['nrp_dept_head'];
            $nrpDivVal = $hierarchy['nrp_div_head'];

            $divU = strtoupper($division);
            $deptU = strtoupper($dept);
            $sectU = strtoupper($namaSect);
            $catU = strtoupper($lineCategory);

            if ($user->isAdmin() || $user->isSuperadmin() || $user->role === 'hr') {
                $resolvedDivHead = $divHeadByDiv[$divU] ?? $nrpDivVal;
                $resolvedDeptHead = $deptHeadByDivDept["{$divU}|{$deptU}"] ?? ($deptHeadByDept[$deptU] ?? $nrpDeptVal);
                $resolvedSectHead = $shByDivDeptSect["{$divU}|{$deptU}|{$sectU}"] ?? $nrpSectionVal;

                if ($levelByPos === 'DIV_HEAD') {
                    $nrpForemanVal = null;
                    $nrpSectionVal = null;
                    $nrpDeptVal = null;
                    $nrpDivVal = $nrp;
                } elseif ($levelByPos === 'DEPT_HEAD') {
                    $nrpForemanVal = null;
                    $nrpSectionVal = null;
                    $nrpDeptVal = $nrp;
                    $nrpDivVal = $resolvedDivHead;
                } elseif ($levelByPos === 'SECTION_HEAD') {
                    $nrpForemanVal = null;
                    $nrpSectionVal = $nrp;
                    $nrpDeptVal = $resolvedDeptHead;
                    $nrpDivVal = $resolvedDivHead;
                } elseif ($levelByPos === 'FOREMAN') {
                    $nrpForemanVal = null;
                    $nrpSectionVal = $resolvedSectHead;
                    $nrpDeptVal = $resolvedDeptHead;
                    $nrpDivVal = $resolvedDivHead;
                } else {
                    if ($catU !== '' && $catU !== '-' && isset($foremanByCat[$catU]) && empty($nrpSectionVal)) {
                        $nrpForemanVal = $foremanByCat[$catU];
                    }
                    $nrpSectionVal = $nrpSectionVal ?: $resolvedSectHead;
                    $nrpDeptVal = $nrpDeptVal ?: $resolvedDeptHead;
                    $nrpDivVal = $nrpDivVal ?: $resolvedDivHead;
                }

                $impForeman = trim((string)($rawItem['nrp_foreman'] ?? ''));
                if ($impForeman !== '') {
                    $nrpForemanVal = $impForeman;
                    $foremanRow = DB::table('karyawan')->where('nrp', $impForeman)->first();
                    if ($foremanRow) {
                        $nrpSectionVal = $nrpSectionVal ?: $foremanRow->nrp_section_head;
                        $nrpDeptVal = $nrpDeptVal ?: $foremanRow->nrp_dept_head;
                        $nrpDivVal = $nrpDivVal ?: $foremanRow->nrp_div_head;
                    }
                }
            }

            $levelForm = PenilaianController::determineLevelForm([
                'kj' => $kj,
                'sub_gol' => $subGol,
                'jabatan' => $jabatan,
                'nrp_foreman' => $nrpForemanVal,
                'nrp_section_head' => $nrpSectionVal,
                'nrp_dept_head' => $nrpDeptVal,
                'nrp_div_head' => $nrpDivVal,
            ]);

            $employeeRecord = [
                'nama_lengkap' => $namaLengkap,
                'golongan' => $golongan,
                'sub_gol' => $subGol,
                'kj' => $kj,
                'jabatan' => $jabatan,
                'tgl_masuk' => $tglMasuk,
                'departemen' => $dept,
                'seksi' => $namaSect,
                'code_bagian' => $codeBagian,
                'nama_bagian' => $namaBagian,
                'nama_sect' => $namaSect,
                'line_category' => $lineCategory,
                'division' => $division,
                'level_form' => $levelForm,
                'nrp_foreman' => $nrpForemanVal,
                'nrp_section_head' => $nrpSectionVal,
                'nrp_dept_head' => $nrpDeptVal,
                'nrp_div_head' => $nrpDivVal,
            ];

            if ($isDuplicate && $syncExisting) {
                $this->repository->update('karyawan', 'nrp', $nrp, $employeeRecord);
                $updatedExisting++;
            } else {
                $newEmployee = array_merge(['nrp' => $nrp], $employeeRecord);
                $this->repository->insert('karyawan', $newEmployee);
                if (!empty($hierarchy['auto_create_doc'])) {
                    $this->ensureInitialDocument($newEmployee, $hierarchy['default_status'] ?? 'menunggu_section_head');
                }
                $existingNrps[$lowerNrp] = $namaLengkap;
                $imported++;
            }
        }

        $msgDuplicates = '';
        if (count($skippedDuplicates) > 0) {
            $preview = implode(', ', array_slice($skippedDuplicates, 0, 5));
            if (count($skippedDuplicates) > 5) {
                $preview .= ' dan ' . (count($skippedDuplicates) - 5) . ' lainnya';
            }
            $msgDuplicates = " (Catatan: " . count($skippedDuplicates) . " baris dilewati karena NRP sudah terdaftar: {$preview}).";
        }

        $prevUrl = (string)url()->previous();
        $targetRoute = 'karyawan.index';
        if (str_contains($prevUrl, '/admin/manajemen-akun') || str_contains($prevUrl, '/superadmin/users')) {
            $targetRoute = 'admin.users';
        } elseif (str_contains($prevUrl, '/admin/karyawan')) {
            $targetRoute = 'admin.karyawan';
        }

        if ($imported > 0 || $updatedExisting > 0) {
            $this->repository->clearCache('karyawan');
            $this->repository->clearCache('dokumen');
            \Illuminate\Support\Facades\Cache::flush();

            // Automatically create / sync leadership accounts (Foreman, Section Head, Dept Head, Div Head)
            $syncRes = \App\Services\AccountSyncService::syncLeadershipAccountsFromKaryawan();

            // Sinkronisasi otomatis ke Google Spreadsheet secara real-time
            \App\Services\GoogleSyncService::syncTables(['karyawan', 'dokumen', 'users']);

            $parts = [];
            if ($imported > 0) {
                $parts[] = "{$imported} data baru ditambahkan";
            }
            if ($updatedExisting > 0) {
                $parts[] = "{$updatedExisting} data diperbarui";
            }
            if (($syncRes['total'] ?? 0) > 0) {
                $parts[] = "{$syncRes['total']} akun pimpinan otomatis disinkronkan";
            }
            return redirect()->route($targetRoute)->with('success', "Berhasil mengimpor sheet karyawan (" . implode(', ', $parts) . ")!{$msgDuplicates}");
        }

        return redirect()->route($targetRoute)->with('error', "Tidak ada data baru yang diimpor. Semua NRP sudah pernah ditambahkan dalam sistem (NRP tidak boleh dobel).{$msgDuplicates}");
    }

    /**
     * Delete employee by NRP.
     */
    public function destroy(Request $request, string $nrp)
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user && ($user->isForeman() || (!$user->isAdmin() && !$user->isHr() && !$user->isSuperadmin()))) {
            return redirect()->back()->with('error', 'Akses ditolak: Foreman hanya memiliki izin untuk menilai kinerja bawahan, tidak dapat menghapus data karyawan.');
        }

        $karyawan = $this->repository->find('karyawan', 'nrp', $nrp);
        if (!$karyawan) {
            return redirect()->back()->with('error', "Karyawan dengan NRP {$nrp} tidak ditemukan.");
        }

        $nama = $karyawan['nama_lengkap'] ?? $nrp;

        // Clean up related documents, scores, indicators, signatures directly in DB (fast & atomic < 5ms)
        try {
            $docIds = DB::table('dokumen')->where('nrp', $nrp)->pluck('id')->toArray();
            if (!empty($docIds)) {
                if (Schema::hasTable('nilai')) DB::table('nilai')->whereIn('dokumen_id', $docIds)->delete();
                if (Schema::hasTable('indikator')) DB::table('indikator')->whereIn('dokumen_id', $docIds)->delete();
                if (Schema::hasTable('tanda_tangan')) DB::table('tanda_tangan')->whereIn('dokumen_id', $docIds)->delete();
                if (Schema::hasTable('signature')) DB::table('signature')->whereIn('dokumen_id', $docIds)->delete();
                if (Schema::hasTable('history_status')) DB::table('history_status')->whereIn('dokumen_id', $docIds)->delete();
                DB::table('dokumen')->where('nrp', $nrp)->delete();
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Notice on cascading delete documents for NRP {$nrp}: " . $e->getMessage());
        }

        // Direct delete employee record
        DB::table('karyawan')->where('nrp', $nrp)->delete();

        // Flush all relevant caches completely
        $this->repository->clearCache('karyawan');
        $this->repository->clearCache('dokumen');
        $this->repository->clearCache('nilai');
        $this->repository->clearCache('indikator');
        $this->repository->clearCache('tanda_tangan');
        \Illuminate\Support\Facades\Cache::forget('sheet_all_karyawan');
        \Illuminate\Support\Facades\Cache::forget('sheet_all_dokumen');

        // Sinkronisasi hapus otomatis ke Google Spreadsheet secara instan & non-blocking (cepat tanpa delay)
        \App\Services\GoogleSyncService::syncEmployeeRowAsync('delete', $nrp);

        return redirect()->back()->with('success', "Data karyawan {$nama} (NRP: {$nrp}) berhasil dihapus!");
    }

    /**
     * Bulk delete employees: All employees or Filtered employees.
     */
    public function bulkDestroy(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user && ($user->isForeman() || (!$user->isAdmin() && !$user->isHr() && !$user->isSuperadmin()))) {
            return redirect()->back()->with('error', 'Akses ditolak: Hanya Admin dan Super Admin yang berwenang menghapus data karyawan secara massal.');
        }

        $scope = strtolower(trim((string)$request->input('scope', 'filtered')));
        $filterDivisi = trim((string)$request->input('divisi', ''));
        $filterDept = trim((string)$request->input('departemen', ''));
        $filterSeksi = trim((string)$request->input('seksi', ''));
        $filterGol = trim((string)$request->input('golongan', ''));
        $filterAtasan = trim((string)$request->input('atasan', ''));
        $search = trim((string)$request->input('q', ''));
        $selectedNrps = $request->input('selected_nrps', []);

        $targetNrps = [];
        $scopeDescription = '';

        if (!empty($selectedNrps) && is_array($selectedNrps)) {
            $targetNrps = array_values(array_filter(array_map('strval', $selectedNrps)));
            $scopeDescription = count($targetNrps) . ' karyawan terpilih';
        } else {
            $query = DB::table('karyawan');

            if ($scope !== 'all') {
                // Apply Search
                if ($search !== '') {
                    $query->where(function ($q) use ($search) {
                        $q->where('nama_lengkap', 'like', "%{$search}%")
                          ->orWhere('nrp', 'like', "%{$search}%")
                          ->orWhere('jabatan', 'like', "%{$search}%")
                          ->orWhere('departemen', 'like', "%{$search}%")
                          ->orWhere('division', 'like', "%{$search}%");
                    });
                }

                // Apply Division
                if ($filterDivisi !== '') {
                    $query->whereRaw('LOWER(TRIM(division)) = ?', [strtolower(trim($filterDivisi))]);
                }

                // Apply Department
                if ($filterDept !== '') {
                    $query->whereRaw('LOWER(TRIM(departemen)) = ?', [strtolower(trim($filterDept))]);
                }

                // Apply Section
                if ($filterSeksi !== '') {
                    $query->where(function ($q) use ($filterSeksi) {
                        $q->whereRaw('LOWER(TRIM(nama_sect)) = ?', [strtolower(trim($filterSeksi))])
                          ->orWhereRaw('LOWER(TRIM(seksi)) = ?', [strtolower(trim($filterSeksi))]);
                    });
                }

                // Apply Golongan / KJ
                if ($filterGol !== '') {
                    $normalizedGol = \App\Services\ExcelKaryawanReader::normalizeKj($filterGol);
                    $query->where(function ($q) use ($filterGol, $normalizedGol) {
                        $q->where('kj', $filterGol)
                          ->orWhere('kj', $normalizedGol)
                          ->orWhere('sub_gol', $filterGol)
                          ->orWhere('golongan', $filterGol);
                    });
                }

                // Apply Atasan
                if ($filterAtasan !== '') {
                    if ($filterAtasan === 'foreman') {
                        $query->whereNotNull('nrp_foreman')->where('nrp_foreman', '!=', '');
                    } elseif ($filterAtasan === 'section_head') {
                        $query->whereNotNull('nrp_section_head')->where('nrp_section_head', '!=', '');
                    } elseif ($filterAtasan === 'dept_head') {
                        $query->whereNotNull('nrp_dept_head')->where('nrp_dept_head', '!=', '');
                    } elseif ($filterAtasan === 'div_head') {
                        $query->whereNotNull('nrp_div_head')->where('nrp_div_head', '!=', '');
                    } else {
                        $query->where(function ($q) use ($filterAtasan) {
                            $q->where('nrp_foreman', $filterAtasan)
                              ->orWhere('nrp_section_head', $filterAtasan)
                              ->orWhere('nrp_dept_head', $filterAtasan)
                              ->orWhere('nrp_div_head', $filterAtasan);
                        });
                    }
                }
            }

            $targetNrps = $query->pluck('nrp')->map(fn($v) => (string)$v)->toArray();

            if ($scope === 'all') {
                $scopeDescription = 'Seluruh data karyawan (' . count($targetNrps) . ' orang)';
            } else {
                $activeFilters = [];
                if ($filterDivisi !== '') $activeFilters[] = "Divisi: {$filterDivisi}";
                if ($filterDept !== '') $activeFilters[] = "Dept: {$filterDept}";
                if ($filterSeksi !== '') $activeFilters[] = "Seksi: {$filterSeksi}";
                if ($filterGol !== '') $activeFilters[] = "Golongan: {$filterGol}";
                if ($filterAtasan !== '') $activeFilters[] = "Atasan: {$filterAtasan}";
                if ($search !== '') $activeFilters[] = "Pencarian: '{$search}'";

                $filterLabel = !empty($activeFilters) ? implode(', ', $activeFilters) : 'Semua Data';
                $scopeDescription = count($targetNrps) . " karyawan terfilter ({$filterLabel})";
            }
        }

        if (empty($targetNrps)) {
            return redirect()->back()->with('error', 'Tidak ada data karyawan yang cocok dengan kriteria filter untuk dihapus.');
        }

        // Cascade delete related records
        try {
            $docIds = DB::table('dokumen')->whereIn('nrp', $targetNrps)->pluck('id')->toArray();
            if (!empty($docIds)) {
                if (Schema::hasTable('nilai')) DB::table('nilai')->whereIn('dokumen_id', $docIds)->delete();
                if (Schema::hasTable('indikator')) DB::table('indikator')->whereIn('dokumen_id', $docIds)->delete();
                if (Schema::hasTable('tanda_tangan')) DB::table('tanda_tangan')->whereIn('dokumen_id', $docIds)->delete();
                if (Schema::hasTable('signature')) DB::table('signature')->whereIn('dokumen_id', $docIds)->delete();
                if (Schema::hasTable('history_status')) DB::table('history_status')->whereIn('dokumen_id', $docIds)->delete();
                DB::table('dokumen')->whereIn('id', $docIds)->delete();
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Notice on cascading bulk delete: " . $e->getMessage());
        }

        // Delete from karyawan table
        $deletedCount = DB::table('karyawan')->whereIn('nrp', $targetNrps)->delete();

        // Clear all caches
        $this->repository->clearCache('karyawan');
        $this->repository->clearCache('dokumen');
        $this->repository->clearCache('nilai');
        $this->repository->clearCache('indikator');
        $this->repository->clearCache('tanda_tangan');
        \Illuminate\Support\Facades\Cache::forget('sheet_all_karyawan');
        \Illuminate\Support\Facades\Cache::forget('sheet_all_dokumen');

        // Sync to Google Spreadsheet in background
        \App\Services\GoogleSyncService::syncTablesAsync(['karyawan', 'dokumen']);

        return redirect()->route('admin.karyawan')->with('success', "Berhasil menghapus {$deletedCount} data karyawan ({$scopeDescription}) secara massal beserta seluruh dokumen penilaian terkait!");
    }
}
