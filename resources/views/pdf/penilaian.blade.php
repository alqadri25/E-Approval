<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $formCodeLabel }} - {{ $karyawan['nrp'] }} - {{ $karyawan['nama_lengkap'] }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 4mm 5mm 4mm 5mm;
        }
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            font-family: {{ !empty($isGol4Up) ? "'Arial Narrow', 'Arial', arialuni, 'DejaVu Sans', sans-serif" : "'DejaVu Sans', Arial, Helvetica, sans-serif" }} !important;
        }
        body, table, td, th, div, span, p {
            font-family: {{ !empty($isGol4Up) ? "'Arial Narrow', 'Arial', arialuni, 'DejaVu Sans', sans-serif" : "'DejaVu Sans', Arial, Helvetica, sans-serif" }} !important;
        }
        body {
            font-size: {{ !empty($isGol4Up) ? '6.5pt' : '6.5pt' }};
            color: #000000;
            line-height: 1.1;
            margin: 0;
            padding: 0;
            background: #ffffff;
        }
        table {
            border-collapse: collapse;
            width: 100%;
        }
        th, td {
            border: 1px solid #000000;
            padding: 1.5px 2.5px;
            vertical-align: middle;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-bold { font-weight: bold; }
        .font-black { font-weight: bold; }
        .uppercase { text-transform: uppercase; }
        .font-mono { font-family: {{ !empty($isGol4Up) ? "'Arial Narrow', 'Arial', monospace" : "'DejaVu Sans Mono', monospace" }}; }
        
        .bg-excel-green { background-color: {{ !empty($isGol4Up) ? '#ffffff' : '#e2efda' }} !important; }
        .bg-equation-green { background-color: #d4f8d4 !important; }
        .bg-banner-green { background-color: #00e000 !important; color: #000000 !important; }
        .bg-gray-bar { background-color: #d1d5db !important; }
        .bg-gray-header { background-color: #d9d9d9 !important; }
        .bg-blocked-gray { background-color: #bfbfbf !important; }
        
        .form-code-tag {
            font-size: 7.5pt;
            font-weight: bold;
            font-family: {{ !empty($isGol4Up) ? "'Arial Narrow', 'Arial', sans-serif" : "'DejaVu Sans Mono', monospace" }};
            margin-bottom: 2px;
        }

        .sheet-container {
            border: 1.5px solid #000000;
            width: 100%;
            overflow: hidden;
        }

        .header-cell {
            vertical-align: top;
            padding: 0;
        }

        .potensi-box {
            display: inline-block;
            width: 10px;
            height: 10px;
            border: 1px solid #000000;
            text-align: center;
            line-height: 8.5px;
            font-size: 7.5pt;
            font-weight: bold;
            vertical-align: middle;
        }
        .potensi-box.checked {
            background-color: #0f172a;
            color: #ffffff;
        }
        .potensi-box.unchecked {
            background-color: #ffffff;
            color: transparent;
        }

        .line-underline {
            border-bottom: 0.8px solid #94a3b8;
            display: block;
            margin-top: 1.5px;
            padding-bottom: 1.5px;
            min-height: 10px;
            font-size: 5.8pt;
            line-height: 1.2;
        }

        .ruled-line {
            border-bottom: 0.8px solid #cbd5e1;
            height: 14px;
            line-height: 14px;
            padding: 0 2px;
            font-size: 6.5pt;
        }

        /* 07-006 Specific High-Precision Styles */
        .box-07006 {
            border: 1px solid #000000;
            padding: 3px 2px;
            margin-bottom: 5px;
            background: #ffffff;
        }
        .check-07006 {
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 1.2px solid #000000;
            text-align: center;
            line-height: 10px;
            font-size: 8pt;
            font-weight: bold;
            vertical-align: middle;
        }
    </style>
</head>
<body>

    <div class="form-code-tag">{{ $formCodeLabel }}</div>

    <div class="sheet-container">
        
        <!-- ======================= -->
        <!-- TOP HEADER ROW          -->
        <!-- ======================= -->
        @php
            $rawForemanNama = $ttdByLevel['foreman']['signer_name'] ?? ($ttdByLevel['foreman']['user']['nama'] ?? ($karyawan['foreman_nama'] ?? ''));
            $foremanNama = trim(preg_replace('/\s*\(.*?\)/', '', $rawForemanNama));
            $foremanNrp = $ttdByLevel['foreman']['signer_nrp'] ?? ($ttdByLevel['foreman']['user']['nrp'] ?? ($karyawan['nrp_foreman'] ?? ''));

            $isTanpaForemanStd = !$isGol4Up && empty($karyawan['nrp_foreman']);
            $showForemanData = !$isTanpaForemanStd || !empty($ttdByLevel['foreman']) || !empty($scoresByLevel['foreman']);

            $rawShNama = $ttdByLevel['section_head']['signer_name'] ?? ($ttdByLevel['section_head']['user']['nama'] ?? ($karyawan['section_head_nama'] ?? ''));
            $shNama = trim(preg_replace('/\s*\(.*?\)/', '', $rawShNama));
            $shNrp = $ttdByLevel['section_head']['signer_nrp'] ?? ($ttdByLevel['section_head']['user']['nrp'] ?? ($karyawan['nrp_section_head'] ?? ''));

            $rawDhNama = $ttdByLevel['dept_head']['signer_name'] ?? ($ttdByLevel['dept_head']['user']['nama'] ?? ($karyawan['dept_head_nama'] ?? ''));
            $dhNama = trim(preg_replace('/\s*\(.*?\)/', '', $rawDhNama));
            $dhNrp = $ttdByLevel['dept_head']['signer_nrp'] ?? ($ttdByLevel['dept_head']['user']['nrp'] ?? ($karyawan['nrp_dept_head'] ?? ''));

            $rawDivHeadNama = $ttdByLevel['div_head']['signer_name'] ?? ($ttdByLevel['div_head']['user']['nama'] ?? ($karyawan['div_head_nama'] ?? ''));
            $divHeadNama = trim(preg_replace('/\s*\(.*?\)/', '', $rawDivHeadNama));
            $divHeadNrp = $ttdByLevel['div_head']['signer_nrp'] ?? ($ttdByLevel['div_head']['user']['nrp'] ?? ($karyawan['nrp_div_head'] ?? ''));

            // Resolusi NAMA SECT. (Seksi resmi organisasi, BUKAN Nama Bagian / tanpa akhiran .SEC)
            $displaySect = trim($karyawan['nama_sect'] ?? '');
            if (!$displaySect) {
                $rawS = trim($karyawan['seksi'] ?? '');
                $rawSUpper = strtoupper($rawS);
                $deptUpper = strtoupper(trim($karyawan['departemen'] ?? 'PROD 2'));
                if (str_contains($rawSUpper, '2') || str_contains($rawSUpper, 'FACT-2')) {
                    $displaySect = 'FACTORY 2';
                } elseif (str_contains($deptUpper, 'ENGINEERING') || str_contains($rawSUpper, 'ENG')) {
                    $displaySect = 'ENGINEERING';
                } elseif (str_contains($deptUpper, 'QA') || str_contains($rawSUpper, 'QA')) {
                    $displaySect = 'QA';
                } elseif (str_contains($deptUpper, 'QC') || str_contains($rawSUpper, 'QC')) {
                    $displaySect = 'QC';
                } else {
                    $cleanS = trim(preg_replace('/\s+SEC\.?$/i', '', $rawS));
                    $displaySect = ($cleanS && $cleanS !== '-') ? $cleanS : 'FACTORY 1';
                }
            }

            // Helper format nilai
            $formatScoreCell = function($val) use ($isGol4Up) {
                if ($isGol4Up) {
                    if (!isset($val) || $val === '' || (float)$val <= 0) {
                        return '';
                    }
                    $n = (float)$val;
                    return fmod($n, 1) == 0 ? number_format($n, 0) : number_format($n, 2);
                }
                return (isset($val) && (float)$val > 0)
                    ? (fmod((float)$val, 1) == 0 ? number_format((float)$val, 0) : number_format((float)$val, 2))
                    : '-';
            };

            $formatTotalCell = function($val) use ($isGol4Up) {
                if ($isGol4Up) {
                    if (!isset($val) || $val === '' || (float)$val <= 0) {
                        return '';
                    }
                    $n = (float)$val;
                    return fmod($n, 1) == 0 ? number_format($n, 0) : number_format($n, 2);
                }
                return (isset($val) && (float)$val > 0)
                    ? (fmod((float)$val, 1) == 0 ? number_format((float)$val, 0) : number_format((float)$val, 2))
                    : '0';
            };

            $formatDecimalCell = function($val) use ($isGol4Up) {
                if ($isGol4Up) {
                    if (!isset($val) || $val === '' || (float)$val <= 0) {
                        return '';
                    }
                    return number_format((float)$val, 2);
                }
                return (isset($val) && (float)$val > 0) ? number_format((float)$val, 2) : '0.00';
            };
        @endphp

        <table style="width: 100%; border-collapse: collapse; border-bottom: 1.5px solid #000000;">
            <colgroup>
                <col style="width: 15%;">
                <col style="width: 13%;">
                <col style="width: 17%;">
                <col style="width: 15.5%;">
                <col style="width: 15.5%;">
                <col style="width: 8%;">
                <col style="width: 8%;">
                <col style="width: 8%;">
            </colgroup>

            <!-- ROW 1 -->
            <tr>
                <!-- Col 1: Musashi Logo + RAHASIA Box + Periode + Tahun (Spans all 5 rows) -->
                <td rowspan="5" style="width: 15%; border-right: 1.2px solid #000000; border-bottom: 1.5px solid #000000; padding: 2.5px 3px; vertical-align: top; background: #ffffff;">
                    <div style="margin-bottom: 2px;">
                        @if(!empty($musashiLogoBase64))
                            <img src="{{ $musashiLogoBase64 }}" alt="MUSASHI" style="max-height: 22px; max-width: 100%; object-fit: contain; display: block;">
                        @else
                            <img src="{{ public_path('logomusashi.png') }}" alt="MUSASHI" style="max-height: 22px; max-width: 100%; object-fit: contain; display: block;">
                        @endif
                    </div>
                    <div style="border: 1px solid #000000; text-align: center; padding: 1px 0; font-weight: bold; font-size: {{ $isGol4Up ? '7pt' : '6.8pt' }}; background: {{ $isGol4Up ? '#d9d9d9' : '#ffffff' }}; margin: 2.5px 0 2px 0;">
                        {{ $isGol4Up ? 'RAHASIA 秘密' : ($formSettings['label_kerahasiaan'] ?? 'RAHASIA') }}
                    </div>
                    <table style="width: 100%; border: none; font-size: 5.5pt; margin-top: 1px;">
                        <tr>
                            <td style="border: none; padding: 0.5px 0; border-top: 0.8px solid #e2e8f0;">{{ $isGol4Up ? 'PERIODE 期間 :' : 'Periode :' }}</td>
                            <td style="border: none; padding: 0.5px 0; text-align: right; font-weight: bold; border-top: 0.8px solid #e2e8f0;">{{ $formSettings['periode_penilaian'] ?? 'Jan - Des' }}</td>
                        </tr>
                        <tr>
                            <td style="border: none; padding: 0.5px 0;">{{ $isGol4Up ? 'TAHUN 年 :' : 'Tahun :' }}</td>
                            <td style="border: none; padding: 0.5px 0; text-align: right; font-weight: bold; font-family: monospace;">{{ $formSettings['tahun_penilaian'] ?? ($periode['tahun'] ?? 2025) }}</td>
                        </tr>
                    </table>
                </td>

                <!-- Col 2: NAMA label -->
                <td style="width: 13%; font-size: 6.2pt; font-weight: bold; border-right: 1px solid #cbd5e1; border-bottom: 1px solid #000000; padding: 1.5px 3px; white-space: nowrap; vertical-align: middle;">
                    {{ $isGol4Up ? 'NAMA / 名前 :' : 'NAMA LENGKAP :' }}
                </td>
                <!-- Col 3: NAMA value -->
                <td class="bg-excel-green font-bold uppercase" style="width: 17%; border-right: 1.2px solid #000000; border-bottom: 1px solid #000000; padding: 1.5px 3px; font-size: 6.8pt; vertical-align: middle; background: {{ $isGol4Up ? '#ffffff' : '#e2efda' }};">
                    {{ $karyawan['nama_lengkap'] }}
                </td>

                <!-- Col 4 & 5: PENILAIAN KARYA Title (Colspan 2) -->
                @if($isGol4Up)
                <td colspan="2" rowspan="2" style="width: 31%; border-right: 1.2px solid #000000; border-bottom: 1px solid #000000; padding: 2px 2px; text-align: center; vertical-align: middle;">
                    <div style="font-size: 8.8pt; font-weight: bold; line-height: 1.1; text-transform: uppercase; letter-spacing: 0.5px;">
                        {{ $formSettings['judul_form_pk'] ?? 'PENILAIAN KARYA' }} / 仕事の評価
                    </div>
                </td>
                @else
                <td colspan="2" style="width: 31%; border-right: 1.2px solid #000000; border-bottom: 1px solid #000000; padding: 2px 2px; text-align: center; vertical-align: middle;">
                    <div style="font-size: 9.5pt; font-weight: bold; line-height: 1; text-transform: uppercase;">
                        {{ $formSettings['judul_form_pk'] ?? 'PENILAIAN KARYA' }}
                    </div>
                </td>
                @endif

                <!-- Col 6: Foreman / Kary Ybs / Section Head Header -->
                @if($subFormVariant === 'direct_div_subordinate' || $subFormVariant === 'dept_and_div')
                <td class="bg-blocked-gray" style="width: 8%; border-bottom: 1px solid #000000; border-right: 1px solid #000000; padding: 2px 1px; background-color: #bfbfbf !important;">&nbsp;</td>
                @else
                <td class="bg-gray-header text-center font-bold" style="width: 8%; border-bottom: 1px solid #000000; border-right: 1px solid #000000; padding: 2px 1px; font-size: 5.5pt; vertical-align: middle; line-height: 1.15;">
                    @if($subFormVariant === 'karyawan_gol4')
                        Section Head
                    @elseif($isGol4Up)
                        Kary. Ybs<br><span style="font-size: 4.8pt; font-weight: normal;">個人的</span>
                    @else
                        Foreman
                    @endif
                </td>
                @endif

                <!-- Col 7: Section Head / Atasan Langsung / Dept Head Header -->
                @if($subFormVariant === 'direct_div_subordinate' || $subFormVariant === 'div_head')
                <td class="bg-blocked-gray" style="width: 8%; border-bottom: 1px solid #000000; border-right: 1px solid #000000; padding: 2px 1px; background-color: #bfbfbf !important;">&nbsp;</td>
                @elseif($subFormVariant === 'dept_head')
                <td class="bg-blocked-gray" style="width: 8%; border-bottom: 1px solid #000000; border-right: 1px solid #000000; background-color: #bfbfbf !important;"></td>
                @elseif($subFormVariant === 'dept_and_div')
                <td class="bg-gray-header text-center font-bold" style="width: 8%; border-bottom: 1px solid #000000; border-right: 1px solid #000000; padding: 2px 1px; font-size: 5.5pt; vertical-align: middle; line-height: 1.15;">
                    Department Head
                </td>
                @else
                <td class="bg-gray-header text-center font-bold" style="width: 8%; border-bottom: 1px solid #000000; border-right: 1px solid #000000; padding: 2px 1px; font-size: 5.5pt; vertical-align: middle; line-height: 1.15;">
                    @if($subFormVariant === 'karyawan_gol4')
                        Department Head
                    @elseif($isGol4Up)
                        Atasan Langsung<br><span style="font-size: 4.8pt; font-weight: normal;">直属の上司</span>
                    @else
                        Section Head
                    @endif
                </td>
                @endif

                <!-- Col 8: Dept Head / Penilai Final / Div Head Header -->
                @if($subFormVariant === 'div_head')
                <td class="bg-blocked-gray" style="width: 8%; border-bottom: 1px solid #000000; border-right: none; background-color: #bfbfbf !important;"></td>
                @elseif($subFormVariant === 'direct_div_subordinate' || $subFormVariant === 'dept_and_div')
                <td class="bg-gray-header text-center font-bold" style="width: 8%; border-bottom: 1px solid #000000; border-right: none; padding: 2px 1px; font-size: 5.5pt; vertical-align: middle; line-height: 1.15;">
                    Division Head
                </td>
                @else
                <td class="bg-gray-header text-center font-bold" style="width: 8%; border-bottom: 1px solid #000000; border-right: none; padding: 2px 1px; font-size: 5.5pt; vertical-align: middle; line-height: 1.15;">
                    @if($subFormVariant === 'karyawan_gol4')
                        Division Head
                    @elseif($isGol4Up)
                        Penilai Final<br><span style="font-size: 4.8pt; font-weight: normal;">最終審査員</span>
                    @else
                        Department Head
                    @endif
                </td>
                @endif
            </tr>

            <!-- ROW 2 -->
            <tr>
                <!-- Col 2: NRP label -->
                <td style="font-size: 6.2pt; font-weight: bold; border-right: 1px solid #cbd5e1; border-bottom: 1px solid #000000; padding: 1.5px 3px; white-space: nowrap; vertical-align: middle;">
                    NRP :
                </td>
                <!-- Col 3: NRP value -->
                <td class="bg-excel-green font-mono font-bold" style="border-right: 1.2px solid #000000; border-bottom: 1px solid #000000; padding: 1.5px 3px; font-size: 6.8pt; vertical-align: middle; background: {{ $isGol4Up ? '#ffffff' : '#e2efda' }};">
                    {{ $karyawan['nrp'] }}
                </td>

                @if(!$isGol4Up)
                <!-- Col 4 & 5: Bright Green Banner (Colspan 2) -->
                <td colspan="2" class="bg-banner-green text-center font-black" style="border-right: 1.2px solid #000000; border-bottom: 1px solid #000000; padding: 2.5px 0; font-size: 8.5pt; vertical-align: middle;">
                    {{ $bannerTitle }}
                </td>
                @endif
                <!-- If $isGol4Up, Col 4 & 5 covered by rowspan="2" from Row 1! -->

                <!-- Col 6: Foreman / Kary Ybs / Section Head Signature (rowspan 2 for rows 2 & 3) -->
                @if($subFormVariant === 'direct_div_subordinate' || $subFormVariant === 'dept_and_div')
                <td rowspan="2" class="bg-blocked-gray" style="border-bottom: 1px solid #000000; border-right: 1px solid #000000; background-color: #bfbfbf !important;">&nbsp;</td>
                @else
                <td rowspan="2" style="border-bottom: 1px solid #000000; border-right: 1px solid #000000; text-align: center; vertical-align: middle; padding: 1px;">
                    @php
                        if ($subFormVariant === 'karyawan_gol4') {
                            $foremanPdfImg = $ttdByLevel['section_head']['ttd_base64'] ?? $ttdByLevel['section_head']['ttd_image'] ?? null;
                            $col6PdfAlt = 'Section Head';
                        } elseif ($subFormVariant === 'div_head') {
                            $foremanPdfImg = $ttdByLevel['foreman']['ttd_base64'] ?? $ttdByLevel['foreman']['ttd_image']
                                ?? $ttdByLevel['div_head']['ttd_base64'] ?? $ttdByLevel['div_head']['ttd_image']
                                ?? null;
                            $col6PdfAlt = 'Kary. Ybs';
                        } else {
                            $foremanPdfImg = $ttdByLevel['foreman']['ttd_base64'] ?? $ttdByLevel['foreman']['ttd_image'] ?? null;
                            $col6PdfAlt = $isGol4Up ? 'Kary. Ybs' : 'Foreman';
                        }
                    @endphp
                    @if($isTanpaForemanStd && $subFormVariant !== 'karyawan_gol4')
                        <span style="color: #94a3b8; font-style: italic; font-size: 6.5pt;">-</span>
                    @elseif(!empty($foremanPdfImg))
                        <img src="{{ $foremanPdfImg }}" alt="Barcode TTD {{ $col6PdfAlt }}" style="max-height: 48px; max-width: 48px; width: 48px; height: 48px; display: block; margin: 0 auto; padding: 1px;">
                    @else
                        <span style="color: #cbd5e1; font-style: italic; font-size: 5.5pt;">Belum TTD</span>
                    @endif
                </td>
                @endif

                <!-- Col 7: Section Head / Atasan Langsung / Dept Head Signature (rowspan 2 for rows 2 & 3) -->
                @if($subFormVariant === 'direct_div_subordinate' || $subFormVariant === 'div_head')
                <td rowspan="2" class="bg-blocked-gray" style="border-bottom: 1px solid #000000; border-right: 1px solid #000000; background-color: #bfbfbf !important;">&nbsp;</td>
                @elseif($subFormVariant === 'dept_head')
                <td rowspan="2" class="bg-blocked-gray" style="border-bottom: 1px solid #000000; border-right: 1px solid #000000; background-color: #bfbfbf !important;"></td>
                @elseif($subFormVariant === 'dept_and_div')
                <td rowspan="2" style="border-bottom: 1px solid #000000; border-right: 1px solid #000000; text-align: center; vertical-align: middle; padding: 1px;">
                    @php
                        $dhPdfImg = $ttdByLevel['dept_head']['ttd_base64'] ?? $ttdByLevel['dept_head']['ttd_image'] ?? null;
                    @endphp
                    @if(!empty($dhPdfImg))
                        <img src="{{ $dhPdfImg }}" alt="Barcode TTD Dept Head" style="max-height: 48px; max-width: 48px; width: 48px; height: 48px; display: block; margin: 0 auto; padding: 1px;">
                    @else
                        <span style="color: #cbd5e1; font-style: italic; font-size: 5.5pt;">Belum TTD</span>
                    @endif
                </td>
                @else
                <td rowspan="2" style="border-bottom: 1px solid #000000; border-right: 1px solid #000000; text-align: center; vertical-align: middle; padding: 1px;">
                    @php
                        if ($subFormVariant === 'karyawan_gol4') {
                            $shPdfImg = $ttdByLevel['dept_head']['ttd_base64'] ?? $ttdByLevel['dept_head']['ttd_image'] ?? null;
                            $col7PdfAlt = 'Department Head';
                        } else {
                            $shPdfImg = $ttdByLevel['section_head']['ttd_base64'] ?? $ttdByLevel['section_head']['ttd_image'] ?? null;
                            $col7PdfAlt = $isGol4Up ? 'Atasan' : 'Section Head';
                        }
                    @endphp
                    @if(!empty($shPdfImg))
                        <img src="{{ $shPdfImg }}" alt="Barcode TTD {{ $col7PdfAlt }}" style="max-height: 48px; max-width: 48px; width: 48px; height: 48px; display: block; margin: 0 auto; padding: 1px;">
                    @else
                        <span style="color: #cbd5e1; font-style: italic; font-size: 5.5pt;">Belum TTD</span>
                    @endif
                </td>
                @endif

                <!-- Col 8: Dept Head / Penilai Final / Div Head Signature (rowspan 2 for rows 2 & 3) -->
                @if($subFormVariant === 'div_head')
                <td rowspan="2" class="bg-blocked-gray" style="border-right: none; border-bottom: 1px solid #000000; background-color: #bfbfbf !important;"></td>
                @elseif($subFormVariant === 'direct_div_subordinate' || $subFormVariant === 'dept_and_div')
                <td rowspan="2" style="border-right: none; border-bottom: 1px solid #000000; text-align: center; vertical-align: middle; padding: 1px; background: #ffffff;">
                    @php
                        $divPdfImg = $ttdByLevel['div_head']['ttd_base64'] ?? $ttdByLevel['div_head']['ttd_image'] ?? null;
                    @endphp
                    @if(!empty($divPdfImg))
                        <img src="{{ $divPdfImg }}" alt="Barcode TTD Div Head" style="max-height: 48px; max-width: 48px; width: 48px; height: 48px; display: block; margin: 0 auto; padding: 1px;">
                    @else
                        <span style="color: #cbd5e1; font-style: italic; font-size: 5.5pt;">Belum TTD</span>
                    @endif
                </td>
                @else
                <td rowspan="2" style="border-right: none; border-bottom: 1px solid #000000; text-align: center; vertical-align: middle; padding: 1px;">
                    @php
                        if ($subFormVariant === 'karyawan_gol4') {
                            $dhPdfImg = $ttdByLevel['div_head']['ttd_base64'] ?? $ttdByLevel['div_head']['ttd_image'] ?? null;
                            $col8PdfAlt = 'Division Head';
                        } else {
                            $dhPdfImg = $ttdByLevel['dept_head']['ttd_base64'] ?? $ttdByLevel['dept_head']['ttd_image'] ?? null;
                            $col8PdfAlt = $isGol4Up ? 'Penilai Final' : 'Dept Head';
                        }
                    @endphp
                    @if(!empty($dhPdfImg))
                        <img src="{{ $dhPdfImg }}" alt="Barcode TTD {{ $col8PdfAlt }}" style="max-height: 48px; max-width: 48px; width: 48px; height: 48px; display: block; margin: 0 auto; padding: 1px;">
                    @else
                        <span style="color: #cbd5e1; font-style: italic; font-size: 5.5pt;">Belum TTD</span>
                    @endif
                </td>
                @endif
            </tr>

            <!-- ROW 3 -->
            <tr>
                <!-- Col 2: GOLONGAN label -->
                <td style="font-size: 6.2pt; font-weight: bold; border-right: 1px solid #cbd5e1; border-bottom: 1px solid #000000; padding: 1.5px 3px; white-space: nowrap; vertical-align: middle;">
                    {{ $isGol4Up ? 'GOLONGAN / 級 :' : 'GOLONGAN :' }}
                </td>
                <!-- Col 3: GOLONGAN value -->
                <td class="bg-excel-green font-bold" style="border-right: 1.2px solid #000000; border-bottom: 1px solid #000000; padding: 1.5px 3px; font-size: 6.8pt; vertical-align: middle; background: {{ $isGol4Up ? '#ffffff' : '#e2efda' }};">
                    {{ $karyawan['sub_gol'] ?? $karyawan['golongan'] }}
                </td>

                <!-- Col 4 & 5: Subtitle boxes -->
                @if(!$isGol4Up)
                <td style="width: 15.5%; border-right: 1px solid #000000; border-bottom: 1px solid #000000; text-align: center; vertical-align: middle; padding: 1px 1px;">
                    <div style="font-size: 5pt; color: #64748b; font-weight: bold; text-transform: uppercase; line-height: 1;">SEKSI</div>
                </td>
                <td style="width: 15.5%; border-right: 1.2px solid #000000; border-bottom: 1px solid #000000; text-align: center; vertical-align: middle; padding: 1px 1px;">
                    <div style="font-size: 5pt; color: #64748b; font-weight: bold; text-transform: uppercase; line-height: 1;">DEPARTEMEN</div>
                </td>
                @elseif($subFormVariant === 'div_head' || $subFormVariant === 'direct_div_subordinate')
                <td colspan="2" style="width: 31%; border-right: 1.2px solid #000000; border-bottom: 1px solid #000000; text-align: center; vertical-align: middle; padding: 1px 1px;">
                    <div style="font-size: 5.5pt; color: #0f172a; font-weight: bold; letter-spacing: 0.5px;">DIVISION</div>
                </td>
                @elseif($subFormVariant === 'dept_head' || $subFormVariant === 'dept_and_div')
                <td style="width: 15.5%; border-right: 1px solid #000000; border-bottom: 1px solid #000000; text-align: center; vertical-align: middle; padding: 1px 1px;">
                    <div style="font-size: 5.5pt; color: #0f172a; font-weight: bold; line-height: 1;">DEPARTEMEN / 部門</div>
                </td>
                <td style="width: 15.5%; border-right: 1.2px solid #000000; border-bottom: 1px solid #000000; text-align: center; vertical-align: middle; padding: 1px 1px;">
                    <div style="font-size: 5.5pt; color: #0f172a; font-weight: bold; line-height: 1;">DIVISION</div>
                </td>
                @else
                <td style="width: 15.5%; border-right: 1px solid #000000; border-bottom: 1px solid #000000; text-align: center; vertical-align: middle; padding: 1px 1px;">
                    <div style="font-size: 5.5pt; color: #0f172a; font-weight: bold; line-height: 1;">SECTION / セクション</div>
                </td>
                <td style="width: 15.5%; border-right: 1.2px solid #000000; border-bottom: 1px solid #000000; text-align: center; vertical-align: middle; padding: 1px 1px;">
                    <div style="font-size: 5.5pt; color: #0f172a; font-weight: bold; line-height: 1;">DEPARTEMEN / 部門</div>
                </td>
                @endif
                <!-- Col 6, 7, 8 covered by rowspan="2" from Row 2 -->
            </tr>

            <!-- ROW 4 -->
            <tr>
                <!-- Col 2: JABATAN label -->
                <td style="font-size: 6.2pt; font-weight: bold; border-right: 1px solid #cbd5e1; border-bottom: 1px solid #000000; padding: 1.5px 3px; white-space: nowrap; vertical-align: middle;">
                    {{ $isGol4Up ? 'JABATAN / 職位 :' : 'JABATAN :' }}
                </td>
                <!-- Col 3: JABATAN value -->
                <td class="bg-excel-green font-bold" style="border-right: 1.2px solid #000000; border-bottom: 1px solid #000000; padding: 1.5px 3px; font-size: 6.8pt; vertical-align: middle; background: {{ $isGol4Up ? '#ffffff' : '#e2efda' }};">
                    {{ $karyawan['jabatan'] }}
                </td>

                <!-- Col 4 & 5 Values (rowspan 2 for rows 4 & 5) -->
                @if(!$isGol4Up)
                <td rowspan="2" style="border-right: 1px solid #000000; border-bottom: 1.5px solid #000000; text-align: center; vertical-align: middle; padding: 2px 1px;">
                    <div style="font-size: 6.2pt; font-weight: bold; line-height: 1.1; text-transform: uppercase;">{{ $displaySect }}</div>
                </td>
                <td rowspan="2" style="border-right: 1.2px solid #000000; border-bottom: 1.5px solid #000000; text-align: center; vertical-align: middle; padding: 2px 1px;">
                    <div style="font-size: 6.2pt; font-weight: bold; line-height: 1.1; text-transform: uppercase;">{{ $karyawan['departemen'] }}</div>
                </td>
                @elseif($subFormVariant === 'div_head' || $subFormVariant === 'direct_div_subordinate')
                <td colspan="2" rowspan="2" style="border-right: 1.2px solid #000000; border-bottom: 1.5px solid #000000; text-align: center; vertical-align: middle; padding: 2px 1px;">
                    <div style="font-size: 6.5pt; font-weight: bold; line-height: 1.1; text-transform: uppercase;">{{ (!empty($karyawan['division']) && $karyawan['division'] !== '-') ? $karyawan['division'] : ($karyawan['departemen'] ?? 'PRODUCTION A DIV') }}</div>
                </td>
                @elseif($subFormVariant === 'dept_head' || $subFormVariant === 'dept_and_div')
                <td rowspan="2" style="border-right: 1px solid #000000; border-bottom: 1.5px solid #000000; text-align: center; vertical-align: middle; padding: 2px 1px;">
                    <div style="font-size: 6.5pt; font-weight: bold; line-height: 1.1; text-transform: uppercase;">{{ $karyawan['departemen'] }}</div>
                </td>
                <td rowspan="2" style="border-right: 1.2px solid #000000; border-bottom: 1.5px solid #000000; text-align: center; vertical-align: middle; padding: 2px 1px;">
                    <div style="font-size: 6.5pt; font-weight: bold; line-height: 1.1; text-transform: uppercase;">{{ $karyawan['division'] ?? 'PPIC DIV' }}</div>
                </td>
                @else
                <td rowspan="2" style="border-right: 1px solid #000000; border-bottom: 1.5px solid #000000; text-align: center; vertical-align: middle; padding: 2px 1px;">
                    <div style="font-size: 6.5pt; font-weight: bold; line-height: 1.1; text-transform: uppercase;">{{ $displaySect }}</div>
                </td>
                <td rowspan="2" style="border-right: 1.2px solid #000000; border-bottom: 1.5px solid #000000; text-align: center; vertical-align: middle; padding: 2px 1px;">
                    <div style="font-size: 6.5pt; font-weight: bold; line-height: 1.1; text-transform: uppercase;">{{ $karyawan['departemen'] }}</div>
                </td>
                @endif

                <!-- Col 6: Foreman / Kary Ybs / Section Head Name (rowspan 2 for rows 4 & 5) -->
                @if($subFormVariant === 'direct_div_subordinate' || $subFormVariant === 'dept_and_div')
                <td rowspan="2" class="bg-blocked-gray" style="border-bottom: 1.5px solid #000000; border-right: 1px solid #000000; background-color: #bfbfbf !important;">&nbsp;</td>
                @else
                <td rowspan="2" style="border-bottom: 1.5px solid #000000; border-right: 1px solid #000000; text-align: center; vertical-align: middle; padding: 1px;">
                    @php 
                        if ($subFormVariant === 'karyawan_gol4') {
                            $rawSignCol6 = $ttdByLevel['section_head']['signer_name'] ?? ($ttdByLevel['section_head']['user']['nama'] ?? '');
                            $karyName = !empty($rawSignCol6) ? trim(preg_replace('/\s*\(.*?\)/', '', $rawSignCol6)) : $shNama;
                        } elseif ($subFormVariant === 'div_head') {
                            $rawSignForeman = $ttdByLevel['foreman']['signer_name'] ?? ($ttdByLevel['foreman']['user']['nama'] ?? ($ttdByLevel['div_head']['signer_name'] ?? ($ttdByLevel['div_head']['user']['nama'] ?? '')));
                            $karyName = !empty($rawSignForeman)
                                ? trim(preg_replace('/\s*\(.*?\)/', '', $rawSignForeman))
                                : $karyawan['nama_lengkap'];
                        } else {
                            $rawSignForeman = $ttdByLevel['foreman']['user']['nama'] ?? '';
                            $karyName = !empty($rawSignForeman)
                                ? trim(preg_replace('/\s*\(.*?\)/', '', $rawSignForeman))
                                : ($isGol4Up ? $karyawan['nama_lengkap'] : ($isTanpaForemanStd ? '' : ($foremanNama ?: '-')));
                        }
                    @endphp
                    @if($isTanpaForemanStd && $subFormVariant !== 'karyawan_gol4' && empty($rawSignForeman))
                        &nbsp;
                    @else
                        <div style="font-size: 5pt; font-weight: bold; color: #0f172a; line-height: 1;">{{ $karyName }}</div>
                        @if($subFormVariant === 'karyawan_gol4')
                            <div style="font-size: 4.5pt; color: #94a3b8; line-height: 1.1; margin-top: 1px;">Section Head</div>
                        @endif
                    @endif
                </td>
                @endif
                <!-- Col 7: Section Head / Dept Head Name (rowspan 2 for rows 4 & 5) -->
                @if($subFormVariant === 'direct_div_subordinate' || $subFormVariant === 'div_head')
                <td rowspan="2" class="bg-blocked-gray" style="border-bottom: 1.5px solid #000000; border-right: 1px solid #000000; background-color: #bfbfbf !important;">&nbsp;</td>
                @elseif($subFormVariant === 'dept_head')
                <td rowspan="2" class="bg-blocked-gray" style="border-bottom: 1.5px solid #000000; border-right: 1px solid #000000; background-color: #bfbfbf !important;"></td>
                @elseif($subFormVariant === 'dept_and_div')
                <td rowspan="2" style="border-bottom: 1.5px solid #000000; border-right: 1px solid #000000; text-align: center; vertical-align: middle; padding: 1px;">
                    @php 
                        $rawSignDh = $ttdByLevel['dept_head']['signer_name'] ?? ($ttdByLevel['dept_head']['user']['nama'] ?? '');
                        $colDeptName = !empty($rawSignDh) ? trim(preg_replace('/\s*\(.*?\)/', '', $rawSignDh)) : $dhNama;
                    @endphp
                    @if(empty($colDeptName) || $colDeptName === '-')
                        <div style="font-size: 5.5pt; font-weight: bold; color: #64748b; line-height: 1;">-</div>
                        <div style="font-size: 4.5pt; color: #94a3b8; line-height: 1.1; margin-top: 1px;">Dept. Head</div>
                    @else
                        <div style="font-size: 5.5pt; font-weight: bold; color: #0f172a; line-height: 1;">{{ $colDeptName }}</div>
                        <div style="font-size: 4.5pt; color: #94a3b8; line-height: 1.1; margin-top: 1px;">Dept. Head</div>
                    @endif
                </td>
                @else
                <td rowspan="2" style="border-bottom: 1.5px solid #000000; border-right: 1px solid #000000; text-align: center; vertical-align: middle; padding: 1px;">
                    @php
                        if ($subFormVariant === 'karyawan_gol4') {
                            $rawSignSh = $ttdByLevel['dept_head']['signer_name'] ?? ($ttdByLevel['dept_head']['user']['nama'] ?? '');
                            $col7Name = !empty($rawSignSh) ? trim(preg_replace('/\s*\(.*?\)/', '', $rawSignSh)) : $dhNama;
                        } else {
                            $rawSignSh = $ttdByLevel['section_head']['signer_name'] ?? ($ttdByLevel['section_head']['user']['nama'] ?? '');
                            $col7Name = !empty($rawSignSh) ? trim(preg_replace('/\s*\(.*?\)/', '', $rawSignSh)) : ($isGol4Up ? ($dhNama ?: '') : $shNama);
                        }
                    @endphp
                    @if(empty($col7Name) || $col7Name === '-')
                        <div style="font-size: 5.5pt; font-weight: bold; color: #64748b; line-height: 1;">-</div>
                        @if($isGol4Up || $subFormVariant === 'karyawan_gol4')
                            <div style="font-size: 4.5pt; color: #94a3b8; line-height: 1.1; margin-top: 1px;">Dept. Head</div>
                        @endif
                    @else
                        <div style="font-size: 5.5pt; font-weight: bold; color: #0f172a; line-height: 1;">{{ $col7Name }}</div>
                        @if(($isGol4Up || $subFormVariant === 'karyawan_gol4') && empty($rawSignSh))
                            <div style="font-size: 4.5pt; color: #94a3b8; line-height: 1.1; margin-top: 1px;">Dept. Head</div>
                        @endif
                    @endif
                </td>
                @endif
                <!-- Col 8: Dept Head / Div Head Name (rowspan 2 for rows 4 & 5) -->
                @if($subFormVariant === 'div_head')
                <td rowspan="2" class="bg-blocked-gray" style="border-bottom: 1.5px solid #000000; border-right: none; background-color: #bfbfbf !important;"></td>
                @elseif($subFormVariant === 'direct_div_subordinate' || $subFormVariant === 'dept_and_div')
                <td rowspan="2" style="border-right: none; border-bottom: 1.5px solid #000000; text-align: center; vertical-align: middle; padding: 1px; background: #ffffff;">
                    @php
                        $rawSignDiv = $ttdByLevel['div_head']['signer_name'] ?? ($ttdByLevel['div_head']['user']['nama'] ?? '');
                        $col8Name = !empty($rawSignDiv) ? trim(preg_replace('/\s*\(.*?\)/', '', $rawSignDiv)) : $divHeadNama;
                    @endphp
                    @if(empty($col8Name) || $col8Name === '-')
                        <div style="font-size: 5.5pt; font-weight: bold; color: #64748b; line-height: 1;">-</div>
                        <div style="font-size: 4.5pt; color: #94a3b8; line-height: 1.1; margin-top: 1px;">Div. Head</div>
                    @else
                        <div style="font-size: 5.5pt; font-weight: bold; color: #0f172a; line-height: 1;">{{ $col8Name }}</div>
                        <div style="font-size: 4.5pt; color: #94a3b8; line-height: 1.1; margin-top: 1px;">Div. Head</div>
                    @endif
                </td>
                @else
                <td rowspan="2" style="border-right: none; border-bottom: 1.5px solid #000000; text-align: center; vertical-align: middle; padding: 1px;">
                    @php
                        if ($subFormVariant === 'karyawan_gol4') {
                            $rawSignDh = $ttdByLevel['div_head']['signer_name'] ?? ($ttdByLevel['div_head']['user']['nama'] ?? '');
                            $col8Name = !empty($rawSignDh) ? trim(preg_replace('/\s*\(.*?\)/', '', $rawSignDh)) : $divHeadNama;
                        } else {
                            $rawSignDh = $ttdByLevel['dept_head']['signer_name'] ?? ($ttdByLevel['dept_head']['user']['nama'] ?? '');
                            $hasDhTtd = !empty($ttdByLevel['dept_head']);
                            $divHeadFallback = trim(preg_replace('/\s*\(.*?\)/', '', (string)($karyawan['div_head_nama'] ?? '')));
                            if ($isGol4Up && (strcasecmp($divHeadFallback, $karyawan['nama_lengkap'] ?? '') === 0 || $subFormVariant === 'div_head')) {
                                $divHeadFallback = '';
                            }
                            $col8Name = !empty($rawSignDh)
                                ? trim(preg_replace('/\s*\(.*?\)/', '', $rawSignDh))
                                : ($isGol4Up ? ($divHeadFallback ?: '') : $dhNama);
                        }
                    @endphp
                    @if(empty($col8Name) || $col8Name === '-')
                        <div style="font-size: 5.5pt; font-weight: bold; color: #64748b; line-height: 1;">-</div>
                        @if($isGol4Up || $subFormVariant === 'karyawan_gol4')
                            <div style="font-size: 4.5pt; color: #94a3b8; line-height: 1.1; margin-top: 1px;">Div. Head</div>
                        @endif
                    @else
                        <div style="font-size: 5.5pt; font-weight: bold; color: #0f172a; line-height: 1;">{{ $col8Name }}</div>
                        @if(($isGol4Up || $subFormVariant === 'karyawan_gol4') && empty($rawSignDh))
                            <div style="font-size: 4.5pt; color: #94a3b8; line-height: 1.1; margin-top: 1px;">Div. Head</div>
                        @endif
                    @endif
                </td>
                @endif
            </tr>

            <!-- ROW 5 -->
            <tr>
                <!-- Col 2: TANGGAL MASUK label -->
                <td style="font-size: 6.2pt; font-weight: bold; border-right: 1px solid #cbd5e1; border-bottom: 1.5px solid #000000; padding: 1.5px 3px; white-space: nowrap; vertical-align: middle;">
                    {{ $isGol4Up ? 'TANGGAL MASUK / 入社 :' : 'TANGGAL MASUK :' }}
                </td>
                <!-- Col 3: TANGGAL MASUK value -->
                <td class="bg-excel-green font-bold" style="border-right: 1.2px solid #000000; border-bottom: 1.5px solid #000000; padding: 1.5px 3px; font-size: 6.8pt; vertical-align: middle; background: {{ $isGol4Up ? '#ffffff' : '#e2efda' }};">
                    {{ \App\Services\GoogleSyncService::formatMusashiDate($karyawan['tgl_masuk'] ?? '') }}
                </td>
                <!-- Col 1, 4, 5, 6, 7, 8 all covered by rowspans spanning into row 5! -->
            </tr>
        </table>

        <!-- ========================================================================= -->
        <!-- TWO-COLUMN MAIN BODY: LEFT (58%) vs RIGHT (42%)                           -->
        <!-- ========================================================================= -->
        <div>
            <!-- LEFT COLUMN (FLOAT LEFT) -->
            <div style="width: 58%; float: left; border-right: 1.5px solid #000000;">
                
                <table style="width: 100%; border: none;">
                    <thead>
                        <tr class="bg-gray-bar font-bold text-center" style="font-size: 6.5pt;">
                            <th colspan="2" rowspan="2" style="border-top: none; border-left: none; width: 62%;">
                                {{ $isGol4Up ? 'ASPEK PENILAIAN / 評価項目' : 'ASPEK PENILAIAN' }}
                            </th>
                            <th colspan="3" style="border-top: none; border-right: none; width: 38%; padding: 1px 0;">
                                {{ $isGol4Up ? 'PENILAIAN / 点数' : 'PENILAIAN' }}
                            </th>
                        </tr>
                        <tr class="bg-gray-header font-bold text-center" style="font-size: 5.5pt;">
                            @if($subFormVariant === 'dept_and_div')
                            <th class="bg-blocked-gray" style="width: 12%; padding: 1px; background-color: #bfbfbf !important;"></th>
                            <th style="width: 13%; padding: 1px;">Dept. Head</th>
                            <th style="width: 13%; border-right: none; padding: 1px;">Div. Head</th>
                            @elseif($subFormVariant === 'direct_div_subordinate')
                            <th class="bg-blocked-gray" style="width: 12%; padding: 1px; background-color: #bfbfbf !important;"></th>
                            <th class="bg-blocked-gray" style="width: 13%; padding: 1px; background-color: #bfbfbf !important;"></th>
                            <th style="width: 13%; border-right: none; padding: 1px;">Division Head</th>
                            @elseif($subFormVariant === 'div_head')
                            <th class="bg-blocked-gray" style="width: 12%; padding: 1px; background-color: #bfbfbf !important;"></th>
                            <th class="bg-blocked-gray" style="width: 13%; padding: 1px; background-color: #bfbfbf !important;"></th>
                            <th style="width: 13%; border-right: none; padding: 1px;">Mandiri / 自分</th>
                            @elseif($subFormVariant === 'karyawan_gol4')
                            <th style="width: 12%; padding: 1px;">Sect. Head</th>
                            <th style="width: 13%; padding: 1px;">Dept. head</th>
                            <th style="width: 13%; border-right: none; padding: 1px;">Div. Head</th>
                            @elseif($isGol4Up)
                            <th style="width: 12%; padding: 1px;">Mandiri / 自分</th>
                            <th style="width: 13%; padding: 1px;">Dept. head</th>
                            <th style="width: 13%; border-right: none; padding: 1px;">Div. Head</th>
                            @elseif($subFormVariant === 'dept_head')
                            <th style="width: 12%; padding: 1px;">{{ $col1Title }}</th>
                            <th class="bg-blocked-gray" style="width: 13%; padding: 1px;"></th>
                            <th style="width: 13%; border-right: none; padding: 1px;">{{ $col3Title }}</th>
                            @else
                            <th style="width: 12%; padding: 1px;">{{ $col1Title }}</th>
                            <th style="width: 13%; padding: 1px;">{{ $col2Title }}</th>
                            <th style="width: 13%; border-right: none; padding: 1px;">{{ $col3Title }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($formStructure['groups'] as $groupKey => $group)
                            
                            @php $isFirstInGroup = true; @endphp
                            @foreach($group['aspects'] as $aspectCode => $aspectName)
                            @php
                                // Clean aspect names for 07-006 (e.g. Plan instead of Plan (Perencanaan))
                                $displayAspectName = $aspectName;
                                if ($isGol4Up) {
                                    if ($aspectCode === 'PK_PLAN') $displayAspectName = 'Plan';
                                    elseif ($aspectCode === 'PK_DO') $displayAspectName = 'Do';
                                    elseif ($aspectCode === 'PK_CHECK') $displayAspectName = 'Check';
                                    elseif ($aspectCode === 'PK_ACTION') $displayAspectName = 'Action';
                                    elseif ($aspectCode === 'MM_KEPUTUSAN') $displayAspectName = 'Pengambil Keputusan / 決まる能力';
                                    elseif ($aspectCode === 'MM_PENGEMBANGAN') $displayAspectName = 'Pengembangan Bawahan (Counching & Counseling) / 部下の発展の能力';
                                    elseif ($aspectCode === 'SK_KOMUNIKASI') $displayAspectName = 'Komunikasi & kerjasama / コミュニケーション';
                                }
                            @endphp
                            <tr>
                                @if($isFirstInGroup)
                                <td rowspan="{{ count($group['aspects']) }}" class="text-center font-bold uppercase" style="width: 25%; font-size: {{ $isGol4Up ? '6.2pt' : '6.2pt' }}; border-left: none; padding: 2px 1px; background: #ffffff;">
                                    @if($isGol4Up)
                                        @php
                                            $parts = explode('/', $group['name']);
                                        @endphp
                                        <div style="font-weight: bold; line-height: 1.15;">{{ trim($parts[0] ?? $group['name']) }}</div>
                                        @if(isset($parts[1]))
                                            <div style="font-size: 5.5pt; color: #334155; line-height: 1;">{{ trim($parts[1]) }}</div>
                                        @endif
                                    @else
                                        {{ $group['name'] }}
                                    @endif
                                </td>
                                @php $isFirstInGroup = false; @endphp
                                @endif

                                <td style="width: 37%; font-size: 6.5pt; padding: 1.5px 3px;">
                                    {{ $displayAspectName }}
                                </td>

                                @if($subFormVariant === 'dept_and_div')
                                <td class="bg-blocked-gray" style="padding: 1px; background-color: #bfbfbf !important;"></td>
                                <td class="text-center font-mono font-bold" style="font-size: 7pt; padding: 1px;">
                                    {{ $formatScoreCell($scoresByLevel['dept_head'][$aspectCode] ?? null) }}
                                </td>
                                <td class="text-center font-mono font-bold" style="font-size: 7pt; border-right: none; padding: 1px;">
                                    {{ $formatScoreCell($scoresByLevel['div_head'][$aspectCode] ?? null) }}
                                </td>
                                @elseif($subFormVariant === 'direct_div_subordinate')
                                <td class="bg-blocked-gray" style="padding: 1px; background-color: #bfbfbf !important;"></td>
                                <td class="bg-blocked-gray" style="padding: 1px; background-color: #bfbfbf !important;"></td>
                                <td class="text-center font-mono font-bold" style="font-size: 7pt; border-right: none; padding: 1px;">
                                    {{ $formatScoreCell($scoresByLevel['div_head'][$aspectCode] ?? null) }}
                                </td>
                                @elseif($subFormVariant === 'div_head')
                                <td class="bg-blocked-gray" style="padding: 1px; background-color: #bfbfbf !important;"></td>
                                <td class="bg-blocked-gray" style="padding: 1px; background-color: #bfbfbf !important;"></td>
                                <td class="text-center font-mono font-bold" style="font-size: 7pt; border-right: none; padding: 1px;">
                                    {{ $formatScoreCell($scoresByLevel['dept_head'][$aspectCode] ?? ($scoresByLevel['foreman'][$aspectCode] ?? null)) }}
                                </td>
                                @elseif($subFormVariant === 'karyawan_gol4')
                                <td class="text-center font-mono font-bold" style="font-size: 7pt; padding: 1px;">
                                    {{ $formatScoreCell($scoresByLevel['section_head'][$aspectCode] ?? null) }}
                                </td>
                                <td class="text-center font-mono font-bold" style="font-size: 7pt; padding: 1px;">
                                    {{ $formatScoreCell($scoresByLevel['dept_head'][$aspectCode] ?? null) }}
                                </td>
                                <td class="text-center font-mono font-bold" style="font-size: 7pt; border-right: none; padding: 1px;">
                                    {{ $formatScoreCell($scoresByLevel['div_head'][$aspectCode] ?? null) }}
                                </td>
                                @elseif($isGol4Up)
                                <td class="text-center font-mono font-bold" style="font-size: 7pt; padding: 1px;">
                                    {{ $formatScoreCell($scoresByLevel['foreman'][$aspectCode] ?? null) }}
                                </td>
                                <td class="text-center font-mono font-bold" style="font-size: 7pt; padding: 1px;">
                                    {{ $formatScoreCell($scoresByLevel['section_head'][$aspectCode] ?? null) }}
                                </td>
                                <td class="text-center font-mono font-bold" style="font-size: 7pt; border-right: none; padding: 1px;">
                                    {{ $formatScoreCell($scoresByLevel['dept_head'][$aspectCode] ?? null) }}
                                </td>
                                @elseif($subFormVariant === 'dept_head')
                                <td class="text-center font-mono font-bold bg-excel-green" style="font-size: 7pt; padding: 1px;">
                                    {{ $formatScoreCell($scoresByLevel['foreman'][$aspectCode] ?? null) }}
                                </td>
                                <td class="bg-blocked-gray" style="padding: 1px;"></td>
                                <td class="text-center font-mono font-bold" style="font-size: 7pt; border-right: none; padding: 1px;">
                                    {{ $formatScoreCell($scoresByLevel['dept_head'][$aspectCode] ?? null) }}
                                </td>
                                @else
                                <td class="text-center font-mono font-bold" style="font-size: 7pt; padding: 1px;">
                                    {{ ($isTanpaForemanStd && !$showForemanData) ? '' : $formatScoreCell($scoresByLevel['foreman'][$aspectCode] ?? null) }}
                                </td>
                                <td class="text-center font-mono font-bold" style="font-size: 7pt; padding: 1px;">
                                    {{ $formatScoreCell($scoresByLevel['section_head'][$aspectCode] ?? null) }}
                                </td>
                                <td class="text-center font-mono font-bold" style="font-size: 7pt; border-right: none; padding: 1px;">
                                    {{ $formatScoreCell($scoresByLevel['dept_head'][$aspectCode] ?? null) }}
                                </td>
                                @endif
                            </tr>
                            @endforeach

                            <!-- Group TOTAL Row -->
                            <tr class="font-bold text-center" style="font-size: 6.2pt; background: #ffffff;">
                                <td colspan="2" class="text-right uppercase" style="border-left: none; padding: 1.5px 4px;">{{ $isGol4Up ? 'TOTAL 合計' : 'TOTAL' }}</td>
                                @if($subFormVariant === 'dept_and_div')
                                <td class="bg-blocked-gray" style="background-color: #bfbfbf !important;"></td>
                                <td class="font-mono">{{ $formatTotalCell($calcDeptHead['groups'][$groupKey]['total_sum'] ?? null) }}</td>
                                <td class="font-mono" style="border-right: none;">{{ $formatTotalCell($calcDivHead['groups'][$groupKey]['total_sum'] ?? null) }}</td>
                                @elseif($subFormVariant === 'direct_div_subordinate')
                                <td class="bg-blocked-gray" style="background-color: #bfbfbf !important;"></td>
                                <td class="bg-blocked-gray" style="background-color: #bfbfbf !important;"></td>
                                <td class="font-mono" style="border-right: none;">{{ $formatTotalCell($calcDivHead['groups'][$groupKey]['total_sum'] ?? null) }}</td>
                                @elseif($subFormVariant === 'div_head')
                                <td class="bg-blocked-gray" style="background-color: #bfbfbf !important;"></td>
                                <td class="bg-blocked-gray" style="background-color: #bfbfbf !important;"></td>
                                <td class="font-mono" style="border-right: none;">{{ $formatTotalCell($calcDeptHead['groups'][$groupKey]['total_sum'] ?? null) }}</td>
                                @elseif($subFormVariant === 'karyawan_gol4')
                                <td class="font-mono">{{ $formatTotalCell($calcSectionHead['groups'][$groupKey]['total_sum'] ?? null) }}</td>
                                <td class="font-mono">{{ $formatTotalCell($calcDeptHead['groups'][$groupKey]['total_sum'] ?? null) }}</td>
                                <td class="font-mono" style="border-right: none;">{{ $formatTotalCell($calcDivHead['groups'][$groupKey]['total_sum'] ?? null) }}</td>
                                @elseif($isGol4Up)
                                <td class="font-mono">{{ $formatTotalCell($calcForeman['groups'][$groupKey]['total_sum'] ?? null) }}</td>
                                <td class="font-mono">{{ $formatTotalCell($calcSectionHead['groups'][$groupKey]['total_sum'] ?? null) }}</td>
                                <td class="font-mono" style="border-right: none;">{{ $formatTotalCell($calcDeptHead['groups'][$groupKey]['total_sum'] ?? null) }}</td>
                                @elseif($subFormVariant === 'dept_head')
                                <td class="font-mono">{{ $formatTotalCell($calcForeman['groups'][$groupKey]['total_sum'] ?? null) }}</td>
                                <td class="bg-blocked-gray"></td>
                                <td class="font-mono" style="border-right: none;">{{ $formatTotalCell($calcDeptHead['groups'][$groupKey]['total_sum'] ?? null) }}</td>
                                @else
                                <td class="font-mono">{!! ($isTanpaForemanStd && !$showForemanData) ? '&nbsp;' : $formatTotalCell($calcForeman['groups'][$groupKey]['total_sum'] ?? null) !!}</td>
                                <td class="font-mono">{{ $formatTotalCell($calcSectionHead['groups'][$groupKey]['total_sum'] ?? null) }}</td>
                                <td class="font-mono" style="border-right: none;">{{ $formatTotalCell($calcDeptHead['groups'][$groupKey]['total_sum'] ?? null) }}</td>
                                @endif
                            </tr>

                            @php
                                $rataTitle = match(true) {
                                    $isGol4Up && $groupKey === 'hasil_kerja' => 'RATA - RATA HASIL KERJA (Total Nilai dibagi 5) / 仕事の成果の平均（合計値を5で割ったもの）',
                                    $isGol4Up && $groupKey === 'proses_kerja' => 'RATA - RATA PROSES KERJA (Total Nilai dibagi 4) 作業プロセスの平均（合計値を4で割ったもの）',
                                    $isGol4Up && $groupKey === 'man_management' => 'RATA - RATA MANAGEMENT (Total Nilai dibagi 4) 平均管理（合計値を4で割ったもの',
                                    $isGol4Up && $groupKey === 'sikap_kerja' => 'RATA - RATA SIKAP KERJA (Total Nilai dibagi 3) 仕事の平均（合計値を3で割ったもの',
                                    default => 'RATA - RATA ' . strtoupper(explode('(', $group['name'])[0]) . ' (Total Nilai dibagi ' . count($group['aspects']) . ')',
                                };
                                $nilaiAkhirTitle = match(true) {
                                    $isGol4Up && $groupKey === 'hasil_kerja' => 'NILAI AKHIR HASIL KERJA (Rata-Rata Hasil Kerja X 55%) / 仕事成績の最終得点 平均労働記述X 55%)',
                                    $isGol4Up && $groupKey === 'proses_kerja' => 'NILAI AKHIR PROSES KERJA (Rata-Rata Proses Kerja X 15%) / 最終的な作業手順（平均作業手順X 15%)',
                                    $isGol4Up && $groupKey === 'man_management' => 'NILAI AKHIR MANAGEMENT (Rata-Rata Sikap Kerja X 15%) ファイナルバリユーマネジメント（平均作業姿勢X 15%)',
                                    $isGol4Up && $groupKey === 'sikap_kerja' => 'NILAI AKHIR SIKAP KERJA (Rata-Rata Sikap Kerja X 15%) 職場における最終的な価値（平均職務態度X 15%）',
                                    default => 'NILAI AKHIR ' . strtoupper(explode('(', $group['name'])[0]) . ' (Rata-Rata ' . explode('(', $group['name'])[0] . ' X ' . $group['weight'] . '%)',
                                };
                            @endphp

                            <!-- Group RATA - RATA Row -->
                            <tr class="font-bold text-center" style="font-size: 5.5pt; background: #ffffff;">
                                <td colspan="2" class="text-left" style="border-left: none; padding: 1.5px 3px;">
                                    {{ $rataTitle }}
                                </td>
                                @if($subFormVariant === 'dept_and_div')
                                <td class="bg-blocked-gray" style="background-color: #bfbfbf !important;"></td>
                                <td class="font-mono">{{ $formatDecimalCell($calcDeptHead['groups'][$groupKey]['average'] ?? null) }}</td>
                                <td class="font-mono" style="border-right: none;">{{ $formatDecimalCell($calcDivHead['groups'][$groupKey]['average'] ?? null) }}</td>
                                @elseif($subFormVariant === 'direct_div_subordinate')
                                <td class="bg-blocked-gray" style="background-color: #bfbfbf !important;"></td>
                                <td class="bg-blocked-gray" style="background-color: #bfbfbf !important;"></td>
                                <td class="font-mono" style="border-right: none;">{{ $formatDecimalCell($calcDivHead['groups'][$groupKey]['average'] ?? null) }}</td>
                                @elseif($subFormVariant === 'div_head')
                                <td class="bg-blocked-gray" style="background-color: #bfbfbf !important;"></td>
                                <td class="bg-blocked-gray" style="background-color: #bfbfbf !important;"></td>
                                <td class="font-mono" style="border-right: none;">{{ $formatDecimalCell($calcDeptHead['groups'][$groupKey]['average'] ?? null) }}</td>
                                @elseif($subFormVariant === 'karyawan_gol4')
                                <td class="font-mono">{{ $formatDecimalCell($calcSectionHead['groups'][$groupKey]['average'] ?? null) }}</td>
                                <td class="font-mono">{{ $formatDecimalCell($calcDeptHead['groups'][$groupKey]['average'] ?? null) }}</td>
                                <td class="font-mono" style="border-right: none;">{{ $formatDecimalCell($calcDivHead['groups'][$groupKey]['average'] ?? null) }}</td>
                                @elseif($isGol4Up)
                                <td class="font-mono">{{ $formatDecimalCell($calcForeman['groups'][$groupKey]['average'] ?? null) }}</td>
                                <td class="font-mono">{{ $formatDecimalCell($calcSectionHead['groups'][$groupKey]['average'] ?? null) }}</td>
                                <td class="font-mono" style="border-right: none;">{{ $formatDecimalCell($calcDeptHead['groups'][$groupKey]['average'] ?? null) }}</td>
                                @elseif($subFormVariant === 'dept_head')
                                <td class="font-mono">{{ $formatDecimalCell($calcForeman['groups'][$groupKey]['average'] ?? null) }}</td>
                                <td class="bg-blocked-gray"></td>
                                <td class="font-mono" style="border-right: none;">{{ $formatDecimalCell($calcDeptHead['groups'][$groupKey]['average'] ?? null) }}</td>
                                @else
                                <td class="font-mono">{!! ($isTanpaForemanStd && !$showForemanData) ? '&nbsp;' : $formatDecimalCell($calcForeman['groups'][$groupKey]['average'] ?? null) !!}</td>
                                <td class="font-mono">{{ $formatDecimalCell($calcSectionHead['groups'][$groupKey]['average'] ?? null) }}</td>
                                <td class="font-mono" style="border-right: none;">{{ $formatDecimalCell($calcDeptHead['groups'][$groupKey]['average'] ?? null) }}</td>
                                @endif
                            </tr>

                            <!-- Group NILAI AKHIR Row -->
                            <tr class="font-black text-center" style="font-size: 5.5pt; background: #f8fafc;">
                                <td colspan="2" class="text-left" style="border-left: none; padding: 1.5px 3px;">
                                    {{ $nilaiAkhirTitle }}
                                </td>
                                @if($subFormVariant === 'dept_and_div')
                                <td class="bg-blocked-gray" style="background-color: #bfbfbf !important;"></td>
                                <td class="font-mono" style="background: #f1f5f9;">{{ $formatDecimalCell($calcDeptHead['groups'][$groupKey]['weighted_score'] ?? null) }}</td>
                                <td class="font-mono" style="background: #f1f5f9; border-right: none;">{{ $formatDecimalCell($calcDivHead['groups'][$groupKey]['weighted_score'] ?? null) }}</td>
                                @elseif($subFormVariant === 'direct_div_subordinate')
                                <td class="bg-blocked-gray" style="background-color: #bfbfbf !important;"></td>
                                <td class="bg-blocked-gray" style="background-color: #bfbfbf !important;"></td>
                                <td class="font-mono" style="background: #f1f5f9; border-right: none;">{{ $formatDecimalCell($calcDivHead['groups'][$groupKey]['weighted_score'] ?? null) }}</td>
                                @elseif($subFormVariant === 'div_head')
                                <td class="bg-blocked-gray" style="background-color: #bfbfbf !important;"></td>
                                <td class="bg-blocked-gray" style="background-color: #bfbfbf !important;"></td>
                                <td class="font-mono" style="background: #f1f5f9; border-right: none;">{{ $formatDecimalCell($calcDeptHead['groups'][$groupKey]['weighted_score'] ?? null) }}</td>
                                @elseif($subFormVariant === 'karyawan_gol4')
                                <td class="font-mono" style="background: #f1f5f9;">{{ $formatDecimalCell($calcSectionHead['groups'][$groupKey]['weighted_score'] ?? null) }}</td>
                                <td class="font-mono" style="background: #f1f5f9;">{{ $formatDecimalCell($calcDeptHead['groups'][$groupKey]['weighted_score'] ?? null) }}</td>
                                <td class="font-mono" style="background: #f1f5f9; border-right: none;">{{ $formatDecimalCell($calcDivHead['groups'][$groupKey]['weighted_score'] ?? null) }}</td>
                                @elseif($isGol4Up)
                                <td class="font-mono" style="background: #f1f5f9;">{{ $formatDecimalCell($calcForeman['groups'][$groupKey]['weighted_score'] ?? null) }}</td>
                                <td class="font-mono" style="background: #f1f5f9;">{{ $formatDecimalCell($calcSectionHead['groups'][$groupKey]['weighted_score'] ?? null) }}</td>
                                <td class="font-mono" style="background: #f1f5f9; border-right: none;">{{ $formatDecimalCell($calcDeptHead['groups'][$groupKey]['weighted_score'] ?? null) }}</td>
                                @elseif($subFormVariant === 'dept_head')
                                <td class="font-mono" style="background: #f1f5f9;">{{ $formatDecimalCell($calcForeman['groups'][$groupKey]['weighted_score'] ?? null) }}</td>
                                <td class="bg-blocked-gray"></td>
                                <td class="font-mono" style="background: #f1f5f9; border-right: none;">{{ $formatDecimalCell($calcDeptHead['groups'][$groupKey]['weighted_score'] ?? null) }}</td>
                                @else
                                <td class="font-mono" style="background: #f1f5f9;">{!! ($isTanpaForemanStd && !$showForemanData) ? '&nbsp;' : $formatDecimalCell($calcForeman['groups'][$groupKey]['weighted_score'] ?? null) !!}</td>
                                <td class="font-mono" style="background: #f1f5f9;">{{ $formatDecimalCell($calcSectionHead['groups'][$groupKey]['weighted_score'] ?? null) }}</td>
                                <td class="font-mono" style="background: #f1f5f9; border-right: none;">{{ $formatDecimalCell($calcDeptHead['groups'][$groupKey]['weighted_score'] ?? null) }}</td>
                                @endif
                            </tr>

                        @endforeach
                    </tbody>
                </table>

                <!-- Bottom Notes & Equation Box -->
                <div style="padding: 3px; border-top: 1px solid #000000;">
                    <table style="width: 100%; border: none; font-size: 5.5pt; margin-bottom: 2px;">
                        <tr>
                            <td style="border: none; padding: 0;">{{ $isGol4Up ? 'Tanggal 日付 :' : 'Tanggal :' }} {{ date('d F Y') }}</td>
                            <td style="border: none; padding: 0; text-align: right;">Form: {{ $kodeForm }}</td>
                        </tr>
                    </table>

                    <table style="width: 100%; border: none; border-collapse: collapse; font-size: 5.2pt; line-height: 1.15; color: #475569; margin-bottom: 2px; font-style: italic;">
                        <tr>
                            <td style="border: none; padding: 0 4px 0 0; font-weight: bold; white-space: nowrap; vertical-align: top; width: 44px;">CATATAN :</td>
                            <td style="border: none; padding: 0 2px 0 0; font-weight: bold; white-space: nowrap; vertical-align: top; width: 12px;">1.</td>
                            <td style="border: none; padding: 0; vertical-align: top;">
                                Jika nilai pecahan, ditentukan dua angka dibelakang koma.
                                @if($isGol4Up)<div style="font-size: 4.8pt; font-style: normal;">注：1.値が小数点以下の場合は、コンマの後ろに2つの数値が決まります</div>@endif
                            </td>
                        </tr>
                        <tr>
                            <td style="border: none; padding: 0;"></td>
                            <td style="border: none; padding: 0 2px 0 0; font-weight: bold; white-space: nowrap; vertical-align: top; width: 12px;">2.</td>
                            <td style="border: none; padding: 0; vertical-align: top;">
                                Pembulatan : &le; 0,5 pembulatan ke bawah ; &gt; 0,5 pembulatan ke atas.
                                @if($isGol4Up)<div style="font-size: 4.8pt; font-style: normal;">2. 丸め：0.5以下 切り捨て；0.5 を切り上げます。</div>@endif
                            </td>
                        </tr>
                    </table>

                    @php
                        $fmtClean = function($val) {
                            $n = (float)($val ?? 0);
                            return $n == 0 ? '' : (fmod($n, 1) == 0 ? number_format($n, 0) : number_format($n, 2));
                        };
                    @endphp

                    <!-- PENJUMLAHAN PENILAIAN Box (Sesuai Scan Resmi) -->
                    <div style="border: 1.2px solid #000000; padding: 2.5px; background-color: #ffffff;">
                        <div style="font-size: 6pt; font-weight: bold; text-transform: uppercase; margin-bottom: 1.5px;">
                            PENJUMLAHAN PENILAIAN
                        </div>
                        
                        <table style="border: none; width: 100%; font-size: 6pt; font-weight: bold;">
                            <tr>
                                <td style="border: none; padding: 0; text-align: center;">
                                    <div style="font-size: 4.8pt; color: #334155;">HASIL KERJA</div>
                                    @if($isGol4Up)<div style="font-size: 4.2pt; color: #64748b;">仕事の結果</div>@endif
                                    <div style="border: 1px solid #000000; background: #ffffff; padding: 2px 4px; font-family: monospace; font-size: 7.5pt; font-weight: bold; display: inline-block; margin-top: 1px; min-width: 28px; min-height: 12px;">
                                        {{ $fmtClean($calcActive['groups']['hasil_kerja']['weighted_score'] ?? null) }}
                                    </div>
                                </td>
                                
                                <td style="border: none; padding: 0; font-size: 8pt; font-weight: bold;">+</td>

                                <td style="border: none; padding: 0; text-align: center;">
                                    <div style="font-size: 4.8pt; color: #334155;">PROSES KERJA</div>
                                    @if($isGol4Up)<div style="font-size: 4.2pt; color: #64748b;">仕事の方法</div>@endif
                                    <div style="border: 1px solid #000000; background: #ffffff; padding: 2px 4px; font-family: monospace; font-size: 7.5pt; font-weight: bold; display: inline-block; margin-top: 1px; min-width: 28px; min-height: 12px;">
                                        {{ $fmtClean($calcActive['groups']['proses_kerja']['weighted_score'] ?? null) }}
                                    </div>
                                </td>

                                @if(isset($formStructure['groups']['man_management']))
                                <td style="border: none; padding: 0; font-size: 8pt; font-weight: bold;">+</td>
                                <td style="border: none; padding: 0; text-align: center;">
                                    <div style="font-size: 4.8pt; color: #334155;">PEOPLE MAN</div>
                                    @if($isGol4Up)<div style="font-size: 4.2pt; color: #64748b;">人材管理</div>@endif
                                    <div style="border: 1px solid #000000; background: #ffffff; padding: 2px 4px; font-family: monospace; font-size: 7.5pt; font-weight: bold; display: inline-block; margin-top: 1px; min-width: 28px; min-height: 12px;">
                                        {{ $fmtClean($calcActive['groups']['man_management']['weighted_score'] ?? null) }}
                                    </div>
                                </td>
                                @endif

                                <td style="border: none; padding: 0; font-size: 8pt; font-weight: bold;">+</td>

                                <td style="border: none; padding: 0; text-align: center;">
                                    <div style="font-size: 4.8pt; color: #334155;">SIKAP KERJA</div>
                                    @if($isGol4Up)<div style="font-size: 4.2pt; color: #64748b;">仕事に関する性格</div>@endif
                                    <div style="border: 1px solid #000000; background: #ffffff; padding: 2px 4px; font-family: monospace; font-size: 7.5pt; font-weight: bold; display: inline-block; margin-top: 1px; min-width: 28px; min-height: 12px;">
                                        {{ $fmtClean($calcActive['groups']['sikap_kerja']['weighted_score'] ?? null) }}
                                    </div>
                                </td>

                                <td style="border: none; padding: 0; font-size: 8pt; font-weight: bold;">=</td>

                                <td style="border: none; padding: 0; text-align: center;">
                                    <div style="font-size: 4.8pt; font-weight: bold; color: #000000;">TOTAL</div>
                                    @if($isGol4Up)<div style="font-size: 4.2pt; color: #64748b;">合計</div>@endif
                                    <div style="border: 1px solid #000000; background: #ffffff; padding: 2px 5px; font-family: monospace; font-size: 8.5pt; font-weight: bold; display: inline-block; margin-top: 1px; min-width: 32px; min-height: 12px;">
                                        {{ $fmtClean($calcActive['grand_total'] ?? null) }}
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

            </div>

            <!-- RIGHT COLUMN (FLOAT RIGHT) -->
            <div style="width: 41.8%; float: right;">
                
                <!-- Header Bar -->
                <div class="bg-gray-bar text-center font-bold uppercase" style="padding: 2px; font-size: 6.5pt; border-bottom: 1px solid #000000; background-color: #d9d9d9 !important;">
                    {{ $isGol4Up ? 'INDIKATOR EVALUASI POTENSI KARYAWAN / 従業員の発展' : 'INDIKATOR EVALUASI POTENSI KARYAWAN' }}
                </div>

                <!-- Petunjuk Pengisian -->
                <div style="padding: 2px 4px; border-bottom: 1px solid #cbd5e1; background: #f8fafc; font-size: 5.6pt; line-height: 1.15;">
                    <span style="font-weight: bold;">{{ $isGol4Up ? 'Petunjuk Pengisian / 記入方法' : 'Petunjuk Pengisian.' }}</span>
                    <div style="text-align: justify; color: #334155;">
                        Setiap Atasan diharapkan mengisi dengan seksama kolom ini untuk mengetahui seberapa jauh potensi karyawan dapat dikembangkan ke posisi &amp; tanggung jawab yang lebih besar sesuai dengan ketentuan yang berlaku dan selagi ada kesempatan dalam struktur organisasi.
                    </div>
                    @if($isGol4Up)
                    <div style="color: #475569; font-size: 5.1pt; margin-top: 1px;">
                        各上司が部下の能力を把握し今より高い職位ともっと重い責任を任せれるかどうかを記入する
                    </div>
                    @endif
                </div>

                @php
                    $catatanPotensiDisplay = '';
                    if (!empty($commentMeta['allCatatanPotensi'])) {
                        $catParts = [];
                        foreach ($commentMeta['allCatatanPotensi'] as $c) {
                            $catParts[] = $c['role_label'] . ': ' . $c['text'];
                        }
                        $catatanPotensiDisplay = implode('; ', $catParts);
                    } else {
                        $catatanPotensiDisplay = $indikator['catatan_potensi'] ?? '';
                    }
                    $pilPotensi = (int)($indikator['pilihan_potensi'] ?? 0);
                @endphp

                <!-- PILIHAN POTENSI -->
                @if($isGol4Up)
                <!-- 4 KOTAK PILIHAN POTENSI BILINGUAL (SESUAI SCAN EXCEL RESMI FORM 07-006) -->
                <div style="padding: 2px 3px; font-size: 5.3pt;">
                    
                    <!-- Kotak 1 -->
                    <div style="border: 1px solid #000000; padding: 1.5px 3px; margin-bottom: 2px; background: #ffffff;">
                        <table style="width: 100%; border: none; border-collapse: collapse;">
                            <tr>
                                <td style="border: none; padding: 0 4px 0 0; width: 13px; vertical-align: top;">
                                    <span class="potensi-box {{ $pilPotensi == 1 ? 'checked' : 'unchecked' }}">{!! $pilPotensi == 1 ? '&#10003;' : '&nbsp;' !!}</span>
                                </td>
                                <td style="border: none; padding: 0; vertical-align: top;">
                                    <div style="font-size: 5.3pt; line-height: 1.15; color: #000000;">
                                        Memenuhi standard untuk dipromosikan pada level diatasnya, saran pada posisi :
                                    </div>
                                    <div style="font-size: 4.7pt; color: #334155; line-height: 1.1; margin-top: 0.5px;">
                                        能力が高く、今より上のレベルに上がれる。お勧めとなる職位は
                                    </div>
                                    <div class="line-underline" style="border-bottom: 0.8px solid #000000; min-height: 9px; margin-top: 1px;">
                                        @if($pilPotensi == 1 && !empty($catatanPotensiDisplay))
                                            <span style="color: #000000; font-weight: normal; font-size: 5.5pt;">{{ $catatanPotensiDisplay }}</span>
                                        @else
                                            &nbsp;
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Kotak 2 -->
                    <div style="border: 1px solid #000000; padding: 1.5px 3px; margin-bottom: 2px; background: #ffffff;">
                        <table style="width: 100%; border: none; border-collapse: collapse;">
                            <tr>
                                <td style="border: none; padding: 0 4px 0 0; width: 13px; vertical-align: top;">
                                    <span class="potensi-box {{ $pilPotensi == 2 ? 'checked' : 'unchecked' }}">{!! $pilPotensi == 2 ? '&#10003;' : '&nbsp;' !!}</span>
                                </td>
                                <td style="border: none; padding: 0; vertical-align: top;">
                                    <div style="font-size: 5.3pt; line-height: 1.15; color: #000000;">
                                        Sesuai dengan posisi saat ini dan masih bisa dikembangkan dengan pengalaman dan pelatihan sesuai matriks.
                                    </div>
                                    <div style="font-size: 4.7pt; color: #334155; line-height: 1.1; margin-top: 0.5px;">
                                        今の能力よりもまだ発展することがなるためトレーニングと経験を高める為に今のままでいい
                                    </div>
                                    <div class="line-underline" style="border-bottom: 0.8px solid #000000; min-height: 9px; margin-top: 1px;">
                                        @if($pilPotensi == 2 && !empty($catatanPotensiDisplay))
                                            <span style="color: #000000; font-weight: normal; font-size: 5.5pt;">{{ $catatanPotensiDisplay }}</span>
                                        @else
                                            &nbsp;
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Kotak 3 -->
                    <div style="border: 1px solid #000000; padding: 1.5px 3px; margin-bottom: 2px; background: #ffffff;">
                        <table style="width: 100%; border: none; border-collapse: collapse;">
                            <tr>
                                <td style="border: none; padding: 0 4px 0 0; width: 13px; vertical-align: top;">
                                    <span class="potensi-box {{ $pilPotensi == 3 ? 'checked' : 'unchecked' }}">{!! $pilPotensi == 3 ? '&#10003;' : '&nbsp;' !!}</span>
                                </td>
                                <td style="border: none; padding: 0; vertical-align: top;">
                                    <div style="font-size: 5.3pt; line-height: 1.15; color: #000000;">
                                        Sesuai untuk posisi saat ini dan tidak bisa dikembangkan lagi, alasan :
                                    </div>
                                    <div style="font-size: 4.7pt; color: #334155; line-height: 1.1; margin-top: 0.5px;">
                                        能力のキャパがマックスであり発揮する事は来ないので今のままで残す理由：
                                    </div>
                                    <div class="line-underline" style="border-bottom: 0.8px solid #000000; min-height: 9px; margin-top: 1px;">
                                        @if($pilPotensi == 3 && !empty($catatanPotensiDisplay))
                                            <span style="color: #000000; font-weight: normal; font-size: 5.5pt;">{{ $catatanPotensiDisplay }}</span>
                                        @else
                                            &nbsp;
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Kotak 4 -->
                    <div style="border: 1px solid #000000; padding: 1.5px 3px; margin-bottom: 1.5px; background: #ffffff;">
                        <table style="width: 100%; border: none; border-collapse: collapse;">
                            <tr>
                                <td style="border: none; padding: 0 4px 0 0; width: 13px; vertical-align: top;">
                                    <span class="potensi-box {{ $pilPotensi == 4 ? 'checked' : 'unchecked' }}">{!! $pilPotensi == 4 ? '&#10003;' : '&nbsp;' !!}</span>
                                </td>
                                <td style="border: none; padding: 0; vertical-align: top;">
                                    <div style="font-size: 5.3pt; line-height: 1.15; color: #000000;">
                                        Yang bersangkutan kurang sesuai dengan posisi saat ini, dengan alasan :
                                    </div>
                                    <div style="font-size: 4.7pt; color: #334155; line-height: 1.1; margin-top: 0.5px;">
                                        現在の職位に合わない。その理由：
                                    </div>
                                    <div class="line-underline" style="border-bottom: 0.8px solid #000000; min-height: 9px; margin-top: 1px;">
                                        @if($pilPotensi == 4 && !empty($catatanPotensiDisplay))
                                            <span style="color: #000000; font-weight: normal; font-size: 5.5pt;">{{ $catatanPotensiDisplay }}</span>
                                        @else
                                            &nbsp;
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>

                </div>
                @else
                <!-- 4 PILIHAN POTENSI (STANDAR GOLONGAN 1 - 3) -->
                <div style="padding: 2.5px 4px; font-size: 5.9pt;">
                    
                    <!-- Option 1 -->
                    <div style="margin-bottom: 2.5px;">
                        <table style="width: 100%; border: none; border-collapse: collapse;">
                            <tr>
                                <td style="border: none; padding: 0 4px 0 0; width: 13px; vertical-align: top;">
                                    <span class="potensi-box {{ $pilPotensi == 1 ? 'checked' : 'unchecked' }}">{!! $pilPotensi == 1 ? '&#10003;' : '&nbsp;' !!}</span>
                                </td>
                                <td style="border: none; padding: 0; vertical-align: top;">
                                    <div style="font-size: 5.9pt; line-height: 1.15; color: #000000;">
                                        Memenuhi standard untuk dipromosikan pada level diatasnya, saran pada posisi :
                                    </div>
                                    <div class="line-underline">
                                        @if($pilPotensi == 1 && !empty($catatanPotensiDisplay))
                                            <span style="color: #000000; font-weight: normal; font-size: 5.8pt;">{{ $catatanPotensiDisplay }}</span>
                                        @else
                                            &nbsp;
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Option 2 -->
                    <div style="margin-bottom: 2.5px;">
                        <table style="width: 100%; border: none; border-collapse: collapse;">
                            <tr>
                                <td style="border: none; padding: 0 4px 0 0; width: 13px; vertical-align: top;">
                                    <span class="potensi-box {{ $pilPotensi == 2 ? 'checked' : 'unchecked' }}">{!! $pilPotensi == 2 ? '&#10003;' : '&nbsp;' !!}</span>
                                </td>
                                <td style="border: none; padding: 0; vertical-align: top;">
                                    <div style="font-size: 5.9pt; line-height: 1.15; color: #000000;">
                                        Sesuai dengan posisi saat ini dan masih bisa dikembangkan dengan pengalaman dan pelatihan sesuai matriks.
                                    </div>
                                    <div class="line-underline">
                                        @if($pilPotensi == 2 && !empty($catatanPotensiDisplay))
                                            <span style="color: #000000; font-weight: normal; font-size: 5.8pt;">{{ $catatanPotensiDisplay }}</span>
                                        @else
                                            &nbsp;
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Option 3 -->
                    <div style="margin-bottom: 2.5px;">
                        <table style="width: 100%; border: none; border-collapse: collapse;">
                            <tr>
                                <td style="border: none; padding: 0 4px 0 0; width: 13px; vertical-align: top;">
                                    <span class="potensi-box {{ $pilPotensi == 3 ? 'checked' : 'unchecked' }}">{!! $pilPotensi == 3 ? '&#10003;' : '&nbsp;' !!}</span>
                                </td>
                                <td style="border: none; padding: 0; vertical-align: top;">
                                    <div style="font-size: 5.9pt; line-height: 1.15; color: #000000;">
                                        Sesuai untuk posisi saat ini dan tidak bisa dikembangkan lagi, alasan :
                                    </div>
                                    <div class="line-underline">
                                        @if($pilPotensi == 3 && !empty($catatanPotensiDisplay))
                                            <span style="color: #000000; font-weight: normal; font-size: 5.8pt;">{{ $catatanPotensiDisplay }}</span>
                                        @else
                                            &nbsp;
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Option 4 -->
                    <div style="margin-bottom: 2.5px;">
                        <table style="width: 100%; border: none; border-collapse: collapse;">
                            <tr>
                                <td style="border: none; padding: 0 4px 0 0; width: 13px; vertical-align: top;">
                                    <span class="potensi-box {{ $pilPotensi == 4 ? 'checked' : 'unchecked' }}">{!! $pilPotensi == 4 ? '&#10003;' : '&nbsp;' !!}</span>
                                </td>
                                <td style="border: none; padding: 0; vertical-align: top;">
                                    <div style="font-size: 5.9pt; line-height: 1.15; color: #000000;">
                                        Yang bersangkutan kurang sesuai dengan posisi saat ini, dengan alasan :
                                    </div>
                                    <div class="line-underline">
                                        @if($pilPotensi == 4 && !empty($catatanPotensiDisplay))
                                            <span style="color: #000000; font-weight: normal; font-size: 5.8pt;">{{ $catatanPotensiDisplay }}</span>
                                        @else
                                            &nbsp;
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>

                </div>
                @endif

                @php
                    $formatCommentLines = function(array $commentList, ?string $fallback = '', int $maxLines = 5, int $charsPerLine = 48) {
                        $lines = [];
                        if (!empty($commentList)) {
                            foreach ($commentList as $item) {
                                $roleKey = strtolower($item['role'] ?? '');
                                $roleShort = match($roleKey) {
                                    'foreman' => 'Foreman',
                                    'section_head' => 'Section Head',
                                    'dept_head' => 'Dept. Head',
                                    'div_head' => 'Div. Head',
                                    default => ($item['role_label'] ?? 'Penilai')
                                };
                                $prefix = '• ' . $roleShort . ': ';
                                $text = trim((string)($item['text'] ?? ''));
                                if ($text === '') continue;

                                $fullComment = $prefix . $text;
                                if (mb_strlen($fullComment) <= $charsPerLine) {
                                    $lines[] = [
                                        'prefix' => $prefix,
                                        'text' => $text,
                                    ];
                                } else {
                                    $firstLineMaxTextLen = max(10, $charsPerLine - mb_strlen($prefix));
                                    $wrapped = wordwrap($text, $firstLineMaxTextLen, "\n", true);
                                    $parts = explode("\n", $wrapped);
                                    
                                    $lines[] = [
                                        'prefix' => $prefix,
                                        'text' => array_shift($parts),
                                    ];

                                    $subsequentText = implode(' ', $parts);
                                    if (trim($subsequentText) !== '') {
                                        $subWrapped = wordwrap($subsequentText, $charsPerLine - 4, "\n", true);
                                        foreach (explode("\n", $subWrapped) as $subLine) {
                                            if (count($lines) >= $maxLines) break;
                                            $lines[] = [
                                                'prefix' => '',
                                                'text' => $subLine,
                                            ];
                                        }
                                    }
                                }
                                if (count($lines) >= $maxLines) break;
                            }
                        } elseif (!empty($fallback)) {
                            $wrapped = wordwrap(trim($fallback), $charsPerLine, "\n", true);
                            foreach (explode("\n", $wrapped) as $idx => $w) {
                                if (count($lines) >= $maxLines) break;
                                $lines[] = [
                                    'prefix' => ($idx === 0 ? '• ' : ''),
                                    'text' => $w,
                                ];
                            }
                        }

                        $result = array_slice($lines, 0, $maxLines);
                        while (count($result) < $maxLines) {
                            $result[] = null;
                        }
                        return $result;
                    };

                    $slotCount = 5;
                    $pdfKelebihanLines = $formatCommentLines($commentMeta['allKelebihan'] ?? [], $indikator['kelebihan'] ?? '', $slotCount, 48);
                    $pdfKekuranganLines = $formatCommentLines($commentMeta['allKekurangan'] ?? [], $indikator['kekurangan'] ?? '', $slotCount, 48);
                @endphp

                <!-- KELEBIHAN KARYAWAN -->
                <div style="border-top: 1px solid #000000;">
                    <div class="bg-gray-bar font-bold uppercase text-center" style="padding: 1.5px; font-size: 6.2pt; border-bottom: 1px solid #000000; background-color: #d9d9d9 !important;">
                        {{ $isGol4Up ? 'KELEBIHAN KARYAWAN | 従業員のプラス所' : 'KELEBIHAN KARYAWAN' }}
                    </div>
                    <div style="padding: 0; min-height: 70px;">
                        <table style="width: 100%; border-collapse: collapse; table-layout: fixed; margin: 0; padding: 0;">
                            @foreach($pdfKelebihanLines as $line)
                                <tr>
                                    <td style="border-bottom: 0.8px solid #cbd5e1; height: 14px; padding: 1px 4px 2px 4px; vertical-align: top; font-size: 6.2pt; line-height: 1.2; white-space: nowrap;">
                                        @if($line)
                                            @if(!empty($line['prefix']))
                                                <span style="font-weight: bold; color: #1e293b;">{{ $line['prefix'] }}</span>
                                                <span style="font-weight: normal; color: #0f172a;">{{ $line['text'] }}</span>
                                            @else
                                                <span style="font-weight: normal; color: #0f172a; padding-left: 8px;">{{ $line['text'] }}</span>
                                            @endif
                                        @else
                                            &nbsp;
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                </div>

                <!-- KEKURANGAN KARYAWAN -->
                <div style="border-top: 1px solid #000000;">
                    <div class="bg-gray-bar font-bold uppercase text-center" style="padding: 1.5px; font-size: 6.2pt; border-bottom: 1px solid #000000; background-color: #d9d9d9 !important;">
                        {{ $isGol4Up ? 'KEKURANGAN KARYAWAN | 従業員のマイナス所' : 'KEKURANGAN KARYAWAN' }}
                    </div>
                    <div style="padding: 0; min-height: 70px;">
                        <table style="width: 100%; border-collapse: collapse; table-layout: fixed; margin: 0; padding: 0;">
                            @foreach($pdfKekuranganLines as $line)
                                <tr>
                                    <td style="border-bottom: 0.8px solid #cbd5e1; height: 14px; padding: 1px 4px 2px 4px; vertical-align: top; font-size: 6.2pt; line-height: 1.2; white-space: nowrap;">
                                        @if($line)
                                            @if(!empty($line['prefix']))
                                                <span style="font-weight: bold; color: #1e293b;">{{ $line['prefix'] }}</span>
                                                <span style="font-weight: normal; color: #0f172a;">{{ $line['text'] }}</span>
                                            @else
                                                <span style="font-weight: normal; color: #0f172a; padding-left: 8px;">{{ $line['text'] }}</span>
                                            @endif
                                        @else
                                            &nbsp;
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                </div>

            </div>

            <div style="clear: both;"></div>
        </div>

    </div>

</body>
</html>
