<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PenilaianFormDropdownFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh --seed');
    }

    /**
     * Test Department Head sees only subordinates + self (5 employees), excludes Division Head.
     * Options format: NRP - Nama (no 'NRP' prefix, no '(07-006...)' suffix).
     */
    public function test_dept_head_dropdown_excludes_division_head_and_formats_cleanly(): void
    {
        $deptHead = User::where('nrp', '31')->first();
        $this->assertNotNull($deptHead);

        $docSh = DB::table('dokumen')->where('nrp', '99999')->first();
        $this->assertNotNull($docSh);

        $docDiv = DB::table('dokumen')->where('nrp', '11')->first();
        $this->assertNotNull($docDiv);

        // Open Section Head FACTORY 3 doc via depthead route
        $response = $this->actingAs($deptHead)->get("/penilaian/depthead/dokumen/{$docSh->id}");
        $response->assertStatus(200);

        // Check options in dropdown (format NRP Nama without dash)
        $response->assertSee('99999 AL QADRI');
        $response->assertSee('99998 TES 1');
        $response->assertSee('66 SLAMET');
        $response->assertSee('31 SUPARDI');

        // Division Head must NOT appear in the dropdown
        $response->assertDontSee('11 ATASAN');
        $response->assertDontSee('NRP 11');

        // Check format: should not have old pattern
        $response->assertDontSee('NRP 99999 • AL QADRI (07-006-SH)');
        $response->assertDontSee('NRP 66 • SLAMET');

        // Attempting to access Division Head doc directly should be forbidden (403)
        $unauthorizedDocResponse = $this->actingAs($deptHead)->get("/dokumen/{$docDiv->id}/penilaian");
        $unauthorizedDocResponse->assertStatus(403);

        // Attempting to jump to Division Head via byNrp should redirect with error
        $jumpResponse = $this->actingAs($deptHead)->get('/penilaian/nrp/11');
        $jumpResponse->assertRedirect(route('dashboard'));
        $jumpResponse->assertSessionHas('error');
    }

    /**
     * Test Section Head without Foreman (99999 AL QADRI) sees only FACTORY 3 direct subordinates + self, excludes Foremen, Dept Head and Division Head.
     */
    public function test_section_head_dropdown_excludes_dept_head_and_division_head(): void
    {
        $sectionHead = User::where('nrp', '99999')->first();
        $this->assertNotNull($sectionHead);

        $docSh = DB::table('dokumen')->where('nrp', '99999')->first();
        $this->assertNotNull($docSh);

        $docDh = DB::table('dokumen')->where('nrp', '31')->first();
        $this->assertNotNull($docDh);

        $docDiv = DB::table('dokumen')->where('nrp', '11')->first();
        $this->assertNotNull($docDiv);

        $response = $this->actingAs($sectionHead)->get("/penilaian/sechead/dokumen/{$docSh->id}");
        $response->assertStatus(200);

        // Should see self + direct FACTORY 3 subs (excludes Factory 1/2 Foremen)
        $response->assertSee('99999 AL QADRI');
        $response->assertSee('99998 TES 1');
        $response->assertSee('99997 TES 2');
        $response->assertDontSee('62 SARYANTA');

        // Dept Head and Div Head must NOT appear
        $response->assertDontSee('31 SUPARDI');
        $response->assertDontSee('11 ATASAN');

        // Accessing Dept Head or Div Head doc should be 403
        $this->actingAs($sectionHead)->get("/dokumen/{$docDh->id}/penilaian")->assertStatus(403);
        $this->actingAs($sectionHead)->get("/dokumen/{$docDiv->id}/penilaian")->assertStatus(403);

        // Jumping by NRP should redirect with error
        $jumpDh = $this->actingAs($sectionHead)->get('/penilaian/nrp/31');
        $jumpDh->assertRedirect(route('dashboard'));
        $jumpDh->assertSessionHas('error');
    }

    /**
     * Test Division Head sees self and ready division subordinates formatted cleanly.
     */
    public function test_division_head_dropdown_includes_all_division_employees(): void
    {
        $divHead = User::where('nrp', '11')->first();
        $this->assertNotNull($divHead);

        // Advance ready subordinates under 11 ATASAN to menunggu_div_head so they appear in Div Head dropdown
        DB::table('dokumen')->whereIn('nrp', ['31', '99999', '99998', '39'])->update([
            'status' => 'menunggu_div_head',
        ]);

        $docDiv = DB::table('dokumen')->where('nrp', '11')->first();
        $this->assertNotNull($docDiv);

        $response = $this->actingAs($divHead)->get("/penilaian/divhead/dokumen/{$docDiv->id}");
        $response->assertStatus(200);

        // Should see self and ready division subordinates formatted cleanly (NRP Nama without dash)
        $response->assertSee('11 ATASAN');
        $response->assertSee('31 SUPARDI');
        $response->assertSee('99999 AL QADRI');
        $response->assertSee('99998 TES 1');
        $response->assertSee('39 NUR EKA PURNAMA');
    }
}
