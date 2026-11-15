<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PenilaianRoleRoutesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh --seed');
    }

    public function test_section_head_clean_route_and_access_control(): void
    {
        $sectionHead = User::where('nrp', '99999')->first();
        $this->assertNotNull($sectionHead);

        $docSh = DB::table('dokumen')->where('nrp', '99999')->first();
        $this->assertNotNull($docSh);

        // 1. Section Head can access /penilaian/sechead/dokumen/{id}
        $response = $this->actingAs($sectionHead)->get("/penilaian/sechead/dokumen/{$docSh->id}");
        $response->assertStatus(200);
        $response->assertSee('99999 AL QADRI');

        // 2. Section Head CANNOT access depthead or divhead routes (403)
        $this->actingAs($sectionHead)->get("/penilaian/depthead/dokumen/{$docSh->id}")->assertStatus(403);
        $this->actingAs($sectionHead)->get("/penilaian/divhead/dokumen/{$docSh->id}")->assertStatus(403);

        // 3. Generic route /dokumen/{id}/penilaian redirects to /penilaian/sechead/dokumen/{id}
        $redirectRes = $this->actingAs($sectionHead)->get("/dokumen/{$docSh->id}/penilaian");
        $redirectRes->assertRedirect(url("/penilaian/sechead/dokumen/{$docSh->id}"));

        // 4. Old reversed route /dokumen/sechead/penilaian/{id} redirects for backward compatibility
        $oldRes = $this->actingAs($sectionHead)->get("/dokumen/sechead/penilaian/{$docSh->id}");
        $oldRes->assertRedirect(url("/penilaian/sechead/dokumen/{$docSh->id}"));
    }

    public function test_dept_head_clean_route_and_access_control(): void
    {
        $deptHead = User::where('nrp', '31')->first();
        $this->assertNotNull($deptHead);

        $docDh = DB::table('dokumen')->where('nrp', '31')->first();
        $this->assertNotNull($docDh);

        // 1. Dept Head can access /penilaian/depthead/dokumen/{id}
        $response = $this->actingAs($deptHead)->get("/penilaian/depthead/dokumen/{$docDh->id}");
        $response->assertStatus(200);
        $response->assertSee('31 SUPARDI');

        // 2. Dept Head CANNOT access sechead or divhead routes (403)
        $this->actingAs($deptHead)->get("/penilaian/sechead/dokumen/{$docDh->id}")->assertStatus(403);
        $this->actingAs($deptHead)->get("/penilaian/divhead/dokumen/{$docDh->id}")->assertStatus(403);

        // 3. Generic route /dokumen/{id}/penilaian redirects to /penilaian/depthead/dokumen/{id}
        $redirectRes = $this->actingAs($deptHead)->get("/dokumen/{$docDh->id}/penilaian");
        $redirectRes->assertRedirect(url("/penilaian/depthead/dokumen/{$docDh->id}"));

        // 4. Old reversed route /dokumen/depthead/penilaian/{id} redirects for backward compatibility
        $oldRes = $this->actingAs($deptHead)->get("/dokumen/depthead/penilaian/{$docDh->id}");
        $oldRes->assertRedirect(url("/penilaian/depthead/dokumen/{$docDh->id}"));
    }

    public function test_division_head_clean_route_and_access_control(): void
    {
        $divHead = User::where('nrp', '11')->first();
        $this->assertNotNull($divHead);

        $docDiv = DB::table('dokumen')->where('nrp', '11')->first();
        $this->assertNotNull($docDiv);

        // 1. Division Head can access /penilaian/divhead/dokumen/{id}
        $response = $this->actingAs($divHead)->get("/penilaian/divhead/dokumen/{$docDiv->id}");
        $response->assertStatus(200);
        $response->assertSee('11 ATASAN');

        // 2. Division Head CANNOT access sechead or depthead routes (403)
        $this->actingAs($divHead)->get("/penilaian/sechead/dokumen/{$docDiv->id}")->assertStatus(403);
        $this->actingAs($divHead)->get("/penilaian/depthead/dokumen/{$docDiv->id}")->assertStatus(403);

        // 3. Generic route /dokumen/{id}/penilaian redirects to /penilaian/divhead/dokumen/{id}
        $redirectRes = $this->actingAs($divHead)->get("/dokumen/{$docDiv->id}/penilaian");
        $redirectRes->assertRedirect(url("/penilaian/divhead/dokumen/{$docDiv->id}"));

        // 4. Old reversed route /dokumen/divhead/penilaian/{id} redirects for backward compatibility
        $oldRes = $this->actingAs($divHead)->get("/dokumen/divhead/penilaian/{$docDiv->id}");
        $oldRes->assertRedirect(url("/penilaian/divhead/dokumen/{$docDiv->id}"));
    }

    public function test_foreman_clean_route_and_access_control(): void
    {
        $foreman = User::where('nrp', '62')->first();
        $this->assertNotNull($foreman);

        $docF = DB::table('dokumen')->where('nrp', '62')->first();
        $this->assertNotNull($docF);

        // 1. Foreman can access /penilaian/foreman/dokumen/{id}
        $response = $this->actingAs($foreman)->get("/penilaian/foreman/dokumen/{$docF->id}");
        $response->assertStatus(200);

        // 2. Foreman CANNOT access sechead, depthead, or divhead routes (403)
        $this->actingAs($foreman)->get("/penilaian/sechead/dokumen/{$docF->id}")->assertStatus(403);
        $this->actingAs($foreman)->get("/penilaian/depthead/dokumen/{$docF->id}")->assertStatus(403);
        $this->actingAs($foreman)->get("/penilaian/divhead/dokumen/{$docF->id}")->assertStatus(403);

        // 3. Generic route /dokumen/{id}/penilaian redirects to /penilaian/foreman/dokumen/{id}
        $redirectRes = $this->actingAs($foreman)->get("/dokumen/{$docF->id}/penilaian");
        $redirectRes->assertRedirect(url("/penilaian/foreman/dokumen/{$docF->id}"));

        // 4. Old reversed route /dokumen/foreman/penilaian/{id} redirects for backward compatibility
        $oldRes = $this->actingAs($foreman)->get("/dokumen/foreman/penilaian/{$docF->id}");
        $oldRes->assertRedirect(url("/penilaian/foreman/dokumen/{$docF->id}"));
    }
}

