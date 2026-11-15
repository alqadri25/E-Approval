<?php

namespace Tests\Feature;

use App\Contracts\SheetRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MultiRoleIndikatorCommentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh --seed');
    }

    /**
     * Test full chain: Foreman -> Section Head -> Department Head comments,
     * locked badges, watermarks, and PDF multi-role rendering.
     */
    public function test_multi_role_comment_lifecycle_and_pdf(): void
    {
        $repo = app(SheetRepositoryInterface::class);

        // For employee NRP 39:
        // Foreman = 62 (saryanta@musashi.co.id)
        // Section Head = 112 (kristiyanto@musashi.co.id)
        // Dept Head = 31 (supardi@musashi.co.id)
        $foreman = User::where('nrp', '62')->firstOrFail();
        $sectionHead = User::where('nrp', '112')->firstOrFail();
        $deptHead = User::where('nrp', '31')->firstOrFail();

        $doc = DB::table('dokumen')->where('nrp', '39')->firstOrFail();

        // 1. FOREMAN STAGE:
        // Foreman submits comment
        $foremanRes = $this->actingAs($foreman)->postJson("/dokumen/{$doc->id}/simpan-indikator", [
            'pilihan_potensi' => 1,
            'catatan_potensi' => 'Leader Machining Line 1',
            'kelebihan' => 'Karyawan sangat rajin dan disiplin',
            'kekurangan' => 'Perlu tingkatkan kerapian 5S',
        ]);
        $foremanRes->assertStatus(200);

        // Verify DB for Foreman
        $ind1 = DB::table('indikator')->where('dokumen_id', $doc->id)->first();
        $this->assertNotNull($ind1);
        $this->assertEquals('Karyawan sangat rajin dan disiplin', $ind1->kelebihan_foreman);
        $this->assertEquals('Perlu tingkatkan kerapian 5S', $ind1->kekurangan_foreman);

        // Verify PDF at Foreman stage generates successfully
        $pdfForeman = $this->actingAs($foreman)->get("/dokumen/{$doc->id}/pdf");
        $pdfForeman->assertStatus(200);

        // Advance doc status to Section Head
        DB::table('dokumen')->where('id', $doc->id)->update(['status' => 'menunggu_section_head']);
        $repo->clearCache();

        // 2. SECTION HEAD STAGE:
        // Section Head opens form
        $shFormRes = $this->actingAs($sectionHead)->followingRedirects()->get("/dokumen/{$doc->id}/penilaian");
        $shFormRes->assertStatus(200);
        // Should see locked badge for Foreman
        $shFormRes->assertSee('Dikomentari oleh Foreman');
        $shFormRes->assertSee('Karyawan sangat rajin dan disiplin');
        $shFormRes->assertSee('Terkunci');

        // Section Head enters their own comment
        $shRes = $this->actingAs($sectionHead)->postJson("/dokumen/{$doc->id}/simpan-indikator", [
            'pilihan_potensi' => 1,
            'catatan_potensi' => 'Leader Machining Line 1',
            'kelebihan' => 'Komunikasi antar shift sangat aktif',
            'kekurangan' => 'Pelajari SOP mesin baru',
        ]);
        $shRes->assertStatus(200);

        // Verify DB: Foreman comment is STILL preserved, Section Head comment is saved
        $ind2 = DB::table('indikator')->where('dokumen_id', $doc->id)->first();
        $this->assertEquals('Karyawan sangat rajin dan disiplin', $ind2->kelebihan_foreman, 'Foreman comment must not be overwritten');
        $this->assertEquals('Perlu tingkatkan kerapian 5S', $ind2->kekurangan_foreman, 'Foreman comment must not be overwritten');
        $this->assertEquals('Komunikasi antar shift sangat aktif', $ind2->kelebihan_section);
        $this->assertEquals('Pelajari SOP mesin baru', $ind2->kekurangan_section);

        $history = json_decode($ind2->komentar_history, true);
        $this->assertArrayHasKey('foreman', $history);
        $this->assertArrayHasKey('section_head', $history);
        $this->assertEquals('Karyawan sangat rajin dan disiplin', $history['foreman']['kelebihan']);
        $this->assertEquals('Komunikasi antar shift sangat aktif', $history['section_head']['kelebihan']);

        // Advance doc status to Department Head
        DB::table('dokumen')->where('id', $doc->id)->update(['status' => 'menunggu_dept_head']);
        $repo->clearCache();

        // 3. DEPARTMENT HEAD STAGE:
        // Dept Head opens form
        $dhFormRes = $this->actingAs($deptHead)->followingRedirects()->get("/dokumen/{$doc->id}/penilaian");
        $dhFormRes->assertStatus(200);
        // Dept Head should see BOTH locked badges
        $dhFormRes->assertSee('Dikomentari oleh Foreman');
        $dhFormRes->assertSee('Dikomentari oleh Section Head');
        $dhFormRes->assertSee('Komunikasi antar shift sangat aktif');

        // Dept Head enters their comment
        $dhRes = $this->actingAs($deptHead)->postJson("/dokumen/{$doc->id}/simpan-indikator", [
            'pilihan_potensi' => 1,
            'catatan_potensi' => 'Leader Machining Line 1',
            'kelebihan' => 'Performa kerja melampaui target',
            'kekurangan' => 'Pertahankan konsistensi',
        ]);
        $dhRes->assertStatus(200);

        // Verify DB: All 3 comments are preserved!
        $ind3 = DB::table('indikator')->where('dokumen_id', $doc->id)->first();
        $this->assertEquals('Karyawan sangat rajin dan disiplin', $ind3->kelebihan_foreman);
        $this->assertEquals('Komunikasi antar shift sangat aktif', $ind3->kelebihan_section);
        $this->assertEquals('Performa kerja melampaui target', $ind3->kelebihan_dept);

        $finalHistory = json_decode($ind3->komentar_history, true);
        $this->assertArrayHasKey('foreman', $finalHistory);
        $this->assertArrayHasKey('section_head', $finalHistory);
        $this->assertArrayHasKey('dept_head', $finalHistory);

        // Verify PDF generation succeeds
        $pdfRes = $this->actingAs($deptHead)->get("/dokumen/{$doc->id}/pdf");
        $pdfRes->assertStatus(200);
    }

    /**
     * Test Section Head without Foreman scenario -> Section Head comments first, Dept Head follows.
     */
    public function test_section_head_without_foreman_comment_lifecycle(): void
    {
        $repo = app(SheetRepositoryInterface::class);

        // 2023020 has no foreman (direct under Section Head)
        // Section Head = SH-11310 (section.p1a@musashi.co.id)
        // Dept Head = DH-11300 (dept.prod1@musashi.co.id)
        $sectionHead = User::where('nrp', 'SH-11310')->firstOrFail();
        $deptHead = User::where('nrp', 'DH-11300')->firstOrFail();

        $doc = DB::table('dokumen')->where('nrp', '2023020')->firstOrFail();

        // Section Head opens form - no prior comments yet
        $shForm = $this->actingAs($sectionHead)->followingRedirects()->get("/dokumen/{$doc->id}/penilaian");
        $shForm->assertStatus(200);
        $shForm->assertDontSee('Dikomentari oleh Foreman');

        // Section Head saves comment
        $shRes = $this->actingAs($sectionHead)->postJson("/dokumen/{$doc->id}/simpan-indikator", [
            'pilihan_potensi' => 2,
            'catatan_potensi' => 'Pelatihan lanjutan CNC',
            'kelebihan' => 'Inisiatif tinggi tanpa perlu diawasi',
            'kekurangan' => 'Perlu pendalaman trouble shooting',
        ]);
        $shRes->assertStatus(200);

        $ind = DB::table('indikator')->where('dokumen_id', $doc->id)->first();
        $this->assertEquals('Inisiatif tinggi tanpa perlu diawasi', $ind->kelebihan_section);
        $this->assertEquals('Perlu pendalaman trouble shooting', $ind->kekurangan_section);

        // Advance doc to Dept Head
        DB::table('dokumen')->where('id', $doc->id)->update(['status' => 'menunggu_dept_head']);
        $repo->clearCache();

        // Dept Head views form: sees Section Head locked badge
        $dhForm = $this->actingAs($deptHead)->followingRedirects()->get("/dokumen/{$doc->id}/penilaian");
        $dhForm->assertStatus(200);
        $dhForm->assertSee('Dikomentari oleh Section Head');
        $dhForm->assertSee('Inisiatif tinggi tanpa perlu diawasi');

        // Dept Head adds comment
        $dhRes = $this->actingAs($deptHead)->postJson("/dokumen/{$doc->id}/simpan-indikator", [
            'pilihan_potensi' => 2,
            'catatan_potensi' => 'Pelatihan lanjutan CNC',
            'kelebihan' => 'Disetujui untuk ikut sertifikasi',
            'kekurangan' => 'Fokus pada target efisiensi',
        ]);
        $dhRes->assertStatus(200);

        $indFinal = DB::table('indikator')->where('dokumen_id', $doc->id)->first();
        $this->assertEquals('Inisiatif tinggi tanpa perlu diawasi', $indFinal->kelebihan_section);
        $this->assertEquals('Disetujui untuk ikut sertifikasi', $indFinal->kelebihan_dept);

        $pdfRes = $this->actingAs($deptHead)->get("/dokumen/{$doc->id}/pdf");
        $pdfRes->assertStatus(200);
    }
}
