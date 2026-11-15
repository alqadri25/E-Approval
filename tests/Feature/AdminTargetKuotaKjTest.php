<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminTargetKuotaKjTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh --seed');
    }

    /**
     * Test Admin and Superadmin can view Target Kuota page with KJ elements.
     */
    public function test_admin_and_superadmin_can_view_target_kuota_kj_elements(): void
    {
        $admin = User::where('email', 'admin@musashi.co.id')->first();
        $this->assertNotNull($admin);

        $response = $this->actingAs($admin)->get('/admin/target-kuota');
        $response->assertStatus(200);
        $response->assertDontSee('Mode 1: Berdasarkan Divisi &amp; Departemen', false);
        $response->assertDontSee('Mode 3: Target Level Form (Selain 07-006)', false);
        $response->assertDontSee('Tambah KJ / Golongan Baru', false);
        $response->assertSee('KJ 2');
        $response->assertSee('KJ 3');
        $response->assertSee('KJ 4');
        $response->assertSee('KJ 5');
        $response->assertSee('Pengaturan Target Departemen per Golongan/KJ', false);
        $response->assertSee('Total Karyawan');
        $response->assertSee('Total Division');
        $response->assertSee('Total Department');
        $response->assertSee('Total Section');
        $response->assertSee('Total Foreman');
        $response->assertSee('TOTAL TARGET SEMUA DEPARTEMEN');
        $response->assertSee('Rincian Pengaturan Target per KJ &amp; Departemen', false);

        $superadmin = User::where('email', 'superadmin@musashi.co.id')->first();
        if ($superadmin) {
            $responseSa = $this->actingAs($superadmin)->get('/superadmin/target-kuota');
            $responseSa->assertStatus(200);
            $responseSa->assertDontSee('Tambah KJ / Golongan Baru', false);
            $responseSa->assertSee('Pengaturan Target Departemen per Golongan/KJ', false);
        }
    }

    /**
     * Test Admin can save target quota per KJ & Departemen.
     */
    public function test_admin_can_save_target_kuota_kj(): void
    {
        $admin = User::where('email', 'admin@musashi.co.id')->first();

        $response = $this->actingAs($admin)->post('/admin/target-kuota/kj', [
            'kj' => 'KJ 2',
            'target_departemen' => 'ENG',
            'kuota_s' => 1,
            'kuota_a' => 2,
            'kuota_b' => 2,
            'kuota_c' => 0,
            'kuota_k' => 0,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $record = DB::table('kuota_departemen')
            ->where('kj', 'KJ 2')
            ->where('departemen', 'ENG')
            ->first();

        $this->assertNotNull($record);
        $this->assertEquals(1, $record->kuota_s);
        $this->assertEquals(2, $record->kuota_a);
        $this->assertEquals(2, $record->kuota_b);
        $this->assertEquals(0, $record->kuota_c);
        $this->assertEquals(0, $record->kuota_k);

        // Test save by Divisi (e.g. DIV-MFG)
        $responseDiv = $this->actingAs($admin)->post('/admin/target-kuota/kj', [
            'kj' => 'KJ 3',
            'departemen' => 'DIV-MFG',
            'quota_s' => 2,
            'quota_a' => 4,
            'quota_b' => 4,
            'quota_c' => 2,
            'quota_k' => 0,
        ]);
        $responseDiv->assertRedirect();
        $responseDiv->assertSessionHas('success');
        $responseDiv->assertSessionHas('selected_kj_dept', 'DIV-MFG');
        $responseDiv->assertSessionHas('selected_kj', 'KJ 3');

        $divSetting = DB::table('pengaturan')->where('key', 'target_kuota_kj_DIV-MFG_KJ3')->first();
        $this->assertNotNull($divSetting);
        $divData = json_decode($divSetting->value, true);
        $this->assertEquals(2, $divData['S']);
        $this->assertEquals(4, $divData['A']);
    }

    /**
     * Test dynamically adding a new KJ (KJ 6) and deleting it.
     */
    public function test_admin_can_dynamically_add_and_delete_new_kj_6_and_above(): void
    {
        $admin = User::where('email', 'admin@musashi.co.id')->first();

        // 1. Add KJ 6
        $responseAdd = $this->actingAs($admin)->post('/admin/target-kuota/tambah-kj', [
            'kode' => 'KJ 6',
            'golongan' => 'Golongan VI',
            'deskripsi' => 'Lead Expert / Specialist',
        ]);

        $responseAdd->assertRedirect();
        $responseAdd->assertSessionHas('success');

        // Verify it appears on the target-kuota page
        $responseView = $this->actingAs($admin)->get('/admin/target-kuota');
        $responseView->assertSee('KJ 6');
        $responseView->assertSee('GOLONGAN VI');

        // 2. Try to delete a default KJ (KJ 2) - should fail/not be deleted
        $responseDeleteDefault = $this->actingAs($admin)->post('/admin/target-kuota/hapus-kj', [
            'kode_kj' => 'KJ 2',
        ]);
        $responseDeleteDefault->assertSessionHas('error');

        // 3. Delete custom KJ 6 - should succeed
        $responseDeleteKj6 = $this->actingAs($admin)->post('/admin/target-kuota/hapus-kj', [
            'kode_kj' => 'KJ 6',
        ]);
        $responseDeleteKj6->assertSessionHas('success');

        // Verify KJ 6 is no longer in master list
        $setting = DB::table('pengaturan')->where('key', 'master_kj_list')->value('value');
        $this->assertStringNotContainsString('KJ 6', $setting ?? '');
    }

    /**
     * Test KJ quota changes automatically sync with parent target kuota induk (Division & Department).
     */
    public function test_kj_quota_changes_automatically_sync_to_parent_target_kuota_induk(): void
    {
        $admin = User::where('email', 'admin@musashi.co.id')->first();

        // Save KJ 2 for DIV-E (1 C)
        $this->actingAs($admin)->post('/admin/target-kuota/kj', [
            'kj' => 'KJ 2',
            'departemen' => 'DIV-E',
            'quota_s' => 0, 'quota_a' => 0, 'quota_b' => 0, 'quota_c' => 1, 'quota_k' => 0,
        ]);

        // Save KJ 3 for DIV-E (1 B)
        $this->actingAs($admin)->post('/admin/target-kuota/kj', [
            'kj' => 'KJ 3',
            'departemen' => 'DIV-E',
            'quota_s' => 0, 'quota_a' => 0, 'quota_b' => 1, 'quota_c' => 0, 'quota_k' => 0,
        ]);

        // Save KJ 4 for DIV-E (1 A, 1 B)
        $this->actingAs($admin)->post('/admin/target-kuota/kj', [
            'kj' => 'KJ 4',
            'departemen' => 'DIV-E',
            'quota_s' => 0, 'quota_a' => 1, 'quota_b' => 1, 'quota_c' => 0, 'quota_k' => 0,
        ]);

        // Save KJ 5 for DIV-E (1 S, 1 A)
        $this->actingAs($admin)->post('/admin/target-kuota/kj', [
            'kj' => 'KJ 5',
            'departemen' => 'DIV-E',
            'quota_s' => 1, 'quota_a' => 1, 'quota_b' => 0, 'quota_c' => 0, 'quota_k' => 0,
        ]);

        // Verify target_kuota_div_DIV-E was automatically synchronized to total sum of KJ (6 Org: 1 S, 2 A, 2 B, 1 C, 0 K)
        $divSetting = DB::table('pengaturan')->where('key', 'target_kuota_div_DIV-E')->first();
        $this->assertNotNull($divSetting);
        $divData = json_decode($divSetting->value, true);
        $this->assertEquals(1, $divData['S']);
        $this->assertEquals(2, $divData['A']);
        $this->assertEquals(2, $divData['B']);
        $this->assertEquals(1, $divData['C']);
        $this->assertEquals(0, $divData['K']);
        $this->assertEquals(6, $divData['total']);

        // Verify target_kuota_dept_ENGINEERING is also identical (synced)
        $deptSetting = DB::table('pengaturan')->where('key', 'target_kuota_dept_ENGINEERING')->first();
        $this->assertNotNull($deptSetting);
        $deptData = json_decode($deptSetting->value, true);
        $this->assertEquals(6, $deptData['total']);
        $this->assertEquals(1, $deptData['S']);
        $this->assertEquals(2, $deptData['A']);

        // Verify page loads with the synchronized values
        $response = $this->actingAs($admin)->get('/admin/target-kuota?tab=kj&dept=DIV-E&kj=KJ+2');
        $response->assertStatus(200);
        $response->assertSee('Selalu Sinkron: Target Induk Mengikuti Total KJ', false);
    }

    /**
     * Test Pengaturan Target Level Form (only non-07-006 forms bound to target; 07-006 exempt)
     * and dynamic operational KJ 6 Foreman handling without polluting master_kj_list.
     */
    public function test_admin_and_superadmin_target_level_form_rules_and_operational_kj6(): void
    {
        $admin = User::where('email', 'admin@musashi.co.id')->firstOrFail();
        $superadmin = User::where('email', 'superadmin@musashi.co.id')->firstOrFail();

        $resAdmin = $this->actingAs($admin)->get('/admin/target-kuota?tab=level');
        $resAdmin->assertStatus(200);
        $resAdmin->assertDontSee('Mode 3: Target Level Form (Selain 07-006)', false);
        $resAdmin->assertSee('Kena Target');
        $resAdmin->assertSee('Bebas Target');
        $resAdmin->assertDontSee('Section Head / Asisten Manajer');

        // Expected PROD 2 quota-bound headcount from DB (all non-07-006 incl. Leader 07-003, Foreman KJ5/KJ6 07-004, KJ1-3, KJ4-5)
        $prod2Subs = DB::table('karyawan')->where('departemen', 'PROD 2')->get()->map(fn ($r) => (array)$r);
        $prod2QuotaCount = $prod2Subs->filter(fn ($k) => !\App\Http\Controllers\PenilaianController::isQuotaExemptEmployee($k))->count();
        $kjTargetsMap = $resAdmin->viewData('kjTargetsMap');
        $prod2KjSum = 0;
        foreach ($resAdmin->viewData('displayKjList') as $kjDef) {
            $key = 'PROD 2_' . str_replace(' ', '', $kjDef['kode']);
            $this->assertArrayHasKey($key, $kjTargetsMap);
            $prod2KjSum += $kjTargetsMap[$key]['s'] + $kjTargetsMap[$key]['a'] + $kjTargetsMap[$key]['b'] + $kjTargetsMap[$key]['c'] + $kjTargetsMap[$key]['k'];
        }
        $this->assertEquals($prod2QuotaCount, $prod2KjSum);
        $this->assertArrayNotHasKey('PROD 2_KJ6', $kjTargetsMap);
        $this->assertEquals(6, $kjTargetsMap['PROD 2_KJ5']['total'] ?? 0);
        $this->assertEquals(4, ($kjTargetsMap['PROD 2_KJ5']['b'] ?? 0) + ($kjTargetsMap['PROD 2_KJ5']['c'] ?? 0));

        // Saving target per Level Form (LEADER 07-003 & FOREMAN 07-004) persists and syncs unit total
        $resPostLeader = $this->actingAs($admin)->post('/admin/target-kuota/level', [
            'level_unit' => 'PROD 2',
            'level_form' => 'LEADER',
            'quota_s' => 2,
            'quota_a' => 6,
            'quota_b' => 6,
            'quota_c' => 4,
            'quota_k' => 2,
            'sync_to_unit' => '1',
        ]);
        $resPostLeader->assertRedirect();
        $resPostLeader->assertSessionHas('success');

        $savedLeader = DB::table('pengaturan')->where('key', 'target_kuota_level_PROD 2_LEADER')->value('value');
        $this->assertNotNull($savedLeader);
        $decodedLeader = json_decode($savedLeader, true);
        $this->assertEquals(20, $decodedLeader['total']);
        $this->assertNotNull(DB::table('pengaturan')->where('key', 'target_kuota_dept_PROD 2')->value('value'));

        // Form 07-006 (EXEMPT_07006) must be rejected if posted as quota-bound level
        $resPostExempt = $this->actingAs($superadmin)->post('/superadmin/target-kuota/level', [
            'level_unit' => 'PROD 2',
            'level_form' => 'EXEMPT_07006',
            'quota_s' => 1,
            'quota_a' => 1,
            'quota_b' => 1,
            'quota_c' => 0,
            'quota_k' => 0,
        ]);
        $resPostExempt->assertSessionHas('error');
    }

    /**
     * Test Target Kuota auto-discovers real divisions from karyawan DB (DATA PK.xlsx currently has
     * PRODUCTION A DIV + PRODUCTION B DIV, and dynamically scales up to 9 divisions in a 3-column grid).
     */
    public function test_target_kuota_auto_discovers_real_divisions_from_karyawan_and_supports_9_divisions(): void
    {
        $admin = User::where('email', 'admin@musashi.co.id')->firstOrFail();

        // 1. Initial state from seeded DATA PK.xlsx: 2 real divisions (PRODUCTION A DIV & PRODUCTION B DIV)
        $resInitial = $this->actingAs($admin)->get('/admin/target-kuota');
        $resInitial->assertStatus(200);
        $resInitial->assertSee('Pilih Division untuk Pengaturan Target');
        $resInitial->assertDontSee('1 Division Aktif');
        $resInitial->assertSee('PRODUCTION A DIV');
        $resInitial->assertSee('PRODUCTION B DIV');
        $resInitial->assertSee('Pilih Departemen yang Mau Diatur — PRODUCTION A DIV');
        $resInitial->assertDontSee('Belum Ada Data');
        $resInitial->assertDontSee('Division E (Technical &amp; Engineering)', false);

        $realDivsInitial = $resInitial->viewData('realDivisions');
        $this->assertCount(2, $realDivsInitial);
        $this->assertEquals('PRODUCTION A DIV', $realDivsInitial[0]['nama']);
        $this->assertEquals(178, $realDivsInitial[0]['total_karyawan']);
        $this->assertEquals(['PROD 1', 'PROD 2', 'PROD 3'], $realDivsInitial[0]['dept_codes']);
        $this->assertEquals(6, $realDivsInitial[0]['foreman']);
        $this->assertEquals(3, $realDivsInitial[0]['section_head']);
        $this->assertEquals(1, $realDivsInitial[0]['dept_head']);
        $this->assertEquals(20, $realDivsInitial[0]['leader']);
        $this->assertEquals(1, $realDivsInitial[0]['div_head']);

        $this->assertEquals('PRODUCTION B DIV', $realDivsInitial[1]['nama']);
        $this->assertEquals(2, $realDivsInitial[1]['total_karyawan']);
        $this->assertEquals(['PROD 4'], $realDivsInitial[1]['dept_codes']);
        $this->assertEquals(1, $realDivsInitial[1]['div_head']);

        // 2. Add 7 more divisions (total 9 divisions) into karyawan table
        $now = now();
        $rows = [];
        for ($i = 2; $i <= 8; $i++) {
            $divName = "DIVISION TEST {$i}";
            $deptName = "DEPT{$i}";
            $rows[] = [
                'nrp' => "900{$i}01", 'nama_lengkap' => "OP DIV {$i}", 'golongan' => 'GOLONGAN II', 'sub_gol' => 'IIB',
                'kj' => 'KJ 2', 'jabatan' => 'OPERATOR', 'departemen' => $deptName, 'seksi' => 'SEC 1',
                'division' => $divName, 'level_form' => '07-001', 'created_at' => $now, 'updated_at' => $now,
            ];
            $rows[] = [
                'nrp' => "900{$i}02", 'nama_lengkap' => "FM DIV {$i}", 'golongan' => 'GOLONGAN V', 'sub_gol' => 'VA',
                'kj' => 'KJ 5', 'jabatan' => 'FOREMAN', 'departemen' => $deptName, 'seksi' => 'SEC 1',
                'division' => $divName, 'level_form' => '07-004', 'created_at' => $now, 'updated_at' => $now,
            ];
            $rows[] = [
                'nrp' => "900{$i}03", 'nama_lengkap' => "SH DIV {$i}", 'golongan' => 'GOLONGAN VI', 'sub_gol' => 'VIA',
                'kj' => 'KJ 6', 'jabatan' => 'SECTION HEAD', 'departemen' => $deptName, 'seksi' => 'SEC 1',
                'division' => $divName, 'level_form' => '07-006-SH', 'created_at' => $now, 'updated_at' => $now,
            ];
            $rows[] = [
                'nrp' => "900{$i}04", 'nama_lengkap' => "DH DIV {$i}", 'golongan' => 'GOLONGAN VII', 'sub_gol' => 'VIIA',
                'kj' => 'KJ 7', 'jabatan' => 'DEPT HEAD', 'departemen' => $deptName, 'seksi' => '-',
                'division' => $divName, 'level_form' => '07-006-DH', 'created_at' => $now, 'updated_at' => $now,
            ];
        }
        DB::table('karyawan')->insert($rows);
        app(\App\Contracts\SheetRepositoryInterface::class)->clearCache();

        $resNine = $this->actingAs($admin)->get('/admin/target-kuota?dept=DEPT2');
        $resNine->assertStatus(200);
        $resNine->assertSee('Pilih Division untuk Pengaturan Target');
        $resNine->assertSee('PRODUCTION A DIV');
        $resNine->assertSee('Pilih Departemen yang Mau Diatur — DIVISION TEST 2');
        for ($i = 2; $i <= 8; $i++) {
            $resNine->assertSee("DIVISION TEST {$i}");
        }
        $resNine->assertSee('Pengaturan Target Departemen per Golongan/KJ');
        $resNine->assertSee('DEPT2');

        // 3. Batch-save KJ quotas for dynamically discovered department DEPT2
        $resSave = $this->actingAs($admin)->post('/admin/target-kuota/kj', [
            'departemen' => 'DEPT2',
            'batch' => '1',
            'kj_rows' => [
                ['kj' => 'KJ 2', 'quota_s' => 0, 'quota_a' => 1, 'quota_b' => 0, 'quota_c' => 0, 'quota_k' => 0],
                ['kj' => 'KJ 5', 'quota_s' => 1, 'quota_a' => 0, 'quota_b' => 0, 'quota_c' => 0, 'quota_k' => 0],
            ],
        ]);
        $resSave->assertRedirect();
        $resSave->assertSessionHas('success');

        $savedDept2 = DB::table('kuota_departemen')->where('departemen', 'DEPT2')->where('kj', 'KJ 2')->first();
        $this->assertNotNull($savedDept2);
        $this->assertEquals(1, $savedDept2->kuota_a);
    }

    /**
     * Test uploading a DATA PK.xlsx-format spreadsheet adds new Divisions/Departments
     * to both /admin/karyawan (including divisi filter) and /admin/target-kuota,
     * and sync_existing=1 updates existing employees on re-upload.
     */
    public function test_excel_import_syncs_new_division_and_existing_rows_to_karyawan_and_target_kuota(): void
    {
        $admin = User::where('role', 'admin')->first();
        $this->assertNotNull($admin);

        $buildXlsx = function (array $dataRows): string {
            $allRows = array_merge([[
                'EMPLOYEENO', 'FULLNAME', 'ORGSTRUCTURECODE', 'ORGSTRUCTURENAME',
                'LINE CATEGORY', 'POSITION', 'DIVISION', 'DEPT', 'SECTION',
                'EMPLOYMENTTYPE', 'JOBCLASSCODE', 'JOBLEVELCODE', 'JOINDATE',
            ]], $dataRows);

            $strings = [];
            $stringIndex = [];
            $getStringIdx = function (string $val) use (&$strings, &$stringIndex): int {
                if (!isset($stringIndex[$val])) {
                    $stringIndex[$val] = count($strings);
                    $strings[] = $val;
                }
                return $stringIndex[$val];
            };

            $colLetter = function (int $idx): string {
                $letter = '';
                while ($idx >= 0) {
                    $letter = chr(65 + ($idx % 26)) . $letter;
                    $idx = intdiv($idx, 26) - 1;
                }
                return $letter;
            };

            $sheetRowsXml = '';
            foreach ($allRows as $rIdx => $row) {
                $rNum = $rIdx + 1;
                $sheetRowsXml .= '<row r="' . $rNum . '">';
                foreach ($row as $cIdx => $cellVal) {
                    $ref = $colLetter($cIdx) . $rNum;
                    $sIdx = $getStringIdx((string)$cellVal);
                    $sheetRowsXml .= '<c r="' . $ref . '" t="s"><v>' . $sIdx . '</v></c>';
                }
                $sheetRowsXml .= '</row>';
            }

            $siXml = '';
            foreach ($strings as $s) {
                $siXml .= '<si><t>' . htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</t></si>';
            }

            $tmpBase = tempnam(sys_get_temp_dir(), 'pk_import_');
            $tmpXlsx = $tmpBase . '.xlsx';
            @unlink($tmpBase);

            $zip = new \ZipArchive();
            $zip->open($tmpXlsx, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/></Types>');
            $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/></Relationships>');
            $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($strings) . '" uniqueCount="' . count($strings) . '">' . $siXml . '</sst>');
            $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . $sheetRowsXml . '</sheetData></worksheet>');
            $zip->close();

            return $tmpXlsx;
        };

        $file1 = $buildXlsx([
            ['99100', 'DIV HEAD MAINT', '1150000000', 'ENGINEERING DIV', '-', 'DIV HEAD', 'ENGINEERING DIV', '-', '-', 'Permanent', 'VIIA', 'KJ 8', '2018-01-02'],
            ['99101', 'DEPT HEAD MAINT', '1151000000', 'MAINTENANCE DEPT', '-', 'DEPT HEAD', 'ENGINEERING DIV', 'MAINTENANCE', '-', 'Permanent', 'VIA', 'KJ 7', '2019-03-04'],
            ['99102', 'SH MAINT', '1151100000', 'MECH SEC', '-', 'SECTION HEAD', 'ENGINEERING DIV', 'MAINTENANCE', 'MECH SEC', 'Permanent', 'VA', 'KJ 6', '2020-05-06'],
            ['99103', 'FM MAINT', '1151100001', 'MECH LINE A', 'LINE M1', 'FOREMAN', 'ENGINEERING DIV', 'MAINTENANCE', 'MECH SEC', 'Permanent', 'IVA', 'KJ 5', '2021-07-08'],
            ['99104', 'OP MAINT', '1151100001', 'MECH LINE A', 'LINE M1', 'OPERATOR', 'ENGINEERING DIV', 'MAINTENANCE', 'MECH SEC', 'Permanent', 'IIB', 'KJ 2', '2023-09-10'],
        ]);

        try {
            $upload1 = new UploadedFile(
                $file1,
                'new_division_pk.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                null,
                true
            );

            $resImport1 = $this->actingAs($admin)->post(route('karyawan.import'), [
                'sheet_file' => $upload1,
                'sync_existing' => '1',
            ]);
            $resImport1->assertRedirect();
            $resImport1->assertSessionHas('success');

            $op = DB::table('karyawan')->where('nrp', '99104')->first();
            $this->assertNotNull($op);
            $this->assertEquals('ENGINEERING DIV', $op->division);
            $this->assertEquals('MAINTENANCE', $op->departemen);
            $this->assertEquals('MECH SEC', $op->seksi);
            $this->assertEquals('99103', $op->nrp_foreman);
            $this->assertEquals('99102', $op->nrp_section_head);
            $this->assertEquals('99101', $op->nrp_dept_head);
            $this->assertEquals('99100', $op->nrp_div_head);

            $resKaryawan = $this->actingAs($admin)->get('/admin/karyawan?divisi=ENGINEERING+DIV');
            $resKaryawan->assertStatus(200);
            $resKaryawan->assertSee('OP MAINT');
            $resKaryawan->assertSee('ENGINEERING DIV');

            $resTarget = $this->actingAs($admin)->get('/admin/target-kuota?dept=MAINTENANCE');
            $resTarget->assertStatus(200);
            $resTarget->assertSee('ENGINEERING DIV');
            $resTarget->assertSee('MAINTENANCE');

            // Re-upload same NRP with updated name & KJ using sync_existing=1
            $file2 = $buildXlsx([
                ['99104', 'OP MAINT UPDATED', '1151100001', 'MECH LINE A', 'LINE M1', 'OPERATOR', 'ENGINEERING DIV', 'MAINTENANCE', 'MECH SEC', 'Permanent', 'IIIA', 'KJ 3', '2023-09-10'],
            ]);

            try {
                $upload2 = new UploadedFile(
                    $file2,
                    'update_division_pk.xlsx',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    null,
                    true
                );

                $resImport2 = $this->actingAs($admin)->post(route('karyawan.import'), [
                    'sheet_file' => $upload2,
                    'sync_existing' => '1',
                ]);
                $resImport2->assertRedirect();
                $resImport2->assertSessionHas('success');

                $opUpdated = DB::table('karyawan')->where('nrp', '99104')->first();
                $this->assertEquals('OP MAINT UPDATED', $opUpdated->nama_lengkap);
                $this->assertEquals('KJ 3', $opUpdated->kj);
            } finally {
                @unlink($file2);
            }
        } finally {
            @unlink($file1);
        }
    }

    public function test_admin_target_kuota_wording_columns_and_interactive_scopes(): void
    {
        $admin = User::where('email', 'admin@musashi.co.id')->first();
        $this->assertNotNull($admin);

        // 1. GET /admin/target-kuota
        $res = $this->actingAs($admin)->get('/admin/target-kuota');
        $res->assertStatus(200);

        // Verify removed subtitle
        $res->assertDontSee('KEBIJAKAN EVALUASI');
        $res->assertDontSee('DISTRIBUSI TARGET KUOTA NILAI');

        // Verify updated wording
        $res->assertSee('Pilih Division untuk Pengaturan Target');
        $res->assertSee('Pengaturan Target Departemen per Golongan/KJ');
        $res->assertSee('Rincian Pengaturan Target per KJ &amp; Departemen', false);

        // Verify table columns: Kode Dept, Divisi, Departemen
        $res->assertSee('Kode Dept');
        $res->assertSee('Divisi');
        $res->assertSee('Departemen');
        $res->assertSee('Production 1');
        $res->assertDontSee('Production 1 (PROD 1)');

        // Verify interactive scope stats
        $stats = $res->viewData('interactiveScopeStats');
        $topStats = $res->viewData('topSummaryStats');
        $this->assertNotNull($stats);
        $this->assertArrayHasKey('all', $stats);
        $this->assertSame($topStats['total_karyawan'], $stats['all']['total_karyawan']);
        $this->assertSame($topStats['kena_target'], $stats['all']['kena_target']);
        $this->assertSame($topStats['bebas_target'], $stats['all']['bebas_target']);

        // Verify dept scope PROD 2
        $resProd2 = $this->actingAs($admin)->get('/admin/target-kuota?dept=PROD+2');
        $resProd2->assertStatus(200);
        $statsProd2 = $resProd2->viewData('interactiveScopeStats');
        $this->assertArrayHasKey('dept_PROD 2', $statsProd2);
        $this->assertGreaterThan(0, $statsProd2['dept_PROD 2']['total_karyawan']);
        $this->assertGreaterThan(0, $statsProd2['dept_PROD 2']['kena_target']);
    }
}
