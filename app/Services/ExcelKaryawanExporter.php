<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

class ExcelKaryawanExporter
{
    /**
     * Build a styled .xlsx binary containing a formatted, auto-sized, bordered table.
     *
     * @param array<int, string> $headers
     * @param array<int, array<int, string|int|float>> $rows
     * @param array<int, int> $centeredColumnIndexes Zero-based column indexes that should be horizontally centered.
     */
    public static function buildXlsxBinary(
        string $sheetTitle,
        array $headers,
        array $rows,
        array $centeredColumnIndexes = []
    ): string {
        $tempPath = tempnam(sys_get_temp_dir(), 'xlsx_export_');
        if ($tempPath === false) {
            throw new RuntimeException('Unable to allocate temporary file for Excel export.');
        }

        $zip = new ZipArchive();
        if ($zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($tempPath);
            throw new RuntimeException('Unable to create Excel archive.');
        }

        try {
            $safeTitle = self::sanitizeSheetTitle($sheetTitle);
            $colCount = max(1, count($headers));
            $lastCol = self::indexToColLetter($colCount - 1);
            $lastRow = 1 + count($rows);

            $sharedStrings = [];
            $sharedIndexMap = [];
            $sharedTotalCount = 0;

            $internString = static function (string $value) use (&$sharedStrings, &$sharedIndexMap, &$sharedTotalCount): int {
                $clean = self::sanitizeXmlText($value);
                $sharedTotalCount++;
                if (!isset($sharedIndexMap[$clean])) {
                    $sharedIndexMap[$clean] = count($sharedStrings);
                    $sharedStrings[] = $clean;
                }
                return $sharedIndexMap[$clean];
            };

            $colWidths = self::computeColumnWidths($headers, $rows);
            $centeredLookup = array_flip($centeredColumnIndexes);

            $sheetXml = self::buildWorksheetXml(
                $headers,
                $rows,
                $colWidths,
                $centeredLookup,
                $lastCol,
                $lastRow,
                $internString
            );

            $zip->addFromString('[Content_Types].xml', self::buildContentTypesXml());
            $zip->addFromString('_rels/.rels', self::buildRootRelsXml());
            $zip->addFromString('xl/_rels/workbook.xml.rels', self::buildWorkbookRelsXml());
            $zip->addFromString('xl/workbook.xml', self::buildWorkbookXml($safeTitle));
            $zip->addFromString('xl/styles.xml', self::buildStylesXml());
            $zip->addFromString('xl/sharedStrings.xml', self::buildSharedStringsXml($sharedStrings, $sharedTotalCount));
            $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        } finally {
            $zip->close();
        }

        $binary = (string)file_get_contents($tempPath);
        @unlink($tempPath);

        return $binary;
    }

