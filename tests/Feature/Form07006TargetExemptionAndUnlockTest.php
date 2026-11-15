<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AppraisalCalculationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Form07006TargetExemptionAndUnlockTest extends TestCase
{
    private const DUMMY_PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->artisan('migrate:fresh --seed');
        Cache::flush();
    }

    private function buildUniformScores(string $kodeForm, int $scoreVal): array
    {
        $calcService = app(AppraisalCalculationService::class);
        $struct = $calcService->getFormStructure($kodeForm);
        $scores = [];
        foreach ($struct['groups'] as $g) {
            foreach ($g['aspects'] as $aCode => $aName) {
                $scores[$aCode] = $scoreVal;
            }
        }
        return $scores;
    }

    public function test_admin_target_kuota_and_summary_pk_exclude_form_07_006(): void
    {
        $admin = User::where('email', 'admin@musashi.co.id')->firstOrFail();

        $rawKaryawanCount = DB::table('karyawan')->count();
        $form07006Count = DB::table('dokumen')
            ->where('kode_form', 'like', '07-006%')
            ->distinct('nrp')
            ->count('nrp');
        $this->assertGreaterThan(0, $form07006Count);

        $resTarget = $this->actingAs($admin)->get('/admin/target-kuota');
        $resTarget->assertStatus(200);
        $deptTargetsMap = $resTarget->viewData('deptTargetsMap');
        $this->assertLessThan($rawKaryawanCount, $deptTargetsMap['all']['total']);

        // Give 99999 AL QADRI (07-006-SH, KJ 2) and DH-11300 (07-006-DH, KJ 5) Grade C and verify Summary PK excludes ALL 07-006 forms
        DB::table('dokumen')->whereIn('nrp', ['99999', 'DH-11300'])->update([
            'status' => 'selesai',
            'total_nilai' => 2.20,
            'grade_original' => 'C',
            'docking_final' => 'C',
        ]);

        $resSummary = $this->actingAs($admin)->get('/summary-pk');
        $resSummary->assertStatus(200);
        $actualCountByKj = $resSummary->viewData('actualCountByKj');
        $matrixOriginal = $resSummary->viewData('matrixOriginal');
        $matrixDocking = $resSummary->viewData('matrixDocking');

        // Operational KJ 5 (3 KJ 5 + 3 KJ 6 Foremen merged into KJ 5 quota = 6); DH-11300 (07-006-DH) Grade C MUST stay excluded (count stays 6, C=0)
        // Operational KJ 2 also excludes 99999 (07-006-SH, KJ 2) so Grade C stays 0
        $this->assertSame(6, (int)($actualCountByKj['KJ 5'] ?? 0));
        $this->assertSame(0, (int)($matrixOriginal['C']['KJ 5'] ?? 0));
        $this->assertSame(0, (int)($matrixDocking['C']['KJ 5'] ?? 0));
        $this->assertSame(0, (int)($matrixOriginal['C']['KJ 2'] ?? 0));
        $this->assertSame(0, (int)($matrixDocking['C']['KJ 2'] ?? 0));
    }

    public function test_section_head_07_006_self_eval_excluded_from_target_and_actual_matrices(): void
    {
        $shF3 = User::where('nrp', '99999')->firstOrFail();

        // Set zero C quota for KJ 2 in PROD 2 / DIV-MFG
        DB::table('pengaturan')->updateOrInsert(
            ['key' => 'target_kuota_kj_DIV-MFG_KJ2'],
            ['value' => json_encode(['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0, 'total' => 0]), 'updated_at' => now()]
        );

        // Self-evaluation by 99999 AL QADRI (07-006-SH, KJ 2) with Rank C must remain OPEN and succeed
        $selfDoc = DB::table('dokumen')->where('nrp', '99999')->first();
        $this->assertNotNull($selfDoc);
        $this->assertEquals('07-006-SH', $selfDoc->kode_form);

        $resForm = $this->actingAs($shF3)->get("/penilaian/sechead/dokumen/{$selfDoc->id}");
        $resForm->assertStatus(200);
        $this->assertTrue($resForm->viewData('isQuotaExempt'));
        $resForm->assertSee('Form 07-006');
        $resForm->assertSee('Bebas Target Kuota');

        $postSelf = $this->actingAs($shF3)->post("/dokumen/{$selfDoc->id}/ttd", [
            'level' => 'foreman',
            'role_title' => 'Section Head (Mandiri)',
            'password' => 'password123',
            'scores' => $this->buildUniformScores('07-006-SH', 2),
            'signature_base64' => self::DUMMY_PNG,
        ]);
        $postSelf->assertSessionMissing('error');
        $postSelf->assertSessionHas('success');

        // Dashboard: 4 total karyawan (99998..99996 + self), but only 3 bound to Target Kuota
        $resDash = $this->actingAs($shF3)->get('/dashboard');
        $resDash->assertStatus(200);
        $this->assertCount(4, $resDash->viewData('sectionSubordinates'));
        $this->assertEquals(3, array_sum($resDash->viewData('sectionTargetCounts')));
        $this->assertEquals(0, array_sum($resDash->viewData('sectionGradeCounts')));

        // Riwayat: evaluatedList shows 99999 workflow row, while quota-bound actuals/matrix stay 0
        $resRiw = $this->actingAs($shF3)->get('/penilaian/riwayat');
        $resRiw->assertStatus(200);
        $this->assertEquals(4, $resRiw->viewData('totalSubordinates'));
        $this->assertEquals(3, array_sum($resRiw->viewData('targetCounts')));
        $this->assertSame(['KJ 2'], array_keys($resRiw->viewData('targetKjMatrix')));
        $this->assertSame(['KJ 2'], array_keys($resRiw->viewData('actualKjMatrix')));
        $this->assertEquals(1, $resRiw->viewData('actualCounts')['C']);
        $this->assertEquals(0, $resRiw->viewData('quotaActualCounts')['C']);

        // Laporan: targetKuota = 3, quotaActualCounts = 0
        $resLap = $this->actingAs($shF3)->get('/laporan');
        $resLap->assertStatus(200);
        $this->assertEquals(3, array_sum($resLap->viewData('targetKuota')));
        $this->assertEquals(0, array_sum($resLap->viewData('quotaActualCounts')));
    }

    public function test_dept_head_dashboard_displays_updated_cards_and_allows_unconstrained_07_006(): void
    {
        $dhProd2 = User::where('nrp', '31')->firstOrFail();
        $dhProd1 = User::where('nrp', 'DH-11300')->firstOrFail();

        // Both dept heads show updated card titles, 4 pipeline stages, clean KJ tables, and no Priority Action Box
        foreach ([$dhProd2, $dhProd1] as $dh) {
            $res = $this->actingAs($dh)->get('/dashboard');
            $res->assertStatus(200);
            $res->assertSee('Dinilai Foreman');
            $res->assertSee('Diteruskan Ke Sect Head');
            $res->assertSee('Nilai Foreman');
            $res->assertSee('Dinilai Section Head');
            $res->assertSee('Diteruskan ke Dept Head');
            $res->assertSee('Nilai SH');
            $res->assertSee('1. Penilaian Foreman');
            $res->assertSee('Proses pengerjaan Foreman');
            $res->assertSee('Alur For-SH-DH');
            $res->assertSee('2. Penilaian Section Head');
            $res->assertSee('Proses pengerjaan SH');
            $res->assertSee('Alur SH-DH');
            $res->assertSee('3. Approval Department Head');
            $res->assertSee('4. Approval Division Head');
            $res->assertSee('Selesai Final');
            $res->assertSee('Tuntas');
            $res->assertSee('Target Penilaian');
            $res->assertSee('Hasil Penilaian Department Head');
            $res->assertDontSee('Prioritas Tindakan Department Head');
        }

        // Dept Head PROD 2 target sum & KJ matrix exclude ALL 07-006 forms (Section Heads incl. KJ 2/7 & DEPT HEADs)
        $expectedSubs = DB::table('karyawan')->get()
            ->map(fn ($r) => (array)$r)
            ->filter(fn ($k) => $dhProd2->matchesDeptHeadScope($k));
        $expectedTotalSubs = $expectedSubs->count();
        $expectedQuotaBoundSubs = $expectedSubs->filter(fn ($k) => !\App\Http\Controllers\PenilaianController::isQuotaExemptEmployee($k))->count();

        $resDh = $this->actingAs($dhProd2)->get('/dashboard');
        $this->assertEquals($expectedTotalSubs + 1, $resDh->viewData('deptTotalKaryawan'));
        $this->assertEquals($expectedQuotaBoundSubs, array_sum($resDh->viewData('deptTargetCounts')));
        $this->assertEquals($expectedQuotaBoundSubs, $resDh->viewData('deptTargetKjSummary')['total']);
        $this->assertArrayNotHasKey('KJ 7', $resDh->viewData('deptTargetKjMatrix'));
        $this->assertArrayNotHasKey('KJ 9', $resDh->viewData('deptTargetKjMatrix'));

        // Dept Head PROD 2 evaluates Section Head 112 (07-006-SH) with Rank C even when PROD 2 Rank C quota = 0
        DB::table('pengaturan')->updateOrInsert(
            ['key' => 'target_kuota_dept_PROD 2'],
            ['value' => json_encode(['S' => 1, 'A' => 1, 'B' => 1, 'C' => 0, 'K' => 0, 'total' => 3]), 'updated_at' => now()]
        );

        $shProd2Doc = DB::table('dokumen')->where('nrp', '112')->first();
        $this->assertNotNull($shProd2Doc);
        $this->assertEquals('07-006-SH', $shProd2Doc->kode_form);
        DB::table('dokumen')->where('id', $shProd2Doc->id)->update(['status' => 'menunggu_dept_head']);
        Cache::flush();

        $beforeProd2Actual = $this->actingAs($dhProd2)->get('/dashboard')->viewData('deptActualKjSummary')['total'];

        $resForm = $this->actingAs($dhProd2)->get("/penilaian/depthead/dokumen/{$shProd2Doc->id}");
        $resForm->assertStatus(200);
        $this->assertTrue($resForm->viewData('isQuotaExempt'));

        $postSign = $this->actingAs($dhProd2)->post("/dokumen/{$shProd2Doc->id}/ttd", [
            'level' => 'section_head',
            'role_title' => 'Department Head (Penilai 2)',
            'password' => 'password123',
            'scores' => $this->buildUniformScores('07-006-SH', 2),
            'docking_final' => 'C',
            'signature_base64' => self::DUMMY_PNG,
        ]);
        $this->assertNull(session('error'), 'Unexpected sign error: ' . (string)session('error'));
        $postSign->assertSessionHas('success');

        $updatedShDoc = DB::table('dokumen')->where('id', $shProd2Doc->id)->first();
        $this->assertEquals('menunggu_div_head', $updatedShDoc->status);

        // 07-006-SH signed by Dept Head MUST remain excluded from Dept Head quota Actual KJ table
        Cache::flush();
        $resAfterProd2 = $this->actingAs($dhProd2)->get('/dashboard');
        $resAfterProd2->assertStatus(200);
        $afterProd2Actual = $resAfterProd2->viewData('deptActualKjSummary')['total'];
        $this->assertEquals($beforeProd2Actual, $afterProd2Actual);
    }

    public function test_division_head_07_006_never_locked_and_finalizes_even_with_zero_quota(): void
    {
        $divHead = User::where('nrp', '11')->firstOrFail();

        // Set EVERY Division PROD 2 (DIV-MFG) & KJ 2/7/9 quota slot to 0
        $zeroQuota = json_encode(['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'K' => 0, 'total' => 0]);
        foreach (['target_kuota_div_DIV-MFG', 'target_kuota_kj_DIV-MFG_KJ2', 'target_kuota_kj_DIV-MFG_KJ7', 'target_kuota_kj_DIV-MFG_KJ9'] as $key) {
            DB::table('pengaturan')->updateOrInsert(['key' => $key], ['value' => $zeroQuota, 'updated_at' => now()]);
        }

        // Prepare 99999 (07-006-SH), 31 (07-006-DH), 11 (07-006-DIV) with Grade C
        foreach (['99999', '31', '11'] as $nrp) {
            DB::table('dokumen')->where('nrp', $nrp)->update([
                'status' => 'menunggu_div_head',
                'total_nilai' => 2.20,
                'grade_original' => 'C',
                'docking_final' => null,
            ]);
        }
        Cache::flush();

        // 1. Cari page keeps all 07-006 forms UNLOCKED and flagged is_quota_exempt
        $resCari = $this->actingAs($divHead)->get('/penilaian/cari');
        $resCari->assertStatus(200);
        $subsCari = collect($resCari->viewData('subordinates'))->keyBy('nrp');
        foreach (['99999', '31', '11'] as $nrp) {
            $this->assertTrue((bool)$subsCari[$nrp]['is_quota_exempt'], "{$nrp} must be is_quota_exempt");
            $this->assertFalse((bool)$subsCari[$nrp]['is_kj_locked'], "{$nrp} must never be locked");
        }

        // 2. API lookup & POST /penilaian/buka never lock or redirect to penyesuaian
        foreach (['99999', '31', '11'] as $nrp) {
            $doc = DB::table('dokumen')->where('nrp', $nrp)->first();

            $resApi = $this->actingAs($divHead)->getJson('/api/karyawan-lookup?nrp=' . $nrp);
            $resApi->assertStatus(200);
            $resApi->assertJson(['found' => true]);
            $resApi->assertJsonPath('karyawan.is_kj_locked', false);

            $resBuka = $this->actingAs($divHead)->post('/penilaian/buka', ['nrp' => $nrp]);
            $resBuka->assertStatus(302);
            $resBuka->assertRedirect("/penilaian/divhead/dokumen/{$doc->id}");
            $resBuka->assertSessionMissing('error');

            // 3. Opening form shows isQuotaExempt = true & Bebas Target Kuota banner
            $resForm = $this->actingAs($divHead)->get("/penilaian/divhead/dokumen/{$doc->id}");
            $resForm->assertStatus(200);
            $this->assertTrue($resForm->viewData('isQuotaExempt'));
            $resForm->assertSee('Bebas Target Kuota');

            // 4. Finalizing (TTD) with Rank C succeeds despite 0 quota!
            $level = ($nrp === '11') ? 'foreman' : 'dept_head';
            $roleTitle = ($nrp === '11') ? 'Division Head (Mandiri)' : 'Division Head (Penilai Final)';
            $postSign = $this->actingAs($divHead)->post("/dokumen/{$doc->id}/ttd", [
                'level' => $level,
                'role_title' => $roleTitle,
                'password' => 'password123',
                'scores' => $this->buildUniformScores($doc->kode_form, 2),
                'docking_final' => 'C',
                'signature_base64' => self::DUMMY_PNG,
            ]);
            $postSign->assertSessionMissing('error');
            $postSign->assertSessionHas('success');

            $finalDoc = DB::table('dokumen')->where('id', $doc->id)->first();
            $this->assertEquals('selesai', $finalDoc->status);
        }

        // 5. Division Head Penyesuaian page is disabled (403); Dept Head Penyesuaian marks 07-006 rows as quota-exempt & non-eligible
        $this->actingAs($divHead)->get('/penilaian/div/penyesuaian')->assertStatus(403);
        $dhProd2 = User::where('nrp', '31')->firstOrFail();
        $resPenyesuaian = $this->actingAs($dhProd2)->get('/penilaian/dept/penyesuaian');
        $resPenyesuaian->assertStatus(200);
        $penRows = collect($resPenyesuaian->viewData('rows'))->keyBy('nrp');
        if ($penRows->has('99999')) {
            $this->assertTrue((bool)$penRows['99999']['is_quota_exempt']);
            $this->assertFalse((bool)$penRows['99999']['is_eligible']);
        }
    }

    public function test_only_non_07_006_forms_bound_to_target_including_leader_and_foreman_kj6_while_kj2_07_006_exempt(): void
    {
        // 1. Verify Form-based target eligibility on actual seeded data
        // 99999 AL QADRI (SECTION HEAD in KJ 2 -> Form 07-006-SH) MUST be exempt
        $shKj2 = DB::table('karyawan')->where('nrp', '99999')->first();
        if ($shKj2) {
            $this->assertTrue(\App\Http\Controllers\PenilaianController::isQuotaExemptEmployee((array)$shKj2));
            $this->assertEquals('07-006-SH', \App\Http\Controllers\PenilaianController::determineKodeForm((array)$shKj2));
        }
        // Leader (07-003), Foreman KJ 5 (07-004), Foreman KJ 6 (07-004), Staff KJ 3 (07-001), Staff KJ 4 (07-002) MUST be quota-bound
        foreach (['40', '349', '51', '39', '24'] as $boundNrp) {
            $emp = DB::table('karyawan')->where('nrp', $boundNrp)->first();
            $this->assertNotNull($emp, "Seeded quota-bound NRP {$boundNrp} missing");
            $this->assertFalse(\App\Http\Controllers\PenilaianController::isQuotaExemptEmployee((array)$emp), "{$boundNrp} must be bound to target");
        }

        // 2. Summary PK includes KJ 6 operational Foremen (07-004) in target & matrix, excluding 07-006 even at KJ 2
        $dhProd2 = User::where('email', 'supardi@musashi.co.id')->firstOrFail();
        $resSummary = $this->actingAs($dhProd2)->get('/summary-pk?departemen=PROD+2');
        $resSummary->assertStatus(200);
        $kjListSummary = $resSummary->viewData('kjList');
        $actualByKj = $resSummary->viewData('actualCountByKj');
        $this->assertNotContains('KJ 6', $kjListSummary);
        $this->assertEquals(6, $actualByKj['KJ 5'] ?? 0);

        $expectedProd2Bound = DB::table('karyawan')->where('departemen', 'PROD 2')->get()
            ->map(fn ($r) => (array)$r)
            ->filter(fn ($k) => !\App\Http\Controllers\PenilaianController::isQuotaExemptEmployee($k))
            ->count();
        $this->assertEquals($expectedProd2Bound, array_sum($actualByKj));

        // 3. Admin Mode 1 save on DIV-E is NOT overwritten by GET /admin/target-kuota
        $admin = User::where('email', 'admin@musashi.co.id')->firstOrFail();
        $this->actingAs($admin)->post('/admin/target-kuota', [
            'scope' => 'DIV-E',
            'quota_s' => 1, 'quota_a' => 2, 'quota_b' => 3, 'quota_c' => 1, 'quota_k' => 0,
        ])->assertRedirect();
        $this->actingAs($admin)->get('/admin/target-kuota')->assertStatus(200);
        $divESetting = json_decode(DB::table('pengaturan')->where('key', 'target_kuota_div_DIV-E')->value('value'), true);
        $this->assertEquals(7, $divESetting['total']);
    }
}

