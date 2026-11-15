<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LoginAndMultiScenarioTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh --seed');
    }

    /**
     * Test 1: Login page renders with role selector, email & password form, and 16 test accounts
     */
    public function test_login_page_renders_with_role_selector_and_accounts(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('TTD Digital Penilaian Karya');
        $response->assertSee('Pilih Hak Akses / Role');
        $response->assertSee('Email Perusahaan');
        $response->assertSee('Password Akun');
        $response->assertSee('Pusat Akun Uji');
        
        // Assert all role filter buttons exist
        $response->assertSee('Foreman (6)');
        $response->assertSee('Section Head (4)');
        $response->assertSee('Dept Head (2)');
        $response->assertSee('Div Head (2)');
        $response->assertSee('Admin (1)');

        // Assert 3 test case tags exist
        $response->assertSee('Kasus 1: PROD 2');
        $response->assertSee('Kasus 2: PROD 2 FACTORY 3');
        $response->assertSee('Kasus 3: PROD 1');
    }

    /**
     * Test 2: Login with valid credentials and correct role selection
     */
    public function test_login_with_correct_credentials_and_role(): void
    {
        // 1. Foreman login with role = foreman and remember me checked
        $response = $this->post('/login', [
            'email' => 'foreman.prod2@musashi.co.id',
            'password' => 'password123',
            'role' => 'foreman',
            'remember' => '1',
        ]);
        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
        auth()->logout();

        // 2. Section Head login with role = section_head
        $response = $this->post('/login', [
            'email' => 'section.prod2@musashi.co.id',
            'password' => 'password123',
            'role' => 'section_head',
        ]);
        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
        auth()->logout();

        // 3. Dept Head login with role = department_head
        $response = $this->post('/login', [
            'email' => 'dept.prod2@musashi.co.id',
            'password' => 'password123',
            'role' => 'department_head',
        ]);
        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
        auth()->logout();

        // 4. Division Head login with role = division_head
        $response = $this->post('/login', [
            'email' => 'div.prod@musashi.co.id',
            'password' => 'password123',
            'role' => 'division_head',
        ]);
        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
        auth()->logout();
    }

    /**
     * Test 3: Login with email aliases
     */
    public function test_login_with_email_aliases(): void
    {
        // Aliases for Foreman F1 CAT 1 (SARYANTA)
        $response = $this->post('/login', [
            'email' => 'foreman.a1@musashi.co.id',
            'password' => 'password123',
        ]);
        $response->assertRedirect('/dashboard');
        $this->assertEquals('62', auth()->user()->nrp);
        auth()->logout();

        // Alias for Section Head C (Tanpa Foreman PROD 1)
        $response = $this->post('/login', [
            'email' => 'section.c@musashi.co.id',
            'password' => 'password123',
        ]);
        $response->assertRedirect('/dashboard');
        $this->assertEquals('SH-11310', auth()->user()->nrp);
        auth()->logout();

        // Alias for Section Head F2 (MURYADI)
        $response = $this->post('/login', [
            'email' => 'section.b@musashi.co.id',
            'password' => 'password123',
        ]);
        $response->assertRedirect('/dashboard');
        $this->assertEquals('280', auth()->user()->nrp);
        auth()->logout();

        // Alias for Section Head FACTORY 3 Tanpa Foreman (AL QADRI)
        $response = $this->post('/login', [
            'email' => 'al.qadri@musashi.co.id',
            'password' => 'password123',
        ]);
        $response->assertRedirect('/dashboard');
        $this->assertEquals('99999', auth()->user()->nrp);
        auth()->logout();

        // Alias for Dept Head PROD 2 (SUPARDI)
        $response = $this->post('/login', [
            'email' => 'dept.prod2@musashi.co.id',
            'password' => 'password123',
        ]);
        $response->assertRedirect('/dashboard');
        $this->assertEquals('31', auth()->user()->nrp);
        auth()->logout();

        // Alias for Dept Head X / B (PROD 1)
        $response = $this->post('/login', [
            'email' => 'dept.x@musashi.co.id',
            'password' => 'password123',
        ]);
        $response->assertRedirect('/dashboard');
        $this->assertEquals('DH-11300', auth()->user()->nrp);
        auth()->logout();

        // Alias for Dept Head B
        $response = $this->post('/login', [
            'email' => 'dept.b@musashi.co.id',
            'password' => 'password123',
        ]);
        $response->assertRedirect('/dashboard');
        $this->assertEquals('DH-11300', auth()->user()->nrp);
        auth()->logout();

        // Alias for Division Head PROD 2 (ATASAN)
        $response = $this->post('/login', [
            'email' => 'atasan.div@musashi.co.id',
            'password' => 'password123',
        ]);
        $response->assertRedirect('/dashboard');
        $this->assertEquals('11', auth()->user()->nrp);
        auth()->logout();

        // Alias for Division Head PROD 1
        $response = $this->post('/login', [
            'email' => 'div1@musashi.co.id',
            'password' => 'password123',
        ]);
        $response->assertRedirect('/dashboard');
        $this->assertEquals('DIV-11000', auth()->user()->nrp);
        auth()->logout();
    }

    /**
     * Test 4: Role mismatch validation error
     */
    public function test_role_mismatch_gives_informative_validation_error(): void
    {
        // Try logging in with a foreman email but selecting 'department_head'
        $response = $this->post('/login', [
            'email' => 'foreman.prod2@musashi.co.id',
            'password' => 'password123',
            'role' => 'department_head',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertGuest();
    }

    /**
     * Test 5: Kasus 1 Isolation - SARYANTA (62), KRISTIYANTO (112), SUPARDI (31) only manage PROD 2
     */
    public function test_kasus1_manages_only_prod2(): void
    {
        $foremanA = User::where('nrp', '62')->firstOrFail();
        $sectionA = User::where('nrp', '112')->firstOrFail();
        $deptA = User::where('nrp', '31')->firstOrFail();

        $this->assertEquals('PROD 2', $foremanA->departemen);
        $this->assertEquals('PROD 2', $sectionA->departemen);
        $this->assertEquals('PROD 2', $deptA->departemen);

        // Foreman A only has access to PROD 2
        $this->assertTrue($foremanA->canAccessDepartment('PROD 2'));
        $this->assertFalse($foremanA->canAccessDepartment('PROD 1'));

        // Section Head A only has access to PROD 2
        $this->assertTrue($sectionA->canAccessDepartment('PROD 2'));
        $this->assertFalse($sectionA->canAccessDepartment('PROD 1'));

        // Dept Head A only has access to PROD 2
        $this->assertTrue($deptA->canAccessDepartment('PROD 2'));
        $this->assertFalse($deptA->canAccessDepartment('PROD 1'));
    }

    /**
     * Test 6: Kasus 2 - Section Head B evaluates directly without Foreman to Dept Head B
     */
    public function test_kasus2_direct_section_head_b_to_dept_head_b(): void
    {
        $sectionB = User::where('nrp', 'SH-11310')->firstOrFail();
        $deptB = User::where('nrp', 'DH-11300')->firstOrFail();

        // 1. Section Head B accesses data (redirects to /karyawan because SH without foreman uses Data Karyawan)
        $responseRedirect = $this->actingAs($sectionB)->get('/penilaian/section');
        $responseRedirect->assertRedirect(route('karyawan.index'));

        $response = $this->actingAs($sectionB)->get('/karyawan');
        $response->assertStatus(200);

        $karyawans = $response->viewData('karyawanList');
        // Subordinates under SH-11310 in Assembly 1 (Rudi Hermawan 2023020, Siti Aminah 2023021)
        $this->assertTrue(collect($karyawans)->contains('nrp', '2023020'));
        $this->assertTrue(collect($karyawans)->contains('nrp', '2023021'));

        // None from PROD 2 should be here
        $this->assertFalse(collect($karyawans)->contains('nrp', '39'));

        // 2. Section Head B batch submits Rudi Hermawan (2023020) to Dept Head
        $doc = DB::table('dokumen')->where('nrp', '2023020')->first();
        $this->assertNotNull($doc);

        $submitResponse = $this->actingAs($sectionB)->post('/penilaian/section/submit-dept', [
            'penilaian' => [
                $doc->id => [
                    'grade' => 'A',
                    'catatan' => 'Kinerja Assembly Line 1 sangat memuaskan.',
                ],
            ],
        ]);
        $submitResponse->assertRedirect();

        // Verify document advanced to 'menunggu_dept_head'
        $updatedDoc = DB::table('dokumen')->where('id', $doc->id)->first();
        $this->assertEquals('menunggu_dept_head', $updatedDoc->status);
        $this->assertEquals('A', $updatedDoc->grade_original);

        // 3. Dept Head B logs in and sees Rudi Hermawan in Data Penilaian Dept
        $deptResponse = $this->actingAs($deptB)->get('/penilaian/dept');
        $deptResponse->assertStatus(200);

        $deptRows = $deptResponse->viewData('rows');
        $this->assertTrue(collect($deptRows)->contains('nrp', '2023020'));

        // 4. Section Head B opens Form Penilaian: Rudi Hermawan uses KJ 1-5 (07-003 LEADER), while SH self uses 07-006-SH
        $formRes = $this->actingAs($sectionB)->followingRedirects()->get("/dokumen/{$doc->id}/penilaian");
        $formRes->assertStatus(200);
        $formRes->assertSee('Code PDV Mapin - 07-003');
        $formRes->assertSee('LEADER');
        $formRes->assertSee('INDIKATOR EVALUASI POTENSI KARYAWAN', false);

        $selfDoc = DB::table('dokumen')->where('nrp', 'SH-11310')->first();
        $this->assertNotNull($selfDoc);
        $selfFormRes = $this->actingAs($sectionB)->followingRedirects()->get("/dokumen/{$selfDoc->id}/penilaian");
        $selfFormRes->assertStatus(200);
        $selfFormRes->assertSee('Code PDV Mapin - 07-006');
        $selfFormRes->assertSee('SECTION / セクション', false);
    }

    /**
     * Test 7: Foreman F2 CAT 1 -> Section Head F2 -> Dept Head PROD 2 (SUPARDI)
     */
    public function test_kasus3_foreman_c_to_section_c_to_dept_head_b(): void
    {
        $foremanB = User::where('nrp', '51')->firstOrFail();
        $sectionB = User::where('nrp', '280')->firstOrFail();
        $deptAB = User::where('nrp', '31')->firstOrFail();

        // 1. Foreman F2 CAT 1 (51 PURWANTO) sees F2 CAT 1 subordinates in Factory 2 (and excludes F1 CAT 1 39 & PROD 1 2023020)
        $response = $this->actingAs($foremanB)->get('/penilaian/cari');
        $response->assertStatus(200);

        $subs = $response->viewData('subordinates');
        $this->assertTrue(collect($subs)->contains('nrp', '66'));
        $this->assertTrue(collect($subs)->contains('nrp', '288'));
        $this->assertTrue(collect($subs)->contains('nrp', '40'));
        $this->assertTrue(collect($subs)->contains('nrp', '51'));

        // None from F1 CAT 1 (39) or PROD 1 (2023020)
        $this->assertFalse(collect($subs)->contains('nrp', '39'));
        $this->assertFalse(collect($subs)->contains('nrp', '2023020'));

        // 2. Section Head F2 (280 MURYADI) sees Factory 2 subordinates (98 rows = 97 subs + self)
        $secResponse = $this->actingAs($sectionB)->get('/penilaian/section');
        $secResponse->assertStatus(200);

        $secRows = $secResponse->viewData('rows');
        $this->assertCount(98, $secRows);
        $this->assertTrue(collect($secRows)->contains('nrp', '66'));
        $this->assertTrue(collect($secRows)->contains('nrp', '288'));
        $this->assertTrue(collect($secRows)->contains('nrp', '40'));
        $this->assertTrue(collect($secRows)->contains('nrp', '280'));

        // 3. Dept Head PROD 2 (31 SUPARDI) sees Factory 1, Factory 2, Factory 3 + Self (07-006-DH)
        $deptResponse = $this->actingAs($deptAB)->get('/penilaian/dept');
        $deptResponse->assertStatus(200);

        $allRows = $deptResponse->viewData('rows');
        $this->assertTrue(collect($allRows)->contains('nrp', '39'));
        $this->assertTrue(collect($allRows)->contains('nrp', '66'));
        $this->assertTrue(collect($allRows)->contains('nrp', '99998'));
        $this->assertTrue(collect($allRows)->contains('nrp', '31'));
    }

    /**
     * Test 8: Division Head ATASAN (11) accesses PROD 2 while DIV-11000 accesses PROD 1
     */
    public function test_division_head_accesses_both_prod1_and_prod2(): void
    {
        $divProd2 = User::where('nrp', '11')->firstOrFail();
        $this->assertTrue($divProd2->canAccessDepartment('PROD 2'));
        $this->assertFalse($divProd2->canAccessDepartment('PROD 1'));
        $this->assertFalse($divProd2->canAccessDepartment('QA'));

        $divProd1 = User::where('nrp', 'DIV-11000')->firstOrFail();
        $this->assertTrue($divProd1->canAccessDepartment('PROD 1'));
        $this->assertFalse($divProd1->canAccessDepartment('PROD 2'));
        $this->assertFalse($divProd1->canAccessDepartment('QA'));
    }
}
