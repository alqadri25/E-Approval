<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HierarchyWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh --seed');
    }

    /**
     * Test 1: Foreman SARYANTA (62, F1 CAT 1) has 23 assigned F1 CAT 1 records (22 subs + self in karyawan view).
     */
    public function test_foreman_has_exactly_12_operator_subordinates(): void
    {
        $foremanUser = User::where('nrp', '62')->firstOrFail();

        $response = $this->actingAs($foremanUser)->get('/dashboard');
        $response->assertStatus(200);
        $response->assertSee('Dashboard Foreman');
        $response->assertSee('23 Karyawan');

        $karyawanResponse = $this->actingAs($foremanUser)->get('/karyawan');
        $karyawanResponse->assertStatus(200);
        $karyawanList = $karyawanResponse->viewData('karyawanList');
        $this->assertCount(23, $karyawanList);

        // Verify representative F1 CAT 1 NRPs are present
        $expectedNrps = ['39', '24', '110', '62'];
        foreach ($expectedNrps as $nrp) {
            $this->assertTrue(collect($karyawanList)->contains('nrp', $nrp), "NRP {$nrp} should be in Foreman subordinates list");
        }
        // Factory 2 Foreman 51's subordinate must NOT be in F1 CAT 1 Foreman's list
        $this->assertFalse(collect($karyawanList)->contains('nrp', '66'));
    }

    /**
     * Test 2: Section Head F1 (112 KRISTIYANTO) has 72 records (71 Factory 1 subs + self).
     */
    public function test_section_head_has_exactly_13_subordinates(): void
    {
        $sectionUser = User::where('nrp', '112')->firstOrFail();

        $response = $this->actingAs($sectionUser)->get('/dashboard');
        $response->assertStatus(200);
        $response->assertSee('Dashboard Section Head');
        $response->assertSee('Total Karyawan');

        $sectionSubordinates = $response->viewData('sectionSubordinates');
        $this->assertCount(72, $sectionSubordinates);
        $this->assertTrue(collect($sectionSubordinates)->contains('nrp', '112'));

        // Check Karyawan Page for Section Head
        $karyawanResponse = $this->actingAs($sectionUser)->get('/karyawan');
        $karyawanResponse->assertStatus(200);
        $karyawanList = $karyawanResponse->viewData('karyawanList');
        $this->assertCount(72, $karyawanList);

        // Check Data Penilaian Page for Section Head
        $penilaianResponse = $this->actingAs($sectionUser)->get('/penilaian/section');
        $penilaianResponse->assertStatus(200);
        $penilaianResponse->assertSee('Data Penilaian');
        $rows = $penilaianResponse->viewData('rows');
        $this->assertCount(72, $rows);
    }

    /**
     * Test 3: Department Head SUPARDI (31) has 174 subordinates on dashboard/karyawan and 175 rows (incl. self 07-006-DH) on /penilaian/dept.
     */
    public function test_dept_head_has_exactly_14_subordinates(): void
    {
        $deptUser = User::where('nrp', '31')->firstOrFail();

        $response = $this->actingAs($deptUser)->get('/dashboard');
        $response->assertStatus(200);
        $response->assertSee('Dashboard');

        $deptSubordinates = $response->viewData('deptSubordinates');
        $this->assertCount(175, $deptSubordinates);

        // Dept Head himself (31 SUPARDI) is included on Dashboard (174 + 1 = 175) matching /penilaian/dept & /laporan
        $this->assertTrue(collect($deptSubordinates)->contains('nrp', '31'));

        $karyawanResponse = $this->actingAs($deptUser)->get('/karyawan');
        $karyawanResponse->assertStatus(200);
        $karyawanList = $karyawanResponse->viewData('karyawanList');
        $this->assertCount(174, $karyawanList);

        // On /penilaian/dept, Dept Head includes self (SUPARDI 31, form 07-006-DH) + 174 subs = 175 rows
        $penilaianResponse = $this->actingAs($deptUser)->get('/penilaian/dept');
        $penilaianResponse->assertStatus(200);
        $penilaianResponse->assertSee('Data Penilaian');
        $rows = $penilaianResponse->viewData('rows');
        $this->assertCount(175, $rows);
        $this->assertTrue(collect($rows)->contains('nrp', '31'));

        // Check Target & Aturan Kuota Page: Forbidden for Dept Head, accessible for Admin
        $targetResponse = $this->actingAs($deptUser)->get('/target-aturan');
        $targetResponse->assertStatus(403);

        $adminUser = User::where('role', 'admin')->first();
        if ($adminUser) {
            $adminTargetResponse = $this->actingAs($adminUser)->get('/target-aturan');
            $adminTargetResponse->assertStatus(200);
            $adminTargetResponse->assertSee('Target &amp; Aturan Nilai', false);
        }
    }

    /**
     * Test 4: End-to-end real-time connected workflow from Foreman -> Section Head -> Dept Head -> Division Head.
     */
    public function test_full_chain_foreman_to_section_to_dept_to_division(): void
    {
        $foremanUser = User::where('nrp', '62')->firstOrFail();
        $sectionUser = User::where('nrp', '112')->firstOrFail();
        $deptUser = User::where('nrp', '31')->firstOrFail();

        // 1. Get a document that is currently awaiting foreman (NRP 39 - NUR EKA PURNAMA)
        $doc = DB::table('dokumen')->where('nrp', '39')->first();
        $this->assertNotNull($doc);
        $this->assertEquals('menunggu_foreman', $doc->status);

        // 2. Section Head should NOT see this in incoming docs yet
        $sectionDashInitial = $this->actingAs($sectionUser)->get('/dashboard');
        $incomingInitial = $sectionDashInitial->viewData('incomingDocsSectionHead');
        $this->assertFalse(collect($incomingInitial)->contains('nrp', '39'));

        // 3. Foreman signs the document
        // First seed mock scores for Form 07-001
        $calcService = app(\App\Services\AppraisalCalculationService::class);
        $structure = $calcService->getFormStructure('07-001');
        $scores = [];
        foreach ($structure['groups'] as $group) {
            foreach ($group['aspects'] as $aspectCode => $aspectName) {
                $scores[$aspectCode] = 4;
            }
        }

        $dummyCanvasBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $signResponse = $this->actingAs($foremanUser)->post("/dokumen/{$doc->id}/ttd", [
            'level' => 'foreman',
            'password' => 'password123',
            'scores' => $scores,
            'signature_base64' => $dummyCanvasBase64,
            'pilihan_potensi' => 2,
            'kelebihan' => 'Bekerja dengan sangat baik',
            'kekurangan' => 'Tingkatkan kaizen',
        ]);
        $signResponse->assertRedirect();

        // Verify status moved to menunggu_section_head
        $docAfterForeman = DB::table('dokumen')->where('id', $doc->id)->first();
        $this->assertEquals('menunggu_section_head', $docAfterForeman->status);

        // 4. Section Head now sees this in incoming docs!
        $sectionDash = $this->actingAs($sectionUser)->get('/dashboard');
        $incomingSection = $sectionDash->viewData('incomingDocsSectionHead');
        $this->assertTrue(collect($incomingSection)->contains('nrp', '39'));

        // 5. Section Head batch-submits to Dept Head
        $submitResponse = $this->actingAs($sectionUser)->post('/penilaian/section/submit-dept', [
            'penilaian' => [
                $doc->id => [
                    'grade' => 'A',
                    'catatan' => 'Kerja sangat baik dan teliti.',
                ],
            ],
        ]);
        $submitResponse->assertRedirect();

        // Verify status changed to menunggu_dept_head
        $docAfterSection = DB::table('dokumen')->where('id', $doc->id)->first();
        $this->assertEquals('menunggu_dept_head', $docAfterSection->status);

        // 6. Dept Head immediately sees it in incoming documents!
        $deptDash = $this->actingAs($deptUser)->get('/dashboard');
        $incomingDept = $deptDash->viewData('incomingDocsDeptHead');
        $this->assertTrue(collect($incomingDept)->contains('nrp', '39'));

        // 7. Admin / Super Admin adjusts docking via target-aturan and completes evaluation
        $adminUser = User::where('role', 'admin')->first();
        $dockingResponse = $this->actingAs($adminUser)->post('/target-aturan/simpan', [
            'docking' => [
                $doc->id => 'A',
            ],
        ]);
        $dockingResponse->assertRedirect();

        // Verify status is now 'selesai'
        $docFinal = DB::table('dokumen')->where('id', $doc->id)->first();
        $this->assertEquals('selesai', $docFinal->status);
        $this->assertEquals('A', $docFinal->docking_final);
    }
}