    /**
     * Compute comfortable column widths from header and cell lengths, leaving room for AutoFilter arrows.
     *
     * @param array<int, string> $headers
     * @param array<int, array<int, string|int|float>> $rows
     * @return array<int, float>
     */
    private static function computeColumnWidths(array $headers, array $rows): array
    {
        $widths = [];
        foreach ($headers as $idx => $header) {
            $headerLen = mb_strlen((string)$header);
            // Reserve space so Excel's row-1 AutoFilter arrow never overlaps the header label.
            $maxLen = max(7, $headerLen + 5);

            foreach ($rows as $row) {
                $valLen = mb_strlen((string)($row[$idx] ?? ''));
                if ($valLen + 3 > $maxLen) {
                    $maxLen = $valLen + 3;
                }
            }

            $widths[$idx] = (float)min(48, $maxLen);
        }

        return $widths;
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, array<int, string|int|float>> $rows
     * @param array<int, float> $colWidths
     * @param array<int, int> $centeredLookup
     */
    private static function buildWorksheetXml(
        array $headers,
        array $rows,
        array $colWidths,
        array $centeredLookup,
        string $lastCol,
        int $lastRow,
        callable $internString
    ): string {
        $colsXml = '';
        foreach ($colWidths as $idx => $width) {
            $colNum = $idx + 1;
            $wFormatted = number_format($width, 2, '.', '');
            $colsXml .= "<col min=\"{$colNum}\" max=\"{$colNum}\" width=\"{$wFormatted}\" customWidth=\"1\" bestFit=\"1\"/>";
        }

        $rowsXml = '';

        // Row 1: Styled Header
        $rowsXml .= '<row r="1" ht="26" customHeight="1">';
        foreach ($headers as $colIdx => $header) {
            $ref = self::indexToColLetter($colIdx) . '1';
            $sIdx = $internString((string)$header);
            $rowsXml .= "<c r=\"{$ref}\" t=\"s\" s=\"1\"><v>{$sIdx}</v></c>";
        }
        $rowsXml .= '</row>';

        // Data Rows (with subtle zebra shading + crisp borders)
        foreach ($rows as $rIdx => $row) {
            $excelRow = $rIdx + 2;
            $isEven = ($rIdx % 2) === 1;
            $rowsXml .= "<row r=\"{$excelRow}\" ht=\"20\" customHeight=\"1\">";

            foreach ($headers as $colIdx => $_unusedHeader) {
                $ref = self::indexToColLetter($colIdx) . $excelRow;
                $rawVal = $row[$colIdx] ?? '';
                $isCentered = isset($centeredLookup[$colIdx]);
                $styleIdx = $isEven ? ($isCentered ? 5 : 4) : ($isCentered ? 3 : 2);

                // Keep only ordinal NO as numeric so IDs like NRP/CODE BAGIAN never turn into scientific notation.
                if ($colIdx === 0 && is_int($rawVal)) {
                    $rowsXml .= "<c r=\"{$ref}\" s=\"{$styleIdx}\"><v>{$rawVal}</v></c>";
                } else {
                    $sIdx = $internString((string)$rawVal);
                    $rowsXml .= "<c r=\"{$ref}\" t=\"s\" s=\"{$styleIdx}\"><v>{$sIdx}</v></c>";
                }
            }

            $rowsXml .= '</row>';
        }

        $rangeRef = "A1:{$lastCol}{$lastRow}";

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            . "<dimension ref=\"{$rangeRef}\"/>"
            . '<sheetViews><sheetView showGridLines="1" workbookViewId="0">'
            . '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
            . '</sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="20"/>'
            . "<cols>{$colsXml}</cols>"
            . "<sheetData>{$rowsXml}</sheetData>"
            . "<autoFilter ref=\"{$rangeRef}\"/>"
            . '<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>'
            . '<pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0" paperSize="9"/>'
            . '</worksheet>';
    }

    private static function buildStylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2">'
            . '<font><sz val="10"/><color rgb="FF0F172A"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="4">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF92302E"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFF8FAFC"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="3">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border>'
            . '<left style="thin"><color rgb="FFCBD5E1"/></left>'
            . '<right style="thin"><color rgb="FFCBD5E1"/></right>'
            . '<top style="thin"><color rgb="FFCBD5E1"/></top>'
            . '<bottom style="thin"><color rgb="FFCBD5E1"/></bottom>'
            . '<diagonal/>'
            . '</border>'
            . '<border>'
            . '<left style="thin"><color rgb="FF7E2B29"/></left>'
            . '<right style="thin"><color rgb="FF7E2B29"/></right>'
            . '<top style="thin"><color rgb="FF7E2B29"/></top>'
            . '<bottom style="thin"><color rgb="FF7E2B29"/></bottom>'
            . '<diagonal/>'
            . '</border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="6">'
            // 0: default
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            // 1: header (bold white on #92302E, centered)
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            // 2: body left odd (white + slate border)
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>'
            // 3: body center odd (white + slate border)
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            // 4: body left even (zebra tint + slate border)
            . '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>'
            // 5: body center even (zebra tint + slate border)
            . '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    /**
     * @param array<int, string> $strings
     */
    private static function buildSharedStringsXml(array $strings, int $totalCount): string
    {
        $uniqueCount = count($strings);
        $itemsXml = '';
        foreach ($strings as $str) {
            $escaped = htmlspecialchars($str, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $itemsXml .= "<si><t xml:space=\"preserve\">{$escaped}</t></si>";
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . "<sst xmlns=\"http://schemas.openxmlformats.org/spreadsheetml/2006/main\" count=\"{$totalCount}\" uniqueCount=\"{$uniqueCount}\">"
            . $itemsXml
            . '</sst>';
    }

    private static function buildWorkbookXml(string $sheetTitle): string
    {
        $escapedTitle = htmlspecialchars($sheetTitle, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . "<sheets><sheet name=\"{$escapedTitle}\" sheetId=\"1\" r:id=\"rId1\"/></sheets>"
            . '</workbook>';
    }

    private static function buildWorkbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
            . '</Relationships>';
    }

    private static function buildRootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private static function buildContentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            . '</Types>';
    }

    private static function indexToColLetter(int $index): string
    {
        $letter = '';
        $num = $index + 1;
        while ($num > 0) {
            $rem = ($num - 1) % 26;
            $letter = chr(65 + $rem) . $letter;
            $num = intdiv($num - 1, 26);
        }

        return $letter;
    }

    private static function sanitizeSheetTitle(string $title): string
    {
        $clean = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', ' ', trim($title)) ?? 'Data Karyawan';
        $clean = trim(preg_replace('/\s+/', ' ', $clean) ?? '');
        if ($clean === '') {
            $clean = 'Data Karyawan';
        }

        return mb_substr($clean, 0, 31);
    }

    private static function sanitizeXmlText(string $value): string
    {
        // Strip ASCII control characters disallowed in XML 1.0 except tab, LF, CR.
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? '';
    }
}

