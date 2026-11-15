<?php

namespace App\Services;

use ZipArchive;

class ExcelKaryawanReader
{
    /**
     * Excluded position keywords for Section Head without Foreman subordinate import.
     */
    private const EXCLUDED_POSITION_KEYWORDS = [
        'FOREMAN',
        'LEADER',
        'SECTION HEAD',
        'DEPT HEAD',
        'DIV HEAD',
        'DIVISION HEAD',
    ];

    /**
     * Parse the first worksheet (or 'Result' sheet) of an .xlsx file into associative rows.
     *
     * @return array<int, array<string, string>>
     */
    public static function parseXlsx(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [];
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            return [];
        }

        try {
            $strings = self::readSharedStrings($zip);
            $sheetPath = self::resolveFirstSheetPath($zip);
            $sheetXml = $zip->getFromName($sheetPath);
            if (!$sheetXml) {
                return [];
            }

            $xml = simplexml_load_string($sheetXml);
            if (!$xml || !isset($xml->sheetData->row)) {
                return [];
            }

            $rawRows = [];
            foreach ($xml->sheetData->row as $row) {
                $rowCells = [];
                foreach ($row->c as $c) {
                    $colIdx = self::columnRefToIndex((string)$c['r']);
                    $type = (string)$c['t'];
                    $val = isset($c->v) ? (string)$c->v : '';

                    if ($type === 's' && $val !== '') {
                        $val = $strings[(int)$val] ?? '';
                    } elseif ($type === 'inlineStr' && isset($c->is->t)) {
                        $val = (string)$c->is->t;
                    }

                    $rowCells[$colIdx] = trim($val);
                }

                if (!empty($rowCells)) {
                    $maxIdx = max(array_keys($rowCells));
                    $filled = [];
                    for ($i = 0; $i <= $maxIdx; $i++) {
                        $filled[] = $rowCells[$i] ?? '';
                    }
                    $rawRows[] = $filled;
                }
            }

            if (empty($rawRows)) {
                return [];
            }

            // Dynamically detect header row within first 10 rows
            $headerRowIdx = 0;
            for ($i = 0; $i < min(10, count($rawRows)); $i++) {
                $rowNormalized = array_map(function ($cell) {
                    return strtoupper((string)preg_replace('/[^a-zA-Z0-9]/', '', (string)$cell));
                }, $rawRows[$i]);

                foreach ($rowNormalized as $val) {
                    if (in_array($val, ['NRP', 'NIK', 'EMPLOYEENO', 'NAMA', 'NAMALENGKAP', 'FULLNAME', 'POSITION', 'JABATAN', 'CODEBAGIAN', 'ORGSTRUCTURECODE'])) {
                        $headerRowIdx = $i;
                        break 2;
                    }
                }
            }

            $headers = array_map(fn ($h) => strtoupper(trim((string)$h)), $rawRows[$headerRowIdx]);
            $result = [];

            for ($r = $headerRowIdx + 1; $r < count($rawRows); $r++) {
                $vals = $rawRows[$r];
                $assoc = [];
                $hasValue = false;
                foreach ($headers as $idx => $header) {
                    if ($header === '') {
                        continue;
                    }
                    $cellVal = trim((string)($vals[$idx] ?? ''));
                    if (preg_match('/^-?\d+\.0+$/', $cellVal)) {
                        $cellVal = preg_replace('/\.0+$/', '', $cellVal);
                    }
                    if ($cellVal !== '') {
                        $hasValue = true;
                    }
                    $assoc[$header] = $cellVal;
                }
                if ($hasValue) {
                    $result[] = $assoc;
                }
            }

            return $result;
        } finally {
            $zip->close();
        }
    }

    /**
     * Extract filtered Section Head without Foreman subordinates from DATA PK.xlsx:
     * - DEPT = PROD 2
     * - SECTION = FACTORY 2
     * - Exclude positions FOREMAN, LEADER, SECTION HEAD, DEPT HEAD
     *
     * @param array<string, ?string> $hierarchy
     * @return array<int, array<string, mixed>>
     */
    public static function extractSectionHeadWithoutForemanFromExcel(
        string $filePath,
        string $targetDept = 'PROD 2',
        string $targetSection = 'FACTORY 2',
        array $hierarchy = []
    ): array {
        $rows = self::parseXlsx($filePath);
        $records = [];

        foreach ($rows as $row) {
            $nrp = trim($row['EMPLOYEENO'] ?? '');
            $nama = trim($row['FULLNAME'] ?? '');
            $dept = strtoupper(trim($row['DEPT'] ?? ''));
            $section = strtoupper(trim($row['SECTION'] ?? ''));
            $position = strtoupper(trim($row['POSITION'] ?? ''));

            if ($nrp === '' || $nama === '') {
                continue;
            }
            if ($dept !== strtoupper($targetDept) || $section !== strtoupper($targetSection)) {
                continue;
            }
            if (self::isExcludedPosition($position)) {
                continue;
            }

            $kj = self::normalizeKj($row['JOBLEVELCODE'] ?? 'KJ02');
            $subGol = trim($row['JOBCLASSCODE'] ?? 'IIB');
            $rawPos = trim($row['POSITION'] ?? 'OPERATOR');

            $records[] = [
                'nrp' => $nrp,
                'nama_lengkap' => $nama,
                'code_bagian' => trim($row['ORGSTRUCTURECODE'] ?? ''),
                'nama_bagian' => trim($row['ORGSTRUCTURENAME'] ?? ''),
                'line_category' => trim($row['LINE CATEGORY'] ?? ''),
                'jabatan' => $rawPos,
                'division' => trim($row['DIVISION'] ?? 'PRODUCTION A DIV'),
                'departemen' => trim($row['DEPT'] ?? $targetDept),
                'seksi' => trim($row['SECTION'] ?? $targetSection),
                'nama_sect' => trim($row['SECTION'] ?? $targetSection),
                'sub_gol' => $subGol,
                'golongan' => $subGol,
                'kj' => $kj,
                'tgl_masuk' => self::excelDateToYmd($row['JOINDATE'] ?? null),
                'level_form' => self::kjToLevelForm($kj, $rawPos),
                'nrp_foreman' => null,
                'nrp_section_head' => $hierarchy['nrp_section_head'] ?? '99999',
                'nrp_dept_head' => $hierarchy['nrp_dept_head'] ?? '31',
                'nrp_div_head' => $hierarchy['nrp_div_head'] ?? '11',
            ];
        }

        return $records;
    }

    public static function extractDepartmentEmployeesFromExcel(
        string $filePath,
        array $shWithoutForemanHierarchy = [],
        bool $includeUpperHeads = true
    ): array {
        $rows = self::parseXlsx($filePath);
        $records = [];

        // First pass: resolve dynamic Division Head per Division, Dept Head, Section Heads per Section, and Foremen per Line Category
        $divHeadNrp = $shWithoutForemanHierarchy['nrp_div_head'] ?? '11';
        $divHeadByDivision = [];
        $deptHeadByDept = [];
        $deptHeadByDivDept = [];
        $shByDivDeptSection = [];
        $foremanByCategory = [];

        foreach ($rows as $row) {
            $nrp = trim((string)($row['EMPLOYEENO'] ?? ''));
            $nama = trim((string)($row['FULLNAME'] ?? ''));
            if ($nrp === '' || $nama === '') {
                continue;
            }
            $rawPos = trim((string)($row['POSITION'] ?? 'OPERATOR'));
            $kj = self::normalizeKj($row['JOBLEVELCODE'] ?? 'KJ02');
            $lvl = self::kjToLevelForm($kj, $rawPos);
            $divUpper = strtoupper(trim((string)($row['DIVISION'] ?? '')));
            $deptUpper = strtoupper(trim((string)($row['DEPT'] ?? '')));
            $sectUpper = strtoupper(trim((string)($row['SECTION'] ?? '')));
            $catUpper = strtoupper(trim((string)($row['LINE CATEGORY'] ?? '')));

            if ($lvl === 'DIV_HEAD') {
                if ($divUpper !== '' && $divUpper !== '-') {
                    $divHeadByDivision[$divUpper] = $nrp;
                } else {
                    $divHeadNrp = $nrp;
                }
            } elseif ($lvl === 'DEPT_HEAD' && $deptUpper !== '' && $deptUpper !== '-') {
                $deptHeadByDept[$deptUpper] = $nrp;
                if ($divUpper !== '' && $divUpper !== '-') {
                    $deptHeadByDivDept["{$divUpper}|{$deptUpper}"] = $nrp;
                }
            } elseif ($lvl === 'SECTION_HEAD' && $sectUpper !== '' && $sectUpper !== '-') {
                if ($divUpper !== '' && $divUpper !== '-' && $deptUpper !== '' && $deptUpper !== '-') {
                    $shByDivDeptSection["{$divUpper}|{$deptUpper}|{$sectUpper}"] = $nrp;
                }
            } elseif ($lvl === 'FOREMAN' && $catUpper !== '' && $catUpper !== '-') {
                $foremanByCategory[$catUpper] = $nrp;
            }
        }

        foreach ($rows as $row) {
            $nrp = trim((string)($row['EMPLOYEENO'] ?? ''));
            $nama = trim((string)($row['FULLNAME'] ?? ''));
            $dept = trim((string)($row['DEPT'] ?? ''));
            $section = trim((string)($row['SECTION'] ?? ''));
            $rawPos = trim((string)($row['POSITION'] ?? 'OPERATOR'));
            $posUpper = strtoupper($rawPos);
            $orgName = trim((string)($row['ORGSTRUCTURENAME'] ?? ''));
            $division = trim((string)($row['DIVISION'] ?? 'PRODUCTION A DIV'));
            $divUpper = strtoupper($division);
            $catRaw = trim((string)($row['LINE CATEGORY'] ?? ''));
            $catUpper = strtoupper($catRaw);
            $sectUpper = strtoupper($section);

            if ($nrp === '' || $nama === '') {
                continue;
            }

            $isUpperHead = str_contains($posUpper, 'DEPT HEAD')
                || str_contains($posUpper, 'DEPARTMENT HEAD')
                || str_contains($posUpper, 'DEPT. HEAD')
                || str_contains($posUpper, 'DIV HEAD')
                || str_contains($posUpper, 'DIVISION HEAD');

            if ($isUpperHead && !$includeUpperHeads) {
                continue;
            }

            $kj = self::normalizeKj($row['JOBLEVELCODE'] ?? 'KJ02');
            $subGol = trim((string)($row['JOBCLASSCODE'] ?? 'IIB'));
            $golongan = self::subGolToGolongan($subGol);
            $levelForm = self::kjToLevelForm($kj, $rawPos);

            if ($dept !== '' && $dept !== '-') {
                $resolvedDept = $dept;
            } elseif ($levelForm === 'DIV_HEAD') {
                $resolvedDept = '-';
            } elseif (str_contains(strtoupper($orgName), 'PRODUCTION 2') || str_contains($divUpper, 'PRODUCTION A')) {
                $resolvedDept = 'PROD 2';
            } else {
                $resolvedDept = '-';
            }
            $deptUpper = strtoupper($resolvedDept);
            $resolvedDivHeadNrp = $divHeadByDivision[$divUpper] ?? $divHeadNrp;
            $nrpDeptHead = $deptHeadByDivDept["{$divUpper}|{$deptUpper}"]
                ?? $deptHeadByDept[$deptUpper]
                ?? ($deptUpper === 'PROD 2' ? ($shWithoutForemanHierarchy['nrp_dept_head'] ?? '31') : null);
            $nrpSectionHeadDefault = $shByDivDeptSection["{$divUpper}|{$deptUpper}|{$sectUpper}"]
                ?? ($deptUpper === 'PROD 2' ? ($shWithoutForemanHierarchy['nrp_section_head'] ?? null) : null);

            if ($levelForm === 'DIV_HEAD') {
                $nrpForeman = null;
                $nrpSectionHead = null;
                $nrpDeptHead = null;
                $nrpDivHead = $nrp;
            } elseif ($levelForm === 'DEPT_HEAD') {
                $nrpForeman = null;
                $nrpSectionHead = null;
                $nrpDeptHead = $nrp;
                $nrpDivHead = $resolvedDivHeadNrp;
            } elseif ($levelForm === 'SECTION_HEAD') {
                $nrpForeman = null;
                $nrpSectionHead = $nrp;
                $nrpDivHead = $resolvedDivHeadNrp;
            } elseif ($catUpper !== '' && $catUpper !== '-' && isset($foremanByCategory[$catUpper])) {
                $nrpForeman = $foremanByCategory[$catUpper];
                $nrpSectionHead = $nrpSectionHeadDefault;
                $nrpDivHead = $resolvedDivHeadNrp;
            } else {
                // Direct Section Head without Foreman (e.g., FACTORY 3 -> 99999 AL QADRI)
                $nrpForeman = null;
                $nrpSectionHead = $nrpSectionHeadDefault;
                $nrpDivHead = $resolvedDivHeadNrp;
            }

            $records[] = [
                'nrp' => $nrp,
                'nama_lengkap' => $nama,
                'code_bagian' => trim((string)($row['ORGSTRUCTURECODE'] ?? '')),
                'nama_bagian' => $orgName,
                'line_category' => $catRaw,
                'jabatan' => $rawPos,
                'division' => $division,
                'departemen' => $resolvedDept,
                'seksi' => $section ?: '-',
                'nama_sect' => $section ?: '-',
                'sub_gol' => $subGol,
                'golongan' => $golongan,
                'kj' => $kj,
                'tgl_masuk' => self::excelDateToYmd($row['JOINDATE'] ?? null),
                'level_form' => $levelForm,
                'nrp_foreman' => $nrpForeman,
                'nrp_section_head' => $nrpSectionHead,
                'nrp_dept_head' => $nrpDeptHead,
                'nrp_div_head' => $nrpDivHead,
            ];
        }

        return $records;
    }

    public static function subGolToGolongan(string $subGol): string
    {
        $upper = strtoupper(trim($subGol));
        if (str_contains($upper, 'V') && !str_contains($upper, 'IV')) {
            return 'GOLONGAN V';
        }
        if (str_contains($upper, 'IV')) {
            return 'GOLONGAN IV';
        }
        if (str_contains($upper, 'III')) {
            return 'GOLONGAN III';
        }
        if (str_contains($upper, 'II')) {
            return 'GOLONGAN II';
        }
        if (str_contains($upper, 'I')) {
            return 'GOLONGAN I';
        }
        return 'GOLONGAN II';
    }

    public static function isExcludedPosition(string $position): bool
    {
        $upper = strtoupper(trim($position));
        foreach (self::EXCLUDED_POSITION_KEYWORDS as $kw) {
            if (str_contains($upper, $kw)) {
                return true;
            }
        }
        return false;
    }

    public static function normalizeKj(?string $rawKj): string
    {
        $val = strtoupper(trim((string)$rawKj));
        if (preg_match('/(\d+)/', $val, $m)) {
            $num = (int)$m[1];
            if ($num >= 1 && $num <= 10) {
                return 'KJ ' . $num;
            }
        }
        return 'KJ 2';
    }

    /**
     * Normalisasi KJ untuk pengelompokan Target Kuota & Matriks Penilaian.
     * Karyawan non-exempt (selain Form 07-006) dengan KJ 5 ke atas (KJ 5, KJ 6, dst.)
     * mengambil kuota & terhitung pada kelompok target KJ 5 agar KJ >= 6 tidak ditampilkan terpisah.
     */
    public static function normalizeQuotaKj(?string $rawKj): string
    {
        $norm = self::normalizeKj($rawKj);
        if (preg_match('/^KJ\s+(\d+)$/', $norm, $m) && (int)$m[1] >= 5) {
            return 'KJ 5';
        }
        return $norm;
    }

    public static function kjToLevelForm(string $kj, string $position = ''): string
    {
        $posUpper = strtoupper($position);
        if (str_contains($posUpper, 'DIV HEAD') || str_contains($posUpper, 'DIVISION HEAD')) {
            return 'DIV_HEAD';
        }
        if (str_contains($posUpper, 'DEPT HEAD') || str_contains($posUpper, 'DEPARTMENT HEAD') || str_contains($posUpper, 'DEPT. HEAD')) {
            return 'DEPT_HEAD';
        }
        if (str_contains($posUpper, 'SECTION HEAD') || str_contains($posUpper, 'SECT HEAD')) {
            return 'SECTION_HEAD';
        }
        if (str_contains($posUpper, 'FOREMAN')) {
            return 'FOREMAN';
        }
        if (str_contains($posUpper, 'LEADER')) {
            return 'LEADER';
        }
        if (preg_match('/(\d+)/', $kj, $m)) {
            $num = (int)$m[1];
            if ($num >= 4) {
                return 'KJ45';
            }
        }
        return 'KJ13';
    }

    public static function excelDateToYmd(string|int|float|null $value): string
    {
        if ($value === null || $value === '') {
            return '2020-01-01';
        }
        if (is_numeric($value)) {
            $serial = (int)$value;
            if ($serial > 20000 && $serial < 80000) {
                return gmdate('Y-m-d', ($serial - 25569) * 86400);
            }
        }
        $ts = strtotime((string)$value);
        return $ts ? date('Y-m-d', $ts) : '2020-01-01';
    }

    /**
     * @return array<int, string>
     */
    private static function readSharedStrings(ZipArchive $zip): array
    {
        $strings = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if (!$ssXml) {
            return $strings;
        }
        $sx = simplexml_load_string($ssXml);
        if (!$sx) {
            return $strings;
        }
        foreach ($sx->si as $si) {
            if (isset($si->t)) {
                $strings[] = (string)$si->t;
            } else {
                $txt = '';
                if (isset($si->r)) {
                    foreach ($si->r as $r) {
                        $txt .= (string)$r->t;
                    }
                }
                $strings[] = $txt;
            }
        }
        return $strings;
    }

    private static function resolveFirstSheetPath(ZipArchive $zip): string
    {
        $wbXml = simplexml_load_string((string)$zip->getFromName('xl/workbook.xml'));
        $relsXml = simplexml_load_string((string)$zip->getFromName('xl/_rels/workbook.xml.rels'));
        $relMap = [];

        if ($relsXml) {
            foreach ($relsXml->Relationship as $rel) {
                $relMap[(string)$rel['Id']] = (string)$rel['Target'];
            }
        }

        if ($wbXml && isset($wbXml->sheets->sheet)) {
            foreach ($wbXml->sheets->sheet as $sheet) {
                $attrs = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                $rId = (string)($attrs['id'] ?? '');
                if ($rId !== '' && isset($relMap[$rId])) {
                    return 'xl/' . ltrim($relMap[$rId], '/');
                }
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    private static function columnRefToIndex(string $cellRef): int
    {
        preg_match('/^([A-Z]+)/', strtoupper($cellRef), $m);
        $letters = $m[1] ?? 'A';
        $idx = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $idx = $idx * 26 + (ord($letters[$i]) - ord('A') + 1);
        }
        return max(0, $idx - 1);
    }
}

