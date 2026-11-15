<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DivisionHeadSubordinateRestrictionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Cache::flush();
        $this->artisan('migrate:fresh --seed');
        \Illuminate\Support\Facades\Cache::flush();
    }

    public function test_division_head_self_evaluation_is_always_accessible_and_editable(): void
    {
        $divHead = User::where('email', 'div.e@musashi.co.id')->first() ?? User::where('email', 'div.prod@musashi.co.id')->first();
        $this->assertNotNull($divHead);

        $selfDoc = DB::table('dokumen')->where('nrp', $divHead->nrp)->first();
        $this->assertNotNull($selfDoc);

        $response = $this->actingAs($divHead)->get("/penilaian/divhead/dokumen/{$selfDoc->id}");
        $response->assertStatus(200);

        // Self document must be in accessibleDocs
        $accessibleDocs = $response->viewData('accessibleDocs');
        $this->assertNotEmpty($accessibleDocs);
        $accessibleNrps = array_column($accessibleDocs, 'nrp');
        $this->assertContains($divHead->nrp, $accessibleNrps);

        // Must be editable
        $this->assertTrue($response->viewData('canEditDeptHead'));
        $this->assertTrue($response->viewData('canSignForeman'));
        $this->assertNull($response->viewData('waitingForLevel'));
        $response->assertDontSee('Form Penilaian Terkunci: Masih Menunggu Penilaian');
    }

    public function test_unready_subordinates_do_not_appear_in_dropdown_and_are_locked_for_division_head(): void
    {
        $divHead = User::where('email', 'div.e@musashi.co.id')->first() ?? User::where('email', 'div.prod@musashi.co.id')->first();
        $this->assertNotNull($divHead);

        // 1. Subordinate still at Section Head stage under this Division Head
        $subK = DB::table('karyawan')
            ->where('nrp_div_head', $divHead->nrp)
            ->where('nrp', '!=', $divHead->nrp)
            ->first();
        $this->assertNotNull($subK);

        DB::table('dokumen')->where('nrp', $subK->nrp)->update([
            'status' => 'menunggu_section_head',
        ]);
        $shSubDoc = DB::table('dokumen')->where('nrp', $subK->nrp)->first();
        $this->assertNotNull($shSubDoc);

        // Visit self doc first and verify unready subordinate is NOT in accessibleDocs
        $selfDoc = DB::table('dokumen')->where('nrp', $divHead->nrp)->first();
        $resSelf = $this->actingAs($divHead)->get("/penilaian/divhead/dokumen/{$selfDoc->id}");
        $resSelf->assertStatus(200);
        $accessibleNrps = array_column($resSelf->viewData('accessibleDocs'), 'nrp');
        $this->assertNotContains($shSubDoc->nrp, $accessibleNrps, 'Subordinate with status menunggu_section_head must NOT appear in CARI NRP dropdown for Div Head');

        // Direct visit to the unready subordinate document
        $resSub = $this->actingAs($divHead)->get("/penilaian/divhead/dokumen/{$shSubDoc->id}");
        $resSub->assertStatus(200);
        $this->assertFalse($resSub->viewData('canEditDeptHead'), 'canEditDeptHead must be false for unready subordinate');
        $this->assertFalse($resSub->viewData('canSignDeptHead'), 'canSignDeptHead must be false for unready subordinate');
        $this->assertEquals('section_head', $resSub->viewData('waitingForLevel'));
        $resSub->assertSee('Form Penilaian Terkunci: Masih Menunggu Penilaian Section Head');

        // Attempting to sign must be rejected
        $postSign = $this->actingAs($divHead)->post("/dokumen/{$shSubDoc->id}/ttd", [
            'level' => 'dept_head',
            'scores' => [],
            'signature_data' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
        ]);
        $postSign->assertRedirect();
        $postSign->assertSessionHas('error');

        // 2. Subordinate at Dept Head stage in same department
        DB::table('dokumen')->where('nrp', $subK->nrp)->update([
            'status' => 'menunggu_dept_head',
        ]);
        $dhSubDoc = DB::table('dokumen')->where('nrp', $subK->nrp)->first();
        if ($dhSubDoc) {
            $this->assertNotContains($dhSubDoc->nrp, $accessibleNrps, 'Subordinate with status menunggu_dept_head must NOT appear in CARI NRP dropdown for Div Head');
            $resDhSub = $this->actingAs($divHead)->get("/penilaian/divhead/dokumen/{$dhSubDoc->id}");
            $resDhSub->assertStatus(200);
            $this->assertFalse($resDhSub->viewData('canEditDeptHead'));
            $this->assertEquals('dept_head', $resDhSub->viewData('waitingForLevel'));
            $resDhSub->assertSee('Form Penilaian Terkunci: Masih Menunggu Penilaian Department Head');
        }
    }

    public function test_subordinate_reaching_menunggu_div_head_is_accessible_and_editable(): void
    {
        $divHead = User::where('email', 'div.e@musashi.co.id')->first() ?? User::where('email', 'div.prod@musashi.co.id')->first();
        $this->assertNotNull($divHead);

        // Find subordinate document under this Division Head
        $subordinateK = DB::table('karyawan')
            ->where('nrp_div_head', $divHead->nrp)
            ->where('nrp', '!=', $divHead->nrp)
            ->first();
        $this->assertNotNull($subordinateK);

        $doc = DB::table('dokumen')->where('nrp', $subordinateK->nrp)->first();
        DB::table('dokumen')->where('id', $doc->id)->update([
            'status' => 'menunggu_div_head',
            'docking_final' => null,
        ]);

        $res = $this->actingAs($divHead)->get("/penilaian/divhead/dokumen/{$doc->id}");
        $res->assertStatus(200);

        // Must be in accessibleDocs
        $accessibleNrps = array_column($res->viewData('accessibleDocs'), 'nrp');
        $this->assertContains($subordinateK->nrp, $accessibleNrps);

        // Must be editable
        $this->assertTrue($res->viewData('canEditDeptHead'));
        $this->assertTrue($res->viewData('canSignDeptHead'));
        $this->assertNull($res->viewData('waitingForLevel'));
        $res->assertDontSee('Form Penilaian Terkunci: Masih Menunggu Penilaian');
    }
}
