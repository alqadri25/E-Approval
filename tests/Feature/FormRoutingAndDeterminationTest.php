<?php

namespace Tests\Feature;

use App\Contracts\SheetRepositoryInterface;
use App\Http\Controllers\PenilaianController;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FormRoutingAndDeterminationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh --seed');
    }

    /**
     * Test unit-level logic of determineKodeForm and determineLevelForm.
     */
    public function test_determine_kode_form_and_level_form(): void
    {
        // 1. KJ 1 - 3 Non-Jabatan -> Form 07-001 (KJ13)
        $k1 = [
            'kj' => 'KJ 2',
            'sub_gol' => 'IIB',
            'jabatan' => 'OPERATOR MACHINING',
            'nrp_foreman' => 'F-11416',
        ];
        $this->assertEquals('07-001', PenilaianController::determineKodeForm($k1));
        $this->assertEquals('KJ13', PenilaianController::determineLevelForm($k1));

        $k2 = [
            'kj' => 'KJ 1',
            'sub_gol' => 'IIA',
            'jabatan' => 'PELAKSANA PRODUKSI',
            'nrp_foreman' => 'F-11416',
        ];
        $this->assertEquals('07-001', PenilaianController::determineKodeForm($k2));
        $this->assertEquals('KJ13', PenilaianController::determineLevelForm($k2));

        $k3 = [
            'kj' => 'KJ 3',
            'sub_gol' => 'IIIA',
            'jabatan' => 'OPERATOR SENIOR',
            'nrp_foreman' => 'F-11416',
        ];
        $this->assertEquals('07-001', PenilaianController::determineKodeForm($k3));
        $this->assertEquals('KJ13', PenilaianController::determineLevelForm($k3));

        // 2. KJ 4 - 5 Non-Jabatan -> Form 07-002 (KJ45)
        $k4NonJab = [
            'kj' => 'KJ 4',
            'sub_gol' => 'IVA',
            'jabatan' => 'STAFF MACHINING',
            'nrp_foreman' => 'F-11416',
        ];
        $this->assertEquals('07-002', PenilaianController::determineKodeForm($k4NonJab));
        $this->assertEquals('KJ45', PenilaianController::determineLevelForm($k4NonJab));

        $k5NonJab = [
            'kj' => 'KJ 5',
            'sub_gol' => 'VA',
            'jabatan' => 'SENIOR SPECIALIST',
            'nrp_foreman' => 'F-11416',
        ];
        $this->assertEquals('07-002', PenilaianController::determineKodeForm($k5NonJab));
        $this->assertEquals('KJ45', PenilaianController::determineLevelForm($k5NonJab));

        // 3. KJ 4 - 5 with LEADER -> Form 07-003 (LEADER)
        $k4Leader = [
            'kj' => 'KJ 4',
            'sub_gol' => 'IVA',
            'jabatan' => 'LEADER MACHINING',
            'nrp_foreman' => 'F-11416',
        ];
        $this->assertEquals('07-003', PenilaianController::determineKodeForm($k4Leader));
        $this->assertEquals('LEADER', PenilaianController::determineLevelForm($k4Leader));

        // KJ 3 with LEADER -> Form 07-003 (LEADER)
        $k3Leader = [
            'kj' => 'KJ 3',
            'sub_gol' => 'IIIA',
            'jabatan' => 'LEADER FINISHING',
            'nrp_foreman' => 'F-11416',
        ];
        $this->assertEquals('07-003', PenilaianController::determineKodeForm($k3Leader));
        $this->assertEquals('LEADER', PenilaianController::determineLevelForm($k3Leader));

        // 4. KJ 4 - 5 with FOREMAN -> Form 07-004 (FOREMAN)
        $k4Foreman = [
            'kj' => 'KJ 4',
            'sub_gol' => 'IVB',
            'jabatan' => 'SUB-FOREMAN MACHINING',
            'nrp_foreman' => 'F-11416',
        ];
        $this->assertEquals('07-004', PenilaianController::determineKodeForm($k4Foreman));
        $this->assertEquals('FOREMAN', PenilaianController::determineLevelForm($k4Foreman));

        $k5Foreman = [
            'kj' => 'KJ 5',
            'sub_gol' => 'VA',
            'jabatan' => 'FOREMAN ASSEMBLY',
            'nrp_foreman' => 'F-11416',
        ];
        $this->assertEquals('07-004', PenilaianController::determineKodeForm($k5Foreman));
        $this->assertEquals('FOREMAN', PenilaianController::determineLevelForm($k5Foreman));
    }

    /**
     * Test Foreman Karyawan page renders correct form codes and badges.
     */
    public function test_foreman_karyawan_page_renders_form_badges_and_titles(): void
    {
        $foreman = User::where('email', 'saryanta@musashi.co.id')->first();
        $this->assertNotNull($foreman);

        $response = $this->actingAs($foreman)->get('/karyawan');
        $response->assertStatus(200);

        // Subordinates check for 62 (SARYANTA, F1 CAT 1):
        // 39 (NUR EKA PURNAMA): KJ 3, Staff -> Form 07-001
        $response->assertSee('39');
        $response->assertSee('KJ 3');
        $response->assertSee('07-001');

        // 24 (HADI PRIYONO): KJ 4, Staff (Non Jabatan) -> Form 07-002
        $response->assertSee('24');
        $response->assertSee('KJ 4');
        $response->assertSee('07-002');

        // 110 (LAKSONO FITRIYANTO): KJ 4, Leader -> Form 07-003
        $response->assertSee('110');
        $response->assertSee('07-003');

        // 62 (SARYANTA): KJ 6, Foreman -> Form 07-004
        $response->assertSee('62');
        $response->assertSee('07-004');
    }

    /**
     * Test adding employee assigns accurate level_form and form routing.
     */
    public function test_foreman_add_employee_with_kj_determines_correct_form(): void
    {
        $admin = User::where('email', 'admin@musashi.co.id')->first();
        $this->assertNotNull($admin);

        // 1. Add KJ 4 Non-Jabatan
        $response1 = $this->actingAs($admin)->post('/karyawan', [
            'nrp' => '99901',
            'nama_lengkap' => 'KARYAWAN NON JABATAN KJ 4',
            'jabatan' => 'STAFF LOGISTIK',
            'kj' => 'KJ 4',
            'sub_gol' => 'IVA',
            'golongan' => 'GOLONGAN IV',
            'tgl_masuk' => '2022-01-01',
            'departemen' => 'PROD 2',
            'nama_sect' => 'FACTORY 1',
        ]);
        $response1->assertRedirect('/karyawan');

        $repo = app(SheetRepositoryInterface::class);
        $emp1 = $repo->find('karyawan', 'nrp', '99901');
        $this->assertNotNull($emp1);
        $this->assertEquals('KJ45', $emp1['level_form']);
        $this->assertEquals('07-002', PenilaianController::determineKodeForm($emp1));

        // 2. Add KJ 4 with Leader
        $response2 = $this->actingAs($admin)->post('/karyawan', [
            'nrp' => '99902',
            'nama_lengkap' => 'KARYAWAN LEADER KJ 4',
            'jabatan' => 'LEADER LOGISTIK',
            'kj' => 'KJ 4',
            'sub_gol' => 'IVA',
            'golongan' => 'GOLONGAN IV',
            'tgl_masuk' => '2022-01-01',
            'departemen' => 'PROD 2',
            'nama_sect' => 'FACTORY 1',
        ]);
        $response2->assertRedirect('/karyawan');

        $emp2 = $repo->find('karyawan', 'nrp', '99902');
        $this->assertNotNull($emp2);
        $this->assertEquals('LEADER', $emp2['level_form']);
        $this->assertEquals('07-003', PenilaianController::determineKodeForm($emp2));

        // 3. Add KJ 5 with Foreman
        $response3 = $this->actingAs($admin)->post('/karyawan', [
            'nrp' => '99903',
            'nama_lengkap' => 'KARYAWAN FOREMAN KJ 5',
            'jabatan' => 'FOREMAN PACKING',
            'kj' => 'KJ 5',
            'sub_gol' => 'VA',
            'golongan' => 'GOLONGAN IV',
            'tgl_masuk' => '2022-01-01',
            'departemen' => 'PROD 2',
            'nama_sect' => 'FACTORY 1',
        ]);
        $response3->assertRedirect('/karyawan');

        $emp3 = $repo->find('karyawan', 'nrp', '99903');
        $this->assertNotNull($emp3);
        $this->assertEquals('FOREMAN', $emp3['level_form']);
        $this->assertEquals('07-004', PenilaianController::determineKodeForm($emp3));

        // 4. Add KJ 2 Operator
        $response4 = $this->actingAs($admin)->post('/karyawan', [
            'nrp' => '99904',
            'nama_lengkap' => 'KARYAWAN OPERATOR KJ 2',
            'jabatan' => 'OPERATOR PACKING',
            'kj' => 'KJ 2',
            'sub_gol' => 'IIB',
            'golongan' => 'GOLONGAN II',
            'tgl_masuk' => '2022-01-01',
            'departemen' => 'PROD 2',
            'nama_sect' => 'FACTORY 1',
        ]);
        $response4->assertRedirect('/karyawan');

        $emp4 = $repo->find('karyawan', 'nrp', '99904');
        $this->assertNotNull($emp4);
        $this->assertEquals('KJ13', $emp4['level_form']);
        $this->assertEquals('07-001', PenilaianController::determineKodeForm($emp4));
    }

    /**
     * Test updating employee syncs level_form and unfinalized document form code.
     */
    public function test_updating_employee_syncs_form_code_and_level_form(): void
    {
        $admin = User::where('email', 'admin@musashi.co.id')->first();
        $foreman = User::where('email', 'saryanta@musashi.co.id')->first();
        $this->assertNotNull($admin);
        $this->assertNotNull($foreman);

        $repo = app(SheetRepositoryInterface::class);

        // Update 39 (NUR EKA PURNAMA, was KJ 3 Staff 07-001) to KJ 4 Staff (07-002)
        $response = $this->actingAs($admin)->post('/karyawan/39/update', [
            'nama_lengkap' => 'NUR EKA PURNAMA PROMOTED',
            'jabatan' => 'STAFF',
            'kj' => 'KJ 4',
            'sub_gol' => 'IVA',
            'golongan' => 'GOLONGAN IV',
            'departemen' => 'PROD 2',
        ]);
        $response->assertSessionHas('success');

        $emp = $repo->find('karyawan', 'nrp', '39');
        $this->assertEquals('KJ45', $emp['level_form']);
        $this->assertEquals('07-002', PenilaianController::determineKodeForm($emp));

        // Opening by NRP routes to Form 07-002
        $openResponse = $this->actingAs($foreman)->get('/penilaian/nrp/39');
        $openResponse->assertRedirect();
        
        $doc = DB::table('dokumen')->where('nrp', '39')->first();
        $this->assertEquals('07-002', $doc->kode_form);
    }

    /**
     * Test pop-up toast notification appears exactly once (no double) with 4s auto-dismiss.
     */
    public function test_popup_toast_appears_single_with_auto_dismiss(): void
    {
        $admin = User::where('email', 'admin@musashi.co.id')->first();
        $this->assertNotNull($admin);

        $response = $this->actingAs($admin)->followingRedirects()->post('/karyawan', [
            'nrp' => '99905',
            'nama_lengkap' => 'KARYAWAN TOAST TEST',
            'jabatan' => 'OPERATOR TEST',
            'kj' => 'KJ 2',
            'sub_gol' => 'IIB',
            'golongan' => 'GOLONGAN II',
            'tgl_masuk' => '2022-01-01',
            'departemen' => 'PROD 2',
            'nama_sect' => 'FACTORY 1',
        ]);

        $response->assertStatus(200);

        // Toast contains success message
        $response->assertSee('Karyawan KARYAWAN TOAST TEST (NRP: 99905) berhasil ditambahkan!');

        // Must appear EXACTLY once (never double)
        $content = $response->getContent();
        $occurrences = substr_count($content, 'Karyawan KARYAWAN TOAST TEST (NRP: 99905) berhasil ditambahkan!');
        $this->assertEquals(1, $occurrences, 'Notification must appear exactly once, not duplicated.');

        // Must have auto-dismiss timer (4000ms / 4 seconds) and transition effects
        $response->assertSee('duration = 4000', false);
        $response->assertSee('x-transition:leave', false);
    }
}
