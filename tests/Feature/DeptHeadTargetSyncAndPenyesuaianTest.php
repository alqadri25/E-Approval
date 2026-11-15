<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AppraisalCalculationService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeptHeadTargetSyncAndPenyesuaianTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Cache::flush();
        $this->artisan('migrate:fresh --seed');
        \Illuminate\Support\Facades\Cache::flush();
    }

    private function dummySignature(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
    }

    private function buildScoresForForm(string $kodeForm, int $scoreValue): array
    {
        $calcService = app(AppraisalCalculationService::class);
        $struct = $calcService->getFormStructure($kodeForm);
        $scores = [];
        foreach ($struct['groups'] as $g) {
            foreach ($g['aspects'] as $aCode => $aName) {
                $scores[$aCode] = $scoreValue;
            }
        }
        return $scores;
    }

    public function test_dept_head_sidebar_renders_penyesuaian_penilaian_without_conflict(): void
    {
        $deptProd2 = User::where('email', 'supardi@musashi.co.id')->first();
        $this->assertNotNull($deptProd2);

        $resDash = $this->actingAs($deptProd2)->get('/dashboard');
        $resDash->assertStatus(200);
        $resDash->assertSee(route('penilaian.dept.penyesuaian'));
        $resDash->assertSee('Penyesuaian Penilaian');
        $resDash->assertSee('Data Penilaian');

        $resPenyesuaian = $this->actingAs($deptProd2)->get('/penilaian/dept/penyesuaian');
        $resPenyesuaian->assertStatus(200);
        $resPenyesuaian->assertSee('Penyesuaian Penilaian (Docking Kuota) - Department Head');
    }

    public function test_admin_and_superadmin_target_changes_sync_with_dept_head_dashboard_and_penyesuaian(): void
    {
        $admin = User::where('email', 'admin@musashi.co.id')->first();
        $superadmin = User::where('email', 'superadmin@musashi.co.id')->first();
        $deptProd2 = User::where('email', 'supardi@musashi.co.id')->first();
        $deptProd1 = User::where('email', 'dept.prod1@musashi.co.id')->first();

        $this->assertNotNull($admin);
        $this->assertNotNull($superadmin);
        $this->assertNotNull($deptProd2);
        $this->assertNotNull($deptProd1);

        // 1. Admin changes global percentages (scope = all)
        $resGlobal = $this->actingAs($admin)->post('/admin/target-kuota', [
            'scope' => 'all',
            'quota_s' => 15,
            'quota_a' => 35,
            'quota_b' => 30,
            'quota_c' => 15,
            'quota_k' => 5,
        ]);
        $resGlobal->assertRedirect();
        $resGlobal->assertSessionHas('success');

        $expectedQuotaBound = DB::table('karyawan')->get()
            ->map(fn ($r) => (array)$r)
            ->filter(fn ($k) => $deptProd2->matchesDeptHeadScope($k) && !\App\Http\Controllers\PenilaianController::isQuotaExemptEmployee($k))
            ->count();
        $resDeptGlobalDash = $this->actingAs($deptProd2)->get('/dashboard');
        $resDeptGlobalDash->assertStatus(200);
        $globalTargetSummary = $resDeptGlobalDash->viewData('deptTargetKjSummary');
        $this->assertEquals($expectedQuotaBound, $globalTargetSummary['total']);
        $this->assertEquals((int)round($expectedQuotaBound * 0.15), $globalTargetSummary['S']);

        $resDeptGlobalPenyesuaian = $this->actingAs($deptProd2)->get('/penilaian/dept/penyesuaian');
        $resDeptGlobalPenyesuaian->assertStatus(200);
        $this->assertEquals($globalTargetSummary['S'], $resDeptGlobalPenyesuaian->viewData('targetQuota')['S']);
        $this->assertEquals($globalTargetSummary['total'], $resDeptGlobalPenyesuaian->viewData('targetQuota')['total']);

        // 2. Superadmin updates PROD 2 target quota explicitly -> syncs to 31 SUPARDI Dashboard & Penyesuaian
        $resSuperProd2 = $this->actingAs($superadmin)->post('/superadmin/target-kuota', [
            'scope' => 'PROD 2',
            'quota_s' => 15,
            'quota_a' => 50,
            'quota_b' => 55,
            'quota_c' => 36,
            'quota_k' => 15,
        ]);
        $resSuperProd2->assertRedirect();
        $resSuperProd2->assertSessionHas('success');

        $resDeptDash = $this->actingAs($deptProd2)->get('/dashboard');
        $resDeptDash->assertStatus(200);
        $summaryProd2Full = $resDeptDash->viewData('deptTargetKjSummary');
        $this->assertEquals(['S' => 15, 'A' => 50, 'B' => 55, 'C' => 36, 'K' => 15, 'total' => 171], [
            'S' => $summaryProd2Full['S'],
            'A' => $summaryProd2Full['A'],
            'B' => $summaryProd2Full['B'],
            'C' => $summaryProd2Full['C'],
            'K' => $summaryProd2Full['K'],
            'total' => $summaryProd2Full['total'],
        ]);

        $resDeptPenyesuaian = $this->actingAs($deptProd2)->get('/penilaian/dept/penyesuaian');
        $resDeptPenyesuaian->assertStatus(200);
        $this->assertEquals([
            'S' => 15,
            'A' => 50,
            'B' => 55,
            'C' => 36,
            'K' => 15,
            'total' => 171,
        ], $resDeptPenyesuaian->viewData('targetQuota'));

        // 3. Admin updates PROD 2 target quota explicitly -> syncs immediately
        $resProd2 = $this->actingAs($admin)->post('/admin/target-kuota', [
            'scope' => 'PROD 2',
            'quota_s' => 1,
            'quota_a' => 1,
            'quota_b' => 1,
            'quota_c' => 1,
            'quota_k' => 0,
        ]);
        $resProd2->assertRedirect();
        $resProd2->assertSessionHas('success');

        $resProd2Dash = $this->actingAs($deptProd2)->get('/dashboard');
        $resProd2Dash->assertStatus(200);
        $summaryProd2 = $resProd2Dash->viewData('deptTargetKjSummary');
        $this->assertEquals(4, $summaryProd2['total']);
        $this->assertEquals(1, $summaryProd2['S']);
        $this->assertEquals(1, $summaryProd2['A']);
        $this->assertEquals(1, $summaryProd2['B']);
        $this->assertEquals(1, $summaryProd2['C']);
        $this->assertEquals(0, $summaryProd2['K']);

        $resProd2Penyesuaian = $this->actingAs($deptProd2)->get('/penilaian/dept/penyesuaian');
        $resProd2Penyesuaian->assertStatus(200);
        $this->assertEquals([
            'S' => 1,
            'A' => 1,
            'B' => 1,
            'C' => 1,
            'K' => 0,
            'total' => 4,
        ], $resProd2Penyesuaian->viewData('targetQuota'));

        // 4. Admin updates per-KJ DIV-MFG -> reflected on Dept Head PROD 2 dashboard KJ matrix & Penyesuaian KJ cards
        $this->actingAs($admin)->post('/admin/target-kuota/kj', [
            'kj' => 'KJ 3',
            'departemen' => 'DIV-MFG',
            'quota_s' => 5,
            'quota_a' => 25,
            'quota_b' => 30,
            'quota_c' => 10,
            'quota_k' => 2,
        ])->assertRedirect();

        $resAfterKjDash = $this->actingAs($deptProd2)->get('/dashboard');
        $matrix = $resAfterKjDash->viewData('deptTargetKjMatrix');
        $kj3Row = $matrix['KJ 3'] ?? null;
        $this->assertNotNull($kj3Row);
        $this->assertEquals(['S' => 5, 'A' => 25, 'B' => 30, 'C' => 10, 'K' => 2, 'total' => 72], [
            'S' => $kj3Row['S'],
            'A' => $kj3Row['A'],
            'B' => $kj3Row['B'],
            'C' => $kj3Row['C'],
            'K' => $kj3Row['K'],
            'total' => $kj3Row['total'],
        ]);

        $resAfterKjPeny = $this->actingAs($deptProd2)->get('/penilaian/dept/penyesuaian');
        $kjCards = $resAfterKjPeny->viewData('kjSummaryCards');
        $this->assertEquals(5, $kjCards['KJ 3']['target']['S']);
        $this->assertEquals(25, $kjCards['KJ 3']['target']['A']);
        $this->assertEquals(72, $kjCards['KJ 3']['target']['total']);
    }

    public function test_dept_head_penyesuaian_shows_history_comparison_and_adjusts_ready_rows_only(): void
    {
        $foreman = User::where('email', 'saryanta@musashi.co.id')->first();
        $secHead = User::where('email', 'kristiyanto@musashi.co.id')->first();
        $deptProd2 = User::where('email', 'supardi@musashi.co.id')->first();
        $divHead = User::where('email', 'atasan.div@musashi.co.id')->first();

        $this->assertNotNull($foreman);
        $this->assertNotNull($secHead);
        $this->assertNotNull($deptProd2);
        $this->assertNotNull($divHead);

        // Role guards: Foreman, Section Head & Div Head blocked from dept penyesuaian route (403)
        $this->actingAs($foreman)->get('/penilaian/dept/penyesuaian')->assertStatus(403);
        $this->actingAs($secHead)->get('/penilaian/dept/penyesuaian')->assertStatus(403);
        $this->actingAs($divHead)->get('/penilaian/dept/penyesuaian')->assertStatus(403);

        // Seed distinct scores across Foreman (5 -> S), Section Head (4 -> A), Dept Head (3 -> B) on 39
        $docReady = DB::table('dokumen')->where('nrp', '39')->first();
        $this->assertNotNull($docReady);

        DB::table('nilai')->where('dokumen_id', $docReady->id)->delete();
        foreach ($this->buildScoresForForm($docReady->kode_form, 5) as $aspek => $skor) {
            DB::table('nilai')->insert([
                'dokumen_id' => $docReady->id,
                'level' => 'foreman',
                'aspek_kode' => $aspek,
                'skor' => $skor,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        foreach ($this->buildScoresForForm($docReady->kode_form, 4) as $aspek => $skor) {
            DB::table('nilai')->insert([
                'dokumen_id' => $docReady->id,
                'level' => 'section_head',
                'aspek_kode' => $aspek,
                'skor' => $skor,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        foreach ($this->buildScoresForForm($docReady->kode_form, 3) as $aspek => $skor) {
            DB::table('nilai')->insert([
                'dokumen_id' => $docReady->id,
                'level' => 'dept_head',
                'aspek_kode' => $aspek,
                'skor' => $skor,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        DB::table('dokumen')->where('id', $docReady->id)->update([
            'status' => 'menunggu_dept_head',
            'total_nilai' => 3.00,
            'grade_original' => 'B',
            'docking_final' => null,
        ]);

        // Leave another subordinate (24) unready (menunggu_foreman)
        $docUnready = DB::table('dokumen')->where('nrp', '24')->first();
        if ($docUnready) {
            DB::table('dokumen')->where('id', $docUnready->id)->update([
                'status' => 'menunggu_foreman',
                'total_nilai' => null,
                'grade_original' => null,
                'docking_final' => null,
            ]);
        }

        $res = $this->actingAs($deptProd2)->get('/penilaian/dept/penyesuaian');
        $res->assertStatus(200);
        $res->assertDontSee('id="comparisonBarChart"', false);
        $res->assertSee('Tabel Perbandingan Target &amp; Nilai', false);
        $res->assertSee('Target (Kuota)');
        $res->assertSee('Nilai Foreman');
        $res->assertSee('Nilai Section Head');
        $res->assertSee('Nilai Department Head (Anda / Docking)');
        $res->assertSee('NILAI FOREMAN');
        $res->assertSee('NILAI SECTION HEAD');
        $res->assertSee('NILAI DEPT HEAD (ANDA)');
        $res->assertSee('>DEPARTMENT</th>', false);
        $res->assertSee('>SECTION</th>', false);
        $res->assertSee('>POSITION</th>', false);
        $res->assertSee('>AKSI</th>', false);
        $res->assertDontSee('FORM &amp; AKSI', false);
        $res->assertSee('id="penyesuaianFilterStatus"', false);
        $res->assertSee('>Status Kuota</option>', false);
        $res->assertDontSee('>Semua Status Kuota</option>', false);
        $res->assertSee('PK Original');
        $res->assertSee('PK Disesuaikan');
        $res->assertSee('PK Over Kuota');
        $res->assertSee('id="penyesuaianFilterSection"', false);
        $res->assertSee('>Section</option>', false);
        $res->assertDontSee('>Semua Section</option>', false);
        $res->assertSee('id="penyesuaianFilterPosition"', false);
        $res->assertSee('>Position</option>', false);
        $res->assertDontSee('>Semua Position</option>', false);
        $res->assertSee('id="penyesuaianFilterKj"', false);
        $res->assertSee('>KJ / Golongan</option>', false);
        $res->assertDontSee('>Semua KJ / Golongan</option>', false);
        $res->assertSeeInOrder([
            'x-model="searchQuery"',
            'id="penyesuaianFilterStatus"',
            'id="penyesuaianFilterSection"',
            'id="penyesuaianFilterPosition"',
            'id="penyesuaianFilterKj"',
        ], false);

        $rows = collect($res->viewData('rows'));
        $rowReady = $rows->firstWhere('nrp', '39');
        $this->assertNotNull($rowReady);
        $this->assertTrue($rowReady['is_ready']);
        $this->assertEquals('5.00', $rowReady['foreman_score_formatted']);
        $this->assertEquals('S', $rowReady['foreman_grade']);
        $this->assertEquals('4.00', $rowReady['section_score_formatted']);
        $this->assertEquals('A', $rowReady['section_grade']);
        $this->assertEquals('3.00', $rowReady['dept_score_formatted']);
        $this->assertEquals('B', $rowReady['dept_grade']);

        if ($docUnready) {
            $rowUnready = $rows->firstWhere('nrp', '24');
            $this->assertNotNull($rowUnready);
            $this->assertFalse($rowUnready['is_ready']);
            $this->assertEquals('⏳ Menunggu Foreman', $rowUnready['waiting_label']);
        }

        // Save docking adjustment via dept endpoint
        $resSimpan = $this->actingAs($deptProd2)->post(route('penilaian.dept.penyesuaian.simpan'), [
            'docking' => [
                $docReady->id => 'A',
            ],
            'force_save' => 1,
        ]);
        $resSimpan->assertRedirect(route('penilaian.dept.penyesuaian'));
        $resSimpan->assertSessionHas('success');
        $this->assertEquals('A', DB::table('dokumen')->where('id', $docReady->id)->value('docking_final'));

        // Reset via dept endpoint
        $resReset = $this->actingAs($deptProd2)->post(route('penilaian.dept.penyesuaian.reset'));
        $resReset->assertRedirect(route('penilaian.dept.penyesuaian'));
        $this->assertNull(DB::table('dokumen')->where('id', $docReady->id)->value('docking_final'));

        // Configure tight quota (B=0, A=1) and trigger Auto-Adjust -> only ready doc gets adjusted; unready stays null
        DB::table('pengaturan')->updateOrInsert(
            ['key' => 'target_kuota_dept_PROD 2'],
            ['value' => json_encode(['S' => 0, 'A' => 2, 'B' => 0, 'C' => 1, 'K' => 1, 'total' => 4]), 'updated_at' => now()]
        );
        $resAuto = $this->actingAs($deptProd2)->post(route('penilaian.dept.penyesuaian.autoAdjust'));
        $resAuto->assertRedirect(route('penilaian.dept.penyesuaian'));
        $resAuto->assertSessionHas('success');
        $this->assertEquals('A', DB::table('dokumen')->where('id', $docReady->id)->value('docking_final'));
        if ($docUnready) {
            $this->assertNull(DB::table('dokumen')->where('id', $docUnready->id)->value('docking_final'));
        }
    }

    public function test_dept_head_enforces_quota_lock_on_operational_kj_and_keeps_07_006_exempt(): void
    {
        $deptProd2 = User::where('email', 'supardi@musashi.co.id')->first();
        $this->assertNotNull($deptProd2);

        // Configure KJ 3 (operational) and KJ 2 (99999 07-006-SH vs operational) & KJ 7 (112/280 07-006-SH) with B = 0, A = 2
        DB::table('pengaturan')->updateOrInsert(
            ['key' => 'target_kuota_kj_DIV-MFG_KJ3'],
            ['value' => json_encode(['S' => 1, 'A' => 2, 'B' => 0, 'C' => 0, 'K' => 0, 'total' => 3]), 'updated_at' => now()]
        );
        DB::table('pengaturan')->updateOrInsert(
            ['key' => 'target_kuota_kj_DIV-MFG_KJ7'],
            ['value' => json_encode(['S' => 1, 'A' => 2, 'B' => 0, 'C' => 0, 'K' => 0, 'total' => 3]), 'updated_at' => now()]
        );

        $opSub = DB::table('karyawan')
            ->join('dokumen', 'dokumen.nrp', '=', 'karyawan.nrp')
            ->where('karyawan.nrp_dept_head', '31')
            ->where('karyawan.kj', 'KJ 3')
            ->where('dokumen.kode_form', 'not like', '07-006%')
            ->select('karyawan.nrp', 'dokumen.id as doc_id', 'dokumen.kode_form')
            ->first();
        $this->assertNotNull($opSub);

        $shDoc = DB::table('dokumen')->where('nrp', '112')->first();
        $this->assertNotNull($shDoc);

        DB::table('dokumen')->where('id', $opSub->doc_id)->update([
            'status' => 'menunggu_dept_head',
            'total_nilai' => 3.00,
            'grade_original' => 'B',
            'docking_final' => null,
        ]);

        DB::table('dokumen')->where('id', $shDoc->id)->update([
            'status' => 'menunggu_dept_head',
            'total_nilai' => 3.00,
            'grade_original' => 'B',
            'docking_final' => null,
        ]);

        // 1. /penilaian/cari locks operational KJ 3 pointing to Dept Head Penyesuaian, while 07-006-SH (KJ 7) stays unlocked
        $resCari = $this->actingAs($deptProd2)->get('/penilaian/cari');
        $resCari->assertStatus(200);
        $resCari->assertSee('Kuota KJ 3 Penuh / Over');
        $resCari->assertSee('🔒 Buka Form (Kuota KJ 3 Penuh)');
        $resCari->assertSee(route('penilaian.dept.penyesuaian'));
        $resCari->assertDontSee('Kuota KJ 7 Penuh / Over');

        // 2. /api/karyawan-lookup marks operational KJ 3 as is_kj_locked=true and keeps 07-006-SH unlocked
        $resLookupBlocked = $this->actingAs($deptProd2)->getJson('/api/karyawan-lookup?nrp=' . urlencode($opSub->nrp));
        $resLookupBlocked->assertStatus(200);
        $resLookupBlocked->assertJsonPath('found', true);
        $resLookupBlocked->assertJsonPath('karyawan.is_kj_locked', true);

        $resLookupExempt = $this->actingAs($deptProd2)->getJson('/api/karyawan-lookup?nrp=112');
        $resLookupExempt->assertStatus(200);
        $resLookupExempt->assertJsonPath('found', true);
        $resLookupExempt->assertJsonPath('karyawan.is_kj_locked', false);

        // 3. POST /penilaian/buka redirects over-quota KJ 3 to dept penyesuaian; opens 07-006-SH directly
        $resBukaBlocked = $this->actingAs($deptProd2)->post('/penilaian/buka', ['nrp' => $opSub->nrp]);
        $resBukaBlocked->assertRedirect('/penilaian/dept/penyesuaian?filter_kj=KJ%203');
        $resBukaBlocked->assertSessionHas('error');

        $resBukaExempt = $this->actingAs($deptProd2)->post('/penilaian/buka', ['nrp' => '112']);
        $resBukaExempt->assertRedirect("/penilaian/depthead/dokumen/{$shDoc->id}");

        // 4. Form view renders Department Quota Calibration Panel and rejects over-quota TTD on KJ 3
        $resFormOp = $this->actingAs($deptProd2)->get("/penilaian/depthead/dokumen/{$opSub->doc_id}");
        $resFormOp->assertStatus(200);
        $resFormOp->assertSee('Penyesuaian Kuota Departemen');
        $resFormOp->assertSee(route('penilaian.dept.penyesuaian'));
        $resFormOp->assertSee('Sesuaikan ke Rank Tersedia');

        $bScores = $this->buildScoresForForm($opSub->kode_form, 3);
        $resSignOver = $this->actingAs($deptProd2)->post("/dokumen/{$opSub->doc_id}/ttd", [
            'level' => 'dept_head',
            'role_title' => 'Department Head',
            'password' => 'password123',
            'scores' => $bScores,
            'docking_final' => 'B',
            'signature_base64' => $this->dummySignature(),
        ]);
        $resSignOver->assertSessionHas('error');
        $this->assertStringContainsString('Kuota untuk Rank B (KJ 3) sudah penuh/melebihi target Admin', session('error'));
        $this->assertEquals('menunggu_dept_head', DB::table('dokumen')->where('id', $opSub->doc_id)->value('status'));

        // Adjusting via /penilaian/dept/penyesuaian/simpan to available rank A unlocks & allows final sign
        $this->actingAs($deptProd2)->post(route('penilaian.dept.penyesuaian.simpan'), [
            'docking' => [$opSub->doc_id => 'A'],
        ])->assertRedirect(route('penilaian.dept.penyesuaian'));

        $aScores = $this->buildScoresForForm($opSub->kode_form, 4);
        $resSignOk = $this->actingAs($deptProd2)->post("/dokumen/{$opSub->doc_id}/ttd", [
            'level' => 'dept_head',
            'role_title' => 'Department Head',
            'password' => 'password123',
            'scores' => $aScores,
            'docking_final' => 'A',
            'signature_base64' => $this->dummySignature(),
        ]);
        $resSignOk->assertSessionHas('success');
        $this->assertEquals('selesai', DB::table('dokumen')->where('id', $opSub->doc_id)->value('status'));

        // 5. Meanwhile Form 07-006-SH (112) signs cleanly to menunggu_div_head with Grade B even when KJ 7 Rank B = 0!
        $shScoresB = $this->buildScoresForForm($shDoc->kode_form, 3);
        $resSign07006 = $this->actingAs($deptProd2)->post("/dokumen/{$shDoc->id}/ttd", [
            'level' => 'section_head',
            'role_title' => 'Department Head',
            'password' => 'password123',
            'scores' => $shScoresB,
            'docking_final' => 'B',
            'signature_base64' => $this->dummySignature(),
        ]);
        $resSign07006->assertSessionHas('success');
        $this->assertEquals('menunggu_div_head', DB::table('dokumen')->where('id', $shDoc->id)->value('status'));
    }

    public function test_dept_head_cari_page_renders_section_position_and_kj_filters(): void
    {
        $deptProd2 = User::where('email', 'supardi@musashi.co.id')->firstOrFail();

        $resProd2 = $this->actingAs($deptProd2)->get('/penilaian/cari');
        $resProd2->assertStatus(200);
        $resProd2->assertSee('id="cariFilterSection"', false);
        $resProd2->assertSee('FACTORY 1');
        $resProd2->assertSee('FACTORY 2');
        $resProd2->assertSee('FACTORY 3');
        $resProd2->assertSee('id="cariFilterJabatan"', false);
        $resProd2->assertSee('id="cariFilterKj"', false);
        $resProd2->assertSee('filterSection');
        $resProd2->assertDontSee('<option value="-">', false);
    }

    public function test_data_pk_excel_full_import_and_department_name_scoping_excluding_dept_head(): void
    {
        // 1. DATA PK.xlsx imported ALL 176 PROD 2 rows (incl. SUPARDI 31 DEPT HEAD & ATASAN 11 DIV HEAD for self-eval/auth scoping)
        $this->assertTrue(DB::table('karyawan')->where('nrp', '31')->exists());
        $this->assertTrue(DB::table('karyawan')->where('nrp', '11')->exists());
        foreach (['62', '349', '628', '51', '487', '533'] as $foremanNrp) {
            $row = DB::table('karyawan')->where('nrp', $foremanNrp)->first();
            $this->assertNotNull($row, "Excel Foreman NRP {$foremanNrp} must be seeded");
            $this->assertEquals('FOREMAN', $row->level_form);
        }
        foreach (['40', '61', '110', '333'] as $leaderNrp) {
            $row = DB::table('karyawan')->where('nrp', $leaderNrp)->first();
            $this->assertNotNull($row, "Excel Leader NRP {$leaderNrp} must be seeded");
            $this->assertEquals('LEADER', $row->level_form);
        }
        foreach (['112', '280', '99999'] as $shNrp) {
            $row = DB::table('karyawan')->where('nrp', $shNrp)->first();
            $this->assertNotNull($row, "Excel Section Head NRP {$shNrp} must be seeded");
            $this->assertEquals('SECTION_HEAD', $row->level_form);
        }

        // 2. Department Head 31 SUPARDI dashboard shows 175 (174 non-DEPT-HEAD/DIV-HEAD PROD 2 subordinates + self 31) matching /penilaian/dept & /laporan
        $expectedProd2Count = DB::table('karyawan')
            ->get()
            ->map(fn ($r) => (array)$r)
            ->filter(fn ($k) => strcasecmp($k['departemen'] ?? '', 'PROD 2') === 0 && !User::isDeptOrDivHeadPosition($k))
            ->count();
        $dh = User::where('email', 'supardi@musashi.co.id')->firstOrFail();
        $dash = $this->actingAs($dh)->get('/dashboard');
        $dash->assertStatus(200);
        $rows = collect($dash->viewData('deptSubordinateRows'));
        $this->assertEquals(174, $expectedProd2Count);
        $this->assertCount($expectedProd2Count + 1, $rows);
        $this->assertTrue($rows->contains('nrp', '62'));   // Factory 1 Foreman
        $this->assertTrue($rows->contains('nrp', '51'));   // Factory 2 Foreman
        $this->assertTrue($rows->contains('nrp', '40'));   // Factory 2 Leader
        $this->assertTrue($rows->contains('nrp', '112'));  // Factory 1 Section Head
        $this->assertTrue($rows->contains('nrp', '280'));  // Factory 2 Section Head
        $this->assertTrue($rows->contains('nrp', '99999')); // Factory 3 Section Head
        $this->assertTrue($rows->contains('nrp', '99998')); // Factory 3 Staff
        $this->assertTrue($rows->contains('nrp', '31'));   // Self DEPT HEAD included on dashboard (175)
        $this->assertFalse($rows->contains('nrp', '11'));   // DIV HEAD excluded

        // Meanwhile /penilaian/dept also includes SUPARDI 31 (self-eval 07-006-DH) + 174 subordinates = 175
        $penDept = $this->actingAs($dh)->get('/penilaian/dept');
        $penDept->assertStatus(200);
        $deptRows = collect($penDept->viewData('rows'));
        $this->assertCount(175, $deptRows);
        $this->assertTrue($deptRows->contains('nrp', '31'));

        // 3. Logging in with PROD 1 Dept Head sees PROD 1 subordinates + self (DH-11300) + 91227 from Excel = 5, excluding PROD 2
        $dhProd1 = User::where('email', 'dept.prod1@musashi.co.id')->firstOrFail();
        $dashP1 = $this->actingAs($dhProd1)->get('/dashboard');
        $dashP1->assertStatus(200);
        $rowsP1 = collect($dashP1->viewData('deptSubordinateRows'));
        $this->assertCount(5, $rowsP1);
        $this->assertTrue($rowsP1->contains('nrp', '2023020'));
        $this->assertTrue($rowsP1->contains('nrp', '2023021'));
        $this->assertTrue($rowsP1->contains('nrp', '91227'));
        $this->assertTrue($rowsP1->contains('nrp', 'SH-11310'));
        $this->assertTrue($rowsP1->contains('nrp', 'DH-11300'));
        $this->assertFalse($rowsP1->contains('nrp', '62'));

        // 4. Importing employees for another department (QA: Operator, Leader, Foreman, Section Head, DEPT HEAD) connects all except DEPT HEAD to QA Dept Head dashboard
        $admin = User::where('email', 'admin@musashi.co.id')->firstOrFail();
        $csvQa = implode("\n", [
            'EMPLOYEENO;FULLNAME;ORGSTRUCTURECODE;ORGSTRUCTURENAME;LINE CATEGORY;POSITION;DIVISION;DEPT;SECTION;JOBCLASSCODE;JOBLEVELCODE;JOINDATE',
            '88001;OP QA SATU;1151000001;QA LAB;QA;OPERATOR;QUALITY DIV;QA;IQC;IIIA;KJ03;2022-01-10',
            '88002;LEADER QA DUA;1151000001;QA LAB;QA;LEADER;QUALITY DIV;QA;IQC;IIIB;KJ04;2021-01-10',
            '88003;FOREMAN QA TIGA;1151000001;QA LAB;QA;FOREMAN;QUALITY DIV;QA;IQC;IVA;KJ05;2019-01-10',
            '88004;SH QA EMPAT;1151000001;QA LAB;QA;SECTION HEAD;QUALITY DIV;QA;IQC;IVB;KJ06;2017-01-10',
            '88005;DH QA LIMA;1151000001;QA LAB;QA;DEPT HEAD;QUALITY DIV;QA;-;VA;KJ08;2014-01-10',
        ]);
        $uploadQa = \Illuminate\Http\UploadedFile::fake()->createWithContent('qa_import.csv', $csvQa);
        $this->actingAs($admin)->from('/admin/karyawan')->post('/karyawan/import', ['sheet_file' => $uploadQa])
            ->assertRedirect(route('admin.karyawan'))
            ->assertSessionHas('success');

        $dhQa = User::firstOrCreate(
            ['email' => 'dept.qa@musashi.co.id'],
            [
                'nama' => 'Suryo Utomo',
                'password_hash' => \Illuminate\Support\Facades\Hash::make('password123'),
                'role' => 'department_head',
                'nrp' => 'DH-11500',
                'departemen' => 'QA',
                'aktif' => 1,
            ]
        );
        $dashQa = $this->actingAs($dhQa)->get('/dashboard');
        $dashQa->assertStatus(200);
        $rowsQa = collect($dashQa->viewData('deptSubordinateRows'));
        $this->assertCount(4, $rowsQa);
        $this->assertTrue($rowsQa->contains('nrp', '88001'));
        $this->assertTrue($rowsQa->contains('nrp', '88002'));
        $this->assertTrue($rowsQa->contains('nrp', '88003'));
        $this->assertTrue($rowsQa->contains('nrp', '88004'));
        $this->assertFalse($rowsQa->contains('nrp', '88005'));
    }

    public function test_prod2_targets_stay_identical_across_all_dept_pages_admin_and_summary_pk(): void
    {
        $admin = User::where('email', 'admin@musashi.co.id')->firstOrFail();
        $supardi = User::where('email', 'supardi@musashi.co.id')->firstOrFail();

        $assertParity = function (array $expectedTotals) use ($admin, $supardi) {
            $pick = fn (array $src) => [
                'S' => (int)($src['S'] ?? $src['s'] ?? 0),
                'A' => (int)($src['A'] ?? $src['a'] ?? 0),
                'B' => (int)($src['B'] ?? $src['b'] ?? 0),
                'C' => (int)($src['C'] ?? $src['c'] ?? 0),
                'K' => (int)($src['K'] ?? $src['k'] ?? 0),
            ];

            $resDash = $this->actingAs($supardi)->get('/dashboard')->assertStatus(200);
            $resRiw = $this->actingAs($supardi)->get('/penilaian/riwayat')->assertStatus(200);
            $resLap = $this->actingAs($supardi)->get('/laporan')->assertStatus(200);
            $resLapAdm = $this->actingAs($admin)->get('/laporan?departemen=PROD+2')->assertStatus(200);
            $resPen = $this->actingAs($supardi)->get('/penilaian/dept/penyesuaian')->assertStatus(200);
            $resSum = $this->actingAs($admin)->get('/summary-pk?departemen=PROD+2')->assertStatus(200);
            $resAdm = $this->actingAs($admin)->get('/admin/target-kuota')->assertStatus(200);

            $dashMatrix = $resDash->viewData('deptTargetKjMatrix');
            $riwMatrix = $resRiw->viewData('targetKjMatrix');
            $lapMatrix = $resLap->viewData('targetKjMatrix');
            $lapAdmMatrix = $resLapAdm->viewData('targetKjMatrix');

            $penCards = [];
            foreach ($resPen->viewData('kjSummaryCards') as $kjKey => $card) {
                $penCards[$kjKey] = $card['target'];
            }
            $sumRows = [];
            foreach ($resSum->viewData('targetKuota') as $kjKey => $rec) {
                $sumRows[$kjKey] = [
                    'S' => (int)$rec->kuota_s,
                    'A' => (int)$rec->kuota_a,
                    'B' => (int)$rec->kuota_b,
                    'C' => (int)$rec->kuota_c,
                    'K' => (int)$rec->kuota_k,
                    'total' => (int)($rec->kuota_s + $rec->kuota_a + $rec->kuota_b + $rec->kuota_c + $rec->kuota_k),
                ];
            }

            $this->assertSame($expectedTotals, $pick($resDash->viewData('deptTargetKjSummary')));
            $this->assertSame($expectedTotals, $pick($resRiw->viewData('targetCounts')));
            $this->assertSame($expectedTotals, $pick($resLap->viewData('targetKuota')));
            $this->assertSame($expectedTotals, $pick($resLapAdm->viewData('targetKuota')));
            $this->assertSame($expectedTotals, $pick($resPen->viewData('targetQuota')));
            $this->assertSame($expectedTotals, $pick($resAdm->viewData('deptTargetsMap')['PROD 2']));

            $this->assertSame($dashMatrix, $riwMatrix);
            $this->assertSame($dashMatrix, $lapMatrix);
            $this->assertSame($dashMatrix, $lapAdmMatrix);
            $this->assertSame($dashMatrix, $penCards);
            $this->assertSame($dashMatrix, $sumRows);
            $this->assertArrayNotHasKey('KJ 6', $dashMatrix);
        };

        // 1. Fresh seeded PROD 2 targets: S=0, A=2, B=42, C=127, K=0 (total 171)
        $assertParity(['S' => 0, 'A' => 2, 'B' => 42, 'C' => 127, 'K' => 0]);

        // 2. Admin saves Mode 1 PROD 2 with custom total (160)
        $this->actingAs($admin)->post('/admin/target-kuota', [
            'scope' => 'PROD 2',
            'quota_s' => 10,
            'quota_a' => 30,
            'quota_b' => 50,
            'quota_c' => 50,
            'quota_k' => 20,
        ])->assertRedirect();
        $assertParity(['S' => 10, 'A' => 30, 'B' => 50, 'C' => 50, 'K' => 20]);

        // 3. Summary PK modal updates PROD 2 quotas -> syncs back to Admin & all Dept Head pages
        $this->actingAs($admin)->postJson('/summary-pk/update-kuota', [
            'departemen' => 'PROD 2',
            'kuota' => [
                'KJ 2' => ['s' => 2, 'a' => 10, 'b' => 20, 'c' => 30, 'k' => 2],
                'KJ 3' => ['s' => 3, 'a' => 15, 'b' => 25, 'c' => 35, 'k' => 4],
                'KJ 4' => ['s' => 1, 'a' => 3,  'b' => 7,  'c' => 9,  'k' => 1],
                'KJ 5' => ['s' => 0, 'a' => 1,  'b' => 1,  'c' => 2,  'k' => 0],
            ],
        ])->assertStatus(200)->assertJson(['success' => true]);
        $assertParity(['S' => 6, 'A' => 29, 'B' => 53, 'C' => 76, 'K' => 7]);
    }

    public function test_dept_head_laporan_displays_master_kj_in_table_and_maps_quota_kj(): void
    {
        $deptProd2 = User::where('email', 'supardi@musashi.co.id')->first();
        $this->assertNotNull($deptProd2);

        // 1. GET /laporan with kj=KJ 6 filter
        $res = $this->actingAs($deptProd2)->get('/laporan?kj=KJ+6');
        $res->assertStatus(200);

        // The table must list employees with their actual master KJ (KJ 6)
        $res->assertSee('PURWANTO');
        $res->assertSee('SARYANTA');
        $res->assertSee('SARWOTO');
        $res->assertSee('KJ 6');

        $employeeRows = $res->viewData('employeeRows');
        $this->assertNotEmpty($employeeRows);
        foreach ($employeeRows as $emp) {
            $this->assertSame('KJ 6', $emp['kj']);
            $this->assertSame('KJ 5', $emp['quota_kj']);
        }

        // Quota target must map KJ 6 to KJ 5 quota bucket
        $deptActualKjMatrix = $res->viewData('deptActualKjMatrix');
        $this->assertArrayHasKey('KJ 5', $deptActualKjMatrix);
        $this->assertArrayNotHasKey('KJ 6', $deptActualKjMatrix);

        // 2. Excel export test
        $resExcel = $this->actingAs($deptProd2)->get('/laporan/export-excel?kj=KJ+6');
        $resExcel->assertStatus(200);
        $resExcel->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_dept_head_viewing_unready_subordinate_does_not_show_quota_toast(): void
    {
        $deptProd2 = User::where('email', 'supardi@musashi.co.id')->firstOrFail();

        // Document with ID 165 or status menunggu_foreman
        $doc = DB::table('dokumen')->where('id', 165)->first() ?? DB::table('dokumen')->where('status', 'menunggu_foreman')->first();
        if (!$doc) {
            $docId = DB::table('dokumen')->insertGetId([
                'periode_id' => 1,
                'nrp' => '1130',
                'kode_form' => '07-001',
                'status' => 'menunggu_foreman',
                'created_at' => now(),
            ]);
            $doc = DB::table('dokumen')->where('id', $docId)->first();
        }
        $this->assertNotNull($doc);

        $res = $this->actingAs($deptProd2)->get("/penilaian/depthead/dokumen/{$doc->id}");
        $res->assertStatus(200);

        // Toast and over quota warning must NOT be rendered
        $res->assertDontSee('⛔ Kuota Rank K Penuh! Sesuaikan Grade!');
        $res->assertDontSee('KUOTA RANK K PENUH / TIDAK TERSEDIA!');
        $res->assertSee('Form Penilaian Terkunci: Masih Menunggu Penilaian Foreman');
    }
}
