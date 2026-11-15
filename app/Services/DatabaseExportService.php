<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class DatabaseExportService
{
    /**
     * Generate multi-sheet or single-sheet Excel workbook from database.
     *
     * @param string|null $tableSpecific If null or 'all', exports all tables as sheets.
     * @return string Temporary file path of generated .xlsx
     */
    public static function generateExcel(?string $tableSpecific = null): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $tablesToExport = [
            'karyawan' => 'Master Karyawan',
            'dokumen' => 'Dokumen Penilaian',
            'users' => 'Manajemen Akun',
            'kuota_departemen' => 'Target & Kuota',
            'master_aspek' => 'Master Aspek Rubrik',
            'master_kriteria' => 'Kriteria Rubrik Form',
            'nilai' => 'Detail Nilai',
            'tanda_tangan' => 'Tanda Tangan Digital',
            'pengaturan' => 'Pengaturan Sistem',
        ];

        if ($tableSpecific && isset($tablesToExport[$tableSpecific])) {
            $selectedTables = [$tableSpecific => $tablesToExport[$tableSpecific]];
        } else {
            $selectedTables = $tablesToExport;
        }

        $sheetIndex = 0;
        foreach ($selectedTables as $tableName => $sheetTitle) {
            $sheet = $spreadsheet->createSheet($sheetIndex);
            $sheet->setTitle(substr($sheetTitle, 0, 31));

            self::populateSheet($sheet, $tableName, $sheetTitle);
            $sheetIndex++;
        }

        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        $tempPath = tempnam(sys_get_temp_dir(), 'musashi_db_') . '.xlsx';
        $writer->save($tempPath);

        return $tempPath;
    }

    /**
     * Populate a single sheet with table records and apply professional Musashi styling.
     */
    private static function populateSheet($sheet, string $tableName, string $title): void
    {
        $columns = [];
        $rows = [];

        switch ($tableName) {
            case 'karyawan':
                $columns = [
                    'NO', 'NRP', 'NAMA LENGKAP', 'CODE BAGIAN', 'NAMA BAGIAN',
                    'SECTION', 'DEPARTMENT', 'DIVISION', 'POSITION', 'START WORK',
                    'KJ', 'GOLONGAN', 'SUB GOL', 'NRP FOREMAN', 'NRP SECTION HEAD', 'NRP DEPT HEAD', 'NRP DIV HEAD'
                ];
                $data = DB::table('karyawan')->orderBy('nrp', 'asc')->get();
                $idx = 1;
                foreach ($data as $d) {
                    $rows[] = [
                        $idx++,
                        $d->nrp,
                        $d->nama_lengkap ?? '',
                        $d->code_bagian ?? '',
                        $d->nama_bagian ?? '',
                        $d->seksi ?? '',
                        $d->departemen ?? '',
                        $d->division ?? '',
                        $d->jabatan ?? '',
                        $d->tgl_masuk ?? '',
                        $d->kj ?? '',
                        $d->golongan ?? '',
                        $d->sub_gol ?? '',
                        $d->nrp_foreman ?? '',
                        $d->nrp_section_head ?? '',
                        $d->nrp_dept_head ?? '',
                        $d->nrp_div_head ?? '',
                    ];
                }
                break;

            case 'dokumen':
                $columns = [
                    'NO', 'ID DOKUMEN', 'NRP', 'NAMA KARYAWAN', 'KODE FORM',
                    'PERIODE ID', 'STATUS', 'TOTAL NILAI', 'GRADE ORIGINAL',
                    'DOCKING FINAL', 'KODE VERIFIKASI', 'TANGGAL DIBUAT'
                ];
                $data = DB::table('dokumen')
                    ->leftJoin('karyawan', 'dokumen.nrp', '=', 'karyawan.nrp')
                    ->select('dokumen.*', 'karyawan.nama_lengkap as nama_karyawan')
                    ->orderBy('dokumen.id', 'asc')
                    ->get();
                $idx = 1;
                foreach ($data as $d) {
                    $rows[] = [
                        $idx++,
                        $d->id,
                        $d->nrp,
                        $d->nama_karyawan ?? '',
                        $d->kode_form ?? '',
                        $d->periode_id ?? 1,
                        $d->status ?? '',
                        $d->total_nilai ?? '',
                        $d->grade_original ?? '',
                        $d->docking_final ?? '',
                        $d->kode_verifikasi ?? '',
                        $d->created_at ?? '',
                    ];
                }
                break;

            case 'users':
                $columns = [
                    'NO', 'ID USER', 'NRP', 'NAMA', 'EMAIL',
                    'ROLE', 'DEPARTEMEN', 'STATUS AKTIF', 'TANGGAL TERDAFTAR'
                ];
                $data = \App\Models\User::orderBy('id', 'asc')->get();
                $idx = 1;
                foreach ($data as $d) {
                    $rows[] = [
                        $idx++,
                        $d->id,
                        $d->nrp ?? '',
                        $d->nama ?? '',
                        $d->email ?? '',
                        $d->role ?? '',
                        $d->departemen ?? '',
                        $d->aktif ? 'AKTIF' : 'NONAKTIF',
                        $d->created_at ?? '',
                    ];
                }
                break;

            case 'kuota_departemen':
                $columns = [
                    'NO', 'ID', 'DEPARTEMEN', 'KJ', 'TARGET S', 'TARGET A',
                    'TARGET B', 'TARGET C', 'TARGET K', 'TOTAL KARYAWAN'
                ];
                if (DB::getSchemaBuilder()->hasTable('kuota_departemen')) {
                    $data = DB::table('kuota_departemen')->get();
                    $idx = 1;
                    foreach ($data as $d) {
                        $rows[] = [
                            $idx++,
                            $d->id ?? '',
                            $d->departemen ?? '',
                            $d->kj ?? '',
                            $d->kuota_s ?? 0,
                            $d->kuota_a ?? 0,
                            $d->kuota_b ?? 0,
                            $d->kuota_c ?? 0,
                            $d->kuota_k ?? 0,
                            $d->total_karyawan ?? 0,
                        ];
                    }
                }
                break;

            case 'master_aspek':
                $columns = [
                    'NO', 'ID', 'KODE FORM', 'GRUP ASPEK', 'KODE ASPEK', 'NAMA ASPEK', 'BOBOT GRUP (%)', 'URUTAN'
                ];
                if (DB::getSchemaBuilder()->hasTable('master_aspek')) {
                    $data = DB::table('master_aspek')->orderBy('kode_form')->orderBy('urutan')->get();
                    $idx = 1;
                    foreach ($data as $d) {
                        $rows[] = [
                            $idx++,
                            $d->id ?? '',
                            $d->kode_form ?? '',
                            $d->grup ?? '',
                            $d->aspek_kode ?? '',
                            $d->nama ?? '',
                            $d->bobot_grup ?? 0,
                            $d->urutan ?? 1,
                        ];
                    }
                }
                break;

            case 'master_kriteria':
                $columns = [
                    'NO', 'ID', 'KODE ASPEK', 'SKOR (1-5)', 'DESKRIPSI KRITERIA'
                ];
                if (DB::getSchemaBuilder()->hasTable('master_kriteria')) {
                    $data = DB::table('master_kriteria')->orderBy('aspek_kode')->orderBy('skor')->get();
                    $idx = 1;
                    foreach ($data as $d) {
                        $rows[] = [
                            $idx++,
                            $d->id ?? '',
                            $d->aspek_kode ?? '',
                            $d->skor ?? 1,
                            $d->deskripsi ?? '',
                        ];
                    }
                }
                break;

            case 'nilai':
                $columns = [
                    'NO', 'ID NILAI', 'DOKUMEN ID', 'LEVEL PENILAI', 'KODE ASPEK', 'SKOR'
                ];
                if (DB::getSchemaBuilder()->hasTable('nilai')) {
                    $data = DB::table('nilai')->orderBy('id', 'asc')->get();
                    $idx = 1;
                    foreach ($data as $d) {
                        $rows[] = [
                            $idx++,
                            $d->id ?? '',
                            $d->dokumen_id ?? '',
                            $d->level ?? '',
                            $d->aspek_kode ?? '',
                            $d->skor ?? 0,
                        ];
                    }
                }
                break;

            case 'tanda_tangan':
                $columns = [
                    'NO', 'ID TTD', 'DOKUMEN ID', 'LEVEL PENANDATANGAN', 'USER ID',
                    'TANGGAL TTD', 'IP ADDRESS'
                ];
                if (DB::getSchemaBuilder()->hasTable('tanda_tangan')) {
                    $data = DB::table('tanda_tangan')->orderBy('id', 'asc')->get();
                    $idx = 1;
                    foreach ($data as $d) {
                        $rows[] = [
                            $idx++,
                            $d->id ?? '',
                            $d->dokumen_id ?? '',
                            $d->level ?? '',
                            $d->user_id ?? '',
                            $d->tgl_ttd ?? '',
                            $d->ip ?? '',
                        ];
                    }
                }
                break;

            case 'pengaturan':
                $columns = ['NO', 'KEY / PARAMETER', 'VALUE / KONFIGURASI', 'TERAKHIR DIPERBARUI'];
                if (DB::getSchemaBuilder()->hasTable('pengaturan')) {
                    $data = DB::table('pengaturan')->get();
                    $idx = 1;
                    foreach ($data as $d) {
                        $rows[] = [
                            $idx++,
                            $d->key ?? '',
                            $d->value ?? '',
                            $d->updated_at ?? '',
                        ];
                    }
                }
                break;
        }

        if (empty($columns)) {
            $columns = ['NO', 'INFO'];
            $rows = [[1, 'Tidak ada data untuk tabel ini.']];
        }

        // Header Row
        $colCount = count($columns);
        $sheet->fromArray($columns, null, 'A1');

        $lastColLetter = self::columnIndexToLetter($colCount);
        $headerRange = 'A1:' . $lastColLetter . '1';

        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 10,
                'name' => 'Calibri',
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '92302E'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => false,
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);

        // Data Rows
        if (!empty($rows)) {
            $sheet->fromArray($rows, null, 'A2');
            $dataRange = 'A2:' . $lastColLetter . (count($rows) + 1);

            $sheet->getStyle($dataRange)->applyFromArray([
                'font' => [
                    'size' => 10,
                    'name' => 'Calibri',
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'E2E8F0'],
                    ],
                ],
            ]);

            for ($r = 2; $r <= count($rows) + 1; $r++) {
                $sheet->getRowDimension($r)->setRowHeight(20);
            }
        }

        $sheet->freezePane('A2');

        for ($c = 1; $c <= $colCount; $c++) {
            $colLetter = self::columnIndexToLetter($c);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }
    }

    private static function columnIndexToLetter(int $colIndex): string
    {
        $letter = '';
        while ($colIndex > 0) {
            $modulo = ($colIndex - 1) % 26;
            $letter = chr(65 + $modulo) . $letter;
            $colIndex = (int)(($colIndex - $modulo) / 26);
        }
        return $letter;
    }
}