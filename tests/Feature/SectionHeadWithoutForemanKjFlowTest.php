<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AppraisalCalculationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SectionHeadWithoutForemanKjFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh --seed');
        Cache::flush();
    }

    private function buildScores(string $kodeForm, int $val = 4): array
    {
        $calc = app(AppraisalCalculationService::class);
        $struct = $calc->getFormStructure($kodeForm);
        $scores = [];
        foreach ($struct['groups'] as $g) {
            foreach ($g['aspects'] as $aspekCode => $aspekName) {
                $scores[$aspekCode] = $val;
            }
        }
        return $scores;
    }

    public function test_section_head_without_foreman_uses_07_006_only_for_self_and_kj15_for_subordinates(): void
    {
        $shF3 = User::where('nrp', '99999')->firstOrFail();

        // Check Section Head Factory 3 Tanpa Foreman (99999 AL QADRI, direct KJ 2 subs 99998..99996)
        $this->assertEquals('07-006-SH', DB::table('dokumen')->where('nrp', '99999')->value('kode_form'));
        $this->assertEquals('07-001', DB::table('dokumen')->where('nrp', '99998')->value('kode_form'));
        $this->assertEquals('07-001', DB::table('dokumen')->where('nrp', '99997')->value('kode_form'));
        $this->assertEquals('07-001', DB::table('dokumen')->where('nrp', '99996')->value('kode_form'));

        // Check Assembly 1 (SH-11310)
        $this->assertEquals('07-006-SH', DB::table('dokumen')->where('nrp', 'SH-11310')->value('kode_form'));
        $this->assertEquals('07-003', DB::table('dokumen')->where('nrp', '2023020')->value('kode_form'));
        $this->assertEquals('07-001', DB::table('dokumen')->where('nrp', '2023021')->value('kode_form'));

        // Check /api/karyawan-lookup returns nrp_foreman=null and right kode_form
        $lookup = $this->actingAs($shF3)->getJson('/api/karyawan-lookup?nrp=99998');
        $lookup->assertStatus(200)->assertJson([
            'found' => true,
            'karyawan' => [
                'nrp' => '99998',
                'nrp_foreman' => null,
                'kode_form' => '07-001',
            ],
        ]);
    }

    public function test_foreman_column_is_completely_blank_in_form_and_pdf_when_no_foreman(): void
    {
        $shF3 = User::where('nrp', '99999')->firstOrFail();
        $doc = DB::table('dokumen')->where('nrp', '99998')->first();
        $this->assertNotNull($doc);
        $this->assertEquals('07-001', $doc->kode_form);

        $response = $this->actingAs($shF3)->get("/penilaian/sechead/dokumen/{$doc->id}");
        $response->assertStatus(200);

        $this->assertFalse($response->viewData('canEditForeman'));
        $this->assertFalse($response->viewData('canSignForeman'));
        $this->assertTrue($response->viewData('canEditSectionHead'));
        $this->assertTrue($response->viewData('canSignSectionHead'));

        $response->assertSee('Code PDV Mapin - 07-001');
        $response->assertDontSee('Sama dg Foreman');
        $response->assertDontSee('x-text="totalsForeman', false);
        $response->assertDontSee('alt="TTD Foreman"', false);

        // Verify HTML has 2 "Belum TTD" placeholders (Section Head & Dept Head), NOT 3 (Foreman is blank &nbsp;)
        $html = $response->getContent();
        $this->assertEquals(2, substr_count($html, 'Belum TTD'));

        // PDF download succeeds & PDF view has 2 "Belum TTD" placeholders (Foreman column is blank &nbsp;)
        $pdfRes = $this->actingAs($shF3)->get(route('penilaian.downloadPdf', $doc->id));
        $pdfRes->assertStatus(200);
        $this->assertEquals('application/pdf', $pdfRes->headers->get('content-type'));
    }

    public function test_subordinate_kj15_stops_at_dept_head_while_sh_self_goes_through_div_head_and_dashboard_counts_accurately(): void
    {
        $shF3 = User::where('nrp', '99999')->firstOrFail();
        $dh = User::where('nrp', '31')->firstOrFail();
        $div = User::where('nrp', '11')->firstOrFail();

        $docSub = DB::table('dokumen')->where('nrp', '99998')->first();
        $docSelf = DB::table('dokumen')->where('nrp', '99999')->first();

        $canvas = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

        // 0. Check initial dashboard for 99999 AL QADRI (3 FACTORY 3 staff + self 99999 = 4)
        $dash0 = $this->actingAs($shF3)->get('/dashboard');
        $dash0->assertStatus(200);
        $this->assertEquals(4, $dash0->viewData('sectionNeedActionCount'));
        $this->assertEquals(0, $dash0->viewData('waitingDeptCount'));
        $this->assertEquals(0, $dash0->viewData('waitingDivCount'));
        $this->assertEquals(0, $dash0->viewData('sectionSelesaiCount'));

        // "2. Tinjauan Department Head" must appear cleanly once (no duplicated sr-only span)
        $dash0->assertDontSee('2. Tinjauan Department Head <span class="sr-only">', false);
        $this->assertEquals(1, substr_count($dash0->getContent(), '2. Tinjauan Department Head'));

        // Step A: SH evaluates KJ 1-5 subordinate (99998) at Kolom 2 ('section_head') -> menunggu_dept_head (Tahap 2)
        $this->actingAs($shF3)->post(route('penilaian.sign', $docSub->id), [
            'level' => 'section_head',
            'password' => 'password123',
            'signature_base64' => $canvas,
            'scores' => $this->buildScores('07-001', 4),
            'pilihan_potensi' => '2',
        ])->assertSessionHasNoErrors();

        $this->assertEquals('menunggu_dept_head', DB::table('dokumen')->where('id', $docSub->id)->value('status'));
        $this->assertNull(DB::table('tanda_tangan')->where('dokumen_id', $docSub->id)->where('level', 'foreman')->first());

        $dashA = $this->actingAs($shF3)->get('/dashboard');
        $this->assertEquals(3, $dashA->viewData('sectionNeedActionCount'));
        $this->assertEquals(1, $dashA->viewData('waitingDeptCount'));
        $this->assertEquals(0, $dashA->viewData('waitingDivCount'));
        $this->assertEquals(0, $dashA->viewData('sectionSelesaiCount'));

        // Step B: DH (31 SUPARDI) evaluates KJ 1-5 subordinate (99998) at Kolom 3 ('dept_head') -> menunggu_div_head or selesai
        $this->actingAs($dh)->post(route('penilaian.sign', $docSub->id), [
            'level' => 'dept_head',
            'password' => 'password123',
            'signature_base64' => $canvas,
            'scores' => $this->buildScores('07-001', 4),
            'pilihan_potensi' => '2',
        ])->assertSessionHasNoErrors();

        $this->assertEquals('selesai', DB::table('dokumen')->where('id', $docSub->id)->value('status'));

        $dashB = $this->actingAs($shF3)->get('/dashboard');
        $this->assertEquals(3, $dashB->viewData('sectionNeedActionCount'));
        $this->assertEquals(0, $dashB->viewData('waitingDeptCount'));
        $this->assertEquals(0, $dashB->viewData('waitingDivCount'));
        $this->assertEquals(1, $dashB->viewData('sectionSelesaiCount'));

        // Step C: SH signs own self-eval (99999, 07-006-SH) at Kolom 1 ('foreman') -> menunggu_dept_head (Tahap 2)
        $this->actingAs($shF3)->post(route('penilaian.sign', $docSelf->id), [
            'level' => 'foreman',
            'password' => 'password123',
            'signature_base64' => $canvas,
            'scores' => $this->buildScores('07-006-SH', 4),
            'pilihan_potensi' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertEquals('menunggu_dept_head', DB::table('dokumen')->where('id', $docSelf->id)->value('status'));

        // Step D: DH signs 99999 (07-006-SH) at Kolom 2 ('section_head') -> menunggu_div_head (Tahap 3)
        $this->actingAs($dh)->post(route('penilaian.sign', $docSelf->id), [
            'level' => 'section_head',
            'password' => 'password123',
            'signature_base64' => $canvas,
            'scores' => $this->buildScores('07-006-SH', 4),
            'pilihan_potensi' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertEquals('menunggu_div_head', DB::table('dokumen')->where('id', $docSelf->id)->value('status'));

        $dashD = $this->actingAs($shF3)->get('/dashboard');
        $this->assertEquals(2, $dashD->viewData('sectionNeedActionCount'));
        $this->assertEquals(0, $dashD->viewData('waitingDeptCount'));
        $this->assertEquals(1, $dashD->viewData('waitingDivCount'));
        $this->assertEquals(1, $dashD->viewData('sectionSelesaiCount'));

        // Step E: Div Head (11 ATASAN) signs 99999 (07-006-SH) at Kolom 3 ('dept_head') -> SELESAI (Tahap 4)
        $this->actingAs($div)->post(route('penilaian.sign', $docSelf->id), [
            'level' => 'dept_head',
            'password' => 'password123',
            'signature_base64' => $canvas,
            'scores' => $this->buildScores('07-006-SH', 4),
            'pilihan_potensi' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertEquals('selesai', DB::table('dokumen')->where('id', $docSelf->id)->value('status'));

        $dashE = $this->actingAs($shF3)->get('/dashboard');
        $this->assertEquals(2, $dashE->viewData('sectionNeedActionCount'));
        $this->assertEquals(0, $dashE->viewData('waitingDeptCount'));
        $this->assertEquals(0, $dashE->viewData('waitingDivCount'));
        $this->assertEquals(2, $dashE->viewData('sectionSelesaiCount'));
    }

    public function test_dept_head_revisi_on_kj15_without_foreman_returns_to_section_head(): void
    {
        $shF3 = User::where('nrp', '99999')->firstOrFail();
        $dh = User::where('nrp', '31')->firstOrFail();
        $doc = DB::table('dokumen')->where('nrp', '99997')->first();

        $canvas = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

        $this->actingAs($shF3)->post(route('penilaian.sign', $doc->id), [
            'level' => 'section_head',
            'password' => 'password123',
            'signature_base64' => $canvas,
            'scores' => $this->buildScores('07-001', 4),
            'pilihan_potensi' => '2',
        ])->assertSessionHasNoErrors();

        // Dept Head triggers revisi
        $this->actingAs($dh)->post(route('penilaian.revisi', $doc->id), [
            'catatan_revisi' => 'Mohon disesuaikan kembali nilai kerja.',
        ])->assertRedirect();

        $this->assertEquals('revisi', DB::table('dokumen')->where('id', $doc->id)->value('status'));
        $this->assertNull(DB::table('tanda_tangan')->where('dokumen_id', $doc->id)->where('level', 'section_head')->first());

        $formSh = $this->actingAs($shF3)->get("/penilaian/sechead/dokumen/{$doc->id}");
        $formSh->assertStatus(200);
        $this->assertFalse($formSh->viewData('canEditForeman'));
        $this->assertTrue($formSh->viewData('canEditSectionHead'));
        $this->assertTrue($formSh->viewData('canSignSectionHead'));
    }

    public function test_section_head_without_foreman_karyawan_shows_82_excel_rows_plus_self_with_12_excel_columns(): void
    {
        $shF3 = User::where('nrp', '99999')->firstOrFail();

        $res = $this->actingAs($shF3)->get('/karyawan');
        $res->assertStatus(200);

        $rows = $res->viewData('karyawanRows');
        // 3 FACTORY 3 staff (99998, 99997, 99996) + 1 self (99999) = 4
        $this->assertCount(4, $rows);

        foreach ([
            'NRP', 'NAMA LENGKAP', 'CODE BAGIAN', 'NAMA BAGIAN',
            'LINE CATEGORY', 'POSITION', 'DIVISION', 'DEPARTMENT', 'SECTION',
            'KJ', 'GOLONGAN', 'START WORK',
        ] as $headerCol) {
            $res->assertSee($headerCol);
        }
        $res->assertSeeInOrder(['>SECTION</th>', '>KJ</th>', '>GOLONGAN</th>', '>START WORK</th>'], false);

        $sampleRow = collect($rows)->first(fn ($r) => ($r['kj'] ?? '') !== '' && ($r['sub_gol'] ?? '') !== '' && $r['kj'] !== $r['sub_gol']);
        $this->assertNotNull($sampleRow);
        $this->assertSame(1, preg_match('/<tr\b[^>]*>(?:(?!<\/tr>).)*?' . preg_quote($sampleRow['nrp'], '/') . '.*?<\/tr>/s', $res->getContent(), $trMatch));
        preg_match_all('/<td\b[^>]*>(.*?)<\/td>/s', $trMatch[0], $tdMatches);
        $this->assertSame(trim($sampleRow['kj']), trim(strip_tags($tdMatches[1][10])));
        $this->assertSame(trim($sampleRow['sub_gol']), trim(strip_tags($tdMatches[1][11])));
        $res->assertSee('Total Karyawan');
        $res->assertSee('Total BAGIAN');
        $res->assertSee('Total SECTION');
        $res->assertSee('TOTAL KJ / GOLONGAN');
        $res->assertSee('KJ / Golongan');
        $res->assertSee('Total POSITION');
        $res->assertSee('position');
        $res->assertDontSee('Karyawan terdaftar');
        $res->assertDontSee('Bagian / lini kerja');
        $res->assertDontSee('Section organisasi');
        $res->assertDontSee('Bagian Golongan');
        $res->assertDontSee('Posisi / peran kerja');
        $res->assertDontSee('TOTAL GOLONGAN / KJ');
        $res->assertDontSee('Total SECT.');
        $res->assertDontSee('Total DEPT.');
        $res->assertDontSee('Filter Aktif');
        $res->assertDontSee('Semua Seksi');
        $res->assertDontSee('>EMPLOYEENO</th>', false);
        $res->assertDontSee('>FULLNAME</th>', false);
        $res->assertDontSee('>ORGSTRUCTURECODE</th>', false);
        $res->assertDontSee('>ORGSTRUCTURENAME</th>', false);
        $res->assertDontSee('>DEPT</th>', false);
        $res->assertDontSee('>JOBCLASSCODE</th>', false);
        $res->assertDontSee('>JOBLEVELCODE</th>', false);
        $res->assertDontSee('>JOINDATE</th>', false);
        $res->assertDontSee('>07-001</span>', false);

        // Verify sample FACTORY 3 records and absence of Foremen/Leaders in subordinates
        $res->assertSee('TES 1');
        $res->assertSee('TES 2');
        $res->assertSee('HR MANAGEMENT SYSTEM');
        $res->assertSee('PRODUCTION A DIV');

        $nonSelfRows = collect($rows)->reject(fn ($r) => $r['nrp'] === '99999');
        foreach ($nonSelfRows as $r) {
            $posUpper = strtoupper($r['jabatan'] ?? '');
            $this->assertStringNotContainsString('FOREMAN', $posUpper);
            $this->assertStringNotContainsString('LEADER', $posUpper);
            $this->assertStringNotContainsString('DEPT HEAD', $posUpper);
            $this->assertEquals('PROD 2', strtoupper($r['departemen']));
            $this->assertEquals('FACTORY 3', strtoupper($r['nama_sect']));
        }

        // Verify Bagian dropdown filter placed beside Position (before KJ / Golongan) and actually filters rows
        $res->assertSeeInOrder(['Semua Position', 'Bagian', 'Semua Bagian', 'KJ / Golongan'], false);
        $namaBagianList = $res->viewData('namaBagianList');
        $this->assertNotEmpty($namaBagianList);
        $targetBagian = $namaBagianList[0];

        $resBagian = $this->actingAs($shF3)->get('/karyawan?nama_bagian=' . urlencode($targetBagian));
        $resBagian->assertStatus(200);
        $resBagian->assertSee('Reset');
        $filteredRows = $resBagian->viewData('karyawanRows');
        $this->assertNotEmpty($filteredRows);
        $this->assertLessThan(4, count($filteredRows));
        foreach ($filteredRows as $fr) {
            $this->assertEquals(
                strtoupper(rtrim($targetBagian, '.')),
                strtoupper(rtrim($fr['nama_bagian'], '.'))
            );
        }
    }

    public function test_admin_and_superadmin_master_excel_upload_auto_filters_by_section_dept_without_foreman_leader(): void
    {
        $admin = User::where('email', 'admin@musashi.co.id')->firstOrFail();
        $superadmin = User::where('role', 'superadmin')->firstOrFail();
        $shF3 = User::where('nrp', '99999')->firstOrFail();

        // 1. Admin uploads a multi-section / multi-department CSV with mixed positions
        $csvAdmin = implode("\n", [
            'EMPLOYEENO;FULLNAME;ORGSTRUCTURECODE;ORGSTRUCTURENAME;LINE CATEGORY;POSITION;DIVISION;DEPT;SECTION;JOBCLASSCODE;JOBLEVELCODE;JOINDATE',
            '99101;OP FACTORY 1 MASTER;1141620100;MACH 2 FACT-1;F1 CAT 1;OPERATOR;PRODUCTION A DIV;PROD 2;FACTORY 1;IIIB;KJ03;2021-01-10',
            '99102;FOREMAN FACTORY 3 MASTER;1147000000;PRODUCTION 2 DEPT;-;FOREMAN;PRODUCTION A DIV;PROD 2;FACTORY 3;IVA;KJ06;2015-05-01',
            '99103;LEADER FACTORY 3 MASTER;9999999000;HR MANAGEMENT SYSTEM;-;LEADER;PRODUCTION A DIV;PROD 2;FACTORY 3;IIID;KJ04;2018-03-12',
            '99104;OP FACTORY 3 BARU ADMIN;9999999000;HR MANAGEMENT SYSTEM 4;-;STAFF;PRODUCTION A DIV;PROD 2;FACTORY 3;IIIB;KJ03;2023-07-01',
            '99105;OP QA DEPT LAIN;1131000000;QA INSPECTOR;QA CAT;OPERATOR;QUALITY DIV;QA;QA;IIA;KJ02;2022-09-15',
        ]);
        $uploadAdmin = \Illuminate\Http\UploadedFile::fake()->createWithContent('master_admin.csv', $csvAdmin);

        $resAdminPost = $this->actingAs($admin)->from('/admin/karyawan')->post('/karyawan/import', [
            'sheet_file' => $uploadAdmin,
        ]);
        $resAdminPost->assertRedirect(route('admin.karyawan'));
        $resAdminPost->assertSessionHas('success');

        // 2. Superadmin uploads another FACTORY 3 staff using legacy 'file' input name
        $csvSuper = implode("\n", [
            'EMPLOYEENO;FULLNAME;ORGSTRUCTURECODE;ORGSTRUCTURENAME;LINE CATEGORY;POSITION;DIVISION;DEPT;SECTION;JOBCLASSCODE;JOBLEVELCODE;JOINDATE',
            '99106;OP FACTORY 3 BARU SUPERADMIN;9999999000;HR MANAGEMENT SYSTEM 5;-;STAFF;PRODUCTION A DIV;PROD 2;FACTORY 3;IIC;KJ02;2024-02-01',
        ]);
        $uploadSuper = \Illuminate\Http\UploadedFile::fake()->createWithContent('master_superadmin.csv', $csvSuper);

        $resSuperPost = $this->actingAs($superadmin)->from('/admin/karyawan')->post('/karyawan/import', [
            'file' => $uploadSuper,
        ]);
        $resSuperPost->assertRedirect(route('admin.karyawan'));
        $resSuperPost->assertSessionHas('success');

        // Admin /admin/karyawan Master Data contains ALL 6 newly imported rows
        $resAdminList = $this->actingAs($admin)->get('/admin/karyawan');
        $resAdminList->assertStatus(200);
        foreach (['99101', '99102', '99103', '99104', '99105', '99106'] as $nrpAll) {
            $this->assertTrue(collect($resAdminList->viewData('karyawanRows'))->contains('nrp', $nrpAll));
        }

        // Section Head Without Foreman (/karyawan) ONLY sees PROD 2 + FACTORY 3 non-Foreman/non-Leader (99104, 99106 + existing 4 = 6)
        $resSh = $this->actingAs($shF3)->get('/karyawan');
        $resSh->assertStatus(200);
        $shRows = collect($resSh->viewData('karyawanRows'));
        $this->assertCount(6, $shRows);
        $this->assertTrue($shRows->contains('nrp', '99104'));
        $this->assertTrue($shRows->contains('nrp', '99106'));
        $this->assertFalse($shRows->contains('nrp', '99101')); // FACTORY 1 excluded
        $this->assertFalse($shRows->contains('nrp', '99102')); // FOREMAN excluded
        $this->assertFalse($shRows->contains('nrp', '99103')); // LEADER excluded
        $this->assertFalse($shRows->contains('nrp', '99105')); // QA excluded

        // Also verify /penilaian/cari keeps the Penilaian Mandiri Saya hero card & renders clean table rows with updated labels & filters
        $resCari = $this->actingAs($shF3)->get('/penilaian/cari');
        $resCari->assertStatus(200);
        $resCari->assertSee('Penilaian Mandiri Saya');
        $resCari->assertSee('NRP: 99999');
        $resCari->assertSee('Form Section Head (07-006)');
        $resCari->assertSee('AL QADRI');
        $resCari->assertSee('Daftar Karyawan &amp; Formulir Penilaian', false);
        $resCari->assertSee('<table class="w-full text-left border-separate border-spacing-0 text-xs">', false);
        $resCari->assertSee('KJ / GOLONGAN');
        $resCari->assertSee('cariFilterKj');
        $resCari->assertSee('cariFilterJabatan');
        $resCari->assertSee('>POSITION</th>', false);
        $resCari->assertSee('>SECTION</th>', false);
        $resCari->assertSee('>KJ</th>', false);
        $resCari->assertSee('>GOLONGAN</th>', false);
        $resCari->assertSee('Form Section Head', false);
        $resCari->assertSee('Form KJ 1-3', false);
        $resCari->assertDontSee('Form KJ 1-3 (07-001)');
        $resCari->assertDontSee('(07-001)');
        $this->assertSame(1, preg_match('/<tbody\b[^>]*>.*?<\/tbody>/s', $resCari->getContent(), $cariBody));
        $this->assertStringNotContainsString('(07-006)', $cariBody[0]);
        $this->assertStringNotContainsString('(07-001)', $cariBody[0]);
        $resCari->assertDontSee('Cari nama atau NRP...');
        $resCari->assertDontSee('GOLONGAN / KJ');
        $resCari->assertDontSee('KJ / GOL<', false);
        $resCari->assertSee('Evaluasi Mandiri');
        $resCari->assertSee('Section Head');
        $resCari->assertSee('>Nilai Mandiri</span>', false);
        $resCari->assertSee('>Nilai</span>', false);
        $resCari->assertSee('(Anda)');
        $resCari->assertDontSee('Evaluasi Diri (07-006-SH)');
        $resCari->assertDontSee('Nilai Langsung (07-001)');
        $resCari->assertSee('TES 1');
    }

    public function test_karyawan_export_downloads_formatted_xlsx_matching_exact_table_columns_and_filters(): void
    {
        $shF3 = User::where('nrp', '99999')->firstOrFail();

        $resIndex = $this->actingAs($shF3)->get('/karyawan?golongan=' . urlencode('KJ 2'));
        $resIndex->assertStatus(200);
        $resIndex->assertSee(route('karyawan.export', ['golongan' => 'KJ 2']), false);

        // 1. Unfiltered export for Section Head without Foreman -> exact 13 web columns
        $resExport = $this->actingAs($shF3)->get('/karyawan/export');
        $resExport->assertStatus(200);
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $resExport->headers->get('Content-Type')
        );
        $this->assertStringContainsString('.xlsx', (string)$resExport->headers->get('Content-Disposition'));

        $tmpXlsx = tempnam(sys_get_temp_dir(), 'test_karyawan_export_') . '.xlsx';
        file_put_contents($tmpXlsx, $resExport->getContent());

        try {
            $parsed = \App\Services\ExcelKaryawanReader::parseXlsx($tmpXlsx);
            $this->assertCount(4, $parsed);
            $this->assertSame([
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
            ], array_keys($parsed[0]));

            $zip = new \ZipArchive();
            $this->assertTrue($zip->open($tmpXlsx) === true);
            $sheetXml = (string)$zip->getFromName('xl/worksheets/sheet1.xml');
            $stylesXml = (string)$zip->getFromName('xl/styles.xml');
            $zip->close();

            $this->assertSame(13, substr_count($sheetXml, '<col '));
            $this->assertStringContainsString('customWidth="1" bestFit="1"', $sheetXml);
            $this->assertStringContainsString('state="frozen"', $sheetXml);
            $this->assertStringContainsString('<autoFilter ref="A1:M5"/>', $sheetXml);
            $this->assertStringContainsString('FF92302E', $stylesXml);
            $this->assertStringContainsString('<left style="thin">', $stylesXml);
        } finally {
            @unlink($tmpXlsx);
        }

        // 2. Filtered export by nama_bagian matches /karyawan filtered table rows
        $targetBagian = $parsed[0]['NAMA BAGIAN'];
        $resFiltered = $this->actingAs($shF3)->get('/karyawan/export?nama_bagian=' . urlencode($targetBagian));
        $resFiltered->assertStatus(200);

        $tmpFiltered = tempnam(sys_get_temp_dir(), 'test_karyawan_filtered_') . '.xlsx';
        file_put_contents($tmpFiltered, $resFiltered->getContent());
        try {
            $parsedFiltered = \App\Services\ExcelKaryawanReader::parseXlsx($tmpFiltered);
            $this->assertNotEmpty($parsedFiltered);
            $this->assertLessThan(4, count($parsedFiltered));
            foreach ($parsedFiltered as $row) {
                $this->assertEquals(
                    strtoupper(rtrim($targetBagian, '.')),
                    strtoupper(rtrim($row['NAMA BAGIAN'], '.'))
                );
            }
        } finally {
            @unlink($tmpFiltered);
        }

        // 3. Standard role (Admin) export matches exact web table columns
        $admin = User::where('email', 'admin@musashi.co.id')->firstOrFail();
        $resAdminExport = $this->actingAs($admin)->get('/karyawan/export');
        $resAdminExport->assertStatus(200);

        $tmpAdmin = tempnam(sys_get_temp_dir(), 'test_karyawan_admin_') . '.xlsx';
        file_put_contents($tmpAdmin, $resAdminExport->getContent());
        try {
            $parsedAdmin = \App\Services\ExcelKaryawanReader::parseXlsx($tmpAdmin);
            $this->assertNotEmpty($parsedAdmin);
            $this->assertSame([
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
            ], array_keys($parsedAdmin[0]));
        } finally {
            @unlink($tmpAdmin);
        }
    }

    public function test_section_head_without_foreman_riwayat_shows_unified_target_vs_actual_table_instead_of_bar_chart(): void
    {
        $shF3 = User::where('nrp', '99999')->firstOrFail();

        // Evaluate one KJ 2 subordinate ('99998') cleanly as Section Head
        $docSub = DB::table('dokumen')->where('nrp', '99998')->firstOrFail();
        $canvas = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
        $resSubmit = $this->actingAs($shF3)->post(route('penilaian.sign', $docSub->id), [
            'level' => 'section_head',
            'password' => 'password123',
            'signature_base64' => $canvas,
            'scores' => $this->buildScores('07-001', 4),
            'pilihan_potensi' => '2',
        ]);
        $resSubmit->assertStatus(302);
        $resSubmit->assertSessionHasNoErrors();

        $resRiwayat = $this->actingAs($shF3)->get('/penilaian/riwayat');
        $resRiwayat->assertStatus(200);

        // Bar chart must NOT appear for Section Head without Foreman
        $resRiwayat->assertDontSee('id="comparisonBarChart"', false);
        $resRiwayat->assertDontSee('Grafik Perbandingan:');

        // Unified comparison table (Target + Actual Section Head in one table) MUST appear
        $resRiwayat->assertSee('Tabel Perbandingan: Target Penilaian vs Actual Section Head');
        $resRiwayat->assertSee('Target Penilaian (Kuota)');
        $resRiwayat->assertSee('Actual Section Head');
        $resRiwayat->assertSee('Belum Dinilai');
        $resRiwayat->assertSee('KJ \ PK');
        $resRiwayat->assertSee('KJ 2');

        $targetCounts = $resRiwayat->viewData('targetCounts');
        $actualCounts = $resRiwayat->viewData('actualCounts');
        $targetKjMatrix = $resRiwayat->viewData('targetKjMatrix');
        $actualKjMatrix = $resRiwayat->viewData('actualKjMatrix');
        $evaluatedList = $resRiwayat->viewData('evaluatedList');

        // Form 07-006-SH (99999) is exempt from target quota; 3 subs (99998..99996) are KJ 2 quota-bound
        $this->assertEquals(3, array_sum($targetCounts));
        $this->assertSame(['KJ 2'], array_keys($targetKjMatrix));
        $this->assertSame(['KJ 2'], array_keys($actualKjMatrix));

        // Verify per-KJ column sums match overall targetCounts cleanly
        foreach (['S', 'A', 'B', 'C', 'K'] as $grade) {
            $this->assertEquals(
                $targetCounts[$grade],
                array_sum(array_column($targetKjMatrix, $grade)),
                "Target column sum mismatch for grade {$grade}"
            );
        }

        // Verify '99998' evaluation reflects in Actual Section Head matrix (KJ 2 count = 1, Total Dinilai = 1)
        $this->assertCount(1, $evaluatedList);
        $this->assertNotEmpty($evaluatedList[0]['nama_bagian']);

        // Verify Nama Bagian column after Section & filter bar order: Bagian -> Position -> KJ / Golongan
        $resRiwayat->assertSeeInOrder([
            '>Section</th>',
            '>Nama Bagian</th>',
            '>Position</th>',
            '>KJ</th>',
            '>Golongan</th>',
        ], false);
        $resRiwayat->assertSeeInOrder([
            'Cari NRP / Nama / Bagian',
            'Bagian',
            'Semua Bagian',
            'Position',
            'Semua Position',
            'KJ / Golongan',
            'Semua KJ / Golongan',
        ], false);
        $resRiwayat->assertDontSee('Semua Section');

        // Verify Bagian & Position filtering work accurately on /penilaian/riwayat
        $targetBagian = $evaluatedList[0]['nama_bagian'];
        $targetPos = $evaluatedList[0]['jabatan'];
        $resFilteredHit = $this->actingAs($shF3)->get(
            '/penilaian/riwayat?bagian=' . urlencode($targetBagian) . '&jabatan=' . urlencode($targetPos)
        );
        $resFilteredHit->assertStatus(200);
        $this->assertCount(1, $resFilteredHit->viewData('evaluatedList'));

        $resFilteredMiss = $this->actingAs($shF3)->get('/penilaian/riwayat?bagian=BAGIAN_TIDAK_ADA');
        $resFilteredMiss->assertStatus(200);
        $this->assertCount(0, $resFilteredMiss->viewData('evaluatedList'));
        $evalRow = $evaluatedList[0];
        $this->assertSame('99998', $evalRow['nrp']);
        $evalGrade = $evalRow['grade'];
        $this->assertContains($evalGrade, ['S', 'A', 'B', 'C', 'K']);
        $this->assertEquals(1, $actualCounts[$evalGrade] ?? 0);
        $this->assertEquals(1, $actualKjMatrix[$evalRow['kj']][$evalGrade] ?? 0);
        $this->assertEquals(1, $actualKjMatrix[$evalRow['kj']]['total'] ?? 0);
        $this->assertEquals(1, $resRiwayat->viewData('totalDinilai'));
        $this->assertEquals(3, $resRiwayat->viewData('totalBelum'));

        // Also check /laporan for Section Head without Foreman: Card 3 + unified table instead of bar chart
        $resLaporan = $this->actingAs($shF3)->get('/laporan');
        $resLaporan->assertStatus(200);
        $resLaporan->assertSee('TOTAL DINILAI');
        $resLaporan->assertSee('Pengerjaan FORM Section Head');
        $resLaporan->assertSee('Proses Section');
        $resLaporan->assertSee('Tahap Sect');
        $resLaporan->assertSee('Approval Department Head');
        $resLaporan->assertDontSee('Menunggu Department Head');
        $resLaporan->assertDontSee('Belum ke DepartmentHead');
        $resLaporan->assertDontSee('Proses Foreman');
        $resLaporan->assertDontSee('Pengerjaan Form Foreman');
        $resLaporan->assertDontSee('Belum ke Section Head');

        $resLaporan->assertDontSee('id="targetVsActualChart"', false);
        $resLaporan->assertDontSee('Grafik Batang:');
        $resLaporan->assertSee('Tabel Perbandingan: Target Penilaian vs Actual Section Head');
        $resLaporan->assertSee('Berdasarkan penilaian section head per KJ (Target vs Actual).');
        $resLaporan->assertSee('Actual: 1 (33%)');
        $resLaporan->assertDontSee('Actual Section Head:');
        $resLaporan->assertSee('Target Penilaian (Kuota)');
        $resLaporan->assertSee('Actual Section Head');
        $resLaporan->assertSee('KJ 2');
        $this->assertArrayNotHasKey('KJ 4', $resLaporan->viewData('targetKjMatrix'));
        $this->assertEquals(3, array_sum($resLaporan->viewData('targetKuota')));
        $this->assertEquals(1, $resLaporan->viewData('sudahDinilai'));

        // Verify filter changed to Bagian (without Nama) & unified table headers including DIVISION on /laporan
        $resLaporan->assertSee('Bagian');
        $resLaporan->assertSee('Semua Bagian');
        $resLaporan->assertDontSee('Semua Nama Bagian');
        $resLaporan->assertDontSee('Semua Seksi');
        $resLaporan->assertSee('>NAMA LENGKAP</th>', false);
        $resLaporan->assertSee('>NAMA BAGIAN</th>', false);
        $resLaporan->assertSee('>SECTION</th>', false);
        $resLaporan->assertSee('>DEPARTMENT</th>', false);
        $resLaporan->assertSee('>DIVISION</th>', false);
        $resLaporan->assertSee('>POSITION</th>', false);
        $resLaporan->assertSee('>GOLONGAN</th>', false);
        $resLaporan->assertDontSee('>NAMA SECTION</th>', false);
        $resLaporan->assertDontSee('>NAMA DEPARTMENT</th>', false);
        $resLaporan->assertDontSee('>NAMA SECT.</th>', false);
        $resLaporan->assertDontSee('>NAMA DEPT.</th>', false);
        $resLaporan->assertDontSee('>JABATAN</th>', false);
        $resLaporan->assertDontSee('>Sub Gol</th>', false);

        $distinctBagians = $resLaporan->viewData('distinctBagians');
        $this->assertNotEmpty($distinctBagians);
        $chosenBagian = $distinctBagians[0];
        $resLaporanBagian = $this->actingAs($shF3)->get('/laporan?bagian=' . urlencode($chosenBagian));
        $resLaporanBagian->assertStatus(200);
        $lapFilteredRows = $resLaporanBagian->viewData('employeeRows');
        $this->assertNotEmpty($lapFilteredRows);
        $this->assertLessThan(4, count($lapFilteredRows));
        foreach ($lapFilteredRows as $lfr) {
            $this->assertEquals(
                strtoupper(rtrim($chosenBagian, '.')),
                strtoupper(rtrim($lfr['nama_bagian'], '.'))
            );
        }

        // Verify quick-login banner renders cleanly without literal &bull; for both Foreman & Section Head
        $resQuickForeman = $this->followingRedirects()->get('/quick-login/62');
        $resQuickForeman->assertStatus(200);
        $resQuickForeman->assertSee('Berhasil masuk sebagai');
        $resQuickForeman->assertSee('- Dept: PROD 2');
        $resQuickForeman->assertDontSee('&amp;bull;', false);
        $resQuickForeman->assertDontSee('&bull;');

        $resQuickSh = $this->followingRedirects()->get('/quick-login/99999');
        $resQuickSh->assertStatus(200);
        $resQuickSh->assertSee('Berhasil masuk sebagai');
        $resQuickSh->assertSee('(Section Head) - Dept:');
        $resQuickSh->assertDontSee('&amp;bull;', false);
        $resQuickSh->assertDontSee('&bull;');
    }

    public function test_laporan_export_confirmation_excel_xlsx_and_form_specific_pdf_without_errors(): void
    {
        $shF3 = User::where('nrp', '99999')->firstOrFail();

        // 1. Laporan page renders pre-download confirmation modal triggers & PDF form breakdown
        $resLaporan = $this->actingAs($shF3)->get('/laporan');
        $resLaporan->assertStatus(200);
        $resLaporan->assertSee('Konfirmasi Download');
        $resLaporan->assertSee('Ya, Lanjutkan Download');
        $resLaporan->assertSee("openConfirm('pdf')", false);
        $resLaporan->assertSee("openConfirm('excel')", false);
        $resLaporan->assertSee('Form Section Head (07-006)');
        $resLaporan->assertSee('Form KJ 1-3 (07-001)');

        // 2. Excel (.xlsx) export returns cleanly formatted OOXML workbook matching Section Head table columns
        $resExcel = $this->actingAs($shF3)->get('/laporan/export-excel');
        $resExcel->assertStatus(200);
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string)$resExcel->headers->get('Content-Type')
        );
        $this->assertStringContainsString('.xlsx', (string)$resExcel->headers->get('Content-Disposition'));

        $tmpXlsx = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sh_laporan_export_' . uniqid() . '.xlsx';
        file_put_contents($tmpXlsx, $resExcel->getContent());

        try {
            $parsed = \App\Services\ExcelKaryawanReader::parseXlsx($tmpXlsx);
            $this->assertCount(4, $parsed); // 4 scoped rows (99999 + 99998..99996)
            $this->assertSame([
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
                'NILAI PK SECT HEAD',
            ], array_keys($parsed[0]));
            $this->assertSame('PRODUCTION A DIV', $parsed[0]['DIVISION']);

            $xlsxZip = new \ZipArchive();
            $this->assertTrue($xlsxZip->open($tmpXlsx) === true);
            $sheetXml = (string)$xlsxZip->getFromName('xl/worksheets/sheet1.xml');
            $stylesXml = (string)$xlsxZip->getFromName('xl/styles.xml');
            $xlsxZip->close();

            $this->assertStringContainsString('customWidth="1" bestFit="1"', $sheetXml);
            $this->assertStringContainsString('state="frozen"', $sheetXml);
            $this->assertStringContainsString('<autoFilter ref="A1:R5"/>', $sheetXml);
            $this->assertStringContainsString('style="thin"', $stylesXml);
        } finally {
            @unlink($tmpXlsx);
        }

        // 3. Sign Section Head self-appraisal (menunggu_dept_head) at Kolom 1 ('foreman') -> waiting Dept Head
        $docSh = DB::table('dokumen')->where('nrp', '99999')->first();
        $this->assertNotNull($docSh);
        $canvas = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
        $this->actingAs($shF3)->post(route('penilaian.sign', $docSh->id), [
            'level' => 'foreman',
            'password' => 'password123',
            'signature_base64' => $canvas,
            'scores' => $this->buildScores('07-006-SH', 4),
            'pilihan_potensi' => '1',
        ])->assertRedirect();

        // Single PDF export for Section Head (99999) returns 07-006-SH form PDF without error
        $resPdfSh = $this->actingAs($shF3)->get('/laporan/export-pdf?q=99999');
        $resPdfSh->assertStatus(200);
        $this->assertStringContainsString('application/pdf', (string)$resPdfSh->headers->get('Content-Type'));
        $this->assertStringContainsString('Penilaian_Karya_07-006-SH_99999', (string)$resPdfSh->headers->get('Content-Disposition'));
        $shPdfBytes = (string)$resPdfSh->getContent();
        $this->assertStringStartsWith('%PDF-', $shPdfBytes);
        $this->assertGreaterThan(5000, strlen($shPdfBytes));

        // Verify 99999 document moved to menunggu_dept_head (Col 7 Dept Head not signed yet)
        $docShFresh = DB::table('dokumen')->where('id', $docSh->id)->first();
        $this->assertSame('menunggu_dept_head', $docShFresh->status);
        $this->assertSame('07-006-SH', $docShFresh->kode_form);

        // 4. Single PDF export for KJ 1-3 employee (NRP 99998) returns 07-001 form PDF without error
        $doc99998 = DB::table('dokumen')->where('nrp', '99998')->first();
        $this->assertNotNull($doc99998);
        $this->assertSame('07-001', $doc99998->kode_form);
        $resPdfSub = $this->actingAs($shF3)->get('/laporan/export-pdf?q=99998');
        $resPdfSub->assertStatus(200);
        $this->assertStringContainsString('application/pdf', (string)$resPdfSub->headers->get('Content-Type'));
        $this->assertStringContainsString('Penilaian_Karya_07-001_99998', (string)$resPdfSub->headers->get('Content-Disposition'));
        $pdfSubBytes = (string)$resPdfSub->getContent();
        $this->assertStringStartsWith('%PDF-', $pdfSubBytes);

        // Decompress DomPDF streams for 07-001 (DejaVu Sans WinAnsi) to verify exact KJ 1-3 form text inside PDF
        $decodedSubText = '';
        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdfSubBytes, $matches)) {
            foreach ($matches[1] as $streamData) {
                $inflated = @gzuncompress($streamData);
                if ($inflated !== false) {
                    $decodedSubText .= $inflated . "\n";
                }
            }
        }
        $this->assertStringContainsString('07-001', $decodedSubText);
        $this->assertStringContainsString('KJ : 1 - 3', $decodedSubText);

        // 5. Bulk ZIP PDF export across full Section Head scope succeeds without timeout/error
        $resBulkZip = $this->actingAs($shF3)->get('/laporan/export-pdf');
        $resBulkZip->assertStatus(200);
        $this->assertStringContainsString('application/zip', (string)$resBulkZip->headers->get('Content-Type'));

        $zipBinary = file_get_contents($resBulkZip->getFile()->getPathname());
        $this->assertNotFalse($zipBinary);
        $tmpZip = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sh_laporan_pdfs_' . uniqid() . '.zip';
        file_put_contents($tmpZip, $zipBinary);

        try {
            $zip = new \ZipArchive();
            $this->assertTrue($zip->open($tmpZip) === true);
            $this->assertSame(4, $zip->numFiles);

            $hasSh07006 = false;
            $hasKj07001 = false;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entryName = $zip->getNameIndex($i);
                $this->assertStringEndsWith('.pdf', $entryName);
                $entryBytes = (string)$zip->getFromIndex($i);
                $this->assertStringStartsWith('%PDF-', $entryBytes);
                if (str_contains($entryName, 'Penilaian_Karya_07-006-SH_99999')) {
                    $hasSh07006 = true;
                }
                if (str_contains($entryName, 'Penilaian_Karya_07-001_')) {
                    $hasKj07001 = true;
                }
            }
            $this->assertTrue($hasSh07006, 'Bulk ZIP must include 99999 07-006-SH PDF');
            $this->assertTrue($hasKj07001, 'Bulk ZIP must include KJ 1-3 07-001 PDFs');
            $zip->close();
        } finally {
            @unlink($tmpZip);
            if ($resBulkZip->getFile() && file_exists($resBulkZip->getFile()->getPathname())) {
                @unlink($resBulkZip->getFile()->getPathname());
            }
        }

        // 6. Empty filter download handles gracefully with redirect back + error message (no 500)
        $resEmpty = $this->actingAs($shF3)->from('/laporan')->get('/laporan/export-pdf?q=TIDAK_ADA_DATA_9999');
        $resEmpty->assertRedirect(route('laporan.index', ['q' => 'TIDAK_ADA_DATA_9999']));
        $resEmpty->assertSessionHas('error');
    }
}
